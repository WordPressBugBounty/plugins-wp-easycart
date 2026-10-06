<?php
/**
 * The Stripe price a subscription product bills, and moving a subscription to another product ( 6.0.3 ).
 *
 * A subscription product sells through one Stripe product and one Stripe price, kept on its ec_product row:
 * stripe_product_id / stripe_default_price_id in live mode, the *_sandbox columns in test mode ( filter
 * wp_easycart_is_stripe_sandbox ). A Stripe price can't be changed, so once the product's price, billing interval or the
 * store's Stripe currency changes, the stored price no longer fits. ensure() checks the stored price against the product
 * every time one is about to be used ( checkout, Change plan, the product editor's saves ) and makes a new one when it
 * doesn't match. Subscribers already on the old price keep it: Stripe never moves them.
 *
 * A price chosen by a modifier's "new price" ( optionitem_price_override ) is kept per option item, per mode, as JSON in
 * ec_option_to_product.stripe_price_id, and checked the same way.
 *
 * wp_easycart_change_subscription_plan() is the one way a subscription moves to another product ( My Account's Change
 * plan, the older account form, WP EasyCart PRO's subscription screen ). It fires wp_easycart_subscription_plan_changed.
 *
 * @package wp-easycart
 * @since 6.0.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_subscription_prices' ) ) :

	/**
	 * Stripe products and prices for subscription products.
	 */
	final class wp_easycart_subscription_prices {

		/**
		 * Stripe products looked up this request ( id => object ), so a request that asks twice calls Stripe once.
		 *
		 * @var array
		 */
		private static $products = array();

		/**
		 * The Stripe product the last ensure() used ( sync() compares its name ).
		 *
		 * @var object|null
		 */
		private static $last_product = null;

		/**
		 * The Stripe class for the store's gateway: ec_stripe ( WP EasyCart PRO, the store's own keys ) or ec_stripe_connect.
		 *
		 * @return ec_stripe|ec_stripe_connect|false False when the store doesn't take payments through Stripe.
		 */
		public static function gateway() {
			$method = (string) get_option( 'ec_option_payment_process_method' );
			if ( 'stripe' === $method && class_exists( 'ec_stripe' ) ) {
				return new ec_stripe();
			}
			if ( 'stripe_connect' === $method && class_exists( 'ec_stripe_connect' ) ) {
				return new ec_stripe_connect();
			}
			return false;
		}

		/**
		 * Whether Stripe runs in test mode, so the *_sandbox columns hold the ids.
		 *
		 * @return bool
		 */
		public static function is_sandbox() {
			return (bool) apply_filters( 'wp_easycart_is_stripe_sandbox', false );
		}

		/**
		 * The ec_product columns that hold the Stripe ids for a mode.
		 *
		 * @param bool|null $sandbox Test mode; null for the mode in use.
		 * @return array { product: string, price: string }
		 */
		public static function columns( $sandbox = null ) {
			if ( null === $sandbox ) {
				$sandbox = self::is_sandbox();
			}
			if ( $sandbox ) {
				return array(
					'product' => 'stripe_product_id_sandbox',
					'price'   => 'stripe_default_price_id_sandbox',
				);
			}
			return array(
				'product' => 'stripe_product_id',
				'price'   => 'stripe_default_price_id',
			);
		}

		/**
		 * What ensure() needs of a product, read from the database ( never a cart copy with options added to its price ).
		 *
		 * @param int $product_id Product.
		 * @return object|null
		 */
		public static function row( $product_id ) {
			global $wpdb;
			return $wpdb->get_row( $wpdb->prepare( 'SELECT product_id, title, price, is_subscription_item, subscription_bill_period, subscription_bill_length, trial_period_days, stripe_product_id, stripe_default_price_id, stripe_product_id_sandbox, stripe_default_price_id_sandbox FROM ec_product WHERE product_id = %d', (int) $product_id ) );
		}

		/**
		 * The Stripe ids stored for the mode in use.
		 *
		 * @param object $row A row from row().
		 * @return array { product: string, price: string }
		 */
		public static function stored( $row ) {
			$columns = self::columns();
			return array(
				'product' => ( isset( $row->{ $columns['product'] } ) ) ? (string) $row->{ $columns['product'] } : '',
				'price'   => ( isset( $row->{ $columns['price'] } ) ) ? (string) $row->{ $columns['price'] } : '',
			);
		}

		/**
		 * Stripe's name for a bill period.
		 *
		 * @param string $period D, W, M or Y.
		 * @return string day | week | month | year, '' for anything else.
		 */
		public static function interval_name( $period ) {
			$names = array(
				'D' => 'day',
				'W' => 'week',
				'M' => 'month',
				'Y' => 'year',
			);
			return ( isset( $names[ $period ] ) ) ? $names[ $period ] : '';
		}

		/**
		 * An amount in Stripe's smallest unit, rounded as the price was sent.
		 *
		 * @param float $amount Amount.
		 * @return int
		 */
		public static function cents( $amount ) {
			return (int) round( (float) $amount * 100 );
		}

		/**
		 * Whether a Stripe price bills this amount, every this often, in the store's Stripe currency, for this Stripe product.
		 *
		 * @param object|false $price             Stripe price.
		 * @param float        $amount            The amount it should charge.
		 * @param string       $period            D, W, M or Y.
		 * @param int          $length            Bill every N periods.
		 * @param string       $stripe_product_id The Stripe product it should belong to ( '' to skip ).
		 * @return bool
		 */
		public static function matches( $price, $amount, $period, $length, $stripe_product_id = '' ) {
			if ( ! is_object( $price ) || empty( $price->id ) || ! empty( $price->deleted ) ) {
				return false;
			}
			if ( isset( $price->object ) && 'price' !== $price->object ) {
				return false;
			}
			if ( isset( $price->active ) && ! $price->active ) {
				return false;
			}
			if ( ! isset( $price->unit_amount ) || self::cents( $amount ) !== (int) $price->unit_amount ) {
				return false;
			}
			$currency = strtolower( (string) get_option( 'ec_option_stripe_currency' ) );
			if ( '' !== $currency && isset( $price->currency ) && strtolower( (string) $price->currency ) !== $currency ) {
				return false;
			}
			if ( ! isset( $price->recurring ) || ! is_object( $price->recurring ) || ! isset( $price->recurring->interval ) ) {
				return false;
			}
			if ( self::interval_name( $period ) !== (string) $price->recurring->interval ) {
				return false;
			}
			if ( (int) ( isset( $price->recurring->interval_count ) ? $price->recurring->interval_count : 1 ) !== max( 1, (int) $length ) ) {
				return false;
			}
			if ( '' !== $stripe_product_id && isset( $price->product ) ) {
				$belongs_to = ( is_object( $price->product ) && isset( $price->product->id ) ) ? (string) $price->product->id : (string) $price->product;
				if ( $belongs_to !== $stripe_product_id ) {
					return false;
				}
			}
			return true;
		}

		/**
		 * Whether a Stripe plan made for an option item ( ec_optionitem.stripe_plan_id, Stripe's older Plans API ) still bills
		 * the option's amount, in the store's Stripe currency, as often as this product. An option item shared by a monthly and
		 * a yearly product used to reuse one plan for both.
		 *
		 * @param object|false $plan    Stripe plan.
		 * @param float        $amount  The option's price.
		 * @param string       $name    The option item's name ( the plan's nickname ).
		 * @param object       $product The subscription product ( subscription_bill_period / _length ).
		 * @return bool
		 */
		public static function plan_fits( $plan, $amount, $name, $product ) {
			if ( ! is_object( $plan ) || empty( $plan->id ) || ! empty( $plan->deleted ) ) {
				return false;
			}
			if ( isset( $plan->active ) && ! $plan->active ) {
				return false;
			}
			if ( ! isset( $plan->amount ) || self::cents( $amount ) !== (int) $plan->amount ) {
				return false;
			}
			$currency = strtolower( (string) get_option( 'ec_option_stripe_currency' ) );
			if ( '' !== $currency && isset( $plan->currency ) && strtolower( (string) $plan->currency ) !== $currency ) {
				return false;
			}
			$period = ( isset( $product->subscription_bill_period ) ) ? (string) $product->subscription_bill_period : '';
			$length = ( isset( $product->subscription_bill_length ) ) ? max( 1, (int) $product->subscription_bill_length ) : 1;
			if ( ! isset( $plan->interval ) || self::interval_name( $period ) !== (string) $plan->interval ) {
				return false;
			}
			if ( (int) ( isset( $plan->interval_count ) ? $plan->interval_count : 1 ) !== $length ) {
				return false;
			}
			return ( isset( $plan->nickname ) && (string) wp_easycart_language()->convert_text( $name ) === (string) $plan->nickname );
		}

		/**
		 * The Stripe price to bill a subscription product with: the stored one when it still matches the product, else a new
		 * one ( and the Stripe product too, when it is missing ). The ids are saved for the mode in use and set on $product.
		 *
		 * @param object|int $product A product ( ec_product, a row ) or its id. Only its id is read; the rest comes from the
		 *                            database, so a cart copy whose price includes options never becomes a Stripe price.
		 * @param object     $stripe  ec_stripe or ec_stripe_connect; the store's when left out.
		 * @param array      $args    {
		 *     Optional.
		 *     @type float|null  $amount      The amount to bill when it isn't the product's price ( a modifier's new price ).
		 *     @type object|null $option_item The modifier item whose new price this is ( optionitem_id, option_id, optionitem_name ).
		 * }
		 * @return string|false The Stripe price id, or false when Stripe could not be reached or refused.
		 */
		public static function ensure( $product, $stripe = null, $args = array() ) {
			$product_id = ( is_object( $product ) && isset( $product->product_id ) ) ? (int) $product->product_id : (int) $product;
			$row        = self::row( $product_id );
			if ( ! $row ) {
				return false;
			}
			if ( ! $stripe ) {
				$stripe = self::gateway();
			}
			if ( ! $stripe ) {
				return false;
			}
			$period = (string) $row->subscription_bill_period;
			$length = max( 1, (int) $row->subscription_bill_length );
			if ( '' === self::interval_name( $period ) ) {
				return false;
			}
			$args = array_merge(
				array(
					'amount'      => null,
					'option_item' => null,
				),
				(array) $args
			);
			$ids  = self::stored( $row );

			$stripe_product = ( '' !== $ids['product'] ) ? self::fetch_product( $stripe, $ids['product'] ) : false;
			if ( ! $stripe_product ) {
				/**
				 * A Stripe product this product shares with others ( 6.0.3: a plan group's tier sells monthly and yearly
				 * through one Stripe product with a price for each ). '' for none: the product gets its own.
				 *
				 * @since 6.0.3
				 * @param string $stripe_product_id Stripe product id, for the mode in use.
				 * @param object $row               The product ( row() ).
				 */
				$shared = (string) apply_filters( 'wp_easycart_subscription_shared_stripe_product', '', $row );
				if ( '' !== $shared && $shared !== $ids['product'] ) {
					$stripe_product = self::fetch_product( $stripe, $shared );
					if ( $stripe_product ) {
						$ids = array(
							'product' => $shared,
							'price'   => '',
						);
						self::store( $row->product_id, $ids );
					}
				}
			}
			if ( ! $stripe_product ) {
				$created = $stripe->insert_product( self::stripe_object( $row, (float) $row->price, $period, $length, '' ) );
				if ( ! is_object( $created ) || empty( $created->id ) ) {
					return false;
				}
				$default = ( isset( $created->default_price ) && is_object( $created->default_price ) ) ? (string) $created->default_price->id : ( isset( $created->default_price ) ? (string) $created->default_price : '' );
				$ids     = array(
					'product' => (string) $created->id,
					'price'   => $default,
				);
				self::store( $row->product_id, $ids );
				self::$products[ $ids['product'] ] = $created;
				$stripe_product                    = $created;
			}
			self::$last_product = $stripe_product;

			$amount    = ( null === $args['amount'] ) ? (float) $row->price : (float) $args['amount'];
			$option    = ( is_object( $args['option_item'] ) ) ? $args['option_item'] : null;
			$candidate = ( $option ) ? self::override_price_id( $row->product_id, $option ) : $ids['price'];
			if ( '' !== $candidate ) {
				$price = $stripe->get_price( $candidate );
				if ( self::matches( $price, $amount, $period, $length, $ids['product'] ) ) {
					self::apply( $product, $ids['product'], $candidate );
					return $candidate;
				}
			}

			$title = $row->title;
			if ( $option && isset( $option->optionitem_name ) && '' !== (string) $option->optionitem_name ) {
				$title .= ' ' . $option->optionitem_name;
			}
			$new = $stripe->insert_price( self::stripe_object( $row, $amount, $period, $length, $ids['product'], $title ) );
			if ( ! is_object( $new ) || empty( $new->id ) ) {
				return false;
			}
			if ( $option ) {
				self::store_override( $row->product_id, $option, (string) $new->id );
			} else {
				self::store( $row->product_id, array( 'price' => (string) $new->id ) );
			}
			self::apply( $product, $ids['product'], (string) $new->id );
			return (string) $new->id;
		}

		/**
		 * After a product editor save: make sure the product's Stripe price fits, and keep the Stripe product's name and
		 * default price in step ( what the merchant sees in the Stripe dashboard ).
		 *
		 * @param int $product_id Product.
		 * @return string|false The Stripe price id.
		 */
		public static function sync( $product_id ) {
			$row = self::row( $product_id );
			if ( ! $row || empty( $row->is_subscription_item ) ) {
				return false;
			}
			$stripe = self::gateway();
			if ( ! $stripe ) {
				return false;
			}
			self::$last_product = null;
			$price_id           = self::ensure( $row, $stripe );
			$stripe_product     = self::$last_product;
			if ( $price_id && is_object( $stripe_product ) && ! empty( $stripe_product->id ) ) {
				/**
				 * The name of the product's Stripe product ( 6.0.3: a plan group's tier names the one its prices share ).
				 *
				 * @since 6.0.3
				 * @param string $title The product title.
				 * @param object $row   The product ( row() ).
				 */
				$title   = (string) apply_filters( 'wp_easycart_subscription_stripe_product_name', (string) $row->title, $row );
				$name    = wp_easycart_language()->convert_text( $title );
				$default = ( isset( $stripe_product->default_price ) && is_object( $stripe_product->default_price ) ) ? (string) $stripe_product->default_price->id : ( isset( $stripe_product->default_price ) ? (string) $stripe_product->default_price : '' );
				/**
				 * Whether this product's price becomes its Stripe product's default ( 6.0.3: of a tier's shared product, only
				 * the monthly price ).
				 *
				 * @since 6.0.3
				 * @param bool   $sets_default Default true.
				 * @param object $row          The product ( row() ).
				 */
				$sets_default = (bool) apply_filters( 'wp_easycart_subscription_stripe_sets_default', true, $row );
				$new_default  = ( $sets_default || '' === $default ) ? $price_id : $default;
				if ( ( isset( $stripe_product->name ) && $name !== (string) $stripe_product->name ) || $default !== $new_default ) {
					$updated = $stripe->update_product(
						(object) array(
							'stripe_product_id'       => (string) $stripe_product->id,
							'stripe_default_price_id' => $new_default,
							'title'                   => $title,
						)
					);
					if ( is_object( $updated ) && ! empty( $updated->id ) ) {
						self::$products[ (string) $updated->id ] = $updated;
					}
				}
			}
			return $price_id;
		}

		/**
		 * A duplicated product gets its own Stripe product: the copy's Stripe ids ( both modes ) are cleared, and ensure() makes
		 * new ones when it is first sold or saved. Sharing the original's would rename it and bill the copy on its price.
		 *
		 * @param int $new_id The copy.
		 */
		public static function forget_copied_ids( $new_id ) {
			global $wpdb;
			$wpdb->query( $wpdb->prepare( "UPDATE ec_product SET stripe_product_id = '', stripe_default_price_id = '', stripe_product_id_sandbox = '', stripe_default_price_id_sandbox = '' WHERE product_id = %d", (int) $new_id ) );
		}

		/**
		 * A Stripe product by id, once per request. Missing, deleted or archived products answer false ( a new one is made ).
		 *
		 * @param object $stripe Stripe class.
		 * @param string $id     Stripe product id.
		 * @return object|false
		 */
		private static function fetch_product( $stripe, $id ) {
			if ( ! isset( self::$products[ $id ] ) ) {
				self::$products[ $id ] = $stripe->get_product( $id );
			}
			$stripe_product = self::$products[ $id ];
			if ( ! is_object( $stripe_product ) || empty( $stripe_product->id ) || ! empty( $stripe_product->deleted ) ) {
				return false;
			}
			if ( isset( $stripe_product->object ) && 'product' !== $stripe_product->object ) {
				return false;
			}
			if ( isset( $stripe_product->active ) && ! $stripe_product->active ) {
				return false;
			}
			return $stripe_product;
		}

		/**
		 * What the Stripe classes' insert_product() / insert_price() read.
		 *
		 * @param object      $row               A row from row().
		 * @param float       $amount            Amount.
		 * @param string      $period            D, W, M or Y.
		 * @param int         $length            Bill every N periods.
		 * @param string      $stripe_product_id The Stripe product a new price belongs to.
		 * @param string|null $title             Name ( the product's when null ).
		 * @return object
		 */
		private static function stripe_object( $row, $amount, $period, $length, $stripe_product_id, $title = null ) {
			return (object) array(
				'product_id'               => (int) $row->product_id,
				'title'                    => ( null === $title ) ? $row->title : $title,
				'price'                    => $amount,
				'subscription_bill_period' => $period,
				'subscription_bill_length' => $length,
				'trial_period_days'        => (int) $row->trial_period_days,
				'stripe_product_id'        => $stripe_product_id,
				'stripe_default_price_id'  => '',
				'subscription_unique_id'   => '',
			);
		}

		/**
		 * Save Stripe ids to the columns of the mode in use.
		 *
		 * @param int   $product_id Product.
		 * @param array $ids        { product?: string, price?: string }.
		 */
		private static function store( $product_id, $ids ) {
			global $wpdb;
			$columns = self::columns();
			$set     = array();
			foreach ( $ids as $key => $value ) {
				if ( isset( $columns[ $key ] ) ) {
					$set[ $columns[ $key ] ] = (string) $value;
				}
			}
			if ( empty( $set ) ) {
				return;
			}
			$wpdb->update( 'ec_product', $set, array( 'product_id' => (int) $product_id ), array_fill( 0, count( $set ), '%s' ), array( '%d' ) );
			if ( class_exists( 'ec_db' ) && method_exists( 'ec_db', 'product_cache_changed' ) ) {
				ec_db::product_cache_changed();
			}
		}

		/**
		 * Set the ids on the caller's product object.
		 *
		 * @param mixed  $product  What ensure() was given.
		 * @param string $sp       Stripe product id.
		 * @param string $price_id Stripe price id.
		 */
		private static function apply( $product, $sp, $price_id ) {
			if ( is_object( $product ) ) {
				$product->stripe_product_id       = $sp;
				$product->stripe_default_price_id = $price_id;
			}
		}

		/**
		 * The mode key kept with a modifier's Stripe price.
		 *
		 * @return string test | live
		 */
		private static function mode_key() {
			return ( self::is_sandbox() ) ? 'test' : 'live';
		}

		/**
		 * The stored Stripe price for a modifier item's new price, for the mode in use ( entries saved before 6.0.3 have no
		 * mode and are tried last ).
		 *
		 * @param int    $product_id Product.
		 * @param object $option     Option item ( optionitem_id, option_id ).
		 * @return string
		 */
		private static function override_price_id( $product_id, $option ) {
			$found    = '';
			$fallback = '';
			foreach ( self::override_entries( $product_id, $option ) as $entry ) {
				if ( ! is_object( $entry ) || ! isset( $entry->optionitem_id ) || ! isset( $entry->stripe_price_id ) || (int) $entry->optionitem_id !== (int) $option->optionitem_id ) {
					continue;
				}
				if ( ! isset( $entry->mode ) ) {
					$fallback = (string) $entry->stripe_price_id;
				} elseif ( self::mode_key() === $entry->mode ) {
					$found = (string) $entry->stripe_price_id;
				}
			}
			return ( '' !== $found ) ? $found : $fallback;
		}

		/**
		 * The JSON list kept on ec_option_to_product for a product's modifier.
		 *
		 * @param int    $product_id Product.
		 * @param object $option     Option item.
		 * @return array
		 */
		private static function override_entries( $product_id, $option ) {
			global $wpdb;
			$json    = $wpdb->get_var( $wpdb->prepare( 'SELECT stripe_price_id FROM ec_option_to_product WHERE product_id = %d AND option_id = %d', (int) $product_id, (int) $option->option_id ) );
			$entries = ( is_string( $json ) && '' !== $json ) ? json_decode( $json ) : array();
			return ( is_array( $entries ) ) ? $entries : array();
		}

		/**
		 * Keep a modifier item's new Stripe price for the mode in use ( replacing this item's entry for the mode, and an entry
		 * saved before 6.0.3 without one ).
		 *
		 * @param int    $product_id Product.
		 * @param object $option     Option item.
		 * @param string $price_id   Stripe price id.
		 */
		private static function store_override( $product_id, $option, $price_id ) {
			global $wpdb;
			$mode = self::mode_key();
			$keep = array();
			foreach ( self::override_entries( $product_id, $option ) as $entry ) {
				if ( is_object( $entry ) && isset( $entry->optionitem_id ) && (int) $entry->optionitem_id === (int) $option->optionitem_id && ( ! isset( $entry->mode ) || $mode === $entry->mode ) ) {
					continue;
				}
				$keep[] = $entry;
			}
			$keep[] = (object) array(
				'optionitem_id'   => (int) $option->optionitem_id,
				'stripe_price_id' => $price_id,
				'mode'            => $mode,
			);
			$wpdb->query( $wpdb->prepare( 'UPDATE ec_option_to_product SET stripe_price_id = %s WHERE product_id = %d AND option_id = %d', wp_json_encode( $keep ), (int) $product_id, (int) $option->option_id ) );
		}

		/**
		 * The subscription item that bills the product ( a subscription can also hold option and shipping items ): the one on
		 * the product's own Stripe product, else the first.
		 *
		 * @param object $live              Stripe subscription.
		 * @param int    $product_id        The product the subscription is on now.
		 * @return string Item id, or ''.
		 */
		public static function main_item( $live, $product_id ) {
			$item = self::main_item_object( $live, $product_id );
			return ( $item && isset( $item->id ) ) ? (string) $item->id : '';
		}

		/**
		 * The Stripe subscription item main_item() names ( with its price ).
		 *
		 * @param object $live       Stripe subscription.
		 * @param int    $product_id The product the subscription is on now.
		 * @return object|null
		 */
		public static function main_item_object( $live, $product_id ) {
			if ( ! is_object( $live ) || ! isset( $live->items->data ) || ! is_array( $live->items->data ) || empty( $live->items->data ) ) {
				return null;
			}
			$row   = self::row( $product_id );
			$known = array();
			if ( $row ) {
				foreach ( array( $row->stripe_product_id, $row->stripe_product_id_sandbox ) as $id ) {
					if ( '' !== (string) $id ) {
						$known[] = (string) $id;
					}
				}
			}
			foreach ( $live->items->data as $item ) {
				$on = '';
				if ( isset( $item->price->product ) ) {
					$on = ( is_object( $item->price->product ) && isset( $item->price->product->id ) ) ? (string) $item->price->product->id : (string) $item->price->product;
				}
				if ( '' !== $on && in_array( $on, $known, true ) && isset( $item->id ) ) {
					return $item;
				}
			}
			return $live->items->data[0];
		}
	}

	add_action( 'wpeasycart_product_duplicated', array( 'wp_easycart_subscription_prices', 'forget_copied_ids' ), 1, 1 );

