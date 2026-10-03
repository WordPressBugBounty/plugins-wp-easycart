<?php
/**
 * Elementor Pro display condition "Cart" ( 6.0.2 ): Cart | Is / Is not | Empty / Has items.
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

if ( ! class_exists( 'WP_EasyCart_Elementor_Dynamic_Condition_Cart' ) && class_exists( '\ElementorPro\Modules\DisplayConditions\Conditions\Base\Condition_Base' ) ) :

	/**
	 * Show or hide an element by whether the shopper's cart is empty.
	 */
	class WP_EasyCart_Elementor_Dynamic_Condition_Cart extends \ElementorPro\Modules\DisplayConditions\Conditions\Base\Condition_Base {

		/**
		 * Name ( stored in saved pages ).
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_cart_status';
		}

		/**
		 * Label.
		 *
		 * @return string
		 */
		public function get_label() {
			return __( 'Cart', 'wp-easycart' );
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
						'empty'     => __( 'Empty', 'wp-easycart' ),
						'has_items' => __( 'Has items', 'wp-easycart' ),
					),
					'default' => 'has_items',
				)
			);
		}

		/**
		 * Whether the element shows.
		 *
		 * @param array $args Saved condition.
		 * @return bool
		 */
		public function check( $args ): bool {
			WP_EasyCart_Elementor_Dynamic::no_page_cache();
			$state     = WP_EasyCart_Elementor_Dynamic::cart_state();
			$has_items = (int) $state['count'] > 0;
			$status    = ( is_array( $args ) && isset( $args['status'] ) ) ? (string) $args['status'] : 'has_items';
			return WP_EasyCart_Elementor_Dynamic::condition_result( $args, ( 'empty' === $status ) ? ! $has_items : $has_items );
		}
	}

endif;
