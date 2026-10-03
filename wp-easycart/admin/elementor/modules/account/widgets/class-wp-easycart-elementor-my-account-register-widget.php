<?php
/**
 * Registration Form ( 6.0.2 ): the store's create-an-account form.
 *
 * Shown to visitors who are not signed in. Everything the store asks for comes along ( billing address, notes, terms,
 * newsletter box, reCAPTCHA, password rules through wpeasycart_register_js_function ), and errors come back to this page.
 * The sign-in form EasyCart prints beside it is left out unless the merchant wants it ( a Customer Login widget on the same
 * page would otherwise share its field ids ).
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_My_Account_Register_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Account_Widget' ) ) :

	/**
	 * Registration Form widget.
	 */
	class WP_EasyCart_Elementor_My_Account_Register_Widget extends WP_EasyCart_Elementor_Account_Widget {

		/**
		 * Name.
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_my_account_register';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return esc_html__( 'Registration Form', 'wp-easycart' );
		}

		/**
		 * Icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-form-horizontal';
		}

		/**
		 * Keywords.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array( 'register', 'registration', 'sign up', 'create account', 'account', 'my account', 'customer', 'woocommerce' );
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
			return '[ec_account_register]';
		}

		/**
		 * Content controls.
		 */
		protected function ec_content_controls() {
			$this->start_controls_section(
				'section_content',
				array(
					'label' => esc_html__( 'Registration form', 'wp-easycart' ),
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
				'register_settings_note',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					/* translators: %s: link to the settings page. */
					'raw'             => sprintf( esc_html__( 'The fields the form asks for (address, notes, terms, newsletter) follow %s.', 'wp-easycart' ), '<a href="' . esc_url( admin_url( 'admin.php?page=wp-easycart-settings&subpage=account' ) ) . '" target="_blank">' . esc_html__( 'Settings › Accounts', 'wp-easycart' ) . '</a>' ),
					'content_classes' => 'elementor-descriptor',
				)
			);
			$this->ec_visibility_control();
			$this->end_controls_section();
		}

		/**
		 * Wording this widget can replace ( Content › Texts ): the form's title and button, and the sign-in column's when
		 * it shows.
		 *
		 * @return array
		 */
		protected function ec_text_fields() {
			$fields = $this->ec_register_text_fields();
			foreach ( $this->ec_login_text_fields( false ) as $id => $field ) {
				if ( in_array( $id, array( 'text_login_title', 'text_login_email_label', 'text_login_password_label', 'text_login_button', 'text_login_forgot' ), true ) ) {
					$field[4]      = array( 'show_login_form' => 'yes' );
					$fields[ $id ] = $field;
				}
			}
			return $fields;
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
			WP_EasyCart_Elementor_Account_Views::register( $page );
			return true;
		}
	}

endif;
