<?php
/**
 * WP EasyCart — what goes back when an order is cancelled: its stock and the gift card balance it used ( 6.0.3 ).
 *
 * A move into Cancelled ( 19 ) puts back the stock the order took ( lines with ec_orderdetail.stock_adjusted = 1, less the
 * units a refund already restocked; each line is claimed first, stock_adjusted 1 to 0, so two requests never return it
 * twice ) and adds the gift card amount the order used ( ec_order.giftcard_total, recorded since 6.0.2 ) back to its card
 * ( ec_order.giftcard_id ). Settings › Products › Inventory › "Put stock back when an order is cancelled"
 * ( ec_option_cancel_restock ) and Settings › Checkout › "Give gift card balances back when an order is cancelled"
 * ( ec_option_cancel_return_giftcard ), both on. An order that had shipped keeps its stock ( a staff note says so ).
 *
 * Moving a cancelled order on to another status ( Undo, a mistake ) takes the gift card amount the cancel gave back from
 * the card again, and takes the stock again once the status is a paid one ( wp_easycart_order_pay::take_stock(), as for an
 * order made paid ). Cancelled then Refunded keeps what went back.
 *
 * Refunded ( 16 ) changes nothing here: WP EasyCart PRO's refund window restocks the items the merchant picks and, for an
 * order paid with a gift card, refunds to the card ( return_giftcard() with via refund ).
 *
 * Gift card amounts given back and taken again are order timeline entries ( order-giftcard-returned / order-giftcard-taken,
 * meta giftcard_id, amount, via cancelled | refund | reopened ); what is still on the order is worked out from them
 * ( giftcard() ), and each entry is written only while no other was written since it was worked out.
 *
 * @package WP_EasyCart
 * @since   6.0.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_order_returns' ) ) :

	/**
	 * Stock and gift card balance going back from a cancelled order.
	 */
	final class wp_easycart_order_returns {

		/** Order Cancelled. */
		const CANCELLED = 19;

		/** Order Refunded. */
		const REFUNDED = 16;

		/** Timeline entry: an amount went back onto the order's gift card. */
		const GIFTCARD_RETURNED = 'order-giftcard-returned';

		/** Timeline entry: an amount the cancel gave back was taken from the card again. */
		const GIFTCARD_TAKEN = 'order-giftcard-taken';

		/**
		 * Hooks.
		 */
		public static function init() {
			add_action( 'wpeasycart_order_status_update', array( __CLASS__, 'on_status_update' ), 20, 3 );
			add_filter( 'wp_easycart_admin_order_history_entry', array( __CLASS__, 'history_entry' ), 5, 3 );
			add_filter( 'wp_easycart_admin_order_timeline_labels', array( __CLASS__, 'timeline_labels' ), 5, 1 );
		}

		// Settings.

		/**
		 * A cancelled order's stock goes back ( Settings › Products › Inventory ).
		 *
		 * @param int $order_id Order, for the filter.
		 * @return bool
		 */
		public static function restock_on_cancel( $order_id = 0 ) {
			$on = '0' !== (string) get_option( 'ec_option_cancel_restock', '1' );
			/**
			 * Put a cancelled order's stock back.
			 *
			 * @since 6.0.3
			 * @param bool $on       The setting.
			 * @param int  $order_id Order ( 0 when asked for the store ).
			 */
			return (bool) apply_filters( 'wp_easycart_cancel_restock', $on, (int) $order_id );
		}

		/**
		 * A cancelled order's gift card balance goes back ( Settings › Checkout ).
		 *
		 * @param int $order_id Order, for the filter.
		 * @return bool
		 */
		public static function giftcard_on_cancel( $order_id = 0 ) {
			$on = '0' !== (string) get_option( 'ec_option_cancel_return_giftcard', '1' );
			/**
			 * Give a cancelled order's gift card balance back.
			 *
			 * @since 6.0.3
			 * @param bool $on       The setting.
			 * @param int  $order_id Order ( 0 when asked for the store ).
			 */
			return (bool) apply_filters( 'wp_easycart_cancel_return_giftcard', $on, (int) $order_id );
		}

		/**
		 * What a cancel does, for the "Cancel this order?" question on the order screen and the orders list.
		 *
		 * @param bool $bulk Several orders at once.
		 * @return string
		 */
		public static function cancel_text( $bulk = false ) {
			$stock = self::restock_on_cancel();
			$card  = self::giftcard_on_cancel();
			if ( $bulk ) {
				if ( $stock && $card ) {
					return __( 'Unless an order has shipped, its stock goes back. Any gift card balance an order used goes back too. No money goes back: if a customer paid, refund the payment too.', 'wp-easycart' );
				}
				if ( $stock ) {
					return __( 'Unless an order has shipped, its stock goes back. No money goes back: if a customer paid, refund the payment too.', 'wp-easycart' );
				}
				if ( $card ) {
					return __( 'Any gift card balance an order used goes back. No money goes back: if a customer paid, refund the payment too.', 'wp-easycart' );
				}
				return __( 'This changes each order’s status only. If a customer paid, refund the payment too.', 'wp-easycart' );
			}
			if ( $stock && $card ) {
				return __( 'Unless the order has shipped, its stock goes back. Any gift card balance it used goes back too. No money goes back: if the customer paid, refund the payment too.', 'wp-easycart' );
			}
			if ( $stock ) {
				return __( 'Unless the order has shipped, its stock goes back. No money goes back: if the customer paid, refund the payment too.', 'wp-easycart' );
			}
			if ( $card ) {
				return __( 'Any gift card balance it used goes back. No money goes back: if the customer paid, refund the payment too.', 'wp-easycart' );
			}
			return __( 'This changes the order’s status only. If the customer paid, refund the payment too.', 'wp-easycart' );
		}

		// Status changes.

		/**
		 * Action wpeasycart_order_status_update: a move into Cancelled gives back, a move out of it takes again.
		 *
		 * @param int      $order_id Order.
		 * @param int      $status   Status now.
		 * @param int|null $previous Status before ( null when the path did not say ).
		 */
		public static function on_status_update( $order_id, $status, $previous = null ) {
			global $wpdb;
			$order_id = (int) $order_id;
			$status   = (int) $status;
			$previous = ( null === $previous || '' === $previous ) ? null : (int) $previous;
			if ( $order_id <= 0 || ( null !== $previous && $previous === $status ) ) {
				return;
			}
			if ( self::CANCELLED !== $status && self::CANCELLED !== $previous ) {
				return;
			}
			/* The order has moved on again since this was announced: the later announcement acts. */
			if ( $status !== (int) $wpdb->get_var( $wpdb->prepare( 'SELECT orderstatus_id FROM ec_order WHERE order_id = %d', $order_id ) ) ) {
				return;
			}
			if ( self::CANCELLED === $status ) {
				self::cancelled( $order_id, $previous );
			} else {
				self::reopened( $order_id, $status );
			}
		}

		/**
		 * An order was cancelled: its stock and gift card balance go back, as the settings say.
		 *
		 * @param int      $order_id Order.
		 * @param int|null $previous Status before.
		 */
		public static function cancelled( $order_id, $previous = null ) {
			$order_id = (int) $order_id;
			if ( self::restock_on_cancel( $order_id ) ) {
				if ( ! self::has_shipped( $order_id, $previous ) ) {
					/* translators: %d: order number. */
					self::return_stock( $order_id, sprintf( __( 'Order #%d cancelled', 'wp-easycart' ), $order_id ) );
				} elseif ( self::holds_tracked_stock( $order_id ) && function_exists( 'wp_easycart_order_note' ) ) {
					wp_easycart_order_note( $order_id, __( 'Stock was not put back because the order had shipped. Adjust it in Products › Inventory when the items come back.', 'wp-easycart' ), 'WP EasyCart' );
				}
			}
			if ( self::giftcard_on_cancel( $order_id ) ) {
				self::return_giftcard( $order_id, null, array( 'via' => 'cancelled' ) );
			}
		}

		/**
		 * A cancelled order moved on to another status: what the cancel gave back is taken again.
		 *
		 * @param int $order_id Order.
		 * @param int $status   Status now.
		 */
		public static function reopened( $order_id, $status ) {
			global $wpdb;
			$order_id = (int) $order_id;
			if ( self::REFUNDED === (int) $status ) {
				return; /* cancelled, then refunded: what went back stays back */
			}
			self::take_giftcard( $order_id );
			$approved = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT is_approved FROM ec_orderstatus WHERE status_id = %d', (int) $status ) );
			if ( $approved && class_exists( 'wp_easycart_order_pay' ) && method_exists( 'wp_easycart_order_pay', 'take_stock' ) ) {
				/* translators: %d: order number. */
				wp_easycart_order_pay::take_stock( $order_id, sprintf( __( 'Order #%d no longer cancelled', 'wp-easycart' ), $order_id ) );
			}
		}

		// Stock.

		/**
		 * The order shipped, in part or in full: its stock stays out.
		 *
		 * @param int      $order_id Order.
		 * @param int|null $previous Status before the cancel.
		 * @return bool
		 */
		public static function has_shipped( $order_id, $previous = null ) {
			global $wpdb;
			if ( null !== $previous ) {
				$fulfilled = class_exists( 'wp_easycart_shipments' ) ? wp_easycart_shipments::is_fulfilled_status( (int) $previous ) : in_array( (int) $previous, array( 2, 18 ), true );
				if ( $fulfilled ) {
					return true;
				}
			}
			if ( class_exists( 'wp_easycart_fulfillment' ) && wp_easycart_fulfillment::ready() ) {
				$state = wp_easycart_fulfillment::state( (int) $order_id );
				return ! empty( $state['shipped'] );
			}
			return '' !== trim( (string) $wpdb->get_var( $wpdb->prepare( 'SELECT tracking_number FROM ec_order WHERE order_id = %d', (int) $order_id ) ) );
		}

		/**
		 * The order still holds stock of a product that counts it.
		 *
		 * @param int $order_id Order.
		 * @return bool
		 */
		private static function holds_tracked_stock( $order_id ) {
			global $wpdb;
			return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT d.orderdetail_id FROM ec_orderdetail AS d INNER JOIN ec_product AS p ON p.product_id = d.product_id WHERE d.order_id = %d AND d.stock_adjusted = 1 AND ( p.show_stock_quantity = 1 OR p.use_optionitem_quantity_tracking = 1 ) LIMIT 1', (int) $order_id ) );
		}

		/**
		 * Units of each line a refund already put back in stock ( the refund window's Restock item: the refund_items of its
		 * timeline entries, restocked = 1 ).
		 *
		 * @param int $order_id Order.
		 * @return array orderdetail_id => units.
		 */
		public static function restocked_units( $order_id ) {
			global $wpdb;
			$out  = array();
			$rows = (array) $wpdb->get_col( $wpdb->prepare( "SELECT order_log_meta_value FROM ec_order_log_meta WHERE order_id = %d AND order_log_meta_key = 'refund_items'", (int) $order_id ) );
			foreach ( $rows as $row ) {
				$items = json_decode( (string) $row, true );
				foreach ( is_array( $items ) ? $items : array() as $item ) {
					if ( ! is_array( $item ) || empty( $item['restocked'] ) || empty( $item['id'] ) ) {
						continue;
					}
					$line         = (int) $item['id'];
					$out[ $line ] = ( isset( $out[ $line ] ) ? $out[ $line ] : 0 ) + max( 0, (int) ( isset( $item['qty'] ) ? $item['qty'] : 0 ) );
				}
			}
			return $out;
		}

		/**
		 * Put the order's stock back: every line that took stock ( stock_adjusted 1 ), less what a refund restocked. Each line
		 * is claimed first ( stock_adjusted 1 to 0 ), so it goes back once; wp_easycart_order_pay::take_stock() takes it again.
		 *
		 * @param int    $order_id Order.
		 * @param string $note     The stock history's note ( wpeasycart_inventory_stock_changed ).
		 * @return int Units put back.
		 */
		public static function return_stock( $order_id, $note = '' ) {
			global $wpdb;
			$order_id = (int) $order_id;
			$lines    = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ec_orderdetail WHERE order_id = %d AND stock_adjusted = 1', $order_id ) );
			if ( ! $lines ) {
				return 0;
			}
			$db        = new ec_db();
			$restocked = self::restocked_units( $order_id );
			$returned  = 0;
			/* translators: %d: order number. */
			$note = ( '' !== (string) $note ) ? (string) $note : sprintf( __( 'Order #%d cancelled', 'wp-easycart' ), $order_id );
			foreach ( $lines as $line ) {
				if ( 1 !== (int) $wpdb->query( $wpdb->prepare( 'UPDATE ec_orderdetail SET stock_adjusted = 0 WHERE orderdetail_id = %d AND stock_adjusted = 1', (int) $line->orderdetail_id ) ) ) {
					continue;
				}
				$units = (int) $line->quantity - ( isset( $restocked[ (int) $line->orderdetail_id ] ) ? (int) $restocked[ (int) $line->orderdetail_id ] : 0 );
				if ( $units <= 0 ) {
					continue;
				}
				$product = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_product WHERE product_id = %d', (int) $line->product_id ) );
				if ( ! $product ) {
					continue;
				}
				$returned += $units;
				$oiq_id    = 0;
				$old       = (int) $product->stock_quantity;
				if ( $product->use_optionitem_quantity_tracking ) {
					$oiq = $wpdb->get_row( $wpdb->prepare( 'SELECT optionitemquantity_id, quantity FROM ec_optionitemquantity WHERE product_id = %d AND optionitem_id_1 = %d AND optionitem_id_2 = %d AND optionitem_id_3 = %d AND optionitem_id_4 = %d AND optionitem_id_5 = %d', $line->product_id, $line->optionitem_id_1, $line->optionitem_id_2, $line->optionitem_id_3, $line->optionitem_id_4, $line->optionitem_id_5 ) );
					if ( $oiq ) {
						$oiq_id = (int) $oiq->optionitemquantity_id;
						$old    = (int) $oiq->quantity;
					}
					$db->update_quantity_value( -1 * $units, $line->product_id, $line->optionitem_id_1, $line->optionitem_id_2, $line->optionitem_id_3, $line->optionitem_id_4, $line->optionitem_id_5 );
				}
				$db->update_product_stock( $line->product_id, -1 * $units );
				wp_easycart_order_log(
					$order_id,
					'order-stock-update',
					array(
						'product_id'     => (string) $line->product_id,
						'quantity'       => '+' . $units,
						'orderdetail_id' => (string) $line->orderdetail_id,
					)
				);
				do_action(
					'wpeasycart_inventory_stock_changed',
					array(
						'product_id'            => (int) $line->product_id,
						'optionitemquantity_id' => $oiq_id,
						'old_quantity'          => $old,
						'new_quantity'          => $old + $units,
						'delta'                 => $units,
						'reason'                => 'order',
						'source'                => 'order',
						'note'                  => $note,
						'user_id'               => get_current_user_id(),
					)
				);
			}
			if ( $returned > 0 ) {
				/**
				 * A cancelled order's stock went back.
				 *
				 * @since 6.0.3
				 * @param int $order_id Order.
				 * @param int $units    Units put back.
				 */
				do_action( 'wp_easycart_order_stock_returned', $order_id, $returned );
			}
			return $returned;
		}

		// Gift card.

		/**
		 * The gift card an order used and what of it has gone back.
		 *
		 * @param int $order_id Order.
		 * @return array|null id, used, returned ( given back less taken again ), returnable ( used less returned ), by_cancel ( given
		 *                    back by a cancel and not taken again ), moves ( timeline entries so far ); null when the order used no
		 *                    gift card, or before 6.0.2 recorded how much ( ec_order.giftcard_total NULL ).
		 */
		public static function giftcard( $order_id ) {
			global $wpdb;
			if ( ! method_exists( 'ec_db', 'order_accounting_columns_ready' ) || ! ec_db::order_accounting_columns_ready() ) {
				return null;
			}
			$order = $wpdb->get_row( $wpdb->prepare( 'SELECT giftcard_id, giftcard_total FROM ec_order WHERE order_id = %d', (int) $order_id ) );
			if ( ! $order || '' === trim( (string) $order->giftcard_id ) || null === $order->giftcard_total || (float) $order->giftcard_total < 0.005 ) {
				return null;
			}
			$moves     = (array) $wpdb->get_results(
				$wpdb->prepare(
					"SELECT l.order_log_id, l.order_log_key, a.order_log_meta_value AS amount, v.order_log_meta_value AS via
					FROM ec_order_log l
					LEFT JOIN ec_order_log_meta a ON a.order_log_id = l.order_log_id AND a.order_log_meta_key = 'amount'
					LEFT JOIN ec_order_log_meta v ON v.order_log_id = l.order_log_id AND v.order_log_meta_key = 'via'
					WHERE l.order_id = %d AND l.order_log_key IN ( %s, %s ) ORDER BY l.order_log_id",
					(int) $order_id,
					self::GIFTCARD_RETURNED,
					self::GIFTCARD_TAKEN
				)
			);
			$returned  = 0.0;
			$by_cancel = 0.0;
			foreach ( $moves as $move ) {
				$amount = (float) $move->amount;
				if ( self::GIFTCARD_RETURNED === $move->order_log_key ) {
					$returned += $amount;
					if ( 'cancelled' === (string) $move->via ) {
						$by_cancel += $amount;
					}
				} else {
					$returned  -= $amount;
					$by_cancel -= $amount;
				}
			}
			$used = round( (float) $order->giftcard_total, 2 );
			return array(
				'id'         => (string) $order->giftcard_id,
				'used'       => $used,
				'returned'   => round( max( 0, $returned ), 2 ),
				'returnable' => round( max( 0, $used - $returned ), 2 ),
				'by_cancel'  => round( max( 0, $by_cancel ), 2 ),
				'moves'      => count( $moves ),
			);
		}

		/**
		 * Write a gift card timeline entry, only while no other was written since $moves were counted ( two requests at once
		 * cannot both give the same amount back ).
		 *
		 * @param int    $order_id Order.
		 * @param string $key      GIFTCARD_RETURNED or GIFTCARD_TAKEN.
		 * @param int    $moves    Entries counted by giftcard().
		 * @param array  $meta     Meta to write with it.
		 * @return int Log ID, 0 when another request got there first.
		 */
		private static function claim( $order_id, $key, $moves, $meta ) {
			global $wpdb;
			$done   = $wpdb->query(
				$wpdb->prepare(
					'INSERT INTO ec_order_log ( order_id, order_log_key )
					SELECT %d, %s FROM DUAL
					WHERE ( SELECT COUNT(*) FROM ( SELECT order_log_id FROM ec_order_log WHERE order_id = %d AND order_log_key IN ( %s, %s ) ) AS t ) = %d',
					(int) $order_id,
					$key,
					(int) $order_id,
					self::GIFTCARD_RETURNED,
					self::GIFTCARD_TAKEN,
					(int) $moves
				)
			);
			$log_id = ( 1 === (int) $done ) ? (int) $wpdb->insert_id : 0;
			if ( $log_id <= 0 ) {
				return 0;
			}
			foreach ( $meta as $meta_key => $meta_value ) {
				if ( null === $meta_value || '' === $meta_value ) {
					continue;
				}
				$wpdb->insert(
					'ec_order_log_meta',
					array(
						'order_log_id'         => $log_id,
						'order_id'             => (int) $order_id,
						'order_log_meta_key'   => (string) $meta_key,
						'order_log_meta_value' => is_scalar( $meta_value ) ? (string) $meta_value : (string) wp_json_encode( $meta_value ),
					),
					array( '%d', '%d', '%s', '%s' )
				);
			}
			return $log_id;
		}

		/**
		 * Give gift card balance back: add an amount the order used back to its gift card.
		 *
		 * @param int        $order_id Order.
		 * @param float|null $amount   Amount, at most what has not gone back yet; null = all of it.
		 * @param array      $args     via ( cancelled | refund ), meta ( more timeline meta: refund_items, refund_reason, refund_note,
		 *                             refund_by ).
		 * @return float Amount given back ( 0 when nothing went back ).
		 */
		public static function return_giftcard( $order_id, $amount = null, $args = array() ) {
			global $wpdb;
			$order_id = (int) $order_id;
			$card     = self::giftcard( $order_id );
			if ( ! $card || $card['returnable'] < 0.005 ) {
				return 0.0;
			}
			$amount = ( null === $amount ) ? $card['returnable'] : min( round( (float) $amount, 2 ), $card['returnable'] );
			if ( $amount < 0.005 ) {
				return 0.0;
			}
			$via = ( isset( $args['via'] ) && in_array( $args['via'], array( 'cancelled', 'refund' ), true ) ) ? $args['via'] : 'refund';
			if ( null === $wpdb->get_var( $wpdb->prepare( 'SELECT giftcard_id FROM ec_giftcard WHERE giftcard_id = %s', $card['id'] ) ) ) {
				if ( function_exists( 'wp_easycart_order_note' ) ) {
					wp_easycart_order_note(
						$order_id,
						/* translators: 1: gift card code, 2: amount. */
						sprintf( __( 'Gift card %1$s no longer exists, so %2$s could not be put back on it.', 'wp-easycart' ), $card['id'], self::money( $amount ) ),
						'WP EasyCart'
					);
				}
				return 0.0;
			}
			$meta = array_merge(
				array(
					'giftcard_id' => $card['id'],
					'amount'      => (string) $amount,
					'via'         => $via,
				),
				( isset( $args['meta'] ) && is_array( $args['meta'] ) ) ? $args['meta'] : array()
			);
			if ( ! self::claim( $order_id, self::GIFTCARD_RETURNED, $card['moves'], $meta ) ) {
				return 0.0;
			}
			$wpdb->query( $wpdb->prepare( 'UPDATE ec_giftcard SET amount = amount + %s WHERE giftcard_id = %s', (string) $amount, $card['id'] ) );
			/**
			 * Gift card balance an order used went back onto the card.
			 *
			 * @since 6.0.3
			 * @param int    $order_id Order.
			 * @param string $code     Gift card.
			 * @param float  $amount   Amount given back.
			 * @param string $via      cancelled | refund.
			 */
			do_action( 'wp_easycart_order_giftcard_returned', $order_id, $card['id'], (float) $amount, $via );
			return (float) $amount;
		}

		/**
		 * Take what a cancel gave back from the gift card again ( the order is no longer cancelled ). A card spent since then is
		 * taken down to zero, never below, and a staff note names what is short.
		 *
		 * @param int $order_id Order.
		 * @return float Amount taken.
		 */
		public static function take_giftcard( $order_id ) {
			global $wpdb;
			$order_id = (int) $order_id;
			$card     = self::giftcard( $order_id );
			if ( ! $card || $card['by_cancel'] < 0.005 ) {
				return 0.0;
			}
			$balance = $wpdb->get_var( $wpdb->prepare( 'SELECT amount FROM ec_giftcard WHERE giftcard_id = %s', $card['id'] ) );
			if ( null === $balance ) {
				return 0.0;
			}
			$amount = $card['by_cancel'];
			$short  = round( max( 0, $amount - (float) $balance ), 2 );
			if ( ! self::claim(
				$order_id,
				self::GIFTCARD_TAKEN,
				$card['moves'],
				array(
					'giftcard_id' => $card['id'],
					'amount'      => (string) $amount,
					'via'         => 'reopened',
					'short'       => ( $short >= 0.005 ) ? (string) $short : '',
				)
			) ) {
				return 0.0;
			}
			$wpdb->query( $wpdb->prepare( 'UPDATE ec_giftcard SET amount = GREATEST( amount - %s, 0 ) WHERE giftcard_id = %s', (string) $amount, $card['id'] ) );
			if ( $short >= 0.005 && function_exists( 'wp_easycart_order_note' ) ) {
				wp_easycart_order_note(
					$order_id,
					/* translators: 1: gift card code, 2: amount the card was short. */
					sprintf( __( 'Gift card %1$s had been spent since the order was cancelled: it is now empty, %2$s short of what the order used.', 'wp-easycart' ), $card['id'], self::money( $short ) ),
					'WP EasyCart'
				);
			}
			/**
			 * Gift card balance a cancel gave back was taken from the card again.
			 *
			 * @since 6.0.3
			 * @param int    $order_id Order.
			 * @param string $code     Gift card.
			 * @param float  $amount   Amount taken ( the card may have had less: see $short ).
			 * @param float  $short    What the card was short.
			 */
			do_action( 'wp_easycart_order_giftcard_taken', $order_id, $card['id'], (float) $amount, (float) $short );
			return (float) ( $amount - $short );
		}

		// Timeline.

		/**
		 * An amount in the store's currency.
		 *
		 * @param float $amount Amount.
		 * @return string
		 */
		private static function money( $amount ) {
			if ( isset( $GLOBALS['currency'] ) && is_object( $GLOBALS['currency'] ) && method_exists( $GLOBALS['currency'], 'get_currency_display' ) ) {
				return wp_strip_all_tags( (string) $GLOBALS['currency']->get_currency_display( (float) $amount ) );
			}
			return number_format( (float) $amount, 2 );
		}

		/**
		 * Filter wp_easycart_admin_order_history_entry ( WP EasyCart PRO's order timeline ): the gift card entries.
		 *
		 * @param array|null $entry Entry so far.
		 * @param string     $key   Order log key.
		 * @param array      $meta  Meta by key.
		 * @return array|null
		 */
		public static function history_entry( $entry, $key = '', $meta = array() ) {
			if ( null !== $entry || ! in_array( (string) $key, array( self::GIFTCARD_RETURNED, self::GIFTCARD_TAKEN ), true ) ) {
				return $entry;
			}
			$meta   = is_array( $meta ) ? $meta : array();
			$code   = isset( $meta['giftcard_id'] ) ? (string) $meta['giftcard_id'] : '';
			$amount = self::money( isset( $meta['amount'] ) ? (float) $meta['amount'] : 0 );
			$via    = isset( $meta['via'] ) ? (string) $meta['via'] : '';
			$parts  = array();
			if ( self::GIFTCARD_RETURNED === $key ) {
				/* translators: 1: amount, 2: gift card code. */
				$title = sprintf( __( '%1$s put back on gift card %2$s', 'wp-easycart' ), $amount, $code );
				if ( 'cancelled' === $via ) {
					$parts[] = esc_html__( 'The order was cancelled', 'wp-easycart' );
				}
				$items = isset( $meta['refund_items'] ) ? json_decode( (string) $meta['refund_items'], true ) : array();
				foreach ( is_array( $items ) ? $items : array() as $item ) {
					if ( is_array( $item ) && isset( $item['title'], $item['qty'] ) ) {
						$parts[] = esc_html( (int) $item['qty'] . ' × ' . wp_strip_all_tags( (string) $item['title'] ) );
					}
				}
				foreach ( array( 'refund_reason', 'refund_note' ) as $field ) {
					if ( isset( $meta[ $field ] ) && '' !== trim( (string) $meta[ $field ] ) ) {
						$parts[] = esc_html( (string) $meta[ $field ] );
					}
				}
				if ( isset( $meta['refund_by'] ) && '' !== trim( (string) $meta['refund_by'] ) ) {
					/* translators: %s: who refunded the order. */
					$parts[] = esc_html( sprintf( __( 'By %s', 'wp-easycart' ), (string) $meta['refund_by'] ) );
				}
			} else {
				/* translators: 1: amount, 2: gift card code. */
				$title   = sprintf( __( '%1$s taken from gift card %2$s again', 'wp-easycart' ), $amount, $code );
				$parts[] = esc_html__( 'The order is no longer cancelled', 'wp-easycart' );
				if ( isset( $meta['short'] ) && (float) $meta['short'] >= 0.005 ) {
					/* translators: %s: amount the gift card was short. */
					$parts[] = esc_html( sprintf( __( 'The card was %s short', 'wp-easycart' ), self::money( (float) $meta['short'] ) ) );
				}
			}
			return array(
				'title'    => $title,
				'subtitle' => implode( ' · ', $parts ),
				'icon'     => 'dashicons-tickets-alt',
				'category' => 'payments',
			);
		}

		/**
		 * Filter wp_easycart_admin_order_timeline_labels ( the orders list's timeline ).
		 *
		 * @param array $labels log key => label.
		 * @return array
		 */
		public static function timeline_labels( $labels ) {
			$labels = is_array( $labels ) ? $labels : array();
			if ( ! isset( $labels[ self::GIFTCARD_RETURNED ] ) ) {
				$labels[ self::GIFTCARD_RETURNED ] = __( 'Gift card balance put back', 'wp-easycart' );
			}
			if ( ! isset( $labels[ self::GIFTCARD_TAKEN ] ) ) {
				$labels[ self::GIFTCARD_TAKEN ] = __( 'Gift card balance taken again', 'wp-easycart' );
			}
			return $labels;
		}
	}

	wp_easycart_order_returns::init();

endif;
