<?php
/**
 * WP EasyCart Account Forgot Password Widget Display for Elementor
 *
 * @package  Wp_Easycart_Elementor_Account_Forgot_Password_Widget
 * @author WP EasyCart
 */
$args = shortcode_atts(
	array(
		'email_label' => wp_easycart_language( )->get_text( 'account_forgot_password', 'account_forgot_password_email_label' ),
		'email_error' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ) . ' ' . wp_easycart_language( )->get_text( 'account_forgot_password', 'account_forgot_password_email_label' ),
		'button_text_forgot_password' => wp_easycart_language( )->get_text( 'account_forgot_password', 'account_forgot_password_button' ),
		'label_type' => 'above',
	),
	$atts
);

echo '<form action="' . esc_url( wpeasycart_links()->get_account_page() ) . '" method="POST">';
	if ( 'floating' == $args['label_type'] ) {
		echo '<div class="ec_cart_error_row" id="ec_account_forgot_password_email_error">' . esc_html( $args['email_error'] ) . '</div>';
	}
	echo '<div class="ec_cart_input_row">';
		if ( 'above' == $args['label_type'] ) {
			echo '<label for="ec_account_forgot_password_email">' . esc_html( $args['email_label'] ) . '</label>';
		}
		echo '<input type="email" name="ec_account_forgot_password_email" id="ec_account_forgot_password_email" class="ec_account_input_field" autocomplete="off" autocapitalize="off" placeholder="';
		if ( 'inside' == $args['label_type'] ) {
			echo esc_html( $args['email_label'] );
		} else if ( 'floating' == $args['label_type'] ) {
			echo ' ';
		}
		echo '">';
		if ( 'floating' == $args['label_type'] ) {
			echo '<label for="ec_account_forgot_password_email">' . esc_html( $args['email_label'] ) . '</label>';
		}
		if ( 'floating' != $args['label_type'] ) {
			echo '<div class="ec_cart_error_row" id="ec_account_forgot_password_email_error">' . esc_html( $args['email_error'] ) . '</div>';
		}
	echo '</div>';

	echo '<div class="wp-easycart-button-row">';
		echo '<button type="submit" class="wp-easycart-button" onclick="return ec_account_forgot_password_button_click();">' . esc_html( $args['button_text_forgot_password'] ) . '</button>';
	echo '</div>';

	echo '<input type="hidden" name="ec_account_page_id" id="ec_account_page_id" value="' . esc_attr( get_queried_object_id() ) . '" />';
	echo '<input type="hidden" name="ec_account_form_action" value="retrieve_password" />';
	echo '<input type="hidden" name="ec_account_form_nonce" value="' . esc_attr( wp_create_nonce( 'wp-easycart-account-retrieve-password' ) ) . '" />';
echo '</form>';
