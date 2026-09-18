<?php
/**
 * Subscription renewing soon email ( Stripe invoice.upcoming ).
 *
 * Included by ec_subscription::send_subscription_upcoming_payment_email( $subscription, $user, $webhook_data )
 * ( runs as $this = ec_subscription ) with:
 *   $this->title, $subscription ( subscription_id ), $user, $webhook_data, $total ( formatted amount ),
 *   $date ( formatted date or false ), $email_logo_url. $store_page is not set by the sender ( the store page is used ).
 *
 * 6.0.0: rebuilt on the shared email design ( wp_easycart_email_design, inc/classes/core/class-wp-easycart-email-design.php ).
 * The charge line stays the [total] / [date] language string; the payment method link is now a button ( same account page
 * URL ). Copy this file to your wp-easycart-data layout folder to customise it; the file name must stay the same.
 *
 * Language strings kept: subscription_upcoming / subscription_upcoming_email_title, upcoming_message_1, upcoming_message_2,
 * upcoming_details, upcoming_message_link.
 *
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'wp_easycart_email_design' ) ) {
	require_once EC_PLUGIN_DIRECTORY . '/inc/classes/core/class-wp-easycart-email-design.php';
}

$ed            = 'wp_easycart_email_design';
$ec_sub_lang   = wp_easycart_language();
$ec_sub_title  = $ec_sub_lang->get_text( 'subscription_upcoming', 'subscription_upcoming_email_title' );
$ec_sub_id     = ( isset( $subscription ) && is_object( $subscription ) && isset( $subscription->subscription_id ) ) ? (int) $subscription->subscription_id : (int) $this->subscription_id;
$ec_sub_link   = wpeasycart_links()->get_account_page( 'subscription_details', array( 'subscription_id' => $ec_sub_id ) );
$ec_sub_date   = ( isset( $date ) && false !== $date ) ? (string) $date : '';
$ec_sub_total  = isset( $total ) ? (string) $total : '';
$ec_sub_charge = '';
if ( '' !== $ec_sub_date ) {
	$ec_sub_charge = str_replace( array( '[total]', '[date]' ), array( '<strong style="color:#111827;">' . $ed::ltr( $ec_sub_total ) . '</strong>', '<strong style="color:#111827;">' . esc_html( $ec_sub_date ) . '</strong>' ), wp_kses_post( $ec_sub_lang->get_text( 'subscription_upcoming', 'upcoming_message_2' ) ) );
}

$ed::open(
	array(
		'title'     => wp_strip_all_tags( $ec_sub_title ),
		'preheader' => ( '' !== $ec_sub_charge ) ? wp_strip_all_tags( $ec_sub_charge ) : $ec_sub_lang->get_text( 'subscription_upcoming', 'upcoming_message_1' ),
		'logo_url'  => isset( $email_logo_url ) ? (string) $email_logo_url : (string) get_option( 'ec_option_email_logo' ),
		'store_url' => ( isset( $store_page ) && '' !== (string) $store_page ) ? (string) $store_page : null,
	)
);

$ed::section_start();
$ed::heading( wp_kses_post( $ec_sub_title ) );
$ed::paragraph( wp_kses_post( $ec_sub_lang->get_text( 'subscription_upcoming', 'upcoming_message_1' ) ) );
$ed::section_end();

$ed::section_start( array( 'top' => 0 ) );
$ed::card_start();
echo '<div style="' . esc_attr( $ed::css( 'strong' ) ) . 'font-size:15px;">' . esc_html( $this->title ) . '</div>';
if ( '' !== $ec_sub_charge ) {
	$ed::paragraph( $ec_sub_charge, array( 'margin' => '6px 0 0 0' ) );
}
$ed::card_end();
$ed::section_end();

$ed::section_start( array( 'top' => 16 ) );
$ed::paragraph( wp_kses_post( $ec_sub_lang->get_text( 'subscription_upcoming', 'upcoming_details' ) ), array( 'tone' => 'muted', 'margin' => '0' ) );
$ed::section_end();

$ed::button_row( $ec_sub_link, $ec_sub_lang->get_text( 'subscription_upcoming', 'upcoming_message_link' ), array( 'top' => 16, 'bottom' => 8, 'arrow' => true ) );

$ed::close();
