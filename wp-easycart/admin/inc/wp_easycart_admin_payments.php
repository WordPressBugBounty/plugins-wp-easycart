<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'ecv2_payment_settings_guard' ) ) {
	/**
	 * Permission + nonce guard for the payment gateway settings saves ( wp_ajax_ec_admin_ajax_save_* ).
	 *
	 * Allows administrators, and store managers who can reach Settings ( wpec_settings + wpec_manager, the same
	 * pair these handlers required before ). The nonce is the Payment settings nonce printed by the classic
	 * Payment page and the V2 gateway drawer ( field wp_easycart_payment_settings_nonce, action
	 * wp-easycart-settings-payment ), posted by admin/js/payment.js as wp_easycart_nonce. On failure it answers
	 * with a JSON error ( HTTP 403 ) and stops the request, so nothing is saved.
	 *
	 * @since 6.0.0
	 * @return bool True once the request has passed ( repeat calls in the same request are free ).
	 */
	function ecv2_payment_settings_guard() {
		static $passed = false;
		if ( $passed ) {
			return true;
		}
		if ( ! current_user_can( 'manage_options' ) && ! ( current_user_can( 'wpec_settings' ) && current_user_can( 'wpec_manager' ) ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to change the payment settings.', 'wp-easycart' ) ), 403 );
		}
		if ( ! isset( $_POST['wp_easycart_nonce'] ) || false === wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_easycart_nonce'] ) ), 'wp-easycart-settings-payment' ) ) {
			wp_send_json_error( array( 'message' => __( 'The payment settings were not saved because this page is out of date or your session expired. Reload the page and save again.', 'wp-easycart' ) ), 403 );
		}
		$passed = true;
		return true;
	}
}

if ( ! function_exists( 'ecv2_payment_link_guard' ) ) {
	/**
	 * Permission + nonce guard for the Payment settings GET links that change gateway state ( Square disconnect
	 * and renew, PayPal disconnect ). Same capability pair as ecv2_payment_settings_guard(). The link must carry
	 * the _wpnonce that wp_nonce_url( $url, $action ) adds; check_admin_referer() stops the request with the
	 * standard "The link you followed has expired" screen when it does not, so nothing changes.
	 *
	 * @since 6.0.0
	 * @param string $action Nonce action, e.g. wp-easycart-payment-square-disconnect.
	 * @return bool True once the request has passed.
	 */
	function ecv2_payment_link_guard( $action ) {
		if ( ! current_user_can( 'manage_options' ) && ! ( current_user_can( 'wpec_settings' ) && current_user_can( 'wpec_manager' ) ) ) {
			wp_die( esc_html__( 'You do not have permission to change the payment settings.', 'wp-easycart' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( $action );
		return true;
	}
}

if ( ! function_exists( 'ecv2_payment_settings_guard_pro_saves' ) ) {
	/**
	 * Runs ecv2_payment_settings_guard() ahead of the PRO gateway save handlers ( priority 0 on their
	 * wp_ajax_ hooks ). PRO 6.0.0 checks inside every handler as well; this covers sites that update the
	 * free plugin before PRO, whose older handlers saved gateway credentials without any check.
	 *
	 * @since 6.0.0
	 */
	function ecv2_payment_settings_guard_pro_saves() {
		$actions = array(
			'2checkout_thirdparty', 'cashfree', 'dwolla', 'nets', 'payfast', 'payfort', 'paymentexpress_thirdparty',
			'realex_thirdparty', 'redsys', 'sagepay_paynow_za', 'skrill', 'live_gateway_selection', 'amazonpay',
			'authorize', 'beanstream', 'braintree', 'chronopay', 'virtualmerchant', 'eway', 'firstdata', 'goemerchant',
			'intuit', 'migs', 'moneris_ca', 'moneris_us', 'nmi', 'payline', 'paymentexpress', 'paypal_pro',
			'paypal_payments_pro', 'paypoint', 'realex', 'sagepay', 'sagepayus', 'securenet', 'securepay', 'stripe',
			'square_pro', 'square_sync', 'square_product_sync', 'square_webhooks', 'cardpointe',
		);
		foreach ( $actions as $action ) {
			add_action( 'wp_ajax_ec_admin_ajax_save_' . $action, 'ecv2_payment_settings_guard', 0, 0 );
		}
	}
	ecv2_payment_settings_guard_pro_saves();
}

if ( ! class_exists( 'wp_easycart_admin_payments' ) ) :

	final class wp_easycart_admin_payments {

		protected static $_instance = null;

		public $payments_dir;

		public $third_party_gateways;
		public $live_gateways;

		public $cart_page;
		public $permalink_divider;

		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		public function __construct() {
			// Setup File Names ( the gateway partials live in payments_dir; the V2 Payment page resolves them itself )
			$this->payments_dir					= EC_PLUGIN_DIRECTORY . '/admin/template/settings/payments/';

			// Link Information
			$cart_page_id = get_option('ec_option_cartpage');
			if ( function_exists( 'icl_object_id' ) ) {
				$cart_page_id = icl_object_id( $cart_page_id, 'page', true, ICL_LANGUAGE_CODE );
			}
			$this->cart_page = get_permalink( $cart_page_id );
			if ( class_exists( 'WordPressHTTPS' ) && isset( $_SERVER['HTTPS'] ) ) {
				$https_class = new WordPressHTTPS();
				$this->cart_page = $https_class->makeUrlHttps( $this->cart_page );
			}
			if ( substr_count( $this->cart_page, '?' ) )					$this->permalink_divider = '&';
			else														$this->permalink_divider = '?';

			// Setup Default Payment Options
			$this->third_party_gateways = array(
				'2checkout_thirdparty' => '2Checkout',
				'cashfree' => 'CashFree',
				'dwolla_thirdparty' => 'Dwolla',
				'nets' => 'Nets Nexaxept',
				'payfast_thirdparty' => 'PayFast',
				'payfort' => 'Payfort',
				'paymentexpress_thirdparty' => 'Payment Express PxPay 2.0',
				'realex_thirdparty' => 'Realex',
				'redsys' => 'Redsys',
				'sagepay_paynow_za' => 'SagePay Pay Now South Africa',
				'skrill' => 'Skrill',
				'custom_thirdparty' => __( 'Custom Gateway', 'wp-easycart' ),
			);
			$this->live_gateways = array(
				'authorize' => 'Authorize.net',
				'beanstream' => 'Bambora',
				'braintree' => 'Braintree S2S',
				'cardpointe' => 'Cardpointe',
				'chronopay' => 'Chronopay',
				'virtualmerchant' => 'Converge (Virtual Merchant)',
				'eway' => 'Eway',
				'firstdata' => 'First Data Payeezy (e4)',
				'goemerchant' => 'GoeMerchant',
				'intuit' => 'Intuit Payments',
				'migs' => 'MIGS', 
				'moneris_ca' => 'Moneris Canada',
				'moneris_us' => 'Moneris USA',
				'nmi' => 'Network Merchants (NMI)',
				'sagepay' => 'Opayo by Elavon (Formerly Sagepay)',
				'sagepayus' => 'Paya (Previously Sagepay US)',
				'payline' => 'Payline',
				'paymentexpress' => 'Payment Express PxPost',
				'paypal_pro' => 'PayPal PayFlow Pro',
				'paypal_payments_pro' => 'PayPal Payments Pro',
				'paypoint' => 'PayPoint', 
				'realex' => 'Realex',
				'securepay' => 'SecurePay',
				'stripe' => 'Stripe (v1)',
				'securenet' => 'WorldPay',
				'custom' => __( 'Custom Payment Gateway', 'wp-easycart' ),
			);

			add_filter( 'wp_easycart_admin_success_messages', array( $this, 'add_success_messages' ) );
			add_filter( 'wp_easycart_admin_error_messages', array( $this, 'add_failure_messages' ) );

			add_action( 'wp_easycart_process_get_form_action', array( $this, 'disconnect_paypal' ) );
			add_action( 'wp_easycart_process_get_form_action', array( $this, 'onboard_stripe' ) );

			add_action( 'wp_easycart_process_get_form_action', array( $this, 'process_square_app' ) );
		}

		public function add_success_messages( $messages ) {
			if ( isset( $_GET['success'] ) && $_GET['success'] == 'stripe-sandbox-connected' ) {
				$messages[] = __( 'Connected to Stripe Sandbox Successfully!', 'wp-easycart' );
			} else if ( isset( $_GET['success'] ) && $_GET['success'] == 'stripe-live-connected' ) {
				$messages[] = __( 'Connected to Stripe Successfully! You can now process live transactions.', 'wp-easycart' );
			} else if ( isset( $_GET['success'] ) && $_GET['success'] == 'stripe-sandbox-mode' ) {
				$messages[] = __( 'Stripe is now in Sandbox Mode, test orders only!', 'wp-easycart' );
			} else if ( isset( $_GET['success'] ) && $_GET['success'] == 'stripe-live-mode' ) {
				$messages[] = __( 'Stripe is now in Live Mode, you can process live transactions', 'wp-easycart' );
			} else if ( isset( $_GET['success'] ) && $_GET['success'] == 'stripe-sandbox-disconnected' ) {
				$messages[] = __( 'Sandbox keys have been removed from your site. You will still have to revoke access from your Stripe account.', 'wp-easycart' );
			} else if ( isset( $_GET['success'] ) && $_GET['success'] == 'stripe-live-disconnected' ) {
				$messages[] = __( 'Live keys have been removed from your site. You will still have to revoke access from your Stripe account.', 'wp-easycart' );
			}
			return $messages;
		}

		public function add_failure_messages( $messages ) {
			if ( isset( $_GET['error'] ) && $_GET['error'] == 'stripe-onboarding-error' ) {
				$messages[] = __( 'An error occured during the authorization of your Stripe account. Please try again or contact WP EasyCart for assistence.', 'wp-easycart' );
			}
			return $messages;
		}



		/**
		 * Include a gateway settings partial from this instance, so the partial can read $this->cart_page and
		 * $this->permalink_divider the way it does on the classic Payment page ( the PRO dwolla_thirdparty.php and
		 * realex_thirdparty.php partials print a callback URL from them ). The V2 gateway drawer
		 * ( wp_easycart_admin_payment_v2::ajax_gateway_form(), a static handler ) uses this instead of a bare
		 * include, which fatals on "Using $this when not in object context" with those partials.
		 *
		 * @since 6.0.0
		 * @param string $file Absolute path of the partial; the caller has already checked it exists.
		 */
		public function include_gateway_partial( $file ) {
			if ( ! is_string( $file ) || '' === $file || ! file_exists( $file ) ) {
				return;
			}
			include $file;
		}

		public function update_manual_billing_settings() {
			ecv2_payment_settings_guard();

			update_option( 'ec_option_use_direct_deposit', wp_easycart_admin_verification()->filter_bool_int( (int) $_POST['ec_option_use_direct_deposit'] ) );
			update_option( 'ec_option_direct_deposit_message', sanitize_textarea_field( wp_unslash( $_POST['ec_option_direct_deposit_message'] ) ) );

			if ( isset( $_POST['ec_language_field']['cart_payment_information_manual_payment'] ) ) {
				$_POST['ec_language_field']['cart_payment_information_manual_payment'] = sanitize_text_field( wp_unslash( $_POST['ec_language_field']['cart_payment_information_manual_payment'] ) );
			}

			wp_easycart_language()->update_language_data();
			do_action( 'wpeasycart_manual_billing_updated', wp_easycart_admin_verification()->filter_bool_int( (int) $_POST['ec_option_use_direct_deposit'] ) );
		}

		public function update_third_party_selection() {
			ecv2_payment_settings_guard();

			if ( ! isset( $_POST['ec_option_payment_third_party'] ) ) {
				return false;
			}
			$third_party = sanitize_text_field( wp_unslash( $_POST['ec_option_payment_third_party'] ) );
			if ( ! in_array( $third_party, $this->known_third_party_keys(), true ) ) {
				return false;
			}
			update_option( 'ec_option_payment_third_party', $third_party );
			do_action( 'wpeasycart_third_party_payment_updated', $third_party );
			return true;
		}

		/**
		 * Third-party gateway keys the settings may store, plus '0' ( none ): the V2 gateway catalog, the legacy
		 * list and its 'wp_easycart_admin_third_party_gateways' filter.
		 *
		 * @since 6.0.0
		 * @return string[]
		 */
		public function known_third_party_keys() {
			$keys = array( '0', 'paypal' );
			if ( class_exists( 'wp_easycart_admin_payment_v2' ) && method_exists( 'wp_easycart_admin_payment_v2', 'catalog' ) ) {
				foreach ( (array) wp_easycart_admin_payment_v2::catalog() as $key => $gw ) {
					if ( is_array( $gw ) && isset( $gw['role'] ) && 'third_party' === $gw['role'] ) {
						$keys[] = (string) $key;
					}
				}
			}
			$legacy = apply_filters( 'wp_easycart_admin_third_party_gateways', (array) $this->third_party_gateways );
			foreach ( array_keys( (array) $legacy ) as $key ) {
				$keys[] = (string) $key;
			}
			return array_values( array_unique( $keys ) );
		}

		public function update_paypal() {
			ecv2_payment_settings_guard();

			$paypal_email = ( isset( $_POST['ec_option_paypal_email'] ) ) ? sanitize_email( wp_unslash( $_POST['ec_option_paypal_email'] ) ) : '';
			update_option( 'ec_option_paypal_email', $paypal_email );

			update_option( 'ec_option_paypal_enable_pay_now', wp_easycart_admin_verification()->filter_bool_int( (int) $_POST['ec_option_paypal_enable_pay_now'] ) );
			update_option( 'ec_option_paypal_enable_credit', wp_easycart_admin_verification()->filter_bool_int( (int) $_POST['ec_option_paypal_enable_credit'] ) );
			update_option( 'ec_option_paypal_sandbox_access_token_expires', 0 );
			update_option( 'ec_option_paypal_production_access_token_expires', 0 );

			update_option( 'ec_option_paypal_currency_code', wp_easycart_admin_verification()->filter_chars( sanitize_text_field( wp_unslash( $_POST['ec_option_paypal_currency_code'] ) ), 3 ) );
			update_option( 'ec_option_paypal_use_selected_currency', wp_easycart_admin_verification()->filter_bool_int( (int) $_POST['ec_option_paypal_use_selected_currency'] ) );
			update_option( 'ec_option_paypal_use_venmo', wp_easycart_admin_verification()->filter_bool_int( (int) $_POST['ec_option_paypal_use_venmo'] ) );
			update_option( 'ec_option_paypal_use_card', wp_easycart_admin_verification()->filter_bool_int( (int) $_POST['ec_option_paypal_use_card'] ) );
			update_option( 'ec_option_paypal_use_paylater', wp_easycart_admin_verification()->filter_bool_int( (int) $_POST['ec_option_paypal_use_paylater'] ) );
			update_option( 'ec_option_paypal_lc', sanitize_text_field( wp_unslash( $_POST['ec_option_paypal_lc'] ) ) );
			update_option( 'ec_option_paypal_charset', sanitize_text_field( wp_unslash( $_POST['ec_option_paypal_charset'] ) ) );
			update_option( 'ec_option_paypal_weight_unit', wp_easycart_admin_verification()->filter_list( sanitize_text_field( wp_unslash( $_POST['ec_option_paypal_weight_unit'] ) ), array( 'lbs', 'kgs' ) ) );
			update_option( 'ec_option_paypal_use_sandbox', wp_easycart_admin_verification()->filter_bool_int( (int) $_POST['ec_option_paypal_use_sandbox'] ) );
			update_option( 'ec_option_paypal_collect_shipping', wp_easycart_admin_verification()->filter_bool_int( (int) $_POST['ec_option_paypal_collect_shipping'] ) );

			update_option( 'ec_option_paypal_button_color', wp_easycart_admin_verification()->filter_list( sanitize_text_field( wp_unslash( $_POST['ec_option_paypal_button_color'] ) ), array( 'gold', 'blue', 'silver', 'black' ) ) );
			update_option( 'ec_option_paypal_button_shape', wp_easycart_admin_verification()->filter_list( sanitize_text_field( wp_unslash( $_POST['ec_option_paypal_button_shape'] ) ), array( 'pill', 'rect' ) ) );
			update_option( 'ec_option_paypal_express_page1_checkout', wp_easycart_admin_verification()->filter_bool_int( (int) $_POST['ec_option_paypal_express_page1_checkout'] ) );

			if ( isset( $_POST['ec_option_paypal_marketing_solution_cid_sandbox'] ) ) {
				update_option( 'ec_option_paypal_marketing_solution_cid_sandbox', wp_easycart_admin_verification()->filter_bool_int( (int) $_POST['ec_option_paypal_marketing_solution_cid_sandbox'] ) );
			}
			if ( isset( $_POST['ec_option_paypal_marketing_solution_cid_production'] ) ) {
				update_option( 'ec_option_paypal_marketing_solution_cid_production', wp_easycart_admin_verification()->filter_bool_int( (int) $_POST['ec_option_paypal_marketing_solution_cid_production'] ) );
			}

			do_action( 'wp_easycart_paypal_standard_updated' );
		}

		public function update_pro_paypal() {
			ecv2_payment_settings_guard();

			update_option( 'ec_option_paypal_email', sanitize_email( wp_unslash( $_POST['ec_option_paypal_email'] ) ) );

			update_option( 'ec_option_paypal_enable_pay_now', sanitize_text_field( wp_unslash( $_POST['ec_option_paypal_enable_pay_now'] ) ) );
			update_option( 'ec_option_paypal_enable_credit', sanitize_text_field( wp_unslash( $_POST['ec_option_paypal_enable_credit'] ) ) );
			update_option( 'ec_option_paypal_sandbox_access_token_expires', 0 );
			update_option( 'ec_option_paypal_production_access_token_expires', 0 );

			update_option( 'ec_option_paypal_sandbox_app_id', sanitize_text_field( wp_unslash( $_POST['ec_option_paypal_sandbox_app_id'] ) ) );
			update_option( 'ec_option_paypal_sandbox_secret', sanitize_text_field( wp_unslash( $_POST['ec_option_paypal_sandbox_secret'] ) ) );

			update_option( 'ec_option_paypal_production_app_id', sanitize_text_field( wp_unslash( $_POST['ec_option_paypal_production_app_id'] ) ) );
			update_option( 'ec_option_paypal_production_secret', sanitize_text_field( wp_unslash( $_POST['ec_option_paypal_production_secret'] ) ) );

			update_option( 'ec_option_paypal_currency_code', sanitize_text_field( wp_unslash( $_POST['ec_option_paypal_currency_code'] ) ) );
			update_option( 'ec_option_paypal_use_selected_currency', sanitize_text_field( wp_unslash( $_POST['ec_option_paypal_use_selected_currency'] ) ) );
			update_option( 'ec_option_paypal_lc', sanitize_text_field( wp_unslash( $_POST['ec_option_paypal_lc'] ) ) );
			update_option( 'ec_option_paypal_charset', sanitize_text_field( wp_unslash( $_POST['ec_option_paypal_charset'] ) ) );
			update_option( 'ec_option_paypal_weight_unit', sanitize_text_field( wp_unslash( $_POST['ec_option_paypal_weight_unit'] ) ) );
			update_option( 'ec_option_paypal_use_sandbox', (int) $_POST['ec_option_paypal_use_sandbox'] );
			update_option( 'ec_option_paypal_collect_shipping', sanitize_text_field( wp_unslash( $_POST['ec_option_paypal_collect_shipping'] ) ) );

			update_option( 'ec_option_paypal_button_color', sanitize_text_field( wp_unslash( $_POST['ec_option_paypal_button_color'] ) ) );
			update_option( 'ec_option_paypal_button_shape', sanitize_text_field( wp_unslash( $_POST['ec_option_paypal_button_shape'] ) ) );
			update_option( 'ec_option_paypal_express_page1_checkout', sanitize_text_field( wp_unslash( $_POST['ec_option_paypal_express_page1_checkout'] ) ) );

			update_option( 'ec_option_paypal_marketing_solution_cid_sandbox', sanitize_text_field( wp_unslash( $_POST['ec_option_paypal_marketing_solution_cid_sandbox'] ) ) );
			update_option( 'ec_option_paypal_marketing_solution_cid_production', sanitize_text_field( wp_unslash( $_POST['ec_option_paypal_marketing_solution_cid_production'] ) ) );

			do_action( 'wp_easycart_paypal_standard_updated' );
		}

		public function disconnect_paypal() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_settings' ) ) {
				return;
			}

			if ( ! isset( $_GET['ec_admin_form_action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- dispatch only; the matching action is verified by ecv2_payment_link_guard() below.
				return;
			}
			$form_action = sanitize_key( wp_unslash( $_GET['ec_admin_form_action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- dispatch only; verified below before anything changes.
			$cleared     = array(
				'paypal-express-sandbox-disconnect'      => array( 'ec_option_paypal_sandbox_webhook_id', 'ec_option_paypal_sandbox_merchant_id' ),
				'paypal-express-production-disconnect'   => array( 'ec_option_paypal_production_webhook_id', 'ec_option_paypal_production_merchant_id' ),
				'paypal-marketing-sandbox-disconnect'    => array( 'ec_option_paypal_marketing_solution_cid_sandbox' ),
				'paypal-marketing-production-disconnect' => array( 'ec_option_paypal_marketing_solution_cid_production' ),
			);
			if ( ! isset( $cleared[ $form_action ] ) ) {
				return;
			}
			ecv2_payment_link_guard( 'wp-easycart-payment-paypal-disconnect' );
			foreach ( $cleared[ $form_action ] as $option_name ) {
				update_option( $option_name, '' );
			}
			wp_easycart_admin()->redirect( 'wp-easycart-settings', 'payment', array() );
		}

		public function save_stripe_connect() {
			ecv2_payment_settings_guard();

			update_option( 'ec_option_stripe_connect_use_sandbox', wp_easycart_admin_verification()->filter_bool_int( (int) $_POST['ec_option_stripe_connect_use_sandbox'] ) );
			update_option( 'ec_option_payment_process_method', wp_easycart_admin_verification()->filter_list( sanitize_text_field( wp_unslash( $_POST['ec_option_payment_process_method'] ) ), array( 'stripe_connect' ) ) );
			update_option( 'ec_option_stripe_currency', wp_easycart_admin_verification()->filter_chars( sanitize_text_field( wp_unslash( $_POST['ec_option_stripe_currency'] ) ), 3 ) );
			update_option( 'ec_option_stripe_company_country', wp_easycart_admin_verification()->filter_chars( sanitize_text_field( wp_unslash( $_POST['ec_option_stripe_company_country'] ) ), 2 ) );
			update_option( 'ec_option_stripe_payment_theme', sanitize_text_field( wp_unslash( $_POST['ec_option_stripe_payment_theme'] ) ) );
			update_option( 'ec_option_stripe_payment_layout', sanitize_text_field( wp_unslash( $_POST['ec_option_stripe_payment_layout'] ) ) );
			update_option( 'ec_option_stripe_subscription_notices', sanitize_text_field( wp_unslash( $_POST['ec_option_stripe_subscription_notices'] ) ) );
			update_option( 'ec_option_stripe_address_autocomplete', sanitize_text_field( (int) $_POST['ec_option_stripe_address_autocomplete'] ) );
			update_option( 'ec_option_stripe_connect_webhook_secret', sanitize_text_field( wp_unslash( $_POST['ec_option_stripe_connect_webhook_secret'] ) ) );
			
			update_option( 'ec_option_stripe_affirm', wp_easycart_admin_verification()->filter_bool_int( (int) $_POST['ec_option_stripe_affirm'] ) );
			update_option( 'ec_option_stripe_afterpay', wp_easycart_admin_verification()->filter_bool_int( (int) $_POST['ec_option_stripe_afterpay'] ) );
			update_option( 'ec_option_stripe_klarna', wp_easycart_admin_verification()->filter_bool_int( (int) $_POST['ec_option_stripe_klarna'] ) );
			update_option( 'ec_option_stripe_pay_later_minimum', wp_easycart_admin_verification()->filter_bool_int( (int) $_POST['ec_option_stripe_pay_later_minimum'] ) );

			update_option( 'ec_option_stripe_enable_apple_pay', wp_easycart_admin_verification()->filter_bool_int( (int) $_POST['ec_option_stripe_enable_apple_pay'] ) );
			update_option( 'ec_option_stripe_disable_wallet_first', wp_easycart_admin_verification()->filter_bool_int( ( ( isset( $_POST['ec_option_stripe_disable_wallet_first'] ) ) ? (int) $_POST['ec_option_stripe_disable_wallet_first'] : 0 ) ) );
			update_option( 'ec_option_stripe_alipay', wp_easycart_admin_verification()->filter_bool_int( (int) $_POST['ec_option_stripe_alipay'] ) );
			update_option( 'ec_option_stripe_grabpay', wp_easycart_admin_verification()->filter_bool_int( (int) $_POST['ec_option_stripe_grabpay'] ) );
			update_option( 'ec_option_stripe_wechat', wp_easycart_admin_verification()->filter_bool_int( (int) $_POST['ec_option_stripe_wechat'] ) );
			update_option( 'ec_option_stripe_link', wp_easycart_admin_verification()->filter_bool_int( (int) $_POST['ec_option_stripe_link'] ) );

			update_option( 'ec_option_stripe_bancontact', wp_easycart_admin_verification()->filter_bool_int( (int) $_POST['ec_option_stripe_bancontact'] ) );
			update_option( 'ec_option_stripe_blik', wp_easycart_admin_verification()->filter_bool_int( (int) $_POST['ec_option_stripe_blik'] ) );
			update_option( 'ec_option_stripe_eps', wp_easycart_admin_verification()->filter_bool_int( (int) $_POST['ec_option_stripe_eps'] ) );
			update_option( 'ec_option_stripe_fpx', wp_easycart_admin_verification()->filter_bool_int( (int) $_POST['ec_option_stripe_fpx'] ) );
			update_option( 'ec_option_stripe_giropay', wp_easycart_admin_verification()->filter_bool_int( (int) $_POST['ec_option_stripe_giropay'] ) );
			update_option( 'ec_option_stripe_enable_ideal', wp_easycart_admin_verification()->filter_bool_int( (int) $_POST['ec_option_stripe_enable_ideal'] ) );
			update_option( 'ec_option_stripe_p24', wp_easycart_admin_verification()->filter_bool_int( (int) $_POST['ec_option_stripe_p24'] ) );
			update_option( 'ec_option_stripe_sofort', wp_easycart_admin_verification()->filter_bool_int( (int) $_POST['ec_option_stripe_sofort'] ) );

			update_option( 'ec_option_stripe_bacs', wp_easycart_admin_verification()->filter_bool_int( (int) $_POST['ec_option_stripe_bacs'] ) );
			update_option( 'ec_option_stripe_becs', wp_easycart_admin_verification()->filter_bool_int( (int) $_POST['ec_option_stripe_becs'] ) );
			update_option( 'ec_option_stripe_sepa', wp_easycart_admin_verification()->filter_bool_int( (int) $_POST['ec_option_stripe_sepa'] ) );

			update_option( 'ec_option_stripe_pix', wp_easycart_admin_verification()->filter_bool_int( (int) $_POST['ec_option_stripe_pix'] ) );
			update_option( 'ec_option_stripe_paynow', wp_easycart_admin_verification()->filter_bool_int( (int) $_POST['ec_option_stripe_paynow'] ) );
			update_option( 'ec_option_stripe_promptpay', wp_easycart_admin_verification()->filter_bool_int( (int) $_POST['ec_option_stripe_promptpay'] ) );

			update_option( 'ec_option_stripe_boleto', wp_easycart_admin_verification()->filter_bool_int( (int) $_POST['ec_option_stripe_boleto'] ) );
			update_option( 'ec_option_stripe_konbini', wp_easycart_admin_verification()->filter_bool_int( (int) $_POST['ec_option_stripe_konbini'] ) );
			update_option( 'ec_option_stripe_oxxo', wp_easycart_admin_verification()->filter_bool_int( (int) $_POST['ec_option_stripe_oxxo'] ) );
		}

		public function save_stripe_connect_option() {
			ecv2_payment_settings_guard();

			$options = array( 'ec_option_stripe_affirm', 'ec_option_stripe_afterpay', 'ec_option_stripe_klarna', 'ec_option_stripe_enable_apple_pay', 'ec_option_stripe_disable_wallet_first', 'ec_option_stripe_alipay', 'ec_option_stripe_grabpay', 'ec_option_stripe_wechat', 'ec_option_stripe_link', 'ec_option_stripe_bancontact', 'ec_option_stripe_blik', 'ec_option_stripe_eps', 'ec_option_stripe_fpx', 'ec_option_stripe_giropay', 'ec_option_stripe_enable_ideal', 'ec_option_stripe_p24', 'ec_option_stripe_sofort', 'ec_option_stripe_bacs', 'ec_option_stripe_becs', 'ec_option_stripe_sepa', 'ec_option_stripe_pix', 'ec_option_stripe_paynow', 'ec_option_stripe_promptpay', 'ec_option_stripe_boleto', 'ec_option_stripe_konbini', 'ec_option_stripe_oxxo' );
			
			$text_options = array( 'ec_option_stripe_pay_later_minimum' );
			
			if ( isset( $_POST['update_var'] ) && in_array( $_POST['update_var'], $options ) ) {
				$val = ( isset( $_POST['val'] ) && $_POST['val'] == '1' ) ? 1 : 0;
				update_option( sanitize_text_field( wp_unslash( $_POST['update_var'] ) ), $val );
			} else if ( isset( $_POST['update_var'] ) && in_array( $_POST['update_var'], $text_options ) ) {
				update_option( sanitize_text_field( wp_unslash( $_POST['update_var'] ) ), sanitize_text_field( wp_unslash( $_POST['val'] ) ) );
			}
		}

		public function onboard_stripe() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_settings' ) ) {
				return;
			}

			if ( ! wp_easycart_admin_verification()->verify_access( 'wp-easycart-stripe' ) ) {
				return false;
			}

			if ( $_GET['ec_admin_form_action'] == 'stripe_onboard' && isset( $_GET['error'] ) ) {
				if ( isset( $_GET['goto'] ) && $_GET['goto'] == 'wizard' ) {
					wp_redirect( 'admin.php?page=wp-easycart-settings&subpage=setup-wizard&step=3&error=stripe-onboarding-error' );
				} else {
					wp_easycart_admin()->redirect( 'wp-easycart-settings', 'payment', array( 'error' => 'stripe-onboarding-error' ) );
				}
				die();

			}

			if ( $_GET['ec_admin_form_action'] == 'stripe_onboard' && isset( $_GET['env'] ) && in_array( $_GET['env'], array( 'sandbox', 'production' ), true ) ) {

				$stripe_access_token    = isset( $_GET['access_token'] ) ? wp_easycart_admin_verification()->min_filter( sanitize_text_field( wp_unslash( $_GET['access_token'] ) ) ) : '';
				$stripe_refresh_token   = isset( $_GET['refresh_token'] ) ? wp_easycart_admin_verification()->min_filter( sanitize_text_field( wp_unslash( $_GET['refresh_token'] ) ) ) : '';
				$stripe_publishable_key = isset( $_GET['stripe_publishable_key'] ) ? wp_easycart_admin_verification()->min_filter( sanitize_text_field( wp_unslash( $_GET['stripe_publishable_key'] ) ) ) : '';
				$stripe_user_id         = isset( $_GET['stripe_user_id'] ) ? wp_easycart_admin_verification()->min_filter( sanitize_text_field( wp_unslash( $_GET['stripe_user_id'] ) ) ) : '';

				// Reject the return if any required credential is missing or blank. Do NOT save or enable the gateway.
				if ( '' == $stripe_access_token || '' == $stripe_publishable_key || '' == $stripe_user_id ) {
					if ( isset( $_GET['goto'] ) && $_GET['goto'] == 'wizard' ) {
						wp_redirect( 'admin.php?page=wp-easycart-settings&subpage=setup-wizard&step=3&error=stripe-onboarding-error' );
					} else {
						wp_easycart_admin()->redirect( 'wp-easycart-settings', 'payment', array( 'error' => 'stripe-onboarding-error' ) );
					}
					die();
				}

				$stripe_option_prefix = ( 'sandbox' == $_GET['env'] ) ? 'ec_option_stripe_connect_sandbox_' : 'ec_option_stripe_connect_production_';
				update_option( 'ec_option_stripe_connect_use_sandbox', ( 'sandbox' == $_GET['env'] ) ? 1 : 0 );
				update_option( $stripe_option_prefix . 'access_token', $stripe_access_token );
				update_option( $stripe_option_prefix . 'refresh_token', $stripe_refresh_token );
				update_option( $stripe_option_prefix . 'publishable_key', $stripe_publishable_key );
				update_option( $stripe_option_prefix . 'user_id', $stripe_user_id );
				update_option( 'ec_option_payment_process_method', 'stripe_connect' );
				update_option( 'ec_option_default_payment_type', 'credit_card' );
				do_action( 'wpeasycart_live_gateway_updated', get_option( 'ec_option_payment_process_method' ) );
				$stripe_success_message = ( 'sandbox' == $_GET['env'] ) ? 'stripe-sandbox-connected' : 'stripe-live-connected';
				if ( isset( $_GET['goto'] ) && $_GET['goto'] == 'wizard' ) {
					wp_redirect( 'admin.php?page=wp-easycart-settings&subpage=setup-wizard&step=3&success=' . $stripe_success_message );
				} else {
					wp_easycart_admin()->redirect( 'wp-easycart-settings', 'payment', array( 'success' => $stripe_success_message ) );
				}
				die();

			} else if ( $_GET['ec_admin_form_action'] == 'stripe-connect-use-sandbox' ) {
				update_option( 'ec_option_stripe_connect_use_sandbox', 1 );
				if ( isset( $_GET['goto'] ) && $_GET['goto'] == 'wizard' ) {
					wp_redirect( 'admin.php?page=wp-easycart-settings&subpage=setup-wizard&step=3&success=stripe-sandbox-mode' );
				} else {
					wp_easycart_admin()->redirect( 'wp-easycart-settings', 'payment', array( 'success' => 'stripe-sandbox-mode' ) );
				}
				die();

			} else if ( $_GET['ec_admin_form_action'] == 'stripe-connect-use-production' ) {
				update_option( 'ec_option_stripe_connect_use_sandbox', 0 );
				if ( isset( $_GET['goto'] ) && $_GET['goto'] == 'wizard' ) {
					wp_redirect( 'admin.php?page=wp-easycart-settings&subpage=setup-wizard&step=3&success=stripe-live-mode' );
				} else {
					wp_easycart_admin()->redirect( 'wp-easycart-settings', 'payment', array( 'success' => 'stripe-live-mode' ) );
				}
				die();

			} else if ( $_GET['ec_admin_form_action'] == 'stripe-connect-sandbox-disconnect' ) {
				update_option( 'ec_option_stripe_connect_use_sandbox', 0 );
				update_option( 'ec_option_stripe_connect_sandbox_access_token', '' );
				update_option( 'ec_option_stripe_connect_sandbox_refresh_token', '' );
				update_option( 'ec_option_stripe_connect_sandbox_publishable_key', '' );
				update_option( 'ec_option_stripe_connect_sandbox_user_id', '' );
				update_option( 'ec_option_payment_process_method', '0' );
				if ( isset( $_GET['goto'] ) && $_GET['goto'] == 'wizard' ) {
					wp_redirect( 'admin.php?page=wp-easycart-settings&subpage=setup-wizard&step=3&success=stripe-sandbox-disconnected' );
				} else {
					wp_easycart_admin()->redirect( 'wp-easycart-settings', 'payment', array( 'success' => 'stripe-sandbox-disconnected' ) );
				}
				die();

			} else if ( $_GET['ec_admin_form_action'] == 'stripe-connect-production-disconnect' ) {
				update_option( 'ec_option_stripe_connect_use_sandbox', 0 );
				update_option( 'ec_option_stripe_connect_production_access_token', '' );
				update_option( 'ec_option_stripe_connect_production_refresh_token', '' );
				update_option( 'ec_option_stripe_connect_production_publishable_key', '' );
				update_option( 'ec_option_stripe_connect_production_user_id', '' );
				update_option( 'ec_option_payment_process_method', '0' );
				if ( isset( $_GET['goto'] ) && $_GET['goto'] == 'wizard' ) {
					wp_redirect( 'admin.php?page=wp-easycart-settings&subpage=setup-wizard&step=3&success=stripe-live-disconnected' );
				} else {
					wp_easycart_admin()->redirect( 'wp-easycart-settings', 'payment', array( 'success' => 'stripe-live-disconnected' ) );
				}
				die();

			}
		}

		public function process_square_app() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_settings' ) ) {
				return;
			}

			if ( $_GET['ec_admin_form_action'] == 'handle-square' && isset( $_GET['wpeasycart_square_failed'] ) ) {
				if ( isset( $_GET['goto'] ) && $_GET['goto'] == 'wizard' ) {
					wp_redirect( 'admin.php?page=wp-easycart-settings&subpage=setup-wizard&step=3&error=square-failed-to-connect' );
				} else {
					wp_redirect( 'admin.php?page=wp-easycart-settings&subpage=payment&error=square-failed-to-connect' );
				}
				die();

			// Handle a Successful Connect Attempt
			} else if ( $_GET['ec_admin_form_action'] == 'handle-square' && isset( $_GET['wpeasycart_square_state'] ) ) {
				if ( ! isset( $_GET['wpeasycart_square_state'] ) ) {
					return false;
				}
				if ( false === wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['wpeasycart_square_state'] ) ), 'wp-easycart-square' ) ) {
					return false;
				}
				$access_token = ( isset( $_GET['access_token'] ) ) ? preg_replace( '/[^A-Za-z0-9 \-\._\~\+\/]/', '', sanitize_text_field( wp_unslash( $_GET['access_token'] ) ) ) : '';
				$refresh_token = ( isset( $_GET['refresh_token'] ) ) ? preg_replace( '/[^A-Za-z0-9 \-\._\~\+\/]/', '', sanitize_text_field( wp_unslash( $_GET['refresh_token'] ) ) ) : '';
				$expires = ( isset( $_GET['expires'] ) ) ? preg_replace( '/[^A-Za-z0-9 \-\:]/', '', sanitize_text_field( wp_unslash( $_GET['expires'] ) ) ) : '';

				update_option( 'ec_option_payment_process_method', 'square' );
				if ( isset( $_GET['sandbox'] ) ) {
					update_option( 'ec_option_square_is_sandbox', 1 );
					update_option( 'ec_option_square_sandbox_application_id', '' );
					update_option( 'ec_option_square_sandbox_access_token', $access_token );
					update_option( 'ec_option_square_sandbox_refresh_token', $refresh_token );
					update_option( 'ec_option_square_sandbox_token_expires', $expires );

				} else {
					update_option( 'ec_option_square_is_sandbox', 0 );
					update_option( 'ec_option_square_application_id', '' );			
					update_option( 'ec_option_square_access_token', $access_token );
					update_option( 'ec_option_square_refresh_token', $refresh_token );
					update_option( 'ec_option_square_token_expires', $expires );
				}
				do_action( 'wpeasycart_live_gateway_updated', get_option( 'ec_option_payment_process_method' ) );

				$square = new ec_square();
				$square->set_currency();

				if ( !wp_next_scheduled( 'wp_easycart_square_renew_token' ) ) {
					wp_schedule_event( time(), 'daily', 'wp_easycart_square_renew_token' );
				}

				if ( isset( $_GET['goto'] ) && $_GET['goto'] == 'wizard' ) {
					wp_redirect( 'admin.php?page=wp-easycart-settings&subpage=setup-wizard&step=3&success=square-connected' );
				} else {
					wp_redirect( 'admin.php?page=wp-easycart-settings&subpage=payment&success=square-connected' );
				}
				die();

			} else if ( $_GET['ec_admin_form_action'] == 'square-disconnect' ) {
				ecv2_payment_link_guard( 'wp-easycart-payment-square-disconnect' );
				if ( isset( $_GET['sandbox'] ) ) {
					$access_token = get_option( 'ec_option_square_sandbox_access_token' );
					$request = new WP_Http;
					$response = $request->request( 
						'https://connect.wpeasycart.com/square-sandbox/disconnect.php?access_token=' . $access_token, 
						array( 
							'method' => 'GET',
							'timeout' => 5
						)
					);
					update_option( 'ec_option_payment_process_method', '0' );
					update_option( 'ec_option_square_sandbox_application_id', '' );
					update_option( 'ec_option_square_sandbox_access_token', '' );
					update_option( 'ec_option_square_sandbox_refresh_token', '' );
					update_option( 'ec_option_square_sandbox_token_expires', '' );

				} else {
					$access_token = get_option( 'ec_option_square_access_token' );
					$request = new WP_Http;
					$response = $request->request( 
						'https://connect.wpeasycart.com/square/disconnect.php?access_token=' . $access_token, 
						array( 
							'method' => 'GET',
							'timeout' => 5
						)
					);
					update_option( 'ec_option_payment_process_method', '0' );
					update_option( 'ec_option_square_application_id', '' );
					update_option( 'ec_option_square_access_token', '' );
					update_option( 'ec_option_square_refresh_token', '' );
					update_option( 'ec_option_square_token_expires', '' );
				}
				wp_clear_scheduled_hook( 'wp_easycart_square_renew_token' );
				wp_easycart_admin()->redirect( 'wp-easycart-settings', 'payment', array() );

			} else if ( $_GET['ec_admin_form_action'] == 'square-renew' ) {
				ecv2_payment_link_guard( 'wp-easycart-payment-square-renew' );
				$square = new ec_square();
				$square->renew_token();
				wp_clear_scheduled_hook( 'wp_easycart_square_renew_token' );
				if ( !wp_next_scheduled( 'wp_easycart_square_renew_token' ) ) {
					wp_schedule_event( time(), 'daily', 'wp_easycart_square_renew_token' );
				}
				wp_easycart_admin()->redirect( 'wp-easycart-settings', 'payment', array() );
			}
		}

		public function update_square() {
			ecv2_payment_settings_guard();

			update_option( 'ec_option_payment_process_method', wp_easycart_admin_verification()->filter_list( sanitize_text_field( wp_unslash( $_POST['payment_method'] ) ), array( 'square' ) ) );
			if ( get_option( 'ec_option_square_is_sandbox' ) ) {
				update_option( 'ec_option_square_sandbox_location_id', sanitize_text_field( wp_unslash( $_POST['ec_option_square_location_id'] ) ) );

			} else {
				update_option( 'ec_option_square_location_id', sanitize_text_field( wp_unslash( $_POST['ec_option_square_location_id'] ) ) );

			}
			update_option( 'ec_option_square_location_country', wp_easycart_admin_verification()->filter_chars( sanitize_text_field( wp_unslash( $_POST['ec_option_square_location_country'] ) ), 2 ) );
			update_option( 'ec_option_square_digital_wallet', wp_easycart_admin_verification()->filter_bool_int( (int) $_POST['ec_option_square_digital_wallet'] ) );
			update_option( 'ec_option_square_merchant_name', sanitize_text_field( wp_unslash( $_POST['ec_option_square_merchant_name'] ) ) );

			$square = new ec_square();
			$square->set_currency();

			if ( !get_option( 'ec_option_square_is_sandbox' ) && isset( $_POST['ec_option_square_digital_wallet'] ) && (int) $_POST['ec_option_square_digital_wallet'] ) {
				$square->register_domain();
			}
		}

	}
