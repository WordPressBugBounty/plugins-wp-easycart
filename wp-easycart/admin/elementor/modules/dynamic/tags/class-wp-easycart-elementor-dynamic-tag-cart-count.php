<?php
/**
 * Dynamic tag "Cart count" ( 6.0.2 ): how many items the shopper's cart holds.
 *
 * Kept up to date in the browser by default ( "Update when the cart changes" ): the number sits in a small span marked
 * data-wpec-cart-widget, which the store script refreshes from the uncached cart state ( ec_ajax_cart_state,
 * wpeasycart_cart_changed ) when the page loads and whenever the cart changes ( assets/wpec-dynamic.js applies it, and asks
 * itself on a page without the store script ), so the page can stay in a page cache. Switched off ( where a span is not
 * allowed, e.g. an attribute ), the page is kept out of page caches instead.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Dynamic_Tag_Cart_Count' ) && class_exists( 'WP_EasyCart_Elementor_Dynamic_Text_Tag' ) ) :

	/**
	 * Items in the cart.
	 */
	class WP_EasyCart_Elementor_Dynamic_Tag_Cart_Count extends WP_EasyCart_Elementor_Dynamic_Text_Tag {

		/**
		 * Name ( stored in saved pages ).
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp-easycart-cart-count';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Cart count', 'wp-easycart' );
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
		 * Prints the count.
		 */
		public function render() {
			$state = WP_EasyCart_Elementor_Dynamic::cart_state();
			$count = (string) (int) $state['count'];
			if ( $this->ec_switch( 'live' ) ) {
				WP_EasyCart_Elementor_Dynamic::enqueue_assets();
				/* A page cache must never keep one visitor's cart for others: on a cacheable page the store script fills it in. */
				$count = WP_EasyCart_Elementor_Dynamic::live_cart_value( $count, '0' );
				echo '<span class="wpec-dyn-cart" data-wpec-cart="count" data-wpec-cart-widget>' . esc_html( $count ) . '</span>';
				return;
			}
			WP_EasyCart_Elementor_Dynamic::no_page_cache();
			echo esc_html( $count );
		}
	}

endif;
