<?php
/**
 * Side Cart ( wp_easycart_side_cart, 6.0.2 ): a WP EasyCart PRO widget. This is its locked stand-in: the editor shows what
 * it does with an Upgrade ( or Update ) link, and visitors see nothing. WP EasyCart PRO registers the working widget under
 * the same name ( WP_EasyCart_Elementor_Side_Cart_Widget_Pro ), which Elementor keeps because it is registered last.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/trait-wp-easycart-elementor-checkout-controls.php';

if ( ! class_exists( 'WP_EasyCart_Elementor_Side_Cart_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Widget_Base' ) ) :

	/**
	 * Side Cart widget ( locked ).
	 */
	class WP_EasyCart_Elementor_Side_Cart_Widget extends WP_EasyCart_Elementor_Widget_Base {

		use WP_EasyCart_Elementor_Checkout_Controls;

		/**
		 * Panel category.
		 *
		 * @var string
		 */
		protected static $ec_category = 'checkout';

		/**
		 * WP EasyCart PRO feature.
		 *
		 * @var string
		 */
		protected static $ec_pro_feature = 'side_cart';

		/**
		 * Help page.
		 *
		 * @var string
		 */
		protected static $ec_help = 'side-cart';

		/**
		 * Widget name ( stored in saved pages ).
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_side_cart';
		}

		/**
		 * Widget title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Side Cart', 'wp-easycart' );
		}

		/**
		 * Panel icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-cart-medium';
		}

		/**
		 * Search keywords.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array( 'side cart', 'slide out cart', 'off canvas cart', 'mini cart', 'free shipping bar', 'fly cart' );
		}

		/**
		 * Styles this widget loads.
		 *
		 * @return array
		 */
		public function get_style_depends() {
			return array_merge( parent::get_style_depends(), array( WP_EasyCart_Elementor_Checkout_Module::STYLE ) );
		}

		/** Controls: what the widget does, and how to get it. */
		protected function register_controls() {
			$this->ec_locked_section(
				'ec_side_cart_section',
				__( 'Side cart', 'wp-easycart' ),
				'side_cart',
				__( 'A cart that slides in when a shopper adds a product: change quantities, remove items, see how far they are from free shipping, and go on to the cart or the checkout.', 'wp-easycart' ),
				array( __( 'Opens when a product is added', 'wp-easycart' ), __( 'Quantities and remove', 'wp-easycart' ), __( 'Free shipping progress bar', 'wp-easycart' ), __( 'Cart and Checkout buttons', 'wp-easycart' ) )
			);
		}

		/** Draw: the locked note in the editor, nothing for visitors. */
		protected function render() {
			if ( $this->ec_render_pro_lock() ) {
				return;
			}
		}
	}

endif;
