<?php
/**
 * The order screen ( 6.0.2 redesign ): what the template works out before it draws the page, and the order screen's own
 * AJAX actions.
 *
 * - Money in the store's currency format ( money(), money_config() for the page script ).
 * - The next step the header offers ( next_step(), filter wp_easycart_ecv2_order_next_steps ).
 * - What has to be read before anyone acts on the order ( flags(): a payment on hold, a possible card test ).
 * - Each line's picture and where each line stands in the shipping ( line_image(), line_states() ).
 * - The pinned note, the one fulfil flow for orders without packages, and address / contact corrections, which are free
 *   from 6.0.2 ( the action names are the ones WP EasyCart PRO 6.0.1 and older posted to, so either plugin's script saves
 *   through them ).
 *
 * @package WP EasyCart
 * @since 6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'ecv2_order_screen_guard' ) ) :
	/**
	 * Guard for the order screen's AJAX actions: the Orders permission and the order screen's nonce ( printed as
	 * #wp_easycart_order_details_nonce, posted as wp_easycart_nonce ). Answers a JSON error ( 403 ) and ends the request
	 * otherwise.
	 *
	 * @since 6.0.2
	 */
	function ecv2_order_screen_guard() {
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_manager' ) && ! current_user_can( 'wpec_orders' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to change orders.', 'wp-easycart' ) ), 403 );
		}
		if ( ! check_ajax_referer( 'wp-easycart-order-details', 'wp_easycart_nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'This page has expired. Reload it and try again.', 'wp-easycart' ) ), 403 );
		}
	}
endif;

