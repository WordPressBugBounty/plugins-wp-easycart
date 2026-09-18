<?php
/**
 * WP EasyCart Admin — Manufacturers ( V2 ): list, editor and AJAX in one file.
 *
 * List: usage counts, clicks, slug, health cards, inline name edit ( spreadsheet ).
 * Editor: details, products ( read-only list + "Assign products" picker ), SEO & URL, danger zone.
 * AJAX: ecv2_manufacturer_create, ecv2_manufacturer_inline_update, ecv2_manufacturer_bulk,
 *       ecv2_manufacturer_save, ecv2_manufacturer_products, ecv2_manufacturer_assign_search, ecv2_manufacturer_assign.
 *
 * @since 5.x.x
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* The base list class normally arrives later in admin-init.php; load it here so this
   file is safe to include from a constructor regardless of order. The base is wrapped
   in class_exists(), so the later plain include() in admin-init is harmless. */
include_once( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_table_v2.php' );

if ( ! class_exists( 'wp_easycart_admin_manufacturer_table' ) ) :

	class wp_easycart_admin_manufacturer_table extends wp_easycart_admin_table_v2 {

		public static function editor_url( $id ) {
			$id = ( '{id}' === $id ) ? '{id}' : (int) $id;
			return admin_url( 'admin.php?page=wp-easycart-products&subpage=manufacturers&ec_admin_form_action=edit&manufacturer_id=' . $id );
		}

		public function __construct() { parent::__construct(); $this->setup(); }

		public function setup() {
			global $wpdb;
			$this->set_table( 'ec_manufacturer', 'manufacturer_id' );
			$this->set_table_id( 'ec_admin_manufacturer_list_v2' );
			$this->set_default_sort( 'name', 'ASC' );
			$this->set_header( __( 'Manufacturers', 'wp-easycart' ) );
			$this->set_docs_link( 'products', 'manufacturers' );
			$this->set_add_new( true, 'add-new', __( 'Add manufacturer', 'wp-easycart' ) );
			$this->set_add_new_js( 'ecv2_catalog.new_manufacturer( this ); return false;' );
			$this->set_add_new_css( 'ecv2-btn ecv2-btn-primary' );
			$this->set_label( __( 'Manufacturer', 'wp-easycart' ), __( 'Manufacturers', 'wp-easycart' ) );
			$this->set_view_modes( array( 'table', 'spreadsheet' ) );

			$this->set_list_columns( array(
				array( 'name' => 'name', 'label' => __( 'Manufacturer', 'wp-easycart' ), 'format' => 'mf_name', 'linked' => true ),
				array( 'select' => '( SELECT COUNT(*) FROM ec_product p WHERE p.manufacturer_id = ec_manufacturer.manufacturer_id ) AS product_count', 'name' => 'product_count', 'label' => __( 'Products', 'wp-easycart' ), 'format' => 'mf_products' ),
				array( 'select' => '( SELECT COUNT(*) FROM ec_product p2 WHERE p2.manufacturer_id = ec_manufacturer.manufacturer_id AND p2.activate_in_store = 1 ) AS active_count', 'name' => 'active_count', 'format' => 'hidden', 'label' => '' ),
				array( 'name' => 'clicks', 'label' => __( 'Clicks', 'wp-easycart' ), 'format' => 'int', 'tablet_hide' => true ),
				array( 'name' => 'manufacturer_id', 'label' => __( 'ID', 'wp-easycart' ), 'format' => 'int', 'is_id' => true, 'laptop_hide' => true ),
				array( 'name' => 'post_id', 'format' => 'hidden', 'label' => '' ),
				array( 'select' => "( SELECT p.post_name FROM {$wpdb->posts} p WHERE p.ID = ec_manufacturer.post_id ) AS slug", 'name' => 'slug', 'format' => 'hidden', 'label' => '' ),
			) );
			$this->set_search_columns( array( 'ec_manufacturer.name' ) );
			$this->set_bulk_actions( array( array( 'name' => 'ecv2-manufacturer-delete', 'label' => __( 'Delete', 'wp-easycart' ) ) ) );
			$this->set_row_menu_actions( array(
				array( 'label' => __( 'Edit manufacturer', 'wp-easycart' ), 'name' => 'edit', 'icon' => 'edit', 'href' => self::editor_url( '{id}' ) ),
				array( 'label' => __( 'View products', 'wp-easycart' ), 'name' => 'products', 'icon' => 'products', 'href' => admin_url( 'admin.php?page=wp-easycart-products&subpage=products&filter_3={id}' ) ),
				array( 'label' => __( 'Assign products…', 'wp-easycart' ), 'name' => 'assign', 'icon' => 'plus-alt2', 'href' => '#', 'onclick' => 'ecv2_catalog.assign_manufacturer( {id} ); return false;' ),
				array( 'label' => __( 'View on site', 'wp-easycart' ), 'name' => 'view', 'icon' => 'visibility', 'href' => '#', 'target' => '_blank', 'onclick' => 'return ecv2_catalog.view_row_slug( this );' ),
				array( 'label' => __( 'Delete', 'wp-easycart' ), 'name' => 'delete', 'icon' => 'trash', 'href' => '#', 'onclick' => 'ecv2_catalog.safe_delete( \'manufacturer\', {id} ); return false;', 'danger' => true ),
			) );
			$this->set_spreadsheet_columns( array(
				array( 'name' => 'name', 'label' => __( 'Name', 'wp-easycart' ), 'format' => 'string', 'ss_editable' => true ),
				array( 'name' => 'product_count', 'label' => __( 'Products', 'wp-easycart' ), 'format' => 'int' ),
				array( 'name' => 'clicks', 'label' => __( 'Clicks', 'wp-easycart' ), 'format' => 'int' ),
				array( 'name' => 'manufacturer_id', 'label' => __( 'ID', 'wp-easycart' ), 'format' => 'int', 'is_id' => true ),
			) );
			$this->set_filters( array(
				array( 'data' => array( (object) array( 'value' => 'used', 'label' => __( 'In use', 'wp-easycart' ), 'icon' => 'yes-alt' ), (object) array( 'value' => 'unused', 'label' => __( 'Unused', 'wp-easycart' ), 'icon' => 'marker' ) ), 'label' => __( 'Usage', 'wp-easycart' ), 'type' => 'pills', 'where_callback' => true ),
			) );

			$total = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_manufacturer' );
			$used  = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_manufacturer WHERE ' . $this->used_sql() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- used_sql() returns a static SQL fragment.
			$nopage = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_manufacturer WHERE post_id = 0 OR NOT EXISTS ( SELECT 1 FROM ' . $wpdb->posts . ' p WHERE p.ID = ec_manufacturer.post_id )' );
			$this->set_health_stats( array(
				array( 'label' => __( 'All', 'wp-easycart' ), 'value' => $total, 'filter_value' => '', 'color' => 'default', 'group' => 'catalog' ),
				array( 'label' => __( 'In use', 'wp-easycart' ), 'value' => $used, 'filter_value' => 'used', 'color' => 'green', 'group' => 'catalog' ),
				array( 'label' => __( 'Unused', 'wp-easycart' ), 'value' => $total - $used, 'filter_value' => 'unused', 'color' => 'gray', 'group' => 'attention' ),
				array( 'label' => __( 'No page', 'wp-easycart' ), 'value' => $nopage, 'filter_value' => 'nopage', 'color' => 'amber', 'group' => 'attention' ),
				array( 'label' => __( 'Unassigned products', 'wp-easycart' ), 'value' => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_product WHERE manufacturer_id = 0 OR manufacturer_id NOT IN ( SELECT manufacturer_id FROM ec_manufacturer )' ), 'filter_value' => '', 'color' => 'amber', 'group' => 'attention' ),
			) );
		}

		private function used_sql() { return 'EXISTS ( SELECT 1 FROM ec_product p WHERE p.manufacturer_id = ec_manufacturer.manufacturer_id )'; }

		protected function get_health_filter_where( $k ) {
			global $wpdb;
			switch ( $k ) {
				case 'used': return $this->used_sql();
				case 'unused': return 'NOT ' . $this->used_sql();
				case 'nopage': return 'ec_manufacturer.post_id = 0 OR NOT EXISTS ( SELECT 1 FROM ' . $wpdb->posts . ' p WHERE p.ID = ec_manufacturer.post_id )';
			}
			return '';
		}
		protected function get_filter_callback_where( $i, $v ) { return 0 === $i ? $this->get_health_filter_where( $v ) : ''; }

		protected function print_table_row( $result ) {
			echo '<tr class="ecv2-row' . ( 0 == $result->product_count ? ' ecv2-row-inactive' : '' ) . '" data-id="' . esc_attr( $result->manufacturer_id ) . '" data-slug="' . esc_attr( (string) $result->slug ) . '">';
			echo '<td class="ecv2-col-check"><input type="checkbox" name="bulk[]" value="' . esc_attr( $result->manufacturer_id ) . '" class="ecv2-row-check" /></td>';
			foreach ( $this->list_columns as $col ) {
				if ( 'hidden' === $col['format'] ) { continue; }
				$extra = ( ! empty( $col['tablet_hide'] ) ? ' ecv2-hide-tablet' : '' ) . ( ! empty( $col['laptop_hide'] ) ? ' ecv2-hide-laptop' : '' );
				echo '<td class="ecv2-cell ecv2-cell-' . esc_attr( $col['name'] ) . $extra . '">'; $this->print_cell_content( $result, $col ); echo '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $extra only ever holds literal class names set above.
			}
			echo '<td class="ecv2-col-actions">'; $this->print_row_actions( $result ); echo '</td></tr>';
		}

		protected function print_cell_content( $result, $col ) {
			switch ( $col['format'] ) {
				case 'mf_name':
					echo '<a href="' . esc_url( self::editor_url( $result->manufacturer_id ) ) . '" class="ecv2-link-primary ecv2-title-link">' . esc_html( wp_unslash( $result->name ) ) . '</a>';
					$subs = array();
					if ( $result->slug ) { $subs[] = '<span class="ecv2-mono">/' . esc_html( $result->slug ) . '/</span>'; } else { $subs[] = '<span class="ecv2-sub-warn">' . esc_html__( 'No page', 'wp-easycart' ) . '</span>'; }
					if ( $result->product_count && $result->active_count < $result->product_count ) { $subs[] = esc_html( sprintf( __( '%d inactive products', 'wp-easycart' ), $result->product_count - $result->active_count ) ); }
					echo '<span class="ecv2-sub">' . implode( ' · ', $subs ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every $subs entry is built from esc_html()/esc_html__() and literal markup above.
					break;
				case 'mf_products':
					$n = (int) $result->product_count;
					echo $n ? '<a class="ecv2-usage" href="' . esc_url( admin_url( 'admin.php?page=wp-easycart-products&subpage=products&filter_3=' . (int) $result->manufacturer_id ) ) . '">' . esc_html( sprintf( _n( '%d product', '%d products', $n, 'wp-easycart' ), $n ) ) . '</a>' : '<span class="ecv2-usage ecv2-usage-zero">' . esc_html__( 'Not used', 'wp-easycart' ) . '</span> <a class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" href="#" onclick="ecv2_catalog.assign_manufacturer( ' . (int) $result->manufacturer_id . ' ); return false;">' . esc_html__( 'Assign', 'wp-easycart' ) . '</a>';
					break;
				default: parent::print_cell_content( $result, $col );
			}
		}
		protected function print_spreadsheet_cell( $result, $col ) { $this->print_cell_content( $result, $col ); }
	}

	class wp_easycart_admin_manufacturer_editor_v2 {
		const NONCE = 'wp-easycart-mfv2';
		public $m; public $post; public $products = array(); public $total = 0; public $inactive = 0; public $docs_link = ''; public $yoast = false;
		public function __construct() { $this->docs_link = wp_easycart_admin()->helpsystem->print_docs_url( 'products', 'manufacturers', 'manufacturers' ); $this->yoast = function_exists( 'is_plugin_active' ) && ( is_plugin_active( 'wordpress-seo/wp-seo.php' ) || is_plugin_active( 'wordpress-seo-premium/wp-seo-premium.php' ) ); }
		public function load( $id ) {
			global $wpdb;
			$this->m = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_manufacturer WHERE manufacturer_id = %d', $id ) );
			if ( ! $this->m ) { return false; }
			$this->post = $this->m->post_id ? get_post( (int) $this->m->post_id ) : null;
			$this->total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_product WHERE manufacturer_id = %d', $id ) );
			$this->inactive = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_product WHERE manufacturer_id = %d AND activate_in_store = 0', $id ) );
			$this->products = self::product_rows( $id, 30 );
			return true;
		}
		public static function product_rows( $id, $limit, $offset = 0 ) {
			global $wpdb; $out = array();
			foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT product_id, title, model_number, activate_in_store, ' . wp_easycart_admin_catalog_v2_thumb_select( 'ec_product' ) . ' FROM ec_product WHERE manufacturer_id = %d ORDER BY title LIMIT %d OFFSET %d', $id, $limit, $offset ) ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- wp_easycart_admin_catalog_v2_thumb_select() returns static column SQL for a literal alias.
				$out[] = array( 'id' => (int) $r->product_id, 'title' => wp_unslash( $r->title ), 'sku' => $r->model_number, 'image' => wp_easycart_admin_catalog_v2_product_thumb( $r ), 'active' => (bool) $r->activate_in_store, 'edit_url' => admin_url( 'admin.php?page=wp-easycart-products&subpage=products&ec_admin_form_action=edit&product_id=' . (int) $r->product_id ) );
			}
			return $out;
		}
		public function permalink() { return $this->post ? get_permalink( $this->post ) : ''; }
		public function slug_prefix() { $link = $this->permalink(); if ( ! $link ) { return '/store/'; } $path = rtrim( (string) wp_parse_url( $link, PHP_URL_PATH ), '/' ); $pos = strrpos( $path, '/' ); return false === $pos ? '/' : substr( $path, 0, $pos + 1 ); }
		public function health() {
			return array(
				array( 'ok' => $this->total > 0, 'label' => $this->total ? sprintf( _n( '%d product', '%d products', $this->total, 'wp-easycart' ), $this->total ) : __( 'No products assigned', 'wp-easycart' ) ),
				array( 'ok' => 0 === $this->inactive, 'label' => $this->inactive ? sprintf( _n( '%d product is inactive', '%d products are inactive', $this->inactive, 'wp-easycart' ), $this->inactive ) : __( 'All products active', 'wp-easycart' ) ),
				array( 'ok' => $this->post && '' !== trim( (string) $this->post->post_excerpt ), 'label' => $this->post && '' !== trim( (string) $this->post->post_excerpt ) ? __( 'Has a search excerpt', 'wp-easycart' ) : __( 'No search excerpt (helps SEO)', 'wp-easycart' ) ),
				array( 'ok' => (bool) $this->post, 'label' => $this->post ? __( 'Manufacturer page linked', 'wp-easycart' ) : __( 'Page missing — save to recreate', 'wp-easycart' ) ),
			);
		}
		public function output() {
			if ( ! $this->load( isset( $_GET['manufacturer_id'] ) ? (int) $_GET['manufacturer_id'] : 0 ) ) {
				echo '<div class="ecv2-wrap"><div class="ecv2-empty-state">' . esc_html__( 'That manufacturer no longer exists.', 'wp-easycart' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=wp-easycart-products&subpage=manufacturers' ) ) . '">' . esc_html__( 'Back to manufacturers', 'wp-easycart' ) . '</a></div></div>'; return;
			}
			include( EC_PLUGIN_DIRECTORY . '/admin/template/products/manufacturers/manufacturer-editor-v2.php' );
		}
		public function js_data() {
			return array( 'kind' => 'manufacturer', 'id' => (int) $this->m->manufacturer_id, 'delete_type' => 'manufacturer', 'slug' => $this->post ? $this->post->post_name : '', 'redirects' => class_exists( 'ec_url_redirects' ), 'nonce' => wp_create_nonce( self::NONCE ), 'save_action' => 'ecv2_manufacturer_save', 'more_action' => 'ecv2_manufacturer_products', 'list_url' => admin_url( 'admin.php?page=wp-easycart-products&subpage=manufacturers' ), 'product_total' => $this->total, 'shown' => count( $this->products ) );
		}
	}

endif;

/* ---------------------------------------------------------------------- */
function ecv2_mf_guard( $nonce = 'wp-easycart-ecv2-inline-update', $field = 'wp_easycart_nonce' ) {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) { wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) ); }
	check_ajax_referer( $nonce, $field );
}

