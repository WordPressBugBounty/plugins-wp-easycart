<?php
/**
 * Order History ( 6.0.2 ): the customer's orders. "View order" opens the order on this same page ( with a link back ),
 * unless an Order Details widget on the page shows it.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_My_Account_Orders_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Account_Widget' ) ) :

	/**
	 * Order History widget.
	 */
	class WP_EasyCart_Elementor_My_Account_Orders_Widget extends WP_EasyCart_Elementor_Account_Widget {

		/**
		 * Name.
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_my_account_orders';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return esc_html__( 'Order History', 'wp-easycart' );
		}

		/**
		 * Icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-post-list';
		}

		/**
		 * Keywords.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array( 'orders', 'order history', 'my orders', 'purchases', 'account', 'my account', 'woocommerce' );
		}

		/**
		 * Content controls.
		 */
		protected function ec_content_controls() {
			$this->start_controls_section(
				'section_content',
				array(
					'label' => esc_html__( 'Orders', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->ec_title_control();
			$this->add_control(
				'orders_note',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => esc_html__( '"View order" opens the order here, with a link back to the list. Add the Order Details widget to this page to show orders somewhere else on it.', 'wp-easycart' ),
					'content_classes' => 'elementor-descriptor',
				)
			);
			$this->ec_order_column_controls();
			$this->add_control(
				'order_page_heading',
				array(
					'label'     => esc_html__( 'An order opened here', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->ec_order_details_content_controls();
			$this->ec_visibility_control();
			$this->end_controls_section();
		}

		/**
		 * Style sections of the order list ( its colours were read by nothing before round 11 ) and of an order opened here.
		 */
		protected function ec_widget_style_controls() {
			$this->ec_order_list_style_controls();
			$this->ec_order_details_style_controls();
		}

		/**
		 * Whether this request opens an order here.
		 *
		 * @return bool
		 */
		private function shows_details() {
			return 'order_details' === WP_EasyCart_Elementor_Account_Views::requested_view() && ! WP_EasyCart_Elementor_Account::page_has( 'wp_easycart_my_account_order_details' );
		}

		/**
		 * Draws the list, or the order it opened.
		 *
		 * @param WP_EasyCart_Elementor_Account_Page $page    Account page.
		 * @param array                              $context Render context.
		 * @return bool
		 */
		public function ec_render_body( $page, $context ) {
			if ( ! $context['editor'] && $this->shows_details() ) {
				WP_EasyCart_Elementor_Account_Views::order_details( $page, $page->wpec_details_order( false ), $context['views'] );
				return true;
			}
			$page->display_orders_page();
			return true;
		}
	}

endif;
