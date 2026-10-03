<?php
/**
 * Products ( wp_easycart_products, 6.0.2 ): a grid of product cards from a chosen source, with page numbers or "Load more".
 *
 * Replaces the Products widget from before 6.0.2 ( wp_easycart_product, grid layout ). One product keeps the chosen columns
 * and the Quick view switch is honoured ( review A5 ).
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-wp-easycart-elementor-shop-listing-widget.php';

if ( ! class_exists( 'WP_EasyCart_Elementor_Shop_Products_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Shop_Listing_Widget' ) ) :

	/**
	 * Products grid.
	 */
	class WP_EasyCart_Elementor_Shop_Products_Widget extends WP_EasyCart_Elementor_Shop_Listing_Widget {

		/**
		 * Help page.
		 *
		 * @var string
		 */
		protected static $ec_help = 'products';

		/**
		 * Name.
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_products';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Products', 'wp-easycart' );
		}

		/**
		 * Icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-products';
		}

		/**
		 * Keywords.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array( 'products', 'product grid', 'product list', 'featured products', 'best sellers', 'on sale', 'new arrivals', 'related products', 'add to cart', 'quick view', 'woocommerce' );
		}

		/**
		 * Controls.
		 */
		protected function register_controls() {
			$this->ec_register_query_controls( array( 'limit' => 8 ) );

			$this->start_controls_section(
				'ec_section_layout',
				array(
					'label' => __( 'Layout', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->ec_register_columns_controls();
			$this->ec_register_row_gap_control();
			/* A random order has no next page ( every page would draw again ), so it shows one page. */
			$this->add_control(
				'ec_pagination',
				array(
					'label'     => __( 'More products', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'default'   => '',
					'options'   => array(
						''          => __( 'None', 'wp-easycart' ),
						'numbers'   => __( 'Page numbers', 'wp-easycart' ),
						'load_more' => __( 'Load more button', 'wp-easycart' ),
					),
					'separator' => 'before',
					'condition' => array( 'ec_orderby!' => 'random' ),
				)
			);
			$this->add_control(
				'ec_load_more_text',
				array(
					'label'       => __( 'Button text', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => '',
					'placeholder' => WP_EasyCart_Elementor_Shop::text( 'shop_load_more', 'Load more' ),
					'condition'   => array(
						'ec_pagination' => 'load_more',
						'ec_orderby!'   => 'random',
					),
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
			$this->ec_register_pagination_style_controls(
				array(
					'ec_pagination!' => '',
					'ec_orderby!'    => 'random',
				)
			);
		}

		/**
		 * The URL argument holding this widget's page number ( one per widget, so two lists on a page page separately ).
		 *
		 * @return string
		 */
		protected function ec_page_arg() {
			return 'wpec-page-' . $this->get_id();
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
			$settings   = $this->get_settings_for_display();
			$is_editor  = $this->ec_is_editor();
			$resolved   = WP_EasyCart_Elementor_Shop_Query::resolve( $settings, $is_editor );
			$pagination = isset( $settings['ec_pagination'] ) ? (string) $settings['ec_pagination'] : '';
			$page_arg   = $this->ec_page_arg();
			$query      = $resolved['query'];
			if ( $query && 'random' === $query['orderby'] ) {
				$pagination = ''; /* A random order has no next page: another page would repeat and skip products. */
			}
			if ( $query && '' !== $pagination && isset( $_GET[ $page_arg ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page number.
				$query['page'] = WP_EasyCart_Elementor_Shop_Query::clamp( absint( $_GET[ $page_arg ] ), 1, 1000, 1 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page number.
			}
			$result = $query ? WP_EasyCart_Elementor_Shop_Query::run( $query ) : array(
				'products' => array(),
				'total'    => 0,
				'pages'    => 0,
			);

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

			$options = WP_EasyCart_Elementor_Shop_Card::options_from_settings( $settings );
			$this->ec_editor_hint( $resolved['note'] );

			$attrs = '';
			if ( 'load_more' === $pagination && $result['pages'] > $query['page'] ) {
				$client_query = WP_EasyCart_Elementor_Shop_Query::for_client( $query );
				$attrs        = ' data-wpec-query="' . esc_attr( wp_json_encode( $client_query ) ) . '" data-wpec-card="' . esc_attr( wp_json_encode( $options ) ) . '" data-wpec-pages="' . esc_attr( $result['pages'] ) . '"';
			}
			echo '<div class="wpec-el wpec-products wpec-products--grid"' . $attrs . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attributes escaped above.
			$this->ec_render_heading( $settings );
			echo '<ul class="wpec-products__grid" role="list">';
			WP_EasyCart_Elementor_Shop_Card::render_list( $result['products'], $options );
			echo '</ul>';

			if ( 'numbers' === $pagination ) {
				self::ec_render_pagination(
					$query['page'],
					$result['pages'],
					function ( $page ) use ( $page_arg ) {
						return ( 1 === (int) $page ) ? WP_EasyCart_Elementor_Shop::current_url( array( $page_arg ) ) : add_query_arg( $page_arg, (int) $page, WP_EasyCart_Elementor_Shop::current_url( array( $page_arg ) ) );
					}
				);
			} elseif ( 'load_more' === $pagination && $result['pages'] > $query['page'] ) {
				$text = isset( $settings['ec_load_more_text'] ) ? trim( (string) $settings['ec_load_more_text'] ) : '';
				$text = ( '' !== $text ) ? $text : WP_EasyCart_Elementor_Shop::text( 'shop_load_more', 'Load more' );
				$next = add_query_arg( $page_arg, (int) $query['page'] + 1, WP_EasyCart_Elementor_Shop::current_url( array( $page_arg ) ) );
				echo '<div class="wpec-products__more"><a class="wpec-button wpec-button--secondary wpec-load-more" href="' . esc_url( $next ) . '" data-wpec-page="' . esc_attr( (int) $query['page'] + 1 ) . '" rel="nofollow">' . esc_html( $text ) . '</a></div>';
			}
			$this->ec_render_live_region();
			echo '</div>';
		}
	}

endif;
