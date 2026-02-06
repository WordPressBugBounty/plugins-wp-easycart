<?php
/**
 * WP EasyCart Account Shipping Address Widget Display for Elementor
 *
 * @package  Wp_Easycart_Elementor_Account_Shipping_Address_Widget
 * @author WP EasyCart
 */
$args = shortcode_atts(
	array(
		'current_password_label' => wp_easycart_language( )->get_text( 'account_password', 'account_password_current_password' ),
		'current_password_error' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ) . ' ' . wp_easycart_language( )->get_text( 'account_password', 'account_password_current_password' ),

		'new_password_label' => wp_easycart_language( )->get_text( 'account_password', 'account_password_new_password' ),
		'new_password_error' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_length_error' ),

		'retype_password_label' => wp_easycart_language( )->get_text( 'account_password', 'account_password_retype_new_password' ),
		'retype_password_error' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_passwords_do_not_match' ),

		'button_text_password' => wp_easycart_language( )->get_text( 'account_password', 'account_password_update_button' ),
		'label_type' => 'above',
		'enable_extra_email' => 'no',
		'show_subscriber' => 'no',

	),
	$atts
);
$accountpageid = apply_filters( 'wp_easycart_account_page_id', get_option( 'ec_option_accountpage' ) );
if ( function_exists( 'icl_object_id' ) ) {
	$accountpageid = icl_object_id( $accountpageid, 'page', true, ICL_LANGUAGE_CODE );
}
$account_page = get_permalink( $accountpageid );
if ( class_exists( 'WordPressHTTPS' ) && isset( $_SERVER['HTTPS'] ) ) {
	$https_class = new WordPressHTTPS();
	$account_page = $https_class->makeUrlHttps( $account_page );
} else if ( get_option( 'ec_option_load_ssl' ) ) {
	$account_page = str_replace( 'http://', 'https://', $account_page );
}
if ( substr_count( $account_page, '?' ) ) {
	$permalink_divider = '&';
} else {
	$permalink_divider = '?';
}
$account_page = apply_filters( 'wp_easycart_account_page_url', $account_page );
echo '<form action="' . esc_attr( $account_page ) . '" method="POST">';
	echo '<input type="hidden" name="ec_account_form_nonce" value="' . esc_attr( wp_create_nonce( 'wp-easycart-account-update-password-' . (int) $GLOBALS['ec_user']->user_id ) ) . '" />';
	echo '<input type="hidden" name="ec_account_form_action" value="update_password" />';

	do_action( 'wpeasycart_change_password_top' );

	if ( 'floating' == $args['label_type'] ) {
		echo '<div class="ec_cart_error_row" id="ec_account_password_current_password_error">' . esc_html( $args['current_password_error'] ) . '</div>';
	}
	echo '<div class="ec_cart_input_row">';
		if ( 'above' == $args['label_type'] ) {
			echo '<label for="ec_account_password_current_password">' . esc_html( $args['current_password_label'] ) . '</label>';
		}
		echo '<input type="password" name="ec_account_password_current_password" id="ec_account_password_current_password" class="ec_account_input_field" placeholder="';
		if ( 'inside' == $args['label_type'] ) {
			echo esc_html( $args['current_password_label'] );
		} else if ( 'floating' == $args['label_type'] ) {
			echo ' ';
		}
		echo '">';
		if ( 'floating' == $args['label_type'] ) {
			echo '<label for="ec_account_password_current_password">' . esc_html( $args['current_password_label'] ) . '</label>';
		}
		if ( 'floating' != $args['label_type'] ) {
			echo '<div class="ec_cart_error_row" id="ec_account_password_current_password_error">' . esc_html( $args['current_password_error'] ) . '</div>';
		}
	echo '</div>';

	if ( 'floating' == $args['label_type'] ) {
		echo '<div class="ec_cart_error_row" id="ec_account_password_new_password_error">' . esc_html( $args['new_password_error'] ) . '</div>';
	}
	echo '<div class="ec_cart_input_row">';
		do_action( 'wpeasycart_pre_password_display' );
		if ( 'above' == $args['label_type'] ) {
			echo '<label for="ec_account_password_new_password">' . esc_html( $args['new_password_label'] ) . '</label>';
		}
		echo '<input type="password" name="ec_account_password_new_password" id="ec_account_password_new_password" class="ec_account_input_field" placeholder="';
		if ( 'inside' == $args['label_type'] ) {
			echo esc_html( $args['new_password_label'] );
		} else if ( 'floating' == $args['label_type'] ) {
			echo ' ';
		}
		echo '">';
		if ( 'floating' == $args['label_type'] ) {
			echo '<label for="ec_account_password_new_password">' . esc_html( $args['new_password_label'] ) . '</label>';
		}
		if ( 'floating' != $args['label_type'] ) {
			echo '<div class="ec_cart_error_row" id="ec_account_password_new_password_error">' . esc_html( $args['new_password_error'] ) . '</div>';
		}
	echo '</div>';

	if ( 'floating' == $args['label_type'] ) {
		echo '<div class="ec_cart_error_row" id="ec_account_password_retype_new_password_error">' . esc_html( $args['retype_password_error'] ) . '</div>';
	}
	echo '<div class="ec_cart_input_row">';
		
		if ( 'above' == $args['label_type'] ) {
			echo '<label for="ec_account_password_retype_new_password">' . esc_html( $args['retype_password_label'] ) . '</label>';
		}
		echo '<input type="password" name="ec_account_password_retype_new_password" id="ec_account_password_retype_new_password" class="ec_account_input_field" placeholder="';
		if ( 'inside' == $args['label_type'] ) {
			echo esc_html( $args['retype_password_label'] );
		} else if ( 'floating' == $args['label_type'] ) {
			echo ' ';
		}
		echo '">';
		if ( 'floating' == $args['label_type'] ) {
			echo '<label for="ec_account_password_retype_new_password">' . esc_html( $args['retype_password_label'] ) . '</label>';
		}
		if ( 'floating' != $args['label_type'] ) {
			echo '<div class="ec_cart_error_row" id="ec_account_password_retype_new_password_error">' . esc_html( $args['retype_password_error'] ) . '</div>';
		}
	echo '</div>';

	echo '<div class="wp-easycart-button-row">';
		echo '<button type="submit" class="wp-easycart-button" onclick="';
		$ec_password_save_js_function = apply_filters( 'wpeasycart_update_password_js_function', 'return ec_account_password_button_click( );' );
		echo esc_attr( $ec_password_save_js_function );
		echo '">' . esc_html( $args['button_text_password'] ) . '</button>';
	echo '</div>';
	echo '<input type="hidden" name="ec_account_page_id" id="ec_account_page_id" value="' . esc_attr( get_queried_object_id() ) . '" />';
echo '</form>';

if ( get_option( 'ec_option_cache_prevent' ) ) {
	echo '<script type="text/javascript">
		wpeasycart_account_billing_country_update( );
		wpeasycart_account_shipping_country_update( );
		jQuery( document.getElementById( \'ec_account_billing_information_country\' ) ).change( function( ){ wpeasycart_account_billing_country_update( ); } );
		jQuery( document.getElementById( \'ec_account_shipping_information_country\' ) ).change( function( ){ wpeasycart_account_shipping_country_update( ); } );
	</script>';
}
