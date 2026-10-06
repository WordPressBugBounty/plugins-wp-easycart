<?php

class ec_accountpage {
	protected $mysqli;

	public $user;
	public $downloads;
	public $orders;
	public $order;
	public $subscriptions;
	public $subscription;

	private $user_email;
	private $user_password;

	public $store_page;
	public $account_page;
	public $cart_page;
	public $permalink_divider;

	public $redirect_login;

	private $reset_password_key = '';

	/**
	 * Whether the personal information form printed its newsletter box ( 6.0.2 ).
	 *
	 * @var bool
	 */
	private $personal_subscriber_input_shown = false;

	function __construct( $redirect_login = false ) {
		$this->user =& $GLOBALS['ec_user'];
		$this->mysqli = new ec_db();
		$this->orders = new ec_orderlist( $GLOBALS['ec_user']->user_id );
		$this->subscriptions = new ec_subscription_list( $GLOBALS['ec_user'] );
		$this->downloads = $this->mysqli->get_download_list( $GLOBALS['ec_user']->user_id );

		if ( isset( $_GET['order_id'] ) ) {
			if ( isset( $_GET['ec_guest_key'] ) && (bool) $_GET['ec_guest_key'] ) {
				$GLOBALS['ec_cart_data']->cart_data->is_guest = true;
				$GLOBALS['ec_cart_data']->cart_data->guest_key = sanitize_key( $_GET['ec_guest_key'] );
				$order_row = $this->mysqli->get_guest_order_row( (int) $_GET['order_id'], sanitize_key( $_GET['ec_guest_key'] ) );

			} else if ( $GLOBALS['ec_cart_data']->cart_data->is_guest != '' ) {
				$order_row = $this->mysqli->get_guest_order_row( (int) $_GET['order_id'], $GLOBALS['ec_cart_data']->cart_data->guest_key );

			} else {
				$order_row = $this->mysqli->get_order_row( (int) $_GET['order_id'], $GLOBALS['ec_cart_data']->cart_data->user_id );
			}

			if ( $order_row ) {
				$this->order = new ec_orderdisplay( $order_row, true );
			}
		}

		if ( isset( $_GET['subscription_id'] ) ) {
			$subscription_row = $this->mysqli->get_subscription_row( (int) $_GET['subscription_id'] );
			if ( $subscription_row && $subscription_row->user_id == $GLOBALS['ec_cart_data']->cart_data->user_id ) {
				$this->subscription = new ec_subscription( $subscription_row, true );
			} else {
				$this->subscription = false;
			}
		}

		$storepageid = get_option('ec_option_storepage');
		$accountpageid = apply_filters( 'wp_easycart_account_page_id', get_option( 'ec_option_accountpage' ) );
		$cartpageid = get_option('ec_option_cartpage');

		if ( function_exists( 'icl_object_id' ) ) {
			$storepageid = icl_object_id( $storepageid, 'page', true, ICL_LANGUAGE_CODE );
			$accountpageid = icl_object_id( $accountpageid, 'page', true, ICL_LANGUAGE_CODE );
			$cartpageid = icl_object_id( $cartpageid, 'page', true, ICL_LANGUAGE_CODE );
		}

		$this->store_page = get_permalink( $storepageid );
		$this->account_page = get_permalink( $accountpageid );
		$this->cart_page = get_permalink( $cartpageid );

		if ( class_exists( 'WordPressHTTPS' ) && isset( $_SERVER['HTTPS'] ) ) {
			$https_class = new WordPressHTTPS();
			$this->store_page = $https_class->makeUrlHttps( $this->store_page );
			$this->account_page = $https_class->makeUrlHttps( $this->account_page );
			$this->cart_page = $https_class->makeUrlHttps( $this->cart_page );

		} else if ( get_option( 'ec_option_load_ssl' ) ) {
			$this->store_page = str_replace( 'http://', 'https://', $this->store_page );
			$this->cart_page = str_replace( 'http://', 'https://', $this->cart_page );
			$this->account_page = str_replace( 'http://', 'https://', $this->account_page );

		}

		if ( substr_count( $this->account_page, '?' ) ) {
			$this->permalink_divider = '&';
		} else {
			$this->permalink_divider = '?';
		}

		$this->redirect_login = $redirect_login;

		$this->cart_page = apply_filters( 'wp_easycart_cart_page_url', $this->cart_page );
		$this->account_page = apply_filters( 'wp_easycart_account_page_url', $this->account_page );
	}

	public function display_account_dynamic( $account_page, $page_id, $success_code, $error_code, $form_only = false ) {

		$this->display_account_error( $error_code );
		$this->display_account_success( $success_code );

		if ( 'reset_password' === substr( $account_page, 0, 14 ) || ( isset( $_GET['ec_page'] ) && 'reset_password' === sanitize_key( $_GET['ec_page'] ) ) ) {
			if ( strlen( $account_page ) > 15 && '-' === substr( $account_page, 14, 1 ) ) {
				$this->reset_password_key = sanitize_text_field( substr( $account_page, 15 ) );
			}
			$this->display_reset_password_page();
			return;
		}

		if ( $GLOBALS['ec_cart_data']->cart_data->user_id != "" ) {

			if ( $account_page == 'billing_information' ) {
				$this->display_billing_information_page();

			} else if ( $account_page == 'shipping_information' ) {
				$this->display_shipping_information_page();

			} else if ( $account_page == 'personal_information' ) {
				$this->display_personal_information_page();

			} else if ( $account_page == 'orders' ) {
				$this->display_orders_page();

			} else if ( $account_page == 'subscriptions' ) {
				$this->display_subscriptions_page();

			} else if ( $account_page == 'password' ) {
				$this->display_password_page();

			} else if ( substr( $account_page, 0, 13 ) == 'order_details' ) {
				$order_info = explode( '-', $account_page );
				$order_row = false;
				if ( count( $order_info ) > 1 ) {
					$order_id = (int) $order_info[1];
					if ( count( $order_info ) > 2 ) {
						$guest_key = substr( preg_replace( '/[^A-Z]/', '', $order_info[2] ), 0, 30 );
						$order_row = $this->mysqli->get_guest_order_row( $order_id, $guest_key );
						if ( $order_row ) {
							$GLOBALS['ec_cart_data']->cart_data->is_guest = true;
							$GLOBALS['ec_cart_data']->cart_data->guest_key = $guest_key;
						}
					} else {
						$order_row = $this->mysqli->get_order_row( $order_id, $GLOBALS['ec_cart_data']->cart_data->user_id );
					}
				}

				if ( $order_row ) {
					$this->order = new ec_orderdisplay( $order_row, true );
				}

				$this->display_order_details_page();

			} else if ( substr( $account_page, 0, 20 ) == 'subscription_details' ) {
				$this->subscription = false;
				$subscription_id = (int) substr( $account_page, 21 );
				$subscription_row = $this->mysqli->get_subscription_row( $subscription_id );

				if ( $subscription_row && $subscription_row->user_id == $GLOBALS['ec_cart_data']->cart_data->user_id ) {
					$this->subscription = new ec_subscription( $subscription_row, true );
				}

				$this->display_subscription_details_page();

			} else {
				if ( 'login_success' == $success_code ) {
					if ( '' != get_option( 'ec_option_google_ga4_property_id' ) ) {
						if ( get_option( 'ec_option_google_ga4_tag_manager' ) ) {
							echo '<script>
								jQuery( document ).ready( function() {
									dataLayer.push({
										event: "login",
										"userId": "' . esc_attr( $GLOBALS['ec_cart_data']->cart_data->user_id ) . '",
									} );
								} );
							</script>';
						} else {
							echo '<script>
								jQuery( document ).ready( function() {
									gtag( "event", "login", {
										"userId": "' . esc_attr( $GLOBALS['ec_cart_data']->cart_data->user_id ) . '",
									} );
								} );
							</script>';
						}
					}
				}
				$this->display_dashboard_page();

			}

		} else {

			if ( substr( $account_page, 0, 13 ) == 'order_details' ) {
				$order_info = explode( '-', $account_page );
				$order_row = false;
				if ( count( $order_info ) > 1 ) {
					$order_id = (int) $order_info[1];
					if ( count( $order_info ) > 2 ) {
						$guest_key = substr( preg_replace( '/[^A-Z]/', '', $order_info[2] ), 0, 30 );
						$order_row = $this->mysqli->get_guest_order_row( $order_id, $guest_key );
						if ( $order_row ) {
							$GLOBALS['ec_cart_data']->cart_data->is_guest = true;
							$GLOBALS['ec_cart_data']->cart_data->guest_key = $guest_key;
						}
					}
				}

				if ( $order_row ) {
					$this->order = new ec_orderdisplay( $order_row, true );
					$this->display_order_details_page();
				} else {
					$this->display_account_login_page( $page_id, array( 'form_only' => $form_only ) );

				}

			} else if ( $account_page == 'register' ) {
				$this->display_register_page();

			} else if ( $account_page == 'forgot_password' ) {
				$this->display_forgot_password_page();

			} else {
				$this->display_account_login_page( $page_id, array( 'form_only' => $form_only ) );

			}

		}

	}

	public function display_account_page( $force_page = false ) {
		if ( 'reset_password' === $force_page || ( isset( $_GET['ec_page'] ) && 'reset_password' === sanitize_key( $_GET['ec_page'] ) ) ) {
			$this->display_account_error();
			$this->display_account_success();
			$this->display_reset_password_page();
			return;
		}
		if ( $force_page && 'register' == $force_page ) {
			$this->display_register_page();
		} else if ( $force_page && 'forgot_password' == $force_page ) {
			$this->display_forgot_password_page();
		} else if ( $force_page && 'login' == $force_page ) {
			$this->display_account_login_page( false, $shortcode_atts );
		} else {
			do_action( 'wpeasycart_account_page_pre' );
			if ( apply_filters( 'wpeasycart_show_account_page', true ) ) {
				echo "<div class=\"ec_account_page\">";
				if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_account_page.php' ) )	
					include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_account_page.php' );
				else	
					include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_account_page.php' );
				echo "<input type=\"hidden\" name=\"ec_account_base_path\" id=\"ec_account_base_path\" value=\"" . esc_url( plugins_url() ) . "\" />";
				echo "<input type=\"hidden\" name=\"ec_account_session_id\" id=\"ec_account_session_id\" value=\"" . esc_attr( $GLOBALS['ec_cart_data']->ec_cart_id ) . "\" />";
				echo "<input type=\"hidden\" name=\"ec_account_email\" id=\"ec_account_email\" value=\"" . esc_attr( htmlspecialchars( ( isset( $this->user->email ) ) ? $this->user->email : '', ENT_QUOTES ) ) . "\" />";

				$page_name = "";
				if ( $force_page ) {
					$page_name = htmlspecialchars( sanitize_key( $force_page ), ENT_QUOTES );
				} else if ( isset( $_GET['ec_page'] ) ) {
					$page_name = htmlspecialchars( sanitize_key( $_GET['ec_page'] ), ENT_QUOTES );
				}

				echo "<input type=\"hidden\" name=\"ec_account_start_page\" id=\"ec_account_start_page\" value=\"" . esc_attr( $page_name ) . "\" />";
				echo "</div>";
			}
		}
	}

	public function display_account_error( $error_code = '' ) {
		/* 6.0.2: plus the Connect Order answers ( order_claim_*, invalid_order_id ), for a claim link that returns to this page, and
		 * download_unavailable ( a download refused by ec_custom_headers(): an unpaid, refunded or cancelled order ). */
		$valid_error_codes = array( 'not_activated', 'login_failed', 'register_email_error', 'register_invalid', 'no_reset_email_found', 'reset_link_invalid', 'password_too_short', 'password_invalid', 'personal_information_update_error', 'password_no_match', 'password_wrong_current', 'billing_information_error', 'shipping_information_error', 'subscription_update_failed', 'subscription_cancel_failed', 'invalid_order_id', 'order_claim_invalid', 'order_claim_sign_in', 'order_claim_limit', 'download_unavailable' );
		if ( isset( $_GET['account_error'] ) && in_array( $_GET['account_error'], $valid_error_codes ) ) {
			$error_text = self::account_message_text( 'ec_errors', sanitize_key( $_GET['account_error'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a message code for display, checked against the list above.
			$error_text = apply_filters( 'wpeasycart_account_error', $error_text, sanitize_key( $_GET['account_error'] ) );
			if ( $error_text ) {
				echo "<div class=\"ec_account_error\"><div>" . esc_attr( $error_text ) . " ";
				if ( $_GET['account_error'] == 'login_failed' ) {
					$this->display_account_login_forgot_password_link( wp_easycart_language()->get_text( 'account_login', 'account_login_forgot_password_link' ) );
				}
				echo "</div></div>";
				if ( 'not_activated' == $_GET['account_error'] ) {
					$this->display_account_resend_activation_form();
				}
			}

		} else if ( $error_code != '' && in_array( $error_code, $valid_error_codes ) ) {
			$error_text = self::account_message_text( 'ec_errors', $error_code );
			$error_text = apply_filters( 'wpeasycart_account_error', $error_text, $error_code );
			if ( $error_text ) {
				echo "<div class=\"ec_account_error\"><div>" . esc_attr( $error_text ) . " ";
				if ( $error_code == 'login_failed' ) {
					$this->display_account_login_forgot_password_link( wp_easycart_language()->get_text( 'account_login', 'account_login_forgot_password_link' ) );
				}
				echo "</div></div>";
			}
		}
	}

	public function display_account_success( $success_code = '' ) {
		/* 6.0.2: plus the Connect Order answers ( order_claim_sent, order_connected ), for a claim link that returns to this page. */
		$valid_success_codes = array( 'validation_required', 'reset_email_sent', 'password_reset_success', 'resend_activation_sent', 'personal_information_updated', 'billing_information_updated', 'billing_information_updated', 'shipping_information_updated', 'shipping_information_updated', 'subscription_updated', 'subscription_updated', 'subscription_canceled', 'cart_account_created', 'activation_success', 'password_updated', 'order_connected', 'order_claim_sent' );
		if ( isset( $_GET['account_success'] ) && in_array( $_GET['account_success'], $valid_success_codes ) ) {
			$success_code = sanitize_key( $_GET['account_success'] );
			if ( 'reset_email_sent' == $success_code ) { // Custom for upgraded reset email text.
				$success_code = 'reset_email_sent_new';
			}
			$success_text = self::account_message_text( 'ec_success', $success_code );
			$success_text = apply_filters( 'wpeasycart_account_success', $success_text, $success_code );
			if ( $success_text )
				echo "<div class=\"ec_account_success\"><div>" . esc_attr( $success_text ) . "</div></div>";

		} else if ( $success_code != '' && in_array( $success_code, $valid_success_codes ) ) {
			$success_text = self::account_message_text( 'ec_success', $success_code );
			$success_text = apply_filters( 'wpeasycart_account_success', $success_text, $success_code );
			if ( $success_text )
				echo "<div class=\"ec_account_success\"><div>" . esc_attr( $success_text ) . "</div></div>";
		}
		if ( 'login_success' == $success_code ) {
			do_action( 'wp_easycart_login_success_account' );
		}
	}

	public function is_page_visible( $page_name ) {
		if ( isset( $_GET['ec_page'] ) ) {
			if ( '' != $GLOBALS['ec_cart_data']->cart_data->user_id ) {
				if ( $page_name == 'login' ) {
					return false;
				} else if ( $page_name == $_GET['ec_page'] ) {
					return true;
				} else if ( $_GET['ec_page'] == 'login' && $page_name == 'dashboard') {
					return true;
				} else {
					return false;
				}
			} else if ( '' != $GLOBALS['ec_cart_data']->cart_data->is_guest ) {
				if ( $page_name == 'forgot_password' && $_GET['ec_page'] == 'forgot_password' ) {
					return true;
				} else if ( $page_name == 'register' && $_GET['ec_page'] == 'register' ) {
					return true;
				} else if ( $page_name == 'login' && $_GET['ec_page'] != 'register' && $_GET['ec_page'] != 'forgot_password' && $_GET['ec_page'] != 'order_details' ) {
					return true;
				} else if ( $page_name == 'order_details' && $_GET['ec_page'] == 'order_details' && $this->order ) {
					return true;
				} else if ( $page_name == 'login' && $_GET['ec_page'] == 'order_details' && !$this->order ) {
					return true;
				} else {
					return false;
				}
			} else if ( isset( $_GET['ec_guest_key'] ) && (bool) $_GET['ec_guest_key'] ) {
				if ( $page_name == 'forgot_password' && $_GET['ec_page'] == 'forgot_password' ) {
					return true;
				} else if ( $page_name == 'register' && $_GET['ec_page'] == 'register' ) {
					return true;
				} else if ( $page_name == 'login' && $_GET['ec_page'] != 'register' && $_GET['ec_page'] != 'forgot_password' && $_GET['ec_page'] != 'order_details' ) {
					return true;
				} else if ( $page_name == 'order_details' && $_GET['ec_page'] == 'order_details' ) {
					return true;
				} else {
					return false;
				}
			} else {
				if ( $page_name == 'forgot_password' && $_GET['ec_page'] == 'forgot_password' ) {
					return true;
				} else if ( $page_name == 'register' && $_GET['ec_page'] == 'register' ) {
					return true;
				} else if ( $page_name == 'login' && $_GET['ec_page'] != 'register' && $_GET['ec_page'] != 'forgot_password' ) {
					return true;
				} else {
					return false;
				}
			}
		} else {
			if ( $GLOBALS['ec_cart_data']->cart_data->user_id != "" ) {
				if ( $page_name == 'dashboard' ) {
					return true;
				} else {
					return false;
				}
			} else {
				if ( $page_name == 'login' ) {
					return true;
				} else {
					return false;
				}
			}
		}
	}

	/* START ACCOUNT LOGIN FUNCTIONS */
	public function display_account_login() {
		if ( $this->is_page_visible( "login" ) ) {
			$this->display_account_login_page();
		}
	}

	public function display_account_login_page( $page_id = false, $shortcode_atts = array() ) {
		$form_only = false;
		if ( is_array( $shortcode_atts ) && isset( $shortcode_atts['form_only'] ) ) {
			$form_only = (bool) $shortcode_atts['form_only'];
		}
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_account_login.php' ) ) {
			include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_account_login.php' );
		} else {
			include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_account_login.php' );
		}
		do_action( 'wpeasycart_account_login_post' );
	}

	public function display_account_login_form_start() {
		echo "<form action=\"" . esc_url( wpeasycart_links()->get_account_page() ) . "\" method=\"POST\">";
	}

	public function display_account_login_form_end() {
		if ( $this->redirect_login ) {
			echo "<input type=\"hidden\" name=\"ec_custom_login_redirect\" value=\"" . esc_url_raw( wp_unslash( $this->redirect_login ) ) . "\" />";
		}
		if ( isset( $_GET['ec_page'] ) ) {
			echo "<input type=\"hidden\" name=\"ec_goto_page\" value=\"" . esc_attr( sanitize_key( $_GET['ec_page'] ) ) . "\" />";
		}
		if ( isset( $_GET['order_id'] ) ) {
			echo "<input type=\"hidden\" name=\"ec_order_id\" value=\"" . esc_attr( (int) $_GET['order_id'] ) . "\" />";
		}
		if ( isset( $_GET['subscription_id'] ) ) {
			echo "<input type=\"hidden\" name=\"ec_subscription_id\" value=\"" . esc_attr( (int) $_GET['subscription_id'] ) . "\" />";
		}
		echo "<input type=\"hidden\" name=\"ec_account_form_action\" value=\"login\" />";
		echo "<input type=\"hidden\" name=\"ec_account_form_nonce\" value=\"" . esc_attr( wp_create_nonce( 'wp-easycart-account-login' ) ) . "\" />";
		echo "</form>";	
	}

	public function display_account_login_email_input() {
		echo "<input type=\"email\" name=\"ec_account_login_email\" id=\"ec_account_login_email\" class=\"ec_account_login_input_field\" autocomplete=\"off\" autocapitalize=\"off\">";
	}

	public function display_account_login_password_input() {
		echo "<input type=\"password\" name=\"ec_account_login_password\" id=\"ec_account_login_password\" class=\"ec_account_login_input_field\">";
	}

	public function display_account_login_button( $button_text ) {
		echo "<input type=\"submit\" name=\"ec_account_login_button\" id=\"ec_account_login_button\" class=\"ec_account_login_button\" value=\"" . esc_attr( $button_text ) . "\" onclick=\"return ec_account_login_button_click();\">";
	}

	public function display_account_login_forgot_password_link( $link_text ) {
		echo wp_easycart_escape_html( apply_filters( 'wpeasycart_forgot_password_link', "<a href=\"" . esc_url( wpeasycart_links()->get_account_page( 'forgot_password' ) ) . "\" class=\"ec_account_login_link\">" . esc_attr( $link_text ) . "</a>" ) );
	}

	public function display_account_login_create_account_button( $link_text ) {
		echo "<a href=\"" . esc_url( wpeasycart_links()->get_account_page( 'register' ) ) . "\" class=\"ec_account_login_create_account_button\">" . esc_attr( $link_text ) . "</a>";
	}

	/* END ACCOUNT LOGIN FUNCTIONS */

	/* START FORGOT PASSWORD FUNCTIONS */
	public function display_account_forgot_password() {
		if ( $this->is_page_visible( "forgot_password" ) ) {
			$this->display_forgot_password_page();
		}
	}

	public function display_forgot_password_page() {
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_account_forgot_password.php' ) ) {
			include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_account_forgot_password.php' );
		} else {
			include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_account_forgot_password.php' );
		}
	}

	public function display_account_forgot_password_form_start() {
		echo "<form action=\"" . esc_url( wpeasycart_links()->get_account_page() ) . "\" method=\"POST\">";	
	}

	public function display_account_forgot_password_form_end() {
		echo "<input type=\"hidden\" name=\"ec_account_form_action\" value=\"retrieve_password\" />";
		echo "<input type=\"hidden\" name=\"ec_account_form_nonce\" value=\"" . esc_attr( wp_create_nonce( 'wp-easycart-account-retrieve-password' ) ) . "\" />";
		echo "</form>";
	}

	public function display_account_forgot_password_email_input() {
		echo "<input type=\"email\" name=\"ec_account_forgot_password_email\" id=\"ec_account_forgot_password_email\" class=\"ec_account_forgot_password_input_field\">";	
	}

	public function display_account_forgot_password_submit_button( $button_text ) {
		echo "<input type=\"submit\" name=\"ec_account_forgot_password_button\" id=\"ec_account_forgot_password_button\" class=\"ec_account_forgot_password_button\" value=\"" . esc_attr( $button_text ) . "\" onclick=\"return ec_account_forgot_password_button_click();\">";
	}
	/* END FORGOT PASSWORD FUNCTIONS*/

	/* START REGISTER FUNCTIONS */
	public function display_account_register() {
		if ( $this->is_page_visible( "register" ) ) {
			$this->display_register_page();
		}
	}

	public function display_register_page() {
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_account_register.php' ) ) {
			include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_account_register.php' );
		} else {
			include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_account_register.php' );
		}
	}

