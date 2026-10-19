<?php
/**
 * How often the storefront may say whether an email has an account ( 6.0.5, Matt: "Does this new format have proper measures in place to
 * prevent email testing? We don't want a bot entering 1000 emails to figure out who has an account." ).
 *
 * The checkout's and the subscription page's email step ( wp_easycart_subscription_signup::ajax_email() ) answer whether an email has an
 * account: it asks the password of one that has. So do the older checks that refuse an email already taken: the one-page checkout's
 * contact email save and Create Account, the classic checkout's and subscription page's Create Account, the registration page. Each asks
 * ask() ( the step, which can show a human check ) or allow() ( the rest ) before it looks:
 *
 *  - an email this browser looked up in the last hour is answered again for nothing ( a shopper who goes back and forth );
 *  - a new one counts against the browser ( 6 an hour ) and the network address ( 12 an hour, 40 a day ); over a limit the answer is
 *    "Too many tries", nothing about the email;
 *  - the human check set up on Settings › Checkout protection ( Turnstile or reCAPTCHA ) is asked before the browser's 3rd new email in an
 *    hour, the address's 4th, every new email while the store looks up more than 150 in an hour ( many addresses at once: a bot spread
 *    over a network ) and while Checkout protection's attack mode is on. A passed check counts for the browser for 30 minutes, and while
 *    it does only the browser's own limit applies ( shoppers on one mobile network can share an address: a bot then needs a new check for
 *    every 6 emails ); a provider that does not answer lets the shopper through ( the limits still apply ). Without a human check, an
 *    address may look up 3 an hour while the store is that busy. Never a store-wide stop: that would let a bot close the checkout to
 *    everyone.
 *
 * Refusals are written to Checkout protection's activity ( event email_stop, once per browser or address and reason in 10 minutes ). The
 * store's staff are never counted. Filter wp_easycart_email_lookup_limits.
 *
 * @package wp-easycart
 * @since   6.0.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_email_lookups' ) ) :

	/**
	 * Email lookup limits and the human check.
	 */
	class wp_easycart_email_lookups {

		/**
		 * The window the browser's and the address's hourly counts and the store's count run over.
		 */
		const HOUR = 3600;

		/**
		 * How long a passed human check counts for the browser.
		 */
		const PASS_SECONDS = 1800;

		/**
		 * How often one refusal is written to Checkout protection's activity per browser or address and reason.
		 */
		const LOG_SECONDS = 600;

		/**
		 * The named lock the counts are kept under.
		 */
		const LOCK = 'wpec_email_lookups';

		/**
		 * Hooks: the cart page's "Too many tries" for a classic checkout redirect that carries email_busy.
		 */
		public static function init() {
			add_filter( 'wpeasycart_cart_errors', array( __CLASS__, 'error_notes' ) );
			add_filter( 'wpeasycart_valid_cart_errors', array( __CLASS__, 'valid_errors' ) );
		}

		/**
		 * Filter wpeasycart_cart_errors.
		 *
		 * @param array $notes code => text.
		 * @return array
		 */
		public static function error_notes( $notes ) {
			$notes['email_busy'] = self::busy_text();
			return $notes;
		}

		/**
		 * Filter wpeasycart_valid_cart_errors.
		 *
		 * @param string[] $codes Codes.
		 * @return string[]
		 */
		public static function valid_errors( $codes ) {
			$codes[] = 'email_busy';
			return $codes;
		}

		/**
		 * "Too many tries" ( the subscription page's phrase ), nothing about the email.
		 *
		 * @return string
		 */
		public static function busy_text() {
			$text = class_exists( 'wp_easycart_subscription_signup' ) ? wp_easycart_subscription_signup::text( 'subscription_email_busy', '' ) : '';
			$text = trim( wp_strip_all_tags( (string) $text ) );
			return '' !== $text ? $text : 'Too many tries. Please wait a few minutes and try again.';
		}

		/**
		 * What the email step says above the human check.
		 *
		 * @param bool $failed The check posted did not pass.
		 * @return string
		 */
		public static function check_text( $failed = false ) {
			if ( $failed && class_exists( 'wp_easycart_checkout_guard' ) ) {
				return wp_strip_all_tags( (string) wp_easycart_checkout_guard::text( 'check_failed' ) );
			}
			$text = class_exists( 'wp_easycart_subscription_signup' ) ? wp_easycart_subscription_signup::text( 'checkout_email_check', '' ) : '';
			$text = trim( wp_strip_all_tags( (string) $text ) );
			return '' !== $text ? $text : 'To keep accounts safe, please complete this quick check to continue.';
		}

		/**
		 * The limits.
		 *
		 * @return array session_hour, ip_hour, ip_day ( new emails before "Too many tries" ), check_session, check_ip ( new emails
		 *               before the human check is asked ), surge_hour ( the store's new emails in an hour before every one asks the
		 *               check ), surge_ip_hour ( the address's hourly limit then, without a human check ).
		 */
		public static function limits() {
			/**
			 * How many emails the storefront may say have an account or not.
			 *
			 * @since 6.0.5
			 * @param array $limits session_hour, ip_hour, ip_day, check_session, check_ip, surge_hour, surge_ip_hour.
			 */
			$limits = apply_filters(
				'wp_easycart_email_lookup_limits',
				array(
					'session_hour'  => 6,
					'ip_hour'       => 12,
					'ip_day'        => 40,
					'check_session' => 2,
					'check_ip'      => 3,
					'surge_hour'    => 150,
					'surge_ip_hour' => 3,
				)
			);
			return array_map( 'intval', is_array( $limits ) ? $limits : array() );
		}

		/**
		 * The keys this request is counted under: the browser ( the checkout session, else the address ) and the address.
		 *
		 * @param string $email The email looked up.
		 * @return array session, ip, ip_raw, email ( a keyed hash of the email ), context ( Checkout protection's, when loaded ).
		 */
		public static function keys( $email ) {
			$email = strtolower( trim( (string) $email ) );
			if ( class_exists( 'wp_easycart_checkout_guard' ) ) {
				$context = wp_easycart_checkout_guard::context( array( 'email' => $email ) );
				$session = (string) $context['session'];
				$ip      = (string) $context['ip'];
				$ip_raw  = (string) $context['ip_raw'];
			} else {
				$context    = null;
				$session_id = ( isset( $GLOBALS['ec_cart_data'] ) && is_object( $GLOBALS['ec_cart_data'] ) && isset( $GLOBALS['ec_cart_data']->ec_cart_id ) ) ? (string) $GLOBALS['ec_cart_data']->ec_cart_id : '';
				$ip_raw     = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
				$session    = ( '' === $session_id || 'not-set' === $session_id ) ? '' : wp_hash( 'wpec-el-s|' . $session_id );
				$ip         = wp_hash( 'wpec-el-i|' . $ip_raw );
			}
			return array(
				'session' => ( '' !== $session ) ? $session : 'ip|' . $ip, /* no checkout session ( a bot without cookies ): the address is its browser */
				'ip'      => $ip,
				'ip_raw'  => $ip_raw,
				'email'   => wp_hash( 'wpec-el-e|' . $email ),
				'context' => $context,
			);
		}

		/**
		 * Whether the storefront may answer for this email in a place that cannot show a human check ( Create Account, the classic
		 * checkout, the registration page ): an email already looked up by this browser, or a new one within the limits and with no
		 * check owed. Counts it.
		 *
		 * @param string $email Email.
		 * @return bool
		 */
		public static function allow( $email ) {
			$answer = self::ask( $email, array( 'check' => false ) );
			return ! empty( $answer['ok'] );
		}

		/**
		 * May the storefront answer whether this email has an account? Counts the lookup when it may.
		 *
		 * @param string $email Email.
		 * @param array  $args  token ( a human check's answer ), check ( false: the place cannot show one, so a check owed refuses ),
		 *                      place ( for the activity log: email_step | known | create_account | register … ).
		 * @return array ok, reason ( staff | repeat | counted | check | busy ), check ( provider, site_key: the check to show ),
		 *               failed ( the posted check did not pass ).
		 */
		public static function ask( $email, $args = array() ) {
			$args = array_merge(
				array(
					'token' => '',
					'check' => true,
					'place' => 'email',
				),
				(array) $args
			);
			if ( self::is_staff() ) {
				return self::answer( true, 'staff' );
			}
			$keys = self::keys( $email );
			if ( self::seen( $keys ) ) {
				return self::answer( true, 'repeat' );
			}

			/* A posted human check first ( a network call, never under the lock ). */
			$failed = false;
			if ( '' !== (string) $args['token'] && self::check_available() && ! self::passed( $keys ) ) {
				$result = wp_easycart_checkout_guard::verify_token( (string) $args['token'] );
				if ( 'fail' === $result ) {
					$failed = true;
					self::log( 'check_fail', 'email', $keys, $args['place'], false );
				} else {
					/* pass, or the provider did not answer ( the limits still apply ) */
					set_transient( 'wpec_el_pass_' . md5( $keys['session'] ), time() + self::PASS_SECONDS, self::PASS_SECONDS );
					self::log( 'check_pass', 'email', $keys, $args['place'], false );
				}
			}

			global $wpdb;
			$locked = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK( %s, 3 )', self::LOCK ) );
			if ( '0' === (string) $locked ) {
				return self::answer( false, 'busy' ); /* another lookup held the counts for 3 seconds: a flood */
			}
			$limits  = self::limits();
			$now     = time();
			$counts  = array(
				'session' => self::count( 'wpec_el_s_' . md5( $keys['session'] ), self::HOUR, $now ),
				'ip_hour' => self::count( 'wpec_el_ih_' . md5( $keys['ip'] ), self::HOUR, $now ),
				'ip_day'  => self::count( 'wpec_el_id_' . md5( $keys['ip'] ), DAY_IN_SECONDS, $now ),
				'store'   => self::count( 'wpec_el_store', self::HOUR, $now ),
			);
			$surge   = ( $limits['surge_hour'] > 0 && $counts['store'] >= $limits['surge_hour'] ) || self::attack();
			$checks  = self::check_available();
			$ip_hour = ( $surge && ! $checks && $limits['surge_ip_hour'] > 0 ) ? min( $limits['ip_hour'], $limits['surge_ip_hour'] ) : $limits['ip_hour'];

			/* A browser that passed the human check is held to its own limit only ( an address can be a whole mobile network ). */
			$checked = $checks && self::passed( $keys );
			$over    = '';
			if ( $limits['session_hour'] > 0 && $counts['session'] >= $limits['session_hour'] ) {
				$over = 'session';
			} elseif ( ! $checked && $ip_hour > 0 && $counts['ip_hour'] >= $ip_hour ) {
				$over = $surge ? 'surge' : 'ip';
			} elseif ( ! $checked && $limits['ip_day'] > 0 && $counts['ip_day'] >= $limits['ip_day'] ) {
				$over = 'day';
			}
			if ( '' !== $over ) {
				self::unlock( $locked );
				self::log( 'email_stop', $over, $keys, $args['place'] );
				return self::answer( false, 'busy' );
			}

			$owed = $checks && ! self::passed( $keys ) && (
				$surge
				|| ( $limits['check_session'] >= 0 && $counts['session'] >= $limits['check_session'] )
				|| ( $limits['check_ip'] >= 0 && $counts['ip_hour'] >= $limits['check_ip'] )
			);
			if ( $owed ) {
				self::unlock( $locked );
				if ( ! $args['check'] ) {
					self::log( 'email_stop', 'check', $keys, $args['place'] );
					return self::answer( false, 'busy' );
				}
				$settings = wp_easycart_checkout_guard::settings();
				return array(
					'ok'     => false,
					'reason' => 'check',
					'failed' => $failed,
					'check'  => array(
						'provider' => ( 'recaptcha' === $settings['provider'] ) ? 'recaptcha' : 'turnstile',
						'site_key' => wp_easycart_checkout_guard::site_key(),
					),
				);
			}

			self::bump( 'wpec_el_s_' . md5( $keys['session'] ), self::HOUR, $now );
			self::bump( 'wpec_el_ih_' . md5( $keys['ip'] ), self::HOUR, $now );
			self::bump( 'wpec_el_id_' . md5( $keys['ip'] ), DAY_IN_SECONDS, $now );
			self::bump( 'wpec_el_store', self::HOUR, $now );
			self::remember( $keys, $now );
			self::unlock( $locked );
			return self::answer( true, 'counted' );
		}

		/**
		 * An answer.
		 *
		 * @param bool   $ok     May answer.
		 * @param string $reason Why.
		 * @return array
		 */
		private static function answer( $ok, $reason ) {
			return array(
				'ok'     => (bool) $ok,
				'reason' => (string) $reason,
				'failed' => false,
				'check'  => null,
			);
		}

		/**
		 * The store's staff ( never counted ).
		 *
		 * @return bool
		 */
		private static function is_staff() {
			if ( class_exists( 'wp_easycart_checkout_guard' ) ) {
				return wp_easycart_checkout_guard::is_staff();
			}
			return function_exists( 'current_user_can' ) && current_user_can( 'manage_options' );
		}

		/**
		 * Whether a human check can be shown: Checkout protection on, a check set up with its keys and its provider answering.
		 *
		 * @return bool
		 */
		public static function check_available() {
			return class_exists( 'wp_easycart_checkout_guard' ) && wp_easycart_checkout_guard::is_on() && wp_easycart_checkout_guard::check_available();
		}

		/**
		 * Checkout protection's attack mode.
		 *
		 * @return bool
		 */
		private static function attack() {
			return class_exists( 'wp_easycart_checkout_guard' ) && wp_easycart_checkout_guard::is_on() && wp_easycart_checkout_guard::attack_active();
		}

		/**
		 * Whether the browser passed a human check in the last 30 minutes.
		 *
		 * @param array $keys keys().
		 * @return bool
		 */
		private static function passed( $keys ) {
			return (int) get_transient( 'wpec_el_pass_' . md5( $keys['session'] ) ) > time();
		}

		/**
		 * The emails the browser looked up in the last hour ( keyed hashes => when ).
		 *
		 * @param array $keys keys().
		 * @return array
		 */
		private static function seen_list( $keys ) {
			$list = get_transient( 'wpec_el_seen_' . md5( $keys['session'] ) );
			return is_array( $list ) ? $list : array();
		}

		/**
		 * Whether the browser looked this email up in the last hour.
		 *
		 * @param array $keys keys().
		 * @return bool
		 */
		private static function seen( $keys ) {
			$list = self::seen_list( $keys );
			return isset( $list[ $keys['email'] ] ) && (int) $list[ $keys['email'] ] > time() - self::HOUR;
		}

		/**
		 * Remember the email for the browser.
		 *
		 * @param array $keys keys().
		 * @param int   $now  Now.
		 */
		private static function remember( $keys, $now ) {
			$list = array();
			foreach ( self::seen_list( $keys ) as $hash => $at ) {
				if ( (int) $at > $now - self::HOUR ) {
					$list[ $hash ] = (int) $at;
				}
			}
			$list[ $keys['email'] ] = $now;
			set_transient( 'wpec_el_seen_' . md5( $keys['session'] ), array_slice( $list, -50, 50, true ), self::HOUR );
		}

		/**
		 * A window's count ( 0 once the window is over ).
		 *
		 * @param string $key    Transient.
		 * @param int    $window Seconds.
		 * @param int    $now    Now.
		 * @return int
		 */
		private static function count( $key, $window, $now ) {
			$hit = get_transient( $key );
			if ( ! is_array( $hit ) || ! isset( $hit['start'], $hit['count'] ) || $now - (int) $hit['start'] >= (int) $window ) {
				return 0;
			}
			return (int) $hit['count'];
		}

		/**
		 * Count one in a window.
		 *
		 * @param string $key    Transient.
		 * @param int    $window Seconds.
		 * @param int    $now    Now.
		 */
		private static function bump( $key, $window, $now ) {
			$hit = get_transient( $key );
			if ( ! is_array( $hit ) || ! isset( $hit['start'], $hit['count'] ) || $now - (int) $hit['start'] >= (int) $window ) {
				$hit = array(
					'start' => $now,
					'count' => 0,
				);
			}
			$hit['count'] = (int) $hit['count'] + 1;
			set_transient( $key, $hit, max( 1, (int) $window - ( $now - (int) $hit['start'] ) ) );
		}

		/**
		 * Let the counts go.
		 *
		 * @param mixed $locked GET_LOCK's answer.
		 */
		private static function unlock( $locked ) {
			if ( '1' === (string) $locked ) {
				global $wpdb;
				$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', self::LOCK ) );
			}
		}

		/**
		 * Write to Checkout protection's activity ( email_stop once per browser or address and reason in 10 minutes ).
		 *
		 * @param string $type     email_stop | check_pass | check_fail.
		 * @param string $reason   session | ip | day | surge | check, or email.
		 * @param array  $keys     keys().
		 * @param string $place    Where.
		 * @param bool   $throttle Once in 10 minutes.
		 */
		private static function log( $type, $reason, $keys, $place, $throttle = true ) {
			if ( ! class_exists( 'wp_easycart_checkout_guard' ) || ! wp_easycart_checkout_guard::is_on() || ! is_array( $keys['context'] ) ) {
				return;
			}
			if ( $throttle ) {
				$flag = 'wpec_el_log_' . md5( $type . '|' . $reason . '|' . ( 'ip' === $reason || 'day' === $reason || 'surge' === $reason ? $keys['ip'] : $keys['session'] ) );
				if ( get_transient( $flag ) ) {
					return;
				}
				set_transient( $flag, 1, self::LOG_SECONDS );
			}
			wp_easycart_checkout_guard::record( $type, $reason, array( 'place' => substr( (string) $place, 0, 20 ) ), $keys['context'] );
		}
	}

	wp_easycart_email_lookups::init();

endif;
