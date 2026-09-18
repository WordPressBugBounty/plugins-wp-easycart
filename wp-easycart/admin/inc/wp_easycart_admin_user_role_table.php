<?php
/**
 * WP EasyCart Admin — User Roles ( V2 ).
 *
 * List: label, remote-admin access toggle ( PRO ), users in role, products with a role price. Editor: details,
 * remote access panels, ROLE PRICES ( search a product → set price; bulk % off ), users in role ( move ), danger
 * zone. ec_user.user_level stores the role LABEL, so renames cascade to users, role prices and roleaccess.
 * Roles 1 ( admin ) and 2 ( shopper ) are masters: no rename, no delete.
 *
 * AJAX: ecv2_role_create, ecv2_role_toggle_access, ecv2_role_save, ecv2_role_price_search, ecv2_role_price_set,
 *       ecv2_role_price_list ( 6.0.0 ), ecv2_role_price_remove, ecv2_role_price_percent, ecv2_role_users,
 *       ecv2_role_move_users, ecv2_role_delete_impact, ecv2_role_delete, ecv2_role_restore.
 * The Role prices card is driven by admin/js/user-role-editor-v2.js ( 6.0.0 ); the rest of the editor by settings-lists-v2.js.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

include_once( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_table_v2.php' );

if ( ! class_exists( 'wp_easycart_admin_user_role_table' ) ) :

	class wp_easycart_admin_user_role_table extends wp_easycart_admin_table_v2 {

		const NONCE = 'wp-easycart-rolev2';
		const MASTERS = array( 1, 2 );

		public static function editor_url( $id ) { return admin_url( 'admin.php?page=wp-easycart-users&subpage=user-roles&ec_admin_form_action=edit&role_id=' . ( '{id}' === $id ? '{id}' : (int) $id ) ); }
		public static function is_pro() { return '' === apply_filters( 'wp_easycart_admin_lock_icon', 'locked' ); }

		public function __construct() { parent::__construct(); $this->setup(); }

		public function setup() {
			global $wpdb;
			$this->set_table( 'ec_role', 'role_id' );
			$this->set_table_id( 'ec_admin_user_role_list_v2' );
			$this->set_default_sort( 'role_id', 'ASC' );
			$this->set_header( __( 'User Roles', 'wp-easycart' ) );
			$this->set_docs_link( 'users', 'user-roles' );
			$this->set_add_new( true, 'add-new', __( 'Add role', 'wp-easycart' ) );
			$this->set_add_new_js( 'ecrole.new_role( this ); return false;' );
			$this->set_add_new_css( 'ecv2-btn ecv2-btn-primary' );
			$this->set_label( __( 'Role', 'wp-easycart' ), __( 'Roles', 'wp-easycart' ) );
			$this->set_view_modes( array( 'table' ) );
			$this->set_list_columns( array(
				array( 'name' => 'role_label', 'label' => __( 'Role', 'wp-easycart' ), 'format' => 'role_name', 'linked' => true ),
				array( 'name' => 'admin_access', 'label' => __( 'Remote admin access', 'wp-easycart' ), 'format' => 'role_access' ),
				array( 'select' => '( SELECT COUNT(*) FROM ec_user u WHERE u.user_level = ec_role.role_label ) AS user_count', 'name' => 'user_count', 'label' => __( 'Users', 'wp-easycart' ), 'format' => 'role_users' ),
				array( 'select' => '( SELECT COUNT(*) FROM ec_roleprice rp WHERE rp.role_label = ec_role.role_label ) AS price_count', 'name' => 'price_count', 'label' => __( 'Role prices', 'wp-easycart' ), 'format' => 'role_prices' ),
				array( 'name' => 'role_id', 'label' => __( 'ID', 'wp-easycart' ), 'format' => 'int', 'is_id' => true, 'laptop_hide' => true ),
			) );
			$this->set_search_columns( array( 'ec_role.role_label' ) );
			$this->set_bulk_actions( array( array( 'name' => 'ecv2-role-delete', 'label' => __( 'Delete', 'wp-easycart' ) ) ) );
			$this->set_row_menu_actions( array(
				array( 'label' => __( 'Edit role', 'wp-easycart' ), 'name' => 'edit', 'icon' => 'edit', 'href' => self::editor_url( '{id}' ) ),
				array( 'label' => __( 'View users', 'wp-easycart' ), 'name' => 'users', 'icon' => 'groups', 'href' => '#', 'onclick' => 'return ecrole.view_users( this );' ),
				array( 'label' => __( 'Delete', 'wp-easycart' ), 'name' => 'delete', 'icon' => 'trash', 'href' => '#', 'onclick' => 'return ecrole.delete_row( this );', 'danger' => true ),
			) );
			$this->set_filters( array(
				array( 'data' => array( (object) array( 'value' => 'access', 'label' => __( 'Remote access', 'wp-easycart' ), 'icon' => 'smartphone' ), (object) array( 'value' => 'nousers', 'label' => __( 'No users', 'wp-easycart' ), 'icon' => 'marker' ), (object) array( 'value' => 'prices', 'label' => __( 'Has role prices', 'wp-easycart' ), 'icon' => 'tag' ) ), 'label' => __( 'Show', 'wp-easycart' ), 'type' => 'pills', 'where_callback' => true ),
			) );
			$this->set_health_stats( array(
				array( 'label' => __( 'Roles', 'wp-easycart' ), 'value' => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_role' ), 'filter_value' => '', 'color' => 'default', 'group' => 'catalog' ),
				array( 'label' => __( 'Remote admin access', 'wp-easycart' ), 'value' => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_role WHERE admin_access = 1' ), 'filter_value' => 'access', 'color' => 'blue', 'group' => 'catalog' ),
				array( 'label' => __( 'No users', 'wp-easycart' ), 'value' => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_role WHERE NOT EXISTS ( SELECT 1 FROM ec_user u WHERE u.user_level = ec_role.role_label )' ), 'filter_value' => 'nousers', 'color' => 'amber', 'group' => 'attention' ),
				array( 'label' => __( 'Products with role prices', 'wp-easycart' ), 'value' => (int) $wpdb->get_var( 'SELECT COUNT( DISTINCT product_id ) FROM ec_roleprice' ), 'filter_value' => 'prices', 'color' => 'green', 'group' => 'attention' ),
			) );
		}

		protected function get_health_filter_where( $k ) {
			switch ( $k ) {
				case 'access': return 'ec_role.admin_access = 1';
				case 'nousers': return 'NOT EXISTS ( SELECT 1 FROM ec_user u WHERE u.user_level = ec_role.role_label )';
				case 'prices': return 'EXISTS ( SELECT 1 FROM ec_roleprice rp WHERE rp.role_label = ec_role.role_label )';
			}
			return '';
		}
		protected function get_filter_callback_where( $i, $v ) { return 0 === $i ? $this->get_health_filter_where( $v ) : ''; }

		protected function print_table_row( $result ) {
			$master = in_array( (int) $result->role_id, self::MASTERS, true );
			echo '<tr class="ecv2-row' . ( $master ? ' ecv2-row-master' : '' ) . '" data-id="' . esc_attr( $result->role_id ) . '" data-label="' . esc_attr( wp_unslash( $result->role_label ) ) . '" data-master="' . ( $master ? 1 : 0 ) . '">';
			echo '<td class="ecv2-col-check">' . ( $master ? '' : '<input type="checkbox" name="bulk[]" value="' . esc_attr( $result->role_id ) . '" class="ecv2-row-check" />' ) . '</td>';
			foreach ( $this->list_columns as $col ) {
				if ( 'hidden' === $col['format'] ) { continue; }
				echo '<td class="ecv2-cell ecv2-cell-' . esc_attr( $col['name'] ) . ( ! empty( $col['laptop_hide'] ) ? ' ecv2-hide-laptop' : '' ) . '">'; $this->print_cell_content( $result, $col ); echo '</td>';
			}
			echo '<td class="ecv2-col-actions">'; $this->print_row_actions( $result ); echo '</td></tr>';
		}

		protected function print_cell_content( $result, $col ) {
			switch ( $col['format'] ) {
				case 'role_name':
					echo '<a href="' . esc_url( self::editor_url( $result->role_id ) ) . '" class="ecv2-link-primary ecv2-title-link">' . esc_html( wp_unslash( $result->role_label ) ) . '</a>';
					if ( in_array( (int) $result->role_id, self::MASTERS, true ) ) { echo ' <span class="ecv2-chip ecv2-chip-gray" title="' . esc_attr__( 'Built-in role: cannot be renamed or deleted', 'wp-easycart' ) . '">' . esc_html__( 'Built-in', 'wp-easycart' ) . '</span>'; }
					break;
				case 'role_access':
					if ( self::is_pro() ) { echo '<label class="ecv2-toggle ecv2-toggle-sm"><input type="checkbox" class="ecv2-role-access" data-id="' . esc_attr( $result->role_id ) . '"' . ( $result->admin_access ? ' checked' : '' ) . ' /><span class="ecv2-toggle-slider"></span></label>' . ( $result->admin_access ? ' <span class="ecv2-sub" style="display:inline">' . esc_html__( 'can use the remote admin app', 'wp-easycart' ) . '</span>' : '' ); }
					else { echo '<span class="ecv2-chip" title="' . esc_attr( wp_easycart_admin_edition::requires_text( __( 'Remote admin access', 'wp-easycart' ), 'pro' ) ) . '"><span class="dashicons dashicons-lock" style="font-size:12px;width:12px;height:12px"></span> ' . esc_html( wp_easycart_admin_edition::badge( 'pro' ) ) . '</span>'; }
					break;
				case 'role_users':
					$n = (int) $result->user_count;
					echo $n ? '<a class="ecv2-usage" href="' . esc_url( admin_url( 'admin.php?page=wp-easycart-users&subpage=accounts&filter_0=' . rawurlencode( wp_unslash( $result->role_label ) ) ) ) . '">' . esc_html( sprintf( _n( '%d user', '%d users', $n, 'wp-easycart' ), $n ) ) . '</a>' : '<span class="ecv2-usage ecv2-usage-zero">' . esc_html__( 'No users', 'wp-easycart' ) . '</span>';
					break;
				case 'role_prices':
					$n = (int) $result->price_count;
					echo $n ? '<a class="ecv2-usage" href="' . esc_url( self::editor_url( $result->role_id ) . '#rlv2-prices' ) . '">' . esc_html( sprintf( _n( '%d product', '%d products', $n, 'wp-easycart' ), $n ) ) . '</a>' : '<span class="ecv2-sub">—</span>';
					break;
				default: parent::print_cell_content( $result, $col );
			}
		}
	}

	class wp_easycart_admin_user_role_editor_v2 {
		public $role; public $users = array(); public $user_total = 0; public $prices = array(); public $price_total = 0; public $panels = array(); public $available_panels = array(); public $master = false; public $pro = false; public $docs_link = '';
		/** Active products in the catalog, for the "every active product" confirmation. @since 6.0.0 */
		public $active_products = 0;
		/** Role prices per page in the editor card. @since 6.0.0 */
		const PRICE_PER_PAGE = 50;
		public function __construct() { $this->docs_link = wp_easycart_admin()->helpsystem->print_docs_url( 'users', 'user-roles', 'user-roles' ); $this->pro = wp_easycart_admin_user_role_table::is_pro(); }
		public function load( $id ) {
			global $wpdb;
			$this->role = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_role WHERE role_id = %d', $id ) );
			if ( ! $this->role ) { return false; }
			$this->master = in_array( (int) $id, wp_easycart_admin_user_role_table::MASTERS, true );
			$this->user_total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_user WHERE user_level = %s', $this->role->role_label ) );
			$this->users = self::user_rows( $this->role->role_label, 25 );
			$this->price_total = self::price_count( $this->role->role_label );
			$this->prices = self::price_rows( $this->role->role_label, self::PRICE_PER_PAGE );
			$this->active_products = self::active_product_count();
			$this->panels = $wpdb->get_col( $wpdb->prepare( 'SELECT admin_panel FROM ec_roleaccess WHERE role_label = %s', $this->role->role_label ) );
			$this->available_panels = self::panels();
			return true;
		}
		/** Remote-admin panels; PRO extends via the same filter the legacy details page used. */
		public static function panels() {
			$base = array( 'orders' => __( 'Orders', 'wp-easycart' ), 'products' => __( 'Products', 'wp-easycart' ), 'categories' => __( 'Categories', 'wp-easycart' ), 'options' => __( 'Option sets', 'wp-easycart' ), 'menus' => __( 'Menus', 'wp-easycart' ), 'manufacturers' => __( 'Manufacturers', 'wp-easycart' ), 'reviews' => __( 'Reviews', 'wp-easycart' ), 'users' => __( 'Users', 'wp-easycart' ), 'downloads' => __( 'Downloads', 'wp-easycart' ), 'subscriptions' => __( 'Subscriptions', 'wp-easycart' ), 'plans' => __( 'Subscription plans', 'wp-easycart' ), 'giftcards' => __( 'Gift cards', 'wp-easycart' ), 'coupons' => __( 'Coupons', 'wp-easycart' ), 'promotions' => __( 'Promotions', 'wp-easycart' ), 'news' => __( 'News', 'wp-easycart' ), 'newsletter' => __( 'Newsletter', 'wp-easycart' ) );
			return apply_filters( 'wp_easycart_admin_role_remote_panels', $base );
		}
		/** Users in a role: server-side search + paging ( a role can hold thousands ). Names come from ec_user; no per-row WP lookups. */
		public static function user_rows( $label, $limit, $offset = 0, $q = '' ) {
			global $wpdb; $out = array();
			$where = $wpdb->prepare( 'user_level = %s', $label );
			if ( '' !== $q ) { $like = '%' . $wpdb->esc_like( $q ) . '%'; $where .= $wpdb->prepare( " AND ( email LIKE %s OR first_name LIKE %s OR last_name LIKE %s OR CONCAT( first_name, ' ', last_name ) LIKE %s )", $like, $like, $like, $like ); }
			foreach ( $wpdb->get_results( "SELECT user_id, email, first_name, last_name, completed_order_count, lifetime_spend, last_login FROM ec_user WHERE $where ORDER BY last_name, first_name, email LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset ) as $u ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is built entirely from $wpdb->prepare() above; limit/offset are (int) cast.
				$out[] = array( 'id' => (int) $u->user_id, 'email' => $u->email, 'name' => trim( wp_unslash( $u->first_name . ' ' . $u->last_name ) ), 'orders' => (int) $u->completed_order_count, 'spend' => (float) $u->lifetime_spend > 0 ? $GLOBALS['currency']->get_currency_display( $u->lifetime_spend ) : '', 'edit_url' => admin_url( 'admin.php?page=wp-easycart-users&subpage=accounts&ec_admin_form_action=edit&user_id=' . (int) $u->user_id ) );
			}
			return $out;
		}
		public static function user_count( $label, $q = '' ) {
			global $wpdb; $where = $wpdb->prepare( 'user_level = %s', $label );
			if ( '' !== $q ) { $like = '%' . $wpdb->esc_like( $q ) . '%'; $where .= $wpdb->prepare( " AND ( email LIKE %s OR first_name LIKE %s OR last_name LIKE %s OR CONCAT( first_name, ' ', last_name ) LIKE %s )", $like, $like, $like, $like ); }
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM ec_user WHERE $where" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is built entirely from $wpdb->prepare() above.
		}
		/**
		 * WHERE clause shared by price_rows() / price_count(): the role, an optional title / SKU filter and an
		 * optional single roleprice_id. Built entirely from $wpdb->prepare().
		 *
		 * @since 6.0.0
		 */
		private static function price_where( $label, $q = '', $roleprice_id = 0 ) {
			global $wpdb;
			$where = $wpdb->prepare( 'rp.role_label = %s', $label );
			if ( '' !== $q ) { $like = '%' . $wpdb->esc_like( $q ) . '%'; $where .= $wpdb->prepare( ' AND ( p.title LIKE %s OR p.model_number LIKE %s )', $like, $like ); }
			if ( $roleprice_id ) { $where .= $wpdb->prepare( ' AND rp.roleprice_id = %d', (int) $roleprice_id ); }
			return $where;
		}
		/**
		 * Role prices for the editor card, ordered by product title. $q filters by title / SKU and $roleprice_id
		 * fetches one row ( both @since 6.0.0 ); a role can hold thousands, so callers page 50 at a time.
		 */
		public static function price_rows( $label, $limit, $offset = 0, $q = '', $roleprice_id = 0 ) {
			global $wpdb; $out = array();
			$where = self::price_where( $label, $q, $roleprice_id );
			foreach ( $wpdb->get_results( 'SELECT rp.roleprice_id, rp.product_id, rp.role_price, p.title, p.model_number, p.price, p.activate_in_store, ' . wp_easycart_admin_catalog_v2_thumb_select( 'p' ) . " FROM ec_roleprice rp LEFT JOIN ec_product p ON p.product_id = rp.product_id WHERE $where ORDER BY p.title LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- $where is built entirely from $wpdb->prepare() in price_where(); the thumb helper returns static column SQL for a literal alias; limit/offset are (int) cast.
				$reg = (float) $r->price; $rp = (float) $r->role_price;
				$out[] = array( 'id' => (int) $r->roleprice_id, 'product_id' => (int) $r->product_id, 'title' => $r->title ? wp_unslash( $r->title ) : __( '(deleted product)', 'wp-easycart' ), 'sku' => (string) $r->model_number, 'regular' => $reg, 'role_price' => $rp, 'pct' => $reg > 0 ? round( 100 * ( $rp - $reg ) / $reg ) : null, 'image' => $r->title ? wp_easycart_admin_catalog_v2_product_thumb( $r ) : '', 'active' => (bool) $r->activate_in_store, 'orphan' => ! $r->title );
			}
			return $out;
		}
		/** Number of role prices for a role, optionally filtered by product title / SKU. @since 6.0.0 */
		public static function price_count( $label, $q = '' ) {
			global $wpdb;
			$where = self::price_where( $label, $q );
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM ec_roleprice rp LEFT JOIN ec_product p ON p.product_id = rp.product_id WHERE $where" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is built entirely from $wpdb->prepare() in price_where().
		}
		/** Products the "every active product" bulk action would touch; shown in its confirmation. @since 6.0.0 */
		public static function active_product_count() {
			global $wpdb;
			return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_product WHERE activate_in_store = 1' );
		}
		public function health() {
			$out = array();
			$out[] = array( 'ok' => $this->user_total > 0, 'label' => $this->user_total ? sprintf( _n( '%d user in this role', '%d users in this role', $this->user_total, 'wp-easycart' ), $this->user_total ) : __( 'No users have this role yet', 'wp-easycart' ) );
			$out[] = array( 'ok' => true, 'label' => $this->price_total ? sprintf( _n( '%d product has a role price', '%d products have a role price', $this->price_total, 'wp-easycart' ), $this->price_total ) : __( 'No role prices — customers see regular prices', 'wp-easycart' ) );
			$higher = count( array_filter( $this->prices, function( $p ) { return null !== $p['pct'] && $p['pct'] > 0; } ) );
			if ( $higher ) { $out[] = array( 'ok' => false, 'label' => sprintf( _n( '%d role price is higher than the regular price', '%d role prices are higher than the regular price', $higher, 'wp-easycart' ), $higher ) ); }
			$orph = count( array_filter( $this->prices, function( $p ) { return $p['orphan']; } ) );
			if ( $orph ) { $out[] = array( 'ok' => false, 'label' => sprintf( __( '%d role prices point at deleted products', 'wp-easycart' ), $orph ) ); }
			if ( $this->role->admin_access ) { $out[] = array( 'ok' => count( $this->panels ) > 0, 'label' => count( $this->panels ) ? sprintf( __( 'Remote admin: %d panels', 'wp-easycart' ), count( $this->panels ) ) : __( 'Remote admin on but no panels selected', 'wp-easycart' ) ); }
			return $out;
		}
		public function output() {
			if ( ! $this->load( isset( $_GET['role_id'] ) ? (int) $_GET['role_id'] : 0 ) ) {
				echo '<div class="ecv2-wrap"><div class="ecv2-empty-state">' . esc_html__( 'That role no longer exists.', 'wp-easycart' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=wp-easycart-users&subpage=user-roles' ) ) . '">' . esc_html__( 'Back to roles', 'wp-easycart' ) . '</a></div></div>'; return;
			}
			include( EC_PLUGIN_DIRECTORY . '/admin/template/users/user-roles/user-role-editor-v2.php' );
		}
		public function js_data() {
			global $wpdb; $roles = array(); foreach ( $wpdb->get_results( 'SELECT role_id, role_label FROM ec_role ORDER BY role_id' ) as $rr ) { $roles[] = array( 'id' => (int) $rr->role_id, 'label' => wp_unslash( $rr->role_label ) ); }
			return array( 'roles' => $roles, 'grants_admin' => (bool) $this->role->admin_access, 'user_total' => (int) $this->user_total, 'kind' => 'role', 'id' => (int) $this->role->role_id, 'label' => wp_unslash( $this->role->role_label ), 'master' => $this->master, 'is_pro' => $this->pro, 'nonce' => wp_create_nonce( wp_easycart_admin_user_role_table::NONCE ), 'save_action' => 'ecv2_role_save', 'list_url' => admin_url( 'admin.php?page=wp-easycart-users&subpage=user-roles' ), 'redirects' => false, 'slug' => '', 'price_total' => $this->price_total, 'price_per' => self::PRICE_PER_PAGE, 'active_products' => $this->active_products, 'user_total' => $this->user_total );
		}
	}

endif;

/* ---------------------------------------------------------------------- */
function ecv2_role_guard() {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_users' ) ) { wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) ); }
	check_ajax_referer( wp_easycart_admin_user_role_table::NONCE, 'nonce' );
}
function ecv2_role_row( $id ) { global $wpdb; return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_role WHERE role_id = %d', (int) $id ) ); }
function ecv2_role_is_master( $id ) { return in_array( (int) $id, wp_easycart_admin_user_role_table::MASTERS, true ); }

add_action( 'wp_ajax_ecv2_role_create', 'ecv2_role_create' );
function ecv2_role_create() {
	ecv2_role_guard(); global $wpdb;
	$label = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
	if ( '' === $label ) { wp_send_json_error( array( 'message' => __( 'A role name is required.', 'wp-easycart' ) ) ); }
	if ( $wpdb->get_var( $wpdb->prepare( 'SELECT role_id FROM ec_role WHERE role_label = %s', $label ) ) ) { wp_send_json_error( array( 'message' => __( 'A role with that name already exists.', 'wp-easycart' ) ) ); }
	$wpdb->insert( 'ec_role', array( 'role_label' => $label, 'admin_access' => 0 ) );
	$id = (int) $wpdb->insert_id;
	do_action( 'wpeasycart_user_role_added', $id );
	wp_send_json_success( array( 'role_id' => $id, 'edit_url' => wp_easycart_admin_user_role_table::editor_url( $id ) ) );
}

add_action( 'wp_ajax_ecv2_role_toggle_access', 'ecv2_role_toggle_access' );
function ecv2_role_toggle_access() {
	ecv2_role_guard(); global $wpdb;
	if ( ! wp_easycart_admin_user_role_table::is_pro() ) { wp_send_json_error( array( 'message' => wp_easycart_admin_edition::requires_text( __( 'Remote admin access', 'wp-easycart' ), 'pro' ) ) ); }
	$id = isset( $_POST['role_id'] ) ? (int) $_POST['role_id'] : 0; $on = ! empty( $_POST['on'] ) && '0' !== $_POST['on'] ? 1 : 0;
	if ( ! ecv2_role_row( $id ) ) { wp_send_json_error( array( 'message' => __( 'Role not found.', 'wp-easycart' ) ) ); }
	$wpdb->update( 'ec_role', array( 'admin_access' => $on ), array( 'role_id' => $id ) );
	do_action( 'wpeasycart_user_role_updated', $id );
	wp_send_json_success( array( 'admin_access' => $on ) );
}

add_action( 'wp_ajax_ecv2_role_save', 'ecv2_role_save' );
function ecv2_role_save() {
	ecv2_role_guard(); global $wpdb;
	$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0; $role = ecv2_role_row( $id );
	if ( ! $role ) { wp_send_json_error( array( 'message' => __( 'Role not found.', 'wp-easycart' ) ) ); }
	$d = json_decode( wp_unslash( isset( $_POST['data'] ) ? $_POST['data'] : '{}' ), true ); if ( ! is_array( $d ) ) { $d = array(); }
	$label = isset( $d['name'] ) ? sanitize_text_field( $d['name'] ) : $role->role_label;
	if ( '' === $label ) { wp_send_json_error( array( 'message' => __( 'A role name is required.', 'wp-easycart' ) ) ); }
	if ( ecv2_role_is_master( $id ) ) { $label = $role->role_label; }
	if ( $label !== $role->role_label ) {
		if ( $wpdb->get_var( $wpdb->prepare( 'SELECT role_id FROM ec_role WHERE role_label = %s AND role_id != %d', $label, $id ) ) ) { wp_send_json_error( array( 'message' => __( 'A role with that name already exists.', 'wp-easycart' ) ) ); }
		/* user_level stores the label — cascade */
		$wpdb->query( $wpdb->prepare( 'UPDATE ec_user SET user_level = %s WHERE user_level = %s', $label, $role->role_label ) );
		$wpdb->query( $wpdb->prepare( 'UPDATE ec_roleprice SET role_label = %s WHERE role_label = %s', $label, $role->role_label ) );
		$wpdb->query( $wpdb->prepare( 'UPDATE ec_roleaccess SET role_label = %s WHERE role_label = %s', $label, $role->role_label ) );
	}
	$access = (int) $role->admin_access; $panels = array_map( 'strval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT admin_panel FROM ec_roleaccess WHERE role_label = %s', $label ) ) );
	if ( wp_easycart_admin_user_role_table::is_pro() ) {
		$access = ! empty( $d['admin_access'] ) ? 1 : 0;
		$allowed = array_keys( wp_easycart_admin_user_role_editor_v2::panels() );
		$panels = isset( $d['panels'] ) && is_array( $d['panels'] ) ? array_values( array_intersect( array_map( 'sanitize_key', $d['panels'] ), $allowed ) ) : array();
		$wpdb->delete( 'ec_roleaccess', array( 'role_label' => $label ) );
		if ( $access ) { foreach ( $panels as $p ) { $wpdb->insert( 'ec_roleaccess', array( 'role_label' => $label, 'admin_panel' => $p ) ); } }
	}
	$wpdb->update( 'ec_role', array( 'role_label' => $label, 'admin_access' => $access ), array( 'role_id' => $id ) );
	do_action( 'wpeasycart_user_role_updated', $id );
	$ed = new wp_easycart_admin_user_role_editor_v2(); $ed->load( $id );
	wp_send_json_success( array( 'message' => __( 'Saved', 'wp-easycart' ), 'health' => $ed->health(), 'label' => $label ) );
}

add_action( 'wp_ajax_ecv2_role_price_search', 'ecv2_role_price_search' );
function ecv2_role_price_search() {
	ecv2_role_guard(); global $wpdb;
	$role = ecv2_role_row( isset( $_POST['role_id'] ) ? (int) $_POST['role_id'] : 0 ); if ( ! $role ) { wp_send_json_error( array( 'message' => __( 'Role not found.', 'wp-easycart' ) ) ); }
	$q = isset( $_POST['q'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['q'] ) ) ) : '';
	$like = '%' . $wpdb->esc_like( $q ) . '%';
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT p.product_id, p.title, p.model_number, p.price, ' . wp_easycart_admin_catalog_v2_thumb_select( 'p' ) . ', ( SELECT role_price FROM ec_roleprice rp WHERE rp.product_id = p.product_id AND rp.role_label = %s LIMIT 1 ) AS existing FROM ec_product p WHERE ( p.title LIKE %s OR p.model_number LIKE %s ) ORDER BY p.title LIMIT 15', $role->role_label, $like, $like ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- wp_easycart_admin_catalog_v2_thumb_select() returns static column SQL for a literal alias.
	$out = array();
	foreach ( $rows as $r ) { $out[] = array( 'id' => (int) $r->product_id, 'title' => wp_unslash( $r->title ), 'sku' => $r->model_number, 'price' => (float) $r->price, 'price_display' => $GLOBALS['currency']->get_currency_display( $r->price ), 'image' => wp_easycart_admin_catalog_v2_product_thumb( $r ), 'existing' => null === $r->existing ? null : (float) $r->existing ); }
	wp_send_json_success( array( 'items' => $out ) );
}