	public function display_account_register_form_start() {
		echo "<form action=\"" . esc_url( wpeasycart_links()->get_account_page() ) . "\" method=\"POST\">";
		echo "<input type=\"hidden\" name=\"ec_account_form_nonce\" value=\"" . esc_attr( wp_create_nonce( 'wp-easycart-account-register' ) ) . "\" />";
	}

	public function display_account_register_form_end() {
		echo "<input type=\"hidden\" name=\"ec_account_form_action\" value=\"register\"/>";
		echo "</form>";
	}

	public function display_account_register_first_name_input() {
		echo "<input type=\"text\" name=\"ec_account_register_first_name\" id=\"ec_account_register_first_name\" class=\"ec_account_register_input_field\">";
	}

	public function display_account_register_last_name_input() {
		echo "<input type=\"text\" name=\"ec_account_register_last_name\" id=\"ec_account_register_last_name\" class=\"ec_account_register_input_field\">";
	}

	public function display_account_register_zip_input() {
		echo "<input type=\"text\" name=\"ec_account_register_zip\" id=\"ec_account_register_zip\" class=\"ec_account_register_input_field\">";
	}

	public function display_account_register_email_input() {
		echo "<input type=\"email\" name=\"ec_account_register_email\" id=\"ec_account_register_email\" class=\"ec_account_register_input_field\">";
	}

	public function display_account_register_retype_email_input() {
		echo "<input type=\"email\" name=\"ec_account_register_retype_email\" id=\"ec_account_register_retype_email\" class=\"ec_account_register_input_field\">";
	}

	public function display_account_register_password_input() {
		echo "<input type=\"password\" name=\"ec_account_register_password\" id=\"ec_account_register_password\" class=\"ec_account_register_input_field\">";
	}

	public function display_account_register_retype_password_input() {
		echo "<input type=\"password\" name=\"ec_account_register_password_retype\" id=\"ec_account_register_password_retype\" class=\"ec_account_register_input_field\">";
	}

	public function display_account_register_is_subscriber_input() {
		echo "<input type=\"checkbox\" name=\"ec_account_register_is_subscriber\" id=\"ec_account_register_is_subscriber\" class=\"ec_account_register_input_field\" />";	
	}

	public function display_account_register_button( $button_text ) {
		if ( get_option( 'ec_option_require_account_address' ) ) {
			echo "<input type=\"submit\" name=\"ec_account_register_button\" id=\"ec_account_register_button\" class=\"ec_account_register_button\" value=\"" . esc_attr( $button_text ) . "\" onclick=\"return ec_account_register_button_click2();\">";
		} else {
			echo "<input type=\"submit\" name=\"ec_account_register_button\" id=\"ec_account_register_button\" class=\"ec_account_register_button\" value=\"" . esc_attr( $button_text ) . "\" onclick=\"return ec_account_register_button_click();\">";
		}
	}
	/* END REGISTER FUNCTIONS */

	/* START DASHBOARD FUNCTIONS */
	public function display_account_dashboard() {
		if ( $this->is_page_visible( "dashboard" ) ) {
			$this->display_dashboard_page();
		}
	}

