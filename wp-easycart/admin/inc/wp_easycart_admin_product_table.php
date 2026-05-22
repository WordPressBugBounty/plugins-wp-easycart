<?php
/**
 * WP EasyCart Admin Product Table V2
 *
 * Extends wp_easycart_admin_table_v2 with product-specific features.
 *
 * @since 5.x.x
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_product_table' ) ) :

	class wp_easycart_admin_product_table extends wp_easycart_admin_table_v2 {

		const LOW_STOCK_THRESHOLD = 10;

		const SCORE_FIELDS = array(
			'title'             => array( 'label' => 'Product Title',      'check' => 'not_empty' ),
			'price'             => array( 'label' => 'Price set (> $0)',    'check' => 'greater_zero' ),
			'image1'            => array( 'label' => 'Product Image',      'check' => 'not_empty' ),
			'description'       => array( 'label' => 'Full Description',   'check' => 'not_empty' ),
			'short_description' => array( 'label' => 'Short Description',  'check' => 'not_empty' ),
			'model_number'      => array( 'label' => 'SKU / Model Number', 'check' => 'not_empty' ),
			'category_count'    => array( 'label' => 'Category Assigned',  'check' => 'greater_zero' ),
		);

		private $health_data = array();
		private $view_url_cache = array();

		private function get_view_url( $product_id ) {
			$product_id = (int) $product_id;
			if ( $product_id <= 0 ) {
				return '';
			}
			if ( array_key_exists( $product_id, $this->view_url_cache ) ) {
				return $this->view_url_cache[ $product_id ];
			}
			$url = '';
			if ( function_exists( 'wp_easycart_admin_products' ) ) {
				$resolved = wp_easycart_admin_products()->get_product_link( $product_id );
				if ( ! empty( $resolved ) ) {
					$url = $resolved;
				}
			}
			$this->view_url_cache[ $product_id ] = $url;
			return $url;
		}

		public function __construct() {
			parent::__construct();
		}

		public function setup() {
			$this->set_table( 'ec_product', 'product_id' );
			$this->set_table_id( 'ec_admin_product_list_v2' );
			$this->set_default_sort( 'title', 'ASC' );
			$this->set_header( __( 'Manage Products', 'wp-easycart' ) );
			$this->set_importer( true, __( 'Import Products', 'wp-easycart' ) );
			$this->set_docs_link( 'products', 'products' );
			$this->set_add_new_js( 'wp_easycart_admin_open_slideout( \'new_product_box\' ); return false;' );
			$this->set_add_new_css( 'ecv2-btn ecv2-btn-primary' );
			$this->set_label( __( 'Product', 'wp-easycart' ), __( 'Products', 'wp-easycart' ) );
			$this->set_view_modes( array( 'table', 'card', 'spreadsheet' ) );
			$this->set_inline_editable_columns( array( 'title', 'model_number' ) );

			$this->set_list_columns( array(
				array( 'name' => 'image1', 'label' => '', 'format' => 'product_image', 'width' => 50 ),
				array( 'name' => 'title', 'label' => __( 'Product', 'wp-easycart' ), 'format' => 'product_title', 'linked' => true ),
				array( 'name' => 'model_number', 'label' => __( 'SKU', 'wp-easycart' ), 'format' => 'sku_chip', 'tablet_hide' => true ),
				array( 'name' => 'price', 'label' => __( 'Price', 'wp-easycart' ), 'format' => 'product_price' ),
				array( 'name' => 'stock_quantity', 'label' => __( 'Stock', 'wp-easycart' ), 'format' => 'stock_badge' ),
				array( 'select' => '(SELECT GROUP_CONCAT(ec_category.category_name ORDER BY ec_category.category_name SEPARATOR \', \') FROM ec_categoryitem LEFT JOIN ec_category ON ec_categoryitem.category_id = ec_category.category_id WHERE ec_categoryitem.product_id = ec_product.product_id) AS category_names', 'name' => 'category_names', 'label' => __( 'Category', 'wp-easycart' ), 'format' => 'category_tag', 'laptop_hide' => true ),
				array( 'select' => '(SELECT GROUP_CONCAT(ec_categoryitem.category_id ORDER BY ec_category.category_name SEPARATOR \',\') FROM ec_categoryitem LEFT JOIN ec_category ON ec_categoryitem.category_id = ec_category.category_id WHERE ec_categoryitem.product_id = ec_product.product_id) AS category_ids', 'name' => 'category_ids', 'format' => 'hidden', 'label' => '' ),
				array( 'select' => $this->get_completeness_select(), 'name' => 'completeness_score', 'label' => __( 'Score', 'wp-easycart' ), 'format' => 'completeness_ring', 'laptop_hide' => true ),
				array( 'select' => 'ec_product.activate_in_store as is_visible', 'name' => 'is_visible', 'label' => __( 'Status', 'wp-easycart' ), 'format' => 'status_toggle' ),
				array( 'name' => 'product_id', 'label' => __( 'ID', 'wp-easycart' ), 'format' => 'int', 'is_id' => true, 'laptop_hide' => true ),
				// Hidden data columns.
				array( 'name' => 'list_price', 'format' => 'hidden', 'label' => '' ),
				array( 'name' => 'show_stock_quantity', 'format' => 'hidden', 'label' => '' ),
				array( 'name' => 'use_optionitem_quantity_tracking', 'format' => 'hidden', 'label' => '' ),
				array( 'name' => 'views', 'format' => 'hidden', 'label' => '' ),
				array( 'name' => 'description', 'format' => 'hidden', 'label' => '' ),
				array( 'name' => 'short_description', 'format' => 'hidden', 'label' => '' ),
				array( 'name' => 'manufacturer_id', 'format' => 'hidden', 'label' => '' ),
				array( 'name' => 'square_id', 'format' => 'hidden', 'label' => '' ),
				array( 'name' => 'weight', 'format' => 'hidden', 'label' => '' ),
				array( 'select' => '(SELECT ec_manufacturer.name FROM ec_manufacturer WHERE ec_manufacturer.manufacturer_id = ec_product.manufacturer_id) AS manufacturer_name', 'name' => 'manufacturer_name', 'format' => 'hidden', 'label' => '' ),
				array( 'name' => 'product_images', 'format' => 'hidden', 'label' => '' ),
				array( 'name' => 'use_optionitem_images', 'format' => 'hidden', 'label' => '' ),
				array( 'name' => 'image2', 'format' => 'hidden', 'label' => '' ),
				array( 'name' => 'image3', 'format' => 'hidden', 'label' => '' ),
				array( 'name' => 'image4', 'format' => 'hidden', 'label' => '' ),
				array( 'name' => 'image5', 'format' => 'hidden', 'label' => '' ),
				array( 'select' => "(SELECT oii.product_images FROM ec_optionitemimage AS oii LEFT JOIN ec_optionitem AS oi ON oi.optionitem_id = oii.optionitem_id WHERE oii.product_id = ec_product.product_id ORDER BY oi.optionitem_order ASC LIMIT 1) AS oi_first_product_images", 'name' => 'oi_first_product_images', 'format' => 'hidden', 'label' => '' ),
				array( 'select' => "(SELECT oii.image1 FROM ec_optionitemimage AS oii LEFT JOIN ec_optionitem AS oi ON oi.optionitem_id = oii.optionitem_id WHERE oii.product_id = ec_product.product_id ORDER BY oi.optionitem_order ASC LIMIT 1) AS oi_first_image1", 'name' => 'oi_first_image1', 'format' => 'hidden', 'label' => '' ),
				array( 'name' => 'login_for_pricing', 'format' => 'hidden', 'label' => '' ),
				array( 'name' => 'login_for_pricing_user_level', 'format' => 'hidden', 'label' => '' ),
				array( 'name' => 'login_for_pricing_label', 'format' => 'hidden', 'label' => '' ),
				array( 'name' => 'enable_price_label', 'format' => 'hidden', 'label' => '' ),
				array( 'name' => 'replace_price_label', 'format' => 'hidden', 'label' => '' ),
				array( 'name' => 'custom_price_label', 'format' => 'hidden', 'label' => '' ),
				array( 'name' => 'show_custom_price_range', 'format' => 'hidden', 'label' => '' ),
				array( 'name' => 'price_range_low', 'format' => 'hidden', 'label' => '' ),
				array( 'name' => 'price_range_high', 'format' => 'hidden', 'label' => '' ),
				array( 'select' => '(SELECT COUNT(*) FROM ec_pricetier WHERE ec_pricetier.product_id = ec_product.product_id) AS tier_count', 'name' => 'tier_count', 'format' => 'hidden', 'label' => '' ),
				array( 'select' => '(SELECT COUNT(*) FROM ec_roleprice WHERE ec_roleprice.product_id = ec_product.product_id) AS roleprice_count', 'name' => 'roleprice_count', 'format' => 'hidden', 'label' => '' ),
			) );

			$this->set_custom_select(
				'(SELECT COUNT(*) FROM ec_categoryitem WHERE ec_categoryitem.product_id = ec_product.product_id) AS category_count'
			);

			$this->set_search_columns( array( 'ec_product.title', 'ec_product.short_description', 'ec_product.description', 'ec_product.model_number' ) );

			$this->set_bulk_actions( apply_filters( 'wp_easycart_admin_bulk_product_options', array(
				array( 'name' => 'delete-product', 'label' => __( 'Delete', 'wp-easycart' ) ),
				array( 'name' => 'activate-product', 'label' => __( 'Activate Selected', 'wp-easycart' ) ),
				array( 'name' => 'deactivate-product', 'label' => __( 'Deactivate Selected', 'wp-easycart' ) ),
				array( 'name' => 'export-products-csv', 'label' => __( 'Export Selected CSV', 'wp-easycart' ) ),
				array( 'name' => 'export-all-products-csv', 'label' => __( 'Export All CSV', 'wp-easycart' ) ),
			) ) );

			$this->set_row_menu_actions( array(
				array( 'label' => __( 'Edit Product', 'wp-easycart' ), 'name' => 'edit', 'icon' => 'edit', 'action' => 'edit' ),
				array( 'label' => __( 'Quick Edit', 'wp-easycart' ), 'name' => 'quick-edit', 'icon' => 'welcome-write-blog', 'href' => '#', 'onclick' => 'wp_easycart_open_quick_edit( \'product\', \'{id}\' ); return false;' ),
				array( 'label' => __( 'View on Site', 'wp-easycart' ), 'name' => 'view-on-site', 'icon' => 'visibility', 'href' => '#', 'target' => '_blank' ),
				array( 'label' => __( 'Duplicate', 'wp-easycart' ), 'name' => 'duplicate', 'icon' => 'admin-page', 'action' => 'duplicate-product' ),
				array( 'label' => __( 'Delete', 'wp-easycart' ), 'name' => 'delete', 'icon' => 'trash', 'action' => 'delete-product', 'danger' => true, 'confirm' => true ),
			) );

			$this->set_bulk_edit_fields( array(
				array( 'name' => 'price', 'label' => __( 'Price', 'wp-easycart' ), 'type' => 'price' ),
				array( 'name' => 'stock_quantity', 'label' => __( 'Stock Quantity', 'wp-easycart' ), 'type' => 'number' ),
				array( 'name' => 'activate_in_store', 'label' => __( 'Status', 'wp-easycart' ), 'type' => 'select', 'options' => array(
					array( 'value' => '1', 'label' => __( 'Active', 'wp-easycart' ) ),
					array( 'value' => '0', 'label' => __( 'Inactive', 'wp-easycart' ) ),
				) ),
				array( 'name' => 'manufacturer_id', 'label' => __( 'Manufacturer', 'wp-easycart' ), 'type' => 'select', 'options' => $this->get_manufacturer_options() ),
			) );

			$this->set_spreadsheet_columns( array(
				array( 'name' => 'title', 'label' => __( 'Title', 'wp-easycart' ), 'format' => 'string', 'ss_editable' => true ),
				array( 'name' => 'model_number', 'label' => __( 'SKU', 'wp-easycart' ), 'format' => 'string', 'ss_editable' => true ),
				array( 'name' => 'price', 'label' => __( 'Price', 'wp-easycart' ), 'format' => 'product_price_compact' ),
				array( 'name' => 'list_price', 'label' => __( 'List Price', 'wp-easycart' ), 'format' => 'currency', 'ss_default_hidden' => true ),
				array( 'name' => 'stock_quantity', 'label' => __( 'Stock', 'wp-easycart' ), 'format' => 'stock_badge' ),
				array( 'name' => 'weight', 'label' => __( 'Weight', 'wp-easycart' ), 'format' => 'string', 'ss_editable' => true, 'ss_default_hidden' => true ),
				array( 'name' => 'category_names', 'label' => __( 'Category', 'wp-easycart' ), 'format' => 'string', 'ss_default_hidden' => true ),
				array( 'name' => 'manufacturer_name', 'label' => __( 'Manufacturer', 'wp-easycart' ), 'format' => 'string', 'ss_default_hidden' => true ),
				array( 'select' => 'ec_product.activate_in_store as is_visible', 'name' => 'is_visible', 'label' => __( 'Active', 'wp-easycart' ), 'format' => 'status_toggle_sm' ),
				array( 'name' => 'product_id', 'label' => __( 'ID', 'wp-easycart' ), 'format' => 'int', 'is_id' => true ),
			) );

			// Filters.
			global $wpdb;
			$manufacturer_list = $wpdb->get_results( "SELECT ec_manufacturer.manufacturer_id AS value, ec_manufacturer.name AS label FROM ec_manufacturer ORDER BY ec_manufacturer.name ASC" );
			$category_list = $wpdb->get_results( "SELECT ec_category.category_id AS value, ec_category.category_name AS label FROM ec_category ORDER BY ec_category.category_name ASC" );

			$filters = array(
				array(
					'data' => array(
						(object) array( 'value' => '1', 'label' => __( 'Active', 'wp-easycart' ), 'icon' => 'visibility' ),
						(object) array( 'value' => '0', 'label' => __( 'Inactive', 'wp-easycart' ), 'icon' => 'hidden' ),
					),
					'label' => __( 'Status', 'wp-easycart' ),
					'type'  => 'pills',
					'where' => 'ec_product.activate_in_store = %d',
				),
				array(
					'data' => array(
						(object) array( 'value' => 'instock', 'label' => __( 'In Stock', 'wp-easycart' ), 'icon' => 'yes-alt' ),
						(object) array( 'value' => 'low', 'label' => __( 'Low Stock', 'wp-easycart' ), 'icon' => 'warning' ),
						(object) array( 'value' => 'oos', 'label' => __( 'Out of Stock', 'wp-easycart' ), 'icon' => 'dismiss' ),
					),
					'label' => __( 'Stock', 'wp-easycart' ),
					'type'  => 'pills',
					'where_callback' => true,
				),
				array(
					'data' => $category_list,
					'label' => __( 'Category', 'wp-easycart' ),
					'type'  => 'select',
					'select' => 'ec_categoryitem.category_id',
					'join' => 'LEFT JOIN ec_categoryitem ON (ec_categoryitem.product_id = ec_product.product_id)',
					'where' => 'ec_categoryitem.category_id = %d',
				),
				array(
					'data' => $manufacturer_list,
					'label' => __( 'Manufacturer', 'wp-easycart' ),
					'type'  => 'select',
					'where' => 'ec_product.manufacturer_id = %d',
				),
				array(
					'data' => array(),
					'label' => __( 'Price Range', 'wp-easycart' ),
					'type'  => 'range',
					'placeholder_min' => '$0',
					'placeholder_max' => __( 'No limit', 'wp-easycart' ),
					'where_callback' => true,
				),
				array(
					'data' => array(
						(object) array( 'value' => 'complete', 'label' => __( 'Complete (85%+)', 'wp-easycart' ), 'icon' => 'yes-alt' ),
						(object) array( 'value' => 'needs_work', 'label' => __( 'Needs Work (50–84%)', 'wp-easycart' ), 'icon' => 'warning' ),
						(object) array( 'value' => 'incomplete', 'label' => __( 'Incomplete (<50%)', 'wp-easycart' ), 'icon' => 'dismiss' ),
					),
					'label' => __( 'Completeness', 'wp-easycart' ),
					'type'  => 'pills',
					'where_callback' => true,
				),
			);
			$this->set_filters( apply_filters( 'wp_easycart_admin_product_list_filters', $filters ) );

			// Health dashboard.
			$this->compute_health_data();
			$health_stats = array(
				array( 'label' => __( 'Total', 'wp-easycart' ), 'value' => $this->health_data['total'], 'filter_value' => '', 'color' => 'default' ),
				array( 'label' => __( 'Active', 'wp-easycart' ), 'value' => $this->health_data['active'], 'filter_value' => 'active', 'color' => 'green' ),
				array( 'label' => __( 'Inactive', 'wp-easycart' ), 'value' => $this->health_data['inactive'], 'filter_value' => 'inactive', 'color' => 'gray' ),
				array( 'label' => __( 'Out of Stock', 'wp-easycart' ), 'value' => $this->health_data['out_of_stock'], 'filter_value' => 'out_of_stock', 'color' => 'red' ),
				array( 'label' => __( 'Low Stock', 'wp-easycart' ), 'value' => $this->health_data['low_stock'], 'filter_value' => 'low_stock', 'color' => 'amber' ),
				array( 'label' => __( 'No Image', 'wp-easycart' ), 'value' => $this->health_data['no_image'], 'filter_value' => 'no_image', 'color' => 'amber' ),
				array( 'label' => __( '$0 Price', 'wp-easycart' ), 'value' => $this->health_data['zero_price'], 'filter_value' => 'zero_price', 'color' => 'amber' ),
				array( 'label' => __( 'Incomplete', 'wp-easycart' ), 'value' => $this->health_data['incomplete'], 'filter_value' => 'incomplete', 'color' => 'amber' ),
				array( 'label' => __( 'On Sale', 'wp-easycart' ), 'value' => $this->health_data['on_sale'], 'filter_value' => 'on_sale', 'color' => 'cyan' ),
			);
			if ( $this->health_data['square_synced'] > 0 ) {
				$health_stats[] = array( 'label' => __( 'Square Synced', 'wp-easycart' ), 'value' => $this->health_data['square_synced'], 'filter_value' => 'square_synced', 'color' => 'default' );
			}
			$this->set_health_stats( $health_stats );
		}

		private function compute_health_data() {
			global $wpdb;
			$row = $wpdb->get_row( "SELECT 
				COUNT(*) AS total,
				SUM(CASE WHEN activate_in_store = 1 THEN 1 ELSE 0 END) AS active,
				SUM(CASE WHEN activate_in_store = 0 THEN 1 ELSE 0 END) AS inactive,
				SUM(CASE WHEN show_stock_quantity = 1 AND stock_quantity <= 0 THEN 1 ELSE 0 END) AS out_of_stock,
				SUM(CASE WHEN show_stock_quantity = 1 AND stock_quantity > 0 AND stock_quantity <= " . self::LOW_STOCK_THRESHOLD . " THEN 1 ELSE 0 END) AS low_stock,
				SUM(CASE WHEN image1 = '' OR image1 IS NULL THEN 1 ELSE 0 END) AS no_image,
				SUM(CASE WHEN price <= 0 THEN 1 ELSE 0 END) AS zero_price,
				SUM(CASE WHEN square_id IS NOT NULL AND square_id != '' THEN 1 ELSE 0 END) AS square_synced
			FROM ec_product" );

			$incomplete_sql = "SELECT COUNT(*) FROM ec_product WHERE 
				(title = '' OR title IS NULL OR price <= 0 OR image1 = '' OR image1 IS NULL OR 
				description = '' OR description IS NULL OR short_description = '' OR short_description IS NULL OR 
				model_number = '' OR model_number IS NULL OR 
				(SELECT COUNT(*) FROM ec_categoryitem WHERE ec_categoryitem.product_id = ec_product.product_id) = 0)";
 
			
			if ( 'square' == get_option( 'ec_option_payment_process_method' ) && get_option( 'ec_option_square_auto_product_sync' ) ) {
				$incomplete_sql .= ' AND (ec_product.square_id IS NULL OR ec_product.square_id = "")';
			}
 
			$incomplete = $wpdb->get_var( $incomplete_sql );

			$this->health_data = array(
				'total'        => $row ? (int) $row->total : 0,
				'active'       => $row ? (int) $row->active : 0,
				'inactive'     => $row ? (int) $row->inactive : 0,
				'out_of_stock' => $row ? (int) $row->out_of_stock : 0,
				'low_stock'    => $row ? (int) $row->low_stock : 0,
				'no_image'     => $row ? (int) $row->no_image : 0,
				'zero_price'   => $row ? (int) $row->zero_price : 0,
				'incomplete'   => (int) $incomplete,
				'on_sale'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM ec_product WHERE list_price > 0 AND list_price > price" ),
				'square_synced' => $row ? (int) $row->square_synced : 0,
			);
		}

		protected function get_health_filter_where( $filter_key ) {
			switch ( $filter_key ) {
				case 'active':
					return 'ec_product.activate_in_store = 1';
				case 'inactive':
					return 'ec_product.activate_in_store = 0';
				case 'out_of_stock':
					return 'ec_product.show_stock_quantity = 1 AND ec_product.stock_quantity <= 0';
				case 'low_stock':
					return 'ec_product.show_stock_quantity = 1 AND ec_product.stock_quantity > 0 AND ec_product.stock_quantity <= ' . self::LOW_STOCK_THRESHOLD;
				case 'no_image':
					return "(ec_product.image1 = '' OR ec_product.image1 IS NULL)";
				case 'zero_price':
					return 'ec_product.price <= 0';
				case 'incomplete':
					$where = "(ec_product.title = '' OR ec_product.title IS NULL OR ec_product.price <= 0 OR ec_product.image1 = '' OR ec_product.image1 IS NULL OR ec_product.description = '' OR ec_product.description IS NULL OR ec_product.short_description = '' OR ec_product.short_description IS NULL OR ec_product.model_number = '' OR ec_product.model_number IS NULL OR (SELECT COUNT(*) FROM ec_categoryitem WHERE ec_categoryitem.product_id = ec_product.product_id) = 0)";
					if ( 'square' == get_option( 'ec_option_payment_process_method' ) && get_option( 'ec_option_square_auto_product_sync' ) ) {
						$where .= ' AND (ec_product.square_id IS NULL OR ec_product.square_id = "")';
					}
					return $where;
				case 'on_sale':
					return 'ec_product.list_price > 0 AND ec_product.list_price > ec_product.price';
				case 'square_synced':
					return "ec_product.square_id IS NOT NULL AND ec_product.square_id != ''";
				default:
					return '';
			}
		}

		protected function get_filter_callback_where( $filter_index, $value ) {
			$filter = isset( $this->filters[ $filter_index ] ) ? $this->filters[ $filter_index ] : null;
			if ( ! $filter ) {
				return '';
			}

			$filter_label = isset( $filter['label'] ) ? $filter['label'] : '';

			// Stock filter.
			if ( strpos( strtolower( $filter_label ), 'stock' ) !== false && strpos( strtolower( $filter_label ), 'price' ) === false ) {
				$value = sanitize_key( $value );
				switch ( $value ) {
					case 'instock':
						return 'ec_product.show_stock_quantity = 1 AND ec_product.stock_quantity > 0';
					case 'low':
						return 'ec_product.show_stock_quantity = 1 AND ec_product.stock_quantity > 0 AND ec_product.stock_quantity <= ' . self::LOW_STOCK_THRESHOLD;
					case 'oos':
						return 'ec_product.show_stock_quantity = 1 AND ec_product.stock_quantity <= 0';
				}
			}

			// Price range filter (value format: "min-max").
			if ( strpos( strtolower( $filter_label ), 'price' ) !== false ) {
				$value = sanitize_text_field( $value );
				$parts = explode( '-', $value, 2 );
				$min = isset( $parts[0] ) && is_numeric( $parts[0] ) ? floatval( $parts[0] ) : null;
				$max = isset( $parts[1] ) && is_numeric( $parts[1] ) ? floatval( $parts[1] ) : null;
				$clauses = array();
				if ( null !== $min ) {
					$clauses[] = $this->wpdb->prepare( 'ec_product.price >= %f', $min );
				}
				if ( null !== $max ) {
					$clauses[] = $this->wpdb->prepare( 'ec_product.price <= %f', $max );
				}
				return ! empty( $clauses ) ? implode( ' AND ', $clauses ) : '';
			}

			// Completeness filter.
			if ( strpos( strtolower( $filter_label ), 'completeness' ) !== false ) {
				$value = sanitize_key( $value );
				// Build a scoring expression: count how many of the SCORE_FIELDS pass.
				$total_fields = count( self::SCORE_FIELDS );
				$case_parts = array();
				foreach ( self::SCORE_FIELDS as $field => $meta ) {
					if ( $meta['check'] === 'greater_zero' ) {
						if ( $field === 'category_count' ) {
							$case_parts[] = 'CASE WHEN (SELECT COUNT(*) FROM ec_categoryitem WHERE ec_categoryitem.product_id = ec_product.product_id) > 0 THEN 1 ELSE 0 END';
						} else {
							$case_parts[] = 'CASE WHEN ec_product.' . $field . ' > 0 THEN 1 ELSE 0 END';
						}
					} else {
						$case_parts[] = "CASE WHEN ec_product." . $field . " IS NOT NULL AND ec_product." . $field . " != '' THEN 1 ELSE 0 END";
					}
				}
				$score_expr = '((' . implode( ' + ', $case_parts ) . ') * 100 / ' . $total_fields . ')';
				switch ( $value ) {
					case 'complete':
						return $score_expr . ' >= 85';
					case 'needs_work':
						return $score_expr . ' >= 50 AND ' . $score_expr . ' < 85';
					case 'incomplete':
						return $score_expr . ' < 50';
				}
			}

			return '';
		}

		private function get_completeness_select() {
			// SQL to calculate completeness on-the-fly is too complex; we calculate in PHP.
			return '0 AS completeness_score';
		}

		private function get_manufacturer_options() {
			global $wpdb;
			$manufacturers = $wpdb->get_results( "SELECT manufacturer_id AS value, name AS label FROM ec_manufacturer ORDER BY name ASC" );
			$options = array();
			foreach ( $manufacturers as $m ) {
				$options[] = array( 'value' => $m->value, 'label' => $m->label );
			}
			return $options;
		}

		public static function is_square_locked( $result ) {
			if ( 'square' !== get_option( 'ec_option_payment_process_method' ) ) {
				return false;
			}
			if ( ! get_option( 'ec_option_square_auto_product_sync' ) && ! get_option( 'ec_option_square_auto_sync' ) ) {
				return false;
			}
			return ( isset( $result->square_id ) && '' !== $result->square_id );
		}

		/**
		 * Override parent to add Square sync data attribute and lock class on table rows.
		 */
		protected function print_table_row( $result ) {
			$row_id = $result->{ $this->key };
			$is_square = self::is_square_locked( $result );
			$extra_class = $is_square ? ' ecv2-row-square-locked' : '';
			$is_visible = isset( $result->is_visible ) ? (bool) $result->is_visible : true;
			if ( ! $is_visible ) {
				$extra_class .= ' ecv2-row-inactive';
			}
			$square_attr = $is_square ? ' data-square-synced="1"' : '';

			echo '<tr class="ecv2-row' . esc_attr( $extra_class ) . '" data-id="' . esc_attr( $row_id ) . '"' . $square_attr . '>';

			// Checkbox — always available for bulk actions (delete, activate, deactivate, export).
			echo '<td class="ecv2-col-check"><input type="checkbox" name="bulk[]" value="' . esc_attr( $row_id ) . '" class="ecv2-row-check" /></td>';
 
			// Data columns.
			foreach ( $this->list_columns as $col ) {
				if ( isset( $col['format'] ) && $col['format'] === 'hidden' ) {
					continue;
				}
				$extra_classes = '';
				if ( isset( $col['tablet_hide'] ) && $col['tablet_hide'] ) {
					$extra_classes .= ' ecv2-hide-tablet';
				}
				if ( isset( $col['laptop_hide'] ) && $col['laptop_hide'] ) {
					$extra_classes .= ' ecv2-hide-laptop';
				}
				// Disable inline editing for Square-locked products.
				$editable_attr = '';
				if ( ! $is_square && in_array( $col['name'], $this->inline_editable_columns ) ) {
					$editable_attr = ' data-editable="true" data-field="' . esc_attr( $col['name'] ) . '"';
				}
				echo '<td class="ecv2-cell ecv2-cell-' . esc_attr( $col['name'] ) . $extra_classes . '"' . $editable_attr . '>';
				$this->print_cell_content( $result, $col );
				echo '</td>';
			}
 
			// Actions.
			echo '<td class="ecv2-col-actions">';
			$this->print_row_actions( $result );
			echo '</td>';
 
			echo '</tr>';
		}

		protected function print_cell_content( $result, $col ) {
			switch ( $col['format'] ) {
				case 'product_image':
					$this->print_product_image( $result );
					break;
				case 'product_title':
					$this->print_product_title( $result, $col );
					break;
				case 'sku_chip':
					$this->print_sku_chip( $result );
					break;
				case 'product_price':
					$this->print_product_price( $result );
					break;
				case 'stock_badge':
					$this->print_stock_badge( $result );
					break;
				case 'status_toggle':
					$this->print_status_toggle( $result );
					break;
				case 'completeness_ring':
					$this->print_completeness_ring( $result );
					break;
				case 'category_tag':
					$this->print_category_tag( $result );
					break;
				case 'product_price_compact':
					$this->print_product_price_compact( $result );
					break;
				case 'status_toggle_sm':
					$this->print_status_toggle_sm( $result );
					break;
				default:
					parent::print_cell_content( $result, $col );
					break;
			}
		}

		/**
		 * Resolve the best thumbnail URL for a product row.
		 *
		 * Priority order:
		 * 1. If use_optionitem_images is on, check optionitem product_images CSV, then optionitem image1.
		 * 2. Product-level product_images CSV (pro gallery).
		 * 3. Legacy image1 field.
		 * 4. Fallback through image2-5.
		 */
		public static function resolve_thumbnail_url( $result ) {
			// 1. Optionitem images take priority when enabled.
			if ( ! empty( $result->use_optionitem_images ) ) {
				// Try optionitem product_images CSV first.
				if ( ! empty( $result->oi_first_product_images ) ) {
					$url = self::resolve_first_from_csv( $result->oi_first_product_images );
					if ( $url ) {
						return $url;
					}
				}
				// Try optionitem legacy image1.
				if ( ! empty( $result->oi_first_image1 ) ) {
					return self::get_image_url( $result->oi_first_image1 );
				}
			}

			// 2. Product-level product_images CSV (pro gallery).
			if ( ! empty( $result->product_images ) ) {
				$url = self::resolve_first_from_csv( $result->product_images );
				if ( $url ) {
					return $url;
				}
			}

			// 3. Legacy image1.
			if ( ! empty( $result->image1 ) ) {
				return self::get_image_url( $result->image1 );
			}

			// 4. Fallback: image2-5.
			for ( $i = 2; $i <= 5; $i++ ) {
				$field = 'image' . $i;
				if ( isset( $result->$field ) && ! empty( $result->$field ) ) {
					$val = $result->$field;
					if ( substr( $val, 0, 7 ) === 'http://' || substr( $val, 0, 8 ) === 'https://' ) {
						return $val;
					}
					return plugins_url( '/wp-easycart-data/products/pics' . $i . '/' . $val, EC_PLUGIN_DATA_DIRECTORY );
				}
			}

			return '';
		}

		/**
		 * Given a product_images CSV string, resolve the first displayable image URL.
		 */
		public static function resolve_first_from_csv( $csv ) {
			$items = explode( ',', $csv );
			foreach ( $items as $item ) {
				$item = trim( $item );
				if ( empty( $item ) ) {
					continue;
				}

				// Skip video types — they aren't useful as thumbnails.
				if ( substr( $item, 0, 6 ) === 'video:' || substr( $item, 0, 8 ) === 'youtube:' || substr( $item, 0, 6 ) === 'vimeo:' ) {
					continue;
				}

				// WordPress media library attachment ID.
				if ( is_numeric( $item ) ) {
					$attachment = wp_get_attachment_image_src( (int) $item, 'medium' );
					if ( $attachment ) {
						return $attachment[0];
					}
					continue;
				}

				// image: prefixed URL.
				if ( substr( $item, 0, 6 ) === 'image:' ) {
					return substr( $item, 6 );
				}

				// Legacy image1-5 references.
				if ( preg_match( '/^image[1-5]$/', $item ) ) {
					// These refer back to ec_product.image1-5 which we don't have in this context;
					// skip — the legacy fallback in step 3/4 will catch these.
					continue;
				}

				// External/full URL.
				if ( substr( $item, 0, 7 ) === 'http://' || substr( $item, 0, 8 ) === 'https://' ) {
					return $item;
				}
			}

			return '';
		}

		private function print_product_image( $result ) {
			$image_url = self::resolve_thumbnail_url( $result );
			$product_id = (int) $result->product_id;
			$is_square = self::is_square_locked( $result );

			if ( $is_square ) {
				// Square-locked: show image but no click-to-edit, no camera overlay.
				echo '<div class="ecv2-image-wrap ecv2-image-wrap-locked" title="' . esc_attr__( 'Images managed by Square', 'wp-easycart' ) . '">';
				if ( $image_url ) {
					echo '<img src="' . esc_url( $image_url ) . '" alt="" class="ecv2-product-thumb" loading="lazy" onerror="ecv2_image_error(this);" />';
				} else {
					echo '<div class="ecv2-product-thumb-placeholder"><span class="dashicons dashicons-format-image"></span></div>';
				}
				echo '<span class="ecv2-image-lock-icon"><span class="dashicons dashicons-lock"></span></span>';
				echo '</div>';
				return;
			}

			$image_nonce = wp_create_nonce( 'wp-easycart-ecv2-image-manager-' . $product_id );
			echo '<div class="ecv2-image-wrap" data-product-id="' . esc_attr( $product_id ) . '" data-image-nonce="' . esc_attr( $image_nonce ) . '">';
			if ( $image_url ) {
				echo '<img src="' . esc_url( $image_url ) . '" alt="" class="ecv2-product-thumb" loading="lazy" onerror="ecv2_image_error(this);" />';
			} else {
				echo '<div class="ecv2-product-thumb-placeholder"><span class="dashicons dashicons-format-image"></span></div>';
			}
			do_action( 'wp_easycart_admin_ecv2_product_image_edit_trigger', $product_id, 'table' );
			echo '</div>';
		}

		private function print_product_title( $result, $col ) {
			$edit_url = $this->get_url( $this->key, $result->product_id, false, 'ec_admin_form_action', 'edit' );
			$duplicate_url = $this->get_url( $this->key, $result->product_id, false, 'ec_admin_form_action', 'duplicate-product' );
			$delete_url = $this->get_url( $this->key, $result->product_id, false, 'ec_admin_form_action', 'delete-product' );
			$is_square = self::is_square_locked( $result );

			echo '<div class="ecv2-product-title-wrap">';

			if ( $is_square ) {
				// Square logo inline with title + lock chip.
				echo '<span class="ecv2-square-title-badge">';
				echo '<img src="' . esc_url( plugins_url( 'wp-easycart/admin/images/square-logo.png' ) ) . '" alt="" class="ecv2-square-title-logo" />';
				echo '<span class="dashicons dashicons-lock ecv2-square-title-lock"></span>';
				echo esc_html( strip_tags( wp_unslash( $result->title ) ) );
				echo '</span>';
			} else {
				echo '<span class="ecv2-product-title-text">';
				echo esc_html( strip_tags( wp_unslash( $result->title ) ) );
				echo '</span>';
			}

			echo '<div class="ecv2-product-row-actions">';
			echo '<a href="' . esc_url( $edit_url ) . '" class="ecv2-row-action-link">' . esc_html__( 'Edit', 'wp-easycart' ) . '</a>';
			echo '<span class="ecv2-row-action-sep">|</span>';

			if ( $is_square ) {
				// Quick Edit disabled for Square-synced products.
				echo '<span class="ecv2-row-action-link ecv2-row-action-disabled" title="' . esc_attr__( 'Quick edit disabled — this product is synced with Square', 'wp-easycart' ) . '">' . esc_html__( 'Quick Edit', 'wp-easycart' ) . '</span>';
			} else {
				echo '<a href="#" class="ecv2-row-action-link" onclick="wp_easycart_open_quick_edit( \'product\', ' . esc_attr( (int) $result->product_id ) . ' ); return false;">' . esc_html__( 'Quick Edit', 'wp-easycart' ) . '</a>';
			}

			$view_url = $this->get_view_url( $result->product_id );
			if ( ! empty( $view_url ) ) {
				echo '<span class="ecv2-row-action-sep">|</span>';
				echo '<a href="' . esc_url( $view_url ) . '" class="ecv2-row-action-link" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View', 'wp-easycart' ) . '</a>';
			}

			echo '<span class="ecv2-row-action-sep">|</span>';
			echo '<a href="' . esc_url( $duplicate_url ) . '" class="ecv2-row-action-link">' . esc_html__( 'Duplicate', 'wp-easycart' ) . '</a>';
			echo '<span class="ecv2-row-action-sep">|</span>';
			echo '<a href="' . esc_url( $delete_url ) . '" class="ecv2-row-action-link ecv2-row-action-link-danger" onclick="return confirm(\'' . esc_attr__( 'Are you sure you want to delete this product?', 'wp-easycart' ) . '\');">' . esc_html__( 'Delete', 'wp-easycart' ) . '</a>';
			echo '</div>';
			echo '</div>';
		}

		private function print_sku_chip( $result ) {
			if ( ! empty( $result->model_number ) ) {
				echo '<span class="ecv2-sku-chip">' . esc_html( $result->model_number ) . '</span>';
			} else {
				echo '<span class="ecv2-sku-empty">—</span>';
			}
		}

		private function print_product_price( $result ) {
			$product_id = (int) $result->product_id;
			$is_square = self::is_square_locked( $result );

			// Square-locked: read-only price display, no click handler, no dropdown.
			if ( $is_square ) {
				echo '<div class="ecv2-price-cell ecv2-price-cell-locked" title="' . esc_attr__( 'Price managed by Square', 'wp-easycart' ) . '">';
				echo '<div class="ecv2-price-wrap">';
				echo '<span class="ecv2-price-current">' . esc_html( $GLOBALS['currency']->get_currency_display( $result->price ) ) . '</span>';
				if ( isset( $result->list_price ) && $result->list_price > 0 && $result->list_price != $result->price && $result->list_price > $result->price ) {
					echo ' <span class="ecv2-price-list">' . esc_html( $GLOBALS['currency']->get_currency_display( $result->list_price ) ) . '</span>';
				}
				echo ' <span class="dashicons dashicons-lock ecv2-cell-lock-icon"></span>';
				echo '</div>';
				echo '</div>';
				return;
			}

			$nonce = wp_create_nonce( 'wp-easycart-ecv2-price-edit-' . $product_id );
			$has_variant_pricing = false;
			$variant_price_min = 0;
			$variant_price_max = 0;
			$variant_price_count = 0;

			// Check for variant-level pricing when option item tracking is enabled.
			if ( $result->use_optionitem_quantity_tracking ) {
				global $wpdb;
				$variant_prices = $wpdb->get_row( $wpdb->prepare(
					"SELECT MIN( CASE WHEN price >= 0 THEN price END ) AS min_price,
							MAX( CASE WHEN price >= 0 THEN price END ) AS max_price,
							SUM( CASE WHEN price >= 0 THEN 1 ELSE 0 END ) AS price_count
					 FROM ec_optionitemquantity
					 WHERE product_id = %d AND is_enabled = 1 AND price != -1",
					$product_id
				) );
				if ( $variant_prices && (int) $variant_prices->price_count > 0 ) {
					$has_variant_pricing = true;
					$variant_price_min = (float) $variant_prices->min_price;
					$variant_price_max = (float) $variant_prices->max_price;
					$variant_price_count = (int) $variant_prices->price_count;
				}
			}

			$volume_nonce  = wp_create_nonce( 'wp-easycart-ecv2-volume-pricing-' . $product_id );
			$b2b_nonce     = wp_create_nonce( 'wp-easycart-ecv2-b2b-pricing-' . $product_id );

			// Snapshot of every advanced-pricing field for one-shot slideout hydration.
			// Picked up in JS via JSON.parse( $cell.attr('data-advanced') ).
			$advanced_payload = array(
				'product_name'                 => isset( $result->title ) ? (string) $result->title : '',
				'show_custom_price_range'      => ( ! empty( $result->show_custom_price_range ) && (int) $result->show_custom_price_range === 1 ) ? 1 : 0,
				'price_range_low'              => ( isset( $result->price_range_low ) && (float) $result->price_range_low > 0 ) ? number_format( (float) $result->price_range_low, 2, '.', '' ) : '',
				'price_range_high'             => ( isset( $result->price_range_high ) && (float) $result->price_range_high > 0 ) ? number_format( (float) $result->price_range_high, 2, '.', '' ) : '',
				'enable_price_label'           => isset( $result->enable_price_label ) ? (int) $result->enable_price_label : 0,
				'replace_price_label'          => ( ! empty( $result->replace_price_label ) && (int) $result->replace_price_label === 1 ) ? 1 : 0,
				'custom_price_label'           => isset( $result->custom_price_label ) ? (string) $result->custom_price_label : '',
				'login_for_pricing'            => ( ! empty( $result->login_for_pricing ) && (int) $result->login_for_pricing === 1 ) ? 1 : 0,
				'login_for_pricing_label'      => isset( $result->login_for_pricing_label ) ? (string) $result->login_for_pricing_label : '',
				'login_for_pricing_user_level' => isset( $result->login_for_pricing_user_level ) ? (string) $result->login_for_pricing_user_level : '',
				'tier_count'                   => isset( $result->tier_count ) ? (int) $result->tier_count : 0,
				'roleprice_count'              => isset( $result->roleprice_count ) ? (int) $result->roleprice_count : 0,
				'use_optionitem_quantity_tracking' => ( ! empty( $result->use_optionitem_quantity_tracking ) && (int) $result->use_optionitem_quantity_tracking === 1 ) ? 1 : 0,
			);

			echo '<div class="ecv2-price-cell" data-product-id="' . esc_attr( $product_id ) . '" data-nonce="' . esc_attr( $nonce ) . '" data-volume-nonce="' . esc_attr( $volume_nonce ) . '" data-b2b-nonce="' . esc_attr( $b2b_nonce ) . '" data-price="' . esc_attr( (float) $result->price ) . '" data-list-price="' . esc_attr( (float) $result->list_price ) . '" data-has-variant-pricing="' . esc_attr( $has_variant_pricing ? 1 : 0 ) . '" data-advanced="' . esc_attr( wp_json_encode( $advanced_payload ) ) . '">';

			if ( $has_variant_pricing ) {
				$variant_gate = ecv2_get_variant_tracking_gate();
				$variant_tracking_enabled = ( 'enabled' === $variant_gate['state'] );
				$variant_badge_onclick    = $variant_tracking_enabled ? 'ecv2_open_variant_from_badge( this ); return false;' : 'show_pro_required(); return false;';

				echo '<div class="ecv2-price-cell-main">';
				echo '<button type="button" class="ecv2-price-badge-btn" onclick="ecv2_open_price_editor( this );">';
				echo '<div class="ecv2-price-wrap">';
				if ( $variant_price_min === $variant_price_max ) {
					echo '<span class="ecv2-price-current">' . esc_html( $GLOBALS['currency']->get_currency_display( $variant_price_min ) ) . '</span>';
				} else {
					echo '<span class="ecv2-price-current">' . esc_html( $GLOBALS['currency']->get_currency_display( $variant_price_min ) ) . '</span>';
					echo '<span class="ecv2-price-range-sep">&ndash;</span>';
					echo '<span class="ecv2-price-current">' . esc_html( $GLOBALS['currency']->get_currency_display( $variant_price_max ) ) . '</span>';
				}
				echo '</div>';
				if ( (float) $result->price > 0 ) {
					echo '<span class="ecv2-price-base-label">';
					echo esc_html__( 'Base:', 'wp-easycart' ) . ' <span class="ecv2-price-base-value">' . esc_html( $GLOBALS['currency']->get_currency_display( $result->price ) ) . '</span>';
					echo '</span>';
				}
				echo '</button>';
				// Variant badge — clickable shortcut directly into the variant manager.
				/* translators: %d is the number of variants that have a per-variant price set. */
				$variant_badge_aria = sprintf(
					_n(
						'%d variant priced — manage',
						'%d variants priced — manage',
						$variant_price_count,
						'wp-easycart'
					),
					$variant_price_count
				);
				echo '<button type="button" class="ecv2-price-variant-badge"';
				echo ' onclick="' . esc_attr( $variant_badge_onclick ) . '"';
				echo ' title="' . esc_attr__( 'Manage variant pricing', 'wp-easycart' ) . '"';
				echo ' aria-label="' . esc_attr( $variant_badge_aria ) . '">';
				echo '<span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>';
				echo '<span class="ecv2-price-variant-count">' . esc_html( $variant_price_count ) . '</span>';
				echo '</button>';
				echo '</div>'; // .ecv2-price-cell-main
				$this->print_price_cell_badges( $result );
				$this->print_price_editor_menu( $result, true );
			} else {
				// Standard pricing — clickable to open price editor.
				echo '<div class="ecv2-price-cell-main">';
				echo '<button type="button" class="ecv2-price-badge-btn" onclick="ecv2_open_price_editor( this );">';
				echo '<div class="ecv2-price-wrap">';
				echo '<span class="ecv2-price-current">' . esc_html( $GLOBALS['currency']->get_currency_display( $result->price ) ) . '</span>';
				if ( isset( $result->list_price ) && $result->list_price > 0 && $result->list_price != $result->price ) {
					if ( $result->list_price > $result->price ) {
						echo ' <span class="ecv2-price-list">' . esc_html( $GLOBALS['currency']->get_currency_display( $result->list_price ) ) . '</span>';
						$pct = round( ( $result->list_price - $result->price ) / $result->list_price * 100 );
						echo ' <span class="ecv2-sale-tag">' . esc_html( $pct . '% ' . __( 'off', 'wp-easycart' ) ) . '</span>';
					} else {
						echo ' <span class="ecv2-price-list">' . esc_html( $GLOBALS['currency']->get_currency_display( $result->list_price ) ) . '</span>';
					}
				}
				echo '</div>';
				echo '</button>';
				echo '</div>'; // .ecv2-price-cell-main
				$this->print_price_cell_badges( $result );
				$this->print_price_editor_menu( $result );
			}

			echo '</div>'; // .ecv2-price-cell
		}

		private function print_price_cell_badges( $result ) {
			$product_id = (int) $result->product_id;
			$advanced_pricing_enabled = (bool) apply_filters( 'wp_easycart_admin_ecv2_advanced_pricing_enabled', false );
			$badges = array();

			// Login required
			if ( ! empty( $result->login_for_pricing ) && (int) $result->login_for_pricing === 1 ) {
				$badges[] = array(
					'icon'  => 'lock',
					'label' => __( 'Login', 'wp-easycart' ),
					'title' => __( 'Login required to view price — click to manage', 'wp-easycart' ),
					'class' => 'ecv2-price-flag-login',
					'action' => $advanced_pricing_enabled ? 'ecv2_open_advanced_from_badge( this ); return false;' : 'show_pro_required(); return false;',
				);
			}

			// Custom price label
			if ( ! empty( $result->enable_price_label ) && (int) $result->enable_price_label > 0 ) {
				$label_text = ! empty( $result->custom_price_label ) ? $result->custom_price_label : __( 'Custom label', 'wp-easycart' );
				$badges[] = array(
					'icon'  => 'tag',
					'label' => $label_text,
					'title' => __( 'Custom price label is enabled — click to edit', 'wp-easycart' ),
					'class' => 'ecv2-price-flag-pricelabel',
					'action' => $advanced_pricing_enabled ? 'ecv2_open_advanced_from_badge( this ); return false;' : 'show_pro_required(); return false;',
				);
			}

			// Custom price range — clickable shortcut into the Advanced Pricing slideout (PRO).
			if ( ! empty( $result->show_custom_price_range ) && (int) $result->show_custom_price_range === 1 ) {
				$range_low  = (float) $result->price_range_low;
				$range_high = (float) $result->price_range_high;
				$badges[] = array(
					'icon'  => 'leftright',
					'label' => $GLOBALS['currency']->get_currency_display( $range_low ) . '–' . $GLOBALS['currency']->get_currency_display( $range_high ),
					'title' => __( 'Displayed as a price range on the storefront — click to edit', 'wp-easycart' ),
					'class' => 'ecv2-price-flag-range',
					'action' => $advanced_pricing_enabled ? 'ecv2_open_advanced_from_badge( this ); return false;' : 'show_pro_required(); return false;',
				);
			}

			// Volume / tiered pricing — clickable shortcut into the volume manager (PRO).
			$tier_count = isset( $result->tier_count ) ? (int) $result->tier_count : 0;
			if ( $tier_count > 0 ) {
				$badges[] = array(
					'icon'  => 'chart-bar',
					/* translators: %d is the number of volume pricing tiers configured */
					'label' => sprintf( _n( '%d tier', '%d tiers', $tier_count, 'wp-easycart' ), $tier_count ),
					'title' => __( 'Volume pricing — click to manage', 'wp-easycart' ),
					'class' => 'ecv2-price-flag-tier',
					// 'action' makes the flag render as a <button> with the given onclick. PRO-gated.
					'action' => $advanced_pricing_enabled ? 'ecv2_open_volume_from_badge( this ); return false;' : 'show_pro_required(); return false;',
				);
			}

			// B2B / role-based pricing — clickable shortcut into the B2B manager (PRO).
			$role_count = isset( $result->roleprice_count ) ? (int) $result->roleprice_count : 0;
			if ( $role_count > 0 ) {
				$badges[] = array(
					'icon'  => 'groups',
					/* translators: %d is the number of B2B role-based prices configured */
					'label' => sprintf( _n( '%d B2B role', '%d B2B roles', $role_count, 'wp-easycart' ), $role_count ),
					'title' => __( 'B2B role pricing — click to manage', 'wp-easycart' ),
					'class' => 'ecv2-price-flag-b2b',
					'action' => $advanced_pricing_enabled ? 'ecv2_open_b2b_from_badge( this ); return false;' : 'show_pro_required(); return false;',
				);
			}

			$badges = apply_filters( 'wp_easycart_admin_ecv2_price_cell_badges', $badges, $result );

			if ( empty( $badges ) || ! is_array( $badges ) ) {
				return;
			}

			echo '<div class="ecv2-price-flags">';
			foreach ( $badges as $badge ) {
				if ( ! is_array( $badge ) || empty( $badge['label'] ) ) {
					continue;
				}
				$icon  = isset( $badge['icon'] ) ? sanitize_html_class( $badge['icon'] ) : 'marker';
				$class = isset( $badge['class'] ) ? sanitize_html_class( $badge['class'] ) : '';
				$title = isset( $badge['title'] ) ? $badge['title'] : '';

				if ( ! empty( $badge['action'] ) ) {
					// Interactive flag — rendered as a button so it's keyboard-focusable and
					// announces correctly. The 'action' value comes from a fixed allowlist
					// inside this method (never user input), so the onclick attribute is safe.
					echo '<button type="button" class="ecv2-price-flag ecv2-price-flag-action ' . esc_attr( $class ) . '" onclick="' . esc_attr( $badge['action'] ) . '" title="' . esc_attr( $title ) . '">';
				} else {
					// Static informational flag.
					echo '<span class="ecv2-price-flag ' . esc_attr( $class ) . '" title="' . esc_attr( $title ) . '">';
				}
				echo '<span class="dashicons dashicons-' . esc_attr( $icon ) . '" aria-hidden="true"></span>';
				echo '<span class="ecv2-price-flag-label">' . esc_html( $badge['label'] ) . '</span>';
				if ( ! empty( $badge['action'] ) ) {
					echo '</button>';
				} else {
					echo '</span>';
				}
			}
			echo '</div>';
		}

		private function print_price_editor_menu( $result, $has_variant_pricing = false ) {
			$product_id = (int) $result->product_id;

			$advanced_pricing_enabled = (bool) apply_filters( 'wp_easycart_admin_ecv2_advanced_pricing_enabled', false );

			$price_val      = number_format( (float) $result->price, 2, '.', '' );
			$list_price_val = ( (float) $result->list_price > 0 ) ? number_format( (float) $result->list_price, 2, '.', '' ) : '';

			// Determine whether any advanced-pricing flag is active so we can show the indicator pip.
			// The pip shows on FREE sites too: it tells the merchant something is configured even
			// when their license has lapsed and they can't currently edit it.
			$tier_count = isset( $result->tier_count ) ? (int) $result->tier_count : 0;
			$role_count = isset( $result->roleprice_count ) ? (int) $result->roleprice_count : 0;
			$has_advanced_active = (
				( ! empty( $result->show_custom_price_range ) && (int) $result->show_custom_price_range === 1 ) ||
				( isset( $result->enable_price_label ) && (int) $result->enable_price_label > 0 ) ||
				( ! empty( $result->login_for_pricing ) && (int) $result->login_for_pricing === 1 ) ||
				$tier_count > 0 ||
				$role_count > 0
			);

			echo '<div class="ecv2-price-menu ecv2-price-menu-expanded">';

			// --- Price (basic, always visible). -------------------------------
			// Variant-pricing products edit the BASE price here — variants whose per-variant
			// price is -1 fall back to this. The label is reframed for clarity in that case.
			echo '<div class="ecv2-price-menu-section">';
			if ( $has_variant_pricing ) {
				echo '<label class="ecv2-price-menu-label">' . esc_html__( 'Base Price', 'wp-easycart' ) . ' <span class="ecv2-price-menu-hint">' . esc_html__( '(used when variants don\'t override)', 'wp-easycart' ) . '</span></label>';
			} else {
				echo '<label class="ecv2-price-menu-label">' . esc_html__( 'Price', 'wp-easycart' ) . '</label>';
			}
			echo '<input type="text" class="ecv2-price-menu-input" data-field="price" value="' . esc_attr( $price_val ) . '" data-original="' . esc_attr( $price_val ) . '" />';
			echo '</div>';

			// --- List Price (basic, always visible). --------------------------
			echo '<div class="ecv2-price-menu-section">';
			echo '<label class="ecv2-price-menu-label">' . esc_html__( 'List Price', 'wp-easycart' ) . ' <span class="ecv2-price-menu-hint">' . esc_html__( '(original / compare-at)', 'wp-easycart' ) . '</span></label>';
			echo '<input type="text" class="ecv2-price-menu-input" data-field="list_price" value="' . esc_attr( $list_price_val ) . '" data-original="' . esc_attr( $list_price_val ) . '" placeholder="' . esc_attr__( 'None', 'wp-easycart' ) . '" />';
			echo '</div>';

			// --- Sale-percent live preview. -----------------------------------
			echo '<div id="ecv2-price-menu-preview-' . esc_attr( $product_id ) . '" class="ecv2-price-menu-preview" style="display:none;"></div>';

			// --- Advanced pricing entry point. --------------------------------
			// Click routes either to PRO slideout (licensed) or upsell (unlicensed). Pip shows
			// when ANY advanced field/manager is configured, regardless of license state.
			$advanced_onclick = $advanced_pricing_enabled
				? 'ecv2_open_advanced_pricing( this ); return false;'
				: 'show_pro_required(); return false;';
			echo '<button type="button" class="ecv2-price-menu-advanced-link" onclick="' . esc_attr( $advanced_onclick ) . '">';
			echo '<span class="ecv2-price-menu-advanced-label">' . esc_html__( 'Advanced pricing', 'wp-easycart' ) . '</span>';
			if ( ! $advanced_pricing_enabled ) {
				echo '<span class="ecv2-price-menu-pro-tag" title="' . esc_attr__( 'Requires PRO', 'wp-easycart' ) . '">' . esc_html__( 'PRO', 'wp-easycart' ) . '</span>';
			}
			if ( $has_advanced_active ) {
				echo '<span class="ecv2-price-menu-advanced-pip" aria-label="' . esc_attr__( 'Advanced pricing options are configured for this product', 'wp-easycart' ) . '" title="' . esc_attr__( 'Advanced pricing configured', 'wp-easycart' ) . '"></span>';
			}
			echo '<span class="dashicons dashicons-arrow-right-alt2 ecv2-price-menu-advanced-chev"></span>';
			echo '</button>';

			// PRO extension point — additional inline sections still hook here if anything needs
			// to live in the popover itself (vs. the slideout). Most extensions should target
			// the slideout's extras hook below instead.
			do_action( 'wp_easycart_admin_ecv2_price_editor_extra_sections', $result );

			// --- Footer actions. ----------------------------------------------
			echo '<div class="ecv2-price-menu-actions">';
			echo '<button type="button" class="ecv2-btn ecv2-btn-ghost ecv2-btn-sm" onclick="ecv2_close_price_editor( this );">' . esc_html__( 'Cancel', 'wp-easycart' ) . '</button>';
			echo '<button type="button" class="ecv2-btn ecv2-btn-primary ecv2-btn-sm" onclick="ecv2_save_price_editor( this );">' . esc_html__( 'Save', 'wp-easycart' ) . '</button>';
			echo '</div>';

			echo '</div>'; // .ecv2-price-menu
		}

		private function get_pricing_user_roles() {
			global $wpdb;
			$roles = $wpdb->get_results( "SELECT ec_role.role_label AS id, ec_role.role_label AS value FROM ec_role ORDER BY role_label ASC" );
			if ( ! is_array( $roles ) ) {
				$roles = array();
			}
			$roles = apply_filters( 'wp_easycart_admin_product_details_user_roles', $roles );
			return is_array( $roles ) ? $roles : array();
		}

		private function print_category_tag( $result ) {
			$product_id = (int) $result->product_id;
			$nonce = wp_create_nonce( 'wp-easycart-ecv2-category-' . $product_id );

			// Build category data as JSON for JS.
			$cat_data = array();
			if ( ! empty( $result->category_names ) && ! empty( $result->category_ids ) ) {
				$names = explode( ', ', $result->category_names );
				$ids = explode( ',', $result->category_ids );
				for ( $i = 0; $i < count( $ids ); $i++ ) {
					if ( isset( $names[ $i ] ) ) {
						$cat_data[] = array( 'id' => (int) $ids[ $i ], 'name' => $names[ $i ] );
					}
				}
			}

			echo '<div class="ecv2-category-cell" data-product-id="' . esc_attr( $product_id ) . '" data-nonce="' . esc_attr( $nonce ) . '" data-categories="' . esc_attr( wp_json_encode( $cat_data ) ) . '">';
			echo '<div class="ecv2-category-display" onclick="ecv2_open_category_editor( this );">';
			if ( ! empty( $result->category_names ) ) {
				$categories = explode( ', ', $result->category_names );
				$first = $categories[0];
				echo '<span class="ecv2-category-tag">' . esc_html( $first ) . '</span>';
				if ( count( $categories ) > 1 ) {
					echo '<span class="ecv2-category-more" title="' . esc_attr( $result->category_names ) . '">+' . esc_html( count( $categories ) - 1 ) . '</span>';
				}
			} else {
				echo '<span class="ecv2-sku-empty">&mdash;</span>';
			}
			echo '<span class="ecv2-category-edit-icon"><span class="dashicons dashicons-edit-large"></span></span>';
			echo '</div>';
			echo '</div>';
		}

		private function print_stock_badge( $result ) {
			$product_id = (int) $result->product_id;
			$is_square = self::is_square_locked( $result );
			$variant_gate = ecv2_get_variant_tracking_gate();
			$variant_tracking_enabled = ( 'enabled' === $variant_gate['state'] );

			// Square-locked: read-only stock display — no dropdown, no tracking changes.
			if ( $is_square ) {
				echo '<div class="ecv2-stock-wrap ecv2-stock-wrap-locked" title="' . esc_attr__( 'Stock managed by Square', 'wp-easycart' ) . '">';
				if ( $result->use_optionitem_quantity_tracking ) {
					global $wpdb;
					$option_total = (int) $wpdb->get_var( $wpdb->prepare(
						"SELECT COALESCE( SUM( quantity ), 0 ) FROM ec_optionitemquantity WHERE product_id = %d AND is_enabled = 1 AND is_stock_tracking_enabled = 1",
						$product_id
					) );
					echo '<span class="ecv2-stock-badge ecv2-stock-option">' . esc_html( $option_total ) . ' ' . esc_html__( 'in stock', 'wp-easycart' ) . '</span>';
				} else if ( ! $result->show_stock_quantity ) {
					echo '<span class="ecv2-stock-badge ecv2-stock-unlimited">&infin; ' . esc_html__( 'Unlimited', 'wp-easycart' ) . '</span>';
				} else if ( $result->stock_quantity <= 0 ) {
					echo '<span class="ecv2-stock-badge ecv2-stock-out">' . esc_html__( 'Out of Stock', 'wp-easycart' ) . '</span>';
				} else {
					echo '<span class="ecv2-stock-badge ecv2-stock-ok">' . esc_html( $result->stock_quantity ) . ' ' . esc_html__( 'in stock', 'wp-easycart' ) . '</span>';
				}
				echo ' <span class="dashicons dashicons-lock ecv2-cell-lock-icon"></span>';
				echo '</div>';
				return;
			}

			$nonce = wp_create_nonce( 'wp-easycart-ecv2-stock-action-' . $product_id );

			// Determine current tracking type for data attribute.
			$tracking_type = 'unlimited';
			if ( $result->use_optionitem_quantity_tracking ) {
				$tracking_type = 'option';
			} else if ( $result->show_stock_quantity ) {
				$tracking_type = 'basic';
			}

			// For option tracking, compute total variant stock.
			$option_total = 0;
			if ( $tracking_type === 'option' ) {
				global $wpdb;
				$option_total = (int) $wpdb->get_var( $wpdb->prepare(
					"SELECT COALESCE( SUM( quantity ), 0 ) FROM ec_optionitemquantity WHERE product_id = %d AND is_enabled = 1 AND is_stock_tracking_enabled = 1",
					$product_id
				) );
			}

			echo '<div class="ecv2-stock-wrap" data-product-id="' . esc_attr( $product_id ) . '" data-nonce="' . esc_attr( $nonce ) . '" data-tracking="' . esc_attr( $tracking_type ) . '" data-stock-qty="' . esc_attr( (int) $result->stock_quantity ) . '" data-option-total="' . esc_attr( $option_total ) . '">';

			echo '<button type="button" class="ecv2-stock-badge-btn" onclick="ecv2_open_stock_menu( this );">';
			if ( $result->use_optionitem_quantity_tracking ) {
				echo '<span class="ecv2-stock-badge ecv2-stock-option"><span class="dashicons dashicons-admin-settings"></span> ' . esc_html( $option_total ) . ' ' . esc_html__( 'in stock', 'wp-easycart' ) . '</span>';
			} else if ( ! $result->show_stock_quantity ) {
				echo '<span class="ecv2-stock-badge ecv2-stock-unlimited">&infin; ' . esc_html__( 'Unlimited', 'wp-easycart' ) . '</span>';
			} else if ( $result->stock_quantity <= 0 ) {
				echo '<span class="ecv2-stock-badge ecv2-stock-out">' . esc_html__( 'Out of Stock', 'wp-easycart' ) . '</span>';
			} else if ( $result->stock_quantity <= self::LOW_STOCK_THRESHOLD ) {
				echo '<span class="ecv2-stock-badge ecv2-stock-low">' . esc_html( $result->stock_quantity ) . ' ' . esc_html__( 'left', 'wp-easycart' ) . '</span>';
			} else {
				echo '<span class="ecv2-stock-badge ecv2-stock-ok">' . esc_html( $result->stock_quantity ) . ' ' . esc_html__( 'in stock', 'wp-easycart' ) . '</span>';
			}
			echo '</button>';

			// Stock action dropdown menu.
			echo '<div class="ecv2-stock-menu">';

			// Inline edit for basic tracking.
			if ( $tracking_type === 'basic' ) {
				echo '<div class="ecv2-stock-menu-section">';
				echo '<label class="ecv2-stock-menu-label">' . esc_html__( 'Stock Quantity', 'wp-easycart' ) . '</label>';
				echo '<div class="ecv2-stock-menu-inline">';
				echo '<input type="number" class="ecv2-stock-menu-input" value="' . esc_attr( (int) $result->stock_quantity ) . '" step="1" data-original="' . esc_attr( (int) $result->stock_quantity ) . '" />';
				echo '<button type="button" class="ecv2-btn ecv2-btn-primary ecv2-btn-sm" onclick="ecv2_save_stock_qty( this );">' . esc_html__( 'Save', 'wp-easycart' ) . '</button>';
				echo '</div>';
				echo '</div>';
				echo '<div class="ecv2-stock-menu-divider"></div>';
			}

			// Manage variants link for option tracking.
			if ( $tracking_type === 'option' ) {
				if ( $variant_tracking_enabled ) {
					echo '<a href="#" class="ecv2-stock-menu-item" onclick="ecv2_open_variant_popup( ' . esc_attr( $product_id ) . ' ); return false;">';
					echo '<span class="dashicons dashicons-list-view"></span> ' . esc_html__( 'Manage Variants', 'wp-easycart' );
					echo '</a>';
					echo '<div class="ecv2-stock-menu-divider"></div>';
				} else {
					$locked_onclick = "jQuery( this ).closest( '.ecv2-stock-menu' ).removeClass( 'ecv2-stock-menu-open ecv2-menu-fixed' ); return wpec_gate.locked_action( ecv2_lang.variant_gate );";
					echo '<a href="#" class="ecv2-stock-menu-item ecv2-stock-menu-item-locked" onclick="' . esc_attr( $locked_onclick ) . '">';
					echo '<span class="dashicons dashicons-list-view"></span> ' . esc_html__( 'Manage Variants', 'wp-easycart' );
					echo ' <span class="dashicons dashicons-lock ecv2-menu-lock-icon"></span>';
					echo '</a>';
				}
			}

			// Switch tracking type section.
			echo '<div class="ecv2-stock-menu-label ecv2-stock-menu-label-section">' . esc_html__( 'Change Tracking Type', 'wp-easycart' ) . '</div>';

			$types = array(
				'unlimited' => array( 'icon' => 'marker', 'label' => __( 'Unlimited', 'wp-easycart' ), 'desc' => __( 'Do not track stock', 'wp-easycart' ), 'pro' => false ),
				'basic'     => array( 'icon' => 'chart-bar', 'label' => __( 'Basic Tracking', 'wp-easycart' ), 'desc' => __( 'Track overall quantity', 'wp-easycart' ), 'pro' => false ),
				'option'    => array( 'icon' => 'admin-settings', 'label' => __( 'Option/Variant Tracking', 'wp-easycart' ), 'desc' => __( 'Track per variation', 'wp-easycart' ), 'pro' => true ),
			);
			foreach ( $types as $type_key => $type_info ) {
				$is_current     = ( $type_key === $tracking_type );
				$is_locked_type = ( $type_info['pro'] && ! $variant_tracking_enabled );
				$classes = 'ecv2-stock-menu-type';
				if ( $is_current ) {
					$classes .= ' ecv2-stock-menu-type-active';
				}
				if ( $is_locked_type ) {
					$classes .= ' ecv2-stock-menu-type-locked';
				}
				if ( $is_locked_type ) {
					$onclick = "jQuery( this ).closest( '.ecv2-stock-menu' ).removeClass( 'ecv2-stock-menu-open ecv2-menu-fixed' ); return wpec_gate.locked_action( ecv2_lang.variant_gate );";
				} else {
					$onclick = 'ecv2_switch_tracking_type( this, \'' . esc_attr( $type_key ) . '\' ); return false;';
				}
				echo '<a href="#" class="' . esc_attr( $classes ) . '" data-type="' . esc_attr( $type_key ) . '" onclick="' . esc_attr( $onclick ) . '">';
				echo '<span class="ecv2-stock-menu-type-radio">' . ( $is_current ? '<span class="ecv2-stock-menu-type-dot"></span>' : '' ) . '</span>';
				echo '<span class="ecv2-stock-menu-type-info">';
				echo '<span class="ecv2-stock-menu-type-name">';
				echo esc_html( $type_info['label'] );
				if ( $is_locked_type ) {
					echo ' <span class="dashicons dashicons-lock ecv2-menu-lock-icon"></span>';
				}
				echo '</span>';
				if ( $is_locked_type && ! $is_current ) {
					echo '<span class="ecv2-stock-menu-type-desc">' . esc_html( $variant_gate['desc'] ) . '</span>';
				} else {
					echo '<span class="ecv2-stock-menu-type-desc">' . esc_html( $type_info['desc'] ) . '</span>';
				}
				echo '</span>';
				echo '</a>';
			}
			echo '</div>'; // .ecv2-stock-menu

			echo '</div>'; // .ecv2-stock-wrap
		}

		private function print_status_toggle( $result ) {
			$checked = (bool) $result->is_visible;
			$nonce = wp_create_nonce( 'wp-easycart-ecv2-toggle-' . $result->product_id );
			echo '<label class="ecv2-toggle">';
			echo '<input type="checkbox"' . checked( $checked, true, false ) . ' onchange="ecv2_toggle_product_status( ' . esc_attr( $result->product_id ) . ', this.checked, \'' . esc_attr( $nonce ) . '\' );" />';
			echo '<span class="ecv2-toggle-slider"></span>';
			echo '</label>';
		}

		/**
		 * Small toggle for spreadsheet view.
		 */
		private function print_status_toggle_sm( $result ) {
			$checked = (bool) $result->is_visible;
			$nonce = wp_create_nonce( 'wp-easycart-ecv2-toggle-' . $result->product_id );
			echo '<label class="ecv2-toggle ecv2-toggle-sm">';
			echo '<input type="checkbox"' . checked( $checked, true, false ) . ' onchange="ecv2_toggle_product_status( ' . esc_attr( $result->product_id ) . ', this.checked, \'' . esc_attr( $nonce ) . '\' );" />';
			echo '<span class="ecv2-toggle-slider"></span>';
			echo '</label>';
		}

		/**
		 * Compact price cell for spreadsheet view — single column with click-to-edit popup.
		 */
		private function print_product_price_compact( $result ) {
			$product_id = (int) $result->product_id;
			$nonce = wp_create_nonce( 'wp-easycart-ecv2-price-edit-' . $product_id );
			$has_variant_pricing = false;
			$variant_price_min = 0;
			$variant_price_max = 0;

			if ( $result->use_optionitem_quantity_tracking ) {
				global $wpdb;
				$variant_prices = $wpdb->get_row( $wpdb->prepare(
					"SELECT MIN( CASE WHEN price >= 0 THEN price END ) AS min_price,
							MAX( CASE WHEN price >= 0 THEN price END ) AS max_price,
							SUM( CASE WHEN price >= 0 THEN 1 ELSE 0 END ) AS price_count
					 FROM ec_optionitemquantity
					 WHERE product_id = %d AND is_enabled = 1 AND price != -1",
					$product_id
				) );
				if ( $variant_prices && (int) $variant_prices->price_count > 0 ) {
					$has_variant_pricing = true;
					$variant_price_min = (float) $variant_prices->min_price;
					$variant_price_max = (float) $variant_prices->max_price;
				}
			}

			echo '<div class="ecv2-price-cell ecv2-price-cell-compact" data-product-id="' . esc_attr( $product_id ) . '" data-nonce="' . esc_attr( $nonce ) . '" data-price="' . esc_attr( (float) $result->price ) . '" data-list-price="' . esc_attr( (float) $result->list_price ) . '">';

			if ( $has_variant_pricing ) {
				echo '<button type="button" class="ecv2-price-badge-btn ecv2-price-badge-compact" onclick="ecv2_open_variant_popup( ' . esc_attr( $product_id ) . ' ); return false;">';
				if ( $variant_price_min === $variant_price_max ) {
					echo '<span class="ecv2-price-current">' . esc_html( $GLOBALS['currency']->get_currency_display( $variant_price_min ) ) . '</span>';
				} else {
					echo '<span class="ecv2-price-current">' . esc_html( $GLOBALS['currency']->get_currency_display( $variant_price_min ) ) . '&ndash;' . esc_html( $GLOBALS['currency']->get_currency_display( $variant_price_max ) ) . '</span>';
				}
				echo ' <span class="dashicons dashicons-admin-settings" style="font-size:12px;width:12px;height:12px;color:var(--ecv2-g400);vertical-align:middle;"></span>';
				echo '</button>';
			} else {
				echo '<button type="button" class="ecv2-price-badge-btn ecv2-price-badge-compact" onclick="ecv2_open_price_editor( this );">';
				echo '<span class="ecv2-price-current">' . esc_html( $GLOBALS['currency']->get_currency_display( $result->price ) ) . '</span>';
				if ( isset( $result->list_price ) && $result->list_price > 0 && $result->list_price != $result->price && $result->list_price > $result->price ) {
					echo ' <span class="ecv2-price-list">' . esc_html( $GLOBALS['currency']->get_currency_display( $result->list_price ) ) . '</span>';
				}
				echo '</button>';

				// Price editor dropdown (same as main table).
				echo '<div class="ecv2-price-menu">';
				echo '<div class="ecv2-price-menu-section">';
				echo '<label class="ecv2-price-menu-label">' . esc_html__( 'Price', 'wp-easycart' ) . '</label>';
				echo '<input type="text" class="ecv2-price-menu-input" data-field="price" value="' . esc_attr( number_format( (float) $result->price, 2, '.', '' ) ) . '" data-original="' . esc_attr( number_format( (float) $result->price, 2, '.', '' ) ) . '" />';
				echo '</div>';
				echo '<div class="ecv2-price-menu-section">';
				echo '<label class="ecv2-price-menu-label">' . esc_html__( 'List Price', 'wp-easycart' ) . ' <span class="ecv2-price-menu-hint">' . esc_html__( '(original / compare-at)', 'wp-easycart' ) . '</span></label>';
				echo '<input type="text" class="ecv2-price-menu-input" data-field="list_price" value="' . esc_attr( ( (float) $result->list_price > 0 ) ? number_format( (float) $result->list_price, 2, '.', '' ) : '' ) . '" data-original="' . esc_attr( ( (float) $result->list_price > 0 ) ? number_format( (float) $result->list_price, 2, '.', '' ) : '' ) . '" placeholder="' . esc_attr__( 'None', 'wp-easycart' ) . '" />';
				echo '</div>';
				echo '<div id="ecv2-price-menu-preview-' . esc_attr( $product_id ) . '" class="ecv2-price-menu-preview" style="display:none;"></div>';
				echo '<div class="ecv2-price-menu-actions">';
				echo '<button type="button" class="ecv2-btn ecv2-btn-primary ecv2-btn-sm" onclick="ecv2_save_price_editor( this );">' . esc_html__( 'Save', 'wp-easycart' ) . '</button>';
				echo '</div>';
				echo '</div>'; // .ecv2-price-menu
			}

			echo '</div>'; // .ecv2-price-cell
		}

		private function print_completeness_ring( $result ) {
			$score = $this->calculate_completeness( $result );
			$pct = round( $score['percent'] );
			$color = $pct >= 85 ? '#22c55e' : ( $pct >= 50 ? '#f59e0b' : '#ef4444' );
			$status_label = $pct >= 85 ? __( 'Complete', 'wp-easycart' ) : ( $pct >= 50 ? __( 'Needs work', 'wp-easycart' ) : __( 'Incomplete', 'wp-easycart' ) );

			// Build tooltip data.
			if ( ! empty( $score['is_square'] ) ) {
				$tooltip_text = esc_attr( __( 'Managed by Square — edit this product from your Square dashboard.', 'wp-easycart' ) );
			} else {
				$tooltip_items = array();
				foreach ( self::SCORE_FIELDS as $field_key => $field_info ) {
					$passed = isset( $score['fields'][ $field_key ] ) ? $score['fields'][ $field_key ] : false;
					$tooltip_items[] = ( $passed ? '✓' : '✗' ) . ' ' . $field_info['label'];
				}
				$tooltip_text = esc_attr( $pct . '% —” ' . $status_label . "\n" . implode( "\n", $tooltip_items ) );
			}

			// SVG ring.
			$radius = 14;
			$circumference = 2 * 3.14159 * $radius;
			$dash_offset = $circumference * ( 1 - $pct / 100 );

			echo '<div class="ecv2-score-ring" title="' . $tooltip_text . '">';
			echo '<svg width="36" height="36" viewBox="0 0 36 36">';
			echo '<circle cx="18" cy="18" r="' . $radius . '" fill="none" stroke="#e5e7eb" stroke-width="3" />';
			echo '<circle cx="18" cy="18" r="' . $radius . '" fill="none" stroke="' . esc_attr( $color ) . '" stroke-width="3" stroke-dasharray="' . $circumference . '" stroke-dashoffset="' . $dash_offset . '" stroke-linecap="round" transform="rotate(-90 18 18)" />';
			echo '<text x="18" y="18" text-anchor="middle" dominant-baseline="central" class="ecv2-score-text" fill="' . esc_attr( $color ) . '">' . esc_html( $pct ) . '</text>';
			echo '</svg>';
			echo '</div>';
		}

		private function calculate_completeness( $result ) {
			$total = count( self::SCORE_FIELDS );
			$field_results = array();

			if ( self::is_square_locked( $result ) ) {
				foreach ( self::SCORE_FIELDS as $field_key => $field_info ) {
					$field_results[ $field_key ] = true;
				}
				return array(
					'percent'   => 100,
					'passed'    => $total,
					'total'     => $total,
					'fields'    => $field_results,
					'is_square' => true,
				);
			}

			$passed = 0;

			foreach ( self::SCORE_FIELDS as $field_key => $field_info ) {
				$value = isset( $result->{ $field_key } ) ? $result->{ $field_key } : '';
				$check_passed = false;
				switch ( $field_info['check'] ) {
					case 'not_empty':
						$check_passed = ( $value !== '' && $value !== null );
						break;
					case 'greater_zero':
						$check_passed = ( (float) $value > 0 );
						break;
				}
				if ( $check_passed ) {
					$passed++;
				}
				$field_results[ $field_key ] = $check_passed;
			}

			return array(
				'percent' => $total > 0 ? ( $passed / $total ) * 100 : 0,
				'passed'  => $passed,
				'total'   => $total,
				'fields'  => $field_results,
				'is_square' => false,
			);
		}

		protected function print_card( $result ) {
			$image_url = self::resolve_thumbnail_url( $result );
			$edit_url = $this->get_url( $this->key, $result->product_id, false, 'ec_admin_form_action', 'edit' );
			$checked = (bool) $result->is_visible;
			$nonce = wp_create_nonce( 'wp-easycart-ecv2-toggle-' . $result->product_id );
			$is_square = self::is_square_locked( $result );
			$square_class = $is_square ? ' ecv2-card-square-locked' : '';
			$square_attr = $is_square ? ' data-square-synced="1"' : '';

			echo '<div class="ecv2-card' . ( ! $checked ? ' ecv2-card-inactive' : '' ) . $square_class . '" data-id="' . esc_attr( $result->product_id ) . '"' . $square_attr . '>';

			// Image area.
			echo '<div class="ecv2-card-image" data-product-id="' . esc_attr( $result->product_id ) . '">';
			if ( $is_square ) {
				echo '<div class="ecv2-card-image-click" title="' . esc_attr__( 'Images managed by Square', 'wp-easycart' ) . '">';
				if ( $image_url ) {
					echo '<img src="' . esc_url( $image_url ) . '" alt="" loading="lazy" onerror="ecv2_image_error(this);" data-ecv2-placeholder-class="ecv2-card-image-placeholder" />';
				} else {
					echo '<div class="ecv2-card-image-placeholder"><span class="dashicons dashicons-format-image"></span></div>';
				}
				echo '</div>';
			} else {
				$image_nonce = wp_create_nonce( 'wp-easycart-ecv2-image-manager-' . (int) $result->product_id );
				echo '<div class="ecv2-card-image-click" data-product-id="' . esc_attr( (int) $result->product_id ) . '" data-image-nonce="' . esc_attr( $image_nonce ) . '">';
				if ( $image_url ) {
					echo '<img src="' . esc_url( $image_url ) . '" alt="" loading="lazy" onerror="ecv2_image_error(this);" data-ecv2-placeholder-class="ecv2-card-image-placeholder" />';
				} else {
					echo '<div class="ecv2-card-image-placeholder"><span class="dashicons dashicons-format-image"></span></div>';
				}
				do_action( 'wp_easycart_admin_ecv2_product_image_edit_trigger', (int) $result->product_id, 'card' );
				echo '</div>';
			}

			// Status overlay — always editable.
			echo '<label class="ecv2-toggle ecv2-card-toggle">';
			echo '<input type="checkbox"' . checked( $checked, true, false ) . ' onchange="ecv2_toggle_product_status( ' . esc_attr( $result->product_id ) . ', this.checked, \'' . esc_attr( $nonce ) . '\' );" />';
			echo '<span class="ecv2-toggle-slider"></span>';
			echo '</label>';
			echo '</div>';

			// Body.
			echo '<div class="ecv2-card-body">';

			// Title line with Square badge.
			echo '<h3 class="ecv2-card-title">';
			echo '<a href="' . esc_url( $edit_url ) . '">' . esc_html( strip_tags( wp_unslash( $result->title ) ) ) . '</a>';
			if ( $is_square ) {
				echo ' <span class="ecv2-square-title-badge">';
				echo '<img src="' . esc_url( plugins_url( 'wp-easycart/admin/images/square-logo.png' ) ) . '" alt="" class="ecv2-square-title-logo" />';
				echo '<span class="dashicons dashicons-lock ecv2-square-title-lock"></span>';
				echo '</span>';
			}
			echo '</h3>';

			// Square lock banner on card.
			if ( $is_square ) {
				echo '<div class="ecv2-square-lock-banner">';
				echo '<span class="dashicons dashicons-lock"></span> ';
				echo esc_html__( 'Synced with Square — edit in Square dashboard', 'wp-easycart' );
				echo '</div>';
			}

			echo '<div class="ecv2-card-sku-wrap" data-product-id="' . esc_attr( $result->product_id ) . '" data-field="model_number">';
			if ( ! empty( $result->model_number ) ) {
				$this->print_sku_chip( $result );
			} else {
				echo '<span class="ecv2-sku-empty">&mdash;</span>';
			}
			echo '</div>';
			echo '<div class="ecv2-card-meta">';
			$this->print_product_price( $result );
			echo '</div>';
			$this->print_stock_badge( $result );

			// Completeness bar.
			$score = $this->calculate_completeness( $result );
			$pct = round( $score['percent'] );
			if ( $pct < 100 ) {
				$color = $pct >= 85 ? '#22c55e' : ( $pct >= 50 ? '#f59e0b' : '#ef4444' );
				$missing = array();
				foreach ( self::SCORE_FIELDS as $field_key => $field_info ) {
					if ( ! $score['fields'][ $field_key ] ) {
						$missing[] = $field_info['label'];
					}
				}
				echo '<div class="ecv2-card-completeness">';
				echo '<div class="ecv2-card-score-bar"><div style="width:' . esc_attr( $pct ) . '%;background:' . esc_attr( $color ) . ';"></div></div>';
				echo '<span class="ecv2-card-score-text">' . esc_html( $pct . '% —” ' . __( 'missing', 'wp-easycart' ) . ': ' . implode( ', ', $missing ) ) . '</span>';
				echo '</div>';
			}

			echo '</div>';

			// Footer.
			echo '<div class="ecv2-card-footer">';
			echo '<input type="checkbox" name="bulk[]" value="' . esc_attr( $result->product_id ) . '" class="ecv2-row-check" />';
			echo '<div class="ecv2-row-menu-wrap">';
			echo '<button type="button" class="ecv2-row-menu-trigger" onclick="ecv2_toggle_row_menu(this);">&#8943;</button>';
			echo '<div class="ecv2-row-menu">';
			foreach ( $this->row_menu_actions as $action ) {
				$this->print_row_menu_item( $result, $action );
			}
			echo '</div></div>';
			echo '</div>';

			echo '</div>'; // .ecv2-card
		}
		
		protected function print_row_menu_item( $result, $action ) {
			if ( isset( $action['name'] ) && 'quick-edit' === $action['name'] && self::is_square_locked( $result ) ) {
				$action['disabled']       = true;
				$action['disabled_title'] = __( 'Quick edit disabled — this product is synced with Square', 'wp-easycart' );
			}
			if ( isset( $action['name'] ) && 'view-on-site' === $action['name'] ) {
				$view_url = $this->get_view_url( $result->product_id );
				if ( ! empty( $view_url ) ) {
					$action['href']   = $view_url;
					$action['target'] = '_blank';
				} else {
					$action['disabled']       = true;
					$action['disabled_title'] = __( 'No public URL for this product yet.', 'wp-easycart' );
				}
			}
			parent::print_row_menu_item( $result, $action );
		}

		protected function print_custom_modals() {
			do_action( 'wp_easycart_admin_ecv2_render_variant_manager' );
			do_action( 'wp_easycart_admin_ecv2_render_image_manager' );
			do_action( 'wp_easycart_admin_ecv2_render_volume_pricing_manager' );
			do_action( 'wp_easycart_admin_ecv2_render_b2b_pricing_manager' );
			do_action( 'wp_easycart_admin_ecv2_render_advanced_pricing_slideout' );
		}

		private function print_image_manager_modal() {
			echo '<div class="ecv2-modal-overlay" id="ecv2-image-manager-modal" style="display:none;">';
			echo '<div class="ecv2-modal ecv2-modal-image-manager">';
			echo '<div class="ecv2-modal-header">';
			echo '<h2><span class="dashicons dashicons-format-gallery"></span> <span id="ecv2-imgmgr-title">' . esc_html__( 'Manage Images', 'wp-easycart' ) . '</span></h2>';
			echo '<button type="button" class="ecv2-modal-close" onclick="ecv2_close_image_manager();">&times;</button>';
			echo '</div>';
			echo '<div class="ecv2-modal-body">';

			echo '<input type="hidden" id="ecv2-imgmgr-product-id" value="" />';
			echo '<input type="hidden" id="ecv2-imgmgr-nonce" value="" />';

			// Loading indicator.
			echo '<div class="ecv2-imgmgr-loading" id="ecv2-imgmgr-loading"><span class="dashicons dashicons-update ecv2-spin"></span> ' . esc_html__( 'Loading images...', 'wp-easycart' ) . '</div>';

			// Image type switcher (Basic / Option Set / Modifier).
			echo '<div class="ecv2-imgmgr-type-switcher" id="ecv2-imgmgr-type-switcher" style="display:none;">';
			echo '<label class="ecv2-modal-label">' . esc_html__( 'Image Type', 'wp-easycart' ) . '</label>';
			echo '<div class="ecv2-imgmgr-type-pills">';
			echo '<button type="button" class="ecv2-imgmgr-type-pill active" data-type="basic" onclick="ecv2_imgmgr_switch_type(\'basic\');"><span class="dashicons dashicons-format-image"></span> ' . esc_html__( 'Basic', 'wp-easycart' ) . '</button>';
			echo '<button type="button" class="ecv2-imgmgr-type-pill" data-type="variant" onclick="ecv2_imgmgr_switch_type(\'variant\');"><span class="dashicons dashicons-networking"></span> ' . esc_html__( 'Option Set', 'wp-easycart' ) . '</button>';
			echo '<button type="button" class="ecv2-imgmgr-type-pill" data-type="modifier" onclick="ecv2_imgmgr_switch_type(\'modifier\');"><span class="dashicons dashicons-admin-settings"></span> ' . esc_html__( 'Modifier', 'wp-easycart' ) . '</button>';
			echo '</div>';
			echo '<p class="ecv2-imgmgr-type-desc" id="ecv2-imgmgr-type-desc"></p>';
			echo '</div>';

			// Image set selector (shows when optionitem images are active).
			echo '<div class="ecv2-imgmgr-set-selector" id="ecv2-imgmgr-set-selector" style="display:none;">';
			echo '<label class="ecv2-modal-label">' . esc_html__( 'Image Set', 'wp-easycart' ) . '</label>';
			echo '<select id="ecv2-imgmgr-set-select" onchange="ecv2_imgmgr_change_set();" class="ecv2-input"></select>';
			echo '</div>';

			// Gallery area.
			echo '<div class="ecv2-imgmgr-gallery-wrap" id="ecv2-imgmgr-gallery-wrap" style="display:none;">';
			echo '<div class="ecv2-imgmgr-gallery" id="ecv2-imgmgr-gallery"></div>';

			// Add media buttons.
			echo '<div class="ecv2-imgmgr-add-bar">';
			echo '<div class="ecv2-imgmgr-add-buttons">';
			echo '<button type="button" class="ecv2-btn ecv2-btn-ghost ecv2-btn-sm ecv2-imgmgr-add-btn" data-add-type="media" onclick="ecv2_imgmgr_add_media_library();"><span class="dashicons dashicons-admin-media"></span> ' . esc_html__( 'Media Library', 'wp-easycart' ) . '<span class="ecv2-imgmgr-add-lock dashicons dashicons-lock" aria-hidden="true"></span></button>';
			echo '<button type="button" class="ecv2-btn ecv2-btn-ghost ecv2-btn-sm ecv2-imgmgr-add-btn" data-add-type="image" onclick="ecv2_imgmgr_toggle_url_panel(\'image\');"><span class="dashicons dashicons-format-image"></span> ' . esc_html__( 'Image URL', 'wp-easycart' ) . '<span class="ecv2-imgmgr-add-lock dashicons dashicons-lock" aria-hidden="true"></span></button>';
			echo '<button type="button" class="ecv2-btn ecv2-btn-ghost ecv2-btn-sm ecv2-imgmgr-add-btn" data-add-type="video" data-requires-pro="1" onclick="ecv2_imgmgr_toggle_url_panel(\'video\');"><span class="dashicons dashicons-video-alt3"></span> ' . esc_html__( 'Video URL', 'wp-easycart' ) . '<span class="ecv2-imgmgr-add-lock dashicons dashicons-lock" aria-hidden="true"></span></button>';
			echo '<button type="button" class="ecv2-btn ecv2-btn-ghost ecv2-btn-sm ecv2-imgmgr-add-btn" data-add-type="youtube" data-requires-pro="1" onclick="ecv2_imgmgr_toggle_url_panel(\'youtube\');"><span class="dashicons dashicons-youtube"></span> ' . esc_html__( 'YouTube', 'wp-easycart' ) . '<span class="ecv2-imgmgr-add-lock dashicons dashicons-lock" aria-hidden="true"></span></button>';
			echo '<button type="button" class="ecv2-btn ecv2-btn-ghost ecv2-btn-sm ecv2-imgmgr-add-btn" data-add-type="vimeo" data-requires-pro="1" onclick="ecv2_imgmgr_toggle_url_panel(\'vimeo\');"><span class="ecv2-icon ecv2-icon-vimeo" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" focusable="false"><path d="M23.977 6.416c-.105 2.338-1.739 5.543-4.894 9.609-3.268 4.247-6.026 6.37-8.29 6.37-1.409 0-2.578-1.294-3.553-3.881L5.322 11.4C4.603 8.816 3.834 7.522 3.01 7.522c-.179 0-.806.378-1.881 1.132L0 7.197c1.185-1.044 2.351-2.084 3.501-3.128C5.08 2.701 6.266 1.984 7.055 1.91c1.867-.18 3.016 1.1 3.447 3.838.465 2.953.789 4.789.971 5.507.539 2.45 1.131 3.674 1.776 3.674.502 0 1.256-.796 2.265-2.385 1.004-1.589 1.54-2.797 1.612-3.628.144-1.371-.395-2.061-1.614-2.061-.574 0-1.167.121-1.777.391 1.186-3.868 3.434-5.757 6.762-5.637 2.473.07 3.628 1.669 3.48 4.797z"/></svg></span> ' . esc_html__( 'Vimeo', 'wp-easycart' ) . '<span class="ecv2-imgmgr-add-lock dashicons dashicons-lock" aria-hidden="true"></span></button>';
			echo '</div>';
			echo '</div>';

			// URL input panels (toggled).
			echo '<div class="ecv2-imgmgr-url-panel" id="ecv2-imgmgr-url-panel" style="display:none;">';
			echo '<div class="ecv2-imgmgr-url-panel-inner">';
			echo '<label class="ecv2-modal-label" id="ecv2-imgmgr-url-label">' . esc_html__( 'Image URL', 'wp-easycart' ) . '</label>';
			echo '<input type="text" class="ecv2-input" id="ecv2-imgmgr-url-input" placeholder="https://" />';
			// Thumbnail row (for video/youtube/vimeo).
			echo '<div class="ecv2-imgmgr-thumb-row" id="ecv2-imgmgr-thumb-row" style="display:none;">';
			echo '<label class="ecv2-modal-label">' . esc_html__( 'Thumbnail URL', 'wp-easycart' ) . '</label>';
			echo '<div class="ecv2-imgmgr-thumb-input-wrap">';
			echo '<input type="text" class="ecv2-input" id="ecv2-imgmgr-thumb-input" placeholder="https://yoursite.com/thumb.jpg" />';
			echo '<button type="button" class="ecv2-btn ecv2-btn-ghost ecv2-btn-sm" onclick="ecv2_imgmgr_thumb_media_library();">' . esc_html__( 'Media Library', 'wp-easycart' ) . '</button>';
			echo '</div>';
			echo '</div>';
			echo '<div class="ecv2-imgmgr-url-actions">';
			echo '<button type="button" class="ecv2-btn ecv2-btn-primary ecv2-btn-sm" onclick="ecv2_imgmgr_add_url();">' . esc_html__( 'Add', 'wp-easycart' ) . '</button>';
			echo '<button type="button" class="ecv2-btn ecv2-btn-ghost ecv2-btn-sm" onclick="ecv2_imgmgr_close_url_panel();">' . esc_html__( 'Cancel', 'wp-easycart' ) . '</button>';
			echo '</div>';
			echo '</div>';
			echo '</div>';

			// Empty state.
			echo '<div class="ecv2-imgmgr-empty" id="ecv2-imgmgr-empty" style="display:none;">';
			echo '<span class="dashicons dashicons-format-image"></span>';
			echo '<p>' . esc_html__( 'No images yet. Add images using the buttons below.', 'wp-easycart' ) . '</p>';
			echo '</div>';

			echo '</div>'; // .ecv2-imgmgr-gallery-wrap

			echo '</div>'; // .ecv2-modal-body
			echo '<div class="ecv2-modal-footer">';
			echo '<span class="ecv2-imgmgr-count" id="ecv2-imgmgr-count"></span>';
			echo '<div class="ecv2-modal-footer-right">';
			echo '<button type="button" class="ecv2-btn ecv2-btn-ghost" onclick="ecv2_close_image_manager();">' . esc_html__( 'Cancel', 'wp-easycart' ) . '</button>';
			echo '<button type="button" class="ecv2-btn ecv2-btn-primary" id="ecv2-imgmgr-save-btn" onclick="ecv2_imgmgr_save();">' . esc_html__( 'Save Images', 'wp-easycart' ) . '</button>';
			echo '</div>';
			echo '</div>';
			echo '</div>'; // .ecv2-modal
			echo '</div>'; // .ecv2-modal-overlay
		}
	}