endif; // End if class_exists check

function wp_easycart_admin_payments() {
	return wp_easycart_admin_payments::instance();
}
wp_easycart_admin_payments();

add_action( 'wp_ajax_ec_admin_ajax_save_third_party_selection', 'ec_admin_ajax_save_third_party_selection' );
function ec_admin_ajax_save_third_party_selection() {
	ecv2_payment_settings_guard();
	wp_easycart_admin_payments()->update_third_party_selection();
	die();
}

add_action( 'wp_ajax_ec_admin_ajax_save_direct_deposit', 'ec_admin_ajax_save_direct_deposit' );
function ec_admin_ajax_save_direct_deposit() {
	ecv2_payment_settings_guard();
	wp_easycart_admin_payments()->update_manual_billing_settings();
	die();
}

add_action( 'wp_ajax_ec_admin_ajax_save_paypal', 'ec_admin_ajax_save_paypal' );
function ec_admin_ajax_save_paypal() {
	ecv2_payment_settings_guard();
	wp_easycart_admin_payments()->update_third_party_selection();
	wp_easycart_admin_payments()->update_paypal();
	die();
}

add_action( 'wp_ajax_ec_admin_ajax_save_pro_paypal', 'ec_admin_ajax_save_pro_paypal' );
function ec_admin_ajax_save_pro_paypal() {
	ecv2_payment_settings_guard();
	wp_easycart_admin_payments()->update_third_party_selection();
	wp_easycart_admin_payments()->update_pro_paypal();
	die();
}

add_action( 'wp_ajax_ec_admin_ajax_save_stripe_connect', 'ec_admin_ajax_save_stripe_connect' );
function ec_admin_ajax_save_stripe_connect() {
	ecv2_payment_settings_guard();
	wp_easycart_admin_payments()->save_stripe_connect();
	die();
}

add_action( 'wp_ajax_ec_admin_ajax_save_stripe_connect_option', 'ec_admin_ajax_save_stripe_connect_option' );
function ec_admin_ajax_save_stripe_connect_option() {
	ecv2_payment_settings_guard();
	wp_easycart_admin_payments()->save_stripe_connect_option();
	die();
}

add_action( 'wp_ajax_ec_admin_ajax_save_square_free', 'ec_admin_ajax_save_square_free' );
function ec_admin_ajax_save_square_free() {
	ecv2_payment_settings_guard();
	wp_easycart_admin_payments()->update_square();
	die();
}