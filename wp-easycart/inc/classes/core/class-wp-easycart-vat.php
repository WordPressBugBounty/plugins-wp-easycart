<?php
/**
 * WP EasyCart — EU VAT rates ( 6.0.5 ).
 *
 * The EU countries' standard VAT rates and filling them into Countries & Regions ( ec_country.vat_rate_cnt, read when VAT varies by
 * country ). Used by the setup wizard ( a store in the EU charges its own rate across the EU below the €10,000 distance-sales
 * threshold, each country's own rate above it or under the One-Stop Shop ) and by Settings › Taxes › VAT › Fill in EU VAT rates.
 * Before 6.0.5 the wizard set a rate for the store's own country only, and VAT by country charged every other EU consumer nothing.
 *
 * The tax's name ( admin review M5 ): name() is GST in Australia, New Zealand, Singapore, Malaysia and India, else VAT, unless the
 * store chose one ( ec_option_vat_name, Settings › Taxes › VAT ). With GST, the storefront's, the receipts' and the documents' phrases
 * say GST where they said VAT ( language_text() on wp_easycart_language_text ), the admin's VAT section and order totals say GST, and an
 * Australian store's invoice PDF is titled "Tax invoice" ( pdf_title() ).
 *
 * @package WP_EasyCart
 * @since   6.0.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_vat' ) ) :

	/**
	 * EU VAT rates.
	 */
	class wp_easycart_vat {

		/** When the rates below were last checked against the European Commission's list ( Your Europe ). */
		const CHECKED = '2026-07';

		/** The EU distance-sales threshold in euros ( a year, all EU countries together ). */
		const OSS_THRESHOLD = 10000;

		/**
		 * Each EU country's standard VAT rate, percent. Greece is GR here, as in Countries & Regions ( EL in VAT numbers ).
		 *
		 * @return array ISO code => rate.
		 */
		public static function eu() {
			$rates = array(
				'AT' => 20,
				'BE' => 21,
				'BG' => 20,
				'CY' => 19,
				'CZ' => 21,
				'DE' => 19,
				'DK' => 25,
				'EE' => 24,
				'ES' => 21,
				'FI' => 25.5,
				'FR' => 20,
				'GR' => 24,
				'HR' => 25,
				'HU' => 27,
				'IE' => 23,
				'IT' => 22,
				'LT' => 21,
				'LU' => 17,
				'LV' => 21,
				'MT' => 18,
				'NL' => 21,
				'PL' => 23,
				'PT' => 23,
				'RO' => 21,
				'SE' => 25,
				'SI' => 22,
				'SK' => 23,
			);
			/**
			 * The EU standard VAT rates WP EasyCart fills in.
			 *
			 * @since 6.0.5
			 * @param array $rates ISO code => percent.
			 */
			return (array) apply_filters( 'wp_easycart_eu_vat_rates', $rates );
		}

		/**
		 * Is the country in the EU ( GR or EL for Greece )?
		 *
		 * @param string $iso2 Country code.
		 * @return bool
		 */
		public static function in_eu( $iso2 ) {
			$iso2 = strtoupper( trim( (string) $iso2 ) );
			return array_key_exists( 'EL' === $iso2 ? 'GR' : $iso2, self::eu() );
		}

		/* ------------------------------------------------------------------ */
		/* VAT numbers ( 6.0.5, admin review H8 )                              */
		/* ------------------------------------------------------------------ */

		/**
		 * A VAT number as the EU writes it: letters and digits only, upper case.
		 *
		 * @param string $number Number as entered.
		 * @return string
		 */
		public static function clean( $number ) {
			return strtoupper( (string) preg_replace( '/[^A-Za-z0-9]/', '', trim( (string) $number ) ) );
		}

		/**
		 * The country a VAT number belongs to, from its prefix: GR for Greece's EL, XI ( Northern Ireland ) as given, '' without one.
		 *
		 * @param string $number Number.
		 * @return string
		 */
		public static function number_country( $number ) {
			$number = self::clean( $number );
			if ( ! preg_match( '/^([A-Z]{2})[0-9A-Z]{2,13}$/', $number, $m ) ) {
				return '';
			}
			return ( 'EL' === $m[1] ) ? 'GR' : $m[1];
		}

		/**
		 * WP EasyCart Connect's HMRC relay ( docs/hmrc-vat.md in the connect repository ).
		 *
		 * @since 6.0.5
		 * @var string
		 */
		const HMRC_RELAY = 'https://connect.wpeasycart.com/hmrc-v1/api.php';

		/**
		 * Is the number valid? valid | invalid | unknown. EU numbers ( and XI, Northern Ireland ) ask the EU's VIES service; UK ( GB )
		 * numbers ask HMRC through WP EasyCart Connect ( 6.0.5, hmrc_check() ); anything else, or no answer, is unknown. Answers are
		 * kept: valid a week, invalid a day, no answer ten minutes ( a request waits at most six seconds ). Only the number is sent
		 * ( readme, External services ).
		 *
		 * @param string $number Number.
		 * @return string
		 */
		public static function number_status( $number ) {
			$number  = self::clean( $number );
			$country = self::number_country( $number );
			if ( '' === $country || ( ! self::in_eu( $country ) && 'XI' !== $country && 'GB' !== $country ) ) {
				return 'unknown';
			}
			/**
			 * Answer a VAT number check without asking VIES ( valid | invalid | unknown ), e.g. from another service.
			 *
			 * @since 6.0.5
			 * @param string|null $status Null asks VIES.
			 * @param string      $number The number.
			 */
			$status = apply_filters( 'wp_easycart_vat_number_status', null, $number );
			if ( in_array( $status, array( 'valid', 'invalid', 'unknown' ), true ) ) {
				return $status;
			}
			if ( 'GB' === $country ) {
				$check = self::hmrc_check( $number );
				return $check['status'];
			}
			$key    = 'wpec_vies_' . md5( $number );
			$cached = get_transient( $key );
			if ( in_array( $cached, array( 'valid', 'invalid', 'unknown' ), true ) ) {
				return $cached;
			}
			$code     = ( 'GR' === $country ) ? 'EL' : $country;
			$url      = 'https://ec.europa.eu/taxation_customs/vies/rest-api/ms/' . rawurlencode( $code ) . '/vat/' . rawurlencode( substr( $number, 2 ) );
			/* keep-alive: with WordPress's Connection: close the Commission's servers drop the socket without a TLS goodbye, which
			   OpenSSL 3 reports as an error ( cURL 56, unexpected eof ). */
			$response = wp_remote_get( $url, array( 'timeout' => 6, 'headers' => array( 'Accept' => 'application/json', 'Connection' => 'keep-alive' ) ) );
			$status   = 'unknown';
			if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
				$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
				if ( is_array( $body ) && isset( $body['isValid'] ) ) {
					if ( true === $body['isValid'] ) {
						$status = 'valid';
					} elseif ( ! isset( $body['userError'] ) || 'INVALID' === $body['userError'] || 'VALID' === $body['userError'] ) {
						$status = 'invalid'; /* a definite no; MS_UNAVAILABLE, TIMEOUT and the like stay unknown */
					}
				}
			}
			set_transient( $key, $status, ( 'valid' === $status ) ? WEEK_IN_SECONDS : ( ( 'invalid' === $status ) ? DAY_IN_SECONDS : 10 * MINUTE_IN_SECONDS ) );
			return $status;
		}

		/**
		 * A UK ( GB ) VAT number checked with HMRC's Check a UK VAT number service, through WP EasyCart Connect ( which holds the HMRC
		 * application HMRC requires; only the number is sent ). GB and 9 or 12 digits; GBGD / GBHA numbers ( government departments,
		 * health authorities ) are not in HMRC's check and stay unknown. Answers are kept as number_status() keeps VIES's; while the
		 * relay is not there ( a 404 that is not the relay's own answer ) numbers are not sent for six hours.
		 *
		 * @since 6.0.5
		 * @param string $number Number.
		 * @param bool   $fresh  Ask again even when an answer is kept ( Settings › Taxes › Check a VAT number ).
		 * @return array status ( valid | invalid | unknown ), name, address ( one line ), reason ( why unknown, for the admin ).
		 */
		public static function hmrc_check( $number, $fresh = false ) {
			$number = self::clean( $number );
			$out    = array(
				'status'  => 'unknown',
				'name'    => '',
				'address' => '',
				'reason'  => '',
			);
			if ( 0 !== strpos( $number, 'GB' ) ) {
				return $out;
			}
			$digits = substr( $number, 2 );
			if ( 1 !== preg_match( '/^(\d{9}|\d{12})$/', $digits ) ) {
				if ( 1 !== preg_match( '/^(GD|HA)\d{3}$/', $digits ) ) {
					$out['status'] = 'invalid'; /* not the shape of a UK VAT number */
				}
				return $out;
			}
			$key = 'wpec_hmrc_' . md5( $digits );
			if ( ! $fresh ) {
				$kept = get_transient( $key );
				if ( is_array( $kept ) && isset( $kept['status'] ) ) {
					return array_merge( $out, $kept );
				}
				if ( get_transient( 'wpec_hmrc_relay_missing' ) ) {
					$out['reason'] = 'missing';
					return $out;
				}
			}
			/**
			 * The HMRC relay's address.
			 *
			 * @since 6.0.5
			 * @param string $url Address.
			 */
			$url      = (string) apply_filters( 'wp_easycart_hmrc_relay_url', self::HMRC_RELAY );
			$response = wp_remote_post(
				add_query_arg( 'action', 'check', $url ),
				array(
					'timeout' => 6,
					'headers' => array( 'Accept' => 'application/json' ),
					'body'    => array( 'vrn' => $digits ),
				)
			);
			$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
			$body = is_wp_error( $response ) ? null : json_decode( (string) wp_remote_retrieve_body( $response ), true );
			if ( 404 === $code && ! ( is_array( $body ) && isset( $body['api'] ) ) ) {
				set_transient( 'wpec_hmrc_relay_missing', 1, 6 * HOUR_IN_SECONDS );
				$out['reason'] = 'missing';
				return $out;
			}
			if ( 200 === $code && is_array( $body ) && isset( $body['status'] ) && in_array( $body['status'], array( 'valid', 'invalid' ), true ) ) {
				$out['status'] = (string) $body['status'];
				if ( 'valid' === $out['status'] ) {
					$out['name'] = isset( $body['name'] ) ? sanitize_text_field( (string) $body['name'] ) : '';
					$parts       = array();
					if ( isset( $body['address']['lines'] ) && is_array( $body['address']['lines'] ) ) {
						foreach ( $body['address']['lines'] as $line ) {
							$parts[] = sanitize_text_field( (string) $line );
						}
					}
					if ( ! empty( $body['address']['postcode'] ) ) {
						$parts[] = sanitize_text_field( (string) $body['address']['postcode'] );
					}
					$out['address'] = implode( ', ', array_filter( $parts ) );
				}
				delete_transient( 'wpec_hmrc_relay_missing' );
			} else {
				$out['reason'] = is_wp_error( $response ) ? 'unreachable' : ( ( is_array( $body ) && isset( $body['error'] ) ) ? sanitize_key( (string) $body['error'] ) : 'http_' . $code );
			}
			$keep = array(
				'status'  => $out['status'],
				'name'    => $out['name'],
				'address' => $out['address'],
			);
			set_transient( $key, $keep, ( 'valid' === $out['status'] ) ? WEEK_IN_SECONDS : ( ( 'invalid' === $out['status'] ) ? DAY_IN_SECONDS : 10 * MINUTE_IN_SECONDS ) );
			return $out;
		}

		/**
		 * Does the rate for VAT-registered businesses ( Settings › Taxes › VAT ) replace the country rate for this number? Never at
		 * home: a business in the store's own country pays the usual VAT. A store in the EU charges it only to a business in another
		 * EU country whose number belongs to the delivery or billing country and checks out in VIES ( the EU's reverse charge ), or to
		 * a UK business whose GB number HMRC knows ( 6.0.5 ); outside the EU, any business in another country, as before 6.0.5.
		 *
		 * @param string $number          VAT number entered.
		 * @param string $country         Delivery country.
		 * @param string $billing_country Billing country ( optional ).
		 * @return bool
		 */
		public static function business_rate_applies( $number, $country, $billing_country = '' ) {
			$number  = self::clean( $number );
			$country = strtoupper( trim( (string) $country ) );
			$billing = strtoupper( trim( (string) $billing_country ) );
			$store   = class_exists( 'wp_easycart_carriers' ) ? (string) wp_easycart_carriers::store_country() : '';
			if ( '' === $number ) {
				$applies = false;
			} elseif ( '' !== $store && $country === $store ) {
				$applies = false;
			} elseif ( '' === $store || ! self::in_eu( $store ) ) {
				$applies = true;
			} else {
				$prefix = self::number_country( $number );
				$ours   = ( '' !== $prefix && ( $prefix === $country || $prefix === $billing || ( 'XI' === $prefix && ( 'GB' === $country || 'GB' === $billing ) ) ) );
				$status = $ours ? self::number_status( $number ) : 'invalid';
				/**
				 * Whether a number VIES could not check ( it did not answer ) still gets the business rate. Off: the business pays VAT
				 * and claims it back.
				 *
				 * @since 6.0.5
				 * @param bool   $allow  Allow.
				 * @param string $number The number.
				 */
				$applies = ( 'valid' === $status ) || ( 'unknown' === $status && (bool) apply_filters( 'wp_easycart_vat_unverified_business_rate', false, $number ) );
			}
			/**
			 * Whether the business VAT rate applies.
			 *
			 * @since 6.0.5
			 * @param bool   $applies         Applies.
			 * @param string $number          The number.
			 * @param string $country         Delivery country.
			 * @param string $billing_country Billing country.
			 */
			return (bool) apply_filters( 'wp_easycart_vat_business_rate', $applies, $number, $country, $billing );
		}

		/**
		 * The reverse-charge mention for an order's documents ( Article 226(11a) of the VAT Directive ): an order from a store in
		 * the EU to a business in another EU country, with a VAT number, charged no VAT. '' otherwise.
		 *
		 * @param object $order Order row or display ( vat_registration_number, shipping_country, billing_country, vat_total ).
		 * @return string Plain text.
		 */
		public static function reverse_charge_text( $order ) {
			if ( ! is_object( $order ) || ! isset( $order->vat_registration_number ) || '' === self::clean( $order->vat_registration_number ) ) {
				return '';
			}
			$store   = class_exists( 'wp_easycart_carriers' ) ? (string) wp_easycart_carriers::store_country() : '';
			$country = strtoupper( (string) ( ! empty( $order->shipping_country ) ? $order->shipping_country : ( isset( $order->billing_country ) ? $order->billing_country : '' ) ) );
			$applies = '' !== $store && self::in_eu( $store ) && '' !== $country && $country !== $store && self::in_eu( $country ) && ( ! isset( $order->vat_total ) || abs( (float) $order->vat_total ) < 0.005 );
			/**
			 * Whether an order's documents say "Reverse charge".
			 *
			 * @since 6.0.5
			 * @param bool   $applies Applies.
			 * @param object $order   The order.
			 */
			if ( ! apply_filters( 'wp_easycart_vat_reverse_charge', $applies, $order ) ) {
				return '';
			}
			$text = function_exists( 'wp_easycart_language' ) ? wp_strip_all_tags( (string) wp_easycart_language()->get_text( 'cart_billing_information', 'cart_billing_information_vat_reverse_charge' ) ) : '';
			return '' !== trim( $text ) ? html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ) : 'Reverse charge';
		}

		/**
		 * Fill in the EU countries' VAT rates in Countries & Regions.
		 *
		 * @param string     $mode      oss = each country's own standard rate; home = one rate for every EU country ( a store under the
		 *                              threshold charges its own country's rate ).
		 * @param float|null $home_rate The rate for home.
		 * @return int Countries updated.
		 */
		public static function fill_eu( $mode = 'oss', $home_rate = null ) {
			global $wpdb;
			$rates    = self::eu();
			$existing = (array) $wpdb->get_col( $wpdb->prepare( 'SELECT iso2_cnt FROM ec_country WHERE iso2_cnt IN ( ' . implode( ', ', array_fill( 0, count( $rates ), '%s' ) ) . ' )', array_keys( $rates ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders only.
			$done     = 0;
			foreach ( $rates as $code => $rate ) {
				if ( ! in_array( $code, $existing, true ) ) {
					continue;
				}
				$use = ( 'home' === $mode && null !== $home_rate ) ? (float) $home_rate : (float) $rate;
				if ( false !== $wpdb->query( $wpdb->prepare( 'UPDATE ec_country SET vat_rate_cnt = %f WHERE iso2_cnt = %s', $use, $code ) ) ) {
					++$done;
				}
			}
			wp_cache_delete( 'wpeasycart-countries' );
			wp_cache_delete( 'wpeasycart-config-vat-included' );
			wp_cache_delete( 'wpeasycart-config-vat-added' );
			/**
			 * The EU VAT rates were filled in.
			 *
			 * @since 6.0.5
			 * @param string     $mode      oss | home.
			 * @param float|null $home_rate The one rate for home.
			 */
			do_action( 'wp_easycart_eu_vat_rates_filled', $mode, $home_rate );
			return $done;
		}

		/**
		 * The name this request uses, kept once worked out.
		 *
		 * @var string|null
		 */
		private static $name = null;

		/**
		 * Countries whose value added tax is called GST.
		 *
		 * @return array
		 */
		public static function gst_countries() {
			return array( 'AU', 'NZ', 'SG', 'MY', 'IN' );
		}

		/**
		 * VAT or GST: the store's choice, else GST in a GST country.
		 *
		 * @return string VAT | GST
		 */
		public static function name() {
			if ( null === self::$name ) {
				$chosen = strtoupper( trim( (string) get_option( 'ec_option_vat_name', '' ) ) );
				if ( in_array( $chosen, array( 'VAT', 'GST' ), true ) ) {
					$name = $chosen;
				} else {
					$country = ( class_exists( 'wp_easycart_carriers' ) && method_exists( 'wp_easycart_carriers', 'store_country' ) ) ? (string) wp_easycart_carriers::store_country() : '';
					$name    = in_array( strtoupper( $country ), self::gst_countries(), true ) ? 'GST' : 'VAT';
				}
				/**
				 * What the store's value added tax is called: VAT or GST.
				 *
				 * @since 6.0.5
				 * @param string $name VAT | GST.
				 */
				$name       = (string) apply_filters( 'wp_easycart_vat_name', $name );
				self::$name = in_array( $name, array( 'VAT', 'GST' ), true ) ? $name : 'VAT';
			}
			return self::$name;
		}

		/**
		 * Forget the name ( after Settings saves it ).
		 */
		public static function forget() {
			self::$name = null;
		}

		/**
		 * The name for the admin, translatable.
		 *
		 * @return string
		 */
		public static function name_label() {
			return ( 'GST' === self::name() ) ? __( 'GST', 'wp-easycart' ) : __( 'VAT', 'wp-easycart' );
		}

		/**
		 * A text with GST for VAT when the store calls it GST ( whole words only ).
		 *
		 * @param string $text Text.
		 * @return string
		 */
		public static function rename( $text ) {
			if ( ! is_string( $text ) || false === strpos( $text, 'VAT' ) || 'GST' !== self::name() ) {
				return $text;
			}
			return (string) preg_replace( '/\bVAT\b/u', 'GST', $text );
		}

		/**
		 * wp_easycart_language_text: the storefront's, receipts' and documents' phrases say GST where they said VAT.
		 *
		 * @param string $text    Phrase.
		 * @param string $section Section.
		 * @param string $key     Key.
		 * @return string
		 */
		public static function language_text( $text, $section = '', $key = '' ) {
			return self::rename( $text );
		}

		/**
		 * wp_easycart_pdf_receipt_title: an Australian store's invoice PDF is a "Tax invoice" ( while its heading is the invoice
		 * heading WP EasyCart gives it ).
		 *
		 * @param string $title Title.
		 * @param object $order Order.
		 * @return string
		 */
		public static function pdf_title( $title, $order = null ) {
			$country = ( class_exists( 'wp_easycart_carriers' ) && method_exists( 'wp_easycart_carriers', 'store_country' ) ) ? (string) wp_easycart_carriers::store_country() : '';
			if ( 'AU' !== strtoupper( $country ) || ! class_exists( 'wp_easycart_documents' ) ) {
				return $title;
			}
			$default = wp_strip_all_tags( wp_easycart_documents::text( 'invoice_title', __( 'Invoice', 'wp-easycart' ) ) );
			if ( trim( (string) $title ) !== trim( $default ) ) {
				return $title;
			}
			return wp_strip_all_tags( wp_easycart_documents::text( 'tax_invoice_title', __( 'Tax invoice', 'wp-easycart' ) ) );
		}
	}

endif;

if ( function_exists( 'add_filter' ) ) {
	add_filter( 'wp_easycart_language_text', array( 'wp_easycart_vat', 'language_text' ), 20, 3 );
	add_filter( 'wp_easycart_pdf_receipt_title', array( 'wp_easycart_vat', 'pdf_title' ), 10, 2 );
	add_action( 'update_option_ec_option_vat_name', array( 'wp_easycart_vat', 'forget' ) );
}
