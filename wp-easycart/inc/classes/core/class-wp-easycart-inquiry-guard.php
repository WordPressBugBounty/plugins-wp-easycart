<?php
/**
 * WP EasyCart — Abuse protection for the product inquiry form.
 *
 * Inquiry-mode products show a name / email / message form that emails the store
 * ( inc/classes/cart/ec_cartpage.php: process_send_inquiry() and send_inquiry() ).
 * Before 6.0.0 the only optional protection was Google reCAPTCHA v2, which every
 * shopper sees. This class adds layered, invisible checks that work with or without
 * reCAPTCHA and never show a challenge to a real shopper:
 *
 *   1. Honeypot field + minimum time-to-submit  ( ec_option_inquiry_honeypot,
 *                                                 ec_option_inquiry_min_seconds )
 *   2. Rate limits per IP, per email address and per product, kept in transients
 *      ( ec_option_inquiry_rate_limit and its three counts )
 *   3. Optional "must be signed in"             ( ec_option_inquiry_require_login )
 *   4. Content checks: message length, number of links, merchant word / domain
 *      blocklist, and rejection of forged or mismatched fields
 *      ( ec_option_inquiry_content_checks and its children )
 *   5. Blocked attempts logged to Settings › Log entries, with the reason only
 *      ( ec_option_inquiry_log_blocked )
 *
 * reCAPTCHA is untouched and independent: these checks run first, so an obvious
 * spam post never costs a round trip to Google, and a store with reCAPTCHA off
 * still gets every protection here.
 *
 * Defaults are deliberately generous so existing stores keep working: honeypot and
 * the four-second timing check on, rate limiting on at 10 per IP / 5 per email /
 * 3 per product an hour, content checks on at 4000 characters and 3 links, blocklist
 * empty, sign-in not required.
 *
 * Privacy: nothing shopper-identifying is stored. Counters are keyed by a salted
 * hash of the IP / email, and a blocked-attempt log line carries the reason, the
 * product, a masked email and a short hash of the IP, never the address itself.
 *
 * Template compatibility: a theme that overrides the storefront layout and does not
 * print the hidden fields simply skips the honeypot and timing checks — a missing
 * field is never treated as a failure, so old template copies keep working.
 *
 * @package wp-easycart
 * @since   6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_inquiry_guard' ) ) :

	/**
	 * Inquiry form abuse protection ( static ).
	 */
	class wp_easycart_inquiry_guard {

		/** Hidden field carrying the moment the form was rendered. */
		const TS_FIELD = 'ec_inquiry_started';

		/** Hidden field carrying the signature of TS_FIELD. */
		const TS_KEY_FIELD = 'ec_inquiry_check';

		/** Honeypot: a real shopper never sees it, so it must come back empty. */
		const HP_FIELD = 'ec_inquiry_confirm_url';

		/** Query args used to report a rejection back on the product page. */
		const ERROR_ARG   = 'ec_inquiry_error';
		const PRODUCT_ARG = 'ec_inquiry_ref';

		/** Source column value used for blocked attempts in ec_response. */
		const LOG_SOURCE = 'INQUIRY BLOCKED';

		/** Transient prefix for the rate-limit counters. */
		const COUNTER_PREFIX = 'wpec_inq_';

		/**
		 * Every reason this class can reject for. Also the whitelist used when a
		 * reason arrives back as a query argument.
		 *
		 * @return array
		 */
		public static function reasons() {
			return array(
				'login_required',
				'too_fast',
				'honeypot',
				'too_long',
				'too_many_links',
				'blocked_content',
				'rate_limit',
				'rate_limit_product',
				'captcha',
				'invalid',
			);
		}

		/**
		 * Resolved settings, each filterable so a site can override the stored option.
		 *
		 * @return array
		 */
		public static function settings() {
			$settings = array(
				'honeypot'       => self::option_on( 'ec_option_inquiry_honeypot', true ),
				'min_seconds'    => self::option_int( 'ec_option_inquiry_min_seconds', 4 ),
				'rate_limit'     => self::option_on( 'ec_option_inquiry_rate_limit', true ),
				'per_ip'         => self::option_int( 'ec_option_inquiry_max_per_ip', 10 ),
				'per_email'      => self::option_int( 'ec_option_inquiry_max_per_email', 5 ),
				'per_product'    => self::option_int( 'ec_option_inquiry_max_per_product', 3 ),
				'window'         => HOUR_IN_SECONDS,
				'require_login'  => self::option_on( 'ec_option_inquiry_require_login', false ),
				'content_checks' => self::option_on( 'ec_option_inquiry_content_checks', true ),
				'max_length'     => self::option_int( 'ec_option_inquiry_max_length', 4000 ),
				'max_links'      => self::option_int( 'ec_option_inquiry_max_links', 3 ),
				'blocklist'      => self::blocklist(),
				'log_blocked'    => self::option_on( 'ec_option_inquiry_log_blocked', true ),
			);
			$settings = apply_filters( 'wpeasycart_inquiry_guard_settings', $settings );
			return is_array( $settings ) ? $settings : array();
		}

		/**
		 * A stored toggle, falling back to the shipped default when it was never saved.
		 *
		 * @param string $key      Option name.
		 * @param bool   $shipped  Value to use when the option does not exist yet.
		 * @return bool
		 */
		private static function option_on( $key, $shipped ) {
			$value = get_option( $key, null );
			if ( null === $value ) {
				return (bool) $shipped;
			}
			return ( '1' === (string) $value );
		}

		/**
		 * A stored whole number, falling back to the shipped default when it was never saved.
		 *
		 * @param string $key      Option name.
		 * @param int    $shipped  Value to use when the option is missing or blank.
		 * @return int
		 */
		private static function option_int( $key, $shipped ) {
			$value = get_option( $key, null );
			if ( null === $value || false === $value || '' === trim( (string) $value ) ) {
				return (int) $shipped;
			}
			return (int) $value;
		}

		/**
		 * Merchant blocklist: one word, phrase or domain per line ( commas also accepted ).
		 *
		 * @return array Lower-cased terms.
		 */
		private static function blocklist() {
			$raw = (string) get_option( 'ec_option_inquiry_blocklist', '' );
			$raw = str_replace( array( "\r\n", "\r", ',' ), "\n", $raw );
			$out = array();
			foreach ( explode( "\n", $raw ) as $term ) {
				$term = trim( strtolower( $term ) );
				if ( '' !== $term ) {
					$out[] = $term;
				}
			}
			$out = apply_filters( 'wpeasycart_inquiry_blocklist', array_values( array_unique( $out ) ) );
			return is_array( $out ) ? $out : array();
		}

		/**
		 * Is the visitor signed in, either to WordPress or to an EasyCart account?
		 *
		 * @return bool
		 */
		public static function is_signed_in() {
			if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
				return true;
			}
			if ( isset( $GLOBALS['ec_user'] ) && is_object( $GLOBALS['ec_user'] ) && ! empty( $GLOBALS['ec_user']->user_id ) ) {
				return true;
			}
			return false;
		}

		/**
		 * The visitor's public IP address, or '' when there is none to key a limit on.
		 *
		 * Behind Cloudflare or a proxy that leaves REMOTE_ADDR as its own address, every shopper
		 * would otherwise share one counter and the per-IP limits would lock the whole store out.
		 * So: CF-Connecting-IP when present, otherwise the first hop of X-Forwarded-For when
		 * REMOTE_ADDR is a private or reserved address, otherwise REMOTE_ADDR. The
		 * wpeasycart_inquiry_client_ip filter still has the final say ( a site behind another
		 * proxy can point at its own header ). Whatever comes out, a value that is not a public
		 * IP returns '' so the IP-keyed checks are skipped and only the per-email limit applies.
		 * Never stored anywhere in the clear.
		 *
		 * @since 6.0.0 proxy headers and the public-address rule.
		 *
		 * @return string
		 */
		private static function client_ip() {
			$remote = self::server_ip( 'REMOTE_ADDR' );
			$ip     = $remote;

			$cloudflare = self::server_ip( 'HTTP_CF_CONNECTING_IP' );
			if ( '' !== $cloudflare && false !== filter_var( $cloudflare, FILTER_VALIDATE_IP ) ) {
				$ip = $cloudflare;
			} elseif ( ! self::is_public_ip( $remote ) && isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
				$forwarded = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
				$first_hop = trim( (string) strtok( $forwarded, ',' ) );
				if ( self::is_public_ip( $first_hop ) ) {
					$ip = $first_hop;
				}
			}

			$ip = apply_filters( 'wpeasycart_inquiry_client_ip', $ip, $remote );
			if ( ! is_string( $ip ) || ! self::is_public_ip( trim( $ip ) ) ) {
				return '';
			}
			return trim( $ip );
		}

		/**
		 * One $_SERVER address value, sanitised.
		 *
		 * @since 6.0.0
		 *
		 * @param string $key $_SERVER key.
		 * @return string
		 */
		private static function server_ip( $key ) {
			if ( ! isset( $_SERVER[ $key ] ) ) {
				return '';
			}
			return trim( sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) );
		}

		/**
		 * Whether a value is a valid IP outside the private and reserved ranges.
		 *
		 * @since 6.0.0
		 *
		 * @param string $ip Candidate address.
		 * @return bool
		 */
		private static function is_public_ip( $ip ) {
			if ( ! is_string( $ip ) || '' === $ip ) {
				return false;
			}
			return false !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
		}

		/**
		 * Salted, truncated hash used as a counter key and as the log reference.
		 *
		 * @param string $value  Value to hash.
		 * @param int    $length Characters to keep.
		 * @return string
		 */
		private static function fingerprint( $value, $length = 20 ) {
			if ( '' === (string) $value ) {
				return '';
			}
			return substr( wp_hash( 'wpec-inquiry|' . strtolower( (string) $value ) ), 0, (int) $length );
		}

		// ----------------------------------------------------------------
		// Form rendering.
		// ----------------------------------------------------------------

		/**
		 * Prints the hidden protection fields inside the inquiry form. Safe to call
		 * even when every check is off; the fields are then simply not needed.
		 *
		 * @param int $product_id Product the form belongs to.
		 */
		public static function print_fields( $product_id ) {
			$settings   = self::settings();
			$product_id = (int) $product_id;
			$stamp      = time();

			echo '<input type="hidden" name="' . esc_attr( self::TS_FIELD ) . '" value="' . esc_attr( $stamp ) . '" />';
			echo '<input type="hidden" name="' . esc_attr( self::TS_KEY_FIELD ) . '" value="' . esc_attr( self::stamp_key( $stamp, $product_id ) ) . '" />';

			if ( empty( $settings['honeypot'] ) ) {
				return;
			}
			echo '<div class="ec_inquiry_hp" aria-hidden="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;">';
			echo '<label for="' . esc_attr( self::HP_FIELD . '_' . $product_id ) . '">' . esc_html__( 'Leave this field empty', 'wp-easycart' ) . '</label>';
			echo '<input type="text" name="' . esc_attr( self::HP_FIELD ) . '" id="' . esc_attr( self::HP_FIELD . '_' . $product_id ) . '" value="" tabindex="-1" autocomplete="off" />';
			echo '</div>';
		}

		/**
		 * Signature for a rendered-at timestamp, so the value cannot be back-dated.
		 *
		 * @param int $stamp      Unix time the form was printed.
		 * @param int $product_id Product id.
		 * @return string
		 */
		private static function stamp_key( $stamp, $product_id ) {
			return wp_hash( 'wpec-inquiry-stamp|' . (int) $stamp . '|' . (int) $product_id );
		}

		/**
		 * Prints the rejection message after a blocked submission, and the standing
		 * "please sign in" hint when the store requires an account.
		 *
		 * @param int $product_id Product the form belongs to.
		 */
		public static function print_notice( $product_id ) {
			$product_id = (int) $product_id;
			$settings   = self::settings();

			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only display of the reason this shopper's own submission was refused.
			$reason = isset( $_GET[ self::ERROR_ARG ] ) ? sanitize_key( wp_unslash( $_GET[ self::ERROR_ARG ] ) ) : '';
			$ref    = isset( $_GET[ self::PRODUCT_ARG ] ) ? (int) $_GET[ self::PRODUCT_ARG ] : 0;
			// phpcs:enable WordPress.Security.NonceVerification.Recommended

			if ( '' !== $reason && in_array( $reason, self::reasons(), true ) && ( 0 === $ref || $ref === $product_id ) ) {
				echo '<div class="ec_cart_error ec_inquiry_error_notice"><div>' . esc_html( self::message( $reason, $settings ) ) . '</div></div>';
			}

			if ( ! empty( $settings['require_login'] ) && ! self::is_signed_in() ) {
				echo '<div class="ec_inquiry_login_notice">' . esc_html__( 'Please sign in to your account before sending an inquiry about this product.', 'wp-easycart' ) . '</div>';
			}
		}

		/**
		 * Shopper-facing text for a rejection reason. Deliberately vague where telling
		 * the truth would only help a bot tune its next attempt.
		 *
		 * @param string $reason   One of reasons().
		 * @param array  $settings Resolved settings.
		 * @return string
		 */
		public static function message( $reason, $settings = null ) {
			if ( null === $settings ) {
				$settings = self::settings();
			}
			switch ( $reason ) {
				case 'login_required':
					$text = __( 'Please sign in to your account before sending an inquiry about this product.', 'wp-easycart' );
					break;
				case 'too_fast':
					$text = __( 'That was sent a little too quickly. Please try again.', 'wp-easycart' );
					break;
				case 'too_long':
					/* translators: %s: maximum number of characters allowed in an inquiry message. */
					$text = sprintf( __( 'Your message is too long. Please keep it under %s characters and try again.', 'wp-easycart' ), number_format_i18n( isset( $settings['max_length'] ) ? (int) $settings['max_length'] : 4000 ) );
					break;
				case 'too_many_links':
					$text = __( 'Please remove the web links from your message and try again.', 'wp-easycart' );
					break;
				case 'blocked_content':
					$text = __( 'Your message could not be sent. Please reword it and try again.', 'wp-easycart' );
					break;
				case 'rate_limit':
					$text = __( 'You have sent several inquiries recently. Please try again later.', 'wp-easycart' );
					break;
				case 'rate_limit_product':
					$text = __( 'You have already sent an inquiry about this product recently. We will be in touch soon.', 'wp-easycart' );
					break;
				case 'captcha':
					$text = __( 'The security check did not pass. Please complete it and try again.', 'wp-easycart' );
					break;
				default:
					$text = __( 'Your inquiry could not be sent. Please check your details and try again.', 'wp-easycart' );
					break;
			}
			return (string) apply_filters( 'wpeasycart_inquiry_error_message', $text, $reason, $settings );
		}

		// ----------------------------------------------------------------
		// Checking.
		// ----------------------------------------------------------------

		/**
		 * Runs every enabled check over one submission.
		 *
		 * @param object|false $product Product row the inquiry is about, or false when it could not be found.
		 * @param string       $name    Sanitized shopper name.
		 * @param string       $email   Sanitized shopper email.
		 * @param string       $message Sanitized shopper message.
		 * @return string '' when the inquiry may be sent, otherwise one of reasons().
		 */
		public static function check( $product, $name, $email, $message ) {
			$settings   = self::settings();
			$product_id = ( is_object( $product ) && isset( $product->product_id ) ) ? (int) $product->product_id : 0;

			$reason = self::run_checks( $settings, $product, $product_id, $name, $email, $message );
			$reason = apply_filters(
				'wpeasycart_inquiry_guard_result',
				$reason,
				array(
					'product_id' => $product_id,
					'name'       => $name,
					'email'      => $email,
					'message'    => $message,
					'settings'   => $settings,
				)
			);

			return in_array( $reason, self::reasons(), true ) ? $reason : '';
		}

		/**
		 * The checks themselves, cheapest and most certain first.
		 *
		 * @param array        $settings   Resolved settings.
		 * @param object|false $product    Product row or false.
		 * @param int          $product_id Product id, 0 when unknown.
		 * @param string       $name       Shopper name.
		 * @param string       $email      Shopper email.
		 * @param string       $message    Shopper message.
		 * @return string
		 */
		private static function run_checks( $settings, $product, $product_id, $name, $email, $message ) {

			// Forged or broken submission: no product, or a product id that does not
			// match the model number the form was rendered for.
			if ( ! is_object( $product ) || ! $product_id ) {
				return 'invalid';
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- the calling form handler verified its nonce before running any check.
			if ( isset( $_POST['product_id'] ) && (int) $_POST['product_id'] !== $product_id ) {
				return 'invalid';
			}
			if ( '' === trim( $name ) || '' === trim( $message ) || ! is_email( $email ) ) {
				return 'invalid';
			}

			if ( ! empty( $settings['require_login'] ) && ! self::is_signed_in() ) {
				return 'login_required';
			}

			// phpcs:disable WordPress.Security.NonceVerification.Missing -- the calling form handler verified its nonce before running any check.
			if ( ! empty( $settings['honeypot'] ) && isset( $_POST[ self::HP_FIELD ] ) && '' !== trim( sanitize_text_field( wp_unslash( $_POST[ self::HP_FIELD ] ) ) ) ) {
				return 'honeypot';
			}

			$min = isset( $settings['min_seconds'] ) ? (int) $settings['min_seconds'] : 0;
			if ( $min > 0 && isset( $_POST[ self::TS_FIELD ] ) ) {
				$stamp = (int) $_POST[ self::TS_FIELD ];
				$key   = isset( $_POST[ self::TS_KEY_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::TS_KEY_FIELD ] ) ) : '';
				if ( ! $stamp || '' === $key || ! hash_equals( self::stamp_key( $stamp, $product_id ), $key ) ) {
					return 'invalid';
				}
				$age = time() - $stamp;
				// Only a minimum is enforced: a page served from a full-page cache carries an
				// old stamp, which must keep working. A negative age is a tampered field.
				if ( $age < 0 ) {
					return 'invalid';
				}
				if ( $age < $min ) {
					return 'too_fast';
				}
			}
			// phpcs:enable WordPress.Security.NonceVerification.Missing

			if ( ! empty( $settings['content_checks'] ) ) {
				$max_length = isset( $settings['max_length'] ) ? (int) $settings['max_length'] : 0;
				if ( $max_length > 0 && self::text_length( $message ) > $max_length ) {
					return 'too_long';
				}

				$max_links = isset( $settings['max_links'] ) ? (int) $settings['max_links'] : -1;
				if ( $max_links >= 0 && self::count_links( $name . "\n" . $message ) > $max_links ) {
					return 'too_many_links';
				}

				if ( self::matches_blocklist( $settings['blocklist'], $name . "\n" . $email . "\n" . $message ) ) {
					return 'blocked_content';
				}
			}

			if ( ! empty( $settings['rate_limit'] ) ) {
				$ip = self::fingerprint( self::client_ip() );

				$per_product = isset( $settings['per_product'] ) ? (int) $settings['per_product'] : 0;
				if ( $per_product > 0 && '' !== $ip && self::count_for( 'pp' . $product_id . '_' . $ip ) >= $per_product ) {
					return 'rate_limit_product';
				}

				$per_ip = isset( $settings['per_ip'] ) ? (int) $settings['per_ip'] : 0;
				if ( $per_ip > 0 && '' !== $ip && self::count_for( 'ip' . $ip ) >= $per_ip ) {
					return 'rate_limit';
				}

				$per_email = isset( $settings['per_email'] ) ? (int) $settings['per_email'] : 0;
				$email_fp  = self::fingerprint( $email );
				if ( $per_email > 0 && '' !== $email_fp && self::count_for( 'em' . $email_fp ) >= $per_email ) {
					return 'rate_limit';
				}
			}

			return '';
		}

		/**
		 * Character count that copes with multibyte messages.
		 *
		 * @param string $text Text to measure.
		 * @return int
		 */
		private static function text_length( $text ) {
			return function_exists( 'mb_strlen' ) ? mb_strlen( (string) $text, 'UTF-8' ) : strlen( (string) $text );
		}

		/**
		 * How many web links a piece of text contains: full URLs, www. prefixes,
		 * bare "domain.tld/" paths, HTML anchors and BBCode url tags.
		 *
		 * @param string $text Text to scan.
		 * @return int
		 */
		public static function count_links( $text ) {
			$text   = strtolower( (string) $text );
			$count  = 0;
			$count += preg_match_all( '#https?://#i', $text, $unused_matches );
			$count += preg_match_all( '#(^|[^a-z0-9./@-])www\.[a-z0-9-]+\.[a-z]{2,24}#i', $text, $unused_matches );
			$count += preg_match_all( '#(^|[^a-z0-9./@-])[a-z0-9-]{2,}\.[a-z]{2,24}/#i', $text, $unused_matches );
			$count += preg_match_all( '#<a\s|\[url#i', $text, $unused_matches );
			return (int) $count;
		}

		/**
		 * Does the submission contain a blocked word, phrase or domain?
		 *
		 * @param array  $terms Lower-cased blocklist terms.
		 * @param string $text  Name, email and message joined.
		 * @return bool
		 */
		private static function matches_blocklist( $terms, $text ) {
			if ( empty( $terms ) || ! is_array( $terms ) ) {
				return false;
			}
			$text = strtolower( (string) $text );
			foreach ( $terms as $term ) {
				if ( '' !== $term && false !== strpos( $text, $term ) ) {
					return true;
				}
			}
			return false;
		}

		// ----------------------------------------------------------------
		// Rate-limit counters.
		// ----------------------------------------------------------------

		/**
		 * Current count in the open window for one counter suffix.
		 *
		 * @param string $suffix Counter suffix ( already hashed ).
		 * @return int
		 */
		private static function count_for( $suffix ) {
			$value = get_transient( self::COUNTER_PREFIX . $suffix );
			return ( false === $value ) ? 0 : (int) $value;
		}

		/**
		 * Adds one to a counter, starting a fresh window when it was empty.
		 *
		 * @param string $suffix Counter suffix ( already hashed ).
		 * @param int    $window Window length in seconds.
		 */
		private static function bump( $suffix, $window ) {
			$key   = self::COUNTER_PREFIX . $suffix;
			$value = get_transient( $key );
			if ( false === $value ) {
				set_transient( $key, 1, (int) $window );
				return;
			}
			// Keeps the original expiry: WordPress rewrites the timeout on set_transient(),
			// so the remaining window is read back and reused.
			$timeout   = (int) get_option( '_transient_timeout_' . $key, 0 );
			$remaining = $timeout ? ( $timeout - time() ) : (int) $window;
			if ( $remaining < 1 ) {
				$remaining = (int) $window;
			}
			set_transient( $key, ( (int) $value ) + 1, $remaining );
		}

		/**
		 * Records one accepted inquiry against the IP, email and product counters.
		 * Called only after an inquiry is actually emailed, so a shopper who is
		 * rejected for a typo does not spend their allowance.
		 *
		 * @param int    $product_id Product the inquiry was about.
		 * @param string $email      Shopper email.
		 */
		public static function record( $product_id, $email ) {
			$settings = self::settings();
			if ( empty( $settings['rate_limit'] ) ) {
				return;
			}
			$window   = isset( $settings['window'] ) ? (int) $settings['window'] : HOUR_IN_SECONDS;
			$ip       = self::fingerprint( self::client_ip() );
			$email_fp = self::fingerprint( $email );

			if ( '' !== $ip ) {
				self::bump( 'ip' . $ip, $window );
				self::bump( 'pp' . (int) $product_id . '_' . $ip, $window );
			}
			if ( '' !== $email_fp ) {
				self::bump( 'em' . $email_fp, $window );
			}
		}

		// ----------------------------------------------------------------
		// Rejection.
		// ----------------------------------------------------------------

		/**
		 * Logs the blocked attempt to Settings › Log entries. Stores the reason, the
		 * product and a masked email plus a short hash of the IP — enough to spot a
		 * pattern or recognise a real customer, without keeping the address itself.
		 *
		 * @param string $reason     One of reasons().
		 * @param int    $product_id Product id, 0 when unknown.
		 * @param string $email      Shopper email, masked before it is written.
		 */
		public static function log_block( $reason, $product_id, $email ) {
			$settings = self::settings();
			if ( empty( $settings['log_blocked'] ) ) {
				return;
			}
			global $wpdb;
			if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
				return;
			}
			$visitor = self::fingerprint( self::client_ip(), 12 );
			$text    = sprintf(
				'Inquiry blocked. Reason: %1$s. Product: %2$d. Email: %3$s. Visitor: %4$s.',
				$reason,
				(int) $product_id,
				self::mask_email( $email ),
				( '' !== $visitor ) ? $visitor : '( no public address )'
			);
			$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_response( order_id, is_error, processor, response_text ) VALUES( %d, %d, %s, %s )', 0, 1, self::LOG_SOURCE, $text ) );
		}

		/**
		 * Masks an address for the log: j.smith@example.com becomes j*****@example.com.
		 *
		 * @param string $email Shopper email.
		 * @return string
		 */
		private static function mask_email( $email ) {
			$email = (string) $email;
			$at    = strpos( $email, '@' );
			if ( false === $at || $at < 1 ) {
				return '( none )';
			}
			return substr( $email, 0, 1 ) . str_repeat( '*', 5 ) . substr( $email, $at );
		}

		/**
		 * Logs the rejection and sends the shopper back to the product page with the
		 * reason, where print_notice() turns it into a message. Never returns.
		 *
		 * @param object|false $product Product row or false.
		 * @param string       $reason  One of reasons().
		 * @param string       $email   Shopper email, for the log line only.
		 */
		public static function reject( $product, $reason, $email = '' ) {
			$product_id = ( is_object( $product ) && isset( $product->product_id ) ) ? (int) $product->product_id : 0;
			self::log_block( $reason, $product_id, $email );

			// A background ( AJAX ) add simply stops; there is no page to send anyone back to.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- the calling form handler verified its nonce; this only chooses between redirecting and stopping.
			if ( isset( $_POST['noredirect'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['noredirect'] ) ) ) {
				exit;
			}

			$fallback = '';
			if ( is_object( $product ) && ! empty( $product->post_id ) ) {
				$fallback = get_permalink( (int) $product->post_id );
			}
			if ( ! $fallback ) {
				$store    = (int) get_option( 'ec_option_storepage' );
				$fallback = $store ? get_permalink( $store ) : home_url( '/' );
			}
			$return_url = wp_get_referer();
			$return_url = $return_url ? wp_validate_redirect( $return_url, $fallback ) : $fallback;
			$return_url = remove_query_arg( array( self::ERROR_ARG, self::PRODUCT_ARG, 'ec_store_success', 'ec_store_error', 'model' ), $return_url );

			$args = array( self::ERROR_ARG => $reason );
			if ( $product_id ) {
				$args[ self::PRODUCT_ARG ] = $product_id;
			}
			wp_safe_redirect( add_query_arg( $args, $return_url ) );
			exit;
		}
	}

endif;
