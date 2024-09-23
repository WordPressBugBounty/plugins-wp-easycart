<?php
/**
 * WP EasyCart Product Details Featured Products Widget for Elementor
 *
 * @category Class
 * @package  Wp_Easycart_Elementor_Product_Details_Featured_Products_Widget
 * @author   WP EasyCart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Elementor\Controls_Manager;
use Elementor\Scheme_Color;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use ELementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Image_Size;
use Elementor\Utils;
use Elementor\Wp_Easycart_Controls_Manager;

/**
 * WP EasyCart Product Details Featured Products Widget for Elementor
 *
 * @category Class
 * @package  Wp_Easycart_Elementor_Product_Details_Featured_Products_Widget
 * @author   WP EasyCart
 */
class Wp_Easycart_Elementor_Product_Details_Featured_Products_Widget extends \Elementor\Widget_Base {

	/**
	 * Get product details featured products widget name.
	 */
	public function get_name() {
		return 'wp_easycart_product_details_featured_products';
	}

	/**
	 * Get product details featured products widget title.
	 */
	public function get_title() {
		return esc_attr__( 'WP EasyCart Product Featured Items', 'wp-easycart' );
	}

	/**
	 * Get product details featured products widget icon.
	 */
	public function get_icon() {
		return 'eicon-slider-album';
	}

	/**
	 * Get product details featured products widget categories.
	 */
	public function get_categories() {
		return array( 'wp-easycart-elements' );
	}

	/**
	 * Get product details featured products widget keywords.
	 */
	public function get_keywords() {
		return array( 'featured products', 'wp-easycart' );
	}

	/**
	 * Enqueue product details featured products widget scripts and styles.
	 */
	public function get_script_depends() {
		$scripts = array( 'isotope-pkgd', 'jquery-hoverIntent' );
		if ( ( isset( $_REQUEST['action'] ) && 'elementor' == $_REQUEST['action'] ) || isset( $_REQUEST['elementor-preview'] ) ) {
			$scripts[] = 'wpeasycart_js';
		}
		return $scripts;
	}

