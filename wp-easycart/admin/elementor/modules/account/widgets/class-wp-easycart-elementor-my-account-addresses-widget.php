<?php
/**
 * Addresses ( 6.0.2 ): the customer's billing and shipping address forms, side by side on wide screens. Saving keeps the
 * customer on this page with a "saved" message.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_My_Account_Addresses_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Account_Widget' ) ) :

	/**
	 * Addresses widget.
	 */
	class WP_EasyCart_Elementor_My_Account_Addresses_Widget extends WP_EasyCart_Elementor_Account_Widget {

		/**
		 * Name.
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_my_account_addresses';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return esc_html__( 'Addresses', 'wp-easycart' );
		}

		/**
		 * Icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-map-pin';
		}

		/**
		 * Keywords.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array( 'address', 'addresses', 'billing address', 'shipping address', 'my account', 'woocommerce' );
		}

		/**
		 * Content controls.
		 */
		protected function ec_content_controls() {
			$this->start_controls_section(
				'section_content',
				array(
					'label' => esc_html__( 'Addresses', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->add_control(
				'show',
				array(
					'label'       => esc_html__( 'Show', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'default'     => 'both',
					'options'     => array(
						'both'     => esc_html__( 'Billing and shipping address', 'wp-easycart' ),
						'billing'  => esc_html__( 'Billing address', 'wp-easycart' ),
						'shipping' => esc_html__( 'Shipping address', 'wp-easycart' ),
					),
					'description' => esc_html__( 'The shipping address shows only when your store ships.', 'wp-easycart' ),
				)
			);
			$this->ec_title_control( esc_html__( 'Show the titles', 'wp-easycart' ) );
			$this->ec_visibility_control();
			$this->end_controls_section();
		}

		/**
		 * Style section of the two forms side by side.
		 */
		protected function ec_widget_style_controls() {
			$this->ec_columns_style_controls();
		}

		/**
		 * Draws the forms.
		 *
		 * @param WP_EasyCart_Elementor_Account_Page $page    Account page.
		 * @param array                              $context Render context.
		 * @return bool
		 */
		public function ec_render_body( $page, $context ) {
			$which = isset( $context['settings']['show'] ) && in_array( $context['settings']['show'], array( 'both', 'billing', 'shipping' ), true ) ? $context['settings']['show'] : 'both';
			WP_EasyCart_Elementor_Account_Views::addresses( $page, $which );
			return true;
		}
	}

endif;
