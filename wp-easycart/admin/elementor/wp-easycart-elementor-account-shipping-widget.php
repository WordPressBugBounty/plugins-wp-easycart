<?php
/**
 * WP EasyCart Account Shipping Address Widget Display for Elementor
 *
 * @package  Wp_Easycart_Elementor_Account_Shipping_Address_Widget
 * @author WP EasyCart
 */
$args = shortcode_atts(
	array(
		'address_first_name_label' => wp_easycart_language( )->get_text( 'account_shipping_information', 'account_shipping_information_first_name' ),
		'address_first_name_error' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ) . ' ' . wp_easycart_language( )->get_text( 'cart_contact_information', 'account_shipping_information_first_name' ),

		'address_last_name_label' => wp_easycart_language( )->get_text( 'account_shipping_information', 'account_shipping_information_last_name' ),
		'address_last_name_error' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ) . ' ' . wp_easycart_language( )->get_text( 'cart_contact_information', 'account_shipping_information_last_name' ),

		'company_name_label' => wp_easycart_language( )->get_text( 'cart_shipping_information', 'cart_shipping_information_company_name' ),
		'company_name_error' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ) . ' ' . wp_easycart_language( )->get_text( 'cart_shipping_information', 'cart_shipping_information_company_name' ),

		'country_label' => wp_easycart_language( )->get_text( 'account_shipping_information', 'account_shipping_information_country' ),
		'country_error' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ) . ' ' . wp_easycart_language( )->get_text( 'account_shipping_information', 'account_shipping_information_country' ),

		'vat_label' => wp_easycart_language( )->get_text( 'account_shipping_information', 'account_shipping_information_vat_registration_number' ),

		'address_label' => wp_easycart_language( )->get_text( 'account_shipping_information', 'account_shipping_information_address' ),
		'address_error' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ) . ' ' . wp_easycart_language( )->get_text( 'account_shipping_information', 'account_shipping_information_address' ),

		'address2_label' => esc_html__( 'Apartment # or STE', 'wp-easycart' ),

		'city_label' => wp_easycart_language( )->get_text( 'account_shipping_information', 'account_shipping_information_city' ),
		'city_error' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ) . ' ' . wp_easycart_language( )->get_text( 'account_shipping_information', 'account_shipping_information_city' ),

		'state_label' => wp_easycart_language( )->get_text( 'account_shipping_information', 'account_shipping_information_state' ),
		'state_error' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ) . ' ' . wp_easycart_language( )->get_text( 'account_shipping_information', 'account_shipping_information_state' ),

		'zip_label' => wp_easycart_language( )->get_text( 'account_shipping_information', 'account_shipping_information_zip' ),
		'zip_error' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ) . ' ' . wp_easycart_language( )->get_text( 'account_shipping_information', 'account_shipping_information_zip' ),

		'phone_label' => wp_easycart_language( )->get_text( 'account_shipping_information', 'account_shipping_information_phone' ),
		'phone_error' => wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ) . ' ' . wp_easycart_language( )->get_text( 'account_shipping_information', 'account_shipping_information_phone' ),

		'button_text_shipping' => wp_easycart_language( )->get_text( 'account_register', 'account_register_button' ),
		'label_type' => 'above',

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
$countries = $GLOBALS['ec_countries']->countries;
$db = new ec_db();
$states = $db->get_states();
if ( $GLOBALS['ec_user']->shipping->country ) {
	$selected_country = $GLOBALS['ec_user']->shipping->country;
} else if ( count( $countries ) == 1 ) {
	$selected_country = $countries[0]->iso2_cnt;
} else if ( get_option( 'ec_option_default_country' ) ) {
	$selected_country = get_option( 'ec_option_default_country' );
} else {
	$selected_country = $GLOBALS['ec_user']->shipping->country;
}
$selected_state = $GLOBALS['ec_user']->shipping->get_value( "state" );

