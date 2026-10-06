<?php
/**
 * Order Summary ( wp_easycart_order_summary, 6.0.2 ): the items and totals of the shopper's cart, for checkout page layouts
 * ( beside, above or below the Checkout widget, or on a page of its own ). It follows the checkout: whenever the cart page
 * changes the cart, shipping, tax or a code, checkout.js asks for the summary again ( wp_easycart_elementor_cart,
 * view=summary ), so its figures are the ones the checkout charges.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/trait-wp-easycart-elementor-checkout-controls.php';

if ( ! class_exists( 'WP_EasyCart_Elementor_Order_Summary_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Widget_Base' ) ) :

	/**
	 * Order Summary widget.
	 */
	class WP_EasyCart_Elementor_Order_Summary_Widget extends WP_EasyCart_Elementor_Widget_Base {

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
		protected static $ec_help = 'order-summary';

		/**
		 * Widget name ( stored in saved pages ).
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_order_summary';
		}

		/**
		 * Widget title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Order Summary', 'wp-easycart' );
		}

		/**
		 * Panel icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-price-list';
		}

		/**
		 * Search keywords.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array( 'order summary', 'cart totals', 'review order', 'checkout', 'totals' );
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

		/** Controls. */
		protected function register_controls() {
			$this->start_controls_section(
				'ec_summary_section',
				array(
					'label' => __( 'Order summary', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->add_control(
				'ec_summary_note',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => esc_html__( 'The products and totals in the shopper\'s cart, kept up to date while they check out. Nothing shows while the cart is empty.', 'wp-easycart' ),
					'content_classes' => 'elementor-descriptor',
				)
			);
			$this->add_control(
				'ec_show_heading',
				array(
					'label'        => __( 'Heading', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'label_on'     => __( 'Show', 'wp-easycart' ),
					'label_off'    => __( 'Hide', 'wp-easycart' ),
					'return_value' => 'yes',
					'default'      => 'yes',
				)
			);
			$this->add_control(
				'ec_heading',
				array(
					'label'       => __( 'Heading text', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'placeholder' => wp_strip_all_tags( WP_EasyCart_Elementor_Checkout_Module::text( 'order_summary', 'Order Summary', 'cart_onepage' ) ),
					'description' => __( 'Leave it empty for your store\'s own wording.', 'wp-easycart' ),
					'condition'   => array( 'ec_show_heading' => 'yes' ),
				)
			);
			$this->add_control(
				'ec_heading_tag',
				array(
					'label'     => __( 'Heading tag', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'default'   => 'h3',
					'options'   => array(
						'h2'  => 'H2',
						'h3'  => 'H3',
						'h4'  => 'H4',
						'h5'  => 'H5',
						'h6'  => 'H6',
						'div' => 'div',
						'p'   => 'p',
					),
					'condition' => array( 'ec_show_heading' => 'yes' ),
				)
			);
			foreach ( array(
				'ec_show_images'  => __( 'Product images', 'wp-easycart' ),
				'ec_show_options' => __( 'Product options', 'wp-easycart' ),
				'ec_show_totals'  => __( 'Totals', 'wp-easycart' ),
			) as $key => $label ) {
				$this->add_control(
					$key,
					array(
						'label'        => $label,
						'type'         => \Elementor\Controls_Manager::SWITCHER,
						'label_on'     => __( 'Show', 'wp-easycart' ),
						'label_off'    => __( 'Hide', 'wp-easycart' ),
						'return_value' => 'yes',
						'default'      => 'yes',
					)
				);
			}
			$this->add_control(
				'ec_show_edit',
				array(
					'label'        => __( '"Edit cart" link', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'label_on'     => __( 'Show', 'wp-easycart' ),
					'label_off'    => __( 'Hide', 'wp-easycart' ),
					'return_value' => 'yes',
					'default'      => '',
					'separator'    => 'before',
					'description'  => __( 'A link to your cart page under the summary.', 'wp-easycart' ),
				)
			);
			$this->add_control(
				'ec_edit_text',
				array(
					'label'       => __( 'Link text', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'placeholder' => wp_strip_all_tags( WP_EasyCart_Elementor_Checkout_Module::text( 'edit_cart', 'Edit cart', 'cart_onepage' ) ),
					'condition'   => array( 'ec_show_edit' => 'yes' ),
				)
			);
			$this->add_control(
				'ec_show_empty',
				array(
					'label'        => __( 'Message when the cart is empty', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'label_on'     => __( 'Show', 'wp-easycart' ),
					'label_off'    => __( 'Hide', 'wp-easycart' ),
					'return_value' => 'yes',
					'default'      => '',
					'description'  => __( 'Otherwise nothing shows while the cart is empty.', 'wp-easycart' ),
				)
			);
			$this->add_control(
				'ec_empty_message',
				array(
					'label'       => __( 'Message', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'placeholder' => wp_strip_all_tags( WP_EasyCart_Elementor_Checkout_Module::text( 'cart_empty_cart', 'There are no items in your cart.', 'cart' ) ),
					'condition'   => array( 'ec_show_empty' => 'yes' ),
				)
			);
			$this->add_control(
				'ec_sticky',
				array(
					'label'        => __( 'Keep it in view while scrolling', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'return_value' => 'yes',
					'default'      => '',
					'separator'    => 'before',
					'description'  => __( 'In a column taller than the summary, beside the checkout.', 'wp-easycart' ),
					'selectors'    => array( '{{WRAPPER}}' => 'position: sticky; top: var(--wpec-el-sticky-top, 24px); align-self: flex-start; z-index: 2;' ),
				)
			);
			$this->add_responsive_control(
				'ec_sticky_top',
				array(
					'label'      => __( 'Distance from the top', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 200,
						),
					),
					'selectors'  => array( '{{WRAPPER}}' => '--wpec-el-sticky-top: {{SIZE}}{{UNIT}};' ),
					'condition'  => array( 'ec_sticky' => 'yes' ),
				)
			);
			$this->end_controls_section();

			$this->ec_register_totals_controls();

			$this->ec_box_section( 'box', __( 'Box', 'wp-easycart' ), '.wpec-summary' );
			$this->ec_text_section(
				'heading',
				__( 'Heading', 'wp-easycart' ),
				'.wpec-summary__heading',
				array(
					'typography' => \Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_PRIMARY,
					'color'      => \Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_PRIMARY,
					'spacing'    => true,
					'align'      => true,
					'condition'  => array( 'ec_show_heading' => 'yes' ),
				)
			);
			$this->start_controls_section(
				'items_style',
				array(
					'label' => __( 'Products', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
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
							'max' => 160,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-summary__image' => 'width: {{SIZE}}{{UNIT}}; flex-basis: {{SIZE}}{{UNIT}};' ),
					'condition'  => array( 'ec_show_images' => 'yes' ),
				)
			);
			$this->add_responsive_control(
				'items_image_radius',
				array(
					'label'      => __( 'Image border radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px', '%' ),
					'selectors'  => array( '{{WRAPPER}} .wpec-summary__image img' => 'border-radius: {{SIZE}}{{UNIT}};' ),
					'condition'  => array( 'ec_show_images' => 'yes' ),
				)
			);
			$this->add_control(
				'items_title_heading',
				array(
					'label'     => __( 'Product name', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->ec_text_controls( 'items_title', '.wpec-summary__title', array( 'color' => \Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_PRIMARY ) );
			$this->add_control(
				'items_meta_heading',
				array(
					'label'     => __( 'Options and quantity', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->ec_text_controls( 'items_meta', '.wpec-summary__options, .wpec-summary__quantity', array( 'color' => '' ) );
			$this->add_control(
				'items_price_heading',
				array(
					'label'     => __( 'Price', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->ec_text_controls( 'items_price', '.wpec-summary__price' );
			$this->ec_divider_control( 'items_divider', '.wpec-summary__item', 'bottom' );
			$this->ec_divider_style_controls( 'items_divider', '.wpec-summary__item', 'bottom' );
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
					'selectors'  => array( '{{WRAPPER}} .wpec-summary__item' => 'padding-top: {{SIZE}}{{UNIT}}; padding-bottom: {{SIZE}}{{UNIT}};' ),
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
					'selectors'  => array( '{{WRAPPER}} .wpec-summary__item' => 'gap: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->end_controls_section();

			$this->start_controls_section(
				'totals_style',
				array(
					'label'     => __( 'Totals', 'wp-easycart' ),
					'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
					'condition' => array( 'ec_show_totals' => 'yes' ),
				)
			);
			$this->ec_text_controls( 'totals', '.wpec-summary__line' );
			/* Round 11: the line above the grand total is that line's own border ( the totals list has none ). */
			$this->ec_divider_control( 'totals_divider', '.wpec-summary__totals', 'top', array( '.wpec-summary__line--total' => 'border-top-color: {{VALUE}} !important;' ) );
			$this->ec_summary_totals_controls( 'totals' );
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
				array( 'typography' => \Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_PRIMARY )
			);
			$this->end_controls_section();

			$this->ec_text_section(
				'edit',
				__( '"Edit cart" link', 'wp-easycart' ),
				'.wpec-summary__edit',
				array(
					'hover'     => true,
					'align'     => true,
					'color'     => '',
					'condition' => array( 'ec_show_edit' => 'yes' ),
				)
			);
			$this->ec_text_section(
				'empty_message',
				__( 'Empty cart message', 'wp-easycart' ),
				'.wpec-summary__empty',
				array(
					'align'     => true,
					'color'     => '',
					'condition' => array( 'ec_show_empty' => 'yes' ),
				)
			);
		}

		/**
		 * Totals lines ( WP EasyCart PRO, as on the Checkout widget ): locked here ( round 11 ).
		 */
		protected function ec_register_totals_controls() {
			$this->ec_locked_section(
				'ec_totals_section',
				__( 'Totals lines', 'wp-easycart' ),
				'checkout_sections',
				__( 'Choose which lines the summary shows: hide the shipping or tax line, as the Checkout widget can. The grand total always shows.', 'wp-easycart' ),
				array( __( 'Shipping line', 'wp-easycart' ), __( 'Tax lines', 'wp-easycart' ) )
			);
		}

		/**
		 * More classes ( WP EasyCart PRO: hidden totals lines ).
		 *
		 * @param array $settings Settings.
		 * @return string[]
		 */
		protected function ec_extra_classes( $settings ) {
			unset( $settings );
			return array();
		}

		/**
		 * Classes on the summary box.
		 *
		 * @param array $settings Settings.
		 * @return string
		 */
		protected function ec_classes( $settings ) {
			$classes = array( 'wpec-el', 'wpec-summary' );
			foreach ( array(
				'ec_show_images'  => 'images',
				'ec_show_options' => 'options',
				'ec_show_totals'  => 'totals',
			) as $key => $part ) {
				if ( ! $this->ec_on( $settings, $key ) ) {
					$classes[] = 'wpec-summary--no-' . $part;
				}
			}
			return implode( ' ', array_merge( $classes, $this->ec_extra_classes( $settings ) ) );
		}

		/**
		 * The heading.
		 *
		 * @param array $settings Settings.
		 */
		protected function ec_heading( $settings ) {
			if ( ! $this->ec_on( $settings, 'ec_show_heading' ) ) {
				return;
			}
			$heading = ( isset( $settings['ec_heading'] ) && '' !== trim( (string) $settings['ec_heading'] ) ) ? (string) $settings['ec_heading'] : wp_strip_all_tags( WP_EasyCart_Elementor_Checkout_Module::text( 'order_summary', 'Order Summary', 'cart_onepage' ) );
			$tag     = ( isset( $settings['ec_heading_tag'] ) && in_array( $settings['ec_heading_tag'], array( 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'p' ), true ) ) ? $settings['ec_heading_tag'] : 'h3';
			echo '<' . tag_escape( $tag ) . ' class="wpec-summary__heading">' . esc_html( $heading ) . '</' . tag_escape( $tag ) . '>';
		}

		/**
		 * The "Edit cart" link and the empty cart message ( outside the part the checkout redraws ).
		 *
		 * @param array $settings Settings.
		 * @param bool  $is_empty The cart is empty now ( the message shows ).
		 */
		protected function ec_after_body( $settings, $is_empty ) {
			if ( $this->ec_on( $settings, 'ec_show_empty', false ) ) {
				$message = ( isset( $settings['ec_empty_message'] ) && '' !== trim( (string) $settings['ec_empty_message'] ) ) ? (string) $settings['ec_empty_message'] : wp_strip_all_tags( WP_EasyCart_Elementor_Checkout_Module::text( 'cart_empty_cart', 'There are no items in your cart.', 'cart' ) );
				echo '<p class="wpec-summary__empty"' . ( $is_empty ? '' : ' hidden' ) . '>' . esc_html( $message ) . '</p>';
			}
			if ( $this->ec_on( $settings, 'ec_show_edit', false ) ) {
				$text = ( isset( $settings['ec_edit_text'] ) && '' !== trim( (string) $settings['ec_edit_text'] ) ) ? (string) $settings['ec_edit_text'] : wp_strip_all_tags( WP_EasyCart_Elementor_Checkout_Module::text( 'edit_cart', 'Edit cart', 'cart_onepage' ) );
				echo '<p class="wpec-summary__edit-row"' . ( $is_empty ? ' hidden' : '' ) . '><a class="wpec-summary__edit" href="' . esc_url( WP_EasyCart_Elementor_Checkout_Module::cart_url() ) . '">' . esc_html( $text ) . '</a></p>';
			}
		}

		/** Draw. */
		protected function render() {
			$settings = $this->get_settings_for_display();
			if ( $this->ec_is_editor() ) {
				echo '<div class="' . esc_attr( $this->ec_classes( $settings ) ) . '">';
				$this->ec_heading( $settings );
				$this->ec_render_sample();
				$this->ec_after_body( $settings, false );
				echo '</div>';
				return;
			}
			if ( 'confirmation' === WP_EasyCart_Elementor_Checkout_Module::state() && WP_EasyCart_Elementor_Checkout_Module::is_cart_page() ) {
				return;
			}
			WP_EasyCart_Elementor_Checkout_Module::no_cache();
			/* checkout.js fills the summary after the page loads, and removes it for an empty cart ( or shows its message ). 6.0.3:
			 * always away from the cart page, where the HTML must never hold a visitor's cart ( a cache may ignore the no-cache signal ). */
			if ( get_option( 'ec_option_cache_prevent' ) || ! WP_EasyCart_Elementor_Checkout_Module::is_cart_page() ) {
				echo '<div class="' . esc_attr( $this->ec_classes( $settings ) ) . '" data-wpec-summary="1" data-wpec-summary-load="1" hidden>';
				$this->ec_heading( $settings );
				echo '<div class="wpec-summary__body" aria-live="polite"></div>';
				$this->ec_after_body( $settings, false );
				echo '</div>';
				return;
			}
			if ( ! class_exists( 'ec_cartpage' ) || ! isset( $GLOBALS['ec_cart_data'] ) ) {
				return;
			}
			$cartpage = new ec_cartpage();
			if ( (int) $cartpage->cart->total_items <= 0 ) {
				if ( $this->ec_on( $settings, 'ec_show_empty', false ) ) {
					echo '<div class="' . esc_attr( $this->ec_classes( $settings ) ) . '" data-wpec-summary="1">';
					$this->ec_heading( $settings );
					echo '<div class="wpec-summary__body" aria-live="polite"></div>';
					$this->ec_after_body( $settings, true );
					echo '</div>';
				}
				return;
			}
			echo '<div class="' . esc_attr( $this->ec_classes( $settings ) ) . '" data-wpec-summary="1">';
			$this->ec_heading( $settings );
			echo '<div class="wpec-summary__body" aria-live="polite">' . WP_EasyCart_Elementor_Checkout_Module::summary_body( $cartpage ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- summary_body() escapes every value.
			$this->ec_after_body( $settings, false );
			echo '</div>';
		}

		/** The editor's sample. */
		protected function ec_render_sample() {
			$lines    = WP_EasyCart_Elementor_Checkout_Module::sample_lines( 2 );
			$subtotal = 0;
			echo '<div class="wpec-summary__body"><ul class="wpec-summary__items">';
			foreach ( $lines as $line ) {
				$subtotal += $line['amount'];
				echo '<li class="wpec-summary__item"><span class="wpec-summary__image">' . ( '' !== $line['image'] ? '<img src="' . esc_url( $line['image'] ) . '" alt="" />' : '' ) . '</span><span class="wpec-summary__details"><span class="wpec-summary__title">' . esc_html( $line['title'] ) . '</span>';
				if ( '' !== $line['options'] ) {
					echo '<span class="wpec-summary__options">' . esc_html( $line['options'] ) . '</span>';
				}
				echo '<span class="wpec-summary__quantity">&times; ' . esc_html( (string) $line['quantity'] ) . '</span></span><span class="wpec-summary__price">' . esc_html( $line['total'] ) . '</span></li>';
			}
			echo '</ul><dl class="wpec-summary__totals">';
			$shipping = 5;
			$tax      = round( $subtotal * 0.07, 2 );
			$rows     = array(
				'subtotal' => array( WP_EasyCart_Elementor_Checkout_Module::text( 'cart_totals_subtotal', 'Cart Subtotal', 'cart_totals' ), $subtotal ),
				'shipping' => array( WP_EasyCart_Elementor_Checkout_Module::text( 'cart_totals_shipping', 'Shipping', 'cart_totals' ), $shipping ),
				'tax'      => array( WP_EasyCart_Elementor_Checkout_Module::text( 'cart_totals_tax', 'Tax', 'cart_totals' ), $tax ),
				'total'    => array( WP_EasyCart_Elementor_Checkout_Module::text( 'cart_totals_grand_total', 'Grand Total', 'cart_totals' ), $subtotal + $shipping + $tax ),
			);
			foreach ( $rows as $key => $row ) {
				echo '<div class="wpec-summary__line wpec-summary__line--' . esc_attr( $key ) . '"><dt>' . esc_html( wp_strip_all_tags( $row[0] ) ) . '</dt><dd>' . esc_html( WP_EasyCart_Elementor_Checkout_Module::price( $row[1] ) ) . '</dd></div>';
			}
			echo '</dl></div>';
		}
	}

endif;
