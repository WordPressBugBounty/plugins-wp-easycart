<?php
/**
 * Dynamic tag "Product URL" ( 6.0.2 ): the product page, for any link.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Dynamic_Tag_Product_Url' ) && class_exists( 'WP_EasyCart_Elementor_Dynamic_Data_Tag' ) ) :

	/**
	 * The product page address.
	 */
	class WP_EasyCart_Elementor_Dynamic_Tag_Product_Url extends WP_EasyCart_Elementor_Dynamic_Data_Tag {

		/**
		 * Name ( stored in saved pages ).
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp-easycart-product-url';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Product URL', 'wp-easycart' );
		}

		/**
		 * Categories.
		 *
		 * @return array
		 */
		public function get_categories() {
			return array( 'url' );
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
		 * @param array $options Unused.
		 * @return string
		 */
		public function get_value( array $options = array() ) {
			$product = $this->ec_product();
			if ( ! $product || ! method_exists( $product, 'get_product_link' ) ) {
				return '';
			}
			return esc_url_raw( (string) $product->get_product_link() );
		}
	}

endif;
