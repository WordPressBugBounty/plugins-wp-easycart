<?php
/**
 * WP EasyCart Product Details Manufacturer Widget for Elementor
 *
 * @category Class
 * @package  Wp_Easycart_Elementor_Product_Details_Manufacturer_Widget
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
 * WP EasyCart Product Details Manufacturer Widget for Elementor
 *
 * @category Class
 * @package  Wp_Easycart_Elementor_Product_Details_Manufacturer_Widget
 * @author   WP EasyCart
 */
class Wp_Easycart_Elementor_Product_Details_Manufacturer_Widget extends \Elementor\Widget_Base {

	/**
	 * Get product details manufacturer widget name.
	 */
	public function get_name() {
		return 'wp_easycart_product_details_manufacturer';
	}

	/**
	 * Get product details manufacturer widget title.
	 */
	public function get_title() {
		return esc_attr__( 'WP EasyCart Product Manufacturer', 'wp-easycart' );
	}

	/**
	 * Get product details manufacturer widget icon.
	 */
	public function get_icon() {
		return 'eicon-wrench';
	}

	/**
	 * Get product details manufacturer widget categories.
	 */
	public function get_categories() {
		return array( 'wp-easycart-elements' );
	}

	/**
	 * Get product details manufacturer widget keywords.
	 */
	public function get_keywords() {
		return array( 'manufacturer', 'wp-easycart' );
	}

	/**
	 * Enqueue product details manufacturer widget scripts and styles.
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
				'label' => esc_attr__( 'Manufacturer', 'wp-easycart' ),
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
			'label_text',
			array(
				'type'  => Controls_Manager::TEXT,
				'label' => esc_attr__( 'Text', 'wp-easycart' ),
				'default' => wp_easycart_language( )->get_text( 'product_details', 'product_details_manufacturer' ),
				'placeholder' => esc_attr__( 'Leave empty for no label', 'wp-easycart' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'ec_mw_style_section_container',
			array(
				'label' => esc_attr__( 'Manufacturer', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
			)
		);
		
		$this->add_responsive_control(
			'ec_mw_align',
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
					'{{WRAPPER}} .ec_details_manufacturer' => 'text-align: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'ec_mw_background_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Background Color', 'wp-easycart' ),
				'default' => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_manufacturer' => 'background-color: {{VALUE}}',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_mw_padding',
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
					'{{WRAPPER}} .ec_details_manufacturer' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_mw_margin',
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
					'{{WRAPPER}} .ec_details_manufacturer' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'ec_mw_border',
				'default' => array(
					'type' => 'none',
					'top' => 0,
					'right' => 0,
					'bottom' => 0,
					'left' => 0,
					'unit' => 'px',
					'isLinked' => true,
				),
				'selector' => '{{WRAPPER}} .ec_details_manufacturer',
			)
		);
		
		$this->add_responsive_control(
			'ec_mw_border_radius',
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
					'{{WRAPPER}} .ec_details_manufacturer' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'style_section_label',
			array(
				'label' => esc_attr__( 'Label', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
			)
		);
		
		$this->add_responsive_control(
			'label_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Color', 'wp-easycart' ),
				'default' => ( get_option( 'ec_option_details_main_color' ) != '' ) ? get_option( 'ec_option_details_main_color' ) : '#333333',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_manufacturer_label' => 'color: {{VALUE}}',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'label_font',
				'label' => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_details_manufacturer_label',
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'style_section_manufacturer',
			array(
				'label' => esc_attr__( 'Manufacturer', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
			)
		);
		
		$this->add_responsive_control(
			'manufacturer_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Color', 'wp-easycart' ),
				'default' => ( get_option( 'ec_option_details_main_color' ) != '' ) ? get_option( 'ec_option_details_main_color' ) : '#333333',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_manufacturer_item' => 'color: {{VALUE}}',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'manufacturer_font',
				'label' => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_details_manufacturer_item',
			)
		);
		
		$this->end_controls_section();
	}

	/**
	 * Render search widget control output in the editor.
	 */
	protected function render() {
		$atts = $this->get_settings_for_display();
		include( EC_PLUGIN_DIRECTORY . '/admin/elementor/wp-easycart-elementor-product-details-manufacturer-widget.php' );
	}
}
