<?php
/**
 * Dynamic tag "Product manufacturer" ( 6.0.2 ): the product's manufacturer ( brand ).
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Dynamic_Tag_Product_Manufacturer' ) && class_exists( 'WP_EasyCart_Elementor_Dynamic_Text_Tag' ) ) :

	/**
	 * Manufacturer name.
	 */
	class WP_EasyCart_Elementor_Dynamic_Tag_Product_Manufacturer extends WP_EasyCart_Elementor_Dynamic_Text_Tag {

		/**
		 * Name ( stored in saved pages ).
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp-easycart-product-manufacturer';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Product manufacturer', 'wp-easycart' );
		}

		/**
		 * Controls.
		 */
		protected function register_controls() {
			$this->ec_add_product_controls();
		}

		/**
		 * Value.
		 *
		 * @return string
		 */
		protected function ec_text() {
			$product = $this->ec_product();
			if ( ! $product || empty( $product->manufacturer_id ) ) {
				return '';
			}
			$name = (string) $product->manufacturer_name;
			if ( function_exists( 'wp_easycart_language' ) ) {
				$name = (string) wp_easycart_language()->convert_text( $name );
			}
			return WP_EasyCart_Elementor_Dynamic::plain( $name, 200 );
		}
	}

endif;
