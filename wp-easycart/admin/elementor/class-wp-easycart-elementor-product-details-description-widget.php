<?php
/**
 * WP EasyCart Product Details Description Widget for Elementor
 *
 * @category Class
 * @package  Wp_Easycart_Elementor_Product_Details_Description_Widget
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
 * WP EasyCart Product Details Description Widget for Elementor
 *
 * @category Class
 * @package  Wp_Easycart_Elementor_Product_Details_Description_Widget
 * @author   WP EasyCart
 */
class Wp_Easycart_Elementor_Product_Details_Description_Widget extends \Elementor\Widget_Base {

	/**
	 * Get product details description widget name.
	 */
	public function get_name() {
		return 'wp_easycart_product_details_description';
	}

	/**
	 * Get product details description widget title.
	 */
	public function get_title() {
		return esc_attr__( 'WP EasyCart Product Description', 'wp-easycart' );
	}

	/**
	 * Get product details description widget icon.
	 */
	public function get_icon() {
		return 'eicon-product-description';
	}

	/**
	 * Get product details description widget categories.
	 */
	public function get_categories() {
		return array( 'wp-easycart-elements' );
	}

	/**
	 * Get product details description widget keywords.
	 */
	public function get_keywords() {
		return array( 'description', 'wp-easycart' );
	}

	/**
	 * Enqueue product details description widget scripts and styles.
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
			'content_section',
			array(
				'label' => esc_attr__( 'Product Description', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_CONTENT,
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
		
		$this->end_controls_section();
		
		$this->start_controls_section(
			'style_section_list_container',
			array(
				'label' => esc_attr__( 'Product Description', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
			)
		);
		
		$this->add_responsive_control(
			'description_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Color', 'wp-easycart' ),
				'default' => ( get_option( 'ec_option_details_main_color' ) != '' ) ? get_option( 'ec_option_details_main_color' ) : '#333333',
				'selectors' => array(
					'{{WRAPPER}} .ec_product_details_description' => 'color: {{VALUE}}',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'description_font',
				'label' => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_product_details_description',
			)
		);
		
		$this->add_responsive_control(
			'description_align',
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
				'selectors' => array(
					'{{WRAPPER}} .ec_product_details_description' => 'text-align: {{VALUE}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'description_padding',
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
					'{{WRAPPER}} .ec_product_details_description' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'description_margin',
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
					'{{WRAPPER}} .ec_product_details_description' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);

		$this->add_responsive_control(
			'wpec_dw_description_border_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Border Color', 'wp-easycart' ),
				'default' => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_product_details_description' => 'border-color: {{VALUE}}',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'description_border',
				'selector' => '{{WRAPPER}} .ec_product_details_description',
			)
		);
		
		$this->add_responsive_control(
			'description_border_radius',
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
					'{{WRAPPER}} .ec_product_details_description' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
		include( EC_PLUGIN_DIRECTORY . '/admin/elementor/wp-easycart-elementor-product-details-description-widget.php' );
	}
}
