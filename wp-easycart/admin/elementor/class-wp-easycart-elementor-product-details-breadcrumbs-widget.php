<?php
/**
 * WP EasyCart Product Details Breadcrumbs Widget for Elementor
 *
 * @category Class
 * @package  Wp_Easycart_Elementor_Product_Details_Breadcrumbs_Widget
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
 * WP EasyCart Product Details Breadcrumbs Widget for Elementor
 *
 * @category Class
 * @package  Wp_Easycart_Elementor_Product_Details_Breadcrumbs_Widget
 * @author   WP EasyCart
 */
class Wp_Easycart_Elementor_Product_Details_Breadcrumbs_Widget extends \Elementor\Widget_Base {

	/**
	 * Get product details breadcrumbs widget name.
	 */
	public function get_name() {
		return 'wp_easycart_product_details_breadcrumbs';
	}

	/**
	 * Get product details breadcrumbs widget breadcrumbs.
	 */
	public function get_title() {
		return esc_attr__( 'WP EasyCart Product Breadcrumbs', 'wp-easycart' );
	}

	/**
	 * Get product details breadcrumbs widget icon.
	 */
	public function get_icon() {
		return 'eicon-product-breadcrumbs';
	}

	/**
	 * Get product details breadcrumbs widget categories.
	 */
	public function get_categories() {
		return array( 'wp-easycart-elements' );
	}

	/**
	 * Get product details breadcrumbs widget keywords.
	 */
	public function get_keywords() {
		return array( 'breadcrumbs', 'wp-easycart' );
	}

	/**
	 * Enqueue product details breadcrumbs widget scripts and styles.
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
			$pages_select[ $page->ID ] = $page->post_breadcrumbs;
		}
		$this->start_controls_section(
			'content_section',
			array(
				'label' => esc_attr__( 'Breadcrumbs', 'wp-easycart' ),
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

		$this->add_control(
			'divider_character',
			array(
				'type'  => Controls_Manager::TEXT,
				'label' => esc_attr__( 'Divider Character', 'wp-easycart' ),
				'default' => '/',
				'placeholder' => '/',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'style_section_container',
			array(
				'label' => esc_attr__( 'Breadcrumbs', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'breadcrumb_element',
			array(
				'label' => esc_attr__( 'Element Container', 'wp-easycart' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'div',
				'options' => array(
					'div' => 'div',
					'p' => 'p',
					'h1' => 'h1',
					'h2' => 'h2',
					'h3' => 'h3',
					'h4' => 'h4',
					'h5' => 'h5',
					'h6' => 'h6',
				),
			)
		);

		$this->add_responsive_control(
			'breadcrumb_align',
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
					'justify' => array(
						'title' => esc_attr__( 'Justify', 'wp-easycart' ),
						'icon' => 'eicon-text-align-justify',
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_breadcrumbs_ele' => 'text-align: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'style_section_links',
			array(
				'label' => esc_attr__( 'Links', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
			)
		);

		$this->start_controls_tabs(
			'link_style_tabs'
		);

		$this->start_controls_tab(
			'style_normal_tab',
			array(
				'label' => esc_attr__( 'Normal', 'wp-easycart' ),
			)
		);

		$this->add_responsive_control(
			'link_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Color', 'wp-easycart' ),
				'default' => ( get_option( 'ec_option_details_main_color' ) != '' ) ? get_option( 'ec_option_details_main_color' ) : '#333333',
				'selectors' => array(
					'{{WRAPPER}} .ec_breadcrumbs_link' => 'color: {{VALUE}}',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'style_hover_tab',
			array(
				'label' => esc_attr__( 'Hover', 'wp-easycart' ),
			)
		);

		$this->add_responsive_control(
			'link_hover_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Hover Color', 'wp-easycart' ),
				'default' => ( get_option( 'ec_option_details_main_color' ) != '' ) ? get_option( 'ec_option_details_main_color' ) : '#333333',
				'selectors' => array(
					'{{WRAPPER}} .ec_breadcrumbs_link:hover' => 'color: {{VALUE}}',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'link_font',
				'label' => esc_attr__( 'Typography', 'wp-easycart' ),
				'selectors' => array(
					'{{WRAPPER}} .ec_breadcrumbs_link',
				),
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'style_section_item',
			array(
				'label' => esc_attr__( 'Product Item', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'item_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Color', 'wp-easycart' ),
				'default' => ( get_option( 'ec_option_details_main_color' ) != '' ) ? get_option( 'ec_option_details_main_color' ) : '#333333',
				'selectors' => array(
					'{{WRAPPER}} .ec_breadcrumbs_item' => 'color: {{VALUE}}',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'item_font',
				'label' => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_breadcrumbs_item',
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'style_section_divider',
			array(
				'label' => esc_attr__( 'Divider', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'divider_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Color', 'wp-easycart' ),
				'default' => ( get_option( 'ec_option_details_main_color' ) != '' ) ? get_option( 'ec_option_details_main_color' ) : '#CCCCCC',
				'selectors' => array(
					'{{WRAPPER}} .ec_breadcrumbs_divider' => 'color: {{VALUE}}',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'divider_font',
				'label' => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_breadcrumbs_divider',
			)
		);

		$this->add_responsive_control(
			'divider_spacing',
			array(
				'label' => esc_attr__( 'Spacing', 'wp-easycart' ),
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
					'size' => 5,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_breadcrumbs_divider' => 'padding-left: {{SIZE}}{{UNIT}};padding-right: {{SIZE}}{{UNIT}};',
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
		include( EC_PLUGIN_DIRECTORY . '/admin/elementor/wp-easycart-elementor-product-details-breadcrumbs-widget.php' );
	}
}
