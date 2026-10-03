<?php
/**
 * Dynamic tag "Product gallery" ( 6.0.2 ): every product image, for gallery and carousel widgets.
 *
 * Images from the media library carry their id ( every gallery widget can use them ); images uploaded to the store before it
 * used the media library carry only their address, which widgets that read addresses ( Image Carousel, Elementor Pro's
 * Gallery ) show.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Dynamic_Tag_Product_Gallery' ) && class_exists( 'WP_EasyCart_Elementor_Dynamic_Data_Tag' ) ) :

	/**
	 * All product images.
	 */
	class WP_EasyCart_Elementor_Dynamic_Tag_Product_Gallery extends WP_EasyCart_Elementor_Dynamic_Data_Tag {

		/**
		 * Name ( stored in saved pages ).
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp-easycart-product-gallery';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Product gallery', 'wp-easycart' );
		}

		/**
		 * Categories.
		 *
		 * @return array
		 */
		public function get_categories() {
			return array( 'gallery' );
		}

		/**
		 * Controls.
		 */
		protected function register_controls() {
			$this->ec_add_product_controls();
		}

		/**
		 * Value: a list of array( 'id', 'url' ).
		 *
		 * @param array $options Unused.
		 * @return array
		 */
		public function get_value( array $options = array() ) {
			$product = $this->ec_product();
			return $product ? WP_EasyCart_Elementor_Dynamic::images( $product ) : array();
		}
	}

endif;
