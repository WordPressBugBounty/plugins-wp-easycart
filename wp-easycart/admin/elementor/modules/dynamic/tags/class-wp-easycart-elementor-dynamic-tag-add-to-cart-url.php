<?php
/**
 * Dynamic tag "Add to cart URL" ( 6.0.2 ): a link that puts the product in the cart, for any Button, Call to Action or Price
 * Table. The shopper then goes to the cart, or stays on the page and sees a short note.
 *
 * A product the link can't add ( options to choose, a subscription, a gift card, out of stock ... ) links to its product page
 * instead. See WP_EasyCart_Elementor_Dynamic_Add_To_Cart.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Dynamic_Tag_Add_To_Cart_Url' ) && class_exists( 'WP_EasyCart_Elementor_Dynamic_Data_Tag' ) ) :

	/**
	 * An add to cart link.
	 */
	class WP_EasyCart_Elementor_Dynamic_Tag_Add_To_Cart_Url extends WP_EasyCart_Elementor_Dynamic_Data_Tag {

		/**
		 * Name ( stored in saved pages ).
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp-easycart-add-to-cart-url';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Add to cart URL', 'wp-easycart' );
		}

		/**
		 * Categories.
		 *
		 * @return array
		 */
		public function get_categories() {
			return array( 'url' );
		}

		/**
		 * Controls.
		 */
		protected function register_controls() {
			$this->add_control(
				'after',
				array(
					'label'       => __( 'After adding', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'default'     => 'cart',
					'options'     => array(
						'cart' => __( 'Go to the cart', 'wp-easycart' ),
						'stay' => __( 'Stay on the page and show a note', 'wp-easycart' ),
					),
					'description' => __( 'A product with options to choose, a subscription, a gift card or one that is out of stock links to its product page instead.', 'wp-easycart' ),
				)
			);
			$this->add_control(
				'quantity',
				array(
					'label'   => __( 'Quantity', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::NUMBER,
					'default' => 1,
					'min'     => 1,
					'max'     => class_exists( 'WP_EasyCart_Elementor_Dynamic_Add_To_Cart' ) ? WP_EasyCart_Elementor_Dynamic_Add_To_Cart::max_quantity() : 20,
					'step'    => 1,
				)
			);
			$this->ec_add_product_controls();
		}

		/**
		 * Value.
		 *
		 * @param array $options Unused.
		 * @return string
		 */
		public function get_value( array $options = array() ) {
			$product = $this->ec_product();
			if ( ! $product || ! class_exists( 'WP_EasyCart_Elementor_Dynamic_Add_To_Cart' ) ) {
				return '';
			}
			$quantity = max( 1, min( WP_EasyCart_Elementor_Dynamic_Add_To_Cart::max_quantity(), absint( $this->ec_setting( 'quantity', '1' ) ) ) );
			return WP_EasyCart_Elementor_Dynamic_Add_To_Cart::url( $product, $quantity, 'stay' === $this->ec_setting( 'after', 'cart' ) );
		}
	}

endif;
