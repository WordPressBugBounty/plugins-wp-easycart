<?php
/**
 * WP EasyCart Admin — Menus list ( V2 ).
 *
 * One screen for all three menu levels ( ec_menulevel1 → 2 → 3 ), replacing
 * the three legacy list pages. Top-level menus are paged; their sub-menus
 * render beneath, collapsible. Drag to reorder among siblings, or drop a
 * sub-menu onto a menu of the level above to re-parent it ( product paths are
 * rewritten ). Search / filters switch to a flat list with the parent path.
 *
 * Row ids are level-qualified ( "1:12", "2:40", "3:7" ) because the three
 * tables have overlapping ids.
 *
 * AJAX: ecv2_menu_move, ecv2_menu_create, ecv2_menu_bulk, ecv2_menu_inline_update.
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

if ( ! class_exists( 'wp_easycart_admin_menu_table' ) ) :

	class wp_easycart_admin_menu_table extends wp_easycart_admin_table_v2 {

		private $tree_mode = true;
		private $rows = array();       /* "L:id" => row ( all levels ) */
		private $children = array();   /* "L:id" => [ "L+1:id" ] */
		private $product_counts = array();

		public static function editor_url( $level, $id ) {
			$id = ( '{id}' === $id ) ? '{id}' : (int) $id;
			return admin_url( 'admin.php?page=wp-easycart-products&subpage=menus&ec_admin_form_action=edit&level=' . (int) $level . '&menu_id=' . $id );
		}

		public function __construct() {
			parent::__construct();
			$this->tree_mode = $this->detect_tree_mode();
			$this->setup();
		}

		private function detect_tree_mode() {
			if ( isset( $_GET['s'] ) && '' !== trim( sanitize_text_field( wp_unslash( $_GET['s'] ) ) ) ) { return false; }
			if ( isset( $_GET['health_filter'] ) && '' !== $_GET['health_filter'] ) { return false; }
			foreach ( $_GET as $k => $v ) { if ( 0 === strpos( $k, 'filter_' ) && '' !== $v ) { return false; } }
			if ( isset( $_GET['orderby'] ) && '' !== $_GET['orderby'] && 'menu_order' !== $_GET['orderby'] ) { return false; }
			return true;
		}

		public function setup() {
			/* The base table only knows one table; we page level 1 and pull the rest ourselves. In flat
			   mode the base queries a UNION view built in get_from(). */
			$this->set_table( 'ec_menulevel1', 'menulevel1_id' );
			$this->set_table_id( 'ec_admin_menu_list_v2' );
			$this->set_default_sort( 'menu_order', 'ASC' );
			$this->set_header( __( 'Menus', 'wp-easycart' ) );
			$this->set_docs_link( 'products', 'menus' );
			$this->set_add_new( true, 'add-new-menulevel1', __( 'Add menu', 'wp-easycart' ) );
			$this->set_add_new_js( 'ecv2_catalog.new_menu( 0, 0, this ); return false;' );
			$this->set_add_new_css( 'ecv2-btn ecv2-btn-primary' );
			$this->set_label( __( 'Menu', 'wp-easycart' ), __( 'Menus', 'wp-easycart' ) );
			$this->set_view_modes( array( 'table' ) );
			$this->set_sortable( true );

			$this->set_list_columns( array(
				array( 'name' => 'name', 'label' => __( 'Menu', 'wp-easycart' ), 'format' => 'menu_name', 'linked' => true ),
				array( 'name' => 'level', 'label' => __( 'Level', 'wp-easycart' ), 'format' => 'menu_level', 'select' => '1 AS level' ),
				array( 'name' => 'product_count', 'label' => __( 'Products', 'wp-easycart' ), 'format' => 'menu_products', 'select' => '0 AS product_count' ),
				array( 'name' => 'child_count', 'label' => __( 'Sub-menus', 'wp-easycart' ), 'format' => 'menu_children', 'select' => '( SELECT COUNT(*) FROM ec_menulevel2 m2 WHERE m2.menulevel1_id = ec_menulevel1.menulevel1_id ) AS child_count', 'tablet_hide' => true ),
				array( 'name' => 'clicks', 'label' => __( 'Clicks', 'wp-easycart' ), 'format' => 'int', 'laptop_hide' => true ),
				array( 'name' => 'menulevel1_id', 'label' => __( 'ID', 'wp-easycart' ), 'format' => 'int', 'is_id' => true, 'laptop_hide' => true ),
				array( 'name' => 'menu_order', 'format' => 'hidden', 'label' => '' ),
				array( 'name' => 'post_id', 'format' => 'hidden', 'label' => '' ),
				array( 'name' => 'banner_image', 'format' => 'hidden', 'label' => '' ),
				array( 'select' => "( SELECT p.post_name FROM {$GLOBALS['wpdb']->posts} p WHERE p.ID = ec_menulevel1.post_id ) AS slug", 'name' => 'slug', 'format' => 'hidden', 'label' => '' ),
			) );
			$this->set_search_columns( array( 'ec_menulevel1.name' ) );

			$this->set_bulk_actions( array(
				array( 'name' => 'ecv2-menu-delete', 'label' => __( 'Delete', 'wp-easycart' ) ),
			) );
			$this->set_row_menu_actions( array(
				array( 'label' => __( 'Edit menu', 'wp-easycart' ), 'name' => 'edit', 'icon' => 'edit', 'href' => '#', 'onclick' => 'return ecv2_catalog.menu_edit( this );' ),
				array( 'label' => __( 'Add sub-menu', 'wp-easycart' ), 'name' => 'add-sub', 'icon' => 'plus-alt2', 'href' => '#', 'onclick' => 'return ecv2_catalog.menu_add_sub( this );' ),
				array( 'label' => __( 'View products', 'wp-easycart' ), 'name' => 'products', 'icon' => 'products', 'href' => '#', 'onclick' => 'return ecv2_catalog.menu_products( this );' ),
				array( 'label' => __( 'View on site', 'wp-easycart' ), 'name' => 'view', 'icon' => 'visibility', 'href' => '#', 'target' => '_blank', 'onclick' => 'return ecv2_catalog.menu_view( this );' ),
				array( 'label' => __( 'Delete', 'wp-easycart' ), 'name' => 'delete', 'icon' => 'trash', 'href' => '#', 'onclick' => 'return ecv2_catalog.menu_delete( this );', 'danger' => true ),
			) );

			$this->set_filters( array(
				array(
					'data'  => array(
						(object) array( 'value' => 'empty', 'label' => __( 'No products', 'wp-easycart' ), 'icon' => 'marker' ),
						(object) array( 'value' => 'nobanner', 'label' => __( 'No banner', 'wp-easycart' ), 'icon' => 'format-image' ),
					),
					'label' => __( 'Needs attention', 'wp-easycart' ), 'type' => 'pills', 'where_callback' => true,
				),
			) );

			$this->load_all();
			$h = $this->health();
			$this->set_health_stats( array(
				array( 'label' => __( 'Menus', 'wp-easycart' ), 'value' => $h['l1'], 'filter_value' => '', 'color' => 'default', 'group' => 'catalog' ),
				array( 'label' => __( 'Sub-menus', 'wp-easycart' ), 'value' => $h['l2'] + $h['l3'], 'filter_value' => 'subs', 'color' => 'blue', 'group' => 'catalog' ),
				array( 'label' => __( 'Products in menus', 'wp-easycart' ), 'value' => $h['products'], 'filter_value' => '', 'color' => 'green', 'group' => 'catalog', 'static' => true ),
				array( 'label' => __( 'No products', 'wp-easycart' ), 'value' => $h['empty'], 'filter_value' => 'empty', 'color' => 'amber', 'group' => 'attention' ),
				array( 'label' => __( 'No banner', 'wp-easycart' ), 'value' => $h['nobanner'], 'filter_value' => 'nobanner', 'color' => 'amber', 'group' => 'attention' ),
				array( 'label' => __( 'Clicks (30d)', 'wp-easycart' ), 'value' => $h['clicks'], 'filter_value' => '', 'color' => 'gray', 'group' => 'attention', 'static' => true ),
			) );
		}

		/* ------------------------------------------------------------------ */
		/* Data                                                                */
		/* ------------------------------------------------------------------ */

		private function load_all() {
			global $wpdb;
			for ( $l = 1; $l <= 3; $l++ ) {
				$parent = $l > 1 ? 'menulevel' . ( $l - 1 ) . '_id' : null;
				$rows = $wpdb->get_results( 'SELECT m.*, ' . ( $parent ? "m.$parent AS parent_id" : '0 AS parent_id' ) . ", $l AS level, ( SELECT p.post_name FROM {$wpdb->posts} p WHERE p.ID = m.post_id ) AS slug FROM ec_menulevel$l m ORDER BY m.menu_order ASC, m.name ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $l is the loop counter 1..3 and $parent is built from it; no user values.
				foreach ( $rows as $r ) {
					$id = (int) $r->{ "menulevel{$l}_id" };
					$r->menu_id = $id; $r->key = "$l:$id";
					$r->parent_key = $l > 1 ? ( $l - 1 ) . ':' . (int) $r->parent_id : '';
					$this->rows[ $r->key ] = $r;
					if ( $l > 1 ) { $this->children[ $r->parent_key ][] = $r->key; }
				}
			}
			/* Product counts per node: any of the three paths ( menulevel{PATH}_id_{LEVEL} ) whose level-L segment equals the id */
			for ( $l = 1; $l <= 3; $l++ ) {
				$counts = $wpdb->get_results( "SELECT id, COUNT(*) AS n FROM ( SELECT product_id, menulevel1_id_{$l} AS id FROM ec_product WHERE menulevel1_id_{$l} > 0 UNION SELECT product_id, menulevel2_id_{$l} FROM ec_product WHERE menulevel2_id_{$l} > 0 UNION SELECT product_id, menulevel3_id_{$l} FROM ec_product WHERE menulevel3_id_{$l} > 0 ) x GROUP BY id" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $l is the loop counter 1..3; no user values.
				foreach ( $counts as $c ) { $this->product_counts[ "$l:" . (int) $c->id ] = (int) $c->n; }
			}
		}

		private function health() {
			global $wpdb;
			$h = array( 'l1' => 0, 'l2' => 0, 'l3' => 0, 'empty' => 0, 'nobanner' => 0 );
			foreach ( $this->rows as $k => $r ) {
				$h[ 'l' . $r->level ]++;
				if ( empty( $this->product_counts[ $k ] ) && empty( $this->children[ $k ] ) ) { $h['empty']++; }
				if ( '' === (string) $r->banner_image ) { $h['nobanner']++; }
			}
			/* A product is "in a menu" when any of its three paths has a top-level segment ( menulevel{PATH}_id_1 ) */
			$h['products'] = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_product WHERE menulevel1_id_1 > 0 OR menulevel2_id_1 > 0 OR menulevel3_id_1 > 0' );
			$h['clicks'] = (int) $wpdb->get_var( 'SELECT COALESCE( SUM( clicks ), 0 ) FROM ec_menulevel1' );
			return $h;
		}

		protected function get_health_filter_where( $filter_key ) {
			switch ( $filter_key ) {
				case 'subs': return '1=0'; /* handled in flat mode below */
				/* Only level-1 rows reach this SQL ( the base query pages ec_menulevel1 ); a top menu is empty when no path's level-1 segment ( menulevel{PATH}_id_1 ) names it */
				case 'empty': return 'NOT EXISTS ( SELECT 1 FROM ec_product p WHERE p.menulevel1_id_1 = ec_menulevel1.menulevel1_id OR p.menulevel2_id_1 = ec_menulevel1.menulevel1_id OR p.menulevel3_id_1 = ec_menulevel1.menulevel1_id ) AND NOT EXISTS ( SELECT 1 FROM ec_menulevel2 m2 WHERE m2.menulevel1_id = ec_menulevel1.menulevel1_id )';
				case 'nobanner': return "ec_menulevel1.banner_image = ''";
			}
			return '';
		}

		protected function get_filter_callback_where( $filter_index, $value ) {
			if ( 0 === $filter_index ) { return $this->get_health_filter_where( $value ); }
			return '';
		}

		/* ------------------------------------------------------------------ */
		/* Rendering                                                           */
		/* ------------------------------------------------------------------ */

		protected function print_table_view() {
			echo '<div class="ecv2-table-scroll ecv2-tree-scroll" data-tree="' . ( $this->tree_mode ? '1' : '0' ) . '" data-tree-kind="menu" data-move-action="ecv2_menu_move">';
			if ( $this->tree_mode ) { echo '<p class="ecv2-drag-hint"><span class="dashicons dashicons-move"></span> ' . esc_html__( 'Drag to reorder. Drop on the middle of a menu to nest under it; between menus, slide left or right to pick the level. Menus go three levels deep.', 'wp-easycart' ) . '</p>'; }
			echo '<table class="ecv2-table ecv2-cat-table ecv2-menu-table" id="' . esc_attr( $this->table_id ) . '">';
			$this->print_table_thead();
			echo '<tbody>';
			if ( $this->tree_mode ) {
				if ( empty( $this->results ) ) { $this->print_empty(); }
				foreach ( $this->results as $result ) {
					$key = '1:' . (int) $result->menulevel1_id;
					if ( isset( $this->rows[ $key ] ) ) { $this->print_node( $key, 0 ); }
				}
			} else {
				/* Flat: every level matching search/filter */
				$q = isset( $_GET['s'] ) ? strtolower( trim( sanitize_text_field( wp_unslash( $_GET['s'] ) ) ) ) : '';
				$f = isset( $_GET['health_filter'] ) ? sanitize_key( $_GET['health_filter'] ) : ( isset( $_GET['filter_0'] ) ? sanitize_key( $_GET['filter_0'] ) : '' );
				$n = 0;
				foreach ( $this->rows as $key => $r ) {
					if ( '' !== $q && false === strpos( strtolower( $r->name ), $q ) ) { continue; }
					if ( 'subs' === $f && 1 === (int) $r->level ) { continue; }
					if ( 'empty' === $f && ( ! empty( $this->product_counts[ $key ] ) || ! empty( $this->children[ $key ] ) ) ) { continue; }
					if ( 'nobanner' === $f && '' !== (string) $r->banner_image ) { continue; }
					$this->print_node( $key, 0, false ); $n++;
				}
				if ( ! $n ) { $this->print_empty(); }
			}
			echo '</tbody></table></div>';
		}

		private function print_empty() {
			echo '<tr><td colspan="' . ( count( $this->list_columns ) + 2 ) . '" class="ecv2-empty-state"><span class="dashicons dashicons-info-outline"></span> ' . esc_html__( 'No menus found.', 'wp-easycart' ) . '</td></tr>';
		}

		private function print_node( $key, $depth, $recurse = true ) {
			$r = $this->rows[ $key ];
			$kids = $recurse && isset( $this->children[ $key ] ) ? $this->children[ $key ] : array();
			$count = isset( $this->product_counts[ $key ] ) ? $this->product_counts[ $key ] : 0;
			$editor = self::editor_url( $r->level, $r->menu_id );
			echo '<tr class="ecv2-row ecv2-cat-row ecv2-menu-row' . ( $kids ? ' has-children' : '' ) . '" data-id="' . esc_attr( $key ) . '" data-level="' . (int) $depth . '" data-menu-level="' . (int) $r->level . '" data-menu-id="' . (int) $r->menu_id . '" data-parent="' . esc_attr( $r->parent_key ) . '" data-edit-url="' . esc_url( $editor ) . '" data-slug="' . esc_attr( (string) $r->slug ) . '" data-name="' . esc_attr( wp_unslash( $r->name ) ) . '" draggable="true">';
			echo '<td class="ecv2-col-check"><input type="checkbox" name="bulk[]" value="' . esc_attr( $key ) . '" class="ecv2-row-check" /></td>';
			/* name */
			echo '<td class="ecv2-cell ecv2-cell-name"><div class="ecv2-tree" style="--lvl:' . (int) $depth . '">';
			if ( $this->tree_mode ) {
				echo '<span class="ecv2-drag-handle" title="' . esc_attr__( 'Drag to move, or focus and use the arrow keys', 'wp-easycart' ) . '"><span class="dashicons dashicons-menu"></span></span>';
				echo $kids ? '<button type="button" class="ecv2-tree-toggle is-open" aria-expanded="true" onclick="ecv2_catalog.toggle_tree( this );"><span class="dashicons dashicons-arrow-right-alt2"></span></button>' : '<span class="ecv2-tree-toggle is-leaf"></span>';
			}
			echo '<div class="ecv2-tree-text"><a href="' . esc_url( $editor ) . '" class="ecv2-link-primary ecv2-title-link">' . esc_html( wp_unslash( $r->name ) ) . '</a>';
			$subs = array();
			if ( ! $this->tree_mode ) { $path = $this->path_for( $key ); if ( $path ) { $subs[] = esc_html( implode( ' › ', $path ) ) . ' ›'; } }
			if ( $r->slug ) { $subs[] = '<span class="ecv2-mono">/' . esc_html( $r->slug ) . '/</span>'; }
			if ( '' === (string) $r->banner_image ) { $subs[] = '<span class="ecv2-sub-warn">' . esc_html__( 'No banner', 'wp-easycart' ) . '</span>'; }
			if ( $subs ) { echo '<span class="ecv2-sub">' . implode( ' · ', $subs ) . '</span>'; } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every $subs entry is built from esc_html()/esc_html__() plus literal markup above.
			echo '</div></div></td>';
			/* level */
			$labels = array( 1 => __( 'Menu', 'wp-easycart' ), 2 => __( 'Sub-menu', 'wp-easycart' ), 3 => __( 'Sub-sub-menu', 'wp-easycart' ) );
			echo '<td class="ecv2-cell"><span class="ecv2-chip ecv2-chip-' . ( 1 === (int) $r->level ? 'brand' : 'gray' ) . '">' . esc_html( $labels[ (int) $r->level ] ) . '</span></td>';
			/* products */
			echo '<td class="ecv2-cell">';
			if ( $count ) { echo '<a class="ecv2-usage" href="' . esc_url( admin_url( 'admin.php?page=wp-easycart-products&subpage=products&menu=' . (int) $r->level . ':' . (int) $r->menu_id ) ) . '">' . esc_html( sprintf( _n( '%d product', '%d products', $count, 'wp-easycart' ), $count ) ) . '</a>'; }
			else { echo '<span class="ecv2-usage ecv2-usage-zero">' . esc_html__( '0 products', 'wp-easycart' ) . '</span>'; }
			echo '</td>';
			/* children */
			$cc = isset( $this->children[ $key ] ) ? count( $this->children[ $key ] ) : 0;
			echo '<td class="ecv2-cell ecv2-hide-tablet">' . ( $cc ? '<span class="ecv2-chip ecv2-chip-gray">' . (int) $cc . '</span>' : '<span class="ecv2-sub">—</span>' ) . '</td>';
			echo '<td class="ecv2-cell ecv2-hide-laptop">' . (int) $r->clicks . '</td>';
			echo '<td class="ecv2-cell ecv2-hide-laptop"><span class="ecv2-sub" style="margin:0">' . (int) $r->menu_id . '</span></td>';
			echo '<td class="ecv2-col-actions">';
			$this->print_row_actions( (object) array( $this->key => $key ) );
			echo '</td></tr>';
			foreach ( $kids as $kid ) { $this->print_node( $kid, $depth + 1 ); }
		}

		private function path_for( $key ) {
			$names = array(); $cur = $key; $guard = 0;
			while ( isset( $this->rows[ $cur ] ) && $this->rows[ $cur ]->parent_key && $guard++ < 5 ) {
				$cur = $this->rows[ $cur ]->parent_key;
				if ( isset( $this->rows[ $cur ] ) ) { array_unshift( $names, $this->rows[ $cur ]->name ); }
			}
			return $names;
		}

		/**
		 * Options for parent selects: [ { value: "1:12", label, level } ], depth-first.
		 * One query per level ( not one per top-level menu ); $limit > 0 stops after that
		 * many entries so a huge menu tree never lands in a page payload whole.
		 *
		 * @since 6.0.0 $limit parameter; sub-menus are fetched in one query.
		 */
		public static function parent_options( $max_level = 2, $limit = 0 ) {
			global $wpdb;
			$out   = array();
			$limit = (int) $limit;
			$l1    = $wpdb->get_results( 'SELECT menulevel1_id AS id, name FROM ec_menulevel1 ORDER BY menu_order, name' );
			$kids  = array();
			if ( $max_level >= 2 && $l1 ) {
				foreach ( $wpdb->get_results( 'SELECT menulevel2_id AS id, menulevel1_id AS parent_id, name FROM ec_menulevel2 ORDER BY menu_order, name' ) as $b ) {
					$kids[ (int) $b->parent_id ][] = $b;
				}
			}
			foreach ( $l1 as $a ) {
				if ( $limit && count( $out ) >= $limit ) { break; }
				$out[] = array( 'value' => '1:' . (int) $a->id, 'label' => $a->name, 'level' => 1 );
				if ( isset( $kids[ (int) $a->id ] ) ) {
					foreach ( $kids[ (int) $a->id ] as $b ) {
						if ( $limit && count( $out ) >= $limit ) { break; }
						$out[] = array( 'value' => '2:' . (int) $b->id, 'label' => '— ' . $b->name, 'level' => 2 );
					}
				}
			}
			return $out;
		}
	}

endif;

/* ---------------------------------------------------------------------- */
/* AJAX                                                                    */
/* ---------------------------------------------------------------------- */

function ecv2_menu_guard() {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) { wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) ); }
	check_ajax_referer( 'wp-easycart-ecv2-inline-update', 'wp_easycart_nonce' );
}
function ecv2_menu_shortcode_attr( $level ) { return array( 1 => 'menuid', 2 => 'submenuid', 3 => 'subsubmenuid' )[ (int) $level ]; }
function ecv2_menu_parse_key( $key ) {
	if ( ! preg_match( '/^([123]):(\d+)$/', (string) $key, $m ) ) { return null; }
	return array( 'level' => (int) $m[1], 'id' => (int) $m[2] );
}

