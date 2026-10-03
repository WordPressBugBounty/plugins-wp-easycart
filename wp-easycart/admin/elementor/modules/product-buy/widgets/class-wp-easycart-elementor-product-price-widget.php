<?php
/**
 * Elementor widget "Product Price" ( wp_easycart_product_price, 6.0.2 ).
 *
 * The price as the product page shows it ( EasyCart's price template: sale and regular price, the VAT split when the store
 * shows it, custom price labels, the Offers price preview, promotion lines ), a quantity discount table and the "log in for
 * pricing" message. It follows the options chosen in an Add to Cart widget on the same page ( data-wpec-linked-* ), the
 * Offers price included ( product-buy.js ). Replaces wp_easycart_product_details_price.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/trait-wp-easycart-product-buy-controls.php';

if ( ! class_exists( 'WP_EasyCart_Elementor_Product_Price_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Widget_Base' ) ) :

	/**
	 * Product Price.
	 */
	class WP_EasyCart_Elementor_Product_Price_Widget extends WP_EasyCart_Elementor_Widget_Base {

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
		protected static $ec_help = 'product-price';

		/**
		 * The offer message icon while render() draws ( see callout_icon() ).
		 *
		 * @var string|null
		 */
		private $callout_icon = null;

		/**
		 * Widget name.
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_product_price';
		}

		/**
		 * Widget title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Product Price', 'wp-easycart' );
		}

		/**
		 * Widget icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-product-price';
		}

		/**
		 * Search keywords.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array( 'price', 'product price', 'sale', 'discount', 'vat', 'tiers', 'quantity discount', 'offer', 'you save', 'product' );
		}

		/**
		 * Scripts.
		 *
		 * @return array
		 */
		public function get_script_depends() {
			return array_merge( parent::get_script_depends(), array( WP_EasyCart_Product_Buy::HANDLE ) );
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
				'section_price',
				array(
					'label' => __( 'Price', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->add_control(
				'show_regular_price',
				array(
					'label'       => __( 'Regular price when on sale', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SWITCHER,
					'default'     => 'yes',
					'description' => __( 'Crossed out next to the sale price.', 'wp-easycart' ),
				)
			);
			$this->add_control(
				'regular_price_position',
				array(
					'label'        => __( 'Regular price', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::CHOOSE,
					'default'      => 'before',
					'toggle'       => false,
					'options'      => array(
						'before' => array(
							'title' => __( 'Before the price', 'wp-easycart' ),
							'icon'  => 'eicon-h-align-left',
						),
						'after'  => array(
							'title' => __( 'After the price', 'wp-easycart' ),
							'icon'  => 'eicon-h-align-right',
						),
					),
					'prefix_class' => 'wpec-price-regular-',
					'condition'    => array(
						'show_regular_price' => 'yes',
					),
				)
			);
			$this->add_control(
				'offer_prices',
				array(
					'label'        => __( 'Offer on a sale price', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SELECT,
					'default'      => 'regular_final',
					'options'      => array(
						'regular_final' => __( 'Regular price, then the offer price', 'wp-easycart' ),
						'sale_final'    => __( 'Sale price, then the offer price', 'wp-easycart' ),
						'all'           => __( 'All three prices', 'wp-easycart' ),
					),
					'description'  => __( 'Which prices show when a running offer lowers a product that is already on sale.', 'wp-easycart' ),
					'prefix_class' => 'wpec-price-offers-',
					'condition'    => array(
						'show_regular_price' => 'yes',
					),
				)
			);
			$this->add_control(
				'layout',
				array(
					'label'        => __( 'Layout', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SELECT,
					'default'      => 'inline',
					'options'      => array(
						'inline'  => __( 'On one line', 'wp-easycart' ),
						'stacked' => __( 'One under the other', 'wp-easycart' ),
					),
					'prefix_class' => 'wpec-price-layout-',
				)
			);
			$this->add_control(
				'prefix_text',
				array(
					'label'       => __( 'Text before the price', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => '',
					'placeholder' => __( 'From', 'wp-easycart' ),
					'separator'   => 'before',
					'ai'          => array( 'active' => false ),
				)
			);
			$this->add_control(
				'suffix_text',
				array(
					'label'       => __( 'Text after the price', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => '',
					'placeholder' => __( 'incl. VAT', 'wp-easycart' ),
					'ai'          => array( 'active' => false ),
				)
			);
			$this->add_control(
				'show_savings',
				array(
					'label'       => __( 'What the shopper saves', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SWITCHER,
					'default'     => '',
					'separator'   => 'before',
					'description' => __( 'A line under the price, e.g. "You save $10.00 ( 20% )", when the product is on sale or an offer lowers it.', 'wp-easycart' ),
				)
			);
			$this->add_control(
				'savings_text',
				array(
					'label'       => __( 'Savings text', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => '',
					'placeholder' => __( 'You save [amount] ([percent]%)', 'wp-easycart' ),
					'description' => __( '[amount] is the amount saved, [percent] the percentage.', 'wp-easycart' ),
					'condition'   => array(
						'show_savings' => 'yes',
					),
					'ai'          => array( 'active' => false ),
				)
			);
			$this->add_control(
				'show_tiers',
				array(
					'label'       => __( 'Quantity discounts', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SWITCHER,
					'default'     => 'yes',
					'separator'   => 'before',
					'description' => __( 'A table of the product’s quantity prices, when it has them.', 'wp-easycart' ),
				)
			);
			$this->add_control(
				'show_login',
				array(
					'label'       => __( 'Log in for price message', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SWITCHER,
					'default'     => 'yes',
					'description' => __( 'For products whose price is shown to signed-in customers only.', 'wp-easycart' ),
				)
			);
			$this->add_control(
				'login_style',
				array(
					'label'     => __( 'Log in link shows as', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'default'   => 'text',
					'options'   => array(
						'text'   => __( 'A text link', 'wp-easycart' ),
						'button' => __( 'A button', 'wp-easycart' ),
					),
					'condition' => array(
						'show_login' => 'yes',
					),
				)
			);
			$this->end_controls_section();

			$this->start_controls_section(
				'section_offer_message',
				array(
					'label' => __( 'Offer and promotion messages', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->add_control(
				'show_offer_message',
				array(
					'label'        => __( 'Offer message', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'default'      => 'yes',
					'description'  => __( 'The message a running offer shows on its products ( with its countdown ).', 'wp-easycart' ),
					'prefix_class' => 'wpec-price-callouts-',
				)
			);
			$this->add_control(
				'offer_message_icon',
				array(
					'label'     => __( 'Offer message icon', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::ICONS,
					'skin'      => 'inline',
					'condition' => array(
						'show_offer_message' => 'yes',
						'hide_offer_icon!'   => 'yes',
					),
				)
			);
			$this->add_control(
				'hide_offer_icon',
				array(
					'label'        => __( 'Hide the icon', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'default'      => '',
					'prefix_class' => 'wpec-price-callout-noicon-',
					'condition'    => array(
						'show_offer_message' => 'yes',
					),
				)
			);
			$this->add_control(
				'show_promotion',
				array(
					'label'        => __( 'Promotion line', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'default'      => 'yes',
					'separator'    => 'before',
					'description'  => __( 'What a promotion takes off, when the store shows promotion savings.', 'wp-easycart' ),
					'prefix_class' => 'wpec-price-promo-',
				)
			);
			$this->end_controls_section();

			$this->register_style_controls();
		}

		/**
		 * Style tab.
		 */
		private function register_style_controls() {
			$this->start_controls_section(
				'style_price',
				array(
					'label' => __( 'Price', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->pb_align();
			$this->pb_color( 'price_color', __( 'Color', 'wp-easycart' ), '--wpec-p-price' );
			$this->pb_typography( 'price_typography', __( 'Typography', 'wp-easycart' ), '{{WRAPPER}} .wpec-price .ec_product_price_ele, {{WRAPPER}} .wpec-price .ec_product_sale_price_ele, {{WRAPPER}} .wpec-price .ec_offer_price_preview', '' );
			$this->pb_size( 'price_gap', __( 'Space between prices', 'wp-easycart' ), '--wpec-p-gap' );
			$this->pb_size( 'block_gap', __( 'Space between rows', 'wp-easycart' ), '--wpec-p-rows-gap', array( 'description' => __( 'Between the price, the messages and the quantity discount table.', 'wp-easycart' ) ) );
			$this->end_controls_section();

			$this->start_controls_section(
				'style_sale_price',
				array(
					'label' => __( 'Sale price', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->pb_color( 'sale_price_color', __( 'Color', 'wp-easycart' ), '--wpec-p-sale' );
			$this->pb_typography( 'sale_price_typography', __( 'Typography', 'wp-easycart' ), '{{WRAPPER}} .wpec-price .ec_product_sale_price_ele, {{WRAPPER}} .wpec-price .ec_offer_price_preview', '' );
			$this->end_controls_section();

			$this->start_controls_section(
				'style_offer_price',
				array(
					'label' => __( 'Offer price', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->add_control(
				'offer_price_note',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => esc_html__( 'The price a running offer lowers a product to. It follows the sale price until you style it here.', 'wp-easycart' ),
					'content_classes' => 'elementor-descriptor',
				)
			);
			$this->pb_color( 'offer_price_color', __( 'Color', 'wp-easycart' ), '--wpec-p-offer' );
			$this->pb_typography( 'offer_price_typography', __( 'Typography', 'wp-easycart' ), '{{WRAPPER}} .wpec-price .ec_offer_price_preview, {{WRAPPER}} .wpec-price .ec_details_price_has_offer .ec_product_sale_price_ele, {{WRAPPER}} .wpec-price .ec_details_price_has_offer .ec_product_price_ele', '' );
			$this->end_controls_section();

			$this->start_controls_section(
				'style_regular_price',
				array(
					'label'     => __( 'Regular price', 'wp-easycart' ),
					'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
					'condition' => array(
						'show_regular_price' => 'yes',
					),
				)
			);
			$this->pb_color( 'regular_price_color', __( 'Color', 'wp-easycart' ), '--wpec-p-regular' );
			$this->pb_typography( 'regular_price_typography', __( 'Typography', 'wp-easycart' ), '{{WRAPPER}} .wpec-price .ec_product_old_price_ele, {{WRAPPER}} .wpec-price .ec_offer_price_strike', 'text' );
			$this->pb_color( 'strike_color', __( 'Line color', 'wp-easycart' ), '--wpec-p-strike-color', array( 'separator' => 'before' ) );
			$this->pb_size(
				'strike_thickness',
				__( 'Line thickness', 'wp-easycart' ),
				'--wpec-p-strike-width',
				array(
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 1,
							'max' => 6,
						),
					),
				)
			);
			$this->end_controls_section();

			$this->start_controls_section(
				'style_affixes',
				array(
					'label' => __( 'Text before and after the price', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->pb_color( 'affix_color', __( 'Color', 'wp-easycart' ), '--wpec-p-affix' );
			$this->pb_typography( 'affix_typography', __( 'Typography', 'wp-easycart' ), '{{WRAPPER}} .wpec-price .wpec-price__prefix, {{WRAPPER}} .wpec-price .wpec-price__suffix', 'text' );
			$this->end_controls_section();

			$this->start_controls_section(
				'style_savings',
				array(
					'label'     => __( 'What the shopper saves', 'wp-easycart' ),
					'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
					'condition' => array(
						'show_savings' => 'yes',
					),
				)
			);
			$this->pb_color( 'savings_color', __( 'Text color', 'wp-easycart' ), '--wpec-p-save-color' );
			$this->pb_color( 'savings_background_color', __( 'Background', 'wp-easycart' ), '--wpec-p-save-bg' );
			$this->pb_typography( 'savings_typography', __( 'Typography', 'wp-easycart' ), '{{WRAPPER}} .wpec-price .wpec-price__save', 'text' );
			$this->pb_dimensions( 'savings_padding', __( 'Padding', 'wp-easycart' ), '{{WRAPPER}} .wpec-price .wpec-price__save' );
			$this->pb_dimensions( 'savings_radius', __( 'Border radius', 'wp-easycart' ), '{{WRAPPER}} .wpec-price .wpec-price__save', 'border-radius' );
			$this->end_controls_section();

			$this->register_offer_message_style();

			$this->start_controls_section(
				'style_promotion',
				array(
					'label'     => __( 'Promotion line', 'wp-easycart' ),
					'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
					'condition' => array(
						'show_promotion' => 'yes',
					),
				)
			);
			$this->pb_color( 'promo_color', __( 'Text color', 'wp-easycart' ), '--wpec-p-promo', array( 'selectors' => array( '{{WRAPPER}} .wpec-price .ec_details_price_promo_discount' => 'color: {{VALUE}};' ) ) );
			$this->pb_typography( 'promo_typography', __( 'Typography', 'wp-easycart' ), '{{WRAPPER}} .wpec-price .ec_details_price_promo_discount', 'text' );
			$this->pb_icon_style( 'promo_icon', '{{WRAPPER}} .wpec-price .ec_details_price_promo_discount .dashicons' );
			$this->end_controls_section();

			$this->start_controls_section(
				'style_price_label',
				array(
					'label' => __( 'Price label', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->add_control(
				'price_label_note',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => esc_html__( 'The label set on the product ( for example "per box" ), next to the price.', 'wp-easycart' ),
					'content_classes' => 'elementor-descriptor',
				)
			);
			$this->pb_color( 'price_label_color', __( 'Color', 'wp-easycart' ), '--wpec-p-label', array( 'selectors' => array( '{{WRAPPER}} .wpec-price .ec_details_price_label' => 'color: {{VALUE}};' ) ) );
			$this->pb_typography( 'price_label_typography', __( 'Typography', 'wp-easycart' ), '{{WRAPPER}} .wpec-price .ec_details_price_label', 'text' );
			$this->end_controls_section();

			$this->start_controls_section(
				'style_extras',
				array(
					'label' => __( 'Quantity discounts and messages', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->pb_typography( 'tiers_typography', __( 'Table typography', 'wp-easycart' ), '{{WRAPPER}} .wpec-price__tiers', 'text' );
			$this->pb_color( 'tiers_border_color', __( 'Table lines', 'wp-easycart' ), '--wpec-p-tier-border' );
			$this->pb_typography( 'tiers_caption_typography', __( 'Title typography', 'wp-easycart' ), '{{WRAPPER}} .wpec-price__tiers caption', 'text' );
			$this->pb_color( 'tiers_caption_color', __( 'Title color', 'wp-easycart' ), '--wpec-p-tier-caption' );
			$this->pb_color( 'tiers_header_background_color', __( 'Header background', 'wp-easycart' ), '--wpec-p-tier-head-bg' );
			$this->pb_color( 'tiers_header_color', __( 'Header text color', 'wp-easycart' ), '--wpec-p-tier-head-color' );
			$this->pb_dimensions( 'tiers_cell_padding', __( 'Cell padding', 'wp-easycart' ), '{{WRAPPER}} .wpec-price__tiers th, {{WRAPPER}} .wpec-price__tiers td' );
			$this->pb_color( 'note_color', __( 'Message color', 'wp-easycart' ), '--wpec-p-note', array( 'separator' => 'before' ) );
			$this->pb_typography( 'note_typography', __( 'Message typography', 'wp-easycart' ), '{{WRAPPER}} .wpec-price .wpec-price__note', 'text' );
			$this->end_controls_section();

			$this->start_controls_section(
				'style_login_button',
				array(
					'label'     => __( 'Log in button', 'wp-easycart' ),
					'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
					'condition' => array(
						'show_login'  => 'yes',
						'login_style' => 'button',
					),
				)
			);
			$this->pb_typography( 'login_button_typography', __( 'Typography', 'wp-easycart' ), '{{WRAPPER}} .wpec-price .wpec-price__login', '' );
			$this->start_controls_tabs( 'login_button_tabs' );
			$this->start_controls_tab( 'login_button_tab_normal', array( 'label' => __( 'Normal', 'wp-easycart' ) ) );
			$this->pb_color( 'login_button_color', __( 'Text color', 'wp-easycart' ), '--wpec-p-login-color' );
			$this->pb_color( 'login_button_background_color', __( 'Background', 'wp-easycart' ), '--wpec-p-login-bg' );
			$this->end_controls_tab();
			$this->start_controls_tab( 'login_button_tab_hover', array( 'label' => __( 'Hover', 'wp-easycart' ) ) );
			$this->pb_color( 'login_button_hover_color', __( 'Text color', 'wp-easycart' ), '--wpec-p-login-hover-color' );
			$this->pb_color( 'login_button_hover_background_color', __( 'Background', 'wp-easycart' ), '--wpec-p-login-hover-bg' );
			$this->end_controls_tab();
			$this->end_controls_tabs();
			$this->pb_border( 'login_button_border', '{{WRAPPER}} .wpec-price .wpec-price__login', array( 'separator' => 'before' ) );
			$this->pb_dimensions( 'login_button_radius', __( 'Border radius', 'wp-easycart' ), '{{WRAPPER}} .wpec-price .wpec-price__login', 'border-radius' );
			$this->pb_dimensions( 'login_button_padding', __( 'Padding', 'wp-easycart' ), '{{WRAPPER}} .wpec-price .wpec-price__login' );
			$this->end_controls_section();

			$this->pb_box_section( '{{WRAPPER}} .wpec-price' );
		}

		/**
		 * Style › Offer message: the running offer's message under the price ( PRO's .ec_offer_product_callout, styled by its
		 * --ec-offer-accent / --ec-offer-accent-bg, which the colours set on this widget only ).
		 */
		private function register_offer_message_style() {
			$callout = '{{WRAPPER}} .wpec-price .ec_offer_product_callout';
			$this->start_controls_section(
				'style_offer_message',
				array(
					'label'     => __( 'Offer message', 'wp-easycart' ),
					'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
					'condition' => array(
						'show_offer_message' => 'yes',
					),
				)
			);
			$this->add_responsive_control(
				'offer_message_align',
				array(
					'label'                => __( 'Alignment', 'wp-easycart' ),
					'type'                 => \Elementor\Controls_Manager::CHOOSE,
					'options'              => array(
						'start'  => array(
							'title' => __( 'Start', 'wp-easycart' ),
							'icon'  => 'eicon-text-align-left',
						),
						'center' => array(
							'title' => __( 'Center', 'wp-easycart' ),
							'icon'  => 'eicon-text-align-center',
						),
						'end'    => array(
							'title' => __( 'End', 'wp-easycart' ),
							'icon'  => 'eicon-text-align-right',
						),
					),
					'selectors_dictionary' => array(
						'start'  => 'flex-start',
						'center' => 'center',
						'end'    => 'flex-end',
					),
					'selectors'            => array(
						$callout => 'justify-content: {{VALUE}};',
					),
				)
			);
			$this->pb_color( 'offer_message_accent_color', __( 'Accent color', 'wp-easycart' ), '--ec-offer-accent', array( 'description' => __( 'The side line, the icon and the countdown.', 'wp-easycart' ) ) );
			$this->pb_color( 'offer_message_background_color', __( 'Background', 'wp-easycart' ), '--ec-offer-accent-bg' );
			$this->pb_color( 'offer_message_text_color', __( 'Text color', 'wp-easycart' ), '--wpec-p-callout-color' );
			$this->pb_typography( 'offer_message_typography', __( 'Typography', 'wp-easycart' ), $callout, 'text' );
			$this->pb_heading( 'offer_message_icon_heading', __( 'Icon', 'wp-easycart' ), array( 'condition' => array( 'hide_offer_icon!' => 'yes' ) ) );
			$this->pb_icon_style( 'offer_message_icon', $callout . ' .ec_offer_product_callout_icon', array( 'condition' => array( 'hide_offer_icon!' => 'yes' ) ) );
			$this->pb_size( 'offer_message_icon_gap', __( 'Space after the icon', 'wp-easycart' ), array( $callout => 'column-gap: {{SIZE}}{{UNIT}};' ) );
			$this->pb_heading( 'offer_message_box_heading', __( 'Box', 'wp-easycart' ) );
			$this->pb_dimensions( 'offer_message_padding', __( 'Padding', 'wp-easycart' ), $callout );
			$this->pb_border( 'offer_message_border', $callout );
			$this->pb_dimensions( 'offer_message_radius', __( 'Border radius', 'wp-easycart' ), $callout, 'border-radius' );
			$this->pb_shadow( 'offer_message_shadow', $callout );
			$this->pb_size( 'offer_message_gap', __( 'Space between messages', 'wp-easycart' ), '--wpec-p-callout-gap' );
			$this->pb_heading( 'offer_countdown_heading', __( 'Countdown', 'wp-easycart' ) );
			$this->pb_color( 'offer_countdown_label_color', __( 'Label color', 'wp-easycart' ), '--wpec-p-countdown-label' );
			$this->pb_typography( 'offer_countdown_label_typography', __( 'Label typography', 'wp-easycart' ), $callout . ' .ec_offer_countdown_label', 'text' );
			$this->pb_color( 'offer_countdown_clock_color', __( 'Clock color', 'wp-easycart' ), '--wpec-p-countdown-clock' );
			$this->pb_typography( 'offer_countdown_clock_typography', __( 'Clock typography', 'wp-easycart' ), $callout . ' .ec_offer_countdown_clock', 'text' );
			$this->end_controls_section();
		}

		/**
		 * The offer message icon ( filter wp_easycart_offer_callout_icon while this widget draws ).
		 *
		 * @param string $icon Icon markup.
		 * @return string
		 */
		public function callout_icon( $icon ) {
			return ( null === $this->callout_icon ) ? $icon : $this->callout_icon;
		}

		/**
		 * Render.
		 */
		protected function render() {
			$settings = $this->get_settings_for_display();
			$product  = $this->ec_product( $settings );
			if ( ! $product ) {
				$this->ec_no_product_notice( __( 'Product Price', 'wp-easycart' ) );
				return;
			}
			WP_EasyCart_Product_Buy::prepare( $product, $this->ec_is_editor() );
			$on      = function ( $id ) use ( $settings ) {
				return isset( $settings[ $id ] ) && 'yes' === $settings[ $id ];
			};
			$rand    = WP_EasyCart_Product_Buy::next_rand();
			$login   = WP_EasyCart_Product_Buy::login_for_price( $product );
			$regular = $on( 'show_regular_price' );
			$state   = WP_EasyCart_Product_Buy::price_state( $product );
			$price   = '';
			if ( ! $login ) {
				/* The offer message icon: the widget's, none, or PRO's dashicon. */
				$this->callout_icon = null;
				if ( $on( 'hide_offer_icon' ) ) {
					$this->callout_icon = '';
				} else {
					$icon = $this->pb_icon_html( isset( $settings['offer_message_icon'] ) ? $settings['offer_message_icon'] : null );
					if ( '' !== $icon ) {
						$this->callout_icon = '<span class="ec_offer_product_callout_icon ec_offer_product_callout_icon--custom" aria-hidden="true">' . $icon . '</span>';
					}
				}
				add_filter( 'wp_easycart_offer_callout_icon', array( $this, 'callout_icon' ), 20 );
				$price = WP_EasyCart_Product_Buy::price_html(
					$product,
					$rand,
					$regular,
					array(
						'prefix' => isset( $settings['prefix_text'] ) ? sanitize_text_field( (string) $settings['prefix_text'] ) : '',
						'suffix' => isset( $settings['suffix_text'] ) ? sanitize_text_field( (string) $settings['suffix_text'] ) : '',
					)
				);
				remove_filter( 'wp_easycart_offer_callout_icon', array( $this, 'callout_icon' ), 20 );
				$this->callout_icon = null;
			}
			$note = '';
			if ( $login && $on( 'show_login' ) ) {
				$text   = esc_html( wp_strip_all_tags( $login['text'] ) );
				$button = ( '' !== $login['url'] && isset( $settings['login_style'] ) && 'button' === $settings['login_style'] );
				$note   = '<div class="wpec-price__note' . ( $button ? ' wpec-price__note--button' : '' ) . '">' . ( ( '' !== $login['url'] ) ? '<a class="wpec-price__login" href="' . esc_url( $login['url'] ) . '">' . $text . '</a>' : $text ) . '</div>';
			}
			$tiers = $on( 'show_tiers' ) ? $this->tiers_html( $product ) : '';
			if ( '' === trim( wp_strip_all_tags( $price ) ) && '' === $note && '' === $tiers ) {
				$this->ec_editor_notice( __( 'Product Price', 'wp-easycart' ), __( 'This product’s price is hidden from this shopper ( catalog or inquiry mode ), so visitors see nothing here.', 'wp-easycart' ) );
				return;
			}
			$save    = ( '' !== $price && $on( 'show_savings' ) ) ? $this->savings_html( $product, $state, $settings ) : '';
			$classes = 'wpec-el wpec-price' . ( $regular ? '' : ' wpec-price--no-regular' );
			if ( ! $state['hidden'] && $state['list'] > 0 && $regular ) {
				$classes .= ' wpec-price--has-list';
			}
			if ( $state['offer'] ) {
				$classes .= ' wpec-price--has-offer';
			}
			echo '<div class="' . esc_attr( $classes ) . '" data-product-id="' . esc_attr( $product->product_id ) . '">';
			echo $price; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- EasyCart's price template, escaped where it prints.
			echo $save . $note . $tiers; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts in this class.
			echo '</div>';
		}

		/**
		 * The "You save" line ( price_state(): regular − final ). Printed hidden when nothing is saved yet: an option the
		 * shopper picks can change that ( product-buy.js redraws it from data-wpec-* ).
		 *
		 * @param ec_product $product  Product.
		 * @param array      $state    WP_EasyCart_Product_Buy::price_state().
		 * @param array      $settings Settings.
		 * @return string
		 */
		private function savings_html( $product, $state, $settings ) {
			if ( $state['hidden'] || ! isset( $GLOBALS['currency'] ) ) {
				return '';
			}
			$template = ( isset( $settings['savings_text'] ) && '' !== trim( (string) $settings['savings_text'] ) ) ? sanitize_text_field( (string) $settings['savings_text'] ) : wp_strip_all_tags( WP_EasyCart_Product_Buy::text( 'price_you_save', __( 'You save [amount] ([percent]%)', 'wp-easycart' ) ) );
			$amount   = $state['save'];
			/* Stores that show VAT prices save with VAT as the page shows it. */
			if ( $amount > 0 && class_exists( 'wp_easycart_product_schema' ) && method_exists( 'wp_easycart_product_schema', 'shown' ) && (float) $product->vat_rate > 0 && get_option( 'ec_option_show_multiple_vat_pricing' ) ) {
				$amount = wp_easycart_product_schema::shown( $product, $amount, true );
			}
			$text = ( $amount > 0 ) ? str_replace( array( '[amount]', '[percent]' ), array( $GLOBALS['currency']->get_currency_display( $amount ), (string) $state['percent'] ), $template ) : '';
			$out  = '<div class="wpec-price__save" aria-live="polite" data-wpec-save-text="' . esc_attr( $template ) . '" data-wpec-list-price="' . esc_attr( $state['list'] ) . '"';
			if ( $state['offer'] && is_array( $state['rules'] ) ) {
				$out .= ' data-wpec-offer-rules="' . esc_attr( wp_json_encode( $state['rules'] ) ) . '"';
			}
			$out .= ( '' === $text ) ? ' hidden>' : '>';
			$out .= esc_html( $text ) . '</div>';
			return $out;
		}

		/**
		 * The quantity discount table.
		 *
		 * @param ec_product $product Product.
		 * @return string
		 */
		private function tiers_html( $product ) {
			$tiers = WP_EasyCart_Product_Buy::price_tiers( $product );
			if ( ! $tiers || ! isset( $GLOBALS['currency'] ) ) {
				return '';
			}
			$out  = '<table class="wpec-price__tiers">';
			$out .= '<caption>' . esc_html( wp_strip_all_tags( WP_EasyCart_Product_Buy::text( 'price_tiers_title', __( 'Buy more, save more', 'wp-easycart' ) ) ) ) . '</caption>';
			$out .= '<thead><tr><th scope="col">' . esc_html( wp_strip_all_tags( WP_EasyCart_Product_Buy::text( 'price_tiers_quantity', __( 'Quantity', 'wp-easycart' ) ) ) ) . '</th><th scope="col">' . esc_html( wp_strip_all_tags( WP_EasyCart_Product_Buy::text( 'price_tiers_price', __( 'Price each', 'wp-easycart' ) ) ) ) . '</th></tr></thead><tbody>';
			if ( $tiers[0][0] > 1 ) {
				$out .= '<tr><td>1+</td><td>' . esc_html( $GLOBALS['currency']->get_currency_display( $product->price ) ) . '</td></tr>';
			}
			foreach ( $tiers as $tier ) {
				$out .= '<tr><td>' . esc_html( $tier[0] ) . '+</td><td>' . esc_html( $GLOBALS['currency']->get_currency_display( $tier[1] ) ) . '</td></tr>';
			}
			$out .= '</tbody></table>';
			return $out;
		}

		/**
		 * The equivalent shortcode for post_content.
		 *
		 * @return string
		 */
		protected function plain_content_shortcode() {
			return $this->pb_plain_shortcode( 'ec_product_details_price', $this->get_settings_for_display() );
		}
	}

endif;
