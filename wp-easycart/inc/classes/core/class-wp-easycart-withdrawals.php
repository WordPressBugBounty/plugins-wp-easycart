<?php
/**
 * WP EasyCart — the EU withdrawal button ( 6.0.4 ).
 *
 * Article 11a of the Consumer Rights Directive 2011/83/EU ( inserted by Directive (EU) 2023/2673, applied from 19 June 2026 ):
 * a store that sells online to consumers in the EU lets them withdraw from a contract through a function labelled
 * "withdraw from contract here" ( or as clear ), available for the whole withdrawal period, where they give or confirm their
 * name, the contract and the email for the receipt, then submit it with a separate "confirm withdrawal" button. The store
 * then emails, without undue delay, a receipt with what was sent and the date and time it was received. A statement sent
 * within the period counts as in time.
 *
 * This class is the one place that knows the rules, so the Classic templates, the Modern storefront ( 6.1 ) and any
 * extension draw the same thing:
 *
 *  - Settings ( Settings › Checkout › Withdrawal button ): on / off ( ec_option_withdrawal_enabled, on for a store in the EU
 *    or the EEA from the 6.0.4 update: seed_setting() ), EU and EEA orders or every order, days ( at least 14 ), when they
 *    start ( delivery | shipping | the order ), a delivery allowance, the page, the button at the foot of every page, the
 *    store's copy and the "what happens next" text.
 *  - Eligibility: applies() ( the switch, the order's country, renewals and closed orders left out ) and window() ( each line:
 *    its start, deadline, exempt switch ec_product.no_withdrawal, units left ). Lines that ship count from delivery ( or the
 *    store's choice ); lines that don't ( downloads, services, gift cards, a subscription's first order ) from the order.
 *    The deadline is the end of the last day in the store's time zone, a Saturday or Sunday moving to Monday ( EU rule
 *    1182/71 ). A line that has not shipped has no deadline yet: it stays open.
 *  - Statements: ec_order_withdrawal ( EC_UPGRADE_DB 124 ). submit() saves one, logs order-withdrawal-requested on the order,
 *    emails the shopper's receipt and the store's notice and fires wp_easycart_order_withdrawal_requested. A statement typed on
 *    the page without a link that matches no order is kept too ( order 0 ), so a typo never loses a statement made in time.
 *  - The page ( shortcode [ec_withdrawal], or the account page's ec_page=withdrawal ): signed-in customers' orders ( loaded
 *    by an uncached request, ec_ajax_withdrawal_orders ), the form for an order from its link ( wpec_order + wpec_key, the
 *    order's guest key ), and a form without a link ( order number, email, name ). Review, then Confirm withdrawal. Forms post
 *    without a page nonce so they work on cached pages; the order's key or the rate limits protect them.
 *  - The button: the order confirmation page, My Account order details and the guest order view, the receipt and shipped
 *    emails ( document switch withdrawal ), the account menu, the foot of every page, shortcode [ec_withdrawal_button].
 *
 * Nothing here changes an order's status, stock or money: the store decides ( the order screen's flag, admin side in
 * admin/inc/wp_easycart_admin_withdrawals.php ). Loaded from inc/ec_config.php on every request.
 *
 * @since 6.0.4
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_withdrawals' ) ) :

	/**
	 * The EU withdrawal button: eligibility, statements, emails and the storefront page.
	 *
	 * @since 6.0.4
	 */
	final class wp_easycart_withdrawals {

		/** The database update that added ec_order_withdrawal and ec_product.no_withdrawal. */
		const DB_VERSION = 124;

		/** Statements. */
		const TABLE = 'ec_order_withdrawal';

		/** The shortest period the law allows. */
		const MIN_DAYS = 14;

		/** Order log entries. */
		const LOG_REQUESTED = 'order-withdrawal-requested';
		const LOG_HANDLED   = 'order-withdrawal-handled';

		/** Settings. */
		const OPT_ENABLED    = 'ec_option_withdrawal_enabled';
		const OPT_SCOPE      = 'ec_option_withdrawal_scope';
		const OPT_DAYS       = 'ec_option_withdrawal_days';
		const OPT_START      = 'ec_option_withdrawal_start';
		const OPT_ALLOWANCE  = 'ec_option_withdrawal_delivery_days';
		const OPT_PAGE       = 'ec_option_withdrawal_page';
		const OPT_FOOTER     = 'ec_option_withdrawal_footer';
		const OPT_RECIPIENTS = 'ec_option_withdrawal_recipients';
		const OPT_NEXT       = 'ec_option_withdrawal_next_steps';

		/** Set when the 6.0.4 update switched the button on for an EU store ( the admin notice ). */
		const OPT_SWITCHED_ON = 'ec_option_withdrawal_switched_on';

		/** Key for the received page's links. */
		const SECRET = 'wp_easycart_withdrawal_secret';

		/** Rate limits: tries a visitor may make from one address. */
		const LIMIT_SHORT        = 5;
		const LIMIT_SHORT_WINDOW = 900;
		const LIMIT_DAY          = 20;
		const LIMIT_EMAIL        = 3;

		/**
		 * The answer to a form post on this request ( handle_request() ), for the shortcode to draw.
		 *
		 * @var array|null
		 */
		private static $state = null;

		/**
		 * The window() of each order, this request.
		 *
		 * @var array
		 */
		private static $windows = array();

		/**
		 * The button's styles have been printed on this page.
		 *
		 * @var bool
		 */
		private static $styled = false;

		/** Register hooks. */
		public static function init() {
			add_action( 'init', array( __CLASS__, 'register_shortcodes' ) );
			add_action( 'template_redirect', array( __CLASS__, 'handle_request' ), 2 );
			add_action( 'wpeasycart_success_page_content_middle', array( __CLASS__, 'success_button' ), 15, 1 );
			add_action( 'wp_easycart_order_details_header_right', array( __CLASS__, 'order_details_button' ), 20, 1 );
			add_action( 'wp_easycart_document_email_links', array( __CLASS__, 'email_row' ), 20, 3 );
			add_action( 'wpeasycart_account_links_after_subscriptions', array( __CLASS__, 'account_menu_link' ) );
			add_action( 'wp_footer', array( __CLASS__, 'footer_button' ), 50 );
			add_action( 'wp_ajax_ec_ajax_withdrawal_orders', array( __CLASS__, 'ajax_orders' ) );
			add_action( 'wp_ajax_nopriv_ec_ajax_withdrawal_orders', array( __CLASS__, 'ajax_orders' ) );
			add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		}

		/** Shortcodes [ec_withdrawal] ( the page ) and [ec_withdrawal_button] ( the button alone, for a footer or a menu area ). */
		public static function register_shortcodes() {
			add_shortcode( 'ec_withdrawal', array( __CLASS__, 'shortcode' ) );
			add_shortcode( 'ec_withdrawal_button', array( __CLASS__, 'button_shortcode' ) );
		}

		// Settings.

		/**
		 * The 6.0.4 database update has run.
		 *
		 * @return bool
		 */
		public static function ready() {
			return (int) get_option( 'ec_option_db_new_version' ) >= self::DB_VERSION;
		}

		/**
		 * The 27 EU countries and the three EEA countries that follow the Directive ( Iceland, Liechtenstein, Norway ). Greece is
		 * GR ( its VAT prefix EL is accepted too ).
		 *
		 * @return array ISO codes.
		 */
		public static function eu_countries() {
			$list = array( 'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'EL', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE', 'IS', 'LI', 'NO' );
			/**
			 * Countries whose consumers get the withdrawal button ( setting "EU and EEA orders" ).
			 *
			 * @since 6.0.4
			 * @param array $list ISO 3166-1 alpha-2 codes.
			 */
			return array_map( 'strtoupper', (array) apply_filters( 'wp_easycart_withdrawal_countries', $list ) );
		}

		/**
		 * The store is in the EU or the EEA ( wp_easycart_carriers::store_country() ).
		 *
		 * @return bool
		 */
		public static function store_in_eu() {
			$country = class_exists( 'wp_easycart_carriers' ) ? (string) wp_easycart_carriers::store_country() : strtoupper( (string) get_option( 'ec_option_default_country', '' ) );
			return '' !== $country && in_array( $country, self::eu_countries(), true );
		}

		/**
		 * The first value of the switch: on for a store in the EU or the EEA ( the law has applied there since 19 June 2026 ), off
		 * for every other store. Written once ( the 6.0.4 update step, or the first time the switch is read ); a store that turns it
		 * on gets no notice, one the update turned on does ( OPT_SWITCHED_ON ).
		 */
		public static function seed_setting() {
			if ( false !== get_option( self::OPT_ENABLED, false ) ) {
				return;
			}
			$on = self::store_in_eu();
			add_option( self::OPT_ENABLED, $on ? '1' : '0' );
			if ( $on ) {
				add_option( self::OPT_SWITCHED_ON, '1', '', false );
			}
		}

		/**
		 * The withdrawal button is on ( and the database is ready for it ).
		 *
		 * @return bool
		 */
		public static function enabled() {
			if ( ! self::ready() ) {
				return false;
			}
			$on = get_option( self::OPT_ENABLED, null );
			if ( null === $on ) {
				self::seed_setting();
				$on = get_option( self::OPT_ENABLED, '0' );
			}
			/**
			 * The withdrawal button is on.
			 *
			 * @since 6.0.4
			 * @param bool $on The setting.
			 */
			return (bool) apply_filters( 'wp_easycart_withdrawal_enabled', '1' === (string) $on );
		}

		/**
		 * The settings, cleaned.
		 *
		 * @return array scope ( eu | all ), days ( 14+ ), start ( delivered | shipped | ordered ), allowance ( days for delivery
		 *               after shipping when no delivery is recorded ), footer ( bool ).
		 */
		public static function settings() {
			$start    = (string) get_option( self::OPT_START, 'delivered' );
			$settings = array(
				'scope'     => ( 'all' === (string) get_option( self::OPT_SCOPE, 'eu' ) ) ? 'all' : 'eu',
				'days'      => max( self::MIN_DAYS, min( 365, (int) get_option( self::OPT_DAYS, self::MIN_DAYS ) ) ),
				'start'     => in_array( $start, array( 'delivered', 'shipped', 'ordered' ), true ) ? $start : 'delivered',
				'allowance' => max( 0, min( 60, (int) get_option( self::OPT_ALLOWANCE, 7 ) ) ),
				'footer'    => '0' !== (string) get_option( self::OPT_FOOTER, '1' ),
			);
			/**
			 * The withdrawal button's settings.
			 *
			 * @since 6.0.4
			 * @param array $settings scope, days, start, allowance, footer.
			 */
			$settings         = (array) apply_filters( 'wp_easycart_withdrawal_settings', $settings );
			$settings['days'] = max( self::MIN_DAYS, (int) $settings['days'] );
			return $settings;
		}

		/**
		 * The page the withdrawal form lives on ( Settings › Checkout › Withdrawal button › Withdrawal page ), 0 when none is set or
		 * it is not published ( the account page draws the form then ).
		 *
		 * @return int
		 */
		public static function page_id() {
			$page_id = (int) get_option( self::OPT_PAGE, 0 );
			return ( $page_id > 0 && 'publish' === get_post_status( $page_id ) ) ? $page_id : 0;
		}

		/**
		 * The withdrawal page's address, with query arguments.
		 *
		 * @param array $args Query arguments.
		 * @return string
		 */
		public static function page_url( $args = array() ) {
			$page_id = self::page_id();
			if ( $page_id > 0 ) {
				$url = (string) get_permalink( $page_id );
			} else {
				$url = function_exists( 'wpeasycart_links' ) ? (string) wpeasycart_links()->get_account_page() : home_url( '/' );
				$url = add_query_arg( 'ec_page', 'withdrawal', $url );
			}
			return $args ? add_query_arg( array_map( 'rawurlencode', $args ), $url ) : $url;
		}

		/**
		 * The form for one order: the withdrawal page with the order's number and its guest key ( the key is the link's secret, as
		 * for the receipt's "view order" link; an order made in the admin is given one ).
		 *
		 * @param object|int $order Order row or id.
		 * @return string
		 */
		public static function form_url( $order ) {
			$order_id = is_object( $order ) ? (int) $order->order_id : (int) $order;
			if ( $order_id <= 0 ) {
				return '';
			}
			$key = class_exists( 'wp_easycart_order_pay' ) ? wp_easycart_order_pay::ensure_key( $order_id ) : '';
			return self::page_url(
				array(
					'wpec_order' => $order_id,
					'wpec_key'   => $key,
				)
			);
		}

		// Orders and their periods.

		/**
		 * An order row with its time as a Unix time ( wpec_order_ts, wpec_updated_ts ) and is_approved.
		 *
		 * @param int $order_id Order.
		 * @return object|null
		 */
		public static function get_order( $order_id ) {
			global $wpdb;
			if ( (int) $order_id <= 0 ) {
				return null;
			}
			$order = $wpdb->get_row( $wpdb->prepare( 'SELECT ec_order.*, UNIX_TIMESTAMP( ec_order.order_date ) AS wpec_order_ts, UNIX_TIMESTAMP( ec_order.last_updated ) AS wpec_updated_ts, ec_orderstatus.is_approved FROM ec_order LEFT JOIN ec_orderstatus ON ec_orderstatus.status_id = ec_order.orderstatus_id WHERE ec_order.order_id = %d', (int) $order_id ) );
			return $order ? $order : null;
		}

		/**
		 * The order a link names ( its number and guest key must both match; drafts are never found ).
		 *
		 * @param int    $order_id Order.
		 * @param string $key      Guest key.
		 * @return object|null
		 */
		public static function find_by_key( $order_id, $key ) {
			if ( ! class_exists( 'wp_easycart_order_pay' ) ) {
				return null;
			}
			$found = wp_easycart_order_pay::find( (int) $order_id, (string) $key );
			if ( ! $found || self::is_draft( $found ) ) {
				return null;
			}
			return self::get_order( (int) $found->order_id );
		}

		/**
		 * The order a typed order number and email name ( the order's email, any case; drafts are never found ).
		 *
		 * @param string $order_ref Order number as typed.
		 * @param string $email     Email as typed.
		 * @return object|null
		 */
		public static function find_by_email( $order_ref, $email ) {
			global $wpdb;
			$order_id = (int) preg_replace( '/[^0-9]/', '', (string) $order_ref );
			$email    = strtolower( trim( (string) $email ) );
			if ( $order_id <= 0 || '' === $email ) {
				return null;
			}
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT order_id, user_email, orderstatus_id FROM ec_order WHERE order_id = %d', $order_id ) );
			if ( ! $row || ! hash_equals( strtolower( trim( (string) $row->user_email ) ), $email ) || self::is_draft( $row ) ) {
				return null;
			}
			return self::get_order( $order_id );
		}

		/**
		 * A draft order built in the admin ( never shown to customers ).
		 *
		 * @param object $order Order row.
		 * @return bool
		 */
		private static function is_draft( $order ) {
			$draft = (int) get_option( 'ec_option_orderstatus_draft', 0 );
			return $draft > 0 && $draft === (int) $order->orderstatus_id;
		}

		/**
		 * An order a subscription renewal made ( renewals are not new contracts; the subscription's first order is ).
		 *
		 * @param object $order Order row.
		 * @return bool
		 */
		public static function is_renewal( $order ) {
			global $wpdb;
			if ( isset( $order->source_type ) && 'renewal' === (string) $order->source_type ) {
				return true;
			}
			$subscription_id = isset( $order->subscription_id ) ? (int) $order->subscription_id : 0;
			if ( $subscription_id <= 0 ) {
				return false;
			}
			$first = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MIN( order_id ) FROM ec_order WHERE subscription_id = %d', $subscription_id ) );
			return $first > 0 && $first < (int) $order->order_id;
		}

		/**
		 * A country as an ISO code ( orders keep the code; an old one may keep the name ).
		 *
		 * @param string $value Country.
		 * @return string
		 */
		private static function country_code( $value ) {
			global $wpdb;
			$value = trim( (string) $value );
			if ( '' === $value ) {
				return '';
			}
			if ( preg_match( '/^[A-Za-z]{2}$/', $value ) ) {
				return strtoupper( $value );
			}
			return strtoupper( (string) $wpdb->get_var( $wpdb->prepare( 'SELECT iso2_cnt FROM ec_country WHERE name_cnt = %s', $value ) ) );
		}

		/**
		 * Does the withdrawal button apply to this order at all? The switch is on; the order is not a draft, unpaid attempt,
		 * refund or cancellation; it is not a subscription renewal; and, with "EU and EEA orders", its billing or shipping country
		 * is one of them ( an order with no country gets it: when unsure, offer it ).
		 *
		 * @param object $order Order row.
		 * @return bool
		 */
		public static function applies( $order ) {
			if ( ! $order || ! self::enabled() ) {
				return false;
			}
			$status  = (int) $order->orderstatus_id;
			$applies = true;
			if ( in_array( $status, array( 1, 5, 7, 9, 16, 19 ), true ) || self::is_draft( $order ) ) {
				$applies = false;
			} elseif ( self::is_renewal( $order ) ) {
				$applies = false;
			} elseif ( 'eu' === self::settings()['scope'] ) {
				$billing  = self::country_code( isset( $order->billing_country ) ? $order->billing_country : '' );
				$shipping = self::country_code( isset( $order->shipping_country ) ? $order->shipping_country : '' );
				$list     = self::eu_countries();
				$applies  = ( '' === $billing && '' === $shipping ) || in_array( $billing, $list, true ) || in_array( $shipping, $list, true );
			}
			/**
			 * Does the withdrawal button apply to this order?
			 *
			 * @since 6.0.4
			 * @param bool   $applies The rules above.
			 * @param object $order   Order row.
			 */
			return (bool) apply_filters( 'wp_easycart_withdrawal_applies', $applies, $order );
		}

		/**
		 * A UTC datetime column as a Unix time ( 0 for none ).
		 *
		 * @param string|null $value Datetime.
		 * @return int
		 */
		private static function utc( $value ) {
			$value = trim( (string) $value );
			if ( '' === $value || 0 === strpos( $value, '0000-00-00' ) ) {
				return 0;
			}
			$ts = strtotime( $value . ' UTC' );
			return ( false === $ts ) ? 0 : (int) $ts;
		}

		/**
		 * When the order last moved into a status ( its order-status-update log entry ), or 0.
		 *
		 * @param int $order_id Order.
		 * @param int $status   Status.
		 * @return int
		 */
		private static function status_time( $order_id, $status ) {
			global $wpdb;
			return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT UNIX_TIMESTAMP( log.order_log_timestamp ) FROM ec_order_log AS log INNER JOIN ec_order_log_meta AS meta ON meta.order_log_id = log.order_log_id WHERE log.order_id = %d AND log.order_log_key = %s AND meta.order_log_meta_key = %s AND meta.order_log_meta_value = %s ORDER BY log.order_log_id DESC LIMIT 1', (int) $order_id, 'order-status-update', 'orderstatus_id', (string) (int) $status ) );
		}

		/**
		 * When the order was placed, shipped and delivered. Shipped = the last outgoing package left ( all of them; one still packed
		 * or waiting with a label = not yet ), else the order's first fulfilment ( fulfilled_at ), else the time it moved to a shipped,
		 * picked up or delivered status. Delivered = every outgoing package delivered ( the last one's time ), else the time the order
		 * became Delivered or Picked Up.
		 *
		 * @param object $order Order row ( get_order() ).
		 * @return array ordered, shipped, all_shipped, delivered ( Unix times, 0 = not known ).
		 */
		public static function order_dates( $order ) {
			global $wpdb;
			$order_id         = (int) $order->order_id;
			$status           = (int) $order->orderstatus_id;
			$ordered          = isset( $order->wpec_order_ts ) ? (int) $order->wpec_order_ts : (int) strtotime( (string) $order->order_date );
			$updated          = isset( $order->wpec_updated_ts ) ? (int) $order->wpec_updated_ts : $ordered;
			$delivered_status = ( class_exists( 'wp_easycart_shipments' ) && method_exists( 'wp_easycart_shipments', 'delivered_status_id_if_set' ) ) ? (int) wp_easycart_shipments::delivered_status_id_if_set() : 0;
			$dates            = array(
				'ordered'     => $ordered,
				'shipped'     => 0,
				'all_shipped' => false,
				'delivered'   => 0,
			);
			$rows             = array();
			if ( class_exists( 'wp_easycart_shipments' ) && wp_easycart_shipments::ready() ) {
				$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT status, shipped_at, delivered_at FROM ec_order_shipment WHERE order_id = %d AND is_return = 0 AND status != 'voided'", $order_id ) );
			}
			if ( $rows ) {
				$waiting       = false;
				$shipped       = 0;
				$delivered     = 0;
				$all_delivered = true;
				foreach ( $rows as $row ) {
					if ( in_array( (string) $row->status, array( 'packed', 'label' ), true ) ) {
						$waiting       = true;
						$all_delivered = false;
						continue;
					}
					$shipped = max( $shipped, self::utc( $row->shipped_at ) );
					if ( 'delivered' === (string) $row->status ) {
						$delivered = max( $delivered, self::utc( $row->delivered_at ) );
					} else {
						$all_delivered = false;
					}
				}
				if ( ! $waiting && $shipped > 0 ) {
					$dates['shipped']     = $shipped;
					$dates['all_shipped'] = true;
				}
				if ( $all_delivered && $delivered > 0 ) {
					$dates['delivered'] = $delivered;
				}
			} else {
				$fulfilled        = isset( $order->fulfilled_at ) ? self::utc( $order->fulfilled_at ) : 0;
				$shipped_statuses = array( 2, 18 );
				if ( $delivered_status > 0 ) {
					$shipped_statuses[] = $delivered_status;
				}
				if ( $fulfilled > 0 ) {
					$dates['shipped']     = $fulfilled;
					$dates['all_shipped'] = true;
				} elseif ( in_array( $status, $shipped_statuses, true ) ) {
					$at                   = self::status_time( $order_id, $status );
					$dates['shipped']     = $at > 0 ? $at : $updated;
					$dates['all_shipped'] = true;
				}
			}
			if ( 0 === $dates['delivered'] && ( 18 === $status || ( $delivered_status > 0 && $delivered_status === $status ) ) ) {
				$at                 = self::status_time( $order_id, $status );
				$dates['delivered'] = $at > 0 ? $at : ( $dates['shipped'] > 0 ? $dates['shipped'] : $updated );
			}
			/**
			 * When an order was placed, shipped and delivered, for its withdrawal period.
			 *
			 * @since 6.0.4
			 * @param array  $dates ordered, shipped, all_shipped, delivered ( Unix times, 0 = not known ).
			 * @param object $order Order row.
			 */
			return (array) apply_filters( 'wp_easycart_withdrawal_order_dates', $dates, $order );
		}

		/**
		 * The end of a withdrawal period that starts at $start: the end of the day $days whole days later in the store's time zone
		 * ( the day of the event itself is not counted ), moved to the Monday when it falls on a Saturday or a Sunday ( EU rule
		 * 1182/71; public holidays are not known, so the store decides those ).
		 *
		 * @param int      $start Unix time of the event ( 0 = not known yet ).
		 * @param int|null $days  Days ( default: the setting ).
		 * @return int Unix time, 0 when the period has not started.
		 */
		public static function deadline( $start, $days = null ) {
			if ( (int) $start <= 0 ) {
				return 0;
			}
			$days = ( null === $days ) ? self::settings()['days'] : max( 1, (int) $days );
			$day  = new DateTime( '@' . (int) $start );
			$day->setTimezone( wp_timezone() );
			$day->setTime( 0, 0, 0 );
			$day->modify( '+' . $days . ' days' );
			$weekday = (int) $day->format( 'N' );
			if ( 6 === $weekday ) {
				$day->modify( '+2 days' );
			} elseif ( 7 === $weekday ) {
				$day->modify( '+1 day' );
			}
			$day->setTime( 23, 59, 59 );
			return (int) $day->getTimestamp();
		}

		/**
		 * Each line of the order and how much of it can be withdrawn from now, and the order's period as a whole.
		 *
		 * @param object $order Order row ( get_order() ).
		 * @param bool   $fresh Read again ( after a statement ).
		 * @return array order_id, lines ( each id, product_id, title, options, quantity, available, withdrawn, ships, start,
		 *               deadline, exempt, state open | exempt | withdrawn | refunded | closed ), open ( any line open ), deadline
		 *               ( the latest open line's; 0 while one has not started ), pending ( an open line's period has not started ),
		 *               withdrawn ( a statement covers the whole order ), dates ( order_dates() ).
		 */
		public static function window( $order, $fresh = false ) {
			global $wpdb;
			$order_id = (int) $order->order_id;
			if ( ! $fresh && isset( self::$windows[ $order_id ] ) ) {
				return self::$windows[ $order_id ];
			}
			$settings = self::settings();
			$dates    = self::order_dates( $order );
			$now      = time();
			$taken    = self::withdrawn_quantities( $order_id );
			if ( self::ready() ) {
				$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT d.orderdetail_id, d.product_id, d.title, d.model_number, d.quantity, d.refunded_quantity, d.is_shippable, d.is_download, d.is_giftcard, d.optionitem_name_1, d.optionitem_name_2, d.optionitem_name_3, d.optionitem_name_4, d.optionitem_name_5, p.no_withdrawal FROM ec_orderdetail AS d LEFT JOIN ec_product AS p ON p.product_id = d.product_id WHERE d.order_id = %d ORDER BY d.orderdetail_id ASC', $order_id ) );
			} else {
				$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT d.orderdetail_id, d.product_id, d.title, d.model_number, d.quantity, d.refunded_quantity, d.is_shippable, d.is_download, d.is_giftcard, d.optionitem_name_1, d.optionitem_name_2, d.optionitem_name_3, d.optionitem_name_4, d.optionitem_name_5, 0 AS no_withdrawal FROM ec_orderdetail AS d WHERE d.order_id = %d ORDER BY d.orderdetail_id ASC', $order_id ) );
			}
			$lines    = array();
			$open     = false;
			$pending  = false;
			$deadline = 0;
			foreach ( $rows as $row ) {
				$quantity = (int) $row->quantity;
				if ( $quantity <= 0 ) {
					continue;
				}
				$refunded  = min( $quantity, max( 0, (int) $row->refunded_quantity ) );
				$withdrawn = $taken['whole'] ? $quantity : min( $quantity, isset( $taken['lines'][ (int) $row->orderdetail_id ] ) ? (int) $taken['lines'][ (int) $row->orderdetail_id ] : 0 );
				$available = max( 0, $quantity - $refunded - $withdrawn );
				$ships     = ( 1 === (int) $row->is_shippable );
				if ( ! $ships || 'ordered' === $settings['start'] ) {
					$start = $dates['ordered'];
				} elseif ( 'shipped' === $settings['start'] ) {
					$start = $dates['all_shipped'] ? $dates['shipped'] : 0;
				} elseif ( $dates['delivered'] > 0 ) {
					$start = $dates['delivered'];
				} else {
					$start = ( $dates['all_shipped'] && $dates['shipped'] > 0 ) ? $dates['shipped'] + $settings['allowance'] * DAY_IN_SECONDS : 0;
				}
				$line_deadline = self::deadline( $start, $settings['days'] );
				/**
				 * Is this order line without a right of withdrawal ( made to order, perishable, sealed hygiene goods and so on )?
				 *
				 * @since 6.0.4
				 * @param bool   $exempt The product's switch ( No right of withdrawal ).
				 * @param object $row    Order line.
				 * @param object $order  Order row.
				 */
				$exempt = (bool) apply_filters( 'wp_easycart_withdrawal_line_exempt', ! empty( $row->no_withdrawal ), $row, $order );
				if ( $exempt ) {
					$state = 'exempt';
				} elseif ( $available <= 0 ) {
					$state = $withdrawn > 0 ? 'withdrawn' : 'refunded';
				} elseif ( $line_deadline > 0 && $now > $line_deadline ) {
					$state = 'closed';
				} else {
					$state = 'open';
				}
				if ( 'open' === $state ) {
					$open = true;
					if ( 0 === $line_deadline ) {
						$pending = true;
					}
					$deadline = max( $deadline, $line_deadline );
				}
				$options = array();
				for ( $i = 1; $i <= 5; $i++ ) {
					$name = trim( wp_strip_all_tags( wp_unslash( (string) $row->{'optionitem_name_' . $i} ) ) );
					if ( '' !== $name ) {
						$options[] = $name;
					}
				}
				$lines[ (int) $row->orderdetail_id ] = array(
					'id'         => (int) $row->orderdetail_id,
					'product_id' => (int) $row->product_id,
					'title'      => wp_strip_all_tags( wp_unslash( (string) $row->title ) ),
					'options'    => implode( ', ', $options ),
					'quantity'   => $quantity,
					'available'  => ( 'open' === $state ) ? $available : 0,
					'withdrawn'  => $withdrawn,
					'ships'      => $ships,
					'start'      => (int) $start,
					'deadline'   => $line_deadline,
					'exempt'     => $exempt,
					'state'      => $state,
				);
			}
			$window = array(
				'order_id'  => $order_id,
				'lines'     => $lines,
				'open'      => $open,
				'deadline'  => $pending ? 0 : $deadline,
				'pending'   => $pending,
				'withdrawn' => $taken['whole'],
				'dates'     => $dates,
			);
			/**
			 * An order's withdrawal period, line by line.
			 *
			 * @since 6.0.4
			 * @param array  $window See window().
			 * @param object $order  Order row.
			 */
			$window                     = (array) apply_filters( 'wp_easycart_withdrawal_window', $window, $order );
			self::$windows[ $order_id ] = $window;
			return $window;
		}

		/**
		 * Can the customer withdraw from ( part of ) this order online now?
		 *
		 * @param object $order Order row ( get_order() ).
		 * @return bool
		 */
		public static function can_withdraw( $order ) {
			if ( ! $order || ! self::applies( $order ) ) {
				return false;
			}
			$window = self::window( $order );
			return ! empty( $window['open'] );
		}

		// Statements.

		/**
		 * What the order's statements already withdrew from ( not counting dismissed ones ).
		 *
		 * @param int $order_id Order.
		 * @return array whole ( bool ), lines ( orderdetail_id => units ).
		 */
		public static function withdrawn_quantities( $order_id ) {
			global $wpdb;
			$taken = array(
				'whole' => false,
				'lines' => array(),
			);
			if ( ! self::ready() || (int) $order_id <= 0 ) {
				return $taken;
			}
			foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT withdrawn_lines FROM ec_order_withdrawal WHERE order_id = %d AND withdrawal_status != 'dismissed'", (int) $order_id ) ) as $json ) {
				$what = self::decode_lines( $json );
				if ( $what['whole'] ) {
					$taken['whole'] = true;
					continue;
				}
				foreach ( $what['lines'] as $line_id => $units ) {
					$taken['lines'][ $line_id ] = ( isset( $taken['lines'][ $line_id ] ) ? $taken['lines'][ $line_id ] : 0 ) + $units;
				}
			}
			return $taken;
		}

		/**
		 * A statement's withdrawn_lines.
		 *
		 * @param string $json Stored value.
		 * @return array whole ( bool ), lines ( orderdetail_id => units ).
		 */
		public static function decode_lines( $json ) {
			$data  = json_decode( (string) $json, true );
			$lines = array();
			if ( is_array( $data ) && isset( $data['lines'] ) && is_array( $data['lines'] ) ) {
				foreach ( $data['lines'] as $line_id => $units ) {
					if ( (int) $line_id > 0 && (int) $units > 0 ) {
						$lines[ (int) $line_id ] = (int) $units;
					}
				}
			}
			return array(
				'whole' => ! $lines || ( is_array( $data ) && ! empty( $data['whole'] ) ),
				'lines' => $lines,
			);
		}

		/**
		 * A statement.
		 *
		 * @param int $withdrawal_id Statement.
		 * @return object|null
		 */
		public static function get( $withdrawal_id ) {
			global $wpdb;
			if ( ! self::ready() || (int) $withdrawal_id <= 0 ) {
				return null;
			}
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_order_withdrawal WHERE withdrawal_id = %d', (int) $withdrawal_id ) );
			return $row ? $row : null;
		}

		/**
		 * An order's statements, newest first.
		 *
		 * @param int    $order_id Order.
		 * @param string $status   received | handled | '' ( every one but dismissed ).
		 * @return array
		 */
		public static function for_order( $order_id, $status = '' ) {
			global $wpdb;
			if ( ! self::ready() || (int) $order_id <= 0 ) {
				return array();
			}
			if ( '' !== $status ) {
				return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ec_order_withdrawal WHERE order_id = %d AND withdrawal_status = %s ORDER BY withdrawal_id DESC', (int) $order_id, $status ) );
			}
			return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM ec_order_withdrawal WHERE order_id = %d AND withdrawal_status != 'dismissed' ORDER BY withdrawal_id DESC", (int) $order_id ) );
		}

		/**
		 * Statements that matched no order and were not dismissed, newest first.
		 *
		 * @param int $limit Rows.
		 * @return array
		 */
		public static function unmatched( $limit = 50 ) {
			global $wpdb;
			if ( ! self::ready() ) {
				return array();
			}
			return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM ec_order_withdrawal WHERE order_id = 0 AND withdrawal_status = 'received' ORDER BY withdrawal_id DESC LIMIT %d", max( 1, (int) $limit ) ) );
		}

		/**
		 * Statements on orders the store has not handled yet.
		 *
		 * @return int
		 */
		public static function open_count() {
			global $wpdb;
			if ( ! self::ready() ) {
				return 0;
			}
			return (int) $wpdb->get_var( "SELECT COUNT( DISTINCT order_id ) FROM ec_order_withdrawal WHERE order_id > 0 AND withdrawal_status = 'received'" );
		}

		/**
		 * The store has ever received a statement ( the orders list then offers its filter ).
		 *
		 * @return bool
		 */
		public static function has_any() {
			global $wpdb;
			if ( ! self::ready() ) {
				return false;
			}
			return (bool) $wpdb->get_var( 'SELECT withdrawal_id FROM ec_order_withdrawal LIMIT 1' );
		}

		/**
		 * Save a withdrawal statement, log it on the order, send the receipt and the store's notice and announce it.
		 *
		 * @param array $args order ( row or null: matched no order ), order_ref ( as typed ), name, email ( where the receipt goes ),
		 *                    reason, note, lines ( orderdetail_id => units; empty = the whole order ), source ( account | link |
		 *                    page | admin ), user_id, ack ( send the receipt, default true ), notify ( the store's notice, default
		 *                    true ).
		 * @return object|WP_Error The saved statement.
		 */
		public static function submit( $args ) {
			global $wpdb;
			if ( ! self::ready() ) {
				return new WP_Error( 'not_ready', self::text( 'closed_text' ) );
			}
			$args  = wp_parse_args(
				$args,
				array(
					'order'     => null,
					'order_ref' => '',
					'name'      => '',
					'email'     => '',
					'reason'    => '',
					'note'      => '',
					'lines'     => array(),
					'source'    => 'page',
					'user_id'   => 0,
					'ack'       => true,
					'notify'    => true,
				)
			);
			$order = is_object( $args['order'] ) ? $args['order'] : null;
			$name  = substr( trim( sanitize_text_field( (string) $args['name'] ) ), 0, 255 );
			$email = sanitize_email( (string) $args['email'] );
			if ( '' === $name || ! is_email( $email ) ) {
				return new WP_Error( 'fields', self::text( 'error_fields' ) );
			}
			$in_time  = null;
			$deadline = 0;
			$chosen   = array();
			$whole    = true;
			if ( $order ) {
				$window = self::window( $order, true );
				foreach ( (array) $args['lines'] as $line_id => $units ) {
					$line_id = (int) $line_id;
					$units   = (int) $units;
					if ( $units > 0 && isset( $window['lines'][ $line_id ] ) && 'open' === $window['lines'][ $line_id ]['state'] ) {
						$chosen[ $line_id ] = min( $units, (int) $window['lines'][ $line_id ]['available'] );
					}
				}
				if ( $args['lines'] && ! $chosen ) {
					return new WP_Error( 'items', self::text( 'error_items' ) );
				}
				$whole    = ! $chosen || self::covers_whole( $window, $chosen );
				$in_time  = ( self::applies( $order ) && $window['open'] ) ? 1 : 0;
				$deadline = (int) $window['deadline'];
			}
			$now  = time();
			$data = array(
				'order_id'          => $order ? (int) $order->order_id : 0,
				'user_id'           => (int) $args['user_id'],
				'order_ref'         => substr( preg_replace( '/[^A-Za-z0-9\-]/', '', (string) ( $order ? $order->order_id : $args['order_ref'] ) ), 0, 64 ),
				'customer_name'     => $name,
				'customer_email'    => $email,
				'reason'            => substr( sanitize_textarea_field( (string) $args['reason'] ), 0, 500 ),
				'note'              => substr( sanitize_textarea_field( (string) $args['note'] ), 0, 500 ),
				'withdrawn_lines'   => wp_json_encode(
					( $chosen && ! $whole ) ? array( 'lines' => $chosen ) : array(
						'whole' => 1,
						'lines' => $chosen,
					)
				),
				'withdrawal_source' => in_array( $args['source'], array( 'account', 'link', 'page', 'admin' ), true ) ? $args['source'] : 'page',
				'submitted_at'      => gmdate( 'Y-m-d H:i:s', $now ),
				'deadline_at'       => $deadline > 0 ? gmdate( 'Y-m-d H:i:s', $deadline ) : null,
				'in_time'           => $in_time,
				'withdrawal_status' => 'received',
			);
			if ( false === $wpdb->insert( self::TABLE, $data ) ) {
				return new WP_Error( 'save', self::text( 'error_save' ) );
			}
			$record = self::get( (int) $wpdb->insert_id );
			if ( ! $record ) {
				return new WP_Error( 'save', self::text( 'error_save' ) );
			}
			unset( self::$windows[ (int) $record->order_id ] );
			if ( $order ) {
				self::log( (int) $order->order_id, self::LOG_REQUESTED, $record );
			}
			if ( $args['ack'] ) {
				self::send_receipt( $record );
			}
			if ( $args['notify'] ) {
				self::send_store_notice( $record );
			}
			$request = self::describe( $record );
			if ( $order ) {
				/**
				 * A customer withdrew from ( part of ) an order with the withdrawal button. The store decides what to do: nothing about
				 * the order's status, stock or money has changed.
				 *
				 * @since 6.0.4
				 * @param int   $order_id Order.
				 * @param array $request  describe(): withdrawal_id, order_id, name, email, reason, note, whole, lines ( orderdetail_id =>
				 *                        units ), source, submitted_at ( UTC ), in_time ( 1 | 0 ), deadline_at ( UTC or '' ).
				 */
				do_action( 'wp_easycart_order_withdrawal_requested', (int) $order->order_id, $request );
			} else {
				/**
				 * A withdrawal statement whose order number and email matched no order ( kept for the store to check ).
				 *
				 * @since 6.0.4
				 * @param array $request describe(), with order_ref as typed.
				 */
				do_action( 'wp_easycart_withdrawal_unmatched', $request );
			}
			return $record;
		}

		/**
		 * Do the chosen units make up the whole order: every unit that could be withdrawn, with nothing withdrawn, refunded or
		 * closed before ( items without a right of withdrawal do not count )?
		 *
		 * @param array $window window().
		 * @param array $chosen orderdetail_id => units.
		 * @return bool
		 */
		public static function covers_whole( $window, $chosen ) {
			foreach ( $window['lines'] as $line ) {
				if ( 'exempt' === $line['state'] ) {
					continue;
				}
				if ( 'open' !== $line['state'] || $line['withdrawn'] > 0 || ! isset( $chosen[ $line['id'] ] ) || (int) $chosen[ $line['id'] ] < (int) $line['available'] ) {
					return false;
				}
			}
			return true;
		}

		/**
		 * A statement as plain data ( hooks, the privacy export ).
		 *
		 * @param object $record Statement.
		 * @return array
		 */
		public static function describe( $record ) {
			$what = self::decode_lines( $record->withdrawn_lines );
			return array(
				'withdrawal_id' => (int) $record->withdrawal_id,
				'order_id'      => (int) $record->order_id,
				'order_ref'     => (string) $record->order_ref,
				'name'          => (string) $record->customer_name,
				'email'         => (string) $record->customer_email,
				'reason'        => (string) $record->reason,
				'note'          => (string) $record->note,
				'whole'         => $what['whole'],
				'lines'         => $what['lines'],
				'source'        => (string) $record->withdrawal_source,
				'status'        => (string) $record->withdrawal_status,
				'submitted_at'  => (string) $record->submitted_at,
				'in_time'       => ( null === $record->in_time ) ? null : (int) $record->in_time,
				'deadline_at'   => (string) $record->deadline_at,
			);
		}

		/**
		 * Mark an order's statement handled ( the order screen ).
		 *
		 * @param int $withdrawal_id Statement.
		 * @param int $user_id       Staff member.
		 * @return bool Changed.
		 */
		public static function mark_handled( $withdrawal_id, $user_id = 0 ) {
			global $wpdb;
			$record = self::get( $withdrawal_id );
			if ( ! $record || 'received' !== $record->withdrawal_status ) {
				return false;
			}
			$done = $wpdb->query( $wpdb->prepare( "UPDATE ec_order_withdrawal SET withdrawal_status = 'handled', handled_at = %s, handled_by = %d WHERE withdrawal_id = %d AND withdrawal_status = 'received'", gmdate( 'Y-m-d H:i:s' ), (int) $user_id, (int) $withdrawal_id ) );
			if ( ! $done ) {
				return false;
			}
			$record = self::get( $withdrawal_id );
			if ( (int) $record->order_id > 0 ) {
				self::log( (int) $record->order_id, self::LOG_HANDLED, $record );
				/**
				 * The store marked a withdrawal statement handled.
				 *
				 * @since 6.0.4
				 * @param int   $order_id Order.
				 * @param array $request  describe().
				 */
				do_action( 'wp_easycart_order_withdrawal_handled', (int) $record->order_id, self::describe( $record ) );
			}
			return true;
		}

		/**
		 * Dismiss a statement that matched no order ( spam, or one the store dealt with by email ).
		 *
		 * @param int $withdrawal_id Statement.
		 * @return bool
		 */
		public static function dismiss( $withdrawal_id ) {
			global $wpdb;
			return (bool) $wpdb->query( $wpdb->prepare( "UPDATE ec_order_withdrawal SET withdrawal_status = 'dismissed', handled_at = %s, handled_by = %d WHERE withdrawal_id = %d AND order_id = 0 AND withdrawal_status = 'received'", gmdate( 'Y-m-d H:i:s' ), (int) get_current_user_id(), (int) $withdrawal_id ) );
		}

		/**
		 * Put a statement that matched no order on the order the store says it was for. It counts as in time when it was sent
		 * before that order's period ends as it stands now.
		 *
		 * @param int $withdrawal_id Statement.
		 * @param int $order_id      Order.
		 * @return object|WP_Error The statement.
		 */
		public static function attach( $withdrawal_id, $order_id ) {
			global $wpdb;
			$record = self::get( $withdrawal_id );
			$order  = self::get_order( $order_id );
			if ( ! $record || 0 !== (int) $record->order_id || 'received' !== $record->withdrawal_status ) {
				return new WP_Error( 'record', __( 'This statement is already on an order or was dismissed.', 'wp-easycart' ) );
			}
			if ( ! $order || self::is_draft( $order ) ) {
				return new WP_Error( 'order', __( 'There is no order with that number.', 'wp-easycart' ) );
			}
			$window    = self::window( $order, true );
			$submitted = self::utc( $record->submitted_at );
			$open_line = false;
			$deadline  = 0;
			foreach ( $window['lines'] as $line ) {
				if ( 'exempt' === $line['state'] ) {
					continue;
				}
				$open_line = $open_line || 0 === $line['deadline'] || $submitted <= $line['deadline'];
				$deadline  = max( $deadline, $line['deadline'] );
			}
			$wpdb->update(
				self::TABLE,
				array(
					'order_id'    => (int) $order->order_id,
					'user_id'     => (int) $order->user_id,
					'in_time'     => $open_line ? 1 : 0,
					'deadline_at' => $deadline > 0 ? gmdate( 'Y-m-d H:i:s', $deadline ) : null,
				),
				array( 'withdrawal_id' => (int) $record->withdrawal_id )
			);
			$record = self::get( $withdrawal_id );
			unset( self::$windows[ (int) $order->order_id ] );
			self::log( (int) $order->order_id, self::LOG_REQUESTED, $record );
			/** This action is documented in submit(). */
			do_action( 'wp_easycart_order_withdrawal_requested', (int) $order->order_id, self::describe( $record ) );
			return $record;
		}

		/**
		 * The order log entry for a statement ( the order screen's timeline ).
		 *
		 * @param int    $order_id Order.
		 * @param string $key      LOG_REQUESTED | LOG_HANDLED.
		 * @param object $record   Statement.
		 */
		private static function log( $order_id, $key, $record ) {
			if ( ! function_exists( 'wp_easycart_order_log' ) ) {
				return;
			}
			$what = self::decode_lines( $record->withdrawn_lines );
			$meta = array(
				'withdrawal_id' => (string) (int) $record->withdrawal_id,
				'items'         => $what['whole'] ? 'all' : (string) array_sum( $what['lines'] ),
				'source'        => (string) $record->withdrawal_source,
			);
			if ( self::LOG_REQUESTED === $key ) {
				$meta['in_time'] = ( null === $record->in_time ) ? '' : (string) (int) $record->in_time;
				$meta['name']    = (string) $record->customer_name;
			} else {
				$meta['user_id'] = (string) (int) $record->handled_by;
			}
			wp_easycart_order_log( (int) $order_id, $key, $meta );
		}

		// Wording.

		/**
		 * A storefront phrase ( language section withdrawal ) with its [tokens] filled in. Plain text: escape it where it prints.
		 *
		 * @param string $key  Phrase.
		 * @param array  $vars token => value ( without brackets ).
		 * @return string
		 */
		public static function text( $key, $vars = array() ) {
			$text = function_exists( 'wp_easycart_language' ) ? (string) wp_easycart_language()->get_text( 'withdrawal', $key ) : '';
			if ( '' === trim( $text ) ) {
				$text = self::fallback( $key );
			}
			if ( ! isset( $vars['days'] ) ) {
				$vars['days'] = (string) self::settings()['days'];
			}
			foreach ( (array) $vars as $token => $value ) {
				$text = str_replace( '[' . $token . ']', (string) $value, $text );
			}
			return $text;
		}

		/**
		 * English for a phrase a store's language data does not have yet.
		 *
		 * @param string $key Phrase.
		 * @return string
		 */
		public static function fallback( $key ) {
			$en = self::phrases();
			return isset( $en[ $key ] ) ? $en[ $key ][1] : '';
		}

		/**
		 * The section's phrases in English: key => array( title, value ). The language files carry the same ( and their
		 * translations ); the button and confirm labels are the ones Article 11a sets.
		 *
		 * @return array
		 */
		public static function phrases() {
			return array(
				'withdraw_button'      => array( 'Withdrawal button ( keep the legal wording: withdraw from contract here )', 'Withdraw from contract here' ),
				'confirm_button'       => array( 'Confirm button ( keep the legal wording: confirm withdrawal )', 'Confirm withdrawal' ),
				'page_title'           => array( 'Withdrawal page heading', 'Withdraw from a contract' ),
				'page_intro'           => array( 'Withdrawal page introduction', 'You can withdraw from an order without giving a reason while its withdrawal period is open. Tell us which order, check what you are sending, then confirm. We email you a receipt straight away.' ),
				'your_orders'          => array( 'Signed-in customer: heading above their orders', 'Your orders' ),
				'your_orders_none'     => array( 'Signed-in customer: no order can be withdrawn from', 'None of your orders can be withdrawn from online right now.' ),
				'order_title'          => array( 'Form for one order: heading ( [order_id] )', 'Withdraw from order [order_id]' ),
				'order_until'          => array( 'Form for one order: the deadline ( [date] )', 'You can withdraw from this order until [date].' ),
				'order_pending'        => array( 'Form for one order: the period has not started ( [days] )', 'Your [days]-day withdrawal period starts when you receive your order. You can also withdraw before then.' ),
				'other_title'          => array( 'Form without a link: heading', 'Withdraw from an order' ),
				'other_intro'          => array( 'Form without a link: introduction', 'Enter the order number and the email address you used for the order.' ),
				'order_number_label'   => array( 'Order number label', 'Order number' ),
				'order_email_label'    => array( 'Email used for the order label', 'Email address used for the order' ),
				'name_label'           => array( 'Name label', 'Your name' ),
				'email_label'          => array( 'Email for the receipt label', 'Email for your receipt' ),
				'items_label'          => array( 'Items label', 'Items you are withdrawing from' ),
				'item_exempt'          => array( 'Item without a right of withdrawal', 'No right of withdrawal' ),
				'item_withdrawn'       => array( 'Item already withdrawn from', 'Already withdrawn' ),
				'item_closed'          => array( 'Item whose period ended ( [date] )', 'Withdrawal period ended on [date]' ),
				'item_refunded'        => array( 'Item already refunded', 'Already refunded' ),
				'note_label'           => array( 'Form without a link: only some items ( optional )', 'Only some items? Tell us which (optional)' ),
				'reason_label'         => array( 'Reason label ( optional )', 'Reason (optional)' ),
				'continue_button'      => array( 'Continue button', 'Continue' ),
				'review_title'         => array( 'Review step heading', 'Check your withdrawal' ),
				'review_intro'         => array( 'Review step introduction', 'This is what we will receive. Nothing is sent until you confirm.' ),
				'review_order'         => array( 'Summary: order', 'Order' ),
				'review_items'         => array( 'Summary: items', 'Items' ),
				'review_whole'         => array( 'Summary: the whole order', 'The whole order' ),
				'review_name'          => array( 'Summary: name', 'Name' ),
				'review_email'         => array( 'Summary: email', 'Email' ),
				'review_reason'        => array( 'Summary: reason', 'Reason' ),
				'review_note'          => array( 'Summary: note', 'Note' ),
				'review_received'      => array( 'Summary: date and time received', 'Received' ),
				'change_button'        => array( 'Review step: go back button', 'Change something' ),
				'received_title'       => array( 'Received page heading', 'Withdrawal received' ),
				'received_text'        => array( 'Received page text ( [datetime], [email] )', 'We received your withdrawal on [datetime]. A copy is on its way to [email].' ),
				'received_next'        => array( 'Received page: what happens next heading', 'What happens next' ),
				'already_text'         => array( 'The order was already withdrawn from ( [datetime] )', 'We received your withdrawal from this order on [datetime].' ),
				'closed_text'          => array( 'The order cannot be withdrawn from online', 'This order can no longer be withdrawn from online. If you think that is wrong, please contact us.' ),
				'link_invalid'         => array( 'A withdrawal link that no longer works', 'That link no longer works. Enter your order number and email below instead.' ),
				'error_fields'         => array( 'Error: a required field is empty', 'Please fill in every field marked *.' ),
				'error_email'          => array( 'Error: not an email address', 'Please enter a valid email address.' ),
				'error_items'          => array( 'Error: no item chosen', 'Please choose at least one item.' ),
				'error_limit'          => array( 'Error: too many tries', 'Too many tries. Please wait a few minutes and try again.' ),
				'error_check'          => array( 'Error: the robot check is needed', 'Please confirm you are not a robot, then try again.' ),
				'error_save'           => array( 'Error: the statement could not be saved', 'Your withdrawal could not be saved. Please try again, or contact us.' ),
				'lookup_title'         => array( 'Order lookup: heading', 'Find your order' ),
				'lookup_intro'         => array( 'Order lookup: introduction', 'Ordered without an account? Enter your order number and the email address you used, and we will email you a link to your order.' ),
				'lookup_button'        => array( 'Order lookup: button', 'Email me a link' ),
				'lookup_sent'          => array( 'Order lookup: answer', 'If that matches an order, we have emailed a link to it. It can take a few minutes to arrive.' ),
				'lookup_prompt'        => array( 'Withdrawal page: want to choose items', 'Want to choose items first?' ),
				'login_lookup'         => array( 'Sign-in page: link to the order lookup', 'Ordered without an account? Find your order' ),
				'email_row_until'      => array( 'Receipt and shipped emails: with a deadline ( [date] )', 'Changed your mind? You can withdraw from this order online until [date], without giving a reason.' ),
				'email_row_open'       => array( 'Receipt and shipped emails: before delivery ( [days] )', 'Changed your mind? You can withdraw from this order online without giving a reason. The [days]-day period starts when you receive your order.' ),
				'ack_subject'          => array( 'Receipt email: subject ( [order_id] )', 'We received your withdrawal (order [order_id])' ),
				'ack_intro'            => array( 'Receipt email: text ( [name], [order_id] )', 'Hello [name], we received your withdrawal from order [order_id]. We will check it and contact you about returning any items and your refund.' ),
				'ack_footer'           => array( 'Receipt email: receipt only', 'This email confirms that we received your withdrawal. It does not yet confirm that the withdrawal is valid: we will check it and reply.' ),
				'lookup_email_subject' => array( 'Order lookup email: subject ( [order_id] )', 'The link to your order [order_id]' ),
				'lookup_email_intro'   => array( 'Order lookup email: text ( [order_id] )', 'Here is the link to order [order_id]. You can see the order, print your receipt and, while its withdrawal period is open, withdraw from it.' ),
				'lookup_email_button'  => array( 'Order lookup email: button', 'View your order' ),
				'lookup_email_ignore'  => array( 'Order lookup email: not asked for', 'If you did not ask for this link, you can ignore this email.' ),
			);
		}

		/**
		 * A date and time with the store's time zone ( "6 October 2026, 14:32 (UTC+02:00)" ): what the receipt must show.
		 *
		 * @param int $ts Unix time.
		 * @return string
		 */
		public static function when( $ts ) {
			$ts = (int) $ts;
			return wp_date( get_option( 'date_format', 'F j, Y' ) . ' ' . get_option( 'time_format', 'g:i a' ), $ts ) . ' (UTC' . wp_date( 'P', $ts ) . ')';
		}

		/**
		 * A date in the store's time zone.
		 *
		 * @param int $ts Unix time.
		 * @return string
		 */
		public static function day( $ts ) {
			return (string) wp_date( get_option( 'date_format', 'F j, Y' ), (int) $ts );
		}

		/**
		 * A statement's content as label / value rows ( the review step, the received page, both emails ).
		 *
		 * @param object|array $record Statement row, or the form's values ( order_ref, name, email, reason, note, lines, whole ).
		 * @param bool         $with_time Add the date and time it was received.
		 * @return array Each label, value ( plain text; items as lines joined by "\n" ).
		 */
		public static function summary_rows( $record, $with_time = true ) {
			global $wpdb;
			if ( is_object( $record ) ) {
				$what = self::decode_lines( $record->withdrawn_lines );
				$data = array(
					'order_ref' => (string) $record->order_ref,
					'order_id'  => (int) $record->order_id,
					'name'      => (string) $record->customer_name,
					'email'     => (string) $record->customer_email,
					'reason'    => (string) $record->reason,
					'note'      => (string) $record->note,
					'whole'     => $what['whole'],
					'lines'     => $what['lines'],
					'at'        => self::utc( $record->submitted_at ),
				);
			} else {
				$data = wp_parse_args(
					(array) $record,
					array(
						'order_ref' => '',
						'order_id'  => 0,
						'name'      => '',
						'email'     => '',
						'reason'    => '',
						'note'      => '',
						'whole'     => true,
						'lines'     => array(),
						'at'        => 0,
					)
				);
			}
			$items = self::text( 'review_whole' );
			if ( ! $data['whole'] && $data['lines'] ) {
				$ids    = array_map( 'intval', array_keys( $data['lines'] ) );
				$titles = array();
				foreach ( (array) $wpdb->get_results( 'SELECT orderdetail_id, title, optionitem_name_1, optionitem_name_2, optionitem_name_3, optionitem_name_4, optionitem_name_5 FROM ec_orderdetail WHERE orderdetail_id IN ( ' . implode( ',', $ids ) . ' )' ) as $row ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- integer ids.
					$options = array();
					for ( $i = 1; $i <= 5; $i++ ) {
						$name = trim( wp_strip_all_tags( wp_unslash( (string) $row->{'optionitem_name_' . $i} ) ) );
						if ( '' !== $name ) {
							$options[] = $name;
						}
					}
					$titles[ (int) $row->orderdetail_id ] = wp_strip_all_tags( wp_unslash( (string) $row->title ) ) . ( $options ? ' (' . implode( ', ', $options ) . ')' : '' );
				}
				$list = array();
				foreach ( $data['lines'] as $line_id => $units ) {
					$list[] = ( isset( $titles[ (int) $line_id ] ) ? $titles[ (int) $line_id ] : '#' . (int) $line_id ) . ' × ' . (int) $units;
				}
				$items = implode( "\n", $list );
			}
			$rows   = array();
			$rows[] = array(
				'label' => self::text( 'review_order' ),
				'value' => '' !== (string) $data['order_ref'] ? (string) $data['order_ref'] : (string) (int) $data['order_id'],
			);
			$rows[] = array(
				'label' => self::text( 'review_items' ),
				'value' => $items,
			);
			if ( '' !== trim( (string) $data['note'] ) ) {
				$rows[] = array(
					'label' => self::text( 'review_note' ),
					'value' => (string) $data['note'],
				);
			}
			$rows[] = array(
				'label' => self::text( 'review_name' ),
				'value' => (string) $data['name'],
			);
			$rows[] = array(
				'label' => self::text( 'review_email' ),
				'value' => (string) $data['email'],
			);
			if ( '' !== trim( (string) $data['reason'] ) ) {
				$rows[] = array(
					'label' => self::text( 'review_reason' ),
					'value' => (string) $data['reason'],
				);
			}
			if ( $with_time && (int) $data['at'] > 0 ) {
				$rows[] = array(
					'label' => self::text( 'review_received' ),
					'value' => self::when( (int) $data['at'] ),
				);
			}
			return $rows;
		}

		// Emails.

		/**
		 * The data folder's copy of a template, else ours.
		 *
		 * @param string $file File name.
		 * @return string
		 */
		public static function template( $file ) {
			$layout   = (string) get_option( 'ec_option_base_layout' );
			$override = ( defined( 'EC_PLUGIN_DATA_DIRECTORY' ) && '' !== $layout ) ? EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . $layout . '/' . $file : '';
			if ( '' !== $override && file_exists( $override ) ) {
				return $override;
			}
			$path = EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout', 'base-responsive-v3' ) . '/' . $file;
			return file_exists( $path ) ? $path : EC_PLUGIN_DIRECTORY . '/design/layout/base-responsive-v3/' . $file;
		}

		/**
		 * The store page, for an email's logo link.
		 *
		 * @return string
		 */
		private static function store_page() {
			$page_id = (int) get_option( 'ec_option_storepage' );
			$url     = $page_id > 0 ? get_permalink( $page_id ) : '';
			return $url ? (string) $url : home_url( '/' );
		}

		/**
		 * Email the shopper the receipt of their statement: what they sent and when it was received ( Article 11a(4) ).
		 *
		 * @param object $record Statement.
		 * @return bool
		 */
		public static function send_receipt( $record ) {
			global $wpdb;
			$to = (string) $record->customer_email;
			if ( ! is_email( $to ) ) {
				return false;
			}
			$withdrawal_email = array(
				'record'     => $record,
				'order_ref'  => '' !== (string) $record->order_ref ? (string) $record->order_ref : (string) (int) $record->order_id,
				'name'       => (string) $record->customer_name,
				'rows'       => self::summary_rows( $record ),
				'next_steps' => trim( (string) get_option( self::OPT_NEXT, '' ) ),
			);
			$subject          = self::text( 'ack_subject', array( 'order_id' => $withdrawal_email['order_ref'] ) );
			$email_logo_url   = (string) get_option( 'ec_option_email_logo' );
			$store_page       = self::store_page();
			ob_start();
			include self::template( 'ec_withdrawal_received_email.php' );
			$html = (string) ob_get_clean();
			$sent = class_exists( 'ec_email' ) ? ec_email::send(
				$to,
				wp_strip_all_tags( $subject ),
				$html,
				array(
					'type'     => 'withdrawal_received',
					'order_id' => (int) $record->order_id,
					'channel'  => 'order',
				)
			) : wp_mail( $to, wp_strip_all_tags( $subject ), $html, array( 'Content-Type: text/html; charset=utf-8' ) );
			if ( $sent ) {
				$wpdb->update( self::TABLE, array( 'ack_sent_at' => gmdate( 'Y-m-d H:i:s' ) ), array( 'withdrawal_id' => (int) $record->withdrawal_id ) );
			}
			return (bool) $sent;
		}

		/**
		 * Where the store's notices go: Settings › Checkout › Withdrawal button › Send the store's copy to, else the order
		 * notification addresses, else the admin email.
		 *
		 * @return array Addresses.
		 */
		public static function recipients() {
			$raw = trim( (string) get_option( self::OPT_RECIPIENTS, '' ) );
			if ( '' === $raw ) {
				$raw = trim( (string) get_option( 'ec_option_bcc_email_addresses', '' ) );
			}
			$list = array();
			foreach ( preg_split( '/[,;]+/', $raw ) as $part ) {
				$part = trim( $part );
				if ( preg_match( '/<([^>]+)>/', $part, $m ) ) {
					$part = trim( $m[1] );
				}
				if ( is_email( $part ) ) {
					$list[] = $part;
				}
			}
			if ( ! $list && is_email( (string) get_option( 'admin_email' ) ) ) {
				$list[] = (string) get_option( 'admin_email' );
			}
			/**
			 * Who gets the store's notice of a withdrawal statement.
			 *
			 * @since 6.0.4
			 * @param array $list Email addresses.
			 */
			return array_values( array_unique( (array) apply_filters( 'wp_easycart_withdrawal_recipients', $list ) ) );
		}

		/**
		 * Email the store that a statement arrived ( in the admin's language ).
		 *
		 * @param object $record Statement.
		 * @return bool
		 */
		public static function send_store_notice( $record ) {
			global $wpdb;
			$to = self::recipients();
			if ( ! $to || ! class_exists( 'wp_easycart_email_design' ) ) {
				return false;
			}
			$ed       = 'wp_easycart_email_design';
			$order_id = (int) $record->order_id;
			$matched  = $order_id > 0;
			if ( $matched ) {
				/* translators: %d: order number. */
				$subject = sprintf( __( 'Withdrawal received for order %d', 'wp-easycart' ), $order_id );
				if ( 1 === (int) $record->in_time ) {
					$intro = __( 'A customer withdrew from this order with the withdrawal button, within the withdrawal period as it stands. Check the order, arrange the return and refund what is due. Nothing on the order has changed.', 'wp-easycart' );
				} else {
					$intro = __( 'A customer withdrew from this order with the withdrawal button, but the order shows no withdrawal period still open ( it ended, or every item has no right of withdrawal ). Check the order and decide. Nothing on the order has changed.', 'wp-easycart' );
				}
			} else {
				/* translators: %s: order number as typed. */
				$subject = sprintf( __( 'Withdrawal statement that matched no order ( %s )', 'wp-easycart' ), (string) $record->order_ref );
				$intro   = __( 'Someone sent a withdrawal statement, but the order number and email they typed match no order. It may be a typing mistake: find the order, then attach the statement to it on Settings › Checkout › Withdrawal button, or dismiss it there.', 'wp-easycart' );
			}
			$pairs = array();
			foreach ( self::summary_rows( $record ) as $row ) {
				$pairs[] = '<tr><td style="padding:4px 12px 4px 0;vertical-align:top;color:#6b7280;white-space:nowrap;">' . esc_html( $row['label'] ) . '</td><td style="padding:4px 0;vertical-align:top;">' . nl2br( esc_html( $row['value'] ) ) . '</td></tr>';
			}
			$body  = $ed::get_paragraph( esc_html( $intro ) );
			$body .= '<table role="presentation" border="0" cellpadding="0" cellspacing="0" style="margin:0 0 8px 0;">' . implode( '', $pairs ) . '</table>';
			$url   = $matched ? admin_url( 'admin.php?page=wp-easycart-orders&subpage=orders&ec_admin_form_action=edit&order_id=' . $order_id ) : admin_url( 'admin.php?page=wp-easycart-settings&subpage=checkout#withdrawal' );
			$html  = $ed::wrap(
				$body,
				array(
					'title'       => $subject,
					'heading'     => $subject,
					'preheader'   => $intro,
					'eyebrow'     => __( 'Store notification', 'wp-easycart' ),
					'button_url'  => $url,
					'button_text' => $matched ? __( 'Open the order', 'wp-easycart' ) : __( 'Open the settings', 'wp-easycart' ),
				)
			);
			$sent  = class_exists( 'ec_email' ) ? ec_email::send(
				implode( ',', $to ),
				$subject,
				$html,
				array(
					'type'     => 'withdrawal_store',
					'order_id' => $order_id,
					'channel'  => 'order',
				)
			) : wp_mail( $to, $subject, $html, array( 'Content-Type: text/html; charset=utf-8' ) );
			if ( $sent ) {
				$wpdb->update( self::TABLE, array( 'store_sent_at' => gmdate( 'Y-m-d H:i:s' ) ), array( 'withdrawal_id' => (int) $record->withdrawal_id ) );
			}
			return (bool) $sent;
		}

		// Rate limits and the robot check ( the forms without a link, the order lookup ).

		/**
		 * The visitor's address.
		 *
		 * @return string
		 */
		public static function client_ip() {
			if ( class_exists( 'wp_easycart_checkout_guard' ) && method_exists( 'wp_easycart_checkout_guard', 'client_ip' ) ) {
				return (string) wp_easycart_checkout_guard::client_ip();
			}
			return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		}

		/**
		 * How many times a counter was hit within its window.
		 *
		 * @param string $key    Counter ( transient name ).
		 * @param int    $window Seconds.
		 * @return int
		 */
		public static function count( $key, $window ) {
			$hit = get_transient( $key );
			if ( ! is_array( $hit ) || ! isset( $hit['start'], $hit['count'] ) || time() - (int) $hit['start'] >= (int) $window ) {
				return 0;
			}
			return (int) $hit['count'];
		}

		/**
		 * Count one try against each limit, unless one of them is already reached ( then nothing is counted ). Kept in transients
		 * under a MySQL named lock, as Connect Order does; no lock within 3 seconds counts as over the limit.
		 *
		 * @param array $limits key => array( limit, window seconds ).
		 * @return bool May go ahead.
		 */
		public static function hit( $limits ) {
			global $wpdb;
			if ( 1 !== (int) $wpdb->get_var( "SELECT GET_LOCK( 'wpec_withdrawal_limit', 3 )" ) ) {
				return false;
			}
			$now     = time();
			$allowed = true;
			$windows = array();
			foreach ( (array) $limits as $key => $limit ) {
				$hit = get_transient( $key );
				if ( ! is_array( $hit ) || ! isset( $hit['start'], $hit['count'] ) || $now - (int) $hit['start'] >= (int) $limit[1] ) {
					$hit = array(
						'start' => $now,
						'count' => 0,
					);
				}
				if ( (int) $hit['count'] >= (int) $limit[0] ) {
					$allowed = false;
					break;
				}
				$windows[ $key ] = array( $hit, (int) $limit[1] );
			}
			if ( $allowed ) {
				foreach ( $windows as $key => $pair ) {
					$pair[0]['count'] = (int) $pair[0]['count'] + 1;
					set_transient( $key, $pair[0], max( 1, $pair[1] - ( $now - (int) $pair[0]['start'] ) ) );
				}
			}
			$wpdb->query( "SELECT RELEASE_LOCK( 'wpec_withdrawal_limit' )" );
			return $allowed;
		}

		/**
		 * The limits one address has for a form ( 5 tries in 15 minutes, 20 a day ).
		 *
		 * @param string $form wd ( the withdrawal form without a link ) | wdk ( a form from a link ) | lk ( the order lookup ).
		 * @return array For hit().
		 */
		public static function ip_limits( $form ) {
			$ip    = md5( self::client_ip() );
			$short = ( 'wdk' === $form ) ? self::LIMIT_DAY : self::LIMIT_SHORT;
			return array(
				'wpec_' . $form . '_s_' . $ip => array( $short, self::LIMIT_SHORT_WINDOW ),
				'wpec_' . $form . '_d_' . $ip => array( self::LIMIT_DAY, DAY_IN_SECONDS ),
			);
		}

		/**
		 * The robot check to show before the next try from this address: the human check set up on Settings › Checkout protection
		 * ( Turnstile or reCAPTCHA ), once the address made 2 tries in 15 minutes.
		 *
		 * @param string $form wd | lk.
		 * @return array|null provider ( turnstile | recaptcha ), site_key.
		 */
		public static function check_needed( $form ) {
			if ( ! class_exists( 'wp_easycart_checkout_guard' ) || ! wp_easycart_checkout_guard::check_available() ) {
				return null;
			}
			if ( self::count( 'wpec_' . $form . '_s_' . md5( self::client_ip() ), self::LIMIT_SHORT_WINDOW ) < 2 ) {
				return null;
			}
			$settings = wp_easycart_checkout_guard::settings();
			return array(
				'provider' => ( 'recaptcha' === $settings['provider'] ) ? 'recaptcha' : 'turnstile',
				'site_key' => wp_easycart_checkout_guard::site_key(),
			);
		}

		/**
		 * Did the posted robot check pass? A provider that does not answer lets the visitor through ( the limits still apply ).
		 *
		 * @param array $check check_needed().
		 * @return bool
		 */
		public static function check_passed( $check ) {
			if ( ! $check ) {
				return true;
			}
			$field = ( 'recaptcha' === $check['provider'] ) ? 'g-recaptcha-response' : 'cf-turnstile-response';
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- the robot check's own token; the form has no nonce on cached pages.
			$token = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
			if ( '' === $token ) {
				return false;
			}
			return 'fail' !== wp_easycart_checkout_guard::verify_token( $token );
		}

		/**
		 * Load the robot check's script ( only on the page that shows it ).
		 *
		 * @param array|null $check check_needed().
		 */
		public static function enqueue_check( $check ) {
			if ( ! $check ) {
				return;
			}
			if ( 'recaptcha' === $check['provider'] ) {
				wp_enqueue_script( 'wpeasycart-withdrawal-recaptcha', 'https://www.google.com/recaptcha/api.js', array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- the provider's own address.
			} else {
				wp_enqueue_script( 'wpeasycart-withdrawal-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- the provider's own address.
			}
		}

		// Requests: the forms post here ( template_redirect ).

		/** Keep visitor-specific answers out of page caches. */
		public static function no_cache() {
			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- the page cache plugins' own constant.
			}
			if ( ! defined( 'DONOTCDN' ) ) {
				define( 'DONOTCDN', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- the page cache plugins' own constant.
			}
			if ( ! headers_sent() ) {
				nocache_headers();
			}
		}

		/**
		 * The forms' posts and the pages a link opens ( template_redirect ): never cached; a post is worked out here and drawn by the
		 * shortcode ( or the account page ); a confirmed statement goes to its received page ( 303 ).
		 */
		public static function handle_request() {
			// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- the forms work on cached pages, so they carry no page nonce: the order's key, the matching email and the rate limits protect them.
			$get  = isset( $_GET['wpec_order'] ) || isset( $_GET['wpec_withdrawal'] ) || isset( $_GET['wpec_view'] );
			$post = isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) && ( isset( $_POST['wpec_wd_step'] ) || isset( $_POST['wpec_lookup_step'] ) );
			if ( ! $get && ! $post ) {
				return;
			}
			self::no_cache();
			if ( $post && isset( $_POST['wpec_lookup_step'] ) && class_exists( 'wp_easycart_order_lookup' ) ) {
				self::$state = wp_easycart_order_lookup::handle_post();
			} elseif ( $post && isset( $_POST['wpec_wd_step'] ) ) {
				self::$state = self::handle_post();
			}
			// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
		}

		/**
		 * The answer to this request's post.
		 *
		 * @return array|null
		 */
		public static function state() {
			return self::$state;
		}

		/**
		 * The withdrawal form's posted values, cleaned.
		 *
		 * @return array
		 */
		private static function posted() {
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- see handle_request().
			/* Each ticked item ( wpec_wd_pick[ line ] ) with its quantity ( wpec_wd_items[ line ], 1 when not posted ). */
			$items = array();
			$units = ( isset( $_POST['wpec_wd_items'] ) && is_array( $_POST['wpec_wd_items'] ) ) ? array_map( 'absint', wp_unslash( $_POST['wpec_wd_items'] ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- cast to integers here.
			if ( isset( $_POST['wpec_wd_pick'] ) && is_array( $_POST['wpec_wd_pick'] ) ) {
				foreach ( array_keys( wp_unslash( $_POST['wpec_wd_pick'] ) ) as $line_id ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only the keys are read, cast to integers.
					$line_id = absint( $line_id );
					if ( $line_id > 0 ) {
						$items[ $line_id ] = max( 1, isset( $units[ $line_id ] ) ? (int) $units[ $line_id ] : 1 );
					}
				}
			}
			$values = array(
				'order'     => isset( $_POST['wpec_wd_order'] ) ? absint( $_POST['wpec_wd_order'] ) : 0,
				'key'       => ( isset( $_POST['wpec_wd_key'] ) && class_exists( 'wp_easycart_order_pay' ) ) ? wp_easycart_order_pay::clean_key( sanitize_text_field( wp_unslash( $_POST['wpec_wd_key'] ) ) ) : '',
				'order_ref' => isset( $_POST['wpec_wd_order_ref'] ) ? substr( preg_replace( '/[^A-Za-z0-9\-]/', '', sanitize_text_field( wp_unslash( $_POST['wpec_wd_order_ref'] ) ) ), 0, 64 ) : '',
				'name'      => isset( $_POST['wpec_wd_name'] ) ? substr( sanitize_text_field( wp_unslash( $_POST['wpec_wd_name'] ) ), 0, 255 ) : '',
				'email'     => isset( $_POST['wpec_wd_email'] ) ? sanitize_email( wp_unslash( $_POST['wpec_wd_email'] ) ) : '',
				'reason'    => isset( $_POST['wpec_wd_reason'] ) ? substr( sanitize_textarea_field( wp_unslash( $_POST['wpec_wd_reason'] ) ), 0, 500 ) : '',
				'note'      => isset( $_POST['wpec_wd_note'] ) ? substr( sanitize_textarea_field( wp_unslash( $_POST['wpec_wd_note'] ) ), 0, 500 ) : '',
				'items'     => $items,
				'website'   => isset( $_POST['wpec_wd_website'] ) ? sanitize_text_field( wp_unslash( $_POST['wpec_wd_website'] ) ) : '',
			);
			// phpcs:enable WordPress.Security.NonceVerification.Missing
			return $values;
		}

		/**
		 * Work out a post of the withdrawal form: back to the form with what is missing, on to the review step, or save the
		 * statement and go to its received page.
		 *
		 * @return array The page's state ( view, order, values, errors, check ).
		 */
		public static function handle_post() {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- see handle_request().
			$step   = isset( $_POST['wpec_wd_step'] ) ? sanitize_key( wp_unslash( $_POST['wpec_wd_step'] ) ) : '';
			$values = self::posted();
			if ( ! self::enabled() ) {
				return array( 'view' => 'off' );
			}
			$order = null;
			if ( $values['order'] > 0 ) {
				$order = self::find_by_key( $values['order'], $values['key'] );
				if ( ! $order ) {
					return array(
						'view'   => 'start',
						'errors' => array( self::text( 'link_invalid' ) ),
					);
				}
			}
			$form   = $order ? 'wdk' : 'wd';
			$errors = array();
			if ( '' !== $values['website'] ) {
				$errors[] = self::text( 'error_limit' ); /* the hidden field only a robot fills in */
			}
			if ( '' === trim( $values['name'] ) || '' === trim( $values['email'] ) || ( ! $order && '' === $values['order_ref'] ) ) {
				$errors[] = self::text( 'error_fields' );
			} elseif ( ! is_email( $values['email'] ) ) {
				$errors[] = self::text( 'error_email' );
			}
			if ( $order ) {
				$window = self::window( $order, true );
				$chosen = 0;
				foreach ( $values['items'] as $line_id => $units ) {
					if ( isset( $window['lines'][ $line_id ] ) && 'open' === $window['lines'][ $line_id ]['state'] ) {
						$values['items'][ $line_id ] = min( $units, (int) $window['lines'][ $line_id ]['available'] );
						++$chosen;
					} else {
						unset( $values['items'][ $line_id ] );
					}
				}
				if ( ! self::can_withdraw( $order ) ) {
					return array(
						'view'  => 'closed',
						'order' => $order,
					);
				}
				if ( 0 === $chosen ) {
					$errors[] = self::text( 'error_items' );
				}
			}
			$base = array(
				'order'  => $order,
				'values' => $values,
			);
			if ( 'edit' === $step || $errors ) {
				return $base + array(
					'view'   => 'form',
					'errors' => $errors,
				);
			}
			$check = $order ? null : self::check_needed( $form );
			if ( 'confirm' !== $step ) {
				return $base + array(
					'view'  => 'review',
					'check' => $check,
				);
			}
			if ( $check && ! self::check_passed( $check ) ) {
				return $base + array(
					'view'   => 'review',
					'check'  => $check,
					'errors' => array( self::text( 'error_check' ) ),
				);
			}
			if ( ! self::hit( self::ip_limits( $form ) ) ) {
				return $base + array(
					'view'   => 'review',
					'check'  => $check,
					'errors' => array( self::text( 'error_limit' ) ),
				);
			}
			$user_id = isset( $GLOBALS['ec_cart_data']->cart_data->user_id ) ? (int) $GLOBALS['ec_cart_data']->cart_data->user_id : 0;
			if ( $order ) {
				$args = array(
					'order'   => $order,
					'lines'   => $values['items'],
					'source'  => ( $user_id > 0 && $user_id === (int) $order->user_id ) ? 'account' : 'link',
					'user_id' => (int) $order->user_id,
				);
			} else {
				$matched = self::find_by_email( $values['order_ref'], $values['email'] );
				$args    = array(
					'order'     => $matched,
					'order_ref' => $values['order_ref'],
					'source'    => 'page',
					'user_id'   => $matched ? (int) $matched->user_id : 0,
					/* A receipt to an address only so many times a day ( the form can be sent to any address ). */
					'ack'       => self::hit( array( 'wpec_wd_ack_' . md5( strtolower( $values['email'] ) ) => array( self::LIMIT_EMAIL, DAY_IN_SECONDS ) ) ),
				);
			}
			$record = self::submit(
				$args + array(
					'name'   => $values['name'],
					'email'  => $values['email'],
					'reason' => $values['reason'],
					'note'   => $order ? '' : $values['note'],
				)
			);
			if ( is_wp_error( $record ) ) {
				return $base + array(
					'view'   => 'form',
					'errors' => array( $record->get_error_message() ),
				);
			}
			wp_safe_redirect( self::received_url( $record ), 303 );
			exit;
		}

		/**
		 * The page's address as this request asked for it, without the withdrawal arguments.
		 *
		 * @return string
		 */
		public static function current_url() {
			$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
			$url = home_url( $uri );
			return remove_query_arg( array( 'wpec_order', 'wpec_key', 'wpec_withdrawal', 'wpec_wd', 'wpec_t', 'wpec_view' ), $url );
		}

		/**
		 * The key the received page's links are signed with.
		 *
		 * @return string
		 */
		private static function secret() {
			$secret = (string) get_option( self::SECRET, '' );
			if ( strlen( $secret ) < 32 ) {
				$secret = wp_generate_password( 64, true, true );
				if ( ! add_option( self::SECRET, $secret, '', false ) ) {
					$secret = (string) get_option( self::SECRET, $secret ); /* another request made it first */
				}
			}
			return $secret;
		}

		/**
		 * The received page's signature for a statement.
		 *
		 * @param object $record Statement.
		 * @return string
		 */
		public static function token( $record ) {
			return substr( hash_hmac( 'sha256', 'wd|' . (int) $record->withdrawal_id . '|' . (string) $record->submitted_at, self::secret() ), 0, 32 );
		}

		/**
		 * The received page of a statement.
		 *
		 * @param object $record Statement.
		 * @return string
		 */
		public static function received_url( $record ) {
			return add_query_arg(
				array(
					'wpec_withdrawal' => 'received',
					'wpec_wd'         => (int) $record->withdrawal_id,
					'wpec_t'          => self::token( $record ),
				),
				self::current_url()
			);
		}

		// The page.

		/**
		 * [ec_withdrawal]: the withdrawal page. heading="1" prints its own heading ( the page title usually is the heading ).
		 *
		 * @param array $atts Shortcode attributes.
		 * @return string
		 */
		public static function shortcode( $atts ) {
			$atts = shortcode_atts( array( 'heading' => '0' ), $atts, 'ec_withdrawal' );
			return self::render( array( 'heading' => '1' === (string) $atts['heading'] ) );
		}

		/**
		 * The withdrawal page or the order lookup on the account page ( ec_page=withdrawal | order_lookup ), from load_ec_account().
		 *
		 * @return string|null HTML, or null for any other account page.
		 */
		public static function account_route() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which page to draw.
			$page = isset( $_GET['ec_page'] ) ? sanitize_key( wp_unslash( $_GET['ec_page'] ) ) : '';
			if ( 'withdrawal' === $page ) {
				return self::render( array( 'heading' => true ) );
			}
			if ( 'order_lookup' === $page && class_exists( 'wp_easycart_order_lookup' ) ) {
				return wp_easycart_order_lookup::render( array( 'heading' => true ) );
			}
			return null;
		}

		/**
		 * What the page shows for this request.
		 *
		 * @param array $args heading.
		 * @return array For ec_withdrawal.php.
		 */
		public static function view_data( $args = array() ) {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- links: the order's key or the statement's signature is the credential.
			$data  = array(
				'view'     => 'start',
				'heading'  => ! empty( $args['heading'] ),
				'errors'   => array(),
				'values'   => array(),
				'order'    => null,
				'summary'  => null,
				'record'   => null,
				'check'    => null,
				'action'   => self::current_url(),
				'page_url' => self::page_url(),
				'lookup'   => class_exists( 'wp_easycart_order_lookup' ) ? wp_easycart_order_lookup::url() : '',
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'language' => function_exists( 'wp_easycart_language' ) ? (string) wp_easycart_language()->get_language_code() : '',
				'next'     => trim( (string) get_option( self::OPT_NEXT, '' ) ),
			);
			$state = self::$state;
			if ( is_array( $state ) && isset( $state['view'] ) && 'lookup' === $state['view'] ) {
				$data['view'] = 'lookup'; /* the order lookup posted here: wp_easycart_order_lookup::render() draws its answer */
			} elseif ( ! self::enabled() ) {
				$data['view'] = 'off';
			} elseif ( is_array( $state ) && isset( $state['view'] ) ) {
				$data = array_merge( $data, $state );
			} elseif ( isset( $_GET['wpec_withdrawal'] ) && 'received' === $_GET['wpec_withdrawal'] ) {
				$record = self::get( isset( $_GET['wpec_wd'] ) ? absint( $_GET['wpec_wd'] ) : 0 );
				$token  = isset( $_GET['wpec_t'] ) ? sanitize_key( wp_unslash( $_GET['wpec_t'] ) ) : '';
				if ( $record && '' !== $token && hash_equals( self::token( $record ), $token ) ) {
					$data['view']   = 'received';
					$data['record'] = $record;
				} else {
					$data['errors'][] = self::text( 'link_invalid' );
				}
			} elseif ( isset( $_GET['wpec_view'] ) && 'lookup' === $_GET['wpec_view'] ) {
				$data['view'] = 'lookup';
			} elseif ( isset( $_GET['wpec_order'] ) ) {
				$order = self::find_by_key( absint( $_GET['wpec_order'] ), isset( $_GET['wpec_key'] ) ? sanitize_text_field( wp_unslash( $_GET['wpec_key'] ) ) : '' );
				if ( ! $order ) {
					$data['errors'][] = self::text( 'link_invalid' );
				} else {
					$data['order'] = $order;
					$data['view']  = self::can_withdraw( $order ) ? 'form' : 'closed';
				}
			}
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			if ( $data['order'] ) {
				$data['summary'] = self::order_summary( $data['order'] );
				if ( 'closed' === $data['view'] ) {
					$latest = self::for_order( (int) $data['order']->order_id );
					if ( $latest && ! empty( $data['summary']['withdrawn'] ) ) {
						$data['view']   = 'already';
						$data['record'] = $latest[0];
					}
				}
				if ( 'form' === $data['view'] && ! $data['values'] ) {
					$data['values'] = self::order_defaults( $data['order'], $data['summary'] );
				}
			}
			/**
			 * What the withdrawal page shows ( ec_withdrawal.php ).
			 *
			 * @since 6.0.4
			 * @param array $data view ( start | form | review | received | closed | already | lookup | off ), heading, errors, values, order,
			 *                    summary, record, check, action, page_url, lookup, ajax_url, language, next.
			 */
			return (array) apply_filters( 'wp_easycart_withdrawal_view', $data );
		}

		/**
		 * An order for the form: its number, date and lines with what can be withdrawn.
		 *
		 * @param object $order Order row.
		 * @return array id, date, deadline_text, lines ( window() lines with state_text ), open, withdrawn.
		 */
		public static function order_summary( $order ) {
			$window = self::window( $order );
			$lines  = array();
			foreach ( $window['lines'] as $line ) {
				$state_text = '';
				if ( 'exempt' === $line['state'] ) {
					$state_text = self::text( 'item_exempt' );
				} elseif ( 'withdrawn' === $line['state'] ) {
					$state_text = self::text( 'item_withdrawn' );
				} elseif ( 'refunded' === $line['state'] ) {
					$state_text = self::text( 'item_refunded' );
				} elseif ( 'closed' === $line['state'] ) {
					$state_text = self::text( 'item_closed', array( 'date' => self::day( $line['deadline'] ) ) );
				}
				$line['state_text'] = $state_text;
				$lines[]            = $line;
			}
			if ( $window['open'] && $window['deadline'] > 0 ) {
				$deadline_text = self::text( 'order_until', array( 'date' => self::day( $window['deadline'] ) ) );
			} elseif ( $window['open'] ) {
				$deadline_text = self::text( 'order_pending' );
			} else {
				$deadline_text = '';
			}
			return array(
				'id'            => (int) $order->order_id,
				'date'          => self::day( isset( $order->wpec_order_ts ) ? (int) $order->wpec_order_ts : time() ),
				'deadline_text' => $deadline_text,
				'lines'         => $lines,
				'open'          => ! empty( $window['open'] ),
				'withdrawn'     => ! empty( $window['withdrawn'] ) || ! $window['open'],
			);
		}

		/**
		 * The form's first values for an order: every open item, the billing name and the order's email ( the customer can change
		 * them: "provide or confirm" ).
		 *
		 * @param object $order   Order row.
		 * @param array  $summary order_summary().
		 * @return array
		 */
		private static function order_defaults( $order, $summary ) {
			$items = array();
			foreach ( $summary['lines'] as $line ) {
				if ( 'open' === $line['state'] && $line['available'] > 0 ) {
					$items[ $line['id'] ] = $line['available'];
				}
			}
			return array(
				'order'     => (int) $order->order_id,
				'key'       => class_exists( 'wp_easycart_order_pay' ) ? wp_easycart_order_pay::clean_key( (string) $order->guest_key ) : '',
				'order_ref' => (string) (int) $order->order_id,
				'name'      => trim( wp_unslash( (string) $order->billing_first_name ) . ' ' . wp_unslash( (string) $order->billing_last_name ) ),
				'email'     => (string) $order->user_email,
				'reason'    => '',
				'note'      => '',
				'items'     => $items,
			);
		}

		/**
		 * Draw the withdrawal page.
		 *
		 * @param array $args heading.
		 * @return string
		 */
		public static function render( $args = array() ) {
			$withdrawal = self::view_data( $args );
			self::enqueue_check( isset( $withdrawal['check'] ) ? $withdrawal['check'] : null );
			ob_start();
			include self::template( 'ec_withdrawal.php' );
			return (string) ob_get_clean();
		}

		/**
		 * AJAX ec_ajax_withdrawal_orders: the signed-in customer's orders they can still withdraw from, for the withdrawal page
		 * ( asked after the page loads, so a cached page never shows one customer's orders to another ). No nonce: it only reads the
		 * orders of whoever asks ( their own session ).
		 */
		public static function ajax_orders() {
			nocache_headers();
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- read-only: the visitor's own orders.
			if ( isset( $_POST['language'] ) && function_exists( 'wp_easycart_language' ) ) {
				wp_easycart_language()->set_language( sanitize_text_field( wp_unslash( $_POST['language'] ) ) );
			}
			// phpcs:enable WordPress.Security.NonceVerification.Missing
			$user_id = isset( $GLOBALS['ec_cart_data']->cart_data->user_id ) ? (int) $GLOBALS['ec_cart_data']->cart_data->user_id : 0;
			if ( ! self::enabled() || $user_id <= 0 ) {
				wp_send_json_success(
					array(
						'signed_in' => false,
						'html'      => '',
					)
				);
			}
			wp_send_json_success(
				array(
					'signed_in' => true,
					'html'      => self::orders_html( $user_id ),
				)
			);
		}

		/**
		 * A customer's recent orders they can withdraw from, as a list for the withdrawal page.
		 *
		 * @param int $user_id Customer account.
		 * @return string
		 */
		public static function orders_html( $user_id ) {
			global $wpdb;
			$items = array();
			foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SELECT order_id FROM ec_order WHERE user_id = %d ORDER BY order_id DESC LIMIT 25', (int) $user_id ) ) as $order_id ) {
				$order = self::get_order( (int) $order_id );
				if ( ! $order || ! self::can_withdraw( $order ) ) {
					continue;
				}
				$summary = self::order_summary( $order );
				$items[] = '<li class="ec_withdrawal_order"><span class="ec_withdrawal_order_text"><strong>#' . esc_html( (string) (int) $order->order_id ) . '</strong> · ' . esc_html( $summary['date'] ) . ( '' !== $summary['deadline_text'] ? '<br><small>' . esc_html( $summary['deadline_text'] ) . '</small>' : '' ) . '</span>' . self::button_html( self::form_url( $order ), 'list' ) . '</li>';
			}
			$html  = '<h3 class="ec_withdrawal_subtitle">' . esc_html( self::text( 'your_orders' ) ) . '</h3>';
			$html .= $items ? '<ul class="ec_withdrawal_orders">' . implode( '', $items ) . '</ul>' : '<p class="ec_withdrawal_muted">' . esc_html( self::text( 'your_orders_none' ) ) . '</p>';
			return $html;
		}

		// The button.

		/**
		 * The labelled button ( ec_withdrawal_button.php ).
		 *
		 * @param string $url     Where it goes.
		 * @param string $context success | order | footer | menu | list | shortcode.
		 * @param string $note    A line under it ( plain text ).
		 * @return string
		 */
		public static function button_html( $url, $context = 'order', $note = '' ) {
			if ( '' === (string) $url ) {
				return '';
			}
			$button       = array(
				'url'     => (string) $url,
				'label'   => self::text( 'withdraw_button' ),
				'note'    => (string) $note,
				'context' => sanitize_key( $context ),
				'styles'  => ! self::$styled,
			);
			self::$styled = true;
			ob_start();
			include self::template( 'ec_withdrawal_button.php' );
			return (string) ob_get_clean();
		}

		/**
		 * The line under an order's button: until when, or that the period starts on delivery.
		 *
		 * @param object $order Order row.
		 * @return string
		 */
		private static function deadline_note( $order ) {
			$window = self::window( $order );
			if ( $window['deadline'] > 0 ) {
				return self::text( 'order_until', array( 'date' => self::day( $window['deadline'] ) ) );
			}
			return self::text( 'order_pending' );
		}

		/**
		 * The order confirmation page ( wpeasycart_success_page_content_middle ).
		 *
		 * @param int $order_id Order.
		 */
		public static function success_button( $order_id ) {
			$row = self::get_order( (int) $order_id );
			if ( $row && self::can_withdraw( $row ) ) {
				echo self::button_html( self::form_url( $row ), 'success', self::deadline_note( $row ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the template escapes every part.
			}
		}

		/**
		 * My Account order details and the guest order view ( wp_easycart_order_details_header_right ).
		 *
		 * @param object $order ec_orderdisplay.
		 */
		public static function order_details_button( $order ) {
			if ( ! is_object( $order ) || empty( $order->order_id ) ) {
				return;
			}
			$row = self::get_order( (int) $order->order_id );
			if ( $row && self::can_withdraw( $row ) ) {
				echo self::button_html( self::form_url( $row ), 'order' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the template escapes every part.
			}
		}

		/**
		 * The receipt and shipped emails ( wp_easycart_document_email_links; the document switch "Withdrawal button" ).
		 *
		 * @param string $email     The email: receipt | shipping | ….
		 * @param int    $order_id  Order.
		 * @param string $recipient customer | admin.
		 */
		public static function email_row( $email, $order_id, $recipient = 'customer' ) {
			if ( 'customer' !== $recipient || ! in_array( $email, array( 'receipt', 'shipping' ), true ) || ! class_exists( 'wp_easycart_email_design' ) ) {
				return;
			}
			if ( class_exists( 'wp_easycart_documents' ) ) {
				$fields = wp_easycart_documents::current_fields( $email );
				if ( null === $fields ) {
					$fields = wp_easycart_documents::resolve( $email, '', array(), (int) $order_id );
				}
				if ( ! wp_easycart_documents::show( $fields, 'withdrawal', $email ) ) {
					return;
				}
			}
			$row = self::get_order( (int) $order_id );
			if ( ! $row || ! self::can_withdraw( $row ) ) {
				return;
			}
			$window = self::window( $row );
			$text   = ( $window['deadline'] > 0 ) ? self::text( 'email_row_until', array( 'date' => self::day( $window['deadline'] ) ) ) : self::text( 'email_row_open' );
			$ed     = 'wp_easycart_email_design';
			$ed::section_start( array( 'top' => 20 ) );
			$ed::paragraph( esc_html( $text ), array( 'margin' => '0 0 10px 0' ) );
			$ed::button( self::form_url( $row ), self::text( 'withdraw_button' ), array( 'variant' => 'secondary' ) );
			$ed::section_end();
		}

		/** The account menu ( wpeasycart_account_links_after_subscriptions ). */
		public static function account_menu_link() {
			if ( ! self::enabled() ) {
				return;
			}
			echo '<div class="ec_cart_input_row"><a class="ec_account_dashboard_link ec_withdrawal_menu_link" href="' . esc_url( self::page_url() ) . '">' . esc_html( self::text( 'withdraw_button' ) ) . '</a></div>';
		}

		/** The button at the foot of every storefront page ( Settings: "Show the button at the foot of every page" ). */
		public static function footer_button() {
			if ( is_admin() || is_feed() || is_embed() || ! self::enabled() || ! self::settings()['footer'] ) {
				return;
			}
			if ( function_exists( 'wp_easycart_elementor_is_editor' ) && wp_easycart_elementor_is_editor() ) {
				return;
			}
			$page_id = self::page_id();
			if ( $page_id > 0 && is_page( $page_id ) ) {
				return; /* the page itself */
			}
			/**
			 * Print the withdrawal button at the foot of this page.
			 *
			 * @since 6.0.4
			 * @param bool $show Yes.
			 */
			if ( ! apply_filters( 'wp_easycart_withdrawal_footer_button', true ) ) {
				return;
			}
			echo self::button_html( self::page_url(), 'footer' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the template escapes every part.
		}

		/**
		 * [ec_withdrawal_button]: the button alone, to the withdrawal page.
		 *
		 * @return string
		 */
		public static function button_shortcode() {
			return self::enabled() ? self::button_html( self::page_url(), 'shortcode' ) : '';
		}

		// Privacy.

		/**
		 * Filter wp_privacy_personal_data_exporters: a customer's withdrawal statements. The eraser leaves them, as it leaves
		 * orders: they are the store's record of a legal request.
		 *
		 * @param array $exporters Exporters.
		 * @return array
		 */
		public static function register_exporter( $exporters ) {
			$exporters['wp-easycart-withdrawals'] = array(
				'exporter_friendly_name' => __( 'WP EasyCart withdrawals', 'wp-easycart' ),
				'callback'               => array( __CLASS__, 'export' ),
			);
			return $exporters;
		}

		/**
		 * The privacy export.
		 *
		 * @param string $email Email address.
		 * @param int    $page  Page.
		 * @return array
		 */
		public static function export( $email, $page = 1 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- the exporter's signature; every statement fits one page.
			global $wpdb;
			$data = array();
			if ( self::ready() && is_email( $email ) ) {
				foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ec_order_withdrawal WHERE customer_email = %s ORDER BY withdrawal_id ASC', $email ) ) as $record ) {
					$items = array();
					foreach ( self::summary_rows( $record ) as $row ) {
						$items[] = array(
							'name'  => $row['label'],
							'value' => $row['value'],
						);
					}
					$data[] = array(
						'group_id'    => 'wp-easycart-withdrawals',
						'group_label' => __( 'Withdrawals', 'wp-easycart' ),
						'item_id'     => 'withdrawal-' . (int) $record->withdrawal_id,
						'data'        => $items,
					);
				}
			}
			return array(
				'data' => $data,
				'done' => true,
			);
		}
	}

	wp_easycart_withdrawals::init();

endif;
