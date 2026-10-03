<?php
/**
 * Dynamic tag "Product image" ( 6.0.2 ): the main image, or another of the product's images, for any image setting.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Dynamic_Tag_Product_Image' ) && class_exists( 'WP_EasyCart_Elementor_Dynamic_Data_Tag' ) ) :

	/**
	 * One product image.
	 */
	class WP_EasyCart_Elementor_Dynamic_Tag_Product_Image extends WP_EasyCart_Elementor_Dynamic_Data_Tag {

		/**
		 * Name ( stored in saved pages ).
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp-easycart-product-image';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Product image', 'wp-easycart' );
		}

		/**
		 * Categories.
		 *
		 * @return array
		 */
		public function get_categories() {
			return array( 'image' );
		}

		/**
		 * Controls.
		 */
		protected function register_controls() {
			$this->add_control(
				'image',
				array(
					'label'       => __( 'Image', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::NUMBER,
					'default'     => 1,
					'min'         => 1,
					'max'         => 10,
					'step'        => 1,
					'description' => __( '1 is the main image, 2 the next one, and so on.', 'wp-easycart' ),
				)
			);
			$this->ec_add_product_controls();
		}

		/**
		 * Value: array( 'id' => media library id or 0, 'url' => address ).
		 *
		 * @param array $options Unused.
		 * @return array
		 */
		public function get_value( array $options = array() ) {
			$product = $this->ec_product();
			if ( ! $product ) {
				return array();
			}
			$images = WP_EasyCart_Elementor_Dynamic::images( $product );
			$index  = max( 1, absint( $this->ec_setting( 'image', '1' ) ) ) - 1;
			if ( ! isset( $images[ $index ] ) ) {
				return array();
			}
			$image        = $images[ $index ];
			$image['alt'] = WP_EasyCart_Elementor_Dynamic::plain( $product->title, 150 );
			return $image;
		}
	}

endif;
