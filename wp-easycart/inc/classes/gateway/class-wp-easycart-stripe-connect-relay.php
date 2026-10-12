<?php
/**
 * WP EasyCart Connect's Stripe Connect relay, v2 ( 6.0.4 ): the Connect Stripe links and collecting a new connection.
 *
 * Before 6.0.4 the store sent the merchant to /connect/, and Connect sent the connected account's access token ( a secret key for
 * the account ), refresh token, publishable key and account id back to the store's admin in the address, into web server access logs
 * and the merchant's browser history. Since 6.0.4 Connect Stripe goes through admin-post ( handle_connect() ), which keeps a verifier
 * on this server and sends /stripe-v2/start.php only its SHA-256 ( the challenge ); the return carries a one-time claim, which only
 * this server can collect ( claim() ) with a POST.
 *
 * WP EasyCart 6.0.3 and older keep using /connect/, which stays as it is. While /stripe-v2/ is missing on Connect ( a 404 that is not
 * v2's own JSON ) Connect Stripe goes to /connect/ as before, so nothing stops working; an outage or a 500 stays on v2.
 *
 * Contract: GitHub/wp-easycart-connect/docs/token-relays.md ( outside the plugins ).
 *
 * @package wp-easycart
 * @since 6.0.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_stripe_connect_relay' ) ) :

	/**
	 * WP EasyCart Connect's Stripe Connect relay ( /stripe-v2/, else /connect/ ).
	 */
	final class wp_easycart_stripe_connect_relay {

		/** WP EasyCart Connect ( filter wp_easycart_stripe_connect_relay_url ). */
		const BASE = 'https://connect.wpeasycart.com';

		/** Whether /stripe-v2/ answers here: 'yes' ( 12 hours ) or 'no' ( 15 minutes, then asked again ). */
		const AVAILABLE = 'wpec_stripe_relay_v2';

		/** The verifier of the Connect Stripe a person started, per user ( 30 minutes ). */
		const VERIFIER = 'wpec_stripe_verifier_';

		/** The admin-post action of the Connect Stripe links. */
		const CONNECT_ACTION = 'wp_easycart_stripe_connect';

		/**
		 * WP EasyCart Connect's address.
		 *
		 * @return string Without a trailing slash.
		 */
		public static function base() {
			return untrailingslashit( (string) apply_filters( 'wp_easycart_stripe_connect_relay_url', self::BASE ) );
		}

		/**
		 * A /stripe-v2/ address.
		 *
		 * @param string $file api.php | start.php.
		 * @return string
		 */
		public static function url( $file ) {
			return self::base() . '/stripe-v2/' . $file;
		}

		/**
		 * Whether an answer came from /stripe-v2/ ( JSON with "api": 2, whatever its status ).
		 *
		 * @param array|WP_Error $response WP_Http answer.
		 * @return bool
		 */
		public static function is_v2( $response ) {
			if ( is_wp_error( $response ) ) {
				return false;
			}
			$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			return is_array( $data ) && isset( $data['api'] ) && 2 === (int) $data['api'];
		}

		/**
		 * Whether /stripe-v2/ answers on WP EasyCart Connect. Only a 404 that is not v2's own JSON counts as missing.
		 *
		 * @param bool $fresh Ask again now.
		 * @return bool
		 */
		public static function available( $fresh = false ) {
			$known = $fresh ? false : get_transient( self::AVAILABLE );
			if ( 'yes' === $known ) {
				return true;
			}
			if ( 'no' === $known ) {
				return false;
			}
			return self::remember(
				wp_remote_get(
					self::url( 'api.php?action=ping' ),
					array(
						'timeout'     => 8,
						'redirection' => 0,
					)
				)
			);
		}

		/**
		 * Keep what an answer says about /stripe-v2/.
		 *
		 * @param array|WP_Error $response WP_Http answer.
		 * @return bool Whether /stripe-v2/ is there.
		 */
		private static function remember( $response ) {
			if ( self::is_v2( $response ) ) {
				set_transient( self::AVAILABLE, 'yes', 12 * HOUR_IN_SECONDS );
				return true;
			}
			if ( ! is_wp_error( $response ) && 404 === (int) wp_remote_retrieve_response_code( $response ) ) {
				set_transient( self::AVAILABLE, 'no', 15 * MINUTE_IN_SECONDS );
				return false;
			}
			return true;
		}

		/**
		 * Where Connect sends the merchant back ( wp_easycart_admin_payments::onboard_stripe(), which checks the nonce ).
		 *
		 * @param bool   $sandbox Stripe's test mode.
		 * @param string $back    '' | wizard.
		 * @return string
		 */
		public static function return_url( $sandbox, $back = '' ) {
			$url = esc_url_raw( admin_url() ) . '?ec_admin_form_action=stripe_onboard&env=' . ( $sandbox ? 'sandbox' : 'production' ) . '&wp_easycart_nonce=' . wp_create_nonce( 'wp-easycart-stripe' );
			if ( 'wizard' === $back ) {
				$url .= '&goto=wizard';
			}
			return $url;
		}

		/**
		 * A Connect Stripe link: admin-post, which keeps the verifier here and sends the merchant on ( handle_connect() ).
		 *
		 * @param bool   $sandbox Stripe's test mode.
		 * @param string $back    '' | wizard.
		 * @return string
		 */
		public static function connect_url( $sandbox = false, $back = '' ) {
			$args = array(
				'action' => self::CONNECT_ACTION,
				'mode'   => $sandbox ? 'sandbox' : 'live',
			);
			if ( 'wizard' === $back ) {
				$args['goto'] = $back;
			}
			$args['_wpnonce'] = wp_create_nonce( 'wp-easycart-stripe-connect' ); /* not wp_nonce_url(): its &amp; would reach scripts as is */
			return add_query_arg( $args, admin_url( 'admin-post.php' ) );
		}

		/**
		 * Connect Stripe ( admin-post ): /stripe-v2/start.php with a challenge, or /connect/ while /stripe-v2/ is missing.
		 */
		public static function handle_connect() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_settings' ) ) {
				wp_die( esc_html__( 'You do not have permission to connect Stripe.', 'wp-easycart' ), '', array( 'response' => 403 ) );
			}
			check_admin_referer( 'wp-easycart-stripe-connect' );
			$sandbox = isset( $_GET['mode'] ) && 'sandbox' === sanitize_key( wp_unslash( $_GET['mode'] ) );
			$back    = isset( $_GET['goto'] ) ? sanitize_key( wp_unslash( $_GET['goto'] ) ) : '';
			$return  = self::return_url( $sandbox, $back );
			if ( self::available() ) {
				$verifier = self::verifier();
				set_transient( self::VERIFIER . get_current_user_id(), $verifier, 30 * MINUTE_IN_SECONDS );
				$url = add_query_arg(
					array(
						'mode'      => $sandbox ? 'sandbox' : 'live',
						'return'    => rawurlencode( $return ),
						'challenge' => self::challenge( $verifier ),
					),
					self::url( 'start.php' )
				);
			} else {
				$env = $sandbox ? 'sandbox' : 'production';
				$url = self::base() . '/connect/?step=start&redirect=' . rawurlencode( $return ) . '&env=' . $env;
			}
			wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- WP EasyCart Connect, another host by design.
			exit;
		}

		/**
		 * A random verifier ( 64 URL-safe characters ).
		 *
		 * @return string
		 */
		public static function verifier() {
			return rtrim( strtr( base64_encode( random_bytes( 48 ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- URL-safe random text.
		}

		/**
		 * The challenge Connect keeps for a verifier ( base64url SHA-256, 43 characters ).
		 *
		 * @param string $verifier Verifier.
		 * @return string
		 */
		public static function challenge( $verifier ) {
			return rtrim( strtr( base64_encode( hash( 'sha256', (string) $verifier, true ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- base64url, as PKCE.
		}

		/**
		 * Collect a new connection ( the return's wpec_stripe_claim ), once, with the verifier this person's Connect Stripe kept.
		 *
		 * @param string $claim The claim code.
		 * @return array|WP_Error access_token, refresh_token, publishable_key, user_id, sandbox ( bool ).
		 */
		public static function claim( $claim ) {
			$key      = self::VERIFIER . get_current_user_id();
			$verifier = (string) get_transient( $key );
			if ( '' === $verifier ) {
				return new WP_Error( 'stripe_claim', 'This Stripe connection was not started here, or took longer than 30 minutes.' );
			}
			if ( ! preg_match( '/^[a-f0-9]{32}$/', (string) $claim ) ) {
				return new WP_Error( 'stripe_claim', 'The Stripe connection code is not valid.' );
			}
			$response = wp_remote_post(
				self::url( 'api.php?action=claim' ),
				array(
					'timeout'     => 20,
					'redirection' => 0,
					'headers'     => array( 'Accept' => 'application/json' ),
					'body'        => array(
						'claim'    => (string) $claim,
						'verifier' => $verifier,
					),
				)
			);
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			if ( ! is_array( $data ) || empty( $data['access_token'] ) || empty( $data['stripe_publishable_key'] ) || empty( $data['stripe_user_id'] ) ) {
				$message = ( is_array( $data ) && ! empty( $data['message'] ) && is_scalar( $data['message'] ) ) ? (string) $data['message'] : 'WP EasyCart Connect did not hand over the connection.';
				return new WP_Error( 'stripe_claim', $message );
			}
			delete_transient( $key );
			$token = '/[^A-Za-z0-9_\-]/';
			return array(
				'access_token'    => preg_replace( $token, '', (string) $data['access_token'] ),
				'refresh_token'   => isset( $data['refresh_token'] ) ? preg_replace( $token, '', (string) $data['refresh_token'] ) : '',
				'publishable_key' => preg_replace( $token, '', (string) $data['stripe_publishable_key'] ),
				'user_id'         => preg_replace( $token, '', (string) $data['stripe_user_id'] ),
				'sandbox'         => ! empty( $data['sandbox'] ),
			);
		}
	}

	add_action( 'admin_post_' . wp_easycart_stripe_connect_relay::CONNECT_ACTION, array( 'wp_easycart_stripe_connect_relay', 'handle_connect' ) );

endif;
