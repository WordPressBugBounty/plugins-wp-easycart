<?php
/**
 * WP EasyCart Admin — Email delivery health ( phase E1 ).
 *
 *   wp_easycart_admin_email_health_card()    Store Status card ( + top of Settings → Email )
 *   wp_easycart_admin_email_checks_panel()   pre-flight checks
 *   wp_easycart_admin_email_log_panel()      log with retry actions
 *   wp_easycart_admin_email_order_chip()     "Receipt not sent" chip for the Orders list
 *   admin_notices banner after 3 consecutive failures
 *   AJAX: ecv2_email_retry, ecv2_email_retry_all, ecv2_email_resend_receipt, ecv2_email_run_checks,
 *         ecv2_email_queue_toggle, ecv2_email_dismiss_banner, ecv2_email_log, ecv2_email_send_test
 *   On-screen help ( fix steps, 3-step guide, glossary ): wp_easycart_admin_email_help.php ( @since 6.0.0 )
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_email_help' ) && file_exists( __DIR__ . '/wp_easycart_admin_email_help.php' ) ) {
	require_once __DIR__ . '/wp_easycart_admin_email_help.php';
}

if ( ! class_exists( 'wp_easycart_admin_email_health' ) ) :

	class wp_easycart_admin_email_health {

		const NONCE = 'wp-easycart-email-health';

		public static function init() {
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
			add_action( 'admin_notices', array( __CLASS__, 'banner' ) );
		}

		public static function available() { return class_exists( 'ec_email' ) && ec_email::tables_exist(); }

		public static function enqueue() {
			if ( ! isset( $_GET['page'] ) || 0 !== strpos( sanitize_text_field( wp_unslash( $_GET['page'] ) ), 'wp-easycart' ) ) { return; } // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only check of which admin screen is loading.
			wp_enqueue_style( 'wp_easycart_admin_email_health_css', plugins_url( '/admin/css/email-health-v2.css', EC_PLUGIN_DIRECTORY . '/wpeasycart.php' ), array(), EC_CURRENT_VERSION );
			wp_enqueue_script( 'wp_easycart_admin_email_health_js', plugins_url( '/admin/js/email-health-v2.js', EC_PLUGIN_DIRECTORY . '/wpeasycart.php' ), array( 'jquery' ), EC_CURRENT_VERSION, true );
			wp_localize_script(
				'wp_easycart_admin_email_health_js',
				'ecv2_email_vars',
				array(
					'nonce'    => wp_create_nonce( self::NONCE ),
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'i18n'     => array(
						'error'     => __( 'Something went wrong.', 'wp-easycart' ),
						'refreshed' => __( 'Checks refreshed.', 'wp-easycart' ),
						'copy'      => __( 'Copy', 'wp-easycart' ),
						'copied'    => __( 'Copied', 'wp-easycart' ),
						'copy_fail' => __( 'Could not copy. Select the text and copy it by hand.', 'wp-easycart' ),
						'sending'   => __( 'Sending…', 'wp-easycart' ),
						'checking'  => __( 'Checking…', 'wp-easycart' ),
					),
				)
			);
		}

		public static function settings_url() { return admin_url( 'admin.php?page=wp-easycart-settings&subpage=email' ); }
		public static function orders_url() { return admin_url( 'admin.php?page=wp-easycart-orders&subpage=orders&email_status=failed' ); }

		/* ------------------------------------------------------------------ */
		/* Health card                                                         */
		/* ------------------------------------------------------------------ */

		public static function health_card( $compact = false ) {
			if ( ! self::available() ) {
				echo '<div class="ecem-card"><div class="ecem-card-h"><h3>' . esc_html__( 'Email health', 'wp-easycart' ) . '</h3></div><div class="ecos-note"><span class="dashicons dashicons-warning"></span><div>' . esc_html__( 'The database update for email logging has not run yet. Load any EasyCart admin page after updating.', 'wp-easycart' ) . '</div></div></div>';
				return;
			}
			$h = ec_email::health( 7 );
			$rate = $h['rate']; $tone = null === $rate ? 'gray' : ( $rate >= 98 ? 'green' : ( $rate >= 85 ? 'amber' : 'red' ) );
			$since = '';
			if ( $h['failed'] ) { global $wpdb; $first = $wpdb->get_var( "SELECT MIN( created_at ) FROM ec_email_log WHERE status IN ( 'failed', 'retrying' ) AND created_at >= DATE_SUB( NOW(), INTERVAL 7 DAY )" ); if ( $first ) { $since = date_i18n( 'M j', strtotime( $first ) ); } }
			echo '<div class="ecem-card ecem-health" id="ecem_health">';
			echo '<div class="ecem-card-h"><h3>' . esc_html__( 'Email health', 'wp-easycart' ) . '</h3><span class="ecem-hint">' . esc_html__( 'Receipts, status updates, account mail, review requests', 'wp-easycart' ) . '</span><a class="ecem-link" href="' . esc_url( self::settings_url() . '#ecem_log' ) . '">' . esc_html__( 'Open the email log →', 'wp-easycart' ) . '</a></div>';
			echo '<div class="ecem-health-grid' . ( $compact ? ' is-compact' : '' ) . '">';
			echo '<div class="ecem-gauge"><div class="ecem-ring ecem-ring-' . esc_attr( $tone ) . '" style="--pct:' . (int) ( null === $rate ? 0 : $rate ) . '"><i><b>' . ( null === $rate ? '—' : (int) $rate . '%' ) . '</b><small>' . esc_html__( 'delivered · 7d', 'wp-easycart' ) . '</small></i></div>';
			if ( $h['streak'] >= ec_email::FAIL_STREAK_ALERT ) { echo '<span class="ecv2-chip ecv2-chip-red">' . esc_html( $since ? sprintf( __( 'Failing since %s', 'wp-easycart' ), $since ) : __( 'Failing', 'wp-easycart' ) ) . '</span>'; }
			else if ( null === $rate ) { echo '<span class="ecv2-chip ecv2-chip-gray">' . esc_html__( 'No emails sent yet', 'wp-easycart' ) . '</span>'; }
			else if ( $rate >= 98 ) { echo '<span class="ecv2-chip ecv2-chip-green">' . esc_html__( 'Healthy', 'wp-easycart' ) . '</span>'; }
			else { echo '<span class="ecv2-chip ecv2-chip-amber">' . esc_html__( 'Some failures', 'wp-easycart' ) . '</span>'; }
			echo '</div><div>';
			if ( class_exists( 'wp_easycart_admin_email_help' ) ) { wp_easycart_admin_email_help::print_summary( $h ); }
			echo '<div class="ecem-kpis">';
			echo '<div class="ecem-kpi' . ( $h['failed'] ? ' is-bad' : '' ) . '"><b>' . (int) $h['failed'] . '</b><span>' . esc_html__( 'failed · 7 days', 'wp-easycart' ) . '</span></div>';
			echo '<div class="ecem-kpi is-ok"><b>' . (int) $h['sent'] . '</b><span>' . esc_html__( 'sent · 7 days', 'wp-easycart' ) . '</span></div>';
			echo '<div class="ecem-kpi"><b>' . esc_html( $h['last_success'] ? date_i18n( 'M j, H:i', strtotime( $h['last_success'] ) ) : '—' ) . '</b><span>' . esc_html__( 'last successful send', 'wp-easycart' ) . '</span></div>';
			echo '<div class="ecem-kpi"><b>' . esc_html( ec_email::transport_label( $h['transport'] ) ) . '</b><span>' . esc_html__( 'transport in use', 'wp-easycart' ) . ( 'plugin_smtp' === $h['transport'] ? ' · ' . esc_html( get_option( 'ec_option_order_from_smtp_host' ) . ':' . get_option( 'ec_option_order_from_smtp_port' ) ) : '' ) . '</span></div>';
			echo '</div>';
			if ( $h['queued'] || $h['parked'] ) { echo '<div class="ecem-queue-line">' . esc_html( sprintf( __( 'Queue: %1$d waiting to retry · %2$d need attention', 'wp-easycart' ), $h['queued'], $h['parked'] ) ) . ( $h['affected_orders'] ? ' · <a href="' . esc_url( self::orders_url() ) . '">' . esc_html( sprintf( _n( '%d order affected', '%d orders affected', $h['affected_orders'], 'wp-easycart' ), $h['affected_orders'] ) ) . '</a>' : '' ) . '</div>'; }
			/* sparkline */
			echo '<div class="ecem-spark-label">' . esc_html__( 'Last 14 days', 'wp-easycart' ) . '</div><div class="ecem-spark">';
			$max = 1; foreach ( $h['daily'] as $d ) { $max = max( $max, $d['sent'] + $d['failed'] ); }
			for ( $i = 13; $i >= 0; $i-- ) { $day = date( 'Y-m-d', current_time( 'timestamp' ) - $i * DAY_IN_SECONDS ); $d = isset( $h['daily'][ $day ] ) ? $h['daily'][ $day ] : array( 'sent' => 0, 'failed' => 0 ); $tot = $d['sent'] + $d['failed']; $hgt = $tot ? max( 8, (int) ( 100 * $tot / $max ) ) : 3; echo '<i class="' . esc_attr( $d['failed'] > $d['sent'] ? 'f' : ( $d['failed'] ? 'm' : '' ) ) . '" style="height:' . (int) $hgt . '%" title="' . esc_attr( date_i18n( 'M j', strtotime( $day ) ) . ': ' . $d['sent'] . ' sent, ' . $d['failed'] . ' failed' ) . '"></i>'; }
			echo '</div>';
			if ( $h['last_error'] ) {
				$le = $h['last_error']; $ex = ec_email::explain( $le->error_text );
				echo '<div class="ecem-err-label">' . esc_html__( 'Last error', 'wp-easycart' ) . ' <span>· ' . esc_html( date_i18n( 'M j, H:i', strtotime( $le->created_at ) ) . ' · ' . self::type_label( $le->email_type ) . ( $le->order_id ? ' #' . (int) $le->order_id : '' ) . ' · ' . ec_email::transport_label( $le->transport ) ) . '</span></div>';
				echo '<pre class="ecem-err">' . esc_html( $le->error_text ) . '</pre>';
				echo '<div class="ecos-note ecos-note-danger"><span class="dashicons dashicons-warning"></span><div><b>' . esc_html__( 'What this means:', 'wp-easycart' ) . '</b> ' . esc_html( $ex['meaning'] ) . ' <a href="' . esc_url( self::settings_url() ) . '">' . esc_html( $ex['fix'] ) . '</a></div></div>';
			}
			echo '</div></div></div>';
		}

		public static function type_label( $t ) {
			$l = array( 'order' => __( 'Order email', 'wp-easycart' ), 'order_receipt' => __( 'Order receipt', 'wp-easycart' ), 'order_shipped' => __( 'Order shipped', 'wp-easycart' ), 'packing_slip' => __( 'Packing slip', 'wp-easycart' ), 'order_status' => __( 'Status update', 'wp-easycart' ), 'store' => __( 'Store email', 'wp-easycart' ), 'account' => __( 'Account email', 'wp-easycart' ), 'review_request' => __( 'Review request', 'wp-easycart' ), 'review_reminder' => __( 'Review reminder', 'wp-easycart' ), 'review_reply' => __( 'Review reply', 'wp-easycart' ), 'review_alert' => __( 'Low-rating alert', 'wp-easycart' ), 'giftcard' => __( 'Gift card', 'wp-easycart' ), 'test' => __( 'Test', 'wp-easycart' ), 'test_failure' => __( 'Test — simulated failure', 'wp-easycart' ), 'subscription_trial' => __( 'Subscription trial started', 'wp-easycart' ), 'subscription_trial_ending' => __( 'Subscription trial ending', 'wp-easycart' ), 'subscription_upcoming' => __( 'Subscription renewal notice', 'wp-easycart' ), 'subscription_ended' => __( 'Subscription ended', 'wp-easycart' ), 'subscription_failed' => __( 'Subscription payment failed', 'wp-easycart' ) );
			return isset( $l[ $t ] ) ? $l[ $t ] : ucfirst( str_replace( '_', ' ', (string) $t ) );
		}

		/* ------------------------------------------------------------------ */
		/* Checks panel                                                        */
		/* ------------------------------------------------------------------ */

		public static function checks_panel() {
			if ( ! class_exists( 'ec_email' ) ) { return; }
			$checks = ec_email::checks();
			$help   = class_exists( 'wp_easycart_admin_email_help' );
			if ( $help ) {
				wp_easycart_admin_email_help::print_guide( $checks );
			}
			echo '<div class="ecem-card" id="ecem_checks">';
			echo '<div class="ecem-card-h"><h3>' . esc_html__( 'Delivery checks', 'wp-easycart' ) . '</h3><span class="ecem-hint" id="ecem_checks_hint">' . esc_html( self::checks_hint( $checks ) ) . '</span><button type="button" class="ecem-link" data-ecem-run>' . esc_html__( 'Run again', 'wp-easycart' ) . '</button></div>';
			if ( $help ) {
				wp_easycart_admin_email_help::print_glossary();
			}
			echo '<div class="ecem-checks">';
			self::print_check_rows( $checks );
			echo '</div>';
			echo '<div class="ecos-note ecos-note-info"><span class="dashicons dashicons-info-outline" aria-hidden="true"></span><div>' . esc_html__( 'Checks are advisory and never block sending. They re-run on save and when you open this page.', 'wp-easycart' ) . '</div></div>';
			if ( $help ) {
				wp_easycart_admin_email_help::print_help_links( __( 'Need a hand?', 'wp-easycart' ) );
			}
			echo '</div>';
		}

		/** "2 warnings" / "1 problem" / "All clear" for the checks card header. */
		public static function checks_hint( $checks ) {
			$counts = array( 'bad' => 0, 'warn' => 0, 'ok' => 0 );
			foreach ( $checks as $c ) {
				if ( isset( $c['status'], $counts[ $c['status'] ] ) ) {
					$counts[ $c['status'] ]++;
				}
			}
			if ( $counts['bad'] ) {
				/* translators: %d: number of problems */
				return sprintf( _n( '%d problem', '%d problems', $counts['bad'], 'wp-easycart' ), $counts['bad'] );
			}
			if ( $counts['warn'] ) {
				/* translators: %d: number of warnings */
				return sprintf( _n( '%d warning', '%d warnings', $counts['warn'], 'wp-easycart' ), $counts['warn'] );
			}
			return __( 'All clear', 'wp-easycart' );
		}

		public static function print_check_rows( $checks ) {
			$icons = array( 'ok' => '✓', 'warn' => '!', 'bad' => '✕', 'skip' => '–' );
			$words = array( 'ok' => __( 'Passed:', 'wp-easycart' ), 'warn' => __( 'Warning:', 'wp-easycart' ), 'bad' => __( 'Problem:', 'wp-easycart' ), 'skip' => __( 'Not checked:', 'wp-easycart' ) );
			$help  = class_exists( 'wp_easycart_admin_email_help' );
			foreach ( $checks as $c ) {
				$c      = array_merge( array( 'key' => '', 'status' => 'skip', 'title' => '', 'detail' => '', 'fix_label' => '', 'fix_url' => '' ), (array) $c );
				$status = isset( $icons[ $c['status'] ] ) ? $c['status'] : 'skip';
				$has    = $help && wp_easycart_admin_email_help::has_help( $c );
				echo '<div class="ecem-check" data-key="' . esc_attr( $c['key'] ) . '"><span class="ecem-st ecem-st-' . esc_attr( $status ) . '" aria-hidden="true">' . esc_html( $icons[ $status ] ) . '</span>';
				echo '<div class="ecem-check-t"><b><span class="screen-reader-text">' . esc_html( $words[ $status ] ) . ' </span>' . esc_html( $c['title'] ) . '</b>' . ( $c['detail'] ? '<small>' . esc_html( $c['detail'] ) . '</small>' : '' );
				if ( $has ) {
					wp_easycart_admin_email_help::print_row_help( $c );
				}
				echo '</div>';
				if ( ! $has && $c['fix_label'] ) {
					$external = 0 === strpos( $c['fix_url'], 'http' ) && false === strpos( $c['fix_url'], home_url() );
					echo '<a class="ecem-fix" href="' . esc_url( $c['fix_url'] ) . '"' . ( $external ? ' target="_blank" rel="noopener"' : '' ) . '>' . esc_html( $c['fix_label'] ) . '</a>';
				}
				echo '</div>';
			}
		}

		/* ------------------------------------------------------------------ */
		/* Log panel                                                           */
		/* ------------------------------------------------------------------ */

		public static function log_panel() {
			if ( ! self::available() ) { return; }
			global $wpdb;
			$counts = $wpdb->get_row( "SELECT SUM( status = 'failed' ) AS failed, SUM( status = 'retrying' ) AS retrying, SUM( status = 'sent' ) AS sent, COUNT(*) AS total FROM ec_email_log WHERE created_at >= DATE_SUB( NOW(), INTERVAL 30 DAY )", ARRAY_A );
			$queue_on = ec_email::queue_enabled();
			echo '<div class="ecem-card" id="ecem_log">';
			echo '<div class="ecem-card-h"><h3>' . esc_html__( 'Email log', 'wp-easycart' ) . '</h3><span class="ecem-hint">' . esc_html__( 'Last 30 days', 'wp-easycart' ) . '</span>';
			echo '<label class="ecem-queue-toggle" title="' . esc_attr__( 'When on, a failed send is retried automatically (1 min, 10 min, 1 h, 6 h) instead of being lost.', 'wp-easycart' ) . '"><input type="checkbox" id="ecem_queue_on"' . checked( $queue_on, true, false ) . ' onchange="ecem.queue_toggle( this.checked );"> ' . esc_html__( 'Retry failed emails automatically', 'wp-easycart' ) . '</label></div>';
			echo '<div class="ecem-log-bar">';
			foreach ( array( 'failed' => array( __( 'Failed', 'wp-easycart' ), 'red' ), 'retrying' => array( __( 'Retrying', 'wp-easycart' ), 'amber' ), 'sent' => array( __( 'Sent', 'wp-easycart' ), 'green' ), '' => array( __( 'All', 'wp-easycart' ), 'gray' ) ) as $k => $m ) {
				echo '<a href="#" class="ecv2-chip ecv2-chip-' . esc_attr( $m[1] ) . ( '' === $k ? ' is-on' : '' ) . '" data-status="' . esc_attr( $k ) . '" onclick="ecem.log_filter( this ); return false;">' . esc_html( $m[0] ) . ' ' . (int) ( '' === $k ? $counts['total'] : $counts[ $k ] ) . '</a>';
			}
			echo '<input type="search" class="ecv2-input ecem-log-search" id="ecem_log_q" placeholder="' . esc_attr__( 'Search order, email, subject…', 'wp-easycart' ) . '" oninput="ecem.log_search( this.value );">';
			if ( (int) $counts['failed'] ) { echo '<button type="button" class="ecv2-btn ecv2-btn-sm" onclick="ecem.retry_all();">' . esc_html__( 'Retry all failed', 'wp-easycart' ) . '</button>'; }
			echo '</div>';
			echo '<div class="ecem-log-table" id="ecem_log_rows">';
			self::print_log_page( '', '', 1 );
			echo '</div>';
			echo '<div class="ecos-note ecos-note-info"><span class="dashicons dashicons-info-outline"></span><div>' . esc_html__( 'The log stores recipient, subject, transport and result — not the email body. Queued emails keep their body until sent. Entries are kept for 30 days.', 'wp-easycart' ) . '</div></div>';
			echo '</div>';
		}

		/** Rows per page in the email log. @since 6.0.0 */
		const LOG_PER_PAGE = 25;

		/**
		 * WHERE clause ( placeholders only ) and its arguments for the log filters.
		 *
		 * @since 6.0.0
		 * @param string $status '' | failed | retrying | sent.
		 * @param string $q      Search: order number ( digits, optional # ), address, subject or error text.
		 * @return array array( string $sql, array $args ).
		 */
		private static function log_where( $status, $q ) {
			global $wpdb;
			$sql  = 'l.created_at >= DATE_SUB( NOW(), INTERVAL 30 DAY )';
			$args = array();
			if ( in_array( $status, array( 'failed', 'retrying', 'sent' ), true ) ) {
				$sql   .= ' AND l.status = %s';
				$args[] = $status;
			}
			$q = trim( (string) $q );
			if ( '' !== $q ) {
				$like = '%' . $wpdb->esc_like( $q ) . '%';
				$num  = ltrim( $q, '#' );
				if ( '' !== $num && ctype_digit( $num ) ) {
					/* An order number: match it exactly ( indexed ) as well as in the text columns. Before 6.0.0 any text search also matched every log row with no order ( order_id = 0 ). */
					$sql .= ' AND ( l.order_id = %d OR l.to_email LIKE %s OR l.subject LIKE %s OR l.error_text LIKE %s )';
					array_push( $args, (int) $num, $like, $like, $like );
				} else {
					$sql .= ' AND ( l.to_email LIKE %s OR l.subject LIKE %s OR l.error_text LIKE %s )';
					array_push( $args, $like, $like, $like );
				}
			}
			return array( $sql, $args );
		}

		public static function log_rows( $status, $q, $limit = 50, $offset = 0 ) {
			global $wpdb;
			list( $where, $args ) = self::log_where( $status, $q );
			$args[] = max( 1, (int) $limit );
			$args[] = max( 0, (int) $offset );
			return $wpdb->get_results( $wpdb->prepare( "SELECT l.*, q.status AS queue_status, q.attempts AS queue_attempts, q.next_attempt FROM ec_email_log l LEFT JOIN ec_email_queue q ON q.queue_id = l.queue_id WHERE {$where} ORDER BY l.log_id DESC LIMIT %d OFFSET %d", $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is built from literal SQL and placeholders only ( log_where() ).
		}

		/**
		 * Number of log rows matching the filters.
		 *
		 * @since 6.0.0
		 * @param string $status Status filter.
		 * @param string $q      Search.
		 * @return int
		 */
		public static function log_count( $status, $q ) {
			global $wpdb;
			list( $where, $args ) = self::log_where( $status, $q );
			$sql = "SELECT COUNT(*) FROM ec_email_log l WHERE {$where}";
			if ( $args ) {
				$sql = $wpdb->prepare( $sql, $args ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql is literal SQL plus placeholders from log_where().
			}
			return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above when it has arguments; otherwise literal SQL.
		}

		/**
		 * One page of the log ( rows + pager ), as printed into #ecem_log_rows.
		 *
		 * @since 6.0.0
		 * @param string $status Status filter.
		 * @param string $q      Search.
		 * @param int    $page   1-based page.
		 */
		public static function print_log_page( $status, $q, $page ) {
			$per   = (int) apply_filters( 'wp_easycart_email_log_per_page', self::LOG_PER_PAGE );
			$per   = max( 5, min( 200, $per ) );
			$total = self::log_count( $status, $q );
			$pages = max( 1, (int) ceil( $total / $per ) );
			$page  = max( 1, min( $pages, (int) $page ) );
			self::print_log_rows( $total ? self::log_rows( $status, $q, $per, ( $page - 1 ) * $per ) : array() );
			self::print_log_pager( $page, $pages, $total, $per );
		}

		/**
		 * "Showing 26–50 of 1,204" and first / previous / next / last buttons ( V2 list pagination look ).
		 *
		 * @since 6.0.0
		 * @param int $page  Current page.
		 * @param int $pages Page count.
		 * @param int $total Matching rows.
		 * @param int $per   Rows per page.
		 */
		public static function print_log_pager( $page, $pages, $total, $per ) {
			if ( $total <= $per ) {
				return;
			}
			$from = ( ( $page - 1 ) * $per ) + 1;
			$to   = min( $total, $page * $per );
			echo '<nav class="ecv2-pagination ecem-log-pager" aria-label="' . esc_attr__( 'Email log pages', 'wp-easycart' ) . '">';
			/* translators: 1: first row shown, 2: last row shown, 3: total rows */
			echo '<div class="ecv2-pagination-center">' . esc_html( sprintf( __( 'Showing %1$s–%2$s of %3$s', 'wp-easycart' ), number_format_i18n( $from ), number_format_i18n( $to ), number_format_i18n( $total ) ) ) . '</div>';
			echo '<div class="ecv2-pagination-right">';
			$buttons = array(
				array( 1, '&laquo;', __( 'First page', 'wp-easycart' ), $page <= 1 ),
				array( $page - 1, '&lsaquo;', __( 'Previous page', 'wp-easycart' ), $page <= 1 ),
				'info',
				array( $page + 1, '&rsaquo;', __( 'Next page', 'wp-easycart' ), $page >= $pages ),
				array( $pages, '&raquo;', __( 'Last page', 'wp-easycart' ), $page >= $pages ),
			);
			foreach ( $buttons as $button ) {
				if ( 'info' === $button ) {
					/* translators: 1: current page, 2: number of pages */
					echo '<span class="ecv2-page-info">' . esc_html( sprintf( __( 'Page %1$s of %2$s', 'wp-easycart' ), number_format_i18n( $page ), number_format_i18n( $pages ) ) ) . '</span>';
					continue;
				}
				echo '<button type="button" class="ecv2-page-btn' . ( $button[3] ? ' disabled' : '' ) . '" data-ecem-page="' . (int) $button[0] . '" title="' . esc_attr( $button[2] ) . '" aria-label="' . esc_attr( $button[2] ) . '"' . ( $button[3] ? ' disabled' : '' ) . '>' . $button[1] . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $button[1] is a literal HTML entity from the array above.
			}
			echo '</div></nav>';
		}

		public static function print_log_rows( $rows ) {
			if ( empty( $rows ) ) { echo '<div class="ecem-empty">' . esc_html__( 'No emails match.', 'wp-easycart' ) . '</div>'; return; }
			echo '<table class="ecem-t"><thead><tr><th>' . esc_html__( 'When', 'wp-easycart' ) . '</th><th>' . esc_html__( 'Type', 'wp-easycart' ) . '</th><th>' . esc_html__( 'To', 'wp-easycart' ) . '</th><th>' . esc_html__( 'Transport', 'wp-easycart' ) . '</th><th>' . esc_html__( 'Result', 'wp-easycart' ) . '</th><th></th></tr></thead><tbody>';
			foreach ( $rows as $r ) {
				$ts = strtotime( $r->created_at );
				echo '<tr data-log="' . (int) $r->log_id . '"><td class="ecem-mono">' . esc_html( $ts > current_time( 'timestamp' ) - DAY_IN_SECONDS ? date_i18n( 'H:i', $ts ) : date_i18n( 'M j H:i', $ts ) ) . '</td>';
				echo '<td>' . esc_html( self::type_label( $r->email_type ) ) . ( $r->order_id ? ' · <a href="' . esc_url( admin_url( 'admin.php?page=wp-easycart-orders&subpage=orders&ec_admin_form_action=edit&order_id=' . (int) $r->order_id ) ) . '">#' . (int) $r->order_id . '</a>' : '' ) . '<div class="ecem-sub">' . esc_html( $r->subject ) . '</div></td>';
				echo '<td>' . esc_html( $r->to_email ) . '</td><td>' . esc_html( ec_email::transport_label( $r->transport ) ) . '</td><td>';
				if ( 'sent' === $r->status ) { echo '<span class="ecv2-chip ecv2-chip-green">' . esc_html__( 'Sent', 'wp-easycart' ) . ( (int) $r->attempts > 1 ? ' · ' . esc_html( sprintf( __( 'attempt %d', 'wp-easycart' ), (int) $r->attempts ) ) : '' ) . '</span>'; }
				else if ( 'retrying' === $r->status ) { echo '<span class="ecv2-chip ecv2-chip-amber">' . esc_html( sprintf( __( 'Retry %1$d/%2$d', 'wp-easycart' ), (int) $r->attempts, ec_email::MAX_ATTEMPTS ) ) . ( $r->next_attempt ? ' · ' . esc_html( sprintf( __( 'next %s', 'wp-easycart' ), human_time_diff( strtotime( $r->next_attempt ), current_time( 'timestamp' ) ) ) ) : '' ) . '</span>'; }
				else { echo '<span class="ecv2-chip ecv2-chip-red">' . esc_html( (int) $r->attempts >= ec_email::MAX_ATTEMPTS ? sprintf( __( 'Failed after %d attempts', 'wp-easycart' ), (int) $r->attempts ) : __( 'Failed', 'wp-easycart' ) ) . '</span>'; }
				if ( $r->error_text ) { echo '<div class="ecem-sub ecem-mono" title="' . esc_attr( $r->error_text ) . '">' . esc_html( mb_strimwidth_compat( $r->error_text, 90 ) ) . '</div>'; }
				echo '</td><td class="ecem-acts">';
				if ( $r->queue_id && in_array( $r->queue_status, array( 'pending', 'failed' ), true ) ) { echo '<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" onclick="ecem.retry( ' . (int) $r->queue_id . ', this );">' . esc_html( 'pending' === $r->queue_status ? __( 'Send now', 'wp-easycart' ) : __( 'Retry', 'wp-easycart' ) ) . '</button>'; }
				else if ( 'failed' === $r->status && $r->order_id && in_array( $r->email_type, array( 'order', 'order_receipt', 'store' ), true ) ) { echo '<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" onclick="ecem.resend_receipt( ' . (int) $r->order_id . ', this );">' . esc_html__( 'Resend receipt', 'wp-easycart' ) . '</button>'; }
				echo '</td></tr>';
			}
			echo '</tbody></table>';
		}

		/* ------------------------------------------------------------------ */
		/* Retry queue ( @since 6.0.0 )                                        */
		/* ------------------------------------------------------------------ */

		/**
		 * What is waiting to be retried, when the next attempt is, and the controls to exercise it.
		 *
		 * @since 6.0.0
		 */
		public static function queue_panel() {
			if ( ! self::available() ) {
				return;
			}
			$q = ec_email::queue_status();
			echo '<div class="ecem-card" id="ecem_queue">';
			echo '<div class="ecem-card-h"><h3>' . esc_html__( 'Retry queue', 'wp-easycart' ) . '</h3><span class="ecem-hint">' . esc_html__( 'Emails that did not send first time', 'wp-easycart' ) . '</span>';
			echo '<button type="button" class="ecv2-btn ecv2-btn-sm" onclick="ecem.queue_run( this );">' . esc_html__( 'Send queue now', 'wp-easycart' ) . '</button>';
			echo '<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" onclick="ecem.queue_simulate( this );" title="' . esc_attr__( 'Queues a test message that always fails, so you can watch a retry happen. No mail is sent and no customer is affected.', 'wp-easycart' ) . '">' . esc_html__( 'Simulate a failed send', 'wp-easycart' ) . '</button>';
			echo '</div>';
			echo '<div id="ecem_queue_body">';
			self::print_queue_body( $q );
			echo '</div>';
			self::print_queue_help();
			echo '</div>';
		}

		/**
		 * Counts, next run and the queue table ( replaced on its own by AJAX ).
		 *
		 * @since 6.0.0
		 * @param array $q ec_email::queue_status() result.
		 */
		public static function print_queue_body( $q = null ) {
			$q    = ( null === $q ) ? ec_email::queue_status() : $q;
			$rows = ec_email::queue_rows( 25 );

			echo '<div class="ecem-log-bar">';
			echo '<span class="ecv2-chip ecv2-chip-' . ( $q['pending'] ? 'amber' : 'gray' ) . '">' . esc_html( sprintf( /* translators: %d: number of emails waiting. */ _n( '%d waiting to retry', '%d waiting to retry', (int) $q['pending'], 'wp-easycart' ), (int) $q['pending'] ) ) . '</span>';
			echo '<span class="ecv2-chip ecv2-chip-' . ( $q['failed'] ? 'red' : 'gray' ) . '">' . esc_html( sprintf( /* translators: %d: number of emails that gave up. */ _n( '%d gave up', '%d gave up', (int) $q['failed'], 'wp-easycart' ), (int) $q['failed'] ) ) . '</span>';
			if ( ! $q['queue_enabled'] ) {
				echo '<span class="ecv2-chip ecv2-chip-red">' . esc_html__( 'Automatic retry is off', 'wp-easycart' ) . '</span>';
			} elseif ( $q['next_run'] ) {
				/* translators: %s: human time difference, e.g. "4 mins". */
				echo '<span class="ecem-hint">' . esc_html( sprintf( __( 'Queue runs again in %s', 'wp-easycart' ), human_time_diff( current_time( 'timestamp' ), $q['next_run'] ) ) ) . '</span>'; // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- compared with a WP-scheduled local timestamp.
			} elseif ( $q['pending'] ) {
				echo '<span class="ecem-hint">' . esc_html__( 'Waiting for the next WordPress cron run', 'wp-easycart' ) . '</span>';
			}
			echo '</div>';

			if ( ! $rows ) {
				echo '<div class="ecem-empty">' . esc_html__( 'Nothing is waiting — every email sent first time.', 'wp-easycart' ) . '</div>';
				return;
			}
			echo '<div class="ecem-log-table"><table class="ecem-t"><thead><tr>';
			echo '<th>' . esc_html__( 'Type', 'wp-easycart' ) . '</th><th>' . esc_html__( 'To', 'wp-easycart' ) . '</th><th>' . esc_html__( 'Attempts', 'wp-easycart' ) . '</th><th>' . esc_html__( 'Next attempt', 'wp-easycart' ) . '</th><th>' . esc_html__( 'Last error', 'wp-easycart' ) . '</th><th></th>';
			echo '</tr></thead><tbody>';
			foreach ( $rows as $r ) {
				$simulated = ( ec_email::SIMULATE_TYPE === $r->email_type );
				echo '<tr data-queue="' . (int) $r->queue_id . '">';
				echo '<td>' . esc_html( self::type_label( $r->email_type ) ) . ( $r->order_id ? ' · <a href="' . esc_url( admin_url( 'admin.php?page=wp-easycart-orders&subpage=orders&ec_admin_form_action=edit&order_id=' . (int) $r->order_id ) ) . '">#' . (int) $r->order_id . '</a>' : '' );
				echo '<div class="ecem-sub">' . esc_html( mb_strimwidth_compat( (string) $r->subject, 70 ) ) . '</div></td>';
				echo '<td>' . esc_html( $r->to_email ) . '</td>';
				echo '<td>' . esc_html( sprintf( /* translators: 1: attempts so far, 2: maximum attempts. */ __( '%1$d of %2$d', 'wp-easycart' ), (int) $r->attempts, ec_email::MAX_ATTEMPTS ) ) . '</td>';
				echo '<td>';
				if ( 'failed' === $r->status ) {
					echo '<span class="ecv2-chip ecv2-chip-red">' . esc_html__( 'Gave up', 'wp-easycart' ) . '</span>';
				} elseif ( $r->next_attempt ) {
					$ts = strtotime( $r->next_attempt );
					echo esc_html( date_i18n( 'M j, H:i', $ts ) );
					echo '<div class="ecem-sub">' . esc_html( $ts <= current_time( 'timestamp' ) ? __( 'due now', 'wp-easycart' ) : sprintf( /* translators: %s: human time difference. */ __( 'in %s', 'wp-easycart' ), human_time_diff( current_time( 'timestamp' ), $ts ) ) ) . '</div>'; // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- local time, matching the stored queue time.
				}
				echo '</td>';
				echo '<td>' . ( $r->last_error ? '<span class="ecem-mono" title="' . esc_attr( $r->last_error ) . '">' . esc_html( mb_strimwidth_compat( $r->last_error, 70 ) ) . '</span>' : '' ) . '</td>';
				echo '<td class="ecem-acts"><button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" onclick="ecem.retry( ' . (int) $r->queue_id . ', this );">' . esc_html( 'failed' === $r->status ? __( 'Retry', 'wp-easycart' ) : __( 'Send now', 'wp-easycart' ) ) . '</button>';
				if ( $simulated ) {
					echo ' <button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" onclick="ecem.queue_cancel( ' . (int) $r->queue_id . ', this );">' . esc_html__( 'Remove test', 'wp-easycart' ) . '</button>';
				}
				echo '</td></tr>';
			}
			echo '</tbody></table></div>';
		}

		/**
		 * What happens to an email that does not send first time.
		 *
		 * @since 6.0.0
		 */
		public static function print_queue_help() {
			$steps = array(
				__( 'Sent straight away — it shows as Sent in the email log below.', 'wp-easycart' ),
				__( 'If the mail server refuses it, the email is logged as Failed and put in this queue.', 'wp-easycart' ),
				__( 'It is retried after 1 minute, then 10 minutes, then 1 hour, then every 6 hours ( each attempt appears in the log as Retrying ).', 'wp-easycart' ),
				sprintf( /* translators: %d: maximum attempts. */ __( 'After %d attempts it stops and is marked Failed here and in the log, so you can fix the settings and press Retry.', 'wp-easycart' ), ec_email::MAX_ATTEMPTS ),
			);
			echo '<div class="ecos-note ecos-note-info ecem-queue-help"><span class="dashicons dashicons-info-outline" aria-hidden="true"></span><div><b>' . esc_html__( 'What happens when an email does not send', 'wp-easycart' ) . '</b><ol class="ecem-steps">';
			foreach ( $steps as $step ) {
				echo '<li>' . esc_html( $step ) . '</li>';
			}
			echo '</ol><span class="ecem-hint">' . esc_html__( 'Retries run on WordPress cron every 5 minutes while anything is waiting; "Send queue now" runs them immediately.', 'wp-easycart' ) . '</span></div></div>';
		}

		/* ------------------------------------------------------------------ */
		/* Orders list chip                                                    */
		/* ------------------------------------------------------------------ */

		/**
		 * Queue / failed-log rows per order id, for the orders on the current page only: order_id => rows
		 * ( an empty array means "looked up, nothing wrong" ). Filled by prime() with the page's ids; an id that
		 * was never primed is looked up on its own in order_chip().
		 *
		 * @since 6.0.0 Was one static load of every pending / failed row in the store.
		 * @var array
		 */
		private static $problem_orders = array();

		/**
		 * Load the email problems for a set of orders in two IN(...) queries. The order table calls this once
		 * with its page ids before the rows print.
		 *
		 * @since 6.0.0
		 * @param int[] $order_ids Orders on the page.
		 */
		public static function prime( array $order_ids ) {
			if ( ! self::available() ) { return; }
			$ids = array();
			foreach ( $order_ids as $id ) {
				$id = (int) $id;
				if ( $id > 0 && ! array_key_exists( $id, self::$problem_orders ) ) { $ids[] = $id; }
			}
			$ids = array_values( array_unique( $ids ) );
			if ( empty( $ids ) ) { return; }
			foreach ( $ids as $id ) { self::$problem_orders[ $id ] = array(); }
			global $wpdb;
			$in = implode( ',', $ids );
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $in is an implode of (int)-cast order ids.
			foreach ( (array) $wpdb->get_results( "SELECT order_id, status, email_type, attempts, last_error FROM ec_email_queue WHERE status IN ( 'pending', 'failed' ) AND order_id IN ( {$in} ) ORDER BY queue_id ASC" ) as $q ) { self::$problem_orders[ (int) $q->order_id ][] = $q; }
			foreach ( (array) $wpdb->get_results( "SELECT l.order_id, l.status, l.email_type, l.attempts, l.error_text AS last_error FROM ec_email_log l WHERE l.status = 'failed' AND l.queue_id = 0 AND l.order_id IN ( {$in} ) AND l.created_at >= DATE_SUB( NOW(), INTERVAL 30 DAY ) AND NOT EXISTS ( SELECT 1 FROM ec_email_log s WHERE s.order_id = l.order_id AND s.to_email = l.to_email AND s.status = 'sent' AND s.log_id > l.log_id ) ORDER BY l.log_id ASC" ) as $q ) { self::$problem_orders[ (int) $q->order_id ][] = $q; }
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		public static function order_chip( $order_id ) {
			if ( ! self::available() ) { return ''; }
			$id = (int) $order_id;
			if ( $id <= 0 ) { return ''; }
			if ( ! array_key_exists( $id, self::$problem_orders ) ) {
				self::prime( array( $id ) ); /* not primed by a list: one order, two small indexed lookups */
			}
			if ( empty( self::$problem_orders[ $id ] ) ) { return ''; }
			$q = self::$problem_orders[ $id ][0];
			$pending = 'pending' === $q->status;
			$label = $pending ? __( 'Email retrying', 'wp-easycart' ) : __( 'Receipt not sent', 'wp-easycart' );
			return '<span class="ecem-order-chip"><span class="ecv2-chip ' . ( $pending ? 'ecv2-chip-amber' : 'ecv2-chip-red' ) . '" title="' . esc_attr( $q->last_error ) . '">' . esc_html( $label ) . '</span> <a href="#" class="ecem-order-resend" onclick="ecem.resend_receipt( ' . $id . ', this ); return false;">' . esc_html__( 'Resend', 'wp-easycart' ) . '</a></span>';
		}

		/* ------------------------------------------------------------------ */
		/* Banner                                                              */
		/* ------------------------------------------------------------------ */

		public static function banner() {
			if ( ! self::available() || ! current_user_can( 'manage_options' ) ) { return; }
			if ( ! isset( $_GET['page'] ) || 0 !== strpos( sanitize_text_field( wp_unslash( $_GET['page'] ) ), 'wp-easycart' ) ) { return; } // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only check of which admin screen is loading.
			$streak = (int) get_option( 'ec_option_email_fail_streak', 0 );
			if ( $streak < ec_email::FAIL_STREAK_ALERT ) { return; }
			if ( get_transient( 'ec_email_banner_snooze_' . get_current_user_id() ) ) { return; }
			$h = ec_email::health( 7 ); $ex = $h['last_error'] ? ec_email::explain( $h['last_error']->error_text ) : null;
			echo '<div class="notice notice-error ecem-banner"><span class="ecem-banner-ico">📭</span><div class="ecem-banner-body"><b>' . esc_html__( 'Store emails are failing.', 'wp-easycart' ) . '</b> ';
			echo esc_html( sprintf( __( '%1$d emails in the last 7 days could not be sent', 'wp-easycart' ), $h['failed'] + $h['queued'] + $h['parked'] ) );
			if ( $h['affected_orders'] ) { echo ' — ' . esc_html( sprintf( _n( '%d customer has not received an order email', '%d customers have not received an order email', $h['affected_orders'], 'wp-easycart' ), $h['affected_orders'] ) ); }
			echo '.';
			if ( $h['last_error'] ) { echo ' <span class="ecem-banner-err">' . esc_html__( 'Last error:', 'wp-easycart' ) . ' <code>' . esc_html( mb_strimwidth_compat( $h['last_error']->error_text, 120 ) ) . '</code></span>'; }
			echo '<div class="ecem-banner-sub">' . ( $ex ? esc_html( $ex['meaning'] ) . ' ' : '' ) . ( ec_email::queue_enabled() ? esc_html__( 'Emails are queued and will be resent automatically once delivery works again.', 'wp-easycart' ) : esc_html__( 'Automatic retry is off — turn it on in the email log so nothing is lost while you fix this.', 'wp-easycart' ) ) . '</div></div>';
			echo '<div class="ecem-banner-acts"><a class="button button-primary" href="' . esc_url( self::settings_url() ) . '">' . esc_html__( 'Fix email', 'wp-easycart' ) . '</a>' . ( $h['affected_orders'] ? '<a class="button" href="' . esc_url( self::orders_url() ) . '">' . esc_html__( 'See affected orders', 'wp-easycart' ) . '</a>' : '' ) . '<a href="#" class="ecem-banner-snooze" onclick="ecem.snooze( this ); return false;">' . esc_html__( 'Snooze 24h', 'wp-easycart' ) . '</a></div></div>';
		}
	}

	if ( ! function_exists( 'mb_strimwidth_compat' ) ) {
		function mb_strimwidth_compat( $s, $w ) { $s = (string) $s; return strlen( $s ) > $w ? substr( $s, 0, $w - 1 ) . '…' : $s; }
	}

	function wp_easycart_admin_email_health_card( $compact = false ) { wp_easycart_admin_email_health::health_card( $compact ); }
	function wp_easycart_admin_email_checks_panel() { wp_easycart_admin_email_health::checks_panel(); }
	function wp_easycart_admin_email_log_panel() { wp_easycart_admin_email_health::log_panel(); }
	/** Retry queue view + controls ( @since 6.0.0 ). */
	function wp_easycart_admin_email_queue_panel() { wp_easycart_admin_email_health::queue_panel(); }
	function wp_easycart_admin_email_order_chip( $order_id ) { return wp_easycart_admin_email_health::order_chip( $order_id ); }

	wp_easycart_admin_email_health::init();

endif;

/* ---------------------------------------------------------------------- */
/* AJAX                                                                    */
/* ---------------------------------------------------------------------- */
function ecv2_email_guard() {
	/* 6.0.1: these screens live under Settings, which is reachable with wpec_settings, so demanding
	   manage_options here let a store manager open the page and fail on every action. */
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_settings' ) ) { wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) ); }
	check_ajax_referer( wp_easycart_admin_email_health::NONCE, 'nonce' );
	if ( ! wp_easycart_admin_email_health::available() ) { wp_send_json_error( array( 'message' => __( 'Email logging is not available until the database update runs.', 'wp-easycart' ) ) ); }
}

add_action( 'wp_ajax_ecv2_email_retry', 'ecv2_email_retry' );
function ecv2_email_retry() {
	ecv2_email_guard();
	$r = ec_email::retry( isset( $_POST['queue_id'] ) ? (int) $_POST['queue_id'] : 0 );
	if ( is_wp_error( $r ) ) { wp_send_json_error( array( 'message' => $r->get_error_message() ) ); }
	$msgs = array( 'sent' => __( 'Sent.', 'wp-easycart' ), 'retried' => __( 'Still failing — it will retry again automatically.', 'wp-easycart' ), 'parked' => __( 'Still failing after the final attempt. Fix the email settings, then Retry.', 'wp-easycart' ) );
	wp_send_json_success( array( 'result' => $r, 'message' => $msgs[ $r ] ) );
}

/**
 * "Retry all failed" no longer sends inside the request ( unbounded live SMTP attempts ): the parked rows are put
 * back in the queue, up to 500 per click, and the 5-minute cron sends them 50 at a time.
 *
 * @since 6.0.0
 */
add_action( 'wp_ajax_ecv2_email_retry_all', 'ecv2_email_retry_all' );
function ecv2_email_retry_all() {
	ecv2_email_guard();
	$r = ec_email::retry_all_failed();
	$queued    = isset( $r['queued'] ) ? (int) $r['queued'] : 0;
	$remaining = isset( $r['remaining'] ) ? (int) $r['remaining'] : 0;
	if ( ! $queued ) {
		wp_send_json_success( array( 'queued' => 0, 'remaining' => $remaining, 'message' => __( 'Nothing to retry.', 'wp-easycart' ) ) );
	}
	/* translators: %d: number of emails put back in the retry queue. */
	$message = sprintf( _n( '%d email queued for retry — it sends on the next queue run.', '%d emails queued for retry — they send on the next queue runs.', $queued, 'wp-easycart' ), $queued );
	if ( $remaining ) {
		/* translators: %d: emails still marked failed after the 500-per-click limit. */
		$message .= ' ' . sprintf( _n( '%d more is still marked failed; press Retry all again.', '%d more are still marked failed; press Retry all again.', $remaining, 'wp-easycart' ), $remaining );
	}
	wp_send_json_success( array( 'queued' => $queued, 'remaining' => $remaining, 'message' => $message ) );
}

/** Regenerates the receipt from the order and sends it through the normal path ( logged, queued on failure ). */
add_action( 'wp_ajax_ecv2_email_resend_receipt', 'ecv2_email_resend_receipt' );
function ecv2_email_resend_receipt() {
	ecv2_email_guard();
	$order_id = isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : 0;
	if ( ! $order_id || ! class_exists( 'ec_db_admin' ) || ! class_exists( 'ec_orderdisplay' ) ) { wp_send_json_error( array( 'message' => __( 'Order not found.', 'wp-easycart' ) ) ); }
	$db = new ec_db_admin(); $row = $db->get_order_row_admin( $order_id );
	if ( ! $row ) { wp_send_json_error( array( 'message' => __( 'Order not found.', 'wp-easycart' ) ) ); }
	global $wpdb;
	/* Anything still queued for this order is superseded by the fresh receipt */
	$wpdb->query( $wpdb->prepare( "UPDATE ec_email_queue SET status = 'superseded' WHERE order_id = %d AND status IN ( 'pending', 'failed' ) AND email_type IN ( 'order', 'order_receipt', 'store' )", $order_id ) );
	$before = (int) $wpdb->get_var( 'SELECT MAX( log_id ) FROM ec_email_log' );
	ec_email::context( 'order_receipt', $order_id );
	$display = new ec_orderdisplay( $row, true, true );
	$display->send_email_receipt();
	ec_email::context( null );
	/* send_email_receipt() sends the customer copy first and the admin copy last, so the newest row is the admin's.
	   Report on the customer's copy: the rows written by this resend whose recipient is the order's email. */
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT to_email, status, error_text FROM ec_email_log WHERE log_id > %d AND order_id = %d ORDER BY log_id DESC', $before, $order_id ) );
	if ( ! $rows ) { wp_send_json_success( array( 'message' => __( 'Receipt sent (not logged — the mailer in use does not report results).', 'wp-easycart' ), 'status' => 'unknown' ) ); }
	$customer = strtolower( trim( (string) $row->user_email ) );
	$after    = null;
	foreach ( $rows as $log ) {
		$to = strtolower( trim( preg_match( '/<([^>]+)>\s*$/', (string) $log->to_email, $m ) ? $m[1] : (string) $log->to_email ) );
		if ( '' !== $customer && $to === $customer ) {
			/* One failed customer copy is a failure, whatever the other copies did. */
			if ( null === $after || 'sent' !== $log->status ) { $after = $log; }
			if ( 'sent' !== $log->status ) { break; }
		}
	}
	if ( ! $after ) { $after = $rows[0]; } /* the customer copy was not logged ( e.g. custom mailer ): fall back to the newest row */
	if ( 'sent' === $after->status ) { wp_send_json_success( array( 'message' => __( 'Receipt sent.', 'wp-easycart' ), 'status' => 'sent' ) ); }
	wp_send_json_success( array( 'message' => ec_email::queue_enabled() ? __( 'Still failing — queued to retry automatically.', 'wp-easycart' ) : __( 'Still failing.', 'wp-easycart' ), 'status' => $after->status, 'error' => $after->error_text ) );
}

add_action( 'wp_ajax_ecv2_email_run_checks', 'ecv2_email_run_checks' );
function ecv2_email_run_checks() {
	ecv2_email_guard();
	$checks = ec_email::checks();
	ob_start();
	wp_easycart_admin_email_health::print_check_rows( $checks );
	$html  = ob_get_clean();
	$guide = '';
	if ( class_exists( 'wp_easycart_admin_email_help' ) ) {
		ob_start();
		wp_easycart_admin_email_help::print_guide( $checks );
		$guide = ob_get_clean();
	}
	wp_send_json_success(
		array(
			'html'  => $html,
			'guide' => $guide,
			'hint'  => wp_easycart_admin_email_health::checks_hint( $checks ),
		)
	);
}

/**
 * Sends a short test email to the signed-in admin through the configured transport ( ec_email::send ), so it
 * takes the same route as order emails and lands in the log as "Test". A failed test is not queued for retry.
 *
 * @since 6.0.0
 */
add_action( 'wp_ajax_ecv2_email_send_test', 'ecv2_email_send_test' );
function ecv2_email_send_test() {
	ecv2_email_guard();
	$user = wp_get_current_user();
	$to   = ( $user && is_email( $user->user_email ) ) ? $user->user_email : get_option( 'admin_email' );
	if ( ! is_email( $to ) ) {
		wp_send_json_error( array( 'message' => __( 'Your WordPress account has no valid email address to send the test to.', 'wp-easycart' ) ) );
	}
	global $wpdb;
	$before    = (int) $wpdb->get_var( 'SELECT MAX( log_id ) FROM ec_email_log' );
	$transport = ec_email::configured_transport();
	$mailer    = ( 'wp_mail' === $transport ) ? ec_email::detect_mailer() : null;
	$route     = $mailer ? $mailer['name'] : ec_email::transport_label( $transport );
	$store     = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	/* translators: %s: store name */
	$subject = sprintf( __( 'Test email from %s', 'wp-easycart' ), $store );
	/* translators: 1: store name, 2: how the email was sent, e.g. "Post SMTP" */
	$message = '<p>' . esc_html( sprintf( __( 'This is a test from WP EasyCart on %1$s. It was sent through %2$s, the same way as your order emails.', 'wp-easycart' ), $store, $route ) ) . '</p><p>' . esc_html__( 'If it reached your inbox, store emails are working. If it landed in spam, go through the delivery checks in Settings › Email.', 'wp-easycart' ) . '</p>';
	if ( class_exists( 'wp_easycart_email_design' ) ) { /* 6.0.0: shared email design. */
		/* translators: 1: store name, 2: how the email was sent, e.g. "Post SMTP" */
		$test_line = sprintf( __( 'This is a test from WP EasyCart on %1$s. It was sent through %2$s, the same way as your order emails.', 'wp-easycart' ), $store, $route );
		$message   = wp_easycart_email_design::wrap(
			wp_easycart_email_design::get_paragraph( esc_html( $test_line ) )
			. wp_easycart_email_design::get_paragraph( esc_html__( 'If it reached your inbox, store emails are working. If it landed in spam, go through the delivery checks in Settings › Email.', 'wp-easycart' ), array( 'margin' => '0' ) ),
			array(
				'title'       => $subject,
				'heading'     => $subject,
				'preheader'   => $test_line,
				'eyebrow'     => __( 'Test email', 'wp-easycart' ),
				'button_url'  => admin_url( 'admin.php?page=wp-easycart-settings&subpage=email' ),
				'button_text' => __( 'Open email settings', 'wp-easycart' ),
			)
		);
	}

	add_filter( 'wp_easycart_email_queue_enabled', '__return_false', 99 );
	$ok = ec_email::send( $to, $subject, $message, array( 'type' => 'test', 'channel' => 'order' ) );
	remove_filter( 'wp_easycart_email_queue_enabled', '__return_false', 99 );

	$row = $wpdb->get_row( $wpdb->prepare( "SELECT status, error_text FROM ec_email_log WHERE log_id > %d AND email_type = 'test' ORDER BY log_id DESC LIMIT 1", $before ) );
	if ( $row && 'sent' !== $row->status ) {
		$ok = false;
	}
	if ( $ok ) {
		wp_send_json_success(
			array(
				'status'  => 'sent',
				/* translators: 1: recipient, 2: how it was sent */
				'message' => sprintf( __( 'Sent to %1$s through %2$s. Check the inbox and the spam folder.', 'wp-easycart' ), $to, $route ),
			)
		);
	}
	$error   = ( $row && '' !== (string) $row->error_text ) ? (string) $row->error_text : __( 'The mailer did not say why.', 'wp-easycart' );
	$explain = ec_email::explain( $error );
	wp_send_json_success(
		array(
			'status'  => 'failed',
			/* translators: 1: recipient, 2: plain-English explanation, 3: suggested fix */
			'message' => sprintf( __( 'The test to %1$s failed. %2$s %3$s.', 'wp-easycart' ), $to, $explain['meaning'], $explain['fix'] ),
			'error'   => $error,
		)
	);
}

/**
 * Retry queue controls ( @since 6.0.0 ): run it now, simulate a failure, drop a test row, refresh the panel.
 */
function ecv2_email_queue_body_html() {
	ob_start();
	wp_easycart_admin_email_health::print_queue_body();
	return ob_get_clean();
}

add_action( 'wp_ajax_ecv2_email_queue_panel', 'ecv2_email_queue_panel' );
function ecv2_email_queue_panel() {
	ecv2_email_guard();
	wp_send_json_success( array( 'html' => ecv2_email_queue_body_html() ) );
}

add_action( 'wp_ajax_ecv2_email_queue_run', 'ecv2_email_queue_run' );
function ecv2_email_queue_run() {
	ecv2_email_guard();
	$res = ec_email::run_queue();
	$sent = (int) $res['sent'];
	$retried = (int) $res['retried'];
	$parked = (int) $res['parked'];
	if ( ! $sent && ! $retried && ! $parked ) {
		$message = __( 'Nothing was due yet. Emails waiting for their next attempt stay in the queue until that time.', 'wp-easycart' );
	} else {
		/* translators: 1: sent, 2: retried, 3: gave up. */
		$message = sprintf( __( '%1$d sent, %2$d retried and still waiting, %3$d gave up.', 'wp-easycart' ), $sent, $retried, $parked );
	}
	wp_send_json_success( array( 'message' => $message, 'html' => ecv2_email_queue_body_html() ) );
}

add_action( 'wp_ajax_ecv2_email_queue_simulate', 'ecv2_email_queue_simulate' );
function ecv2_email_queue_simulate() {
	ecv2_email_guard();
	$user = wp_get_current_user();
	$to   = ( $user && is_email( $user->user_email ) ) ? $user->user_email : get_option( 'admin_email' );
	$res  = ec_email::simulate_failed_send( $to );
	if ( is_wp_error( $res ) ) {
		wp_send_json_error( array( 'message' => $res->get_error_message() ) );
	}
	$message = __( 'Test message queued and already failed once, on purpose. Press "Send queue now" to watch each retry; after the fifth attempt it is marked Failed. Nothing was sent to anyone.', 'wp-easycart' );
	wp_send_json_success( array( 'message' => $message, 'html' => ecv2_email_queue_body_html() ) );
}

add_action( 'wp_ajax_ecv2_email_queue_cancel', 'ecv2_email_queue_cancel' );
function ecv2_email_queue_cancel() {
	ecv2_email_guard();
	$queue_id = isset( $_POST['queue_id'] ) ? absint( wp_unslash( $_POST['queue_id'] ) ) : 0;
	/* Only the "simulate a failed send" test row offers Remove; a real customer email must never be dropped this way. */
	global $wpdb;
	$type = $queue_id ? $wpdb->get_var( $wpdb->prepare( 'SELECT email_type FROM ec_email_queue WHERE queue_id = %d', $queue_id ) ) : null;
	if ( null === $type ) {
		wp_send_json_error( array( 'message' => __( 'That queued email no longer exists.', 'wp-easycart' ) ) );
	}
	if ( ec_email::SIMULATE_TYPE !== $type ) {
		wp_send_json_error( array( 'message' => __( 'Only the retry-queue test message can be removed. Real emails stay queued until they send or give up.', 'wp-easycart' ) ) );
	}
	ec_email::cancel( $queue_id );
	wp_send_json_success( array( 'message' => __( 'Removed from the queue.', 'wp-easycart' ), 'html' => ecv2_email_queue_body_html() ) );
}

add_action( 'wp_ajax_ecv2_email_queue_toggle', 'ecv2_email_queue_toggle' );
function ecv2_email_queue_toggle() {
	ecv2_email_guard();
	update_option( 'ec_option_email_queue_enabled', ! empty( $_POST['on'] ) && '0' !== $_POST['on'] ? 1 : 0, false );
	wp_send_json_success( array( 'message' => ec_email::queue_enabled() ? __( 'Failed emails will be retried automatically.', 'wp-easycart' ) : __( 'Automatic retry turned off.', 'wp-easycart' ) ) );
}

add_action( 'wp_ajax_ecv2_email_dismiss_banner', 'ecv2_email_dismiss_banner' );
function ecv2_email_dismiss_banner() {
	ecv2_email_guard();
	set_transient( 'ec_email_banner_snooze_' . get_current_user_id(), 1, DAY_IN_SECONDS );
	wp_send_json_success();
}

add_action( 'wp_ajax_ecv2_email_log', 'ecv2_email_log_ajax' );
function ecv2_email_log_ajax() {
	ecv2_email_guard();
	$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
	$q      = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
	$page   = isset( $_POST['paged'] ) ? absint( wp_unslash( $_POST['paged'] ) ) : 1;
	ob_start();
	wp_easycart_admin_email_health::print_log_page( $status, $q, $page );
	$html = ob_get_clean();
	wp_send_json_success( array( 'html' => $html ) );
}
