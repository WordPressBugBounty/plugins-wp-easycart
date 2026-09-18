<?php
/**
 * WP EasyCart — Email delivery: log, queue, health.
 *
 * Every store email becomes visible, and a failed send becomes a retry instead of a lost receipt.
 *
 *   ec_email::send( $to, $subject, $message, $args )   preferred API for plugin code: inline attempt through the
 *                                                       configured transport, queued with backoff on failure.
 *   wp_mail path                                        captured automatically via the 'wp_mail' filter and the
 *                                                       wp_mail_succeeded / wp_mail_failed actions. Failures of
 *                                                       store emails are re-queued from the captured arguments, so
 *                                                       legacy callers get retries without being modified.
 *   plugin mailer path                                  wpeasycart_mailer reports each result to ec_email::record()
 *                                                       and hands failures to ec_email::enqueue().
 *
 * Both transports are tracked; the checks push merchants toward wp_mail + an SMTP/API plugin.
 * Queue is ON by default ( ec_option_email_queue_enabled ); when off, sends stay synchronous but are still logged.
 *
 * Registered from inc/ec_config.php; bootstraps itself.
 *
 * @since 5.x.x
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'ec_email' ) ) :

	final class ec_email {

		const CRON_HOOK   = 'wp_easycart_email_queue_run';
		const MAX_ATTEMPTS = 5;
		const RETENTION_DAYS = 30;
		const FAIL_STREAK_ALERT = 3;
		/** Email types that are merchant tests: kept out of the health figures and the failure streak. @since 6.0.0 */
		const TEST_TYPE = 'test';
		/** Type of the "simulate a failed send" message: every attempt fails on purpose, so it is never real customer mail. @since 6.0.0 */
		const SIMULATE_TYPE = 'test_failure';
		/** Transient set once the init path has looked at the queue; the COUNT is skipped until it expires. @since 6.0.0 */
		const QUEUE_CHECK_TRANSIENT = 'ec_email_queue_checked';
		/** Seconds between init-path queue checks. @since 6.0.0 */
		const QUEUE_CHECK_TTL = 600;
		/** Seconds the SPF / DKIM / DMARC lookups for one from-domain are kept. @since 6.0.0 */
		const DNS_CACHE_TTL = 3600;

		/** Context the current send belongs to ( set by callers, read by the wp_mail capture ). */
		private static $context = null;
		/** Args captured in the 'wp_mail' filter, matched in the succeeded/failed actions. */
		private static $pending = array();
		private static $sending_from_queue = false;

		public static function init() {
			add_action( 'init', array( __CLASS__, 'maybe_schedule_on_init' ), 20 );
			add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
			add_action( self::CRON_HOOK, array( __CLASS__, 'run_queue' ) );
			add_filter( 'wp_mail', array( __CLASS__, 'capture_wp_mail' ), 9999 );
			add_action( 'wp_mail_succeeded', array( __CLASS__, 'on_wp_mail_succeeded' ), 10, 1 );
			add_action( 'wp_mail_failed', array( __CLASS__, 'on_wp_mail_failed' ), 10, 1 );
		}

		public static function tables_exist( $recheck = false ) {
			static $ok = null;
			if ( null === $ok || $recheck ) {
				global $wpdb;
				$ok = (bool) $wpdb->get_var( "SHOW TABLES LIKE 'ec_email_log'" ) && (bool) $wpdb->get_var( "SHOW TABLES LIKE 'ec_email_queue'" );
			}
			return $ok;
		}

		public static function queue_enabled() {
			$v = get_option( 'ec_option_email_queue_enabled', null );
			return (bool) apply_filters( 'wp_easycart_email_queue_enabled', null === $v ? true : (bool) $v );
		}

		/** 'wp_mail' | 'plugin_smtp' | 'plugin_mail' | 'custom' — what EasyCart is configured to use. */
		public static function configured_transport() {
			$m = (string) apply_filters( 'wpeasycart_email_method', get_option( 'ec_option_use_wp_mail' ) );
			if ( '1' === $m ) { return 'wp_mail'; }
			if ( '0' === $m ) { return get_option( 'ec_option_order_use_smtp' ) ? 'plugin_smtp' : 'plugin_mail'; }
			return 'custom';
		}

		public static function transport_label( $t ) {
			$l = array( 'wp_mail' => __( 'WordPress mail (wp_mail)', 'wp-easycart' ), 'plugin_smtp' => __( 'EasyCart built-in SMTP', 'wp-easycart' ), 'plugin_mail' => __( 'EasyCart built-in PHP mail()', 'wp-easycart' ), 'custom' => __( 'Custom (third-party hook)', 'wp-easycart' ), 'queue' => __( 'Queue', 'wp-easycart' ) );
			return isset( $l[ $t ] ) ? $l[ $t ] : $t;
		}

		/* ------------------------------------------------------------------ */
		/* Context                                                             */
		/* ------------------------------------------------------------------ */

		/** Callers wrap a send: ec_email::context( 'order_receipt', $order_id ); … ; ec_email::context( null ); */
		public static function context( $type, $order_id = 0 ) {
			self::$context = $type ? array( 'type' => (string) $type, 'order_id' => (int) $order_id ) : null;
		}

		/** Best guess for legacy callers: the recipient is an order's customer and the order was touched recently. */
		public static function resolve_order( $to, $subject = '' ) {
			global $wpdb;
			if ( preg_match( '/#\s?(\d{2,})/', (string) $subject, $m ) ) {
				$oid = (int) $m[1];
				if ( $wpdb->get_var( $wpdb->prepare( 'SELECT order_id FROM ec_order WHERE order_id = %d', $oid ) ) ) { return $oid; }
			}
			$to = trim( (string) $to );
			if ( '' === $to ) { return 0; }
			$oid = $wpdb->get_var( $wpdb->prepare( 'SELECT order_id FROM ec_order WHERE user_email = %s AND ( last_updated >= DATE_SUB( NOW(), INTERVAL 10 MINUTE ) OR order_date >= DATE_SUB( NOW(), INTERVAL 10 MINUTE ) ) ORDER BY last_updated DESC LIMIT 1', $to ) );
			return (int) $oid;
		}

		/* ------------------------------------------------------------------ */
		/* Preferred send API                                                  */
		/* ------------------------------------------------------------------ */

		/**
		 * @param array $args { type, order_id, channel ( order|account ), headers[] }
		 * @return bool  true when delivered inline OR queued for retry; false only when it failed and could not be queued.
		 */
		public static function send( $to, $subject, $message, $args = array() ) {
			$args = wp_parse_args( $args, array( 'type' => 'store', 'order_id' => 0, 'channel' => 'order', 'headers' => array() ) );
			$to = self::normalise_to( $to );
			if ( ! $to ) { return false; }
			self::context( $args['type'], $args['order_id'] );
			$res = self::deliver( $to, $subject, $message, $args['channel'], $args['headers'] );
			self::context( null );
			/* wp_mail results are normally logged ( and failures queued ) by the capture hooks; when a plugin
			   replaces wp_mail() without firing them, $res['handled'] is false and we do it here. */
			$handled = 'wp_mail' === $res['transport'] && $res['handled'];
			if ( $res['ok'] ) {
				if ( ! $handled ) { self::record( $args['type'], $args['order_id'], $to, $subject, $res['transport'], 'sent', '', 1, 0, $message ); }
				return true;
			}
			if ( ! $handled ) {
				self::record( $args['type'], $args['order_id'], $to, $subject, $res['transport'], 'failed', $res['error'], 1, 0, $message );
				if ( self::queue_enabled() && self::tables_exist() ) {
					self::enqueue( $to, $subject, $message, array_merge( $args, array( 'error' => $res['error'], 'attempts' => 1 ) ) );
					return true;
				}
				return false;
			}
			return self::queue_enabled() && self::tables_exist(); /* the failed hook queued it */
		}

		/** True while the message being delivered is the merchant's "simulate a failed send" test. @since 6.0.0 */
		public static function is_simulated_failure() {
			return ( self::$context && isset( self::$context['type'] ) && self::SIMULATE_TYPE === self::$context['type'] );
		}

		/** A merchant test ( 'test' or 'test_failure' ): never counted as customer mail. @since 6.0.0 */
		public static function is_test_type( $type ) {
			return in_array( (string) $type, array( self::TEST_TYPE, self::SIMULATE_TYPE ), true );
		}

		/** One attempt through the configured transport. wp_mail results are logged by the capture hooks. */
		private static function deliver( $to, $subject, $message, $channel, $headers = array() ) {
			$t = self::configured_transport();
			if ( self::is_simulated_failure() ) {
				/* The merchant asked to watch the retry queue work: fail without touching the mail server. */
				return array(
					'ok'        => false,
					'transport' => $t,
					'error'     => __( 'Simulated failure — this is the "simulate a failed send" test, no mail was sent.', 'wp-easycart' ),
					'handled'   => false,
				);
			}
			$from = stripslashes( get_option( 'order' === $channel ? 'ec_option_order_from_email' : 'ec_option_password_from_email' ) );
			if ( 'wp_mail' === $t ) {
				$h = array_merge( array( 'MIME-Version: 1.0', 'Content-Type: text/html; charset=utf-8', 'From: ' . $from, 'Reply-To: ' . $from, 'X-Mailer: PHP/' . phpversion() ), (array) $headers );
				self::$wp_mail_handled = false; self::$last_wp_error = '';
				$ok = (bool) wp_mail( $to, $subject, $message, implode( "\r\n", $h ) );
				return array( 'ok' => $ok, 'transport' => 'wp_mail', 'error' => $ok ? '' : ( self::$last_wp_error ? self::$last_wp_error : 'wp_mail() returned false' ), 'handled' => self::$wp_mail_handled );
			}
			if ( 'custom' === $t ) {
				do_action( 'wpeasycart_custom_store_email', $from, $to, '', $subject, $message );
				return array( 'ok' => true, 'transport' => 'custom', 'error' => '', 'handled' => false );
			}
			if ( ! class_exists( 'wpeasycart_mailer' ) ) { return array( 'ok' => false, 'transport' => $t, 'error' => 'wpeasycart_mailer not loaded', 'handled' => false ); }
			$mailer = new wpeasycart_mailer();
			self::$suppress_mailer_record = true;
			$err = ( 'order' === $channel ) ? $mailer->send_order_email( $to, $subject, $message ) : $mailer->send_customer_email( $to, $subject, $message );
			self::$suppress_mailer_record = false;
			return array( 'ok' => false === $err, 'transport' => $t, 'error' => false === $err ? '' : (string) $err, 'handled' => false );
		}
		private static $suppress_mailer_record = false;
		private static $last_wp_error = '';
		private static $wp_mail_handled = false;

		/**
		 * Comma list of the recipients that carry a valid address. A part written as "Name <addr@x.com>" ( what the
		 * store notification / BCC list allows, see ecst_email_validate_list() ) is kept whole, since wp_mail accepts
		 * display names; anything else must be a bare address. @since 6.0.0 display names are no longer dropped.
		 */
		private static function normalise_to( $to ) {
			if ( is_array( $to ) ) { $to = implode( ',', $to ); }
			$parts = array();
			foreach ( array_map( 'trim', explode( ',', (string) $to ) ) as $part ) {
				if ( '' === $part ) { continue; }
				$address = preg_match( '/<([^>]+)>\s*$/', $part, $m ) ? trim( $m[1] ) : $part;
				if ( is_email( $address ) ) { $parts[] = $part; }
			}
			return $parts ? implode( ',', $parts ) : '';
		}

		/* ------------------------------------------------------------------ */
		/* Capture: wp_mail                                                    */
		/* ------------------------------------------------------------------ */

		/** Remember the args so the succeeded/failed actions can be logged with type/order and re-queued. */
		public static function capture_wp_mail( $atts ) {
			if ( ! is_array( $atts ) ) { return $atts; }
			$to = isset( $atts['to'] ) ? self::normalise_to( $atts['to'] ) : '';
			$subject = isset( $atts['subject'] ) ? (string) $atts['subject'] : '';
			$store = self::is_store_email( $atts );
			self::$pending[ self::key( $to, $subject ) ] = array(
				'store' => $store, 'to' => $to, 'subject' => $subject, 'message' => isset( $atts['message'] ) ? $atts['message'] : '',
				'headers' => isset( $atts['headers'] ) ? $atts['headers'] : '', 'ctx' => self::$context, 'from_queue' => self::$sending_from_queue,
			);
			return $atts;
		}

		/** Only EasyCart mail is logged: either a context is set, or the From header is one of the store's sender addresses. */
		private static function is_store_email( $atts ) {
			if ( self::$context || self::$sending_from_queue ) { return true; }
			$h = isset( $atts['headers'] ) ? ( is_array( $atts['headers'] ) ? implode( "\n", $atts['headers'] ) : (string) $atts['headers'] ) : '';
			foreach ( array( 'ec_option_order_from_email', 'ec_option_password_from_email' ) as $opt ) {
				$v = trim( (string) stripslashes( get_option( $opt ) ) );
				if ( '' !== $v && false !== stripos( $h, $v ) ) { return true; }
				if ( preg_match( '/<([^>]+)>/', $v, $m ) && false !== stripos( $h, $m[1] ) ) { return true; }
			}
			return (bool) apply_filters( 'wp_easycart_email_is_store_email', false, $atts );
		}

		private static function key( $to, $subject ) { return md5( strtolower( $to ) . '|' . $subject ); }

		public static function on_wp_mail_succeeded( $mail_data ) {
			$p = self::take_pending( $mail_data );
			if ( ! $p || ! $p['store'] ) { return; }
			self::$wp_mail_handled = true;
			$ctx = $p['ctx'] ? $p['ctx'] : array( 'type' => 'store', 'order_id' => 0 );
			if ( ! $p['from_queue'] ) { self::record( $ctx['type'], $ctx['order_id'], $p['to'], $p['subject'], 'wp_mail', 'sent', '', 1, 0, $p['message'] ); }
		}

		public static function on_wp_mail_failed( $error ) {
			$data = is_wp_error( $error ) ? (array) $error->get_error_data() : array();
			$msg = is_wp_error( $error ) ? $error->get_error_message() : 'wp_mail failed';
			self::$last_wp_error = $msg;
			$p = self::take_pending( $data );
			if ( ! $p || ! $p['store'] ) { return; }
			self::$wp_mail_handled = true;
			if ( $p['from_queue'] ) { return; }
			$ctx = $p['ctx'] ? $p['ctx'] : array( 'type' => 'store', 'order_id' => 0 );
			if ( ! $ctx['order_id'] ) { $ctx['order_id'] = self::resolve_order( $p['to'], $p['subject'] ); if ( $ctx['order_id'] && 'store' === $ctx['type'] ) { $ctx['type'] = 'order'; } }
			self::record( $ctx['type'], $ctx['order_id'], $p['to'], $p['subject'], 'wp_mail', 'failed', $msg, 1, 0, $p['message'] );
			/* Legacy callers get retries too: re-queue from the captured arguments. */
			if ( self::queue_enabled() && self::tables_exist() && '' !== $p['message'] ) {
				self::enqueue( $p['to'], $p['subject'], $p['message'], array( 'type' => $ctx['type'], 'order_id' => $ctx['order_id'], 'channel' => 'order', 'headers' => $p['headers'], 'error' => $msg, 'attempts' => 1 ) );
			}
		}

		private static function take_pending( $data ) {
			$to = isset( $data['to'] ) ? self::normalise_to( $data['to'] ) : '';
			$subject = isset( $data['subject'] ) ? (string) $data['subject'] : '';
			$k = self::key( $to, $subject );
			if ( isset( self::$pending[ $k ] ) ) { $p = self::$pending[ $k ]; unset( self::$pending[ $k ] ); return $p; }
			/* WP < 5.9 has no wp_mail_succeeded; fall back to the most recent capture */
			$p = array_pop( self::$pending ); self::$pending = array();
			return $p;
		}

		/* ------------------------------------------------------------------ */
		/* Log                                                                 */
		/* ------------------------------------------------------------------ */

		public static function record( $type, $order_id, $to, $subject, $transport, $status, $error = '', $attempts = 1, $queue_id = 0, $message = '' ) {
			global $wpdb;
			if ( ! self::tables_exist() ) { return 0; }
			$order_id = (int) $order_id;
			if ( ! $order_id ) { $order_id = self::resolve_order( $to, $subject ); }
			if ( 'store' === $type && $order_id ) { $type = 'order'; }
			$wpdb->insert( 'ec_email_log', array(
				'created_at' => current_time( 'mysql' ), 'email_type' => substr( (string) $type, 0, 40 ), 'order_id' => $order_id, 'to_email' => substr( (string) $to, 0, 255 ), 'subject' => substr( wp_strip_all_tags( (string) $subject ), 0, 255 ),
				'transport' => $transport, 'status' => $status, 'error_text' => (string) $error, 'attempts' => (int) $attempts, 'queue_id' => (int) $queue_id, 'body_hash' => $message ? substr( md5( (string) $message ), 0, 40 ) : '',
			) );
			$id = (int) $wpdb->insert_id;
			if ( ! self::is_test_type( $type ) ) {
				self::update_streak( 'sent' === $status ); /* merchant tests never move the streak or raise the failing-email banner */
			}
			do_action( 'wp_easycart_email_logged', $id, $status, $transport, $type, $order_id );
			return $id;
		}

		/** Called by wpeasycart_mailer after each attempt ( unless ec_email::send() is the caller and records itself ). */
		public static function record_mailer_result( $channel, $to, $subject, $message, $error ) {
			if ( self::$suppress_mailer_record ) { return; }
			$t = get_option( 'order' === $channel ? 'ec_option_order_use_smtp' : 'ec_option_password_use_smtp' ) ? 'plugin_smtp' : 'plugin_mail';
			$ctx = self::$context ? self::$context : array( 'type' => 'store', 'order_id' => 0 );
			if ( ! $ctx['order_id'] ) { $ctx['order_id'] = self::resolve_order( self::normalise_to( $to ), $subject ); if ( $ctx['order_id'] && 'store' === $ctx['type'] ) { $ctx['type'] = 'order'; } }
			$ok = ( false === $error );
			self::record( $ctx['type'], $ctx['order_id'], self::normalise_to( $to ), $subject, $t, $ok ? 'sent' : 'failed', $ok ? '' : (string) $error, 1, 0, $message );
			if ( ! $ok && self::queue_enabled() && self::tables_exist() && ! self::$sending_from_queue ) {
				self::enqueue( self::normalise_to( $to ), $subject, $message, array( 'type' => $ctx['type'], 'order_id' => $ctx['order_id'], 'channel' => $channel, 'error' => (string) $error, 'attempts' => 1 ) );
			}
		}

		private static function update_streak( $sent ) {
			if ( $sent ) { update_option( 'ec_option_email_fail_streak', 0, false ); update_option( 'ec_option_email_last_success', current_time( 'mysql' ), false ); }
			else { update_option( 'ec_option_email_fail_streak', (int) get_option( 'ec_option_email_fail_streak', 0 ) + 1, false ); }
		}

		/* ------------------------------------------------------------------ */
		/* Queue                                                               */
		/* ------------------------------------------------------------------ */

		public static function enqueue( $to, $subject, $message, $args = array() ) {
			global $wpdb;
			if ( ! self::tables_exist() ) { return 0; }
			$args = wp_parse_args( $args, array( 'type' => 'store', 'order_id' => 0, 'channel' => 'order', 'headers' => array(), 'error' => '', 'attempts' => 0 ) );
			$attempts = (int) $args['attempts'];
			$wpdb->insert( 'ec_email_queue', array(
				'created_at' => current_time( 'mysql' ), 'email_type' => substr( (string) $args['type'], 0, 40 ), 'order_id' => (int) $args['order_id'], 'channel' => $args['channel'], 'to_email' => $to, 'subject' => $subject,
				'message' => $message, 'headers' => is_array( $args['headers'] ) ? implode( "\r\n", $args['headers'] ) : (string) $args['headers'],
				'attempts' => $attempts, 'next_attempt' => self::next_attempt( $attempts ), 'status' => 'pending', 'last_error' => (string) $args['error'],
			) );
			$id = (int) $wpdb->insert_id;
			/* A pending row now exists: schedule its retry here, on this request, rather than waiting for the init path. */
			self::queue_changed();
			self::maybe_schedule();
			return $id;
		}

		/** Backoff: 1 min, 10 min, 1 h, 6 h. */
		public static function next_attempt( $attempts ) {
			$steps = array( 60, 600, 3600, 21600 );
			$delay = $steps[ min( max( 0, (int) $attempts - 1 ), count( $steps ) - 1 ) ];
			return date( 'Y-m-d H:i:s', current_time( 'timestamp' ) + $delay );
		}

		public static function cron_schedules( $s ) {
			if ( ! isset( $s['wp_easycart_5min'] ) ) { $s['wp_easycart_5min'] = array( 'interval' => 300, 'display' => __( 'Every 5 minutes (EasyCart)', 'wp-easycart' ) ); }
			return $s;
		}

		/**
		 * The init path, kept near free on ordinary requests: when the cron event is already on the calendar it
		 * re-evaluates the queue itself after each run, and otherwise the COUNT runs at most once per
		 * QUEUE_CHECK_TTL. enqueue() and run_queue() clear that window, so a retry is still scheduled the
		 * moment a send fails.
		 *
		 * @since 6.0.0
		 */
		public static function maybe_schedule_on_init() {
			if ( wp_next_scheduled( self::CRON_HOOK ) ) {
				return;
			}
			if ( get_transient( self::QUEUE_CHECK_TRANSIENT ) ) {
				return;
			}
			self::maybe_schedule();
		}

		/** Forget the last init-path check: called whenever the queue gained rows or was run. @since 6.0.0 */
		public static function queue_changed() {
			delete_transient( self::QUEUE_CHECK_TRANSIENT );
		}

		/** Full check: schedule the cron event when pending rows exist, remove it ( and prune ) when none do. */
		public static function maybe_schedule() {
			set_transient( self::QUEUE_CHECK_TRANSIENT, 1, self::QUEUE_CHECK_TTL );
			if ( ! self::tables_exist() ) { return; }
			global $wpdb;
			$want = (int) $wpdb->get_var( "SELECT COUNT(*) FROM ec_email_queue WHERE status = 'pending'" ) > 0;
			$next = wp_next_scheduled( self::CRON_HOOK );
			if ( $want && ! $next ) { wp_schedule_event( time() + 60, 'wp_easycart_5min', self::CRON_HOOK ); }
			else if ( ! $want && $next ) { wp_unschedule_event( $next, self::CRON_HOOK ); self::prune(); }
		}

		/** Cron: send everything due; on final failure park it. Returns counts. */
		public static function run_queue( $limit = 50 ) {
			global $wpdb;
			if ( ! self::tables_exist() ) { return array( 'sent' => 0, 'retried' => 0, 'parked' => 0 ); }
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM ec_email_queue WHERE status = 'pending' AND next_attempt <= %s ORDER BY next_attempt LIMIT %d", current_time( 'mysql' ), (int) $limit ) );
			$out = array( 'sent' => 0, 'retried' => 0, 'parked' => 0 );
			foreach ( $rows as $q ) { $r = self::attempt( $q ); $out[ $r ]++; }
			self::queue_changed();
			self::maybe_schedule();
			return $out;
		}

		/** One attempt for a queue row ( also used by "Send now" / "Retry" ). @return 'sent'|'retried'|'parked' */
		public static function attempt( $q ) {
			global $wpdb;
			self::$sending_from_queue = true;
			self::context( $q->email_type, (int) $q->order_id );
			$headers = '' !== (string) $q->headers ? explode( "\r\n", (string) $q->headers ) : array();
			$res = self::deliver( $q->to_email, $q->subject, $q->message, $q->channel, $headers );
			self::context( null );
			self::$sending_from_queue = false;
			$attempts = (int) $q->attempts + 1;
			if ( $res['ok'] ) {
				$wpdb->update( 'ec_email_queue', array( 'status' => 'sent', 'attempts' => $attempts, 'last_error' => '' ), array( 'queue_id' => (int) $q->queue_id ) );
				self::record( $q->email_type, (int) $q->order_id, $q->to_email, $q->subject, $res['transport'], 'sent', '', $attempts, (int) $q->queue_id, $q->message );
				return 'sent';
			}
			$parked = $attempts >= self::MAX_ATTEMPTS;
			$wpdb->update( 'ec_email_queue', array( 'status' => $parked ? 'failed' : 'pending', 'attempts' => $attempts, 'next_attempt' => $parked ? null : self::next_attempt( $attempts ), 'last_error' => $res['error'] ), array( 'queue_id' => (int) $q->queue_id ) );
			self::record( $q->email_type, (int) $q->order_id, $q->to_email, $q->subject, $res['transport'], $parked ? 'failed' : 'retrying', $res['error'], $attempts, (int) $q->queue_id, $q->message );
			return $parked ? 'parked' : 'retried';
		}

		public static function retry( $queue_id ) {
			global $wpdb;
			$q = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_email_queue WHERE queue_id = %d', (int) $queue_id ) );
			if ( ! $q ) { return new WP_Error( 'not_found', __( 'That email is no longer in the queue.', 'wp-easycart' ) ); }
			if ( 'failed' === $q->status ) { $q->attempts = 0; $wpdb->update( 'ec_email_queue', array( 'attempts' => 0 ), array( 'queue_id' => (int) $q->queue_id ) ); }
			$r = self::attempt( $q );
			/* A manual retry that failed again is pending once more; make sure the cron event exists to pick it up. */
			if ( 'retried' === $r && ! wp_next_scheduled( self::CRON_HOOK ) ) {
				self::queue_changed();
				self::maybe_schedule();
			}
			return $r;
		}

		/**
		 * Put every parked ( 'failed' ) email back in the queue — status pending, attempts reset, due now — and
		 * make sure the cron event exists so run_queue() sends them in batches of 50. Nothing is sent inside
		 * this request. At most $limit rows per call; the caller reports what is left.
		 *
		 * @since 6.0.0 Requeues instead of attempting every failed email live.
		 * @param int $limit Rows to requeue per call.
		 * @return array queued ( rows requeued now ), remaining ( rows still failed ).
		 */
		public static function retry_all_failed( $limit = 500 ) {
			global $wpdb;
			if ( ! self::tables_exist() ) { return array( 'queued' => 0, 'remaining' => 0 ); }
			$limit  = max( 1, (int) $limit );
			$queued = (int) $wpdb->query( $wpdb->prepare( "UPDATE ec_email_queue SET status = 'pending', attempts = 0, next_attempt = %s WHERE status = 'failed' ORDER BY queue_id ASC LIMIT %d", current_time( 'mysql' ), $limit ) );
			$remaining = (int) $wpdb->get_var( "SELECT COUNT(*) FROM ec_email_queue WHERE status = 'failed'" );
			if ( $queued ) {
				self::queue_changed();
				self::maybe_schedule();
			}
			return array( 'queued' => $queued, 'remaining' => $remaining );
		}

		/**
		 * Queue rows a merchant should see: waiting to retry first, then the ones that gave up.
		 *
		 * @since 6.0.0
		 * @param int $limit Rows.
		 * @return array
		 */
		public static function queue_rows( $limit = 25 ) {
			global $wpdb;
			if ( ! self::tables_exist() ) {
				return array();
			}
			return (array) $wpdb->get_results( $wpdb->prepare( "SELECT queue_id, created_at, email_type, order_id, to_email, subject, attempts, next_attempt, status, last_error FROM ec_email_queue WHERE status IN ( 'pending', 'failed' ) ORDER BY FIELD( status, 'pending', 'failed' ), next_attempt IS NULL, next_attempt, queue_id LIMIT %d", max( 1, (int) $limit ) ) );
		}

		/**
		 * Waiting / needs-attention counts and when the queue runs next.
		 *
		 * @since 6.0.0
		 * @return array pending, failed, due_now, next_attempt ( mysql time or '' ), next_run ( timestamp or 0 ), cron_on.
		 */
		public static function queue_status() {
			global $wpdb;
			$out = array( 'pending' => 0, 'failed' => 0, 'due_now' => 0, 'next_attempt' => '', 'next_run' => 0, 'cron_on' => false, 'queue_enabled' => self::queue_enabled() );
			if ( ! self::tables_exist() ) {
				return $out;
			}
			$out['pending']      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM ec_email_queue WHERE status = 'pending'" );
			$out['failed']       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM ec_email_queue WHERE status = 'failed'" );
			$out['due_now']      = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM ec_email_queue WHERE status = 'pending' AND next_attempt <= %s", current_time( 'mysql' ) ) );
			$out['next_attempt'] = (string) $wpdb->get_var( "SELECT MIN( next_attempt ) FROM ec_email_queue WHERE status = 'pending'" );
			$next                = wp_next_scheduled( self::CRON_HOOK );
			$out['next_run']     = $next ? (int) $next : 0;
			$out['cron_on']      = (bool) $next;
			return $out;
		}

		/**
		 * Take one queued message out of the queue ( merchant cancelled it, e.g. after a simulation ).
		 *
		 * @since 6.0.0
		 * @param int $queue_id Row.
		 * @return bool
		 */
		public static function cancel( $queue_id ) {
			global $wpdb;
			if ( ! self::tables_exist() ) {
				return false;
			}
			return (bool) $wpdb->delete( 'ec_email_queue', array( 'queue_id' => (int) $queue_id ) );
		}

		/**
		 * Send a message that always fails, so the merchant can watch the retry queue work.
		 *
		 * It goes through the normal path ( inline attempt → log 'failed' → queued with backoff ), but deliver()
		 * short-circuits for this type, so no mail server is contacted and no customer is ever written to.
		 *
		 * @since 6.0.0
		 * @param string $to Recipient recorded on the test row.
		 * @return array|WP_Error queue_id, next_attempt.
		 */
		public static function simulate_failed_send( $to ) {
			global $wpdb;
			if ( ! self::tables_exist() ) {
				return new WP_Error( 'ec_email_tables', __( 'Email logging is not available until the database update runs.', 'wp-easycart' ) );
			}
			if ( ! self::queue_enabled() ) {
				return new WP_Error( 'ec_email_queue_off', __( 'Automatic retry is off, so a failed email would simply be reported as failed. Turn on "Retry failed emails automatically" in the email log to try this.', 'wp-easycart' ) );
			}
			$to = self::normalise_to( $to );
			if ( ! $to ) {
				$to = (string) get_option( 'admin_email' );
			}
			/* translators: %s: store name. */
			$subject = sprintf( __( 'Retry queue test from %s ( this email is never delivered )', 'wp-easycart' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
			$message = '<p>' . esc_html__( 'This is the "simulate a failed send" test from WP EasyCart. Every attempt fails on purpose so you can watch the retry queue: it is queued, retried after 1 minute, 10 minutes, 1 hour and 6 hours, then marked failed after the fifth attempt.', 'wp-easycart' ) . '</p>'
				. '<p>' . esc_html__( 'No mail server is contacted and no customer receives anything.', 'wp-easycart' ) . '</p>';

			self::send( $to, $subject, $message, array( 'type' => self::SIMULATE_TYPE, 'channel' => 'order' ) );

			$row = $wpdb->get_row( $wpdb->prepare( "SELECT queue_id, next_attempt FROM ec_email_queue WHERE email_type = %s AND status = 'pending' ORDER BY queue_id DESC LIMIT 1", self::SIMULATE_TYPE ) );
			if ( ! $row ) {
				return new WP_Error( 'ec_email_simulate', __( 'The test message could not be queued. Check that automatic retry is on.', 'wp-easycart' ) );
			}
			/* Due straight away so "Send queue now" shows the first retry without a wait; real emails keep their backoff. */
			$wpdb->update( 'ec_email_queue', array( 'next_attempt' => current_time( 'mysql' ) ), array( 'queue_id' => (int) $row->queue_id ) );
			return array( 'queue_id' => (int) $row->queue_id, 'next_attempt' => current_time( 'mysql' ) );
		}

		public static function prune() {
			global $wpdb;
			if ( ! self::tables_exist() ) { return; }
			$days = (int) apply_filters( 'wp_easycart_email_log_retention_days', self::RETENTION_DAYS );
			$wpdb->query( $wpdb->prepare( 'DELETE FROM ec_email_log WHERE created_at < DATE_SUB( NOW(), INTERVAL %d DAY )', $days ) );
			$wpdb->query( $wpdb->prepare( "DELETE FROM ec_email_queue WHERE status = 'sent' AND created_at < DATE_SUB( NOW(), INTERVAL %d DAY )", $days ) );
		}

		/* ------------------------------------------------------------------ */
		/* Health                                                              */
		/* ------------------------------------------------------------------ */

		public static function health( $days = 7 ) {
			global $wpdb;
			$h = array( 'available' => self::tables_exist(), 'sent' => 0, 'failed' => 0, 'rate' => null, 'last_success' => get_option( 'ec_option_email_last_success', '' ), 'last_error' => null, 'streak' => (int) get_option( 'ec_option_email_fail_streak', 0 ), 'transport' => self::configured_transport(), 'queued' => 0, 'parked' => 0, 'daily' => array(), 'affected_orders' => 0 );
			if ( ! $h['available'] ) { return $h; }
			$since = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - (int) $days * DAY_IN_SECONDS );
			/* Final outcomes only: a 'retrying' row is not a failure yet. Merchant tests ( including the simulated failure ) are excluded so they cannot dent the delivery rate. */
			$r = $wpdb->get_row( $wpdb->prepare( "SELECT SUM( status = 'sent' ) AS sent, SUM( status = 'failed' ) AS failed FROM ec_email_log WHERE created_at >= %s AND email_type NOT IN ( %s, %s )", $since, self::TEST_TYPE, self::SIMULATE_TYPE ), ARRAY_A );
			$h['sent'] = (int) $r['sent']; $h['failed'] = (int) $r['failed'];
			$h['rate'] = ( $h['sent'] + $h['failed'] ) ? (int) round( 100 * $h['sent'] / ( $h['sent'] + $h['failed'] ) ) : null;
			$h['last_error'] = $wpdb->get_row( $wpdb->prepare( "SELECT created_at, email_type, order_id, to_email, error_text, transport FROM ec_email_log WHERE status IN ( 'failed', 'retrying' ) AND email_type NOT IN ( %s, %s ) ORDER BY log_id DESC LIMIT 1", self::TEST_TYPE, self::SIMULATE_TYPE ) );
			$h['queued'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM ec_email_queue WHERE status = 'pending'" );
			$h['parked'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM ec_email_queue WHERE status = 'failed'" );
			$h['affected_orders'] = (int) $wpdb->get_var( "SELECT COUNT( DISTINCT order_id ) FROM ec_email_queue WHERE status IN ( 'pending', 'failed' ) AND order_id > 0" );
			foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT DATE( created_at ) AS d, SUM( status = 'sent' ) AS s, SUM( status = 'failed' ) AS f FROM ec_email_log WHERE created_at >= %s AND email_type NOT IN ( %s, %s ) GROUP BY DATE( created_at ) ORDER BY d", date( 'Y-m-d', current_time( 'timestamp' ) - 13 * DAY_IN_SECONDS ), self::TEST_TYPE, self::SIMULATE_TYPE ) ) as $d ) { $h['daily'][ $d->d ] = array( 'sent' => (int) $d->s, 'failed' => (int) $d->f ); }
			return $h;
		}

		/** Plain-English explanation + fix for the common SMTP errors. */
		public static function explain( $error ) {
			$e = strtolower( (string) $error );
			$map = array(
				array( array( '535', 'authenticate', 'badcredentials', 'username and password' ), __( 'The mail server rejected the login. This usually means an app password was regenerated, two-step verification changed, or the account was locked for suspicious activity.', 'wp-easycart' ), __( 'Update the SMTP password / app password', 'wp-easycart' ) ),
				array( array( 'connect()', 'connection refused', 'connection timed out', 'could not connect', 'network is unreachable' ), __( 'The server could not reach the mail host. Hosts often block outbound SMTP ports (25, 465, 587), or the hostname/port is wrong.', 'wp-easycart' ), __( 'Check host and port, or ask your host whether SMTP is blocked', 'wp-easycart' ) ),
				array( array( 'tls', 'ssl', 'certificate', 'starttls' ), __( 'The encrypted connection failed. The encryption type usually needs to match the port: TLS with 587, SSL with 465.', 'wp-easycart' ), __( 'Match encryption to port', 'wp-easycart' ) ),
				array( array( '550', '553', 'sender address rejected', 'not allowed to send', 'from address' ), __( 'The server refused the "from" address. Most providers only send from addresses on domains you have verified with them.', 'wp-easycart' ), __( 'Use a from address on your own, verified domain', 'wp-easycart' ) ),
				array( array( '452', '421', 'quota', 'limit', 'too many', 'rate' ), __( 'The account hit a sending limit (Gmail and most free tiers cap daily volume). Mail after the cap fails until the window resets.', 'wp-easycart' ), __( 'Move to a transactional provider without a daily cap', 'wp-easycart' ) ),
				array( array( 'dmarc', 'spf', 'dkim', 'unauthenticated', '5.7.26' ), __( 'The receiving server rejected the mail for failing authentication (SPF/DKIM/DMARC).', 'wp-easycart' ), __( 'Add SPF and DKIM records for your sending service', 'wp-easycart' ) ),
				array( array( 'mailer failed to load', 'phpmailer' ), __( 'PHPMailer could not be loaded on this server.', 'wp-easycart' ), __( 'Switch to WordPress mail', 'wp-easycart' ) ),
			);
			foreach ( $map as $m ) { foreach ( $m[0] as $needle ) { if ( false !== strpos( $e, $needle ) ) { return array( 'meaning' => $m[1], 'fix' => $m[2] ); } } }
			return array( 'meaning' => __( 'The mail server returned an error.', 'wp-easycart' ), 'fix' => __( 'Check the email settings and send a test', 'wp-easycart' ) );
		}

		/* ------------------------------------------------------------------ */
		/* Pre-flight checks                                                   */
		/* ------------------------------------------------------------------ */

		/**
		 * Warning-free dns_get_record(). Some hosts disable it or let the resolver emit warnings on failure;
		 * the caller gets an array ( possibly empty ), or null when lookups are not possible at all.
		 *
		 * @since 6.0.0
		 * @param string $name Host name to query.
		 * @param int    $type DNS_* constant ( or a sum of them ).
		 * @return array|null
		 */
		public static function dns_lookup( $name, $type ) {
			if ( ! function_exists( 'dns_get_record' ) ) {
				return null;
			}
			set_error_handler( array( __CLASS__, 'silence_dns_error' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- scoped to this one lookup so a failing resolver cannot print a warning into the admin page; restored right after.
			try {
				$records = dns_get_record( (string) $name, (int) $type );
			} catch ( Throwable $e ) {
				$records = array();
			}
			restore_error_handler();
			return is_array( $records ) ? $records : array();
		}

		/** Error handler active while a DNS lookup runs: swallow the warning and report nothing. @since 6.0.0 */
		public static function silence_dns_error() {
			return true;
		}

		/**
		 * The merchant pressed "Run again" ( wp_ajax_ecv2_email_run_checks ): DNS answers must not come from cache.
		 *
		 * @since 6.0.0
		 * @return bool
		 */
		public static function checks_forced() {
			return function_exists( 'doing_action' ) && doing_action( 'wp_ajax_ecv2_email_run_checks' );
		}

		/**
		 * SPF record, DKIM selector and DMARC record for one domain, cached per domain for DNS_CACHE_TTL so the
		 * Settings > Email page does not repeat up to 18 lookups on every load.
		 *
		 * @since 6.0.0
		 * @param string    $domain From-domain.
		 * @param bool|null $force  true bypasses and refreshes the cache; null does so only during "Run again".
		 * @return array { spf: string, dkim: string|false, dmarc: string, selectors: string, checked: int }
		 */
		public static function dns_checks( $domain, $force = null ) {
			$domain    = strtolower( trim( (string) $domain ) );
			$selectors = (array) apply_filters( 'wp_easycart_email_dkim_selectors', array( 'default', 'google', 'selector1', 'selector2', 'k1', 'pm', 's1', 's2', 'mail', 'dkim', 'mandrill', 'smtp', 'brevo', 'sendgrid', 'mailgun', 'resend' ) );
			$signature = md5( implode( ',', $selectors ) );
			$key       = 'ec_email_dns_' . md5( $domain );
			if ( null === $force ) {
				$force = self::checks_forced();
			}
			if ( ! $force ) {
				$cached = get_transient( $key );
				if ( is_array( $cached ) && isset( $cached['spf'], $cached['dkim'], $cached['dmarc'], $cached['selectors'] ) && $signature === $cached['selectors'] ) {
					return $cached;
				}
			}
			$out = array(
				'spf'       => '',
				'dkim'      => false,
				'dmarc'     => '',
				'selectors' => $signature,
				'checked'   => time(),
			);
			foreach ( (array) self::dns_lookup( $domain, DNS_TXT ) as $r ) {
				if ( isset( $r['txt'] ) && 0 === stripos( $r['txt'], 'v=spf1' ) ) {
					$out['spf'] = $r['txt'];
				}
			}
			foreach ( $selectors as $sel ) {
				$rec = self::dns_lookup( $sel . '._domainkey.' . $domain, DNS_TXT + DNS_CNAME );
				if ( is_array( $rec ) && count( $rec ) ) {
					$out['dkim'] = (string) $sel;
					break;
				}
			}
			foreach ( (array) self::dns_lookup( '_dmarc.' . $domain, DNS_TXT ) as $r ) {
				if ( isset( $r['txt'] ) && 0 === stripos( $r['txt'], 'v=dmarc1' ) ) {
					$out['dmarc'] = $r['txt'];
				}
			}
			set_transient( $key, $out, self::DNS_CACHE_TTL );
			return $out;
		}

		/**
		 * Pre-flight delivery checks shown on Settings > Email.
		 *
		 * @param bool|null $force DNS lookups: true re-runs them and refreshes the per-domain cache, null ( default )
		 *                         does so only during the "Run again" request. Added in 6.0.0.
		 * @return array of { key, status ok|warn|bad|skip, title, detail, fix_label, fix_url }
		 */
		public static function checks( $force = null ) {
			$out = array();
			$transport = self::configured_transport();
			$from_raw = trim( (string) stripslashes( get_option( 'ec_option_order_from_email' ) ) );
			$from = preg_match( '/<([^>]+)>/', $from_raw, $m ) ? $m[1] : $from_raw;
			$from_domain = strtolower( substr( strrchr( $from, '@' ), 1 ) );
			$site_domain = strtolower( preg_replace( '/^www\./', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) );
			$settings_url = admin_url( 'admin.php?page=wp-easycart-settings&subpage=email' );

			/* 1. free-mail from address */
			$free = array( 'gmail.com', 'googlemail.com', 'yahoo.com', 'yahoo.co.uk', 'hotmail.com', 'outlook.com', 'live.com', 'aol.com', 'icloud.com', 'me.com', 'msn.com', 'protonmail.com', 'proton.me' );
			/* 'reason', 'from', 'domain' and 'site_domain' feed the "How to fix" help in the admin ( @since 6.0.0 ). */
			$from_extra = array( 'from' => $from, 'domain' => $from_domain, 'site_domain' => $site_domain );
			if ( '' === $from || ! is_email( $from ) ) {
				$out[] = array_merge( array( 'key' => 'from', 'status' => 'bad', 'title' => __( 'No valid "from" address', 'wp-easycart' ), 'detail' => __( 'Order emails need a sender address. Receivers reject mail without one.', 'wp-easycart' ), 'fix_label' => __( 'Set the from address', 'wp-easycart' ), 'fix_url' => $settings_url, 'reason' => 'none' ), $from_extra );
			} else if ( in_array( $from_domain, $free, true ) ) {
				$out[] = array_merge( array( 'key' => 'from', 'status' => 'bad', 'title' => sprintf( __( 'Sending "from" a %s address', 'wp-easycart' ), $from_domain ), 'detail' => sprintf( __( 'Order emails are sent from %1$s. Gmail, Yahoo and Microsoft reject or spam-folder mail claiming to be from their domains when it isn\'t sent by them. Use an address on your own domain, e.g. orders@%2$s.', 'wp-easycart' ), $from, $site_domain ), 'fix_label' => __( 'Change from address', 'wp-easycart' ), 'fix_url' => $settings_url, 'reason' => 'free' ), $from_extra );
			} else if ( $from_domain && $site_domain && $from_domain !== $site_domain && ! str_ends_with_compat( $from_domain, '.' . $site_domain ) && ! str_ends_with_compat( $site_domain, '.' . $from_domain ) ) {
				$out[] = array_merge( array( 'key' => 'from', 'status' => 'warn', 'title' => __( 'From address is on a different domain than the store', 'wp-easycart' ), 'detail' => sprintf( __( 'Mail is sent from %1$s but the store is %2$s. That is fine if you control both domains and %1$s has SPF/DKIM for your sending service.', 'wp-easycart' ), $from_domain, $site_domain ), 'fix_label' => '', 'fix_url' => '', 'reason' => 'other_domain' ), $from_extra );
			} else {
				$out[] = array_merge( array( 'key' => 'from', 'status' => 'ok', 'title' => __( 'From address is on your own domain', 'wp-easycart' ), 'detail' => $from, 'fix_label' => '', 'fix_url' => '', 'reason' => '' ), $from_extra );
			}

			/* 2. transport path vs SMTP plugin */
			$smtp_plugins = self::detect_smtp_plugins();
			$mailer = ( 'wp_mail' === $transport ) ? self::detect_mailer() : null;
			if ( 'wp_mail' !== $transport && $smtp_plugins ) {
				$out[] = array( 'key' => 'path', 'status' => 'bad', 'title' => __( 'Your SMTP plugin isn\'t in the path', 'wp-easycart' ), 'detail' => sprintf( __( '%1$s is active, but EasyCart is set to use its %2$s. SMTP plugins only affect WordPress mail, so order emails bypass it.', 'wp-easycart' ), implode( ', ', $smtp_plugins ), self::transport_label( $transport ) ), 'fix_label' => __( 'Switch to WordPress mail', 'wp-easycart' ), 'fix_url' => $settings_url, 'reason' => 'bypassed' );
			} else if ( 'wp_mail' === $transport && $mailer ) {
				/* A mail plugin sends ( it replaced wp_mail() or is a known SMTP / API plugin ): that is the recommended setup. */
				$out[] = array(
					'key'       => 'path',
					'status'    => 'ok',
					/* translators: %s: mail plugin name, e.g. Post SMTP */
					'title'     => sprintf( __( 'Your emails are sent by %s', 'wp-easycart' ), $mailer['name'] ),
					/* translators: %s: mail plugin name */
					'detail'    => sprintf( __( 'Good: a dedicated mail plugin usually delivers better than your server\'s default mail. Send a test from %s and confirm it reports success.', 'wp-easycart' ), $mailer['name'] ),
					'fix_label' => '',
					'fix_url'   => '',
					'reason'    => 'mailer',
					'mailer'    => $mailer,
				);
			} else if ( 'wp_mail' === $transport && ! has_action( 'phpmailer_init' ) ) {
				$out[] = array( 'key' => 'path', 'status' => 'warn', 'title' => __( 'No mail plugin: emails use your server\'s basic mail', 'wp-easycart' ), 'detail' => __( 'Many inboxes treat mail from a web server as suspicious, so receipts can land in spam or go missing. Follow the 3 steps in "Improve delivery" to fix it.', 'wp-easycart' ), 'fix_label' => __( 'How to set up an SMTP plugin', 'wp-easycart' ), 'fix_url' => 'https://wpeasycart.com/docs/email-delivery', 'reason' => 'no_plugin', 'guide' => true );
			} else if ( 'wp_mail' === $transport ) {
				$out[] = array( 'key' => 'path', 'status' => 'ok', 'title' => __( 'WordPress mail with a mail plugin', 'wp-easycart' ), 'detail' => __( 'Another plugin configures the mailer.', 'wp-easycart' ), 'fix_label' => '', 'fix_url' => '', 'reason' => 'configured' );
			} else {
				$out[] = array( 'key' => 'path', 'status' => 'warn', 'title' => sprintf( __( 'Using %s', 'wp-easycart' ), self::transport_label( $transport ) ), 'detail' => __( 'This works, but WordPress mail plus a dedicated mail plugin is what we recommend: one place to configure, one set of logs, and API transports that hosts can\'t block.', 'wp-easycart' ), 'fix_label' => __( 'Switch to WordPress mail', 'wp-easycart' ), 'fix_url' => $settings_url, 'reason' => 'builtin', 'guide' => ( 'plugin_mail' === $transport ) );
			}
			$service = self::sending_service( $mailer );

			/* 3-5. DNS */
			if ( $from_domain && function_exists( 'dns_get_record' ) ) {
				$dns = self::dns_checks( $from_domain, $force );
				$spf = $dns['spf'];
				$services = self::sending_services();
				$service_include = ( $service && isset( $services[ $service ] ) ) ? $services[ $service ]['spf'] : '';
				if ( '' === $spf ) { $out[] = array( 'key' => 'spf', 'status' => 'warn', 'title' => __( 'No SPF record', 'wp-easycart' ), 'detail' => sprintf( __( '%s has no SPF record, so receivers can\'t tell which servers may send for it. Your mail provider publishes the exact record to add.', 'wp-easycart' ), $from_domain ), 'fix_label' => __( 'What is SPF?', 'wp-easycart' ), 'fix_url' => 'https://wpeasycart.com/docs/email-delivery#spf', 'domain' => $from_domain, 'service' => $service ); }
				else { $out[] = array( 'key' => 'spf', 'status' => 'ok', 'title' => __( 'SPF record present', 'wp-easycart' ), 'detail' => $spf, 'fix_label' => '', 'fix_url' => '', 'domain' => $from_domain, 'service' => $service, 'record' => $spf, 'service_missing' => ( '' !== $service_include && false === stripos( $spf, 'include:' . $service_include ) ) ); }
				$dkim_found = $dns['dkim'];
				if ( $dkim_found ) { $out[] = array( 'key' => 'dkim', 'status' => 'ok', 'title' => __( 'DKIM record found', 'wp-easycart' ), 'detail' => sprintf( __( 'selector "%s"', 'wp-easycart' ), $dkim_found ), 'fix_label' => '', 'fix_url' => '' ); }
				else { $out[] = array( 'key' => 'dkim', 'status' => 'warn', 'title' => __( 'No DKIM signature found', 'wp-easycart' ), 'detail' => __( 'We checked the common selectors and found none. Unsigned mail is increasingly filtered. Your sending service gives you one or two CNAME records that fix this.', 'wp-easycart' ), 'fix_label' => __( 'How to add DKIM', 'wp-easycart' ), 'fix_url' => 'https://wpeasycart.com/docs/email-delivery#dkim', 'domain' => $from_domain, 'service' => $service ); }
				$dm = $dns['dmarc'];
				$out[] = '' === $dm
					? array( 'key' => 'dmarc', 'status' => 'warn', 'title' => __( 'No DMARC record', 'wp-easycart' ), 'detail' => __( 'Gmail and Yahoo expect a DMARC record from anyone sending them volume. A monitoring-only record ( p=none ) is enough to start.', 'wp-easycart' ), 'fix_label' => __( 'Add a DMARC record', 'wp-easycart' ), 'fix_url' => 'https://wpeasycart.com/docs/email-delivery#dmarc', 'domain' => $from_domain, 'from' => $from )
					: array( 'key' => 'dmarc', 'status' => 'ok', 'title' => __( 'DMARC record present', 'wp-easycart' ), 'detail' => $dm, 'fix_label' => '', 'fix_url' => '', 'domain' => $from_domain );
			} else if ( $from_domain ) {
				$out[] = array( 'key' => 'dns', 'status' => 'skip', 'title' => __( 'DNS checks unavailable', 'wp-easycart' ), 'detail' => __( 'This server does not allow DNS lookups from PHP, so SPF/DKIM/DMARC could not be checked.', 'wp-easycart' ), 'fix_label' => '', 'fix_url' => '', 'domain' => $from_domain );
			}

			/* 6. ports ( only when the plugin's SMTP is in use ) */
			if ( 'plugin_smtp' === $transport ) {
				$host = (string) get_option( 'ec_option_order_from_smtp_host' ); $port = (int) get_option( 'ec_option_order_from_smtp_port' );
				if ( $host && $port ) {
					$fp = @fsockopen( $host, $port, $errno, $errstr, 3 );
					if ( $fp ) { fclose( $fp ); $out[] = array( 'key' => 'port', 'status' => 'ok', 'title' => sprintf( __( 'Can reach %1$s:%2$d', 'wp-easycart' ), $host, $port ), 'detail' => '', 'fix_label' => '', 'fix_url' => '' ); }
					else { $out[] = array( 'key' => 'port', 'status' => 'bad', 'title' => sprintf( __( 'Cannot reach %1$s:%2$d', 'wp-easycart' ), $host, $port ), 'detail' => sprintf( __( '%s. Hosts commonly block outbound SMTP; this is the classic "worked on staging, fails in production".', 'wp-easycart' ), $errstr ? $errstr : __( 'Connection failed', 'wp-easycart' ) ), 'fix_label' => __( 'Ask your host, or switch to WordPress mail + an API plugin', 'wp-easycart' ), 'fix_url' => $settings_url ); }
				}
			}

			/* 7. mailer overrides. Replacing wp_mail() is how most mail plugins work, so that alone is good news ( reported
			   on the 'path' row ). Only warn when a second mail plugin is also active, because its settings are ignored. */
			if ( 'wp_mail' === $transport && $mailer && 'override' === $mailer['source'] ) {
				$others = array_values( array_diff( $smtp_plugins, array( $mailer['name'] ) ) );
				if ( $others ) {
					$out[] = array(
						'key'       => 'override',
						'status'    => 'warn',
						'title'     => __( 'More than one mail plugin is active', 'wp-easycart' ),
						/* translators: 1: the plugin that sends, 2: the other active mail plugins */
						'detail'    => sprintf( __( '%1$s sends your email, so the settings in %2$s are ignored. Keep one mail plugin and turn the other off.', 'wp-easycart' ), $mailer['name'], implode( ', ', $others ) ),
						'fix_label' => '',
						'fix_url'   => '',
						'reason'    => 'two_plugins',
						'mailer'    => $mailer,
						'others'    => $others,
					);
				}
			}

			/* 8. volume */
			if ( self::tables_exist() ) {
				global $wpdb;
				$yesterday = (int) $wpdb->get_var( "SELECT COUNT(*) FROM ec_email_log WHERE status = 'sent' AND created_at >= DATE_SUB( NOW(), INTERVAL 1 DAY )" );
				if ( in_array( $from_domain, array( 'gmail.com', 'googlemail.com' ), true ) || false !== stripos( (string) get_option( 'ec_option_order_from_smtp_host' ), 'gmail' ) ) {
					if ( $yesterday >= 400 ) { $out[] = array( 'key' => 'volume', 'status' => 'warn', 'title' => __( 'Volume near Gmail\'s daily limit', 'wp-easycart' ), 'detail' => sprintf( __( '%d emails in the last 24 hours; Gmail caps at 500/day and mail after the cap fails silently.', 'wp-easycart' ), $yesterday ), 'fix_label' => __( 'Move to a transactional provider', 'wp-easycart' ), 'fix_url' => 'https://wpeasycart.com/docs/email-delivery' ); }
				}
			}
			return apply_filters( 'wp_easycart_email_checks', $out );
		}

		/** Names of the active SMTP / API mail plugins EasyCart knows about. */
		public static function detect_smtp_plugins() {
			$active = self::active_plugin_files();
			$found  = array();
			$plugins = self::mailer_plugins();
			foreach ( self::active_mailer_slugs( $active ) as $slug ) {
				$found[] = $plugins[ $slug ]['name'];
			}
			return apply_filters( 'wp_easycart_email_smtp_plugins', $found, $active );
		}

		/* ------------------------------------------------------------------ */
		/* Mail plugins and sending services ( @since 6.0.0 )                  */
		/* ------------------------------------------------------------------ */

		/**
		 * Known mail plugins, keyed by an internal slug.
		 *
		 *   name     Plugin name shown to the merchant.
		 *   files    Main plugin files, for "is it active".
		 *   folders  Plugin folders, to recognise the plugin that replaced wp_mail() from its file path.
		 *   settings wp-admin path of the plugin's settings screen ( '' sends the merchant to the Plugins page ).
		 *   test     wp-admin path of its test email screen ( '' means the test lives on the settings screen ).
		 *   where    Where the test is, in words.
		 *   service  Sending service key ( see sending_services() ) for service-specific plugins.
		 *   passive  True when being active does not mean it sends WordPress mail; only recognised as the wp_mail() owner.
		 *
		 * @since 6.0.0
		 * @return array
		 */
		public static function mailer_plugins() {
			static $plugins = null;
			if ( null !== $plugins ) {
				return $plugins;
			}
			$list = array(
				'wp-mail-smtp'   => array(
					'name'     => 'WP Mail SMTP',
					'files'    => array( 'wp-mail-smtp/wp_mail_smtp.php', 'wp-mail-smtp-pro/wp_mail_smtp.php' ),
					'folders'  => array( 'wp-mail-smtp', 'wp-mail-smtp-pro' ),
					'settings' => 'admin.php?page=wp-mail-smtp',
					'test'     => 'admin.php?page=wp-mail-smtp-tools&tab=test',
					'where'    => __( 'WP Mail SMTP › Tools › Email Test', 'wp-easycart' ),
				),
				'fluent-smtp'    => array(
					'name'     => 'FluentSMTP',
					'files'    => array( 'fluent-smtp/fluent-smtp.php' ),
					'folders'  => array( 'fluent-smtp' ),
					'settings' => 'options-general.php?page=fluent-mail#/',
					'test'     => 'options-general.php?page=fluent-mail#/test',
					'where'    => __( 'Settings › FluentSMTP › Email Test', 'wp-easycart' ),
				),
				'post-smtp'      => array(
					'name'     => 'Post SMTP',
					'files'    => array( 'post-smtp/postman-smtp.php' ),
					'folders'  => array( 'post-smtp' ),
					'settings' => 'admin.php?page=postman',
					'test'     => '',
					'where'    => __( 'Post SMTP › Dashboard › Send a test email', 'wp-easycart' ),
				),
				'easy-wp-smtp'   => array(
					'name'     => 'Easy WP SMTP',
					'files'    => array( 'easy-wp-smtp/easy-wp-smtp.php' ),
					'folders'  => array( 'easy-wp-smtp' ),
					'settings' => 'admin.php?page=easy-wp-smtp',
					'test'     => '',
					'where'    => __( 'Easy WP SMTP › Tools › Email Test', 'wp-easycart' ),
				),
				'smtp-mailer'    => array(
					'name'     => 'SMTP Mailer',
					'files'    => array( 'smtp-mailer/main.php' ),
					'folders'  => array( 'smtp-mailer' ),
					'settings' => 'options-general.php?page=smtp-mailer-settings',
					'test'     => '',
					'where'    => __( 'Settings › SMTP Mailer › Test Email tab', 'wp-easycart' ),
				),
				'gmail-smtp'     => array(
					'name'     => 'Gmail SMTP',
					'files'    => array( 'gmail-smtp/main.php' ),
					'folders'  => array( 'gmail-smtp' ),
					'settings' => 'options-general.php?page=gmail-smtp-settings',
					'test'     => '',
					'where'    => __( 'Settings › Gmail SMTP › Test Email tab', 'wp-easycart' ),
					'service'  => 'google',
				),
				'wp-smtp'        => array(
					'name'    => 'WP SMTP',
					'files'   => array( 'wp-smtp/wp-smtp.php' ),
					'folders' => array( 'wp-smtp' ),
				),
				'sendgrid'       => array(
					'name'     => 'SendGrid',
					'files'    => array( 'sendgrid-email-delivery-simplified/wpsendgrid.php' ),
					'folders'  => array( 'sendgrid-email-delivery-simplified' ),
					'settings' => 'options-general.php?page=sendgrid-settings',
					'service'  => 'sendgrid',
				),
				'mailgun'        => array(
					'name'     => 'Mailgun',
					'files'    => array( 'mailgun/mailgun.php' ),
					'folders'  => array( 'mailgun' ),
					'settings' => 'options-general.php?page=mailgun',
					'where'    => __( 'Settings › Mailgun › Test Configuration', 'wp-easycart' ),
					'service'  => 'mailgun',
				),
				'brevo'          => array(
					'name'     => 'Brevo',
					'files'    => array( 'mailin/sendinblue.php', 'brevo/brevo.php' ),
					'folders'  => array( 'mailin', 'brevo' ),
					'settings' => 'admin.php?page=sib_page_home',
					'service'  => 'brevo',
				),
				'wp-offload-ses' => array(
					'name'     => 'WP Offload SES',
					'files'    => array( 'wp-ses/wp-ses.php', 'wp-offload-ses/wp-offload-ses.php', 'wp-offload-ses-lite/wp-offload-ses.php' ),
					'folders'  => array( 'wp-ses', 'wp-offload-ses', 'wp-offload-ses-lite' ),
					'settings' => 'options-general.php?page=wp-offload-ses',
					'service'  => 'ses',
				),
				'postmark'       => array(
					'name'     => 'Postmark',
					'files'    => array( 'postmark-approved-wordpress-plugin/postmark.php' ),
					'folders'  => array( 'postmark-approved-wordpress-plugin' ),
					'settings' => 'options-general.php?page=pm-admin',
					'service'  => 'postmark',
				),
				'mailersend'     => array(
					'name'    => 'MailerSend',
					'files'   => array( 'mailersend-official/mailersend.php' ),
					'folders' => array( 'mailersend-official', 'mailersend-official-smtp-integration' ),
					'service' => 'mailersend',
				),
				'sparkpost'      => array(
					'name'     => 'SparkPost',
					'files'    => array( 'sparkpost/wordpress-sparkpost.php' ),
					'folders'  => array( 'sparkpost' ),
					'settings' => 'options-general.php?page=wpsp-setting-admin',
					'service'  => 'sparkpost',
				),
				'mailpoet'       => array(
					'name'     => 'MailPoet',
					'files'    => array( 'mailpoet/mailpoet.php' ),
					'folders'  => array( 'mailpoet' ),
					'settings' => 'admin.php?page=mailpoet-settings#mta',
					'where'    => __( 'MailPoet › Settings › Send With', 'wp-easycart' ),
					'passive'  => true,
				),
			);
			$defaults = array( 'name' => '', 'files' => array(), 'folders' => array(), 'settings' => '', 'test' => '', 'where' => '', 'service' => '', 'passive' => false );
			$plugins  = array();
			foreach ( (array) apply_filters( 'wp_easycart_email_mailer_plugins', $list ) as $slug => $plugin ) {
				if ( is_array( $plugin ) && ! empty( $plugin['name'] ) ) {
					$plugins[ $slug ] = array_merge( $defaults, $plugin );
				}
			}
			return $plugins;
		}

		/** Active plugin files, including network-activated ones. */
		private static function active_plugin_files() {
			$active = (array) get_option( 'active_plugins', array() );
			if ( is_multisite() ) {
				$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
			}
			return $active;
		}

		/** Slugs from mailer_plugins() that are active and send WordPress mail when active. */
		private static function active_mailer_slugs( $active ) {
			$slugs = array();
			foreach ( self::mailer_plugins() as $slug => $plugin ) {
				if ( $plugin['passive'] ) {
					continue;
				}
				if ( array_intersect( $plugin['files'], $active ) ) {
					$slugs[] = $slug;
				}
			}
			return $slugs;
		}

		/** File that defines wp_mail() when it is not WordPress core, '' otherwise. */
		public static function wp_mail_override_file() {
			if ( ! function_exists( 'wp_mail' ) ) {
				return '';
			}
			try {
				$reflection = new ReflectionFunction( 'wp_mail' );
				$file       = str_replace( '\\', '/', (string) $reflection->getFileName() );
			} catch ( Exception $e ) {
				return '';
			}
			return ( '' !== $file && false === strpos( $file, '/wp-includes/' ) ) ? $file : '';
		}

		/**
		 * The plugin that sends WordPress mail, if any.
		 *
		 * @since 6.0.0
		 * @return array|null { slug, name, source ( override|active ), file, settings_url, test_url, where, service, known }
		 */
		public static function detect_mailer() {
			$plugins  = self::mailer_plugins();
			$override = self::wp_mail_override_file();
			$mailer   = null;
			if ( '' !== $override ) {
				$location = self::code_location( $override );
				foreach ( $plugins as $slug => $plugin ) {
					if ( '' !== $location['folder'] && in_array( $location['folder'], $plugin['folders'], true ) ) {
						$mailer = self::mailer_info( $slug, $plugin, 'override', $override );
						break;
					}
				}
				if ( ! $mailer ) {
					$mailer = self::mailer_info( '', array( 'name' => self::code_owner_name( $location, $override ), 'settings' => ( 'other' === $location['type'] ? 'plugins.php' : '' ) ), 'override', $override );
				}
			} else {
				$slugs = self::active_mailer_slugs( self::active_plugin_files() );
				if ( $slugs ) {
					$mailer = self::mailer_info( $slugs[0], $plugins[ $slugs[0] ], 'active', '' );
				} else {
					$names = self::detect_smtp_plugins();
					if ( $names ) {
						$mailer = self::mailer_info( '', array( 'name' => (string) reset( $names ) ), 'active', '' );
					}
				}
			}
			return apply_filters( 'wp_easycart_email_mailer', $mailer );
		}

		private static function mailer_info( $slug, $plugin, $source, $file ) {
			$plugin   = array_merge( array( 'settings' => '', 'test' => '', 'where' => '', 'service' => '' ), $plugin );
			$fallback = admin_url( 'plugins.php?plugin_status=all&s=' . rawurlencode( $plugin['name'] ) );
			$settings = '' !== $plugin['settings'] ? admin_url( $plugin['settings'] ) : $fallback;
			return array(
				'slug'         => $slug,
				'name'         => $plugin['name'],
				'source'       => $source,
				'file'         => '' !== $file ? str_replace( str_replace( '\\', '/', ABSPATH ), '', $file ) : '',
				'settings_url' => $settings,
				'test_url'     => '' !== $plugin['test'] ? admin_url( $plugin['test'] ) : $settings,
				'where'        => $plugin['where'],
				'service'      => $plugin['service'],
				'known'        => '' !== $slug,
			);
		}

		/** { type: plugin|mu|other, folder, path } for a PHP file. */
		private static function code_location( $file ) {
			$file = str_replace( '\\', '/', $file );
			$dirs = array(
				'plugin' => defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : '',
				'mu'     => defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : '',
			);
			foreach ( $dirs as $type => $dir ) {
				$base = rtrim( str_replace( '\\', '/', (string) $dir ), '/' ) . '/';
				if ( '/' !== $base && 0 === strpos( $file, $base ) ) {
					$relative = substr( $file, strlen( $base ) );
					$parts    = explode( '/', $relative );
					return array( 'type' => $type, 'folder' => ( 'plugin' === $type && count( $parts ) > 1 ) ? $parts[0] : '', 'path' => $relative );
				}
			}
			return array( 'type' => 'other', 'folder' => '', 'path' => $file );
		}

		/** Readable name for whatever defines wp_mail() when it is not a known mail plugin. */
		private static function code_owner_name( $location, $file ) {
			if ( 'other' !== $location['type'] ) {
				if ( ! function_exists( 'get_plugin_data' ) && defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}
				if ( 'plugin' === $location['type'] && '' !== $location['folder'] && function_exists( 'get_plugins' ) ) {
					foreach ( get_plugins( '/' . $location['folder'] ) as $data ) {
						if ( ! empty( $data['Name'] ) ) {
							return wp_strip_all_tags( $data['Name'] );
						}
					}
				}
				if ( function_exists( 'get_plugin_data' ) && file_exists( $file ) ) {
					$data = get_plugin_data( $file, false, false );
					if ( ! empty( $data['Name'] ) ) {
						return wp_strip_all_tags( $data['Name'] );
					}
				}
				if ( '' !== $location['folder'] ) {
					return ucwords( str_replace( array( '-', '_' ), ' ', $location['folder'] ) );
				}
				return 'mu' === $location['type'] ? __( 'a must-use plugin', 'wp-easycart' ) : basename( $file );
			}
			return __( 'custom code on your site', 'wp-easycart' );
		}

		/**
		 * Sending services the help text knows. spf is the SPF include ( '' when the service does not need one ).
		 *
		 * @since 6.0.0
		 * @return array
		 */
		public static function sending_services() {
			return apply_filters(
				'wp_easycart_email_sending_services',
				array(
					'google'     => array( 'name' => 'Google Workspace', 'spf' => '_spf.google.com', 'dkim' => __( 'Google Admin console › Apps › Google Workspace › Gmail › Authenticate email › Generate new record', 'wp-easycart' ) ),
					'microsoft'  => array( 'name' => 'Microsoft 365', 'spf' => 'spf.protection.outlook.com', 'dkim' => __( 'Microsoft Defender portal › Email & collaboration › Policies & rules › Threat policies › Email authentication settings › DKIM', 'wp-easycart' ) ),
					'sendgrid'   => array( 'name' => 'SendGrid', 'spf' => 'sendgrid.net', 'dkim' => __( 'SendGrid › Settings › Sender Authentication › Authenticate your domain', 'wp-easycart' ) ),
					'mailgun'    => array( 'name' => 'Mailgun', 'spf' => 'mailgun.org', 'dkim' => __( 'Mailgun › Sending › Domains › your domain › DNS records', 'wp-easycart' ) ),
					'brevo'      => array( 'name' => 'Brevo', 'spf' => 'spf.brevo.com', 'dkim' => __( 'Brevo › Senders, Domains & Dedicated IPs › Domains › Authenticate', 'wp-easycart' ) ),
					'ses'        => array( 'name' => 'Amazon SES', 'spf' => 'amazonses.com', 'dkim' => __( 'Amazon SES console › Identities › your domain › Authentication (Easy DKIM)', 'wp-easycart' ) ),
					'postmark'   => array( 'name' => 'Postmark', 'spf' => '', 'dkim' => __( 'Postmark › Sender Signatures › Domains › DNS Settings', 'wp-easycart' ) ),
					'sparkpost'  => array( 'name' => 'SparkPost', 'spf' => 'sparkpostmail.com', 'dkim' => __( 'SparkPost › Configuration › Domains', 'wp-easycart' ) ),
					'zoho'       => array( 'name' => 'Zoho Mail', 'spf' => 'zohomail.com', 'dkim' => __( 'Zoho Mail Admin Console › Domains › Email Configuration › DKIM', 'wp-easycart' ) ),
					'mailjet'    => array( 'name' => 'Mailjet', 'spf' => 'spf.mailjet.com', 'dkim' => __( 'Mailjet › Account settings › Domains & senders', 'wp-easycart' ) ),
					'mailersend' => array( 'name' => 'MailerSend', 'spf' => '_spf.mailersend.net', 'dkim' => __( 'MailerSend › Domains › Manage › DNS records', 'wp-easycart' ) ),
				)
			);
		}

		/**
		 * Best guess at the service that actually sends: the mail plugin's own settings, or EasyCart's SMTP host.
		 * Returns a sending_services() key or ''. Advisory only.
		 *
		 * @since 6.0.0
		 * @param array|null $mailer detect_mailer() result.
		 * @return string
		 */
		public static function sending_service( $mailer = null ) {
			$key  = '';
			$host = '';
			if ( is_array( $mailer ) && ! empty( $mailer['service'] ) ) {
				$key = $mailer['service'];
			} else if ( is_array( $mailer ) && 'wp-mail-smtp' === $mailer['slug'] ) {
				$opt  = get_option( 'wp_mail_smtp', array() );
				$key  = ( is_array( $opt ) && isset( $opt['mail']['mailer'] ) ) ? (string) $opt['mail']['mailer'] : '';
				$host = ( is_array( $opt ) && isset( $opt['smtp']['host'] ) ) ? (string) $opt['smtp']['host'] : '';
			} else if ( is_array( $mailer ) && 'fluent-smtp' === $mailer['slug'] ) {
				$opt = get_option( 'fluentmail-settings', array() );
				if ( is_array( $opt ) && ! empty( $opt['connections'] ) && is_array( $opt['connections'] ) ) {
					$first = reset( $opt['connections'] );
					$key   = isset( $first['provider_settings']['provider'] ) ? (string) $first['provider_settings']['provider'] : '';
					$host  = isset( $first['provider_settings']['host'] ) ? (string) $first['provider_settings']['host'] : '';
				}
			} else if ( is_array( $mailer ) && 'post-smtp' === $mailer['slug'] ) {
				$opt  = get_option( 'postman_options', array() );
				$key  = ( is_array( $opt ) && isset( $opt['transport_type'] ) ) ? (string) $opt['transport_type'] : '';
				$host = ( is_array( $opt ) && isset( $opt['hostname'] ) ) ? (string) $opt['hostname'] : '';
			} else if ( 'plugin_smtp' === self::configured_transport() ) {
				$host = (string) get_option( 'ec_option_order_from_smtp_host' );
			}
			$aliases = array(
				'sendinblue' => 'brevo',
				'amazonses'  => 'ses',
				'amazonaws'  => 'ses',
				'gmail'      => 'google',
				'google'     => 'google',
				'outlook'    => 'microsoft',
				'office365'  => 'microsoft',
				'sendgrid'   => 'sendgrid',
				'mailgun'    => 'mailgun',
				'brevo'      => 'brevo',
				'postmark'   => 'postmark',
				'sparkpost'  => 'sparkpost',
				'zoho'       => 'zoho',
				'mailjet'    => 'mailjet',
				'mailersend' => 'mailersend',
			);
			$services = self::sending_services();
			foreach ( array( strtolower( $key ), strtolower( $host ) ) as $haystack ) {
				if ( '' === $haystack ) {
					continue;
				}
				if ( isset( $services[ $haystack ] ) ) {
					return $haystack;
				}
				foreach ( $aliases as $needle => $service ) {
					if ( false !== strpos( $haystack, $needle ) ) {
						return $service;
					}
				}
			}
			return '';
		}
	}

	if ( ! function_exists( 'str_ends_with_compat' ) ) {
		function str_ends_with_compat( $haystack, $needle ) { $n = strlen( $needle ); return 0 === $n || substr( $haystack, -$n ) === $needle; }
	}

	ec_email::init();

endif;