endif;

/**
 * Server-side check: is this product locked from editing by Square sync?
 *
 * Uses the same three-part condition as the original admin list:
 * 1. Payment method is Square
 * 2. Auto product sync OR auto inventory sync is enabled
 * 3. The product has a square_id
 *
 * @param int    $product_id Product ID.
 * @param string $field      Optional field name. 'activate_in_store' and 'categories' are always allowed.
 * @return bool True if the edit should be blocked.
 */
function ecv2_is_square_locked( $product_id, $field = '' ) {
	$always_editable = array( 'activate_in_store', 'categories' );
	if ( $field && in_array( $field, $always_editable, true ) ) {
		return false;
	}
	if ( 'square' !== get_option( 'ec_option_payment_process_method' ) ) {
		return false;
	}
	if ( ! get_option( 'ec_option_square_auto_product_sync' ) && ! get_option( 'ec_option_square_auto_sync' ) ) {
		return false;
	}
	global $wpdb;
	$square_id = $wpdb->get_var( $wpdb->prepare( "SELECT square_id FROM ec_product WHERE product_id = %d", $product_id ) );
	return ( null !== $square_id && '' !== $square_id );
}

add_action( 'wp_ajax_ecv2_product_inline_update', 'ecv2_product_inline_update' );
function ecv2_product_inline_update() {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_easycart_nonce'] ) ), 'wp-easycart-ecv2-inline-update' ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wp-easycart' ) ) );
	}

	$product_id = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
	$field = isset( $_POST['field'] ) ? sanitize_key( $_POST['field'] ) : '';
	$value = isset( $_POST['value'] ) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '';

	if ( ! $product_id || ! $field ) {
		wp_send_json_error( array( 'message' => __( 'Invalid request.', 'wp-easycart' ) ) );
	}

	if ( ecv2_is_square_locked( $product_id, $field ) ) {
		wp_send_json_error( array( 'message' => __( 'This product is synced with Square. Please make changes in your Square dashboard.', 'wp-easycart' ) ) );
	}

	$allowed_fields = array( 'title', 'price', 'list_price', 'stock_quantity', 'model_number' );
	if ( ! in_array( $field, $allowed_fields, true ) ) {
		wp_send_json_error( array( 'message' => __( 'Field not allowed.', 'wp-easycart' ) ) );
	}

	global $wpdb;
	$old_value = $wpdb->get_var( $wpdb->prepare( "SELECT `$field` FROM ec_product WHERE product_id = %d", $product_id ) );

	$format = '%s';
	if ( $field === 'stock_quantity' ) {
		$value = (int) $value;
		$format = '%d';
	} else if ( in_array( $field, array( 'price', 'list_price' ), true ) ) {
		$value = wp_easycart_admin_verification()->filter_float( $value );
	} else {
		$value = wp_easycart_escape_html( $value );
	}

	// Field-specific validation for model_number: format + uniqueness.
	if ( $field === 'model_number' && $value !== '' ) {
		if ( ! preg_match( '/^[a-zA-Z0-9\-\/_]+$/', $value ) ) {
			wp_send_json_error( array( 'message' => __( 'SKU values must only include letters, numbers, forward slashes, underscores, and dashes.', 'wp-easycart' ) ) );
		}
		$duplicate_id = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT product_id FROM ec_product WHERE model_number = %s AND product_id != %d LIMIT 1",
			$value,
			$product_id
		) );
		if ( $duplicate_id > 0 ) {
			wp_send_json_error( array( 'message' => __( 'SKU is a duplicate and must be unique.', 'wp-easycart' ) ) );
		}
	}

	$updated = $wpdb->update( 'ec_product', array( $field => $value ), array( 'product_id' => $product_id ), array( $format ), array( '%d' ) );
	if ( false === $updated ) {
		wp_send_json_error( array( 'message' => __( 'Could not save the change. Please try again.', 'wp-easycart' ) ) );
	}
	if ( 'model_number' === $field ) {
		$linked_post_id = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT post_id FROM ec_product WHERE product_id = %d',
			$product_id
		) );
		if ( $linked_post_id > 0 ) {
			$wpdb->query( $wpdb->prepare(
				'UPDATE ' . $wpdb->prefix . 'posts SET post_content = %s, post_modified = NOW(), post_modified_gmt = UTC_TIMESTAMP() WHERE ID = %d AND post_type = %s',
				'[ec_store modelnumber="' . $value . '"]',
				$linked_post_id,
				'ec_store'
			) );
		}
	}
	wp_cache_flush();

	$display_value = $value;
	if ( in_array( $field, array( 'price', 'list_price' ), true ) ) {
		$display_value = $GLOBALS['currency']->get_currency_display( $value );
	} else if ( in_array( $field, array( 'title', 'model_number' ), true ) ) {
		$display_value = wp_specialchars_decode( $value, ENT_QUOTES );
	}

	wp_send_json_success( array(
		'product_id'    => $product_id,
		'field'         => $field,
		'value'         => $value,
		'display_value' => $display_value,
		'old_value'     => $old_value,
	) );
}

