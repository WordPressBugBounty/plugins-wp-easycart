<?php
/**
 * WP EasyCart — Checkout protection ( card-testing defence ).
 *
 * Card testing is bots running stolen card numbers through a store's checkout to learn which ones work. Every attempt
 * costs the store a gateway fee, approved tests turn into disputes, and processors freeze payouts over it. This class is
 * the one gatekeeper every payment request goes through:
 *
 *   check( $place )  before the gateway is called: block list, pauses, human check. true or a WP_Error to show.
 *   record_*()       after the gateway answered: declines count against the shopper's device ( EasyCart session ),
 *                    network address, email and card, and against the store-wide decline rate.
 *
 * Levels ( Settings › Checkout protection ): off, watch ( log what would be stopped, stop nothing ), relaxed, standard
 * ( default ), strict, custom. Only failures count, so browsing and normal buying never get near a limit. When declines
 * across the store spike against the store's own 30-day rate, attack mode turns on by itself: every shopper gets the
 * human check ( Cloudflare Turnstile or reCAPTCHA v2 ), tighter limits apply, the owner is emailed, and it ends by
 * itself once things are quiet, with an all-clear email.
 *
 * Stripe's Payment Element confirms cards in the browser, so its declines reach the store only through the
 * payment_intent.payment_failed webhook ( re-fetched from Stripe before it is trusted ) and a browser report that asks
 * the server to look the session's intent up. A paused session is handed no client secret, and its open intent is
 * cancelled so a secret already on the page stops working.
 *
 * Privacy: IP addresses, emails and cards are stored only as keyed hashes ( HMAC with a per-site secret ) plus a
 * shortened form for display; events are deleted after the retention period ( 30 days by default ). Trust comes only
 * from a signed-in customer with a paid order, never from a typed email.
 *
 * Fail safe: if the events table is missing, or a check provider cannot be reached, payments carry on ( under stricter
 * limits where that applies ) and the settings page says so. Nothing here may break a checkout by accident.
 *
 * @package wp-easycart
 * @since   6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wp_easycart_checkout_protection_guard' ) ) {
	/**
	 * Nonce check for the storefront checkout protection calls. A plain function, so WPCS credits it ( see
	 * .phpcs.xml.dist ). The nonce is not tied to the session, so checkout pages served from a cache keep working.
	 *
	 * @since 6.0.2
	 * @return bool
	 */
	function wp_easycart_checkout_protection_guard() {
		return isset( $_POST['nonce'] ) && false !== wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wp-easycart-checkout-protection' );
	}
}

