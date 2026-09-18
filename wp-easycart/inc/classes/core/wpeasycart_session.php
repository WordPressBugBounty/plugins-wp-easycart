<?php
if( !class_exists( 'wpeasycart_session' ) ) :

class wpeasycart_session {

	protected static $_instance = null;

	const COOKIE_NAME = 'ec_cart_id';
	const ID_LENGTH = 30;
	const COOKIE_LIFETIME = 86400; // 24 hours, matches previous behavior

	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	public function __construct() {
		$GLOBALS['ec_cart_id'] = 'not-set';

		if ( ! isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return;
		}

		$raw = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );

		if ( preg_match( '/^([A-Z]{' . self::ID_LENGTH . '})-([a-f0-9]{64})$/', $raw, $matches ) ) {
			if ( hash_equals( $this->sign_cart_id( $matches[1] ), $matches[2] ) ) {
				$GLOBALS['ec_cart_id'] = $matches[1];
				$this->set_cart_cookie( $matches[1] ); // refresh expiry
			}
			return;
		}

		if ( preg_match( '/^[A-Z]{20,40}$/', $raw ) ) {
			$this->try_adopt_legacy_session( $raw );
			return;
		}

		// 3) Anything else ('not-set', 'deleted', 'WARNINGCANNOT...', junk):
		// leave as not-set so a clean session is created on demand.
	}

	private function try_adopt_legacy_session( $legacy_id ) {
		global $wpdb;

		if ( ! $wpdb ) {
			return;
		}

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT session_id, user_id, billing_first_name, billing_address_line_1, billing_zip, billing_phone
			 FROM ec_tempcart_data
			 WHERE session_id = %s",
			$legacy_id
		) );

		if ( ! $row ) {
			return;
		}

		if ( '' != $row->user_id || '' != $row->billing_first_name || '' != $row->billing_address_line_1 || '' != $row->billing_zip || '' != $row->billing_phone ) {
			return;
		}

		$GLOBALS['ec_cart_id'] = $row->session_id;
		$this->set_cart_cookie( $row->session_id );
	}

	public function get_secret_key() {
		$key = get_option( 'ec_option_session_secret_key' );
		if ( ! $key || strlen( $key ) < 64 ) {
			try {
				$key = bin2hex( random_bytes( 32 ) );
			} catch ( Exception $e ) {
				$key = hash( 'sha256', wp_salt( 'auth' ) . microtime( true ) . uniqid( '', true ) );
			}
			update_option( 'ec_option_session_secret_key', $key, true );
		}
		return $key;
	}

	public function sign_cart_id( $cart_id ) {
		return hash_hmac( 'sha256', 'wpec-cart|' . $cart_id, $this->get_secret_key() );
	}

	public function get_abandoned_cart_key( $session_id, $email ) {
		return hash_hmac( 'sha256', 'wpec-abandoned|' . $session_id . '|' . strtolower( trim( $email ) ), $this->get_secret_key() );
	}

	public function generate_unique_cart_id() {
		global $wpdb;
		do {
			$id = '';
			for ( $i = 0; $i < self::ID_LENGTH; $i++ ) {
				$id .= chr( 65 + wp_rand( 0, 25 ) );
			}
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM ec_tempcart_data WHERE session_id = %s", $id ) );
			$exists += $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM ec_tempcart WHERE session_id = %s", $id ) );
		} while ( $exists );
		return $id;
	}

	public function set_cart_cookie( $cart_id ) {
		$value = $cart_id . '-' . $this->sign_cart_id( $cart_id );
		$path = ( defined( 'COOKIEPATH' ) && COOKIEPATH ) ? COOKIEPATH : '/';
		$domain = ( defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ) ? COOKIE_DOMAIN : '';
		$secure = is_ssl();
		$httponly = apply_filters( 'wp_easycart_cart_cookie_httponly', true );
		$samesite = apply_filters( 'wp_easycart_cart_cookie_samesite', 'Lax' );

		if ( PHP_VERSION_ID >= 70300 ) {
			setcookie( self::COOKIE_NAME, $value, array(
				'expires'  => time() + self::COOKIE_LIFETIME,
				'path'     => $path,
				'domain'   => $domain,
				'secure'   => $secure,
				'httponly' => $httponly,
				'samesite' => $samesite,
			) );
		} else {
			setcookie( self::COOKIE_NAME, $value, time() + self::COOKIE_LIFETIME, $path . '; samesite=' . $samesite, $domain, $secure, $httponly );
		}

		$_COOKIE[ self::COOKIE_NAME ] = $value;
	}

	public function clear_cart_cookie() {
		$path = ( defined( 'COOKIEPATH' ) && COOKIEPATH ) ? COOKIEPATH : '/';
		$domain = ( defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ) ? COOKIE_DOMAIN : '';
		setcookie( self::COOKIE_NAME, '', time() - 3600 );
		setcookie( self::COOKIE_NAME, '', time() - 3600, $path, $domain );
		unset( $_COOKIE[ self::COOKIE_NAME ] );
	}

	public function handle_session( $session_id = false, $create_new_is_missing = true ) {
		if ( $session_id ) {
			if ( 'not-set' == $session_id ) {
				$this->new_session();
			} else {
				$clean = preg_replace( '/[^A-Z]/', '', strtoupper( $session_id ) );
				$GLOBALS['ec_cart_id'] = $clean;
				$this->set_cart_cookie( $clean );
				$GLOBALS['ec_cart_data'] = new ec_cart_data( $clean );
			}
			return true;
		}

		if ( isset( $GLOBALS['ec_cart_id'] ) && 'not-set' != $GLOBALS['ec_cart_id'] ) {
			return true;
		}

		if ( $create_new_is_missing ) {
			$this->new_session();
			return true;
		}

		return false;
	}

	private function new_session() {
		$id = $this->generate_unique_cart_id();
		$GLOBALS['ec_cart_id'] = $id;
		$this->set_cart_cookie( $id );
		$GLOBALS['ec_cart_data'] = new ec_cart_data( $id );
	}

	public function rotate_session_id() {
		global $wpdb;

		if ( ! isset( $GLOBALS['ec_cart_id'] ) || 'not-set' == $GLOBALS['ec_cart_id'] ) {
			return false;
		}

		$old_id = $GLOBALS['ec_cart_id'];
		$new_id = $this->generate_unique_cart_id();

		$wpdb->query( $wpdb->prepare( "UPDATE ec_tempcart SET session_id = %s WHERE session_id = %s", $new_id, $old_id ) );
		$wpdb->query( $wpdb->prepare( "UPDATE ec_tempcart_data SET session_id = %s WHERE session_id = %s", $new_id, $old_id ) );
		$wpdb->query( $wpdb->prepare( "UPDATE ec_tempcart_optionitem SET session_id = %s WHERE session_id = %s", $new_id, $old_id ) );

		/* Offers v2: applied coupon codes live in ec_tempcart_offer, keyed by
		 * session id like the rows above. Not migrating them orphaned every
		 * applied code the moment a shopper logged in or out at checkout —
		 * the cart survived the rotation but the coupons silently vanished.
		 * The table only exists on offers-schema cores; guard accordingly. */
		if ( function_exists( 'wp_easycart_offers_active' ) && wp_easycart_offers_active() ) {
			$wpdb->query( $wpdb->prepare( "UPDATE ec_tempcart_offer SET session_id = %s WHERE session_id = %s", $new_id, $old_id ) );
			if ( class_exists( 'ec_offer_integration' ) ) {
				ec_offer_integration::reset(); // any memoized evaluation predates the rotation
			}
		}

		$GLOBALS['ec_cart_id'] = $new_id;
		$this->set_cart_cookie( $new_id );

		if ( isset( $GLOBALS['ec_cart_data'] ) && $GLOBALS['ec_cart_data'] instanceof ec_cart_data ) {
			$GLOBALS['ec_cart_data']->ec_cart_id = $new_id;
			if ( isset( $GLOBALS['ec_cart_data']->cart_data ) && is_object( $GLOBALS['ec_cart_data']->cart_data ) ) {
				$GLOBALS['ec_cart_data']->cart_data->session_id = $new_id;
			}
		}

		/**
		 * Fires after a cart session id is rotated ( restore link, login, logout ). Tables keyed by the session id
		 * outside the core cart tables ( e.g. ec_abandoned_cart ) follow the cart through this hook.
		 *
		 * @since 6.0.0
		 * @param string $old_id Previous session id.
		 * @param string $new_id New session id.
		 */
		do_action( 'wpeasycart_session_rotated', $old_id, $new_id );

		return $new_id;
	}
}
endif; // End if class_exists check

function wpeasycart_session() {
	return wpeasycart_session::instance();
}
wpeasycart_session();
