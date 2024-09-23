<?php
/**
 * WP EasyCart Product Details Tabs Widget for Elementor
 *
 * @category Class
 * @package  Wp_Easycart_Elementor_Product_Details_Tabs_Widget
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
 * WP EasyCart Product Details Tabs Widget for Elementor
 *
 * @category Class
 * @package  Wp_Easycart_Elementor_Product_Details_Tabs_Widget
 * @author   WP EasyCart
 */
class Wp_Easycart_Elementor_Product_Details_Tabs_Widget extends \Elementor\Widget_Base {

	/**
	 * Get product details tabs widget name.
	 */
	public function get_name() {
		return 'wp_easycart_product_details_tabs';
	}

	/**
	 * Get product details tabs widget title.
	 */
	public function get_title() {
		return esc_attr__( 'WP EasyCart Product Tabs', 'wp-easycart' );
	}

	/**
	 * Get product details tabs widget icon.
	 */
	public function get_icon() {
		return 'eicon-product-tabs';
	}

	/**
	 * Get product details tabs widget categories.
	 */
	public function get_categories() {
		return array( 'wp-easycart-elements' );
	}

	/**
	 * Get product details tabs widget keywords.
	 */
	public function get_keywords() {
		return array( 'tabs', 'wp-easycart' );
	}

	/**
	 * Enqueue product details tabs widget scripts and styles.
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
				'label' => esc_attr__( 'Tabs Options', 'wp-easycart' ),
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
			'description',
			array(
				'type'  => Controls_Manager::SWITCHER,
				'label' => esc_attr__( 'Display Description', 'wp-easycart' ),
				'default' => 1,
			)
		);

		$this->add_control(
			'specifications',
			array(
				'type'  => Controls_Manager::SWITCHER,
				'label' => esc_attr__( 'Display Specifications', 'wp-easycart' ),
				'default' => 1,
			)
		);

		$this->add_control(
			'customer_reviews',
			array(
				'type'  => Controls_Manager::SWITCHER,
				'label' => esc_attr__( 'Display Customer Reviews', 'wp-easycart' ),
				'default'   => 1,
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render search widget control output in the editor.
	 */
	protected function render() {
		$atts = $this->get_settings_for_display();
		include( EC_PLUGIN_DIRECTORY . '/admin/elementor/wp-easycart-elementor-product-details-tabs-widget.php' );
	}
}
