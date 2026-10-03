<?php
/**
 * Dynamic tag "Product price" ( 6.0.2 ): the price the shopper pays, the regular or sale price, or the lowest price of a
 * product with options, as the product page shows it ( currency, VAT, customer role, sale ).
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Dynamic_Tag_Product_Price' ) && class_exists( 'WP_EasyCart_Elementor_Dynamic_Text_Tag' ) ) :

	/**
	 * A product price as text.
	 */
	class WP_EasyCart_Elementor_Dynamic_Tag_Product_Price extends WP_EasyCart_Elementor_Dynamic_Text_Tag {

		/**
		 * Name ( stored in saved pages ).
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp-easycart-product-price';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Product price', 'wp-easycart' );
		}

		/**
		 * Shown next to the tag in the panel.
		 *
		 * @return string
		 */
		public function get_panel_template_setting_key() {
			return 'format';
		}

		/**
		 * Controls.
		 */
		protected function register_controls() {
			$this->add_control(
				'format',
				array(
					'label'   => __( 'Show', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => 'current',
					'options' => WP_EasyCart_Elementor_Dynamic::price_formats(),
				)
			);
			$this->add_control(
				'currency',
				array(
					'label'   => __( 'Currency symbol', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SWITCHER,
					'default' => 'yes',
				)
			);
			$this->ec_add_product_controls();
		}

		/**
		 * Value.
		 *
		 * @return string
		 */
		protected function ec_text() {
			$product = $this->ec_product();
			if ( ! $product ) {
				return '';
			}
			WP_EasyCart_Elementor_Dynamic::no_page_cache();
			$format = $this->ec_setting( 'format', 'current' );
			if ( ! array_key_exists( $format, WP_EasyCart_Elementor_Dynamic::price_formats() ) ) {
				$format = 'current';
			}
			return WP_EasyCart_Elementor_Dynamic::price_text( $product, $format, $this->ec_switch( 'currency' ) );
		}
	}

endif;
