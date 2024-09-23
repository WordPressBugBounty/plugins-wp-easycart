<?php
/**
 * WP EasyCart Product Details Price Widget for Elementor
 *
 * @category Class
 * @package  Wp_Easycart_Elementor_Product_Details_Price_Widget
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
 * WP EasyCart Product Details Price Widget for Elementor
 *
 * @category Class
 * @package  Wp_Easycart_Elementor_Product_Details_Price_Widget
 * @author   WP EasyCart
 */
class Wp_Easycart_Elementor_Product_Details_Price_Widget extends \Elementor\Widget_Base {

	/**
	 * Get product details price widget name.
	 */
	public function get_name() {
		return 'wp_easycart_product_details_price';
	}

	/**
	 * Get product details price widget title.
	 */
	public function get_title() {
		return esc_attr__( 'WP EasyCart Product Price', 'wp-easycart' );
	}

	/**
	 * Get product details price widget icon.
	 */
	public function get_icon() {
		return 'eicon-product-price';
	}

	/**
	 * Get product details price widget categories.
	 */
	public function get_categories() {
		return array( 'wp-easycart-elements' );
	}

	/**
	 * Get product details price widget keywords.
	 */
	public function get_keywords() {
		return array( 'price', 'wp-easycart' );
	}

	/**
	 * Enqueue product details price widget scripts and styles.
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
				'label' => esc_attr__( 'Price', 'wp-easycart' ),
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

		$this->add_control(
			'show_price',
			array(
				'type'  => Controls_Manager::SWITCHER,
				'label' => esc_attr__( 'Enable Price', 'wp-easycart' ),
				'default'   => 'yes',
			)
		);

		$this->add_control(
			'show_list_price',
			array(
				'type'  => Controls_Manager::SWITCHER,
				'label' => esc_attr__( 'Enable Discount Price', 'wp-easycart' ),
				'default'   => 'yes',
			)
		);

		$this->end_controls_section();
		
		$this->start_controls_section(
			'style_section_price_container',
			array(
				'label' => esc_attr__( 'Price Container', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
			)
		);
		
		$this->add_control(
			'price_container_width_percentage',
			array(
				'label' => esc_attr__( 'Width', 'wp-easycart' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array( '%' ),
				'range' => array(
					'%' => array(
						'min' => 1,
						'max' => 100,
					),
				),
				'default' => array(
					'unit' => '%',
					'size' => 60,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_single_price' => 'width: {{SIZE}}{{UNIT}};',
				),
				'condition' => array(
					'enable_review_form' => array( 'yes' ),
				),
			)
		);

		$this->add_control(
			'price_container_background_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Background Color', 'wp-easycart' ),
				'default' => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_single_price' => 'background-color: {{VALUE}}',
				),
			)
		);
		
		$this->add_control(
			'price_container_padding',
			array(
				'label' => esc_attr__( 'Padding', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 15,
					'right' => 0,
					'bottom' => 0,
					'left' => 0,
					'unit' => 'px',
					'isLinked' => false,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_single_price' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);
		
		$this->add_control(
			'price_container_margin',
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
					'{{WRAPPER}} .ec_details_single_price' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);

		$this->add_control(
			'wpec_pw_price_container_border_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Border Color', 'wp-easycart' ),
				'default' => '#CCCCCC',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_single_price' => 'border-color: {{VALUE}}',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'price_container_border',
				'default' => array(
					'type' => 'solid',
					'top' => 1,
					'right' => 0,
					'bottom' => 0,
					'left' => 0,
					'unit' => 'px',
					'isLinked' => false,
				),
				'selector' => '{{WRAPPER}} .ec_details_single_price',
			)
		);
		
		$this->add_control(
			'price_container_border_radius',
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
					'{{WRAPPER}} .ec_details_single_price' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'style_section_price',
			array(
				'label' => esc_attr__( 'Standard Price', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'show_price' => array( 'yes' ),
				),
			)
		);
		
		$this->add_control(
			'price_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Color', 'wp-easycart' ),
				'default' => ( get_option( 'ec_option_details_main_color' ) != '' ) ? get_option( 'ec_option_details_main_color' ) : '#333333',
				'selectors' => array(
					'{{WRAPPER}} .ec_product_price_ele' => 'color: {{VALUE}} !important;',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'price_font',
				'label' => esc_attr__( 'Typography', 'wp-easycart' ),
				'fields_options' => array(),
				'selector' => '{{WRAPPER}} .ec_product_price_ele',
			)
		);
		
		$this->add_responsive_control(
			'price_align',
			array(
				'label' => esc_attr__( 'Alignment', 'wp-easycart' ),
				'type' => Controls_Manager::CHOOSE,
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
					'{{WRAPPER}} .ec_product_price_ele' => 'text-align: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'price_width',
			array(
				'label'     => esc_attr__( 'Width', 'wp-easycart' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'inline',
				'options'   => array(
					'inline' => esc_attr__( 'Default', 'wp-easycart' ),
					'block' => esc_attr__( 'Full Width', 'wp-easycart' ),
					'initial' => esc_attr__( 'Custom', 'wp-easycart' ),
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_product_price_ele' => 'display: {{VALUE}};',
				),
			)
		);
		
		$this->add_control(
			'price_width_percentage',
			array(
				'label' => esc_attr__( 'Width', 'wp-easycart' ),
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
					'unit' => '%',
					'size' => 100,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_product_price_ele' => 'float:left; display:block; width: {{SIZE}}{{UNIT}};',
				),
				'condition' => array(
					'price_width' => array( 'initial' ),
				),
			)
		);
		
		$this->add_control(
			'price_padding',
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
					'{{WRAPPER}} .ec_product_price_ele' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_control(
			'price_margin',
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
					'{{WRAPPER}} .ec_product_price_ele' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);

		$this->add_control(
			'wpec_pw_price_border_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Border Color', 'wp-easycart' ),
				'default' => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_product_price_ele' => 'border-color: {{VALUE}}',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'price_border',
				'selector' => '{{WRAPPER}} .ec_product_price_ele',
			)
		);
		
		$this->add_control(
			'price_border_radius',
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
					'{{WRAPPER}} .ec_product_price_ele' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'style_section_sale_price',
			array(
				'label' => esc_attr__( 'Sale Price', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'show_list_price' => array( 'yes' ),
				),
			)
		);
		
		$this->add_control(
			'sale_price_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Color', 'wp-easycart' ),
				'default' => ( get_option( 'ec_option_details_main_color' ) != '' ) ? get_option( 'ec_option_details_main_color' ) : '#333333',
				'selectors' => array(
					'{{WRAPPER}} .ec_product_sale_price_ele' => 'color: {{VALUE}} !important;',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'sale_price_font',
				'label' => esc_attr__( 'Typography', 'wp-easycart' ),
				'fields_options' => array(
					'font_weight' => array(
						'default' => '700',
					),
				),
				'selector' => '{{WRAPPER}} .ec_product_sale_price_ele',
			)
		);
		
		$this->add_responsive_control(
			'sale_price_align',
			array(
				'label' => esc_attr__( 'Alignment', 'wp-easycart' ),
				'type' => Controls_Manager::CHOOSE,
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
					'{{WRAPPER}} .ec_product_sale_price_ele' => 'text-align: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'sale_price_width',
			array(
				'label'     => esc_attr__( 'Width', 'wp-easycart' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'inline',
				'options'   => array(
					'inline' => esc_attr__( 'Default', 'wp-easycart' ),
					'block' => esc_attr__( 'Full Width', 'wp-easycart' ),
					'initial' => esc_attr__( 'Custom', 'wp-easycart' ),
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_product_sale_price_ele' => 'display: {{VALUE}};',
				),
			)
		);
		
		$this->add_control(
			'sale_price_width_percentage',
			array(
				'label' => esc_attr__( 'Width', 'wp-easycart' ),
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
					'unit' => '%',
					'size' => 100,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_product_sale_price_ele' => 'float:left; display:block; width: {{SIZE}}{{UNIT}};',
				),
				'condition' => array(
					'sale_price_width' => array( 'initial' ),
				),
			)
		);
		
		$this->add_control(
			'sale_price_padding',
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
					'{{WRAPPER}} .ec_product_sale_price_ele' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_control(
			'sale_price_margin',
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
					'{{WRAPPER}} .ec_product_sale_price_ele' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);

		$this->add_control(
			'wpec_pw_sale_price_border_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Border Color', 'wp-easycart' ),
				'default' => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_product_sale_price_ele' => 'border-color: {{VALUE}}',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'sale_price_border',
				'selector' => '{{WRAPPER}} .ec_product_sale_price_ele',
			)
		);
		
		$this->add_control(
			'sale_price_border_radius',
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
					'{{WRAPPER}} .ec_product_sale_price_ele' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'style_section_previous_price',
			array(
				'label' => esc_attr__( 'Previous Price', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'show_list_price' => array( 'yes' ),
				),
			)
		);
		
		$this->add_control(
			'previous_price_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Color', 'wp-easycart' ),
				'default' => ( get_option( 'ec_option_details_main_color' ) != '' ) ? get_option( 'ec_option_details_main_color' ) : '#333333',
				'selectors' => array(
					'{{WRAPPER}} .ec_product_old_price_ele' => 'color: {{VALUE}} !important;',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'previous_price_font',
				'label' => esc_attr__( 'Typography', 'wp-easycart' ),
				'fields_options' => array(
					'text_decoration' => array(
						'default' => 'line-through',
					),
				),
				'selector' => '{{WRAPPER}} .ec_product_old_price_ele',
			)
		);
		
		$this->add_responsive_control(
			'previous_price_align',
			array(
				'label' => esc_attr__( 'Alignment', 'wp-easycart' ),
				'type' => Controls_Manager::CHOOSE,
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
					'{{WRAPPER}} .ec_product_old_price_ele' => 'text-align: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'previous_price_width',
			array(
				'label'     => esc_attr__( 'Width', 'wp-easycart' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'inline',
				'options'   => array(
					'inline' => esc_attr__( 'Default', 'wp-easycart' ),
					'block' => esc_attr__( 'Full Width', 'wp-easycart' ),
					'initial' => esc_attr__( 'Custom', 'wp-easycart' ),
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_product_old_price_ele' => 'display: {{VALUE}};',
				),
			)
		);
		
		$this->add_control(
			'previous_price_width_percentage',
			array(
				'label' => esc_attr__( 'Width', 'wp-easycart' ),
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
					'unit' => '%',
					'size' => 100,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_product_old_price_ele' => 'float:left; display:block; width: {{SIZE}}{{UNIT}};',
				),
				'condition' => array(
					'previous_price_width' => array( 'initial' ),
				),
			)
		);
		
		$this->add_control(
			'previous_price_padding',
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
					'{{WRAPPER}} .ec_product_old_price_ele' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_control(
			'previous_price_margin',
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
					'{{WRAPPER}} .ec_product_old_price_ele' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);

		$this->add_control(
			'wpec_pw_previous_price_border_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Border Color', 'wp-easycart' ),
				'default' => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_product_old_price_ele' => 'border-color: {{VALUE}}',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'previous_price_border',
				'selector' => '{{WRAPPER}} .ec_product_old_price_ele',
			)
		);
		
		$this->add_control(
			'previous_price_border_radius',
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
					'{{WRAPPER}} .ec_product_old_price_ele' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
		include( EC_PLUGIN_DIRECTORY . '/admin/elementor/wp-easycart-elementor-product-details-price-widget.php' );
	}
}
