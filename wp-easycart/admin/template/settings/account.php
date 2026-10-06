<?php
/**
 * Settings › Accounts ( V2 declaration ).
 *
 * Reference conversion. Every other page follows this shape; see
 * wp_easycart_admin_settings_registry for the full field vocabulary.
 *
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'ecst_account_render_recaptcha_test' ) ) {
	/**
	 * The reCAPTCHA key test row ( 6.0.3, wp_easycart_admin_recaptcha ): where the saved keys stand and a Test keys button.
	 *
	 * @since 6.0.3
	 * @param array $field Field declaration.
	 * @param array $page  Page declaration.
	 * @return void
	 */
	function ecst_account_render_recaptcha_test( $field, $page = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- settings render callable signature.
		if ( class_exists( 'wp_easycart_admin_recaptcha' ) ) {
			wp_easycart_admin_recaptcha::print_row();
		}
	}
}

if ( ! function_exists( 'ecst_account_recaptcha_switch' ) ) {
	/**
	 * Sanitize for the reCAPTCHA switch ( 6.0.3 ): it turns on only once the saved keys passed a test, so keys that don't
	 * work never lock shoppers out of signing in. Turning it off is always allowed.
	 *
	 * @since 6.0.3
	 * @param string $raw   Posted value.
	 * @param array  $field Field declaration.
	 * @return int|WP_Error
	 */
	function ecst_account_recaptcha_switch( $raw, $field = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- settings sanitize callable signature.
		$on = in_array( (string) $raw, array( '1', 'true', 'on' ), true ) ? 1 : 0;
		if ( $on && function_exists( 'wp_easycart_recaptcha_verified' ) && ! wp_easycart_recaptcha_verified() ) {
			return new WP_Error( 'recaptcha_untested', __( 'Test your keys first: reCAPTCHA switches on once the saved keys pass the test above.', 'wp-easycart' ) );
		}
		return $on;
	}
}

if ( ! function_exists( 'ecst_account_recaptcha_keys_saved' ) ) {
	/**
	 * A reCAPTCHA key was saved ( 6.0.3 ): a pass recorded for other keys no longer counts, and new keys are never taken for
	 * keys that were already in use before the update.
	 *
	 * @since 6.0.3
	 * @param string $value New value.
	 * @param string $old   Previous value.
	 * @param array  $field Field declaration.
	 * @return void
	 */
	function ecst_account_recaptcha_keys_saved( $value, $old, $field = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- settings on_save callable signature.
		if ( trim( (string) $value ) === trim( (string) $old ) ) {
			return;
		}
		$record      = get_option( 'ec_option_recaptcha_verified', false );
		$fingerprint = function_exists( 'wp_easycart_recaptcha_fingerprint' ) ? wp_easycart_recaptcha_fingerprint() : '';
		if ( ! is_array( $record ) || empty( $record['fingerprint'] ) || $record['fingerprint'] !== $fingerprint ) {
			update_option( 'ec_option_recaptcha_verified', array(), false );
		}
	}
}

