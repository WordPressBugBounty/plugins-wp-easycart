<?php
/**
 * Elementor Pro display condition "Customer" ( 6.0.2 ): Customer | Is / Is not | Signed in / Signed out ( to the store: an
 * EasyCart customer account, not a guest checkout ).
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

if ( ! class_exists( 'WP_EasyCart_Elementor_Dynamic_Condition_Customer' ) && class_exists( '\ElementorPro\Modules\DisplayConditions\Conditions\Base\Condition_Base' ) ) :

	/**
	 * Show or hide an element by whether a customer is signed in.
	 */
	class WP_EasyCart_Elementor_Dynamic_Condition_Customer extends \ElementorPro\Modules\DisplayConditions\Conditions\Base\Condition_Base {

		/**
		 * Name ( stored in saved pages ).
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_customer_status';
		}

		/**
		 * Label.
		 *
		 * @return string
		 */
		public function get_label() {
			return __( 'Customer', 'wp-easycart' );
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
						'signed_in'  => __( 'Signed in', 'wp-easycart' ),
						'signed_out' => __( 'Signed out', 'wp-easycart' ),
					),
					'default' => 'signed_in',
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
			$signed_in = WP_EasyCart_Elementor_Dynamic::customer_signed_in();
			$status    = ( is_array( $args ) && isset( $args['status'] ) ) ? (string) $args['status'] : 'signed_in';
			return WP_EasyCart_Elementor_Dynamic::condition_result( $args, ( 'signed_out' === $status ) ? ! $signed_in : $signed_in );
		}
	}

endif;
