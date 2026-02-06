<?php
/**
 * WP EasyCart Account Forms Widget for Elementor
 *
 * @category Class
 * @package  Wp_Easycart_Elementor_Account_Forms_Widget
 * @author   WP EasyCart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Elementor\Controls_Manager;
use Elementor\Scheme_Color;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use ELementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Image_Size;
use Elementor\Utils;
use Elementor\Wp_Easycart_Controls_Manager;

/**
 * WP EasyCart Account Forms Widget for Elementor
 *
 * @category Class
 * @package  Wp_Easycart_Elementor_Account_Forms_Widget
 * @author   WP EasyCart
 */
class Wp_Easycart_Elementor_Account_Forms_Widget extends \Elementor\Widget_Base {

	/**
	 * Get store widget name.
	 */
	public function get_name() {
		return 'wp_easycart_account_forms';
	}

	/**
	 * Get store widget title.
	 */
	public function get_title() {
		return esc_attr__( 'WP EasyCart Account Forms', 'wp-easycart' );
	}

	/**
	 * Get store widget icon.
	 */
	public function get_icon() {
		return 'eicon-ehp-forms';
	}

	/**
	 * Get store widget categories.
	 */
	public function get_categories() {
		return array( 'wp-easycart-elements' );
	}

	/**
	 * Get store widget keywords.
	 */
	public function get_keywords() {
		return array( 'shop', 'wp-easycart' );
	}

	/**
	 * Enqueue store widget scripts and styles.
	 */
	public function get_script_depends() {
		$scripts = array( 'isotope-pkgd', 'jquery-hoverIntent' );
		if ( ( isset( $_REQUEST['action'] ) && 'elementor' == $_REQUEST['action'] ) || isset( $_REQUEST['elementor-preview'] ) ) {
			$scripts[] = 'wpeasycart_js';
		}
		return $scripts;
	}

