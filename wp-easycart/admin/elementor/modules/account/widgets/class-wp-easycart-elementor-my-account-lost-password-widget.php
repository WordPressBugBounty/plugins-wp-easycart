<?php
/**
 * Lost Password ( 6.0.2 ): asks for the account email, and draws the new password form when the emailed link opens.
 *
 * The reset email links to the page this widget is on ( the request is processed with this page's links ), so the link
 * always lands on a page that can set the new password. "We sent you a link" shows here too.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_My_Account_Lost_Password_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Account_Widget' ) ) :

	/**
	 * Lost Password widget.
	 */
	class WP_EasyCart_Elementor_My_Account_Lost_Password_Widget extends WP_EasyCart_Elementor_Account_Widget {

		/**
		 * Name.
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_my_account_lost_password';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return esc_html__( 'Lost Password', 'wp-easycart' );
		}

		/**
		 * Icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-lock';
		}

		/**
		 * Keywords.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array( 'lost password', 'forgot password', 'reset password', 'password', 'account', 'my account', 'woocommerce' );
		}

		/**
		 * Post content.
		 *
		 * @return string
		 */
		protected function plain_content_shortcode() {
			return '[ec_account_forgot]';
		}

		/**
		 * Content controls.
		 */
		protected function ec_content_controls() {
			$this->start_controls_section(
				'section_content',
				array(
					'label' => esc_html__( 'Lost password', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->ec_title_control();
			$this->add_control(
				'show_login_form',
				array(
					'label'        => esc_html__( 'Show a sign-in form beside it', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'default'      => '',
					'return_value' => 'yes',
					'description'  => esc_html__( 'Leave off when a Customer Login widget is on the same page.', 'wp-easycart' ),
				)
			);
			$this->add_control(
				'lost_password_note',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => esc_html__( 'The reset email links back to this page, where customers choose their new password.', 'wp-easycart' ),
					'content_classes' => 'elementor-descriptor',
				)
			);
			$this->ec_labels_control();
			$this->add_control(
				'forgot_email_placeholder',
				array(
					'label'       => esc_html__( 'Email placeholder', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => '',
					'description' => esc_html__( 'Shown inside the empty email field. Leave empty for none.', 'wp-easycart' ),
					'ai'          => array( 'active' => false ),
				)
			);
			$this->ec_visibility_control();
			$this->end_controls_section();
		}

		/**
		 * Wording this widget can replace ( Content › Texts ): both forms, and the sign-in column's when it shows.
		 *
		 * @return array
		 */
		protected function ec_text_fields() {
			$fields = $this->ec_lost_password_text_fields();
			foreach ( $this->ec_login_text_fields( false ) as $id => $field ) {
				if ( in_array( $id, array( 'text_login_title', 'text_login_email_label', 'text_login_password_label', 'text_login_button' ), true ) ) {
					$field[4]      = array( 'show_login_form' => 'yes' );
					$fields[ $id ] = $field;
				}
			}
			return $fields;
		}

		/**
		 * Placeholders: setting => field id.
		 *
		 * @return array
		 */
		protected function ec_placeholder_fields() {
			return array( 'forgot_email_placeholder' => 'ec_account_forgot_password_email' );
		}

		/**
		 * The sign-in column goes unless asked for.
		 *
		 * @param array $settings Settings.
		 * @return array
		 */
		protected function ec_strip( $settings ) {
			$rules = parent::ec_strip( $settings );
			if ( ! isset( $settings['show_login_form'] ) || 'yes' !== $settings['show_login_form'] ) {
				$rules[] = array( 'has' => 'ec_cart_right' );
			}
			return $rules;
		}

		/**
		 * Wrapper classes.
		 *
		 * @param array $settings Settings.
		 * @return array
		 */
		protected function ec_classes( $settings ) {
			$classes = parent::ec_classes( $settings );
			if ( ! isset( $settings['show_login_form'] ) || 'yes' !== $settings['show_login_form'] ) {
				$classes[] = 'wpec-acc--single';
			}
			return $classes;
		}

		/**
		 * The reset link always draws ( a customer may still be signed in ); the request form keeps its routing to this
		 * page for the "we sent a link" message.
		 *
		 * @param array $settings Settings.
		 * @return array
		 */
		protected function ec_render_args( $settings ) {
			$reset = WP_EasyCart_Elementor_Account_Views::is_reset_request();
			return array(
				'marker' => $reset ? array( 'reset_password', 'forgot_password' ) : array( 'forgot_password', 'reset_password', 'login' ),
				'force'  => $reset,
			);
		}

		/**
		 * Draws the request form or the new password form.
		 *
		 * @param WP_EasyCart_Elementor_Account_Page $page    Account page.
		 * @param array                              $context Render context.
		 * @return bool
		 */
		public function ec_render_body( $page, $context ) {
			$reset = WP_EasyCart_Elementor_Account_Views::is_reset_request();
			if ( $context['signed_in'] && ! $context['editor'] && ! $reset ) {
				WP_EasyCart_Elementor_Account_Views::signed_in( $context['views'] );
				return true;
			}
			WP_EasyCart_Elementor_Account_Views::lost_password( $page, $reset );
			return true;
		}
	}

endif;
