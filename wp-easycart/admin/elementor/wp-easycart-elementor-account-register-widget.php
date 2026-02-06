<?php
/**
 * WP EasyCart Account Register Widget Display for Elementor
 *
 * @package  Wp_Easycart_Elementor_Account_Register_Widget
 * @author WP EasyCart
 */
$args = shortcode_atts(
	array(
		'first_name_label' => wp_easycart_language( )->get_text( 'cart_contact_information', 'cart_contact_information_first_name' ),
		'first_name_error' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ) . ' ' . wp_easycart_language( )->get_text( 'cart_contact_information', 'cart_contact_information_first_name' ),

		'last_name_label' => wp_easycart_language( )->get_text( 'cart_contact_information', 'cart_contact_information_last_name' ),
		'last_name_error' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ) . ' ' . wp_easycart_language( )->get_text( 'cart_contact_information', 'cart_contact_information_last_name' ),

		'email_label' => wp_easycart_language( )->get_text( 'cart_contact_information', 'cart_contact_information_email' ),
		'email_error' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ) . ' ' . wp_easycart_language( )->get_text( 'cart_contact_information', 'cart_contact_information_email' ),

		'retype_email_label' => wp_easycart_language( )->get_text( 'cart_contact_information', 'cart_contact_information_retype_email' ),
		'retype_email_error' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_emails_do_not_match' ),

		'password_label' => wp_easycart_language( )->get_text( 'cart_contact_information', 'cart_contact_information_password' ),
		'password_error' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_length_error' ),

		'retype_password_label' => wp_easycart_language( )->get_text( 'cart_contact_information', 'cart_contact_information_retype_password' ),
		'retype_password_error' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_passwords_do_not_match' ),

		'billing_country_label' => wp_easycart_language( )->get_text( 'cart_billing_information', 'cart_billing_information_country' ),
		'billing_country_error' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ) . ' ' . wp_easycart_language( )->get_text( 'cart_billing_information', 'cart_billing_information_country' ),

		'billing_first_name_label' => wp_easycart_language( )->get_text( 'cart_billing_information', 'cart_billing_information_first_name' ),
		'billing_first_name_error' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ) . ' ' . wp_easycart_language( )->get_text( 'cart_billing_information', 'cart_billing_information_first_name' ),

		'billing_last_name_label' => wp_easycart_language( )->get_text( 'cart_billing_information', 'cart_billing_information_last_name' ),
		'billing_last_name_error' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ) . ' ' . wp_easycart_language( )->get_text( 'cart_billing_information', 'cart_billing_information_last_name' ),

		'billing_company_name_label' => wp_easycart_language( )->get_text( 'cart_billing_information', 'cart_billing_information_company_name' ),

		'billing_vat_label' => wp_easycart_language( )->get_text( 'cart_billing_information', 'cart_billing_information_vat_registration_number' ),

		'billing_address_label' => wp_easycart_language( )->get_text( 'cart_billing_information', 'cart_billing_information_address' ),
		'billing_address_error' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ) . ' ' . wp_easycart_language( )->get_text( 'cart_billing_information', 'cart_billing_information_address' ),

		'billing_address2_label' => 'Apartment # or STE',

		'billing_city_label' => wp_easycart_language( )->get_text( 'cart_billing_information', 'cart_billing_information_city' ),
		'billing_city_error' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ) . ' ' . wp_easycart_language( )->get_text( 'cart_billing_information', 'cart_billing_information_city' ),

		'billing_state_label' => wp_easycart_language( )->get_text( 'cart_billing_information', 'cart_billing_information_state' ),
		'billing_state_error' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ) . ' ' . wp_easycart_language( )->get_text( 'cart_billing_information', 'cart_billing_information_state' ),

		'billing_zip_label' => wp_easycart_language( )->get_text( 'cart_billing_information', 'cart_billing_information_zip' ),
		'billing_zip_error' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ) . ' ' . wp_easycart_language( )->get_text( 'cart_billing_information', 'cart_billing_information_zip' ),

		'billing_phone_label' => wp_easycart_language( )->get_text( 'cart_billing_information', 'cart_billing_information_phone' ),
		'billing_phone_error' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ) . ' ' . wp_easycart_language( )->get_text( 'cart_billing_information', 'cart_billing_information_phone' ),

		'billing_user_notes_label' => wp_easycart_language( )->get_text( 'account_register', 'account_billing_information_user_notes' ),
		'billing_user_notes_error' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ) . ' ' . wp_easycart_language( )->get_text( 'cart_billing_information', 'account_billing_information_user_notes' ),

		'billing_title_text' => wp_easycart_language( )->get_text( 'cart_billing_information', 'cart_billing_information_title' ),
		'billing_title_tag' => 'div',

		'button_text_register' => wp_easycart_language( )->get_text( 'account_register', 'account_register_button' ),

		'label_type' => 'above',
		'show_first_name' => 'no',
		'show_last_name' => 'no',
		'show_retype_email' => 'no',
		'show_retype_password' => 'no',
		'require_billing' => 'no',
		'enable_notes' => 'no',
		'require_terms' => 'no',
		'show_subscriber' => 'no',
	),
	$atts
);

