<?php
/**
 * WP EasyCart Product Details Rating Widget for Elementor
 *
 * @category Class
 * @package  Wp_Easycart_Elementor_Product_Details_Rating_Widget
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
 * WP EasyCart Product Details Rating Widget for Elementor
 *
 * @category Class
 * @package  Wp_Easycart_Elementor_Product_Details_Rating_Widget
 * @author   WP EasyCart
 */
class Wp_Easycart_Elementor_Product_Details_Rating_Widget extends \Elementor\Widget_Base {

	/**
	 * Get product details rating widget name.
	 */
	public function get_name() {
		return 'wp_easycart_product_details_rating';
	}

	/**
	 * Get product details rating widget title.
	 */
	public function get_title() {
		return esc_attr__( 'WP EasyCart Product Rating', 'wp-easycart' );
	}

	/**
	 * Get product details rating widget icon.
	 */
	public function get_icon() {
		return 'eicon-product-rating';
	}

	/**
	 * Get product details rating widget categories.
	 */
	public function get_categories() {
		return array( 'wp-easycart-elements' );
	}

	/**
	 * Get product details rating widget keywords.
	 */
	public function get_keywords() {
		return array( 'rating', 'wp-easycart' );
	}

	/**
	 * Enqueue product details rating widget scripts and styles.
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
				'label' => esc_attr__( 'Rating', 'wp-easycart' ),
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
			'ec_rw_style_section_rating',
			array(
				'label' => esc_attr__( 'Rating', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
			)
		);
		
		$this->add_responsive_control(
			'ec_rw_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Active Color', 'wp-easycart' ),
				'default' => ( get_option( 'ec_option_details_main_color' ) != '' ) ? get_option( 'ec_option_details_main_color' ) : '#333333',
				'selectors' => array(
					'{{WRAPPER}} .ec_product_details_star_on_ele' => 'border-bottom-color: {{VALUE}}; color: {{VALUE}}',
					'{{WRAPPER}} .ec_product_details_star_on_ele:before' => 'border-bottom-color: {{VALUE}};',
					'{{WRAPPER}} .ec_product_details_star_on_ele:after' => 'border-bottom-color: {{VALUE}}; color: {{VALUE}}',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_rw_color_inactive',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Inactive Color', 'wp-easycart' ),
				'default' => '#CCCCCC',
				'selectors' => array(
					'{{WRAPPER}} .ec_product_details_star_off_ele' => 'border-bottom-color: {{VALUE}}; color: {{VALUE}}',
					'{{WRAPPER}} .ec_product_details_star_off_ele:before' => 'border-bottom-color: {{VALUE}};',
					'{{WRAPPER}} .ec_product_details_star_off_ele:after' => 'border-bottom-color: {{VALUE}}; color: {{VALUE}}',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_rw_align',
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
					'{{WRAPPER}} .ec_details_rating' => 'text-align: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'ec_rw_spacing',
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
					'{{WRAPPER}} .ec_product_details_star_on_ele' => 'margin-right: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .ec_product_details_star_off_ele' => 'margin-right: {{SIZE}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_rw_padding',
			array(
				'label' => esc_attr__( 'Padding', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_rating' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_rw_margin',
			array(
				'label' => esc_attr__( 'Margin', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_rating' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
		include( EC_PLUGIN_DIRECTORY . '/admin/elementor/wp-easycart-elementor-product-details-rating-widget.php' );
	}
}
