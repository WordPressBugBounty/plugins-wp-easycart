<?php
/**
 * WP EasyCart Account Login Widget Display for Elementor
 *
 * @package  Wp_Easycart_Elementor_Account_Login_Widget
 * @author WP EasyCart
 */
$args = shortcode_atts(
	array(
		'email_label' => wp_easycart_language( )->get_text( 'account_login', 'account_login_email_label' ),
		'email_error' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ) . ' ' . wp_easycart_language( )->get_text( 'cart_login', 'cart_login_email_label' ),
		'password_label' => wp_easycart_language( )->get_text( 'account_login', 'account_login_password_label' ),
		'password_error' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ) . ' ' . wp_easycart_language( )->get_text( 'cart_login', 'cart_login_password_label' ),
		'button_text_login' => wp_easycart_language( )->get_text( 'account_login', 'account_login_button' ),
		'redirect_after_login' => 'no',
		'redirect_url' => '',
		'label_type' => 'above',
	),
	$atts
);

echo '<form action="' . esc_url( wpeasycart_links()->get_account_page() ) . '" method="POST">';
	if ( 'floating' == $args['label_type'] ) {
		echo '<div class="ec_cart_error_row" id="ec_account_login_email_error">' . esc_html( $args['email_error'] ) . '</div>';
	}
	echo '<div class="ec_cart_input_row">';
		if ( 'above' == $args['label_type'] ) {
			echo '<label for="ec_account_login_email">' . esc_html( $args['email_label'] ) . '</label>';
		}
		echo '<input type="email" name="ec_account_login_email" id="ec_account_login_email" class="ec_account_input_field" autocomplete="off" autocapitalize="off" placeholder="';
		if ( 'inside' == $args['label_type'] ) {
			echo esc_html( $args['email_label'] );
		} else if ( 'floating' == $args['label_type'] ) {
			echo ' ';
		}
		echo '">';
		if ( 'floating' == $args['label_type'] ) {
			echo '<label for="ec_account_login_email">' . esc_html( $args['email_label'] ) . '</label>';
		}
		if ( 'floating' != $args['label_type'] ) {
			echo '<div class="ec_cart_error_row" id="ec_account_login_email_error">' . esc_html( $args['email_error'] ) . '</div>';
		}
	echo '</div>';

	if ( 'floating' == $args['label_type'] ) {
		echo '<div class="ec_cart_error_row" id="ec_account_login_password_error">' . esc_html( $args['password_error'] ) . '</div>';
	}
	echo '<div class="ec_cart_input_row">';
		do_action( 'wpeasycart_pre_login_password_display' );
		if ( 'above' == $args['label_type'] ) {
			echo '<label for="ec_account_login_password">' . esc_html( $args['password_label'] ) . '</label>';
		}
		echo '<input type="password" name="ec_account_login_password" id="ec_account_login_password" class="ec_account_input_field" placeholder="';
		if ( 'inside' == $args['label_type'] ) {
			echo esc_html( $args['password_label'] );
		} else if ( 'floating' == $args['label_type'] ) {
			echo ' ';
		}
		echo '">';
		if ( 'floating' == $args['label_type'] ) {
			echo '<label for="ec_account_login_password">' . esc_html( $args['password_label'] ) . '</label>';
		}
		if ( 'floating' != $args['label_type'] ) {
			echo '<div class="ec_cart_error_row" id="ec_account_login_password_error">' . esc_html( $args['password_error'] ) . '</div>';
		}
	echo '</div>';

	if ( get_option( 'ec_option_enable_recaptcha' ) && '' != get_option( 'ec_option_recaptcha_site_key' ) ) {
		echo '<input type="hidden" id="ec_grecaptcha_response_login" name="ec_grecaptcha_response_login" value="" />';
		echo '<input type="hidden" id="ec_grecaptcha_site_key" value="' . esc_attr( get_option( 'ec_option_recaptcha_site_key' ) ) . '" />';
		echo '<div class="ec_cart_input_row" data-sitekey="' . esc_attr( get_option( 'ec_option_recaptcha_site_key' ) ) . '" id="ec_account_login_recaptcha"></div>';
	}

	echo '<div class="wp-easycart-button-row">';
		echo '<button type="submit" class="wp-easycart-button" onclick="return ec_account_login_button_click();">' . esc_html( $args['button_text_login'] ) . '</button>';
	echo '</div>';

	echo '<input type="hidden" name="ec_account_page_id" id="ec_account_page_id" value="' . esc_attr( get_queried_object_id() ) . '" />';

	if ( 'yes' == $args['redirect_after_login'] ) {
		echo "<input type=\"hidden\" name=\"ec_custom_login_redirect\" value=\"" . esc_url_raw( wp_unslash( $args['redirect_url'] ) ) . "\" />";
	}
	echo '<input type="hidden" name="ec_account_form_action" value="login" />';
	echo '<input type="hidden" name="ec_account_form_nonce" value="' . esc_attr( wp_create_nonce( 'wp-easycart-account-login' ) ) . '" />';
echo '</form>';
if ( get_option( 'ec_option_cache_prevent' ) ) {
echo "<script type=\"text/javascript\">
	if ( jQuery( document.getElementById( 'ec_account_login_recaptcha' ) ).length ) {
		var wpeasycart_login_recaptcha = grecaptcha.render( document.getElementById( 'ec_account_login_recaptcha' ), {
			'sitekey' : jQuery( document.getElementById( 'ec_grecaptcha_site_key' ) ).val(),
			'callback' : wpeasycart_login_recaptcha_callback
		} );
	}
</script>";
}
