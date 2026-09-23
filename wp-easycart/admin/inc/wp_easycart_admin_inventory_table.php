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
 *  - action 'wp_easycart_admin_inventory_toolbar'        PRO adds toolbar buttons ( Import panel, activity, alerts ).
 *  - action 'wp_easycart_admin_inventory_qty_pop'        PRO prints On hand popover fields ( the required reason ).
 *  - filter 'wp_easycart_admin_inventory_qty_reason'     ( wp_easycart_admin_inventory ) PRO validates that reason.
 *  - filter 'wp_easycart_admin_inventory_qty_reason_error' ( wp_easycart_admin_inventory ) PRO refuses a save without one.
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

			/* Export rides the standard add-new slot ( the FREE CSV export; with PRO the same button opens the import / export panel ). */
			$this->set_add_new( true, 'export-inventory-list', __( 'Export', 'wp-easycart' ) );
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

			/* The product filter lists only the chosen product ( the rest arrive from the typeahead ). */
			$selected_products = array();
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter.
			$selected_product  = isset( $_GET['filter_3'] ) ? (int) $_GET['filter_3'] : 0;
			if ( $selected_product > 0 ) {
				$product_title = $this->wpdb->get_var( $this->wpdb->prepare( 'SELECT title FROM ec_product WHERE product_id = %d', $selected_product ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared through $this->wpdb, which the sniff does not follow.
				$selected_products[] = (object) array( 'value' => $selected_product, 'label' => ( null !== $product_title ) ? wp_unslash( $product_title ) : $selected_product );
			}
			$this->set_filters( array(
				array(
					'label' => __( 'Stock Status', 'wp-easycart' ),
					/* Same wording as the stat strip above the list. */
					'data'  => array(
						(object) array( 'value' => 'in_stock', 'label' => __( 'In stock', 'wp-easycart' ), 'icon' => 'yes-alt' ),
						(object) array( 'value' => 'low_stock', 'label' => __( 'Low stock', 'wp-easycart' ), 'icon' => 'warning' ),
						(object) array( 'value' => 'out_of_stock', 'label' => __( 'Out of stock', 'wp-easycart' ), 'icon' => 'dismiss' ),
						(object) array( 'value' => 'untracked', 'label' => __( 'Not tracked', 'wp-easycart' ), 'icon' => 'marker' ),
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
				/* 6.0.1: one product and its variants. Search-as-you-type, like the orders list; the chosen product is
				   listed so the control can show its title. */
				array(
					'label' => __( 'Product', 'wp-easycart' ),
					'data'  => $selected_products,
					'type'  => 'select',
					'ajax'  => array( 'action' => 'ec_admin_ajax_ecv2_product_search' ),
				),
			) );

			$this->build_health_stats();
		}

		/* ------------------------------------------------------------------ */
		/* Shared helpers                                                       */
		/* ------------------------------------------------------------------ */

		/**
		 * The store-wide low stock number. One setting now drives the Low chip, the
		 * filters, the PRO digest and the low stock emails: see
		 * wp_easycart_store_low_stock_threshold() in inc/classes/core/ec_stock.php.
		 * Kept as the admin-facing wrapper that PRO already calls.
		 */
		public static function low_stock_threshold() {
			if ( function_exists( 'wp_easycart_store_low_stock_threshold' ) ) {
				return wp_easycart_store_low_stock_threshold();
			}
			$threshold = (int) get_option( 'ec_option_low_stock_trigger_total' );
			return ( $threshold > 0 ) ? $threshold : 10;
		}

		/** Where a merchant changes that number. @since 6.0.0 */
		public static function low_stock_threshold_url() {
			return admin_url( 'admin.php?page=wp-easycart-settings&subpage=checkout&highlight=ec_option_low_stock_trigger_total' );
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
		/*
		 * 6.0.1: both halves also select the gallery CSV and the pics2-5 columns so print_image_cell()
		 * can resolve a thumbnail the way the product list does. The two column lists must stay identical.
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
				p.image2,
				p.image3,
				p.image4,
				p.image5,
				p.product_images,
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
				p.image2,
				p.image3,
				p.image4,
				p.image5,
				p.product_images,
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

			/* filter_3: one product, with its variants. */
			if ( isset( $_GET['filter_3'] ) && '' != $_GET['filter_3'] ) {
				$where .= $this->wpdb->prepare( ' AND inv.product_id = %d', (int) $_GET['filter_3'] );
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
			$this->results = $this->wpdb->get_results( $this->get_query() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- get_query() is static SQL plus a whitelisted sort column, int-cast LIMIT and prepared/whitelisted WHERE clauses.
			$this->showing = count( $this->results );
			$record_count = $this->wpdb->get_var( 'SELECT COUNT(*) FROM ' . $this->get_union_sql() . $this->get_outer_where() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- get_union_sql() is static SQL; get_outer_where() only emits prepared or whitelisted clauses.
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

			/*
			 * Same strip as the other V2 lists: stats carrying a 'group' render as the grouped pill bar
			 * ( wp_easycart_admin_table_v2::print_health_dashboard ), sentence case, stock health last so
			 * PRO's own stat ( Below reorder ) joins that group. Keep the groups contiguous.
			 */
			$stats = array(
				array( 'label' => __( 'All', 'wp-easycart' ), 'value' => $this->health_data['total'], 'filter_value' => '', 'color' => 'default', 'group' => 'catalog' ),
				array( 'label' => __( 'In stock', 'wp-easycart' ), 'value' => $this->health_data['in_stock'], 'filter_value' => 'in_stock', 'color' => 'green', 'group' => 'catalog' ),
				array( 'label' => __( 'Not tracked', 'wp-easycart' ), 'value' => $this->health_data['untracked'], 'filter_value' => 'untracked', 'color' => 'gray', 'group' => 'catalog' ),
				array( 'label' => __( 'Low stock', 'wp-easycart' ), 'value' => $this->health_data['low_stock'], 'filter_value' => 'low_stock', 'color' => 'amber', 'group' => 'attention' ),
				array( 'label' => __( 'Out of stock', 'wp-easycart' ), 'value' => $this->health_data['out_of_stock'], 'filter_value' => 'out_of_stock', 'color' => 'red', 'group' => 'attention' ),
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
			echo '<span class="dashicons dashicons-' . esc_attr( $this->icon ) . ' ecv2-page-header-icon"></span>';
			echo '<div class="ecv2-page-header-text">';
			echo '<h1 class="ecv2-page-title">' . esc_html( $this->custom_header ) . '</h1>';
			echo '<p class="ecv2-page-subline"><span class="ecv2-record-count">' . esc_html( $this->record_count ) . ' ' . esc_html( 1 == $this->record_count ? $this->item_label : $this->item_label_plural ) . '</span></p>';
			echo '</div>'; // .ecv2-page-header-text
			echo '</div>';

			echo '<div class="ecv2-page-header-right">';

			/* Low stock threshold ( free ). One store-wide number, edited in Settings: it
			   drives the Low badge, stat card and filters here and the low stock emails. */
			echo '<a href="' . esc_url( self::low_stock_threshold_url() ) . '" class="ecv2-btn ecv2-btn-ghost ecv2-btn-sm ecv2i-threshold-link" title="' . esc_attr__( 'Change the low stock threshold in Settings > Checkout > Stock alerts', 'wp-easycart' ) . '">';
			echo '<span class="dashicons dashicons-admin-settings"></span> ' . esc_html__( 'Low Stock at', 'wp-easycart' ) . ' <strong>' . esc_html( self::low_stock_threshold() ) . '</strong>';
			echo '</a>';

			/* PRO toolbar buttons ( Import, Activity, Alerts ) or the locked Import upsell. */
			do_action( 'wp_easycart_admin_inventory_toolbar', $this->pro_gate );
			if ( ! $this->pro_enabled ) {
				echo '<a href="#" class="ecv2-btn ecv2-btn-ghost ecv2-btn-sm ecv2i-locked-btn" onclick="ecv2i_show_locked( \'import\' ); return false;" title="' . esc_attr( $this->pro_gate['desc'] ) . '">';
				echo '<span class="dashicons dashicons-lock"></span> <span class="ecv2-btn-label">' . esc_html__( 'Import', 'wp-easycart' ) . '</span>';
				echo '</a>';
			}

			/* Help link. */
			if ( isset( $this->docs_guide ) ) {
				echo '<a href="' . esc_url( wp_easycart_admin()->helpsystem->print_docs_url( $this->docs_guide, $this->docs_link, 'master-record' ) ) . '" target="_blank" class="ecv2-btn ecv2-btn-ghost ecv2-btn-sm"><span class="dashicons dashicons-editor-help"></span> <span class="ecv2-btn-label">' . esc_html__( 'Help', 'wp-easycart' ) . '</span></a>';
			}

			/* Export. The href is the FREE CSV export; with PRO live, inventory-import-v2.js intercepts
			   data-ecv2ii-open and opens the Export tab of the import / export panel instead ( @since 6.0.0 ). */
			echo '<a href="' . esc_url( wp_easycart_admin_inventory::export_url() ) . '" class="ecv2-btn ecv2-btn-primary" id="ecv2i-export-btn" data-ecv2ii-open="export"><span class="dashicons dashicons-download"></span> ' . esc_html( $this->add_new_label ) . '</a>';

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
						echo '<span class="ecv2-chip ecv2-chip-blue">' . esc_html__( 'Variant', 'wp-easycart' ) . '</span>';
					} else {
						echo '<span class="ecv2-chip ecv2-chip-gray">' . esc_html__( 'Product', 'wp-easycart' ) . '</span>';
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

		/**
		 * The product thumbnail.
		 *
		 * 6.0.1: this used to read ec_product.image1 alone, through get_image_url(), which only ever looks in
		 * the pics1 folder. A store that has been running for years often has its picture in image2-5 ( pics2-5 )
		 * or in the product_images gallery instead, and those products showed a placeholder here while the same
		 * picture appeared on the product list and in the product editor's media panel. Both now go through the
		 * product list's resolver, which understands the gallery CSV, media library ids and the legacy columns.
		 */
		protected function print_image_cell( $result ) {
			$image_url = ( class_exists( 'wp_easycart_admin_product_table' ) && method_exists( 'wp_easycart_admin_product_table', 'resolve_thumbnail_url' ) )
				? wp_easycart_admin_product_table::resolve_thumbnail_url( $result )
				: self::get_image_url( $result->image1 );
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
				echo '<span class="ecv2-chip ecv2-chip-gray ecv2i-hidden-tag">' . esc_html__( 'Hidden', 'wp-easycart' ) . '</span>';
			}
			echo '</div>';
		}

		/**
		 * On-hand cell. One pill, worded like the Products list ( "∞ Unlimited", "12 in stock · Low", "Out of Stock" ),
		 * and always the affordance for changing stock: clicking it opens the quantity popover. Untracked items open
		 * a "Track stock" form; tracked items get Set / Add / Remove with a stepper and a "Stop tracking" link.
		 * Square-managed rows are read-only ( lock ).
		 */
		protected function print_quantity_cell( $result ) {
			$threshold = self::low_stock_threshold();
			$qty = (int) $result->quantity;
			$tracked = (bool) $result->tracked;
			$locked = (bool) $result->square_locked;

			echo '<div class="ecv2i-qty-wrap" data-row-key="' . esc_attr( $result->row_key ) . '" data-product-id="' . esc_attr( (int) $result->product_id ) . '" data-oiq-id="' . esc_attr( (int) $result->oiq_id ) . '" data-qty="' . esc_attr( $qty ) . '" data-tracked="' . ( $tracked ? 1 : 0 ) . '" data-title="' . esc_attr( wp_unslash( $result->title ) . ( isset( $result->variant_label ) && $result->variant_label ? ' — ' . wp_unslash( $result->variant_label ) : '' ) ) . '">';

			if ( $locked ) {
				echo '<span class="ecv2-stock-badge ' . esc_attr( $tracked ? $this->qty_badge_class( $qty, $threshold ) : 'ecv2-stock-unlimited' ) . '">' . ( $tracked ? esc_html( $this->qty_label( $qty, $threshold ) ) : '&infin; ' . esc_html__( 'Unlimited', 'wp-easycart' ) ) . '</span>';
				echo ' <span class="dashicons dashicons-lock ecv2-cell-lock-icon" title="' . esc_attr__( 'Stock is managed by Square — change it there and it syncs here.', 'wp-easycart' ) . '"></span>';
				echo '</div>';
				return;
			}

			echo '<button type="button" class="ecv2-stock-badge-btn ecv2i-qty-badge-btn" onclick="ecv2i_open_qty( this );" title="' . esc_attr( $tracked ? __( 'Change the quantity on hand', 'wp-easycart' ) : __( 'Stock isn’t tracked for this item — click to start tracking', 'wp-easycart' ) ) . '" aria-haspopup="dialog">';
			if ( $tracked ) {
				echo '<span class="ecv2-stock-badge ' . esc_attr( $this->qty_badge_class( $qty, $threshold ) ) . '">' . esc_html( $this->qty_label( $qty, $threshold ) ) . '</span>';
			} else {
				echo '<span class="ecv2-stock-badge ecv2-stock-unlimited">&infin; ' . esc_html__( 'Unlimited', 'wp-easycart' ) . '</span>';
			}
			echo '<span class="ecv2i-edit-hint dashicons dashicons-edit" aria-hidden="true"></span>';
			echo '</button>';

			/* Popover — one markup, two states ( JS switches on data-tracked ). */
			echo '<div class="ecv2i-qty-pop" role="dialog" aria-label="' . esc_attr__( 'Change stock', 'wp-easycart' ) . '">';
			echo '<div class="ecv2i-pop-title">' . esc_html( wp_unslash( $result->title ) ) . ( isset( $result->variant_label ) && $result->variant_label ? ' <span class="ecv2-sub" style="display:inline">' . esc_html( wp_unslash( $result->variant_label ) ) . '</span>' : '' ) . '</div>';
			/* tracked state */
			echo '<div class="ecv2i-pop-tracked">';
			echo '<div class="ecv2i-mode" role="tablist"><button type="button" class="ecv2i-mode-btn is-on" data-mode="set">' . esc_html__( 'Set to', 'wp-easycart' ) . '</button><button type="button" class="ecv2i-mode-btn" data-mode="add">' . esc_html__( 'Add', 'wp-easycart' ) . '</button><button type="button" class="ecv2i-mode-btn" data-mode="remove">' . esc_html__( 'Remove', 'wp-easycart' ) . '</button></div>';
			echo '<div class="ecv2i-pop-row">';
			echo '<button type="button" class="ecv2i-step" data-step="-1" aria-label="' . esc_attr__( 'Minus one', 'wp-easycart' ) . '">&minus;</button>';
			echo '<input type="number" step="1" min="0" class="ecv2-input ecv2-input-sm ecv2i-qty-input" value="' . esc_attr( $qty ) . '" data-original="' . esc_attr( $qty ) . '" aria-label="' . esc_attr__( 'Quantity', 'wp-easycart' ) . '" />';
			echo '<button type="button" class="ecv2i-step" data-step="1" aria-label="' . esc_attr__( 'Plus one', 'wp-easycart' ) . '">+</button>';
			echo '</div>';
			echo '<div class="ecv2i-pop-preview" aria-live="polite"></div>';

			/*
			 * PRO prints its fields here ( the adjustment reason, required like Bulk Update ). Any field
			 * with class .ecv2i-pop-extra and a name is posted with the save by inventory-v2.js; a field
			 * marked required blocks the save until it has a value.
			 * Free edition: the same row, locked, opening the inventory upsell.
			 * Save sits below these fields, as the Apply button does in the Bulk Update modal.
			 */
			do_action( 'wp_easycart_admin_inventory_qty_pop', $result, $this->pro_gate );
			if ( ! $this->pro_enabled ) {
				echo '<a href="#" class="ecv2i-pop-reason-locked" onclick="jQuery( this ).closest( \'.ecv2i-qty-pop\' ).removeClass( \'ecv2i-pop-open\' ); ecv2i_show_locked( \'adjust\' ); return false;" title="' . esc_attr( $this->pro_gate['desc'] ) . '"><span class="dashicons dashicons-lock" aria-hidden="true"></span> ' . esc_html__( 'Add a reason', 'wp-easycart' ) . ' <span class="ecv2i-menu-pro">' . esc_html( class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::badge( 'pro' ) : __( 'Pro/Premium', 'wp-easycart' ) ) . '</span></a>';
			}
			echo '<div class="ecv2i-pop-actions"><button type="button" class="ecv2-btn ecv2-btn-primary ecv2-btn-sm ecv2i-qty-save">' . esc_html__( 'Save', 'wp-easycart' ) . '</button></div>';
			echo '<div class="ecv2i-pop-foot"><span class="ecv2i-pop-kbd">' . esc_html__( 'Enter to save · Esc to close', 'wp-easycart' ) . '</span><a href="#" class="ecv2i-stop-tracking">' . esc_html__( 'Stop tracking', 'wp-easycart' ) . '</a></div>';
			echo '</div>';
			/* untracked state */
			echo '<div class="ecv2i-pop-untracked">';
			echo '<p class="ecv2i-pop-note">' . esc_html__( 'Stock isn’t tracked, so customers can always buy this. Enter what you have on hand to start tracking — it shows Out of Stock at zero and appears in low-stock alerts.', 'wp-easycart' ) . '</p>';
			echo '<div class="ecv2i-pop-row">';
			echo '<input type="number" step="1" min="0" class="ecv2-input ecv2-input-sm ecv2i-track-input" value="" placeholder="0" aria-label="' . esc_attr__( 'Quantity on hand', 'wp-easycart' ) . '" />';
			echo '<button type="button" class="ecv2-btn ecv2-btn-primary ecv2-btn-sm ecv2i-track-start">' . esc_html__( 'Start tracking', 'wp-easycart' ) . '</button>';
			echo '</div>';
			echo '</div>';
			echo '</div>';

			echo '</div>';
		}

		/** Same words as the Products list stock pill, plus a "· Low" suffix at or under the threshold. */
		protected function qty_label( $qty, $threshold ) {
			if ( $qty <= 0 ) { return __( 'Out of Stock', 'wp-easycart' ); }
			$label = sprintf( __( '%s in stock', 'wp-easycart' ), number_format_i18n( $qty ) );
			return $qty <= $threshold ? $label . ' · ' . __( 'Low', 'wp-easycart' ) : $label;
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
				$this->print_inventory_empty_row( $visible_cols + 2 );
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

		protected function print_inventory_empty_row( $colspan ) { /* 6.0.0: renamed; the base list class now owns print_empty_state() with no arguments. */
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

			if ( ! $result->square_locked ) {
				if ( $result->tracked ) {
					echo '<a href="#" class="ecv2-row-menu-item" onclick="ecv2i_menu_set_qty( this ); return false;"><span class="dashicons dashicons-edit"></span> ' . esc_html__( 'Change quantity…', 'wp-easycart' ) . '</a>';
					echo '<a href="#" class="ecv2-row-menu-item" onclick="ecv2i_menu_stop_tracking( this ); return false;"><span class="dashicons dashicons-dismiss"></span> ' . esc_html__( 'Stop tracking stock', 'wp-easycart' ) . '</a>';
				} else {
					echo '<a href="#" class="ecv2-row-menu-item" onclick="ecv2i_menu_set_qty( this ); return false;"><span class="dashicons dashicons-plus-alt2"></span> ' . esc_html__( 'Track stock…', 'wp-easycart' ) . '</a>';
				}
			} else {
				echo '<span class="ecv2-row-menu-item is-disabled" title="' . esc_attr__( 'Stock is managed by Square', 'wp-easycart' ) . '"><span class="dashicons dashicons-lock"></span> ' . esc_html__( 'Managed by Square', 'wp-easycart' ) . '</span>';
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
					echo ' <span class="ecv2i-menu-pro">' . esc_html( class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::badge( 'pro' ) : __( 'Pro/Premium', 'wp-easycart' ) ) . '</span></a>';
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