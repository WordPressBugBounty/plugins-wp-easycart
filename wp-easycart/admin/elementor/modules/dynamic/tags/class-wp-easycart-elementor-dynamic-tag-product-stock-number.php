<?php
/**
 * Dynamic tag "Product stock (number)" ( 6.0.2 ): units left as a number ( empty when the product's stock is not counted ).
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Dynamic_Tag_Product_Stock_Number' ) && class_exists( 'WP_EasyCart_Elementor_Dynamic_Data_Tag' ) ) :

	/**
	 * Stock as a number.
	 */
	class WP_EasyCart_Elementor_Dynamic_Tag_Product_Stock_Number extends WP_EasyCart_Elementor_Dynamic_Data_Tag {

		/**
		 * Name ( stored in saved pages ).
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp-easycart-product-stock-number';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Product stock (number)', 'wp-easycart' );
		}

		/**
		 * Categories.
		 *
		 * @return array
		 */
		public function get_categories() {
			return array( 'number' );
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
			if ( ! $product ) {
				return '';
			}
			WP_EasyCart_Elementor_Dynamic::no_page_cache();
			$stock = WP_EasyCart_Elementor_Dynamic::stock( $product );
			return $stock['tracked'] ? (string) (int) $stock['quantity'] : '';
		}
	}

endif;
