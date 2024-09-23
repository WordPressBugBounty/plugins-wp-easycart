<?php
/**
 * WP EasyCart Product Details Customer Reviews Widget for Elementor
 *
 * @category Class
 * @package  Wp_Easycart_Elementor_Product_Details_Customer_Reviews_Widget
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
use Elementor\Group_Control_Background;
use Elementor\Utils;
use Elementor\Wp_Easycart_Controls_Manager;

/**
 * WP EasyCart Product Details Customer Reviews Widget for Elementor
 *
 * @category Class
 * @package  Wp_Easycart_Elementor_Product_Details_Customer_Reviews_Widget
 * @author   WP EasyCart
 */
class Wp_Easycart_Elementor_Product_Details_Customer_Reviews_Widget extends \Elementor\Widget_Base {

	/**
	 * Get product details customer_reviews widget name.
	 */
	public function get_name() {
		return 'wp_easycart_product_details_customer_reviews';
	}

	/**
	 * Get product details customer reviews widget customer reviews.
	 */
	public function get_title() {
		return esc_attr__( 'WP EasyCart Product Customer Reviews', 'wp-easycart' );
	}

	/**
	 * Get product details customer reviews widget icon.
	 */
	public function get_icon() {
		return 'eicon-notes';
	}

	/**
	 * Get product details customer reviews widget categories.
	 */
	public function get_categories() {
		return array( 'wp-easycart-elements' );
	}

	/**
	 * Get product details customer reviews widget keywords.
	 */
	public function get_keywords() {
		return array( 'customer reviews', 'wp-easycart' );
	}

