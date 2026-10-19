<?php
/**
 * WP EasyCart: recommended tax settings by store country ( 6.0.5, Matt: "most people just want to get the latest rates and expected
 * settings without researching it themselves" ). Plan and decisions D1–D10: https://claude.ai/artifact/G5YFjc2wPHCU7aBvgkwQAh
 *
 * One list of what a store in each supported country is expected to charge, with the date it was checked: the setup wizard
 * ( location step ), Settings › Taxes ( the card at the top ) and `wp easycart tax` read it. changes() says what applying it would
 * change, apply() writes it through the same storage Settings › Taxes uses ( options, the VAT row, ec_country.vat_rate_cnt, rates by
 * state, the Canada grid ) after keeping a snapshot that undo() puts back. Product tax classes ( reduced rates ), automated tax
 * services and duty are never touched.
 *
 * @package WP_EasyCart
 * @since   6.0.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_tax_setup' ) ) :

	/**
	 * Recommended tax settings by store country.
	 */
	class wp_easycart_tax_setup {

		/** The day the rates and rules were checked. */
		const CHECKED = '2026-10-14';

		/** Raised when the data or the rules change: a card someone kept shows again. */
		const VERSION = 1;

		/** The store's choices ( state, prices include tax, EU mode, Canadian PST ), country => choices. */
		const CHOICES = 'wp_easycart_tax_setup_choices';

		/** The signature of the recommendation "Keep my settings" was pressed for. */
		const KEPT = 'wp_easycart_tax_setup_kept';

		/** What apply() changed, for undo(). */
		const SNAPSHOT = 'wp_easycart_tax_setup_snapshot';

		/**
		 * Options apply() may write ( the snapshot keeps them ).
		 *
		 * @return array
		 */
		public static function option_keys() {
			return array(
				'ec_option_use_vat_tax',
				'ec_vat_by_country',
				'ec_default_vat_rate',
				'ec_vat_pricing_method',
				'ec_option_no_vat_on_shipping',
				'ec_option_vat_custom_rate',
				'ec_option_vat_name',
				'ec_option_eu_vat_mode',
				'ec_option_use_global_tax',
				'ec_global_tax_rate',
				'ec_option_enable_easy_canada_tax',
				'ec_option_canada_tax_options',
				'ec_option_collect_tax_on_shipping',
			);
		}

		/* ------------------------------------------------------------------ */
		/* Data ( checked 14 October 2026 )                                     */
		/* ------------------------------------------------------------------ */

		/**
		 * US states: statewide rate ( 0 = no statewide sales tax ), whether shipping charged with taxable goods is taxed ( 1 | 0 ), and
		 * whether that depends on how delivery is sold ( check: the usual online store case is given ). Tax Foundation midyear 2026 and
		 * the Sales Tax Institute ( 1 October 2026 ); shipping: Avalara's state guide ( 1 June 2026 ). CA, UT and VA include their
		 * statewide local tax.
		 *
		 * @return array code => array( rate, ship, check )
		 */
		public static function us_states() {
			$states = array(
				'AL' => array( 4, 0, 0 ),
				'AK' => array( 0, 0, 0 ),
				'AZ' => array( 5.6, 0, 0 ),
				'AR' => array( 6.5, 1, 0 ),
				'CA' => array( 7.25, 0, 1 ),
				'CO' => array( 2.9, 0, 0 ),
				'CT' => array( 6.35, 1, 0 ),
				'DE' => array( 0, 0, 0 ),
				'DC' => array( 6, 0, 0 ),
				'FL' => array( 6, 0, 0 ),
				'GA' => array( 4, 1, 0 ),
				'HI' => array( 4, 1, 0 ),
				'ID' => array( 6, 0, 0 ),
				'IL' => array( 6.25, 1, 1 ),
				'IN' => array( 7, 1, 0 ),
				'IA' => array( 6, 0, 0 ),
				'KS' => array( 6.5, 0, 0 ),
				'KY' => array( 6, 1, 0 ),
				'LA' => array( 5, 1, 0 ),
				'ME' => array( 5.5, 0, 0 ),
				'MD' => array( 6, 0, 0 ),
				'MA' => array( 6.25, 0, 0 ),
				'MI' => array( 6, 0, 0 ),
				'MN' => array( 6.875, 1, 0 ),
				'MS' => array( 7, 1, 0 ),
				'MO' => array( 4.225, 0, 1 ),
				'MT' => array( 0, 0, 0 ),
				'NE' => array( 5.5, 1, 0 ),
				'NV' => array( 6.85, 0, 1 ),
				'NH' => array( 0, 0, 0 ),
				'NJ' => array( 6.625, 1, 0 ),
				'NM' => array( 4.875, 1, 0 ),
				'NY' => array( 4, 1, 0 ),
				'NC' => array( 4.75, 1, 0 ),
				'ND' => array( 5, 1, 0 ),
				'OH' => array( 5.75, 1, 0 ),
				'OK' => array( 4.5, 0, 0 ),
				'OR' => array( 0, 0, 0 ),
				'PA' => array( 6, 1, 0 ),
				'RI' => array( 7, 1, 0 ),
				'SC' => array( 6, 1, 1 ),
				'SD' => array( 4.2, 1, 0 ),
				'TN' => array( 7, 1, 0 ),
				'TX' => array( 6.25, 1, 0 ),
				'UT' => array( 6.1, 0, 0 ),
				'VT' => array( 6, 1, 0 ),
				'VA' => array( 5.3, 0, 0 ),
				'WA' => array( 6.5, 1, 0 ),
				'WV' => array( 6, 1, 0 ),
				'WI' => array( 5, 1, 0 ),
				'WY' => array( 4, 0, 0 ),
			);
			/**
			 * US state rates and shipping rules the recommendation uses.
			 *
			 * @since 6.0.5
			 * @param array $states code => array( rate, ship 1|0, check 1|0 ).
			 */
			return (array) apply_filters( 'wp_easycart_tax_setup_us_states', $states );
		}

		/**
		 * States with no local sales tax on top of the state rate ( elsewhere cities and counties add their own ).
		 *
		 * @return array
		 */
		public static function no_local_tax_states() {
			return array( 'CT', 'IN', 'KY', 'ME', 'MD', 'MA', 'MI', 'RI', 'DC', 'DE', 'MT', 'NH', 'OR' );
		}

		/**
		 * Canada: province code => the grid's key ( wp_easycart_admin_tax_v2::provinces() ) and GST / PST / HST as fractions. CRA ( Nova
		 * Scotia HST 14% from 1 April 2025 ), BC PST 7%, SK PST 6%, MB RST 7%, QC QST 9.975%.
		 *
		 * @return array
		 */
		public static function canada() {
			return array(
				'AB' => array( 'key' => 'alberta', 'gst' => .05, 'pst' => 0, 'hst' => 0 ),
				'BC' => array( 'key' => 'british_columbia', 'gst' => .05, 'pst' => .07, 'hst' => 0 ),
				'MB' => array( 'key' => 'manitoba', 'gst' => .05, 'pst' => .07, 'hst' => 0 ),
				'NB' => array( 'key' => 'new_brunswick', 'gst' => 0, 'pst' => 0, 'hst' => .15 ),
				'NL' => array( 'key' => 'newfoundland', 'gst' => 0, 'pst' => 0, 'hst' => .15 ),
				'NT' => array( 'key' => 'northwest_territories', 'gst' => .05, 'pst' => 0, 'hst' => 0 ),
				'NS' => array( 'key' => 'nova_scotia', 'gst' => 0, 'pst' => 0, 'hst' => .14 ),
				'NU' => array( 'key' => 'nunavut', 'gst' => .05, 'pst' => 0, 'hst' => 0 ),
				'ON' => array( 'key' => 'ontario', 'gst' => 0, 'pst' => 0, 'hst' => .13 ),
				'PE' => array( 'key' => 'prince_edward_island', 'gst' => 0, 'pst' => 0, 'hst' => .15 ),
				'QC' => array( 'key' => 'quebec', 'gst' => .05, 'pst' => .09975, 'hst' => 0 ),
				'SK' => array( 'key' => 'saskatchewan', 'gst' => .05, 'pst' => .06, 'hst' => 0 ),
				'YT' => array( 'key' => 'yukon', 'gst' => .05, 'pst' => 0, 'hst' => 0 ),
			);
		}

		/**
		 * VAT / GST countries: the standard rate, the countries in the same VAT area ( they pay the store's rate too ) and the
		 * registration threshold to mention. EU rates are wp_easycart_vat::eu() ( checked July and October 2026 ).
		 *
		 * @return array cc => array( rate, area, threshold )
		 */
		public static function vat_countries() {
			$countries = array(
				'GB' => array( 'rate' => 20, 'area' => array( 'IM' ), 'threshold' => '£90,000' ),
				'AU' => array( 'rate' => 10, 'area' => array(), 'threshold' => 'A$75,000' ),
				'NZ' => array( 'rate' => 15, 'area' => array(), 'threshold' => 'NZ$60,000' ),
				'NO' => array( 'rate' => 25, 'area' => array(), 'threshold' => '' ),
				'IS' => array( 'rate' => 24, 'area' => array(), 'threshold' => '' ),
				'CH' => array( 'rate' => 8.1, 'area' => array( 'LI' ), 'threshold' => '' ),
				'LI' => array( 'rate' => 8.1, 'area' => array( 'CH' ), 'threshold' => '' ),
				'SG' => array( 'rate' => 9, 'area' => array(), 'threshold' => '' ),
				'JP' => array( 'rate' => 10, 'area' => array(), 'threshold' => '' ),
				'ZA' => array( 'rate' => 15, 'area' => array(), 'threshold' => '' ),
				'MX' => array( 'rate' => 16, 'area' => array(), 'threshold' => '' ),
				/* The setup wizard's starter rates before 6.0.5, kept so these stores are still set up ( not checked again in October 2026 ). */
				'TH' => array( 'rate' => 7, 'area' => array(), 'threshold' => '' ),
				'TR' => array( 'rate' => 20, 'area' => array(), 'threshold' => '' ),
				'BD' => array( 'rate' => 15, 'area' => array(), 'threshold' => '' ),
				'NP' => array( 'rate' => 13, 'area' => array(), 'threshold' => '' ),
			);
			if ( class_exists( 'wp_easycart_vat' ) ) {
				foreach ( wp_easycart_vat::eu() as $cc => $rate ) {
					$countries[ $cc ] = array( 'rate' => (float) $rate, 'area' => ( 'FR' === $cc ) ? array( 'MC' ) : array(), 'threshold' => '' );
				}
			}
			/**
			 * VAT / GST countries the recommendation covers.
			 *
			 * @since 6.0.5
			 * @param array $countries cc => array( rate, area, threshold ).
			 */
			return (array) apply_filters( 'wp_easycart_tax_setup_vat_countries', $countries );
		}

		/**
		 * Where each country's facts come from ( shown under the card ).
		 *
		 * @param string $cc Country.
		 * @return array label => URL
		 */
		public static function sources( $cc ) {
			$cc = strtoupper( (string) $cc );
			if ( 'US' === $cc ) {
				return array(
					'Tax Foundation 2026' => 'https://taxfoundation.org/data/all/state/2026-sales-tax-rates-midyear/',
					'Avalara: shipping by state' => 'https://www.avalara.com/us/en/blog/how-to-handle-sales-tax-on-shipping-a-state-by-state-guide.html',
				);
			}
			$list = array(
				'CA' => array( 'CRA' => 'https://www.canada.ca/en/revenue-agency/services/tax/businesses/topics/gst-hst-businesses/charge-collect-which-rate/calculator.html' ),
				'AU' => array( 'ATO' => 'https://www.ato.gov.au/businesses-and-organisations/international-tax-for-business/australians-doing-business-overseas/exports-and-gst' ),
				'NZ' => array( 'IRD' => 'https://www.ird.govt.nz/gst/registering-for-gst' ),
				'GB' => array( 'HMRC: delivery' => 'https://www.gov.uk/guidance/vat-on-postage-delivery-and-direct-marketing-notice-70024', 'HMRC: exports' => 'https://www.gov.uk/guidance/vat-on-goods-exported-from-the-uk-notice-703' ),
				'SG' => array( 'IRAS' => 'https://www.iras.gov.sg/news-events/newsroom/gst-registered-businesses-must-display-gst-inclusive-prices2' ),
			);
			if ( isset( $list[ $cc ] ) ) {
				return $list[ $cc ];
			}
			if ( class_exists( 'wp_easycart_vat' ) && wp_easycart_vat::in_eu( $cc ) ) {
				return array( 'European Commission: VAT e-commerce' => 'https://vat-one-stop-shop.ec.europa.eu/system/files/2021-07/vatecommerceexplanatory_notes_28102020_en.pdf' );
			}
			return array();
		}

		/* ------------------------------------------------------------------ */
		/* The store                                                            */
		/* ------------------------------------------------------------------ */

		/**
		 * The tax system a country uses: us | canada | vat, or '' ( no recommendation ).
		 *
		 * @param string $cc Country.
		 * @return string
		 */
		public static function system( $cc ) {
			$cc = strtoupper( (string) $cc );
			if ( 'US' === $cc ) {
				return 'us';
			}
			if ( 'CA' === $cc ) {
				return 'canada';
			}
			$vat = self::vat_countries();
			return isset( $vat[ $cc ] ) ? 'vat' : '';
		}

		/**
		 * Whether WP EasyCart recommends settings for this country.
		 *
		 * @param string $cc Country.
		 * @return bool
		 */
		public static function supported( $cc ) {
			return '' !== self::system( $cc );
		}

		/**
		 * The store's country.
		 *
		 * @return string
		 */
		public static function store_country() {
			return ( class_exists( 'wp_easycart_carriers' ) && method_exists( 'wp_easycart_carriers', 'store_country' ) ) ? strtoupper( (string) wp_easycart_carriers::store_country() ) : '';
		}

		/**
		 * What a country's own tax is called in the admin ( 6.0.5, Matt: a Canadian store read "Sales tax" ): Sales tax in the US,
		 * GST / HST and PST in Canada, VAT or GST where the country charges it, else Tax.
		 *
		 * @since 6.0.5
		 * @param string $cc Country.
		 * @return string
		 */
		public static function own_tax_name( $cc ) {
			$cc     = strtoupper( (string) $cc );
			$system = self::system( $cc );
			if ( 'us' === $system ) {
				return __( 'Sales tax', 'wp-easycart' );
			}
			if ( 'canada' === $system ) {
				return __( 'GST / HST and PST', 'wp-easycart' );
			}
			if ( 'vat' === $system ) {
				return ( class_exists( 'wp_easycart_vat' ) && in_array( $cc, wp_easycart_vat::gst_countries(), true ) ) ? __( 'GST', 'wp-easycart' ) : __( 'VAT', 'wp-easycart' );
			}
			return __( 'Tax', 'wp-easycart' );
		}

		/**
		 * The store's choices for a country, with the defaults: state ( the US state or Canadian province it is in ), included ( prices
		 * include VAT / GST ), eu ( home | oss ), pst ( home | all: Canadian PST where the store is registered ).
		 *
		 * @param string $cc Country.
		 * @return array
		 */
		public static function choices( $cc ) {
			global $wpdb;
			$cc    = strtoupper( (string) $cc );
			$saved = get_option( self::CHOICES, array() );
			$mine  = ( is_array( $saved ) && isset( $saved[ $cc ] ) && is_array( $saved[ $cc ] ) ) ? $saved[ $cc ] : array();
			$eu    = (string) get_option( 'ec_option_eu_vat_mode', '' );
			$out   = array(
				'state'    => isset( $mine['state'] ) ? (string) $mine['state'] : '',
				'included' => isset( $mine['included'] ) ? (int) (bool) $mine['included'] : 1,
				'eu'       => isset( $mine['eu'] ) ? (string) $mine['eu'] : ( 'oss' === $eu ? 'oss' : 'home' ),
				'pst'      => ( isset( $mine['pst'] ) && 'all' === $mine['pst'] ) ? 'all' : 'home',
			);
			if ( '' === $out['state'] && 'US' === $cc ) {
				/* A US store that set up TaxJar or TaxCloud gave its state there; else its one rate by state. */
				foreach ( array( 'ec_option_tax_jar_state', 'ec_option_tax_cloud_state' ) as $key ) {
					$state = strtoupper( trim( (string) get_option( $key, '' ) ) );
					if ( '' !== $state ) {
						$out['state'] = $state;
						break;
					}
				}
			}
			if ( '' === $out['state'] && in_array( $cc, array( 'US', 'CA' ), true ) && self::table_ready() ) {
				$codes = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT state_code FROM ec_taxrate WHERE tax_by_state = 1 AND ( country_code = %s OR country_code = '' )", $cc ) );
				if ( 1 === count( $codes ) ) {
					$out['state'] = strtoupper( (string) $codes[0] );
				}
			}
			$known = ( 'US' === $cc ) ? self::us_states() : ( ( 'CA' === $cc ) ? self::canada() : array() );
			if ( '' !== $out['state'] && ! isset( $known[ $out['state'] ] ) ) {
				$out['state'] = '';
			}
			if ( ! in_array( $out['eu'], array( 'home', 'oss' ), true ) ) {
				$out['eu'] = 'home';
			}
			return $out;
		}

		/**
		 * Keep the store's choices for a country.
		 *
		 * @param string $cc      Country.
		 * @param array  $choices Choices.
		 */
		public static function save_choices( $cc, $choices ) {
			$saved = get_option( self::CHOICES, array() );
			$saved = is_array( $saved ) ? $saved : array();
			$saved[ strtoupper( (string) $cc ) ] = array(
				'state'    => isset( $choices['state'] ) ? strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $choices['state'] ) ) : '',
				'included' => empty( $choices['included'] ) ? 0 : 1,
				'eu'       => ( isset( $choices['eu'] ) && 'oss' === $choices['eu'] ) ? 'oss' : 'home',
				'pst'      => ( isset( $choices['pst'] ) && 'all' === $choices['pst'] ) ? 'all' : 'home',
			);
			update_option( self::CHOICES, $saved, false );
		}

		/**
		 * Choices with the changes a control sends: "state:TX", "eu:oss", "pst:all", "included:0", several joined by "|".
		 *
		 * @param string $cc    Country.
		 * @param string $input The control's value.
		 * @param array  $base  Choices to start from ( default: the saved ones ).
		 * @return array
		 */
		public static function choices_with( $cc, $input, $base = null ) {
			$choices = is_array( $base ) ? array_merge( self::choices( $cc ), $base ) : self::choices( $cc );
			foreach ( explode( '|', (string) $input ) as $segment ) {
				$parts = explode( ':', $segment, 2 );
				if ( 2 !== count( $parts ) ) {
					continue;
				}
				$key   = sanitize_key( $parts[0] );
				$value = (string) $parts[1];
				if ( 'state' === $key ) {
					$choices['state'] = strtoupper( preg_replace( '/[^A-Za-z]/', '', $value ) );
				} elseif ( 'eu' === $key ) {
					$choices['eu'] = ( 'oss' === $value ) ? 'oss' : 'home';
				} elseif ( 'pst' === $key ) {
					$choices['pst'] = ( 'all' === $value ) ? 'all' : 'home';
				} elseif ( 'included' === $key ) {
					$choices['included'] = '0' === $value ? 0 : 1;
				}
			}
			$known = ( 'US' === strtoupper( (string) $cc ) ) ? self::us_states() : ( ( 'CA' === strtoupper( (string) $cc ) ) ? self::canada() : array() );
			if ( '' !== $choices['state'] && ! isset( $known[ $choices['state'] ] ) ) {
				$choices['state'] = '';
			}
			return $choices;
		}

		/* ------------------------------------------------------------------ */
		/* The recommendation                                                  */
		/* ------------------------------------------------------------------ */

		/**
		 * What a store in $cc is expected to charge.
		 *
		 * @param string     $cc      Country ( default: the store's ).
		 * @param array|null $choices Choices ( default: the saved ones ).
		 * @return array supported, country, country_name, system, name ( VAT | GST ), state, state_name, rate, settings, summary,
		 *               notes, sources, checked.
		 */
		public static function recommend( $cc = null, $choices = null ) {
			$cc      = strtoupper( (string) ( null === $cc ? self::store_country() : $cc ) );
			$choices = is_array( $choices ) ? array_merge( self::choices( $cc ), $choices ) : self::choices( $cc );
			$system  = self::system( $cc );
			$rec     = array(
				'supported'    => '' !== $system,
				'country'      => $cc,
				'country_name' => self::country_name( $cc ),
				'system'       => $system,
				'name'         => in_array( $cc, array( 'AU', 'NZ', 'SG' ), true ) ? 'GST' : 'VAT',
				'state'        => '',
				'state_name'   => '',
				'rate'         => 0.0,
				'choices'      => $choices,
				'settings'     => array(),
				'summary'      => array(),
				'notes'        => array(),
				'sources'      => self::sources( $cc ),
				'checked'      => self::CHECKED,
			);
			if ( 'vat' === $system ) {
				$rec = self::recommend_vat( $rec, $choices );
			} elseif ( 'us' === $system ) {
				$rec = self::recommend_us( $rec, $choices );
			} elseif ( 'canada' === $system ) {
				$rec = self::recommend_canada( $rec, $choices );
			}
			/**
			 * The recommended tax settings for a store in a country.
			 *
			 * @since 6.0.5
			 * @param array  $rec     The recommendation ( settings: the values apply() writes ).
			 * @param string $cc      Country.
			 * @param array  $choices The store's choices.
			 */
			return (array) apply_filters( 'wp_easycart_tax_setup_recommendation', $rec, $cc, $choices );
		}

		/**
		 * A VAT / GST country: the rate, included in prices, on shipping too, by country ( the store's country and its VAT area carry
		 * it, EU countries the home rate or their own, everyone else none ), businesses abroad pay none.
		 *
		 * @param array $rec     Recommendation so far.
		 * @param array $choices Choices.
		 * @return array
		 */
		private static function recommend_vat( $rec, $choices ) {
			$cc        = $rec['country'];
			$countries = self::vat_countries();
			$info      = $countries[ $cc ];
			$rate      = (float) $info['rate'];
			$name      = $rec['name'];
			$in_eu     = class_exists( 'wp_easycart_vat' ) && wp_easycart_vat::in_eu( $cc );
			$rates     = array( $cc => $rate );
			foreach ( $info['area'] as $area ) {
				$rates[ $area ] = $rate;
			}
			if ( $in_eu ) {
				foreach ( wp_easycart_vat::eu() as $member => $own ) {
					if ( $member !== $cc ) {
						$rates[ $member ] = ( 'oss' === $choices['eu'] ) ? (float) $own : $rate;
					}
				}
				if ( isset( $rates['GR'] ) ) {
					$rates['EL'] = $rates['GR']; /* some stores list Greece as EL */
				}
			}
			$rec['rate']     = $rate;
			$rec['settings'] = array(
				'vat'           => array( 'rate' => $rate, 'by_country' => 1, 'included' => (int) $choices['included'] ),
				'vat_shipping'  => 1,
				'business_rate' => 0.0,
				'vat_name'      => $name,
				'country_rates' => $rates,
				'eu_mode'       => $in_eu ? $choices['eu'] : null,
				'global'        => 0,
				'canada'        => 0,
				'remove_states' => 'own',
				'remove_rates'  => array( $cc ),
			);
			$pct = self::pct( $rate );
			/* translators: 1: rate ( 10% ), 2: VAT or GST */
			$rec['summary'][] = $choices['included'] ? sprintf( __( '%1$s %2$s, included in your prices', 'wp-easycart' ), $pct, $name ) : sprintf( __( '%1$s %2$s, added at checkout', 'wp-easycart' ), $pct, $name );
			/* translators: %s: VAT or GST */
			$rec['summary'][] = sprintf( __( '%s on shipping', 'wp-easycart' ), $name );
			if ( $in_eu ) {
				$rec['summary'][] = ( 'oss' === $choices['eu'] ) ? __( 'Customers in other EU countries pay their own country\'s VAT', 'wp-easycart' ) : sprintf( /* translators: %s: rate */ __( 'Customers in other EU countries pay your rate ( %s )', 'wp-easycart' ), $pct );
				$rec['summary'][] = __( 'Customers outside the EU pay no VAT', 'wp-easycart' );
				$rec['summary'][] = __( 'EU businesses with a valid VAT number pay no VAT ( reverse charge )', 'wp-easycart' );
				$rec['notes'][]   = __( 'Selling more than €10,000 a year to consumers in other EU countries, or registered for the One-Stop Shop ( OSS )? Choose "each country\'s own VAT".', 'wp-easycart' );
			} else {
				/* translators: %s: VAT or GST */
				$rec['summary'][] = sprintf( __( 'No %s for customers abroad', 'wp-easycart' ), $name );
				/* translators: %s: VAT or GST */
				$rec['summary'][] = sprintf( __( 'Businesses abroad pay no %s', 'wp-easycart' ), $name );
			}
			if ( '' !== $info['threshold'] ) {
				/* translators: 1: VAT or GST, 2: threshold ( £90,000 ) */
				$rec['notes'][] = sprintf( __( 'Not registered for %1$s ( usually needed from %2$s of sales a year )? Then don\'t charge it yet: switch it off under Settings › Taxes.', 'wp-easycart' ), $name, $info['threshold'] );
			}
			$rec['notes'][] = __( 'Products at a reduced rate ( food, books ): set the reduced rate under Settings › Taxes and choose it in each product\'s Tax card.', 'wp-easycart' );
			return $rec;
		}

		/**
		 * The United States: sales tax in the home state at the state rate, shipping as that state taxes it, no VAT.
		 *
		 * @param array $rec     Recommendation so far.
		 * @param array $choices Choices.
		 * @return array
		 */
		private static function recommend_us( $rec, $choices ) {
			$states = self::us_states();
			$state  = (string) $choices['state'];
			$rec['settings'] = array(
				'vat'           => 0,
				'global'        => 0,
				'canada'        => 0,
				'remove_states' => 'blank_country',
				'remove_rates'  => array( 'US' ),
			);
			if ( '' === $state || ! isset( $states[ $state ] ) ) {
				$rec['notes'][] = __( 'Choose your state to see its rate.', 'wp-easycart' );
				return $rec;
			}
			$info              = $states[ $state ];
			$rec['state']      = $state;
			$rec['state_name'] = self::state_name( 'US', $state );
			$rec['rate']       = (float) $info[0];
			$rec['settings']['home_state'] = array( 'code' => $state, 'rate' => (float) $info[0] );
			if ( $info[0] > 0 ) {
				$rec['settings']['tax_shipping'] = (int) $info[1];
				/* translators: 1: rate, 2: state */
				$rec['summary'][] = sprintf( __( '%1$s %2$s sales tax, added at checkout', 'wp-easycart' ), self::pct( $info[0] ), $rec['state_name'] );
				/* translators: %s: state */
				$rec['summary'][] = $info[1] ? sprintf( __( 'Tax on shipping ( %s taxes shipping charged with taxable goods )', 'wp-easycart' ), $rec['state_name'] ) : sprintf( __( 'No tax on shipping ( %s doesn\'t tax shipping listed separately )', 'wp-easycart' ), $rec['state_name'] );
				if ( ! in_array( $state, self::no_local_tax_states(), true ) ) {
					/* translators: %s: state */
					$rec['notes'][] = sprintf( __( 'Cities and counties in %s add their own rates, so the state rate alone can collect too little. For exact rates by address, use an automated tax service ( TaxJar, TaxCloud or AvaTax ).', 'wp-easycart' ), $rec['state_name'] );
				}
				if ( $info[2] ) {
					/* translators: %s: state */
					$rec['notes'][] = sprintf( __( 'Whether %s taxes shipping depends on how you sell delivery; this is the usual case for an online store. Check with the state if you\'re unsure.', 'wp-easycart' ), $rec['state_name'] );
				}
			} else {
				/* translators: %s: state */
				$rec['summary'][] = sprintf( __( '%s has no state sales tax', 'wp-easycart' ), $rec['state_name'] );
				if ( 'AK' === $state ) {
					$rec['notes'][] = __( 'Some Alaska towns charge a local sales tax; remote sellers collect it through the Alaska Remote Seller Sales Tax Commission.', 'wp-easycart' );
				}
			}
			$rec['notes'][] = __( 'You also collect in other states once your sales there pass their threshold ( usually $100,000 a year ): add those under Rates by state.', 'wp-easycart' );
			return $rec;
		}

		/**
		 * Canada: GST / HST in every province at today's rates ( by where the order goes ), PST / QST where the store is registered
		 * ( its own province; every PST province when chosen ), tax on shipping, no VAT.
		 *
		 * @param array $rec     Recommendation so far.
		 * @param array $choices Choices.
		 * @return array
		 */
		private static function recommend_canada( $rec, $choices ) {
			$state     = (string) $choices['state'];
			$provinces = self::canada();
			$pst       = array();
			foreach ( $provinces as $code => $info ) {
				if ( $info['pst'] > 0 && ( 'all' === $choices['pst'] || $code === $state ) ) {
					$pst[] = $code;
				}
			}
			$rec['state']      = isset( $provinces[ $state ] ) ? $state : '';
			$rec['state_name'] = '' !== $rec['state'] ? self::state_name( 'CA', $state ) : '';
			$rec['settings']   = array(
				'vat'           => 0,
				'global'        => 0,
				'canada'        => array( 'pst' => $pst ),
				'tax_shipping'  => 1,
				'remove_states' => 'own',
				'remove_rates'  => array( 'CA' ),
			);
			$rec['summary'][] = __( 'GST / HST in every province at today\'s rates, by where the order goes', 'wp-easycart' );
			if ( '' !== $rec['state'] ) {
				$home = $provinces[ $rec['state'] ];
				if ( $home['hst'] > 0 ) {
					/* translators: 1: province, 2: rate */
					$rec['summary'][] = sprintf( __( '%1$s: HST %2$s', 'wp-easycart' ), $rec['state_name'], self::pct( $home['hst'] * 100 ) );
				} elseif ( $home['pst'] > 0 ) {
					/* translators: 1: province, 2: GST rate, 3: PST rate */
					$rec['summary'][] = sprintf( __( '%1$s: GST %2$s + PST %3$s', 'wp-easycart' ), $rec['state_name'], self::pct( $home['gst'] * 100 ), self::pct( $home['pst'] * 100 ) );
				} else {
					/* translators: 1: province, 2: rate */
					$rec['summary'][] = sprintf( __( '%1$s: GST %2$s', 'wp-easycart' ), $rec['state_name'], self::pct( $home['gst'] * 100 ) );
				}
			} else {
				$rec['notes'][] = __( 'Choose your province to see its rate.', 'wp-easycart' );
			}
			$rec['summary'][] = ( 'all' === $choices['pst'] ) ? __( 'PST / QST in British Columbia, Saskatchewan, Manitoba and Québec', 'wp-easycart' ) : ( $pst ? __( 'PST / QST only in your own province', 'wp-easycart' ) : __( 'No PST / QST ( only collected where you\'re registered )', 'wp-easycart' ) );
			$rec['summary'][] = __( 'Tax on shipping', 'wp-easycart' );
			$rec['notes'][]   = __( 'PST / QST is collected only where you\'re registered: British Columbia over $10,000 of sales a year, Saskatchewan from your first sale, Manitoba and Québec over $30,000.', 'wp-easycart' );
			$rec['notes'][]   = __( 'Under $30,000 of sales a year, you may not need to register for GST / HST yet.', 'wp-easycart' );
			return $rec;
		}

		/* ------------------------------------------------------------------ */
		/* What the store has now, and what would change                       */
		/* ------------------------------------------------------------------ */

		/**
		 * The store's tax settings as checkout reads them.
		 *
		 * @return array
		 */
		public static function current() {
			global $wpdb;
			$ready = self::table_ready();
			$now   = array(
				'vat_row'       => $ready ? $wpdb->get_row( 'SELECT * FROM ec_taxrate WHERE tax_by_vat = 1 OR tax_by_single_vat = 1 ORDER BY taxrate_id LIMIT 1' ) : null,
				'all_row'       => $ready ? $wpdb->get_row( 'SELECT * FROM ec_taxrate WHERE tax_by_all = 1 ORDER BY taxrate_id LIMIT 1' ) : null,
				'state_rows'    => $ready ? (array) $wpdb->get_results( 'SELECT taxrate_id, state_code, country_code, state_rate FROM ec_taxrate WHERE tax_by_state = 1 ORDER BY taxrate_id' ) : array(),
				'country_rows'  => $ready ? (array) $wpdb->get_results( 'SELECT taxrate_id, country_code, country_rate FROM ec_taxrate WHERE tax_by_country = 1 ORDER BY taxrate_id' ) : array(),
				'country_rates' => array(),
				'canada'        => (int) get_option( 'ec_option_enable_easy_canada_tax' ),
				'canada_opts'   => get_option( 'ec_option_canada_tax_options', array() ),
				'tax_shipping'  => (int) get_option( 'ec_option_collect_tax_on_shipping' ),
				'vat_shipping'  => (int) get_option( 'ec_option_no_vat_on_shipping' ) ? 0 : 1,
				'business_rate' => (float) get_option( 'ec_option_vat_custom_rate', 0 ),
				'vat_name'      => '',
			);
			if ( ! is_array( $now['canada_opts'] ) ) {
				$now['canada_opts'] = array();
			}
			foreach ( (array) $wpdb->get_results( 'SELECT iso2_cnt, vat_rate_cnt FROM ec_country' ) as $row ) {
				$now['country_rates'][ strtoupper( (string) $row->iso2_cnt ) ] = (float) $row->vat_rate_cnt;
			}
			$now['vat_name_set'] = strtoupper( trim( (string) get_option( 'ec_option_vat_name', '' ) ) );
			if ( class_exists( 'wp_easycart_vat' ) ) {
				wp_easycart_vat::forget();
				$now['vat_name'] = wp_easycart_vat::name();
			}
			return $now;
		}

		/**
		 * What applying the recommendation would change: each with a label, the value now, the value after and why.
		 *
		 * @param array      $rec Recommendation ( recommend() ).
		 * @param array|null $now Current settings ( current() ).
		 * @return array list of array( key, label, from, to, why )
		 */
		public static function changes( $rec, $now = null ) {
			$now = is_array( $now ) ? $now : self::current();
			$out = array();
			if ( empty( $rec['supported'] ) ) {
				return $out;
			}
			$set  = $rec['settings'];
			$name = $rec['name'];
			$on   = __( 'On', 'wp-easycart' );
			$off  = __( 'Off', 'wp-easycart' );

			/* VAT / GST. */
			if ( is_array( $set['vat'] ) ) {
				$want = $set['vat'];
				$row  = $now['vat_row'];
				if ( ! $row ) {
					$out[] = array(
						'key'   => 'vat',
						/* translators: %s: VAT or GST */
						'label' => sprintf( __( 'Charge %s', 'wp-easycart' ), $name ),
						'from'  => $off,
						/* translators: %s: rate */
						'to'    => $want['included'] ? sprintf( __( '%s, included in prices', 'wp-easycart' ), self::pct( $want['rate'] ) ) : sprintf( __( '%s, added at checkout', 'wp-easycart' ), self::pct( $want['rate'] ) ),
						/* translators: %s: VAT or GST */
						'why'   => sprintf( __( 'Orders to customers in your country carry %s.', 'wp-easycart' ), $name ),
					);
				} else {
					if ( abs( (float) $row->vat_rate - $want['rate'] ) > 0.0005 ) {
						$out[] = array(
							'key'   => 'vat_rate',
							/* translators: %s: VAT or GST */
							'label' => sprintf( __( 'Default %s rate', 'wp-easycart' ), $name ),
							'from'  => self::pct( $row->vat_rate ),
							'to'    => self::pct( $want['rate'] ),
							'why'   => __( 'Your country\'s standard rate.', 'wp-easycart' ),
						);
					}
					if ( (int) $row->tax_by_vat !== (int) $want['by_country'] ) {
						$out[] = array(
							'key'   => 'vat_by_country',
							'label' => __( 'Rate varies by country', 'wp-easycart' ),
							'from'  => $off,
							'to'    => $on,
							'why'   => __( 'Customers abroad pay a different rate, or none, so checkout looks up their country.', 'wp-easycart' ),
						);
					}
					if ( (int) $row->vat_included !== (int) $want['included'] ) {
						$out[] = array(
							'key'   => 'vat_included',
							/* translators: %s: VAT or GST */
							'label' => sprintf( __( 'Prices include %s', 'wp-easycart' ), $name ),
							'from'  => $want['included'] ? $off : $on,
							'to'    => $want['included'] ? $on : $off,
							'why'   => $want['included'] ? __( 'Prices shown to consumers must include the tax.', 'wp-easycart' ) : __( 'You chose to add it at checkout.', 'wp-easycart' ),
						);
					}
				}
			} elseif ( 0 === $set['vat'] && $now['vat_row'] ) {
				$out[] = array(
					'key'   => 'vat',
					'label' => __( 'Charge VAT', 'wp-easycart' ),
					'from'  => $on,
					'to'    => $off,
					'why'   => ( 'us' === $rec['system'] ) ? __( 'US stores collect sales tax, not VAT.', 'wp-easycart' ) : __( 'Canadian stores collect GST / HST and PST, not VAT.', 'wp-easycart' ),
				);
			}
			if ( isset( $set['vat_shipping'] ) && (int) $now['vat_shipping'] !== (int) $set['vat_shipping'] ) {
				$out[] = array(
					'key'   => 'vat_shipping',
					/* translators: %s: VAT or GST */
					'label' => sprintf( __( 'Charge %s on shipping', 'wp-easycart' ), $name ),
					'from'  => $off,
					'to'    => $on,
					/* translators: %s: VAT or GST */
					'why'   => sprintf( __( 'Delivery you charge for is part of the sale, so it carries %s too.', 'wp-easycart' ), $name ),
				);
			}
			if ( isset( $set['business_rate'] ) && abs( (float) $now['business_rate'] - (float) $set['business_rate'] ) > 0.0005 ) {
				$in_eu = class_exists( 'wp_easycart_vat' ) && wp_easycart_vat::in_eu( $rec['country'] );
				$out[] = array(
					'key'   => 'business_rate',
					/* translators: %s: VAT or GST */
					'label' => sprintf( __( 'Rate for %s-registered businesses abroad', 'wp-easycart' ), $name ),
					'from'  => self::pct( $now['business_rate'] ),
					'to'    => self::pct( $set['business_rate'] ),
					/* translators: %s: VAT or GST */
					'why'   => $in_eu ? __( 'A business in another EU country with a valid VAT number pays no VAT: it accounts for it itself ( reverse charge ).', 'wp-easycart' ) : sprintf( __( 'Goods sent abroad carry no %s.', 'wp-easycart' ), $name ),
				);
			}
			/* The name the store would use in this country: its own choice, else the country's ( GST in Australia, New Zealand, Singapore ). */
			$named = ( isset( $now['vat_name_set'] ) && in_array( $now['vat_name_set'], array( 'VAT', 'GST' ), true ) ) ? $now['vat_name_set'] : ( isset( $set['vat_name'] ) ? $set['vat_name'] : '' );
			if ( isset( $set['vat_name'] ) && $named !== $set['vat_name'] ) {
				$out[] = array(
					'key'   => 'vat_name',
					'label' => __( 'Tax name', 'wp-easycart' ),
					'from'  => $named,
					'to'    => $set['vat_name'],
					'why'   => __( 'That\'s what it\'s called in your country, at checkout and on receipts and invoices.', 'wp-easycart' ),
				);
			}
			if ( isset( $set['country_rates'] ) ) {
				$out = array_merge( $out, self::country_rate_changes( $rec, $now ) );
			}

			/* Other systems that would charge on top. */
			if ( isset( $set['global'] ) && 0 === $set['global'] && $now['all_row'] ) {
				$out[] = array(
					'key'   => 'global',
					'label' => __( 'Flat rate on every order', 'wp-easycart' ),
					'from'  => self::pct( $now['all_row']->all_rate ),
					'to'    => $off,
					'why'   => ( 'vat' === $rec['system'] ) ? sprintf( /* translators: %s: VAT or GST */ __( '%s replaces it; both would be charged.', 'wp-easycart' ), $name ) : __( 'Tax depends on where the order goes; a flat rate charges everyone the same.', 'wp-easycart' ),
				);
			}
			if ( 0 === ( isset( $set['canada'] ) ? $set['canada'] : null ) && $now['canada'] ) {
				$out[] = array(
					'key'   => 'canada',
					'label' => __( 'Canadian GST / HST / PST', 'wp-easycart' ),
					'from'  => $on,
					'to'    => $off,
					'why'   => __( 'Only for stores in Canada.', 'wp-easycart' ),
				);
			}
			if ( isset( $set['canada'] ) && is_array( $set['canada'] ) ) {
				$out = array_merge( $out, self::canada_changes( $rec, $now ) );
			}
			if ( isset( $set['tax_shipping'] ) && (int) $now['tax_shipping'] !== (int) $set['tax_shipping'] ) {
				$state = ( 'us' === $rec['system'] ) ? $rec['state_name'] : '';
				$out[] = array(
					'key'   => 'tax_shipping',
					'label' => __( 'Charge tax on shipping', 'wp-easycart' ),
					'from'  => $set['tax_shipping'] ? $off : $on,
					'to'    => $set['tax_shipping'] ? $on : $off,
					/* translators: %s: state */
					'why'   => ( 'canada' === $rec['system'] ) ? __( 'Delivery is taxed with the goods in Canada.', 'wp-easycart' ) : ( $set['tax_shipping'] ? sprintf( __( '%s taxes shipping charged with taxable goods.', 'wp-easycart' ), $state ) : sprintf( __( '%s doesn\'t tax shipping listed separately on the order.', 'wp-easycart' ), $state ) ),
				);
			}
			if ( isset( $set['home_state'] ) ) {
				$change = self::home_state_change( $rec, $now );
				if ( $change ) {
					$out[] = $change;
				}
			}
			$out = array_merge( $out, self::row_changes( $rec, $now ) );
			/**
			 * What applying the recommended tax settings would change.
			 *
			 * @since 6.0.5
			 * @param array $out Changes ( key, label, from, to, why ).
			 * @param array $rec The recommendation.
			 * @param array $now The settings now.
			 */
			return (array) apply_filters( 'wp_easycart_tax_setup_changes', $out, $rec, $now );
		}

		/**
		 * The rates by country ( the VAT table ): the store's country and its VAT area, EU countries, and countries that should pay none.
		 *
		 * @param array $rec Recommendation.
		 * @param array $now Current settings.
		 * @return array
		 */
		private static function country_rate_changes( $rec, $now ) {
			$want  = $rec['settings']['country_rates'];
			$name  = $rec['name'];
			$cc    = $rec['country'];
			$have  = $now['country_rates'];
			$out   = array();
			$home  = isset( $have[ $cc ] ) ? (float) $have[ $cc ] : 0.0;
			if ( abs( $home - (float) $want[ $cc ] ) > 0.0005 ) {
				$out[] = array(
					'key'   => 'country_rate_home',
					/* translators: %s: VAT or GST */
					'label' => sprintf( __( '%s for customers in your country', 'wp-easycart' ), $name ),
					'from'  => $home > 0 ? self::pct( $home ) : __( 'None', 'wp-easycart' ),
					'to'    => self::pct( $want[ $cc ] ),
					'why'   => __( 'Checkout looks up the customer\'s country once they enter their address.', 'wp-easycart' ),
				);
			}
			$eu_diff  = 0;
			$area     = array();
			$others   = array();
			$in_eu    = class_exists( 'wp_easycart_vat' ) && wp_easycart_vat::in_eu( $cc );
			$eu_codes = $in_eu ? array_keys( wp_easycart_vat::eu() ) : array();
			foreach ( $want as $code => $rate ) {
				if ( $code === $cc || ! isset( $have[ $code ] ) ) {
					continue;
				}
				if ( abs( (float) $have[ $code ] - (float) $rate ) > 0.0005 ) {
					if ( in_array( $code, $eu_codes, true ) || 'EL' === $code ) {
						++$eu_diff;
					} else {
						$area[ $code ] = self::country_name( $code ) . ' ' . ( $have[ $code ] > 0 ? self::pct( $have[ $code ] ) : __( 'none', 'wp-easycart' ) );
					}
				}
			}
			foreach ( $have as $code => $rate ) {
				if ( ! isset( $want[ $code ] ) && $rate > 0 ) {
					$others[ $code ] = $rate;
				}
			}
			$eu_none = true;
			foreach ( $eu_codes as $code ) {
				if ( $code !== $cc && isset( $have[ $code ] ) && $have[ $code ] > 0 ) {
					$eu_none = false;
				}
			}
			if ( $eu_diff ) {
				$out[] = array(
					'key'   => 'country_rates_eu',
					'label' => __( 'Customers in other EU countries', 'wp-easycart' ),
					/* translators: %d: number of countries */
					'from'  => $eu_none ? __( 'None', 'wp-easycart' ) : sprintf( _n( '%d country differs', '%d countries differ', $eu_diff, 'wp-easycart' ), $eu_diff ),
					/* translators: %s: rate */
					'to'    => ( 'oss' === $rec['choices']['eu'] ) ? __( 'Each country\'s own rate', 'wp-easycart' ) : sprintf( __( 'Your rate ( %s )', 'wp-easycart' ), self::pct( $rec['rate'] ) ),
					'why'   => ( 'oss' === $rec['choices']['eu'] ) ? __( 'Over €10,000 a year of sales to other EU countries, or under the One-Stop Shop, each country\'s rate applies.', 'wp-easycart' ) : __( 'Under €10,000 a year of sales to other EU countries, your own country\'s VAT applies.', 'wp-easycart' ),
				);
			}
			if ( $area ) {
				$out[] = array(
					'key'   => 'country_rates_area',
					'label' => __( 'Countries in your VAT area', 'wp-easycart' ),
					'from'  => implode( ', ', $area ),
					'to'    => self::pct( $rec['rate'] ),
					/* translators: %s: VAT or GST */
					'why'   => sprintf( __( 'Customers there pay your %s.', 'wp-easycart' ), $name ),
				);
			}
			if ( $others ) {
				$list = array();
				foreach ( array_slice( $others, 0, 3, true ) as $code => $rate ) {
					$list[] = self::country_name( $code ) . ' ' . self::pct( $rate );
				}
				$more  = count( $others ) - count( $list );
				$out[] = array(
					'key'   => 'country_rates_abroad',
					/* translators: 1: VAT or GST, 2: region ( outside the EU, abroad ) */
					'label' => sprintf( __( '%1$s for customers %2$s', 'wp-easycart' ), $name, $in_eu ? __( 'outside the EU', 'wp-easycart' ) : __( 'abroad', 'wp-easycart' ) ),
					/* translators: %d: more countries */
					'from'  => implode( ', ', $list ) . ( $more > 0 ? ' ' . sprintf( _n( '+ %d more', '+ %d more', $more, 'wp-easycart' ), $more ) : '' ),
					'to'    => __( 'None', 'wp-easycart' ),
					/* translators: %s: VAT or GST */
					'why'   => sprintf( __( 'Goods sent abroad carry no %s ( exports are zero-rated ).', 'wp-easycart' ), $name ),
				);
			}
			return $out;
		}

		/**
		 * The Canada grid: switched on, GST / HST at today's rates in every province for every customer role, PST where registered.
		 *
		 * @param array $rec Recommendation.
		 * @param array $now Current settings.
		 * @return array
		 */
		private static function canada_changes( $rec, $now ) {
			$out = array();
			if ( ! $now['canada'] ) {
				$out[] = array(
					'key'   => 'canada',
					'label' => __( 'Canadian GST / HST / PST', 'wp-easycart' ),
					'from'  => __( 'Off', 'wp-easycart' ),
					'to'    => __( 'On', 'wp-easycart' ),
					'why'   => __( 'Checkout charges the rate of the province the order goes to.', 'wp-easycart' ),
				);
			}
			$want      = self::canada_options( $rec );
			$have      = $now['canada_opts'];
			$federal   = array();
			$any       = false;
			foreach ( array_keys( $have ) as $key ) {
				if ( 0 === strpos( (string) $key, 'ec_option_collect_' ) ) {
					$any = true;
					break;
				}
			}
			$pst_on    = array();
			$pst_off   = array();
			foreach ( self::canada() as $code => $info ) {
				$name = self::state_name( 'CA', $code );
				foreach ( self::roles() as $role ) {
					$prefix  = 'ec_option_' . $info['key'] . '_tax_' . $role . '_';
					$collect = isset( $have[ 'ec_option_collect_' . $info['key'] . '_tax_' . $role ] );
					foreach ( array( 'gst', 'hst' ) as $type ) {
						$is = isset( $have[ $prefix . $type ] ) ? (float) $have[ $prefix . $type ] : 0.0;
						if ( ! $collect || abs( $is - (float) $want[ $prefix . $type ] ) > 0.00001 ) {
							$federal[ $code ] = $name;
						}
					}
					$is_pst = $collect && isset( $have[ $prefix . 'pst' ] ) ? (float) $have[ $prefix . 'pst' ] : 0.0;
					if ( abs( $is_pst - (float) $want[ $prefix . 'pst' ] ) > 0.00001 ) {
						if ( $want[ $prefix . 'pst' ] > 0 ) {
							$pst_on[ $code ] = $name . ' ' . self::pct( $want[ $prefix . 'pst' ] * 100 );
						} else {
							$pst_off[ $code ] = $name;
						}
					}
				}
			}
			if ( $federal ) {
				$out[] = array(
					'key'   => 'canada_federal',
					'label' => __( 'GST / HST by province', 'wp-easycart' ),
					/* translators: %s: provinces */
					'from'  => $any ? sprintf( __( 'Differs in %s', 'wp-easycart' ), implode( ', ', array_slice( $federal, 0, 4 ) ) . ( count( $federal ) > 4 ? '…' : '' ) ) : __( 'Not set', 'wp-easycart' ),
					'to'    => __( 'Today\'s rates, every province', 'wp-easycart' ),
					'why'   => __( 'GST / HST follows the province the order goes to ( Nova Scotia HST is 14% since April 2025 ).', 'wp-easycart' ),
				);
			}
			if ( $pst_on ) {
				$out[] = array(
					'key'   => 'canada_pst',
					'label' => __( 'PST / QST', 'wp-easycart' ),
					'from'  => __( 'Not collected, or another rate', 'wp-easycart' ),
					'to'    => implode( ', ', $pst_on ),
					'why'   => __( 'Collected where you\'re registered.', 'wp-easycart' ),
				);
			}
			if ( $pst_off ) {
				$out[] = array(
					'key'   => 'canada_pst_off',
					/* translators: %s: provinces */
					'label' => sprintf( __( 'PST / QST in %s', 'wp-easycart' ), implode( ', ', $pst_off ) ),
					'from'  => __( 'Collected', 'wp-easycart' ),
					'to'    => __( 'Not collected', 'wp-easycart' ),
					'why'   => __( 'Collected only where you\'re registered. Registered there too? Choose "every PST province".', 'wp-easycart' ),
				);
			}
			return $out;
		}

		/**
		 * The home state's rate ( US ).
		 *
		 * @param array $rec Recommendation.
		 * @param array $now Current settings.
		 * @return array|null
		 */
		private static function home_state_change( $rec, $now ) {
			$want = $rec['settings']['home_state'];
			$row  = null;
			foreach ( $now['state_rows'] as $state_row ) {
				if ( strtoupper( (string) $state_row->state_code ) === $want['code'] && in_array( strtoupper( (string) $state_row->country_code ), array( 'US', '' ), true ) ) {
					$row = $state_row;
					break;
				}
			}
			/* translators: %s: state */
			$label = sprintf( __( 'Sales tax in %s', 'wp-easycart' ), $rec['state_name'] );
			if ( $want['rate'] <= 0 ) {
				return ( $row && (float) $row->state_rate > 0 ) ? array(
					'key'   => 'home_state',
					'label' => $label,
					'from'  => self::pct( $row->state_rate ),
					'to'    => __( 'None', 'wp-easycart' ),
					/* translators: %s: state */
					'why'   => sprintf( __( '%s has no state sales tax.', 'wp-easycart' ), $rec['state_name'] ),
				) : null;
			}
			if ( $row && abs( (float) $row->state_rate - $want['rate'] ) < 0.0005 ) {
				return ( '' === (string) $row->country_code ) ? array(
					'key'   => 'home_state',
					'label' => $label,
					/* translators: %s: rate */
					'from'  => sprintf( __( '%s, no country', 'wp-easycart' ), self::pct( $row->state_rate ) ),
					'to'    => self::pct( $want['rate'] ),
					'why'   => __( 'Saved without its country, it showed blank in Settings and matched regions of other countries with the same code.', 'wp-easycart' ),
				) : null;
			}
			return array(
				'key'   => 'home_state',
				'label' => $label,
				'from'  => $row ? self::pct( $row->state_rate ) : __( 'None', 'wp-easycart' ),
				'to'    => self::pct( $want['rate'] ),
				/* translators: %s: state */
				'why'   => sprintf( __( 'The %s state rate, for orders shipped within the state.', 'wp-easycart' ), $rec['state_name'] ),
			);
		}

		/**
		 * Rates by state and by country that would charge on top of the recommended system, or were saved without their country.
		 *
		 * @param array $rec Recommendation.
		 * @param array $now Current settings.
		 * @return array
		 */
		private static function row_changes( $rec, $now ) {
			$plan = self::row_plan( $rec, $now );
			$out  = array();
			if ( $plan['remove_states'] ) {
				$list = array();
				foreach ( array_slice( $plan['remove_states'], 0, 3 ) as $row ) {
					$list[] = self::state_name( '' !== (string) $row->country_code ? $row->country_code : 'US', $row->state_code ) . ' ' . self::pct( $row->state_rate );
				}
				$more  = count( $plan['remove_states'] ) - count( $list );
				$blank = true;
				foreach ( $plan['remove_states'] as $row ) {
					if ( '' !== (string) $row->country_code ) {
						$blank = false;
					}
				}
				$out[] = array(
					'key'   => 'remove_states',
					'label' => __( 'Rates by state', 'wp-easycart' ),
					/* translators: %d: more rates */
					'from'  => implode( ', ', $list ) . ( $more > 0 ? ' ' . sprintf( _n( '+ %d more', '+ %d more', $more, 'wp-easycart' ), $more ) : '' ),
					'to'    => __( 'Removed', 'wp-easycart' ),
					'why'   => $blank ? __( 'Left by an earlier setup for a US store; your store doesn\'t collect US sales tax.', 'wp-easycart' ) : __( 'Your country\'s tax replaces them; both would be charged.', 'wp-easycart' ),
				);
			}
			if ( $plan['fix_states'] ) {
				$count = count( $plan['fix_states'] );
				$out[] = array(
					'key'   => 'fix_states',
					/* translators: %d: number of rates */
					'label' => sprintf( _n( '%d rate by state saved without its country', '%d rates by state saved without their country', $count, 'wp-easycart' ), $count ),
					'from'  => __( 'No country', 'wp-easycart' ),
					'to'    => self::country_name( 'US' ),
					'why'   => __( 'Without a country they show blank in Settings and match regions of other countries with the same code.', 'wp-easycart' ),
				);
			}
			if ( $plan['remove_rates'] ) {
				$list = array();
				foreach ( $plan['remove_rates'] as $row ) {
					$list[] = self::country_name( $row->country_code ) . ' ' . self::pct( $row->country_rate );
				}
				$out[] = array(
					'key'   => 'remove_rates',
					'label' => __( 'Rates by country', 'wp-easycart' ),
					'from'  => implode( ', ', $list ),
					'to'    => __( 'Removed', 'wp-easycart' ),
					'why'   => __( 'Your country\'s tax replaces it; both would be charged.', 'wp-easycart' ),
				);
			}
			return $out;
		}

		/**
		 * Which rate rows apply() removes or fixes.
		 *
		 * @param array $rec Recommendation.
		 * @param array $now Current settings.
		 * @return array remove_states, fix_states, remove_rates ( rows )
		 */
		private static function row_plan( $rec, $now ) {
			$set  = $rec['settings'];
			$cc   = $rec['country'];
			$plan = array( 'remove_states' => array(), 'fix_states' => array(), 'remove_rates' => array() );
			$mode = isset( $set['remove_states'] ) ? $set['remove_states'] : '';
			foreach ( $now['state_rows'] as $row ) {
				$country = strtoupper( (string) $row->country_code );
				if ( 'own' === $mode && ( '' === $country || $country === $cc ) ) {
					$plan['remove_states'][] = $row;
				} elseif ( 'blank_country' === $mode && '' === $country ) {
					/* A US store's own rates saved without a country: the home state is handled by its own change. */
					$home = isset( $set['home_state'] ) ? $set['home_state']['code'] : '';
					if ( strtoupper( (string) $row->state_code ) !== $home ) {
						$plan['fix_states'][] = $row;
					}
				}
			}
			$remove = isset( $set['remove_rates'] ) ? (array) $set['remove_rates'] : array();
			foreach ( $now['country_rows'] as $row ) {
				if ( in_array( strtoupper( (string) $row->country_code ), $remove, true ) ) {
					$plan['remove_rates'][] = $row;
				}
			}
			return $plan;
		}

		/* ------------------------------------------------------------------ */
		/* Apply, undo, keep                                                    */
		/* ------------------------------------------------------------------ */

		/**
		 * Write the recommendation ( a snapshot first, for undo() ), keep the choices, and forget "Keep my settings".
		 *
		 * @param string|null $cc      Country ( default: the store's ).
		 * @param array|null  $choices Choices ( default: the saved ones ).
		 * @return int|WP_Error The number of changes made.
		 */
		public static function apply( $cc = null, $choices = null ) {
			global $wpdb;
			$rec = self::recommend( $cc, $choices );
			if ( empty( $rec['supported'] ) ) {
				return new WP_Error( 'unsupported', __( 'WP EasyCart has no recommended tax settings for this country yet.', 'wp-easycart' ) );
			}
			if ( ! self::table_ready() ) {
				return new WP_Error( 'tables', __( 'The tax tables are not ready yet.', 'wp-easycart' ) );
			}
			$now     = self::current();
			$changes = self::changes( $rec, $now );
			self::snapshot( $rec['country'] );
			$set = $rec['settings'];

			/* VAT / GST. */
			if ( is_array( $set['vat'] ) ) {
				update_option( 'ec_option_use_vat_tax', 1 );
				update_option( 'ec_vat_by_country', (int) $set['vat']['by_country'] );
				update_option( 'ec_default_vat_rate', (float) $set['vat']['rate'] );
				update_option( 'ec_vat_pricing_method', (int) $set['vat']['included'] );
				self::write_vat_row( $set['vat'] );
			} elseif ( 0 === $set['vat'] ) {
				update_option( 'ec_option_use_vat_tax', 0 );
				$wpdb->query( 'DELETE FROM ec_taxrate WHERE tax_by_vat = 1 OR tax_by_single_vat = 1' );
			}
			if ( isset( $set['vat_shipping'] ) ) {
				update_option( 'ec_option_no_vat_on_shipping', $set['vat_shipping'] ? 0 : 1 );
			}
			if ( isset( $set['business_rate'] ) ) {
				update_option( 'ec_option_vat_custom_rate', (float) $set['business_rate'] );
			}
			if ( isset( $set['vat_name'] ) ) {
				/* Automatic gives the country's own name ( GST in Australia, New Zealand, Singapore ). */
				update_option( 'ec_option_vat_name', '' );
				if ( class_exists( 'wp_easycart_vat' ) ) {
					wp_easycart_vat::forget();
				}
			}
			if ( isset( $set['country_rates'] ) ) {
				$wpdb->query( 'UPDATE ec_country SET vat_rate_cnt = 0 WHERE vat_rate_cnt <> 0' );
				foreach ( $set['country_rates'] as $code => $rate ) {
					$wpdb->query( $wpdb->prepare( 'UPDATE ec_country SET vat_rate_cnt = %f WHERE iso2_cnt = %s', (float) $rate, $code ) );
				}
			}
			if ( ! empty( $set['eu_mode'] ) ) {
				update_option( 'ec_option_eu_vat_mode', $set['eu_mode'] );
			}

			/* Systems that would charge on top. */
			if ( isset( $set['global'] ) && 0 === $set['global'] ) {
				update_option( 'ec_option_use_global_tax', 0 );
				$wpdb->query( 'DELETE FROM ec_taxrate WHERE tax_by_all = 1' );
			}
			if ( isset( $set['canada'] ) ) {
				if ( is_array( $set['canada'] ) ) {
					update_option( 'ec_option_enable_easy_canada_tax', 1 );
					update_option( 'ec_option_canada_tax_options', array_merge( is_array( $now['canada_opts'] ) ? $now['canada_opts'] : array(), self::canada_options( $rec ) ) );
				} elseif ( 0 === $set['canada'] && $now['canada'] ) {
					update_option( 'ec_option_enable_easy_canada_tax', 0 );
				}
			}
			if ( isset( $set['tax_shipping'] ) ) {
				update_option( 'ec_option_collect_tax_on_shipping', (int) $set['tax_shipping'] );
			}
			if ( isset( $set['home_state'] ) ) {
				self::write_home_state( $set['home_state'], $now );
			}
			$plan = self::row_plan( $rec, $now );
			foreach ( $plan['remove_states'] as $row ) {
				$wpdb->delete( 'ec_taxrate', array( 'taxrate_id' => (int) $row->taxrate_id ), array( '%d' ) );
			}
			foreach ( $plan['fix_states'] as $row ) {
				$wpdb->update( 'ec_taxrate', array( 'country_code' => 'US' ), array( 'taxrate_id' => (int) $row->taxrate_id ), array( '%s' ), array( '%d' ) );
			}
			foreach ( $plan['remove_rates'] as $row ) {
				$wpdb->delete( 'ec_taxrate', array( 'taxrate_id' => (int) $row->taxrate_id ), array( '%d' ) );
			}

			self::save_choices( $rec['country'], $rec['choices'] );
			delete_option( self::KEPT );
			self::forget_caches();
			/**
			 * The recommended tax settings were applied.
			 *
			 * @since 6.0.5
			 * @param array $rec     The recommendation.
			 * @param array $changes What changed.
			 */
			do_action( 'wp_easycart_tax_setup_applied', $rec, $changes );
			return count( $changes );
		}

		/**
		 * Keep everything apply() may change ( undo() puts it back ).
		 *
		 * @param string $cc Country applied.
		 */
		private static function snapshot( $cc ) {
			global $wpdb;
			$options = array();
			foreach ( self::option_keys() as $key ) {
				$options[ $key ] = get_option( $key, null );
			}
			$rates = array();
			foreach ( (array) $wpdb->get_results( 'SELECT iso2_cnt, vat_rate_cnt FROM ec_country WHERE vat_rate_cnt <> 0' ) as $row ) {
				$rates[ (string) $row->iso2_cnt ] = (float) $row->vat_rate_cnt;
			}
			update_option(
				self::SNAPSHOT,
				array(
					'time'    => time(),
					'country' => strtoupper( (string) $cc ),
					'options' => $options,
					'rows'    => (array) $wpdb->get_results( 'SELECT * FROM ec_taxrate ORDER BY taxrate_id', ARRAY_A ),
					'rates'   => $rates,
				),
				false
			);
		}

		/**
		 * The snapshot apply() kept, while it is under a week old.
		 *
		 * @return array|null
		 */
		public static function undo_available() {
			$snap = get_option( self::SNAPSHOT, null );
			return ( is_array( $snap ) && isset( $snap['time'] ) && (int) $snap['time'] > time() - WEEK_IN_SECONDS ) ? $snap : null;
		}

		/**
		 * Put back what the last apply() changed.
		 *
		 * @return bool|WP_Error
		 */
		public static function undo() {
			global $wpdb;
			$snap = self::undo_available();
			if ( ! $snap ) {
				return new WP_Error( 'none', __( 'There is nothing to undo.', 'wp-easycart' ) );
			}
			foreach ( (array) $snap['options'] as $key => $value ) {
				if ( ! in_array( $key, self::option_keys(), true ) ) {
					continue;
				}
				if ( null === $value ) {
					delete_option( $key );
				} else {
					update_option( $key, $value );
				}
			}
			$wpdb->query( 'DELETE FROM ec_taxrate' );
			foreach ( (array) $snap['rows'] as $row ) {
				$wpdb->insert( 'ec_taxrate', (array) $row );
			}
			$wpdb->query( 'UPDATE ec_country SET vat_rate_cnt = 0 WHERE vat_rate_cnt <> 0' );
			foreach ( (array) $snap['rates'] as $code => $rate ) {
				$wpdb->query( $wpdb->prepare( 'UPDATE ec_country SET vat_rate_cnt = %f WHERE iso2_cnt = %s', (float) $rate, $code ) );
			}
			delete_option( self::SNAPSHOT );
			if ( class_exists( 'wp_easycart_vat' ) ) {
				wp_easycart_vat::forget();
			}
			self::forget_caches();
			/**
			 * The last recommended tax settings were undone.
			 *
			 * @since 6.0.5
			 * @param array $snap The snapshot put back.
			 */
			do_action( 'wp_easycart_tax_setup_undone', $snap );
			return true;
		}

		/**
		 * The recommendation's signature ( "Keep my settings" holds while it stays the same ).
		 *
		 * @param array $rec Recommendation.
		 * @return string
		 */
		public static function signature( $rec ) {
			return md5( wp_json_encode( array( self::VERSION, $rec['country'], isset( $rec['settings'] ) ? $rec['settings'] : array() ) ) );
		}

		/**
		 * "Keep my settings": hide the recommendation until it changes.
		 *
		 * @param array $rec Recommendation.
		 */
		public static function keep( $rec ) {
			update_option( self::KEPT, self::signature( $rec ), false );
		}

		/**
		 * Whether "Keep my settings" was pressed for this recommendation.
		 *
		 * @param array $rec Recommendation.
		 * @return bool
		 */
		public static function is_kept( $rec ) {
			return self::signature( $rec ) === (string) get_option( self::KEPT, '' );
		}

		/**
		 * Whether the store collects any tax ( a rate row or Canadian tax ).
		 *
		 * @return bool
		 */
		public static function tax_in_use() {
			global $wpdb;
			return ( self::table_ready() && (bool) $wpdb->get_var( 'SELECT taxrate_id FROM ec_taxrate LIMIT 1' ) ) || (int) get_option( 'ec_option_enable_easy_canada_tax' );
		}

		/* ------------------------------------------------------------------ */
		/* Helpers                                                              */
		/* ------------------------------------------------------------------ */

		/**
		 * The Canada grid's values the recommendation sets, for every customer role.
		 *
		 * @param array $rec Recommendation.
		 * @return array
		 */
		private static function canada_options( $rec ) {
			$pst = ( isset( $rec['settings']['canada']['pst'] ) && is_array( $rec['settings']['canada']['pst'] ) ) ? $rec['settings']['canada']['pst'] : array();
			$out = array();
			foreach ( self::canada() as $code => $info ) {
				foreach ( self::roles() as $role ) {
					$out[ 'ec_option_collect_' . $info['key'] . '_tax_' . $role ] = 1;
					$out[ 'ec_option_' . $info['key'] . '_tax_' . $role . '_gst' ] = $info['gst'];
					$out[ 'ec_option_' . $info['key'] . '_tax_' . $role . '_hst' ] = $info['hst'];
					$out[ 'ec_option_' . $info['key'] . '_tax_' . $role . '_pst' ] = in_array( $code, $pst, true ) ? $info['pst'] : 0;
				}
			}
			return $out;
		}

		/**
		 * Customer roles the Canada grid is kept for ( every ec_role but admin; checkout reads "shopper" for a guest ).
		 *
		 * @return array
		 */
		private static function roles() {
			global $wpdb;
			$roles = array( 'shopper' );
			foreach ( (array) $wpdb->get_col( "SELECT role_label FROM ec_role WHERE role_label != 'admin' ORDER BY role_id ASC" ) as $role ) {
				if ( '' !== (string) $role && ! in_array( $role, $roles, true ) ) {
					$roles[] = (string) $role;
				}
			}
			return $roles;
		}

		/**
		 * The VAT row ( as Settings › Taxes keeps it: ecst_tax_sync_vat() ).
		 *
		 * @param array $vat rate, by_country, included.
		 */
		private static function write_vat_row( $vat ) {
			global $wpdb;
			$by_country = (int) $vat['by_country'];
			$included   = (int) $vat['included'];
			$row_id     = (int) $wpdb->get_var( 'SELECT taxrate_id FROM ec_taxrate WHERE tax_by_vat = 1 OR tax_by_single_vat = 1 ORDER BY taxrate_id LIMIT 1' );
			if ( $row_id ) {
				$wpdb->query( $wpdb->prepare( 'UPDATE ec_taxrate SET tax_by_vat = %d, tax_by_single_vat = %d, vat_rate = %f, vat_included = %d, vat_added = %d WHERE taxrate_id = %d', $by_country, $by_country ? 0 : 1, (float) $vat['rate'], $included, $included ? 0 : 1, $row_id ) );
				$wpdb->query( $wpdb->prepare( 'DELETE FROM ec_taxrate WHERE ( tax_by_vat = 1 OR tax_by_single_vat = 1 ) AND taxrate_id <> %d', $row_id ) );
			} else {
				$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_taxrate( tax_by_vat, tax_by_single_vat, vat_rate, vat_included, vat_added ) VALUES( %d, %d, %f, %d, %d )', $by_country, $by_country ? 0 : 1, (float) $vat['rate'], $included, $included ? 0 : 1 ) );
			}
		}

		/**
		 * The home state's rate ( US ), saved with its country; removed for a state without sales tax.
		 *
		 * @param array $want code, rate.
		 * @param array $now  Current settings.
		 */
		private static function write_home_state( $want, $now ) {
			global $wpdb;
			$row = null;
			foreach ( $now['state_rows'] as $state_row ) {
				if ( strtoupper( (string) $state_row->state_code ) === $want['code'] && in_array( strtoupper( (string) $state_row->country_code ), array( 'US', '' ), true ) ) {
					$row = $state_row;
					break;
				}
			}
			if ( $want['rate'] <= 0 ) {
				if ( $row ) {
					$wpdb->delete( 'ec_taxrate', array( 'taxrate_id' => (int) $row->taxrate_id ), array( '%d' ) );
				}
				return;
			}
			if ( $row ) {
				$wpdb->update( 'ec_taxrate', array( 'state_rate' => (float) $want['rate'], 'country_code' => 'US' ), array( 'taxrate_id' => (int) $row->taxrate_id ), array( '%f', '%s' ), array( '%d' ) );
			} else {
				$wpdb->insert( 'ec_taxrate', array( 'tax_by_state' => 1, 'state_code' => $want['code'], 'country_code' => 'US', 'state_rate' => (float) $want['rate'] ), array( '%d', '%s', '%s', '%f' ) );
			}
		}

		/**
		 * Forget the cached tax rates, countries and VAT flags; tell listeners the rates changed.
		 */
		private static function forget_caches() {
			wp_cache_delete( 'wpeasycart-tax-rates' );
			wp_cache_delete( 'wpeasycart-countries' );
			wp_cache_delete( 'wpeasycart-config-vat-included' );
			wp_cache_delete( 'wpeasycart-config-vat-added' );
			do_action( 'wpeasycart_taxrate_updated', 0 );
		}

		/**
		 * Whether ec_taxrate can be read.
		 *
		 * @return bool
		 */
		private static function table_ready() {
			global $wpdb;
			return isset( $wpdb ) && is_object( $wpdb );
		}

		/**
		 * A percentage for people: 7.25%, 10%, 9.975%.
		 *
		 * @param float $rate Percent.
		 * @return string
		 */
		public static function pct( $rate ) {
			$rate = round( (float) $rate, 3 );
			$text = rtrim( rtrim( number_format( $rate, 3, '.', '' ), '0' ), '.' );
			if ( function_exists( 'number_format_i18n' ) ) {
				$decimals = ( false !== strpos( $text, '.' ) ) ? strlen( substr( $text, strpos( $text, '.' ) + 1 ) ) : 0;
				$text     = number_format_i18n( $rate, $decimals );
			}
			return $text . '%';
		}

		/**
		 * A country's name ( Countries & Regions ), else its code.
		 *
		 * @param string $cc Country.
		 * @return string
		 */
		public static function country_name( $cc ) {
			global $wpdb;
			static $names = null;
			if ( null === $names ) {
				$names = array();
				foreach ( (array) $wpdb->get_results( 'SELECT iso2_cnt, name_cnt FROM ec_country' ) as $row ) {
					$names[ strtoupper( (string) $row->iso2_cnt ) ] = html_entity_decode( (string) $row->name_cnt, ENT_QUOTES, 'UTF-8' );
				}
			}
			$cc = strtoupper( (string) $cc );
			return isset( $names[ $cc ] ) ? $names[ $cc ] : $cc;
		}

		/**
		 * A state or province's name ( Countries & Regions ), else its code.
		 *
		 * @param string $cc    Country.
		 * @param string $state State code.
		 * @return string
		 */
		public static function state_name( $cc, $state ) {
			global $wpdb;
			$name = $wpdb->get_var( $wpdb->prepare( 'SELECT s.name_sta FROM ec_state s JOIN ec_country c ON c.id_cnt = s.idcnt_sta WHERE c.iso2_cnt = %s AND s.code_sta = %s LIMIT 1', strtoupper( (string) $cc ), strtoupper( (string) $state ) ) );
			return ( null !== $name && '' !== $name ) ? html_entity_decode( (string) $name, ENT_QUOTES, 'UTF-8' ) : strtoupper( (string) $state );
		}

		/**
		 * The states or provinces a store can choose ( code => name ).
		 *
		 * @param string $cc Country.
		 * @return array
		 */
		public static function regions( $cc ) {
			$cc   = strtoupper( (string) $cc );
			$list = ( 'US' === $cc ) ? self::us_states() : ( ( 'CA' === $cc ) ? self::canada() : array() );
			$out  = array();
			foreach ( array_keys( $list ) as $code ) {
				$out[ $code ] = self::state_name( $cc, $code );
			}
			asort( $out );
			return $out;
		}
	}

endif;