add_action( 'wp_ajax_ecv2_product_toggle_status', 'ecv2_product_toggle_status' );
function ecv2_product_toggle_status() {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
	}

	$product_id = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_easycart_nonce'] ) ), 'wp-easycart-ecv2-toggle-' . $product_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wp-easycart' ) ) );
	}

	$status = isset( $_POST['status'] ) ? ( (int) $_POST['status'] ? 1 : 0 ) : 0;

	global $wpdb;
	$old_status = $wpdb->get_var( $wpdb->prepare( "SELECT activate_in_store FROM ec_product WHERE product_id = %d", $product_id ) );

	$wpdb->update( 'ec_product', array( 'activate_in_store' => $status ), array( 'product_id' => $product_id ), array( '%d' ), array( '%d' ) );
	wp_cache_flush();

	if ( 0 == $status ) {
		do_action( 'wpeasycart_product_deactivated', (int) $product_id );
	} else {
		do_action( 'wpeasycart_product_activated', (int) $product_id );
	}

	wp_send_json_success( array(
		'product_id' => $product_id,
		'status'     => $status,
		'old_status' => (int) $old_status,
	) );
}

add_action( 'wp_ajax_ecv2_product_bulk_edit', 'ecv2_product_bulk_edit' );
function ecv2_product_bulk_edit() {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_easycart_nonce'] ) ), 'wp-easycart-ecv2-bulk-edit' ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wp-easycart' ) ) );
	}

	$product_ids = isset( $_POST['product_ids'] ) ? array_map( 'intval', (array) $_POST['product_ids'] ) : array();
	$changes = isset( $_POST['changes'] ) ? $_POST['changes'] : array();

	if ( empty( $product_ids ) || empty( $changes ) ) {
		wp_send_json_error( array( 'message' => __( 'No products or changes specified.', 'wp-easycart' ) ) );
	}
	
	$square_locked_fields = array( 'price', 'stock_quantity' );
	$requested_square_fields = array();
	if ( isset( $changes['price_mode'] ) && $changes['price_mode'] !== '' ) {
		$requested_square_fields[] = 'price';
	}
	if ( isset( $changes['stock_quantity_mode'] ) && $changes['stock_quantity_mode'] !== '' ) {
		$requested_square_fields[] = 'stock_quantity';
	}

	global $wpdb;
	$updated = 0;
	$square_skipped_count  = 0;
	$square_fully_skipped  = 0;


	foreach ( $product_ids as $pid ) {
		$update_data = array();
		$update_format = array();
		$pid_is_square = ecv2_is_square_locked( $pid );

		if ( isset( $changes['activate_in_store'] ) && $changes['activate_in_store'] !== '' ) {
			$update_data['activate_in_store'] = (int) $changes['activate_in_store'];
			$update_format[] = '%d';
		}
		if ( isset( $changes['manufacturer_id'] ) && $changes['manufacturer_id'] !== '' ) {
			$update_data['manufacturer_id'] = (int) $changes['manufacturer_id'];
			$update_format[] = '%d';
		}
		if ( ! $pid_is_square && isset( $changes['price_mode'] ) && $changes['price_mode'] !== '' && isset( $changes['price_value'] ) ) {
			$price_val = (float) $changes['price_value'];
			$current_price = (float) $wpdb->get_var( $wpdb->prepare( "SELECT price FROM ec_product WHERE product_id = %d", $pid ) );
			switch ( sanitize_key( $changes['price_mode'] ) ) {
				case 'set':              $update_data['price'] = $price_val; break;
				case 'increase':         $update_data['price'] = $current_price + $price_val; break;
				case 'decrease':         $update_data['price'] = max( 0, $current_price - $price_val ); break;
				case 'percent_increase': $update_data['price'] = $current_price * ( 1 + $price_val / 100 ); break;
				case 'percent_decrease': $update_data['price'] = max( 0, $current_price * ( 1 - $price_val / 100 ) ); break;
			}
			if ( isset( $update_data['price'] ) ) {
				$update_data['price'] = round( $update_data['price'], 2 );
				$update_format[] = '%s';
			}
		}
		if ( ! $pid_is_square && isset( $changes['stock_quantity_mode'] ) && $changes['stock_quantity_mode'] !== '' && isset( $changes['stock_quantity_value'] ) ) {
			$stock_val = (int) $changes['stock_quantity_value'];
			$current_stock = (int) $wpdb->get_var( $wpdb->prepare( "SELECT stock_quantity FROM ec_product WHERE product_id = %d", $pid ) );
			switch ( sanitize_key( $changes['stock_quantity_mode'] ) ) {
				case 'set':      $update_data['stock_quantity'] = $stock_val; break;
				case 'increase': $update_data['stock_quantity'] = $current_stock + $stock_val; break;
				case 'decrease': $update_data['stock_quantity'] = max( 0, $current_stock - $stock_val ); break;
			}
			if ( isset( $update_data['stock_quantity'] ) ) {
				$update_format[] = '%d';
			}
		}

		if ( $pid_is_square && ! empty( $requested_square_fields ) ) {
			$square_skipped_count++;
			if ( empty( $update_data ) ) {
				$square_fully_skipped++;
			}
		}

		if ( ! empty( $update_data ) ) {
			$wpdb->update( 'ec_product', $update_data, array( 'product_id' => $pid ), $update_format, array( '%d' ) );
			$updated++;
		}
	}

	wp_cache_flush();

	wp_send_json_success( array(
		'updated'                 => $updated,
		'total_selected'          => count( $product_ids ),
		'square_skipped_count'    => $square_skipped_count,
		'square_fully_skipped'    => $square_fully_skipped,
		'square_skipped_fields'   => $requested_square_fields,
	) );
}

