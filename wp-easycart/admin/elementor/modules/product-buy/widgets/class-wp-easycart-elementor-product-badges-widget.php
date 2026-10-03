<?php
/**
 * Elementor widget "Product Badges" ( wp_easycart_product_badges, 6.0.2 ).
 *
 * Sale ( word or percent ), New, Low stock and Sold out badges, the tag the merchant set on the product, and the Offers
 * badge. Stock badges follow the option chosen in an Add to Cart widget on the same page. Prints nothing when no badge applies.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/trait-wp-easycart-product-buy-controls.php';

if ( ! class_exists( 'WP_EasyCart_Elementor_Product_Badges_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Widget_Base' ) ) :

	/**
	 * Product Badges.
	 */
	class WP_EasyCart_Elementor_Product_Badges_Widget extends WP_EasyCart_Elementor_Widget_Base {

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
		protected static $ec_help = 'product-badges';

		/**
		 * Widget name.
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_product_badges';
		}

		/**
		 * Widget title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Product Badges', 'wp-easycart' );
		}

		/**
		 * Widget icon ( 6.0.2 round 11: eicon-sale is in no Elementor icon font, so the panel showed no icon ).
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-tags';
		}

		/**
		 * Search keywords.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array( 'badge', 'sale', 'sale flash', 'new', 'sold out', 'label', 'ribbon', 'tag', 'offer', 'product' );
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
		 * The badges ( key => label ) in their default order.
		 *
		 * @return array
		 */
		private function badge_labels() {
			return array(
				'offer' => __( 'Offer', 'wp-easycart' ),
				'sale'  => __( 'Sale', 'wp-easycart' ),
				'new'   => __( 'New', 'wp-easycart' ),
				'low'   => __( 'Low stock', 'wp-easycart' ),
				'out'   => __( 'Sold out', 'wp-easycart' ),
				'tag'   => __( 'The product’s own tag', 'wp-easycart' ),
			);
		}

		/**
		 * An icon control for one badge.
		 *
		 * @param string $key       Badge key.
		 * @param array  $condition Condition.
		 */
		private function icon_control( $key, $condition ) {
			$this->add_control(
				$key . '_icon',
				array(
					'label'     => __( 'Icon', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::ICONS,
					'skin'      => 'inline',
					'condition' => $condition,
				)
			);
		}

		/**
		 * Controls.
		 */
		protected function register_controls() {
			$this->ec_register_product_controls();

			$this->start_controls_section(
				'section_badges',
				array(
					'label' => __( 'Badges', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->add_control(
				'show_sale',
				array(
					'label'   => __( 'On sale', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SWITCHER,
					'default' => 'yes',
				)
			);
			$this->add_control(
				'sale_style',
				array(
					'label'     => __( 'Sale badge shows', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'default'   => 'text',
					'options'   => array(
						'text'    => __( 'A word ( Sale )', 'wp-easycart' ),
						'percent' => __( 'The discount ( -20% )', 'wp-easycart' ),
					),
					'condition' => array(
						'show_sale' => 'yes',
					),
				)
			);
			$this->add_control(
				'sale_text',
				array(
					'label'       => __( 'Sale text', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => '',
					'placeholder' => __( 'Sale', 'wp-easycart' ),
					'condition'   => array(
						'show_sale'  => 'yes',
						'sale_style' => 'text',
					),
					'ai'          => array( 'active' => false ),
				)
			);
			$this->add_control(
				'sale_percent_format',
				array(
					'label'       => __( 'Discount text', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => '',
					'placeholder' => '-[percent]%',
					'description' => __( '[percent] is the discount, e.g. "Save [percent]%".', 'wp-easycart' ),
					'condition'   => array(
						'show_sale'  => 'yes',
						'sale_style' => 'percent',
					),
					'ai'          => array( 'active' => false ),
				)
			);
			$this->icon_control( 'sale', array( 'show_sale' => 'yes' ) );
			$this->add_control(
				'show_new',
				array(
					'label'     => __( 'New', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SWITCHER,
					'default'   => 'yes',
					'separator' => 'before',
				)
			);
			$this->add_control(
				'new_days',
				array(
					'label'       => __( 'New for', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::NUMBER,
					'default'     => 30,
					'min'         => 1,
					'description' => __( 'Days after the product was added.', 'wp-easycart' ),
					'condition'   => array(
						'show_new' => 'yes',
					),
				)
			);
			$this->add_control(
				'new_text',
				array(
					'label'       => __( 'New text', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => '',
					'placeholder' => __( 'New', 'wp-easycart' ),
					'condition'   => array(
						'show_new' => 'yes',
					),
					'ai'          => array( 'active' => false ),
				)
			);
			$this->icon_control( 'new', array( 'show_new' => 'yes' ) );
			$this->add_control(
				'show_low',
				array(
					'label'     => __( 'Low stock', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SWITCHER,
					'default'   => 'yes',
					'separator' => 'before',
				)
			);
			$this->add_control(
				'low_stock',
				array(
					'label'     => __( 'Low stock at', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::NUMBER,
					'default'   => 5,
					'min'       => 1,
					'condition' => array(
						'show_low' => 'yes',
					),
				)
			);
			$this->add_control(
				'low_text',
				array(
					'label'       => __( 'Low stock text', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => '',
					'placeholder' => __( 'Low stock', 'wp-easycart' ),
					'condition'   => array(
						'show_low' => 'yes',
					),
					'ai'          => array( 'active' => false ),
				)
			);
			$this->icon_control( 'low', array( 'show_low' => 'yes' ) );
			$this->add_control(
				'show_out',
				array(
					'label'     => __( 'Sold out', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SWITCHER,
					'default'   => 'yes',
					'separator' => 'before',
				)
			);
			$this->add_control(
				'out_text',
				array(
					'label'       => __( 'Sold out text', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => '',
					'placeholder' => __( 'Sold out', 'wp-easycart' ),
					'condition'   => array(
						'show_out' => 'yes',
					),
					'ai'          => array( 'active' => false ),
				)
			);
			$this->icon_control( 'out', array( 'show_out' => 'yes' ) );
			$this->add_control(
				'show_tag',
				array(
					'label'       => __( 'The product’s own tag', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SWITCHER,
					'default'     => 'yes',
					'separator'   => 'before',
					'description' => __( 'The tag set in the product editor, in its own colors.', 'wp-easycart' ),
				)
			);
			$this->icon_control( 'tag', array( 'show_tag' => 'yes' ) );
			$this->add_control(
				'show_offer',
				array(
					'label'       => __( 'Offer badge', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SWITCHER,
					'default'     => 'yes',
					'separator'   => 'before',
					'description' => __( 'The badge of a running offer, when it has one.', 'wp-easycart' ),
				)
			);
			$this->end_controls_section();

			$this->start_controls_section(
				'section_order',
				array(
					'label' => __( 'Order', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->add_control(
				'order_note',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => esc_html__( 'Lower numbers come first. Leave empty to keep the order below.', 'wp-easycart' ),
					'content_classes' => 'elementor-descriptor',
				)
			);
			foreach ( $this->badge_labels() as $key => $label ) {
				$this->add_control(
					$key . '_order',
					array(
						'label'     => $label,
						'type'      => \Elementor\Controls_Manager::NUMBER,
						'min'       => -10,
						'max'       => 20,
						'step'      => 1,
						'selectors' => array(
							'{{WRAPPER}} .' . ( ( 'offer' === $key ) ? 'wpec-badge-offer' : 'wpec-badge--' . $key ) => 'order: {{VALUE}};',
						),
					)
				);
			}
			$this->end_controls_section();

			$this->register_style_controls();
		}

		/**
		 * Style tab.
		 */
		private function register_style_controls() {
			$badge = '{{WRAPPER}} .wpec-badge, {{WRAPPER}} .wpec-badges .ec_offer_product_badge';
			$this->start_controls_section(
				'style_badges',
				array(
					'label' => __( 'Badges', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->pb_align();
			$this->add_control(
				'direction',
				array(
					'label'        => __( 'Stack', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SELECT,
					'default'      => 'row',
					'options'      => array(
						'row'    => __( 'Side by side', 'wp-easycart' ),
						'column' => __( 'One under the other', 'wp-easycart' ),
					),
					'prefix_class' => 'wpec-badges-',
				)
			);
			$this->pb_size( 'badges_gap', __( 'Space between badges', 'wp-easycart' ), '--wpec-badges-gap' );
			$this->add_control(
				'badge_shape',
				array(
					'label'        => __( 'Shape', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SELECT,
					'default'      => '',
					'options'      => array(
						''       => __( 'Rounded corners', 'wp-easycart' ),
						'pill'   => __( 'Pill', 'wp-easycart' ),
						'ribbon' => __( 'Ribbon', 'wp-easycart' ),
						'circle' => __( 'Circle', 'wp-easycart' ),
					),
					'prefix_class' => 'wpec-badges-shape-',
				)
			);
			$this->pb_size(
				'badge_circle_size',
				__( 'Circle size', 'wp-easycart' ),
				'--wpec-badge-circle',
				array(
					'condition' => array(
						'badge_shape' => 'circle',
					),
				)
			);
			$this->pb_typography( 'badge_typography', __( 'Typography', 'wp-easycart' ), $badge, 'accent' );
			$this->add_responsive_control(
				'badge_radius',
				array(
					'label'      => __( 'Corner radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px', 'em' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 40,
						),
					),
					'selectors'  => array(
						'{{WRAPPER}}' => '--wpec-badge-radius: {{SIZE}}{{UNIT}};',
						'{{WRAPPER}} .wpec-badges .ec_offer_product_badge' => 'border-radius: {{SIZE}}{{UNIT}};',
					),
					'condition'  => array(
						'badge_shape' => '',
					),
				)
			);
			$this->add_responsive_control(
				'badge_padding',
				array(
					'label'      => __( 'Padding', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em' ),
					'selectors'  => array(
						'{{WRAPPER}} .wpec-badge' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
						'{{WRAPPER}} .wpec-badges .ec_offer_product_badge' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
				)
			);
			$this->pb_border( 'badge_border', $badge, array( 'separator' => 'before' ) );
			$this->pb_shadow( 'badge_shadow', $badge );
			$this->pb_heading( 'badge_icon_heading', __( 'Icons', 'wp-easycart' ) );
			$this->pb_icon_style( 'badge_icon', '{{WRAPPER}} .wpec-badge__icon' );
			$this->pb_size( 'badge_icon_gap', __( 'Space after the icon', 'wp-easycart' ), '--wpec-badge-icon-gap' );
			foreach ( $this->badge_labels() as $key => $label ) {
				if ( 'offer' === $key ) {
					$this->pb_heading( 'offer_heading', $label, array( 'condition' => array( 'show_offer' => 'yes' ) ) );
					$this->pb_color( 'offer_background_color', __( 'Background', 'wp-easycart' ), '--wpec-badge-offer-bg', array( 'condition' => array( 'show_offer' => 'yes' ) ) );
					$this->pb_color( 'offer_text_color', __( 'Text color', 'wp-easycart' ), '--wpec-badge-offer-color', array( 'condition' => array( 'show_offer' => 'yes' ) ) );
					continue;
				}
				if ( 'tag' === $key ) {
					$this->pb_heading( 'tag_heading', $label, array( 'condition' => array( 'show_tag' => 'yes' ) ) );
					$this->add_control(
						'tag_color_note',
						array(
							'type'            => \Elementor\Controls_Manager::RAW_HTML,
							'raw'             => esc_html__( 'Each tag keeps the colors set in the product editor unless you set them here.', 'wp-easycart' ),
							'content_classes' => 'elementor-descriptor',
							'condition'       => array( 'show_tag' => 'yes' ),
						)
					);
					$this->pb_color( 'tag_background_color', __( 'Background', 'wp-easycart' ), '--wpec-badge-tag-bg-override', array( 'condition' => array( 'show_tag' => 'yes' ) ) );
					$this->pb_color( 'tag_text_color', __( 'Text color', 'wp-easycart' ), '--wpec-badge-tag-color-override', array( 'condition' => array( 'show_tag' => 'yes' ) ) );
					continue;
				}
				$this->add_control(
					$key . '_heading',
					array(
						'label'     => $label,
						'type'      => \Elementor\Controls_Manager::HEADING,
						'separator' => 'before',
					)
				);
				$this->pb_color( $key . '_background_color', __( 'Background', 'wp-easycart' ), '--wpec-badge-' . $key . '-bg' );
				$this->pb_color( $key . '_text_color', __( 'Text color', 'wp-easycart' ), '--wpec-badge-' . $key . '-color' );
			}
			$this->end_controls_section();
		}

		/**
		 * One badge.
		 *
		 * @param string $key    sale | new | low | out | tag.
		 * @param string $text   Text.
		 * @param bool   $hidden Printed hidden ( a stock badge the chosen option may show ).
		 * @param string $icon   Icon markup ( pb_icon_html() ).
		 * @param string $style  Inline style ( escaped ).
		 * @return string
		 */
		private function badge( $key, $text, $hidden = false, $icon = '', $style = '' ) {
			$inner = esc_html( wp_strip_all_tags( $text ) );
			if ( '' !== $icon ) {
				$inner = '<span class="wpec-badge__icon" aria-hidden="true">' . $icon . '</span><span class="wpec-badge__text">' . $inner . '</span>';
			}
			return '<span class="wpec-badge wpec-badge--' . esc_attr( $key ) . ( ( '' !== $icon ) ? ' wpec-badge--has-icon' : '' ) . '"' . ( ( '' !== $style ) ? ' style="' . esc_attr( $style ) . '"' : '' ) . ( $hidden ? ' hidden' : '' ) . '>' . $inner . '</span>';
		}

		/**
		 * A setting's text, else the language text.
		 *
		 * @param array  $settings Settings.
		 * @param string $id       Control id.
		 * @param string $key      Language key.
		 * @param string $fallback English.
		 * @return string
		 */
		private function text_setting( $settings, $id, $key, $fallback ) {
			if ( isset( $settings[ $id ] ) && '' !== trim( (string) $settings[ $id ] ) ) {
				return (string) $settings[ $id ];
			}
			return WP_EasyCart_Product_Buy::text( $key, $fallback );
		}

		/**
		 * Render.
		 */
		protected function render() {
			$settings = $this->get_settings_for_display();
			$product  = $this->ec_product( $settings );
			if ( ! $product ) {
				$this->ec_no_product_notice( __( 'Product Badges', 'wp-easycart' ) );
				return;
			}
			/* Stock and sale badges change, so a page cache must not keep them ( the other product widgets do the same ). */
			WP_EasyCart_Product_Buy::prepare( $product, $this->ec_is_editor() );
			$on    = function ( $id ) use ( $settings ) {
				return isset( $settings[ $id ] ) && 'yes' === $settings[ $id ];
			};
			$icon  = function ( $key ) use ( $settings ) {
				return $this->pb_icon_html( isset( $settings[ $key . '_icon' ] ) ? $settings[ $key . '_icon' ] : null );
			};
			$out   = '';
			$stock = WP_EasyCart_Product_Buy::stock( $product );
			$price = WP_EasyCart_Product_Buy::price_state( $product );
			if ( $on( 'show_offer' ) && ! $price['hidden'] && WP_EasyCart_Product_Buy::offers_on() && function_exists( 'wp_easycart_offers_template' ) ) {
				ob_start();
				wp_easycart_offers_template(
					'ec_offer_product_badge.php',
					array(
						'badge_product_id'      => $product->product_id,
						'badge_manufacturer_id' => $product->manufacturer_id,
						'badge_price'           => $price['base'],
						'badge_list_price'      => $price['list'],
					)
				);
				$offer = trim( (string) ob_get_clean() );
				if ( '' !== $offer ) {
					$out .= '<span class="wpec-badge-offer">' . $offer . '</span>';
				}
			}
			if ( $on( 'show_sale' ) && $price['percent'] > 0 ) {
				if ( isset( $settings['sale_style'] ) && 'percent' === $settings['sale_style'] ) {
					$format = $this->text_setting( $settings, 'sale_percent_format', 'badge_sale_percent', '-[percent]%' );
					$text   = str_replace( '[percent]', (string) $price['percent'], $format );
				} else {
					$text = $this->text_setting( $settings, 'sale_text', 'badge_sale', __( 'Sale', 'wp-easycart' ) );
				}
				$out .= $this->badge( 'sale', $text, false, $icon( 'sale' ) );
			}
			if ( $on( 'show_new' ) && WP_EasyCart_Product_Buy::is_new( $product, isset( $settings['new_days'] ) ? (int) $settings['new_days'] : 30 ) ) {
				$out .= $this->badge( 'new', $this->text_setting( $settings, 'new_text', 'badge_new', __( 'New', 'wp-easycart' ) ), false, $icon( 'new' ) );
			}
			$stock_badges  = '';
			$stock_visible = false;
			$low           = isset( $settings['low_stock'] ) ? max( 1, (int) $settings['low_stock'] ) : 5;
			if ( $stock['tracked'] ) {
				$is_low = ( 'in' === $stock['status'] && $stock['quantity'] <= $low );
				$is_out = ( 'out' === $stock['status'] );
				/* Per-option stock: both badges are printed, hidden until the chosen option calls for them. */
				if ( $on( 'show_low' ) && ( $is_low || $stock['variants'] ) ) {
					$stock_badges .= $this->badge( 'low', $this->text_setting( $settings, 'low_text', 'badge_low_stock', __( 'Low stock', 'wp-easycart' ) ), ! $is_low, $icon( 'low' ) );
					$stock_visible = ( $stock_visible || $is_low );
				}
				if ( $on( 'show_out' ) && ( $is_out || $stock['variants'] ) ) {
					$stock_badges .= $this->badge( 'out', $this->text_setting( $settings, 'out_text', 'badge_out_of_stock', __( 'Sold out', 'wp-easycart' ) ), ! $is_out, $icon( 'out' ) );
					$stock_visible = ( $stock_visible || $is_out );
				}
			}
			if ( $on( 'show_tag' ) && (int) $product->tag_type > 0 && '' !== trim( (string) $product->tag_text ) ) {
				$bg    = sanitize_hex_color( (string) $product->tag_bg_color );
				$color = sanitize_hex_color( (string) $product->tag_text_color );
				$style = ( $bg ? '--wpec-badge-tag-bg:' . $bg . ';' : '' ) . ( $color ? '--wpec-badge-tag-color:' . $color . ';' : '' );
				$tag   = function_exists( 'wp_easycart_language' ) ? wp_easycart_language()->convert_text( $product->tag_text ) : $product->tag_text;
				$out  .= $this->badge( 'tag', $tag, false, $icon( 'tag' ), $style );
			}
			if ( '' === $out && '' === $stock_badges ) {
				$this->ec_editor_notice( __( 'Product Badges', 'wp-easycart' ), __( 'No badge applies to this product right now, so visitors see nothing here.', 'wp-easycart' ) );
				return;
			}
			$hidden_only = ( '' === $out && ! $stock_visible );
			if ( $hidden_only ) {
				$this->ec_editor_notice( __( 'Product Badges', 'wp-easycart' ), __( 'The stock badges appear when the shopper picks an option that is low or sold out.', 'wp-easycart' ) );
			}
			echo '<div class="wpec-el wpec-badges" data-product-id="' . esc_attr( $product->product_id ) . '" data-wpec-low="' . esc_attr( $low ) . '" data-wpec-backorders="' . ( $product->allow_backorders ? '1' : '0' ) . '"' . ( $hidden_only ? ' hidden' : '' ) . '>';
			echo $out . $stock_badges; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts above ( the Offers badge is PRO's own template ).
			if ( $stock['tracked'] && '' !== $stock_badges ) {
				$rand = WP_EasyCart_Product_Buy::next_rand();
				/* The Phase 0 stock link: ec-store.js writes the chosen variant's stock here, product-buy.js shows the right badge. */
				echo '<span class="ec_details_stock_total_ele wpec-stock__link" data-wpec-linked-product="' . esc_attr( $product->product_id ) . '" data-wpec-linked-rand="' . esc_attr( $rand ) . '" hidden><span id="ec_details_stock_quantity_' . esc_attr( $product->product_id ) . '_' . esc_attr( $rand ) . '">' . esc_html( $stock['quantity'] ) . '</span></span>';
			}
			echo '</div>';
		}
	}

endif;
