<?php
/**
 * WP EasyCart Admin — Per Page Options ( V2 ).
 *
 * The per-page values shown in the storefront's "products per page" menu, as chips. The two settings the
 * feature depends on ( pagination on, per-page menu shown ) are surfaced here so the page explains itself.
 *
 * One value can be marked as the default ( ec_option_perpage_default ); the storefront starts shoppers on it
 * ( ec_perpage::get_default() ) and falls back to the middle value when none is set or it was removed.
 *
 * AJAX: ecv2_perpage_add, ecv2_perpage_remove, ecv2_perpage_default, ecv2_perpage_option.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_perpage_v2' ) ) :

	class wp_easycart_admin_perpage_v2 {

		const NONCE = 'wp-easycart-perpagev2';
		const MAX = 500;

		public static function values() {
			global $wpdb;
			$out = array();
			foreach ( $wpdb->get_results( 'SELECT perpage_id, perpage FROM ec_perpage ORDER BY perpage ASC' ) as $r ) { $out[] = array( 'id' => (int) $r->perpage_id, 'value' => (int) $r->perpage ); }
			$default = self::default_value( $out );
			foreach ( $out as $i => $v ) { $out[ $i ]['is_default'] = ( $v['value'] === $default ); }
			return $out;
		}

		/** The page size shoppers start on: the chosen default if it is still in the list, else the middle value ( the storefront's fallback in ec_perpage::get_default() ). */
		public static function default_value( $vals ) {
			if ( empty( $vals ) ) { return 0; }
			$chosen = (int) get_option( 'ec_option_perpage_default' );
			foreach ( $vals as $v ) { if ( $chosen > 0 && $v['value'] === $chosen ) { return $chosen; } }
			return $vals[ (int) ceil( count( $vals ) / 2 ) - 1 ]['value'];
		}

		/** 0 clears the choice ( back to the middle value ). */
		public static function set_default( $value ) {
			global $wpdb;
			$value = (int) $value;
			if ( $value > 0 && ! $wpdb->get_var( $wpdb->prepare( 'SELECT perpage_id FROM ec_perpage WHERE perpage = %d', $value ) ) ) { return new WP_Error( 'missing', sprintf( __( '%d is not in the list. Add it first.', 'wp-easycart' ), $value ) ); }
			update_option( 'ec_option_perpage_default', $value );
			return $value;
		}

		public static function add( $value ) {
			global $wpdb;
			$value = (int) $value;
			if ( $value < 1 ) { return new WP_Error( 'range', __( 'Enter a whole number of 1 or more.', 'wp-easycart' ) ); }
			if ( $value > self::MAX ) { return new WP_Error( 'range', sprintf( __( 'Values above %d make very slow pages.', 'wp-easycart' ), self::MAX ) ); }
			if ( $wpdb->get_var( $wpdb->prepare( 'SELECT perpage_id FROM ec_perpage WHERE perpage = %d', $value ) ) ) { return new WP_Error( 'dupe', sprintf( __( '%d is already in the list.', 'wp-easycart' ), $value ) ); }
			$wpdb->insert( 'ec_perpage', array( 'perpage' => $value ) );
			wp_cache_delete( 'wpeasycart-perpages' );
			do_action( 'wpeasycart_perpage_added', (int) $wpdb->insert_id );
			return (int) $wpdb->insert_id;
		}

		public static function remove( $id ) {
			global $wpdb;
			if ( (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_perpage' ) <= 1 ) { return new WP_Error( 'last', __( 'Keep at least one value or shoppers have no page size.', 'wp-easycart' ) ); }
			do_action( 'wpeasycart_perpage_deleting', (int) $id );
			$value = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT perpage FROM ec_perpage WHERE perpage_id = %d', (int) $id ) );
			$wpdb->delete( 'ec_perpage', array( 'perpage_id' => (int) $id ) );
			if ( $value && $value === (int) get_option( 'ec_option_perpage_default' ) ) { update_option( 'ec_option_perpage_default', 0 ); } // the default fell out of the list: back to the middle value
			wp_cache_delete( 'wpeasycart-perpages' );
			return true;
		}

		public function output() {
			$vals = self::values();
			$paging = (bool) get_option( 'ec_option_enable_product_paging' );
			$menu = (bool) get_option( 'ec_option_enable_product_paging_per_page' );
			$docs = wp_easycart_admin()->helpsystem->print_docs_url( 'settings', 'per-page-options', 'per-page-options' );
			$highlight = isset( $_GET['perpage_id'] ) ? (int) $_GET['perpage_id'] : 0;
			?>
			<div class="ecv2-wrap ecsl-wrap" id="ecperpage" data-nonce="<?php echo esc_attr( wp_create_nonce( self::NONCE ) ); ?>">
				<div class="ecv2-page-header">
					<div class="ecv2-page-header-left"><h1 class="ecv2-page-title"><?php esc_html_e( 'Per Page Options', 'wp-easycart' ); ?></h1><span class="ecv2-record-count" id="ecperpage_count"><?php echo esc_html( sprintf( _n( '%d value', '%d values', count( $vals ), 'wp-easycart' ), count( $vals ) ) ); ?></span></div>
					<div class="ecv2-page-header-right"><a href="<?php echo esc_url( $docs ); ?>" target="_blank" class="ecv2-help-link"><span class="dashicons dashicons-editor-help"></span> <?php esc_html_e( 'Help', 'wp-easycart' ); ?></a></div>
				</div>
				<?php if ( ! $paging ) { ?><div class="ecos-note"><span class="dashicons dashicons-warning"></span><div><b><?php esc_html_e( 'Pagination is off', 'wp-easycart' ); ?></b> — <?php esc_html_e( 'all products show on one page and these values are not used.', 'wp-easycart' ); ?> <a href="#" onclick="ecperpage.option( 'ec_option_enable_product_paging', 1, this ); return false;"><?php esc_html_e( 'Turn pagination on', 'wp-easycart' ); ?></a></div></div><?php } ?>
				<div class="ecsl-panel">
					<div class="ecsl-panel-h"><h3><?php esc_html_e( 'Values shoppers can choose', 'wp-easycart' ); ?></h3><span class="ecos-hint"><?php esc_html_e( 'Shoppers start on the default value. Click the star on another value to make it the default.', 'wp-easycart' ); ?></span></div>
					<div class="ecperpage-chips" id="ecperpage_chips">
						<?php foreach ( $vals as $v ) { ?><span class="ecperpage-chip<?php echo $v['is_default'] ? ' is-default' : ''; echo $highlight === $v['id'] ? ' is-highlight' : ''; ?>" data-id="<?php echo (int) $v['id']; ?>" data-value="<?php echo (int) $v['value']; ?>"><button type="button" class="ecperpage-star" title="<?php esc_attr_e( 'Make this the default', 'wp-easycart' ); ?>" aria-pressed="<?php echo $v['is_default'] ? 'true' : 'false'; ?>" onclick="ecperpage.set_default( <?php echo (int) $v['value']; ?>, this );">&#9733;</button><b><?php echo (int) $v['value']; ?></b><?php if ( $v['is_default'] ) { ?><span class="ecv2-chip ecv2-chip-green"><?php esc_html_e( 'default', 'wp-easycart' ); ?></span><?php } ?><button type="button" class="ecperpage-x" title="<?php esc_attr_e( 'Remove', 'wp-easycart' ); ?>" onclick="ecperpage.remove( <?php echo (int) $v['id']; ?>, this );">&times;</button></span><?php } ?>
						<span class="ecperpage-add"><input type="number" min="1" max="<?php echo (int) self::MAX; ?>" class="ecv2-input" id="ecperpage_new" placeholder="<?php esc_attr_e( 'Add…', 'wp-easycart' ); ?>" onkeydown="if ( event.key === 'Enter' ) { ecperpage.add(); return false; }"><button type="button" class="ecv2-btn ecv2-btn-sm" onclick="ecperpage.add();"><?php esc_html_e( 'Add', 'wp-easycart' ); ?></button></span>
					</div>
				</div>
				<div class="ecsl-panel">
					<div class="ecsl-panel-h"><h3><?php esc_html_e( 'How pagination behaves', 'wp-easycart' ); ?></h3><span class="ecos-hint"><?php esc_html_e( 'These live in Product settings too; they’re here because the values above depend on them.', 'wp-easycart' ); ?></span></div>
					<label class="ecos-toggle-row"><span class="ecv2-toggle"><input type="checkbox"<?php checked( $paging ); ?> onchange="ecperpage.option( 'ec_option_enable_product_paging', this.checked ? 1 : 0, this );"><span class="ecv2-toggle-slider"></span></span><span><span class="ecos-toggle-t"><?php esc_html_e( 'Split products into pages', 'wp-easycart' ); ?></span><small><?php esc_html_e( 'Off shows every product on one page.', 'wp-easycart' ); ?></small></span></label>
					<label class="ecos-toggle-row"><span class="ecv2-toggle"><input type="checkbox"<?php checked( $menu ); ?> onchange="ecperpage.option( 'ec_option_enable_product_paging_per_page', this.checked ? 1 : 0, this );"><span class="ecv2-toggle-slider"></span></span><span><span class="ecos-toggle-t"><?php esc_html_e( 'Let shoppers pick a page size', 'wp-easycart' ); ?></span><small><?php esc_html_e( 'Shows the per-page menu with the values above. Off always uses the default value.', 'wp-easycart' ); ?></small></span></label>
				</div>
				<div id="ecv2-toast-container"></div>
			</div>
			<?php
		}
	}

endif;

function ecv2_perpage_guard() {
	/* 6.0.1: these screens live under Settings, which is reachable with wpec_settings, so demanding
	   manage_options here let a store manager open the page and fail on every action. */
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_settings' ) ) { wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) ); }
	check_ajax_referer( wp_easycart_admin_perpage_v2::NONCE, 'nonce' );
}
add_action( 'wp_ajax_ecv2_perpage_add', 'ecv2_perpage_add' );
function ecv2_perpage_add() {
	ecv2_perpage_guard();
	$r = wp_easycart_admin_perpage_v2::add( isset( $_POST['value'] ) ? (int) $_POST['value'] : 0 );
	if ( is_wp_error( $r ) ) { wp_send_json_error( array( 'message' => $r->get_error_message() ) ); }
	wp_send_json_success( array( 'id' => $r, 'values' => wp_easycart_admin_perpage_v2::values() ) );
}
add_action( 'wp_ajax_ecv2_perpage_remove', 'ecv2_perpage_remove' );
function ecv2_perpage_remove() {
	ecv2_perpage_guard();
	$r = wp_easycart_admin_perpage_v2::remove( isset( $_POST['id'] ) ? (int) $_POST['id'] : 0 );
	if ( is_wp_error( $r ) ) { wp_send_json_error( array( 'message' => $r->get_error_message() ) ); }
	wp_send_json_success( array( 'values' => wp_easycart_admin_perpage_v2::values() ) );
}
add_action( 'wp_ajax_ecv2_perpage_default', 'ecv2_perpage_default' );
function ecv2_perpage_default() {
	ecv2_perpage_guard();
	$r = wp_easycart_admin_perpage_v2::set_default( isset( $_POST['value'] ) ? (int) $_POST['value'] : 0 );
	if ( is_wp_error( $r ) ) { wp_send_json_error( array( 'message' => $r->get_error_message() ) ); }
	wp_send_json_success( array( 'values' => wp_easycart_admin_perpage_v2::values(), 'message' => $r ? sprintf( __( 'Shoppers now start on %d per page.', 'wp-easycart' ), $r ) : __( 'Default cleared.', 'wp-easycart' ) ) );
}
add_action( 'wp_ajax_ecv2_perpage_option', 'ecv2_perpage_option' );
function ecv2_perpage_option() {
	ecv2_perpage_guard();
	$k = isset( $_POST['key'] ) ? sanitize_key( $_POST['key'] ) : '';
	if ( ! in_array( $k, array( 'ec_option_enable_product_paging', 'ec_option_enable_product_paging_per_page' ), true ) ) { wp_send_json_error( array( 'message' => __( 'Unknown setting.', 'wp-easycart' ) ) ); }
	update_option( $k, ! empty( $_POST['value'] ) && '0' !== $_POST['value'] ? 1 : 0 );
	wp_send_json_success( array( 'message' => __( 'Saved', 'wp-easycart' ) ) );
}
