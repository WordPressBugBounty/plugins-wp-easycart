<?php
/**
 * Dynamic tag "Product SKU" ( 6.0.2 ): the product's model number.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Dynamic_Tag_Product_Sku' ) && class_exists( 'WP_EasyCart_Elementor_Dynamic_Text_Tag' ) ) :

	/**
	 * The SKU.
	 */
	class WP_EasyCart_Elementor_Dynamic_Tag_Product_Sku extends WP_EasyCart_Elementor_Dynamic_Text_Tag {

		/**
		 * Name ( stored in saved pages ).
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp-easycart-product-sku';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Product SKU', 'wp-easycart' );
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
			return $product ? WP_EasyCart_Elementor_Dynamic::plain( $product->model_number, 200 ) : '';
		}
	}

endif;
