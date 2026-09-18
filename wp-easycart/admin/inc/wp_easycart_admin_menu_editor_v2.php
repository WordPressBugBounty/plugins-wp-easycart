<?php
/**
 * WP EasyCart Admin — Menu editor ( V2 ), one class for all three levels.
 *
 * URL: subpage=menus&ec_admin_form_action=edit&level=N&menu_id=ID
 * AJAX: ecv2_menu_save, ecv2_menu_products.
 *
 * @since 5.x.x
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_menu_editor_v2' ) ) :

	class wp_easycart_admin_menu_editor_v2 {

		const NONCE = 'wp-easycart-menuv2';

		public $level = 1;
		public $menu;
		public $post;
		public $parent;         /* row of the level above, if any */
		public $grandparent;
		public $children = array();
		public $products = array();
		public $product_total = 0;
		public $parent_options = array();
		public $placements = array();   /* every legal parent incl. top level, for the editor's Parent select */
		public $docs_link = '';
		public $yoast = false;

		public static function table( $level ) { return 'ec_menulevel' . (int) $level; }
		public static function key( $level ) { return 'menulevel' . (int) $level . '_id'; }
		public static function level_label( $level ) {
			$l = array( 1 => __( 'Menu', 'wp-easycart' ), 2 => __( 'Sub-menu', 'wp-easycart' ), 3 => __( 'Sub-sub-menu', 'wp-easycart' ) );
			return isset( $l[ $level ] ) ? $l[ $level ] : __( 'Menu', 'wp-easycart' );
		}

		public function __construct() {
			$this->docs_link = wp_easycart_admin()->helpsystem->print_docs_url( 'products', 'menus', 'menus' );
			$this->yoast = function_exists( 'is_plugin_active' ) && ( is_plugin_active( 'wordpress-seo/wp-seo.php' ) || is_plugin_active( 'wordpress-seo-premium/wp-seo-premium.php' ) );
		}

		public function load( $level, $id ) {
			global $wpdb;
			$level = max( 1, min( 3, (int) $level ) );
			$this->level = $level;
			$this->menu = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( $level ) . ' WHERE ' . self::key( $level ) . ' = %d', $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table()/key() build identifiers from (int) $level, clamped to 1..3 above.
			if ( ! $this->menu ) { return false; }
			$this->menu->menu_id = (int) $id;
			$this->post = $this->menu->post_id ? get_post( (int) $this->menu->post_id ) : null;
			if ( $level > 1 ) {
				$pk = self::key( $level - 1 );
				$this->parent = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( $level - 1 ) . ' WHERE ' . $pk . ' = %d', (int) $this->menu->{ $pk } ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $pk and table() are (int)-derived identifiers.
				if ( $this->parent && 3 === $level ) {
					$this->grandparent = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_menulevel1 WHERE menulevel1_id = %d', (int) $this->parent->menulevel1_id ) );
				}
				/* parent_options was a second copy of the whole parent level; the placements list below is the only one the template uses. */
				$this->parent_options = array();
			}
			/* Every possible placement: top level, or under any level-1 / level-2 menu that isn't this menu or below it,
			   and that leaves room for this menu's own depth ( three levels total ). Capped at 500 entries ( plus the
			   current parent, always kept ); a disabled note row says when the tree is larger than that. @since 6.0.0 cap. */
			$desc = class_exists( 'wp_easycart_admin_safe_delete' ) ? wp_easycart_admin_safe_delete()->menu_descendants( $level, (int) $id ) : array();
			$deepest = $level; $blocked = array();
			foreach ( $desc as $d ) { $deepest = max( $deepest, (int) $d['level'] ); $blocked[ $d['level'] . ':' . $d['id'] ] = true; }
			$depth = $deepest - $level;
			$this->placements = array( array( 'value' => '', 'label' => __( '— Top level —', 'wp-easycart' ), 'disabled' => false, 'current' => 1 === $level ) );
			$current_key = $level > 1 ? ( $level - 1 ) . ':' . (int) $this->menu->{ self::key( $level - 1 ) } : '';
			$cap = 500; $listed = 0; $current_listed = ( '' === $current_key ); $capped = false;
			foreach ( wp_easycart_admin_menu_table::parent_options( 2, $cap + 1 ) as $o ) {
				if ( $listed >= $cap ) { $capped = true; break; }
				$ol = (int) $o['level']; $new_level = $ol + 1;
				$is_current = ( $o['value'] === $current_key );
				if ( $is_current ) { $current_listed = true; }
				$this->placements[] = array( 'value' => $o['value'], 'label' => $o['label'], 'disabled' => isset( $blocked[ $o['value'] ] ) || $new_level + $depth > 3, 'current' => $is_current );
				$listed++;
			}
			if ( ! $current_listed && $this->parent ) {
				$this->placements[] = array( 'value' => $current_key, 'label' => ( 2 === $level - 1 ? '— ' : '' ) . $this->parent->name, 'disabled' => false, 'current' => true );
			}
			if ( $capped ) {
				/* the template appends "(not available)" to disabled rows, which reads fine after this note */
				$this->placements[] = array( 'value' => '', 'label' => __( 'Only the first 500 menus are listed here; drag rows on the Menus list to nest under another menu', 'wp-easycart' ), 'disabled' => true, 'current' => false );
			}
			if ( $level < 3 ) {
				$ck = self::key( $level + 1 );
				$this->children = $wpdb->get_results( $wpdb->prepare( 'SELECT c.*, c.' . $ck . ' AS menu_id FROM ' . self::table( $level + 1 ) . ' c WHERE c.' . self::key( $level ) . ' = %d ORDER BY c.menu_order, c.name', $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $ck/table()/key() are (int)-derived identifiers; $id is a %d placeholder.
			}
			$this->product_total = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_product WHERE ' . $this->products_where() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- products_where() returns a $wpdb->prepare()'d fragment.
			$this->products = $this->product_rows( 30 );
			return true;
		}

		/**
		 * Products that have this menu in any of their three menu locations.
		 * ec_product columns are menulevel{PATH}_id_{LEVEL}: PATH is the product's location slot ( 1..3 ),
		 * LEVEL the menu depth ( 1..3 ) — the same convention as the storefront filter ( ec_filter.php ) and
		 * the product editor. So a menu at level L with id X is matched on menulevel1_id_L, menulevel2_id_L
		 * and menulevel3_id_L.
		 */
		public function products_where() {
			global $wpdb;
			$l = (int) $this->level;
			return $wpdb->prepare( "( menulevel1_id_{$l} = %d OR menulevel2_id_{$l} = %d OR menulevel3_id_{$l} = %d )", $this->menu->menu_id, $this->menu->menu_id, $this->menu->menu_id ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $l is the (int) level, clamped to 1..3 in load().
		}

		public function product_rows( $limit, $offset = 0 ) {
			global $wpdb;
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT product_id, title, model_number, activate_in_store, ' . wp_easycart_admin_catalog_v2_thumb_select( 'ec_product' ) . ' FROM ec_product WHERE ' . $this->products_where() . ' ORDER BY title LIMIT %d OFFSET %d', $limit, $offset ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- thumb_select() is a static column list; products_where() is already prepared.
			$out = array();
			foreach ( $rows as $r ) {
				$out[] = array( 'id' => (int) $r->product_id, 'title' => wp_unslash( $r->title ), 'sku' => $r->model_number, 'image' => wp_easycart_admin_catalog_v2_product_thumb( $r ), 'active' => (bool) $r->activate_in_store, 'edit_url' => admin_url( 'admin.php?page=wp-easycart-products&subpage=products&ec_admin_form_action=edit&product_id=' . (int) $r->product_id ) );
			}
			return $out;
		}

		/**
		 * The ( level 1, level 2, level 3 ) ids a product location holds when it is assigned to this menu:
		 * a top-level menu is ( id, 0, 0 ), a sub-menu ( parent, id, 0 ), a sub-sub-menu ( grandparent,
		 * parent, id ). Location P stores them as menulevel{P}_id_1 / _id_2 / _id_3. Returns null when the
		 * menu is orphaned ( its parent row is gone ).
		 *
		 * @since 6.0.0
		 */
		public function product_path() {
			$path = array( 1 => 0, 2 => 0, 3 => 0 );
			$path[ $this->level ] = (int) $this->menu->menu_id;
			if ( $this->level >= 2 ) {
				if ( ! $this->parent ) { return null; }
				$path[ $this->level - 1 ] = (int) $this->parent->{ self::key( $this->level - 1 ) };
			}
			if ( 3 === $this->level ) {
				if ( ! $this->grandparent ) { return null; }
				$path[1] = (int) $this->grandparent->menulevel1_id;
			}
			return $path;
		}

		/** One product row for the Products card, with the per-row remove ( × ). Mirrored by catalog-editor-lite-v2.js row_html(). @since 6.0.0 */
		public static function product_row_html( $u ) {
			return '<div class="ecos-u" data-product-id="' . (int) $u['id'] . '"><span class="ecv2-thumb">' . ( $u['image'] ? '<img src="' . esc_url( $u['image'] ) . '" alt="" loading="lazy">' : '<span class="dashicons dashicons-format-image"></span>' ) . '</span><div class="ecos-u-main"><a class="ecv2-link-primary" href="' . esc_url( $u['edit_url'] ) . '">' . esc_html( $u['title'] ) . '</a><span class="ecv2-sub">' . esc_html( $u['sku'] ) . '</span></div><span class="ecv2-chip ' . ( $u['active'] ? 'ecv2-chip-green' : 'ecv2-chip-gray' ) . '">' . ( $u['active'] ? esc_html__( 'Active', 'wp-easycart' ) : esc_html__( 'Inactive', 'wp-easycart' ) ) . '</span><a class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" href="' . esc_url( $u['edit_url'] ) . '">' . esc_html__( 'Open', 'wp-easycart' ) . '</a><button type="button" class="eclite-product-x" data-id="' . (int) $u['id'] . '" title="' . esc_attr__( 'Remove from this menu', 'wp-easycart' ) . '" aria-label="' . esc_attr__( 'Remove from this menu', 'wp-easycart' ) . '">&times;</button></div>';
		}

		public function permalink() { return $this->post ? get_permalink( $this->post ) : ''; }

		public function slug_prefix() {
			$link = $this->permalink();
			if ( ! $link ) { return '/store/'; }
			$path = rtrim( (string) wp_parse_url( $link, PHP_URL_PATH ), '/' );
			$pos = strrpos( $path, '/' );
			return false === $pos ? '/' : substr( $path, 0, $pos + 1 );
		}

		public function path() {
			$p = array();
			if ( $this->grandparent ) { $p[] = $this->grandparent->name; }
			if ( $this->parent ) { $p[] = $this->parent->name; }
			return $p;
		}

		public function health() {
			$m = $this->menu;
			return array(
				array( 'ok' => $this->product_total > 0 || count( $this->children ) > 0, 'label' => $this->product_total ? __( 'Has products', 'wp-easycart' ) : ( count( $this->children ) ? __( 'Has sub-menus', 'wp-easycart' ) : __( 'Empty — nothing to show shoppers', 'wp-easycart' ) ) ),
				array( 'ok' => '' !== trim( (string) $m->banner_image ), 'label' => '' !== trim( (string) $m->banner_image ) ? __( 'Banner image set', 'wp-easycart' ) : __( 'No banner image', 'wp-easycart' ) ),
				array( 'ok' => $this->post && '' !== trim( (string) $this->post->post_excerpt ), 'label' => $this->post && '' !== trim( (string) $this->post->post_excerpt ) ? __( 'Has a search excerpt', 'wp-easycart' ) : __( 'No search excerpt (helps SEO)', 'wp-easycart' ) ),
				array( 'ok' => (bool) $this->post, 'label' => $this->post ? __( 'Menu page linked', 'wp-easycart' ) : __( 'Menu page missing — save to recreate', 'wp-easycart' ) ),
			);
		}

		public function output() {
			$level = isset( $_GET['level'] ) ? (int) $_GET['level'] : 1;
			$id = isset( $_GET['menu_id'] ) ? (int) $_GET['menu_id'] : 0;
			if ( ! $this->load( $level, $id ) ) {
				echo '<div class="ecv2-wrap"><div class="ecv2-empty-state">' . esc_html__( 'That menu no longer exists.', 'wp-easycart' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=wp-easycart-products&subpage=menus' ) ) . '">' . esc_html__( 'Back to menus', 'wp-easycart' ) . '</a></div></div>';
				return;
			}
			include( EC_PLUGIN_DIRECTORY . '/admin/template/products/menus/menu-editor-v2.php' );
		}

		public function js_data() {
			return array(
				'kind' => 'menu', 'level' => $this->level, 'id' => $this->menu->menu_id, 'delete_type' => 'menu' . $this->level,
				'slug' => $this->post ? $this->post->post_name : '', 'redirects' => class_exists( 'ec_url_redirects' ),
				'nonce' => wp_create_nonce( self::NONCE ), 'save_action' => 'ecv2_menu_save', 'more_action' => 'ecv2_menu_products',
				/* Products card add / remove ( @since 6.0.0 ); the search is ec_admin_ajax_ecv2_product_search ( capability-gated, no nonce ). */
				'add_action' => 'ecv2_menu_add_product', 'remove_action' => 'ecv2_menu_remove_product', 'search_action' => 'ec_admin_ajax_ecv2_product_search',
				'list_url' => admin_url( 'admin.php?page=wp-easycart-products&subpage=menus' ), 'product_total' => $this->product_total, 'shown' => count( $this->products ),
			);
		}
	}

endif;

add_action( 'wp_ajax_ecv2_menu_save', 'ecv2_menu_save' );
function ecv2_menu_save() {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) { wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) ); }
	check_ajax_referer( wp_easycart_admin_menu_editor_v2::NONCE, 'nonce' );
	global $wpdb;
	$level = isset( $_POST['level'] ) ? max( 1, min( 3, (int) $_POST['level'] ) ) : 1;
	$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
	$table = wp_easycart_admin_menu_editor_v2::table( $level ); $key = wp_easycart_admin_menu_editor_v2::key( $level );
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE $key = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table/$key come from table()/key() with $level clamped to 1..3.
	if ( ! $row ) { wp_send_json_error( array( 'message' => __( 'Menu not found.', 'wp-easycart' ) ) ); }
	$d = json_decode( wp_unslash( isset( $_POST['data'] ) ? $_POST['data'] : '{}' ), true );
	if ( ! is_array( $d ) ) { wp_send_json_error( array( 'message' => __( 'Invalid payload.', 'wp-easycart' ) ) ); }
	$name = isset( $d['name'] ) ? sanitize_text_field( $d['name'] ) : '';
	if ( '' === $name ) { wp_send_json_error( array( 'message' => __( 'A name is required.', 'wp-easycart' ) ) ); }

	$update = array(
		'name' => $name,
		'menu_order' => isset( $d['menu_order'] ) ? (int) $d['menu_order'] : (int) $row->menu_order,
		'banner_image' => isset( $d['banner_image'] ) ? sanitize_text_field( $d['banner_image'] ) : (string) $row->banner_image,
		'seo_keywords' => isset( $d['seo_keywords'] ) ? sanitize_text_field( $d['seo_keywords'] ) : (string) $row->seo_keywords,
		'seo_description' => isset( $d['seo_description'] ) ? sanitize_textarea_field( $d['seo_description'] ) : (string) $row->seo_description,
	);
	/* Placement ( any level ): "" = top level, "1:ID" / "2:ID" = under that menu. A level change moves the row between
	   tables ( new id ) — we relocate first, then continue saving against the new row and redirect the editor. */
	$relocated = null;
	if ( isset( $d['placement'] ) ) {
		$pl = ecv2_menu_parse_key( (string) $d['placement'] ); $target_level = $pl ? $pl['level'] + 1 : 1;
		$cur_parent = $level > 1 ? (int) $row->{ wp_easycart_admin_menu_editor_v2::key( $level - 1 ) } : 0;
		if ( $target_level !== $level || ( $pl && $pl['id'] !== $cur_parent ) ) {
			$r = ecv2_menu_relocate( $level, $id, $pl ? $pl : null );
			if ( is_wp_error( $r ) ) { wp_send_json_error( array( 'message' => $r->get_error_message(), 'field' => 'placement' ) ); }
			if ( $r['level_changed'] ) { $relocated = $r; $level = $r['level']; $id = $r['id']; $table = wp_easycart_admin_menu_editor_v2::table( $level ); $key = wp_easycart_admin_menu_editor_v2::key( $level ); $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE $key = %d", $id ) ); } // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table/$key rebuilt via table()/key() from the (int) level returned by ecv2_menu_relocate().
			else { $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE $key = %d", $id ) ); } // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table/$key come from table()/key() with $level clamped to 1..3.
		}
		unset( $d['parent_id'] );
	}
	/* Legacy parent_id ( levels 2 & 3 ): validate and rewrite product paths */
	if ( $level > 1 && isset( $d['parent_id'] ) ) {
		$pk = wp_easycart_admin_menu_editor_v2::key( $level - 1 );
		$new_parent = (int) $d['parent_id'];
		if ( $new_parent !== (int) $row->{ $pk } ) {
			if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT ' . $pk . ' FROM ' . wp_easycart_admin_menu_editor_v2::table( $level - 1 ) . " WHERE $pk = %d", $new_parent ) ) ) { wp_send_json_error( array( 'message' => __( 'Parent menu not found.', 'wp-easycart' ) ) ); } // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $pk and table() are (int)-derived identifiers; $new_parent is a %d placeholder.
			$update[ $pk ] = $new_parent;
			/* Product locations: ec_product.menulevel{PATH}_id_{LEVEL}. For every location P ( 1..3 ) whose level-$dl column names a
			   moved node, point its level-( $level - 1 ) column at the new parent ( and, for a sub-sub-menu, its level-1 column at
			   the new grandparent ). */
			$parent_level = (int) $level - 1;
			$l1 = 3 === $level ? (int) $wpdb->get_var( $wpdb->prepare( 'SELECT menulevel1_id FROM ec_menulevel2 WHERE menulevel2_id = %d', $new_parent ) ) : 0;
			foreach ( wp_easycart_admin_safe_delete()->menu_descendants( $level, $id ) as $dsc ) {
				$dl = (int) $dsc['level'];
				for ( $n = 1; $n <= 3; $n++ ) {
					$wpdb->query( $wpdb->prepare( "UPDATE ec_product SET menulevel{$n}_id_{$parent_level} = %d WHERE menulevel{$n}_id_{$dl} = %d", $new_parent, (int) $dsc['id'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $n is the loop counter 1..3; $parent_level / $dl are (int) levels 1..3.
					if ( 3 === $level ) {
						$wpdb->query( $wpdb->prepare( "UPDATE ec_product SET menulevel{$n}_id_1 = %d WHERE menulevel{$n}_id_{$dl} = %d", $l1, (int) $dsc['id'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $n is the loop counter 1..3; $dl is an (int) level 1..3.
					}
				}
			}
		}
	}
	$wpdb->update( $table, $update, array( $key => $id ) );

	/* Post */
	$old_link = $row->post_id ? get_permalink( (int) $row->post_id ) : '';
	$post = array( 'post_content' => '[ec_store ' . ecv2_menu_shortcode_attr( $level ) . '="' . $id . '"]', 'post_status' => 'publish', 'post_title' => wp_easycart_language()->convert_text( $name ), 'post_type' => 'ec_store', 'post_excerpt' => isset( $d['excerpt'] ) ? sanitize_text_field( $d['excerpt'] ) : '' );
	$slug = isset( $d['slug'] ) ? sanitize_title( $d['slug'] ) : '';
	if ( '' !== $slug ) { $post['post_name'] = $slug; }
	$post_id = wp_easycart_post_sync()->update( 'menulevel' . $level, $id, (int) $row->post_id, $post );
	if ( $post_id && $post_id != $row->post_id ) { wp_set_post_tags( $post_id, array( 'menu' ), true ); $wpdb->update( $table, array( 'post_id' => (int) $post_id ), array( $key => $id ) ); }
	if ( $post_id ) {
		$fi = isset( $d['featured_image'] ) ? (int) $d['featured_image'] : 0;
		if ( $fi ) { set_post_thumbnail( $post_id, $fi ); } else { delete_post_thumbnail( $post_id ); }
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->posts} SET guid = %s WHERE ID = %d", get_permalink( $post_id ), $post_id ) );
	}
	$new_link = $post_id ? get_permalink( $post_id ) : '';
	$redirect_added = false;
	if ( ! empty( $d['redirect'] ) && $old_link && $new_link && class_exists( 'ec_url_redirects' ) && ec_url_redirects::key( $old_link ) !== ec_url_redirects::key( $new_link ) ) {
		$redirect_added = ec_url_redirects::add( $old_link, $new_link, 'menu-slug:' . $level . ':' . $id );
	}
	do_action( 'wpeasycart_menu_updated', $id, $level );
	wp_cache_delete( 'wpeasycart-settings', 'wpeasycart-settings' );

	$ed = new wp_easycart_admin_menu_editor_v2(); $ed->load( $level, $id );
	wp_send_json_success( array( 'message' => $relocated ? __( 'Saved — menu moved', 'wp-easycart' ) : __( 'Saved', 'wp-easycart' ), 'redirect' => $relocated ? wp_easycart_admin_menu_table::editor_url( $level, $id ) : '', 'permalink' => $new_link, 'slug' => $ed->post ? $ed->post->post_name : '', 'slug_prefix' => $ed->slug_prefix(), 'redirect_added' => $redirect_added, 'health' => $ed->health(), 'path' => $ed->path() ) );
}

add_action( 'wp_ajax_ecv2_menu_products', 'ecv2_menu_products' );
function ecv2_menu_products() {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) { wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) ); }
	check_ajax_referer( wp_easycart_admin_menu_editor_v2::NONCE, 'nonce' );
	$ed = new wp_easycart_admin_menu_editor_v2();
	if ( ! $ed->load( isset( $_POST['level'] ) ? (int) $_POST['level'] : 1, isset( $_POST['id'] ) ? (int) $_POST['id'] : 0 ) ) { wp_send_json_error( array( 'message' => __( 'Not found.', 'wp-easycart' ) ) ); }
	wp_send_json_success( array( 'items' => $ed->product_rows( 30, isset( $_POST['offset'] ) ? (int) $_POST['offset'] : 0 ) ) );
}

/**
 * Products card, "Add product": put this menu in the product's first empty menu-path slot.
 * POST: nonce ( wp-easycart-menuv2 ), level, id ( the menu ), product_id.
 * A product has three locations, stored as ec_product.menulevel{PATH}_id_{LEVEL} ( PATH = location 1..3,
 * LEVEL = menu depth 1..3, as the storefront filter and the product editor read them ). Location P is
 * empty when menulevel{P}_id_1 = 0; it receives menulevel{P}_id_1 = l1, _id_2 = l2, _id_3 = l3 from
 * product_path(). Errors when the product is already in this menu ( its level-L column in any location
 * equals this id ) or every location is used. Answers the refreshed list so the card can redraw.
 *
 * @since 6.0.0
 */
add_action( 'wp_ajax_ecv2_menu_add_product', 'ecv2_menu_add_product' );
function ecv2_menu_add_product() {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) { wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) ); }
	check_ajax_referer( wp_easycart_admin_menu_editor_v2::NONCE, 'nonce' );
	global $wpdb;
	$ed = new wp_easycart_admin_menu_editor_v2();
	if ( ! $ed->load( isset( $_POST['level'] ) ? (int) $_POST['level'] : 1, isset( $_POST['id'] ) ? (int) $_POST['id'] : 0 ) ) { wp_send_json_error( array( 'message' => __( 'Menu not found.', 'wp-easycart' ) ) ); }
	$product_id = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
	$p = $wpdb->get_row( $wpdb->prepare( 'SELECT product_id, title, model_number, menulevel1_id_1, menulevel1_id_2, menulevel1_id_3, menulevel2_id_1, menulevel2_id_2, menulevel2_id_3, menulevel3_id_1, menulevel3_id_2, menulevel3_id_3 FROM ec_product WHERE product_id = %d', $product_id ) );
	if ( ! $p ) { wp_send_json_error( array( 'message' => __( 'That product no longer exists.', 'wp-easycart' ) ) ); }
	$path = $ed->product_path();
	if ( ! $path ) { wp_send_json_error( array( 'message' => __( 'This menu is not attached to a parent menu, so it cannot be assigned. Move it under a menu first.', 'wp-easycart' ) ) ); }
	$l = (int) $ed->level;
	$slot = 0;
	for ( $n = 1; $n <= 3; $n++ ) {
		if ( (int) $p->{ 'menulevel' . $n . '_id_' . $l } === (int) $ed->menu->menu_id ) {
			wp_send_json_error( array( 'message' => sprintf( __( '“%s” is already in this menu.', 'wp-easycart' ), wp_unslash( $p->title ) ) ) );
		}
		if ( ! $slot && 0 === (int) $p->{ 'menulevel' . $n . '_id_1' } ) { $slot = $n; }
	}
	if ( ! $slot ) {
		wp_send_json_error( array( 'message' => sprintf( __( '“%s” already uses all three of its menu paths. Free one from the product editor first.', 'wp-easycart' ), wp_unslash( $p->title ) ) ) );
	}
	$wpdb->update( 'ec_product', array( 'menulevel' . $slot . '_id_1' => $path[1], 'menulevel' . $slot . '_id_2' => $path[2], 'menulevel' . $slot . '_id_3' => $path[3] ), array( 'product_id' => $product_id ) );
	wp_cache_delete( 'wpeasycart-product-only-' . $p->model_number, 'wpeasycart-product-list' );
	do_action( 'wpeasycart_product_updated', $product_id, $p->model_number );

	$ed->load( $ed->level, $ed->menu->menu_id );
	wp_send_json_success( array( 'message' => sprintf( __( 'Added “%s”', 'wp-easycart' ), wp_unslash( $p->title ) ), 'items' => $ed->products, 'total' => $ed->product_total, 'health' => $ed->health() ) );
}

/**
 * Products card, row ×: clear every product location that runs through this menu — each location P
 * ( 1..3 ) whose menulevel{P}_id_L equals this id has its menulevel{P}_id_1, _id_2 and _id_3 zeroed.
 * POST: nonce ( wp-easycart-menuv2 ), level, id ( the menu ), product_id.
 *
 * @since 6.0.0
 */
add_action( 'wp_ajax_ecv2_menu_remove_product', 'ecv2_menu_remove_product' );
function ecv2_menu_remove_product() {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) { wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) ); }
	check_ajax_referer( wp_easycart_admin_menu_editor_v2::NONCE, 'nonce' );
	global $wpdb;
	$ed = new wp_easycart_admin_menu_editor_v2();
	if ( ! $ed->load( isset( $_POST['level'] ) ? (int) $_POST['level'] : 1, isset( $_POST['id'] ) ? (int) $_POST['id'] : 0 ) ) { wp_send_json_error( array( 'message' => __( 'Menu not found.', 'wp-easycart' ) ) ); }
	$product_id = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
	$p = $wpdb->get_row( $wpdb->prepare( 'SELECT product_id, title, model_number, menulevel1_id_1, menulevel1_id_2, menulevel1_id_3, menulevel2_id_1, menulevel2_id_2, menulevel2_id_3, menulevel3_id_1, menulevel3_id_2, menulevel3_id_3 FROM ec_product WHERE product_id = %d', $product_id ) );
	if ( ! $p ) { wp_send_json_error( array( 'message' => __( 'That product no longer exists.', 'wp-easycart' ) ) ); }
	$l = (int) $ed->level;
	$clear = array();
	for ( $n = 1; $n <= 3; $n++ ) {
		if ( (int) $p->{ 'menulevel' . $n . '_id_' . $l } === (int) $ed->menu->menu_id ) {
			$clear[ 'menulevel' . $n . '_id_1' ] = 0; $clear[ 'menulevel' . $n . '_id_2' ] = 0; $clear[ 'menulevel' . $n . '_id_3' ] = 0;
		}
	}
	if ( empty( $clear ) ) { wp_send_json_error( array( 'message' => sprintf( __( '“%s” is not in this menu.', 'wp-easycart' ), wp_unslash( $p->title ) ) ) ); }
	$wpdb->update( 'ec_product', $clear, array( 'product_id' => $product_id ) );
	wp_cache_delete( 'wpeasycart-product-only-' . $p->model_number, 'wpeasycart-product-list' );
	do_action( 'wpeasycart_product_updated', $product_id, $p->model_number );

	$ed->load( $ed->level, $ed->menu->menu_id );
	wp_send_json_success( array( 'message' => sprintf( __( 'Removed “%s”', 'wp-easycart' ), wp_unslash( $p->title ) ), 'items' => $ed->products, 'total' => $ed->product_total, 'health' => $ed->health() ) );
}