endif;

if ( ! function_exists( 'wp_easycart_plan_groups_ready' ) ) {
	/**
	 * The plan group columns and table exist ( EC_UPGRADE_DB 121 ).
	 *
	 * @since 6.0.3
	 * @return bool
	 */
	function wp_easycart_plan_groups_ready() {
		return (int) get_option( 'ec_option_db_new_version' ) >= 121;
	}
}

if ( ! function_exists( 'wp_easycart_unlisted_sql' ) ) {
	/**
	 * " AND …" that leaves out of a product list the products that sell only through something else: a WP EasyCart PRO
	 * plan group's products sell from its pricing table ( their own pages and the subscription page still work ). Store
	 * lists, category pages, search, live search and the Elementor listing widgets add it.
	 *
	 * @since 6.0.3
	 * @param string $alias The ec_product table's alias.
	 * @return string SQL starting with ' AND', or ''.
	 */
	function wp_easycart_unlisted_sql( $alias = 'product' ) {
		if ( ! wp_easycart_plan_groups_ready() ) {
			return '';
		}
		$alias = preg_replace( '/[^a-z0-9_]/i', '', (string) $alias );
		$sql   = ' AND ' . $alias . '.plan_tier_id = 0';
		/**
		 * The SQL a product list adds to leave out unlisted products.
		 *
		 * @since 6.0.3
		 * @param string $sql   SQL starting with ' AND', or ''.
		 * @param string $alias The ec_product table's alias.
		 */
		return (string) apply_filters( 'wp_easycart_unlisted_sql', $sql, $alias );
	}
}

