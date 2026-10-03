<?php
/**
 * Store activity for Reports ( 6.0.2, EC_UPGRADE_DB 119 ): daily counts that store nothing about the shopper.
 *
 * - Product views and add-to-carts per product and variant per day ( ec_report_product_day ). A view is counted from the
 *   browser ( ec-activity.js, once per product per browsing session ), so cached pages count and crawlers that run no
 *   script do not; an add is counted on wpeasycart_cart_item_added.
 * - Store searches per term per day, with how many products were found ( ec_report_search_day ): from the browser too. A term
 *   that looks like an email address or a phone number is never kept ( looks_personal() ).
 * - While the store asks for cookie consent ( wp_easycart_consent::asks() ), ec-activity.js counts views and searches only
 *   once the shopper allows statistics.
 * - Visits per source type per day ( ec_report_visit_day ): ec-order-source.js reports a new visit, so visits follow the
 *   Cookie consent setting exactly as order sources do.
 * - Checkout steps per day ( ec_report_step_day ): cart, checkout, payment, order, each counted once per cart session. The
 *   session is kept for two days as a keyed hash ( ec_report_session ), only to count a step once.
 * - Stock sold at checkout goes into the inventory log ( ec_inventory_log, source order ) that WP EasyCart PRO's stock
 *   history shows, unless the path that took it already announced it ( wpeasycart_inventory_stock_changed ).
 *
 * All of it follows one switch ( ec_option_store_activity, on ) and one retention ( ec_option_store_activity_days, 730 );
 * a daily cron prunes. Store staff ( manage_options ) are never counted.
 *
 * Bug round 7: consent_block() says when the store asks for cookie consent but nothing on the site can answer ( no banner
 * plugin WP EasyCart reads and no WP Consent API, or the chosen banner is not the one active ), so views, searches and
 * visits are never counted; Reports, Settings › Integrations and Store Status name it. A storefront page opened with
 * ?wpec_activity_check=1 shows a panel ( and a console line ) with what this browser sends and what the server counted,
 * for the rest of that browser session ( a session cookie ); ?wpec_activity_check=0 ends it.
 *
 * @since 6.0.2
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_store_activity' ) ) :

	// phpcs:disable PEAR.NamingConventions.ValidClassName -- WP EasyCart class names are lower case.
	/**
	 * Daily store activity counters.
	 *
	 * @since 6.0.2
	 */
	final class wp_easycart_store_activity {
		// phpcs:enable PEAR.NamingConventions.ValidClassName

		/** On / off. */
		const OPTION = 'ec_option_store_activity';

		/** Days kept. */
		const DAYS_OPTION = 'ec_option_store_activity_days';

		/** Days a cart session is kept for step counting. */
		const SESSION_DAYS = 2;

		/** Daily housekeeping. */
		const CRON = 'wp_easycart_store_activity_daily';

		/** Events per beacon. */
		const MAX_EVENTS = 30;

		/** Checkout steps, in order. */
		const STEPS = array( 'cart', 'checkout', 'payment', 'order' );

		/** Query argument and session cookie of the storefront check ( 1 on, 0 off ). */
		const CHECK = 'wpec_activity_check';

		/**
		 * Products whose view marker was printed in this request.
		 *
		 * @var array
		 */
		private static $viewed = array();

		/**
		 * Orders whose stock changes are mirrored into the inventory log at shutdown.
		 *
		 * @var array
		 */
		private static $orders = array();

		/**
		 * Products whose stock change this request already announced ( logged by its own path ).
		 *
		 * @var array
		 */
		private static $announced = array();

		/**
		 * The database time when the first order was touched ( only newer stock logs are mirrored ).
		 *
		 * @var string
		 */
		private static $since = '';

		/** Register hooks. */
		public static function init() {
			add_action( 'wp_ajax_wp_easycart_activity', array( __CLASS__, 'ajax' ) );
			add_action( 'wp_ajax_nopriv_wp_easycart_activity', array( __CLASS__, 'ajax' ) );
			add_action( 'wpeasycart_cart_item_added', array( __CLASS__, 'cart_item_added' ), 20, 5 );
			add_action( 'wp_easycart_display_cart_before', array( __CLASS__, 'cart_viewed' ), 10, 1 );
			add_action( 'wpeasycart_order_inserted', array( __CLASS__, 'order_placed' ), 60, 1 );
			add_action( 'wpeasycart_order_paid', array( __CLASS__, 'touch_order' ), 60, 1 );
			add_action( 'wpeasycart_order_status_update', array( __CLASS__, 'touch_order' ), 60, 1 );
			add_action( 'wpeasycart_inventory_stock_changed', array( __CLASS__, 'stock_announced' ), 1, 1 );
			add_action( 'wp_easycart_view_product_list', array( __CLASS__, 'product_list' ), 10, 1 );
			add_action( self::CRON, array( __CLASS__, 'daily' ) );
			add_action( 'init', array( __CLASS__, 'schedule' ), 30 );
			add_action( 'template_redirect', array( __CLASS__, 'check_start' ), 1 );
			add_action( 'wp_footer', array( __CLASS__, 'check_footer' ), 5 );
		}

		/**
		 * Is counting on ( the switch, and the tables are there )?
		 *
		 * @return bool
		 */
		public static function enabled() {
			return (bool) get_option( self::OPTION, 1 ) && class_exists( 'wp_easycart_order_ledger' ) && wp_easycart_order_ledger::ready();
		}

		/**
		 * Days kept ( 30 to 3650 ).
		 *
		 * @return int
		 */
		public static function days() {
			$days = (int) get_option( self::DAYS_OPTION, 730 );
			return ( $days < 30 ) ? 730 : min( 3650, $days );
		}

		/**
		 * Today in the store's time zone.
		 *
		 * @return string Y-m-d.
		 */
		public static function today() {
			return current_time( 'Y-m-d' );
		}

		/**
		 * Should this request be counted? Not for staff, not for requests that are no browser.
		 *
		 * @return bool
		 */
		private static function countable() {
			return '' === self::why_not();
		}

		/**
		 * Why this request is not counted: '' ( it is ), off ( the switch ), update ( the 6.0.2 database update has not run ),
		 * staff ( signed in with manage_options ), crawler ( no browser ) or filter ( wp_easycart_store_activity_countable ).
		 * Cookie consent is decided in the browser ( ec-activity.js ), not here.
		 *
		 * @since 6.0.2
		 * @return string
		 */
		public static function why_not() {
			if ( ! self::enabled() ) {
				return get_option( self::OPTION, 1 ) ? 'update' : 'off';
			}
			if ( function_exists( 'current_user_can' ) && is_user_logged_in() && current_user_can( 'manage_options' ) ) {
				return 'staff';
			}
			$agent   = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
			$browser = '' !== wp_easycart_order_ledger::device_class( $agent );
			/**
			 * Should a storefront request count toward store activity?
			 *
			 * @since 6.0.2
			 * @param bool   $count Default: a browser that is not a crawler.
			 * @param string $agent User agent.
			 */
			if ( ! (bool) apply_filters( 'wp_easycart_store_activity_countable', $browser, $agent ) ) {
				return $browser ? 'filter' : 'crawler';
			}
			return '';
		}

		/**
		 * Can nothing on this site answer the store's cookie consent? Then what the shopper's browser counts ( product views,
		 * searches, visits ) waits for ever, and the Meta Pixel and Google tags never load. That is the case when Cookie consent
		 * follows a banner ( auto, or the WP Consent API without its plugin ) and no banner plugin WP EasyCart reads ( Complianz,
		 * CookieYes, Cookiebot ) and no WP Consent API plugin was found ( a banner added with a script can still answer ), or
		 * when the chosen banner is not active while another one is. Google Consent Mode is left out: any banner can set it.
		 *
		 * @since 6.0.2
		 * @param string|null $setting A Cookie consent choice ( default: the saved one ).
		 * @return array Empty when something can answer; else message ( what happens ), suggest ( a Cookie consent choice that
		 *               fixes it: off, or the banner found ) and suggest_label.
		 */
		public static function consent_block( $setting = null ) {
			if ( ! class_exists( 'wp_easycart_consent' ) ) {
				return array();
			}
			$setting = ( null === $setting ) ? wp_easycart_consent::setting() : (string) $setting;
			if ( 'off' === $setting || 'google_consent_mode' === $setting || ! in_array( $setting, wp_easycart_consent::keys(), true ) ) {
				return array();
			}
			$source  = wp_easycart_consent::source( $setting );
			$banners = wp_easycart_consent::banners();
			$found   = wp_easycart_consent::found();
			if ( 'any' === $source ) {
				return array(
					'message'       => __( 'Cookie consent follows your cookie banner, but no cookie banner plugin WP EasyCart can read ( Complianz, CookieYes, Cookiebot ) and no WP Consent API plugin was found on this site. Unless your banner is added with a script, no shopper can allow statistics or marketing, so Reports never counts product views, searches or visits, and the Meta Pixel and Google tags never load.', 'wp-easycart' ),
					'suggest'       => 'off',
					'suggest_label' => __( 'Load tags without asking', 'wp-easycart' ),
				);
			}
			if ( isset( $banners[ $source ] ) && ! isset( $found[ $source ] ) && ! empty( $found ) ) {
				$ids   = array_keys( $found );
				$names = array_values( $found );
				return array(
					/* translators: 1: the cookie banner Cookie consent follows, 2: the cookie banner active on the site. */
					'message'       => sprintf( __( 'Cookie consent follows %1$s, but %1$s isn\'t active on this site ( %2$s is ), so no shopper\'s answer is ever read: Reports never counts product views, searches or visits, and the Meta Pixel and Google tags never load.', 'wp-easycart' ), $banners[ $source ]['name'], $names[0] ),
					'suggest'       => $ids[0],
					/* translators: %s: cookie banner name. */
					'suggest_label' => sprintf( __( 'Follow %s', 'wp-easycart' ), $names[0] ),
				);
			}
			return array();
		}

		/**
		 * The Cookie consent choices that consent_block() stops on this site ( Settings › Integrations › Store activity shows
		 * its note while one of them is picked ).
		 *
		 * @since 6.0.2
		 * @return string[]
		 */
		public static function consent_block_choices() {
			$choices = array();
			if ( class_exists( 'wp_easycart_consent' ) ) {
				foreach ( wp_easycart_consent::keys() as $choice ) {
					if ( self::consent_block( $choice ) ) {
						$choices[] = (string) $choice;
					}
				}
			}
			return $choices;
		}

		/**
		 * Add to a daily counter ( INSERT … ON DUPLICATE KEY UPDATE ).
		 *
		 * @param string $table  ec_report_* table.
		 * @param array  $keys   Key column => value ( strings or ints ).
		 * @param array  $counts Counter column => amount.
		 */
		private static function bump( $table, $keys, $counts ) {
			global $wpdb;
			$columns = array();
			$values  = array();
			$formats = array();
			foreach ( array_merge( $keys, $counts ) as $column => $value ) {
				$columns[] = preg_replace( '/[^a-z_]/', '', $column );
				$values[]  = $value;
				$formats[] = is_int( $value ) ? '%d' : '%s';
			}
			$updates = array();
			foreach ( $counts as $column => $amount ) {
				$column    = preg_replace( '/[^a-z_]/', '', $column );
				$updates[] = $column . ' = ' . $column . ' + ' . (int) $amount;
			}
			$table = preg_replace( '/[^a-z_]/', '', $table );
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- table and column names are fixed keys cleaned to [a-z_]; values are placeholders built from $formats.
			$wpdb->query( $wpdb->prepare( "INSERT INTO {$table} ( " . implode( ', ', $columns ) . ' ) VALUES ( ' . implode( ', ', $formats ) . ' ) ON DUPLICATE KEY UPDATE ' . implode( ', ', $updates ), $values ) );
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		}

		// ------------------------------------------------------------------
		// Product views and searches ( counted from the browser ).
		// ------------------------------------------------------------------

		/**
		 * A product's details were drawn: print the marker ec-activity.js counts ( once per product per page ). Called from
		 * the store page, the Elementor product templates and wp_easycart_meta_view_content().
		 *
		 * @param object|int $product ec_product or its id.
		 */
		public static function product_viewed( $product ) {
			$product_id = is_object( $product ) ? ( isset( $product->product_id ) ? (int) $product->product_id : 0 ) : (int) $product;
			if ( $product_id <= 0 || isset( self::$viewed[ $product_id ] ) || is_admin() || ! self::enabled() || ( function_exists( 'wp_easycart_elementor_is_editor' ) && wp_easycart_elementor_is_editor() ) ) {
				return;
			}
			self::$viewed[ $product_id ] = true;
			self::marker( array( 'view', $product_id ) );
		}

		/**
		 * Action wp_easycart_view_product_list: store search results print a search marker ( first page only ).
		 *
		 * @param object $product_list ec_productlist.
		 */
		public static function product_list( $product_list ) {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- which search is shown; nothing is changed.
			if ( ! isset( $_GET['ec_search'] ) || ( isset( $_GET['pagenum'] ) && 1 < (int) $_GET['pagenum'] ) || is_admin() || ! self::enabled() ) {
				return;
			}
			$term = self::clean_term( sanitize_text_field( wp_unslash( $_GET['ec_search'] ) ) );
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			if ( '' === $term ) {
				return;
			}
			$found = 0;
			if ( is_object( $product_list ) && isset( $product_list->num_products ) ) {
				$found = (int) $product_list->num_products;
			} elseif ( is_object( $product_list ) && isset( $product_list->products ) && is_array( $product_list->products ) ) {
				$found = count( $product_list->products );
			}
			self::marker( array( 'search', $term, $found ) );
		}

		/**
		 * A search term as it is kept: trimmed, lower case, spaces squeezed, at most 100 characters. '' ( not kept ) for a term
		 * that looks like an email address or a phone number.
		 *
		 * @param string $term Term.
		 * @return string
		 */
		public static function clean_term( $term ) {
			$term = trim( preg_replace( '/\s+/u', ' ', (string) $term ) );
			if ( self::looks_personal( $term ) ) {
				return '';
			}
			$term = function_exists( 'mb_strtolower' ) ? mb_strtolower( $term, 'UTF-8' ) : strtolower( $term );
			return function_exists( 'mb_substr' ) ? mb_substr( $term, 0, 100, 'UTF-8' ) : substr( $term, 0, 100 );
		}

		/**
		 * Does a search term look like an email address ( text on both sides of an @ ) or a phone number ( 7 or more digits,
		 * with spaces, dashes, dots, brackets, slashes or a + between them, not joined to letters )? Such searches are never
		 * counted; ec-activity.js applies the same rule before sending.
		 *
		 * @since 6.0.2
		 * @param string $term Term.
		 * @return bool
		 */
		public static function looks_personal( $term ) {
			$term = (string) $term;
			if ( preg_match( '/[^\s@]@[^\s@]/', $term ) ) {
				return true;
			}
			if ( preg_match_all( '/(^|[^a-z0-9])(\+?\(?\d(?:[\s().\/-]{0,2}\d)+)(?![a-z0-9])/i', $term, $found ) ) {
				foreach ( $found[2] as $number ) {
					/* A phone number: 7+ digits written with a + or ( or separators, or a plain run of 10 or 11 digits; other plain
					   runs are SKUs and barcodes ( 12+ digits ). */
					$digits = strlen( preg_replace( '/\D/', '', $number ) );
					if ( $digits >= 7 && ( preg_match( '/^[+(]|\d[\s().\/-]+\d/', $number ) || ( $digits >= 10 && $digits <= 11 ) ) ) {
						return true;
					}
				}
			}
			return false;
		}

		/**
		 * Print one event for ec-activity.js and make sure the script is on the page.
		 *
		 * @param array $event The event.
		 */
		private static function marker( $event ) {
			self::enqueue();
			echo '<script>(window.wpeasycart_activity=window.wpeasycart_activity||[]).push(' . wp_json_encode( $event, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ');</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded with the HEX flags.
		}

		/** The counting script, in the footer ( enqueued while the page is drawn ). */
		public static function enqueue() {
			if ( wp_script_is( 'wpeasycart_activity_js', 'enqueued' ) ) {
				return;
			}
			wp_enqueue_script(
				'wpeasycart_activity_js',
				plugins_url( 'wp-easycart/design/theme/base-responsive-v3/ec-activity.js', EC_PLUGIN_DIRECTORY ),
				array(),
				EC_CURRENT_VERSION,
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				)
			);
			$config = array(
				'url'     => admin_url( 'admin-ajax.php' ),
				/* 6.0.2: while the store asks for cookie consent, views and searches wait for the shopper to allow statistics. */
				'consent' => class_exists( 'wp_easycart_consent' ) && wp_easycart_consent::asks(),
			);
			if ( self::check_on() ) {
				$config['check'] = self::check_config();
			}
			wp_add_inline_script( 'wpeasycart_activity_js', 'window.wpeasycart_activity_config = ' . wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP ) . ';', 'before' );
		}

		// ------------------------------------------------------------------
		// The storefront check ( ?wpec_activity_check=1 ).
		// ------------------------------------------------------------------

		/**
		 * Is the storefront check on for this request ( the query argument, else the browser session's cookie )?
		 *
		 * @since 6.0.2
		 * @return bool
		 */
		public static function check_on() {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- a read-only diagnostic panel; nothing is changed.
			if ( isset( $_GET[ self::CHECK ] ) && is_string( $_GET[ self::CHECK ] ) ) {
				return '1' === sanitize_key( wp_unslash( $_GET[ self::CHECK ] ) );
			}
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			return isset( $_COOKIE[ self::CHECK ] ) && is_string( $_COOKIE[ self::CHECK ] ) && '1' === sanitize_key( wp_unslash( $_COOKIE[ self::CHECK ] ) );
		}

		/**
		 * Action template_redirect: ?wpec_activity_check=1 keeps the check on for the browser session ( a session cookie ),
		 * =0 ends it; a checked page is never cached ( the panel describes this visitor ).
		 */
		public static function check_start() {
			if ( is_admin() || headers_sent() ) {
				return;
			}
			/* A session cookie for this host ( the panel's close button clears it in the browser ). */
			$path = ( defined( 'COOKIEPATH' ) && COOKIEPATH ) ? COOKIEPATH : '/';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- turns a read-only diagnostic panel on or off for this browser.
			if ( isset( $_GET[ self::CHECK ] ) && is_string( $_GET[ self::CHECK ] ) ) {
				if ( self::check_on() ) {
					setcookie( self::CHECK, '1', 0, $path, '', is_ssl(), false );
				} elseif ( isset( $_COOKIE[ self::CHECK ] ) ) {
					setcookie( self::CHECK, '', time() - HOUR_IN_SECONDS, $path, '', is_ssl(), false );
				}
			}
			if ( self::check_on() ) {
				if ( ! defined( 'DONOTCACHEPAGE' ) ) {
					define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- the page cache plugins' shared constant, as load_ec_store() uses it.
				}
				nocache_headers();
			}
		}

		/**
		 * The storefront page to open for the check ( the store page ).
		 *
		 * @since 6.0.2
		 * @return string
		 */
		public static function check_url() {
			$page_id = (int) get_option( 'ec_option_storepage' );
			$url     = ( $page_id > 0 && function_exists( 'get_permalink' ) ) ? get_permalink( $page_id ) : '';
			if ( ! $url ) {
				$url = home_url( '/' );
			}
			return add_query_arg( self::CHECK, '1', $url );
		}

		/** Action wp_footer ( before the footer scripts print ): with the check on, the script and its panel on every page. */
		public static function check_footer() {
			if ( self::check_on() && ! is_admin() ) {
				self::enqueue();
			}
		}

		/**
		 * What the panel says about this request and the store's settings ( only facts about this visitor and the store's
		 * switches ), and its wording.
		 *
		 * @return array
		 */
		private static function check_config() {
			$block   = self::consent_block();
			$setting = class_exists( 'wp_easycart_consent' ) ? wp_easycart_consent::setting() : 'off';
			$modes   = class_exists( 'wp_easycart_consent' ) ? wp_easycart_consent::modes() : array();
			return array(
				'why'     => self::why_not(),
				'setting' => isset( $modes[ $setting ] ) ? $modes[ $setting ] : $setting,
				'mode'    => class_exists( 'wp_easycart_consent' ) ? wp_easycart_consent::source() : 'off',
				'block'   => $block ? $block['message'] : '',
				'cookie'  => self::CHECK,
				'path'    => ( defined( 'COOKIEPATH' ) && COOKIEPATH ) ? COOKIEPATH : '/',
				'i18n'    => array(
					'title'     => __( 'WP EasyCart store activity check', 'wp-easycart' ),
					'counting'  => __( 'Store activity', 'wp-easycart' ),
					'on'        => __( 'counting', 'wp-easycart' ),
					'off'       => __( 'off ( Settings › Integrations › Store activity )', 'wp-easycart' ),
					'update'    => __( 'waiting for the WP EasyCart database update', 'wp-easycart' ),
					'visitor'   => __( 'This browser', 'wp-easycart' ),
					'counted'   => __( 'can be counted', 'wp-easycart' ),
					'staff'     => __( 'not counted: signed in as a store admin ( test in a private window )', 'wp-easycart' ),
					'crawler'   => __( 'not counted: looks like a crawler', 'wp-easycart' ),
					'filter'    => __( 'not counted: left out by a filter on this site', 'wp-easycart' ),
					'robot'     => __( 'not counted: an automated browser', 'wp-easycart' ),
					'consent'   => __( 'Cookie consent', 'wp-easycart' ),
					'not_asked' => __( 'not asked ( Load tags without asking )', 'wp-easycart' ),
					'allowed'   => __( 'statistics allowed', 'wp-easycart' ),
					'refused'   => __( 'statistics refused: nothing is counted', 'wp-easycart' ),
					'waiting'   => __( 'no answer yet: views and searches wait for statistics consent', 'wp-easycart' ),
					'no_banner' => __( 'No cookie banner WP EasyCart can read is on this page, so nothing will answer.', 'wp-easycart' ),
					'page'      => __( 'This page', 'wp-easycart' ),
					/* translators: 1: the search, 2: number of products found. */
					'search'    => __( 'search "%1$s", %2$s products found', 'wp-easycart' ),
					/* translators: %s: product ID. */
					'view'      => __( 'view of product #%s', 'wp-easycart' ),
					'nothing'   => __( 'nothing to count ( open search results or a product )', 'wp-easycart' ),
					'sent'      => __( 'Sent', 'wp-easycart' ),
					'answered'  => __( 'sent', 'wp-easycart' ),
					'sending'   => __( 'sending…', 'wp-easycart' ),
					/* translators: 1: searches the server counted, 2: product views the server counted. */
					'stored'    => __( 'counted by the server: searches %1$s, product views %2$s', 'wp-easycart' ),
					'failed'    => __( 'the request failed', 'wp-easycart' ),
					'held'      => __( 'held until statistics consent', 'wp-easycart' ),
					'seen'      => __( 'not sent: already counted in this browsing session', 'wp-easycart' ),
					'personal'  => __( 'not sent: looks like an email address or a phone number', 'wp-easycart' ),
					'hint'      => __( 'Reports can take up to 10 minutes to show new counts. Open ?wpec_activity_check=0 to end this check.', 'wp-easycart' ),
					'close'     => __( 'Close the store activity check', 'wp-easycart' ),
				),
			);
		}

		/**
		 * Where ec-order-source.js reports a new visit ( '' while counting is off ).
		 *
		 * @return string
		 */
		public static function visit_url() {
			return self::enabled() ? admin_url( 'admin-ajax.php' ) : '';
		}

		/**
		 * AJAX wp_easycart_activity ( public, no nonce: cached pages send it, and it only adds to daily counts ). Takes
		 * `e`, a JSON list of events: [ 'view', product_id ], [ 'search', term, found ], [ 'visit', visit ]; at most 20 views,
		 * 2 searches and 1 visit count per request. Answers 204, or with `check` ( the storefront check ) what it counted and
		 * why_not() as JSON.
		 */
		public static function ajax() {
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- public counter beacon from cacheable pages; it stores no personal data and only adds to daily totals.
			$check = ! empty( $_POST['check'] );
			$why   = self::why_not();
			$raw   = ( '' === $why && ! empty( $_POST['e'] ) ) ? sanitize_textarea_field( wp_unslash( $_POST['e'] ) ) : '';
			// phpcs:enable WordPress.Security.NonceVerification.Missing
			$events = ( '' !== $raw ) ? json_decode( $raw, true ) : null;
			$done   = array(
				'view'   => 0,
				'search' => 0,
				'visit'  => 0,
			);
			if ( is_array( $events ) ) {
				$today = self::today();
				/* What one page can send: views of the products it shows, a search or two, one new visit. */
				$left = array(
					'view'   => 20,
					'search' => 2,
					'visit'  => 1,
				);
				foreach ( array_slice( $events, 0, self::MAX_EVENTS ) as $event ) {
					if ( ! is_array( $event ) || ! isset( $event[0] ) || ! is_string( $event[0] ) || empty( $left[ $event[0] ] ) ) {
						continue;
					}
					$counted = false;
					if ( 'view' === $event[0] && isset( $event[1] ) && is_numeric( $event[1] ) ) {
						$counted = self::record_view( (int) $event[1], $today );
					} elseif ( 'search' === $event[0] && isset( $event[1] ) && is_string( $event[1] ) ) {
						$counted = self::record_search( $event[1], isset( $event[2] ) && is_numeric( $event[2] ) ? (int) $event[2] : 0, $today );
					} elseif ( 'visit' === $event[0] && isset( $event[1] ) && is_array( $event[1] ) ) {
						$counted = self::record_visit( $event[1], $today );
					}
					if ( $counted ) {
						--$left[ $event[0] ];
						++$done[ $event[0] ];
					}
				}
			}
			if ( $check ) {
				wp_send_json(
					array(
						'why'     => $why,
						'counted' => $done,
					)
				);
			}
			wp_die( '', '', array( 'response' => 204 ) );
		}

		/**
		 * Count a product view ( products that exist and show in the store ).
		 *
		 * @param int    $product_id Product.
		 * @param string $date       Y-m-d.
		 * @return bool Counted.
		 */
		public static function record_view( $product_id, $date = '' ) {
			global $wpdb;
			if ( $product_id <= 0 || ! $wpdb->get_var( $wpdb->prepare( 'SELECT product_id FROM ec_product WHERE product_id = %d AND activate_in_store = 1', $product_id ) ) ) {
				return false;
			}
			self::bump(
				'ec_report_product_day',
				array(
					'stat_date'  => '' !== $date ? $date : self::today(),
					'product_id' => (int) $product_id,
					'variant_id' => 0,
				),
				array( 'views' => 1 )
			);
			return true;
		}

		/**
		 * Count a search ( never one that looks like an email address or a phone number: clean_term() ).
		 *
		 * @param string $term  Term.
		 * @param int    $found Products found.
		 * @param string $date  Y-m-d.
		 * @return bool Counted.
		 */
		public static function record_search( $term, $found, $date = '' ) {
			global $wpdb;
			$term = self::clean_term( sanitize_text_field( $term ) );
			if ( '' === $term ) {
				return false;
			}
			$date  = '' !== $date ? $date : self::today();
			$found = max( 0, min( 100000, (int) $found ) );
			$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_report_search_day ( stat_date, term, searches, results ) VALUES ( %s, %s, 1, %d ) ON DUPLICATE KEY UPDATE searches = searches + 1, results = %d', $date, $term, $found, $found ) );
			return true;
		}

		/**
		 * Count a visit by its source type ( wp_easycart_order_source::classify() ).
		 *
		 * @param array  $visit The visit ec-order-source.js recorded ( t, r, p, u, k ).
		 * @param string $date  Y-m-d.
		 * @return bool Counted.
		 */
		public static function record_visit( $visit, $date = '' ) {
			if ( ! class_exists( 'wp_easycart_order_source' ) ) {
				return false;
			}
			$visit = wp_easycart_order_source::clean_visit( $visit );
			if ( ! $visit ) {
				return false;
			}
			$type = wp_easycart_order_source::classify( $visit );
			$type = ( is_array( $type ) && isset( $type[0] ) ) ? sanitize_key( (string) $type[0] ) : 'direct';
			self::bump(
				'ec_report_visit_day',
				array(
					'stat_date'   => '' !== $date ? $date : self::today(),
					'source_type' => substr( '' !== $type ? $type : 'direct', 0, 20 ),
				),
				array( 'visits' => 1 )
			);
			return true;
		}

		// ------------------------------------------------------------------
		// Add to cart and checkout steps ( counted on the server ).
		// ------------------------------------------------------------------

		/**
		 * Action wpeasycart_cart_item_added.
		 *
		 * @param int   $tempcart_id Cart line.
		 * @param int   $product_id  Product.
		 * @param int   $quantity    Added.
		 * @param float $unit_price  Unit price.
		 * @param array $args        optionitemquantity_id, source.
		 */
		public static function cart_item_added( $tempcart_id, $product_id, $quantity = 1, $unit_price = 0, $args = array() ) {
			if ( ! self::countable() || (int) $product_id <= 0 ) {
				return;
			}
			self::bump(
				'ec_report_product_day',
				array(
					'stat_date'  => self::today(),
					'product_id' => (int) $product_id,
					'variant_id' => ( is_array( $args ) && isset( $args['optionitemquantity_id'] ) ) ? (int) $args['optionitemquantity_id'] : 0,
				),
				array( 'add_to_carts' => 1 )
			);
			self::step( 'cart_add' );
		}

		/**
		 * Action wp_easycart_display_cart_before: the cart shows with something in it.
		 *
		 * @param object $cart ec_cart.
		 */
		public static function cart_viewed( $cart = null ) {
			if ( is_object( $cart ) && isset( $cart->total_items ) && (int) $cart->total_items > 0 ) {
				self::step( 'cart' );
			}
		}

		/**
		 * Action wpeasycart_order_inserted: a checkout placed an order.
		 *
		 * @param int $order_id Order.
		 */
		public static function order_placed( $order_id ) {
			self::touch_order( $order_id );
			self::step( 'order' );
		}

		/**
		 * Count a checkout step once per cart session. cart_add is not a step of its own: it only opens the session.
		 *
		 * @param string $step cart | checkout | payment | order | cart_add.
		 */
		public static function step( $step ) {
			global $wpdb;
			if ( ! in_array( $step, array_merge( self::STEPS, array( 'cart_add' ) ), true ) || ! self::countable() ) {
				return;
			}
			$session = ( isset( $GLOBALS['ec_cart_data'] ) && is_object( $GLOBALS['ec_cart_data'] ) && isset( $GLOBALS['ec_cart_data']->ec_cart_id ) ) ? (string) $GLOBALS['ec_cart_data']->ec_cart_id : '';
			if ( '' === $session || in_array( $session, array( 'not-set', 'deleted' ), true ) ) {
				return;
			}
			$key   = substr( wp_hash( 'wpec-activity|' . $session ), 0, 32 );
			$today = self::today();
			$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO ec_report_session ( session_key, stat_date, steps ) VALUES ( %s, %s, '' )", $key, $today ) );
			if ( 'cart_add' === $step ) {
				return;
			}
			/* Earlier steps count too ( a shopper sent straight to checkout from a buy link still viewed a cart ). */
			foreach ( self::STEPS as $each ) {
				$changed = $wpdb->query( $wpdb->prepare( "UPDATE ec_report_session SET steps = CONCAT( steps, IF( steps = '', '', ',' ), %s ) WHERE session_key = %s AND FIND_IN_SET( %s, steps ) = 0", $each, $key, $each ) );
				if ( $changed ) {
					self::bump(
						'ec_report_step_day',
						array(
							'stat_date' => $today,
							'step'      => $each,
						),
						array( 'sessions' => 1 )
					);
				}
				if ( $each === $step ) {
					break;
				}
			}
		}

		// ------------------------------------------------------------------
		// Stock sold, into the inventory log.
		// ------------------------------------------------------------------

		/**
		 * Remember an order whose stock this request may take ( mirrored at shutdown ).
		 *
		 * @param int $order_id Order.
		 */
		public static function touch_order( $order_id ) {
			global $wpdb;
			$order_id = (int) $order_id;
			if ( $order_id <= 0 || ! self::enabled() ) {
				return;
			}
			if ( empty( self::$orders ) ) {
				self::$since = (string) $wpdb->get_var( 'SELECT DATE_SUB( NOW(), INTERVAL 5 SECOND )' );
				add_action( 'shutdown', array( __CLASS__, 'mirror_stock' ), 5 );
			}
			self::$orders[ $order_id ] = true;
		}

		/**
		 * Action wpeasycart_inventory_stock_changed: this path logs its own change.
		 *
		 * @param array $change The change ( product_id and the rest ).
		 */
		public static function stock_announced( $change ) {
			if ( is_array( $change ) && ! empty( $change['product_id'] ) ) {
				self::$announced[ (int) $change['product_id'] ] = true;
			}
		}

		/**
		 * Shutdown: write the inventory log rows for the stock the touched orders took or gave back in this request.
		 */
		public static function mirror_stock() {
			global $wpdb;
			if ( empty( self::$orders ) || '' === self::$since || 'ec_inventory_log' !== $wpdb->get_var( "SHOW TABLES LIKE 'ec_inventory_log'" ) ) {
				return;
			}
			foreach ( array_keys( self::$orders ) as $order_id ) {
				$logs = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT l.order_log_id, p.order_log_meta_value AS product_id, q.order_log_meta_value AS quantity
						FROM ec_order_log l
						INNER JOIN ec_order_log_meta p ON p.order_log_id = l.order_log_id AND p.order_log_meta_key = 'product_id'
						INNER JOIN ec_order_log_meta q ON q.order_log_id = l.order_log_id AND q.order_log_meta_key = 'quantity'
						WHERE l.order_id = %d AND l.order_log_key = 'order-stock-update' AND l.order_log_timestamp >= %s",
						$order_id,
						self::$since
					)
				);
				foreach ( (array) $logs as $log ) {
					$product_id = (int) $log->product_id;
					$delta      = (int) $log->quantity;
					$note       = 'order:' . $order_id . ':log:' . (int) $log->order_log_id;
					if ( $product_id <= 0 || 0 === $delta || isset( self::$announced[ $product_id ] ) ) {
						continue;
					}
					if ( $wpdb->get_var( $wpdb->prepare( "SELECT log_id FROM ec_inventory_log WHERE product_id = %d AND source = 'order' AND note = %s LIMIT 1", $product_id, $note ) ) ) {
						continue;
					}
					$variants = $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT optionitemquantity_id FROM ec_orderdetail WHERE order_id = %d AND product_id = %d', $order_id, $product_id ) );
					$variant  = ( 1 === count( $variants ) ) ? (int) $variants[0] : 0;
					$now      = ( $variant > 0 )
						? (int) $wpdb->get_var( $wpdb->prepare( 'SELECT quantity FROM ec_optionitemquantity WHERE optionitemquantity_id = %d', $variant ) )
						: (int) $wpdb->get_var( $wpdb->prepare( 'SELECT stock_quantity FROM ec_product WHERE product_id = %d', $product_id ) );
					$wpdb->insert(
						'ec_inventory_log',
						array(
							'product_id'            => $product_id,
							'optionitemquantity_id' => $variant,
							'location_id'           => 0,
							'delta'                 => $delta,
							'new_quantity'          => $now,
							'reason'                => 'order',
							'source'                => 'order',
							'note'                  => $note,
							'user_id'               => 0,
						),
						array( '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%d' )
					);
				}
			}
			self::$orders = array();
		}

		// ------------------------------------------------------------------
		// Store Status.
		// ------------------------------------------------------------------

		/**
		 * Store Status rows ( printed by admin/template/status/store-status/store-status.php ): is store activity counting,
		 * and can the shopper's browser send views and searches ( cookie consent )?
		 *
		 * @since 6.0.2
		 */
		public static function store_status_rows() {
			$settings = admin_url( 'admin.php?page=wp-easycart-settings&subpage=integrations' );
			if ( ! get_option( self::OPTION, 1 ) ) {
				self::status_row( 'warning', esc_html__( 'Store activity is off, so Reports counts no product views, searches, visits or checkout steps.', 'wp-easycart' ) . ' <a href="' . esc_url( $settings . '#ecst-sec-store-activity' ) . '">' . esc_html__( 'Open Store activity', 'wp-easycart' ) . '</a>' );
				return;
			}
			if ( ! self::enabled() ) {
				self::status_row( 'error', esc_html__( 'Store activity starts counting once the WP EasyCart database update has run.', 'wp-easycart' ) );
				return;
			}
			if ( self::consent_block() ) {
				/* The Cookie consent row just above says why nothing can answer. */
				self::status_row( 'error', esc_html__( 'Store activity: Reports counts no product views, searches or visits, because nothing on this site can answer the Cookie consent setting above.', 'wp-easycart' ) . ' <a href="' . esc_url( $settings . '#ecst-sec-cookie-consent' ) . '">' . esc_html__( 'Open Cookie consent', 'wp-easycart' ) . '</a>' );
				return;
			}
			if ( class_exists( 'wp_easycart_consent' ) && wp_easycart_consent::asks() ) {
				self::status_row( 'success', esc_html__( 'Store activity is counting for Reports. Product views, searches and visits count once a shopper allows statistics in your cookie banner.', 'wp-easycart' ) );
				return;
			}
			self::status_row( 'success', esc_html__( 'Store activity is counting product views, searches, visits and checkout steps for Reports.', 'wp-easycart' ) );
		}

		/**
		 * One Store Status row, in that screen's markup.
		 *
		 * @param string $kind success | warning | error.
		 * @param string $html Escaped label HTML.
		 */
		private static function status_row( $kind, $html ) {
			$icons = array(
				'success' => 'dashicons-yes',
				'warning' => 'dashicons-warning',
				'error'   => 'dashicons-no',
			);
			$kind  = isset( $icons[ $kind ] ) ? $kind : 'error';
			echo '<div class="ec_status_' . esc_attr( $kind ) . '"><div class="dashicons-before ' . esc_attr( $icons[ $kind ] ) . '"></div><span class="ec_status_label">' . $html . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- callers pass escaped text and links built with esc_url() / esc_html__().
		}

		// ------------------------------------------------------------------
		// Housekeeping.
		// ------------------------------------------------------------------

		/** Action init: the daily job. */
		public static function schedule() {
			if ( ! wp_next_scheduled( self::CRON ) ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
			}
		}

		/** Daily: drop counts older than the retention, sessions older than two days, and stamp ended subscriptions. */
		public static function daily() {
			global $wpdb;
			if ( ! class_exists( 'wp_easycart_order_ledger' ) || ! wp_easycart_order_ledger::ready() ) {
				return;
			}
			$cutoff = gmdate( 'Y-m-d', strtotime( self::today() . ' 00:00:00' ) - self::days() * DAY_IN_SECONDS );
			foreach ( array( 'ec_report_product_day', 'ec_report_search_day', 'ec_report_visit_day', 'ec_report_step_day' ) as $table ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table comes from the fixed list above.
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE stat_date < %s", $cutoff ) );
			}
			$wpdb->query( $wpdb->prepare( 'DELETE FROM ec_report_session WHERE stat_date < %s', gmdate( 'Y-m-d', strtotime( self::today() . ' 00:00:00' ) - self::SESSION_DAYS * DAY_IN_SECONDS ) ) );
			wp_easycart_order_ledger::sweep_subscriptions();
			/**
			 * The daily store activity housekeeping ran ( Reports' rollups and summaries hang off it ).
			 *
			 * @since 6.0.2
			 */
			do_action( 'wp_easycart_store_activity_daily_done' );
		}

		/** Forget every count ( Settings › Store activity › Clear ). */
		public static function clear() {
			global $wpdb;
			foreach ( array( 'ec_report_product_day', 'ec_report_search_day', 'ec_report_visit_day', 'ec_report_step_day', 'ec_report_session' ) as $table ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table comes from the fixed list above.
				$wpdb->query( "DELETE FROM {$table}" );
			}
		}
	}

	wp_easycart_store_activity::init();

endif;
