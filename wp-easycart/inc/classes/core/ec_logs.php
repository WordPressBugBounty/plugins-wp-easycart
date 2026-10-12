<?php
/**
 * WP EasyCart — gateway log retention + redaction.
 *
 * ec_response holds raw gateway / carrier / IPN payloads. This class prunes old entries on a daily cron
 * ( ec_option_log_retention_days, 0 = keep forever, default 90 ) and redacts card numbers and API keys before
 * anything is displayed or exported. Registered from inc/ec_config.php; bootstraps itself.
 *
 * 6.0.4: OAuth tokens never reach the table. ec_db::insert_response() runs every entry through scrub_tokens(), token
 * renewals log token_answer() ( what came back, never a token ), and scrub_stored_tokens() clears the tokens older
 * versions stored, once ( option wp_easycart_gateway_log_scrubbed ).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'ec_logs' ) ) :

	final class ec_logs {

		const CRON_HOOK = 'wp_easycart_logs_prune';

		/**
		 * Progress of the one-time token clean-up of stored entries: cursor, max ( the newest entry when it started; later
		 * ones were cleaned as they were written ), done ( time ).
		 *
		 * @since 6.0.4
		 */
		const SCRUB_OPTION = 'wp_easycart_gateway_log_scrubbed';

		/**
		 * Keys whose values are never kept: OAuth tokens and client secrets ( with any prefix, e.g. sandbox_access_token ).
		 *
		 * @since 6.0.4
		 */
		const TOKEN_KEYS = '[A-Za-z0-9_]*(?:access_token|refresh_token|id_token|client_secret)';

		public static function init() {
			add_action( 'init', array( __CLASS__, 'maybe_schedule' ), 20 );
			add_action( self::CRON_HOOK, array( __CLASS__, 'prune' ) );
			add_action( 'admin_init', array( __CLASS__, 'maybe_scrub_stored_tokens' ) );
		}

		public static function retention_days() {
			$v = get_option( 'ec_option_log_retention_days', null );
			return null === $v ? 90 : max( 0, (int) $v );
		}

		public static function maybe_schedule() {
			$want = self::retention_days() > 0;
			$next = wp_next_scheduled( self::CRON_HOOK );
			if ( $want && ! $next ) { wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK ); }
			else if ( ! $want && $next ) { wp_unschedule_event( $next, self::CRON_HOOK ); }
		}

		/** Delete entries older than $days ( default: the retention setting ). Returns rows removed. */
		public static function prune( $days = null ) {
			global $wpdb;
			$days = null === $days ? self::retention_days() : (int) $days;
			if ( $days <= 0 ) { return 0; }
			return (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM ec_response WHERE response_time < DATE_SUB( NOW(), INTERVAL %d DAY )', $days ) );
		}

		/** Mask PANs, CVVs in JSON, and common API-key shapes. Applied to display and export, never to storage. */
		public static function redact( $text ) {
			$text = self::scrub_tokens( (string) $text ); /* 6.0.4: tokens in every shape, also in entries an older version stored. */
			$text = preg_replace_callback( '/\b(\d[ -]?){13,19}\b/', function( $m ) { $d = preg_replace( '/\D/', '', $m[0] ); return str_repeat( '•', max( 0, strlen( $d ) - 4 ) ) . substr( $d, -4 ); }, $text );
			$text = preg_replace( '/("?(cvc|cvv|cvv2|security_code|card_code)"?\s*[:=]\s*"?)\d{3,4}/i', '$1•••', $text );
			$text = preg_replace( '/\b((sk|rk|pk)_(live|test)_)[A-Za-z0-9]{8,}/', '$1••••••••', $text );
			$text = preg_replace( '/\b(whsec_)[A-Za-z0-9]{8,}/', '$1••••••••', $text );
			/* 6.0.2: Shippo tokens, and any Authorization header value an extension printed into its log ( Shippo 1.0.x logged its request headers ). */
			$text = preg_replace( '/\b(shippo_(live|test)_)[A-Za-z0-9]{8,}/', '$1••••••••', $text );
			$text = preg_replace( '/\b((ShippoToken|Bearer|Basic)\s+)[A-Za-z0-9._~+\/=-]{8,}/', '$1••••••••', $text );
			$text = preg_replace( '/("?(password|passwd|api_key|apikey|access_token|secret|signature|license)"?\s*[:=]\s*"?)[^"&\s,}]{4,}/i', '$1••••••••', $text );
			return $text;
		}

		/** First meaningful line for the list. */
		public static function summary( $text, $max = 110 ) {
			$t = trim( preg_replace( '/\s+/', ' ', self::redact( wp_strip_all_tags( (string) $text ) ) ) );
			if ( preg_match( '/"message"\s*:\s*"([^"]{3,})"/', $t, $m ) ) { $t = $m[1]; }
			else if ( preg_match( '/"(decline_code|code|error|status)"\s*:\s*"([^"]{2,})"/', $t, $m ) ) { $t = $m[1] . ': ' . $m[2]; }
			return strlen( $t ) > $max ? substr( $t, 0, $max - 1 ) . '…' : $t;
		}

		/**
		 * Text with every OAuth token and client secret replaced by REDACTED: JSON ( "access_token": "…", also JSON inside
		 * JSON ), print_r() ( [refresh_token] => … ) and var_export() output, addresses and form bodies ( ?refresh_token=… )
		 * and Bearer header values. ec_db::insert_response() runs every gateway log entry through it, so no path stores a
		 * token, whatever it logs. Applied to storage; redact() adds the display masks.
		 *
		 * @since 6.0.4
		 * @param string $text Log text.
		 * @return string
		 */
		public static function scrub_tokens( $text ) {
			$text = (string) $text;
			if ( ! preg_match( '/token|client_secret|bearer/i', $text ) ) {
				return $text;
			}
			$keys = self::TOKEN_KEYS;
			/* "access_token": "…" and, inside JSON text, \"access_token\": \"…\" ( a value ends at its quote, escaped or not ). */
			$text = preg_replace( '/(\\\\*"' . $keys . '\\\\*"\s*:\s*\\\\*")(?:[^"\\\\]|\\\\[^"])+/i', '$1REDACTED', $text );
			/* print_r() output: the key in square brackets, an arrow and the value ( protected properties too ). */
			$text = preg_replace( '/(\[' . $keys . '(?::[^\]]*)?\][ \t]*=>[ \t]*)[^\s]+/i', '$1REDACTED', $text );
			/* var_export() output: the quoted key, an arrow and the quoted value. */
			$text = preg_replace( '/(\'' . $keys . '\'\s*=>\s*\')[^\']+/i', '$1REDACTED', $text );
			/* Addresses and form bodies: ?refresh_token=…&, client_secret=… */
			$text = preg_replace( '/((?<![A-Za-z0-9_])' . $keys . '=)[^&\s"\'<>]+/i', '$1REDACTED', $text );
			/* An Authorization header someone printed. */
			$text = preg_replace( '/(\bBearer\s+)[A-Za-z0-9._~+\/=-]{8,}/i', '$1REDACTED', $text );
			return $text;
		}

		/**
		 * What an OAuth token answer says, for the gateway log: the HTTP status, whether an access token and a refresh token
		 * came back ( yes / no ), when they run out, and the service's own error fields. Never a token.
		 *
		 * @since 6.0.4
		 * @param int   $status HTTP status ( 0 when there was none ).
		 * @param mixed $body   The answer: JSON text, or already decoded.
		 * @return string JSON.
		 */
		public static function token_answer( $status, $body ) {
			$data    = is_string( $body ) ? json_decode( $body, true ) : json_decode( (string) wp_json_encode( $body ), true );
			$summary = array( 'status' => (int) $status );
			if ( ! is_array( $data ) ) {
				$text              = is_scalar( $body ) ? trim( wp_strip_all_tags( (string) $body ) ) : '';
				$summary['answer'] = ( '' === $text ) ? 'empty' : 'not JSON: ' . substr( self::scrub_tokens( $text ), 0, 300 );
				return (string) wp_json_encode( $summary );
			}
			$summary['access_token']  = empty( $data['access_token'] ) ? 'no' : 'yes';
			$summary['refresh_token'] = empty( $data['refresh_token'] ) ? 'no' : 'yes';
			foreach ( array( 'expires_at', 'expires_in', 'refresh_expires', 'refresh_token_expires_in', 'token_type', 'merchant_id', 'short_lived', 'error', 'error_description', 'message', 'type', 'code', 'detail', 'errors' ) as $key ) {
				if ( isset( $data[ $key ] ) && is_scalar( $data[ $key ] ) && '' !== (string) $data[ $key ] ) {
					$summary[ $key ] = is_bool( $data[ $key ] ) ? $data[ $key ] : substr( self::scrub_tokens( (string) $data[ $key ] ), 0, 300 );
				}
			}
			/* Square and FedEx answer errors: [ { category, code, detail | message } ]; UPS response.errors. */
			$errors = array();
			if ( isset( $data['errors'] ) && is_array( $data['errors'] ) ) {
				$errors = $data['errors'];
			} elseif ( isset( $data['response']['errors'] ) && is_array( $data['response']['errors'] ) ) {
				$errors = $data['response']['errors'];
			}
			$list = array();
			foreach ( array_slice( array_values( $errors ), 0, 3 ) as $error ) {
				$line = array();
				foreach ( array( 'category', 'code', 'detail', 'message', 'field' ) as $key ) {
					if ( is_array( $error ) && isset( $error[ $key ] ) && is_scalar( $error[ $key ] ) && '' !== (string) $error[ $key ] ) {
						$line[ $key ] = substr( self::scrub_tokens( (string) $error[ $key ] ), 0, 300 );
					}
				}
				if ( $line ) {
					$list[] = $line;
				}
			}
			if ( $list ) {
				$summary['errors'] = $list;
			}
			return (string) wp_json_encode( $summary );
		}

		/**
		 * On admin_init: the one-time clean-up, a slice per admin page ( never during AJAX or WP-Cron ).
		 *
		 * @since 6.0.4
		 */
		public static function maybe_scrub_stored_tokens() {
			if ( wp_doing_ajax() || wp_doing_cron() ) {
				return;
			}
			$state = get_option( self::SCRUB_OPTION );
			if ( is_array( $state ) && ! empty( $state['done'] ) ) {
				return;
			}
			self::scrub_stored_tokens();
		}

		/**
		 * Once: the tokens older versions stored in ec_response become REDACTED ( Square's token renewal logged Square's whole
		 * answer and the address it asked, which carried the refresh token; PayPal's own app logged its access token; WP EasyCart
		 * PRO's FedEx, UPS and USPS logins theirs ). Only the token values change. Rows are picked by their OAuth tokens and
		 * Bearer values, never by client_secret alone: that would rewrite every Stripe PaymentIntent entry a busy store kept
		 * ( new entries lose it through insert_response() ). Works through the table by response_id in
		 * ranges of 1,000, up to $seconds a call, and keeps its place in SCRUB_OPTION until it reaches the newest entry there
		 * was when it started.
		 *
		 * @since 6.0.4
		 * @param int $seconds Time to work this call.
		 * @return array State: cursor, max, cleaned ( rows changed so far ), done ( time, 0 while working ).
		 */
		public static function scrub_stored_tokens( $seconds = 2 ) {
			global $wpdb;
			$state = get_option( self::SCRUB_OPTION );
			$state = is_array( $state ) ? $state : array();
			if ( ! empty( $state['done'] ) ) {
				return $state;
			}
			if ( ! isset( $state['max'] ) ) {
				$state = array(
					'cursor'  => 0,
					'max'     => (int) $wpdb->get_var( 'SELECT MAX( response_id ) FROM ec_response' ),
					'cleaned' => 0,
					'done'    => 0,
				);
			}
			$until = microtime( true ) + max( 0.1, (float) $seconds );
			while ( (int) $state['cursor'] < (int) $state['max'] && microtime( true ) < $until ) {
				$from = (int) $state['cursor'];
				$to   = min( (int) $state['max'], $from + 1000 );
				$rows = $wpdb->get_results(
					$wpdb->prepare(
						'SELECT response_id, response_text FROM ec_response WHERE response_id > %d AND response_id <= %d AND ( response_text LIKE %s OR response_text LIKE %s OR response_text LIKE %s OR response_text LIKE %s )',
						$from,
						$to,
						'%' . $wpdb->esc_like( 'access_token' ) . '%',
						'%' . $wpdb->esc_like( 'refresh_token' ) . '%',
						'%' . $wpdb->esc_like( 'id_token' ) . '%',
						'%' . $wpdb->esc_like( 'Bearer ' ) . '%'
					)
				);
				if ( ! is_array( $rows ) || '' !== (string) $wpdb->last_error ) {
					break; /* the table could not be read: try again on a later page. */
				}
				foreach ( (array) $rows as $row ) {
					$text  = (string) $row->response_text;
					$clean = self::scrub_tokens( $text );
					if ( $clean !== $text ) {
						$wpdb->query( $wpdb->prepare( 'UPDATE ec_response SET response_text = %s WHERE response_id = %d', $clean, (int) $row->response_id ) );
						++$state['cleaned'];
					}
				}
				$state['cursor'] = $to;
			}
			if ( (int) $state['cursor'] >= (int) $state['max'] ) {
				$state['done'] = time();
			}
			update_option( self::SCRUB_OPTION, $state );
			return $state;
		}
	}

	ec_logs::init();

endif;
