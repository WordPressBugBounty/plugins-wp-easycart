<?php
/**
 * Subscription renewal reminder email ( 6.0.3 ).
 *
 * Sent N days ( 30 ) before a subscription billed yearly or less often renews, by wp_easycart_subscription_reminders::build(), with:
 *   $reminder ( kind, subscription_id, title, first_name, date, at, amount, amount_text, period_text, price_text, link ),
 *   $email_logo_url, $store_page.
 *
 * Built on the shared email design ( wp_easycart_email_design ). Copy this file to your wp-easycart-data layout folder to customise
 * it; the file name must stay the same. Language strings: subscription_upcoming / renewal_reminder_email_title, renewal_reminder_message,
 * renewal_reminder_plan, renewal_reminder_date, renewal_reminder_price, renewal_reminder_cancel, renewal_reminder_link ( [title],
 * [date], [price] and [name] are filled in ).
 *
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'wp_easycart_email_design' ) ) {
	require_once EC_PLUGIN_DIRECTORY . '/inc/classes/core/class-wp-easycart-email-design.php';
}

$ed           = 'wp_easycart_email_design';
$ec_rem       = 'wp_easycart_subscription_reminders';
$ec_rem_title = $ec_rem::text( 'subscription_upcoming', 'renewal_reminder_email_title', $reminder );
$ec_rem_intro = $ec_rem::text( 'subscription_upcoming', 'renewal_reminder_message', $reminder );

$ed::open(
	array(
		'title'     => $ec_rem_title,
		'preheader' => $ec_rem_intro,
		'logo_url'  => isset( $email_logo_url ) ? (string) $email_logo_url : (string) get_option( 'ec_option_email_logo' ),
		'store_url' => ( isset( $store_page ) && '' !== (string) $store_page ) ? (string) $store_page : null,
	)
);

$ed::section_start();
$ed::heading( esc_html( $ec_rem_title ) );
$ed::paragraph( esc_html( $ec_rem_intro ) );
$ed::section_end();

$ed::section_start( array( 'top' => 0 ) );
$ed::card_start();
echo '<div style="' . esc_attr( $ed::css( 'label' ) ) . '">' . esc_html( $ec_rem::text( 'subscription_upcoming', 'renewal_reminder_plan', $reminder ) ) . '</div>';
echo '<div style="' . esc_attr( $ed::css( 'strong' ) ) . 'font-size:15px;margin-bottom:8px;">' . esc_html( $reminder['title'] ) . '</div>';
$ed::key_values(
	array(
		array(
			'label' => esc_html( $ec_rem::text( 'subscription_upcoming', 'renewal_reminder_date', $reminder ) ),
			'value' => esc_html( $reminder['date'] ),
		),
		array(
			'label' => esc_html( $ec_rem::text( 'subscription_upcoming', 'renewal_reminder_price', $reminder ) ),
			'value' => $ed::ltr( $reminder['price_text'] ),
		),
	)
);
$ed::card_end();
$ed::section_end();

$ed::section_start( array( 'top' => 16 ) );
$ed::paragraph(
	esc_html( $ec_rem::text( 'subscription_upcoming', 'renewal_reminder_cancel', $reminder ) ),
	array(
		'tone'   => 'muted',
		'margin' => '0',
	)
);
$ed::section_end();

if ( '' !== (string) $reminder['link'] ) {
	$ed::button_row(
		$reminder['link'],
		$ec_rem::text( 'subscription_upcoming', 'renewal_reminder_link', $reminder ),
		array(
			'top'    => 16,
			'bottom' => 8,
			'arrow'  => true,
		)
	);
}

$ed::close();
