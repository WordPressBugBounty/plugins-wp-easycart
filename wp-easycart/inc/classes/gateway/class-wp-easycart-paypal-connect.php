<?php
/**
 * WP EasyCart Connect's PayPal relay: one client for every call a store connected through the WP EasyCart PayPal app makes
 * to connect.wpeasycart.com, and for every Connect PayPal link ( 6.0.2 ).
 *
 * WP EasyCart 6.0.2 calls /paypal-v3/api.php?action=<action>&mode=<sandbox|production>&<the call's own parameters>, with
 * the same method, body and answer as the /paypal-v2/ script the action replaces, and signs every call with the store's
 * notification key when it holds one for the mode ( the stored key, and the pending one while a registration is not
 * confirmed ). WP EasyCart 6.0.1 and older keep calling the /paypal-v2/ scripts, which stay as they are. The filter
 * wp_easycart_paypal_connect_version ( 'v3', or 'v2' ) sends a store back to the v2 scripts without a release: the same
 * parameters, is_sandbox where the v2 script took it, unsigned.
 *
 * request() answers exactly what WP_Http::request() answers ( an array with the body, or a WP_Error ), so every caller
 * keeps its own reading and logging. The contract: GitHub/wp-easycart-connect/docs/paypal-v3.md ( outside the plugins ).
 *
 * @package wp-easycart
 * @since 6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_paypal_connect' ) ) :

	/**
	 * WP EasyCart Connect's PayPal relay ( /paypal-v3/, or /paypal-v2/ through the version filter ).
	 */
	final class wp_easycart_paypal_connect {

		/** WP EasyCart Connect ( filter wp_easycart_paypal_connect_url ). */
		const BASE = 'https://connect.wpeasycart.com';

		/** The relay's actions, each with the /paypal-v2/ script it replaces ( {mode} = sandbox | production ). */
		const V2_SCRIPTS = array(
			'create_order'         => '{mode}-create-order_v2.php',
			'update_order'         => '{mode}-update-order_v2.php',
			'capture'              => '{mode}-payment-submit_v2.php',
			'refund'               => '{mode}-order-refund.php',
			'order_status'         => '{mode}-order-verify.php',
			'order_pay'            => '{mode}-order-submit.php',
			'payment_execute'      => '{mode}-payment-submit.php',
			'payment_verify'       => '{mode}-payment-verify.php',
			'payment_order_verify' => '{mode}-payment-order-verify.php',
			'add_order'            => 'webhook-add.php',
			'register'             => 'webhook-create.php',
		);

		/** The v2 scripts that take the mode as is_sandbox ( the others are one file per mode ). */
		const V2_IS_SANDBOX = array( 'add_order', 'register' );

		/**
		 * The relay version in use ( filter wp_easycart_paypal_connect_version: 'v3', or 'v2' to go back to the older scripts ).
		 *
		 * @return string v3 | v2
		 */
		public static function version() {
			return ( 'v2' === apply_filters( 'wp_easycart_paypal_connect_version', 'v3' ) ) ? 'v2' : 'v3';
		}

		/**
		 * WP EasyCart Connect's address.
		 *
		 * @param string $base An address to use instead ( '' = WP EasyCart Connect, filter wp_easycart_paypal_connect_url ).
		 * @return string Without a trailing slash.
		 */
		public static function base( $base = '' ) {
			if ( '' === (string) $base ) {
				$base = apply_filters( 'wp_easycart_paypal_connect_url', self::BASE );
			}
			return untrailingslashit( (string) $base );
		}

		/**
		 * A mode name, or the one in use ( ec_option_paypal_use_sandbox ).
		 *
		 * @param string $mode sandbox | production | ''.
		 * @return string sandbox | production
		 */
		public static function mode( $mode = '' ) {
			if ( 'sandbox' === $mode || 'production' === $mode ) {
				return $mode;
			}
			return get_option( 'ec_option_paypal_use_sandbox' ) ? 'sandbox' : 'production';
		}

		/**
		 * The address WP EasyCart Connect sends this store's PayPal events to ( the webhookURL it registers ).
		 *
		 * @return string
		 */
		public static function site() {
			if ( class_exists( 'wp_easycart_paypal_webhooks' ) ) {
				return wp_easycart_paypal_webhooks::webhook_url();
			}
			return wp_easycart_hook_url( 'paypal-webhook' );
		}

		/**
		 * A call's own query parameters as strings ( action and mode are the client's own ).
		 *
		 * @param array $query name => value.
		 * @return array
		 */
		public static function params( $query ) {
			$params = array();
			foreach ( (array) $query as $name => $value ) {
				$name = (string) $name;
				if ( '' === $name || 'action' === $name || 'mode' === $name ) {
					continue;
				}
				if ( is_bool( $value ) ) {
					$value = $value ? '1' : '0';
				} elseif ( null === $value ) {
					$value = '';
				} elseif ( ! is_scalar( $value ) ) {
					continue;
				}
				$params[ $name ] = (string) $value;
			}
			return $params;
		}

		/**
		 * The query part of the canonical string: the parameters other than action and mode, sorted by name.
		 *
		 * @param array $params params().
		 * @return string
		 */
		public static function canonical_query( $params ) {
			ksort( $params );
			return http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
		}

		/**
		 * The string a call is signed over ( the contract's canonical string ).
		 *
		 * @param string $action      Relay action.
		 * @param string $mode        sandbox | production.
		 * @param string $merchant_id The call's merchantID ( '' when it has none ).
		 * @param string $site        X-EasyCart-Site.
		 * @param int    $timestamp   X-EasyCart-Timestamp.
		 * @param string $query       canonical_query().
		 * @param string $body        The raw body ( '' when none ).
		 * @return string
		 */
		public static function canonical( $action, $mode, $merchant_id, $site, $timestamp, $query, $body ) {
			return "v3\n" . $action . "\n" . $mode . "\n" . $merchant_id . "\n" . $site . "\n" . $timestamp . "\n" . $query . "\n" . hash( 'sha256', (string) $body );
		}

		/**
		 * The signing headers for a call, when the store holds a key for the mode ( none otherwise: Connect accepts the call
		 * unsigned, as v2 did ).
		 *
		 * @param string   $action    Relay action.
		 * @param string   $mode      sandbox | production.
		 * @param array    $params    params().
		 * @param string   $body      The raw body ( '' when none ).
		 * @param int|null $timestamp Unix seconds ( null = now ).
		 * @return array header => value
		 */
		public static function signature_headers( $action, $mode, $params, $body, $timestamp = null ) {
			$key     = (string) get_option( 'ec_option_paypal_' . $mode . '_webhook_key' );
			$pending = class_exists( 'wp_easycart_paypal_webhooks' ) ? (string) wp_easycart_paypal_webhooks::pending_key( $mode ) : '';
			if ( '' === $key && '' === $pending ) {
				return array();
			}
			$timestamp = ( null === $timestamp ) ? time() : (int) $timestamp;
			$site      = self::site();
			$canonical = self::canonical( $action, $mode, isset( $params['merchantID'] ) ? $params['merchantID'] : '', $site, $timestamp, self::canonical_query( $params ), $body );
			$headers   = array(
				'X-EasyCart-Site'      => $site,
				'X-EasyCart-Timestamp' => (string) $timestamp,
			);
			if ( '' !== $key ) {
				$headers['X-EasyCart-Signature'] = hash_hmac( 'sha256', $canonical, $key );
			}
			if ( '' !== $pending && $pending !== $key ) {
				$headers['X-EasyCart-Signature-Pending'] = hash_hmac( 'sha256', $canonical, $pending );
			}
			return $headers;
		}

		/**
		 * The address of a call.
		 *
		 * @param string $action Relay action ( a key of V2_SCRIPTS ).
		 * @param string $mode   sandbox | production.
		 * @param array  $params params().
		 * @param string $base   base().
		 * @return string '' for an unknown action.
		 */
		public static function url( $action, $mode, $params, $base ) {
			$scripts = self::V2_SCRIPTS;
			if ( ! isset( $scripts[ $action ] ) ) {
				return '';
			}
			if ( 'v2' === self::version() ) {
				if ( in_array( $action, self::V2_IS_SANDBOX, true ) ) {
					$params = array_merge( array( 'is_sandbox' => ( 'sandbox' === $mode ) ? '1' : '0' ), $params );
				}
				$script = str_replace( '{mode}', $mode, $scripts[ $action ] );
				return $base . '/paypal-v2/' . $script . ( $params ? '?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 ) : '' );
			}
			return $base . '/paypal-v3/api.php?' . http_build_query(
				array_merge(
					array(
						'action' => $action,
						'mode'   => $mode,
					),
					$params
				),
				'',
				'&',
				PHP_QUERY_RFC3986
			);
		}

		/**
		 * Call WP EasyCart Connect's PayPal relay.
		 *
		 * @param string            $action Relay action: create_order, update_order, capture, refund, order_status, order_pay,
		 *                                  payment_execute, payment_verify, payment_order_verify, add_order, register.
		 * @param array             $query  The call's own query parameters ( merchantID, orderID, webhookURL, … ).
		 * @param string|array|null $body   The raw body ( an array or object is JSON encoded ); null sends none.
		 * @param array             $args   WP_Http arguments ( method, timeout, headers, … ), plus mode ( sandbox | production,
		 *                                  default the mode in use ) and base ( another address than WP EasyCart Connect ).
		 * @return array|WP_Error What WP_Http::request() answers.
		 */
		public static function request( $action, $query = array(), $body = null, $args = array() ) {
			$args   = (array) $args;
			$mode   = self::mode( isset( $args['mode'] ) ? (string) $args['mode'] : '' );
			$base   = self::base( isset( $args['base'] ) ? (string) $args['base'] : '' );
			$params = self::params( $query );
			$url    = self::url( (string) $action, $mode, $params, $base );
			if ( '' === $url ) {
				return new WP_Error( 'wp_easycart_paypal_connect_action', 'Unknown WP EasyCart Connect PayPal action: ' . sanitize_key( (string) $action ) );
			}
			unset( $args['mode'], $args['base'] );
			$raw = '';
			if ( null !== $body ) {
				$raw          = is_scalar( $body ) ? (string) $body : (string) wp_json_encode( $body );
				$args['body'] = $raw;
			}
			if ( ! isset( $args['method'] ) ) {
				$args['method'] = ( null === $body ) ? 'GET' : 'POST';
			}
			if ( 'v3' === self::version() ) {
				$headers = self::signature_headers( (string) $action, $mode, $params, $raw );
				if ( $headers ) {
					$args['headers'] = array_merge( isset( $args['headers'] ) ? (array) $args['headers'] : array(), $headers );
				}
			}
			return wp_remote_request( $url, $args );
		}

		/**
		 * Connect PayPal: the merchant's browser goes to WP EasyCart Connect's onboarding, which sends it back to $return_url.
		 *
		 * @param string $mode       sandbox | production.
		 * @param string $return_url The store's admin address to come back to ( with its own query: mode, nonce ).
		 * @return string
		 */
		public static function onboard_url( $mode, $return_url ) {
			$mode = ( 'sandbox' === $mode ) ? 'sandbox' : 'production';
			if ( 'v2' === self::version() ) {
				return self::base() . '/paypal-v2/' . $mode . '_onboard.php?redirect=' . rawurlencode( (string) $return_url );
			}
			return self::base() . '/paypal-v3/onboard.php?' . http_build_query(
				array(
					'mode'     => $mode,
					'redirect' => (string) $return_url,
				),
				'',
				'&',
				PHP_QUERY_RFC3986
			);
		}

		/**
		 * Did WP EasyCart Connect refuse a call because its signature did not match ( {"error":"signature"}, HTTP 403: a
		 * refund while the store's key is not the one Connect holds for it )?
		 *
		 * @param array|WP_Error $response request() answer.
		 * @return bool
		 */
		public static function signature_refused( $response ) {
			if ( ! is_array( $response ) || ! isset( $response['body'] ) ) {
				return false;
			}
			$data = json_decode( (string) $response['body'] );
			return is_object( $data ) && isset( $data->error ) && 'signature' === $data->error;
		}

		/**
		 * What to tell the merchant when a refund was refused for its signature.
		 *
		 * @return string
		 */
		public static function refund_signature_text() {
			return __( 'The refund was not made: your store’s PayPal key does not match the one WP EasyCart Connect holds for it. Go to Settings › Payments › PayPal and choose Register again under Notifications, then try the refund again.', 'wp-easycart' );
		}
	}

endif;
