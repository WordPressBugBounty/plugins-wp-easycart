<?php
/**
 * WP EasyCart — gateway log retention + redaction.
 *
 * ec_response holds raw gateway / carrier / IPN payloads. This class prunes old entries on a daily cron
 * ( ec_option_log_retention_days, 0 = keep forever, default 90 ) and redacts card numbers and API keys before
 * anything is displayed or exported. Registered from inc/ec_config.php; bootstraps itself.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'ec_logs' ) ) :

	final class ec_logs {

		const CRON_HOOK = 'wp_easycart_logs_prune';

		public static function init() {
			add_action( 'init', array( __CLASS__, 'maybe_schedule' ), 20 );
			add_action( self::CRON_HOOK, array( __CLASS__, 'prune' ) );
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
			$text = (string) $text;
			$text = preg_replace_callback( '/\b(\d[ -]?){13,19}\b/', function( $m ) { $d = preg_replace( '/\D/', '', $m[0] ); return str_repeat( '•', max( 0, strlen( $d ) - 4 ) ) . substr( $d, -4 ); }, $text );
			$text = preg_replace( '/("?(cvc|cvv|cvv2|security_code|card_code)"?\s*[:=]\s*"?)\d{3,4}/i', '$1•••', $text );
			$text = preg_replace( '/\b((sk|rk|pk)_(live|test)_)[A-Za-z0-9]{8,}/', '$1••••••••', $text );
			$text = preg_replace( '/\b(whsec_)[A-Za-z0-9]{8,}/', '$1••••••••', $text );
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
	}

	ec_logs::init();

endif;
