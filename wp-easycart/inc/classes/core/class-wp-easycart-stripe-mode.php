<?php
/**
 * Stripe live and test mode ( 6.0.4 ): which Stripe keys each request uses.
 *
 * A store takes card payments through Stripe ( WP EasyCart's Stripe Connect, gateway stripe_connect ) or with its own API keys
 * ( WP EasyCart PRO's Stripe v1, gateway stripe ). Each keeps live and test credentials and is in live or test mode for the whole
 * store. A customer account can also be a "Stripe test-mode customer" ( ec_user.is_stripe_test_user, Users › customer › Preferences ):
 * while that customer is signed in on the storefront, their checkout uses the test credentials and everybody else keeps paying live.
 * The flag never applies in the admin ( staff screens, refunds, reports ), in WP-Cron or in Stripe's notifications: those run in the
 * store's mode, or in the mode of the Stripe event or order they act on ( force() ).
 *
 * mode() is the one answer. Code that still reads the options directly gets it too: get_option() of ec_option_stripe_public_api_key and
 * ec_option_stripe_api_key ( Stripe v1 ) and ec_option_stripe_connect_use_sandbox ( Stripe Connect ) answers with the request's mode
 * ( pre_option filters, only while that gateway is the store's ), so theme copies of the checkout templates and WP EasyCart PRO releases
 * before 6.0.4 follow it. raw() reads what is saved.
 *
 * Stripe v1 options: ec_option_stripe_public_api_key / ec_option_stripe_api_key are the live keys ( before 6.0.4 the only pair: a store
 * that saved test keys there is in test mode, as it always was ), ec_option_stripe_sandbox_public_api_key / ec_option_stripe_sandbox_api_key
 * the test keys, ec_option_stripe_use_sandbox the mode chosen ( '1' test, '0' live, missing = by the keys saved ). A mode whose keys are
 * missing falls back to the keys that are there, so a store is never left without keys by its switch. Until WP EasyCart PRO 6.0.4's
 * panel is saved ( v1_legacy() ) the one saved pair is used for everything, exactly as before 6.0.4: checkout, Stripe's notifications,
 * refunds, renewals. Only a test-mode customer is told that it pays with those keys ( live keys: live ).
 *
 * Before 6.0.4 the flag swapped keys in WP EasyCart PRO ( maybe_customize_stripe_api_key / _publishable_key ), for Stripe Connect's test
 * keys only and also in the admin; a Stripe v1 store got Connect's test publishable key beside its own live secret key. Those callbacks
 * are taken off ( retire_pro_key_swap() ).
 *
 * @package  Wp_Easycart
 * @since    6.0.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_stripe_mode' ) ) :

	/**
	 * Stripe live and test mode.
	 */
	final class wp_easycart_stripe_mode {

		/** Live mode. */
		const LIVE = 'live';

		/** Test mode ( Stripe's sandbox ). */
		const TEST = 'test';

		/** Order log key of an order paid in Stripe test mode. */
		const ORDER_LOG_KEY = 'stripe-test-payment';

		/**
		 * Modes forced for this request, the last one wins ( force() / release() ).
		 *
		 * @var string[]
		 */
		private static $forced = array();

		/**
		 * More than 0 while raw() reads an option, so the pre_option filters stand back.
		 *
		 * @var int
		 */
		private static $raw = 0;

		/**
		 * Test-mode customer accounts, counted once a request.
		 *
		 * @var int|null
		 */
		private static $customers = null;

		/** Hooks. */
		public static function init() {
			add_filter( 'pre_option_ec_option_stripe_public_api_key', array( __CLASS__, 'filter_publishable_key' ) );
			add_filter( 'pre_option_ec_option_stripe_api_key', array( __CLASS__, 'filter_secret_key' ) );
			add_filter( 'pre_option_ec_option_stripe_connect_use_sandbox', array( __CLASS__, 'filter_connect_sandbox' ) );
			add_filter( 'wp_easycart_is_stripe_sandbox', array( __CLASS__, 'filter_is_sandbox' ) );
			add_filter( 'wp_easycart_admin_order_history_entry', array( __CLASS__, 'history_entry' ), 10, 3 );
			add_filter( 'wp_easycart_ecv2_order_flags', array( __CLASS__, 'order_flags' ), 10, 2 );
			add_action( 'plugins_loaded', array( __CLASS__, 'retire_pro_key_swap' ), 20 );
		}

		/**
		 * The Stripe gateway the store takes cards with: stripe ( API keys, WP EasyCart PRO ), stripe_connect, or '' for another gateway.
		 *
		 * @return string
		 */
		public static function gateway() {
			$method = (string) get_option( 'ec_option_payment_process_method' );
			return in_array( $method, array( 'stripe', 'stripe_connect' ), true ) ? $method : '';
		}

		/**
		 * A saved option as it is stored, past this class's pre_option filters.
		 *
		 * @param string $option   Option name.
		 * @param mixed  $fallback Value when it is missing.
		 * @return mixed
		 */
		public static function raw( $option, $fallback = '' ) {
			++self::$raw;
			$value = get_option( $option, $fallback );
			--self::$raw;
			return $value;
		}

		/**
		 * Whether a Stripe key is a test key ( pk_test_, sk_test_, rk_test_ ).
		 *
		 * @param string $key Key.
		 * @return bool
		 */
		public static function is_test_key( $key ) {
			return (bool) preg_match( '/^[a-z]{2}_test_/', trim( (string) $key ) );
		}

		/**
		 * Whether a Stripe key is a live key ( pk_live_, sk_live_, rk_live_ ).
		 *
		 * @param string $key Key.
		 * @return bool
		 */
		public static function is_live_key( $key ) {
			return (bool) preg_match( '/^[a-z]{2}_live_/', trim( (string) $key ) );
		}

		/**
		 * The Stripe v1 keys as saved.
		 *
		 * @return array live_publishable, live_secret, test_publishable, test_secret.
		 */
		public static function v1_saved() {
			return array(
				'live_publishable' => trim( (string) self::raw( 'ec_option_stripe_public_api_key' ) ),
				'live_secret'      => trim( (string) self::raw( 'ec_option_stripe_api_key' ) ),
				'test_publishable' => trim( (string) self::raw( 'ec_option_stripe_sandbox_public_api_key' ) ),
				'test_secret'      => trim( (string) self::raw( 'ec_option_stripe_sandbox_api_key' ) ),
			);
		}

		/**
		 * Whether the Stripe v1 keys are as before 6.0.4: no test keys saved and no mode chosen ( WP EasyCart PRO 6.0.4's panel not
		 * saved yet ). Then the one saved pair is used for every request, whatever its mode, so an update changes no live payment.
		 *
		 * @param array|null $saved v1_saved().
		 * @return bool
		 */
		public static function v1_legacy( $saved = null ) {
			$saved = ( null === $saved ) ? self::v1_saved() : $saved;
			return '' === $saved['test_publishable'] && '' === $saved['test_secret'] && '' === trim( (string) self::raw( 'ec_option_stripe_use_sandbox' ) );
		}

		/**
		 * A gateway's credentials for a mode. Stripe v1 test keys saved in the live fields before 6.0.4 count as test keys ( and so
		 * as no live keys ) once the new panel has been saved; before that the one pair is used for both ( v1_legacy() ).
		 *
		 * @param string      $mode    live | test.
		 * @param string|null $gateway stripe | stripe_connect; null for the store's.
		 * @return array publishable, secret, account ( Stripe Connect's account id ).
		 */
		public static function keys( $mode, $gateway = null ) {
			$gateway = ( null === $gateway ) ? self::gateway() : $gateway;
			$keys    = array(
				'publishable' => '',
				'secret'      => '',
				'account'     => '',
			);
			if ( 'stripe' === $gateway ) {
				$saved = self::v1_saved();
				if ( self::v1_legacy( $saved ) ) {
					$keys['publishable'] = $saved['live_publishable'];
					$keys['secret']      = $saved['live_secret'];
					return $keys;
				}
				$old = self::is_test_key( $saved['live_publishable'] ) || self::is_test_key( $saved['live_secret'] );
				if ( self::TEST === $mode ) {
					if ( '' !== $saved['test_publishable'] && '' !== $saved['test_secret'] ) {
						$keys['publishable'] = $saved['test_publishable'];
						$keys['secret']      = $saved['test_secret'];
					} elseif ( $old ) {
						$keys['publishable'] = $saved['live_publishable'];
						$keys['secret']      = $saved['live_secret'];
					}
				} elseif ( ! $old ) {
					$keys['publishable'] = $saved['live_publishable'];
					$keys['secret']      = $saved['live_secret'];
				}
			} elseif ( 'stripe_connect' === $gateway ) {
				$env                 = ( self::TEST === $mode ) ? 'sandbox' : 'production';
				$keys['publishable'] = trim( (string) self::raw( 'ec_option_stripe_connect_' . $env . '_publishable_key' ) );
				$keys['secret']      = trim( (string) self::raw( 'ec_option_stripe_connect_' . $env . '_access_token' ) );
				$keys['account']     = trim( (string) self::raw( 'ec_option_stripe_connect_' . $env . '_user_id' ) );
			}
			return $keys;
		}

		/**
		 * Whether a gateway has the credentials for a mode.
		 *
		 * @param string      $mode    live | test.
		 * @param string|null $gateway stripe | stripe_connect; null for the store's.
		 * @return bool
		 */
		public static function ready( $mode, $gateway = null ) {
			$gateway = ( null === $gateway ) ? self::gateway() : $gateway;
			$keys    = self::keys( $mode, $gateway );
			if ( '' === $keys['publishable'] || '' === $keys['secret'] ) {
				return false;
			}
			if ( 'stripe' === $gateway && self::v1_legacy() ) {
				/* The one pair saved before 6.0.4 is a mode's keys only when it is that mode's. */
				return ( self::TEST === $mode ) === ( self::is_test_key( $keys['publishable'] ) || self::is_test_key( $keys['secret'] ) );
			}
			return 'stripe_connect' !== $gateway || '' !== $keys['account'];
		}

		/**
		 * The mode chosen for the whole store, whatever its keys ( Stripe v1: '' when nothing was chosen ).
		 *
		 * @param string|null $gateway stripe | stripe_connect; null for the store's.
		 * @return string live | test | ''.
		 */
		public static function chosen_mode( $gateway = null ) {
			$gateway = ( null === $gateway ) ? self::gateway() : $gateway;
			if ( 'stripe_connect' === $gateway ) {
				return self::raw( 'ec_option_stripe_connect_use_sandbox' ) ? self::TEST : self::LIVE;
			}
			$choice = (string) self::raw( 'ec_option_stripe_use_sandbox' );
			if ( '1' === $choice ) {
				return self::TEST;
			}
			return ( '0' === $choice ) ? self::LIVE : '';
		}

		/**
		 * The mode the whole store runs in: the mode chosen, while its keys are saved.
		 *
		 * @param string|null $gateway stripe | stripe_connect; null for the store's.
		 * @return string live | test.
		 */
		public static function store_mode( $gateway = null ) {
			$gateway = ( null === $gateway ) ? self::gateway() : $gateway;
			$chosen  = self::chosen_mode( $gateway );
			if ( 'stripe' !== $gateway ) {
				return ( self::TEST === $chosen ) ? self::TEST : self::LIVE;
			}
			if ( '' !== $chosen && self::ready( $chosen, 'stripe' ) ) {
				return $chosen;
			}
			/* Nothing chosen ( Stripe v1 before 6.0.4 ), or the chosen mode has no keys: the keys that are there. */
			if ( self::ready( self::LIVE, 'stripe' ) ) {
				return self::LIVE;
			}
			if ( self::ready( self::TEST, 'stripe' ) ) {
				return self::TEST;
			}
			return ( self::TEST === $chosen ) ? self::TEST : self::LIVE;
		}

		/**
		 * The mode this request runs in: forced ( a Stripe event, a test order ), else test for a test-mode customer on the storefront,
		 * else the store's.
		 *
		 * @param string|null $gateway stripe | stripe_connect; null for the store's.
		 * @return string live | test.
		 */
		public static function mode( $gateway = null ) {
			if ( self::$forced ) {
				return end( self::$forced );
			}
			$gateway = ( null === $gateway ) ? self::gateway() : $gateway;
			if ( '' === $gateway ) {
				return self::LIVE;
			}
			$store = self::store_mode( $gateway );
			if ( self::LIVE === $store && self::customer_test( $gateway ) ) {
				return self::TEST;
			}
			return $store;
		}

		/**
		 * Whether this request runs in test mode.
		 *
		 * @param string|null $gateway stripe | stripe_connect; null for the store's.
		 * @return bool
		 */
		public static function is_test( $gateway = null ) {
			return self::TEST === self::mode( $gateway );
		}

		/**
		 * Run in one mode until release(): a Stripe event in its own mode, a refund of a test order. Nests.
		 *
		 * @param string $mode live | test.
		 */
		public static function force( $mode ) {
			self::$forced[] = ( self::TEST === $mode ) ? self::TEST : self::LIVE;
		}

		/** Ends the last force(). */
		public static function release() {
			array_pop( self::$forced );
		}

		/**
		 * Whether this request comes from the storefront ( a page or the storefront's AJAX ), where a test-mode customer's flag applies.
		 * Admin screens and their AJAX, WP-Cron and WP-CLI never are.
		 *
		 * @return bool
		 */
		public static function storefront_request() {
			if ( ( defined( 'WP_CLI' ) && WP_CLI ) || ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) ) {
				return false;
			}
			if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only names the request's kind; nothing is read or saved from it.
				$action = isset( $_REQUEST['action'] ) && is_string( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
				if ( preg_match( '/^(ecv2_|ec_admin_|ecudv2_|ecodv2_|wp_easycart_admin)/', $action ) ) {
					return false;
				}
				$referer = function_exists( 'wp_get_raw_referer' ) ? (string) wp_get_raw_referer() : '';
				$admin   = (string) wp_parse_url( admin_url(), PHP_URL_PATH );
				if ( '' !== $referer && '' !== $admin && 0 === strpos( (string) wp_parse_url( $referer, PHP_URL_PATH ), $admin ) ) {
					return false;
				}
				return true;
			}
			return ! is_admin();
		}

		/**
		 * Whether the customer signed in on the storefront is a Stripe test-mode customer ( whatever keys the store has ).
		 *
		 * @return bool
		 */
		public static function customer_flagged() {
			$user = isset( $GLOBALS['ec_user'] ) ? $GLOBALS['ec_user'] : null;
			if ( ! is_object( $user ) || empty( $user->user_id ) || empty( $user->is_stripe_test_user ) ) {
				return false;
			}
			return self::storefront_request();
		}

		/**
		 * Whether this request is a test-mode customer's, on a gateway with test credentials.
		 *
		 * @param string|null $gateway stripe | stripe_connect; null for the store's.
		 * @return bool
		 */
		public static function customer_test( $gateway = null ) {
			$gateway = ( null === $gateway ) ? self::gateway() : $gateway;
			$test    = '' !== $gateway && self::customer_flagged() && self::ready( self::TEST, $gateway );
			/**
			 * Whether this request pays in Stripe test mode for the signed-in customer.
			 *
			 * @since 6.0.4
			 * @param bool   $test    Test mode.
			 * @param object $user    $GLOBALS['ec_user'].
			 * @param string $gateway stripe | stripe_connect.
			 */
			return (bool) apply_filters( 'wp_easycart_stripe_customer_test_mode', $test, isset( $GLOBALS['ec_user'] ) ? $GLOBALS['ec_user'] : null, $gateway );
		}

		/**
		 * Filter pre_option_ec_option_stripe_public_api_key: the Stripe v1 publishable key of this request's mode.
		 *
		 * @param mixed $pre Short-circuit value ( false = none ).
		 * @return mixed
		 */
		public static function filter_publishable_key( $pre ) {
			return self::filter_v1( $pre, 'publishable', 'ec_option_stripe_public_api_key' );
		}

		/**
		 * Filter pre_option_ec_option_stripe_api_key: the Stripe v1 secret key of this request's mode.
		 *
		 * @param mixed $pre Short-circuit value ( false = none ).
		 * @return mixed
		 */
		public static function filter_secret_key( $pre ) {
			return self::filter_v1( $pre, 'secret', 'ec_option_stripe_api_key' );
		}

		/**
		 * A Stripe v1 key for this request's mode, only when it differs from the saved one ( false lets WordPress read the option ).
		 *
		 * @param mixed  $pre    Short-circuit value.
		 * @param string $part   publishable | secret.
		 * @param string $option Option name.
		 * @return mixed
		 */
		private static function filter_v1( $pre, $part, $option ) {
			if ( false !== $pre || self::$raw > 0 || 'stripe' !== self::gateway() ) {
				return $pre;
			}
			$keys  = self::keys( self::mode( 'stripe' ), 'stripe' );
			$saved = trim( (string) self::raw( $option ) );
			return ( $saved === $keys[ $part ] ) ? $pre : $keys[ $part ];
		}

		/**
		 * Filter pre_option_ec_option_stripe_connect_use_sandbox: on for a request in test mode ( a test-mode customer, a test event ).
		 *
		 * @param mixed $pre Short-circuit value ( false = none ).
		 * @return mixed
		 */
		public static function filter_connect_sandbox( $pre ) {
			if ( false !== $pre || self::$raw > 0 || 'stripe_connect' !== self::gateway() ) {
				return $pre;
			}
			$test  = self::is_test( 'stripe_connect' );
			$saved = (bool) self::raw( 'ec_option_stripe_connect_use_sandbox' );
			return ( $saved === $test ) ? $pre : ( $test ? '1' : '0' );
		}

		/**
		 * Filter wp_easycart_is_stripe_sandbox ( the subscription price columns, the product's Stripe ids ).
		 *
		 * @param bool $is_sandbox Test mode.
		 * @return bool
		 */
		public static function filter_is_sandbox( $is_sandbox ) {
			return ( '' === self::gateway() ) ? (bool) $is_sandbox : self::is_test();
		}

		/** WP EasyCart PRO before 6.0.4 swapped in Stripe Connect's test keys for test-mode customers, also in the admin. */
		public static function retire_pro_key_swap() {
			if ( ! function_exists( 'wp_easycart_admin_pro' ) || ! class_exists( 'wp_easycart_admin_pro' ) ) {
				return;
			}
			$pro = wp_easycart_admin_pro();
			if ( is_object( $pro ) ) {
				remove_filter( 'wp_easycart_stripe_connect_api_key', array( $pro, 'maybe_customize_stripe_api_key' ) );
				remove_filter( 'wp_easycart_stripe_connect_publishable_key', array( $pro, 'maybe_customize_stripe_publishable_key' ) );
			}
		}

		/**
		 * How many customer accounts are Stripe test-mode customers.
		 *
		 * @return int
		 */
		public static function test_customers() {
			global $wpdb;
			if ( null === self::$customers ) {
				$suppress        = $wpdb->suppress_errors( true );
				self::$customers = (int) $wpdb->get_var( 'SELECT COUNT( user_id ) FROM ec_user WHERE is_stripe_test_user = 1' );
				$wpdb->suppress_errors( $suppress );
			}
			return self::$customers;
		}

		/**
		 * After an order is inserted: an order paid in Stripe test mode says so on its timeline, and refunds it in test mode
		 * ( order_mode() ). Called by ec_db after each insert that can be a Stripe payment.
		 *
		 * @param int $order_id Order.
		 */
		public static function order_inserted( $order_id ) {
			global $wpdb;
			$order_id = (int) $order_id;
			if ( $order_id <= 0 || ! function_exists( 'wp_easycart_order_log' ) ) {
				return;
			}
			$gateway = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT order_gateway FROM ec_order WHERE order_id = %d', $order_id ) );
			if ( ! in_array( $gateway, array( 'stripe', 'stripe_connect' ), true ) || ! self::is_test( $gateway ) ) {
				return;
			}
			if ( self::$forced ) {
				$reason = 'event';
			} elseif ( self::TEST === self::store_mode( $gateway ) ) {
				$reason = 'store';
			} else {
				$reason = 'customer';
			}
			wp_easycart_order_log( $order_id, self::ORDER_LOG_KEY, array( 'reason' => $reason ) );
		}

		/**
		 * The mode an order was paid in, when it was paid in test mode ( '' = live, or not known ).
		 *
		 * @param int $order_id Order.
		 * @return string test | ''.
		 */
		public static function order_mode( $order_id ) {
			global $wpdb;
			$order_id = (int) $order_id;
			if ( $order_id <= 0 ) {
				return '';
			}
			$found = $wpdb->get_var( $wpdb->prepare( 'SELECT order_log_id FROM ec_order_log WHERE order_id = %d AND order_log_key = %s LIMIT 1', $order_id, self::ORDER_LOG_KEY ) );
			return $found ? self::TEST : '';
		}

		/**
		 * Why an order was paid in test mode, in words.
		 *
		 * @param string $reason customer | store | event.
		 * @return string
		 */
		private static function reason_text( $reason ) {
			if ( 'customer' === $reason ) {
				return __( 'A Stripe test-mode customer paid with a test card: no money was taken.', 'wp-easycart' );
			}
			if ( 'store' === $reason ) {
				return __( 'Stripe was in test mode for the whole store: no money was taken.', 'wp-easycart' );
			}
			return __( 'Made from a Stripe test-mode payment: no money was taken.', 'wp-easycart' );
		}

		/**
		 * Filter wp_easycart_admin_order_history_entry ( WP EasyCart PRO's timeline ): an order paid in Stripe test mode.
		 *
		 * @param array|null $entry Entry.
		 * @param string     $key   Log key.
		 * @param array      $meta  The entry's meta by key.
		 * @return array|null
		 */
		public static function history_entry( $entry, $key, $meta = array() ) {
			if ( self::ORDER_LOG_KEY !== $key ) {
				return $entry;
			}
			$reason = ( is_array( $meta ) && isset( $meta['reason'] ) ) ? (string) $meta['reason'] : '';
			return array(
				'title'    => __( 'Paid in Stripe test mode', 'wp-easycart' ),
				'subtitle' => esc_html( self::reason_text( $reason ) ),
				'icon'     => 'dashicons-warning',
				'category' => 'payments',
			);
		}

		/**
		 * Filter wp_easycart_ecv2_order_flags: a test payment says so at the top of the order screen.
		 *
		 * @param array  $flags Flags.
		 * @param object $order Order row.
		 * @return array
		 */
		public static function order_flags( $flags, $order ) {
			if ( ! is_object( $order ) || empty( $order->order_id ) || ! in_array( isset( $order->order_gateway ) ? (string) $order->order_gateway : '', array( 'stripe', 'stripe_connect' ), true ) ) {
				return $flags;
			}
			if ( self::TEST !== self::order_mode( (int) $order->order_id ) ) {
				return $flags;
			}
			$flags   = (array) $flags;
			$flags[] = array(
				'kind'   => 'stripe_test',
				'title'  => __( 'Test payment', 'wp-easycart' ),
				'text'   => __( 'Paid in Stripe test mode, so no money was taken. Ship it only if this test was meant to go out.', 'wp-easycart' ),
				'tone'   => 'yellow',
				'icon'   => 'warning',
				'review' => false,
			);
			return $flags;
		}

		/**
		 * The notice above the Stripe payment form: a test-mode customer is told their checkout is in test mode and everyone else
		 * pays live ( or that the store has no test keys, so they pay live too ); a store manager is told the whole store is in
		 * test mode. Nothing for other shoppers.
		 *
		 * @return string HTML.
		 */
		public static function notice_html() {
			$gateway = self::gateway();
			if ( '' === $gateway ) {
				return '';
			}
			$store   = self::store_mode( $gateway );
			$flagged = self::customer_flagged();
			$manager = function_exists( 'current_user_can' ) && ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_manager' ) );
			$class   = 'ec_cart_success';
			if ( self::TEST === $store ) {
				if ( ! $flagged && ! $manager ) {
					return '';
				}
				$text = __( 'Stripe is in test mode for the whole store: pay with a Stripe test card, such as 4242 4242 4242 4242 with any future date and any CVC. No card is charged, for anyone, until you switch Stripe to live.', 'wp-easycart' );
			} elseif ( $flagged && self::customer_test( $gateway ) ) {
				$text = __( 'Stripe test mode is on for your account: pay with a Stripe test card, such as 4242 4242 4242 4242 with any future date and any CVC. Nothing is charged. Other customers and guests still pay live.', 'wp-easycart' );
			} elseif ( $flagged ) {
				$class = 'ec_cart_error';
				$text  = __( 'Your account is set to Stripe test mode, but this store has no Stripe test keys, so this payment is live and your card will be charged.', 'wp-easycart' );
			} else {
				return '';
			}
			return '<div class="' . esc_attr( $class ) . ' wpec-stripe-mode-notice" role="status"><div>' . esc_html( $text ) . '</div></div>';
		}

		/** Prints notice_html(). */
		public static function print_notice() {
			echo self::notice_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
		}

		/**
		 * What the customer editor's "Stripe test-mode customer" switch does on this store right now.
		 *
		 * @return string Plain text.
		 */
		public static function customer_hint() {
			$gateway = self::gateway();
			if ( '' === $gateway ) {
				return __( 'Used with Stripe only. Your store takes cards with another gateway, so this changes nothing.', 'wp-easycart' );
			}
			if ( self::TEST === self::store_mode( $gateway ) ) {
				return __( 'Stripe is in test mode for the whole store right now, so every customer pays with test cards. Once Stripe is live, this customer still checks out with your Stripe test keys.', 'wp-easycart' );
			}
			if ( ! self::ready( self::TEST, $gateway ) ) {
				return ( 'stripe' === $gateway )
					? __( 'No Stripe test keys are saved ( Settings › Payments › Stripe ), so this customer would still pay live.', 'wp-easycart' )
					: __( 'No Stripe test account is connected ( Settings › Payments › Stripe ), so this customer would still pay live.', 'wp-easycart' );
			}
			return __( 'While this customer is signed in, checkout uses your Stripe test keys and takes test cards only; everybody else pays live. Meant for staff test accounts: a real customer\'s saved cards and Stripe subscriptions stay in live mode.', 'wp-easycart' );
		}

		/** Diagnostics rows ( Store Status ): test-mode customers, and a chosen mode without its keys. */
		public static function store_status_rows() {
			$gateway = self::gateway();
			if ( '' === $gateway ) {
				return;
			}
			$url   = admin_url( 'admin.php?page=wp-easycart-settings&subpage=payment' );
			$rows  = array();
			$store = self::store_mode( $gateway );
			if ( 'stripe' === $gateway && self::TEST === self::chosen_mode( 'stripe' ) && self::LIVE === $store ) {
				$rows[] = array( 'warning', __( 'Stripe is set to test mode, but no Stripe test keys are saved, so your store takes live payments.', 'wp-easycart' ) );
			}
			$count = self::test_customers();
			if ( $count > 0 && self::LIVE === $store ) {
				if ( self::ready( self::TEST, $gateway ) ) {
					/* translators: %d: number of customer accounts. */
					$rows[] = array( 'success', sprintf( _n( 'Stripe is live. %d customer account is a Stripe test-mode customer and checks out with your Stripe test keys; everybody else pays live.', 'Stripe is live. %d customer accounts are Stripe test-mode customers and check out with your Stripe test keys; everybody else pays live.', $count, 'wp-easycart' ), $count ) );
				} else {
					/* translators: %d: number of customer accounts. */
					$rows[] = array( 'warning', sprintf( _n( '%d customer account is a Stripe test-mode customer, but no Stripe test keys are saved, so it pays live.', '%d customer accounts are Stripe test-mode customers, but no Stripe test keys are saved, so they pay live.', $count, 'wp-easycart' ), $count ) );
				}
			}
			foreach ( $rows as $row ) {
				$icon = ( 'success' === $row[0] ) ? 'dashicons-yes' : 'dashicons-warning';
				echo '<div class="ec_status_' . esc_attr( $row[0] ) . '"><div class="dashicons-before ' . esc_attr( $icon ) . '"></div><span class="ec_status_label">' . esc_html( $row[1] ) . ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Open Settings › Payments', 'wp-easycart' ) . '</a></span></div>';
			}
		}

		/**
		 * The Stripe webhook signing secret saved for a mode's endpoint ( '' = none ). Stripe sends test events only to endpoints added
		 * in test mode, each with its own secret. Before 6.0.4 there was one field, which holds whichever endpoint the store added.
		 *
		 * @param string $mode live | test.
		 * @return string
		 */
		public static function webhook_secret( $mode ) {
			return trim( (string) get_option( ( self::TEST === $mode ) ? 'ec_option_stripe_sandbox_webhook_secret' : 'ec_option_stripe_connect_webhook_secret' ) );
		}
	}

	wp_easycart_stripe_mode::init();

endif;
