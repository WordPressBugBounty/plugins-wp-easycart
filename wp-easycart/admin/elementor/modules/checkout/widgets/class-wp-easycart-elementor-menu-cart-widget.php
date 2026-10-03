<?php
/**
 * Menu Cart ( wp_easycart_menu_cart, 6.0.2 ): a cart icon with the item count ( and the subtotal ) for headers, that opens a
 * mini cart below it or in a side panel ( items, remove, subtotal, View cart and Checkout ), or goes to the cart page.
 *
 * Replaces the Cart Icon ( wp_easycart_cart_icon, retired through wp_easycart_elementor_retired_widgets with a settings map ).
 * The count and items come from the uncached cart state ( ec_ajax_cart_state, window.wpeasycart_refresh_cart_widgets(),
 * event wpeasycart_cart_changed ), so a cached header still shows the shopper's own cart. mini-cart.js draws it.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/trait-wp-easycart-elementor-checkout-controls.php';

if ( ! class_exists( 'WP_EasyCart_Elementor_Menu_Cart_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Widget_Base' ) ) :

	/**
	 * Menu Cart widget.
	 */
	class WP_EasyCart_Elementor_Menu_Cart_Widget extends WP_EasyCart_Elementor_Widget_Base {

		use WP_EasyCart_Elementor_Checkout_Controls;

		/**
		 * Panel category.
		 *
		 * @var string
		 */
		protected static $ec_category = 'checkout';

		/**
		 * Help page.
		 *
		 * @var string
		 */
		protected static $ec_help = 'menu-cart';

		/**
		 * Widget name ( stored in saved pages ).
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_menu_cart';
		}

		/**
		 * Widget title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Menu Cart', 'wp-easycart' );
		}

		/**
		 * Panel icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-cart';
		}

		/**
		 * Search keywords.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array( 'menu cart', 'mini cart', 'cart icon', 'header cart', 'basket', 'woocommerce menu cart' );
		}

		/**
		 * Styles this widget loads.
		 *
		 * @return array
		 */
		public function get_style_depends() {
			return array_merge( parent::get_style_depends(), array( WP_EasyCart_Elementor_Checkout_Module::STYLE ) );
		}

		/**
		 * Scripts this widget loads.
		 *
		 * @return array
		 */
		public function get_script_depends() {
			return array_merge( parent::get_script_depends(), array( WP_EasyCart_Elementor_Checkout_Module::MINI_CART_SCRIPT ) );
		}

		/** Controls. */
		protected function register_controls() {
			$this->start_controls_section(
				'ec_menu_cart_section',
				array(
					'label' => __( 'Menu cart', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->add_control(
				'ec_icon',
				array(
					'label'   => __( 'Icon', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::ICONS,
					'default' => array(
						'value'   => 'fas fa-shopping-cart',
						'library' => 'fa-solid',
					),
				)
			);
			foreach ( array(
				'ec_show_count'    => array( __( 'Item count', 'wp-easycart' ), 'yes', array() ),
				'ec_hide_zero'     => array( __( 'Hide the count at 0', 'wp-easycart' ), '', array( 'ec_show_count' => 'yes' ) ),
				'ec_show_subtotal' => array( __( 'Subtotal', 'wp-easycart' ), '', array() ),
				'ec_hide_empty'    => array( __( 'Hide when the cart is empty', 'wp-easycart' ), '', array() ),
			) as $key => $control ) {
				$args = array(
					'label'        => $control[0],
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'return_value' => 'yes',
					'default'      => $control[1],
				);
				if ( $control[2] ) {
					$args['condition'] = $control[2];
				}
				$this->add_control( $key, $args );
			}
			$this->add_control(
				'ec_click',
				array(
					'label'     => __( 'When clicked', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'default'   => 'dropdown',
					'options'   => array(
						'dropdown' => __( 'Open the mini cart below it', 'wp-easycart' ),
						'panel'    => __( 'Open the mini cart in a side panel', 'wp-easycart' ),
						'link'     => __( 'Go to the cart page', 'wp-easycart' ),
					),
					'separator' => 'before',
				)
			);
			$this->add_control(
				'ec_hover',
				array(
					'label'        => __( 'Open on hover', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'return_value' => 'yes',
					'default'      => '',
					'description'  => __( 'On computers with a mouse. Touch screens and keyboards open it with a tap or Enter.', 'wp-easycart' ),
					'condition'    => array( 'ec_click' => 'dropdown' ),
				)
			);
			$this->add_control(
				'ec_open_on_add',
				array(
					'label'        => __( 'Open when a product is added', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'return_value' => 'yes',
					'default'      => '',
					'description'  => __( 'Shows shoppers what they just added, on pages where products are added without leaving the page.', 'wp-easycart' ),
					'condition'    => array( 'ec_click!' => 'link' ),
				)
			);
			$this->add_control(
				'ec_drop_position',
				array(
					'label'     => __( 'Mini cart position', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'default'   => 'end',
					'options'   => array(
						'end'    => __( 'Lined up with the end of the icon', 'wp-easycart' ),
						'start'  => __( 'Lined up with the start of the icon', 'wp-easycart' ),
						'center' => __( 'Centred under the icon', 'wp-easycart' ),
					),
					'condition' => array( 'ec_click' => 'dropdown' ),
				)
			);
			$this->add_control(
				'ec_panel_side',
				array(
					'label'     => __( 'Panel side', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::CHOOSE,
					'default'   => 'right',
					'options'   => array(
						'left'  => array(
							'title' => __( 'Left', 'wp-easycart' ),
							'icon'  => 'eicon-h-align-left',
						),
						'right' => array(
							'title' => __( 'Right', 'wp-easycart' ),
							'icon'  => 'eicon-h-align-right',
						),
					),
					'condition' => array( 'ec_click' => 'panel' ),
				)
			);
			$this->add_control(
				'ec_link',
				array(
					'label'       => __( 'Link', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::URL,
					'dynamic'     => array( 'active' => true ),
					'placeholder' => __( 'Your cart page', 'wp-easycart' ),
					'description' => __( 'Leave it empty for your cart page.', 'wp-easycart' ),
					'condition'   => array( 'ec_click' => 'link' ),
				)
			);
			$this->add_responsive_control(
				'ec_align',
				array(
					'label'     => __( 'Alignment', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::CHOOSE,
					'options'   => $this->ec_align_options( 'flex' ),
					'selectors' => array( '{{WRAPPER}} .wpec-menu-cart' => 'justify-content: {{VALUE}};' ),
				)
			);
			$this->end_controls_section();

			$this->start_controls_section(
				'ec_mini_cart_section',
				array(
					'label'     => __( 'Mini cart', 'wp-easycart' ),
					'tab'       => \Elementor\Controls_Manager::TAB_CONTENT,
					'condition' => array( 'ec_click!' => 'link' ),
				)
			);
			$this->add_control(
				'ec_title',
				array(
					'label'       => __( 'Title', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'placeholder' => wp_strip_all_tags( WP_EasyCart_Elementor_Checkout_Module::text( 'mini_cart_title', 'Your cart' ) ),
				)
			);
			$this->add_control(
				'ec_show_view_cart',
				array(
					'label'        => __( 'View cart button', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'return_value' => 'yes',
					'default'      => 'yes',
				)
			);
			$this->add_control(
				'ec_view_cart_text',
				array(
					'label'       => __( 'View cart text', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'placeholder' => wp_strip_all_tags( WP_EasyCart_Elementor_Checkout_Module::text( 'view_cart', 'View cart' ) ),
					'condition'   => array( 'ec_show_view_cart' => 'yes' ),
				)
			);
			$this->add_control(
				'ec_show_checkout',
				array(
					'label'        => __( 'Checkout button', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'return_value' => 'yes',
					'default'      => 'yes',
				)
			);
			$this->add_control(
				'ec_checkout_text',
				array(
					'label'       => __( 'Checkout text', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'placeholder' => wp_strip_all_tags( WP_EasyCart_Elementor_Checkout_Module::text( 'cart_checkout', 'Checkout', 'cart' ) ),
					'condition'   => array( 'ec_show_checkout' => 'yes' ),
				)
			);
			$this->add_control(
				'ec_lines_heading',
				array(
					'label'     => __( 'Product lines', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			foreach ( array(
				'ec_show_images'  => array( __( 'Images', 'wp-easycart' ), 'yes', '' ),
				'ec_show_options' => array( __( 'Options', 'wp-easycart' ), 'yes', '' ),
				'ec_show_stepper' => array( __( 'Quantity buttons', 'wp-easycart' ), '', __( 'Lets shoppers change quantities in the mini cart ( − and + ).', 'wp-easycart' ) ),
			) as $key => $control ) {
				$args = array(
					'label'        => $control[0],
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'label_on'     => __( 'Show', 'wp-easycart' ),
					'label_off'    => __( 'Hide', 'wp-easycart' ),
					'return_value' => 'yes',
					'default'      => $control[1],
				);
				if ( '' !== $control[2] ) {
					$args['description'] = $control[2];
				}
				$this->add_control( $key, $args );
			}
			$this->add_control(
				'ec_remove_icon',
				array(
					'label'       => __( 'Remove icon', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::ICONS,
					'skin'        => 'inline',
					'label_block' => false,
					'description' => __( 'Leave it empty for ×.', 'wp-easycart' ),
				)
			);
			$this->add_control(
				'ec_close_icon',
				array(
					'label'       => __( 'Close icon', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::ICONS,
					'skin'        => 'inline',
					'label_block' => false,
				)
			);
			$this->add_control(
				'ec_subtotal_label',
				array(
					'label'       => __( 'Subtotal label', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'placeholder' => wp_strip_all_tags( WP_EasyCart_Elementor_Checkout_Module::text( 'cart_totals_subtotal', 'Cart Subtotal', 'cart_totals' ) ),
					'separator'   => 'before',
				)
			);
			$this->add_control(
				'ec_empty_text',
				array(
					'label'       => __( 'Empty cart text', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'placeholder' => wp_strip_all_tags( WP_EasyCart_Elementor_Checkout_Module::text( 'cart_empty_cart', 'There are no items in your cart.', 'cart' ) ),
				)
			);
			/* 6.0.2: the dropdown only. The side panel opens in the editor as it does on the page ( a panel drawn open in the page was not how it looks ). */
			$this->add_control(
				'ec_preview_open',
				array(
					'label'        => __( 'Show it open in the editor', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'return_value' => 'yes',
					'default'      => '',
					'separator'    => 'before',
					'description'  => __( 'To style it. Visitors open it themselves.', 'wp-easycart' ),
					'condition'    => array( 'ec_click' => 'dropdown' ),
				)
			);
			$this->add_control(
				'ec_panel_editor_note',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => esc_html__( 'To style the side panel, click the cart icon in the preview: it opens as it does on your site and stays open while you change these settings. Close it with × or by clicking beside it.', 'wp-easycart' ),
					'content_classes' => 'elementor-descriptor',
					'separator'       => 'before',
					'condition'       => array( 'ec_click' => 'panel' ),
				)
			);
			$this->end_controls_section();

			$this->ec_register_style_controls();
		}

		/** Style tab. */
		protected function ec_register_style_controls() {
			$this->start_controls_section(
				'icon_style',
				array(
					'label' => __( 'Icon', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->add_responsive_control(
				'icon_size',
				array(
					'label'      => __( 'Size', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px', 'em' ),
					'range'      => array(
						'px' => array(
							'min' => 10,
							'max' => 100,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-menu-cart__icon' => 'font-size: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->start_controls_tabs( 'icon_tabs' );
			$this->start_controls_tab( 'icon_tab_normal', array( 'label' => __( 'Normal', 'wp-easycart' ) ) );
			$this->add_control(
				'icon_color',
				array(
					'label'     => __( 'Color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'global'    => array( 'default' => \Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_TEXT ),
					'selectors' => array( '{{WRAPPER}} .wpec-menu-cart__toggle' => 'color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'toggle_background',
				array(
					'label'     => __( 'Background colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-menu-cart__toggle' => 'background-color: {{VALUE}};' ),
				)
			);
			$this->end_controls_tab();
			$this->start_controls_tab( 'icon_tab_hover', array( 'label' => __( 'Hover', 'wp-easycart' ) ) );
			$hover = '{{WRAPPER}} .wpec-menu-cart__toggle:hover, {{WRAPPER}} .wpec-menu-cart__toggle:focus-visible, {{WRAPPER}} .wpec-menu-cart__toggle[aria-expanded="true"]';
			$this->add_control(
				'icon_hover_color',
				array(
					'label'     => __( 'Color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $hover => 'color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'toggle_hover_background',
				array(
					'label'     => __( 'Background colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $hover => 'background-color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'toggle_hover_border_color',
				array(
					'label'     => __( 'Border colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $hover => 'border-color: {{VALUE}};' ),
				)
			);
			$this->end_controls_tab();
			$this->end_controls_tabs();
			$this->add_responsive_control(
				'icon_gap',
				array(
					'label'      => __( 'Space before the subtotal', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'separator'  => 'before',
					'selectors'  => array( '{{WRAPPER}} .wpec-menu-cart__toggle' => 'gap: {{SIZE}}{{UNIT}};' ),
					'condition'  => array( 'ec_show_subtotal' => 'yes' ),
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Border::get_type(),
				array(
					'name'      => 'toggle_border',
					'selector'  => '{{WRAPPER}} .wpec-menu-cart__toggle',
					'separator' => 'before',
				)
			);
			$this->add_responsive_control(
				'toggle_radius',
				array(
					'label'      => __( 'Border radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', '%', 'em' ),
					'selectors'  => array( '{{WRAPPER}} .wpec-menu-cart__toggle' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->add_responsive_control(
				'toggle_padding',
				array(
					'label'      => __( 'Padding', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em' ),
					'selectors'  => array( '{{WRAPPER}} .wpec-menu-cart__toggle' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->ec_typography( 'toggle_typography', '.wpec-menu-cart__toggle' );
			$this->end_controls_section();

			$this->start_controls_section(
				'count_style',
				array(
					'label'     => __( 'Item count', 'wp-easycart' ),
					'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
					'condition' => array( 'ec_show_count' => 'yes' ),
				)
			);
			$this->add_control(
				'count_position',
				array(
					'label'        => __( 'Position', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SELECT,
					'default'      => 'badge',
					'options'      => array(
						'badge'  => __( 'On the icon', 'wp-easycart' ),
						'inline' => __( 'Beside the icon', 'wp-easycart' ),
					),
					'prefix_class' => 'wpec-menu-cart-count-',
				)
			);
			$this->add_control(
				'count_color',
				array(
					'label'     => __( 'Text color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'default'   => '#ffffff',
					'selectors' => array( '{{WRAPPER}} .wpec-menu-cart__count' => 'color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'count_background',
				array(
					'label'     => __( 'Background color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'global'    => array( 'default' => \Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_ACCENT ),
					'selectors' => array( '{{WRAPPER}} .wpec-menu-cart__count' => 'background-color: {{VALUE}};' ),
				)
			);
			$this->ec_typography( 'count_typography', '.wpec-menu-cart__count' );
			$this->add_group_control(
				\Elementor\Group_Control_Border::get_type(),
				array(
					'name'     => 'count_border',
					'selector' => '{{WRAPPER}} .wpec-menu-cart__count',
				)
			);
			$this->add_responsive_control(
				'count_radius',
				array(
					'label'      => __( 'Border radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', '%', 'em' ),
					'selectors'  => array( '{{WRAPPER}} .wpec-menu-cart__count' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->add_responsive_control(
				'count_padding',
				array(
					'label'      => __( 'Padding', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em' ),
					'selectors'  => array( '{{WRAPPER}} .wpec-menu-cart__count' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->add_responsive_control(
				'count_size',
				array(
					'label'      => __( 'Size', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px', 'em' ),
					'range'      => array(
						'px' => array(
							'min' => 10,
							'max' => 40,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-menu-cart__count' => 'min-width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_responsive_control(
				'count_offset_x',
				array(
					'label'      => __( 'Horizontal offset', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px', 'em' ),
					'range'      => array(
						'px' => array(
							'min' => -30,
							'max' => 30,
						),
					),
					'selectors'  => array( '{{WRAPPER}}.wpec-menu-cart-count-badge .wpec-menu-cart__count, {{WRAPPER}} .wpec-menu-cart__icon > .wpec-menu-cart__count:only-child' => 'inset-inline-end: {{SIZE}}{{UNIT}};' ),
					'condition'  => array( 'count_position' => 'badge' ),
				)
			);
			$this->add_responsive_control(
				'count_offset_y',
				array(
					'label'      => __( 'Vertical offset', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px', 'em' ),
					'range'      => array(
						'px' => array(
							'min' => -30,
							'max' => 30,
						),
					),
					'selectors'  => array( '{{WRAPPER}}.wpec-menu-cart-count-badge .wpec-menu-cart__count, {{WRAPPER}} .wpec-menu-cart__icon > .wpec-menu-cart__count:only-child' => 'top: {{SIZE}}{{UNIT}};' ),
					'condition'  => array( 'count_position' => 'badge' ),
				)
			);
			$this->end_controls_section();

			$this->ec_text_section( 'subtotal', __( 'Subtotal', 'wp-easycart' ), '.wpec-menu-cart__subtotal', array( 'condition' => array( 'ec_show_subtotal' => 'yes' ) ) );

			$this->start_controls_section(
				'panel_style',
				array(
					'label'     => __( 'Mini cart', 'wp-easycart' ),
					'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
					'condition' => array( 'ec_click!' => 'link' ),
				)
			);
			$this->add_responsive_control(
				'panel_width',
				array(
					'label'      => __( 'Width', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px', 'vw' ),
					'range'      => array(
						'px' => array(
							'min' => 220,
							'max' => 700,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-mini-cart__inner' => 'width: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->ec_box_controls( 'panel', '.wpec-mini-cart__inner' );
			$this->add_control(
				'panel_overlay',
				array(
					'label'     => __( 'Page overlay color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-mini-cart__overlay' => 'background-color: {{VALUE}};' ),
					'condition' => array( 'ec_click' => 'panel' ),
				)
			);
			$this->add_control(
				'panel_place_heading',
				array(
					'label'     => __( 'Placement', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
					'condition' => array( 'ec_click' => 'dropdown' ),
				)
			);
			$this->add_responsive_control(
				'panel_offset_y',
				array(
					'label'      => __( 'Distance from the icon', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => -20,
							'max' => 80,
						),
					),
					'selectors'  => array( '{{WRAPPER}}' => '--wpec-mc-offset-y: {{SIZE}}{{UNIT}};' ),
					'condition'  => array( 'ec_click' => 'dropdown' ),
				)
			);
			$this->add_responsive_control(
				'panel_offset_x',
				array(
					'label'      => __( 'Sideways shift', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => -200,
							'max' => 200,
						),
					),
					'selectors'  => array( '{{WRAPPER}}' => '--wpec-mc-offset-x: {{SIZE}}{{UNIT}};' ),
					'condition'  => array( 'ec_click' => 'dropdown' ),
				)
			);
			$this->add_control(
				'panel_z_index',
				array(
					'label'     => __( 'Stacking order ( z-index )', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::NUMBER,
					'min'       => 1,
					'max'       => 999999,
					'selectors' => array( '{{WRAPPER}}' => '--wpec-mc-z: {{VALUE}};' ),
				)
			);
			$this->add_responsive_control(
				'panel_list_height',
				array(
					'label'       => __( 'Product list height', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SLIDER,
					'size_units'  => array( 'vh', 'px' ),
					'range'       => array(
						'vh' => array(
							'min' => 10,
							'max' => 90,
						),
						'px' => array(
							'min' => 100,
							'max' => 900,
						),
					),
					'description' => __( 'Longer carts scroll inside the mini cart.', 'wp-easycart' ),
					'selectors'   => array( '{{WRAPPER}} .wpec-mini-cart__body' => 'max-height: {{SIZE}}{{UNIT}};' ),
					'condition'   => array( 'ec_click' => 'dropdown' ),
				)
			);
			$this->end_controls_section();

			$this->ec_text_section(
				'panel_title',
				__( 'Mini cart title', 'wp-easycart' ),
				'.wpec-mini-cart__title',
				array(
					'typography' => \Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_PRIMARY,
					'color'      => \Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_PRIMARY,
					'condition'  => array( 'ec_click!' => 'link' ),
				)
			);

			$this->start_controls_section(
				'close_style',
				array(
					'label'     => __( 'Close button', 'wp-easycart' ),
					'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
					'condition' => array( 'ec_click!' => 'link' ),
				)
			);
			$this->ec_icon_button_controls( 'close', '.wpec-mini-cart__close' );
			$this->end_controls_section();

			$this->start_controls_section(
				'items_style',
				array(
					'label'     => __( 'Products', 'wp-easycart' ),
					'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
					'condition' => array( 'ec_click!' => 'link' ),
				)
			);
			$this->add_responsive_control(
				'items_image_width',
				array(
					'label'      => __( 'Image width', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 24,
							'max' => 140,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-mini-cart__image' => 'width: {{SIZE}}{{UNIT}}; flex-basis: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_responsive_control(
				'items_image_radius',
				array(
					'label'      => __( 'Image border radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px', '%' ),
					'selectors'  => array( '{{WRAPPER}} .wpec-mini-cart__image img' => 'border-radius: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_control(
				'items_name_heading',
				array(
					'label'     => __( 'Product name', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->ec_text_controls(
				'items_name',
				'.wpec-mini-cart__name',
				array(
					'color' => \Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_PRIMARY,
					'hover' => true,
				)
			);
			$this->add_control(
				'items_meta_heading',
				array(
					'label'     => __( 'Options, quantity and price', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->ec_text_controls( 'items_meta', '.wpec-mini-cart__options, .wpec-mini-cart__meta, .wpec-mini-cart__total', array( 'color' => '' ) );
			$this->add_control(
				'items_remove_color',
				array(
					'label'     => __( 'Remove color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'separator' => 'before',
					'selectors' => array( '{{WRAPPER}} .wpec-mini-cart__remove' => 'color: {{VALUE}};' ),
				)
			);
			$this->ec_icon_button_controls( 'items_remove', '.wpec-mini-cart__remove', false );
			$this->add_control(
				'items_rows_heading',
				array(
					'label'     => __( 'Rows', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->ec_divider_control( 'items_divider', '.wpec-mini-cart__item', 'bottom' );
			$this->ec_divider_style_controls( 'items_divider', '.wpec-mini-cart__item', 'bottom' );
			$this->add_responsive_control(
				'items_padding',
				array(
					'label'      => __( 'Row spacing', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 40,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-mini-cart__item' => 'padding-top: {{SIZE}}{{UNIT}}; padding-bottom: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_responsive_control(
				'items_gap',
				array(
					'label'      => __( 'Space between image and text', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 40,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-mini-cart__item' => 'gap: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_control(
				'items_qty_heading',
				array(
					'label'     => __( 'Quantity buttons', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
					'condition' => array( 'ec_show_stepper' => 'yes' ),
				)
			);
			$this->add_control(
				'items_qty_color',
				array(
					'label'     => __( 'Text colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-mini-cart__qty, {{WRAPPER}} .wpec-mini-cart__qty button, {{WRAPPER}} .wpec-mini-cart__qty input' => 'color: {{VALUE}};' ),
					'condition' => array( 'ec_show_stepper' => 'yes' ),
				)
			);
			$this->add_control(
				'items_qty_border',
				array(
					'label'     => __( 'Border colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-mini-cart__qty' => 'border-color: {{VALUE}};' ),
					'condition' => array( 'ec_show_stepper' => 'yes' ),
				)
			);
			$this->add_control(
				'items_qty_background',
				array(
					'label'     => __( 'Background colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-mini-cart__qty' => 'background-color: {{VALUE}};' ),
					'condition' => array( 'ec_show_stepper' => 'yes' ),
				)
			);
			$this->add_responsive_control(
				'items_qty_radius',
				array(
					'label'      => __( 'Border radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 30,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-mini-cart__qty' => 'border-radius: {{SIZE}}{{UNIT}};' ),
					'condition'  => array( 'ec_show_stepper' => 'yes' ),
				)
			);
			$this->end_controls_section();

			$this->ec_text_section(
				'panel_empty',
				__( 'Empty cart text', 'wp-easycart' ),
				'.wpec-mini-cart__empty',
				array(
					'color'     => '',
					'align'     => true,
					'condition' => array( 'ec_click!' => 'link' ),
				)
			);

			$this->start_controls_section(
				'panel_subtotal_style',
				array(
					'label'     => __( 'Mini cart subtotal', 'wp-easycart' ),
					'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
					'condition' => array( 'ec_click!' => 'link' ),
				)
			);
			$this->ec_text_controls(
				'panel_subtotal',
				'.wpec-mini-cart__subtotal',
				array(
					'typography' => \Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_TEXT,
					'color'      => \Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_TEXT,
				)
			);
			$this->add_control(
				'panel_subtotal_label_heading',
				array(
					'label'     => __( 'Label', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->ec_text_controls(
				'panel_subtotal_label',
				'.wpec-mini-cart__subtotal-label',
				array(
					'color'      => '',
					'typography' => null,
				)
			);
			$this->add_control(
				'panel_subtotal_value_heading',
				array(
					'label'     => __( 'Amount', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->ec_text_controls(
				'panel_subtotal_value',
				'.wpec-mini-cart__subtotal-value',
				array(
					'color'      => '',
					'typography' => null,
				)
			);
			$this->end_controls_section();

			$this->start_controls_section(
				'buttons_layout_style',
				array(
					'label'     => __( 'Mini cart buttons', 'wp-easycart' ),
					'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
					'condition' => array( 'ec_click!' => 'link' ),
				)
			);
			$this->add_responsive_control(
				'buttons_layout',
				array(
					'label'                => __( 'Layout', 'wp-easycart' ),
					'type'                 => \Elementor\Controls_Manager::SELECT,
					'default'              => '',
					'options'              => array(
						''        => __( 'Side by side', 'wp-easycart' ),
						'stacked' => __( 'Stacked', 'wp-easycart' ),
					),
					'selectors_dictionary' => array(
						'stacked' => 'flex-direction: column;',
					),
					'selectors'            => array( '{{WRAPPER}} .wpec-mini-cart__buttons' => '{{VALUE}}' ),
				)
			);
			$this->add_responsive_control(
				'buttons_gap',
				array(
					'label'      => __( 'Space between buttons', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 40,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-mini-cart__buttons' => 'gap: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_control(
				'buttons_fill',
				array(
					'label'        => __( 'Buttons fill the width', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'return_value' => 'yes',
					'default'      => 'yes',
				)
			);
			$this->add_responsive_control(
				'buttons_align',
				array(
					'label'     => __( 'Alignment', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::CHOOSE,
					'options'   => $this->ec_align_options( 'flex' ),
					'selectors' => array( '{{WRAPPER}} .wpec-mini-cart__buttons' => 'justify-content: {{VALUE}}; align-items: {{VALUE}};' ),
					'condition' => array( 'buttons_fill!' => 'yes' ),
				)
			);
			$this->end_controls_section();

			$this->ec_button_section(
				'view_cart_button',
				__( 'View cart button', 'wp-easycart' ),
				'.wpec-mini-cart__button--cart',
				array(
					'background' => '',
					'condition'  => array(
						'ec_click!'         => 'link',
						'ec_show_view_cart' => 'yes',
					),
				)
			);
			$this->ec_button_section(
				'checkout_button',
				__( 'Checkout button', 'wp-easycart' ),
				'.wpec-mini-cart__button--checkout',
				array(
					'condition' => array(
						'ec_click!'        => 'link',
						'ec_show_checkout' => 'yes',
					),
				)
			);
		}

		/**
		 * The cart the widget is drawn with: a sample in the editor, else always empty. The Menu Cart sits in headers on every
		 * page, which page caches keep and serve to everyone, so the visitor's own cart is never part of the page: ec-store.js
		 * asks for it once the page is ready ( data-wpec-cart-widget, ec_ajax_cart_state ) and mini-cart.js fills it in.
		 *
		 * @return array count, subtotal_display, items.
		 */
		protected function ec_state() {
			if ( $this->ec_is_editor() ) {
				$lines = WP_EasyCart_Elementor_Checkout_Module::sample_lines( 2 );
				$state = array(
					'count'            => 0,
					'subtotal_display' => '',
					'items'            => array(),
				);
				$total = 0;
				foreach ( $lines as $n => $line ) {
					$state['count']  += $line['quantity'];
					$total           += $line['amount'];
					$state['items'][] = array(
						'cartitem_id'   => $n + 1,
						'title'         => $line['title'],
						'quantity'      => $line['quantity'],
						'price_display' => $line['price'],
						'total_display' => $line['total'],
						'options'       => $line['options'],
						'image'         => $line['image'],
						'link'          => '#',
						'removable'     => true,
					);
				}
				$state['subtotal_display'] = WP_EasyCart_Elementor_Checkout_Module::price( $total );
				return $state;
			}
			return array(
				'count'            => 0,
				'subtotal_display' => '',
				'items'            => array(),
			);
		}

		/**
		 * The toggle's accessible name ( "Cart, 3 items" ).
		 *
		 * @param int $count Items.
		 * @return string
		 */
		protected function ec_label( $count ) {
			return str_replace( '[count]', (string) (int) $count, wp_strip_all_tags( WP_EasyCart_Elementor_Checkout_Module::text( 'cart_button_label', 'Cart, [count] items' ) ) );
		}

		/** Draw. */
		protected function render() {
			$settings = $this->get_settings_for_display();
			$click    = ( isset( $settings['ec_click'] ) && in_array( $settings['ec_click'], array( 'dropdown', 'panel', 'link' ), true ) ) ? $settings['ec_click'] : 'dropdown';
			$state    = $this->ec_state();
			$count    = isset( $state['count'] ) ? (int) $state['count'] : 0;
			$panel_id = 'wpec-mini-cart-' . $this->get_id();
			$editor   = $this->ec_is_editor();
			$open     = $editor && 'dropdown' === $click && $this->ec_on( $settings, 'ec_preview_open', false );

			$href = WP_EasyCart_Elementor_Checkout_Module::cart_url();
			$link = array();
			if ( 'link' === $click && ! empty( $settings['ec_link']['url'] ) ) {
				$link = $settings['ec_link'];
				$href = (string) $link['url'];
			}

			$classes = array( 'wpec-el', 'wpec-menu-cart', 'wpec-menu-cart--' . $click );
			if ( $count <= 0 ) {
				$classes[] = 'wpec-menu-cart--empty';
			}
			if ( $this->ec_on( $settings, 'ec_hide_empty', false ) && ! $editor ) {
				$classes[] = 'wpec-menu-cart--hide-empty';
			}
			if ( $this->ec_on( $settings, 'ec_hide_zero', false ) ) {
				$classes[] = 'wpec-menu-cart--hide-zero';
			}
			if ( 'panel' === $click ) {
				$classes[] = 'wpec-menu-cart--side-' . ( ( isset( $settings['ec_panel_side'] ) && 'left' === $settings['ec_panel_side'] ) ? 'left' : 'right' );
			}
			if ( 'dropdown' === $click ) {
				$position  = ( isset( $settings['ec_drop_position'] ) && in_array( $settings['ec_drop_position'], array( 'start', 'center' ), true ) ) ? $settings['ec_drop_position'] : 'end';
				$classes[] = 'wpec-menu-cart--drop-' . $position;
			}
			foreach ( array(
				'ec_show_images'  => 'no-images',
				'ec_show_options' => 'no-options',
				'buttons_fill'    => 'buttons-auto',
			) as $key => $class ) {
				if ( ! $this->ec_on( $settings, $key ) ) {
					$classes[] = 'wpec-menu-cart--' . $class;
				}
			}
			if ( $open ) {
				$classes[] = 'wpec-menu-cart--open';
			}

			$data = ' data-wpec-cart-widget="menu-cart" data-wpec-menu-cart="' . esc_attr( $click ) . '" data-wpec-hover="' . esc_attr( $this->ec_on( $settings, 'ec_hover', false ) ? '1' : '0' ) . '" data-wpec-label="' . esc_attr( wp_strip_all_tags( WP_EasyCart_Elementor_Checkout_Module::text( 'cart_button_label', 'Cart, [count] items' ) ) ) . '"';
			if ( 'link' !== $click && $this->ec_on( $settings, 'ec_open_on_add', false ) ) {
				$data .= ' data-wpec-open-on-add="1"';
			}
			if ( 'link' !== $click && $this->ec_on( $settings, 'ec_show_stepper', false ) ) {
				$data .= ' data-wpec-stepper="1"';
			}
			echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '"' . $data . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts above.

			$toggle = '<a class="wpec-menu-cart__toggle" href="' . esc_url( $href ) . '" aria-label="' . esc_attr( $this->ec_label( $count ) ) . '"';
			if ( 'link' === $click ) {
				$toggle .= ( ! empty( $link['is_external'] ) ? ' target="_blank"' : '' ) . ( ! empty( $link['nofollow'] ) ? ' rel="nofollow"' : '' );
			} else {
				$toggle .= ' role="button" aria-haspopup="dialog" aria-controls="' . esc_attr( $panel_id ) . '" aria-expanded="' . ( $open ? 'true' : 'false' ) . '"';
			}
			$toggle .= '>';
			echo $toggle; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts above.
			echo '<span class="wpec-menu-cart__icon" aria-hidden="true">';
			if ( ! empty( $settings['ec_icon']['value'] ) && class_exists( '\Elementor\Icons_Manager' ) ) {
				\Elementor\Icons_Manager::render_icon( $settings['ec_icon'], array( 'aria-hidden' => 'true' ) );
			}
			if ( $this->ec_on( $settings, 'ec_show_count' ) ) {
				echo '<span class="wpec-menu-cart__count" data-wpec-count>' . esc_html( (string) $count ) . '</span>';
			}
			echo '</span>';
			if ( $this->ec_on( $settings, 'ec_show_subtotal', false ) ) {
				echo '<span class="wpec-menu-cart__subtotal" data-wpec-subtotal>' . esc_html( isset( $state['subtotal_display'] ) ? (string) $state['subtotal_display'] : '' ) . '</span>';
			}
			echo '</a>';

			if ( 'link' !== $click ) {
				$this->ec_render_panel( $settings, $state, $panel_id, $click, $open );
			}
			echo '</div>';
		}

		/**
		 * An ICONS control's markup, or '' when none is chosen.
		 *
		 * @param array  $settings Settings.
		 * @param string $key      Control.
		 * @return string
		 */
		protected function ec_icon_html( $settings, $key ) {
			if ( empty( $settings[ $key ]['value'] ) || ! class_exists( '\Elementor\Icons_Manager' ) ) {
				return '';
			}
			ob_start();
			\Elementor\Icons_Manager::render_icon( $settings[ $key ], array( 'aria-hidden' => 'true' ) );
			return (string) ob_get_clean();
		}

		/**
		 * The mini cart panel.
		 *
		 * @param array  $settings Settings.
		 * @param array  $state    Cart state.
		 * @param string $panel_id Panel id.
		 * @param string $click    dropdown | panel.
		 * @param bool   $open     Drawn open ( editor ).
		 */
		protected function ec_render_panel( $settings, $state, $panel_id, $click, $open ) {
			$title    = ( isset( $settings['ec_title'] ) && '' !== trim( (string) $settings['ec_title'] ) ) ? (string) $settings['ec_title'] : wp_strip_all_tags( WP_EasyCart_Elementor_Checkout_Module::text( 'mini_cart_title', 'Your cart' ) );
			$view     = ( isset( $settings['ec_view_cart_text'] ) && '' !== trim( (string) $settings['ec_view_cart_text'] ) ) ? (string) $settings['ec_view_cart_text'] : wp_strip_all_tags( WP_EasyCart_Elementor_Checkout_Module::text( 'view_cart', 'View cart' ) );
			$checkout = ( isset( $settings['ec_checkout_text'] ) && '' !== trim( (string) $settings['ec_checkout_text'] ) ) ? (string) $settings['ec_checkout_text'] : wp_strip_all_tags( WP_EasyCart_Elementor_Checkout_Module::text( 'cart_checkout', 'Checkout', 'cart' ) );
			$subtotal = ( isset( $settings['ec_subtotal_label'] ) && '' !== trim( (string) $settings['ec_subtotal_label'] ) ) ? (string) $settings['ec_subtotal_label'] : wp_strip_all_tags( WP_EasyCart_Elementor_Checkout_Module::text( 'cart_totals_subtotal', 'Cart Subtotal', 'cart_totals' ) );
			$empty    = ( isset( $settings['ec_empty_text'] ) && '' !== trim( (string) $settings['ec_empty_text'] ) ) ? (string) $settings['ec_empty_text'] : wp_strip_all_tags( WP_EasyCart_Elementor_Checkout_Module::text( 'cart_empty_cart', 'There are no items in your cart.', 'cart' ) );
			$items    = ( isset( $state['items'] ) && is_array( $state['items'] ) ) ? $state['items'] : array();
			$modal    = ( 'panel' === $click );
			$close    = $this->ec_icon_html( $settings, 'ec_close_icon' );
			$remove   = $this->ec_icon_html( $settings, 'ec_remove_icon' );

			echo '<div class="wpec-mini-cart" id="' . esc_attr( $panel_id ) . '" role="dialog" aria-modal="' . ( ( $modal && ! $open ) ? 'true' : 'false' ) . '" aria-labelledby="' . esc_attr( $panel_id ) . '-title"' . ( $open ? '' : ' hidden' ) . '>';
			if ( $modal ) {
				echo '<div class="wpec-mini-cart__overlay" data-wpec-close></div>';
			}
			echo '<div class="wpec-mini-cart__inner" tabindex="-1">';
			if ( '' !== $remove ) {
				/* mini-cart.js copies this into every line it draws ( the chosen remove icon ). */
				echo '<template class="wpec-mini-cart__remove-icon">' . $remove . '</template>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor's own icon markup.
			}
			echo '<div class="wpec-mini-cart__header"><span class="wpec-mini-cart__title" id="' . esc_attr( $panel_id ) . '-title">' . esc_html( $title ) . '</span>';
			echo '<button type="button" class="wpec-mini-cart__close" data-wpec-close aria-label="' . esc_attr( wp_strip_all_tags( WP_EasyCart_Elementor_Checkout_Module::text( 'close', 'Close' ) ) ) . '"><span aria-hidden="true">' . ( '' !== $close ? $close : '&times;' ) . '</span></button></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor's own icon markup.
			echo '<div class="wpec-mini-cart__body"><ul class="wpec-mini-cart__items" data-wpec-items>';
			foreach ( $items as $item ) {
				$this->ec_render_row( $item, $remove, $this->ec_on( $settings, 'ec_show_stepper', false ) );
			}
			echo '</ul><p class="wpec-mini-cart__empty"' . ( $items ? ' hidden' : '' ) . '>' . esc_html( $empty ) . '</p></div>';
			echo '<div class="wpec-mini-cart__footer"' . ( $items ? '' : ' hidden' ) . '><div class="wpec-mini-cart__subtotal"><span class="wpec-mini-cart__subtotal-label">' . esc_html( $subtotal ) . '</span><span class="wpec-mini-cart__subtotal-value" data-wpec-subtotal>' . esc_html( isset( $state['subtotal_display'] ) ? (string) $state['subtotal_display'] : '' ) . '</span></div>';
			echo '<div class="wpec-mini-cart__buttons">';
			if ( $this->ec_on( $settings, 'ec_show_view_cart' ) ) {
				echo '<a class="wpec-mini-cart__button wpec-mini-cart__button--cart" href="' . esc_url( WP_EasyCart_Elementor_Checkout_Module::cart_url() ) . '">' . esc_html( $view ) . '</a>';
			}
			if ( $this->ec_on( $settings, 'ec_show_checkout' ) ) {
				echo '<a class="wpec-mini-cart__button wpec-mini-cart__button--checkout" href="' . esc_url( WP_EasyCart_Elementor_Checkout_Module::checkout_url() ) . '">' . esc_html( $checkout ) . '</a>';
			}
			echo '</div></div>';
			echo '<div class="wpec-mini-cart__status screen-reader-text" role="status" aria-live="polite"></div>';
			echo '</div></div>';
		}

		/**
		 * One cart line ( mini-cart.js draws the same markup ).
		 *
		 * @param array  $item    Cart state line.
		 * @param string $remove  The chosen remove icon's markup ( '' for × ).
		 * @param bool   $stepper Quantity buttons ( the editor's sample ).
		 */
		protected function ec_render_row( $item, $remove = '', $stepper = false ) {
			$title    = isset( $item['title'] ) ? (string) $item['title'] : '';
			$link     = ( isset( $item['link'] ) && '' !== $item['link'] ) ? (string) $item['link'] : '#';
			$quantity = isset( $item['quantity'] ) ? (int) $item['quantity'] : 0;
			echo '<li class="wpec-mini-cart__item" data-cartitem="' . esc_attr( isset( $item['cartitem_id'] ) ? (string) (int) $item['cartitem_id'] : '0' ) . '">';
			echo '<a class="wpec-mini-cart__image" href="' . esc_url( $link ) . '" tabindex="-1" aria-hidden="true">' . ( ! empty( $item['image'] ) ? '<img src="' . esc_url( $item['image'] ) . '" alt="" loading="lazy" />' : '' ) . '</a>';
			echo '<div class="wpec-mini-cart__details"><a class="wpec-mini-cart__name" href="' . esc_url( $link ) . '">' . esc_html( $title ) . '</a>';
			if ( ! empty( $item['options'] ) ) {
				echo '<span class="wpec-mini-cart__options">' . esc_html( (string) $item['options'] ) . '</span>';
			}
			if ( $stepper ) {
				echo '<span class="wpec-mini-cart__meta"><span class="wpec-mini-cart__price">' . esc_html( isset( $item['price_display'] ) ? (string) $item['price_display'] : '' ) . '</span></span>';
				echo '<div class="wpec-mini-cart__qty"><button type="button" tabindex="-1" aria-hidden="true">&minus;</button><input type="number" value="' . esc_attr( (string) $quantity ) . '" readonly tabindex="-1" aria-hidden="true" /><button type="button" tabindex="-1" aria-hidden="true">+</button></div></div>';
			} else {
				echo '<span class="wpec-mini-cart__meta"><span class="wpec-mini-cart__quantity">' . esc_html( (string) $quantity ) . ' &times;</span> <span class="wpec-mini-cart__price">' . esc_html( isset( $item['price_display'] ) ? (string) $item['price_display'] : '' ) . '</span></span></div>';
			}
			echo '<span class="wpec-mini-cart__total">' . esc_html( isset( $item['total_display'] ) ? (string) $item['total_display'] : '' ) . '</span>';
			if ( ! isset( $item['removable'] ) || $item['removable'] ) {
				echo '<button type="button" class="wpec-mini-cart__remove" data-wpec-remove aria-label="' . esc_attr( str_replace( '[title]', $title, wp_strip_all_tags( WP_EasyCart_Elementor_Checkout_Module::text( 'remove_item', 'Remove [title]' ) ) ) ) . '"><span aria-hidden="true">' . ( '' !== $remove ? $remove : '&times;' ) . '</span></button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor's own icon markup.
			}
			echo '</li>';
		}
	}

endif;
