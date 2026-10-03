<?php
/**
 * Vatlayer: the VAT registration number check behind "Verify VAT numbers with Vatlayer" ( Settings › Taxes › VAT ).
 *
 * The checkout's rule is validate() ( ec_cartpage::validate_vat_registration_number() calls it ); Settings › Taxes › VAT ›
 * Test Vatlayer is test(), which asks Vatlayer about a number at once ( no saved answer ) and says what came back.
 *
 * @package WP_EasyCart
 * @since   6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_vatlayer' ) ) :

	/**
	 * Vatlayer's validate endpoint.
	 */
	class wp_easycart_vatlayer {

		/**
		 * A number Vatlayer's own documentation checks ( Amazon Europe Core, Luxembourg ): Test Vatlayer's default.
		 */
		const SAMPLE = 'LU26375245';

		/**
		 * The saved access key.
		 *
		 * @return string
		 */
		public static function access_key() {
			return trim( (string) get_option( 'ec_option_vatlayer_api_key' ) );
		}

		/**
		 * A number as Vatlayer takes it: letters and digits only.
		 *
		 * @param string $vat_number Number as entered.
		 * @return string
		 */
		public static function clean( $vat_number ) {
			return strtoupper( (string) preg_replace( '/[^A-Za-z0-9]/', '', trim( (string) $vat_number ) ) );
		}

		/**
		 * The checkout's rule: true when no check is asked for ( the VAT number field or the check is off, no key, or no
		 * number ), else Vatlayer's answer. An answer is kept for a day ( an invalid one for 10 minutes ) so checkout pages do
		 * not use up the monthly allowance; a Vatlayer error is logged without the access key and reads as not valid.
		 *
		 * @param string $vat_number Number as entered.
		 * @return bool
		 */
		public static function validate( $vat_number ) {
			$access_key = self::access_key();
			if ( '' === (string) $vat_number || ! get_option( 'ec_option_collect_vat_registration_number' ) || ! get_option( 'ec_option_validate_vat_registration_number' ) || '' === $access_key ) {
				return true; // No validation required.
			}
			$vat_number = self::clean( $vat_number );
			if ( '' === $vat_number ) {
				return false;
			}

			$cache_key = 'wpec_vatlayer_' . md5( $access_key . '|' . $vat_number );
			$cached    = get_transient( $cache_key );
			if ( 'valid' === $cached || 'invalid' === $cached ) {
				return ( 'valid' === $cached );
			}

			$asked  = self::ask( $access_key, $vat_number );
			$result = $asked['answer'];
			if ( ! is_array( $result ) || ! isset( $result['valid'] ) ) {
				$db = new ec_db();
				$db->insert_response( 0, 1, 'Vatlayer VAT Number ERROR', str_replace( $access_key, '[access key]', self::error_text( $result ) ) );
				return false;
			}

			$valid = self::is_valid( $result );
			set_transient( $cache_key, ( $valid ) ? 'valid' : 'invalid', ( $valid ) ? DAY_IN_SECONDS : 10 * MINUTE_IN_SECONDS );
			return $valid;
		}

		/**
		 * Asks Vatlayer: HTTPS when the plan allows it ( the free plan answers over HTTP only, so the check falls back and
		 * remembers that for a day ).
		 *
		 * @param string $access_key Access key.
		 * @param string $vat_number Cleaned number.
		 * @return array answer ( decoded array, WP_Error or null ), scheme ( https | http ).
		 */
		public static function ask( $access_key, $vat_number ) {
			$http_only = (bool) get_transient( 'wpec_vatlayer_http_only' );
			$scheme    = ( $http_only ) ? 'http' : 'https';
			$result    = self::request( $scheme, $access_key, $vat_number );
			if ( ! $http_only && is_array( $result ) && isset( $result['error'] ) && is_array( $result['error'] ) && ( ( isset( $result['error']['type'] ) && 'https_access_restricted' === $result['error']['type'] ) || ( isset( $result['error']['code'] ) && 105 === (int) $result['error']['code'] ) ) ) {
				set_transient( 'wpec_vatlayer_http_only', 1, DAY_IN_SECONDS );
				$scheme = 'http';
				$result = self::request( $scheme, $access_key, $vat_number );
			}
			return array(
				'answer' => $result,
				'scheme' => $scheme,
			);
		}

		/**
		 * One validate call. The access key has to travel in the query string ( Vatlayer takes it no other way ), so the
		 * URL is never logged.
		 *
		 * @param string $scheme     https | http.
		 * @param string $access_key Access key.
		 * @param string $vat_number Cleaned number.
		 * @return array|WP_Error|null Decoded answer.
		 */
		public static function request( $scheme, $access_key, $vat_number ) {
			$response = wp_remote_get(
				$scheme . '://apilayer.net/api/validate?access_key=' . rawurlencode( $access_key ) . '&vat_number=' . rawurlencode( $vat_number ),
				array( 'timeout' => 30 )
			);
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			return json_decode( wp_remote_retrieve_body( $response ), true );
		}

		/**
		 * Vatlayer says the number is valid.
		 *
		 * @param array $result Answer.
		 * @return bool
		 */
		public static function is_valid( $result ) {
			return is_array( $result ) && isset( $result['valid'] ) && ( true === $result['valid'] || 'true' === $result['valid'] || 1 === $result['valid'] );
		}

		/**
		 * What went wrong, from a failed answer.
		 *
		 * @param mixed $result Answer.
		 * @return string
		 */
		public static function error_text( $result ) {
			if ( is_wp_error( $result ) ) {
				return $result->get_error_message();
			}
			if ( is_array( $result ) && isset( $result['error'] ) && is_array( $result['error'] ) ) {
				return trim( ( isset( $result['error']['code'] ) ? $result['error']['code'] . ' ' : '' ) . ( isset( $result['error']['type'] ) ? $result['error']['type'] . ': ' : '' ) . ( isset( $result['error']['info'] ) ? $result['error']['info'] : '' ) );
			}
			return 'Unexpected answer';
		}

		/**
		 * Settings › Taxes › VAT › Test Vatlayer: checks a number now ( the one typed, else SAMPLE ) with the saved key and
		 * says what Vatlayer answered, and whether checkout asks it.
		 *
		 * @param string $vat_number Number typed beside the button ( '' for SAMPLE ).
		 * @return array|WP_Error message, or what stopped the test.
		 */
		public static function test( $vat_number = '' ) {
			$access_key = self::access_key();
			if ( '' === $access_key ) {
				return new WP_Error( 'vatlayer_key', __( 'Save your Vatlayer API key first ( under "Verify VAT numbers with Vatlayer" ).', 'wp-easycart' ) );
			}
			$number = self::clean( $vat_number );
			$sample = ( '' === $number );
			if ( $sample ) {
				$number = self::SAMPLE;
			}
			/* The last Vatlayer answer over HTTP only is forgotten, so the test finds out again whether the plan allows HTTPS. */
			delete_transient( 'wpec_vatlayer_http_only' );
			$asked  = self::ask( $access_key, $number );
			$result = $asked['answer'];
			$db     = new ec_db();
			if ( ! is_array( $result ) || ! isset( $result['valid'] ) ) {
				$error = str_replace( $access_key, '[access key]', self::error_text( $result ) );
				$db->insert_response( 0, 1, 'Vatlayer Test ERROR', $error );
				$code = ( is_array( $result ) && isset( $result['error']['code'] ) ) ? (int) $result['error']['code'] : 0;
				if ( 101 === $code ) {
					/* translators: %s: Vatlayer's own message. */
					return new WP_Error( 'vatlayer_key', sprintf( __( 'Vatlayer did not accept the API key ( %s ). Copy it again from your Vatlayer dashboard.', 'wp-easycart' ), $error ) );
				}
				if ( 104 === $code ) {
					/* translators: %s: Vatlayer's own message. */
					return new WP_Error( 'vatlayer_limit', sprintf( __( 'Your Vatlayer plan has used this month\'s checks ( %s ). Checkout treats numbers as not valid until the allowance renews or the plan is upgraded.', 'wp-easycart' ), $error ) );
				}
				/* translators: %s: the error. */
				return new WP_Error( 'vatlayer_error', sprintf( __( 'Vatlayer did not answer the check: %s', 'wp-easycart' ), $error ) );
			}
			$db->insert_response( 0, 0, 'Vatlayer Test', wp_json_encode( $result ) );

			$parts   = array();
			$parts[] = ( 'https' === $asked['scheme'] )
				? __( 'Connected to Vatlayer.', 'wp-easycart' )
				: __( 'Connected to Vatlayer over HTTP ( your Vatlayer plan does not include HTTPS ).', 'wp-easycart' );
			$company = isset( $result['company_name'] ) ? trim( (string) $result['company_name'] ) : '';
			$country = isset( $result['country_code'] ) ? trim( (string) $result['country_code'] ) : '';
			if ( self::is_valid( $result ) ) {
				$parts[] = ( '' !== $company && '---' !== $company )
					/* translators: 1: VAT number, 2: company name, 3: country code. */
					? sprintf( __( '%1$s is valid: %2$s ( %3$s ).', 'wp-easycart' ), $number, $company, $country )
					/* translators: %s: VAT number. */
					: sprintf( __( '%s is valid.', 'wp-easycart' ), $number );
			} elseif ( isset( $result['database'] ) && 'ok' !== $result['database'] ) {
				/* translators: %s: VAT number. */
				$parts[] = sprintf( __( '%s could not be checked: the EU\'s VAT register ( VIES ) did not answer Vatlayer. Checkout treats numbers as not valid until it is back.', 'wp-easycart' ), $number );
			} elseif ( isset( $result['format_valid'] ) && ! $result['format_valid'] ) {
				/* translators: %s: VAT number. */
				$parts[] = sprintf( __( '%s is not a VAT number: its format is wrong for the country in its first two letters.', 'wp-easycart' ), $number );
			} else {
				/* translators: %s: VAT number. */
				$parts[] = sprintf( __( '%s is not a registered VAT number.', 'wp-easycart' ), $number );
			}
			if ( $sample ) {
				$parts[] = __( '( A sample number: type one beside the button to check your own. )', 'wp-easycart' );
			}
			if ( ! get_option( 'ec_option_use_vat_tax' ) || ! get_option( 'ec_option_collect_vat_registration_number' ) || ! get_option( 'ec_option_validate_vat_registration_number' ) ) {
				$parts[] = __( 'Checkout does not ask Vatlayer yet: it needs VAT on, "VAT registration number" on Settings › Checkout and "Verify VAT numbers with Vatlayer".', 'wp-easycart' );
			}
			return array( 'message' => implode( ' ', $parts ) );
		}
	}

endif;