add_action( 'wp_ajax_ecv2_role_price_set', 'ecv2_role_price_set' );
function ecv2_role_price_set() {
	ecv2_role_guard(); global $wpdb;
	$role = ecv2_role_row( isset( $_POST['role_id'] ) ? (int) $_POST['role_id'] : 0 ); if ( ! $role ) { wp_send_json_error( array( 'message' => __( 'Role not found.', 'wp-easycart' ) ) ); }
	$pid = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0; $price = isset( $_POST['price'] ) ? (float) str_replace( ',', '', sanitize_text_field( wp_unslash( $_POST['price'] ) ) ) : -1;
	if ( ! $pid || $price < 0 ) { wp_send_json_error( array( 'message' => __( 'Enter a price of 0 or more.', 'wp-easycart' ) ) ); }
	if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT product_id FROM ec_product WHERE product_id = %d', $pid ) ) ) { wp_send_json_error( array( 'message' => __( 'Product not found.', 'wp-easycart' ) ) ); }
	$existing = $wpdb->get_var( $wpdb->prepare( 'SELECT roleprice_id FROM ec_roleprice WHERE product_id = %d AND role_label = %s', $pid, $role->role_label ) );
	$created = ! $existing;
	if ( $existing ) { $wpdb->update( 'ec_roleprice', array( 'role_price' => $price ), array( 'roleprice_id' => (int) $existing ) ); }
	else { $wpdb->insert( 'ec_roleprice', array( 'product_id' => $pid, 'role_label' => $role->role_label, 'role_price' => $price ) ); $existing = $wpdb->insert_id; }
	do_action( 'wp_easycart_product_updated', $pid );
	/* 6.0.0: fetch the saved row by id instead of scanning the first 500 — a role can hold thousands of prices. */
	$rows = wp_easycart_admin_user_role_editor_v2::price_rows( $role->role_label, 1, 0, '', (int) $existing );
	wp_send_json_success( array( 'row' => $rows ? $rows[0] : null, 'total' => wp_easycart_admin_user_role_editor_v2::price_count( $role->role_label ), 'created' => $created ) );
}

