<?php
/**
 * The codes and promotions an order used, for exports ( 6.0.4 ).
 *
 * An order keeps its discounts in two places. The classic coupon is ec_order.promo_code, and a line's share of a classic
 * coupon or promotion is ec_orderdetail.total_discount_coupon / total_discount_promotion ( amounts only: a classic
 * promotion's name is not saved ). Offers ( WP EasyCart PRO ) save what applied as JSON: ec_order.applied_offers
 * ( { applied_offers: [ { offer_id, label, offer_name, code, amount, … } ], … } ) and ec_orderdetail.applied_offers
 * ( [ { offer_id, label, amount, code } ] ), and never write promo_code ( a subscription order's code is the exception ).
 * An entry with a code is a code the shopper entered; one without is an automatic offer ( a promotion ). The order export
 * read only promo_code, so an Offers code never reached it.
 *
 * This class reads both, so every export says the same thing whichever system took the discount. It reads saved data
 * only, so an order keeps its codes and promotions in the export without WP EasyCart PRO.
 *
 * @since 6.0.4
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_order_discounts' ) ) :

	// phpcs:disable PEAR.NamingConventions.ValidClassName -- WP EasyCart class names are lower case.
	/**
	 * An order's codes and promotions, per order and per line.
	 *
	 * @since 6.0.4
	 */
	final class wp_easycart_order_discounts {
		// phpcs:enable PEAR.NamingConventions.ValidClassName

		/**
		 * The order export's columns this class adds, in order ( after advanced_product_options ). promotions_used is the
		 * order's, printed on its first line only as promo_code_used is ( the exporters' single-use keys ); the rest are
		 * the line's. promo_code_used is the export's own column, now filled by codes() as well.
		 *
		 * @return string[]
		 */
		public static function export_keys() {
			return array( 'promotions_used', 'line_promo_code', 'line_promo_code_discount', 'line_promotions', 'line_promotion_discount' );
		}

		/**
		 * The entries of a saved applied_offers value: the order's object ( its applied_offers list ) or a line's list.
		 *
		 * @param mixed $saved JSON text, or the decoded value.
		 * @return array[] Each with offer_id, label, code, amount ( and offer_name on the order's ).
		 */
		public static function entries( $saved ) {
			if ( is_string( $saved ) ) {
				$saved = ( '' !== trim( $saved ) ) ? json_decode( $saved, true ) : null;
			}
			if ( is_object( $saved ) ) {
				$saved = json_decode( wp_json_encode( $saved ), true );
			}
			if ( ! is_array( $saved ) ) {
				return array();
			}
			if ( isset( $saved['applied_offers'] ) ) {
				$saved = $saved['applied_offers'];
			}
			$entries = array();
			foreach ( (array) $saved as $entry ) {
				if ( is_object( $entry ) ) {
					$entry = get_object_vars( $entry );
				}
				if ( ! is_array( $entry ) ) {
					continue;
				}
				$entries[] = array(
					'offer_id'   => isset( $entry['offer_id'] ) ? (int) $entry['offer_id'] : 0,
					'label'      => isset( $entry['label'] ) && is_scalar( $entry['label'] ) ? trim( (string) $entry['label'] ) : '',
					'offer_name' => isset( $entry['offer_name'] ) && is_scalar( $entry['offer_name'] ) ? trim( (string) $entry['offer_name'] ) : '',
					'code'       => isset( $entry['code'] ) && is_scalar( $entry['code'] ) ? trim( (string) $entry['code'] ) : '',
					'amount'     => ( isset( $entry['amount'] ) && is_numeric( $entry['amount'] ) ) ? (float) $entry['amount'] : 0.0,
				);
			}
			return $entries;
		}

		/**
		 * Every code the shopper entered on the order: the classic coupon first, then each Offers code, once each ( any case ).
		 *
		 * @param string $promo_code     ec_order.promo_code.
		 * @param mixed  $applied_offers ec_order.applied_offers.
		 * @return string[]
		 */
		public static function codes( $promo_code, $applied_offers ) {
			$codes = array();
			self::add_unique( $codes, (string) $promo_code );
			foreach ( self::entries( $applied_offers ) as $entry ) {
				self::add_unique( $codes, $entry['code'] );
			}
			return $codes;
		}

		/**
		 * The automatic offers that applied to the order ( discounts, free shipping, gifts ), by the name the shopper saw.
		 *
		 * @param mixed $applied_offers ec_order.applied_offers.
		 * @return string[]
		 */
		public static function promotions( $applied_offers ) {
			$names = array();
			foreach ( self::entries( $applied_offers ) as $entry ) {
				if ( '' === $entry['code'] ) {
					self::add_unique( $names, self::name( $entry ) );
				}
			}
			return $names;
		}

		/**
		 * What took money off one order line: the codes and their amount, the promotions and theirs. Offers amounts come
		 * from the line's applied_offers; a classic coupon's share ( total_discount_coupon ) is named by the order's
		 * promo_code; a classic promotion's share ( total_discount_promotion ) has no saved name.
		 *
		 * @param array|object $line           An ec_orderdetail row ( applied_offers, total_discount_coupon, total_discount_promotion ).
		 * @param string       $promo_code     ec_order.promo_code.
		 * @param mixed        $order_offers   ec_order.applied_offers ( names an entry the line saved without a label ).
		 * @return array { codes: string[], code_discount: float, promotions: string[], promotion_discount: float }
		 */
		public static function line( $line, $promo_code = '', $order_offers = null ) {
			$line   = is_object( $line ) ? get_object_vars( $line ) : (array) $line;
			$result = array(
				'codes'              => array(),
				'code_discount'      => 0.0,
				'promotions'         => array(),
				'promotion_discount' => 0.0,
			);
			$names  = array();
			foreach ( self::entries( $order_offers ) as $entry ) {
				if ( $entry['offer_id'] > 0 && ! isset( $names[ $entry['offer_id'] ] ) ) {
					$names[ $entry['offer_id'] ] = self::name( $entry );
				}
			}
			foreach ( self::entries( isset( $line['applied_offers'] ) ? $line['applied_offers'] : null ) as $entry ) {
				if ( $entry['amount'] <= 0 ) {
					continue;
				}
				if ( '' !== $entry['code'] ) {
					self::add_unique( $result['codes'], $entry['code'] );
					$result['code_discount'] += $entry['amount'];
				} else {
					$name = self::name( $entry );
					if ( '' === $name && isset( $names[ $entry['offer_id'] ] ) ) {
						$name = $names[ $entry['offer_id'] ];
					}
					self::add_unique( $result['promotions'], $name );
					$result['promotion_discount'] += $entry['amount'];
				}
			}
			$coupon = ( isset( $line['total_discount_coupon'] ) && is_numeric( $line['total_discount_coupon'] ) ) ? (float) $line['total_discount_coupon'] : 0.0;
			if ( $coupon > 0 ) {
				self::add_unique( $result['codes'], (string) $promo_code );
				$result['code_discount'] += $coupon;
			}
			$promotion = ( isset( $line['total_discount_promotion'] ) && is_numeric( $line['total_discount_promotion'] ) ) ? (float) $line['total_discount_promotion'] : 0.0;
			if ( $promotion > 0 ) {
				$result['promotion_discount'] += $promotion;
			}
			$result['code_discount']      = round( $result['code_discount'], 3 );
			$result['promotion_discount'] = round( $result['promotion_discount'], 3 );
			return $result;
		}

		/**
		 * The order export's cells for one row: promo_code_used, then export_keys(). A row with no line ( an order without
		 * items ) leaves the line cells empty.
		 *
		 * @param array|object $order ec_order values: promo_code, applied_offers.
		 * @param array|object $line  ec_orderdetail values ( orderdetail_id, applied_offers, total_discount_coupon,
		 *                            total_discount_promotion ), or an empty array.
		 * @return array key => text.
		 */
		public static function export_values( $order, $line = array() ) {
			$order          = is_object( $order ) ? get_object_vars( $order ) : (array) $order;
			$line           = is_object( $line ) ? get_object_vars( $line ) : (array) $line;
			$promo_code     = isset( $order['promo_code'] ) ? (string) $order['promo_code'] : '';
			$applied_offers = isset( $order['applied_offers'] ) ? $order['applied_offers'] : null;
			$values         = array(
				'promo_code_used'          => self::cell( self::codes( $promo_code, $applied_offers ) ),
				'promotions_used'          => self::cell( self::promotions( $applied_offers ), '; ' ),
				'line_promo_code'          => '',
				'line_promo_code_discount' => '',
				'line_promotions'          => '',
				'line_promotion_discount'  => '',
			);
			if ( ! empty( $line['orderdetail_id'] ) ) {
				$discounts                          = self::line( $line, $promo_code, $applied_offers );
				$values['line_promo_code']          = self::cell( $discounts['codes'] );
				$values['line_promo_code_discount'] = number_format( $discounts['code_discount'], 3, '.', '' );
				$values['line_promotions']          = self::cell( $discounts['promotions'], '; ' );
				$values['line_promotion_discount']  = number_format( $discounts['promotion_discount'], 3, '.', '' );
			}
			return $values;
		}

		/**
		 * A list as one spreadsheet cell, never read as a formula ( a merchant's offer name or code can start with = + - @ ).
		 * Codes are separated by commas; promotion names by semicolons, as a name can hold a comma.
		 *
		 * @param string[] $values    Names or codes.
		 * @param string   $separator Between two values.
		 * @return string
		 */
		public static function cell( $values, $separator = ', ' ) {
			$text = implode( $separator, array_map( 'strval', (array) $values ) );
			if ( '' !== $text && false !== strpos( "=+-@\t\r", $text[0] ) ) {
				$text = "'" . $text;
			}
			return $text;
		}

		/**
		 * Whether customer-facing documents print the code a customer used ( 6.0.5, FREE #497 ): receipts, invoices, the refund
		 * and shipped emails, the invoice PDF, printable receipts, the order confirmation and My Account. Settings › Checkout ›
		 * "Show discount codes on receipts and documents" ( ec_option_show_discount_codes, on ): off, a private code ( a 100% code
		 * given to one customer ) is never printed where a receipt can be forwarded or shared. The cart and checkout still show the
		 * code the shopper typed, and the admin always shows it.
		 *
		 * @since 6.0.5
		 * @param string $context Where it prints ( receipt, invoice, refund, shipped, invoice_pdf, print_receipt, confirmation, account … ).
		 * @return bool
		 */
		public static function show_codes( $context = '' ) {
			$show = ( '0' !== (string) get_option( 'ec_option_show_discount_codes', '1' ) );
			/**
			 * Whether a document prints discount codes.
			 *
			 * @since 6.0.5
			 * @param bool   $show    The setting.
			 * @param string $context Where it prints.
			 */
			return (bool) apply_filters( 'wp_easycart_show_discount_codes', $show, (string) $context );
		}

		/**
		 * The words an applied offer is printed with on a customer-facing document: its label, with the code in brackets when the
		 * offer was a code and show_codes() allows it ( "100% off ( DEVAT100FREE )", else "100% off" ). Plain text: escape it where
		 * it prints. Filter wp_easycart_discount_display_label for other wording.
		 *
		 * @since 6.0.5
		 * @param array  $offer   An applied_offers entry ( label, code … ).
		 * @param string $context Where it prints ( see show_codes() ).
		 * @return string
		 */
		public static function display_label( $offer, $context = '' ) {
			$offer = is_array( $offer ) ? $offer : (array) $offer;
			$label = isset( $offer['label'] ) ? trim( wp_strip_all_tags( (string) $offer['label'] ) ) : '';
			if ( '' === $label && isset( $offer['offer_name'] ) ) {
				$label = trim( wp_strip_all_tags( (string) $offer['offer_name'] ) );
			}
			$code = isset( $offer['code'] ) ? trim( wp_strip_all_tags( (string) $offer['code'] ) ) : '';
			$show = ( '' !== $code && self::show_codes( $context ) );
			$text = $show ? trim( $label . ' (' . $code . ')' ) : $label;
			/**
			 * The words an applied offer is printed with on a customer-facing document.
			 *
			 * @since 6.0.5
			 * @param string $text    Label, with " (CODE)" when shown.
			 * @param array  $offer   The applied_offers entry.
			 * @param bool   $show    The code is shown.
			 * @param string $context Where it prints.
			 */
			return (string) apply_filters( 'wp_easycart_discount_display_label', $text, $offer, $show, (string) $context );
		}

		/**
		 * What a document prints on its classic coupon line ( ec_order.promo_code ): the code, or the coupon's message when
		 * "Show the coupon's message instead of its code" is on and it has one. With codes hidden ( show_codes() ), the message
		 * when the coupon has one, else '' ( the document leaves the line out ).
		 *
		 * @since 6.0.5
		 * @param string $promo_code    The order's coupon code.
		 * @param string $promo_message The coupon's saved message.
		 * @param string $context       Where it prints ( see show_codes() ).
		 * @return string
		 */
		public static function coupon_text( $promo_code, $promo_message = '', $context = '' ) {
			$promo_code    = trim( (string) $promo_code );
			$promo_message = trim( (string) $promo_message );
			if ( '' === $promo_code ) {
				return '';
			}
			if ( ! self::show_codes( $context ) ) {
				return $promo_message;
			}
			return ( get_option( 'ec_option_show_coupon_message' ) && '' !== $promo_message ) ? $promo_message : $promo_code;
		}

		/**
		 * The name an entry is shown by: its label, else the offer's name.
		 *
		 * @param array $entry An entries() entry.
		 * @return string
		 */
		private static function name( $entry ) {
			return ( '' !== $entry['label'] ) ? $entry['label'] : $entry['offer_name'];
		}

		/**
		 * Add a value to a list unless it is empty or already there ( any case ).
		 *
		 * @param string[] $items List ( by reference ).
		 * @param string   $value Value.
		 */
		private static function add_unique( &$items, $value ) {
			$value = trim( (string) $value );
			if ( '' === $value ) {
				return;
			}
			foreach ( $items as $kept ) {
				if ( 0 === strcasecmp( $kept, $value ) ) {
					return;
				}
			}
			$items[] = $value;
		}
	}

endif;