if ( ! class_exists( 'wp_easycart_admin_order_screen' ) ) :

	/**
	 * Order screen helpers and AJAX actions.
	 *
	 * @since 6.0.2
	 */
	class wp_easycart_admin_order_screen {

		/** The database update that added ec_orderdetail.packed_quantity ( the Packed ticks ). */
		const PACK_DB_VERSION = 118;

		/**
		 * The store's packages as the Fulfill window lists them, per order and request ( label_rows() ).
		 *
		 * @var array
		 */
		private static $rows_cache = array();

		/**
		 * The label services an extension offers for an order, per order and request ( label_services() ).
		 *
		 * @since 6.0.3
		 * @var array
		 */
		private static $services_cache = array();

		/**
		 * Hooks.
		 */
		public static function init() {
			add_action( 'wp_ajax_ecv2_order_pinned_note', array( __CLASS__, 'ajax_pinned_note' ) );
			add_action( 'wp_ajax_ecv2_order_screen_fulfill', array( __CLASS__, 'ajax_fulfill' ) );
			add_action( 'wp_ajax_ecv2_order_screen_find', array( __CLASS__, 'ajax_find' ) );
			add_action( 'wp_ajax_ecv2_order_screen_pack', array( __CLASS__, 'ajax_pack' ) );
			/* 6.0.2 bug round 6: the page drawn again after the packages changed or an email went out, and Mark delivered. */
			add_action( 'wp_ajax_ecv2_order_screen_refresh', array( __CLASS__, 'ajax_refresh' ) );
			add_action( 'wp_ajax_ecv2_order_screen_delivered', array( __CLASS__, 'ajax_delivered' ) );
			/* 6.0.2 bug round 14: a paid order whose stock was never taken ( the Stock not taken notice ). */
			add_action( 'wp_ajax_ecv2_order_screen_stock', array( __CLASS__, 'ajax_stock' ) );
			add_filter( 'wp_easycart_admin_order_history_entry', array( __CLASS__, 'history_entry' ), 5, 3 );
			/* Address and contact corrections ( free from 6.0.2 ). These run before WP EasyCart PRO's handlers for the same
			   actions ( PRO registers later ), and do what they did, so an older PRO's script saves through them too. */
			add_action( 'wp_ajax_ec_admin_ajax_save_order_billing_address', array( __CLASS__, 'ajax_save_billing' ) );
			add_action( 'wp_ajax_ec_admin_ajax_save_order_shipping_address', array( __CLASS__, 'ajax_save_shipping' ) );
			add_action( 'wp_ajax_ec_admin_ajax_save_order_management_details', array( __CLASS__, 'ajax_save_contact' ) );
		}

		/* ------------------------------------------------------------------ */
		/* Money                                                              */
		/* ------------------------------------------------------------------ */

		/**
		 * An amount in the store's currency format. Orders are kept in the store's own currency, so nothing is converted
		 * ( a shopper's display currency cookie can reach admin AJAX requests ).
		 *
		 * @param float $amount Amount.
		 * @return string
		 */
		public static function money( $amount ) {
			if ( isset( $GLOBALS['currency'] ) && is_object( $GLOBALS['currency'] ) && method_exists( $GLOBALS['currency'], 'get_currency_display' ) ) {
				return (string) $GLOBALS['currency']->get_currency_display( (float) $amount, false );
			}
			return number_format( (float) $amount, 2 );
		}

		/**
		 * The store's currency format for the page script ( ecodv2_money() ), so amounts written in place look like the page.
		 *
		 * @return array
		 */
		public static function money_config() {
			$config = array(
				'symbol'    => '$',
				'before'    => 1,
				'negBefore' => 1,
				'decimals'  => 2,
				'dec'       => '.',
				'group'     => ',',
				'code'      => '',
			);
			if ( class_exists( 'ec_currency' ) && isset( ec_currency::$static_symbol ) ) {
				$config['symbol']    = (string) ec_currency::$static_symbol;
				$config['before']    = ec_currency::$static_symbol_location ? 1 : 0;
				$config['negBefore'] = ec_currency::$static_negative_location ? 1 : 0;
				$config['decimals']  = max( 0, (int) ec_currency::$static_decimal_length );
				$config['dec']       = (string) ec_currency::$static_decimal_symbol;
				$config['group']     = (string) ec_currency::$static_grouping_symbol;
				$config['code']      = ec_currency::$static_show_currency_code ? (string) ec_currency::$static_currency_code : '';
			}
			return $config;
		}

		/**
		 * An amount as the page prints it: the formatted text beside a hidden raw number with the id the order screen's
		 * scripts ( and WP EasyCart PRO's ) write to after a save. The page script keeps the two in step.
		 *
		 * @param string $id     Element id of the raw number.
		 * @param float  $amount Amount.
		 * @param string $sign   '' or '-' ( printed before the amount, e.g. a discount ).
		 * @return string
		 */
		public static function amount_html( $id, $amount, $sign = '' ) {
			$decimals = class_exists( 'ec_currency' ) && isset( ec_currency::$static_decimal_length ) ? max( 0, (int) ec_currency::$static_decimal_length ) : 2;
			return '<span class="ecodv2-amt" data-amt-for="' . esc_attr( $id ) . '"' . ( '' !== $sign ? ' data-sign="' . esc_attr( $sign ) . '"' : '' ) . '>' . esc_html( ( '' !== $sign ? '−' : '' ) . self::money( abs( (float) $amount ) ) ) . '</span>'
				. '<span class="ecodv2-amt-raw" id="' . esc_attr( $id ) . '" hidden>' . esc_html( number_format( (float) $amount, max( 2, $decimals ), '.', '' ) ) . '</span>';
		}

		/* ------------------------------------------------------------------ */
		/* Where the order stands                                             */
		/* ------------------------------------------------------------------ */

		/**
		 * Where the order stands on shipping: fulfilled | partial | pickup | unfulfilled | digital ( nothing to ship ) | none
		 * ( not paid yet ). An order with packages or fulfillment partner lines reads what is left to ship
		 * ( wp_easycart_fulfillment::state() ). Filter wp_easycart_ecv2_order_fulfillment_state.
		 *
		 * 6.0.2 bug round 14: an order the customer collects ( wp_easycart_order_is_pickup(): a restaurant order, preorder items
		 * or Free Local Pickup ) is unfulfilled until it is picked up, so it gets Mark picked up and never the shipping flow;
		 * pickup is left for an order in Ready for Pickup that is none of them.
		 *
		 * @param object $order Order row ( with is_approved ).
		 * @return array state, local_pickup ( the customer collects it: any pickup, not only Free Local Pickup ), pickup_kind
		 *               ( restaurant | preorder | local | '' ), store_open ( the store still has items to send ), partner
		 *               ( "Printful is making and shipping 2 items." ), fstate ( wp_easycart_fulfillment::state() or null ).
		 */
		public static function fulfillment( $order ) {
			$status       = (int) $order->orderstatus_id;
			$tracking     = trim( (string) $order->tracking_number );
			$pickup_kind  = function_exists( 'wp_easycart_order_pickup_kind' ) ? wp_easycart_order_pickup_kind( $order ) : ( ( function_exists( 'wp_easycart_order_is_local_pickup' ) && wp_easycart_order_is_local_pickup( $order ) ) ? 'local' : '' );
			$local_pickup = '' !== $pickup_kind;
			if ( 18 === $status || 2 === $status || '' !== $tracking || ( class_exists( 'wp_easycart_shipments' ) && wp_easycart_shipments::is_fulfilled_status( $status ) ) ) {
				$state = 'fulfilled';
			} elseif ( 11 === $status && ! $local_pickup ) {
				$state = 'pickup';
			} elseif ( ! empty( $order->is_approved ) && ! in_array( $status, array( 16, 19 ), true ) ) {
				/* Nothing to ship ( downloads, gift cards, subscriptions / services, shipping disabled ) is fulfilled on payment; an
				   order the customer collects waits for them ( restaurant items are not shippable ). */
				if ( ! $local_pickup && class_exists( 'wp_easycart_admin_order_table' ) && method_exists( 'wp_easycart_admin_order_table', 'requires_shipping' ) && ! wp_easycart_admin_order_table::requires_shipping( $order->order_id ) ) {
					$state = 'digital';
				} else {
					$state = 'unfulfilled';
				}
			} else {
				$state = 'none';
			}
			$fstate     = null;
			$store_open = true;
			$partner    = '';
			if ( ! $local_pickup && ! empty( $order->is_approved ) && ! in_array( $status, array( 16, 18, 19 ), true ) && in_array( $state, array( 'fulfilled', 'unfulfilled', 'digital' ), true ) && class_exists( 'wp_easycart_fulfillment' ) && wp_easycart_fulfillment::ready() ) {
				$fstate = wp_easycart_fulfillment::state( (int) $order->order_id, $order );
				if ( empty( $fstate['legacy'] ) && 'none' !== $fstate['state'] ) {
					$state      = $fstate['state'];
					$store_open = wp_easycart_fulfillment::store_open( $fstate );
					$bits       = array();
					foreach ( wp_easycart_fulfillment::open_providers( $fstate ) as $slug => $label ) {
						$left = max( 0, (int) $fstate['providers'][ $slug ]['units'] - (int) $fstate['providers'][ $slug ]['shipped'] );
						/* translators: 1: fulfillment partner, e.g. Printful, 2: number of items it still has to ship. */
						$bits[] = sprintf( _n( '%1$s is making and shipping %2$d item.', '%1$s is making and shipping %2$d items.', $left, 'wp-easycart' ), $label, $left );
					}
					$partner = implode( ' ', $bits );
				} else {
					$fstate = null;
				}
			}
			return array(
				'state'        => (string) apply_filters( 'wp_easycart_ecv2_order_fulfillment_state', $state, $order ),
				'local_pickup' => $local_pickup,
				'pickup_kind'  => $pickup_kind,
				'store_open'   => $store_open,
				'partner'      => $partner,
				'fstate'       => $fstate,
			);
		}

		/**
		 * The payment badge in the header: Paid, Balance due, Refund due, Refunded, Partial refund, Canceled, Failed or
		 * Processing.
		 *
		 * @param object     $order   Order row ( with is_approved ).
		 * @param array|null $summary wp_easycart_order_payments::summary() or null.
		 * @return array class ( payment-paid | payment-processing | payment-bad | payment-neutral ), label.
		 */
		public static function payment_badge( $order, $summary = null ) {
			$status = (int) $order->orderstatus_id;
			if ( 17 === $status ) {
				$badge = array( 'class' => 'payment-neutral', 'label' => __( 'Partial Refund', 'wp-easycart' ) );
			} elseif ( 16 === $status ) {
				$badge = array( 'class' => 'payment-bad', 'label' => __( 'Refunded', 'wp-easycart' ) );
			} elseif ( ! empty( $order->is_approved ) ) {
				$badge = array( 'class' => 'payment-paid', 'label' => __( 'Paid', 'wp-easycart' ) );
			} elseif ( 19 === $status ) {
				$badge = array( 'class' => 'payment-bad', 'label' => __( 'Canceled', 'wp-easycart' ) );
			} elseif ( 7 === $status || 9 === $status ) {
				$badge = array( 'class' => 'payment-bad', 'label' => __( 'Failed', 'wp-easycart' ) );
			} else {
				$badge = array( 'class' => 'payment-processing', 'label' => __( 'Processing', 'wp-easycart' ) );
			}
			if ( is_array( $summary ) && ! empty( $summary['recorded'] ) && 'partial' === $summary['state'] ) {
				$badge = array( 'class' => 'payment-processing', 'label' => __( 'Balance due', 'wp-easycart' ) );
			} elseif ( is_array( $summary ) && ! empty( $summary['recorded'] ) && 'overpaid' === $summary['state'] ) {
				$badge = array( 'class' => 'payment-neutral', 'label' => __( 'Refund due', 'wp-easycart' ) );
			}
			return $badge;
		}

		/**
		 * What the order screen redraws after a status change: the badge, the balance and the shipping state.
		 *
		 * @param object $order Order row ( with is_approved and status_label ).
		 * @return array
		 */
		public static function status_reply( $order ) {
			global $wpdb;
			$ctx     = self::context( $order );
			$summary = $ctx['summary'];
			/* 6.0.2 bug round 6: the status's name and colour, for a status the page's menu does not list yet ( Order Delivered,
			   made on first use ). */
			$status = $wpdb->get_row( $wpdb->prepare( 'SELECT order_status, color_code FROM ec_orderstatus WHERE status_id = %d', (int) $order->orderstatus_id ) );
			return array(
				'status_id'    => (int) $order->orderstatus_id,
				'status_label' => $status ? wp_strip_all_tags( wp_unslash( (string) $status->order_status ) ) : '',
				'status_color' => ( $status && sanitize_hex_color( (string) $status->color_code ) ) ? (string) $status->color_code : '',
				'badge'        => self::payment_badge( $order, $summary ),
				'fulfillment'  => $ctx['fulfillment']['state'],
				'paid'         => ( is_array( $summary ) && ! empty( $summary['recorded'] ) ) ? round( (float) $summary['paid'], 2 ) : null,
				'approved'     => ! empty( $order->is_approved ),
				/* The steps and the next step panel as they read now ( the page puts them in place ). */
				'steps_html'   => self::steps_html( self::steps( $order, $ctx ) ),
				'next_html'    => self::next_panel_html( $order, $ctx ),
				/* 6.0.2 bug round 14: and the notices above them ( a status change can end a hold; Take stock now ends its own ). */
				'flags_html'   => self::flags_html( $ctx['flags'] ),
				'flags_count'  => count( $ctx['flags'] ),
			);
		}

		/**
		 * Everything on the order screen that follows the order's packages, drawn as it reads now: status_reply() ( the
		 * steps, the next step panel, the badge ), the Fulfill window's tracking step ( label_tracking_html() ) and each line's
		 * package chip. The page asks for it after the Packages card is drawn again ( Edit packages, Pack again, a line changed,
		 * a label extension ) and after an email is sent, so nothing needs a reload ( bug round 6 ).
		 *
		 * @since 6.0.2
		 * @param object $order Order row ( with is_approved ).
		 * @return array status_reply() plus label_html, label_nav ( the tracking step's name ), label_open ( packages still
		 *               waiting for tracking ), label_nothing ( packages, none waiting: no Save ), line_packages ( orderdetail_id
		 *               => the chip's words, '' for none ).
		 */
		public static function refresh_reply( $order ) {
			global $wpdb;
			self::forget_rows( (int) $order->order_id );
			$reply = self::status_reply( $order );
			$rows  = self::label_rows( $order );
			$open  = 0;
			foreach ( $rows as $row ) {
				if ( empty( $row['done'] ) ) {
					++$open;
				}
			}
			$reply['label_html']    = self::label_tracking_html( $order );
			$reply['label_nav']     = count( $rows ) > 1 ? __( 'Tracking for each package', 'wp-easycart' ) : __( 'Tracking and email', 'wp-easycart' );
			$reply['label_open']    = $open;
			$reply['label_nothing'] = ( $rows && 0 === $open );
			$reply['line_packages'] = array();
			$ful                    = self::fulfillment( $order );
			if ( 'pickup' !== $ful['state'] && ! $ful['local_pickup'] ) {
				$lines = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ec_orderdetail WHERE order_id = %d ORDER BY orderdetail_id', (int) $order->order_id ) );
				foreach ( self::line_states( $order, $lines ) as $detail_id => $state ) {
					$reply['line_packages'][ (int) $detail_id ] = self::line_packages_text( $state );
				}
			}
			return $reply;
		}

		/**
		 * The package chip on an order line ( "Package 2", "Packages 1, 2" ), or '' when it has none ( order-item.php prints the
		 * same ).
		 *
		 * @since 6.0.2
		 * @param array|null $state line_states() entry.
		 * @return string
		 */
		public static function line_packages_text( $state ) {
			if ( ! is_array( $state ) || empty( $state['quantity'] ) || empty( $state['packages'] ) || ! in_array( $state['group'], array( 'ship', 'shipped', 'delivered' ), true ) ) {
				return '';
			}
			/* translators: %s: package numbers, e.g. 1, 2. */
			return sprintf( _n( 'Package %s', 'Packages %s', count( $state['packages'] ), 'wp-easycart' ), implode( ', ', $state['packages'] ) );
		}

		/**
		 * The Fulfill window's tracking step ( inside #ecodv2_label_rows ): a tracking row for each of the store's packages,
		 * or one tracking field when the order has none, then Mark the order as shipped and Email the customer. Printed with
		 * the page and drawn again after the packages change ( refresh_reply() ), so every package's Add tracking opens the
		 * rows as they are now.
		 *
		 * @since 6.0.2
		 * @param object $order Order row.
		 * @return string
		 */
		public static function label_tracking_html( $order ) {
			$order_id = (int) $order->order_id;
			$rows     = self::label_rows( $order );
			$carriers = self::label_carriers( $order );
			$options  = $carriers['options'];
			$open     = 0;
			foreach ( $rows as $row ) {
				if ( empty( $row['done'] ) ) {
					++$open;
				}
			}
			$html = '';
			if ( $rows ) {
				$html .= '<div class="ecodv2-label-pkgs" id="ecodv2_label_pkgs" data-order-id="' . esc_attr( (string) $order_id ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'wp-easycart-ecv2-order-packages' ) ) . '" data-need="' . esc_attr__( 'Enter a tracking number for at least one package.', 'wp-easycart' ) . '" data-fail="' . esc_attr__( 'The tracking numbers could not be saved. Reload the order and try again.', 'wp-easycart' ) . '">';
				if ( $open > 1 ) {
					$html .= '<label class="ecodv2-label-all"><span>' . esc_html__( 'Carrier for every package', 'wp-easycart' ) . '</span> <select id="ecodv2_label_carrier_all" data-wpec-carrier="1">' . $options . '</select></label>';
				}
				$html .= '<ol class="ecodv2-label-pkg-list">';
				foreach ( $rows as $row ) {
					$number = (int) $row['number'];
					$html  .= '<li class="ecodv2-label-pkg' . ( ! empty( $row['done'] ) ? ' is-done' : '' ) . '" data-index="' . esc_attr( (string) (int) $row['index'] ) . '" data-shipment-id="' . esc_attr( (string) (int) $row['shipment_id'] ) . '">';
					/* translators: %d: package number. */
					$html .= '<div class="ecodv2-label-pkg-head"><strong>' . esc_html( sprintf( __( 'Package %d', 'wp-easycart' ), $number ) ) . '</strong>';
					if ( '' !== (string) $row['meta'] ) {
						$html .= ' <span class="ecodv2-label-pkg-meta">' . esc_html( $row['meta'] ) . '</span>';
					}
					$html .= '</div>';
					if ( '' !== (string) $row['items'] ) {
						$html .= '<div class="ecodv2-label-pkg-items">' . esc_html( $row['items'] ) . '</div>';
					}
					if ( ! empty( $row['done'] ) ) {
						$html .= '<div class="ecodv2-label-pkg-done"><span class="dashicons dashicons-yes" aria-hidden="true"></span> ' . esc_html( '' !== trim( (string) $row['tracking'] ) ? trim( $row['carrier'] . ' ' . $row['tracking'] ) : __( 'Has a label', 'wp-easycart' ) ) . '</div>';
					} else {
						$html .= '<div class="ecodv2-label-pkg-row">'
							/* translators: %d: package number. */
							. '<label class="screen-reader-text" for="ecodv2_lp_carrier_' . esc_attr( (string) $number ) . '">' . esc_html( sprintf( __( 'Carrier for package %d', 'wp-easycart' ), $number ) ) . '</label>'
							. '<select class="ecodv2-label-pkg-carrier" id="ecodv2_lp_carrier_' . esc_attr( (string) $number ) . '" data-wpec-carrier="1">' . $options . '</select>'
							/* translators: %d: package number. */
							. '<label class="screen-reader-text" for="ecodv2_lp_tracking_' . esc_attr( (string) $number ) . '">' . esc_html( sprintf( __( 'Tracking number for package %d', 'wp-easycart' ), $number ) ) . '</label>'
							. '<input type="text" class="ecodv2-label-pkg-tracking" id="ecodv2_lp_tracking_' . esc_attr( (string) $number ) . '" placeholder="' . esc_attr__( 'Paste tracking number…', 'wp-easycart' ) . '" autocomplete="off" spellcheck="false" />'
							. '</div>';
					}
					$html .= '</li>';
				}
				$html .= '</ol></div>';
			} else {
				$html .= '<div class="ecodv2-label-after" id="ecodv2_label_single" data-order-id="' . esc_attr( (string) $order_id ) . '">'
					. '<span class="ecodv2-eyebrow">' . esc_html__( 'Tracking', 'wp-easycart' ) . '</span>'
					. '<div class="ecodv2-label-after-row">'
					. '<label class="screen-reader-text" for="ecodv2_label_carrier_sel">' . esc_html__( 'Carrier', 'wp-easycart' ) . '</label>'
					. '<select id="ecodv2_label_carrier_sel" data-wpec-carrier="1">' . $options . '</select>'
					. '<label class="screen-reader-text" for="ecodv2_label_tracking">' . esc_html__( 'Tracking number', 'wp-easycart' ) . '</label>'
					. '<input type="text" id="ecodv2_label_tracking" placeholder="' . esc_attr__( 'Paste tracking number…', 'wp-easycart' ) . '" value="' . esc_attr( trim( (string) $order->tracking_number ) ) . '" autocomplete="off" spellcheck="false" />'
					. '</div></div>';
			}
			$many  = count( $rows ) > 1;
			$html .= '<div class="ecodv2-label-opts"' . ( ( $rows && 0 === $open ) ? ' hidden' : '' ) . '>'
				. '<label class="ecodv2-label-email-opt"><input type="checkbox" id="ecodv2_label_mark_shipped" checked="checked" /> ' . esc_html__( 'Mark the order as shipped', 'wp-easycart' ) . ' <span class="ecodv2-muted">' . esc_html( $many ? __( '( once every package has tracking )', 'wp-easycart' ) : '' ) . '</span></label>'
				. '<label class="ecodv2-label-email-opt"><input type="checkbox" id="ecodv2_label_send_email" checked="checked" /> ' . esc_html( $many ? __( 'Email the customer: one shipped email listing every package and its tracking', 'wp-easycart' ) : __( 'Email the customer the shipped email', 'wp-easycart' ) ) . '</label>'
				. '</div>';
			return $html;
		}

		/* ------------------------------------------------------------------ */
		/* What the header offers next                                         */
		/* ------------------------------------------------------------------ */

		/**
		 * The one action the header offers first, from where the order stands. WP EasyCart adds Review payment ( a hold or a
		 * possible card test ) and Fulfill items; WP EasyCart PRO adds its own ( send the invoice, collect a balance, refund
		 * an overpayment, mark picked up ) through filter wp_easycart_ecv2_order_next_steps. The lowest priority wins.
		 *
		 * @param object $order   Order row.
		 * @param array  $context fulfillment ( state ), payment ( wp_easycart_order_payments state or '' ), local_pickup ( the
		 *                        customer collects it ), pickup_kind ( restaurant | preorder | local | '' ), store_open ( the
		 *                        store still ships items ), flags ( flags() ).
		 * @return array|null key, label, onclick, icon; null when nothing is next ( fulfilled, refunded, cancelled ).
		 */
		public static function next_step( $order, $context ) {
			$context = wp_parse_args(
				$context,
				array(
					'fulfillment'  => 'none',
					'payment'      => '',
					'local_pickup' => false,
					'pickup_kind'  => '',
					'store_open'   => true,
					'flags'        => array(),
				)
			);
			$status = (int) $order->orderstatus_id;
			$steps  = array();
			/* 6.0.2 bug round 14: only a notice about the payment asks for a review first ( Stock not taken has its own buttons ). */
			if ( self::review_flags( $context['flags'] ) ) {
				$steps[] = array(
					'key'      => 'review',
					'priority' => 10,
					'label'    => __( 'Review payment', 'wp-easycart' ),
					'onclick'  => 'ecodv2_scroll_to( \'ecodv2_attention\' ); return false;',
					'icon'     => 'shield',
				);
			}
			$closed = in_array( $status, array( 16, 19 ), true );
			/* The customer collects it ( free from 6.0.2; WP EasyCart PRO 6.0.1 added the same step for local pickup ). 6.0.2 bug
			   round 14: restaurant orders and preorders too, and an order in Ready for Pickup ( state pickup ). */
			if ( ! $closed && ! empty( $order->is_approved ) && ( ( $context['local_pickup'] && 'unfulfilled' === $context['fulfillment'] ) || 'pickup' === $context['fulfillment'] ) ) {
				$pickup_texts = array(
					'restaurant' => __( 'Restaurant order. Mark it picked up once they have it.', 'wp-easycart' ),
					'preorder'   => __( 'Preorder pickup. Mark it picked up once they have it.', 'wp-easycart' ),
				);
				$steps[]      = array(
					'key'      => 'pickup',
					'priority' => 45,
					'label'    => __( 'Mark picked up', 'wp-easycart' ),
					'title'    => __( 'Waiting for the customer to collect it', 'wp-easycart' ),
					'text'     => isset( $pickup_texts[ $context['pickup_kind'] ] ) ? $pickup_texts[ $context['pickup_kind'] ] : __( 'Local pickup. Mark it picked up once they have it.', 'wp-easycart' ),
					'onclick'  => 'ecodv2_mark_picked_up(); return false;',
					'icon'     => 'store',
				);
			}
			if ( ! $closed && ! $context['local_pickup'] && $context['store_open'] && in_array( $context['fulfillment'], array( 'unfulfilled', 'partial' ), true ) && ! empty( $order->is_approved ) ) {
				$steps[] = array(
					'key'      => 'fulfill',
					'priority' => 50,
					'label'    => ( 'partial' === $context['fulfillment'] ) ? __( 'Fulfill remaining', 'wp-easycart' ) : __( 'Fulfill items', 'wp-easycart' ),
					'onclick'  => 'ecodv2_fulfill_open(); return false;',
					'icon'     => 'airplane',
				);
			}
			/**
			 * The actions the order screen's header can offer first. The one with the lowest priority is shown.
			 *
			 * @since 6.0.2
			 * @param array  $steps   Each key, priority ( 10 review payment, 20 refund due, 30 send invoice, 40 collect a
			 *                        balance, 45 mark picked up, 50 fulfill ), label, onclick, icon ( a dashicon name ).
			 * @param object $order   Order row.
			 * @param array  $context fulfillment, payment, local_pickup, pickup_kind, store_open, flags.
			 */
			$steps = apply_filters( 'wp_easycart_ecv2_order_next_steps', $steps, $order, $context );
			$best  = null;
			foreach ( (array) $steps as $step ) {
				if ( ! is_array( $step ) || empty( $step['label'] ) || empty( $step['onclick'] ) ) {
					continue;
				}
				$step = wp_parse_args(
					$step,
					array(
						'key'      => '',
						'priority' => 100,
						'icon'     => '',
					)
				);
				if ( null === $best || (int) $step['priority'] < (int) $best['priority'] ) {
					$best = $step;
				}
			}
			return $best;
		}

		/**
		 * What must be read before anyone ships or refunds the order: a payment held at checkout ( a fulfillment partner could
		 * not price shipping ), a payment made during a card-testing attack, and ( 6.0.2 bug round 14 ) a paid order whose stock
		 * was never taken. Each has a kind, a title and the text to show; optionally tone ( red, the default, or yellow ), icon
		 * ( a dashicon name ), review ( false: the next step is not Review payment ) and actions ( label, onclick, primary ).
		 *
		 * @param object $order Order row.
		 * @return array
		 */
		public static function flags( $order ) {
			global $wpdb;
			$order_id = (int) $order->order_id;
			$keys     = $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT order_log_key FROM ec_order_log WHERE order_id = %d AND order_log_key IN ( %s, %s )', $order_id, 'order-payment-hold', 'possible-card-test' ) );
			$flags    = array();
			if ( in_array( 'order-payment-hold', (array) $keys, true ) && 12 === (int) $order->orderstatus_id ) {
				$note    = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT meta.order_log_meta_value FROM ec_order_log_meta AS meta INNER JOIN ec_order_log AS log ON log.order_log_id = meta.order_log_id WHERE log.order_id = %d AND log.order_log_key = %s AND meta.order_log_meta_key = %s ORDER BY log.order_log_id DESC LIMIT 1', $order_id, 'staff-comment', 'comment' ) );
				$flags[] = array(
					'kind'  => 'hold',
					'title' => __( 'Payment on hold', 'wp-easycart' ),
					'text'  => '' !== $note ? $note : __( 'This order was paid but put on hold at checkout. Check it before you ship it or mark it paid.', 'wp-easycart' ),
				);
			}
			if ( in_array( 'possible-card-test', (array) $keys, true ) && ! in_array( (int) $order->orderstatus_id, array( 16, 19 ), true ) ) {
				$flags[] = array(
					'kind'  => 'card_test',
					'title' => __( 'Possible card test', 'wp-easycart' ),
					'text'  => __( 'This payment went through during a card-testing attack, after declined payments from the same shopper. If you do not recognise the customer, refund it before you ship anything.', 'wp-easycart' ),
				);
			}
			/* 6.0.2 bug round 14: a manual payment order marked paid before 6.0.2 kept its stock ( 6.0.1 took it only at checkout,
			   and checkout leaves manual payments until they are paid ). Only the store knows whether it corrected the stock by
			   hand since, so the order screen asks rather than a sweep taking it. */
			$owed = self::stock_owed( $order );
			if ( $owed['units'] > 0 ) {
				$flags[] = array(
					'kind'    => 'stock',
					'title'   => __( 'Stock not taken', 'wp-easycart' ),
					/* translators: %d: number of items. */
					'text'    => sprintf( _n( 'Paid, but stock was never taken for %d item. Take it now, or choose Already corrected if you changed the stock by hand.', 'Paid, but stock was never taken for %d items. Take it now, or choose Already corrected if you changed the stock by hand.', $owed['units'], 'wp-easycart' ), $owed['units'] ),
					'tone'    => 'yellow',
					'icon'    => 'archive',
					'review'  => false,
					'actions' => array(
						array(
							'label'   => __( 'Take stock now', 'wp-easycart' ),
							'onclick' => "ecodv2_stock_fix( this, 'take' ); return false;",
							'primary' => true,
						),
						array(
							'label'   => __( 'Already corrected', 'wp-easycart' ),
							'onclick' => "ecodv2_stock_fix( this, 'mark' ); return false;",
						),
					),
				);
			}
			/**
			 * Notices at the top of the order screen, before the notes ( red ).
			 *
			 * @since 6.0.2
			 * @param array  $flags Each kind, title, text; optionally tone ( red | yellow ), icon, review ( false: not a payment
			 *                      to review ) and actions ( each label, onclick, primary ).
			 * @param object $order Order row.
			 */
			return (array) apply_filters( 'wp_easycart_ecv2_order_flags', $flags, $order );
		}

		/**
		 * The notices that ask for the payment to be reviewed before anything else ( every notice but those with review false ).
		 *
		 * @since 6.0.2 bug round 14
		 * @param array $flags flags().
		 * @return array
		 */
		public static function review_flags( $flags ) {
			$out = array();
			foreach ( (array) $flags as $flag ) {
				if ( is_array( $flag ) && ( ! isset( $flag['review'] ) || false !== $flag['review'] ) ) {
					$out[] = $flag;
				}
			}
			return $out;
		}

		/**
		 * The notices as the page prints them inside #ecodv2_attention ( with the page, and again from a status change's answer ).
		 *
		 * @since 6.0.2 bug round 14
		 * @param array $flags flags().
		 * @return string
		 */
		public static function flags_html( $flags ) {
			$html = '';
			foreach ( (array) $flags as $flag ) {
				if ( ! is_array( $flag ) || empty( $flag['title'] ) ) {
					continue;
				}
				$tone    = ( isset( $flag['tone'] ) && in_array( $flag['tone'], array( 'red', 'yellow', 'blue' ), true ) ) ? $flag['tone'] : 'red';
				$icon    = ( isset( $flag['icon'] ) && '' !== sanitize_key( $flag['icon'] ) ) ? sanitize_key( $flag['icon'] ) : 'shield';
				$buttons = '';
				foreach ( ( isset( $flag['actions'] ) && is_array( $flag['actions'] ) ) ? $flag['actions'] : array() as $action ) {
					if ( is_array( $action ) && ! empty( $action['label'] ) && ! empty( $action['onclick'] ) ) {
						$buttons .= '<button type="button" class="ecv2-btn ecv2-btn-sm' . ( ! empty( $action['primary'] ) ? ' ecv2-btn-primary' : '' ) . '" onclick="' . esc_attr( $action['onclick'] ) . '">' . esc_html( $action['label'] ) . '</button>';
					}
				}
				$html .= '<div class="ecodv2-callout ecodv2-callout-' . esc_attr( $tone ) . '" data-flag="' . esc_attr( isset( $flag['kind'] ) ? (string) $flag['kind'] : '' ) . '" role="note">'
					. '<span class="dashicons dashicons-' . esc_attr( $icon ) . '" aria-hidden="true"></span>'
					. '<div class="ecodv2-callout-body"><strong>' . esc_html( $flag['title'] ) . '</strong><span>' . esc_html( isset( $flag['text'] ) ? (string) $flag['text'] : '' ) . '</span>'
					. ( '' !== $buttons ? '<div class="ecodv2-callout-buttons">' . $buttons . '</div>' : '' )
					. '</div></div>';
			}
			return $html;
		}

		/**
		 * The stock a paid manual payment order still owes: its lines never adjusted ( ec_orderdetail.stock_adjusted 0 ) whose
		 * product counts stock ( for the product or per variant ). Checkout leaves a manual payment's stock until the order is
		 * paid; WP EasyCart 6.0.2 takes it on every path that makes it paid ( wp_easycart_order_pay::take_stock() ), 6.0.1 did not.
		 * Other payment methods are left alone: WP EasyCart PRO's older gateway return pages took stock without marking the lines.
		 *
		 * @since 6.0.2 bug round 14
		 * @param object $order Order row ( with is_approved ).
		 * @return array units ( items whose product counts stock ), lines ( every line not adjusted ); both 0 when nothing is owed.
		 */
		public static function stock_owed( $order ) {
			global $wpdb;
			$owed = array(
				'units' => 0,
				'lines' => 0,
			);
			if ( empty( $order->is_approved ) || in_array( (int) $order->orderstatus_id, array( 16, 19 ), true ) || 'manual_bill' !== (string) $order->payment_method ) {
				return $owed;
			}
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT COUNT(*) AS lines_open, COALESCE( SUM( CASE WHEN ( p.show_stock_quantity = 1 OR p.use_optionitem_quantity_tracking = 1 ) THEN d.quantity ELSE 0 END ), 0 ) AS units FROM ec_orderdetail AS d LEFT JOIN ec_product AS p ON p.product_id = d.product_id WHERE d.order_id = %d AND d.stock_adjusted = 0', (int) $order->order_id ) );
			if ( $row && (int) $row->units > 0 ) {
				$owed['units'] = (int) $row->units;
				$owed['lines'] = (int) $row->lines_open;
			}
			return $owed;
		}

		/**
		 * The stock an unpaid manual payment order will take when it is marked paid ( the next step panel says so ).
		 *
		 * @since 6.0.2 bug round 14
		 * @param object $order Order row ( with is_approved ).
		 * @return bool
		 */
		public static function stock_waits_for_payment( $order ) {
			global $wpdb;
			if ( ! empty( $order->is_approved ) || in_array( (int) $order->orderstatus_id, array( 16, 19 ), true ) || 'manual_bill' !== (string) $order->payment_method ) {
				return false;
			}
			return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT d.orderdetail_id FROM ec_orderdetail AS d INNER JOIN ec_product AS p ON p.product_id = d.product_id WHERE d.order_id = %d AND d.stock_adjusted = 0 AND ( p.show_stock_quantity = 1 OR p.use_optionitem_quantity_tracking = 1 ) LIMIT 1', (int) $order->order_id ) );
		}

		/* ------------------------------------------------------------------ */
		/* The fast path: the order's steps, the next step and the queue       */
		/* ------------------------------------------------------------------ */

		/**
		 * A time the database wrote ( the order date, an order log time ) as a timestamp in the site's time zone, the way
		 * the order screen prints the order date ( wp_easycart_admin_details_orders::init_data() ).
		 *
		 * @param string $db_time Database date and time.
		 * @return int 0 when there is none.
		 */
		public static function local_time( $db_time ) {
			static $diff = null;
			if ( null === $diff ) {
				global $wpdb;
				$now_db = strtotime( (string) $wpdb->get_var( 'SELECT NOW()' ) );
				$diff   = (int) round( (float) get_option( 'gmt_offset' ) * 3600 ) - ( $now_db ? $now_db - time() : 0 );
			}
			$time = ( '' !== trim( (string) $db_time ) ) ? strtotime( (string) $db_time ) : false;
			return $time ? $time + $diff : 0;
		}

		/**
		 * Everything the steps and the next step panel read about the order. The order screen passes what it already worked
		 * out; a status change's answer ( status_reply() ) works it out here.
		 *
		 * @param object     $order Order row ( with is_approved ).
		 * @param array|null $lines ec_orderdetail rows, or null to read them.
		 * @return array summary, fulfillment ( fulfillment() ), flags, next ( next_step() ), lines, line_states.
		 */
		public static function context( $order, $lines = null ) {
			global $wpdb;
			$summary = class_exists( 'wp_easycart_order_payments' ) ? wp_easycart_order_payments::summary( $order ) : null;
			$ful     = self::fulfillment( $order );
			$flags   = self::flags( $order );
			$next    = self::next_step(
				$order,
				array(
					'fulfillment'  => $ful['state'],
					'payment'      => ( $summary && ! empty( $summary['recorded'] ) ) ? $summary['state'] : '',
					'local_pickup' => $ful['local_pickup'],
					'pickup_kind'  => $ful['pickup_kind'],
					'store_open'   => $ful['store_open'],
					'flags'        => $flags,
				)
			);
			if ( null === $lines ) {
				$lines = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ec_orderdetail WHERE order_id = %d ORDER BY orderdetail_id', (int) $order->order_id ) );
			}
			return array(
				'summary'     => $summary,
				'fulfillment' => $ful,
				'flags'       => $flags,
				'next'        => $next,
				'lines'       => (array) $lines,
				'line_states' => ( 'pickup' === $ful['state'] || $ful['local_pickup'] ) ? array() : self::line_states( $order, $lines ),
			);
		}

		/**
		 * The items the store still has to send ( a fulfillment partner's lines and lines already shipped are left out ).
		 *
		 * @param array $ctx context().
		 * @return int Units.
		 */
		public static function units_to_ship( $ctx ) {
			$units = 0;
			foreach ( (array) $ctx['lines'] as $line ) {
				$id = (int) $line->orderdetail_id;
				if ( isset( $ctx['line_states'][ $id ] ) ) {
					if ( 'ship' === $ctx['line_states'][ $id ]['group'] ) {
						$units += max( 0, (int) $ctx['line_states'][ $id ]['quantity'] - (int) $ctx['line_states'][ $id ]['shipped'] );
					}
				} elseif ( ! empty( $line->is_shippable ) && empty( $line->is_download ) && empty( $line->is_giftcard ) ) {
					$units += max( 0, (int) $line->quantity - ( isset( $line->refunded_quantity ) ? (int) $line->refunded_quantity : 0 ) );
				}
			}
			return $units;
		}

		/**
		 * The store's own packages, as the Fulfill window lists them ( wp_easycart_admin_packages::label_rows() ). Read once
		 * per order and request.
		 *
		 * @param object $order Order row.
		 * @return array
		 */
		public static function label_rows( $order ) {
			$order_id = (int) $order->order_id;
			if ( ! isset( self::$rows_cache[ $order_id ] ) ) {
				self::$rows_cache[ $order_id ] = ( class_exists( 'wp_easycart_admin_packages' ) && method_exists( 'wp_easycart_admin_packages', 'label_rows' ) ) ? (array) wp_easycart_admin_packages::label_rows( $order ) : array();
			}
			return self::$rows_cache[ $order_id ];
		}

		/**
		 * Read the order's packages again on the next label_rows() ( they changed in this request ).
		 *
		 * @since 6.0.2
		 * @param int $order_id Order.
		 */
		public static function forget_rows( $order_id ) {
			unset( self::$rows_cache[ (int) $order_id ], self::$services_cache[ (int) $order_id ] );
		}

		/**
		 * The label services extensions offer for an order ( WP EasyCart for Stamps.com, Shippo, ShipStation ): the rows of the
		 * Fulfill window's label step, and the Ship panel's key label button ( label_buyers() ). Asked once per order and request.
		 *
		 * @since 6.0.3 ( the filter is from 6.0.2; order-details.php asked it itself )
		 * @param object $order Order row.
		 * @return array slug => row.
		 */
		public static function label_services( $order ) {
			$order_id = ( is_object( $order ) && isset( $order->order_id ) ) ? (int) $order->order_id : 0;
			if ( ! isset( self::$services_cache[ $order_id ] ) ) {
				/**
				 * Label services an extension draws itself in the Fulfill window. A row keyed shippo, stamps or shipstation replaces
				 * the built-in link for that service. A row whose onclick buys this order's label in the page ( a cta, no url ) is
				 * also the Ship panel's key button ( 6.0.3; 'buy' => true | false says so outright ).
				 *
				 * @since 6.0.2
				 * @param array  $rows  slug => array( name, sub, logo ( two letters ), color, cta, onclick | url, integrated ( bool ), buy ( bool, 6.0.3 ) ).
				 * @param object $order Order row.
				 */
				$rows                              = apply_filters( 'wp_easycart_ecv2_label_services', array(), $order );
				self::$services_cache[ $order_id ] = is_array( $rows ) ? $rows : array();
			}
			return self::$services_cache[ $order_id ];
		}

		/**
		 * The label services that can buy this order's label right on the order screen: a row with a call to action and an onclick
		 * ( the extension's label window ), not a link elsewhere ( Connect, Settings, Open Shippo ) and not a row with nothing to do
		 * ( a fulfillment partner ships it all ). A row's 'buy' says so outright.
		 *
		 * @since 6.0.3
		 * @param object $order Order row.
		 * @return array Each slug, name, logo, color, onclick.
		 */
		public static function label_buyers( $order ) {
			$buyers = array();
			foreach ( self::label_services( $order ) as $slug => $row ) {
				if ( ! is_array( $row ) || empty( $row['name'] ) ) {
					continue;
				}
				$onclick = isset( $row['onclick'] ) ? trim( (string) $row['onclick'] ) : '';
				$cta     = isset( $row['cta'] ) ? trim( (string) $row['cta'] ) : '';
				$url     = isset( $row['url'] ) ? trim( (string) $row['url'] ) : '';
				$buys    = isset( $row['buy'] ) ? (bool) $row['buy'] : ( '' !== $cta && '' === $url && '' !== $onclick && ! preg_match( '/^return\s+false\s*;?$/i', $onclick ) );
				if ( ! $buys || '' === $onclick ) {
					continue;
				}
				$color    = ( isset( $row['color'] ) && sanitize_hex_color( (string) $row['color'] ) ) ? (string) $row['color'] : '#6b7280';
				$buyers[] = array(
					'slug'    => sanitize_key( (string) $slug ),
					'name'    => (string) $row['name'],
					'logo'    => isset( $row['logo'] ) ? substr( (string) $row['logo'], 0, 3 ) : '',
					'color'   => $color,
					'onclick' => $onclick,
				);
			}
			return $buyers;
		}

		/**
		 * The Ship panel's key label button: with one label service ready to buy this order's label, its Buy label ( the
		 * extension's own label window ); with several, Buy a label, which opens the Fulfill window where each is listed; with
		 * none, ''. A store with Stamps.com connected bought labels through a small "Make a label" link under the form.
		 *
		 * @since 6.0.3
		 * @param object $order Order row.
		 * @return string
		 */
		public static function label_button_html( $order ) {
			$buyers = self::label_buyers( $order );
			if ( ! $buyers ) {
				return '';
			}
			if ( 1 === count( $buyers ) ) {
				$buyer = $buyers[0];
				return '<button type="button" class="ecv2-btn ecv2-btn-primary ecodv2-ship-label" id="ecodv2_ship_label" data-service="' . esc_attr( $buyer['slug'] ) . '"'
					/* translators: %s: a label service, e.g. Stamps.com. */
					. ' aria-label="' . esc_attr( sprintf( __( 'Buy a label with %s', 'wp-easycart' ), $buyer['name'] ) ) . '" onclick="' . esc_attr( $buyer['onclick'] ) . '">'
					. ( '' !== $buyer['logo'] ? '<span class="ecodv2-ship-label-logo" style="background:' . esc_attr( $buyer['color'] ) . ';" aria-hidden="true">' . esc_html( $buyer['logo'] ) . '</span>' : '' )
					. '<span class="ecodv2-ship-label-text">' . esc_html__( 'Buy label', 'wp-easycart' ) . '<small>' . esc_html( $buyer['name'] ) . '</small></span></button>';
			}
			$names = implode( ', ', wp_list_pluck( $buyers, 'name' ) );
			return '<button type="button" class="ecv2-btn ecv2-btn-primary ecodv2-ship-label" id="ecodv2_ship_label" data-service=""'
				/* translators: %s: label services, e.g. Stamps.com, Shippo. */
				. ' aria-label="' . esc_attr( sprintf( __( 'Buy a label: %s', 'wp-easycart' ), $names ) ) . '" onclick="ecodv2_fulfill_open(); return false;">'
				. '<span class="ecodv2-ship-label-text">' . esc_html__( 'Buy a label', 'wp-easycart' ) . '<small>' . esc_html( $names ) . '</small></span></button>';
		}

		/**
		 * The steps across the top of the order screen: Placed, Paid, Pack & ship, Shipped, Delivered ( a pickup reads Ready
		 * for pickup, Picked up; nothing to ship reads Placed, Paid, Nothing to ship; a refunded or canceled order ends there ).
		 *
		 * @param object $order Order row ( with is_approved ).
		 * @param array  $ctx   context().
		 * @return array Each key, label, sub, state ( done | current | warn | todo | bad ).
		 */
		public static function steps( $order, $ctx ) {
			$status   = (int) $order->orderstatus_id;
			$approved = ! empty( $order->is_approved );
			$ful      = $ctx['fulfillment'];
			$state    = $ful['state'];
			$summary  = $ctx['summary'];
			$recorded = is_array( $summary ) && ! empty( $summary['recorded'] );
			$paid     = $recorded ? (float) $summary['paid'] : ( $approved ? (float) $order->grand_total : 0.0 );
			$method   = self::payment_method( $order );
			$placed   = self::local_time( $order->order_date );
			$steps    = array(
				array(
					'key'   => 'placed',
					'label' => __( 'Placed', 'wp-easycart' ),
					'sub'   => $placed ? date_i18n( 'M j · ' . get_option( 'time_format', 'g:i a' ), $placed ) : '',
					'state' => 'done',
				),
			);
			$paid_sub = trim( self::money( $paid ) . ( '' !== $method['gateway'] ? ' · ' . $method['gateway'] : ( '' !== $method['method'] ? ' · ' . $method['method'] : '' ) ) );

			/* A refunded or canceled order ends at its outcome. */
			if ( 16 === $status || 19 === $status ) {
				if ( $paid >= 0.005 ) {
					$steps[] = array(
						'key'   => 'paid',
						'label' => __( 'Paid', 'wp-easycart' ),
						'sub'   => $paid_sub,
						'state' => 'done',
					);
				}
				$steps[] = array(
					'key'   => 'closed',
					'label' => ( 16 === $status ) ? __( 'Refunded', 'wp-easycart' ) : __( 'Canceled', 'wp-easycart' ),
					/* translators: %s: amount refunded. */
					'sub'   => ( (float) $order->refund_total >= 0.005 ) ? sprintf( __( '%s back to the customer', 'wp-easycart' ), self::money( $order->refund_total ) ) : '',
					'state' => 'bad',
				);
				return (array) apply_filters( 'wp_easycart_ecv2_order_steps', $steps, $order, $ctx );
			}

			/* Paid. */
			$pay_state = $recorded ? (string) $summary['state'] : ( $approved ? 'paid' : 'unpaid' );
			if ( 7 === $status || 9 === $status ) {
				$steps[] = array(
					'key'   => 'paid',
					'label' => __( 'Paid', 'wp-easycart' ),
					'sub'   => __( 'The payment failed', 'wp-easycart' ),
					'state' => 'bad',
				);
			} elseif ( 'partial' === $pay_state ) {
				$steps[] = array(
					'key'   => 'paid',
					'label' => __( 'Paid', 'wp-easycart' ),
					/* translators: %s: balance due. */
					'sub'   => sprintf( __( '%s still due', 'wp-easycart' ), self::money( $summary['due'] ) ),
					'state' => 'warn',
				);
			} elseif ( $approved || in_array( $pay_state, array( 'paid', 'overpaid' ), true ) ) {
				$steps[] = array(
					'key'   => 'paid',
					'label' => __( 'Paid', 'wp-easycart' ),
					'sub'   => $paid_sub,
					'state' => 'done',
				);
			} else {
				$waiting = ( 'manual_bill' === (string) $order->payment_method ) ? __( 'Waiting for payment by invoice', 'wp-easycart' ) : __( 'Waiting for payment', 'wp-easycart' );
				/* 6.0.2 bug round 6: once the invoice was emailed ( WP EasyCart PRO's Invoice email ), the step says when. */
				$invoiced = self::email_sent( (int) $order->order_id, 'order-invoice-email' );
				$steps[]  = array(
					'key'   => 'paid',
					'label' => __( 'Paid', 'wp-easycart' ),
					/* translators: %s: date the invoice was emailed, e.g. Sep 28. */
					'sub'   => $invoiced ? sprintf( __( 'Invoice sent %s', 'wp-easycart' ), date_i18n( 'M j', $invoiced ) ) : $waiting,
					'state' => 'current',
				);
			}
			$is_paid = ( 'done' === $steps[ count( $steps ) - 1 ]['state'] || 'warn' === $steps[ count( $steps ) - 1 ]['state'] );

			if ( 'digital' === $state ) {
				$steps[] = array(
					'key'   => 'digital',
					'label' => __( 'Nothing to ship', 'wp-easycart' ),
					'sub'   => __( 'Downloads, gift cards or services', 'wp-easycart' ),
					'state' => 'done',
				);
				return (array) apply_filters( 'wp_easycart_ecv2_order_steps', $steps, $order, $ctx );
			}

			if ( 'pickup' === $state || $ful['local_pickup'] ) {
				$collected = ( 'fulfilled' === $state );
				$kind      = isset( $ful['pickup_kind'] ) ? (string) $ful['pickup_kind'] : '';
				$steps[]   = array(
					'key'   => 'ready',
					'label' => __( 'Ready for pickup', 'wp-easycart' ),
					'sub'   => ( 'restaurant' === $kind || ! empty( $order->includes_restaurant_type ) ) ? __( 'Restaurant order', 'wp-easycart' ) : ( 'preorder' === $kind ? __( 'Preorder pickup', 'wp-easycart' ) : __( 'Local pickup', 'wp-easycart' ) ),
					'state' => $collected ? 'done' : ( $is_paid ? 'current' : 'todo' ),
				);
				$steps[]   = array(
					'key'   => 'collected',
					'label' => __( 'Picked up', 'wp-easycart' ),
					'sub'   => '',
					'state' => $collected ? 'done' : 'todo',
				);
				return (array) apply_filters( 'wp_easycart_ecv2_order_steps', $steps, $order, $ctx );
			}

			/* Pack & ship, Shipped, Delivered. */
			$rows   = self::label_rows( $order );
			$units  = self::units_to_ship( $ctx );
			$weight = 0.0;
			$boxes  = 0;
			foreach ( $rows as $row ) {
				if ( empty( $row['done'] ) ) {
					++$boxes;
				}
			}
			$pack_sub = '';
			if ( $boxes > 0 && class_exists( 'wp_easycart_shipments' ) && wp_easycart_shipments::ready() ) {
				foreach ( wp_easycart_shipments::plan( (int) $order->order_id ) as $package ) {
					if ( empty( $package->is_return ) && empty( $package->partner ) && 'packed' === (string) $package->status ) {
						$weight += (float) $package->weight;
					}
				}
				$units_label = class_exists( 'wp_easycart_packages' ) ? wp_easycart_packages::units() : array( 'weight' => 'lb' );
				/* translators: %d: number of boxes. */
				$pack_sub = sprintf( _n( '%d box', '%d boxes', $boxes, 'wp-easycart' ), $boxes ) . ( $weight > 0 ? ' · ' . rtrim( rtrim( number_format( $weight, 2, '.', '' ), '0' ), '.' ) . ' ' . $units_label['weight'] : '' );
			} elseif ( $units > 0 ) {
				/* translators: %d: number of items. */
				$pack_sub = sprintf( _n( '%d item', '%d items', $units, 'wp-easycart' ), $units );
			}
			if ( 'partial' === $state && is_array( $ful['fstate'] ) ) {
				/* translators: 1: items shipped, 2: items in the order to ship. */
				$pack_sub = sprintf( __( '%1$d of %2$d shipped', 'wp-easycart' ), (int) $ful['fstate']['shipped'], (int) $ful['fstate']['total'] );
			}
			$shipped   = ( 'fulfilled' === $state );
			$steps[]   = array(
				'key'   => 'pack',
				'label' => __( 'Pack & ship', 'wp-easycart' ),
				'sub'   => $pack_sub,
				'state' => $shipped ? 'done' : ( $is_paid ? ( 'partial' === $state ? 'warn' : 'current' ) : 'todo' ),
			);
			$ship_how  = trim( (string) $order->shipping_carrier );
			$ship_how  = '' !== $ship_how ? $ship_how : trim( (string) $order->shipping_method );
			$steps[]   = array(
				'key'   => 'shipped',
				'label' => __( 'Shipped', 'wp-easycart' ),
				'sub'   => $ship_how,
				'state' => $shipped ? 'done' : 'todo',
			);
			$delivered = ( class_exists( 'wp_easycart_shipments' ) && $status > 0 && wp_easycart_shipments::delivered_status_id_if_set() === $status );
			/* 6.0.2 bug round 8: or every package it sent arrived ( wp_easycart_shipments::packages_delivered() ). A partly refunded
			   order keeps its status when it is marked delivered, and a store with Mark orders delivered from tracking off keeps
			   Shipped; a package without a carrier's word ( shipped by hand ) no longer passes for delivered. */
			if ( ! $delivered && $shipped && class_exists( 'wp_easycart_shipments' ) && method_exists( 'wp_easycart_shipments', 'packages_delivered' ) ) {
				$delivered = wp_easycart_shipments::packages_delivered( (int) $order->order_id );
			}
			$track = '';
			if ( $shipped && ! $delivered && class_exists( 'wp_easycart_shipments' ) && wp_easycart_shipments::ready() ) {
				$seen = array();
				foreach ( wp_easycart_shipments::plan( (int) $order->order_id ) as $package ) {
					if ( empty( $package->is_return ) && empty( $package->partner ) && '' !== (string) $package->tracking_status ) {
						$seen[] = (string) $package->tracking_status;
					}
				}
				if ( array_intersect( $seen, array( 'failure', 'returned' ) ) ) {
					$track = 'problem';
				} elseif ( in_array( 'out_for_delivery', $seen, true ) ) {
					$track = 'out';
				} elseif ( in_array( 'in_transit', $seen, true ) ) {
					$track = 'transit';
				}
			}
			$track_subs = array(
				'problem' => __( 'Delivery problem', 'wp-easycart' ),
				'out'     => __( 'Out for delivery', 'wp-easycart' ),
				'transit' => __( 'In transit', 'wp-easycart' ),
			);
			/* 6.0.2 bug round 6: Delivered is optional. A label extension or fulfillment partner that reports tracking marks it
			   ( "From carrier tracking" ); otherwise nothing will, so a shipped order offers Mark delivered right on the step. */
			$fed      = ! $delivered && $shipped && class_exists( 'wp_easycart_shipments' ) && method_exists( 'wp_easycart_shipments', 'delivery_tracked' ) && wp_easycart_shipments::delivery_tracked( (int) $order->order_id );
			$offer    = ! $delivered && ! $fed && self::delivery_offer( $order, $ful );
			$delivery = array(
				'key'      => 'delivered',
				'label'    => __( 'Delivered', 'wp-easycart' ),
				'sub'      => $delivered ? '' : ( isset( $track_subs[ $track ] ) ? $track_subs[ $track ] : ( $fed ? __( 'From carrier tracking', 'wp-easycart' ) : __( 'Optional', 'wp-easycart' ) ) ),
				'state'    => $delivered ? 'done' : ( 'problem' === $track ? 'bad' : ( '' !== $track ? 'current' : 'todo' ) ),
				'optional' => ! $delivered && ! $fed && '' === $track,
			);
			/* 6.0.2 bug round 7: the step's own circle is the control ( no button under the step ): its tip says what a click does
			   and the step's line adds "click to mark", so a touch screen without hover says it too. */
			if ( $offer ) {
				$delivery['action'] = array(
					'label'   => __( 'Mark delivered', 'wp-easycart' ),
					'onclick' => 'ecodv2_mark_delivered( this ); return false;',
					'hint'    => __( 'Click once the customer has it', 'wp-easycart' ),
					'cue'     => __( 'click to mark', 'wp-easycart' ),
				);
			}
			$steps[] = $delivery;
			/**
			 * The steps across the top of the order screen.
			 *
			 * @since 6.0.2
			 * @param array  $steps Each key, label, sub, state ( done | current | warn | todo | bad ), and optionally optional
			 *                      ( bool: drawn as a step the order does not need ) and action ( label, onclick, hint, cue: the
			 *                      step's circle becomes a button that does it, e.g. Mark delivered. label names the button and
			 *                      heads its tip, hint is the tip's second line ( title is read when there is no hint ), cue is
			 *                      added after the step's line, e.g. "Optional · click to mark" ).
			 * @param object $order Order row.
			 * @param array  $ctx   wp_easycart_admin_order_screen::context().
			 */
			return (array) apply_filters( 'wp_easycart_ecv2_order_steps', $steps, $order, $ctx );
		}

		/**
		 * Can the store mark this order delivered from the order screen ( the Delivered step's and the next step panel's Mark
		 * delivered )? A paid order that shipped and was not fully refunded, canceled or picked up, is not a local pickup, and is
		 * not delivered yet ( wp_easycart_shipments::order_delivered(): its status, or every package it sent ). The steps and the
		 * panel leave the button out while a tracking feed will mark it ( wp_easycart_shipments::delivery_tracked() ).
		 *
		 * @since 6.0.2
		 * @param object     $order Order row ( with is_approved ).
		 * @param array|null $ful   fulfillment(), or null to work it out.
		 * @return bool
		 */
		public static function delivery_offer( $order, $ful = null ) {
			if ( ! self::deliverable( $order, $ful ) ) {
				return false;
			}
			return ! wp_easycart_shipments::order_delivered( (int) $order->order_id, (int) $order->orderstatus_id );
		}

		/**
		 * Can this order be marked delivered at all? Paid, shipped ( fulfilled, not a local pickup ), not refunded, canceled or
		 * picked up, and no package of the store's still waiting for its tracking unless the order was marked shipped or carries
		 * a tracking number of its own that no package explains ( then the store says it went ).
		 *
		 * 6.0.2 bug round 8: a partly refunded order ( Partial Refund ) still ships and arrives, so it can be marked delivered:
		 * its packages are, and it keeps its status ( wp_easycart_shipments::mark_order_delivered() ). That needs the packages
		 * table ( the 6.0.2 database update ).
		 *
		 * @since 6.0.2
		 * @param object     $order Order row ( with is_approved ).
		 * @param array|null $ful   fulfillment(), or null to work it out.
		 * @return bool
		 */
		private static function deliverable( $order, $ful = null ) {
			$status = (int) $order->orderstatus_id;
			if ( empty( $order->is_approved ) || in_array( $status, array( 16, 18, 19 ), true ) || ! class_exists( 'wp_easycart_shipments' ) || ! method_exists( 'wp_easycart_shipments', 'order_delivered' ) ) {
				return false;
			}
			if ( 17 === $status && ! wp_easycart_shipments::ready() ) {
				return false;
			}
			$ful = is_array( $ful ) ? $ful : self::fulfillment( $order );
			if ( 'fulfilled' !== $ful['state'] || ! empty( $ful['local_pickup'] ) ) {
				return false;
			}
			if ( wp_easycart_shipments::is_fulfilled_status( $status ) || ! wp_easycart_shipments::ready() || 0 === wp_easycart_shipments::waiting_packages( (int) $order->order_id, '' ) ) {
				return true;
			}
			/* The order's own tracking number, with no package of the store's gone out ( typed on the order before packages
			   existed ), says the packages still waiting went: wp_easycart_fulfillment::state() reads it the same way. */
			return '' !== wp_easycart_shipments::unrecorded_tracking( (int) $order->order_id, $order );
		}

		/**
		 * The steps as the page prints them ( an ordered list; the page script puts a status change's answer in its place ).
		 *
		 * @param array $steps steps().
		 * @return string
		 */
		public static function steps_html( $steps ) {
			$marks = array(
				'done'    => '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12l5 5 9-10"/></svg>',
				'bad'     => '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M7 7l10 10M17 7L7 17"/></svg>',
				'current' => '<span class="ecodv2-step-dot"></span>',
				'warn'    => '<span class="ecodv2-step-dot"></span>',
				'todo'    => '',
			);
			$said  = array(
				'done'    => __( 'done', 'wp-easycart' ),
				'current' => __( 'now', 'wp-easycart' ),
				'warn'    => __( 'now', 'wp-easycart' ),
				'todo'    => __( 'not yet', 'wp-easycart' ),
				'bad'     => __( 'stopped', 'wp-easycart' ),
			);
			/* The check a step's circle shows on hover when a click completes it ( an action, below ). */
			$check = '<svg class="ecodv2-step-check" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12l5 5 9-10"/></svg>';
			$html  = '<ol class="ecodv2-steps" id="ecodv2_steps_list" style="--ecodv2-steps:' . (int) max( 1, count( $steps ) ) . ';">';
			foreach ( (array) $steps as $index => $step ) {
				if ( ! is_array( $step ) || empty( $step['label'] ) ) {
					continue;
				}
				$state = isset( $step['state'], $marks[ $step['state'] ] ) ? $step['state'] : 'todo';
				$now   = ( 'current' === $state || 'warn' === $state );
				$key   = isset( $step['key'] ) ? (string) $step['key'] : '';
				/* 6.0.2 bug round 7: a step's one action ( the Delivered step's Mark delivered ) is its circle: a button with a tip
				   ( what it does, and when ), which fills and shows the check it will become on hover and focus. */
				$act   = ( isset( $step['action'] ) && is_array( $step['action'] ) && ! empty( $step['action']['label'] ) && ! empty( $step['action']['onclick'] ) ) ? $step['action'] : null;
				$html .= '<li class="ecodv2-step is-' . esc_attr( $state ) . ( ! empty( $step['optional'] ) ? ' is-optional' : '' ) . ( $act ? ' has-action' : '' ) . '" data-step="' . esc_attr( $key ) . '"' . ( $now ? ' aria-current="step"' : '' ) . '>';
				if ( $act ) {
					$tip   = 'ecodv2_step_tip_' . ( '' !== sanitize_key( $key ) ? sanitize_key( $key ) : (int) $index );
					$hint  = ! empty( $act['hint'] ) ? (string) $act['hint'] : ( ! empty( $act['title'] ) ? (string) $act['title'] : '' );
					$html .= '<button type="button" class="ecodv2-step-mark ecodv2-step-mark-btn" data-step-act="' . esc_attr( $key ) . '" data-busy="ring" onclick="' . esc_attr( $act['onclick'] ) . '" aria-label="' . esc_attr( $act['label'] ) . '"' . ( '' !== $hint ? ' aria-describedby="' . esc_attr( $tip ) . '_hint"' : '' ) . '>' . $marks[ $state ] . $check . '</button>';
					$html .= '<span class="ecodv2-step-tip" id="' . esc_attr( $tip ) . '" role="tooltip"><b>' . esc_html( $act['label'] ) . '</b>' . ( '' !== $hint ? '<span id="' . esc_attr( $tip ) . '_hint">' . esc_html( $hint ) . '</span>' : '' ) . '</span>';
				} else {
					$html .= '<span class="ecodv2-step-mark" aria-hidden="true">' . $marks[ $state ] . '</span>';
				}
				$html .= '<span class="ecodv2-step-text"><span class="ecodv2-step-label">' . esc_html( $step['label'] ) . ( $now ? ' <span class="ecodv2-step-now" aria-hidden="true">' . esc_html__( 'Now', 'wp-easycart' ) . '</span>' : '' ) . '<span class="screen-reader-text">: ' . esc_html( $said[ $state ] ) . '</span></span>';

				$sub = isset( $step['sub'] ) ? (string) $step['sub'] : '';
				$cue = ( $act && ! empty( $act['cue'] ) ) ? (string) $act['cue'] : '';
				if ( '' !== $sub || '' !== $cue ) {
					$html .= '<span class="ecodv2-step-sub">' . esc_html( $sub ) . ( '' !== $cue ? '<span class="ecodv2-step-cue" aria-hidden="true">' . esc_html( ( '' !== $sub ? ' · ' : '' ) . $cue ) . '</span>' : '' ) . '</span>';
				}
				$html .= '</span></li>';
			}
			$html .= '</ol>';
			/* A phone has room for one line under the marks: the step the order is at ( else the last one done ). */
			$at = null;
			foreach ( (array) $steps as $step ) {
				if ( is_array( $step ) && isset( $step['state'] ) && in_array( $step['state'], array( 'current', 'warn', 'bad' ), true ) ) {
					$at = $step;
					break;
				}
				if ( is_array( $step ) && isset( $step['state'] ) && 'done' === $step['state'] ) {
					$at = $step;
				}
			}
			if ( $at && ! empty( $at['label'] ) ) {
				$html .= '<p class="ecodv2-steps-caption" aria-hidden="true">' . esc_html( $at['label'] ) . ( isset( $at['sub'] ) && '' !== (string) $at['sub'] ? ' <span>· ' . esc_html( $at['sub'] ) . '</span>' : '' ) . '</p>';
			}
			return $html;
		}

		/**
		 * The carriers the Fulfill window and the Ship form offer, and the one the order's carrier field starts on.
		 *
		 * 6.0.3: the list is wp_easycart_carriers ( the major carriers worldwide, the store's country and recent orders first ) with
		 * Other… for any carrier's name; the Fulfill window's "make it at the carrier" links are the carriers with a label site.
		 *
		 * @param object $order Order row.
		 * @return array defs ( key => label, sub, url, configured, keywords: the label sites ), suggested ( a defs key or '' ),
		 *               selected ( the carrier name the fields start on ), options ( <option> list ).
		 */
		public static function label_carriers( $order ) {
			$sites = class_exists( 'wp_easycart_carriers' ) ? wp_easycart_carriers::label_sites() : array();
			$setup = array(
				'usps'  => 'ec_option_usps_v3_client_id',
				'ups'   => 'ec_option_ups_token_info',
				'fedex' => 'ec_option_fedex_api_key',
				'dhl'   => 'ec_option_dhl_account_number',
			);
			$defs  = array();
			foreach ( $sites as $key => $entry ) {
				$defs[ $key ] = array(
					'label'      => (string) $entry['name'],
					'sub'        => (string) $entry['label_sub'],
					'url'        => (string) $entry['label_url'],
					'configured' => isset( $setup[ $key ] ) && ! empty( get_option( $setup[ $key ] ) ),
					'keywords'   => (string) $entry['keywords'],
				);
			}
			/**
			 * The carriers the order screen's Fulfill window links to ( where a label is made ).
			 *
			 * @since 6.0.2
			 * @param array  $defs  key => array( label, sub, url, configured, keywords ( | separated ) ).
			 * @param object $order Order row.
			 */
			$defs     = (array) apply_filters( 'wp_easycart_ecv2_label_carriers', $defs, $order );
			$selected = class_exists( 'wp_easycart_carriers' ) ? wp_easycart_carriers::suggested( $order ) : trim( (string) $order->shipping_carrier );
			/* A label site that matches the order's own carrier is marked Suggested ( only when the method or the order names it ). */
			$suggested = '';
			$named     = class_exists( 'wp_easycart_carriers' ) ? wp_easycart_carriers::suggested( $order, false ) : '';
			foreach ( $defs as $key => $def ) {
				if ( '' !== $named && isset( $def['label'] ) && strtolower( (string) $def['label'] ) === strtolower( $named ) ) {
					$suggested = (string) $key;
					break;
				}
			}
			/* Names an extension adds ( the label sites filter, the orders list's carrier filter ) are offered too. */
			$extra = function_exists( 'ecv2_order_carrier_suggestions' ) ? (array) ecv2_order_carrier_suggestions() : array();
			foreach ( $defs as $def ) {
				if ( isset( $def['label'] ) ) {
					$extra[] = (string) $def['label'];
				}
			}
			if ( class_exists( 'wp_easycart_carriers' ) ) {
				$options = wp_easycart_carriers::options_html( $selected, $extra );
			} else {
				$options = '';
				foreach ( array_unique( array_merge( array( 'USPS', 'UPS', 'FedEx', 'DHL', 'Canada Post', 'Royal Mail', 'Australia Post' ), $extra ) ) as $name ) {
					$options .= '<option value="' . esc_attr( $name ) . '"' . ( strtolower( (string) $name ) === strtolower( $selected ) ? ' selected="selected"' : '' ) . '>' . esc_html( $name ) . '</option>';
				}
				$options .= '<option value="">' . esc_html__( 'Other', 'wp-easycart' ) . '</option>';
			}
			return array(
				'defs'      => $defs,
				'suggested' => $suggested,
				'selected'  => $selected,
				'options'   => $options,
			);
		}

		/**
		 * The store's orders waiting to ship, besides this one ( the orders list's Awaiting Fulfillment ): how many, and the
		 * one waiting longest ( the next to ship ).
		 *
		 * @param int $order_id This order.
		 * @return array count, next_id, next_hours ( how long it has waited ).
		 */
		public static function queue( $order_id ) {
			static $cache = array();
			global $wpdb;
			$order_id = (int) $order_id;
			if ( isset( $cache[ $order_id ] ) ) {
				return $cache[ $order_id ];
			}
			$out = array(
				'count'      => 0,
				'next_id'    => 0,
				'next_hours' => 0,
			);
			if ( class_exists( 'wp_easycart_admin_order_table' ) && method_exists( 'wp_easycart_admin_order_table', 'unfulfilled_where' ) ) {
				/* 6.0.2: the store-wide queue is kept for a short while ( queue_all() ); this order is taken out of it. */
				$all     = self::queue_all();
				$waiting = false;
				foreach ( $all['next'] as $row ) {
					if ( $order_id === $row['id'] ) {
						$waiting = true;
					} elseif ( ! $out['next_id'] ) {
						$out['next_id']    = $row['id'];
						$out['next_hours'] = $row['hours'];
					}
				}
				if ( ! $waiting && $all['count'] > count( $all['next'] ) ) {
					$where = wp_easycart_admin_order_table::unfulfilled_where();
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- unfulfilled_where() is a fixed fragment built from the order table's own constants.
					$waiting = (bool) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM ec_order LEFT JOIN ec_orderstatus ON ( ec_orderstatus.status_id = ec_order.orderstatus_id ) WHERE ec_order.order_id = %d AND {$where}", $order_id ) );
				}
				$out['count'] = max( 0, $all['count'] - ( $waiting ? 1 : 0 ) );
				if ( ! $out['count'] ) {
					$out['next_id']    = 0;
					$out['next_hours'] = 0;
				}
			}
			$cache[ $order_id ] = $out;
			return $out;
		}

		/**
		 * Every order waiting to ship ( the orders list's Awaiting Fulfillment ): how many, and the two waiting longest. Working
		 * it out reads every paid order, so the answer is kept for two minutes ( wp_easycart_shipments::keep_queue() ); an order
		 * placed, changed, shipped or deleted forgets it ( wp_easycart_shipments::forget_queue() ).
		 *
		 * @since 6.0.2
		 * @return array count, next ( up to two: id, hours waited ).
		 */
		private static function queue_all() {
			global $wpdb;
			$keep = class_exists( 'wp_easycart_shipments' ) && method_exists( 'wp_easycart_shipments', 'keep_queue' );
			if ( $keep ) {
				$all = get_transient( wp_easycart_shipments::QUEUE_CACHE );
				if ( is_array( $all ) && isset( $all['count'], $all['next'] ) && is_array( $all['next'] ) ) {
					return $all;
				}
			}
			$where = wp_easycart_admin_order_table::unfulfilled_where();
			$all   = array(
				'count' => 0,
				'next'  => array(),
			);
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- unfulfilled_where() is a fixed fragment built from the order table's own constants; nothing else goes in.
			$all['count'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM ec_order LEFT JOIN ec_orderstatus ON ( ec_orderstatus.status_id = ec_order.orderstatus_id ) WHERE {$where}" );
			if ( $all['count'] > 0 ) {
				foreach ( (array) $wpdb->get_results( "SELECT ec_order.order_id, TIMESTAMPDIFF( HOUR, ec_order.order_date, NOW() ) AS hours FROM ec_order LEFT JOIN ec_orderstatus ON ( ec_orderstatus.status_id = ec_order.orderstatus_id ) WHERE {$where} ORDER BY ec_order.order_date ASC, ec_order.order_id ASC LIMIT 2" ) as $row ) {
					$all['next'][] = array(
						'id'    => (int) $row->order_id,
						'hours' => max( 0, (int) $row->hours ),
					);
				}
			}
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $keep ) {
				wp_easycart_shipments::keep_queue( $all );
			}
			return $all;
		}

		/**
		 * How long an order has waited, in words ( "3 hours", "2 days" ).
		 *
		 * @param int $hours Hours.
		 * @return string
		 */
		private static function waited( $hours ) {
			$hours = (int) $hours;
			if ( $hours < 1 ) {
				return __( 'under an hour', 'wp-easycart' );
			}
			if ( $hours < 48 ) {
				/* translators: %d: hours. */
				return sprintf( _n( '%d hour', '%d hours', $hours, 'wp-easycart' ), $hours );
			}
			$days = (int) floor( $hours / 24 );
			/* translators: %d: days. */
			return sprintf( _n( '%d day', '%d days', $days, 'wp-easycart' ), $days );
		}

		/**
		 * The link to an order on this screen.
		 *
		 * @param int $order_id Order.
		 * @return string
		 */
		public static function order_url( $order_id ) {
			return admin_url( 'admin.php?page=wp-easycart-orders&subpage=orders&order_id=' . (int) $order_id . '&ec_admin_form_action=edit' );
		}

		/**
		 * The printable packing slip for the order ( the Print menu's ).
		 *
		 * @param int $order_id Order.
		 * @return string
		 */
		public static function packing_slip_url( $order_id ) {
			return admin_url( 'admin.php?page=wp-easycart-orders&subpage=orders&bulk=' . (int) $order_id . '&ec_admin_form_action=print-packing-slip&wp_easycart_nonce=' . wp_create_nonce( 'wp-easycart-bulk-orders' ) );
		}

		/**
		 * The next step panel under the steps: what to do with the order now and the means to do it. When the next step is
		 * to ship, the carrier, the tracking number and Ship order are right there ( an order with more than one package to
		 * ship opens the Fulfill window at its tracking step ); another next step shows its button; with nothing to do it
		 * says where the order stands. The shipping queue sits beside it.
		 *
		 * @param object $order Order row ( with is_approved ).
		 * @param array  $ctx   context().
		 * @return string
		 */
		public static function next_panel_html( $order, $ctx ) {
			$order_id = (int) $order->order_id;
			$next     = $ctx['next'];
			$ful      = $ctx['fulfillment'];
			$state    = $ful['state'];
			$status   = (int) $order->orderstatus_id;
			$name     = trim( (string) $order->shipping_first_name . ' ' . (string) $order->shipping_last_name );
			$name     = '' !== $name ? $name : trim( (string) $order->billing_first_name . ' ' . (string) $order->billing_last_name );
			$country  = ( isset( $order->shipping_country_name ) && '' !== (string) $order->shipping_country_name ) ? (string) $order->shipping_country_name : (string) $order->shipping_country;
			$address  = trim( implode( ', ', array_filter( array( trim( (string) $order->shipping_address_line_1 . ' ' . (string) $order->shipping_address_line_2 ), trim( (string) $order->shipping_city . ' ' . (string) $order->shipping_state . ' ' . (string) $order->shipping_zip ), $country ) ) ), ', ' );
			$kind     = 'waiting';
			$title    = '';
			$text     = '';
			$body     = '';
			$links    = '';
			$asides   = array(); /* 6.0.2 bug round 14: short lines under the text ( icon, text, warn ) */

			$has_tracks = false;

			/* What the banner of the older layout carried: WP EasyCart PRO's Track and Mark Picked Up. */
			ob_start();
			do_action( 'wp_easycart_ecv2_order_details_fulfillment_banner', $order, $state );
			$extra = trim( (string) ob_get_clean() );

			if ( $next && 'fulfill' === $next['key'] ) {
				$kind  = 'ship';
				$units = self::units_to_ship( $ctx );
				$rows  = self::label_rows( $order );
				$open  = array();
				foreach ( $rows as $row ) {
					if ( empty( $row['done'] ) ) {
						$open[] = $row;
					}
				}
				if ( 'partial' === $state ) {
					/* translators: 1: number of items, 2: customer name. */
					$title = '' !== $name ? sprintf( _n( 'Ship the last %1$d item to %2$s', 'Ship the remaining %1$d items to %2$s', max( 1, $units ), 'wp-easycart' ), max( 1, $units ), $name ) : sprintf( _n( 'Ship the last %d item', 'Ship the remaining %d items', max( 1, $units ), 'wp-easycart' ), max( 1, $units ) );
				} elseif ( $units > 0 ) {
					/* translators: 1: number of items, 2: customer name. */
					$title = '' !== $name ? sprintf( _n( 'Ship %1$d item to %2$s', 'Ship %1$d items to %2$s', $units, 'wp-easycart' ), $units, $name ) : sprintf( _n( 'Ship %d item', 'Ship %d items', $units, 'wp-easycart' ), $units );
				} else {
					$title = __( 'Ship this order', 'wp-easycart' );
				}
				$bits = array( $address );
				$how  = trim( (string) $order->shipping_method );
				if ( '' !== $how ) {
					/* translators: 1: shipping method, 2: what the customer paid for shipping. */
					$bits[] = ( (float) $order->shipping_total >= 0.005 ) ? sprintf( __( '%1$s, paid %2$s', 'wp-easycart' ), $how, self::money( $order->shipping_total ) ) : $how;
				}
				if ( 1 === count( $open ) && '' !== (string) $open[0]['meta'] ) {
					$bits[] = (string) $open[0]['meta'];
				}
				$text = implode( ' · ', array_filter( $bits ) );
				if ( '' !== $ful['partner'] ) {
					$text .= ( '' !== $text ? ' ' : '' ) . $ful['partner'];
				}
				$carriers = self::label_carriers( $order );
				$email    = trim( (string) $order->user_email );
				/* 6.0.3: a label service that can buy this order's label ( Stamps.com, Shippo, ShipStation ) is the key button, and
				   Ship order ( typing the tracking in ) the second one. */
				$label_btn = self::label_button_html( $order );
				$go_class  = 'ecv2-btn' . ( '' === $label_btn ? ' ecv2-btn-primary' : '' ) . ' ecodv2-ship-go';
				if ( count( $open ) > 1 ) {
					$body = '<div class="ecodv2-ship-many">'
						/* translators: %d: number of packages. */
						. '<span>' . esc_html( sprintf( _n( '%d package to ship', '%d packages to ship', count( $open ), 'wp-easycart' ), count( $open ) ) ) . '</span>'
						. $label_btn
						. '<button type="button" class="' . esc_attr( $go_class ) . '" id="ecodv2_ship_go" data-ship-many="1" onclick="ecodv2_fulfill_tracking(); return false;"><span class="dashicons dashicons-airplane" aria-hidden="true"></span> ' . esc_html__( 'Add tracking for each package', 'wp-easycart' ) . '</button>'
						. '</div>';
				} else {
					$package = $open ? $open[0] : null;
					$body    = '<div class="ecodv2-ship" id="ecodv2_ship" data-order-id="' . esc_attr( (string) $order_id ) . '"'
						. ( $package ? ' data-package-index="' . esc_attr( (string) (int) $package['index'] ) . '" data-shipment-id="' . esc_attr( (string) (int) $package['shipment_id'] ) . '" data-packages-nonce="' . esc_attr( wp_create_nonce( 'wp-easycart-ecv2-order-packages' ) ) . '"' : '' ) . '>'
						. '<div class="ecodv2-ship-field ecodv2-ship-carrier"><label for="ecodv2_ship_carrier">' . esc_html__( 'Carrier', 'wp-easycart' ) . '</label><select id="ecodv2_ship_carrier" data-wpec-carrier="1">' . $carriers['options'] . '</select></div>'
						. '<div class="ecodv2-ship-field ecodv2-ship-tracking"><label for="ecodv2_ship_tracking">' . esc_html__( 'Tracking number', 'wp-easycart' ) . '</label>'
						. '<div class="ecodv2-ship-input"><input type="text" id="ecodv2_ship_tracking" placeholder="' . esc_attr__( 'Paste or scan a tracking number', 'wp-easycart' ) . '" autocomplete="off" spellcheck="false" inputmode="text" />'
						. '<button type="button" class="ecodv2-ship-scan" id="ecodv2_ship_scan" hidden aria-label="' . esc_attr__( 'Scan a tracking barcode with the camera', 'wp-easycart' ) . '" title="' . esc_attr__( 'Scan a tracking barcode', 'wp-easycart' ) . '"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M4 8V5a1 1 0 0 1 1-1h3M16 4h3a1 1 0 0 1 1 1v3M20 16v3a1 1 0 0 1-1 1h-3M8 20H5a1 1 0 0 1-1-1v-3"/><path d="M8 9v6M11 9v6M14 9v6M17 9v6"/></svg></button></div></div>'
						. ( '' !== $email ? '<label class="ecodv2-ship-email" title="' . esc_attr( sprintf( /* translators: %s: the customer's email address. */ __( 'The shipped email goes to %s', 'wp-easycart' ), $email ) ) . '"><input type="checkbox" id="ecodv2_ship_email" checked="checked" /> ' . esc_html( '' !== $name ? sprintf( /* translators: %s: customer's name. */ __( 'Email %s', 'wp-easycart' ), $name ) : __( 'Email the customer', 'wp-easycart' ) ) . '</label>' : '' )
						. $label_btn
						. '<button type="button" class="' . esc_attr( $go_class ) . '" id="ecodv2_ship_go" onclick="ecodv2_ship(); return false;"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M3 7h11v9H3z"/><path d="M14 10h4l3 3v3h-7"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/></svg> ' . esc_html__( 'Ship order', 'wp-easycart' ) . ' <kbd class="ecodv2-kbd ecodv2-kbd-on">' . esc_html__( 'Enter', 'wp-easycart' ) . '</kbd></button>'
						. '</div>';
				}
				$buyers = count( self::label_buyers( $order ) );
				$links  = ( 0 === $buyers ? '<button type="button" class="ecodv2-next-link" onclick="ecodv2_fulfill_open(); return false;">' . esc_html__( 'Make a label', 'wp-easycart' ) . '</button>' : '' )
					/* 6.0.3: the Fulfill window still lists the carriers' own sites and the other services. */
					. ( 1 === $buyers ? '<button type="button" class="ecodv2-next-link" onclick="ecodv2_fulfill_open(); return false;">' . esc_html__( 'More label options', 'wp-easycart' ) . '</button>' : '' )
					. '<a class="ecodv2-next-link" href="' . esc_url( self::packing_slip_url( $order_id ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Print packing slip', 'wp-easycart' ) . '</a>'
					. ( $rows ? '<button type="button" class="ecodv2-next-link" onclick="ecodv2_edit_packages(); return false;">' . esc_html__( 'Edit packages', 'wp-easycart' ) . '</button>' : '' )
					. ( ( count( $open ) <= 1 && '' !== $email ) ? '<span class="ecodv2-next-note">' . esc_html( '' !== $name ? sprintf( /* translators: %s: customer's name. */ __( 'The order is marked shipped and %s gets the tracking in one step.', 'wp-easycart' ), $name ) : __( 'The order is marked shipped and the customer gets the tracking in one step.', 'wp-easycart' ) ) . '</span>' : '' );
			} elseif ( $next ) {
				$kind   = 'step';
				$title  = isset( $next['title'] ) && '' !== (string) $next['title'] ? (string) $next['title'] : (string) $next['label'];
				$text   = isset( $next['text'] ) ? (string) $next['text'] : '';
				$review = self::review_flags( $ctx['flags'] );
				if ( 'review' === $next['key'] && $review ) {
					$title = (string) $review[0]['title'];
					$text  = __( 'Read the notice above before you ship or refund anything.', 'wp-easycart' );
				}
				$body = '<div class="ecodv2-next-actions"><button type="button" class="ecv2-btn ecv2-btn-primary" id="ecodv2_next_btn" data-step="' . esc_attr( $next['key'] ) . '" onclick="' . esc_attr( $next['onclick'] ) . '">' . ( '' !== (string) $next['icon'] ? '<span class="dashicons dashicons-' . esc_attr( $next['icon'] ) . '" aria-hidden="true"></span> ' : '' ) . esc_html( $next['label'] ) . '</button></div>';
			} elseif ( 16 === $status || 19 === $status ) {
				$kind  = 'closed';
				$title = ( 16 === $status ) ? __( 'This order was refunded', 'wp-easycart' ) : __( 'This order was canceled', 'wp-easycart' );
				$text  = __( 'Nothing left to do on it.', 'wp-easycart' );
			} elseif ( 'fulfilled' === $state ) {
				$kind     = 'done';
				$tracking = trim( (string) $order->tracking_number );
				if ( $ful['local_pickup'] ) {
					$title = __( 'Picked up', 'wp-easycart' );
					$text  = __( 'The customer has collected this order.', 'wp-easycart' );
				} else {
					/* 6.0.2 bug round 8: delivered by its status, or by its packages ( a partly refunded order keeps its status ). */
					$title = ( class_exists( 'wp_easycart_shipments' ) && method_exists( 'wp_easycart_shipments', 'order_delivered' ) && wp_easycart_shipments::order_delivered( $order_id, $status ) ) ? __( 'Delivered', 'wp-easycart' ) : __( 'Shipped', 'wp-easycart' );
					/* 6.0.2 bug round 14: tracking saved on an order that is not paid yet ( a manual payment ) ships it, but the order is
					   not marked shipped until it is paid. */
					if ( empty( $order->is_approved ) ) {
						$asides[] = array( 'warning', __( 'Not paid yet. Mark the order paid to finish it.', 'wp-easycart' ), true );
					}
					/* 6.0.2 bug round 7: each tracking number as a card ( the carrier's tile, the number with a copy button, Track ),
					   and Mark delivered beside them as one optional action that says when to use it. */
					$tracks = self::tracking_list( $order );
					$cards  = '';
					foreach ( $tracks as $track ) {
						$cards .= self::tracking_card_html( $track, $title );
					}
					if ( '' === $cards ) {
						$text = ( '' !== $tracking ) ? trim( (string) $order->shipping_carrier . ' ' . $tracking ) : ( '' !== trim( (string) $order->shipping_method ) ? (string) $order->shipping_method : __( 'Marked as shipped.', 'wp-easycart' ) );
					} else {
						$has_tracks = true;
					}
					/* 6.0.2 bug round 6: nothing reports delivery for this order ( no label service or partner tracking ), so the
					   store can mark it delivered once the customer has it. Optional: nothing else depends on it. */
					if ( self::delivery_offer( $order, $ful ) && ! wp_easycart_shipments::delivery_tracked( $order_id ) ) {
						$cards .= '<button type="button" class="ecodv2-deliver-btn" data-step-act="delivered" data-busy="ring" onclick="ecodv2_mark_delivered( this ); return false;">'
							. '<span class="ecodv2-deliver-ring" aria-hidden="true"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M5 12l5 5 9-10"/></svg></span>'
							. '<span class="ecodv2-deliver-text"><b>' . esc_html__( 'Mark delivered', 'wp-easycart' ) . '</b><small data-busy-line="1">' . esc_html__( 'Optional, once the customer has it', 'wp-easycart' ) . '</small></span>'
							. '</button>';
					}
					if ( '' !== $cards ) {
						$body = '<div class="ecodv2-done' . ( count( $tracks ) > 1 ? ' is-many' : '' ) . '">' . $cards . '</div>';
					}
				}
			} elseif ( 'digital' === $state ) {
				$kind  = 'done';
				$title = __( 'Nothing to ship', 'wp-easycart' );
				$text  = __( 'Every item is a download, a gift card, a subscription or a product with shipping turned off.', 'wp-easycart' );
			} elseif ( 'none' === $state ) {
				$title = ( 'manual_bill' === (string) $order->payment_method ) ? __( 'Waiting for payment by invoice', 'wp-easycart' ) : __( 'Waiting for payment', 'wp-easycart' );
				$text  = $ful['local_pickup'] ? __( 'Once the payment is approved, the order is ready for pickup.', 'wp-easycart' ) : __( 'Once the payment is approved, the order is ready to ship.', 'wp-easycart' );
			} elseif ( 'pickup' === $state || $ful['local_pickup'] ) {
				$title = __( 'Waiting for the customer to collect it', 'wp-easycart' );
				$text  = __( 'Change the status once they have it.', 'wp-easycart' );
			} elseif ( ! $ful['store_open'] && '' !== $ful['partner'] ) {
				$kind  = 'done';
				$title = __( 'With your fulfillment partner', 'wp-easycart' );
				$text  = $ful['partner'];
			} else {
				$kind  = 'done';
				$title = __( 'Nothing to do right now', 'wp-easycart' );
			}

			/* 6.0.2 bug round 14: a manual payment order keeps its stock until it is paid ( stock leaves when it is marked paid ). */
			if ( self::stock_waits_for_payment( $order ) ) {
				$asides[] = array( 'archive', __( 'Stock is taken when you mark this order paid.', 'wp-easycart' ), false );
			}
			$aside_html = '';
			foreach ( $asides as $aside ) {
				$aside_html .= '<p class="ecodv2-next-aside' . ( ! empty( $aside[2] ) ? ' is-warn' : '' ) . '"><span class="dashicons dashicons-' . esc_attr( $aside[0] ) . '" aria-hidden="true"></span> ' . esc_html( $aside[1] ) . '</p>';
			}

			/* The shipping queue: how many other orders wait, and the next one to ship. */
			$queue = '';
			$q     = self::queue( $order_id );
			if ( $q['count'] > 0 || in_array( $state, array( 'unfulfilled', 'partial', 'fulfilled' ), true ) ) {
				$queue = '<aside class="ecodv2-queue" aria-label="' . esc_attr__( 'Shipping queue', 'wp-easycart' ) . '">'
					. '<span class="ecodv2-next-eyebrow">' . esc_html__( 'Shipping queue', 'wp-easycart' ) . '</span>';
				if ( $q['count'] > 0 ) {
					$queue .= '<div class="ecodv2-queue-count"><b>' . esc_html( number_format_i18n( $q['count'] ) ) . '</b> <span>' . esc_html( in_array( $state, array( 'unfulfilled', 'partial' ), true ) ? _n( 'more order to ship', 'more orders to ship', $q['count'], 'wp-easycart' ) : _n( 'order to ship', 'orders to ship', $q['count'], 'wp-easycart' ) ) . '</span></div>'
						/* translators: 1: order number, 2: how long it has waited, e.g. 2 days. */
						. '<div class="ecodv2-queue-sub">' . esc_html( sprintf( __( 'Waiting longest: #%1$d, %2$s', 'wp-easycart' ), $q['next_id'], self::waited( $q['next_hours'] ) ) ) . '</div>'
						. '<a class="ecv2-btn' . ( 'ship' !== $kind ? ' ecv2-btn-primary' : '' ) . ' ecodv2-queue-next" id="ecodv2_queue_next" href="' . esc_url( self::order_url( $q['next_id'] ) ) . '">' . esc_html__( 'Next order to ship', 'wp-easycart' ) . ' <kbd class="ecodv2-kbd">N</kbd></a>';
				} else {
					$queue .= '<div class="ecodv2-queue-sub">' . esc_html( in_array( $state, array( 'unfulfilled', 'partial' ), true ) ? __( 'This is the only order waiting to ship.', 'wp-easycart' ) : __( 'Every order has shipped.', 'wp-easycart' ) ) . '</div>';
				}
				/* The orders list's Awaiting tile ( the same orders the queue counts ). */
				$queue .= '<a class="ecodv2-queue-all" href="' . esc_url( admin_url( 'admin.php?page=wp-easycart-orders&subpage=orders&health_filter=awaiting' ) ) . '">' . esc_html__( 'All orders to ship', 'wp-easycart' ) . '</a></aside>';
			}

			/* has-tracking: the panel lists the tracking itself, so WP EasyCart PRO's Track ( and 6.0.1's Copy ) in the banner hook
			   below would repeat it ( the stylesheet leaves them out ). */
			$eyebrow = in_array( $kind, array( 'ship', 'step' ), true ) ? __( 'Next step', 'wp-easycart' ) : __( 'Where it stands', 'wp-easycart' );
			$html    = '<section class="ecodv2-next is-' . esc_attr( $kind ) . ( '' !== $queue ? ' has-queue' : '' ) . ( $has_tracks ? ' has-tracking' : '' ) . '" id="ecodv2_next_panel" data-kind="' . esc_attr( $kind ) . '"' . ( $next ? ' data-step-key="' . esc_attr( $next['key'] ) . '"' : '' ) . ' data-state="' . esc_attr( $state ) . '" aria-labelledby="ecodv2_next_title">'
				. '<div class="ecodv2-next-main">'
				. '<span class="ecodv2-next-eyebrow">' . esc_html( $eyebrow ) . '</span>'
				. '<h2 class="ecodv2-next-title" id="ecodv2_next_title">' . esc_html( $title ) . '</h2>'
				. ( '' !== $text ? '<p class="ecodv2-next-text">' . esc_html( $text ) . '</p>' : '' )
				. $aside_html
				. $body
				. '<p class="ecodv2-ship-status" id="ecodv2_ship_status" role="status" aria-live="polite"></p>'
				. ( '' !== $links || '' !== $extra ? '<div class="ecodv2-next-links">' . $links . $extra . '</div>' : '' )
				. '</div>'
				. $queue
				. '</section>';
			return $html;
		}

		/**
		 * The tracking numbers the next step panel shows once the order shipped: each outgoing package with a number
		 * ( wp_easycart_shipments::tracking_rows(), a fulfillment partner's too ), else the order's own number.
		 *
		 * @since 6.0.2
		 * @param object $order Order row.
		 * @return array Each carrier, tracking, url ( the carrier's tracking page, or the label service's ), state ( the carrier's
		 *               latest word, e.g. In transit, or '' ), partner ( a fulfillment partner's name or '' ), package ( its
		 *               number when there are several, else 0 ).
		 */
		public static function tracking_list( $order ) {
			$list = array();
			if ( class_exists( 'wp_easycart_shipments' ) && method_exists( 'wp_easycart_shipments', 'tracking_rows' ) && wp_easycart_shipments::ready() ) {
				foreach ( (array) wp_easycart_shipments::tracking_rows( (int) $order->order_id ) as $row ) {
					/* A voided label's number no longer tracks anything. */
					if ( ! is_array( $row ) || '' === trim( (string) $row['tracking'] ) || 'voided' === (string) $row['status'] ) {
						continue;
					}
					/* A package the store marked shipped says nothing new; a carrier's word, a delivery or a problem does. */
					$said    = ( '' !== (string) $row['tracking_status'] || in_array( (string) $row['status'], array( 'delivered', 'returned', 'exception' ), true ) ) ? (string) $row['label'] : '';
					$partner = '';
					if ( '' !== (string) $row['partner'] ) {
						$partner = ( class_exists( 'wp_easycart_fulfillment' ) && method_exists( 'wp_easycart_fulfillment', 'label' ) ) ? (string) wp_easycart_fulfillment::label( (string) $row['partner'] ) : ucfirst( (string) $row['partner'] );
					}
					$list[] = array(
						'carrier'  => trim( (string) $row['carrier'] ),
						'tracking' => trim( (string) $row['tracking'] ),
						'url'      => (string) $row['url'],
						'state'    => $said,
						'partner'  => $partner,
						'package'  => 0,
					);
				}
			}
			$tracking = trim( (string) $order->tracking_number );
			if ( ! $list && '' !== $tracking ) {
				$list[] = array(
					'carrier'  => trim( (string) $order->shipping_carrier ),
					'tracking' => $tracking,
					'url'      => ( class_exists( 'wp_easycart_email_design' ) && method_exists( 'wp_easycart_email_design', 'tracking_url' ) ) ? (string) wp_easycart_email_design::tracking_url( (string) $order->shipping_carrier, $tracking ) : '',
					'state'    => '',
					'partner'  => '',
					'package'  => 0,
				);
			}
			if ( count( $list ) > 1 ) {
				foreach ( $list as $i => $track ) {
					$list[ $i ]['package'] = $i + 1;
				}
			}
			return $list;
		}

		/**
		 * A carrier's tile: its short name on its own colours ( the Shipping rates list's, filter
		 * wp_easycart_shipping_rate_carriers ), the initials of a carrier it does not know, or a box when there is no carrier.
		 *
		 * @since 6.0.2
		 * @param string $carrier Carrier name as the order or package has it ( USPS, UPS Ground, Canada Post ).
		 * @return string
		 */
		public static function carrier_tile( $carrier ) {
			$name     = trim( wp_strip_all_tags( (string) $carrier ) );
			$lower    = strtolower( $name );
			$carriers = ( class_exists( 'wp_easycart_admin_shipping_rates_v2' ) && method_exists( 'wp_easycart_admin_shipping_rates_v2', 'carriers' ) ) ? (array) wp_easycart_admin_shipping_rates_v2::carriers() : array();
			/* The Shipping rates list's tiles when it is not loaded: mark, background, text colour. */
			$fallback = array(
				'usps'       => 'USPS|#333366|#ffffff',
				'ups'        => 'UPS|#351c15|#ffb500',
				'fedex'      => 'FedEx|#4d148c|#ffffff',
				'dhl'        => 'DHL|#ffcc00|#d40511',
				'auspost'    => 'AP|#dc1928|#ffffff',
				'canadapost' => 'CP|#c8102e|#ffffff',
			);
			$words    = array(
				'usps'       => '/\busps\b|postal service/',
				'ups'        => '/\bups\b/',
				'fedex'      => '/\bfed\s?ex\b/',
				'dhl'        => '/\bdhl\b/',
				'canadapost' => '/canada\s?post/',
				'auspost'    => '/aus\s?post|australia\s?post/',
			);

			$key = '';
			foreach ( $words as $slug => $pattern ) {
				if ( '' !== $lower && preg_match( $pattern, $lower ) ) {
					$key = $slug;
					break;
				}
			}
			if ( '' === $key && '' !== $lower ) {
				foreach ( $carriers as $slug => $def ) {
					$def_name = ( is_array( $def ) && isset( $def['name'] ) ) ? strtolower( (string) $def['name'] ) : '';
					if ( sanitize_key( str_replace( ' ', '', $lower ) ) === (string) $slug || ( '' !== $def_name && false !== strpos( $lower, $def_name ) ) ) {
						$key = (string) $slug;
						break;
					}
				}
			}
			$def = array();
			if ( '' !== $key && isset( $carriers[ $key ] ) && is_array( $carriers[ $key ] ) ) {
				$def = $carriers[ $key ];
			} elseif ( '' !== $key && isset( $fallback[ $key ] ) ) {
				$def = array_combine( array( 'mark', 'bg', 'fg' ), explode( '|', $fallback[ $key ] ) );
			}
			/* 6.0.3: the carrier list's own marks ( Royal Mail and the others the order screen offers ). */
			if ( empty( $def ) && class_exists( 'wp_easycart_carriers' ) ) {
				$ck = wp_easycart_carriers::find( $name );
				if ( '' !== $ck ) {
					$entry = wp_easycart_carriers::catalog()[ $ck ];
					$key   = '' !== $key ? $key : $ck;
					$def   = array(
						'mark' => (string) $entry['mark'],
						'bg'   => (string) $entry['bg'],
						'fg'   => (string) $entry['fg'],
					);
				}
			}
			$mark = isset( $def['mark'] ) ? (string) $def['mark'] : '';
			if ( '' === $mark && '' !== $name ) {
				$bits = array_values( array_filter( (array) preg_split( '/[\s\-_]+/', $name ), 'strlen' ) );
				$mark = ( count( $bits ) >= 2 ) ? strtoupper( substr( $bits[0], 0, 1 ) . substr( $bits[1], 0, 1 ) ) : ucfirst( substr( $name, 0, 3 ) );
			}
			$bg    = isset( $def['bg'] ) ? sanitize_hex_color( (string) $def['bg'] ) : '';
			$fg    = isset( $def['fg'] ) ? sanitize_hex_color( (string) $def['fg'] ) : '';
			$style = ( $bg ? '--ecodv2-tile-bg:' . $bg . ';' : '' ) . ( $fg ? '--ecodv2-tile-fg:' . $fg . ';' : '' );
			$inner = ( '' !== $mark )
				? '<span class="ecodv2-carrier-mark' . ( strlen( $mark ) >= 5 ? ' is-long' : ( strlen( $mark ) >= 4 ? ' is-wide' : '' ) ) . '">' . esc_html( $mark ) . '</span>'
				: '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M21 8l-9-5-9 5 9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/></svg>';
			return '<span class="ecodv2-carrier-tile' . ( '' !== $key ? ' is-' . esc_attr( $key ) : '' ) . ( $bg ? ' has-colors' : '' ) . '"' . ( '' !== $style ? ' style="' . esc_attr( $style ) . '"' : '' ) . ' aria-hidden="true">' . $inner . '</span>';
		}

		/**
		 * One tracking number in the next step panel: the carrier's tile, the carrier ( and the carrier's latest word ), the
		 * number with a copy button, and Track ( the carrier's page, in a new tab ).
		 *
		 * @since 6.0.2
		 * @param array  $track tracking_list() entry.
		 * @param string $title The panel's title ( a state that repeats it is left out ).
		 * @return string
		 */
		public static function tracking_card_html( $track, $title = '' ) {
			$number = (string) $track['tracking'];
			$name   = implode( ' · ', array_filter( array( (string) $track['partner'], '' !== (string) $track['carrier'] ? (string) $track['carrier'] : __( 'Tracking number', 'wp-easycart' ) ) ) );
			if ( ! empty( $track['package'] ) ) {
				/* translators: %d: package number. */
				$name .= ' · ' . sprintf( __( 'Package %d', 'wp-easycart' ), (int) $track['package'] );
			}
			$state = ( '' !== (string) $track['state'] && (string) $track['state'] !== (string) $title ) ? (string) $track['state'] : '';
			$copy  = '<svg class="ecodv2-copy-ic" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1"/></svg>'
				. '<svg class="ecodv2-copied-ic" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12l5 5 9-10"/></svg>';
			$html  = '<div class="ecodv2-track">'
				. self::carrier_tile( $track['carrier'] )
				. '<div class="ecodv2-track-main">'
				. '<span class="ecodv2-track-name">' . esc_html( $name ) . ( '' !== $state ? '<span class="ecodv2-track-state"> · ' . esc_html( $state ) . '</span>' : '' ) . '</span>'
				. '<span class="ecodv2-track-num"><code class="ecodv2-track-code">' . esc_html( $number ) . '</code>'
				/* translators: %s: tracking number. */
				. '<button type="button" class="ecodv2-track-copy" onclick="ecodv2_copy_text( ' . esc_attr( wp_json_encode( $number ) ) . ', this ); return false;" aria-label="' . esc_attr( sprintf( __( 'Copy tracking number %s', 'wp-easycart' ), $number ) ) . '" title="' . esc_attr__( 'Copy tracking number', 'wp-easycart' ) . '" data-copied="' . esc_attr__( 'Copied', 'wp-easycart' ) . '">' . $copy . '</button></span>'
				. '</div>';
			if ( '' !== (string) $track['url'] ) {
				/* translators: %s: tracking number. */
				$html .= '<a class="ecodv2-track-open" href="' . esc_url( $track['url'] ) . '" target="_blank" rel="noopener" aria-label="' . esc_attr( sprintf( __( 'Track %s on the carrier’s site ( opens in a new tab )', 'wp-easycart' ), $number ) ) . '">' . esc_html__( 'Track', 'wp-easycart' )
					. '<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M14 4h6v6"/><path d="M20 4l-9 9"/><path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/></svg></a>';
			}
			return $html . '</div>';
		}

		/**
		 * When an order email was last sent ( the order log ), as the site's local time.
		 *
		 * @param int    $order_id Order.
		 * @param string $key      Log key: order-receipt-email, order-shipping-email.
		 * @return int 0 when never.
		 */
		public static function email_sent( $order_id, $key ) {
			global $wpdb;
			$time = $wpdb->get_var( $wpdb->prepare( 'SELECT order_log_timestamp FROM ec_order_log WHERE order_id = %d AND order_log_key = %s ORDER BY order_log_id DESC LIMIT 1', (int) $order_id, (string) $key ) );
			return $time ? self::local_time( $time ) : 0;
		}

		/* ------------------------------------------------------------------ */
		/* Lines                                                              */
		/* ------------------------------------------------------------------ */

		/**
		 * The picture an order line was bought with ( the cart stores it as a web address, older orders as a file name ).
		 *
		 * @param object $line ec_orderdetail row.
		 * @return string '' when there is none.
		 */
		public static function line_image( $line ) {
			$image = isset( $line->image1 ) ? trim( (string) $line->image1 ) : '';
			if ( '' === $image ) {
				return '';
			}
			if ( preg_match( '#^https?://#i', $image ) || 0 === strpos( $image, '//' ) ) {
				return $image;
			}
			$file = basename( $image );
			if ( defined( 'EC_PLUGIN_DATA_DIRECTORY' ) && is_file( EC_PLUGIN_DATA_DIRECTORY . '/products/pics1/' . $file ) ) {
				return plugins_url( 'wp-easycart-data/products/pics1/' . rawurlencode( $file ), EC_PLUGIN_DATA_DIRECTORY );
			}
			if ( defined( 'EC_PLUGIN_DIRECTORY' ) && is_file( EC_PLUGIN_DIRECTORY . '/products/pics1/' . $file ) ) {
				return plugins_url( 'wp-easycart/products/pics1/' . rawurlencode( $file ), EC_PLUGIN_DIRECTORY );
			}
			return '';
		}

		/**
		 * Where each line stands: shipped units, delivered units, the partner making it, or no shipping needed. Read from the
		 * order's packages ( wp_easycart_shipments::plan() ), so an order without packages only knows no shipping / to ship.
		 *
		 * @param object $order Order row.
		 * @param array  $lines ec_orderdetail rows.
		 * @return array orderdetail_id => array( group ( ship | shipped | delivered | partner | none ), shipped, delivered,
		 *               quantity, partner ( label ), packages ( numbers ) ).
		 */
		public static function line_states( $order, $lines ) {
			$states   = array();
			$packages = array();
			if ( class_exists( 'wp_easycart_shipments' ) && method_exists( 'wp_easycart_shipments', 'plan' ) && wp_easycart_shipments::ready() ) {
				$packages = (array) wp_easycart_shipments::plan( (int) $order->order_id );
			}
			$counts = array();
			foreach ( $packages as $number => $package ) {
				if ( ! empty( $package->is_return ) || empty( $package->items ) ) {
					continue;
				}
				$status = (string) $package->status;
				foreach ( (array) $package->items as $detail_id => $qty ) {
					$detail_id = (int) $detail_id;
					if ( ! isset( $counts[ $detail_id ] ) ) {
						$counts[ $detail_id ] = array(
							'shipped'   => 0,
							'delivered' => 0,
							'partner'   => '',
							'packages'  => array(),
						);
					}
					$counts[ $detail_id ]['packages'][] = (int) $number + 1;
					if ( 'delivered' === $status ) {
						$counts[ $detail_id ]['delivered'] += (int) $qty;
						$counts[ $detail_id ]['shipped']   += (int) $qty;
					} elseif ( in_array( $status, array( 'label', 'shipped', 'exception', 'returned' ), true ) ) {
						$counts[ $detail_id ]['shipped'] += (int) $qty;
					} elseif ( ! empty( $package->partner ) && class_exists( 'wp_easycart_fulfillment' ) ) {
						$counts[ $detail_id ]['partner'] = wp_easycart_fulfillment::label( (string) $package->partner );
					}
				}
			}
			foreach ( (array) $lines as $line ) {
				$id       = (int) $line->orderdetail_id;
				$quantity = max( 0, (int) $line->quantity - ( isset( $line->refunded_quantity ) ? (int) $line->refunded_quantity : 0 ) );
				$count    = isset( $counts[ $id ] ) ? $counts[ $id ] : array(
					'shipped'   => 0,
					'delivered' => 0,
					'partner'   => '',
					'packages'  => array(),
				);
				if ( empty( $line->is_shippable ) || ! empty( $line->is_download ) || ! empty( $line->is_giftcard ) ) {
					$group = 'none';
				} elseif ( $quantity > 0 && $count['delivered'] >= $quantity ) {
					$group = 'delivered';
				} elseif ( $quantity > 0 && $count['shipped'] >= $quantity ) {
					$group = 'shipped';
				} elseif ( '' !== $count['partner'] ) {
					$group = 'partner';
				} else {
					$group = 'ship';
				}
				$states[ $id ] = array(
					'group'     => $group,
					'shipped'   => (int) $count['shipped'],
					'delivered' => (int) $count['delivered'],
					'quantity'  => $quantity,
					'partner'   => (string) $count['partner'],
					'packages'  => array_values( array_unique( $count['packages'] ) ),
				);
			}
			return $states;
		}

		/**
		 * How the order was paid, in words: the gateway and the card ( "Visa •••• 4242 · Stripe" ).
		 *
		 * @param object $order Order row.
		 * @return array method ( card or way of paying ), gateway, holder.
		 */
		public static function payment_method( $order ) {
			$gateways = array(
				'stripe'         => 'Stripe',
				'stripe_connect' => 'Stripe',
				'paypal'         => 'PayPal',
				'paypal-express' => 'PayPal',
				'square'         => 'Square',
				'authorize'      => 'Authorize.net',
				'paytrace'       => 'PayTrace',
				'braintree'      => 'Braintree',
				'intuit'         => 'Intuit',
				'affirm'         => 'Affirm',
				'amazonpay'      => 'Amazon Pay',
			);
			$brands   = array(
				'visa'       => 'Visa',
				'mastercard' => 'Mastercard',
				'amex'       => 'American Express',
				'discover'   => 'Discover',
				'jcb'        => 'JCB',
				'diners'     => 'Diners Club',
			);
			$gateway  = strtolower( (string) $order->order_gateway );
			$method   = strtolower( trim( (string) $order->payment_method ) );
			$digits   = preg_replace( '/[^0-9]/', '', (string) $order->creditcard_digits );
			$label    = '';
			if ( '' !== $digits ) {
				$label = trim( ( isset( $brands[ $method ] ) ? $brands[ $method ] : __( 'Card', 'wp-easycart' ) ) . ' •••• ' . substr( $digits, -4 ) );
			} elseif ( 'manual_bill' === $method ) {
				$label = __( 'Pay by invoice', 'wp-easycart' );
			} elseif ( in_array( $method, array( 'third_party', 'paypal' ), true ) ) {
				$label = 'PayPal';
			} elseif ( '' !== $method && ! isset( $brands[ $method ] ) && 'credit_card' !== $method ) {
				$label = ucwords( str_replace( array( '-', '_' ), ' ', $method ) );
			}
			return array(
				'method'  => $label,
				'gateway' => isset( $gateways[ $gateway ] ) ? $gateways[ $gateway ] : ( '' !== $gateway ? ucwords( str_replace( array( '-', '_' ), ' ', $gateway ) ) : '' ),
				'holder'  => trim( (string) $order->card_holder_name ),
			);
		}

		/**
		 * Is the billing address the shipping address?
		 *
		 * @param object $order Order row.
		 * @return bool
		 */
		public static function same_addresses( $order ) {
			foreach ( array( 'first_name', 'last_name', 'company_name', 'address_line_1', 'address_line_2', 'city', 'state', 'zip', 'country' ) as $part ) {
				$billing  = isset( $order->{'billing_' . $part} ) ? strtolower( trim( (string) $order->{'billing_' . $part} ) ) : '';
				$shipping = isset( $order->{'shipping_' . $part} ) ? strtolower( trim( (string) $order->{'shipping_' . $part} ) ) : '';
				if ( $billing !== $shipping ) {
					return false;
				}
			}
			return '' !== trim( (string) $order->shipping_address_line_1 );
		}

		/**
		 * Does an older WP EasyCart PRO print its own address and contact forms on the order screen? ( PRO 6.0.2 leaves them to
		 * WP EasyCart. ) The screen then leaves them out, so no field id is printed twice.
		 *
		 * @return bool
		 */
		public static function pro_prints_edit_forms() {
			if ( ! function_exists( 'wp_easycart_admin_orders_pro' ) ) {
				return false;
			}
			return false !== has_action( 'wp_easycart_admin_order_details_billing_content_end', array( wp_easycart_admin_orders_pro(), 'print_billing_form' ) );
		}

		/* ------------------------------------------------------------------ */
		/* AJAX                                                               */
		/* ------------------------------------------------------------------ */

		/**
		 * The order an AJAX request names ( ends the request when there is none ).
		 *
		 * @return object ec_order row with is_approved.
		 */
		private static function request_order() {
			global $wpdb;
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- every caller ran ecv2_order_screen_guard() first.
			$order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
			$order    = $order_id ? $wpdb->get_row( $wpdb->prepare( 'SELECT ec_order.*, ec_orderstatus.is_approved FROM ec_order LEFT JOIN ec_orderstatus ON ec_orderstatus.status_id = ec_order.orderstatus_id WHERE ec_order.order_id = %d', $order_id ) ) : null;
			if ( ! $order ) {
				wp_send_json_error( array( 'message' => __( 'The order could not be found.', 'wp-easycart' ) ) );
			}
			return $order;
		}

		/**
		 * AJAX ecv2_order_pinned_note: the note pinned to the top of the order for the team ( ec_order.order_notes ). Posts
		 * order_id and note ( '' unpins ). Saves the note only ( the older path posted the weight, gift card and coupon again ).
		 */
		public static function ajax_pinned_note() {
			ecv2_order_screen_guard();
			global $wpdb;
			$order = self::request_order();
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- ecv2_order_screen_guard() above.
			$note = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
			$wpdb->query( $wpdb->prepare( 'UPDATE ec_order SET order_notes = %s, last_updated = NOW() WHERE order_id = %d', $note, (int) $order->order_id ) );
			$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log( order_id, order_log_key ) VALUES( %d, "order-note-updated" )', (int) $order->order_id ) );
			$log_id = (int) $wpdb->insert_id;
			if ( $log_id ) {
				$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "order_notes", %s )', $log_id, (int) $order->order_id, $note ) );
			}
			do_action( 'wpeasycart_order_updated', (int) $order->order_id );
			wp_send_json_success(
				array(
					'note'    => $note,
					'message' => '' === $note ? __( 'Note unpinned.', 'wp-easycart' ) : __( 'Note pinned to the order.', 'wp-easycart' ),
				)
			);
		}

		/**
		 * AJAX ecv2_order_screen_fulfill: the Fulfill flow for an order without packages. Posts order_id, carrier,
		 * tracking_number, mark_shipped ( 1 sets the order's shipped status ) and notify ( 1 sends the shipped email ).
		 * Orders with packages save through ecv2_order_packages_track_all.
		 */
		public static function ajax_fulfill() {
			ecv2_order_screen_guard();
			$order = self::request_order();
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- ecv2_order_screen_guard() above.
			$result = self::ship(
				(int) $order->order_id,
				array(
					'carrier'  => isset( $_POST['carrier'] ) ? sanitize_text_field( wp_unslash( $_POST['carrier'] ) ) : '',
					'tracking' => isset( $_POST['tracking_number'] ) ? sanitize_text_field( wp_unslash( $_POST['tracking_number'] ) ) : '',
					'mark'     => ! empty( $_POST['mark_shipped'] ),
					'notify'   => ! empty( $_POST['notify'] ),
				)
			);
			// phpcs:enable WordPress.Security.NonceVerification.Missing
			if ( is_wp_error( $result ) ) {
				wp_send_json_error(
					array(
						'message' => $result->get_error_message(),
						'field'   => ( 'tracking' === $result->get_error_code() ) ? 'tracking' : '',
					)
				);
			}
			wp_send_json_success(
				array(
					'message' => $result['message'],
					'reload'  => true,
					'unpaid'  => ! empty( $result['unpaid'] ), /* 6.0.2 bug round 14: the page waits longer, so the message is read */
				)
			);
		}

		/**
		 * Ship an order: the one way the order screen's Ship order and Fulfill window, and the orders list's Fulfill, save a
		 * shipment. The tracking number goes on the order's one package waiting to ship ( wp_easycart_order_add_shipment() ),
		 * or on the order itself when it has no package; the order moves to its shipped status through
		 * wp_easycart_admin_orders::set_order_status() ( stock, the status hooks and the activity log ); the shipped email
		 * goes out when asked. Several packages waiting take a tracking number each ( the order screen's Fulfill window ), so a
		 * single number is refused for them. While a fulfillment partner still has items to ship the order is not marked
		 * shipped, and neither is an order that is not paid, or was refunded, partly refunded or canceled.
		 *
		 * @since 6.0.2
		 * @param int   $order_id Order.
		 * @param array $args     carrier, tracking, mark ( set the shipped status, default true ), notify ( send the shipped
		 *                        email, default false ).
		 * @return array|WP_Error message, status_changed, emailed, waiting ( the store's packages still without a tracking
		 *                        number ), partners ( fulfillment partners still shipping, slug => name ), unpaid ( 6.0.2 bug
		 *                        round 14: the order is not paid yet, so it stays unpaid and not shipped ). WP_Error codes:
		 *                        order, tracking ( neither a tracking number nor mark ), packages ( data: count, url ), status
		 *                        ( no tracking number, and the order cannot be marked shipped ).
		 */
		public static function ship( $order_id, $args = array() ) {
			global $wpdb;
			$args     = wp_parse_args(
				$args,
				array(
					'carrier'  => '',
					'tracking' => '',
					'mark'     => true,
					'notify'   => false,
				)
			);
			$order_id = (int) $order_id;
			$order    = $wpdb->get_row( $wpdb->prepare( 'SELECT ec_order.*, ec_orderstatus.is_approved FROM ec_order LEFT JOIN ec_orderstatus ON ec_orderstatus.status_id = ec_order.orderstatus_id WHERE ec_order.order_id = %d', $order_id ) );
			if ( ! $order ) {
				return new WP_Error( 'order', __( 'The order could not be found.', 'wp-easycart' ) );
			}
			$carrier  = class_exists( 'wp_easycart_carriers' ) ? wp_easycart_carriers::clean( $args['carrier'] ) : trim( sanitize_text_field( (string) $args['carrier'] ) );
			if ( class_exists( 'wp_easycart_carriers' ) ) {
				wp_easycart_carriers::saved( $carrier );
			}
			$tracking = trim( sanitize_text_field( (string) $args['tracking'] ) );
			$mark     = (bool) $args['mark'];
			$notify   = (bool) $args['notify'];
			if ( '' === $tracking && ! $mark ) {
				return new WP_Error( 'tracking', __( 'Enter the tracking number, or tick Mark as shipped.', 'wp-easycart' ) );
			}
			$before = (int) $order->orderstatus_id;
			/* Only a paid order that was not refunded, partly refunded or canceled is marked shipped ( wp_easycart_shipments::record()'s
			   rule, so the answer is the same with a tracking number or without ): marking it would take its stock and record it paid. */
			$closed = empty( $order->is_approved ) || in_array( $before, array( 16, 17, 19 ), true );
			/* 6.0.2 bug round 14: not paid yet ( a manual payment ): the tracking is kept and the answer says the order still waits
			   for its payment, so the store does not take a shipped order for a finished one. */
			$unpaid = empty( $order->is_approved ) && ! in_array( $before, array( 16, 19 ), true );
			if ( '' === $tracking && $closed ) {
				return new WP_Error( 'status', $unpaid ? __( 'This order is not paid yet, so it cannot be marked shipped. Mark it paid first.', 'wp-easycart' ) : __( 'This order was not marked shipped: it is not paid, or it was refunded or canceled.', 'wp-easycart' ) );
			}
			$markable = ! $closed && ! ( class_exists( 'wp_easycart_shipments' ) && wp_easycart_shipments::is_fulfilled_status( $before ) );
			$open     = array();
			foreach ( self::label_rows( $order ) as $row ) {
				if ( empty( $row['done'] ) ) {
					$open[] = $row;
				}
			}
			if ( '' !== $tracking && count( $open ) > 1 ) {
				return new WP_Error(
					'packages',
					/* translators: %d: number of packages. */
					sprintf( _n( 'This order has %d package to ship.', 'This order has %d packages to ship. Add each package’s tracking number on the order.', count( $open ), 'wp-easycart' ), count( $open ) ),
					array(
						'count' => count( $open ),
						'url'   => add_query_arg( 'ecodv2_open', 'tracking', self::order_url( $order_id ) ),
					)
				);
			}
			$emailed = false;
			if ( '' !== $tracking && 1 === count( $open ) && function_exists( 'wp_easycart_order_add_shipment' ) ) {
				// The order's one package takes the number ( a planned package is saved first ), and is marked shipped once
				// nothing else waits ( wp_easycart_shipments::record() ).
				$done = wp_easycart_order_add_shipment(
					$order_id,
					array(
						'shipment_id'     => (int) $open[0]['shipment_id'],
						'carrier'         => $carrier,
						'tracking_number' => $tracking,
						'mark_shipped'    => $mark,
						'notify'          => $notify && '' !== trim( (string) $order->user_email ),
					)
				);
				if ( is_wp_error( $done ) ) {
					return $done;
				}
				$emailed = $notify && '' !== trim( (string) $order->user_email );
			} else {
				if ( '' !== $tracking ) {
					self::record_tracking( $order, $carrier, $tracking );
				}
				if ( $mark && $markable && ! self::partners_open( $order_id ) ) {
					$shipped = self::shipped_status_id();
					if ( $shipped && $before !== $shipped && function_exists( 'wp_easycart_admin_orders' ) ) {
						wp_easycart_admin_orders()->set_order_status( $order_id, $shipped );
					}
				}
				do_action( 'wpeasycart_order_updated', $order_id );
				if ( $notify && '' !== trim( (string) $order->user_email ) && function_exists( 'wp_easycart_admin_orders' ) && method_exists( wp_easycart_admin_orders(), 'send_customer_shipping_email' ) ) {
					$fresh = $wpdb->get_row( $wpdb->prepare( 'SELECT tracking_number, shipping_carrier FROM ec_order WHERE order_id = %d', $order_id ) );
					wp_easycart_admin_orders()->send_customer_shipping_email( $order_id, $fresh ? (string) $fresh->tracking_number : $tracking, $fresh ? (string) $fresh->shipping_carrier : $carrier );
					$emailed = true;
				}
			}
			$after          = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT orderstatus_id FROM ec_order WHERE order_id = %d', $order_id ) );
			$status_changed = ( $after !== $before );
			$waiting        = ( class_exists( 'wp_easycart_shipments' ) && wp_easycart_shipments::ready() ) ? wp_easycart_shipments::waiting_packages( $order_id, '' ) : 0;
			$partners       = self::partners_open( $order_id );
			if ( $status_changed ) {
				$message = $emailed ? __( 'Order marked as shipped and the customer was emailed.', 'wp-easycart' ) : __( 'Order marked as shipped.', 'wp-easycart' );
			} elseif ( '' !== $tracking ) {
				$message = $emailed ? __( 'Tracking saved and the customer was emailed.', 'wp-easycart' ) : __( 'Tracking saved.', 'wp-easycart' );
			} else {
				$message = $emailed ? __( 'The customer was emailed.', 'wp-easycart' ) : __( 'Saved.', 'wp-easycart' );
			}
			$unpaid = $unpaid && ! $status_changed;
			if ( $unpaid ) {
				$message .= ' ' . ( $mark ? __( 'The order is not paid yet, so it was not marked shipped. Mark it paid to finish it.', 'wp-easycart' ) : __( 'The order is not paid yet. Mark it paid to finish it.', 'wp-easycart' ) );
			} elseif ( $mark && ! $status_changed && $closed ) {
				$message .= ' ' . __( 'The order was not marked shipped: it is not paid, or it was refunded or canceled.', 'wp-easycart' );
			} elseif ( $mark && ! $status_changed && $partners ) {
				/* translators: %s: fulfillment partners, e.g. Printful. */
				$message .= ' ' . sprintf( __( '%s still has items to ship; the order is marked shipped once they ship.', 'wp-easycart' ), implode( ', ', $partners ) );
			} elseif ( $mark && ! $status_changed && $waiting > 0 && '' !== $tracking ) {
				/* translators: %d: number of packages. */
				$message .= ' ' . sprintf( _n( '%d package still needs a tracking number; the order is marked shipped once it has one.', '%d packages still need a tracking number; the order is marked shipped once they have one.', $waiting, 'wp-easycart' ), $waiting );
			}
			return array(
				'message'        => $message,
				'status_changed' => $status_changed,
				'emailed'        => $emailed,
				'waiting'        => $waiting,
				'partners'       => $partners,
				'unpaid'         => $unpaid,
			);
		}

		/**
		 * Change the order's tracking number or carrier, as an edit ( the orders list's Quick edit, WP EasyCart PRO's
		 * spreadsheet ). A first tracking number is a shipment: it is recorded on the order's first package waiting to ship
		 * ( wp_easycart_order_add_shipment(), which does not change the status ). A corrected number or carrier also corrects
		 * the store's own package that carried the old number, so the customer's emails and My Account show the right one.
		 * wpeasycart_tracking_info_update fires and the activity log gets the change.
		 *
		 * @since 6.0.2
		 * @param int    $order_id Order.
		 * @param string $carrier  Carrier.
		 * @param string $tracking Tracking number ( '' clears the order's ).
		 * @return bool Something changed.
		 */
		public static function set_tracking( $order_id, $carrier, $tracking ) {
			global $wpdb;
			$order = $wpdb->get_row( $wpdb->prepare( 'SELECT ec_order.*, ec_orderstatus.is_approved FROM ec_order LEFT JOIN ec_orderstatus ON ec_orderstatus.status_id = ec_order.orderstatus_id WHERE ec_order.order_id = %d', (int) $order_id ) );
			if ( ! $order ) {
				return false;
			}
			$carrier  = class_exists( 'wp_easycart_carriers' ) ? wp_easycart_carriers::clean( $carrier ) : trim( sanitize_text_field( (string) $carrier ) );
			if ( class_exists( 'wp_easycart_carriers' ) ) {
				wp_easycart_carriers::saved( $carrier );
			}
			$tracking = trim( sanitize_text_field( (string) $tracking ) );
			$old      = trim( (string) $order->tracking_number );
			if ( $old === $tracking && trim( (string) $order->shipping_carrier ) === $carrier ) {
				return false;
			}
			if ( '' === $old && '' !== $tracking ) {
				self::record_tracking( $order, $carrier, $tracking );
			} else {
				if ( '' !== $old && class_exists( 'wp_easycart_shipments' ) && wp_easycart_shipments::ready() ) {
					foreach ( wp_easycart_shipments::for_order( (int) $order->order_id, array( 'returns' => false ) ) as $package ) {
						/* Only a package the store recorded by hand: a label extension's package keeps what the carrier said. */
						if ( trim( (string) $package->tracking_number ) === $old && '' === (string) $package->provider && '' === (string) $package->partner ) {
							wp_easycart_shipments::update(
								(int) $package->shipment_id,
								array(
									'tracking_number' => $tracking,
									'carrier'         => $carrier,
								)
							);
							break;
						}
					}
				}
				self::write_tracking( $order, $carrier, $tracking );
			}
			do_action( 'wpeasycart_order_updated', (int) $order->order_id );
			return true;
		}

		/**
		 * A tracking number for an order being shipped: recorded as a shipment when packages are kept
		 * ( wp_easycart_order_add_shipment(), without changing the status or sending email ), else written on the order.
		 *
		 * @param object $order    Order row.
		 * @param string $carrier  Carrier.
		 * @param string $tracking Tracking number.
		 */
		private static function record_tracking( $order, $carrier, $tracking ) {
			if ( function_exists( 'wp_easycart_order_add_shipment' ) && class_exists( 'wp_easycart_shipments' ) && wp_easycart_shipments::ready() ) {
				$done = wp_easycart_order_add_shipment(
					(int) $order->order_id,
					array(
						'carrier'         => $carrier,
						'tracking_number' => $tracking,
						'mark_shipped'    => false,
						'notify'          => false,
					)
				);
				if ( ! is_wp_error( $done ) ) {
					global $wpdb;
					// record() keeps the order's own number when that one is already on a package: an order being shipped, or
					// whose number was blank, shows the new one.
					$now = trim( (string) $wpdb->get_var( $wpdb->prepare( 'SELECT tracking_number FROM ec_order WHERE order_id = %d', (int) $order->order_id ) ) );
					if ( $now !== $tracking && '' === trim( (string) $order->tracking_number ) ) {
						self::write_tracking( $order, $carrier, $tracking );
					}
					return;
				}
			}
			self::write_tracking( $order, $carrier, $tracking );
		}

		/**
		 * The order's own tracking columns, wpeasycart_tracking_info_update and the activity log entry.
		 *
		 * @param object $order    Order row.
		 * @param string $carrier  Carrier.
		 * @param string $tracking Tracking number.
		 */
		private static function write_tracking( $order, $carrier, $tracking ) {
			global $wpdb;
			$order_id = (int) $order->order_id;
			do_action( 'wpeasycart_tracking_info_update', $order_id, (int) $order->use_expedited_shipping, (string) $order->shipping_method, $carrier, $tracking );
			$wpdb->query( $wpdb->prepare( 'UPDATE ec_order SET shipping_carrier = %s, tracking_number = %s, last_updated = NOW() WHERE order_id = %d', $carrier, $tracking, $order_id ) );
			$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log( order_id, order_log_key ) VALUES( %d, "order-shipping-method-update" )', $order_id ) );
			$log_id = (int) $wpdb->insert_id;
			if ( $log_id <= 0 ) {
				return;
			}
			foreach ( array(
				'use_expedited_shipping' => (int) $order->use_expedited_shipping,
				'shipping_method'        => (string) $order->shipping_method,
				'shipping_carrier'       => $carrier,
				'tracking_number'        => $tracking,
			) as $key => $value ) {
				$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, %s, %s )', $log_id, $order_id, $key, (string) $value ) );
			}
		}

		/**
		 * Fulfillment partners that still have items of this order to ship.
		 *
		 * @param int $order_id Order.
		 * @return array slug => name.
		 */
		private static function partners_open( $order_id ) {
			if ( ! class_exists( 'wp_easycart_fulfillment' ) || ! wp_easycart_fulfillment::ready() ) {
				return array();
			}
			return (array) wp_easycart_fulfillment::open_providers( wp_easycart_fulfillment::state( (int) $order_id ) );
		}

		/**
		 * Mark a local pickup order collected: Order Picked Up, through wp_easycart_admin_orders::set_order_status(). Nothing
		 * was shipped, so no carrier, tracking number or shipped email. The orders list's Mark picked up and the order
		 * screen's next step use it; WP EasyCart PRO 6.0.2's bulk Fulfill too.
		 *
		 * @since 6.0.2
		 * @param int $order_id Order.
		 * @return array|WP_Error set_order_status() answer ( changed, previous ).
		 */
		public static function mark_picked_up( $order_id ) {
			if ( ! function_exists( 'wp_easycart_admin_orders' ) ) {
				return new WP_Error( 'order', __( 'The order could not be changed. Reload the page and try again.', 'wp-easycart' ) );
			}
			$picked_up = class_exists( 'wp_easycart_admin_order_table' ) ? wp_easycart_admin_order_table::STATUS_PICKED_UP : 18;
			return wp_easycart_admin_orders()->set_order_status( (int) $order_id, $picked_up );
		}

		/**
		 * AJAX ecv2_order_screen_find: orders for the order screen's Find ( Ctrl K ). Posts q: an order number ( # optional ),
		 * or part of a name, company or email address. Answers up to 8 orders, the newest first ( an exact number first ).
		 */
		public static function ajax_find() {
			ecv2_order_screen_guard();
			global $wpdb;
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- ecv2_order_screen_guard() above.
			$q = isset( $_POST['q'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['q'] ) ) ) : '';
			$q = trim( ltrim( $q, '#' ) );
			if ( '' === $q || strlen( $q ) > 100 ) {
				wp_send_json_success( array( 'orders' => array() ) );
			}
			$select = 'SELECT ec_order.order_id, ec_order.order_date, ec_order.grand_total, ec_order.user_email, ec_order.billing_first_name, ec_order.billing_last_name, ec_order.shipping_first_name, ec_order.shipping_last_name, ec_order.billing_company_name, ec_orderstatus.order_status, ec_orderstatus.color_code FROM ec_order LEFT JOIN ec_orderstatus ON ( ec_orderstatus.status_id = ec_order.orderstatus_id )';
			$rows   = array();
			if ( ctype_digit( $q ) ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $select is a fixed string.
				$rows = (array) $wpdb->get_results( $wpdb->prepare( "{$select} WHERE ec_order.order_id = %d OR CAST( ec_order.order_id AS CHAR ) LIKE %s ORDER BY ( ec_order.order_id = %d ) DESC, ec_order.order_id DESC LIMIT 8", (int) $q, $wpdb->esc_like( $q ) . '%', (int) $q ) );
			}
			if ( count( $rows ) < 8 ) {
				$like = '%' . $wpdb->esc_like( $q ) . '%';
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $select is a fixed string.
				$more = (array) $wpdb->get_results( $wpdb->prepare( "{$select} WHERE CONCAT( ec_order.billing_first_name, ' ', ec_order.billing_last_name ) LIKE %s OR CONCAT( ec_order.shipping_first_name, ' ', ec_order.shipping_last_name ) LIKE %s OR ec_order.user_email LIKE %s OR ec_order.billing_company_name LIKE %s ORDER BY ec_order.order_id DESC LIMIT 8", $like, $like, $like, $like ) );
				$have = array();
				foreach ( $rows as $row ) {
					$have[ (int) $row->order_id ] = true;
				}
				foreach ( $more as $row ) {
					if ( count( $rows ) < 8 && ! isset( $have[ (int) $row->order_id ] ) ) {
						$rows[] = $row;
					}
				}
			}
			$orders = array();
			foreach ( $rows as $row ) {
				$name = trim( (string) $row->billing_first_name . ' ' . (string) $row->billing_last_name );
				$name = '' !== $name ? $name : trim( (string) $row->shipping_first_name . ' ' . (string) $row->shipping_last_name );
				$name = '' !== $name ? $name : trim( (string) $row->billing_company_name );
				$when     = self::local_time( $row->order_date );
				$orders[] = array(
					'id'     => (int) $row->order_id,
					'name'   => wp_strip_all_tags( wp_unslash( $name ) ),
					'email'  => (string) $row->user_email,
					'total'  => self::money( $row->grand_total ),
					'status' => wp_strip_all_tags( wp_unslash( (string) $row->order_status ) ),
					'color'  => sanitize_hex_color( (string) $row->color_code ) ? (string) $row->color_code : '',
					'date'   => $when ? date_i18n( get_option( 'date_format', 'M j, Y' ), $when ) : '',
					'url'    => self::order_url( (int) $row->order_id ),
				);
			}
			wp_send_json_success( array( 'orders' => $orders ) );
		}

		/**
		 * Are Packed ticks kept on the order ( the 6.0.2 database update added ec_orderdetail.packed_quantity )? Until it has run,
		 * the order screen keeps them in the browser.
		 *
		 * @return bool
		 */
		public static function packing_ready() {
			return (int) get_option( 'ec_option_db_new_version' ) >= self::PACK_DB_VERSION;
		}

		/**
		 * Is a line packed? Its packed units cover what the customer still gets ( refunded units left out ).
		 *
		 * @param object $line ec_orderdetail row.
		 * @return bool
		 */
		public static function line_packed( $line ) {
			$quantity = max( 0, (int) $line->quantity - ( isset( $line->refunded_quantity ) ? (int) $line->refunded_quantity : 0 ) );
			return isset( $line->packed_quantity ) && $quantity > 0 && (int) $line->packed_quantity >= $quantity;
		}

		/**
		 * AJAX ecv2_order_screen_pack: tick a line packed, or untick it, on the order ( everyone packing it sees the same ).
		 * Posts order_id, orderdetail_id and packed ( 1 | 0 ). Answers packed ( bool ).
		 */
		public static function ajax_pack() {
			ecv2_order_screen_guard();
			global $wpdb;
			if ( ! self::packing_ready() ) {
				wp_send_json_error( array( 'message' => __( 'Run the WP EasyCart database update to keep packed items on the order.', 'wp-easycart' ) ) );
			}
			$order = self::request_order();
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- ecv2_order_screen_guard() above.
			$detail_id = isset( $_POST['orderdetail_id'] ) ? absint( wp_unslash( $_POST['orderdetail_id'] ) ) : 0;
			$packed    = ! empty( $_POST['packed'] );
			// phpcs:enable WordPress.Security.NonceVerification.Missing
			$line = $detail_id ? $wpdb->get_row( $wpdb->prepare( 'SELECT orderdetail_id, quantity, refunded_quantity FROM ec_orderdetail WHERE orderdetail_id = %d AND order_id = %d', $detail_id, (int) $order->order_id ) ) : null;
			if ( ! $line ) {
				wp_send_json_error( array( 'message' => __( 'That item is not on this order. Reload the order and try again.', 'wp-easycart' ) ) );
			}
			$units = $packed ? max( 0, (int) $line->quantity - (int) $line->refunded_quantity ) : 0;
			$wpdb->query( $wpdb->prepare( 'UPDATE ec_orderdetail SET packed_quantity = %d WHERE orderdetail_id = %d', $units, $detail_id ) );
			wp_send_json_success( array( 'packed' => $units > 0 ) );
		}

		/**
		 * AJAX ecv2_order_screen_refresh: the parts of the order screen that follow its packages and emails, as they read now
		 * ( refresh_reply() ). Posts order_id. Reads only.
		 *
		 * @since 6.0.2
		 */
		public static function ajax_refresh() {
			ecv2_order_screen_guard();
			$order = self::request_order();
			wp_send_json_success( self::refresh_reply( $order ) );
		}

		/**
		 * AJAX ecv2_order_screen_delivered: the store marks a shipped order delivered ( wp_easycart_shipments::mark_order_delivered():
		 * its packages on their way are marked delivered and the order moves to the Delivered status; a partly refunded order
		 * keeps its status ). Posts order_id. Answers the message and refresh_reply() for the order as it is now.
		 *
		 * @since 6.0.2
		 */
		public static function ajax_delivered() {
			ecv2_order_screen_guard();
			global $wpdb;
			$order        = self::request_order();
			$delivered_id = class_exists( 'wp_easycart_shipments' ) ? wp_easycart_shipments::delivered_status_id_if_set() : 0;
			if ( $delivered_id > 0 && $delivered_id === (int) $order->orderstatus_id ) {
				/* A second click, or a page from before: nothing to do. */
				$reply            = self::refresh_reply( $order );
				$reply['message'] = __( 'This order is already delivered.', 'wp-easycart' );
				wp_send_json_success( $reply );
			}
			if ( ! self::deliverable( $order ) ) {
				wp_send_json_error( array( 'message' => __( 'Only a paid order that has shipped, and was not refunded, canceled or picked up, can be marked delivered.', 'wp-easycart' ) ) );
			}
			$done = wp_easycart_shipments::mark_order_delivered( (int) $order->order_id );
			if ( is_wp_error( $done ) ) {
				wp_send_json_error( array( 'message' => $done->get_error_message() ) );
			}
			$fresh = $wpdb->get_row( $wpdb->prepare( 'SELECT ec_order.*, ec_orderstatus.is_approved FROM ec_order LEFT JOIN ec_orderstatus ON ec_orderstatus.status_id = ec_order.orderstatus_id WHERE ec_order.order_id = %d', (int) $order->order_id ) );
			$reply = self::refresh_reply( $fresh ? $fresh : $order );
			if ( $done['moved'] ) {
				/* translators: %s: order status name, e.g. Order Delivered. */
				$reply['message'] = '' !== $reply['status_label'] ? sprintf( __( 'Marked delivered: the order is now %s.', 'wp-easycart' ), $reply['status_label'] ) : __( 'Marked delivered.', 'wp-easycart' );
			} elseif ( ! empty( $done['kept'] ) && $done['packages'] > 0 ) {
				/* 6.0.2 bug round 8: a partly refunded order keeps its status. */
				/* translators: %s: order status name, e.g. Partial Refund. */
				$reply['message'] = '' !== $reply['status_label'] ? sprintf( __( 'Marked delivered. The order keeps its %s status.', 'wp-easycart' ), $reply['status_label'] ) : __( 'Marked delivered.', 'wp-easycart' );
			} elseif ( $done['packages'] > 0 ) {
				$reply['message'] = __( 'The packages are marked delivered.', 'wp-easycart' );
			} else {
				$reply['message'] = __( 'This order is already delivered.', 'wp-easycart' );
			}
			wp_send_json_success( $reply );
		}

		/**
		 * AJAX ecv2_order_screen_stock: the Stock not taken notice ( flags() ). Posts order_id and do: take ( take the stock now
		 * through wp_easycart_order_pay::take_stock(), as if it had been taken when the order was paid ) or mark ( the store
		 * already corrected the stock by hand: the lines are marked adjusted, the stock stays as it is ). Either writes an
		 * activity log entry. Answers message and refresh_reply() ( the notice is gone from flags_html ).
		 *
		 * @since 6.0.2 bug round 14
		 */
		public static function ajax_stock() {
			ecv2_order_screen_guard();
			global $wpdb;
			$order = self::request_order();
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- ecv2_order_screen_guard() above.
			$do = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
			if ( ! in_array( $do, array( 'take', 'mark' ), true ) ) {
				wp_send_json_error( array( 'message' => __( 'The stock could not be changed. Reload the page and try again.', 'wp-easycart' ) ) );
			}
			$order_id = (int) $order->order_id;
			$owed     = self::stock_owed( $order );
			$done     = 0;
			if ( $owed['units'] > 0 && 'take' === $do && class_exists( 'wp_easycart_order_pay' ) ) {
				/* translators: %d: order number. */
				$done = wp_easycart_order_pay::take_stock( $order_id, sprintf( __( 'Order #%d: stock taken on the order screen', 'wp-easycart' ), $order_id ) );
			} elseif ( $owed['units'] > 0 && 'mark' === $do ) {
				$done = (int) $wpdb->query( $wpdb->prepare( 'UPDATE ec_orderdetail SET stock_adjusted = 1 WHERE order_id = %d AND stock_adjusted = 0', $order_id ) );
			}
			if ( $done > 0 ) {
				self::stock_log( $order_id, ( 'take' === $do ) ? 'order-stock-taken' : 'order-stock-corrected', $owed['units'] );
				/* translators: %d: number of items. */
				$message = ( 'take' === $do ) ? sprintf( _n( 'Stock taken for %d item.', 'Stock taken for %d items.', $owed['units'], 'wp-easycart' ), $owed['units'] ) : __( 'Marked as corrected. The stock was left as it is.', 'wp-easycart' );
			} else {
				/* Another click or another person was first. */
				$message = __( 'Nothing to change: this order’s stock was already taken or marked corrected.', 'wp-easycart' );
			}
			$fresh            = $wpdb->get_row( $wpdb->prepare( 'SELECT ec_order.*, ec_orderstatus.is_approved FROM ec_order LEFT JOIN ec_orderstatus ON ec_orderstatus.status_id = ec_order.orderstatus_id WHERE ec_order.order_id = %d', $order_id ) );
			$reply            = self::refresh_reply( $fresh ? $fresh : $order );
			$reply['message'] = $message;
			$reply['changed'] = $done > 0;
			wp_send_json_success( $reply );
		}

		/**
		 * The activity log entry for Take stock now ( order-stock-taken ) or Already corrected ( order-stock-corrected ).
		 *
		 * @since 6.0.2 bug round 14
		 * @param int    $order_id Order.
		 * @param string $key      Log key.
		 * @param int    $units    Items whose stock it was.
		 */
		private static function stock_log( $order_id, $key, $units ) {
			$meta = array(
				'items'   => (string) (int) $units,
				'user_id' => (string) get_current_user_id(),
			);
			if ( function_exists( 'wp_easycart_order_log' ) ) {
				wp_easycart_order_log( (int) $order_id, $key, $meta );
				return;
			}
			global $wpdb;
			$wpdb->insert(
				'ec_order_log',
				array(
					'order_id'      => (int) $order_id,
					'order_log_key' => $key,
				)
			);
			$log_id = (int) $wpdb->insert_id;
			foreach ( $log_id > 0 ? $meta : array() as $meta_key => $meta_value ) {
				$wpdb->insert(
					'ec_order_log_meta',
					array(
						'order_log_id'         => $log_id,
						'order_id'             => (int) $order_id,
						'order_log_meta_key'   => $meta_key,
						'order_log_meta_value' => $meta_value,
					)
				);
			}
		}

		/**
		 * Filter wp_easycart_admin_order_history_entry ( WP EasyCart PRO's order timeline ): Take stock now and Already
		 * corrected.
		 *
		 * @since 6.0.2 bug round 14
		 * @param array|null $entry Entry so far.
		 * @param string     $key   Order log key.
		 * @param array      $meta  Meta by key.
		 * @return array|null
		 */
		public static function history_entry( $entry, $key = '', $meta = array() ) {
			if ( null !== $entry || ! in_array( (string) $key, array( 'order-stock-taken', 'order-stock-corrected' ), true ) ) {
				return $entry;
			}
			$items = isset( $meta['items'] ) ? (int) $meta['items'] : 0;
			$user  = ( ! empty( $meta['user_id'] ) && function_exists( 'get_userdata' ) ) ? get_userdata( (int) $meta['user_id'] ) : false;
			return array(
				/* translators: %d: number of items. */
				'title'    => ( 'order-stock-taken' === $key ) ? sprintf( _n( 'Stock taken for %d item on the order screen', 'Stock taken for %d items on the order screen', $items, 'wp-easycart' ), $items ) : sprintf( _n( 'Stock for %d item marked as already corrected', 'Stock for %d items marked as already corrected', $items, 'wp-easycart' ), $items ),
				/* translators: %s: who did it. */
				'subtitle' => $user ? esc_html( sprintf( __( 'By %s', 'wp-easycart' ), (string) $user->display_name ) ) : '',
				'icon'     => 'dashicons-archive',
				'category' => 'fulfillment',
			);
		}

		/**
		 * The status an order gets when it ships: WP EasyCart's Order Shipped ( 2 ), or the one a store chose instead.
		 *
		 * @return int
		 */
		public static function shipped_status_id() {
			/**
			 * The status the order screen's Fulfill sets ( Mark as shipped ).
			 *
			 * @since 6.0.2
			 * @param int $status_id Order status.
			 */
			return (int) apply_filters( 'wp_easycart_order_shipped_status', 2 );
		}

		/**
		 * AJAX ec_admin_ajax_save_order_billing_address ( free from 6.0.2 ).
		 */
		public static function ajax_save_billing() {
			ecv2_order_screen_guard();
			self::save_address( 'billing' );
			wp_send_json_success( array( 'message' => __( 'Billing address saved.', 'wp-easycart' ) ) );
		}

		/**
		 * AJAX ec_admin_ajax_save_order_shipping_address ( free from 6.0.2 ).
		 */
		public static function ajax_save_shipping() {
			ecv2_order_screen_guard();
			self::save_address( 'shipping' );
			wp_send_json_success( array( 'message' => __( 'Shipping address saved.', 'wp-easycart' ) ) );
		}

		/**
		 * Save one of the order's addresses, as WP EasyCart PRO 6.0.1 did ( the same hook, columns and order log ).
		 *
		 * @param string $type billing | shipping.
		 */
		private static function save_address( $type ) {
			global $wpdb;
			$order    = self::request_order();
			$order_id = (int) $order->order_id;
			$parts    = array( 'first_name', 'last_name', 'company_name', 'address_line_1', 'address_line_2', 'city', 'state', 'country', 'zip', 'phone' );
			$values   = array();
			foreach ( $parts as $part ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- the callers ran ecv2_order_screen_guard().
				$values[ $part ] = isset( $_POST[ $type . '_' . $part ] ) ? sanitize_text_field( wp_unslash( $_POST[ $type . '_' . $part ] ) ) : '';
			}
			do_action( 'wpeasycart_admin_order_' . $type . '_update', $order_id, $values['first_name'], $values['last_name'], $values['company_name'], $values['address_line_1'], $values['address_line_2'], $values['city'], $values['state'], $values['country'], $values['zip'], $values['phone'] );
			$columns = array();
			foreach ( $values as $part => $value ) {
				$columns[ $type . '_' . $part ] = $value;
			}
			$wpdb->update( 'ec_order', $columns, array( 'order_id' => $order_id ) );
			$wpdb->query( $wpdb->prepare( 'UPDATE ec_order SET last_updated = NOW() WHERE order_id = %d', $order_id ) );
			$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log( order_id, order_log_key ) VALUES( %d, %s )', $order_id, 'order-' . $type . '-update' ) );
			$log_id = (int) $wpdb->insert_id;
			foreach ( array( 'first_name', 'last_name', 'company_name', 'address_line_1', 'address_line_2', 'city', 'state', 'zip', 'country', 'phone' ) as $part ) {
				$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, %s, %s )', $log_id, $order_id, $type . '_' . $part, $values[ $part ] ) );
			}
			do_action( 'wpeasycart_order_updated', $order_id );
		}

		/**
		 * AJAX ec_admin_ajax_save_order_management_details ( free from 6.0.2 ): the order's email addresses and the card
		 * details kept on it, as WP EasyCart PRO 6.0.1 saved them.
		 */
		public static function ajax_save_contact() {
			ecv2_order_screen_guard();
			global $wpdb;
			$order    = self::request_order();
			$order_id = (int) $order->order_id;
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- ecv2_order_screen_guard() above.
			$typed  = isset( $_POST['user_email'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['user_email'] ) ) ) : '';
			$values = array(
				'user_email'        => isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : '',
				'email_other'       => isset( $_POST['email_other'] ) ? sanitize_email( wp_unslash( $_POST['email_other'] ) ) : '',
				'card_holder_name'  => isset( $_POST['card_holder_name'] ) ? sanitize_text_field( wp_unslash( $_POST['card_holder_name'] ) ) : '',
				'creditcard_digits' => isset( $_POST['creditcard_digits'] ) ? substr( preg_replace( '/[^0-9]/', '', sanitize_text_field( wp_unslash( $_POST['creditcard_digits'] ) ) ), -4 ) : '',
				'cc_exp_month'      => isset( $_POST['cc_exp_month'] ) ? sanitize_text_field( wp_unslash( $_POST['cc_exp_month'] ) ) : '',
				'cc_exp_year'       => isset( $_POST['cc_exp_year'] ) ? sanitize_text_field( wp_unslash( $_POST['cc_exp_year'] ) ) : '',
			);
			// phpcs:enable WordPress.Security.NonceVerification.Missing
			if ( '' !== $typed && '' === $values['user_email'] ) {
				wp_send_json_error(
					array(
						'message' => __( 'Enter a valid email address.', 'wp-easycart' ),
						'field'   => 'user_email',
					)
				);
			}
			$wpdb->update( 'ec_order', $values, array( 'order_id' => $order_id ) );
			$wpdb->query( $wpdb->prepare( 'UPDATE ec_order SET last_updated = NOW() WHERE order_id = %d', $order_id ) );
			$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log( order_id, order_log_key ) VALUES( %d, "order-credit-card-update" )', $order_id ) );
			$log_id = (int) $wpdb->insert_id;
			foreach ( $values as $key => $value ) {
				$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, %s, %s )', $log_id, $order_id, $key, $value ) );
			}
			do_action( 'wpeasycart_order_updated', $order_id );
			wp_send_json_success( array( 'message' => __( 'Customer details saved.', 'wp-easycart' ) ) );
		}
	}

	wp_easycart_admin_order_screen::init();

endif;
