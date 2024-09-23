<?php
/**
 * WP EasyCart Product Add To Cart Widget for Elementor
 *
 * @category Class
 * @package  Wp_Easycart_Elementor_Product_Addtocart_Widget
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
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;

/**
 * WP EasyCart Product Add To Cart Widget for Elementor
 *
 * @category Class
 * @package  Wp_Easycart_Elementor_Product_Addtocart_Widget
 * @author   WP EasyCart
 */
class Wp_Easycart_Elementor_Product_Addtocart_Widget extends \Elementor\Widget_Base {

	/**
	 * Get product add to cart widget name.
	 */
	public function get_name() {
		return 'wp_easycart_product_addtocart';
	}

	/**
	 * Get product add to cart widget title.
	 */
	public function get_title() {
		return esc_attr__( 'WP EasyCart Add to Cart', 'wp-easycart' );
	}

	/**
	 * Get product add to cart widget icon.
	 */
	public function get_icon() {
		return 'eicon-product-add-to-cart';
	}

	/**
	 * Get product add to cart widget categories.
	 */
	public function get_categories() {
		return array( 'wp-easycart-elements' );
	}

	/**
	 * Get product add to cart widget keywords.
	 */
	public function get_keywords() {
		return array( 'products', 'shop', 'wp-easycart' );
	}

	/**
	 * Enqueue product add to cart widget scripts and styles.
	 */
	public function get_script_depends() {
		$scripts = array( 'isotope-pkgd', 'jquery-hoverIntent' );
		if ( ( isset( $_REQUEST['action'] ) && 'elementor' == $_REQUEST['action'] ) || isset( $_REQUEST['elementor-preview'] ) ) {
			$scripts[] = 'wpeasycart_js';
		}
		return $scripts;
	}

	/**
	 * Setup product add to cart widget controls.
	 */
	protected function _register_controls() {

		$this->start_controls_section(
			'section_addtocart_v2',
			array(
				'label' => esc_attr__( 'Add to Cart (v2)', 'wp-easycart' ),
			)
		);

		$this->add_control(
			'enable_v2',
			array(
				'type'  => Controls_Manager::SWITCHER,
				'label' => esc_attr__( 'Enable Version 2 (more features)', 'wp-easycart' ),
				'default' => 'no',
			)
		);

		$this->add_control(
			'use_post_id',
			array(
				'type'  => Controls_Manager::SWITCHER,
				'label' => esc_attr__( 'Use in Template', 'wp-easycart' ),
				'default'   => 'no',
				'condition' => array(
					'enable_v2' => 'yes',
				),
			)
		);

		$this->add_control(
			'product_id_v2',
			array(
				'label'       => esc_attr__( 'Select Product', 'wp-easycart' ),
				'type'        => Wp_Easycart_Controls_Manager::WPECAJAXSELECT2,
				'options'     => 'easycart_product',
				'label_block' => true,
				'multiple'    => false,
				'condition' => array(
					'use_post_id!' => 'yes',
					'enable_v2' => 'yes',
				),
			)
		);

		$this->add_control(
			'enable_your_price',
			array(
				'type'  => Controls_Manager::SWITCHER,
				'label' => esc_attr__( 'Enable Your Price', 'wp-easycart' ),
				'default' => 'yes',
				'condition' => array(
					'enable_v2' => 'yes',
				),
			)
		);

		$this->add_control(
			'enable_quantity_v2',
			array(
				'type'  => Controls_Manager::SWITCHER,
				'label' => esc_attr__( 'Enable Quantity Selection', 'wp-easycart' ),
				'default' => 'yes',
				'condition' => array(
					'enable_v2' => 'yes',
				),
			)
		);

		$this->end_controls_section();
		
		$this->start_controls_section(
			'section_products',
			array(
				'label' => esc_attr__( 'Add to Cart (v1)', 'wp-easycart' ),
				'condition' => array(
					'enable_v2!' => 'yes',
				),
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
			)
		);

		$this->add_control(
			'background_add',
			array(
				'label'       => esc_attr__( 'Background Add', 'wp-easycart' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'featured',
				'options'     => array(
					'0' => esc_attr__( 'No, Redirect to Cart', 'wp-easycart' ),
					'1'  => esc_attr__( 'Yes, Add in Background', 'wp-easycart' ),
				)
			)
		);

		$this->add_control(
			'enable_quantity',
			array(
				'type'  => Controls_Manager::SWITCHER,
				'label' => esc_attr__( 'Display Quantity', 'wp-easycart' ),
				'default'   => 0,
			)
		);

		$this->add_control(
			'button_width',
			array(
				'type'    => Controls_Manager::SLIDER,
				'label'   => esc_attr__( 'Button Width (px)', 'wp-easycart' ),
				'default' => array(
					'unit' => 'px',
					'size' => 150,
				),
				'size_units' => array( 'px' ),
				'range'   => array(
					'px' => array(
						'step' => 1,
						'min'  => 0,
						'max'  => 1000,
					),
				),
			)
		);

		$this->add_control(
			'button_font',
			array(
				'type'  => Controls_Manager::FONT,
				'label' => esc_attr__( 'Button Font', 'wp-easycart' ),
				'default' => ( get_option( 'ec_option_font_main' ) != '' ) ? get_option( 'ec_option_font_main' ) : 'Lato',
			)
		);

		$this->add_control(
			'button_bg_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Button Background Color', 'wp-easycart' ),
				'default' => ( get_option( 'ec_option_details_main_color' ) != '' ) ? get_option( 'ec_option_details_main_color' ) : '#333333',
			)
		);