	public function display_dashboard_page() {
		if ( isset( $_GET['account_success'] ) && 'login_success' == $_GET['account_success'] ) {
			if ( '' != get_option( 'ec_option_google_ga4_property_id' ) ) {
				if ( get_option( 'ec_option_google_ga4_tag_manager' ) ) {
					echo '<script>
						jQuery( document ).ready( function() {
							dataLayer.push({
								event: "login",
								"userId": "' . esc_attr( $GLOBALS['ec_cart_data']->cart_data->user_id ) . '",
							} );
						} );
					</script>';
				} else {
					echo '<script>
						jQuery( document ).ready( function() {
							gtag( "event", "login", {
								"userId": "' . esc_attr( $GLOBALS['ec_cart_data']->cart_data->user_id ) . '",
							} );
						} );
					</script>';
				}
			}
		}
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_account_dashboard.php' ) ) {
			include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_account_dashboard.php' );
		} else {
			include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_account_dashboard.php' );
		}
	}

	public function display_dashboard_link( $link_text ) {
		echo "<a href=\"" . esc_url( wpeasycart_links()->get_account_page( 'dashboard' ) ) . "\" class=\"ec_account_dashboard_link\">" . esc_attr( $link_text ) . "</a>";	
	}

	public function display_orders_link( $link_text ) {
		echo "<a href=\"" . esc_url( wpeasycart_links()->get_account_page( 'orders' ) ) . "\" class=\"ec_account_dashboard_link\">" . esc_attr( $link_text ) . "</a>";	
	}

	public function display_personal_information_link( $link_text ) {
		echo "<a href=\"" . esc_url( wpeasycart_links()->get_account_page( 'personal_information' ) ) . "\" class=\"ec_account_dashboard_link\">" . esc_attr( $link_text ) . "</a>";
	}

	public function display_billing_information_link( $link_text ) {
		echo "<a href=\"" . esc_url( wpeasycart_links()->get_account_page( 'billing_information' ) ) . "\" class=\"ec_account_dashboard_link\">" . esc_attr( $link_text ) . "</a>";
	}

	public function display_shipping_information_link( $link_text ) {
		echo "<a href=\"" . esc_url( wpeasycart_links()->get_account_page( 'shipping_information' ) ) . "\" class=\"ec_account_dashboard_link\">" . esc_attr( $link_text ) . "</a>";
	}

	public function display_password_link( $link_text ) {
		echo "<a href=\"" . esc_url( wpeasycart_links()->get_account_page( 'password' ) ) . "\" class=\"ec_account_dashboard_link\">" . esc_attr( $link_text ) . "</a>";
	}

	public function display_subscriptions_link( $link_text ) {
		echo "<a href=\"" . esc_url( wpeasycart_links()->get_account_page( 'subscriptions' ) ) . "\" class=\"ec_account_dashboard_link\">" . esc_attr( $link_text ) . "</a>";
	}

	public function display_payment_methods_link( $link_text ) {
		echo "<a href=\"" . esc_url( wpeasycart_links()->get_account_page( 'payment_methods' ) ) . "\" class=\"ec_account_dashboard_link\">" . esc_attr( $link_text ) . "</a>";
	}

	public function display_logout_link( $link_text ) {
		echo "<a href=\"" . esc_url( wpeasycart_links()->get_account_page( 'logout' ) ) . "\" class=\"ec_account_dashboard_link\">" . esc_attr( $link_text ) . "</a>";
	}
	/* END DASHBOARD FUNCTIONS */

	/* START ORDERS FUNCTIONS */
	public function display_account_orders() {
		if ( $this->is_page_visible( "orders" ) ) {
			$this->display_orders_page();
		}
	}

	public function display_orders_page() {
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_account_orders.php' ) )	
			include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_account_orders.php' );
		else
			include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_account_orders.php' );
	}
	/* END ORDERS FUNCTIONS*/

	/* START ORDER DETAILS FUNCTIONS */
	public function display_account_order_details() {
		if ( $this->is_page_visible( "order_details" ) ) {
			$this->display_order_details_page();
		}
	}

	public function display_order_details_page() {
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_account_order_details.php' ) )	
			include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_account_order_details.php' );
		else
			include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_account_order_details.php' );
	}

	public function display_order_detail_product_list() {
		if ( $this->order ) {
			$this->order->display_order_detail_product_list();
		}
	}

	public function display_print_order_icon() {
		if ( $this->order ) {
			if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . "/design/theme/" . get_option( 'ec_option_base_theme' ) . "/ec_account_order_details/print_icon.png" ) ) {
				echo "<a href=\"" . esc_url( wpeasycart_links()->get_account_page( 'print_receipt', array( 'order_id' => $this->order->order_id ) ) ) . "\" target=\"_blank\"><img src=\"" . esc_url( plugins_url( "wp-easycart-data/design/theme/" . get_option( 'ec_option_base_theme' ) . "/ec_account_order_details/print_icon.png", EC_PLUGIN_DATA_DIRECTORY ) ) . "\" alt=\"print\" /></a>";
			} else {
				echo "<a href=\"" . esc_url( wpeasycart_links()->get_account_page( 'print_receipt', array( 'order_id' => $this->order->order_id ) ) ) . "\" target=\"_blank\"><img src=\"" . esc_url( plugins_url( "wp-easycart/design/theme/" . get_option( 'ec_option_base_theme' ) . "/ec_account_order_details/print_icon.png", EC_PLUGIN_DIRECTORY ) ) . "\" alt=\"print\" /></a>";
			}
		}
	}

	public function get_print_order_icon_url() {
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . "/design/theme/" . get_option( 'ec_option_base_theme' ) . "/images/print_icon.png" ) )
			return plugins_url( "wp-easycart-data/design/theme/" . get_option( 'ec_option_base_theme' ) . "/images/print_icon.png", EC_PLUGIN_DATA_DIRECTORY  );
		else
			return plugins_url( "wp-easycart/design/theme/" . get_option( 'ec_option_latest_theme' ) . "/images/print_icon.png", EC_PLUGIN_DIRECTORY  );
	}

	public function display_complete_payment_link() {
		if ( $this->order && $this->order->orderstatus_id == 8 ) {
			echo "<a href=\"" . esc_url( wpeasycart_links()->get_cart_page( 'third_party', array( 'order_id' => (int) $this->order->order_id ) ) ) . "\" class=\"ec_account_complete_order_link\">" . wp_easycart_language()->get_text( 'account_order_details', 'complete_payment' ) . "</a> ";
		}
	}
	/* END ORDER DETAILS FUNCTIONS*/

	/* START PERSONAL INFORMATION FUNCTIONS */
	public function display_account_personal_information() {
		if ( $this->is_page_visible( "personal_information" ) ) {
			$this->display_personal_information_page();
		}
	}

	public function display_personal_information_page() {
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_account_personal_information.php' ) )	
			include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_account_personal_information.php' );
		else
			include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_account_personal_information.php' );
	}

	public function display_account_personal_information_form_start() {
		echo "<form action=\"" . esc_url( wpeasycart_links()->get_account_page() ) . "\" method=\"POST\">";
		echo "<input type=\"hidden\" name=\"ec_account_form_action\" id=\"ec_account_personal_information_form_action\" value=\"update_personal_information\" />";
		echo "<input type=\"hidden\" name=\"ec_account_form_nonce\" value=\"" . esc_attr( wp_create_nonce( 'wp-easycart-account-update-personal-info-' . (int) $GLOBALS['ec_user']->user_id ) ) . "\" />";
	}

	public function display_account_personal_information_form_end() {
		/* 6.0.2: a store's copy of the template from before 6.0.2 has no fields hook; the fields then go at the end of its form. */
		if ( ! did_action( 'wpeasycart_account_personal_information_fields' ) ) {
			do_action( 'wpeasycart_account_personal_information_fields', $this );
		}
		/* 6.0.2: a form without the newsletter box ( Settings: subscriber feature off ) keeps the subscription as it is when saved; it unsubscribed the customer. */
		if ( ! $this->personal_subscriber_input_shown ) {
			echo '<input type="hidden" name="ec_account_personal_information_keep_subscription" value="1" />';
		}
		echo "</form>";
	}

	public function display_account_personal_information_first_name_input() {
		echo "<input type=\"text\" name=\"ec_account_personal_information_first_name\" id=\"ec_account_personal_information_first_name\" class=\"ec_account_personal_information_input_field\" value=\"" . esc_attr( htmlspecialchars( $GLOBALS['ec_user']->first_name, ENT_QUOTES ) ) . "\" />";
	}

	public function display_account_personal_information_last_name_input() {
		echo "<input type=\"text\" name=\"ec_account_personal_information_last_name\" id=\"ec_account_personal_information_last_name\" class=\"ec_account_personal_information_input_field\" value=\"" . esc_attr( htmlspecialchars( $GLOBALS['ec_user']->last_name, ENT_QUOTES ) ) . "\" />";
	}

	public function display_account_personal_information_vat_registration_number_input() {
		echo "<input type=\"text\" name=\"ec_account_personal_information_vat_registration_number\" id=\"ec_account_personal_information_vat_registration_number\" class=\"ec_account_personal_information_input_field\" value=\"" . esc_attr( htmlspecialchars( $GLOBALS['ec_user']->vat_registration_number, ENT_QUOTES ) ) . "\" />";
	}

	public function display_account_personal_information_zip_input() {
		echo "<input type=\"text\" name=\"ec_account_personal_information_zip\" id=\"ec_account_personal_information_zip\" class=\"ec_account_personal_information_input_field\" value=\"" . esc_attr(  htmlspecialchars( $GLOBALS['ec_user']->billing->zip, ENT_QUOTES ) ) . "\" />";
	}

	public function display_account_personal_information_email_input() {
		echo "<input type=\"email\" name=\"ec_account_personal_information_email\" id=\"ec_account_personal_information_email\" class=\"ec_account_personal_information_input_field\" value=\"" . esc_attr( htmlspecialchars( $GLOBALS['ec_user']->email, ENT_QUOTES ) ) . "\" />";
	}

	public function display_account_personal_information_email_other_input() {
		echo "<input type=\"email\" name=\"ec_account_personal_information_email_other\" id=\"ec_account_personal_information_email_other\" class=\"ec_account_personal_information_input_field\" value=\"" . esc_attr( htmlspecialchars( $GLOBALS['ec_user']->email_other, ENT_QUOTES ) ) . "\" />";
	}

	public function display_account_personal_information_is_subscriber_input() {
		$this->personal_subscriber_input_shown = true;
		echo "<input type=\"checkbox\" name=\"ec_account_personal_information_is_subscriber\" id=\"ec_account_personal_information_is_subscriber\" class=\"ec_account_personal_information_input_field\"";
		/* 6.0.2: also ticked when the address joined the list another way ( newsletter widget, popup, checkout ), so saving this form never unsubscribes someone who saw an empty box. */
		if ( $GLOBALS['ec_user']->is_subscriber || $this->mysqli->is_subscribed( $GLOBALS['ec_user']->email ) ) {
			echo ' checked="checked"';
		}
		echo "/>";
	}

	public function display_account_personal_information_update_button( $button_text ) {
		echo "<input type=\"submit\" name=\"ec_account_personal_information_button\" id=\"ec_account_personal_information_button\" class=\"ec_account_personal_information_button\" value=\"" . esc_attr( $button_text ) . "\" onclick=\"return ec_account_personal_information_update_click();\" />";
	}
	public function display_account_personal_information_cancel_link( $button_text ) {
		echo "<a href=\"" . esc_url( wpeasycart_links()->get_account_page( 'dashboard' ) ) . "\" class=\"ec_account_personal_information_link\"><input type=\"button\" name=\"ec_account_personal_information_button\" id=\"ec_account_personal_information_button\" class=\"ec_account_personal_information_button\" value=\"" . esc_attr( $button_text ) . "\" onclick=\"window.location='" . esc_url_raw( wpeasycart_links()->get_account_page( 'dashboard' ) ) . "'\" /></a>";
	}


	/* END PERSONAL INFORMATION FUNCTIONS */

	/* START PASSWORD FUNCTIONS */
	public function display_account_password() {
		if ( $this->is_page_visible( "password" ) ) {
			$this->display_password_page();
		}
	}

	public function display_password_page() {
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_account_password.php' ) )	
			include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_account_password.php' );
		else
			include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_account_password.php' );
	}

	public function display_account_password_form_start() {
		echo "<form action=\"" . esc_url( wpeasycart_links()->get_account_page() ) . "\" method=\"POST\">";
		echo "<input type=\"hidden\" name=\"ec_account_form_action\" id=\"ec_account_password_form_action\" value=\"update_password\" />";
		echo "<input type=\"hidden\" name=\"ec_account_form_nonce\" value=\"" . esc_attr( wp_create_nonce( 'wp-easycart-account-update-password-' . (int) $GLOBALS['ec_user']->user_id ) ) . "\" />";
	}

	public function display_account_password_form_end() {
		echo "</form>";
	}

	public function display_account_password_current_password() {
		echo "<input type=\"password\" name=\"ec_account_password_current_password\" id=\"ec_account_password_current_password\" class=\"ec_account_password_input_field\">";
	}

	public function display_account_password_new_password() {
		echo "<input type=\"password\" name=\"ec_account_password_new_password\" id=\"ec_account_password_new_password\" class=\"ec_account_password_input_field\">";
	}

	public function display_account_password_retype_new_password() {
		echo "<input type=\"password\" name=\"ec_account_password_retype_new_password\" id=\"ec_account_password_retype_new_password\" class=\"ec_account_password_input_field\">";
	}

	public function display_account_password_update_button( $button_text ) {
		echo "<input type=\"submit\" name=\"ec_account_password_button\" id=\"ec_account_password_button\" class=\"ec_account_password_button\" value=\"" . esc_attr( $button_text ) . "\" onclick=\"return ec_account_password_button_click();\" />";
	}
	public function display_account_password_cancel_link( $button_text ) {
		echo "<a href=\"" . esc_url( wpeasycart_links()->get_account_page( 'dashboard' ) ) . "\" class=\"ec_account_password_link\"><input type=\"button\" name=\"ec_account_password_button\" id=\"ec_account_password_button\" class=\"ec_account_password_button\" value=\"" . esc_attr( $button_text ) . "\" onclick=\"window.location='" . esc_url_raw( wpeasycart_links()->get_account_page( 'dashboard' ) ) . "'\" /></a>";
	}

	/* END PASSWORD FUNCTIONS */

	/* START BILLING INFORMATION FUNCTIONS */
	public function display_account_billing_information() {
		if ( $this->is_page_visible( "billing_information" ) ) {
			$this->display_billing_information_page();
		}
	}

	public function display_billing_information_page() {
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_account_billing_information.php' ) )	
			include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_account_billing_information.php' );
		else
			include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_account_billing_information.php' );
	}

	public function display_account_billing_information_form_start() {
		echo "<form action=\"" . esc_url( wpeasycart_links()->get_account_page() ) . "\" method=\"POST\">";
		echo "<input type=\"hidden\" name=\"ec_account_form_action\" id=\"ec_account_billing_information_form_action\" value=\"update_billing_information\" />";
		echo "<input type=\"hidden\" name=\"ec_account_form_nonce\" value=\"" . esc_attr( wp_create_nonce( 'wp-easycart-account-update-billing-info-' . (int) $GLOBALS['ec_user']->user_id ) ) . "\" />";
	}

	public function display_account_billing_information_form_end() {
		echo "</form>";
	}

	public function display_account_billing_information_first_name_input() {
		echo "<input type=\"text\" name=\"ec_account_billing_information_first_name\" id=\"ec_account_billing_information_first_name\" class=\"ec_account_billing_information_input_field\" value=\"" . esc_attr( htmlspecialchars( $GLOBALS['ec_user']->billing->first_name, ENT_QUOTES )  ). "\" />";
	}

	public function display_account_billing_information_last_name_input() {
		echo "<input type=\"text\" name=\"ec_account_billing_information_last_name\" id=\"ec_account_billing_information_last_name\" class=\"ec_account_billing_information_input_field\" value=\"" . esc_attr( htmlspecialchars( $GLOBALS['ec_user']->billing->last_name, ENT_QUOTES ) ) . "\" />";
	}

	public function display_account_billing_information_company_name_input() {
		echo "<input type=\"text\" name=\"ec_account_billing_information_company_name\" id=\"ec_account_billing_information_company_name\" class=\"ec_account_billing_information_input_field\" value=\"" . esc_attr( htmlspecialchars( $GLOBALS['ec_user']->billing->company_name, ENT_QUOTES ) ) . "\" />";
	}

	public function display_vat_registration_number_input() {
		echo "<input type=\"text\" name=\"ec_account_billing_vat_registration_number\" id=\"ec_account_billing_vat_registration_number\" class=\"ec_account_billing_information_input_field\" value=\"" . esc_attr( htmlspecialchars( $GLOBALS['ec_user']->vat_registration_number, ENT_QUOTES ) ) . "\" />";
	}

	public function display_account_billing_information_address_input() {
		echo "<input type=\"text\" name=\"ec_account_billing_information_address\" id=\"ec_account_billing_information_address\" class=\"ec_account_billing_information_input_field\" value=\"" . esc_attr( htmlspecialchars( $GLOBALS['ec_user']->billing->address_line_1, ENT_QUOTES ) ) . "\" />";
	}

	public function display_account_billing_information_address2_input() {
		echo "<input type=\"text\" name=\"ec_account_billing_information_address2\" id=\"ec_account_billing_information_address2\" class=\"ec_account_billing_information_input_field\" value=\"" . esc_attr( htmlspecialchars( $GLOBALS['ec_user']->billing->address_line_2, ENT_QUOTES ) ) . "\" />";
	}

	public function display_account_billing_information_city_input() {
		echo "<input type=\"text\" name=\"ec_account_billing_information_city\" id=\"ec_account_billing_information_city\" class=\"ec_account_billing_information_input_field\" value=\"" . esc_attr( htmlspecialchars( $GLOBALS['ec_user']->billing->city, ENT_QUOTES ) ) . "\" />";
	}

	public function display_account_billing_information_state_input() {

		if ( get_option( 'ec_option_use_smart_states' ) ) {

			// DISPLAY STATE DROP DOWN MENU
			$states = $this->mysqli->get_states();
			$selected_state = $GLOBALS['ec_user']->billing->get_value( "state" );
			$selected_country = $GLOBALS['ec_user']->billing->get_value( "country2" );

			$current_country = "";
			$close_last_state = false;
			$state_found = false;
			$current_state_group = "";
			$close_last_state_group = false;

			foreach ($states as $state) {
				if ( $current_country != $state->iso2_cnt ) {
					if ( $close_last_state ) {
						echo "</select>";
					}
					echo "<select name=\"ec_account_billing_information_state_" . esc_attr( $state->iso2_cnt ) . "\" id=\"ec_account_billing_information_state_" . esc_attr( $state->iso2_cnt ) . "\" class=\"ec_account_billing_information_input_field ec_billing_state_dropdown\"";
					if ( $state->iso2_cnt != $selected_country ) {
						echo " style=\"display:none;\"";
					} else {
						$state_found = true;
					}
					echo ">";

					if ( $state->iso2_cnt == "CA" ) { // Canada
						echo "<option value=\"0\">" . wp_easycart_language()->get_text( "cart_billing_information", "cart_billing_information_select_province" ) . "</option>";
					} else if ( $state->iso2_cnt == "GB" ) { // United Kingdom
						echo "<option value=\"0\">" . wp_easycart_language()->get_text( "cart_billing_information", "cart_billing_information_select_county" ) . "</option>";
					} else if ( $state->iso2_cnt == "US" ) { //USA 
						echo "<option value=\"0\">" . wp_easycart_language()->get_text( "cart_billing_information", "cart_billing_information_select_state" ) . "</option>";
					} else {
						echo "<option value=\"0\">" . wp_easycart_language()->get_text( "cart_billing_information", "cart_billing_information_select_other" ) . "</option>";
					}

					$current_country = $state->iso2_cnt;
					$close_last_state = true;
				}

				if ( $current_state_group != $state->group_sta && $state->group_sta != "" ) {
					if ( $close_last_state_group ) {
						echo "</optgroup>";
					}
					echo "<optgroup label=\"" . esc_attr( $state->group_sta ) . "\">";
					$current_state_group = $state->group_sta;
					$close_last_state_group = true;
				}

				echo "<option value=\"" . esc_attr( $state->code_sta ) . "\"";
				if ( $state->code_sta == $selected_state )
					echo " selected=\"selected\"";
				echo ">" . esc_attr( $state->name_sta ) . "</option>";
			}

			if ( $close_last_state_group ) {
				echo "</optgroup>";
			}

			echo "</select>";

			// DISPLAY STATE TEXT INPUT	
			echo "<input type=\"text\" name=\"ec_account_billing_information_state\" id=\"ec_account_billing_information_state\" class=\"ec_account_billing_information_input_field\" value=\"" . esc_attr( htmlspecialchars( $selected_state, ENT_QUOTES ) ) . "\"";
			if ( $state_found ) {
				echo " style=\"display:none;\"";
			}
			echo " />";

		} else {
			// Use the basic method of old
			if ( get_option( 'ec_option_use_state_dropdown' ) ) {
				$states = $this->mysqli->get_states();
				$selected_state = $GLOBALS['ec_user']->billing->state;
				echo "<select name=\"ec_account_billing_information_state\" id=\"ec_account_billing_information_state\" class=\"ec_account_billing_information_input_field\">";
				echo "<option value=\"0\">" . wp_easycart_language()->get_text( "account_billing_information", "account_billing_information_default_no_state" ) . "</option>";
				foreach ($states as $state) {
					echo "<option value=\"" . esc_attr( $state->code_sta ) . "\"";
					if ( $state->code_sta == $selected_state )
					echo " selected=\"selected\"";
					echo ">" . esc_attr( $state->name_sta ) . "</option>";
				}
				echo "</select>";
			} else {
				echo "<input type=\"text\" name=\"ec_account_billing_information_state\" id=\"ec_account_billing_information_state\" class=\"ec_account_billing_information_input_field\" value=\"" . esc_attr( htmlspecialchars( $GLOBALS['ec_user']->billing->state, ENT_QUOTES ) ) . "\" />";
			}
		}// Close if/else for state display type
	}

	public function display_account_billing_information_zip_input() {
		echo "<input type=\"text\" name=\"ec_account_billing_information_zip\" id=\"ec_account_billing_information_zip\" class=\"ec_account_billing_information_input_field\" value=\"" . esc_attr( htmlspecialchars( $GLOBALS['ec_user']->billing->zip, ENT_QUOTES ) ) . "\" />";
	}

	public function display_account_billing_information_country_input() {
		if ( get_option( 'ec_option_use_country_dropdown' ) ) {
			$countries = $GLOBALS['ec_countries']->countries;
			if ( $GLOBALS['ec_user']->billing->country ) {
				$selected_country = $GLOBALS['ec_user']->billing->country;
			} else if ( count( $countries ) == 1 ) {
				$selected_country = $countries[0]->iso2_cnt;
			} else if ( get_option( 'ec_option_default_country' ) ) {
				$selected_country = get_option( 'ec_option_default_country' );
			} else {
				$selected_country = $GLOBALS['ec_user']->billing->country;
			}
			echo "<select name=\"ec_account_billing_information_country\" id=\"ec_account_billing_information_country\" class=\"ec_account_billing_information_input_field\">";
			echo "<option value=\"0\">" . wp_easycart_language()->get_text( "account_billing_information", "account_billing_information_default_no_country" ) . "</option>";
			foreach ($countries as $country) {
				echo "<option value=\"" . esc_attr( $country->iso2_cnt ) . "\"";
				if ( $country->iso2_cnt == $selected_country ) {
					echo " selected=\"selected\"";
				}
				echo ">" . esc_attr( $country->name_cnt ) . "</option>";
			}
			echo "</select>";
		} else {
			echo "<input type=\"text\" name=\"ec_account_billing_information_country\" id=\"ec_account_billing_information_country\" class=\"ec_account_billing_information_input_field\" value=\"" . esc_attr( htmlspecialchars( $GLOBALS['ec_user']->billing->country, ENT_QUOTES ) ) . "\" />";
		}
	}

	public function display_account_billing_information_phone_input() {
		echo "<input type=\"text\" name=\"ec_account_billing_information_phone\" id=\"ec_account_billing_information_phone\" class=\"ec_account_billing_information_input_field\" value=\"" . esc_attr( htmlspecialchars( $GLOBALS['ec_user']->billing->phone, ENT_QUOTES ) ) . "\" />";
	}

	public function display_account_billing_information_update_button( $button_text ) {
		echo "<input type=\"submit\" name=\"ec_account_billing_information_button\" id=\"ec_account_billing_information_button\" class=\"ec_account_billing_information_button\" value=\"" . esc_attr( $button_text ) . "\" onclick=\"return ec_account_billing_information_update_click();\" />";
	}
	public function display_account_billing_information_cancel_link( $button_text ) {
		echo "<a href=\"" . esc_url( wpeasycart_links()->get_account_page( 'dashboard' ) ) . "\" class=\"ec_account_billing_information_link\">" . "<input type=\"button\" name=\"ec_account_billing_information_button\" id=\"ec_account_billing_information_button\" class=\"ec_account_billing_information_button\" value=\"" . esc_attr( $button_text ) . "\" onclick=\"window.location='" . esc_url_raw( wpeasycart_links()->get_account_page( 'dashboard' ) ) . "'\" /></a>";
	}
	/* END BILLING INFORMATION FUNCTIONS */

	/* START SHIPPING INFORMATION FUNCTIONS */
	public function display_account_shipping_information() {
		if ( $this->is_page_visible( "shipping_information" ) ) {
			$this->display_shipping_information_page();
		}
	}

	public function display_shipping_information_page() {
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_account_shipping_information.php' ) )	
			include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_account_shipping_information.php' );
		else
			include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_account_shipping_information.php' );
	}

	public function display_account_shipping_information_form_start() {
		echo "<form action=\"" . esc_url( wpeasycart_links()->get_account_page() ) . "\" method=\"POST\">";
		echo "<input type=\"hidden\" name=\"ec_account_form_action\" id=\"ec_account_shipping_information_form_action\" value=\"update_shipping_information\" />";
		echo "<input type=\"hidden\" name=\"ec_account_form_nonce\" value=\"" . esc_attr( wp_create_nonce( 'wp-easycart-account-update-shipping-info-' . (int) $GLOBALS['ec_user']->user_id ) ) . "\" />";
	}

	public function display_account_shipping_information_form_end() {
		echo "</form>";
	}

	public function display_account_shipping_information_first_name_input() {
		echo "<input type=\"text\" name=\"ec_account_shipping_information_first_name\" id=\"ec_account_shipping_information_first_name\" class=\"ec_account_shipping_information_input_field\" value=\"" . esc_attr( htmlspecialchars( $GLOBALS['ec_user']->shipping->first_name, ENT_QUOTES ) ) . "\" />";
	}

	public function display_account_shipping_information_last_name_input() {
		echo "<input type=\"text\" name=\"ec_account_shipping_information_last_name\" id=\"ec_account_shipping_information_last_name\" class=\"ec_account_shipping_information_input_field\" value=\"" . esc_attr( htmlspecialchars( $GLOBALS['ec_user']->shipping->last_name, ENT_QUOTES ) ) . "\" />";
	}

	public function display_account_shipping_information_address_input() {
		echo "<input type=\"text\" name=\"ec_account_shipping_information_address\" id=\"ec_account_shipping_information_address\" class=\"ec_account_shipping_information_input_field\" value=\"" . esc_attr( htmlspecialchars( $GLOBALS['ec_user']->shipping->address_line_1, ENT_QUOTES ) ) . "\" />";
	}

	public function display_account_shipping_information_address2_input() {
		echo "<input type=\"text\" name=\"ec_account_shipping_information_address2\" id=\"ec_account_shipping_information_address2\" class=\"ec_account_shipping_information_input_field\" value=\"" . esc_attr( htmlspecialchars( $GLOBALS['ec_user']->shipping->address_line_2, ENT_QUOTES ) ) . "\" />";
	}

	public function display_account_shipping_information_city_input() {
		echo "<input type=\"text\" name=\"ec_account_shipping_information_city\" id=\"ec_account_shipping_information_city\" class=\"ec_account_shipping_information_input_field\" value=\"" . esc_attr( htmlspecialchars( $GLOBALS['ec_user']->shipping->city, ENT_QUOTES ) ) . "\" />";
	}

	public function display_account_shipping_information_state_input() {

		if ( get_option( 'ec_option_use_smart_states' ) ) {

			// DISPLAY STATE DROP DOWN MENU
			$states = $this->mysqli->get_states();
			$selected_state = $GLOBALS['ec_user']->shipping->get_value( "state" );
			$selected_country = $GLOBALS['ec_user']->shipping->get_value( "country2" );

			$current_country = "";
			$close_last_state = false;
			$state_found = false;
			$current_state_group = "";
			$close_last_state_group = false;

			foreach ($states as $state) {
				if ( $current_country != $state->iso2_cnt ) {
					if ( $close_last_state ) {
						echo "</select>";
					}
					echo "<select name=\"ec_account_shipping_information_state_" . esc_attr( $state->iso2_cnt ) . "\" id=\"ec_account_shipping_information_state_" . esc_attr( $state->iso2_cnt ) . "\" class=\"ec_account_shipping_information_input_field ec_shipping_state_dropdown\"";
					if ( $state->iso2_cnt != $selected_country ) {
						echo " style=\"display:none;\"";
					} else {
						$state_found = true;
					}
					echo ">";

					if ( $state->iso2_cnt == "CA" ) { // Canada
						echo "<option value=\"0\">" . wp_easycart_language()->get_text( "cart_shipping_information", "cart_shipping_information_select_province" ) . "</option>";
					} else if ( $state->iso2_cnt == "GB" ) { // United Kingdom
						echo "<option value=\"0\">" . wp_easycart_language()->get_text( "cart_shipping_information", "cart_shipping_information_select_county" ) . "</option>";
					} else if ( $state->iso2_cnt == "US" ) { //USA 
						echo "<option value=\"0\">" . wp_easycart_language()->get_text( "cart_shipping_information", "cart_shipping_information_select_state" ) . "</option>";
					} else {
						echo "<option value=\"0\">" . wp_easycart_language()->get_text( "cart_shipping_information", "cart_shipping_information_select_other" ) . "</option>";
					}

					$current_country = $state->iso2_cnt;
					$close_last_state = true;
				}

				if ( $current_state_group != $state->group_sta && $state->group_sta != "" ) {
					if ( $close_last_state_group ) {
						echo "</optgroup>";
					}
					echo "<optgroup label=\"" . esc_attr( $state->group_sta ) . "\">";
					$current_state_group = $state->group_sta;
					$close_last_state_group = true;
				}

				echo "<option value=\"" . esc_attr( $state->code_sta ) . "\"";
				if ( $state->code_sta == $selected_state )
					echo " selected=\"selected\"";
				echo ">" . esc_attr( $state->name_sta ) . "</option>";
			}

			if ( $close_last_state_group ) {
				echo "</optgroup>";
			}

			echo "</select>";

			// DISPLAY STATE TEXT INPUT	
			echo "<input type=\"text\" name=\"ec_account_shipping_information_state\" id=\"ec_account_shipping_information_state\" class=\"ec_account_shipping_information_input_field\" value=\"" . esc_attr( htmlspecialchars( $selected_state, ENT_QUOTES ) ) . "\"";
			if ( $state_found ) {
				echo " style=\"display:none;\"";
			}
			echo " />";

		} else {
			// Use the basic method of old
			if ( get_option( 'ec_option_use_state_dropdown' ) ) {
				$states = $this->mysqli->get_states();
				$selected_state = $GLOBALS['ec_user']->shipping->state;

				echo "<select name=\"ec_account_shipping_information_state\" id=\"ec_account_shipping_information_state\" class=\"ec_account_shipping_information_input_field\">";
				echo "<option value=\"0\">" . wp_easycart_language()->get_text( "account_shipping_information", "account_shipping_information_default_no_state" ) . "</option>";
				foreach ($states as $state) {
					echo "<option value=\"" . esc_attr( $state->code_sta ) . "\"";
					if ( $state->code_sta == $selected_state )
					echo " selected=\"selected\"";
					echo ">" . esc_attr( $state->name_sta ) . "</option>";
				}
				echo "</select>";
			} else {
				echo "<input type=\"text\" name=\"ec_account_shipping_information_state\" id=\"ec_account_shipping_information_state\" class=\"ec_account_shipping_information_input_field\" value=\"" . esc_attr( htmlspecialchars( $GLOBALS['ec_user']->shipping->state, ENT_QUOTES ) ) . "\" />";
			}
		}// Close if/else for state display type

	}

	public function display_account_shipping_information_zip_input() {
		echo "<input type=\"text\" name=\"ec_account_shipping_information_zip\" id=\"ec_account_shipping_information_zip\" class=\"ec_account_shipping_information_input_field\" value=\"" . esc_attr( htmlspecialchars( $GLOBALS['ec_user']->shipping->zip, ENT_QUOTES ) ) . "\" />";
	}

	public function display_account_shipping_information_country_input() {
		if ( get_option( 'ec_option_use_country_dropdown' ) ) {
			$countries = $GLOBALS['ec_countries']->countries;
			if ( $GLOBALS['ec_user']->shipping->country )
				$selected_country = $GLOBALS['ec_user']->shipping->country;
			else if ( count( $countries ) == 1 )
				$selected_country = $countries[0]->iso2_cnt;
			else if ( get_option( 'ec_option_default_country' ) )
				$selected_country = get_option( 'ec_option_default_country' );
			else
				$selected_country = $GLOBALS['ec_user']->shipping->country;

			echo "<select name=\"ec_account_shipping_information_country\" id=\"ec_account_shipping_information_country\" class=\"ec_account_shipping_information_input_field\">";
			echo "<option value=\"0\">" . wp_easycart_language()->get_text( "account_shipping_information", "account_shipping_information_default_no_country" ) . "</option>";
			foreach ($countries as $country) {
				echo "<option value=\"" . esc_attr( $country->iso2_cnt ) . "\"";
				if ( $country->iso2_cnt == $selected_country ) {
					echo " selected=\"selected\"";
				}
				echo ">" . esc_attr( $country->name_cnt ) . "</option>";
			}
			echo "</select>";
		} else {
			echo "<input type=\"text\" name=\"ec_account_shipping_information_country\" id=\"ec_account_shipping_information_country\" class=\"ec_account_shipping_information_input_field\" value=\"" . esc_attr( htmlspecialchars( $GLOBALS['ec_user']->shipping->country, ENT_QUOTES ) ) . "\" />";
		}
	}

	public function display_account_shipping_information_phone_input() {
		echo "<input type=\"text\" name=\"ec_account_shipping_information_phone\" id=\"ec_account_shipping_information_phone\" class=\"ec_account_shipping_information_input_field\" value=\"" . esc_attr( htmlspecialchars( $GLOBALS['ec_user']->shipping->phone, ENT_QUOTES ) ) . "\" />";
	}

	public function display_account_shipping_information_update_button( $button_text ) {
		echo "<input type=\"submit\" name=\"ec_account_shipping_information_button\" id=\"ec_account_shipping_information_button\" class=\"ec_account_shipping_information_button\" value=\"" . esc_attr( $button_text ) . "\" onclick=\"return ec_account_shipping_information_update_click();\" />";
	}

	public function display_account_shipping_information_cancel_link( $button_text ) {
		echo "<a href=\"" . esc_url( wpeasycart_links()->get_account_page( 'dashboard' ) ) . "\" class=\"ec_account_shipping_information_link\">" ."<input type=\"button\" name=\"ec_account_shipping_information_button\" id=\"ec_account_shipping_information_button\" class=\"ec_account_shipping_information_button\" value=\"" . esc_attr( $button_text ) . "\" onclick=\"window.location='" . esc_url_raw( wpeasycart_links()->get_account_page( 'dashboard' ) ) . "'\" /></a>";
	}
	/* END SHIPPING INFORMATION FUNCTIONS */

	/* START SUBSCRIPTIONS FUNCTIONS */
	public function display_account_subscriptions() {
		if ( $this->is_page_visible( "subscriptions" ) ) {
			$this->display_subscriptions_page();
		}
	}

	public function display_subscriptions_page() {
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_account_subscriptions.php' ) )	
			include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_account_subscriptions.php' );
		else if ( file_exists( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_account_subscriptions.php' ) )
			include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_account_subscriptions.php' );
	}

	public function using_subscriptions() {
		if ( ( get_option( 'ec_option_payment_process_method' ) == "stripe" || get_option( 'ec_option_payment_process_method' ) == "stripe_connect" ) && get_option( 'ec_option_show_account_subscriptions_link' ) ) {
			return true;
		} else {
			return false;
		}
	}
	/* END SUBSCRIPTIONS FUNCTIONS*/

	/* START SUBSCRIPTION DETAILS FUNCTIONS */
	public function display_account_subscription_details() {

		if ( $this->is_page_visible( "subscription_details" ) ) {
			$this->display_subscription_details_page();

		}

	}

	public function display_subscription_details_page() {
		if ( $this->subscription ) {
			if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_account_subscription_details.php' ) )	
				include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_account_subscription_details.php' );
			else if ( file_exists( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_account_subscription_details.php' ) )
				include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_account_subscription_details.php' );

		} else {
			echo '<div style="float:left; width:100%; margin:50px 0; text-align:center;">' . wp_easycart_language()->get_text( 'account_subscriptions', 'account_subscriptions_none_found' ) . '</div>';

		}
	}

	/* END SUBSCRIPTION DETAILS FUNCTIONS */

	/* START PAYMENT METHODS FUNCTIONS */
	public function display_account_payment_methods() {
		if ( $this->is_page_visible( "payment_methods" ) ) {
			if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_account_payment_methods.php' ) )	
				include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_account_payment_methods.php' );
			else if ( file_exists( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_account_payment_methods.php' ) )
				include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_account_payment_methods.php' );
		}
	}
	/* END PAYMENT METHODS FUNCTIONS*/

	/* START PAYMENT METHOD DETAILS FUNCTIONS */
	public function display_account_payment_method_details() {
		if ( $this->is_page_visible( "payment_method_details" ) ) {
			if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_account_payment_method_details.php' ) )	
				include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_account_payment_method_details.php' );
			else if ( file_exists( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_account_payment_method_details.php' ) )
				include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_account_payment_method_details.php' );
		}
	}

	/* END PAYMENT METHOD DETAILS FUNCTIONS */

	/* START FORM ACTION FUNCTIONS */
	public function process_form_action( $action ) {
		wpeasycart_session()->handle_session();
		if ( $action == "login" ) {
			$this->process_login();
		} else if ( $action == "register" ) {
			$this->process_register();
		} else if ( $action == "retrieve_password" ) {
			$this->process_retrieve_password();
		} else if ( $action == "reset_password" ) {
			$this->process_reset_password();
		} else if ( $action == "resend_activation" ) {
			$this->process_resend_activation();
		} else if ( $action == "update_personal_information" ) {
			$this->process_update_personal_information();
		} else if ( $action == "update_password" ) {
			$this->process_update_password();
		} else if ( $action == "update_billing_information" ) {
			$this->process_update_billing_information();
		} else if ( $action == "update_shipping_information" ) {
			$this->process_update_shipping_information();
		} else if ( $action == "logout" ) {
			$this->process_logout();
		} else if ( $action == "update_subscription" ) {
			$this->process_update_subscription();
		} else if ( $action == "cancel_subscription" ) {
			$this->process_cancel_subscription();
		} else if ( $action == "connect_order" ) {
			$this->process_connect_order();
		}
		do_action( 'wpeasycart_user_updated' );
	}

	private function process_login() {
		if ( ! wp_verify_nonce( sanitize_text_field( $_POST['ec_account_form_nonce'] ), 'wp-easycart-account-login' ) ) {
			header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'login', array( 'account_error' => 'invalid_nonce' ) ) ) );
			die();
		}

		$recaptcha_valid = true;
		if ( wp_easycart_recaptcha_ready() ) { // 6.0.2: only once both keys are saved, as the form shows it
			if ( ! isset( $_POST['ec_grecaptcha_response_login'] ) || $_POST['ec_grecaptcha_response_login'] == '' ) {
				header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'login', array( 'account_error' => 'login_failed' ) ) ) );
				die();
			}

			$db = new ec_db_admin();
			$recaptcha_response = sanitize_text_field( $_POST['ec_grecaptcha_response_login'] );

			$data = array(
				"secret" => get_option( 'ec_option_recaptcha_secret_key' ),
				"response" => $recaptcha_response
			);

			$request = new WP_Http;
			$response = $request->request( 
				"https://www.google.com/recaptcha/api/siteverify", 
				array( 
					'method' => 'POST', 
					'body' => http_build_query( $data ),
					'timeout' => 30
				)
			);
			if ( is_wp_error( $response ) ) {
				$error_message = $response->get_error_message();
				$db->insert_response( 0, 1, "GOOGLE RECAPTCHA CURL ERROR", $error_message );
				$response = (object) array( "error" => $error_message );
			} else {
				$response = json_decode( $response['body'] );
				$db->insert_response( 0, 0, "Google Recaptcha Response", print_r( $response, true ) );
			}
			$recaptcha_valid = ( isset( $response->success ) && $response->success ) ? true : false;
		}

		if ( $recaptcha_valid ) {
			if ( isset( $_POST['ec_account_login_email_widget'] ) ) {
				$email = sanitize_email( $_POST['ec_account_login_email_widget'] );
			} else {
				$email = sanitize_email( $_POST['ec_account_login_email'] );
			}

			if ( isset( $_POST['ec_account_login_password_widget'] ) ) {
				$password_typed = (string) $_POST['ec_account_login_password_widget']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- passwords are used as typed, never sanitized ( 6.0.2 ).
			} else {
				$password_typed = (string) $_POST['ec_account_login_password']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- passwords are used as typed, never sanitized ( 6.0.2 ).
			}

			/* 6.0.2: the password as typed ( sign-up and the checkout login use it that way ), then the sanitized form this form
			   compared before, so passwords with < or extra spaces sign in here too. */
			$password = $password_typed;
			$password_hash = wp_easycart_hash_password( $password );
			$password_hash = apply_filters( 'wpeasycart_password_hash', $password_hash, $password );

			do_action( 'wpeasycart_pre_login_attempt', $email );
			$user = $this->mysqli->get_user_login( $email, $password, $password_hash );
			if ( ! $user && sanitize_text_field( $password_typed ) !== $password_typed ) {
				$password = sanitize_text_field( $password_typed );
				$password_hash = apply_filters( 'wpeasycart_password_hash', wp_easycart_hash_password( $password ), $password );
				$user = $this->mysqli->get_user_login( $email, $password, $password_hash );
			}
			/* 6.0.2: a password reset before 6.0.2 was saved without WordPress's slashes; one with ' " or \ still signs in. */
			if ( ! $user && wp_unslash( $password_typed ) !== $password_typed ) {
				$password = wp_unslash( $password_typed );
				$password_hash = apply_filters( 'wpeasycart_password_hash', wp_easycart_hash_password( $password ), $password );
				$user = $this->mysqli->get_user_login( $email, $password, $password_hash );
			}

			if ( $user && $user->user_level == "pending" ) {
				header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'login', array( 'account_error' => 'not_activated' ) ) ) );
				die();
			} else if ( $user ) {
				$GLOBALS['ec_cart_data']->cart_data->billing_first_name = $user->billing_first_name;
				$GLOBALS['ec_cart_data']->cart_data->billing_last_name = $user->billing_last_name;
				$GLOBALS['ec_cart_data']->cart_data->billing_address_line_1 = $user->billing_address_line_1;
				$GLOBALS['ec_cart_data']->cart_data->billing_address_line_2 = $user->billing_address_line_2;
				$GLOBALS['ec_cart_data']->cart_data->billing_city = $user->billing_city;
				$GLOBALS['ec_cart_data']->cart_data->billing_state = $user->billing_state;
				$GLOBALS['ec_cart_data']->cart_data->billing_zip = $user->billing_zip;
				$GLOBALS['ec_cart_data']->cart_data->billing_country = $user->billing_country;
				$GLOBALS['ec_cart_data']->cart_data->billing_phone = $user->billing_phone;

				$GLOBALS['ec_cart_data']->cart_data->shipping_selector = "";
				if ( $user->shipping_first_name != "" ) {
					$GLOBALS['ec_cart_data']->cart_data->shipping_first_name = $user->shipping_first_name;
					$GLOBALS['ec_cart_data']->cart_data->shipping_last_name = $user->shipping_last_name;
					$GLOBALS['ec_cart_data']->cart_data->shipping_address_line_1 = $user->shipping_address_line_1;
					$GLOBALS['ec_cart_data']->cart_data->shipping_address_line_2 = $user->shipping_address_line_2;
					$GLOBALS['ec_cart_data']->cart_data->shipping_city = $user->shipping_city;
					$GLOBALS['ec_cart_data']->cart_data->shipping_state = $user->shipping_state;
					$GLOBALS['ec_cart_data']->cart_data->shipping_zip = $user->shipping_zip;
					$GLOBALS['ec_cart_data']->cart_data->shipping_country = $user->shipping_country;
					$GLOBALS['ec_cart_data']->cart_data->shipping_phone = $user->shipping_phone;

				} else {
					$GLOBALS['ec_cart_data']->cart_data->shipping_first_name = $user->billing_first_name;
					$GLOBALS['ec_cart_data']->cart_data->shipping_last_name = $user->billing_last_name;
					$GLOBALS['ec_cart_data']->cart_data->shipping_address_line_1 = $user->billing_address_line_1;
					$GLOBALS['ec_cart_data']->cart_data->shipping_address_line_2 = $user->billing_address_line_2;
					$GLOBALS['ec_cart_data']->cart_data->shipping_city = $user->billing_city;
					$GLOBALS['ec_cart_data']->cart_data->shipping_state = $user->billing_state;
					$GLOBALS['ec_cart_data']->cart_data->shipping_zip = $user->billing_zip;
					$GLOBALS['ec_cart_data']->cart_data->shipping_country = $user->billing_country;
					$GLOBALS['ec_cart_data']->cart_data->shipping_phone = $user->billing_phone;
				}

				$GLOBALS['ec_cart_data']->cart_data->is_guest = "";
				$GLOBALS['ec_cart_data']->cart_data->guest_key = "";

				$GLOBALS['ec_cart_data']->cart_data->user_id = $user->user_id;
				$GLOBALS['ec_cart_data']->cart_data->email = $email;
				$GLOBALS['ec_cart_data']->cart_data->username = $user->first_name . " " . $user->last_name;
				$GLOBALS['ec_cart_data']->cart_data->first_name = $user->first_name;
				$GLOBALS['ec_cart_data']->cart_data->last_name = $user->last_name;

				$GLOBALS['ec_cart_data']->save_session_to_db();

				// WordPress User Sync 1.x: sign in to the linked WordPress user ( 2.0 does it on wpeasycart_login_success ).
				wp_easycart_wordpress_users::legacy_store_login( $user, $email, $password );

				wp_cache_flush();
				do_action( 'wpeasycart_login_success', $email );

				if ( isset( $_POST['ec_goto_page'] ) && $_POST['ec_goto_page'] == "store" ) {
					header( "location: " . esc_url_raw( $this->store_page ) );
					die();
				} else if ( isset( $_POST['ec_custom_login_redirect'] ) ) {
					/*
					 * 6.0.2: only an address on this site ( or a host added with the allowed_redirect_hosts filter ); the form field
					 * could send a customer anywhere after signing in. A page ID still works. Anything else, or nothing, goes to the
					 * account dashboard. The address is no longer HTML-escaped first ( & became &amp; in the redirect ).
					 */
					$redirect_raw = trim( (string) wp_unslash( $_POST['ec_custom_login_redirect'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below by esc_url_raw() / wp_validate_redirect(); the login nonce is verified at the top of this method.
					$redirect_url = '';
					if ( '' !== $redirect_raw && ctype_digit( $redirect_raw ) ) {
						$redirect_url = (string) get_page_link( (int) $redirect_raw );
					} else if ( '' !== $redirect_raw ) {
						$redirect_url = wp_validate_redirect( esc_url_raw( $redirect_raw ), '' );
					}
					if ( '' === $redirect_url ) {
						$redirect_url = wpeasycart_links()->get_account_page( 'dashboard', array( 'account_success' => 'login_success' ) );
					}
					header( "location: " . esc_url_raw( $redirect_url ) );
					die();
				} else if ( isset( $_POST['ec_goto_page'] ) && $_POST['ec_goto_page'] != "forgot_password" && $_POST['ec_goto_page'] != "register" && $_POST['ec_goto_page'] != "login" ) {
					$atts = array();
					if ( isset( $_POST['ec_order_id'] ) ) {
						$atts['order_id'] = (int) $_POST['ec_order_id'];
					}
					if ( isset( $_POST['ec_subscription_id'] ) ) {
						$atts['subscription_id'] = (int) $_POST['ec_subscription_id'];
					}
					$goto = wpeasycart_links()->get_account_page( htmlspecialchars( sanitize_key( $_POST['ec_goto_page'] ) ), $atts );
					header( "location: " . esc_url_raw( $goto ) );
				} else {
					$page_id = ( isset( $_POST['ec_account_page_id'] ) ) ? (int) $_POST['ec_account_page_id'] : 0;
					$page_content = get_post( $page_id ); /* 6.0.2: 0 ( no field ) is the page posted to, as before; no warning when there is no post. */
					if ( $page_content && preg_match( "/\[ec_account redirect\=[\'\\\"](.*)[\'\\\"]\]/", (string) $page_content->post_content, $matches ) ) {
						header( "location: " . esc_url_raw( $matches[1] ) );
					} else {
						header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'dashboard', array( 'account_success' => 'login_success' ) ) ) );
					}
				}
			} else {
				do_action( 'wpeasycart_login_failed', $email );
				if ( isset( $_POST['ec_goto_page'] ) && $_POST['ec_goto_page'] == "store" ) {
					header( "location: " . $this->store_page . $this->permalink_divider . "ec_page=login&account_error=login_failed" );
				} else {
					$page_id = (int) $_POST['ec_account_page_id'];
					do_action( 'wpeasycart_account_pre_login_failed_redirect', $email, $password );
					header( "location: " . get_permalink( $page_id ) . $this->permalink_divider . "ec_page=login&account_error=login_failed" );
				}
			}
		} else { // close recaptcha check
			header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'login', array( 'account_error' => 'recaptcha_failed' ) ) ) );
			die();
		}
	}

	private function process_register() {
		if ( ! wp_verify_nonce( sanitize_text_field( $_POST['ec_account_form_nonce'] ), 'wp-easycart-account-register' ) ) {
			header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'login', array( 'account_error' => 'invalid_nonce' ) ) ) );
			die();
		}

		$account_page = $this->account_page;
		$permalink_divider = $this->permalink_divider;
		if ( isset( $_POST['ec_account_page_id'] ) ) {
			$page_id = (int) $_POST['ec_account_page_id'];
			$account_page = get_permalink( $page_id );
			if ( substr_count( $account_page, '?' ) ) {
				$permalink_divider = '&';
			} else {
				$permalink_divider = '?';
			}
		}

		if ( isset( $_POST['ec_account_register_email'] ) && isset( $_POST['ec_account_register_password'] ) && $_POST['ec_account_register_email'] != "" && $_POST['ec_account_register_password'] != "" ) {
			$recaptcha_valid = true;
			if ( wp_easycart_recaptcha_ready() ) { // 6.0.2: only once both keys are saved, as the form shows it
				if ( !isset( $_POST['ec_grecaptcha_response_register'] ) || $_POST['ec_grecaptcha_response_register'] == '' ) {
					header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'register', array( 'account_error' => 'register_invalid' ) ) ) );
					die();
				}

				$db = new ec_db_admin();
				$recaptcha_response = sanitize_text_field( $_POST['ec_grecaptcha_response_register'] );
				$data = array(
					"secret"	=> get_option( 'ec_option_recaptcha_secret_key' ),
					"response"	=> $recaptcha_response
				);

				$request = new WP_Http;
				$response = $request->request( 
					"https://www.google.com/recaptcha/api/siteverify", 
					array( 
						'method' => 'POST', 
						'body' => http_build_query( $data ),
						'timeout' => 30
					)
				);
				if ( is_wp_error( $response ) ) {
					$error_message = $response->get_error_message();
					$db->insert_response( 0, 1, "GOOGLE RECAPTCHA CURL ERROR", $error_message );
					$response = (object) array( "error" => $error_message );
				} else {
					$response = json_decode( $response['body'] );
					$db->insert_response( 0, 0, "Google Recaptcha Response", print_r( $response, true ) );
				}

				$recaptcha_valid = ( isset( $response->success ) && $response->success ) ? true : false;
			}

			if ( $recaptcha_valid ) {
				$first_name = "";
				if ( isset( $_POST['ec_account_register_first_name'] ) ) {
					$first_name = sanitize_text_field( $_POST['ec_account_register_first_name'] );
				}
				$last_name = "";
				if ( isset( $_POST['ec_account_register_last_name'] ) ) {
					$last_name = sanitize_text_field( $_POST['ec_account_register_last_name'] );
				}
				$email = sanitize_email( $_POST['ec_account_register_email'] );
				$password = wp_easycart_hash_password( $_POST['ec_account_register_password'] ); // XSS OK, Password Hashed Immediately
				$password = apply_filters( 'wpeasycart_password_hash', $password, sanitize_text_field( $_POST['ec_account_register_password'] ) );

				// Check if account already exists
				if ( wp_easycart_wordpress_users::email_taken( sanitize_email( $_POST['ec_account_register_email'] ) ) ) {
					header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'register', array( 'account_error' => 'register_email_error' ) ) ) );
					die();
				}

				$is_subscriber = false;
				if ( isset( $_POST['ec_account_register_is_subscriber'] ) ) {
					$is_subscriber = true;
				}

				$billing_id = 0;
				$vat_registration_number = "";

				// Insert billing address if enabled
				if ( get_option( 'ec_option_require_account_address' ) ) {
					$billing = array(
						"first_name" => sanitize_text_field( $_POST['ec_account_billing_information_first_name'] ),
						"last_name" => sanitize_text_field( $_POST['ec_account_billing_information_last_name'] ),
						"address" => sanitize_text_field( $_POST['ec_account_billing_information_address'] ),
						"city" => sanitize_text_field( $_POST['ec_account_billing_information_city'] ),
						"zip_code" => sanitize_text_field( $_POST['ec_account_billing_information_zip'] ),
						"country" => sanitize_text_field( $_POST['ec_account_billing_information_country'] ),
					);

					if ( isset( $_POST[ 'ec_account_billing_information_state_' . $billing['country'] ] ) ) {
						$billing['state'] = stripslashes( sanitize_text_field( $_POST['ec_account_billing_information_state_' . sanitize_text_field( $billing['country'] )] ) );
					} else {
						$billing['state'] = stripslashes( sanitize_text_field( $_POST['ec_account_billing_information_state'] ) );
					}

					if ( isset( $_POST['ec_account_billing_information_company_name'] ) ) {
						$billing['company_name'] = stripslashes( sanitize_text_field( $_POST['ec_account_billing_information_company_name'] ) );
					} else {
						$billing['company_name'] = "";
					}

					if ( isset( $_POST['ec_account_billing_vat_registration_number'] ) ) {
						$vat_registration_number = stripslashes( sanitize_text_field( $_POST['ec_account_billing_vat_registration_number'] ) );
					}

					if ( isset( $_POST['ec_account_billing_information_address2'] ) ) {
						$billing['address2'] = stripslashes( sanitize_text_field( $_POST['ec_account_billing_information_address2'] ) );
					} else {
						$billing['address2'] = "";
					}

					if ( isset( $_POST['ec_account_billing_information_phone'] ) ) {
						$billing['phone'] = stripslashes( sanitize_text_field( $_POST['ec_account_billing_information_phone'] ) );
					} else {
						$billing['phone'] = "";
					}

					$billing_id = $this->mysqli->insert_address( $billing["first_name"], $billing["last_name"], $billing["address"], $billing["address2"], $billing["city"], $billing["state"], $billing["zip_code"], $billing["country"], $billing["phone"], $billing["company_name"] );

				}

				if ( isset( $_POST['ec_account_register_user_notes'] ) ) {
					$user_notes = stripslashes( sanitize_textarea_field( $_POST['ec_account_register_user_notes'] ) );
				} else {
					$user_notes = "";
				}

				// Insert the user
				if ( get_option( 'ec_option_require_email_validation' ) ) {
					// Send a validation email here.
					$this->send_validation_email( $email );
					$user_id = $this->mysqli->insert_user( $email, $password, $first_name, $last_name, $billing_id, 0, "pending", $is_subscriber, $user_notes, $vat_registration_number );
				} else {
					$user_id = $this->mysqli->insert_user( $email, $password, $first_name, $last_name, $billing_id, 0, "shopper", $is_subscriber, $user_notes, $vat_registration_number );
				}

				// Update the address user_id
				if ( get_option( 'ec_option_require_account_address' ) ) {
					$this->mysqli->update_address_user_id( $billing_id, $user_id );
				}

				// MyMail Hook. 6.0.2: only with the newsletter box ticked ( it added every new account before ).
				if ( $is_subscriber && function_exists( 'mailster' ) ) {
					$subscriber_id = mailster('subscribers')->add(array(
						'firstname' => $first_name,
						'lastname' => $last_name,
						'email' => $email,
						'status' => 1,
					), false );
				}

				/* 6.0.2: the password as typed, the way it was hashed above, and where the account came from. */
				do_action( 'wpeasycart_account_added', $user_id, $email, $_POST['ec_account_register_password'], 'register' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- passwords are used as typed, never sanitized ( 6.0.2 ).

				// WordPress User Sync 1.x: the WordPress user ( not while the account waits for email confirmation ).
				wp_easycart_wordpress_users::legacy_account_created( $user_id, $email, $_POST['ec_account_register_password'], $first_name, $last_name ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- passwords are used as typed, never sanitized ( 6.0.2 ).

				// Send registration email if needed
				if ( get_option( 'ec_option_send_signup_email' ) ) {
					$headers   = array();
					$headers[] = "MIME-Version: 1.0";
					$headers[] = "Content-Type: text/html; charset=utf-8";
					$headers[] = "From: " . stripslashes( get_option( 'ec_option_password_from_email' ) );
					$headers[] = "Reply-To: " . stripslashes( get_option( 'ec_option_password_from_email' ) );
					$headers[] = "X-Mailer: PHP/" . phpversion();

					$message = wp_easycart_account_register_admin_email_html( $email ); // 6.0.0: shared email design.

					if ( get_option( 'ec_option_use_wp_mail' ) ) {
						wp_mail( stripslashes( get_option( 'ec_option_bcc_email_addresses' ) ), wp_easycart_language()->get_text( "account_register", "account_register_email_title" ), $message, implode("\r\n", $headers) );
					} else {
						$admin_email = stripslashes( get_option( 'ec_option_bcc_email_addresses' ) );
						$subject = wp_easycart_language()->get_text( "account_register", "account_register_email_title" );
						$mailer = new wpeasycart_mailer();
						$mailer->send_customer_email( $admin_email, $subject, $message );
					}
				}

				if ( $user_id ) {
					if ( get_option( 'ec_option_require_email_validation' ) ) {
						header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'login', array( 'account_success' => 'validation_required' ) ) ) );
						die();
					} else {
						$GLOBALS['ec_cart_data']->cart_data->user_id = $user_id;
						$GLOBALS['ec_cart_data']->cart_data->email = $email;
						$GLOBALS['ec_cart_data']->cart_data->username = $first_name . " " . $last_name;
						$GLOBALS['ec_cart_data']->cart_data->first_name = $first_name;
						$GLOBALS['ec_cart_data']->cart_data->last_name = $last_name;
						$GLOBALS['ec_cart_data']->cart_data->is_guest = "";
						$GLOBALS['ec_cart_data']->cart_data->guest_key = "";
						$GLOBALS['ec_cart_data']->save_session_to_db();
						header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'dashboard' ) ) );
						die();
					}
				} else {
					header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'register', array( 'account_error' => 'register_email_error' ) ) ) );
					die();
				}
			} else { // close recaptcha check
				header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'register', array( 'account_error' => 'recaptcha_failed' ) ) ) );
				die();
			}
		} else {
			header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'register', array( 'account_error' => 'register_invalid' ) ) ) );
			die();
		}
	}

	private function process_retrieve_password() {
		if ( ! wp_verify_nonce( sanitize_text_field( $_POST['ec_account_form_nonce'] ), 'wp-easycart-account-retrieve-password' ) ) {
			header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'dashboard', array( 'account_error' => 'invalid_nonce' ) ) ) );
			die();
		}
		$email = sanitize_email( $_POST['ec_account_forgot_password_email'] );

		global $wpdb;
		$user = $wpdb->get_row( $wpdb->prepare( 'SELECT user_id, email, password, first_name, last_name FROM ec_user WHERE email = %s', $email ) );
		if ( $user ) {
			$token     = wp_easycart_generate_password_reset_token( $user );
			$reset_url = wpeasycart_links()->get_account_page( 'reset_password', array( 'ec_reset_key' => $token ) );
			$this->send_password_reset_email( $user, $reset_url );
			do_action( 'wpeasycart_password_reset_requested', $user->user_id );
		}

		header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'login', array( 'account_success' => 'reset_email_sent' ) ) ) );
		die();
	}

	private function process_reset_password() {
		if ( ! isset( $_POST['ec_account_form_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['ec_account_form_nonce'] ), 'wp-easycart-account-reset-password' ) ) {
			header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'forgot_password', array( 'account_error' => 'reset_link_invalid' ) ) ) );
			die();
		}

		$token = isset( $_POST['ec_reset_key'] ) ? sanitize_text_field( wp_unslash( $_POST['ec_reset_key'] ) ) : '';
		$user  = wp_easycart_validate_password_reset_token( $token );
		if ( ! $user ) {
			header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'forgot_password', array( 'account_error' => 'reset_link_invalid' ) ) ) );
			die();
		}

		// Passwords are intentionally not sanitized.
		$new_password    = isset( $_POST['ec_account_reset_password_new_password'] ) ? wp_unslash( $_POST['ec_account_reset_password_new_password'] ) : '';
		$retype_password = isset( $_POST['ec_account_reset_password_retype_new_password'] ) ? wp_unslash( $_POST['ec_account_reset_password_retype_new_password'] ) : '';

		if ( apply_filters( 'wpeasycart_custom_verify_new_password', false, $new_password ) ) {
			do_action( 'wpeasycart_custom_verify_new_password_failed', $new_password );
			header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'reset_password', array( 'ec_reset_key' => $token, 'account_error' => 'password_invalid' ) ) ) );
			die();
		}
		if ( $new_password !== $retype_password ) {
			header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'reset_password', array( 'ec_reset_key' => $token, 'account_error' => 'password_no_match' ) ) ) );
			die();
		}
		$min_length = (int) apply_filters( 'wp_easycart_minimum_password_length', 6 );
		if ( strlen( $new_password ) < $min_length ) {
			header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'reset_password', array( 'ec_reset_key' => $token, 'account_error' => 'password_too_short' ) ) ) );
			die();
		}

		/* 6.0.2: one rule for every store password: it is hashed as WordPress posts it ( slashed ), as sign-up, the password
		   change and both logins do. The reset hashed it unslashed, so a password with ' " or \ never signed in again. */
		$slashed_password = wp_slash( $new_password );
		$password_hash    = wp_easycart_hash_password( $slashed_password );
		$password_hash    = apply_filters( 'wpeasycart_password_hash', $password_hash, $slashed_password );
		$this->mysqli->reset_password( $user->email, $password_hash );
		do_action( 'wpeasycart_password_reset_complete', $user->user_id );

		/* 6.0.2: WordPress User Sync takes it to the linked WordPress user ( slashed, the way WordPress's own forms post it ). */
		wp_easycart_wordpress_users::password_set( $user->user_id, $slashed_password, 'reset' );

		do_action( 'wpeasycart_password_changed', $user->user_id, $password_hash );
		if ( function_exists( 'wp_easycart_maintain_admin_password_backup' ) ) {
			wp_easycart_maintain_admin_password_backup( $user->user_id, $slashed_password );
		}

		header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'login', array( 'account_success' => 'password_reset_success' ) ) ) );
		die();
	}

	private function process_resend_activation() {
		if ( ! isset( $_POST['ec_account_form_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['ec_account_form_nonce'] ), 'wp-easycart-account-resend-activation' ) ) {
			header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'login', array( 'account_error' => 'invalid_nonce' ) ) ) );
			die();
		}

		$email = sanitize_email( $_POST['ec_account_resend_activation_email'] );

		global $wpdb;
		$user = $wpdb->get_row( $wpdb->prepare( 'SELECT email, user_level FROM ec_user WHERE email = %s', $email ) );
		if ( $user && 'pending' == $user->user_level ) {
			wp_easycart_send_activation_email( $user->email );
			do_action( 'wpeasycart_activation_email_resent', $user->email );
		}

		header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'login', array( 'account_success' => 'resend_activation_sent' ) ) ) );
		die();
	}

	public function display_account_resend_activation_form() {
		echo "<form action=\"" . esc_url( wpeasycart_links()->get_account_page() ) . "\" method=\"POST\" class=\"ec_account_resend_activation_form\">";
		echo "<label for=\"ec_account_resend_activation_email\">" . wp_easycart_language()->get_text( 'account_resend_activation', 'account_resend_activation_email_label' ) . "</label>";
		echo "<input type=\"email\" name=\"ec_account_resend_activation_email\" id=\"ec_account_resend_activation_email\" class=\"ec_account_resend_activation_input_field\" value=\"" . ( isset( $_GET['email'] ) ? esc_attr( sanitize_email( wp_unslash( $_GET['email'] ) ) ) : '' ) . "\" />";
		echo "<input type=\"hidden\" name=\"ec_account_form_action\" value=\"resend_activation\" />";
		echo "<input type=\"hidden\" name=\"ec_account_form_nonce\" value=\"" . esc_attr( wp_create_nonce( 'wp-easycart-account-resend-activation' ) ) . "\" />";
		echo "<input type=\"submit\" class=\"ec_account_button\" value=\"" . esc_attr( wp_easycart_language()->get_text( 'account_resend_activation', 'account_resend_activation_button' ) ) . "\" />";
		echo "</form>";
	}

	public function get_reset_password_key() {
		if ( '' !== $this->reset_password_key ) {
			return $this->reset_password_key;
		}
		return isset( $_GET['ec_reset_key'] ) ? sanitize_text_field( wp_unslash( $_GET['ec_reset_key'] ) ) : '';
	}

	public function get_reset_password_user() {
		return wp_easycart_validate_password_reset_token( $this->get_reset_password_key() );
	}

	public function display_reset_password_page() {
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_account_reset_password.php' ) ) {
			include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_account_reset_password.php' );
		} else {
			include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_account_reset_password.php' );
		}
	}

	public function display_account_reset_password_form_start() {
		echo "<form action=\"" . esc_url( wpeasycart_links()->get_account_page() ) . "\" method=\"POST\">";
	}

	public function display_account_reset_password_form_end( $reset_key ) {
		echo "<input type=\"hidden\" name=\"ec_account_form_action\" value=\"reset_password\" />";
		echo "<input type=\"hidden\" name=\"ec_reset_key\" value=\"" . esc_attr( $reset_key ) . "\" />";
		echo "<input type=\"hidden\" name=\"ec_account_form_nonce\" value=\"" . esc_attr( wp_create_nonce( 'wp-easycart-account-reset-password' ) ) . "\" />";
		echo "</form>";
	}

	private function process_update_personal_information() {
		if ( ! wp_verify_nonce( sanitize_text_field( $_POST['ec_account_form_nonce'] ), 'wp-easycart-account-update-personal-info-' . (int) $GLOBALS['ec_user']->user_id ) ) {
			header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'dashboard', array( 'account_error' => 'invalid_nonce' ) ) ) );
			die();
		}
		$this->require_signed_in();
		$old_email = $GLOBALS['ec_cart_data']->cart_data->email;
		$user_id = $GLOBALS['ec_cart_data']->cart_data->user_id;
		$first_name = sanitize_text_field( $_POST['ec_account_personal_information_first_name'] );
		$last_name = sanitize_text_field( $_POST['ec_account_personal_information_last_name'] );
		$email = sanitize_email( $_POST['ec_account_personal_information_email'] );
		/* 6.0.2: a form without the extra email or VAT field ( a setting turned off, or an Elementor account form ) keeps the saved value; it erased it. */
		if ( isset( $_POST['ec_account_personal_information_email_other'] ) ) {
			$email_other = sanitize_email( wp_unslash( $_POST['ec_account_personal_information_email_other'] ) );
		} else {
			$email_other = (string) $GLOBALS['ec_user']->email_other;
		}
		if ( isset( $_POST['ec_account_personal_information_vat_registration_number'] ) ) {
			$vat_registration_number = sanitize_text_field( $_POST['ec_account_personal_information_vat_registration_number'] );
		} else {
			$vat_registration_number = (string) $GLOBALS['ec_user']->vat_registration_number;
		}
		$is_subscriber = ( isset( $_POST['ec_account_personal_information_is_subscriber'] ) && (bool) $_POST['ec_account_personal_information_is_subscriber'] ) ? 1 : 0;
		/*
		 * 6.0.2: a form that had no newsletter box says so ( keep_subscription ): the subscription stays as it is ( null for
		 * ec_db::update_personal_information() ). Without it, saving such a form unsubscribed the customer.
		 */
		if ( ! isset( $_POST['ec_account_personal_information_is_subscriber'] ) && isset( $_POST['ec_account_personal_information_keep_subscription'] ) ) {
			$is_subscriber = null;
		}

		/* 6.0.2: WordPress User Sync refuses an email another WordPress account already uses. */
		if ( '' !== wp_easycart_wordpress_users::email_change_error( $user_id, $email, $old_email ) ) {
			header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'dashboard', array( 'account_error' => 'personal_information_update_error' ) ) ) );
			die();
		}

		/* Subscribes or unsubscribes too; its insert_subscriber() / remove_subscriber() fire the subscriber hooks ( 6.0.2: no second fire here ). */
		$success = $this->mysqli->update_personal_information( $old_email, $user_id, $first_name, $last_name, $email, $is_subscriber, $vat_registration_number, $email_other );

		if ( $success !== false ) {
			$GLOBALS['ec_cart_data']->cart_data->email = $email;
			$GLOBALS['ec_cart_data']->cart_data->username = $first_name . " " . $last_name;
			$GLOBALS['ec_cart_data']->cart_data->first_name = $first_name;
			$GLOBALS['ec_cart_data']->cart_data->last_name = $last_name;
			$GLOBALS['ec_cart_data']->save_session_to_db();
			// WordPress User Sync 1.x: the linked WordPress user takes the new email ( 2.0 follows wpeasycart_account_updated ).
			wp_easycart_wordpress_users::legacy_email_changed( $user_id, $email );
			do_action( 'wpeasycart_account_updated', $user_id );
			header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'dashboard', array( 'account_success' => 'personal_information_updated' ) ) ) );
			die();
		} else {
			header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'dashboard', array( 'account_error' => 'personal_information_update_error' ) ) ) );
			die();
		}
	}

	private function process_update_password() {
		if ( ! wp_verify_nonce( sanitize_text_field( $_POST['ec_account_form_nonce'] ), 'wp-easycart-account-update-password-' . (int) $GLOBALS['ec_user']->user_id ) ) {
			header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'password', array( 'account_error' => 'invalid_nonce' ) ) ) );
			die();
		}
		$this->require_signed_in();
		$user_id = $GLOBALS['ec_user']->user_id;
		if ( apply_filters( 'wpeasycart_custom_verify_new_password', false, $_POST['ec_account_password_new_password'] ) ) { // XSS OK, Password Should not be sanitized
			do_action( 'wpeasycart_custom_verify_new_password_failed', $_POST['ec_account_password_new_password'] ); // XSS OK, Password Should not be sanitized
		} else if ( $_POST['ec_account_password_new_password'] != $_POST['ec_account_password_retype_new_password'] ) { // XSS OK, Password Should not be sanitized
			header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'password', array( 'account_error' => 'password_no_match' ) ) ) );
			die();
		} else {
			$success = $this->mysqli->update_password( 
				$user_id, $_POST['ec_account_password_current_password'],  // XSS OK, Password Should not be sanitized
				$_POST['ec_account_password_retype_new_password'] // XSS OK, Password Should not be sanitized
			);

			if ( $success ) {
				/* 6.0.2: only once the current password was right. WordPress User Sync sets the WordPress password and keeps the
				   customer signed in. */
				wp_easycart_wordpress_users::password_set( $user_id, $_POST['ec_account_password_retype_new_password'], 'change' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- passwords are used as typed, never sanitized ( 6.0.2 ).
				do_action( 'wpeasycart_password_updated', $user_id );
				$GLOBALS['ec_cart_data']->save_session_to_db();
				header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'dashboard', array( 'account_success' => 'password_updated' ) ) ) );
				die();
			} else {
				header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'password', array( 'account_error' => 'password_wrong_current' ) ) ) );
				die();
			}
		}
	}

	private function process_update_billing_information() {
		if ( ! wp_verify_nonce( sanitize_text_field( $_POST['ec_account_form_nonce'] ), 'wp-easycart-account-update-billing-info-' . (int) $GLOBALS['ec_user']->user_id ) ) {
			header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'billing_information', array( 'account_error' => 'invalid_nonce' ) ) ) );
			die();
		}
		$this->require_signed_in();
		$country = stripslashes( sanitize_text_field( $_POST['ec_account_billing_information_country'] ) );
		$first_name = stripslashes( sanitize_text_field( $_POST['ec_account_billing_information_first_name'] ) );
		$last_name = stripslashes( sanitize_text_field( $_POST['ec_account_billing_information_last_name'] ) );
		if ( isset( $_POST['ec_account_billing_information_company_name'] ) ) {
			$company_name = stripslashes( sanitize_text_field( $_POST['ec_account_billing_information_company_name'] ) );
		} else {
			$company_name = "";
		}
		if ( isset( $_POST['ec_account_billing_information_vat_registration_number'] ) ) {
			$vat_registration_number = stripslashes( sanitize_text_field( $_POST['ec_account_billing_information_vat_registration_number'] ) );
		} else {
			/* 6.0.2: a form without the VAT field keeps the saved number ( it was erased on every save ). */
			$vat_registration_number = (string) $GLOBALS['ec_user']->vat_registration_number;
		}
		$address = stripslashes( sanitize_text_field( $_POST['ec_account_billing_information_address'] ) );
		if ( isset( $_POST['ec_account_billing_information_address2'] ) ) {
			$address2 = stripslashes( sanitize_text_field( $_POST['ec_account_billing_information_address2'] ) );
		} else {
			$address2 = "";
		}
		$city = stripslashes( sanitize_text_field( $_POST['ec_account_billing_information_city'] ) );
		if ( isset( $_POST['ec_account_billing_information_state_' . $country] ) ) {
			$state = stripslashes( sanitize_text_field( $_POST['ec_account_billing_information_state_' . $country] ) );
		} else {
			$state = stripslashes( sanitize_text_field( $_POST['ec_account_billing_information_state'] ) );
		}
		$zip = stripslashes( sanitize_text_field( $_POST['ec_account_billing_information_zip'] ) );
		/* 6.0.2: phone collection off, no phone field: keep the saved phone ( an undefined index, and the phone was erased ). */
		$phone = ( isset( $_POST['ec_account_billing_information_phone'] ) ) ? sanitize_text_field( wp_unslash( $_POST['ec_account_billing_information_phone'] ) ) : (string) $GLOBALS['ec_user']->billing->phone;

		$GLOBALS['ec_cart_data']->cart_data->billing_first_name = $first_name;
		$GLOBALS['ec_cart_data']->cart_data->billing_last_name = $last_name;
		$GLOBALS['ec_cart_data']->cart_data->billing_company_name = $company_name;
		$GLOBALS['ec_cart_data']->cart_data->billing_address_line_1 = $address;
		$GLOBALS['ec_cart_data']->cart_data->billing_address_line_2 = $address2;
		$GLOBALS['ec_cart_data']->cart_data->billing_city = $city;
		$GLOBALS['ec_cart_data']->cart_data->billing_state = $state;
		$GLOBALS['ec_cart_data']->cart_data->billing_zip = $zip;
		$GLOBALS['ec_cart_data']->cart_data->billing_country = $country;
		$GLOBALS['ec_cart_data']->cart_data->billing_phone = $phone;

		if ( $first_name == $GLOBALS['ec_user']->billing->first_name && 
			$last_name == $GLOBALS['ec_user']->billing->last_name && 
			$company_name == $GLOBALS['ec_user']->billing->company_name && 
			$vat_registration_number == $GLOBALS['ec_user']->vat_registration_number && 
			$address == $GLOBALS['ec_user']->billing->address_line_1 && 
			$address2 == $GLOBALS['ec_user']->billing->address_line_2 && 
			$city == $GLOBALS['ec_user']->billing->city && 
			$state == $GLOBALS['ec_user']->billing->state && 
			$zip == $GLOBALS['ec_user']->billing->zip && 
			$country == $GLOBALS['ec_user']->billing->country &&
			$phone == $GLOBALS['ec_user']->billing->phone ) {

			$GLOBALS['ec_cart_data']->save_session_to_db();
			header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'dashboard', array( 'account_success' => 'billing_information_updated' ) ) ) );
			die();
		} else {
			$this->mysqli->update_user( $GLOBALS['ec_user']->user_id, $vat_registration_number );
			$address_id = $GLOBALS['ec_user']->billing_id;
			if ( $address_id )
				$success = $this->mysqli->update_user_address( $address_id, $first_name, $last_name, $address, $address2, $city, $state, $zip, $country, $phone, $company_name, $GLOBALS['ec_user']->user_id );
			else {
				$success = $this->mysqli->insert_user_address( $first_name, $last_name, $company_name, $address, $address2, $city, $state, $zip, $country, $phone, $GLOBALS['ec_user']->user_id, "billing" );
			}
			$GLOBALS['ec_cart_data']->save_session_to_db();
			do_action( 'wpeasycart_account_updated', $GLOBALS['ec_user']->user_id );
			if ( $success >= 0 ) {
				header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'dashboard', array( 'account_success' => 'billing_information_updated' ) ) ) );
				die();
			} else {
				header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'billing_information', array( 'account_error' => 'billing_information_error' ) ) ) );
				die();
			}
		}
	}

	private function process_update_shipping_information() {
		if ( ! wp_verify_nonce( sanitize_text_field( $_POST['ec_account_form_nonce'] ), 'wp-easycart-account-update-shipping-info-' . (int) $GLOBALS['ec_user']->user_id ) ) {
			header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'shipping_information', array( 'account_error' => 'invalid_nonce' ) ) ) );
			die();
		}
		$this->require_signed_in();
		$country = stripslashes( sanitize_text_field( $_POST['ec_account_shipping_information_country'] ) );
		$first_name = stripslashes( sanitize_text_field( $_POST['ec_account_shipping_information_first_name'] ) );
		$last_name = stripslashes( sanitize_text_field( $_POST['ec_account_shipping_information_last_name'] ) );
		if ( isset( $_POST['ec_account_shipping_information_company_name'] ) ) {
			$company_name = stripslashes( sanitize_text_field( $_POST['ec_account_shipping_information_company_name'] ) );
		} else {
			$company_name = "";
		}
		$address = stripslashes( sanitize_text_field( $_POST['ec_account_shipping_information_address'] ) );
		if ( isset( $_POST['ec_account_shipping_information_address2'] ) ) {
			$address2 = stripslashes( sanitize_text_field( $_POST['ec_account_shipping_information_address2'] ) );
		} else {
			$address2 = "";
		}
		$city = stripslashes( sanitize_text_field( $_POST['ec_account_shipping_information_city'] ) );
		if ( isset( $_POST['ec_account_shipping_information_state_' . $country] ) ) {
			$state = stripslashes( sanitize_text_field( $_POST['ec_account_shipping_information_state_' . $country] ) );
		} else {
			$state = stripslashes( sanitize_text_field( $_POST['ec_account_shipping_information_state'] ) );
		}
		$zip = stripslashes( sanitize_text_field( $_POST['ec_account_shipping_information_zip'] ) );
		/* 6.0.2: phone collection off, no phone field: keep the saved phone ( an undefined index, and the phone was erased ). */
		$phone = ( isset( $_POST['ec_account_shipping_information_phone'] ) ) ? sanitize_text_field( wp_unslash( $_POST['ec_account_shipping_information_phone'] ) ) : (string) $GLOBALS['ec_user']->shipping->phone;
		/* 6.0.2: the Elementor Shipping Address form shows the account's VAT number; a change there is saved ( it was dropped ). */
		$vat_changed = false;
		if ( isset( $_POST['ec_account_shipping_vat_registration_number'] ) ) {
			$vat_registration_number = sanitize_text_field( wp_unslash( $_POST['ec_account_shipping_vat_registration_number'] ) );
			if ( $vat_registration_number !== (string) $GLOBALS['ec_user']->vat_registration_number ) {
				$this->mysqli->update_user( $GLOBALS['ec_user']->user_id, $vat_registration_number );
				$vat_changed = true;
			}
		}

		$GLOBALS['ec_cart_data']->cart_data->shipping_first_name = $first_name;
		$GLOBALS['ec_cart_data']->cart_data->shipping_last_name = $last_name;
		$GLOBALS['ec_cart_data']->cart_data->shipping_company_name = $company_name;
		$GLOBALS['ec_cart_data']->cart_data->shipping_address_line_1 = $address;
		$GLOBALS['ec_cart_data']->cart_data->shipping_address_line_2 = $address2;
		$GLOBALS['ec_cart_data']->cart_data->shipping_city = $city;
		$GLOBALS['ec_cart_data']->cart_data->shipping_state = $state;
		$GLOBALS['ec_cart_data']->cart_data->shipping_zip = $zip;
		$GLOBALS['ec_cart_data']->cart_data->shipping_country = $country;
		$GLOBALS['ec_cart_data']->cart_data->shipping_phone = $phone;
		if ( ! $vat_changed &&
			$first_name == $GLOBALS['ec_user']->shipping->first_name &&
			$last_name == $GLOBALS['ec_user']->shipping->last_name &&
			$company_name == $GLOBALS['ec_user']->shipping->company_name && 
			$address == $GLOBALS['ec_user']->shipping->address_line_1 && 
			$address2 == $GLOBALS['ec_user']->shipping->address_line_2 && 
			$city == $GLOBALS['ec_user']->shipping->city && 
			$state == $GLOBALS['ec_user']->shipping->state && 
			$zip == $GLOBALS['ec_user']->shipping->zip && 
			$country == $GLOBALS['ec_user']->shipping->country &&
			$phone == $GLOBALS['ec_user']->shipping->phone ) {

			$GLOBALS['ec_cart_data']->save_session_to_db();
			header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'dashboard', array( 'account_success' => 'shipping_information_updated' ) ) ) );
			die();
		} else {
			$address_id = $GLOBALS['ec_user']->shipping_id;
			if ( $address_id )
				$success = $this->mysqli->update_user_address( $address_id, $first_name, $last_name, $address, $address2, $city, $state, $zip, $country, $phone, $company_name, $GLOBALS['ec_user']->user_id );
			else {
				$success = $this->mysqli->insert_user_address( $first_name, $last_name, $company_name, $address, $address2, $city, $state, $zip, $country, $phone, $GLOBALS['ec_user']->user_id, "shipping" );
			}
			$GLOBALS['ec_cart_data']->save_session_to_db();
			do_action( 'wpeasycart_account_updated', $GLOBALS['ec_user']->user_id );
			if ( $success >= 0 ) {
				header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'dashboard', array( 'account_success' => 'shipping_information_updated' ) ) ) );
				die();
			} else {
				header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'shipping_information', array( 'account_error' => 'shipping_information_error' ) ) ) );
				die();
			}
		}
	}

	private function process_logout() {
		$account_logout_url = apply_filters( 'wp_easycart_account_logout_redirect_url', wpeasycart_links()->get_account_page( 'login' ) );
		$wpec_logout_user_id = ( isset( $GLOBALS['ec_cart_data']->cart_data->user_id ) && '' != $GLOBALS['ec_cart_data']->cart_data->user_id ) ? (int) $GLOBALS['ec_cart_data']->cart_data->user_id : 0;
		if ( $wpec_logout_user_id > 0 ) {
			do_action( 'wpeasycart_logout', $wpec_logout_user_id );
		}
		$GLOBALS['ec_cart_data']->cart_data->user_id = "";
		$GLOBALS['ec_cart_data']->cart_data->email = "";
		$GLOBALS['ec_cart_data']->cart_data->username = "";
		$GLOBALS['ec_cart_data']->cart_data->first_name = "";
		$GLOBALS['ec_cart_data']->cart_data->last_name = "";
		$GLOBALS['ec_cart_data']->cart_data->is_guest = "";
		$GLOBALS['ec_cart_data']->cart_data->guest_key = "";
		$GLOBALS['ec_cart_data']->cart_data->billing_first_name = "";
		$GLOBALS['ec_cart_data']->cart_data->billing_last_name = "";
		$GLOBALS['ec_cart_data']->cart_data->billing_company_name = "";
		$GLOBALS['ec_cart_data']->cart_data->billing_address_line_1 = "";
		$GLOBALS['ec_cart_data']->cart_data->billing_address_line_2 = "";
		$GLOBALS['ec_cart_data']->cart_data->billing_city = "";
		$GLOBALS['ec_cart_data']->cart_data->billing_state = "";
		$GLOBALS['ec_cart_data']->cart_data->billing_zip = "";
		$GLOBALS['ec_cart_data']->cart_data->billing_country = "";
		$GLOBALS['ec_cart_data']->cart_data->billing_phone = "";
		$GLOBALS['ec_cart_data']->cart_data->shipping_first_name = "";
		$GLOBALS['ec_cart_data']->cart_data->shipping_last_name = "";
		$GLOBALS['ec_cart_data']->cart_data->shipping_company_name = "";
		$GLOBALS['ec_cart_data']->cart_data->shipping_address_line_1 = "";
		$GLOBALS['ec_cart_data']->cart_data->shipping_address_line_2 = "";
		$GLOBALS['ec_cart_data']->cart_data->shipping_city = "";
		$GLOBALS['ec_cart_data']->cart_data->shipping_state = "";
		$GLOBALS['ec_cart_data']->cart_data->shipping_zip = "";
		$GLOBALS['ec_cart_data']->cart_data->shipping_country = "";
		$GLOBALS['ec_cart_data']->cart_data->shipping_phone = "";
		$GLOBALS['ec_cart_data']->cart_data->shipping_selector = "";
		$GLOBALS['ec_cart_data']->cart_data->shipping_method = "";
		$GLOBALS['ec_cart_data']->cart_data->expedited_shipping = ""; 
		$GLOBALS['ec_cart_data']->cart_data->create_account = "";
		$GLOBALS['ec_cart_data']->cart_data->coupon_code = "";
		$GLOBALS['ec_cart_data']->cart_data->giftcard = "";
		$GLOBALS['ec_cart_data']->cart_data->stripe_paymentintent_id = "";
		$GLOBALS['ec_cart_data']->cart_data->stripe_pi_client_secret = "";
		$GLOBALS['ec_cart_data']->save_session_to_db();
		wp_cache_flush();
		wp_easycart_wordpress_users::store_logout(); /* 6.0.2: ends a Login as Customer, else signs out of WordPress for User Sync 1.x */
		header( "location: " . esc_url_raw( $account_logout_url ) );
	}

	private function process_update_subscription() {
		if ( ! wp_verify_nonce( sanitize_text_field( $_POST['ec_account_form_nonce'] ), 'wp-easycart-account-update-subscription-' . (int) $_POST['subscription_id'] ) ) {
			header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'subscriptions', array( 'account_error' => 'invalid_nonce' ) ) ) );
			die();
		}

		/* 6.0.0: only the owner, only while the subscription can still change plan ( not canceled / ended / past due ), only to a plan the details page offers. */
		$subscription_post_id = ( isset( $_POST['subscription_id'] ) ) ? (int) $_POST['subscription_id'] : 0;
		$subscription_check = $this->get_customer_subscription( $subscription_post_id );
		if ( ! $subscription_check || ! $subscription_check->can_change_plan() || ! isset( $_POST['ec_selected_plan'] ) || ! $subscription_check->is_allowed_plan( (int) $_POST['ec_selected_plan'] ) ) {
			header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'subscription_details', array( 'subscription_id' => $subscription_post_id, 'account_error' => 'subscription_update_failed', 'errcode' => '05' ) ) ) );
			die();
		}

		global $wpdb;
		$products = $this->mysqli->get_product_list( $wpdb->prepare( " WHERE product.product_id = %d", (int) $_POST['ec_selected_plan'] ), "", "", "" );
		if ( count( $products ) > 0 ) {
			$quantity = ( isset( $_POST['ec_quantity'] ) ) ? (int) $_POST['ec_quantity'] : 1;
			if ( get_option( 'ec_option_subscription_one_only' ) || $quantity < 1 ) {
				$quantity = max( 1, (int) $subscription_check->quantity );
			}
			/* 6.0.3: the same change as My Account's Change plan ( wp_easycart_change_subscription_plan() ). This form also made a
			 * Stripe plan of the older kind on every change, and stopped with a fatal error when the store's gateway was not Stripe. */
			if ( class_exists( 'wp_easycart_subscription_changes' ) ) {
				/* 6.0.3: the plan's change rule applies here too ( a change that waits for renewal is scheduled ). */
				$success = wp_easycart_subscription_changes::customer_change( $subscription_post_id, (int) $_POST['ec_selected_plan'], $quantity, $this->user );
			} else {
				$success = ( true === wp_easycart_change_subscription_plan(
					$subscription_post_id,
					(int) $_POST['ec_selected_plan'],
					$quantity,
					array(
						'source' => 'customer',
						'user'   => $this->user,
					)
				) );
			}

			$GLOBALS['ec_cart_data']->save_session_to_db();
			if ( $success ) {
				header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'subscription_details', array( 'subscription_id' => (int) $_POST['subscription_id'], 'account_success' => 'subscription_updated' ) ) ) );
				die();
			} else {
				header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'subscription_details', array( 'subscription_id' => (int) $_POST['subscription_id'], 'account_error' => 'subscription_update_failed', 'errcode' => '03' ) ) ) );
				die();
			}
		} else { // No product has been found error
			header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'subscription_details', array( 'subscription_id' => (int) $_POST['subscription_id'], 'account_error' => 'subscription_update_failed', 'errcode' => '04' ) ) ) );
			die();
		}
	}// End process update subscription

	private function process_cancel_subscription() {
		if ( ! wp_verify_nonce( sanitize_text_field( $_POST['ec_account_form_nonce'] ), 'wp-easycart-account-cancel-subscription-' . (int) $_POST['ec_account_subscription_id'] ) ) {
			header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'subscriptions', array( 'account_error' => 'invalid_nonce' ) ) ) );
			die();
		}

		$subscription_id = (int) $_POST['ec_account_subscription_id'];
		$subscription_row = $this->mysqli->get_subscription_row( $subscription_id );
		/* 6.0.0: only the owner, and only while the subscription is still running. */
		$subscription_check = $this->get_customer_subscription( $subscription_id );
		if ( ! $subscription_check || ! $subscription_check->can_cancel() ) {
			header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'subscription_details', array( 'subscription_id' => (int) $subscription_id, 'account_error' => 'subscription_cancel_failed' ) ) ) );
			die();
		}
		if ( get_option( 'ec_option_payment_process_method' ) == 'stripe' )
			$stripe = new ec_stripe();
		else
			$stripe = new ec_stripe_connect();
		if ( class_exists( 'wp_easycart_subscription_changes' ) ) {
			wp_easycart_subscription_changes::before_cancel( $subscription_id ); /* 6.0.3: a plan change waiting for renewal goes with it */
		}
		$cancel_success = $stripe->cancel_subscription( $this->user, $subscription_row->stripe_subscription_id );
		/* 6.0.3: wpeasycart_subscription_cancelled fires once, below, when Stripe cancelled it ( it fired here too, before the answer, so listeners such as Zapier saw every cancel twice and failed ones as well ). */
		$GLOBALS['ec_cart_data']->save_session_to_db();
		if ( $cancel_success ) {
			do_action( 'wpeasycart_subscription_cancelled', $this->user->user_id, $subscription_id );
			$this->mysqli->cancel_subscription( $subscription_id );
			header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'subscriptions', array( 'account_success' => 'subscription_canceled' ) ) ) );
			die();
		} else {
			header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'subscription_details', array( 'subscription_id' => (int) $subscription_id, 'account_error' => 'subscription_cancel_failed' ) ) ) );
			die();
		}
	}

	/**
	 * Connect Order ( the Elementor account form ): a guest order placed with this account's email joins the account.
	 *
	 * 6.0.2: only through a signed link emailed to the order's address, opened while signed in to this account
	 * ( self::process_order_claim() ). The form used to attach the order at once, and an account's email is never proven
	 * ( it can be changed to any unused address ), so anyone who knew a guest's email and order number could take that
	 * order with its addresses and downloads. The answer is the same whether or not an order matched, so the form cannot
	 * be used to find orders, and requests are limited per account and per visitor address.
	 */
	public function process_connect_order() {
		if ( ! isset( $_POST['ec_account_form_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ec_account_form_nonce'] ) ), 'wp-easycart-account-connect-order' ) ) {
			header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'dashboard', array( 'account_error' => 'invalid_nonce' ) ) ) );
			die();
		}
		$this->require_signed_in();

		$user_id  = (int) $GLOBALS['ec_cart_data']->cart_data->user_id;
		$page_id  = self::order_claim_page( ( isset( $_POST['ec_account_page_id'] ) ) ? (int) $_POST['ec_account_page_id'] : 0 );
		$order_id = ( isset( $_POST['ec_account_connect_order_id'] ) ) ? (int) $_POST['ec_account_connect_order_id'] : 0;

		if ( $order_id <= 0 ) {
			self::order_claim_redirect( $page_id, 'account_error', 'invalid_order_id' );
		}
		if ( ! self::order_claim_allowed( $user_id ) ) {
			self::order_claim_redirect( $page_id, 'account_error', 'order_claim_limit' );
		}

		global $wpdb;
		$email     = (string) $GLOBALS['ec_user']->email;
		$order_row = null;
		if ( '' !== $email ) {
			$draft_status = (int) get_option( 'ec_option_orderstatus_draft', 0 );
			if ( $draft_status > 0 ) {
				$order_row = $wpdb->get_row( $wpdb->prepare( 'SELECT order_id, user_email FROM ec_order WHERE ec_order.user_email = %s AND ec_order.order_id = %d AND ec_order.user_id = 0 AND ec_order.orderstatus_id != %d', $email, $order_id, $draft_status ) );
			} else {
				$order_row = $wpdb->get_row( $wpdb->prepare( 'SELECT order_id, user_email FROM ec_order WHERE ec_order.user_email = %s AND ec_order.order_id = %d AND ec_order.user_id = 0', $email, $order_id ) );
			}
		}
		if ( $order_row ) {
			$claim_order = (int) $order_row->order_id;
			$claim_email = (string) $order_row->user_email;
			if ( function_exists( 'fastcgi_finish_request' ) ) {
				/* Sent once the answer has gone out ( PHP-FPM ), so it takes as long whether or not an order matched. */
				add_action(
					'shutdown',
					function () use ( $claim_order, $claim_email, $user_id, $page_id ) {
						fastcgi_finish_request();
						self::send_order_claim_email( $claim_order, $claim_email, $user_id, $page_id );
					},
					0
				);
			} else {
				self::send_order_claim_email( $claim_order, $claim_email, $user_id, $page_id );
			}
		}
		self::order_claim_redirect( $page_id, 'account_success', 'order_claim_sent' );
	}

	/**
	 * Opens a Connect Order link ( template_redirect, wp_easycart_account_claim_link() in wpeasycart.php ).
	 *
	 * The link must be signed, not expired, for an order still without an account, and opened by the account that asked
	 * for it. Opening it again once the order is connected just says so.
	 *
	 * @since 6.0.2
	 */
	public static function process_order_claim() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the link carries its own HMAC signature, checked below.
		if ( ! isset( $_GET['ec_claim_sig'], $_GET['ec_claim_order'], $_GET['ec_claim_user'], $_GET['ec_claim_expires'] ) ) {
			return;
		}
		$order_id = (int) $_GET['ec_claim_order'];
		$user_id  = (int) $_GET['ec_claim_user'];
		$page_id  = ( isset( $_GET['ec_claim_page'] ) ) ? (int) $_GET['ec_claim_page'] : 0;
		$expires  = (int) $_GET['ec_claim_expires'];
		$sig      = preg_replace( '/[^a-f0-9]/', '', strtolower( sanitize_text_field( wp_unslash( $_GET['ec_claim_sig'] ) ) ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		/* The link's page is only trusted once its signature checks out; until then errors go to the account page. */
		if ( $order_id <= 0 || $user_id <= 0 || $expires < time() || 64 !== strlen( $sig ) ) {
			self::order_claim_redirect( 0, 'account_error', 'order_claim_invalid' );
		}

		global $wpdb;
		$order = $wpdb->get_row( $wpdb->prepare( 'SELECT order_id, user_id, user_email FROM ec_order WHERE ec_order.order_id = %d', $order_id ) );
		if ( ! $order || ! hash_equals( self::order_claim_signature( $order_id, $user_id, $page_id, $expires, (string) $order->user_email ), $sig ) ) {
			self::order_claim_redirect( 0, 'account_error', 'order_claim_invalid' );
		}
		$page_id = self::order_claim_page( $page_id );

		$signed_in = ( isset( $GLOBALS['ec_cart_data']->cart_data->user_id ) ) ? (int) $GLOBALS['ec_cart_data']->cart_data->user_id : 0;
		if ( $signed_in !== $user_id ) {
			self::order_claim_redirect( $page_id, 'account_error', 'order_claim_sign_in' );
		}

		if ( (int) $order->user_id !== $user_id ) {
			if ( 0 !== (int) $order->user_id ) {
				self::order_claim_redirect( $page_id, 'account_error', 'order_claim_invalid' );
			}
			$updated = $wpdb->query( $wpdb->prepare( 'UPDATE ec_order SET user_id = %d WHERE ec_order.order_id = %d AND ec_order.user_id = 0', $user_id, $order_id ) );
			if ( ! $updated ) {
				self::order_claim_redirect( $page_id, 'account_error', 'order_claim_invalid' );
			}
			/* The account's order list is cached for an hour ( ec_db::get_order_list() ). */
			wp_cache_delete( 'wpeasycart-order-list-' . $user_id, 'wpeasycart-orders' );

			/**
			 * A guest order was connected to a customer's account from the storefront ( Connect Order link ).
			 *
			 * @since 6.0.2
			 * @param int $order_id Order.
			 * @param int $user_id  Store account ( ec_user ) the order now belongs to.
			 */
			do_action( 'wp_easycart_order_connected', $order_id, $user_id );

			/* What moving an order to an account on the order screen does: the account's stored totals ( orders, spend, last
			 * order ), the order's timeline entry and the hooks accounting extensions follow. */
			if ( class_exists( 'wp_easycart_order_ledger' ) ) {
				wp_easycart_order_ledger::customer_totals( array( $user_id ) );
			}
			$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log( order_id, order_log_key ) VALUES( %d, "order-user-update" )', $order_id ) );
			$order_log_id = (int) $wpdb->insert_id;
			if ( $order_log_id ) {
				$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "user_id", %s )', $order_log_id, $order_id, $user_id ) );
			}
			/** This action is documented in admin/inc/wp_easycart_admin_orders.php */
			do_action( 'wp_easycart_admin_order_user_changed', $order_id, $user_id, 0 );
			do_action( 'wpeasycart_order_updated', $order_id );
		}
		self::order_claim_redirect( $page_id, 'account_success', 'order_connected' );
	}

	/**
	 * Signature of a Connect Order link ( the order's email is part of it, so a changed order address ends the link ).
	 *
	 * @since 6.0.2
	 * @param int    $order_id    Order.
	 * @param int    $user_id     Account that asked.
	 * @param int    $page_id     Page the customer returns to.
	 * @param int    $expires     Unix time.
	 * @param string $order_email The order's email address.
	 * @return string
	 */
	private static function order_claim_signature( $order_id, $user_id, $page_id, $expires, $order_email ) {
		return hash_hmac( 'sha256', 'wpec-order-claim|' . (int) $order_id . '|' . (int) $user_id . '|' . (int) $page_id . '|' . (int) $expires . '|' . strtolower( trim( (string) $order_email ) ), wpeasycart_session()->get_secret_key() );
	}

	/**
	 * Sends a Connect Order link to the order's email address ( the store's account email settings, like the activation email ).
	 *
	 * @since 6.0.2
	 * @param int    $order_id    Order.
	 * @param string $order_email The order's email address.
	 * @param int    $user_id     Account that asked.
	 * @param int    $page_id     Page the customer returns to.
	 */
	private static function send_order_claim_email( $order_id, $order_email, $user_id, $page_id ) {
		$expires = time() + DAY_IN_SECONDS;
		$page    = ( $page_id > 0 ) ? get_permalink( $page_id ) : false;
		$base    = ( $page ) ? $page : wpeasycart_links()->get_account_page();
		$url     = add_query_arg(
			array(
				'ec_claim_order'   => (int) $order_id,
				'ec_claim_user'    => (int) $user_id,
				'ec_claim_page'    => ( $page ) ? (int) $page_id : 0,
				'ec_claim_expires' => $expires,
				'ec_claim_sig'     => self::order_claim_signature( $order_id, $user_id, ( $page ) ? $page_id : 0, $expires, $order_email ),
			),
			$base
		);

		$title   = (string) self::account_message_text( 'account_connect_order_email', 'account_connect_order_email_title' );
		$intro   = str_replace( '[order_id]', (string) (int) $order_id, (string) self::account_message_text( 'account_connect_order_email', 'account_connect_order_email_message' ) );
		$button  = (string) self::account_message_text( 'account_connect_order_email', 'account_connect_order_email_link' );
		$message = wp_kses_post( $intro ) . '<br /><a href="' . esc_url( $url ) . '" target="_blank">' . esc_html( $button ) . '</a>';
		if ( class_exists( 'wp_easycart_email_design' ) ) {
			$ed      = 'wp_easycart_email_design';
			$body    = $ed::get_paragraph( wp_kses_post( $intro ) );
			$body   .= $ed::get_button( $url, $button, array( 'margin' => '4px 0 20px 0' ) );
			$body   .= $ed::get_paragraph(
				esc_html__( 'If the button does not work, copy this link into your browser:', 'wp-easycart' ) . '<br /><a href="' . esc_url( $url ) . '" target="_blank" style="' . esc_attr( $ed::css( 'link' ) ) . 'word-break:break-all;">' . esc_html( $url ) . '</a>',
				array(
					'tone'   => 'small',
					'margin' => '0',
				)
			);
			$message = $ed::wrap(
				$body,
				array(
					'title'     => $title,
					'heading'   => $title,
					'preheader' => wp_strip_all_tags( $intro ),
				)
			);
		}

		$headers   = array();
		$headers[] = 'MIME-Version: 1.0';
		$headers[] = 'Content-Type: text/html; charset=utf-8';
		$headers[] = 'From: ' . stripslashes( get_option( 'ec_option_password_from_email' ) );
		$headers[] = 'Reply-To: ' . stripslashes( get_option( 'ec_option_password_from_email' ) );
		$headers[] = 'X-Mailer: PHP/' . phpversion();

		$email_send_method = (string) apply_filters( 'wpeasycart_email_method', get_option( 'ec_option_use_wp_mail' ) );
		if ( '1' === $email_send_method ) {
			wp_mail( $order_email, $title, $message, implode( "\r\n", $headers ) );
		} elseif ( '0' === $email_send_method || '' === $email_send_method ) {
			$mailer = new wpeasycart_mailer();
			$mailer->send_customer_email( $order_email, $title, $message );
		} else {
			/**
			 * The store sends its own emails ( wpeasycart_email_method ): the Connect Order link email.
			 *
			 * @since 6.0.2
			 */
			do_action( 'wpeasycart_custom_order_claim_email', stripslashes( get_option( 'ec_option_password_from_email' ) ), $order_email, '', $title, $message );
		}
	}

	/**
	 * Connect Order request limit: 5 an hour per account, 10 an hour per visitor address.
	 *
	 * Each counter keeps a fixed hour from its first request ( the window start is stored with the count; a request does not
	 * restart the hour ), and the check and count happen under a MySQL named lock so parallel requests cannot all slip
	 * through. No lock within 3 seconds counts as over the limit.
	 *
	 * @since 6.0.2
	 * @param int $user_id Account.
	 * @return bool Whether this request may go ahead ( and it is counted ).
	 */
	private static function order_claim_allowed( $user_id ) {
		global $wpdb;
		$ip = '';
		if ( class_exists( 'wp_easycart_checkout_guard' ) && method_exists( 'wp_easycart_checkout_guard', 'client_ip' ) ) {
			$ip = (string) wp_easycart_checkout_guard::client_ip();
		} elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		$limits = array( 'wpec_order_claim_u_' . (int) $user_id => 5 );
		if ( '' !== $ip ) {
			$limits[ 'wpec_order_claim_ip_' . md5( $ip ) ] = 10;
		}

		if ( 1 !== (int) $wpdb->get_var( "SELECT GET_LOCK( 'wpec_order_claim', 3 )" ) ) {
			return false;
		}
		$now     = time();
		$allowed = true;
		$windows = array();
		foreach ( $limits as $key => $limit ) {
			$window = get_transient( $key );
			if ( ! is_array( $window ) || ! isset( $window['start'], $window['count'] ) || $now - (int) $window['start'] >= HOUR_IN_SECONDS ) {
				$window = array(
					'start' => $now,
					'count' => 0,
				);
			}
			if ( (int) $window['count'] >= $limit ) {
				$allowed = false;
				break;
			}
			$windows[ $key ] = $window;
		}
		if ( $allowed ) {
			foreach ( $windows as $key => $window ) {
				$window['count'] = (int) $window['count'] + 1;
				set_transient( $key, $window, max( 1, HOUR_IN_SECONDS - ( $now - (int) $window['start'] ) ) );
			}
		}
		$wpdb->query( "SELECT RELEASE_LOCK( 'wpec_order_claim' )" );
		return $allowed;
	}

	/**
	 * A page a Connect Order answer may return to: published only ( a draft or private post's address would give away its
	 * slug ), else 0 ( the account page ).
	 *
	 * @since 6.0.2
	 * @param int $page_id Post ID from the form or a signed link.
	 * @return int
	 */
	private static function order_claim_page( $page_id ) {
		$page_id = (int) $page_id;
		return ( $page_id > 0 && 'publish' === get_post_status( $page_id ) ) ? $page_id : 0;
	}

	/**
	 * Back to the page the Connect Order form was on ( else the account dashboard ) with a message code.
	 *
	 * @since 6.0.2
	 * @param int    $page_id Page.
	 * @param string $key     account_success | account_error.
	 * @param string $code    Message code.
	 */
	private static function order_claim_redirect( $page_id, $key, $code ) {
		$page_id = self::order_claim_page( $page_id );
		$page    = ( $page_id > 0 ) ? get_permalink( $page_id ) : false;
		$url     = ( $page ) ? add_query_arg( $key, $code, $page ) : wpeasycart_links()->get_account_page( 'dashboard', array( $key => $code ) );
		header( 'location: ' . esc_url_raw( $url ) );
		die();
	}

	/**
	 * Stops an account update from a visitor who is not signed in ( 6.0.2: the Elementor account forms print these forms to
	 * every visitor; an anonymous post created stray addresses and ran the account-updated hooks for no account ).
	 */
	private function require_signed_in() {
		if ( ! isset( $GLOBALS['ec_cart_data']->cart_data->user_id ) || (int) $GLOBALS['ec_cart_data']->cart_data->user_id <= 0 ) {
			header( 'location: ' . esc_url_raw( wpeasycart_links()->get_account_page( 'login' ) ) );
			die();
		}
	}

	/**
	 * An account message or email phrase from the language file, with an English default for the phrases added in 6.0.2
	 * ( a store's saved language data gets them on its next language update ).
	 *
	 * @since 6.0.2
	 * @param string $section Language section.
	 * @param string $key     Phrase key.
	 * @return string|null The phrase ( null when neither exists, as get_text() ).
	 */
	public static function account_message_text( $section, $key ) {
		$text = wp_easycart_language()->get_text( $section, $key );
		if ( null !== $text && '' !== $text ) {
			return $text;
		}
		$defaults = array(
			'ec_success'                  => array(
				'order_claim_sent' => __( 'If that order was placed with your email address, we sent a link to it. Open the link while you are signed in to add the order to your account.', 'wp-easycart' ),
			),
			'ec_errors'                   => array(
				'order_claim_invalid'  => __( 'That link is not valid or has expired. Ask for a new one from your account.', 'wp-easycart' ),
				'order_claim_sign_in'  => __( 'Sign in to the account that asked for this link, then open the link again.', 'wp-easycart' ),
				'order_claim_limit'    => __( 'Too many requests. Please try again later.', 'wp-easycart' ),
				'download_unavailable' => __( 'This download is not available. Downloads open once an order is paid, and end if it is refunded or cancelled.', 'wp-easycart' ),
			),
			'account_connect_order_email' => array(
				'account_connect_order_email_title'   => __( 'Add your order to your account', 'wp-easycart' ),
				'account_connect_order_email_message' => __( 'You asked to add order [order_id] to your account with us. Open the link below while you are signed in to that account. If you did not ask for this, you can ignore this email.', 'wp-easycart' ),
				'account_connect_order_email_link'    => __( 'Add the order to my account', 'wp-easycart' ),
			),
		);
		return ( isset( $defaults[ $section ][ $key ] ) ) ? $defaults[ $section ][ $key ] : $text;
	}

	/* END FORM ACTION FUNCTIONS */
	private function send_password_reset_email( $user, $reset_url ) {
		$email = $user->email;

		$email_logo_url = get_option( 'ec_option_email_logo' );

		$storepageid = get_option('ec_option_storepage');
		if ( function_exists( 'icl_object_id' ) ) {
			$storepageid = icl_object_id( $storepageid, 'page', true, ICL_LANGUAGE_CODE );
		}
		$store_page = get_permalink( $storepageid );
		if ( class_exists( "WordPressHTTPS" ) && isset( $_SERVER['HTTPS'] ) ) {
			$https_class = new WordPressHTTPS();
			$store_page = $https_class->makeUrlHttps( $store_page );
		}

		if ( substr_count( $store_page, '?' ) ) {
			$permalink_divider = "&";
		} else {
			$permalink_divider = "?";
		}

		// Build the email body ($reset_url and $user are available to the template).
		ob_start();
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_account_retrieve_password_email.php' ) )	
			include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_account_retrieve_password_email.php' );	
		else
			include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_account_retrieve_password_email.php' );
		$message = ob_get_contents();
		ob_end_clean();

		$headers   = array();
		$headers[] = "MIME-Version: 1.0";
		$headers[] = "Content-Type: text/html; charset=utf-8";
		$headers[] = "From: " . stripslashes( get_option( 'ec_option_password_from_email' ) );
		$headers[] = "Reply-To: " . stripslashes( get_option( 'ec_option_password_from_email' ) );
		$headers[] = "X-Mailer: PHP/" . phpversion();

		$email_send_method = get_option( 'ec_option_use_wp_mail' );
		$email_send_method = apply_filters( 'wpeasycart_email_method', $email_send_method );

		if ( $email_send_method == "1" ) {
			wp_mail( $email, wp_easycart_language()->get_text( "account_forgot_password_email", "account_forgot_password_email_title" ), $message, implode("\r\n", $headers));

		} else if ( $email_send_method == "0" ) {
			$to = $email;
			$subject = wp_easycart_language()->get_text( "account_forgot_password_email", "account_forgot_password_email_title" );
			$mailer = new wpeasycart_mailer();
			$mailer->send_customer_email( $to, $subject, $message );

		} else {
			do_action( 'wpeasycart_custom_forgot_password_email', stripslashes( get_option( 'ec_option_password_from_email' ) ), $email, "", wp_easycart_language()->get_text( "account_forgot_password_email", "account_forgot_password_email_title" ), $message );

		}

	}

	private function get_random_password() {
		$rand_chars = array( "A", "B", "C", "D", "E", "F", "G", "H", "I", "J" );
		$rand_password = $rand_chars[ rand( 0, 9 ) ] . $rand_chars[ rand( 0, 9 ) ] . $rand_chars[ rand( 0, 9 ) ] . $rand_chars[ rand( 0, 9 ) ] . rand( 0, 9 ) . rand( 0, 9 ) . rand( 0, 9 ) . rand( 0, 9 ) . rand( 0, 9 );
		return $rand_password;
	}

	public function send_validation_email( $email ) {
		wp_easycart_send_activation_email( $email );
	}

	public function ec_display_card_holder_name_input() {
		echo "<input type=\"text\" name=\"ec_card_holder_name\" id=\"ec_card_holder_name\" class=\"ec_cart_payment_information_input_text\" value=\"\" />";
	}

	public function ec_display_card_number_input() {
		echo "<input type=\"text\" name=\"ec_card_number\" id=\"ec_card_number\" class=\"ec_cart_payment_information_input_text\" value=\"\" />";
	}

	public function ec_display_card_expiration_month_input( $select_text ) {
		echo "<select name=\"ec_expiration_month\" id=\"ec_expiration_month\" class=\"ec_cart_payment_information_input_select\">";
		echo "<option value=\"0\">" . esc_attr( $select_text ) . "</option>";
		for ( $i=1; $i<=12; $i++ ) {
			echo "<option value=\"";
			if ( $i<10 )										$month = "0" . $i;
			else											$month = $i;
			echo esc_attr( $month ) . "\">" . esc_attr( $month ) . "</option>";
		}
		echo "</select>";
	}

	public function ec_display_card_expiration_year_input( $select_text ) {
		echo "<select name=\"ec_expiration_year\" id=\"ec_expiration_year\" class=\"ec_cart_payment_information_input_select\">";
		echo "<option value=\"0\">" . esc_attr( $select_text ) . "</option>";
		for ( $i=date( 'Y' ); $i < date( 'Y' ) + 15; $i++ ) {
			echo "<option value=\"" . esc_attr( $i ) . "\">" . esc_attr( $i ) . "</option>";	
		}
		echo "</select>";
	}

	public function ec_display_card_security_code_input() {
		echo "<input type=\"text\" name=\"ec_security_code\" id=\"ec_security_code\" class=\"ec_cart_payment_information_input_select\" value=\"\" />";
	}

	/**
	 * The subscription when it belongs to the signed-in customer.
	 *
	 * @since 6.0.0
	 * @param int $subscription_id Subscription.
	 * @return ec_subscription|false
	 */
	private function get_customer_subscription( $subscription_id ) {
		$user_id = ( isset( $GLOBALS['ec_cart_data']->cart_data->user_id ) ) ? (int) $GLOBALS['ec_cart_data']->cart_data->user_id : 0;
		if ( $user_id <= 0 || (int) $subscription_id <= 0 ) {
			return false;
		}
		$subscription_row = $this->mysqli->get_subscription_row( (int) $subscription_id );
		if ( ! $subscription_row || (int) $subscription_row->user_id != $user_id ) {
			return false;
		}
		return new ec_subscription( $subscription_row, true );
	}

	public function display_subscription_update_form_start() {
		echo "<form action=\"" . esc_url( wpeasycart_links()->get_account_page() ) . "\" method=\"POST\" id=\"ec_submit_update_form\">";
	}

	public function display_subscription_update_form_end() {
		echo "<input type=\"hidden\" name=\"stripe_subscription_id\" id=\"stripe_subscription_id\" value=\"" . esc_attr( $this->subscription->get_stripe_id() ) . "\" />";
		echo "<input type=\"hidden\" name=\"subscription_id\" id=\"subscription_id\" value=\"" . esc_attr( $this->subscription->subscription_id ) . "\" />";
		echo "<input type=\"hidden\" name=\"ec_account_form_action\" value=\"update_subscription\" />";
		echo "<input type=\"hidden\" name=\"ec_account_form_nonce\" value=\"" . esc_attr( wp_create_nonce( 'wp-easycart-account-update-subscription-' . (int) $this->subscription->subscription_id ) ) . "\" />";
		echo "</form>";
	}

	public function ec_account_display_credit_card_images() {

		/* Fallback only */

	}

	public function ec_account_display_card_holder_name_hidden_input() {
		echo "<input type=\"hidden\" name=\"ec_card_holder_name\" id=\"ec_card_holder_name\" class=\"ec_cart_payment_information_input_text\" value=\"" . esc_attr( $GLOBALS['ec_user']->billing->first_name . " " . $GLOBALS['ec_user']->billing->last_name ) . "\" />";
	}

	private function sanatize_card_number( $card_number ) {

		return preg_replace( "/[^0-9]/", "", $card_number );

	}

	private function get_payment_type( $card_number ) {

		if ( preg_match( "^5[1-5][0-9]{14}$", $card_number ) )
			return "mastercard";
		else if ( preg_match( "^4[0-9]{12}([0-9]{3})?$", $card_number ) )
			return "visa";
		else if ( preg_match( "^3[47][0-9]{13}$", $card_number ) )
			return "amex";
		else if ( preg_match( "^3(0[0-5]|[68][0-9])[0-9]{11}$", $card_number ) )
			return "diners";
		else if ( preg_match( "^6011[0-9]{12}$", $card_number ) )
			return "discover";
		else if ( preg_match( "^(3[0-9]{4}|2131|1800)[0-9]{11}$", $card_number ) )
			return "jcb";	
		else
			return "Credit Card";

	}

	public function get_payment_image_source( $image ) {

		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . "/design/theme/" . get_option( 'ec_option_base_theme' ) . "/images/" . $image ) ) {
			return plugins_url( "/wp-easycart-data/design/theme/" . get_option( 'ec_option_base_theme' ) . "/images/" . $image, EC_PLUGIN_DATA_DIRECTORY );
		} else {
			return plugins_url( "/wp-easycart/design/theme/" . get_option( 'ec_option_latest_theme' ) . "/images/" . $image, EC_PLUGIN_DIRECTORY );
		}

	}

	public function ec_cart_display_card_holder_name_hidden_input() {
		echo "<input type=\"hidden\" name=\"ec_card_holder_name\" id=\"ec_card_holder_name\" class=\"ec_cart_payment_information_input_text\" value=\"" . esc_attr( htmlspecialchars( $GLOBALS['ec_user']->billing->first_name, ENT_QUOTES ) . " " . htmlspecialchars( $GLOBALS['ec_user']->billing->last_name, ENT_QUOTES ) ) . "\" />";
	}

	public function ec_cart_display_card_number_input() {
		echo "<input type=\"text\" name=\"ec_card_number\" id=\"ec_card_number\" class=\"ec_cart_payment_information_input_text\" value=\"\" autocomplete=\"off\" />";
	}

	public function ec_cart_display_card_expiration_month_input( $select_text ) {
		echo "<select name=\"ec_expiration_month\" id=\"ec_expiration_month\" class=\"ec_cart_payment_information_input_select\" autocomplete=\"off\">";
		echo "<option value=\"0\">" . esc_attr( $select_text ) . "</option>";
		for ( $i=1; $i<=12; $i++ ) {
			echo "<option value=\"";
			if ( $i<10 )										$month = "0" . $i;
			else											$month = $i;
			echo esc_attr( $month ) . "\">" . esc_attr( $month ) . "</option>";
		}
		echo "</select>";
	}

	public function ec_cart_display_card_expiration_year_input( $select_text ) {
		echo "<select name=\"ec_expiration_year\" id=\"ec_expiration_year\" class=\"ec_cart_payment_information_input_select\" autocomplete=\"off\">";
		echo "<option value=\"0\">" . esc_attr( $select_text ) . "</option>";
		for ( $i=date( 'Y' ); $i < date( 'Y' ) + 15; $i++ ) {
			echo "<option value=\"" . esc_attr( $i ) . "\">" . esc_attr( $i ) . "</option>";	
		}
		echo "</select>";
	}

	public function ec_cart_display_card_security_code_input() {
		echo "<input type=\"text\" name=\"ec_security_code\" id=\"ec_security_code\" class=\"ec_cart_payment_information_input_text\" value=\"\" autocomplete=\"off\" />";
	}

	public function get_stripe_intent_client_secret() {
		/* 6.0.2: checkout protection. A paused customer, or one owing a human check, gets no setup intent ( Stripe
		   would check every card typed into it ); the protection script shows the pause or the check instead. */
		if ( class_exists( 'wp_easycart_checkout_guard' ) && ! wp_easycart_checkout_guard::card_update_allowed() ) {
			return '';
		}
		if ( get_option( 'ec_option_payment_process_method' ) == 'stripe' ) {
			$stripe = new ec_stripe();
		} else {
			$stripe = new ec_stripe_connect();
		}

		$response = $stripe->create_setup_intent( $GLOBALS['ec_user']->stripe_customer_id );
		if ( isset( $response ) && is_object( $response ) && isset( $response->id ) && class_exists( 'wp_easycart_checkout_guard' ) ) {
			wp_easycart_checkout_guard::map_setup_intent( $response->id );
		}
		return ( isset( $response ) && is_object( $response ) && isset( $response->client_secret ) ) ? $response->client_secret : '';
	}

}