if ( ! defined( 'ECV2_BULK_MAX' ) ) {
	define( 'ECV2_BULK_MAX', 500 );
}

function ecv2_sanitize_bulk_product_ids( $raw ) {
	if ( is_array( $raw ) ) {
		$source = $raw;
	} elseif ( is_string( $raw ) && $raw !== '' ) {
		$trimmed = trim( $raw );
		if ( strlen( $trimmed ) > 1 && $trimmed[0] === '[' ) {
			$decoded = json_decode( $trimmed, true );
			$source  = is_array( $decoded ) ? $decoded : array();
		} elseif ( strpos( $trimmed, ',' ) !== false ) {
			$source = explode( ',', $trimmed );
		} else {
			$source = array( $trimmed );
		}
	} elseif ( is_numeric( $raw ) ) {
		$source = array( $raw );
	} else {
		return array();
	}

	$ids = array();
	foreach ( $source as $val ) {
		$id = (int) $val;
		if ( $id > 0 ) {
			$ids[ $id ] = $id; // using key to dedupe
		}
	}
	$ids = array_values( $ids );
	if ( count( $ids ) > ECV2_BULK_MAX ) {
		$ids = array_slice( $ids, 0, ECV2_BULK_MAX );
	}
	return $ids;
}

add_action( 'wp_ajax_ecv2_product_bulk_delete', 'ecv2_product_bulk_delete' );
function ecv2_product_bulk_delete() {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
	}
	if ( ! isset( $_POST['wp_easycart_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_easycart_nonce'] ) ), 'wp-easycart-ecv2-bulk-delete' ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wp-easycart' ) ) );
	}

	$product_ids = ecv2_sanitize_bulk_product_ids( isset( $_POST['product_ids'] ) ? wp_unslash( $_POST['product_ids'] ) : array() );

	if ( empty( $product_ids ) ) {
		wp_send_json_error( array( 'message' => __( 'No products selected.', 'wp-easycart' ) ) );
	}

	global $wpdb;
	$deleted = 0;
	$failed  = array();

	foreach ( $product_ids as $pid ) {
		$post_id = $wpdb->get_var( $wpdb->prepare( 'SELECT post_id FROM ec_product WHERE product_id = %d', $pid ) );

		if ( null === $post_id ) {
			continue;
		}

		do_action( 'wpeasycart_product_deleting', $pid );

		if ( $post_id ) {
			wp_delete_post( (int) $post_id, true );
		}

		$ok = true;
		$ok = $ok && ( false !== $wpdb->query( $wpdb->prepare( 'DELETE FROM ec_product WHERE product_id = %d', $pid ) ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ec_optionitemimage WHERE product_id = %d', $pid ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ec_pricetier WHERE product_id = %d', $pid ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ec_roleprice WHERE product_id = %d', $pid ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ec_optionitemquantity WHERE product_id = %d', $pid ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ec_option_to_product WHERE product_id = %d', $pid ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ec_review WHERE product_id = %d', $pid ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ec_affiliate_rule_to_product WHERE product_id = %d', $pid ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ec_categoryitem WHERE product_id = %d', $pid ) );

		if ( $ok ) {
			$deleted++;
			do_action( 'wpeasycart_product_deleted', $pid );
		} else {
			$failed[] = $pid;
		}
	}

	wp_cache_delete( 'wpeasycart-all-categories' );
	wp_cache_flush();

	wp_send_json_success( array(
		'deleted'        => $deleted,
		'failed'         => $failed,
		'total_selected' => count( $product_ids ),
		/* translators: %d is the number of products deleted. */
		'message'        => sprintf( _n( '%d product deleted.', '%d products deleted.', $deleted, 'wp-easycart' ), $deleted ),
	) );
}

