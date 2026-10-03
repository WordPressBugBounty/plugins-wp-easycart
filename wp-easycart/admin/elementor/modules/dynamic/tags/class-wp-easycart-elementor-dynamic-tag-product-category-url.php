<?php
/**
 * Dynamic tag "Product category URL" ( 6.0.2 ): the page of the product's first category.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Dynamic_Tag_Product_Category_Url' ) && class_exists( 'WP_EasyCart_Elementor_Dynamic_Data_Tag' ) ) :

	/**
	 * The first category's page.
	 */
	class WP_EasyCart_Elementor_Dynamic_Tag_Product_Category_Url extends WP_EasyCart_Elementor_Dynamic_Data_Tag {

		/**
		 * Name ( stored in saved pages ).
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp-easycart-product-category-url';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Product category URL', 'wp-easycart' );
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
			if ( ! $product ) {
				return '';
			}
			$categories = WP_EasyCart_Elementor_Dynamic::categories( $product );
			if ( ! $categories ) {
				return '';
			}
			return esc_url_raw( WP_EasyCart_Elementor_Dynamic::category_url( $product, $categories[0] ) );
		}
	}

endif;
