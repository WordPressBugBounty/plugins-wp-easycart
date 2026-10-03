<?php
/**
 * Dynamic tag "Product sale price" ( 6.0.2 ): the sale price, only while the product is on sale.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Dynamic_Tag_Product_Sale_Price' ) && class_exists( 'WP_EasyCart_Elementor_Dynamic_Text_Tag' ) ) :

	/**
	 * The sale price.
	 */
	class WP_EasyCart_Elementor_Dynamic_Tag_Product_Sale_Price extends WP_EasyCart_Elementor_Dynamic_Text_Tag {

		/**
		 * Name ( stored in saved pages ).
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp-easycart-product-sale-price';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Product sale price', 'wp-easycart' );
		}

		/**
		 * Controls.
		 */
		protected function register_controls() {
			$this->add_control(
				'currency',
				array(
					'label'   => __( 'Currency symbol', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SWITCHER,
					'default' => 'yes',
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
			WP_EasyCart_Elementor_Dynamic::no_page_cache();
			return WP_EasyCart_Elementor_Dynamic::price_text( $product, 'sale', $this->ec_switch( 'currency' ) );
		}
	}

endif;
