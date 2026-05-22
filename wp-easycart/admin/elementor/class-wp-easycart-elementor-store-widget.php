<?php
/**
 * WP EasyCart Store Widget for Elementor
 *
 * @category Class
 * @package  Wp_Easycart_Elementor_Store_Widget
 * @author   WP EasyCart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Elementor\Controls_Manager;
use Elementor\Scheme_Color;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use ELementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Image_Size;
use Elementor\Utils;
use Elementor\Wp_Easycart_Controls_Manager;

/**
 * WP EasyCart Store Widget for Elementor
 *
 * @category Class
 * @package  Wp_Easycart_Elementor_Store_Widget
 * @author   WP EasyCart
 */
class Wp_Easycart_Elementor_Store_Widget extends \Elementor\Widget_Base {

	/**
	 * Get store widget name.
	 */
	public function get_name() {
		return 'wp_easycart_store';
	}

	/**
	 * Get store widget title.
	 */
	public function get_title() {
		return esc_attr__( 'WP EasyCart Store', 'wp-easycart' );
	}

	/**
	 * Get store widget icon.
	 */
	public function get_icon() {
		return 'eicon-product-pages';
	}

	/**
	 * Get store widget categories.
	 */
	public function get_categories() {
		return array( 'wp-easycart-elements' );
	}

	/**
	 * Get store widget keywords.
	 */
	public function get_keywords() {
		return array( 'products', 'shop', 'wp-easycart' );
	}

	/**
	 * Enqueue store widget scripts and styles.
	 */
	public function get_script_depends() {
		$scripts = array( 'isotope-pkgd', 'jquery-hoverIntent' );
		if ( ( isset( $_REQUEST['action'] ) && 'elementor' == $_REQUEST['action'] ) || isset( $_REQUEST['elementor-preview'] ) ) {
			$scripts[] = 'wpeasycart_js';
		}
		return $scripts;
	}

