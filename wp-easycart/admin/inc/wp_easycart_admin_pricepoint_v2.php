<?php
/**
 * WP EasyCart Admin — Price Points ( V2 ).
 *
 * One screen replaces the list + details pair: every bucket is an inline row ( type, amounts ), drag to
 * reorder, live product counts, gap / overlap warnings between adjacent buckets. All rows save together.
 *
 * AJAX: ecv2_pricepoint_save ( rows[] ), ecv2_pricepoint_preview ( rows[] → counts + warnings ).
 * Legacy edit URLs ( ec_admin_form_action=edit&pricepoint_id=N ) land here with that row highlighted.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_pricepoint_v2' ) ) :

	class wp_easycart_admin_pricepoint_v2 {

		const NONCE = 'wp-easycart-ppv2';

		public static function rows() {
			global $wpdb;
			$rows = $wpdb->get_results( 'SELECT * FROM ec_pricepoint ORDER BY pricepoint_order ASC, pricepoint_id ASC' );
			$out = array();
			foreach ( $rows as $r ) {
				$out[] = array( 'id' => (int) $r->pricepoint_id, 'type' => self::type_of( $r ), 'low' => (float) $r->low_point, 'high' => (float) $r->high_point, 'order' => (int) $r->pricepoint_order );
			}
			return $out;
		}

		public static function type_of( $r ) {
			if ( (int) $r->is_less_than ) { return 'under'; }
			if ( (int) $r->is_greater_than ) { return 'over'; }
			return 'between';
		}

		/** Storefront semantics: under → price <= high; over → price >= low; between → low <= price <= high. */
		public static function where_for( $row ) {
			global $wpdb;
			switch ( $row['type'] ) {
				case 'under': return $wpdb->prepare( 'price <= %f', $row['high'] );
				case 'over':  return $wpdb->prepare( 'price >= %f', $row['low'] );
			}
			return $wpdb->prepare( 'price >= %f AND price <= %f', $row['low'], $row['high'] );
		}

		/**
		 * Counts + label + warnings for a set of rows ( saved or unsaved ).
		 *
		 * One pass over ec_product ( since 6.0.0 ): the total, every bucket count, every
		 * gap count and the uncovered count are SUM( CASE ) columns of a single SELECT,
		 * so a 100k catalog costs one range scan on ( activate_in_store, price ) instead
		 * of 2 + buckets + gaps COUNT(*) queries on render, on every preview and on save.
		 * Returned structure is unchanged ( settings-lists-v2.js ecpp reads it ).
		 */
		public static function analyze( $rows ) {
			global $wpdb;
			$rows = array_values( $rows );
			$where = array(); $out = array();
			foreach ( $rows as $i => $r ) {
				$where[ $i ] = self::where_for( $r );
				$out[ $i ] = array( 'count' => 0, 'label' => self::label( $r ), 'warnings' => array() );
				if ( 'between' === $r['type'] && $r['high'] < $r['low'] ) { $out[ $i ]['warnings'][] = __( 'High is below low — this bucket can never match.', 'wp-easycart' ); }
			}
			/* Adjacent-range checks on the numeric span each row covers */
			$spans = array();
			foreach ( $rows as $i => $r ) {
				$spans[ $i ] = array( 'under' === $r['type'] ? -INF : (float) $r['low'], 'over' === $r['type'] ? INF : (float) $r['high'] );
			}
			$gaps = array(); $gap_where = array();
			for ( $i = 0; $i < count( $rows ) - 1; $i++ ) {
				list( , $hi ) = $spans[ $i ]; list( $lo2, ) = $spans[ $i + 1 ];
				if ( is_infinite( $hi ) || is_infinite( $lo2 ) ) { continue; }
				/* 14.99 -> 15.00 is contiguous for two-decimal prices; only a real gap ( > one cent ) is worth a warning. */
				if ( round( $lo2 - $hi, 2 ) > 0.01 ) {
					$gap_where[ count( $gaps ) ] = $wpdb->prepare( 'price > %f AND price < %f', $hi, $lo2 );
					$gaps[] = array( 'after' => $i, 'kind' => 'gap', 'from' => $hi, 'to' => $lo2, 'products' => 0, 'message' => '' );
				} else if ( $lo2 < $hi ) {
					$gaps[] = array( 'after' => $i, 'kind' => 'overlap', 'from' => $lo2, 'to' => $hi, 'products' => 0, 'message' => sprintf( __( '%1$s – %2$s is covered by both buckets; products there show twice.', 'wp-easycart' ), self::money( $lo2 ), self::money( $hi ) ) );
				}
			}
			/* The single pass. Every fragment below came out of $wpdb->prepare() ( where_for() / the gap prepare above ). */
			$select = array( 'COUNT(*) AS total' );
			foreach ( $where as $i => $w ) { $select[] = 'SUM( CASE WHEN ' . $w . ' THEN 1 ELSE 0 END ) AS b' . (int) $i; }
			foreach ( $gap_where as $g => $w ) { $select[] = 'SUM( CASE WHEN ' . $w . ' THEN 1 ELSE 0 END ) AS g' . (int) $g; }
			if ( $where ) { $select[] = 'SUM( CASE WHEN NOT ( ( ' . implode( ' ) OR ( ', $where ) . ' ) ) THEN 1 ELSE 0 END ) AS uncovered'; }
			$stats = $wpdb->get_row( 'SELECT ' . implode( ', ', $select ) . ' FROM ec_product WHERE activate_in_store = 1', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $select holds only $wpdb->prepare()'d fragments and integer aliases.
			if ( ! is_array( $stats ) ) { $stats = array(); }
			$total = isset( $stats['total'] ) ? (int) $stats['total'] : 0;
			foreach ( $where as $i => $w ) {
				$n = isset( $stats[ 'b' . $i ] ) ? (int) $stats[ 'b' . $i ] : 0;
				$out[ $i ]['count'] = $n;
				if ( 0 === $n ) { $out[ $i ]['warnings'][] = __( 'No active products fall in this bucket.', 'wp-easycart' ); }
			}
			foreach ( $gap_where as $g => $w ) {
				$missing = isset( $stats[ 'g' . $g ] ) ? (int) $stats[ 'g' . $g ] : 0;
				$gaps[ $g ]['products'] = $missing;
				$gaps[ $g ]['message'] = sprintf( __( 'Nothing between %1$s and %2$s — %3$d products won’t appear in any price filter.', 'wp-easycart' ), self::money( $gaps[ $g ]['from'] ), self::money( $gaps[ $g ]['to'] ), $missing );
			}
			$unders = 0; $overs = 0;
			foreach ( $rows as $r ) { if ( 'under' === $r['type'] ) { $unders++; } if ( 'over' === $r['type'] ) { $overs++; } }
			$notes = array();
			if ( $unders > 1 ) { $notes[] = __( 'More than one “Under” bucket — the storefront expects at most one.', 'wp-easycart' ); }
			if ( $overs > 1 ) { $notes[] = __( 'More than one “Over” bucket — the storefront expects at most one.', 'wp-easycart' ); }
			$uncovered = $where ? ( isset( $stats['uncovered'] ) ? (int) $stats['uncovered'] : 0 ) : $total;
			return array( 'rows' => $out, 'gaps' => $gaps, 'notes' => $notes, 'total' => $total, 'uncovered' => $uncovered, 'empty' => count( array_filter( $out, function( $o ) { return 0 === $o['count']; } ) ) );
		}

		public static function money( $v ) { return isset( $GLOBALS['currency'] ) && is_object( $GLOBALS['currency'] ) ? $GLOBALS['currency']->get_currency_display( $v ) : number_format( (float) $v, 2 ); }

		public static function label( $r ) {
			switch ( $r['type'] ) {
				case 'under': return sprintf( __( 'Under %s', 'wp-easycart' ), self::money( $r['high'] ) );
				case 'over':  return sprintf( __( 'Over %s', 'wp-easycart' ), self::money( $r['low'] ) );
			}
			return self::money( $r['low'] ) . ' – ' . self::money( $r['high'] );
		}

		public static function sanitize_rows( $raw ) {
			$out = array();
			if ( ! is_array( $raw ) ) { return $out; }
			foreach ( array_slice( $raw, 0, 50 ) as $r ) {
				if ( ! is_array( $r ) ) { continue; }
				$type = isset( $r['type'] ) && in_array( $r['type'], array( 'under', 'between', 'over' ), true ) ? $r['type'] : 'between';
				$low = isset( $r['low'] ) ? max( 0, (float) str_replace( ',', '', (string) $r['low'] ) ) : 0;
				$high = isset( $r['high'] ) ? max( 0, (float) str_replace( ',', '', (string) $r['high'] ) ) : 0;
				if ( 'under' === $type ) { $low = 0; }
				if ( 'over' === $type ) { $high = 0; }
				$out[] = array( 'id' => isset( $r['id'] ) ? (int) $r['id'] : 0, 'type' => $type, 'low' => $low, 'high' => $high );
			}
			return $out;
		}

		/** Replace the table contents with $rows, preserving ids where given. Returns id map. */
		public static function save( $rows ) {
			global $wpdb;
			$existing = array_map( 'intval', $wpdb->get_col( 'SELECT pricepoint_id FROM ec_pricepoint' ) );
			$keep = array(); $order = 0;
			foreach ( $rows as $r ) {
				$data = array( 'is_less_than' => 'under' === $r['type'] ? 1 : 0, 'is_greater_than' => 'over' === $r['type'] ? 1 : 0, 'low_point' => $r['low'], 'high_point' => $r['high'], 'pricepoint_order' => $order++ );
				if ( $r['id'] && in_array( $r['id'], $existing, true ) ) { $wpdb->update( 'ec_pricepoint', $data, array( 'pricepoint_id' => $r['id'] ) ); $keep[] = $r['id']; }
				else { $wpdb->insert( 'ec_pricepoint', $data ); $keep[] = (int) $wpdb->insert_id; }
			}
			foreach ( array_diff( $existing, $keep ) as $gone ) { $wpdb->delete( 'ec_pricepoint', array( 'pricepoint_id' => (int) $gone ) ); }
			wp_cache_delete( 'wpeasycart-pricepoints' );
			do_action( 'wpeasycart_pricepoints_updated' );
			return $keep;
		}

		public function output() {
			$rows = self::rows();
			$a = self::analyze( $rows );
			$highlight = isset( $_GET['pricepoint_id'] ) ? (int) $_GET['pricepoint_id'] : 0;
			$docs = wp_easycart_admin()->helpsystem->print_docs_url( 'settings', 'price-points', 'price-points' );
			?>
			<div class="ecv2-wrap ecsl-wrap" id="ecpp" data-nonce="<?php echo esc_attr( wp_create_nonce( self::NONCE ) ); ?>" data-highlight="<?php echo (int) $highlight; ?>">
				<script type="application/json" id="ecpp_data"><?php echo wp_json_encode( array( 'rows' => $rows, 'analysis' => $a, 'currency' => array( 'symbol' => self::money( 0 ) ) ) ); ?></script>
				<div class="ecv2-page-header">
					<div class="ecv2-page-header-left"><h1 class="ecv2-page-title"><?php esc_html_e( 'Price Points', 'wp-easycart' ); ?></h1><span class="ecv2-record-count" id="ecpp_count"><?php echo esc_html( sprintf( _n( '%d bucket', '%d buckets', count( $rows ), 'wp-easycart' ), count( $rows ) ) ); ?></span></div>
					<div class="ecv2-page-header-right"><a href="<?php echo esc_url( $docs ); ?>" target="_blank" class="ecv2-help-link"><span class="dashicons dashicons-editor-help"></span> <?php esc_html_e( 'Help', 'wp-easycart' ); ?></a><button type="button" class="ecv2-btn ecv2-btn-primary" id="ecpp_save" onclick="ecpp.save();"><?php esc_html_e( 'Save', 'wp-easycart' ); ?></button></div>
				</div>
				<div class="ecv2-stat-cards ecsl-cards" id="ecpp_cards"></div>
				<div class="ecsl-panel">
					<div class="ecsl-panel-h"><h3><?php esc_html_e( 'Buckets', 'wp-easycart' ); ?></h3><span class="ecos-hint"><?php esc_html_e( 'Shoppers filter by these ranges in the price widget. Drag to set the order they appear in; amounts save when you click Save.', 'wp-easycart' ); ?></span></div>
					<div class="ecpp-head"><span></span><span><?php esc_html_e( 'Type', 'wp-easycart' ); ?></span><span><?php esc_html_e( 'Range', 'wp-easycart' ); ?></span><span><?php esc_html_e( 'Shown as', 'wp-easycart' ); ?></span><span><?php esc_html_e( 'Products', 'wp-easycart' ); ?></span><span></span></div>
					<div id="ecpp_rows"></div>
					<div class="ecpp-foot"><button type="button" class="ecv2-btn ecv2-btn-sm" onclick="ecpp.add();">+ <?php esc_html_e( 'Add bucket', 'wp-easycart' ); ?></button><button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" onclick="ecpp.suggest();"><?php esc_html_e( 'Suggest buckets from my prices', 'wp-easycart' ); ?></button><span class="ecos-hint" id="ecpp_notes"></span></div>
				</div>
				<div class="ecos-note ecos-note-info"><span class="dashicons dashicons-info-outline"></span><div><?php esc_html_e( 'Use one “Under” bucket, one “Over” bucket and “Between” buckets that meet edge to edge — the storefront filter expects the ranges to cover every price exactly once.', 'wp-easycart' ); ?></div></div>
				<div id="ecv2-toast-container"></div>
			</div>
			<?php
		}

		/**
		 * Even-ish quantile suggestion from active product prices.
		 * Since 6.0.0 each cut is one "ORDER BY price LIMIT 1 OFFSET n" ( k = buckets - 1
		 * small queries ) instead of loading every active price into PHP; same cuts.
		 */
		public static function suggest( $buckets = 4 ) {
			global $wpdb;
			$n = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_product WHERE activate_in_store = 1 AND price > 0' );
			if ( $n < 2 ) { return array(); }
			$rows = array(); $cuts = array();
			for ( $i = 1; $i < $buckets; $i++ ) {
				$price = $wpdb->get_var( $wpdb->prepare( 'SELECT price FROM ec_product WHERE activate_in_store = 1 AND price > 0 ORDER BY price ASC LIMIT 1 OFFSET %d', (int) floor( $n * $i / $buckets ) ) );
				$cuts[] = self::round_nice( (float) $price );
			}
			$cuts = array_values( array_unique( $cuts ) );
			$rows[] = array( 'id' => 0, 'type' => 'under', 'low' => 0, 'high' => $cuts[0] );
			for ( $i = 0; $i < count( $cuts ) - 1; $i++ ) { $rows[] = array( 'id' => 0, 'type' => 'between', 'low' => $cuts[ $i ], 'high' => $cuts[ $i + 1 ] ); }
			$rows[] = array( 'id' => 0, 'type' => 'over', 'low' => $cuts[ count( $cuts ) - 1 ], 'high' => 0 );
			return $rows;
		}
		private static function round_nice( $v ) { if ( $v <= 0 ) { return 0; } $mag = pow( 10, floor( log10( $v ) ) ); $step = $mag >= 100 ? 50 : ( $mag >= 10 ? 5 : 1 ); return max( $step, round( $v / $step ) * $step ); }
	}

