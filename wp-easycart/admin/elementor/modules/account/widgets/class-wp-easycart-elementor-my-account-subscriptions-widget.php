<?php
/**
 * Subscriptions ( 6.0.2 ): the customer's subscriptions; "View" opens one on this same page, where the customer changes
 * the card, the plan or cancels ( as the store allows ).
 *
 * Only on stores whose customers manage Stripe subscriptions in their account.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_My_Account_Subscriptions_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Account_Widget' ) ) :

	/**
	 * Subscriptions widget.
	 */
	class WP_EasyCart_Elementor_My_Account_Subscriptions_Widget extends WP_EasyCart_Elementor_Account_Widget {

		/**
		 * Name.
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_my_account_subscriptions';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return esc_html__( 'Subscriptions', 'wp-easycart' );
		}

		/**
		 * Icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-sync';
		}

		/**
		 * Keywords.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array( 'subscriptions', 'subscription', 'membership', 'recurring', 'my account', 'woocommerce subscriptions' );
		}

		/**
		 * Scripts ( Stripe, for changing the card ).
		 *
		 * @return array
		 */
		public function get_script_depends() {
			return array_merge( parent::get_script_depends(), array( 'wpeasycart_stripe_js' ) );
		}

		/**
		 * Editor note on a store without account subscriptions.
		 *
		 * @return string
		 */
		protected function ec_empty_note() {
			return __( 'Your customers do not manage subscriptions in their account ( Stripe subscriptions and the account\'s subscriptions link are needed ), so they see nothing here.', 'wp-easycart' );
		}

		/**
		 * Content controls.
		 */
		protected function ec_content_controls() {
			$this->start_controls_section(
				'section_content',
				array(
					'label' => esc_html__( 'Subscriptions', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->ec_title_control();
			$this->add_control(
				'subscriptions_note',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => esc_html__( 'Shows when your store takes subscriptions with Stripe and lets customers manage them in their account.', 'wp-easycart' ),
					'content_classes' => 'elementor-descriptor',
				)
			);
			$this->ec_visibility_control();
			$this->end_controls_section();
		}

		/**
		 * Style section of the subscription cards and pages.
		 */
		protected function ec_widget_style_controls() {
			$this->ec_subscription_style_controls();
		}

		/**
		 * Draws the list, or the subscription it opened.
		 *
		 * @param WP_EasyCart_Elementor_Account_Page $page    Account page.
		 * @param array                              $context Render context.
		 * @return bool
		 */
		public function ec_render_body( $page, $context ) {
			if ( ! WP_EasyCart_Elementor_Account::uses_subscriptions() ) {
				return false;
			}
			if ( ! $context['editor'] && 'subscription_details' === WP_EasyCart_Elementor_Account_Views::requested_view() ) {
				$page->wpec_display_subscription_details( $page->wpec_details_subscription() );
				return true;
			}
			$page->display_subscriptions_page();
			return true;
		}
	}

endif;
