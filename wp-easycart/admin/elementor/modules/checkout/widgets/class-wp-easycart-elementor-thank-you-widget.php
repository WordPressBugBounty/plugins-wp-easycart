<?php
/**
 * Order Confirmation ( wp_easycart_thank_you, 6.0.2 ): the page shoppers see after they place an order, on the cart page.
 *
 * It draws EasyCart's own confirmation ( load_ec_cart(): purchase tracking, gift details, delayed payment notices ) and adds
 * what the merchant chooses below it ( the module's confirmation_extras() on wpeasycart_success_page_content_middle: a
 * message, the items, totals, addresses, how to pay and an account prompt ). Parts of EasyCart's own can be hidden. The
 * classic confirmation, without this widget, is unchanged. The editor shows a sample order.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/trait-wp-easycart-elementor-checkout-controls.php';

if ( ! class_exists( 'WP_EasyCart_Elementor_Thank_You_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Widget_Base' ) ) :

	/**
	 * Order Confirmation widget.
	 */
	class WP_EasyCart_Elementor_Thank_You_Widget extends WP_EasyCart_Elementor_Widget_Base {

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
		protected static $ec_help = 'order-confirmation';

		/**
		 * Widget name ( stored in saved pages ).
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_thank_you';
		}

		/**
		 * Widget title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Order Confirmation', 'wp-easycart' );
		}

		/**
		 * Panel icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-purchase-summary';
		}

		/**
		 * Search keywords.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array( 'thank you', 'order received', 'order confirmation', 'purchase summary', 'receipt' );
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
			return array_merge( parent::get_script_depends(), array( WP_EasyCart_Elementor_Checkout_Module::SCRIPT ) );
		}

		/**
		 * The parts of the confirmation, control => array( label, description[, default ] ). Every part shows unless it says
		 * otherwise: the account prompt starts off ( its Create Account link opens the plain sign-up page, which does not
		 * attach the order just placed to the new account ).
		 *
		 * @return array
		 */
		protected function ec_parts() {
			return array(
				'ec_show_icon'      => array( __( 'Check mark', 'wp-easycart' ), '' ),
				'ec_show_number'    => array( __( 'Order number', 'wp-easycart' ), '' ),
				'ec_show_email'     => array( __( 'Email note', 'wp-easycart' ), __( 'Where the confirmation email went.', 'wp-easycart' ) ),
				'ec_show_links'     => array( __( 'Order links', 'wp-easycart' ), __( 'Downloads, the order in My Account and Continue shopping.', 'wp-easycart' ) ),
				'ec_show_print'     => array( __( 'Print receipt', 'wp-easycart' ), '' ),
				'ec_show_payment'   => array( __( 'How to pay', 'wp-easycart' ), __( 'For orders paid by bank transfer: your payment details, and a Pay now button while pay links are on.', 'wp-easycart' ) ),
				'ec_show_items'     => array( __( 'Products', 'wp-easycart' ), '' ),
				'ec_show_totals'    => array( __( 'Totals', 'wp-easycart' ), '' ),
				'ec_show_addresses' => array( __( 'Addresses', 'wp-easycart' ), '' ),
				'ec_show_account'   => array( __( 'Account prompt', 'wp-easycart' ), __( 'Asks shoppers who checked out as guests to create an account. It opens your sign-up page; the order they just placed stays a guest order.', 'wp-easycart' ), '' ),
				'ec_show_meta'      => array( __( 'Order date and methods', 'wp-easycart' ), __( 'The order date, payment method and shipping method.', 'wp-easycart' ), '' ),
			);
		}

		/**
		 * Headings and texts the module prints below the thank-you ( round 11 ): control => array( label, language section, key,
		 * English ), from WP_EasyCart_Elementor_Checkout_Module::confirmation_texts() ( confirmation_extras() reads them ).
		 *
		 * @return array
		 */
		protected function ec_extra_texts() {
			return WP_EasyCart_Elementor_Checkout_Module::confirmation_texts();
		}

		/** Controls. */
		protected function register_controls() {
			$this->start_controls_section(
				'ec_thank_you_section',
				array(
					'label' => __( 'Order confirmation', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->add_control(
				'ec_thank_you_note',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => esc_html__( 'Shows on your cart page once an order is placed, with your purchase tracking. The editor shows a sample order.', 'wp-easycart' ),
					'content_classes' => 'elementor-descriptor',
				)
			);
			$this->add_control(
				'ec_message',
				array(
					'label'       => __( 'Message', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::TEXTAREA,
					'rows'        => 3,
					'description' => __( 'Shown under the thank-you, for example what happens next. Simple HTML is allowed.', 'wp-easycart' ),
				)
			);
			foreach ( $this->ec_parts() as $key => $part ) {
				$control = array(
					'label'        => $part[0],
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'label_on'     => __( 'Show', 'wp-easycart' ),
					'label_off'    => __( 'Hide', 'wp-easycart' ),
					'return_value' => 'yes',
					'default'      => isset( $part[2] ) ? $part[2] : 'yes',
				);
				if ( '' !== $part[1] ) {
					$control['description'] = $part[1];
				}
				$this->add_control( $key, $control );
			}
			$this->add_control(
				'ec_show_details_link',
				array(
					'label'        => __( 'Order details button', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'label_on'     => __( 'Show', 'wp-easycart' ),
					'label_off'    => __( 'Hide', 'wp-easycart' ),
					'return_value' => 'yes',
					'default'      => 'yes',
					'condition'    => array( 'ec_show_links' => 'yes' ),
				)
			);
			$this->add_control(
				'ec_show_continue_link',
				array(
					'label'        => __( 'Continue shopping button', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'label_on'     => __( 'Show', 'wp-easycart' ),
					'label_off'    => __( 'Hide', 'wp-easycart' ),
					'return_value' => 'yes',
					'default'      => 'yes',
					'condition'    => array( 'ec_show_links' => 'yes' ),
				)
			);
			$this->end_controls_section();

			$this->start_controls_section(
				'ec_texts_extra_section',
				array(
					'label' => __( 'Headings and buttons', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->add_control(
				'ec_texts_extra_note',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => esc_html__( 'The wording of the parts this widget adds below the thank-you. Leave a field empty for your store\'s own wording.', 'wp-easycart' ),
					'content_classes' => 'elementor-descriptor',
				)
			);
			foreach ( $this->ec_extra_texts() as $key => $text ) {
				$this->add_control(
					$key,
					array(
						'label'       => $text[0],
						'type'        => \Elementor\Controls_Manager::TEXT,
						'default'     => '',
						'placeholder' => wp_strip_all_tags( WP_EasyCart_Elementor_Checkout_Module::text( $text[2], $text[3], $text[1] ) ),
						'label_block' => true,
						'ai'          => array( 'active' => false ),
					)
				);
			}
			$this->end_controls_section();

			$this->ec_text_override_section(
				'ec_texts_section',
				__( 'Texts', 'wp-easycart' ),
				array( 'cart_success_thank_you_title', 'cart_success_will_receive_email', 'cart_payment_receipt_order_details_link', 'cart_continue_shopping', 'cart_success_print_receipt_text' )
			);

			$this->start_controls_section(
				'box_style',
				array(
					'label' => __( 'Thank-you box', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->ec_box_controls( 'box', '.ec_order_success_row' );
			$this->add_control(
				'check_color',
				array(
					'label'     => __( 'Check mark color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'global'    => array( 'default' => \Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_ACCENT ),
					'separator' => 'before',
					'selectors' => array( '{{WRAPPER}} .ec_order_success_loader_loaded_path' => 'stroke: {{VALUE}};' ),
					'condition' => array( 'ec_show_icon' => 'yes' ),
				)
			);
			$this->add_responsive_control(
				'check_size',
				array(
					'label'      => __( 'Check mark size', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 30,
							'max' => 200,
						),
					),
					'selectors'  => array(
						'{{WRAPPER}} .ec_order_success_loader_v2'     => 'width: calc( {{SIZE}}{{UNIT}} * 1.5 ); height: calc( {{SIZE}}{{UNIT}} * 1.5 ); flex: 0 0 auto;',
						'{{WRAPPER}} .ec_order_success_loader_loaded' => 'width: {{SIZE}}{{UNIT}}; margin-top: calc( {{SIZE}}{{UNIT}} / -2 ); margin-left: calc( {{SIZE}}{{UNIT}} / -2 );',
					),
					'condition'  => array( 'ec_show_icon' => 'yes' ),
				)
			);
			$this->add_responsive_control(
				'box_gap',
				array(
					'label'      => __( 'Space between the check mark and the text', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 80,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .ec_order_success_row' => 'column-gap: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->end_controls_section();

			$this->ec_text_section(
				'title',
				__( 'Thank-you heading', 'wp-easycart' ),
				'.ec_cart_success_title',
				array(
					'typography' => \Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_PRIMARY,
					'color'      => \Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_PRIMARY,
					'font_var'   => true,
					'align'      => true,
					'spacing'    => true,
				)
			);
			$this->ec_text_section(
				'number',
				__( 'Order number', 'wp-easycart' ),
				'.ec_cart_success_order_number',
				array(
					'font_var'  => true,
					'align'     => true,
					'condition' => array( 'ec_show_number' => 'yes' ),
				)
			);
			$this->ec_text_section(
				'text',
				__( 'Text', 'wp-easycart' ),
				'.ec_cart_success_subtitle, .wpec-thank-you__message, .wpec-thank-you__bank, .wpec-thank-you__pay-note, .wpec-thank-you__account-text, .wpec-thank-you__address address, .ec_cart_notice_row, .ec_cart_error_row2, .wpec-thank-you__meta-line',
				array(
					'font_var' => true,
					'align'    => true,
				)
			);
			$this->ec_box_section( 'sections', __( 'Order details boxes', 'wp-easycart' ), '.wpec-thank-you__section' );
			/* Round 11: EasyCart's cart page forces the font of every h3 inside it, so the family goes through --wpec-el-font. */
			$this->ec_text_section(
				'headings',
				__( 'Order details headings', 'wp-easycart' ),
				'.wpec-thank-you__heading',
				array(
					'typography' => \Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_PRIMARY,
					'color'      => \Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_PRIMARY,
					'spacing'    => true,
					'font_var'   => true,
					'align'      => true,
				)
			);
			$this->ec_text_section(
				'addresses',
				__( 'Addresses', 'wp-easycart' ),
				'.wpec-thank-you__address address',
				array(
					'font_var'  => true,
					'color'     => '',
					'condition' => array( 'ec_show_addresses' => 'yes' ),
				)
			);
			$this->start_controls_section(
				'items_style',
				array(
					'label' => __( 'Products and totals', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->ec_text_controls( 'items', '.wpec-summary__title, .wpec-summary__price, .wpec-summary__line', array( 'font_var' => true ) );
			$this->add_responsive_control(
				'items_image_width',
				array(
					'label'      => __( 'Image width', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 24,
							'max' => 160,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-summary__image' => 'width: {{SIZE}}{{UNIT}}; flex-basis: {{SIZE}}{{UNIT}};' ),
				)
			);
			/* Round 11: the line above the order total is that line's own border. */
			$this->ec_divider_control( 'items_divider', '.wpec-summary__item, .wpec-summary__totals', 'bottom', array( '.wpec-summary__line--total' => 'border-top-color: {{VALUE}} !important;' ) );
			$this->ec_summary_totals_controls( 'totals', true );
			$this->add_control(
				'total_heading',
				array(
					'label'     => __( 'Grand total', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->ec_text_controls(
				'total',
				'.wpec-summary__line--total',
				array(
					'typography' => \Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_PRIMARY,
					'font_var'   => true,
				)
			);
			$this->end_controls_section();

			$this->ec_button_section(
				'buttons',
				__( 'Buttons', 'wp-easycart' ),
				'.ec_cart_success_continue_shopping_button > a, .wpec-thank-you__button',
				array(
					'vars'     => 'tybtn',
					'font_var' => true,
					'align'    => '.ec_cart_success_continue_shopping_button',
				)
			);
			$this->ec_button_section(
				'secondary_button',
				__( 'Continue shopping button', 'wp-easycart' ),
				'.ec_cart_success_continue_shopping_button > a:last-child:not(:first-child)',
				array(
					'vars'       => 'tybtn2',
					'font_var'   => true,
					'background' => '',
					'condition'  => array( 'ec_show_links' => 'yes' ),
				)
			);
			$this->ec_text_section(
				'print',
				__( 'Print receipt link', 'wp-easycart' ),
				'.ec_cart_success_print_button_v2 > a',
				array(
					'font_var'  => true,
					'color_var' => 'wpec-el-print-color',
					'hover'     => true,
					'condition' => array( 'ec_show_print' => 'yes' ),
				)
			);
		}

		/**
		 * Classes on the widget's box: which of EasyCart's parts it hides.
		 *
		 * @param array $settings Settings.
		 * @return string[]
		 */
		protected function ec_wrapper_classes( $settings ) {
			$classes = array( 'wpec-el', 'wpec-el-cart-page', 'wpec-thank-you' );
			foreach ( array(
				'ec_show_icon'          => 'icon',
				'ec_show_number'        => 'number',
				'ec_show_email'         => 'email',
				'ec_show_links'         => 'links',
				'ec_show_print'         => 'print',
				'ec_show_details_link'  => 'ty-details',
				'ec_show_continue_link' => 'ty-continue',
			) as $key => $part ) {
				if ( ! $this->ec_on( $settings, $key ) ) {
					$classes[] = 'wpec-hide-' . $part;
				}
			}
			return $classes;
		}

		/** Draw. */
		protected function render() {
			$settings = $this->get_settings_for_display();
			$classes  = $this->ec_wrapper_classes( $settings );
			if ( $this->ec_is_editor() ) {
				if ( ! WP_EasyCart_Elementor_Checkout_Module::is_cart_page() ) {
					$this->ec_editor_notice( __( 'Shoppers see this on your cart page after they place an order.', 'wp-easycart' ), __( 'Put this widget on the page chosen as the cart page ( Settings › Initial setup ).', 'wp-easycart' ) );
				}
				echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">';
				$this->ec_render_sample( $settings );
				echo '</div>';
				return;
			}
			if ( 'confirmation' !== WP_EasyCart_Elementor_Checkout_Module::state() || ! WP_EasyCart_Elementor_Checkout_Module::is_cart_page() || ! WP_EasyCart_Elementor_Checkout_Module::owns( $this->get_name() ) ) {
				return;
			}
			echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '" data-wpec-cart-flow="1"' . WP_EasyCart_Elementor_Checkout_Module::context_attributes( $this->get_id() ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- context_attributes() escapes.
			WP_EasyCart_Elementor_Checkout_Module::render_flow( $this->get_name(), $settings );
			echo '</div>';
		}

		/**
		 * The editor's sample order: EasyCart's confirmation markup and the parts this widget adds.
		 *
		 * @param array $settings Settings.
		 */
		protected function ec_render_sample( $settings ) {
			$t        = function ( $key, $fallback, $section ) {
				return wp_strip_all_tags( WP_EasyCart_Elementor_Checkout_Module::text( $key, $fallback, $section ) );
			};
			$own      = function ( $key ) use ( $settings ) {
				return WP_EasyCart_Elementor_Checkout_Module::confirmation_text( $settings, $key );
			};
			$lines    = WP_EasyCart_Elementor_Checkout_Module::sample_lines( 2 );
			$subtotal = 0;
			foreach ( $lines as $line ) {
				$subtotal += $line['amount'];
			}
			$color = (string) get_option( 'ec_option_details_main_color' );
			WP_EasyCart_Elementor_Checkout_Module::push_texts( $settings );
			echo '<div class="ec_cart_page">';
			echo '<div class="ec_cart_success_print_button_v2"><a href="#" onclick="return false;"><span class="dashicons dashicons-printer"></span>' . esc_html( $t( 'cart_success_print_receipt_text', 'Print Receipt', 'cart_success' ) ) . '</a></div>';
			echo '<div class="ec_order_success_row"><div class="ec_order_success_loader ec_order_success_loader_v2"><div class="ec_order_success_loader_loaded"><svg viewBox="0 0 161.2 161.2" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><circle class="ec_order_success_loader_loaded_path" fill="none" stroke="' . esc_attr( '' !== $color ? $color : '#222222' ) . '" stroke-width="4" cx="80.6" cy="80.6" r="62.1"/><polyline class="ec_order_success_loader_loaded_path" fill="none" stroke="' . esc_attr( '' !== $color ? $color : '#222222' ) . '" stroke-width="6" stroke-linecap="round" points="113,52.8 74.1,108.4 48.2,86.4 "/></svg></div></div>';
			echo '<div class="ec_order_success_column2"><p class="ec_cart_success_order_number ec_cart_success_order_number_v2">' . esc_html( $t( 'account_orders_details_order_number', 'Order Number', 'account_order_details' ) ) . ' #1024</p>';
			echo '<h2 class="ec_cart_success_title ec_cart_success_title_v2">' . esc_html( $t( 'cart_success_thank_you_title', 'Thank you for your order', 'cart_success' ) ) . '</h2>';
			echo '<p class="ec_cart_success_subtitle ec_cart_success_subtitle_v2">' . esc_html( $t( 'cart_success_will_receive_email', 'You will receive an email confirmation shortly at', 'cart_success' ) ) . ' shopper@example.com</p>';
			/* The order details link's address names order_details, as the real one does ( per-link switches find it by that ). */
			echo '<p class="ec_cart_success_continue_shopping_button ec_cart_success_continue_shopping_button_v2"><a href="#order_details" onclick="return false;">' . esc_html( $t( 'cart_payment_receipt_order_details_link', 'View Order Details', 'cart_success' ) ) . '</a> <a href="#" onclick="return false;">' . esc_html( $t( 'cart_continue_shopping', 'Continue Shopping', 'cart' ) ) . '</a></p>';
			echo '</div></div>';
			WP_EasyCart_Elementor_Checkout_Module::pop_texts();

			echo '<div class="wpec-thank-you__extras">';
			if ( ! empty( $settings['ec_message'] ) ) {
				echo '<div class="wpec-thank-you__message">' . wp_kses_post( wpautop( (string) $settings['ec_message'] ) ) . '</div>';
			}
			if ( $this->ec_on( $settings, 'ec_show_meta', false ) ) {
				WP_EasyCart_Elementor_Checkout_Module::print_order_meta(
					array(
						'date'     => date_i18n( get_option( 'date_format' ) ),
						'payment'  => __( 'Credit card', 'wp-easycart' ),
						'shipping' => __( 'Standard', 'wp-easycart' ),
					)
				);
			}
			if ( $this->ec_on( $settings, 'ec_show_payment' ) && get_option( 'ec_option_use_direct_deposit' ) ) {
				$bank = (string) wp_easycart_language()->convert_text( get_option( 'ec_option_direct_deposit_message' ) );
				echo '<section class="wpec-thank-you__section wpec-thank-you__payment"><h3 class="wpec-thank-you__heading">' . esc_html( $own( 'ec_payment_heading' ) ) . '</h3>';
				echo '<div class="wpec-thank-you__bank">' . nl2br( esc_html( '' !== trim( $bank ) ? $bank : __( 'Your bank transfer details show here.', 'wp-easycart' ) ) ) . '</div></section>';
			}
			if ( $this->ec_on( $settings, 'ec_show_items' ) ) {
				echo '<section class="wpec-thank-you__section wpec-thank-you__items"><h3 class="wpec-thank-you__heading">' . esc_html( $own( 'ec_items_heading' ) ) . '</h3><ul class="wpec-summary__items">';
				foreach ( $lines as $line ) {
					echo '<li class="wpec-summary__item"><span class="wpec-summary__image">' . ( '' !== $line['image'] ? '<img src="' . esc_url( $line['image'] ) . '" alt="" />' : '' ) . '</span><span class="wpec-summary__details"><span class="wpec-summary__title">' . esc_html( $line['title'] ) . '</span>' . ( '' !== $line['options'] ? '<span class="wpec-summary__options">' . esc_html( $line['options'] ) . '</span>' : '' ) . '<span class="wpec-summary__quantity">&times; ' . esc_html( (string) $line['quantity'] ) . '</span></span><span class="wpec-summary__price">' . esc_html( $line['total'] ) . '</span></li>';
				}
				echo '</ul></section>';
			}
			if ( $this->ec_on( $settings, 'ec_show_totals' ) ) {
				$shipping = 5;
				$tax      = round( $subtotal * 0.07, 2 );
				echo '<section class="wpec-thank-you__section wpec-thank-you__totals"><dl class="wpec-summary__totals">';
				foreach ( array(
					'subtotal' => array( $t( 'cart_payment_complete_order_totals_subtotal', 'Subtotal', 'cart_success' ), $subtotal ),
					'shipping' => array( $t( 'cart_payment_complete_order_totals_shipping', 'Shipping', 'cart_success' ), $shipping ),
					'tax'      => array( $t( 'cart_payment_complete_order_totals_tax', 'Tax', 'cart_success' ), $tax ),
					'total'    => array( $t( 'cart_payment_complete_order_totals_grand_total', 'Order Total', 'cart_success' ), $subtotal + $shipping + $tax ),
				) as $key => $row ) {
					echo '<div class="wpec-summary__line wpec-summary__line--' . esc_attr( $key ) . '"><dt>' . esc_html( $row[0] ) . '</dt><dd>' . esc_html( WP_EasyCart_Elementor_Checkout_Module::price( $row[1] ) ) . '</dd></div>';
				}
				echo '</dl></section>';
			}
			if ( $this->ec_on( $settings, 'ec_show_addresses' ) ) {
				echo '<section class="wpec-thank-you__section wpec-thank-you__addresses">';
				foreach ( array(
					'billing'  => $own( 'ec_billing_heading' ),
					'shipping' => $own( 'ec_shipping_heading' ),
				) as $type => $label ) {
					echo '<div class="wpec-thank-you__address wpec-thank-you__address--' . esc_attr( $type ) . '"><h3 class="wpec-thank-you__heading">' . esc_html( $label ) . '</h3><address>Anna Smith<br />12 Market Street<br />Springfield, IL 12345<br />United States</address></div>';
				}
				echo '</section>';
			}
			if ( $this->ec_on( $settings, 'ec_show_account', false ) ) {
				echo '<section class="wpec-thank-you__section wpec-thank-you__account"><p class="wpec-thank-you__account-text">' . esc_html( $own( 'ec_account_text' ) ) . '</p><a class="wpec-thank-you__button" href="#" onclick="return false;">' . esc_html( $own( 'ec_account_button' ) ) . '</a></section>';
			}
			echo '</div><div style="clear:both;"></div></div>';
		}
	}

endif;
