<?php
/**
 * Elementor widget "Product SKU" ( wp_easycart_product_sku, 6.0.2 ).
 *
 * The product's SKU ( model number ) with an optional label; it shows the chosen variant's SKU when the shopper picks
 * options in an Add to Cart widget on the same page. Replaces wp_easycart_product_details_sku.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/trait-wp-easycart-product-buy-controls.php';

if ( ! class_exists( 'WP_EasyCart_Elementor_Product_Sku_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Widget_Base' ) ) :

	/**
	 * Product SKU.
	 */
	class WP_EasyCart_Elementor_Product_Sku_Widget extends WP_EasyCart_Elementor_Widget_Base {

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
		protected static $ec_help = 'product-sku';

		/**
		 * Widget name.
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_product_sku';
		}

		/**
		 * Widget title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Product SKU', 'wp-easycart' );
		}

		/**
		 * Widget icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-product-meta';
		}

		/**
		 * Search keywords.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array( 'sku', 'model number', 'part number', 'product meta', 'product' );
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
				'section_sku',
				array(
					'label' => __( 'SKU', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->add_control(
				'show_label',
				array(
					'label'   => __( 'Label', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SWITCHER,
					'default' => 'yes',
				)
			);
			$this->add_control(
				'label_text',
				array(
					'label'       => __( 'Label text', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => '',
					'placeholder' => __( 'SKU:', 'wp-easycart' ),
					'condition'   => array(
						'show_label' => 'yes',
					),
					'ai'          => array( 'active' => false ),
				)
			);
			$this->add_control(
				'separator_text',
				array(
					'label'       => __( 'Between label and SKU', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => '',
					'placeholder' => '·',
					'description' => __( 'Optional, e.g. a dash or a dot.', 'wp-easycart' ),
					'condition'   => array(
						'show_label' => 'yes',
					),
					'ai'          => array( 'active' => false ),
				)
			);
			$this->add_control(
				'sku_layout',
				array(
					'label'        => __( 'Layout', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SELECT,
					'default'      => 'inline',
					'options'      => array(
						'inline'  => __( 'On one line', 'wp-easycart' ),
						'stacked' => __( 'Label above the SKU', 'wp-easycart' ),
					),
					'prefix_class' => 'wpec-sku-layout-',
					'condition'    => array(
						'show_label' => 'yes',
					),
				)
			);
			$this->add_control(
				'sku_icon',
				array(
					'label' => __( 'Icon', 'wp-easycart' ),
					'type'  => \Elementor\Controls_Manager::ICONS,
					'skin'  => 'inline',
				)
			);
			$this->end_controls_section();

			$this->start_controls_section(
				'style_sku',
				array(
					'label' => __( 'SKU', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->pb_align();
			$this->pb_size( 'sku_gap', __( 'Space between parts', 'wp-easycart' ), '--wpec-sku-gap' );
			$this->pb_color( 'label_color', __( 'Label color', 'wp-easycart' ), '--wpec-sku-label', array( 'condition' => array( 'show_label' => 'yes' ) ) );
			$this->pb_typography( 'label_typography', __( 'Label typography', 'wp-easycart' ), '{{WRAPPER}} .wpec-sku__label', 'text' );
			$this->pb_color( 'value_color', __( 'SKU color', 'wp-easycart' ), '--wpec-sku-value', array( 'separator' => 'before' ) );
			$this->pb_typography( 'value_typography', __( 'SKU typography', 'wp-easycart' ), '{{WRAPPER}} .wpec-sku__value', 'text' );
			$this->pb_color( 'separator_color', __( 'Separator color', 'wp-easycart' ), '--wpec-sku-sep', array( 'separator' => 'before' ) );
			$this->pb_heading( 'sku_icon_heading', __( 'Icon', 'wp-easycart' ), array( 'condition' => array( 'sku_icon[value]!' => '' ) ) );
			$this->pb_icon_style( 'sku_icon_style', '{{WRAPPER}} .wpec-sku__icon', array( 'condition' => array( 'sku_icon[value]!' => '' ) ) );
			$this->end_controls_section();

			$this->pb_box_section( '{{WRAPPER}} .wpec-sku' );
		}

		/**
		 * Whether a variant of the product has a SKU of its own ( ec-store.js then writes it into the widget ).
		 *
		 * @param ec_product $product Product.
		 * @return bool
		 */
		private function variant_skus( $product ) {
			if ( ! isset( $product->options->variation_array ) || ! is_array( $product->options->variation_array ) ) {
				return false;
			}
			foreach ( $product->options->variation_array as $variant ) {
				if ( is_object( $variant ) && isset( $variant->sku ) && '' !== trim( (string) $variant->sku ) ) {
					return true;
				}
			}
			return false;
		}

		/**
		 * Render.
		 */
		protected function render() {
			$settings = $this->get_settings_for_display();
			$product  = $this->ec_product( $settings );
			if ( ! $product ) {
				$this->ec_no_product_notice( __( 'Product SKU', 'wp-easycart' ) );
				return;
			}
			$sku      = trim( (string) $product->model_number );
			$variants = ( '' === $sku ) ? $this->variant_skus( $product ) : false;
			if ( '' === $sku && ! $variants ) {
				$this->ec_editor_notice( __( 'Product SKU', 'wp-easycart' ), __( 'This product has no SKU, and none of its variants has one, so visitors see nothing here. Add one in the product editor.', 'wp-easycart' ) );
				return;
			}
			if ( '' === $sku ) {
				$this->ec_editor_notice( __( 'Product SKU', 'wp-easycart' ), __( 'This product has no SKU of its own: the widget shows once the shopper picks a variant that has one.', 'wp-easycart' ) );
			}
			WP_EasyCart_Product_Buy::prepare( $product, $this->ec_is_editor() );
			/* A product without a SKU of its own is printed hidden: product-buy.js shows it with the chosen variant's SKU. */
			echo '<div class="wpec-el wpec-sku" data-product-id="' . esc_attr( $product->product_id ) . '"' . ( ( '' === $sku ) ? ' data-wpec-sku-variants="1" hidden' : '' ) . '>';
			$icon = $this->pb_icon_html( isset( $settings['sku_icon'] ) ? $settings['sku_icon'] : null );
			if ( '' !== $icon ) {
				echo '<span class="wpec-sku__icon" aria-hidden="true">' . $icon . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor's icon markup.
			}
			if ( isset( $settings['show_label'] ) && 'yes' === $settings['show_label'] ) {
				$label = ( isset( $settings['label_text'] ) && '' !== trim( (string) $settings['label_text'] ) ) ? (string) $settings['label_text'] : WP_EasyCart_Product_Buy::text( 'sku_label', __( 'SKU:', 'wp-easycart' ) );
				echo '<span class="wpec-sku__label">' . esc_html( wp_strip_all_tags( $label ) ) . '</span> ';
				if ( isset( $settings['separator_text'] ) && '' !== trim( (string) $settings['separator_text'] ) ) {
					echo '<span class="wpec-sku__sep" aria-hidden="true">' . esc_html( wp_strip_all_tags( (string) $settings['separator_text'] ) ) . '</span> ';
				}
			}
			/* .ec_details_sku[data-wpec-linked-product]: ec-store.js writes the chosen variant's SKU here. */
			echo '<span class="ec_details_sku wpec-sku__value" data-wpec-linked-product="' . esc_attr( $product->product_id ) . '" data-wpec-default-sku="' . esc_attr( $product->model_number ) . '" aria-live="polite">' . esc_html( $product->model_number ) . '</span>';
			echo '</div>';
		}

		/**
		 * The equivalent shortcode for post_content.
		 *
		 * @return string
		 */
		protected function plain_content_shortcode() {
			return $this->pb_plain_shortcode( 'ec_product_details_sku', $this->get_settings_for_display() );
		}
	}

endif;