add_action( 'wp_ajax_ecv2_product_bulk_activate', 'ecv2_product_bulk_activate' );
function ecv2_product_bulk_activate() {
	ecv2_product_bulk_set_status( 1 );
}

add_action( 'wp_ajax_ecv2_product_bulk_deactivate', 'ecv2_product_bulk_deactivate' );
function ecv2_product_bulk_deactivate() {
	ecv2_product_bulk_set_status( 0 );
}

function ecv2_product_bulk_set_status( $new_status ) {
	$new_status = $new_status ? 1 : 0;

	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
	}
	$nonce_action = $new_status ? 'wp-easycart-ecv2-bulk-activate' : 'wp-easycart-ecv2-bulk-deactivate';
	if ( ! isset( $_POST['wp_easycart_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_easycart_nonce'] ) ), $nonce_action ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wp-easycart' ) ) );
	}

	$product_ids = ecv2_sanitize_bulk_product_ids( isset( $_POST['product_ids'] ) ? wp_unslash( $_POST['product_ids'] ) : array() );

	if ( empty( $product_ids ) ) {
		wp_send_json_error( array( 'message' => __( 'No products selected.', 'wp-easycart' ) ) );
	}

	global $wpdb;

	$id_list_sql = implode( ',', array_map( 'intval', $product_ids ) );

	$rows = $wpdb->get_results(
		"SELECT product_id, post_id, model_number FROM ec_product WHERE product_id IN ( {$id_list_sql} )"
	);

	if ( empty( $rows ) ) {
		wp_send_json_error( array( 'message' => __( 'No matching products found.', 'wp-easycart' ) ) );
	}

	$resolved_product_ids = array();
	$resolved_post_ids    = array();
	foreach ( $rows as $row ) {
		$resolved_product_ids[] = (int) $row->product_id;
		if ( ! empty( $row->post_id ) ) {
			$resolved_post_ids[] = (int) $row->post_id;
		}
	}

	$resolved_id_list_sql = implode( ',', array_map( 'intval', $resolved_product_ids ) );

	$wpdb->query(
		$wpdb->prepare(
			"UPDATE ec_product SET activate_in_store = %d WHERE product_id IN ( {$resolved_id_list_sql} )",
			$new_status
		)
	);

	if ( ! empty( $resolved_post_ids ) ) {
		$post_id_list_sql = implode( ',', array_map( 'intval', $resolved_post_ids ) );
		$post_status      = $new_status ? 'publish' : 'private';
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}posts SET post_status = %s WHERE ID IN ( {$post_id_list_sql} )",
				$post_status
			)
		);
	}

	$updated_ids = array();
	foreach ( $rows as $row ) {
		$pid = (int) $row->product_id;
		if ( ! empty( $row->model_number ) ) {
			wp_cache_delete( 'wpeasycart-product-only-' . $row->model_number, 'wpeasycart-product-list' );
		}
		if ( $new_status ) {
			do_action( 'wpeasycart_product_activated', $pid );
		} else {
			do_action( 'wpeasycart_product_deactivated', $pid );
		}
		$updated_ids[] = $pid;
	}

	wp_cache_flush();

	wp_send_json_success( array(
		'updated'        => count( $updated_ids ),
		'updated_ids'    => $updated_ids,
		'new_status'     => $new_status,
		'total_selected' => count( $product_ids ),
		'message'        => $new_status
			/* translators: %d is the number of products activated. */
			? sprintf( _n( '%d product activated.', '%d products activated.', count( $updated_ids ), 'wp-easycart' ), count( $updated_ids ) )
			/* translators: %d is the number of products deactivated. */
			: sprintf( _n( '%d product deactivated.', '%d products deactivated.', count( $updated_ids ), 'wp-easycart' ), count( $updated_ids ) ),
	) );
}

