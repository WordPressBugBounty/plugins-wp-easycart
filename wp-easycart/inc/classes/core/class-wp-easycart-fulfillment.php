<?php
/**
 * WP EasyCart — fulfillment partners ( print on demand and dropship extensions ): providers, order lines, release to
 * partners, order state ( 6.0.2 ).
 *
 * A fulfillment partner ( Printful, Printify … ) makes and ships some of a store's products. An extension registers it
 * with the filter wp_easycart_fulfillment_providers; a product it makes carries its slug in ec_product.fulfillment_provider.
 * Every order line keeps what it was bought as ( ec_orderdetail, EC_UPGRADE_DB 117 ):
 *
 *   optionitemquantity_id   the variant row the line was bought as ( 0 = none )
 *   fulfillment_provider    the partner that makes it, copied from the product when the line was inserted while that
 *                           partner was connected ( '' = the store ); after that it decides, connected or not
 *   fulfillment_status      '' | queued | sent | in_production | shipped | cancelled | failed | returned | on_hold
 *   fulfillment_ref         the partner's reference for the line or its order
 *   unit_cost               cost of one unit when the order was placed ( variant cost, else product cost > 0, else NULL )
 *
 * Release: once an order is paid ( an approved status, not refunded or cancelled ) and has lines, release() stamps
 * ec_order.fulfillment_released_at once and fires wp_easycart_order_ready_to_fulfill, which WP EasyCart Premium's
 * fulfillment core answers by sending each partner its lines. Checkout, pay links, mark paid, status changes, subscription
 * first orders and renewals all pass through it; the database update marks every order already paid as released, so an
 * old order is never sent to a partner by a later status change.
 *
 * Cancel: a move into Refunded ( 16 ) or Cancelled ( 19 ) fires wp_easycart_order_cancelled once, for every order; the
 * timeline entry ( order log key fulfillment-cancelled ) only on orders a partner has lines in.
 *
 * State: state() says how much of an order has shipped ( none | unfulfilled | partial | fulfilled ), from its packages
 * ( wp_easycart_shipments ) and its partners' lines. The order screen, the orders list and PRO's Fulfill actions read it.
 *
 * The log helpers wp_easycart_order_log() and wp_easycart_order_note() at the end of this file write order timeline
 * entries and staff comments the way WP EasyCart and PRO write them.
 *
 * @package WP_EasyCart
 * @since   6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_fulfillment' ) ) :

	/**
	 * Fulfillment partners.
	 */
	final class wp_easycart_fulfillment {

		/** The database update that added the columns. */
		const DB_VERSION = 117;

		/** Order statuses that end an order: Refunded, Cancelled. */
		const CANCELLED = array( 16, 19 );

		/** Line statuses a partner may set. */
		const LINE_STATUSES = array( 'queued', 'sent', 'in_production', 'shipped', 'cancelled', 'failed', 'returned', 'on_hold' );

		/** Line statuses that mean the partner shipped the line. */
		const LINE_SHIPPED = array( 'shipped', 'returned' );

		/** Package statuses that mean the package left ( exception: the carrier has it and reported a problem ). */
		const OUTGOING = array( 'label', 'shipped', 'delivered', 'exception' );

		/**
		 * Orders announced by cancelled() in this request, order_id => the status announced ( until the status moves on ).
		 *
		 * @var array
		 */
		private static $cancel_announced = array();

		/**
		 * Hooks.
		 */
		public static function init() {
			add_action( 'wp_easycart_order_detail_inserted', array( __CLASS__, 'on_line_inserted' ), 1, 2 );
			add_action( 'wpeasycart_order_detail_line_added', array( __CLASS__, 'on_line_added' ), 1, 2 );
			add_action( 'wpeasycart_order_inserted', array( __CLASS__, 'on_order_event' ), 50, 1 );
			add_action( 'wpeasycart_order_paid', array( __CLASS__, 'on_order_event' ), 50, 1 );
			add_action( 'wpeasycart_order_status_update', array( __CLASS__, 'on_status_update' ), 50, 3 );
			add_action( 'wpeasycart_full_order_refund', array( __CLASS__, 'on_full_refund' ), 50, 1 );
			add_action( 'wpeasycart_subscription_first_order_inserted', array( __CLASS__, 'on_subscription_order' ), 25, 1 );
			add_filter( 'wp_easycart_admin_order_history_entry', array( __CLASS__, 'history_entry' ), 5, 3 );
			add_filter( 'wp_easycart_admin_order_timeline_labels', array( __CLASS__, 'timeline_labels' ), 5, 1 );
		}

		/**
		 * The 6.0.2 fulfillment columns exist ( install_db() records the version only after dbDelta added them ).
		 *
		 * @return bool
		 */
		public static function ready() {
			return function_exists( 'get_option' ) && (int) get_option( 'ec_option_db_new_version' ) >= self::DB_VERSION;
		}

		// Providers.

		/**
		 * Registered partners.
		 *
		 * @return array slug => array( slug, label, active, url, order_url ( callable( $order_id, $ref ) | null ) ).
		 */
		public static function providers() {
			/**
			 * Fulfillment partners an extension connects ( WP EasyCart for Printful registers printful ).
			 *
			 * @since 6.0.2
			 * @param array $providers slug => array( 'label' => 'Printful', 'active' => bool, 'url' => partner dashboard URL,
			 *                         'order_url' => callable( $order_id, $ref ) returning the partner's page for an order, or null ).
			 */
			$list = apply_filters( 'wp_easycart_fulfillment_providers', array() );
			$out  = array();
			foreach ( is_array( $list ) ? $list : array() as $slug => $provider ) {
				$slug = self::slug( $slug );
				if ( '' === $slug ) {
					continue;
				}
				$provider     = is_array( $provider ) ? $provider : array();
				$label        = isset( $provider['label'] ) ? trim( wp_strip_all_tags( (string) $provider['label'] ) ) : '';
				$out[ $slug ] = array(
					'slug'      => $slug,
					'label'     => '' !== $label ? $label : ucfirst( $slug ),
					'active'    => ! empty( $provider['active'] ),
					'url'       => isset( $provider['url'] ) ? (string) $provider['url'] : '',
					'order_url' => ( isset( $provider['order_url'] ) && is_callable( $provider['order_url'] ) ) ? $provider['order_url'] : null,
				);
			}
			return $out;
		}

		/**
		 * One partner.
		 *
		 * @param string $slug Partner.
		 * @return array|null See providers().
		 */
		public static function provider( $slug ) {
			$slug      = self::slug( $slug );
			$providers = '' !== $slug ? self::providers() : array();
			return isset( $providers[ $slug ] ) ? $providers[ $slug ] : null;
		}

		/**
		 * A partner is registered and connected.
		 *
		 * @param string $slug Partner.
		 * @return bool
		 */
		public static function is_active( $slug ) {
			$provider = self::provider( $slug );
			return $provider && $provider['active'];
		}

		/**
		 * A partner's name ( its slug with a capital when it is not registered ).
		 *
		 * @param string $slug Partner.
		 * @return string
		 */
		public static function label( $slug ) {
			$provider = self::provider( $slug );
			return $provider ? $provider['label'] : ucfirst( self::slug( $slug ) );
		}

		/**
		 * The partner's page for an order, when the partner gives one.
		 *
		 * @param string $slug     Partner.
		 * @param int    $order_id Order.
		 * @param string $ref      The partner's reference.
		 * @return string
		 */
		public static function order_url( $slug, $order_id, $ref = '' ) {
			$provider = self::provider( $slug );
			if ( ! $provider || ! $provider['order_url'] ) {
				return '';
			}
			try {
				return (string) call_user_func( $provider['order_url'], (int) $order_id, (string) $ref );
			} catch ( \Throwable $e ) {
				unset( $e );
				return '';
			}
		}

		/**
		 * A partner slug, cleaned ( lower case, letters, numbers, - and _, at most 40 ).
		 *
		 * @param mixed $value Slug.
		 * @return string
		 */
		public static function slug( $value ) {
			return substr( sanitize_key( (string) $value ), 0, 40 );
		}

		// Products.

		/**
		 * The partner that makes a product ( its fulfillment_provider as saved, connected or not ).
		 *
		 * @param int|object|array $product Product ID or row.
		 * @return string '' = the store.
		 */
		public static function product_provider( $product ) {
			global $wpdb;
			$product_id = 0;
			if ( is_object( $product ) || is_array( $product ) ) {
				$product = (object) $product;
				if ( isset( $product->fulfillment_provider ) ) {
					return (string) $product->fulfillment_provider;
				}
				$product_id = isset( $product->product_id ) ? (int) $product->product_id : 0;
			} else {
				$product_id = (int) $product;
			}
			if ( $product_id <= 0 || ! self::ready() ) {
				return '';
			}
			return (string) $wpdb->get_var( $wpdb->prepare( 'SELECT fulfillment_provider FROM ec_product WHERE product_id = %d', $product_id ) );
		}

		/**
		 * Set the partner that makes a product.
		 *
		 * @param int    $product_id Product.
		 * @param string $slug       Partner ( '' = the store ).
		 * @return bool
		 */
		public static function set_product_provider( $product_id, $slug ) {
			global $wpdb;
			if ( ! self::ready() || (int) $product_id <= 0 ) {
				return false;
			}
			$done = $wpdb->update( 'ec_product', array( 'fulfillment_provider' => self::slug( $slug ) ), array( 'product_id' => (int) $product_id ), array( '%s' ), array( '%d' ) );
			if ( method_exists( 'ec_db', 'product_cache_changed' ) ) {
				ec_db::product_cache_changed();
			}
			return false !== $done;
		}

		// Order lines.

		/**
		 * Action wp_easycart_order_detail_inserted: record the line's variant, partner and cost.
		 *
		 * @param int    $orderdetail_id Line.
		 * @param object $cart_item      The cart line ( or the stored line for a subscription order or renewal ).
		 */
		public static function on_line_inserted( $orderdetail_id, $cart_item = null ) {
			self::stamp_line( $orderdetail_id, $cart_item );
		}

		/**
		 * Action wpeasycart_order_detail_line_added: a line added on the order screen. A partner's line added to an order
		 * already released to its partners is announced ( wp_easycart_order_fulfillment_lines_added ): the release does not
		 * fire again, so the partner would never hear of it. On an order already packed, the line joins its partner's package
		 * ( wp_easycart_packages::add_partner_line() ).
		 *
		 * @param int $order_id       Order.
		 * @param int $orderdetail_id Line.
		 */
		public static function on_line_added( $order_id, $orderdetail_id = 0 ) {
			global $wpdb;
			if ( (int) $orderdetail_id <= 0 || ! self::stamp_line( $orderdetail_id ) ) {
				return;
			}
			$line = $wpdb->get_row( $wpdb->prepare( 'SELECT order_id, fulfillment_provider FROM ec_orderdetail WHERE orderdetail_id = %d', (int) $orderdetail_id ) );
			if ( ! $line || '' === (string) $line->fulfillment_provider ) {
				return;
			}
			/* An order already packed: the line joins its partner's package ( the store's package editor leaves it alone ). */
			if ( class_exists( 'wp_easycart_packages' ) && method_exists( 'wp_easycart_packages', 'add_partner_line' ) ) {
				wp_easycart_packages::add_partner_line( (int) $line->order_id, (int) $orderdetail_id );
			}
			if ( ! self::released( (int) $line->order_id ) ) {
				return;
			}
			/**
			 * Fulfillment partner lines were added to an order already released to its partners ( a line added on the order
			 * screen ). Whoever sent the order decides whether to send them too.
			 *
			 * @since 6.0.2
			 * @param int   $order_id        Order.
			 * @param int[] $orderdetail_ids The lines added.
			 */
			do_action( 'wp_easycart_order_fulfillment_lines_added', (int) $line->order_id, array( (int) $orderdetail_id ) );
		}

		/**
		 * Record what a line was bought as: its variant row, the partner that makes it and the cost of one unit. Every
		 * insert path calls this once ( checkout through wp_easycart_order_detail_inserted, lines added on the order
		 * screen, New order, duplicated orders, subscription orders and renewals ).
		 *
		 * A line is a partner's line only when its partner is connected ( is_active() ) as the line is inserted: a line of
		 * a partner that is disconnected then is the store's to make and ship, as the checkout charged it. From then on
		 * the recorded partner decides everywhere ( packing, state(), the Fulfill actions ), connected or not.
		 *
		 * @param int         $orderdetail_id Line.
		 * @param object|null $hint           Cart line when there is one: optionitemquantity_id, fulfillment_provider,
		 *                                    variant_cost and optionitem1_id..5 are used when set.
		 * @return bool
		 */
		public static function stamp_line( $orderdetail_id, $hint = null ) {
			global $wpdb;
			if ( ! self::ready() || (int) $orderdetail_id <= 0 ) {
				return false;
			}
			$line = $wpdb->get_row( $wpdb->prepare( 'SELECT orderdetail_id, order_id, product_id, optionitem_id_1, optionitem_id_2, optionitem_id_3, optionitem_id_4, optionitem_id_5, optionitemquantity_id, fulfillment_provider, unit_cost FROM ec_orderdetail WHERE orderdetail_id = %d', (int) $orderdetail_id ) );
			if ( ! $line ) {
				return false;
			}
			$hint    = is_object( $hint ) ? $hint : null;
			$product = ( (int) $line->product_id > 0 ) ? $wpdb->get_row( $wpdb->prepare( 'SELECT product_id, fulfillment_provider, product_cost FROM ec_product WHERE product_id = %d', (int) $line->product_id ) ) : null;

			// The partner: the product's now, else what the cart line or the copied line said.
			if ( $product ) {
				$provider = (string) $product->fulfillment_provider;
			} elseif ( $hint && isset( $hint->fulfillment_provider ) && is_scalar( $hint->fulfillment_provider ) ) {
				$provider = (string) $hint->fulfillment_provider;
			} else {
				$provider = (string) $line->fulfillment_provider;
			}
			if ( '' !== $provider && ! self::is_active( $provider ) ) {
				$provider = ''; /* not connected now: the store makes and ships it */
			}

			// The variant row.
			$slots      = self::line_slots( $line, $hint );
			$variant    = ( array_sum( $slots ) > 0 && (int) $line->product_id > 0 ) ? self::variant_row( (int) $line->product_id, $slots ) : null;
			$variant_id = 0;
			if ( $hint && ! empty( $hint->optionitemquantity_id ) ) {
				$variant_id = (int) $hint->optionitemquantity_id;
			} elseif ( $variant ) {
				$variant_id = (int) $variant->optionitemquantity_id;
			} else {
				$variant_id = (int) $line->optionitemquantity_id;
			}

			// The cost of one unit.
			$cost = null;
			if ( $hint && isset( $hint->variant_cost ) && is_numeric( $hint->variant_cost ) ) {
				$cost = (float) $hint->variant_cost;
			} elseif ( $variant && isset( $variant->cost ) && null !== $variant->cost && is_numeric( $variant->cost ) ) {
				$cost = (float) $variant->cost;
			} elseif ( $product && (float) $product->product_cost > 0 ) {
				$cost = (float) $product->product_cost;
			} elseif ( ! $product && null !== $line->unit_cost ) {
				$cost = (float) $line->unit_cost;
			}

			/**
			 * What a new order line records about its fulfillment.
			 *
			 * @since 6.0.2
			 * @param array       $data optionitemquantity_id, fulfillment_provider, unit_cost ( float or null ).
			 * @param object      $line The line as inserted.
			 * @param object|null $hint The cart line, when there is one.
			 */
			$data = apply_filters(
				'wp_easycart_order_line_fulfillment_data',
				array(
					'optionitemquantity_id' => $variant_id,
					'fulfillment_provider'  => self::slug( $provider ),
					'unit_cost'             => $cost,
				),
				$line,
				$hint
			);
			$cost = ( isset( $data['unit_cost'] ) && is_numeric( $data['unit_cost'] ) ) ? round( (float) $data['unit_cost'], 3 ) : null;
			if ( null === $cost ) {
				$done = $wpdb->query( $wpdb->prepare( 'UPDATE ec_orderdetail SET optionitemquantity_id = %d, fulfillment_provider = %s, unit_cost = NULL WHERE orderdetail_id = %d', isset( $data['optionitemquantity_id'] ) ? (int) $data['optionitemquantity_id'] : 0, isset( $data['fulfillment_provider'] ) ? self::slug( $data['fulfillment_provider'] ) : '', (int) $orderdetail_id ) );
			} else {
				$done = $wpdb->query( $wpdb->prepare( 'UPDATE ec_orderdetail SET optionitemquantity_id = %d, fulfillment_provider = %s, unit_cost = %f WHERE orderdetail_id = %d', isset( $data['optionitemquantity_id'] ) ? (int) $data['optionitemquantity_id'] : 0, isset( $data['fulfillment_provider'] ) ? self::slug( $data['fulfillment_provider'] ) : '', $cost, (int) $orderdetail_id ) );
			}
			return false !== $done;
		}

		/**
		 * Record every line of an order ( stamp_line() ).
		 *
		 * @param int $order_id Order.
		 * @return int Lines recorded.
		 */
		public static function stamp_order( $order_id ) {
			global $wpdb;
			if ( ! self::ready() ) {
				return 0;
			}
			$count = 0;
			foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SELECT orderdetail_id FROM ec_orderdetail WHERE order_id = %d', (int) $order_id ) ) as $orderdetail_id ) {
				if ( self::stamp_line( (int) $orderdetail_id ) ) {
					++$count;
				}
			}
			return $count;
		}

		/**
		 * The five option item ids of a line ( the stored row's, else the cart line's ).
		 *
		 * @param object      $line Line row.
		 * @param object|null $hint Cart line.
		 * @return int[] Keys 1..5.
		 */
		private static function line_slots( $line, $hint ) {
			$slots = array();
			for ( $slot = 1; $slot <= 5; $slot++ ) {
				$slots[ $slot ] = isset( $line->{'optionitem_id_' . $slot} ) ? (int) $line->{'optionitem_id_' . $slot} : 0;
			}
			if ( ! array_sum( $slots ) && $hint ) {
				for ( $slot = 1; $slot <= 5; $slot++ ) {
					$key            = 'optionitem' . $slot . '_id';
					$slots[ $slot ] = isset( $hint->{$key} ) ? (int) $hint->{$key} : 0;
				}
			}
			return $slots;
		}

		/**
		 * A product's variant row for five option items.
		 *
		 * @param int   $product_id Product.
		 * @param array $slots      Option item ids, keys 1..5.
		 * @return object|null
		 */
		private static function variant_row( $product_id, $slots ) {
			global $wpdb;
			if ( class_exists( 'wp_easycart_variants' ) && method_exists( 'wp_easycart_variants', 'find' ) ) {
				$row = wp_easycart_variants::find( $product_id, $slots );
				return is_object( $row ) ? $row : null;
			}
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_optionitemquantity WHERE product_id = %d AND optionitem_id_1 = %d AND optionitem_id_2 = %d AND optionitem_id_3 = %d AND optionitem_id_4 = %d AND optionitem_id_5 = %d ORDER BY optionitemquantity_id ASC LIMIT 1', (int) $product_id, (int) $slots[1], (int) $slots[2], (int) $slots[3], (int) $slots[4], (int) $slots[5] ) );
			return $row ? $row : null;
		}

		/**
		 * An order's lines ( every column ), each with to_fulfill = quantity less refunded ( never below 0 ).
		 *
		 * @param int         $order_id Order.
		 * @param string|null $provider null = every line, '' = the store's lines, a slug = that partner's lines.
		 * @return array Rows.
		 */
		public static function lines( $order_id, $provider = null ) {
			global $wpdb;
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ec_orderdetail WHERE order_id = %d ORDER BY orderdetail_id ASC', (int) $order_id ) );
			$out  = array();
			foreach ( (array) $rows as $row ) {
				$slug = isset( $row->fulfillment_provider ) ? (string) $row->fulfillment_provider : '';
				if ( null !== $provider && self::slug( $provider ) !== $slug ) {
					continue;
				}
				$row->fulfillment_provider = $slug;
				$row->to_fulfill           = max( 0, (int) $row->quantity - (int) $row->refunded_quantity );
				$out[]                     = $row;
			}
			return $out;
		}

		/**
		 * A fulfillment partner has at least one line in the order ( the recorded ec_orderdetail.fulfillment_provider ).
		 *
		 * @since 6.0.2
		 * @param int $order_id Order.
		 * @return bool False before the database update.
		 */
		public static function has_partner_lines( $order_id ) {
			global $wpdb;
			if ( (int) $order_id <= 0 || ! self::ready() ) {
				return false;
			}
			return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT orderdetail_id FROM ec_orderdetail WHERE order_id = %d AND fulfillment_provider <> '' LIMIT 1", (int) $order_id ) );
		}

		/**
		 * Change the fulfillment status or reference of some of an order's lines.
		 *
		 * @param int   $order_id        Order.
		 * @param array $orderdetail_ids Lines ( ids of other orders are ignored ).
		 * @param array $data            fulfillment_status ( see LINE_STATUSES, or '' ), fulfillment_ref.
		 * @return int Lines changed.
		 */
		public static function set_lines( $order_id, $orderdetail_ids, $data ) {
			global $wpdb;
			if ( ! self::ready() ) {
				return 0;
			}
			$ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $orderdetail_ids ) ) ) );
			if ( ! $ids ) {
				return 0;
			}
			$data  = is_array( $data ) ? $data : array();
			$clean = array();
			if ( array_key_exists( 'fulfillment_status', $data ) ) {
				$status = sanitize_key( (string) $data['fulfillment_status'] );
				if ( '' === $status || in_array( $status, self::LINE_STATUSES, true ) ) {
					$clean['fulfillment_status'] = $status;
				}
			}
			if ( array_key_exists( 'fulfillment_ref', $data ) ) {
				$ref                      = trim( sanitize_text_field( (string) $data['fulfillment_ref'] ) );
				$clean['fulfillment_ref'] = function_exists( 'mb_substr' ) ? mb_substr( $ref, 0, 100 ) : substr( $ref, 0, 100 );
			}
			if ( ! $clean ) {
				return 0;
			}
			$sets = array();
			foreach ( array_keys( $clean ) as $column ) {
				$sets[] = $column . ' = %s'; /* one of two fixed column names */
			}
			$args   = array_values( $clean );
			$args[] = (int) $order_id;
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- fixed column names with %s placeholders; the ids are integers.
			$done = (int) $wpdb->query( $wpdb->prepare( 'UPDATE ec_orderdetail SET ' . implode( ', ', $sets ) . ' WHERE order_id = %d AND orderdetail_id IN ( ' . implode( ',', $ids ) . ' )', $args ) );
			/**
			 * A partner's lines changed status or reference.
			 *
			 * @since 6.0.2
			 * @param int   $order_id        Order.
			 * @param int[] $orderdetail_ids Lines.
			 * @param array $data            What changed ( fulfillment_status, fulfillment_ref ).
			 */
			do_action( 'wp_easycart_order_lines_fulfillment_updated', (int) $order_id, $ids, $clean );
			return $done;
		}

		/**
		 * The units of an order still to ship, by line, for the store or one partner: shippable lines less refunds, less what
		 * outgoing packages already hold ( a partner's lines marked shipped count as shipped ).
		 *
		 * @param int    $order_id Order.
		 * @param string $provider '' = the store's lines, a slug = that partner's.
		 * @return array orderdetail_id => quantity.
		 */
		public static function open_items( $order_id, $provider = '' ) {
			$provider = self::slug( $provider );
			$covered  = self::covered( $order_id );
			$out      = array();
			foreach ( self::lines( $order_id, $provider ) as $line ) {
				if ( ! self::ships( $line ) || ( '' !== $provider && in_array( (string) $line->fulfillment_status, array_merge( self::LINE_SHIPPED, array( 'cancelled' ) ), true ) ) ) {
					continue;
				}
				$left = (int) $line->to_fulfill - ( isset( $covered[ (int) $line->orderdetail_id ] ) ? $covered[ (int) $line->orderdetail_id ] : 0 );
				if ( $left > 0 ) {
					$out[ (int) $line->orderdetail_id ] = $left;
				}
			}
			return $out;
		}

		/**
		 * A line is shipped: shippable, not a download or gift card, with units left after refunds.
		 *
		 * @param object $line Line from lines().
		 * @return bool
		 */
		private static function ships( $line ) {
			return ! empty( $line->is_shippable ) && empty( $line->is_download ) && empty( $line->is_giftcard ) && (int) $line->to_fulfill > 0;
		}

		/**
		 * Units in outgoing packages, by line.
		 *
		 * @param int $order_id Order.
		 * @return array orderdetail_id => quantity.
		 */
		private static function covered( $order_id ) {
			$out = array();
			if ( ! class_exists( 'wp_easycart_shipments' ) || ! wp_easycart_shipments::ready() ) {
				return $out;
			}
			foreach ( wp_easycart_shipments::for_order( $order_id, array( 'returns' => false ) ) as $row ) {
				if ( in_array( $row->status, self::OUTGOING, true ) ) {
					foreach ( $row->items as $detail_id => $qty ) {
						$out[ (int) $detail_id ] = ( isset( $out[ (int) $detail_id ] ) ? $out[ (int) $detail_id ] : 0 ) + (int) $qty;
					}
				}
			}
			return $out;
		}

		// Release.

		/**
		 * Release a paid order to its partners, once: an approved status that is not Refunded or Cancelled, at least one
		 * line, and not released before. Only the request whose conditional UPDATE changed the row fires
		 * wp_easycart_order_ready_to_fulfill, and writes the fulfillment-released log when a partner has lines in the order.
		 *
		 * @param int $order_id Order.
		 * @return bool Released now.
		 */
		public static function release( $order_id ) {
			global $wpdb;
			$order_id = (int) $order_id;
			if ( $order_id <= 0 || ! self::ready() ) {
				return false;
			}
			$order = $wpdb->get_row( $wpdb->prepare( 'SELECT ec_order.orderstatus_id, ec_order.fulfillment_released_at, ec_orderstatus.is_approved FROM ec_order LEFT JOIN ec_orderstatus ON ec_orderstatus.status_id = ec_order.orderstatus_id WHERE ec_order.order_id = %d', $order_id ) );
			if ( ! $order || null !== $order->fulfillment_released_at || empty( $order->is_approved ) || in_array( (int) $order->orderstatus_id, self::CANCELLED, true ) ) {
				return false;
			}
			// Checkout approves the payment before it inserts the lines: an order with no lines yet is released later, when
			// wpeasycart_order_inserted fires.
			if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT orderdetail_id FROM ec_orderdetail WHERE order_id = %d LIMIT 1', $order_id ) ) ) {
				return false;
			}
			$done = $wpdb->query( $wpdb->prepare( 'UPDATE ec_order, ec_orderstatus SET ec_order.fulfillment_released_at = %s WHERE ec_order.order_id = %d AND ec_order.fulfillment_released_at IS NULL AND ec_orderstatus.status_id = ec_order.orderstatus_id AND ec_orderstatus.is_approved = 1 AND ec_order.orderstatus_id NOT IN ( 16, 19 )', current_time( 'mysql', true ), $order_id ) );
			if ( 1 !== (int) $done ) {
				return false;
			}
			/* The timeline entry only on orders a partner has lines in ( every other order is released too, silently ). */
			if ( $wpdb->get_var( $wpdb->prepare( "SELECT orderdetail_id FROM ec_orderdetail WHERE order_id = %d AND fulfillment_provider <> '' LIMIT 1", $order_id ) ) ) {
				wp_easycart_order_log( $order_id, 'fulfillment-released' );
			}
			/**
			 * An order is paid and ready for its fulfillment partners ( fires once per order ).
			 *
			 * @since 6.0.2
			 * @param int $order_id Order.
			 */
			do_action( 'wp_easycart_order_ready_to_fulfill', $order_id );
			return true;
		}

		/**
		 * The order was released to partners.
		 *
		 * @param int $order_id Order.
		 * @return bool
		 */
		public static function released( $order_id ) {
			global $wpdb;
			if ( ! self::ready() ) {
				return false;
			}
			return null !== $wpdb->get_var( $wpdb->prepare( 'SELECT fulfillment_released_at FROM ec_order WHERE order_id = %d', (int) $order_id ) );
		}

		/**
		 * Actions wpeasycart_order_inserted, wpeasycart_order_paid: release when paid.
		 *
		 * @param int $order_id Order.
		 */
		public static function on_order_event( $order_id ) {
			self::release( $order_id );
		}

		/**
		 * Action wpeasycart_order_status_update: release when the status is paid; announce a cancel.
		 *
		 * @param int      $order_id           Order.
		 * @param int      $status_id          New status.
		 * @param int|null $previous_status_id Status before, when the caller knew it.
		 */
		public static function on_status_update( $order_id, $status_id = 0, $previous_status_id = null ) {
			if ( ! in_array( (int) $status_id, self::CANCELLED, true ) ) {
				unset( self::$cancel_announced[ (int) $order_id ] ); /* moved on: a later move into 16 / 19 is a new one */
			}
			self::release( $order_id );
			self::cancelled( $order_id, $status_id, $previous_status_id );
		}

		/**
		 * Action wpeasycart_full_order_refund: a full refund ends the order ( announced once, with the status hook ).
		 *
		 * @param int $order_id Order.
		 */
		public static function on_full_refund( $order_id ) {
			global $wpdb;
			$status = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT orderstatus_id FROM ec_order WHERE order_id = %d', (int) $order_id ) );
			self::cancelled( $order_id, $status, null );
		}

		/**
		 * Action wpeasycart_subscription_first_order_inserted: a subscription's first order is packed and released like a
		 * checkout order ( it never fires wpeasycart_order_inserted ).
		 *
		 * @param int $order_id Order.
		 */
		public static function on_subscription_order( $order_id ) {
			self::order_created( $order_id );
		}

		/**
		 * An order made outside the checkout's wpeasycart_order_inserted ( subscription first orders, renewals ): pack it
		 * ( Settings › Shipping › Boxes › Pack new orders ) and release it when paid.
		 *
		 * @param int $order_id Order.
		 */
		public static function order_created( $order_id ) {
			if ( class_exists( 'wp_easycart_shipments' ) && method_exists( 'wp_easycart_shipments', 'on_order_inserted' ) ) {
				wp_easycart_shipments::on_order_inserted( (int) $order_id );
			}
			self::release( $order_id );
		}

		// Cancel.

		/**
		 * Announce an order moving into Refunded or Cancelled, once per move: wp_easycart_order_cancelled, for every order.
		 * A second report of the same move ( the status hook and the full refund hook, or two paths ) is recognised by a
		 * mark written just before firing, with no change to another status logged since. On an order released to a fulfillment
		 * partner with lines in it, the mark is the fulfillment-cancelled timeline entry ( "Fulfillment partners told the order
		 * ended" ); on every other order it writes nothing to the timeline: a per-request flag and a transient
		 * ( wpec_order_cancelled_{id}, 30 days: the order log id reached when it was announced ).
		 *
		 * @since 6.0.2 the timeline entry only on orders with partner lines.
		 * @param int      $order_id           Order.
		 * @param int      $status_id          Status now.
		 * @param int|null $previous_status_id Status before ( null or 0 when unknown ).
		 * @return bool Announced now.
		 */
		public static function cancelled( $order_id, $status_id, $previous_status_id = null ) {
			global $wpdb;
			$order_id  = (int) $order_id;
			$status_id = (int) $status_id;
			$previous  = ( null === $previous_status_id || '' === $previous_status_id ) ? 0 : (int) $previous_status_id;
			if ( $order_id <= 0 || ! in_array( $status_id, self::CANCELLED, true ) || in_array( $previous, self::CANCELLED, true ) ) {
				return false;
			}
			if ( $status_id !== (int) $wpdb->get_var( $wpdb->prepare( 'SELECT orderstatus_id FROM ec_order WHERE order_id = %d', $order_id ) ) ) {
				return false;
			}
			/* Reported already in this request ( cleared when the status moves on, on_status_update() ). */
			if ( isset( self::$cancel_announced[ $order_id ] ) && $status_id === self::$cancel_announced[ $order_id ] ) {
				return false;
			}
			// 6.0.2: the timeline entry ( and its dedupe ) only for an order that went to its partners; an order cancelled before
			// release was never theirs, so it dedupes like any other order.
			$partner = self::has_partner_lines( $order_id ) && self::released( $order_id );
			if ( $partner ) {
				/* Only this method's own entries count: a partner writes the same key with provider meta when it cancels its
				   part of an order that stays open, and that must not hide a later store cancel. */
				$last = (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX( l.order_log_id ) FROM ec_order_log l WHERE l.order_id = %d AND l.order_log_key = %s AND NOT EXISTS ( SELECT 1 FROM ec_order_log_meta m WHERE m.order_log_id = l.order_log_id AND m.order_log_meta_key = 'provider' )", $order_id, 'fulfillment-cancelled' ) );
			} else {
				$mark = function_exists( 'get_transient' ) ? get_transient( 'wpec_order_cancelled_' . $order_id ) : false;
				$last = ( is_array( $mark ) && isset( $mark['log'] ) ) ? (int) $mark['log'] : 0;
				if ( is_array( $mark ) && $last <= 0 ) {
					$last = -1; /* announced when the order had no log yet: every logged status change comes after it */
				}
			}
			if ( 0 !== $last && ! $wpdb->get_var( $wpdb->prepare( "SELECT order_log_meta_id FROM ec_order_log_meta WHERE order_id = %d AND order_log_id > %d AND order_log_meta_key = 'orderstatus_id' AND order_log_meta_value NOT IN ( '16', '19' ) LIMIT 1", $order_id, max( 0, $last ) ) ) ) {
				self::$cancel_announced[ $order_id ] = $status_id;
				return false;
			}
			self::$cancel_announced[ $order_id ] = $status_id;
			if ( $partner ) {
				wp_easycart_order_log(
					$order_id,
					'fulfillment-cancelled',
					array(
						'orderstatus_id'  => $status_id,
						'previous_status' => $previous,
					)
				);
			} elseif ( function_exists( 'set_transient' ) ) {
				set_transient(
					'wpec_order_cancelled_' . $order_id,
					array(
						'log' => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX( order_log_id ) FROM ec_order_log WHERE order_id = %d', $order_id ) ),
					),
					30 * DAY_IN_SECONDS
				);
			}
			/**
			 * An order moved into Refunded ( 16 ) or Cancelled ( 19 ): partners should stop work on it ( fires once per move ).
			 *
			 * @since 6.0.2
			 * @param int $order_id           Order.
			 * @param int $status_id          Status now.
			 * @param int $previous_status_id Status before ( 0 when unknown ).
			 */
			do_action( 'wp_easycart_order_cancelled', $order_id, $status_id, $previous );
			return true;
		}

		// State.

		/**
		 * How much of an order has shipped.
		 *
		 * Units are shippable lines less refunds ( a partner's cancelled lines are left out ). A unit counts as shipped when
		 * an outgoing package holds it ( label, shipped, delivered, or a delivery problem ), or its partner marked its line
		 * shipped. The store's own units also count as shipped when the order is in a fulfilled status ( Shipped, Picked
		 * up, Delivered ), or carries a tracking number that no package of the store's and no partner's package explains
		 * ( typed on the order screen before packages existed ): an order with no packages and no partner lines keeps
		 * the old rule, a tracking number or a fulfilled status = fulfilled ( legacy ).
		 *
		 * @param int         $order_id Order.
		 * @param object|null $order    The order row, when the caller has it ( needs orderstatus_id and tracking_number ).
		 * @return array state ( none | unfulfilled | partial | fulfilled ), total, shipped, providers ( slug => lines, units,
		 *               shipped, status, label ), store ( units, shipped ), legacy ( bool ).
		 */
		public static function state( $order_id, $order = null ) {
			global $wpdb;
			$order_id = (int) $order_id;
			$out      = array(
				'state'     => 'none',
				'total'     => 0,
				'shipped'   => 0,
				'providers' => array(),
				'store'     => array(
					'units'   => 0,
					'shipped' => 0,
				),
				'legacy'    => true,
			);
			if ( ! is_object( $order ) || ! isset( $order->orderstatus_id ) || ! property_exists( $order, 'tracking_number' ) ) {
				$order = $wpdb->get_row( $wpdb->prepare( 'SELECT order_id, orderstatus_id, tracking_number FROM ec_order WHERE order_id = %d', $order_id ) );
			}
			if ( ! $order ) {
				return $out;
			}
			$rows = ( class_exists( 'wp_easycart_shipments' ) && wp_easycart_shipments::ready() ) ? wp_easycart_shipments::for_order( $order_id, array( 'returns' => false ) ) : array();

			$covered          = array();
			$partner_tracking = array();
			$store_out        = false;
			foreach ( $rows as $row ) {
				$partner = isset( $row->partner ) ? (string) $row->partner : '';
				if ( in_array( $row->status, self::OUTGOING, true ) ) {
					foreach ( $row->items as $detail_id => $qty ) {
						$covered[ (int) $detail_id ] = ( isset( $covered[ (int) $detail_id ] ) ? $covered[ (int) $detail_id ] : 0 ) + (int) $qty;
					}
					if ( '' === $partner ) {
						$store_out = true;
					}
				}
				if ( '' !== $partner && '' !== (string) $row->tracking_number ) {
					$partner_tracking[] = (string) $row->tracking_number;
				}
			}
			$tracking         = trim( (string) $order->tracking_number );
			$fulfilled_status = class_exists( 'wp_easycart_shipments' ) ? wp_easycart_shipments::is_fulfilled_status( (int) $order->orderstatus_id ) : in_array( (int) $order->orderstatus_id, array( 2, 18 ), true );
			$legacy_tracking  = '' !== $tracking && ! $store_out && ! in_array( $tracking, $partner_tracking, true );
			$statuses         = array();
			$has_partner      = false;

			foreach ( self::lines( $order_id ) as $line ) {
				if ( ! self::ships( $line ) ) {
					continue;
				}
				$detail_id = (int) $line->orderdetail_id;
				$units     = (int) $line->to_fulfill;
				$by_rows   = isset( $covered[ $detail_id ] ) ? min( $units, $covered[ $detail_id ] ) : 0;
				$slug      = (string) $line->fulfillment_provider;
				if ( '' !== $slug ) {
					$has_partner = true;
					$status      = isset( $line->fulfillment_status ) ? (string) $line->fulfillment_status : '';
					if ( ! isset( $out['providers'][ $slug ] ) ) {
						$out['providers'][ $slug ] = array(
							'lines'   => 0,
							'units'   => 0,
							'shipped' => 0,
							'status'  => '',
							'label'   => self::label( $slug ),
						);
					}
					++$out['providers'][ $slug ]['lines'];
					$statuses[ $slug ][ $status ] = ( isset( $statuses[ $slug ][ $status ] ) ? $statuses[ $slug ][ $status ] : 0 ) + 1;
					if ( 'cancelled' === $status ) {
						continue;
					}
					$done                                  = in_array( $status, self::LINE_SHIPPED, true ) ? $units : $by_rows;
					$out['providers'][ $slug ]['units']   += $units;
					$out['providers'][ $slug ]['shipped'] += $done;
				} else {
					$done                     = ( $fulfilled_status || $legacy_tracking ) ? $units : $by_rows;
					$out['store']['units']   += $units;
					$out['store']['shipped'] += $done;
				}
				$out['total']   += $units;
				$out['shipped'] += $done;
			}
			foreach ( $statuses as $slug => $counts ) {
				arsort( $counts );
				$out['providers'][ $slug ]['status'] = (string) key( $counts );
			}
			$out['legacy'] = ! $rows && ! $has_partner;
			if ( $out['total'] <= 0 ) {
				$out['state'] = 'none';
			} elseif ( $out['shipped'] >= $out['total'] ) {
				$out['state'] = 'fulfilled';
			} elseif ( $out['shipped'] <= 0 ) {
				$out['state'] = 'unfulfilled';
			} else {
				$out['state'] = 'partial';
			}
			/**
			 * An order's fulfillment state.
			 *
			 * @since 6.0.2
			 * @param array $state    See state().
			 * @param int   $order_id Order.
			 */
			return (array) apply_filters( 'wp_easycart_order_fulfillment_state', $out, $order_id );
		}

		/**
		 * The store's own units still to ship ( store part of state() ).
		 *
		 * @param array $state state().
		 * @return bool
		 */
		public static function store_open( $state ) {
			return isset( $state['store']['units'], $state['store']['shipped'] ) && (int) $state['store']['units'] > (int) $state['store']['shipped'];
		}

		/**
		 * Partners with units still to ship.
		 *
		 * @param array $state state().
		 * @return array slug => label.
		 */
		public static function open_providers( $state ) {
			$out = array();
			foreach ( isset( $state['providers'] ) ? (array) $state['providers'] : array() as $slug => $provider ) {
				if ( (int) $provider['units'] > (int) $provider['shipped'] ) {
					$out[ $slug ] = (string) $provider['label'];
				}
			}
			return $out;
		}

		/**
		 * SQL, true while an order still has units to ship ( the orders list's Awaiting filters ): the store's lines by the
		 * same rules as state(), partners' lines while a partner package waits or, before the order has any package, while
		 * a partner line is not shipped. Needs ready().
		 *
		 * @param string $alias Order table alias.
		 * @return string
		 */
		public static function open_sql( $alias = 'ec_order' ) {
			$o         = preg_replace( '/[^A-Za-z0-9_]/', '', (string) $alias );
			$fulfilled = implode( ', ', array_map( 'intval', class_exists( 'wp_easycart_shipments' ) ? wp_easycart_shipments::fulfilled_status_ids() : array( 2, 18 ) ) );
			$ship      = 'd.is_shippable = 1 AND d.is_download = 0 AND d.is_giftcard = 0 AND d.quantity > d.refunded_quantity';
			$is_ptr    = "s.provider <> '' AND EXISTS ( SELECT 1 FROM ec_orderdetail pd WHERE pd.order_id = s.order_id AND pd.fulfillment_provider = s.provider )";
			$is_store  = "( s.provider = '' OR NOT EXISTS ( SELECT 1 FROM ec_orderdetail pd WHERE pd.order_id = s.order_id AND pd.fulfillment_provider = s.provider ) )";
			$out       = "s.is_return = 0 AND s.status IN ( 'label', 'shipped', 'delivered', 'exception' )";
			$store_out = "EXISTS ( SELECT 1 FROM ec_order_shipment s WHERE s.order_id = {$o}.order_id AND {$out} AND {$is_store} )";
			$store_pk  = "EXISTS ( SELECT 1 FROM ec_order_shipment s WHERE s.order_id = {$o}.order_id AND s.is_return = 0 AND s.status = 'packed' AND {$is_store} )";
			$ptr_track = "EXISTS ( SELECT 1 FROM ec_order_shipment s WHERE s.order_id = {$o}.order_id AND s.is_return = 0 AND s.status <> 'voided' AND s.tracking_number = {$o}.tracking_number AND {$is_ptr} )";
			$store     = "( EXISTS ( SELECT 1 FROM ec_orderdetail d WHERE d.order_id = {$o}.order_id AND d.fulfillment_provider = '' AND {$ship} ) AND {$o}.orderstatus_id NOT IN ( {$fulfilled} ) AND ( ( {$store_out} AND {$store_pk} ) OR ( NOT {$store_out} AND ( {$o}.tracking_number = '' OR {$ptr_track} ) ) ) )";
			$ptr_line  = "EXISTS ( SELECT 1 FROM ec_orderdetail d WHERE d.order_id = {$o}.order_id AND d.fulfillment_provider <> '' AND {$ship} AND d.fulfillment_status NOT IN ( 'shipped', 'returned', 'cancelled' ) )";
			$ptr_pk    = "EXISTS ( SELECT 1 FROM ec_order_shipment s WHERE s.order_id = {$o}.order_id AND s.is_return = 0 AND s.status = 'packed' AND {$is_ptr} )";
			$no_rows   = "NOT EXISTS ( SELECT 1 FROM ec_order_shipment s WHERE s.order_id = {$o}.order_id AND s.is_return = 0 AND s.status <> 'voided' )";
			return "( {$store} OR ( {$ptr_line} AND ( {$ptr_pk} OR {$no_rows} ) ) )";
		}

		/**
		 * SQL, true when some of an order has left: an outgoing package, a tracking number or a fulfilled status. Needs ready().
		 *
		 * @param string $alias Order table alias.
		 * @return string
		 */
		public static function some_shipped_sql( $alias = 'ec_order' ) {
			$o         = preg_replace( '/[^A-Za-z0-9_]/', '', (string) $alias );
			$fulfilled = implode( ', ', array_map( 'intval', class_exists( 'wp_easycart_shipments' ) ? wp_easycart_shipments::fulfilled_status_ids() : array( 2, 18 ) ) );
			return "( {$o}.tracking_number <> '' OR {$o}.orderstatus_id IN ( {$fulfilled} ) OR EXISTS ( SELECT 1 FROM ec_order_shipment s WHERE s.order_id = {$o}.order_id AND s.is_return = 0 AND s.status IN ( 'label', 'shipped', 'delivered', 'exception' ) ) OR EXISTS ( SELECT 1 FROM ec_orderdetail d WHERE d.order_id = {$o}.order_id AND d.fulfillment_status IN ( 'shipped', 'returned' ) ) )";
		}

		// Words.

		/**
		 * A customer-facing phrase from the language file ( section fulfillment ), else the fallback.
		 *
		 * @param string $key      Key.
		 * @param string $fallback Translated default.
		 * @return string
		 */
		public static function text( $key, $fallback ) {
			if ( function_exists( 'wp_easycart_language' ) ) {
				$text = wp_easycart_language()->get_text( 'fulfillment', $key );
				if ( is_string( $text ) && '' !== trim( $text ) ) {
					return $text;
				}
			}
			return (string) $fallback;
		}

		/**
		 * Filter wp_easycart_admin_order_history_entry ( WP EasyCart PRO's order timeline ): the fulfillment-* entries.
		 *
		 * @param array|null $entry Entry so far.
		 * @param string     $key   Order log key.
		 * @param array      $meta  Meta by key.
		 * @return array|null
		 */
		public static function history_entry( $entry, $key = '', $meta = array() ) {
			if ( null !== $entry || 0 !== strpos( (string) $key, 'fulfillment-' ) ) {
				return $entry;
			}
			$meta     = is_array( $meta ) ? $meta : array();
			$provider = isset( $meta['provider'] ) && '' !== (string) $meta['provider'] ? self::label( $meta['provider'] ) : '';
			$titles   = self::log_titles( $provider );
			if ( ! isset( $titles[ $key ] ) ) {
				return $entry;
			}
			$parts = array();
			foreach ( array( 'detail', 'ref' ) as $field ) {
				if ( isset( $meta[ $field ] ) && '' !== trim( (string) $meta[ $field ] ) ) {
					$parts[] = esc_html( (string) $meta[ $field ] );
				}
			}
			$icons = array(
				'fulfillment-released'  => 'dashicons-products',
				'fulfillment-sent'      => 'dashicons-migrate',
				'fulfillment-update'    => 'dashicons-update',
				'fulfillment-problem'   => 'dashicons-warning',
				'fulfillment-cancelled' => 'dashicons-dismiss',
			);
			return array(
				'title'    => $titles[ $key ],
				'subtitle' => implode( ' · ', $parts ),
				'icon'     => $icons[ $key ],
			);
		}

		/**
		 * Filter wp_easycart_admin_order_timeline_labels ( the orders list's timeline ).
		 *
		 * @param array $labels order_log_key => label.
		 * @return array
		 */
		public static function timeline_labels( $labels ) {
			$labels = is_array( $labels ) ? $labels : array();
			foreach ( self::log_titles( '' ) as $key => $label ) {
				if ( ! isset( $labels[ $key ] ) ) {
					$labels[ $key ] = $label;
				}
			}
			return $labels;
		}

		/**
		 * Timeline titles for the fulfillment log keys.
		 *
		 * @param string $provider Partner name, or ''.
		 * @return array
		 */
		private static function log_titles( $provider ) {
			if ( '' === $provider ) {
				return array(
					'fulfillment-released'  => __( 'Ready for fulfillment partners', 'wp-easycart' ),
					'fulfillment-sent'      => __( 'Sent to the fulfillment partner', 'wp-easycart' ),
					'fulfillment-update'    => __( 'Fulfillment partner update', 'wp-easycart' ),
					'fulfillment-problem'   => __( 'Fulfillment partner problem', 'wp-easycart' ),
					'fulfillment-cancelled' => __( 'Fulfillment partners told the order ended', 'wp-easycart' ),
				);
			}
			return array(
				'fulfillment-released'  => __( 'Ready for fulfillment partners', 'wp-easycart' ),
				/* translators: %s: fulfillment partner, e.g. Printful. */
				'fulfillment-sent'      => sprintf( __( 'Sent to %s', 'wp-easycart' ), $provider ),
				/* translators: %s: fulfillment partner, e.g. Printful. */
				'fulfillment-update'    => sprintf( __( '%s update', 'wp-easycart' ), $provider ),
				/* translators: %s: fulfillment partner, e.g. Printful. */
				'fulfillment-problem'   => sprintf( __( '%s reported a problem', 'wp-easycart' ), $provider ),
				/* translators: %s: fulfillment partner, e.g. Printful. */
				'fulfillment-cancelled' => sprintf( __( 'Cancelled with %s', 'wp-easycart' ), $provider ),
			);
		}
	}

	wp_easycart_fulfillment::init();

