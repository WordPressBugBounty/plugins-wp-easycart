<?php
/**
 * WP EasyCart Admin Inventory Table (V2)
 *
 * Modern inventory grid built on the wp_easycart_admin_table_v2 framework.
 * Unlike the other V2 tables, inventory rows come from a UNION of two
 * grains: basic/untracked products ( ec_product ) and per-variant rows
 * ( ec_optionitemquantity ), so this class overrides the query builder,
 * counting, filtering and sorting entirely while inheriting the shared
 * rendering shell ( header, stats, toolbar, drawer, pagination, modals ).
 *
 * Row keys are strings: "p{product_id}" for product-level rows and
 * "v{optionitemquantity_id}" for variant rows.
 *
 * PRO integration points ( free plugin renders locked UI when absent ):
 *  - filter 'wp_easycart_admin_inventory_pro_enabled'   PRO flips true when licensed.
 *  - filter 'wp_easycart_admin_inventory_columns'        PRO appends columns.
 *  - filter 'wp_easycart_admin_inventory_select'         PRO appends per-branch SELECT SQL.
 *  - filter 'wp_easycart_admin_inventory_health_stats'   PRO appends stat cards.
 *  - filter 'wp_easycart_admin_inventory_health_where'   PRO resolves its stat card filters.
 *  - action 'wp_easycart_admin_inventory_cell_{name}'    PRO prints its column cells.
 *  - action 'wp_easycart_admin_inventory_row_menu'       PRO prints live row-menu items.
 *  - action 'wp_easycart_admin_inventory_toolbar'        PRO adds toolbar buttons ( import, digest ).
 *  - action 'wp_easycart_admin_ecv2_render_modals'       ( fired by base ) PRO renders its modals.
 *
 * @since 6.x.x
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_inventory_table' ) ) :

	class wp_easycart_admin_inventory_table extends wp_easycart_admin_table_v2 {

		/** @var array Health counts computed once per request. */
		protected $health_data = array();

		/** @var array Gate info from wp_easycart_admin_pro_gate. */
		protected $pro_gate;

		/** @var bool Convenience flag: PRO inventory features live. */
		protected $pro_enabled = false;

		public function __construct() {
			parent::__construct();

			$this->pro_gate = wp_easycart_inventory_pro_gate();
			$this->pro_enabled = ( 'enabled' === $this->pro_gate['state'] );

			$this->set_table( 'ec_product', 'row_key' );
			$this->set_table_id( 'ecv2-inventory-table' );
			$this->set_header( __( 'Inventory', 'wp-easycart' ) );
			$this->set_icon( 'performance' );
			$this->set_label( __( 'item', 'wp-easycart' ), __( 'items', 'wp-easycart' ) );
			$this->set_default_sort( 'title', 'asc' );
			$this->set_docs_link( 'products', 'inventory' );
			$this->set_view_modes( array( 'table' ) );

			/* Export CSV rides the standard add-new slot. */
			$this->set_add_new( true, 'export-inventory-list', __( 'Export CSV', 'wp-easycart' ) );
			$this->set_add_new_css( 'ecv2-btn ecv2-btn-ghost' );

			/* No form-submitting bulk actions; PRO uses its own bulk modal. */
			$this->set_bulk_actions( array() );

			$this->set_search_columns( array( 'inv.title', 'inv.sku', 'inv.variant_label' ) );

			$columns = array(
				array( 'name' => 'image1', 'label' => '', 'width' => 54 ),
				array( 'name' => 'title', 'label' => __( 'Item', 'wp-easycart' ) ),
				array( 'name' => 'sku', 'label' => __( 'SKU', 'wp-easycart' ), 'laptop_hide' => true ),
				array( 'name' => 'row_type', 'label' => __( 'Type', 'wp-easycart' ), 'tablet_hide' => true ),
				array( 'name' => 'quantity', 'label' => __( 'On Hand', 'wp-easycart' ) ),
			);
			/* PRO appends: committed, available, reorder_point, subscribers. */
			$columns = apply_filters( 'wp_easycart_admin_inventory_columns', $columns, $this->pro_enabled );
			$this->set_list_columns( $columns );

			$this->set_filters( array(
				array(
					'label' => __( 'Stock Status', 'wp-easycart' ),
					'data'  => array(
						(object) array( 'value' => 'in_stock', 'label' => __( 'In Stock', 'wp-easycart' ), 'icon' => 'yes-alt' ),
						(object) array( 'value' => 'low_stock', 'label' => __( 'Low Stock', 'wp-easycart' ), 'icon' => 'warning' ),
						(object) array( 'value' => 'out_of_stock', 'label' => __( 'Out of Stock', 'wp-easycart' ), 'icon' => 'dismiss' ),
						(object) array( 'value' => 'untracked', 'label' => __( 'Not Tracked', 'wp-easycart' ), 'icon' => 'marker' ),
					),
				),
				array(
					'label' => __( 'Item Type', 'wp-easycart' ),
					'data'  => array(
						(object) array( 'value' => 'basic', 'label' => __( 'Simple Products', 'wp-easycart' ) ),
						(object) array( 'value' => 'variant', 'label' => __( 'Variants', 'wp-easycart' ) ),
					),
				),
				array(
					'label' => __( 'Product Visibility', 'wp-easycart' ),
					'data'  => array(
						(object) array( 'value' => 'active', 'label' => __( 'Active Only', 'wp-easycart' ) ),
						(object) array( 'value' => 'inactive', 'label' => __( 'Hidden Only', 'wp-easycart' ) ),
					),
				),
			) );

			$this->build_health_stats();
		}

		/* ------------------------------------------------------------------ */
		/* Shared helpers                                                       */
		/* ------------------------------------------------------------------ */

		public static function low_stock_threshold() {
			$threshold = (int) get_option( 'ec_option_inventory_low_stock_threshold' );
			return ( $threshold > 0 ) ? $threshold : 10;
		}

		/**
		 * Extra SELECT SQL contributed by PRO for each UNION branch.
		 * Returns array( 'basic' => '', 'variant' => '' ) with leading commas.
		 */
		protected function get_extra_select() {
			$extra = apply_filters( 'wp_easycart_admin_inventory_select', array( 'basic' => '', 'variant' => '' ), $this->pro_enabled );
			if ( ! is_array( $extra ) ) {
				$extra = array();
			}
			return array(
				'basic'   => isset( $extra['basic'] ) ? $extra['basic'] : '',
				'variant' => isset( $extra['variant'] ) ? $extra['variant'] : '',
			);
		}

		/**
		 * The two UNION branches. Every alias here is part of the row
		 * contract used by sorting, filtering and cell printers.
		 */
		protected function get_union_sql() {
			$extra = $this->get_extra_select();

			$basic = "SELECT CONCAT( 'p', p.product_id ) AS row_key,
				'basic' AS row_type,
				p.product_id,
				0 AS oiq_id,
				p.title,
				'' AS variant_label,
				p.model_number AS sku,
				p.image1,
				p.stock_quantity AS quantity,
				p.show_stock_quantity AS tracked,
				p.activate_in_store,
				( CASE WHEN p.square_id != '' OR p.square_variation_id != '' THEN 1 ELSE 0 END ) AS square_locked"
				. $extra['basic'] . '
				FROM ec_product p
				WHERE p.use_optionitem_quantity_tracking = 0';

			$variant = "SELECT CONCAT( 'v', q.optionitemquantity_id ) AS row_key,
				'variant' AS row_type,
				p.product_id,
				q.optionitemquantity_id AS oiq_id,
				p.title,
				CONCAT_WS( ', ', oi1.optionitem_name, oi2.optionitem_name, oi3.optionitem_name, oi4.optionitem_name, oi5.optionitem_name ) AS variant_label,
				( CASE WHEN q.sku != '' THEN q.sku ELSE p.model_number END ) AS sku,
				p.image1,
				q.quantity,
				q.is_stock_tracking_enabled AS tracked,
				p.activate_in_store,
				( CASE WHEN q.square_id != '' OR p.square_id != '' THEN 1 ELSE 0 END ) AS square_locked"
				. $extra['variant'] . '
				FROM ec_optionitemquantity q
				INNER JOIN ec_product p ON p.product_id = q.product_id AND p.use_optionitem_quantity_tracking = 1
				LEFT JOIN ec_optionitem oi1 ON oi1.optionitem_id = q.optionitem_id_1
				LEFT JOIN ec_optionitem oi2 ON oi2.optionitem_id = q.optionitem_id_2
				LEFT JOIN ec_optionitem oi3 ON oi3.optionitem_id = q.optionitem_id_3
				LEFT JOIN ec_optionitem oi4 ON oi4.optionitem_id = q.optionitem_id_4
				LEFT JOIN ec_optionitem oi5 ON oi5.optionitem_id = q.optionitem_id_5
				WHERE q.is_enabled = 1';

			return '( ' . $basic . ' UNION ALL ' . $variant . ' ) inv';
		}

		/**
		 * WHERE conditions applied to the outer, unified row set.
		 */
		protected function get_outer_where() {
			$threshold = self::low_stock_threshold();
			$where = ' WHERE 1=1';

			/* Health / stat-card filter. */
			if ( isset( $_GET['health_filter'] ) && '' != $_GET['health_filter'] ) {
				$health_where = $this->get_health_filter_where( sanitize_key( $_GET['health_filter'] ) );
				if ( $health_where ) {
					$where .= ' AND ( ' . $health_where . ' )';
				}
			}

			/* filter_0: stock status. */
			if ( isset( $_GET['filter_0'] ) && '' != $_GET['filter_0'] ) {
				$status_where = $this->get_stock_status_where( sanitize_key( $_GET['filter_0'] ), $threshold );
				if ( $status_where ) {
					$where .= ' AND ( ' . $status_where . ' )';
				}
			}

			/* filter_1: item type. */
			if ( isset( $_GET['filter_1'] ) && '' != $_GET['filter_1'] ) {
				$type = sanitize_key( $_GET['filter_1'] );
				if ( in_array( $type, array( 'basic', 'variant' ), true ) ) {
					$where .= $this->wpdb->prepare( ' AND inv.row_type = %s', $type );
				}
			}

			/* filter_2: visibility. Empty = all products. */
			if ( isset( $_GET['filter_2'] ) && '' != $_GET['filter_2'] ) {
				$visibility = sanitize_key( $_GET['filter_2'] );
				if ( 'active' === $visibility ) {
					$where .= ' AND inv.activate_in_store = 1';
				} else if ( 'inactive' === $visibility ) {
					$where .= ' AND inv.activate_in_store = 0';
				}
			}

			/* Search. */
			if ( isset( $_GET['s'] ) && '' != sanitize_text_field( wp_unslash( $_GET['s'] ) ) ) {
				$search = trim( sanitize_text_field( wp_unslash( $_GET['s'] ) ) );
				$where .= ' AND (';
				for ( $i = 0; $i < count( $this->search_columns ); $i++ ) {
					if ( $i > 0 ) {
						$where .= ' OR ';
					}
					$where .= ' ' . $this->search_columns[ $i ] . ' LIKE ' . $this->wpdb->prepare( '%s', '%' . $this->wpdb->esc_like( $search ) . '%' );
				}
				$where .= ' )';
			}

			return $where;
		}

		protected function get_stock_status_where( $status, $threshold ) {
			switch ( $status ) {
				case 'in_stock':
					return 'inv.tracked = 1 AND inv.quantity > ' . (int) $threshold;
				case 'low_stock':
					return 'inv.tracked = 1 AND inv.quantity > 0 AND inv.quantity <= ' . (int) $threshold;
				case 'out_of_stock':
					return 'inv.tracked = 1 AND inv.quantity <= 0';
				case 'untracked':
					return 'inv.tracked = 0';
			}
			return '';
		}

		protected function get_health_filter_where( $filter_key ) {
			$threshold = self::low_stock_threshold();
			$where = $this->get_stock_status_where( $filter_key, $threshold );
			/* Unknown keys ( e.g. PRO 'below_reorder' ) resolve via filter. */
			return apply_filters( 'wp_easycart_admin_inventory_health_where', $where, $filter_key, array(
				'threshold'   => $threshold,
				'pro_enabled' => $this->pro_enabled,
			) );
		}

		protected function is_valid_sort_column( $column ) {
			return in_array( $column, array( 'title', 'sku', 'quantity', 'row_type' ), true );
		}

		protected function get_query() {
			if ( isset( $this->current_sort_column ) && $this->is_valid_sort_column( $this->current_sort_column ) ) {
				$sort_column = 'inv.' . $this->current_sort_column;
				$sort_direction = ( 'desc' === $this->current_sort_direction ) ? 'DESC' : 'ASC';
			} else {
				$this->current_sort_column = $this->default_sort_column;
				$this->current_sort_direction = $this->default_sort_direction;
				$sort_column = 'inv.title';
				$sort_direction = 'ASC';
			}

			$secondary = ( 'inv.title' === $sort_column ) ? ', inv.variant_label ASC' : ', inv.title ASC, inv.variant_label ASC';

			return 'SELECT inv.* FROM ' . $this->get_union_sql()
				. $this->get_outer_where()
				. ' ORDER BY ' . $sort_column . ' ' . $sort_direction . $secondary
				. ' LIMIT ' . ( ( $this->current_page - 1 ) * $this->perpage ) . ', ' . (int) $this->perpage;
		}

		protected function get_data() {
			$this->results = $this->wpdb->get_results( $this->get_query() );
			$this->showing = count( $this->results );
			$record_count = $this->wpdb->get_var( 'SELECT COUNT(*) FROM ' . $this->get_union_sql() . $this->get_outer_where() );
			$this->record_count = ( null !== $record_count ) ? (int) $record_count : 0;
			$this->total_pages = ( $this->perpage > 0 ) ? ceil( $this->record_count / $this->perpage ) : 1;
			if ( $this->current_page > $this->total_pages && 0 == $this->record_count ) {
				$this->current_page = 1;
			} else if ( $this->current_page > $this->total_pages ) {
				$this->current_page = $this->total_pages;
				$this->get_data();
			}
		}

		/* ------------------------------------------------------------------ */
		/* Health stats                                                         */
		/* ------------------------------------------------------------------ */

		protected function build_health_stats() {
			$threshold = self::low_stock_threshold();

			$product_row = $this->wpdb->get_row(
				'SELECT COUNT(*) AS total,
					SUM( CASE WHEN show_stock_quantity = 1 AND stock_quantity > ' . (int) $threshold . ' THEN 1 ELSE 0 END ) AS in_stock,
					SUM( CASE WHEN show_stock_quantity = 1 AND stock_quantity > 0 AND stock_quantity <= ' . (int) $threshold . ' THEN 1 ELSE 0 END ) AS low_stock,
					SUM( CASE WHEN show_stock_quantity = 1 AND stock_quantity <= 0 THEN 1 ELSE 0 END ) AS out_of_stock,
					SUM( CASE WHEN show_stock_quantity = 0 THEN 1 ELSE 0 END ) AS untracked
				FROM ec_product WHERE use_optionitem_quantity_tracking = 0'
			);
			$variant_row = $this->wpdb->get_row(
				'SELECT COUNT(*) AS total,
					SUM( CASE WHEN q.is_stock_tracking_enabled = 1 AND q.quantity > ' . (int) $threshold . ' THEN 1 ELSE 0 END ) AS in_stock,
					SUM( CASE WHEN q.is_stock_tracking_enabled = 1 AND q.quantity > 0 AND q.quantity <= ' . (int) $threshold . ' THEN 1 ELSE 0 END ) AS low_stock,
					SUM( CASE WHEN q.is_stock_tracking_enabled = 1 AND q.quantity <= 0 THEN 1 ELSE 0 END ) AS out_of_stock,
					SUM( CASE WHEN q.is_stock_tracking_enabled = 0 THEN 1 ELSE 0 END ) AS untracked
				FROM ec_optionitemquantity q
				INNER JOIN ec_product p ON p.product_id = q.product_id AND p.use_optionitem_quantity_tracking = 1
				WHERE q.is_enabled = 1'
			);

			$keys = array( 'total', 'in_stock', 'low_stock', 'out_of_stock', 'untracked' );
			foreach ( $keys as $key ) {
				$this->health_data[ $key ] = (int) ( isset( $product_row->$key ) ? $product_row->$key : 0 ) + (int) ( isset( $variant_row->$key ) ? $variant_row->$key : 0 );
			}

			$stats = array(
				array( 'label' => __( 'Total Items', 'wp-easycart' ), 'value' => $this->health_data['total'], 'filter_value' => '', 'color' => 'default' ),
				array( 'label' => __( 'In Stock', 'wp-easycart' ), 'value' => $this->health_data['in_stock'], 'filter_value' => 'in_stock', 'color' => 'green' ),
				array( 'label' => __( 'Low Stock', 'wp-easycart' ), 'value' => $this->health_data['low_stock'], 'filter_value' => 'low_stock', 'color' => 'amber' ),
				array( 'label' => __( 'Out of Stock', 'wp-easycart' ), 'value' => $this->health_data['out_of_stock'], 'filter_value' => 'out_of_stock', 'color' => 'red' ),
				array( 'label' => __( 'Not Tracked', 'wp-easycart' ), 'value' => $this->health_data['untracked'], 'filter_value' => 'untracked', 'color' => 'gray' ),
			);

			/* PRO appends e.g. Below Reorder Point. */
			$stats = apply_filters( 'wp_easycart_admin_inventory_health_stats', $stats, $this->pro_enabled, $threshold );
			$this->set_health_stats( $stats );
		}

		/* ------------------------------------------------------------------ */
		/* Rendering overrides                                                  */
		/* ------------------------------------------------------------------ */

		protected function print_page_header() {
			echo '<div class="ecv2-page-header">';
			echo '<div class="ecv2-page-header-left">';
			echo '<h1 class="ecv2-page-title">';
			echo '<span class="dashicons dashicons-' . esc_attr( $this->icon ) . '"></span> ';
			echo esc_html( $this->custom_header );
			echo '</h1>';
			echo '<span class="ecv2-record-count">' . esc_html( $this->record_count ) . ' ' . esc_html( 1 == $this->record_count ? $this->item_label : $this->item_label_plural ) . '</span>';
			echo '</div>';

			echo '<div class="ecv2-page-header-right">';

			/* Low stock threshold control ( free ). */
			echo '<div class="ecv2i-threshold-wrap">';
			echo '<button type="button" class="ecv2-btn ecv2-btn-ghost ecv2-btn-sm" id="ecv2i-threshold-btn" title="' . esc_attr__( 'Low stock threshold', 'wp-easycart' ) . '">';
			echo '<span class="dashicons dashicons-admin-settings"></span> ' . esc_html__( 'Low Stock at', 'wp-easycart' ) . ' <strong>' . esc_html( self::low_stock_threshold() ) . '</strong>';
			echo '</button>';
			echo '<div class="ecv2i-threshold-pop" id="ecv2i-threshold-pop" style="display:none;">';
			echo '<label class="ecv2i-pop-label">' . esc_html__( 'Flag items as low stock at or below', 'wp-easycart' ) . '</label>';
			echo '<div class="ecv2i-pop-row">';
			echo '<input type="number" min="1" step="1" class="ecv2-input ecv2-input-sm" id="ecv2i-threshold-input" value="' . esc_attr( self::low_stock_threshold() ) . '" />';
			echo '<button type="button" class="ecv2-btn ecv2-btn-primary ecv2-btn-sm" id="ecv2i-threshold-save">' . esc_html__( 'Save', 'wp-easycart' ) . '</button>';
			echo '</div>';
			echo '<p class="ecv2i-pop-note">' . esc_html__( 'Used by the Low Stock badge, stat card and filters.', 'wp-easycart' ) . '</p>';
			echo '</div>';
			echo '</div>';

			/* PRO toolbar buttons ( Import CSV, Digest settings, Activity ) or locked upsells. */
			do_action( 'wp_easycart_admin_inventory_toolbar', $this->pro_gate );
			if ( ! $this->pro_enabled ) {
				echo '<a href="#" class="ecv2-btn ecv2-btn-ghost ecv2-btn-sm ecv2i-locked-btn" onclick="ecv2i_show_locked( \'import\' ); return false;" title="' . esc_attr( $this->pro_gate['desc'] ) . '">';
				echo '<span class="dashicons dashicons-lock"></span> ' . esc_html__( 'Import CSV', 'wp-easycart' );
				echo '</a>';
			}

			/* Help link. */
			if ( isset( $this->docs_guide ) ) {
				echo '<a href="' . esc_url( wp_easycart_admin()->helpsystem->print_docs_url( $this->docs_guide, $this->docs_link, 'master-record' ) ) . '" target="_blank" class="ecv2-btn ecv2-btn-ghost ecv2-btn-sm"><span class="dashicons dashicons-editor-help"></span> ' . esc_html__( 'Help', 'wp-easycart' ) . '</a>';
			}

			/* Export CSV. */
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=wp-easycart-products&subpage=inventory&ec_admin_form_action=export-inventory-list&wp_easycart_nonce=' . wp_create_nonce( 'wp-easycart-export-inventory' ) ) ) . '" class="ecv2-btn ecv2-btn-primary"><span class="dashicons dashicons-download"></span> ' . esc_html( $this->add_new_label ) . '</a>';

			echo '</div>'; // .ecv2-page-header-right
			echo '</div>'; // .ecv2-page-header

			/* Free edition: what PRO adds to this page, in the same clickable strip the other locked pages use. */
			if ( ! $this->pro_enabled && class_exists( 'wp_easycart_admin_upsell' ) ) {
				$e = wp_easycart_admin_upsell::entry( 'inventory' );
				if ( '' !== $e['stat_line'] ) {
					echo '<p class="ecv2-page-intro ecv2-upsell-stat-inline"><span class="dashicons dashicons-chart-line"></span> ' . esc_html( $e['stat_line'] ) . '</p>';
				}
				wp_easycart_admin_upsell::print_feature_strip( 'inventory' );
			}
		}

		protected function print_bulk_actions_toolbar() {
			echo '<div class="ecv2-bulk-actions">';
			echo '<span class="ecv2-bulk-count" style="display:none;"><span id="ecv2-selected-count">0</span> ' . esc_html__( 'selected', 'wp-easycart' ) . '</span>';
			if ( $this->pro_enabled ) {
				echo '<button type="button" class="ecv2-btn ecv2-btn-primary ecv2-btn-sm" id="ecv2i-bulk-btn" style="display:none;" onclick="ecv2i_open_bulk();">';
				echo '<span class="dashicons dashicons-edit"></span> ' . esc_html__( 'Bulk Update', 'wp-easycart' ) . '</button>';
			} else {
				echo '<button type="button" class="ecv2-btn ecv2-btn-sm ecv2i-locked-btn" id="ecv2i-bulk-btn" style="display:none;" onclick="ecv2i_show_locked( \'bulk\' );" title="' . esc_attr( $this->pro_gate['desc'] ) . '">';
				echo '<span class="dashicons dashicons-lock"></span> ' . esc_html__( 'Bulk Update', 'wp-easycart' ) . '</button>';
			}
			echo '</div>';
		}

		protected function print_cell_content( $result, $col ) {
			switch ( $col['name'] ) {
				case 'image1':
					$this->print_image_cell( $result );
					break;
				case 'title':
					$this->print_item_cell( $result );
					break;
				case 'sku':
					if ( '' !== (string) $result->sku ) {
						echo '<span class="ecv2-sku">' . esc_html( $result->sku ) . '</span>';
					} else {
						echo '<span class="ecv2-sku-empty">&mdash;</span>';
					}
					break;
				case 'row_type':
					if ( 'variant' === $result->row_type ) {
						echo '<span class="ecv2i-type-tag ecv2i-type-variant">' . esc_html__( 'Variant', 'wp-easycart' ) . '</span>';
					} else {
						echo '<span class="ecv2i-type-tag">' . esc_html__( 'Product', 'wp-easycart' ) . '</span>';
					}
					break;
				case 'quantity':
					$this->print_quantity_cell( $result );
					break;
				default:
					/* PRO columns print through their own actions. */
					if ( has_action( 'wp_easycart_admin_inventory_cell_' . $col['name'] ) ) {
						do_action( 'wp_easycart_admin_inventory_cell_' . $col['name'], $result, $this->pro_enabled );
					} else {
						parent::print_cell_content( $result, $col );
					}
					break;
			}
		}

		protected function print_image_cell( $result ) {
			$image_url = self::get_image_url( $result->image1 );
			echo '<div class="ecv2i-thumb">';
			if ( '' !== $image_url ) {
				echo '<img src="' . esc_url( $image_url ) . '" alt="" loading="lazy" />';
			} else {
				echo '<span class="dashicons dashicons-format-image"></span>';
			}
			echo '</div>';
		}

		protected function print_item_cell( $result ) {
			$edit_url = admin_url( 'admin.php?page=wp-easycart-products&product_id=' . (int) $result->product_id . '&ec_admin_form_action=edit&wp_easycart_nonce=' . wp_create_nonce( 'wp-easycart-action-edit' ) );
			echo '<div class="ecv2i-item">';
			echo '<a href="' . esc_url( $edit_url ) . '" class="ecv2-link-primary ecv2i-item-title">' . esc_html( wp_unslash( $result->title ) ) . '</a>';
			if ( 'variant' === $result->row_type && '' !== (string) $result->variant_label ) {
				echo '<span class="ecv2i-variant-label">' . esc_html( wp_unslash( $result->variant_label ) ) . '</span>';
			}
			if ( ! $result->activate_in_store ) {
				echo '<span class="ecv2i-hidden-tag">' . esc_html__( 'Hidden', 'wp-easycart' ) . '</span>';
			}
			echo '</div>';
		}

		protected function print_quantity_cell( $result ) {
			$threshold = self::low_stock_threshold();
			$qty = (int) $result->quantity;
			$tracked = (bool) $result->tracked;
			$locked = (bool) $result->square_locked;

			echo '<div class="ecv2i-qty-wrap" data-row-key="' . esc_attr( $result->row_key ) . '" data-product-id="' . esc_attr( (int) $result->product_id ) . '" data-oiq-id="' . esc_attr( (int) $result->oiq_id ) . '" data-qty="' . esc_attr( $qty ) . '" data-tracked="' . esc_attr( $tracked ? 1 : 0 ) . '">';

			if ( ! $tracked ) {
				echo '<span class="ecv2-stock-badge ecv2-stock-unlimited">&infin; ' . esc_html__( 'Not Tracked', 'wp-easycart' ) . '</span>';
			} else if ( $locked ) {
				echo '<span class="ecv2-stock-badge ' . esc_attr( $this->qty_badge_class( $qty, $threshold ) ) . '">' . esc_html( $qty ) . '</span>';
				echo ' <span class="dashicons dashicons-lock ecv2-cell-lock-icon" title="' . esc_attr__( 'Stock managed by Square', 'wp-easycart' ) . '"></span>';
			} else {
				echo '<button type="button" class="ecv2i-qty-badge-btn" onclick="ecv2i_open_qty( this );">';
				echo '<span class="ecv2-stock-badge ' . esc_attr( $this->qty_badge_class( $qty, $threshold ) ) . '">' . esc_html( $qty );
				if ( $qty <= 0 ) {
					echo ' &middot; ' . esc_html__( 'Out', 'wp-easycart' );
				} else if ( $qty <= $threshold ) {
					echo ' &middot; ' . esc_html__( 'Low', 'wp-easycart' );
				}
				echo '</span>';
				echo '</button>';

				/* Inline set-quantity popover. */
				echo '<div class="ecv2i-qty-pop">';
				echo '<label class="ecv2i-pop-label">' . esc_html__( 'On Hand', 'wp-easycart' ) . '</label>';
				echo '<div class="ecv2i-pop-row">';
				echo '<button type="button" class="ecv2i-step" data-step="-1">&minus;</button>';
				echo '<input type="number" step="1" class="ecv2-input ecv2-input-sm ecv2i-qty-input" value="' . esc_attr( $qty ) . '" data-original="' . esc_attr( $qty ) . '" />';
				echo '<button type="button" class="ecv2i-step" data-step="1">+</button>';
				echo '<button type="button" class="ecv2-btn ecv2-btn-primary ecv2-btn-sm ecv2i-qty-save">' . esc_html__( 'Save', 'wp-easycart' ) . '</button>';
				echo '</div>';
				do_action( 'wp_easycart_admin_inventory_qty_pop', $result, $this->pro_gate );
				echo '</div>';
			}

			echo '</div>';
		}

		protected function qty_badge_class( $qty, $threshold ) {
			if ( $qty <= 0 ) {
				return 'ecv2-stock-out';
			}
			if ( $qty <= $threshold ) {
				return 'ecv2-stock-low';
			}
			return 'ecv2-stock-ok';
		}

		/**
		 * Table view override: identical to the base rendering, except the
		 * empty state explains *why* nothing matched — the search term and
		 * every active filter render as removable chips with a clear-all
		 * action, instead of a bare "No items found."
		 */
		protected function print_table_view() {
			/* Same scroll wrapper the base table uses: on narrow containers the table scrolls
			   inside its card ( see .ecv2-table-scroll in admin-v2.css ) instead of being clipped. */
			echo '<div class="ecv2-table-scroll">';
			echo '<table class="ecv2-table' . ( '' !== $this->table_class ? ' ' . esc_attr( $this->table_class ) : '' ) . '" id="' . esc_attr( $this->table_id ) . '">';
			$this->print_table_thead();
			echo '<tbody>';
			foreach ( $this->results as $result ) {
				$this->print_table_row( $result );
			}
			if ( empty( $this->results ) ) {
				$visible_cols = 0;
				foreach ( $this->list_columns as $col ) {
					if ( ! isset( $col['format'] ) || 'hidden' !== $col['format'] ) {
						$visible_cols++;
					}
				}
				$this->print_empty_state( $visible_cols + 2 );
			}
			echo '</tbody>';
			echo '</table>';
			echo '</div>'; // .ecv2-table-scroll
		}

		/**
		 * Collect the active search term + filters as chip descriptors:
		 * array of ( 'key' => removal target for JS, 'group' => label,
		 * 'value' => human-readable value ).
		 */
		protected function get_empty_state_chips() {
			$chips = array();

			if ( isset( $_GET['s'] ) && '' != sanitize_text_field( wp_unslash( $_GET['s'] ) ) ) {
				$chips[] = array(
					'key'   => 'search',
					'group' => __( 'Search', 'wp-easycart' ),
					'value' => '"' . trim( sanitize_text_field( wp_unslash( $_GET['s'] ) ) ) . '"',
				);
			}

			if ( isset( $_GET['health_filter'] ) && '' != $_GET['health_filter'] ) {
				$health_val = sanitize_key( $_GET['health_filter'] );
				$health_label = ucwords( str_replace( '_', ' ', $health_val ) );
				foreach ( $this->health_stats as $stat ) {
					if ( $stat['filter_value'] === $health_val ) {
						$health_label = $stat['label'];
						break;
					}
				}
				$chips[] = array(
					'key'   => 'health_filter',
					'group' => __( 'Quick Filter', 'wp-easycart' ),
					'value' => $health_label,
				);
			}

			for ( $i = 0; $i < count( $this->filters ); $i++ ) {
				if ( ! isset( $_GET[ 'filter_' . $i ] ) || '' == sanitize_text_field( wp_unslash( $_GET[ 'filter_' . $i ] ) ) ) {
					continue;
				}
				$val = sanitize_text_field( wp_unslash( $_GET[ 'filter_' . $i ] ) );
				$val_label = $val;
				if ( isset( $this->filters[ $i ]['data'] ) && is_array( $this->filters[ $i ]['data'] ) ) {
					foreach ( $this->filters[ $i ]['data'] as $option ) {
						if ( isset( $option->value ) && (string) $option->value === $val ) {
							$val_label = isset( $option->label ) ? $option->label : $val;
							break;
						}
					}
				}
				$chips[] = array(
					'key'   => 'filter_' . $i,
					'group' => $this->filters[ $i ]['label'],
					'value' => $val_label,
				);
			}

			return $chips;
		}

		protected function print_empty_state( $colspan ) {
			$chips = $this->get_empty_state_chips();

			echo '<tr><td colspan="' . (int) $colspan . '" class="ecv2-empty-state ecv2i-empty-state">';
			echo '<div class="ecv2i-empty-title"><span class="dashicons dashicons-info-outline"></span> ' . esc_html__( 'No items found.', 'wp-easycart' ) . '</div>';

			if ( ! empty( $chips ) ) {
				echo '<div class="ecv2i-empty-context">';
				echo '<span class="ecv2i-empty-context-label">' . esc_html__( 'Currently applied:', 'wp-easycart' ) . '</span>';
				foreach ( $chips as $chip ) {
					echo '<span class="ecv2-active-tag">';
					echo '<span class="ecv2-active-tag-label">' . esc_html( $chip['group'] ) . ':</span> ';
					echo esc_html( $chip['value'] );
					echo '<button type="button" class="ecv2-active-tag-remove" data-filter="' . esc_attr( $chip['key'] ) . '" title="' . esc_attr__( 'Remove', 'wp-easycart' ) . '">&times;</button>';
					echo '</span>';
				}
				if ( count( $chips ) > 1 ) {
					echo '<button type="button" class="ecv2-active-tag ecv2-active-tag-clear-all" onclick="ecv2i_clear_all();">' . esc_html__( 'Clear search & filters', 'wp-easycart' ) . '</button>';
				}
				echo '</div>';
				echo '<p class="ecv2i-empty-hint">' . esc_html__( 'Try removing one of the above, or broadening your search.', 'wp-easycart' ) . '</p>';
			}

			echo '</td></tr>';
		}

		protected function print_row_actions( $result ) {
			$edit_url = admin_url( 'admin.php?page=wp-easycart-products&product_id=' . (int) $result->product_id . '&ec_admin_form_action=edit&wp_easycart_nonce=' . wp_create_nonce( 'wp-easycart-action-edit' ) );

			echo '<div class="ecv2-row-menu-wrap">';
			echo '<button type="button" class="ecv2-row-menu-trigger" onclick="ecv2_toggle_row_menu(this);">&#8943;</button>';
			echo '<div class="ecv2-row-menu">';

			echo '<a href="' . esc_url( $edit_url ) . '" class="ecv2-row-menu-item"><span class="dashicons dashicons-edit"></span> ' . esc_html__( 'Edit Product', 'wp-easycart' ) . '</a>';

			if ( $result->tracked && ! $result->square_locked ) {
				echo '<a href="#" class="ecv2-row-menu-item" onclick="ecv2i_menu_set_qty( this ); return false;"><span class="dashicons dashicons-update"></span> ' . esc_html__( 'Set Quantity', 'wp-easycart' ) . '</a>';
			}

			if ( $this->pro_enabled ) {
				do_action( 'wp_easycart_admin_inventory_row_menu', $result );
			} else {
				$locked_items = array(
					array( 'icon' => 'plus-alt', 'label' => __( 'Adjust Stock', 'wp-easycart' ), 'feature' => 'adjust' ),
					array( 'icon' => 'backup', 'label' => __( 'View History', 'wp-easycart' ), 'feature' => 'history' ),
					array( 'icon' => 'flag', 'label' => __( 'Set Reorder Point', 'wp-easycart' ), 'feature' => 'reorder' ),
				);
				foreach ( $locked_items as $item ) {
					echo '<a href="#" class="ecv2-row-menu-item ecv2i-locked-menu-item" onclick="jQuery( this ).closest( \'.ecv2-row-menu\' ).removeClass( \'ecv2-row-menu-open\' ); ecv2i_show_locked( \'' . esc_attr( $item['feature'] ) . '\' ); return false;" title="' . esc_attr( $this->pro_gate['desc'] ) . '">';
					echo '<span class="dashicons dashicons-' . esc_attr( $item['icon'] ) . '"></span> ' . esc_html( $item['label'] );
					echo ' <span class="ecv2i-menu-pro">PRO</span></a>';
				}
			}

			echo '</div>';
			echo '</div>';
		}
	}

endif;

if ( ! function_exists( 'wp_easycart_inventory_pro_gate' ) ) {
	function wp_easycart_inventory_pro_gate() {
		return wp_easycart_admin_pro_gate::evaluate( array(
			'enabled_filter' => 'wp_easycart_admin_inventory_pro_enabled',
			'min_version' => '5.8.15',
			'labels' => array(
				'enabled' => '',
				'upsell'  => __( 'Advanced inventory tools are available in WP EasyCart PRO', 'wp-easycart' ),
			),
		) );
	}
}