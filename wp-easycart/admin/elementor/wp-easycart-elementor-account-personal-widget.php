<?php
/**
 * WP EasyCart Account Shipping Address Widget Display for Elementor
 *
 * @package  Wp_Easycart_Elementor_Account_Shipping_Address_Widget
 * @author WP EasyCart
 */
$args = shortcode_atts(
	array(
		'first_name_label' => wp_easycart_language( )->get_text( 'account_personal_information', 'account_personal_information_first_name' ),
		'first_name_error' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ) . ' ' . wp_easycart_language( )->get_text( 'account_personal_information', 'account_personal_information_first_name' ),

		'last_name_label' => wp_easycart_language( )->get_text( 'account_personal_information', 'account_personal_information_last_name' ),
		'last_name_error' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ) . ' ' . wp_easycart_language( )->get_text( 'account_personal_information', 'account_personal_information_last_name' ),
		
		'email_label' => wp_easycart_language( )->get_text( 'account_personal_information', 'account_personal_information_email' ),
		'email_error' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ) . ' ' . wp_easycart_language( )->get_text( 'account_personal_information', 'account_personal_information_email' ),

		'billing_vat_label' => wp_easycart_language( )->get_text( 'cart_billing_information', 'cart_billing_information_vat_registration_number' ),
		
		'extra_email_label' => wp_easycart_language( )->get_text( 'account_personal_information', 'account_personal_information_email_other' ),

		'button_text_personal' => wp_easycart_language( )->get_text( 'account_personal_information', 'account_personal_information_update_button' ),
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
	echo '<input type="hidden" name="ec_account_form_nonce" value="' . esc_attr( wp_create_nonce( 'wp-easycart-account-update-personal-info-' . (int) $GLOBALS['ec_user']->user_id ) ) . '" />';
	echo '<input type="hidden" name="ec_account_form_action" id="ec_account_personal_information_form_action" value="update_personal_information" />';

	if ( 'floating' == $args['label_type'] ) {
		echo '<div class="ec_cart_error_row" id="ec_account_personal_information_first_name_error">' . esc_html( $args['first_name_error'] ) . '</div>';
	}
	echo '<div class="ec_cart_input_row">';
		if ( 'above' == $args['label_type'] ) {
			echo '<label for="ec_account_personal_information_first_name">' . esc_html( $args['first_name_label'] ) . '</label>';
		}
		echo '<input type="text" name="ec_account_personal_information_first_name" id="ec_account_personal_information_first_name" class="ec_account_input_field" value="' . esc_attr( htmlspecialchars( $GLOBALS['ec_user']->first_name, ENT_QUOTES ) ) . '" placeholder="';
		if ( 'inside' == $args['label_type'] ) {
			echo esc_html( $args['first_name_label'] );
		} else if ( 'floating' == $args['label_type'] ) {
			echo ' ';
		}
		echo '">';
		if ( 'floating' == $args['label_type'] ) {
			echo '<label for="ec_account_personal_information_first_name">' . esc_html( $args['first_name_label'] ) . '</label>';
		}
		if ( 'floating' != $args['label_type'] ) {
			echo '<div class="ec_cart_error_row" id="ec_account_personal_information_first_name_error">' . esc_html( $args['first_name_error'] ) . '</div>';
		}
	echo '</div>';

	if ( 'floating' == $args['label_type'] ) {
		echo '<div class="ec_cart_error_row" id="ec_account_personal_information_last_name_error">' . esc_html( $args['last_name_error'] ) . '</div>';
	}
	echo '<div class="ec_cart_input_row">';
		if ( 'above' == $args['label_type'] ) {
			echo '<label for="ec_account_personal_information_last_name">' . esc_html( $args['last_name_label'] ) . '</label>';
		}
		echo '<input type="text" name="ec_account_personal_information_last_name" id="ec_account_personal_information_last_name" class="ec_account_input_field" value="' . esc_attr( htmlspecialchars( $GLOBALS['ec_user']->last_name, ENT_QUOTES ) ) . '" placeholder="';
		if ( 'inside' == $args['label_type'] ) {
			echo esc_html( $args['last_name_label'] );
		} else if ( 'floating' == $args['label_type'] ) {
			echo ' ';
		}
		echo '">';
		if ( 'floating' == $args['label_type'] ) {
			echo '<label for="ec_account_personal_information_last_name">' . esc_html( $args['last_name_label'] ) . '</label>';
		}
		if ( 'floating' != $args['label_type'] ) {
			echo '<div class="ec_cart_error_row" id="ec_account_personal_information_last_name_error">' . esc_html( $args['last_name_error'] ) . '</div>';
		}
	echo '</div>';

	if ( get_option( 'ec_option_collect_vat_registration_number' ) ) {
		echo '<div class="ec_cart_input_row">';
			if ( 'above' == $args['label_type'] ) {
				echo '<label for="ec_account_personal_information_vat_registration_number">' . esc_html( $args['billing_vat_label'] ) . '</label>';
			}
			echo '<input type="text" name="ec_account_personal_information_vat_registration_number" id="ec_account_personal_information_vat_registration_number" class="ec_account_input_field" value="' . esc_attr( htmlspecialchars( $GLOBALS['ec_user']->vat_registration_number, ENT_QUOTES ) ) . '" placeholder="';
			if ( 'inside' == $args['label_type'] ) {
				echo esc_html( $args['billing_vat_label'] );
			} else if ( 'floating' == $args['label_type'] ) {
				echo ' ';
			}
			echo '">';
			if ( 'floating' == $args['label_type'] ) {
				echo '<label for="ec_account_personal_information_vat_registration_number">' . esc_html( $args['billing_vat_label'] ) . '</label>';
			}
		echo '</div>';
	}

	if ( 'floating' == $args['label_type'] ) {
		echo '<div class="ec_cart_error_row" id="ec_account_personal_information_email_error">' . esc_html( $args['email_error'] ) . '</div>';
	}
	echo '<div class="ec_cart_input_row">';
		if ( 'above' == $args['label_type'] ) {
			echo '<label for="ec_account_personal_information_email">' . esc_html( $args['email_label'] ) . '</label>';
		}
		echo '<input type="text" name="ec_account_personal_information_email" id="ec_account_personal_information_email" class="ec_account_input_field" value="' . esc_attr( htmlspecialchars( $GLOBALS['ec_user']->email, ENT_QUOTES ) ) . '" placeholder="';
		if ( 'inside' == $args['label_type'] ) {
			echo esc_html( $args['email_label'] );
		} else if ( 'floating' == $args['label_type'] ) {
			echo ' ';
		}
		echo '">';
		if ( 'floating' == $args['label_type'] ) {
			echo '<label for="ec_account_personal_information_email">' . esc_html( $args['email_label'] ) . '</label>';
		}
		if ( 'floating' != $args['label_type'] ) {
			echo '<div class="ec_cart_error_row" id="ec_account_personal_information_email_error">' . esc_html( $args['email_error'] ) . '</div>';
		}
	echo '</div>';

	if ( 'yes' == $args['enable_extra_email'] ) {
		echo '<div class="ec_cart_input_row">';
			if ( 'above' == $args['label_type'] ) {
				echo '<label for="ec_account_personal_information_email_other">' . esc_html( $args['extra_email_label'] ) . '</label>';
			}
			echo '<input type="text" name="ec_account_personal_information_email_other" id="ec_account_personal_information_email_other" class="ec_account_input_field" value="' . esc_attr( htmlspecialchars( $GLOBALS['ec_user']->email_other, ENT_QUOTES ) ) . '" placeholder="';
			if ( 'inside' == $args['label_type'] ) {
				echo esc_html( $args['extra_email_label'] );
			} else if ( 'floating' == $args['label_type'] ) {
				echo ' ';
			}
			echo '">';
			if ( 'floating' == $args['label_type'] ) {
				echo '<label for="ec_account_personal_information_email_other">' . esc_html( $args['extra_email_label'] ) . '</label>';
			}
		echo '</div>';
	}

	if ( 'yes' == $args['show_subscriber'] ) {
		echo '<div class="ec_cart_button_row">';
			echo '<input type="checkbox" name="ec_account_register_is_subscriber" id="ec_account_register_is_subscriber" class="ec_account_input_field" />';
			echo wp_easycart_language( )->get_text( 'account_register', 'account_register_subscribe' );
		echo '</div>';
	}

	echo '<div class="wp-easycart-button-row">';
		echo '<button type="submit" class="wp-easycart-button" onclick="return ec_account_personal_information_update_click();">' . esc_html( $args['button_text_personal'] ) . '</button>';
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
