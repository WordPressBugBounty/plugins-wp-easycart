<?php
/**
 * WP EasyCart — unit prices ( 6.0.5, admin review M16 ).
 *
 * A product sold by weight, volume, length, area or count says how much it holds ( ec_product.unit_measure + unit_measure_unit:
 * 750 g, 1.5 l, 12 ct ) and the price is shown per base unit beside it ( "€13.27 / kg" ), as EU law ( Directive 98/6/EC, Germany's
 * PAngV ), the UK's Price Marking Order and US state unit pricing rules ask. The base is the product's own choice
 * ( ec_product.unit_base: 100g, 1kg, 1l … ) or, left on automatic, 1 kg / 1 l / 1 m / 1 m² / 1 m³ for metric amounts and the
 * amount's own unit for US and imperial ones ( per oz, lb, fl oz, sq ft ), and per item for counts.
 *
 * The note shows next to the price on product pages, the store's product cards and the Elementor shop cards, follows the price the
 * shopper sees ( the price with VAT where the store shows both, an Offers price preview ) and, on the product page, the price the
 * options make ( ec-store.js wpeasycart_unit_price_update() ). Units are Google Merchant's tokens, so the PRO product feed sends them
 * as unit_pricing_measure / unit_pricing_base_measure when the Google card leaves those empty.
 *
 * @package WP_EasyCart
 * @since   6.0.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_unit_price' ) ) :

	/**
	 * Unit prices.
	 */
	class wp_easycart_unit_price {

		/** Database version with the columns. */
		const DB = 129;

		/**
		 * The columns exist.
		 *
		 * @return bool
		 */
		public static function ready() {
			return (int) get_option( 'ec_option_db_new_version' ) >= self::DB;
		}

		/**
		 * The units an amount can be given in ( Google Merchant's tokens ).
		 *
		 * @return array token => array( dimension, size in the dimension's measure: g, ml, cm, m², item; label ).
		 */
		public static function units() {
			$units = array(
				'mg'   => array( 'mass', 0.001, 'mg' ),
				'g'    => array( 'mass', 1, 'g' ),
				'kg'   => array( 'mass', 1000, 'kg' ),
				'oz'   => array( 'mass', 28.349523125, 'oz' ),
				'lb'   => array( 'mass', 453.59237, 'lb' ),
				'ml'   => array( 'volume', 1, 'ml' ),
				'cl'   => array( 'volume', 10, 'cl' ),
				'l'    => array( 'volume', 1000, 'l' ),
				'cbm'  => array( 'volume', 1000000, 'm³' ),
				'floz' => array( 'volume', 29.5735295625, 'fl oz' ),
				'gal'  => array( 'volume', 3785.411784, 'gal' ),
				'cm'   => array( 'length', 1, 'cm' ),
				'm'    => array( 'length', 100, 'm' ),
				'in'   => array( 'length', 2.54, 'in' ),
				'ft'   => array( 'length', 30.48, 'ft' ),
				'yd'   => array( 'length', 91.44, 'yd' ),
				'sqm'  => array( 'area', 1, 'm²' ),
				'sqft' => array( 'area', 0.09290304, 'sq ft' ),
				'ct'   => array( 'count', 1, '' ),
			);
			/**
			 * The units a product's contents can be given in.
			 *
			 * @since 6.0.5
			 * @param array $units token => array( dimension, size in g | ml | cm | m² | item, label ).
			 */
			return (array) apply_filters( 'wp_easycart_unit_price_units', $units );
		}

		/**
		 * The bases a unit price can be shown per.
		 *
		 * @return array key => array( quantity, unit token ).
		 */
		public static function bases() {
			$bases = array(
				'100g'  => array( 100, 'g' ),
				'1kg'   => array( 1, 'kg' ),
				'1oz'   => array( 1, 'oz' ),
				'1lb'   => array( 1, 'lb' ),
				'100ml' => array( 100, 'ml' ),
				'1l'    => array( 1, 'l' ),
				'1floz' => array( 1, 'floz' ),
				'1gal'  => array( 1, 'gal' ),
				'1cbm'  => array( 1, 'cbm' ),
				'1m'    => array( 1, 'm' ),
				'1ft'   => array( 1, 'ft' ),
				'1sqm'  => array( 1, 'sqm' ),
				'1sqft' => array( 1, 'sqft' ),
				'1ct'   => array( 1, 'ct' ),
			);
			/**
			 * The bases a unit price can be shown per.
			 *
			 * @since 6.0.5
			 * @param array $bases key => array( quantity, unit token ).
			 */
			return (array) apply_filters( 'wp_easycart_unit_price_bases', $bases );
		}

		/**
		 * The base used when the product leaves it on automatic: metric amounts per 1 kg / 1 l / 1 m / 1 m² / 1 m³, US and
		 * imperial amounts per their own unit, counts per item.
		 *
		 * @param string $unit Unit token.
		 * @return string Base key, '' for an unknown unit.
		 */
		public static function default_base( $unit ) {
			$map  = array(
				'mg'   => '1kg',
				'g'    => '1kg',
				'kg'   => '1kg',
				'oz'   => '1oz',
				'lb'   => '1lb',
				'ml'   => '1l',
				'cl'   => '1l',
				'l'    => '1l',
				'cbm'  => '1cbm',
				'floz' => '1floz',
				'gal'  => '1gal',
				'cm'   => '1m',
				'm'    => '1m',
				'in'   => '1ft',
				'ft'   => '1ft',
				'yd'   => '1ft',
				'sqm'  => '1sqm',
				'sqft' => '1sqft',
				'ct'   => '1ct',
			);
			$base = isset( $map[ $unit ] ) ? $map[ $unit ] : '';
			/**
			 * The base a unit is shown per when the product leaves it on automatic.
			 *
			 * @since 6.0.5
			 * @param string $base Base key.
			 * @param string $unit Unit token.
			 */
			$base = (string) apply_filters( 'wp_easycart_unit_price_default_base', $base, $unit );
			return isset( self::bases()[ $base ] ) ? $base : '';
		}

		/**
		 * A unit token, cleaned ( '' when not a unit ).
		 *
		 * @param mixed $unit Unit.
		 * @return string
		 */
		public static function clean_unit( $unit ) {
			$unit = strtolower( preg_replace( '/[^a-z]/i', '', (string) $unit ) );
			return isset( self::units()[ $unit ] ) ? $unit : '';
		}

		/**
		 * A base key, cleaned ( '' = automatic ).
		 *
		 * @param mixed $base Base.
		 * @return string
		 */
		public static function clean_base( $base ) {
			$base = strtolower( preg_replace( '/[^a-z0-9]/i', '', (string) $base ) );
			return isset( self::bases()[ $base ] ) ? $base : '';
		}

		/**
		 * The product's amount, unit and base, or null when it has none.
		 *
		 * @param object $product Product ( ec_product or a row ).
		 * @return array|null { measure, unit, base, quantity, base_unit }.
		 */
		public static function setup( $product ) {
			if ( ! is_object( $product ) || empty( $product->unit_measure ) ) {
				return null;
			}
			$measure = (float) $product->unit_measure;
			$unit    = self::clean_unit( isset( $product->unit_measure_unit ) ? $product->unit_measure_unit : '' );
			if ( $measure <= 0 || '' === $unit ) {
				return null;
			}
			$units = self::units();
			$bases = self::bases();
			$base  = self::clean_base( isset( $product->unit_base ) ? $product->unit_base : '' );
			if ( '' === $base || $units[ $bases[ $base ][1] ][0] !== $units[ $unit ][0] ) {
				$base = self::default_base( $unit );
			}
			if ( '' === $base ) {
				return null;
			}
			return array(
				'measure'   => $measure,
				'unit'      => $unit,
				'base'      => $base,
				'quantity'  => (float) $bases[ $base ][0],
				'base_unit' => $bases[ $base ][1],
			);
		}

		/**
		 * What the price is multiplied by to give the unit price ( 0 when the product has no unit price ).
		 *
		 * @param object $product Product.
		 * @return float
		 */
		public static function factor( $product ) {
			$setup = self::setup( $product );
			if ( ! $setup ) {
				return 0.0;
			}
			$units = self::units();
			$held  = $setup['measure'] * (float) $units[ $setup['unit'] ][1];
			$per   = $setup['quantity'] * (float) $units[ $setup['base_unit'] ][1];
			return ( $held > 0 && $per > 0 ) ? $per / $held : 0.0;
		}

		/**
		 * The base as shoppers read it: "kg", "100 g", "fl oz", "item".
		 *
		 * @param string $base Base key.
		 * @return string
		 */
		public static function base_label( $base ) {
			$bases = self::bases();
			if ( ! isset( $bases[ $base ] ) ) {
				return '';
			}
			$units = self::units();
			$unit  = $bases[ $base ][1];
			if ( 'count' === $units[ $unit ][0] ) {
				$label = self::text( 'product_details_unit_item', 'item' );
			} else {
				$label = (string) $units[ $unit ][2];
			}
			$quantity = (float) $bases[ $base ][0];
			if ( 1.0 !== $quantity ) {
				$label = self::number( $quantity ) . ' ' . $label;
			}
			return $label;
		}

		/**
		 * A quantity in the store's decimal style ( no trailing zeros ).
		 *
		 * @param float $number Number.
		 * @return string
		 */
		public static function number( $number ) {
			$text = rtrim( rtrim( number_format( (float) $number, 4, '.', '' ), '0' ), '.' );
			$dot  = (string) get_option( 'ec_option_currency_decimal_symbol', '.' );
			return ( '' !== $dot && '.' !== $dot ) ? str_replace( '.', $dot, $text ) : $text;
		}

		/**
		 * A product_details phrase, else its English.
		 *
		 * @param string $key      Phrase key.
		 * @param string $fallback English.
		 * @return string Plain text.
		 */
		private static function text( $key, $fallback ) {
			$words = function_exists( 'wp_easycart_language' ) ? (string) wp_easycart_language()->get_text( 'product_details', $key ) : '';
			$words = html_entity_decode( wp_strip_all_tags( $words ), ENT_QUOTES, 'UTF-8' );
			return '' === trim( $words ) ? $fallback : $words;
		}

		/**
		 * The store's sentence, both tokens left in: "[price] / [unit]".
		 *
		 * @return string
		 */
		public static function phrase() {
			$words = self::text( 'product_details_unit_price', '[price] / [unit]' );
			return ( false === strpos( $words, '[price]' ) ) ? '[price] / [unit]' : $words;
		}

		/**
		 * The sentence with the base filled in and [price] left for the amount: "[price] / kg".
		 *
		 * @param string $base Base key.
		 * @return string
		 */
		public static function words( $base ) {
			return str_replace( '[unit]', self::base_label( $base ), self::phrase() );
		}

		/**
		 * Whether the product shows a price this visitor may see ( a unit price never shows without it ).
		 *
		 * @param object $product Product.
		 * @return bool
		 */
		public static function shows_price( $product ) {
			if ( ! empty( $product->is_donation ) || ! empty( $product->is_subscription_item ) || ! empty( $product->show_custom_price_range ) || ! empty( $product->pricing_per_sq_foot ) ) {
				return false;
			}
			if ( ! empty( $product->replace_price_label ) && isset( $product->enable_price_label ) && in_array( (int) $product->enable_price_label, array( 1, 2, 4, 5, 6, 7 ), true ) ) {
				return false;
			}
			if ( ! empty( $product->login_for_pricing ) && method_exists( $product, 'is_login_for_pricing_valid' ) && ! $product->is_login_for_pricing_valid() ) {
				return false;
			}
			if ( ( ! empty( $product->is_catalog_mode ) && get_option( 'ec_option_hide_price_seasonal' ) ) || ( ! empty( $product->is_inquiry_mode ) && get_option( 'ec_option_hide_price_inquiry' ) ) ) {
				return false;
			}
			return true;
		}

		/**
		 * The price a unit price is worked out from: listings the card's price, product pages the price with the options picked
		 * by default ( with VAT where the store shows both prices; an Offers price preview where one shows ).
		 *
		 * @param object $product Product.
		 * @param string $context listing | details.
		 * @return array { price, vat ( the note follows the VAT price ), rules ( Offers rules, or null ) }.
		 */
		public static function shown_price( $product, $context ) {
			$answer = array(
				'price' => (float) ( isset( $product->price ) ? $product->price : 0 ),
				'vat'   => false,
				'rules' => null,
			);
			if ( 'details' !== $context ) {
				return $answer;
			}
			$base            = ( isset( $product->price_options ) && is_numeric( $product->price_options ) ) ? (float) $product->price_options : $answer['price'];
			$answer['price'] = $base;
			if ( ! empty( $product->vat_rate ) && get_option( 'ec_option_show_multiple_vat_pricing' ) && method_exists( $product, 'get_product_price_with_vat' ) && isset( $GLOBALS['currency'] ) && is_object( $GLOBALS['currency'] ) ) {
				$converted = (float) $GLOBALS['currency']->convert_price( $base );
				if ( $converted > 0 ) {
					$answer['price'] = $base * ( (float) $product->get_product_price_with_vat() / $converted );
				}
				$answer['vat'] = true;
				return $answer;
			}
			if ( $base > 0 && function_exists( 'wp_easycart_offers_active' ) && wp_easycart_offers_active() && class_exists( 'ec_offer_display' ) && method_exists( 'ec_offer_display', 'get_product_price_preview' ) ) {
				$list    = isset( $product->list_price ) ? (float) $product->list_price : 0;
				$offered = ec_offer_display::get_product_price_preview( $product->product_id, $product->manufacturer_id, $base, $list );
				if ( false !== $offered && (float) $offered < $base ) {
					$answer['price'] = (float) $offered;
					if ( method_exists( 'ec_offer_display', 'get_product_price_rules' ) ) {
						$answer['rules'] = array_values( (array) ec_offer_display::get_product_price_rules( $product->product_id, $product->manufacturer_id, $base, $list ) );
					}
				}
			}
			return $answer;
		}

		/**
		 * The unit price beside a product's price: '<div class="ec_product_unit_price">€13.27 / kg</div>', or '' when the product
		 * has no amount set or shows no price. On product pages it carries what ec-store.js needs to follow the options.
		 *
		 * @param object     $product Product ( ec_product ).
		 * @param string     $context listing | details.
		 * @param float|null $price   The price shown, when the caller knows it better.
		 * @return string HTML.
		 */
		public static function note_html( $product, $context = 'listing', $price = null ) {
			if ( ! is_object( $product ) || empty( $product->product_id ) || ! self::shows_price( $product ) ) {
				return '';
			}
			$factor = self::factor( $product );
			if ( $factor <= 0 ) {
				return '';
			}
			$setup = self::setup( $product );
			$shown = self::shown_price( $product, $context );
			if ( null !== $price ) {
				$shown['price'] = (float) $price;
			}
			if ( $shown['price'] <= 0 ) {
				return '';
			}
			$amount = $shown['price'] * $factor;
			$money  = isset( $GLOBALS['currency'] ) && is_object( $GLOBALS['currency'] ) ? $GLOBALS['currency']->get_currency_display( $amount ) : number_format( $amount, 2 );
			$money  = html_entity_decode( wp_strip_all_tags( $money ), ENT_QUOTES, 'UTF-8' );
			$words  = self::words( $setup['base'] );
			$text   = str_replace( '[price]', $money, $words );
			/**
			 * The unit price shown beside a product's price.
			 *
			 * @since 6.0.5
			 * @param string $text    "€13.27 / kg".
			 * @param object $product Product.
			 * @param float  $amount  The unit price.
			 * @param array  $setup   { measure, unit, base, quantity, base_unit }.
			 */
			$text = (string) apply_filters( 'wp_easycart_unit_price_text', $text, $product, $amount, $setup );
			if ( '' === trim( $text ) ) {
				return '';
			}
			$attrs = '';
			if ( 'details' === $context ) {
				$attrs = ' data-wpec-unit-product="' . esc_attr( (int) $product->product_id ) . '" data-wpec-unit-factor="' . esc_attr( $factor ) . '" data-wpec-unit-words="' . esc_attr( $words ) . '"';
				if ( $shown['vat'] ) {
					$attrs .= ' data-wpec-unit-vat="1"';
				}
				if ( is_array( $shown['rules'] ) && $shown['rules'] ) {
					$attrs .= ' data-wpec-unit-rules="' . esc_attr( wp_json_encode( $shown['rules'] ) ) . '"';
				}
			}
			return '<div class="ec_product_unit_price ec_product_unit_price_' . esc_attr( 'details' === $context ? 'details' : 'listing' ) . '"' . $attrs . '>' . esc_html( $text ) . '</div>';
		}

		/**
		 * Google Merchant's unit_pricing_measure ( "750g" ) and unit_pricing_base_measure ( "1kg" ), or '' for both.
		 *
		 * @param object $product Product.
		 * @return array { measure, base }.
		 */
		public static function google( $product ) {
			$setup = self::setup( $product );
			if ( ! $setup ) {
				return array(
					'measure' => '',
					'base'    => '',
				);
			}
			return array(
				'measure' => rtrim( rtrim( number_format( $setup['measure'], 4, '.', '' ), '0' ), '.' ) . $setup['unit'],
				'base'    => rtrim( rtrim( number_format( $setup['quantity'], 4, '.', '' ), '0' ), '.' ) . $setup['base_unit'],
			);
		}
	}

endif;
