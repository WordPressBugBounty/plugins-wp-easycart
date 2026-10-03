<?php
/**
 * Dynamic tag "Cart count (number)" ( 6.0.2 ): items in the cart for number settings. Pages using it are kept out of page
 * caches ( a number setting can't be refreshed in the browser ).
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Dynamic_Tag_Cart_Count_Number' ) && class_exists( 'WP_EasyCart_Elementor_Dynamic_Data_Tag' ) ) :

	/**
	 * Items in the cart as a number.
	 */
	class WP_EasyCart_Elementor_Dynamic_Tag_Cart_Count_Number extends WP_EasyCart_Elementor_Dynamic_Data_Tag {

		/**
		 * Name ( stored in saved pages ).
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp-easycart-cart-count-number';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Cart count (number)', 'wp-easycart' );
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
		 * Value.
		 *
		 * @param array $options Unused.
		 * @return string
		 */
		public function get_value( array $options = array() ) {
			WP_EasyCart_Elementor_Dynamic::no_page_cache();
			$state = WP_EasyCart_Elementor_Dynamic::cart_state();
			return (string) (int) $state['count'];
		}
	}

endif;
