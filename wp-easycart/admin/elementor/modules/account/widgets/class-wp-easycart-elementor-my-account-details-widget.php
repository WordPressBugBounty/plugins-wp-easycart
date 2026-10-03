<?php
/**
 * Account Details ( 6.0.2 ): the customer's name, email and newsletter choice, and the password change form. The
 * newsletter subscription and the fields a form does not show are kept as they are when it is saved.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_My_Account_Details_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Account_Widget' ) ) :

	/**
	 * Account Details widget.
	 */
	class WP_EasyCart_Elementor_My_Account_Details_Widget extends WP_EasyCart_Elementor_Account_Widget {

		/**
		 * Name.
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_my_account_details';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return esc_html__( 'Account Details', 'wp-easycart' );
		}

		/**
		 * Icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-person';
		}

		/**
		 * Keywords.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array( 'account details', 'profile', 'edit account', 'change password', 'password', 'email', 'my account', 'woocommerce' );
		}

		/**
		 * Content controls.
		 */
		protected function ec_content_controls() {
			$this->start_controls_section(
				'section_content',
				array(
					'label' => esc_html__( 'Account details', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->add_control(
				'show',
				array(
					'label'   => esc_html__( 'Show', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => 'both',
					'options' => array(
						'both'     => esc_html__( 'Name and email, and the password change', 'wp-easycart' ),
						'personal' => esc_html__( 'Name and email', 'wp-easycart' ),
						'password' => esc_html__( 'Password change', 'wp-easycart' ),
					),
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
			$which = isset( $context['settings']['show'] ) && in_array( $context['settings']['show'], array( 'both', 'personal', 'password' ), true ) ? $context['settings']['show'] : 'both';
			WP_EasyCart_Elementor_Account_Views::details( $page, $which );
			return true;
		}
	}

endif;
