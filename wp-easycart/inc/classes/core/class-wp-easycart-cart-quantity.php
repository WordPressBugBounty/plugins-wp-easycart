<?php
/**
 * Cart quantity limits and what the cart says about them ( 6.0.4 ).
 *
 * A quantity the store cannot give ( more than the stock, the item's purchase limit or under its minimum ) used to be changed without a
 * word: the box went back to what the store kept, which looked like an error ( Matt, a customer's two lines of one product sharing its
 * stock ). ec_db::update_cartitem() records here what it changed and why, the cart AJAX answer carries the message
 * ( ec_ajax_cartitem_update(), quantity_notice ), and each cart line knows the most it can hold with the same product on other lines
 * ( share_stock() ) and what to say when the shopper asks for more ( line_notes(): the templates' data-max-note / data-min-note,
 * ec_get_cart_data()'s max_note / min_note ).
 *
 * @package WP EasyCart
 * @since 6.0.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_cart_quantity' ) ) :

	/**
	 * Quantity limits of cart lines.
	 */
	class wp_easycart_cart_quantity {

		/** A limit at or above this is no limit ( ec_cartitem starts its max_quantity there ). */
		const NO_LIMIT = 10000000;

		/**
		 * The last quantity ec_db::update_cartitem() could not set as asked: tempcart_id, requested, quantity ( what it kept ), reason
		 * ( stock | max | min ), stock ( what the stock allows in all ), other ( units of the product on the cart's other lines ), max,
		 * min. Null when it set what was asked.
		 *
		 * @var array|null
		 */
		public static $last = null;

		/**
		 * What ec_db::update_cartitem() changed, or nothing ( called at its end ).
		 *
		 * @param int    $tempcart_id The cart line.
		 * @param int    $requested   The quantity asked for.
		 * @param int    $quantity    The quantity kept.
		 * @param string $reason      stock | max | min, '' for none.
		 * @param array  $args        stock, other, max, min.
		 */
		public static function record( $tempcart_id, $requested, $quantity, $reason, $args = array() ) {
			self::$last = null;
			if ( '' === (string) $reason || (int) $requested === (int) $quantity ) {
				return;
			}
			$args       = array_merge(
				array(
					'stock' => 0,
					'other' => 0,
					'max'   => 0,
					'min'   => 0,
				),
				$args
			);
			self::$last = array(
				'tempcart_id' => (int) $tempcart_id,
				'requested'   => (int) $requested,
				'quantity'    => (int) $quantity,
				'reason'      => (string) $reason,
				'stock'       => (int) $args['stock'],
				'other'       => max( 0, (int) $args['other'] ),
				'max'         => (int) $args['max'],
				'min'         => (int) $args['min'],
			);
		}

		/**
		 * The cart AJAX answer's quantity_notice for a line just changed: id, quantity ( kept ), requested, reason, message. Null when
		 * the store set what was asked.
		 *
		 * @param int $tempcart_id The cart line.
		 * @return array|null
		 */
		public static function notice( $tempcart_id ) {
			$last = self::$last;
			if ( ! is_array( $last ) || (int) $tempcart_id !== $last['tempcart_id'] ) {
				return null;
			}
			$message = self::message( $last['reason'], $last );
			if ( '' === $message ) {
				return null;
			}
			return array(
				'id'        => $last['tempcart_id'],
				'quantity'  => $last['quantity'],
				'requested' => $last['requested'],
				'reason'    => $last['reason'],
				'message'   => $message,
			);
		}

		/**
		 * Lowers each line's max_quantity to what its product's stock leaves after the cart's other lines of the same product, when the
		 * stock is counted for the product as a whole ( not per variant, no backorders ): ec_db::update_cartitem() keeps the same rule.
		 * Never under the line's own quantity ( a cart already over the stock keeps a valid box; its + is at the end ). Each line also
		 * gets stock_other_lines ( units of its product on the other lines ).
		 *
		 * @param array $lines ec_cartitem objects.
		 */
		public static function share_stock( $lines ) {
			if ( ! is_array( $lines ) ) {
				return;
			}
			$units = array();
			foreach ( $lines as $line ) {
				if ( is_object( $line ) && self::shares_stock( $line ) ) {
					$product_id           = (int) $line->product_id;
					$units[ $product_id ] = ( isset( $units[ $product_id ] ) ? $units[ $product_id ] : 0 ) + (int) $line->quantity;
				}
			}
			foreach ( $lines as $line ) {
				if ( ! is_object( $line ) ) {
					continue;
				}
				$line->stock_other_lines = 0;
				if ( ! self::shares_stock( $line ) ) {
					continue;
				}
				$other                   = $units[ (int) $line->product_id ] - (int) $line->quantity;
				$line->stock_other_lines = max( 0, $other );
				$room                    = max( (int) $line->quantity, (int) $line->stock_quantity - $other );
				if ( $room < (int) $line->max_quantity ) {
					$line->max_quantity = $room;
				}
			}
		}

		/**
		 * True when a line's product counts its stock as a whole and sells no more than it has.
		 *
		 * @param object $line ec_cartitem.
		 * @return bool
		 */
		public static function shares_stock( $line ) {
			return ( ! empty( $line->show_stock_quantity ) && empty( $line->use_optionitem_quantity_tracking ) && empty( $line->allow_backorders ) );
		}

		/**
		 * What a line says at its most and at its least: max ( '' while nothing limits it ), min ( '' at 1 ).
		 *
		 * @param object $line ec_cartitem ( after share_stock() ).
		 * @return array
		 */
		public static function line_notes( $line ) {
			$notes = array(
				'max' => '',
				'min' => '',
			);
			if ( ! is_object( $line ) ) {
				return $notes;
			}
			$min = (int) $line->min_quantity;
			if ( $min > 1 ) {
				$notes['min'] = self::message( 'min', array( 'min' => $min ) );
			}
			$max = (int) $line->max_quantity;
			if ( $max <= 0 || $max >= self::NO_LIMIT ) {
				return $notes;
			}
			$purchase = ( (int) $line->max_purchase_quantity > 0 ) ? (int) $line->max_purchase_quantity : self::NO_LIMIT;
			$stock    = self::NO_LIMIT;
			$other    = 0;
			if ( empty( $line->allow_backorders ) ) {
				if ( ! empty( $line->use_optionitem_quantity_tracking ) ) {
					$stock = (int) $line->optionitem_stock_quantity;
				} elseif ( ! empty( $line->show_stock_quantity ) ) {
					$other = isset( $line->stock_other_lines ) ? (int) $line->stock_other_lines : 0;
					$stock = (int) $line->stock_quantity;
				}
			}
			if ( $stock < self::NO_LIMIT && $stock - $other <= $purchase ) {
				$notes['max'] = self::message(
					'stock',
					array(
						'stock' => $stock,
						'other' => $other,
					)
				);
			} elseif ( $purchase < self::NO_LIMIT ) {
				$notes['max'] = self::message( 'max', array( 'max' => $purchase ) );
			}
			return $notes;
		}

		/**
		 * A quantity box's data-max-note and data-min-note ( escaped, with a leading space ): what ec-store.js says when the shopper
		 * asks for more than the line can hold, or less than its minimum, without asking the store.
		 *
		 * @param object $line ec_cartitem.
		 * @return string
		 */
		public static function note_attributes( $line ) {
			$notes = self::line_notes( $line );
			return ' data-max-note="' . esc_attr( $notes['max'] ) . '" data-min-note="' . esc_attr( $notes['min'] ) . '"';
		}

		/**
		 * The shopper's message for a limit ( language section cart; English until the store's wording has the phrase ).
		 *
		 * @param string $reason stock | max | min.
		 * @param array  $args   stock, other, max, min.
		 * @return string Plain text.
		 */
		public static function message( $reason, $args = array() ) {
			$args = array_merge(
				array(
					'stock' => 0,
					'other' => 0,
					'max'   => 0,
					'min'   => 0,
				),
				(array) $args
			);
			if ( 'stock' === $reason ) {
				if ( (int) $args['stock'] <= 0 || ! get_option( 'ec_option_show_stock_quantity' ) ) {
					$text = self::text( 'cartitem_stock_limit_hidden', "That's all we have in stock." );
				} elseif ( (int) $args['other'] > 0 ) {
					$text = self::text( 'cartitem_stock_limit_cart', 'Only [stock] in stock, including [other] elsewhere in your cart.' );
				} else {
					$text = self::text( 'cartitem_stock_limit', 'Only [stock] in stock.' );
				}
			} elseif ( 'max' === $reason && (int) $args['max'] > 0 ) {
				$text = self::text( 'cartitem_max_limit', 'You can buy up to [max] of this item.' );
			} elseif ( 'min' === $reason && (int) $args['min'] > 0 ) {
				$text = self::text( 'cartitem_min_limit', 'The minimum for this item is [min].' );
			} else {
				return '';
			}
			$text = str_replace(
				array( '[stock]', '[other]', '[max]', '[min]' ),
				array( self::number( $args['stock'] ), self::number( $args['other'] ), self::number( $args['max'] ), self::number( $args['min'] ) ),
				$text
			);
			/**
			 * Filters what the cart says about a quantity limit.
			 *
			 * @since 6.0.4
			 *
			 * @param string $text   Plain text.
			 * @param string $reason stock | max | min.
			 * @param array  $args   stock, other, max, min.
			 */
			return (string) apply_filters( 'wp_easycart_cart_quantity_message', $text, $reason, $args );
		}

		/**
		 * A cart phrase, else the English.
		 *
		 * @param string $key      Phrase key ( section cart ).
		 * @param string $fallback English.
		 * @return string
		 */
		private static function text( $key, $fallback ) {
			$text = function_exists( 'wp_easycart_language' ) ? wp_easycart_language()->get_text( 'cart', $key ) : '';
			$text = is_string( $text ) ? trim( html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, 'UTF-8' ) ) : '';
			return ( '' !== $text ) ? $text : $fallback;
		}

		/**
		 * A count in the site's number format.
		 *
		 * @param int $number The count.
		 * @return string
		 */
		private static function number( $number ) {
			return function_exists( 'number_format_i18n' ) ? number_format_i18n( (int) $number ) : (string) (int) $number;
		}
	}

endif;
