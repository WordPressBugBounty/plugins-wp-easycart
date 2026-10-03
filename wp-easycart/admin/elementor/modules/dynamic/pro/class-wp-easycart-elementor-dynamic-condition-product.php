<?php
/**
 * Elementor Pro display condition "Product" ( 6.0.2 ): Product | Is / Is not | On sale / In stock, for the product of the
 * page ( a product page, a product template, or each card of a Loop Grid ).
 *
 * Elementor Pro's element display conditions are an internal API: WP_EasyCart_Elementor_Dynamic::conditions_supported()
 * decides whether this class is loaded at all. The condition name is stored in saved pages: never rename it.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Dynamic_Condition_Product' ) && class_exists( '\ElementorPro\Modules\DisplayConditions\Conditions\Base\Condition_Base' ) ) :

	/**
	 * Show or hide an element by the product's sale or stock.
	 */
	class WP_EasyCart_Elementor_Dynamic_Condition_Product extends \ElementorPro\Modules\DisplayConditions\Conditions\Base\Condition_Base {

		/**
		 * Name ( stored in saved pages ).
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_product_status';
		}

		/**
		 * Label.
		 *
		 * @return string
		 */
		public function get_label() {
			return __( 'Product', 'wp-easycart' );
		}

		/**
		 * Group.
		 *
		 * @return string
		 */
		public function get_group() {
			return 'wp_easycart';
		}

		/**
		 * Controls.
		 */
		public function get_options() {
			$this->add_control(
				'comparator',
				array(
					'type'    => \Elementor\Controls_Manager::SELECT,
					'options' => WP_EasyCart_Elementor_Dynamic::condition_comparators(),
					'default' => 'is',
				)
			);
			$this->add_control(
				'status',
				array(
					'type'    => \Elementor\Controls_Manager::SELECT,
					'options' => array(
						'on_sale'  => __( 'On sale', 'wp-easycart' ),
						'in_stock' => __( 'In stock', 'wp-easycart' ),
					),
					'default' => 'on_sale',
				)
			);
		}

		/**
		 * Whether the element shows ( no product on the page: the product is neither on sale nor in stock ).
		 *
		 * @param array $args Saved condition.
		 * @return bool
		 */
		public function check( $args ): bool {
			WP_EasyCart_Elementor_Dynamic::no_page_cache();
			$product = WP_EasyCart_Elementor_Dynamic::product();
			$status  = ( is_array( $args ) && isset( $args['status'] ) ) ? (string) $args['status'] : 'on_sale';
			$match   = false;
			if ( $product ) {
				if ( 'in_stock' === $status ) {
					$stock = WP_EasyCart_Elementor_Dynamic::stock( $product );
					$match = $stock['in_stock'];
				} else {
					$match = WP_EasyCart_Elementor_Dynamic::on_sale( $product );
				}
			}
			return WP_EasyCart_Elementor_Dynamic::condition_result( $args, $match );
		}
	}

endif;
