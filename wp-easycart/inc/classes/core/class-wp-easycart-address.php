<?php
/**
 * WP EasyCart — addresses written the way each country writes them ( 6.0.5 ).
 *
 * Every place WP EasyCart prints an address ( emails and PDFs through wp_easycart_email_design::address(), the order confirmation,
 * My Account, the checkout's summaries, the admin's order screen, orders list and label window ) asks this class for the lines, so a
 * German address reads "10115 Berlin", a UK one puts the postcode on its own line, a Canadian one "Toronto ON M5V 3L9" and a US one
 * "Austin, TX 78701". The layouts follow the conventions Google's address data ( libaddressinput ) records for each country, in Latin
 * script, with the name, company and street first everywhere.
 *
 * Forms follow the same layouts ( form_layout(), form_config() → wpeasycart_ajax_object.address, ec-store.js
 * wpeasycart_address_place() ): the town, region and postcode fields sit in the country's order, and countries without postcodes
 * ( no_postcode() ) do not ask for one.
 *
 * Layout tokens: %C city, %S region ( state, province, county ), %Z postcode, %n a new line. Text between two tokens is kept only when
 * both have a value ( "%C, %S %Z" with no region gives "Austin 78701" ). Postcodes print in capitals. The region prints as entered,
 * except that a region code is written as its name where the country writes names ( region_text() ).
 *
 * @package WP_EasyCart
 * @since   6.0.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_address' ) ) :

	/**
	 * Address lines by country.
	 */
	class wp_easycart_address {

		/** The layout for a country not listed: "City, Region Postcode". */
		const DEFAULT_LAYOUT = '%C, %S %Z';

		/**
		 * Countries that write the region's code ( TX, ON, NSW, MI ), not its name.
		 *
		 * @var array
		 */
		const REGION_CODES = array( 'US', 'CA', 'AU', 'IT', 'BR' );

		/**
		 * Region code => name, by country, read once a request.
		 *
		 * @var array|null
		 */
		private static $regions = null;

		/**
		 * Country code => name, read once a request.
		 *
		 * @var array|null
		 */
		private static $countries = null;

		/**
		 * The city / region / postcode part of an address, by country.
		 *
		 * @return array iso2 => layout.
		 */
		public static function layouts() {
			$zip_city = '%Z %C';
			$layouts  = array(
				/* North America and Oceania */
				'US' => '%C, %S %Z',
				'PR' => '%C, %S %Z',
				'CA' => '%C %S %Z',
				'AU' => '%C %S %Z',
				'NZ' => '%C %Z',
				/* The British Isles: town, county, postcode, each on its own line */
				'GB' => '%C%n%S%n%Z',
				'IE' => '%C%n%S%n%Z',
				'IM' => '%C%n%Z',
				'JE' => '%C%n%Z',
				'GG' => '%C%n%Z',
				/* Most of Europe: postcode before the town */
				'AT' => $zip_city,
				'BE' => $zip_city,
				'BG' => $zip_city,
				'CH' => $zip_city,
				'CY' => $zip_city,
				'CZ' => $zip_city,
				'DE' => $zip_city,
				'DK' => $zip_city,
				'EE' => $zip_city,
				'FI' => $zip_city,
				'FR' => $zip_city,
				'GR' => $zip_city,
				'HR' => $zip_city,
				'IS' => $zip_city,
				'LI' => $zip_city,
				'LT' => $zip_city,
				'LU' => $zip_city,
				'MC' => $zip_city,
				'MT' => '%C %Z',
				'NL' => $zip_city,
				'NO' => $zip_city,
				'PL' => $zip_city,
				'PT' => $zip_city,
				'SE' => $zip_city,
				'SI' => $zip_city,
				'SK' => $zip_city,
				'RS' => $zip_city,
				'BA' => $zip_city,
				'ME' => $zip_city,
				'MK' => $zip_city,
				'AL' => $zip_city,
				'MD' => $zip_city,
				'TR' => '%Z %C%n%S',
				'ES' => '%Z %C%n%S',
				'IT' => '%Z %C %S',
				'RO' => '%Z %C%n%S',
				'HU' => '%C%n%Z',
				'LV' => '%C, %Z',
				'UA' => '%C%n%S%n%Z',
				'RU' => '%C%n%S%n%Z',
				'BY' => '%Z %C%n%S',
				'GE' => '%Z %C',
				'AM' => '%Z%n%C%n%S',
				/* Asia */
				'JP' => '%C, %S%n%Z',
				'CN' => '%C%n%S, %Z',
				'KR' => '%C%n%S%n%Z',
				'TW' => '%C, %S %Z',
				'HK' => '%C%n%S',
				'MO' => '%C',
				'SG' => '%Z',
				'MY' => '%Z %C%n%S',
				'TH' => '%C%n%S %Z',
				'ID' => '%C%n%S %Z',
				'PH' => '%C%n%Z %S',
				'VN' => '%C%n%S %Z',
				'IN' => '%C %Z%n%S',
				'PK' => '%C-%Z',
				'BD' => '%C - %Z',
				'LK' => '%C%n%Z',
				'NP' => '%C %Z',
				/* The Middle East and Africa */
				'IL' => '%C %Z',
				'AE' => '%C%n%S',
				'QA' => '%C',
				'KW' => '%Z %C',
				'BH' => '%C %Z',
				'OM' => '%Z%n%C',
				'SA' => '%C %Z',
				'JO' => '%C %Z',
				'EG' => '%C%n%S%n%Z',
				'MA' => $zip_city,
				'DZ' => $zip_city,
				'TN' => $zip_city,
				'ZA' => '%C%n%Z',
				'NG' => '%C %Z%n%S',
				'KE' => '%C%n%Z',
				'GH' => '%C',
				/* Latin America */
				'MX' => '%Z %C, %S',
				'BR' => '%C-%S%n%Z',
				'AR' => '%Z %C%n%S',
				'CL' => '%Z %C%n%S',
				'CO' => '%C, %S, %Z',
				'PE' => '%C %Z%n%S',
				'UY' => '%Z %C %S',
				'EC' => '%Z%n%C',
				'VE' => '%C %Z, %S',
				'CR' => '%S, %C%n%Z',
				'DO' => '%Z %C',
				'GT' => '%Z- %C',
			);
			/**
			 * The city / region / postcode layout by country ( tokens %C, %S, %Z, %n ).
			 *
			 * @since 6.0.5
			 * @param array $layouts iso2 => layout.
			 */
			return (array) apply_filters( 'wp_easycart_address_layouts', $layouts );
		}

		/**
		 * A country's layout.
		 *
		 * @param string $iso2 Country.
		 * @return string
		 */
		public static function layout( $iso2 ) {
			$layouts = self::layouts();
			$iso2    = strtoupper( trim( (string) $iso2 ) );
			return isset( $layouts[ $iso2 ] ) ? (string) $layouts[ $iso2 ] : self::DEFAULT_LAYOUT;
		}

		/**
		 * Countries without postcodes: forms leave the postcode out and do not ask for it.
		 *
		 * @since 6.0.5
		 * @return array ISO codes.
		 */
		public static function no_postcode() {
			$none = array( 'AE', 'AG', 'AO', 'AW', 'BF', 'BI', 'BJ', 'BO', 'BS', 'BW', 'BZ', 'CD', 'CF', 'CG', 'CI', 'CK', 'CM', 'DJ', 'DM', 'ER', 'FJ', 'GA', 'GD', 'GH', 'GM', 'GQ', 'GY', 'HK', 'JM', 'KI', 'KM', 'KN', 'KP', 'LC', 'ML', 'MO', 'MR', 'MS', 'MW', 'NR', 'NU', 'QA', 'RW', 'SB', 'SC', 'SL', 'SO', 'SR', 'SS', 'ST', 'SY', 'TD', 'TF', 'TG', 'TK', 'TL', 'TO', 'TT', 'TV', 'UG', 'VU', 'YE', 'ZW' );
			/**
			 * Countries without postcodes ( their forms leave the postcode out ).
			 *
			 * @since 6.0.5
			 * @param array $none ISO codes.
			 */
			return array_values( array_unique( array_map( 'strtoupper', (array) apply_filters( 'wp_easycart_address_no_postcode', $none ) ) ) );
		}

		/**
		 * How a form lays out the town, region and postcode for a country, from its layout:
		 * csz town, then region and postcode ( the US ); zc postcode before the town, region under them ( most of Europe );
		 * cz town and postcode, region under them ( New Zealand, India ); stack town, region, postcode under each other ( the UK );
		 * cs town and region side by side, the postcode under them ( Japan, Brazil ) or none ( Hong Kong, no_postcode() ).
		 *
		 * @since 6.0.5
		 * @param string $iso2 Country.
		 * @return string csz | zc | cz | stack | cs
		 */
		public static function form_layout( $iso2 ) {
			$iso2 = strtoupper( trim( (string) $iso2 ) );
			if ( in_array( $iso2, self::no_postcode(), true ) ) {
				return 'cs';
			}
			$lines = explode( '%n', self::layout( $iso2 ) );
			$city  = '';
			foreach ( $lines as $line ) {
				if ( false !== strpos( $line, '%C' ) ) {
					$city = $line;
					break;
				}
			}
			$c = strpos( $city, '%C' );
			$z = strpos( $city, '%Z' );
			$s = strpos( $city, '%S' );
			if ( false !== $z && false !== $c && $z < $c ) {
				return 'zc';
			}
			if ( false !== $z && false !== $c ) {
				return ( false !== $s && $s > $c && $s < $z ) ? 'csz' : 'cz';
			}
			if ( false !== $s && false !== $c ) {
				return 'cs';
			}
			foreach ( $lines as $line ) {
				if ( false !== strpos( $line, '%S' ) && false !== strpos( $line, '%Z' ) ) {
					return 'csz';
				}
			}
			return ( '' === $city ) ? 'cz' : 'stack';
		}

		/**
		 * The forms' settings: each listed country's layout ( unlisted ones are csz ) and the countries without postcodes.
		 *
		 * @since 6.0.5
		 * @return array layouts ( iso2 => key, csz left out ), nozip.
		 */
		public static function form_config() {
			$layouts = array();
			foreach ( array_merge( array_keys( self::layouts() ), self::no_postcode() ) as $iso2 ) {
				$key = self::form_layout( $iso2 );
				if ( 'csz' !== $key ) {
					$layouts[ $iso2 ] = $key;
				}
			}
			return array(
				'layouts' => $layouts,
				'nozip'   => self::no_postcode(),
			);
		}

		/**
		 * The parts of an address from an order, account or session row.
		 *
		 * @param object|array $source Row.
		 * @param string       $side   Field prefix: billing, shipping, or '' for none ( an ec_address row ).
		 * @param array        $map    Part => property overrides.
		 * @return array name, first_name, last_name, company, line1, line2, city, state, zip, country ( code ), country_name, phone.
		 */
		public static function parts( $source, $side = '', $map = array() ) {
			$source = is_array( $source ) ? (object) $source : $source;
			$get    = function ( $part ) use ( $source, $side, $map ) {
				$key = isset( $map[ $part ] ) ? $map[ $part ] : ( '' !== $side ? $side . '_' . $part : $part );
				return ( is_object( $source ) && isset( $source->{$key} ) && is_scalar( $source->{$key} ) ) ? trim( stripslashes( (string) $source->{$key} ) ) : '';
			};
			$country = strtoupper( $get( 'country' ) );
			$name    = $get( 'country_name' );
			if ( '' !== $country && ( '' === $name || strtoupper( $name ) === $country ) ) {
				$name = self::country_name( $country );
			}
			return array(
				'first_name'   => $get( 'first_name' ),
				'last_name'    => $get( 'last_name' ),
				'name'         => trim( $get( 'first_name' ) . ' ' . $get( 'last_name' ) ),
				'company'      => $get( 'company_name' ),
				'line1'        => $get( 'address_line_1' ),
				'line2'        => $get( 'address_line_2' ),
				'city'         => $get( 'city' ),
				'state'        => $get( 'state' ),
				'zip'          => $get( 'zip' ),
				'country'      => ( '0' === $country ) ? '' : $country,
				'country_name' => ( '0' === $name ) ? '' : $name,
				'phone'        => $get( 'phone' ),
			);
		}

		/**
		 * The lines of an address.
		 *
		 * @param array $parts From parts().
		 * @param array $args  name, company, street ( true ), country: always | foreign ( only when not the store's country ) | never
		 *                     ( always ), all ( false: true adds a region the country's layout leaves out, for the admin ), phone ( false ).
		 * @return array Lines ( plain text ).
		 */
		public static function render( $parts, $args = array() ) {
			$args  = wp_parse_args(
				$args,
				array(
					'name'    => true,
					'company' => true,
					'street'  => true,
					'country' => 'always',
					'all'     => false,
					'phone'   => false,
				)
			);
			$lines = array();
			if ( $args['name'] ) {
				$lines[] = $parts['name'];
			}
			if ( $args['company'] ) {
				$lines[] = $parts['company'];
			}
			if ( $args['street'] ) {
				$lines[] = $parts['line1'];
				$lines[] = $parts['line2'];
			}
			foreach ( self::place( $parts, $args['all'] ) as $line ) {
				$lines[] = $line;
			}
			$show_country = ( 'always' === $args['country'] ) || ( 'foreign' === $args['country'] && '' !== $parts['country'] && $parts['country'] !== self::store_country() );
			if ( $show_country ) {
				$lines[] = '' !== $parts['country_name'] ? $parts['country_name'] : $parts['country'];
			}
			if ( $args['phone'] ) {
				$lines[] = $parts['phone'];
			}
			$lines = array_values( array_filter( array_map( 'trim', $lines ), 'strlen' ) );
			/**
			 * The lines of an address.
			 *
			 * @since 6.0.5
			 * @param array $lines Lines.
			 * @param array $parts From parts().
			 * @param array $args  render()'s arguments.
			 */
			return (array) apply_filters( 'wp_easycart_address_lines', $lines, $parts, $args );
		}

		/**
		 * The city / region / postcode lines in the country's order.
		 *
		 * @param array $parts From parts().
		 * @param bool  $all   Add a region the layout leaves out ( on its own line ).
		 * @return array
		 */
		public static function place( $parts, $all = false ) {
			$layout = self::layout( $parts['country'] );
			$values = array(
				'C' => $parts['city'],
				'S' => self::region_text( $parts['country'], $parts['state'] ),
				'Z' => function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $parts['zip'] ) : strtoupper( $parts['zip'] ),
			);
			/* 6.0.5: a region that only repeats the town ( London, London; Singapore, Singapore ) is left out. */
			if ( '' !== $values['S'] && 0 === strcasecmp( trim( $values['S'] ), trim( $values['C'] ) ) ) {
				$values['S'] = '';
			}
			$lines  = array();
			foreach ( explode( '%n', $layout ) as $line ) {
				$lines[] = self::fill( $line, $values );
			}
			if ( $all && '' !== $values['S'] && false === strpos( $layout, '%S' ) ) {
				$lines[] = $values['S'];
			}
			return array_values( array_filter( $lines, 'strlen' ) );
		}

		/**
		 * One layout line with its values: the text between two tokens is kept only when both have a value.
		 *
		 * @param string $line   Layout line.
		 * @param array  $values Token letter => value.
		 * @return string
		 */
		private static function fill( $line, $values ) {
			$pieces = preg_split( '/(%[CSZ])/', $line, -1, PREG_SPLIT_DELIM_CAPTURE );
			$out    = '';
			$joiner = '';
			$have   = false;
			foreach ( $pieces as $piece ) {
				if ( 1 === preg_match( '/^%([CSZ])$/', $piece, $m ) ) {
					$value = isset( $values[ $m[1] ] ) ? trim( (string) $values[ $m[1] ] ) : '';
					if ( '' === $value ) {
						continue;
					}
					$out   .= ( $have ? $joiner : '' ) . $value;
					$have   = true;
					$joiner = '';
				} else {
					$joiner = $piece;
				}
			}
			return trim( $out );
		}

		/**
		 * A region as the country writes it: its code where the country writes codes ( REGION_CODES ), else its name when the value
		 * is one of the country's region codes; anything else as entered.
		 *
		 * @param string $iso2  Country.
		 * @param string $value Region as stored.
		 * @return string
		 */
		public static function region_text( $iso2, $value ) {
			$value = trim( (string) $value );
			$iso2  = strtoupper( (string) $iso2 );
			if ( '' === $value || in_array( $iso2, self::REGION_CODES, true ) ) {
				return $value;
			}
			$regions = self::regions();
			$key     = strtolower( $value );
			return isset( $regions[ $iso2 ][ $key ] ) ? $regions[ $iso2 ][ $key ] : $value;
		}

		/**
		 * Region code => name by country ( lower-case codes ), read once a request.
		 *
		 * @return array
		 */
		private static function regions() {
			global $wpdb;
			if ( null === self::$regions ) {
				self::$regions = array();
				if ( isset( $wpdb ) ) {
					$rows = wp_cache_get( 'wpeasycart-address-regions', 'wpeasycart-address' );
					if ( ! is_array( $rows ) ) {
						$rows = (array) $wpdb->get_results( 'SELECT c.iso2_cnt, s.code_sta, s.name_sta FROM ec_state s INNER JOIN ec_country c ON c.id_cnt = s.idcnt_sta' );
						wp_cache_set( 'wpeasycart-address-regions', $rows, 'wpeasycart-address', HOUR_IN_SECONDS );
					}
					foreach ( $rows as $row ) {
						$row = (object) $row;
						if ( '' !== (string) $row->code_sta && (string) $row->code_sta !== (string) $row->name_sta ) {
							self::$regions[ strtoupper( (string) $row->iso2_cnt ) ][ strtolower( (string) $row->code_sta ) ] = (string) $row->name_sta;
						}
					}
				}
			}
			return self::$regions;
		}

		/**
		 * A country's name from its code ( the store's list ), or the code.
		 *
		 * @param string $iso2 Country.
		 * @return string
		 */
		public static function country_name( $iso2 ) {
			global $wpdb;
			$iso2 = strtoupper( trim( (string) $iso2 ) );
			if ( null === self::$countries ) {
				self::$countries = array();
				if ( isset( $wpdb ) ) {
					foreach ( (array) $wpdb->get_results( 'SELECT iso2_cnt, name_cnt FROM ec_country' ) as $row ) {
						self::$countries[ strtoupper( (string) $row->iso2_cnt ) ] = (string) $row->name_cnt;
					}
				}
			}
			return isset( self::$countries[ $iso2 ] ) ? self::$countries[ $iso2 ] : $iso2;
		}

		/**
		 * The store's country ( for country: foreign ).
		 *
		 * @return string
		 */
		private static function store_country() {
			return class_exists( 'wp_easycart_carriers' ) ? strtoupper( (string) wp_easycart_carriers::store_country() ) : '';
		}

		/**
		 * The lines of a row's address.
		 *
		 * @param object|array $source Row.
		 * @param string       $side   billing | shipping | ''.
		 * @param array        $args   render()'s arguments.
		 * @return array
		 */
		public static function lines( $source, $side = '', $args = array() ) {
			return self::render( self::parts( $source, $side ), $args );
		}

		/**
		 * The city / region / postcode lines of a row's address.
		 *
		 * @param object|array $source Row.
		 * @param string       $side   billing | shipping | ''.
		 * @param bool         $all    Add a region the layout leaves out.
		 * @return array
		 */
		public static function place_lines( $source, $side = '', $all = false ) {
			return self::place( self::parts( $source, $side ), $all );
		}

		/**
		 * The city / region / postcode of a row's address on one line ( "10115 Berlin", "London, SW1A 1AA" ).
		 *
		 * @param object|array $source Row.
		 * @param string       $side   billing | shipping | ''.
		 * @param bool         $all    Add a region the layout leaves out.
		 * @return string
		 */
		public static function place_line( $source, $side = '', $all = false ) {
			return implode( ', ', self::place_lines( $source, $side, $all ) );
		}

		/**
		 * A row's address on one line, its lines separated by commas.
		 *
		 * @param object|array $source Row.
		 * @param string       $side   billing | shipping | ''.
		 * @param array        $args   render()'s arguments.
		 * @return string
		 */
		public static function one_line( $source, $side = '', $args = array() ) {
			return implode( ', ', self::lines( $source, $side, $args ) );
		}

		/**
		 * A row's address as HTML lines ( escaped, joined with <br> ).
		 *
		 * @param object|array $source Row.
		 * @param string       $side   billing | shipping | ''.
		 * @param array        $args   render()'s arguments.
		 * @return string
		 */
		public static function html( $source, $side = '', $args = array() ) {
			return implode( '<br />', array_map( 'esc_html', self::lines( $source, $side, $args ) ) );
		}

		/**
		 * Forget what was read this request ( after a region or country changes ).
		 */
		public static function forget() {
			self::$regions   = null;
			self::$countries = null;
			wp_cache_delete( 'wpeasycart-address-regions', 'wpeasycart-address' );
		}
	}

endif;
