<?php
/**
 * Elementor widget "Product Stock" ( wp_easycart_product_stock, 6.0.2 ).
 *
 * In stock, "Only 3 left", out of stock or available on backorder, as text or a bar. Counts follow Settings › Products
 * ( remaining stock shown to shoppers ), and the line follows the option chosen in an Add to Cart widget on the same page.
 * Replaces wp_easycart_product_details_stock.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/trait-wp-easycart-product-buy-controls.php';

if ( ! class_exists( 'WP_EasyCart_Elementor_Product_Stock_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Widget_Base' ) ) :

	/**
	 * Product Stock.
	 */
	class WP_EasyCart_Elementor_Product_Stock_Widget extends WP_EasyCart_Elementor_Widget_Base {

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
		protected static $ec_help = 'product-stock';

		/**
		 * Widget name.
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_product_stock';
		}

		/**
		 * Widget title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Product Stock', 'wp-easycart' );
		}

		/**
		 * Widget icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-product-stock';
		}

		/**
		 * Search keywords.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array( 'stock', 'inventory', 'in stock', 'out of stock', 'availability', 'backorder', 'product' );
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
				'section_stock',
				array(
					'label' => __( 'Stock', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->add_control(
				'stock_style',
				array(
					'label'   => __( 'Show as', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => 'text',
					'options' => array(
						'text' => __( 'Text', 'wp-easycart' ),
						'bar'  => __( 'Text and a bar', 'wp-easycart' ),
					),
				)
			);
			$this->add_control(
				'low_stock',
				array(
					'label'       => __( 'Low stock at', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::NUMBER,
					'default'     => 5,
					'min'         => 0,
					'step'        => 1,
					'description' => __( 'With this many left or fewer the widget says "Only 3 left". 0 turns it off.', 'wp-easycart' ),
				)
			);
			$this->add_control(
				'bar_max',
				array(
					'label'       => __( 'Full bar at', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::NUMBER,
					'default'     => 20,
					'min'         => 1,
					'step'        => 1,
					'description' => __( 'The number of units that fills the bar.', 'wp-easycart' ),
					'condition'   => array(
						'stock_style' => 'bar',
					),
				)
			);
			$this->add_control(
				'show_count',
				array(
					'label'   => __( 'Number left', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SWITCHER,
					'default' => 'yes',
				)
			);
			$this->add_control(
				'show_untracked',
				array(
					'label'       => __( '"In stock" for products without stock tracking', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SWITCHER,
					'default'     => '',
					'description' => __( 'Off: the widget shows nothing for them.', 'wp-easycart' ),
				)
			);
			$this->add_control(
				'hide_in_stock',
				array(
					'label'       => __( 'Only when stock is low or out', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SWITCHER,
					'default'     => '',
					'description' => __( 'Nothing shows while the product ( or the chosen option ) is simply in stock.', 'wp-easycart' ),
				)
			);
			$this->add_control(
				'settings_note',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => sprintf(
						/* translators: %s: link to the stock setting. */
						esc_html__( 'Numbers show only while the store shows remaining stock: %s.', 'wp-easycart' ),
						'<a href="' . esc_url( admin_url( 'admin.php?page=wp-easycart-settings&subpage=products&highlight=ec_option_show_stock_quantity' ) ) . '" target="_blank">' . esc_html__( 'Settings › Products', 'wp-easycart' ) . '</a>'
					),
					'content_classes' => 'elementor-descriptor',
				)
			);
			$this->end_controls_section();

			$this->start_controls_section(
				'section_stock_texts',
				array(
					'label' => __( 'Wording and icons', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->add_control(
				'texts_note',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => esc_html__( 'Leave a text empty to use the store’s wording. [count] is the number left.', 'wp-easycart' ),
					'content_classes' => 'elementor-descriptor',
				)
			);
			foreach ( $this->states() as $key => $state ) {
				$this->add_control(
					'text_' . $key,
					array(
						'label'       => $state['label'],
						'type'        => \Elementor\Controls_Manager::TEXT,
						'default'     => '',
						'placeholder' => $state['placeholder'],
						'label_block' => true,
						'ai'          => array( 'active' => false ),
					)
				);
			}
			$this->add_control(
				'icons_note',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => esc_html__( 'An icon per state shows in place of the colored dot.', 'wp-easycart' ),
					'content_classes' => 'elementor-descriptor',
					'separator'       => 'before',
				)
			);
			foreach ( $this->states() as $key => $state ) {
				if ( 'count' === $key ) {
					continue;
				}
				$this->add_control(
					'icon_' . $key,
					array(
						'label' => $state['icon'],
						'type'  => \Elementor\Controls_Manager::ICONS,
						'skin'  => 'inline',
					)
				);
			}
			$this->end_controls_section();

			$this->start_controls_section(
				'style_stock',
				array(
					'label' => __( 'Stock', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->pb_align();
			$this->pb_color( 'text_color', __( 'Text color', 'wp-easycart' ), '--wpec-stock-color', array( 'description' => __( 'Every state. The colors below win for their own state.', 'wp-easycart' ) ) );
			$this->pb_typography( 'text_typography', __( 'Typography', 'wp-easycart' ), '{{WRAPPER}} .wpec-stock', 'text' );
			$this->add_control(
				'show_icon',
				array(
					'label'        => __( 'Colored dot', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'default'      => 'yes',
					'prefix_class' => 'wpec-stock-dot-',
				)
			);
			$this->pb_size( 'icon_size', __( 'Dot and icon size', 'wp-easycart' ), '--wpec-stock-icon-size' );
			$this->pb_size( 'icon_gap', __( 'Space after the dot or icon', 'wp-easycart' ), '--wpec-stock-gap' );
			$this->pb_heading( 'state_colors_heading', __( 'State colors ( dot, icon and bar )', 'wp-easycart' ) );
			$this->pb_color( 'in_color', __( 'In stock', 'wp-easycart' ), '--wpec-stock-in' );
			$this->pb_color( 'low_color', __( 'Low stock', 'wp-easycart' ), '--wpec-stock-low' );
			$this->pb_color( 'backorder_color', __( 'Backorder', 'wp-easycart' ), '--wpec-stock-backorder', array( 'description' => __( 'Follows Low stock until you set it.', 'wp-easycart' ) ) );
			$this->pb_color( 'out_color', __( 'Out of stock', 'wp-easycart' ), '--wpec-stock-out' );
			$this->pb_heading( 'state_text_heading', __( 'Text colors', 'wp-easycart' ) );
			$this->pb_color( 'in_text_color', __( 'In stock', 'wp-easycart' ), '--wpec-stock-in-text' );
			$this->pb_color( 'low_text_color', __( 'Low stock', 'wp-easycart' ), '--wpec-stock-low-text' );
			$this->pb_color( 'backorder_text_color', __( 'Backorder', 'wp-easycart' ), '--wpec-stock-backorder-text' );
			$this->pb_color( 'out_text_color', __( 'Out of stock', 'wp-easycart' ), '--wpec-stock-out-text' );
			$this->pb_heading( 'bar_heading', __( 'Bar', 'wp-easycart' ), array( 'condition' => array( 'stock_style' => 'bar' ) ) );
			$this->pb_color( 'bar_background_color', __( 'Bar background', 'wp-easycart' ), '--wpec-stock-bar-bg', array( 'condition' => array( 'stock_style' => 'bar' ) ) );
			$this->pb_size( 'bar_height', __( 'Bar height', 'wp-easycart' ), '--wpec-stock-bar-height', array( 'condition' => array( 'stock_style' => 'bar' ) ) );
			$this->pb_size( 'bar_radius', __( 'Bar corner radius', 'wp-easycart' ), '--wpec-stock-bar-radius', array( 'condition' => array( 'stock_style' => 'bar' ) ) );
			$this->pb_size(
				'bar_width',
				__( 'Bar width', 'wp-easycart' ),
				'--wpec-stock-bar-width',
				array(
					'size_units' => array( '%', 'px' ),
					'range'      => array(
						'%'  => array(
							'min' => 10,
							'max' => 100,
						),
						'px' => array(
							'min' => 40,
							'max' => 800,
						),
					),
					'condition'  => array( 'stock_style' => 'bar' ),
				)
			);
			$this->end_controls_section();

			$this->pb_box_section( '{{WRAPPER}} .wpec-stock' );
		}

		/**
		 * The stock states with their own text ( and icon ).
		 *
		 * @return array key => { label, placeholder, icon }.
		 */
		private function states() {
			return array(
				'in'        => array(
					'label'       => __( 'In stock', 'wp-easycart' ),
					'placeholder' => __( 'In stock', 'wp-easycart' ),
					'icon'        => __( 'In stock icon', 'wp-easycart' ),
				),
				'count'     => array(
					'label'       => __( 'In stock, with the number left', 'wp-easycart' ),
					'placeholder' => __( '[count] in stock', 'wp-easycart' ),
					'icon'        => '',
				),
				'low'       => array(
					'label'       => __( 'Low stock', 'wp-easycart' ),
					'placeholder' => __( 'Only [count] left', 'wp-easycart' ),
					'icon'        => __( 'Low stock icon', 'wp-easycart' ),
				),
				'out'       => array(
					'label'       => __( 'Out of stock', 'wp-easycart' ),
					'placeholder' => __( 'Out of stock', 'wp-easycart' ),
					'icon'        => __( 'Out of stock icon', 'wp-easycart' ),
				),
				'backorder' => array(
					'label'       => __( 'Backorder', 'wp-easycart' ),
					'placeholder' => __( 'Available on backorder', 'wp-easycart' ),
					'icon'        => __( 'Backorder icon', 'wp-easycart' ),
				),
			);
		}

		/**
		 * Render.
		 */
		protected function render() {
			$settings = $this->get_settings_for_display();
			$product  = $this->ec_product( $settings );
			if ( ! $product ) {
				$this->ec_no_product_notice( __( 'Product Stock', 'wp-easycart' ) );
				return;
			}
			WP_EasyCart_Product_Buy::prepare( $product, $this->ec_is_editor() );
			$texts = array();
			$icons = array();
			foreach ( array_keys( $this->states() ) as $key ) {
				$texts[ $key ] = isset( $settings[ 'text_' . $key ] ) ? sanitize_text_field( (string) $settings[ 'text_' . $key ] ) : '';
				if ( 'count' !== $key ) {
					$icons[ $key ] = $this->pb_icon_html( isset( $settings[ 'icon_' . $key ] ) ? $settings[ 'icon_' . $key ] : null );
				}
			}
			$hide_in = ( isset( $settings['hide_in_stock'] ) && 'yes' === $settings['hide_in_stock'] );
			$html    = WP_EasyCart_Product_Buy::stock_html(
				$product,
				array(
					'rand'       => WP_EasyCart_Product_Buy::next_rand(),
					'style'      => ( isset( $settings['stock_style'] ) && 'bar' === $settings['stock_style'] ) ? 'bar' : 'text',
					'low'        => isset( $settings['low_stock'] ) ? (int) $settings['low_stock'] : 5,
					'show_count' => ( isset( $settings['show_count'] ) && 'yes' === $settings['show_count'] ),
					'untracked'  => ( isset( $settings['show_untracked'] ) && 'yes' === $settings['show_untracked'] ),
					'bar_max'    => isset( $settings['bar_max'] ) ? (int) $settings['bar_max'] : 20,
					'texts'      => $texts,
					'icons'      => array_filter( $icons ),
					'hide_in'    => $hide_in,
				)
			);
			if ( $hide_in && '' !== $html && false !== strpos( $html, ' is-in' ) ) {
				$this->ec_editor_notice( __( 'Product Stock', 'wp-easycart' ), __( 'This product is in stock, so visitors see nothing here until stock runs low or out.', 'wp-easycart' ) );
			}
			if ( '' === $html ) {
				$this->ec_editor_notice( __( 'Product Stock', 'wp-easycart' ), __( 'This product does not track stock, so visitors see nothing here. Switch on "In stock" for products without stock tracking to show it anyway.', 'wp-easycart' ) );
				return;
			}
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in stock_html().
		}

		/**
		 * The equivalent shortcode for post_content.
		 *
		 * @return string
		 */
		protected function plain_content_shortcode() {
			return $this->pb_plain_shortcode( 'ec_product_details_stock', $this->get_settings_for_display() );
		}
	}

endif;