add_action( 'wp_ajax_ecv2_product_bulk_export_prepare', 'ecv2_product_bulk_export_prepare' );
function ecv2_product_bulk_export_prepare() {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
	}
	if ( ! isset( $_POST['wp_easycart_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_easycart_nonce'] ) ), 'wp-easycart-ecv2-bulk-export' ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wp-easycart' ) ) );
	}

	$product_ids = ecv2_sanitize_bulk_product_ids( isset( $_POST['product_ids'] ) ? wp_unslash( $_POST['product_ids'] ) : array() );

	if ( empty( $product_ids ) ) {
		wp_send_json_error( array( 'message' => __( 'No products selected.', 'wp-easycart' ) ) );
	}

	$token = strtolower( wp_generate_password( 32, false, false ) );

	set_transient( 'ecv2_export_' . $token, $product_ids, 10 * MINUTE_IN_SECONDS );

	$download_url = add_query_arg(
		array(
			'page'                 => 'wp-easycart-products',
			'subpage'              => 'products',
			'ec_admin_form_action' => 'ecv2-export-staged',
			'ecv2_export_token'    => $token,
			'_wpnonce'             => wp_create_nonce( 'ecv2-export-' . $token ),
		),
		admin_url( 'admin.php' )
	);

	wp_send_json_success( array(
		'download_url'   => $download_url,
		'token'          => $token,
		'count'          => count( $product_ids ),
		'expires_in'     => 10 * MINUTE_IN_SECONDS,
		/* translators: %d is the number of products staged for export. */
		'message'        => sprintf( _n( '%d product ready to export.', '%d products ready to export.', count( $product_ids ), 'wp-easycart' ), count( $product_ids ) ),
	) );
}

