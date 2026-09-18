<?php
/**
 * WP EasyCart Admin — Categories list ( V2 ).
 *
 * Tree mode ( default ): top-level categories are paged; their descendants
 * render beneath them, collapsible. Any search or filter switches to a flat
 * list where each row shows its parent path instead.
 *
 * Drag a row to reorder it among its siblings, or drop it onto another row to
 * nest it. Both post to ecv2_category_move.
 *
 * AJAX: ecv2_category_move, ecv2_category_toggle, ecv2_category_create,
 *       ecv2_category_inline_update, ecv2_category_bulk.
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

if ( ! class_exists( 'wp_easycart_admin_category_table' ) ) :

	class wp_easycart_admin_category_table extends wp_easycart_admin_table_v2 {

		private $health_data = array();
		private $tree_mode   = true;
		/*
		 * Per-request caches ( @since 6.0.0 ). The list never loads the whole table any more:
		 * tree mode pages over the top-level rows and children arrive through
		 * ecv2_category_children when a row is expanded.
		 */
		private $parent_map     = null;    /* category_id => parent_id ( two ints per row ) */
		private $ancestor_names = array(); /* category_id => category_name for the page rows' ancestors */
		private $tree_counts    = array(); /* category_id => distinct products incl. subcategories */
		/** Set before `new` from ecv2_category_children so the constructor skips the health stat counts. */
		public static $lightweight = false;

		public static function editor_url( $category_id ) {
			$id = ( '{id}' === $category_id ) ? '{id}' : (int) $category_id;
			return admin_url( 'admin.php?page=wp-easycart-products&subpage=category&ec_admin_form_action=edit&category_id=' . $id );
		}

		public function __construct() {
			parent::__construct();
			$this->tree_mode = $this->detect_tree_mode();
			$this->setup();
		}

		private function detect_tree_mode() {
			if ( isset( $_GET['s'] ) && '' !== trim( sanitize_text_field( wp_unslash( $_GET['s'] ) ) ) ) { return false; }
			if ( isset( $_GET['health_filter'] ) && '' !== $_GET['health_filter'] ) { return false; }
			foreach ( $_GET as $k => $v ) {
				if ( 0 === strpos( $k, 'filter_' ) && '' !== $v ) { return false; }
			}
			if ( isset( $_GET['orderby'] ) && '' !== $_GET['orderby'] && 'priority' !== $_GET['orderby'] ) { return false; }
			return true;
		}

		public function is_tree_mode() {
			return $this->tree_mode;
		}

		public function setup() {
			$this->set_table( 'ec_category', 'category_id' );
			$this->set_table_id( 'ec_admin_category_list_v2' );
			$this->set_default_sort( array( 'priority', 'category_name' ), array( 'DESC', 'ASC' ) );
			$this->set_header( __( 'Categories', 'wp-easycart' ) );
			$this->set_docs_link( 'products', 'categories' );
			$this->set_add_new( true, 'add-new-category', __( 'Add category', 'wp-easycart' ) );
			$this->set_add_new_js( 'ecv2_catalog.new_category( 0, this ); return false;' );
			$this->set_add_new_css( 'ecv2-btn ecv2-btn-primary' );
			$this->set_label( __( 'Category', 'wp-easycart' ), __( 'Categories', 'wp-easycart' ) );
			$this->set_view_modes( array( 'table', 'card' ) );
			$this->set_sortable( true );
			if ( $this->tree_mode ) {
				/* Orphans ( parent no longer exists ) surface as roots so they are never invisible */
				$this->set_custom_where( ' AND ( ec_category.parent_id = 0 OR NOT EXISTS ( SELECT 1 FROM ec_category c9 WHERE c9.category_id = ec_category.parent_id ) )' );
			}

			$this->set_list_columns( self::columns() );
			$this->set_search_columns( array( 'ec_category.category_name', 'ec_category.short_description' ) );

			$this->set_bulk_actions( apply_filters( 'wp_easycart_admin_bulk_category_options', array(
				array( 'name' => 'ecv2-category-activate', 'label' => __( 'Activate', 'wp-easycart' ) ),
				array( 'name' => 'ecv2-category-deactivate', 'label' => __( 'Hide', 'wp-easycart' ) ),
				array( 'name' => 'ecv2-category-feature', 'label' => __( 'Mark featured', 'wp-easycart' ) ),
				array( 'name' => 'ecv2-category-unfeature', 'label' => __( 'Unmark featured', 'wp-easycart' ) ),
				array( 'name' => 'ecv2-category-move', 'label' => __( 'Move under…', 'wp-easycart' ) ),
				array( 'name' => 'ecv2-category-delete', 'label' => __( 'Delete', 'wp-easycart' ) ),
			) ) );

			$this->set_row_menu_actions( array(
				array( 'label' => __( 'Edit category', 'wp-easycart' ), 'name' => 'edit', 'icon' => 'edit', 'href' => self::editor_url( '{id}' ) ),
				array( 'label' => __( 'Add products…', 'wp-easycart' ), 'name' => 'add-products', 'icon' => 'plus-alt2', 'href' => '#', 'onclick' => 'ecv2_catalog.pick_products( {id} ); return false;' ),
				array( 'label' => __( 'Add subcategory', 'wp-easycart' ), 'name' => 'add-sub', 'icon' => 'category', 'href' => '#', 'onclick' => 'ecv2_catalog.new_category( {id} ); return false;' ),
				array( 'label' => __( 'View on site', 'wp-easycart' ), 'name' => 'view', 'icon' => 'visibility', 'href' => '#', 'target' => '_blank', 'onclick' => 'return ecv2_catalog.view_category( this, {id} );' ),
				array( 'label' => __( 'Duplicate', 'wp-easycart' ), 'name' => 'duplicate', 'icon' => 'admin-page', 'action' => 'duplicate-category' ),
				array( 'label' => __( 'Move under…', 'wp-easycart' ), 'name' => 'move', 'icon' => 'migrate', 'href' => '#', 'onclick' => 'ecv2_catalog.move_category( [ {id} ] ); return false;' ),
				array( 'label' => __( 'Delete', 'wp-easycart' ), 'name' => 'delete', 'icon' => 'trash', 'href' => '#', 'onclick' => 'ecv2_catalog.safe_delete( \'category\', {id} ); return false;', 'danger' => true ),
			) );

			$this->set_filters( apply_filters( 'wp_easycart_admin_category_list_filters', array(
				array(
					'data'  => array(
						(object) array( 'value' => '1', 'label' => __( 'Active', 'wp-easycart' ), 'icon' => 'visibility' ),
						(object) array( 'value' => '0', 'label' => __( 'Hidden', 'wp-easycart' ), 'icon' => 'hidden' ),
					),
					'label' => __( 'Status', 'wp-easycart' ), 'type' => 'pills', 'where' => 'ec_category.is_active = %d',
				),
				array(
					'data'  => array( (object) array( 'value' => '1', 'label' => __( 'Featured', 'wp-easycart' ), 'icon' => 'star-filled' ) ),
					'label' => __( 'Featured', 'wp-easycart' ), 'type' => 'pills', 'where' => 'ec_category.featured_category = %d',
				),
				array(
					'data'  => array(
						(object) array( 'value' => 'empty', 'label' => __( 'Empty', 'wp-easycart' ), 'icon' => 'marker' ),
						(object) array( 'value' => 'noimage', 'label' => __( 'No image', 'wp-easycart' ), 'icon' => 'format-image' ),
						(object) array( 'value' => 'top', 'label' => __( 'Top level only', 'wp-easycart' ), 'icon' => 'arrow-up-alt2' ),
					),
					'label' => __( 'Needs attention', 'wp-easycart' ), 'type' => 'pills', 'where_callback' => true,
				),
				array(
					'data'  => $this->parent_options(),
					'label' => __( 'Parent', 'wp-easycart' ), 'type' => 'select', 'where' => 'ec_category.parent_id = %d',
				),
			) ) );

			if ( self::$lightweight ) {
				return; /* ecv2_category_children only needs columns + row rendering */
			}
			$this->compute_health_data();
			$h = $this->health_data;
			$this->set_health_stats( array(
				array( 'label' => __( 'All', 'wp-easycart' ), 'value' => $h['total'], 'filter_value' => '', 'color' => 'default', 'group' => 'catalog' ),
				array( 'label' => __( 'Active', 'wp-easycart' ), 'value' => $h['active'], 'filter_value' => 'active', 'color' => 'green', 'group' => 'catalog' ),
				array( 'label' => __( 'Hidden', 'wp-easycart' ), 'value' => $h['hidden'], 'filter_value' => 'hidden', 'color' => 'gray', 'group' => 'catalog' ),
				array( 'label' => __( 'Featured', 'wp-easycart' ), 'value' => $h['featured'], 'filter_value' => 'featured', 'color' => 'green', 'group' => 'catalog' ),
				array( 'label' => __( 'Empty', 'wp-easycart' ), 'value' => $h['empty'], 'filter_value' => 'empty', 'color' => 'amber', 'group' => 'attention' ),
				array( 'label' => __( 'No image', 'wp-easycart' ), 'value' => $h['noimage'], 'filter_value' => 'noimage', 'color' => 'amber', 'group' => 'attention' ),
				array( 'label' => __( 'Orphaned', 'wp-easycart' ), 'value' => $h['orphaned'], 'filter_value' => 'orphaned', 'color' => 'red', 'group' => 'attention' ),
			) );
			if ( self::smart_available() ) {
				global $wpdb;
				$stats = $this->health_stats;
				array_splice( $stats, 4, 0, array( array( 'label' => __( 'Smart', 'wp-easycart' ), 'value' => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_category WHERE smart_mode > 0' ), 'filter_value' => 'smart', 'color' => 'blue', 'group' => 'catalog' ) ) );
				$this->set_health_stats( $stats );
			}
		}

		public static function smart_available() {
			return class_exists( 'ec_smart_categories_support' ) && ec_smart_categories_support::columns_exist();
		}

		public static function columns() {
			return array(
				array( 'name' => 'category_name', 'label' => __( 'Category', 'wp-easycart' ), 'format' => 'cat_name', 'linked' => true ),
				array( 'select' => '( SELECT COUNT(*) FROM ec_categoryitem ci WHERE ci.category_id = ec_category.category_id ) AS direct_products', 'name' => 'direct_products', 'label' => __( 'Products', 'wp-easycart' ), 'format' => 'cat_products' ),
				array( 'select' => '( SELECT COUNT(*) FROM ec_category c2 WHERE c2.parent_id = ec_category.category_id ) AS child_count', 'name' => 'child_count', 'label' => __( 'Subcategories', 'wp-easycart' ), 'format' => 'cat_children', 'tablet_hide' => true ),
				array( 'name' => 'featured_category', 'label' => __( 'Featured', 'wp-easycart' ), 'format' => 'cat_toggle' ),
				array( 'name' => 'is_active', 'label' => __( 'Active', 'wp-easycart' ), 'format' => 'cat_toggle' ),
				array( 'name' => 'category_id', 'label' => __( 'ID', 'wp-easycart' ), 'format' => 'int', 'is_id' => true, 'laptop_hide' => true ),
				array( 'name' => 'parent_id', 'format' => 'hidden', 'label' => '' ),
				array( 'name' => 'image', 'format' => 'hidden', 'label' => '' ),
				array( 'name' => 'post_id', 'format' => 'hidden', 'label' => '' ),
				array( 'name' => 'priority', 'format' => 'hidden', 'label' => '' ),
				array( 'name' => 'short_description', 'format' => 'hidden', 'label' => '' ),
				array( 'select' => ( self::smart_available() ? 'ec_category.smart_mode' : '0 AS smart_mode' ), 'name' => 'smart_mode', 'format' => 'hidden', 'label' => '' ),
				array( 'select' => "( SELECT p.post_name FROM {$GLOBALS['wpdb']->posts} p WHERE p.ID = ec_category.post_id ) AS slug", 'name' => 'slug', 'format' => 'hidden', 'label' => '' ),
				array( 'select' => '( SELECT COUNT(*) FROM ec_category c3 WHERE c3.category_id = ec_category.parent_id ) AS parent_exists', 'name' => 'parent_exists', 'format' => 'hidden', 'label' => '' ),
			);
		}

		/**
		 * Parent filter choices. The base table renders this as a searchable select, so the
		 * list is capped at the 300 parents with the most subcategories ( plus whichever parent
		 * is currently filtered on ) instead of every parent in the store.
		 *
		 * @since 6.0.0 capped.
		 */
		private function parent_options() {
			global $wpdb;
			$rows = $wpdb->get_results( 'SELECT category_id AS value, category_name AS label, ( SELECT COUNT(*) FROM ec_category c2 WHERE c2.parent_id = ec_category.category_id ) AS kids FROM ec_category HAVING kids > 0 ORDER BY kids DESC, category_name ASC LIMIT 300' );
			$rows = $rows ? $rows : array();
			$current = isset( $_GET['filter_3'] ) ? (int) $_GET['filter_3'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter.
			if ( $current ) {
				$found = false;
				foreach ( $rows as $r ) { if ( (int) $r->value === $current ) { $found = true; break; } }
				if ( ! $found ) {
					$cur = $wpdb->get_row( $wpdb->prepare( 'SELECT category_id AS value, category_name AS label FROM ec_category WHERE category_id = %d', $current ) );
					if ( $cur ) { array_unshift( $rows, $cur ); }
				}
			}
			usort( $rows, function( $a, $b ) { return strcasecmp( (string) $a->label, (string) $b->label ); } );
			return $rows;
		}

		private function compute_health_data() {
			global $wpdb;
			$this->health_data = array(
				'total'    => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_category' ),
				'active'   => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_category WHERE is_active = 1' ),
				'hidden'   => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_category WHERE is_active = 0' ),
				'featured' => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_category WHERE featured_category = 1' ),
				'empty'    => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_category WHERE ' . $this->empty_sql() ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- empty_sql() returns a static SQL fragment.
				'noimage'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM ec_category WHERE image IS NULL OR image = ''" ),
				'orphaned' => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_category WHERE ' . $this->orphan_sql() ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- orphan_sql() returns a static SQL fragment.
			);
		}

		private function empty_sql() {
			return 'NOT EXISTS ( SELECT 1 FROM ec_categoryitem ci WHERE ci.category_id = ec_category.category_id ) AND NOT EXISTS ( SELECT 1 FROM ec_category c2 WHERE c2.parent_id = ec_category.category_id )';
		}
		private function orphan_sql() {
			return 'ec_category.parent_id != 0 AND NOT EXISTS ( SELECT 1 FROM ec_category c3 WHERE c3.category_id = ec_category.parent_id )';
		}

		protected function get_health_filter_where( $filter_key ) {
			switch ( $filter_key ) {
				case 'active': return 'ec_category.is_active = 1';
				case 'hidden': return 'ec_category.is_active = 0';
				case 'featured': return 'ec_category.featured_category = 1';
				case 'empty': return $this->empty_sql();
				case 'noimage': return "( ec_category.image IS NULL OR ec_category.image = '' )";
				case 'orphaned': return $this->orphan_sql();
				case 'smart': return self::smart_available() ? 'ec_category.smart_mode > 0' : '1=0';
			}
			return '';
		}

		protected function get_filter_callback_where( $filter_index, $value ) {
			if ( 2 === $filter_index ) {
				if ( 'empty' === $value ) { return $this->empty_sql(); }
				if ( 'noimage' === $value ) { return "( ec_category.image IS NULL OR ec_category.image = '' )"; }
				if ( 'top' === $value ) { return 'ec_category.parent_id = 0'; }
			}
			return '';
		}

		/* ------------------------------------------------------------------ */
		/* Tree data                                                           */
		/* ------------------------------------------------------------------ */

		/** category_id => parent_id for the whole table ( ints only ), once per request. */
		private function parent_map() {
			if ( null === $this->parent_map ) {
				$this->parent_map = wp_easycart_admin_safe_delete()->parent_map();
			}
			return $this->parent_map;
		}

		/**
		 * Everything the rows about to be printed need, from a handful of queries instead of
		 * one or more per row: the parent map, ancestor names ( flat mode paths and cards ) and
		 * one grouped COUNT of distinct products across each parent row's subtree.
		 *
		 * @param array $rows Row objects ( the page's results, or one AJAX chunk of children ).
		 * @since 6.0.0
		 */
		private function prime_rows( $rows ) {
			global $wpdb;
			$map = $this->parent_map();
			/* ancestors ( names ) */
			$need = array();
			foreach ( $rows as $r ) {
				$cur = isset( $map[ (int) $r->category_id ] ) ? $map[ (int) $r->category_id ] : (int) $r->parent_id;
				$guard = 0;
				while ( $cur && $guard++ < 20 ) {
					if ( ! isset( $this->ancestor_names[ $cur ] ) ) { $need[ $cur ] = true; }
					$cur = isset( $map[ $cur ] ) ? $map[ $cur ] : 0;
				}
			}
			if ( $need ) {
				foreach ( $wpdb->get_results( 'SELECT category_id, category_name FROM ec_category WHERE category_id IN ( ' . implode( ',', array_map( 'intval', array_keys( $need ) ) ) . ' )' ) as $a ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- IN list is an implode of intval() ids.
					$this->ancestor_names[ (int) $a->category_id ] = $a->category_name;
				}
			}
			/*
			 * Subtree product counts: one grouped COUNT( DISTINCT ) over ( root, descendant )
			 * pairs. A root's pairs never split across statements ( a split would double count
			 * a product filed under two of its subcategories ); statements are cut between roots
			 * at roughly 2,000 pairs.
			 */
			$batch = array(); $batch_size = 0;
			$run = function( $pairs ) use ( $wpdb ) {
				$counts = $wpdb->get_results( 'SELECT m.root, COUNT( DISTINCT ci.product_id ) AS n FROM ( ' . implode( ' UNION ALL ', $pairs ) . ' ) m JOIN ec_categoryitem ci ON ci.category_id = m.cid GROUP BY m.root' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- the derived table is built solely from (int) category ids.
				foreach ( $counts as $c ) { $this->tree_counts[ (int) $c->root ] = (int) $c->n; }
			};
			foreach ( $rows as $r ) {
				$id = (int) $r->category_id;
				if ( empty( $r->child_count ) || isset( $this->tree_counts[ $id ] ) ) { continue; }
				$this->tree_counts[ $id ] = 0;
				$pairs = array();
				foreach ( wp_easycart_admin_safe_delete()->descendant_ids( $id, $map ) as $d ) {
					$pairs[] = 'SELECT ' . $id . ' AS root, ' . (int) $d . ' AS cid';
				}
				if ( $batch && $batch_size + count( $pairs ) > 2000 ) { $run( $batch ); $batch = array(); $batch_size = 0; }
				$batch = array_merge( $batch, $pairs ); $batch_size += count( $pairs );
			}
			if ( $batch ) { $run( $batch ); }
		}

		/** Products in this category or any descendant ( distinct ). Answered from the primed cache. */
		private function tree_product_count( $category_id ) {
			$id = (int) $category_id;
			if ( ! isset( $this->tree_counts[ $id ] ) ) {
				$this->prime_rows( array( (object) array( 'category_id' => $id, 'parent_id' => 0, 'child_count' => 1 ) ) );
			}
			return (int) $this->tree_counts[ $id ];
		}

		private function path_for( $category_id ) {
			$map = $this->parent_map();
			$names = array(); $cur = (int) $category_id; $guard = 0;
			while ( isset( $map[ $cur ] ) && $map[ $cur ] && $guard++ < 20 ) {
				$cur = $map[ $cur ];
				if ( ! isset( $this->ancestor_names[ $cur ] ) ) {
					$this->prime_rows( array( (object) array( 'category_id' => $category_id, 'parent_id' => $map[ (int) $category_id ], 'child_count' => 0 ) ) );
				}
				if ( isset( $this->ancestor_names[ $cur ] ) ) { array_unshift( $names, $this->ancestor_names[ $cur ] ); }
			}
			return $names;
		}

		/* ------------------------------------------------------------------ */
		/* Rendering                                                           */
		/* ------------------------------------------------------------------ */

		protected function print_table_view() {
			$this->prime_rows( (array) $this->results );
			echo '<div class="ecv2-table-scroll ecv2-tree-scroll" data-tree="' . ( $this->tree_mode ? '1' : '0' ) . '" data-children-action="ecv2_category_children" data-children-limit="' . (int) self::CHILDREN_PER_PAGE . '">';
			echo '<table class="ecv2-table ecv2-cat-table" id="' . esc_attr( $this->table_id ) . '">';
			$this->print_table_thead();
			echo '<tbody id="ecv2-cat-tbody">';
			if ( empty( $this->results ) ) {
				echo '<tr><td colspan="' . ( count( $this->list_columns ) + 2 ) . '" class="ecv2-empty-state"><span class="dashicons dashicons-info-outline"></span> ' . esc_html__( 'No categories found.', 'wp-easycart' ) . '</td></tr>';
			}
			foreach ( $this->results as $result ) {
				$this->print_table_row( $result );
			}
			echo '</tbody></table></div>';
		}

		protected function print_card_view() {
			$this->prime_rows( (array) $this->results );
			parent::print_card_view();
		}

		/** Children per expand request; a "Show more" row fetches the next batch. */
		const CHILDREN_PER_PAGE = 200;

		/**
		 * One row. In tree mode a parent row prints collapsed with data-loaded="0"; catalog-v2.js
		 * fetches its children through ecv2_category_children on the first expand.
		 */
		protected function print_table_row( $result, $level = 0 ) {
			$id        = (int) $result->category_id;
			$kid_count = $this->tree_mode ? (int) $result->child_count : 0;
			$cls       = 'ecv2-row ecv2-cat-row' . ( $result->is_active ? '' : ' ecv2-row-inactive' ) . ( $kid_count ? ' has-children' : '' );
			echo '<tr class="' . esc_attr( $cls ) . '" data-id="' . esc_attr( $id ) . '" data-parent="' . esc_attr( (int) $result->parent_id ) . '" data-level="' . esc_attr( $level ) . '"' . ( $kid_count ? ' data-loaded="0" data-children="' . esc_attr( $kid_count ) . '"' : '' ) . ' draggable="true">';
			echo '<td class="ecv2-col-check"><input type="checkbox" name="bulk[]" value="' . esc_attr( $id ) . '" class="ecv2-row-check" /></td>';
			foreach ( $this->list_columns as $col ) {
				if ( isset( $col['format'] ) && 'hidden' === $col['format'] ) { continue; }
				$extra = '';
				if ( ! empty( $col['tablet_hide'] ) ) { $extra .= ' ecv2-hide-tablet'; }
				if ( ! empty( $col['laptop_hide'] ) ) { $extra .= ' ecv2-hide-laptop'; }
				echo '<td class="ecv2-cell ecv2-cell-' . esc_attr( $col['name'] ) . $extra . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $extra only ever holds literal class names set above.
				if ( 'cat_name' === $col['format'] ) {
					$this->print_name_cell( $result, $level, $kid_count );
				} else {
					$this->print_cell_content( $result, $col );
				}
				echo '</td>';
			}
			echo '<td class="ecv2-col-actions">';
			$this->print_row_actions( $result );
			echo '</td></tr>';
		}

		/**
		 * Children of one category as table rows, for ecv2_category_children.
		 * Returns array( 'html' => string, 'more' => bool, 'next_offset' => int ).
		 *
		 * @since 6.0.0
		 */
		public function render_children( $parent_id, $offset, $level ) {
			global $wpdb;
			$select = array();
			foreach ( $this->list_columns as $col ) {
				$select[] = isset( $col['select'] ) ? $col['select'] : 'ec_category.' . $col['name'];
			}
			$limit = self::CHILDREN_PER_PAGE;
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT ' . implode( ', ', $select ) . ' FROM ec_category WHERE ec_category.parent_id = %d ORDER BY ec_category.priority DESC, ec_category.category_name ASC LIMIT %d OFFSET %d', (int) $parent_id, $limit + 1, max( 0, (int) $offset ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $select is built from the developer-defined list_columns config in columns(); values are placeholders.
			$more = count( $rows ) > $limit;
			if ( $more ) { array_pop( $rows ); }
			$this->prime_rows( $rows );
			ob_start();
			foreach ( $rows as $r ) { $this->print_table_row( $r, (int) $level ); }
			return array( 'html' => ob_get_clean(), 'more' => $more, 'next_offset' => (int) $offset + count( $rows ) );
		}

		private function print_name_cell( $result, $level, $kid_count ) {
			echo '<div class="ecv2-tree" style="--lvl:' . (int) $level . '">';
			if ( $this->tree_mode ) {
				echo '<span class="ecv2-drag-handle" title="' . esc_attr__( 'Drag to reorder, drop on a category to nest', 'wp-easycart' ) . '"><span class="dashicons dashicons-menu"></span></span>';
				if ( $kid_count ) {
					/* collapsed: children load on first expand ( ecv2_category_children ) */
					echo '<button type="button" class="ecv2-tree-toggle" aria-expanded="false" onclick="ecv2_catalog.toggle_tree( this );" title="' . esc_attr( sprintf( _n( 'Show %d subcategory', 'Show %d subcategories', $kid_count, 'wp-easycart' ), $kid_count ) ) . '"><span class="dashicons dashicons-arrow-right-alt2"></span></button>';
				} else {
					echo '<span class="ecv2-tree-toggle is-leaf"></span>';
				}
			}
			$this->print_thumb( $result );
			echo '<div class="ecv2-tree-text">';
			echo '<a href="' . esc_url( self::editor_url( $result->category_id ) ) . '" class="ecv2-link-primary ecv2-title-link">' . esc_html( wp_unslash( $result->category_name ) ) . '</a>';
			if ( ! empty( $result->smart_mode ) ) {
				$paused = ! ( class_exists( 'ec_smart_categories_support' ) && ec_smart_categories_support::enabled() );
				echo ' <span class="ecv2-chip ' . ( $paused ? 'ecv2-chip-amber' : 'ecv2-chip-blue' ) . '" title="' . esc_attr( $paused ? __( 'Rule-driven category — rules are paused because WP EasyCart PRO is not active. Existing members are kept.', 'wp-easycart' ) : ( 2 === (int) $result->smart_mode ? __( 'Filled by rules plus hand-picked products', 'wp-easycart' ) : __( 'Filled by rules', 'wp-easycart' ) ) ) . '">' . esc_html( $paused ? __( 'Smart · paused', 'wp-easycart' ) : __( 'Smart', 'wp-easycart' ) ) . '</span>';
			}
			$subs = array();
			if ( ! $this->tree_mode ) {
				$path = $this->path_for( $result->category_id );
				if ( $path ) { $subs[] = implode( ' › ', array_map( 'esc_html', $path ) ) . ' ›'; }
			}
			if ( ! empty( $result->slug ) ) { $subs[] = '<span class="ecv2-mono">/' . esc_html( $result->slug ) . '/</span>'; }
			if ( (int) $result->parent_id && empty( $result->parent_exists ) ) { $subs[] = '<span class="ecv2-sub-danger">' . esc_html__( 'Parent category was deleted — now top level', 'wp-easycart' ) . '</span>'; }
			if ( empty( $result->image ) ) { $subs[] = '<span class="ecv2-sub-warn">' . esc_html__( 'No image', 'wp-easycart' ) . '</span>'; }
			if ( $subs ) { echo '<span class="ecv2-sub">' . implode( ' · ', $subs ) . '</span>'; } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every $subs entry is built from esc_html()/esc_html__() and literal markup above.
			echo '</div></div>';
		}

		private function print_thumb( $result, $lg = false ) {
			$url = $this->image_url( $result->image );
			echo '<span class="ecv2-thumb' . ( $lg ? ' ecv2-thumb-lg' : '' ) . '">';
			if ( $url ) { echo '<img src="' . esc_url( $url ) . '" alt="" loading="lazy" />'; } else { echo '<span class="dashicons dashicons-format-image"></span>'; }
			echo '</span>';
		}

		public static function image_url( $image ) {
			if ( empty( $image ) ) { return ''; }
			if ( 0 === strpos( $image, 'http' ) ) { return $image; }
			return plugins_url( '/wp-easycart-data/products/categories/' . ltrim( $image, '/' ), EC_PLUGIN_DATA_DIRECTORY );
		}

		protected function print_cell_content( $result, $col ) {
			switch ( $col['format'] ) {
				case 'cat_products':
					$direct = (int) $result->direct_products;
					$url    = admin_url( 'admin.php?page=wp-easycart-products&subpage=products&filter_2=' . (int) $result->category_id );
					if ( $direct ) {
						echo '<a class="ecv2-usage" href="' . esc_url( $url ) . '">' . esc_html( sprintf( _n( '%d product', '%d products', $direct, 'wp-easycart' ), $direct ) ) . '</a>';
					} else {
						echo '<span class="ecv2-usage ecv2-usage-zero">' . esc_html__( '0 products', 'wp-easycart' ) . '</span> <a class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" href="#" onclick="ecv2_catalog.pick_products( ' . (int) $result->category_id . ' ); return false;">' . esc_html__( 'Add', 'wp-easycart' ) . '</a>';
					}
					if ( (int) $result->child_count ) {
						$tree = $this->tree_product_count( $result->category_id );
						if ( $tree !== $direct ) {
							echo '<span class="ecv2-sub">' . esc_html( sprintf( __( '%d incl. subcategories', 'wp-easycart' ), $tree ) ) . '</span>';
						}
					}
					break;
				case 'cat_children':
					echo (int) $result->child_count ? '<span class="ecv2-chip ecv2-chip-gray">' . (int) $result->child_count . '</span>' : '<span class="ecv2-sub">—</span>';
					break;
				case 'cat_toggle':
					$field = $col['name'];
					echo '<label class="ecv2-toggle ecv2-toggle-sm" title="' . esc_attr( 'is_active' === $field ? __( 'Active in store', 'wp-easycart' ) : __( 'Featured', 'wp-easycart' ) ) . '"><input type="checkbox" class="ecv2-category-toggle" data-id="' . esc_attr( $result->category_id ) . '" data-field="' . esc_attr( $field ) . '"' . ( $result->{ $field } ? ' checked' : '' ) . ' /><span class="ecv2-toggle-slider"></span></label>';
					break;
				default:
					parent::print_cell_content( $result, $col );
			}
		}

		protected function print_card( $result ) {
			$url = $this->image_url( $result->image );
			$path = $this->path_for( $result->category_id );
			echo '<div class="ecv2-card ecv2-cat-card' . ( $result->is_active ? '' : ' ecv2-card-inactive' ) . '" data-id="' . esc_attr( $result->category_id ) . '">';
			echo '<div class="ecv2-card-image ecv2-cat-card-image">';
			if ( $url ) { echo '<img src="' . esc_url( $url ) . '" alt="" loading="lazy" />'; } else { echo '<div class="ecv2-card-image-placeholder"><span class="dashicons dashicons-format-image"></span></div>'; }
			echo '<div class="ecv2-card-toggle"><label class="ecv2-toggle ecv2-toggle-sm"><input type="checkbox" class="ecv2-category-toggle" data-id="' . esc_attr( $result->category_id ) . '" data-field="is_active"' . ( $result->is_active ? ' checked' : '' ) . ' /><span class="ecv2-toggle-slider"></span></label></div>';
			echo '</div><div class="ecv2-card-body">';
			echo '<h3 class="ecv2-card-title"><a href="' . esc_url( self::editor_url( $result->category_id ) ) . '">' . esc_html( wp_unslash( $result->category_name ) ) . '</a>';
			if ( $result->featured_category ) { echo ' <span class="ecv2-chip ecv2-chip-brand">' . esc_html__( 'Featured', 'wp-easycart' ) . '</span>'; }
			echo '</h3>';
			echo '<span class="ecv2-sub">' . ( $path ? esc_html( implode( ' › ', $path ) . ' › ' ) : esc_html__( 'Top level', 'wp-easycart' ) ) . ( (int) $result->child_count ? ' · ' . esc_html( sprintf( _n( '%d subcategory', '%d subcategories', (int) $result->child_count, 'wp-easycart' ), (int) $result->child_count ) ) : '' ) . '</span>';
			echo '</div><div class="ecv2-card-footer"><input type="checkbox" name="bulk[]" value="' . esc_attr( $result->category_id ) . '" class="ecv2-row-check" /> <span class="ecv2-sub">' . esc_html( sprintf( _n( '%d product', '%d products', (int) $result->direct_products, 'wp-easycart' ), (int) $result->direct_products ) ) . '</span>';
			$this->print_row_actions( $result );
			echo '</div></div>';
		}
	}

endif;

/* ---------------------------------------------------------------------- */
/* AJAX                                                                    */
/* ---------------------------------------------------------------------- */

function ecv2_category_guard() {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
	}
	check_ajax_referer( 'wp-easycart-ecv2-inline-update', 'wp_easycart_nonce' );
}

/** Sync the WP post's status with is_active ( mirrors update_category() ). */
function ecv2_category_sync_post_status( $category_id ) {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT post_id, is_active FROM ec_category WHERE category_id = %d', $category_id ) );
	if ( $row && $row->post_id ) {
		wp_update_post( array( 'ID' => (int) $row->post_id, 'post_status' => $row->is_active ? 'publish' : 'private' ) );
	}
}

/**
 * Children of one category as rendered table rows, for the lazy tree.
 * POST: parent_id, offset, level ( depth of the child rows ), wp_easycart_nonce
 * ( 'wp-easycart-ecv2-inline-update' ). Answers { html, more, next_offset }; 200 rows per call.
 *
 * @since 6.0.0
 */
add_action( 'wp_ajax_ecv2_category_children', 'ecv2_category_children' );
function ecv2_category_children() {
	ecv2_category_guard();
	$parent = isset( $_POST['parent_id'] ) ? (int) $_POST['parent_id'] : 0;
	$offset = isset( $_POST['offset'] ) ? max( 0, (int) $_POST['offset'] ) : 0;
	$level  = isset( $_POST['level'] ) ? max( 1, min( 50, (int) $_POST['level'] ) ) : 1;
	if ( ! $parent ) { wp_send_json_error( array( 'message' => __( 'Invalid category.', 'wp-easycart' ) ) ); }
	wp_easycart_admin_category_table::$lightweight = true;
	$table = new wp_easycart_admin_category_table();
	wp_send_json_success( $table->render_children( $parent, $offset, $level ) );
}

add_action( 'wp_ajax_ecv2_category_toggle', 'ecv2_category_toggle' );
function ecv2_category_toggle() {
	ecv2_category_guard();
	global $wpdb;
	$id    = isset( $_POST['category_id'] ) ? (int) $_POST['category_id'] : 0;
	$field = isset( $_POST['field'] ) ? sanitize_key( $_POST['field'] ) : '';
	$val   = ! empty( $_POST['value'] ) && '0' !== $_POST['value'] ? 1 : 0;
	if ( ! $id || ! in_array( $field, array( 'is_active', 'featured_category' ), true ) ) {
		wp_send_json_error( array( 'message' => __( 'Field not allowed.', 'wp-easycart' ) ) );
	}
	$wpdb->update( 'ec_category', array( $field => $val ), array( 'category_id' => $id ) );
	if ( 'is_active' === $field ) { ecv2_category_sync_post_status( $id ); }
	do_action( 'wpeasycart_category_updated', $id );
	wp_send_json_success( array( 'value' => $val ) );
}

/**
 * Move: set a new parent and rewrite sibling priorities from an ordered id list.
 * POST: category_id, parent_id, order[] ( ids of the new sibling group, top to bottom ).
 * Refuses cycles ( a category can't become its own descendant ).
 */
add_action( 'wp_ajax_ecv2_category_move', 'ecv2_category_move' );
function ecv2_category_move() {
	ecv2_category_guard();
	global $wpdb;
	$id     = isset( $_POST['category_id'] ) ? (int) $_POST['category_id'] : 0;
	$parent = isset( $_POST['parent_id'] ) ? (int) $_POST['parent_id'] : 0;
	$order  = isset( $_POST['order'] ) && is_array( $_POST['order'] ) ? array_map( 'intval', $_POST['order'] ) : array();
	if ( ! $id ) { wp_send_json_error( array( 'message' => __( 'Invalid category.', 'wp-easycart' ) ) ); }
	if ( $parent === $id || in_array( $parent, wp_easycart_admin_safe_delete()->descendant_ids( $id ), true ) ) {
		wp_send_json_error( array( 'message' => __( 'A category cannot be moved inside itself.', 'wp-easycart' ) ) );
	}
	if ( $parent && ! $wpdb->get_var( $wpdb->prepare( 'SELECT category_id FROM ec_category WHERE category_id = %d', $parent ) ) ) {
		wp_send_json_error( array( 'message' => __( 'Target category not found.', 'wp-easycart' ) ) );
	}
	$old_parent = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT parent_id FROM ec_category WHERE category_id = %d', $id ) );
	$wpdb->update( 'ec_category', array( 'parent_id' => $parent ), array( 'category_id' => $id ) );
	if ( empty( $order ) ) {
		$order = $wpdb->get_col( $wpdb->prepare( 'SELECT category_id FROM ec_category WHERE parent_id = %d ORDER BY priority DESC, category_name ASC', $parent ) );
	}
	$n = count( $order );
	foreach ( $order as $i => $cid ) {
		$wpdb->update( 'ec_category', array( 'priority' => $n - $i ), array( 'category_id' => $cid, 'parent_id' => $parent ) );
	}
	do_action( 'wpeasycart_category_updated', $id );
	wp_send_json_success( array( 'category_id' => $id, 'parent_id' => $parent, 'old_parent_id' => $old_parent ) );
}

add_action( 'wp_ajax_ecv2_category_create', 'ecv2_category_create' );
function ecv2_category_create() {
	ecv2_category_guard();
	global $wpdb;
	$name   = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$parent = isset( $_POST['parent_id'] ) ? (int) $_POST['parent_id'] : 0;
	$active = isset( $_POST['is_active'] ) ? ( '0' !== $_POST['is_active'] ? 1 : 0 ) : 1;
	$image  = isset( $_POST['image'] ) ? sanitize_text_field( wp_unslash( $_POST['image'] ) ) : '';
	if ( '' === $name ) { wp_send_json_error( array( 'message' => __( 'Name is required.', 'wp-easycart' ) ) ); }
	$priority = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE( MAX( priority ), 0 ) FROM ec_category WHERE parent_id = %d', $parent ) ) + 1;
	$wpdb->insert( 'ec_category', array( 'is_active' => $active, 'featured_category' => 0, 'category_name' => $name, 'parent_id' => $parent, 'image' => $image, 'short_description' => '', 'priority' => $priority ) );
	$category_id = (int) $wpdb->insert_id;
	$post_id = wp_easycart_post_sync()->insert( 'category', $category_id, array(
		'post_content' => '[ec_store groupid="' . $category_id . '"]',
		'post_status'  => $active ? 'publish' : 'private',
		'post_title'   => wp_easycart_language()->convert_text( $name ),
		'post_type'    => 'ec_store',
		'post_excerpt' => '',
	) );
	if ( $post_id ) {
		wp_set_post_tags( $post_id, array( 'category' ), true );
		$wpdb->update( 'ec_category', array( 'post_id' => (int) $post_id ), array( 'category_id' => $category_id ) );
	}
	do_action( 'wpeasycart_category_added', $category_id );
	wp_send_json_success( array( 'category_id' => $category_id, 'edit_url' => wp_easycart_admin_category_table::editor_url( $category_id ), 'url' => $post_id ? get_permalink( $post_id ) : '' ) );
}

add_action( 'wp_ajax_ecv2_category_bulk', 'ecv2_category_bulk' );
function ecv2_category_bulk() {
	ecv2_category_guard();
	global $wpdb;
	$ids = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ? array_slice( array_map( 'intval', $_POST['ids'] ), 0, 200 ) : array();
	$op  = isset( $_POST['op'] ) ? sanitize_key( $_POST['op'] ) : '';
	$target = isset( $_POST['target_id'] ) ? (int) $_POST['target_id'] : 0;
	if ( empty( $ids ) ) { wp_send_json_error( array( 'message' => __( 'Nothing selected.', 'wp-easycart' ) ) ); }
	$done = 0; $trash = array(); $errors = array();
	foreach ( $ids as $id ) {
		switch ( $op ) {
			case 'activate': case 'deactivate':
				$wpdb->update( 'ec_category', array( 'is_active' => 'activate' === $op ? 1 : 0 ), array( 'category_id' => $id ) );
				ecv2_category_sync_post_status( $id ); $done++; break;
			case 'feature': case 'unfeature':
				$wpdb->update( 'ec_category', array( 'featured_category' => 'feature' === $op ? 1 : 0 ), array( 'category_id' => $id ) ); $done++; break;
			case 'move':
				if ( $target === $id || in_array( $target, wp_easycart_admin_safe_delete()->descendant_ids( $id ), true ) ) { $errors[] = sprintf( __( 'Category #%d cannot be moved inside itself.', 'wp-easycart' ), $id ); break; }
				$wpdb->update( 'ec_category', array( 'parent_id' => $target ), array( 'category_id' => $id ) ); $done++; break;
			case 'delete':
				$r = wp_easycart_admin_safe_delete()->execute( 'category', $id, array( 'strategy' => 'lift', 'redirect' => true ) );
				if ( is_wp_error( $r ) ) { $errors[] = $r->get_error_message(); } else { $done++; $trash[] = $r['trash_id']; }
				break;
		}
	}
	wp_send_json_success( array( 'done' => $done, 'trash_ids' => $trash, 'errors' => $errors ) );
}

/** Lightweight product search for the "Add products" picker ( shared with the editor ). */
add_action( 'wp_ajax_ecv2_category_product_search', 'ecv2_category_product_search' );
function ecv2_category_product_search() {
	ecv2_category_guard();
	global $wpdb;
	$q     = isset( $_POST['q'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['q'] ) ) ) : '';
	$cat   = isset( $_POST['category_id'] ) ? (int) $_POST['category_id'] : 0;
	$scope = isset( $_POST['scope'] ) ? sanitize_key( $_POST['scope'] ) : 'notin';
	$where = 'WHERE 1=1';
	if ( '' !== $q ) { $where .= $wpdb->prepare( ' AND ( p.title LIKE %s OR p.model_number LIKE %s )', '%' . $wpdb->esc_like( $q ) . '%', '%' . $wpdb->esc_like( $q ) . '%' ); }
	if ( 'notin' === $scope && $cat ) { $where .= $wpdb->prepare( ' AND NOT EXISTS ( SELECT 1 FROM ec_categoryitem ci WHERE ci.product_id = p.product_id AND ci.category_id = %d )', $cat ); }
	if ( 'uncategorized' === $scope ) { $where .= ' AND NOT EXISTS ( SELECT 1 FROM ec_categoryitem ci WHERE ci.product_id = p.product_id )'; }
	$rows = $wpdb->get_results( "SELECT p.product_id, p.title, p.model_number, p.price, p.activate_in_store, " . wp_easycart_admin_catalog_v2_thumb_select( "p" ) . ", ( SELECT GROUP_CONCAT( c.category_name ORDER BY c.category_name SEPARATOR ', ' ) FROM ec_categoryitem ci LEFT JOIN ec_category c ON c.category_id = ci.category_id WHERE ci.product_id = p.product_id ) AS cats FROM ec_product p $where ORDER BY p.title ASC LIMIT 40" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is literal SQL plus $wpdb->prepare() fragments; thumb_select() returns static column SQL.
	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM ec_product p $where" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is literal SQL plus $wpdb->prepare() fragments.
	$out = array();
	foreach ( $rows as $r ) {
		$out[] = array(
			'id'     => (int) $r->product_id,
			'title'  => wp_unslash( $r->title ),
			'sku'    => $r->model_number,
			'price'  => $GLOBALS['currency']->get_currency_display( $r->price ),
			'image'  => wp_easycart_admin_catalog_v2_product_thumb( $r ),
			'active' => (bool) $r->activate_in_store,
			'cats'   => $r->cats ? $r->cats : '',
		);
	}
	wp_send_json_success( array( 'items' => $out, 'total' => $total ) );
}

add_action( 'wp_ajax_ecv2_category_add_products', 'ecv2_category_add_products' );
function ecv2_category_add_products() {
	ecv2_category_guard();
	global $wpdb;
	$cat = isset( $_POST['category_id'] ) ? (int) $_POST['category_id'] : 0;
	$ids = isset( $_POST['product_ids'] ) && is_array( $_POST['product_ids'] ) ? array_map( 'intval', $_POST['product_ids'] ) : array();
	if ( ! $cat || empty( $ids ) ) { wp_send_json_error( array( 'message' => __( 'Nothing to add.', 'wp-easycart' ) ) ); }
	$added = 0;
	foreach ( $ids as $pid ) {
		$exists = $wpdb->get_var( $wpdb->prepare( 'SELECT categoryitem_id FROM ec_categoryitem WHERE category_id = %d AND product_id = %d', $cat, $pid ) );
		if ( ! $exists && $wpdb->get_var( $wpdb->prepare( 'SELECT product_id FROM ec_product WHERE product_id = %d', $pid ) ) ) {
			$wpdb->insert( 'ec_categoryitem', array( 'category_id' => $cat, 'product_id' => $pid ) );
			$added++;
		}
	}
	do_action( 'wpeasycart_category_updated', $cat );
	wp_send_json_success( array( 'added' => $added ) );
}

add_action( 'wp_ajax_ecv2_category_remove_product', 'ecv2_category_remove_product' );
function ecv2_category_remove_product() {
	ecv2_category_guard();
	global $wpdb;
	$cat = isset( $_POST['category_id'] ) ? (int) $_POST['category_id'] : 0;
	$pid = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
	$excluded = class_exists( 'ec_smart_categories_support' ) && ec_smart_categories_support::exclude_product( $cat, $pid );
	$wpdb->delete( 'ec_categoryitem', array( 'category_id' => $cat, 'product_id' => $pid ) );
	do_action( 'wpeasycart_category_updated', $cat );
	wp_send_json_success( array( 'excluded' => $excluded ) );
}
