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

return array(
	'slug'        => 'account',
	'title'       => __( 'Accounts', 'wp-easycart' ),
	'description' => __( 'What shoppers must provide to register, what they can do from their account, and spam protection.', 'wp-easycart' ),
	'group'       => 'store-setup',
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
			'title'  => __( 'Spam protection', 'wp-easycart' ),
			'hint'   => __( 'Google reCAPTCHA v2 on the account and checkout forms', 'wp-easycart' ),
			'fields' => array(
				'ec_option_enable_recaptcha' => array(
					'type'     => 'toggle',
					'label'    => __( 'Google reCAPTCHA v2', 'wp-easycart' ),
					'desc'     => __( 'Adds a reCAPTCHA challenge to registration and login. Needs a site key and secret key from your Google reCAPTCHA account.', 'wp-easycart' ),
					'keywords' => array( 'captcha', 'bots', 'spam', 'google' ),
					'legacy'   => array( 'page' => 'account', 'section' => 'Account Options', 'label' => 'Google Recaptcha V2' ),
				),
				'ec_option_recaptcha_site_key' => array(
					'type'        => 'text',
					'label'       => __( 'Site key', 'wp-easycart' ),
					'desc'        => __( 'From your Google reCAPTCHA admin console. One key pair per site URL.', 'wp-easycart' ),
					'placeholder' => '6Lc…',
					'parent'      => 'ec_option_enable_recaptcha',
					'keywords'    => array( 'captcha', 'google' ),
					'legacy'      => array( 'page' => 'account', 'section' => 'Account Options', 'label' => 'Google Recaptcha: Site Key' ),
				),
				'ec_option_recaptcha_secret_key' => array(
					'type'        => 'password',
					'label'       => __( 'Secret key', 'wp-easycart' ),
					'desc'        => __( 'Keep this private. It is only sent to Google from your server.', 'wp-easycart' ),
					'parent'      => 'ec_option_enable_recaptcha',
					'keywords'    => array( 'captcha', 'google' ),
					'legacy'      => array( 'page' => 'account', 'section' => 'Account Options', 'label' => 'Google Recaptcha: Secret Key' ),
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
