<?php
/**
 * Product Carousel ( wp_easycart_product_carousel, 6.0.2 ): product cards in a sliding row.
 *
 * The row is a CSS scroll-snap strip from the first paint ( no jump while scripts load, and it scrolls by touch or keyboard
 * without them ). shop.js then turns it into Elementor's Swiper when the page has it ( declared as a dependency here ):
 * arrows, dots, autoplay that pauses on hover and focus and has a Pause button, loop, reduced motion respected. Without
 * Swiper the strip keeps simple arrows.
 *
 * Replaces the slider layout of the Products widget from before 6.0.2 ( wp_easycart_product ).
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-wp-easycart-elementor-shop-listing-widget.php';

if ( ! class_exists( 'WP_EasyCart_Elementor_Shop_Carousel_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Shop_Listing_Widget' ) ) :

	/**
	 * Products carousel.
	 */
	class WP_EasyCart_Elementor_Shop_Carousel_Widget extends WP_EasyCart_Elementor_Shop_Listing_Widget {

		/**
		 * Help page.
		 *
		 * @var string
		 */
		protected static $ec_help = 'product-carousel';

		/**
		 * Name.
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_product_carousel';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Product Carousel', 'wp-easycart' );
		}

		/**
		 * Icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-slider-push';
		}

		/**
		 * Keywords.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array( 'carousel', 'slider', 'products', 'product slider', 'featured products', 'related products', 'best sellers', 'woocommerce' );
		}

		/**
		 * Scripts: Elementor's Swiper and the shop script.
		 *
		 * @return array
		 */
		public function get_script_depends() {
			return array_values( array_unique( array_merge( parent::get_script_depends(), array( 'swiper', 'wpec-el-shop' ) ) ) );
		}

		/**
		 * Controls.
		 */
		protected function register_controls() {
			$this->ec_register_query_controls( array( 'limit' => 8 ) );

			$this->start_controls_section(
				'ec_section_carousel',
				array(
					'label' => __( 'Carousel', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			/* Redrawn when they change: the script ( Swiper ) sizes the slides once, so the CSS variables alone never showed it. */
			$this->ec_register_columns_controls( __( 'Products in view', 'wp-easycart' ), array( 4, 3, 2 ), true );
			$this->add_responsive_control(
				'ec_arrows',
				array(
					'label'                => __( 'Arrows', 'wp-easycart' ),
					'type'                 => \Elementor\Controls_Manager::SELECT,
					'default'              => 'show',
					'options'              => array(
						'show' => __( 'Show', 'wp-easycart' ),
						'hide' => __( 'Hide', 'wp-easycart' ),
					),
					'selectors_dictionary' => array(
						'show' => 'flex',
						'hide' => 'none',
					),
					'selectors'            => array( '{{WRAPPER}} .wpec-carousel__arrow' => 'display: {{VALUE}};' ),
					'separator'            => 'before',
				)
			);
			$this->add_responsive_control(
				'ec_dots',
				array(
					'label'                => __( 'Dots', 'wp-easycart' ),
					'type'                 => \Elementor\Controls_Manager::SELECT,
					'default'              => 'show',
					'options'              => array(
						'show' => __( 'Show', 'wp-easycart' ),
						'hide' => __( 'Hide', 'wp-easycart' ),
					),
					'selectors_dictionary' => array(
						'show' => 'flex',
						'hide' => 'none',
					),
					'selectors'            => array( '{{WRAPPER}} .wpec-carousel__dots' => 'display: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'ec_autoplay',
				array(
					'label'       => __( 'Move by itself', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SWITCHER,
					'default'     => '',
					'description' => __( 'Stops while a shopper points at it or uses the keyboard in it, has a Pause button, and stays still for shoppers who ask their device for less motion.', 'wp-easycart' ),
				)
			);
			$this->add_control(
				'ec_autoplay_speed',
				array(
					'label'     => __( 'Seconds on each move', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::NUMBER,
					'default'   => 5,
					'min'       => 2,
					'max'       => 30,
					'step'      => 1,
					'condition' => array( 'ec_autoplay' => 'yes' ),
				)
			);
			$this->add_control(
				'ec_loop',
				array(
					'label'   => __( 'Start again after the last product', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SWITCHER,
					'default' => '',
				)
			);
			$this->add_control(
				'ec_empty_message',
				array(
					'label'       => __( 'Message when there are no products', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => '',
					'label_block' => true,
					'description' => __( 'Leave empty and visitors see nothing at all when no product matches.', 'wp-easycart' ),
				)
			);
			$this->ec_register_heading_controls();
			$this->end_controls_section();

			$this->ec_register_card_controls();
			$this->ec_register_heading_style_controls();
			$this->ec_register_card_style_controls();
			$this->ec_register_navigation_style_controls();
		}

		/**
		 * Style section for arrows and dots.
		 */
		protected function ec_register_navigation_style_controls() {
			$this->start_controls_section(
				'ec_style_navigation',
				array(
					'label' => __( 'Arrows and dots', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->add_control(
				'arrow_size',
				array(
					'label'      => __( 'Arrow size', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 24,
							'max' => 72,
						),
					),
					'selectors'  => array( '{{WRAPPER}}' => '--wpec-arrow-size: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->start_controls_tabs( 'arrow_tabs' );
			$this->start_controls_tab( 'arrow_tab_normal', array( 'label' => __( 'Normal', 'wp-easycart' ) ) );
			$this->ec_color_var( 'arrow_color', __( 'Arrow colour', 'wp-easycart' ), '--wpec-arrow-color' );
			$this->ec_color_var( 'arrow_background', __( 'Arrow background', 'wp-easycart' ), '--wpec-arrow-bg' );
			$this->end_controls_tab();
			$this->start_controls_tab( 'arrow_tab_hover', array( 'label' => __( 'Hover', 'wp-easycart' ) ) );
			$this->ec_color_var( 'arrow_hover_color', __( 'Arrow colour', 'wp-easycart' ), '--wpec-arrow-hover-color' );
			$this->ec_color_var( 'arrow_hover_background', __( 'Arrow background', 'wp-easycart' ), '--wpec-arrow-hover-bg' );
			$this->end_controls_tab();
			$this->end_controls_tabs();
			$this->ec_color_var( 'dot_color', __( 'Dot colour', 'wp-easycart' ), '--wpec-dot-color' );
			$this->ec_color_var( 'dot_active_color', __( 'Current dot colour', 'wp-easycart' ), '--wpec-dot-active-color' );
			$this->end_controls_section();
		}

		/**
		 * Per-device values of a responsive setting, for the script ( device => value; missing devices inherit ).
		 *
		 * @param array  $settings Widget settings.
		 * @param string $id       Control id.
		 * @param bool   $size     The value is a slider ( { size } ).
		 * @return array
		 */
		protected function ec_devices( $settings, $id, $size = false ) {
			$out = array();
			foreach ( array(
				''              => 'desktop',
				'_widescreen'   => 'widescreen',
				'_laptop'       => 'laptop',
				'_tablet_extra' => 'tablet_extra',
				'_tablet'       => 'tablet',
				'_mobile_extra' => 'mobile_extra',
				'_mobile'       => 'mobile',
			) as $suffix => $device ) {
				if ( ! isset( $settings[ $id . $suffix ] ) ) {
					continue;
				}
				$value = $settings[ $id . $suffix ];
				if ( $size ) {
					$value = ( is_array( $value ) && isset( $value['size'] ) && '' !== $value['size'] ) ? $value['size'] : '';
				}
				if ( '' !== $value && null !== $value && is_numeric( $value ) ) {
					$out[ $device ] = (float) $value;
				}
			}
			return $out;
		}

		/**
		 * Draws the widget.
		 */
		protected function render() {
			if ( WP_EasyCart_Elementor_Shop::restricted() ) {
				return;
			}
			WP_EasyCart_Elementor_Shop_Query::role_no_cache(); /* Cacheable, unless prices or products differ per customer role. */
			WP_EasyCart_Elementor_Shop::pixel_base_code();
			$settings  = $this->get_settings_for_display();
			$is_editor = $this->ec_is_editor();
			$resolved  = WP_EasyCart_Elementor_Shop_Query::resolve( $settings, $is_editor );
			$result    = $resolved['query'] ? WP_EasyCart_Elementor_Shop_Query::run( $resolved['query'] ) : array( 'products' => array() );

			if ( empty( $result['products'] ) ) {
				$message = isset( $settings['ec_empty_message'] ) ? trim( (string) $settings['ec_empty_message'] ) : '';
				if ( $is_editor ) {
					$this->ec_editor_notice( __( 'No products to show with these settings.', 'wp-easycart' ), ( '' !== $resolved['note'] ) ? $resolved['note'] : __( 'Visitors see nothing here ( or your message ) until products match. Try another choice under Content › Products.', 'wp-easycart' ) );
				}
				if ( '' !== $message ) {
					echo '<div class="wpec-products wpec-products--empty"><p class="wpec-products__empty">' . esc_html( $message ) . '</p></div>';
				}
				return;
			}

			$options  = WP_EasyCart_Elementor_Shop_Card::options_from_settings( $settings );
			$autoplay = ( isset( $settings['ec_autoplay'] ) && 'yes' === $settings['ec_autoplay'] );
			$config   = array(
				'perView'  => $this->ec_devices( $settings, 'ec_columns' ),
				'gap'      => $this->ec_devices( $settings, 'ec_column_gap', true ),
				'autoplay' => $autoplay ? 1 : 0,
				'delay'    => WP_EasyCart_Elementor_Shop_Query::clamp( isset( $settings['ec_autoplay_speed'] ) ? $settings['ec_autoplay_speed'] : 5, 2, 30, 5 ) * 1000,
				'loop'     => ( isset( $settings['ec_loop'] ) && 'yes' === $settings['ec_loop'] ) ? 1 : 0,
			);
			$heading  = isset( $settings['ec_heading'] ) ? trim( (string) $settings['ec_heading'] ) : '';
			$label    = ( '' !== $heading ) ? $heading : WP_EasyCart_Elementor_Shop::text( 'shop_carousel_label', 'Products' );
			$chevron  = '<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';

			$this->ec_editor_hint( $resolved['note'] );
			echo '<div class="wpec-el wpec-products wpec-carousel" role="region" aria-roledescription="carousel" aria-label="' . esc_attr( $label ) . '" data-wpec-carousel="' . esc_attr( wp_json_encode( $config ) ) . '">';
			$this->ec_render_heading( $settings );
			echo '<div class="wpec-carousel__stage">';
			echo '<button type="button" class="wpec-carousel__arrow wpec-carousel__arrow--prev" aria-label="' . esc_attr( WP_EasyCart_Elementor_Shop::text( 'shop_carousel_prev', 'Previous products' ) ) . '">' . $chevron . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed inline icon.
			echo '<div class="wpec-carousel__viewport"><div class="wpec-carousel__track">';
			WP_EasyCart_Elementor_Shop_Card::render_list(
				$result['products'],
				$options,
				array(
					'tag'    => 'div',
					'class'  => 'wpec-carousel__slide',
					'slides' => true,
				)
			);
			echo '</div></div>';
			echo '<button type="button" class="wpec-carousel__arrow wpec-carousel__arrow--next" aria-label="' . esc_attr( WP_EasyCart_Elementor_Shop::text( 'shop_carousel_next', 'Next products' ) ) . '">' . $chevron . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed inline icon.
			echo '</div>';
			echo '<div class="wpec-carousel__footer"><div class="wpec-carousel__dots"></div>';
			if ( $autoplay ) {
				echo '<button type="button" class="wpec-carousel__pause" aria-pressed="false">' . esc_html( WP_EasyCart_Elementor_Shop::text( 'shop_carousel_pause', 'Pause' ) ) . '</button>';
			}
			echo '</div>';
			$this->ec_render_live_region();
			echo '</div>';
		}
	}

endif;