		$this->add_control(
			'button_text_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Button Text Color', 'wp-easycart' ),
				'default' => '#ffffff',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'ec_adtw_style_section_button_container',
			array(
				'label' => esc_attr__( 'Add to Cart Group', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'enable_v2' => array( 'yes' ),
					'enable_quantity_v2' => array( 'yes' ),
				),
			)
		);

		$this->add_responsive_control(
			'ec_adtw_button_columns',
			array(
				'label'     => esc_attr__( 'Format', 'wp-easycart' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'row',
				'options'   => array(
					'column' => esc_attr__( '1 Column', 'wp-easycart' ),
					'column-reverse' => esc_attr__( '1 Column (Reverse)', 'wp-easycart' ),
					'row' => esc_attr__( '2 Columns', 'wp-easycart' ),
					'row-reverse' => esc_attr__( '2 Columns (Reverse)', 'wp-easycart' ),
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_add_to_cart_group_ele' => 'flex-direction: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'ec_adtw_button_column_gap',
			array(
				'type'    => Controls_Manager::SLIDER,
				'label'   => esc_attr__( 'Column Gap', 'wp-easycart' ),
				'default' => array(
					'unit' => 'px',
					'size' => 5,
				),
				'size_units' => array( 'px' ),
				'range'   => array(
					'px' => array(
						'step' => 1,
						'min'  => 0,
						'max'  => 100,
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_add_to_cart_group_ele' => 'column-gap: {{SIZE}}{{UNIT}};',
				),
				'condition' => array(
					'ec_adtw_button_columns' => array( 'row', 'row-reverse' ),
				),
			)
		);

		$this->add_responsive_control(
			'ec_adtw_button_column_width',
			array(
				'type'    => Controls_Manager::SLIDER,
				'label'   => esc_attr__( 'Button Column Width', 'wp-easycart' ),
				'default' => array(
					'unit' => '%',
					'size' => 50,
				),
				'size_units' => array( 'px', '%' ),
				'range'   => array(
					'px' => array(
						'step' => 1,
						'min'  => 5,
						'max'  => 500,
					),
					'%' => array(
						'step' => 1,
						'min'  => 5,
						'max'  => 95,
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_add_to_cart_group_ele > .ec_details_quantity' => 'width: calc( 100% - {{SIZE}}{{UNIT}} );',
					'{{WRAPPER}} .ec_details_add_to_cart_group_ele > .ec_details_add_to_cart_ele' => 'width: {{SIZE}}{{UNIT}};',
				),
				'condition' => array(
					'ec_adtw_button_columns' => array( 'row', 'row-reverse' ),
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_main_button_container_align',
			array(
				'label' => esc_attr__( 'Alignment', 'wp-easycart' ),
				'type' => Controls_Manager::CHOOSE,
				'default' => 'left',
				'options' => array(
					'flex-start' => array(
						'title' => esc_attr__( 'Left', 'wp-easycart' ),
						'icon' => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_attr__( 'Center', 'wp-easycart' ),
						'icon' => 'eicon-text-align-center',
					),
					'flex-end'  => array(
						'title' => esc_attr__( 'Right', 'wp-easycart' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_add_to_cart_group_ele > .ec_details_quantity' => 'width:100% !important;',
					'{{WRAPPER}} .ec_details_add_to_cart_group_ele > .ec_details_add_to_cart_ele' => 'width:100% !important;',
					'{{WRAPPER}} .ec_details_add_to_cart_group_ele' => 'display:flex; justify-content: {{VALUE}}; align-items: {{VALUE}};',
				),
				'condition' => array(
					'ec_adtw_button_columns' => array( 'column', 'column-reverse' ),
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_button_container_margin',
			array(
				'label' => esc_attr__( 'Container Margin', 'wp-easycart' ),
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
					'{{WRAPPER}} .ec_details_add_to_cart_group_ele' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'ec_adtw_style_section_button',
			array(
				'label' => esc_attr__( 'Add to Cart Button', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'enable_v2' => array( 'yes' ),
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'ec_adtw_button_font',
				'label' => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_details_add_to_cart_ele input',
				'fields_options' => array(
					'typography' => array(
						'default' => Global_Typography::TYPOGRAPHY_PRIMARY
					),
					'font_size' => array(
						'default' => array(
							'size' => 16,
							'unit' => 'px',
						),
					),
					'font_weight' => array(
						'default' => 'bold',
					),
				),
			)
		);

		$this->add_responsive_control(
			'list_item_button_width',
			array(
				'label'     => esc_attr__( 'Button Width', 'wp-easycart' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => '100%',
				'options'   => array(
					'initial' => esc_attr__( 'Default', 'wp-easycart' ),
					'100%' => esc_attr__( 'Fill Container', 'wp-easycart' ),
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_add_to_cart_ele' => 'width: {{VALUE}};',
					'{{WRAPPER}} .ec_details_add_to_cart_ele input' => 'width: {{VALUE}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_button_align',
			array(
				'label' => esc_attr__( 'Text Alignment', 'wp-easycart' ),
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
					'{{WRAPPER}} .ec_details_add_to_cart_ele input' => 'text-align: {{VALUE}};',
				),
				'condition' => array(
					'list_item_button_width' => array( '100%' ),
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_button_container_align',
			array(
				'label' => esc_attr__( 'Button Alignment', 'wp-easycart' ),
				'type' => Controls_Manager::CHOOSE,
				'default' => 'left',
				'options' => array(
					'flex-start' => array(
						'title' => esc_attr__( 'Left', 'wp-easycart' ),
						'icon' => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_attr__( 'Center', 'wp-easycart' ),
						'icon' => 'eicon-text-align-center',
					),
					'flex-end'  => array(
						'title' => esc_attr__( 'Right', 'wp-easycart' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_add_to_cart_ele' => 'display:flex; justify-content: {{VALUE}}; align-items: {{VALUE}};',
				),
				'condition' => array(
					'list_item_button_width' => array( 'initial' ),
					'ec_adtw_button_columns' => array( 'row', 'row-reverse' ),
				),
			)
		);

		$this->start_controls_tabs(
			'button_style_tabs'
		);

		$this->start_controls_tab(
			'button_style_normal_tab',
			array(
				'label' => esc_attr__( 'Normal', 'wp-easycart' ),
			)
		);

		$this->add_responsive_control(
			'ec_adtw_button_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Text Color', 'wp-easycart' ),
				'default' => '#FFFFFF',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_add_to_cart_ele input' => 'color: {{VALUE}} !important',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'ec_adtw_button_background',
				'types' => array( 'classic', 'gradient' ),
				'fields_options' => array(
					'background' => array(
						'default' => 'classic',
						'label' => esc_attr__( 'Background Type', 'wp-easycart' ),
					),
					'color' => array(
						'default' => ( get_option( 'ec_option_details_main_color' ) != '' ) ? get_option( 'ec_option_details_main_color' ) : '#333333',
						'label' => esc_attr__( 'Background Color', 'wp-easycart' ),
					),
					'image' => array(
						'label' => esc_attr__( 'Background Image', 'wp-easycart' ),
					),
				),
				'selector' => '{{WRAPPER}} .ec_details_add_to_cart_ele input',
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'button_style_hover_tab',
			array(
				'label' => esc_attr__( 'Hover', 'wp-easycart' ),
			)
		);

		$this->add_responsive_control(
			'ec_adtw_button_color_hover',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Text Color (hover)', 'wp-easycart' ),
				'default' => '#FFFFFF',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_add_to_cart_ele input:hover' => 'color: {{VALUE}} !important',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'ec_adtw_button_background_hover',
				'types' => array( 'classic', 'gradient' ),
				'fields_options' => array(
					'background' => array(
						'default' => 'classic',
						'label' => esc_attr__( 'Background Type (hover)', 'wp-easycart' ),
					),
					'color' => array(
						'default' => ( get_option( 'ec_option_details_second_color' ) != '' ) ? get_option( 'ec_option_details_second_color' ) : '#111111',
						'label' => esc_attr__( 'Background Color (hover)', 'wp-easycart' ),
					),
					'image' => array(
						'label' => esc_attr__( 'Background Image (hover)', 'wp-easycart' ),
					),
				),
				'selector' => '{{WRAPPER}} .ec_details_add_to_cart_ele input:hover',
			)
		);

		$this->add_responsive_control(
			'ec_adtw_button_border_color_hover',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Border Color (hover)', 'wp-easycart' ),
				'default' => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_add_to_cart_ele input:hover' => 'border-color: {{VALUE}} !important',
				),
				'condition' => array(
					'ec_adtw_button_border_border!' => 'none',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'ec_adtw_button_border',
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
						'label' => esc_attr__( 'Border Width', 'wp-easycart' ),
					),
					'color' => array(
						'label' => esc_attr__( 'Border Color', 'wp-easycart' ),
					),
				),
				'selector' => '{{WRAPPER}} .ec_details_add_to_cart_ele input',
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_text_button_border_radius',
			array(
				'label' => esc_attr__( 'Button Border Radius', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 0,
					'right' => 0,
					'bottom' => 0,
					'left' => 0,
					'unit' => 'px',
					'isLinked' => false,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_add_to_cart_group_ele input[type="submit"]' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_button_padding',
			array(
				'label' => esc_attr__( 'Button Padding', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 9,
					'right' => 9,
					'bottom' => 9,
					'left' => 9,
					'unit' => 'px',
					'isLinked' => true,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_add_to_cart_group_ele input[type="submit"]' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_button_margin',
			array(
				'label' => esc_attr__( 'Button Margin', 'wp-easycart' ),
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
					'{{WRAPPER}} .ec_details_add_to_cart_group_ele input[type="submit"]' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'ec_adtw_style_section_quantity',
			array(
				'label' => esc_attr__( 'Quantity Element Container', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'enable_v2' => array( 'yes' ),
				),
			)
		);

		$this->add_responsive_control(
			'ec_adtw_quantity_width',
			array(
				'type'    => Controls_Manager::SLIDER,
				'label'   => esc_attr__( 'Quantity Group Width', 'wp-easycart' ),
				'default' => array(
					'unit' => '%',
					'size' => 100,
				),
				'size_units' => array( 'px', '%' ),
				'range'   => array(
					'px' => array(
						'step' => 1,
						'min'  => 100,
						'max'  => 1000,
					),
					'%' => array(
						'step' => 1,
						'min'  => 10,
						'max'  => 100,
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_add_to_cart_group_ele .ec_details_quantity' => 'width: {{SIZE}}{{UNIT}} !important;',
				),
				'condition' => array(
					'ec_adtw_button_columns' => array( 'column', 'column-reverse' ),
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_text_quantity_border_radius',
			array(
				'label' => esc_attr__( 'Group Border Radius', 'wp-easycart' ),
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
					'{{WRAPPER}} .ec_details_add_to_cart_group_ele .ec_details_quantity' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .ec_details_add_to_cart_group_ele .ec_details_quantity > .ec_minus' => 'border-top-left-radius: {{TOP}}{{UNIT}}; border-bottom-left-radius: {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .ec_details_add_to_cart_group_ele .ec_details_quantity > .ec_plus' => 'border-top-right-radius: {{RIGHT}}{{UNIT}};border-bottom-right-radius: {{BOTTOM}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_quantity_padding',
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
					'{{WRAPPER}} .ec_details_add_to_cart_group_ele .ec_details_quantity' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_quantity_margin',
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
					'{{WRAPPER}} .ec_details_add_to_cart_group_ele .ec_details_quantity' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'ec_adtw_style_section_quantity_buttons',
			array(
				'label' => esc_attr__( 'Quantity +/- Buttons', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'enable_v2' => array( 'yes' ),
				),
			)
		);

		$this->add_control(
			'ec_adtw_quantity_minus_button_icon',
			array(
				'type'  => Controls_Manager::ICONS,
				'label' => esc_attr__( 'Minus Quantity Icon', 'wp-easycart' ),
				'default' => array(
					'value' => 'fas fa-minus',
					'library' => 'fa-solid',
				),
				'recommended' => array(
					'fa-solid' => array(
						'minus',
						'minus-circle',
						'minus-square',
					),
					'fa-regular' => array(
						'minus',
						'minus-circle',
						'minus-square',
					),
				),
			)
		);

		$this->add_responsive_control(
			'ec_adtw_quantity_minus_font_size',
			array(
				'type'    => Controls_Manager::SLIDER,
				'label'   => esc_attr__( 'Icon Size', 'wp-easycart' ),
				'default' => array(
					'unit' => 'px',
					'size' => 12,
				),
				'size_units' => array( 'px' ),
				'range'   => array(
					'px' => array(
						'step' => 1,
						'min'  => 1,
						'max'  => 100,
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_add_to_cart_group_ele .ec_details_quantity > button.ec_minus i:before' => 'font-size: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'ec_adtw_quantity_plus_button_icon',
			array(
				'type'  => Controls_Manager::ICONS,
				'label' => esc_attr__( 'Plus Quantity Icon', 'wp-easycart' ),
				'default' => array(
					'value' => 'fas fa-plus',
					'library' => 'fa-solid',
				),
				'recommended' => array(
					'fa-solid' => array(
						'plus',
						'plus-circle',
						'plus-square',
					),
					'fa-regular' => array(
						'plus',
						'plus-circle',
						'plus-square',
					),
				),
			)
		);

		$this->add_responsive_control(
			'ec_adtw_quantity_plus_font_size',
			array(
				'type'    => Controls_Manager::SLIDER,
				'label'   => esc_attr__( 'Icon Size', 'wp-easycart' ),
				'default' => array(
					'unit' => 'px',
					'size' => 12,
				),
				'size_units' => array( 'px' ),
				'range'   => array(
					'px' => array(
						'step' => 1,
						'min'  => 1,
						'max'  => 100,
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_add_to_cart_group_ele .ec_details_quantity > button.ec_plus i:before' => 'font-size: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->start_controls_tabs(
			'quantity_button_style_tabs'
		);

		$this->start_controls_tab(
			'quantity_button_style_normal_tab',
			array(
				'label' => esc_attr__( 'Normal', 'wp-easycart' ),
			)
		);

		$this->add_responsive_control(
			'ec_adtw_quantity_button_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Text Color', 'wp-easycart' ),
				'default' => '#FFFFFF',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_add_to_cart_group_ele .ec_details_quantity > button' => 'color: {{VALUE}} !important',
					'{{WRAPPER}} .ec_details_add_to_cart_group_ele .ec_details_quantity > button > i' => 'color: {{VALUE}} !important',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'ec_adtw_quantity_button_background',
				'types' => array( 'classic', 'gradient' ),
				'fields_options' => array(
					'background' => array(
						'default' => 'classic',
						'label' => esc_attr__( 'Button Background Type', 'wp-easycart' ),
					),
					'color' => array(
						'default' => ( get_option( 'ec_option_details_main_color' ) != '' ) ? get_option( 'ec_option_details_main_color' ) : '#333333',
						'label' => esc_attr__( 'Button Background Color', 'wp-easycart' ),
					),
					'image' => array(
						'label' => esc_attr__( 'Button Background Image', 'wp-easycart' ),
					),
				),
				'selector' => '{{WRAPPER}} .ec_details_add_to_cart_group_ele .ec_details_quantity > button',
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'quantity_button_style_hover_tab',
			array(
				'label' => esc_attr__( 'Hover', 'wp-easycart' ),
			)
		);

		$this->add_responsive_control(
			'ec_adtw_quantity_button_color_hover',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Text Color (hover)', 'wp-easycart' ),
				'default' => '#FFFFFF',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_add_to_cart_group_ele .ec_details_quantity > button:hover' => 'color: {{VALUE}} !important',
					'{{WRAPPER}} .ec_details_add_to_cart_group_ele .ec_details_quantity > button:hover > i' => 'color: {{VALUE}} !important',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'ec_adtw_quantity_button_background_hover',
				'types' => array( 'classic', 'gradient' ),
				'fields_options' => array(
					'background' => array(
						'default' => 'classic',
						'label' => esc_attr__( 'Background Type (hover)', 'wp-easycart' ),
					),
					'color' => array(
						'default' => ( get_option( 'ec_option_details_second_color' ) != '' ) ? get_option( 'ec_option_details_second_color' ) : '#111111',
						'label' => esc_attr__( 'Background Color (hover)', 'wp-easycart' ),
					),
					'image' => array(
						'label' => esc_attr__( 'Background Image (hover)', 'wp-easycart' ),
					),
				),
				'selector' => '{{WRAPPER}} .ec_details_add_to_cart_group_ele .ec_details_quantity > button:hover',
			)
		);

		$this->add_responsive_control(
			'ec_adtw_quantity_button_border_color_hover',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Border Color (hover)', 'wp-easycart' ),
				'default' => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_add_to_cart_group_ele .ec_details_quantity > button:hover' => 'border-color: {{VALUE}} !important',
				),
				'condition' => array(
					'ec_adtw_button_border_border!' => 'none',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'ec_adtw_quantity_button_border',
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
						'label' => esc_attr__( 'Border Width', 'wp-easycart' ),
					),
					'color' => array(
						'label' => esc_attr__( 'Border Color', 'wp-easycart' ),
						'default' => '#cccccc',
					),
				),
				'selector' => '{{WRAPPER}} .ec_details_add_to_cart_group_ele .ec_details_quantity > button',
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_text_quantity_buttons_border_radius',
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
					'{{WRAPPER}} .ec_details_add_to_cart_group_ele .ec_details_quantity > button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'ec_adtw_style_section_quantity_input_box',
			array(
				'label' => esc_attr__( 'Quantity Input', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'enable_v2' => array( 'yes' ),
				),
			)
		);

		$this->add_responsive_control(
			'ec_adtw_quantity_box_width',
			array(
				'type'    => Controls_Manager::SLIDER,
				'label'   => esc_attr__( 'Quantity Box Width', 'wp-easycart' ),
				'default' => array(
					'unit' => '%',
					'size' => 33,
				),
				'size_units' => array( 'px', '%' ),
				'range'   => array(
					'px' => array(
						'step' => 1,
						'min'  => 1,
						'max'  => 1000,
					),
					'%' => array(
						'step' => 1,
						'min'  => 10,
						'max'  => 100,
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_add_to_cart_group_ele .ec_details_quantity > input[type="number"]' => 'width: {{SIZE}}{{UNIT}} !important;',
					'{{WRAPPER}} .ec_details_add_to_cart_group_ele .ec_details_quantity > button' => 'width: calc( ( 100% - {{SIZE}}{{UNIT}} ) / 2 ) !important;',
				),
			)
		);

		$this->add_responsive_control(
			'ec_adtw_quantity_input_box_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Text Color', 'wp-easycart' ),
				'default' => '#333333',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_add_to_cart_group_ele .ec_details_quantity > input[type="number"]' => 'color: {{VALUE}} !important',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'ec_adtw_quantity_input_box_font',
				'label' => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_details_add_to_cart_group_ele .ec_details_quantity > input[type="number"]',
				'fields_options' => array(
					'typography' => array(
						'default' => Global_Typography::TYPOGRAPHY_PRIMARY
					),
					'font_size' => array(
						'default' => array(
							'size' => 16,
							'unit' => 'px',
						),
					),
					'font_weight' => array(
						'default' => '500',
					),
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_quantity_input_box_align',
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
					'{{WRAPPER}} .ec_details_add_to_cart_group_ele .ec_details_quantity > input[type="number"]' => 'text-align: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'ec_adtw_quantity_input_box_background',
				'types' => array( 'classic', 'gradient' ),
				'default' => '',
				'fields_options' => array(
					'background' => array(
						'default' => 'classic',
						'label' => esc_attr__( 'Background Type', 'wp-easycart' ),
					),
					'color' => array(
						'default' => '',
						'label' => esc_attr__( 'Background Color', 'wp-easycart' ),
					),
					'image' => array(
						'label' => esc_attr__( 'Background Image', 'wp-easycart' ),
					),
				),
				'selector' => '{{WRAPPER}} .ec_details_add_to_cart_group_ele .ec_details_quantity > input[type="number"]',
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'ec_adtw_quantity_input_box_border',
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
						'label' => esc_attr__( 'Border Width', 'wp-easycart' ),
					),
					'color' => array(
						'label' => esc_attr__( 'Border Color', 'wp-easycart' ),
					),
				),
				'selector' => '{{WRAPPER}} .ec_details_add_to_cart_group_ele .ec_details_quantity > input[type="number"]',
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_text_quantity_input_border_box_radius',
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
					'{{WRAPPER}} .ec_details_add_to_cart_group_ele .ec_details_quantity > input[type="number"]' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_quantity_input_box_padding',
			array(
				'label' => esc_attr__( 'Padding', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 9,
					'right' => 9,
					'bottom' => 9,
					'left' => 9,
					'unit' => 'px',
					'isLinked' => true,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_add_to_cart_group_ele .ec_details_quantity > input[type="number"]' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_quantity_input_box_margin',
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
					'{{WRAPPER}} .ec_details_add_to_cart_group_ele .ec_details_quantity > input[type="number"]' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'ec_adtw_style_section_label_message',
			array(
				'label' => esc_attr__( 'Variant/Modifier Label', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'enable_v2' => array( 'yes' ),
				),
			)
		);

		$this->add_responsive_control(
			'ec_adtw_label_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Text Color', 'wp-easycart' ),
				'default' => ( get_option( 'ec_option_details_main_color' ) != '' ) ? get_option( 'ec_option_details_main_color' ) : '#333333',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_option_label_ele' => 'color: {{VALUE}} !important',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'ec_adtw_label_font',
				'label' => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_details_option_label_ele',
				'fields_options' => array(
					'typography' => array(
						'default' => Global_Typography::TYPOGRAPHY_PRIMARY
					),
					'font_size' => array(
						'default' => array(
							'size' => 16,
							'unit' => 'px',
						),
					),
					'font_weight' => array(
						'default' => 'bold',
					),
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_label_align',
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
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_option_label_ele' => 'text-align: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'ec_adtw_label_background',
				'types' => array( 'classic', 'gradient' ),
				'fields_options' => array(
					'background' => array(
						'label' => esc_attr__( 'Background Type', 'wp-easycart' ),
					),
					'color' => array(
						'label' => esc_attr__( 'Background Color', 'wp-easycart' ),
					),
					'image' => array(
						'label' => esc_attr__( 'Background Image', 'wp-easycart' ),
					),
				),
				'selector' => '{{WRAPPER}} .ec_details_option_label_ele',
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'ec_adtw_label_border',
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
						'label' => esc_attr__( 'Border Width', 'wp-easycart' ),
					),
					'color' => array(
						'label' => esc_attr__( 'Border Color', 'wp-easycart' ),
					),
				),
				'selector' => '{{WRAPPER}} .ec_details_option_label_ele',
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_label_padding',
			array(
				'label' => esc_attr__( 'Label Padding', 'wp-easycart' ),
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
					'{{WRAPPER}} .ec_details_option_label_ele' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_label_margin',
			array(
				'label' => esc_attr__( 'Label Margin', 'wp-easycart' ),
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
					'{{WRAPPER}} .ec_details_option_label_ele' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'ec_adtw_style_section_swatches_all',
			array(
				'label' => esc_attr__( 'Swatch Group', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'enable_v2' => array( 'yes' ),
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_swatch_image_align',
			array(
				'label' => esc_attr__( 'Alignment', 'wp-easycart' ),
				'type' => Controls_Manager::CHOOSE,
				'default' => 'flex-start',
				'options' => array(
					'flex-start' => array(
						'title' => esc_attr__( 'Left', 'wp-easycart' ),
						'icon' => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_attr__( 'Center', 'wp-easycart' ),
						'icon' => 'eicon-text-align-center',
					),
					'flex-end'  => array(
						'title' => esc_attr__( 'Right', 'wp-easycart' ),
						'icon'  => 'eicon-text-align-right',
					),
					'space-between'  => array(
						'title' => esc_attr__( 'Justify', 'wp-easycart' ),
						'icon'  => 'eicon-text-align-justify',
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_swatches_ele' => 'justify-content: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'ec_adtw_swatch_gap',
			array(
				'type'    => Controls_Manager::SLIDER,
				'label'   => esc_attr__( 'Swatch Column Spacing', 'wp-easycart' ),
				'default' => array(
					'unit' => 'px',
					'size' => 2,
				),
				'size_units' => array( 'px' ),
				'range'   => array(
					'px' => array(
						'step' => 1,
						'min'  => 0,
						'max'  => 50,
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_swatches_ele' => 'column-gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'ec_adtw_swatch_gap_row',
			array(
				'type'    => Controls_Manager::SLIDER,
				'label'   => esc_attr__( 'Swatch Row Spacing', 'wp-easycart' ),
				'default' => array(
					'unit' => 'px',
					'size' => 4,
				),
				'size_units' => array( 'px' ),
				'range'   => array(
					'px' => array(
						'step' => 1,
						'min'  => 0,
						'max'  => 50,
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_swatches_ele' => 'row-gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'ec_adtw_swatches_background',
				'types' => array( 'classic', 'gradient' ),
				'fields_options' => array(
					'background' => array(
						'label' => esc_attr__( 'Background Type', 'wp-easycart' ),
					),
					'color' => array(
						'label' => esc_attr__( 'Background Color', 'wp-easycart' ),
					),
					'image' => array(
						'label' => esc_attr__( 'Background Image', 'wp-easycart' ),
					),
				),
				'selector' => '{{WRAPPER}} .ec_details_swatches_ele',
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'ec_adtw_swatches_border',
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
						'label' => esc_attr__( 'Border Width', 'wp-easycart' ),
					),
					'color' => array(
						'label' => esc_attr__( 'Border Color', 'wp-easycart' ),
					),
				),
				'selector' => '{{WRAPPER}} .ec_details_swatches_ele',
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_swatches_border_radius',
			array(
				'label' => esc_attr__( 'Group Border Radius', 'wp-easycart' ),
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
					'{{WRAPPER}} .ec_details_swatches_ele' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_swatches_padding',
			array(
				'label' => esc_attr__( 'Item Padding', 'wp-easycart' ),
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
					'{{WRAPPER}} .ec_details_swatches_ele' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_swatches_margin',
			array(
				'label' => esc_attr__( 'Item Margin', 'wp-easycart' ),
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
					'{{WRAPPER}} .ec_details_swatches_ele' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'ec_adtw_style_section_swatches_images',
			array(
				'label' => esc_attr__( 'Image Swatches', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'enable_v2' => array( 'yes' ),
				),
			)
		);

		$this->add_responsive_control(
			'ec_adtw_swatch_image_width',
			array(
				'type'    => Controls_Manager::SLIDER,
				'label'   => esc_attr__( 'Image Width (px)', 'wp-easycart' ),
				'default' => array(
					'unit' => 'px',
					'size' => 30,
				),
				'size_units' => array( 'px' ),
				'range'   => array(
					'px' => array(
						'step' => 5,
						'min'  => 5,
						'max'  => 200,
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_swatch_ele:not( .wpeasycart-html-swatch-ele  )' => 'width: {{SIZE}}{{UNIT}} !important;',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'ec_adtw_swatch_image_border',
				'fields_options' => array(
					'border' => array(
						'default' => 'solid',
					),
					'width' => array(
						'default' => array(
							'top' => 2,
							'right' => 2,
							'bottom' => 2,
							'left' => 2,
							'unit' => 'px',
							'isLinked' => true,
						),
						'label' => esc_attr__( 'Border Width', 'wp-easycart' ),
					),
					'color' => array(
						'default' => '#ffffff',
						'label' => esc_attr__( 'Border Color', 'wp-easycart' ),
					),
				),
				'selector' => '{{WRAPPER}} .ec_details_swatch_ele:not( .wpeasycart-html-swatch-ele ) > img',
			)
		);

		$this->add_responsive_control(
			'ec_adtw_swatch_image_border_color_hover',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Border Color (hover)', 'wp-easycart' ),
				'default' => ( get_option( 'ec_option_details_second_color' ) != '' ) ? get_option( 'ec_option_details_second_color' ) : '#666666',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_swatch_ele:not( .wpeasycart-html-swatch-ele ):hover > img' => 'border-color: {{VALUE}} !important',
				),
				'condition' => array(
					'ec_adtw_swatch_image_border_border!' => 'none',
				),
			)
		);

		$this->add_responsive_control(
			'ec_adtw_swatch_image_border_color_selected',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Border Color (selected)', 'wp-easycart' ),
				'default' => ( get_option( 'ec_option_details_main_color' ) != '' ) ? get_option( 'ec_option_details_main_color' ) : '#333333',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_swatch_ele:not( .wpeasycart-html-swatch-ele ).ec_selected > img' => 'border-color: {{VALUE}} !important',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_swatch_image_border_radius',
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
					'{{WRAPPER}} .ec_details_swatch_ele:not( .wpeasycart-html-swatch-ele  )' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_swatch_image_padding',
			array(
				'label' => esc_attr__( 'Item Padding', 'wp-easycart' ),
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
					'{{WRAPPER}} .ec_details_swatch_ele:not( .wpeasycart-html-swatch-ele  )' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_swatch_image_margin',
			array(
				'label' => esc_attr__( 'Item Margin', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 0,
					'right' => 2,
					'bottom' => 0,
					'left' => 0,
					'unit' => 'px',
					'isLinked' => false,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_swatch_ele:not( .wpeasycart-html-swatch-ele  )' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'ec_adtw_style_section_swatches',
			array(
				'label' => esc_attr__( 'HTML Swatches', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'enable_v2' => array( 'yes' ),
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'ec_adtw_swatch_font',
				'label' => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .wpeasycart-html-swatch-ele',
				'fields_options' => array(
					'typography' => array(
						'default' => Global_Typography::TYPOGRAPHY_PRIMARY
					),
					'font_size' => array(
						'default' => array(
							'size' => 16,
							'unit' => 'px',
						),
					),
					'font_weight' => array(
						'default' => 'bold',
					),
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_swatch_align',
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
					'{{WRAPPER}} .wpeasycart-html-swatch-ele' => 'text-align: {{VALUE}};',
				),
			)
		);

		$this->start_controls_tabs(
			'swatch_style_tabs'
		);

		$this->start_controls_tab(
			'swatch_style_normal_tab',
			array(
				'label' => esc_attr__( 'Normal', 'wp-easycart' ),
			)
		);

		$this->add_responsive_control(
			'ec_adtw_swatch_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Text Color', 'wp-easycart' ),
				'default' => '#ffffff',
				'selectors' => array(
					'{{WRAPPER}} .wpeasycart-html-swatch-ele' => 'color: {{VALUE}} !important',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'ec_adtw_swatch_background',
				'types' => array( 'classic', 'gradient' ),
				'fields_options' => array(
					'background' => array(
						'default' => 'classic',
						'label' => esc_attr__( 'Background Type', 'wp-easycart' ),
					),
					'color' => array(
						'default' => ( get_option( 'ec_option_details_main_color' ) != '' ) ? get_option( 'ec_option_details_main_color' ) : '#333333',
						'label' => esc_attr__( 'Background Color', 'wp-easycart' ),
					),
					'image' => array(
						'label' => esc_attr__( 'Background Image', 'wp-easycart' ),
					),
				),
				'selector' => '{{WRAPPER}} .wpeasycart-html-swatch-ele',
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'swatch_style_hover_tab',
			array(
				'label' => esc_attr__( 'Hover', 'wp-easycart' ),
			)
		);

		$this->add_responsive_control(
			'ec_adtw_swatch_color_hover',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Text Color (hover)', 'wp-easycart' ),
				'default' => '#ffffff',
				'selectors' => array(
					'{{WRAPPER}} .wpeasycart-html-swatch-ele' => 'color: {{VALUE}} !important',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'ec_adtw_swatch_background_hover',
				'types' => array( 'classic', 'gradient' ),
				'fields_options' => array(
					'background' => array(
						'default' => 'classic',
						'label' => esc_attr__( 'Background Type (hover)', 'wp-easycart' ),
					),
					'color' => array(
						'default' => ( get_option( 'ec_option_details_second_color' ) != '' ) ? get_option( 'ec_option_details_second_color' ) : '#666666',
						'label' => esc_attr__( 'Background Color (hover)', 'wp-easycart' ),
					),
					'image' => array(
						'label' => esc_attr__( 'Background Image (hover)', 'wp-easycart' ),
					),
				),
				'selector' => '{{WRAPPER}} .wpeasycart-html-swatch-ele:hover',
			)
		);

		$this->add_responsive_control(
			'ec_adtw_swatch_border_color_hover',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Border Color (hover)', 'wp-easycart' ),
				'default' => '',
				'selectors' => array(
					'{{WRAPPER}} .wpeasycart-html-swatch-ele:hover' => 'border-color: {{VALUE}} !important',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'swatch_style_selected_tab',
			array(
				'label' => esc_attr__( 'Selected', 'wp-easycart' ),
			)
		);
		
		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'ec_adtw_swatch_background_selected',
				'types' => array( 'classic', 'gradient' ),
				'fields_options' => array(
					'background' => array(
						'default' => 'classic',
						'label' => esc_attr__( 'Background Type (selected)', 'wp-easycart' ),
					),
					'color' => array(
						'default' => ( get_option( 'ec_option_details_second_color' ) != '' ) ? get_option( 'ec_option_details_second_color' ) : '#666666',
						'label' => esc_attr__( 'Background Color (selected)', 'wp-easycart' ),
					),
					'image' => array(
						'label' => esc_attr__( 'Background Image (selected)', 'wp-easycart' ),
					),
				),
				'selector' => '{{WRAPPER}} .wpeasycart-html-swatch-ele.ec_selected',
			)
		);

		$this->add_responsive_control(
			'ec_adtw_swatch_border_color_selected',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Border Color (selected)', 'wp-easycart' ),
				'default' => '',
				'selectors' => array(
					'{{WRAPPER}} .wpeasycart-html-swatch-ele.ec_selected' => 'border-color: {{VALUE}} !important',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'ec_adtw_swatch_border',
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
						'label' => esc_attr__( 'Border Width', 'wp-easycart' ),
					),
					'color' => array(
						'label' => esc_attr__( 'Border Color', 'wp-easycart' ),
					),
				),
				'selector' => '{{WRAPPER}} .wpeasycart-html-swatch-ele',
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_swatch_border_radius',
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
					'{{WRAPPER}} .wpeasycart-html-swatch-ele' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_swatch_padding',
			array(
				'label' => esc_attr__( 'Label Padding', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 5,
					'right' => 10,
					'bottom' => 5,
					'left' => 10,
					'unit' => 'px',
					'isLinked' => false,
				),
				'selectors' => array(
					'{{WRAPPER}} .wpeasycart-html-swatch-ele' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_swatch_margin',
			array(
				'label' => esc_attr__( 'Label Margin', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 0,
					'right' => 2,
					'bottom' => 0,
					'left' => 0,
					'unit' => 'px',
					'isLinked' => false,
				),
				'selectors' => array(
					'{{WRAPPER}} .wpeasycart-html-swatch-ele' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'ec_adtw_style_section_combo',
			array(
				'label' => esc_attr__( 'Select Box', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'enable_v2' => array( 'yes' ),
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_combo_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Text Color', 'wp-easycart' ),
				'default' => ( get_option( 'ec_option_details_main_color' ) != '' ) ? get_option( 'ec_option_details_main_color' ) : '#333333',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_combo, {{WRAPPER}} .ec_details_option_data > select' => 'color: {{VALUE}} !important',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'ec_adtw_combo_font',
				'label' => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_details_combo, {{WRAPPER}} .ec_details_option_data > select',
				'fields_options' => array(
					'typography' => array(
						'default' => Global_Typography::TYPOGRAPHY_PRIMARY
					),
					'font_size' => array(
						'default' => array(
							'size' => 16,
							'unit' => 'px',
						),
					),
					'font_weight' => array(
						'default' => '500',
					),
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_combo_align',
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
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_combo, {{WRAPPER}} .ec_details_option_data > select' => 'text-align: {{VALUE}};',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'ec_adtw_combo_background',
				'types' => array( 'classic', 'gradient' ),
				'fields_options' => array(
					'background' => array(
						'label' => esc_attr__( 'Background Type', 'wp-easycart' ),
					),
					'color' => array(
						'label' => esc_attr__( 'Background Color', 'wp-easycart' ),
					),
					'image' => array(
						'label' => esc_attr__( 'Background Image', 'wp-easycart' ),
					),
				),
				'selector' => '{{WRAPPER}} .ec_details_combo, {{WRAPPER}} .ec_details_option_data > select',
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'ec_adtw_combo_border',
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
						'label' => esc_attr__( 'Border Width', 'wp-easycart' ),
					),
					'color' => array(
						'label' => esc_attr__( 'Border Color', 'wp-easycart' ),
						'default' => '#cccccc',
					),
				),
				'selector' => '{{WRAPPER}} .ec_details_combo, {{WRAPPER}} .ec_details_option_data > select',
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_combo_border_radius',
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
					'{{WRAPPER}} .ec_details_combo, {{WRAPPER}} .ec_details_option_data > select' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_combo_padding',
			array(
				'label' => esc_attr__( 'Padding', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 5,
					'right' => 10,
					'bottom' => 5,
					'left' => 10,
					'unit' => 'px',
					'isLinked' => false,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_combo, {{WRAPPER}} .ec_details_option_data > select' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_combo_margin',
			array(
				'label' => esc_attr__( 'Margin', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 0,
					'right' => 2,
					'bottom' => 0,
					'left' => 0,
					'unit' => 'px',
					'isLinked' => false,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_combo, {{WRAPPER}} .ec_details_option_data > select' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'ec_adtw_style_section_text_input',
			array(
				'label' => esc_attr__( 'Text Input', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'enable_v2' => array( 'yes' ),
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_text_input_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Text Color', 'wp-easycart' ),
				'default' => ( get_option( 'ec_option_details_main_color' ) != '' ) ? get_option( 'ec_option_details_main_color' ) : '#333333',
				'selectors' => array(
					'{{WRAPPER}} .ec_option_type_text input' => 'color: {{VALUE}} !important',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'ec_adtw_text_input_font',
				'label' => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_option_type_text input',
				'fields_options' => array(
					'typography' => array(
						'default' => Global_Typography::TYPOGRAPHY_PRIMARY
					),
					'font_size' => array(
						'default' => array(
							'size' => 16,
							'unit' => 'px',
						),
					),
					'font_weight' => array(
						'default' => '500',
					),
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_text_input_align',
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
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_option_type_text input' => 'text-align: {{VALUE}};',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'ec_adtw_text_input_background',
				'types' => array( 'classic', 'gradient' ),
				'fields_options' => array(
					'background' => array(
						'label' => esc_attr__( 'Background Type', 'wp-easycart' ),
					),
					'color' => array(
						'label' => esc_attr__( 'Background Color', 'wp-easycart' ),
					),
					'image' => array(
						'label' => esc_attr__( 'Background Image', 'wp-easycart' ),
					),
				),
				'selector' => '{{WRAPPER}} .ec_option_type_text input',
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'ec_adtw_text_input_border',
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
						'label' => esc_attr__( 'Border Width', 'wp-easycart' ),
					),
					'color' => array(
						'label' => esc_attr__( 'Border Color', 'wp-easycart' ),
						'default' => '#cccccc',
					),
				),
				'selector' => '{{WRAPPER}} .ec_option_type_text input',
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_text_input_border_radius',
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
					'{{WRAPPER}} .ec_option_type_text input' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_text_input_padding',
			array(
				'label' => esc_attr__( 'Padding', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 5,
					'right' => 10,
					'bottom' => 5,
					'left' => 10,
					'unit' => 'px',
					'isLinked' => false,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_option_type_text input' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_text_input_margin',
			array(
				'label' => esc_attr__( 'Margin', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 0,
					'right' => 2,
					'bottom' => 0,
					'left' => 0,
					'unit' => 'px',
					'isLinked' => false,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_option_type_text input' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'ec_adtw_style_section_textarea_input',
			array(
				'label' => esc_attr__( 'Textarea Input', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'enable_v2' => array( 'yes' ),
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_textarea_input_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Text Color', 'wp-easycart' ),
				'default' => ( get_option( 'ec_option_details_main_color' ) != '' ) ? get_option( 'ec_option_details_main_color' ) : '#333333',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_option_data > textarea' => 'color: {{VALUE}} !important',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'ec_adtw_textarea_input_font',
				'label' => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_details_option_data > textarea',
				'fields_options' => array(
					'typography' => array(
						'default' => Global_Typography::TYPOGRAPHY_PRIMARY
					),
					'font_size' => array(
						'default' => array(
							'size' => 16,
							'unit' => 'px',
						),
					),
					'font_weight' => array(
						'default' => '500',
					),
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_textarea_input_align',
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
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_option_data > textarea' => 'text-align: {{VALUE}};',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'ec_adtw_textarea_input_background',
				'types' => array( 'classic', 'gradient' ),
				'fields_options' => array(
					'background' => array(
						'label' => esc_attr__( 'Background Type', 'wp-easycart' ),
					),
					'color' => array(
						'label' => esc_attr__( 'Background Color', 'wp-easycart' ),
					),
					'image' => array(
						'label' => esc_attr__( 'Background Image', 'wp-easycart' ),
					),
				),
				'selector' => '{{WRAPPER}} .ec_details_option_data > textarea',
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'ec_adtw_textarea_input_border',
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
						'label' => esc_attr__( 'Border Width', 'wp-easycart' ),
					),
					'color' => array(
						'label' => esc_attr__( 'Border Color', 'wp-easycart' ),
						'default' => '#cccccc',
					),
				),
				'selector' => '{{WRAPPER}} .ec_details_option_data > textarea',
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_textarea_input_border_radius',
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
					'{{WRAPPER}} .ec_details_option_data > textarea' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_textarea_input_padding',
			array(
				'label' => esc_attr__( 'Padding', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 5,
					'right' => 10,
					'bottom' => 5,
					'left' => 10,
					'unit' => 'px',
					'isLinked' => false,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_option_data > textarea' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_textarea_input_margin',
			array(
				'label' => esc_attr__( 'Margin', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 0,
					'right' => 2,
					'bottom' => 0,
					'left' => 0,
					'unit' => 'px',
					'isLinked' => false,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_option_data > textarea' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'ec_adtw_style_section_number_input',
			array(
				'label' => esc_attr__( 'Number Input', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'enable_v2' => array( 'yes' ),
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_number_input_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Text Color', 'wp-easycart' ),
				'default' => ( get_option( 'ec_option_details_main_color' ) != '' ) ? get_option( 'ec_option_details_main_color' ) : '#333333',
				'selectors' => array(
					'{{WRAPPER}} .ec_option_type_number input' => 'color: {{VALUE}} !important',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'ec_adtw_number_input_font',
				'label' => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_option_type_number input',
				'fields_options' => array(
					'typography' => array(
						'default' => Global_Typography::TYPOGRAPHY_PRIMARY
					),
					'font_size' => array(
						'default' => array(
							'size' => 16,
							'unit' => 'px',
						),
					),
					'font_weight' => array(
						'default' => '500',
					),
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_number_input_align',
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
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_option_type_number input' => 'text-align: {{VALUE}};',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'ec_adtw_number_input_background',
				'types' => array( 'classic', 'gradient' ),
				'fields_options' => array(
					'background' => array(
						'label' => esc_attr__( 'Background Type', 'wp-easycart' ),
					),
					'color' => array(
						'label' => esc_attr__( 'Background Color', 'wp-easycart' ),
					),
					'image' => array(
						'label' => esc_attr__( 'Background Image', 'wp-easycart' ),
					),
				),
				'selector' => '{{WRAPPER}} .ec_option_type_number input',
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'ec_adtw_number_input_border',
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
						'label' => esc_attr__( 'Border Width', 'wp-easycart' ),
					),
					'color' => array(
						'label' => esc_attr__( 'Border Color', 'wp-easycart' ),
						'default' => '#cccccc',
					),
				),
				'selector' => '{{WRAPPER}} .ec_option_type_number input',
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_number_input_border_radius',
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
					'{{WRAPPER}} .ec_option_type_number input' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_number_input_padding',
			array(
				'label' => esc_attr__( 'Padding', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 5,
					'right' => 10,
					'bottom' => 5,
					'left' => 10,
					'unit' => 'px',
					'isLinked' => false,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_option_type_number input' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_number_input_margin',
			array(
				'label' => esc_attr__( 'Margin', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 0,
					'right' => 2,
					'bottom' => 0,
					'left' => 0,
					'unit' => 'px',
					'isLinked' => false,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_option_type_number input' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'ec_adtw_style_section_file_input_container',
			array(
				'label' => esc_attr__( 'File Upload Container', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'enable_v2' => array( 'yes' ),
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_file_input_container_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Text Color', 'wp-easycart' ),
				'default' => ( get_option( 'ec_option_details_main_color' ) != '' ) ? get_option( 'ec_option_details_main_color' ) : '#333333',
				'selectors' => array(
					'{{WRAPPER}} .ec_option_type_file input' => 'color: {{VALUE}} !important',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'ec_adtw_file_input_container_font',
				'label' => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_option_type_file input',
				'fields_options' => array(
					'typography' => array(
						'default' => Global_Typography::TYPOGRAPHY_PRIMARY
					),
					'font_size' => array(
						'default' => array(
							'size' => 16,
							'unit' => 'px',
						),
					),
					'font_weight' => array(
						'default' => '500',
					),
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'ec_adtw_file_input_container_background',
				'types' => array( 'classic', 'gradient' ),
				'fields_options' => array(
					'background' => array(
						'label' => esc_attr__( 'Background Type', 'wp-easycart' ),
					),
					'color' => array(
						'label' => esc_attr__( 'Background Color', 'wp-easycart' ),
					),
					'image' => array(
						'label' => esc_attr__( 'Background Image', 'wp-easycart' ),
					),
				),
				'selector' => '{{WRAPPER}} .ec_option_type_file .ec_details_option_data',
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'ec_adtw_file_input_container_border',
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
						'label' => esc_attr__( 'Border Width', 'wp-easycart' ),
					),
					'color' => array(
						'label' => esc_attr__( 'Border Color', 'wp-easycart' ),
						'default' => '#cccccc',
					),
				),
				'selector' => '{{WRAPPER}} .ec_option_type_file .ec_details_option_data',
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_file_input_border_container_radius',
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
					'{{WRAPPER}} .ec_option_type_file .ec_details_option_data' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_file_input_container_padding',
			array(
				'label' => esc_attr__( 'Padding', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 5,
					'right' => 10,
					'bottom' => 5,
					'left' => 10,
					'unit' => 'px',
					'isLinked' => false,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_option_type_file .ec_details_option_data' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_file_input_container_margin',
			array(
				'label' => esc_attr__( 'Margin', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 0,
					'right' => 2,
					'bottom' => 0,
					'left' => 0,
					'unit' => 'px',
					'isLinked' => false,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_option_type_file .ec_details_option_data' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'ec_adtw_style_section_file_input',
			array(
				'label' => esc_attr__( 'File Upload Button', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'enable_v2' => array( 'yes' ),
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'ec_adtw_file_input_font',
				'label' => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_option_type_file input::file-selector-button',
				'fields_options' => array(
					'typography' => array(
						'default' => Global_Typography::TYPOGRAPHY_PRIMARY
					),
					'font_size' => array(
						'default' => array(
							'size' => 16,
							'unit' => 'px',
						),
					),
					'font_weight' => array(
						'default' => '500',
					),
				),
			)
		);
		
		$this->start_controls_tabs(
			'ec_adtw_file_button_style_tabs'
		);

		$this->start_controls_tab(
			'ec_adtw_file_button_style_normal_tab',
			array(
				'label' => esc_attr__( 'Normal', 'wp-easycart' ),
			)
		);

		$this->add_responsive_control(
			'ec_adtw_file_input_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Text Color', 'wp-easycart' ),
				'default' => ( get_option( 'ec_option_details_main_color' ) != '' ) ? get_option( 'ec_option_details_main_color' ) : '#333333',
				'selectors' => array(
					'{{WRAPPER}} .ec_option_type_file input::file-selector-button' => 'color: {{VALUE}} !important',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'ec_adtw_file_input_background',
				'types' => array( 'classic', 'gradient' ),
				'fields_options' => array(
					'background' => array(
						'label' => esc_attr__( 'Background Type', 'wp-easycart' ),
					),
					'color' => array(
						'label' => esc_attr__( 'Background Color', 'wp-easycart' ),
					),
					'image' => array(
						'label' => esc_attr__( 'Background Image', 'wp-easycart' ),
					),
				),
				'selector' => '{{WRAPPER}} .ec_option_type_file input::file-selector-button',
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'ec_adtw_file_button_style_hover_tab',
			array(
				'label' => esc_attr__( 'Hover', 'wp-easycart' ),
			)
		);

		$this->add_responsive_control(
			'ec_adtw_file_input_color_hover',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Text Color (hover)', 'wp-easycart' ),
				'default' => '#FFFFFF',
				'selectors' => array(
					'{{WRAPPER}} .ec_option_type_file input:hover::file-selector-button' => 'color: {{VALUE}} !important',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'ec_adtw_file_input_background_hover',
				'types' => array( 'classic', 'gradient' ),
				'fields_options' => array(
					'background' => array(
						'label' => esc_attr__( 'Background Type (hover)', 'wp-easycart' ),
					),
					'color' => array(
						'label' => esc_attr__( 'Background Color (hover)', 'wp-easycart' ),
					),
					'image' => array(
						'label' => esc_attr__( 'Background Image (hover)', 'wp-easycart' ),
					),
				),
				'selector' => '{{WRAPPER}} .ec_option_type_file input:hover::file-selector-button',
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'ec_adtw_file_input_border',
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
						'label' => esc_attr__( 'Border Width', 'wp-easycart' ),
					),
					'color' => array(
						'label' => esc_attr__( 'Border Color', 'wp-easycart' ),
						'default' => '#cccccc',
					),
				),
				'selector' => '{{WRAPPER}} .ec_option_type_file input::file-selector-button',
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_file_input_border_radius',
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
					'{{WRAPPER}} .ec_option_type_file input::file-selector-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_file_input_padding',
			array(
				'label' => esc_attr__( 'Padding', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 5,
					'right' => 10,
					'bottom' => 5,
					'left' => 10,
					'unit' => 'px',
					'isLinked' => false,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_option_type_file input::file-selector-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_file_input_margin',
			array(
				'label' => esc_attr__( 'Margin', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 0,
					'right' => 2,
					'bottom' => 0,
					'left' => 0,
					'unit' => 'px',
					'isLinked' => false,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_option_type_file input::file-selector-button' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'ec_adtw_style_section_radio_label_input',
			array(
				'label' => esc_attr__( 'Radio Label / Container', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'enable_v2' => array( 'yes' ),
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_radio_label_input_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Text Color', 'wp-easycart' ),
				'default' => ( get_option( 'ec_option_details_main_color' ) != '' ) ? get_option( 'ec_option_details_main_color' ) : '#333333',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_radio_row_ele' => 'color: {{VALUE}} !important',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'ec_adtw_radio_label_input_font',
				'label' => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_details_radio_row_ele',
				'fields_options' => array(
					'typography' => array(
						'default' => Global_Typography::TYPOGRAPHY_PRIMARY
					),
					'font_size' => array(
						'default' => array(
							'size' => 16,
							'unit' => 'px',
						),
					),
					'font_weight' => array(
						'default' => 'bold',
					),
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_radio_label_input_align',
			array(
				'label' => esc_attr__( 'Alignment', 'wp-easycart' ),
				'type' => Controls_Manager::CHOOSE,
				'default' => 'left',
				'options' => array(
					'flex-start' => array(
						'title' => esc_attr__( 'Left', 'wp-easycart' ),
						'icon' => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_attr__( 'Center', 'wp-easycart' ),
						'icon' => 'eicon-text-align-center',
					),
					'flex-end'  => array(
						'title' => esc_attr__( 'Right', 'wp-easycart' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_radio_row_ele' => 'justify-content: {{VALUE}};',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'ec_adtw_radio_label_input_background',
				'types' => array( 'classic', 'gradient' ),
				'fields_options' => array(
					'background' => array(
						'label' => esc_attr__( 'Background Type', 'wp-easycart' ),
					),
					'color' => array(
						'label' => esc_attr__( 'Background Color', 'wp-easycart' ),
					),
					'image' => array(
						'label' => esc_attr__( 'Background Image', 'wp-easycart' ),
					),
				),
				'selector' => '{{WRAPPER}} .ec_details_radio_row_ele',
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'ec_adtw_radio_label_input_border',
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
						'label' => esc_attr__( 'Border Width', 'wp-easycart' ),
					),
					'color' => array(
						'label' => esc_attr__( 'Border Color', 'wp-easycart' ),
					),
				),
				'selector' => '{{WRAPPER}} .ec_details_radio_row_ele',
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_radio_label_input_border_radius',
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
					'{{WRAPPER}} .ec_details_radio_row_ele' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_radio_label_input_padding',
			array(
				'label' => esc_attr__( 'Padding', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 5,
					'right' => 10,
					'bottom' => 5,
					'left' => 10,
					'unit' => 'px',
					'isLinked' => false,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_radio_row_ele' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_radio_label_input_margin',
			array(
				'label' => esc_attr__( 'Margin', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 0,
					'right' => 2,
					'bottom' => 0,
					'left' => 0,
					'unit' => 'px',
					'isLinked' => false,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_radio_row_ele' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'ec_adtw_style_section_radio_input',
			array(
				'label' => esc_attr__( 'Radio Input', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'enable_v2' => array( 'yes' ),
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'ec_adtw_radio_input_background',
				'types' => array( 'classic', 'gradient' ),
				'fields_options' => array(
					'background' => array(
						'default' => 'classic',
						'label' => esc_attr__( 'Background Type', 'wp-easycart' ),
					),
					'color' => array(
						'default' => '#fff',
						'label' => esc_attr__( 'Background Color', 'wp-easycart' ),
					),
					'image' => array(
						'label' => esc_attr__( 'Background Image', 'wp-easycart' ),
					),
				),
				'selector' => '{{WRAPPER}} .ec_details_radio_row_ele input',
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_radio_input_selected_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Selected Color', 'wp-easycart' ),
				'default' => '#333333',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_radio_row_ele input.ec_details_radio_ele::before' => 'color: {{VALUE}}',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_radio_input_focus_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Focus Color', 'wp-easycart' ),
				'default' => '#ffffff',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_radio_row_ele input.ec_details_radio_ele:focus' => 'outline: 0.1em solid {{VALUE}}',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'ec_adtw_radio_input_border',
				'fields_options' => array(
					'border' => array(
						'default' => 'solid',
					),
					'width' => array(
						'default' => array(
							'top' => 2,
							'right' => 2,
							'bottom' => 2,
							'left' => 2,
							'unit' => 'px',
							'isLinked' => true,
						),
						'label' => esc_attr__( 'Border Width', 'wp-easycart' ),
					),
					'color' => array(
						'default' => '#333333',
						'label' => esc_attr__( 'Border Color', 'wp-easycart' ),
					),
				),
				'selector' => '{{WRAPPER}} .ec_details_radio_row_ele input',
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_radio_input_border_radius',
			array(
				'label' => esc_attr__( 'Border Radius', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 50,
					'right' => 50,
					'bottom' => 50,
					'left' => 50,
					'unit' => '%',
					'isLinked' => true,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_radio_row_ele input' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_radio_input_margin',
			array(
				'label' => esc_attr__( 'Margin', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 0,
					'right' => 5,
					'bottom' => 0,
					'left' => 0,
					'unit' => 'px',
					'isLinked' => false,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_radio_row_ele input' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'ec_adtw_style_section_checkbox_label_input',
			array(
				'label' => esc_attr__( 'Checkbox Label / Container', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'enable_v2' => array( 'yes' ),
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_checkbox_label_input_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Text Color', 'wp-easycart' ),
				'default' => ( get_option( 'ec_option_details_main_color' ) != '' ) ? get_option( 'ec_option_details_main_color' ) : '#333333',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_checkbox_row_ele' => 'color: {{VALUE}} !important',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'ec_adtw_checkbox_label_input_font',
				'label' => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_details_checkbox_row_ele',
				'fields_options' => array(
					'typography' => array(
						'default' => Global_Typography::TYPOGRAPHY_PRIMARY
					),
					'font_size' => array(
						'default' => array(
							'size' => 16,
							'unit' => 'px',
						),
					),
					'font_weight' => array(
						'default' => 'bold',
					),
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_checkbox_label_input_align',
			array(
				'label' => esc_attr__( 'Alignment', 'wp-easycart' ),
				'type' => Controls_Manager::CHOOSE,
				'default' => 'left',
				'options' => array(
					'flex-start' => array(
						'title' => esc_attr__( 'Left', 'wp-easycart' ),
						'icon' => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_attr__( 'Center', 'wp-easycart' ),
						'icon' => 'eicon-text-align-center',
					),
					'flex-end'  => array(
						'title' => esc_attr__( 'Right', 'wp-easycart' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_checkbox_row_ele' => 'justify-content: {{VALUE}};',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'ec_adtw_checkbox_label_input_background',
				'types' => array( 'classic', 'gradient' ),
				'fields_options' => array(
					'background' => array(
						'label' => esc_attr__( 'Background Type', 'wp-easycart' ),
					),
					'color' => array(
						'label' => esc_attr__( 'Background Color', 'wp-easycart' ),
					),
					'image' => array(
						'label' => esc_attr__( 'Background Image', 'wp-easycart' ),
					),
				),
				'selector' => '{{WRAPPER}} .ec_details_checkbox_row_ele',
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'ec_adtw_checkbox_label_input_border',
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
						'label' => esc_attr__( 'Border Width', 'wp-easycart' ),
					),
					'color' => array(
						'label' => esc_attr__( 'Border Color', 'wp-easycart' ),
					),
				),
				'selector' => '{{WRAPPER}} .ec_details_checkbox_row_ele',
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_checkbox_label_input_border_radius',
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
					'{{WRAPPER}} .ec_details_checkbox_row_ele' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_checkbox_label_input_padding',
			array(
				'label' => esc_attr__( 'Padding', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 5,
					'right' => 10,
					'bottom' => 5,
					'left' => 10,
					'unit' => 'px',
					'isLinked' => false,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_checkbox_row_ele' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_checkbox_label_input_margin',
			array(
				'label' => esc_attr__( 'Margin', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 0,
					'right' => 2,
					'bottom' => 0,
					'left' => 0,
					'unit' => 'px',
					'isLinked' => false,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_checkbox_row_ele' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'ec_adtw_style_section_checkbox_input',
			array(
				'label' => esc_attr__( 'Checkbox Input', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'enable_v2' => array( 'yes' ),
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'ec_adtw_checkbox_input_background',
				'types' => array( 'classic', 'gradient' ),
				'fields_options' => array(
					'background' => array(
						'default' => 'classic',
						'label' => esc_attr__( 'Background Type', 'wp-easycart' ),
					),
					'color' => array(
						'default' => '#fff',
						'label' => esc_attr__( 'Background Color', 'wp-easycart' ),
					),
					'image' => array(
						'label' => esc_attr__( 'Background Image', 'wp-easycart' ),
					),
				),
				'selector' => '{{WRAPPER}} .ec_details_checkbox_row_ele input',
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_checkbox_input_selected_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Selected Color', 'wp-easycart' ),
				'default' => '#222222',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_checkbox_row_ele input.ec_details_checkbox_ele::before' => 'color: {{VALUE}}',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_checkbox_input_focus_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Focus Color', 'wp-easycart' ),
				'default' => '#222222',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_checkbox_row_ele input.ec_details_checkbox_ele:focus' => 'outline: 0.1em solid {{VALUE}}',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'ec_adtw_checkbox_input_border',
				'fields_options' => array(
					'border' => array(
						'default' => 'solid',
					),
					'width' => array(
						'default' => array(
							'top' => 2,
							'right' => 2,
							'bottom' => 2,
							'left' => 2,
							'unit' => 'px',
							'isLinked' => true,
						),
						'label' => esc_attr__( 'Border Width', 'wp-easycart' ),
					),
					'color' => array(
						'default' => '#333333',
						'label' => esc_attr__( 'Border Color', 'wp-easycart' ),
					),
				),
				'selector' => '{{WRAPPER}} .ec_details_checkbox_row_ele input',
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_checkbox_input_border_radius',
			array(
				'label' => esc_attr__( 'Border Radius', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 0,
					'right' => 0,
					'bottom' => 0,
					'left' => 0,
					'unit' => '%',
					'isLinked' => true,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_checkbox_row_ele input' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_checkbox_input_margin',
			array(
				'label' => esc_attr__( 'Margin', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 0,
					'right' => 5,
					'bottom' => 0,
					'left' => 0,
					'unit' => 'px',
					'isLinked' => false,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_checkbox_row_ele input' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'ec_adtw_style_section_quantity_label_input',
			array(
				'label' => esc_attr__( 'Quantity Grid Label / Container', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'enable_v2' => array( 'yes' ),
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_quantity_label_input_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Text Color', 'wp-easycart' ),
				'default' => ( get_option( 'ec_option_details_main_color' ) != '' ) ? get_option( 'ec_option_details_main_color' ) : '#333333',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_grid_row_ele > span' => 'color: {{VALUE}} !important',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'ec_adtw_quantity_label_input_font',
				'label' => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_details_grid_row_ele > span',
				'fields_options' => array(
					'typography' => array(
						'default' => Global_Typography::TYPOGRAPHY_PRIMARY
					),
					'font_size' => array(
						'default' => array(
							'size' => 16,
							'unit' => 'px',
						),
					),
					'font_weight' => array(
						'default' => 'bold',
					),
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_quantity_label_input_align',
			array(
				'label' => esc_attr__( 'Alignment', 'wp-easycart' ),
				'type' => Controls_Manager::CHOOSE,
				'default' => 'left',
				'options' => array(
					'flex-start' => array(
						'title' => esc_attr__( 'Left', 'wp-easycart' ),
						'icon' => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_attr__( 'Center', 'wp-easycart' ),
						'icon' => 'eicon-text-align-center',
					),
					'flex-end'  => array(
						'title' => esc_attr__( 'Right', 'wp-easycart' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_grid_row_ele' => 'justify-content: {{VALUE}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_quantity_label_input_label_align',
			array(
				'label' => esc_attr__( 'Label Alignment', 'wp-easycart' ),
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
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_grid_row_ele > span' => 'text-align: {{VALUE}};',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'ec_adtw_quantity_label_input_background',
				'types' => array( 'classic', 'gradient' ),
				'fields_options' => array(
					'background' => array(
						'label' => esc_attr__( 'Background Type', 'wp-easycart' ),
					),
					'color' => array(
						'label' => esc_attr__( 'Background Color', 'wp-easycart' ),
					),
					'image' => array(
						'label' => esc_attr__( 'Background Image', 'wp-easycart' ),
					),
				),
				'selector' => '{{WRAPPER}} .ec_details_grid_row_ele',
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'ec_adtw_quantity_label_input_border',
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
						'label' => esc_attr__( 'Border Width', 'wp-easycart' ),
					),
					'color' => array(
						'label' => esc_attr__( 'Border Color', 'wp-easycart' ),
					),
				),
				'selector' => '{{WRAPPER}} .ec_details_grid_row_ele',
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_quantity_label_input_border_radius',
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
					'{{WRAPPER}} .ec_details_grid_row_ele' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_quantity_label_input_padding',
			array(
				'label' => esc_attr__( 'Padding', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 5,
					'right' => 10,
					'bottom' => 5,
					'left' => 10,
					'unit' => 'px',
					'isLinked' => false,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_grid_row_ele' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_quantity_label_input_margin',
			array(
				'label' => esc_attr__( 'Margin', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 0,
					'right' => 2,
					'bottom' => 0,
					'left' => 0,
					'unit' => 'px',
					'isLinked' => false,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_grid_row_ele' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'ec_adtw_style_section_quantity_input',
			array(
				'label' => esc_attr__( 'Quantity Grid Input', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'enable_v2' => array( 'yes' ),
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_quantity_input_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Text Color', 'wp-easycart' ),
				'default' => '#333333',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_grid_row_ele > input' => 'color: {{VALUE}} !important',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'ec_adtw_quantity_input_font',
				'label' => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_details_grid_row_ele > input',
				'fields_options' => array(
					'typography' => array(
						'default' => Global_Typography::TYPOGRAPHY_PRIMARY
					),
					'font_size' => array(
						'default' => array(
							'size' => 16,
							'unit' => 'px',
						),
					),
					'font_weight' => array(
						'default' => '500',
					),
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_quantity_input_align',
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
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_grid_row_ele > input' => 'text-align: {{VALUE}};',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'ec_adtw_quantity_input_background',
				'types' => array( 'classic', 'gradient' ),
				'fields_options' => array(
					'background' => array(
						'default' => 'classic',
						'label' => esc_attr__( 'Background Type', 'wp-easycart' ),
					),
					'color' => array(
						'default' => '#fff',
						'label' => esc_attr__( 'Background Color', 'wp-easycart' ),
					),
					'image' => array(
						'label' => esc_attr__( 'Background Image', 'wp-easycart' ),
					),
				),
				'selector' => '{{WRAPPER}} .ec_details_grid_row_ele input',
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_quantity_input_selected_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Selected Color', 'wp-easycart' ),
				'default' => '#ffffff',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_grid_row_ele input.ec_details_quantity_ele::before' => 'color: {{VALUE}}',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_quantity_input_focus_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Focus Color', 'wp-easycart' ),
				'default' => '#ffffff',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_grid_row_ele input.ec_details_quantity_ele:focus' => 'outline: 0.1em solid {{VALUE}}',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'ec_adtw_quantity_input_border',
				'fields_options' => array(
					'border' => array(
						'default' => 'solid',
					),
					'width' => array(
						'default' => array(
							'top' => 2,
							'right' => 2,
							'bottom' => 2,
							'left' => 2,
							'unit' => 'px',
							'isLinked' => true,
						),
						'label' => esc_attr__( 'Border Width', 'wp-easycart' ),
					),
					'color' => array(
						'default' => '#333333',
						'label' => esc_attr__( 'Border Color', 'wp-easycart' ),
					),
				),
				'selector' => '{{WRAPPER}} .ec_details_grid_row_ele input',
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_quantity_input_border_radius',
			array(
				'label' => esc_attr__( 'Border Radius', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 0,
					'right' => 0,
					'bottom' => 0,
					'left' => 0,
					'unit' => '%',
					'isLinked' => true,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_grid_row_ele input' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_quantity_input_padding',
			array(
				'label' => esc_attr__( 'Padding', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 6,
					'right' => 10,
					'bottom' => 6,
					'left' => 10,
					'unit' => 'px',
					'isLinked' => false,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_grid_row_ele input' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_quantity_input_margin',
			array(
				'label' => esc_attr__( 'Margin', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 0,
					'right' => 5,
					'bottom' => 0,
					'left' => 0,
					'unit' => 'px',
					'isLinked' => false,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_grid_row_ele input' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'ec_adtw_style_section_date_input',
			array(
				'label' => esc_attr__( 'Date Input', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'enable_v2' => array( 'yes' ),
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_date_input_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Text Color', 'wp-easycart' ),
				'default' => ( get_option( 'ec_option_details_main_color' ) != '' ) ? get_option( 'ec_option_details_main_color' ) : '#333333',
				'selectors' => array(
					'{{WRAPPER}} .ec_option_type_date input' => 'color: {{VALUE}} !important',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'ec_adtw_date_input_font',
				'label' => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_option_type_date input',
				'fields_options' => array(
					'typography' => array(
						'default' => Global_Typography::TYPOGRAPHY_PRIMARY
					),
					'font_size' => array(
						'default' => array(
							'size' => 16,
							'unit' => 'px',
						),
					),
					'font_weight' => array(
						'default' => '500',
					),
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_date_input_align',
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
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_option_type_date input' => 'text-align: {{VALUE}};',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'ec_adtw_date_input_background',
				'types' => array( 'classic', 'gradient' ),
				'fields_options' => array(
					'background' => array(
						'label' => esc_attr__( 'Background Type', 'wp-easycart' ),
					),
					'color' => array(
						'label' => esc_attr__( 'Background Color', 'wp-easycart' ),
					),
					'image' => array(
						'label' => esc_attr__( 'Background Image', 'wp-easycart' ),
					),
				),
				'selector' => '{{WRAPPER}} .ec_option_type_date input',
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'ec_adtw_date_input_border',
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
						'label' => esc_attr__( 'Border Width', 'wp-easycart' ),
					),
					'color' => array(
						'label' => esc_attr__( 'Border Color', 'wp-easycart' ),
						'default' => '#cccccc',
					),
				),
				'selector' => '{{WRAPPER}} .ec_option_type_date input',
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_date_input_border_radius',
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
					'{{WRAPPER}} .ec_option_type_date input' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_date_input_padding',
			array(
				'label' => esc_attr__( 'Padding', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 5,
					'right' => 10,
					'bottom' => 5,
					'left' => 10,
					'unit' => 'px',
					'isLinked' => false,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_option_type_date input' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_date_input_margin',
			array(
				'label' => esc_attr__( 'Margin', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 0,
					'right' => 2,
					'bottom' => 0,
					'left' => 0,
					'unit' => 'px',
					'isLinked' => false,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_option_type_date input' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'ec_adtw_style_section_dimension_label_input',
			array(
				'label' => esc_attr__( 'Dimension Label / Container', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'enable_v2' => array( 'yes' ),
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_dimension_label_input_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Text Color', 'wp-easycart' ),
				'default' => ( get_option( 'ec_option_details_main_color' ) != '' ) ? get_option( 'ec_option_details_main_color' ) : '#333333',
				'selectors' => array(
					'{{WRAPPER}} .ec_option_type_dimensions1 span' => 'color: {{VALUE}} !important',
					'{{WRAPPER}} .ec_option_type_dimensions2 span' => 'color: {{VALUE}} !important',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'ec_adtw_dimension_label_input_font',
				'label' => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_option_type_dimensions1 span, {{WRAPPER}} .ec_option_type_dimensions2 span',
				'fields_options' => array(
					'typography' => array(
						'default' => Global_Typography::TYPOGRAPHY_PRIMARY
					),
					'font_size' => array(
						'default' => array(
							'size' => 16,
							'unit' => 'px',
						),
					),
					'font_weight' => array(
						'default' => '500',
					),
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_dimension_label_input_align',
			array(
				'label' => esc_attr__( 'Alignment', 'wp-easycart' ),
				'type' => Controls_Manager::CHOOSE,
				'default' => 'left',
				'options' => array(
					'flex-start' => array(
						'title' => esc_attr__( 'Left', 'wp-easycart' ),
						'icon' => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_attr__( 'Center', 'wp-easycart' ),
						'icon' => 'eicon-text-align-center',
					),
					'flex-end'  => array(
						'title' => esc_attr__( 'Right', 'wp-easycart' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_option_type_dimensions1 .ec_details_option_data' => 'display:flex; align-items:center; justify-content: {{VALUE}};',
					'{{WRAPPER}} .ec_option_type_dimensions2 .ec_details_option_data' => 'display:flex; align-items:center; justify-content: {{VALUE}};',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'ec_adtw_dimension_label_input_background',
				'types' => array( 'classic', 'gradient' ),
				'fields_options' => array(
					'background' => array(
						'label' => esc_attr__( 'Background Type', 'wp-easycart' ),
					),
					'color' => array(
						'label' => esc_attr__( 'Background Color', 'wp-easycart' ),
					),
					'image' => array(
						'label' => esc_attr__( 'Background Image', 'wp-easycart' ),
					),
				),
				'selector' => '{{WRAPPER}} .ec_option_type_dimensions1 .ec_details_option_data, {{WRAPPER}} .ec_option_type_dimensions2 .ec_details_option_data',
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'ec_adtw_dimension_label_input_border',
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
						'label' => esc_attr__( 'Border Width', 'wp-easycart' ),
					),
					'color' => array(
						'label' => esc_attr__( 'Border Color', 'wp-easycart' ),
					),
				),
				'selector' => '{{WRAPPER}} .ec_option_type_dimensions1 .ec_details_option_data, {{WRAPPER}} .ec_option_type_dimensions2 .ec_details_option_data',
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_dimension_label_input_border_radius',
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
					'{{WRAPPER}} .ec_option_type_dimensions1 .ec_details_option_data' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .ec_option_type_dimensions2 .ec_details_option_data' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_dimension_label_input_padding',
			array(
				'label' => esc_attr__( 'Padding', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 5,
					'right' => 10,
					'bottom' => 5,
					'left' => 10,
					'unit' => 'px',
					'isLinked' => false,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_option_type_dimensions1 .ec_details_option_data' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .ec_option_type_dimensions2 .ec_details_option_data' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_dimension_label_input_margin',
			array(
				'label' => esc_attr__( 'Margin', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 0,
					'right' => 2,
					'bottom' => 0,
					'left' => 0,
					'unit' => 'px',
					'isLinked' => false,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_option_type_dimensions1 .ec_details_option_data' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .ec_option_type_dimensions2 .ec_details_option_data' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'ec_adtw_style_section_dimension_input',
			array(
				'label' => esc_attr__( 'Dimension Input', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'enable_v2' => array( 'yes' ),
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_dimension_input_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Text Color', 'wp-easycart' ),
				'default' => '#333333',
				'selectors' => array(
					'{{WRAPPER}} .ec_option_type_dimensions1 input' => 'color: {{VALUE}} !important',
					'{{WRAPPER}} .ec_option_type_dimensions2 input' => 'color: {{VALUE}} !important',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'ec_adtw_dimension_input_font',
				'label' => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_option_type_dimensions1 input, {{WRAPPER}} .ec_option_type_dimensions2 input',
				'fields_options' => array(
					'typography' => array(
						'default' => Global_Typography::TYPOGRAPHY_PRIMARY
					),
					'font_size' => array(
						'default' => array(
							'size' => 16,
							'unit' => 'px',
						),
					),
					'font_weight' => array(
						'default' => '500',
					),
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_dimension_input_align',
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
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_option_type_dimensions1 input' => 'text-align: {{VALUE}} !important',
					'{{WRAPPER}} .ec_option_type_dimensions2 input' => 'text-align: {{VALUE}} !important',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'ec_adtw_dimension_input_background',
				'types' => array( 'classic', 'gradient' ),
				'fields_options' => array(
					'background' => array(
						'default' => 'classic',
						'label' => esc_attr__( 'Background Type', 'wp-easycart' ),
					),
					'color' => array(
						'default' => '#fff',
						'label' => esc_attr__( 'Background Color', 'wp-easycart' ),
					),
					'image' => array(
						'label' => esc_attr__( 'Background Image', 'wp-easycart' ),
					),
				),
				'selector' => '{{WRAPPER}} .ec_option_type_dimensions1 input, {{WRAPPER}} .ec_option_type_dimensions2 input',
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'ec_adtw_dimension_input_border',
				'fields_options' => array(
					'border' => array(
						'default' => 'solid',
					),
					'width' => array(
						'default' => array(
							'top' => 2,
							'right' => 2,
							'bottom' => 2,
							'left' => 2,
							'unit' => 'px',
							'isLinked' => true,
						),
						'label' => esc_attr__( 'Border Width', 'wp-easycart' ),
					),
					'color' => array(
						'default' => '#333333',
						'label' => esc_attr__( 'Border Color', 'wp-easycart' ),
					),
				),
				'selector' => '{{WRAPPER}} .ec_option_type_dimensions1 input, {{WRAPPER}} .ec_option_type_dimensions2 input',
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_dimension_input_border_radius',
			array(
				'label' => esc_attr__( 'Border Radius', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 0,
					'right' => 0,
					'bottom' => 0,
					'left' => 0,
					'unit' => '%',
					'isLinked' => true,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_option_type_dimensions1 input' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .ec_option_type_dimensions2 input' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_dimension_input_padding',
			array(
				'label' => esc_attr__( 'Padding', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 6,
					'right' => 10,
					'bottom' => 6,
					'left' => 10,
					'unit' => 'px',
					'isLinked' => false,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_option_type_dimensions1 input' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .ec_option_type_dimensions2 input' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_dimension_input_margin',
			array(
				'label' => esc_attr__( 'Margin', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 0,
					'right' => 5,
					'bottom' => 0,
					'left' => 0,
					'unit' => 'px',
					'isLinked' => false,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_option_type_dimensions1 input' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
					'{{WRAPPER}} .ec_option_type_dimensions2 input' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'ec_adtw_style_section_variants',
			array(
				'label' => esc_attr__( 'Variant Container', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'enable_v2' => array( 'yes' ),
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'ec_adtw_variants_background',
				'types' => array( 'classic', 'gradient' ),
				'fields_options' => array(
					'background' => array(
						'label' => esc_attr__( 'Background Type', 'wp-easycart' ),
					),
					'color' => array(
						'label' => esc_attr__( 'Background Color', 'wp-easycart' ),
					),
					'image' => array(
						'label' => esc_attr__( 'Background Image', 'wp-easycart' ),
					),
				),
				'selector' => '{{WRAPPER}} .ec_details_options_basic',
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'ec_adtw_variants_border',
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
						'label' => esc_attr__( 'Border Width', 'wp-easycart' ),
					),
					'color' => array(
						'label' => esc_attr__( 'Border Color', 'wp-easycart' ),
					),
				),
				'selector' => '{{WRAPPER}} .ec_details_options_basic',
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_variants_border_radius',
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
					'{{WRAPPER}} .ec_details_options_basic' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_variants_padding',
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
					'{{WRAPPER}} .ec_details_options_basic' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_variants_margin',
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
					'{{WRAPPER}} .ec_details_options_basic' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'ec_adtw_style_section_modifiers',
			array(
				'label' => esc_attr__( 'Modifier Container', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'enable_v2' => array( 'yes' ),
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'ec_adtw_modifiers_background',
				'types' => array( 'classic', 'gradient' ),
				'fields_options' => array(
					'background' => array(
						'label' => esc_attr__( 'Background Type', 'wp-easycart' ),
					),
					'color' => array(
						'label' => esc_attr__( 'Background Color', 'wp-easycart' ),
					),
					'image' => array(
						'label' => esc_attr__( 'Background Image', 'wp-easycart' ),
					),
				),
				'selector' => '{{WRAPPER}} .ec_details_options_advanced',
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'ec_adtw_modifiers_border',
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
						'label' => esc_attr__( 'Border Width', 'wp-easycart' ),
					),
					'color' => array(
						'label' => esc_attr__( 'Border Color', 'wp-easycart' ),
					),
				),
				'selector' => '{{WRAPPER}} .ec_details_options_advanced',
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_modifiers_border_radius',
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
					'{{WRAPPER}} .ec_details_options_advanced' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_modifiers_padding',
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
					'{{WRAPPER}} .ec_details_options_advanced' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_modifiers_margin',
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
					'{{WRAPPER}} .ec_details_options_advanced' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'ec_adtw_style_section_your_price',
			array(
				'label' => esc_attr__( 'Your Price', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'enable_v2' => array( 'yes' ),
					'enable_your_price' => array( 'yes' ),
				),
			)
		);

		$this->add_responsive_control(
			'ec_adtw_your_price_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Text Color', 'wp-easycart' ),
				'default' => ( get_option( 'ec_option_details_main_color' ) != '' ) ? get_option( 'ec_option_details_main_color' ) : '#333333',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_final_price_ele' => 'color: {{VALUE}} !important',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'ec_adtw_your_price_font',
				'label' => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_details_final_price_ele',
				'fields_options' => array(
					'typography' => array(
						'default' => Global_Typography::TYPOGRAPHY_PRIMARY
					),
					'font_size' => array(
						'default' => array(
							'size' => 16,
							'unit' => 'px',
						),
					),
					'font_weight' => array(
						'default' => 'bold',
					),
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_your_price_align',
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
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_final_price_ele' => 'text-align: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'ec_adtw_your_price_background',
				'types' => array( 'classic', 'gradient' ),
				'fields_options' => array(
					'background' => array(
						'default' => 'none',
						'label' => esc_attr__( 'Background Type', 'wp-easycart' ),
					),
					'color' => array(
						'default' => '',
						'label' => esc_attr__( 'Background Color', 'wp-easycart' ),
					),
					'image' => array(
						'label' => esc_attr__( 'Background Image', 'wp-easycart' ),
					),
				),
				'selector' => '{{WRAPPER}} .ec_details_final_price_ele',
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'ec_adtw_your_price_border',
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
						'label' => esc_attr__( 'Border Width', 'wp-easycart' ),
					),
					'color' => array(
						'default' => '#AE0000',
						'label' => esc_attr__( 'Border Color', 'wp-easycart' ),
					),
				),
				'selector' => '{{WRAPPER}} .ec_details_final_price_ele',
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_your_price_padding',
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
					'{{WRAPPER}} .ec_details_final_price_ele' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_your_price_margin',
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
					'isLinked' => false,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_final_price_ele' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'ec_adtw_style_section_error_message',
			array(
				'label' => esc_attr__( 'Error Message', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'enable_v2' => array( 'yes' ),
				),
			)
		);

		$this->add_responsive_control(
			'ec_adtw_error_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Text Color', 'wp-easycart' ),
				'default' => '#AE0000',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_option_row_error' => 'color: {{VALUE}} !important',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'ec_adtw_error_font',
				'label' => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_details_option_row_error',
				'fields_options' => array(
					'typography' => array(
						'default' => Global_Typography::TYPOGRAPHY_PRIMARY
					),
					'font_size' => array(
						'default' => array(
							'size' => 16,
							'unit' => 'px',
						),
					),
					'font_weight' => array(
						'default' => '500',
					),
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_error_align',
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
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_option_row_error' => 'text-align: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'ec_adtw_error_background',
				'types' => array( 'classic', 'gradient' ),
				'fields_options' => array(
					'background' => array(
						'default' => 'classic',
						'label' => esc_attr__( 'Background Type', 'wp-easycart' ),
					),
					'color' => array(
						'default' => '#FFE7E7',
						'label' => esc_attr__( 'Background Color', 'wp-easycart' ),
					),
					'image' => array(
						'label' => esc_attr__( 'Background Image', 'wp-easycart' ),
					),
				),
				'selector' => '{{WRAPPER}} .ec_details_option_row_error',
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'ec_adtw_error_border',
				'fields_options' => array(
					'border' => array(
						'default' => 'dashed',
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
						'label' => esc_attr__( 'Border Width', 'wp-easycart' ),
					),
					'color' => array(
						'default' => '#AE0000',
						'label' => esc_attr__( 'Border Color', 'wp-easycart' ),
					),
				),
				'selector' => '{{WRAPPER}} .ec_details_option_row_error',
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_error_padding',
			array(
				'label' => esc_attr__( 'Button Padding', 'wp-easycart' ),
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
					'{{WRAPPER}} .ec_details_option_row_error' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_adtw_error_margin',
			array(
				'label' => esc_attr__( 'Button Margin', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 5,
					'right' => 0,
					'bottom' => 5,
					'left' => 0,
					'unit' => 'px',
					'isLinked' => false,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_option_row_error' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->end_controls_section();
	}

	/**
	 * Render product add to cart widget control output in the editor.
	 */
	protected function render() {
		$atts = $this->get_settings_for_display();
		include( EC_PLUGIN_DIRECTORY . '/admin/elementor/wp-easycart-elementor-product-addtocart-widget.php' );
	}
}