	/**
	 * Setup search widget controls.
	 */
	protected function _register_controls() {
		$pages = get_pages();
		$pages_select = array();
		foreach ( $pages as $page ) {
			$pages_select[ $page->ID ] = $page->post_title;
		}
		$this->start_controls_section(
			'section_products',
			array(
				'label' => esc_attr__( 'Featured Products', 'wp-easycart' ),
			)
		);

		$this->add_control(
			'use_post_id',
			array(
				'type'  => Controls_Manager::SWITCHER,
				'label' => esc_attr__( 'Use in Template', 'wp-easycart' ),
				'default'   => false,
			)
		);

		$this->add_control(
			'product_id',
			array(
				'label'       => esc_attr__( 'Select Product', 'wp-easycart' ),
				'type'        => Wp_Easycart_Controls_Manager::WPECAJAXSELECT2,
				'options'     => 'easycart_product',
				'label_block' => true,
				'multiple'    => false,
				'condition' => array(
					'use_post_id!' => 'yes',
				),
			)
		);

		$this->add_responsive_control(
			'enable_product1',
			array(
				'type'      => Controls_Manager::SWITCHER,
				'label'     => esc_attr__( 'Enable Product 1', 'wp-easycart' ),
				'default'   => 'yes',
			)
		);

		$this->add_responsive_control(
			'enable_product2',
			array(
				'type'      => Controls_Manager::SWITCHER,
				'label'     => esc_attr__( 'Enable Product 2', 'wp-easycart' ),
				'default'   => 'yes',
			)
		);

		$this->add_responsive_control(
			'enable_product3',
			array(
				'type'      => Controls_Manager::SWITCHER,
				'label'     => esc_attr__( 'Enable Product 3', 'wp-easycart' ),
				'default'   => 'yes',
			)
		);

		$this->add_responsive_control(
			'enable_product4',
			array(
				'type'      => Controls_Manager::SWITCHER,
				'label'     => esc_attr__( 'Enable Product 4', 'wp-easycart' ),
				'default'   => 'yes',
			)
		);

		$this->add_control(
			'visible_options',
			array(
				'label' => esc_attr__( 'Visible Items', 'wp-easycart' ),
				'type' => Controls_Manager::SELECT2,
				'multiple' => true,
				'default' => array(
					'title',
					'category',
					'price',
					'rating',
					'cart',
					'quickview',
					'desc',
				),
				'description' => esc_attr__( 'Short description only where available.', 'wp-easycart' ),
				'options' => array(
					'title' => esc_attr__( 'Title', 'wp-easycart' ),
					'category' => esc_attr__( 'Categories', 'wp-easycart' ),
					'price' => esc_attr__( 'Price', 'wp-easycart' ),
					'rating' => esc_attr__( 'Rating', 'wp-easycart' ),
					'cart' => esc_attr__( 'Add To Cart', 'wp-easycart' ),
					'quickview' => esc_attr__( 'Quick View', 'wp-easycart' ),
					'desc' => esc_attr__( 'Short Description', 'wp-easycart' ),
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'ec_fpw_style_section_container',
			array(
				'label' => esc_attr__( 'Featured Products', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'visible_options' => 'title',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'ec_fpw_container_background',
				'types' => array( 'classic', 'gradient' ),
				'default' => array(
					'type' => 'classic',
					'color' => '#ffffff',
				),
				'selector' => '{{WRAPPER}} .ec_details_related_products > li > div',
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'ec_fpw_container_border',
				'fields_options' => array(
					'border' => array(
						'default' => 'solid',
					),
					'width' => array(
						'default' => array(
							'top' => 1,
							'right' => 1,
							'bottom' => 1,
							'left' => 1,
							'unit' => 'px',
							'isLinked' => true,
						),
					),
					'color' => array(
						'default' => '#eaeaea',
					),
				),
				'selector' => '{{WRAPPER}} .ec_details_related_products > li > div',
			)
		);
		
		$this->add_control(
			'ec_fpw_container_border_radius',
			array(
				'label' => esc_attr__( 'Border Radius', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 10,
					'right' => 10,
					'bottom' => 0,
					'left' => 0,
					'unit' => 'px',
					'isLinked' => false,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_related_products > li > div' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'ec_fpw_style_section_image',
			array(
				'label' => esc_attr__( 'Image', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
			)
		);
		
		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'ec_fpw_image_background',
				'types' => array( 'classic', 'gradient' ),
				'default' => array(
					'type' => 'classic',
					'color' => '',
				),
				'selector' => '{{WRAPPER}} .ec_details_related_products .ec_product_image_ele img',
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'ec_fpw_image_border',
				'fields_options' => array(
					'border' => array(
						'default' => 'none',
					),
					'width' => array(
						'default' => array(
							'top' => 0,
							'right' => 0,
							'bottom' => 0,
							'left' => 0,
							'unit' => 'px',
							'isLinked' => true,
						),
					),
					'color' => array(
						'default' => '#eaeaea',
					),
				),
				'selector' => '{{WRAPPER}} .ec_details_related_products .ec_product_image_ele img',
			)
		);
		
		$this->add_control(
			'ec_fpw_image_border_radius',
			array(
				'label' => esc_attr__( 'Border Radius', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 10,
					'right' => 10,
					'bottom' => 0,
					'left' => 0,
					'unit' => 'px',
					'isLinked' => false,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_related_products .ec_product_image_ele img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'ec_fpw_style_section_title',
			array(
				'label' => esc_attr__( 'Title', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'visible_options' => 'title',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_fpw_title_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Color', 'wp-easycart' ),
				'default' => ( get_option( 'ec_option_details_main_color' ) != '' ) ? get_option( 'ec_option_details_main_color' ) : '#333333',
				'selectors' => array(
					'{{WRAPPER}} .ec_product_title > a' => 'color: {{VALUE}} !important;',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'ec_fpw_title_font',
				'label' => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_product_title > a',
			)
		);
		
		$this->add_responsive_control(
			'ec_fpw_title_align',
			array(
				'label' => esc_attr__( 'Alignment', 'wp-easycart' ),
				'type' => Controls_Manager::CHOOSE,
				'default' => 'center',
				'options' => array(
					'left' => array(
						'title' => esc_attr__( 'Left', 'wp-easycart' ),
						'icon' => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_attr__( 'Center', 'wp-easycart' ),
						'icon' => 'eicon-text-align-center',
					),
					'right'  => array(
						'title' => esc_attr__( 'Right', 'wp-easycart' ),
						'icon'  => 'eicon-text-align-right',
					),
					'justify' => array(
						'title' => esc_attr__( 'Justify', 'wp-easycart' ),
						'icon' => 'eicon-text-align-justify',
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_product_title' => 'text-align: {{VALUE}};',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'ec_fpw_title_background',
				'types' => array( 'classic', 'gradient' ),
				'default' => array(
					'type' => 'classic',
					'color' => '#efefef',
				),
				'selector' => '{{WRAPPER}} .ec_product_title',
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'ec_fpw_title_border',
				'fields_options' => array(
					'border' => array(
						'default' => 'solid',
					),
					'width' => array(
						'default' => array(
							'top' => 1,
							'right' => 0,
							'bottom' => 0,
							'left' => 0,
							'unit' => 'px',
							'isLinked' => false,
						),
					),
					'color' => array(
						'default' => '#eaeaea',
					),
				),
				'selector' => '{{WRAPPER}} .ec_product_title',
			)
		);
		
		$this->add_control(
			'ec_fpw_title_border_radius',
			array(
				'label' => esc_attr__( 'Border Radius', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 0,
					'right' => 0,
					'bottom' => 0,
					'left' => 0,
					'unit' => 'px',
					'isLinked' => true,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_product_title' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_fpw_title_padding',
			array(
				'label' => esc_attr__( 'Padding', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 10,
					'right' => 10,
					'bottom' => 0,
					'left' => 10,
					'unit' => 'px',
					'isLinked' => false,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_product_title' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_fpw_title_margin',
			array(
				'label' => esc_attr__( 'Margin', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 0,
					'right' => 0,
					'bottom' => 8,
					'left' => 0,
					'unit' => 'px',
					'isLinked' => false,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_product_title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'ec_fpw_style_section_categories',
			array(
				'label' => esc_attr__( 'Categories', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'visible_options' => 'category',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_fpw_categories_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Color', 'wp-easycart' ),
				'default' => ( get_option( 'ec_option_details_main_color' ) != '' ) ? get_option( 'ec_option_details_main_color' ) : '#333333',
				'selectors' => array(
					'{{WRAPPER}} .ec_product_categories > a' => 'color: {{VALUE}} !important;',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'ec_fpw_categories_font',
				'label' => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_product_categories > a',
			)
		);
		
		$this->add_responsive_control(
			'ec_fpw_categories_align',
			array(
				'label' => esc_attr__( 'Alignment', 'wp-easycart' ),
				'type' => Controls_Manager::CHOOSE,
				'default' => 'center',
				'options' => array(
					'left' => array(
						'title' => esc_attr__( 'Left', 'wp-easycart' ),
						'icon' => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_attr__( 'Center', 'wp-easycart' ),
						'icon' => 'eicon-text-align-center',
					),
					'right'  => array(
						'title' => esc_attr__( 'Right', 'wp-easycart' ),
						'icon'  => 'eicon-text-align-right',
					),
					'justify' => array(
						'title' => esc_attr__( 'Justify', 'wp-easycart' ),
						'icon' => 'eicon-text-align-justify',
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_product_categories' => 'text-align: {{VALUE}};',
				),
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'ec_fpw_style_section_rating',
			array(
				'label' => esc_attr__( 'Rating', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'visible_options' => 'rating',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_fpw_rating_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Active Color', 'wp-easycart' ),
				'default' => ( get_option( 'ec_option_details_main_color' ) != '' ) ? get_option( 'ec_option_details_main_color' ) : '#333333',
				'selectors' => array(
					'{{WRAPPER}} .ec_product_star_on_ele' => 'border-bottom-color: {{VALUE}}; color: {{VALUE}}',
					'{{WRAPPER}} .ec_product_star_on_ele:before' => 'border-bottom-color: {{VALUE}};',
					'{{WRAPPER}} .ec_product_star_on_ele:after' => 'border-bottom-color: {{VALUE}}; color: {{VALUE}}',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_fpw_rating_color_inactive',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Inactive Color', 'wp-easycart' ),
				'default' => '#CCCCCC',
				'selectors' => array(
					'{{WRAPPER}} .ec_product_star_off_ele' => 'border-bottom-color: {{VALUE}}; color: {{VALUE}}',
					'{{WRAPPER}} .ec_product_star_off_ele:before' => 'border-bottom-color: {{VALUE}};',
					'{{WRAPPER}} .ec_product_star_off_ele:after' => 'border-bottom-color: {{VALUE}}; color: {{VALUE}}',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_fpw_rating_align',
			array(
				'label' => esc_attr__( 'Alignment', 'wp-easycart' ),
				'type' => Controls_Manager::CHOOSE,
				'default' => 'center',
				'options' => array(
					'left' => array(
						'title' => esc_attr__( 'Left', 'wp-easycart' ),
						'icon' => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_attr__( 'Center', 'wp-easycart' ),
						'icon' => 'eicon-text-align-center',
					),
					'right'  => array(
						'title' => esc_attr__( 'Right', 'wp-easycart' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_product_stars_type' => 'text-align: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'ec_fpw_rating_spacing',
			array(
				'label' => esc_attr__( 'Star Spacing', 'wp-easycart' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'range' => array(
					'px' => array(
						'min' => 0,
						'max' => 200,
						'step' => 1,
					),
					'%' => array(
						'min' => 0,
						'max' => 100,
					),
				),
				'default' => array(
					'unit' => 'px',
					'size' => 1,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_product_star_on_ele' => 'margin-right: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .ec_product_star_off_ele' => 'margin-right: {{SIZE}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_fpw_rating_padding',
			array(
				'label' => esc_attr__( 'Padding', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 10,
					'right' => 10,
					'bottom' => 10,
					'left' => 10,
					'unit' => 'px',
					'isLinked' => true,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_product_stars_type' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_fpw_rating_margin',
			array(
				'label' => esc_attr__( 'Margin', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors' => array(
					'{{WRAPPER}} .ec_product_stars_type' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'ec_fpw_style_section_price',
			array(
				'label' => esc_attr__( 'Price', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'visible_options' => 'price',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_fpw_price_align',
			array(
				'label' => esc_attr__( 'Alignment', 'wp-easycart' ),
				'type' => Controls_Manager::CHOOSE,
				'default' => 'center',
				'options' => array(
					'left' => array(
						'title' => esc_attr__( 'Left', 'wp-easycart' ),
						'icon' => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_attr__( 'Center', 'wp-easycart' ),
						'icon' => 'eicon-text-align-center',
					),
					'right'  => array(
						'title' => esc_attr__( 'Right', 'wp-easycart' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'default' => 'left',
				'selectors' => array(
					'{{WRAPPER}} .ec_price_container_type1' => 'text-align: {{VALUE}};',
				),
			)
		);
		
		$this->add_control(
			'ec_fpw_price_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Color', 'wp-easycart' ),
				'default' => '#EE3B3B',
				'selectors' => array(
					'{{WRAPPER}} .ec_price_type1' => 'color: {{VALUE}} !important;',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'ec_fpw_price_font',
				'label' => esc_attr__( 'Typography', 'wp-easycart' ),
				'fields_options' => array(),
				'selector' => '{{WRAPPER}} .ec_price_type1',
			)
		);
		
		$this->add_control(
			'ec_fpw_previous_price_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Previous Price Color', 'wp-easycart' ),
				'default' => '#999999',
				'selectors' => array(
					'{{WRAPPER}} .ec_list_price_type1' => 'color: {{VALUE}} !important;',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'ec_fpw_previous_price_font',
				'label' => esc_attr__( 'Previous Price Typography', 'wp-easycart' ),
				'fields_options' => array(
					'text_decoration' => array(
						'default' => 'line-through',
					),
				),
				'selector' => '{{WRAPPER}} .ec_list_price_type1',
			)
		);
		
		$this->add_control(
			'ec_fpw_price_padding',
			array(
				'label' => esc_attr__( 'Padding', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 0,
					'right' => 0,
					'bottom' => 0,
					'left' => 0,
					'unit' => 'px',
					'isLinked' => true,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_price_container_type1' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_control(
			'ec_fpw_price_margin',
			array(
				'label' => esc_attr__( 'Margin', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 0,
					'right' => 0,
					'bottom' => 0,
					'left' => 0,
					'unit' => 'px',
					'isLinked' => true,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_price_container_type1' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'ec_fpw_price_border',
				'selector' => '{{WRAPPER}} .ec_price_container_type1',
			)
		);
		
		$this->add_control(
			'ec_fpw_price_border_radius',
			array(
				'label' => esc_attr__( 'Border Radius', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 0,
					'right' => 0,
					'bottom' => 0,
					'left' => 0,
					'unit' => 'px',
					'isLinked' => true,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_price_container_type1' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'ec_fpw_style_section_button',
			array(
				'label' => esc_attr__( 'Button', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'visible_options' => 'cart',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'ec_fpw_button_font',
				'label' => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_product_addtocart_container_ele a.ec_add_to_cart_button',
			)
		);
		
		$this->start_controls_tabs(
			'ec_fpw_button_style_tabs'
		);

		$this->start_controls_tab(
			'ec_fpw_button_style_normal_tab',
			array(
				'label' => esc_attr__( 'Normal', 'wp-easycart' ),
			)
		);

		$this->add_responsive_control(
			'ec_fpw_button_text_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Text Color', 'wp-easycart' ),
				'default' => '#FFFFFF',
				'selectors' => array(
					'{{WRAPPER}} .ec_product_addtocart_container_ele a.ec_add_to_cart_button' => 'color: {{VALUE}}',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'ec_fpw_button_background',
				'types' => array( 'classic', 'gradient' ),
				'default' => array(
					'type' => 'classic',
					'color' => ( get_option( 'ec_option_details_main_color' ) != '' ) ? get_option( 'ec_option_details_main_color' ) : '#333333',
				),
				'selector' => '{{WRAPPER}} .ec_product_addtocart_container_ele a.ec_add_to_cart_button',
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'ec_fpw_button_style_hover_tab',
			array(
				'label' => esc_attr__( 'Hover', 'wp-easycart' ),
			)
		);

		$this->add_responsive_control(
			'ec_fpw_button_text_color_hover',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Text Color', 'wp-easycart' ),
				'default' => '#FFFFFF',
				'selectors' => array(
					'{{WRAPPER}} .ec_product_addtocart_container_ele a.ec_add_to_cart_button:hover' => 'color: {{VALUE}}',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'ec_fpw_button_background_hover',
				'types' => array( 'classic', 'gradient' ),
				'default' => array(
					'type' => 'classic',
					'color' => ( get_option( 'ec_option_details_second_color' ) != '' ) ? get_option( 'ec_option_details_second_color' ) : '#111111',
				),
				'selector' => '{{WRAPPER}} .ec_product_addtocart_container_ele a.ec_add_to_cart_button:hover',
			)
		);

		$this->add_responsive_control(
			'ec_fpw_button_border_color_hover',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Border Color', 'wp-easycart' ),
				'default' => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_product_addtocart_container_ele a.ec_add_to_cart_button:hover' => 'border-color: {{VALUE}}',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();
		
		$this->add_responsive_control(
			'ec_fpw_button_align',
			array(
				'label' => esc_attr__( 'Alignment', 'wp-easycart' ),
				'type' => Controls_Manager::CHOOSE,
				'default' => 'center',
				'options' => array(
					'left' => array(
						'title' => esc_attr__( 'Left', 'wp-easycart' ),
						'icon' => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_attr__( 'Center', 'wp-easycart' ),
						'icon' => 'eicon-text-align-center',
					),
					'right'  => array(
						'title' => esc_attr__( 'Right', 'wp-easycart' ),
						'icon'  => 'eicon-text-align-right',
					),
					'justify' => array(
						'title' => esc_attr__( 'Justify', 'wp-easycart' ),
						'icon' => 'eicon-text-align-justify',
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_product_addtocart_container_ele' => 'text-align: {{VALUE}};',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'ec_fpw_button_border',
				'default' => 'none',
				'selector' => '{{WRAPPER}} .ec_product_addtocart_container_ele a.ec_add_to_cart_button',
			)
		);
		
		$this->add_control(
			'ec_fpw_button_border_radius',
			array(
				'label' => esc_attr__( 'Border Radius', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 5,
					'right' => 5,
					'bottom' => 5,
					'left' => 5,
					'unit' => 'px',
					'isLinked' => true,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_product_addtocart_container_ele a.ec_add_to_cart_button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_fpw_button_padding',
			array(
				'label' => esc_attr__( 'Padding', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 8,
					'right' => 16,
					'bottom' => 8,
					'left' => 16,
					'unit' => 'px',
					'isLinked' => false,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_product_addtocart_container_ele a.ec_add_to_cart_button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_fpw_button_margin',
			array(
				'label' => esc_attr__( 'Margin', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 0,
					'right' => 0,
					'bottom' => 10,
					'left' => 0,
					'unit' => 'px',
					'isLinked' => false,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_product_addtocart_container_ele' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'ec_fpw_style_section_checkout_button',
			array(
				'label' => esc_attr__( 'Checkout Button', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'visible_options' => 'cart',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'ec_fpw_button_checkout_font',
				'label' => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_product_addtocart_container_ele a.ec_added_to_cart_button',
			)
		);
		
		$this->start_controls_tabs(
			'ec_fpw_button_checkout_style_tabs'
		);

		$this->start_controls_tab(
			'ec_fpw_button_checkout_style_normal_tab',
			array(
				'label' => esc_attr__( 'Normal', 'wp-easycart' ),
			)
		);

		$this->add_responsive_control(
			'ec_fpw_button_checkout_text_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Text Color', 'wp-easycart' ),
				'default' => '#FFFFFF',
				'selectors' => array(
					'{{WRAPPER}} .ec_product_addtocart_container_ele a.ec_added_to_cart_button' => 'color: {{VALUE}}',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'ec_fpw_button_checkout_background',
				'types' => array( 'classic', 'gradient' ),
				'default' => array(
					'type' => 'classic',
					'color' => ( get_option( 'ec_option_details_main_color' ) != '' ) ? get_option( 'ec_option_details_main_color' ) : '#333333',
				),
				'selector' => '{{WRAPPER}} .ec_product_addtocart_container_ele a.ec_added_to_cart_button',
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'ec_fpw_button_checkout_style_hover_tab',
			array(
				'label' => esc_attr__( 'Hover', 'wp-easycart' ),
			)
		);

		$this->add_responsive_control(
			'ec_fpw_button_checkout_text_color_hover',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Text Color', 'wp-easycart' ),
				'default' => '#FFFFFF',
				'selectors' => array(
					'{{WRAPPER}} .ec_product_addtocart_container_ele a.ec_added_to_cart_button:hover' => 'color: {{VALUE}}',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'ec_fpw_button_checkout_background_hover',
				'types' => array( 'classic', 'gradient' ),
				'default' => array(
					'type' => 'classic',
					'color' => ( get_option( 'ec_option_details_second_color' ) != '' ) ? get_option( 'ec_option_details_second_color' ) : '#111111',
				),
				'selector' => '{{WRAPPER}} .ec_product_addtocart_container_ele a.ec_added_to_cart_button:hover',
			)
		);

		$this->add_responsive_control(
			'ec_fpw_button_checkout_border_color_hover',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Border Color', 'wp-easycart' ),
				'default' => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_product_addtocart_container_ele a.ec_added_to_cart_button:hover' => 'border-color: {{VALUE}}',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();
		
		$this->add_responsive_control(
			'ec_fpw_button_checkout_align',
			array(
				'label' => esc_attr__( 'Alignment', 'wp-easycart' ),
				'type' => Controls_Manager::CHOOSE,
				'default' => 'center',
				'options' => array(
					'left' => array(
						'title' => esc_attr__( 'Left', 'wp-easycart' ),
						'icon' => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_attr__( 'Center', 'wp-easycart' ),
						'icon' => 'eicon-text-align-center',
					),
					'right'  => array(
						'title' => esc_attr__( 'Right', 'wp-easycart' ),
						'icon'  => 'eicon-text-align-right',
					),
					'justify' => array(
						'title' => esc_attr__( 'Justify', 'wp-easycart' ),
						'icon' => 'eicon-text-align-justify',
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_product_addtocart_container_ele' => 'text-align: {{VALUE}};',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'ec_fpw_button_checkout_border',
				'default' => 'none',
				'selector' => '{{WRAPPER}} .ec_product_addtocart_container_ele a.ec_added_to_cart_button',
			)
		);
		
		$this->add_control(
			'ec_fpw_button_checkout_border_radius',
			array(
				'label' => esc_attr__( 'Border Radius', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 5,
					'right' => 5,
					'bottom' => 5,
					'left' => 5,
					'unit' => 'px',
					'isLinked' => true,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_product_addtocart_container_ele a.ec_added_to_cart_button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_fpw_button_checkout_padding',
			array(
				'label' => esc_attr__( 'Padding', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 8,
					'right' => 16,
					'bottom' => 8,
					'left' => 16,
					'unit' => 'px',
					'isLinked' => false,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_product_addtocart_container_ele a.ec_added_to_cart_button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'ec_fpw_style_section_quickview_button',
			array(
				'label' => esc_attr__( 'Quickview Button', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'visible_options' => 'quickview',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'ec_fpw_button_quickview_font',
				'label' => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_product_quickview_ele > input',
			)
		);
		
		$this->start_controls_tabs(
			'ec_fpw_button_quickview_style_tabs'
		);

		$this->start_controls_tab(
			'ec_fpw_button_quickview_style_normal_tab',
			array(
				'label' => esc_attr__( 'Normal', 'wp-easycart' ),
			)
		);

		$this->add_responsive_control(
			'ec_fpw_button_quickview_text_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Text Color', 'wp-easycart' ),
				'default' => '#FFFFFF',
				'selectors' => array(
					'{{WRAPPER}} .ec_product_quickview_ele > input' => 'color: {{VALUE}}',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'ec_fpw_button_quickview_background',
				'types' => array( 'classic', 'gradient' ),
				'default' => array(
					'type' => 'classic',
					'color' => ( get_option( 'ec_option_details_main_color' ) != '' ) ? get_option( 'ec_option_details_main_color' ) : '#333333',
				),
				'selector' => '{{WRAPPER}} .ec_product_quickview_ele > input',
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'ec_fpw_button_quickview_style_hover_tab',
			array(
				'label' => esc_attr__( 'Hover', 'wp-easycart' ),
			)
		);

		$this->add_responsive_control(
			'ec_fpw_button_quickview_text_color_hover',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Text Color', 'wp-easycart' ),
				'default' => '#FFFFFF',
				'selectors' => array(
					'{{WRAPPER}} .ec_product_quickview_ele > input:hover' => 'color: {{VALUE}}',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'ec_fpw_button_quickview_background_hover',
				'types' => array( 'classic', 'gradient' ),
				'default' => array(
					'type' => 'classic',
					'color' => ( get_option( 'ec_option_details_second_color' ) != '' ) ? get_option( 'ec_option_details_second_color' ) : '#111111',
				),
				'selector' => '{{WRAPPER}} .ec_product_quickview_ele > input:hover',
			)
		);

		$this->add_responsive_control(
			'ec_fpw_button_quickview_border_color_hover',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Border Color', 'wp-easycart' ),
				'default' => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_product_quickview_ele > input:hover' => 'border-color: {{VALUE}}',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();
		
		$this->add_responsive_control(
			'ec_fpw_button_quickview_align',
			array(
				'label' => esc_attr__( 'Alignment', 'wp-easycart' ),
				'type' => Controls_Manager::CHOOSE,
				'default' => 'center',
				'options' => array(
					'left' => array(
						'title' => esc_attr__( 'Left', 'wp-easycart' ),
						'icon' => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_attr__( 'Center', 'wp-easycart' ),
						'icon' => 'eicon-text-align-center',
					),
					'right'  => array(
						'title' => esc_attr__( 'Right', 'wp-easycart' ),
						'icon'  => 'eicon-text-align-right',
					),
					'justify' => array(
						'title' => esc_attr__( 'Justify', 'wp-easycart' ),
						'icon' => 'eicon-text-align-justify',
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_product_quickview_ele' => 'text-align: {{VALUE}};',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'ec_fpw_button_quickview_border',
				'default' => 'none',
				'selector' => '{{WRAPPER}} .ec_product_quickview_ele > input',
			)
		);
		
		$this->add_control(
			'ec_fpw_button_quickview_border_radius',
			array(
				'label' => esc_attr__( 'Border Radius', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 5,
					'right' => 5,
					'bottom' => 5,
					'left' => 5,
					'unit' => 'px',
					'isLinked' => true,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_product_quickview_ele > input' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_fpw_button_quickview_padding',
			array(
				'label' => esc_attr__( 'Padding', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 8,
					'right' => 16,
					'bottom' => 8,
					'left' => 16,
					'unit' => 'px',
					'isLinked' => false,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_product_quickview_ele > input' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'ec_fpw_style_section_desc',
			array(
				'label' => esc_attr__( 'Short Description', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'visible_options' => 'desc',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_fpw_desc_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Color', 'wp-easycart' ),
				'default' => ( get_option( 'ec_option_details_main_color' ) != '' ) ? get_option( 'ec_option_details_main_color' ) : '#333333',
				'selectors' => array(
					'{{WRAPPER}} .ec_product_basic_description_ele' => 'color: {{VALUE}} !important;',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'ec_fpw_desc_font',
				'label' => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_product_basic_description_ele',
			)
		);
		
		$this->add_responsive_control(
			'ec_fpw_desc_align',
			array(
				'label' => esc_attr__( 'Alignment', 'wp-easycart' ),
				'type' => Controls_Manager::CHOOSE,
				'default' => 'left',
				'options' => array(
					'left' => array(
						'title' => esc_attr__( 'Left', 'wp-easycart' ),
						'icon' => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_attr__( 'Center', 'wp-easycart' ),
						'icon' => 'eicon-text-align-center',
					),
					'right'  => array(
						'title' => esc_attr__( 'Right', 'wp-easycart' ),
						'icon'  => 'eicon-text-align-right',
					),
					'justify' => array(
						'title' => esc_attr__( 'Justify', 'wp-easycart' ),
						'icon' => 'eicon-text-align-justify',
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_product_basic_description_ele' => 'text-align: {{VALUE}};',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'ec_fpw_desc_background',
				'types' => array( 'classic', 'gradient' ),
				'default' => array(
					'type' => 'classic',
					'color' => '#efefef',
				),
				'selector' => '{{WRAPPER}} .ec_product_basic_description_ele',
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'ec_fpw_desc_border',
				'default' => 'none',
				'selector' => '{{WRAPPER}} .ec_product_basic_description_ele',
			)
		);
		
		$this->add_control(
			'ec_fpw_desc_border_radius',
			array(
				'label' => esc_attr__( 'Border Radius', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 0,
					'right' => 0,
					'bottom' => 0,
					'left' => 0,
					'unit' => 'px',
					'isLinked' => true,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_product_basic_description_ele' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_fpw_desc_padding',
			array(
				'label' => esc_attr__( 'Padding', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 20,
					'right' => 20,
					'bottom' => 20,
					'left' => 20,
					'unit' => 'px',
					'isLinked' => true,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_product_basic_description_ele' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_fpw_desc_margin',
			array(
				'label' => esc_attr__( 'Margin', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 0,
					'right' => 0,
					'bottom' => 0,
					'left' => 0,
					'unit' => 'px',
					'isLinked' => true,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_product_basic_description_ele' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);
		
		$this->end_controls_section();
	}

	/**
	 * Render search widget control output in the editor.
	 */
	protected function render() {
		$atts = $this->get_settings_for_display();
		include( EC_PLUGIN_DIRECTORY . '/admin/elementor/wp-easycart-elementor-product-details-featured-products-widget.php' );
	}
}
