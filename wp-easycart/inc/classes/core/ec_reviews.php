<?php
/**
 * WP EasyCart — Native reviews engine.
 *
 * Free:  verified-buyer flag, rating summary/distribution, one post-shipping review
 *        request email per order with clickable stars, signed one-time links for guests,
 *        duplicate protection.
 * PRO:   public merchant replies ( optionally emailed to the reviewer ), moderation rules,
 *        reminder email, request funnel stats.
 *
 * Integration points
 *   wpeasycart_order_status_update( $order_id, $status_id )   ← queues requests ( statuses configurable, default Shipped )
 *   wpeasycart_review_submitted( $review_id )                  ← the review-submit AJAX handler must fire this once
 *                                                                after the INSERT; the engine then claims the request
 *                                                                token ( cookie ), sets verified, applies rules.
 *   wp_easycart_review_requests_send ( hourly cron )           ← sends due requests / reminders
 *   ?ec_review_token=…&ec_review_rating=N on a product page    ← opens the form pre-filled; token kept in a cookie
 *   ?ec_review_unsubscribe=…                                    ← one-click opt-out
 *
 * Registered from inc/ec_config.php; bootstraps itself.
 *
 * @since 5.x.x
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'ec_reviews' ) ) :

	final class ec_reviews {

		const CRON_HOOK   = 'wp_easycart_review_requests_send';
		const COOKIE      = 'ec_review_req';
		const TOKEN_DAYS  = 60;

		/* ------------------------------------------------------------------ */
		/* Bootstrap                                                           */
		/* ------------------------------------------------------------------ */

		public static function init() {
			add_action( 'init', array( __CLASS__, 'maybe_schedule' ), 20 );
			add_action( 'template_redirect', array( __CLASS__, 'handle_landing' ), 5 );
			add_action( self::CRON_HOOK, array( __CLASS__, 'send_due' ) );
			add_action( 'wpeasycart_order_status_update', array( __CLASS__, 'on_order_status' ), 10, 2 );
			add_action( 'wpeasycart_review_submitted', array( __CLASS__, 'on_review_submitted' ), 10, 1 );
			add_action( 'wpeasycart_review_approved', array( __CLASS__, 'flush' ) );
			add_action( 'wpeasycart_review_unapproved', array( __CLASS__, 'flush' ) );
			add_action( 'wpeasycart_review_updated', array( __CLASS__, 'flush' ) );
			add_action( 'wpeasycart_review_deleting', array( __CLASS__, 'flush' ) );
		}

		/** PRO gate — same detection as smart categories, overridable per feature. */
		public static function is_pro( $recheck = false ) {
			static $pro = null;
			if ( null === $pro || $recheck ) {
				$pro = ( '' === apply_filters( 'wp_easycart_admin_lock_icon', 'locked' ) );
				if ( ! $pro && function_exists( 'wp_easycart_admin_license' ) ) {
					$license = wp_easycart_admin_license();
					if ( is_object( $license ) && method_exists( $license, 'is_licensed' ) ) { $pro = (bool) $license->is_licensed(); }
					else if ( is_object( $license ) && isset( $license->active_license ) ) { $pro = ! empty( $license->active_license ); }
				}
				$pro = (bool) apply_filters( 'wp_easycart_reviews_pro_enabled', $pro );
			}
			return $pro;
		}

		public static function columns_exist( $recheck = false ) {
			static $ok = null;
			if ( null === $ok || $recheck ) {
				global $wpdb;
				$ok = (bool) $wpdb->get_var( "SHOW COLUMNS FROM ec_review LIKE 'reply_text'" ) && (bool) $wpdb->get_var( "SHOW TABLES LIKE 'ec_review_request'" );
			}
			return $ok;
		}

		/* ------------------------------------------------------------------ */
		/* Settings                                                            */
		/* ------------------------------------------------------------------ */

		public static function defaults() {
			return array(
				'request_enabled'   => 0,          /* off until the merchant turns it on — nobody's customers get surprise email */
				'request_delay'     => 7,          /* days after the trigger status */
				'request_statuses'  => array( 2 ), /* ec_orderstatus ids; 2 = Shipped */
				'request_subject'   => '',         /* blank = default text */
				'request_intro'     => '',
				'reminder_days'     => 0,          /* PRO; 0 = off */
				'reply_signature'   => '',         /* PRO; blank = site name */
				'reply_email'       => 1,          /* PRO; email the reviewer when a reply is posted */
				'rules'             => array(      /* PRO */
					'auto_approve_verified_min' => 0,  /* 0 = off, else min stars */
					'hold_links'                => 1,
					'hold_unverified'           => 0,
					'blocked_words'             => '',
					'notify_low'                => 0,  /* 0 = off, else max stars that trigger an admin email */
				),
			);
		}

		public static function settings() {
			$saved = get_option( 'ec_option_native_reviews', array() );
			$s = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
			$s['rules'] = wp_parse_args( isset( $saved['rules'] ) && is_array( $saved['rules'] ) ? $saved['rules'] : array(), self::defaults()['rules'] );
			return $s;
		}

		public static function save_settings( $in ) {
			$d = self::defaults(); $s = self::settings(); $pro = self::is_pro();
			$s['request_enabled']  = ! empty( $in['request_enabled'] ) ? 1 : 0;
			$s['request_delay']    = isset( $in['request_delay'] ) ? max( 0, min( 90, (int) $in['request_delay'] ) ) : $d['request_delay'];
			$s['request_statuses'] = isset( $in['request_statuses'] ) && is_array( $in['request_statuses'] ) ? array_values( array_filter( array_map( 'intval', $in['request_statuses'] ) ) ) : $d['request_statuses'];
			if ( empty( $s['request_statuses'] ) ) { $s['request_statuses'] = $d['request_statuses']; }
			$s['request_subject']  = isset( $in['request_subject'] ) ? sanitize_text_field( $in['request_subject'] ) : '';
			$s['request_intro']    = isset( $in['request_intro'] ) ? sanitize_textarea_field( $in['request_intro'] ) : '';
			if ( $pro ) {
				$s['reminder_days']   = isset( $in['reminder_days'] ) ? max( 0, min( 60, (int) $in['reminder_days'] ) ) : 0;
				$s['reply_signature'] = isset( $in['reply_signature'] ) ? sanitize_text_field( $in['reply_signature'] ) : '';
				$s['reply_email']     = ! empty( $in['reply_email'] ) ? 1 : 0;
				$r = isset( $in['rules'] ) && is_array( $in['rules'] ) ? $in['rules'] : array();
				$s['rules'] = array(
					'auto_approve_verified_min' => isset( $r['auto_approve_verified_min'] ) ? max( 0, min( 5, (int) $r['auto_approve_verified_min'] ) ) : 0,
					'hold_links'                => ! empty( $r['hold_links'] ) ? 1 : 0,
					'hold_unverified'           => ! empty( $r['hold_unverified'] ) ? 1 : 0,
					'blocked_words'             => isset( $r['blocked_words'] ) ? sanitize_textarea_field( $r['blocked_words'] ) : '',
					'notify_low'                => isset( $r['notify_low'] ) ? max( 0, min( 5, (int) $r['notify_low'] ) ) : 0,
				);
			}
			update_option( 'ec_option_native_reviews', $s, false );
			self::maybe_schedule();
			return $s;
		}

		/* ------------------------------------------------------------------ */
		/* Verified buyer                                                      */
		/* ------------------------------------------------------------------ */

		/** Did this person ( by user id or email ) buy this product on an approved order? */
		public static function is_verified_buyer( $product_id, $user_id = 0, $email = '' ) {
			global $wpdb;
			$product_id = (int) $product_id; $user_id = (int) $user_id; $email = trim( (string) $email );
			if ( ! $product_id || ( ! $user_id && '' === $email ) ) { return false; }
			$who = array(); $args = array( $product_id );
			if ( $user_id ) { $who[] = 'o.user_id = %d'; $args[] = $user_id; }
			if ( '' !== $email ) { $who[] = 'o.user_email = %s'; $args[] = $email; }
			$sql = 'SELECT o.order_id FROM ec_order o JOIN ec_orderdetail d ON d.order_id = o.order_id LEFT JOIN ec_orderstatus s ON s.status_id = o.orderstatus_id WHERE d.product_id = %d AND ( ' . implode( ' OR ', $who ) . ' ) AND ( s.is_approved = 1 OR s.status_id IS NULL ) LIMIT 1';
			return (bool) $wpdb->get_var( $wpdb->prepare( $sql, $args ) );
		}

		/** Recompute the flag for every review that has a user or email ( admin tool / after upgrade ). */
		public static function recompute_verified() {
			global $wpdb;
			if ( ! self::columns_exist() ) { return 0; }
			$rows = $wpdb->get_results( "SELECT review_id, product_id, user_id, reviewer_email FROM ec_review WHERE user_id > 0 OR reviewer_email != ''" );
			$n = 0;
			foreach ( $rows as $r ) {
				$v = self::is_verified_buyer( $r->product_id, $r->user_id, $r->reviewer_email ) ? 1 : 0;
				$wpdb->update( 'ec_review', array( 'verified' => $v ), array( 'review_id' => (int) $r->review_id ) );
				$n += $v;
			}
			self::flush();
			return $n;
		}

		/* ------------------------------------------------------------------ */
		/* Summary                                                             */
		/* ------------------------------------------------------------------ */

		/** { avg, count, dist: [1..5 => n] } over approved reviews. */
		public static function summary( $product_id ) {
			global $wpdb;
			$key = 'ec-review-summary-' . (int) $product_id;
			$s = wp_cache_get( $key, 'wpeasycart-reviews' );
			if ( false === $s ) {
				$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT rating, COUNT(*) AS n FROM ec_review WHERE product_id = %d AND approved = 1 GROUP BY rating', (int) $product_id ) );
				$dist = array( 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0 ); $count = 0; $sum = 0;
				foreach ( $rows as $r ) { $st = max( 1, min( 5, (int) $r->rating ) ); $dist[ $st ] += (int) $r->n; $count += (int) $r->n; $sum += $st * (int) $r->n; }
				$s = array( 'avg' => $count ? round( $sum / $count, 1 ) : 0, 'count' => $count, 'dist' => $dist );
				wp_cache_set( $key, $s, 'wpeasycart-reviews' );
			}
			return $s;
		}

		public static function flush( $review_id = 0 ) {
			self::$extras_map = null;
			wp_cache_delete( 'wpeasycart-reviews' ); /* the storefront's all-reviews cache in ec_db */
			if ( $review_id ) {
				global $wpdb;
				$pid = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT product_id FROM ec_review WHERE review_id = %d', (int) $review_id ) );
				if ( $pid ) { wp_cache_delete( 'ec-review-summary-' . $pid, 'wpeasycart-reviews' ); }
			}
		}

		/** Storefront helper: reply / verified / date for a set of review ids in one query ( guarded for pre-upgrade DBs ). */
		public static function extras_for( $review_ids ) {
			global $wpdb;
			$ids = array_values( array_filter( array_map( 'intval', (array) $review_ids ) ) );
			if ( empty( $ids ) || ! self::columns_exist() ) { return array(); }
			$out = array();
			foreach ( $wpdb->get_results( 'SELECT review_id, verified, reply_text, reply_date FROM ec_review WHERE review_id IN ( ' . implode( ',', $ids ) . ' )' ) as $r ) {
				$out[ (int) $r->review_id ] = array( 'verified' => (bool) $r->verified, 'reply' => (string) $r->reply_text, 'reply_date' => $r->reply_date );
			}
			return $out;
		}

		/** Per-review extras with one query for the whole approved set ( the storefront loads all reviews anyway ). */
		private static $extras_map = null;
		public static function extras( $review_id ) {
			if ( null === self::$extras_map ) {
				global $wpdb;
				self::$extras_map = array();
				if ( self::columns_exist() ) {
					foreach ( $wpdb->get_results( 'SELECT review_id, verified, reply_text, reply_date FROM ec_review WHERE approved = 1 AND ( verified = 1 OR ( reply_text IS NOT NULL AND reply_text != "" ) )' ) as $r ) {
						self::$extras_map[ (int) $r->review_id ] = array( 'verified' => (bool) $r->verified, 'reply' => (string) $r->reply_text, 'reply_date' => $r->reply_date );
					}
				}
			}
			$id = (int) $review_id;
			return isset( self::$extras_map[ $id ] ) ? self::$extras_map[ $id ] : array( 'verified' => false, 'reply' => '', 'reply_date' => null );
		}

		public static function reply_signature() {
			$s = self::settings();
			return '' !== trim( $s['reply_signature'] ) ? $s['reply_signature'] : get_bloginfo( 'name' );
		}

		/* ------------------------------------------------------------------ */
		/* Replies ( PRO )                                                     */
		/* ------------------------------------------------------------------ */

		public static function set_reply( $review_id, $text, $user_id = 0, $email_reviewer = null ) {
			global $wpdb;
			if ( ! self::is_pro() ) { return new WP_Error( 'pro', ( class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::requires_text( __( 'Replying to reviews', 'wp-easycart' ), 'pro' ) : __( 'Replying to reviews is included with Pro and Premium licenses.', 'wp-easycart' ) ) ); }
			if ( ! self::columns_exist() ) { return new WP_Error( 'db', __( 'Database update required.', 'wp-easycart' ) ); }
			$review = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_review WHERE review_id = %d', (int) $review_id ) );
			if ( ! $review ) { return new WP_Error( 'not_found', __( 'Review not found.', 'wp-easycart' ) ); }
			$text = trim( wp_kses( (string) $text, array( 'br' => array(), 'a' => array( 'href' => array() ), 'b' => array(), 'strong' => array(), 'i' => array(), 'em' => array() ) ) );
			$data = '' === $text ? array( 'reply_text' => '', 'reply_date' => null, 'reply_user_id' => 0 ) : array( 'reply_text' => $text, 'reply_date' => current_time( 'mysql' ), 'reply_user_id' => (int) $user_id );
			$wpdb->update( 'ec_review', $data, array( 'review_id' => (int) $review_id ) );
			self::flush( $review_id );
			do_action( 'wpeasycart_review_replied', (int) $review_id, $text );
			$emailed = false;
			if ( '' !== $text ) {
				$s = self::settings();
				if ( null === $email_reviewer ) { $email_reviewer = (bool) $s['reply_email']; }
				if ( $email_reviewer ) { $emailed = self::send_reply_email( $review, $text ); }
			}
			return array( 'reply_date' => $data['reply_date'], 'emailed' => $emailed );
		}

		/* ------------------------------------------------------------------ */
		/* Submission: claim token, verified, rules                            */
		/* ------------------------------------------------------------------ */

		/**
		 * Fired by the review-submit handler after INSERT. Everything here is best-effort and
		 * silent — a failure must never break the customer's submission.
		 */
		public static function on_review_submitted( $review_id ) {
			global $wpdb;
			$review_id = (int) $review_id;
			if ( ! $review_id || ! self::columns_exist() ) { return; }
			$review = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_review WHERE review_id = %d', $review_id ) );
			if ( ! $review ) { return; }
			$update = array();

			/* 1. Claim a request token ( cookie set by handle_landing ) */
			$req = self::request_from_cookie( (int) $review->product_id );
			if ( $req && ! $req->review_id ) {
				$wpdb->update( 'ec_review_request', array( 'reviewed_at' => current_time( 'mysql' ), 'review_id' => $review_id ), array( 'request_id' => (int) $req->request_id ) );
				$update['request_id'] = (int) $req->request_id;
				if ( '' === (string) $review->reviewer_email && $req->email ) { $update['reviewer_email'] = $req->email; $review->reviewer_email = $req->email; }
				if ( ! (int) $review->user_id && (int) $req->user_id ) { $update['user_id'] = (int) $req->user_id; $review->user_id = (int) $req->user_id; }
				self::clear_cookie( (int) $review->product_id );
			}
			/* Logged-in customer: remember their email so guests-turned-customers still match later */
			if ( '' === (string) $review->reviewer_email && (int) $review->user_id ) {
				$u = get_userdata( (int) $review->user_id );
				if ( $u && ! empty( $u->user_email ) ) { $update['reviewer_email'] = $u->user_email; $review->reviewer_email = $u->user_email; }
			}

			/* 2. Verified buyer */
			$verified = $req ? true : self::is_verified_buyer( (int) $review->product_id, (int) $review->user_id, (string) $review->reviewer_email );
			$update['verified'] = $verified ? 1 : 0;

			/* 3. Duplicate: same person already reviewed this product → hold with a reason */
			$dupe = false;
			if ( (int) $review->user_id || '' !== (string) $review->reviewer_email ) {
				$dupe = (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT review_id FROM ec_review WHERE product_id = %d AND review_id != %d AND ( ( %d > 0 AND user_id = %d ) OR ( %s != "" AND reviewer_email = %s ) ) LIMIT 1', (int) $review->product_id, $review_id, (int) $review->user_id, (int) $review->user_id, (string) $review->reviewer_email, (string) $review->reviewer_email ) );
			}
			if ( $dupe ) { $update['approved'] = 0; $update['held_reason'] = 'duplicate'; }

			/* 4. Moderation rules ( PRO ) */
			if ( ! $dupe && self::is_pro() ) {
				$decision = self::apply_rules( $review, $verified );
				if ( 'approve' === $decision['action'] ) { $update['approved'] = 1; $update['held_reason'] = ''; }
				else if ( 'hold' === $decision['action'] ) { $update['approved'] = 0; $update['held_reason'] = $decision['reason']; }
				if ( ! empty( $decision['notify'] ) ) { self::notify_low_rating( $review ); }
			}

			$wpdb->update( 'ec_review', $update, array( 'review_id' => $review_id ) );
			self::flush( $review_id );
			do_action( 'wpeasycart_review_processed', $review_id, $update );
		}

		/** @return array( action => keep|approve|hold, reason, notify ) */
		public static function apply_rules( $review, $verified ) {
			$r = self::settings()['rules'];
			$text = strtolower( (string) $review->title . ' ' . (string) $review->description );
			$out = array( 'action' => 'keep', 'reason' => '', 'notify' => false );
			if ( ! empty( $r['hold_links'] ) && preg_match( '#https?://|www\.|\S+@\S+\.\S+#i', $text ) ) { return array( 'action' => 'hold', 'reason' => 'links', 'notify' => self::is_low( $review, $r ) ); }
			if ( '' !== trim( (string) $r['blocked_words'] ) ) {
				foreach ( preg_split( '/[\s,]+/', strtolower( $r['blocked_words'] ) ) as $w ) {
					$w = trim( $w );
					if ( '' !== $w && false !== strpos( $text, $w ) ) { return array( 'action' => 'hold', 'reason' => 'blocked_word', 'notify' => self::is_low( $review, $r ) ); }
				}
			}
			if ( ! empty( $r['hold_unverified'] ) && ! $verified ) { return array( 'action' => 'hold', 'reason' => 'unverified', 'notify' => self::is_low( $review, $r ) ); }
			if ( (int) $r['auto_approve_verified_min'] > 0 && $verified && (int) $review->rating >= (int) $r['auto_approve_verified_min'] ) { $out['action'] = 'approve'; }
			$out['notify'] = self::is_low( $review, $r );
			return $out;
		}

		private static function is_low( $review, $r ) { return (int) $r['notify_low'] > 0 && (int) $review->rating <= (int) $r['notify_low']; }

		private static function notify_low_rating( $review ) {
			$to = stripslashes( get_option( 'ec_option_bcc_email_addresses' ) );
			if ( ! $to ) { return; }
			$title = (string) get_option( 'blogname' );
			$url = admin_url( 'admin.php?page=wp-easycart-products&subpage=reviews&ec_admin_form_action=edit&review_id=' . (int) $review->review_id );
			$subject = sprintf( __( '[%1$s] %2$d-star review needs your attention', 'wp-easycart' ), $title, (int) $review->rating );
			$body = '<p>' . esc_html( sprintf( __( 'A %d-star review was just submitted.', 'wp-easycart' ), (int) $review->rating ) ) . '</p><p><b>' . esc_html( wp_unslash( $review->title ) ) . '</b><br>' . nl2br( esc_html( wp_unslash( $review->description ) ) ) . '</p><p><a href="' . esc_url( $url ) . '">' . esc_html__( 'Open the review', 'wp-easycart' ) . '</a></p>';
			self::send_mail( $to, $subject, $body, 'order', 'review_alert' );
		}

		/* ------------------------------------------------------------------ */
		/* Requests: queue → send → land → claim                               */
		/* ------------------------------------------------------------------ */

		public static function on_order_status( $order_id, $status_id ) {
			$s = self::settings();
			if ( ! $s['request_enabled'] || ! in_array( (int) $status_id, array_map( 'intval', $s['request_statuses'] ), true ) ) { return; }
			self::queue_order( (int) $order_id );
		}

		/** One request row per reviewable product on the order; never twice for the same email+product. */
		public static function queue_order( $order_id ) {
			global $wpdb;
			if ( ! self::columns_exist() ) { return 0; }
			$o = $wpdb->get_row( $wpdb->prepare( 'SELECT order_id, user_id, user_email, billing_first_name FROM ec_order WHERE order_id = %d', (int) $order_id ) );
			if ( ! $o || ! is_email( $o->user_email ) ) { return 0; }
			if ( $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM ec_review_request WHERE email = %s AND unsubscribed = 1 LIMIT 1', $o->user_email ) ) ) { return 0; }
			$items = $wpdb->get_results( $wpdb->prepare( 'SELECT DISTINCT d.product_id FROM ec_orderdetail d JOIN ec_product p ON p.product_id = d.product_id WHERE d.order_id = %d AND p.use_customer_reviews = 1 AND p.activate_in_store = 1 AND p.is_giftcard = 0', (int) $order_id ) );
			$s = self::settings(); $n = 0;
			$when = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) + (int) $s['request_delay'] * DAY_IN_SECONDS );
			foreach ( $items as $it ) {
				$pid = (int) $it->product_id;
				if ( $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM ec_review_request WHERE email = %s AND product_id = %d LIMIT 1', $o->user_email, $pid ) ) ) { continue; }
				if ( $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM ec_review WHERE product_id = %d AND ( ( %d > 0 AND user_id = %d ) OR reviewer_email = %s ) LIMIT 1', $pid, (int) $o->user_id, (int) $o->user_id, $o->user_email ) ) ) { continue; }
				$wpdb->insert( 'ec_review_request', array(
					'order_id' => (int) $order_id, 'product_id' => $pid, 'user_id' => (int) $o->user_id, 'email' => $o->user_email,
					'first_name' => (string) $o->billing_first_name, 'token' => self::new_token(), 'scheduled_at' => $when, 'created_at' => current_time( 'mysql' ),
				) );
				$n++;
			}
			if ( $n ) { self::maybe_schedule(); }
			return $n;
		}

		private static function new_token() { return substr( str_replace( array( '+', '/', '=' ), '', base64_encode( random_bytes( 36 ) ) ), 0, 48 ); }

		public static function maybe_schedule() {
			if ( ! self::columns_exist() ) { return; }
			global $wpdb;
			$s = self::settings();
			$pending = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_review_request WHERE sent_at IS NULL AND unsubscribed = 0' );
			$reminders = ( self::is_pro() && (int) $s['reminder_days'] > 0 ) ? (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_review_request WHERE sent_at IS NOT NULL AND reminded_at IS NULL AND review_id = 0 AND unsubscribed = 0' ) : 0;
			$want = $s['request_enabled'] && ( $pending || $reminders );
			$next = wp_next_scheduled( self::CRON_HOOK );
			if ( $want && ! $next ) { wp_schedule_event( time() + 60, 'hourly', self::CRON_HOOK ); }
			else if ( ! $want && $next ) { wp_unschedule_event( $next, self::CRON_HOOK ); }
		}

		/** Cron: send every due request grouped per order ( one email per order ), then due reminders ( PRO ). */
		public static function send_due( $limit = 200 ) {
			global $wpdb;
			if ( ! self::columns_exist() ) { return array( 'sent' => 0, 'reminded' => 0 ); }
			$s = self::settings(); $sent = 0; $reminded = 0;
			if ( $s['request_enabled'] ) {
				$now = current_time( 'mysql' );
				$orders = $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT order_id FROM ec_review_request WHERE sent_at IS NULL AND unsubscribed = 0 AND scheduled_at <= %s ORDER BY scheduled_at LIMIT %d', $now, (int) $limit ) );
				foreach ( $orders as $oid ) {
					$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ec_review_request WHERE order_id = %d AND sent_at IS NULL AND unsubscribed = 0', (int) $oid ) );
					if ( $rows && self::send_request_email( $rows, false ) ) {
						$wpdb->query( $wpdb->prepare( 'UPDATE ec_review_request SET sent_at = %s WHERE order_id = %d AND sent_at IS NULL', $now, (int) $oid ) );
						$sent++;
					}
				}
				if ( self::is_pro() && (int) $s['reminder_days'] > 0 ) {
					$cut = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - (int) $s['reminder_days'] * DAY_IN_SECONDS );
					$orders = $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT order_id FROM ec_review_request WHERE sent_at IS NOT NULL AND sent_at <= %s AND reminded_at IS NULL AND review_id = 0 AND unsubscribed = 0 LIMIT %d', $cut, (int) $limit ) );
					foreach ( $orders as $oid ) {
						$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ec_review_request WHERE order_id = %d AND reminded_at IS NULL AND review_id = 0 AND unsubscribed = 0', (int) $oid ) );
						if ( $rows && self::send_request_email( $rows, true ) ) {
							$wpdb->query( $wpdb->prepare( 'UPDATE ec_review_request SET reminded_at = %s WHERE order_id = %d AND reminded_at IS NULL', $now, (int) $oid ) );
							$reminded++;
						}
					}
				}
			}
			self::maybe_schedule();
			return array( 'sent' => $sent, 'reminded' => $reminded );
		}

		/** Per-product links carried by the email. */
		public static function review_link( $request, $rating = 0 ) {
			global $wpdb;
			$post_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT post_id FROM ec_product WHERE product_id = %d', (int) $request->product_id ) );
			$url = $post_id ? get_permalink( $post_id ) : get_permalink( (int) get_option( 'ec_option_storepage' ) );
			$args = array( 'ec_review_token' => $request->token );
			if ( $rating ) { $args['ec_review_rating'] = (int) $rating; }
			return add_query_arg( $args, $url );
		}
		public static function unsubscribe_link( $request ) { return add_query_arg( array( 'ec_review_unsubscribe' => $request->token ), get_permalink( (int) get_option( 'ec_option_storepage' ) ) ); }

		/** Renders design/layout/<layout>/ec_review_request_email.php and sends. */
		public static function send_request_email( $rows, $is_reminder = false, $override_to = '' ) {
			global $wpdb;
			$rows = array_values( (array) $rows );
			if ( empty( $rows ) ) { return false; }
			$s = self::settings();
			$products = array();
			foreach ( $rows as $r ) {
				$p = $wpdb->get_row( $wpdb->prepare( 'SELECT product_id, title, image1, product_images, use_optionitem_images, post_id FROM ec_product WHERE product_id = %d', (int) $r->product_id ) );
				if ( ! $p ) { continue; }
				$image = '';
				if ( function_exists( 'wp_easycart_admin_catalog_v2_product_thumb' ) ) { $image = wp_easycart_admin_catalog_v2_product_thumb( $p ); }
				else if ( $p->image1 ) { $image = ( 0 === strpos( $p->image1, 'http' ) ) ? $p->image1 : plugins_url( '/wp-easycart-data/products/pics1/' . $p->image1, EC_PLUGIN_DATA_DIRECTORY ); }
				$stars = array(); for ( $i = 1; $i <= 5; $i++ ) { $stars[ $i ] = self::review_link( $r, $i ); }
				$products[] = array( 'title' => wp_unslash( $p->title ), 'image' => $image, 'link' => self::review_link( $r ), 'stars' => $stars );
			}
			if ( empty( $products ) ) { return false; }
			$first = $rows[0];
			$vars = array(
				'first_name'      => (string) $first->first_name,
				'order_id'        => (int) $first->order_id,
				'products'        => $products,
				'is_reminder'     => (bool) $is_reminder,
				'intro'           => '' !== trim( $s['request_intro'] ) ? $s['request_intro'] : '',
				'store_name'      => get_bloginfo( 'name' ),
				'store_page'      => get_permalink( (int) get_option( 'ec_option_storepage' ) ),
				'email_logo_url'  => get_option( 'ec_option_email_logo' ),
				'unsubscribe_url' => self::unsubscribe_link( $first ),
				'signature_text'  => get_option( 'ec_option_email_signature_text' ),
				'signature_image' => get_option( 'ec_option_email_signature_image' ),
			);
			$message = self::render_template( 'ec_review_request_email.php', $vars );
			if ( '' === trim( $message ) ) { return false; }
			$subject = '' !== trim( $s['request_subject'] ) ? $s['request_subject'] : ( $is_reminder ? __( 'A quick reminder — how did you like your order?', 'wp-easycart' ) : __( 'How did you like your order?', 'wp-easycart' ) );
			$subject = str_replace( array( '{store}', '{first_name}' ), array( $vars['store_name'], $vars['first_name'] ), $subject );
			return self::send_mail( $override_to ? $override_to : $first->email, apply_filters( 'wp_easycart_review_request_subject', $subject, $vars ), $message, 'order', $is_reminder ? 'review_reminder' : 'review_request', (int) $first->order_id );
		}

		private static function send_reply_email( $review, $reply ) {
			global $wpdb;
			$to = (string) $review->reviewer_email;
			if ( '' === $to && (int) $review->user_id ) { $u = get_userdata( (int) $review->user_id ); $to = $u ? $u->user_email : ''; }
			if ( ! is_email( $to ) ) { return false; }
			$p = $wpdb->get_row( $wpdb->prepare( 'SELECT title, post_id FROM ec_product WHERE product_id = %d', (int) $review->product_id ) );
			$vars = array(
				'reviewer_name' => (string) $review->reviewer_name, 'review_title' => wp_unslash( (string) $review->title ), 'review_text' => wp_unslash( (string) $review->description ), 'rating' => (int) $review->rating,
				'reply' => $reply, 'signature' => self::reply_signature(), 'product_title' => $p ? wp_unslash( $p->title ) : '', 'product_link' => $p && $p->post_id ? get_permalink( (int) $p->post_id ) : '',
				'store_name' => get_bloginfo( 'name' ), 'store_page' => get_permalink( (int) get_option( 'ec_option_storepage' ) ), 'email_logo_url' => get_option( 'ec_option_email_logo' ),
			);
			$message = self::render_template( 'ec_review_reply_email.php', $vars );
			if ( '' === trim( $message ) ) { return false; }
			return self::send_mail( $to, apply_filters( 'wp_easycart_review_reply_subject', sprintf( __( '%s replied to your review', 'wp-easycart' ), $vars['store_name'] ), $vars ), $message, 'order', 'review_reply' );
		}

		/** Same layout-override convention as every other EasyCart template. */
		public static function render_template( $file, $vars ) {
			$paths = array(
				EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/' . $file,
				EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/' . $file,
			);
			foreach ( $paths as $path ) {
				if ( file_exists( $path ) ) { extract( $vars, EXTR_SKIP ); ob_start(); include $path; return ob_get_clean(); }
			}
			return '';
		}

		/** Mirrors ec_order::send_gift_card_email(): wp_mail, the plugin mailer, or a custom hook. */
		public static function send_mail( $to, $subject, $message, $channel = 'order', $type = 'review_request', $order_id = 0 ) {
			if ( class_exists( 'ec_email' ) ) {
				return ec_email::send( $to, $subject, $message, array( 'type' => $type, 'order_id' => (int) $order_id, 'channel' => $channel ) );
			}
			$from = stripslashes( get_option( 'order' === $channel ? 'ec_option_order_from_email' : 'ec_option_password_from_email' ) );
			$method = apply_filters( 'wpeasycart_email_method', get_option( 'ec_option_use_wp_mail' ) );
			if ( '1' == $method ) {
				$headers = array( 'MIME-Version: 1.0', 'Content-Type: text/html; charset=utf-8', 'From: ' . $from, 'Reply-To: ' . $from, 'X-Mailer: PHP/' . phpversion() );
				return (bool) wp_mail( $to, $subject, $message, implode( "\r\n", $headers ) );
			} else if ( '0' == $method ) {
				if ( ! class_exists( 'wpeasycart_mailer' ) ) { return false; }
				$mailer = new wpeasycart_mailer();
				$err = 'order' === $channel ? $mailer->send_order_email( $to, $subject, $message ) : $mailer->send_customer_email( $to, $subject, $message );
				return false === $err;
			}
			do_action( 'wpeasycart_custom_review_email', $from, $to, $subject, $message );
			return true;
		}

		/* ------------------------------------------------------------------ */
		/* Landing on the product page                                          */
		/* ------------------------------------------------------------------ */

		public static function handle_landing() {
			if ( is_admin() || ! self::columns_exist() ) { return; }
			global $wpdb;
			if ( isset( $_GET['ec_review_unsubscribe'] ) ) {
				$tok = preg_replace( '/[^A-Za-z0-9]/', '', (string) $_GET['ec_review_unsubscribe'] );
				$email = $wpdb->get_var( $wpdb->prepare( 'SELECT email FROM ec_review_request WHERE token = %s', $tok ) );
				if ( $email ) { $wpdb->query( $wpdb->prepare( 'UPDATE ec_review_request SET unsubscribed = 1 WHERE email = %s', $email ) ); $GLOBALS['ec_review_unsubscribed'] = true; }
				return;
			}
			if ( ! isset( $_GET['ec_review_token'] ) ) { return; }
			$tok = preg_replace( '/[^A-Za-z0-9]/', '', (string) $_GET['ec_review_token'] );
			$req = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_review_request WHERE token = %s', $tok ) );
			/* 6.0.0: say why the link did nothing instead of loading the page as though it had not been clicked.
			   A sample token ( the Settings test send when the store has no requests yet ) lands here too. */
			if ( ! $req ) { $GLOBALS['ec_review_token_status'] = 'unknown'; return; }
			if ( $req->review_id ) { $GLOBALS['ec_review_token_status'] = 'used'; return; }
			if ( $req->sent_at && strtotime( $req->sent_at ) < time() - self::TOKEN_DAYS * DAY_IN_SECONDS ) { $GLOBALS['ec_review_token_status'] = 'expired'; return; }
			if ( ! $req->opened_at ) { $wpdb->update( 'ec_review_request', array( 'opened_at' => current_time( 'mysql' ) ), array( 'request_id' => (int) $req->request_id ) ); }
			$GLOBALS['ec_review_request'] = $req;
			$GLOBALS['ec_review_prefill_rating'] = isset( $_GET['ec_review_rating'] ) ? max( 0, min( 5, (int) $_GET['ec_review_rating'] ) ) : 0;
			if ( ! headers_sent() ) {
				setcookie( self::COOKIE . '_' . (int) $req->product_id, $tok, time() + DAY_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
			}
		}

		/** The request that brought this shopper to this product page, if any ( template helper ). */
		public static function current_request( $product_id ) {
			if ( isset( $GLOBALS['ec_review_request'] ) && (int) $GLOBALS['ec_review_request']->product_id === (int) $product_id ) { return $GLOBALS['ec_review_request']; }
			return self::request_from_cookie( (int) $product_id );
		}
		public static function prefill_rating() { return isset( $GLOBALS['ec_review_prefill_rating'] ) ? (int) $GLOBALS['ec_review_prefill_rating'] : 0; }

		/**
		 * Message for a review-request link that could not be used, or for a completed unsubscribe.
		 * Empty when the visitor did not arrive from one of those links. @since 6.0.0
		 *
		 * @return string
		 */
		public static function link_notice() {
			if ( ! empty( $GLOBALS['ec_review_unsubscribed'] ) ) {
				return __( 'You will not receive any more review requests from this store.', 'wp-easycart' );
			}
			$status = isset( $GLOBALS['ec_review_token_status'] ) ? $GLOBALS['ec_review_token_status'] : '';
			if ( 'used' === $status ) {
				return __( 'You have already reviewed this product. Thank you!', 'wp-easycart' );
			}
			if ( 'expired' === $status ) {
				return __( 'That review link has expired, but you can still leave a review below.', 'wp-easycart' );
			}
			if ( 'unknown' === $status ) {
				return __( 'That review link is no longer valid, but you can still leave a review below.', 'wp-easycart' );
			}
			return '';
		}

		private static function request_from_cookie( $product_id ) {
			global $wpdb;
			$k = self::COOKIE . '_' . (int) $product_id;
			if ( empty( $_COOKIE[ $k ] ) ) { return null; }
			$tok = preg_replace( '/[^A-Za-z0-9]/', '', (string) $_COOKIE[ $k ] );
			$req = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_review_request WHERE token = %s AND product_id = %d', $tok, (int) $product_id ) );
			return $req ? $req : null;
		}
		private static function clear_cookie( $product_id ) { if ( ! headers_sent() ) { setcookie( self::COOKIE . '_' . (int) $product_id, '', time() - 3600, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN ); } }

		/* ------------------------------------------------------------------ */
		/* Stats ( admin )                                                     */
		/* ------------------------------------------------------------------ */

		public static function funnel( $days = 30 ) {
			global $wpdb;
			if ( ! self::columns_exist() ) { return array( 'queued' => 0, 'sent' => 0, 'opened' => 0, 'reviewed' => 0, 'avg' => 0, 'pending' => 0, 'unsubscribed' => 0 ); }
			$since = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - (int) $days * DAY_IN_SECONDS );
			$r = $wpdb->get_row( $wpdb->prepare( 'SELECT COUNT(*) AS queued, SUM( sent_at IS NOT NULL ) AS sent, SUM( opened_at IS NOT NULL ) AS opened, SUM( review_id > 0 ) AS reviewed, SUM( sent_at IS NULL AND unsubscribed = 0 ) AS pending, SUM( unsubscribed ) AS unsubscribed FROM ec_review_request WHERE created_at >= %s', $since ), ARRAY_A );
			$avg = $wpdb->get_var( $wpdb->prepare( 'SELECT ROUND( AVG( r.rating ), 1 ) FROM ec_review r JOIN ec_review_request q ON q.review_id = r.review_id WHERE q.created_at >= %s', $since ) );
			$r = array_map( 'intval', (array) $r ); $r['avg'] = $avg ? (float) $avg : 0;
			return $r;
		}
	}

	ec_reviews::init();

endif;
