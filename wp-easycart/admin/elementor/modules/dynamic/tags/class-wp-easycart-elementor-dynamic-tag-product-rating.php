<?php
/**
 * Dynamic tag "Product rating" ( 6.0.2 ): the average customer rating as text ( "4.5" ), nothing before the first review.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Dynamic_Tag_Product_Rating' ) && class_exists( 'WP_EasyCart_Elementor_Dynamic_Text_Tag' ) ) :

	/**
	 * Average rating as text.
	 */
	class WP_EasyCart_Elementor_Dynamic_Tag_Product_Rating extends WP_EasyCart_Elementor_Dynamic_Text_Tag {

		/**
		 * Name ( stored in saved pages ).
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp-easycart-product-rating';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Product rating', 'wp-easycart' );
		}

		/**
		 * Controls.
		 */
		protected function register_controls() {
			$this->add_control(
				'out_of',
				array(
					'label'   => __( 'Add "/ 5"', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SWITCHER,
					'default' => '',
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
			$rating = WP_EasyCart_Elementor_Dynamic::rating( $product );
			if ( ! $rating['count'] ) {
				return '';
			}
			return number_format_i18n( $rating['average'], 1 ) . ( $this->ec_switch( 'out_of' ) ? ' / 5' : '' );
		}
	}

endif;