	/**
	 * Setup store widget controls.
	 */
	protected function _register_controls() {
		$this->start_controls_section(
			'section_content_form_fields',
			array(
				'label' => esc_html__( 'Form Options', 'wp-easycart' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);
		
		$this->add_control(
			'form_type',
			array(
				'label'   => esc_html__( 'Account Form Type', 'wp-easycart' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'login' => esc_html__( 'Login', 'wp-easycart' ),
					'register' => esc_html__( 'Register', 'wp-easycart' ),
					'forgot-password' => esc_html__( 'Forgot Password', 'wp-easycart' ),
					'billing' => esc_html__( 'Billing Address', 'wp-easycart' ),
					'shipping' => esc_html__( 'Shipping Address', 'wp-easycart' ),
					'personal' => esc_html__( 'Email & Name', 'wp-easycart' ),
					'password' => esc_html__( 'Password', 'wp-easycart' ),
					'connect-order' => esc_html__( 'Connect Order', 'wp-easycart' ),
				),
				'default'      => 'login',
				'toggle'       => false,
				'prefix_class' => 'wp-easycart-label-type-',
				'separator'    => 'after',
				'render_type'  => 'template',
			)
		);

		$this->add_control(
			'first_name_label',
			array(
				'label'   => esc_html__( 'First Name Label', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => wp_easycart_language( )->get_text( 'cart_contact_information', 'cart_contact_information_first_name' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'form_type' => array( 'register', 'personal' ),
				),
			)
		);
		
		$this->add_control(
			'first_name_error',
			array(
				'label'   => esc_html__( 'First Name Error Message', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ) . ' ' . wp_easycart_language( )->get_text( 'cart_contact_information', 'cart_contact_information_first_name' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'form_type' => array( 'register', 'personal' ),
				),
			)
		);

		$this->add_control(
			'last_name_label',
			array(
				'label'   => esc_html__( 'Last Name Label', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => wp_easycart_language( )->get_text( 'cart_contact_information', 'account_billing_information_last_name' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'form_type' => array( 'register', 'personal' ),
				),
			)
		);
		
		$this->add_control(
			'last_name_error',
			array(
				'label'   => esc_html__( 'Last Name Error Message', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ) . ' ' . wp_easycart_language( )->get_text( 'cart_contact_information', 'cart_contact_information_last_name' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'form_type' => array( 'register', 'personal' ),
				),
			)
		);

		$this->add_control(
			'email_label',
			array(
				'label'   => esc_html__( 'Email Label', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => wp_easycart_language( )->get_text( 'account_forgot_password', 'account_forgot_password_email_label' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'form_type' => array( 'login', 'register', 'forgot-password', 'personal' ),
				),
			)
		);
		
		$this->add_control(
			'email_error',
			array(
				'label'   => esc_html__( 'Email Error Message', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ) . ' ' . wp_easycart_language( )->get_text( 'account_forgot_password', 'account_forgot_password_email_label' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'form_type' => array( 'login', 'register', 'forgot-password', 'personal' ),
				),
			)
		);

		$this->add_control(
			'retype_email_label',
			array(
				'label'   => esc_html__( 'Retype Email Label', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => wp_easycart_language( )->get_text( 'cart_contact_information', 'cart_contact_information_retype_email' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'form_type' => array( 'register', 'personal' ),
					'show_retype_email' => 'yes',
				),
			)
		);
		
		$this->add_control(
			'retype_email_error',
			array(
				'label'   => esc_html__( 'Retype Email Error Message', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_emails_do_not_match' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'form_type' => array( 'register', 'personal' ),
					'show_retype_email' => 'yes',
				),
			)
		);

		$this->add_control(
			'extra_email_label',
			array(
				'label'   => esc_html__( 'Extra Email Label', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => wp_easycart_language( )->get_text( 'account_personal_information', 'account_personal_information_email_other' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'enable_extra_email' => 'yes',
					'form_type' => array( 'personal' ),
				),
			)
		);

		$this->add_control(
			'password_label',
			array(
				'label'   => esc_html__( 'Password Label', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => wp_easycart_language( )->get_text( 'account_login', 'account_login_password_label' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'form_type' => array( 'login', 'register' ),
				),
			)
		);

		$this->add_control(
			'password_error',
			array(
				'label'   => esc_html__( 'Password Error', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ) . ' ' . wp_easycart_language( )->get_text( 'cart_login', 'cart_login_password_label' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'form_type' => array( 'login', 'register' ),
				),
			)
		);

		$this->add_control(
			'current_password_label',
			array(
				'label'   => esc_html__( 'Current Password Label', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => wp_easycart_language( )->get_text( 'account_password', 'account_password_current_password' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'form_type' => array( 'password' ),
				),
			)
		);

		$this->add_control(
			'current_password_error',
			array(
				'label'   => esc_html__( 'Current Password Error', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ) . ' ' . wp_easycart_language( )->get_text( 'account_password', 'account_password_current_password' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'form_type' => array( 'password' ),
				),
			)
		);

		$this->add_control(
			'new_password_label',
			array(
				'label'   => esc_html__( 'New Password Label', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => wp_easycart_language( )->get_text( 'account_password', 'account_password_new_password' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'form_type' => array( 'password' ),
				),
			)
		);

		$this->add_control(
			'new_password_error',
			array(
				'label'   => esc_html__( 'New Password Error', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_length_error' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'form_type' => array( 'password' ),
				),
			)
		);

		$this->add_control(
			'retype_password_label',
			array(
				'label'   => esc_html__( 'Retype Password Label', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => wp_easycart_language( )->get_text( 'cart_contact_information', 'cart_contact_information_retype_password' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'form_type' => array( 'register', 'password' ),
				),
			)
		);

		$this->add_control(
			'retype_password_error',
			array(
				'label'   => esc_html__( 'Retype Password Error', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_passwords_do_not_match' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'form_type' => array( 'register', 'password' ),
				),
			)
		);

		$this->add_control(
			'address_first_name_label',
			array(
				'label'   => esc_html__( 'First Name (Address) Label', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => wp_easycart_language( )->get_text( 'account_billing_information', 'account_billing_information_first_name' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'form_type' => array( 'register', 'billing', 'shipping' ),
				),
			)
		);
		
		$this->add_control(
			'address_first_name_error',
			array(
				'label'   => esc_html__( 'First Name (Address) Error Message', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ) . ' ' . wp_easycart_language( )->get_text( 'account_billing_information', 'account_billing_information_first_name' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'form_type' => array( 'register', 'billing', 'shipping' ),
				),
			)
		);

		$this->add_control(
			'address_last_name_label',
			array(
				'label'   => esc_html__( 'Last Name (Address) Label', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => wp_easycart_language( )->get_text( 'account_billing_information', 'account_billing_information_last_name' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'form_type' => array( 'register', 'billing', 'shipping' ),
				),
			)
		);
		
		$this->add_control(
			'address_last_name_error',
			array(
				'label'   => esc_html__( 'Last Name (Address) Error Message', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ) . ' ' . wp_easycart_language( )->get_text( 'account_billing_information', 'account_billing_information_last_name' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'form_type' => array( 'register', 'billing', 'shipping' ),
				),
			)
		);

		$this->add_control(
			'company_name_label',
			array(
				'label'   => esc_html__( 'Company Name Label', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => wp_easycart_language( )->get_text( 'cart_billing_information', 'cart_billing_information_company_name' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'form_type' => array( 'register', 'billing', 'shipping' ),
				),
			)
		);
		
		$this->add_control(
			'company_name_error',
			array(
				'label'   => esc_html__( 'Company Name Error Message', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ) . ' ' . wp_easycart_language( )->get_text( 'cart_billing_information', 'cart_billing_information_company_name' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'form_type' => array( 'register', 'billing', 'shipping' ),
				),
			)
		);

		if ( get_option( 'ec_option_collect_vat_registration_number' ) ) {
			$this->add_control(
				'billing_vat_label',
				array(
					'label'   => esc_html__( 'VAT Registration Label', 'wp-easycart' ),
					'type'    => Controls_Manager::TEXT,
					'default' => wp_easycart_language( )->get_text( 'cart_billing_information', 'cart_billing_information_vat_registration_number' ),
					'dynamic' => array(
						'active' => true,
					),
					'condition'   => array(
						'form_type' => array( 'register', 'personal' ),
					),
				)
			);
		}

		$this->add_control(
			'address_label',
			array(
				'label'   => esc_html__( 'Address Label', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => wp_easycart_language( )->get_text( 'account_billing_information', 'account_billing_information_address' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'form_type' => array( 'register', 'billing', 'shipping' ),
				),
			)
		);

		$this->add_control(
			'address_error',
			array(
				'label'   => esc_html__( 'Address Error Message', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ) . ' ' . wp_easycart_language( )->get_text( 'account_billing_information', 'account_billing_information_address' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'form_type' => array( 'register', 'billing', 'shipping' ),
				),
			)
		);

		$this->add_control(
			'address2_label',
			array(
				'label'   => esc_html__( 'Address Line 2 Label', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => esc_html__( 'Apartment # or STE', 'wp-easycart' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'form_type' => array( 'register', 'billing', 'shipping' ),
				),
			)
		);

		$this->add_control(
			'city_label',
			array(
				'label'   => esc_html__( 'City Label', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => wp_easycart_language( )->get_text( 'account_billing_information', 'account_billing_information_city' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'form_type' => array( 'register', 'billing', 'shipping' ),
				),
			)
		);

		$this->add_control(
			'city_error',
			array(
				'label'   => esc_html__( 'City Error Message', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ) . ' ' . wp_easycart_language( )->get_text( 'account_billing_information', 'account_billing_information_city' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'form_type' => array( 'register', 'billing', 'shipping' ),
				),
			)
		);

		$this->add_control(
			'state_label',
			array(
				'label'   => esc_html__( 'State Label', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => wp_easycart_language( )->get_text( 'account_billing_information', 'account_billing_information_state' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'form_type' => array( 'register', 'billing', 'shipping' ),
				),
			)
		);

		$this->add_control(
			'state_error',
			array(
				'label'   => esc_html__( 'State Error Message', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ) . ' ' . wp_easycart_language( )->get_text( 'account_billing_information', 'account_billing_information_state' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'form_type' => array( 'register', 'billing', 'shipping' ),
				),
			)
		);

		$this->add_control(
			'zip_label',
			array(
				'label'   => esc_html__( 'Zip/Postal Code Label', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => wp_easycart_language( )->get_text( 'account_billing_information', 'account_billing_information_zip' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'form_type' => array( 'register', 'billing', 'shipping' ),
				),
			)
		);

		$this->add_control(
			'zip_error',
			array(
				'label'   => esc_html__( 'Zip/Postal Code Error Message', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ) . ' ' . wp_easycart_language( )->get_text( 'account_billing_information', 'account_billing_information_zip' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'form_type' => array( 'register', 'billing', 'shipping' ),
				),
			)
		);

		$this->add_control(
			'country_label',
			array(
				'label'   => esc_html__( 'Country Label', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => wp_easycart_language( )->get_text( 'account_billing_information', 'account_billing_information_country' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'form_type' => array( 'register', 'billing', 'shipping' ),
				),
			)
		);

		$this->add_control(
			'country_error',
			array(
				'label'   => esc_html__( 'Country Error Message', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ) . ' ' . wp_easycart_language( )->get_text( 'account_billing_information', 'account_billing_information_country' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'form_type' => array( 'register', 'billing', 'shipping' ),
				),
			)
		);

		$this->add_control(
			'phone_label',
			array(
				'label'   => esc_html__( 'Phone Label', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => wp_easycart_language( )->get_text( 'account_billing_information', 'account_billing_information_phone' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'form_type' => array( 'register', 'billing', 'shipping' ),
				),
			)
		);

		$this->add_control(
			'phone_error',
			array(
				'label'   => esc_html__( 'Phone Error Message', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ) . ' ' . wp_easycart_language( )->get_text( 'account_billing_information', 'account_billing_information_phone' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'form_type' => array( 'register', 'billing', 'shipping' ),
				),
			)
		);

		$this->add_control(
			'button_text_login',
			array(
				'label'   => esc_html__( 'Button Text', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => wp_easycart_language( )->get_text( 'account_login', 'account_login_button' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'form_type' => array( 'login' ),
				),
			)
		);

		$this->add_control(
			'button_text_register',
			array(
				'label'   => esc_html__( 'Button Text', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => wp_easycart_language( )->get_text( 'account_register', 'account_register_button' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'form_type' => array( 'register' ),
				),
			)
		);

		$this->add_control(
			'button_text_forgot_password',
			array(
				'label'   => esc_html__( 'Button Text', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => wp_easycart_language( )->get_text( 'account_forgot_password', 'account_forgot_password_button' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'form_type' => array( 'forgot-password' ),
				),
			)
		);

		$this->add_control(
			'button_text_billing',
			array(
				'label'   => esc_html__( 'Button Text', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => wp_easycart_language( )->get_text( 'account_billing_information', 'account_billing_information_update_button' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'form_type' => array( 'billing' ),
				),
			)
		);

		$this->add_control(
			'button_text_shipping',
			array(
				'label'   => esc_html__( 'Button Text', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => wp_easycart_language( )->get_text( 'account_shipping_information', 'account_shipping_information_update_button' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'form_type' => array( 'shipping' ),
				),
			)
		);

		$this->add_control(
			'button_text_personal',
			array(
				'label'   => esc_html__( 'Button Text', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => wp_easycart_language( )->get_text( 'account_personal_information', 'account_personal_information_update_button' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'form_type' => array( 'personal' ),
				),
			)
		);

		$this->add_control(
			'button_text_password',
			array(
				'label'   => esc_html__( 'Button Text', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => wp_easycart_language( )->get_text( 'account_password', 'account_password_update_button' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'form_type' => array( 'password' ),
				),
			)
		);

		$this->add_control(
			'connect_order_label',
			array(
				'label'   => esc_html__( 'Connect Order Input Label', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => esc_html__( 'Your Order Number', 'wp-easycart' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'form_type' => array( 'connect-order' ),
				),
			)
		);

		$this->add_control(
			'connect_order_error',
			array(
				'label'   => esc_html__( 'Connect Order Error Message', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => esc_html__( 'Your past order number is required', 'wp-easycart' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'form_type' => array( 'connect-order' ),
				),
			)
		);

		$this->add_control(
			'button_text_connect_order',
			array(
				'label'   => esc_html__( 'Connect Order Button', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => esc_html__( 'Find Your Order', 'wp-easycart' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'form_type' => array( 'connect-order' ),
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_content_additional_options',
			array(
				'label' => esc_html__( 'Additional Options', 'wp-easycart' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);
		
		$this->add_control(
			'label_type',
			array(
				'label'   => esc_html__( 'Label Position', 'wp-easycart' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'above' => esc_html__( 'Above Field', 'wp-easycart' ),
					'floating' => esc_html__( 'Floating Labels', 'wp-easycart' ),
					'inside' => esc_html__( 'Inside Field', 'wp-easycart' ),
				),
				'default'      => 'above',
				'toggle'       => false,
				'prefix_class' => 'wp-easycart-label-type-',
				'separator'    => 'after',
				'render_type'  => 'template',
			)
		);

		$this->add_control(
			'redirect_after_login',
			array(
				'label'        => esc_html__( 'Redirect After Login', 'wp-easycart' ),
				'type'         => Controls_Manager::SWITCHER,
				'separator'    => 'before',
				'label_on'     => esc_html__( 'Yes', 'wp-easycart' ),
				'label_off'    => esc_html__( 'No', 'wp-easycart' ),
				'return_value' => 'yes',
				'default'      => '',
				'condition'   => array(
					'form_type' => array( 'login' ),
				),
			)
		);

		$this->add_control(
			'redirect_url',
			array(
				'label'       => esc_html__( 'Redirect URL', 'wp-easycart' ),
				'type'        => Controls_Manager::URL,
				'placeholder' => 'https://your-site.com/my-account',
				'show_external' => true,
				'default'     => array(
					'url' => '',
				),
				'condition'   => array(
					'form_type' => array( 'login' ),
					'redirect_after_login' => 'yes',
				),
				'description' => esc_html__( 'Note: Leave blank to redirect to the current page.', 'wp-easycart' ),
			)
		);

		$this->add_control(
			'show_first_name',
			array(
				'label'        => esc_html__( 'Show and Require First Name', 'wp-easycart' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'wp-easycart' ),
				'label_off'    => esc_html__( 'No', 'wp-easycart' ),
				'return_value' => 'yes',
				'default'      => 'no',
				'condition'   => array(
					'form_type' => array( 'register' ),
				),
			)
		);

		$this->add_control(
			'show_last_name',
			array(
				'label'        => esc_html__( 'Show and Require Last Name', 'wp-easycart' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'wp-easycart' ),
				'label_off'    => esc_html__( 'No', 'wp-easycart' ),
				'return_value' => 'yes',
				'default'      => 'no',
				'condition'   => array(
					'form_type' => array( 'register' ),
				),
			)
		);

		$this->add_control(
			'show_retype_email',
			array(
				'label'        => esc_html__( 'Show and Require Retype Email', 'wp-easycart' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'wp-easycart' ),
				'label_off'    => esc_html__( 'No', 'wp-easycart' ),
				'return_value' => 'yes',
				'default'      => 'no',
				'condition'   => array(
					'form_type' => array( 'register' ),
				),
			)
		);

		$this->add_control(
			'show_retype_password',
			array(
				'label'        => esc_html__( 'Show and Require Retype Password', 'wp-easycart' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'wp-easycart' ),
				'label_off'    => esc_html__( 'No', 'wp-easycart' ),
				'return_value' => 'yes',
				'default'      => 'no',
				'condition'   => array(
					'form_type' => array( 'register' ),
				),
			)
		);

		$this->add_control(
			'require_billing',
			array(
				'label'        => esc_html__( 'Require Billing Address', 'wp-easycart' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'wp-easycart' ),
				'label_off'    => esc_html__( 'No', 'wp-easycart' ),
				'return_value' => 'yes',
				'default'      => 'no',
				'condition'   => array(
					'form_type' => array( 'register' ),
				),
			)
		);

		$this->add_control(
			'enable_extra_email',
			array(
				'label'        => esc_html__( 'Enable Extra Email', 'wp-easycart' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'wp-easycart' ),
				'label_off'    => esc_html__( 'No', 'wp-easycart' ),
				'return_value' => 'yes',
				'default'      => 'no',
				'condition'   => array(
					'form_type' => array( 'personal' ),
				),
			)
		);

		$this->add_control(
			'enable_notes',
			array(
				'label'        => esc_html__( 'Enable User Notes', 'wp-easycart' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'wp-easycart' ),
				'label_off'    => esc_html__( 'No', 'wp-easycart' ),
				'return_value' => 'yes',
				'default'      => 'no',
				'condition'   => array(
					'form_type' => array( 'register' ),
				),
			)
		);

		$this->add_control(
			'require_terms',
			array(
				'label'        => esc_html__( 'Enable Terms Agreement', 'wp-easycart' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'wp-easycart' ),
				'label_off'    => esc_html__( 'No', 'wp-easycart' ),
				'return_value' => 'yes',
				'default'      => 'no',
				'condition'   => array(
					'form_type' => array( 'register' ),
				),
			)
		);

		$this->add_control(
			'show_subscriber',
			array(
				'label'        => esc_html__( 'Enable Subscribe to Newsletter', 'wp-easycart' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'wp-easycart' ),
				'label_off'    => esc_html__( 'No', 'wp-easycart' ),
				'return_value' => 'yes',
				'default'      => 'no',
				'condition'   => array(
					'form_type' => array( 'register' ),
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_content_title',
			array(
				'label' => esc_html__( 'Billing Title', 'wp-easycart' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
				'condition'   => array(
					'form_type' => array( 'register' ),
					'require_billing' => 'yes',
				),
			)
		);

		$this->add_control(
			'billing_title_text',
			array(
				'label'       => esc_html__( 'Title', 'wp-easycart' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'Billing Address', 'wp-easycart' ),
				'placeholder' => esc_html__( 'Billing Address', 'wp-easycart' ),
				'dynamic'     => array(
					'active' => true,
				),
				'condition'   => array(
					'form_type' => array( 'register' ),
					'require_billing' => 'yes',
				),
			)
		);

		$this->add_control(
			'billing_title_tag',
			array(
				'label'   => esc_html__( 'HTML Tag', 'wp-easycart' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'h1'   => 'H1',
					'h2'   => 'H2',
					'h3'   => 'H3',
					'h4'   => 'H4',
					'h5'   => 'H5',
					'h6'   => 'H6',
					'div'  => 'div',
					'span' => 'span',
					'p'    => 'p',
				),
				'default'   => 'div',
				'condition' => array(
					'form_type' => array( 'register' ),
					'require_billing' => 'yes',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style_form_container',
			array(
				'label' => esc_html__( 'Form Styling', 'wp-easycart' ),
				'tab'   => Controls_Manager::TAB_STYLE,
				'condition'   => array(
					'form_type' => array( 'login', 'register', 'forgot-password', 'billing', 'shipping', 'personal', 'password' ),
				),
			)
		);

		$this->add_responsive_control(
			'row_gap',
			array(
				'label'      => esc_html__( 'Fields Gap', 'wp-easycart' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 60,
					),
				),
				'default' => array(
					'unit' => 'px',
					'size' => 20,
				),
				'selectors'  => array(
					'{{WRAPPER}} .ec_cart_input_row' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style_labels',
			array(
				'label' => esc_html__( 'Labels', 'wp-easycart' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'label_color',
			array(
				'label'     => esc_html__( 'Color', 'wp-easycart' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .ec_cart_input_row label' => 'color: {{VALUE}};',
					'{{WRAPPER}} .ec_cart_input_row input::placeholder' => 'color: {{VALUE}} !important;',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'label_typography',
				'selectors' => array(
					'{{WRAPPER}} .ec_cart_input_row label',
					'{{WRAPPER}} .ec_cart_input_row input::placeholder',
				),
			)
		);

		$this->add_responsive_control(
			'label_spacing',
			array(
				'label'      => esc_html__( 'Spacing', 'wp-easycart' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 50,
					),
				),
				'default' => array(
					'unit' => 'px',
					'size' => 8,
				),
				'selectors'  => array(
					'{{WRAPPER}} .ec_cart_input_row label' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				),
				'condition'   => array(
					'label_type' => array( 'above', 'floating' ),
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style_fields',
			array(
				'label' => esc_html__( 'Input Fields', 'wp-easycart' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'field_typography',
				'selector' => '{{WRAPPER}} .ec_account_input_field',
			)
		);

		$this->start_controls_tabs( 'tabs_field_style' );

		$this->start_controls_tab(
			'tab_field_normal',
			array(
				'label' => esc_html__( 'Normal', 'wp-easycart' ),
			)
		);

		$this->add_control(
			'field_text_color',
			array(
				'label'     => esc_html__( 'Text Color', 'wp-easycart' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .ec_account_input_field' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'field_bg_color',
			array(
				'label'     => esc_html__( 'Background Color', 'wp-easycart' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .ec_account_input_field' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'field_border',
				'selector' => '{{WRAPPER}} .ec_account_input_field',
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tab_field_focus',
			array(
				'label' => esc_html__( 'Focus', 'wp-easycart' ),
			)
		);

		$this->add_control(
			'field_focus_text_color',
			array(
				'label'     => esc_html__( 'Text Color', 'wp-easycart' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .ec_account_input_field:focus' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'field_focus_bg_color',
			array(
				'label'     => esc_html__( 'Background Color', 'wp-easycart' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .ec_account_input_field:focus' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'field_focus_border',
				'selector' => '{{WRAPPER}} .ec_account_input_field:focus',
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'field_focus_box_shadow',
				'selector' => '{{WRAPPER}} .ec_account_input_field:focus',
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_responsive_control(
			'field_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'wp-easycart' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'separator'  => 'before',
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .ec_account_input_field' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'field_padding',
			array(
				'label'      => esc_html__( 'Padding (Size)', 'wp-easycart' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ec_account_input_field' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style_button',
			array(
				'label' => esc_html__( 'Button', 'wp-easycart' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'button_typography',
				'selector' => '{{WRAPPER}} .wp-easycart-button',
			)
		);

		$this->start_controls_tabs( 'tabs_button_style' );

		$this->start_controls_tab(
			'tab_button_normal',
			array(
				'label' => esc_html__( 'Normal', 'wp-easycart' ),
			)
		);

		$this->add_control(
			'button_text_color',
			array(
				'label'     => esc_html__( 'Text Color', 'wp-easycart' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-button' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'button_bg_color',
			array(
				'label'     => esc_html__( 'Background Color', 'wp-easycart' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-button' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'button_border',
				'selector' => '{{WRAPPER}} .wp-easycart-button',
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tab_button_hover',
			array(
				'label' => esc_html__( 'Hover', 'wp-easycart' ),
			)
		);

		$this->add_control(
			'button_hover_text_color',
			array(
				'label'     => esc_html__( 'Text Color', 'wp-easycart' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-button:hover' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'button_hover_bg_color',
			array(
				'label'     => esc_html__( 'Background Color', 'wp-easycart' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-button:hover' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'button_hover_border',
				'selector' => '{{WRAPPER}} .wp-easycart-button:hover',
			)
		);

		$this->add_control(
			'button_hover_transition',
			array(
				'label'   => esc_html__( 'Transition Duration', 'wp-easycart' ),
				'type'    => Controls_Manager::SLIDER,
				'range'   => array(
					's' => array(
						'min'  => 0,
						'max'  => 3,
						'step' => 0.1,
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-button' => 'transition-duration: {{SIZE}}s;',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_responsive_control(
			'button_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'wp-easycart' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'separator'  => 'before',
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .wp-easycart-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'button_padding',
			array(
				'label'      => esc_html__( 'Padding (Size)', 'wp-easycart' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .wp-easycart-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'button_alignment',
			array(
				'label'   => esc_html__( 'Alignment', 'wp-easycart' ),
				'type'    => Controls_Manager::CHOOSE,
				'options' => array(
					'left' => array(
						'title' => esc_html__( 'Left', 'wp-easycart' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_html__( 'Center', 'wp-easycart' ),
						'icon'  => 'eicon-text-align-center',
					),
					'right' => array(
						'title' => esc_html__( 'Right', 'wp-easycart' ),
						'icon'  => 'eicon-text-align-right',
					),
					'justify' => array(
						'title' => esc_html__( 'Full Width', 'wp-easycart' ),
						'icon'  => 'eicon-text-align-justify',
					),
				),
				'default' => 'justify',
				'selectors_dictionary' => array(
					'left'    => 'flex-start',
					'center'  => 'center',
					'right'   => 'flex-end',
					'justify' => 'stretch',
				),
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-button-row' => 'display:flex; justify-content: {{VALUE}}; width:100%;',
					'{{WRAPPER}} .wp-easycart-button' => 'align-self: {{VALUE}}'
				),
				'prefix_class' => 'wp-easycart-elementor-button-align-%s',
			)
		);
		$this->end_controls_section();
		
		$this->start_controls_section(
			'connect_order_section_layout_style',
			array(
				'label' => esc_html__( 'Connect Order Layout', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition'   => array(
					'form_type' => array( 'connect-order' ),
				),
			)
		);

		$this->add_responsive_control(
			'connect_wrapper_direction',
			array(
				'label' => esc_html__( 'Direction', 'wp-easycart' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => array(
					'row' => array(
						'title' => esc_html__( 'Row', 'wp-easycart' ),
						'icon' => 'eicon-arrow-right',
					),
					'column' => array(
						'title' => esc_html__( 'Column', 'wp-easycart' ),
						'icon' => 'eicon-arrow-down',
					),
				),
				'default' => 'row',
				'prefix_class' => 'wp-easycart-connect-direction%s-',
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-connect-order-wrapper' => 'display: flex;',
					'{{WRAPPER}}.wp-easycart-connect-direction-row .wp-easycart-connect-order-wrapper' => 'flex-direction: row; align-items: stretch;',
					'{{WRAPPER}}.wp-easycart-connect-direction-row-tablet .wp-easycart-connect-order-wrapper'  => 'flex-direction: row; align-items: stretch;',
					'{{WRAPPER}}.wp-easycart-connect-direction-row-mobile .wp-easycart-connect-order-wrapper'  => 'flex-direction: row; align-items: stretch;',
					'{{WRAPPER}}.wp-easycart-connect-direction-column .wp-easycart-connect-order-wrapper' => 'flex-direction: column; align-items: stretch;',
					'{{WRAPPER}}.wp-easycart-connect-direction-column-tablet .wp-easycart-connect-order-wrapper'  => 'flex-direction: column; align-items: stretch;',
					'{{WRAPPER}}.wp-easycart-connect-direction-column-mobile .wp-easycart-connect-order-wrapper'  => 'flex-direction: column; align-items: stretch;',
					'{{WRAPPER}}.wp-easycart-connect-direction-column .wp-easycart-connect-order-wrapper > div' => 'width: 100%;',
					'{{WRAPPER}}.wp-easycart-connect-direction-column-tablet .wp-easycart-connect-order-wrapper > div'  => 'width: 100%;',
					'{{WRAPPER}}.wp-easycart-connect-direction-column-mobile .wp-easycart-connect-order-wrapper > div'  => 'width: 100%;',
				),
			)
		);

		$this->add_responsive_control(
			'connect_input_width',
			array(
				'label' => esc_html__( 'Input Width', 'wp-easycart' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array( '%' ),
				'range' => array(
					'%' => array(
						'min' => 10,
						'max' => 90,
					),
				),
				'default' => array(
					'unit' => '%',
					'size' => 75,
				),
				'condition' => array(
					'connect_wrapper_direction' => 'row',
				),
				'selectors' => array(
					'{{WRAPPER}}.wp-easycart-connect-direction-row .wp-easycart-connect-order-wrapper > div:first-child' => 'width: {{SIZE}}%; flex-grow: 0; flex-shrink: 0;',
					'{{WRAPPER}}.wp-easycart-connect-direction-row-tablet .wp-easycart-connect-order-wrapper > div:first-child'  => 'width: {{SIZE}}%; flex-grow: 0; flex-shrink: 0;',
					'{{WRAPPER}}.wp-easycart-connect-direction-row-mobile .wp-easycart-connect-order-wrapper > div:first-child'  => 'width: {{SIZE}}%; flex-grow: 0; flex-shrink: 0;',
					'{{WRAPPER}}.wp-easycart-connect-direction-row .wp-easycart-connect-order-wrapper > div:last-child' => 'width: calc( 100% - {{SIZE}}% ); flex-grow: 1;',
					'{{WRAPPER}}.wp-easycart-connect-direction-row-tablet .wp-easycart-connect-order-wrapper > div:last-child'  => 'width: calc( 100% - {{SIZE}}% ); flex-grow: 1;',
					'{{WRAPPER}}.wp-easycart-connect-direction-row-mobile .wp-easycart-connect-order-wrapper > div:last-child'  => 'width: calc( 100% - {{SIZE}}% ); flex-grow: 1;',
					'.wp-easycart-connect-order-wrapper > div:last-child > button' => 'width:100%;',
				),
			)
		);

		$this->add_responsive_control(
			'connect_wrapper_gap',
			array(
				'label' => esc_html__( 'Gap', 'wp-easycart' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range' => array(
					'px' => array(
						'min' => 0,
						'max' => 50,
					),
				),
				'default' => array(
					'unit' => 'px',
					'size' => 10,
				),
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-connect-order-wrapper' => 'gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$atts = $this->get_settings_for_display();
		if ( 'login' == $atts['form_type'] ) {
			include( EC_PLUGIN_DIRECTORY . '/admin/elementor/wp-easycart-elementor-account-login-widget.php' );
		} else if ( 'register' == $atts['form_type'] ) {
			include( EC_PLUGIN_DIRECTORY . '/admin/elementor/wp-easycart-elementor-account-register-widget.php' );
		} else if ( 'forgot-password' == $atts['form_type'] ) {
			include( EC_PLUGIN_DIRECTORY . '/admin/elementor/wp-easycart-elementor-account-forgot-password-widget.php' );
		} else if ( 'billing' == $atts['form_type'] ) {
			include( EC_PLUGIN_DIRECTORY . '/admin/elementor/wp-easycart-elementor-account-billing-widget.php' );
		} else if ( 'shipping' == $atts['form_type'] ) {
			include( EC_PLUGIN_DIRECTORY . '/admin/elementor/wp-easycart-elementor-account-shipping-widget.php' );
		} else if ( 'personal' == $atts['form_type'] ) {
			include( EC_PLUGIN_DIRECTORY . '/admin/elementor/wp-easycart-elementor-account-personal-widget.php' );
		} else if ( 'password' == $atts['form_type'] ) {
			include( EC_PLUGIN_DIRECTORY . '/admin/elementor/wp-easycart-elementor-account-password-widget.php' );
		} else if ( 'connect-order' == $atts['form_type'] ) {
			include( EC_PLUGIN_DIRECTORY . '/admin/elementor/wp-easycart-elementor-account-connect-order-widget.php' );
		}
	}
}
