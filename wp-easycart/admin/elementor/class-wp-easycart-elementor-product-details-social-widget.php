<?php
/**
 * WP EasyCart Product Details Social Widget for Elementor
 *
 * @category Class
 * @package  Wp_Easycart_Elementor_Product_Details_Social_Widget
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
use Elementor\Repeater;
use Elementor\Utils;
use Elementor\Wp_Easycart_Controls_Manager;

/**
 * WP EasyCart Product Details Social Widget for Elementor
 *
 * @category Class
 * @package  Wp_Easycart_Elementor_Product_Details_Social_Widget
 * @author   WP EasyCart
 */
class Wp_Easycart_Elementor_Product_Details_Social_Widget extends \Elementor\Widget_Base {

	/**
	 * Get product details social widget name.
	 */
	public function get_name() {
		return 'wp_easycart_product_details_social';
	}

	/**
	 * Get product details social widget title.
	 */
	public function get_title() {
		return esc_attr__( 'WP EasyCart Product Social Icons', 'wp-easycart' );
	}

	/**
	 * Get product details social widget icon.
	 */
	public function get_icon() {
		return 'eicon-social-icons';
	}

	/**
	 * Get product details social widget categories.
	 */
	public function get_categories() {
		return array( 'wp-easycart-elements' );
	}

	/**
	 * Get product details social widget keywords.
	 */
	public function get_keywords() {
		return array( 'social', 'wp-easycart' );
	}

