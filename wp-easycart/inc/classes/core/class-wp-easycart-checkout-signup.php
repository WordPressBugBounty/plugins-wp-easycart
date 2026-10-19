<?php
/**
 * Email first for a checkout that needs an account ( 6.0.5, Matt: "the same subscription setting to apply to anything that requires an
 * account to purchase something" ). A cart needs one when it holds a download, opens a members-only page ( WP EasyCart Limit Access ) or
 * the store has no guest checkout ( required() ). Both checkouts then start with the email, as the subscription page does
 * ( the class wp_easycart_subscription_signup keeps the lock, the account made at purchase and its password card ):
 *
 * - an email that has an account asks for its password ( the checkout's own sign in );
 * - a new email asks for a password ( the one-page checkout's Create account; the classic checkout's password, saved with the details );
 * - with "New customers choose a password after paying" on, a new email is locked for the session and the checkout opens. The account is
 *   made just before the order is saved ( before_order(), from ec_order::submit_order() ), so the order and its downloads are the
 *   account's; the shopper sets the password on the order confirmation ( the subscription page's card ), else with Forgot your password.
 *
 * While the setting is on, order_errors() refuses an order that needs an account when nobody is signed in and no email is locked; with it
 * off the checkout pages make the account first, as they always did.
 *
 * @package wp-easycart
 * @since   6.0.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_checkout_signup' ) ) :

	/**
	 * Email first, and the account made at purchase, for a checkout that needs an account.
	 */
	class wp_easycart_checkout_signup {

		/**
		 * Hooks.
		 */
		public static function init() {
			add_filter( 'wpeasycart_checkout_order_errors', array( __CLASS__, 'order_errors' ), 10, 2 );
			add_filter( 'wpeasycart_cart_errors', array( __CLASS__, 'error_notes' ) );
			add_filter( 'wpeasycart_valid_cart_errors', array( __CLASS__, 'valid_errors' ) );
		}

		/**
		 * Whether this checkout needs an account ( a download, a members-only page, no guest checkout ).
		 *
		 * @param ec_cartpage $cartpage The checkout.
		 * @return bool
		 */
		public static function required( $cartpage ) {
			$required = ( ! empty( $cartpage->has_downloads ) || ! get_option( 'ec_option_allow_guest' ) );
			/**
			 * Whether a checkout needs an account.
			 *
			 * @since 6.0.5
			 * @param bool        $required A download, a page the purchase opens ( Limit Access ) or no guest checkout.
			 * @param ec_cartpage $cartpage The checkout.
			 */
			return (bool) apply_filters( 'wp_easycart_checkout_account_required', $required, $cartpage );
		}

		/**
		 * Whether cart lines need an account ( the rule ec_cartpage reads into has_downloads, for code that has only the lines ).
		 *
		 * @param array $items ec_cartitem objects.
		 * @return bool
		 */
		public static function lines_need_account( $items ) {
			if ( ! get_option( 'ec_option_allow_guest' ) ) {
				return true;
			}
			foreach ( (array) $items as $item ) {
				if ( is_object( $item ) && ! empty( $item->is_download ) ) {
					return true;
				}
			}
			return class_exists( 'wp_easycart_page_access' ) && wp_easycart_page_access::cart_unlocks_page( $items );
		}

		/**
		 * The session's account, or 0.
		 *
		 * @return int
		 */
		public static function session_user_id() {
			return ( isset( $GLOBALS['ec_cart_data']->cart_data->user_id ) ) ? (int) $GLOBALS['ec_cart_data']->cart_data->user_id : 0;
		}

		/**
		 * Whether a new customer may choose the password after paying ( the setting the subscription page reads ).
		 *
		 * @return bool
		 */
		public static function later() {
			return class_exists( 'wp_easycart_subscription_signup' ) && wp_easycart_subscription_signup::after_purchase();
		}

		/**
		 * The email this session locked for a purchase that makes the account, or ''.
		 *
		 * @return string
		 */
		public static function locked_email() {
			return class_exists( 'wp_easycart_subscription_signup' ) ? wp_easycart_subscription_signup::locked_email() : '';
		}

		/**
		 * Whether the checkout waits for the email step: it needs an account, nobody is signed in and no email is locked.
		 *
		 * @param ec_cartpage $cartpage The checkout.
		 * @return bool
		 */
		public static function waits( $cartpage ) {
			return self::required( $cartpage ) && self::session_user_id() <= 0 && '' === self::locked_email();
		}

		/**
		 * What the email step draws for a shopper who is not signed in.
		 *
		 * @param ec_cartpage $cartpage The checkout.
		 * @return array required, later ( the setting ), locked, email, nonce, state ( email | later | optional ).
		 */
		public static function page_state( $cartpage ) {
			$state             = class_exists( 'wp_easycart_subscription_signup' ) ? wp_easycart_subscription_signup::page_state() : array(
				'later'  => false,
				'locked' => false,
				'email'  => '',
				'nonce'  => '',
			);
			$state['required'] = self::required( $cartpage );
			if ( ! $state['required'] ) {
				$state['state'] = 'optional';
			} else {
				$state['state'] = $state['locked'] ? 'later' : 'email';
			}
			if ( '' === $state['email'] && isset( $GLOBALS['ec_user']->email ) && is_email( (string) $GLOBALS['ec_user']->email ) ) {
				$state['email'] = (string) $GLOBALS['ec_user']->email;
			}
			return $state;
		}

		/**
		 * Filter wpeasycart_checkout_order_errors: an order that needs an account, while new customers choose the password after paying,
		 * is placed only by an account or with the email this session locked.
		 *
		 * @param string[]    $errors   Codes.
		 * @param ec_cartpage $cartpage The checkout.
		 * @return string[]
		 */
		public static function order_errors( $errors, $cartpage ) {
			if ( ! is_object( $cartpage ) || ! self::later() || self::session_user_id() > 0 || ! self::required( $cartpage ) ) {
				return $errors;
			}
			$locked = self::locked_email();
			$email  = isset( $GLOBALS['ec_cart_data']->cart_data->email ) ? trim( (string) $GLOBALS['ec_cart_data']->cart_data->email ) : '';
			if ( '' === $locked || 0 !== strcasecmp( $email, $locked ) ) {
				$errors[] = 'account_required';
			}
			return $errors;
		}

		/**
		 * Filter wpeasycart_cart_errors: the account_required notice.
		 *
		 * @param array $notes code => text.
		 * @return array
		 */
		public static function error_notes( $notes ) {
			$notes['account_required'] = self::text( 'checkout_account_required', 'This order needs an account. Enter your email at the top of the checkout to continue.' );
			return $notes;
		}

		/**
		 * Filter wpeasycart_valid_cart_errors: the cart page shows account_required when a redirect carries it.
		 *
		 * @param string[] $codes Codes.
		 * @return string[]
		 */
		public static function valid_errors( $codes ) {
			$codes[] = 'account_required';
			return $codes;
		}

		/**
		 * Make the account for the email this session locked, just before the order is saved ( ec_order::submit_order() ), when the
		 * order needs one and nobody is signed in: the order is then the account's ( its downloads and pages too ), the session is signed
		 * in and may set the password on the next page. ec_order fills the account's addresses from the order once it is paid
		 * ( update_user_addresses() ). Nothing is made for an order that does not need an account.
		 *
		 * @param ec_user       $user  The order's customer ( ec_order's; filled in with the account made ).
		 * @param array         $items The order's lines ( ec_cartitem ).
		 * @param ec_order|null $order The order about to be saved.
		 * @return int The account made, or 0.
		 */
		public static function before_order( $user, $items, $order = null ) {
			if ( ! is_object( $user ) ) {
				return 0;
			}
			if ( (int) $user->user_id > 0 || self::session_user_id() > 0 || ! self::later() ) {
				return 0;
			}
			$locked = self::locked_email();
			if ( '' === $locked || ! self::lines_need_account( $items ) ) {
				return 0;
			}
			if ( class_exists( 'wp_easycart_wordpress_users' ) && wp_easycart_wordpress_users::email_taken( $locked ) ) {
				return 0; /* made meanwhile: the order stays a guest's ( order_errors() stops the one-page checkout before this ) */
			}
			$user_id = self::make_account( $locked );
			if ( $user_id <= 0 ) {
				return 0;
			}
			$user->user_id    = $user_id;
			$user->email      = $locked;
			$user->user_level = 'shopper';
			if ( isset( $GLOBALS['ec_cart_data']->cart_data ) ) {
				$user->first_name = (string) $GLOBALS['ec_cart_data']->cart_data->first_name;
				$user->last_name  = (string) $GLOBALS['ec_cart_data']->cart_data->last_name;
			}
			wp_easycart_subscription_signup::mark_pending( $user_id, 'order' );
			/**
			 * An order that needs an account made one for a new customer, who chooses its password after paying.
			 *
			 * @since 6.0.5
			 * @param int           $user_id Account.
			 * @param ec_order|null $order   The order about to be saved.
			 */
			do_action( 'wp_easycart_checkout_account_made', $user_id, $order );
			return $user_id;
		}

		/**
		 * Make a store account for the session's email with a password nobody is told, and sign the session in.
		 *
		 * @param string $email Email.
		 * @return int Account, or 0.
		 */
		private static function make_account( $email ) {
			global $wpdb;
			$data     = isset( $GLOBALS['ec_cart_data']->cart_data ) ? $GLOBALS['ec_cart_data']->cart_data : new stdClass();
			$first    = isset( $data->billing_first_name ) && '' !== (string) $data->billing_first_name ? (string) $data->billing_first_name : ( isset( $data->first_name ) ? (string) $data->first_name : '' );
			$last     = isset( $data->billing_last_name ) && '' !== (string) $data->billing_last_name ? (string) $data->billing_last_name : ( isset( $data->last_name ) ? (string) $data->last_name : '' );
			$vat      = isset( $data->vat_registration_number ) ? preg_replace( '/[^a-zA-Z0-9\s]/', '', (string) $data->vat_registration_number ) : '';
			$password = wp_generate_password( 32, false );
			$hash     = apply_filters( 'wpeasycart_password_hash', wp_easycart_hash_password( $password ), $password );
			$db       = new ec_db();
			$user_id  = (int) $db->insert_user( $email, $hash, $first, $last, 0, 0, 'shopper', false, '', $vat );
			if ( $user_id <= 0 ) {
				return 0;
			}

			do_action( 'wpeasycart_account_added', $user_id, $email, $password, 'checkout' );
			if ( class_exists( 'wp_easycart_wordpress_users' ) ) {
				wp_easycart_wordpress_users::legacy_account_created( $user_id, $email, $password, $first, $last ); /* WordPress User Sync 1.x */
			}
			if ( get_option( 'ec_option_send_signup_email' ) && function_exists( 'wp_easycart_account_register_admin_email_html' ) ) {
				self::signup_email( $email );
			}

			$data->is_guest   = '';
			$data->user_id    = $user_id;
			$data->email      = $email;
			$data->username   = trim( $first . ' ' . $last );
			$data->first_name = $first;
			$data->last_name  = $last;
			if ( isset( $GLOBALS['ec_cart_data'] ) && is_object( $GLOBALS['ec_cart_data'] ) && method_exists( $GLOBALS['ec_cart_data'], 'save_session_to_db' ) ) {
				$GLOBALS['ec_cart_data']->save_session_to_db();
			}
			if ( class_exists( 'wp_easycart_cache' ) ) {
				wp_easycart_cache::shopper_changed();
			}
			$GLOBALS['ec_user'] = new ec_user( '' );
			return $user_id;
		}

		/**
		 * The store's new account notice ( Settings › Accounts › Send the store an email for new accounts ), as checkout sends it.
		 *
		 * @param string $email The account's email.
		 */
		private static function signup_email( $email ) {
			$from    = stripslashes( get_option( 'ec_option_order_from_email' ) );
			$headers = array(
				'MIME-Version: 1.0',
				'Content-Type: text/html; charset=utf-8',
				'From: ' . $from,
				'Reply-To: ' . $from,
			);
			$subject = wp_easycart_language()->get_text( 'account_register', 'account_register_email_title' );
			$message = wp_easycart_account_register_admin_email_html( $email );
			$to      = stripslashes( get_option( 'ec_option_bcc_email_addresses' ) );
			if ( get_option( 'ec_option_use_wp_mail' ) ) {
				wp_mail( $to, $subject, $message, implode( "\r\n", $headers ) );
			} else {
				$mailer = new wpeasycart_mailer();
				$mailer->send_order_email( $to, $subject, $message );
			}
		}

		/**
		 * A cart_login phrase, else the English.
		 *
		 * @param string $key      Phrase.
		 * @param string $fallback English.
		 * @return string
		 */
		public static function text( $key, $fallback ) {
			return class_exists( 'wp_easycart_subscription_signup' ) ? wp_easycart_subscription_signup::text( $key, $fallback ) : esc_html( $fallback );
		}
	}

	wp_easycart_checkout_signup::init();

endif;