if ( ! function_exists( 'wp_easycart_subscription_period_text' ) ) {
	/**
	 * How often a subscription bills, as shown after its price: "/mo", "/3 months", "/year for 2 years". One wording for
	 * product pages, the subscription page and My Account ( which said "/month" in English only, and product pages "for 3
	 * year" or "/3 mos" ).
	 *
	 * @since 6.0.3
	 * @param int    $length   Bill every N periods.
	 * @param string $period   D, W, M or Y.
	 * @param int    $duration Number of payments, 0 for until cancelled.
	 * @return string Plain text ( escape when printing ).
	 */
	function wp_easycart_subscription_period_text( $length, $period, $duration = 0 ) {
		$words = array(
			'D' => array( array( 'product_details_subscription_day', 'day' ), array( 'product_details_subscription_days', 'days' ), array( 'product_details_subscription_day', 'day' ) ),
			'W' => array( array( 'product_details_subscription_week', 'week' ), array( 'product_details_subscription_weeks', 'weeks' ), array( 'product_details_subscription_week', 'week' ) ),
			'M' => array( array( 'product_details_subscription_month', 'mo' ), array( 'product_details_subscription_month_full', 'months' ), array( 'product_details_subscription_month_one', 'month' ) ),
			'Y' => array( array( 'product_details_subscription_year', 'year' ), array( 'product_details_subscription_years', 'years' ), array( 'product_details_subscription_year', 'year' ) ),
		);
		if ( ! isset( $words[ $period ] ) ) {
			return '/';
		}
		$word   = function ( $pair ) {
			$text = ( function_exists( 'wp_easycart_language' ) ) ? trim( (string) wp_easycart_language()->get_text( 'product_details', $pair[0] ) ) : '';
			return ( '' === $text ) ? $pair[1] : $text;
		};
		$length = max( 1, (int) $length );
		$text   = '/' . ( ( $length > 1 ) ? $length . ' ' . $word( $words[ $period ][1] ) : $word( $words[ $period ][0] ) );
		if ( (int) $duration > 0 ) {
			$divider = ( function_exists( 'wp_easycart_language' ) ) ? trim( (string) wp_easycart_language()->get_text( 'product_details', 'product_details_subscription_duration_divider' ) ) : 'for';
			$text   .= ' ' . $divider . ' ' . (int) $duration . ' ' . $word( ( 1 === (int) $duration ) ? $words[ $period ][2] : $words[ $period ][1] );
		}
		return $text;
	}
}

