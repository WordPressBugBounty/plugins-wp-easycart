<?php
/**
 * Dynamic tag "Cart subtotal" ( 6.0.2 ): the cart's subtotal as the store shows it. Kept up to date in the browser like
 * "Cart count".
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Dynamic_Tag_Cart_Subtotal' ) && class_exists( 'WP_EasyCart_Elementor_Dynamic_Text_Tag' ) ) :

	/**
	 * Cart subtotal.
	 */
	class WP_EasyCart_Elementor_Dynamic_Tag_Cart_Subtotal extends WP_EasyCart_Elementor_Dynamic_Text_Tag {

		/**
		 * Name ( stored in saved pages ).
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp-easycart-cart-subtotal';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Cart subtotal', 'wp-easycart' );
		}

		/**
		 * Controls.
		 */
		protected function register_controls() {
			$this->add_control(
				'live',
				array(
					'label'       => __( 'Update when the cart changes', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SWITCHER,
					'default'     => 'yes',
					'description' => __( 'Turn off only where text with formatting is not allowed, such as an image\'s alt text.', 'wp-easycart' ),
				)
			);
		}

		/**
		 * Prints the subtotal.
		 */
		public function render() {
			$state    = WP_EasyCart_Elementor_Dynamic::cart_state();
			$subtotal = (string) $state['subtotal_display'];
			if ( $this->ec_switch( 'live' ) ) {
				WP_EasyCart_Elementor_Dynamic::enqueue_assets();
				/* A page cache must never keep one visitor's cart for others: on a cacheable page the store script fills it in. */
				$subtotal = WP_EasyCart_Elementor_Dynamic::live_cart_value( $subtotal, '' );
				echo '<span class="wpec-dyn-cart" data-wpec-cart="subtotal" data-wpec-cart-widget>' . esc_html( $subtotal ) . '</span>';
				return;
			}
			WP_EasyCart_Elementor_Dynamic::no_page_cache();
			echo esc_html( $subtotal );
		}
	}

endif;
