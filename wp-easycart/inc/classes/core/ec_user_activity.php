<?php
/**
 * WP EasyCart — Customer Activity Engine ( FREE ).
 *
 * Records a per-customer activity stream into ec_user_activity. Recording
 * lives in the FREE plugin so history is complete from day one: PRO only
 * *displays* the log, and a store that adds PRO later still has its full
 * history waiting.
 *
 * Design:
 *  - wp_easycart_user_activity()->log( $user_id, $type, $args ) is the one
 *    write path. $args: object_type, object_id, meta ( array, JSON-encoded ),
 *    actor_type ( 'customer' | 'admin' | 'system', auto-detected ),
 *    actor_id ( defaults to current WP user ), activity_date ( defaults now ).
 *  - A global helper wp_easycart_log_user_activity() exists for core call
 *    sites ( Phase: "wire the core later" ) — one line, always safe.
 *  - The engine self-subscribes to hooks that ALREADY fire in core, so
 *    logins, registrations, profile updates, paid orders, refunds,
 *    impersonation, anonymize, and merge are captured with no core edits.
 *    Deeper instrumentation ( status changes, downloads, subscriptions )
 *    is added at the call sites later.
 *  - Type registry ( labels + icons ) is filterable; unknown types still
 *    log and render generically, so extensions can add their own.
 *  - Retention: daily cron purges rows older than
 *    ec_option_activity_retention_days ( default 365, 0 = keep forever ).
 *  - Privacy: anonymize_user() strips IPs + meta; merge_user() moves rows.
 *  - Kill switches: ec_option_user_activity_enabled option and the
 *    wp_easycart_user_activity_should_log filter ( per-event veto ).
 *
 * @since 5.9.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_user_activity' ) ) :

	class wp_easycart_user_activity {

		private static $instance = null;

		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		private function __construct() {
			// Self-wired hooks ( all already fire in core / the v2 admin ).
			add_action( 'wpeasycart_login_success', array( $this, 'on_login' ), 20, 1 );
			add_action( 'wpeasycart_account_added', array( $this, 'on_account_added' ), 20, 2 );
			add_action( 'wpeasycart_account_updated', array( $this, 'on_account_updated' ), 20, 1 );
			add_action( 'wpeasycart_order_paid', array( $this, 'on_order_paid' ), 20, 1 );
			add_action( 'wpeasycart_full_order_refund', array( $this, 'on_order_refund' ), 20, 1 );
			add_action( 'wpeasycart_partial_order_refund', array( $this, 'on_order_refund' ), 20, 1 );
			add_action( 'wpeasycart_admin_login_as_user', array( $this, 'on_admin_login_as' ), 20, 2 );
			add_action( 'wpeasycart_admin_user_anonymized', array( $this, 'on_anonymized' ), 5, 1 );
			add_action( 'wpeasycart_admin_user_merged', array( $this, 'on_merged' ), 5, 2 );

			// Core instrumentation phase: account + subscription lifecycle.
			add_action( 'wpeasycart_logout', array( $this, 'on_logout' ), 20, 1 );
			add_action( 'wpeasycart_password_reset_requested', array( $this, 'on_password_reset_requested' ), 20, 1 );
			add_action( 'wpeasycart_password_reset_complete', array( $this, 'on_password_reset_complete' ), 20, 1 );
			add_action( 'wpeasycart_password_updated', array( $this, 'on_password_updated' ), 20, 1 );
			add_action( 'wpeasycart_subscription_cancelled', array( $this, 'on_subscription_cancelled' ), 20, 2 );
			add_action( 'wpeasycart_subscription_paid', array( $this, 'on_subscription_paid' ), 20, 1 );
			add_action( 'wpeasycart_subscription_first_order_inserted', array( $this, 'on_subscription_started' ), 20, 1 );
			add_action( 'wpeasycart_order_status_update', array( $this, 'on_order_status' ), 20, 2 );
			add_action( 'wpeasycart_tracking_info_update', array( $this, 'on_tracking_update' ), 20, 5 );
			add_action( 'wp_easycart_subscription_ended', array( $this, 'on_subscription_ended' ), 20, 3 );
			add_action( 'wpeasycart_subscriber_added', array( $this, 'on_newsletter_subscribed' ), 20, 1 );
			add_action( 'wpeasycart_insert_subscriber', array( $this, 'on_newsletter_subscribed' ), 20, 1 );
			add_action( 'wpeasycart_subscriber_deleted', array( $this, 'on_newsletter_unsubscribed' ), 20, 1 );

			// Retention cron.
			add_action( 'wp_easycart_user_activity_purge', array( $this, 'run_retention_purge' ) );
			if ( ! wp_next_scheduled( 'wp_easycart_user_activity_purge' ) ) {
				wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'wp_easycart_user_activity_purge' );
			}
		}

		/* ------------------------------------------------------------------ */
		/* Type registry                                                        */
		/* ------------------------------------------------------------------ */

		/**
		 * Known activity types => label + dashicon. Filterable so PRO,
		 * extensions, and later core instrumentation can register their own.
		 * Unknown types still log and display with a generic label/icon.
		 */
		public function get_types() {
			return apply_filters( 'wp_easycart_user_activity_types', array(
				'login'             => array( 'label' => __( 'Signed in', 'wp-easycart' ), 'icon' => 'migrate' ),
				'logout'            => array( 'label' => __( 'Signed out', 'wp-easycart' ), 'icon' => 'exit' ),
				'account_created'   => array( 'label' => __( 'Account created', 'wp-easycart' ), 'icon' => 'admin-users' ),
				'account_updated'   => array( 'label' => __( 'Account updated', 'wp-easycart' ), 'icon' => 'update' ),
				'account_approved'  => array( 'label' => __( 'Account approved', 'wp-easycart' ), 'icon' => 'yes-alt' ),
				'password_reset'    => array( 'label' => __( 'Password reset sent', 'wp-easycart' ), 'icon' => 'lock' ),
				'password_changed'  => array( 'label' => __( 'Password changed', 'wp-easycart' ), 'icon' => 'lock' ),
				'order_paid'        => array( 'label' => __( 'Order paid', 'wp-easycart' ), 'icon' => 'cart' ),
				'order_status'      => array( 'label' => __( 'Order status changed', 'wp-easycart' ), 'icon' => 'randomize' ),
				'tracking_added'    => array( 'label' => __( 'Tracking added', 'wp-easycart' ), 'icon' => 'location-alt' ),
				'order_refunded'    => array( 'label' => __( 'Order refunded', 'wp-easycart' ), 'icon' => 'undo' ),
				'subscription_started'   => array( 'label' => __( 'Subscription started', 'wp-easycart' ), 'icon' => 'update' ),
				'subscription_paid'      => array( 'label' => __( 'Subscription payment', 'wp-easycart' ), 'icon' => 'money-alt' ),
				'subscription_cancelled' => array( 'label' => __( 'Subscription cancelled', 'wp-easycart' ), 'icon' => 'dismiss' ),
				'download'          => array( 'label' => __( 'File downloaded', 'wp-easycart' ), 'icon' => 'download' ),
				'newsletter_subscribed'   => array( 'label' => __( 'Subscribed to newsletter', 'wp-easycart' ), 'icon' => 'megaphone' ),
				'newsletter_unsubscribed' => array( 'label' => __( 'Unsubscribed from newsletter', 'wp-easycart' ), 'icon' => 'dismiss' ),
				'admin_login_as'    => array( 'label' => __( 'Admin signed in as customer', 'wp-easycart' ), 'icon' => 'businessperson' ),
				'anonymized'        => array( 'label' => __( 'Account anonymized', 'wp-easycart' ), 'icon' => 'hidden' ),
				'merged'            => array( 'label' => __( 'Duplicate account merged in', 'wp-easycart' ), 'icon' => 'networking' ),
			) );
		}

		public function get_type_label( $type ) {
			$types = $this->get_types();
			if ( isset( $types[ $type ]['label'] ) ) {
				return $types[ $type ]['label'];
			}
			return ucwords( str_replace( array( '_', '-' ), ' ', $type ) );
		}

		public function get_type_icon( $type ) {
			$types = $this->get_types();
			return isset( $types[ $type ]['icon'] ) ? $types[ $type ]['icon'] : 'marker';
		}

		/* ------------------------------------------------------------------ */
		/* Write path                                                           */
		/* ------------------------------------------------------------------ */

		public function is_enabled() {
			return (bool) apply_filters( 'wp_easycart_user_activity_enabled', '1' == get_option( 'ec_option_user_activity_enabled', '1' ) );
		}

		/**
		 * Record one activity row.
		 *
		 * @param int    $user_id ec_user.user_id ( required, > 0 ).
		 * @param string $type    Activity type key ( sanitized to a key ).
		 * @param array  $args    object_type, object_id, meta ( array ),
		 *                        actor_type, actor_id, activity_date.
		 * @return int|false Inserted activity_id, or false when skipped.
		 */
		public function log( $user_id, $type, $args = array() ) {
			global $wpdb;

			$user_id = (int) $user_id;
			$type = sanitize_key( $type );
			if ( $user_id <= 0 || '' === $type || ! $this->is_enabled() ) {
				return false;
			}
			if ( ! apply_filters( 'wp_easycart_user_activity_should_log', true, $user_id, $type, $args ) ) {
				return false;
			}

			$actor_type = isset( $args['actor_type'] ) ? sanitize_key( $args['actor_type'] ) : $this->detect_actor_type();
			$actor_id = isset( $args['actor_id'] ) ? (int) $args['actor_id'] : (int) get_current_user_id();
			$meta = ( isset( $args['meta'] ) && is_array( $args['meta'] ) && ! empty( $args['meta'] ) ) ? wp_json_encode( $args['meta'] ) : '';

			$inserted = $wpdb->insert( 'ec_user_activity', array(
				'user_id'       => $user_id,
				'activity_type' => substr( $type, 0, 50 ),
				'activity_date' => isset( $args['activity_date'] ) ? $args['activity_date'] : current_time( 'mysql' ),
				'object_type'   => isset( $args['object_type'] ) ? substr( sanitize_key( $args['object_type'] ), 0, 50 ) : '',
				'object_id'     => isset( $args['object_id'] ) ? (int) $args['object_id'] : 0,
				'meta'          => $meta,
				'actor_type'    => substr( $actor_type, 0, 20 ),
				'actor_id'      => $actor_id,
				'ip_address'    => $this->capture_ip(),
			), array( '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s' ) );

			if ( false === $inserted ) {
				return false;
			}
			$activity_id = (int) $wpdb->insert_id;
			do_action( 'wp_easycart_user_activity_logged', $activity_id, $user_id, $type, $args );
			return $activity_id;
		}

		private function detect_actor_type() {
			if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
				return 'system';
			}
			if ( get_current_user_id() > 0 && ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_manager' ) || current_user_can( 'wpec_users' ) ) ) {
				return 'admin';
			}
			return get_current_user_id() > 0 || ! ( defined( 'WP_CLI' ) && WP_CLI ) ? 'customer' : 'system';
		}

		private function capture_ip() {
			if ( ! apply_filters( 'wp_easycart_user_activity_store_ip', true ) ) {
				return '';
			}
			$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
			return substr( $ip, 0, 45 );
		}

		/* ------------------------------------------------------------------ */
		/* Query API ( used by the PRO admin )                                  */
		/* ------------------------------------------------------------------ */

		/**
		 * @param int   $user_id
		 * @param array $args limit ( default 20, max 100 ), offset, type,
		 *                    date_from, date_to ( Y-m-d ).
		 * @return array of row objects.
		 */
		public function get_activity( $user_id, $args = array() ) {
			global $wpdb;
			$limit = isset( $args['limit'] ) ? min( 100, max( 1, (int) $args['limit'] ) ) : 20;
			$offset = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;
			list( $where, $params ) = $this->build_where( $user_id, $args );
			$params[] = $offset;
			$params[] = $limit;
			return $wpdb->get_results( $wpdb->prepare(
				"SELECT activity_id, user_id, activity_type, activity_date, object_type, object_id, meta, actor_type, actor_id, ip_address FROM ec_user_activity {$where} ORDER BY activity_date DESC, activity_id DESC LIMIT %d, %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where placeholders are bound below.
				$params
			) );
		}

		public function count_activity( $user_id, $args = array() ) {
			global $wpdb;
			list( $where, $params ) = $this->build_where( $user_id, $args );

			if ( empty( $params ) ) {
				return 0;
			}
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM ec_user_activity {$where}", $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		/**
		 * Distinct types present for one customer ( drives the filter UI ).
		 */
		public function get_present_types( $user_id ) {
			global $wpdb;
			return $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT activity_type FROM ec_user_activity WHERE user_id = %d ORDER BY activity_type ASC', (int) $user_id ) );
		}

		private function build_where( $user_id, $args ) {
			$where = 'WHERE user_id = %d';
			$params = array( (int) $user_id );
			if ( ! empty( $args['type'] ) ) {
				$where .= ' AND activity_type = %s';
				$params[] = sanitize_key( $args['type'] );
			}
			if ( ! empty( $args['date_from'] ) ) {
				$where .= ' AND activity_date >= %s';
				$params[] = $args['date_from'] . ' 00:00:00';
			}
			if ( ! empty( $args['date_to'] ) ) {
				$where .= ' AND activity_date <= %s';
				$params[] = $args['date_to'] . ' 23:59:59';
			}
			return array( $where, $params );
		}

		/* ------------------------------------------------------------------ */
		/* Retention / privacy                                                  */
		/* ------------------------------------------------------------------ */

		public function run_retention_purge() {
			$days = (int) apply_filters( 'wp_easycart_user_activity_retention_days', get_option( 'ec_option_activity_retention_days', 365 ) );
			if ( $days <= 0 ) {
				return; // 0 = keep forever.
			}
			global $wpdb;
			$wpdb->query( $wpdb->prepare( 'DELETE FROM ec_user_activity WHERE activity_date < DATE_SUB( NOW(), INTERVAL %d DAY ) LIMIT 5000', $days ) );
		}

		/**
		 * GDPR: keep the behavioral skeleton, strip identifying payloads.
		 */
		public function anonymize_user( $user_id ) {
			global $wpdb;
			$wpdb->query( $wpdb->prepare( "UPDATE ec_user_activity SET ip_address = '', meta = '' WHERE user_id = %d", (int) $user_id ) );
		}

		/**
		 * Merge tool support: move the duplicate's history onto the target.
		 */
		public function merge_user( $source_id, $target_id ) {
			global $wpdb;
			$wpdb->query( $wpdb->prepare( 'UPDATE ec_user_activity SET user_id = %d WHERE user_id = %d', (int) $target_id, (int) $source_id ) );
		}

		/* ------------------------------------------------------------------ */
		/* Self-wired listeners ( hooks that already fire today )               */
		/* ------------------------------------------------------------------ */

		private function user_id_from_email( $email ) {
			global $wpdb;
			return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT user_id FROM ec_user WHERE email = %s', sanitize_email( $email ) ) );
		}

		private function order_row( $order_id ) {
			global $wpdb;
			return $wpdb->get_row( $wpdb->prepare( 'SELECT user_id, grand_total, refund_total FROM ec_order WHERE order_id = %d', (int) $order_id ) );
		}

		public function on_login( $email ) {
			if ( is_admin() ) {
				return; // Impersonation is logged separately as admin_login_as.
			}
			$user_id = $this->user_id_from_email( $email );
			if ( $user_id ) {
				$this->log( $user_id, 'login', array( 'actor_type' => 'customer' ) );
			}
		}

		public function on_account_added( $user_id, $email = '' ) {
			$this->log( (int) $user_id, 'account_created', array( 'actor_type' => is_admin() ? 'admin' : 'customer' ) );
		}

		public function on_account_updated( $user_id ) {
			$this->log( (int) $user_id, 'account_updated' );
		}

		public function on_order_paid( $order_id ) {
			$order = $this->order_row( $order_id );
			if ( $order && (int) $order->user_id > 0 ) {
				$this->log( (int) $order->user_id, 'order_paid', array(
					'object_type' => 'order',
					'object_id' => (int) $order_id,
					'meta' => array( 'total' => (float) $order->grand_total ),
					'actor_type' => 'customer',
				) );
			}
		}

		public function on_order_refund( $order_id ) {
			$order = $this->order_row( $order_id );
			if ( $order && (int) $order->user_id > 0 ) {
				$this->log( (int) $order->user_id, 'order_refunded', array(
					'object_type' => 'order',
					'object_id' => (int) $order_id,
					'meta' => array( 'refund_total' => (float) $order->refund_total ),
				) );
			}
		}

		public function on_admin_login_as( $user_id, $admin_id ) {
			$this->log( (int) $user_id, 'admin_login_as', array( 'actor_type' => 'admin', 'actor_id' => (int) $admin_id ) );
		}

		public function on_logout( $user_id ) {
			$this->log( (int) $user_id, 'logout', array( 'actor_type' => 'customer' ) );
		}

		public function on_password_reset_requested( $user_id ) {
			$this->log( (int) $user_id, 'password_reset', array( 'actor_type' => 'customer' ) );
		}

		public function on_password_reset_complete( $user_id ) {
			$this->log( (int) $user_id, 'password_changed', array( 'actor_type' => 'customer', 'meta' => array( 'method' => 'reset_link' ) ) );
		}

		public function on_password_updated( $user_id ) {
			$this->log( (int) $user_id, 'password_changed', array( 'actor_type' => 'customer', 'meta' => array( 'method' => 'account' ) ) );
		}

		public function on_subscription_cancelled( $user_id, $subscription_id ) {
			$this->log( (int) $user_id, 'subscription_cancelled', array(
				'object_type' => 'subscription',
				'object_id' => (int) $subscription_id,
			) );
		}

		public function on_subscription_paid( $order_id ) {
			$order = $this->order_row( $order_id );
			if ( $order && (int) $order->user_id > 0 ) {
				$this->log( (int) $order->user_id, 'subscription_paid', array(
					'object_type' => 'order',
					'object_id' => (int) $order_id,
					'meta' => array( 'total' => (float) $order->grand_total ),
					'actor_type' => 'system',
				) );
			}
		}

		public function on_subscription_started( $order_id ) {
			$order = $this->order_row( $order_id );
			if ( $order && (int) $order->user_id > 0 ) {
				$this->log( (int) $order->user_id, 'subscription_started', array(
					'object_type' => 'order',
					'object_id' => (int) $order_id,
					'meta' => array( 'total' => (float) $order->grand_total ),
					'actor_type' => 'customer',
				) );
			}
		}

		/**
		 * Status changes are never customer-initiated: an admin in wp-admin,
		 * or the system ( gateway webhook / offers automation ) elsewhere.
		 */
		private function admin_or_system() {
			return ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_manager' ) || current_user_can( 'wpec_orders' ) ) ? 'admin' : 'system';
		}

		public function on_order_status( $order_id, $orderstatus_id ) {
			global $wpdb;
			$order = $this->order_row( $order_id );
			if ( ! $order || (int) $order->user_id <= 0 ) {
				return;
			}
			$status_label = $wpdb->get_var( $wpdb->prepare( 'SELECT order_status FROM ec_orderstatus WHERE status_id = %d', (int) $orderstatus_id ) );
			$this->log( (int) $order->user_id, 'order_status', array(
				'object_type' => 'order',
				'object_id' => (int) $order_id,
				'meta' => array(
					'status_id' => (int) $orderstatus_id,
					'status' => (string) $status_label,
				),
				'actor_type' => $this->admin_or_system(),
			) );
		}

		public function on_tracking_update( $order_id, $use_expedited_shipping = '', $shipping_method = '', $shipping_carrier = '', $tracking_number = '' ) {
			if ( '' === trim( (string) $tracking_number ) ) {
				return; // Only log when a tracking number is actually set.
			}
			$order = $this->order_row( $order_id );
			if ( ! $order || (int) $order->user_id <= 0 ) {
				return;
			}
			$this->log( (int) $order->user_id, 'tracking_added', array(
				'object_type' => 'order',
				'object_id' => (int) $order_id,
				'meta' => array(
					'carrier' => (string) $shipping_carrier,
					'tracking' => (string) $tracking_number,
				),
				'actor_type' => $this->admin_or_system(),
			) );
		}

		/**
		 * Stripe webhook customer.subscription.deleted — covers payment-
		 * failure terminations and period-end cancels. A customer-initiated
		 * account-page cancel also triggers this webhook moments later, so a
		 * short dedupe window suppresses the echo.
		 */
		public function on_subscription_ended( $subscription, $user, $webhook_data = null ) {
			global $wpdb;
			$user_id = ( is_object( $user ) && isset( $user->user_id ) ) ? (int) $user->user_id : 0;
			if ( $user_id <= 0 ) {
				return;
			}
			$subscription_id = ( is_object( $subscription ) && isset( $subscription->subscription_id ) ) ? (int) $subscription->subscription_id : 0;

			$recent = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM ec_user_activity WHERE user_id = %d AND activity_type = 'subscription_cancelled' AND object_id = %d AND activity_date > DATE_SUB( NOW(), INTERVAL 1 HOUR )",
				$user_id, $subscription_id
			) );
			if ( $recent > 0 ) {
				return;
			}
			$this->log( $user_id, 'subscription_cancelled', array(
				'object_type' => 'subscription',
				'object_id' => $subscription_id,
				'meta' => array( 'source' => 'stripe_webhook' ),
				'actor_type' => 'system',
			) );
		}

		/**
		 * Newsletter membership. Three producers feed the subscribe side:
		 * the newsletter widget + admin subscribers screen
		 * ( wpeasycart_subscriber_added ) and checkout opt-ins
		 * ( wpeasycart_insert_subscriber ) — all pass the email first, so one
		 * handler serves both. The admin "update subscriber" flow re-fires
		 * added on every edit and checkout opt-ins repeat per order, so
		 * log_newsletter_direction() only records actual state changes.
		 * Guest signups with no ec_user account are skipped by design ( the
		 * log is keyed to customer accounts ).
		 */
		public function on_newsletter_subscribed( $email ) {
			$this->log_newsletter_direction( $email, 'newsletter_subscribed' );
		}

		public function on_newsletter_unsubscribed( $email ) {
			$this->log_newsletter_direction( $email, 'newsletter_unsubscribed' );
		}

		private function log_newsletter_direction( $email, $type ) {
			global $wpdb;
			$user_id = $this->user_id_from_email( $email );
			if ( $user_id <= 0 ) {
				return;
			}
			$last = $wpdb->get_var( $wpdb->prepare(
				"SELECT activity_type FROM ec_user_activity WHERE user_id = %d AND activity_type IN ( 'newsletter_subscribed', 'newsletter_unsubscribed' ) ORDER BY activity_date DESC, activity_id DESC LIMIT 1",
				$user_id
			) );
			if ( $last === $type ) {
				return; // No state change — suppress the repeat.
			}
			$this->log( $user_id, $type );
		}

		public function on_anonymized( $user_id ) {
			// Priority 5: strip history payloads first, then record the event.
			$this->anonymize_user( (int) $user_id );
			$this->log( (int) $user_id, 'anonymized', array( 'actor_type' => 'admin' ) );
		}

		public function on_merged( $source_id, $target_id ) {
			// Priority 5: move history before the source row disappears.
			$this->merge_user( (int) $source_id, (int) $target_id );
			$this->log( (int) $target_id, 'merged', array(
				'actor_type' => 'admin',
				'meta' => array( 'merged_user_id' => (int) $source_id ),
			) );
		}
	}

endif;

if ( ! function_exists( 'wp_easycart_user_activity' ) ) {
	function wp_easycart_user_activity() {
		return wp_easycart_user_activity::instance();
	}
}

/**
 * One-line logger for core call sites ( wired in a later phase ):
 *   wp_easycart_log_user_activity( $user_id, 'download', array( 'object_type' => 'download', 'object_id' => $id ) );
 */
if ( ! function_exists( 'wp_easycart_log_user_activity' ) ) {
	function wp_easycart_log_user_activity( $user_id, $type, $args = array() ) {
		return wp_easycart_user_activity()->log( $user_id, $type, $args );
	}
}

// Boot the singleton so the self-wired listeners register.
wp_easycart_user_activity();