echo '<form action="' . esc_url( wpeasycart_links()->get_account_page() ) . '" method="POST">';
	echo '<input type="hidden" name="ec_account_form_nonce" value="' . esc_attr( wp_create_nonce( 'wp-easycart-account-register' ) ) . '" />';

	if ( 'yes' == $args['show_first_name'] ) {
		/* First Name */
		if ( 'floating' == $args['label_type'] ) {
			echo '<div class="ec_cart_error_row" id="ec_account_register_first_name_error">' . esc_html( $args['first_name_error'] ) . '</div>';
		}
		echo '<div class="ec_cart_input_row">';
			if ( 'above' == $args['label_type'] ) {
				echo '<label for="ec_account_register_first_name">' . esc_html( $args['first_name_label'] ) . '</label>';
			}
			echo '<input type="text" name="ec_account_register_first_name" id="ec_account_register_first_name" class="ec_account_input_field" placeholder="';
			if ( 'inside' == $args['label_type'] ) {
				echo esc_html( $args['first_name_label'] );
			} else if ( 'floating' == $args['label_type'] ) {
				echo ' ';
			}
			echo '">';
			if ( 'floating' == $args['label_type'] ) {
				echo '<label for="ec_account_register_first_name">' . esc_html( $args['first_name_label'] ) . '</label>';
			}
			if ( 'floating' != $args['label_type'] ) {
				echo '<div class="ec_cart_error_row" id="ec_account_register_first_name_error">' . esc_html( $args['first_name_error'] ) . '</div>';
			}
		echo '</div>';
	}

	if ( 'yes' == $args['show_last_name'] ) {
		/* Last Name */
		if ( 'floating' == $args['label_type'] ) {
			echo '<div class="ec_cart_error_row" id="ec_account_register_last_name_error">' . esc_html( $args['last_name_error'] ) . '</div>';
		}
		echo '<div class="ec_cart_input_row">';
			if ( 'above' == $args['label_type'] ) {
				echo '<label for="ec_account_register_last_name">' . esc_html( $args['last_name_label'] ) . '</label>';
			}
			echo '<input type="text" name="ec_account_register_last_name" id="ec_account_register_last_name" class="ec_account_input_field" placeholder="';
			if ( 'inside' == $args['label_type'] ) {
				echo esc_html( $args['last_name_label'] );
			} else if ( 'floating' == $args['label_type'] ) {
				echo ' ';
			}
			echo '">';
			if ( 'floating' == $args['label_type'] ) {
				echo '<label for="ec_account_register_last_name">' . esc_html( $args['last_name_label'] ) . '</label>';
			}
			if ( 'floating' != $args['label_type'] ) {
				echo '<div class="ec_cart_error_row" id="ec_account_register_last_name_error">' . esc_html( $args['last_name_error'] ) . '</div>';
			}
		echo '</div>';
	}

	/* Email */
	if ( 'floating' == $args['label_type'] ) {
		echo '<div class="ec_cart_error_row" id="ec_account_register_email_error">' . esc_html( $args['email_error'] ) . '</div>';
	}
	echo '<div class="ec_cart_input_row">';
		if ( 'above' == $args['label_type'] ) {
			echo '<label for="ec_account_register_email">' . esc_html( $args['email_label'] ) . '</label>';
		}
		echo '<input type="text" name="ec_account_register_email" id="ec_account_register_email" class="ec_account_input_field" placeholder="';
		if ( 'inside' == $args['label_type'] ) {
			echo esc_html( $args['email_label'] );
		} else if ( 'floating' == $args['label_type'] ) {
			echo ' ';
		}
		echo '">';
		if ( 'floating' == $args['label_type'] ) {
			echo '<label for="ec_account_register_email">' . esc_html( $args['email_label'] ) . '</label>';
		}
		if ( 'floating' != $args['label_type'] ) {
			echo '<div class="ec_cart_error_row" id="ec_account_register_email_error">' . esc_html( $args['email_error'] ) . '</div>';
		}
	echo '</div>';

	if ( 'yes' == $args['show_retype_email'] ) {
		/* Retype Email */
		if ( 'floating' == $args['label_type'] ) {
			echo '<div class="ec_cart_error_row" id="ec_account_register_email_retype_error">' . esc_html( $args['retype_email_error'] ) . '</div>';
		}
		echo '<div class="ec_cart_input_row">';
			if ( 'above' == $args['label_type'] ) {
				echo '<label for="ec_account_register_retype_email">' . esc_html( $args['retype_email_label'] ) . '</label>';
			}
			echo '<input type="text" name="ec_account_register_retype_email" id="ec_account_register_retype_email" class="ec_account_input_field" placeholder="';
			if ( 'inside' == $args['label_type'] ) {
				echo esc_html( $args['retype_email_label'] );
			} else if ( 'floating' == $args['label_type'] ) {
				echo ' ';
			}
			echo '">';
			if ( 'floating' == $args['label_type'] ) {
				echo '<label for="ec_account_register_retype_email">' . esc_html( $args['retype_email_label'] ) . '</label>';
			}
			if ( 'floating' != $args['label_type'] ) {
				echo '<div class="ec_cart_error_row" id="ec_account_register_email_retype_error">' . esc_html( $args['retype_email_error'] ) . '</div>';
			}
		echo '</div>';
	}

	/* Password */
	if ( 'floating' == $args['label_type'] ) {
		echo '<div class="ec_cart_error_row" id="ec_account_register_password_error">' . esc_html( $args['password_error'] ) . '</div>';
	}
	echo '<div class="ec_cart_input_row">';
		if ( 'above' == $args['label_type'] ) {
			echo '<label for="ec_account_register_password">' . esc_html( $args['password_label'] ) . '</label>';
		}
		echo '<input type="password" name="ec_account_register_password" id="ec_account_register_password" class="ec_account_input_field" placeholder="';
		if ( 'inside' == $args['label_type'] ) {
			echo esc_html( $args['password_label'] );
		} else if ( 'floating' == $args['label_type'] ) {
			echo ' ';
		}
		echo '">';
		if ( 'floating' == $args['label_type'] ) {
			echo '<label for="ec_account_register_password">' . esc_html( $args['password_label'] ) . '</label>';
		}
		if ( 'floating' != $args['label_type'] ) {
			echo '<div class="ec_cart_error_row" id="ec_account_register_password_error">' . esc_html( $args['password_error'] ) . '</div>';
		}
	echo '</div>';

	if ( 'yes' == $args['show_retype_password'] ) {
		/* Retype Password */
		if ( 'floating' == $args['label_type'] ) {
			echo '<div class="ec_cart_error_row" id="ec_account_register_password_retype_error">' . esc_html( $args['retype_password_error'] ) . '</div>';
		}
		echo '<div class="ec_cart_input_row">';
			if ( 'above' == $args['label_type'] ) {
				echo '<label for="ec_account_register_password_retype">' . esc_html( $args['retype_password_label'] ) . '</label>';
			}
			echo '<input type="password" name="ec_account_register_password_retype" id="ec_account_register_password_retype" class="ec_account_input_field" placeholder="';
			if ( 'inside' == $args['label_type'] ) {
				echo esc_html( $args['retype_password_label'] );
			} else if ( 'floating' == $args['label_type'] ) {
				echo ' ';
			}
			echo '">';
			if ( 'floating' == $args['label_type'] ) {
				echo '<label for="ec_account_register_password_retype">' . esc_html( $args['retype_password_label'] ) . '</label>';
			}
			if ( 'floating' != $args['label_type'] ) {
				echo '<div class="ec_cart_error_row" id="ec_account_register_password_retype_error">' . esc_html( $args['retype_password_error'] ) . '</div>';
			}
		echo '</div>';
	}

	if ( 'yes' == $args['require_billing'] ) {
		$billing_title_tag = ( isset( $args['billing_title_tag'] ) && in_array( $args['billing_title_tag'], array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'p' ) ) ) ? $args['billing_title_tag'] : 'div';
		echo '<' . esc_attr( $billing_title_tag ) . ' class="ec">' . esc_html( $args['billing_title_text'] ) . '</' . esc_attr( $billing_title_tag ) . '>';

		if( get_option( 'ec_option_display_country_top' ) ){
			echo '<div class="ec_cart_input_row">';
				if ( 'above' == $args['label_type'] ) {
					echo '<label for="ec_account_billing_information_country">' . esc_html( $args['billing_country_label'] ) . '</label>';
				}
				echo '<input type="text" name="ec_account_billing_information_country" id="ec_account_billing_information_country" class="ec_account_input_field" placeholder="';
				if ( 'inside' == $args['label_type'] ) {
					echo esc_html( $args['billing_country_label'] );
				} else if ( 'floating' == $args['label_type'] ) {
					echo ' ';
				}
				echo '">';
				if ( get_option( 'ec_option_use_country_dropdown' ) ) {
					$countries = $GLOBALS['ec_countries']->countries;
					$selected_country = get_option( 'ec_option_default_country' );

					echo '<select name="ec_account_billing_information_country" id="ec_account_billing_information_country" class="ec_account_billing_information_input_field">';
						echo '<option value="0">' . wp_easycart_language()->get_text( "account_billing_information", "account_billing_information_default_no_country" ) . '</option>';
						foreach ( $countries as $country ) {
							echo '<option value="' . esc_attr( $country->iso2_cnt ) . '"';
							if ( $country->iso2_cnt == $selected_country ) {
								echo ' selected="selected"';
							}
							echo '>' . esc_attr( $country->name_cnt ) . '</option>';
						}
					echo '</select>';
				} else {
					echo '<input type="text" name="ec_account_billing_information_country" id="ec_account_billing_information_country" class="ec_account_billing_information_input_field" value="" placeholder="';
					if ( 'inside' == $args['label_type'] ) {
						echo esc_html( $args['billing_country_label'] );
					} else if ( 'floating' == $args['label_type'] ) {
						echo ' ';
					}
					echo '">';
				}
				if ( 'floating' == $args['label_type'] ) {
					echo '<label for="ec_account_billing_information_country">' . esc_html( $args['billing_country_label'] ) . '</label>';
				}
				if ( 'floating' != $args['label_type'] ) {
					echo '<div class="ec_cart_error_row" id="ec_account_billing_information_country_error">' . esc_html( $args['billing_country_error'] ) . '</div>';
				}
			echo '</div>';
		}

		echo '<div class="ec_cart_input_row">';
			if ( 'above' == $args['label_type'] ) {
				echo '<label for="ec_account_billing_information_first_name">' . esc_html( $args['billing_first_name_label'] ) . '</label>';
			}
			echo '<input type="text" name="ec_account_billing_information_first_name" id="ec_account_billing_information_first_name" class="ec_account_input_field" placeholder="';
			if ( 'inside' == $args['label_type'] ) {
				echo esc_html( $args['billing_first_name_label'] );
			} else if ( 'floating' == $args['label_type'] ) {
				echo ' ';
			}
			echo '">';
			if ( 'floating' == $args['label_type'] ) {
				echo '<label for="ec_account_billing_information_first_name">' . esc_html( $args['billing_first_name_label'] ) . '</label>';
			}
			if ( 'floating' != $args['label_type'] ) {
				echo '<div class="ec_cart_error_row" id="ec_account_billing_information_first_name_error">' . esc_html( $args['billing_first_name_error'] ) . '</div>';
			}
		echo '</div>';

		echo '<div class="ec_cart_input_row">';
			if ( 'above' == $args['label_type'] ) {
				echo '<label for="ec_account_billing_information_last_name">' . esc_html( $args['billing_last_name_label'] ) . '</label>';
			}
			echo '<input type="text" name="ec_account_billing_information_last_name" id="ec_account_billing_information_last_name" class="ec_account_input_field" placeholder="';
			if ( 'inside' == $args['label_type'] ) {
				echo esc_html( $args['billing_last_name_label'] );
			} else if ( 'floating' == $args['label_type'] ) {
				echo ' ';
			}
			echo '">';
			if ( 'floating' == $args['label_type'] ) {
				echo '<label for="ec_account_billing_information_last_name">' . esc_html( $args['billing_last_name_label'] ) . '</label>';
			}
			if ( 'floating' != $args['label_type'] ) {
				echo '<div class="ec_cart_error_row" id="ec_account_billing_information_last_name_error">' . esc_html( $args['billing_last_name_error'] ) . '</div>';
			}
		echo '</div>';

		if ( get_option( 'ec_option_enable_company_name' ) ) {
			echo '<div class="ec_cart_input_row">';
				if ( 'above' == $args['label_type'] ) {
					echo '<label for="ec_account_billing_information_company_name">' . esc_html( $args['billing_company_name_label'] ) . '</label>';
				}
				echo '<input type="text" name="ec_account_billing_information_company_name" id="ec_account_billing_information_company_name" class="ec_account_input_field" placeholder="';
				if ( 'inside' == $args['label_type'] ) {
					echo esc_html( $args['billing_company_name_label'] );
				} else if ( 'floating' == $args['label_type'] ) {
					echo ' ';
				}
				echo '">';
				if ( 'floating' == $args['label_type'] ) {
					echo '<label for="ec_account_billing_information_company_name">' . esc_html( $args['billing_company_name_label'] ) . '</label>';
				}
			echo '</div>';
		}

		if ( get_option( 'ec_option_collect_vat_registration_number' ) ) {
			echo '<div class="ec_cart_input_row">';
				if ( 'above' == $args['label_type'] ) {
					echo '<label for="ec_account_billing_vat_registration_number">' . esc_html( $args['billing_vat_label'] ) . '</label>';
				}
				echo '<input type="text" name="ec_account_billing_vat_registration_number" id="ec_account_billing_vat_registration_number" class="ec_account_input_field" placeholder="';
				if ( 'inside' == $args['label_type'] ) {
					echo esc_html( $args['billing_vat_label'] );
				} else if ( 'floating' == $args['label_type'] ) {
					echo ' ';
				}
				echo '">';
				if ( 'floating' == $args['label_type'] ) {
					echo '<label for="ec_account_billing_vat_registration_number">' . esc_html( $args['billing_vat_label'] ) . '</label>';
				}
			echo '</div>';
		}

		echo '<div class="ec_cart_input_row">';
			if ( 'above' == $args['label_type'] ) {
				echo '<label for="ec_account_billing_information_address">' . esc_html( $args['billing_address_label'] ) . '</label>';
			}
			echo '<input type="text" name="ec_account_billing_information_address" id="ec_account_billing_information_address" class="ec_account_input_field" placeholder="';
			if ( 'inside' == $args['label_type'] ) {
				echo esc_html( $args['billing_address_label'] );
			} else if ( 'floating' == $args['label_type'] ) {
				echo ' ';
			}
			echo '">';
			if ( 'floating' == $args['label_type'] ) {
				echo '<label for="ec_account_billing_information_address">' . esc_html( $args['billing_address_label'] ) . '</label>';
			}
			if ( 'floating' != $args['label_type'] ) {
				echo '<div class="ec_cart_error_row" id="ec_account_billing_information_address_error">' . esc_html( $args['billing_address_error'] ) . '</div>';
			}
		echo '</div>';

		if ( get_option( 'ec_option_use_address2' ) ) {
			echo '<div class="ec_cart_input_row">';
				if ( 'above' == $args['label_type'] ) {
					echo '<label for="ec_account_billing_information_address2">' . esc_html( $args['billing_address2_label'] ) . '</label>';
				}
				echo '<input type="text" name="ec_account_billing_information_address2" id="ec_account_billing_information_address2" class="ec_account_input_field" placeholder="';
				if ( 'inside' == $args['label_type'] ) {
					echo esc_html( $args['billing_address_label'] );
				} else if ( 'floating' == $args['label_type'] ) {
					echo ' ';
				}
				echo '">';
				if ( 'floating' == $args['label_type'] ) {
					echo '<label for="ec_account_billing_information_address2">' . esc_html( $args['billing_address2_label'] ) . '</label>';
				}
			echo '</div>';
		}

		echo '<div class="ec_cart_input_row">';
			if ( 'above' == $args['label_type'] ) {
				echo '<label for="ec_account_billing_information_city">' . esc_html( $args['billing_city_label'] ) . '</label>';
			}
			echo '<input type="text" name="ec_account_billing_information_city" id="ec_account_billing_information_city" class="ec_account_input_field" placeholder="';
			if ( 'inside' == $args['label_type'] ) {
				echo esc_html( $args['billing_city_label'] );
			} else if ( 'floating' == $args['label_type'] ) {
				echo ' ';
			}
			echo '">';
			if ( 'floating' == $args['label_type'] ) {
				echo '<label for="ec_account_billing_information_city">' . esc_html( $args['billing_city_label'] ) . '</label>';
			}
			if ( 'floating' != $args['label_type'] ) {
				echo '<div class="ec_cart_error_row" id="ec_account_billing_information_city_error">' . esc_html( $args['billing_city_error'] ) . '</div>';
			}
		echo '</div>';

		echo '<div class="ec_cart_input_row">';
			if ( 'above' == $args['label_type'] ) {
				echo '<label for="ec_account_billing_information_state">' . esc_html( $args['billing_state_label'] ) . '</label>';
			}
			$db = new ec_db();
			$states = $db->get_states();
			if ( get_option( 'ec_option_use_smart_states' ) ) {
				$selected_country = '';
				$current_country = '';
				$close_last_state = false;
				$state_found = false;
				$current_state_group = '';
				$close_last_state_group = false;

				foreach ( $states as $state ) {
					if ( $current_country != $state->iso2_cnt ) {
						if ( $close_last_state ) {
							echo '</select>';
						}
						echo '<select name="ec_account_billing_information_state_' . esc_attr( $state->iso2_cnt ) . '" id="ec_account_billing_information_state_' . esc_attr( $state->iso2_cnt ) . '" class="ec_account_billing_information_input_field ec_billing_state_dropdown"';
						if ( $state->iso2_cnt != $selected_country ) {
							echo ' style="display:none;"';
						} else {
							$state_found = true;
						}
						echo '>';

						if ( 'CA' == $state->iso2_cnt ) {
							echo '<option value="0">' . wp_easycart_language()->get_text( "cart_billing_information", "cart_billing_information_select_province" ) . '</option>';
						} else if ( 'GB' == $state->iso2_cnt ) {
							echo '<option value="0">' . wp_easycart_language()->get_text( "cart_billing_information", "cart_billing_information_select_county" ) . '</option>';
						} else if ( 'US' == $state->iso2_cnt ) {
							echo '<option value="0">' . wp_easycart_language()->get_text( "cart_billing_information", "cart_billing_information_select_state" ) . '</option>';
						} else {
							echo '<option value="0">' . wp_easycart_language()->get_text( "cart_billing_information", "cart_billing_information_select_other" ) . '</option>';
						}

						$current_country = $state->iso2_cnt;
						$close_last_state = true;
					}

					if ( $current_state_group != $state->group_sta && '' != $state->group_sta ) {
						if ( $close_last_state_group ) {
							echo '</optgroup>';
						}
						echo '<optgroup label="' . esc_attr( $state->group_sta ) . '">';
						$current_state_group = $state->group_sta;
						$close_last_state_group = true;
					}

					echo '<option value="' . esc_attr( $state->code_sta ) . '"';
					echo '>' . esc_attr( $state->name_sta ) . '</option>';
				}

				if ( $close_last_state_group ) {
					echo '</optgroup>';
				}
				echo '</select>';

				echo '<input type="text" name="ec_account_billing_information_state" id="ec_account_billing_information_state" class="ec_account_billing_information_input_field" value=""';
				if ( $state_found ) {
					echo ' style="display:none;"';
				}
				echo ' />';

			} else {
				if ( get_option( 'ec_option_use_state_dropdown' ) ) {
					echo '<select name="ec_account_billing_information_state" id="ec_account_billing_information_state" class="ec_account_billing_information_input_field">';
					echo '<option value="0">' . wp_easycart_language()->get_text( "account_billing_information", "account_billing_information_default_no_state" ) . '</option>';
					foreach ( $states as $state ) {
						echo '<option value="' . esc_attr( $state->code_sta ) . '"';
						if ( $state->code_sta == $selected_state ) {
							echo ' selected="selected"';
						}
						echo '>' . esc_attr( $state->name_sta ) . '</option>';
					}
					echo '</select>';
				} else {
					echo '<input type="text" name="ec_account_billing_information_state" id="ec_account_billing_information_state" class="ec_account_billing_information_input_field" value="" placeholder="';
					if ( 'inside' == $args['label_type'] ) {
						echo esc_html( $args['billing_state_label'] );
					} else if ( 'floating' == $args['label_type'] ) {
						echo ' ';
					}
					echo '">';
				}
			}
			if ( 'floating' == $args['label_type'] ) {
				echo '<label for="ec_account_billing_information_state">' . esc_html( $args['billing_state_label'] ) . '</label>';
			}
			if ( 'floating' != $args['label_type'] ) {
				echo '<div class="ec_cart_error_row" id="ec_account_billing_information_state_error">' . esc_html( $args['billing_state_error'] ) . '</div>';
			}
		echo '</div>';

		echo '<div class="ec_cart_input_row">';
			if ( 'above' == $args['label_type'] ) {
				echo '<label for="ec_account_billing_information_zip">' . esc_html( $args['billing_zip_label'] ) . '</label>';
			}
			echo '<input type="text" name="ec_account_billing_information_zip" id="ec_account_billing_information_zip" class="ec_account_input_field" placeholder="';
			if ( 'inside' == $args['label_type'] ) {
				echo esc_html( $args['billing_zip_label'] );
			} else if ( 'floating' == $args['label_type'] ) {
				echo ' ';
			}
			echo '">';
			if ( 'floating' == $args['label_type'] ) {
				echo '<label for="ec_account_billing_information_zip">' . esc_html( $args['billing_zip_label'] ) . '</label>';
			}
			if ( 'floating' != $args['label_type'] ) {
				echo '<div class="ec_cart_error_row" id="ec_account_billing_information_zip_error">' . esc_html( $args['billing_zip_error'] ) . '</div>';
			}
		echo '</div>';

		if ( ! get_option( 'ec_option_display_country_top' ) ) {
			echo '<div class="ec_cart_input_row">';
				if ( 'above' == $args['label_type'] ) {
					echo '<label for="ec_account_billing_information_country">' . esc_html( $args['billing_country_label'] ) . '</label>';
				}
				echo '<input type="text" name="ec_account_billing_information_country" id="ec_account_billing_information_country" class="ec_account_input_field" placeholder="';
				if ( 'inside' == $args['label_type'] ) {
					echo esc_html( $args['billing_country_label'] );
				} else if ( 'floating' == $args['label_type'] ) {
					echo ' ';
				}
				echo '">';
				if ( get_option( 'ec_option_use_country_dropdown' ) ) {
					$countries = $GLOBALS['ec_countries']->countries;
					$selected_country = get_option( 'ec_option_default_country' );

					echo '<select name="ec_account_billing_information_country" id="ec_account_billing_information_country" class="ec_account_billing_information_input_field">';
						echo '<option value="0">' . wp_easycart_language()->get_text( "account_billing_information", "account_billing_information_default_no_country" ) . '</option>';
						foreach ( $countries as $country ) {
							echo '<option value="' . esc_attr( $country->iso2_cnt ) . '"';
							if ( $country->iso2_cnt == $selected_country ) {
								echo ' selected="selected"';
							}
							echo '>' . esc_attr( $country->name_cnt ) . '</option>';
						}
					echo '</select>';
				} else {
					echo '<input type="text" name="ec_account_billing_information_country" id="ec_account_billing_information_country" class="ec_account_billing_information_input_field" value="" placeholder="';
					if ( 'inside' == $args['label_type'] ) {
						echo esc_html( $args['billing_country_label'] );
					} else if ( 'floating' == $args['label_type'] ) {
						echo ' ';
					}
					echo '">';
				}
				if ( 'floating' == $args['label_type'] ) {
					echo '<label for="ec_account_billing_information_country">' . esc_html( $args['billing_country_label'] ) . '</label>';
				}
				if ( 'floating' != $args['label_type'] ) {
					echo '<div class="ec_cart_error_row" id="ec_account_billing_information_country_error">' . esc_html( $args['billing_country_error'] ) . '</div>';
				}
			echo '</div>';
		}
		if ( get_option( 'ec_option_collect_user_phone' ) ) {
			echo '<div class="ec_cart_input_row">';
				if ( 'above' == $args['label_type'] ) {
					echo '<label for="ec_account_billing_information_phone">' . esc_html( $args['billing_phone_label'] ) . '</label>';
				}
				echo '<input type="text" name="ec_account_billing_information_phone" id="ec_account_billing_information_phone" class="ec_account_input_field" placeholder="';
				if ( 'inside' == $args['label_type'] ) {
					echo esc_html( $args['billing_phone_label'] );
				} else if ( 'floating' == $args['label_type'] ) {
					echo ' ';
				}
				echo '">';
				if ( 'floating' == $args['label_type'] ) {
					echo '<label for="ec_account_billing_information_phone">' . esc_html( $args['billing_phone_label'] ) . '</label>';
				}
				if ( 'floating' != $args['label_type'] ) {
					echo '<div class="ec_cart_error_row" id="ec_account_billing_information_phone_error">' . esc_html( $args['billing_phone_error'] ) . '</div>';
				}
			echo '</div>';
		}
	}

	if ( 'yes' == $args['enable_notes'] ) {
		echo '<div class="ec_cart_input_row">';
			if ( 'above' == $args['label_type'] ) {
				echo '<label for="ec_account_billing_information_user_notes">' . esc_html( $args['billing_user_notes_label'] ) . '</label>';
			}
			echo '<input type="text" name="ec_account_billing_information_user_notes" id="ec_account_billing_information_user_notes" class="ec_account_input_field" placeholder="';
			if ( 'inside' == $args['label_type'] ) {
				echo esc_html( $args['billing_user_notes_label'] );
			} else if ( 'floating' == $args['label_type'] ) {
				echo ' ';
			}
			echo '">';
			if ( 'floating' == $args['label_type'] ) {
				echo '<label for="ec_account_billing_information_user_notes">' . esc_html( $args['billing_user_notes_label'] ) . '</label>';
			}
			if ( 'floating' != $args['label_type'] ) {
				echo '<div class="ec_cart_error_row" id="ec_account_register_user_notes_error">' . esc_html( $args['billing_user_notes_error'] ) . '</div>';
			}
		echo '</div>';
	}

	if ( 'yes' == $args['require_terms'] ) {
		echo '<div class="ec_cart_button_row">';
			echo '<input type="checkbox" name="ec_terms_agree" id="ec_terms_agree" class="ec_account_input_field" />';
			echo wp_easycart_language( )->get_text( 'account_register', 'account_register_agree_terms' );
		echo '</div>';
		echo '<div class="ec_cart_error_row" id="ec_terms_error">';
			echo wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_payment_accept_terms' );
		echo '</div>';
	}

	if ( get_option( 'ec_option_enable_recaptcha' ) && '' != get_option( 'ec_option_recaptcha_site_key' ) ) {
		echo '<input type="hidden" id="ec_grecaptcha_response_register" name="ec_grecaptcha_response_register" value="" />';
		echo '<input type="hidden" id="ec_grecaptcha_site_key" value="' . esc_attr( get_option( 'ec_option_recaptcha_site_key' ) ) . '" />';
		echo '<div class="ec_cart_input_row" data-sitekey="' . esc_attr( get_option( 'ec_option_recaptcha_site_key' ) ) . '" id="ec_account_register_recaptcha"></div>';
	}

	if ( 'yes' == $args['show_subscriber'] ) {
		echo '<div class="ec_cart_button_row">';
			echo '<input type="checkbox" name="ec_account_register_is_subscriber" id="ec_account_register_is_subscriber" class="ec_account_input_field" />';
			echo wp_easycart_language( )->get_text( 'account_register', 'account_register_subscribe' );
		echo '</div>';
	}

	echo '<div class="wp-easycart-button-row">';
		echo '<button type="submit" class="wp-easycart-button" onclick="return ec_account_register_button_click();">' . esc_html( $args['button_text_register'] ) . '</button>';
	echo '</div>';
	echo '<input type="hidden" name="ec_account_page_id" id="ec_account_page_id" value="' . esc_attr( get_queried_object_id() ) . '" />';
	echo '<input type="hidden" name="ec_account_form_action" value="register"/>';