add_action( 'wp_ajax_ecv2_product_get_sale_data', 'ecv2_product_get_sale_data' );
function ecv2_product_get_sale_data() {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_easycart_nonce'] ) ), 'wp-easycart-ecv2-get-sale-data' ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wp-easycart' ) ) );
	}

	$product_id = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
	global $wpdb;
	$product = $wpdb->get_row( $wpdb->prepare( "SELECT product_id, title, price, list_price FROM ec_product WHERE product_id = %d", $product_id ) );

	if ( ! $product ) {
		wp_send_json_error( array( 'message' => __( 'Product not found.', 'wp-easycart' ) ) );
	}

	wp_send_json_success( array(
		'product_id'      => $product->product_id,
		'title'           => $product->title,
		'price'           => $product->price,
		'price_formatted' => $GLOBALS['currency']->get_currency_display( $product->price ),
		'list_price'      => $product->list_price,
	) );
}

add_action( 'wp_ajax_ecv2_product_schedule_sale', 'ecv2_product_schedule_sale' );
function ecv2_product_schedule_sale() {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_easycart_nonce'] ) ), 'wp-easycart-ecv2-schedule-sale' ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wp-easycart' ) ) );
	}

	$product_id = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
	$sale_price = isset( $_POST['sale_price'] ) ? wp_easycart_admin_verification()->filter_float( sanitize_text_field( wp_unslash( $_POST['sale_price'] ) ) ) : 0;

	if ( ! $product_id || $sale_price <= 0 ) {
		wp_send_json_error( array( 'message' => __( 'Invalid sale price.', 'wp-easycart' ) ) );
	}

	if ( ecv2_is_square_locked( $product_id, 'price' ) ) {
		wp_send_json_error( array( 'message' => __( 'This product is synced with Square. Please make changes in your Square dashboard.', 'wp-easycart' ) ) );
	}

	global $wpdb;
	$current_price = (float) $wpdb->get_var( $wpdb->prepare( "SELECT price FROM ec_product WHERE product_id = %d", $product_id ) );

	$wpdb->update(
		'ec_product',
		array( 'price' => $sale_price, 'list_price' => $current_price > $sale_price ? $current_price : 0 ),
		array( 'product_id' => $product_id ),
		array( '%s', '%s' ),
		array( '%d' )
	);
	wp_cache_flush();

	wp_send_json_success( array(
		'product_id'     => $product_id,
		'old_price'      => $current_price,
		'sale_price'     => $sale_price,
		'sale_formatted' => $GLOBALS['currency']->get_currency_display( $sale_price ),
	) );
}

add_action( 'wp_ajax_ecv2_product_remove_sale', 'ecv2_product_remove_sale' );
function ecv2_product_remove_sale() {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_easycart_nonce'] ) ), 'wp-easycart-ecv2-remove-sale' ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wp-easycart' ) ) );
	}

	$product_id = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;

	global $wpdb;
	$product = $wpdb->get_row( $wpdb->prepare( "SELECT price, list_price FROM ec_product WHERE product_id = %d", $product_id ) );

	if ( $product && $product->list_price > 0 ) {
		$wpdb->update( 'ec_product', array( 'price' => $product->list_price, 'list_price' => 0 ), array( 'product_id' => $product_id ), array( '%s', '%s' ), array( '%d' ) );
	}
	wp_cache_flush();

	wp_send_json_success( array( 'product_id' => $product_id ) );
}

/* --- Stock Tracking Type Change --- */

add_action( 'wp_ajax_ecv2_product_change_tracking_type', 'ecv2_product_change_tracking_type' );
function ecv2_product_change_tracking_type() {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
	}

	$product_id = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_easycart_nonce'] ) ), 'wp-easycart-ecv2-stock-action-' . $product_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wp-easycart' ) ) );
	}

	$new_type = isset( $_POST['tracking_type'] ) ? sanitize_key( $_POST['tracking_type'] ) : '';

	if ( ! in_array( $new_type, array( 'unlimited', 'basic', 'option' ), true ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid tracking type.', 'wp-easycart' ) ) );
	}

	if ( $new_type === 'option' && ! ecv2_is_variant_tracking_enabled() ) {
		$gate = ecv2_get_variant_tracking_gate();
		wp_send_json_error( array(
			'message' => wp_easycart_admin_pro_gate::message( $gate, __( 'Option/Variant tracking', 'wp-easycart' ) ),
		) );
	}

	if ( ecv2_is_square_locked( $product_id, 'stock_quantity' ) ) {
		wp_send_json_error( array( 'message' => __( 'This product is synced with Square. Stock tracking is managed by Square.', 'wp-easycart' ) ) );
	}

	global $wpdb;

	$show_stock_quantity              = 0;
	$use_optionitem_quantity_tracking = 0;

	switch ( $new_type ) {
		case 'basic':
			$show_stock_quantity = 1;
			break;
		case 'option':
			$show_stock_quantity              = 1;
			$use_optionitem_quantity_tracking = 1;
			break;
	}

	$wpdb->update(
		'ec_product',
		array(
			'show_stock_quantity'              => $show_stock_quantity,
			'use_optionitem_quantity_tracking' => $use_optionitem_quantity_tracking,
		),
		array( 'product_id' => $product_id ),
		array( '%d', '%d' ),
		array( '%d' )
	);
	wp_cache_flush();

	$product = $wpdb->get_row( $wpdb->prepare( "SELECT stock_quantity FROM ec_product WHERE product_id = %d", $product_id ) );
	$stock_quantity = $product ? (int) $product->stock_quantity : 0;

	$option_total = 0;
	if ( $new_type === 'option' ) {
		$option_total = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE( SUM( quantity ), 0 ) FROM ec_optionitemquantity WHERE product_id = %d AND is_enabled = 1 AND is_stock_tracking_enabled = 1",
			$product_id
		) );
	}

	wp_send_json_success( array(
		'product_id'     => $product_id,
		'tracking_type'  => $new_type,
		'stock_quantity' => $stock_quantity,
		'option_total'   => $option_total,
	) );
}

/* --- Price Editor: Save Price + List Price --- */
add_action( 'wp_ajax_ecv2_product_save_prices', 'ecv2_product_save_prices' );
function ecv2_product_save_prices() {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
	}

	$product_id = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
	if ( ! $product_id ) {
		wp_send_json_error( array( 'message' => __( 'Invalid product.', 'wp-easycart' ) ) );
	}

	if ( ! isset( $_POST['wp_easycart_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_easycart_nonce'] ) ), 'wp-easycart-ecv2-price-edit-' . $product_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wp-easycart' ) ) );
	}

	if ( ecv2_is_square_locked( $product_id, 'price' ) ) {
		wp_send_json_error( array( 'message' => __( 'This product is synced with Square. Please make changes in your Square dashboard.', 'wp-easycart' ) ) );
	}

	global $wpdb;

	// --- Read-old snapshot (used for undo). --------------------------------
	$old = $wpdb->get_row( $wpdb->prepare(
		"SELECT price, list_price, login_for_pricing, login_for_pricing_user_level, login_for_pricing_label,
				enable_price_label, replace_price_label, custom_price_label,
				show_custom_price_range, price_range_low, price_range_high
		 FROM ec_product WHERE product_id = %d",
		$product_id
	) );

	// --- Required fields: price + list_price. -----------------------------
	$price_raw      = isset( $_POST['price'] ) ? sanitize_text_field( wp_unslash( $_POST['price'] ) ) : '';
	$list_price_raw = isset( $_POST['list_price'] ) ? sanitize_text_field( wp_unslash( $_POST['list_price'] ) ) : '';

	$price      = wp_easycart_admin_verification()->filter_float( $price_raw );
	$list_price = ( '' === trim( $list_price_raw ) ) ? 0 : wp_easycart_admin_verification()->filter_float( $list_price_raw );

	if ( $price < 0 ) {
		$price = 0;
	}
	if ( $list_price < 0 ) {
		$list_price = 0;
	}

	$update_fields  = array( 'price' => $price, 'list_price' => $list_price );
	$update_formats = array( '%s', '%s' );

	// --- Optional core fields: only update if the field is in $_POST. ------
	// (Keeps the endpoint useful for partial saves and matches save_product_details_pricing in wp_easycart_admin_products.php.)

	// Custom price range.
	if ( isset( $_POST['show_custom_price_range'] ) ) {
		$update_fields['show_custom_price_range'] = ( '1' === (string) $_POST['show_custom_price_range'] ) ? 1 : 0;
		$update_formats[]                         = '%d';
	}
	if ( isset( $_POST['price_range_low'] ) ) {
		$update_fields['price_range_low'] = wp_easycart_admin_verification()->filter_float( sanitize_text_field( wp_unslash( $_POST['price_range_low'] ) ) );
		$update_formats[]                 = '%s';
	}
	if ( isset( $_POST['price_range_high'] ) ) {
		$update_fields['price_range_high'] = wp_easycart_admin_verification()->filter_float( sanitize_text_field( wp_unslash( $_POST['price_range_high'] ) ) );
		$update_formats[]                  = '%s';
	}

	// Custom price label — gated by PRO via the existing
	// wp_easycart_admin_product_custom_price_label_save filter (returns 0 on free, actual value on PRO).
	if ( isset( $_POST['enable_price_label'] ) ) {
		$enable_price_label_raw = (int) $_POST['enable_price_label'];
		// Range-clamp so the filter only ever sees an expected integer.
		if ( $enable_price_label_raw < 0 || $enable_price_label_raw > 7 ) {
			$enable_price_label_raw = 0;
		}
		$update_fields['enable_price_label'] = (int) apply_filters( 'wp_easycart_admin_product_custom_price_label_save', 0, $enable_price_label_raw );
		$update_formats[]                    = '%d';
	}
	if ( isset( $_POST['replace_price_label'] ) ) {
		$update_fields['replace_price_label'] = ( '1' === (string) $_POST['replace_price_label'] ) ? 1 : 0;
		$update_formats[]                     = '%d';
	}
	if ( isset( $_POST['custom_price_label'] ) ) {
		// wp_easycart_escape_html mirrors the save path used in wp_easycart_admin_products.php for this field.
		$update_fields['custom_price_label'] = wp_easycart_escape_html( wp_unslash( $_POST['custom_price_label'] ) );
		$update_formats[]                    = '%s';
	}

	// Login for pricing.
	if ( isset( $_POST['login_for_pricing'] ) ) {
		$update_fields['login_for_pricing'] = ( '1' === (string) $_POST['login_for_pricing'] ) ? 1 : 0;
		$update_formats[]                   = '%d';
	}
	if ( isset( $_POST['login_for_pricing_user_level'] ) ) {
		$valid_user_levels = array();
		$incoming_levels   = $_POST['login_for_pricing_user_level']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- each item is unslashed and validated below.
		if ( ! is_array( $incoming_levels ) ) {
			$incoming_levels = array( $incoming_levels );
		}
		foreach ( $incoming_levels as $user_role ) {
			$user_role = sanitize_text_field( wp_unslash( $user_role ) );
			if ( '' !== $user_role && wp_easycart_admin_verification()->valid_user_role( $user_role ) ) {
				$valid_user_levels[] = $user_role;
			}
		}
		$update_fields['login_for_pricing_user_level'] = wp_json_encode( $valid_user_levels );
		$update_formats[]                              = '%s';
	}
	if ( isset( $_POST['login_for_pricing_label'] ) ) {
		$update_fields['login_for_pricing_label'] = sanitize_text_field( wp_unslash( $_POST['login_for_pricing_label'] ) );
		$update_formats[]                         = '%s';
	}

	// --- Persist. ---------------------------------------------------------
	$wpdb->update(
		'ec_product',
		$update_fields,
		array( 'product_id' => $product_id ),
		$update_formats,
		array( '%d' )
	);

	// Bust the per-product cache that the storefront uses (mirrors save_product_details_pricing).
	$model_number = $wpdb->get_var( $wpdb->prepare( 'SELECT model_number FROM ec_product WHERE product_id = %d', $product_id ) );
	if ( ! empty( $model_number ) ) {
		wp_cache_delete( 'wpeasycart-product-only-' . $model_number, 'wpeasycart-product-list' );
	}
	wp_cache_flush();

	// PRO extension: lets PRO save its own POST data (e.g. extra advanced-pricing fields)
	// inside the same nonce-validated, capability-checked request — no second round-trip.
	do_action( 'wp_easycart_admin_ecv2_save_price_extras', $product_id );
	do_action( 'wpeasycart_product_updated', $product_id, $model_number );

	// --- Re-read the updated row so the response reflects whatever was actually written
	//     (including any changes the PRO save extras hook made). -------------
	$updated = $wpdb->get_row( $wpdb->prepare(
		"SELECT price, list_price, login_for_pricing, login_for_pricing_user_level, login_for_pricing_label,
				enable_price_label, replace_price_label, custom_price_label,
				show_custom_price_range, price_range_low, price_range_high,
				(SELECT COUNT(*) FROM ec_pricetier WHERE ec_pricetier.product_id = ec_product.product_id) AS tier_count,
				(SELECT COUNT(*) FROM ec_roleprice WHERE ec_roleprice.product_id = ec_product.product_id) AS roleprice_count
		 FROM ec_product WHERE product_id = %d",
		$product_id
	) );

	$is_on_sale   = ( $updated && $updated->list_price > 0 && $updated->list_price > $updated->price );
	$discount_pct = $is_on_sale ? round( ( $updated->list_price - $updated->price ) / $updated->list_price * 100 ) : 0;

	$response = array(
		'product_id'                   => $product_id,
		'price'                        => $updated ? (float) $updated->price : $price,
		'list_price'                   => $updated ? (float) $updated->list_price : $list_price,
		'price_formatted'              => $GLOBALS['currency']->get_currency_display( $updated ? $updated->price : $price ),
		'list_price_formatted'         => ( $updated && $updated->list_price > 0 ) ? $GLOBALS['currency']->get_currency_display( $updated->list_price ) : '',
		'is_on_sale'                   => $is_on_sale,
		'discount_pct'                 => $discount_pct,
		'old_price'                    => $old ? (float) $old->price : 0,
		'old_list_price'               => $old ? (float) $old->list_price : 0,

		// Echo new fields back so JS can refresh badges without a page reload.
		'login_for_pricing'            => $updated ? (int) $updated->login_for_pricing : 0,
		'login_for_pricing_user_level' => $updated ? (string) $updated->login_for_pricing_user_level : '',
		'login_for_pricing_label'      => $updated ? (string) $updated->login_for_pricing_label : '',
		'enable_price_label'           => $updated ? (int) $updated->enable_price_label : 0,
		'replace_price_label'          => $updated ? (int) $updated->replace_price_label : 0,
		'custom_price_label'           => $updated ? (string) $updated->custom_price_label : '',
		'show_custom_price_range'      => $updated ? (int) $updated->show_custom_price_range : 0,
		'price_range_low'              => $updated ? (float) $updated->price_range_low : 0,
		'price_range_high'             => $updated ? (float) $updated->price_range_high : 0,
		'tier_count'                   => $updated ? (int) $updated->tier_count : 0,
		'roleprice_count'              => $updated ? (int) $updated->roleprice_count : 0,
	);

	// Allow PRO to add response data (e.g. updated tier list, role list) for live UI refresh.
	$response = apply_filters( 'wp_easycart_admin_ecv2_save_price_response', $response, $product_id );

	wp_send_json_success( $response );
}

/* --- Category Editor: Search Categories (AJAX) --- */
add_action( 'wp_ajax_ecv2_category_search', 'ecv2_category_search' );
function ecv2_category_search() {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_easycart_nonce'] ) ), 'wp-easycart-ecv2-category-search' ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wp-easycart' ) ) );
	}

	$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
	$product_id = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;

	if ( strlen( $search ) < 1 ) {
		wp_send_json_success( array( 'results' => array() ) );
	}

	global $wpdb;

	$assigned_ids = array();
	if ( $product_id > 0 ) {
		$assigned_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT category_id FROM ec_categoryitem WHERE product_id = %d",
			$product_id
		) );
		$assigned_ids = array_map( 'intval', $assigned_ids );
	}

	$results = $wpdb->get_results( $wpdb->prepare(
		"SELECT category_id, category_name FROM ec_category WHERE category_name LIKE %s ORDER BY category_name ASC LIMIT 20",
		'%' . $wpdb->esc_like( $search ) . '%'
	) );

	$formatted = array();
	foreach ( $results as $row ) {
		$cat_id = (int) $row->category_id;
		$formatted[] = array(
			'id'       => $cat_id,
			'name'     => $row->category_name,
			'assigned' => in_array( $cat_id, $assigned_ids, true ),
		);
	}

	wp_send_json_success( array( 'results' => $formatted ) );
}

/* --- Category Editor: Add Category to Product --- */

add_action( 'wp_ajax_ecv2_category_add', 'ecv2_category_add' );
function ecv2_category_add() {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
	}

	$product_id  = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
	$category_id = isset( $_POST['category_id'] ) ? (int) $_POST['category_id'] : 0;

	if ( ! $product_id || ! $category_id ) {
		wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'wp-easycart' ) ) );
	}

	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_easycart_nonce'] ) ), 'wp-easycart-ecv2-category-' . $product_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wp-easycart' ) ) );
	}

	global $wpdb;

	$exists = $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM ec_categoryitem WHERE product_id = %d AND category_id = %d",
		$product_id,
		$category_id
	) );

	if ( $exists ) {
		wp_send_json_error( array( 'message' => __( 'Category already assigned.', 'wp-easycart' ) ) );
	}

	$wpdb->insert(
		'ec_categoryitem',
		array( 'product_id' => $product_id, 'category_id' => $category_id ),
		array( '%d', '%d' )
	);
	wp_cache_flush();

	$categories = $wpdb->get_results( $wpdb->prepare(
		"SELECT ec_category.category_id AS id, ec_category.category_name AS name FROM ec_categoryitem INNER JOIN ec_category ON ec_categoryitem.category_id = ec_category.category_id WHERE ec_categoryitem.product_id = %d ORDER BY ec_category.category_name ASC",
		$product_id
	) );

	$formatted = array();
	foreach ( $categories as $cat ) {
		$formatted[] = array( 'id' => (int) $cat->id, 'name' => $cat->name );
	}

	wp_send_json_success( array( 'categories' => $formatted ) );
}

add_action( 'wp_ajax_ecv2_category_remove', 'ecv2_category_remove' );
function ecv2_category_remove() {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
	}

	$product_id  = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
	$category_id = isset( $_POST['category_id'] ) ? (int) $_POST['category_id'] : 0;

	if ( ! $product_id || ! $category_id ) {
		wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'wp-easycart' ) ) );
	}

	// Verify per-product nonce.
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_easycart_nonce'] ) ), 'wp-easycart-ecv2-category-' . $product_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wp-easycart' ) ) );
	}

	global $wpdb;
	$wpdb->delete(
		'ec_categoryitem',
		array( 'product_id' => $product_id, 'category_id' => $category_id ),
		array( '%d', '%d' )
	);
	wp_cache_flush();

	// Return updated category list.
	$categories = $wpdb->get_results( $wpdb->prepare(
		"SELECT ec_category.category_id AS id, ec_category.category_name AS name FROM ec_categoryitem INNER JOIN ec_category ON ec_categoryitem.category_id = ec_category.category_id WHERE ec_categoryitem.product_id = %d ORDER BY ec_category.category_name ASC",
		$product_id
	) );

	$formatted = array();
	foreach ( $categories as $cat ) {
		$formatted[] = array( 'id' => (int) $cat->id, 'name' => $cat->name );
	}

	wp_send_json_success( array( 'categories' => $formatted ) );
}

