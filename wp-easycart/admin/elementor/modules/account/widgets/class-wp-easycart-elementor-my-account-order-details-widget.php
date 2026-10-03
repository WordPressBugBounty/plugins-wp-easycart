<?php
/**
 * Order Details ( 6.0.2 ): one order, drawn by the store's order details page ( pay link and balance due, documents,
 * tracking, checkout answers, items, totals ).
 *
 * Shows the order a link opened ( ?ec_page=order_details&order_id=…, the signed-in customer's own order or a guest's with
 * its key ), otherwise the customer's latest order, or nothing.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_My_Account_Order_Details_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Account_Widget' ) ) :

	/**
	 * Order Details widget.
	 */
	class WP_EasyCart_Elementor_My_Account_Order_Details_Widget extends WP_EasyCart_Elementor_Account_Widget {

		/**
		 * Name.
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_my_account_order_details';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return esc_html__( 'Order Details', 'wp-easycart' );
		}

		/**
		 * Icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-purchase-summary';
		}

		/**
		 * Keywords.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array( 'order details', 'order', 'receipt', 'invoice', 'purchase summary', 'my account', 'woocommerce' );
		}

		/**
		 * A signed-in customer's orders ( the editor shows a sample one when the editing admin has none ). A visitor only
		 * ever gets the guest order its emailed link opens ( ec_accountpage matched it to the guest key ).
		 *
		 * @param array $settings Settings.
		 * @return bool
		 */
		protected function ec_is_customer_view( $settings ) {
			return WP_EasyCart_Elementor_Account::is_editor() || WP_EasyCart_Elementor_Account::signed_in();
		}

		/**
		 * Content controls.
		 */
		protected function ec_content_controls() {
			$this->start_controls_section(
				'section_content',
				array(
					'label' => esc_html__( 'Order details', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->add_control(
				'no_order',
				array(
					'label'       => esc_html__( 'When no order was opened', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'default'     => 'latest',
					'options'     => array(
						'latest'  => esc_html__( 'Show the customer\'s latest order', 'wp-easycart' ),
						'nothing' => esc_html__( 'Show nothing', 'wp-easycart' ),
					),
					'description' => esc_html__( 'Orders open here from "View order" links on this page and from order emails that point to it.', 'wp-easycart' ),
				)
			);
			$this->add_control(
				'show_back_link',
				array(
					'label'        => esc_html__( 'Show "Back to orders"', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'default'      => 'yes',
					'return_value' => 'yes',
				)
			);
			$this->ec_order_details_content_controls();
			$this->ec_visibility_control();
			$this->end_controls_section();
		}

		/**
		 * Style section of the order's items and information.
		 */
		protected function ec_widget_style_controls() {
			$this->ec_order_details_style_controls();
		}

		/**
		 * Draws the order.
		 *
		 * @param WP_EasyCart_Elementor_Account_Page $page    Account page.
		 * @param array                              $context Render context.
		 * @return bool
		 */
		public function ec_render_body( $page, $context ) {
			$settings = $context['settings'];
			$opened   = ( 'order_details' === WP_EasyCart_Elementor_Account_Views::requested_view() );
			$latest   = ! isset( $settings['no_order'] ) || 'nothing' !== $settings['no_order'];
			if ( $opened && ! $context['editor'] ) {
				$order = $page->wpec_details_order( false );
			} elseif ( $latest || $context['editor'] ) {
				$order = $context['customer'] ? $page->wpec_details_order( true ) : null;
				if ( ! $order ) {
					return false;
				}
			} else {
				return false;
			}
			if ( $context['customer'] && ( ! isset( $settings['show_back_link'] ) || 'yes' === $settings['show_back_link'] ) ) {
				WP_EasyCart_Elementor_Account_Views::order_details( $page, $order, $context['views'] );
			} else {
				$page->wpec_display_order_details( $order );
			}
			return true;
		}
	}

endif;
