<?php
/**
 * WP EasyCart Admin — Log Entries ( V2 ). Read-only viewer over ec_response.
 *
 * Filters: processor, errors only, last 24h / 7d / 30d. Search: see search_where() ( words across payload + source,
 * numbers also match order / entry id, #1234 = one order; shared by the CSV export ). Rows expand to the
 * full ( redacted ) payload with copy. Actions: export filtered CSV, clear older than N days, retention setting.
 * Legacy edit URLs ( response_id=N ) open the list with that entry expanded.
 *
 * AJAX: ecv2_log_payload, ecv2_log_clear, ecv2_log_retention, ecv2_log_export ( GET, streams CSV ).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

include_once( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_table_v2.php' );

if ( ! class_exists( 'wp_easycart_admin_log_table' ) ) :

	class wp_easycart_admin_log_table extends wp_easycart_admin_table_v2 {

		const NONCE = 'wp-easycart-logv2';

		/** Hidden form field carrying the search/filter signature of the page being viewed. */
		const QUERY_SIG = 'eclog_q';

		/** Longest search term honoured, and the most words matched. */
		const SEARCH_MAX_LEN   = 200;
		const SEARCH_MAX_TERMS = 8;

		public function __construct() {
			parent::__construct();
			$this->setup();
			$this->normalize_request_state();
		}

		/**
		 * Search and pagination fixes that the shared V2 table cannot make for every list.
		 *
		 * 1. The toolbar form carries the current page number, so a new search or filter used to
		 *    land on page N of the new results ( or on the last page ). The form now carries a
		 *    signature of the search + filters; when it no longer matches, paging restarts at 1.
		 * 2. The shared table rebuilds pagination links from a sanitize_text_field()'d REQUEST_URI,
		 *    which strips percent-encoded characters. A search for an email address, a URL or a
		 *    "cus_…/pi_…" style id lost its @ / : characters on page 2. Links are rebuilt from $_GET.
		 *
		 * @since 6.0.0
		 */
		private function normalize_request_state() {
			$sig = self::query_signature();
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list view; values only select which rows are shown.
			if ( isset( $_GET[ self::QUERY_SIG ] ) && sanitize_key( wp_unslash( $_GET[ self::QUERY_SIG ] ) ) !== $sig ) {
				$this->current_page = 1;
			}
			$params = array();
			foreach ( $_GET as $key => $value ) {
				if ( ! is_scalar( $value ) ) {
					continue;
				}
				$key = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $key );
				if ( '' === $key || self::QUERY_SIG === $key ) {
					continue;
				}
				if ( 's' === $key ) {
					$value = self::search_term();
				} else {
					$value = sanitize_text_field( wp_unslash( $value ) );
				}
				$params[] = array( $key, rawurlencode( $value ) );
			}
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			$params[] = array( self::QUERY_SIG, $sig );
			$this->query_params = $params;
		}

		/**
		 * The raw search term: unslashed, control characters removed, length capped. Deliberately not
		 * sanitize_text_field(), which strips tags and %xx sequences from things merchants search for
		 * ( XML element names, URL-encoded payloads ). It only reaches SQL through esc_like() + prepare().
		 *
		 * @since 6.0.0
		 */
		public static function search_term() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only search on a list view.
			if ( ! isset( $_GET['s'] ) || ! is_scalar( $_GET['s'] ) ) {
				return '';
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- cleaned below; reaches SQL only through $wpdb->esc_like() + $wpdb->prepare() and HTML only through esc_attr().
			$term = wp_check_invalid_utf8( (string) wp_unslash( $_GET['s'] ) );
			$term = trim( preg_replace( '/[\x00-\x1F\x7F]+/', ' ', $term ) );
			if ( function_exists( 'mb_substr' ) ) {
				$term = mb_substr( $term, 0, self::SEARCH_MAX_LEN );
			} else {
				$term = substr( $term, 0, self::SEARCH_MAX_LEN );
			}
			return $term;
		}

		/** Short hash of everything that changes which rows match. */
		public static function query_signature() {
			$state = array( 's' => self::search_term() );
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list view.
			foreach ( array( 'filter_0', 'filter_1', 'filter_2', 'health_filter', 'perpage' ) as $k ) {
				$state[ $k ] = isset( $_GET[ $k ] ) && is_scalar( $_GET[ $k ] ) ? sanitize_text_field( wp_unslash( $_GET[ $k ] ) ) : '';
			}
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			return substr( md5( wp_json_encode( $state ) ), 0, 12 );
		}

		/**
		 * Builds the search clause for ec_response ( list view and CSV export share it ).
		 *
		 * - Words are matched independently ( all must match, in any order, in any column ), so
		 *   "stripe declined" finds a Stripe entry whose payload says "Your card was declined".
		 *   "Quoted phrases" stay together.
		 * - Each word matches the payload text or the source ( gateway / processor name ).
		 * - A number also matches the order id and the log entry id exactly. "#1234" means
		 *   order 1234 only. A lone number of 4+ digits also searches the payload, because
		 *   webhooks often log the order id in the payload with order_id = 0.
		 * - "/" also matches the JSON-escaped "\/" form stored by json_encode().
		 * - Every value goes through $wpdb->esc_like() and $wpdb->prepare().
		 *
		 * @since 6.0.0
		 *
		 * @param string $term Raw search term ( see search_term() ).
		 * @return string SQL fragment beginning with " AND ", or '' for no search.
		 */
		public static function search_where( $term ) {
			global $wpdb;
			$term = trim( (string) $term );
			if ( '' === $term ) {
				return '';
			}

			// Single "#1234" / "order 1234": exact order id.
			if ( preg_match( '/^(?:#|order\s*#?\s*)(\d{1,11})$/i', $term, $m ) ) {
				return $wpdb->prepare( ' AND ( ec_response.order_id = %d )', (int) $m[1] );
			}

			preg_match_all( '/"([^"]+)"|(\S+)/u', $term, $matches, PREG_SET_ORDER );
			$tokens = array();
			foreach ( $matches as $match ) {
				$token = isset( $match[2] ) && '' !== $match[2] ? $match[2] : $match[1];
				$token = trim( $token );
				if ( '' !== $token && ! in_array( $token, $tokens, true ) ) {
					$tokens[] = $token;
				}
				if ( count( $tokens ) >= self::SEARCH_MAX_TERMS ) {
					break;
				}
			}
			if ( empty( $tokens ) ) {
				return '';
			}
			$single = ( 1 === count( $tokens ) );

			$clauses = array();
			foreach ( $tokens as $token ) {
				$ors     = array();
				$numeric = ltrim( $token, '#' );
				if ( preg_match( '/^\d{1,11}$/', $numeric ) ) {
					$ors[] = $wpdb->prepare( 'ec_response.order_id = %d', (int) $numeric );
					$ors[] = $wpdb->prepare( 'ec_response.response_id = %d', (int) $numeric );
					if ( $single && strlen( $numeric ) < 4 ) {
						// A short lone number is an order / entry id, not "any payload containing 12".
						$clauses[] = '( ' . implode( ' OR ', $ors ) . ' )';
						continue;
					}
					$token = $numeric;
				}
				$like  = '%' . $wpdb->esc_like( $token ) . '%';
				$ors[] = $wpdb->prepare( 'ec_response.response_text LIKE %s', $like );
				$ors[] = $wpdb->prepare( 'ec_response.processor LIKE %s', $like );
				if ( false !== strpos( $token, '/' ) ) {
					$ors[] = $wpdb->prepare( 'ec_response.response_text LIKE %s', '%' . $wpdb->esc_like( str_replace( '/', '\\/', $token ) ) . '%' );
				}
				$clauses[] = '( ' . implode( ' OR ', $ors ) . ' )';
			}
			return ' AND ( ' . implode( ' AND ', $clauses ) . ' )';
		}

		/**
		 * The shared table searches with an unescaped LIKE on every search column. Hand it the request
		 * without the search term and append the log-specific clause instead. Log filters use no
		 * HAVING / GROUP, so appending to the WHERE is safe.
		 */
		protected function get_filter() {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list view.
			$had_search = isset( $_GET['s'] );
			$raw        = $had_search ? $_GET['s'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- restored untouched below; never used here.
			unset( $_GET['s'] );
			$sql = parent::get_filter();
			if ( $had_search ) {
				$_GET['s'] = $raw;
			}
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			return $sql . self::search_where( self::search_term() );
		}

		protected function print_hidden_fields() {
			parent::print_hidden_fields();
			echo '<input type="hidden" name="' . esc_attr( self::QUERY_SIG ) . '" value="' . esc_attr( self::query_signature() ) . '" />';
		}

		protected function print_search_box() {
			$search_val = self::search_term();
			echo '<div class="ecv2-search">';
			echo '<input type="search" id="ecv2-search-input" name="s" value="' . esc_attr( $search_val ) . '" placeholder="' . esc_attr__( 'Order #, gateway, transaction id or error text', 'wp-easycart' ) . '" title="' . esc_attr__( 'Words match in any order. Use "quotes" for an exact phrase and #1234 for one order.', 'wp-easycart' ) . '" />';
			echo '<span class="ecv2-search-loading" id="ecv2-search-loading" style="display:none;"><span class="dashicons dashicons-update ecv2-spin"></span></span>';
			echo '<button type="button" class="ecv2-search-clear" id="ecv2-search-clear" ' . ( '' !== $search_val ? '' : 'style="display:none;"' ) . ' title="' . esc_attr__( 'Clear search', 'wp-easycart' ) . '"><span class="dashicons dashicons-no-alt"></span></button>';
			echo '<button type="button" class="ecv2-search-submit" id="ecv2-search-submit" title="' . esc_attr__( 'Search', 'wp-easycart' ) . '"><span class="dashicons dashicons-search"></span></button>';
			echo '</div>';
		}

		public function setup() {
			global $wpdb;
			$this->set_table( 'ec_response', 'response_id' );
			$this->set_table_id( 'ec_admin_log_list_v2' );
			$this->set_default_sort( 'response_id', 'DESC' );
			$this->set_header( __( 'Log Entries', 'wp-easycart' ) );
			$this->set_docs_link( 'settings', 'log-entries' );
			$this->set_add_new( false, '', '' );
			$this->set_label( __( 'Entry', 'wp-easycart' ), __( 'Entries', 'wp-easycart' ) );
			$this->set_view_modes( array( 'table' ) );
			$this->set_list_columns( array(
				array( 'name' => 'response_time', 'label' => __( 'When', 'wp-easycart' ), 'format' => 'log_time', 'width' => 130 ),
				array( 'name' => 'processor', 'label' => __( 'Source', 'wp-easycart' ), 'format' => 'log_processor', 'width' => 140 ),
				array( 'name' => 'order_id', 'label' => __( 'Order', 'wp-easycart' ), 'format' => 'log_order', 'width' => 90 ),
				array( 'name' => 'is_error', 'label' => __( 'Result', 'wp-easycart' ), 'format' => 'log_result', 'width' => 90 ),
				array( 'name' => 'response_text', 'label' => __( 'Response', 'wp-easycart' ), 'format' => 'log_text' ),
				array( 'name' => 'response_id', 'label' => __( 'ID', 'wp-easycart' ), 'format' => 'int', 'is_id' => true, 'laptop_hide' => true ),
			) );
			$this->set_search_columns( array( 'ec_response.response_text', 'ec_response.order_id', 'ec_response.processor' ) );
			$this->set_bulk_actions( array() );
			$this->set_row_menu_actions( array(
				array( 'label' => __( 'Copy payload', 'wp-easycart' ), 'name' => 'copy', 'icon' => 'clipboard', 'href' => '#', 'onclick' => 'return eclog.copy( this );' ),
				array( 'label' => __( 'View order', 'wp-easycart' ), 'name' => 'order', 'icon' => 'cart', 'href' => '#', 'onclick' => 'return eclog.order( this );', 'show_if' => 'order_id' ),
			) );
			/* DISTINCT processor is a full scan of ec_response: keep it for an hour ( see clear_caches() ). */
			$proc_values = get_transient( self::PROCESSORS_TRANSIENT );
			if ( ! is_array( $proc_values ) ) {
				$proc_values = (array) $wpdb->get_col( 'SELECT DISTINCT processor FROM ec_response WHERE processor IS NOT NULL AND processor != "" ORDER BY processor' );
				set_transient( self::PROCESSORS_TRANSIENT, $proc_values, HOUR_IN_SECONDS );
			}
			$procs = array();
			foreach ( $proc_values as $proc ) {
				$procs[] = (object) array( 'value' => $proc, 'label' => $proc );
			}
			$this->set_filters( array(
				array( 'data' => array( (object) array( 'value' => '1', 'label' => __( 'Errors only', 'wp-easycart' ), 'icon' => 'warning' ) ), 'label' => __( 'Result', 'wp-easycart' ), 'type' => 'pills', 'where' => 'ec_response.is_error = %d' ),
				array( 'data' => $procs, 'label' => __( 'Source', 'wp-easycart' ), 'type' => 'select', 'where' => 'ec_response.processor = %s' ),
				array( 'data' => array( (object) array( 'value' => '1', 'label' => __( 'Last 24 hours', 'wp-easycart' ), 'icon' => 'clock' ), (object) array( 'value' => '7', 'label' => __( 'Last 7 days', 'wp-easycart' ), 'icon' => 'calendar' ), (object) array( 'value' => '30', 'label' => __( 'Last 30 days', 'wp-easycart' ), 'icon' => 'calendar-alt' ) ), 'label' => __( 'When', 'wp-easycart' ), 'type' => 'pills', 'where_callback' => true ),
			) );
			$stats = self::stats();
			$this->set_health_stats( array(
				array( 'label' => __( 'Errors · 24h', 'wp-easycart' ), 'value' => $stats['err24'], 'filter_value' => 'err24', 'color' => 'red', 'group' => 'catalog' ),
				array( 'label' => __( 'Errors · 7d', 'wp-easycart' ), 'value' => $stats['err7'], 'filter_value' => 'err7', 'color' => 'amber', 'group' => 'catalog' ),
				array( 'label' => __( 'Entries', 'wp-easycart' ), 'value' => $stats['total'], 'filter_value' => '', 'color' => 'default', 'group' => 'catalog' ),
				array( 'label' => __( 'Oldest entry', 'wp-easycart' ), 'value' => $stats['oldest'] ? date_i18n( 'M j, Y', strtotime( $stats['oldest'] ) ) : '—', 'filter_value' => '', 'color' => 'gray', 'group' => 'attention' ),
			) );
		}

		/** Transient: distinct ec_response.processor values ( 1 hour ). @since 6.0.0 */
		const PROCESSORS_TRANSIENT = 'ecv2_log_processors';

		/** Transient: the four health tile aggregates over ec_response ( 5 minutes ). @since 6.0.0 */
		const STATS_TRANSIENT = 'ecv2_log_stats';

		/**
		 * Error counts, total and oldest entry — one query, cached for 5 minutes so a Logs page load
		 * no longer aggregates the whole table.
		 *
		 * @since 6.0.0
		 * @return array err24, err7, total ( int ), oldest ( mysql datetime or '' ).
		 */
		public static function stats() {
			$cached = get_transient( self::STATS_TRANSIENT );
			if ( is_array( $cached ) && isset( $cached['total'] ) ) {
				return $cached;
			}
			global $wpdb;
			$row = $wpdb->get_row( 'SELECT COUNT(*) AS total, SUM( CASE WHEN is_error = 1 AND response_time >= DATE_SUB( NOW(), INTERVAL 1 DAY ) THEN 1 ELSE 0 END ) AS err24, SUM( CASE WHEN is_error = 1 AND response_time >= DATE_SUB( NOW(), INTERVAL 7 DAY ) THEN 1 ELSE 0 END ) AS err7, MIN( response_time ) AS oldest FROM ec_response' );
			$stats = array(
				'total'  => $row ? (int) $row->total : 0,
				'err24'  => $row ? (int) $row->err24 : 0,
				'err7'   => $row ? (int) $row->err7 : 0,
				'oldest' => ( $row && $row->oldest ) ? (string) $row->oldest : '',
			);
			set_transient( self::STATS_TRANSIENT, $stats, 5 * MINUTE_IN_SECONDS );
			return $stats;
		}

		/** Drop the cached stats and processor list ( after entries are removed ). @since 6.0.0 */
		public static function clear_caches() {
			delete_transient( self::STATS_TRANSIENT );
			delete_transient( self::PROCESSORS_TRANSIENT );
		}

		protected function get_health_filter_where( $k ) {
			switch ( $k ) {
				case 'err24': return 'ec_response.is_error = 1 AND ec_response.response_time >= DATE_SUB( NOW(), INTERVAL 1 DAY )';
				case 'err7': return 'ec_response.is_error = 1 AND ec_response.response_time >= DATE_SUB( NOW(), INTERVAL 7 DAY )';
			}
			return '';
		}
		protected function get_filter_callback_where( $i, $v ) {
			if ( 2 === $i && in_array( $v, array( '1', '7', '30' ), true ) ) { return 'ec_response.response_time >= DATE_SUB( NOW(), INTERVAL ' . (int) $v . ' DAY )'; }
			return '';
		}

		/** Toolbar extras: export + retention. */
		protected function print_toolbar() {
			$days = class_exists( 'ec_logs' ) ? ec_logs::retention_days() : 90;
			$export = wp_nonce_url( add_query_arg( array( 'action' => 'ecv2_log_export' ) + $this->current_filter_args(), admin_url( 'admin-ajax.php' ) ), self::NONCE, 'nonce' );
			echo '<div class="eclog-bar" data-nonce="' . esc_attr( wp_create_nonce( self::NONCE ) ) . '"><a class="ecv2-btn ecv2-btn-sm" href="' . esc_url( $export ) . '">' . esc_html__( 'Export filtered CSV', 'wp-easycart' ) . '</a>';
			echo '<span class="eclog-sep"></span><label class="eclog-ret">' . esc_html__( 'Keep entries for', 'wp-easycart' ) . ' <select class="ecv2-select" onchange="eclog.retention( this.value );">';
			foreach ( array( 7 => __( '7 days', 'wp-easycart' ), 30 => __( '30 days', 'wp-easycart' ), 90 => __( '90 days', 'wp-easycart' ), 180 => __( '180 days', 'wp-easycart' ), 365 => __( '1 year', 'wp-easycart' ), 0 => __( 'forever', 'wp-easycart' ) ) as $d => $l ) { echo '<option value="' . (int) $d . '"' . selected( $days, $d, false ) . '>' . esc_html( $l ) . '</option>'; }
			echo '</select></label><button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" onclick="eclog.clear();">' . esc_html__( 'Clear older than…', 'wp-easycart' ) . '</button></div>';
			parent::print_toolbar();
		}

		private function current_filter_args() {
			$out = array();
			$term = self::search_term();
			if ( '' !== $term ) {
				$out['s'] = $term;
			}
			foreach ( array( 'filter_0', 'filter_1', 'filter_2', 'health_filter' ) as $k ) { if ( isset( $_GET[ $k ] ) && '' !== $_GET[ $k ] ) { $out[ $k ] = sanitize_text_field( wp_unslash( $_GET[ $k ] ) ); } }
			// add_query_arg() does not encode values; a search containing & # + or spaces would break the export link.
			return array_map( 'rawurlencode', $out );
		}

		protected function print_table_row( $result ) {
			$open = isset( $_GET['response_id'] ) && (int) $_GET['response_id'] === (int) $result->response_id;
			echo '<tr class="ecv2-row eclog-row' . ( $result->is_error ? ' is-error' : '' ) . ( $open ? ' is-open' : '' ) . '" data-id="' . esc_attr( $result->response_id ) . '" data-order-id="' . (int) $result->order_id . '">';
			echo '<td class="ecv2-col-check"><input type="checkbox" name="bulk[]" value="' . esc_attr( $result->response_id ) . '" class="ecv2-row-check" /></td>';
			foreach ( $this->list_columns as $col ) {
				if ( 'hidden' === $col['format'] ) { continue; }
				echo '<td class="ecv2-cell ecv2-cell-' . esc_attr( $col['name'] ) . ( ! empty( $col['laptop_hide'] ) ? ' ecv2-hide-laptop' : '' ) . '">'; $this->print_cell_content( $result, $col ); echo '</td>';
			}
			echo '<td class="ecv2-col-actions">'; $this->print_row_actions( $result ); echo '</td></tr>';
		}

		protected function print_cell_content( $result, $col ) {
			switch ( $col['format'] ) {
				case 'log_time':
					$ts = strtotime( $result->response_time );
					echo '<span class="ecv2-mono" title="' . esc_attr( $result->response_time ) . '">' . esc_html( $ts > current_time( 'timestamp' ) - DAY_IN_SECONDS ? date_i18n( 'H:i:s', $ts ) : date_i18n( 'M j H:i', $ts ) ) . '</span>';
					if ( $ts > current_time( 'timestamp' ) - DAY_IN_SECONDS ) { echo '<span class="ecv2-sub">' . esc_html( sprintf( __( '%s ago', 'wp-easycart' ), human_time_diff( $ts, current_time( 'timestamp' ) ) ) ) . '</span>'; }
					break;
				case 'log_processor': echo '<span class="ecv2-chip ecv2-chip-gray">' . esc_html( $result->processor ? $result->processor : __( 'unknown', 'wp-easycart' ) ) . '</span>'; break;
				case 'log_order': echo $result->order_id ? '<a class="ecv2-link-primary" href="' . esc_url( admin_url( 'admin.php?page=wp-easycart-orders&subpage=orders&ec_admin_form_action=edit&order_id=' . (int) $result->order_id ) ) . '">#' . (int) $result->order_id . '</a>' : '<span class="ecv2-sub">—</span>'; break;
				case 'log_result': echo $result->is_error ? '<span class="ecv2-chip ecv2-chip-red">' . esc_html__( 'Error', 'wp-easycart' ) . '</span>' : '<span class="ecv2-chip ecv2-chip-green">' . esc_html__( 'OK', 'wp-easycart' ) . '</span>'; break;
				case 'log_text':
					$summary = class_exists( 'ec_logs' ) ? ec_logs::summary( $result->response_text ) : wp_strip_all_tags( substr( (string) $result->response_text, 0, 110 ) );
					echo '<div class="eclog-summary">' . esc_html( $summary ) . ' <a href="#" class="eclog-expand" onclick="return eclog.toggle( this );">' . esc_html__( 'expand', 'wp-easycart' ) . '</a></div>';
					echo '<pre class="eclog-payload" style="display:none" data-loaded="0"></pre>';
					break;
				default: parent::print_cell_content( $result, $col );
			}
		}
	}

