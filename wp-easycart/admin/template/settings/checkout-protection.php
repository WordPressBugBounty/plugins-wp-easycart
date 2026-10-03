<?php
/**
 * Settings › Checkout protection ( V2 declaration ).
 *
 * 6.0.2: card-testing defence ( inc/classes/core/class-wp-easycart-checkout-guard.php ). Everything here is FREE and on
 * by default at Standard. The status card, the paused-now list, the payment processor tips, the activity list and the
 * key test are drawn by admin/inc/wp_easycart_admin_checkout_protection.php through render callables; this file only
 * declares the settings. Every option is read through wp_easycart_checkout_guard::settings() with the same defaults.
 *
 * @package wp-easycart
 * @since   6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'ecst_protection_sanitize_emails' ) ) {
	/**
	 * Alert recipients: valid addresses only, comma separated.
	 *
	 * @param string $raw Typed value.
	 * @return string
	 */
	function ecst_protection_sanitize_emails( $raw ) {
		$out = array();
		foreach ( preg_split( '/[\s,;]+/', (string) $raw ) as $email ) {
			$email = sanitize_email( $email );
			if ( '' !== $email && is_email( $email ) ) {
				$out[] = $email;
			}
		}
		return implode( ', ', array_unique( $out ) );
	}
}

if ( ! function_exists( 'ecst_protection_sanitize_list' ) ) {
	/**
	 * Address and email lists: one entry per line, notes after # kept.
	 *
	 * @param string $raw Typed value.
	 * @return string
	 */
	function ecst_protection_sanitize_list( $raw ) {
		$lines = preg_split( '/\r\n|\r|\n/', (string) $raw );
		$out   = array();
		foreach ( (array) $lines as $line ) {
			$line = trim( sanitize_text_field( $line ) );
			if ( '' !== $line ) {
				$out[] = $line;
			}
		}
		return implode( "\n", array_unique( $out ) );
	}
}

if ( ! function_exists( 'ecst_protection_saved' ) ) {
	/**
	 * Any protection setting saved: forget the cached settings and the baseline.
	 */
	function ecst_protection_saved() {
		if ( class_exists( 'wp_easycart_checkout_guard' ) ) {
			wp_easycart_checkout_guard::flush_settings();
		}
		if ( function_exists( 'delete_transient' ) ) {
			delete_transient( 'wpec_cp_provider_down' );
		}
	}
}

$ecst_protection_legacy = array(
	'page'    => 'checkout-protection',
	'section' => 'Checkout protection',
	'label'   => 'New in 6.0.2',
);