if ( ! function_exists( 'wp_easycart_subscription_quantity_max' ) ) {
	/**
	 * The most a product page's Sign Up quantity box allows for a subscription, or 0 for no limit. The maximum purchase
	 * quantity when set; else the stock count, only when the product shows one and it allows at least the minimum.
	 * Subscriptions are never sold out by stock at checkout, so a stock count of 0 ( the column default ) used to print
	 * min 1 / max 0, and the browser refused to submit ( "Minimum value ( 1 ) must be less than the maximum value ( 0 )" ).
	 *
	 * @since 6.0.3
	 * @param object $product ec_product.
	 * @return int
	 */
	function wp_easycart_subscription_quantity_max( $product ) {
		if ( ! is_object( $product ) ) {
			return 0;
		}
		$min = ( isset( $product->min_purchase_quantity ) && (int) $product->min_purchase_quantity > 0 ) ? (int) $product->min_purchase_quantity : 1;
		if ( isset( $product->max_purchase_quantity ) && (int) $product->max_purchase_quantity > 0 ) {
			return max( $min, (int) $product->max_purchase_quantity );
		}
		if ( ! empty( $product->show_stock_quantity ) && isset( $product->stock_quantity ) && (int) $product->stock_quantity >= $min ) {
			return (int) $product->stock_quantity;
		}
		return 0;
	}
}

