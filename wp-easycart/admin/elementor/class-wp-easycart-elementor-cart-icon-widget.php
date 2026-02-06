<?php
/**
 * WP EasyCart Cart Icon Widget for Elementor
 *
 * @category Class
 * @package  Wp_Easycart_Elementor_Cart_Icon_Widget
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
 * WP EasyCart Cart Icon Widget for Elementor
 *
 * @category Class
 * @package  Wp_Easycart_Elementor_Cart_Icon_Widget
 * @author   WP EasyCart
 */
class Wp_Easycart_Elementor_Cart_Icon_Widget extends \Elementor\Widget_Base {

	/**
	 * Get product cart icon widget name.
	 */
	public function get_name() {
		return 'wp_easycart_cart_icon';
	}

	/**
	 * Get product cart icon widget title.
	 */
	public function get_title() {
		return esc_attr__( 'WP EasyCart Cart Icon', 'wp-easycart' );
	}

	/**
	 * Get product cart icon widget icon.
	 */
	public function get_icon() {
		return 'eicon-product-add-to-cart';
	}

	/**
	 * Get product cart icon widget categories.
	 */
	public function get_categories() {
		return array( 'wp-easycart-elements' );
	}

	/**
	 * Get product cart icon widget keywords.
	 */
	public function get_keywords() {
		return array( 'products', 'shop', 'wp-easycart' );
	}

	/**
	 * Enqueue product cart icon widget scripts and styles.
	 */
	public function get_script_depends() {
		$scripts = array( 'isotope-pkgd', 'jquery-hoverIntent' );
		if ( ( isset( $_REQUEST['action'] ) && 'elementor' == $_REQUEST['action'] ) || isset( $_REQUEST['elementor-preview'] ) ) {
			$scripts[] = 'wpeasycart_js';
		}
		return $scripts;
	}

