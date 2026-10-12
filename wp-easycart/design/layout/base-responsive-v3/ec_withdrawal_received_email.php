<?php
/**
 * The withdrawal receipt email ( 6.0.4 ): sent the moment a shopper confirms a withdrawal, on a durable medium, with what they
 * sent and the date and time it was received ( Article 11a(4) of the Consumer Rights Directive ). It confirms receipt only.
 *
 * Sent by wp_easycart_withdrawals::send_receipt() with $withdrawal_email ( record: the statement row; order_ref; name; rows:
 * label / value pairs of the statement, the time received last; next_steps: the store's "What happens next" text ),
 * $email_logo_url, $store_page.
 *
 * Built on the shared email design ( wp_easycart_email_design ). Copy this file to your wp-easycart-data layout folder to
 * customise it; the file name must stay the same. Language strings: withdrawal / ack_subject, ack_intro, received_title,
 * received_next, ack_footer ( [name] and [order_id] are filled in ).
 *
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- a template: it is included inside a method, so its variables are that method's locals.
if ( ! class_exists( 'wp_easycart_email_design' ) ) {
	require_once EC_PLUGIN_DIRECTORY . '/inc/classes/core/class-wp-easycart-email-design.php';
}

$ed          = 'wp_easycart_email_design';
$ec_wd_vars  = array(
	'name'     => $withdrawal_email['name'],
	'order_id' => $withdrawal_email['order_ref'],
);
$ec_wd_title = wp_easycart_withdrawals::text( 'received_title', $ec_wd_vars );
$ec_wd_intro = wp_easycart_withdrawals::text( 'ack_intro', $ec_wd_vars );

$ed::open(
	array(
		'title'     => wp_easycart_withdrawals::text( 'ack_subject', $ec_wd_vars ),
		'preheader' => $ec_wd_intro,
		'logo_url'  => isset( $email_logo_url ) ? (string) $email_logo_url : (string) get_option( 'ec_option_email_logo' ),
		'store_url' => ( isset( $store_page ) && '' !== (string) $store_page ) ? (string) $store_page : null,
	)
);

$ed::section_start();
$ed::heading( esc_html( $ec_wd_title ) );
$ed::paragraph( esc_html( $ec_wd_intro ) );
$ed::section_end();

$ed::section_start( array( 'top' => 0 ) );
$ed::card_start();
foreach ( (array) $withdrawal_email['rows'] as $ec_wd_row ) {
	echo '<div style="' . esc_attr( $ed::css( 'label' ) ) . '">' . esc_html( $ec_wd_row['label'] ) . '</div>';
	echo '<div style="' . esc_attr( $ed::css( 'text' ) ) . 'margin:0 0 10px 0;">' . nl2br( esc_html( $ec_wd_row['value'] ) ) . '</div>';
}
$ed::card_end();
$ed::section_end();

if ( '' !== (string) $withdrawal_email['next_steps'] ) {
	$ed::section_start( array( 'top' => 16 ) );
	$ed::label( esc_html( wp_easycart_withdrawals::text( 'received_next', $ec_wd_vars ) ) );
	$ed::paragraph( nl2br( esc_html( (string) $withdrawal_email['next_steps'] ) ), array( 'margin' => '0' ) );
	$ed::section_end();
}

$ed::section_start(
	array(
		'top'    => 16,
		'bottom' => 8,
	)
);
$ed::paragraph(
	esc_html( wp_easycart_withdrawals::text( 'ack_footer', $ec_wd_vars ) ),
	array(
		'tone'   => 'muted',
		'margin' => '0',
	)
);
$ed::section_end();

$ed::close();
