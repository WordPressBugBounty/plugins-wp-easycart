<?php
/**
 * Cart ( wp_easycart_cart, 6.0.2 ): the shopper's cart, EasyCart's own, with its markup, order and behaviour, styled here.
 *
 * On the cart page it is the cart page itself ( load_ec_cart(): the Stripe / redirect returns, notices and every checkout
 * step when no Checkout widget is on the page ). Anywhere else it draws the same cart ( ec_cartpage::display_cart_contents() )
 * with its Checkout button. The coupon, gift card, shipping estimate and continue shopping parts can be hidden; WP EasyCart
 * PRO adds the totals lines ( WP_EasyCart_Elementor_Cart_Widget_Pro, same name ).
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/trait-wp-easycart-elementor-checkout-controls.php';

if ( ! class_exists( 'WP_EasyCart_Elementor_Cart_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Widget_Base' ) ) :

	/**
	 * Cart widget.
	 */
	class WP_EasyCart_Elementor_Cart_Widget extends WP_EasyCart_Elementor_Widget_Base {

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
		protected static $ec_help = 'cart';

		/**
		 * Widget name ( stored in saved pages ).
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_cart';
		}

		/**
		 * Widget title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Cart', 'wp-easycart' );
		}

		/**
		 * Panel icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-woo-cart';
		}

		/**
		 * Search keywords.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array( 'cart', 'basket', 'shopping cart', 'coupon', 'woocommerce cart' );
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
		 * Saved into post_content: EasyCart loads its cart scripts ( payment SDKs ) for pages that carry [ec_cart] ( once per
		 * document, shared with the Checkout widget ).
		 *
		 * @return string
		 */
		protected function plain_content_shortcode() {
			return WP_EasyCart_Elementor_Checkout_Module::plain_cart_shortcode();
		}

		/** Controls. */
		protected function register_controls() {
			$this->start_controls_section(
				'ec_cart_section',
				array(
					'label' => __( 'Cart', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->add_control(
				'ec_cart_note',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => esc_html__( 'Shows the shopper\'s cart. On your cart page it starts the checkout; on any other page it shows the same cart with a Checkout button. The switches below change the cart only: the checkout always shows every part.', 'wp-easycart' ),
					'content_classes' => 'elementor-descriptor',
				)
			);
			$parts = array(
				'ec_show_coupon'   => array( __( 'Coupon box', 'wp-easycart' ), __( 'Shown while coupons are on in Settings › Checkout.', 'wp-easycart' ) ),
				'ec_show_giftcard' => array( __( 'Gift card box', 'wp-easycart' ), __( 'Shown while gift cards are on in Settings › Checkout.', 'wp-easycart' ) ),
				'ec_show_estimate' => array( __( 'Shipping estimate', 'wp-easycart' ), __( 'Shown while the estimate is on in Settings › Shipping.', 'wp-easycart' ) ),
				'ec_show_continue' => array( __( 'Continue shopping button', 'wp-easycart' ), '' ),
				'ec_show_steps'    => array( __( 'Checkout steps', 'wp-easycart' ), __( 'The Cart › Details › Payment line above the cart.', 'wp-easycart' ) ),
			);
			foreach ( $parts as $key => $part ) {
				$control = array(
					'label'        => $part[0],
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'label_on'     => __( 'Show', 'wp-easycart' ),
					'label_off'    => __( 'Hide', 'wp-easycart' ),
					'return_value' => 'yes',
					'default'      => 'yes',
				);
				if ( '' !== $part[1] ) {
					$control['description'] = $part[1];
				}
				$this->add_control( $key, $control );
			}
			$this->add_control(
				'ec_lines_heading',
				array(
					'label'     => __( 'Product lines', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			foreach ( $this->ec_line_parts() as $key => $part ) {
				$this->add_control(
					$key,
					array(
						'label'        => $part[0],
						'type'         => \Elementor\Controls_Manager::SWITCHER,
						'label_on'     => __( 'Show', 'wp-easycart' ),
						'label_off'    => __( 'Hide', 'wp-easycart' ),
						'return_value' => 'yes',
						'default'      => 'yes',
						'description'  => $part[1],
					)
				);
			}
			$this->add_control(
				'ec_title_link',
				array(
					'label'        => __( 'Product names link to the product', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'return_value' => 'yes',
					'default'      => 'yes',
				)
			);
			$this->add_control(
				'ec_show_express',
				array(
					'label'        => __( 'Express buttons', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'label_on'     => __( 'Show', 'wp-easycart' ),
					'label_off'    => __( 'Hide', 'wp-easycart' ),
					'return_value' => 'yes',
					'default'      => 'yes',
					'description'  => __( 'Apple Pay, Google Pay and PayPal buttons beside the cart totals, when your payment settings show them. Shoppers can still pay with them at checkout.', 'wp-easycart' ),
				)
			);
			$this->add_control(
				'ec_after_template',
				array(
					'label'       => __( 'Below the cart', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'options'     => $this->ec_saved_templates(),
					'default'     => '',
					'separator'   => 'before',
					'description' => __( 'A saved template shown under the cart ( not in the checkout steps ), for example a trust badge row or cross-sells.', 'wp-easycart' ),
				)
			);
			$this->add_control(
				'ec_preview',
				array(
					'label'       => __( 'Show in the editor', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'default'     => 'items',
					'options'     => array(
						'items' => __( 'A cart with products', 'wp-easycart' ),
						'empty' => __( 'The empty cart', 'wp-easycart' ),
					),
					'separator'   => 'before',
					'description' => __( 'Only while you edit. Visitors see their own cart.', 'wp-easycart' ),
				)
			);
			$this->end_controls_section();

			$this->ec_empty_cart_controls();
			$this->ec_register_totals_controls();
			$this->ec_text_override_section(
				'ec_texts_section',
				__( 'Texts', 'wp-easycart' ),
				array( 'cart_header_column1', 'cart_header_column3', 'cart_header_column4', 'cart_header_column5', 'cart_checkout', 'cart_continue_shopping', 'cart_totals_label', 'cart_coupon_title', 'cart_enter_coupon', 'cart_apply_coupon', 'cart_gift_card_title', 'cart_enter_gift_code', 'cart_redeem_gift_card', 'cart_estimate_shipping_button', 'checkout_note', 'remove', 'cart_title', 'cart_checkout_details_title', 'cart_submit_payment_title' )
			);
			$this->ec_register_style_controls();
		}

		/**
		 * Parts of each product line that can be hidden: control => array( label, description ).
		 *
		 * @return array
		 */
		protected function ec_line_parts() {
			return array(
				'ec_show_image'   => array( __( 'Image', 'wp-easycart' ), '' ),
				'ec_show_options' => array( __( 'Options', 'wp-easycart' ), __( 'The choices under the product name ( size, colour, gift card details ).', 'wp-easycart' ) ),
				'ec_show_price'   => array( __( 'Unit price', 'wp-easycart' ), __( 'The price column; the line total stays.', 'wp-easycart' ) ),
				'ec_show_remove'  => array( __( 'Remove', 'wp-easycart' ), __( 'The remove button of each line.', 'wp-easycart' ) ),
			);
		}

		/**
		 * Totals lines ( WP EasyCart PRO ): locked here.
		 */
		protected function ec_register_totals_controls() {
			$this->ec_locked_section(
				'ec_totals_section',
				__( 'Totals lines', 'wp-easycart' ),
				'checkout_sections',
				__( 'Choose which lines the cart totals show: hide the shipping, tax or grand total line until the checkout works them out. The checkout always shows them.', 'wp-easycart' ),
				array( __( 'Shipping line', 'wp-easycart' ), __( 'Tax line', 'wp-easycart' ), __( 'Grand total line', 'wp-easycart' ) )
			);
		}

		/** Style tab. */
		protected function ec_register_style_controls() {
			/* Layout */
			$this->start_controls_section(
				'layout_style',
				array(
					'label' => __( 'Layout', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->add_responsive_control(
				'layout_totals_width',
				array(
					'label'       => __( 'Totals column width', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SLIDER,
					'size_units'  => array( '%' ),
					'range'       => array(
						'%' => array(
							'min' => 20,
							'max' => 100,
						),
					),
					'description' => __( '100% puts the totals under the cart.', 'wp-easycart' ),
					'selectors'   => array(
						'{{WRAPPER}} .ec_cart_right' => 'width: {{SIZE}}%;',
						'{{WRAPPER}} .ec_cart_left'  => 'width: calc( 100% - {{SIZE}}% );',
					),
				)
			);
			$this->add_responsive_control(
				'layout_gap',
				array(
					'label'      => __( 'Space between columns', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 80,
						),
					),
					'selectors'  => array(
						'{{WRAPPER}} .ec_cart_left'  => 'padding-right: {{SIZE}}{{UNIT}};',
						'{{WRAPPER}} .ec_cart_right' => 'padding-left: {{SIZE}}{{UNIT}};',
					),
				)
			);
			$this->ec_divider_control( 'layout_divider_color', '.ec_cart_left', 'right' );
			$this->add_responsive_control(
				'layout_stack',
				array(
					'label'                => __( 'Totals', 'wp-easycart' ),
					'type'                 => \Elementor\Controls_Manager::SELECT,
					'default'              => '',
					'options'              => array(
						''        => __( 'Beside the cart', 'wp-easycart' ),
						'stacked' => __( 'Under the cart', 'wp-easycart' ),
					),
					'selectors_dictionary' => array(
						'stacked' => 'float: none; width: 100% !important; padding-left: 0; padding-right: 0; border-right-width: 0;',
					),
					'selectors'            => array( '{{WRAPPER}} .ec_cart_left, {{WRAPPER}} .ec_cart_right' => '{{VALUE}}' ),
					'separator'            => 'before',
				)
			);
			$this->add_control(
				'layout_sticky',
				array(
					'label'        => __( 'Keep the totals in view', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'return_value' => 'yes',
					'description'  => __( 'While shoppers scroll a long cart, with the totals beside it.', 'wp-easycart' ),
					'selectors'    => array(
						'{{WRAPPER}} section.ec_cart_page' => 'display: flow-root;',
						'{{WRAPPER}} .ec_cart_right'       => 'position: sticky; top: var(--wpec-el-sticky-top, 24px);',
					),
				)
			);
			$this->add_responsive_control(
				'layout_sticky_top',
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
					'condition'  => array( 'layout_sticky' => 'yes' ),
				)
			);
			$this->end_controls_section();

			/* Checkout steps ( round 11: the colour reaches the steps, as on the Checkout widget ) */
			$this->start_controls_section(
				'steps_style',
				array(
					'label'     => __( 'Checkout steps', 'wp-easycart' ),
					'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
					'condition' => array( 'ec_show_steps' => 'yes' ),
				)
			);
			$this->ec_text_controls(
				'steps',
				'.ec_cart_breadcrumb, .ec_cart_breadcrumb_item_v2',
				array(
					'font_var'  => true,
					'color_var' => 'wpec-el-steps-color',
					'hover'     => true,
					'important' => array( 'font_size' ),
				)
			);
			$this->ec_steps_extra_controls();
			$this->end_controls_section();

			/* Table header */
			$this->start_controls_section(
				'table_head_style',
				array(
					'label' => __( 'Table header', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->ec_text_controls( 'table_head', '.ec_cart > thead > tr > th, .ec_cart_table_headers > div', array( 'font_var' => true ) );
			$this->add_control(
				'table_head_background',
				array(
					'label'     => __( 'Background color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .ec_cart > thead > tr > th, {{WRAPPER}} .ec_cart_table_headers' => 'background-color: {{VALUE}};' ),
				)
			);
			$this->ec_divider_control( 'table_head_divider', '.ec_cart > thead, .ec_cart_table_headers', 'bottom' );
			$this->end_controls_section();

			/* Products */
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
							'min' => 30,
							'max' => 240,
						),
					),
					'selectors'  => array(
						'{{WRAPPER}} td.ec_cartitem_image' => 'width: {{SIZE}}{{UNIT}};',
						'{{WRAPPER}} .ec_cartitem_image > img, {{WRAPPER}} .ec_cart_table_image > img' => 'width: {{SIZE}}{{UNIT}}; min-width: {{SIZE}}{{UNIT}};',
						'{{WRAPPER}} .ec_cart_table_image' => 'width: auto; min-width: {{SIZE}}{{UNIT}};',
					),
				)
			);
			$this->add_responsive_control(
				'items_image_radius',
				array(
					'label'      => __( 'Image border radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px', '%' ),
					'selectors'  => array( '{{WRAPPER}} .ec_cartitem_image > img, {{WRAPPER}} .ec_cart_table_image > img' => 'border-radius: {{SIZE}}{{UNIT}};' ),
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
			$this->ec_text_controls(
				'items_title',
				'.ec_cartitem_title',
				array(
					'typography' => \Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_PRIMARY,
					'color'      => \Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_PRIMARY,
					'color_var'  => 'wpec-el-title-color',
					'font_var'   => true,
					'hover'      => true,
				)
			);
			$this->add_control(
				'items_details_heading',
				array(
					'label'     => __( 'Options and notes', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->ec_text_controls( 'items_details', 'td.ec_cartitem_details > dl > dt, .ec_cart_table_details_content dl, .ec_cart_backorder_date', array( 'font_var' => true ) );
			$this->add_control(
				'items_price_heading',
				array(
					'label'     => __( 'Prices', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->ec_text_controls( 'items_price', 'td.ec_cartitem_price, td.ec_cartitem_total, .ec_cart_table_price, .ec_cart_table_total', array( 'font_var' => true ) );
			$this->add_control(
				'items_rows_heading',
				array(
					'label'     => __( 'Rows', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->add_control(
				'items_row_background',
				array(
					'label'     => __( 'Background color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .ec_cart > tbody > tr.ec_cartitem_row > td, {{WRAPPER}} .ec_cart_table_row' => 'background-color: {{VALUE}};' ),
				)
			);
			/* Round 11: the line is the row's own border ( its cells have none ); the one-page cart's rows get one between them. */
			$this->ec_divider_control( 'items_row_divider', '.ec_cart > tbody > tr.ec_cartitem_row', 'bottom', array( '.ec_cart_table_row ~ .ec_cart_table_row' => 'border-top: 1px solid {{VALUE}};' ) );
			$this->ec_divider_style_controls( 'items_row_divider', '.ec_cart > tbody > tr.ec_cartitem_row', 'bottom' );
			$this->add_responsive_control(
				'items_row_padding',
				array(
					'label'      => __( 'Row spacing', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 60,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .ec_cart > tbody > tr.ec_cartitem_row > td, {{WRAPPER}} .ec_cart_table_row' => 'padding-top: {{SIZE}}{{UNIT}} !important; padding-bottom: {{SIZE}}{{UNIT}} !important;' ),
				)
			);
			$this->end_controls_section();

			/* Quantity and remove */
			$this->start_controls_section(
				'quantity_style',
				array(
					'label' => __( 'Quantity and remove', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->ec_typography( 'quantity_typography', '.ec_cartitem_quantity_table .ec_quantity, .ec_table_quantity_box', null, true, array( 'font_size' ) );
			$this->add_control(
				'quantity_field_color',
				array(
					'label'     => __( 'Quantity text color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .ec_cartitem_quantity_table .ec_quantity, {{WRAPPER}} .ec_table_quantity_box' => 'color: {{VALUE}} !important;' ),
				)
			);
			$this->add_control(
				'quantity_field_border',
				array(
					'label'     => __( 'Quantity border color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .ec_cartitem_quantity_table .ec_quantity, {{WRAPPER}} .ec_table_quantity_box' => 'border-color: {{VALUE}} !important;' ),
				)
			);
			$this->ec_quantity_box_controls( 'quantity', '.ec_cartitem_quantity_table .ec_quantity, .ec_table_quantity_box' );
			$this->add_control(
				'quantity_buttons_heading',
				array(
					'label'     => __( '+ and − buttons', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			foreach ( array(
				'normal' => '',
				'hover'  => '-hover',
			) as $state => $suffix ) {
				$this->add_control(
					'quantity_buttons_bg' . ( '' === $suffix ? '' : '_hover' ),
					array(
						'label'     => ( '' === $suffix ) ? __( 'Background color', 'wp-easycart' ) : __( 'Hover background color', 'wp-easycart' ),
						'type'      => \Elementor\Controls_Manager::COLOR,
						'selectors' => array( '{{WRAPPER}}' => '--wpec-el-qty-bg' . $suffix . ': {{VALUE}};' ),
					)
				);
			}
			$this->add_control(
				'quantity_buttons_color',
				array(
					'label'     => __( 'Symbol color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}}' => '--wpec-el-qty-color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'quantity_remove_heading',
				array(
					'label'     => __( 'Remove', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->add_control(
				'quantity_remove_color',
				array(
					'label'     => __( 'Color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}}' => '--wpec-el-remove-color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'quantity_remove_hover_color',
				array(
					'label'     => __( 'Hover color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}}' => '--wpec-el-remove-hover-color: {{VALUE}};' ),
				)
			);
			$this->ec_remove_icon_controls( 'quantity_remove' );
			$this->end_controls_section();

			/* Totals */
			$this->start_controls_section(
				'totals_box_style',
				array(
					'label' => __( 'Totals box', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->ec_box_controls( 'totals_box', '.ec_cart_right, .ec_cart_table_subtotal_row' );
			$this->end_controls_section();

			$this->ec_text_section(
				'headings',
				__( 'Box headings', 'wp-easycart' ),
				'.ec_cart_header',
				array(
					'typography' => \Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_PRIMARY,
					'color'      => \Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_PRIMARY,
					'font_var'   => true,
					'spacing'    => true,
				)
			);
			$this->start_controls_section(
				'headings_line_style',
				array(
					'label' => __( 'Totals lines', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->add_control(
				'lines_label_heading',
				array(
					'label' => __( 'Lines', 'wp-easycart' ),
					'type'  => \Elementor\Controls_Manager::HEADING,
				)
			);
			$this->ec_text_controls( 'lines', '.ec_cart_price_row_label, .ec_cart_price_row_total, .ec_cart_table_subtotal', array( 'font_var' => true ) );
			$this->ec_divider_control( 'lines_divider', '.ec_cart_price_row, .ec_cart_header', 'bottom' );
			$this->ec_totals_split_controls( 'lines', '.ec_cart_price_row_label, .ec_cart_table_subtotal_label', '.ec_cart_price_row_total, .ec_cart_table_subtotal_amount' );
			$this->add_control(
				'grand_total_heading',
				array(
					'label'     => __( 'Grand total', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			/* Round 11: #ec_cart_total ( EasyCart's bold weight by id ) is named too, so the weight applies. */
			$this->ec_text_controls(
				'grand_total',
				'.ec_cart_price_row.ec_order_total .ec_cart_price_row_label, .ec_cart_price_row.ec_order_total .ec_cart_price_row_total, #ec_cart_total, .ec_cart_table_subtotal_amount',
				array(
					'typography' => \Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_PRIMARY,
					'font_var'   => true,
				)
			);
			$this->end_controls_section();

			$this->ec_fields_section(
				'fields',
				__( 'Coupon and gift card fields', 'wp-easycart' ),
				'',
				'.ec_cart_input_row input, .ec_cart_input_row select',
				array(),
				array( 'rows' => '.ec_cart_input_row' )
			);
			$this->ec_button_section(
				'checkout_button',
				__( 'Checkout button', 'wp-easycart' ),
				'.ec_cart_button_checkout, .ec_cart_table_checkout_button',
				array(
					'vars'     => 'cta',
					'font_var' => true,
					'width'    => true,
				)
			);
			$this->ec_button_section(
				'buttons',
				__( 'Other buttons', 'wp-easycart' ),
				'.ec_cart_button:not(.ec_cart_button_checkout), .ec_cart_button_row input[type="button"]',
				array(
					'vars'       => 'btn',
					'font_var'   => true,
					'background' => '',
					'working'    => '.ec_cart_button_working',
				)
			);
			$this->ec_text_section(
				'continue',
				__( 'Continue shopping link', 'wp-easycart' ),
				'.ec_cart_table_continue_shopping',
				array(
					'hover'     => true,
					'font_var'  => true,
					'condition' => array( 'ec_show_continue' => 'yes' ),
				)
			);
			$this->ec_notices_section( '.ec_cart_error > div, .ec_cart_error_message, .ec_cartitem_error_row > td, .ec_cart_error_line_item', '.ec_cart_success > div, .ec_cart_success_message' );
			$this->ec_empty_style_sections();
		}

		/**
		 * Where the widget's hide switches apply ( round 11 ):
		 * - 'all': the cart on its own ( another page ), the cart state of the classic checkout, and the editor's classic sample;
		 * - 'onepage': the cart page of a one-page store, where the cart and the checkout share one page and one wrapper: only
		 *   the parts that exist nowhere but in the cart ( continue shopping, the product lines' parts );
		 * - 'none': a classic checkout step this widget draws ( no Checkout widget on the page ): the checkout shows every part.
		 *
		 * @return string
		 */
		protected function ec_hide_scope() {
			$editor    = $this->ec_is_editor();
			$cart_page = $editor ? WP_EasyCart_Elementor_Checkout_Module::editing_cart_page() : WP_EasyCart_Elementor_Checkout_Module::is_cart_page();
			if ( ! $cart_page ) {
				return 'all';
			}
			if ( function_exists( 'wp_easycart_onepage_active' ) && wp_easycart_onepage_active() ) {
				return 'onepage';
			}
			if ( $editor ) {
				return 'all';
			}
			return ( 'cart' === WP_EasyCart_Elementor_Checkout_Module::state() ) ? 'all' : 'none';
		}

		/**
		 * Whether every hide switch applies ( see ec_hide_scope() ).
		 *
		 * @return bool
		 */
		protected function ec_hides_apply() {
			return 'all' === $this->ec_hide_scope();
		}

		/**
		 * Classes on the widget's box: what it hides ( ec_hide_scope() ).
		 *
		 * @param array $settings Settings.
		 * @return string[]
		 */
		protected function ec_wrapper_classes( $settings ) {
			$classes = array( 'wpec-el', 'wpec-el-cart-page', 'wpec-cart' );
			$scope   = $this->ec_hide_scope();
			if ( 'none' === $scope ) {
				return $classes;
			}
			$parts = array(
				'ec_show_coupon'   => 'coupon',
				'ec_show_giftcard' => 'giftcard',
				'ec_show_estimate' => 'estimate',
				'ec_show_continue' => 'continue',
				'ec_show_steps'    => 'steps',
				'ec_show_express'  => 'cart-express',
			);
			if ( 'onepage' === $scope ) {
				$parts = array( 'ec_show_continue' => 'continue' );
			}
			foreach ( $this->ec_line_parts() as $key => $part ) {
				$parts[ $key ] = str_replace( 'ec_show_', 'line-', $key );
			}
			foreach ( $parts as $key => $part ) {
				if ( ! $this->ec_on( $settings, $key ) ) {
					$classes[] = 'wpec-hide-' . $part;
				}
			}
			if ( ! $this->ec_on( $settings, 'ec_title_link' ) ) {
				$classes[] = 'wpec-cart--no-title-link';
			}
			if ( 'onepage' === $scope ) {
				return $classes;
			}
			return array_merge( $classes, $this->ec_extra_classes( $settings ) );
		}

		/**
		 * More classes ( WP EasyCart PRO: hidden totals lines ), asked only while every hide applies ( ec_hides_apply() ).
		 *
		 * @param array $settings Settings.
		 * @return string[]
		 */
		protected function ec_extra_classes( $settings ) {
			unset( $settings );
			return array();
		}

		/**
		 * Editor notes: the one-page cart page, older template copies, a second Cart widget away from the cart page.
		 *
		 * @param string $scope ec_hide_scope().
		 */
		protected function ec_editor_notes( $scope ) {
			if ( ! $this->ec_is_editor() ) {
				return;
			}
			if ( 'onepage' === $scope ) {
				/* checkout.css shows the one that fits the preview ( a Checkout widget on the page or not ), unsaved changes included. */
				echo '<div class="wpec-el-notice wpec-cart-note wpec-cart-note--with-checkout" role="note"><strong>' . esc_html__( 'The Checkout widget shows the cart on this page.', 'wp-easycart' ) . '</strong><span>' . esc_html__( 'Your store uses the one-page checkout, which draws the cart and every step together, so shoppers see nothing from this widget here. Style the cart under Checkout › Style › Cart, or remove this widget.', 'wp-easycart' ) . '</span></div>';
				echo '<div class="wpec-el-notice wpec-cart-note wpec-cart-note--alone" role="note"><strong>' . esc_html__( 'On this page the cart leads into your one-page checkout.', 'wp-easycart' ) . '</strong><span>' . esc_html__( 'The coupon, gift card, shipping estimate, steps and express buttons belong to the checkout steps there, so those switches do not apply. Add the Checkout widget to style the steps.', 'wp-easycart' ) . '</span></div>';
			}
			$note = WP_EasyCart_Elementor_Checkout_Module::template_copy_note( 'cart' );
			if ( '' !== $note ) {
				$this->ec_editor_notice( __( 'Some switches may do nothing.', 'wp-easycart' ), $note );
			}
		}

		/**
		 * The saved template shown below the cart, when one is chosen.
		 *
		 * @param array $settings Settings.
		 */
		protected function ec_render_after( $settings ) {
			if ( empty( $settings['ec_after_template'] ) ) {
				return;
			}
			$html = $this->ec_template_html( $settings['ec_after_template'] );
			if ( '' !== trim( $html ) ) {
				echo '<div class="wpec-cart-after">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor's own rendering of a saved template.
			}
		}

		/** Draw. */
		protected function render() {
			$settings = $this->get_settings_for_display();
			$classes  = $this->ec_wrapper_classes( $settings );
			$scope    = $this->ec_hide_scope();

			if ( $this->ec_is_editor() ) {
				$this->ec_editor_notes( $scope );
				if ( 'all' === $scope && ! WP_EasyCart_Elementor_Checkout_Module::editing_cart_page() && ! WP_EasyCart_Elementor_Checkout_Module::claim_offpage_cart() ) {
					$this->ec_editor_notice( __( 'Only the first Cart widget on a page shows the cart.', 'wp-easycart' ), __( 'The cart\'s buttons and fields work by id, so shoppers see nothing from this one. Remove it, or keep one Cart widget per page.', 'wp-easycart' ) );
				}
				$empty = ( isset( $settings['ec_preview'] ) && 'empty' === $settings['ec_preview'] );
				if ( $empty ) {
					$classes[] = 'wpec-cart--empty';
				}
				echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">';
				if ( $empty ) {
					$this->ec_render_empty( $settings );
				} else {
					/* The sample shows the widget's own texts ( Texts section ) as shoppers will see them. */
					WP_EasyCart_Elementor_Checkout_Module::push_texts( $settings );
					if ( 'onepage' === $scope ) {
						$this->ec_render_onepage_sample();
					} else {
						$this->ec_render_sample();
					}
					WP_EasyCart_Elementor_Checkout_Module::pop_texts();
				}
				echo '</div>';
				if ( ! $empty ) {
					$this->ec_render_after( $settings );
				}
				return;
			}

			if ( WP_EasyCart_Elementor_Checkout_Module::is_cart_page() ) {
				if ( ! WP_EasyCart_Elementor_Checkout_Module::owns( $this->get_name() ) ) {
					return;
				}
				$dynamic = function_exists( 'wp_easycart_cart_is_dynamic' ) && wp_easycart_cart_is_dynamic();
				$state   = WP_EasyCart_Elementor_Checkout_Module::state();
				$empty   = ( ! $dynamic && 'cart' === $state && $this->ec_cart_count() <= 0 );
				if ( $empty ) {
					$classes[] = 'wpec-cart--empty';
				}
				echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '" data-wpec-cart-flow="1" data-wpec-cart-widget="cart"' . WP_EasyCart_Elementor_Checkout_Module::context_attributes( $this->get_id() ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- context_attributes() escapes.
				WP_EasyCart_Elementor_Checkout_Module::render_flow( $this->get_name(), $settings );
				$this->ec_render_empty( $settings, ! $empty );
				echo '</div>';
				if ( 'cart' === $state && ! $empty ) {
					$this->ec_render_after( $settings );
				}
				return;
			}

			/* Away from the cart page: the cart on its own, once per page ( EasyCart's cart works by id ). */
			if ( ! WP_EasyCart_Elementor_Checkout_Module::claim_offpage_cart() ) {
				return;
			}
			WP_EasyCart_Elementor_Checkout_Module::no_cache();
			if ( function_exists( 'wp_easycart_load_cart_js' ) && did_action( 'wp_enqueue_scripts' ) && ! wp_script_is( 'payment_jquery_js', 'enqueued' ) ) {
				/* A Cart widget the page scan did not see ( a popup, a shortcode ): its scripts still load, in the footer. */
				wp_easycart_load_cart_js();
			}
			$attributes = ' data-wpec-cart-widget="cart" data-wpec-cart-offpage="1"' . WP_EasyCart_Elementor_Checkout_Module::context_attributes( $this->get_id() );
			/* checkout.js asks for the cart after the page loads ( wp_easycart_elementor_cart ). 6.0.3: always, not only with cache
			 * prevention on: the page's HTML never holds a visitor's cart, even where a cache ignores the no-cache signal above. */
			echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '" data-wpec-cart-load="1"' . $attributes . '><div class="wpec-cart-loading" aria-busy="true"></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
			$this->ec_render_empty( $settings, true );
			echo '</div>';
			$this->ec_render_after( $settings );
		}

		/**
		 * Items in the visitor's cart ( no cart created for a visitor without one ).
		 *
		 * @return int
		 */
		protected function ec_cart_count() {
			if ( ! isset( $GLOBALS['ec_cart_data'] ) || ! class_exists( 'ec_db' ) ) {
				return 0;
			}
			$db = new ec_db();
			return (int) $db->get_cart_count( $GLOBALS['ec_cart_data']->ec_cart_id );
		}

		/**
		 * The editor's sample on a one-page store's cart page: the one-page cart ( ec_cart_v2.php's classes, no ids or scripts ).
		 */
		protected function ec_render_onepage_sample() {
			$lines    = WP_EasyCart_Elementor_Checkout_Module::sample_lines( 2 );
			$subtotal = 0;
			foreach ( $lines as $line ) {
				$subtotal += $line['amount'];
			}
			$t = function ( $key, $fallback, $section ) {
				return wp_strip_all_tags( WP_EasyCart_Elementor_Checkout_Module::text( $key, $fallback, $section ) );
			};
			echo '<div class="ec_cart_page wpec-cart-sample--onepage"><div class="ec_cart_left ec_cart_full"><div class="ec_cart_onepage">';
			echo '<div class="ec_cart_table"><div class="ec_cart_table_headers"><div class="ec_cart_table_header_spacer ec_cart_table_details"></div>';
			echo '<div class="ec_cart_table_header_price ec_cart_table_price">' . esc_html( $t( 'cart_header_column3', 'Price', 'cart' ) ) . '</div>';
			echo '<div class="ec_cart_table_header_quantity ec_cart_table_quantity">' . esc_html( $t( 'cart_header_column4', 'Quantity', 'cart' ) ) . '</div>';
			echo '<div class="ec_cart_table_header_total ec_cart_table_total">' . esc_html( $t( 'cart_header_column5', 'Total', 'cart' ) ) . '</div></div>';
			echo '<div class="ec_cart_table_body">';
			foreach ( $lines as $line ) {
				echo '<div class="ec_cart_table_row"><div class="ec_cart_table_column_details ec_cart_table_details"><div class="ec_cart_table_image">' . ( '' !== $line['image'] ? '<img src="' . esc_url( $line['image'] ) . '" alt="" />' : '' ) . '</div>';
				echo '<div class="ec_cart_table_details_content"><a href="#" class="ec_cartitem_title" onclick="return false;">' . esc_html( $line['title'] ) . '</a><div class="ec_cart_table_mobile_price">' . esc_html( $line['price'] ) . '</div>' . ( '' !== $line['options'] ? '<dl><dt>' . esc_html( $line['options'] ) . '</dt></dl>' : '' ) . '</div></div>';
				echo '<div class="ec_cart_table_column_price ec_cart_table_price">' . esc_html( $line['price'] ) . '</div>';
				echo '<div class="ec_cart_table_column_quantity ec_cart_table_quantity"><input type="number" value="' . esc_attr( (string) $line['quantity'] ) . '" class="ec_quantity ec_table_quantity_box" readonly tabindex="-1" /><div class="ec_cartitem_delete">' . esc_html( $t( 'remove', 'Remove', 'cart_onepage' ) ) . '</div></div>';
				echo '<div class="ec_cart_table_column_total ec_cart_table_total">' . esc_html( $line['total'] ) . '</div></div>';
			}
			echo '</div></div>';
			echo '<div class="ec_cart_table_subtotal_row"><div class="ec_cart_table_subtotal"><div><span class="ec_cart_table_subtotal_label">' . esc_html( $t( 'cart_totals_subtotal', 'Cart Subtotal', 'cart_totals' ) ) . '</span> <span class="ec_cart_table_subtotal_amount">' . esc_html( WP_EasyCart_Elementor_Checkout_Module::price( $subtotal ) ) . '</span></div>';
			echo '<div class="ec_cart_table_message">' . esc_html( $t( 'checkout_note', 'Shipping, taxes, and discount codes calculated at checkout.', 'cart_onepage' ) ) . '</div>';
			echo '<div class="ec_cart_table_checkout_button_row"><a class="ec_cart_table_checkout_button" href="#" onclick="return false;">' . esc_html( $t( 'cart_checkout', 'Checkout', 'cart' ) ) . '</a></div>';
			echo '<div class="ec_cart_table_shopping_button"><a class="ec_cart_table_continue_shopping" href="#" onclick="return false;">' . esc_html( $t( 'cart_continue_shopping', 'Continue Shopping', 'cart' ) ) . '</a></div>';
			echo '</div></div></div></div><div style="clear:both;"></div></div>';
		}

		/**
		 * The editor's sample: EasyCart's cart markup ( classes only, no ids or scripts ) with the store's first products.
		 */
		protected function ec_render_sample() {
			$lines    = WP_EasyCart_Elementor_Checkout_Module::sample_lines( 2 );
			$subtotal = 0;
			foreach ( $lines as $line ) {
				$subtotal += $line['amount'];
			}
			$shipping = 5;
			$tax      = round( $subtotal * 0.07, 2 );
			$text     = array( 'WP_EasyCart_Elementor_Checkout_Module', 'text' );
			?>
			<div class="ec_cart_page">
			<section class="ec_cart_page">
				<div class="ec_cart_breadcrumbs">
					<div class="ec_cart_breadcrumb"><?php echo esc_html( wp_strip_all_tags( call_user_func( $text, 'cart_title', 'SHOPPING CART', 'cart' ) ) ); ?></div>
					<div class="ec_cart_breadcrumb_divider"></div>
					<div class="ec_cart_breadcrumb ec_inactive"><?php echo esc_html( wp_strip_all_tags( call_user_func( $text, 'cart_checkout_details_title', 'CHECKOUT DETAILS', 'cart' ) ) ); ?></div>
					<div class="ec_cart_breadcrumb_divider"></div>
					<div class="ec_cart_breadcrumb ec_inactive"><?php echo esc_html( wp_strip_all_tags( call_user_func( $text, 'cart_submit_payment_title', 'SUBMIT PAYMENT', 'cart' ) ) ); ?></div>
				</div>
				<div class="ec_cart_left ec_cart_holder">
					<table class="ec_cart" cellspacing="0">
						<thead>
							<tr>
								<th class="ec_cartitem_head_name" colspan="3"><?php echo esc_html( wp_strip_all_tags( call_user_func( $text, 'cart_header_column1', 'Product', 'cart' ) ) ); ?></th>
								<th class="ec_cartitem_head_price"><?php echo esc_html( wp_strip_all_tags( call_user_func( $text, 'cart_header_column3', 'Price', 'cart' ) ) ); ?></th>
								<th class="ec_cartitem_head_quantity"><?php echo esc_html( wp_strip_all_tags( call_user_func( $text, 'cart_header_column4', 'Quantity', 'cart' ) ) ); ?></th>
								<th class="ec_cartitem_head_total"><?php echo esc_html( wp_strip_all_tags( call_user_func( $text, 'cart_header_column5', 'Total', 'cart' ) ) ); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ( $lines as $line ) { ?>
							<tr class="ec_cartitem_row">
								<td class="ec_cartitem_remove_column"><div class="ec_cartitem_delete"><span><?php echo esc_html( wp_strip_all_tags( call_user_func( $text, 'cart_item_remove_button', 'REMOVE', 'cart' ) ) ); ?></span></div></td>
								<td class="ec_cartitem_image">
								<?php
								if ( '' !== $line['image'] ) {
									?>
									<img src="<?php echo esc_url( $line['image'] ); ?>" alt="" /><?php } ?></td>
								<td class="ec_cartitem_details">
									<a href="#" class="ec_cartitem_title" onclick="return false;"><?php echo esc_html( $line['title'] ); ?></a>
									<?php
									if ( '' !== $line['options'] ) {
										?>
										<dl><dt><?php echo esc_html( $line['options'] ); ?></dt></dl><?php } ?>
								</td>
								<td class="ec_cartitem_price"><?php echo esc_html( $line['price'] ); ?></td>
								<td class="ec_cartitem_quantity">
									<table class="ec_cartitem_quantity_table"><tbody><tr>
										<td class="ec_minus_column"><input type="button" value="-" class="ec_minus" tabindex="-1" /></td>
										<td class="ec_quantity_column"><input type="number" value="<?php echo esc_attr( (string) $line['quantity'] ); ?>" class="ec_quantity" readonly tabindex="-1" /></td>
										<td class="ec_plus_column"><input type="button" value="+" class="ec_plus" tabindex="-1" /></td>
									</tr></tbody></table>
								</td>
								<td class="ec_cartitem_total"><?php echo esc_html( $line['total'] ); ?></td>
							</tr>
						<?php } ?>
						</tbody>
					</table>
				</div>
				<div class="ec_cart_right">
					<div class="ec_cart_header ec_top"><?php echo esc_html( wp_strip_all_tags( call_user_func( $text, 'cart_totals_label', 'Cart Totals', 'cart_totals' ) ) ); ?></div>
					<?php
					$rows = array(
						array( 'ec_cart_price_row_subtotal', call_user_func( $text, 'cart_totals_subtotal', 'Cart Subtotal', 'cart_totals' ), $subtotal ),
						array( 'ec_cart_price_row_shipping_total', call_user_func( $text, 'cart_totals_shipping', 'Shipping', 'cart_totals' ), $shipping ),
						array( 'ec_cart_price_row_tax_total', call_user_func( $text, 'cart_totals_tax', 'Tax', 'cart_totals' ), $tax ),
						array( 'ec_order_total', call_user_func( $text, 'cart_totals_grand_total', 'Grand Total', 'cart_totals' ), $subtotal + $shipping + $tax ),
					);
					foreach ( $rows as $row ) {
						echo '<div class="ec_cart_price_row ' . esc_attr( $row[0] ) . '"><div class="ec_cart_price_row_label">' . esc_html( wp_strip_all_tags( $row[1] ) ) . '</div><div class="ec_cart_price_row_total">' . esc_html( WP_EasyCart_Elementor_Checkout_Module::price( $row[2] ) ) . '</div></div>';
					}
					?>
					<div class="ec_cart_button_row ec_cart_button_row_checkout"><a class="ec_cart_button ec_cart_button_checkout" href="#" onclick="return false;"><?php echo esc_html( wp_strip_all_tags( call_user_func( $text, 'cart_checkout', 'Checkout', 'cart' ) ) ); ?></a></div>
					<div class="ec_cart_button_row ec_cart_button_row_shopping"><a class="ec_cart_button ec_cart_button_shopping" href="#" onclick="return false;"><?php echo esc_html( wp_strip_all_tags( call_user_func( $text, 'cart_continue_shopping', 'Continue Shopping', 'cart' ) ) ); ?></a></div>
					<div class="ec_cart_header ec_cart_coupon_part"><?php echo esc_html( wp_strip_all_tags( call_user_func( $text, 'cart_coupon_title', 'Coupon', 'cart_coupons' ) ) ); ?></div>
					<div class="ec_cart_input_row ec_cart_coupon_part"><input type="text" placeholder="<?php echo esc_attr( wp_strip_all_tags( call_user_func( $text, 'cart_enter_coupon', 'Enter Coupon Code', 'cart_coupons' ) ) ); ?>" tabindex="-1" /></div>
					<div class="ec_cart_button_row ec_cart_coupon_part"><div class="ec_cart_button"><?php echo esc_html( wp_strip_all_tags( call_user_func( $text, 'cart_apply_coupon', 'Apply Coupon', 'cart_coupons' ) ) ); ?></div></div>
					<div class="ec_cart_header ec_cart_giftcard_part"><?php echo esc_html( wp_strip_all_tags( call_user_func( $text, 'cart_gift_card_title', 'Gift Card', 'cart_coupons' ) ) ); ?></div>
					<div class="ec_cart_input_row ec_cart_giftcard_part"><input type="text" placeholder="<?php echo esc_attr( wp_strip_all_tags( call_user_func( $text, 'cart_enter_gift_code', 'Enter Gift Card Code', 'cart_coupons' ) ) ); ?>" tabindex="-1" /></div>
					<div class="ec_cart_button_row ec_cart_giftcard_part"><div class="ec_cart_button"><?php echo esc_html( wp_strip_all_tags( call_user_func( $text, 'cart_redeem_gift_card', 'Redeem Gift Card', 'cart_coupons' ) ) ); ?></div></div>
					<div class="ec_cart_header ec_cart_estimate_part"><?php echo esc_html( wp_strip_all_tags( call_user_func( $text, 'cart_estimate_shipping_button', 'Estimate Shipping', 'cart_estimate_shipping' ) ) ); ?></div>
					<div class="ec_cart_input_row ec_cart_estimate_part"><input type="text" placeholder="<?php echo esc_attr( wp_strip_all_tags( call_user_func( $text, 'cart_estimate_shipping_hint', 'Zip Code', 'cart_estimate_shipping' ) ) ); ?>" tabindex="-1" /></div>
					<div class="ec_cart_button_row ec_cart_estimate_part"><div class="ec_cart_button"><?php echo esc_html( wp_strip_all_tags( call_user_func( $text, 'cart_estimate_shipping_button', 'Estimate Shipping', 'cart_estimate_shipping' ) ) ); ?></div></div>
				</div>
			</section>
			<div style="clear:both;"></div>
			</div>
			<?php
		}
	}

endif;