/**
 * Paged, filterable list of a role's prices for the editor card ( 50 per page ). @since 6.0.0
 * POST: role_id, page, q ( product title / SKU filter ).
 */
add_action( 'wp_ajax_ecv2_role_price_list', 'ecv2_role_price_list' );
function ecv2_role_price_list() {
	ecv2_role_guard();
	$role = ecv2_role_row( isset( $_POST['role_id'] ) ? (int) $_POST['role_id'] : 0 ); if ( ! $role ) { wp_send_json_error( array( 'message' => __( 'Role not found.', 'wp-easycart' ) ) ); }
	$q = isset( $_POST['q'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['q'] ) ) ) : ''; $per = wp_easycart_admin_user_role_editor_v2::PRICE_PER_PAGE; $page = isset( $_POST['page'] ) ? max( 1, (int) $_POST['page'] ) : 1;
	$total = wp_easycart_admin_user_role_editor_v2::price_count( $role->role_label, $q ); $pages = max( 1, (int) ceil( $total / $per ) ); $page = min( $page, $pages );
	wp_send_json_success( array(
		'items'           => wp_easycart_admin_user_role_editor_v2::price_rows( $role->role_label, $per, ( $page - 1 ) * $per, $q ),
		'total'           => $total,
		'all_total'       => '' === $q ? $total : wp_easycart_admin_user_role_editor_v2::price_count( $role->role_label ),
		'page'            => $page,
		'pages'           => $pages,
		'per'             => $per,
		'q'               => $q,
		'active_products' => wp_easycart_admin_user_role_editor_v2::active_product_count(),
	) );
}

