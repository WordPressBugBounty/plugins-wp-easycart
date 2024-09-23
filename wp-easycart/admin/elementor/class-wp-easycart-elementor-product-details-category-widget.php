<?php
/**
 * WP EasyCart Product Details Category Widget for Elementor
 *
 * @category Class
 * @package  Wp_Easycart_Elementor_Product_Details_Category_Widget
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
 * WP EasyCart Product Details Category Widget for Elementor
 *
 * @category Class
 * @package  Wp_Easycart_Elementor_Product_Details_Category_Widget
 * @author   WP EasyCart
 */
class Wp_Easycart_Elementor_Product_Details_Category_Widget extends \Elementor\Widget_Base {

	/**
	 * Get product details category widget name.
	 */
	public function get_name() {
		return 'wp_easycart_product_details_category';
	}

	/**
	 * Get product details category widget title.
	 */
	public function get_title() {
		return esc_attr__( 'WP EasyCart Product Categories', 'wp-easycart' );
	}

	/**
	 * Get product details category widget icon.
	 */
	public function get_icon() {
		return 'eicon-product-categories';
	}

	/**
	 * Get product details category widget categories.
	 */
	public function get_categories() {
		return array( 'wp-easycart-elements' );
	}

	/**
	 * Get product details category widget keywords.
	 */
	public function get_keywords() {
		return array( 'category', 'wp-easycart' );
	}

	/**
	 * Enqueue product details category widget scripts and styles.
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
				'label' => esc_attr__( 'Categories', 'wp-easycart' ),
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
			'categories_label',
			array(
				'type'  => Controls_Manager::TEXT,
				'label' => esc_attr__( 'Label', 'wp-easycart' ),
				'default' => wp_easycart_language( )->get_text( 'product_details', 'product_details_categories' ),
				'placeholder' => esc_attr__( 'Enter label (optional)', 'wp-easycart' ),
			)
		);

		$this->add_control(
			'categories_divider',
			array(
				'type'  => Controls_Manager::TEXT,
				'label' => esc_attr__( 'Separator Character', 'wp-easycart' ),
				'default' => ',',
				'placeholder' => 'Enter separator character',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'style_section_container',
			array(
				'label' => esc_attr__( 'Categories', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'categories_element',
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
			'categories_align',
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
					'{{WRAPPER}} .ec_details_categories_ele' => 'text-align: {{VALUE}};',
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
					'{{WRAPPER}} .ec_category_label' => 'color: {{VALUE}}',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'label_font',
				'label' => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_category_label',
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
					'{{WRAPPER}} .ec_category_link' => 'color: {{VALUE}}',
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
					'{{WRAPPER}} .ec_category_link:hover' => 'color: {{VALUE}}',
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
				'selector' => '{{WRAPPER}} .ec_category_link',
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
				'default' => ( get_option( 'ec_option_details_main_color' ) != '' ) ? get_option( 'ec_option_details_main_color' ) : '#333333',
				'selectors' => array(
					'{{WRAPPER}} .ec_category_divider' => 'color: {{VALUE}}',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'divider_font',
				'label' => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_category_divider',
			)
		);

		$this->add_responsive_control(
			'divider_spacing_left',
			array(
				'label' => esc_attr__( 'Spacing Left', 'wp-easycart' ),
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
					'size' => 0,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_category_divider' => 'padding-left: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'divider_spacing_right',
			array(
				'label' => esc_attr__( 'Spacing Right', 'wp-easycart' ),
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
					'{{WRAPPER}} .ec_category_divider' => 'padding-right: {{SIZE}}{{UNIT}};',
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
		include( EC_PLUGIN_DIRECTORY . '/admin/elementor/wp-easycart-elementor-product-details-category-widget.php' );
	}
}