if ( ! function_exists( 'wp_easycart_subscription_coupon_schedule' ) ) {
	/**
	 * What a subscription bought on the subscription page will be charged, by Stripe's rules for the code on the checkout: the
	 * renewal price, the first charge, and how many charges the code comes off. Stripe takes a coupon off charges: `once` off the
	 * first charge ( after a free trial that is the payment when the trial ends: the trial's $0 invoice is no charge ),
	 * `repeating` off the charges in its first months counted from the signup ( a free trial uses part of them ), `forever` off
	 * every charge. The code is read with ec_cartpage::subscription_checkout_coupon(), the rule that decides what reaches Stripe.
	 *
	 * The renewal price is what Stripe bills each period: the product and its recurring options times the quantity, plus the
	 * shipping when it recurs; never the sign-up fee or one-time options.
	 *
	 * @since 6.0.3
	 * @param object $product ec_product ( its price after any option override ).
	 * @param array  $args    code ( the code on the checkout, '' = none ), quantity, option_total ( recurring options for one
	 *                        unit ), shipping_total ( the first payment's ), taxed ( the first payment carried tax the prices
	 *                        do not include ), now ( the signup time, a timestamp ).
	 * @return array amount ( the renewal ), first ( the first charge ), charges ( how many charges the code comes off: 0 none,
	 *               -1 every one ), months ( a repeating code's months ), coupon ( subscription_checkout_coupon()'s answer, or
	 *               false ), args ( with their defaults ).
	 */
	function wp_easycart_subscription_coupon_schedule( $product, $args = array() ) {
		$args = array_merge(
			array(
				'code'           => '',
				'quantity'       => 1,
				'option_total'   => 0,
				'shipping_total' => 0,
				'taxed'          => false,
				'now'            => time(),
			),
			(array) $args
		);
		$plan = array(
			'amount'  => 0.0,
			'first'   => 0.0,
			'charges' => 0,
			'months'  => 0,
			'coupon'  => false,
			'args'    => $args,
		);
		if ( ! is_object( $product ) ) {
			return $plan;
		}
		$amount = ( (float) $product->price + (float) $args['option_total'] ) * max( 1, (int) $args['quantity'] );
		if ( ! empty( $product->subscription_shipping_recurring ) ) {
			$amount += (float) $args['shipping_total'];
		}
		$plan['amount'] = round( $amount, 2 );
		$plan['first']  = $plan['amount'];
		if ( '' === trim( (string) $args['code'] ) || ! class_exists( 'ec_cartpage' ) || ! method_exists( 'ec_cartpage', 'subscription_checkout_coupon' ) ) {
			return $plan;
		}
		$coupon = ec_cartpage::subscription_checkout_coupon( (string) $args['code'], (int) $product->product_id, isset( $product->manufacturer_id ) ? (int) $product->manufacturer_id : 0 );
		if ( ! $coupon ) {
			return $plan;
		}
		$plan['coupon'] = $coupon;

		if ( 'forever' === $coupon['duration'] ) {
			$plan['charges'] = -1;
		} elseif ( 'repeating' === $coupon['duration'] ) {
			// The charges inside the code's months: the first when the trial ends ( or at once ), then one each billing period.
			$plan['months'] = max( 1, (int) $coupon['months'] );
			$units          = array(
				'D' => 'day',
				'W' => 'week',
				'M' => 'month',
				'Y' => 'year',
			);
			$period         = strtoupper( (string) $product->subscription_bill_period );
			$length         = max( 1, (int) $product->subscription_bill_length );
			$step           = '+' . $length . ' ' . ( isset( $units[ $period ] ) ? $units[ $period ] : 'month' ) . ( $length > 1 ? 's' : '' );
			$trial          = isset( $product->trial_period_days ) ? max( 0, (int) $product->trial_period_days ) : 0;
			$start          = new DateTime( '@' . (int) $args['now'] );
			$ends           = clone $start;
			$ends->modify( '+' . $plan['months'] . ' months' );
			$charge = clone $start;
			if ( $trial > 0 ) {
				$charge->modify( '+' . $trial . ' days' );
			}
			while ( $charge < $ends && $plan['charges'] < 1000 ) {
				++$plan['charges'];
				$charge->modify( $step );
			}
		} else {
			$plan['charges'] = 1;
		}
		$payments = isset( $product->subscription_bill_duration ) ? (int) $product->subscription_bill_duration : 0; // 0 = until cancelled.
		if ( $payments > 0 && ( -1 === $plan['charges'] || $plan['charges'] >= $payments ) ) {
			$plan['charges'] = -1; // Every payment the subscription makes.
		}
		if ( 0 !== $plan['charges'] ) {
			if ( ! empty( $coupon['amount_off'] ) ) {
				$plan['first'] = max( 0, round( $amount - (float) $coupon['row']->promo_dollar, 2 ) );
			} else {
				$plan['first'] = round( $amount * ( 1 - (float) $coupon['row']->promo_percentage / 100 ), 2 );
			}
		}
		return $plan;
	}
}

