<?php
/**
 * Dynamic tag "Product stock" ( 6.0.2 ): "12 Left in Stock", "OUT OF STOCK" or "Backordered", in the product page's own
 * wording ( the store's language file ).
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Dynamic_Tag_Product_Stock' ) && class_exists( 'WP_EasyCart_Elementor_Dynamic_Text_Tag' ) ) :

	/**
	 * Stock as text.
	 */
	class WP_EasyCart_Elementor_Dynamic_Tag_Product_Stock extends WP_EasyCart_Elementor_Dynamic_Text_Tag {

		/**
		 * Name ( stored in saved pages ).
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp-easycart-product-stock';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Product stock', 'wp-easycart' );
		}

		/**
		 * Controls.
		 */
		protected function register_controls() {
			$this->add_control(
				'show',
				array(
					'label'       => __( 'Show', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'default'     => 'count',
					'options'     => array(
						'count'  => __( 'Units left, or out of stock', 'wp-easycart' ),
						'all'    => __( 'Units left, In stock, or out of stock', 'wp-easycart' ),
						'status' => __( 'Only out of stock or backordered', 'wp-easycart' ),
					),
					'description' => __( 'Products whose stock is not counted show nothing while they can be bought, unless the choice says In stock.', 'wp-easycart' ),
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
			$show = $this->ec_setting( 'show', 'count' );
			return WP_EasyCart_Elementor_Dynamic::stock_text( $product, in_array( $show, array( 'status', 'all' ), true ) ? $show : 'count' );
		}
	}

endif;