endif;

function ecv2_log_guard( $get = false ) {
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) ); }
	if ( $get ) { if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), wp_easycart_admin_log_table::NONCE ) ) { wp_die( 'Invalid nonce' ); } return; }
	check_ajax_referer( wp_easycart_admin_log_table::NONCE, 'nonce' );
}

add_action( 'wp_ajax_ecv2_log_payload', 'ecv2_log_payload' );
function ecv2_log_payload() {
	ecv2_log_guard(); global $wpdb;
	$t = $wpdb->get_var( $wpdb->prepare( 'SELECT response_text FROM ec_response WHERE response_id = %d', isset( $_POST['id'] ) ? (int) $_POST['id'] : 0 ) );
	if ( null === $t ) { wp_send_json_error( array( 'message' => __( 'Entry not found.', 'wp-easycart' ) ) ); }
	$decoded = json_decode( (string) $t );
	$pretty = ( null !== $decoded && ( is_object( $decoded ) || is_array( $decoded ) ) ) ? wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) : (string) $t;
	wp_send_json_success( array( 'payload' => ec_logs::redact( $pretty ) ) );
}

add_action( 'wp_ajax_ecv2_log_clear', 'ecv2_log_clear' );
function ecv2_log_clear() {
	ecv2_log_guard();
	$days = isset( $_POST['days'] ) ? max( 1, (int) $_POST['days'] ) : 30;
	$n = ec_logs::prune( $days );
	wp_easycart_admin_log_table::clear_caches();
	wp_send_json_success( array( 'message' => sprintf( _n( 'Removed %1$d entry older than %2$d days.', 'Removed %1$d entries older than %2$d days.', $n, 'wp-easycart' ), $n, $days ) ) );
}

