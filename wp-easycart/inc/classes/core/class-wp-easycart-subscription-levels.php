<?php
/**
 * WP EasyCart — usage-based subscription levels ( 6.0.5 ).
 *
 * A subscription product can sell levels of one unit ( 1,000 / 10,000 / 25,000 subscribers ), each at its own flat price, or a
 * price per unit ( seats ) for a number the customer picks. WP EasyCart PRO's pricing tables write the levels on each tier
 * product ( ec_product.usage_levels ); everything that sells, bills and changes a subscription reads them here:
 *
 * - levels: the subscription page shows the chosen level and its price, the checkout bills the level's own Stripe price ( one flat
 *   price per level, quantity 1, made the first time it sells: usage_stripe ), the subscription records the level, and a level
 *   change swaps the Stripe price within the same product ( wp_easycart_subscription_changes ).
 * - units: the product's own price × the number of units, billed as the Stripe quantity ( as quantities always were ); the
 *   subscription records the number.
 *
 * Not Stripe's tiered prices and not metered billing: the customer chooses a level in advance.
 *
 * @package WP EasyCart
 * @since 6.0.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_subscription_levels' ) ) :

	/**
	 * Usage levels of subscription products.
	 */
	class wp_easycart_subscription_levels {

		/** The database version with the level columns ( wpeasycart_sql_6_0_5_usage_levels ). */
		const DB_VERSION = 132;

		/** The most levels a product sells. */
		const MAX_LEVELS = 20;

		/** Product levels read this request, product id => clean() answer ( null = none ). */
		private static $cache = array();

		/**
		 * The level / units the page's address asked for, when the page is drawn by AJAX ( requested() ).
		 *
		 * @var array
		 */
		private static $requested = array();

		/**
		 * Hooks.
		 */
		public static function init() {
			add_filter( 'wpeasycart_subscription_checkout_errors', array( __CLASS__, 'checkout_errors' ), 6, 3 );
			add_filter( 'wpeasycart_cart_errors', array( __CLASS__, 'error_notes' ) );
			add_action( 'wpeasycart_subscription_page_plan_choices', array( __CLASS__, 'print_picker' ), 20 );
			add_filter( 'wp_easycart_subscription_summary_choices', array( __CLASS__, 'summary_choice' ), 10, 2 ); /* the level under the item */
			add_filter( 'wp_easycart_subscription_summary_title', array( __CLASS__, 'summary_title' ), 10, 2 );
			add_action( 'wpeasycart_product_duplicated', array( __CLASS__, 'forget_copied_ids' ), 1, 1 );
		}

		/**
		 * Whether the level columns exist.
		 *
		 * @return bool
		 */
		public static function ready() {
			return (int) get_option( 'ec_option_db_new_version' ) >= self::DB_VERSION;
		}

		/**
		 * Forget what this request read ( after a product is saved ).
		 *
		 * @param int $product_id Product, or 0 for all.
		 */
		public static function forget( $product_id = 0 ) {
			if ( $product_id > 0 ) {
				unset( self::$cache[ (int) $product_id ] );
			} else {
				self::$cache = array();
			}
		}

		/* ------------------------------------------------------------------ a product's levels */

		/**
		 * Levels from their stored JSON, cleaned.
		 *
		 * @param string|array $raw Stored levels.
		 * @return array|null {
		 *     @type string $style  levels ( a flat price per level ) | units ( a price per unit × the number ).
		 *     @type array  $unit   one, many ( "subscriber", "subscribers" ).
		 *     @type array  $levels levels style: [ { id, value, label, price } ] by value, lowest first.
		 *     @type string $default levels style: the level the page starts on.
		 *     @type float  $min    units style: the fewest units ( 1 or more ).
		 *     @type float  $max    units style: the most units ( 0 = no limit ).
		 *     @type float  $step   units style: units come in steps of this.
		 *     @type string $change both ( more or fewer ) | up ( more only ) | off: what customers may change in My Account.
		 *     @type int    $plan   The pricing table ( subscription plan ) the levels come from, 0 for none.
		 *     @type int    $tier   The tier.
		 * } null when the product sells no levels.
		 */
		public static function clean( $raw ) {
			$in = is_array( $raw ) ? $raw : json_decode( (string) $raw, true );
			if ( ! is_array( $in ) || empty( $in['style'] ) ) {
				return null;
			}
			$style = ( 'units' === $in['style'] ) ? 'units' : 'levels';
			$unit  = ( isset( $in['unit'] ) && is_array( $in['unit'] ) ) ? $in['unit'] : array();
			$one   = isset( $unit['one'] ) ? sanitize_text_field( (string) $unit['one'] ) : '';
			$many  = isset( $unit['many'] ) ? sanitize_text_field( (string) $unit['many'] ) : '';
			$out   = array(
				'style'   => $style,
				'unit'    => array(
					'one'  => ( '' !== $one ) ? $one : $many,
					'many' => ( '' !== $many ) ? $many : $one,
				),
				'levels'  => array(),
				'default' => '',
				'min'     => 1,
				'max'     => 0,
				'step'    => 1,
				'change'  => ( isset( $in['change'] ) && in_array( $in['change'], array( 'both', 'up', 'off' ), true ) ) ? $in['change'] : 'both',
				'plan'    => isset( $in['plan'] ) ? (int) $in['plan'] : 0,
				'tier'    => isset( $in['tier'] ) ? (int) $in['tier'] : 0,
			);
			if ( 'units' === $style ) {
				$out['min']  = ( isset( $in['min'] ) && is_numeric( $in['min'] ) ) ? max( 1, (int) $in['min'] ) : 1;
				$out['max']  = ( isset( $in['max'] ) && is_numeric( $in['max'] ) ) ? max( 0, (int) $in['max'] ) : 0;
				$out['step'] = ( isset( $in['step'] ) && is_numeric( $in['step'] ) ) ? max( 1, (int) $in['step'] ) : 1;
				if ( $out['max'] > 0 && $out['max'] < $out['min'] ) {
					$out['max'] = $out['min'];
				}
				return $out;
			}
			$seen = array();
			foreach ( ( isset( $in['levels'] ) && is_array( $in['levels'] ) ) ? $in['levels'] : array() as $level ) {
				if ( ! is_array( $level ) || ! isset( $level['id'], $level['value'], $level['price'] ) || ! is_numeric( $level['value'] ) || ! is_numeric( $level['price'] ) ) {
					continue;
				}
				$id = self::clean_id( $level['id'] );
				if ( '' === $id || isset( $seen[ $id ] ) || (float) $level['price'] < 0 ) {
					continue;
				}
				$seen[ $id ]     = true;
				$out['levels'][] = array(
					'id'    => $id,
					'value' => (float) $level['value'],
					'label' => isset( $level['label'] ) ? sanitize_text_field( (string) $level['label'] ) : '',
					'price' => round( (float) $level['price'], 2 ),
				);
			}
			if ( ! $out['levels'] ) {
				return null;
			}
			usort(
				$out['levels'],
				function ( $a, $b ) {
					return ( $a['value'] === $b['value'] ) ? 0 : ( ( $a['value'] < $b['value'] ) ? -1 : 1 );
				}
			);
			$out['levels']  = array_slice( $out['levels'], 0, self::MAX_LEVELS );
			$default        = isset( $in['default'] ) ? self::clean_id( $in['default'] ) : '';
			$out['default'] = ( '' !== $default && self::find( $out, $default ) ) ? $default : $out['levels'][0]['id'];
			return $out;
		}

		/**
		 * A level id as stored ( letters, digits, - and _ ).
		 *
		 * @param mixed $id Id.
		 * @return string
		 */
		public static function clean_id( $id ) {
			return substr( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $id ) ), 0, 40 );
		}

		/**
		 * A product's levels.
		 *
		 * @param object|array|int $product ec_product, a product row or its id.
		 * @return array|null clean().
		 */
		public static function of( $product ) {
			if ( ! self::ready() ) {
				return null;
			}
			$get        = function ( $key ) use ( $product ) {
				if ( is_array( $product ) ) {
					return array_key_exists( $key, $product ) ? $product[ $key ] : null;
				}
				return ( is_object( $product ) && isset( $product->{$key} ) ) ? $product->{$key} : null;
			};
			$product_id = is_numeric( $product ) ? (int) $product : (int) $get( 'product_id' );
			if ( $product_id > 0 && array_key_exists( $product_id, self::$cache ) ) {
				return self::$cache[ $product_id ];
			}
			$raw = is_numeric( $product ) ? null : $get( 'usage_levels' );
			if ( null === $raw && $product_id > 0 ) {
				global $wpdb;
				$raw = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT usage_levels FROM ec_product WHERE product_id = %d', $product_id ) );
			}
			/**
			 * A product's usage levels ( 6.0.5 ).
			 *
			 * @since 6.0.5
			 * @param array|null $levels     clean() of the stored levels, null for none.
			 * @param int        $product_id Product.
			 */
			$levels = apply_filters( 'wp_easycart_subscription_levels', self::clean( $raw ), $product_id );
			$levels = ( is_array( $levels ) ) ? self::clean( $levels ) : null;
			if ( $product_id > 0 ) {
				self::$cache[ $product_id ] = $levels;
			}
			return $levels;
		}

		/**
		 * One level.
		 *
		 * @param array|null $levels of().
		 * @param string     $id     Level id.
		 * @return array|null { id, value, label, price }.
		 */
		public static function find( $levels, $id ) {
			if ( ! is_array( $levels ) || empty( $levels['levels'] ) ) {
				return null;
			}
			foreach ( $levels['levels'] as $level ) {
				if ( (string) $level['id'] === (string) $id ) {
					return $level;
				}
			}
			return null;
		}

		/**
		 * A level's position ( 0 = the lowest ), -1 when it is not one of these.
		 *
		 * @param array|null $levels of().
		 * @param string     $id     Level id.
		 * @return int
		 */
		public static function position( $levels, $id ) {
			if ( is_array( $levels ) && ! empty( $levels['levels'] ) ) {
				foreach ( array_values( $levels['levels'] ) as $i => $level ) {
					if ( (string) $level['id'] === (string) $id ) {
						return $i;
					}
				}
			}
			return -1;
		}

		/**
		 * A number of units as written ( 25,000, 2.5 ).
		 *
		 * @param float $value Value.
		 * @return string
		 */
		public static function number( $value ) {
			$value    = (float) $value;
			$decimals = ( abs( $value - round( $value ) ) < 0.00001 ) ? 0 : ( ( abs( $value * 10 - round( $value * 10 ) ) < 0.0001 ) ? 1 : 2 );
			return function_exists( 'number_format_i18n' ) ? number_format_i18n( $value, $decimals ) : number_format( $value, $decimals );
		}

		/**
		 * The unit's name for a number ( "subscriber" for 1, else "subscribers" ).
		 *
		 * @param array $levels of().
		 * @param float $value  The number.
		 * @return string
		 */
		public static function unit( $levels, $value ) {
			return ( abs( (float) $value - 1 ) < 0.00001 ) ? (string) $levels['unit']['one'] : (string) $levels['unit']['many'];
		}

		/**
		 * A level as customers read it: its own label, else "25,000 subscribers".
		 *
		 * @param array       $levels of().
		 * @param array|float $level  find() of a level, or a number of units.
		 * @return string
		 */
		public static function label( $levels, $level ) {
			if ( is_array( $level ) && '' !== (string) $level['label'] ) {
				return (string) $level['label'];
			}
			$value = is_array( $level ) ? (float) $level['value'] : (float) $level;
			$text  = trim( self::number( $value ) . ' ' . self::unit( $levels, $value ) );
			/**
			 * A usage level as customers read it.
			 *
			 * @since 6.0.5
			 * @param string      $text   "25,000 subscribers".
			 * @param array|float $level  The level ( or a number of units ).
			 * @param array       $levels The product's levels.
			 */
			return (string) apply_filters( 'wp_easycart_subscription_level_label', $text, $level, $levels );
		}

		/**
		 * A product title with its level ( the subscription page, the subscription, the order line ).
		 *
		 * @param string $title Product title.
		 * @param string $label label().
		 * @return string
		 */
		public static function title( $title, $label ) {
			$title = (string) $title;
			if ( '' === (string) $label ) {
				return $title;
			}
			/**
			 * A subscription's title with its level ( "Growth – Monthly · 25,000 subscribers" ).
			 *
			 * @since 6.0.5
			 * @param string $titled The title with the level.
			 * @param string $title  The product's title.
			 * @param string $label  The level.
			 */
			return (string) apply_filters( 'wp_easycart_subscription_level_title', $title . ' · ' . $label, $title, $label );
		}

		/**
		 * The units a customer may choose, kept in range and on a step.
		 *
		 * @param array $levels of() ( units style ).
		 * @param float $value  Asked for.
		 * @return int
		 */
		public static function units_in_range( $levels, $value ) {
			$value = max( (int) $levels['min'], (int) round( (float) $value ) );
			if ( $levels['max'] > 0 ) {
				$value = min( (int) $levels['max'], $value );
			}
			$step = max( 1, (int) $levels['step'] );
			if ( $step > 1 ) {
				$value = (int) $levels['min'] + (int) ( ceil( ( $value - (int) $levels['min'] ) / $step ) * $step );
				if ( $levels['max'] > 0 && $value > $levels['max'] ) {
					$value -= $step;
				}
			}
			return max( (int) $levels['min'], $value );
		}

		/* ------------------------------------------------------------------ the checkout session */

		/**
		 * The checkout session's data, or null.
		 *
		 * @return object|null
		 */
		private static function session() {
			return ( isset( $GLOBALS['ec_cart_data'] ) && is_object( $GLOBALS['ec_cart_data'] ) && isset( $GLOBALS['ec_cart_data']->cart_data ) && is_object( $GLOBALS['ec_cart_data']->cart_data ) ) ? $GLOBALS['ec_cart_data']->cart_data : null;
		}

		/**
		 * The level the session chose for this product ( its own choice only ), else the product's default level.
		 *
		 * @param object|array|int $product The product.
		 * @return array|null find() of the level; null when the product sells no levels ( or sells units ).
		 */
		public static function chosen( $product ) {
			$levels = self::of( $product );
			if ( ! $levels || 'levels' !== $levels['style'] ) {
				return null;
			}
			$product_id = is_numeric( $product ) ? (int) $product : ( is_array( $product ) ? (int) $product['product_id'] : (int) $product->product_id );
			$data       = self::session();
			$owner      = class_exists( 'wp_easycart_subscription_options' ) ? wp_easycart_subscription_options::owner() : 0;
			if ( $data && property_exists( $data, 'subscription_level' ) && '' !== (string) $data->subscription_level && $owner === $product_id ) {
				$level = self::find( $levels, (string) $data->subscription_level );
				if ( $level ) {
					return $level;
				}
			}
			return self::find( $levels, $levels['default'] );
		}

		/**
		 * Remember a level for this product in the session ( the caller saves it ).
		 *
		 * @param int    $product_id Product.
		 * @param string $level_id   Level.
		 * @return bool The product sells that level.
		 */
		public static function choose( $product_id, $level_id ) {
			$data   = self::session();
			$levels = self::of( (int) $product_id );
			if ( ! $data || ! property_exists( $data, 'subscription_level' ) || ! $levels || 'levels' !== $levels['style'] || ! self::find( $levels, $level_id ) ) {
				return false;
			}
			if ( class_exists( 'wp_easycart_subscription_options' ) && wp_easycart_subscription_options::owner() !== (int) $product_id ) {
				wp_easycart_subscription_options::claim( (int) $product_id );
			}
			$data->subscription_level = self::clean_id( $level_id );
			return true;
		}

		/**
		 * The level / units of the page's own address, for a subscription page drawn by AJAX ( ec_ajax_get_dynamic_cart_page: cache
		 * prevention, the one-page checkout ), whose request carries only the page and product. from_request() reads them there.
		 *
		 * @param string $level ec_level.
		 * @param int    $units ec_units.
		 */
		public static function requested( $level, $units ) {
			self::$requested = array(
				'level' => self::clean_id( (string) $level ),
				'units' => max( 0, (int) $units ),
			);
		}

		/**
		 * The subscription page was opened with a level or a number of units ( a pricing table's button: ec_level, ec_units ): keep
		 * it in the session for this product.
		 *
		 * @param object|array $product The product row.
		 */
		public static function from_request( $product ) {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- a choice of what to buy, read like the page's own subscription parameter.
			$level = isset( $_GET['ec_level'] ) ? self::clean_id( sanitize_text_field( wp_unslash( $_GET['ec_level'] ) ) ) : '';
			$units = isset( $_GET['ec_units'] ) ? (int) $_GET['ec_units'] : 0;
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			if ( '' === $level && $units <= 0 && self::$requested ) {
				$level = (string) self::$requested['level'];
				$units = (int) self::$requested['units'];
			}
			if ( '' === $level && $units <= 0 ) {
				return;
			}
			$levels     = self::of( $product );
			$product_id = is_array( $product ) ? (int) $product['product_id'] : (int) $product->product_id;
			$data       = self::session();
			if ( ! $levels || ! $data ) {
				return;
			}
			if ( 'levels' === $levels['style'] && '' !== $level ) {
				if ( self::choose( $product_id, $level ) && isset( $GLOBALS['ec_cart_data'] ) ) {
					$GLOBALS['ec_cart_data']->save_session_to_db();
				}
			} elseif ( 'units' === $levels['style'] && $units > 0 ) {
				if ( class_exists( 'wp_easycart_subscription_options' ) && wp_easycart_subscription_options::owner() !== $product_id ) {
					wp_easycart_subscription_options::claim( $product_id );
				}
				$data->subscription_quantity = self::units_in_range( $levels, $units );
				if ( isset( $GLOBALS['ec_cart_data'] ) ) {
					$GLOBALS['ec_cart_data']->save_session_to_db();
				}
			}
		}

		/**
		 * A product row as the subscription checkout sells it: a levels product's price is the chosen level's, its title names the
		 * level, and the row carries usage_level / usage_label / usage_value. Call before the row becomes an ec_product.
		 *
		 * @param array|object $row Product row ( get_product_list() gives arrays ).
		 * @return array|object The row.
		 */
		public static function apply( $row ) {
			$level = self::chosen( $row );
			if ( ! $level ) {
				return $row;
			}
			$levels = self::of( $row );
			$label  = self::label( $levels, $level );
			$set    = array(
				'price'       => (float) $level['price'],
				'title'       => self::title( is_array( $row ) ? $row['title'] : $row->title, $label ),
				'usage_level' => (string) $level['id'],
				'usage_label' => $label,
				'usage_value' => (float) $level['value'],
			);
			foreach ( $set as $key => $value ) {
				if ( is_array( $row ) ) {
					$row[ $key ] = $value;
				} else {
					$row->{$key} = $value;
				}
			}
			return $row;
		}

		/* ------------------------------------------------------------------ Stripe */

		/**
		 * Stripe price ids made for levels, by mode then level.
		 *
		 * @param int $product_id Product.
		 * @return array
		 */
		private static function stripe_ids( $product_id ) {
			global $wpdb;
			$raw = $wpdb->get_var( $wpdb->prepare( 'SELECT usage_stripe FROM ec_product WHERE product_id = %d', (int) $product_id ) );
			$ids = json_decode( (string) $raw, true );
			return is_array( $ids ) ? $ids : array();
		}

		/**
		 * The Stripe price stored for a level in the mode in use ( '' for none ).
		 *
		 * @param int    $product_id Product.
		 * @param string $level_id   Level.
		 * @param bool   $sandbox    Test mode.
		 * @return string
		 */
		public static function stripe_price_id( $product_id, $level_id, $sandbox ) {
			$ids  = self::stripe_ids( $product_id );
			$mode = $sandbox ? 'test' : 'live';
			return ( isset( $ids[ $mode ][ $level_id ] ) && is_string( $ids[ $mode ][ $level_id ] ) ) ? $ids[ $mode ][ $level_id ] : '';
		}

		/**
		 * Keep a level's Stripe price for the mode in use.
		 *
		 * @param int    $product_id Product.
		 * @param string $level_id   Level.
		 * @param bool   $sandbox    Test mode.
		 * @param string $price_id   Stripe price.
		 */
		public static function store_stripe_price( $product_id, $level_id, $sandbox, $price_id ) {
			global $wpdb;
			$ids                       = self::stripe_ids( $product_id );
			$mode                      = $sandbox ? 'test' : 'live';
			$ids[ $mode ]              = ( isset( $ids[ $mode ] ) && is_array( $ids[ $mode ] ) ) ? $ids[ $mode ] : array();
			$ids[ $mode ][ $level_id ] = (string) $price_id;
			$wpdb->update( 'ec_product', array( 'usage_stripe' => wp_json_encode( $ids ) ), array( 'product_id' => (int) $product_id ) );
		}

		/**
		 * Forget a copied product's level prices ( a duplicate must not bill the original's ).
		 *
		 * @param int $product_id The copy.
		 */
		public static function forget_copied_ids( $product_id ) {
			global $wpdb;
			if ( self::ready() ) {
				$wpdb->update( 'ec_product', array( 'usage_stripe' => '' ), array( 'product_id' => (int) $product_id ) );
			}
		}

		/* ------------------------------------------------------------------ subscriptions */

		/**
		 * Record what a new subscription bills: the level ( levels style ) or the number of units ( units style ).
		 *
		 * @param int          $subscription_id Subscription.
		 * @param object|array $product         The product as sold ( apply()'s row, or ec_product made from it ).
		 * @param int          $quantity        Quantity.
		 */
		public static function record( $subscription_id, $product, $quantity = 1 ) {
			global $wpdb;
			$levels = self::of( $product );
			if ( ! $levels || (int) $subscription_id <= 0 ) {
				return;
			}
			$get = function ( $key ) use ( $product ) {
				return is_array( $product ) ? ( isset( $product[ $key ] ) ? $product[ $key ] : '' ) : ( isset( $product->{$key} ) ? $product->{$key} : '' );
			};
			if ( 'units' === $levels['style'] ) {
				$value  = max( 1, (int) $quantity );
				$fields = array(
					'usage_level' => '',
					'usage_value' => $value,
					'usage_label' => self::label( $levels, $value ),
				);
			} else {
				$level = self::find( $levels, (string) $get( 'usage_level' ) );
				if ( ! $level ) {
					$level = self::chosen( $product );
				}
				if ( ! $level ) {
					return;
				}
				$fields = array(
					'usage_level' => (string) $level['id'],
					'usage_value' => (float) $level['value'],
					'usage_label' => self::label( $levels, $level ),
				);
			}
			$wpdb->update( 'ec_subscription', $fields, array( 'subscription_id' => (int) $subscription_id ) );
			if ( class_exists( 'wp_easycart_cache' ) && method_exists( 'wp_easycart_cache', 'forget_subscription' ) ) {
				wp_easycart_cache::forget_subscription( (int) $subscription_id );
			}
		}

		/**
		 * The level a subscription bills.
		 *
		 * @param object $row ec_subscription row ( or ec_subscription ).
		 * @return array { level ( id, '' for units ), value, label }, empty values when it bills none.
		 */
		public static function of_subscription( $row ) {
			return array(
				'level' => ( is_object( $row ) && isset( $row->usage_level ) ) ? (string) $row->usage_level : '',
				'value' => ( is_object( $row ) && isset( $row->usage_value ) ) ? (float) $row->usage_value : 0,
				'label' => ( is_object( $row ) && isset( $row->usage_label ) ) ? (string) $row->usage_label : '',
			);
		}

		/**
		 * Add the level columns to a subscription row read without them ( ec_db::get_subscription_row() lists its columns ).
		 *
		 * @param object $row ec_subscription row.
		 * @return object The row.
		 */
		public static function fill( $row ) {
			global $wpdb;
			if ( ! is_object( $row ) || isset( $row->usage_level ) || empty( $row->subscription_id ) || ! self::ready() ) {
				return $row;
			}
			$usage            = $wpdb->get_row( $wpdb->prepare( 'SELECT usage_level, usage_value, usage_label FROM ec_subscription WHERE subscription_id = %d', (int) $row->subscription_id ) );
			$row->usage_level = $usage ? (string) $usage->usage_level : '';
			$row->usage_value = $usage ? (float) $usage->usage_value : 0;
			$row->usage_label = $usage ? (string) $usage->usage_label : '';
			return $row;
		}

		/**
		 * The level nearest a number of units: the first level with at least that many, else the largest.
		 *
		 * @param array $levels of() ( levels style ).
		 * @param float $value  Units.
		 * @return array|null find() of a level.
		 */
		public static function nearest( $levels, $value ) {
			if ( ! is_array( $levels ) || empty( $levels['levels'] ) ) {
				return null;
			}
			foreach ( $levels['levels'] as $level ) {
				if ( (float) $level['value'] >= (float) $value ) {
					return $level;
				}
			}
			return $levels['levels'][ count( $levels['levels'] ) - 1 ];
		}

		/**
		 * The level a subscription moves to on a product: the one asked for, else the one it has, else the level nearest the units
		 * it has ( a tier that does not sell them ). Null when the product sells no levels.
		 *
		 * @param int    $product_id The product it moves to.
		 * @param string $level_id   Asked for ( '' = keep ).
		 * @param object $row        ec_subscription row ( usage_level, usage_value ).
		 * @return array|null find() of the level.
		 */
		public static function resolve( $product_id, $level_id, $row ) {
			$levels = self::of( (int) $product_id );
			if ( ! $levels || 'levels' !== $levels['style'] ) {
				return null;
			}
			$level_id = self::clean_id( $level_id );
			if ( '' !== $level_id ) {
				return self::find( $levels, $level_id );
			}
			$have = self::of_subscription( $row );
			$same = ( '' !== $have['level'] ) ? self::find( $levels, $have['level'] ) : null;
			return $same ? $same : self::nearest( $levels, $have['value'] );
		}

		/**
		 * After a subscription moved ( a change applied, or made by the store ): what it bills now. A level sets its price and title
		 * and is recorded; units record the quantity; a product without levels clears them.
		 *
		 * @param int        $subscription_id Subscription.
		 * @param object     $product         The product it is on now ( ec_product or a row ).
		 * @param int        $quantity        Quantity.
		 * @param array|null $level           resolve() of the level.
		 */
		public static function after_change( $subscription_id, $product, $quantity, $level ) {
			global $wpdb;
			if ( ! self::ready() || (int) $subscription_id <= 0 ) {
				return;
			}
			$levels = self::of( $product );
			if ( $levels && 'units' === $levels['style'] ) {
				self::record( $subscription_id, $product, $quantity );
				return;
			}
			if ( $levels && $level ) {
				$label = self::label( $levels, $level );
				$wpdb->update(
					'ec_subscription',
					array(
						'price'       => (float) $level['price'],
						'title'       => self::title( isset( $product->title ) ? (string) $product->title : '', $label ),
						'usage_level' => (string) $level['id'],
						'usage_value' => (float) $level['value'],
						'usage_label' => $label,
					),
					array( 'subscription_id' => (int) $subscription_id )
				);
			} else {
				$wpdb->update(
					'ec_subscription',
					array(
						'usage_level' => '',
						'usage_value' => 0,
						'usage_label' => '',
					),
					array( 'subscription_id' => (int) $subscription_id )
				);
			}
			if ( class_exists( 'wp_easycart_cache' ) && method_exists( 'wp_easycart_cache', 'forget_subscription' ) ) {
				wp_easycart_cache::forget_subscription( (int) $subscription_id );
			}
		}

		/**
		 * The price a product bills at a subscription's level ( the plan list shows each plan at the subscriber's level ).
		 *
		 * @param object $choice A product row ( product_id, price, title ).
		 * @param object $row    ec_subscription row or ec_subscription.
		 * @return array { price, label } ( the product's own price and '' when it sells no levels ).
		 */
		public static function at_subscription_level( $choice, $row ) {
			$level = self::resolve( (int) $choice->product_id, '', $row );
			if ( ! $level ) {
				return array(
					'price' => (float) $choice->price,
					'label' => '',
				);
			}
			return array(
				'price' => (float) $level['price'],
				'label' => self::label( self::of( (int) $choice->product_id ), $level ),
			);
		}

		/**
		 * The levels a subscriber may move to in My Account: the product's levels, less the one they have, as the store allows
		 * ( up only, or none ).
		 *
		 * @param object $row ec_subscription row.
		 * @return array [ { id, value, label, price, text, direction ( up | down ) } ].
		 */
		public static function choices_for( $row ) {
			$levels = self::of( (int) $row->product_id );
			if ( ! $levels || 'levels' !== $levels['style'] || 'off' === $levels['change'] ) {
				return array();
			}
			$current = self::find( $levels, isset( $row->usage_level ) ? (string) $row->usage_level : '' );
			$value   = $current ? (float) $current['value'] : ( isset( $row->usage_value ) ? (float) $row->usage_value : 0 );
			$out     = array();
			foreach ( $levels['levels'] as $level ) {
				if ( $current && $level['id'] === $current['id'] ) {
					continue;
				}
				$direction = ( (float) $level['value'] > $value ) ? 'up' : 'down';
				if ( 'up' === $levels['change'] && 'down' === $direction ) {
					continue;
				}
				$out[] = array_merge(
					$level,
					array(
						'text'      => self::label( $levels, $level ),
						'direction' => $direction,
					)
				);
			}
			return $out;
		}

		/**
		 * "Change subscribers": the words for the Manage card's button.
		 *
		 * @param int $product_id Product.
		 * @return string
		 */
		public static function change_label( $product_id ) {
			$levels = self::of( (int) $product_id );
			$many   = $levels ? (string) $levels['unit']['many'] : '';
			return self::text( 'account_subscriptions', 'subscription_level_change', 'Change [unit]', array( 'unit' => $many ) );
		}

		/**
		 * My Account's level field in Change plan: the level the subscription has and the ones it may move to, each with its price
		 * and when it starts ( more now, fewer at the renewal ). ec-account-subscriptions.js sends the choice with Review change.
		 *
		 * @param ec_subscription $sub The subscription.
		 */
		public static function print_account_field( $sub ) {
			$levels  = self::of( (int) $sub->product_id );
			$choices = self::choices_for( $sub );
			if ( ! $levels || ! $choices ) {
				return;
			}
			$id      = 'ec_level_' . (int) $sub->subscription_id;
			$current = self::find( $levels, (string) $sub->usage_level );
			$renews  = method_exists( $sub, 'get_next_payment_timestamp' ) ? (int) $sub->get_next_payment_timestamp() : 0;
			$money   = function ( $amount ) {
				return ( isset( $GLOBALS['currency'] ) && is_object( $GLOBALS['currency'] ) ) ? wp_strip_all_tags( html_entity_decode( (string) $GLOBALS['currency']->get_currency_display( $amount ), ENT_QUOTES, 'UTF-8' ) ) : number_format( (float) $amount, 2 );
			};
			$now     = class_exists( 'wp_easycart_subscription_changes' ) ? wp_easycart_subscription_changes::text( 'subscription_change_note_now' ) : '';
			$later   = ( class_exists( 'wp_easycart_subscription_changes' ) && $renews > 0 ) ? wp_easycart_subscription_changes::text( 'subscription_change_note_renewal', array( 'date' => wp_easycart_subscription_changes::date( $renews ) ) ) : '';
			$out     = '<div class="ec_account_subscription_v2_field ec_account_subscription_v2_level">';
			$out    .= '<label class="ec_account_subscription_v2_label" for="' . esc_attr( $id ) . '">' . esc_html( self::change_label( (int) $sub->product_id ) ) . '</label>';
			$out    .= '<select id="' . esc_attr( $id ) . '" name="ec_level" class="ec_account_subscription_v2_level_select">';
			$current_text = $current ? self::label( $levels, $current ) . ' – ' . $money( $current['price'] ) : (string) $sub->usage_label;
			$out    .= '<option value="' . esc_attr( (string) $sub->usage_level ) . '" selected>' . esc_html( $current_text . ' · ' . self::text( 'account_subscriptions', 'subscription_level_current', 'current' ) ) . '</option>';
			foreach ( $choices as $choice ) {
				$note = ( 'up' === $choice['direction'] ) ? $now : $later;
				$out .= '<option value="' . esc_attr( $choice['id'] ) . '" data-when="' . ( 'up' === $choice['direction'] ? 'now' : 'renewal' ) . '">' . esc_html( $choice['text'] . ' – ' . $money( $choice['price'] ) . ( '' !== $note ? ' · ' . $note : '' ) ) . '</option>';
			}
			$out .= '</select>';
			if ( 'both' === $levels['change'] ) {
				$hint = self::text( 'account_subscriptions', 'subscription_level_hint', 'A higher amount starts now, with the rest of this period charged today. A lower amount starts at your next renewal.' );
			} else {
				$hint = self::text( 'account_subscriptions', 'subscription_level_hint_up', 'A higher amount starts now, with the rest of this period charged today.' );
			}
			$out .= '<p class="ec_account_subscription_v2_hint">' . esc_html( $hint ) . '</p></div>';
			echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped as it was built.
		}

		/* ------------------------------------------------------------------ the subscription page */

		/**
		 * The checkout's errors: units out of the product's range.
		 *
		 * @param array  $codes    Error codes so far.
		 * @param object $product  ec_product.
		 * @param int    $quantity The quantity bought.
		 * @return array
		 */
		public static function checkout_errors( $codes, $product, $quantity = 0 ) {
			$levels = is_object( $product ) ? self::of( $product ) : null;
			if ( ! $levels || 'units' !== $levels['style'] ) {
				return $codes;
			}
			$quantity = (int) $quantity;
			$data     = self::session();
			if ( $quantity <= 0 && $data && '' !== (string) $data->subscription_quantity ) {
				$quantity = (int) $data->subscription_quantity;
			}
			if ( $quantity > 0 && self::units_in_range( $levels, $quantity ) !== $quantity ) {
				$codes   = (array) $codes;
				$codes[] = 'subscription_units';
			}
			return $codes;
		}

		/**
		 * The wording of the checkout's level errors.
		 *
		 * @param array $notes code => message.
		 * @return array
		 */
		public static function error_notes( $notes ) {
			$notes                       = (array) $notes;
			$notes['subscription_units'] = self::text( 'cart_login', 'subscription_units_range', 'Choose an amount this plan includes.' );
			return $notes;
		}

		/**
		 * A phrase from the store's language file, else the English.
		 *
		 * @param string $section  Section.
		 * @param string $key      Key.
		 * @param string $fallback English.
		 * @param array  $fill     [token] => value.
		 * @return string
		 */
		public static function text( $section, $key, $fallback, $fill = array() ) {
			$text = function_exists( 'wp_easycart_language' ) ? (string) wp_easycart_language()->get_text( $section, $key ) : '';
			$text = ( '' === trim( $text ) || $key === $text ) ? $fallback : $text;
			foreach ( $fill as $token => $value ) {
				$text = str_replace( '[' . $token . ']', (string) $value, $text );
			}
			return $text;
		}

		/**
		 * Filter wp_easycart_subscription_summary_choices: the level chosen, as a line under the item that opens the list of levels
		 * ( each with its price ); a choice opens the page again with ec_level.
		 *
		 * @param array  $lines   Choices so far.
		 * @param object $product ec_product.
		 * @return array
		 */
		public static function summary_choice( $lines, $product ) {
			$levels = is_object( $product ) ? self::of( $product ) : null;
			if ( ! $levels || 'levels' !== $levels['style'] || ! class_exists( 'wp_easycart_subscription_choices' ) ) {
				return $lines;
			}
			$chosen = self::chosen( $product );
			$url    = function_exists( 'wpeasycart_links' ) ? (string) wpeasycart_links()->get_cart_page( 'subscription_info', array( 'subscription' => $product->model_number ) ) : '';
			if ( ! $chosen || '' === $url ) {
				return $lines;
			}
			$form    = wp_easycart_subscription_choices::form_of( $url );
			$options = array();
			foreach ( $levels['levels'] as $level ) {
				/* each level's price with its period, as the item's own price reads ( $1,335.00/year ) */
				$price     = method_exists( $product, 'get_option_price_formatted' ) ? $product->get_option_price_formatted( $level['price'] ) : $GLOBALS['currency']->get_currency_display( $level['price'] );
				$options[] = array(
					'value'    => $level['id'],
					'text'     => self::label( $levels, $level ) . ' – ' . wp_strip_all_tags( html_entity_decode( (string) $price, ENT_QUOTES, 'UTF-8' ) ),
					'selected' => ( $level['id'] === $chosen['id'] ),
				);
			}
			$lines[] = array(
				'key'     => 'level',
				'order'   => 10, /* the level first, then WP EasyCart PRO's billing ( 20 ) */
				'label'   => self::text( 'cart_login', 'subscription_level_choose', 'How many [unit] do you need?', array( 'unit' => (string) $levels['unit']['many'] ) ),
				'text'    => self::label( $levels, $chosen ),
				'action'  => $form['action'],
				'hidden'  => $form['hidden'],
				'name'    => 'ec_level',
				'options' => $options,
			);
			return $lines;
		}

		/**
		 * Filter wp_easycart_subscription_summary_title: the item's title without its level, which the line under it names.
		 *
		 * @param string $title   Title ( "Growth – Monthly · 25,000 subscribers" ).
		 * @param object $product ec_product.
		 * @return string
		 */
		public static function summary_title( $title, $product ) {
			$levels = is_object( $product ) ? self::of( $product ) : null;
			$chosen = ( $levels && 'levels' === $levels['style'] ) ? self::chosen( $product ) : null;
			if ( ! $chosen ) {
				return $title;
			}
			$tail = ' · ' . self::label( $levels, $chosen );
			return ( strlen( $title ) > strlen( $tail ) && substr( $title, -strlen( $tail ) ) === $tail ) ? substr( $title, 0, -strlen( $tail ) ) : $title;
		}

		/**
		 * The subscription page's level at the top of the page, for a store's copy of the template without the summary's choices
		 * ( a GET form that opens the page again with ec_level, as the quantity's Update does ).
		 *
		 * @param object $product ec_product.
		 */
		public static function print_picker( $product ) {
			if ( class_exists( 'wp_easycart_subscription_choices' ) && wp_easycart_subscription_choices::in_summary() ) {
				return; /* the summary shows it under the item ( summary_choice() ) */
			}
			$levels = is_object( $product ) ? self::of( $product ) : null;
			if ( ! $levels || 'levels' !== $levels['style'] ) {
				return;
			}
			$chosen = self::chosen( $product );
			$url    = function_exists( 'wpeasycart_links' ) ? (string) wpeasycart_links()->get_cart_page( 'subscription_info', array( 'subscription' => $product->model_number ) ) : '';
			if ( ! $chosen || '' === $url ) {
				return;
			}
			$query = array();
			$parts = wp_parse_url( $url );
			if ( isset( $parts['query'] ) ) {
				parse_str( $parts['query'], $query );
			}
			$action = strtok( $url, '?' );
			$id     = 'ec_subscription_level_' . (int) $product->product_id;
			$many   = (string) $levels['unit']['many'];
			$out    = '<form class="ec_subscription_level" method="get" action="' . esc_url( $action ) . '">';
			foreach ( $query as $name => $value ) {
				if ( 'ec_level' !== $name && is_scalar( $value ) ) {
					$out .= '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" />';
				}
			}
			$out .= '<label class="ec_subscription_level_label" for="' . esc_attr( $id ) . '">' . esc_html( self::text( 'cart_login', 'subscription_level_choose', 'How many [unit] do you need?', array( 'unit' => $many ) ) ) . '</label>';
			$out .= '<span class="ec_subscription_level_row"><select id="' . esc_attr( $id ) . '" name="ec_level" class="ec_subscription_level_select">';
			foreach ( $levels['levels'] as $level ) {
				$out .= '<option value="' . esc_attr( $level['id'] ) . '"' . selected( $level['id'], $chosen['id'], false ) . '>' . esc_html( self::label( $levels, $level ) . ' – ' . $GLOBALS['currency']->get_currency_display( $level['price'] ) ) . '</option>';
			}
			$out .= '</select><button type="submit" class="ec_cart_button ec_subscription_level_update">' . esc_html( self::text( 'cart_login', 'subscription_level_update', 'Update' ) ) . '</button></span></form>';
			echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped as it was built.
		}
	}

	wp_easycart_subscription_levels::init();

endif;

if ( ! function_exists( 'wp_easycart_subscription_quantity_allowed' ) ) {
	/**
	 * Whether a subscription is sold by quantity: the store does not keep subscriptions to one each ( ec_option_subscription_one_only ),
	 * or the product's price is per unit ( a usage bar of seats: the quantity is the number of units ).
	 *
	 * @since 6.0.5
	 * @param object|array|int $product The product ( ec_product, a row ) or its id.
	 * @return bool
	 */
	function wp_easycart_subscription_quantity_allowed( $product ) {
		$allowed = ! get_option( 'ec_option_subscription_one_only' );
		if ( ! $allowed && class_exists( 'wp_easycart_subscription_levels' ) ) {
			$levels  = wp_easycart_subscription_levels::of( $product );
			$allowed = ( $levels && 'units' === $levels['style'] );
		}
		/**
		 * Whether a subscription product is sold by quantity.
		 *
		 * @since 6.0.5
		 * @param bool             $allowed Sold by quantity.
		 * @param object|array|int $product The product.
		 */
		return (bool) apply_filters( 'wp_easycart_subscription_quantity_allowed', $allowed, $product );
	}
}
