<?php
/**
 * Elementor widget "Add to Cart" ( wp_easycart_add_to_cart, 6.0.2 ).
 *
 * Every product type through EasyCart's own add to cart template ( options of every kind, quantity grids, gift cards,
 * donations, subscriptions, inquiries, DecoNetwork, backorders, login for pricing, catalog mode, pickup locations ), with
 * the widget's own area: quantity box or number box, button text and icon, an optional Buy now button, and what happens
 * after adding ( a confirmation on the page, the side cart, or the cart ). Replaces wp_easycart_product_addtocart.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/trait-wp-easycart-product-buy-controls.php';

if ( ! class_exists( 'WP_EasyCart_Elementor_Add_To_Cart_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Widget_Base' ) ) :

	/**
	 * Add to Cart.
	 */
	class WP_EasyCart_Elementor_Add_To_Cart_Widget extends WP_EasyCart_Elementor_Widget_Base {

		use WP_EasyCart_Product_Buy_Controls;

		/**
		 * Panel category.
		 *
		 * @var string
		 */
		protected static $ec_category = 'product';

		/**
		 * Help page.
		 *
		 * @var string
		 */
		protected static $ec_help = 'add-to-cart';

		/**
		 * Widget name.
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_add_to_cart';
		}

		/**
		 * Widget title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Add to Cart', 'wp-easycart' );
		}

		/**
		 * Widget icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-product-add-to-cart';
		}

		/**
		 * Search keywords.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array( 'add to cart', 'buy', 'buy now', 'cart', 'purchase', 'button', 'quantity', 'options', 'variations', 'swatches', 'product' );
		}

		/**
		 * Scripts. With reCAPTCHA on, Google's script too ( registered by wp_easycart_register_grecaptcha_js() ): the
		 * inquiry and back in stock forms print a CAPTCHA box, and the store only enqueues that script on pages whose
		 * content holds a store shortcode, so a popup, header or Theme Builder template would have none.
		 *
		 * @return array
		 */
		public function get_script_depends() {
			$scripts = array_merge( parent::get_script_depends(), array( WP_EasyCart_Product_Buy::HANDLE ) );
			if ( wp_easycart_recaptcha_ready() ) {
				$scripts[] = 'wpeasycart_google_recaptcha_js';
			}
			return $scripts;
		}

		/**
		 * Styles.
		 *
		 * @return array
		 */
		public function get_style_depends() {
			return array_merge( parent::get_style_depends(), array( WP_EasyCart_Product_Buy::HANDLE ) );
		}

		/**
		 * Controls.
		 */
		protected function register_controls() {
			$this->ec_register_product_controls();

			$this->start_controls_section(
				'section_add_to_cart',
				array(
					'label' => __( 'Add to cart', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->add_control(
				'button_text',
				array(
					'label'       => __( 'Button text', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => '',
					'placeholder' => __( 'Add to Cart', 'wp-easycart' ),
					'description' => __( 'Leave empty to use the store’s wording. Backorders, subscriptions and inquiries keep their own button text.', 'wp-easycart' ),
					'ai'          => array( 'active' => false ),
				)
			);
			$this->add_control(
				'button_icon',
				array(
					'label'   => __( 'Button icon', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::ICONS,
					'default' => array(
						'value'   => 'fas fa-shopping-cart',
						'library' => 'fa-solid',
					),
					'skin'    => 'inline',
				)
			);
			$this->add_control(
				'icon_position',
				array(
					'label'     => __( 'Icon position', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::CHOOSE,
					'default'   => 'before',
					'toggle'    => false,
					'options'   => array(
						'before' => array(
							'title' => __( 'Before the text', 'wp-easycart' ),
							'icon'  => 'eicon-h-align-left',
						),
						'after'  => array(
							'title' => __( 'After the text', 'wp-easycart' ),
							'icon'  => 'eicon-h-align-right',
						),
					),
					'condition' => array(
						'button_icon[value]!' => '',
					),
				)
			);
			$side_cart = function_exists( 'wp_easycart_elementor_pro_feature' ) ? wp_easycart_elementor_pro_feature( 'side_cart' ) : 'locked';
			$own_page  = __( 'Subscriptions and inquiries always go to their own page.', 'wp-easycart' );
			if ( 'active' === $side_cart ) {
				$after_add_note = $own_page;
			} elseif ( 'update' === $side_cart ) {
				$after_add_note = __( 'The side cart needs the latest WP EasyCart PRO: update it on the Plugins screen. Until then shoppers see the confirmation.', 'wp-easycart' ) . ' ' . $own_page;
			} else {
				$after_add_note = ( function_exists( 'wp_easycart_elementor_requires_text' ) ? wp_easycart_elementor_requires_text( __( 'The side cart', 'wp-easycart' ) ) : '' ) . ' ' . __( 'Until then shoppers see the confirmation.', 'wp-easycart' ) . ' ' . $own_page;
			}
			$this->add_control(
				'after_add',
				array(
					'label'       => __( 'After adding', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'default'     => 'stay',
					'options'     => array(
						'stay'      => __( 'Stay on the page and confirm', 'wp-easycart' ),
						'side_cart' => __( 'Open the side cart', 'wp-easycart' ),
						'cart'      => __( 'Go to the cart', 'wp-easycart' ),
					),
					'description' => trim( $after_add_note ),
				)
			);
			$this->add_control(
				'added_text',
				array(
					'label'       => __( 'Confirmation', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => '',
					'placeholder' => __( 'Added to your cart.', 'wp-easycart' ),
					'condition'   => array(
						'after_add!' => 'cart',
					),
				)
			);
			$this->add_control(
				'quantity_style',
				array(
					'label'   => __( 'Quantity', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => 'buttons',
					'options' => array(
						'buttons' => __( 'Box with − and + buttons', 'wp-easycart' ),
						'input'   => __( 'Number box', 'wp-easycart' ),
						'none'    => __( 'Hidden ( one at a time )', 'wp-easycart' ),
					),
				)
			);
			$this->add_control(
				'show_buy_now',
				array(
					'label'       => __( 'Buy now button', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SWITCHER,
					'default'     => '',
					'description' => __( 'Adds the product and goes straight to checkout.', 'wp-easycart' ),
				)
			);
			$this->add_control(
				'buy_now_text',
				array(
					'label'       => __( 'Buy now text', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => '',
					'placeholder' => __( 'Buy now', 'wp-easycart' ),
					'condition'   => array(
						'show_buy_now' => 'yes',
					),
					'ai'          => array( 'active' => false ),
				)
			);
			$this->add_control(
				'buy_now_layout',
				array(
					'label'        => __( 'Buy now button sits', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SELECT,
					'default'      => 'stacked',
					'options'      => array(
						'stacked' => __( 'Under the add to cart button', 'wp-easycart' ),
						'inline'  => __( 'Next to it', 'wp-easycart' ),
					),
					'prefix_class' => 'wpec-atc-buynow-',
					'condition'    => array(
						'show_buy_now' => 'yes',
					),
				)
			);
			$this->add_control(
				'show_your_price',
				array(
					'label'       => __( 'Price with the chosen options', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SWITCHER,
					'default'     => 'yes',
					'description' => __( 'Shown for products with options.', 'wp-easycart' ),
				)
			);
			$this->add_control(
				'your_price_label',
				array(
					'label'       => __( 'Price label', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => '',
					'placeholder' => __( 'Your Price:', 'wp-easycart' ),
					'description' => __( 'Leave empty to use the store’s wording.', 'wp-easycart' ),
					'condition'   => array(
						'show_your_price' => 'yes',
					),
					'ai'          => array( 'active' => false ),
				)
			);
			$this->add_control(
				'your_price_offers',
				array(
					'label'       => __( 'Running offers', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'default'     => 'after',
					'options'     => array(
						'after'  => __( 'Show the price after the offer', 'wp-easycart' ),
						'before' => __( 'Show the price before the offer', 'wp-easycart' ),
					),
					'description' => __( 'When an offer lowers this product, the price the shopper pays is the one after it.', 'wp-easycart' ),
					'condition'   => array(
						'show_your_price' => 'yes',
					),
				)
			);
			$this->add_control(
				'show_stock',
				array(
					'label'   => __( 'Stock', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SWITCHER,
					'default' => 'yes',
				)
			);
			$this->add_control(
				'settings_note',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => sprintf(
						/* translators: %s: link to Settings › Products. */
						esc_html__( 'Where "Go to the cart" lands, stock counts and back-in-stock emails are store settings: %s.', 'wp-easycart' ),
						'<a href="' . esc_url( admin_url( 'admin.php?page=wp-easycart-settings&subpage=products' ) ) . '" target="_blank">' . esc_html__( 'Settings › Products', 'wp-easycart' ) . '</a>'
					),
					'content_classes' => 'elementor-descriptor',
				)
			);
			$this->end_controls_section();

			$this->register_style_controls();
		}

		/**
		 * Style tab.
		 */
		private function register_style_controls() {
			/* Options */
			$this->start_controls_section(
				'style_options',
				array(
					'label' => __( 'Options', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->pb_color( 'label_color', __( 'Label color', 'wp-easycart' ), '--wpec-atc-label-color' );
			$this->pb_typography( 'label_typography', __( 'Label typography', 'wp-easycart' ), '{{WRAPPER}} .wpec-atc .ec_details_option_label_ele, {{WRAPPER}} .wpec-atc .ec_details_option_label', 'text' );
			$this->add_responsive_control(
				'options_gap',
				array(
					'label'      => __( 'Space between options', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px', 'em' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 60,
						),
					),
					'selectors'  => array(
						'{{WRAPPER}}' => '--wpec-atc-gap: {{SIZE}}{{UNIT}};',
					),
				)
			);
			$this->add_control(
				'label_position',
				array(
					'label'        => __( 'Labels', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SELECT,
					'default'      => 'above',
					'options'      => array(
						'above'  => __( 'Above the field', 'wp-easycart' ),
						'inline' => __( 'Beside the field', 'wp-easycart' ),
					),
					'prefix_class' => 'wpec-atc-labels-',
				)
			);
			$this->pb_size(
				'label_width',
				__( 'Label width', 'wp-easycart' ),
				'--wpec-atc-label-width',
				array(
					'size_units' => array( '%', 'px', 'em' ),
					'range'      => array(
						'%'  => array(
							'min' => 10,
							'max' => 60,
						),
						'px' => array(
							'min' => 40,
							'max' => 400,
						),
					),
					'condition'  => array(
						'label_position' => 'inline',
					),
				)
			);
			$this->add_control(
				'required_mark',
				array(
					'label'        => __( 'Mark required options', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'default'      => '',
					'description'  => __( 'A star after the label of every option the shopper must fill in.', 'wp-easycart' ),
					'prefix_class' => 'wpec-atc-required-',
				)
			);
			$this->pb_color( 'required_color', __( 'Star color', 'wp-easycart' ), '--wpec-atc-required', array( 'condition' => array( 'required_mark' => 'yes' ) ) );
			$this->pb_heading( 'fields_heading', __( 'Fields', 'wp-easycart' ) );
			$this->pb_typography( 'field_typography', __( 'Field typography', 'wp-easycart' ), '{{WRAPPER}} .wpec-atc .ec_details_option_data input[type="text"], {{WRAPPER}} .wpec-atc .ec_details_option_data input[type="email"], {{WRAPPER}} .wpec-atc .ec_details_option_data input[type="number"], {{WRAPPER}} .wpec-atc .ec_details_option_data select, {{WRAPPER}} .wpec-atc .ec_details_option_data textarea, {{WRAPPER}} .wpec-atc .ec_out_of_stock_notify_input input', 'text' );
			$this->pb_color( 'field_text_color', __( 'Field text color', 'wp-easycart' ), '--wpec-atc-field-color' );
			$this->pb_color( 'field_background_color', __( 'Field background', 'wp-easycart' ), '--wpec-atc-field-bg' );
			$this->pb_color( 'field_border_color', __( 'Field border color', 'wp-easycart' ), '--wpec-atc-field-border' );
			$this->pb_color( 'field_focus_color', __( 'Focus color', 'wp-easycart' ), '--wpec-atc-focus', array( 'description' => __( 'The outline of the field, swatch or button a keyboard is on.', 'wp-easycart' ) ) );
			$this->pb_size(
				'field_border_width',
				__( 'Border width', 'wp-easycart' ),
				'--wpec-atc-field-border-width',
				array(
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 6,
						),
					),
				)
			);
			$this->pb_size( 'field_radius', __( 'Border radius', 'wp-easycart' ), '--wpec-atc-field-radius' );
			$this->pb_size(
				'field_height',
				__( 'Height', 'wp-easycart' ),
				'--wpec-atc-field-height',
				array(
					'range' => array(
						'px' => array(
							'min' => 24,
							'max' => 80,
						),
					),
				)
			);
			$this->pb_dimensions( 'field_padding', __( 'Padding', 'wp-easycart' ), '{{WRAPPER}} .wpec-atc .ec_details_option_data input[type="text"]:not(.ec_dimensions_box), {{WRAPPER}} .wpec-atc .ec_details_option_data input[type="email"], {{WRAPPER}} .wpec-atc .ec_details_option_data input[type="number"], {{WRAPPER}} .wpec-atc .ec_details_option_data select:not(.ec_dimensions_select), {{WRAPPER}} .wpec-atc .ec_details_option_data textarea, {{WRAPPER}} .wpec-atc .ec_out_of_stock_notify_input input' );
			$this->pb_heading( 'errors_heading', __( 'Errors', 'wp-easycart' ) );
			$this->pb_typography( 'error_typography', __( 'Error typography', 'wp-easycart' ), '{{WRAPPER}} .wpec-atc .ec_details_option_row_error', 'text' );
			$this->end_controls_section();

			/* Swatches */
			$this->start_controls_section(
				'style_swatches',
				array(
					'label' => __( 'Swatches', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->pb_typography( 'swatch_typography', __( 'Typography', 'wp-easycart' ), '{{WRAPPER}} .wpec-atc .wpeasycart-html-swatch-ele', 'text' );
			$this->start_controls_tabs( 'swatch_tabs' );
			$this->start_controls_tab( 'swatch_tab_normal', array( 'label' => __( 'Normal', 'wp-easycart' ) ) );
			$this->pb_color( 'swatch_text_color', __( 'Text color', 'wp-easycart' ), '--wpec-swatch-color' );
			$this->pb_color( 'swatch_background_color', __( 'Background', 'wp-easycart' ), '--wpec-swatch-bg' );
			$this->pb_color( 'swatch_border_color', __( 'Border color', 'wp-easycart' ), '--wpec-swatch-border' );
			$this->end_controls_tab();
			$this->start_controls_tab( 'swatch_tab_hover', array( 'label' => __( 'Hover', 'wp-easycart' ) ) );
			$this->pb_color( 'swatch_hover_text_color', __( 'Text color', 'wp-easycart' ), '--wpec-swatch-hover-color' );
			$this->pb_color( 'swatch_hover_background_color', __( 'Background', 'wp-easycart' ), '--wpec-swatch-hover-bg' );
			$this->pb_color( 'swatch_hover_border_color', __( 'Border color', 'wp-easycart' ), '--wpec-swatch-hover-border' );
			$this->end_controls_tab();
			$this->start_controls_tab( 'swatch_tab_selected', array( 'label' => __( 'Selected', 'wp-easycart' ) ) );
			$this->pb_color( 'swatch_selected_text_color', __( 'Text color', 'wp-easycart' ), '--wpec-swatch-selected-color' );
			$this->pb_color( 'swatch_selected_background_color', __( 'Background', 'wp-easycart' ), '--wpec-swatch-selected-bg' );
			$this->pb_color( 'swatch_selected_border_color', __( 'Border color', 'wp-easycart' ), '--wpec-swatch-selected-border' );
			$this->end_controls_tab();
			$this->start_controls_tab( 'swatch_tab_unavailable', array( 'label' => __( 'Unavailable', 'wp-easycart' ) ) );
			$this->add_control(
				'swatch_unavailable_opacity',
				array(
					'label'     => __( 'Opacity', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SLIDER,
					'range'     => array(
						'px' => array(
							'min'  => 0.1,
							'max'  => 1,
							'step' => 0.05,
						),
					),
					'selectors' => array(
						'{{WRAPPER}}' => '--wpec-swatch-unavailable-opacity: {{SIZE}};',
					),
				)
			);
			$this->add_control(
				'swatch_unavailable_strike',
				array(
					'label'        => __( 'Cross out', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'default'      => 'yes',
					'prefix_class' => 'wpec-swatch-strike-',
				)
			);
			$this->end_controls_tab();
			$this->end_controls_tabs();
			$this->add_responsive_control(
				'swatch_border_radius',
				array(
					'label'      => __( 'Border radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em', '%' ),
					'separator'  => 'before',
					'selectors'  => array(
						'{{WRAPPER}} .wpec-atc .ec_details_swatch_ele, {{WRAPPER}} .wpec-atc .ec_details_swatch_ele img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
				)
			);
			$this->pb_size(
				'swatch_size',
				__( 'Picture swatch size', 'wp-easycart' ),
				array(
					'{{WRAPPER}} .wpec-atc .ec_details_swatches_ele > li > img' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}; object-fit: cover;',
				),
				array(
					'range'       => array(
						'px' => array(
							'min' => 16,
							'max' => 120,
						),
					),
					'description' => __( 'Swatches with a picture. Text swatches grow with their text.', 'wp-easycart' ),
				)
			);
			$this->pb_size( 'swatch_gap', __( 'Space between swatches', 'wp-easycart' ), '--wpec-swatch-gap' );
			$this->end_controls_section();

			/* Quantity */
			$this->start_controls_section(
				'style_quantity',
				array(
					'label'     => __( 'Quantity', 'wp-easycart' ),
					'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
					'condition' => array(
						'quantity_style!' => 'none',
					),
				)
			);
			$this->pb_typography( 'quantity_typography', __( 'Number typography', 'wp-easycart' ), '{{WRAPPER}} .wpec-atc .wpec-atc__qty-input', 'text' );
			$this->pb_color( 'quantity_text_color', __( 'Number color', 'wp-easycart' ), '--wpec-atc-qty-color' );
			$this->pb_color( 'quantity_background_color', __( 'Background', 'wp-easycart' ), '--wpec-atc-qty-bg' );
			$this->pb_size(
				'quantity_width',
				__( 'Number box width', 'wp-easycart' ),
				'--wpec-atc-qty-width',
				array(
					'range' => array(
						'px' => array(
							'min' => 24,
							'max' => 160,
						),
					),
				)
			);
			$this->pb_size(
				'quantity_height',
				__( 'Height', 'wp-easycart' ),
				'--wpec-atc-qty-height',
				array(
					'range' => array(
						'px' => array(
							'min' => 24,
							'max' => 90,
						),
					),
				)
			);
			$this->start_controls_tabs( 'quantity_button_tabs', array( 'condition' => array( 'quantity_style' => 'buttons' ) ) );
			$this->start_controls_tab( 'quantity_button_tab_normal', array( 'label' => __( 'Normal', 'wp-easycart' ) ) );
			$this->pb_color( 'quantity_button_color', __( 'Button color', 'wp-easycart' ), '--wpec-atc-qty-button-color', array( 'condition' => array( 'quantity_style' => 'buttons' ) ) );
			$this->pb_color( 'quantity_button_background_color', __( 'Button background', 'wp-easycart' ), '--wpec-atc-qty-button-bg', array( 'condition' => array( 'quantity_style' => 'buttons' ) ) );
			$this->end_controls_tab();
			$this->start_controls_tab( 'quantity_button_tab_hover', array( 'label' => __( 'Hover', 'wp-easycart' ) ) );
			$this->pb_color( 'quantity_button_hover_color', __( 'Button color', 'wp-easycart' ), '--wpec-atc-qty-button-hover-color', array( 'condition' => array( 'quantity_style' => 'buttons' ) ) );
			$this->pb_color( 'quantity_button_hover_background_color', __( 'Button background', 'wp-easycart' ), '--wpec-atc-qty-button-hover-bg', array( 'condition' => array( 'quantity_style' => 'buttons' ) ) );
			$this->end_controls_tab();
			$this->end_controls_tabs();
			$this->pb_color( 'quantity_border_color', __( 'Border color', 'wp-easycart' ), '--wpec-atc-qty-border', array( 'separator' => 'before' ) );
			$this->add_responsive_control(
				'quantity_border_radius',
				array(
					'label'      => __( 'Border radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em', '%' ),
					'selectors'  => array(
						'{{WRAPPER}} .wpec-atc .wpec-atc__qty' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
				)
			);
			$this->add_control(
				'quantity_minus_icon',
				array(
					'label'     => __( 'Minus icon', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::ICONS,
					'skin'      => 'inline',
					'separator' => 'before',
					'condition' => array(
						'quantity_style' => 'buttons',
					),
				)
			);
			$this->add_control(
				'quantity_plus_icon',
				array(
					'label'     => __( 'Plus icon', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::ICONS,
					'skin'      => 'inline',
					'condition' => array(
						'quantity_style' => 'buttons',
					),
				)
			);
			$this->end_controls_section();

			/* Button */
			$this->start_controls_section(
				'style_button',
				array(
					'label' => __( 'Button', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->pb_typography( 'button_typography', __( 'Typography', 'wp-easycart' ), '{{WRAPPER}} .wpec-atc .wpec-atc__button', '' );
			$this->add_responsive_control(
				'button_width',
				array(
					'label'        => __( 'Width', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SELECT,
					'default'      => 'fill',
					'options'      => array(
						'fill' => __( 'Fill the row', 'wp-easycart' ),
						'auto' => __( 'Fit the text', 'wp-easycart' ),
						'full' => __( 'Full width, below the quantity', 'wp-easycart' ),
					),
					'prefix_class' => 'wpec-atc-button%s-',
				)
			);
			$this->pb_size(
				'button_min_height',
				__( 'Minimum height', 'wp-easycart' ),
				'--wpec-atc-button-min-h',
				array(
					'range' => array(
						'px' => array(
							'min' => 24,
							'max' => 100,
						),
					),
				)
			);
			$this->start_controls_tabs( 'button_tabs' );
			$this->start_controls_tab( 'button_tab_normal', array( 'label' => __( 'Normal', 'wp-easycart' ) ) );
			$this->pb_color( 'button_text_color', __( 'Text color', 'wp-easycart' ), '--wpec-atc-button-color' );
			$this->pb_color( 'button_background_color', __( 'Background', 'wp-easycart' ), '--wpec-atc-button-bg' );
			$this->pb_shadow( 'button_shadow', '{{WRAPPER}} .wpec-atc .wpec-atc__button--add' );
			$this->end_controls_tab();
			$this->start_controls_tab( 'button_tab_hover', array( 'label' => __( 'Hover', 'wp-easycart' ) ) );
			$this->pb_color( 'button_hover_text_color', __( 'Text color', 'wp-easycart' ), '--wpec-atc-button-hover-color' );
			$this->pb_color(
				'button_hover_background_color',
				__( 'Background', 'wp-easycart' ),
				'--wpec-atc-button-hover-bg',
				array(
					'selectors' => array(
						'{{WRAPPER}}' => '--wpec-atc-button-hover-bg: {{VALUE}}; --wpec-atc-button-hover-filter: none;',
					),
				)
			);
			$this->pb_color( 'button_hover_border_color', __( 'Border color', 'wp-easycart' ), '--wpec-atc-button-hover-border' );
			$this->pb_shadow( 'button_hover_shadow', '{{WRAPPER}} .wpec-atc .wpec-atc__button--add:hover' );
			$this->add_control(
				'button_transition',
				array(
					'label'     => __( 'Transition duration', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SLIDER,
					'range'     => array(
						'px' => array(
							'min'  => 0,
							'max'  => 1,
							'step' => 0.05,
						),
					),
					'selectors' => array(
						'{{WRAPPER}}' => '--wpec-atc-transition: {{SIZE}}s;',
					),
				)
			);
			$this->end_controls_tab();
			$this->start_controls_tab( 'button_tab_busy', array( 'label' => __( 'Adding', 'wp-easycart' ) ) );
			$this->add_control(
				'button_busy_note',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => esc_html__( 'While the product is being added, and while a button cannot be used.', 'wp-easycart' ),
					'content_classes' => 'elementor-descriptor',
				)
			);
			$this->pb_color( 'button_busy_text_color', __( 'Text color', 'wp-easycart' ), '--wpec-atc-busy-color', array( 'selectors' => array( '{{WRAPPER}} .wpec-atc .wpec-atc__button.is-loading, {{WRAPPER}} .wpec-atc .wpec-atc__button:disabled' => 'color: {{VALUE}};' ) ) );
			$this->pb_color( 'button_busy_background_color', __( 'Background', 'wp-easycart' ), '--wpec-atc-busy-bg', array( 'selectors' => array( '{{WRAPPER}} .wpec-atc .wpec-atc__button.is-loading, {{WRAPPER}} .wpec-atc .wpec-atc__button:disabled' => 'background-color: {{VALUE}};' ) ) );
			$this->add_control(
				'button_busy_opacity',
				array(
					'label'     => __( 'Opacity', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SLIDER,
					'range'     => array(
						'px' => array(
							'min'  => 0.2,
							'max'  => 1,
							'step' => 0.05,
						),
					),
					'selectors' => array(
						'{{WRAPPER}}' => '--wpec-atc-busy-opacity: {{SIZE}};',
					),
				)
			);
			$this->end_controls_tab();
			$this->end_controls_tabs();
			$this->add_group_control(
				\Elementor\Group_Control_Border::get_type(),
				array(
					'name'      => 'button_border',
					'selector'  => '{{WRAPPER}} .wpec-atc .wpec-atc__button',
					'separator' => 'before',
				)
			);
			$this->add_responsive_control(
				'button_border_radius',
				array(
					'label'      => __( 'Border radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em', '%' ),
					'selectors'  => array(
						'{{WRAPPER}} .wpec-atc .wpec-atc__button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
				)
			);
			$this->add_responsive_control(
				'button_padding',
				array(
					'label'      => __( 'Padding', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em' ),
					'selectors'  => array(
						'{{WRAPPER}} .wpec-atc .wpec-atc__button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
				)
			);
			$this->pb_heading( 'button_icon_heading', __( 'Icon', 'wp-easycart' ), array( 'condition' => array( 'button_icon[value]!' => '' ) ) );
			$this->pb_icon_style( 'button_icon_style', '{{WRAPPER}} .wpec-atc .wpec-atc__button-icon', array( 'condition' => array( 'button_icon[value]!' => '' ) ) );
			$this->pb_size( 'button_icon_gap', __( 'Space between icon and text', 'wp-easycart' ), '--wpec-atc-icon-gap', array( 'condition' => array( 'button_icon[value]!' => '' ) ) );
			$this->end_controls_section();

			/* The row of quantity and buttons */
			$this->start_controls_section(
				'style_actions',
				array(
					'label' => __( 'Quantity and buttons row', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->add_responsive_control(
				'actions_align',
				array(
					'label'                => __( 'Alignment', 'wp-easycart' ),
					'type'                 => \Elementor\Controls_Manager::CHOOSE,
					'options'              => array(
						'start'  => array(
							'title' => __( 'Start', 'wp-easycart' ),
							'icon'  => 'eicon-h-align-left',
						),
						'center' => array(
							'title' => __( 'Center', 'wp-easycart' ),
							'icon'  => 'eicon-h-align-center',
						),
						'end'    => array(
							'title' => __( 'End', 'wp-easycart' ),
							'icon'  => 'eicon-h-align-right',
						),
					),
					'selectors_dictionary' => array(
						'start'  => 'flex-start',
						'center' => 'center',
						'end'    => 'flex-end',
					),
					'selectors'            => array(
						'{{WRAPPER}}' => '--wpec-atc-actions-justify: {{VALUE}};',
					),
					'description'          => __( 'For buttons that fit their text.', 'wp-easycart' ),
				)
			);
			$this->pb_size( 'actions_gap', __( 'Space between', 'wp-easycart' ), '--wpec-atc-actions-gap' );
			$this->end_controls_section();

			/* Buy now */
			$this->start_controls_section(
				'style_buy_now',
				array(
					'label'     => __( 'Buy now button', 'wp-easycart' ),
					'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
					'condition' => array(
						'show_buy_now' => 'yes',
					),
				)
			);
			$this->start_controls_tabs( 'buy_now_tabs' );
			$this->start_controls_tab( 'buy_now_tab_normal', array( 'label' => __( 'Normal', 'wp-easycart' ) ) );
			$this->pb_color( 'buy_now_text_color', __( 'Text color', 'wp-easycart' ), '--wpec-atc-buy-color' );
			$this->pb_color( 'buy_now_background_color', __( 'Background', 'wp-easycart' ), '--wpec-atc-buy-bg' );
			$this->pb_color( 'buy_now_border_color', __( 'Border color', 'wp-easycart' ), '--wpec-atc-buy-border' );
			$this->end_controls_tab();
			$this->start_controls_tab( 'buy_now_tab_hover', array( 'label' => __( 'Hover', 'wp-easycart' ) ) );
			$this->pb_color( 'buy_now_hover_text_color', __( 'Text color', 'wp-easycart' ), '--wpec-atc-buy-hover-color' );
			$this->pb_color( 'buy_now_hover_background_color', __( 'Background', 'wp-easycart' ), '--wpec-atc-buy-hover-bg' );
			$this->end_controls_tab();
			$this->end_controls_tabs();
			$buy_now = '{{WRAPPER}} .wpec-atc .wpec-atc__button--buy-now';
			$this->pb_typography( 'buy_now_typography', __( 'Typography', 'wp-easycart' ), $buy_now, '' );

			/*
			 * No colour field: the Normal tab's "Border color" is buy_now_border_color, the name the group's own colour would
			 * take ( Elementor refused the second one with a "Cannot redeclare control" notice ). product-buy.css reads it.
			 */
			$this->pb_border(
				'buy_now_border',
				$buy_now,
				array(
					'separator' => 'before',
					'exclude'   => array( 'color' ),
				)
			);
			$this->pb_dimensions( 'buy_now_radius', __( 'Border radius', 'wp-easycart' ), $buy_now, 'border-radius' );
			$this->pb_dimensions( 'buy_now_padding', __( 'Padding', 'wp-easycart' ), $buy_now );
			$this->end_controls_section();

			/* Price and messages */
			$this->start_controls_section(
				'style_messages',
				array(
					'label' => __( 'Price and messages', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->pb_color( 'your_price_color', __( 'Price color', 'wp-easycart' ), '--wpec-atc-price-color' );
			$this->pb_typography( 'your_price_typography', __( 'Price typography', 'wp-easycart' ), '{{WRAPPER}} .wpec-atc .wpec-atc__your-price', '' );
			$this->pb_color( 'your_price_label_color', __( 'Price label color', 'wp-easycart' ), '--wpec-atc-price-label-color' );
			$this->pb_typography( 'your_price_label_typography', __( 'Price label typography', 'wp-easycart' ), '{{WRAPPER}} .wpec-atc .wpec-atc__your-price-label', 'text' );
			$this->pb_heading( 'stock_note_heading', __( 'Stock', 'wp-easycart' ), array( 'condition' => array( 'show_stock' => 'yes' ) ) );
			$this->pb_color( 'stock_note_color', __( 'Stock text color', 'wp-easycart' ), '--wpec-atc-stock-color', array( 'condition' => array( 'show_stock' => 'yes' ) ) );
			$this->pb_typography( 'stock_note_typography', __( 'Stock typography', 'wp-easycart' ), '{{WRAPPER}} .wpec-atc .wpec-atc__stock', 'text', array( 'condition' => array( 'show_stock' => 'yes' ) ) );
			$this->pb_heading( 'messages_heading', __( 'Messages', 'wp-easycart' ) );
			$this->pb_color( 'success_color', __( 'Confirmation color', 'wp-easycart' ), '--wpec-atc-success' );
			$this->pb_color( 'error_color', __( 'Error color', 'wp-easycart' ), '--wpec-atc-error' );
			$this->pb_color( 'message_background_color', __( 'Confirmation background', 'wp-easycart' ), '--wpec-atc-status-bg' );
			$this->pb_typography( 'message_typography', __( 'Message typography', 'wp-easycart' ), '{{WRAPPER}} .wpec-atc .wpec-atc__status, {{WRAPPER}} .wpec-atc .wpec-atc__note, {{WRAPPER}} .wpec-atc .ec_details_add_to_cart_area .ec_out_of_stock', 'text' );
			$this->pb_color( 'view_cart_color', __( '"View cart" link color', 'wp-easycart' ), '--wpec-atc-link-color' );
			$this->pb_typography( 'view_cart_typography', __( '"View cart" link typography', 'wp-easycart' ), '{{WRAPPER}} .wpec-atc .wpec-atc__status-link', 'text' );
			$this->end_controls_section();

			/* Back in stock and inquiry forms */
			$this->start_controls_section(
				'style_forms',
				array(
					'label' => __( 'Back in stock and inquiry forms', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->add_control(
				'forms_note',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => esc_html__( 'Their fields follow Options › Fields.', 'wp-easycart' ),
					'content_classes' => 'elementor-descriptor',
				)
			);
			$this->pb_color( 'form_title_color', __( 'Title color', 'wp-easycart' ), '--wpec-atc-form-title', array( 'selectors' => array( '{{WRAPPER}} .wpec-atc .ec_out_of_stock_notify_title' => 'color: {{VALUE}};' ) ) );
			$this->pb_typography( 'form_title_typography', __( 'Title typography', 'wp-easycart' ), '{{WRAPPER}} .wpec-atc .ec_out_of_stock_notify_title', 'text' );
			$form_button       = '{{WRAPPER}} .wpec-atc .wpec-atc__notify .wpec-atc__button, {{WRAPPER}} .wpec-atc .wpec-atc__button--inquiry';
			$form_button_hover = '{{WRAPPER}} .wpec-atc .wpec-atc__notify .wpec-atc__button:hover, {{WRAPPER}} .wpec-atc .wpec-atc__button--inquiry:hover';
			$this->pb_typography( 'form_button_typography', __( 'Button typography', 'wp-easycart' ), $form_button, '' );
			$this->start_controls_tabs( 'form_button_tabs' );
			$this->start_controls_tab( 'form_button_tab_normal', array( 'label' => __( 'Normal', 'wp-easycart' ) ) );
			$this->pb_color( 'form_button_color', __( 'Text color', 'wp-easycart' ), '--wpec-atc-form-button-color', array( 'selectors' => array( $form_button => 'color: {{VALUE}};' ) ) );
			$this->pb_color( 'form_button_background_color', __( 'Background', 'wp-easycart' ), '--wpec-atc-form-button-bg', array( 'selectors' => array( $form_button => 'background-color: {{VALUE}};' ) ) );
			$this->end_controls_tab();
			$this->start_controls_tab( 'form_button_tab_hover', array( 'label' => __( 'Hover', 'wp-easycart' ) ) );
			$this->pb_color( 'form_button_hover_color', __( 'Text color', 'wp-easycart' ), '--wpec-atc-form-button-hover-color', array( 'selectors' => array( $form_button_hover => 'color: {{VALUE}};' ) ) );
			$this->pb_color( 'form_button_hover_background_color', __( 'Background', 'wp-easycart' ), '--wpec-atc-form-button-hover-bg', array( 'selectors' => array( $form_button_hover => 'background-color: {{VALUE}}; border-color: {{VALUE}};' ) ) );
			$this->end_controls_tab();
			$this->end_controls_tabs();
			$this->pb_border( 'form_button_border', $form_button, array( 'separator' => 'before' ) );
			$this->pb_dimensions( 'form_button_radius', __( 'Border radius', 'wp-easycart' ), $form_button, 'border-radius' );
			$this->end_controls_section();
		}

		/**
		 * The widget's choices for the add to cart template.
		 *
		 * @param array $settings Settings.
		 * @return array
		 */
		private function buy_args( $settings ) {
			$after    = ( isset( $settings['after_add'] ) && in_array( $settings['after_add'], array( 'stay', 'side_cart', 'cart' ), true ) ) ? $settings['after_add'] : 'stay';
			$quantity = ( isset( $settings['quantity_style'] ) && in_array( $settings['quantity_style'], array( 'buttons', 'input', 'none' ), true ) ) ? $settings['quantity_style'] : 'buttons';
			return array(
				'after_add'     => $after,
				'quantity'      => $quantity,
				'your_price'    => ( isset( $settings['show_your_price'] ) && 'yes' === $settings['show_your_price'] ),
				'price_label'   => isset( $settings['your_price_label'] ) ? sanitize_text_field( (string) $settings['your_price_label'] ) : '',
				'price_offers'  => ( isset( $settings['your_price_offers'] ) && 'before' === $settings['your_price_offers'] ) ? 'before' : 'after',
				'stock_note'    => ( isset( $settings['show_stock'] ) && 'yes' === $settings['show_stock'] ),
				'button_text'   => isset( $settings['button_text'] ) ? sanitize_text_field( (string) $settings['button_text'] ) : '',
				'button_icon'   => $this->pb_icon_html( isset( $settings['button_icon'] ) ? $settings['button_icon'] : null ),
				'icon_position' => ( isset( $settings['icon_position'] ) && 'after' === $settings['icon_position'] ) ? 'after' : 'before',
				'buy_now'       => ( isset( $settings['show_buy_now'] ) && 'yes' === $settings['show_buy_now'] ),
				'buy_now_text'  => isset( $settings['buy_now_text'] ) ? sanitize_text_field( (string) $settings['buy_now_text'] ) : '',
				'minus_icon'    => $this->pb_icon_html( isset( $settings['quantity_minus_icon'] ) ? $settings['quantity_minus_icon'] : null ),
				'plus_icon'     => $this->pb_icon_html( isset( $settings['quantity_plus_icon'] ) ? $settings['quantity_plus_icon'] : null ),
			);
		}

		/**
		 * Render.
		 */
		protected function render() {
			$settings = $this->get_settings_for_display();
			$product  = $this->ec_product( $settings );
			if ( ! $product ) {
				$this->ec_no_product_notice( __( 'Add to Cart', 'wp-easycart' ) );
				return;
			}
			$is_editor = $this->ec_is_editor();
			WP_EasyCart_Product_Buy::prepare( $product, $is_editor );
			$args     = $this->buy_args( $settings );
			$added    = ( isset( $settings['added_text'] ) && '' !== trim( (string) $settings['added_text'] ) ) ? sanitize_text_field( (string) $settings['added_text'] ) : wp_strip_all_tags( WP_EasyCart_Product_Buy::text( 'added_to_cart', __( 'Added to your cart.', 'wp-easycart' ) ) );
			$texts    = array(
				'added'       => $added,
				'view_cart'   => wp_strip_all_tags( WP_EasyCart_Product_Buy::text( 'view_cart', __( 'View cart', 'wp-easycart' ) ) ),
				/* No answer to a background add ( timeout, dropped connection ): it may still have gone in, so no "try again". */
				'unconfirmed' => wp_strip_all_tags( WP_EasyCart_Product_Buy::text( 'add_unconfirmed', __( 'We could not confirm this was added. Please check your cart before you try again.', 'wp-easycart' ) ) ),
				'editor'      => __( 'Adding to the cart works on the live page.', 'wp-easycart' ),
			);
			$cart     = function_exists( 'wpeasycart_links' ) ? wpeasycart_links()->get_cart_page() : $product->cart_page;
			$checkout = function_exists( 'wpeasycart_links' ) ? wpeasycart_links()->get_cart_page( 'checkout_info' ) : $product->cart_page;
			echo '<div class="wpec-el wpec-atc" data-product-id="' . esc_attr( $product->product_id ) . '" data-wpec-after-add="' . esc_attr( $args['after_add'] ) . '"';
			echo ' data-wpec-cart-url="' . esc_url( $cart ) . '" data-wpec-checkout-url="' . esc_url( $checkout ) . '" data-wpec-ajax-url="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '"';
			echo ' data-wpec-texts="' . esc_attr( wp_json_encode( $texts ) ) . '"' . ( $is_editor ? ' data-wpec-editor="1"' : '' ) . '>';
			WP_EasyCart_Product_Buy::render_add_to_cart( $product, $args );
			echo '</div>';
			/* Editor only: a customised copy of the add to cart template ( wp-easycart-data ) is not what this widget draws. */
			if ( $is_editor && '' !== WP_EasyCart_Product_Buy::unused_copy( 'ec_product_details_page_add_to_cart.php' ) ) {
				$this->ec_editor_notice(
					__( 'This widget does not use your store\'s copy of the add to cart template.', 'wp-easycart' ),
					__( 'Your wp-easycart-data folder has its own ec_product_details_page_add_to_cart.php, which the older Add to Cart widget and the [ec_product_details_addtocart] shortcode use. This widget draws with WP EasyCart\'s template. To use a copy here, make it from the 6.0.2 template and point the widget at it with the filter wp_easycart_elementor_product_buy_template.', 'wp-easycart' )
				);
			}
		}

		/**
		 * The equivalent shortcode for post_content.
		 *
		 * @return string
		 */
		protected function plain_content_shortcode() {
			return $this->pb_plain_shortcode( 'ec_product_details_addtocart', $this->get_settings_for_display() );
		}
	}

endif;
