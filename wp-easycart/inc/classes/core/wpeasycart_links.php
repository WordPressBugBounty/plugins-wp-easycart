<?php
if ( ! class_exists( 'wpeasycart_links' ) ) :
class wpeasycart_links {
	public $store_page;
	public $account_page;
	public $cart_page;
	public $permalink_divider_account;
	public $permalink_divider_cart;
	
	protected static $_instance = null;
	
	public static function instance() {
		if( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	function __construct() { }

	function init_links() {
		$accountpageid = (int) apply_filters( 'wp_easycart_account_page_id', get_option( 'ec_option_accountpage' ) );
		$cartpageid = (int) get_option( 'ec_option_cartpage' );

		if ( function_exists( 'icl_object_id' ) ) {
			$accountpageid = (int) icl_object_id( $accountpageid, 'page', true, ICL_LANGUAGE_CODE );
			$cartpageid = (int) icl_object_id( $cartpageid, 'page', true, ICL_LANGUAGE_CODE );
		}

		$this->account_page = get_permalink( $accountpageid );
		$this->cart_page = get_permalink( $cartpageid );

		if ( class_exists( 'WordPressHTTPS' ) && isset( $_SERVER['HTTPS'] ) ) {
			$https_class = new WordPressHTTPS();
			$this->account_page = $https_class->makeUrlHttps( $this->account_page );
			$this->cart_page = $https_class->makeUrlHttps( $this->cart_page );
		} else if ( get_option( 'ec_option_load_ssl' ) ) {
			$this->cart_page = str_replace( 'http://', 'https://', $this->cart_page );
			$this->account_page = str_replace( 'http://', 'https://', $this->account_page );
		}

		if ( substr_count( $this->account_page, '?' ) ) {
			$this->permalink_divider_account = '&';
		} else {
			$this->permalink_divider_account = '?';
		}

		if ( substr_count( $this->account_page, '?' ) ) {
			$this->permalink_divider_cart = '&';
		} else {
			$this->permalink_divider_cart = '?';
		}

		$this->cart_page = apply_filters( 'wp_easycart_cart_page_url', $this->cart_page );
		$this->account_page = apply_filters( 'wp_easycart_account_page_url', $this->account_page );
	}
	
	private function build_atts( $url, $atts ) {
		$is_first = ( substr_count( $url, '?' ) ) ? false : true;
		if( isset( $atts['order_id'] ) ) {
			if ( $is_first ) {
				$url .= '?';
				$is_first = false;
			} else {
				$url .= '&';
			}
			$url .= 'order_id=' . esc_attr( $atts['order_id'] );
		}
		if( isset( $atts['orderdetail_id'] ) ) {
			if ( $is_first ) {
				$url .= '?';
				$is_first = false;
			} else {
				$url .= '&';
			}
			$url .= 'orderdetail_id=' . esc_attr( (int) $atts['orderdetail_id'] );
		}
		if( isset( $atts['download_id'] ) ) {
			if ( $is_first ) {
				$url .= '?';
				$is_first = false;
			} else {
				$url .= '&';
			}
			$url .= 'download_id=' . esc_attr( $atts['download_id'] );
		}
		if( isset( $atts['ec_guest_key'] ) ) {
			if ( $is_first ) {
				$url .= '?';
				$is_first = false;
			} else {
				$url .= '&';
			}
			$url .= 'ec_guest_key=' . esc_attr( $atts['ec_guest_key'] );
		}
		if( isset( $atts['guest_key'] ) ) {
			if ( $is_first ) {
				$url .= '?';
				$is_first = false;
			} else {
				$url .= '&';
			}
			$url .= 'guest_key=' . esc_attr( $atts['guest_key'] );
		}
		if( isset( $atts['subscription_id'] ) ) {
			if ( $is_first ) {
				$url .= '?';
				$is_first = false;
			} else {
				$url .= '&';
			}
			$url .= 'subscription_id=' . esc_attr( (int) $atts['subscription_id'] );
		}
		if( isset( $atts['subscription'] ) ) {
			if ( $is_first ) {
				$url .= '?';
				$is_first = false;
			} else {
				$url .= '&';
			}
			$url .= 'subscription=' . esc_attr( $atts['subscription'] );
		}
		if( isset( $atts['account_success'] ) ) {
			if ( $is_first ) {
				$url .= '?';
				$is_first = false;
			} else {
				$url .= '&';
			}
			$url .= 'account_success=' . esc_attr( $atts['account_success'] );
		}
		if( isset( $atts['account_error'] ) ) {
			if ( $is_first ) {
				$url .= '?';
				$is_first = false;
			} else {
				$url .= '&';
			}
			$url .= 'account_error=' . esc_attr( $atts['account_error'] );
		}
		if( isset( $atts['ec_cart_success'] ) ) {
			if ( $is_first ) {
				$url .= '?';
				$is_first = false;
			} else {
				$url .= '&';
			}
			$url .= 'ec_cart_success=' . esc_attr( $atts['ec_cart_success'] );
		}
		if( isset( $atts['ec_cart_error'] ) ) {
			if ( $is_first ) {
				$url .= '?';
				$is_first = false;
			} else {
				$url .= '&';
			}
			$url .= 'ec_cart_error=' . esc_attr( $atts['ec_cart_error'] );
		}
		if( isset( $atts['ec_error'] ) ) {
			if ( $is_first ) {
				$url .= '?';
				$is_first = false;
			} else {
				$url .= '&';
			}
			$url .= 'ec_error=' . esc_attr( $atts['ec_error'] );
		}
		if( isset( $atts['error'] ) ) {
			if ( $is_first ) {
				$url .= '?';
				$is_first = false;
			} else {
				$url .= '&';
			}
			$url .= 'error=' . esc_attr( $atts['error'] );
		}
		if( isset( $atts['errcode'] ) ) {
			if ( $is_first ) {
				$url .= '?';
				$is_first = false;
			} else {
				$url .= '&';
			}
			$url .= 'errcode=' . esc_attr( $atts['errcode'] );
		}
		if( isset( $atts['email'] ) ) {
			if ( $is_first ) {
				$url .= '?';
				$is_first = false;
			} else {
				$url .= '&';
			}
			$url .= 'email=' . esc_attr( $atts['email'] );
		}
		if( isset( $atts['key'] ) ) {
			if ( $is_first ) {
				$url .= '?';
				$is_first = false;
			} else {
				$url .= '&';
			}
			$url .= 'key=' . esc_attr( $atts['key'] );
		}
		if( isset( $atts['ideal'] ) ) {
			if ( $is_first ) {
				$url .= '?';
				$is_first = false;
			} else {
				$url .= '&';
			}
			$url .= 'ideal=' . esc_attr( $atts['ideal'] );
		}
		if( isset( $atts['stripe'] ) ) {
			if ( $is_first ) {
				$url .= '?';
				$is_first = false;
			} else {
				$url .= '&';
			}
			$url .= 'stripe=' . esc_attr( $atts['stripe'] );
		}
		if( isset( $atts['wpecnonce'] ) ) {
			if ( $is_first ) {
				$url .= '?';
				$is_first = false;
			} else {
				$url .= '&';
			}
			$url .= 'wpecnonce=' . esc_attr( $atts['wpecnonce'] );
		}
		if( isset( $atts['checkout_info'] ) ) {
			if ( $is_first ) {
				$url .= '?';
				$is_first = false;
			} else {
				$url .= '&';
			}
			$url .= 'checkout_info=' . esc_attr( $atts['checkout_info'] );
		}
		if( isset( $atts['ec_action'] ) ) {
			if ( $is_first ) {
				$url .= '?';
				$is_first = false;
			} else {
				$url .= '&';
			}
			$url .= 'ec_action=' . esc_attr( $atts['ec_action'] );
		}
		if( isset( $atts['model_number'] ) ) {
			if ( $is_first ) {
				$url .= '?';
				$is_first = false;
			} else {
				$url .= '&';
			}
			$url .= 'model_number=' . esc_attr( $atts['model_number'] );
		}
		return $url;
	}

	public function get_account_page( $key = '', $atts = array() ) {
		if ( empty( $this->account_page ) ) {
			$this->init_links();
		}
		if ( 'login' == $key ) {
			return $this->build_atts( apply_filters( 'wp_easycart_account_login_link', $this->account_page . $this->permalink_divider_account . 'ec_page=login' ), $atts );
		} else if ( 'register' == $key ) {
			return $this->build_atts( apply_filters( 'wp_easycart_account_register_link', $this->account_page . $this->permalink_divider_account . 'ec_page=register' ), $atts );
		} else if ( 'forgot_password' == $key ) {
			return $this->build_atts( apply_filters( 'wp_easycart_account_forgot_password_link', $this->account_page . $this->permalink_divider_account . 'ec_page=forgot_password' ), $atts );
		} else if ( 'dashboard' == $key ) {
			return $this->build_atts( apply_filters( 'wp_easycart_account_dashboard_link', $this->account_page . $this->permalink_divider_account . 'ec_page=dashboard' ), $atts );
		} else if ( 'orders' == $key ) {
			return $this->build_atts( apply_filters( 'wp_easycart_account_orders_link', $this->account_page . $this->permalink_divider_account . 'ec_page=orders' ), $atts );
		} else if ( 'order_details' == $key ) {
			return $this->build_atts( apply_filters( 'wp_easycart_account_order_details_link', $this->account_page . $this->permalink_divider_account . 'ec_page=order_details' ), $atts );
		} else if ( 'personal_information' == $key ) {
			return $this->build_atts( apply_filters( 'wp_easycart_account_personal_information_link', $this->account_page . $this->permalink_divider_account . 'ec_page=personal_information' ), $atts );
		} else if ( 'billing_information' == $key ) {
			return $this->build_atts( apply_filters( 'wp_easycart_account_billing_information_link', $this->account_page . $this->permalink_divider_account . 'ec_page=billing_information' ), $atts );
		} else if ( 'shipping_information' == $key ) {
			return $this->build_atts( apply_filters( 'wp_easycart_account_shipping_information_link', $this->account_page . $this->permalink_divider_account . 'ec_page=shipping_information' ), $atts );
		} else if ( 'password' == $key ) {
			return $this->build_atts( apply_filters( 'wp_easycart_account_password_link', $this->account_page . $this->permalink_divider_account . 'ec_page=password' ), $atts );
		} else if ( 'subscriptions' == $key ) {
			return $this->build_atts( apply_filters( 'wp_easycart_account_subscriptions_link', $this->account_page . $this->permalink_divider_account . 'ec_page=subscriptions' ), $atts );
		} else if ( 'subscription_details' == $key ) {
			return $this->build_atts( apply_filters( 'wp_easycart_account_subscription_details_link', $this->account_page . $this->permalink_divider_account . 'ec_page=subscription_details' ), $atts );
		} else if ( 'payment_methods' == $key ) {
			return $this->build_atts( apply_filters( 'wp_easycart_account_payment_methods_link', $this->account_page . $this->permalink_divider_account . 'ec_page=payment_methods' ), $atts );
		} else if ( 'logout' == $key ) {
			return $this->build_atts( apply_filters( 'wp_easycart_account_logout_link', $this->account_page . $this->permalink_divider_account . 'ec_page=logout' ), $atts );
		} else if ( 'print_receipt' == $key ) {
			return $this->build_atts( apply_filters( 'wp_easycart_account_print_receipt_link', $this->account_page . $this->permalink_divider_account . 'ec_page=print_receipt' ), $atts );
		} else if ( 'activate_account' == $key ) {
			return $this->build_atts( apply_filters( 'wp_easycart_account_activate_account_link', $this->account_page . $this->permalink_divider_account . 'ec_page=activate_account' ), $atts );
		} else {
			return $this->build_atts( apply_filters( 'wp_easycart_account_link', $this->account_page ), $atts, true );
		}
	}

	public function get_cart_page( $key = '', $atts = array() ) {
		if ( empty( $this->cart_page ) ) {
			$this->init_links();
		}
		if ( 'checkout_success' == $key ) {
			return $this->build_atts( apply_filters( 'wp_easycart_cart_checkout_success_link', $this->cart_page . $this->permalink_divider_cart . 'ec_page=checkout_success' ), $atts );
		} else if ( 'subscription_info' == $key ) {
			return $this->build_atts( apply_filters( 'wp_easycart_cart_subscription_info_link', $this->cart_page . $this->permalink_divider_cart . 'ec_page=subscription_info' ), $atts );
		} else if ( 'checkout_info' == $key ) {
			return $this->build_atts( apply_filters( 'wp_easycart_cart_checkout_info_link', $this->cart_page . $this->permalink_divider_cart . 'ec_page=checkout_info' ), $atts );
		} else if ( 'checkout_shipping' == $key ) {
			return $this->build_atts( apply_filters( 'wp_easycart_cart_checkout_shipping_link', $this->cart_page . $this->permalink_divider_cart . 'ec_page=checkout_shipping' ), $atts );
		} else if ( 'checkout_payment' == $key ) {
			return $this->build_atts( apply_filters( 'wp_easycart_cart_checkout_payment_link', $this->cart_page . $this->permalink_divider_cart . 'ec_page=checkout_payment' ), $atts );
		} else if ( 'checkout_submit_order' == $key ) {
			return $this->build_atts( apply_filters( 'wp_easycart_cart_checkout_submit_order_link', $this->cart_page . $this->permalink_divider_cart . 'ec_page=checkout_submit_order' ), $atts );
		} else if ( 'third_party' == $key ) {
			return $this->build_atts( apply_filters( 'wp_easycart_cart_third_party_link', $this->cart_page . $this->permalink_divider_cart . 'ec_page=third_party' ), $atts );
		} else if ( '3dsecure' == $key ) {
			return $this->build_atts( apply_filters( 'wp_easycart_cart_3dsecure_link', $this->cart_page . $this->permalink_divider_cart . 'ec_page=3dsecure' ), $atts );
		} else if ( 'invoice' == $key ) {
			return $this->build_atts( apply_filters( 'wp_easycart_cart_invoice_link', $this->cart_page . $this->permalink_divider_cart . 'ec_page=invoice' ), $atts );
		} else if ( 'process_affirm' == $key ) {
			return $this->build_atts( apply_filters( 'wp_easycart_cart_process_affirm_link', $this->cart_page . $this->permalink_divider_cart . 'ec_page=process_affirm' ), $atts );
		} else if ( 'checkout_paypal_authorized' == $key ) {
			return $this->build_atts( apply_filters( 'wp_easycart_cart_checkout_paypal_authorized_link', $this->cart_page . $this->permalink_divider_cart . 'ec_page=checkout_paypal_authorized' ), $atts );
		} else {
			return $this->build_atts( apply_filters( 'wp_easycart_cart_link', $this->cart_page ), $atts );
		}
	}

}
endif; // End if class_exists check

function wpeasycart_links() {
	return wpeasycart_links::instance();
}
wpeasycart_links();
