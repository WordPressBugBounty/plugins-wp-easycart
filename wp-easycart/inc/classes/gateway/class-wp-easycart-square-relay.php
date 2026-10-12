<?php
/**
 * WP EasyCart Connect's Square relay, v3 ( 6.0.4 ): every call a store connected through the WP EasyCart Square app makes to
 * connect.wpeasycart.com, and its Connect Square links.
 *
 * Before 6.0.4 the store sent its Square tokens to /square-v2/ and /square-sandbox/ in the address ( refresh.php?refresh_token=…,
 * disconnect.php?access_token=…, webhook-create.php?accessToken=… ), and Connect handed a new connection's tokens back to the
 * store's admin in the address too, so they sat in web server access logs and the merchant's browser history. v3 takes them only
 * in a POST body ( /square-v3/api.php ), and a new connection comes back as a one-time claim: Connect Square goes through
 * admin-post ( handle_connect() ), which keeps a verifier on this server and sends Connect only its SHA-256 ( the challenge ); the
 * return carries the claim, which only this server can collect ( claim() ).
 *
 * WP EasyCart 6.0.3 and older keep calling the v2 scripts, which stay as they are. While /square-v3/ is missing on Connect
 * ( a 404: this release reached a store before the relay was uploaded ) every call goes to v2 as before, so nothing stops
 * working; once /square-v3/ answers, tokens never go in an address again. post() answers exactly what WP_Http answers ( an
 * array with the body, or a WP_Error ), or null for "use v2", so each caller keeps its own reading and logging.
 *
 * Contract: GitHub/wp-easycart-connect/docs/square-v3.md ( outside the plugins ).
 *
 * @package wp-easycart
 * @since 6.0.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_square_relay' ) ) :

	/**
	 * WP EasyCart Connect's Square relay ( /square-v3/, else /square-v2/ and /square-sandbox/ ).
	 */
	final class wp_easycart_square_relay {

		/** WP EasyCart Connect ( filter wp_easycart_square_relay_url ). */
		const BASE = 'https://connect.wpeasycart.com';

		/** Whether /square-v3/ answers here: 'yes' ( 12 hours ) or 'no' ( 15 minutes, then asked again ). */
		const AVAILABLE = 'wpec_square_relay_v3';

		/** The verifier of the Connect Square a person started, per user ( 30 minutes ). */
		const VERIFIER = 'wpec_square_verifier_';

		/** The admin-post action of the Connect Square links. */
		const CONNECT_ACTION = 'wp_easycart_square_connect';

		/**
		 * WP EasyCart Connect's address.
		 *
		 * @return string Without a trailing slash.
		 */
		public static function base() {
			return untrailingslashit( (string) apply_filters( 'wp_easycart_square_relay_url', self::BASE ) );
		}

		/**
		 * A /square-v3/ address.
		 *
		 * @param string $file api.php | start.php.
		 * @return string
		 */
		public static function url( $file ) {
			return self::base() . '/square-v3/' . $file;
		}

		/**
		 * A v2 address ( stores before 6.0.4, and this one while /square-v3/ is missing ).
		 *
		 * @param bool   $sandbox Square's test account.
		 * @param string $file    '' ( the connect page ), refresh.php, disconnect.php, webhook-create.php, webhook-delete.php.
		 * @return string
		 */
		public static function legacy_url( $sandbox, $file = '' ) {
			return self::base() . ( $sandbox ? '/square-sandbox/' : '/square-v2/' ) . $file;
		}

		/**
		 * The mode in use ( ec_option_square_is_sandbox ).
		 *
		 * @return bool True for Square's test account.
		 */
		public static function sandbox() {
			return (bool) get_option( 'ec_option_square_is_sandbox' );
		}

		/**
		 * Whether an answer came from /square-v3/ ( JSON with "api": 3, whatever its status ).
		 *
		 * @param array|WP_Error $response WP_Http answer.
		 * @return bool
		 */
		public static function is_v3( $response ) {
			if ( is_wp_error( $response ) ) {
				return false;
			}
			$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			return is_array( $data ) && isset( $data['api'] ) && 3 === (int) $data['api'];
		}

		/**
		 * Whether /square-v3/ answers on WP EasyCart Connect. Only a 404 counts as missing: an outage or an error answer still
		 * means v3, so a store never falls back to sending its tokens in an address because Connect had a bad minute.
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
			$response = wp_remote_get(
				self::url( 'api.php?action=ping' ),
				array(
					'timeout'     => 8,
					'redirection' => 0,
				)
			);
			return self::remember( $response );
		}

		/**
		 * Keep what an answer says about /square-v3/.
		 *
		 * @param array|WP_Error $response WP_Http answer.
		 * @return bool Whether /square-v3/ is there.
		 */
		private static function remember( $response ) {
			if ( self::is_v3( $response ) ) { /* v3's own answers, a 404 for a claim already collected included */
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
		 * One call to /square-v3/api.php: tokens only in the body.
		 *
		 * @param string $action  claim | refresh | disconnect | webhook-create | webhook-delete.
		 * @param bool   $sandbox Square's test account.
		 * @param array  $body    The call's fields.
		 * @param int    $timeout Seconds.
		 * @return array|WP_Error|null WP_Http's answer, or null when /square-v3/ is missing ( call v2 as before ).
		 */
		public static function post( $action, $sandbox, array $body, $timeout = 30 ) {
			if ( ! self::available() ) {
				return null;
			}
			$response = wp_remote_post(
				self::url( 'api.php?action=' . rawurlencode( $action ) . '&mode=' . ( $sandbox ? 'sandbox' : 'live' ) ),
				array(
					'timeout'     => (int) $timeout,
					'redirection' => 0,
					'headers'     => array( 'Accept' => 'application/json' ),
					'body'        => $body,
				)
			);
			if ( ! self::remember( $response ) ) {
				return null;
			}
			return $response;
		}

		/**
		 * New tokens from the stored refresh token ( or, with none, the access token: Square's migration grant ). The answer
		 * carries access_token, refresh_token, expires_at, as refresh.php's did.
		 *
		 * @param bool   $sandbox       Square's test account.
		 * @param string $refresh_token Refresh token ( '' = none ).
		 * @param string $access_token  Access token.
		 * @return array|WP_Error WP_Http's answer.
		 */
		public static function refresh( $sandbox, $refresh_token, $access_token ) {
			$body     = ( '' !== (string) $refresh_token ) ? array( 'refresh_token' => (string) $refresh_token ) : array( 'access_token' => (string) $access_token );
			$response = self::post( 'refresh', $sandbox, $body, 30 );
			if ( null !== $response ) {
				return $response;
			}
			$query = ( '' !== (string) $refresh_token ) ? 'refresh_token=' . rawurlencode( $refresh_token ) : 'access_token=' . rawurlencode( $access_token ) . '&refreshv2=true';
			return wp_remote_get(
				self::legacy_url( $sandbox, 'refresh.php?' . $query ),
				array( 'timeout' => 30 )
			);
		}

		/**
		 * Revoke the store's access to the merchant's Square account ( Disconnect ).
		 *
		 * @param bool   $sandbox      Square's test account.
		 * @param string $access_token Access token.
		 * @return array|WP_Error WP_Http's answer.
		 */
		public static function disconnect( $sandbox, $access_token ) {
			if ( '' === (string) $access_token ) {
				return new WP_Error( 'square_no_token', 'No access token.' );
			}
			$response = self::post( 'disconnect', $sandbox, array( 'access_token' => (string) $access_token ), 5 );
			if ( null !== $response ) {
				return $response;
			}
			return wp_remote_get(
				self::legacy_url( $sandbox, 'disconnect.php?access_token=' . rawurlencode( $access_token ) ),
				array( 'timeout' => 5 )
			);
		}

		/**
		 * Register the store for the merchant's Square notifications ( WP EasyCart PRO ). Answers success added | updated and
		 * webhook_id, or error, as webhook-create.php did.
		 *
		 * @param bool   $sandbox          Square's test account.
		 * @param string $merchant_id      Merchant.
		 * @param string $access_token     Access token.
		 * @param string $webhook_url      The store's Square hook.
		 * @param string $notification_key The key notifications are signed with.
		 * @return array|WP_Error WP_Http's answer.
		 */
		public static function webhook_create( $sandbox, $merchant_id, $access_token, $webhook_url, $notification_key ) {
			$response = self::post(
				'webhook-create',
				$sandbox,
				array(
					'merchant_id'      => (string) $merchant_id,
					'access_token'     => (string) $access_token,
					'webhook_url'      => (string) $webhook_url,
					'notification_key' => (string) $notification_key,
				),
				8
			);
			if ( null !== $response ) {
				return $response;
			}
			return wp_remote_get(
				self::legacy_url( $sandbox, 'webhook-create.php?merchantID=' . rawurlencode( $merchant_id ) . '&accessToken=' . rawurlencode( $access_token ) . '&webhookURL=' . rawurlencode( $webhook_url ) . '&notificationKey=' . rawurlencode( $notification_key ) ),
				array( 'timeout' => 8 )
			);
		}

		/**
		 * Stop forwarding the merchant's Square notifications to this store ( WP EasyCart PRO ).
		 *
		 * @param bool   $sandbox      Square's test account.
		 * @param string $merchant_id  Merchant.
		 * @param string $access_token Access token.
		 * @param string $webhook_id   Registration id.
		 * @return array|WP_Error WP_Http's answer.
		 */
		public static function webhook_delete( $sandbox, $merchant_id, $access_token, $webhook_id ) {
			$response = self::post(
				'webhook-delete',
				$sandbox,
				array(
					'merchant_id'  => (string) $merchant_id,
					'access_token' => (string) $access_token,
					'webhook_id'   => (string) $webhook_id,
				),
				8
			);
			if ( null !== $response ) {
				return $response;
			}
			return wp_remote_get(
				self::legacy_url( $sandbox, 'webhook-delete.php?merchantID=' . rawurlencode( $merchant_id ) . '&accessToken=' . rawurlencode( $access_token ) . '&webhookID=' . rawurlencode( $webhook_id ) ),
				array( 'timeout' => 8 )
			);
		}

		/**
		 * Where Connect sends the merchant back ( process_square_app() ).
		 *
		 * @param string $back '' | wizard | import.
		 * @return string
		 */
		public static function return_url( $back = '' ) {
			$url = esc_url_raw( admin_url() ) . '?ec_admin_form_action=handle-square';
			if ( in_array( $back, array( 'wizard', 'import' ), true ) ) {
				$url .= '&goto=' . $back;
			}
			return $url;
		}

		/**
		 * A Connect Square link: admin-post, which keeps the verifier here and sends the merchant on ( handle_connect() ).
		 *
		 * @param bool|null $sandbox Square's test account ( null = the mode in use ).
		 * @param string    $back    '' | wizard | import.
		 * @return string
		 */
		public static function connect_url( $sandbox = null, $back = '' ) {
			$sandbox = ( null === $sandbox ) ? self::sandbox() : (bool) $sandbox;
			$args    = array(
				'action' => self::CONNECT_ACTION,
				'mode'   => $sandbox ? 'sandbox' : 'live',
			);
			if ( in_array( $back, array( 'wizard', 'import' ), true ) ) {
				$args['goto'] = $back;
			}
			$args['_wpnonce'] = wp_create_nonce( 'wp-easycart-square-connect' ); /* not wp_nonce_url(): its &amp; would reach scripts as is */
			return add_query_arg( $args, admin_url( 'admin-post.php' ) );
		}

		/**
		 * Connect Square ( admin-post ): /square-v3/start.php with a challenge, or the v2 page while /square-v3/ is missing.
		 */
		public static function handle_connect() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_settings' ) ) {
				wp_die( esc_html__( 'You do not have permission to connect Square.', 'wp-easycart' ), '', array( 'response' => 403 ) );
			}
			check_admin_referer( 'wp-easycart-square-connect' );
			$sandbox = isset( $_GET['mode'] ) && 'sandbox' === sanitize_key( wp_unslash( $_GET['mode'] ) );
			$back    = isset( $_GET['goto'] ) ? sanitize_key( wp_unslash( $_GET['goto'] ) ) : '';
			$return  = self::return_url( $back );
			$state   = wp_create_nonce( 'wp-easycart-square' );

			if ( self::available() ) {
				$verifier  = self::verifier();
				$challenge = self::challenge( $verifier );
				set_transient( self::VERIFIER . get_current_user_id(), $verifier, 30 * MINUTE_IN_SECONDS );
				$url = add_query_arg(
					array(
						'mode'      => $sandbox ? 'sandbox' : 'live',
						'return'    => rawurlencode( $return ),
						'state'     => $state,
						'challenge' => $challenge,
					),
					self::url( 'start.php' )
				);
			} else {
				$url = self::legacy_url( $sandbox ) . '?url=' . rawurlencode( $return ) . '&state=' . $state;
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
		 * Collect a new connection's tokens ( the return's wpec_square_claim ), once, with the verifier this person's Connect
		 * Square kept.
		 *
		 * @param string $claim The claim code.
		 * @return array|WP_Error access_token, refresh_token, expires, merchant_id, sandbox ( bool ).
		 */
		public static function claim( $claim ) {
			$key      = self::VERIFIER . get_current_user_id();
			$verifier = (string) get_transient( $key );
			if ( '' === $verifier ) {
				return new WP_Error( 'square_claim', 'This Square connection was not started here, or took longer than 30 minutes.' );
			}
			if ( ! preg_match( '/^[a-f0-9]{32}$/', (string) $claim ) ) {
				return new WP_Error( 'square_claim', 'The Square connection code is not valid.' );
			}
			$response = self::post(
				'claim',
				false,
				array(
					'claim'    => (string) $claim,
					'verifier' => $verifier,
				),
				20
			);
			if ( null === $response ) {
				return new WP_Error( 'square_claim', 'WP EasyCart Connect could not hand over the connection.' );
			}
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			if ( ! is_array( $data ) || empty( $data['access_token'] ) ) {
				$message = ( is_array( $data ) && ! empty( $data['message'] ) && is_scalar( $data['message'] ) ) ? (string) $data['message'] : 'WP EasyCart Connect did not hand over the connection.';
				return new WP_Error( 'square_claim', $message );
			}
			delete_transient( $key );
			$token = '/[^A-Za-z0-9 \-\._\~\+\/]/';
			return array(
				'access_token'  => preg_replace( $token, '', (string) $data['access_token'] ),
				'refresh_token' => isset( $data['refresh_token'] ) ? preg_replace( $token, '', (string) $data['refresh_token'] ) : '',
				'expires'       => isset( $data['expires_at'] ) ? preg_replace( '/[^A-Za-z0-9 \-\:]/', '', (string) $data['expires_at'] ) : '',
				'merchant_id'   => isset( $data['merchant_id'] ) ? preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $data['merchant_id'] ) : '',
				'sandbox'       => ! empty( $data['sandbox'] ),
			);
		}
	}

	add_action( 'admin_post_' . wp_easycart_square_relay::CONNECT_ACTION, array( 'wp_easycart_square_relay', 'handle_connect' ) );

endif;
