<?php
/**
 * Subscription trial started email.
 *
 * Included by ec_subscription::send_trial_start_email( $user ) ( runs as $this = ec_subscription ) with:
 *   $this->title, $this->trial_period_days, $this->subscription_id, $user, $email_logo_url, $store_page, $permalink_divider.
 *
 * 6.0.0: rebuilt on the shared email design ( wp_easycart_email_design, inc/classes/core/class-wp-easycart-email-design.php ).
 * The subscription details link is now a button ( same account page URL ). Copy this file to your wp-easycart-data layout
 * folder to customise it; the file name must stay the same.
 *
 * Language strings kept: subscription_trial / subscription_trial_email_title, trial_message_1 … trial_message_4, trial_message_link.
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
$ec_sub_title = $ec_sub_lang->get_text( 'subscription_trial', 'subscription_trial_email_title' );
$ec_sub_link  = wpeasycart_links()->get_account_page( 'subscription_details', array( 'subscription_id' => (int) $this->subscription_id ) );
$ec_sub_intro = wp_kses_post( $ec_sub_lang->get_text( 'subscription_trial', 'trial_message_1' ) ) . ' <strong style="color:#111827;">' . esc_html( $this->trial_period_days ) . '</strong> ' . wp_kses_post( $ec_sub_lang->get_text( 'subscription_trial', 'trial_message_2' ) ) . ' <strong style="color:#111827;">' . esc_html( $this->title ) . '</strong> ' . wp_kses_post( $ec_sub_lang->get_text( 'subscription_trial', 'trial_message_3' ) );

$ed::open(
	array(
		'title'     => wp_strip_all_tags( $ec_sub_title ),
		'preheader' => wp_strip_all_tags( $ec_sub_intro ),
		'logo_url'  => isset( $email_logo_url ) ? (string) $email_logo_url : (string) get_option( 'ec_option_email_logo' ),
		'store_url' => ( isset( $store_page ) && '' !== (string) $store_page ) ? (string) $store_page : null,
	)
);

$ed::section_start();
$ed::heading( wp_kses_post( $ec_sub_title ) );
$ed::paragraph( $ec_sub_intro );
$ed::section_end();

$ed::section_start( array( 'top' => 0 ) );
$ed::card_start();
$ed::paragraph( wp_kses_post( $ec_sub_lang->get_text( 'subscription_trial', 'trial_message_4' ) ), array( 'tone' => 'small', 'margin' => '0' ) );
$ed::card_end();
$ed::section_end();

$ed::button_row( $ec_sub_link, $ec_sub_lang->get_text( 'subscription_trial', 'trial_message_link' ), array( 'top' => 16, 'bottom' => 8, 'arrow' => true ) );

$ed::close();
