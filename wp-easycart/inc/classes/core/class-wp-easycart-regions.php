<?php
/**
 * WP EasyCart — address regions and labels by country ( 6.0.5 ).
 *
 * Whether a country's region ( state, county, province ) must be chosen at checkout and in My Account, and what the region and
 * postcode fields are called there. Before 6.0.5 every country with a region list had to have one chosen: a UK shopper had to
 * pick a county ( from a list with counties abolished in 1996 ), though Royal Mail addresses need none; German and New Zealand
 * addresses carry no region either.
 *
 * ec_country.region_required ( EC_UPGRADE_DB 126 ): NULL = WP EasyCart decides ( optional for optional_by_default(), else
 * required ), 1 = required, 0 = optional; set per country in Settings › Countries & Regions. Read from the storefront's own
 * country list ( $GLOBALS['ec_countries'], ship-to countries ), so the storefront asks the database nothing more.
 *
 * Labels: a country in label_keys() shows its own words for the region and postcode ( County and Postcode in the UK, Province
 * and Postal code in Canada … ), phrases in the language section address_labels the store can edit; other countries keep the
 * store's State and Zip Code phrases. The storefront script switches them as the country changes ( ec-store.js
 * wpeasycart_address_fields(), config wpeasycart_ajax_object.address from config() ).
 *
 * @package WP_EasyCart
 * @since   6.0.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_regions' ) ) :

	/**
	 * Region rules and address labels by country.
	 */
	class wp_easycart_regions {

		/** Database version with ec_country.region_required. */
		const DB = 126;

		/**
		 * ISO code => null | 0 | 1, this request.
		 *
		 * @var array|null
		 */
		private static $rows = null;

		/**
		 * The column exists.
		 *
		 * @return bool
		 */
		public static function ready() {
			return (int) get_option( 'ec_option_db_new_version' ) >= self::DB;
		}

		/**
		 * Countries whose addresses carry no region, so choosing one is optional unless the store says otherwise: the UK
		 * ( Royal Mail ), Germany and New Zealand.
		 *
		 * @return array ISO codes.
		 */
		public static function optional_by_default() {
			/**
			 * Countries where choosing a region is optional while the store has not decided ( Settings › Countries & Regions ).
			 *
			 * @since 6.0.5
			 * @param array $countries ISO 3166-1 alpha-2 codes.
			 */
			return array_values( array_map( 'strtoupper', (array) apply_filters( 'wp_easycart_region_optional_countries', array( 'GB', 'DE', 'NZ' ) ) ) );
		}

		/**
		 * What each country's row says ( ship-to countries ).
		 *
		 * @return array ISO code => null | 0 | 1.
		 */
		private static function rows() {
			if ( null !== self::$rows ) {
				return self::$rows;
			}
			self::$rows = array();
			if ( ! self::ready() ) {
				return self::$rows;
			}
			$countries = ( isset( $GLOBALS['ec_countries'] ) && is_object( $GLOBALS['ec_countries'] ) && is_array( $GLOBALS['ec_countries']->countries ) ) ? $GLOBALS['ec_countries']->countries : array();
			foreach ( $countries as $row ) {
				if ( isset( $row->iso2_cnt ) ) {
					self::$rows[ strtoupper( (string) $row->iso2_cnt ) ] = ( isset( $row->region_required ) && null !== $row->region_required && '' !== $row->region_required ) ? (int) $row->region_required : null;
				}
			}
			return self::$rows;
		}

		/**
		 * Forget this request's rows ( after a save ).
		 */
		public static function forget() {
			self::$rows = null;
		}

		/**
		 * A stored choice read straight from the table ( the admin, any country ).
		 *
		 * @param string $iso2 Country code.
		 * @return int|null
		 */
		public static function stored( $iso2 ) {
			global $wpdb;
			if ( ! self::ready() ) {
				return null;
			}
			$value = $wpdb->get_var( $wpdb->prepare( 'SELECT region_required FROM ec_country WHERE iso2_cnt = %s LIMIT 1', strtoupper( (string) $iso2 ) ) );
			return ( null === $value ) ? null : (int) $value;
		}

		/**
		 * Must a shopper choose a region for this country ( when it has a region list )?
		 *
		 * @param string   $iso2   Country code.
		 * @param int|null $stored The row's value when known ( null = WP EasyCart decides; omitted = read it ).
		 * @return bool
		 */
		public static function required( $iso2, $stored = false ) {
			$iso2 = strtoupper( trim( (string) $iso2 ) );
			if ( false === $stored ) {
				$rows   = self::rows();
				$stored = array_key_exists( $iso2, $rows ) ? $rows[ $iso2 ] : null;
			}
			$required = ( null === $stored ) ? ! in_array( $iso2, self::optional_by_default(), true ) : ( 1 === (int) $stored );
			/**
			 * Whether a country's region must be chosen at checkout and in My Account.
			 *
			 * @since 6.0.5
			 * @param bool   $required Required.
			 * @param string $iso2     Country code.
			 */
			return (bool) apply_filters( 'wp_easycart_region_required', $required, $iso2 );
		}

		/**
		 * Ship-to countries whose region is optional.
		 *
		 * @return array ISO codes.
		 */
		public static function optional_countries() {
			$codes = array_unique( array_merge( array_keys( self::rows() ), self::optional_by_default() ) );
			$out   = array();
			foreach ( $codes as $code ) {
				if ( ! self::required( $code ) ) {
					$out[] = $code;
				}
			}
			sort( $out );
			return $out;
		}

		/**
		 * Phrases ( language section address_labels ) per country for the region and the postcode. Countries not listed keep the
		 * store's own State and Zip Code phrases ( the US among them ).
		 *
		 * @return array ISO code => array( region key, postal key ).
		 */
		public static function label_keys() {
			$keys = array(
				'GB' => array( 'region_county', 'postal_postcode' ),
				'IE' => array( 'region_county', 'postal_eircode' ),
				'CA' => array( 'region_province', 'postal_postal_code' ),
				'AU' => array( 'region_state_territory', 'postal_postcode' ),
				'NZ' => array( 'region_region', 'postal_postcode' ),
				'DE' => array( 'region_state', 'postal_postcode' ),
				'ES' => array( 'region_province', 'postal_postal_code' ),
				'AR' => array( 'region_province', 'postal_postal_code' ),
				'CN' => array( 'region_province', 'postal_postal_code' ),
				'JP' => array( 'region_prefecture', 'postal_postal_code' ),
				'MX' => array( 'region_state', 'postal_postal_code' ),
				'BR' => array( 'region_state', 'postal_cep' ),
				'IN' => array( 'region_state', 'postal_pin' ),
			);
			/**
			 * The address_labels phrases per country ( region key, postal key ).
			 *
			 * @since 6.0.5
			 * @param array $keys ISO code => array( region key, postal key ).
			 */
			return (array) apply_filters( 'wp_easycart_address_label_keys', $keys );
		}

		/**
		 * The words for each listed country, in the storefront's language.
		 *
		 * @return array ISO code => array( region, postal ).
		 */
		public static function labels() {
			$out = array();
			if ( ! function_exists( 'wp_easycart_language' ) ) {
				return $out;
			}
			foreach ( self::label_keys() as $code => $keys ) {
				$region = wp_strip_all_tags( (string) wp_easycart_language()->get_text( 'address_labels', (string) $keys[0] ) );
				$postal = wp_strip_all_tags( (string) wp_easycart_language()->get_text( 'address_labels', (string) $keys[1] ) );
				if ( '' !== trim( $region ) || '' !== trim( $postal ) ) {
					$out[ strtoupper( $code ) ] = array(
						'region' => html_entity_decode( $region, ENT_QUOTES, 'UTF-8' ),
						'postal' => html_entity_decode( $postal, ENT_QUOTES, 'UTF-8' ),
					);
				}
			}
			return $out;
		}

		/**
		 * The storefront script's settings ( wpeasycart_ajax_object.address ).
		 *
		 * @return array optional ( ISO codes ), labels ( ISO code => region, postal ).
		 */
		public static function config() {
			$config = array(
				'optional' => self::optional_countries(),
				'labels'   => self::labels(),
			);
			/* 6.0.5: the town, region and postcode in the country's order, no postcode where a country has none ( wp_easycart_address ). */
			if ( class_exists( 'wp_easycart_address' ) && method_exists( 'wp_easycart_address', 'form_config' ) ) {
				$config = array_merge( $config, wp_easycart_address::form_config() );
			}
			return $config;
		}
	}

endif;
