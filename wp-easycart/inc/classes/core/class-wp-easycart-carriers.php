<?php
/**
 * Shipping carriers ( 6.0.3 ): one list for every place an order's carrier is chosen or shown.
 *
 * The order screen's Ship form and Fulfill window, the orders list's Ship popover and Quick edit, and the package Add tracking
 * dialog all offer the carriers from catalog(): the major carriers worldwide, the ones that ship from the store's country first,
 * then the carriers on the store's own recent orders ( a name typed with Other… comes back next time ). Any carrier can still be
 * typed with Other…. tracking_url() turns a carrier and a tracking number into the carrier's tracking page for the shipped email,
 * My Account and the order screen.
 *
 * @package  Wp_Easycart
 * @since    6.0.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_carriers' ) ) :

	/**
	 * Shipping carriers.
	 */
	final class wp_easycart_carriers {

		/** The option value that opens the field for a carrier's name. */
		const OTHER = '__other__';

		/** Transient with the carriers on the store's recent orders. */
		const USED_KEY = 'wpec_used_carriers';

		/**
		 * The carriers, in the order they are listed within a group.
		 *
		 * Each entry: name ( saved on the order and shown to the customer ), aliases ( other ways it is written, lower case ),
		 * countries ( ISO codes it ships from; empty = worldwide ), track ( tracking page with %s for the number, '' when the
		 * carrier has no page a number alone opens ), label_url / label_sub ( where a label is made, for the Fulfill window ),
		 * keywords ( | separated, matched against the order's shipping method to suggest the carrier ), mark / bg / fg ( the
		 * carrier tile on the order screen ).
		 *
		 * @return array key => entry.
		 */
		public static function catalog() {
			static $catalog = null;
			if ( null !== $catalog ) {
				return $catalog;
			}
			$catalog = array(
				'usps'          => array(
					'name'      => 'USPS',
					'aliases'   => array( 'united states postal service', 'us postal service' ),
					'countries' => array( 'US' ),
					'track'     => 'https://tools.usps.com/go/TrackConfirmAction?tLabels=%s',
					'label_url' => 'https://cns.usps.com/',
					'label_sub' => 'Click-N-Ship',
					'keywords'  => 'usps|priority mail|ground advantage|first-class|first class|media mail|parcel select',
					'mark'      => 'USPS',
					'bg'        => '#333366',
					'fg'        => '#ffffff',
				),
				'ups'           => array(
					'name'      => 'UPS',
					'aliases'   => array( 'united parcel service' ),
					'countries' => array(),
					'track'     => 'https://www.ups.com/track?tracknum=%s',
					'label_url' => 'https://www.ups.com/ship/guided',
					'label_sub' => 'Ship',
					'keywords'  => 'ups|2nd day air|second day air|next day air|3 day select|ups ground|ups standard|ups express',
					'mark'      => 'UPS',
					'bg'        => '#351c15',
					'fg'        => '#ffb500',
				),
				'fedex'         => array(
					'name'      => 'FedEx',
					'aliases'   => array( 'fed ex', 'federal express' ),
					'countries' => array(),
					'track'     => 'https://www.fedex.com/fedextrack/?trknbr=%s',
					'label_url' => 'https://www.fedex.com/en-us/shipping.html',
					'label_sub' => 'Ship Manager',
					'keywords'  => 'fedex|fed ex|home delivery|smartpost|2day|overnight',
					'mark'      => 'FedEx',
					'bg'        => '#4d148c',
					'fg'        => '#ffffff',
				),
				'dhl'           => array(
					'name'      => 'DHL',
					'aliases'   => array( 'dhl express' ),
					'countries' => array(),
					'track'     => 'https://www.dhl.com/en/express/tracking.html?AWB=%s',
					'label_url' => 'https://mydhl.express.dhl/',
					'label_sub' => 'MyDHL+',
					'keywords'  => 'dhl express|express worldwide|express easy|dhl',
					'mark'      => 'DHL',
					'bg'        => '#ffcc00',
					'fg'        => '#d40511',
				),
				'ontrac'        => array(
					'name'      => 'OnTrac',
					'aliases'   => array( 'lasership' ),
					'countries' => array( 'US' ),
					'track'     => 'https://www.lasership.com/track/%s',
					'keywords'  => 'ontrac|lasership',
				),
				'canadapost'    => array(
					'name'      => 'Canada Post',
					'aliases'   => array( 'postes canada' ),
					'countries' => array( 'CA' ),
					'track'     => 'https://www.canadapost-postescanada.ca/track-reperage/en#/search?searchFor=%s',
					'label_url' => 'https://www.canadapost-postescanada.ca/cpc/en/business/shipping.page',
					'label_sub' => 'Ship Online',
					'keywords'  => 'canada post|xpresspost|expedited parcel|regular parcel',
					'mark'      => 'CP',
					'bg'        => '#c8102e',
					'fg'        => '#ffffff',
				),
				'purolator'     => array(
					'name'      => 'Purolator',
					'aliases'   => array(),
					'countries' => array( 'CA' ),
					'track'     => 'https://www.purolator.com/en/shipping/tracking/%s',
					'keywords'  => 'purolator',
				),
				'royalmail'     => array(
					'name'      => 'Royal Mail',
					'aliases'   => array(),
					'countries' => array( 'GB' ),
					'track'     => 'https://www.royalmail.com/track-your-item#/tracking-results/%s',
					'label_url' => 'https://parcel.royalmail.com/',
					'label_sub' => 'Click & Drop',
					'keywords'  => 'royal mail|1st class|2nd class|first class|second class|special delivery|tracked 24|tracked 48|signed for|international tracked',
					'mark'      => 'RM',
					'bg'        => '#da202a',
					'fg'        => '#ffffff',
				),
				'parcelforce'   => array(
					'name'      => 'Parcelforce',
					'aliases'   => array( 'parcelforce worldwide' ),
					'countries' => array( 'GB' ),
					'track'     => 'https://www.parcelforce.com/track-trace/%s',
					'keywords'  => 'parcelforce|express48|express24|express 48|express 24',
				),
				'evri'          => array(
					'name'      => 'Evri',
					'aliases'   => array( 'hermes uk', 'myhermes' ),
					'countries' => array( 'GB' ),
					'track'     => 'https://www.evri.com/track/%s',
					'keywords'  => 'evri|hermes',
				),
				'dpd'           => array(
					'name'      => 'DPD',
					'aliases'   => array( 'dpd local' ),
					'countries' => array( 'GB', 'IE', 'DE', 'FR', 'NL', 'BE', 'AT', 'PL', 'CZ', 'SK', 'HU', 'PT', 'ES', 'CH', 'LU', 'LT', 'LV', 'EE', 'HR', 'SI', 'RO', 'BG' ),
					'track'     => 'https://www.dpd.com/tracking/%s',
					'keywords'  => 'dpd',
				),
				'yodel'         => array(
					'name'      => 'Yodel',
					'aliases'   => array(),
					'countries' => array( 'GB' ),
					'track'     => '',
					'keywords'  => 'yodel',
				),
				'anpost'        => array(
					'name'      => 'An Post',
					'aliases'   => array(),
					'countries' => array( 'IE' ),
					'track'     => 'https://www.anpost.com/Track/Track?item=%s',
					'keywords'  => 'an post',
				),
				'dhlpaket'      => array(
					'name'      => 'DHL Paket',
					'aliases'   => array( 'deutsche post', 'dhl parcel' ),
					'countries' => array( 'DE' ),
					'track'     => 'https://www.dhl.de/en/privatkunden/dhl-sendungsverfolgung.html?piececode=%s',
					'keywords'  => 'dhl paket|deutsche post|warenpost|päckchen',
				),
				'hermes'        => array(
					'name'      => 'Hermes',
					'aliases'   => array( 'hermes germany' ),
					'countries' => array( 'DE' ),
					'track'     => '',
					'keywords'  => 'hermes',
				),
				'gls'           => array(
					'name'      => 'GLS',
					'aliases'   => array(),
					'countries' => array( 'DE', 'AT', 'BE', 'DK', 'ES', 'FR', 'IT', 'NL', 'PL', 'PT', 'CZ', 'SK', 'HU', 'SI', 'HR', 'RO', 'IE', 'LU', 'CA', 'US' ),
					'track'     => 'https://gls-group.eu/EU/en/parcel-tracking/%s',
					'keywords'  => 'gls',
				),
				'postnl'        => array(
					'name'      => 'PostNL',
					'aliases'   => array(),
					'countries' => array( 'NL', 'BE' ),
					'track'     => 'https://www.postnl.nl/track-en-trace/%s',
					'keywords'  => 'postnl',
				),
				'bpost'         => array(
					'name'      => 'bpost',
					'aliases'   => array(),
					'countries' => array( 'BE' ),
					'track'     => 'https://www.bpost.be/en/track-and-trace?itemId=%s',
					'keywords'  => 'bpost',
				),
				'colissimo'     => array(
					'name'      => 'Colissimo',
					'aliases'   => array( 'la poste' ),
					'countries' => array( 'FR' ),
					'track'     => 'https://www.laposte.fr/outils/suivre-vos-envois?code=%s',
					'keywords'  => 'colissimo|la poste|lettre suivie',
				),
				'chronopost'    => array(
					'name'      => 'Chronopost',
					'aliases'   => array(),
					'countries' => array( 'FR' ),
					'track'     => 'https://www.chronopost.fr/en/track/%s',
					'keywords'  => 'chronopost',
				),
				'correos'       => array(
					'name'      => 'Correos',
					'aliases'   => array(),
					'countries' => array( 'ES' ),
					'track'     => 'https://www.correos.es/ss/Satellite/site/pagina-tracking/info?idioma=en_GB&numeroEnvio=%s',
					'keywords'  => 'correos',
				),
				'posteitaliane' => array(
					'name'      => 'Poste Italiane',
					'aliases'   => array(),
					'countries' => array( 'IT' ),
					'track'     => 'https://www.poste.it/track/%s',
					'keywords'  => 'poste italiane|poste delivery|crono',
				),
				'brt'           => array(
					'name'      => 'BRT',
					'aliases'   => array( 'bartolini' ),
					'countries' => array( 'IT' ),
					'track'     => 'https://www.brt.it/track?trackingNumber=%s',
					'keywords'  => 'brt|bartolini',
				),
				'swisspost'     => array(
					'name'      => 'Swiss Post',
					'aliases'   => array( 'die post', 'la poste suisse' ),
					'countries' => array( 'CH' ),
					'track'     => 'https://www.post.ch/en/parcel-tracking?itemId=%s',
					'keywords'  => 'swiss post|postpac',
				),
				'austrianpost'  => array(
					'name'      => 'Austrian Post',
					'aliases'   => array( 'österreichische post', 'post.at' ),
					'countries' => array( 'AT' ),
					'track'     => 'https://www.post.at/en/track/%s',
					'keywords'  => 'austrian post|österreichische post',
				),
				'postnord'      => array(
					'name'      => 'PostNord',
					'aliases'   => array(),
					'countries' => array( 'SE', 'DK', 'NO', 'FI' ),
					'track'     => 'https://www.postnord.se/track-and-trace/%s',
					'keywords'  => 'postnord',
				),
				'bring'         => array(
					'name'      => 'Bring',
					'aliases'   => array( 'posten norge' ),
					'countries' => array( 'NO', 'SE', 'DK', 'FI' ),
					'track'     => 'https://www.posten.no/sporing/%s',
					'keywords'  => 'bring|posten',
				),
				'inpost'        => array(
					'name'      => 'InPost',
					'aliases'   => array(),
					'countries' => array( 'PL', 'GB', 'IT', 'FR', 'ES', 'PT' ),
					'track'     => 'https://inpost.pl/sledzenie-przesylek/%s',
					'keywords'  => 'inpost|paczkomat',
				),
				'auspost'       => array(
					'name'      => 'Australia Post',
					'aliases'   => array( 'auspost', 'aus post' ),
					'countries' => array( 'AU' ),
					'track'     => 'https://auspost.com.au/mypost/track/details/%s',
					'label_url' => 'https://auspost.com.au/mypost-business',
					'label_sub' => 'MyPost',
					'keywords'  => 'australia post|auspost|parcel post|express post|satchel',
					'mark'      => 'AP',
					'bg'        => '#dc1928',
					'fg'        => '#ffffff',
				),
				'sendle'        => array(
					'name'      => 'Sendle',
					'aliases'   => array(),
					'countries' => array( 'AU', 'US', 'CA' ),
					'track'     => '',
					'keywords'  => 'sendle',
				),
				'nzpost'        => array(
					'name'      => 'NZ Post',
					'aliases'   => array( 'new zealand post' ),
					'countries' => array( 'NZ' ),
					'track'     => 'https://www.nzpost.co.nz/tools/tracking?track=%s',
					'keywords'  => 'nz post|new zealand post|courierpost',
				),
				'japanpost'     => array(
					'name'      => 'Japan Post',
					'aliases'   => array(),
					'countries' => array( 'JP' ),
					'track'     => '',
					'keywords'  => 'japan post|yu-pack|yu-packet',
				),
				'aramex'        => array(
					'name'      => 'Aramex',
					'aliases'   => array(),
					'countries' => array( 'AE', 'SA', 'QA', 'KW', 'BH', 'OM', 'JO', 'EG', 'LB' ),
					'track'     => '',
					'keywords'  => 'aramex',
				),
			);
			/**
			 * The carriers the order screens offer and the tracking pages they link to.
			 *
			 * @since 6.0.3
			 * @param array $catalog key => array( name, aliases, countries, track ( %s = the number ), label_url, label_sub,
			 *                       keywords, mark, bg, fg ).
			 */
			$catalog = (array) apply_filters( 'wp_easycart_carriers', $catalog );
			foreach ( $catalog as $key => $entry ) {
				if ( ! is_array( $entry ) || empty( $entry['name'] ) ) {
					unset( $catalog[ $key ] );
					continue;
				}
				$catalog[ $key ] = array_merge(
					array(
						'aliases'   => array(),
						'countries' => array(),
						'track'     => '',
						'label_url' => '',
						'label_sub' => '',
						'keywords'  => '',
						'mark'      => '',
						'bg'        => '',
						'fg'        => '',
					),
					$entry
				);
			}
			return $catalog;
		}

		/**
		 * Forget the carriers on recent orders ( after a new carrier is saved, and in tests ).
		 */
		public static function forget() {
			delete_transient( self::USED_KEY );
		}

		/**
		 * A carrier was saved on an order or a package: a name the list does not have yet shows from the next page on.
		 *
		 * @param string $carrier Carrier as saved.
		 */
		public static function saved( $carrier ) {
			$carrier = self::clean( $carrier );
			if ( '' === $carrier ) {
				return;
			}
			$cached = get_transient( self::USED_KEY );
			if ( is_array( $cached ) ) {
				foreach ( array_keys( $cached ) as $name ) {
					if ( strtolower( (string) $name ) === strtolower( $carrier ) || ( '' !== self::find( $carrier ) && self::find( $carrier ) === self::find( $name ) ) ) {
						return;
					}
				}
			}
			self::forget();
		}

		/**
		 * The country the store ships from: the checkout's default country, else the Stripe business country, else the region in the
		 * site's language ( en_GB → GB ). Filter wp_easycart_store_country.
		 *
		 * @return string ISO code or ''.
		 */
		public static function store_country() {
			$country = strtoupper( trim( (string) get_option( 'ec_option_default_country', '' ) ) );
			if ( ! preg_match( '/^[A-Z]{2}$/', $country ) ) {
				$country = strtoupper( trim( (string) get_option( 'ec_option_stripe_company_country', '' ) ) );
			}
			if ( ! preg_match( '/^[A-Z]{2}$/', $country ) && function_exists( 'get_locale' ) && preg_match( '/^[a-z]{2,3}_([A-Z]{2})/', (string) get_locale(), $m ) ) {
				$country = $m[1];
			}
			if ( 'UK' === $country ) {
				$country = 'GB';
			}
			/**
			 * The country the store ships from, for the order of the carrier list.
			 *
			 * @since 6.0.3
			 * @param string $country ISO code or ''.
			 */
			$country = strtoupper( (string) apply_filters( 'wp_easycart_store_country', preg_match( '/^[A-Z]{2}$/', $country ) ? $country : '' ) );
			return preg_match( '/^[A-Z]{2}$/', $country ) ? $country : '';
		}

		/**
		 * The catalog key a carrier name stands for ( its name or an alias, any case ), or ''.
		 *
		 * @param string $name Carrier as saved.
		 * @return string
		 */
		public static function find( $name ) {
			$name = strtolower( trim( wp_strip_all_tags( (string) $name ) ) );
			if ( '' === $name ) {
				return '';
			}
			foreach ( self::catalog() as $key => $entry ) {
				if ( strtolower( (string) $entry['name'] ) === $name || in_array( $name, array_map( 'strtolower', (array) $entry['aliases'] ), true ) ) {
					return (string) $key;
				}
			}
			return '';
		}

		/**
		 * A carrier as it is saved: trimmed, and '' for the Other… choice itself.
		 *
		 * @param string $carrier Posted carrier.
		 * @return string
		 */
		public static function clean( $carrier ) {
			$carrier = trim( sanitize_text_field( (string) $carrier ) );
			return ( self::OTHER === $carrier ) ? '' : $carrier;
		}

		/**
		 * The carriers on the store's recent orders and packages, most used first: catalog carriers by their name, others as typed.
		 * Kept a day ( forget() clears it when a new name is saved ).
		 *
		 * @return array name => count.
		 */
		public static function used() {
			$cached = get_transient( self::USED_KEY );
			if ( is_array( $cached ) ) {
				return $cached;
			}
			global $wpdb;
			$counts = array();
			if ( isset( $wpdb ) && is_object( $wpdb ) ) {
				$rows = (array) $wpdb->get_col( 'SELECT shipping_carrier FROM ( SELECT shipping_carrier FROM ec_order WHERE shipping_carrier <> \'\' ORDER BY order_id DESC LIMIT 300 ) recent' );
				if ( class_exists( 'wp_easycart_shipments' ) && method_exists( 'wp_easycart_shipments', 'ready' ) && wp_easycart_shipments::ready() ) {
					$rows = array_merge( $rows, (array) $wpdb->get_col( 'SELECT carrier FROM ( SELECT carrier FROM ec_order_shipment WHERE carrier <> \'\' ORDER BY shipment_id DESC LIMIT 300 ) recent' ) );
				}
				foreach ( $rows as $carrier ) {
					$carrier = trim( wp_strip_all_tags( (string) $carrier ) );
					if ( '' === $carrier || in_array( strtolower( $carrier ), array( '0', 'null', 'other', self::OTHER ), true ) || strlen( $carrier ) > 60 ) {
						continue;
					}
					$key  = self::find( $carrier );
					$name = '' !== $key ? self::catalog()[ $key ]['name'] : $carrier;
					/* One entry per name, whatever the case it was typed in. */
					foreach ( array_keys( $counts ) as $seen ) {
						if ( strtolower( $seen ) === strtolower( $name ) ) {
							$name = $seen;
							break;
						}
					}
					$counts[ $name ] = isset( $counts[ $name ] ) ? $counts[ $name ] + 1 : 1;
				}
				arsort( $counts );
				$counts = array_slice( $counts, 0, 12, true );
			}
			set_transient( self::USED_KEY, $counts, DAY_IN_SECONDS );
			return $counts;
		}

		/**
		 * The carrier list in the order it is offered: the carriers on recent orders, the carriers that ship from the store's
		 * country, the worldwide carriers, then the rest.
		 *
		 * @return array Groups: array( label, names[] ), empty groups left out.
		 */
		public static function groups() {
			$catalog = self::catalog();
			$country = self::store_country();
			$taken   = array();
			$groups  = array(
				'used'      => array(
					'label' => __( 'On your recent orders', 'wp-easycart' ),
					'names' => array(),
				),
				'country'   => array(
					'label' => __( 'In your country', 'wp-easycart' ),
					'names' => array(),
				),
				'worldwide' => array(
					'label' => __( 'Worldwide', 'wp-easycart' ),
					'names' => array(),
				),
				'more'      => array(
					'label' => __( 'More carriers', 'wp-easycart' ),
					'names' => array(),
				),
			);
			foreach ( array_keys( self::used() ) as $name ) {
				$groups['used']['names'][]    = $name;
				$taken[ strtolower( $name ) ] = true;
			}
			foreach ( $catalog as $entry ) {
				$name = (string) $entry['name'];
				if ( isset( $taken[ strtolower( $name ) ] ) ) {
					continue;
				}
				if ( '' !== $country && in_array( $country, (array) $entry['countries'], true ) ) {
					$groups['country']['names'][] = $name;
				} elseif ( empty( $entry['countries'] ) ) {
					$groups['worldwide']['names'][] = $name;
				} else {
					$groups['more']['names'][] = $name;
				}
			}
			natcasesort( $groups['more']['names'] );
			$groups['more']['names'] = array_values( $groups['more']['names'] );
			return array_filter(
				$groups,
				function ( $group ) {
					return ! empty( $group['names'] );
				}
			);
		}

		/**
		 * Every carrier name offered, in list order ( for type-ahead lists ).
		 *
		 * @return string[]
		 */
		public static function names() {
			$names = array();
			foreach ( self::groups() as $group ) {
				$names = array_merge( $names, $group['names'] );
			}
			return $names;
		}

		/**
		 * The carrier to start an order's carrier field on: the one its shipping method names ( the store's country first ), else
		 * the carrier on most of the store's recent orders, else the first carrier from the store's country.
		 *
		 * @param object|null $order    Order row ( shipping_carrier, shipping_method ).
		 * @param bool        $fallback False: only a carrier the order itself names ( its carrier or shipping method ).
		 * @return string Carrier name or ''.
		 */
		public static function suggested( $order = null, $fallback = true ) {
			$catalog = self::catalog();
			if ( is_object( $order ) && isset( $order->shipping_carrier ) && '' !== self::clean( $order->shipping_carrier ) && '0' !== trim( (string) $order->shipping_carrier ) ) {
				$key = self::find( $order->shipping_carrier );
				return '' !== $key ? $catalog[ $key ]['name'] : self::clean( $order->shipping_carrier );
			}
			$method = is_object( $order ) && isset( $order->shipping_method ) ? strtolower( wp_strip_all_tags( (string) $order->shipping_method ) ) : '';
			if ( '' !== $method ) {
				$country = self::store_country();
				$order_k = array();
				/* The store's own carriers are tried first, so "First Class" is Royal Mail in the UK and USPS in the US. */
				foreach ( $catalog as $key => $entry ) {
					$order_k[ $key ] = ( '' !== $country && in_array( $country, (array) $entry['countries'], true ) ) ? 0 : ( empty( $entry['countries'] ) ? 1 : 2 );
				}
				asort( $order_k );
				foreach ( array_keys( $order_k ) as $key ) {
					foreach ( explode( '|', (string) $catalog[ $key ]['keywords'] ) as $word ) {
						if ( '' !== $word && preg_match( '/(^|[^a-z0-9])' . preg_quote( $word, '/' ) . '($|[^a-z0-9])/u', $method ) ) {
							return $catalog[ $key ]['name'];
						}
					}
				}
			}
			if ( ! $fallback ) {
				return '';
			}
			$used = array_keys( self::used() );
			if ( $used ) {
				return (string) $used[0];
			}
			$groups = self::groups();
			if ( isset( $groups['country'] ) ) {
				return (string) $groups['country']['names'][0];
			}
			return '';
		}

		/**
		 * The <option> list for a carrier <select>: the groups, the selected carrier ( added when it is not in the list ), then
		 * Other… ( a field for any carrier's name; the order screens' scripts show it ).
		 *
		 * @param string   $selected Carrier to select.
		 * @param string[] $extra    More names to offer ( a filter's own carriers ); names already listed are skipped.
		 * @return string HTML.
		 */
		public static function options_html( $selected = '', $extra = array() ) {
			$selected = self::clean( $selected );
			$key      = self::find( $selected );
			if ( '' !== $key ) {
				$selected = self::catalog()[ $key ]['name'];
			}
			$html  = '';
			$found = ( '' === $selected );
			foreach ( self::groups() as $group ) {
				$html .= '<optgroup label="' . esc_attr( $group['label'] ) . '">';
				foreach ( $group['names'] as $name ) {
					$on    = ( '' !== $selected && strtolower( $name ) === strtolower( $selected ) );
					$found = $found || $on;
					$html .= '<option value="' . esc_attr( $name ) . '"' . ( $on ? ' selected="selected"' : '' ) . '>' . esc_html( $name ) . '</option>';
				}
				$html .= '</optgroup>';
			}
			$listed = array_map( 'strtolower', self::names() );
			$added  = '';
			foreach ( (array) $extra as $name ) {
				$name = self::clean( $name );
				if ( '' === $name || 'other' === strtolower( $name ) || in_array( strtolower( $name ), $listed, true ) ) {
					continue;
				}
				$listed[] = strtolower( $name );
				$on       = ( '' !== $selected && strtolower( $name ) === strtolower( $selected ) );
				$found    = $found || $on;
				$added   .= '<option value="' . esc_attr( $name ) . '"' . ( $on ? ' selected="selected"' : '' ) . '>' . esc_html( $name ) . '</option>';
			}
			if ( '' !== $added ) {
				$html .= '<optgroup label="' . esc_attr__( 'Added carriers', 'wp-easycart' ) . '">' . $added . '</optgroup>';
			}
			if ( ! $found ) {
				$html = '<option value="' . esc_attr( $selected ) . '" selected="selected">' . esc_html( $selected ) . '</option>' . $html;
			}
			return $html . '<option value="' . esc_attr( self::OTHER ) . '">' . esc_html__( 'Other…', 'wp-easycart' ) . '</option>';
		}

		/**
		 * The carriers with a site that makes labels, for the Fulfill window: the store's country first, then worldwide.
		 *
		 * @return array key => entry.
		 */
		public static function label_sites() {
			$country = self::store_country();
			$first   = array();
			$then    = array();
			foreach ( self::catalog() as $key => $entry ) {
				if ( '' === (string) $entry['label_url'] ) {
					continue;
				}
				if ( '' !== $country && in_array( $country, (array) $entry['countries'], true ) ) {
					$first[ $key ] = $entry;
				} elseif ( empty( $entry['countries'] ) || '' === $country ) {
					$then[ $key ] = $entry;
				}
			}
			return $first + $then;
		}

		/**
		 * The carrier's tracking page for a number, or '' when the carrier is unknown or has no tracking page.
		 *
		 * @param string $carrier  Carrier as saved.
		 * @param string $tracking Tracking number.
		 * @return string
		 */
		public static function tracking_url( $carrier, $tracking ) {
			$carrier  = strtolower( trim( (string) $carrier ) );
			$tracking = trim( (string) $tracking );
			if ( '' === $carrier || '' === $tracking ) {
				return '';
			}
			$map = self::tracking_map();
			if ( isset( $map[ $carrier ] ) ) {
				return '' !== (string) $map[ $carrier ] ? sprintf( (string) $map[ $carrier ], rawurlencode( $tracking ) ) : '';
			}
			/* A carrier written with more words ( "Royal Mail Tracked 24" ): the longest name it contains. */
			$keys = array_keys( $map );
			usort(
				$keys,
				function ( $a, $b ) {
					return strlen( (string) $b ) - strlen( (string) $a );
				}
			);
			foreach ( $keys as $key ) {
				if ( '' !== (string) $key && '' !== (string) $map[ $key ] && preg_match( '/(^|[^a-z0-9])' . preg_quote( (string) $key, '/' ) . '($|[^a-z0-9])/', $carrier ) ) {
					return sprintf( (string) $map[ $key ], rawurlencode( $tracking ) );
				}
			}
			return '';
		}

		/**
		 * Lower-case carrier names and aliases => tracking page template ( '' = none ), through the 6.0.2 filter
		 * wp_easycart_ecv2_tracking_url_map.
		 *
		 * @return array
		 */
		public static function tracking_map() {
			$map = array();
			foreach ( self::catalog() as $entry ) {
				$map[ strtolower( (string) $entry['name'] ) ] = (string) $entry['track'];
				foreach ( (array) $entry['aliases'] as $alias ) {
					$map[ strtolower( (string) $alias ) ] = (string) $entry['track'];
				}
			}
			/** This filter is documented in inc/classes/core/class-wp-easycart-email-design.php ( 6.0.2 ). */
			return (array) apply_filters( 'wp_easycart_ecv2_tracking_url_map', $map );
		}
	}

endif;
