<?php
/**
 * WP EasyCart Product Details Images Widget for Elementor
 *
 * @category Class
 * @package  Wp_Easycart_Elementor_Product_Details_Images_Widget
 * @author   WP EasyCart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Elementor\Controls_Manager;
use Elementor\Scheme_Color;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Image_Size;
use Elementor\Utils;
use Elementor\Wp_Easycart_Controls_Manager;

/**
 * WP EasyCart Product Details Images Widget for Elementor
 *
 * @category Class
 * @package  Wp_Easycart_Elementor_Product_Details_Images_Widget
 * @author   WP EasyCart
 */
class Wp_Easycart_Elementor_Product_Details_Images_Widget extends \Elementor\Widget_Base {

	/**
	 * Get product details images widget name.
	 */
	public function get_name() {
		return 'wp_easycart_product_details_images';
	}

	/**
	 * Get product details images widget title.
	 */
	public function get_title() {
		return esc_attr__( 'WP EasyCart Product Images', 'wp-easycart' );
	}

	/**
	 * Get product details images widget icon.
	 */
	public function get_icon() {
		return 'eicon-image-box';
	}

	/**
	 * Get product details images widget categories.
	 */
	public function get_categories() {
		return array( 'wp-easycart-elements' );
	}

	/**
	 * Get product details images widget keywords.
	 */
	public function get_keywords() {
		return array( 'images', 'wp-easycart' );
	}