return array(
	'slug'        => 'account',
	'title'       => __( 'Accounts', 'wp-easycart' ),
	'description' => __( 'What shoppers must provide to register, what they can do from their account, and spam protection.', 'wp-easycart' ),
	'group'       => 'store',
	'order'       => 30,
	'icon'        => 'admin-users',
	'docs'        => array( 'settings', 'accounts', 'settings' ),
	'legacy'      => array( 'account' ),
	'upsell'      => 'default',
	'sections'    => array(

		'registration' => array(
			'title'  => __( 'Registration', 'wp-easycart' ),
			'hint'   => __( 'What a shopper must do to create an account', 'wp-easycart' ),
			'fields' => array(
				'ec_option_require_account_terms' => array(
					'type'     => 'toggle',
					'label'    => __( 'Require terms agreement', 'wp-easycart' ),
					'desc'     => __( 'Adds a checkbox to registration that shoppers must tick. Required for GDPR and CCPA compliance.', 'wp-easycart' ),
					'keywords' => array( 'gdpr', 'ccpa', 'privacy', 'legal' ),
					'legacy'   => array( 'page' => 'account', 'section' => 'Account Options', 'label' => 'Require Terms Agreement' ),
				),
				'ec_option_require_account_address' => array(
					'type'     => 'toggle',
					'label'    => __( 'Require a billing address', 'wp-easycart' ),
					'desc'     => __( 'Shoppers enter their billing address when they register, not just at checkout.', 'wp-easycart' ),
					'legacy'   => array( 'page' => 'account', 'section' => 'Account Options', 'label' => 'Require Billing for Account' ),
				),
				'ec_option_require_email_validation' => array(
					'type'     => 'toggle',
					'label'    => __( 'Require email confirmation', 'wp-easycart' ),
					'desc'     => __( 'New accounts stay inactive until the shopper clicks the link in the activation email.', 'wp-easycart' ),
					'keywords' => array( 'validation', 'activate', 'activation' ),
					'legacy'   => array( 'page' => 'account', 'section' => 'Account Options', 'label' => 'Email Validation' ),
				),
				'ec_option_enable_user_notes' => array(
					'type'     => 'toggle',
					'label'    => __( 'Notes field on registration', 'wp-easycart' ),
					'desc'     => __( 'Adds a free-text notes box to the registration form. Staff see the notes on the customer record.', 'wp-easycart' ),
					'advanced' => true,
					'legacy'   => array( 'page' => 'account', 'section' => 'Account Options', 'label' => 'User Notes' ),
				),
				'ec_option_show_subscriber_feature' => array(
					'type'     => 'toggle',
					'label'    => __( '"Subscribe to newsletter" checkbox', 'wp-easycart' ),
					'desc'     => __( 'Shown on registration and at checkout. Subscribers appear under Users › Subscribers.', 'wp-easycart' ),
					'keywords' => array( 'newsletter', 'subscribers', 'mailing list' ),
					'legacy'   => array( 'page' => 'account', 'section' => 'Account Options', 'label' => 'Subscribe to Newsletter' ),
				),
			),
		),

		'account-page' => array(
			'title'  => __( 'Account page', 'wp-easycart' ),
			'hint'   => __( 'What a signed-in shopper can manage', 'wp-easycart' ),
			'fields' => array(
				'ec_option_show_account_subscriptions_link' => array(
					'type'     => 'toggle',
					'label'    => __( 'Let shoppers manage their subscriptions', 'wp-easycart' ),
					'desc'     => __( 'Adds a Subscriptions item to the account menu where shoppers can update cards or cancel.', 'wp-easycart' ),
					'keywords' => array( 'subscription', 'recurring', 'cancel' ),
					'legacy'   => array( 'page' => 'account', 'section' => 'Account Options', 'label' => 'Manage Subscriptions' ),
				),
				'ec_subscriptions_use_first_order_details' => array(
					'type'     => 'toggle',
					'label'    => __( 'Renewals use the first order’s addresses', 'wp-easycart' ),
					'desc'     => __( 'Subscription renewals copy billing and shipping from the original order instead of the current account details.', 'wp-easycart' ),
					'advanced' => true,
					'keywords' => array( 'subscription', 'renewal', 'billing', 'shipping' ),
					'legacy'   => array( 'page' => 'account', 'section' => 'Account Options', 'label' => 'Subscription Renewal: Use First Order Details' ),
				),
			),
		),

		'spam-protection' => array(
			'title'   => __( 'Spam protection', 'wp-easycart' ),
			'hint'    => __( 'Google reCAPTCHA v2 on the account and checkout forms', 'wp-easycart' ),
			/* 6.0.3: the key test's script and styles ( wp_easycart_admin_recaptcha ). */
			'enqueue' => array( 'wp_easycart_admin_recaptcha', 'enqueue' ),
			'fields'  => array(
				/* 6.0.3: keys first, then a test with them, then the switch, which turns on only once the test passed. */
				'ec_option_recaptcha_site_key' => array(
					'type'        => 'text',
					'label'       => __( 'Site key', 'wp-easycart' ),
					'desc'        => __( 'From your Google reCAPTCHA admin console: a v2 “I’m not a robot” key that lists this site’s domain.', 'wp-easycart' ),
					'placeholder' => '6Lc…',
					'on_save'     => 'ecst_account_recaptcha_keys_saved',
					'keywords'    => array( 'captcha', 'google' ),
					'legacy'      => array( 'page' => 'account', 'section' => 'Account Options', 'label' => 'Google Recaptcha: Site Key' ),
				),
				'ec_option_recaptcha_secret_key' => array(
					'type'        => 'password',
					'label'       => __( 'Secret key', 'wp-easycart' ),
					'desc'        => __( 'Keep this private. It is only sent to Google from your server.', 'wp-easycart' ),
					'on_save'     => 'ecst_account_recaptcha_keys_saved',
					'keywords'    => array( 'captcha', 'google' ),
					'legacy'      => array( 'page' => 'account', 'section' => 'Account Options', 'label' => 'Google Recaptcha: Secret Key' ),
				),
				'ecst_recaptcha_test' => array(
					'type'     => 'html',
					'label'    => __( 'Test your keys', 'wp-easycart' ),
					'render'   => 'ecst_account_render_recaptcha_test',
					'keywords' => array( 'captcha', 'keys', 'test', 'verify' ),
					'legacy'   => array( 'page' => 'account', 'section' => 'Account Options', 'label' => 'New in 6.0.3' ),
				),
				'ec_option_enable_recaptcha' => array(
					'type'     => 'toggle',
					'label'    => __( 'Google reCAPTCHA v2', 'wp-easycart' ),
					'desc'     => __( 'Adds a reCAPTCHA challenge to registration and login. It switches on once your keys pass the test above.', 'wp-easycart' ),
					'sanitize' => 'ecst_account_recaptcha_switch',
					'keywords' => array( 'captcha', 'bots', 'spam', 'google' ),
					'legacy'   => array( 'page' => 'account', 'section' => 'Account Options', 'label' => 'Google Recaptcha V2' ),
				),
				'ec_option_enable_recaptcha_cart' => array(
					'type'     => 'toggle',
					'label'    => __( 'Also protect checkout', 'wp-easycart' ),
					'desc'     => __( 'Shows the challenge on the checkout login and details forms too.', 'wp-easycart' ),
					'parent'   => 'ec_option_enable_recaptcha',
					'keywords' => array( 'captcha', 'checkout' ),
					'legacy'   => array( 'page' => 'account', 'section' => 'Account Options', 'label' => 'Enable in Cart' ),
				),
			),
		),
	),
);
