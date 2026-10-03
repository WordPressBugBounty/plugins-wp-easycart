<?php
/**
 * Dynamic tag "Review count" ( 6.0.2 ): how many customer reviews the product has, as text or for a number setting.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Dynamic_Tag_Product_Review_Count' ) && class_exists( 'WP_EasyCart_Elementor_Dynamic_Text_Tag' ) ) :

	/**
	 * Number of reviews.
	 */
	class WP_EasyCart_Elementor_Dynamic_Tag_Product_Review_Count extends WP_EasyCart_Elementor_Dynamic_Text_Tag {

		/**
		 * Name ( stored in saved pages ).
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp-easycart-product-review-count';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Review count', 'wp-easycart' );
		}

		/**
		 * Categories.
		 *
		 * @return array
		 */
		public function get_categories() {
			return array( 'text', 'number' );
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
			if ( ! $product ) {
				return '';
			}
			$rating = WP_EasyCart_Elementor_Dynamic::rating( $product );
			return (string) (int) $rating['count'];
		}
	}

endif;
