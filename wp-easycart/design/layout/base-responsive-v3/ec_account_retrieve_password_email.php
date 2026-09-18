<?php
/**
 * Password reset email.
 *
 * Included by ec_accountpage::send_password_reset_email() and wp_easycart_admin_users::send_password_reset_email() with:
 *   $user ( first_name, last_name, email ), $reset_url, $email_logo_url, $store_page, $permalink_divider.
 *   Older callers that still pass $new_password ( no $reset_url ) get the password in a code box.
 *
 * 6.0.0: rebuilt on the shared email design ( wp_easycart_email_design, inc/classes/core/class-wp-easycart-email-design.php ).
 * The reset link is used as given ( button plus the plain link for clients that block buttons ). Copy this file to your
 * wp-easycart-data layout folder to customise it; the file name must stay the same.
 *
 * Language strings kept: account_forgot_password_email / account_forgot_password_email_title, _dear, _reset_intro,
 * _reset_button, _reset_expiry, _thank_you ( _your_new_password and _change_password for the $new_password fallback ).
 *
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'wp_easycart_email_design' ) ) {
	require_once EC_PLUGIN_DIRECTORY . '/inc/classes/core/class-wp-easycart-email-design.php';
}

$ed          = 'wp_easycart_email_design';
$ec_pw_lang  = wp_easycart_language();
$ec_pw_reset = isset( $reset_url ) ? (string) $reset_url : '';
$ec_pw_name  = ( isset( $user ) && is_object( $user ) ) ? trim( ( isset( $user->first_name ) ? $user->first_name : '' ) . ' ' . ( isset( $user->last_name ) ? $user->last_name : '' ) ) : '';
$ec_pw_title = $ec_pw_lang->get_text( 'account_forgot_password_email', 'account_forgot_password_email_title' );
$ec_pw_intro = $ec_pw_lang->get_text( 'account_forgot_password_email', 'account_forgot_password_email_reset_intro' );

$ed::open(
	array(
		'title'     => wp_strip_all_tags( $ec_pw_title ),
		'preheader' => ( '' !== $ec_pw_reset ) ? $ec_pw_intro : $ec_pw_title,
		'logo_url'  => isset( $email_logo_url ) ? (string) $email_logo_url : (string) get_option( 'ec_option_email_logo' ),
		'store_url' => ( isset( $store_page ) && '' !== (string) $store_page ) ? (string) $store_page : null,
	)
);

$ed::section_start( array( 'top' => 16 ) );
$ed::heading( wp_kses_post( rtrim( trim( $ec_pw_lang->get_text( 'account_forgot_password_email', 'account_forgot_password_email_dear' ) ), ':,' ) ) . ( '' !== $ec_pw_name ? ' ' . esc_html( $ec_pw_name ) : '' ) . ',' );

if ( '' !== $ec_pw_reset ) {
	$ed::paragraph( wp_kses_post( $ec_pw_intro ) );
	$ed::button( $ec_pw_reset, $ec_pw_lang->get_text( 'account_forgot_password_email', 'account_forgot_password_email_reset_button' ), array( 'margin' => '4px 0 20px 0' ) );
	$ed::paragraph( wp_kses_post( $ec_pw_lang->get_text( 'account_forgot_password_email', 'account_forgot_password_email_reset_expiry' ) ), array( 'tone' => 'muted', 'margin' => '0 0 12px 0' ) );
	$ed::paragraph( '<a href="' . esc_url( $ec_pw_reset ) . '" target="_blank" style="' . esc_attr( $ed::css( 'link' ) ) . 'word-break:break-all;">' . esc_html( $ec_pw_reset ) . '</a>', array( 'tone' => 'small', 'margin' => '0' ) );
} elseif ( isset( $new_password ) && '' !== (string) $new_password ) {
	$ed::code_box( $new_password, wp_kses_post( $ec_pw_lang->get_text( 'account_forgot_password_email', 'account_forgot_password_email_your_new_password' ) ) );
	$ed::paragraph( wp_kses_post( $ec_pw_lang->get_text( 'account_forgot_password_email', 'account_forgot_password_email_change_password' ) ), array( 'margin' => '16px 0 0 0' ) );
}
$ed::section_end();

$ed::section_start( array( 'top' => 24, 'bottom' => 8 ) );
$ed::paragraph( wp_kses_post( $ec_pw_lang->get_text( 'account_forgot_password_email', 'account_forgot_password_email_thank_you' ) ), array( 'tone' => 'strong', 'margin' => '0' ) );
$ed::section_end();

$ed::close();