endif;

if ( ! function_exists( 'wp_easycart_order_log' ) ) {
	/**
	 * Write an order timeline entry ( ec_order_log + ec_order_log_meta ), as WP EasyCart's own entries are written.
	 *
	 * @since 6.0.2
	 * @param int    $order_id Order.
	 * @param string $key      order_log_key ( e.g. fulfillment-sent ).
	 * @param array  $meta     key => value ( arrays and objects are stored as JSON; null values are skipped ).
	 * @return int Log ID, 0 on failure.
	 */
	function wp_easycart_order_log( $order_id, $key, $meta = array() ) {
		global $wpdb;
		$key = substr( preg_replace( '/[^A-Za-z0-9_.:-]/', '', (string) $key ), 0, 100 );
		if ( (int) $order_id <= 0 || '' === $key ) {
			return 0;
		}
		$wpdb->insert(
			'ec_order_log',
			array(
				'order_id'      => (int) $order_id,
				'order_log_key' => $key,
			),
			array( '%d', '%s' )
		);
		$log_id = (int) $wpdb->insert_id;
		if ( $log_id <= 0 ) {
			return 0;
		}
		foreach ( is_array( $meta ) ? $meta : array() as $meta_key => $meta_value ) {
			if ( null === $meta_value ) {
				continue;
			}
			if ( is_bool( $meta_value ) ) {
				$meta_value = $meta_value ? '1' : '0';
			} elseif ( is_array( $meta_value ) || is_object( $meta_value ) ) {
				$meta_value = (string) wp_json_encode( $meta_value );
			}
			$wpdb->insert(
				'ec_order_log_meta',
				array(
					'order_log_id'         => $log_id,
					'order_id'             => (int) $order_id,
					'order_log_meta_key'   => (string) $meta_key,
					'order_log_meta_value' => (string) $meta_value,
				),
				array( '%d', '%d', '%s', '%s' )
			);
		}
		return $log_id;
	}
}

if ( ! function_exists( 'wp_easycart_order_note' ) ) {
	/**
	 * Leave a staff comment on an order ( the order screen's comments; order log key staff-comment with meta comment and
	 * author, as WP EasyCart PRO writes them ).
	 *
	 * @since 6.0.2
	 * @param int    $order_id Order.
	 * @param string $text     Comment.
	 * @param string $author   Who wrote it ( '' = WP EasyCart ).
	 * @return int Log ID, 0 on failure.
	 */
	function wp_easycart_order_note( $order_id, $text, $author = '' ) {
		$text = trim( (string) $text );
		if ( '' === $text ) {
			return 0;
		}
		return wp_easycart_order_log(
			$order_id,
			'staff-comment',
			array(
				'comment' => $text,
				'author'  => '' !== trim( (string) $author ) ? trim( (string) $author ) : 'WP EasyCart',
			)
		);
	}
}
