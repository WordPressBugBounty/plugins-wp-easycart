<?php
/**
 * WP EasyCart store policies for search engines and AI assistants ( 6.0.2 ).
 *
 * The store's return policy ( Settings › Search & AI › Returns ) and its shipping, built from the store's own rate tables
 * ( Settings › Shipping rates ) plus handling and delivery times, as schema.org MerchantReturnPolicy and ShippingService.
 * Google reads both once for the whole store from the organization markup. With Yoast SEO or Rank Math active, and the
 * switch on, they are added to that plugin's organization; otherwise EasyCart prints an OnlineStore on the home page and
 * the store page. Every product offer names the store as its seller ( wp_easycart_product_schema::seller() ).
 *
 * @package wp-easycart
 * @since   6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_store_schema' ) ) :

	/**
	 * Store policy markup ( static ).
	 *
	 * @since 6.0.2
	 */
	final class wp_easycart_store_schema {

		/** Google reads at most this many countries for one policy. */
		const COUNTRY_LIMIT = 50;

		/** A shipping-to list longer than this was never trimmed: don't describe it as the store's markets. */
		const SHIP_TO_LIMIT = 25;

		/** Countries where Google reads states in a shipping destination. */
		const REGION_COUNTRIES = array( 'US', 'AU', 'JP' );

		/**
		 * The shipping() result for this request.
		 *
		 * @var array|null
		 */
		private static $shipping = null;

		/**
		 * Register hooks.
		 */
		public static function init() {
			add_action( 'wp_head', array( __CLASS__, 'print_head' ), 30 );
			add_filter( 'wpseo_schema_graph', array( __CLASS__, 'yoast_graph' ), 20 );
			add_filter( 'rank_math/json_ld', array( __CLASS__, 'rank_math_graph' ), 99 );
		}

		// ------------------------------------------------------------------
		// Identity.
		// ------------------------------------------------------------------

		/**
		 * The organization every offer points to: the SEO plugin's when the policies are added to it, else EasyCart's.
		 *
		 * @return string
		 */
		public static function id() {
			$plugin = self::policy_plugin();
			if ( 'yoast' === $plugin || 'rankmath' === $plugin ) {
				return trailingslashit( home_url() ) . '#organization';
			}
			return trailingslashit( home_url() ) . '#store';
		}

		/**
		 * The SEO plugin that carries the policies ( yoast | rankmath ), or '' when EasyCart prints its own.
		 *
		 * @return string
		 */
		public static function policy_plugin() {
			if ( '0' === (string) get_option( 'ec_option_seo_plugin_policies', '1' ) || ! class_exists( 'wp_easycart_product_schema' ) ) {
				return '';
			}
			$plugin = wp_easycart_product_schema::seo_plugin();
			if ( 'yoast' === $plugin ) {
				$type = ( class_exists( 'WPSEO_Options' ) && method_exists( 'WPSEO_Options', 'get' ) ) ? (string) WPSEO_Options::get( 'company_or_person', 'company' ) : 'company';
				return ( 'company' === $type ) ? 'yoast' : '';
			}
			if ( 'rankmath' === $plugin ) {
				$type = ( class_exists( '\RankMath\Helper' ) && method_exists( '\RankMath\Helper', 'get_settings' ) ) ? (string) \RankMath\Helper::get_settings( 'titles.knowledgegraph_type' ) : 'company';
				return ( 'person' !== $type ) ? 'rankmath' : '';
			}
			return '';
		}

		/**
		 * Is this the home page or the store page ( where EasyCart prints its own OnlineStore )?
		 *
		 * @return bool
		 */
		private static function is_store_home() {
			if ( function_exists( 'is_front_page' ) && is_front_page() ) {
				return true;
			}
			$store_page = (int) get_option( 'ec_option_storepage' );
			return $store_page > 0 && function_exists( 'is_page' ) && is_page( $store_page );
		}

		// ------------------------------------------------------------------
		// Countries.
		// ------------------------------------------------------------------

		/**
		 * The countries the store sells to: the Returns setting, else the countries in shipping zones, else a short
		 * ship-to list.
		 *
		 * @return string[] ISO 3166-1 alpha-2.
		 */
		public static function countries() {
			$saved = self::country_list( (string) get_option( 'ec_option_returns_countries', '' ) );
			if ( $saved ) {
				return $saved;
			}
			$zones = self::zone_countries();
			if ( $zones ) {
				return array_slice( $zones, 0, self::COUNTRY_LIMIT );
			}
			global $wpdb;
			$ship_to = (array) $wpdb->get_col( 'SELECT iso2_cnt FROM ec_country WHERE ship_to_active = 1 ORDER BY sort_order, name_cnt' );
			$ship_to = self::country_list( implode( ',', $ship_to ) );
			return ( count( $ship_to ) <= self::SHIP_TO_LIMIT ) ? $ship_to : array();
		}

		/**
		 * Clean a comma-separated country list.
		 *
		 * @param string $raw Codes.
		 * @return string[]
		 */
		public static function country_list( $raw ) {
			$out = array();
			foreach ( explode( ',', (string) $raw ) as $code ) {
				$code = strtoupper( trim( $code ) );
				if ( preg_match( '/^[A-Z]{2}$/', $code ) && ! in_array( $code, $out, true ) ) {
					$out[] = $code;
				}
			}
			return array_slice( $out, 0, self::COUNTRY_LIMIT );
		}

		/**
		 * Countries named in shipping zones.
		 *
		 * @return string[]
		 */
		public static function zone_countries() {
			global $wpdb;
			return self::country_list( implode( ',', (array) $wpdb->get_col( 'SELECT DISTINCT iso2_cnt FROM ec_zone_to_location ORDER BY iso2_cnt' ) ) );
		}

		// ------------------------------------------------------------------
		// Returns.
		// ------------------------------------------------------------------

		/**
		 * The returns page address.
		 *
		 * @return string
		 */
		public static function returns_link() {
			$page = (int) get_option( 'ec_option_returns_page', 0 );
			if ( $page > 0 && 'publish' === get_post_status( $page ) ) {
				return esc_url_raw( (string) get_permalink( $page ) );
			}
			return '';
		}

		/**
		 * MerchantReturnPolicy from the Returns settings ( null until the owner picks a policy ).
		 *
		 * @return array|null
		 */
		public static function return_policy() {
			$policy = (string) get_option( 'ec_option_returns_policy', '' );
			if ( ! in_array( $policy, array( 'finite', 'unlimited', 'none' ), true ) ) {
				return null;
			}
			$countries = self::countries();
			$link      = self::returns_link();
			/* 6.0.2: Google requires applicableCountry on a return policy, so none is shared without countries. */
			if ( ! $countries ) {
				return null;
			}
			$categories = array(
				'finite'    => 'https://schema.org/MerchantReturnFiniteReturnWindow',
				'unlimited' => 'https://schema.org/MerchantReturnUnlimitedWindow',
				'none'      => 'https://schema.org/MerchantReturnNotPermitted',
			);
			$out = array( '@type' => 'MerchantReturnPolicy' );
			if ( $countries ) {
				$out['applicableCountry'] = ( 1 === count( $countries ) ) ? $countries[0] : $countries;
			}
			$out['returnPolicyCategory'] = $categories[ $policy ];
			if ( 'finite' === $policy ) {
				$out['merchantReturnDays'] = max( 1, (int) get_option( 'ec_option_returns_days', 30 ) );
			}
			if ( 'none' !== $policy ) {
				$methods = array();
				$map     = array(
					'mail'  => 'https://schema.org/ReturnByMail',
					'store' => 'https://schema.org/ReturnInStore',
				);
				foreach ( explode( ',', (string) get_option( 'ec_option_returns_methods', 'mail' ) ) as $method ) {
					$method = trim( $method );
					if ( isset( $map[ $method ] ) ) {
						$methods[] = $map[ $method ];
					}
				}
				if ( $methods ) {
					$out['returnMethod'] = ( 1 === count( $methods ) ) ? $methods[0] : $methods;
				}
				$fees = (string) get_option( 'ec_option_returns_fees', 'free' );
				if ( 'customer' === $fees ) {
					$out['returnFees'] = 'https://schema.org/ReturnFeesCustomerResponsibility';
				} elseif ( 'flat' === $fees && (float) get_option( 'ec_option_returns_fee_amount', 0 ) > 0 ) {
					$out['returnFees']               = 'https://schema.org/ReturnShippingFees';
					$out['returnShippingFeesAmount'] = array(
						'@type'    => 'MonetaryAmount',
						'value'    => round( (float) get_option( 'ec_option_returns_fee_amount', 0 ), 2 ),
						'currency' => self::currency(),
					);
				} elseif ( 'free' === $fees ) {
					$out['returnFees'] = 'https://schema.org/FreeReturn';
				}
				$refunds = array();
				$map     = array(
					'full'     => 'https://schema.org/FullRefund',
					'exchange' => 'https://schema.org/ExchangeRefund',
					'credit'   => 'https://schema.org/StoreCreditRefund',
				);
				foreach ( explode( ',', (string) get_option( 'ec_option_returns_refund', 'full' ) ) as $refund ) {
					$refund = trim( $refund );
					if ( isset( $map[ $refund ] ) ) {
						$refunds[] = $map[ $refund ];
					}
				}
				if ( $refunds ) {
					$out['refundType'] = ( 1 === count( $refunds ) ) ? $refunds[0] : $refunds;
				}
			}
			if ( '' !== $link ) {
				$out['merchantReturnLink'] = $link;
			}
			return apply_filters( 'wp_easycart_store_return_policy_schema', $out );
		}

		// ------------------------------------------------------------------
		// Shipping.
		// ------------------------------------------------------------------

		/**
		 * The store's currency code.
		 *
		 * @return string
		 */
		private static function currency() {
			return class_exists( 'wp_easycart_product_schema' ) ? wp_easycart_product_schema::currency() : 'USD';
		}

		/**
		 * A "min-max" day range ( "2-5", "3" ).
		 *
		 * @param string $raw Typed value.
		 * @return int[]|null min, max.
		 */
		public static function day_range( $raw ) {
			if ( ! preg_match( '/^\s*(\d{1,3})\s*(?:[-–to]+\s*(\d{1,3}))?\s*$/u', (string) $raw, $match ) ) {
				return null;
			}
			$min = (int) $match[1];
			$max = isset( $match[2] ) && '' !== $match[2] ? (int) $match[2] : $min;
			return array( min( $min, $max ), max( $min, $max ) );
		}

		/**
		 * A ServicePeriod of days.
		 *
		 * @param int[]  $range  day_range().
		 * @param string $cutoff HH:MM, handling time only.
		 * @return array
		 */
		private static function period( $range, $cutoff = '' ) {
			$out = array( '@type' => 'ServicePeriod' );
			if ( '' !== $cutoff && preg_match( '/^([01]\d|2[0-3]):([0-5]\d)$/', $cutoff ) ) {
				$offset            = function_exists( 'wp_date' ) ? wp_date( 'P' ) : '+00:00';
				$out['cutoffTime'] = $cutoff . ':00' . $offset;
			}
			$out['duration'] = array(
				'@type'    => 'QuantitativeValue',
				'minValue' => $range[0],
				'maxValue' => $range[1],
				'unitCode' => 'DAY',
			);
			return $out;
		}

		/**
		 * Where a zone ships to, as DefinedRegions ( zone 0 = the store's countries ).
		 *
		 * @param int $zone_id Zone.
		 * @return array regions, and skipped ( parts of a country Google can't read ).
		 */
		public static function zone_regions( $zone_id ) {
			$out = array(
				'regions' => array(),
				'skipped' => false,
			);
			if ( 0 === (int) $zone_id ) {
				foreach ( self::countries() as $country ) {
					$out['regions'][] = array(
						'@type'          => 'DefinedRegion',
						'addressCountry' => $country,
					);
				}
				return $out;
			}
			global $wpdb;
			$rows      = $wpdb->get_results( $wpdb->prepare( 'SELECT iso2_cnt, code_sta FROM ec_zone_to_location WHERE zone_id = %d', (int) $zone_id ) );
			$countries = array();
			foreach ( (array) $rows as $row ) {
				$country = strtoupper( trim( (string) $row->iso2_cnt ) );
				$state   = strtoupper( trim( (string) $row->code_sta ) );
				if ( ! preg_match( '/^[A-Z]{2}$/', $country ) ) {
					continue;
				}
				if ( '' === $state ) {
					$countries[ $country ] = true; /* the whole country */
				} elseif ( in_array( $country, self::REGION_COUNTRIES, true ) ) {
					if ( ! isset( $countries[ $country ] ) || true !== $countries[ $country ] ) {
						$countries[ $country ]   = isset( $countries[ $country ] ) ? $countries[ $country ] : array();
						$countries[ $country ][] = $state;
					}
				} else {
					$out['skipped'] = true;
				}
			}
			foreach ( $countries as $country => $states ) {
				$region = array(
					'@type'          => 'DefinedRegion',
					'addressCountry' => $country,
				);
				if ( is_array( $states ) ) {
					$states                  = array_values( array_unique( $states ) );
					$region['addressRegion'] = ( 1 === count( $states ) ) ? $states[0] : $states;
				}
				$out['regions'][] = $region;
			}
			return $out;
		}

		/**
		 * Zone names.
		 *
		 * @return array zone_id => name.
		 */
		public static function zone_names() {
			global $wpdb;
			$out = array();
			foreach ( (array) $wpdb->get_results( 'SELECT zone_id, zone_name FROM ec_zone ORDER BY zone_name' ) as $row ) {
				$out[ (int) $row->zone_id ] = (string) $row->zone_name;
			}
			return $out;
		}

		/**
		 * The store's shipping: ShippingServices built from the rate tables, plus a per-zone summary for the admin.
		 *
		 * @return array method, services ( markup ), zones ( zone_id => name, listed, reason, rows ).
		 */
		public static function shipping() {
			if ( null !== self::$shipping ) {
				return self::$shipping;
			}
			global $wpdb;
			$out = array(
				'method'   => 'none',
				'services' => array(),
				'zones'    => array(),
			);
			if ( ! get_option( 'ec_option_use_shipping' ) ) {
				self::$shipping = $out;
				return $out;
			}
			$setting       = ( isset( $GLOBALS['ec_setting'] ) && is_object( $GLOBALS['ec_setting'] ) && isset( $GLOBALS['ec_setting']->setting_row ) && is_object( $GLOBALS['ec_setting']->setting_row ) ) ? $GLOBALS['ec_setting']->setting_row : $wpdb->get_row( 'SELECT shipping_method, shipping_handling_rate FROM ec_setting WHERE setting_id = 1' );
			$method        = $setting ? (string) apply_filters( 'wp_easycart_shipping_method', $setting->shipping_method ) : '';
			$handling      = $setting ? (float) $setting->shipping_handling_rate : 0.0;
			$out['method'] = $method;
			$flags         = array(
				'price'      => 'is_price_based',
				'weight'     => 'is_weight_based',
				'quantity'   => 'is_quantity_based',
				'percentage' => 'is_percentage_based',
				'method'     => 'is_method_based',
			);
			$names = self::zone_names();
			$rows  = (array) $wpdb->get_results( 'SELECT * FROM ec_shippingrate ORDER BY zone_id ASC, trigger_rate ASC, shipping_order ASC, shippingrate_id ASC' );

			if ( ! isset( $flags[ $method ] ) ) {
				/* Live carrier rates ( and Fraktjakt ) change with every order: nothing fixed to describe. */
				$zones = array();
				foreach ( $rows as $row ) {
					if ( ! $row->is_price_based && ! $row->is_weight_based && ! $row->is_method_based && ! $row->is_quantity_based && ! $row->is_percentage_based ) {
						$zones[ (int) $row->zone_id ] = true;
					}
				}
				if ( ! $zones ) {
					$zones = array( 0 => true );
				}
				foreach ( array_keys( $zones ) as $zone_id ) {
					$out['zones'][ $zone_id ] = array(
						'name'   => isset( $names[ $zone_id ] ) ? $names[ $zone_id ] : '',
						'listed' => false,
						'reason' => 'live',
						'rows'   => array(),
					);
				}
				self::$shipping = $out;
				return $out;
			}

			$by_zone = array();
			foreach ( $rows as $row ) {
				if ( ! empty( $row->{ $flags[ $method ] } ) ) {
					$by_zone[ (int) $row->zone_id ][] = $row;
				}
			}
			$currency  = self::currency();
			$unit_map  = array(
				'lbs' => 'LBR',
				'lb'  => 'LBR',
				'kg'  => 'KGM',
				'kgs' => 'KGM',
				'oz'  => 'ONZ',
				'g'   => 'GRM',
			);
			$unit      = strtolower( (string) get_option( 'ec_option_weight', 'lbs' ) );
			$unit_code = isset( $unit_map[ $unit ] ) ? $unit_map[ $unit ] : 'LBR';
			$default   = self::day_range( (string) get_option( 'ec_option_shipping_transit_days', '' ) );
			$services  = array();

			foreach ( $by_zone as $zone_id => $zone_rows ) {
				$zone    = array(
					'name'   => isset( $names[ $zone_id ] ) ? $names[ $zone_id ] : '',
					'listed' => false,
					'reason' => '',
					'rows'   => array(),
				);
				$regions = self::zone_regions( $zone_id );
				if ( ! $regions['regions'] ) {
					$zone['reason']           = ( 0 === $zone_id ) ? 'countries' : 'regions';
					$out['zones'][ $zone_id ] = $zone;
					continue;
				}
				if ( 'percentage' === $method && $handling > 0 ) {
					$zone['reason']           = 'percentage_handling';
					$out['zones'][ $zone_id ] = $zone;
					continue;
				}
				$transit = self::day_range( (string) get_option( 'ec_option_shipping_transit_days_z' . $zone_id, '' ) );
				if ( ! $transit ) {
					$transit = $default;
				}
				$destination = ( 1 === count( $regions['regions'] ) ) ? $regions['regions'][0] : $regions['regions'];
				$base        = array(
					'@type'               => 'ShippingConditions',
					'shippingDestination' => $destination,
				);
				if ( $transit ) {
					$base['transitTime'] = self::period( $transit );
				}

				if ( 'method' === $method ) {
					foreach ( $zone_rows as $row ) {
						/* 6.0.2: through the language conversion, as the cart shows it ( multi-language labels carry markers ). */
						$label = trim( html_entity_decode( wp_strip_all_tags( function_exists( 'wp_easycart_language' ) ? (string) wp_easycart_language()->convert_text( (string) $row->shipping_label ) : (string) $row->shipping_label ), ENT_QUOTES, 'UTF-8' ) );
						$label = ( '' !== $label ) ? $label : __( 'Shipping', 'wp-easycart' );
						$rate  = round( (float) $row->shipping_rate + $handling, 2 );
						$free  = (float) $row->free_shipping_at;
						if ( $free >= 0 && $free < 0.01 ) {
							$rate = 0.0; /* free at any order value */
							$free = -1;
						}
						$first = $base;
						if ( $free > 0 ) {
							$first['orderValue'] = array(
								'@type'    => 'MonetaryAmount',
								'minValue' => 0,
								'maxValue' => round( $free - 0.01, 2 ),
								'currency' => $currency,
							);
						}
						$first['shippingRate']          = self::money( $rate, $currency );
						$services[ $label ]['items'][]  = $first;
						$zone['rows'][]                 = array(
							'label' => $label,
							'rate'  => $rate,
							'free'  => ( $free > 0 ) ? $free : null,
						);
						if ( $free > 0 ) {
							$free_condition                 = $base;
							$free_condition['orderValue']   = array(
								'@type'    => 'MonetaryAmount',
								'minValue' => round( $free, 2 ),
								'currency' => $currency,
							);
							$free_condition['shippingRate'] = self::money( 0, $currency );
							$services[ $label ]['items'][]  = $free_condition;
						}
					}
					$zone['listed']           = true;
					$out['zones'][ $zone_id ] = $zone;
					continue;
				}

				/* Tables: each row starts a band at its trigger, up to the next row's trigger. */
				$count = count( $zone_rows );
				if ( $count && (float) $zone_rows[0]->trigger_rate > 0 ) {
					/* Below the lowest trigger nothing matches, and the store charges nothing but handling. */
					array_unshift(
						$zone_rows,
						(object) array(
							'trigger_rate'  => 0,
							'shipping_rate' => 0,
						)
					);
					++$count;
				}
				$label = __( 'Standard shipping', 'wp-easycart' );
				for ( $i = 0; $i < $count; $i++ ) {
					$min       = (float) $zone_rows[ $i ]->trigger_rate;
					$next      = ( $i + 1 < $count ) ? (float) $zone_rows[ $i + 1 ]->trigger_rate : null;
					$condition = $base;
					if ( 'price' === $method || 'percentage' === $method ) {
						$condition['orderValue'] = array(
							'@type'    => 'MonetaryAmount',
							'minValue' => round( $min, 2 ),
							'currency' => $currency,
						);
						if ( null !== $next ) {
							$condition['orderValue']['maxValue'] = round( $next - 0.01, 2 );
						}
					} elseif ( 'weight' === $method ) {
						$condition['weight'] = array(
							'@type'    => 'QuantitativeValue',
							'minValue' => round( $min, 3 ),
							'unitCode' => $unit_code,
						);
						if ( null !== $next ) {
							$condition['weight']['maxValue'] = round( $next - 0.001, 3 );
						}
					} else {
						$condition['numItems'] = array(
							'@type'    => 'QuantitativeValue',
							'minValue' => (int) $min,
						);
						if ( null !== $next ) {
							$condition['numItems']['maxValue'] = max( (int) $min, (int) $next - 1 );
						}
					}
					if ( 'percentage' === $method ) {
						$condition['shippingRate'] = array(
							'@type'           => 'ShippingRateSettings',
							'orderPercentage' => round( (float) $zone_rows[ $i ]->shipping_rate / 100, 4 ),
						);
						$rate                      = (float) $zone_rows[ $i ]->shipping_rate;
					} else {
						$rate                      = round( (float) $zone_rows[ $i ]->shipping_rate + $handling, 2 );
						$condition['shippingRate'] = self::money( $rate, $currency );
					}
					$services[ $label ]['items'][] = $condition;
					$zone['rows'][]                = array(
						'min'  => $min,
						'max'  => $next,
						'rate' => $rate,
					);
				}
				$zone['listed']           = true;
				$out['zones'][ $zone_id ] = $zone;
			}

			$handling_days = self::day_range( (string) get_option( 'ec_option_shipping_handling_days', '' ) );
			$cutoff        = (string) get_option( 'ec_option_shipping_cutoff', '' );
			foreach ( $services as $name => $service ) {
				$item = array(
					'@type'              => 'ShippingService',
					'name'               => $name,
					'fulfillmentType'    => 'https://schema.org/FulfillmentTypeDelivery',
					'shippingConditions' => $service['items'],
				);
				if ( $handling_days ) {
					$item['handlingTime'] = self::period( $handling_days, $cutoff );
				}
				$out['services'][] = $item;
			}
			self::$shipping = $out;
			return $out;
		}

		/**
		 * A MonetaryAmount.
		 *
		 * @param float  $value    Amount.
		 * @param string $currency Code.
		 * @return array
		 */
		private static function money( $value, $currency ) {
			return array(
				'@type'    => 'MonetaryAmount',
				'value'    => round( (float) $value, 2 ),
				'currency' => $currency,
			);
		}

		// ------------------------------------------------------------------
		// Output.
		// ------------------------------------------------------------------

		/**
		 * The policy properties to add to an organization.
		 *
		 * @return array hasMerchantReturnPolicy / hasShippingService, or empty.
		 */
		public static function policies() {
			$out     = array();
			$returns = self::return_policy();
			if ( $returns ) {
				$out['hasMerchantReturnPolicy'] = $returns;
			}
			$shipping = self::shipping();
			if ( $shipping['services'] ) {
				$out['hasShippingService'] = ( 1 === count( $shipping['services'] ) ) ? $shipping['services'][0] : $shipping['services'];
			}
			return (array) apply_filters( 'wp_easycart_store_policies_schema', $out );
		}

		/**
		 * EasyCart's own OnlineStore ( null when there is no policy to share ).
		 *
		 * @return array|null
		 */
		public static function build() {
			$policies = self::policies();
			if ( ! $policies ) {
				return null;
			}
			$store = array(
				'@context' => 'https://schema.org',
				'@type'    => 'OnlineStore',
				'@id'      => trailingslashit( home_url() ) . '#store',
				'name'     => wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
				'url'      => trailingslashit( home_url() ),
			);
			$logo = (string) get_option( 'ec_option_email_logo', '' );
			if ( preg_match( '#^https?://#i', $logo ) ) {
				$store['logo'] = esc_url_raw( $logo );
			}
			return apply_filters( 'wp_easycart_store_schema', array_merge( $store, $policies ) );
		}

		/**
		 * The OnlineStore on the home page and the store page, unless an SEO plugin carries the policies.
		 */
		public static function print_head() {
			if ( ! class_exists( 'wp_easycart_product_schema' ) || ! wp_easycart_product_schema::enabled() || '' !== self::policy_plugin() || ! self::is_store_home() ) {
				return;
			}
			$store = self::build();
			if ( $store ) {
				echo "<script type=\"application/ld+json\">\n" . wp_easycart_product_schema::json( $store ) . "\n</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON from wp_json_encode() with JSON_HEX_TAG.
			}
		}

		/**
		 * Yoast SEO: add the policies to its organization.
		 *
		 * @param array $graph Yoast's schema pieces.
		 * @return array
		 */
		public static function yoast_graph( $graph ) {
			if ( 'yoast' !== self::policy_plugin() || ! is_array( $graph ) || ! wp_easycart_product_schema::enabled() ) {
				return $graph;
			}
			return self::merge_into( $graph, trailingslashit( home_url() ) . '#organization' );
		}

		/**
		 * Rank Math: add the policies to its organization.
		 *
		 * @param array $data Rank Math's entities.
		 * @return array
		 */
		public static function rank_math_graph( $data ) {
			if ( 'rankmath' !== self::policy_plugin() || ! is_array( $data ) || ! wp_easycart_product_schema::enabled() ) {
				return $data;
			}
			return self::merge_into( $data, trailingslashit( home_url() ) . '#organization' );
		}

		/**
		 * Add the policies to the organization entity with this @id.
		 *
		 * @param array  $entities Graph entities.
		 * @param string $org_id   Organization @id.
		 * @return array
		 */
		private static function merge_into( $entities, $org_id ) {
			$policies = self::policies();
			if ( ! $policies ) {
				return $entities;
			}
			foreach ( $entities as $key => $entity ) {
				if ( is_array( $entity ) && isset( $entity['@id'] ) && $org_id === $entity['@id'] ) {
					$entities[ $key ] = array_merge( $entity, $policies );
					return $entities;
				}
			}
			/* 6.0.2: the plugin describes no organization on this page ( Yoast leaves it out until the company name and logo
			   are set ). EasyCart's own store goes into the graph under that @id, so the policies are not lost and every
			   offer's seller points to something. */
			$store = self::build();
			if ( ! $store ) {
				return $entities;
			}
			unset( $store['@context'] );
			$store['@id'] = $org_id;
			if ( array_values( $entities ) === $entities ) {
				$entities[] = $store;
			} else {
				$entities['wpeasycart_store'] = $store;
			}
			return $entities;
		}
	}

	wp_easycart_store_schema::init();

endif;
