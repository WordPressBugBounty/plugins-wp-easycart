<?php
/**
 * The subscription page's email first sign up ( 6.0.5, Matt: a shopper who is not signed in clicked Sign up and found an almost
 * empty page ). The page asks for the email first, with the rest of the checkout drawn below it, blurred, until the email is settled:
 *
 * - an email that has an account asks for its password and signs in ( the page's own sign in );
 * - a new email asks for a password and makes the account at once, as before ( Create account );
 * - a new email with "New subscribers choose a password after paying" on ( OPTION ) is locked for this session and the checkout opens:
 *   the account is made with that email when they pay ( ec_cartpage::submit_stripe_quick_subscription() asks purchase_email() ),
 *   and they set its password on the page they land on ( the order confirmation, or the subscription in My Account for a trial ),
 *   else later with Forgot your password.
 *
 * Changing a locked email takes the lock away: the checkout is blurred again until the new email is settled. Nothing is made for a
 * locked email that never pays.
 *
 * The regular checkout uses the same lock, the same account made at purchase and the same password card for an order that needs an
 * account ( a download, a members-only page, no guest checkout: wp_easycart_checkout_signup ). The pending marker remembers which kind
 * of purchase made the account, so the card's words fit it.
 *
 * @package wp-easycart
 * @since   6.0.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_subscription_signup' ) ) :

	/**
	 * Email first sign up on the subscription page.
	 */
	class wp_easycart_subscription_signup {

		/**
		 * The setting: new customers choose a password after paying ( Settings › Checkout › Checkout form; a subscription, or an order that
		 * needs an account ).
		 */
		const OPTION = 'ec_option_subscription_password_after_purchase';

		/**
		 * How long a locked email waits for the purchase ( a day ).
		 */
		const LOCK_TTL = 86400;

		/**
		 * How long an account made at purchase may set its password from the session that bought it ( 14 days ). After that, and
		 * from any other browser, Forgot your password does it.
		 */
		const PENDING_TTL = 1209600;

		/**
		 * Hooks.
		 */
		public static function init() {
			add_action( 'wp_ajax_ec_ajax_subscription_email', array( __CLASS__, 'ajax_email' ) );
			add_action( 'wp_ajax_nopriv_ec_ajax_subscription_email', array( __CLASS__, 'ajax_email' ) );
			add_action( 'wp_ajax_ec_ajax_subscription_set_password', array( __CLASS__, 'ajax_set_password' ) );
			add_action( 'wp_ajax_nopriv_ec_ajax_subscription_set_password', array( __CLASS__, 'ajax_set_password' ) );
			add_action( 'wpeasycart_success_page_content_middle', array( __CLASS__, 'print_card' ), 1 );
			add_action( 'wpeasycart_account_subscription_details_before', array( __CLASS__, 'print_card' ), 3 );
			add_action( 'wpeasycart_dashboard_top', array( __CLASS__, 'print_card' ), 5 );
			add_action( 'wp_easycart_checkout_session_renewed', array( __CLASS__, 'session_renewed' ), 10, 2 );
		}

		/**
		 * Whether a new subscriber chooses a password after paying.
		 *
		 * @return bool
		 */
		public static function after_purchase() {
			/**
			 * Whether a new email goes straight to billing and payment, the account made when they pay ( the subscription page, and a
			 * checkout that needs an account ).
			 *
			 * @since 6.0.5
			 * @param bool $after Settings › Checkout › Checkout form › New customers choose a password after paying.
			 */
			return (bool) apply_filters( 'wp_easycart_subscription_password_after_purchase', '1' === (string) get_option( self::OPTION, '0' ) );
		}

		/**
		 * The checkout session's id, or ''.
		 *
		 * @return string
		 */
		public static function session_id() {
			return ( isset( $GLOBALS['ec_cart_data'] ) && is_object( $GLOBALS['ec_cart_data'] ) && isset( $GLOBALS['ec_cart_data']->ec_cart_id ) ) ? (string) $GLOBALS['ec_cart_data']->ec_cart_id : '';
		}

		/**
		 * A keyed hash of the session ( what the transients keep, never the session id ).
		 *
		 * @param string|null $id A session id, else this request's.
		 * @return string
		 */
		private static function session_hash( $id = null ) {
			$id = ( null === $id ) ? self::session_id() : (string) $id;
			return ( '' === $id ) ? '' : wp_hash( 'wpec-sub-signup|' . $id );
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
		 * The nonce the page's email step posts.
		 *
		 * @return string
		 */
		public static function nonce() {
			return wp_create_nonce( 'wp-easycart-subscription-email-' . self::session_id() );
		}

		/**
		 * Whether an email has a store account ( or, with WordPress User Sync, a WordPress user ).
		 *
		 * @param string $email Email.
		 * @return string existing | new
		 */
		public static function email_state( $email ) {
			if ( class_exists( 'wp_easycart_wordpress_users' ) ) {
				$taken = wp_easycart_wordpress_users::email_taken( $email );
			} else {
				global $wpdb;
				$taken = (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT user_id FROM ec_user WHERE email = %s LIMIT 1', $email ) );
			}
			return $taken ? 'existing' : 'new';
		}

		/**
		 * The lock's transient key for this session.
		 *
		 * @return string
		 */
		private static function lock_key() {
			return 'wpec_sub_lock_' . md5( self::session_hash() );
		}

		/**
		 * The email this session locked for a purchase that makes the account, or ''.
		 *
		 * @return string
		 */
		public static function locked_email() {
			if ( '' === self::session_id() || self::session_user_id() > 0 || ! self::after_purchase() ) {
				return '';
			}
			$email = get_transient( self::lock_key() );
			return ( is_string( $email ) && is_email( $email ) ) ? $email : '';
		}

		/**
		 * Lock a new email for this session's purchase.
		 *
		 * @param string $email Email.
		 */
		public static function lock( $email ) {
			set_transient( self::lock_key(), $email, self::LOCK_TTL );
			if ( isset( $GLOBALS['ec_cart_data']->cart_data ) ) {
				$GLOBALS['ec_cart_data']->cart_data->email = $email;
				$GLOBALS['ec_cart_data']->save_session_to_db();
			}
		}

		/**
		 * Take the lock away ( the shopper changes the email ).
		 */
		public static function unlock() {
			$locked = get_transient( self::lock_key() );
			delete_transient( self::lock_key() );
			if ( isset( $GLOBALS['ec_cart_data']->cart_data ) && is_string( $locked ) && '' !== $locked && 0 === strcasecmp( (string) $GLOBALS['ec_cart_data']->cart_data->email, $locked ) && self::session_user_id() <= 0 ) {
				$GLOBALS['ec_cart_data']->cart_data->email = '';
				$GLOBALS['ec_cart_data']->save_session_to_db();
			}
		}

		/**
		 * What the subscription page draws for a shopper who is not signed in.
		 *
		 * @return array later ( the setting ), locked ( the email locked for the purchase ), email ( to show in the field ), nonce.
		 */
		public static function page_state() {
			$locked = self::locked_email();
			$email  = $locked;
			if ( '' === $email && isset( $GLOBALS['ec_cart_data']->cart_data->email ) && is_email( (string) $GLOBALS['ec_cart_data']->cart_data->email ) ) {
				$email = (string) $GLOBALS['ec_cart_data']->cart_data->email;
			}
			return array(
				'later'  => self::after_purchase(),
				'locked' => '' !== $locked,
				'email'  => $email,
				'nonce'  => self::nonce(),
			);
		}

		/**
		 * The email a purchase makes the account with ( ec_cartpage::submit_stripe_quick_subscription(), when the page posts
		 * wpec_signup_later ): the email this session locked, and only while the shopper still pays with it.
		 *
		 * @param string $posted_email The email the page posted with the payment.
		 * @return string|WP_Error
		 */
		public static function purchase_email( $posted_email ) {
			$locked = self::locked_email();
			if ( '' === $locked || 0 !== strcasecmp( trim( (string) $posted_email ), $locked ) ) {
				return new WP_Error( 'email_changed', self::text( 'subscription_email_changed', 'Your email changed. Confirm it again before you pay.' ) );
			}
			return $locked;
		}

		/**
		 * The pending marker's transient key for an account.
		 *
		 * @param int $user_id Account.
		 * @return string
		 */
		private static function pending_key( $user_id ) {
			return 'wpec_sub_setpw_' . (int) $user_id;
		}

		/**
		 * An account was made at purchase without a password: this session may set one ( the lock is spent ).
		 *
		 * @param int    $user_id Account.
		 * @param string $kind    subscription | order ( the card's words ).
		 */
		public static function mark_pending( $user_id, $kind = 'subscription' ) {
			if ( (int) $user_id <= 0 || '' === self::session_hash() ) {
				return;
			}
			$kind = ( 'order' === $kind ) ? 'order' : 'subscription';
			set_transient( self::pending_key( $user_id ), self::session_hash() . '|' . $kind, self::PENDING_TTL );
			delete_transient( self::lock_key() );
			if ( 'subscription' === $kind ) {
				/**
				 * A subscription purchase made an account that has no password yet.
				 *
				 * @since 6.0.5
				 * @param int $user_id Account.
				 */
				do_action( 'wp_easycart_subscription_account_made', (int) $user_id );
			}
		}

		/**
		 * The pending marker of an account: the session hash it may be set from and the kind of purchase, or null.
		 *
		 * @param int $user_id Account.
		 * @return array|null hash, kind.
		 */
		private static function pending_marker( $user_id ) {
			$value = get_transient( self::pending_key( $user_id ) );
			if ( ! is_string( $value ) || '' === $value ) {
				return null;
			}
			$parts = explode( '|', $value, 2 );
			return array(
				'hash' => $parts[0],
				'kind' => ( isset( $parts[1] ) && 'order' === $parts[1] ) ? 'order' : 'subscription',
			);
		}

		/**
		 * The session's account when it was made at purchase in this session and has no password yet, else 0.
		 *
		 * @return int
		 */
		public static function pending_user() {
			$user_id = self::session_user_id();
			if ( $user_id <= 0 ) {
				return 0;
			}
			$marker = self::pending_marker( $user_id );
			return ( $marker && '' !== $marker['hash'] && '' !== self::session_hash() && hash_equals( $marker['hash'], self::session_hash() ) ) ? $user_id : 0;
		}

		/**
		 * Action wp_easycart_checkout_session_renewed: an order gives the shopper a new checkout session
		 * ( ec_cart_data::checkout_session_complete() ); the account made at purchase may still set its password from it.
		 *
		 * @param string $old_id The session that placed the order.
		 * @param string $new_id The session that follows.
		 */
		public static function session_renewed( $old_id, $new_id ) {
			$user_id = self::session_user_id();
			if ( $user_id <= 0 || '' === (string) $old_id || '' === (string) $new_id ) {
				return;
			}
			$marker = self::pending_marker( $user_id );
			if ( $marker && hash_equals( $marker['hash'], self::session_hash( $old_id ) ) ) {
				set_transient( self::pending_key( $user_id ), self::session_hash( $new_id ) . '|' . $marker['kind'], self::PENDING_TTL );
			}
		}

		/**
		 * Set the password of the session's account made at purchase, as the password reset does ( hashed as WordPress posts it,
		 * WordPress User Sync told ).
		 *
		 * @param string $password As typed ( unslashed ).
		 * @return true|WP_Error
		 */
		public static function set_password( $password ) {
			$user_id = self::pending_user();
			if ( ! $user_id ) {
				return new WP_Error( 'not_pending', self::text( 'subscription_set_password_failed', 'Your password could not be saved. Please try again.' ) );
			}
			$password = (string) $password;
			if ( apply_filters( 'wpeasycart_custom_verify_new_password', false, $password ) ) {
				do_action( 'wpeasycart_custom_verify_new_password_failed', $password );
				return new WP_Error( 'password_invalid', self::text( 'subscription_set_password_failed', 'Your password could not be saved. Please try again.' ) );
			}
			$min_length = (int) apply_filters( 'wp_easycart_minimum_password_length', 6 );
			if ( strlen( $password ) < $min_length ) {
				return new WP_Error( 'password_short', str_replace( '[count]', (string) $min_length, self::text( 'subscription_set_password_short', 'Use at least [count] characters.' ) ) );
			}
			global $wpdb;
			$slashed = wp_slash( $password );
			$hash    = apply_filters( 'wpeasycart_password_hash', wp_easycart_hash_password( $slashed ), $slashed );
			if ( false === $wpdb->update( 'ec_user', array( 'password' => $hash ), array( 'user_id' => $user_id ), array( '%s' ), array( '%d' ) ) ) {
				return new WP_Error( 'save_failed', self::text( 'subscription_set_password_failed', 'Your password could not be saved. Please try again.' ) );
			}
			if ( class_exists( 'wp_easycart_cache' ) ) {
				wp_easycart_cache::forget_user( $user_id );
			}
			delete_transient( self::pending_key( $user_id ) );
			do_action( 'wpeasycart_password_reset_complete', $user_id );
			if ( class_exists( 'wp_easycart_wordpress_users' ) ) {
				wp_easycart_wordpress_users::password_set( $user_id, $slashed, 'reset' );
			}
			do_action( 'wpeasycart_password_changed', $user_id, $hash );
			return true;
		}

		/**
		 * AJAX ec_ajax_subscription_email: do = check ( answers state existing | new | later, later having locked the email ) or
		 * unlock. Answers signed_in when the session already has an account ( the page reloads ). lock = 0 only asks: the checkout's
		 * welcome back note while guest checkout is open never locks an email. 6.0.5: every new email is counted by
		 * wp_easycart_email_lookups::ask() first: error check ( provider, site_key, message, failed: the step shows the human check and
		 * posts again with check_token ) or busy; the note's lookup ( lock 0 ) never asks for a check and answers state unknown instead.
		 */
		public static function ajax_email() {
			wpeasycart_session()->handle_session();
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wp-easycart-subscription-email-' . self::session_id() ) ) {
				wp_send_json( array( 'error' => 'expired' ) );
			}
			if ( self::session_user_id() > 0 ) {
				wp_send_json( array( 'state' => 'signed_in' ) );
			}
			$do = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : 'check';
			if ( 'unlock' === $do ) {
				self::unlock();
				wp_send_json( array( 'state' => 'email' ) );
			}
			$email = isset( $_POST['email'] ) ? trim( sanitize_email( wp_unslash( $_POST['email'] ) ) ) : '';
			if ( '' === $email || ! is_email( $email ) ) {
				wp_send_json( array( 'error' => 'email' ) );
			}
			$lock   = ! isset( $_POST['lock'] ) || '0' !== sanitize_text_field( wp_unslash( $_POST['lock'] ) );
			$lookup = wp_easycart_email_lookups::ask(
				$email,
				array(
					'token' => isset( $_POST['check_token'] ) ? sanitize_text_field( wp_unslash( $_POST['check_token'] ) ) : '',
					'check' => $lock,
					'place' => $lock ? 'email_step' : 'email_note',
				)
			);
			if ( empty( $lookup['ok'] ) ) {
				if ( ! $lock ) {
					wp_send_json(
						array(
							'state' => 'unknown',
							'email' => $email,
						)
					);
				}
				if ( 'check' === $lookup['reason'] && is_array( $lookup['check'] ) ) {
					wp_send_json(
						array(
							'error'    => 'check',
							'provider' => $lookup['check']['provider'],
							'site_key' => $lookup['check']['site_key'],
							'failed'   => ! empty( $lookup['failed'] ),
							'message'  => wp_easycart_email_lookups::check_text( ! empty( $lookup['failed'] ) ),
						)
					);
				}
				wp_send_json(
					array(
						'error'   => 'busy',
						'message' => wp_easycart_email_lookups::busy_text(),
					)
				);
			}
			$state = self::email_state( $email );
			if ( ! $lock ) {
				wp_send_json(
					array(
						'state' => $state,
						'email' => $email,
					)
				);
			}
			if ( 'new' === $state && self::after_purchase() ) {
				self::lock( $email );
				$state = 'later';
			} elseif ( 'existing' === $state ) {
				self::unlock(); /* a lock for another email this session made before */
			}
			wp_send_json(
				array(
					'state' => $state,
					'email' => $email,
				)
			);
		}

		/**
		 * AJAX ec_ajax_subscription_set_password: the password of the session's account made at purchase.
		 */
		public static function ajax_set_password() {
			wpeasycart_session()->handle_session();
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wp-easycart-subscription-set-password-' . self::session_id() ) ) {
				wp_send_json(
					array(
						'error'   => 'expired',
						'message' => wp_strip_all_tags( self::text( 'subscription_set_password_failed', 'Your password could not be saved. Please try again.' ) ),
					)
				);
			}
			$password = isset( $_POST['password'] ) ? wp_unslash( $_POST['password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- passwords are used as typed, never sanitized ( 6.0.2 ).
			$result   = self::set_password( is_string( $password ) ? $password : '' );
			if ( is_wp_error( $result ) ) {
				wp_send_json(
					array(
						'error'   => $result->get_error_code(),
						'message' => wp_strip_all_tags( $result->get_error_message() ),
					)
				);
			}
			wp_send_json(
				array(
					'saved'   => true,
					'message' => wp_strip_all_tags( self::text( 'subscription_set_password_saved', 'Your password is saved.' ) ),
				)
			);
		}

		/**
		 * The set a password card, for the session's account made at purchase ( '' otherwise ).
		 *
		 * @return string
		 */
		public static function card_html() {
			$user_id = self::pending_user();
			if ( ! $user_id ) {
				return '';
			}
			global $wpdb;
			$email = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT email FROM ec_user WHERE user_id = %d', $user_id ) ); /* the account's own email, not the session's */
			$min   = max( 1, (int) apply_filters( 'wp_easycart_minimum_password_length', 6 ) );
			$id    = 'ec_subscription_set_password_' . wp_rand( 1000, 999999 );
			$mark  = self::pending_marker( $user_id );
			$words = ( $mark && 'order' === $mark['kind'] ) ? self::text( 'checkout_set_password_text', 'Your account uses [email]. Choose a password so you can sign in to see your orders and downloads.' ) : self::text( 'subscription_set_password_text', 'Your account uses [email]. Choose a password so you can sign in and manage your subscription.' );
			$text  = str_replace( '[email]', '<strong>' . esc_html( $email ) . '</strong>', $words );
			$html  = '<div class="ec_subscription_set_password" data-nonce="' . esc_attr( wp_create_nonce( 'wp-easycart-subscription-set-password-' . self::session_id() ) ) . '" data-min="' . esc_attr( (string) $min ) . '" data-short="' . esc_attr( wp_strip_all_tags( str_replace( '[count]', (string) $min, self::text( 'subscription_set_password_short', 'Use at least [count] characters.' ) ) ) ) . '" data-failed="' . esc_attr( wp_strip_all_tags( self::text( 'subscription_set_password_failed', 'Your password could not be saved. Please try again.' ) ) ) . '">';
			$html .= '<div class="ec_subscription_set_password_form">';
			$html .= '<h3 class="ec_subscription_set_password_title">' . wp_kses_post( self::text( 'subscription_set_password_title', 'Set a password for your account' ) ) . '</h3>';
			$html .= '<p class="ec_subscription_set_password_text">' . wp_kses_post( $text ) . '</p>';
			$html .= '<input type="email" class="ec_subscription_set_password_user" value="' . esc_attr( $email ) . '" autocomplete="username" readonly hidden />'; /* password managers save the pair */
			$html .= '<div class="ec_subscription_set_password_row">';
			$html .= '<label for="' . esc_attr( $id ) . '">' . wp_kses_post( wp_easycart_language()->get_text( 'cart_login', 'cart_login_password_label' ) ) . '</label>';
			$html .= '<span class="ec_subscription_set_password_field"><input type="password" id="' . esc_attr( $id ) . '" class="ec_subscription_set_password_input" autocomplete="new-password" minlength="' . esc_attr( (string) $min ) . '" aria-describedby="' . esc_attr( $id ) . '_note" />';
			$html .= '<button type="button" class="ec_cart_button ec_subscription_set_password_save">' . esc_html( wp_strip_all_tags( self::text( 'subscription_set_password_button', 'Save password' ) ) ) . '</button></span>';
			$html .= '</div>';
			$html .= '<p class="ec_subscription_set_password_error" id="' . esc_attr( $id ) . '_note" role="alert" hidden></p>';
			$html .= '<p class="ec_subscription_set_password_later">' . wp_kses_post( self::text( 'subscription_set_password_later', 'You can also set it later with Forgot your password on the sign-in page.' ) ) . '</p>';
			$html .= '</div>';
			$html .= '<p class="ec_subscription_set_password_done" role="status" hidden>' . wp_kses_post( self::text( 'subscription_set_password_saved', 'Your password is saved.' ) ) . '</p>';
			$html .= '</div>';
			/**
			 * The set a password card after a purchase made the account ( a subscription, or an order that needs an account ).
			 *
			 * @since 6.0.5
			 * @param string $html    Markup.
			 * @param int    $user_id Account.
			 */
			return (string) apply_filters( 'wp_easycart_subscription_set_password_html', $html, $user_id );
		}

		/**
		 * Print the card ( the order confirmation, the subscription in My Account, the account dashboard ), once a page.
		 */
		public static function print_card() {
			static $printed = false;
			if ( $printed ) {
				return;
			}
			$html = self::card_html();
			if ( '' !== $html ) {
				$printed = true;
				echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped as it is built in card_html().
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
			$text = function_exists( 'wp_easycart_language' ) ? wp_easycart_language()->get_text( 'cart_login', $key ) : null;
			return ( null === $text || '' === trim( (string) $text ) ) ? esc_html( $fallback ) : (string) $text;
		}
	}

	wp_easycart_subscription_signup::init();

endif;