/** Shared by the list modal and the product editor's "new manufacturer" flow. */
function ecv2_manufacturer_insert( $name ) {
	global $wpdb;
	$wpdb->insert( 'ec_manufacturer', array( 'name' => $name, 'clicks' => 0 ) );
	$id = (int) $wpdb->insert_id;
	$post_id = wp_easycart_post_sync()->insert( 'manufacturer', $id, array( 'post_content' => '[ec_store manufacturerid="' . $id . '"]', 'post_status' => 'publish', 'post_title' => wp_easycart_language()->convert_text( $name ), 'post_type' => 'ec_store', 'post_excerpt' => '' ) );
	if ( $post_id ) { wp_set_post_tags( $post_id, array( 'manufacturer' ), true ); $wpdb->update( 'ec_manufacturer', array( 'post_id' => (int) $post_id ), array( 'manufacturer_id' => $id ) ); }
	do_action( 'wpeasycart_manufacturer_added', $id );
	return $id;
}

add_action( 'wp_ajax_ecv2_manufacturer_create', 'ecv2_manufacturer_create' );
function ecv2_manufacturer_create() {
	ecv2_mf_guard();
	$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	if ( '' === $name ) { wp_send_json_error( array( 'message' => __( 'Name is required.', 'wp-easycart' ) ) ); }
	global $wpdb;
	if ( $wpdb->get_var( $wpdb->prepare( 'SELECT manufacturer_id FROM ec_manufacturer WHERE name = %s', $name ) ) ) { wp_send_json_error( array( 'message' => __( 'A manufacturer with that name already exists.', 'wp-easycart' ) ) ); }
	$id = ecv2_manufacturer_insert( $name );
	wp_send_json_success( array( 'manufacturer_id' => $id, 'edit_url' => wp_easycart_admin_manufacturer_table::editor_url( $id ) ) );
}