if ( ! function_exists( 'wp_easycart_subscription_renewal_note' ) ) {
	/**
	 * What the subscription page says under its total when the code applied stops before the subscription does: off the first
	 * payment only ( after a free trial, the payment when it ends ) or off the first months, with the price the subscription
	 * renews at. '' when nothing needs saying: no code, a code on every payment ( forever, or as many charges as a fixed
	 * subscription makes ), a code a free trial outlasts, a single payment, or a one-month code on a weekly or daily plan.
	 * "plus tax" follows the price when the first payment carried tax the prices do not include.
	 *
	 * @since 6.0.3
	 * @param object $product ec_product ( its price after any option override ).
	 * @param array  $args    As wp_easycart_subscription_coupon_schedule().
	 * @return string Plain text.
	 */
	function wp_easycart_subscription_renewal_note( $product, $args = array() ) {
		if ( ! is_object( $product ) || ( isset( $product->subscription_bill_duration ) && 1 === (int) $product->subscription_bill_duration ) ) {
			return '';
		}
		$plan = wp_easycart_subscription_coupon_schedule( $product, $args );
		if ( ! $plan['coupon'] || $plan['charges'] <= 0 ) {
			return '';
		}
		$several = ( $plan['charges'] > 1 );
		if ( $several && $plan['months'] < 2 ) {
			return ''; // A one-month code on a weekly or daily plan: "the first 1 months" would read wrong.
		}
		$price = html_entity_decode( wp_strip_all_tags( (string) $product->get_option_price_formatted( $plan['amount'], 1 ) ), ENT_QUOTES, 'UTF-8' );
		if ( ! empty( $plan['args']['taxed'] ) ) {
			$price .= ' ' . wp_easycart_subscription_coupon_phrase( 'subscription_renewal_plus_tax', 'plus tax' );
		}
		if ( $several ) {
			$text = str_replace( array( '[months]', '[price]' ), array( (string) $plan['months'], $price ), wp_easycart_subscription_coupon_phrase( 'subscription_coupon_months', 'Your discount applies for the first [months] months. After that, it renews at [price].' ) );
		} else {
			$text = str_replace( '[price]', $price, wp_easycart_subscription_coupon_phrase( 'subscription_coupon_first_payment', 'Your discount applies to your first payment only. After that, it renews at [price].' ) );
		}

		/**
		 * The subscription page's renewal note ( plain text; '' prints nothing ).
		 *
		 * @since 6.0.3
		 * @param string $text    The note.
		 * @param object $product ec_product.
		 * @param array  $coupon  ec_cartpage::subscription_checkout_coupon()'s answer.
		 * @param array  $args    What the page passed.
		 */
		return (string) apply_filters( 'wp_easycart_subscription_renewal_note', $text, $product, $plan['coupon'], $args );
	}
}

if ( ! function_exists( 'wp_easycart_subscription_trial_text' ) ) {
	/**
	 * The subscription page's free trial line: when the trial ends and what the first payment is then, with the code on the
	 * checkout taken off when Stripe will take it off that payment ( wp_easycart_subscription_coupon_schedule() ). '' for a
	 * product without a free trial.
	 *
	 * @since 6.0.3
	 * @param object $product ec_product ( its price after any option override ).
	 * @param array  $args    As wp_easycart_subscription_coupon_schedule().
	 * @return string Plain text.
	 */
	function wp_easycart_subscription_trial_text( $product, $args = array() ) {
		if ( ! is_object( $product ) || empty( $product->trial_period_days ) || (int) $product->trial_period_days <= 0 ) {
			return '';
		}
		$plan  = wp_easycart_subscription_coupon_schedule( $product, $args );
		$ends  = (int) $plan['args']['now'] + (int) $product->trial_period_days * 86400;
		$price = isset( $GLOBALS['currency'] ) && is_object( $GLOBALS['currency'] ) ? $GLOBALS['currency']->get_currency_display( $plan['first'] ) : number_format( $plan['first'], 2 );
		$price = html_entity_decode( wp_strip_all_tags( (string) $price ), ENT_QUOTES, 'UTF-8' );
		if ( ! empty( $plan['args']['taxed'] ) ) {
			$price .= ' ' . wp_easycart_subscription_coupon_phrase( 'subscription_renewal_plus_tax', 'plus tax' );
		}
		$text = class_exists( 'ec_subscription' ) ? ec_subscription::get_text( 'subscription_details_trial_notice', 'Your free trial ends on [date]. Your first payment of [price] is taken then.' ) : 'Your free trial ends on [date]. Your first payment of [price] is taken then.';
		$date = class_exists( 'ec_subscription' ) ? ec_subscription::format_date( $ends ) : gmdate( 'F j, Y', $ends );
		$text = str_replace( array( '[date]', '[price]' ), array( $date, $price ), html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES, 'UTF-8' ) );

		/**
		 * The subscription page's free trial line ( plain text ).
		 *
		 * @since 6.0.3
		 * @param string $text    The line.
		 * @param object $product ec_product.
		 * @param array  $plan    wp_easycart_subscription_coupon_schedule()'s answer.
		 */
		return (string) apply_filters( 'wp_easycart_subscription_trial_text', $text, $product, $plan );
	}
}

