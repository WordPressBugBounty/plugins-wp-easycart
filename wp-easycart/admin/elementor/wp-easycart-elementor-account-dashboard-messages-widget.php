<?php
/**
 * WP EasyCart Account Dashboard Orders Widget Display for Elementor
 *
 * @package  Wp_Easycart_Elementor_Account_Dashboard_Orders_Widget
 * @author WP EasyCart
 */
$args = shortcode_atts(
	array(),
	$atts
);

$valid_success_codes = array(
	'validation_required',
	'reset_email_sent',
	'personal_information_updated',
	'billing_information_updated',
	'billing_information_updated',
	'shipping_information_updated',
	'shipping_information_updated',
	'subscription_updated',
	'subscription_updated',
	'subscription_canceled',
	'cart_account_created',
	'activation_success',
	'password_updated',
	'order_connected',
);

$valid_error_codes = array(
	'not_activated',
	'login_failed',
	'register_email_error',
	'register_invalid',
	'no_reset_email_found',
	'personal_information_update_error',
	'password_no_match',
	'password_wrong_current',
	'billing_information_error',
	'shipping_information_error',
	'subscription_update_failed',
	'subscription_cancel_failed',
	'invalid_order_id',
);

if ( isset( $_GET['account_success'] ) && 'login_success' == $_GET['account_success'] ) {
	do_action( 'wp_easycart_login_success_account' );
}


if ( isset( $_GET['account_success'] ) && in_array( $_GET['account_success'], $valid_success_codes ) ) {
	$success_text = wp_easycart_language()->get_text( "ec_success", sanitize_key( $_GET['account_success'] ) );
	$success_text = apply_filters( 'wpeasycart_account_success', $success_text, sanitize_key( $_GET['account_success'] ) );
	if ( $success_text ) {
		echo "<div class=\"ec_account_success\"><div>" . esc_attr( $success_text ) . "</div></div>";
	}
}

if ( isset( $_GET['account_error'] ) && in_array( sanitize_key( $_GET['account_error'] ), $valid_error_codes ) ) {
	$error_text = wp_easycart_language()->get_text( "ec_errors", sanitize_key( $_GET['account_error'] ) );
	$error_text = apply_filters( 'wpeasycart_account_error', $error_text, sanitize_key( $_GET['account_error'] ) );
	if ( $error_text ) {
		echo '<div class="ec_account_error"><div>' . esc_attr( $error_text ) . ' ';
		if ( $_GET['account_error'] == 'login_failed' ) {
			echo wp_easycart_escape_html( apply_filters( 'wpeasycart_forgot_password_link', '<a href="' . esc_url( wpeasycart_links()->get_account_page( 'forgot_password' ) ) . '" class="ec_account_login_link">' . esc_attr( wp_easycart_language()->get_text( 'account_login', 'account_login_forgot_password_link' ) ) . '</a>' ) );
		}
		echo '</div></div>';
	}

}