	/**
	 * Enqueue product details social widget scripts and styles.
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
				'label' => esc_attr__( 'Social Icon', 'wp-easycart' ),
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

		$repeater = new Repeater();
		$repeater->add_control(
			'social_title',
			array(
				'label' => esc_attr__( 'Social Title', 'wp-easycart' ),
				'type' => Controls_Manager::TEXT,
				'label_block' => true,
			)
		);

		$repeater->add_control(
			'social_link',
			array(
				'label' => esc_attr__( 'Social Link', 'wp-easycart' ),
				'type' => Controls_Manager::TEXT,
				'label_block' => true,
			)
		);

		$repeater->add_control(
			'social_icon',
			array(
				'label' => esc_attr__( 'Icon', 'wp-easycart' ),
				'type' => Controls_Manager::ICONS,
				'default' => array(
					'value' => 'fas fa-circle',
					'library' => 'fa-solid',
				),
				'recommended' => array(
					'fa-solid' => array(
						'circle',
						'dot-circle',
						'square-full',
					),
					'fa-regular' => array(
						'circle',
						'dot-circle',
						'square-full',
					),
				),
			)
		);

		$repeater->add_control(
			'social_color',
			array(
				'label' => esc_attr__( 'Icon Color', 'wp-easycart' ),
				'type' => Controls_Manager::COLOR,
				'default' => '#aaaaaa',
				'selectors' => array(
					'{{WRAPPER}} {{CURRENT_ITEM}} i' => 'color: {{VALUE}}'
				),
			)
		);

		$repeater->add_control(
			'social_color_hover',
			array(
				'label' => esc_attr__( 'Icon Hover Color', 'wp-easycart' ),
				'type' => Controls_Manager::COLOR,
				'default' => ( get_option( 'ec_option_details_main_color' ) != '' ) ? get_option( 'ec_option_details_main_color' ) : '#333333',
				'selectors' => array(
					'{{WRAPPER}} {{CURRENT_ITEM}}:hover i' => 'color: {{VALUE}}'
				),
			)
		);

		$this->add_control(
			'social_list',
			array(
				'label' => esc_attr__( 'Social List', 'wp-easycart' ),
				'type' => Controls_Manager::REPEATER,
				'fields' => $repeater->get_controls(),
				'default' => array(
					array(
						'social_title' => esc_attr__( 'Facebook', 'wp-easycart' ),
						'social_link' => 'https://www.facebook.com/sharer/sharer.php?p[url]={{prod_link}}&p[images][o]={{prod_image}}&p[title]={{prod_title}}',
						'social_icon' => array(
							'value' => 'fab fa-facebook',
							'library' => 'fa-brands',
						),
						'social_color' => '#AAAAAA',
						'social_color_hover' => '#4c6fa4',
					),
					array(
						'social_title' => esc_attr__( 'X', 'wp-easycart' ),
						'social_link' => 'https://x.com/intent/tweet?original_referer={{prod_link}}&source=tweetbutton&text={{prod_title}}&url={{prod_link}}',
						'social_icon' => array(
							'value' => 'fab fa-x-twitter',
							'library' => 'fa-brands',
						),
						'social_color' => '#AAAAAA',
						'social_color_hover' => '#25d7eb',
					),
					array(
						'social_title' => esc_attr__( 'Email', 'wp-easycart' ),
						'social_link' => 'mailto:?subject={{prod_title}}&body=Link%20for%20Product:%20{{prod_link}}',
						'social_icon' => array(
							'value' => 'far fa-envelope',
							'library' => 'fa-regular',
						),
						'social_color' => '#AAAAAA',
						'social_color_hover' => '#ea5c31',
					),
					array(
						'social_title' => esc_attr__( 'Pinterest', 'wp-easycart' ),
						'social_link' => 'https://pinterest.com/pin/create/button/?media={{prod_image}}&description={{prod_title}}&url={{prod_link}}',
						'social_icon' => array(
							'value' => 'fab fa-pinterest',
							'library' => 'fa-brands',
						),
						'social_color' => '#AAAAAA',
						'social_color_hover' => '#cb1c22',
					),
				),
				'title_field' => '{{{ social_title }}}',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'ec_siw_style_section_container',
			array(
				'label' => esc_attr__( 'Social Icons', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
			)
		);
		
		$this->add_responsive_control(
			'ec_siw_align',
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
					'{{WRAPPER}} .ec_details_social' => 'text-align: {{VALUE}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_siw_icon_size',
			array(
				'label' => esc_attr__( 'Icon Size', 'wp-easycart' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range' => array(
					'px' => array(
						'min' => 1,
						'max' => 100,
					),
				),
				'default' => array(
					'unit' => 'px',
					'size' => 28,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_social_icon_ele i' => 'font-size: {{SIZE}}{{UNIT}};',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_siw_icon_spacing',
			array(
				'label' => esc_attr__( 'Icon Spacing', 'wp-easycart' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range' => array(
					'px' => array(
						'min' => 1,
						'max' => 50,
					),
				),
				'default' => array(
					'unit' => 'px',
					'size' => 5,
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_details_social_icon_ele i' => 'margin-right: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .ec_details_social_icon_ele:last-child i' => 'margin-right: 0px;',
				),
			)
		);

		$this->add_responsive_control(
			'ec_siw_background_color',
			array(
				'type'  => Controls_Manager::COLOR,
				'label' => esc_attr__( 'Background Color', 'wp-easycart' ),
				'default' => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_details_social' => 'background-color: {{VALUE}}',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_siw_padding',
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
					'{{WRAPPER}} .ec_details_social' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);
		
		$this->add_responsive_control(
			'ec_siw_margin',
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
					'{{WRAPPER}} .ec_details_social' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'ec_siw_border',
				'default' => array(
					'type' => 'none',
					'top' => 0,
					'right' => 0,
					'bottom' => 0,
					'left' => 0,
					'unit' => 'px',
					'isLinked' => true,
				),
				'selector' => '{{WRAPPER}} .ec_details_social',
			)
		);
		
		$this->add_responsive_control(
			'ec_siw_border_radius',
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
					'{{WRAPPER}} .ec_details_social' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
		include( EC_PLUGIN_DIRECTORY . '/admin/elementor/wp-easycart-elementor-product-details-social-widget.php' );
	}
}