if ( ! function_exists( 'wp_easycart_subscription_coupon_phrase' ) ) {
	/**
	 * A cart_coupons phrase as plain text, or its English when the store's language file has none.
	 *
	 * @since 6.0.3
	 * @param string $key      Phrase key.
	 * @param string $fallback English.
	 * @return string
	 */
	function wp_easycart_subscription_coupon_phrase( $key, $fallback ) {
		$text = function_exists( 'wp_easycart_language' ) ? wp_easycart_language()->get_text( 'cart_coupons', $key ) : null;
		$text = ( null === $text ) ? '' : html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES, 'UTF-8' );
		return ( '' !== trim( $text ) ) ? $text : $fallback;
	}
}

if ( ! function_exists( 'wp_easycart_subscription_member_statuses' ) ) {
	/**
	 * The ec_subscription statuses that still hold the product: Active, a free trial, and a subscription cancelled at the end
	 * of the period it paid for ( WP EasyCart PRO's sync writes trialing / canceling ). Paused, failed and ended ones don't.
	 * Memberships, the "already subscribed" check and its classic twin read this.
	 *
	 * @since 6.0.3
	 * @return string[]
	 */
	function wp_easycart_subscription_member_statuses() {
		$statuses = (array) apply_filters( 'wp_easycart_subscription_member_statuses', array( 'Active', 'trialing', 'canceling' ) );
		$statuses = array_values( array_filter( array_map( 'strval', $statuses ), 'strlen' ) );
		return ( empty( $statuses ) ) ? array( 'Active' ) : $statuses;
	}
}

if ( ! function_exists( 'wp_easycart_subscription_mrr_statuses' ) ) {
	/**
	 * The ec_subscription statuses that count toward monthly recurring revenue: the paying ones ( Active, cancelling at the end of
	 * the period it paid for until it ends, and past due ). A free trial counts once it pays. One MRR rule ( 6.0.3 ) for Reports, the
	 * Subscriptions list and the Plans list in WP EasyCart PRO.
	 *
	 * @since 6.0.3
	 * @return string[]
	 */
	function wp_easycart_subscription_mrr_statuses() {
		$statuses = (array) apply_filters( 'wp_easycart_subscription_mrr_statuses', array( 'Active', 'canceling', 'Failed', 'past_due' ) );
		$statuses = array_values( array_filter( array_map( 'strval', $statuses ), 'strlen' ) );
		return ( empty( $statuses ) ) ? array( 'Active' ) : $statuses;
	}
}

if ( ! function_exists( 'wp_easycart_subscription_mrr' ) ) {
	/**
	 * A subscription's monthly recurring revenue: its price × quantity, spread over a month ( a year is 12 months, a month 30.4375
	 * days ). The same rule as wp_easycart_subscription_mrr_sql().
	 *
	 * @since 6.0.3
	 * @param float  $price    Price per charge.
	 * @param int    $length   Every N periods.
	 * @param string $period   D, W, M, Y ( or day, week … ).
	 * @param int    $quantity Quantity.
	 * @return float
	 */
	function wp_easycart_subscription_mrr( $price, $length, $period, $quantity = 1 ) {
		$per_month = array(
			'Y' => 1 / 12,
			'W' => 30.4375 / 7,
			'D' => 30.4375,
			'H' => 730.5,
		);
		$key       = strtoupper( substr( trim( (string) $period ), 0, 1 ) );
		return (float) $price * max( 1, (int) $quantity ) * ( isset( $per_month[ $key ] ) ? $per_month[ $key ] : 1 ) / max( 1, (int) $length );
	}
}

if ( ! function_exists( 'wp_easycart_subscription_mrr_sql' ) ) {
	/**
	 * wp_easycart_subscription_mrr() as SQL, for an ec_subscription alias ( no status test: add wp_easycart_subscription_mrr_status_sql() ).
	 *
	 * @since 6.0.3
	 * @param string $alias Table alias ( letters and underscores ).
	 * @return string
	 */
	function wp_easycart_subscription_mrr_sql( $alias = 's' ) {
		$a = preg_replace( '/[^A-Za-z_]/', '', (string) $alias );
		$a = ( '' === $a ) ? 's' : $a;
		return "( {$a}.price * GREATEST( 1, COALESCE( {$a}.quantity, 1 ) ) * CASE UPPER( LEFT( {$a}.payment_period, 1 ) ) WHEN 'Y' THEN 1 / 12 WHEN 'W' THEN 30.4375 / 7 WHEN 'D' THEN 30.4375 WHEN 'H' THEN 730.5 ELSE 1 END / GREATEST( 1, COALESCE( {$a}.payment_length, 1 ) ) )";
	}
}

