<?php
/**
 * WP EasyCart — bank details for Bill later ( 6.0.5, admin review M2 ).
 *
 * A store taking bank transfers enters its account in the fields its country uses, and every place that tells a shopper how to pay
 * ( the checkout's Bill later choice, the order confirmation, the invoice email, the pay link page ) lists them under the store's own
 * instructions, with the order number as the payment reference:
 *   uk     account name, sort code, account number ( IBAN and BIC for payments from abroad )
 *   iban   account holder, IBAN, BIC, bank ( the EU, the EEA, Switzerland and other IBAN countries )
 *   au     account name, BSB, account number, bank
 *   nz     account name, account number, bank
 *   us     account name, bank, routing number, account number
 *   ca     Interac e-Transfer email, account name, institution, transit and account numbers
 *   other  account name, bank, account number, IBAN, SWIFT / BIC
 * The scheme follows the store's country ( wp_easycart_carriers::store_country() ) until the store picks one. Saved in
 * ec_option_bank_details ( scheme + fields, every field kept when the scheme changes ); only the scheme's own fields print.
 * Storefront words: language section bank_details.
 *
 * @package WP_EasyCart
 * @since   6.0.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_bank_details' ) ) :

	/**
	 * Bank details by country.
	 */
	class wp_easycart_bank_details {

		/** Option. */
		const OPTION = 'ec_option_bank_details';

		/**
		 * Every field.
		 *
		 * @return array
		 */
		public static function fields() {
			return array( 'holder', 'bank', 'iban', 'bic', 'sort_code', 'account', 'bsb', 'routing', 'institution', 'transit', 'interac' );
		}

		/**
		 * The schemes and their fields, in the order they show.
		 *
		 * @return array scheme => fields.
		 */
		public static function schemes() {
			return array(
				'uk'    => array( 'holder', 'sort_code', 'account', 'iban', 'bic' ),
				'iban'  => array( 'holder', 'iban', 'bic', 'bank' ),
				'au'    => array( 'holder', 'bsb', 'account', 'bank' ),
				'nz'    => array( 'holder', 'account', 'bank' ),
				'us'    => array( 'holder', 'bank', 'routing', 'account' ),
				'ca'    => array( 'interac', 'holder', 'institution', 'transit', 'account' ),
				'other' => array( 'holder', 'bank', 'account', 'iban', 'bic' ),
			);
		}

		/**
		 * Countries that use IBANs for domestic transfers.
		 *
		 * @return array
		 */
		public static function iban_countries() {
			return array( 'AD', 'AE', 'AL', 'AT', 'AZ', 'BA', 'BE', 'BG', 'BH', 'BR', 'CH', 'CR', 'CY', 'CZ', 'DE', 'DK', 'DO', 'EE', 'EG', 'ES', 'FI', 'FO', 'FR', 'GE', 'GI', 'GL', 'GR', 'GT', 'HR', 'HU', 'IE', 'IL', 'IQ', 'IS', 'IT', 'JO', 'KW', 'KZ', 'LB', 'LC', 'LI', 'LT', 'LU', 'LV', 'LY', 'MC', 'MD', 'ME', 'MK', 'MR', 'MT', 'MU', 'NL', 'NO', 'PK', 'PL', 'PS', 'PT', 'QA', 'RO', 'RS', 'SA', 'SC', 'SE', 'SI', 'SK', 'SM', 'ST', 'SV', 'TL', 'TN', 'TR', 'UA', 'VA', 'VG', 'XK' );
		}

		/**
		 * The scheme a country uses.
		 *
		 * @param string $iso2 Country.
		 * @return string
		 */
		public static function scheme_for( $iso2 ) {
			$iso2 = strtoupper( trim( (string) $iso2 ) );
			$map  = array(
				'GB' => 'uk',
				'GG' => 'uk',
				'JE' => 'uk',
				'IM' => 'uk',
				'AU' => 'au',
				'NZ' => 'nz',
				'US' => 'us',
				'CA' => 'ca',
			);
			if ( isset( $map[ $iso2 ] ) ) {
				return $map[ $iso2 ];
			}
			return in_array( $iso2, self::iban_countries(), true ) ? 'iban' : 'other';
		}

		/**
		 * The store's country.
		 *
		 * @return string
		 */
		public static function store_country() {
			return ( class_exists( 'wp_easycart_carriers' ) && method_exists( 'wp_easycart_carriers', 'store_country' ) ) ? (string) wp_easycart_carriers::store_country() : '';
		}

		/**
		 * What is saved: scheme ( the store's choice, else its country's ) and every field.
		 *
		 * @return array
		 */
		public static function get() {
			$saved = get_option( self::OPTION, array() );
			$saved = is_array( $saved ) ? $saved : array();
			$out   = array( 'scheme' => ( isset( $saved['scheme'] ) && isset( self::schemes()[ $saved['scheme'] ] ) ) ? $saved['scheme'] : self::scheme_for( self::store_country() ) );
			foreach ( self::fields() as $field ) {
				$out[ $field ] = isset( $saved[ $field ] ) ? (string) $saved[ $field ] : '';
			}
			return $out;
		}

		/**
		 * Clean and check what was typed, then save it.
		 *
		 * @param string $scheme Scheme.
		 * @param array  $values Field => text.
		 * @return true|WP_Error code = the field.
		 */
		public static function save( $scheme, $values ) {
			$scheme = isset( self::schemes()[ $scheme ] ) ? $scheme : self::scheme_for( self::store_country() );
			$values = is_array( $values ) ? $values : array();
			$out    = array( 'scheme' => $scheme );
			foreach ( self::fields() as $field ) {
				$value = isset( $values[ $field ] ) ? trim( sanitize_text_field( (string) $values[ $field ] ) ) : '';
				$check = self::clean( $field, $value, $scheme );
				if ( is_wp_error( $check ) ) {
					return $check;
				}
				$out[ $field ] = $check;
			}
			update_option( self::OPTION, $out, false );
			/**
			 * Bank details were saved.
			 *
			 * @since 6.0.5
			 * @param array $out Scheme and fields.
			 */
			do_action( 'wp_easycart_bank_details_saved', $out );
			return true;
		}

		/**
		 * One field cleaned and checked ( only the scheme's own fields are checked; others are kept as typed ).
		 *
		 * @param string $field  Field.
		 * @param string $value  Text.
		 * @param string $scheme Scheme.
		 * @return string|WP_Error
		 */
		public static function clean( $field, $value, $scheme ) {
			$value = trim( (string) $value );
			if ( '' === $value || ! in_array( $field, self::schemes()[ $scheme ], true ) ) {
				return $value;
			}
			$digits = preg_replace( '/[\s\-]/', '', $value );
			switch ( $field ) {
				case 'iban':
					$iban = strtoupper( preg_replace( '/\s+/', '', $value ) );
					if ( ! self::iban_valid( $iban ) ) {
						return new WP_Error( 'iban', __( 'That IBAN is not valid: check it against your bank statement.', 'wp-easycart' ) );
					}
					return $iban;
				case 'bic':
					$bic = strtoupper( preg_replace( '/\s+/', '', $value ) );
					if ( 1 !== preg_match( '/^[A-Z]{4}[A-Z]{2}[A-Z0-9]{2}([A-Z0-9]{3})?$/', $bic ) ) {
						return new WP_Error( 'bic', __( 'A BIC ( SWIFT code ) has 8 or 11 letters and numbers, such as DEUTDEFF.', 'wp-easycart' ) );
					}
					return $bic;
				case 'sort_code':
					if ( 1 !== preg_match( '/^\d{6}$/', $digits ) ) {
						return new WP_Error( 'sort_code', __( 'A sort code has six digits, such as 12-34-56.', 'wp-easycart' ) );
					}
					return $digits;
				case 'bsb':
					if ( 1 !== preg_match( '/^\d{6}$/', $digits ) ) {
						return new WP_Error( 'bsb', __( 'A BSB has six digits, such as 062-000.', 'wp-easycart' ) );
					}
					return $digits;
				case 'routing':
					if ( ! self::routing_valid( $digits ) ) {
						return new WP_Error( 'routing', __( 'That routing number is not valid: it has nine digits and is printed on your checks.', 'wp-easycart' ) );
					}
					return $digits;
				case 'institution':
					if ( 1 !== preg_match( '/^\d{3}$/', $digits ) ) {
						return new WP_Error( 'institution', __( 'An institution number has three digits.', 'wp-easycart' ) );
					}
					return $digits;
				case 'transit':
					if ( 1 !== preg_match( '/^\d{5}$/', $digits ) ) {
						return new WP_Error( 'transit', __( 'A transit number has five digits.', 'wp-easycart' ) );
					}
					return $digits;
				case 'account':
					if ( 'uk' === $scheme && 1 !== preg_match( '/^\d{8}$/', $digits ) ) {
						return new WP_Error( 'account', __( 'A UK account number has eight digits.', 'wp-easycart' ) );
					}
					return ( 'uk' === $scheme ) ? $digits : $value;
				case 'interac':
					if ( ! is_email( $value ) ) {
						return new WP_Error( 'interac', __( 'Enter the email address Interac e-Transfers go to.', 'wp-easycart' ) );
					}
					return sanitize_email( $value );
			}
			return $value;
		}

		/**
		 * An IBAN's length and checksum ( ISO 13616 mod 97 ).
		 *
		 * @param string $iban Upper case, no spaces.
		 * @return bool
		 */
		public static function iban_valid( $iban ) {
			if ( 1 !== preg_match( '/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $iban ) ) {
				return false;
			}
			$moved = substr( $iban, 4 ) . substr( $iban, 0, 4 );
			$num   = '';
			foreach ( str_split( $moved ) as $char ) {
				$num .= ctype_alpha( $char ) ? (string) ( ord( $char ) - 55 ) : $char;
			}
			$rest = 0;
			foreach ( str_split( $num, 7 ) as $chunk ) {
				$rest = (int) ( ( $rest . $chunk ) % 97 );
			}
			return 1 === $rest;
		}

		/**
		 * A US routing ( ABA ) number's checksum.
		 *
		 * @param string $digits Digits.
		 * @return bool
		 */
		public static function routing_valid( $digits ) {
			if ( 1 !== preg_match( '/^\d{9}$/', (string) $digits ) ) {
				return false;
			}
			$d = array_map( 'intval', str_split( $digits ) );
			return 0 === ( 3 * ( $d[0] + $d[3] + $d[6] ) + 7 * ( $d[1] + $d[4] + $d[7] ) + ( $d[2] + $d[5] + $d[8] ) ) % 10;
		}

		/**
		 * A field as it is written.
		 *
		 * @param string $field Field.
		 * @param string $value Saved value.
		 * @return string
		 */
		public static function display( $field, $value ) {
			$value = (string) $value;
			switch ( $field ) {
				case 'iban':
					return trim( chunk_split( $value, 4, ' ' ) );
				case 'sort_code':
					return 6 === strlen( $value ) ? implode( '-', str_split( $value, 2 ) ) : $value;
				case 'bsb':
					return 6 === strlen( $value ) ? substr( $value, 0, 3 ) . '-' . substr( $value, 3 ) : $value;
			}
			return $value;
		}

		/**
		 * The storefront's word for a field ( language section bank_details ).
		 *
		 * @param string $key Phrase key.
		 * @return string
		 */
		public static function text( $key ) {
			$english = array(
				'title'       => 'Bank details',
				'holder'      => 'Account name',
				'bank'        => 'Bank',
				'iban'        => 'IBAN',
				'bic'         => 'BIC / SWIFT',
				'sort_code'   => 'Sort code',
				'account'     => 'Account number',
				'bsb'         => 'BSB',
				'routing'     => 'Routing number',
				'institution' => 'Institution number',
				'transit'     => 'Transit number',
				'interac'     => 'Interac e-Transfer',
				'reference'   => 'Payment reference',
				'order'       => 'Order [order_id]',
			);
			$text = '';
			if ( function_exists( 'wp_easycart_language' ) ) {
				$text = (string) wp_easycart_language()->get_text( 'bank_details', 'bank_details_' . $key );
			}
			return ( '' !== trim( $text ) ) ? $text : ( isset( $english[ $key ] ) ? $english[ $key ] : $key );
		}

		/**
		 * The lines a shopper sees: the scheme's filled fields, then the payment reference when the order is known.
		 *
		 * @param int|string $order_id Order number ( '' at checkout ).
		 * @return array of array( label, value ).
		 */
		public static function lines( $order_id = '' ) {
			$saved = self::get();
			$lines = array();
			foreach ( self::schemes()[ $saved['scheme'] ] as $field ) {
				if ( '' !== $saved[ $field ] ) {
					$lines[] = array( self::text( $field ), self::display( $field, $saved[ $field ] ) );
				}
			}
			if ( ! empty( $lines ) && '' !== (string) $order_id && 0 !== (int) $order_id ) {
				$lines[] = array( self::text( 'reference' ), str_replace( '[order_id]', (string) $order_id, self::text( 'order' ) ) );
			}
			/**
			 * The bank details a shopper sees.
			 *
			 * @since 6.0.5
			 * @param array      $lines    array( label, value ) each.
			 * @param int|string $order_id Order number, or ''.
			 * @param array      $saved    Scheme and fields.
			 */
			return (array) apply_filters( 'wp_easycart_bank_details_lines', $lines, $order_id, $saved );
		}

		/**
		 * The details as HTML ( a definition list ), '' when none are saved.
		 *
		 * @param int|string $order_id Order number.
		 * @param string     $style    Inline style for emails ( '' on pages ).
		 * @return string
		 */
		public static function html( $order_id = '', $style = '' ) {
			$lines = self::lines( $order_id );
			if ( empty( $lines ) ) {
				return '';
			}
			$out = '<dl class="ec_bank_details"' . ( '' !== $style ? ' style="margin:12px 0 0;"' : '' ) . '>';
			foreach ( $lines as $line ) {
				$out .= '<div class="ec_bank_details_row"' . ( '' !== $style ? ' style="margin:0 0 4px;"' : '' ) . '><dt' . ( '' !== $style ? ' style="display:inline;font-weight:600;"' : '' ) . '>' . esc_html( $line[0] ) . '</dt> <dd' . ( '' !== $style ? ' style="display:inline;margin:0;"' : '' ) . '>' . esc_html( $line[1] ) . '</dd></div>';
			}
			return $out . '</dl>';
		}

		/**
		 * The details as plain text lines ( "Label: value" ).
		 *
		 * @param int|string $order_id Order number.
		 * @return string
		 */
		public static function text_block( $order_id = '' ) {
			$out = array();
			foreach ( self::lines( $order_id ) as $line ) {
				$out[] = $line[0] . ': ' . $line[1];
			}
			return implode( "\n", $out );
		}
	}

endif;
