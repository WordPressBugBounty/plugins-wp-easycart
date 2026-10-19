<?php
/**
 * WP EasyCart — the currency an order was placed in ( 6.0.5, Matt's H2 ).
 *
 * Every amount on an order used to be printed with the store's currency settings of the moment: a store that moved from USD to
 * EUR showed every past order in €, and a shopper browsing in another currency ( the currency switcher ) saw an order converted
 * at today's rate. Orders are kept in the store's base currency, so an order now keeps the format it was placed in:
 * ec_order.currency_code and ec_order.currency_format ( JSON: code, symbol, before, space, neg, decimals, dec, group, show_code,
 * and, when the shopper browsed in another currency, shown and rate ), recorded when the order is made ( stamp() ). Orders
 * placed before 6.0.5 were given the store's settings on the update ( EC_UPGRADE_DB 130 ).
 *
 * Printing: money( $order, $amount ) formats one amount in the order's own format, never converted; push( $order ) / pop() put the
 * order's format on ec_currency for a whole render ( an email, a PDF, a page about the order ), so templates that call
 * $GLOBALS['currency']->get_currency_display() follow it too. An order in the store's current format prints exactly as before.
 *
 * @package WP_EasyCart
 * @since   6.0.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_order_currency' ) ) :

	/**
	 * An order's currency.
	 */
	class wp_easycart_order_currency {

		/** The database update that adds ec_order.currency_code / currency_format. */
		const DB = 130;

		/** Option: when the orders placed before 6.0.5 were given the store's format. */
		const BACKFILLED = 'wp_easycart_order_currency_backfilled';

		/**
		 * Formats read this request, by order id.
		 *
		 * @var array
		 */
		private static $cache = array();

		/**
		 * The ec_currency settings put aside by push(), newest last.
		 *
		 * @var array
		 */
		private static $stack = array();

		/**
		 * The order columns exist ( WP EasyCart 6.0.5's database update ran ).
		 *
		 * @return bool
		 */
		public static function ready() {
			return (int) get_option( 'ec_option_db_new_version' ) >= self::DB;
		}

		/**
		 * Hooks.
		 */
		public static function init() {
			/* Orders made outside ec_db's checkout inserts ( WP EasyCart PRO's New order ) are recorded too. */
			add_action( 'wp_easycart_admin_order_created', array( __CLASS__, 'stamp' ), 1, 1 );
			add_action( 'wpeasycart_order_inserted', array( __CLASS__, 'stamp' ), 1, 1 );
			add_action( 'admin_init', array( __CLASS__, 'ajax_scope' ), 0 );
			add_action( 'admin_init', array( __CLASS__, 'maybe_backfill' ), 1 );
		}

		/**
		 * The orders placed before the update get the store's format once, on the first admin request after it: the database step does
		 * it on an update from 6.0.4, but a site that had already recorded 6.0.5 gets the columns from dbDelta without the step.
		 */
		public static function maybe_backfill() {
			if ( self::ready() && ! get_option( self::BACKFILLED ) ) {
				self::backfill();
			}
		}

		/**
		 * An admin AJAX request about one order ( the order screen's and the orders list's: Quick view, Quick edit, status,
		 * lines, refunds … ) prints that order's amounts in its own currency. Formatting only: each handler checks its own
		 * nonce and capability.
		 */
		public static function ajax_scope() {
			// phpcs:disable WordPress.Security.NonceVerification -- read only to choose how amounts are written; the handler checks the request.
			if ( ! wp_doing_ajax() || empty( $_REQUEST['order_id'] ) || empty( $_REQUEST['action'] ) || ! is_scalar( $_REQUEST['order_id'] ) ) {
				return;
			}
			$action = sanitize_key( wp_unslash( $_REQUEST['action'] ) );
			if ( false === strpos( $action, 'order' ) ) {
				return;
			}
			self::push( absint( $_REQUEST['order_id'] ) );
			// phpcs:enable WordPress.Security.NonceVerification
		}

		/**
		 * Read the formats of these orders in one query ( a list page ).
		 *
		 * @param array $orders Rows ( order_id ) or ids.
		 */
		public static function prime( $orders ) {
			global $wpdb;
			if ( ! self::ready() ) {
				return;
			}
			$ids = array();
			foreach ( (array) $orders as $order ) {
				$id = is_object( $order ) ? ( isset( $order->order_id ) ? (int) $order->order_id : 0 ) : (int) $order;
				if ( $id > 0 && ! array_key_exists( $id, self::$cache ) ) {
					$ids[] = $id;
				}
			}
			if ( ! $ids ) {
				return;
			}
			foreach ( $ids as $id ) {
				self::$cache[ $id ] = null;
			}
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- the placeholders are built for the ids.
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT order_id, currency_format FROM ec_order WHERE order_id IN ( ' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ' )', $ids ) );
			foreach ( (array) $rows as $row ) {
				self::$cache[ (int) $row->order_id ] = self::decode( (string) $row->currency_format );
			}
		}

		/**
		 * The store's money format as saved ( Settings › Store details › Currency ), never a shopper's display currency.
		 *
		 * @return array code, symbol, before, space, neg, decimals, dec, group, show_code.
		 */
		public static function store_format() {
			$decimals = get_option( 'ec_option_currency_decimal_places' );
			$symbol   = (string) get_option( 'ec_option_currency' );
			return array(
				'code'      => strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', (string) get_option( 'ec_option_base_currency', 'USD' ) ), 0, 3 ) ),
				'symbol'    => false === strpos( $symbol, '&' ) ? $symbol : html_entity_decode( $symbol, ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
				'before'    => get_option( 'ec_option_currency_symbol_location' ) ? 1 : 0,
				'space'     => get_option( 'ec_option_currency_symbol_space' ) ? 1 : 0,
				'neg'       => get_option( 'ec_option_currency_negative_location' ) ? 1 : 0,
				'decimals'  => ( '' === (string) $decimals || ! is_numeric( $decimals ) || $decimals < 0 ) ? 2 : (int) $decimals,
				'dec'       => (string) get_option( 'ec_option_currency_decimal_symbol' ),
				'group'     => (string) get_option( 'ec_option_currency_thousands_seperator' ),
				'show_code' => get_option( 'ec_option_show_currency_code' ) ? 1 : 0,
			);
		}

		/**
		 * The format as stored ( compact JSON ).
		 *
		 * @param array $format Format.
		 * @return string
		 */
		public static function encode( $format ) {
			return (string) wp_json_encode( $format );
		}

		/**
		 * A stored format, checked; null for none.
		 *
		 * @param string $json Stored JSON.
		 * @return array|null
		 */
		public static function decode( $json ) {
			$json = is_string( $json ) ? trim( $json ) : '';
			if ( '' === $json ) {
				return null;
			}
			$format = json_decode( $json, true );
			if ( ! is_array( $format ) || ! isset( $format['symbol'], $format['decimals'] ) ) {
				return null;
			}
			return array_merge(
				array(
					'code'      => '',
					'before'    => 1,
					'space'     => 0,
					'neg'       => 1,
					'dec'       => '.',
					'group'     => ',',
					'show_code' => 0,
				),
				$format
			);
		}

		/**
		 * Record the currency of an order just made: the store's format now and, when the shopper browsed in another currency
		 * ( the currency switcher ), that currency and its rate. An order that already has one keeps it.
		 *
		 * @param int $order_id Order.
		 * @return bool Recorded.
		 */
		public static function stamp( $order_id ) {
			global $wpdb;
			$order_id = (int) $order_id;
			if ( $order_id <= 0 || ! self::ready() ) {
				return false;
			}
			$format = self::store_format();
			if ( class_exists( 'ec_currency' ) && isset( ec_currency::$static_conversion_rate ) && 1.0 !== (float) ec_currency::$static_conversion_rate && '' !== (string) ec_currency::$static_currency_code && (string) ec_currency::$static_currency_code !== $format['code'] ) {
				$format['shown'] = (string) ec_currency::$static_currency_code;
				$format['rate']  = (float) ec_currency::$static_conversion_rate;
			}
			unset( self::$cache[ $order_id ] );
			return (bool) $wpdb->query( $wpdb->prepare( "UPDATE ec_order SET currency_code = %s, currency_format = %s WHERE order_id = %d AND currency_format = ''", $format['code'], self::encode( $format ), $order_id ) );
		}

		/**
		 * Every order without a currency gets the store's format ( the 6.0.5 update: orders placed before it keep how they show today ).
		 *
		 * @return int Orders given one.
		 */
		public static function backfill() {
			global $wpdb;
			$format = self::store_format();
			$done   = $wpdb->query( $wpdb->prepare( "UPDATE ec_order SET currency_code = %s, currency_format = %s WHERE currency_format = ''", $format['code'], self::encode( $format ) ) );
			if ( false !== $done ) {
				update_option( self::BACKFILLED, gmdate( 'Y-m-d H:i:s' ), false );
			}
			self::$cache = array();
			return (int) $done;
		}

		/**
		 * Give the store's current currency to the orders recorded in another ( Settings › Store details: a store that changed
		 * its currency because the old one was wrong ).
		 *
		 * @return int Orders changed.
		 */
		public static function relabel_all() {
			global $wpdb;
			if ( ! self::ready() ) {
				return 0;
			}
			$format      = self::store_format();
			$done        = $wpdb->query( $wpdb->prepare( 'UPDATE ec_order SET currency_code = %s, currency_format = %s WHERE currency_code <> %s', $format['code'], self::encode( $format ), $format['code'] ) );
			self::$cache = array();
			/**
			 * Orders recorded in another currency now carry the store's.
			 *
			 * @since 6.0.5
			 * @param int   $done   Orders changed.
			 * @param array $format The store's format.
			 */
			do_action( 'wp_easycart_order_currency_relabelled', (int) $done, $format );
			return (int) $done;
		}

		/**
		 * How many orders were recorded in a currency other than the store's, by code.
		 *
		 * @return array code => orders.
		 */
		public static function other_currencies() {
			global $wpdb;
			if ( ! self::ready() ) {
				return array();
			}
			$code = self::store_format()['code'];
			$out  = array();
			foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT currency_code, COUNT(*) AS orders FROM ec_order WHERE currency_code <> '' AND currency_code <> %s GROUP BY currency_code ORDER BY orders DESC", $code ) ) as $row ) {
				$out[ (string) $row->currency_code ] = (int) $row->orders;
			}
			return $out;
		}

		/**
		 * An order's format: from the object when it carries currency_format, else read once a request by its order_id; null
		 * when none is recorded ( the store's saved format is used ).
		 *
		 * @param object|array|int $order Order row / ec_orderdisplay / ec_orderdetail, or an order id.
		 * @return array|null
		 */
		public static function format_of( $order ) {
			global $wpdb;
			if ( is_array( $order ) ) {
				$order = (object) $order;
			}
			if ( is_object( $order ) && isset( $order->currency_format ) && '' !== (string) $order->currency_format ) {
				return self::decode( $order->currency_format );
			}
			$order_id = is_object( $order ) ? ( isset( $order->order_id ) ? (int) $order->order_id : 0 ) : (int) $order;
			if ( $order_id <= 0 || ! self::ready() ) {
				return null;
			}
			if ( ! array_key_exists( $order_id, self::$cache ) ) {
				self::$cache[ $order_id ] = self::decode( (string) $wpdb->get_var( $wpdb->prepare( 'SELECT currency_format FROM ec_order WHERE order_id = %d', $order_id ) ) );
			}
			return self::$cache[ $order_id ];
		}

		/**
		 * The code an order was placed in ( '' when not recorded ).
		 *
		 * @param object|array|int $order Order.
		 * @return string
		 */
		public static function code_of( $order ) {
			$format = self::format_of( $order );
			return $format ? (string) $format['code'] : '';
		}

		/**
		 * The order's format differs from what ec_currency prints with now ( another currency or format, or a shopper's
		 * display currency ).
		 *
		 * @param array $format Format.
		 * @return bool
		 */
		private static function differs( $format ) {
			self::boot();
			$now = array(
				'symbol'    => (string) ec_currency::$static_symbol,
				'before'    => ec_currency::$static_symbol_location ? 1 : 0,
				'space'     => ec_currency::$static_symbol_space ? 1 : 0,
				'neg'       => ec_currency::$static_negative_location ? 1 : 0,
				'decimals'  => (int) ec_currency::$static_decimal_length,
				'dec'       => (string) ec_currency::$static_decimal_symbol,
				'group'     => (string) ec_currency::$static_grouping_symbol,
				'show_code' => ec_currency::$static_show_currency_code ? 1 : 0,
			);
			foreach ( $now as $key => $value ) {
				if ( (string) $format[ $key ] !== (string) $value ) {
					return true;
				}
			}
			return ( $now['show_code'] && (string) ec_currency::$static_currency_code !== (string) $format['code'] ) || 1.0 !== (float) ec_currency::$static_conversion_rate;
		}

		/**
		 * The ec_currency settings are loaded.
		 */
		private static function boot() {
			if ( class_exists( 'ec_currency' ) && null === ec_currency::$static_decimal_length ) {
				$GLOBALS['currency'] = isset( $GLOBALS['currency'] ) && is_object( $GLOBALS['currency'] ) ? $GLOBALS['currency'] : new ec_currency(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- the storefront's own ec_currency global, as ec_config.php makes it.
				if ( null === ec_currency::$static_decimal_length ) {
					new ec_currency();
				}
			}
		}

		/**
		 * Print with an order's format until pop(): ec_currency's settings are put aside and the order's put in their place, with
		 * no conversion. Always pair with pop() ( it does nothing more when nothing was changed ).
		 *
		 * @param object|array|int|null $order Order ( null / no record = the store's saved format, without a shopper's conversion ).
		 * @return bool The format was changed.
		 */
		public static function push( $order ) {
			if ( ! class_exists( 'ec_currency' ) ) {
				self::$stack[] = null;
				return false;
			}
			self::boot();
			$format = self::format_of( $order );
			if ( ! $format ) {
				$format = self::store_format();
			}
			if ( ! self::differs( $format ) ) {
				self::$stack[] = null;
				return false;
			}
			self::$stack[] = array(
				'format'    => $format,
				'symbol'    => ec_currency::$static_symbol,
				'before'    => ec_currency::$static_symbol_location,
				'space'     => ec_currency::$static_symbol_space,
				'neg'       => ec_currency::$static_negative_location,
				'decimals'  => ec_currency::$static_decimal_length,
				'dec'       => ec_currency::$static_decimal_symbol,
				'group'     => ec_currency::$static_grouping_symbol,
				'code'      => ec_currency::$static_currency_code,
				'show_code' => ec_currency::$static_show_currency_code,
				'rate'      => ec_currency::$static_conversion_rate,
			);
			self::set( $format );
			return true;
		}

		/**
		 * Put back what push() put aside.
		 */
		public static function pop() {
			if ( ! self::$stack ) {
				return;
			}
			$saved = array_pop( self::$stack );
			if ( is_array( $saved ) ) {
				ec_currency::$static_symbol             = $saved['symbol'];
				ec_currency::$static_symbol_location    = $saved['before'];
				ec_currency::$static_symbol_space       = $saved['space'];
				ec_currency::$static_negative_location  = $saved['neg'];
				ec_currency::$static_decimal_length     = $saved['decimals'];
				ec_currency::$static_decimal_symbol     = $saved['dec'];
				ec_currency::$static_grouping_symbol    = $saved['group'];
				ec_currency::$static_currency_code      = $saved['code'];
				ec_currency::$static_show_currency_code = $saved['show_code'];
				ec_currency::$static_conversion_rate    = $saved['rate'];
			}
		}

		/**
		 * The ec_currency class was made again inside a push() ( its constructor reads the store's settings ): the order's format goes back on.
		 */
		public static function reapply() {
			$top = self::$stack ? self::$stack[ count( self::$stack ) - 1 ] : null;
			if ( is_array( $top ) ) {
				self::set( $top['format'] );
			}
		}

		/**
		 * The ec_currency class prints with this format.
		 *
		 * @param array $format Format.
		 */
		private static function set( $format ) {
			ec_currency::$static_symbol             = (string) $format['symbol'];
			ec_currency::$static_symbol_location    = (int) $format['before'];
			ec_currency::$static_symbol_space       = (bool) $format['space'];
			ec_currency::$static_negative_location  = (int) $format['neg'];
			ec_currency::$static_decimal_length     = (int) $format['decimals'];
			ec_currency::$static_decimal_symbol     = (string) $format['dec'];
			ec_currency::$static_grouping_symbol    = (string) $format['group'];
			ec_currency::$static_currency_code      = (string) $format['code'];
			ec_currency::$static_show_currency_code = (int) $format['show_code'];
			ec_currency::$static_conversion_rate    = 1.000;
		}

		/**
		 * One amount of an order, in the order's own format ( what $GLOBALS['currency']->get_currency_display() printed ).
		 *
		 * @param object|array|int $order   Order.
		 * @param float            $amount  Amount ( in the order's currency ).
		 * @param bool             $convert Unused: an order is never converted into a shopper's display currency ( kept for the
		 *                                  calls it replaces ).
		 * @return string
		 */
		public static function money( $order, $amount, $convert = true ) {
			unset( $convert );
			if ( ! class_exists( 'ec_currency' ) ) {
				return number_format( (float) $amount, 2 );
			}
			self::push( $order );
			$out = ec_currency::get_currency_display( (float) $amount, false );
			self::pop();
			return $out;
		}

		/**
		 * What the order screen says about an order's currency when it differs from the store's, or the shopper browsed in another:
		 * '' otherwise.
		 *
		 * @param object|array|int $order Order.
		 * @return string
		 */
		public static function note( $order ) {
			$format = self::format_of( $order );
			if ( ! $format ) {
				return '';
			}
			$store = self::store_format();
			$parts = array();
			if ( '' !== (string) $format['code'] && (string) $format['code'] !== $store['code'] ) {
				/* translators: 1: the order's currency code, e.g. USD, 2: the store's currency code now, e.g. EUR. */
				$parts[] = sprintf( __( 'Placed in %1$s: its amounts show in %1$s although your store now uses %2$s.', 'wp-easycart' ), $format['code'], $store['code'] );
			}
			if ( ! empty( $format['shown'] ) ) {
				/* translators: 1: the currency the shopper browsed in, e.g. EUR, 2: the order's currency, e.g. USD. */
				$parts[] = sprintf( __( 'The shopper browsed in %1$s; the amounts here are in %2$s, as the order was recorded.', 'wp-easycart' ), $format['shown'], '' !== (string) $format['code'] ? $format['code'] : $store['code'] );
			}
			return implode( ' ', $parts );
		}
	}

	wp_easycart_order_currency::init();

endif;
