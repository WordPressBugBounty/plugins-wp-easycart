<?php
/**
 * Dynamic tag "Customer first name" ( 6.0.2 ): the signed-in customer's first name ( "Welcome back, Sam" ). Nothing ( or the
 * fallback text ) for a shopper who is not signed in. Pages using it are kept out of page caches.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Dynamic_Tag_Customer_First_Name' ) && class_exists( 'WP_EasyCart_Elementor_Dynamic_Text_Tag' ) ) :

	/**
	 * Customer first name.
	 */
	class WP_EasyCart_Elementor_Dynamic_Tag_Customer_First_Name extends WP_EasyCart_Elementor_Dynamic_Text_Tag {

		/**
		 * Name ( stored in saved pages ).
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp-easycart-customer-first-name';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Customer first name', 'wp-easycart' );
		}

		/**
		 * Value ( a sample name in the editor, so the design shows how it reads ).
		 *
		 * @return string
		 */
		protected function ec_text() {
			WP_EasyCart_Elementor_Dynamic::no_page_cache();
			$name = WP_EasyCart_Elementor_Dynamic::customer_first_name();
			if ( '' === $name && WP_EasyCart_Elementor_Dynamic::is_editor() ) {
				$name = __( 'Sam', 'wp-easycart' );
			}
			return $name;
		}
	}

endif;