/** Remove one role price ( roleprice_id ) or, @since 6.0.0, a selection ( roleprice_ids[], up to 500 ). */
add_action( 'wp_ajax_ecv2_role_price_remove', 'ecv2_role_price_remove' );
function ecv2_role_price_remove() {
	ecv2_role_guard(); global $wpdb;
	$ids = isset( $_POST['roleprice_ids'] ) && is_array( $_POST['roleprice_ids'] ) ? array_slice( array_filter( array_map( 'intval', $_POST['roleprice_ids'] ) ), 0, 500 ) : array();
	if ( ! $ids && ! empty( $_POST['roleprice_id'] ) ) { $ids = array( (int) $_POST['roleprice_id'] ); }
	$rows = $ids ? $wpdb->get_results( 'SELECT roleprice_id, product_id, role_label FROM ec_roleprice WHERE roleprice_id IN ( ' . implode( ',', $ids ) . ' )' ) : array(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $ids is an intval()-mapped list built above.
	if ( ! $rows ) { wp_send_json_error( array( 'message' => __( 'Not found.', 'wp-easycart' ) ) ); }
	$found = array(); foreach ( $rows as $rp ) { $found[] = (int) $rp->roleprice_id; }
	$wpdb->query( 'DELETE FROM ec_roleprice WHERE roleprice_id IN ( ' . implode( ',', $found ) . ' )' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $found is an (int) cast list of ids read back from the table.
	foreach ( $rows as $rp ) { do_action( 'wp_easycart_product_updated', (int) $rp->product_id ); }
	if ( count( $rows ) > 1 ) { wp_cache_flush(); }
	wp_send_json_success( array( 'total' => wp_easycart_admin_user_role_editor_v2::price_count( $rows[0]->role_label ), 'removed' => count( $rows ), 'message' => sprintf( _n( 'Removed %d role price.', 'Removed %d role prices.', count( $rows ), 'wp-easycart' ), count( $rows ) ) ) );
}

/**
 * Apply a % off the regular price. scope=existing: every role price this role already has; scope=selected
 * ( @since 6.0.0 ): the roleprice_ids[] posted; scope=all: every active product ( creates missing role prices ).
 */
add_action( 'wp_ajax_ecv2_role_price_percent', 'ecv2_role_price_percent' );
function ecv2_role_price_percent() {
	ecv2_role_guard(); global $wpdb;
	$role = ecv2_role_row( isset( $_POST['role_id'] ) ? (int) $_POST['role_id'] : 0 ); if ( ! $role ) { wp_send_json_error( array( 'message' => __( 'Role not found.', 'wp-easycart' ) ) ); }
	$pct = isset( $_POST['percent'] ) ? (float) $_POST['percent'] : 0; $scope = isset( $_POST['scope'] ) ? sanitize_key( wp_unslash( $_POST['scope'] ) ) : 'existing';
	if ( ! in_array( $scope, array( 'existing', 'selected', 'all' ), true ) ) { $scope = 'existing'; }
	if ( $pct <= 0 || $pct >= 100 ) { wp_send_json_error( array( 'message' => __( 'Enter a discount between 1 and 99 percent.', 'wp-easycart' ) ) ); }
	$mult = ( 100 - $pct ) / 100; $n = 0;
	if ( 'selected' === $scope ) {
		$ids = isset( $_POST['roleprice_ids'] ) && is_array( $_POST['roleprice_ids'] ) ? array_slice( array_filter( array_map( 'intval', $_POST['roleprice_ids'] ) ), 0, 500 ) : array();
		if ( ! $ids ) { wp_send_json_error( array( 'message' => __( 'Select at least one role price.', 'wp-easycart' ) ) ); }
		$n = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_roleprice rp JOIN ec_product p ON p.product_id = rp.product_id WHERE rp.role_label = %s AND rp.roleprice_id IN ( ' . implode( ',', $ids ) . ' )', $role->role_label ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $ids is an intval()-mapped list built above.
		$wpdb->query( $wpdb->prepare( 'UPDATE ec_roleprice rp JOIN ec_product p ON p.product_id = rp.product_id SET rp.role_price = ROUND( p.price * %f, 2 ) WHERE rp.role_label = %s AND rp.roleprice_id IN ( ' . implode( ',', $ids ) . ' )', $mult, $role->role_label ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $ids is an intval()-mapped list built above.
	} elseif ( 'all' === $scope ) {
		/*
		 * 6.0.0: two set-based statements instead of a PHP loop with two queries per product. ec_roleprice has
		 * only a plain index on ( product_id, role_label ), not a unique key, so INSERT … ON DUPLICATE KEY UPDATE
		 * is not available: update the role prices that exist, then insert the ones that do not.
		 */
		$wpdb->query( $wpdb->prepare( 'UPDATE ec_roleprice rp JOIN ec_product p ON p.product_id = rp.product_id SET rp.role_price = ROUND( p.price * %f, 2 ) WHERE rp.role_label = %s AND p.activate_in_store = 1', $mult, $role->role_label ) );
		$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_roleprice ( product_id, role_label, role_price ) SELECT p.product_id, %s, ROUND( p.price * %f, 2 ) FROM ec_product p WHERE p.activate_in_store = 1 AND NOT EXISTS ( SELECT 1 FROM ec_roleprice rp WHERE rp.product_id = p.product_id AND rp.role_label = %s )', $role->role_label, $mult, $role->role_label ) );
		$n = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_product WHERE activate_in_store = 1' );
	} else {
		/* Count the targets rather than trusting affected rows: MySQL reports 0 for rows whose price did not change. */
		$n = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_roleprice rp JOIN ec_product p ON p.product_id = rp.product_id WHERE rp.role_label = %s', $role->role_label ) );
		$wpdb->query( $wpdb->prepare( 'UPDATE ec_roleprice rp JOIN ec_product p ON p.product_id = rp.product_id SET rp.role_price = ROUND( p.price * %f, 2 ) WHERE rp.role_label = %s', $mult, $role->role_label ) );
	}
	wp_cache_flush();
	wp_send_json_success( array( 'message' => sprintf( _n( 'Set %1$d role price at %2$s%% off.', 'Set %1$d role prices at %2$s%% off.', $n, 'wp-easycart' ), $n, rtrim( rtrim( number_format( $pct, 2 ), '0' ), '.' ) ), 'count' => $n, 'total' => wp_easycart_admin_user_role_editor_v2::price_count( $role->role_label ), 'rows' => wp_easycart_admin_user_role_editor_v2::price_rows( $role->role_label, 500 ) ) );
}

add_action( 'wp_ajax_ecv2_role_users', 'ecv2_role_users' );
function ecv2_role_users() {
	ecv2_role_guard();
	$role = ecv2_role_row( isset( $_POST['role_id'] ) ? (int) $_POST['role_id'] : 0 ); if ( ! $role ) { wp_send_json_error( array( 'message' => __( 'Role not found.', 'wp-easycart' ) ) ); }
	$q = isset( $_POST['q'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['q'] ) ) ) : ''; $per = 25; $page = isset( $_POST['page'] ) ? max( 1, (int) $_POST['page'] ) : 1;
	$total = wp_easycart_admin_user_role_editor_v2::user_count( $role->role_label, $q ); $pages = max( 1, (int) ceil( $total / $per ) ); $page = min( $page, $pages );
	wp_send_json_success( array( 'items' => wp_easycart_admin_user_role_editor_v2::user_rows( $role->role_label, $per, ( $page - 1 ) * $per, $q ), 'total' => $total, 'page' => $page, 'pages' => $pages, 'per' => $per, 'q' => $q ) );
}

/** Users NOT in this role, to assign ( name / email search, 12 at a time ). */
add_action( 'wp_ajax_ecv2_role_user_search', 'ecv2_role_user_search' );
function ecv2_role_user_search() {
	ecv2_role_guard(); global $wpdb;
	$role = ecv2_role_row( isset( $_POST['role_id'] ) ? (int) $_POST['role_id'] : 0 ); if ( ! $role ) { wp_send_json_error( array( 'message' => __( 'Role not found.', 'wp-easycart' ) ) ); }
	$q = isset( $_POST['q'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['q'] ) ) ) : ''; if ( strlen( $q ) < 2 ) { wp_send_json_success( array( 'items' => array() ) ); }
	$like = '%' . $wpdb->esc_like( $q ) . '%'; $out = array();
	foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT user_id, email, first_name, last_name, user_level FROM ec_user WHERE user_level != %s AND ( email LIKE %s OR first_name LIKE %s OR last_name LIKE %s OR CONCAT( first_name, ' ', last_name ) LIKE %s ) ORDER BY last_name, first_name, email LIMIT 12", $role->role_label, $like, $like, $like, $like ) ) as $u ) {
		$out[] = array( 'id' => (int) $u->user_id, 'email' => $u->email, 'name' => trim( wp_unslash( $u->first_name . ' ' . $u->last_name ) ), 'role' => wp_unslash( (string) $u->user_level ) );
	}
	wp_send_json_success( array( 'items' => $out, 'grants_admin' => (bool) $role->admin_access ) );
}

/** Assign users ( any current role ) to this role. */
add_action( 'wp_ajax_ecv2_role_assign', 'ecv2_role_assign' );
function ecv2_role_assign() {
	ecv2_role_guard(); global $wpdb;
	$role = ecv2_role_row( isset( $_POST['role_id'] ) ? (int) $_POST['role_id'] : 0 ); if ( ! $role ) { wp_send_json_error( array( 'message' => __( 'Role not found.', 'wp-easycart' ) ) ); }
	$ids = isset( $_POST['user_ids'] ) && is_array( $_POST['user_ids'] ) ? array_slice( array_filter( array_map( 'intval', $_POST['user_ids'] ) ), 0, 500 ) : array();
	if ( ! $ids ) { wp_send_json_error( array( 'message' => __( 'Choose at least one user.', 'wp-easycart' ) ) ); }
	$n = (int) $wpdb->query( $wpdb->prepare( 'UPDATE ec_user SET user_level = %s WHERE user_id IN ( ' . implode( ',', $ids ) . ' ) AND user_level != %s', $role->role_label, $role->role_label ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $ids is an intval()-mapped list built above.
	foreach ( $ids as $uid ) { do_action( 'wpeasycart_user_updated', $uid ); }
	wp_cache_flush();
	wp_send_json_success( array( 'assigned' => $n, 'total' => wp_easycart_admin_user_role_editor_v2::user_count( $role->role_label ), 'message' => sprintf( _n( '%1$d user now has the %2$s role.', '%1$d users now have the %2$s role.', $n, 'wp-easycart' ), $n, wp_unslash( $role->role_label ) ) ) );
}

add_action( 'wp_ajax_ecv2_role_move_users', 'ecv2_role_move_users' );
function ecv2_role_move_users() {
	ecv2_role_guard(); global $wpdb;
	$from = ecv2_role_row( isset( $_POST['role_id'] ) ? (int) $_POST['role_id'] : 0 ); $to = ecv2_role_row( isset( $_POST['to_role_id'] ) ? (int) $_POST['to_role_id'] : 0 );
	if ( ! $from || ! $to ) { wp_send_json_error( array( 'message' => __( 'Role not found.', 'wp-easycart' ) ) ); }
	$ids = isset( $_POST['user_ids'] ) && is_array( $_POST['user_ids'] ) ? array_map( 'intval', $_POST['user_ids'] ) : array();
	$n = $ids ? (int) $wpdb->query( $wpdb->prepare( 'UPDATE ec_user SET user_level = %s WHERE user_level = %s AND user_id IN ( ' . implode( ',', $ids ) . ' )', $to->role_label, $from->role_label ) ) : (int) $wpdb->query( $wpdb->prepare( 'UPDATE ec_user SET user_level = %s WHERE user_level = %s', $to->role_label, $from->role_label ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $ids is an intval()-mapped list built above.
	foreach ( $ids as $uid ) { do_action( 'wpeasycart_user_updated', $uid ); } wp_cache_flush();
	wp_send_json_success( array( 'moved' => $n, 'total' => wp_easycart_admin_user_role_editor_v2::user_count( $from->role_label ), 'message' => sprintf( _n( 'Moved %1$d user to %2$s.', 'Moved %1$d users to %2$s.', $n, 'wp-easycart' ), $n, wp_unslash( $to->role_label ) ) ) );
}

/* ---- delete: impact → strategy → 15-minute undo ---- */
add_action( 'wp_ajax_ecv2_role_delete_impact', 'ecv2_role_delete_impact' );
function ecv2_role_delete_impact() {
	ecv2_role_guard(); global $wpdb;
	$id = isset( $_POST['role_id'] ) ? (int) $_POST['role_id'] : 0; $role = ecv2_role_row( $id );
	if ( ! $role ) { wp_send_json_error( array( 'message' => __( 'Role not found.', 'wp-easycart' ) ) ); }
	if ( ecv2_role_is_master( $id ) ) { wp_send_json_error( array( 'message' => __( 'Built-in roles cannot be deleted.', 'wp-easycart' ) ) ); }
	$users = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_user WHERE user_level = %s', $role->role_label ) );
	$prices = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_roleprice WHERE role_label = %s', $role->role_label ) );
	$targets = $wpdb->get_results( $wpdb->prepare( 'SELECT role_id AS value, role_label AS label FROM ec_role WHERE role_id != %d ORDER BY role_id', $id ) );
	wp_send_json_success( array( 'name' => wp_unslash( $role->role_label ), 'users' => $users, 'prices' => $prices, 'targets' => $targets, 'default_target' => 2 ) );
}

add_action( 'wp_ajax_ecv2_role_delete', 'ecv2_role_delete' );
function ecv2_role_delete() {
	ecv2_role_guard(); global $wpdb;
	$id = isset( $_POST['role_id'] ) ? (int) $_POST['role_id'] : 0; $role = ecv2_role_row( $id );
	if ( ! $role ) { wp_send_json_error( array( 'message' => __( 'Role not found.', 'wp-easycart' ) ) ); }
	if ( ecv2_role_is_master( $id ) ) { wp_send_json_error( array( 'message' => __( 'Built-in roles cannot be deleted.', 'wp-easycart' ) ) ); }
	$to = ecv2_role_row( isset( $_POST['to_role_id'] ) ? (int) $_POST['to_role_id'] : 2 ); if ( ! $to ) { $to = ecv2_role_row( 2 ); }
	$snap = array( 'role' => (array) $role, 'prices' => $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ec_roleprice WHERE role_label = %s', $role->role_label ), ARRAY_A ), 'access' => $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ec_roleaccess WHERE role_label = %s', $role->role_label ), ARRAY_A ), 'users' => array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT user_id FROM ec_user WHERE user_level = %s', $role->role_label ) ) ), 'moved_to' => $to ? $to->role_label : 'shopper' );
	do_action( 'wpeasycart_user_role_deleting', $id );
	$wpdb->query( $wpdb->prepare( 'UPDATE ec_user SET user_level = %s WHERE user_level = %s', $snap['moved_to'], $role->role_label ) );
	$wpdb->delete( 'ec_roleprice', array( 'role_label' => $role->role_label ) );
	$wpdb->delete( 'ec_roleaccess', array( 'role_label' => $role->role_label ) );
	$wpdb->delete( 'ec_role', array( 'role_id' => $id ) );
	do_action( 'wpeasycart_user_role_deleted', $id );
	wp_cache_flush();
	$undo = 'role_' . time() . '_' . wp_rand( 100, 999 ); set_transient( 'ec_role_undo_' . $undo, $snap, 15 * MINUTE_IN_SECONDS );
	wp_send_json_success( array( 'undo' => $undo, 'message' => sprintf( __( 'Deleted “%1$s” — %2$d users moved to “%3$s”, %4$d role prices removed.', 'wp-easycart' ), wp_unslash( $role->role_label ), count( $snap['users'] ), wp_unslash( $snap['moved_to'] ), count( $snap['prices'] ) ) ) );
}

add_action( 'wp_ajax_ecv2_role_restore', 'ecv2_role_restore' );
function ecv2_role_restore() {
	ecv2_role_guard(); global $wpdb;
	$key = isset( $_POST['undo'] ) ? preg_replace( '/[^a-z0-9_]/', '', (string) $_POST['undo'] ) : '';
	$snap = get_transient( 'ec_role_undo_' . $key );
	if ( ! $snap || ! is_array( $snap ) ) { wp_send_json_error( array( 'message' => __( 'This deletion can no longer be undone.', 'wp-easycart' ) ) ); }
	$wpdb->replace( 'ec_role', $snap['role'] );
	foreach ( $snap['prices'] as $p ) { $wpdb->replace( 'ec_roleprice', $p ); }
	foreach ( $snap['access'] as $a ) { $wpdb->replace( 'ec_roleaccess', $a ); }
	if ( $snap['users'] ) { $wpdb->query( $wpdb->prepare( 'UPDATE ec_user SET user_level = %s WHERE user_id IN ( ' . implode( ',', array_map( 'intval', $snap['users'] ) ) . ' )', $snap['role']['role_label'] ) ); } // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- IN list is an implode of intval() ids.
	delete_transient( 'ec_role_undo_' . $key );
	wp_cache_flush();
	wp_send_json_success( array( 'message' => sprintf( __( 'Restored “%s”.', 'wp-easycart' ), wp_unslash( $snap['role']['role_label'] ) ) ) );
}
