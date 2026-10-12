<?php
/**
 * Guest order lookup email ( 6.0.4 ): the link to an order, sent to the address on the order when someone typed its number and
 * that address on the order lookup form.
 *
 * Sent by wp_easycart_order_lookup::send() with $lookup_email ( order_id, url: the order's own link with its guest key, name:
 * the billing first name ), $email_logo_url, $store_page.
 *
 * Built on the shared email design ( wp_easycart_email_design ). Copy this file to your wp-easycart-data layout folder to
 * customise it; the file name must stay the same. Language strings: withdrawal / lookup_email_subject, lookup_email_intro,
 * lookup_email_button, lookup_email_ignore ( [order_id] is filled in ).
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
$ec_lk_vars  = array( 'order_id' => (int) $lookup_email['order_id'] );
$ec_lk_title = wp_easycart_withdrawals::text( 'lookup_email_subject', $ec_lk_vars );
$ec_lk_intro = wp_easycart_withdrawals::text( 'lookup_email_intro', $ec_lk_vars );

$ed::open(
	array(
		'title'     => $ec_lk_title,
		'preheader' => $ec_lk_intro,
		'logo_url'  => isset( $email_logo_url ) ? (string) $email_logo_url : (string) get_option( 'ec_option_email_logo' ),
		'store_url' => ( isset( $store_page ) && '' !== (string) $store_page ) ? (string) $store_page : null,
	)
);

$ed::section_start();
$ed::heading( esc_html( $ec_lk_title ) );
$ed::paragraph( esc_html( $ec_lk_intro ) );
$ed::section_end();

$ed::button_row(
	(string) $lookup_email['url'],
	wp_easycart_withdrawals::text( 'lookup_email_button', $ec_lk_vars ),
	array(
		'top'    => 8,
		'bottom' => 8,
		'arrow'  => true,
	)
);

$ed::section_start(
	array(
		'top'    => 16,
		'bottom' => 8,
	)
);
$ed::paragraph(
	esc_html( wp_easycart_withdrawals::text( 'lookup_email_ignore', $ec_lk_vars ) ),
	array(
		'tone'   => 'muted',
		'margin' => '0',
	)
);
$ed::section_end();

$ed::close();