if ( ! function_exists( 'wp_easycart_subscription_mrr_status_sql' ) ) {
	/**
	 * " LOWER( alias.subscription_status ) IN ( … )" for wp_easycart_subscription_mrr_statuses(), prepared.
	 *
	 * @since 6.0.3
	 * @param string $alias Table alias.
	 * @return string
	 */
	function wp_easycart_subscription_mrr_status_sql( $alias = 's' ) {
		global $wpdb;
		$a        = preg_replace( '/[^A-Za-z_]/', '', (string) $alias );
		$a        = ( '' === $a ) ? 's' : $a;
		$statuses = array_values( array_unique( array_map( 'strtolower', wp_easycart_subscription_mrr_statuses() ) ) );
		return ' LOWER( ' . $a . '.subscription_status ) IN ' . $wpdb->prepare( '( ' . implode( ', ', array_fill( 0, count( $statuses ), '%s' ) ) . ' )', $statuses ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- one %s placeholder per status, bound by prepare().
	}
}

if ( ! function_exists( 'wp_easycart_subscription_member_status_sql' ) ) {
	/**
	 * " IN ( … )" for wp_easycart_subscription_member_statuses(), prepared, to follow a status column.
	 *
	 * @since 6.0.3
	 * @return string
	 */
	function wp_easycart_subscription_member_status_sql() {
		global $wpdb;
		$statuses = wp_easycart_subscription_member_statuses();
		return $wpdb->prepare( ' IN ( ' . implode( ', ', array_fill( 0, count( $statuses ), '%s' ) ) . ' )', $statuses ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- one %s placeholder per status, bound by prepare().
	}
}

if ( ! function_exists( 'wp_easycart_change_subscription_plan' ) ) {
	/**
	 * Move a Stripe subscription to another product ( and quantity ). The caller checks who may ask: My Account checks the
	 * owner and the plan's choices ( wp_easycart_customer_subscription_allows() ), WP EasyCart PRO the store manager.
	 *
	 * @since 6.0.3
	 * @param int   $subscription_id ec_subscription id.
	 * @param int   $product_id      The product to move to ( the same product changes only the quantity ).
	 * @param int   $quantity        New quantity; 0 keeps the current one.
	 * @param array $args            {
	 *     Optional.
	 *     @type string      $source customer | admin | an extension's own name.
	 *     @type object|null $user   The customer's ec_user, which also refreshes the name and email kept on the subscription.
	 * }
	 * @return true|WP_Error
	 */
	function wp_easycart_change_subscription_plan( $subscription_id, $product_id, $quantity = 0, $args = array() ) {
		global $wpdb;
		$args         = array_merge(
			array(
				'source' => 'customer',
				'user'   => null,
			),
			(array) $args
		);
		$ec_db        = new ec_db(); /* sets ec_db's database handle for its static calls */
		$subscription = ec_db::get_subscription_row( (int) $subscription_id );
		if ( ! $subscription || 'stripe' !== (string) $subscription->subscription_type || '' === (string) $subscription->stripe_subscription_id ) {
			return new WP_Error( 'subscription', 'Only Stripe subscriptions can change product.' );
		}
		$stripe = wp_easycart_subscription_prices::gateway();
		if ( ! $stripe ) {
			return new WP_Error( 'gateway', 'The store does not take payments through Stripe.' );
		}
		/* 6.0.3: a change made here applies now, so a change waiting for renewal or a payment is cancelled first. */
		if ( class_exists( 'wp_easycart_subscription_changes' ) ) {
			$replaced = wp_easycart_subscription_changes::cancel( (int) $subscription_id, ( 'customer' === $args['source'] ) ? 'replaced' : 'store' );
			if ( is_wp_error( $replaced ) ) {
				return $replaced;
			}
		}
		$products = $ec_db->get_product_list( $wpdb->prepare( ' WHERE product.product_id = %d', (int) $product_id ), '', '', '' );
		if ( empty( $products ) ) {
			return new WP_Error( 'product', 'The product was not found.' );
		}
		$product = new ec_product( $products[0] );
		if ( ! $product->is_subscription_item ) {
			return new WP_Error( 'product', 'The product is not a subscription.' );
		}

		$customer_id = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT stripe_customer_id FROM ec_user WHERE user_id = %d', (int) $subscription->user_id ) );
		$live        = $stripe->get_subscription( $customer_id, $subscription->stripe_subscription_id );
		if ( ! $live ) {
			return new WP_Error( 'stripe', 'Stripe did not find the subscription.' );
		}
		$item    = wp_easycart_subscription_prices::main_item_object( $live, (int) $subscription->product_id );
		$item_id = ( $item && isset( $item->id ) ) ? (string) $item->id : '';

		/* Only the quantity changes: the subscriber keeps the price they pay now ( a price raised since is for new subscribers ). */
		$price_id = '';
		if ( (int) $product->product_id === (int) $subscription->product_id && $item && isset( $item->price->id ) && '' !== (string) $item->price->id ) {
			$price_id                         = (string) $item->price->id;
			$product->stripe_default_price_id = $price_id;
			if ( '' === (string) $product->stripe_product_id && isset( $item->price->product ) ) {
				$product->stripe_product_id = ( is_object( $item->price->product ) && isset( $item->price->product->id ) ) ? (string) $item->price->product->id : (string) $item->price->product;
			}
		}
		if ( '' === $price_id ) {
			$price_id = wp_easycart_subscription_prices::ensure( $product, $stripe );
		}
		if ( ! $price_id ) {
			return new WP_Error( 'price', 'Stripe could not make a price for the product.' );
		}

		$previous_quantity = max( 1, (int) $subscription->quantity );
		$quantity          = ( (int) $quantity > 0 ) ? (int) $quantity : $previous_quantity;
		$user              = ( is_object( $args['user'] ) ) ? $args['user'] : (object) array(
			'user_id'            => (int) $subscription->user_id,
			'stripe_customer_id' => $customer_id,
		);
		if ( empty( $user->stripe_customer_id ) ) {
			$user->stripe_customer_id = $customer_id;
		}
		$updated = $stripe->update_subscription( $product, $user, null, $subscription->stripe_subscription_id, null, $product->subscription_prorate, null, $quantity, ( '' !== $item_id ) ? $item_id : false );
		if ( ! $updated ) {
			return new WP_Error( 'stripe_refused', 'Stripe refused the change.' );
		}

		if ( is_object( $args['user'] ) && isset( $args['user']->billing ) && is_object( $args['user']->billing ) ) {
			ec_db::update_subscription( (int) $subscription->subscription_id, $args['user'], $product, null, $quantity );
		} else {
			ec_db::upgrade_subscription( (int) $subscription->subscription_id, $product, $quantity );
		}

		$previous_period = (string) $subscription->payment_period . '/' . (int) $subscription->payment_length;
		/**
		 * A subscription moved to another product or quantity ( My Account, the account form, WP EasyCart PRO ).
		 *
		 * @since 6.0.3
		 * @param int   $subscription_id     ec_subscription id.
		 * @param int   $product_id          The product it is on now.
		 * @param int   $previous_product_id The product it was on.
		 * @param array $context {
		 *     @type int    $quantity               New quantity.
		 *     @type int    $previous_quantity      Quantity before.
		 *     @type bool   $interval_changed       How often it bills changed ( Stripe charges at once ).
		 *     @type string $source                 customer | admin | …
		 *     @type string $stripe_subscription_id Stripe subscription id.
		 *     @type string $stripe_price_id        The Stripe price it bills now.
		 * }
		 */
		do_action(
			'wp_easycart_subscription_plan_changed',
			(int) $subscription->subscription_id,
			(int) $product->product_id,
			(int) $subscription->product_id,
			array(
				'quantity'               => $quantity,
				'previous_quantity'      => $previous_quantity,
				'interval_changed'       => ( $previous_period !== (string) $product->subscription_bill_period . '/' . (int) $product->subscription_bill_length ),
				'source'                 => (string) $args['source'],
				'stripe_subscription_id' => (string) $subscription->stripe_subscription_id,
				'stripe_price_id'        => $price_id,
			)
		);
		return true;
	}
}