echo '<form action="' . esc_attr( $account_page ) . '" method="POST">';
	echo '<input type="hidden" name="ec_account_form_nonce" value="' . esc_attr( wp_create_nonce( 'wp-easycart-account-update-shipping-info-' . (int) $GLOBALS['ec_user']->user_id ) ) . '" />';
	echo '<input type="hidden" name="ec_account_form_action" id="ec_account_shipping_information_form_action" value="update_shipping_information" />';
	if ( get_option( 'ec_option_display_country_top' ) ) {
		if ( 'floating' == $args['label_type'] ) {
			echo '<div class="ec_cart_error_row" id="ec_account_shipping_information_country_error">' . esc_html( $args['country_error'] ) . '</div>';
		}
		echo '<div class="ec_cart_input_row">';
			if ( 'above' == $args['label_type'] ) {
				echo '<label for="ec_account_shipping_information_country">' . esc_html( $args['country_label'] ) . '</label>';
			}
			if ( get_option( 'ec_option_use_country_dropdown' ) ) {
				echo '<select name="ec_account_shipping_information_country" id="ec_account_shipping_information_country" class="ec_account_input_field">';
					echo '<option value="0">' . wp_easycart_language()->get_text( "account_shipping_information", "account_shipping_information_default_no_country" ) . '</option>';
					foreach ( $countries as $country ) {
						echo '<option value="' . esc_attr( $country->iso2_cnt ) . '"';
						if ( $country->iso2_cnt == $selected_country ) {
							echo ' selected="selected"';
						}
						echo '>' . esc_attr( $country->name_cnt ) . '</option>';
					}
				echo '</select>';
			} else {
				echo '<input type="text" name="ec_account_shipping_information_country" id="ec_account_shipping_information_country" class="ec_account_input_field" value="' .  esc_attr( htmlspecialchars( $GLOBALS['ec_user']->shipping->country, ENT_QUOTES ) ) . '" placeholder="';
				if ( 'inside' == $args['label_type'] ) {
					echo esc_html( $args['country_label'] );
				} else if ( 'floating' == $args['label_type'] ) {
					echo ' ';
				}
				echo '">';
			}
			if ( 'floating' == $args['label_type'] ) {
				echo '<label for="ec_account_shipping_information_country">' . esc_html( $args['country_label'] ) . '</label>';
			}
			if ( 'floating' != $args['label_type'] ) {
				echo '<div class="ec_cart_error_row" id="ec_account_shipping_information_country_error">' . esc_html( $args['country_error'] ) . '</div>';
			}
		echo '</div>';
	}

	if ( 'floating' == $args['label_type'] ) {
		echo '<div class="ec_cart_error_row" id="ec_account_shipping_information_first_name_error">' . esc_html( $args['first_name_error'] ) . '</div>';
	}
	echo '<div class="ec_cart_input_row">';
		if ( 'above' == $args['label_type'] ) {
			echo '<label for="ec_account_shipping_information_first_name">' . esc_html( $args['address_first_name_error'] ) . '</label>';
		}
		echo '<input type="text" name="ec_account_shipping_information_first_name" id="ec_account_shipping_information_first_name" class="ec_account_input_field" value="'. esc_attr( htmlspecialchars( $GLOBALS['ec_user']->shipping->first_name, ENT_QUOTES ) ) .'" placeholder="';
		if ( 'inside' == $args['label_type'] ) {
			echo esc_html( $args['address_first_name_label'] );
		} else if ( 'floating' == $args['label_type'] ) {
			echo ' ';
		}
		echo '">';
		if ( 'floating' == $args['label_type'] ) {
			echo '<label for="ec_account_shipping_information_first_name">' . esc_html( $args['address_first_name_label'] ) . '</label>';
		}
		if ( 'floating' != $args['label_type'] ) {
			echo '<div class="ec_cart_error_row" id="ec_account_shipping_information_first_name_error">' . esc_html( $args['address_first_name_error'] ) . '</div>';
		}
	echo '</div>';

	if ( 'floating' == $args['label_type'] ) {
		echo '<div class="ec_cart_error_row" id="ec_account_shipping_information_last_name_error">' . esc_html( $args['address_last_name_error'] ) . '</div>';
	}
	echo '<div class="ec_cart_input_row">';
		if ( 'above' == $args['label_type'] ) {
			echo '<label for="ec_account_shipping_information_last_name">' . esc_html( $args['address_last_name_label'] ) . '</label>';
		}
		echo '<input type="text" name="ec_account_shipping_information_last_name" id="ec_account_shipping_information_last_name" class="ec_account_input_field" value="' . esc_attr( htmlspecialchars( $GLOBALS['ec_user']->shipping->last_name, ENT_QUOTES ) ) . '" placeholder="';
		if ( 'inside' == $args['label_type'] ) {
			echo esc_html( $args['address_last_name_label'] );
		} else if ( 'floating' == $args['label_type'] ) {
			echo ' ';
		}
		echo '">';
		if ( 'floating' == $args['label_type'] ) {
			echo '<label for="ec_account_shipping_information_last_name">' . esc_html( $args['address_last_name_label'] ) . '</label>';
		}
		if ( 'floating' != $args['label_type'] ) {
			echo '<div class="ec_cart_error_row" id="ec_account_shipping_information_last_name_error">' . esc_html( $args['address_last_name_error'] ) . '</div>';
		}
	echo '</div>';

	if ( get_option( 'ec_option_enable_company_name' ) ) {
		if ( get_option( 'ec_option_enable_company_name_required' ) ) {
			if ( 'floating' == $args['label_type'] ) {
				echo '<div class="ec_cart_error_row" id="ec_account_shipping_information_company_name_error">' . esc_html( $args['company_name_error'] ) . '</div>';
			}
		}
		echo '<div class="ec_cart_input_row">';
			if ( 'above' == $args['label_type'] ) {
				echo '<label for="ec_account_shipping_information_company_name">' . esc_html( $args['company_name_label'] ) . '</label>';
			}
			echo '<input type="text" name="ec_account_shipping_information_company_name" id="ec_account_shipping_information_company_name" class="ec_account_input_field" value="' . esc_attr( htmlspecialchars( $GLOBALS['ec_user']->shipping->company_name, ENT_QUOTES ) ) . '" placeholder="';
			if ( 'inside' == $args['label_type'] ) {
				echo esc_html( $args['company_name_label'] );
			} else if ( 'floating' == $args['label_type'] ) {
				echo ' ';
			}
			echo '">';
			if ( 'floating' == $args['label_type'] ) {
				echo '<label for="ec_account_shipping_information_company_name">' . esc_html( $args['company_name_label'] ) . '</label>';
			}
			if ( get_option( 'ec_option_enable_company_name_required' ) ) {
				if ( 'floating' != $args['label_type'] ) {
					echo '<div class="ec_cart_error_row" id="ec_account_shipping_information_company_name_error">' . esc_html( $args['company_name_error'] ) . '</div>';
				}
			}
		echo '</div>';
	}

	if ( get_option( 'ec_option_collect_vat_registration_number' ) ) {
		echo '<div class="ec_cart_input_row">';
			if ( 'above' == $args['label_type'] ) {
				echo '<label for="ec_account_shipping_vat_registration_number">' . esc_html( $args['vat_label'] ) . '</label>';
			}
			echo '<input type="text" name="ec_account_shipping_vat_registration_number" id="ec_account_shipping_vat_registration_number" class="ec_account_input_field" value="' . esc_attr( htmlspecialchars( $GLOBALS['ec_user']->vat_registration_number, ENT_QUOTES ) ) . '" placeholder="';
			if ( 'inside' == $args['label_type'] ) {
				echo esc_html( $args['vat_label'] );
			} else if ( 'floating' == $args['label_type'] ) {
				echo ' ';
			}
			echo '">';
			if ( 'floating' == $args['label_type'] ) {
				echo '<label for="ec_account_shipping_vat_registration_number">' . esc_html( $args['vat_label'] ) . '</label>';
			}
		echo '</div>';
	}

	if ( 'floating' == $args['label_type'] ) {
		echo '<div class="ec_cart_error_row" id="ec_account_shipping_information_address_error">' . esc_html( $args['address_error'] ) . '</div>';
	}
	echo '<div class="ec_cart_input_row">';
		if ( 'above' == $args['label_type'] ) {
			echo '<label for="ec_account_shipping_information_address">' . esc_html( $args['address_label'] ) . '</label>';
		}
		echo '<input type="text" name="ec_account_shipping_information_address" id="ec_account_shipping_information_address" class="ec_account_input_field" value="' . esc_attr( htmlspecialchars( $GLOBALS['ec_user']->shipping->address_line_1, ENT_QUOTES ) ) . '" placeholder="';
		if ( 'inside' == $args['label_type'] ) {
			echo esc_html( $args['address_label'] );
		} else if ( 'floating' == $args['label_type'] ) {
			echo ' ';
		}
		echo '">';
		if ( 'floating' == $args['label_type'] ) {
			echo '<label for="ec_account_shipping_information_address">' . esc_html( $args['address_label'] ) . '</label>';
		}
		if ( 'floating' != $args['label_type'] ) {
			echo '<div class="ec_cart_error_row" id="ec_account_shipping_information_address_error">' . esc_html( $args['address_error'] ) . '</div>';
		}
	echo '</div>';

	if ( get_option( 'ec_option_use_address2' ) ) {
		echo '<div class="ec_cart_input_row">';
			echo '<input type="text" name="ec_account_shipping_information_address2" id="ec_account_shipping_information_address2" class="ec_account_input_field" value="' . esc_attr( htmlspecialchars( $GLOBALS['ec_user']->shipping->address_line_2, ENT_QUOTES ) ) . '" placeholder="';
			if ( 'inside' == $args['label_type'] ) {
				echo esc_html( $args['address2_label'] );
			} else if ( 'floating' == $args['label_type'] ) {
				echo ' ';
			}
			echo '">';
			if ( 'floating' == $args['label_type'] ) {
				echo '<label for="ec_account_shipping_information_address2">' . esc_html( $args['address2_label'] ) . '</label>';
			}
		echo '</div>';
	}

	if ( 'floating' == $args['label_type'] ) {
		echo '<div class="ec_cart_error_row" id="ec_account_shipping_information_city_error">' . esc_html( $args['city_error'] ) . '</div>';
	}
	echo '<div class="ec_cart_input_row">';
		if ( 'above' == $args['label_type'] ) {
			echo '<label for="ec_account_shipping_information_city">' . esc_html( $args['city_label'] ) . '</label>';
		}
		echo '<input type="text" name="ec_account_shipping_information_city" id="ec_account_shipping_information_city" class="ec_account_input_field" value="' . esc_attr( htmlspecialchars( $GLOBALS['ec_user']->shipping->city, ENT_QUOTES ) ) . '" placeholder="';
		if ( 'inside' == $args['label_type'] ) {
			echo esc_html( $args['city_label'] );
		} else if ( 'floating' == $args['label_type'] ) {
			echo ' ';
		}
		echo '">';
		if ( 'floating' == $args['label_type'] ) {
			echo '<label for="ec_account_shipping_information_city">' . esc_html( $args['city_label'] ) . '</label>';
		}
		if ( 'floating' != $args['label_type'] ) {
			echo '<div class="ec_cart_error_row" id="ec_account_shipping_information_city_error">' . esc_html( $args['city_error'] ) . '</div>';
		}
	echo '</div>';

	if ( 'floating' == $args['label_type'] ) {
		echo '<div class="ec_cart_error_row" id="ec_account_shipping_information_state_error">' . esc_html( $args['state_error'] ) . '</div>';
	}
	echo '<div class="ec_cart_input_row">';
		if ( 'above' == $args['label_type'] ) {
			echo '<label for="ec_account_shipping_information_state">' . esc_html( $args['state_label'] ) . '</label>';
		}
		if ( get_option( 'ec_option_use_smart_states' ) ) {
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
					echo '<select name="ec_account_shipping_information_state_' . esc_attr( $state->iso2_cnt ) . '" id="ec_account_shipping_information_state_' . esc_attr( $state->iso2_cnt ) . '" class="ec_account_input_field ec_shipping_state_dropdown"';
					if ( $state->iso2_cnt != $selected_country ) {
						echo ' style="display:none;"';
					} else {
						$state_found = true;
					}
					echo '>';

					if ( 'CA' == $state->iso2_cnt ) {
						echo '<option value="0">' . wp_easycart_language()->get_text( "cart_shipping_information", "cart_shipping_information_select_province" ) . '</option>';
					} else if ( 'GB' == $state->iso2_cnt ) {
						echo '<option value="0">' . wp_easycart_language()->get_text( "cart_shipping_information", "cart_shipping_information_select_county" ) . '</option>';
					} else if ( 'US' == $state->iso2_cnt ) {
						echo '<option value="0">' . wp_easycart_language()->get_text( "cart_shipping_information", "cart_shipping_information_select_state" ) . '</option>';
					} else {
						echo '<option value="0">' . wp_easycart_language()->get_text( "cart_shipping_information", "cart_shipping_information_select_other" ) . '</option>';
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
				if ( $state->code_sta == $selected_state ) {
					echo " selected=\"selected\"";
				}
				echo '>' . esc_attr( $state->name_sta ) . '</option>';
			}

			if ( $close_last_state_group ) {
				echo '</optgroup>';
			}
			echo '</select>';

			echo '<input type="text" name="ec_account_input_field" id="ec_account_shipping_information_state" class="ec_account_input_field" value="' . esc_attr( htmlspecialchars( $selected_state, ENT_QUOTES ) ) . '" placeholder="';
			if ( 'inside' == $args['label_type'] ) {
				echo esc_html( $args['state_label'] );
			} else if ( 'floating' == $args['label_type'] ) {
				echo ' ';
			}
			echo '"';
			if ( $state_found ) {
				echo ' style="display:none;"';
			}
			echo ' />';

		} else {
			if ( get_option( 'ec_option_use_state_dropdown' ) ) {
				echo '<select name="ec_account_shipping_information_state" id="ec_account_shipping_information_state" class="ec_account_input_field">';
				echo '<option value="0">' . wp_easycart_language()->get_text( "account_shipping_information", "account_shipping_information_default_no_state" ) . '</option>';
				foreach ( $states as $state ) {
					echo '<option value="' . esc_attr( $state->code_sta ) . '"';
					if ( $state->code_sta == $selected_state ) {
						echo ' selected="selected"';
					}
					echo '>' . esc_attr( $state->name_sta ) . '</option>';
				}
				echo '</select>';
			} else {
				echo '<input type="text" name="ec_account_shipping_information_state" id="ec_account_shipping_information_state" class="ec_account_input_field" value="' . esc_attr( htmlspecialchars( $selected_state, ENT_QUOTES ) ) . '" placeholder="';
				if ( 'inside' == $args['label_type'] ) {
					echo esc_html( $args['state_label'] );
				} else if ( 'floating' == $args['label_type'] ) {
					echo ' ';
				}
				echo '">';
			}
		}
		if ( 'floating' == $args['label_type'] ) {
			echo '<label for="ec_account_shipping_information_state">' . esc_html( $args['state_label'] ) . '</label>';
		}
		if ( 'floating' != $args['label_type'] ) {
			echo '<div class="ec_cart_error_row" id="ec_account_shipping_information_state_error">' . esc_html( $args['state_error'] ) . '</div>';
		}
	echo '</div>';

	if ( 'floating' == $args['label_type'] ) {
		echo '<div class="ec_cart_error_row" id="ec_account_shipping_information_zip_error">' . esc_html( $args['zip_error'] ) . '</div>';
	}
	echo '<div class="ec_cart_input_row">';
		if ( 'above' == $args['label_type'] ) {
			echo '<label for="ec_account_shipping_information_zip">' . esc_html( $args['zip_label'] ) . '</label>';
		}
		echo '<input type="text" name="ec_account_shipping_information_zip" id="ec_account_shipping_information_zip" class="ec_account_input_field" value="' . esc_attr( htmlspecialchars( $GLOBALS['ec_user']->shipping->zip, ENT_QUOTES ) ) . '" placeholder="';
		if ( 'inside' == $args['label_type'] ) {
			echo esc_html( $args['zip_label'] );
		} else if ( 'floating' == $args['label_type'] ) {
			echo ' ';
		}
		echo '">';
		if ( 'floating' == $args['label_type'] ) {
			echo '<label for="ec_account_shipping_information_zip">' . esc_html( $args['zip_label'] ) . '</label>';
		}
		if ( 'floating' != $args['label_type'] ) {
			echo '<div class="ec_cart_error_row" id="ec_account_shipping_information_zip_error">' . esc_html( $args['zip_error'] ) . '</div>';
		}
	echo '</div>';

	if ( ! get_option( 'ec_option_display_country_top' ) ) {
		echo '<div class="ec_cart_input_row">';
			if ( 'above' == $args['label_type'] ) {
				echo '<label for="ec_account_shipping_information_country">' . esc_html( $args['country_label'] ) . '</label>';
			}
			echo '<input type="text" name="ec_account_shipping_information_country" id="ec_account_shipping_information_country" class="ec_account_input_field" placeholder="';
			if ( 'inside' == $args['label_type'] ) {
				echo esc_html( $args['country_label'] );
			} else if ( 'floating' == $args['label_type'] ) {
				echo ' ';
			}
			echo '">';
			if ( get_option( 'ec_option_use_country_dropdown' ) ) {
				$countries = $GLOBALS['ec_countries']->countries;
				$selected_country = get_option( 'ec_option_default_country' );

				echo '<select name="ec_account_shipping_information_country" id="ec_account_shipping_information_country" class="ec_account_input_field">';
					echo '<option value="0">' . wp_easycart_language()->get_text( "account_shipping_information", "account_shipping_information_default_no_country" ) . '</option>';
					foreach ( $countries as $country ) {
						echo '<option value="' . esc_attr( $country->iso2_cnt ) . '"';
						if ( $country->iso2_cnt == $selected_country ) {
							echo ' selected="selected"';
						}
						echo '>' . esc_attr( $country->name_cnt ) . '</option>';
					}
				echo '</select>';
			} else {
				echo '<input type="text" name="ec_account_shipping_information_country" id="ec_account_shipping_information_country" class="ec_account_input_field" value="" placeholder="';
				if ( 'inside' == $args['label_type'] ) {
					echo esc_html( $args['country_label'] );
				} else if ( 'floating' == $args['label_type'] ) {
					echo ' ';
				}
				echo '">';
			}
			if ( 'floating' == $args['label_type'] ) {
				echo '<label for="ec_account_shipping_information_country">' . esc_html( $args['country_label'] ) . '</label>';
			}
			if ( 'floating' != $args['label_type'] ) {
				echo '<div class="ec_cart_error_row" id="ec_account_shipping_information_country_error">' . esc_html( $args['country_error'] ) . '</div>';
			}
		echo '</div>';
	}

	if ( get_option( 'ec_option_collect_user_phone' ) ) {
		echo '<div class="ec_cart_input_row">';
			if ( 'above' == $args['label_type'] ) {
				echo '<label for="ec_account_shipping_information_phone">' . esc_html( $args['phone_label'] ) . '</label>';
			}
			echo '<input type="text" name="ec_account_shipping_information_phone" id="ec_account_shipping_information_phone" class="ec_account_input_field" value="' . esc_attr( htmlspecialchars( $GLOBALS['ec_user']->shipping->phone, ENT_QUOTES ) ) . '" placeholder="';
			if ( 'inside' == $args['label_type'] ) {
				echo esc_html( $args['phone_label'] );
			} else if ( 'floating' == $args['label_type'] ) {
				echo ' ';
			}
			echo '">';
			if ( 'floating' == $args['label_type'] ) {
				echo '<label for="ec_account_shipping_information_phone">' . esc_html( $args['phone_label'] ) . '</label>';
			}
			if ( 'floating' != $args['label_type'] ) {
				echo '<div class="ec_cart_error_row" id="ec_account_shipping_information_phone_error">' . esc_html( $args['phone_error'] ) . '</div>';
			}
		echo '</div>';
	}

	echo '<div class="wp-easycart-button-row">';
		echo '<button type="submit" class="wp-easycart-button" onclick="return ec_account_shipping_information_update_click();">' . esc_html( $args['button_text_shipping'] ) . '</button>';
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