	/**
	 * Enqueue product details customer reviews widget scripts and styles.
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
				'label' => esc_attr__( 'Customer Reviews', 'wp-easycart' ),
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
		
		$this->add_responsive_control(
			'enable_review_list',
			array(
				'type'      => Controls_Manager::SWITCHER,
				'label'     => esc_attr__( 'Enable Review List', 'wp-easycart' ),
				'default'   => 'yes',
			)
		);
		
		$this->add_responsive_control(
			'enable_review_list_title',
			array(
				'type'      => Controls_Manager::SWITCHER,
				'label'     => esc_attr__( 'Enable Review List Title', 'wp-easycart' ),
				'default'   => 'yes',
				'condition' => array(
					'enable_review_list' => array( 'yes' ),
				),
			)
		);
		
		$this->add_responsive_control(
			'enable_review_item_title',
			array(
				'type'      => Controls_Manager::SWITCHER,
				'label'     => esc_attr__( 'Enable Single Item Title', 'wp-easycart' ),
				'default'   => 'yes',
				'condition' => array(
					'enable_review_list' => array( 'yes' ),
				),
			)
		);
		
		$this->add_responsive_control(
			'enable_review_item_date',
			array(
				'type'      => Controls_Manager::SWITCHER,
				'label'     => esc_attr__( 'Enable Single Item Date', 'wp-easycart' ),
				'default'   => 'yes',
				'condition' => array(
					'enable_review_list' => array( 'yes' ),
				),
			)
		);
		
		$this->add_responsive_control(
			'enable_review_item_user_name',
			array(
				'type'      => Controls_Manager::SWITCHER,
				'label'     => esc_attr__( 'Enable Single Item Name', 'wp-easycart' ),
				'default'   => 'no',
				'condition' => array(
					'enable_review_list' => array( 'yes' ),
				),
			)
		);
		
		$this->add_responsive_control(
			'enable_review_item_rating',
			array(
				'type'      => Controls_Manager::SWITCHER,
				'label'     => esc_attr__( 'Enable Single Item Rating', 'wp-easycart' ),
				'default'   => 'yes',
				'condition' => array(
					'enable_review_list' => array( 'yes' ),
				),
			)
		);
		
		$this->add_responsive_control(
			'enable_review_item_review',
			array(
				'type'      => Controls_Manager::SWITCHER,
				'label'     => esc_attr__( 'Enable Single Item Review', 'wp-easycart' ),
				'default'   => 'yes',
				'condition' => array(
					'enable_review_list' => array( 'yes' ),
				),
			)
		);
		
		$this->add_responsive_control(
			'enable_review_form',
			array(
				'type'      => Controls_Manager::SWITCHER,
				'label'     => esc_attr__( 'Enable Review Form', 'wp-easycart' ),
				'default'   => 'yes',
			)
		);
		
		$this->add_responsive_control(
			'enable_review_form_title',
			array(
				'type'      => Controls_Manager::SWITCHER,
				'label'     => esc_attr__( 'Enable Review Form Title', 'wp-easycart' ),
				'default'   => 'yes',
				'condition' => array(
					'enable_review_form' => array( 'yes' ),
				),
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'style_section_list_main_title',
			array(
				'label' => esc_attr__( 'List Main Title', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'enable_review_list' => array( 'yes' ),
					'enable_review_list_title' => array( 'yes' ),
				),
			)
		);
		
		$this->add_responsive_control(
			'list_main_title_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Color', 'wp-easycart' ),
				'default' => ( get_option( 'ec_option_details_main_color' ) != '' ) ? get_option( 'ec_option_details_main_color' ) : '#333333',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_customer_reviews_list_title' => 'color: {{VALUE}}',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'list_main_title_font',
				'label' => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_details_customer_reviews_list_title',
			)
		);
		
		$this->add_responsive_control(
			'list_main_title_align',
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
					'{{WRAPPER}} .ec_details_customer_reviews_list_title' => 'text-align: {{VALUE}};',
				),
			)
		);
		
		$this->end_controls_section();
		
		$this->start_controls_section(
			'style_section_customer_reviews_container',
			array(
				'label' => esc_attr__( 'Customer Reviews', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'enable_review_list' => array( 'yes' ),
				),
			)
		);

		$this->add_responsive_control(
			'customer_reviews_columns',
			array(
				'label'     => esc_attr__( 'Columns', 'wp-easycart' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'inline',
				'options'   => array(
					'column' => esc_attr__( '1 Column', 'wp-easycart' ),
					'column-reverse' => esc_attr__( '1 Column Reversed', 'wp-easycart' ),
					'row' => esc_attr__( '2 Columns', 'wp-easycart' ),
					'row-reverse' => esc_attr__( '2 Columns Reversed', 'wp-easycart' ),
				),
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-product-details-customer-reviews-shortcode-wrapper' => 'display:flex; flex-direction: {{VALUE}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'list_width_percentage',
			array(
				'label' => esc_attr__( 'Review List Width', 'wp-easycart' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'range' => array(
					'%' => array(
						'min' => 1,
						'max' => 100,
					),
					'px' => array(
						'min' => 1,
						'max' => 2000,
					),
				),
				'default' => array(
					'unit' => '%',
					'size' => 60,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_customer_reviews_left_ele' => 'width: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .ec_details_customer_reviews_form_ele' => 'width: calc( 100% - {{SIZE}}{{UNIT}} );',
				),
				'condition' => array(
					'enable_review_form' => array( 'yes' ),
					'customer_reviews_columns' => array( 'row', 'row-reverse' ),
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
					'{{WRAPPER}} .wp-easycart-product-details-customer-reviews-shortcode-wrapper' => 'column-gap: {{SIZE}}{{UNIT}};',
				),
				'condition' => array(
					'customer_reviews_columns' => array( 'row', 'row-reverse' ),
				),
			)
		);
		
		$this->add_responsive_control(
			'one_column_width',
			array(
				'label' => esc_attr__( 'Column Width', 'wp-easycart' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'range' => array(
					'px' => array(
						'min' => 1,
						'max' => 2000,
					),
					'%' => array(
						'min' => 1,
						'max' => 100,
					),
				),
				'default' => array(
					'unit' => '%',
					'size' => 100,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_customer_reviews_left_ele' => 'width: {{SIZE}}{{UNIT}} !important;',
					'{{WRAPPER}} .ec_details_customer_reviews_form_ele' => 'width: {{SIZE}}{{UNIT}} !important;',
				),
				'condition' => array(
					'customer_reviews_columns' => array( 'column', 'column-reverse' ),
				),
			)
		);
		
		$this->end_controls_section();
		
		$this->start_controls_section(
			'style_section_list_container',
			array(
				'label' => esc_attr__( 'List Container', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'enable_review_list' => array( 'yes' ),
				),
			)
		);

		$this->add_responsive_control(
			'list_background_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Background Color', 'wp-easycart' ),
				'default' => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_customer_review_list' => 'background-color: {{VALUE}}',
				),
			)
		);
		
		$this->add_responsive_control(
			'list_padding',
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
					'{{WRAPPER}} .ec_details_customer_review_list' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);
		
		$this->add_responsive_control(
			'list_margin',
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
					'{{WRAPPER}} .ec_details_customer_review_list' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);

		$this->add_responsive_control(
			'wpec_crw_list_border_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Border Color', 'wp-easycart' ),
				'default' => '#CCCCCC',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_customer_review_list' => 'border-color: {{VALUE}}',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'list_border',
				'default' => array(
					'type' => 'solid',
					'top' => 1,
					'right' => 0,
					'bottom' => 0,
					'left' => 0,
					'unit' => 'px',
					'isLinked' => false,
				),
				'selector' => '{{WRAPPER}} .ec_details_customer_review_list',
			)
		);
		
		$this->add_responsive_control(
			'list_border_radius',
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
					'{{WRAPPER}} .ec_details_customer_review_list' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->end_controls_section();
		
		$this->start_controls_section(
			'style_section_list_item_container',
			array(
				'label' => esc_attr__( 'List Item Container', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'enable_review_list' => array( 'yes' ),
				),
			)
		);
		
		$this->add_responsive_control(
			'list_item_width_percentage',
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
					'size' => 90,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_customer_review_list' => 'width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'list_item_background_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Background Color', 'wp-easycart' ),
				'default' => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_customer_review_list > li' => 'background-color: {{VALUE}}',
				),
			)
		);
		
		$this->add_responsive_control(
			'list_item_padding',
			array(
				'label' => esc_attr__( 'Padding', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 0,
					'right' => 0,
					'bottom' => 20,
					'left' => 0,
					'unit' => 'px',
					'isLinked' => false,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_customer_review_list > li' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'list_item_margin',
			array(
				'label' => esc_attr__( 'Margin', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 0,
					'right' => 0,
					'bottom' => 20,
					'left' => 0,
					'unit' => 'px',
					'isLinked' => false,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_customer_review_list > li' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);

		$this->add_responsive_control(
			'wpec_crw_list_item_border_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Border Color', 'wp-easycart' ),
				'default' => '#CCCCCC',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_customer_review_list > li' => 'border-color: {{VALUE}}',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'list_item_border',
				'default' => array(
					'type' => 'solid',
					'top' => 0,
					'right' => 0,
					'bottom' => 1,
					'left' => 0,
					'unit' => 'px',
					'isLinked' => false,
				),
				'selector' => '{{WRAPPER}} .ec_details_customer_review_list > li',
			)
		);
		
		$this->add_responsive_control(
			'list_item_border_radius',
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
					'{{WRAPPER}} .ec_details_customer_review_list > li' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'style_section_list_item_title',
			array(
				'label' => esc_attr__( 'List Item Title', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'enable_review_list' => array( 'yes' ),
					'enable_review_item_title' => array( 'yes' ),
				),
			)
		);
		
		$this->add_responsive_control(
			'list_item_title_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Color', 'wp-easycart' ),
				'default' => ( get_option( 'ec_option_details_main_color' ) != '' ) ? get_option( 'ec_option_details_main_color' ) : '#333333',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_customer_review_title_ele' => 'color: {{VALUE}}',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'list_item_title_font',
				'label' => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_details_customer_review_title_ele',
			)
		);
		
		$this->add_responsive_control(
			'list_item_title_align',
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
					'{{WRAPPER}} .ec_details_customer_review_title_ele' => 'text-align: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'list_item_title_width',
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
					'{{WRAPPER}} .ec_details_customer_review_title_ele' => 'display: {{VALUE}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'list_item_title_width_percentage',
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
					'{{WRAPPER}} .ec_details_customer_review_title_ele' => 'float:left; display:block; width: {{SIZE}}{{UNIT}};',
				),
				'condition' => array(
					'list_item_title_width' => array( 'initial' ),
				),
			)
		);
		
		$this->add_responsive_control(
			'list_item_title_padding',
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
					'{{WRAPPER}} .ec_details_customer_review_title_ele' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'list_item_title_margin',
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
					'{{WRAPPER}} .ec_details_customer_review_title_ele' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);

		$this->add_responsive_control(
			'wpec_crw_list_item_title_border_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Border Color', 'wp-easycart' ),
				'default' => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_customer_review_title_ele' => 'border-color: {{VALUE}}',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'list_item_title_border',
				'selector' => '{{WRAPPER}} .ec_details_customer_review_title_ele',
			)
		);
		
		$this->add_responsive_control(
			'list_item_title_border_radius',
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
					'{{WRAPPER}} .ec_details_customer_review_title_ele' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'style_section_list_item_date',
			array(
				'label' => esc_attr__( 'List Item Date', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'enable_review_list' => array( 'yes' ),
					'enable_review_item_date' => array( 'yes' ),
				),
			)
		);
		
		$this->add_responsive_control(
			'list_item_date_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Color', 'wp-easycart' ),
				'default' => ( get_option( 'ec_option_details_main_color' ) != '' ) ? get_option( 'ec_option_details_main_color' ) : '#333333',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_customer_review_date_ele' => 'color: {{VALUE}}',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'list_item_date_font',
				'label' => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_details_customer_review_date_ele',
			)
		);
		
		$this->add_responsive_control(
			'list_item_date_align',
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
					'{{WRAPPER}} .ec_details_customer_review_date_ele' => 'text-align: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'list_item_date_width',
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
					'{{WRAPPER}} .ec_details_customer_review_date_ele' => 'display: {{VALUE}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'list_item_date_width_percentage',
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
					'{{WRAPPER}} .ec_details_customer_review_date_ele' => 'float:left; display:block; width: {{SIZE}}{{UNIT}};',
				),
				'condition' => array(
					'list_item_date_width' => array( 'initial' ),
				),
			)
		);
		
		$this->add_responsive_control(
			'list_item_date_padding',
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
					'{{WRAPPER}} .ec_details_customer_review_date_ele' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'list_item_date_margin',
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
					'{{WRAPPER}} .ec_details_customer_review_date_ele' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);

		$this->add_responsive_control(
			'wpec_crw_list_item_date_border_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Border Color', 'wp-easycart' ),
				'default' => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_customer_review_date_ele' => 'border-color: {{VALUE}}',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'list_item_date_border',
				'selector' => '{{WRAPPER}} .ec_details_customer_review_date_ele',
			)
		);
		
		$this->add_responsive_control(
			'list_item_date_border_radius',
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
					'isLinked' => false,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_customer_review_date_ele' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'style_section_list_item_rating',
			array(
				'label' => esc_attr__( 'List Item Rating', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'enable_review_list' => array( 'yes' ),
					'enable_review_item_rating' => array( 'yes' ),
				),
			)
		);
		
		$this->add_responsive_control(
			'list_item_rating_color',
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
			'list_item_rating_color_inactive',
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
			'list_item_rating_align',
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
					'{{WRAPPER}} .ec_details_customer_review_stars' => 'text-align: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'list_item_rating_spacing',
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
			'list_item_rating_padding',
			array(
				'label' => esc_attr__( 'Padding', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_customer_review_stars' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'list_item_rating_margin',
			array(
				'label' => esc_attr__( 'Margin', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_customer_review_stars' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'style_section_list_item_user_name',
			array(
				'label' => esc_attr__( 'List Item User Name', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'enable_review_list' => array( 'yes' ),
					'enable_review_item_user_name' => array( 'yes' ),
				),
			)
		);
		
		$this->add_responsive_control(
			'list_item_user_name_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Color', 'wp-easycart' ),
				'default' => ( get_option( 'ec_option_details_main_color' ) != '' ) ? get_option( 'ec_option_details_main_color' ) : '#333333',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_customer_review_name_ele' => 'color: {{VALUE}}',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'list_item_user_name_font',
				'label' => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_details_customer_review_name_ele',
			)
		);
		
		$this->add_responsive_control(
			'list_item_user_name_align',
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
					'{{WRAPPER}} .ec_details_customer_review_name_ele' => 'text-align: {{VALUE}};',
				),
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'style_section_list_item_review',
			array(
				'label' => esc_attr__( 'List Item Review', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'enable_review_list' => array( 'yes' ),
					'enable_review_item_review' => array( 'yes' ),
				),
			)
		);
		
		$this->add_responsive_control(
			'list_item_review_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Color', 'wp-easycart' ),
				'default' => ( get_option( 'ec_option_details_main_color' ) != '' ) ? get_option( 'ec_option_details_main_color' ) : '#333333',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_customer_review_data_ele' => 'color: {{VALUE}}',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'list_item_review_font',
				'label' => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_details_customer_review_data_ele',
			)
		);
		
		$this->add_responsive_control(
			'list_item_review_align',
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
					'{{WRAPPER}} .ec_details_customer_review_data_ele' => 'text-align: {{VALUE}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'list_item_review_background_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Background Color', 'wp-easycart' ),
				'default' => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_customer_review_data_ele' => 'background-color: {{VALUE}}',
				),
			)
		);
		
		$this->add_responsive_control(
			'list_item_review_padding',
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
					'{{WRAPPER}} .ec_details_customer_review_data_ele' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'list_item_review_margin',
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
					'{{WRAPPER}} .ec_details_customer_review_data_ele' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);

		$this->add_responsive_control(
			'wpec_crw_list_item_review_border_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Border Color', 'wp-easycart' ),
				'default' => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_customer_review_data_ele' => 'border-color: {{VALUE}}',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'list_item_review_border',
				'selector' => '{{WRAPPER}} .ec_details_customer_review_data_ele',
			)
		);
		
		$this->add_responsive_control(
			'list_item_review_border_radius',
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
					'{{WRAPPER}} .ec_details_customer_review_data_ele' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->end_controls_section();
		
		$this->start_controls_section(
			'style_section_form',
			array(
				'label' => esc_attr__( 'Form Container', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'enable_review_form' => array( 'yes' ),
				),
			)
		);
		
		$this->add_responsive_control(
			'form_padding',
			array(
				'label' => esc_attr__( 'Padding', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),'default' => array(
					'top' => 20,
					'right' => 20,
					'bottom' => 20,
					'left' => 20,
					'unit' => 'px',
					'isLinked' => true,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_customer_reviews_form_holder' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);
		
		$this->add_responsive_control(
			'form_margin',
			array(
				'label' => esc_attr__( 'Margin', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 0,
					'right' => 0,
					'bottom' => 0,
					'left' => 0,
					'unit' => '%',
					'isLinked' => false,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_customer_reviews_form_holder' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'form_background_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Background Color', 'wp-easycart' ),
				'default' => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_customer_reviews_form_holder' => 'background-color: {{VALUE}}',
				),
			)
		);

		$this->add_responsive_control(
			'wpec_crw_form_border_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Border Color', 'wp-easycart' ),
				'default' => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_customer_reviews_form_holder' => 'border-color: {{VALUE}}',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'form_border',
				'selector' => '{{WRAPPER}} .ec_details_customer_reviews_form_holder',
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'style_section_form_title',
			array(
				'label' => esc_attr__( 'Form Title', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'enable_review_form' => array( 'yes' ),
					'enable_review_form_title' => array( 'yes' ),
				),
			)
		);
		
		$this->add_responsive_control(
			'form_title_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Color', 'wp-easycart' ),
				'default' => ( get_option( 'ec_option_details_main_color' ) != '' ) ? get_option( 'ec_option_details_main_color' ) : '#333333',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_customer_review_form_title_ele' => 'color: {{VALUE}}',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'form_title_font',
				'label' => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_details_customer_review_form_title_ele',
			)
		);
		
		$this->add_responsive_control(
			'form_title_align',
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
					'{{WRAPPER}} .ec_details_customer_review_form_title_ele' => 'text-align: {{VALUE}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'title_padding',
			array(
				'label' => esc_attr__( 'Padding', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_customer_review_form_title_ele' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'title_margin',
			array(
				'label' => esc_attr__( 'Margin', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_customer_review_form_title_ele' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'title_background_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Background Color', 'wp-easycart' ),
				'default' => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_customer_review_form_title_ele' => 'background-color: {{VALUE}}',
				),
			)
		);

		$this->add_responsive_control(
			'wpec_crw_title_border_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Border Color', 'wp-easycart' ),
				'default' => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_customer_review_form_title_ele' => 'border-color: {{VALUE}}',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'title_border',
				'selector' => '{{WRAPPER}} .ec_details_customer_review_form_title_ele',
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'style_section_form_label',
			array(
				'label' => esc_attr__( 'Form Label', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'enable_review_form' => array( 'yes' ),
				),
			)
		);
		
		$this->add_responsive_control(
			'form_label_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Color', 'wp-easycart' ),
				'default' => ( get_option( 'ec_option_details_main_color' ) != '' ) ? get_option( 'ec_option_details_main_color' ) : '#333333',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_customer_reviews_label_ele' => 'color: {{VALUE}}',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'form_label_font',
				'label' => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_details_customer_reviews_label_ele',
			)
		);
		
		$this->add_responsive_control(
			'form_label_align',
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
					'{{WRAPPER}} .ec_details_customer_reviews_label_ele' => 'text-align: {{VALUE}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'form_label_background_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Background Color', 'wp-easycart' ),
				'default' => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_customer_reviews_label_ele' => 'background-color: {{VALUE}}',
				),
			)
		);
		
		$this->add_responsive_control(
			'form_label_padding',
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
					'{{WRAPPER}} .ec_details_customer_reviews_label_ele' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'form_label_margin',
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
					'{{WRAPPER}} .ec_details_customer_reviews_label_ele' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);

		$this->add_responsive_control(
			'wpec_crw_form_label_border_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Border Color', 'wp-easycart' ),
				'default' => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_customer_reviews_label_ele' => 'border-color: {{VALUE}}',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'form_label_border',
				'selector' => '{{WRAPPER}} .ec_details_customer_reviews_label_ele',
			)
		);
		
		$this->add_responsive_control(
			'form_label_border_radius',
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
					'{{WRAPPER}} .ec_details_customer_reviews_label_ele' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'style_section_form_item_rating',
			array(
				'label' => esc_attr__( 'Form Rating', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'enable_review_list' => array( 'yes' ),
					'enable_review_item_rating' => array( 'yes' ),
				),
			)
		);
		
		$this->add_responsive_control(
			'form_rating_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Active Color', 'wp-easycart' ),
				'default' => ( get_option( 'ec_option_details_main_color' ) != '' ) ? get_option( 'ec_option_details_main_color' ) : '#333333',
				'selectors' => array(
					'{{WRAPPER}} .ec_product_details_star_on_ele' => 'border-bottom-color: {{VALUE}} !important; color: {{VALUE}} !important',
					'{{WRAPPER}} .ec_product_details_star_on_ele:before' => 'border-bottom-color: {{VALUE}} !important;',
					'{{WRAPPER}} .ec_product_details_star_on_ele:after' => 'border-bottom-color: {{VALUE}} !important; color: {{VALUE}} !important',
				),
			)
		);
		
		$this->add_responsive_control(
			'form_rating_color_inactive',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Inactive Color', 'wp-easycart' ),
				'default' => '#CCCCCC',
				'selectors' => array(
					'{{WRAPPER}} .ec_product_details_star_off_ele' => 'border-bottom-color: {{VALUE}}; color: {{VALUE}};',
					'{{WRAPPER}} .ec_product_details_star_off_ele:before' => 'border-bottom-color: {{VALUE}};',
					'{{WRAPPER}} .ec_product_details_star_off_ele:after' => 'border-bottom-color: {{VALUE}}; color: {{VALUE}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'form_rating_align',
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
					'{{WRAPPER}} .ec_details_customer_reviews_row.ec_stars' => 'text-align: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'form_rating_spacing',
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
			'form_rating_padding',
			array(
				'label' => esc_attr__( 'Padding', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_customer_reviews_row.ec_stars' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'form_rating_margin',
			array(
				'label' => esc_attr__( 'Margin', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_customer_reviews_row.ec_stars' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'style_section_form_button',
			array(
				'label' => esc_attr__( 'Form Button', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'enable_review_form' => array( 'yes' ),
				),
			)
		);

		$this->add_control(
			'form_button_text',
			array(
				'type'  => Controls_Manager::TEXT,
				'label' => esc_attr__( 'Text', 'wp-easycart' ),
				'default' => wp_easycart_language( )->get_text( 'customer_review', 'product_details_your_review_submit' ),
				'placeholder' => esc_attr__( 'Submit', 'wp-easycart' ),
			)
		);
		
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'form_button_font',
				'label' => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_details_customer_reviews_button_ele',
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
			'form_button_text_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Text Color', 'wp-easycart' ),
				'default' => '#FFFFFF',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_customer_reviews_button_ele' => 'color: {{VALUE}}',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'form_button_background',
				'types' => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .ec_details_customer_reviews_button_ele',
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
			'form_button_text_color_hover',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Text Color', 'wp-easycart' ),
				'default' => '#FFFFFF',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_customer_reviews_button_ele:hover' => 'color: {{VALUE}}',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'form_button_background_hover',
				'types' => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .ec_details_customer_reviews_button_ele:hover',
			)
		);

		$this->add_responsive_control(
			'form_button_border_color_hover',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Border Color', 'wp-easycart' ),
				'default' => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_customer_reviews_button_ele:hover' => 'border-color: {{VALUE}}',
				),
			)
		);
		
		$this->add_responsive_control(
			'form_button_hover_animation',
			array(
				'label' => esc_attr__( 'Hover Animation', 'wp-easycart' ),
				'type' => Controls_Manager::HOVER_ANIMATION,
				'selector' => '{{WRAPPER}} .ec_details_customer_reviews_button_ele',
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();
		
		$this->add_responsive_control(
			'form_button_align',
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
					'{{WRAPPER}} .ec_details_customer_reviews_button_ele' => 'text-align: {{VALUE}};',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'button_border',
				'selector' => '{{WRAPPER}} .ec_details_customer_reviews_button_ele',
			)
		);
		
		$this->add_responsive_control(
			'button_padding',
			array(
				'label' => esc_attr__( 'Padding', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_customer_reviews_button_ele' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'button_margin',
			array(
				'label' => esc_attr__( 'Margin', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_customer_reviews_button_ele' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'style_section_form_error',
			array(
				'label' => esc_attr__( 'Form Error Message', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'enable_review_form' => array( 'yes' ),
				),
			)
		);
		
		$this->add_responsive_control(
			'form_error_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Color', 'wp-easycart' ),
				'default' => '#AE0000',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_option_row_error > span' => 'color: {{VALUE}}',
				),
			)
		);
		
		$this->add_responsive_control(
			'form_error_background_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Background Color', 'wp-easycart' ),
				'default' => '#FFE7E7',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_option_row_error' => 'background-color: {{VALUE}}',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'form_error_font',
				'label' => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_details_option_row_error > span',
			)
		);
		
		$this->add_responsive_control(
			'form_error_align',
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
					'{{WRAPPER}} .ec_details_option_row_error' => 'text-align: {{VALUE}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'form_error_padding',
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
					'{{WRAPPER}} .ec_details_option_row_error' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'form_error_margin',
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
					'{{WRAPPER}} .ec_details_option_row_error' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);

		$this->add_responsive_control(
			'wpec_crw_form_error_border_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Border Color', 'wp-easycart' ),
				'default' => '#AE0000',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_option_row_error' => 'border-color: {{VALUE}} !important;',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'form_error_border',
				'default' => array(
					'type' => 'dashed',
					'top' => 1,
					'right' => 1,
					'bottom' => 1,
					'left' => 1,
					'unit' => 'px',
					'isLinked' => true,
				),
				'selector' => '{{WRAPPER}} .ec_details_option_row_error',
			)
		);
		
		$this->add_responsive_control(
			'form_error_border_radius',
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
					'{{WRAPPER}} .ec_details_option_row_error' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->end_controls_section();

		$this->start_controls_section(
			'style_section_form_login_note',
			array(
				'label' => esc_attr__( 'Form Login Note', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'enable_review_form' => array( 'yes' ),
				),
			)
		);
		
		$this->add_responsive_control(
			'form_login_note_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Text Color', 'wp-easycart' ),
				'default' => ( get_option( 'ec_option_details_main_color' ) != '' ) ? get_option( 'ec_option_details_main_color' ) : '#333333',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_customer_reviews_form_login_note' => 'color: {{VALUE}}',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'form_login_note_font',
				'label' => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_details_customer_reviews_form_login_note',
			)
		);
		
		$this->add_responsive_control(
			'form_login_note_align',
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
					'{{WRAPPER}} .ec_details_customer_reviews_form_login_note' => 'text-align: {{VALUE}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'form_login_note_background_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Background Color', 'wp-easycart' ),
				'default' => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_customer_reviews_form_login_note' => 'background-color: {{VALUE}}',
				),
			)
		);
		
		$this->add_responsive_control(
			'form_login_note_padding',
			array(
				'label' => esc_attr__( 'Padding', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 15,
					'right' => 15,
					'bottom' => 15,
					'left' => 15,
					'unit' => 'px',
					'isLinked' => true,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_customer_reviews_form_login_note' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'form_login_note_margin',
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
					'{{WRAPPER}} .ec_details_customer_reviews_form_login_note' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);

		$this->add_responsive_control(
			'wpec_crw_form_login_note_border_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Border Color', 'wp-easycart' ),
				'default' => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_customer_reviews_form_login_note' => 'border-color: {{VALUE}} !important;',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'form_login_note_border',
				'default' => array(
					'type' => 'none',
					'top' => 0,
					'right' => 0,
					'bottom' => 0,
					'left' => 0,
					'unit' => 'px',
					'isLinked' => true,
				),
				'selector' => '{{WRAPPER}} .ec_details_customer_reviews_form_login_note',
			)
		);
		
		$this->add_responsive_control(
			'form_login_note_border_radius',
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
					'{{WRAPPER}} .ec_details_customer_reviews_form_login_note' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
		include( EC_PLUGIN_DIRECTORY . '/admin/elementor/wp-easycart-elementor-product-details-customer-reviews-widget.php' );
	}
}
