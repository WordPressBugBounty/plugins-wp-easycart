<?php
/**
 * Subscription payment failed email.
 *
 * Included by ec_orderdisplay::send_failed_payment(), running as $this ( ec_orderdisplay for the failed-payment order,
 * built with order details ). The same message goes to the customer, the order's second address and the store.
 * Variables from the sender: $subscription ( ec_subscription row ), $email_logo_url, $store_page, $permalink_divider.
 *
 * On the shared email design since 6.0.0 ( wp_easycart_email_design, inc/classes/core/class-wp-easycart-email-design.php ):
 * a red banner, the subscription / order / amount in a card and an "update billing" button to the subscription page
 * ( same link as before ). Copy this file to your theme / wp-easycart-data layout folder to customise it; the file name
 * must stay the same.
 *
 * Language strings kept: ec_errors › subscription_payment_failed_title, subscription_payment_failed_text,
 *   subscription_payment_failed_link.
 *
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'wp_easycart_email_design' ) ) {
	require_once EC_PLUGIN_DIRECTORY . '/inc/classes/core/class-wp-easycart-email-design.php';
}

$ed              = 'wp_easycart_email_design';
$ec_failed_lang  = wp_easycart_language();
$ec_failed_title = ( isset( $this->orderdetails[0] ) && isset( $this->orderdetails[0]->title ) ) ? (string) $this->orderdetails[0]->title : '';
if ( '' === $ec_failed_title && isset( $subscription ) && is_object( $subscription ) && isset( $subscription->title ) ) {
	$ec_failed_title = (string) $subscription->title;
}
$ec_failed_name   = trim( $this->billing_first_name . ' ' . $this->billing_last_name );
$ec_failed_link   = wpeasycart_links()->get_account_page( 'subscription_details', array( 'subscription_id' => (int) $this->subscription_id ) );
$ec_failed_amount = ( isset( $this->grand_total ) && $this->grand_total > 0 ) ? $ed::money( $this->grand_total ) : '';

$ed::open(
	array(
		'title'     => wp_strip_all_tags( $ec_failed_lang->get_text( 'ec_errors', 'subscription_payment_failed_title' ) ),
		'preheader' => wp_strip_all_tags( $ec_failed_lang->get_text( 'ec_errors', 'subscription_payment_failed_text' ) ),
		'logo_url'  => (string) $email_logo_url,
		'store_url' => (string) $store_page,
	)
);

$ed::notice( wp_kses_post( $ec_failed_lang->get_text( 'ec_errors', 'subscription_payment_failed_title' ) ), 'danger' );

$ed::section_start( array( 'top' => 20 ) );
$ed::paragraph( wp_kses_post( $ec_failed_lang->get_text( 'ec_errors', 'subscription_payment_failed_text' ) ) );
$ed::card_start();
if ( '' !== $ec_failed_title || '' !== $ec_failed_name ) {
	$ed::paragraph(
		esc_html( $ec_failed_title ) . ( ( '' !== $ec_failed_title && '' !== $ec_failed_name ) ? ' &ndash; ' : '' ) . esc_html( $ec_failed_name ),
		array(
			'tone'   => 'strong',
			'margin' => '0 0 8px 0',
		)
	);
}
$ed::key_values(
	array(
		array(
			'label' => wp_kses_post( rtrim( trim( $ec_failed_lang->get_text( 'account_order_details', 'account_orders_details_order_number' ) ), ':' ) ),
			'value' => ( (int) $this->order_id > 0 ) ? $ed::ltr( $this->order_id ) : '',
		),
		array(
			'label' => wp_kses_post( $ec_failed_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_grand_total' ) ),
			'value' => $ec_failed_amount,
		),
	)
);
$ed::button(
	$ec_failed_link,
	wp_strip_all_tags( $ec_failed_lang->get_text( 'ec_errors', 'subscription_payment_failed_link' ) ),
	array(
		'margin' => ( '' !== $ec_failed_title || '' !== $ec_failed_name || (int) $this->order_id > 0 || '' !== $ec_failed_amount ) ? '14px 0 0 0' : '0',
		'arrow'  => true,
	)
);
$ed::card_end();
$ed::section_end();
$ed::close();