add_action( 'wp_ajax_ecv2_log_retention', 'ecv2_log_retention' );
function ecv2_log_retention() {
	ecv2_log_guard();
	update_option( 'ec_option_log_retention_days', isset( $_POST['days'] ) ? max( 0, (int) $_POST['days'] ) : 90, false );
	ec_logs::maybe_schedule();
	$d = ec_logs::retention_days();
	wp_send_json_success( array( 'message' => $d ? sprintf( __( 'Entries older than %d days are removed daily.', 'wp-easycart' ), $d ) : __( 'Entries are kept forever.', 'wp-easycart' ) ) );
}

add_action( 'wp_ajax_ecv2_log_export', 'ecv2_log_export' );
function ecv2_log_export() {
	ecv2_log_guard( true ); global $wpdb;
	$where = ' WHERE 1=1';
	if ( isset( $_GET['filter_0'] ) && '1' === $_GET['filter_0'] ) { $where .= ' AND is_error = 1'; }
	if ( ! empty( $_GET['filter_1'] ) ) { $where .= $wpdb->prepare( ' AND processor = %s', sanitize_text_field( wp_unslash( $_GET['filter_1'] ) ) ); }
	if ( isset( $_GET['filter_2'] ) && in_array( $_GET['filter_2'], array( '1', '7', '30' ), true ) ) { $where .= ' AND response_time >= DATE_SUB( NOW(), INTERVAL ' . (int) $_GET['filter_2'] . ' DAY )'; }
	if ( isset( $_GET['health_filter'] ) && 'err24' === $_GET['health_filter'] ) { $where .= ' AND is_error = 1 AND response_time >= DATE_SUB( NOW(), INTERVAL 1 DAY )'; }
	if ( isset( $_GET['health_filter'] ) && 'err7' === $_GET['health_filter'] ) { $where .= ' AND is_error = 1 AND response_time >= DATE_SUB( NOW(), INTERVAL 7 DAY )'; }
	$where .= wp_easycart_admin_log_table::search_where( wp_easycart_admin_log_table::search_term() ); // Same matching as the list view; esc_like() + prepare() inside.
	if ( ! headers_sent() ) { header( 'Content-Type: text/csv; charset=utf-8' ); header( 'Content-Disposition: attachment; filename="easycart-log-' . date( 'Ymd-Hi' ) . '.csv"' ); }
	$out = fopen( 'php://output', 'w' );
	fputcsv( $out, array( 'id', 'time', 'source', 'order_id', 'is_error', 'response' ) );
	/*
	 * 6.0.0: streamed in keyset chunks of 500 ( response_id < last ) so the response_text blobs never sit in
	 * memory together; the 5000-row ceiling of the old single query is kept.
	 */
	$chunk = 500; $max_rows = 5000; $sent = 0; $last_id = PHP_INT_MAX;
	while ( $sent < $max_rows ) {
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT response_id, response_time, processor, order_id, is_error, response_text FROM ec_response $where AND response_id < %d ORDER BY response_id DESC LIMIT %d", $last_id, min( $chunk, $max_rows - $sent ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is assembled above from literals, $wpdb->prepare()'d fragments, and whitelisted/(int)-cast values only; the cursor and limit go through prepare().
		if ( empty( $rows ) ) { break; }
		foreach ( $rows as $r ) { $last_id = (int) $r['response_id']; $r['response_text'] = ec_logs::redact( $r['response_text'] ); fputcsv( $out, $r ); }
		$sent += count( $rows );
		fflush( $out );
		if ( ob_get_level() ) { ob_flush(); }
		flush();
		if ( count( $rows ) < $chunk ) { break; }
	}
	fclose( $out );
	exit;
}
