<?php
/**
 * Customer Login ( 6.0.2 ): the store's sign-in form, with the new customer box beside it.
 *
 * Shown to visitors who are not signed in. A wrong password comes back to this page with its message; after signing in the
 * customer goes to their account ( this page when it shows the account, else the store's account page ), this page, or a
 * page the merchant picks. reCAPTCHA, account activation ( resend link ) and the sign-in hooks work as on the account page.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_My_Account_Login_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Account_Widget' ) ) :

	/**
	 * Customer Login widget.
	 */
	class WP_EasyCart_Elementor_My_Account_Login_Widget extends WP_EasyCart_Elementor_Account_Widget {

		/**
		 * Name.
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_my_account_login';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return esc_html__( 'Customer Login', 'wp-easycart' );
		}

		/**
		 * Icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-lock-user';
		}

		/**
		 * Keywords.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array( 'login', 'log in', 'sign in', 'account', 'my account', 'customer', 'woocommerce' );
		}

		/**
		 * Scripts ( reCAPTCHA where the store uses it ).
		 *
		 * @return array
		 */
		public function get_script_depends() {
			return array_merge( parent::get_script_depends(), array( 'wpeasycart_google_recaptcha_js' ) );
		}

		/**
		 * Post content.
		 *
		 * @return string
		 */
		protected function plain_content_shortcode() {
			return '[ec_account_login]';
		}

		/**
		 * Content controls.
		 */
		protected function ec_content_controls() {
			$this->start_controls_section(
				'section_content',
				array(
					'label' => esc_html__( 'Sign-in form', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->ec_title_control( esc_html__( 'Show the title', 'wp-easycart' ) );
			$this->add_control(
				'show_forgot',
				array(
					'label'        => esc_html__( 'Show "Forgot your password?"', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'default'      => 'yes',
					'return_value' => 'yes',
				)
			);
			$this->add_control(
				'show_register_box',
				array(
					'label'        => esc_html__( 'Show the new customer box', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'default'      => 'yes',
					'return_value' => 'yes',
					'description'  => esc_html__( 'A box beside the form with a button to create an account.', 'wp-easycart' ),
				)
			);
			$this->ec_login_field_controls( true );
			$this->ec_login_redirect_controls();
			$this->ec_visibility_control();
			$this->end_controls_section();
		}

		/**
		 * Wording this widget can replace ( Content › Texts ).
		 *
		 * @return array
		 */
		protected function ec_text_fields() {
			return $this->ec_login_text_fields( true );
		}

		/**
		 * Placeholders: setting => field id.
		 *
		 * @return array
		 */
		protected function ec_placeholder_fields() {
			return array(
				'login_email_placeholder'    => 'ec_account_login_email',
				'login_password_placeholder' => 'ec_account_login_password',
			);
		}

		/**
		 * The sign-in box and the new customer box.
		 */
		protected function ec_widget_style_controls() {
			$this->ec_login_boxes_style_controls();
		}

		/**
		 * Wrapper classes.
		 *
		 * @param array $settings Settings.
		 * @return array
		 */
		protected function ec_classes( $settings ) {
			$classes = parent::ec_classes( $settings );
			if ( isset( $settings['show_forgot'] ) && 'yes' !== $settings['show_forgot'] ) {
				$classes[] = 'wpec-acc--no-forgot';
			}
			if ( isset( $settings['show_register_box'] ) && 'yes' !== $settings['show_register_box'] ) {
				$classes[] = 'wpec-acc--no-register-box';
			}
			return $classes;
		}

		/**
		 * The new customer box goes when it is off.
		 *
		 * @param array $settings Settings.
		 * @return array
		 */
		protected function ec_strip( $settings ) {
			$rules = parent::ec_strip( $settings );
			if ( isset( $settings['show_register_box'] ) && 'yes' !== $settings['show_register_box'] ) {
				$rules[] = array( 'has' => array( 'ec_account_right', 'ec_account_login' ) );
			}
			return $rules;
		}

		/**
		 * Draws the form, or for a signed-in customer ( Show to: everyone ) who is signed in.
		 *
		 * @param WP_EasyCart_Elementor_Account_Page $page    Account page.
		 * @param array                              $context Render context.
		 * @return bool
		 */
		public function ec_render_body( $page, $context ) {
			if ( $context['signed_in'] && ! $context['editor'] ) {
				WP_EasyCart_Elementor_Account_Views::signed_in( $context['views'] );
				return true;
			}
			WP_EasyCart_Elementor_Account::print_account_top( $context );
			WP_EasyCart_Elementor_Account_Views::login( $page, $context['settings'] );
			return true;
		}
	}

endif;
