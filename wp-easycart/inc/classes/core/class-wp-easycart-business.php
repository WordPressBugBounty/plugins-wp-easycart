<?php
/**
 * WP EasyCart — the store's business details ( 6.0.5, admin review M7 and M5 ).
 *
 * The registered name, address and tax number a receipt or invoice must show ( EU and UK invoices: the seller's name, address and
 * VAT number; Australian tax invoices: the ABN ). Free: Settings › Store details › Business details ( ec_option_business_name,
 * ec_option_business_address, ec_option_business_tax_id, ec_option_business_tax_label ). Printed in the footer of the order emails
 * whose profile shows "Business details" ( wp_easycart_documents field seller, wp_easycart_documents::close_args() ), and used by the
 * invoice and credit note PDFs while their own business details box ( ec_option_pdf_seller_details ) is empty.
 *
 * @package WP_EasyCart
 * @since   6.0.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_business' ) ) :

	/**
	 * Business details.
	 */
	class wp_easycart_business {

		/**
		 * An option, trimmed.
		 *
		 * @param string $key Option.
		 * @return string
		 */
		private static function get( $key ) {
			return trim( wp_unslash( (string) get_option( $key, '' ) ) );
		}

		/**
		 * The registered name.
		 *
		 * @return string
		 */
		public static function name() {
			return self::get( 'ec_option_business_name' );
		}

		/**
		 * The registered address ( lines ).
		 *
		 * @return string
		 */
		public static function address() {
			return self::get( 'ec_option_business_address' );
		}

		/**
		 * The tax number ( VAT number, ABN … ).
		 *
		 * @return string
		 */
		public static function tax_id() {
			return self::get( 'ec_option_business_tax_id' );
		}

		/**
		 * What the tax number is called where the store is ( ABN in Australia, GST number in New Zealand … ).
		 *
		 * @param string|null $country ISO code ( the store's when null ).
		 * @return string
		 */
		public static function auto_label( $country = null ) {
			if ( null === $country ) {
				$country = ( class_exists( 'wp_easycart_carriers' ) && method_exists( 'wp_easycart_carriers', 'store_country' ) ) ? (string) wp_easycart_carriers::store_country() : '';
			}
			switch ( strtoupper( (string) $country ) ) {
				case 'AU':
					return __( 'ABN', 'wp-easycart' );
				case 'NZ':
					return __( 'GST number', 'wp-easycart' );
				case 'CA':
					return __( 'GST/HST number', 'wp-easycart' );
				case 'IN':
					return __( 'GSTIN', 'wp-easycart' );
				case 'SG':
				case 'MY':
					return __( 'GST registration number', 'wp-easycart' );
				case 'US':
					return __( 'Tax ID', 'wp-easycart' );
			}
			return __( 'VAT number', 'wp-easycart' );
		}

		/**
		 * The tax number's label: the store's own, else the automatic one.
		 *
		 * @return string
		 */
		public static function tax_label() {
			$label = self::get( 'ec_option_business_tax_label' );
			return '' !== $label ? $label : self::auto_label();
		}

		/**
		 * Anything filled in.
		 *
		 * @return bool
		 */
		public static function filled() {
			return '' !== self::name() || '' !== self::address() || '' !== self::tax_id();
		}

		/**
		 * The details as lines: name, address lines, "VAT number: GB123456789".
		 *
		 * @return array
		 */
		public static function lines() {
			$lines = array();
			if ( '' !== self::name() ) {
				$lines[] = self::name();
			}
			foreach ( preg_split( '/\r\n|\r|\n/', self::address() ) as $line ) {
				if ( '' !== trim( $line ) ) {
					$lines[] = trim( $line );
				}
			}
			if ( '' !== self::tax_id() ) {
				$lines[] = self::tax_label() . ': ' . self::tax_id();
			}
			/**
			 * The business details printed on receipts and invoices.
			 *
			 * @since 6.0.5
			 * @param array $lines Lines.
			 */
			return (array) apply_filters( 'wp_easycart_business_details_lines', $lines );
		}

		/**
		 * The details as text, one line each.
		 *
		 * @return string
		 */
		public static function text() {
			return implode( "\n", self::lines() );
		}
	}

endif;