	/**
	 * Enqueue product details images widget scripts and styles.
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
				'label' => esc_attr__( 'Images', 'wp-easycart' ),
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
			'image_hover',
			array(
				'type'  => Controls_Manager::SWITCHER,
				'label' => esc_attr__( 'Enable Image Magnification', 'wp-easycart' ),
				'default'   => get_option( 'ec_option_show_magnification' ),
			)
		);

		$this->add_control(
			'lightbox',
			array(
				'type'  => Controls_Manager::SWITCHER,
				'label' => esc_attr__( 'Enable Image Lightbox', 'wp-easycart' ),
				'default'   => get_option( 'ec_option_show_large_popup' ),
			)
		);

		$this->add_control(
			'thumbnails',
			array(
				'type'  => Controls_Manager::SWITCHER,
				'label' => esc_attr__( 'Display Image Thumbnails', 'wp-easycart' ),
				'default' => true,
			)
		);

		$this->add_responsive_control(
			'thumbnails_position',
			array(
				'label'     => esc_attr__( 'Thumbnail Position', 'wp-easycart' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'column',
				'options'   => array(
					'column' => esc_attr__( 'Bottom', 'wp-easycart' ),
					'column-reverse' => esc_attr__( 'Top', 'wp-easycart' ),
					'row-reverse' => esc_attr__( 'Left', 'wp-easycart' ),
					'row' => esc_attr__( 'Right', 'wp-easycart' ),
				),
				'condition' => array(
					'thumbnails' => 'yes',
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_images' => 'display:flex; flex-direction:{{VALUE}}; align-items:flex-start;',
				),
			)
		);
		
		$this->add_responsive_control(
			'thumbnails_spacing_row',
			array(
				'label' => esc_attr__( 'Thumbnail Spacing', 'wp-easycart' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array( '%' ),
				'range' => array(
					'%' => array(
						'min' => 1,
						'max' => 100,
					),
					'px' => array(
						'min' => 0,
						'max' => 100,
					),
				),
				'default' => array(
					'unit' => 'px',
					'size' => 5,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_thumbnails' => 'flex-direction:column; column-gap:{{SIZE}}{{UNIT}}; align-content-center;',
					'{{WRAPPER}} .ec_details_thumbnail' => 'height:20%; width:100%;',
				),
				'condition' => array(
					'thumbnails_position' => array( 'row', 'row-reverse' ),
				),
			)
		);
		
		$this->add_responsive_control(
			'thumbnails_spacing_column',
			array(
				'label' => esc_attr__( 'Thumbnail Spacing', 'wp-easycart' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array( '%' ),
				'range' => array(
					'%' => array(
						'min' => 1,
						'max' => 100,
					),
					'px' => array(
						'min' => 0,
						'max' => 100,
					),
				),
				'default' => array(
					'unit' => 'px',
					'size' => 5,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_thumbnails' => 'flex-direction:row; column-gap:{{SIZE}}{{UNIT}}; width:100%;',
					'{{WRAPPER}} .ec_details_thumbnail' => 'height:100%; width:20%;',
				),
				'condition' => array(
					'thumbnails_position' => array( 'column', 'column-reverse' ),
				),
			)
		);
		
		$this->add_responsive_control(
			'thumbnail_image_width',
			array(
				'label' => esc_attr__( 'Thumbnail Width', 'wp-easycart' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array( '%' ),
				'range' => array(
					'%' => array(
						'min' => 1,
						'max' => 100,
					),
					'px' => array(
						'min' => 5,
						'max' => 800,
					),
				),
				'default' => array(
					'unit' => 'px',
					'size' => 55,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_main_image' => 'width:100%;',
					'{{WRAPPER}} .ec_details_thumbnails ~ .ec_details_main_image' => 'width:calc( 100% - {{SIZE}}{{UNIT}} );',
					'{{WRAPPER}} .ec_details_thumbnails' => 'width:{{SIZE}}{{UNIT}};',
				),
				'condition' => array(
					'thumbnails_position' => array( 'row', 'row-reverse' ),
				),
			)
		);
		
		$this->add_responsive_control(
			'thumbnail_image_width_row',
			array(
				'label' => esc_attr__( 'Thumbnail Width', 'wp-easycart' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array( '%' ),
				'range' => array(
					'%' => array(
						'min' => 1,
						'max' => 100,
					),
					'px' => array(
						'min' => 5,
						'max' => 800,
					),
				),
				'default' => array(
					'unit' => 'px',
					'size' => 55,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_main_image' => 'width:100%;',
					'{{WRAPPER}} .ec_details_thumbnail' => 'width:{{SIZE}}{{UNIT}};',
				),
				'condition' => array(
					'thumbnails_position' => array( 'column', 'column-reverse' ),
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'ec_imw_style_section_images',
			array(
				'label' => esc_attr__( 'Images', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
			)
		);
		
		$this->add_group_control(
			Group_Control_Image_Size::get_type(),
			array(
				'name' => 'ec_imw_image_size',
				'label' => esc_attr__( 'Main Size', 'wp-easycart' ),
				'include' => [],
				'default' => 'medium_large',
			)
		);
		
		$this->add_group_control(
			Group_Control_Image_Size::get_type(),
			array(
				'name' => 'ec_imw_thumb_size',
				'label' => esc_attr__( 'Thumb Size', 'wp-easycart' ),
				'include' => [],
				'default' => 'small',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'ec_imw_style_section_container',
			array(
				'label' => esc_attr__( 'Container', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'ec_imw_background_color',
				'types' => array( 'classic', 'gradient' ),
				'label' => esc_attr__( 'Background Color', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_details_images',
			)
		);
		
		$this->add_responsive_control(
			'ec_imw_padding',
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
					'{{WRAPPER}} .ec_details_images' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_imw_margin',
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
					'{{WRAPPER}} .ec_details_images' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'ec_imw_border',
				'default' => array(
					'type' => 'none',
					'top' => 0,
					'right' => 0,
					'bottom' => 0,
					'left' => 0,
					'unit' => 'px',
					'isLinked' => true,
				),
				'selector' => '{{WRAPPER}} .ec_details_images',
			)
		);
		
		$this->add_responsive_control(
			'ec_imw_border_radius',
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
					'{{WRAPPER}} .ec_details_images' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'ec_imw_style_section_main_image',
			array(
				'label' => esc_attr__( 'Main Image', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'ec_imw_main_background_color',
				'types' => array( 'classic', 'gradient' ),
				'label' => esc_attr__( 'Background Color', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_details_main_image',
			)
		);
		
		$this->add_responsive_control(
			'ec_imw_main_padding',
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
					'{{WRAPPER}} .ec_details_main_image' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_imw_main_margin',
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
					'{{WRAPPER}} .ec_details_main_image' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'ec_imw_main_border',
				'default' => array(
					'type' => 'sold',
					'color' => '#cccccc',
					'top' => 1,
					'right' => 1,
					'bottom' => 1,
					'left' => 1,
					'unit' => 'px',
					'isLinked' => true,
				),
				'selector' => '{{WRAPPER}} .ec_details_main_image',
			)
		);
		
		$this->add_responsive_control(
			'ec_imw_main_border_radius',
			array(
				'label' => esc_attr__( 'Border Radius', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 6,
					'right' => 6,
					'bottom' => 6,
					'left' => 6,
					'unit' => 'px',
					'isLinked' => true,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_main_image' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'ec_imw_style_section_thumbnails',
			array(
				'label' => esc_attr__( 'Thumbnail Group', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'ec_imw_thumbs_background_color',
				'types' => array( 'classic', 'gradient' ),
				'label' => esc_attr__( 'Background Color', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_details_thumbnails',
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'ec_imw_thumbs_border',
				'default' => array(
					'type' => 'none',
					'top' => 0,
					'right' => 0,
					'bottom' => 0,
					'left' => 0,
					'unit' => 'px',
					'isLinked' => true,
				),
				'selector' => '{{WRAPPER}} .ec_details_thumbnails',
			)
		);
		
		$this->add_responsive_control(
			'ec_imw_thumbs_border_radius',
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
					'{{WRAPPER}} .ec_details_thumbnails' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_imw_thumbs_padding',
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
					'{{WRAPPER}} .ec_details_thumbnails' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_imw_thumbs_margin',
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
					'{{WRAPPER}} .ec_details_thumbnails' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'ec_imw_style_section_thumbnail',
			array(
				'label' => esc_attr__( 'Thumbnail Item', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'ec_imw_thumb_background_color',
				'types' => array( 'classic', 'gradient' ),
				'label' => esc_attr__( 'Background Color', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_details_thumbnail',
			)
		);
		
		$this->add_responsive_control(
			'ec_imw_thumb_padding',
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
					'{{WRAPPER}} .ec_details_thumbnail' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'ec_imw_thumb_border',
				'default' => array(
					'type' => 'none',
					'top' => 0,
					'right' => 0,
					'bottom' => 0,
					'left' => 0,
					'unit' => 'px',
					'isLinked' => true,
				),
				'selector' => '{{WRAPPER}} .ec_details_thumbnail',
			)
		);
		
		$this->add_responsive_control(
			'ec_imw_thumb_border_radius',
			array(
				'label' => esc_attr__( 'Border Radius', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default' => array(
					'top' => 6,
					'right' => 6,
					'bottom' => 6,
					'left' => 6,
					'unit' => 'px',
					'isLinked' => true,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_thumbnail' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
		include( EC_PLUGIN_DIRECTORY . '/admin/elementor/wp-easycart-elementor-product-details-images-widget.php' );
	}
}
