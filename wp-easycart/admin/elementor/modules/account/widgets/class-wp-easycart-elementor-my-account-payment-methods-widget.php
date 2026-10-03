<?php
/**
 * Payment Methods ( 6.0.2 ): the card on file for the customer's subscriptions, with a link to change it on each
 * subscription that allows it. Only on stores whose customers manage Stripe subscriptions in their account ( the store
 * keeps no other saved cards ).
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_My_Account_Payment_Methods_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Account_Widget' ) ) :

	/**
	 * Payment Methods widget.
	 */
	class WP_EasyCart_Elementor_My_Account_Payment_Methods_Widget extends WP_EasyCart_Elementor_Account_Widget {

		/**
		 * Name.
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_my_account_payment_methods';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return esc_html__( 'Payment Methods', 'wp-easycart' );
		}

		/**
		 * Icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-price-table'; /* round 11: eicon-credit-card is in no Elementor version ( a blank panel tile ) */
		}

		/**
		 * Keywords.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array( 'payment methods', 'saved cards', 'card', 'credit card', 'my account', 'woocommerce' );
		}

		/**
		 * Style sections.
		 *
		 * @return array
		 */
		protected function ec_style_parts() {
			return array( 'box', 'headings', 'text', 'buttons', 'links', 'accent' );
		}

		/**
		 * No secondary buttons here.
		 *
		 * @return bool
		 */
		protected function ec_has_secondary_buttons() {
			return false;
		}

		/**
		 * Style section of the card on file.
		 */
		protected function ec_widget_style_controls() {
			$this->ec_payment_style_controls();
		}

		/**
		 * Editor note on a store without account subscriptions.
		 *
		 * @return string
		 */
		protected function ec_empty_note() {
			return __( 'Your store keeps a card on file only for Stripe subscriptions that customers manage in their account, so customers see nothing here.', 'wp-easycart' );
		}

		/**
		 * Content controls.
		 */
		protected function ec_content_controls() {
			$this->start_controls_section(
				'section_content',
				array(
					'label' => esc_html__( 'Payment methods', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->ec_title_control();
			$this->add_control(
				'payment_note',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => esc_html__( 'Shows the card customers pay their subscriptions with, when your store takes subscriptions with Stripe.', 'wp-easycart' ),
					'content_classes' => 'elementor-descriptor',
				)
			);
			$this->add_control(
				'show_change_links',
				array(
					'label'        => esc_html__( 'List the subscriptions it pays for', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'default'      => 'yes',
					'return_value' => 'yes',
					'description'  => esc_html__( 'Each with a link to change the card it is paid with.', 'wp-easycart' ),
				)
			);
			$this->add_control(
				'change_link_style',
				array(
					'label'     => esc_html__( '"Change payment method" shows as', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'default'   => 'link',
					'options'   => array(
						'link'   => esc_html__( 'A link', 'wp-easycart' ),
						'button' => esc_html__( 'A button', 'wp-easycart' ),
					),
					'condition' => array( 'show_change_links' => 'yes' ),
				)
			);
			$this->ec_visibility_control();
			$this->end_controls_section();
		}

		/**
		 * Draws the card on file.
		 *
		 * @param WP_EasyCart_Elementor_Account_Page $page    Account page.
		 * @param array                              $context Render context.
		 * @return bool
		 */
		public function ec_render_body( $page, $context ) {
			if ( ! WP_EasyCart_Elementor_Account::uses_subscriptions() ) {
				return false;
			}
			$settings = $context['settings'];
			WP_EasyCart_Elementor_Account_Views::payment_methods(
				$page,
				! isset( $settings['show_title'] ) || 'yes' === $settings['show_title'],
				array(
					'change_links' => ! isset( $settings['show_change_links'] ) || 'yes' === $settings['show_change_links'],
					'change_style' => ( isset( $settings['change_link_style'] ) && 'button' === $settings['change_link_style'] ) ? 'button' : 'link',
				)
			);
			return true;
		}
	}

endif;