add_action( 'wp_ajax_ecv2_manufacturer_inline_update', 'ecv2_manufacturer_inline_update' );
function ecv2_manufacturer_inline_update() {
	ecv2_mf_guard(); global $wpdb;
	$id = isset( $_POST['manufacturer_id'] ) ? (int) $_POST['manufacturer_id'] : 0; $field = isset( $_POST['field'] ) ? sanitize_key( $_POST['field'] ) : ''; $value = isset( $_POST['value'] ) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '';
	if ( ! $id || 'name' !== $field || '' === $value ) { wp_send_json_error( array( 'message' => __( 'Name cannot be empty.', 'wp-easycart' ) ) ); }
	$old = $wpdb->get_var( $wpdb->prepare( 'SELECT name FROM ec_manufacturer WHERE manufacturer_id = %d', $id ) );
	$wpdb->update( 'ec_manufacturer', array( 'name' => $value ), array( 'manufacturer_id' => $id ) );
	$post_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT post_id FROM ec_manufacturer WHERE manufacturer_id = %d', $id ) );
	if ( $post_id ) { wp_update_post( array( 'ID' => $post_id, 'post_title' => wp_easycart_language()->convert_text( $value ) ) ); }
	do_action( 'wpeasycart_manufacturer_updated', $id );
	wp_send_json_success( array( 'display_value' => $value, 'old_value' => $old ) );
}