/**
 * Move / reorder. POST: key ( "L:id" ), parent_key ( "L-1:id" or "" ), order[] ( sibling keys, top to bottom ).
 * Re-parenting is allowed only to a node exactly one level up; product paths are rewritten.
 */
/**
 * Relocate a menu anywhere in the tree — same level ( reparent / reorder ) or a different level ( menu ↔ sub-menu ↔
 * sub-sub menu ). Menus live in three fixed tables, so a level change moves the row ( and every descendant ) into the
 * right table with new ids, then rewrites every product's menu path ( ec_product.menulevel{1,2,3}_id_{1,2,3} ) and the
 * ec_store post's shortcode. Returns array( level, id, key, id_map ) or WP_Error.
 *
 * @param array|null $parent   array( 'level' => int, 'id' => int ) or null for top level
 * @param array      $order    sibling keys ( "level:id" ) in the desired order under the new parent; the moved node may
 *                             appear under its OLD key — it is translated after the move
 */
function ecv2_menu_relocate( $level, $id, $parent, $order = array() ) {
	global $wpdb;
	$level = (int) $level; $id = (int) $id; $sd = wp_easycart_admin_safe_delete();
	$key = 'menulevel' . $level . '_id'; $table = 'ec_menulevel' . $level;
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE $key = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table/$key are built from (int) $level above.
	if ( ! $row ) { return new WP_Error( 'missing', __( 'Menu not found.', 'wp-easycart' ) ); }
	$new_level = $parent ? (int) $parent['level'] + 1 : 1;
	if ( $parent ) {
		$pk = 'menulevel' . (int) $parent['level'] . '_id'; $pt = 'ec_menulevel' . (int) $parent['level'];
		if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT $pk FROM $pt WHERE $pk = %d", (int) $parent['id'] ) ) ) { return new WP_Error( 'parent', __( 'Target menu not found.', 'wp-easycart' ) ); } // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $pk/$pt are built from (int) $parent['level'].
		foreach ( $sd->menu_descendants( $level, $id ) as $d ) { if ( $d['level'] === (int) $parent['level'] && $d['id'] === (int) $parent['id'] ) { return new WP_Error( 'cycle', __( 'A menu can’t be moved under itself or one of its own sub-menus.', 'wp-easycart' ) ); } }
	}
	$desc = $sd->menu_descendants( $level, $id ); $deepest = $level;
	foreach ( $desc as $d ) { $deepest = max( $deepest, (int) $d['level'] ); }
	$depth = $deepest - $level;
	if ( $new_level + $depth > 3 ) {
		return new WP_Error( 'depth', $depth === 2 ? sprintf( __( '“%s” has sub-sub menus, so it can only be a top-level menu — menus go three levels deep.', 'wp-easycart' ), wp_unslash( $row->name ) ) : sprintf( __( '“%s” has sub-menus, so it can’t go deeper than a sub-menu — menus go three levels deep.', 'wp-easycart' ), wp_unslash( $row->name ) ) );
	}

	$map = array(); /* "oldlevel:oldid" => array( level, id, path[1..3] ) */
	if ( $new_level === $level ) {
		/* same level: reparent ( levels 2/3 ) and reorder */
		if ( $level > 1 ) {
			$pk = 'menulevel' . ( $level - 1 ) . '_id'; $old_parent = (int) $row->{ $pk };
			if ( $old_parent !== (int) $parent['id'] ) {
				$wpdb->update( $table, array( $pk => (int) $parent['id'] ), array( $key => $id ) );
				$l1 = 3 === $level ? (int) $wpdb->get_var( $wpdb->prepare( 'SELECT menulevel1_id FROM ec_menulevel2 WHERE menulevel2_id = %d', (int) $parent['id'] ) ) : (int) $parent['id'];
				/* Product paths are menulevel{PATH}_id_{LEVEL}: for each of the three paths, every product whose
				   level-d segment names a moved node gets its parent-level segment ( and, for a sub-sub menu, its
				   top-level segment ) pointed at the new parent. */
				$parent_level = $level - 1;
				foreach ( $desc as $d ) {
					$d_level = (int) $d['level'];
					for ( $n = 1; $n <= 3; $n++ ) {
						$wpdb->query( $wpdb->prepare( "UPDATE ec_product SET menulevel{$n}_id_{$parent_level} = %d WHERE menulevel{$n}_id_{$d_level} = %d", (int) $parent['id'], $d['id'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $parent_level from (int) $level, $d_level from menu_descendants() int levels, $n is the loop counter 1..3.
						if ( 3 === $level ) { $wpdb->query( $wpdb->prepare( "UPDATE ec_product SET menulevel{$n}_id_1 = %d WHERE menulevel{$n}_id_{$d_level} = %d", $l1, $d['id'] ) ); } // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $d_level from menu_descendants() int levels, $n is the loop counter 1..3.
					}
				}
			}
		}
		$map[ "$level:$id" ] = array( 'level' => $level, 'id' => $id );
	} else {
		/* level change: copy the subtree into the right tables, deepest last, remembering old → new */
		$path = array( 1 => 0, 2 => 0, 3 => 0 );
		if ( $parent ) {
			$path[ (int) $parent['level'] ] = (int) $parent['id'];
			if ( 2 === (int) $parent['level'] ) { $path[1] = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT menulevel1_id FROM ec_menulevel2 WHERE menulevel2_id = %d', (int) $parent['id'] ) ); }
		}
		$copy = function( $lvl, $node_id, $to_level, $to_parent_id, $path ) use ( &$copy, &$map, $wpdb ) {
			$k = 'menulevel' . $lvl . '_id'; $r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM ec_menulevel$lvl WHERE $k = %d", $node_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $lvl is (int) $level or $lvl + 1 from the recursive call; $k is built from it.
			if ( ! $r ) { return; }
			$data = array( 'name' => $r->name, 'seo_keywords' => (string) $r->seo_keywords, 'seo_description' => (string) $r->seo_description, 'banner_image' => (string) $r->banner_image, 'post_id' => (int) $r->post_id, 'clicks' => (int) $r->clicks, 'is_demo_item' => (int) $r->is_demo_item );
			$where = '';
			if ( $to_level > 1 ) { $data[ 'menulevel' . ( $to_level - 1 ) . '_id' ] = (int) $to_parent_id; $where = $wpdb->prepare( ' WHERE menulevel' . ( $to_level - 1 ) . '_id = %d', (int) $to_parent_id ); } // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $to_level is an int level (new_level or to_level + 1); the arithmetic yields an int identifier suffix.
			$data['menu_order'] = (int) $wpdb->get_var( "SELECT COALESCE( MAX( menu_order ), -1 ) FROM ec_menulevel$to_level$where" ) + 1; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $to_level is an int level; $where is '' or a $wpdb->prepare()'d fragment.
			$wpdb->insert( 'ec_menulevel' . $to_level, $data ); $new_id = (int) $wpdb->insert_id;
			$path[ $to_level ] = $new_id;
			$map[ "$lvl:$node_id" ] = array( 'level' => $to_level, 'id' => $new_id, 'path' => $path, 'post_id' => (int) $r->post_id );
			if ( $lvl < 3 ) {
				foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT menulevel' . ( $lvl + 1 ) . '_id AS cid FROM ec_menulevel' . ( $lvl + 1 ) . " WHERE $k = %d ORDER BY menu_order, name", $node_id ) ) as $c ) { $copy( $lvl + 1, (int) $c->cid, $to_level + 1, $new_id, $path ); } // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $lvl is an int level and $k is built from it; $node_id is a %d placeholder.
			}
		};
		$copy( $level, $id, $new_level, $parent ? (int) $parent['id'] : 0, $path );

		/*
		 * Products: for each of the three menu paths ( slot $n → columns menulevel{$n}_id_{1,2,3} ),
		 * the deepest non-zero level column that names a moved node decides the path's new value;
		 * a path whose deepest column is not a moved node stays. Done as set-based UPDATEs — one
		 * per slot × old level, up to 500 moved nodes per statement — instead of loading and
		 * updating every product row one by one.
		 *
		 * @since 6.0.0
		 */
		$by_level = array( 3 => array(), 2 => array(), 1 => array() );
		foreach ( $map as $ok => $m ) {
			if ( empty( $m['path'] ) ) { continue; }
			list( $ol, $oi ) = explode( ':', $ok );
			$by_level[ (int) $ol ][ (int) $oi ] = $m['path'];
		}
		for ( $n = 1; $n <= 3; $n++ ) {
			for ( $k = 3; $k >= 1; $k-- ) {
				if ( empty( $by_level[ $k ] ) ) { continue; }
				$col = 'menulevel' . (int) $n . '_id_' . (int) $k;
				/* deeper level columns of this path must be empty, otherwise a deeper ( unmoved ) node owns the path */
				$deeper = array();
				for ( $d = $k + 1; $d <= 3; $d++ ) { $deeper[] = 'menulevel' . (int) $n . '_id_' . (int) $d . ' = 0'; }
				foreach ( array_chunk( $by_level[ $k ], 500, true ) as $chunk ) {
					$ids = array(); $case = array( 1 => '', 2 => '', 3 => '' );
					foreach ( $chunk as $old_id => $path ) {
						$ids[] = (int) $old_id;
						for ( $lv = 1; $lv <= 3; $lv++ ) { $case[ $lv ] .= ' WHEN ' . (int) $old_id . ' THEN ' . (int) $path[ $lv ]; }
					}
					/* MySQL applies SET left to right, so the column the CASEs key on is assigned last */
					$set = array();
					foreach ( array( 1, 2, 3 ) as $lv ) {
						if ( $lv === $k ) { continue; }
						$set[] = 'menulevel' . (int) $n . '_id_' . $lv . ' = CASE ' . $col . $case[ $lv ] . ' ELSE menulevel' . (int) $n . '_id_' . $lv . ' END';
					}
					$set[] = $col . ' = CASE ' . $col . $case[ $k ] . ' ELSE ' . $col . ' END';
					$wpdb->query( 'UPDATE ec_product SET ' . implode( ', ', $set ) . ' WHERE ' . $col . ' IN ( ' . implode( ',', $ids ) . ' )' . ( $deeper ? ' AND ' . implode( ' AND ', $deeper ) : '' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- every identifier is built from the int loop counters and every value is (int)-cast above; nothing comes from the request.
				}
			}
		}
		/* posts: same post, new shortcode attribute + entity link */
		foreach ( $map as $ok => $m ) {
			if ( empty( $m['post_id'] ) ) { continue; }
			$content = '[ec_store ' . ecv2_menu_shortcode_attr( $m['level'] ) . '="' . (int) $m['id'] . '"]';
			$wpdb->update( $wpdb->posts, array( 'post_content' => $content ), array( 'ID' => (int) $m['post_id'] ) ); clean_post_cache( (int) $m['post_id'] );
			if ( function_exists( 'wp_easycart_post_sync' ) && method_exists( wp_easycart_post_sync(), 'update' ) ) { wp_easycart_post_sync()->update( 'menulevel' . $m['level'], (int) $m['id'], (int) $m['post_id'], array( 'post_content' => $content ) ); }
		}
		/* delete the old rows ( deepest first ) */
		$olds = array_keys( $map ); usort( $olds, function( $a, $b ) { return (int) $b[0] - (int) $a[0]; } );
		foreach ( $olds as $ok ) { list( $ol, $oi ) = explode( ':', $ok ); $wpdb->delete( 'ec_menulevel' . (int) $ol, array( 'menulevel' . (int) $ol . '_id' => (int) $oi ) ); }
		do_action( 'wpeasycart_menu_relocated', $map, $level, $id );
	}

	/* sibling order under the new parent; the moved node may be referenced by its old key */
	$new = $map[ "$level:$id" ]; $nt = 'ec_menulevel' . $new['level']; $nk = 'menulevel' . $new['level'] . '_id'; $i = 0;
	foreach ( $order as $ok ) {
		$ok = (string) $ok; if ( isset( $map[ $ok ] ) && $map[ $ok ]['level'] === $new['level'] ) { $ok = $map[ $ok ]['level'] . ':' . $map[ $ok ]['id']; }
		$o = ecv2_menu_parse_key( $ok );
		if ( $o && $o['level'] === $new['level'] ) { $wpdb->update( $nt, array( 'menu_order' => $i++ ), array( $nk => $o['id'] ) ); }
	}
	do_action( 'wpeasycart_menu_updated', $new['id'], $new['level'] );
	wp_cache_delete( 'wpeasycart-settings', 'wpeasycart-settings' ); wp_cache_flush();
	return array( 'level' => $new['level'], 'id' => $new['id'], 'key' => $new['level'] . ':' . $new['id'], 'level_changed' => $new['level'] !== $level, 'id_map' => $map );
}

add_action( 'wp_ajax_ecv2_menu_move', 'ecv2_menu_move' );
function ecv2_menu_move() {
	ecv2_menu_guard();
	$node = ecv2_menu_parse_key( isset( $_POST['key'] ) ? $_POST['key'] : '' );
	if ( ! $node ) { wp_send_json_error( array( 'message' => __( 'Invalid menu.', 'wp-easycart' ) ) ); }
	$parent = ecv2_menu_parse_key( isset( $_POST['parent_key'] ) ? $_POST['parent_key'] : '' );
	$order  = isset( $_POST['order'] ) && is_array( $_POST['order'] ) ? array_map( 'sanitize_text_field', $_POST['order'] ) : array();
	$r = ecv2_menu_relocate( $node['level'], $node['id'], $parent ? $parent : null, $order );
	if ( is_wp_error( $r ) ) { wp_send_json_error( array( 'message' => $r->get_error_message() ) ); }
	wp_send_json_success( $r );
}

/** Create at any level. POST: name, parent_key ( "" for a top-level menu ). */
add_action( 'wp_ajax_ecv2_menu_create', 'ecv2_menu_create' );
function ecv2_menu_create() {
	ecv2_menu_guard();
	global $wpdb;
	$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	if ( '' === $name ) { wp_send_json_error( array( 'message' => __( 'Name is required.', 'wp-easycart' ) ) ); }
	$parent = ecv2_menu_parse_key( isset( $_POST['parent_key'] ) ? $_POST['parent_key'] : '' );
	$level = $parent ? $parent['level'] + 1 : 1;
	if ( $level > 3 ) { wp_send_json_error( array( 'message' => __( 'Menus go three levels deep at most.', 'wp-easycart' ) ) ); }
	$table = 'ec_menulevel' . $level;
	$data = array( 'name' => $name, 'seo_keywords' => '', 'seo_description' => '', 'banner_image' => '' );
	$where_parent = '';
	if ( $parent ) { $data[ 'menulevel' . $parent['level'] . '_id' ] = $parent['id']; $where_parent = $wpdb->prepare( ' WHERE menulevel' . $parent['level'] . '_id = %d', $parent['id'] ); } // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $parent['level'] is an int from ecv2_menu_parse_key() (regex [123]).
	$data['menu_order'] = (int) $wpdb->get_var( "SELECT COALESCE( MAX( menu_order ), -1 ) FROM $table$where_parent" ) + 1; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is 'ec_menulevel' . int level (1..3); $where_parent is '' or a $wpdb->prepare()'d fragment.
	$wpdb->insert( $table, $data );
	$id = (int) $wpdb->insert_id;
	$post_id = wp_easycart_post_sync()->insert( 'menulevel' . $level, $id, array(
		'post_content' => '[ec_store ' . ecv2_menu_shortcode_attr( $level ) . '="' . $id . '"]', 'post_status' => 'publish', 'post_title' => wp_easycart_language()->convert_text( $name ), 'post_type' => 'ec_store', 'post_excerpt' => '',
	) );
	if ( $post_id ) { wp_set_post_tags( $post_id, array( 'menu' ), true ); $wpdb->update( $table, array( 'post_id' => (int) $post_id ), array( "menulevel{$level}_id" => $id ) ); }
	do_action( 'wpeasycart_menu_added', $id, $level );
	wp_send_json_success( array( 'key' => "$level:$id", 'level' => $level, 'id' => $id, 'edit_url' => wp_easycart_admin_menu_table::editor_url( $level, $id ) ) );
}

add_action( 'wp_ajax_ecv2_menu_bulk', 'ecv2_menu_bulk' );
function ecv2_menu_bulk() {
	ecv2_menu_guard();
	$keys = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ? array_slice( array_map( 'sanitize_text_field', $_POST['ids'] ), 0, 200 ) : array();
	$op = isset( $_POST['op'] ) ? sanitize_key( $_POST['op'] ) : '';
	if ( empty( $keys ) || 'delete' !== $op ) { wp_send_json_error( array( 'message' => __( 'Nothing to do.', 'wp-easycart' ) ) ); }
	/* Deepest first so a parent's cascade doesn't swallow a child selected separately */
	$nodes = array_filter( array_map( 'ecv2_menu_parse_key', $keys ) );
	usort( $nodes, function( $a, $b ) { return $b['level'] - $a['level']; } );
	$done = 0; $trash = array(); $errors = array();
	foreach ( $nodes as $n ) {
		$report = wp_easycart_admin_safe_delete()->analyze( 'menu' . $n['level'], $n['id'] );
		if ( is_wp_error( $report ) ) { continue; } /* already removed by a parent cascade */
		$strategy = 'remove';
		foreach ( $report['strategies'] as $s ) { if ( 'cascade' === $s['key'] ) { $strategy = 'cascade'; } }
		$r = wp_easycart_admin_safe_delete()->execute( 'menu' . $n['level'], $n['id'], array( 'strategy' => $strategy, 'redirect' => true ) );
		if ( is_wp_error( $r ) ) { $errors[] = $r->get_error_message(); } else { $done++; $trash[] = $r['trash_id']; }
	}
	wp_send_json_success( array( 'done' => $done, 'trash_ids' => $trash, 'errors' => $errors ) );
}