	/**
	 * Setup store widget controls.
	 */
	protected function _register_controls() {

		$this->start_controls_section(
			'section_products',
			array(
				'label' => esc_attr__( 'Products Selector', 'wp-easycart' ),
			)
		);

		$this->add_control(
			'title',
			array(
				'label'       => esc_attr__( 'Title', 'wp-easycart' ),
				'type'        => Controls_Manager::TEXTAREA,
				'rows'        => 3,
				'default'     => '',
				'placeholder' => esc_attr__( 'Title', 'wp-easycart' ),
			)
		);

		$this->add_control(
			'title_link',
			array(
				'label' => esc_attr__( 'Title Link', 'wp-easycart' ),
				'type'  => Controls_Manager::URL,
			)
		);

		$this->add_control(
			'desc',
			array(
				'label'       => esc_attr__( 'Description', 'wp-easycart' ),
				'type'        => Controls_Manager::TEXTAREA,
				'default'     => '',
				'placeholder' => esc_attr__( 'Description', 'wp-easycart' ),
			)
		);

		$this->add_responsive_control(
			'title_align',
			array(
				'label'     => esc_attr__( 'Alignment', 'wp-easycart' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'left'   => array(
						'title' => esc_attr__( 'Left', 'wp-easycart' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_attr__( 'Center', 'wp-easycart' ),
						'icon'  => 'eicon-text-align-center',
					),
					'right'  => array(
						'title' => esc_attr__( 'Right', 'wp-easycart' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .title-wrapper' => 'text-align: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'status',
			array(
				'label'   => esc_attr__( 'Product Status', 'wp-easycart' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'featured',
				'options' => array(
					'all'       => esc_attr__( 'All', 'wp-easycart' ),
					'featured'  => esc_attr__( 'Featured', 'wp-easycart' ),
					'on_sale'   => esc_attr__( 'On Sale', 'wp-easycart' ),
					'in_stock'  => esc_attr__( 'In Stock', 'wp-easycart' ),
				),
			)
		);

		$this->add_control(
			'use_dynamic',
			array(
				'type'  => Controls_Manager::SWITCHER,
				'label' => esc_attr__( 'Dynamic (by post id)', 'wp-easycart' ),
				'default'   => false,
			)
		);

		$this->add_control(
			'ids',
			array(
				'label'       => esc_attr__( 'Select products', 'wp-easycart' ),
				'type'        => Wp_Easycart_Controls_Manager::WPECAJAXSELECT2,
				'options'     => 'easycart_product',
				'label_block' => true,
				'multiple'    => 'true',
				'condition' => array(
					'use_dynamic' => '',
				),
			)
		);

		$this->add_control(
			'category',
			array(
				'label'       => esc_attr__( 'Select categories', 'wp-easycart' ),
				'type'        => Wp_Easycart_Controls_Manager::WPECAJAXSELECT2,
				'options'     => 'easycart_product_cat',
				'label_block' => true,
				'multiple'    => 'true',
				'condition' => array(
					'use_dynamic' => '',
				),
			)
		);

		$this->add_control(
			'brands',
			array(
				'label'       => esc_attr__( 'Select Brands', 'wp-easycart' ),
				'type'        => Wp_Easycart_Controls_Manager::WPECAJAXSELECT2,
				'options'     => 'easycart_product_brand',
				'label_block' => true,
				'multiple'    => 'true',
				'condition' => array(
					'use_dynamic' => '',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_products_layout',
			array(
				'label' => esc_attr__( 'Products Layout', 'wp-easycart' ),
			)
		);

		$this->add_control(
			'spacing',
			array(
				'type'        => Controls_Manager::SLIDER,
				'label'       => esc_attr__( 'Spacing (px)', 'wp-easycart' ),
				'description' => esc_attr__( 'Leave blank if you use theme default value.', 'wp-easycart' ),
				'default'     => array(
					'size' => 20,
				),
				'range'       => array(
					'px' => array(
						'step' => 1,
						'min'  => 0,
						'max'  => 40,
					),
				),
			)
		);

		$this->add_control(
			'cols_upper_desktop',
			array(
				'label'     => esc_attr__( 'Columns Upper Desktop ( >= 1200px )', 'wp-easycart' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => '',
				'options'   => array(
					''  => esc_attr__( 'Default', 'wp-easycart' ),
					'1' => 1,
					'2' => 2,
					'3' => 3,
					'4' => 4,
					'5' => 5,
					'6' => 6,
					'7' => 7,
					'8' => 8,
				),
			)
		);

		$this->add_responsive_control(
			'columns',
			array(
				'type'      => Controls_Manager::SELECT,
				'label'     => esc_attr__( 'Columns', 'wp-easycart' ),
				'default'   => '4',
				'options'   => array(
					'1' => 1,
					'2' => 2,
					'3' => 3,
					'4' => 4,
					'5' => 5,
					'6' => 6,
					'7' => 7,
					'8' => 8,
				),
				'condition' => array(
					'product_style!' => array( 'list' ),
				),
			)
		);

		$this->add_control(
			'cols_under_mobile',
			array(
				'label'     => esc_attr__( 'Columns Under Mobile ( <= 575px )', 'wp-easycart' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 2,
				'options'   => array(
					'1' => 1,
					'2' => 2,
					'3' => 3,
				),
				'condition' => array(
					'product_style!' => array( 'list' ),
				),
			)
		);

		$this->add_control(
			'product_extra_heading',
			array(
				'label'     => esc_attr__( 'Extra Options', 'wp-easycart' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'product_border',
			array(
				'type'  => Controls_Manager::SWITCHER,
				'label' => esc_attr__( 'Enable Product Border', 'wp-easycart' ),
				'description' => esc_attr__( 'Border shows where applicable (depends on product display type).', 'wp-easycart' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_store_layout',
			array(
				'label' => esc_attr__( 'Store Layout', 'wp-easycart' ),
			)
		);

		$this->add_control(
			'paging',
			array(
				'type'  => Controls_Manager::SWITCHER,
				'label' => esc_attr__( 'Enable Paging', 'wp-easycart' ),
			)
		);

		$this->add_control(
			'sorting',
			array(
				'type'  => Controls_Manager::SWITCHER,
				'label' => esc_attr__( 'Enable Sorting', 'wp-easycart' ),
			)
		);

		$this->add_control(
			'sorting_default',
			array(
				'label'     => esc_attr__( 'Sorting Selection', 'wp-easycart' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => get_option( 'ec_option_default_store_filter' ),
				'options'   => array(
					'0' => __( 'Default Sorting (admin determined sort order)', 'wp-easycart' ),
					'1' => __( 'Price Low-High', 'wp-easycart' ),
					'2' => __( 'Price High-Low', 'wp-easycart' ),
					'3' => __( 'Title A-Z', 'wp-easycart' ),
					'4' => __( 'Title Z-A', 'wp-easycart' ),
					'5' => __( 'Newest First', 'wp-easycart' ),
					'6' => __( 'Best Rating First', 'wp-easycart' ),
					'7' => __( 'Most Viewed', 'wp-easycart' ),
				),
				'condition' => array(
					'sorting' => 'yes',
				),
			)
		);

		$this->add_control(
			'sidebar',
			array(
				'type'  => Controls_Manager::SWITCHER,
				'label' => esc_attr__( 'Enable Sidebar', 'wp-easycart' ),
			)
		);

		$this->add_control(
			'sidebar_position',
			array(
				'label'     => esc_attr__( 'Sorting Selection', 'wp-easycart' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'left',
				'options'   => array(
					'left' => __( 'Sidebar Left', 'wp-easycart' ),
					'right' => __( 'Sidebar Right', 'wp-easycart' ),
					'slide-left' => __( 'Slideout Left (overlay)', 'wp-easycart' ),
					'slide-right' => __( 'Slideout Right (overlay)', 'wp-easycart' ),
				),
				'condition' => array(
					'sidebar' => 'yes',
				),
			)
		);

		$this->add_control(
			'sidebar_filter_clear',
			array(
				'type'  => Controls_Manager::SWITCHER,
				'label' => esc_attr__( 'Enable Sidebar Filter Clear', 'wp-easycart' ),
				'default'   => 'yes',
				'condition' => array(
					'sidebar' => 'yes',
				),
			)
		);

		if ( get_option( 'ec_option_pickup_enable_locations' ) && get_option( 'ec_option_pickup_location_select_enabled' ) ) {
			$this->add_control(
				'sidebar_include_location',
				array(
					'type'  => Controls_Manager::SWITCHER,
					'label' => esc_attr__( 'Enable Sidebar Location Selector', 'wp-easycart' ),
					'default'   => 'no',
					'condition' => array(
						'sidebar' => 'yes',
					),
				)
			);
		}

		$this->add_control(
			'sidebar_include_search',
			array(
				'type'  => Controls_Manager::SWITCHER,
				'label' => esc_attr__( 'Enable Sidebar Search', 'wp-easycart' ),
				'default'   => 'yes',
				'condition' => array(
					'sidebar' => 'yes',
				),
			)
		);

		$this->add_control(
			'sidebar_include_categories',
			array(
				'type'  => Controls_Manager::SWITCHER,
				'label' => esc_attr__( 'Enable Sidebar Category Links', 'wp-easycart' ),
				'default'   => 'yes',
				'condition' => array(
					'sidebar' => 'yes',
				),
			)
		);

		$this->add_control(
			'sidebar_include_categories_first',
			array(
				'type'  => Controls_Manager::SWITCHER,
				'label' => esc_attr__( 'Sidebar Category Links First?', 'wp-easycart' ),
				'default'   => 'yes',
				'condition' => array(
					'sidebar' => 'yes',
					'sidebar_include_categories' => 'yes',
				),
			)
		);

		$this->add_control(
			'sidebar_categories',
			array(
				'label'       => esc_attr__( 'Select Category Links', 'wp-easycart' ),
				'type'        => Wp_Easycart_Controls_Manager::WPECAJAXSELECT2,
				'options'     => 'easycart_product_cat',
				'label_block' => true,
				'multiple'    => 'true',
				'condition' => array(
					'sidebar' => 'yes',
					'sidebar_include_categories' => 'yes',
				),
			)
		);

		$this->add_control(
			'sidebar_include_category_filters',
			array(
				'type'  => Controls_Manager::SWITCHER,
				'label' => esc_attr__( 'Enable Sidebar Complex Category Filters', 'wp-easycart' ),
				'default'   => 'no',
				'condition' => array(
					'sidebar' => 'yes',
				),
			)
		);

		$this->add_control(
			'sidebar_category_filter_id',
			array(
				'label'       => esc_attr__( 'Select top level category', 'wp-easycart' ),
				'type'        => Wp_Easycart_Controls_Manager::WPECAJAXSELECT2,
				'options'     => 'easycart_product_cat',
				'label_block' => true,
				'multiple'    => false,
				'condition' => array(
					'sidebar' => 'yes',
					'sidebar_include_category_filters' => 'yes',
				),
			)
		);

		$this->add_control(
			'sidebar_category_filter_method',
			array(
				'label'     => esc_attr__( 'Filter Method', 'wp-easycart' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'AND',
				'options'   => array(
					'AND' => __( 'Filter Method: AND', 'wp-easycart' ),
					'OR' => __( 'Filter Method: OR', 'wp-easycart' ),
				),
				'condition' => array(
					'sidebar' => 'yes',
					'sidebar_include_category_filters' => 'yes',
				),
			)
		);

		$this->add_control(
			'sidebar_category_filter_open',
			array(
				'label'     => esc_attr__( 'Filter Method', 'wp-easycart' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => '1',
				'options'   => array(
					'0' => __( 'All Closed', 'wp-easycart' ),
					'1' => __( 'All Open', 'wp-easycart' ),
					'2' => __( 'First Open', 'wp-easycart' ),
				),
				'condition' => array(
					'sidebar' => 'yes',
					'sidebar_include_category_filters' => 'yes',
				),
			)
		);

		$this->add_control(
			'sidebar_include_manufacturers',
			array(
				'type'  => Controls_Manager::SWITCHER,
				'label' => esc_attr__( 'Enable Sidebar Manufacturer Links', 'wp-easycart' ),
				'default'   => 'no',
				'condition' => array(
					'sidebar' => 'yes',
				),
			)
		);

		$this->add_control(
			'sidebar_manufacturers',
			array(
				'label'       => esc_attr__( 'Select Manufacturer Links', 'wp-easycart' ),
				'type'        => Wp_Easycart_Controls_Manager::WPECAJAXSELECT2,
				'options'     => 'easycart_product_brand',
				'label_block' => true,
				'multiple'    => 'true',
				'condition' => array(
					'sidebar' => 'yes',
					'sidebar_include_manufacturers' => 'yes',
				),
			)
		);

		$this->add_control(
			'sidebar_include_option_filters',
			array(
				'type'  => Controls_Manager::SWITCHER,
				'label' => esc_attr__( 'Enable Sidebar Option Filters', 'wp-easycart' ),
				'default'   => 'yes',
				'condition' => array(
					'sidebar' => 'yes',
				),
			)
		);

		$this->add_control(
			'sidebar_option_filters',
			array(
				'label'       => esc_attr__( 'Select options', 'wp-easycart' ),
				'type'        => Wp_Easycart_Controls_Manager::WPECAJAXSELECT2,
				'options'     => 'easycart_product_optionsets',
				'label_block' => true,
				'multiple'    => 'true',
				'condition' => array(
					'sidebar' => 'yes',
					'sidebar_include_option_filters' => 'yes',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_products_type',
			array(
				'label' => esc_attr__( 'Products Type', 'wp-easycart' ),
			)
		);

		$this->add_control(
			'type',
			array(
				'label'   => esc_attr__( 'Type', 'wp-easycart' ),
				'type'    => Controls_Manager::SELECT,
				'default' => '',
				'options' => array(
					''       => esc_attr__( 'Theme Options', 'wp-easycart' ),
					'custom' => esc_attr__( 'Custom', 'wp-easycart' ),
				),
			)
		);

		$this->add_control(
			'product_style',
			array(
				'label'     => esc_attr__( 'Product Type', 'wp-easycart' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'default',
				'options'   => array(
					'1'     => esc_attr__( 'Grid Type 1', 'wp-easycart' ),
					'2'     => esc_attr__( 'Grid Type 2', 'wp-easycart' ),
					'3'     => esc_attr__( 'Grid Type 3', 'wp-easycart' ),
					'4'     => esc_attr__( 'Grid Type 4', 'wp-easycart' ),
					'5'     => esc_attr__( 'Grid Type 5', 'wp-easycart' ),
					'6'     => esc_attr__( 'List Type 6', 'wp-easycart' ),
				),
				'condition' => array(
					'type' => 'custom',
				),
			)
		);

		$this->add_responsive_control(
			'product_align',
			array(
				'label'     => esc_attr__( 'Product Align', 'wp-easycart' ),
				'type'      => Controls_Manager::CHOOSE,
				'default'   => 'center',
				'options'   => array(
					'left'   => array(
						'title' => esc_attr__( 'Left', 'wp-easycart' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_attr__( 'Center', 'wp-easycart' ),
						'icon'  => 'eicon-text-align-center',
					),
					'right'  => array(
						'title' => esc_attr__( 'Right', 'wp-easycart' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'condition' => array(
					'type'           => 'custom',
					'product_style!' => 'card',
				),
			)
		);
		$this->start_controls_tabs( 'tabs_position' );

		$this->start_controls_tab(
			'tab_pos_top',
			array(
				'label'     => esc_attr__( 'Top', 'wp-easycart' ),
				'condition' => array(
					'type'          => 'custom',
					'product_style' => 'full',
				),
			)
		);

		$this->add_responsive_control(
			'body_pos_top',
			array(
				'label'      => esc_attr__( 'Top', 'wp-easycart' ),
				'type'       => Controls_Manager::SLIDER,
				'default'    => array(
					'size' => 50,
					'unit' => '%',
				),
				'size_units' => array(
					'px',
					'%',
				),
				'range'      => array(
					'px' => array(
						'step' => 1,
						'min'  => 0,
						'max'  => 500,
					),
					'%'  => array(
						'step' => 1,
						'min'  => 0,
						'max'  => 100,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .product-body' => 'top: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array(
					'type'          => 'custom',
					'product_style' => 'full',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tab_pos_right',
			array(
				'label'     => esc_attr__( 'Right', 'wp-easycart' ),
				'condition' => array(
					'type'          => 'custom',
					'product_style' => 'full',
				),
			)
		);

		$this->add_responsive_control(
			'body_pos_right',
			array(
				'label'      => esc_attr__( 'Right', 'wp-easycart' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array(
					'px',
					'%',
				),
				'range'      => array(
					'px' => array(
						'step' => 1,
						'min'  => 0,
						'max'  => 500,
					),
					'%'  => array(
						'step' => 1,
						'min'  => 0,
						'max'  => 100,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .product-body' => 'right: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array(
					'type'          => 'custom',
					'product_style' => 'full',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tab_pos_bottom',
			array(
				'label'     => esc_attr__( 'Bottom', 'wp-easycart' ),
				'condition' => array(
					'type'          => 'custom',
					'product_style' => 'full',
				),
			)
		);

		$this->add_responsive_control(
			'body_pos_bottom',
			array(
				'label'      => esc_attr__( 'Bottom', 'wp-easycart' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array(
					'px',
					'%',
				),
				'range'      => array(
					'px' => array(
						'step' => 1,
						'min'  => 0,
						'max'  => 500,
					),
					'%'  => array(
						'step' => 1,
						'min'  => 0,
						'max'  => 100,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .product-body' => 'bottom: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array(
					'type'          => 'custom',
					'product_style' => 'full',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tab_pos_left',
			array(
				'label'     => esc_attr__( 'Left', 'wp-easycart' ),
				'condition' => array(
					'type'          => 'custom',
					'product_style' => 'full',
				),
			)
		);

		$this->add_responsive_control(
			'body_pos_left',
			array(
				'label'      => esc_attr__( 'Left', 'wp-easycart' ),
				'type'       => Controls_Manager::SLIDER,
				'default'    => array(
					'size' => 50,
					'unit' => '%',
				),
				'size_units' => array(
					'px',
					'%',
				),
				'range'      => array(
					'px' => array(
						'step' => 1,


						'min'  => 0,
						'max'  => 500,
					),
					'%'  => array(
						'step' => 1,
						'min'  => 0,
						'max'  => 100,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .product-body' => 'left: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array(
					'type'          => 'custom',
					'product_style' => 'full',
				),
			)
		);

		$this->end_controls_tab();
		$this->end_controls_tabs();

		$this->add_responsive_control(
			'min_height',
			array(
				'type'       => Controls_Manager::SLIDER,
				'label'      => esc_attr__( 'Image Min Height', 'wp-easycart' ),
				'separator'  => 'after',
				'size_units' => array(
					'px',
					'%',
					'rem',
				),
				'range'      => array(
					'px' => array(
						'step' => 1,
						'min'  => 20,
						'max'  => 100,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .product-media img' => 'min-height: {{SIZE}}px; object-fit: cover',
				),
				'condition'  => array(
					'type'          => 'custom',
					'product_style' => 'full',
				),
			)
		);

		$this->add_control(
			'visible_options',
			array(
				'label'       => esc_attr__( 'Visible Items', 'wp-easycart' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'default'     => array(
					'title',
					'category',
					'price',
					'rating',
					'cart',
					'quickview',
					'desc',
				),
				'description' => esc_attr__( 'Short description only where available.', 'wp-easycart' ),
				'options'     => array(
					'title'     => esc_attr__( 'Title', 'wp-easycart' ),
					'category'  => esc_attr__( 'Categories', 'wp-easycart' ),
					'price'     => esc_attr__( 'Price', 'wp-easycart' ),
					'rating'    => esc_attr__( 'Rating', 'wp-easycart' ),
					'cart'      => esc_attr__( 'Add To Cart', 'wp-easycart' ),
					'quickview' => esc_attr__( 'Quick View', 'wp-easycart' ),
					'desc'      => esc_attr__( 'Short Description', 'wp-easycart' ),
				),
				'condition'   => array(
					'type' => 'custom',
				),
			)
		);

		$this->add_control(
			'product_rounded_corners',
			array(
				'type'        => Controls_Manager::SWITCHER,
				'label'       => esc_attr__( 'Customize Product Image Corners', 'wp-easycart' ),
				'default'     => 'no',
				'condition'   => array(
					'type' => 'custom',
				),
			)
		);

		$this->add_control(
			'product_rounded_corners_tl',
			array(
				'type'        => Controls_Manager::SLIDER,
				'label'       => esc_attr__( 'Border Radius - Top-Left (px)', 'wp-easycart' ),
				'default'     => array(
					'size' => 10,
				),
				'range'       => array(
					'px' => array(
						'step' => 1,
						'min'  => 0,
						'max'  => 50,
					),
				),
				'condition'   => array(
					'type' => 'custom',
					'product_rounded_corners' => 'yes',
				),
			)
		);

		$this->add_control(
			'product_rounded_corners_tr',
			array(
				'type'        => Controls_Manager::SLIDER,
				'label'       => esc_attr__( 'Border Radius - Top-Right (px)', 'wp-easycart' ),
				'default'     => array(
					'size' => 10,
				),
				'range'       => array(
					'px' => array(
						'step' => 1,
						'min'  => 0,
						'max'  => 50,
					),
				),
				'condition'   => array(
					'type' => 'custom',
					'product_rounded_corners' => 'yes',
				),
			)
		);

		$this->add_control(
			'product_rounded_corners_bl',
			array(
				'type'        => Controls_Manager::SLIDER,
				'label'       => esc_attr__( 'Border Radius - Bottom-Left (px)', 'wp-easycart' ),
				'default'     => array(
					'size' => 10,
				),
				'range'       => array(
					'px' => array(
						'step' => 1,
						'min'  => 0,
						'max'  => 50,
					),
				),
				'condition'   => array(
					'type' => 'custom',
					'product_rounded_corners' => 'yes',
				),
			)
		);

		$this->add_control(
			'product_rounded_corners_br',
			array(
				'type'        => Controls_Manager::SLIDER,
				'label'       => esc_attr__( 'Border Radius - Bottom-Right (px)', 'wp-easycart' ),
				'default'     => array(
					'size' => 10,
				),
				'range'       => array(
					'px' => array(
						'step' => 1,
						'min'  => 0,
						'max'  => 50,
					),
				),
				'condition'   => array(
					'type' => 'custom',
					'product_rounded_corners' => 'yes',
				),
			)
		);

		$this->end_controls_section();

		// ============================================================
		// IMAGE DISPLAY SECTION
		// ============================================================
		$this->start_controls_section(
			'section_image_display',
			array(
				'label' => esc_attr__( 'Image Display', 'wp-easycart' ),
			)
		);

		$this->add_control(
			'image_display_mode',
			array(
				'label'   => esc_attr__( 'Image Height Mode', 'wp-easycart' ),
				'type'    => Controls_Manager::SELECT,
				'default' => '',
				'options' => array(
					''         => esc_attr__( 'Use WP EasyCart Default', 'wp-easycart' ),
					'fixed'    => esc_attr__( 'Fixed Height (Custom)', 'wp-easycart' ),
					'dynamic'  => esc_attr__( 'Dynamic Height (Auto)', 'wp-easycart' ),
				),
				'description' => esc_attr__( 'Default uses your WP EasyCart admin settings. Fixed Height lets you set a custom image window per breakpoint. Dynamic Height lets images scale naturally.', 'wp-easycart' ),
				'prefix_class' => 'ec-img-mode-',
			)
		);

		$this->add_responsive_control(
			'image_height',
			array(
				'type'       => Controls_Manager::SLIDER,
				'label'      => esc_attr__( 'Image Height', 'wp-easycart' ),
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'step' => 1,
						'min'  => 50,
						'max'  => 800,
					),
				),
				'default'    => array(
					'size' => 250,
					'unit' => 'px',
				),
				'condition'  => array(
					'image_display_mode' => 'fixed',
				),
				'selectors'  => array(
					'{{WRAPPER}} .ec_image_container_none, {{WRAPPER}} .ec_image_container_none > div, {{WRAPPER}} .ec_image_container_border, {{WRAPPER}} .ec_image_container_border > div, {{WRAPPER}} .ec_image_container_shadow, {{WRAPPER}} .ec_image_container_shadow > div' => 'min-height: {{SIZE}}{{UNIT}} !important; height: {{SIZE}}{{UNIT}} !important;',
					'{{WRAPPER}} .ec_product_image_container, {{WRAPPER}} .ec_product_image_1, {{WRAPPER}} .ec_product_image_2' => 'width: 100% !important; height: 100% !important;',
				),
			)
		);

		$this->add_control(
			'image_object_fit',
			array(
				'label'   => esc_attr__( 'Image Fit', 'wp-easycart' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'cover',
				'options' => array(
					'cover'   => esc_attr__( 'Cover (Crop to Fill)', 'wp-easycart' ),
					'contain' => esc_attr__( 'Contain (Show Full Image)', 'wp-easycart' ),
					'fill'    => esc_attr__( 'Fill (Stretch)', 'wp-easycart' ),
				),
				'description' => esc_attr__( 'How images fit within the image window. Cover crops to fill, Contain shows the full image, Fill stretches to fit.', 'wp-easycart' ),
				'condition'   => array(
					'image_display_mode' => 'fixed',
				),
				'selectors'   => array(
					'{{WRAPPER}} .ec_image_container_none img, {{WRAPPER}} .ec_image_container_border img, {{WRAPPER}} .ec_image_container_shadow img, {{WRAPPER}} .ec_product_image_container > img, {{WRAPPER}} .ec_product_image_1 > img, {{WRAPPER}} .ec_product_image_2 > img, {{WRAPPER}} .ec_flipbook > img' => 'object-fit: {{VALUE}} !important; width: 100% !important; height: 100% !important;',
				),
			)
		);

		$this->add_control(
			'image_object_position',
			array(
				'label'   => esc_attr__( 'Image Position', 'wp-easycart' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'center center',
				'options' => array(
					'center center' => esc_attr__( 'Center', 'wp-easycart' ),
					'center top'    => esc_attr__( 'Top', 'wp-easycart' ),
					'center bottom' => esc_attr__( 'Bottom', 'wp-easycart' ),
				),
				'description' => esc_attr__( 'Anchor point for image cropping when using Cover fit.', 'wp-easycart' ),
				'condition'   => array(
					'image_display_mode' => 'fixed',
					'image_object_fit'   => 'contain',
				),
				'selectors'   => array(
					'{{WRAPPER}} .ec_image_container_none img, {{WRAPPER}} .ec_image_container_border img, {{WRAPPER}} .ec_image_container_shadow img, {{WRAPPER}} .ec_product_image_container > img, {{WRAPPER}} .ec_product_image_1 > img, {{WRAPPER}} .ec_product_image_2 > img, {{WRAPPER}} .ec_flipbook > img' => 'object-position: {{VALUE}} !important;',
				),
			)
		);

		$this->add_control(
			'image_contain_bg',
			array(
				'label'   => esc_attr__( 'Container Background', 'wp-easycart' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '',
				'condition'   => array(
					'image_display_mode' => 'fixed',
					'image_object_fit'   => 'contain',
				),
				'selectors'   => array(
					'{{WRAPPER}} .ec_image_container_none, {{WRAPPER}} .ec_image_container_border, {{WRAPPER}} .ec_image_container_shadow' => 'background-color: {{VALUE}};',
				),
				'description' => esc_attr__( 'Background color visible behind images when using Contain fit.', 'wp-easycart' ),
			)
		);

		$this->add_control(
			'image_hover_effect',
			array(
				'label'   => esc_attr__( 'Hover Effect Override', 'wp-easycart' ),
				'type'    => Controls_Manager::SELECT,
				'default' => '',
				'options' => array(
					''   => esc_attr__( 'Use Product Default', 'wp-easycart' ),
					'1'  => esc_attr__( 'Image Flip', 'wp-easycart' ),
					'2'  => esc_attr__( 'Image Crossfade', 'wp-easycart' ),
					'3'  => esc_attr__( 'Lighten', 'wp-easycart' ),
					'7'  => esc_attr__( 'Grey-Color', 'wp-easycart' ),
					'8'  => esc_attr__( 'Brighten', 'wp-easycart' ),
					'9'  => esc_attr__( 'Image Slide', 'wp-easycart' ),
					'10' => esc_attr__( 'FlipBook', 'wp-easycart' ),
					'4'  => esc_attr__( 'No Effect', 'wp-easycart' ),
				),
				'description' => esc_attr__( 'Override the per-product hover effect for all products in this widget.', 'wp-easycart' ),
				'condition'   => array(
					'image_display_mode!' => '',
				),
			)
		);

		$this->end_controls_section();

		// ============================================================
		// STYLE TAB: PRODUCT CONTAINER
		// ============================================================
		$this->start_controls_section(
			'section_style_product_container',
			array(
				'label' => esc_attr__( 'Product Container', 'wp-easycart' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'product_bg_color',
			array(
				'label'     => esc_attr__( 'Background Color', 'wp-easycart' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_product_li .ec_product_type1, {{WRAPPER}} .ec_product_li .ec_product_type2, {{WRAPPER}} .ec_product_li .ec_product_type3, {{WRAPPER}} .ec_product_li .ec_product_type4, {{WRAPPER}} .ec_product_li .ec_product_type5, {{WRAPPER}} .ec_product_li .ec_product_type5' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'product_container_border',
				'label'    => esc_attr__( 'Border', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_product_li .ec_product_type1, {{WRAPPER}} .ec_product_li .ec_product_type2, {{WRAPPER}} .ec_product_li .ec_product_type3, {{WRAPPER}} .ec_product_li .ec_product_type4, {{WRAPPER}} .ec_product_li .ec_product_type5, {{WRAPPER}} .ec_product_li .ec_product_type5',
			)
		);

		$this->add_control(
			'product_container_border_radius',
			array(
				'label'      => esc_attr__( 'Border Radius', 'wp-easycart' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ec_product_li .ec_product_type1, {{WRAPPER}} .ec_product_li .ec_product_type2, {{WRAPPER}} .ec_product_li .ec_product_type3, {{WRAPPER}} .ec_product_li .ec_product_type4, {{WRAPPER}} .ec_product_li .ec_product_type5, {{WRAPPER}} .ec_product_li .ec_product_type5' => 'border-top-left-radius: {{TOP}}{{UNIT}} !important; border-top-right-radius: {{RIGHT}}{{UNIT}} !important; border-bottom-right-radius: {{BOTTOM}}{{UNIT}} !important; border-bottom-left-radius: {{LEFT}}{{UNIT}} !important; overflow: hidden;',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'product_container_shadow',
				'label'    => esc_attr__( 'Box Shadow', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_product_li .ec_product_type1, {{WRAPPER}} .ec_product_li .ec_product_type2, {{WRAPPER}} .ec_product_li .ec_product_type3, {{WRAPPER}} .ec_product_li .ec_product_type4, {{WRAPPER}} .ec_product_li .ec_product_type5, {{WRAPPER}} .ec_product_li .ec_product_type5',
			)
		);

		$this->add_responsive_control(
			'product_container_padding',
			array(
				'label'      => esc_attr__( 'Padding', 'wp-easycart' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ec_product_li .ec_product_type1, {{WRAPPER}} .ec_product_li .ec_product_type2, {{WRAPPER}} .ec_product_li .ec_product_type3, {{WRAPPER}} .ec_product_li .ec_product_type4, {{WRAPPER}} .ec_product_li .ec_product_type5, {{WRAPPER}} .ec_product_li .ec_product_type5' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);

		$this->end_controls_section();

		// ============================================================
		// STYLE TAB: PRODUCT IMAGE
		// ============================================================
		$this->start_controls_section(
			'section_style_product_image',
			array(
				'label' => esc_attr__( 'Product Image', 'wp-easycart' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'image_border',
				'label'    => esc_attr__( 'Border', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_image_container_none img, {{WRAPPER}} .ec_image_container_border img, {{WRAPPER}} .ec_image_container_shadow img',
			)
		);

		$this->add_control(
			'image_border_radius',
			array(
				'label'      => esc_attr__( 'Border Radius', 'wp-easycart' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ec_image_container_none, {{WRAPPER}} .ec_image_container_border, {{WRAPPER}} .ec_image_container_shadow, {{WRAPPER}} .ec_image_container_none img, {{WRAPPER}} .ec_image_container_border img, {{WRAPPER}} .ec_image_container_shadow img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'image_shadow',
				'label'    => esc_attr__( 'Box Shadow', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_image_container_none, {{WRAPPER}} .ec_image_container_border, {{WRAPPER}} .ec_image_container_shadow',
			)
		);

		$this->add_responsive_control(
			'image_container_margin',
			array(
				'label'      => esc_attr__( 'Margin', 'wp-easycart' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ec_image_container_none, {{WRAPPER}} .ec_image_container_border, {{WRAPPER}} .ec_image_container_shadow' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		// ============================================================
		// STYLE TAB: PRODUCT TITLE
		// ============================================================
		$this->start_controls_section(
			'section_style_product_title',
			array(
				'label' => esc_attr__( 'Product Title', 'wp-easycart' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'title_color',
			array(
				'label'     => esc_attr__( 'Color', 'wp-easycart' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_product_title a, {{WRAPPER}} .ec_product_title_type1 a, {{WRAPPER}} .ec_product_title_type2 a, {{WRAPPER}} .ec_product_title_type3 a, {{WRAPPER}} .ec_product_title_type4 a, {{WRAPPER}} .ec_product_title_type5 a, {{WRAPPER}} .ec_product_title' => 'color: {{VALUE}} !important;',
				),
			)
		);

		$this->add_control(
			'title_hover_color',
			array(
				'label'     => esc_attr__( 'Hover Color', 'wp-easycart' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_product_title a:hover, {{WRAPPER}} .ec_product_title_type1 a:hover, {{WRAPPER}} .ec_product_title_type2 a:hover, {{WRAPPER}} .ec_product_title_type3 a:hover, {{WRAPPER}} .ec_product_title_type4 a:hover, {{WRAPPER}} .ec_product_title_type5 a:hover, {{WRAPPER}} .ec_product_title:hover' => 'color: {{VALUE}} !important;',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'title_typography',
				'label'    => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_product_title a, {{WRAPPER}} .ec_product_title_type1 a, {{WRAPPER}} .ec_product_title_type2 a, {{WRAPPER}} .ec_product_title_type3 a, {{WRAPPER}} .ec_product_title_type4 a, {{WRAPPER}} .ec_product_title_type5 a, {{WRAPPER}} .ec_product_title',
			)
		);

		$this->add_responsive_control(
			'title_margin',
			array(
				'label'      => esc_attr__( 'Margin', 'wp-easycart' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ec_product_title, {{WRAPPER}} .ec_product_title_type1, {{WRAPPER}} .ec_product_title_type2, {{WRAPPER}} .ec_product_title_type3, {{WRAPPER}} .ec_product_title_type4, {{WRAPPER}} .ec_product_title_type5, {{WRAPPER}} .ec_product_title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);

		$this->end_controls_section();

		// ============================================================
		// STYLE TAB: PRODUCT PRICE
		// ============================================================
		$this->start_controls_section(
			'section_style_product_price',
			array(
				'label' => esc_attr__( 'Product Price', 'wp-easycart' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'price_color',
			array(
				'label'     => esc_attr__( 'Price Color', 'wp-easycart' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_price_type1, {{WRAPPER}} .ec_price_type2, {{WRAPPER}} .ec_price_type3, {{WRAPPER}} .ec_price_type4, {{WRAPPER}} .ec_price_type5, {{WRAPPER}} .ec_price_type6' => 'color: {{VALUE}} !important;',
				),
			)
		);

		$this->add_control(
			'sale_price_color',
			array(
				'label'     => esc_attr__( 'Original Price Color (Strikethrough)', 'wp-easycart' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_list_price_type1, {{WRAPPER}} .ec_list_price_type2, {{WRAPPER}} .ec_list_price_type3, {{WRAPPER}} .ec_list_price_type4, {{WRAPPER}} .ec_list_price_type5, {{WRAPPER}} .ec_list_price_type6' => 'color: {{VALUE}} !important;',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'price_typography',
				'label'    => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_price_type1, {{WRAPPER}} .ec_price_type2, {{WRAPPER}} .ec_price_type3, {{WRAPPER}} .ec_price_type4, {{WRAPPER}} .ec_price_type5, {{WRAPPER}} .ec_price_type6, {{WRAPPER}} .ec_list_price_type1, {{WRAPPER}} .ec_list_price_type2, {{WRAPPER}} .ec_list_price_type3, {{WRAPPER}} .ec_list_price_type4, {{WRAPPER}} .ec_list_price_type5, {{WRAPPER}} .ec_list_price_type6',
			)
		);

		$this->add_responsive_control(
			'price_margin',
			array(
				'label'      => esc_attr__( 'Margin', 'wp-easycart' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ec_price_container_type1, {{WRAPPER}} .ec_price_container_type2, {{WRAPPER}} .ec_price_container_type3, {{WRAPPER}} .ec_price_container_type4, {{WRAPPER}} .ec_price_container_type5, {{WRAPPER}} .ec_price_container_type6' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		// ============================================================
		// STYLE TAB: ADD TO CART BUTTON
		// ============================================================
		$this->start_controls_section(
			'section_style_add_to_cart',
			array(
				'label' => esc_attr__( 'Add to Cart Button', 'wp-easycart' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->start_controls_tabs( 'tabs_cart_button_style' );

		$this->start_controls_tab(
			'tab_cart_button_normal',
			array(
				'label' => esc_attr__( 'Normal', 'wp-easycart' ),
			)
		);

		$this->add_control(
			'cart_button_color',
			array(
				'label'     => esc_attr__( 'Text Color', 'wp-easycart' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_product_addtocart, {{WRAPPER}} .ec_product_addtocart_container a' => 'color: {{VALUE}} !important;',
				),
			)
		);

		$this->add_control(
			'cart_button_bg_color',
			array(
				'label'     => esc_attr__( 'Background Color', 'wp-easycart' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_product_addtocart' => 'background-color: {{VALUE}} !important;',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tab_cart_button_hover',
			array(
				'label' => esc_attr__( 'Hover', 'wp-easycart' ),
			)
		);

		$this->add_control(
			'cart_button_hover_color',
			array(
				'label'     => esc_attr__( 'Text Color', 'wp-easycart' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_product_addtocart:hover, {{WRAPPER}} .ec_product_addtocart:hover a' => 'color: {{VALUE}} !important;',
				),
			)
		);

		$this->add_control(
			'cart_button_hover_bg_color',
			array(
				'label'     => esc_attr__( 'Background Color', 'wp-easycart' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_product_addtocart:hover' => 'background-color: {{VALUE}} !important;',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'      => 'cart_button_typography',
				'label'     => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector'  => '{{WRAPPER}} .ec_product_addtocart a, {{WRAPPER}} .ec_product_addtocart_container a',
				'separator' => 'before',
			)
		);

		$this->add_control(
			'cart_button_border_radius',
			array(
				'label'      => esc_attr__( 'Border Radius', 'wp-easycart' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ec_product_addtocart' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'cart_button_padding',
			array(
				'label'      => esc_attr__( 'Padding', 'wp-easycart' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ec_product_addtocart' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		// ============================================================
		// STYLE TAB: PRODUCT RATING
		// ============================================================
		$this->start_controls_section(
			'section_style_product_rating',
			array(
				'label' => esc_attr__( 'Product Rating', 'wp-easycart' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'rating_star_color',
			array(
				'label'     => esc_attr__( 'Star Color', 'wp-easycart' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_product_star_on' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'rating_star_empty_color',
			array(
				'label'     => esc_attr__( 'Empty Star Color', 'wp-easycart' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_product_star_off' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'rating_margin',
			array(
				'label'      => esc_attr__( 'Margin', 'wp-easycart' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ec_product_star_rating' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		// ============================================================
		// STYLE TAB: QUICK VIEW BUTTON
		// ============================================================
		$this->start_controls_section(
			'section_style_quick_view',
			array(
				'label' => esc_attr__( 'Quick View Button', 'wp-easycart' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'quickview_color',
			array(
				'label'     => esc_attr__( 'Text Color', 'wp-easycart' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_product_quickview input' => 'color: {{VALUE}} !important;',
				),
			)
		);

		$this->add_control(
			'quickview_bg_color',
			array(
				'label'     => esc_attr__( 'Background Color', 'wp-easycart' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_product_quickview input' => 'background-color: {{VALUE}} !important;',
				),
			)
		);

		$this->add_control(
			'quickview_hover_color',
			array(
				'label'     => esc_attr__( 'Hover Text Color', 'wp-easycart' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_product_quickview input:hover' => 'color: {{VALUE}} !important;',
				),
			)
		);

		$this->add_control(
			'quickview_hover_bg_color',
			array(
				'label'     => esc_attr__( 'Hover Background Color', 'wp-easycart' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_product_quickview input:hover' => 'background-color: {{VALUE}} !important;',
				),
			)
		);

		$this->end_controls_section();

		// ============================================================
		// STYLE TAB: PAGING
		// ============================================================
		$this->start_controls_section(
			'section_style_paging',
			array(
				'label' => esc_attr__( 'Paging & Sort Bar', 'wp-easycart' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
 
		// --- Bar Text ---
		$this->add_control(
			'paging_text_color',
			array(
				'label'     => esc_attr__( 'Text Color', 'wp-easycart' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_product_page_sort, {{WRAPPER}} .ec_product_page_showing, {{WRAPPER}} .ec_product_page_perpage' => 'color: {{VALUE}};',
				),
			)
		);
 
		$this->add_control(
			'paging_link_color',
			array(
				'label'     => esc_attr__( 'Link Color', 'wp-easycart' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_product_page_sort a, {{WRAPPER}} .ec_product_page_perpage a, {{WRAPPER}} .ec_num_page' => 'color: {{VALUE}} !important;',
				),
			)
		);
 
		// --- Page Buttons Heading ---
		$this->add_control(
			'paging_buttons_heading',
			array(
				'label'     => esc_attr__( 'Page Buttons', 'wp-easycart' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);
 
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'paging_button_typography',
				'label'    => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_num_page, {{WRAPPER}} .ec_num_page_selected, {{WRAPPER}} .ec_product_page_perpage > a',
				'exclude'  => array( 'line_height' ),
			)
		);
 
		$this->add_responsive_control(
			'paging_button_size',
			array(
				'label'      => esc_attr__( 'Button Size', 'wp-easycart' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min'  => 16,
						'max'  => 60,
						'step' => 1,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .ec_num_page, {{WRAPPER}} .ec_num_page_selected, {{WRAPPER}} .ec_product_page_perpage > a' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}; display: inline-flex; align-items: center; justify-content: center; line-height: 1;',
					'{{WRAPPER}} .ec_product_page_perpage, {{WRAPPER}} .ec_product_page_sort' => 'display: inline-flex; align-items: center; line-height: normal;',
					'{{WRAPPER}} .ec_product_page_perpage > span' => 'float: none;',
					'{{WRAPPER}} .ec_product_page_showing' => 'display: inline-flex; align-items: center;',
				),
			)
		);
 
		$this->add_responsive_control(
			'paging_button_spacing',
			array(
				'label'      => esc_attr__( 'Button Spacing', 'wp-easycart' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min'  => 0,
						'max'  => 20,
						'step' => 1,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .ec_num_page, {{WRAPPER}} .ec_num_page_selected, {{WRAPPER}} .ec_product_page_perpage > a' => 'margin: {{SIZE}}{{UNIT}};',
				),
			)
		);
 
		$this->add_control(
			'paging_button_border_radius',
			array(
				'label'      => esc_attr__( 'Border Radius', 'wp-easycart' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ec_num_page, {{WRAPPER}} .ec_num_page_selected, {{WRAPPER}} .ec_product_page_perpage > a' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
 
		// --- Default / Active Tabs ---
		$this->start_controls_tabs( 'tabs_paging_button_style' );
 
		// -- Default Tab --
		$this->start_controls_tab(
			'tab_paging_button_default',
			array(
				'label' => esc_attr__( 'Default', 'wp-easycart' ),
			)
		);
 
		$this->add_control(
			'paging_button_color',
			array(
				'label'     => esc_attr__( 'Text Color', 'wp-easycart' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_num_page, {{WRAPPER}} .ec_product_page_perpage > a' => 'color: {{VALUE}} !important;',
				),
			)
		);
 
		$this->add_control(
			'paging_button_bg_color',
			array(
				'label'     => esc_attr__( 'Background Color', 'wp-easycart' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_num_page, {{WRAPPER}} .ec_product_page_perpage > a' => 'background-color: {{VALUE}} !important;',
				),
			)
		);
 
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'paging_button_border',
				'label'    => esc_attr__( 'Border', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_num_page, {{WRAPPER}} .ec_product_page_perpage > a',
			)
		);
 
		$this->end_controls_tab();
 
		// -- Active Tab --
		$this->start_controls_tab(
			'tab_paging_button_active',
			array(
				'label' => esc_attr__( 'Active', 'wp-easycart' ),
			)
		);
 
		$this->add_control(
			'paging_active_color',
			array(
				'label'     => esc_attr__( 'Text Color', 'wp-easycart' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_num_page_selected, {{WRAPPER}} .ec_num_page:hover, {{WRAPPER}} .ec_product_page_perpage > a.ec_selected, {{WRAPPER}} .ec_product_page_perpage > a:hover' => 'color: {{VALUE}} !important;',
				),
			)
		);
 
		$this->add_control(
			'paging_active_bg_color',
			array(
				'label'     => esc_attr__( 'Background Color', 'wp-easycart' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_num_page_selected, {{WRAPPER}} .ec_num_page:hover, {{WRAPPER}} .ec_product_page_perpage > a.ec_selected, {{WRAPPER}} .ec_product_page_perpage > a:hover' => 'background-color: {{VALUE}} !important;',
				),
			)
		);
 
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'paging_button_active_border',
				'label'    => esc_attr__( 'Border', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_num_page_selected, {{WRAPPER}} .ec_num_page:hover, {{WRAPPER}} .ec_product_page_perpage > a.ec_selected, {{WRAPPER}} .ec_product_page_perpage > a:hover',
			)
		);
 
		$this->end_controls_tab();
 
		$this->end_controls_tabs();
 
		// --- Sort Dropdown Heading ---
		$this->add_control(
			'paging_sort_heading',
			array(
				'label'     => esc_attr__( 'Sort Dropdown', 'wp-easycart' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);
 
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'paging_sort_typography',
				'label'    => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_product_page_sort select',
			)
		);
 
		$this->add_control(
			'paging_sort_color',
			array(
				'label'     => esc_attr__( 'Text Color', 'wp-easycart' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_product_page_sort select' => 'color: {{VALUE}};',
				),
			)
		);
 
		$this->add_control(
			'paging_sort_bg_color',
			array(
				'label'     => esc_attr__( 'Background Color', 'wp-easycart' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_product_page_sort select' => 'background-color: {{VALUE}};',
				),
			)
		);
 
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'paging_sort_border',
				'label'    => esc_attr__( 'Border', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_product_page_sort select',
			)
		);
 
		$this->add_control(
			'paging_sort_border_radius',
			array(
				'label'      => esc_attr__( 'Border Radius', 'wp-easycart' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ec_product_page_sort select' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
 
		$this->add_responsive_control(
			'paging_sort_padding',
			array(
				'label'      => esc_attr__( 'Padding', 'wp-easycart' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ec_product_page_sort select' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
 
		$this->end_controls_section();

		// ============================================================
		// STYLE TAB: SIDEBAR
		// ============================================================
		$this->start_controls_section(
			'section_style_sidebar',
			array(
				'label'     => esc_attr__( 'Sidebar', 'wp-easycart' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'sidebar' => 'yes',
				),
			)
		);

		$this->add_control(
			'sidebar_bg_color',
			array(
				'label'     => esc_attr__( 'Background Color', 'wp-easycart' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_product_page_sidebar' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'sidebar_title_color',
			array(
				'label'     => esc_attr__( 'Title Color', 'wp-easycart' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_product_sidebar_group_title' => 'color: {{VALUE}} !important;',
				),
			)
		);

		$this->add_control(
			'sidebar_link_color',
			array(
				'label'     => esc_attr__( 'Link Color', 'wp-easycart' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_product_page_sidebar a' => 'color: {{VALUE}} !important;',
				),
			)
		);

		$this->add_control(
			'sidebar_link_hover_color',
			array(
				'label'     => esc_attr__( 'Link Hover Color', 'wp-easycart' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_product_page_sidebar a:hover' => 'color: {{VALUE}} !important;',
				),
			)
		);

		$this->add_responsive_control(
			'sidebar_padding',
			array(
				'label'      => esc_attr__( 'Padding', 'wp-easycart' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ec_product_page_sidebar' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render store widget control output in the editor.
	 */
	protected function render() {
		$atts = $this->get_settings_for_display();
		include( EC_PLUGIN_DIRECTORY . '/admin/elementor/wp-easycart-elementor-store-widget.php' );
	}
}