add_action( 'wp_ajax_ecv2_manufacturer_bulk', 'ecv2_manufacturer_bulk' );
function ecv2_manufacturer_bulk() {
	ecv2_mf_guard();
	$ids = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ? array_slice( array_map( 'intval', $_POST['ids'] ), 0, 200 ) : array();
	if ( empty( $ids ) || 'delete' !== ( isset( $_POST['op'] ) ? sanitize_key( $_POST['op'] ) : '' ) ) { wp_send_json_error( array( 'message' => __( 'Nothing to do.', 'wp-easycart' ) ) ); }
	$done = 0; $trash = array(); $errors = array();
	foreach ( $ids as $id ) { $r = wp_easycart_admin_safe_delete()->execute( 'manufacturer', $id, array( 'strategy' => 'remove', 'redirect' => true ) ); if ( is_wp_error( $r ) ) { $errors[] = $r->get_error_message(); } else { $done++; $trash[] = $r['trash_id']; } }
	wp_send_json_success( array( 'done' => $done, 'trash_ids' => $trash, 'errors' => $errors ) );
}

add_action( 'wp_ajax_ecv2_manufacturer_assign_search', 'ecv2_manufacturer_assign_search' );
function ecv2_manufacturer_assign_search() {
	ecv2_mf_guard(); global $wpdb;
	$id = isset( $_POST['manufacturer_id'] ) ? (int) $_POST['manufacturer_id'] : 0;
	$q = isset( $_POST['q'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['q'] ) ) ) : '';
	$scope = isset( $_POST['scope'] ) ? sanitize_key( $_POST['scope'] ) : 'unassigned';
	$where = $wpdb->prepare( 'WHERE p.manufacturer_id != %d', $id );
	if ( 'unassigned' === $scope ) { $where .= ' AND ( p.manufacturer_id = 0 OR p.manufacturer_id NOT IN ( SELECT manufacturer_id FROM ec_manufacturer ) )'; }
	if ( '' !== $q ) { $where .= $wpdb->prepare( ' AND ( p.title LIKE %s OR p.model_number LIKE %s )', '%' . $wpdb->esc_like( $q ) . '%', '%' . $wpdb->esc_like( $q ) . '%' ); }
	$rows = $wpdb->get_results( "SELECT p.product_id, p.title, p.model_number, p.price, p.activate_in_store, " . wp_easycart_admin_catalog_v2_thumb_select( "p" ) . ", ( SELECT name FROM ec_manufacturer m WHERE m.manufacturer_id = p.manufacturer_id ) AS current FROM ec_product p $where ORDER BY p.title LIMIT 40" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is literal SQL plus $wpdb->prepare() fragments; thumb_select() returns static column SQL.
	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM ec_product p $where" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is literal SQL plus $wpdb->prepare() fragments.
	$out = array();
	foreach ( $rows as $r ) { $out[] = array( 'id' => (int) $r->product_id, 'title' => wp_unslash( $r->title ), 'sku' => $r->model_number, 'price' => $GLOBALS['currency']->get_currency_display( $r->price ), 'image' => wp_easycart_admin_catalog_v2_product_thumb( $r ), 'active' => (bool) $r->activate_in_store, 'cats' => $r->current ? $r->current : '' ); }
	wp_send_json_success( array( 'items' => $out, 'total' => $total ) );
}