echo '</form>';

if ( get_option( 'ec_option_cache_prevent' ) ) {
	echo "<script type=\"text/javascript\">
		wpeasycart_account_billing_country_update( );
		wpeasycart_account_shipping_country_update( );
		jQuery( document.getElementById( 'ec_account_billing_information_country' ) ).change( function( ){ wpeasycart_account_billing_country_update( ); } );
		jQuery( document.getElementById( 'ec_account_shipping_information_country' ) ).change( function( ){ wpeasycart_account_shipping_country_update( ); } );
		if( jQuery( document.getElementById( 'ec_account_login_recaptcha' ) ).length ){
			var wpeasycart_login_recaptcha = grecaptcha.render( document.getElementById( 'ec_account_login_recaptcha' ), {
				'sitekey' : jQuery( document.getElementById( 'ec_grecaptcha_site_key' ) ).val( ),
				'callback' : wpeasycart_login_recaptcha_callback
			});
		}
		if( jQuery( document.getElementById( 'ec_account_register_recaptcha' ) ).length ){
			var wpeasycart_register_recaptcha = grecaptcha.render( document.getElementById( 'ec_account_register_recaptcha' ), {
				'sitekey' : jQuery( document.getElementById( 'ec_grecaptcha_site_key' ) ).val( ),
				'callback' : wpeasycart_register_recaptcha_callback
			});
		}
	</script>";
}