/* ===================================================================
   Image Manager - AJAX: Get Product Images
   =================================================================== */

add_action( 'wp_ajax_ecv2_get_product_images', 'ecv2_get_product_images' );
function ecv2_get_product_images() {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
	}

	$product_id = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
	if ( ! $product_id ) {
		wp_send_json_error( array( 'message' => __( 'Invalid product ID.', 'wp-easycart' ) ) );
	}

	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_easycart_nonce'] ) ), 'wp-easycart-ecv2-image-manager-' . $product_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wp-easycart' ) ) );
	}

	global $wpdb;
	$product = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_product WHERE product_id = %d', $product_id ) );
	if ( ! $product ) {
		wp_send_json_error( array( 'message' => __( 'Product not found.', 'wp-easycart' ) ) );
	}

	$is_licensed = false;
	if ( function_exists( 'wp_easycart_admin_license' ) && wp_easycart_admin_license()->is_licensed() ) {
		$is_licensed = true;
	}

	$optionitem_mode = ( ! empty( $product->use_optionitem_images ) && $is_licensed );
	$basic_images    = ecv2_build_default_image_set( $product, $optionitem_mode );

	// Determine which option types are available for this product.
	$has_basic_options = false;
	$has_advanced_options = false;
	for ( $i = 1; $i <= 5; $i++ ) {
		$field = 'option_id_' . $i;
		if ( ! empty( $product->$field ) && (int) $product->$field > 0 ) {
			$has_basic_options = true;
			break;
		}
	}
	$advanced_options_for_images = $wpdb->get_results( $wpdb->prepare(
		"SELECT ec_option.* FROM ec_option_to_product, ec_option WHERE ec_option_to_product.product_id = %d AND ec_option.option_id = ec_option_to_product.option_id ORDER BY ec_option_to_product.option_order ASC",
		$product_id
	) );
	foreach ( $advanced_options_for_images as $opt ) {
		if ( in_array( $opt->option_type, array( 'combo', 'swatch', 'radio' ), true ) ) {
			$has_advanced_options = true;
			break;
		}
	}

	$response = array(
		'product_id'              => $product_id,
		'title'                   => html_entity_decode( $product->title, ENT_QUOTES, 'UTF-8' ),
		'use_optionitem_images'   => (int) $product->use_optionitem_images,
		'use_advanced_optionset'  => (int) $product->use_advanced_optionset,
		'has_basic_options'       => $has_basic_options,
		'has_advanced_options'    => $has_advanced_options,
		'is_licensed'             => $is_licensed,
		'sets'                    => array(
			array(
				'id'     => 'basic',
				'label'  => __( 'Default Images', 'wp-easycart' ),
				'images' => $basic_images,
			),
		),
	);

	if ( $product->use_optionitem_images && $is_licensed ) {
		$option_items = ecv2_get_product_option_items( $product );
		$option_item_images = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM ec_optionitemimage WHERE product_id = %d",
			$product_id
		) );

		if ( $option_items ) {
			foreach ( $option_items as $oi ) {
				$oi_image_set = null;
				foreach ( $option_item_images as $oii ) {
					if ( (int) $oii->optionitem_id === (int) $oi->optionitem_id ) {
						$oi_image_set = $oii;
						break;
					}
				}
				$oi_images = ecv2_build_optionitem_image_list( $product, $oi_image_set );
				$response['sets'][] = array(
					'id'     => (int) $oi->optionitem_id,
					'label'  => esc_html( $oi->optionitem_name ),
					'images' => $oi_images,
				);
			}
		}
	}

	if ( isset( $_POST['preview_type'] ) && $is_licensed ) {
		$preview_type = sanitize_key( $_POST['preview_type'] );
		if ( in_array( $preview_type, array( 'basic', 'variant', 'modifier' ), true ) ) {
			$preview_optionitem_mode = in_array( $preview_type, array( 'variant', 'modifier' ), true );
			$preview_basic_images    = ecv2_build_default_image_set( $product, $preview_optionitem_mode );

			$response['sets'] = array(
				array(
					'id'     => 'basic',
					'label'  => __( 'Default Images', 'wp-easycart' ),
					'images' => $preview_basic_images,
				),
			);

			if ( $preview_type === 'variant' || $preview_type === 'modifier' ) {
				$preview_product = clone $product;
				$preview_product->use_advanced_optionset = ( $preview_type === 'modifier' ) ? 1 : 0;
				$preview_option_items = ecv2_get_product_option_items( $preview_product );
				$option_item_images = $wpdb->get_results( $wpdb->prepare(
					"SELECT * FROM ec_optionitemimage WHERE product_id = %d",
					$product_id
				) );

				if ( $preview_option_items ) {
					foreach ( $preview_option_items as $oi ) {
						$oi_image_set = null;
						foreach ( $option_item_images as $oii ) {
							if ( (int) $oii->optionitem_id === (int) $oi->optionitem_id ) {
								$oi_image_set = $oii;
								break;
							}
						}
						$oi_images = ecv2_build_optionitem_image_list( $product, $oi_image_set );
						$response['sets'][] = array(
							'id'     => (int) $oi->optionitem_id,
							'label'  => esc_html( $oi->optionitem_name ),
							'images' => $oi_images,
						);
					}
				}
			}
		}
	}

	wp_send_json_success( $response );
}

function ecv2_build_image_list( $product ) {
	$images = array();
	$product_images_str = ! empty( $product->product_images ) ? $product->product_images : '';

	if ( ! empty( $product_images_str ) ) {
		$items = explode( ',', $product_images_str );
		foreach ( $items as $item ) {
			$img = ecv2_parse_image_item( $item, $product );
			if ( $img ) {
				$images[] = $img;
			}
		}
	} else {
		for ( $i = 1; $i <= 5; $i++ ) {
			$field = 'image' . $i;
			if ( ! empty( $product->$field ) ) {
				$url = $product->$field;
				if ( substr( $url, 0, 7 ) !== 'http://' && substr( $url, 0, 8 ) !== 'https://' ) {
					$url = plugins_url( '/wp-easycart-data/products/pics' . $i . '/' . $url, EC_PLUGIN_DATA_DIRECTORY );
				}
				$images[] = array(
					'id'    => $field,
					'type'  => 'legacy',
					'url'   => $url,
					'thumb' => $url,
				);
			}
		}
	}

	return $images;
}

function ecv2_build_default_image_set( $product, $optionitem_mode ) {
	if ( ! $optionitem_mode ) {
		return ecv2_build_image_list( $product );
	}

	global $wpdb;
	$oi_image_set = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM ec_optionitemimage WHERE product_id = %d AND optionitem_id = 0",
		(int) $product->product_id
	) );
	return ecv2_build_optionitem_image_list( $product, $oi_image_set );
}

function ecv2_build_optionitem_image_list( $product, $oi_image_set ) {
	$images = array();
	if ( ! $oi_image_set ) {
		return $images;
	}

	$product_images_str = ! empty( $oi_image_set->product_images ) ? $oi_image_set->product_images : '';

	if ( ! empty( $product_images_str ) ) {
		$items = explode( ',', $product_images_str );
		foreach ( $items as $item ) {
			$img = ecv2_parse_image_item( $item, $product );
			if ( $img ) {
				$images[] = $img;
			}
		}
	} else {
		for ( $i = 1; $i <= 5; $i++ ) {
			$field = 'image' . $i;
			if ( ! empty( $oi_image_set->$field ) ) {
				$url = $oi_image_set->$field;
				if ( substr( $url, 0, 7 ) !== 'http://' && substr( $url, 0, 8 ) !== 'https://' ) {
					$url = plugins_url( '/wp-easycart-data/products/pics' . $i . '/' . $url, EC_PLUGIN_DATA_DIRECTORY );
				}
				$images[] = array(
					'id'    => $field,
					'type'  => 'legacy',
					'url'   => $url,
					'thumb' => $url,
				);
			}
		}
	}

	return $images;
}

function ecv2_parse_image_item( $item, $product ) {
	$item = trim( $item );
	if ( empty( $item ) ) {
		return null;
	}

	if ( preg_match( '/^image([1-5])$/', $item, $m ) ) {
		$field = $item;
		$val = isset( $product->$field ) ? $product->$field : '';
		if ( empty( $val ) ) {
			return null;
		}
		$url = $val;
		if ( substr( $url, 0, 7 ) !== 'http://' && substr( $url, 0, 8 ) !== 'https://' ) {
			$url = plugins_url( '/wp-easycart-data/products/pics' . $m[1] . '/' . $url, EC_PLUGIN_DATA_DIRECTORY );
		}
		return array( 'id' => $item, 'type' => 'legacy', 'url' => $url, 'thumb' => $url );
	}

	if ( substr( $item, 0, 6 ) === 'image:' ) {
		$url = substr( $item, 6 );
		return array( 'id' => $item, 'type' => 'image_url', 'url' => $url, 'thumb' => $url );
	}

	if ( substr( $item, 0, 6 ) === 'video:' ) {
		$rest = substr( $item, 6 );
		$parts = explode( ':::', $rest );
		return array( 'id' => $item, 'type' => 'video', 'url' => $parts[0], 'thumb' => isset( $parts[1] ) ? $parts[1] : '' );
	}

	if ( substr( $item, 0, 8 ) === 'youtube:' ) {
		$rest = substr( $item, 8 );
		$parts = explode( ':::', $rest );
		return array( 'id' => $item, 'type' => 'youtube', 'url' => $parts[0], 'thumb' => isset( $parts[1] ) ? $parts[1] : '' );
	}

	if ( substr( $item, 0, 6 ) === 'vimeo:' ) {
		$rest = substr( $item, 6 );
		$parts = explode( ':::', $rest );
		return array( 'id' => $item, 'type' => 'vimeo', 'url' => $parts[0], 'thumb' => isset( $parts[1] ) ? $parts[1] : '' );
	}

	if ( substr( $item, 0, 7 ) === 'http://' || substr( $item, 0, 8 ) === 'https://' ) {
		return array( 'id' => $item, 'type' => 'external', 'url' => $item, 'thumb' => $item );
	}

	if ( is_numeric( $item ) ) {
		$attachment = wp_get_attachment_image_src( (int) $item, 'large' );
		if ( $attachment ) {
			return array( 'id' => $item, 'type' => 'media', 'url' => $attachment[0], 'thumb' => $attachment[0] );
		}
		return null;
	}

	return null;
}

function ecv2_get_product_option_items( $product ) {
	global $wpdb;

	if ( $product->use_advanced_optionset ) {
		$advanced_options = $wpdb->get_results( $wpdb->prepare(
			"SELECT ec_option.* FROM ec_option_to_product, ec_option WHERE ec_option_to_product.product_id = %d AND ec_option.option_id = ec_option_to_product.option_id ORDER BY ec_option_to_product.option_order ASC",
			$product->product_id
		) );
		$advanced_option = null;
		foreach ( $advanced_options as $opt ) {
			if ( in_array( $opt->option_type, array( 'combo', 'swatch', 'radio' ), true ) ) {
				$advanced_option = $opt;
				break;
			}
		}
		if ( $advanced_option ) {
			return $wpdb->get_results( $wpdb->prepare(
				'SELECT * FROM ec_optionitem WHERE option_id = %d ORDER BY optionitem_order ASC',
				$advanced_option->option_id
			) );
		}
	} else {
		for ( $i = 1; $i <= 5; $i++ ) {
			$field = 'option_id_' . $i;
			if ( ! empty( $product->$field ) && (int) $product->$field > 0 ) {
				return $wpdb->get_results( $wpdb->prepare(
					'SELECT * FROM ec_optionitem WHERE option_id = %d ORDER BY optionitem_order ASC',
					(int) $product->$field
				) );
			}
		}
	}

	return array();
}

/* ===================================================================
   Image Manager - AJAX: Save Product Images
   =================================================================== */

add_action( 'wp_ajax_ecv2_save_product_images', 'ecv2_save_product_images' );
function ecv2_save_product_images() {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
	}

	$product_id = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
	if ( ! $product_id ) {
		wp_send_json_error( array( 'message' => __( 'Invalid product ID.', 'wp-easycart' ) ) );
	}

	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_easycart_nonce'] ) ), 'wp-easycart-ecv2-image-manager-' . $product_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wp-easycart' ) ) );
	}

	global $wpdb;
	$product = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_product WHERE product_id = %d', $product_id ) );
	if ( ! $product ) {
		wp_send_json_error( array( 'message' => __( 'Product not found.', 'wp-easycart' ) ) );
	}

	$sets_raw = isset( $_POST['sets'] ) ? $_POST['sets'] : array();
	if ( is_string( $sets_raw ) ) {
		$sets_raw = json_decode( wp_unslash( $sets_raw ), true );
	}
	if ( ! is_array( $sets_raw ) ) {
		$sets_raw = array();
	}

	$is_licensed = false;
	if ( function_exists( 'wp_easycart_admin_license' ) && wp_easycart_admin_license()->is_licensed() ) {
		$is_licensed = true;
	}

	// Save image type flags if provided.
	if ( isset( $_POST['use_optionitem_images'] ) ) {
		$use_optionitem_images = (int) sanitize_text_field( wp_unslash( $_POST['use_optionitem_images'] ) );
		$update_fields = array( 'use_optionitem_images' => $use_optionitem_images ? 1 : 0 );
		$update_formats = array( '%d' );

		if ( isset( $_POST['use_advanced_optionset'] ) && $is_licensed ) {
			$update_fields['use_advanced_optionset'] = (int) sanitize_text_field( wp_unslash( $_POST['use_advanced_optionset'] ) ) ? 1 : 0;
			$update_formats[] = '%d';
		}

		$wpdb->update(
			'ec_product',
			$update_fields,
			array( 'product_id' => $product_id ),
			$update_formats,
			array( '%d' )
		);
	}

	$first_image_url = '';

	foreach ( $sets_raw as $set ) {
		$set_id = isset( $set['id'] ) ? sanitize_text_field( $set['id'] ) : '';
		$image_ids = isset( $set['image_ids'] ) ? $set['image_ids'] : array();

		$sanitized_ids = array();
		if ( is_array( $image_ids ) ) {
			foreach ( $image_ids as $img_id ) {
				$clean = ecv2_sanitize_image_id( $img_id );
				if ( ! empty( $clean ) ) {
					$sanitized_ids[] = $clean;
				}
			}
		}
		$images_csv = implode( ',', $sanitized_ids );

		if ( $set_id === 'basic' ) {
			$wpdb->update(
				'ec_product',
				array( 'product_images' => $images_csv ),
				array( 'product_id' => $product_id ),
				array( '%s' ),
				array( '%d' )
			);

			$new_image1 = ecv2_get_first_displayable_image( $sanitized_ids, $product );
			if ( $new_image1 !== null ) {
				$wpdb->update(
					'ec_product',
					array( 'image1' => $new_image1 ),
					array( 'product_id' => $product_id ),
					array( '%s' ),
					array( '%d' )
				);
				$first_image_url = $new_image1;
				if ( substr( $first_image_url, 0, 4 ) !== 'http' ) {
					$first_image_url = plugins_url( '/wp-easycart-data/products/pics1/' . $first_image_url, EC_PLUGIN_DATA_DIRECTORY );
				}
			} else if ( empty( $sanitized_ids ) ) {
				$wpdb->update(
					'ec_product',
					array( 'image1' => '' ),
					array( 'product_id' => $product_id ),
					array( '%s' ),
					array( '%d' )
				);
			}
		} else if ( is_numeric( $set_id ) && $is_licensed ) {
			$optionitem_id = (int) $set_id;
			$existing = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM ec_optionitemimage WHERE product_id = %d AND optionitem_id = %d",
				$product_id,
				$optionitem_id
			) );

			if ( $existing ) {
				$wpdb->update(
					'ec_optionitemimage',
					array( 'product_images' => $images_csv ),
					array( 'optionitemimage_id' => $existing->optionitemimage_id ),
					array( '%s' ),
					array( '%d' )
				);
			} else if ( ! empty( $images_csv ) ) {
				$wpdb->insert(
					'ec_optionitemimage',
					array(
						'product_id'     => $product_id,
						'optionitem_id'  => $optionitem_id,
						'product_images' => $images_csv,
						'image1'         => '',
						'image2'         => '',
						'image3'         => '',
						'image4'         => '',
						'image5'         => '',
					),
					array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
				);
			}
		}
	}

	if ( empty( $first_image_url ) ) {
		$updated = $wpdb->get_row( $wpdb->prepare( 'SELECT image1 FROM ec_product WHERE product_id = %d', $product_id ) );
		if ( $updated && ! empty( $updated->image1 ) ) {
			$first_image_url = $updated->image1;
			if ( substr( $first_image_url, 0, 4 ) !== 'http' ) {
				$first_image_url = plugins_url( '/wp-easycart-data/products/pics1/' . $first_image_url, EC_PLUGIN_DATA_DIRECTORY );
			}
		}
	}

	wp_cache_flush();
	wp_send_json_success( array(
		'product_id' => $product_id,
		'image_url'  => $first_image_url,
	) );
}

function ecv2_sanitize_image_id( $id ) {
	$id = sanitize_text_field( wp_unslash( $id ) );

	if ( is_numeric( $id ) ) {
		return (string) (int) $id;
	}

	$allowed_prefixes = array( 'image:', 'video:', 'youtube:', 'vimeo:' );
	foreach ( $allowed_prefixes as $prefix ) {
		if ( substr( $id, 0, strlen( $prefix ) ) === $prefix ) {
			return $id;
		}
	}

	if ( preg_match( '/^image[1-5]$/', $id ) ) {
		return $id;
	}

	if ( substr( $id, 0, 8 ) === 'https://' || substr( $id, 0, 7 ) === 'http://' ) {
		return esc_url_raw( $id );
	}

	return '';
}

function ecv2_get_first_displayable_image( $ids, $product ) {
	foreach ( $ids as $id ) {
		if ( substr( $id, 0, 6 ) === 'video:' || substr( $id, 0, 8 ) === 'youtube:' || substr( $id, 0, 6 ) === 'vimeo:' ) {
			continue;
		}

		if ( is_numeric( $id ) ) {
			$attachment = wp_get_attachment_image_src( (int) $id, 'large' );
			if ( $attachment ) {
				return $attachment[0];
			}
			continue;
		}

		if ( substr( $id, 0, 6 ) === 'image:' ) {
			return substr( $id, 6 );
		}

		if ( substr( $id, 0, 8 ) === 'https://' || substr( $id, 0, 7 ) === 'http://' ) {
			return $id;
		}

		if ( preg_match( '/^image([1-5])$/', $id ) ) {
			$field = $id;
			return isset( $product->$field ) ? $product->$field : '';
		}
	}

	return null;
}

function ecv2_is_variant_tracking_enabled() {
	$gate = ecv2_get_variant_tracking_gate();
	return ( isset( $gate['state'] ) && 'enabled' === $gate['state'] );
}

function ecv2_get_variant_tracking_gate() {
	return wp_easycart_admin_pro_gate::evaluate( array(
		'enabled_filter' => 'wp_easycart_admin_ecv2_variant_tracking_enabled',
		'min_version' => '5.8.15',
		'labels' => array(
			'enabled' => __( 'Track per variation', 'wp-easycart' ),
		),
	) );
}