if ( ! class_exists( 'wp_easycart_checkout_guard' ) ) :

	/**
	 * Checkout protection engine ( static ).
	 *
	 * @since 6.0.2
	 */
	final class wp_easycart_checkout_guard {

		/** Events table. */
		const TABLE = 'ec_checkout_event';

		/** Option prefix for every setting. */
		const OPT = 'ec_option_checkout_protection_';

		/** Attack state option ( array ). */
		const ATTACK_OPTION = 'ec_option_checkout_protection_attack';

		/** Per-site hashing secret. */
		const SECRET_OPTION = 'wp_easycart_checkout_protection_secret';

		/** Hourly housekeeping event. */
		const CRON = 'wp_easycart_checkout_protection_hourly';

		/** Nonce for the storefront calls ( not tied to the session, so cached pages keep working ). */
		const NONCE = 'wp-easycart-checkout-protection';

		/** How long a passed human check counts for the session. */
		const PASS_SECONDS = 1800;

		/** How long a checkout page visit counts as "came from the checkout". */
		const SEEN_SECONDS = 7200;

		/** Window used to spot a store-wide spike. */
		const SPIKE_SECONDS = 900;

		/** Declines inside this window make the smart human check show for that shopper. */
		const RECENT_DECLINE_SECONDS = 3600;

		/** Code guessing: wrong codes allowed per shopper, window and pause. */
		const CODE_LIMIT  = 10;
		const CODE_WINDOW = 600;
		const CODE_PAUSE  = 3600;

		/** Order log key on a payment that went through during an attack ( the orders list filters on it ). */
		const ORDER_LOG_KEY = 'possible-card-test';

		/** Set once an order has been tagged, so the orders list only offers the filter to stores that need it. */
		const FLAGGED_OPTION = 'wp_easycart_checkout_protection_flagged';

		/**
		 * Resolved settings for this request.
		 *
		 * @var array|null
		 */
		private static $settings = null;

		/**
		 * Table-exists cache for this request.
		 *
		 * @var bool|null
		 */
		private static $ready = null;

		/**
		 * Shopper context for this request.
		 *
		 * @var array|null
		 */
		private static $context = null;

		/**
		 * Header value to send with the AJAX answer.
		 *
		 * @var string
		 */
		private static $header_state = '';

		/**
		 * The visitor's address for this request ( client_ip() works it out once ).
		 *
		 * @var string|null
		 */
		private static $client_ip = null;

		// ------------------------------------------------------------------
		// Boot.
		// ------------------------------------------------------------------

		/**
		 * Register hooks.
		 */
		public static function init() {
			add_action( 'init', array( __CLASS__, 'schedule' ) );
			add_action( self::CRON, array( __CLASS__, 'tick' ) );

			add_action( 'wpeasycart_payment_failed', array( __CLASS__, 'on_payment_failed' ), 10, 4 );
			add_action( 'wpeasycart_order_paid', array( __CLASS__, 'on_order_paid' ), 20, 1 );
			add_action( 'wpeasycart_stripe_webhook', array( __CLASS__, 'on_stripe_webhook' ), 5, 3 );

			add_filter( 'wpeasycart_checkout_order_errors', array( __CLASS__, 'filter_order_errors' ), 20, 2 );
			add_filter( 'wpeasycart_cart_errors', array( __CLASS__, 'filter_cart_errors' ) );
			add_filter( 'wpeasycart_payment_error_message', array( __CLASS__, 'filter_payment_error' ) );
			add_filter( 'wpeasycart_cart_card_error', array( __CLASS__, 'filter_card_error' ) );
			add_filter( 'wpeasycart_abandoned_cart_capture_skip', array( __CLASS__, 'skip_abandoned' ), 10, 2 );

			add_action( 'wp_easycart_cart_payment_payment_methods_end', array( __CLASS__, 'mark_seen' ) );
			add_action( 'wpeasycart_order_pay_before_button', array( __CLASS__, 'mark_seen' ) );

			foreach ( array( 'state', 'verify', 'report' ) as $action ) {
				add_action( 'wp_ajax_ec_ajax_checkout_protection_' . $action, array( __CLASS__, 'ajax_' . $action ) );
				add_action( 'wp_ajax_nopriv_ec_ajax_checkout_protection_' . $action, array( __CLASS__, 'ajax_' . $action ) );
			}
		}

		/**
		 * Keep the hourly housekeeping event scheduled.
		 */
		public static function schedule() {
			if ( ! wp_next_scheduled( self::CRON ) ) {
				wp_schedule_event( time() + 300, 'hourly', self::CRON );
			}
		}

		// ------------------------------------------------------------------
		// Settings.
		// ------------------------------------------------------------------

		/**
		 * One stored setting, or its default when never saved.
		 *
		 * @param string $key      Name without the prefix.
		 * @param mixed  $fallback Shipped default.
		 * @return mixed
		 */
		public static function opt( $key, $fallback ) {
			$value = get_option( self::OPT . $key, null );
			if ( null === $value || false === $value || '' === $value ) {
				return $fallback;
			}
			return $value;
		}

		/**
		 * Resolved settings ( filterable ).
		 *
		 * @return array
		 */
		public static function settings() {
			if ( null !== self::$settings ) {
				return self::$settings;
			}
			$level = (string) self::opt( 'level', 'standard' );
			if ( ! in_array( $level, array( 'off', 'watch', 'relaxed', 'standard', 'strict', 'custom' ), true ) ) {
				$level = 'standard';
			}
			$human = (string) self::opt( 'human', 'smart' );
			if ( ! in_array( $human, array( 'never', 'smart', 'always' ), true ) ) {
				$human = 'smart';
			}
			$provider = (string) self::opt( 'provider', 'turnstile' );
			if ( ! in_array( $provider, array( 'turnstile', 'recaptcha' ), true ) ) {
				$provider = 'turnstile';
			}
			$proxy = (string) self::opt( 'proxy', 'auto' );
			if ( ! in_array( $proxy, array( 'auto', 'direct', 'proxy' ), true ) ) {
				$proxy = 'auto';
			}
			$settings = array(
				'level'           => $level,
				'human'           => $human,
				'provider'        => $provider,
				'attack_limit'    => max( 1, min( 20, (int) self::opt( 'attack_limit', 5 ) ) ),
				'attack_minutes'  => max( 15, min( 1440, (int) self::opt( 'attack_minutes', 60 ) ) ),
				'alerts'          => '1' === (string) self::opt( 'alerts', '1' ),
				'alert_emails'    => (string) self::opt( 'alert_emails', '' ),
				'flag_orders'     => '1' === (string) self::opt( 'flag_orders', '1' ),
				'simple_declines' => 'bank' !== (string) self::opt( 'declines', 'simple' ),
				'code_limits'     => '1' === (string) self::opt( 'code_limits', '1' ),
				'trust_customers' => '1' === (string) self::opt( 'trust_customers', '1' ),
				'allow_list'      => (string) self::opt( 'allow_list', '' ),
				'block_list'      => (string) self::opt( 'block_list', '' ),
				'proxy'           => $proxy,
				'proxy_ips'       => (string) self::opt( 'proxy_ips', '' ),
				'require_page'    => '1' === (string) self::opt( 'require_page', '1' ),
				'retention'       => in_array( (int) self::opt( 'retention', 30 ), array( 7, 30, 90 ), true ) ? (int) self::opt( 'retention', 30 ) : 30,
			);
			/**
			 * Checkout protection settings.
			 *
			 * @since 6.0.2
			 * @param array $settings Resolved settings.
			 */
			$settings       = apply_filters( 'wpeasycart_checkout_protection_settings', $settings );
			self::$settings = is_array( $settings ) ? $settings : array();
			return self::$settings;
		}

		/**
		 * Forget the cached settings ( after a save ).
		 */
		public static function flush_settings() {
			self::$settings = null;
		}

		/**
		 * The limits for a level.
		 *
		 * @param string $level Level ( defaults to the stored one ).
		 * @return array fails, window ( seconds ), daily, pause ( seconds ), card, attack_count, attack_ratio, strict_page
		 */
		public static function limits( $level = '' ) {
			$level   = '' === $level ? self::settings()['level'] : $level;
			$presets = array(
				'relaxed'  => array( 8, 10, 20, 30, 8, 12, 4, false ),
				'standard' => array( 5, 10, 10, 60, 5, 8, 3, false ),
				'strict'   => array( 3, 10, 6, 1440, 3, 5, 2, true ),
			);
			if ( 'custom' === $level ) {
				$fails  = max( 1, min( 50, (int) self::opt( 'custom_fails', 5 ) ) );
				$preset = array(
					$fails,
					max( 1, min( 1440, (int) self::opt( 'custom_window', 10 ) ) ),
					$fails * 2,
					max( 5, min( 10080, (int) self::opt( 'custom_pause', 60 ) ) ),
					max( 1, min( 50, (int) self::opt( 'custom_card', 5 ) ) ),
					max( 2, min( 500, (int) self::opt( 'custom_attack_count', 8 ) ) ),
					max( 1, min( 20, (int) self::opt( 'custom_attack_ratio', 3 ) ) ),
					false,
				);
			} else {
				$preset = isset( $presets[ $level ] ) ? $presets[ $level ] : $presets['standard'];
			}
			$limits = array(
				'fails'        => (int) $preset[0],
				'window'       => (int) $preset[1] * MINUTE_IN_SECONDS,
				'daily'        => (int) $preset[2],
				'pause'        => (int) $preset[3] * MINUTE_IN_SECONDS,
				'card'         => (int) $preset[4],
				'attack_count' => (int) $preset[5],
				'attack_ratio' => (float) $preset[6],
				'strict_page'  => (bool) $preset[7],
			);
			/**
			 * The limits in force for a level.
			 *
			 * @since 6.0.2
			 * @param array  $limits Limits.
			 * @param string $level  Level.
			 */
			return apply_filters( 'wpeasycart_checkout_protection_limits', $limits, $level );
		}

		/**
		 * Whether protection runs at all ( watch counts: it records ).
		 *
		 * @return bool
		 */
		public static function is_on() {
			return 'off' !== self::settings()['level'] && self::ready();
		}

		/**
		 * Whether protection only watches.
		 *
		 * @return bool
		 */
		public static function is_watch() {
			return 'watch' === self::settings()['level'];
		}

		/**
		 * Whether the events table exists ( the DB upgrade has run ).
		 *
		 * @return bool
		 */
		public static function ready() {
			if ( null !== self::$ready ) {
				return self::$ready;
			}
			$cached = get_transient( 'wpec_cp_ready' );
			if ( 'yes' === $cached ) {
				self::$ready = true;
				return true;
			}
			global $wpdb;
			if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
				self::$ready = false;
				return false;
			}
			self::$ready = ( self::TABLE === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', self::TABLE ) ) );
			if ( self::$ready ) {
				set_transient( 'wpec_cp_ready', 'yes', DAY_IN_SECONDS );
			}
			return self::$ready;
		}

		// ------------------------------------------------------------------
		// Who is paying: keys and display forms.
		// ------------------------------------------------------------------

		/**
		 * Per-site hashing secret ( created on first use ).
		 *
		 * @return string
		 */
		private static function secret() {
			$secret = (string) get_option( self::SECRET_OPTION, '' );
			if ( strlen( $secret ) < 32 ) {
				$secret = wp_generate_password( 64, true, true );
				update_option( self::SECRET_OPTION, $secret, false );
			}
			return $secret;
		}

		/**
		 * Keyed hash used to count a value without storing it.
		 *
		 * @param string $type  s ( session ), i ( ip ), e ( email ), c ( card ).
		 * @param string $value Raw value.
		 * @return string 24 hex characters, or '' for an empty value.
		 */
		public static function key( $type, $value ) {
			$value = strtolower( trim( (string) $value ) );
			if ( '' === $value ) {
				return '';
			}
			return substr( hash_hmac( 'sha256', $type . '|' . $value, self::secret() ), 0, 24 );
		}

		/**
		 * One $_SERVER value, sanitised.
		 *
		 * @param string $name Key.
		 * @return string
		 */
		private static function server_value( $name ) {
			if ( ! isset( $_SERVER[ $name ] ) ) {
				return '';
			}
			return trim( sanitize_text_field( wp_unslash( $_SERVER[ $name ] ) ) );
		}

		/**
		 * Whether a value is a valid IP outside the private and reserved ranges.
		 *
		 * @param string $ip Candidate.
		 * @return bool
		 */
		public static function is_public_ip( $ip ) {
			return is_string( $ip ) && '' !== $ip && false !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
		}

		/**
		 * Cloudflare's published edge ranges ( https://www.cloudflare.com/ips/ ). CF-Connecting-IP is only believed
		 * from these, so a visitor can't fake their address by sending the header.
		 *
		 * @return string[]
		 */
		public static function cloudflare_ranges() {
			$ranges = array(
				'173.245.48.0/20',
				'103.21.244.0/22',
				'103.22.200.0/22',
				'103.31.4.0/22',
				'141.101.64.0/18',
				'108.162.192.0/18',
				'190.93.240.0/20',
				'188.114.96.0/20',
				'197.234.240.0/22',
				'198.41.128.0/17',
				'162.158.0.0/15',
				'104.16.0.0/13',
				'104.24.0.0/14',
				'172.64.0.0/13',
				'131.0.72.0/22',
				'2400:cb00::/32',
				'2606:4700::/32',
				'2803:f800::/32',
				'2405:b500::/32',
				'2405:8100::/32',
				'2a06:98c0::/29',
				'2c0f:f248::/32',
			);
			/**
			 * Address ranges trusted to send CF-Connecting-IP.
			 *
			 * @since 6.0.2
			 * @param string[] $ranges CIDR ranges.
			 */
			return (array) apply_filters( 'wpeasycart_checkout_protection_cloudflare_ranges', $ranges );
		}

		/**
		 * Whether an address is inside a CIDR range ( IPv4 or IPv6 ) or equal to a plain address.
		 *
		 * @param string $ip    Address.
		 * @param string $range CIDR or single address.
		 * @return bool
		 */
		public static function ip_in_range( $ip, $range ) {
			$range = trim( (string) $range );
			if ( '' === $range || '' === (string) $ip ) {
				return false;
			}
			if ( false === strpos( $range, '/' ) ) {
				$a = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- an invalid address is simply not a match.
				$b = @inet_pton( $range ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- as above.
				return false !== $a && false !== $b && $a === $b;
			}
			list( $subnet, $bits ) = explode( '/', $range, 2 );
			$ip_bin                = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- as above.
			$subnet_bin            = @inet_pton( $subnet ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- as above.
			if ( false === $ip_bin || false === $subnet_bin || strlen( $ip_bin ) !== strlen( $subnet_bin ) ) {
				return false;
			}
			$bits  = (int) $bits;
			$bytes = (int) floor( $bits / 8 );
			if ( $bytes > 0 && substr( $ip_bin, 0, $bytes ) !== substr( $subnet_bin, 0, $bytes ) ) {
				return false;
			}
			$rest = $bits % 8;
			if ( 0 === $rest ) {
				return true;
			}
			$mask = chr( ( 0xff << ( 8 - $rest ) ) & 0xff );
			return ( $ip_bin[ $bytes ] & $mask ) === ( $subnet_bin[ $bytes ] & $mask );
		}

		/**
		 * Whether an address matches any entry of a list ( one per line, commas also accepted ).
		 *
		 * @param string       $ip      Address.
		 * @param string|array $entries List.
		 * @return bool
		 */
		private static function ip_in_list( $ip, $entries ) {
			foreach ( self::lines( $entries ) as $entry ) {
				$entry = trim( preg_replace( '/\s*[#·].*$/u', '', $entry ) );
				if ( '' !== $entry && self::ip_in_range( $ip, $entry ) ) {
					return true;
				}
			}
			return false;
		}

		/**
		 * Split a stored list into trimmed, non-empty lines.
		 *
		 * @param string|array $entries Stored list.
		 * @return string[]
		 */
		public static function lines( $entries ) {
			if ( is_array( $entries ) ) {
				$entries = implode( "\n", $entries );
			}
			$entries = str_replace( array( "\r\n", "\r", ',' ), "\n", (string) $entries );
			$out     = array();
			foreach ( explode( "\n", $entries ) as $line ) {
				$line = trim( $line );
				if ( '' !== $line ) {
					$out[] = $line;
				}
			}
			return $out;
		}

		/**
		 * The visitor's address, trusting proxy headers only where they can't be faked.
		 *
		 * Automatic: CF-Connecting-IP when the request comes from Cloudflare's own ranges; otherwise, when the direct
		 * peer is a private address ( a proxy on the same network ), the right-most public hop of X-Forwarded-For.
		 * Another proxy: X-Forwarded-For only from the addresses the owner listed. Direct: REMOTE_ADDR only.
		 *
		 * @return string '' when there is no usable address.
		 */
		public static function client_ip() {
			if ( null !== self::$client_ip ) {
				return self::$client_ip;
			}
			$settings = self::settings();
			$remote   = self::server_value( 'REMOTE_ADDR' );
			$ip       = $remote;
			if ( 'direct' !== $settings['proxy'] ) {
				$cloudflare = self::server_value( 'HTTP_CF_CONNECTING_IP' );
				$from_cf    = false;
				if ( '' !== $cloudflare && false !== filter_var( $cloudflare, FILTER_VALIDATE_IP ) ) {
					foreach ( self::cloudflare_ranges() as $range ) {
						if ( self::ip_in_range( $remote, $range ) ) {
							$from_cf = true;
							break;
						}
					}
				}
				$trusted_proxy = ( 'proxy' === $settings['proxy'] && self::ip_in_list( $remote, $settings['proxy_ips'] ) );
				if ( $from_cf ) {
					$ip = $cloudflare;
				} elseif ( $trusted_proxy || ( 'auto' === $settings['proxy'] && ! self::is_public_ip( $remote ) ) ) {
					$hops = array_reverse( array_map( 'trim', explode( ',', self::server_value( 'HTTP_X_FORWARDED_FOR' ) ) ) );
					foreach ( $hops as $hop ) {
						if ( self::is_public_ip( $hop ) && ! ( $trusted_proxy && self::ip_in_list( $hop, $settings['proxy_ips'] ) ) ) {
							$ip = $hop;
							break;
						}
					}
				}
			}
			/**
			 * The visitor address checkout protection keys limits on.
			 *
			 * @since 6.0.2
			 * @param string $ip     Chosen address.
			 * @param string $remote REMOTE_ADDR.
			 */
			$ip = apply_filters( 'wpeasycart_checkout_protection_client_ip', $ip, $remote );

			self::$client_ip = ( is_string( $ip ) && false !== filter_var( trim( $ip ), FILTER_VALIDATE_IP ) ) ? trim( $ip ) : '';
			return self::$client_ip;
		}

		/**
		 * How the address is used as a key: IPv4 whole, IPv6 by its /64 ( one home or phone ).
		 *
		 * @param string $ip Address.
		 * @return string
		 */
		private static function ip_material( $ip ) {
			if ( '' === $ip || false === strpos( $ip, ':' ) ) {
				return $ip;
			}
			$bin = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- validated before; a failure falls back to the raw value.
			if ( false === $bin ) {
				return $ip;
			}
			return inet_ntop( substr( $bin, 0, 8 ) . str_repeat( "\0", 8 ) ) . '/64';
		}

		/**
		 * Shortened address for display: 203.0.113.x, or the IPv6 /64.
		 *
		 * @param string $ip Address.
		 * @return string
		 */
		public static function mask_ip( $ip ) {
			if ( '' === (string) $ip ) {
				return '';
			}
			if ( false !== strpos( $ip, ':' ) ) {
				return self::ip_material( $ip );
			}
			$parts = explode( '.', $ip );
			if ( 4 !== count( $parts ) ) {
				return '';
			}
			$parts[3] = 'x';
			return implode( '.', $parts );
		}

		/**
		 * Shortened email for display: j•••@gmail.com.
		 *
		 * @param string $email Email.
		 * @return string
		 */
		public static function mask_email( $email ) {
			$email = strtolower( trim( (string) $email ) );
			if ( '' === $email || false === strpos( $email, '@' ) ) {
				return '';
			}
			list( $local, $domain ) = explode( '@', $email, 2 );
			return substr( $local, 0, 1 ) . '•••@' . $domain;
		}

		/**
		 * Card as shown in the activity list: Visa •• 4242.
		 *
		 * @param string $brand Brand.
		 * @param string $last4 Last four digits.
		 * @return string
		 */
		public static function card_display( $brand, $last4 ) {
			$last4 = preg_replace( '/\D/', '', (string) $last4 );
			if ( '' === $last4 ) {
				return '';
			}
			$brand = trim( ucwords( str_replace( array( '_', '-' ), ' ', strtolower( (string) $brand ) ) ) );
			return trim( ( '' !== $brand ? $brand : __( 'Card', 'wp-easycart' ) ) . ' •• ' . substr( $last4, -4 ) );
		}

		/**
		 * The shopper this request belongs to.
		 *
		 * @param array $args Optional session_id, email, card_material, card_display, user_id overrides.
		 * @return array session, ip, email, card ( keys ), ip_display, email_display, card_display, user_id, session_id, ip_raw
		 */
		public static function context( $args = array() ) {
			if ( empty( $args ) && null !== self::$context ) {
				return self::$context;
			}
			$session_id = '';
			if ( isset( $args['session_id'] ) ) {
				$session_id = (string) $args['session_id'];
			} elseif ( isset( $GLOBALS['ec_cart_data'] ) && is_object( $GLOBALS['ec_cart_data'] ) && isset( $GLOBALS['ec_cart_data']->ec_cart_id ) ) {
				$session_id = (string) $GLOBALS['ec_cart_data']->ec_cart_id;
			} elseif ( isset( $GLOBALS['ec_cart_id'] ) ) {
				$session_id = (string) $GLOBALS['ec_cart_id'];
			}
			if ( 'not-set' === $session_id || ! preg_match( '/^[A-Z]{20,40}$/', $session_id ) ) {
				$session_id = '';
			}
			$email = '';
			if ( isset( $args['email'] ) ) {
				$email = (string) $args['email'];
			} elseif ( isset( $GLOBALS['ec_cart_data']->cart_data->email ) && '' !== (string) $GLOBALS['ec_cart_data']->cart_data->email ) {
				$email = (string) $GLOBALS['ec_cart_data']->cart_data->email;
			} elseif ( isset( $GLOBALS['ec_user'] ) && is_object( $GLOBALS['ec_user'] ) && isset( $GLOBALS['ec_user']->email ) ) {
				$email = (string) $GLOBALS['ec_user']->email;
			}
			$email   = is_email( $email ) ? strtolower( trim( $email ) ) : '';
			$user_id = isset( $args['user_id'] ) ? (int) $args['user_id'] : ( ( isset( $GLOBALS['ec_user'] ) && is_object( $GLOBALS['ec_user'] ) && isset( $GLOBALS['ec_user']->user_id ) ) ? (int) $GLOBALS['ec_user']->user_id : 0 );
			$ip      = array_key_exists( 'ip', $args ) ? (string) $args['ip'] : self::client_ip();
			$context = array(
				'session_id'    => $session_id,
				'session'       => self::key( 's', $session_id ),
				'ip_raw'        => $ip,
				'ip'            => self::key( 'i', self::ip_material( $ip ) ),
				'email'         => self::key( 'e', $email ),
				'card'          => isset( $args['card_material'] ) ? self::key( 'c', $args['card_material'] ) : '',
				'ip_display'    => self::mask_ip( $ip ),
				'email_display' => self::mask_email( $email ),
				'card_display'  => isset( $args['card_display'] ) ? (string) $args['card_display'] : '',
				'user_id'       => $user_id,
			);
			if ( empty( $args ) ) {
				self::$context = $context;
			}
			return $context;
		}

		/**
		 * Card key material from raw card fields ( never stored ): first six, last four and expiry.
		 *
		 * @param string $number    Card number.
		 * @param string $exp_month Expiry month.
		 * @param string $exp_year  Expiry year.
		 * @return string
		 */
		public static function card_material( $number, $exp_month = '', $exp_year = '' ) {
			$digits = preg_replace( '/\D/', '', (string) $number );
			if ( strlen( $digits ) < 12 ) {
				return '';
			}
			return substr( $digits, 0, 6 ) . substr( $digits, -4 ) . '|' . (int) $exp_month . '|' . substr( (string) $exp_year, -2 );
		}

		// ------------------------------------------------------------------
		// Trust and blocks.
		// ------------------------------------------------------------------

		/**
		 * Store staff are never limited.
		 *
		 * @return bool
		 */
		public static function is_staff() {
			return function_exists( 'current_user_can' ) && ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_orders' ) || current_user_can( 'wpec_manager' ) ); // phpcs:ignore WordPress.WP.Capabilities.Unknown -- EasyCart's own roles register these capabilities.
		}

		/**
		 * Whether the visitor's address is on the never-limit list.
		 *
		 * @param array $context Context.
		 * @return bool
		 */
		public static function is_allowed( $context ) {
			return '' !== $context['ip_raw'] && self::ip_in_list( $context['ip_raw'], self::settings()['allow_list'] );
		}

		/**
		 * Whether the visitor's address, email or email domain is on the block list.
		 *
		 * @param array  $context Context.
		 * @param string $email   Raw email when known.
		 * @return bool
		 */
		public static function is_blocked( $context, $email = '' ) {
			$list = self::settings()['block_list'];
			if ( '' === trim( $list ) ) {
				return false;
			}
			if ( '' === $email && isset( $GLOBALS['ec_cart_data']->cart_data->email ) ) {
				$email = (string) $GLOBALS['ec_cart_data']->cart_data->email;
			}
			$email  = strtolower( trim( $email ) );
			$domain = ( false !== strpos( $email, '@' ) ) ? substr( $email, strpos( $email, '@' ) ) : '';
			foreach ( self::lines( $list ) as $entry ) {
				$entry = strtolower( trim( preg_replace( '/\s*[#·].*$/u', '', $entry ) ) );
				if ( '' === $entry ) {
					continue;
				}
				if ( 0 === strpos( $entry, 'hash:' ) ) {
					$hash = substr( $entry, 5 );
					if ( in_array( $hash, array( $context['ip'], $context['email'] ), true ) && '' !== $hash ) {
						return true;
					}
					continue;
				}
				if ( false !== strpos( $entry, '@' ) ) {
					if ( '' !== $email && ( $entry === $email || ( '@' === $entry[0] && $entry === $domain ) ) ) {
						return true;
					}
					continue;
				}
				if ( '' !== $context['ip_raw'] && self::ip_in_range( $context['ip_raw'], $entry ) ) {
					return true;
				}
			}
			return false;
		}

		/**
		 * A signed-in customer who has paid the store before.
		 *
		 * @param array $context Context.
		 * @return bool
		 */
		public static function is_trusted_customer( $context ) {
			static $cache = array();
			if ( ! self::settings()['trust_customers'] || $context['user_id'] <= 0 ) {
				return false;
			}
			if ( isset( $cache[ $context['user_id'] ] ) ) {
				return $cache[ $context['user_id'] ];
			}
			global $wpdb;
			$paid = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_order o INNER JOIN ec_orderstatus s ON s.status_id = o.orderstatus_id WHERE o.user_id = %d AND s.is_approved = 1', $context['user_id'] ) );

			$cache[ $context['user_id'] ] = $paid > 0;
			return $cache[ $context['user_id'] ];
		}

		// ------------------------------------------------------------------
		// Time helpers.
		// ------------------------------------------------------------------

		/**
		 * Now, as a UTC MySQL datetime.
		 *
		 * @param int $offset Seconds to add.
		 * @return string
		 */
		private static function gmt( $offset = 0 ) {
			return gmdate( 'Y-m-d H:i:s', time() + (int) $offset );
		}

		/**
		 * A stored UTC datetime as a timestamp.
		 *
		 * @param string $datetime Datetime.
		 * @return int
		 */
		public static function ts( $datetime ) {
			$ts = $datetime ? strtotime( $datetime . ' UTC' ) : false;
			return false === $ts ? 0 : (int) $ts;
		}

		/**
		 * A timestamp in the store's time format ( e.g. 3:40 pm ), with the date when it isn't today.
		 *
		 * @param int $ts Timestamp.
		 * @return string
		 */
		public static function local_time( $ts ) {
			$format = get_option( 'time_format', 'g:i a' );
			$today  = function_exists( 'wp_date' ) ? wp_date( 'Ymd' ) : date_i18n( 'Ymd' );
			$day    = function_exists( 'wp_date' ) ? wp_date( 'Ymd', $ts ) : date_i18n( 'Ymd', $ts );
			if ( $day !== $today ) {
				$format = get_option( 'date_format', 'F j' ) . ' ' . $format;
			}
			return function_exists( 'wp_date' ) ? wp_date( $format, $ts ) : date_i18n( $format, $ts );
		}

		// ------------------------------------------------------------------
		// Pauses.
		// ------------------------------------------------------------------

		/**
		 * The recent failures for one key, newest first, stopping at an unpause.
		 *
		 * @param string $column   session_key, ip_key, email_key or card_key.
		 * @param string $key      Key.
		 * @param int    $since    Oldest timestamp to look at.
		 * @param int    $limit    Most rows to read.
		 * @param string $type     Event type to count.
		 * @return int[] Timestamps.
		 */
		private static function failures( $column, $key, $since, $limit, $type = 'decline' ) {
			$queries = array(
				'session_key' => 'SELECT created_at, event_type FROM ec_checkout_event WHERE session_key = %s AND event_type IN ( %s, %s ) AND created_at >= %s ORDER BY created_at DESC, event_id DESC LIMIT %d',
				'ip_key'      => 'SELECT created_at, event_type FROM ec_checkout_event WHERE ip_key = %s AND event_type IN ( %s, %s ) AND created_at >= %s ORDER BY created_at DESC, event_id DESC LIMIT %d',
				'email_key'   => 'SELECT created_at, event_type FROM ec_checkout_event WHERE email_key = %s AND event_type IN ( %s, %s ) AND created_at >= %s ORDER BY created_at DESC, event_id DESC LIMIT %d',
				'card_key'    => 'SELECT created_at, event_type FROM ec_checkout_event WHERE card_key = %s AND event_type IN ( %s, %s ) AND created_at >= %s ORDER BY created_at DESC, event_id DESC LIMIT %d',
			);
			if ( '' === (string) $key || ! isset( $queries[ $column ] ) ) {
				return array();
			}
			global $wpdb;
			$rows = $wpdb->get_results( $wpdb->prepare( $queries[ $column ], $key, $type, 'cleared', gmdate( 'Y-m-d H:i:s', (int) $since ), (int) $limit + 1 ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- one of the literal queries above, chosen by a whitelisted key.
			$out  = array();
			foreach ( (array) $rows as $row ) {
				if ( 'cleared' === $row->event_type ) {
					break;
				}
				$out[] = self::ts( $row->created_at );
			}
			return $out;
		}

		/**
		 * Whether any of the shopper's keys is paused right now.
		 *
		 * @param array $context Context.
		 * @return array|false array( 'until' => timestamp, 'key' => which key ) or false.
		 */
		public static function pause_for( $context ) {
			if ( ! self::ready() ) {
				return false;
			}
			$limits  = self::limits();
			$attack  = self::attack_active();
			$trusted = self::is_trusted_customer( $context );
			$fails   = $attack ? self::settings()['attack_limit'] : $limits['fails'];
			$window  = $attack ? HOUR_IN_SECONDS : $limits['window'];
			$pause   = $attack ? max( HOUR_IN_SECONDS, $limits['pause'] ) : $limits['pause'];
			$daily   = $limits['daily'];
			/* 6.0.2: while the human check can't be given ( its provider is down ), payments carry on under stricter limits:
			   half the failed payments before a pause, and a pause of at least an hour. */
			if ( self::check_degraded() ) {
				$fails = max( 1, (int) ceil( $fails / 2 ) );
				$daily = max( 1, (int) ceil( $daily / 2 ) );
				$pause = max( HOUR_IN_SECONDS, $pause );
			}
			if ( $trusted ) {
				$fails *= 2;
				$daily *= 2;
			}
			$daily = max( $daily, $fails );
			$now   = time();
			$best  = false;
			foreach ( array(
				'session_key' => 'session',
				'ip_key'      => 'ip',
				'email_key'   => 'email',
			) as $column => $name ) {
				$times = self::failures( $column, $context[ $name ], $now - DAY_IN_SECONDS, $daily );
				$until = 0;
				if ( count( $times ) >= $fails && ( $times[0] - $times[ $fails - 1 ] ) <= $window ) {
					$until = $times[0] + $pause;
				}
				if ( count( $times ) >= $daily ) {
					$until = max( $until, $times[0] + $pause );
				}
				if ( $until > $now && ( false === $best || $until > $best['until'] ) ) {
					$best = array(
						'until' => $until,
						'key'   => $name,
					);
				}
			}
			if ( '' !== $context['card'] ) {
				$card_limit = $attack ? min( 2, $limits['card'] ) : $limits['card'];
				$times      = self::failures( 'card_key', $context['card'], $now - DAY_IN_SECONDS, $card_limit );
				if ( count( $times ) >= $card_limit && $times[0] + DAY_IN_SECONDS > $now && ( false === $best || $times[0] + DAY_IN_SECONDS > $best['until'] ) ) {
					$best = array(
						'until' => $times[0] + DAY_IN_SECONDS,
						'key'   => 'card',
					);
				}
			}
			return $best;
		}

		/**
		 * Whether the shopper had a decline in the last hour ( smart human check ).
		 *
		 * @param array $context Context.
		 * @return bool
		 */
		private static function recent_decline( $context ) {
			$since = time() - self::RECENT_DECLINE_SECONDS;
			foreach ( array(
				'session_key' => 'session',
				'ip_key'      => 'ip',
				'email_key'   => 'email',
			) as $column => $name ) {
				if ( count( self::failures( $column, $context[ $name ], $since, 1 ) ) > 0 ) {
					return true;
				}
			}
			return false;
		}

		// ------------------------------------------------------------------
		// Page visits and passed checks ( per session ).
		// ------------------------------------------------------------------

		/**
		 * Note that this session was shown a checkout payment step ( the "came from the page" signal ).
		 */
		public static function mark_seen() {
			$context = self::context();
			if ( '' !== $context['session'] ) {
				set_transient( 'wpec_cp_seen_' . $context['session'], time(), self::SEEN_SECONDS );
			}
		}

		/**
		 * Whether this session was shown a checkout payment step recently.
		 *
		 * @param array $context Context.
		 * @return bool
		 */
		public static function seen( $context ) {
			return '' !== $context['session'] && false !== get_transient( 'wpec_cp_seen_' . $context['session'] );
		}

		/**
		 * Whether the session passed a human check recently.
		 *
		 * @param array $context Context.
		 * @return bool
		 */
		public static function has_passed( $context ) {
			if ( '' === $context['session'] ) {
				return false;
			}
			$until = (int) get_transient( 'wpec_cp_pass_' . $context['session'] );
			return $until > time();
		}

		/**
		 * Remember a passed human check for the session.
		 *
		 * @param array $context Context.
		 */
		private static function mark_passed( $context ) {
			if ( '' !== $context['session'] ) {
				set_transient( 'wpec_cp_pass_' . $context['session'], time() + self::PASS_SECONDS, self::PASS_SECONDS );
				set_transient( 'wpec_cp_seen_' . $context['session'], time(), self::SEEN_SECONDS );
			}
		}

		// ------------------------------------------------------------------
		// Human check providers.
		// ------------------------------------------------------------------

		/**
		 * Site key for the chosen provider.
		 *
		 * @return string
		 */
		public static function site_key() {
			if ( 'recaptcha' === self::settings()['provider'] ) {
				return trim( (string) get_option( 'ec_option_recaptcha_site_key', '' ) );
			}
			return trim( (string) self::opt( 'turnstile_site_key', '' ) );
		}

		/**
		 * Secret key for the chosen provider.
		 *
		 * @return string
		 */
		private static function secret_key() {
			if ( 'recaptcha' === self::settings()['provider'] ) {
				return trim( (string) get_option( 'ec_option_recaptcha_secret_key', '' ) );
			}
			return trim( (string) self::opt( 'turnstile_secret_key', '' ) );
		}

		/**
		 * Whether a human check can be shown now: keys set, not switched off, and the provider not failing.
		 *
		 * @return bool
		 */
		public static function check_available() {
			return 'never' !== self::settings()['human'] && '' !== self::site_key() && '' !== self::secret_key() && false === get_transient( 'wpec_cp_provider_down' );
		}

		/**
		 * The provider's last failure, for the settings page.
		 *
		 * @return string '' when working.
		 */
		public static function provider_problem() {
			$down = get_transient( 'wpec_cp_provider_down' );
			return false === $down ? '' : (string) $down;
		}

		/**
		 * The store set up a human check but its provider is not answering, so shoppers who would be checked are let through
		 * ( fail-open ) under stricter limits instead ( pause_for() ).
		 *
		 * @since 6.0.2
		 * @return bool
		 */
		public static function check_degraded() {
			return 'never' !== self::settings()['human'] && '' !== self::site_key() && '' !== self::secret_key() && '' !== self::provider_problem();
		}

		/**
		 * Verify a human-check token with the provider.
		 *
		 * @param string $token    Token from the widget.
		 * @param string $secret   Secret key ( defaults to the stored one; the settings page tests typed keys ).
		 * @param string $provider Provider ( defaults to the stored one ).
		 * @return string pass | fail | unavailable
		 */
		public static function verify_token( $token, $secret = '', $provider = '' ) {
			$token    = trim( (string) $token );
			$provider = '' === $provider ? self::settings()['provider'] : $provider;
			$secret   = '' === $secret ? self::secret_key() : $secret;
			if ( '' === $token || '' === $secret ) {
				return 'fail';
			}
			$url  = ( 'recaptcha' === $provider ) ? 'https://www.google.com/recaptcha/api/siteverify' : 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
			$body = array(
				'secret'   => $secret,
				'response' => $token,
			);
			$ip   = self::client_ip();
			if ( '' !== $ip ) {
				$body['remoteip'] = $ip;
			}
			$response = wp_remote_post(
				$url,
				array(
					'timeout' => 10,
					'body'    => $body,
				)
			);
			if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
				$problem = is_wp_error( $response ) ? $response->get_error_message() : 'HTTP ' . (int) wp_remote_retrieve_response_code( $response );
				set_transient( 'wpec_cp_provider_down', $problem, 10 * MINUTE_IN_SECONDS );
				return 'unavailable';
			}
			$json = json_decode( wp_remote_retrieve_body( $response ) );
			if ( ! is_object( $json ) ) {
				set_transient( 'wpec_cp_provider_down', 'Unreadable answer', 10 * MINUTE_IN_SECONDS );
				return 'unavailable';
			}
			/* reCAPTCHA keys over their free quota answer "success" with a score; a v2 key never has one. */
			if ( 'recaptcha' === $provider && isset( $json->score ) ) {
				set_transient( 'wpec_cp_provider_down', 'reCAPTCHA quota', HOUR_IN_SECONDS );
				return 'unavailable';
			}
			$codes = isset( $json->{'error-codes'} ) ? (array) $json->{'error-codes'} : array();
			if ( array_intersect( $codes, array( 'invalid-input-secret', 'missing-input-secret' ) ) ) {
				set_transient( 'wpec_cp_provider_down', 'Secret key refused', 10 * MINUTE_IN_SECONDS );
				return 'unavailable';
			}
			return ! empty( $json->success ) ? 'pass' : 'fail';
		}

		// ------------------------------------------------------------------
		// The gate.
		// ------------------------------------------------------------------

		/**
		 * Why this shopper needs a human check now, or ''.
		 *
		 * @param array $context Context.
		 * @return string attack | always | declined | page | ''
		 */
		public static function check_reason( $context ) {
			$settings = self::settings();
			if ( 'never' === $settings['human'] ) {
				return '';
			}
			if ( self::attack_active() ) {
				return 'attack';
			}
			if ( 'always' === $settings['human'] ) {
				return 'always';
			}
			if ( self::is_trusted_customer( $context ) ) {
				return '';
			}
			if ( self::recent_decline( $context ) ) {
				return 'declined';
			}
			if ( $settings['require_page'] && ! self::seen( $context ) ) {
				return 'page';
			}
			return '';
		}

		/**
		 * Gate one payment attempt.
		 *
		 * @param string $place checkout | express | pay_link | subscription | paypal | card_update.
		 * @param array  $args  Optional gateway, amount, email, card_material, card_display, record ( false to skip the log ).
		 * @return true|WP_Error protection_blocked | protection_paused | protection_check | protection_page
		 */
		public static function check( $place, $args = array() ) {
			if ( ! self::is_on() || self::is_staff() ) {
				return true;
			}
			self::maybe_end_attack();
			$context = empty( $args['card_material'] ) && empty( $args['email'] ) ? self::context() : self::context(
				array_filter(
					array(
						'email'         => isset( $args['email'] ) ? $args['email'] : null,
						'card_material' => isset( $args['card_material'] ) ? $args['card_material'] : null,
						'card_display'  => isset( $args['card_display'] ) ? $args['card_display'] : null,
					),
					function ( $value ) {
						return null !== $value;
					}
				)
			);
			if ( self::is_allowed( $context ) ) {
				return true;
			}
			$error = null;
			if ( self::is_blocked( $context, isset( $args['email'] ) ? (string) $args['email'] : '' ) ) {
				$error = new WP_Error( 'protection_blocked', self::text( 'declined' ), array( 'reason' => 'blocked' ) );
			} else {
				$pause = self::pause_for( $context );
				if ( $pause && 'card' === $pause['key'] ) {
					/* 6.0.2: a card that failed too often today is refused like a decline. The shopper may pay with another
					   card, so the page is not paused ( the storefront state never sees the card ). Only card forms that post
					   the card to the store pass it here; Stripe and Square cards are known only after a decline. */
					$error = new WP_Error(
						'protection_blocked',
						self::text( 'declined' ),
						array(
							'reason' => 'paused_card',
							'until'  => $pause['until'],
						)
					);
				} elseif ( $pause ) {
					$error = new WP_Error(
						'protection_paused',
						self::paused_message( $pause['until'] ),
						array(
							'reason' => 'paused_' . $pause['key'],
							'until'  => $pause['until'],
						)
					);
					if ( ! self::is_watch() ) {
						self::cancel_session_intent( $context );
					}
				} else {
					$why = self::check_reason( $context );
					if ( '' !== $why && ! self::has_passed( $context ) ) {
						if ( self::check_available() ) {
							$error = new WP_Error( 'protection_check', self::text( 'check_needed' ), array( 'reason' => 'check_' . $why ) );
						} elseif ( 'page' === $why || ( 'attack' === $why && ! self::seen( $context ) ) ) {
							$error = new WP_Error( 'protection_page', self::text( 'reload' ), array( 'reason' => 'no_page' ) );
						}
					}
				}
			}
			if ( null === $error ) {
				return true;
			}
			$data   = $error->get_error_data();
			$reason = isset( $data['reason'] ) ? $data['reason'] : $error->get_error_code();
			if ( ! isset( $args['record'] ) || false !== $args['record'] ) {
				self::record(
					self::is_watch() ? 'would_stop' : ( 'protection_check' === $error->get_error_code() ? 'check' : 'stop' ),
					$reason,
					array(
						'place'   => $place,
						'gateway' => isset( $args['gateway'] ) ? $args['gateway'] : '',
						'amount'  => isset( $args['amount'] ) ? $args['amount'] : 0,
					),
					$context
				);
				if ( 'protection_check' !== $error->get_error_code() && self::attack_active() ) {
					self::extend_attack();
				}
			}
			if ( self::is_watch() ) {
				return true;
			}
			self::$header_state = ( 'protection_check' === $error->get_error_code() ) ? 'check' : 'paused';
			self::send_state_header();
			return $error;
		}

		/**
		 * What the storefront should show on the payment step now.
		 *
		 * @return array state ( ok | check | paused | blocked | page ), message, until
		 */
		public static function state() {
			$out = array(
				'state'   => 'ok',
				'message' => '',
				'until'   => 0,
			);
			if ( ! self::is_on() || self::is_watch() || self::is_staff() ) {
				return $out;
			}
			self::maybe_end_attack();
			$context = self::context();
			if ( self::is_allowed( $context ) ) {
				return $out;
			}
			if ( self::is_blocked( $context ) ) {
				return $out; /* bots learn nothing: the block shows only as a declined payment */
			}
			$pause = self::pause_for( $context );
			if ( $pause ) {
				$out['state']   = 'paused';
				$out['message'] = self::paused_message( $pause['until'] );
				$out['until']   = $pause['until'];
				return $out;
			}
			$why = self::check_reason( $context );
			if ( '' !== $why && 'page' !== $why && ! self::has_passed( $context ) && self::check_available() ) {
				$out['state']   = 'check';
				$out['message'] = self::text( 'check_needed' );
			}
			return $out;
		}

		/**
		 * Whether Stripe's payment form may be handed a client secret now ( paused sessions and sessions owing a
		 * human check get none; the storefront script shows the pause or the check in its place ).
		 *
		 * @return bool
		 */
		public static function stripe_allowed() {
			$state = self::state();
			if ( 'paused' === $state['state'] ) {
				self::cancel_session_intent( self::context() );
				return false;
			}
			return 'check' !== $state['state'];
		}

		/**
		 * Send the X-WPEC-Protection header so the storefront script refreshes its state after an AJAX answer.
		 */
		private static function send_state_header() {
			if ( '' !== self::$header_state && ! headers_sent() ) {
				header( 'X-WPEC-Protection: ' . self::$header_state );
			}
		}

		// ------------------------------------------------------------------
		// Recording.
		// ------------------------------------------------------------------

		/**
		 * Store one event.
		 *
		 * @param string     $type    decline | paid | stop | would_stop | check | check_pass | check_fail | cleared |
		 *                            attack_start | attack_end | code_fail | code_stop.
		 * @param string     $reason  Short reason code or the decline code.
		 * @param array      $args    place, gateway, amount, order_id, ref, detail.
		 * @param array|null $context Keys ( defaults to this request's shopper ).
		 * @return int Event id, or 0.
		 */
		public static function record( $type, $reason = '', $args = array(), $context = null ) {
			if ( ! self::ready() ) {
				return 0;
			}
			global $wpdb;
			$context = is_array( $context ) ? $context : self::context();
			$wpdb->insert(
				self::TABLE,
				array(
					'created_at'    => self::gmt(),
					'event_type'    => substr( (string) $type, 0, 20 ),
					'reason'        => substr( (string) $reason, 0, 40 ),
					'place'         => substr( isset( $args['place'] ) ? (string) $args['place'] : '', 0, 20 ),
					'gateway'       => substr( isset( $args['gateway'] ) ? (string) $args['gateway'] : '', 0, 40 ),
					'session_key'   => $context['session'],
					'ip_key'        => $context['ip'],
					'email_key'     => $context['email'],
					'card_key'      => $context['card'],
					'ip_display'    => substr( $context['ip_display'], 0, 48 ),
					'email_display' => substr( $context['email_display'], 0, 100 ),
					'card_display'  => substr( $context['card_display'], 0, 40 ),
					'user_id'       => (int) $context['user_id'],
					'order_id'      => isset( $args['order_id'] ) ? (int) $args['order_id'] : 0,
					'amount'        => isset( $args['amount'] ) ? (float) $args['amount'] : 0,
					'ref'           => substr( isset( $args['ref'] ) ? (string) $args['ref'] : '', 0, 100 ),
					'detail'        => substr( isset( $args['detail'] ) ? wp_strip_all_tags( (string) $args['detail'] ) : '', 0, 255 ),
					'in_attack'     => self::attack_active() ? 1 : 0,
				)
			);
			return (int) $wpdb->insert_id;
		}

		/**
		 * Record a declined or failed payment, then react: attack detection and, when the shopper is now paused, the
		 * Stripe intent is cancelled and the storefront told.
		 *
		 * @param string     $place   Where.
		 * @param array      $args    gateway, amount, order_id, ref ( dedupes ), detail, reason.
		 * @param array|null $context Keys.
		 * @return bool Whether a new decline was recorded ( false when protection is off or it was already counted ).
		 */
		public static function record_decline( $place, $args = array(), $context = null ) {
			if ( ! self::is_on() ) {
				return false;
			}
			global $wpdb;
			if ( ! empty( $args['ref'] ) && $wpdb->get_var( $wpdb->prepare( 'SELECT event_id FROM ec_checkout_event WHERE ref = %s LIMIT 1', (string) $args['ref'] ) ) ) {
				return false;
			}
			$context       = is_array( $context ) ? $context : self::context();
			$args['place'] = $place;
			self::record( 'decline', isset( $args['reason'] ) ? $args['reason'] : '', $args, $context );
			self::maybe_start_attack();
			if ( self::attack_active() ) {
				self::extend_attack();
			}
			if ( ! self::is_watch() && self::pause_for( $context ) ) {
				self::cancel_session_intent( $context );
				self::$header_state = 'paused';
			} elseif ( ! self::is_watch() && 'never' !== self::settings()['human'] && self::check_available() ) {
				self::$header_state = 'check';
			}
			self::send_state_header();
			return true;
		}

		/**
		 * Action wpeasycart_payment_failed: a card payment through ec_order::submit_order() was refused.
		 *
		 * @param int      $order_id Order row that is about to be removed.
		 * @param string   $message  Gateway message.
		 * @param string   $gateway  Gateway.
		 * @param ec_order $order    Order.
		 */
		public static function on_payment_failed( $order_id, $message = '', $gateway = '', $order = null ) {
			$card_material = '';
			$card_display  = '';
			if ( is_object( $order ) && isset( $order->payment ) && is_object( $order->payment ) && isset( $order->payment->credit_card ) && is_object( $order->payment->credit_card ) ) {
				$card = $order->payment->credit_card;
				if ( ! empty( $card->card_number ) ) {
					$card_material = self::card_material( $card->card_number, isset( $card->expiration_month ) ? $card->expiration_month : '', isset( $card->expiration_year ) ? $card->expiration_year : '' );
					$card_display  = self::card_display( isset( $card->payment_method ) ? $card->payment_method : '', substr( preg_replace( '/\D/', '', (string) $card->card_number ), -4 ) );
				}
			}
			$context = ( '' !== $card_material ) ? self::context(
				array(
					'card_material' => $card_material,
					'card_display'  => $card_display,
				)
			) : self::context();
			$amount  = (float) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( 'SELECT grand_total FROM ec_order WHERE order_id = %d', (int) $order_id ) ); /* the row is removed right after this action */
			self::record_decline(
				'checkout',
				array(
					'gateway' => $gateway ? $gateway : (string) get_option( 'ec_option_payment_process_method' ),
					'amount'  => $amount,
					'detail'  => $message,
					'reason'  => 'declined',
				),
				$context
			);
		}

		/**
		 * Action wpeasycart_order_paid: note the payment and flag a likely card test.
		 *
		 * @param int $order_id Order.
		 */
		public static function on_order_paid( $order_id ) {
			if ( ! self::is_on() || isset( $_GET['wpeasycarthook'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only tells a webhook request apart; nothing is read from it.
				return;
			}
			global $wpdb;
			$context = self::context();
			$order   = $wpdb->get_row( $wpdb->prepare( 'SELECT order_id, grand_total, order_gateway FROM ec_order WHERE order_id = %d', (int) $order_id ) );
			if ( ! $order ) {
				return;
			}
			$in_attack = self::attack_active() || self::recent_attack_ended( HOUR_IN_SECONDS );
			$declines  = 0;
			foreach ( array(
				'session_key' => 'session',
				'ip_key'      => 'ip',
				'email_key'   => 'email',
			) as $column => $name ) {
				$declines = max( $declines, count( self::failures( $column, $context[ $name ], time() - HOUR_IN_SECONDS, 5 ) ) );
			}
			$flag = $in_attack && $declines > 0 && ! self::is_trusted_customer( $context );
			self::record(
				'paid',
				$flag ? 'possible_test' : '',
				array(
					'place'    => 'checkout',
					'gateway'  => (string) $order->order_gateway,
					'amount'   => (float) $order->grand_total,
					'order_id' => (int) $order->order_id,
				),
				$context
			);
			if ( $flag && self::settings()['flag_orders'] ) {
				self::flag_order( (int) $order->order_id, $declines );
			}
		}

		/**
		 * Tag an order as a possible card test: a staff comment and an order log key the orders list can filter on.
		 *
		 * @param int $order_id Order.
		 * @param int $declines Declines the shopper had first.
		 */
		public static function flag_order( $order_id, $declines ) {
			global $wpdb;
			/* Paid can be reported twice for one order ( return page and a retry ): one tag, one note. */
			if ( $wpdb->get_var( $wpdb->prepare( 'SELECT order_log_id FROM ec_order_log WHERE order_id = %d AND order_log_key = %s LIMIT 1', (int) $order_id, self::ORDER_LOG_KEY ) ) ) {
				return;
			}
			$wpdb->insert(
				'ec_order_log',
				array(
					'order_id'      => (int) $order_id,
					'order_log_key' => self::ORDER_LOG_KEY,
				)
			);
			update_option( self::FLAGGED_OPTION, time() );
			$wpdb->insert(
				'ec_order_log',
				array(
					'order_id'      => (int) $order_id,
					'order_log_key' => 'staff-comment',
				)
			);
			$log_id = (int) $wpdb->insert_id;
			$text   = sprintf(
				/* translators: %d: number of declined payments before this one. */
				_n(
					'Possible card test: this payment went through during a card-testing attack, after %d declined payment from the same shopper. If you don\'t recognise the customer, refund it now so it can\'t turn into a dispute.',
					'Possible card test: this payment went through during a card-testing attack, after %d declined payments from the same shopper. If you don\'t recognise the customer, refund it now so it can\'t turn into a dispute.',
					$declines,
					'wp-easycart'
				),
				$declines
			);
			foreach ( array(
				'comment' => $text,
				'author'  => 'WP EasyCart',
			) as $meta_key => $meta_value ) {
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
		 * Has this store ever had an order tagged as a possible card test?
		 *
		 * @return bool
		 */
		public static function has_flagged_orders() {
			return (bool) get_option( self::FLAGGED_OPTION, 0 );
		}

		/**
		 * SQL that is true when the order is tagged as a possible card test.
		 *
		 * @param string $order_alias Table or alias holding order_id.
		 * @return string
		 */
		public static function flagged_sql( $order_alias = 'ec_order' ) {
			$order_alias = preg_replace( '/[^A-Za-z0-9_]/', '', (string) $order_alias );
			return 'EXISTS ( SELECT 1 FROM ec_order_log cp_log WHERE cp_log.order_id = ' . $order_alias . ".order_id AND cp_log.order_log_key = '" . self::ORDER_LOG_KEY . "' )";
		}

		/**
		 * Which of these orders are tagged as possible card tests.
		 *
		 * @param int[] $order_ids Orders.
		 * @return int[]
		 */
		public static function flagged_among( $order_ids ) {
			global $wpdb;
			$order_ids = array_values( array_filter( array_map( 'intval', (array) $order_ids ) ) );
			if ( empty( $order_ids ) || ! self::has_flagged_orders() ) {
				return array();
			}
			$placeholders = implode( ',', array_fill( 0, count( $order_ids ), '%d' ) );
			$args         = array_merge( array( self::ORDER_LOG_KEY ), $order_ids );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $placeholders is a list of %d markers; the ids are prepare() arguments.
			return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT order_id FROM ec_order_log WHERE order_log_key = %s AND order_id IN ( $placeholders )", $args ) ) );
		}

		// ------------------------------------------------------------------
		// Stripe.
		// ------------------------------------------------------------------

		/**
		 * The configured Stripe class, or null.
		 *
		 * @return object|null
		 */
		private static function stripe() {
			$method = (string) get_option( 'ec_option_payment_process_method' );
			if ( 'stripe_connect' === $method && class_exists( 'ec_stripe_connect' ) ) {
				return new ec_stripe_connect();
			}
			if ( 'stripe' === $method && class_exists( 'ec_stripe' ) ) {
				return new ec_stripe();
			}
			return null;
		}

		/**
		 * Remember which shopper a payment intent belongs to, for its webhook.
		 *
		 * @param string $intent_id Payment intent id.
		 */
		public static function map_intent( $intent_id ) {
			if ( '' === (string) $intent_id || ! self::is_on() ) {
				return;
			}
			$context = self::context();
			set_transient(
				'wpec_cp_pi_' . md5( (string) $intent_id ),
				array(
					'session'       => $context['session'],
					'ip'            => $context['ip'],
					'email'         => $context['email'],
					'ip_display'    => $context['ip_display'],
					'email_display' => $context['email_display'],
					'user_id'       => $context['user_id'],
				),
				2 * DAY_IN_SECONDS
			);
		}

		/**
		 * The shopper keys for a payment intent ( its map, else the session that holds it ).
		 *
		 * @param string $intent_id Payment intent id.
		 * @return array Context.
		 */
		private static function intent_context( $intent_id ) {
			$blank = array(
				'session_id'    => '',
				'session'       => '',
				'ip_raw'        => '',
				'ip'            => '',
				'email'         => '',
				'card'          => '',
				'ip_display'    => '',
				'email_display' => '',
				'card_display'  => '',
				'user_id'       => 0,
			);
			$map   = get_transient( 'wpec_cp_pi_' . md5( (string) $intent_id ) );
			if ( is_array( $map ) ) {
				return array_merge( $blank, $map );
			}
			global $wpdb;
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT session_id, email, user_id FROM ec_tempcart_data WHERE stripe_paymentintent_id = %s ORDER BY tempcart_data_id DESC LIMIT 1', (string) $intent_id ) );
			if ( $row ) {
				$email                  = is_email( $row->email ) ? strtolower( trim( $row->email ) ) : '';
				$blank['session_id']    = (string) $row->session_id;
				$blank['session']       = self::key( 's', $row->session_id );
				$blank['email']         = self::key( 'e', $email );
				$blank['email_display'] = self::mask_email( $email );
				$blank['user_id']       = (int) $row->user_id;
			}
			return $blank;
		}

		/**
		 * Record the latest failure on a payment intent, fetched from Stripe ( never trusted from the caller ).
		 *
		 * @param string     $intent_id Payment intent id.
		 * @param array|null $context   Keys when the caller is the shopper.
		 * @return bool Whether a new decline was recorded.
		 */
		public static function record_stripe_failure( $intent_id, $context = null ) {
			$stripe = self::stripe();
			if ( ! $stripe || '' === (string) $intent_id || ! method_exists( $stripe, 'get_payment_intent' ) ) {
				return false;
			}
			if ( isset( $GLOBALS['ec_stripe_payment_intent'] ) ) {
				unset( $GLOBALS['ec_stripe_payment_intent'] );
			}
			$intent = $stripe->get_payment_intent( $intent_id );
			if ( ! $intent || ! isset( $intent->last_payment_error ) || ! is_object( $intent->last_payment_error ) ) {
				return false;
			}
			return self::record_stripe_error( $intent_id, $intent->last_payment_error, isset( $intent->amount ) ? ( (float) $intent->amount / 100 ) : 0, 'checkout', $context );
		}

		/**
		 * Record the latest failure on a My Account card form's setup intent, fetched from Stripe.
		 *
		 * @param string     $setup_intent_id Setup intent id.
		 * @param array|null $context         Keys when the caller is the shopper.
		 * @return bool Whether a new decline was recorded.
		 */
		public static function record_stripe_setup_failure( $setup_intent_id, $context = null ) {
			$stripe = self::stripe();
			if ( ! $stripe || '' === (string) $setup_intent_id || ! method_exists( $stripe, 'get_setup_intent' ) ) {
				return false;
			}
			$intent = $stripe->get_setup_intent( $setup_intent_id );
			if ( ! $intent || ! isset( $intent->last_setup_error ) || ! is_object( $intent->last_setup_error ) ) {
				return false;
			}
			return self::record_stripe_error( $setup_intent_id, $intent->last_setup_error, 0, 'card_update', $context );
		}

		/**
		 * Count one Stripe card error ( a payment intent's last_payment_error or a setup intent's last_setup_error ).
		 *
		 * @param string     $intent_id Intent id.
		 * @param object     $error     Stripe error object.
		 * @param float      $amount    Amount.
		 * @param string     $place     checkout | card_update.
		 * @param array|null $context   Keys when the caller is the shopper.
		 * @return bool Whether a new decline was recorded.
		 */
		private static function record_stripe_error( $intent_id, $error, $amount, $place, $context ) {
			$pm      = ( isset( $error->payment_method ) && is_object( $error->payment_method ) ) ? $error->payment_method : null;
			$attempt = isset( $error->charge ) && '' !== (string) $error->charge ? (string) $error->charge : ( $pm && isset( $pm->id ) ? (string) $pm->id : '' );
			if ( '' === $attempt ) {
				return false;
			}
			$context = is_array( $context ) ? $context : self::intent_context( $intent_id );
			if ( $pm && isset( $pm->card ) && is_object( $pm->card ) ) {
				if ( ! empty( $pm->card->fingerprint ) ) {
					$context['card'] = self::key( 'c', 'fp|' . $pm->card->fingerprint );
				}
				$context['card_display'] = self::card_display( isset( $pm->card->brand ) ? $pm->card->brand : '', isset( $pm->card->last4 ) ? $pm->card->last4 : '' );
			}
			$code = isset( $error->decline_code ) ? (string) $error->decline_code : ( isset( $error->code ) ? (string) $error->code : 'declined' );
			return self::record_decline(
				$place,
				array(
					'gateway' => 'stripe',
					'amount'  => $amount,
					'ref'     => 'stripe:' . $intent_id . ':' . $attempt,
					'reason'  => $code,
					'detail'  => isset( $error->message ) ? (string) $error->message : '',
				),
				$context
			);
		}

		/**
		 * Action wpeasycart_stripe_webhook: count Stripe declines that happened in the browser.
		 *
		 * @param string $webhook_id Event id.
		 * @param string $type       Event type.
		 * @param object $data       Event object ( only its id is used; the intent is fetched again ).
		 */
		public static function on_stripe_webhook( $webhook_id, $type, $data ) {
			if ( ! is_object( $data ) || empty( $data->id ) || ! self::is_on() ) {
				return;
			}
			if ( 'payment_intent.payment_failed' === $type ) {
				self::record_stripe_failure( (string) $data->id );
			} elseif ( 'setup_intent.setup_failed' === $type && false !== get_transient( 'wpec_cp_pi_' . md5( (string) $data->id ) ) ) {
				/* Only setup intents the My Account card form made ( map_setup_intent() ); other flows are gated elsewhere. */
				self::record_stripe_setup_failure( (string) $data->id );
			}
		}

		/**
		 * The My Account card form ( subscriptions ): may it get a Stripe setup intent now? Paused customers and
		 * customers owing a human check get none; the storefront script shows the pause or the check in its place.
		 *
		 * @return bool
		 */
		public static function card_update_allowed() {
			self::mark_seen();
			$state = self::state();
			if ( 'paused' === $state['state'] ) {
				self::cancel_session_intent( self::context() );
				return false;
			}
			return 'check' !== $state['state'];
		}

		/**
		 * Remember the My Account card form's setup intent: its webhook, the browser's error report and a pause find it.
		 *
		 * @param string $setup_intent_id Setup intent id.
		 */
		public static function map_setup_intent( $setup_intent_id ) {
			if ( '' === (string) $setup_intent_id || ! self::is_on() ) {
				return;
			}
			self::map_intent( $setup_intent_id );
			$context = self::context();
			if ( '' !== $context['session'] ) {
				set_transient( 'wpec_cp_seti_' . $context['session'], (string) $setup_intent_id, DAY_IN_SECONDS );
			}
		}

		/**
		 * Cancel the session's open setup intent ( My Account card form ).
		 *
		 * @param array $context Context.
		 */
		private static function cancel_setup_intent( $context ) {
			if ( empty( $context['session'] ) ) {
				return;
			}
			$setup_intent_id = (string) get_transient( 'wpec_cp_seti_' . $context['session'] );
			if ( '' === $setup_intent_id ) {
				return;
			}
			delete_transient( 'wpec_cp_seti_' . $context['session'] );
			$stripe = self::stripe();
			if ( $stripe && method_exists( $stripe, 'cancel_setup_intent' ) ) {
				$stripe->cancel_setup_intent( $setup_intent_id );
			}
		}

		/**
		 * Cancel the session's open payment and setup intents so a client secret already on the page stops working.
		 *
		 * @param array $context Context.
		 */
		public static function cancel_session_intent( $context ) {
			self::cancel_setup_intent( $context );
			if ( '' === $context['session_id'] ) {
				return;
			}
			global $wpdb;
			$intent_id = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT stripe_paymentintent_id FROM ec_tempcart_data WHERE session_id = %s ORDER BY tempcart_data_id DESC LIMIT 1', $context['session_id'] ) );
			if ( '' === $intent_id || false !== get_transient( 'wpec_cp_cancelled_' . md5( $intent_id ) ) ) {
				return;
			}
			$stripe = self::stripe();
			if ( ! $stripe || ! method_exists( $stripe, 'cancel_payment_intent' ) ) {
				return;
			}
			set_transient( 'wpec_cp_cancelled_' . md5( $intent_id ), 1, DAY_IN_SECONDS );
			$stripe->cancel_payment_intent( $intent_id );
		}

		// ------------------------------------------------------------------
		// Attack mode.
		// ------------------------------------------------------------------

		/**
		 * The stored attack state.
		 *
		 * @return array active, manual, started, until, last_signal, alerted
		 */
		public static function attack() {
			$state = get_option( self::ATTACK_OPTION, array() );
			$state = is_array( $state ) ? $state : array();
			return array_merge(
				array(
					'active'      => false,
					'manual'      => false,
					'started'     => 0,
					'until'       => 0,
					'last_signal' => 0,
					'alerted'     => 0,
					'ended'       => 0,
					'last_alert'  => 0,
				),
				$state
			);
		}

		/**
		 * Whether attack mode is on right now.
		 *
		 * @return bool
		 */
		public static function attack_active() {
			$state = self::attack();
			return ! empty( $state['active'] ) && (int) $state['until'] > time();
		}

		/**
		 * Whether an attack ended within the given seconds.
		 *
		 * @param int $seconds Window.
		 * @return bool
		 */
		private static function recent_attack_ended( $seconds ) {
			$state = self::attack();
			return (int) $state['ended'] > time() - (int) $seconds;
		}

		/**
		 * The store's usual declines per 15 minutes, over the last 30 days outside attacks ( cached an hour ).
		 *
		 * @return float
		 */
		public static function baseline() {
			$cached = get_transient( 'wpec_cp_baseline' );
			if ( false !== $cached ) {
				return (float) $cached;
			}
			global $wpdb;
			$count    = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_checkout_event WHERE event_type = %s AND in_attack = 0 AND created_at >= %s', 'decline', self::gmt( -30 * DAY_IN_SECONDS ) ) );
			$baseline = $count / ( 30 * 96 );
			set_transient( 'wpec_cp_baseline', (string) $baseline, HOUR_IN_SECONDS );
			return $baseline;
		}

		/**
		 * Start attack mode when declines in the last 15 minutes pass the floor and the multiple of the usual rate.
		 */
		public static function maybe_start_attack() {
			if ( self::attack_active() || ! self::ready() ) {
				return;
			}
			global $wpdb;
			$limits = self::limits();
			$recent = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_checkout_event WHERE event_type = %s AND created_at >= %s', 'decline', self::gmt( -self::SPIKE_SECONDS ) ) );
			if ( $recent < $limits['attack_count'] || $recent < $limits['attack_ratio'] * self::baseline() ) {
				return;
			}
			self::start_attack( false, $recent );
		}

		/**
		 * Turn attack mode on.
		 *
		 * @param bool $manual  Started by the owner ( no email ).
		 * @param int  $recent  Declines that triggered it.
		 */
		public static function start_attack( $manual = false, $recent = 0 ) {
			$state    = self::attack();
			$minutes  = self::settings()['attack_minutes'];
			$watching = self::is_watch();
			$new      = array_merge(
				$state,
				array(
					'active'      => ! $watching,
					'manual'      => (bool) $manual,
					'started'     => time(),
					'until'       => time() + $minutes * MINUTE_IN_SECONDS,
					'last_signal' => time(),
					'trigger'     => (int) $recent,
					'ended'       => 0,
				)
			);
			update_option( self::ATTACK_OPTION, $new, true );
			self::record( $watching ? 'would_stop' : 'attack_start', $manual ? 'manual' : 'spike', array( 'detail' => (string) (int) $recent ), self::empty_context() );
			if ( class_exists( 'ec_db' ) ) {
				$db = new ec_db();
				$db->insert_response( 0, 1, 'Checkout protection', $manual ? 'Extra checks turned on by the store.' : sprintf( 'Card-testing attack detected: %d declined payments in 15 minutes. Extra checks are on.', (int) $recent ) );
			}
			if ( ! $manual && ! $watching ) {
				self::send_alert( 'start', array( 'recent' => (int) $recent ) );
			}
			/**
			 * Attack mode started.
			 *
			 * @since 6.0.2
			 * @param bool $manual Started by the owner.
			 * @param int  $recent Declines in the last 15 minutes.
			 */
			do_action( 'wpeasycart_checkout_protection_attack_started', (bool) $manual, (int) $recent );
		}

		/**
		 * Push the end of attack mode out: the attack is still going.
		 */
		public static function extend_attack() {
			$state = self::attack();
			if ( empty( $state['active'] ) || ( time() - (int) $state['last_signal'] ) < MINUTE_IN_SECONDS ) {
				return;
			}
			$state['last_signal'] = time();
			$state['until']       = max( (int) $state['until'], time() + self::settings()['attack_minutes'] * MINUTE_IN_SECONDS );
			update_option( self::ATTACK_OPTION, $state, true );
		}

		/**
		 * Keep extra checks on for 24 hours ( the banner's button ).
		 */
		public static function hold_attack() {
			$state = self::attack();
			if ( empty( $state['active'] ) ) {
				self::start_attack( true );
				$state = self::attack();
			}
			$state['until'] = max( (int) $state['until'], time() + DAY_IN_SECONDS );
			update_option( self::ATTACK_OPTION, $state, true );
		}

		/**
		 * End attack mode when its time is up ( called lazily and from the hourly event ).
		 */
		public static function maybe_end_attack() {
			$state = self::attack();
			if ( ! empty( $state['active'] ) && (int) $state['until'] <= time() ) {
				self::end_attack( false );
			}
		}

		/**
		 * Turn attack mode off and send the all-clear.
		 *
		 * @param bool $by_owner Ended from the admin.
		 */
		public static function end_attack( $by_owner = false ) {
			$state = self::attack();
			if ( empty( $state['active'] ) ) {
				return;
			}
			$summary          = self::attack_summary( (int) $state['started'], time() );
			$state['active']  = false;
			$state['ended']   = time();
			$state['until']   = 0;
			$state['summary'] = $summary;
			update_option( self::ATTACK_OPTION, $state, true );
			self::record( 'attack_end', $by_owner ? 'manual' : 'quiet', array( 'detail' => wp_json_encode( $summary ) ), self::empty_context() );
			if ( class_exists( 'ec_db' ) ) {
				$db = new ec_db();
				$db->insert_response( 0, 0, 'Checkout protection', sprintf( 'Extra checks ended. Attempts stopped: %d. Payments that went through: %d.', (int) $summary['stopped'], (int) $summary['paid'] ) );
			}
			if ( empty( $state['manual'] ) ) {
				self::send_alert( 'end', $summary );
			}
			/**
			 * Attack mode ended.
			 *
			 * @since 6.0.2
			 * @param array $summary Counts for the attack.
			 */
			do_action( 'wpeasycart_checkout_protection_attack_ended', $summary );
		}

		/**
		 * Counts for a period: stopped, declined, paid, flagged, minutes.
		 *
		 * @param int $from Start timestamp.
		 * @param int $to   End timestamp.
		 * @return array
		 */
		public static function attack_summary( $from, $to ) {
			global $wpdb;
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT event_type, reason, COUNT(*) AS n FROM ec_checkout_event WHERE created_at >= %s AND created_at <= %s GROUP BY event_type, reason', gmdate( 'Y-m-d H:i:s', (int) $from ), gmdate( 'Y-m-d H:i:s', (int) $to ) ) );
			$out  = array(
				'stopped'  => 0,
				'declined' => 0,
				'paid'     => 0,
				'flagged'  => 0,
				'minutes'  => (int) max( 1, round( ( $to - $from ) / 60 ) ),
			);
			foreach ( (array) $rows as $row ) {
				if ( 'stop' === $row->event_type ) {
					$out['stopped'] += (int) $row->n;
				} elseif ( 'decline' === $row->event_type ) {
					$out['declined'] += (int) $row->n;
				} elseif ( 'paid' === $row->event_type ) {
					$out['paid'] += (int) $row->n;
					if ( 'possible_test' === $row->reason ) {
						$out['flagged'] += (int) $row->n;
					}
				}
			}
			return $out;
		}

		/**
		 * Keys for store-wide events that belong to no shopper.
		 *
		 * @return array
		 */
		private static function empty_context() {
			return array(
				'session_id'    => '',
				'session'       => '',
				'ip_raw'        => '',
				'ip'            => '',
				'email'         => '',
				'card'          => '',
				'ip_display'    => '',
				'email_display' => '',
				'card_display'  => '',
				'user_id'       => 0,
			);
		}

		// ------------------------------------------------------------------
		// Alerts.
		// ------------------------------------------------------------------

		/**
		 * Who gets the alerts: the addresses on the settings page only; else the store's order notification list
		 * ( Settings › Email ); else the WordPress admin email.
		 *
		 * @return string[]
		 */
		public static function recipients() {
			$custom = array_values( array_filter( array_map( 'sanitize_email', self::lines( str_replace( ';', ',', self::settings()['alert_emails'] ) ) ), 'is_email' ) );
			if ( $custom ) {
				return array_unique( $custom );
			}
			$bcc = array_values( array_filter( array_map( 'sanitize_email', self::lines( str_replace( ';', ',', stripslashes( (string) get_option( 'ec_option_bcc_email_addresses', '' ) ) ) ) ), 'is_email' ) );
			if ( $bcc ) {
				return array_unique( $bcc );
			}
			$admin = sanitize_email( (string) get_option( 'admin_email', '' ) );
			return is_email( $admin ) ? array( $admin ) : array();
		}

		/**
		 * Email the owner that an attack started or ended ( at most one alert an hour ).
		 *
		 * @param string $kind    start | end | test.
		 * @param array  $details recent ( start ), or the summary ( end ).
		 * @return bool Sent.
		 */
		public static function send_alert( $kind, $details = array() ) {
			if ( 'test' !== $kind && ! self::settings()['alerts'] ) {
				return false;
			}
			$state = self::attack();
			if ( 'start' === $kind && time() - (int) $state['last_alert'] < HOUR_IN_SECONDS ) {
				return false;
			}
			$to = self::recipients();
			if ( ! $to || ! class_exists( 'ec_email' ) ) {
				return false;
			}
			$store   = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
			$url     = admin_url( 'admin.php?page=wp-easycart-settings&subpage=checkout-protection' );
			$button  = __( 'See what\'s happening', 'wp-easycart' );
			$minutes = self::settings()['attack_minutes'];
			if ( 'end' === $kind ) {
				$subject = sprintf( /* translators: %s: store name. */ __( 'All clear: the card-testing attack on %s has stopped', 'wp-easycart' ), $store );
				$heading = __( 'The attack is over', 'wp-easycart' );
				$body    = '<p>' . esc_html( sprintf( /* translators: 1: minutes, 2: attempts stopped. */ __( 'The attack lasted %1$d minutes. We stopped %2$d payment attempts.', 'wp-easycart' ), (int) $details['minutes'], (int) $details['stopped'] ) ) . '</p>';
				if ( (int) $details['flagged'] > 0 ) {
					$body  .= '<p><strong>' . esc_html( sprintf( /* translators: %d: orders. */ _n( '%d payment went through and is tagged "Possible card test" in Orders. Refund it if you don\'t recognise the customer.', '%d payments went through and are tagged "Possible card test" in Orders. Refund them if you don\'t recognise the customers.', (int) $details['flagged'], 'wp-easycart' ), (int) $details['flagged'] ) ) . '</strong></p>';
					$url    = admin_url( 'admin.php?page=wp-easycart-orders&subpage=orders&health_filter=card_tests' );
					$button = __( 'Review tagged orders', 'wp-easycart' );
				}
				$body .= '<p>' . esc_html__( 'Extra checks are off again, and your checkout is back to normal.', 'wp-easycart' ) . '</p>';
			} else {
				$recent  = isset( $details['recent'] ) ? (int) $details['recent'] : 0;
				$subject = ( 'test' === $kind ) ? sprintf( /* translators: %s: store name. */ __( 'Test alert from %s: checkout protection', 'wp-easycart' ), $store ) : sprintf( /* translators: %s: store name. */ __( 'Bots are testing cards on %s. Extra checks are on.', 'wp-easycart' ), $store );
				$heading = __( 'We\'ve switched on extra checks at your checkout', 'wp-easycart' );
				$body    = '';
				if ( 'test' === $kind ) {
					$body .= '<p><em>' . esc_html__( 'This is a test. Your store is not under attack; this is what the real alert looks like.', 'wp-easycart' ) . '</em></p>';
				}
				$body .= '<p>' . esc_html( sprintf( /* translators: %d: declined payments. */ __( 'Declined payments on your store jumped to %d in 15 minutes. This is what card testing looks like: bots trying stolen card numbers to see which ones work.', 'wp-easycart' ), max( 1, $recent ) ) ) . '</p>';
				$body .= '<p><strong>' . esc_html__( 'What we did:', 'wp-easycart' ) . '</strong> ';
				if ( self::check_available() ) {
					$body .= esc_html( sprintf( /* translators: 1: tries per shopper, 2: minutes. */ __( 'every shopper now gets a quick human check, and each one gets %1$d tries before a pause. This lasts until %2$d minutes after the attack stops.', 'wp-easycart' ), self::settings()['attack_limit'], $minutes ) );
				} else {
					$body .= esc_html( sprintf( /* translators: 1: tries per shopper, 2: minutes. */ __( 'each shopper gets %1$d tries before a pause, and payments that didn\'t come from your checkout page are refused. This lasts until %2$d minutes after the attack stops. Add free Cloudflare Turnstile keys so real shoppers can get past with a quick check.', 'wp-easycart' ), self::settings()['attack_limit'], $minutes ) );
				}
				$body .= '</p><p><strong>' . esc_html__( 'What you should do:', 'wp-easycart' ) . '</strong></p><ol>';
				$body .= '<li>' . esc_html__( 'Look for small orders placed in the last few hours. Payments that went through during the attack are tagged "Possible card test". Refund them now, before the cardholders dispute them.', 'wp-easycart' ) . '</li>';
				$body .= '<li>' . esc_html__( 'If your cheapest item or a donation is being hit, raise its price or minimum for a day.', 'wp-easycart' ) . '</li>';
				$body .= '<li>' . esc_html__( 'Nothing else. We\'ll email you when it\'s over.', 'wp-easycart' ) . '</li></ol>';
			}
			$html = class_exists( 'wp_easycart_email_design' ) ? wp_easycart_email_design::wrap(
				$body,
				array(
					'heading'     => $heading,
					'button_url'  => $url,
					'button_text' => $button,
				)
			) : $body . '<p><a href="' . esc_url( $url ) . '">' . esc_html( $button ) . '</a></p>';
			$sent = ec_email::send(
				implode( ',', $to ),
				$subject,
				$html,
				array(
					'type'    => 'checkout_protection',
					'channel' => 'order',
				)
			);
			if ( 'start' === $kind ) {
				$state               = self::attack();
				$state['last_alert'] = time();
				update_option( self::ATTACK_OPTION, $state, true );
			}
			return (bool) $sent;
		}

		// ------------------------------------------------------------------
		// Code guessing ( gift cards and coupons ).
		// ------------------------------------------------------------------

		/**
		 * Whether the shopper may try another code.
		 *
		 * @param string $kind giftcard | coupon.
		 * @return true|WP_Error
		 */
		public static function code_check( $kind ) {
			if ( ! self::is_on() || ! self::settings()['code_limits'] || self::is_staff() ) {
				return true;
			}
			$context = self::context();
			if ( self::is_allowed( $context ) ) {
				return true;
			}
			$now = time();
			foreach ( array(
				'session_key' => 'session',
				'ip_key'      => 'ip',
			) as $column => $name ) {
				$times = self::failures( $column, $context[ $name ], $now - self::CODE_WINDOW - self::CODE_PAUSE, self::CODE_LIMIT, 'code_fail' );
				if ( count( $times ) >= self::CODE_LIMIT && ( $times[0] - $times[ self::CODE_LIMIT - 1 ] ) <= self::CODE_WINDOW && $times[0] + self::CODE_PAUSE > $now ) {
					self::record( self::is_watch() ? 'would_stop' : 'code_stop', $kind, array( 'place' => 'code' ), $context );
					if ( self::is_watch() ) {
						return true;
					}
					return new WP_Error( 'protection_code', self::text( 'code_paused' ) );
				}
			}
			return true;
		}

		/**
		 * Count a wrong code.
		 *
		 * @param string $kind giftcard | coupon.
		 */
		public static function code_failed( $kind ) {
			if ( self::is_on() && self::settings()['code_limits'] && ! self::is_staff() ) {
				self::record( 'code_fail', $kind, array( 'place' => 'code' ) );
			}
		}

		// ------------------------------------------------------------------
		// Checkout integration filters.
		// ------------------------------------------------------------------

		/**
		 * Filter wpeasycart_checkout_order_errors: the one-page checkout's pre-payment check asks the gate too.
		 *
		 * @param string[]    $errors   Codes.
		 * @param ec_cartpage $cartpage Checkout.
		 * @return string[]
		 */
		public static function filter_order_errors( $errors, $cartpage = null ) {
			if ( ! empty( $errors ) || ! is_object( $cartpage ) || ! isset( $cartpage->order_totals->grand_total ) || (float) $cartpage->order_totals->grand_total <= 0 ) {
				return $errors;
			}
			// Bank transfer and redirect gateways charge no card here, so a paused shopper can still pay that way.
			// PayPal's buttons pass their own gate when the PayPal order is created.
			if ( method_exists( $cartpage, 'get_selected_payment_method' ) && in_array( (string) $cartpage->get_selected_payment_method(), array( 'manual_bill', 'third_party' ), true ) ) {
				return $errors;
			}
			$result = self::check(
				'checkout',
				array(
					'gateway' => (string) get_option( 'ec_option_payment_process_method' ),
					'amount'  => (float) $cartpage->order_totals->grand_total,
				)
			);
			if ( is_wp_error( $result ) ) {
				$errors[] = $result->get_error_code();
			}
			return $errors;
		}

		/**
		 * Filter wpeasycart_cart_errors: notices for the protection codes.
		 *
		 * @param array $notes code => text.
		 * @return array
		 */
		public static function filter_cart_errors( $notes ) {
			$notes = is_array( $notes ) ? $notes : array();
			$pause = self::is_on() ? self::pause_for( self::context() ) : false;

			$notes['protection_blocked'] = self::text( 'declined' );
			$notes['protection_paused']  = self::paused_message( $pause ? $pause['until'] : 0 );
			$notes['protection_check']   = self::text( 'check_needed' );
			$notes['protection_page']    = self::text( 'reload' );
			if ( self::settings()['simple_declines'] && isset( $notes['payment_failed'] ) ) {
				$notes['payment_failed'] = self::text( 'declined' );
				$notes['card_error']     = self::text( 'declined' );
			}
			return $notes;
		}

		/**
		 * Filter wpeasycart_payment_error_message: the gateway's decline text, shown to the shopper.
		 *
		 * @param string $message Gateway text.
		 * @return string
		 */
		public static function filter_payment_error( $message ) {
			/* Internal codes ( stock_error, gift-card-error… ) are left alone: scripts may act on them. */
			if ( '' === trim( (string) $message ) || preg_match( '/^[a-z0-9_\-]+$/', (string) $message ) ) {
				return $message;
			}
			if ( self::settings()['simple_declines'] || self::is_fraud_reason( $message ) ) {
				return self::text( 'declined' );
			}
			return $message;
		}

		/**
		 * Filter wpeasycart_cart_card_error: the saved Stripe decline text on the classic checkout.
		 *
		 * @param string $message Saved text.
		 * @return string
		 */
		public static function filter_card_error( $message ) {
			if ( '' === trim( (string) $message ) ) {
				return $message;
			}
			return ( self::settings()['simple_declines'] || self::is_fraud_reason( $message ) ) ? self::text( 'declined' ) : $message;
		}

		/**
		 * Reasons that are never shown to a shopper, whatever the setting.
		 *
		 * @param string $message Text or code.
		 * @return bool
		 */
		private static function is_fraud_reason( $message ) {
			return (bool) preg_match( '/fraud|stolen|lost[\s_]card|pickup[\s_]card|pick[\s_]up|merchant[\s_]blacklist|restricted[\s_]card|security[\s_]violation|blocked/i', (string) $message );
		}

		/**
		 * Filter wpeasycart_abandoned_cart_capture_skip: no reminder emails for sessions this class stopped.
		 *
		 * @param bool   $skip       Skip.
		 * @param string $session_id Session.
		 * @return bool
		 */
		public static function skip_abandoned( $skip, $session_id ) {
			if ( $skip || ! self::ready() ) {
				return $skip;
			}
			global $wpdb;
			$key = self::key( 's', $session_id );
			return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT event_id FROM ec_checkout_event WHERE session_key = %s AND event_type IN ( %s, %s ) LIMIT 1', $key, 'stop', 'code_stop' ) );
		}

		// ------------------------------------------------------------------
		// Wording.
		// ------------------------------------------------------------------

		/**
		 * Shopper-facing text from the language file section checkout_protection, with a built-in default.
		 *
		 * @param string $key Text key.
		 * @return string
		 */
		public static function text( $key ) {
			$defaults = array(
				'declined'     => __( 'Your payment wasn\'t approved. Please check your card details or try a different card.', 'wp-easycart' ),
				'paused_title' => __( 'Payments are paused for a little while.', 'wp-easycart' ),
				'paused'       => __( 'For your security, we\'ve paused payments from this device after several tries. Please try again after [time], or contact us at [email] and we\'ll help.', 'wp-easycart' ),
				'paused_soon'  => __( 'For your security, we\'ve paused payments from this device after several tries. Please try again a little later, or contact us at [email] and we\'ll help.', 'wp-easycart' ),
				'check_title'  => __( 'Quick security check', 'wp-easycart' ),
				'check_needed' => __( 'Please complete the security check below, then place your order again.', 'wp-easycart' ),
				'check_failed' => __( 'The security check didn\'t work. Please try it again.', 'wp-easycart' ),
				'checking'     => __( 'Checking…', 'wp-easycart' ),
				'reload'       => __( 'Please reload this page and try again.', 'wp-easycart' ),
				'code_paused'  => __( 'Too many codes were tried. Please wait an hour before trying another code.', 'wp-easycart' ),
			);
			$text     = null;
			if ( function_exists( 'wp_easycart_language' ) ) {
				$text = wp_easycart_language()->get_text( 'checkout_protection', $key );
			}
			if ( null === $text || '' === trim( (string) $text ) ) {
				$text = isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
			}
			return (string) $text;
		}

		/**
		 * The paused message with the time and the store's contact email.
		 *
		 * @param int $until Pause end ( 0 when unknown ).
		 * @return string
		 */
		public static function paused_message( $until ) {
			/* The order "from" address may be "Store <orders@store.com>"; the shipped placeholder never counts. */
			$email = '';
			if ( preg_match( '/[^\s<>"\']+@[^\s<>"\']+/', stripslashes( (string) get_option( 'ec_option_order_from_email', '' ) ), $match ) ) {
				$email = sanitize_email( $match[0] );
			}
			if ( ! is_email( $email ) || 'youremail@url.com' === strtolower( $email ) ) {
				$email = sanitize_email( (string) get_option( 'admin_email', '' ) );
			}
			/**
			 * The email shoppers are told to contact when their payments are paused.
			 *
			 * @since 6.0.2
			 * @param string $email Email.
			 */
			$email = (string) apply_filters( 'wpeasycart_checkout_protection_contact_email', $email );
			$text  = ( $until > time() ) ? self::text( 'paused' ) : self::text( 'paused_soon' );
			return str_replace( array( '[time]', '[email]' ), array( $until > time() ? self::local_time( $until ) : '', $email ), $text );
		}

		// ------------------------------------------------------------------
		// Storefront script and its calls.
		// ------------------------------------------------------------------

		/**
		 * Enqueue the storefront script ( cart and pay pages; called from wp_easycart_load_cart_js() ).
		 */
		public static function enqueue() {
			if ( ! self::is_on() || self::is_watch() ) {
				return;
			}
			$settings = self::settings();
			wp_enqueue_script( 'wpeasycart_checkout_protection', plugins_url( 'wp-easycart/design/theme/base-responsive-v3/ec-checkout-protection.js', EC_PLUGIN_DIRECTORY ), array( 'jquery' ), EC_CURRENT_VERSION, true );
			wp_localize_script(
				'wpeasycart_checkout_protection',
				'wpeasycart_protection',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( self::NONCE ),
					'provider' => $settings['provider'],
					'site_key' => self::check_available() ? self::site_key() : '',
					'simple'   => $settings['simple_declines'] ? 1 : 0,
					'text'     => array(
						'check_title'  => self::text( 'check_title' ),
						'check_needed' => self::text( 'check_needed' ),
						'check_failed' => self::text( 'check_failed' ),
						'checking'     => self::text( 'checking' ),
						'paused_title' => self::text( 'paused_title' ),
						'declined'     => self::text( 'declined' ),
					),
				)
			);
		}

		/**
		 * AJAX: what the payment step should show. Also counts as a checkout page visit.
		 */
		public static function ajax_state() {
			if ( ! wp_easycart_checkout_protection_guard() ) {
				wp_send_json_error();
			}
			if ( function_exists( 'wpeasycart_session' ) ) {
				wpeasycart_session()->handle_session();
			}
			self::mark_seen();
			wp_send_json_success( self::state() );
		}

		/**
		 * AJAX: verify a human-check token and remember the pass for the session.
		 */
		public static function ajax_verify() {
			if ( ! wp_easycart_checkout_protection_guard() ) {
				wp_send_json_error();
			}
			if ( function_exists( 'wpeasycart_session' ) ) {
				wpeasycart_session()->handle_session();
			}
			$context = self::context();
			$before  = self::state();
			$token   = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
			$result  = self::verify_token( $token );
			if ( 'fail' === $result ) {
				self::record( 'check_fail', 'invalid', array( 'place' => 'checkout' ), $context );
				if ( self::attack_active() ) {
					self::extend_attack();
				}
				wp_send_json_error( array( 'message' => self::text( 'check_failed' ) ) );
			}
			self::mark_passed( $context );
			if ( 'pass' === $result ) {
				self::record( 'check_pass', '', array( 'place' => 'checkout' ), $context );
			}
			wp_send_json_success(
				array(
					'reload' => ( 'check' === $before['state'] && null !== self::stripe() ) ? 1 : 0,
				)
			);
		}

		/**
		 * AJAX: the browser saw a card error from Stripe; look the session's intent up with Stripe and count it.
		 */
		public static function ajax_report() {
			if ( ! wp_easycart_checkout_protection_guard() ) {
				wp_send_json_error();
			}
			if ( function_exists( 'wpeasycart_session' ) ) {
				wpeasycart_session()->handle_session();
			}
			$context  = self::context();
			$declined = false;
			if ( '' !== $context['session'] && false === get_transient( 'wpec_cp_report_' . $context['session'] ) ) {
				set_transient( 'wpec_cp_report_' . $context['session'], 1, 5 );
				$intent_id = isset( $GLOBALS['ec_cart_data']->cart_data->stripe_paymentintent_id ) ? (string) $GLOBALS['ec_cart_data']->cart_data->stripe_paymentintent_id : '';
				if ( '' !== $intent_id ) {
					$declined = self::record_stripe_failure( $intent_id, $context );
				}
				/* The My Account card form checks cards with a setup intent instead. */
				$setup_intent_id = (string) get_transient( 'wpec_cp_seti_' . $context['session'] );
				if ( ! $declined && '' !== $setup_intent_id ) {
					$declined = self::record_stripe_setup_failure( $setup_intent_id, $context );
				}
			}
			wp_send_json_success( array_merge( self::state(), array( 'declined' => $declined ? 1 : 0 ) ) );
		}

		// ------------------------------------------------------------------
		// Housekeeping and admin helpers.
		// ------------------------------------------------------------------

		/**
		 * Hourly: end a finished attack, and once a day delete events past the retention period.
		 */
		public static function tick() {
			self::maybe_end_attack();
			if ( ! self::ready() ) {
				return;
			}
			$last = (int) get_option( 'wp_easycart_checkout_protection_pruned', 0 );
			if ( time() - $last < 20 * HOUR_IN_SECONDS ) {
				return;
			}
			update_option( 'wp_easycart_checkout_protection_pruned', time(), false );
			global $wpdb;
			$wpdb->query( $wpdb->prepare( 'DELETE FROM ec_checkout_event WHERE created_at < %s', self::gmt( -self::settings()['retention'] * DAY_IN_SECONDS ) ) );
		}

		/**
		 * Unpause one key ( from the Activity list or the paused-now table ).
		 *
		 * @param string $column session_key, ip_key, email_key or card_key.
		 * @param string $key    Key.
		 * @return bool
		 */
		public static function clear_key( $column, $key ) {
			if ( ! in_array( $column, array( 'session_key', 'ip_key', 'email_key', 'card_key' ), true ) || ! preg_match( '/^[a-f0-9]{24}$/', (string) $key ) ) {
				return false;
			}
			$context                    = self::empty_context();
			$map                        = array(
				'session_key' => 'session',
				'ip_key'      => 'ip',
				'email_key'   => 'email',
				'card_key'    => 'card',
			);
			$context[ $map[ $column ] ] = $key;
			return self::record( 'cleared', 'owner', array(), $context ) > 0;
		}

		/**
		 * Unpause everyone.
		 *
		 * @return int Keys cleared.
		 */
		public static function clear_all() {
			$cleared = 0;
			foreach ( self::paused_now() as $row ) {
				if ( self::clear_key( $row['column'], $row['key'] ) ) {
					++$cleared;
				}
			}
			return $cleared;
		}

		/**
		 * Keys that are paused now, for the settings page ( newest failures first ).
		 *
		 * @param int $limit Rows.
		 * @return array[] column, key, display, until, failures
		 */
		public static function paused_now( $limit = 25 ) {
			if ( ! self::ready() ) {
				return array();
			}
			global $wpdb;
			$out  = array();
			$seen = array();
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT session_key, ip_key, email_key, card_key, ip_display, email_display, card_display FROM ec_checkout_event WHERE event_type = %s AND created_at >= %s ORDER BY created_at DESC LIMIT 400', 'decline', self::gmt( -DAY_IN_SECONDS ) ) );
			foreach ( (array) $rows as $row ) {
				foreach ( array(
					'session_key' => '',
					'ip_key'      => 'ip_display',
					'email_key'   => 'email_display',
					'card_key'    => 'card_display',
				) as $column => $display_column ) {
					$key = (string) $row->{$column};
					if ( '' === $key || isset( $seen[ $column . $key ] ) ) {
						continue;
					}
					$seen[ $column . $key ]     = true;
					$context                    = self::empty_context();
					$map                        = array(
						'session_key' => 'session',
						'ip_key'      => 'ip',
						'email_key'   => 'email',
						'card_key'    => 'card',
					);
					$context[ $map[ $column ] ] = $key;
					$pause                      = self::pause_for( $context );
					if ( ! $pause ) {
						continue;
					}
					$out[] = array(
						'column'  => $column,
						'key'     => $key,
						'display' => '' !== $display_column ? (string) $row->{$display_column} : __( 'A browser session', 'wp-easycart' ),
						'until'   => (int) $pause['until'],
					);
					if ( count( $out ) >= $limit ) {
						return $out;
					}
				}
			}
			return $out;
		}

		/**
		 * Counts for the status card: last 7 days, plus the daily series for 14 days.
		 *
		 * @return array stopped, declined, attacks, caught ( paused shoppers who later paid ), series[ day => [ stopped, declined ] ]
		 */
		public static function status_numbers() {
			$out = array(
				'stopped'  => 0,
				'declined' => 0,
				'attacks'  => 0,
				'caught'   => 0,
				'series'   => array(),
			);
			if ( ! self::ready() ) {
				return $out;
			}
			global $wpdb;
			$week = self::gmt( -7 * DAY_IN_SECONDS );
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT event_type, COUNT(*) AS n FROM ec_checkout_event WHERE created_at >= %s GROUP BY event_type', $week ) );
			foreach ( (array) $rows as $row ) {
				if ( in_array( $row->event_type, array( 'stop', 'code_stop' ), true ) ) {
					$out['stopped'] += (int) $row->n;
				} elseif ( 'decline' === $row->event_type ) {
					$out['declined'] += (int) $row->n;
				} elseif ( 'attack_start' === $row->event_type ) {
					$out['attacks'] += (int) $row->n;
				}
			}
			/* Real customers caught: a session that was stopped and later paid. */
			$out['caught'] = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT( DISTINCT p.session_key ) FROM ec_checkout_event p INNER JOIN ec_checkout_event s ON s.session_key = p.session_key AND s.event_type = %s AND s.event_id < p.event_id WHERE p.event_type = %s AND p.session_key <> %s AND p.created_at >= %s', 'stop', 'paid', '', $week ) );
			$series        = array();
			for ( $i = 13; $i >= 0; $i-- ) {
				$series[ gmdate( 'Y-m-d', time() - $i * DAY_IN_SECONDS ) ] = array( 0, 0 );
			}
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT DATE( created_at ) AS d, event_type, COUNT(*) AS n FROM ec_checkout_event WHERE created_at >= %s AND event_type IN ( %s, %s, %s ) GROUP BY d, event_type', self::gmt( -14 * DAY_IN_SECONDS ), 'stop', 'code_stop', 'decline' ) );
			foreach ( (array) $rows as $row ) {
				if ( isset( $series[ $row->d ] ) ) {
					$series[ $row->d ][ 'decline' === $row->event_type ? 1 : 0 ] += (int) $row->n;
				}
			}
			$out['series'] = $series;
			return $out;
		}

		/**
		 * Activity rows for the settings page.
		 *
		 * @param string $filter all | stop | dec | pass | att.
		 * @param int    $limit  Rows.
		 * @param int    $offset Offset.
		 * @return array rows, total
		 */
		public static function activity( $filter = 'all', $limit = 25, $offset = 0 ) {
			if ( ! self::ready() ) {
				return array(
					'rows'  => array(),
					'total' => 0,
				);
			}
			global $wpdb;
			/* Literal event lists per filter ( internal names, never input ). */
			$where = array(
				'all'  => "event_type IN ( 'stop', 'would_stop', 'decline', 'paid', 'check_pass', 'check_fail', 'attack_start', 'attack_end', 'code_stop', 'cleared' )",
				'stop' => "event_type IN ( 'stop', 'would_stop', 'code_stop' )",
				'dec'  => "event_type IN ( 'decline', 'paid' )",
				'pass' => "event_type IN ( 'check_pass', 'check_fail' )",
				'att'  => "event_type IN ( 'attack_start', 'attack_end' )",
			);
			$where = isset( $where[ $filter ] ) ? $where[ $filter ] : $where['all'];
			$total = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_checkout_event WHERE ' . $where ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- a literal clause from the list above.
			$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ec_checkout_event WHERE ' . $where . ' ORDER BY created_at DESC, event_id DESC LIMIT %d OFFSET %d', (int) $limit, (int) $offset ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- a literal clause from the list above.
			return array(
				'rows'  => (array) $rows,
				'total' => $total,
			);
		}

		/**
		 * Add a hashed entry to the block list ( "Block" from the Activity list, where only the hash is known ).
		 *
		 * @param string $key     Key.
		 * @param string $display What the owner sees next to it.
		 * @return bool
		 */
		public static function block_key( $key, $display ) {
			if ( ! preg_match( '/^[a-f0-9]{24}$/', (string) $key ) ) {
				return false;
			}
			$list = self::lines( (string) get_option( self::OPT . 'block_list', '' ) );
			foreach ( $list as $line ) {
				if ( 0 === strpos( $line, 'hash:' . $key ) ) {
					return true;
				}
			}
			$list[] = 'hash:' . $key . ( '' !== $display ? ' # ' . sanitize_text_field( $display ) : '' );
			update_option( self::OPT . 'block_list', implode( "\n", $list ) );
			self::flush_settings();
			return true;
		}
	}

	wp_easycart_checkout_guard::init();

endif;
