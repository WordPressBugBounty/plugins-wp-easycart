<?php
/**
 * WP EasyCart — VAT / GST rates by product ( tax classes, 6.0.5, admin review M6 ).
 *
 * A product charges VAT at the standard rate ( the VAT settings: one rate, or a rate per country ) unless its tax class says
 * otherwise ( ec_product.tax_class, the product editor's Tax card ):
 *   reduced   the reduced rate ( books, food … ): ec_option_tax_class_reduced_rate, by country in ec_option_tax_class_reduced_countries
 *   reduced2  a second reduced rate: ec_option_tax_class_reduced2_rate / _reduced2_countries
 *   zero      0% ( children's clothes in the UK … )
 * A country's own rate ( "DE=7,FR=5.5", Settings › Taxes › VAT ) wins over the class rate; a class with no rate set charges the
 * standard rate. A business customer abroad keeps the business rate on every class ( ec_tax::business_vat() ).
 *
 * ec_tax splits the order's VAT subtotal by rate, in the share of each rate's lines ( so discounts spread over every rate ), only
 * when a line has a class; a store whose products have none charges exactly what it did before. The VAT each rate charged is kept on
 * the order ( ec_order.vat_breakdown, JSON [ { rate, base, vat } ] ) and each line's rate on its line ( ec_orderdetail.vat_rate );
 * receipts, invoices, the order confirmation and My Account show one VAT line per rate when there is more than one.
 *
 * @package WP_EasyCart
 * @since   6.0.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_tax_classes' ) ) :

	/**
	 * Tax classes.
	 */
	class wp_easycart_tax_classes {

		/** Database version with the columns. */
		const DB = 128;

		/**
		 * The columns exist.
		 *
		 * @return bool
		 */
		public static function ready() {
			return (int) get_option( 'ec_option_db_new_version' ) >= self::DB;
		}

		/**
		 * The classes besides the standard rate.
		 *
		 * @return array key => label.
		 */
		public static function classes() {
			$classes = array(
				'reduced'  => __( 'Reduced rate', 'wp-easycart' ),
				'reduced2' => __( 'Second reduced rate', 'wp-easycart' ),
				'zero'     => __( 'Zero rate', 'wp-easycart' ),
			);
			/**
			 * The tax classes besides the standard rate ( a class with an option ec_option_tax_class_<key>_rate charges it ).
			 *
			 * @since 6.0.5
			 * @param array $classes key => label.
			 */
			return (array) apply_filters( 'wp_easycart_tax_classes', $classes );
		}

		/**
		 * A product's class, cleaned ( '' = standard ).
		 *
		 * @param mixed $class Stored class.
		 * @return string
		 */
		public static function clean( $class ) {
			$class = sanitize_key( (string) $class );
			return isset( self::classes()[ $class ] ) ? $class : '';
		}

		/**
		 * The class's own rate for everywhere ( null = no rate set: the standard rate applies ).
		 *
		 * @param string $class Class.
		 * @return float|null
		 */
		public static function default_rate( $class ) {
			$class = self::clean( $class );
			if ( '' === $class ) {
				return null;
			}
			if ( 'zero' === $class ) {
				return 0.0;
			}
			$rate = trim( (string) get_option( 'ec_option_tax_class_' . $class . '_rate', '' ) );
			return ( '' === $rate || ! is_numeric( $rate ) ) ? null : max( 0.0, (float) $rate );
		}

		/**
		 * The class's rates by country ( "DE=7,FR=5.5" ).
		 *
		 * @param string $class Class.
		 * @return array ISO code => rate.
		 */
		public static function country_rates( $class ) {
			$class = self::clean( $class );
			if ( '' === $class || 'zero' === $class ) {
				return array();
			}
			$out = array();
			foreach ( explode( ',', (string) get_option( 'ec_option_tax_class_' . $class . '_countries', '' ) ) as $pair ) {
				$parts = explode( '=', $pair, 2 );
				if ( 2 === count( $parts ) ) {
					$code = strtoupper( trim( $parts[0] ) );
					$rate = trim( $parts[1] );
					if ( 1 === preg_match( '/^[A-Z]{2}$/', $code ) && is_numeric( $rate ) && ! isset( $out[ $code ] ) ) {
						$out[ $code ] = max( 0.0, (float) $rate ); /* a country's first entry */
					}
				}
			}
			return $out;
		}

		/**
		 * The class's rate for a country: its own rate there, else the class rate, else null ( the standard rate ).
		 *
		 * @param string $class   Class.
		 * @param string $country ISO code ( '' = the class rate ).
		 * @return float|null
		 */
		public static function rate( $class, $country = '' ) {
			$class = self::clean( $class );
			if ( '' === $class ) {
				return null;
			}
			$country = strtoupper( trim( (string) $country ) );
			$rates   = self::country_rates( $class );
			$rate    = ( '' !== $country && isset( $rates[ $country ] ) ) ? $rates[ $country ] : self::default_rate( $class );
			/**
			 * A tax class's rate for a country.
			 *
			 * @since 6.0.5
			 * @param float|null $rate    Percent, or null for the standard rate.
			 * @param string     $class   Class.
			 * @param string     $country ISO code.
			 */
			$rate = apply_filters( 'wp_easycart_tax_class_rate', $rate, $class, $country );
			return ( null === $rate || '' === $rate ) ? null : (float) $rate;
		}

		/**
		 * Any product uses a class ( kept a request ).
		 *
		 * @return bool
		 */
		public static function in_use() {
			static $used = null;
			if ( null === $used ) {
				global $wpdb;
				$used = self::ready() && (bool) $wpdb->get_var( "SELECT product_id FROM ec_product WHERE tax_class <> '' LIMIT 1" );
			}
			return $used;
		}

		/**
		 * The checkout's tax ( for each line's rate as the order's lines are saved ).
		 *
		 * @var ec_tax|null
		 */
		private static $tax = null;

		/**
		 * Breakdowns read this request, by order.
		 *
		 * @var array
		 */
		private static $read = array();

		/**
		 * Keep the VAT each rate charged on a new order ( ec_order.vat_breakdown ) and remember the order's tax for its lines.
		 * Called by ec_db::insert_order() and insert_subscription_order() after the insert.
		 *
		 * @param int    $order_id Order.
		 * @param ec_tax $tax      The checkout's tax.
		 */
		public static function record_order( $order_id, $tax ) {
			global $wpdb;
			self::$tax = is_object( $tax ) ? $tax : null;
			if ( ! self::ready() || ! $order_id || ! is_object( $tax ) || empty( $tax->vat_breakdown ) || ! is_array( $tax->vat_breakdown ) ) {
				return;
			}
			$wpdb->update( 'ec_order', array( 'vat_breakdown' => wp_json_encode( array_values( $tax->vat_breakdown ) ) ), array( 'order_id' => (int) $order_id ) );
			unset( self::$read[ (int) $order_id ] );
		}

		/**
		 * Keep a line's VAT rate ( ec_orderdetail.vat_rate ) on wp_easycart_order_detail_inserted.
		 *
		 * @param int    $orderdetail_id Line.
		 * @param object $line           Cart line ( or the subscription's product ).
		 */
		public static function record_line( $orderdetail_id, $line ) {
			global $wpdb;
			if ( ! self::ready() || ! $orderdetail_id || ! is_object( self::$tax ) || ! is_object( $line ) || empty( self::$tax->vat_enabled ) ) {
				return;
			}
			$enabled = isset( $line->vat_enabled ) ? ! empty( $line->vat_enabled ) : ( isset( $line->vat_rate ) && (float) $line->vat_rate > 0 );
			if ( ! $enabled ) {
				return;
			}
			$rate = method_exists( self::$tax, 'class_rate' ) ? self::$tax->class_rate( isset( $line->tax_class ) ? $line->tax_class : '' ) : (float) self::$tax->vat_rate;
			$wpdb->update( 'ec_orderdetail', array( 'vat_rate' => (float) $rate ), array( 'orderdetail_id' => (int) $orderdetail_id ) );
		}

		/**
		 * The VAT lines of an order: one per rate when the order kept more than one ( ec_order.vat_breakdown ), else empty
		 * ( the order's single VAT line stays as it was ). Read by order id when the object does not carry the column.
		 *
		 * @param object $order Order row or display.
		 * @return array of array( rate, base, vat ).
		 */
		public static function order_breakdown( $order ) {
			global $wpdb;
			if ( ! is_object( $order ) ) {
				return array();
			}
			$raw = isset( $order->vat_breakdown ) ? (string) $order->vat_breakdown : null;
			if ( null === $raw ) {
				$order_id = isset( $order->order_id ) ? (int) $order->order_id : 0;
				if ( ! $order_id || ! self::ready() ) {
					return array();
				}
				if ( ! isset( self::$read[ $order_id ] ) ) {
					self::$read[ $order_id ] = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT vat_breakdown FROM ec_order WHERE order_id = %d', $order_id ) );
				}
				$raw = self::$read[ $order_id ];
			}
			if ( '' === $raw ) {
				return array();
			}
			$rows = json_decode( $raw, true );
			if ( ! is_array( $rows ) || count( $rows ) < 2 ) {
				return array();
			}
			$out = array();
			foreach ( $rows as $row ) {
				if ( isset( $row['rate'], $row['vat'] ) ) {
					$out[] = array(
						'rate' => (float) $row['rate'],
						'base' => isset( $row['base'] ) ? (float) $row['base'] : 0.0,
						'vat'  => (float) $row['vat'],
					);
				}
			}
			return count( $out ) > 1 ? $out : array();
		}

		/**
		 * The order's VAT rows for receipts and invoices: "label rate%" => amount, one per rate when the order charged more than one.
		 *
		 * @param object $order Order.
		 * @param string $label The VAT label ( "VAT @" or "VAT" ).
		 * @return array of array( label, amount ), empty for a single rate.
		 */
		public static function rows( $order, $label ) {
			$out = array();
			foreach ( self::order_breakdown( $order ) as $row ) {
				$out[] = array( rtrim( (string) $label ) . ' ' . self::rate_text( $row['rate'] ) . '%', $row['vat'] );
			}
			return $out;
		}

		/**
		 * A rate written short: 7, 5.5, 20.
		 *
		 * @param float $rate Percent.
		 * @return string
		 */
		public static function rate_text( $rate ) {
			return rtrim( rtrim( number_format( (float) $rate, 3, '.', '' ), '0' ), '.' );
		}
	}

endif;

if ( function_exists( 'add_action' ) ) {
	add_action( 'wp_easycart_order_detail_inserted', array( 'wp_easycart_tax_classes', 'record_line' ), 10, 2 );
}