endif;

function ecv2_pp_guard() {
	/* 6.0.1: these screens live under Settings, which is reachable with wpec_settings, so demanding
	   manage_options here let a store manager open the page and fail on every action. */
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_settings' ) ) { wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) ); }
	check_ajax_referer( wp_easycart_admin_pricepoint_v2::NONCE, 'nonce' );
}
add_action( 'wp_ajax_ecv2_pricepoint_preview', 'ecv2_pricepoint_preview' );
function ecv2_pricepoint_preview() {
	ecv2_pp_guard();
	$rows = wp_easycart_admin_pricepoint_v2::sanitize_rows( json_decode( wp_unslash( isset( $_POST['rows'] ) ? $_POST['rows'] : '[]' ), true ) );
	wp_send_json_success( array( 'analysis' => wp_easycart_admin_pricepoint_v2::analyze( $rows ) ) );
}
add_action( 'wp_ajax_ecv2_pricepoint_save', 'ecv2_pricepoint_save' );
function ecv2_pricepoint_save() {
	ecv2_pp_guard();
	$rows = wp_easycart_admin_pricepoint_v2::sanitize_rows( json_decode( wp_unslash( isset( $_POST['rows'] ) ? $_POST['rows'] : '[]' ), true ) );
	foreach ( $rows as $r ) { if ( 'between' === $r['type'] && $r['high'] < $r['low'] ) { wp_send_json_error( array( 'message' => __( 'A “Between” bucket has its high amount below its low amount.', 'wp-easycart' ) ) ); } }
	$ids = wp_easycart_admin_pricepoint_v2::save( $rows );
	$saved = wp_easycart_admin_pricepoint_v2::rows();
	wp_send_json_success( array( 'message' => __( 'Saved', 'wp-easycart' ), 'rows' => $saved, 'analysis' => wp_easycart_admin_pricepoint_v2::analyze( $saved ) ) );
}
add_action( 'wp_ajax_ecv2_pricepoint_suggest', 'ecv2_pricepoint_suggest' );
function ecv2_pricepoint_suggest() {
	ecv2_pp_guard();
	$rows = wp_easycart_admin_pricepoint_v2::suggest( 4 );
	if ( empty( $rows ) ) { wp_send_json_error( array( 'message' => __( 'Not enough priced products to suggest ranges.', 'wp-easycart' ) ) ); }
	wp_send_json_success( array( 'rows' => $rows, 'analysis' => wp_easycart_admin_pricepoint_v2::analyze( $rows ) ) );
}