return array(
	'slug'        => 'checkout-protection',
	'title'       => __( 'Checkout protection', 'wp-easycart' ),
	'description' => __( 'Stops bots from testing stolen cards at your checkout, without getting in real shoppers\' way.', 'wp-easycart' ),
	'group'       => 'cart-checkout',
	'order'       => 30,
	'icon'        => 'shield',
	'docs'        => array( 'settings', 'checkout-protection', 'checkout-protection' ),
	'legacy'      => array(),
	'upsell'      => 'default',
	'enqueue'     => array( 'wp_easycart_admin_checkout_protection', 'enqueue' ),
	'sections'    => array(

		'status'   => array(
			'title'  => __( 'Status', 'wp-easycart' ),
			'hint'   => __( 'What happened at your checkout in the last 7 days.', 'wp-easycart' ),
			'icon'   => 'shield',
			'fields' => array(),
			'render' => array( 'wp_easycart_admin_checkout_protection', 'render_status' ),
		),

		'level'    => array(
			'title'  => __( 'Protection level', 'wp-easycart' ),
			'hint'   => __( 'How many failed payments a shopper gets, and how quickly the store reacts to an attack.', 'wp-easycart' ),
			'icon'   => 'sliders',
			'fields' => array(
				'ec_option_checkout_protection_level' => array(
					'type'     => 'pills',
					'label'    => __( 'Level', 'wp-easycart' ),
					'desc'     => __( 'Standard suits almost every store. Change it only if real customers are being paused ( Relaxed ) or attacks are getting through ( Strict ).', 'wp-easycart' ),
					'default'  => 'standard',
					'options'  => array(
						'off'      => __( 'Off', 'wp-easycart' ),
						'watch'    => __( 'Watch only', 'wp-easycart' ),
						'relaxed'  => __( 'Relaxed', 'wp-easycart' ),
						'standard' => __( 'Standard', 'wp-easycart' ),
						'strict'   => __( 'Strict', 'wp-easycart' ),
						'custom'   => __( 'Custom', 'wp-easycart' ),
					),
					'on_save'  => 'ecst_protection_saved',
					'keywords' => array( 'card testing', 'fraud', 'bots', 'carding', 'rate limit', 'velocity', 'security' ),
					'legacy'   => $ecst_protection_legacy,
				),
				'ec_option_checkout_protection_custom_fails' => array(
					'type'      => 'number',
					'label'     => __( 'Failed payments per shopper', 'wp-easycart' ),
					'desc'      => __( 'From the same device, network address or email, before a pause. Most real shoppers get it right within two tries.', 'wp-easycart' ),
					'default'   => 5,
					'min'       => 1,
					'max'       => 50,
					'parent'    => 'ec_option_checkout_protection_level',
					'show_when' => 'custom',
					'on_save'   => 'ecst_protection_saved',
					'legacy'    => $ecst_protection_legacy,
				),
				'ec_option_checkout_protection_custom_window' => array(
					'type'      => 'number',
					'label'     => __( 'Counted over', 'wp-easycart' ),
					'desc'      => __( 'The time the failed payments above must happen in. A shopper also gets twice that number over a whole day.', 'wp-easycart' ),
					'default'   => 10,
					'min'       => 1,
					'max'       => 1440,
					'unit'      => __( 'minutes', 'wp-easycart' ),
					'parent'    => 'ec_option_checkout_protection_level',
					'show_when' => 'custom',
					'on_save'   => 'ecst_protection_saved',
					'legacy'    => $ecst_protection_legacy,
				),
				'ec_option_checkout_protection_custom_pause' => array(
					'type'      => 'select',
					'label'     => __( 'Then pause that shopper for', 'wp-easycart' ),
					'desc'      => __( 'They see a friendly message and can try again after this, or sooner once they pass a human check.', 'wp-easycart' ),
					'default'   => '60',
					'options'   => array(
						'30'   => __( '30 minutes', 'wp-easycart' ),
						'60'   => __( '1 hour', 'wp-easycart' ),
						'240'  => __( '4 hours', 'wp-easycart' ),
						'1440' => __( '24 hours', 'wp-easycart' ),
					),
					'parent'    => 'ec_option_checkout_protection_level',
					'show_when' => 'custom',
					'on_save'   => 'ecst_protection_saved',
					'legacy'    => $ecst_protection_legacy,
				),
				'ec_option_checkout_protection_custom_card' => array(
					'type'      => 'number',
					'label'     => __( 'Failed payments per card, per day', 'wp-easycart' ),
					'desc'      => __( 'A card that keeps failing is refused for 24 hours. Mastercard starts charging extra fees after 10 retries on one card.', 'wp-easycart' ),
					'default'   => 5,
					'min'       => 1,
					'max'       => 50,
					'parent'    => 'ec_option_checkout_protection_level',
					'show_when' => 'custom',
					'on_save'   => 'ecst_protection_saved',
					'legacy'    => $ecst_protection_legacy,
				),
				'ec_option_checkout_protection_custom_attack_count' => array(
					'type'      => 'number',
					'label'     => __( 'Treat it as an attack at', 'wp-easycart' ),
					'desc'      => __( 'Declines across the whole store, from every shopper, within 15 minutes.', 'wp-easycart' ),
					'default'   => 8,
					'min'       => 2,
					'max'       => 500,
					'unit'      => __( 'declines', 'wp-easycart' ),
					'parent'    => 'ec_option_checkout_protection_level',
					'show_when' => 'custom',
					'on_save'   => 'ecst_protection_saved',
					'legacy'    => $ecst_protection_legacy,
				),
				'ec_option_checkout_protection_custom_attack_ratio' => array(
					'type'      => 'number',
					'label'     => __( 'And at least', 'wp-easycart' ),
					'desc'      => __( 'Times your store\'s usual decline rate over the last 30 days, so a busy store doesn\'t trip it on a normal day.', 'wp-easycart' ),
					'default'   => 3,
					'min'       => 1,
					'max'       => 20,
					'unit'      => __( '× usual', 'wp-easycart' ),
					'parent'    => 'ec_option_checkout_protection_level',
					'show_when' => 'custom',
					'on_save'   => 'ecst_protection_saved',
					'legacy'    => $ecst_protection_legacy,
				),
				'ec_option_checkout_protection_code_limits' => array(
					'type'     => 'toggle',
					'label'    => __( 'Limit gift card and coupon guessing', 'wp-easycart' ),
					'desc'     => __( '10 wrong codes in 10 minutes pauses code entry for that shopper for an hour. Bots guess gift card numbers the same way they test cards.', 'wp-easycart' ),
					'default'  => 1,
					'on_save'  => 'ecst_protection_saved',
					'keywords' => array( 'gift card', 'coupon', 'brute force', 'guessing' ),
					'legacy'   => $ecst_protection_legacy,
				),
			),
			'render' => array( 'wp_easycart_admin_checkout_protection', 'render_level_explain' ),
		),

		'human'    => array(
			'title'  => __( 'Human check', 'wp-easycart' ),
			'hint'   => __( 'A quick "are you human?" check that bots can\'t pass. Usually invisible.', 'wp-easycart' ),
			'icon'   => 'check-circle',
			'fields' => array(
				'ec_option_checkout_protection_human'    => array(
					'type'     => 'pills',
					'label'    => __( 'When to ask', 'wp-easycart' ),
					'desc'     => __( 'Recommended: only when a shopper\'s payment was just declined, while your store is under attack, or when a payment didn\'t come from your checkout page. Everyone else never sees it.', 'wp-easycart' ),
					'default'  => 'smart',
					'options'  => array(
						'never'  => __( 'Never', 'wp-easycart' ),
						'smart'  => __( 'When something looks wrong', 'wp-easycart' ),
						'always' => __( 'Every payment', 'wp-easycart' ),
					),
					'on_save'  => 'ecst_protection_saved',
					'keywords' => array( 'captcha', 'turnstile', 'recaptcha', 'bot check', 'challenge' ),
					'legacy'   => $ecst_protection_legacy,
				),
				'ec_option_checkout_protection_provider' => array(
					'type'      => 'pills',
					'label'     => __( 'Check provider', 'wp-easycart' ),
					'desc'      => __( 'Cloudflare Turnstile is free with no monthly limit, usually invisible, and your site doesn\'t need to use Cloudflare. Google reCAPTCHA v2 uses the keys from Settings › Accounts; its free tier ends at 10,000 checks a month, which one attack can use up in hours.', 'wp-easycart' ),
					'default'   => 'turnstile',
					'options'   => array(
						'turnstile' => __( 'Cloudflare Turnstile', 'wp-easycart' ),
						'recaptcha' => __( 'Google reCAPTCHA', 'wp-easycart' ),
					),
					'parent'    => 'ec_option_checkout_protection_human',
					'show_when' => array( 'smart', 'always' ),
					'on_save'   => 'ecst_protection_saved',
					'legacy'    => $ecst_protection_legacy,
				),
				'ec_option_checkout_protection_turnstile_site_key' => array(
					'type'        => 'text',
					'label'       => __( 'Turnstile site key', 'wp-easycart' ),
					'desc'        => __( 'From your free Cloudflare account: Turnstile › Add widget, add this site\'s domain, Managed mode. Copy the site key and the secret key here.', 'wp-easycart' ),
					'placeholder' => '0x4AAAAAAA…',
					'parent'      => 'ec_option_checkout_protection_provider',
					'show_when'   => 'turnstile',
					'on_save'     => 'ecst_protection_saved',
					'legacy'      => $ecst_protection_legacy,
				),
				'ec_option_checkout_protection_turnstile_secret_key' => array(
					'type'      => 'password',
					'label'     => __( 'Turnstile secret key', 'wp-easycart' ),
					'desc'      => __( 'Stays on your server; it is never shown to shoppers.', 'wp-easycart' ),
					'parent'    => 'ec_option_checkout_protection_provider',
					'show_when' => 'turnstile',
					'on_save'   => 'ecst_protection_saved',
					'legacy'    => $ecst_protection_legacy,
				),
				'ecst_protection_keys_status'            => array(
					'type'   => 'html',
					'render' => array( 'wp_easycart_admin_checkout_protection', 'render_keys_status' ),
				),
			),
			'render' => array( 'wp_easycart_admin_checkout_protection', 'render_human_explain' ),
		),

		'attack'   => array(
			'title'   => __( 'During an attack', 'wp-easycart' ),
			'hint'    => __( 'What the store does by itself when declines spike.', 'wp-easycart' ),
			'icon'    => 'alert',
			'fields'  => array(
				'ec_option_checkout_protection_attack_limit' => array(
					'type'    => 'number',
					'label'   => __( 'Failed payments allowed during an attack', 'wp-easycart' ),
					'desc'    => __( 'Per shopper, before a 1-hour pause. With the human check on, bots are stopped by the check, so this mainly protects real customers who mistype. We recommend 5.', 'wp-easycart' ),
					'default' => 5,
					'min'     => 1,
					'max'     => 20,
					'on_save' => 'ecst_protection_saved',
					'legacy'  => $ecst_protection_legacy,
				),
				'ec_option_checkout_protection_attack_minutes' => array(
					'type'    => 'pills',
					'label'   => __( 'Keep extra checks on for', 'wp-easycart' ),
					'desc'    => __( 'After the last sign of an attack. Then everything goes back to normal by itself.', 'wp-easycart' ),
					'default' => '60',
					'options' => array(
						'30'   => __( '30 min', 'wp-easycart' ),
						'60'   => __( '1 hour', 'wp-easycart' ),
						'240'  => __( '4 hours', 'wp-easycart' ),
						'1440' => __( '24 hours', 'wp-easycart' ),
					),
					'on_save' => 'ecst_protection_saved',
					'legacy'  => $ecst_protection_legacy,
				),
				'ec_option_checkout_protection_alerts' => array(
					'type'    => 'toggle',
					'label'   => __( 'Email me when an attack starts and ends', 'wp-easycart' ),
					'desc'    => __( 'One email when it starts, with what to do, and a short summary when it\'s over. Never more than one alert an hour.', 'wp-easycart' ),
					'default' => 1,
					'on_save' => 'ecst_protection_saved',
					'legacy'  => $ecst_protection_legacy,
				),
				'ec_option_checkout_protection_alert_emails' => array(
					'type'        => 'text',
					'label'       => __( 'Send alerts to', 'wp-easycart' ),
					'desc'        => __( 'Leave empty to use your order notification addresses from Settings › Email, or the WordPress admin email if there are none. Addresses entered here replace both.', 'wp-easycart' ),
					'placeholder' => __( 'Your order notification addresses', 'wp-easycart' ),
					'parent'      => 'ec_option_checkout_protection_alerts',
					'show_when'   => '1',
					'sanitize'    => 'ecst_protection_sanitize_emails',
					'on_save'     => 'ecst_protection_saved',
					'keywords'    => array( 'alert email', 'notification' ),
					'legacy'      => $ecst_protection_legacy,
				),
				'ec_option_checkout_protection_flag_orders' => array(
					'type'    => 'toggle',
					'label'   => __( 'Flag orders that might be card tests', 'wp-easycart' ),
					'desc'    => __( 'A payment that goes through during an attack, from a shopper who had declines first, gets a "Possible card test" note on the order. Refunding these quickly stops them turning into disputes.', 'wp-easycart' ),
					'default' => 1,
					'on_save' => 'ecst_protection_saved',
					'legacy'  => $ecst_protection_legacy,
				),
			),
			'actions' => array(
				array(
					'id'       => 'test_alert',
					'label'    => __( 'Send a test alert', 'wp-easycart' ),
					'desc'     => __( 'Emails the alert recipients the message they would get when an attack starts, marked as a test.', 'wp-easycart' ),
					'button'   => __( 'Send test', 'wp-easycart' ),
					'callback' => array( 'wp_easycart_admin_checkout_protection', 'action_test_alert' ),
				),
			),
		),

		'shopper'  => array(
			'title'  => __( 'What shoppers see', 'wp-easycart' ),
			'hint'   => __( 'The wording at checkout when a payment is declined or paused.', 'wp-easycart' ),
			'icon'   => 'message',
			'fields' => array(
				'ec_option_checkout_protection_declines' => array(
					'type'    => 'pills',
					'label'   => __( 'Declined payment message', 'wp-easycart' ),
					'desc'    => __( 'Card testers use the bank\'s reason to learn about a card. Simple tells real shoppers what to do without giving that away. Reasons like "stolen card" are never shown, whichever you pick.', 'wp-easycart' ),
					'default' => 'simple',
					'options' => array(
						'simple' => __( 'Simple', 'wp-easycart' ),
						'bank'   => __( 'Bank\'s reason', 'wp-easycart' ),
					),
					'on_save' => 'ecst_protection_saved',
					'legacy'  => $ecst_protection_legacy,
				),
				'ecst_protection_wording'                => array(
					'type'   => 'html',
					'render' => array( 'wp_easycart_admin_checkout_protection', 'render_wording' ),
				),
			),
		),

		'lists'    => array(
			'title'  => __( 'Trusted & blocked', 'wp-easycart' ),
			'hint'   => __( 'People and places that skip the limits, or never get through.', 'wp-easycart' ),
			'icon'   => 'users',
			'fields' => array(
				'ec_option_checkout_protection_trust_customers' => array(
					'type'    => 'toggle',
					'label'   => __( 'Trust returning customers', 'wp-easycart' ),
					'desc'    => __( 'Signed-in customers who have paid you before get double the limits, and no human check unless there\'s an attack. Trust comes only from a signed-in account, never from an email address: anyone can type an email.', 'wp-easycart' ),
					'default' => 1,
					'on_save' => 'ecst_protection_saved',
					'legacy'  => $ecst_protection_legacy,
				),
				'ec_option_checkout_protection_allow_list' => array(
					'type'        => 'textarea',
					'label'       => __( 'Never limit these network addresses', 'wp-easycart' ),
					'desc'        => __( 'One per line, for your office or a phone-order desk. Ranges like 198.51.100.0/24 work, and anything after # is a note. Store staff who are signed in are never limited.', 'wp-easycart' ),
					'placeholder' => '198.51.100.24 # Office',
					'sanitize'    => 'ecst_protection_sanitize_list',
					'on_save'     => 'ecst_protection_saved',
					'keywords'    => array( 'whitelist', 'allowlist', 'ip address' ),
					'legacy'      => $ecst_protection_legacy,
				),
				'ec_option_checkout_protection_block_list' => array(
					'type'        => 'textarea',
					'label'       => __( 'Always block', 'wp-easycart' ),
					'desc'        => __( 'One per line: network addresses or ranges, email addresses, or whole email domains written as @example.com. Blocked shoppers only ever see the simple declined message, so bots learn nothing.', 'wp-easycart' ),
					'placeholder' => "203.0.113.0/24\n@example.com",
					'sanitize'    => 'ecst_protection_sanitize_list',
					'on_save'     => 'ecst_protection_saved',
					'keywords'    => array( 'blacklist', 'blocklist', 'ban', 'ip address' ),
					'legacy'      => $ecst_protection_legacy,
				),
			),
			'render' => array( 'wp_easycart_admin_checkout_protection', 'render_paused' ),
		),

		'gateway'  => array(
			'title'  => __( 'Your payment processor', 'wp-easycart' ),
			'hint'   => __( 'Settings in your gateway\'s own dashboard that add a second layer.', 'wp-easycart' ),
			'icon'   => 'credit-card',
			'fields' => array(),
			'render' => array( 'wp_easycart_admin_checkout_protection', 'render_gateway_tips' ),
		),

		'activity' => array(
			'title'  => __( 'Activity', 'wp-easycart' ),
			'hint'   => __( 'Every stop, decline, check and attack, with the reason.', 'wp-easycart' ),
			'icon'   => 'activity',
			'fields' => array(),
			'render' => array( 'wp_easycart_admin_checkout_protection', 'render_activity' ),
		),

		'advanced' => array(
			'title'   => __( 'Advanced', 'wp-easycart' ),
			'hint'    => __( 'You shouldn\'t need to change these.', 'wp-easycart' ),
			'icon'    => 'tool',
			'fields'  => array(
				'ec_option_checkout_protection_proxy'     => array(
					'type'    => 'pills',
					'label'   => __( 'How visitors reach your site', 'wp-easycart' ),
					'desc'    => __( 'We need each visitor\'s real network address. Automatic trusts Cloudflare only from Cloudflare\'s own servers. Choose Another proxy only if your host told you to: a wrong choice lets bots fake their address.', 'wp-easycart' ),
					'default' => 'auto',
					'options' => array(
						'auto'   => __( 'Automatic', 'wp-easycart' ),
						'direct' => __( 'Direct', 'wp-easycart' ),
						'proxy'  => __( 'Another proxy', 'wp-easycart' ),
					),
					'on_save' => 'ecst_protection_saved',
					'legacy'  => $ecst_protection_legacy,
				),
				'ec_option_checkout_protection_proxy_ips' => array(
					'type'        => 'textarea',
					'label'       => __( 'Your proxy\'s addresses', 'wp-easycart' ),
					'desc'        => __( 'One per line. Only requests from these addresses may say who the visitor really is.', 'wp-easycart' ),
					'placeholder' => '10.0.0.0/8',
					'parent'      => 'ec_option_checkout_protection_proxy',
					'show_when'   => 'proxy',
					'sanitize'    => 'ecst_protection_sanitize_list',
					'on_save'     => 'ecst_protection_saved',
					'legacy'      => $ecst_protection_legacy,
				),
				'ecst_protection_detected_ip'             => array(
					'type'   => 'html',
					'render' => array( 'wp_easycart_admin_checkout_protection', 'render_detected_ip' ),
				),
				'ec_option_checkout_protection_require_page' => array(
					'type'    => 'toggle',
					'label'   => __( 'Payments must come from your checkout page', 'wp-easycart' ),
					'desc'    => __( 'Payments sent by bots that never opened your checkout get a human check, or are refused when no check is set up. Safe with caching plugins: the checkout always loads fresh.', 'wp-easycart' ),
					'default' => 1,
					'on_save' => 'ecst_protection_saved',
					'legacy'  => $ecst_protection_legacy,
				),
				'ec_option_checkout_protection_retention' => array(
					'type'    => 'pills',
					'label'   => __( 'Keep activity for', 'wp-easycart' ),
					'desc'    => __( 'Older records are deleted every day. Network addresses and emails are stored hashed either way.', 'wp-easycart' ),
					'default' => '30',
					'options' => array(
						'7'  => __( '7 days', 'wp-easycart' ),
						'30' => __( '30 days', 'wp-easycart' ),
						'90' => __( '90 days', 'wp-easycart' ),
					),
					'on_save' => 'ecst_protection_saved',
					'legacy'  => $ecst_protection_legacy,
				),
			),
			'actions' => array(
				array(
					'id'       => 'reset',
					'label'    => __( 'Reset to recommended', 'wp-easycart' ),
					'desc'     => __( 'Standard level, the smart human check, 5 tries and 1 hour during an attack, alerts on. Keeps your keys and lists.', 'wp-easycart' ),
					'button'   => __( 'Reset', 'wp-easycart' ),
					'confirm'  => __( 'Put checkout protection back to the recommended settings? Your keys and lists stay.', 'wp-easycart' ),
					'callback' => array( 'wp_easycart_admin_checkout_protection', 'action_reset' ),
				),
				array(
					'id'       => 'clear_pauses',
					'label'    => __( 'Clear all pauses', 'wp-easycart' ),
					'desc'     => __( 'Lets every paused shopper, address and card try again straight away.', 'wp-easycart' ),
					'button'   => __( 'Clear pauses', 'wp-easycart' ),
					'confirm'  => __( 'Unpause everyone now?', 'wp-easycart' ),
					'danger'   => true,
					'callback' => array( 'wp_easycart_admin_checkout_protection', 'action_clear_pauses' ),
				),
			),
		),
	),
);
