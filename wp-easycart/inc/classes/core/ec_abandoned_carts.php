<?php
/**
 * WP EasyCart — Abandoned carts: snapshot + storefront plumbing ( free core ).
 *
 * ec_tempcart is deleted when an order completes, so nothing today can say a cart was recovered. This class keeps a
 * snapshot per session in ec_abandoned_cart ( written by a sweep and by the early email capture ), attributes orders
 * back to it, expires it, and owns every storefront endpoint the reminders need: signed restore link with a coupon,
 * open pixel, click tracking, unsubscribe. The reminder *sequence* itself is PRO ( ec_abandoned_sequence ) and hooks
 * the 15-minute tick this class schedules: do_action( 'wp_easycart_abandoned_cart_tick_sequence' ).
 *
 * Settings ( option ec_option_abandoned_carts ): idle_minutes ( 60 ), expire_days ( 30 ), track_opens ( 1 ),
 * track_clicks ( 1 ), capture_email ( 1 ). Tracking is on by default and surfaced prominently in the PRO settings
 * for merchants who must turn it off.
 *
 * Statuses: active | reminded | recovered | converted_other | expired | unsubscribed | dismissed
 * Stages:   cart | checkout_started | payment_failed
 *
 * Times: last_activity comes from ec_tempcart.last_changed_date ( MySQL CURRENT_TIMESTAMP ), so every scheduling
 * column on ec_abandoned_cart ( first_seen, last_activity, abandoned_at, next_send_at ) is kept in database time.
 * Use db_time() / db_datetime() for "now", never time() or current_time(), or sends drift by the store's UTC offset.
 *
 * Links ( 6.0.0 ): every link points at the cart page and carries its values URL-encoded. restore_url() is
 * ?ec_ac_restore={id}&ec_act={token}[&ec_coupon=][&ec_acc={step}] and resolves the cart's *current* session when
 * clicked, so it survives session rotation. Older formats are still accepted: ?ec_ac=&ec_act=&ec_acu= ( the old
 * click wrapper, whose inner URL lost its email/key because it was never encoded ) and core's
 * ?ec_load_tempcart=&ec_load_email=&ec_load_key= ( falls back to the customer's live cart after a rotation ).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'ec_abandoned_carts' ) ) :

	final class ec_abandoned_carts {

		const OPTION = 'ec_option_abandoned_carts';
		const TICK = 'wp_easycart_abandoned_cart_tick';
		const SCHEDULE = 'ec_15min';
		const LAST_TICK_OPTION = 'ec_abandoned_carts_last_tick';

		public static function defaults() { return array( 'idle_minutes' => 60, 'expire_days' => 30, 'track_opens' => 1, 'track_clicks' => 1, 'capture_email' => 1, 'sequence_enabled' => 0 ); }
		public static function settings() { $s = get_option( self::OPTION, array() ); return wp_parse_args( is_array( $s ) ? $s : array(), self::defaults() ); }
		public static function save_settings( $in ) {
			$d = self::defaults(); $s = self::settings();
			foreach ( $d as $k => $v ) { if ( array_key_exists( $k, $in ) ) { $s[ $k ] = in_array( $k, array( 'idle_minutes', 'expire_days' ), true ) ? max( 1, (int) $in[ $k ] ) : ( (int) $in[ $k ] ? 1 : 0 ); } }
			update_option( self::OPTION, $s, false ); return $s;
		}

		public static function tables_exist( $recheck = false ) {
			static $ok = null; global $wpdb;
			if ( null === $ok || $recheck ) { $ok = (bool) $wpdb->get_var( "SHOW TABLES LIKE 'ec_abandoned_cart'" ) && (bool) $wpdb->get_var( "SHOW TABLES LIKE 'ec_abandoned_cart_event'" ); }
			return $ok;
		}

		public static function bootstrap() {
			add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
			add_action( 'init', array( __CLASS__, 'maybe_schedule' ), 20 );
			add_action( self::TICK, array( __CLASS__, 'tick' ) );
			add_action( 'init', array( __CLASS__, 'storefront_endpoints' ), 5 );
			add_action( 'wpeasycart_order_success_pre', array( __CLASS__, 'on_order_success' ), 10, 2 );
			add_action( 'wpeasycart_session_rotated', array( __CLASS__, 'on_session_rotated' ), 10, 2 );
			add_action( 'wp_ajax_nopriv_ec_abandoned_capture_email', array( __CLASS__, 'ajax_capture_email' ) );
			add_action( 'wp_ajax_ec_abandoned_capture_email', array( __CLASS__, 'ajax_capture_email' ) );
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_capture' ) );
		}
		public static function cron_schedules( $s ) { if ( ! isset( $s[ self::SCHEDULE ] ) ) { $s[ self::SCHEDULE ] = array( 'interval' => 15 * MINUTE_IN_SECONDS, 'display' => 'Every 15 minutes (EasyCart)' ); } return $s; }

		/**
		 * Self-healing schedule: runs on every init ( front, admin, cron ), so a missing event ( fresh install, PRO
		 * update, cron option wiped, schedule dropped while the filter was not loaded ) is recreated on the next request.
		 *
		 * @return bool True when the event is scheduled after the call.
		 */
		public static function maybe_schedule() {
			if ( wp_next_scheduled( self::TICK ) ) { return true; }
			$r = wp_schedule_event( time() + 60, self::SCHEDULE, self::TICK );
			return ( true === $r || null === $r ) && (bool) wp_next_scheduled( self::TICK );
		}

		/**
		 * What the admin needs to explain automatic sending.
		 *
		 * @since 6.0.0
		 * @return array { next ( UTC ts|0 ), last_tick ( UTC ts|0 ), schedule, cron_disabled, alternate_cron, overdue, missing }
		 */
		public static function schedule_state() {
			$next = (int) wp_next_scheduled( self::TICK );
			return array(
				'next'           => $next,
				'last_tick'      => (int) get_option( self::LAST_TICK_OPTION, 0 ),
				'schedule'       => $next ? (string) wp_get_schedule( self::TICK ) : '',
				'cron_disabled'  => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
				'alternate_cron' => defined( 'ALTERNATE_WP_CRON' ) && ALTERNATE_WP_CRON,
				'overdue'        => $next && $next < time() - 30 * MINUTE_IN_SECONDS,
				'missing'        => ! $next,
			);
		}

		/* ------------------------------------------------------------------ */
		/* Database time                                                       */
		/* ------------------------------------------------------------------ */

		/** "Now" on the database clock as a Unix-style number ( DB datetime strings parse with ts() ). */
		public static function db_time( $refresh = false ) {
			static $offset = null; global $wpdb;
			if ( null === $offset || $refresh ) {
				$now = ( isset( $wpdb ) && is_object( $wpdb ) ) ? $wpdb->get_var( 'SELECT NOW()' ) : '';
				$ts = $now ? strtotime( $now . ' UTC' ) : false;
				$offset = ( false !== $ts ) ? $ts - time() : 0;
			}
			return time() + $offset;
		}
		public static function db_datetime( $ts = null ) { return gmdate( 'Y-m-d H:i:s', null === $ts ? self::db_time() : (int) $ts ); }
		/** Parse a DB datetime string on the same basis as db_time(). */
		public static function ts( $datetime ) { $t = $datetime ? strtotime( $datetime . ' UTC' ) : false; return false === $t ? 0 : $t; }

		/* ------------------------------------------------------------------ */
		/* Capture                                                             */
		/* ------------------------------------------------------------------ */

		/** Build/refresh the snapshot for one session. Returns the abandoned_cart_id or 0 ( no email / no lines ). */
		public static function capture( $session_id ) {
			global $wpdb;
			if ( ! self::tables_exist() ) { return 0; }
			$d = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_tempcart_data WHERE session_id = %s ORDER BY tempcart_data_id DESC LIMIT 1', $session_id ) );
			if ( ! $d ) { return 0; }
			$email = sanitize_email( trim( (string) $d->email ) ); if ( ! is_email( $email ) ) { return 0; }
			$lines = $wpdb->get_results( $wpdb->prepare( 'SELECT t.tempcart_id, t.product_id, t.quantity, t.optionitem_id_1, t.optionitem_id_2, t.optionitem_id_3, t.optionitem_id_4, t.optionitem_id_5, t.last_changed_date, t.hide_from_admin, t.abandoned_cart_email_sent, t.donation_price, p.title, p.model_number, p.price, p.image1, p.stock_quantity, p.show_stock_quantity, p.use_optionitem_quantity_tracking, p.activate_in_store FROM ec_tempcart t LEFT JOIN ec_product p ON p.product_id = t.product_id WHERE t.session_id = %s ORDER BY t.tempcart_id', $session_id ) );
			if ( ! $lines ) { return 0; }
			$items = array(); $subtotal = 0; $count = 0; $last = ''; $sent = 0; $hidden = 0;
			foreach ( $lines as $l ) {
				$price = (float) ( (float) $l->donation_price > 0 ? $l->donation_price : $l->price );
				$opts = array(); for ( $i = 1; $i <= 5; $i++ ) { $oid = (int) $l->{ "optionitem_id_$i" }; if ( $oid ) { $n = $wpdb->get_var( $wpdb->prepare( 'SELECT optionitem_name FROM ec_optionitem WHERE optionitem_id = %d', $oid ) ); if ( $n ) { $opts[] = wp_unslash( $n ); } } }
				$items[] = array( 'product_id' => (int) $l->product_id, 'title' => wp_unslash( (string) $l->title ), 'sku' => (string) $l->model_number, 'qty' => (int) $l->quantity, 'price' => $price, 'options' => $opts, 'image' => (string) $l->image1 );
				$subtotal += $price * (int) $l->quantity; $count += (int) $l->quantity; if ( $l->last_changed_date > $last ) { $last = $l->last_changed_date; } $sent = max( $sent, (int) $l->abandoned_cart_email_sent ); $hidden = max( $hidden, (int) $l->hide_from_admin );
			}
			$stage = 'cart';
			if ( '' !== trim( (string) $d->card_error ) ) { $stage = 'payment_failed'; }
			else if ( '' !== trim( (string) $d->billing_address_line_1 ) || '' !== trim( (string) $d->shipping_method ) || '' !== trim( (string) $d->stripe_paymentintent_id ) ) { $stage = 'checkout_started'; }
			$existing = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_abandoned_cart WHERE session_id = %s', $session_id ) );
			$row = array(
				'session_id' => $session_id, 'email' => $email, 'first_name' => sanitize_text_field( wp_unslash( '' !== (string) $d->billing_first_name ? $d->billing_first_name : $d->first_name ) ), 'last_name' => sanitize_text_field( wp_unslash( '' !== (string) $d->billing_last_name ? $d->billing_last_name : $d->last_name ) ),
				'user_id' => (int) $d->user_id, 'phone' => sanitize_text_field( (string) $d->billing_phone ), 'stage' => $stage, 'items_json' => wp_json_encode( $items ), 'item_count' => $count, 'subtotal' => round( $subtotal, 2 ), 'currency' => (string) get_option( 'ec_option_currency' ),
				'coupon_code' => (string) $d->coupon_code, 'shipping_country' => (string) ( $d->shipping_country ? $d->shipping_country : $d->billing_country ), 'locale' => (string) $d->translate_to, 'card_error' => sanitize_text_field( (string) $d->card_error ), 'last_activity' => $last ? $last : self::db_datetime(),
			);
			$idle = (int) self::settings()['idle_minutes'];
			$row['abandoned_at'] = self::db_datetime( self::ts( $row['last_activity'] ) + $idle * MINUTE_IN_SECONDS );
			$first_send = self::first_send_at( $row );
			/* an address that unsubscribed never gets an open ( remindable ) cart again */
			$suppressed = self::is_suppressed( $email );
			if ( $existing ) {
				/* a live cart moving again: keep the sequence position; if it was expired/dismissed leave it be unless the cart changed */
				if ( in_array( $existing->status, array( 'recovered', 'converted_other', 'unsubscribed' ), true ) ) { return (int) $existing->abandoned_cart_id; }
				if ( 'dismissed' === $existing->status && $existing->items_json === $row['items_json'] ) { return (int) $existing->abandoned_cart_id; }
				if ( 'expired' === $existing->status || 'dismissed' === $existing->status ) { $row['status'] = 'active'; $row['sequence_step'] = 0; $row['next_send_at'] = $first_send; }
				else if ( 0 === (int) $existing->sequence_step ) { $row['next_send_at'] = $first_send; } /* not reminded yet: push the first send out with the new activity */
				if ( $suppressed ) { $row['status'] = 'unsubscribed'; $row['next_send_at'] = null; }
				$wpdb->update( 'ec_abandoned_cart', $row, array( 'abandoned_cart_id' => (int) $existing->abandoned_cart_id ) );
				$id = (int) $existing->abandoned_cart_id;
			} else {
				$status = $hidden ? 'dismissed' : ( $suppressed ? 'unsubscribed' : 'active' );
				$row += array( 'status' => $status, 'first_seen' => $last ? $last : self::db_datetime(), 'sequence_step' => $sent, 'next_send_at' => ( $sent || 'active' !== $status ) ? null : $first_send );
				$wpdb->insert( 'ec_abandoned_cart', $row ); $id = (int) $wpdb->insert_id;
				self::event( $id, 'captured', 0, $stage );
				do_action( 'wp_easycart_abandoned_cart_captured', $id, $row );
			}
			return $id;
		}

		/**
		 * When the first reminder is due for a freshly captured / re-activated cart. Core only knows the idle time;
		 * PRO filters this with the configured step-1 delay ( and the payment-failed shortcut ).
		 *
		 * @param array $row Snapshot being written ( session_id, stage, last_activity, abandoned_at… ).
		 * @return string DB datetime.
		 */
		public static function first_send_at( $row ) {
			$at = apply_filters( 'wp_easycart_abandoned_cart_first_send_at', $row['abandoned_at'], $row );
			return is_string( $at ) && '' !== $at ? $at : $row['abandoned_at'];
		}

		/** Sweep: every session with an email and activity in the last expire_days gets a snapshot. Bounded per run. */
		public static function sweep( $limit = 300 ) {
			global $wpdb; if ( ! self::tables_exist() ) { return 0; }
			$days = (int) self::settings()['expire_days'];
			$sessions = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT t.session_id FROM ec_tempcart t INNER JOIN ec_tempcart_data d ON d.session_id = t.session_id LEFT JOIN ec_abandoned_cart a ON a.session_id = t.session_id WHERE d.email != '' AND t.last_changed_date >= DATE_SUB( NOW(), INTERVAL %d DAY ) AND ( a.abandoned_cart_id IS NULL OR a.last_activity < ( SELECT MAX( t2.last_changed_date ) FROM ec_tempcart t2 WHERE t2.session_id = t.session_id ) ) LIMIT %d", $days, $limit ) );
			$n = 0; foreach ( $sessions as $sid ) { if ( self::capture( $sid ) ) { $n++; } }
			return $n;
		}

		/**
		 * The cart's session id changed ( restore link, login/logout at checkout ). Move the snapshot with it, otherwise
		 * the next sweep sees an unknown session and creates a second row for the same cart, and every later
		 * reminder link points at a session that no longer exists.
		 */
		public static function on_session_rotated( $old_id, $new_id ) {
			global $wpdb;
			$old_id = (string) $old_id; $new_id = (string) $new_id;
			if ( '' === $old_id || '' === $new_id || $old_id === $new_id || ! self::tables_exist() ) { return; }
			if ( $wpdb->get_var( $wpdb->prepare( 'SELECT abandoned_cart_id FROM ec_abandoned_cart WHERE session_id = %s', $new_id ) ) ) { return; }
			$wpdb->update( 'ec_abandoned_cart', array( 'session_id' => $new_id ), array( 'session_id' => $old_id ) );
		}

		/* ------------------------------------------------------------------ */
		/* Attribution + expiry                                                */
		/* ------------------------------------------------------------------ */

		public static function on_order_success( $order_id, $order_row ) {
			if ( ! self::tables_exist() || ! $order_row ) { return; }
			self::attribute_order( (int) $order_id, (string) $order_row->user_email, self::current_session_id(), isset( $order_row->grand_total ) ? (float) $order_row->grand_total : 0, isset( $order_row->order_date ) ? $order_row->order_date : self::db_datetime() );
		}
		/** Same session → recovered; same email, different cart → converted_other ( stop reminding ). */
		public static function attribute_order( $order_id, $email, $session_id = '', $total = 0, $order_date = '' ) {
			global $wpdb; $email = sanitize_email( $email ); if ( ! $email && ! $session_id ) { return; }
			$open = "status IN ( 'active', 'reminded' )";
			if ( $session_id ) {
				$row = $wpdb->get_row( $wpdb->prepare( "SELECT abandoned_cart_id, sequence_step FROM ec_abandoned_cart WHERE session_id = %s AND $open", $session_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $open is a constant fragment.
				if ( $row ) { $wpdb->update( 'ec_abandoned_cart', array( 'status' => 'recovered', 'recovered_at' => self::db_datetime(), 'recovered_order_id' => $order_id, 'recovered_total' => $total, 'next_send_at' => null ), array( 'abandoned_cart_id' => (int) $row->abandoned_cart_id ) ); self::event( (int) $row->abandoned_cart_id, 'recovered', (int) $row->sequence_step, 'order #' . $order_id ); do_action( 'wp_easycart_abandoned_cart_recovered', (int) $row->abandoned_cart_id, $order_id ); }
			}
			if ( $email ) {
				foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT abandoned_cart_id, sequence_step, session_id FROM ec_abandoned_cart WHERE email = %s AND $open", $email ) ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $open is a constant fragment.
					if ( $session_id && $r->session_id === $session_id ) { continue; }
					/* a reminder was sent and the customer bought within its window: count it as recovered even if the cart differs */
					$recovered_by_reminder = (int) $r->sequence_step > 0 && (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM ec_abandoned_cart_event WHERE abandoned_cart_id = %d AND type IN ( 'clicked', 'step_sent' ) AND created_at >= DATE_SUB( NOW(), INTERVAL 7 DAY )", (int) $r->abandoned_cart_id ) );
					$wpdb->update( 'ec_abandoned_cart', array( 'status' => $recovered_by_reminder ? 'recovered' : 'converted_other', 'recovered_at' => self::db_datetime(), 'recovered_order_id' => $order_id, 'recovered_total' => $recovered_by_reminder ? $total : 0, 'next_send_at' => null ), array( 'abandoned_cart_id' => (int) $r->abandoned_cart_id ) );
					self::event( (int) $r->abandoned_cart_id, $recovered_by_reminder ? 'recovered' : 'converted_other', (int) $r->sequence_step, 'order #' . $order_id );
				}
			}
		}
		/** Cron reconciliation for orders that never hit the success page ( webhooks, offline ). */
		public static function reconcile_orders() {
			global $wpdb;
			$rows = $wpdb->get_results( "SELECT a.abandoned_cart_id, a.email, a.session_id, o.order_id, o.grand_total, o.order_date FROM ec_abandoned_cart a INNER JOIN ec_order o ON o.user_email = a.email AND o.order_date >= a.first_seen WHERE a.status IN ( 'active', 'reminded' ) ORDER BY o.order_date ASC LIMIT 200" );
			$done = array();
			foreach ( $rows as $r ) { if ( isset( $done[ $r->abandoned_cart_id ] ) ) { continue; } $done[ $r->abandoned_cart_id ] = 1; self::attribute_order( (int) $r->order_id, $r->email, '', (float) $r->grand_total, $r->order_date ); }
			return count( $done );
		}
		public static function expire() {
			global $wpdb; $days = (int) self::settings()['expire_days'];
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT abandoned_cart_id FROM ec_abandoned_cart WHERE status IN ( 'active', 'reminded' ) AND last_activity < DATE_SUB( NOW(), INTERVAL %d DAY ) LIMIT 500", $days ) );
			foreach ( $ids as $id ) { $wpdb->update( 'ec_abandoned_cart', array( 'status' => 'expired', 'next_send_at' => null ), array( 'abandoned_cart_id' => (int) $id ) ); self::event( (int) $id, 'expired' ); }
			return count( $ids );
		}

		/** Sweep, reconcile, expire ( everything the tick does before the PRO sequence ). Records the run time. */
		public static function housekeeping() {
			if ( ! self::tables_exist() ) { return array(); }
			update_option( self::LAST_TICK_OPTION, time(), false );
			return array( 'captured' => self::sweep(), 'reconciled' => self::reconcile_orders(), 'expired' => self::expire() );
		}

		/** The 15-minute tick: housekeeping, then let PRO run the sequence. */
		public static function tick() {
			if ( ! self::tables_exist() ) { return; }
			self::housekeeping();
			do_action( self::TICK . '_sequence', self::settings() );
		}

		/* ------------------------------------------------------------------ */
		/* Links + storefront endpoints                                        */
		/* ------------------------------------------------------------------ */

		public static function token( $id, $purpose ) { return substr( hash_hmac( 'sha256', $purpose . '|' . (int) $id, wp_salt( 'auth' ) ), 0, 24 ); }
		public static function check_token( $id, $purpose, $t ) { return is_string( $t ) && '' !== $t && hash_equals( self::token( $id, $purpose ), $t ); }
		public static function cart_page_url() {
			$pid = get_option( 'ec_option_cartpage' );
			if ( ! $pid ) { return ''; }
			if ( function_exists( 'icl_object_id' ) && defined( 'ICL_LANGUAGE_CODE' ) ) { $pid = icl_object_id( $pid, 'page', true, ICL_LANGUAGE_CODE ); }
			$url = get_permalink( $pid );
			return is_string( $url ) ? $url : '';
		}
		/**
		 * Base for every reminder link. The cart page, not the home page: stores exclude the cart from full-page caching,
		 * while a cached or redirecting home page silently swallowed the unsubscribe / click endpoints.
		 */
		public static function endpoint_base() {
			$url = self::cart_page_url();
			if ( '' === $url ) { $url = home_url( '/' ); }
			return (string) apply_filters( 'wp_easycart_abandoned_cart_endpoint_base', $url );
		}
		/**
		 * add_query_arg() does NOT encode the values it adds, so each value is rawurlencode()d here. Unencoded, an email
		 * with "+" breaks and a URL passed as a value splits into top-level parameters ( the old click wrapper bug ).
		 */
		public static function build_url( $args, $base = '' ) {
			$enc = array(); foreach ( $args as $k => $v ) { $enc[ $k ] = rawurlencode( (string) $v ); }
			return add_query_arg( $enc, '' !== $base ? $base : self::endpoint_base() );
		}
		public static function clean_code( $code ) { return preg_replace( '/[^A-Za-z0-9\-\$\%]/', '', (string) $code ); }

		/**
		 * Recovery link for a cart. Works from email and when copied from the admin; restores the cart's current session
		 * ( rotations included ), pins $coupon on it, and — when $track and click tracking is on — logs a click for $step.
		 *
		 * @param object $cart   ec_abandoned_cart row ( abandoned_cart_id is all that is needed ).
		 * @param string $coupon Code to apply on arrival ( signed into the token ).
		 * @param bool   $track  Log a "clicked" event ( email links ); false for admin-copied links and tests.
		 * @param int    $step   Sequence step the link was sent in.
		 */
		public static function restore_url( $cart, $coupon = '', $track = true, $step = 0 ) {
			$id = isset( $cart->abandoned_cart_id ) ? (int) $cart->abandoned_cart_id : 0; $coupon = self::clean_code( $coupon );
			$args = array( 'ec_ac_restore' => $id, 'ec_act' => self::token( $id, 'restore|' . $coupon ) );
			if ( '' !== $coupon ) { $args['ec_coupon'] = $coupon; }
			if ( $track && self::settings()['track_clicks'] ) { $args['ec_acc'] = max( 1, (int) $step ); }
			return (string) apply_filters( 'wp_easycart_abandoned_cart_restore_url', self::build_url( $args ), $cart, $coupon, $track, $step );
		}
		public static function unsubscribe_url( $cart ) { $id = isset( $cart->abandoned_cart_id ) ? (int) $cart->abandoned_cart_id : 0; return self::build_url( array( 'ec_ac_unsub' => $id, 'ec_act' => self::token( $id, 'unsub' ) ) ); }
		public static function pixel_url( $cart, $step ) { $id = (int) $cart->abandoned_cart_id; return self::build_url( array( 'ec_ac_open' => $id, 'ec_acs' => (int) $step, 'ec_act' => self::token( $id, 'open' ) ) ); }

		/** Read a query value. Every endpoint below authenticates with a per-cart HMAC token instead of a nonce ( links live in emails ). */
		private static function query( $key ) {
			return isset( $_GET[ $key ] ) && is_scalar( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- emailed links carry an HMAC token ( check_token ), not a nonce.
		}

		public static function storefront_endpoints() {
			if ( '' !== self::query( 'ec_ac_open' ) && '' !== self::query( 'ec_act' ) ) { self::handle_open(); }
			if ( '' !== self::query( 'ec_ac_unsub' ) && '' !== self::query( 'ec_act' ) ) { self::handle_unsubscribe(); }
			if ( '' !== self::query( 'ec_ac_restore' ) && '' !== self::query( 'ec_act' ) ) { self::handle_restore(); }
			if ( '' !== self::query( 'ec_ac' ) && '' !== self::query( 'ec_act' ) ) { self::handle_legacy_click(); }
			if ( '' !== self::query( 'ec_load_tempcart' ) && '' !== self::query( 'ec_load_email' ) && '' !== self::query( 'ec_load_key' ) ) { self::handle_legacy_restore(); }
		}

		private static function handle_open() {
			global $wpdb;
			$id = absint( self::query( 'ec_ac_open' ) ); $step = absint( self::query( 'ec_acs' ) );
			if ( $id && self::tables_exist() && self::check_token( $id, 'open', self::query( 'ec_act' ) ) && self::settings()['track_opens'] && ! $wpdb->get_var( $wpdb->prepare( "SELECT event_id FROM ec_abandoned_cart_event WHERE abandoned_cart_id = %d AND type = 'opened' AND step = %d", $id, $step ) ) ) { self::event( $id, 'opened', $step ); }
			nocache_headers(); if ( ! headers_sent() ) { header( 'Content-Type: image/gif' ); }
			echo base64_decode( 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- fixed 1x1 GIF.
			self::end();
		}

		/**
		 * Unsubscribe endpoint. The signed token in the URL is the authorisation ( the shopper is not logged in, so there is
		 * no nonce ); what protects against link-scanning mail filters is that only a POST changes anything:
		 * - GET ( the link in the email body, and every scanner pre-fetch ) → confirm page, never mutates.
		 * - POST with ec_ac_unsub_confirm=1 ( the "Yes, unsubscribe" button, posted back to the same signed URL ) → unsubscribe.
		 * - POST with body List-Unsubscribe=One-Click ( RFC 8058, sent by Gmail/Yahoo to the List-Unsubscribe header URL,
		 *   which is unsubscribe_url() ) → unsubscribe immediately, no confirm step.
		 * Bad tokens and test links ( cart 0 ) get the same page for any method and never change anything.
		 */
		private static function handle_unsubscribe() {
			$id = absint( self::query( 'ec_ac_unsub' ) );
			nocache_headers();
			if ( ! headers_sent() ) { header( 'X-Robots-Tag: noindex, nofollow' ); header( 'Referrer-Policy: no-referrer' ); } /* the signed URL must not leak to the store link's referrer */
			if ( ! self::check_token( $id, 'unsub', self::query( 'ec_act' ) ) ) {
				self::message_page( __( 'This link has expired', 'wp-easycart' ), __( 'We couldn’t confirm this unsubscribe link. Please use the link in the most recent email, or contact us and we’ll stop the reminders for you.', 'wp-easycart' ), 400 );
			}
			if ( 0 === $id ) { /* test emails carry cart 0 so a merchant's test never unsubscribes a real customer */
				self::message_page( __( 'You’re unsubscribed', 'wp-easycart' ), __( 'This was a test email, so nothing was changed. A real customer clicking this link stops all cart reminders to their address.', 'wp-easycart' ) );
			}
			$post = self::is_post();
			$one_click = $post && 'One-Click' === self::post_field( 'List-Unsubscribe' );
			$confirmed = $post && '1' === self::post_field( 'ec_ac_unsub_confirm' );
			if ( ! $one_click && ! $confirmed ) {
				$row = self::tables_exist() ? self::row( $id ) : null;
				if ( ! $row || self::is_suppressed( $row->email ) ) { /* nothing left to change: say so without mutating */
					self::message_page( __( 'You’re unsubscribed', 'wp-easycart' ), __( 'We won’t send you any more reminders about items left in your cart.', 'wp-easycart' ) );
				}
				$keep_url = self::cart_page_url(); if ( '' === $keep_url ) { $keep_url = home_url( '/' ); }
				$form = '<form method="post" action="' . esc_url( self::unsubscribe_url( $row ) ) . '" style="margin:0">'
					. '<input type="hidden" name="ec_ac_unsub_confirm" value="1" />'
					. '<button type="submit" style="display:inline-block;background:#1d2327;color:#fff;border:0;cursor:pointer;padding:10px 20px;border-radius:6px;font-size:14px;margin:0 6px 10px">' . esc_html__( 'Yes, unsubscribe', 'wp-easycart' ) . '</button>'
					. '<a href="' . esc_url( $keep_url ) . '" rel="noreferrer" style="display:inline-block;padding:10px 14px;font-size:14px;color:#1d2327">' . esc_html__( 'Keep me subscribed', 'wp-easycart' ) . '</a>'
					. '</form>';
				self::message_page(
					/* translators: %s: masked email address, e.g. j***@example.com */
					sprintf( __( 'Stop cart reminder emails to %s?', 'wp-easycart' ), self::mask_email( $row->email ) ),
					__( 'You won’t get any more reminders about items left in your cart. Order confirmations and other emails about your purchases are not affected.', 'wp-easycart' ),
					200,
					$form
				);
			}
			$done = self::unsubscribe( $id, $one_click ? 'one-click' : 'email link' );
			if ( ! $done ) {
				self::message_page( __( 'You’re unsubscribed', 'wp-easycart' ), __( 'We won’t send you any more reminders about items left in your cart.', 'wp-easycart' ) );
			}
			/* translators: %s: masked email address, e.g. j***@example.com */
			self::message_page( __( 'You’re unsubscribed', 'wp-easycart' ), sprintf( __( 'We won’t send any more reminders about items left in a cart to %s. Order confirmations and other emails about your purchases are not affected.', 'wp-easycart' ), self::mask_email( $done['email'] ) ) );
		}

		/**
		 * Stop cart reminders to the address of cart $id: every open, expired or handled cart for that email becomes
		 * "unsubscribed" ( recovered / bought-other carts keep their status for reporting ), and an "unsubscribed" event
		 * makes the suppression durable ( is_suppressed() ) for carts captured later.
		 *
		 * @since 6.0.0
		 * @param int    $id     abandoned_cart_id.
		 * @param string $source Timeline detail ( "email link", "by admin" ).
		 * @return array|false { email, changed ( int rows ), already ( bool ) } or false when the cart does not exist.
		 */
		public static function unsubscribe( $id, $source = '' ) {
			global $wpdb;
			if ( ! self::tables_exist() ) { return false; }
			$row = self::row( $id ); if ( ! $row || '' === (string) $row->email ) { return false; }
			$already = self::is_suppressed( $row->email );
			$changed = (int) $wpdb->query( $wpdb->prepare( "UPDATE ec_abandoned_cart SET status = 'unsubscribed', next_send_at = NULL WHERE email = %s AND status IN ( 'active', 'reminded', 'expired', 'dismissed' )", $row->email ) );
			$wpdb->query( $wpdb->prepare( 'UPDATE ec_abandoned_cart SET next_send_at = NULL WHERE email = %s', $row->email ) );
			if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT event_id FROM ec_abandoned_cart_event WHERE abandoned_cart_id = %d AND type = 'unsubscribed' LIMIT 1", (int) $id ) ) ) { self::event( (int) $id, 'unsubscribed', 0, $source ); }
			if ( ! $already ) { do_action( 'wp_easycart_abandoned_cart_unsubscribed', (int) $id, $row->email ); }
			return array( 'email' => (string) $row->email, 'changed' => $changed, 'already' => $already );
		}

		/** Signed recovery link ( current format ). */
		private static function handle_restore() {
			$id = absint( self::query( 'ec_ac_restore' ) ); $coupon = self::clean_code( self::query( 'ec_coupon' ) );
			if ( $id && self::tables_exist() && self::check_token( $id, 'restore|' . $coupon, self::query( 'ec_act' ) ) ) {
				$row = self::row( $id );
				if ( $row ) {
					$step = absint( self::query( 'ec_acc' ) );
					if ( $step && self::settings()['track_clicks'] ) { self::event( $id, 'clicked', $step ); }
					self::restore_row( $row, $coupon );
				}
			}
			self::redirect_to_cart();
		}

		/**
		 * Old click wrapper ( ?ec_ac={id}&ec_act={click token}&ec_acu={restore url} ). The inner URL was never encoded, so
		 * its email/key/coupon arrived as top-level parameters and the redirect lost them. Resolve by cart id instead.
		 */
		private static function handle_legacy_click() {
			$id = absint( self::query( 'ec_ac' ) );
			if ( ! $id || ! self::tables_exist() || ! self::check_token( $id, 'click', self::query( 'ec_act' ) ) ) { return; }
			$row = self::row( $id );
			if ( $row ) {
				if ( self::settings()['track_clicks'] ) { self::event( $id, 'clicked' ); }
				$coupon = self::clean_code( self::query( 'ec_coupon' ) );
				if ( '' !== $coupon && ! hash_equals( (string) $row->offer_code, $coupon ) ) { $coupon = ''; } /* unsigned in the old format: only the cart's own code */
				self::restore_row( $row, $coupon );
			}
			self::redirect_to_cart();
		}

		/**
		 * Core's signed link ( ?ec_load_tempcart=&ec_load_email=&ec_load_key= ), used by the legacy daily email and by links
		 * copied before 6.0.0. Core restores it on 'wp' when the session still exists; handled here first so a coupon is only
		 * pinned after the key checks out, and so a session that has rotated since still finds the customer's live cart.
		 */
		private static function handle_legacy_restore() {
			global $wpdb;
			if ( ! function_exists( 'wpeasycart_session' ) || ! method_exists( wpeasycart_session(), 'get_abandoned_cart_key' ) ) { return; }
			$sid = preg_replace( '/[^A-Z]/', '', strtoupper( self::query( 'ec_load_tempcart' ) ) ); $email = sanitize_email( self::query( 'ec_load_email' ) );
			if ( '' === $sid || ! is_email( $email ) || ! hash_equals( wpeasycart_session()->get_abandoned_cart_key( $sid, $email ), self::query( 'ec_load_key' ) ) ) { return; }
			$coupon = self::clean_code( self::query( 'ec_coupon' ) );
			if ( self::live_session( $sid, $email ) ) { self::restore_session( $sid, $coupon ); self::redirect_to_cart(); }
			if ( ! self::tables_exist() ) { return; }
			$live = self::live_session_for_email( $email );
			if ( '' !== $live ) { self::restore_session( $live, $coupon ); self::redirect_to_cart(); }
			/* nothing to restore: let the cart page load normally */
		}

		/** Restore the snapshot's session, or — if it is gone — the customer's newest live cart. */
		public static function restore_row( $row, $coupon = '' ) {
			if ( in_array( $row->status, array( 'recovered', 'converted_other' ), true ) ) { return ''; }
			$sid = self::live_session( (string) $row->session_id ) ? (string) $row->session_id : self::live_session_for_email( (string) $row->email );
			return '' !== $sid ? self::restore_session( $sid, $coupon ) : '';
		}
		private static function live_session( $sid, $email = '' ) {
			global $wpdb; if ( '' === $sid ) { return false; }
			if ( '' !== $email ) { return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT t.session_id FROM ec_tempcart t INNER JOIN ec_tempcart_data d ON d.session_id = t.session_id WHERE t.session_id = %s AND d.email = %s LIMIT 1', $sid, $email ) ); }
			return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT session_id FROM ec_tempcart WHERE session_id = %s LIMIT 1', $sid ) );
		}
		private static function live_session_for_email( $email ) {
			global $wpdb; if ( '' === $email ) { return ''; }
			return (string) $wpdb->get_var( $wpdb->prepare( "SELECT a.session_id FROM ec_abandoned_cart a INNER JOIN ec_tempcart t ON t.session_id = a.session_id WHERE a.email = %s AND a.status NOT IN ( 'recovered', 'converted_other' ) ORDER BY a.last_activity DESC LIMIT 1", $email ) );
		}
		/** Adopt $sid in this browser, rotate it ( the link may have been forwarded ), pin the coupon. Returns the new id. */
		private static function restore_session( $sid, $coupon = '' ) {
			if ( ! function_exists( 'wpeasycart_session' ) ) { return ''; }
			$session = wpeasycart_session();
			$session->handle_session( $sid );
			$new = method_exists( $session, 'rotate_session_id' ) ? $session->rotate_session_id() : $sid;
			$new = $new ? (string) $new : $sid;
			self::on_session_rotated( $sid, $new ); /* no-op when the wpeasycart_session_rotated hook already moved it */
			if ( '' !== $coupon ) { self::apply_code_to_session( $new, $coupon ); }
			do_action( 'wp_easycart_abandoned_cart_restored', $new, $sid, $coupon );
			return $new;
		}
		private static function redirect_to_cart() {
			$to = self::cart_page_url(); if ( '' === $to ) { $to = home_url( '/' ); }
			nocache_headers(); wp_safe_redirect( $to ); self::end();
		}
		private static function mask_email( $email ) {
			$p = explode( '@', (string) $email ); if ( 2 !== count( $p ) ) { return ''; }
			return substr( $p[0], 0, 1 ) . str_repeat( '*', max( 3, min( 8, strlen( $p[0] ) - 1 ) ) ) . '@' . $p[1];
		}
		private static function is_post() {
			return isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) );
		}
		/** Read a POST value on the unsubscribe endpoint ( authorised by the signed URL token, see handle_unsubscribe() ). */
		private static function post_field( $key ) {
			return isset( $_POST[ $key ] ) && is_scalar( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- logged-out shopper; the HMAC token in the URL ( check_token ) authorises the request.
		}
		/**
		 * Shopper-facing page ( theme-independent; wp_die prints a noindex robots tag, callers send nocache headers ).
		 *
		 * @param string      $actions_html Pre-escaped markup replacing the default "Continue to store" button ( e.g. the confirm form ).
		 */
		private static function message_page( $title, $text, $status = 200, $actions_html = null ) {
			$shop = (int) get_option( 'ec_option_storepage' ); $shop_url = $shop ? get_permalink( $shop ) : ''; if ( ! $shop_url ) { $shop_url = home_url( '/' ); }
			if ( null === $actions_html ) { $actions_html = '<p style="margin:0"><a href="' . esc_url( $shop_url ) . '" rel="noreferrer" style="display:inline-block;background:#1d2327;color:#fff;text-decoration:none;padding:10px 20px;border-radius:6px;font-size:14px">' . esc_html( sprintf( /* translators: %s: store name */ __( 'Continue to %s', 'wp-easycart' ), get_bloginfo( 'name' ) ) ) . '</a></p>'; }
			$html = '<div style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Helvetica,Arial,sans-serif;text-align:center;padding:12px 4px">'
				. '<h1 style="font-size:24px;margin:0 0 12px;border:0;padding:0;color:#1d2327">' . esc_html( $title ) . '</h1>'
				. '<p style="font-size:15px;line-height:1.6;color:#3c434a;margin:0 0 22px">' . esc_html( $text ) . '</p>'
				. $actions_html . '</div>';
			if ( defined( 'EC_ABANDONED_NO_EXIT' ) ) { throw new RuntimeException( 'page:' . (int) $status . ':' . $title . ':' . $text . ':' . $actions_html ); } // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- test-only hook, never rendered.
			wp_die( $html, esc_html( $title ), array( 'response' => (int) $status, 'back_link' => false ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts above.
		}

		/**
		 * Pin a discount code on a session the way the checkout would. With Offers v2 active, applied codes live in
		 * ec_tempcart_offer ( keyed by session, like the cart rows ); legacy coupons live on ec_tempcart_data.coupon_code.
		 * Both are written so whichever engine evaluates the cart finds it.
		 */
		public static function apply_code_to_session( $sid, $code ) {
			global $wpdb;
			$wpdb->update( 'ec_tempcart_data', array( 'coupon_code' => $code ), array( 'session_id' => $sid ) );
			if ( isset( $GLOBALS['ec_cart_data']->cart_data ) && is_object( $GLOBALS['ec_cart_data']->cart_data ) && $GLOBALS['ec_cart_data']->cart_data->session_id === $sid ) { $GLOBALS['ec_cart_data']->cart_data->coupon_code = $code; }
			if ( function_exists( 'wp_easycart_offers_active' ) && wp_easycart_offers_active() && $wpdb->get_var( "SHOW TABLES LIKE 'ec_tempcart_offer'" ) ) {
				$oc = $wpdb->get_row( $wpdb->prepare( 'SELECT offer_code_id, offer_id, is_active, times_redeemed, max_redemptions FROM ec_offer_code WHERE code = %s', $code ) );
				if ( $oc && (int) $oc->is_active && ( ! (int) $oc->max_redemptions || (int) $oc->times_redeemed < (int) $oc->max_redemptions ) && ! $wpdb->get_var( $wpdb->prepare( 'SELECT tempcart_offer_id FROM ec_tempcart_offer WHERE session_id = %s AND code = %s', $sid, $code ) ) ) {
					$wpdb->insert( 'ec_tempcart_offer', array( 'session_id' => $sid, 'offer_id' => (int) $oc->offer_id, 'offer_code_id' => (int) $oc->offer_code_id, 'code' => $code, 'applied_date' => current_time( 'mysql' ) ) );
					if ( class_exists( 'ec_offer_integration' ) && method_exists( 'ec_offer_integration', 'reset' ) ) { ec_offer_integration::reset(); }
				}
			}
			do_action( 'wp_easycart_abandoned_cart_coupon_applied', $sid, $code );
		}
		/** exit() that tests can intercept ( define EC_ABANDONED_NO_EXIT ). */
		private static function end() { if ( defined( 'EC_ABANDONED_NO_EXIT' ) ) { throw new RuntimeException( 'exit' ); } exit; }

		/** Has this address unsubscribed from cart reminders ( on any cart, ever )? */
		public static function is_suppressed( $email ) {
			global $wpdb; $email = (string) $email; if ( '' === $email ) { return false; }
			$hit = (bool) $wpdb->get_var( $wpdb->prepare( "SELECT abandoned_cart_id FROM ec_abandoned_cart WHERE email = %s AND status = 'unsubscribed' LIMIT 1", $email ) );
			if ( ! $hit ) { $hit = (bool) $wpdb->get_var( $wpdb->prepare( "SELECT e.event_id FROM ec_abandoned_cart_event e INNER JOIN ec_abandoned_cart a ON a.abandoned_cart_id = e.abandoned_cart_id WHERE a.email = %s AND e.type = 'unsubscribed' LIMIT 1", $email ) ); }
			return (bool) apply_filters( 'wp_easycart_abandoned_cart_is_suppressed', $hit, $email );
		}

		/* ------------------------------------------------------------------ */
		/* Early email capture ( storefront )                                  */
		/* ------------------------------------------------------------------ */

		/** The storefront session: wpeasycart_session reads the signed ec_cart_id cookie into $GLOBALS['ec_cart_id'] ( 'not-set' when none ). */
		public static function current_session_id() {
			$sid = isset( $GLOBALS['ec_cart_id'] ) && 'not-set' !== $GLOBALS['ec_cart_id'] ? (string) $GLOBALS['ec_cart_id'] : '';
			if ( '' === $sid && isset( $GLOBALS['ec_cart_data']->cart_data->session_id ) ) { $sid = (string) $GLOBALS['ec_cart_data']->cart_data->session_id; }
			return (string) apply_filters( 'wp_easycart_abandoned_current_session', $sid );
		}
		public static function enqueue_capture() {
			if ( ! self::settings()['capture_email'] || ! self::tables_exist() ) { return; }
			$cart = (int) get_option( 'ec_option_cartpage' ); if ( ! $cart || ! is_page( $cart ) ) { return; }
			wp_enqueue_script( 'ec_abandoned_capture', plugins_url( 'design/theme/base-responsive-v3/ec_abandoned_capture.js', EC_PLUGIN_DIRECTORY . '/wpeasycart.php' ), array( 'jquery' ), EC_CURRENT_VERSION, true );
			wp_localize_script( 'ec_abandoned_capture', 'ec_abandoned_capture', array( 'ajax' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'ec_abandoned_capture' ), 'selectors' => apply_filters( 'wp_easycart_abandoned_email_selectors', '#ec_contact_email, #ec_cart_login_email' ) ) ); /* checkout contact email + the returning-customer login email ( ec_checkout_details.php ) */
		}
		public static function ajax_capture_email() {
			check_ajax_referer( 'ec_abandoned_capture', 'nonce' ); global $wpdb;
			/* adopt the cookie session if there is one; never create a session just to store an email */
			if ( function_exists( 'wpeasycart_session' ) ) { wpeasycart_session()->handle_session( false, false ); }
			$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : ''; $sid = self::current_session_id();
			if ( ! is_email( $email ) || '' === $sid ) { wp_send_json_error(); }
			if ( $wpdb->get_var( $wpdb->prepare( 'SELECT tempcart_data_id FROM ec_tempcart_data WHERE session_id = %s', $sid ) ) ) { $wpdb->query( $wpdb->prepare( "UPDATE ec_tempcart_data SET email = %s WHERE session_id = %s AND ( email = '' OR email IS NULL )", $email, $sid ) ); }
			else { $wpdb->insert( 'ec_tempcart_data', array( 'session_id' => $sid, 'email' => $email, 'tempcart_time' => current_time( 'mysql' ) ) ); }
			self::capture( $sid );
			wp_send_json_success();
		}

		/* ------------------------------------------------------------------ */
		/* Helpers                                                             */
		/* ------------------------------------------------------------------ */

		public static function event( $id, $type, $step = 0, $detail = '' ) {
			global $wpdb; if ( ! $id ) { return; }
			$wpdb->insert( 'ec_abandoned_cart_event', array( 'abandoned_cart_id' => (int) $id, 'type' => $type, 'step' => (int) $step, 'detail' => (string) $detail, 'created_at' => current_time( 'mysql' ) ) );
		}
		public static function row( $id ) { global $wpdb; return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_abandoned_cart WHERE abandoned_cart_id = %d', (int) $id ) ); }
		public static function items( $cart ) { $i = json_decode( (string) $cart->items_json, true ); return is_array( $i ) ? $i : array(); }
		/** Read-only number for Store Status ( free ) and the PRO health cards. */
		public static function count_recent( $days = 30 ) { global $wpdb; if ( ! self::tables_exist() ) { return 0; } return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_abandoned_cart WHERE first_seen >= DATE_SUB( NOW(), INTERVAL %d DAY )', (int) $days ) ); }
		public static function stats( $days = 30 ) {
			global $wpdb; if ( ! self::tables_exist() ) { return array(); }
			$w = $wpdb->prepare( 'first_seen >= DATE_SUB( NOW(), INTERVAL %d DAY )', (int) $days );
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $w is prepared above.
			$r = $wpdb->get_row( "SELECT COUNT(*) total, SUM( status = 'recovered' ) recovered, COALESCE( SUM( CASE WHEN status = 'recovered' THEN recovered_total ELSE 0 END ), 0 ) revenue, SUM( stage = 'payment_failed' AND status IN ( 'active', 'reminded' ) ) failed, SUM( status = 'unsubscribed' ) unsub, SUM( sequence_step > 0 ) reminded, COALESCE( SUM( CASE WHEN status IN ( 'active', 'reminded' ) THEN subtotal ELSE 0 END ), 0 ) open_value FROM ec_abandoned_cart WHERE $w", ARRAY_A );
			$r['sent'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM ec_abandoned_cart_event e INNER JOIN ec_abandoned_cart a ON a.abandoned_cart_id = e.abandoned_cart_id WHERE e.type = 'step_sent' AND a.$w" );
			$r['opened'] = (int) $wpdb->get_var( "SELECT COUNT( DISTINCT e.abandoned_cart_id ) FROM ec_abandoned_cart_event e INNER JOIN ec_abandoned_cart a ON a.abandoned_cart_id = e.abandoned_cart_id WHERE e.type = 'opened' AND a.$w" );
			$r['clicked'] = (int) $wpdb->get_var( "SELECT COUNT( DISTINCT e.abandoned_cart_id ) FROM ec_abandoned_cart_event e INNER JOIN ec_abandoned_cart a ON a.abandoned_cart_id = e.abandoned_cart_id WHERE e.type = 'clicked' AND a.$w" );
			$r['by_step'] = $wpdb->get_results( "SELECT sequence_step AS step, COUNT(*) n, COALESCE( SUM( recovered_total ), 0 ) revenue FROM ec_abandoned_cart WHERE status = 'recovered' AND $w GROUP BY sequence_step ORDER BY sequence_step", ARRAY_A );
			$r['rate'] = $r['total'] ? round( 100 * (int) $r['recovered'] / (int) $r['total'], 1 ) : 0;
			$r['declines'] = $wpdb->get_results( "SELECT card_error AS reason, COUNT(*) n FROM ec_abandoned_cart WHERE stage = 'payment_failed' AND card_error != '' AND $w GROUP BY card_error ORDER BY n DESC LIMIT 6", ARRAY_A );
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return $r;
		}
		/** Top abandoned products from the snapshots — one query over items_json is not possible in SQL, so aggregate in PHP over the window. */
		public static function top_products( $days = 30, $limit = 8 ) {
			global $wpdb; if ( ! self::tables_exist() ) { return array(); }
			$agg = array();
			foreach ( $wpdb->get_col( $wpdb->prepare( 'SELECT items_json FROM ec_abandoned_cart WHERE first_seen >= DATE_SUB( NOW(), INTERVAL %d DAY )', (int) $days ) ) as $j ) { foreach ( (array) json_decode( $j, true ) as $it ) { $k = (int) $it['product_id']; if ( ! isset( $agg[ $k ] ) ) { $agg[ $k ] = array( 'product_id' => $k, 'title' => $it['title'], 'carts' => 0, 'qty' => 0, 'value' => 0 ); } $agg[ $k ]['carts']++; $agg[ $k ]['qty'] += (int) $it['qty']; $agg[ $k ]['value'] += (float) $it['price'] * (int) $it['qty']; } }
			usort( $agg, function( $a, $b ) { return $b['carts'] - $a['carts']; } );
			return array_slice( $agg, 0, $limit );
		}
	}

	ec_abandoned_carts::bootstrap();

endif;