	/**
	 * Setup product cart icon widget controls.
	 */
	protected function _register_controls() {
		$this->start_controls_section(
			'section_content_cart_icon',
			array(
				'label' => esc_html__( 'Cart Icon Settings', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'cart_icon',
			array(
				'label' => esc_html__( 'Icon', 'wp-easycart' ),
				'type' => Controls_Manager::ICONS,
				'default' => array(
					'value' => 'fas fa-shopping-cart',
					'library' => 'fa-solid',
				),
			)
		);

		$this->add_control(
			'show_quantity',
			array(
				'label' => esc_html__( 'Show Item Quantity', 'wp-easycart' ),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Show', 'wp-easycart' ),
				'label_off' => esc_html__( 'Hide', 'wp-easycart' ),
				'return_value' => 'yes',
				'default' => 'yes',
			)
		);

		$cart_page_id = get_option( 'ec_option_cartpage' );
		if ( function_exists( 'icl_object_id' ) ) {
			$cart_page_id = icl_object_id( $cart_page_id, 'page', true, ICL_LANGUAGE_CODE );
		}
		$cart_page = get_permalink( $cart_page_id );
		if ( class_exists( "WordPressHTTPS" ) && isset( $_SERVER['HTTPS'] ) ) {
			$https_class = new WordPressHTTPS();
			$cart_page = $https_class->makeUrlHttps( $cart_page );
		}
		$this->add_control(
			'cart_link',
			array(
				'label' => esc_html__( 'Link to Cart Page', 'wp-easycart' ),
				'type' => Controls_Manager::URL,
				'placeholder' => 'https://your-site.com/cart',
				'dynamic' => array(
					'active' => true,
				),
				'default' => array(
					'url' => $cart_page,
				),
			)
		);
		$this->add_responsive_control(
			'alignment',
			array(
				'label' => esc_html__( 'Alignment', 'wp-easycart' ),
				'type' => \Elementor\Controls_Manager::CHOOSE,
				'options' => array(
					'flex-start' => array(
						'title' => esc_html__( 'Left', 'wp-easycart' ),
						'icon' => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_html__( 'Center', 'wp-easycart' ),
						'icon' => 'eicon-text-align-center',
					),
					'flex-end' => array(
						'title' => esc_html__( 'Right', 'wp-easycart' ),
						'icon' => 'eicon-text-align-right',
					),
				),
				'default' => 'flex-end',
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-cart-icon-shortcode-wrapper' => 'display:flex; justify-content: {{VALUE}};',
				),
			)
		);
		$this->end_controls_section();

		$this->start_controls_section(
			'section_style_icon',
			array(
				'label' => esc_html__( 'Icon', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'alignment',
			array(
				'label' => esc_html__( 'Alignment', 'wp-easycart' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => array(
					'flex-start' => array(
						'title' => esc_html__( 'Left', 'wp-easycart' ),
						'icon' => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_html__( 'Center', 'wp-easycart' ),
						'icon' => 'eicon-text-align-center',
					),
					'flex-end' => array(
						'title' => esc_html__( 'Right', 'wp-easycart' ),
						'icon' => 'eicon-text-align-right',
					),
				),
				'default' => 'flex-end',
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-widget-cart-wrapper' => 'justify-content: {{VALUE}};',
				),
			)
		);

		$this->start_controls_tabs( 'icon_colors' );
		$this->start_controls_tab(
			'icon_color_normal',
			array(
				'label' => esc_html__( 'Normal', 'wp-easycart' ),
			)
		);

		$this->add_control(
			'icon_color',
			array(
				'label' => esc_html__( 'Icon Color', 'wp-easycart' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-widget-cart-icon i' => 'color: {{VALUE}};',
					'{{WRAPPER}} .wp-easycart-widget-cart-icon svg' => 'fill: {{VALUE}};',
				),
			)
		);
		$this->end_controls_tab();

		$this->start_controls_tab(
			'icon_color_hover',
			array(
				'label' => esc_html__( 'Hover', 'wp-easycart' ),
			)
		);

		$this->add_control(
			'icon_hover_color',
			array(
				'label' => esc_html__( 'Icon Color', 'wp-easycart' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-widget-cart-wrapper a:hover .wp-easycart-widget-cart-icon i' => 'color: {{VALUE}};',
					'{{WRAPPER}} .wp-easycart-widget-cart-wrapper a:hover .wp-easycart-widget-cart-icon svg' => 'fill: {{VALUE}};',
				),
			)
		);
		
		$this->end_controls_tab();
		$this->end_controls_tabs();

		$this->add_responsive_control(
			'icon_size',
			array(
				'label' => esc_html__( 'Icon Size', 'wp-easycart' ),
				'type' => Controls_Manager::SLIDER,
				'range' => array(
					'px' => array(
						'min' => 10,
						'max' => 100,
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-widget-cart-icon' => 'font-size: {{SIZE}}{{UNIT}};',
				),
				'separator' => 'before',
			)
		);
		$this->add_responsive_control(
			'icon_margin',
			array(
				'label' => esc_html__( 'Icon Margin', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-widget-cart-icon-area' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'separator' => 'before',
			)
		);
		$this->end_controls_section();

		$this->start_controls_section(
			'section_style_quantity',
			array(
				'label' => esc_html__( 'Quantity Badge', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'show_quantity' => 'yes',
				),
			)
		);

		$this->add_control(
			'quantity_color',
			array(
				'label' => esc_html__( 'Text Color', 'wp-easycart' ),
				'type' => Controls_Manager::COLOR,
				'default' => '#ffffff',
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-widget-cart-quantity' => 'color: {{VALUE}};',
				),
			)
		);
		
		$this->add_control(
			'quantity_bg_color',
			array(
				'label' => esc_html__( 'Background Color', 'wp-easycart' ),
				'type' => Controls_Manager::COLOR,
				'default' => '#dd0000',
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-widget-cart-quantity' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'quantity_typography',
				'selector' => '{{WRAPPER}} .wp-easycart-widget-cart-quantity',
			)
		);
		
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'quantity_border',
				'selector' => '{{WRAPPER}} .wp-easycart-widget-cart-quantity',
			)
		);

		$this->add_responsive_control(
			'quantity_border_radius',
			array(
				'label' => esc_html__( 'Border Radius', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-widget-cart-quantity' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'quantity_padding',
			array(
				'label' => esc_html__( 'Padding', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-widget-cart-quantity' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'quantity_margin',
			array(
				'label' => esc_html__( 'Quantity Margin', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-widget-cart-quantity' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'separator' => 'before',
			)
		);
		$this->end_controls_section();
	}

	/**
	 * Render product add to cart widget control output in the editor.
	 */
	protected function render() {
		$atts = $this->get_settings_for_display();
		include( EC_PLUGIN_DIRECTORY . '/admin/elementor/wp-easycart-elementor-cart-icon-widget.php' );
	}
}
