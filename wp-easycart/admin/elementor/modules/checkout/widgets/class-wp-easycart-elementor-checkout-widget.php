<?php
/**
 * Checkout ( wp_easycart_checkout, 6.0.2 ): EasyCart's checkout on the cart page ( the classic checkout in steps, or the
 * one-page checkout when wp_easycart_onepage_active() ), drawn by load_ec_cart() with every gateway, 3-D Secure and
 * redirect return as always, and styled here: section headings, fields, choices, buttons, the order summary, gift options
 * and messages. The editor shows a sample checkout.
 *
 * WP EasyCart PRO ( WP_EasyCart_Elementor_Checkout_Widget_Pro, same name ) fills the two locked sections: the layout
 * ( order summary position; coupon, gift card, gift options, order notes and express buttons shown or hidden; text between
 * sections ) and the totals lines. It never moves or splits the checkout: one form, one Place order.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/trait-wp-easycart-elementor-checkout-controls.php';

if ( ! class_exists( 'WP_EasyCart_Elementor_Checkout_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Widget_Base' ) ) :

	/**
	 * Checkout widget.
	 */
	class WP_EasyCart_Elementor_Checkout_Widget extends WP_EasyCart_Elementor_Widget_Base {

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
		protected static $ec_help = 'checkout';

		/**
		 * Widget name ( stored in saved pages ).
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_checkout';
		}

		/**
		 * Widget title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Checkout', 'wp-easycart' );
		}

		/**
		 * Panel icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-checkout';
		}

		/**
		 * Search keywords.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array( 'checkout', 'payment', 'one page checkout', 'place order', 'woocommerce checkout' );
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
		 * Saved into post_content: EasyCart loads its payment scripts for pages that carry [ec_cart] ( once per document,
		 * shared with the Cart widget ).
		 *
		 * @return string
		 */
		protected function plain_content_shortcode() {
			return WP_EasyCart_Elementor_Checkout_Module::plain_cart_shortcode();
		}

		/** Controls. */
		protected function register_controls() {
			$onepage = function_exists( 'wp_easycart_onepage_active' ) && wp_easycart_onepage_active();
			$this->start_controls_section(
				'ec_checkout_section',
				array(
					'label' => __( 'Checkout', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->add_control(
				'ec_checkout_note',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => esc_html( $onepage ? __( 'Shows your one-page checkout on your cart page.', 'wp-easycart' ) : __( 'Shows your checkout in steps ( details, shipping, payment ) on your cart page.', 'wp-easycart' ) )
						. ' <a href="' . esc_url( admin_url( 'admin.php?page=wp-easycart-settings&subpage=checkout' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Checkout settings', 'wp-easycart' ) . '</a>',
					'content_classes' => 'elementor-descriptor',
				)
			);
			$this->add_control(
				'ec_show_steps',
				array(
					'label'        => __( 'Checkout steps', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'label_on'     => __( 'Show', 'wp-easycart' ),
					'label_off'    => __( 'Hide', 'wp-easycart' ),
					'return_value' => 'yes',
					'default'      => 'yes',
					'description'  => __( 'The line of steps above the checkout.', 'wp-easycart' ),
				)
			);
			$this->add_control(
				'ec_preview',
				array(
					'label'       => __( 'Show in the editor', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'default'     => 'checkout',
					'options'     => array(
						'checkout' => __( 'A sample checkout', 'wp-easycart' ),
						'empty'    => __( 'The empty cart', 'wp-easycart' ),
					),
					'separator'   => 'before',
					'description' => __( 'Only while you edit. Shoppers see their own checkout.', 'wp-easycart' ),
				)
			);
			$this->end_controls_section();

			$this->ec_empty_cart_controls();
			$this->ec_register_pro_controls();
			$this->ec_text_override_section(
				'ec_texts_section',
				__( 'Texts', 'wp-easycart' ),
				array( 'contact', 'cart_login_title', 'cart_shipping_information_title', 'cart_billing_information_title', 'billing_address', 'cart_shipping_method_title', 'cart_payment_information_payment_method', 'cart_payment_information_order_notes_title', 'cart_payment_information_review_title', 'order_summary', 'your_cart_title', 'cart_totals_title', 'cart_enter_coupon', 'cart_simple_apply', 'cart_apply_coupon', 'cart_enter_gift_code', 'cart_redeem_gift_card', 'cart_payment_information_submit_order_button', 'cart_title', 'cart_checkout_details_title', 'cart_submit_payment_title', 'cart_header_column3', 'cart_header_column4', 'cart_header_column5', 'cart_checkout', 'cart_continue_shopping', 'checkout_note', 'remove' )
			);
			$this->ec_register_style_controls();
		}

		/**
		 * The layout and totals sections ( WP EasyCart PRO ): locked here.
		 */
		protected function ec_register_pro_controls() {
			$this->ec_locked_section(
				'ec_sections_section',
				__( 'Layout and sections', 'wp-easycart' ),
				'checkout_sections',
				__( 'Place the order summary beside, above or below the checkout, choose which extras show ( coupon, gift card, gift options, order notes, express buttons ) and add your own text, HTML or saved templates between the checkout\'s sections.', 'wp-easycart' ),
				array( __( 'Order summary position', 'wp-easycart' ), __( 'Coupon, gift card and gift options', 'wp-easycart' ), __( 'Order notes and express buttons', 'wp-easycart' ), __( 'Text between sections', 'wp-easycart' ) )
			);
			$this->ec_locked_section(
				'ec_totals_section',
				__( 'Totals lines', 'wp-easycart' ),
				'checkout_sections',
				__( 'Choose which lines the order totals show: hide the shipping or tax line. The grand total always shows at checkout.', 'wp-easycart' ),
				array( __( 'Shipping line', 'wp-easycart' ), __( 'Tax line', 'wp-easycart' ) )
			);
		}

		/** Style tab. */
		protected function ec_register_style_controls() {
			$this->start_controls_section(
				'layout_style',
				array(
					'label' => __( 'Layout', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->add_responsive_control(
				'layout_summary_width',
				array(
					'label'       => __( 'Order summary width', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SLIDER,
					'size_units'  => array( '%' ),
					'range'       => array(
						'%' => array(
							'min' => 20,
							'max' => 100,
						),
					),
					'description' => __( '100% puts the summary under the checkout.', 'wp-easycart' ),
					'selectors'   => array(
						'{{WRAPPER}} .ec_cart_right' => 'width: {{SIZE}}%;',
						'{{WRAPPER}} .ec_cart_left:not(.ec_cart_full)' => 'width: calc( 100% - {{SIZE}}% );',
					),
				)
			);
			/* --wpec-el-col-gap and --wpec-el-col-divider: WP EasyCart PRO's summary on the left reads them ( round 11 ). */
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
						'{{WRAPPER}}'                => '--wpec-el-col-gap: {{SIZE}}{{UNIT}};',
						'{{WRAPPER}} .ec_cart_left'  => 'padding-right: {{SIZE}}{{UNIT}};',
						'{{WRAPPER}} .ec_cart_right' => 'padding-left: {{SIZE}}{{UNIT}};',
					),
				)
			);
			$this->ec_divider_control( 'layout_divider_color', '.ec_cart_left', 'right', array( '' => '--wpec-el-col-divider: {{VALUE}};' ) );
			$this->end_controls_section();

			$this->ec_box_section( 'form_box', __( 'Checkout column', 'wp-easycart' ), '.ec_cart_left:not(.ec_cart_full)' );

			$this->start_controls_section(
				'steps_style',
				array(
					'label'     => __( 'Checkout steps', 'wp-easycart' ),
					'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
					'condition' => array( 'ec_show_steps' => 'yes' ),
				)
			);
			/* Round 11: EasyCart sets the one-page steps' size with !important. */
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

			$this->ec_text_section(
				'headings',
				__( 'Section headings', 'wp-easycart' ),
				'.ec_cart_header',
				array(
					'typography' => \Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_PRIMARY,
					'color'      => \Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_PRIMARY,
					'font_var'   => true,
					'spacing'    => true,
					'align'      => true,
				)
			);
			$this->start_controls_section(
				'headings_line_style',
				array(
					'label' => __( 'Heading lines', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->ec_divider_control( 'headings_divider', '.ec_cart_header:not(.ec_cart_header_no_border)', 'bottom' );
			$this->add_responsive_control(
				'headings_divider_width',
				array(
					'label'      => __( 'Line thickness', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 10,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .ec_cart_header:not(.ec_cart_header_no_border)' => 'border-bottom-width: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_control(
				'headings_divider_style',
				array(
					'label'     => __( 'Line style', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'default'   => '',
					'options'   => array(
						''       => __( 'Default', 'wp-easycart' ),
						'solid'  => __( 'Solid', 'wp-easycart' ),
						'dashed' => __( 'Dashed', 'wp-easycart' ),
						'dotted' => __( 'Dotted', 'wp-easycart' ),
						'none'   => __( 'None', 'wp-easycart' ),
					),
					'selectors' => array( '{{WRAPPER}} .ec_cart_header:not(.ec_cart_header_no_border)' => 'border-bottom-style: {{VALUE}};' ),
				)
			);
			$this->end_controls_section();

			$this->ec_fields_section(
				'fields',
				__( 'Fields', 'wp-easycart' ),
				'.ec_cart_input_row label:not(.ec_cart_full_radio)',
				'.ec_cart_input_row input:not([type="checkbox"]):not([type="radio"]):not([type="button"]):not([type="submit"]), .ec_cart_input_row select, .ec_cart_input_row textarea, .ec_cart_box_section input[type="text"], #ec_order_notes',
				array(),
				array(
					'rows'      => '.ec_cart_input_row, .ec_cart_input_row_flex',
					'columns'   => '.ec_cart_input_row > .ec_cart_input_right_half',
					'flex_rows' => '.ec_cart_input_row_flex',
					'choices'   => 'input[type="checkbox"], input[type="radio"]',
				)
			);

			/* Round 11: the chosen shipping method's grey is EasyCart's own ( .ec_method_selected ), and billing rows join in. */
			$rows = '.ec_cart_shipping_method_row, .ec_cart_payment_table_row, .ec_cart_billing_table_row, .ec_cart_review_row';
			$this->start_controls_section(
				'options_style',
				array(
					'label' => __( 'Shipping and payment choices', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->ec_text_controls( 'options', $rows, array( 'font_var' => true ) );
			$this->add_control(
				'options_background',
				array(
					'label'     => __( 'Background color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .ec_cart_shipping_method_row, {{WRAPPER}} .ec_cart_payment_table_row, {{WRAPPER}} .ec_cart_billing_table_row, {{WRAPPER}} .ec_cart_review_box' => 'background-color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'options_selected_background',
				array(
					'label'     => __( 'Chosen background color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .ec_cart_payment_table_row.ec_payment_row_selected, {{WRAPPER}} .ec_cart_shipping_table .ec_cart_shipping_method_row.ec_method_selected, {{WRAPPER}} .ec_cart_billing_table .ec_cart_billing_table_row.ec_billing_row_selected, {{WRAPPER}} .ec_cart_billing_table .ec_cart_billing_table_address' => 'background-color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'options_selected_color',
				array(
					'label'     => __( 'Chosen text colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .ec_cart_payment_table_row.ec_payment_row_selected, {{WRAPPER}} .ec_cart_shipping_table .ec_cart_shipping_method_row.ec_method_selected, {{WRAPPER}} .ec_cart_billing_table .ec_cart_billing_table_row.ec_billing_row_selected' => 'color: {{VALUE}};' ),
				)
			);
			$this->ec_divider_control( 'options_border', '.ec_cart_shipping_method_row, .ec_cart_payment_table_row, .ec_cart_billing_table_row, .ec_cart_review_box, .ec_cart_review_row, .ec_cart_shipping_table, .ec_cart_payment_table, .ec_cart_billing_table', '' );
			$this->add_responsive_control(
				'options_radius',
				array(
					'label'      => __( 'Box border radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 30,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .ec_cart_shipping_table, {{WRAPPER}} .ec_cart_payment_table, {{WRAPPER}} .ec_cart_billing_table, {{WRAPPER}} .ec_cart_review_box' => 'border-radius: {{SIZE}}{{UNIT}}; overflow: hidden;' ),
				)
			);
			$this->add_responsive_control(
				'options_padding',
				array(
					'label'      => __( 'Row padding', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em' ),
					'selectors'  => array( '{{WRAPPER}} .ec_cart_shipping_table .ec_cart_shipping_method_row, {{WRAPPER}} .ec_cart_payment_table_row, {{WRAPPER}} .ec_cart_billing_table_row' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->end_controls_section();

			$this->ec_button_section(
				'buttons',
				__( 'Buttons', 'wp-easycart' ),
				'.ec_cart_button, .ec_cart_button_row input[type="submit"], .ec_cart_button_row input[type="button"]',
				array(
					'vars'     => 'btn',
					'font_var' => true,
					'working'  => '.ec_cart_button_working',
				)
			);
			$this->ec_place_order_section();

			$this->ec_text_section(
				'links',
				__( 'Links', 'wp-easycart' ),
				'.ec_cart_bottom_nav_back, .ec_cart_show_cart a, .ec_cart_login_header_link a, a.ec_cart_login_header_link, .ec_cart_review_button > a, .ec_cart_create_account_row_v2 > a, .ec_cart_address_change a, .ec_cart_button_row a.ec_account_login_link, .ec_cart_button_row a.ec_account_login_cancel_link',
				array(
					'font_var'  => true,
					'hover'     => true,
					'important' => array( 'font_size' ),
					'color'     => '',
				)
			);

			$this->start_controls_section(
				'summary_box_style',
				array(
					'label' => __( 'Order summary', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->ec_box_controls( 'summary_box', '.ec_cart_right' );
			$this->add_responsive_control(
				'summary_image_width',
				array(
					'label'      => __( 'Image width', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 20,
							'max' => 160,
						),
					),
					'separator'  => 'before',
					'selectors'  => array( '{{WRAPPER}} .ec_cart_image_row_v2, {{WRAPPER}} .ec_cart_image_row_v2 img' => 'width: {{SIZE}}{{UNIT}}; min-width: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_responsive_control(
				'summary_image_radius',
				array(
					'label'      => __( 'Image border radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px', '%' ),
					'selectors'  => array( '{{WRAPPER}} .ec_cart_image_row_v2 img' => 'border-radius: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_control(
				'summary_items_heading',
				array(
					'label'     => __( 'Products', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->ec_text_controls( 'summary_items', '.ec_cart_price_row_label_v2, .ec_cart_price_row_total_v2, .ec_cart_right .ec_cart_price_row[class*="ec_cart_price_row_cartitem_"] .ec_cart_price_row_label', array( 'font_var' => true ) );
			$this->add_responsive_control(
				'summary_items_gap',
				array(
					'label'      => __( 'Space between products', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 40,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .ec_cart_price_row_v2' => 'padding-top: {{SIZE}}{{UNIT}}; padding-bottom: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_control(
				'summary_lines_heading',
				array(
					'label'     => __( 'Totals lines', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->ec_text_controls( 'summary_lines', '.ec_cart_price_row_label, .ec_cart_price_row_total', array( 'font_var' => true ) );
			$this->ec_divider_control( 'summary_lines_divider', '.ec_cart_price_row, .ec_cart_price_row_v2', 'bottom' );
			$this->ec_totals_split_controls( 'summary_lines', '.ec_cart_price_row_label', '.ec_cart_price_row_total' );
			$this->add_control(
				'summary_total_heading',
				array(
					'label'     => __( 'Grand total', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			/* Round 11: #ec_cart_total ( EasyCart's bold weight by id ) is named too, so the weight applies. */
			$this->ec_text_controls(
				'summary_total',
				'.ec_cart_price_row.ec_order_total .ec_cart_price_row_label, .ec_cart_price_row.ec_order_total .ec_cart_price_row_total, #ec_cart_total',
				array(
					'typography' => \Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_PRIMARY,
					'font_var'   => true,
				)
			);
			$this->add_control(
				'summary_edit_heading',
				array(
					'label'     => __( '"Edit cart" link', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->add_control(
				'summary_hide_edit',
				array(
					'label'        => __( 'Hide the link', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'return_value' => 'yes',
					'default'      => '',
					'selectors'    => array( '{{WRAPPER}} .ec_cart_right .ec_cart_show_cart, {{WRAPPER}} .ec_cart_mobile_summary .ec_cart_show_cart' => 'display: none !important;' ),
					'description'  => __( 'The link back to the cart under the one-page checkout\'s summary.', 'wp-easycart' ),
				)
			);
			$this->ec_text_controls(
				'summary_edit',
				'.ec_cart_show_cart a',
				array(
					'font_var'  => true,
					'hover'     => true,
					'important' => array( 'font_size' ),
					'color'     => '',
				)
			);
			$this->add_control(
				'summary_mobile_heading',
				array(
					'label'     => __( 'Summary bar on phones', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->add_control(
				'summary_mobile_background',
				array(
					'label'     => __( 'Background colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .ec_cart_mobile_summary' => 'background-color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'summary_mobile_color',
				array(
					'label'     => __( 'Text colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .ec_cart_mobile_summary, {{WRAPPER}} .ec_cart_mobile_summary_header_label, {{WRAPPER}} .ec_cart_mobile_summary_header_total' => 'color: {{VALUE}};' ),
				)
			);
			$this->end_controls_section();

			$this->ec_checkout_cart_section();
			$this->ec_express_section();

			$this->start_controls_section(
				'gift_style',
				array(
					'label' => __( 'Gift options', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->add_control(
				'gift_note',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => esc_html__( 'The "Send this order as a gift" box and the gift summary, when gift orders are on ( Settings › Documents ).', 'wp-easycart' ),
					'content_classes' => 'elementor-descriptor',
				)
			);
			$gift = array(
				'gift_background' => array( __( 'Background color', 'wp-easycart' ), '--wpec-gift-bg' ),
				'gift_border'     => array( __( 'Border color', 'wp-easycart' ), '--wpec-gift-border' ),
				'gift_accent'     => array( __( 'Accent color', 'wp-easycart' ), '--wpec-gift-accent' ),
				'gift_text'       => array( __( 'Title color', 'wp-easycart' ), '--wpec-gift-ink' ),
				'gift_icon'       => array( __( 'Icon background color', 'wp-easycart' ), '--wpec-gift-icon' ),
			);
			foreach ( $gift as $key => $control ) {
				$this->add_control(
					$key,
					array(
						'label'     => $control[0],
						'type'      => \Elementor\Controls_Manager::COLOR,
						'selectors' => array( '{{WRAPPER}} .wpec-gift, {{WRAPPER}} .wpec-gift-summary' => $control[1] . ': {{VALUE}};' ),
					)
				);
			}
			$this->ec_typography( 'gift_typography', '.wpec-gift .wpec-gift-title, .wpec-gift-summary .wpec-gift-summary-title' );
			$this->end_controls_section();

			$this->ec_notices_section( '.ec_cart_error > div, .ec_cart_error_row, .ec_cart_error_message, #ec_onepage_order_errors, .ec_cart_locked_panel', '.ec_cart_success > div, .ec_cart_success_message, .ec_cart_notice_row' );
			$this->ec_empty_style_sections();
		}

		/**
		 * Place order ( round 11 ): EasyCart's #ec_cart_submit_order ( one-page and classic payment step ) and its "Please wait"
		 * copy, styled apart from the other buttons ( whose section still applies until these are set ). Colours go through
		 * --wpec-el-place-* ( checkout.css applies them over EasyCart's !important ones ).
		 */
		protected function ec_place_order_section() {
			$button  = '#ec_cart_submit_order, #ec_cart_submit_order_working, .wpec-el-place-order';
			$section = array(
				'label' => __( 'Place order button', 'wp-easycart' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			);
			$this->start_controls_section( 'place_order_style', $section );
			$this->ec_typography( 'place_order_typography', $button, \Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_ACCENT, true );
			$this->start_controls_tabs( 'place_order_tabs' );
			$states = array(
				'normal'   => array( __( 'Normal', 'wp-easycart' ), '' ),
				'hover'    => array( __( 'Hover', 'wp-easycart' ), '-hover' ),
				'disabled' => array( __( 'Disabled', 'wp-easycart' ), '-disabled' ),
				'working'  => array( __( 'Please wait', 'wp-easycart' ), '-working' ),
			);
			foreach ( $states as $state => $state_args ) {
				$this->start_controls_tab( 'place_order_tab_' . $state, array( 'label' => $state_args[0] ) );
				$properties = array(
					'text_color' => array( __( 'Text color', 'wp-easycart' ), 'color' ),
					'background' => array( __( 'Background color', 'wp-easycart' ), 'bg' ),
				);
				if ( 'working' !== $state ) {
					$properties['border_color'] = array( __( 'Border color', 'wp-easycart' ), 'border' );
				}
				foreach ( $properties as $key => $property ) {
					$selectors = array( '{{WRAPPER}}' => '--wpec-el-place' . $state_args[1] . '-' . $property[1] . ': {{VALUE}};' );
					if ( 'hover' === $state && 'border_color' === $key ) {
						$selectors = array( '{{WRAPPER}} #ec_cart_submit_order:hover, {{WRAPPER}} #ec_cart_submit_order:focus-visible, {{WRAPPER}} .wpec-el-place-order:hover' => 'border-color: {{VALUE}} !important;' );
					}
					if ( 'disabled' === $state && 'border_color' === $key ) {
						$selectors = array( '{{WRAPPER}} #ec_cart_submit_order:disabled, {{WRAPPER}} #ec_cart_submit_order.wpec-protect-blocked' => 'border-color: {{VALUE}} !important;' );
					}
					$this->add_control(
						'place_order_' . ( 'normal' === $state ? '' : $state . '_' ) . $key,
						array(
							'label'     => $property[0],
							'type'      => \Elementor\Controls_Manager::COLOR,
							'selectors' => $selectors,
						)
					);
				}
				if ( 'disabled' === $state ) {
					$this->add_control(
						'place_order_disabled_opacity',
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
							'selectors' => array( '{{WRAPPER}} #ec_cart_submit_order:disabled, {{WRAPPER}} #ec_cart_submit_order.wpec-protect-blocked' => 'opacity: {{SIZE}};' ),
						)
					);
				}
				$this->end_controls_tab();
			}
			$this->end_controls_tabs();
			/* No colour field: the Normal tab's place_order_border_color is the name it would take ( see ec_button_section() ). */
			$this->add_group_control(
				\Elementor\Group_Control_Border::get_type(),
				array(
					'name'           => 'place_order_border',
					'selector'       => $this->ec_sel( '#ec_cart_submit_order, .wpec-el-place-order' ),
					'separator'      => 'before',
					'exclude'        => array( 'color' ),
					'fields_options' => array(
						'border' => $this->ec_group_field( 'border-style: {{VALUE}} !important; border-color: var(--wpec-el-place-border, currentColor) !important;' ),
						'width'  => $this->ec_group_field( 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;' ),
					),
				)
			);
			$this->add_responsive_control(
				'place_order_radius',
				array(
					'label'      => __( 'Border radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', '%', 'em' ),
					'selectors'  => array( $this->ec_sel( $button ) => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;' ),
				)
			);
			$this->add_responsive_control(
				'place_order_padding',
				array(
					'label'      => __( 'Padding', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em' ),
					'selectors'  => array( $this->ec_sel( $button ) => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important; height: auto !important;' ),
				)
			);
			/* The one-page checkout sizes the button's column ( a quarter of the row on computers, the whole row on phones ). */
			$width_targets = '{{WRAPPER}} #wpeasycart_submit_order_row > .ec_cart_bottom_nav_right, {{WRAPPER}} .wpec-el-place-order-row > .ec_cart_bottom_nav_right, {{WRAPPER}} .ec_cart_button_row#wpeasycart_submit_order_row > #ec_cart_submit_order, {{WRAPPER}} .ec_cart_button_row#wpeasycart_submit_order_row > #ec_cart_submit_order_working, {{WRAPPER}} .ec_cart_button_row.wpec-el-place-order-row > .wpec-el-place-order';
			$this->add_control(
				'place_order_full_width',
				array(
					'label'        => __( 'Full width', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'return_value' => 'yes',
					'separator'    => 'before',
					'selectors'    => array(
						$width_targets => 'width: 100% !important;',
						'{{WRAPPER}} #wpeasycart_submit_order_row' => 'flex-wrap: wrap; row-gap: 12px;',
					),
				)
			);
			$this->add_responsive_control(
				'place_order_width',
				array(
					'label'      => __( 'Width', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( '%', 'px' ),
					'range'      => array(
						'%'  => array(
							'min' => 10,
							'max' => 100,
						),
						'px' => array(
							'min' => 80,
							'max' => 600,
						),
					),
					'selectors'  => array( $width_targets => 'width: {{SIZE}}{{UNIT}} !important; max-width: 100%;' ),
					'condition'  => array( 'place_order_full_width!' => 'yes' ),
				)
			);
			$this->add_responsive_control(
				'place_order_align',
				array(
					'label'                => __( 'Alignment', 'wp-easycart' ),
					'type'                 => \Elementor\Controls_Manager::CHOOSE,
					'options'              => $this->ec_align_options( 'text' ),
					'selectors_dictionary' => array(
						'left'   => 'margin-left: 0 !important; margin-right: auto !important; float: left !important;',
						'center' => 'margin-left: auto !important; margin-right: auto !important; float: none !important; display: block;',
						'right'  => 'margin-left: auto !important; margin-right: 0 !important; float: right !important;',
					),
					'selectors'            => array( $width_targets => '{{VALUE}}' ),
					'condition'            => array( 'place_order_full_width!' => 'yes' ),
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Box_Shadow::get_type(),
				array(
					'name'     => 'place_order_shadow',
					'selector' => $this->ec_sel( '#ec_cart_submit_order, .wpec-el-place-order' ),
				)
			);
			$this->end_controls_section();
		}

		/**
		 * The cart as the Checkout widget draws it ( round 11 ): the one-page cart step, and the classic cart when no Cart widget
		 * is on the cart page. On a one-page store the Cart widget prints nothing beside this widget.
		 */
		protected function ec_checkout_cart_section() {
			$this->start_controls_section(
				'cart_style',
				array(
					'label' => __( 'Cart', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->add_control(
				'cart_note',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => esc_html__( 'The cart this widget shows before the checkout: on a one-page store always, otherwise when no Cart widget is on your cart page. Preview it with the Cart widget\'s sample, or on your site.', 'wp-easycart' ),
					'content_classes' => 'elementor-descriptor',
				)
			);
			$this->ec_text_controls( 'cart_head', '.ec_cart_table_headers > div, .ec_cart > thead > tr > th', array( 'font_var' => true ) );
			$this->ec_divider_control( 'cart_head_divider', '.ec_cart_table_headers, .ec_cart > thead, .ec_cart_table_body', 'bottom' );
			$this->add_control(
				'cart_items_heading',
				array(
					'label'     => __( 'Products', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->add_responsive_control(
				'cart_image_width',
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
						'{{WRAPPER}} .ec_cart_table_image' => 'width: auto; min-width: {{SIZE}}{{UNIT}};',
						'{{WRAPPER}} .ec_cart_table_image > img, {{WRAPPER}} .ec_cartitem_image > img' => 'width: {{SIZE}}{{UNIT}}; min-width: {{SIZE}}{{UNIT}};',
						'{{WRAPPER}} td.ec_cartitem_image' => 'width: {{SIZE}}{{UNIT}};',
					),
				)
			);
			$this->add_responsive_control(
				'cart_image_radius',
				array(
					'label'      => __( 'Image border radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px', '%' ),
					'selectors'  => array( '{{WRAPPER}} .ec_cart_table_image > img, {{WRAPPER}} .ec_cartitem_image > img' => 'border-radius: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->ec_text_controls(
				'cart_title',
				'.ec_cartitem_title',
				array(
					'typography' => \Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_PRIMARY,
					'color'      => '',
					'color_var'  => 'wpec-el-title-color',
					'font_var'   => true,
					'hover'      => true,
				)
			);
			$this->ec_text_controls( 'cart_price', '.ec_cart_table_price, .ec_cart_table_total, td.ec_cartitem_price, td.ec_cartitem_total, .ec_cart_table_details_content dl', array( 'font_var' => true ) );
			$this->add_control(
				'cart_row_background',
				array(
					'label'     => __( 'Row background colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .ec_cart_table_row, {{WRAPPER}} .ec_cart > tbody > tr.ec_cartitem_row > td' => 'background-color: {{VALUE}};' ),
				)
			);
			$this->ec_divider_control( 'cart_row_divider', '.ec_cart > tbody > tr.ec_cartitem_row', 'bottom', array( '.ec_cart_table_row ~ .ec_cart_table_row' => 'border-top: 1px solid {{VALUE}};' ) );
			$this->add_control(
				'cart_quantity_heading',
				array(
					'label'     => __( 'Quantity and remove', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->ec_typography( 'cart_quantity_typography', '.ec_table_quantity_box, .ec_cartitem_quantity_table .ec_quantity', null, true, array( 'font_size' ) );
			$this->add_control(
				'cart_quantity_color',
				array(
					'label'     => __( 'Quantity text colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .ec_table_quantity_box, {{WRAPPER}} .ec_cartitem_quantity_table .ec_quantity' => 'color: {{VALUE}} !important;' ),
				)
			);
			$this->add_control(
				'cart_quantity_border',
				array(
					'label'     => __( 'Quantity border colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .ec_table_quantity_box, {{WRAPPER}} .ec_cartitem_quantity_table .ec_quantity' => 'border-color: {{VALUE}} !important;' ),
				)
			);
			$this->ec_quantity_box_controls( 'cart_quantity', '.ec_table_quantity_box, .ec_cartitem_quantity_table .ec_quantity' );
			$this->add_control(
				'cart_remove_color',
				array(
					'label'     => __( 'Remove colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}}' => '--wpec-el-remove-color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'cart_remove_hover_color',
				array(
					'label'     => __( 'Remove hover colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}}' => '--wpec-el-remove-hover-color: {{VALUE}};' ),
				)
			);
			$this->ec_typography( 'cart_remove_typography', '.ec_cart_table .ec_cartitem_delete', null, true );
			$this->add_control(
				'cart_subtotal_heading',
				array(
					'label'     => __( 'Subtotal', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->ec_text_controls( 'cart_subtotal', '.ec_cart_table_subtotal, .ec_cart_table_message', array( 'font_var' => true ) );
			$this->ec_typography( 'cart_subtotal_amount_typography', '.ec_cart_table_subtotal_amount', null, true );
			$this->add_control(
				'cart_continue_heading',
				array(
					'label'     => __( 'Continue shopping link', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->ec_text_controls(
				'cart_continue',
				'.ec_cart_table_continue_shopping',
				array(
					'font_var' => true,
					'hover'    => true,
					'color'    => '',
				)
			);
			$this->end_controls_section();

			$this->ec_button_section(
				'cart_checkout_button',
				__( 'Cart Checkout button', 'wp-easycart' ),
				'.ec_cart_table_checkout_button, .ec_cart_button_checkout',
				array(
					'vars'       => 'cta',
					'font_var'   => true,
					'width'      => true,
					'background' => '',
				)
			);
		}

		/**
		 * The one-page checkout's express area ( wallet and PayPal buttons at the top ) and its "or" divider.
		 */
		protected function ec_express_section() {
			$this->start_controls_section(
				'express_style',
				array(
					'label' => __( 'Express checkout', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->ec_text_controls(
				'express_header',
				'.ec_cart_express_checkout_header, .ec_cart_express_checkout_divider > div',
				array(
					'font_var' => true,
					'color'    => '',
				)
			);
			$this->add_control(
				'express_divider_color',
				array(
					'label'     => __( 'Divider colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .ec_cart_express_checkout_divider::before, {{WRAPPER}} .ec_cart_express_checkout_divider::after' => 'border-top-color: {{VALUE}};' ),
				)
			);
			$this->add_responsive_control(
				'express_gap',
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
					'selectors'  => array( '{{WRAPPER}} .ec_cart_express_button_container' => 'column-gap: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_responsive_control(
				'express_spacing',
				array(
					'label'      => __( 'Space below', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 80,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .ec_cart_express_checkout' => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->end_controls_section();
		}

		/**
		 * Classes on the widget's box.
		 *
		 * @param array $settings Settings.
		 * @return string[]
		 */
		protected function ec_wrapper_classes( $settings ) {
			$classes = array( 'wpec-el', 'wpec-el-cart-page', 'wpec-checkout' );
			if ( ! $this->ec_on( $settings, 'ec_show_steps' ) ) {
				$classes[] = 'wpec-hide-steps';
			}
			return array_merge( $classes, $this->ec_extra_classes( $settings ) );
		}

		/**
		 * More classes ( WP EasyCart PRO: summary position, hidden parts and totals lines ).
		 *
		 * @param array $settings Settings.
		 * @return string[]
		 */
		protected function ec_extra_classes( $settings ) {
			unset( $settings );
			return array();
		}

		/**
		 * Printed above the checkout ( WP EasyCart PRO: text blocks placed there ).
		 *
		 * @param array $settings Settings.
		 */
		protected function ec_before_checkout( $settings ) {
			unset( $settings );
		}

		/**
		 * Printed below the checkout ( WP EasyCart PRO: text blocks placed there ).
		 *
		 * @param array $settings Settings.
		 */
		protected function ec_after_checkout( $settings ) {
			unset( $settings );
		}

		/** Draw. */
		protected function render() {
			$settings = $this->get_settings_for_display();
			$classes  = $this->ec_wrapper_classes( $settings );

			if ( $this->ec_is_editor() ) {
				$empty = ( isset( $settings['ec_preview'] ) && 'empty' === $settings['ec_preview'] );
				if ( ! WP_EasyCart_Elementor_Checkout_Module::editing_cart_page() ) {
					$this->ec_editor_notice( __( 'Shoppers see the checkout on your cart page.', 'wp-easycart' ), __( 'Put this widget on the page chosen as the cart page ( Settings › Initial setup ), or choose this page there.', 'wp-easycart' ) );
				}
				$copies = WP_EasyCart_Elementor_Checkout_Module::template_copy_note( 'checkout' );
				if ( '' !== $copies ) {
					$this->ec_editor_notice( __( 'Some settings may do nothing.', 'wp-easycart' ), $copies );
				}
				if ( $empty ) {
					$classes[] = 'wpec-cart--empty';
				}
				echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">';
				if ( $empty ) {
					$this->ec_render_empty( $settings );
				} else {
					$this->ec_before_checkout( $settings );
					WP_EasyCart_Elementor_Checkout_Module::push_texts( $settings );
					$this->ec_render_sample( $settings );
					WP_EasyCart_Elementor_Checkout_Module::pop_texts();
					$this->ec_after_checkout( $settings );
				}
				echo '</div>';
				return;
			}

			if ( ! WP_EasyCart_Elementor_Checkout_Module::is_cart_page() || ! WP_EasyCart_Elementor_Checkout_Module::owns( $this->get_name() ) ) {
				return;
			}
			echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '" data-wpec-cart-flow="1"' . WP_EasyCart_Elementor_Checkout_Module::context_attributes( $this->get_id() ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- context_attributes() escapes.
			$this->ec_before_checkout( $settings );
			WP_EasyCart_Elementor_Checkout_Module::render_flow( $this->get_name(), $settings );
			$this->ec_after_checkout( $settings );
			$this->ec_render_empty( $settings, true );
			echo '</div>';
		}

		/**
		 * The editor sample's text blocks at a placement ( WP EasyCart PRO ).
		 *
		 * @param array  $settings Widget settings.
		 * @param string $position Placement.
		 */
		protected function ec_sample_slot( $settings, $position ) {
			unset( $settings, $position );
		}

		/**
		 * The editor's sample: EasyCart's checkout markup ( classes only, no ids or scripts ) with the store's first products.
		 *
		 * @param array $settings Widget settings ( WP EasyCart PRO draws its text blocks in the sample ).
		 */
		protected function ec_render_sample( $settings = array() ) {
			$onepage  = function_exists( 'wp_easycart_onepage_active' ) && wp_easycart_onepage_active();
			$lines    = WP_EasyCart_Elementor_Checkout_Module::sample_lines( 2 );
			$subtotal = 0;
			foreach ( $lines as $line ) {
				$subtotal += $line['amount'];
			}
			$shipping = 5;
			$tax      = round( $subtotal * 0.07, 2 );
			$t        = function ( $key, $fallback, $section ) {
				return wp_strip_all_tags( WP_EasyCart_Elementor_Checkout_Module::text( $key, $fallback, $section ) );
			};
			$field    = function ( $label, $value = '', $half = '' ) {
				$html = '<label>' . esc_html( $label ) . '</label><input type="text" value="' . esc_attr( $value ) . '" tabindex="-1" readonly />';
				return ( '' === $half ) ? '<div class="ec_cart_input_row">' . $html . '</div>' : '<div class="ec_cart_input_' . esc_attr( $half ) . '_half">' . $html . '</div>';
			};
			echo '<div class="ec_cart_page wpec-checkout-sample">';
			if ( $onepage ) {
				echo '<div class="ec_cart_breadcrumbs_v2"><span class="ec_cart_breadcrumb_item_v2">' . esc_html( $t( 'breadcrumb_cart', 'Cart', 'cart_onepage' ) ) . '</span> <span class="dashicons dashicons-arrow-right-alt2"></span> <span class="ec_cart_breadcrumb_item_v2">' . esc_html( $t( 'breadcrumb_information', 'Information', 'cart_onepage' ) ) . '</span> <span class="dashicons dashicons-arrow-right-alt2"></span> <span class="ec_cart_breadcrumb_item_v2">' . esc_html( $t( 'breadcrumb_shipping', 'Shipping', 'cart_onepage' ) ) . '</span> <span class="dashicons dashicons-arrow-right-alt2"></span> <span class="ec_cart_breadcrumb_item_v2">' . esc_html( $t( 'breadcrumb_payment', 'Payment', 'cart_onepage' ) ) . '</span></div>';
			} else {
				echo '<div class="ec_cart_breadcrumbs"><div class="ec_cart_breadcrumb ec_inactive">' . esc_html( $t( 'cart_title', 'SHOPPING CART', 'cart' ) ) . '</div><div class="ec_cart_breadcrumb_divider"></div><div class="ec_cart_breadcrumb">' . esc_html( $t( 'cart_checkout_details_title', 'CHECKOUT DETAILS', 'cart' ) ) . '</div><div class="ec_cart_breadcrumb_divider"></div><div class="ec_cart_breadcrumb ec_inactive">' . esc_html( $t( 'cart_submit_payment_title', 'SUBMIT PAYMENT', 'cart' ) ) . '</div></div>';
			}
			echo '<div class="ec_cart_left">';
			echo '<div class="ec_cart_header ec_top">' . esc_html( $t( 'contact', 'Contact', 'cart_onepage' ) ) . '</div>';
			echo $field( $t( 'cart_contact_information_email', 'Email', 'cart_contact_information' ) . '*', 'shopper@example.com' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the closure.
			$this->ec_sample_slot( $settings, 'contact' );
			echo '<div class="ec_cart_header">' . esc_html( $t( 'cart_shipping_information_title', 'Shipping Address', 'cart_shipping_information' ) ) . '</div>';
			echo '<div class="ec_cart_input_row">' . $field( $t( 'cart_shipping_information_first_name', 'First Name', 'cart_shipping_information' ) . '*', 'Anna', 'left' ) . $field( $t( 'cart_shipping_information_last_name', 'Last Name', 'cart_shipping_information' ), 'Smith', 'right' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the closure.
			echo $field( $t( 'cart_shipping_information_address', 'Address', 'cart_shipping_information' ), '12 Market Street' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the closure.
			echo '<div class="ec_cart_input_row">' . $field( $t( 'cart_shipping_information_city', 'City', 'cart_shipping_information' ), 'Springfield', 'left' ) . $field( $t( 'cart_shipping_information_zip', 'Zip Code', 'cart_shipping_information' ), '12345', 'right' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the closure.
			$this->ec_sample_slot( $settings, 'shipping' );
			echo '<div class="ec_cart_header">' . esc_html( $t( 'cart_shipping_method_title', 'Shipping Method', 'cart_shipping_method' ) ) . '</div>';
			echo '<div class="ec_cart_shipping_table"><div class="ec_cart_shipping_method_row ec_method_selected"><input type="radio" checked tabindex="-1" /> <span class="label">' . esc_html__( 'Standard', 'wp-easycart' ) . '</span> <span class="price">' . esc_html( WP_EasyCart_Elementor_Checkout_Module::price( $shipping ) ) . '</span></div><div class="ec_cart_shipping_method_row"><input type="radio" tabindex="-1" /> <span class="label">' . esc_html__( 'Express', 'wp-easycart' ) . '</span> <span class="price">' . esc_html( WP_EasyCart_Elementor_Checkout_Module::price( 15 ) ) . '</span></div></div>';
			$this->ec_sample_slot( $settings, 'delivery' );
			echo '<div class="ec_cart_header">' . esc_html( $t( 'cart_payment_information_title', 'Payment Method', 'cart_payment_information' ) ) . '</div>';
			echo '<div class="ec_cart_payment_table"><div class="ec_cart_payment_table_row ec_payment_row_selected"><div class="ec_cart_payment_table_column"><input type="radio" checked tabindex="-1" /> ' . esc_html__( 'Credit card', 'wp-easycart' ) . '</div></div></div>';
			echo $field( $t( 'cart_payment_information_card_holder_name', 'Card Holder Name', 'cart_payment_information' ), 'Anna Smith' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the closure.
			echo '<div class="ec_cart_error_row" style="display:block;">' . esc_html__( 'A message like this shows next to a field that needs attention.', 'wp-easycart' ) . '</div>';
			$this->ec_sample_slot( $settings, 'review' );
			$place = '<input type="submit" class="ec_cart_button wpec-el-place-order" value="' . esc_attr( $t( 'cart_payment_information_submit_order_button', 'Submit Order', 'cart_payment_information' ) ) . '" tabindex="-1" onclick="return false;" />';
			if ( $onepage ) {
				echo '<div class="ec_cart_bottom_nav_v2 wpec-el-place-order-row"><div class="ec_cart_bottom_nav_right ec_cart_button_column">' . $place . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
			} else {
				echo '<div class="ec_cart_button_row wpec-el-place-order-row">' . $place . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
			}
			echo '</div>';
			echo '<div class="ec_cart_right' . ( $onepage ? ' ec_cart_right_v2' : '' ) . '">';
			echo '<div class="ec_cart_header ec_top">' . esc_html( $onepage ? $t( 'order_summary', 'Order Summary', 'cart_onepage' ) : $t( 'your_cart_title', 'YOUR CART', 'cart' ) ) . '</div>';
			foreach ( $lines as $n => $line ) {
				if ( $onepage ) {
					echo '<div class="ec_cart_price_row_v2"><div class="ec_cart_image_row_v2">' . ( '' !== $line['image'] ? '<img src="' . esc_url( $line['image'] ) . '" alt="" />' : '' ) . '</div><div class="ec_cart_price_row_label_v2">' . esc_html( $line['title'] ) . ( $line['quantity'] > 1 ? ' x ' . esc_html( (string) $line['quantity'] ) : '' ) . ( '' !== $line['options'] ? '<dl><dt>' . esc_html( $line['options'] ) . '</dt></dl>' : '' ) . '</div><div class="ec_cart_price_row_total_v2">' . esc_html( $line['total'] ) . '</div></div>';
				} else {
					echo '<div class="ec_cart_price_row ec_cart_price_row_cartitem_' . esc_attr( (string) $n ) . '"><div class="ec_cart_price_row_label">' . esc_html( $line['title'] ) . ( $line['quantity'] > 1 ? ' x ' . esc_html( (string) $line['quantity'] ) : '' ) . '</div><div class="ec_cart_price_row_total">' . esc_html( $line['total'] ) . '</div></div>';
				}
			}
			echo '<div class="ec_cart_input_row ec_cart_input_button_row ec_cart_coupon_part"><div class="ec_cart_input_column"><input type="text" placeholder="' . esc_attr( $t( 'cart_enter_coupon', 'Enter Coupon Code', 'cart_coupons' ) ) . '" tabindex="-1" /></div><div class="ec_cart_button_column"><div class="ec_cart_button">' . esc_html( $t( 'cart_simple_apply', 'Apply', 'cart_coupons' ) ) . '</div></div></div>';
			$rows = array(
				array( 'ec_cart_price_row_subtotal', $t( 'cart_totals_subtotal', 'Cart Subtotal', 'cart_totals' ), $subtotal ),
				array( 'ec_cart_price_row_shipping_total', $t( 'cart_totals_shipping', 'Shipping', 'cart_totals' ), $shipping ),
				array( 'ec_cart_price_row_tax_total', $t( 'cart_totals_tax', 'Tax', 'cart_totals' ), $tax ),
				array( 'ec_order_total', $t( 'cart_totals_grand_total', 'Grand Total', 'cart_totals' ), $subtotal + $shipping + $tax ),
			);
			foreach ( $rows as $row ) {
				echo '<div class="ec_cart_price_row ' . esc_attr( $row[0] ) . '"><div class="ec_cart_price_row_label">' . esc_html( $row[1] ) . '</div><div class="ec_cart_price_row_total">' . esc_html( WP_EasyCart_Elementor_Checkout_Module::price( $row[2] ) ) . '</div></div>';
			}
			$this->ec_sample_slot( $settings, 'summary' );
			echo '</div><div style="clear:both;"></div></div>';
		}
	}

endif;
