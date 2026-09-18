<?php
/**
 * Subscription ended email ( ended, cancelled or stopped after failed payments ).
 *
 * Included by ec_subscription::send_subscription_ended_email( $user ) ( runs as $this = ec_subscription ) with:
 *   $this->title, $this->get_subscription_purchase_link(), $user ( first_name, last_name ), $email_logo_url, $store_page,
 *   $permalink_divider.
 *
 * 6.0.0: rebuilt on the shared email design ( wp_easycart_email_design, inc/classes/core/class-wp-easycart-email-design.php ).
 * The "start a new subscription" link is now a button ( same URL ). Copy this file to your wp-easycart-data layout folder to
 * customise it; the file name must stay the same.
 *
 * Language strings kept: cart_success / cart_payment_complete_line_1; subscription_ended / subscription_ended_email_title,
 * ended_details, ended_message_1, ended_message_2, emded_message_link.
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
$ec_sub_lang  = wp_easycart_language();
$ec_sub_title = $ec_sub_lang->get_text( 'subscription_ended', 'subscription_ended_email_title' );
$ec_sub_name  = ( isset( $user ) && is_object( $user ) ) ? trim( ( isset( $user->first_name ) ? $user->first_name : '' ) . ' ' . ( isset( $user->last_name ) ? $user->last_name : '' ) ) : '';
$ec_sub_link  = $this->get_subscription_purchase_link();

$ed::open(
	array(
		'title'     => wp_strip_all_tags( $ec_sub_title ),
		'preheader' => wp_strip_all_tags( $ec_sub_lang->get_text( 'subscription_ended', 'ended_details' ) ) . ' ' . $this->title,
		'logo_url'  => isset( $email_logo_url ) ? (string) $email_logo_url : (string) get_option( 'ec_option_email_logo' ),
		'store_url' => ( isset( $store_page ) && '' !== (string) $store_page ) ? (string) $store_page : null,
	)
);

$ed::section_start();
$ed::heading( wp_kses_post( $ec_sub_title ) );
$ed::paragraph( wp_kses_post( rtrim( trim( $ec_sub_lang->get_text( 'cart_success', 'cart_payment_complete_line_1' ) ), ':,' ) ) . ( '' !== $ec_sub_name ? ' ' . esc_html( $ec_sub_name ) : '' ) . ',', array( 'margin' => '0 0 12px 0' ) );
$ed::paragraph( wp_kses_post( $ec_sub_lang->get_text( 'subscription_ended', 'ended_message_1' ) ) );
$ed::section_end();

$ed::section_start( array( 'top' => 0 ) );
$ed::card_start();
$ed::label( wp_kses_post( rtrim( trim( $ec_sub_lang->get_text( 'subscription_ended', 'ended_details' ) ), ':' ) ) );
echo '<div style="' . esc_attr( $ed::css( 'strong' ) ) . 'font-size:15px;">' . esc_html( $this->title ) . '</div>';
$ed::card_end();
$ed::section_end();

$ed::section_start( array( 'top' => 16 ) );
$ed::paragraph( wp_kses_post( $ec_sub_lang->get_text( 'subscription_ended', 'ended_message_2' ) ), array( 'tone' => 'muted', 'margin' => '0' ) );
$ed::section_end();

$ed::button_row( $ec_sub_link, $ec_sub_lang->get_text( 'subscription_ended', 'emded_message_link' ), array( 'top' => 16, 'bottom' => 8, 'arrow' => true ) );

$ed::close();
