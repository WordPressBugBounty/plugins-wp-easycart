<?php
/**
 * Account Dashboard ( 6.0.2 ): the store's account dashboard ( recent orders, downloads, email, billing and shipping
 * addresses ), without its menu unless the merchant wants it.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_My_Account_Dashboard_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Account_Widget' ) ) :

	/**
	 * Account Dashboard widget.
	 */
	class WP_EasyCart_Elementor_My_Account_Dashboard_Widget extends WP_EasyCart_Elementor_Account_Widget {

		/**
		 * Name.
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_my_account_dashboard';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return esc_html__( 'Account Dashboard', 'wp-easycart' );
		}

		/**
		 * Icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-user-circle-o';
		}

		/**
		 * Keywords.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array( 'dashboard', 'account', 'my account', 'recent orders', 'customer', 'woocommerce' );
		}

		/**
		 * Content controls.
		 */
		protected function ec_content_controls() {
			$this->start_controls_section(
				'section_content',
				array(
					'label' => esc_html__( 'Dashboard', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->add_control(
				'show_menu',
				array(
					'label'        => esc_html__( 'Show the store\'s account menu beside it', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'default'      => '',
					'return_value' => 'yes',
					'description'  => esc_html__( 'Or add the Account Navigation widget where you want the menu.', 'wp-easycart' ),
				)
			);
			$this->ec_visibility_control();
			$this->end_controls_section();
		}

		/**
		 * The menu columns stay when asked for.
		 *
		 * @param array $settings Settings.
		 * @return array
		 */
		protected function ec_strip( $settings ) {
			if ( isset( $settings['show_menu'] ) && 'yes' === $settings['show_menu'] ) {
				return array();
			}
			return parent::ec_strip( $settings );
		}

		/**
		 * Wrapper classes.
		 *
		 * @param array $settings Settings.
		 * @return array
		 */
		protected function ec_classes( $settings ) {
			$classes = parent::ec_classes( $settings );
			if ( isset( $settings['show_menu'] ) && 'yes' === $settings['show_menu'] ) {
				$classes[] = 'wpec-acc--with-store-menu';
			}
			return $classes;
		}

		/**
		 * Draws the dashboard.
		 *
		 * @param WP_EasyCart_Elementor_Account_Page $page    Account page.
		 * @param array                              $context Render context.
		 * @return bool
		 */
		public function ec_render_body( $page, $context ) {
			WP_EasyCart_Elementor_Account::print_account_top( $context );
			$page->display_dashboard_page();
			return true;
		}
	}

endif;
