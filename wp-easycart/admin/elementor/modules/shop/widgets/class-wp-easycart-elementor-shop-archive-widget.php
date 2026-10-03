<?php
/**
 * Shop ( wp_easycart_shop, 6.0.2 ): the store archive for the store page and category or manufacturer templates.
 *
 * Product cards with a toolbar ( result count, sorting ), page numbers, the store's link-based filters ( search, categories,
 * manufacturers, price ranges, option filters ) and the active filters as chips a shopper can remove. With no setup it
 * lists what the page is: the store page all products, a category page that category, a manufacturer page that
 * manufacturer.
 *
 * It reads the store's own URL arguments ( ec_search, filternum, pagenum, perpage, group_id, group_id_0-19, manufacturer,
 * pricepoint, filter_option, menuid … ) through ec_filter, so links from menus, the classic sidebar and the search widgets
 * keep working. Unlike the Store widget from before 6.0.2 ( wp_easycart_store ), which it replaces:
 * - "Featured" narrows only the unfiltered first view, never a search or a filter ( review A6 );
 * - ?model_number= never turns the list into one product ( review A17 );
 * - the page's category stays applied when a shopper adds category filters.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-wp-easycart-elementor-shop-listing-widget.php';

if ( ! class_exists( 'WP_EasyCart_Elementor_Shop_Archive_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Shop_Listing_Widget' ) ) :

	/**
	 * Store archive.
	 */
	class WP_EasyCart_Elementor_Shop_Archive_Widget extends WP_EasyCart_Elementor_Shop_Listing_Widget {

		/**
		 * Help page.
		 *
		 * @var string
		 */
		protected static $ec_help = 'shop';

		/**
		 * URL arguments that are shopper filters ( shown as chips, cleared by "Clear all" ).
		 *
		 * @var array
		 */
		protected static $filter_args = array( 'ec_search', 'pricepoint', 'filter_option', 'group_id', 'manufacturer', 'menuid', 'submenuid', 'subsubmenuid', 'menu', 'submenu', 'subsubmenu', 'menuname', 'submenuname', 'subsubmenuname', 'ec_optionitem_id' );

		/**
		 * Whether the filter groups being drawn show product counts ( Content › Product counts ).
		 *
		 * @var bool
		 */
		protected $ec_filter_counts = true;

		/**
		 * Name.
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_shop';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Shop', 'wp-easycart' );
		}

		/**
		 * Icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-products-archive';
		}

		/**
		 * Keywords.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array( 'shop', 'store page', 'archive', 'product archive', 'category page', 'catalog', 'filters', 'sorting', 'products', 'woocommerce' );
		}

		/**
		 * Saved into post_content: the store shortcode, so search, SEO plugins and a site without Elementor see a store.
		 *
		 * @return string
		 */
		protected function plain_content_shortcode() {
			$settings = $this->get_settings();
			$source   = isset( $settings['ec_shop_source'] ) ? $settings['ec_shop_source'] : 'auto';
			if ( 'category' === $source && ! empty( $settings['ec_shop_category'] ) ) {
				return '[ec_store groupid="' . (int) ( is_array( $settings['ec_shop_category'] ) ? reset( $settings['ec_shop_category'] ) : $settings['ec_shop_category'] ) . '"]';
			}
			if ( 'manufacturer' === $source && ! empty( $settings['ec_shop_manufacturer'] ) ) {
				return '[ec_store manufacturerid="' . (int) ( is_array( $settings['ec_shop_manufacturer'] ) ? reset( $settings['ec_shop_manufacturer'] ) : $settings['ec_shop_manufacturer'] ) . '"]';
			}
			return '[ec_store]';
		}

		/**
		 * Controls.
		 */
		protected function register_controls() {
			$this->start_controls_section(
				'ec_section_shop',
				array(
					'label' => __( 'Products', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->add_control(
				'ec_shop_source',
				array(
					'label'   => __( 'Show', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => 'auto',
					'options' => array(
						'auto'         => __( 'What the page is ( store, category or manufacturer )', 'wp-easycart' ),
						'category'     => __( 'A category I choose', 'wp-easycart' ),
						'manufacturer' => __( 'A manufacturer I choose', 'wp-easycart' ),
						'all'          => __( 'All products', 'wp-easycart' ),
					),
				)
			);
			$this->add_control(
				'ec_shop_source_note',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => esc_html__( 'On the store page this lists every product, on a category page or category template that category, on a manufacturer page that manufacturer. Shoppers narrow it with search, sorting and filters.', 'wp-easycart' ),
					'content_classes' => 'elementor-descriptor',
					'condition'       => array( 'ec_shop_source' => 'auto' ),
				)
			);
			$this->add_control(
				'ec_shop_category',
				array(
					'label'       => __( 'Category', 'wp-easycart' ),
					'type'        => 'wpecajaxselect2',
					'options'     => 'easycart_product_cat',
					'label_block' => true,
					'multiple'    => false,
					'condition'   => array( 'ec_shop_source' => 'category' ),
				)
			);
			$this->add_control(
				'ec_shop_manufacturer',
				array(
					'label'       => __( 'Manufacturer', 'wp-easycart' ),
					'type'        => 'wpecajaxselect2',
					'options'     => 'easycart_product_brand',
					'label_block' => true,
					'multiple'    => false,
					'condition'   => array( 'ec_shop_source' => 'manufacturer' ),
				)
			);
			$this->add_control(
				'ec_status',
				array(
					'label'   => __( 'Products shown', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => '',
					'options' => array(
						''         => __( 'Every product', 'wp-easycart' ),
						'featured' => __( 'Featured products, until the shopper searches or filters', 'wp-easycart' ),
						'on_sale'  => __( 'Only products on sale', 'wp-easycart' ),
						'in_stock' => __( 'Only products in stock', 'wp-easycart' ),
					),
				)
			);
			$this->add_control(
				'ec_per_page',
				array(
					'label'       => __( 'Products per page', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::NUMBER,
					'default'     => '',
					'min'         => 1,
					'max'         => WP_EasyCart_Elementor_Shop_Query::MAX_PER_PAGE,
					'description' => __( 'Leave empty to follow the store ( Settings › Per page ).', 'wp-easycart' ),
				)
			);
			$this->ec_register_columns_controls( '', array( 3, 2, 2 ) );
			$this->ec_register_row_gap_control();
			$this->end_controls_section();

			$this->ec_register_card_controls();

			$this->start_controls_section(
				'ec_section_toolbar',
				array(
					'label' => __( 'Toolbar and filters', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$switch = function ( $id, $label, $initial = 'yes', $condition = array(), $description = '' ) {
				$args = array(
					'label'   => $label,
					'type'    => \Elementor\Controls_Manager::SWITCHER,
					'default' => $initial,
				);
				if ( ! empty( $condition ) ) {
					$args['condition'] = $condition;
				}
				if ( '' !== $description ) {
					$args['description'] = $description;
				}
				$this->add_control( $id, $args );
			};
			$switch( 'ec_show_count', __( 'Number of results', 'wp-easycart' ) );
			$switch( 'ec_show_sorting', __( 'Sorting', 'wp-easycart' ) );
			$sorts = array( '' => __( 'Store default', 'wp-easycart' ) ) + self::sort_labels();
			$this->add_control(
				'ec_default_sort',
				array(
					'label'       => __( 'Sort order to start with', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'default'     => '',
					'options'     => $sorts,
					'description' => __( 'The choices shoppers get are set in Settings › Products › Product lists.', 'wp-easycart' ),
				)
			);
			$switch( 'ec_show_pagination', __( 'Page numbers', 'wp-easycart' ), 'yes', array(), __( 'Off shows only the first page of products.', 'wp-easycart' ) );
			$switch( 'ec_show_active', __( 'Active filters as removable chips', 'wp-easycart' ) );
			$this->add_control(
				'ec_filters_heading',
				array(
					'label'     => __( 'Filters', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$switch( 'ec_show_filters', __( 'Filters beside the products', 'wp-easycart' ), 'yes', array(), __( 'On phones and narrow columns they fold behind a Filters button.', 'wp-easycart' ) );
			$this->add_control(
				'ec_filters_position',
				array(
					'label'     => __( 'Side', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::CHOOSE,
					'default'   => 'left',
					'toggle'    => false,
					'options'   => array(
						'left'  => array(
							'title' => __( 'Left', 'wp-easycart' ),
							'icon'  => 'eicon-h-align-left',
						),
						'right' => array(
							'title' => __( 'Right', 'wp-easycart' ),
							'icon'  => 'eicon-h-align-right',
						),
					),
					'condition' => array( 'ec_show_filters' => 'yes' ),
				)
			);
			$filters_on = array( 'ec_show_filters' => 'yes' );
			$switch( 'ec_filter_search', __( 'Search box', 'wp-easycart' ), 'yes', $filters_on );
			$switch( 'ec_filter_categories', __( 'Categories', 'wp-easycart' ), 'yes', $filters_on );
			$this->add_control(
				'ec_filter_category_ids',
				array(
					'label'       => __( 'Categories to list', 'wp-easycart' ),
					'type'        => 'wpecajaxselect2',
					'options'     => 'easycart_product_cat',
					'label_block' => true,
					'multiple'    => true,
					'description' => __( 'Leave empty to list the subcategories of the page’s category, or the top categories elsewhere.', 'wp-easycart' ),
					'condition'   => array(
						'ec_show_filters'      => 'yes',
						'ec_filter_categories' => 'yes',
					),
				)
			);
			$switch( 'ec_filter_manufacturers', __( 'Manufacturers', 'wp-easycart' ), 'yes', $filters_on );
			$switch( 'ec_filter_price', __( 'Price ranges', 'wp-easycart' ), 'yes', $filters_on, __( 'The ranges set in Settings › Price points.', 'wp-easycart' ) );
			$this->add_control(
				'ec_filter_option_ids',
				array(
					'label'       => __( 'Filter by options', 'wp-easycart' ),
					'type'        => 'wpecajaxselect2',
					'options'     => 'easycart_product_optionsets',
					'label_block' => true,
					'multiple'    => true,
					'description' => __( 'Choose option sets such as colour or size; each becomes a filter group.', 'wp-easycart' ),
					'condition'   => $filters_on,
				)
			);
			$switch( 'ec_filter_show_counts', __( 'Product counts', 'wp-easycart' ), 'yes', $filters_on, __( 'The number of products beside each category, manufacturer and price range.', 'wp-easycart' ) );
			$labels   = self::filter_group_labels();
			$repeater = new \Elementor\Repeater();
			$repeater->add_control(
				'filter',
				array(
					'label'   => __( 'Filter', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => 'search',
					'options' => $labels,
				)
			);
			$this->add_control(
				'ec_filter_order',
				array(
					'label'         => __( 'Order of the filters', 'wp-easycart' ),
					'type'          => \Elementor\Controls_Manager::REPEATER,
					'fields'        => $repeater->get_controls(),
					'default'       => array(
						array( 'filter' => 'search' ),
						array( 'filter' => 'categories' ),
						array( 'filter' => 'manufacturers' ),
						array( 'filter' => 'price' ),
						array( 'filter' => 'options' ),
					),
					'title_field'   => '{{{ ( ' . wp_json_encode( $labels ) . ' )[ filter ] || filter }}}',
					'prevent_empty' => false,
					'separator'     => 'before',
					'condition'     => $filters_on,
				)
			);
			$this->add_control(
				'ec_filter_order_note',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => esc_html__( 'Drag to change the order. The switches above show or hide each filter; one left out of this list shows at the end.', 'wp-easycart' ),
					'content_classes' => 'elementor-descriptor',
					'condition'       => $filters_on,
				)
			);
			$this->end_controls_section();

			$this->ec_register_card_style_controls();
			$this->ec_register_toolbar_style_controls();
			$this->ec_register_pagination_style_controls( array( 'ec_show_pagination' => 'yes' ) );
		}

		/**
		 * Style sections for the toolbar, filters and chips.
		 */
		protected function ec_register_toolbar_style_controls() {
			$style = \Elementor\Controls_Manager::TAB_STYLE;
			$this->start_controls_section(
				'ec_style_toolbar',
				array(
					'label' => __( 'Toolbar', 'wp-easycart' ),
					'tab'   => $style,
				)
			);
			$this->ec_typography( 'toolbar_typography', '{{WRAPPER}} .wpec-shop__toolbar', 'text' );
			$this->add_control(
				'toolbar_color',
				array(
					'label'     => __( 'Text colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-shop__toolbar, {{WRAPPER}} .wpec-shop__toolbar .wpec-shop__count' => 'color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'toolbar_count_color',
				array(
					'label'     => __( 'Number of results colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-shop__toolbar .wpec-shop__count' => 'color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'sort_heading',
				array(
					'label'     => __( 'Sort menu and Filters button', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$menu = '{{WRAPPER}} .wpec-shop__sort select, {{WRAPPER}} .wpec-shop__toolbar .wpec-shop__filters-toggle';
			$this->add_control(
				'sort_color',
				array(
					'label'     => __( 'Text colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $menu => 'color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'sort_background',
				array(
					'label'     => __( 'Background colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $menu => 'background-color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'sort_border_color',
				array(
					'label'     => __( 'Border colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $menu => 'border-color: {{VALUE}};' ),
				)
			);
			$this->add_responsive_control(
				'sort_radius',
				array(
					'label'      => __( 'Corner radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 30,
						),
					),
					'selectors'  => array( $menu => 'border-radius: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_control(
				'sort_label_hide',
				array(
					'label'        => __( 'Hide the "Sort by" label', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'default'      => '',
					'return_value' => 'yes',
					'description'  => __( 'Screen readers still read it.', 'wp-easycart' ),
					'prefix_class' => 'wpec-sort-label-hide-',
				)
			);
			$this->add_responsive_control(
				'toolbar_spacing',
				array(
					'label'      => __( 'Space below', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 80,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-shop__toolbar' => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_control(
				'chip_heading',
				array(
					'label'     => __( 'Active filters', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->add_control(
				'chip_color',
				array(
					'label'     => __( 'Text colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-chip' => 'color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'chip_background',
				array(
					'label'     => __( 'Background colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-chip' => 'background-color: {{VALUE}};' ),
				)
			);
			$this->end_controls_section();

			$this->start_controls_section(
				'ec_style_filters',
				array(
					'label'     => __( 'Filters', 'wp-easycart' ),
					'tab'       => $style,
					'condition' => array( 'ec_show_filters' => 'yes' ),
				)
			);
			$this->add_responsive_control(
				'filters_width',
				array(
					'label'      => __( 'Width', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px', '%' ),
					'range'      => array(
						'px' => array(
							'min' => 160,
							'max' => 480,
						),
						'%'  => array(
							'min' => 10,
							'max' => 50,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-shop' => '--wpec-filters-width: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_control(
				'filters_background',
				array(
					'label'     => __( 'Background colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-shop__filters' => 'background-color: {{VALUE}};' ),
				)
			);
			$this->add_responsive_control(
				'filters_padding',
				array(
					'label'      => __( 'Padding', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em' ),
					'selectors'  => array( '{{WRAPPER}} .wpec-shop__filters' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->ec_typography( 'filters_title_typography', '{{WRAPPER}} .wpec-shop__filter-title', 'primary' );
			$this->add_control(
				'filters_title_color',
				array(
					'label'     => __( 'Group title colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-shop__filter-title' => 'color: {{VALUE}};' ),
				)
			);
			$this->add_responsive_control(
				'filters_title_spacing',
				array(
					'label'      => __( 'Space below group titles', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 40,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-shop__filter-title' => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->ec_typography( 'filters_link_typography', '{{WRAPPER}} .wpec-shop__filter-list', 'text' );
			$this->add_control(
				'filters_link_color',
				array(
					'label'     => __( 'Link colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					/* The counts follow the links unless Product count colour says otherwise. */
					'selectors' => array(
						'{{WRAPPER}} .wpec-shop__filter-list a' => 'color: {{VALUE}};',
						'{{WRAPPER}} .wpec-shop__filters' => '--wpec-filter-count-color: {{VALUE}};',
					),
				)
			);
			$this->add_control(
				'filters_active_color',
				array(
					'label'     => __( 'Chosen and hover colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-shop__filter-list a:hover, {{WRAPPER}} .wpec-shop__filter-list a[aria-current], {{WRAPPER}} .wpec-shop__filter-list a:hover .wpec-shop__filter-count, {{WRAPPER}} .wpec-shop__filter-list a[aria-current] .wpec-shop__filter-count' => 'color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'filters_active_background',
				array(
					'label'     => __( 'Chosen and hover background', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-shop__filter-list a:hover, {{WRAPPER}} .wpec-shop__filter-list a[aria-current]' => 'background-color: {{VALUE}};' ),
				)
			);
			$this->add_responsive_control(
				'filters_item_padding',
				array(
					'label'      => __( 'Link padding', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em' ),
					'selectors'  => array( '{{WRAPPER}} .wpec-shop__filter-list a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->add_responsive_control(
				'filters_item_radius',
				array(
					'label'      => __( 'Link corner radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 20,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-shop__filter-list a' => 'border-radius: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_control(
				'filters_chosen_mark',
				array(
					'label'                => __( 'Chosen filter mark', 'wp-easycart' ),
					'type'                 => \Elementor\Controls_Manager::SELECT,
					'default'              => '',
					'options'              => array(
						''     => __( 'Check mark', 'wp-easycart' ),
						'none' => __( 'None', 'wp-easycart' ),
					),
					'selectors_dictionary' => array(
						'none' => 'content: none;',
					),
					'selectors'            => array( '{{WRAPPER}} .wpec-shop__filter-list a[aria-current] .wpec-shop__filter-label::before' => '{{VALUE}}' ),
				)
			);

			$this->add_control(
				'filters_count_heading',
				array(
					'label'     => __( 'Product counts', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
					'condition' => array( 'ec_filter_show_counts' => 'yes' ),
				)
			);
			$count = '{{WRAPPER}} .wpec-shop__filter-count';
			$this->ec_typography( 'filters_count_typography', $count, 'text' );
			$this->add_control(
				'filters_count_color',
				array(
					'label'     => __( 'Colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $count => 'color: {{VALUE}};' ),
					'condition' => array( 'ec_filter_show_counts' => 'yes' ),
				)
			);
			$this->add_control(
				'filters_count_background',
				array(
					'label'     => __( 'Background colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $count => 'background-color: {{VALUE}}; padding: 0.1em 0.55em; border-radius: var( --wpec-filter-count-radius, 999px );' ),
					'condition' => array( 'ec_filter_show_counts' => 'yes' ),
				)
			);
			$this->add_control(
				'filters_count_radius',
				array(
					'label'      => __( 'Corner radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 20,
						),
					),
					'selectors'  => array( '{{WRAPPER}}' => '--wpec-filter-count-radius: {{SIZE}}{{UNIT}};' ),
					'condition'  => array(
						'ec_filter_show_counts'     => 'yes',
						'filters_count_background!' => '',
					),
				)
			);

			$this->add_control(
				'filters_group_heading',
				array(
					'label'     => __( 'Filter groups', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->add_responsive_control(
				'filters_group_spacing',
				array(
					'label'      => __( 'Space between groups', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 80,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-shop__filters' => '--wpec-filter-gap: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_control(
				'filters_group_background',
				array(
					'label'     => __( 'Background colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-shop__filters > .wpec-shop__filter' => 'background-color: {{VALUE}};' ),
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Border::get_type(),
				array(
					'name'     => 'filters_group_border',
					'selector' => '{{WRAPPER}} .wpec-shop__filters > .wpec-shop__filter',
				)
			);
			$this->add_responsive_control(
				'filters_group_radius',
				array(
					'label'      => __( 'Corner radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', '%' ),
					'selectors'  => array( '{{WRAPPER}} .wpec-shop__filters > .wpec-shop__filter' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->add_responsive_control(
				'filters_group_padding',
				array(
					'label'      => __( 'Padding', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em' ),
					'selectors'  => array( '{{WRAPPER}} .wpec-shop__filters > .wpec-shop__filter' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Box_Shadow::get_type(),
				array(
					'name'     => 'filters_group_shadow',
					'selector' => '{{WRAPPER}} .wpec-shop__filters > .wpec-shop__filter',
				)
			);
			$this->add_control(
				'filters_divider',
				array(
					'label'     => __( 'Divider between groups', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'default'   => '',
					'options'   => array(
						''       => __( 'None', 'wp-easycart' ),
						'solid'  => __( 'Solid', 'wp-easycart' ),
						'dashed' => __( 'Dashed', 'wp-easycart' ),
						'dotted' => __( 'Dotted', 'wp-easycart' ),
						'double' => __( 'Double', 'wp-easycart' ),
					),
					'selectors' => array( '{{WRAPPER}} .wpec-shop__filters > * + *' => 'border-top: var( --wpec-filter-divider-width, 1px ) {{VALUE}} var( --wpec-filter-divider-color, var( --wpec-border-color, #e2e2e2 ) ); padding-top: var( --wpec-filter-gap, 24px );' ),
					'separator' => 'before',
				)
			);
			$this->add_control(
				'filters_divider_width',
				array(
					'label'      => __( 'Divider width', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 1,
							'max' => 10,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-shop__filters' => '--wpec-filter-divider-width: {{SIZE}}{{UNIT}};' ),
					'condition'  => array( 'filters_divider!' => '' ),
				)
			);
			$this->add_control(
				'filters_divider_color',
				array(
					'label'     => __( 'Divider colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-shop__filters' => '--wpec-filter-divider-color: {{VALUE}};' ),
					'condition' => array( 'filters_divider!' => '' ),
				)
			);

			$this->add_control(
				'filters_search_heading',
				array(
					'label'     => __( 'Search box', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
					'condition' => array( 'ec_filter_search' => 'yes' ),
				)
			);
			$input  = '{{WRAPPER}} .wpec-shop__search input';
			$button = '{{WRAPPER}} .wpec-shop__search button';
			$this->ec_typography( 'filters_search_typography', $input, 'text' );
			$color = function ( $name, $label, $selectors ) {
				$this->add_control(
					$name,
					array(
						'label'     => $label,
						'type'      => \Elementor\Controls_Manager::COLOR,
						'selectors' => $selectors,
						'condition' => array( 'ec_filter_search' => 'yes' ),
					)
				);
			};
			$color( 'filters_search_color', __( 'Text colour', 'wp-easycart' ), array( $input => 'color: {{VALUE}};' ) );
			$color( 'filters_search_placeholder', __( 'Placeholder colour', 'wp-easycart' ), array( $input . '::placeholder' => 'color: {{VALUE}}; opacity: 1;' ) );
			$color( 'filters_search_background', __( 'Background colour', 'wp-easycart' ), array( $input => 'background-color: {{VALUE}};' ) );
			$color( 'filters_search_border', __( 'Border colour', 'wp-easycart' ), array( $input => 'border-color: {{VALUE}};' ) );
			$this->add_responsive_control(
				'filters_search_radius',
				array(
					'label'      => __( 'Corner radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 30,
						),
					),
					'selectors'  => array(
						$input  => 'border-radius: {{SIZE}}{{UNIT}} 0 0 {{SIZE}}{{UNIT}};',
						$button => 'border-radius: 0 {{SIZE}}{{UNIT}} {{SIZE}}{{UNIT}} 0;',
					),
					'condition'  => array( 'ec_filter_search' => 'yes' ),
				)
			);
			$this->start_controls_tabs( 'filters_search_button_tabs', array( 'condition' => array( 'ec_filter_search' => 'yes' ) ) );
			$this->start_controls_tab( 'filters_search_button_normal', array( 'label' => __( 'Button', 'wp-easycart' ) ) );
			$color( 'filters_search_button_color', __( 'Icon colour', 'wp-easycart' ), array( $button => 'color: {{VALUE}};' ) );
			$color( 'filters_search_button_background', __( 'Background colour', 'wp-easycart' ), array( $button => 'background-color: {{VALUE}}; border-color: {{VALUE}};' ) );
			$this->end_controls_tab();
			$this->start_controls_tab( 'filters_search_button_hover', array( 'label' => __( 'Hover', 'wp-easycart' ) ) );
			$color( 'filters_search_button_hover_color', __( 'Icon colour', 'wp-easycart' ), array( $button . ':hover, ' . $button . ':focus-visible' => 'color: {{VALUE}};' ) );
			$color( 'filters_search_button_hover_background', __( 'Background colour', 'wp-easycart' ), array( $button . ':hover, ' . $button . ':focus-visible' => 'background-color: {{VALUE}}; border-color: {{VALUE}};' ) );
			$this->end_controls_tab();
			$this->end_controls_tabs();
			$this->end_controls_section();
		}

		/**
		 * The sort choices ( ec_filter's numbers ) with the store's own wording.
		 *
		 * @return array number => label.
		 */
		public static function sort_labels() {
			$keys   = array(
				'0' => array( 'sort_default', 'Default Sorting' ),
				'1' => array( 'sort_by_price_low', 'Price Low-High' ),
				'2' => array( 'sort_by_price_high', 'Price High-Low' ),
				'3' => array( 'sort_by_title_a', 'Title A-Z' ),
				'4' => array( 'sort_by_title_z', 'Title Z-A' ),
				'5' => array( 'sort_by_newest', 'Newest' ),
				'8' => array( 'sort_by_oldest', 'Oldest' ),
				'6' => array( 'sort_by_rating', 'Best Rating' ),
				'7' => array( 'sort_by_most_viewed', 'Most Viewed' ),
			);
			$labels = array();
			foreach ( $keys as $number => $key ) {
				$labels[ (string) $number ] = WP_EasyCart_Elementor_Shop::store_text( 'sort_bar', $key[0], $key[1] );
			}
			return $labels;
		}

		/**
		 * Whether the shopper searched or filtered ( a URL filter is set ).
		 *
		 * @return bool
		 */
		public static function url_filters_active() {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only storefront filters.
			foreach ( array( 'ec_search', 'menuid', 'submenuid', 'subsubmenuid', 'manufacturer', 'pricepoint', 'group_id', 'filter_option', 'ec_optionitem_id' ) as $arg ) {
				if ( isset( $_GET[ $arg ] ) && '' !== trim( sanitize_text_field( wp_unslash( $_GET[ $arg ] ) ) ) ) {
					return true;
				}
			}
			for ( $i = 0; $i < 20; $i++ ) {
				if ( isset( $_GET[ 'group_id_' . $i ] ) && '' !== trim( sanitize_text_field( wp_unslash( $_GET[ 'group_id_' . $i ] ) ) ) ) {
					return true;
				}
			}
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			return false;
		}

		/**
		 * Every filter argument ( for "Clear all" ).
		 *
		 * @return array
		 */
		protected static function all_filter_args() {
			$args = array_merge( self::$filter_args, array( 'pagenum' ) );
			for ( $i = 0; $i < 20; $i++ ) {
				$args[] = 'group_id_' . $i;
			}
			return $args;
		}

		/**
		 * A URL argument as a list of positive integers.
		 *
		 * @param string $arg Argument.
		 * @return int[]
		 */
		protected static function url_ids( $arg ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only storefront filter.
			return isset( $_GET[ $arg ] ) ? WP_EasyCart_Elementor_Shop_Query::ids( sanitize_text_field( wp_unslash( $_GET[ $arg ] ) ) ) : array();
		}

		/**
		 * The page's category and manufacturer for these settings.
		 *
		 * @param array $settings Widget settings.
		 * @return array array( category id, manufacturer id ).
		 */
		protected function ec_scope( $settings ) {
			$source = isset( $settings['ec_shop_source'] ) ? $settings['ec_shop_source'] : 'auto';
			if ( 'category' === $source ) {
				$id = isset( $settings['ec_shop_category'] ) ? $settings['ec_shop_category'] : 0;
				return array( (int) ( is_array( $id ) ? reset( $id ) : $id ), 0 );
			}
			if ( 'manufacturer' === $source ) {
				$id = isset( $settings['ec_shop_manufacturer'] ) ? $settings['ec_shop_manufacturer'] : 0;
				return array( 0, (int) ( is_array( $id ) ? reset( $id ) : $id ) );
			}
			if ( 'all' === $source || ! function_exists( 'wp_easycart_elementor_context' ) ) {
				return array( 0, 0 );
			}
			$context = wp_easycart_elementor_context();

			// A category from the URL ( ?group_id= ) is the shopper's filter, handled by ec_filter; only the page's own counts here.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only storefront filter.
			if ( ! isset( $_GET['group_id'] ) ) {
				$category = $context->category( array( 'allow_sample' => false ) );
				if ( $category && isset( $category->category_id ) ) {
					return array( (int) $category->category_id, 0 );
				}
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only storefront filter.
			if ( ! isset( $_GET['manufacturer'] ) ) {
				$manufacturer = $context->manufacturer();
				if ( $manufacturer && isset( $manufacturer->manufacturer_id ) ) {
					return array( 0, (int) $manufacturer->manufacturer_id );
				}
			}
			/**
			 * The category and manufacturer a Shop widget set to "What the page is" lists ( 0 = none ).
			 *
			 * @since 6.0.2
			 *
			 * @param array $scope    array( category id, manufacturer id ).
			 * @param array $settings Widget settings.
			 */
			$scope = apply_filters( 'wp_easycart_elementor_shop_scope', array( 0, 0 ), $settings );
			return ( is_array( $scope ) && 2 === count( $scope ) ) ? array( (int) $scope[0], (int) $scope[1] ) : array( 0, 0 );
		}

		/**
		 * Runs the store query for the page and the URL filters.
		 *
		 * @param array $settings Widget settings.
		 * @param int   $category Page category ( 0 = none ).
		 * @param int   $maker    Page manufacturer ( 0 = none ).
		 * @return array products, total, pages, page, per_page, filter ( ec_filter ).
		 */
		protected function ec_query( $settings, $category, $maker ) {
			/* ec_filter also reads the options a classic [ec_store] earlier on the page left behind; this list is its own. */
			$saved_options = isset( $GLOBALS['ec_store_shortcode_options'] ) ? $GLOBALS['ec_store_shortcode_options'] : null;
			unset( $GLOBALS['ec_store_shortcode_options'] );

			$filter                  = new ec_filter();
			$filter->model_number    = '';
			$filter->product_only    = false;
			$filter->show_on_startup = false;
			$status                  = isset( $settings['ec_status'] ) ? (string) $settings['ec_status'] : '';
			if ( 'featured' === $status && self::url_filters_active() ) {
				$status = ''; /* A6: featured is the first view only. */
			}
			$filter->product_status  = in_array( $status, array( 'featured', 'on_sale', 'in_stock' ), true ) ? $status : 'all';
			$filter->productids      = '';
			$filter->groupids        = '';
			$filter->manufacturerids = '';
			if ( isset( $settings['ec_default_sort'] ) && '' !== (string) $settings['ec_default_sort'] ) {
				$filter->default_first_filter = (int) $settings['ec_default_sort'];
				$filter->current_filter       = $filter->get_current_filter();
			}

			$where = $filter->get_where_query();
			if ( $category ) {
				$where .= ' AND product.product_id IN ( SELECT wpec_ci.product_id FROM ec_categoryitem AS wpec_ci WHERE wpec_ci.category_id = ' . (int) $category . ' )';
			}
			if ( $maker ) {
				$where .= ' AND product.manufacturer_id = ' . (int) $maker;
			}
			$order = $filter->get_order_by_query( null );
			$joins = $filter->get_extra_left_joins();
			$items = $filter->get_optionitems_filters();

			if ( null !== $saved_options ) {
				$GLOBALS['ec_store_shortcode_options'] = $saved_options; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, WordPress.WP.GlobalVariablesOverride.Prohibited -- EasyCart's own global, put back as it was.
			}

			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only paging.
			$per_page = WP_EasyCart_Elementor_Shop_Query::clamp( isset( $settings['ec_per_page'] ) ? $settings['ec_per_page'] : '', 1, WP_EasyCart_Elementor_Shop_Query::MAX_PER_PAGE, 0 );
			if ( ! $per_page || isset( $_GET['perpage'] ) ) {
				$per_page = ( isset( $filter->perpage->selected ) && (int) $filter->perpage->selected > 0 ) ? (int) $filter->perpage->selected : 12;
			}
			$paging = ! isset( $settings['ec_show_pagination'] ) || 'yes' === $settings['ec_show_pagination'];
			// As the Products widget: a huge page number never reaches the LIMIT.
			$page = ( $paging && isset( $_GET['pagenum'] ) ) ? WP_EasyCart_Elementor_Shop_Query::clamp( absint( $_GET['pagenum'] ), 1, 1000, 1 ) : 1;
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			$limit = ' LIMIT ' . ( ( $page - 1 ) * $per_page ) . ', ' . $per_page;

			$db   = new ec_db();
			$rows = $db->get_product_list( $where, $order, $limit, ( isset( $GLOBALS['ec_cart_data']->ec_cart_id ) ? $GLOBALS['ec_cart_data']->ec_cart_id : '' ), 'wpeasycart-elementor-shop', $items, $joins );
			$rows = is_array( $rows ) ? $rows : array();

			$products = array();
			foreach ( $rows as $index => $row ) {
				$products[] = new ec_product( $row, 1, 0, 1, $index );
			}
			$total = ! empty( $rows ) ? (int) $rows[0]['product_count'] : 0;
			return array(
				'products' => $products,
				'total'    => $total,
				'pages'    => $paging ? (int) ceil( $total / $per_page ) : 1,
				'page'     => $page,
				'per_page' => $per_page,
				'filter'   => $filter,
			);
		}

		/**
		 * Draws the widget.
		 */
		protected function render() {
			$settings  = $this->get_settings_for_display();
			$is_editor = $this->ec_is_editor();
			if ( ! $is_editor && function_exists( 'wp_easycart_product_details_no_cache' ) ) {
				wp_easycart_product_details_no_cache(); /* As [ec_store]: the list follows the URL, the shopper's role and session. */
			}
			if ( WP_EasyCart_Elementor_Shop::restricted() ) {
				$this->ec_render_restricted();
				return;
			}
			if ( ! class_exists( 'ec_filter' ) || ! class_exists( 'ec_db' ) ) {
				return;
			}
			WP_EasyCart_Elementor_Shop::pixel_base_code();
			list( $category, $maker ) = $this->ec_scope( $settings );
			$result                   = $this->ec_query( $settings, $category, $maker );
			$options                  = WP_EasyCart_Elementor_Shop_Card::options_from_settings( $settings );
			$options['eager']         = WP_EasyCart_Elementor_Shop_Query::clamp( isset( $settings['ec_columns'] ) ? $settings['ec_columns'] : 3, 1, 6, 3 );
			$filters                  = ( ! isset( $settings['ec_show_filters'] ) || 'yes' === $settings['ec_show_filters'] );
			$side                     = ( isset( $settings['ec_filters_position'] ) && 'right' === $settings['ec_filters_position'] ) ? 'right' : 'left';
			$panel_id                 = 'wpec-shop-filters-' . $this->get_id();

			// Inside an EasyCart template the page sends its list events itself.
			if ( ! $is_editor && ! WP_EasyCart_Elementor_Shop::template_rendering() ) {
				$this->ec_track( $result, $category, $maker );
			}

			echo '<div class="wpec-el wpec-shop wpec-products' . ( $filters ? ' wpec-shop--filters wpec-shop--filters-' . esc_attr( $side ) : '' ) . '" data-wpec-shop>';
			if ( apply_filters( 'wp_easycart_catalog_display', get_option( 'ec_option_display_as_catalog' ) ) && '' !== trim( (string) get_option( 'ec_option_vacation_mode_banner_text' ) ) ) {
				echo '<p class="wpec-shop__banner">' . esc_html( wp_strip_all_tags( wp_easycart_language()->convert_text( get_option( 'ec_option_vacation_mode_banner_text' ) ) ) ) . '</p>';
			}
			echo '<div class="wpec-shop__layout">';
			if ( $filters ) {
				echo '<aside class="wpec-shop__filters" id="' . esc_attr( $panel_id ) . '" aria-label="' . esc_attr( WP_EasyCart_Elementor_Shop::store_text( 'product_page', 'product_page_filters', 'Filters' ) ) . '">';
				$this->ec_render_filters( $settings, $category, $maker, $result['filter'] );
				echo '</aside>';
			}
			echo '<div class="wpec-shop__main">';
			$this->ec_render_toolbar( $settings, $result, $filters ? $panel_id : '' );
			if ( ! isset( $settings['ec_show_active'] ) || 'yes' === $settings['ec_show_active'] ) {
				$this->ec_render_chips();
			}
			if ( empty( $result['products'] ) ) {
				echo '<div class="wpec-shop__empty"><p>' . esc_html( WP_EasyCart_Elementor_Shop::store_text( 'product_page', 'product_no_results', 'No Results Found' ) ) . '</p>';
				if ( self::url_filters_active() ) {
					echo '<a class="wpec-button wpec-button--secondary" href="' . esc_url( WP_EasyCart_Elementor_Shop::current_url( self::all_filter_args() ) ) . '">' . esc_html( WP_EasyCart_Elementor_Shop::store_text( 'product_page', 'product_page_clear_filters', 'Clear Filters' ) ) . '</a>';
				}
				echo '</div>';
			} else {
				echo '<ul class="wpec-products__grid" role="list">';
				WP_EasyCart_Elementor_Shop_Card::render_list( $result['products'], $options );
				echo '</ul>';
				self::ec_render_pagination(
					$result['page'],
					$result['pages'],
					function ( $page ) {
						$url = WP_EasyCart_Elementor_Shop::current_url( array( 'pagenum' ) );
						return ( 1 === (int) $page ) ? $url : add_query_arg( 'pagenum', (int) $page, $url );
					}
				);
			}
			echo '</div></div>';
			$this->ec_render_live_region();
			echo '</div>';
		}

		/**
		 * What a visitor the store keeps out sees ( Settings › Products › Who can view the store ).
		 */
		protected function ec_render_restricted() {
			/* 6.0.2: what to do next: sign in, or ( signed in ) the account has no access yet ( as ec_product_restricted.php ). */
			$signed_in = ! empty( $GLOBALS['ec_user']->user_id );
			$message   = $signed_in
				? WP_EasyCart_Elementor_Shop::store_text( 'product_page', 'product_page_restricted_no_access', 'Your account does not have access to the store yet.' )
				: WP_EasyCart_Elementor_Shop::store_text( 'product_page', 'product_page_restricted_signed_out', 'Please sign in to see the store.' );
			echo '<div class="wpec-shop wpec-shop--restricted"><p>' . esc_html( $message ) . '</p>';
			$account = WP_EasyCart_Elementor_Shop::account_page_url();
			if ( '' !== $account && empty( $GLOBALS['ec_user']->user_id ) ) {
				echo '<a class="wpec-button" href="' . esc_url( $account ) . '">' . esc_html( WP_EasyCart_Elementor_Shop::text( 'shop_sign_in', 'Sign in' ) ) . '</a>';
			}
			echo '</div>';
		}

		/**
		 * Result count and sorting.
		 *
		 * @param array  $settings Widget settings.
		 * @param array  $result   Query result.
		 * @param string $panel_id Filters panel id ( '' without filters ).
		 */
		protected function ec_render_toolbar( $settings, $result, $panel_id ) {
			$count   = ( ! isset( $settings['ec_show_count'] ) || 'yes' === $settings['ec_show_count'] ) && $result['total'] > 0;
			$sorting = ( ! isset( $settings['ec_show_sorting'] ) || 'yes' === $settings['ec_show_sorting'] ) && $result['total'] > 1;
			if ( ! $count && ! $sorting && '' === $panel_id ) {
				return;
			}
			echo '<div class="wpec-shop__toolbar">';
			if ( '' !== $panel_id ) {
				echo '<button type="button" class="wpec-shop__filters-toggle" aria-expanded="false" aria-controls="' . esc_attr( $panel_id ) . '"><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M4 6h16M7 12h10M10 18h4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>' . esc_html( WP_EasyCart_Elementor_Shop::store_text( 'product_page', 'product_page_filters', 'Filters' ) ) . '</button>';
			}
			if ( $count ) {
				$shown = count( $result['products'] );
				$start = ( ( $result['page'] - 1 ) * $result['per_page'] ) + 1;
				if ( $shown < $result['total'] ) {
					$text = WP_EasyCart_Elementor_Shop::fill(
						WP_EasyCart_Elementor_Shop::text( 'shop_result_count', 'Showing [start]–[end] of [total] products' ),
						array(
							'start' => $start,
							'end'   => $start + $shown - 1,
							'total' => $result['total'],
						)
					);
				} else {
					$text = WP_EasyCart_Elementor_Shop::fill( ( 1 === $result['total'] ) ? WP_EasyCart_Elementor_Shop::text( 'shop_result_one', 'Showing 1 product' ) : WP_EasyCart_Elementor_Shop::text( 'shop_result_all', 'Showing all [total] products' ), array( 'total' => $result['total'] ) );
				}
				echo '<p class="wpec-shop__count">' . esc_html( $text ) . '</p>';
			}
			if ( $sorting ) {
				$this->ec_render_sort( $result['filter'] );
			}
			echo '</div>';
		}

		/**
		 * The sort menu: a GET form that keeps every other URL argument ( plain permalinks, WPML, filters ).
		 *
		 * @param ec_filter $filter The list's filter ( current sort ).
		 */
		protected function ec_render_sort( $filter ) {
			$current = (string) (int) $filter->current_filter;
			$labels  = self::sort_labels();
			$action  = WP_EasyCart_Elementor_Shop::current_url( array( 'filternum', 'pagenum' ) );
			$field   = 'wpec-sort-' . $this->get_id();
			echo '<form class="wpec-shop__sort" method="get" action="' . esc_url( $action ) . '">';
			if ( function_exists( 'wp_easycart_print_form_query_inputs' ) ) {
				wp_easycart_print_form_query_inputs( $action, array( 'filternum', 'pagenum' ) );
			}
			echo '<label for="' . esc_attr( $field ) . '">' . esc_html( WP_EasyCart_Elementor_Shop::text( 'shop_sort_by', 'Sort by' ) ) . '</label>';
			echo '<select id="' . esc_attr( $field ) . '" name="filternum" data-wpec-sort>';
			foreach ( $labels as $number => $label ) {
				if ( get_option( 'ec_option_product_filter_' . $number ) || $current === (string) $number ) {
					echo '<option value="' . esc_attr( $number ) . '"' . selected( $current, (string) $number, false ) . '>' . esc_html( $label ) . '</option>';
				}
			}
			echo '</select>';
			echo '<button type="submit" class="wpec-button wpec-button--secondary wpec-shop__sort-submit">' . esc_html( WP_EasyCart_Elementor_Shop::text( 'shop_sort_apply', 'Sort' ) ) . '</button>';
			echo '</form>';
		}

		/**
		 * The active filters as chips, each a link that removes it, and "Clear all".
		 */
		protected function ec_render_chips() {
			global $wpdb;
			$chips = array();
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only storefront filters.
			if ( isset( $_GET['ec_search'] ) && '' !== trim( sanitize_text_field( wp_unslash( $_GET['ec_search'] ) ) ) ) {
				$chips[] = array(
					WP_EasyCart_Elementor_Shop::fill( WP_EasyCart_Elementor_Shop::text( 'shop_chip_search', 'Search: [term]' ), array( 'term' => sanitize_text_field( wp_unslash( $_GET['ec_search'] ) ) ) ),
					WP_EasyCart_Elementor_Shop::current_url( array( 'ec_search', 'pagenum' ) ),
				);
			}
			if ( isset( $_GET['pricepoint'] ) && absint( $_GET['pricepoint'] ) && isset( $GLOBALS['ec_pricepoints'] ) ) {
				$pricepoint = $GLOBALS['ec_pricepoints']->get_pricepoint( absint( $_GET['pricepoint'] ) );
				if ( $pricepoint ) {
					$chips[] = array( self::pricepoint_label( $pricepoint ), WP_EasyCart_Elementor_Shop::current_url( array( 'pricepoint', 'pagenum' ) ) );
				}
			}
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			$maker = self::url_ids( 'manufacturer' );
			if ( ! empty( $maker ) && isset( $GLOBALS['ec_manufacturers'] ) ) {
				$row = $GLOBALS['ec_manufacturers']->get_manufacturer( $maker[0] );
				if ( $row && isset( $row->name ) ) {
					$chips[] = array( wp_strip_all_tags( html_entity_decode( wp_easycart_language()->convert_text( $row->name ), ENT_QUOTES, 'UTF-8' ) ), WP_EasyCart_Elementor_Shop::current_url( array( 'manufacturer', 'pagenum' ) ) );
				}
			}
			foreach ( self::url_ids( 'group_id' ) as $category_id ) {
				$row = isset( $GLOBALS['ec_categories'] ) ? $GLOBALS['ec_categories']->get_category( $category_id ) : false;
				if ( $row ) {
					$chips[] = array( WP_EasyCart_Elementor_Shop::category_name( $row ), WP_EasyCart_Elementor_Shop::current_url( array( 'group_id', 'pagenum' ) ) );
				}
			}
			for ( $i = 0; $i < 20; $i++ ) {
				$ids = self::url_ids( 'group_id_' . $i );
				foreach ( $ids as $category_id ) {
					$row = isset( $GLOBALS['ec_categories'] ) ? $GLOBALS['ec_categories']->get_category( $category_id ) : false;
					if ( ! $row ) {
						continue;
					}
					$rest    = array_diff( $ids, array( $category_id ) );
					$url     = WP_EasyCart_Elementor_Shop::current_url( array( 'group_id_' . $i, 'pagenum' ) );
					$chips[] = array( WP_EasyCart_Elementor_Shop::category_name( $row ), empty( $rest ) ? $url : add_query_arg( 'group_id_' . $i, implode( ',', $rest ), $url ) );
				}
			}
			$items = self::url_ids( 'filter_option' );
			if ( ! empty( $items ) ) {
				$names = $wpdb->get_results( 'SELECT optionitem_id, optionitem_name FROM ec_optionitem WHERE optionitem_id IN (' . implode( ',', $items ) . ')', OBJECT_K ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- the ids are integers ( WP_EasyCart_Elementor_Shop_Query::ids() ).
				foreach ( $items as $item_id ) {
					if ( ! isset( $names[ $item_id ] ) ) {
						continue;
					}
					$rest    = array_diff( $items, array( $item_id ) );
					$url     = WP_EasyCart_Elementor_Shop::current_url( array( 'filter_option', 'pagenum' ) );
					$chips[] = array( wp_strip_all_tags( html_entity_decode( wp_easycart_language()->convert_text( $names[ $item_id ]->optionitem_name ), ENT_QUOTES, 'UTF-8' ) ), empty( $rest ) ? $url : add_query_arg( 'filter_option', implode( ',', $rest ), $url ) );
				}
			}
			if ( empty( $chips ) ) {
				return;
			}
			$remove = WP_EasyCart_Elementor_Shop::text( 'shop_remove_filter', 'Remove filter: [filter]' );
			echo '<div class="wpec-shop__active"><p class="wpec-sr-only">' . esc_html( WP_EasyCart_Elementor_Shop::text( 'shop_active_filters', 'Active filters' ) ) . '</p><ul class="wpec-shop__chips" role="list">';
			foreach ( $chips as $chip ) {
				echo '<li><a class="wpec-chip" href="' . esc_url( $chip[1] ) . '" aria-label="' . esc_attr( WP_EasyCart_Elementor_Shop::fill( $remove, array( 'filter' => $chip[0] ) ) ) . '"><span>' . esc_html( $chip[0] ) . '</span><span class="wpec-chip__x" aria-hidden="true">&times;</span></a></li>';
			}
			if ( count( $chips ) > 1 ) {
				echo '<li><a class="wpec-shop__clear" href="' . esc_url( WP_EasyCart_Elementor_Shop::current_url( self::all_filter_args() ) ) . '">' . esc_html( WP_EasyCart_Elementor_Shop::text( 'shop_clear_all', 'Clear all' ) ) . '</a></li>';
			}
			echo '</ul></div>';
		}

		/**
		 * A price range's label ( the store's price point wording ).
		 *
		 * @param object $pricepoint Row from ec_pricepoint.
		 * @return string
		 */
		protected static function pricepoint_label( $pricepoint ) {
			$currency = $GLOBALS['currency'];
			if ( $pricepoint->is_less_than ) {
				$label = WP_EasyCart_Elementor_Shop::store_text( 'ec_pricepoint_widget', 'less_than', 'Less than' ) . ' ' . $currency->get_currency_display( $pricepoint->high_point );
			} elseif ( $pricepoint->is_greater_than ) {
				$label = WP_EasyCart_Elementor_Shop::store_text( 'ec_pricepoint_widget', 'greater_than', 'Greater than' ) . ' ' . $currency->get_currency_display( $pricepoint->low_point );
			} else {
				$label = $currency->get_currency_display( $pricepoint->low_point ) . ' – ' . $currency->get_currency_display( $pricepoint->high_point );
			}
			return wp_strip_all_tags( html_entity_decode( $label, ENT_QUOTES, 'UTF-8' ) );
		}

		/**
		 * The filters panel.
		 *
		 * @param array     $settings Widget settings.
		 * @param int       $category Page category.
		 * @param int       $maker    Page manufacturer.
		 * @param ec_filter $filter   The list's filter.
		 */
		protected function ec_render_filters( $settings, $category, $maker, $filter ) {
			$on = function ( $key ) use ( $settings ) {
				return ! isset( $settings[ $key ] ) || 'yes' === $settings[ $key ];
			};

			$this->ec_filter_counts = $on( 'ec_filter_show_counts' );
			foreach ( self::filter_order( $settings ) as $group ) {
				if ( 'search' === $group && $on( 'ec_filter_search' ) ) {
					$this->ec_render_filter_search();
				} elseif ( 'categories' === $group && $on( 'ec_filter_categories' ) ) {
					$this->ec_render_category_filter( $settings, $category );
				} elseif ( 'manufacturers' === $group && $on( 'ec_filter_manufacturers' ) && ! $maker ) {
					$this->ec_render_manufacturer_filter( $category, $filter->product_status );
				} elseif ( 'price' === $group && $on( 'ec_filter_price' ) ) {
					$this->ec_render_price_filter( $category, $maker, $filter->product_status );
				} elseif ( 'options' === $group ) {
					$this->ec_render_option_filters( $settings, $filter );
				}
			}
			$this->ec_filter_counts = true;
		}

		/**
		 * The filter groups a merchant can order ( key => label ).
		 *
		 * @since 6.0.2
		 * @return array
		 */
		public static function filter_group_labels() {
			return array(
				'search'        => __( 'Search box', 'wp-easycart' ),
				'categories'    => __( 'Categories', 'wp-easycart' ),
				'manufacturers' => __( 'Manufacturers', 'wp-easycart' ),
				'price'         => __( 'Price ranges', 'wp-easycart' ),
				'options'       => __( 'Option filters', 'wp-easycart' ),
			);
		}

		/**
		 * The filter groups in the merchant's order: each once, any left out of the list at the end in the usual order.
		 *
		 * @since 6.0.2
		 * @param array $settings Widget settings.
		 * @return string[]
		 */
		public static function filter_order( $settings ) {
			$known = array_keys( self::filter_group_labels() );
			$order = array();
			if ( isset( $settings['ec_filter_order'] ) && is_array( $settings['ec_filter_order'] ) ) {
				foreach ( $settings['ec_filter_order'] as $row ) {
					$group = ( is_array( $row ) && isset( $row['filter'] ) ) ? (string) $row['filter'] : '';
					if ( in_array( $group, $known, true ) && ! in_array( $group, $order, true ) ) {
						$order[] = $group;
					}
				}
			}
			foreach ( $known as $group ) {
				if ( ! in_array( $group, $order, true ) ) {
					$order[] = $group;
				}
			}
			return $order;
		}

		/**
		 * The search box at the top of the filters.
		 *
		 * @since 6.0.2
		 */
		protected function ec_render_filter_search() {
			$action = WP_EasyCart_Elementor_Shop::current_url( array( 'ec_search', 'pagenum' ) );
			$field  = 'wpec-shop-search-' . $this->get_id();
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only search text.
			$value = isset( $_GET['ec_search'] ) ? sanitize_text_field( wp_unslash( $_GET['ec_search'] ) ) : '';
			echo '<form class="wpec-shop__search" role="search" method="get" action="' . esc_url( $action ) . '">';
			if ( function_exists( 'wp_easycart_print_form_query_inputs' ) ) {
				wp_easycart_print_form_query_inputs( $action, array( 'ec_search', 'pagenum' ) );
			}
			echo '<label class="wpec-sr-only" for="' . esc_attr( $field ) . '">' . esc_html( WP_EasyCart_Elementor_Shop::text( 'shop_search_label', 'Search products' ) ) . '</label>';
			echo '<input type="search" id="' . esc_attr( $field ) . '" name="ec_search" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( WP_EasyCart_Elementor_Shop::text( 'shop_search_placeholder', 'Search products…' ) ) . '" />';
			echo '<button type="submit"><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"/><path d="M20 20l-3.5-3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg><span class="wpec-sr-only">' . esc_html( WP_EasyCart_Elementor_Shop::text( 'shop_search_button', 'Search' ) ) . '</span></button>';
			echo '</form>';
		}

		/**
		 * One filter group.
		 *
		 * @param string $title Group title.
		 * @param array  $links Each array( label, url, current, count, swatch ).
		 */
		protected function ec_render_filter_group( $title, $links ) {
			if ( empty( $links ) ) {
				return;
			}
			echo '<div class="wpec-shop__filter"><h3 class="wpec-shop__filter-title">' . esc_html( $title ) . '</h3><ul class="wpec-shop__filter-list" role="list">';
			foreach ( $links as $link ) {
				if ( ! $this->ec_filter_counts ) {
					$link['count'] = null;
				}
				echo '<li><a href="' . esc_url( $link['url'] ) . '"' . ( ! empty( $link['current'] ) ? ' aria-current="true" class="is-current"' : '' ) . ' rel="nofollow">';
				if ( ! empty( $link['swatch'] ) && class_exists( 'ec_optionitem' ) ) {
					echo '<img class="wpec-shop__filter-swatch" src="' . esc_url( ec_optionitem::swatch_src( $link['swatch'], 36 ), ec_optionitem::swatch_url_protocols() ) . '" alt="" width="18" height="18" loading="lazy" />';
				}
				echo '<span class="wpec-shop__filter-label">' . esc_html( $link['label'] ) . '</span>';
				if ( isset( $link['count'] ) && null !== $link['count'] ) {
					echo '<span class="wpec-shop__filter-count">' . esc_html( $link['count'] ) . '</span>';
				}
				echo '</a></li>';
			}
			echo '</ul></div>';
		}

		/**
		 * Categories: links to each category's page.
		 *
		 * @param array $settings Widget settings.
		 * @param int   $category Page category.
		 */
		protected function ec_render_category_filter( $settings, $category ) {
			if ( ! isset( $GLOBALS['ec_categories'] ) ) {
				return;
			}
			$chosen = WP_EasyCart_Elementor_Shop_Query::ids( isset( $settings['ec_filter_category_ids'] ) ? $settings['ec_filter_category_ids'] : array() );
			$rows   = array();
			if ( ! empty( $chosen ) ) {
				foreach ( $chosen as $category_id ) {
					$row = $GLOBALS['ec_categories']->get_category( $category_id );
					if ( $row ) {
						$rows[] = $row;
					}
				}
			} else {
				$parent = $category;
				foreach ( (array) $GLOBALS['ec_categories']->all_categories as $row ) {
					if ( (int) $row->parent_id === (int) $parent && (int) $row->category_id !== (int) $category ) {
						$rows[] = $row;
					}
				}
				if ( empty( $rows ) && $category ) {
					return; /* A category without subcategories lists nothing. */
				}
			}
			$counts = WP_EasyCart_Elementor_Shop::category_counts( wp_list_pluck( $rows, 'category_id' ) );
			$links  = array();
			foreach ( $rows as $row ) {
				$count = isset( $counts[ (int) $row->category_id ] ) ? $counts[ (int) $row->category_id ] : 0;
				if ( ! $count && ! WP_EasyCart_Elementor_Shop::category_has_children( (int) $row->category_id ) ) {
					continue; /* Empty, and no subcategories to lead to. */
				}
				$links[] = array(
					'label'   => WP_EasyCart_Elementor_Shop::category_name( $row ),
					'url'     => WP_EasyCart_Elementor_Shop::category_url( $row ),
					'current' => ( (int) $row->category_id === (int) $category ),
					'count'   => $count ? $count : null,
				);
			}
			$this->ec_render_filter_group( WP_EasyCart_Elementor_Shop::store_text( 'product_details', 'product_details_categories', 'Categories' ), $links );
		}

		/**
		 * Manufacturers with products ( in the page's category ): filter in place.
		 *
		 * @param int    $category Page category.
		 * @param string $status   The list's "Products shown" rule ( ec_filter product_status ).
		 */
		protected function ec_render_manufacturer_filter( $category, $status = '' ) {
			global $wpdb;
			/* Counted as the list shows them: role-only, hidden out of stock, pickup location and Products shown rules ( integers only ). */
			$visible = WP_EasyCart_Elementor_Shop_Query::visibility_sql( 'wpec_p' ) . WP_EasyCart_Elementor_Shop_Query::status_sql( $status, 'wpec_p' );
			$scope   = $category ? ' INNER JOIN ec_categoryitem AS wpec_ci ON wpec_ci.product_id = wpec_p.product_id AND wpec_ci.category_id = ' . (int) $category : '';
			$rows    = $wpdb->get_results( 'SELECT wpec_m.manufacturer_id, wpec_m.name, COUNT( DISTINCT wpec_p.product_id ) AS products FROM ec_manufacturer AS wpec_m INNER JOIN ec_product AS wpec_p ON wpec_p.manufacturer_id = wpec_m.manufacturer_id AND wpec_p.activate_in_store = 1' . $visible . $scope . ' GROUP BY wpec_m.manufacturer_id, wpec_m.name ORDER BY wpec_m.name ASC LIMIT 100' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed SQL; $visible and $scope hold integers only.
			if ( ! is_array( $rows ) || count( $rows ) < 2 ) {
				return; /* One manufacturer is no choice. */
			}
			$selected = self::url_ids( 'manufacturer' );
			$base     = WP_EasyCart_Elementor_Shop::current_url( array( 'manufacturer', 'pagenum' ) );
			$links    = array();
			foreach ( $rows as $row ) {
				$current = in_array( (int) $row->manufacturer_id, $selected, true );
				$links[] = array(
					'label'   => wp_strip_all_tags( html_entity_decode( wp_easycart_language()->convert_text( $row->name ), ENT_QUOTES, 'UTF-8' ) ),
					'url'     => $current ? $base : add_query_arg( 'manufacturer', (int) $row->manufacturer_id, $base ),
					'current' => $current,
					'count'   => (int) $row->products,
				);
			}
			$this->ec_render_filter_group( WP_EasyCart_Elementor_Shop::store_text( 'product_details', 'product_details_manufacturer', 'Manufacturer' ), $links );
		}

		/**
		 * Price ranges with products ( in the page's category / manufacturer ): filter in place.
		 *
		 * @param int    $category Page category.
		 * @param int    $maker    Page manufacturer.
		 * @param string $status   The list's "Products shown" rule ( ec_filter product_status ).
		 */
		protected function ec_render_price_filter( $category, $maker, $status = '' ) {
			global $wpdb;
			if ( empty( $GLOBALS['ec_pricepoints']->pricepoints ) ) {
				return;
			}
			$scope = '';
			if ( $category ) {
				$scope .= ' AND wpec_p.product_id IN ( SELECT wpec_ci.product_id FROM ec_categoryitem AS wpec_ci WHERE wpec_ci.category_id = ' . (int) $category . ' )';
			}
			if ( $maker ) {
				$scope .= ' AND wpec_p.manufacturer_id = ' . (int) $maker;
			}
			// Counted as the list shows them ( role-only, hidden out of stock, pickup location and Products shown rules ).
			$scope   .= WP_EasyCart_Elementor_Shop_Query::visibility_sql( 'wpec_p' ) . WP_EasyCart_Elementor_Shop_Query::status_sql( $status, 'wpec_p' );
			$rows     = $wpdb->get_results( 'SELECT wpec_pp.pricepoint_id, COUNT( DISTINCT wpec_p.product_id ) AS products FROM ec_pricepoint AS wpec_pp INNER JOIN ec_product AS wpec_p ON wpec_p.activate_in_store = 1' . $scope . ' AND ( ( wpec_pp.is_less_than = 1 AND wpec_p.price < wpec_pp.high_point ) OR ( wpec_pp.is_greater_than = 1 AND wpec_p.price > wpec_pp.low_point ) OR ( wpec_pp.is_less_than = 0 AND wpec_pp.is_greater_than = 0 AND wpec_p.price >= wpec_pp.low_point AND wpec_p.price <= wpec_pp.high_point ) ) GROUP BY wpec_pp.pricepoint_id', OBJECT_K ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $scope holds integers only.
			$selected = self::url_ids( 'pricepoint' );
			$base     = WP_EasyCart_Elementor_Shop::current_url( array( 'pricepoint', 'pagenum' ) );
			$links    = array();
			foreach ( $GLOBALS['ec_pricepoints']->pricepoints as $pricepoint ) {
				$id = (int) $pricepoint->pricepoint_id;
				if ( empty( $rows[ $id ] ) || ! (int) $rows[ $id ]->products ) {
					continue;
				}
				$current = in_array( $id, $selected, true );
				$links[] = array(
					'label'   => self::pricepoint_label( $pricepoint ),
					'url'     => $current ? $base : add_query_arg( 'pricepoint', $id, $base ),
					'current' => $current,
					'count'   => (int) $rows[ $id ]->products,
				);
			}
			$this->ec_render_filter_group( WP_EasyCart_Elementor_Shop::text( 'shop_price', 'Price' ), $links );
		}

		/**
		 * Option filters: each chosen option set is a group of items that toggle in the URL's filter_option list.
		 *
		 * @param array     $settings Widget settings.
		 * @param ec_filter $filter   The list's filter.
		 */
		protected function ec_render_option_filters( $settings, $filter ) {
			$sets = WP_EasyCart_Elementor_Shop_Query::ids( isset( $settings['ec_filter_option_ids'] ) ? $settings['ec_filter_option_ids'] : array() );
			if ( empty( $sets ) || ! class_exists( 'ec_optionset' ) ) {
				return;
			}
			$selected = self::url_ids( 'filter_option' );
			$base     = WP_EasyCart_Elementor_Shop::current_url( array( 'filter_option', 'pagenum' ) );
			foreach ( $sets as $option_id ) {
				$optionset = new ec_optionset( $option_id );
				if ( empty( $optionset->option_id ) ) {
					continue;
				}
				$links = array();
				foreach ( $optionset->optionset as $item ) {
					$item_id = (int) $item->optionitem_id;
					$current = in_array( $item_id, $selected, true );
					$next    = $current ? array_diff( $selected, array( $item_id ) ) : array_merge( $selected, array( $item_id ) );
					$links[] = array(
						'label'   => wp_strip_all_tags( html_entity_decode( (string) $item->optionitem_name, ENT_QUOTES, 'UTF-8' ) ),
						'url'     => empty( $next ) ? $base : add_query_arg( 'filter_option', implode( ',', $next ), $base ),
						'current' => $current,
						'swatch'  => (string) $item->optionitem_icon,
					);
				}
				$title = trim( wp_strip_all_tags( html_entity_decode( (string) ( '' !== (string) $optionset->option_label ? $optionset->option_label : $optionset->option_name ), ENT_QUOTES, 'UTF-8' ) ) );
				$this->ec_render_filter_group( $title, $links );
			}
		}

		/**
		 * Analytics the classic store sends for a product list ( GA4 view_item_list, Meta Search, PRO's list event ).
		 *
		 * @param array $result   Query result.
		 * @param int   $category Page category.
		 * @param int   $maker    Page manufacturer.
		 */
		protected function ec_track( $result, $category, $maker ) {
			/* A search that found nothing is still announced, as the classic store does ( Reports counts searches that found nothing ). */
			$result['products'] = isset( $result['products'] ) && is_array( $result['products'] ) ? $result['products'] : array();
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- which search is shown; nothing is changed.
			if ( function_exists( 'wp_easycart_meta_search' ) && isset( $_GET['ec_search'] ) && 1 === (int) $result['page'] ) {
				wp_easycart_meta_search( sanitize_text_field( wp_unslash( $_GET['ec_search'] ) ), $result['products'] );
			}
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			$list = (object) array(
				'products'     => $result['products'],
				'num_products' => isset( $result['total'] ) ? (int) $result['total'] : count( $result['products'] ),
			);
			do_action( 'wp_easycart_view_product_list', $list, null, 'NOMENU', 'NOSUBMENU', 'NOSUBSUBMENU', $maker ? $maker : 'NOMANUFACTURER', $category ? $category : 'NOGROUP', array( 'elementor' => 1 ) );

			if ( empty( $result['products'] ) || '' === (string) get_option( 'ec_option_google_ga4_property_id' ) ) {
				return;
			}
			$items = array();
			foreach ( $result['products'] as $index => $product ) {
				$items[] = array(
					'item_id'    => (string) $product->model_number,
					'item_name'  => wp_strip_all_tags( html_entity_decode( (string) $product->title, ENT_QUOTES, 'UTF-8' ) ),
					'index'      => $index,
					'price'      => (float) number_format( (float) $product->price, 2, '.', '' ),
					'item_brand' => (string) $product->manufacturer_name,
					'quantity'   => 1,
				);
			}
			$event = array(
				'item_list_id'   => 'products',
				'item_list_name' => __( 'Products', 'wp-easycart' ),
				'items'          => $items,
			);
			if ( get_option( 'ec_option_google_ga4_tag_manager' ) ) {
				$js = 'window.dataLayer=window.dataLayer||[];dataLayer.push({ecommerce:null});dataLayer.push({event:"view_item_list",ecommerce:' . wp_json_encode( $event ) . '});';
			} else {
				$js = 'if(typeof gtag==="function"){gtag("event","view_item_list",' . wp_json_encode( $event ) . ');}';
			}
			echo '<script>document.addEventListener("DOMContentLoaded",function(){' . $js . '});</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON from wp_json_encode().
		}
	}

endif;
