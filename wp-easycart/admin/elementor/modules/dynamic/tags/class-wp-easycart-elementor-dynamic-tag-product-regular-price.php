<?php
/**
 * Dynamic tag "Product regular price" ( 6.0.2 ): the price before a sale ( the struck-through price ).
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Dynamic_Tag_Product_Regular_Price' ) && class_exists( 'WP_EasyCart_Elementor_Dynamic_Text_Tag' ) ) :

	/**
	 * The regular price.
	 */
	class WP_EasyCart_Elementor_Dynamic_Tag_Product_Regular_Price extends WP_EasyCart_Elementor_Dynamic_Text_Tag {

		/**
		 * Name ( stored in saved pages ).
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp-easycart-product-regular-price';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Product regular price', 'wp-easycart' );
		}

		/**
		 * Controls.
		 */
		protected function register_controls() {
			$this->add_control(
				'only_on_sale',
				array(
					'label'       => __( 'Only when on sale', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SWITCHER,
					'default'     => 'yes',
					'description' => __( 'Shows nothing (or your fallback text) for products that are not on sale.', 'wp-easycart' ),
				)
			);
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
			if ( $this->ec_switch( 'only_on_sale' ) && ! WP_EasyCart_Elementor_Dynamic::on_sale( $product ) ) {
				return '';
			}
			return WP_EasyCart_Elementor_Dynamic::price_text( $product, 'regular', $this->ec_switch( 'currency' ) );
		}
	}

endif;
