<?php
/**
 * WP EasyCart — price history and the 30-day lowest price ( 6.0.5, admin review H12 ).
 *
 * EU rules ( Article 6a of Directive 98/6/EC, from the Omnibus Directive ) say that a store announcing a price reduction must show
 * the lowest price it charged in the 30 days before the reduction. WP EasyCart kept no price history, so a struck-through
 * compare-at price could not show it.
 *
 * ec_price_history ( EC_UPGRADE_DB 127 ) gets a row whenever a product's price changes: on every product save
 * ( wpeasycart_product_updated / _added ), when a page asks and the price moved without a save ( an import, a sync ), and in a
 * daily sweep ( WP-Cron wp_easycart_price_history_sweep ). prior_price() is the lowest price charged in the 30 days before the
 * current price began ( the reduction's start, so the figure stays put while the sale lasts ); an offer's reduction counts from
 * today. note_html() prints "Lowest price in the last 30 days: …" under struck prices on product pages and store listings,
 * when Settings › Products › Lowest price before a reduction is on ( by default for stores in the EU ).
 *
 * Variants that set their own price ( ec_optionitemquantity.price >= 0 ) keep their own rows ( optionitemquantity_id ); the product
 * page's note follows the chosen variant ( data-wpec-prior-variants, ec-store.js wpeasycart_prior_price_variant() ). A reduction that
 * began before 6.0.5 has no earlier price on record: the product editor's Pricing panel lets the merchant enter it ( set_prior() ).
 *
 * @package WP_EasyCart
 * @since   6.0.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_price_history' ) ) :

	/**
	 * Price history.
	 */
	class wp_easycart_price_history {

		/** Database version with ec_price_history. */
		const DB = 127;

		/** Days looked back. */
		const DAYS = 30;

		/** Products checked by one run of the sweep. */
		const SWEEP_BATCH = 500;

		/**
		 * Prior prices worked out this request, product id => float|null.
		 *
		 * @var array
		 */
		private static $prior = array();

		/**
		 * Recorded prices on this request's shopper's basis ( basis() ), "product|price" => float.
		 *
		 * @var array
		 */
		private static $basis = array();

		/**
		 * The table exists.
		 *
		 * @return bool
		 */
		public static function ready() {
			return (int) get_option( 'ec_option_db_new_version' ) >= self::DB;
		}

		/**
		 * Hooks: record on product saves; the daily sweep.
		 */
		public static function hooks() {
			add_action( 'wpeasycart_product_updated', array( __CLASS__, 'on_product_saved' ), 20 );
			add_action( 'wpeasycart_product_added', array( __CLASS__, 'on_product_saved' ), 20 );
			add_action( 'wp_easycart_price_history_sweep', array( __CLASS__, 'sweep' ) );
			add_action( 'init', array( __CLASS__, 'schedule' ) );
		}

		/**
		 * Keep the daily sweep scheduled ( only once the table exists ).
		 */
		public static function schedule() {
			if ( self::ready() && function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( 'wp_easycart_price_history_sweep' ) ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'wp_easycart_price_history_sweep' );
			}
		}

		/**
		 * A product was saved.
		 *
		 * @param int $product_id Product.
		 */
		public static function on_product_saved( $product_id ) {
			self::record( (int) $product_id );
			self::variant_rows( (int) $product_id ); /* records the variants' own prices */
		}

		/**
		 * Record the product's price when it differs from the last one kept ( or none is kept ).
		 *
		 * @param int        $product_id Product.
		 * @param float|null $price      The price ( null reads it ).
		 * @param int        $variant_id A variant that sets its own price ( ec_optionitemquantity ), 0 for the product.
		 * @return bool A row was added.
		 */
		public static function record( $product_id, $price = null, $variant_id = 0 ) {
			global $wpdb;
			$product_id = (int) $product_id;
			$variant_id = (int) $variant_id;
			if ( $product_id <= 0 || ! self::ready() ) {
				return false;
			}
			if ( null === $price ) {
				$price = $variant_id > 0
					? $wpdb->get_var( $wpdb->prepare( 'SELECT price FROM ec_optionitemquantity WHERE optionitemquantity_id = %d AND product_id = %d AND price >= 0', $variant_id, $product_id ) )
					: $wpdb->get_var( $wpdb->prepare( 'SELECT price FROM ec_product WHERE product_id = %d', $product_id ) );
				if ( null === $price ) {
					return false;
				}
			}
			$price = round( (float) $price, 3 );
			$last  = self::latest( $product_id, $variant_id );
			if ( $last && abs( (float) $last->price - $price ) < 0.0005 ) {
				return false;
			}
			$wpdb->insert(
				'ec_price_history',
				array(
					'product_id'            => $product_id,
					'optionitemquantity_id' => $variant_id,
					'price'                 => $price,
					'recorded_at'           => gmdate( 'Y-m-d H:i:s' ),
				),
				array( '%d', '%d', '%f', '%s' )
			);
			self::$prior = array();
			return true;
		}

		/**
		 * The newest row kept for a product: the current price and when it began.
		 *
		 * @param int $product_id Product.
		 * @param int $variant_id Variant ( 0: the product ).
		 * @return object|null price_history_id, price, recorded_at ( UTC ).
		 */
		public static function latest( $product_id, $variant_id = 0 ) {
			global $wpdb;
			return $wpdb->get_row( $wpdb->prepare( 'SELECT price_history_id, price, recorded_at FROM ec_price_history WHERE product_id = %d AND optionitemquantity_id = %d ORDER BY recorded_at DESC, price_history_id DESC LIMIT 1', (int) $product_id, (int) $variant_id ) );
		}

		/**
		 * The lowest price charged in the 30 days before a reduction began. For a compare-at sale the reduction began when the
		 * current price did; for an offer, today. Null while too little history is kept ( a product on sale when 6.0.5 arrived
		 * shows no figure until it has a price from before the sale ).
		 *
		 * @param int  $product_id Product.
		 * @param bool $from_now   Count back from now ( an offer ) rather than from the current price's start.
		 * @return float|null
		 */
		public static function prior_price( $product_id, $from_now = false ) {
			global $wpdb;
			$product_id = (int) $product_id;
			$cache_key  = $product_id . ( $from_now ? 'n' : 's' );
			if ( array_key_exists( $cache_key, self::$prior ) ) {
				return self::$prior[ $cache_key ];
			}
			self::$prior[ $cache_key ] = null;
			if ( $product_id <= 0 || ! self::ready() ) {
				return null;
			}
			self::record( $product_id ); /* a price changed without a save ( an import ) starts here */
			$latest = self::latest( $product_id );
			if ( ! $latest ) {
				return null;
			}
			$start        = $from_now ? gmdate( 'Y-m-d H:i:s' ) : (string) $latest->recorded_at;
			$window_start = gmdate( 'Y-m-d H:i:s', strtotime( $start . ' UTC' ) - self::DAYS * DAY_IN_SECONDS );
			$before       = $wpdb->get_var( $wpdb->prepare( 'SELECT price FROM ec_price_history WHERE product_id = %d AND optionitemquantity_id = 0 AND recorded_at < %s ORDER BY recorded_at DESC, price_history_id DESC LIMIT 1', $product_id, $window_start ) );
			$inside       = $wpdb->get_var( $wpdb->prepare( 'SELECT MIN( price ) FROM ec_price_history WHERE product_id = %d AND optionitemquantity_id = 0 AND recorded_at >= %s AND recorded_at < %s', $product_id, $window_start, $start ) );
			$prices       = array();
			foreach ( array( $before, $inside ) as $value ) {
				if ( null !== $value ) {
					$prices[] = (float) $value;
				}
			}
			if ( $from_now && ! $prices ) {
				$prices[] = (float) $latest->price; /* an offer on a price unchanged for 30 days: that price */
			}
			$prior = $prices ? min( $prices ) : null;
			/**
			 * The lowest price in the 30 days before a reduction.
			 *
			 * @since 6.0.5
			 * @param float|null $prior      Price, or null when unknown.
			 * @param int        $product_id Product.
			 * @param bool       $from_now   An offer's reduction ( counted from today ).
			 * @param int        $variant_id Variant ( 0: the product ).
			 */
			$prior                     = apply_filters( 'wp_easycart_prior_price', $prior, $product_id, $from_now, 0 );
			self::$prior[ $cache_key ] = ( null === $prior ) ? null : (float) $prior;
			return self::$prior[ $cache_key ];
		}

		/**
		 * Show the figure? Settings › Products ( ec_option_show_prior_price ); while never saved, on for a store in the EU.
		 *
		 * @return bool
		 */
		public static function enabled() {
			$saved = get_option( 'ec_option_show_prior_price', null );
			if ( null === $saved || false === $saved || '' === $saved ) {
				$saved = self::default_on() ? '1' : '0';
			}
			/**
			 * Whether struck prices show the lowest price of the last 30 days.
			 *
			 * @since 6.0.5
			 * @param bool $enabled Enabled.
			 */
			return (bool) apply_filters( 'wp_easycart_show_prior_price', '1' === (string) $saved );
		}

		/**
		 * While an offer lowers a product's price, the price struck through is the price before the offer, never the compare-at
		 * price ( 6.0.5, Matt's call "B" ): the note then names the lowest price of the 30 days before the offer ( it began today ),
		 * and a reduction is measured from that figure ( EU court, Aldi Süd, C-330/23 ), so the struck price is the one the note
		 * speaks of. Only while the store shows the note ( enabled() ); other stores keep the compare-at price struck.
		 *
		 * @since 6.0.5
		 * @return bool
		 */
		public static function strike_before_offer() {
			/**
			 * Strike the price before the offer, not the compare-at price, while an offer runs.
			 *
			 * @since 6.0.5
			 * @param bool $strike On while the lowest price of the last 30 days shows.
			 */
			return (bool) apply_filters( 'wp_easycart_prior_price_strike_before_offer', self::enabled() );
		}

		/**
		 * On by default: the store is in the EU or the EEA.
		 *
		 * @return bool
		 */
		public static function default_on() {
			if ( class_exists( 'wp_easycart_withdrawals' ) && method_exists( 'wp_easycart_withdrawals', 'store_in_eu' ) ) {
				return (bool) wp_easycart_withdrawals::store_in_eu();
			}
			return class_exists( 'wp_easycart_vat' ) && class_exists( 'wp_easycart_carriers' ) && wp_easycart_vat::in_eu( wp_easycart_carriers::store_country() );
		}

		/**
		 * Does the product's price depend on a variant's own price ( no single figure fits )?
		 *
		 * @param object $product ec_product.
		 * @return bool
		 */
		private static function priced_by_variant( $product ) {
			global $wpdb;
			if ( empty( $product->use_optionitem_quantity_tracking ) ) {
				return false;
			}
			return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT optionitemquantity_id FROM ec_optionitemquantity WHERE product_id = %d AND price >= 0 LIMIT 1', (int) $product->product_id ) );
		}

		/**
		 * The note's words for a price: "Lowest price in the last 30 days: €20.00" ( phrase product_details_prior_price ).
		 *
		 * @since 6.0.5
		 * @param float $prior Price.
		 * @return string Plain text.
		 */
		public static function note_text( $prior ) {
			$amount = isset( $GLOBALS['currency'] ) && is_object( $GLOBALS['currency'] ) ? $GLOBALS['currency']->get_currency_display( $prior ) : number_format( (float) $prior, 2 );
			$words  = function_exists( 'wp_easycart_language' ) ? (string) wp_easycart_language()->get_text( 'product_details', 'product_details_prior_price' ) : '';
			if ( '' === trim( wp_strip_all_tags( $words ) ) ) {
				$words = 'Lowest price in the last 30 days: [price]';
			}
			$words  = html_entity_decode( wp_strip_all_tags( $words ), ENT_QUOTES, 'UTF-8' );
			$amount = html_entity_decode( wp_strip_all_tags( $amount ), ENT_QUOTES, 'UTF-8' );
			return false !== strpos( $words, '[price]' ) ? str_replace( '[price]', $amount, $words ) : $words . ' ' . $amount;
		}

		/**
		 * The product's variants that set their own price, with their lowest price of the 30 days before ( null when unknown ). Their
		 * current prices are recorded first. Two queries for every variant together.
		 *
		 * @since 6.0.5
		 * @param int  $product_id Product.
		 * @param bool $from_now   Count back from now ( an offer ).
		 * @return array optionitemquantity_id => object( key, price, enabled, prior ).
		 */
		public static function variant_rows( $product_id, $from_now = false ) {
			global $wpdb;
			$product_id = (int) $product_id;
			if ( $product_id <= 0 || ! self::ready() ) {
				return array();
			}
			$variants = $wpdb->get_results( $wpdb->prepare( 'SELECT optionitemquantity_id, optionitem_id_1, optionitem_id_2, optionitem_id_3, optionitem_id_4, optionitem_id_5, price, is_enabled FROM ec_optionitemquantity WHERE product_id = %d AND price >= 0', $product_id ) );
			if ( ! $variants ) {
				return array();
			}
			$history = array();
			foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT optionitemquantity_id, price, recorded_at FROM ec_price_history WHERE product_id = %d AND optionitemquantity_id > 0 ORDER BY recorded_at ASC, price_history_id ASC', $product_id ) ) as $row ) {
				$history[ (int) $row->optionitemquantity_id ][] = $row;
			}
			$now = gmdate( 'Y-m-d H:i:s' );
			$out = array();
			foreach ( $variants as $variant ) {
				$id    = (int) $variant->optionitemquantity_id;
				$price = round( (float) $variant->price, 3 );
				$rows  = isset( $history[ $id ] ) ? $history[ $id ] : array();
				$last  = $rows ? end( $rows ) : null;
				if ( ! $last || abs( (float) $last->price - $price ) >= 0.0005 ) {
					$wpdb->insert( 'ec_price_history', array( 'product_id' => $product_id, 'optionitemquantity_id' => $id, 'price' => $price, 'recorded_at' => $now ), array( '%d', '%d', '%f', '%s' ) );
					$last   = (object) array( 'price' => $price, 'recorded_at' => $now );
					$rows[] = $last;
				}
				$start        = $from_now ? $now : (string) $last->recorded_at;
				$window_start = gmdate( 'Y-m-d H:i:s', strtotime( $start . ' UTC' ) - self::DAYS * DAY_IN_SECONDS );
				$prices       = array();
				$before       = null;
				foreach ( $rows as $row ) {
					if ( (string) $row->recorded_at < $window_start ) {
						$before = (float) $row->price;
					} elseif ( (string) $row->recorded_at < $start ) {
						$prices[] = (float) $row->price;
					}
				}
				if ( null !== $before ) {
					$prices[] = $before;
				}
				if ( $from_now && ! $prices ) {
					$prices[] = $price;
				}
				$prior      = $prices ? min( $prices ) : null;
				$prior      = apply_filters( 'wp_easycart_prior_price', $prior, $product_id, $from_now, $id );
				$out[ $id ] = (object) array(
					'key'     => (int) $variant->optionitem_id_1 . (int) $variant->optionitem_id_2 . (int) $variant->optionitem_id_3 . (int) $variant->optionitem_id_4 . (int) $variant->optionitem_id_5,
					'price'   => $price,
					'enabled' => ! empty( $variant->is_enabled ),
					'prior'   => ( null === $prior ) ? null : (float) $prior,
				);
			}
			return $out;
		}

		/**
		 * A recorded price on the basis this shopper sees the product's prices ( 6.0.5, Matt: a shopper charged another VAT rate saw
		 * the figure at the store's own rate ). History keeps the price as saved; a store whose prices include VAT shows a shopper whose
		 * rate differs ( a country without VAT, a VAT number ) every price at that rate ( ec_product::get_updated_price(), the price and
		 * the compare-at price beside the note ), so the figure is worked out the same way, to the cent.
		 *
		 * @since 6.0.5
		 * @param object $product ec_product.
		 * @param float  $price   A recorded price.
		 * @return float
		 */
		public static function basis( $product, $price ) {
			$price = (float) $price;
			if ( ! is_object( $product ) || ! method_exists( $product, 'get_updated_price' ) ) {
				return $price;
			}
			/* The shopper's place decides the rate ( as get_updated_price() reads it ). */
			$where = '';
			if ( isset( $GLOBALS['ec_cart_data'] ) && is_object( $GLOBALS['ec_cart_data'] ) && isset( $GLOBALS['ec_cart_data']->cart_data ) && is_object( $GLOBALS['ec_cart_data']->cart_data ) ) {
				$where = ( isset( $GLOBALS['ec_cart_data']->cart_data->shipping_country ) ? (string) $GLOBALS['ec_cart_data']->cart_data->shipping_country : '' ) . ( isset( $GLOBALS['ec_cart_data']->shipping_state ) ? (string) $GLOBALS['ec_cart_data']->shipping_state : '' );
			}
			if ( '' === $where && isset( $GLOBALS['ec_user'] ) && is_object( $GLOBALS['ec_user'] ) && isset( $GLOBALS['ec_user']->shipping ) && is_object( $GLOBALS['ec_user']->shipping ) ) {
				$where = ( isset( $GLOBALS['ec_user']->shipping->country ) ? (string) $GLOBALS['ec_user']->shipping->country : '' ) . ( isset( $GLOBALS['ec_user']->shipping->state ) ? (string) $GLOBALS['ec_user']->shipping->state : '' );
			}
			$key = ( isset( $product->product_id ) ? (int) $product->product_id : 0 ) . '|' . $price . '|' . $where;
			if ( ! array_key_exists( $key, self::$basis ) ) {
				self::$basis[ $key ] = (float) $product->get_updated_price( $price );
			}
			return self::$basis[ $key ];
		}

		/**
		 * The figure the note prints for a recorded price: basis(), and, where the store shows prices with and without VAT, the price
		 * with VAT ( as the unit price note: a consumer price includes VAT ). Filter wp_easycart_prior_price_shown.
		 *
		 * @since 6.0.5
		 * @param object $product ec_product.
		 * @param float  $price   A recorded price.
		 * @return float
		 */
		public static function shown( $product, $price ) {
			$amount = self::basis( $product, $price );
			if ( is_object( $product ) && ! empty( $product->vat_rate ) && get_option( 'ec_option_show_multiple_vat_pricing' ) && method_exists( $product, 'get_product_price_with_vat' ) && isset( $product->price_options ) && isset( $GLOBALS['currency'] ) && is_object( $GLOBALS['currency'] ) ) {
				$converted = (float) $GLOBALS['currency']->convert_price( $product->price_options );
				if ( $converted > 0 ) {
					$amount = $amount * ( (float) $product->get_product_price_with_vat() / $converted );
				}
			}
			/**
			 * The lowest price of the last 30 days as the note prints it ( the shopper's VAT basis ).
			 *
			 * @since 6.0.5
			 * @param float  $amount  Amount.
			 * @param object $product ec_product.
			 * @param float  $price   The price as recorded.
			 */
			return (float) apply_filters( 'wp_easycart_prior_price_shown', $amount, $product, (float) $price );
		}

		/**
		 * The note's words for each variant that sets its own price and shows a reduction ( the product's compare-at price above the
		 * variant's, or an offer ), keyed as the product page keys its variants ( the five option item ids run together ).
		 *
		 * @since 6.0.5
		 * @param object $product ec_product.
		 * @param bool   $offer   The reduction shown is an offer's.
		 * @return array key => text.
		 */
		public static function variant_notes( $product, $offer = false ) {
			$list  = isset( $product->list_price ) ? (float) $product->list_price : 0;
			$notes = array();
			foreach ( self::variant_rows( (int) $product->product_id, (bool) $offer ) as $variant ) {
				if ( ! $variant->enabled || null === $variant->prior ) {
					continue;
				}
				$price = self::basis( $product, $variant->price ); /* the list price is on the shopper's basis too */
				if ( $offer || ( $list > $price && $price > 0 ) ) {
					$notes[ $variant->key ] = self::note_text( self::shown( $product, $variant->prior ) );
				}
			}
			return $notes;
		}

		/**
		 * "Lowest price in the last 30 days: …" for a product showing a reduction ( a compare-at price above its price, or an
		 * offer's preview ), or ''. On a product page, a product whose variants set their own prices carries every variant's words
		 * too ( data-wpec-prior-variants ) and the note follows the variant chosen; before a choice, the product's own.
		 *
		 * @param object $product   ec_product ( product_id, price, list_price ).
		 * @param bool   $offer     The reduction shown is an offer's.
		 * @param string $class     Extra class ( listing | details ).
		 * @return string HTML.
		 */
		public static function note_html( $product, $offer = false, $class = '' ) {
			if ( ! is_object( $product ) || empty( $product->product_id ) || ! self::enabled() ) {
				return '';
			}
			$price   = isset( $product->price ) ? (float) $product->price : 0;
			$reduced = $offer || ( isset( $product->list_price ) && (float) $product->list_price > $price && $price > 0 );
			$base    = '';
			if ( $reduced ) {
				$prior = self::prior_price( (int) $product->product_id, (bool) $offer );
				$base  = ( null === $prior ) ? '' : self::note_text( self::shown( $product, $prior ) );
			}
			$variants = ( 'listing' !== $class && self::priced_by_variant( $product ) ) ? self::variant_notes( $product, (bool) $offer ) : array();
			if ( '' === $base && ! $variants ) {
				return '';
			}
			$attrs = '';
			if ( $variants ) {
				$attrs = ' data-wpec-prior-product="' . esc_attr( (int) $product->product_id ) . '" data-wpec-prior-base="' . esc_attr( $base ) . '" data-wpec-prior-variants="' . esc_attr( wp_json_encode( $variants, JSON_FORCE_OBJECT ) ) . '"' . ( '' === $base ? ' style="display:none"' : '' );
			}
			return '<div class="ec_product_prior_price' . ( '' !== $class ? ' ec_product_prior_price_' . esc_attr( $class ) : '' ) . '"' . $attrs . '>' . esc_html( $base ) . '</div>';
		}

		/**
		 * Enter the lowest price charged in the 30 days before a reduction that began before price history was kept ( the product
		 * editor's Pricing panel ): the current price is dated from the day the reduction began, and that price is kept as charged
		 * the day before. A lower price already on record in those 30 days still wins.
		 *
		 * @since 6.0.5
		 * @param int   $product_id Product.
		 * @param float $price      The lowest price in the 30 days before.
		 * @param int   $start      When the reduction began ( Unix time ); not in the future, at most two years ago.
		 * @return true|WP_Error
		 */
		public static function set_prior( $product_id, $price, $start ) {
			global $wpdb;
			$product_id = (int) $product_id;
			$price      = round( (float) $price, 3 );
			$start      = (int) $start;
			if ( $product_id <= 0 || ! self::ready() ) {
				return new WP_Error( 'product', __( 'This product cannot keep a price history yet.', 'wp-easycart' ) );
			}
			if ( $price <= 0 ) {
				return new WP_Error( 'price', __( 'Enter the price, above zero.', 'wp-easycart' ) );
			}
			if ( $start > time() || $start < time() - 2 * YEAR_IN_SECONDS ) {
				return new WP_Error( 'date', __( 'Enter the day the reduction began: today or earlier, within the last two years.', 'wp-easycart' ) );
			}
			self::record( $product_id );
			$latest = self::latest( $product_id );
			if ( ! $latest ) {
				return new WP_Error( 'product', __( 'This product cannot keep a price history yet.', 'wp-easycart' ) );
			}
			$when = gmdate( 'Y-m-d H:i:s', $start );
			if ( (string) $latest->recorded_at > $when ) {
				$wpdb->update( 'ec_price_history', array( 'recorded_at' => $when ), array( 'price_history_id' => (int) $latest->price_history_id ), array( '%s' ), array( '%d' ) );
			} else {
				$when = (string) $latest->recorded_at; /* the price on record began earlier: the figure counts back from then */
			}
			$wpdb->insert( 'ec_price_history', array( 'product_id' => $product_id, 'optionitemquantity_id' => 0, 'price' => $price, 'recorded_at' => gmdate( 'Y-m-d H:i:s', strtotime( $when . ' UTC' ) - 1 ) ), array( '%d', '%d', '%f', '%s' ) );
			self::$prior = array();
			/**
			 * The merchant entered the lowest price before a reduction.
			 *
			 * @since 6.0.5
			 * @param int   $product_id Product.
			 * @param float $price      Price.
			 * @param int   $start      When the reduction began.
			 */
			do_action( 'wp_easycart_prior_price_set', $product_id, $price, $start );
			return true;
		}

		/**
		 * Record every product whose price moved without a save ( imports, syncs, direct edits ), a batch a day.
		 *
		 * @return int Rows added.
		 */
		public static function sweep() {
			global $wpdb;
			if ( ! self::ready() ) {
				return 0;
			}
			$rows  = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT p.product_id, p.price FROM ec_product p LEFT JOIN ( SELECT h.product_id, h.price FROM ec_price_history h INNER JOIN ( SELECT product_id, MAX( price_history_id ) AS last_id FROM ec_price_history WHERE optionitemquantity_id = 0 GROUP BY product_id ) m ON m.last_id = h.price_history_id ) l ON l.product_id = p.product_id WHERE l.product_id IS NULL OR ABS( l.price - p.price ) >= 0.0005 LIMIT %d',
					self::SWEEP_BATCH
				)
			);
			$added = 0;
			foreach ( (array) $rows as $row ) {
				if ( self::record( (int) $row->product_id, (float) $row->price ) ) {
					++$added;
				}
			}
			/* Variants that set their own price. */
			$variants = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT q.product_id, q.optionitemquantity_id, q.price FROM ec_optionitemquantity q LEFT JOIN ( SELECT h.optionitemquantity_id, h.price FROM ec_price_history h INNER JOIN ( SELECT optionitemquantity_id, MAX( price_history_id ) AS last_id FROM ec_price_history WHERE optionitemquantity_id > 0 GROUP BY optionitemquantity_id ) m ON m.last_id = h.price_history_id ) l ON l.optionitemquantity_id = q.optionitemquantity_id WHERE q.price >= 0 AND ( l.optionitemquantity_id IS NULL OR ABS( l.price - q.price ) >= 0.0005 ) LIMIT %d',
					self::SWEEP_BATCH
				)
			);
			foreach ( (array) $variants as $row ) {
				if ( self::record( (int) $row->product_id, (float) $row->price, (int) $row->optionitemquantity_id ) ) {
					++$added;
				}
			}
			return $added;
		}
	}

	wp_easycart_price_history::hooks();

endif;
