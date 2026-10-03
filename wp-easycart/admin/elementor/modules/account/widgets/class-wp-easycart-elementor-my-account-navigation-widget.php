<?php
/**
 * Account Navigation ( 6.0.2 ): the account menu on its own.
 *
 * For custom layouts ( a menu in a sidebar column beside a My Account widget whose menu is hidden, or a menu in a header ).
 * Each link opens its page on this page when an account widget here shows it, otherwise on the store's account page.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_My_Account_Navigation_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Account_Widget' ) ) :

	/**
	 * Account Navigation widget.
	 */
	class WP_EasyCart_Elementor_My_Account_Navigation_Widget extends WP_EasyCart_Elementor_Account_Widget {

		/**
		 * Name.
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_my_account_navigation';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return esc_html__( 'Account Navigation', 'wp-easycart' );
		}

		/**
		 * Icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-nav-menu';
		}

		/**
		 * Keywords.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array( 'account menu', 'account navigation', 'my account', 'menu', 'tabs', 'woocommerce' );
		}

		/**
		 * Style sections: the menu, the box and the accent colour ( focus ring, hover and current item ).
		 *
		 * @return array
		 */
		protected function ec_style_parts() {
			return array( 'box', 'accent' );
		}

		/**
		 * No customer data ( no sample customer in the editor ).
		 *
		 * @param array $settings Settings.
		 * @return bool
		 */
		protected function ec_is_customer_view( $settings ) {
			return false;
		}

		/**
		 * No messages, no forms.
		 *
		 * @param array $settings Settings.
		 * @return array
		 */
		protected function ec_render_args( $settings ) {
			return array(
				'messages'   => false,
				'marker'     => array(),
				'needs_page' => false,
			);
		}

		/**
		 * Content controls.
		 */
		protected function ec_content_controls() {
			$this->start_controls_section(
				'section_content',
				array(
					'label' => esc_html__( 'Account menu', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->add_control(
				'orientation',
				array(
					'label'   => esc_html__( 'Layout', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => 'vertical',
					'options' => array(
						'vertical'   => esc_html__( 'List', 'wp-easycart' ),
						'horizontal' => esc_html__( 'Row', 'wp-easycart' ),
					),
				)
			);
			$this->add_control(
				'menu_dropdown',
				array(
					'label'        => esc_html__( 'Menu as a dropdown on phones', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'default'      => 'yes',
					'return_value' => 'yes',
				)
			);
			$this->add_control(
				'menu_items_heading',
				array(
					'label'     => esc_html__( 'Pages in the account', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->ec_menu_item_controls();
			$this->ec_visibility_control();
			$this->end_controls_section();
		}

		/**
		 * Menu style.
		 */
		protected function ec_widget_style_controls() {
			$this->ec_menu_style_controls( false );
		}

		/**
		 * The current page's item, when an account widget on this page shows it.
		 *
		 * @return string
		 */
		private function active_item() {
			$view  = WP_EasyCart_Elementor_Account_Views::requested_view();
			$views = WP_EasyCart_Elementor_Account::page_views();
			$all   = in_array( 'all', $views, true );
			if ( '' === $view || 'login' === $view ) {
				return ( $all || in_array( 'dashboard', $views, true ) ) ? 'dashboard' : '';
			}
			if ( ! $all && ! in_array( $view, $views, true ) ) {
				return '';
			}
			return self::menu_key_for( $view );
		}

		/**
		 * Draws the menu.
		 *
		 * @param WP_EasyCart_Elementor_Account_Page $page    Account page.
		 * @param array                              $context Render context.
		 * @return bool
		 */
		public function ec_render_body( $page, $context ) {
			$settings = $context['settings'];
			$items    = self::menu_links( $settings, array(), $this->active_item() );
			if ( empty( $items ) ) {
				return false;
			}
			self::print_menu( $items, ( isset( $settings['orientation'] ) && 'horizontal' === $settings['orientation'] ) ? 'horizontal' : 'vertical', isset( $settings['menu_dropdown'] ) && 'yes' === $settings['menu_dropdown'] );
			return true;
		}
	}

endif;
