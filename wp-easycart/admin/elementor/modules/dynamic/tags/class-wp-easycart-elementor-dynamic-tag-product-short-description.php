<?php
/**
 * Dynamic tag "Product short description" ( 6.0.2 ).
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Dynamic_Tag_Product_Short_Description' ) && class_exists( 'WP_EasyCart_Elementor_Dynamic_Text_Tag' ) ) :

	/**
	 * The short description ( its line breaks and basic formatting kept ).
	 */
	class WP_EasyCart_Elementor_Dynamic_Tag_Product_Short_Description extends WP_EasyCart_Elementor_Dynamic_Text_Tag {

		/**
		 * Name ( stored in saved pages ).
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp-easycart-product-short-description';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Product short description', 'wp-easycart' );
		}

		/**
		 * Controls.
		 */
		protected function register_controls() {
			$this->ec_add_product_controls();
		}

		/**
		 * Prints the short description.
		 */
		public function render() {
			$product = $this->ec_product();
			if ( ! $product ) {
				return;
			}
			$text = trim( stripslashes( (string) $product->short_description ) );
			if ( '' === $text ) {
				return;
			}
			if ( false === strpos( $text, '<' ) ) {
				$text = nl2br( esc_html( $text ) );
			}
			echo wp_kses_post( $text );
		}
	}

endif;
