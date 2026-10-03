<?php
/**
 * Dynamic tag "Product price (number)" ( 6.0.2 ): the price as a plain number for number settings ( a counter, a price
 * table's price, a progress bar ).
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Dynamic_Tag_Product_Price_Number' ) && class_exists( 'WP_EasyCart_Elementor_Dynamic_Data_Tag' ) ) :

	/**
	 * A product price as a number.
	 */
	class WP_EasyCart_Elementor_Dynamic_Tag_Product_Price_Number extends WP_EasyCart_Elementor_Dynamic_Data_Tag {

		/**
		 * Name ( stored in saved pages ).
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp-easycart-product-price-number';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Product price (number)', 'wp-easycart' );
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
		 * Controls.
		 */
		protected function register_controls() {
			$this->add_control(
				'format',
				array(
					'label'   => __( 'Show', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => 'current',
					'options' => WP_EasyCart_Elementor_Dynamic::price_formats(),
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
			if ( ! $product ) {
				return '';
			}
			WP_EasyCart_Elementor_Dynamic::no_page_cache();
			$format = $this->ec_setting( 'format', 'current' );
			if ( ! array_key_exists( $format, WP_EasyCart_Elementor_Dynamic::price_formats() ) ) {
				$format = 'current';
			}
			$amount = WP_EasyCart_Elementor_Dynamic::price_amount( $product, $format );
			return ( null === $amount ) ? '' : WP_EasyCart_Elementor_Dynamic::number( $amount );
		}
	}

endif;