add_action( 'wp_ajax_ecv2_manufacturer_assign', 'ecv2_manufacturer_assign' );
function ecv2_manufacturer_assign() {
	ecv2_mf_guard(); global $wpdb;
	$id = isset( $_POST['manufacturer_id'] ) ? (int) $_POST['manufacturer_id'] : 0;
	$pids = isset( $_POST['product_ids'] ) && is_array( $_POST['product_ids'] ) ? array_map( 'intval', $_POST['product_ids'] ) : array();
	if ( ! $id || empty( $pids ) || ! $wpdb->get_var( $wpdb->prepare( 'SELECT manufacturer_id FROM ec_manufacturer WHERE manufacturer_id = %d', $id ) ) ) { wp_send_json_error( array( 'message' => __( 'Nothing to assign.', 'wp-easycart' ) ) ); }
	$n = 0;
	foreach ( $pids as $pid ) { $n += (int) $wpdb->update( 'ec_product', array( 'manufacturer_id' => $id ), array( 'product_id' => $pid ) ); do_action( 'wp_easycart_product_updated', $pid ); }
	wp_send_json_success( array( 'added' => $n ) );
}

add_action( 'wp_ajax_ecv2_manufacturer_save', 'ecv2_manufacturer_save' );
function ecv2_manufacturer_save() {
	ecv2_mf_guard( wp_easycart_admin_manufacturer_editor_v2::NONCE, 'nonce' ); global $wpdb;
	$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_manufacturer WHERE manufacturer_id = %d', $id ) );
	if ( ! $row ) { wp_send_json_error( array( 'message' => __( 'Manufacturer not found.', 'wp-easycart' ) ) ); }
	$d = json_decode( wp_unslash( isset( $_POST['data'] ) ? $_POST['data'] : '{}' ), true );
	$name = is_array( $d ) && isset( $d['name'] ) ? sanitize_text_field( $d['name'] ) : '';
	if ( '' === $name ) { wp_send_json_error( array( 'message' => __( 'A name is required.', 'wp-easycart' ) ) ); }
	$wpdb->update( 'ec_manufacturer', array( 'name' => $name ), array( 'manufacturer_id' => $id ) );
	$old_link = $row->post_id ? get_permalink( (int) $row->post_id ) : '';
	$post = array( 'post_content' => '[ec_store manufacturerid="' . $id . '"]', 'post_status' => 'publish', 'post_title' => wp_easycart_language()->convert_text( $name ), 'post_type' => 'ec_store', 'post_excerpt' => isset( $d['excerpt'] ) ? sanitize_text_field( $d['excerpt'] ) : '' );
	$slug = isset( $d['slug'] ) ? sanitize_title( $d['slug'] ) : ''; if ( '' !== $slug ) { $post['post_name'] = $slug; }
	$post_id = wp_easycart_post_sync()->update( 'manufacturer', $id, (int) $row->post_id, $post );
	if ( $post_id && $post_id != $row->post_id ) { wp_set_post_tags( $post_id, array( 'manufacturer' ), true ); $wpdb->update( 'ec_manufacturer', array( 'post_id' => (int) $post_id ), array( 'manufacturer_id' => $id ) ); }
	if ( $post_id ) { $fi = isset( $d['featured_image'] ) ? (int) $d['featured_image'] : 0; if ( $fi ) { set_post_thumbnail( $post_id, $fi ); } else { delete_post_thumbnail( $post_id ); } $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->posts} SET guid = %s WHERE ID = %d", get_permalink( $post_id ), $post_id ) ); }
	$new_link = $post_id ? get_permalink( $post_id ) : ''; $redirect_added = false;
	if ( ! empty( $d['redirect'] ) && $old_link && $new_link && class_exists( 'ec_url_redirects' ) && ec_url_redirects::key( $old_link ) !== ec_url_redirects::key( $new_link ) ) { $redirect_added = ec_url_redirects::add( $old_link, $new_link, 'manufacturer-slug:' . $id ); }
	do_action( 'wpeasycart_manufacturer_updated', $id );
	$ed = new wp_easycart_admin_manufacturer_editor_v2(); $ed->load( $id );
	wp_send_json_success( array( 'message' => __( 'Saved', 'wp-easycart' ), 'permalink' => $new_link, 'slug' => $ed->post ? $ed->post->post_name : '', 'slug_prefix' => $ed->slug_prefix(), 'redirect_added' => $redirect_added, 'health' => $ed->health(), 'path' => array() ) );
}

add_action( 'wp_ajax_ecv2_manufacturer_products', 'ecv2_manufacturer_products' );
function ecv2_manufacturer_products() {
	ecv2_mf_guard( wp_easycart_admin_manufacturer_editor_v2::NONCE, 'nonce' );
	wp_send_json_success( array( 'items' => wp_easycart_admin_manufacturer_editor_v2::product_rows( isset( $_POST['id'] ) ? (int) $_POST['id'] : 0, 30, isset( $_POST['offset'] ) ? (int) $_POST['offset'] : 0 ) ) );
}
