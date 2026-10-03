<?php
/**
 * What each order has been paid ( 6.0.2 ).
 *
 * An order's grand_total follows the order: WP EasyCart PRO's order screen can add, change and remove lines and edit the
 * totals after the customer paid. What the customer paid does not move with it, so it is recorded on the order:
 *
 *  - ec_order.amount_paid: the money paid toward grand_total ( a checkout or gateway payment, a pay link payment, a payment
 *    marked paid by staff or an accounting system ). Gift cards are not in it: they stay in discount_total ( and
 *    giftcard_total ), as grand_total is after them. NULL = not recorded ( see below ).
 *  - ec_order.overpaid_refund_total: the part of refund_total that gave back an overpayment ( the order came to less than was
 *    paid after an edit, and the difference was refunded ), as opposed to a refund that took something off the order.
 *
 *     balance = grand_total − amount_paid + overpaid_refund_total
 *
 * Positive: still to pay ( Balance due; a pay link charges exactly this ). Negative: the customer paid more than the order
 * comes to ( Refund due ). A refund that takes something off the order ( items, shipping, goodwill ) lowers what the order
 * comes to and what was kept alike, so it does not move the balance. A refunded or cancelled order has no balance.
 *
 * Recorded when:
 *  - an order is placed ( ec_db::insert_order() and wpeasycart_order_inserted: nothing yet, or the total when its status
 *    already counts as paid );
 *  - its status first counts as paid with nothing recorded ( wpeasycart_order_status_update, wpeasycart_order_paid ): the
 *    total at that moment, which is what every checkout gateway charges ( an order already paid with nothing to pay, then
 *    changed, is not: it owes what was added );
 *  - a pay link payment or wp_easycart_order_pay::mark_paid() takes one ( record() );
 *  - just before an order's lines or totals are edited ( stamp(), asked by WP EasyCart PRO and on the line hooks ), so an
 *    edit never rewrites what was paid;
 *  - the 6.0.2 upgrade ( backfill() ): paid or refunded orders whose lines and totals were never edited paid their total.
 *
 * Not recorded ( NULL ): an order paid and then edited before 6.0.2 ( what was paid is not known ), or one made by code that
 * bypasses WP EasyCart. Those keep the old reading: paid in full when the status counts as paid or something was refunded,
 * else unpaid.
 *
 * Accounting extensions read both columns from ec_order ( ready() first ) or summary(), and hear
 * wp_easycart_order_payment_recorded( $order_id, $amount, $args ) for every payment recorded.
 *
 * @since 6.0.2
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_order_payments' ) ) :

	// phpcs:disable PEAR.NamingConventions.ValidClassName -- WP EasyCart class names are lower case.
	/**
	 * What an order has been paid, refunded and still owes.
	 *
	 * @since 6.0.2
	 */
	final class wp_easycart_order_payments {
		// phpcs:enable PEAR.NamingConventions.ValidClassName

		/** Option holding the upgrade backfill's progress ( cursor, max, done ). */
		const BACKFILL_OPTION = 'wp_easycart_amount_paid_backfill';

		/** Orders looked at per backfill query. */
		const BACKFILL_BATCH = 5000;

		/**
		 * Order log keys of an edit to an order's lines or totals ( WP EasyCart PRO ).
		 *
		 * @return array
		 */
		private static function edit_logs() {
			return array( 'order-totals-update', 'order-line-added', 'order-line-updated', 'order-line-deleted' );
		}

		/**
		 * The columns found ( per request ).
		 *
		 * @var bool|null
		 */
		private static $ready = null;

		/**
		 * Whether each order status counts as paid ( per request ).
		 *
		 * @var array
		 */
		private static $approved = array();

		/** Register hooks. */
		public static function init() {
			add_action( 'wpeasycart_order_inserted', array( __CLASS__, 'stamp' ), 1, 1 );
			add_action( 'wp_easycart_admin_order_created', array( __CLASS__, 'stamp' ), 1, 1 );
			add_action( 'wpeasycart_order_status_update', array( __CLASS__, 'status_changed' ), 1, 3 );
			add_action( 'wpeasycart_order_paid', array( __CLASS__, 'stamp' ), 1, 1 );
			/* WP EasyCart PRO fires these before it changes or removes a line. */
			add_action( 'wpeasycart_order_detail_line_update', array( __CLASS__, 'stamp' ), 1, 1 );
			add_action( 'wpeasycart_order_detail_line_delete', array( __CLASS__, 'stamp' ), 1, 1 );
			add_action( 'wpeasycart_partial_order_refund', array( __CLASS__, 'refunded' ), 1, 2 );
			add_action( 'wpeasycart_full_order_refund', array( __CLASS__, 'refunded' ), 1, 1 );
			add_action( 'admin_init', array( __CLASS__, 'maybe_backfill' ) );
		}

		/**
		 * Are the columns there ( EC_UPGRADE_DB 116 )? Read once per request.
		 *
		 * @return bool
		 */
		public static function ready() {
			global $wpdb;
			if ( null === self::$ready ) {
				self::$ready = isset( $wpdb ) && (bool) $wpdb->get_var( "SHOW COLUMNS FROM ec_order LIKE 'overpaid_refund_total'" );
			}
			return self::$ready;
		}

		/** Forget what ready() found ( the upgrade step adds the columns during a request ). */
		public static function reset() {
			self::$ready    = null;
			self::$approved = array();
		}

		/**
		 * Does an order status count as paid?
		 *
		 * @param int $status_id Order status.
		 * @return bool
		 */
		private static function status_approved( $status_id ) {
			global $wpdb;
			$status_id = (int) $status_id;
			if ( ! isset( self::$approved[ $status_id ] ) ) {
				self::$approved[ $status_id ] = (bool) (int) $wpdb->get_var( $wpdb->prepare( 'SELECT is_approved FROM ec_orderstatus WHERE status_id = %d', $status_id ) );
			}
			return self::$approved[ $status_id ];
		}

		/**
		 * Was the order paid, going by its status? A refunded order ( 16, which does not count as paid ) was paid first.
		 *
		 * @param object $order Order row ( orderstatus_id, and is_approved when it has it ).
		 * @return bool
		 */
		public static function paid_status( $order ) {
			if ( 16 === (int) $order->orderstatus_id ) {
				return true;
			}
			if ( property_exists( $order, 'is_approved' ) && null !== $order->is_approved ) {
				return (bool) (int) $order->is_approved;
			}
			return self::status_approved( (int) $order->orderstatus_id );
		}

		/**
		 * The columns this class needs, for one order.
		 *
		 * @param int $order_id Order.
		 * @return object|null
		 */
		private static function row( $order_id ) {
			global $wpdb;
			if ( ! self::ready() ) {
				return $wpdb->get_row( $wpdb->prepare( 'SELECT ec_order.order_id, ec_order.orderstatus_id, ec_order.grand_total, ec_order.refund_total, ec_orderstatus.is_approved FROM ec_order LEFT JOIN ec_orderstatus ON ec_orderstatus.status_id = ec_order.orderstatus_id WHERE ec_order.order_id = %d', (int) $order_id ) );
			}
			return $wpdb->get_row( $wpdb->prepare( 'SELECT ec_order.order_id, ec_order.orderstatus_id, ec_order.grand_total, ec_order.refund_total, ec_order.amount_paid, ec_order.overpaid_refund_total, ec_orderstatus.is_approved FROM ec_order LEFT JOIN ec_orderstatus ON ec_orderstatus.status_id = ec_order.orderstatus_id WHERE ec_order.order_id = %d', (int) $order_id ) );
		}

		/**
		 * Have the order's lines or totals been edited?
		 *
		 * @param int $order_id Order.
		 * @return bool
		 */
		public static function edited( $order_id ) {
			global $wpdb;
			$keys = self::edit_logs();
			return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT order_log_id FROM ec_order_log WHERE order_id = %d AND order_log_key IN ( %s, %s, %s, %s ) LIMIT 1', (int) $order_id, $keys[0], $keys[1], $keys[2], $keys[3] ) );
		}

		/**
		 * Action wpeasycart_order_status_update: stamp(), told whether the order has just become paid ( its status before did
		 * not count as paid ).
		 *
		 * @param int      $order_id  Order.
		 * @param int      $status_id Status now.
		 * @param int|null $previous  Status before, when the caller knows it.
		 * @return bool Whether the order's payments are recorded now.
		 */
		public static function status_changed( $order_id, $status_id = 0, $previous = null ) {
			$became_paid = ( null !== $previous ) && ! self::paid_status(
				(object) array(
					'orderstatus_id' => (int) $previous,
					'is_approved'    => null,
				)
			);
			return self::stamp( $order_id, $became_paid );
		}

		/**
		 * Record what the order has been paid when nothing is recorded yet, and the payment of an order whose status now counts
		 * as paid while nothing was recorded ( its total, what the checkout charged ). Call it before an order's lines or totals
		 * change. Safe to call any number of times.
		 *
		 * @param int  $order_id    Order.
		 * @param bool $became_paid The order has just become paid ( wpeasycart_order_status_update from a status that did not
		 *                          count as paid ): it was paid its total even when its lines were changed while it was unpaid.
		 * @return bool Whether the order's payments are recorded now.
		 */
		public static function stamp( $order_id, $became_paid = false ) {
			global $wpdb;
			$order_id = (int) $order_id;
			if ( $order_id <= 0 || ! self::ready() ) {
				return false;
			}
			$row = self::row( $order_id );
			if ( ! $row ) {
				return false;
			}
			$paid  = self::paid_status( $row );
			$total = round( max( 0, (float) $row->grand_total ), 3 );
			if ( null === $row->amount_paid ) {
				$paid = $paid || (float) $row->refund_total >= 0.005; /* only a paid order can have been refunded */
				if ( $paid && self::edited( $order_id ) ) {
					return false; /* paid, then edited before payments were recorded: what was paid is not known */
				}
				$wpdb->query( $wpdb->prepare( 'UPDATE ec_order SET amount_paid = %s WHERE order_id = %d AND amount_paid IS NULL', (string) ( $paid ? $total : 0 ), $order_id ) );
				return true;
			}
			if ( $paid && (float) $row->amount_paid < 0.005 && $total >= 0.005 && ( $became_paid || ! self::edited( $order_id ) ) ) {
				// The status counts as paid now and no payment was recorded: the order was paid its total ( a checkout gateway,
				// its webhook, or staff setting a paid status ). Not an order that was paid with nothing to pay ( free, or all
				// gift card ) and changed after: it owes what was added.
				if ( 1 === (int) $wpdb->query( $wpdb->prepare( 'UPDATE ec_order SET amount_paid = %s WHERE order_id = %d AND amount_paid < 0.005', (string) $total, $order_id ) ) ) {
					self::announce( $order_id, $total, array( 'source' => 'approved' ) );
				}
			}
			return true;
		}

		/**
		 * Add a payment to what the order has been paid.
		 *
		 * @param int   $order_id Order.
		 * @param float $amount   Amount paid.
		 * @param array $args     source ( pay_link, admin, xero … ), source_label, gateway, reference, balance ( a payment toward
		 *                        the balance of an order already paid ). Passed on to wp_easycart_order_payment_recorded.
		 * @param bool  $announce Fire wp_easycart_order_payment_recorded now ( a caller holding a lock announces after it ).
		 * @return float|false What the order has been paid now, or false.
		 */
		public static function record( $order_id, $amount, $args = array(), $announce = true ) {
			global $wpdb;
			$order_id = (int) $order_id;
			$amount   = round( (float) $amount, 3 );
			if ( $order_id <= 0 || $amount < 0.005 || ! self::ready() ) {
				return false;
			}
			$wpdb->query( $wpdb->prepare( 'UPDATE ec_order SET amount_paid = COALESCE( amount_paid, 0 ) + %s WHERE order_id = %d', (string) $amount, $order_id ) );
			if ( $announce ) {
				self::announce( $order_id, $amount, $args );
			}
			return (float) $wpdb->get_var( $wpdb->prepare( 'SELECT amount_paid FROM ec_order WHERE order_id = %d', $order_id ) );
		}

		/**
		 * Tell listeners ( accounting extensions ) a payment was recorded.
		 *
		 * @param int   $order_id Order.
		 * @param float $amount   Amount.
		 * @param array $args     See record().
		 */
		public static function announce( $order_id, $amount, $args = array() ) {
			/**
			 * A payment was recorded on an order ( ec_order.amount_paid went up ).
			 *
			 * @since 6.0.2
			 * @param int   $order_id Order.
			 * @param float $amount   Amount paid.
			 * @param array $args     source ( approved | pay_link | admin | an extension's own ), source_label, gateway, reference,
			 *                        balance ( true: toward the balance of an order already paid ).
			 */
			do_action( 'wp_easycart_order_payment_recorded', (int) $order_id, round( (float) $amount, 2 ), (array) $args );
		}

		/**
		 * Actions wpeasycart_partial_order_refund / wpeasycart_full_order_refund: the part of a refund that gave back an
		 * overpayment ( the order came to less than was paid ) is kept apart, so it does not count as taking something off the
		 * order as well.
		 *
		 * @param int        $order_id Order.
		 * @param float|null $amount   Amount refunded now; null for a full refund ( all of any overpayment went back ).
		 */
		public static function refunded( $order_id, $amount = null ) {
			global $wpdb;
			$order_id = (int) $order_id;
			if ( $order_id <= 0 || ! self::ready() ) {
				return;
			}
			self::stamp( $order_id );
			$row = self::row( $order_id );
			if ( ! $row || null === $row->amount_paid ) {
				return;
			}
			$over = round( (float) $row->amount_paid - (float) $row->grand_total - (float) $row->overpaid_refund_total, 3 );
			if ( $over < 0.005 ) {
				return;
			}
			$part = ( null === $amount ) ? $over : min( $over, max( 0, round( (float) $amount, 3 ) ) );
			if ( $part >= 0.005 ) {
				$wpdb->query( $wpdb->prepare( 'UPDATE ec_order SET overpaid_refund_total = overpaid_refund_total + %s WHERE order_id = %d', (string) $part, $order_id ) );
			}
		}

		/**
		 * What an order has been paid and what is left to pay or to give back.
		 *
		 * @param object|int $order Order row ( grand_total, refund_total, orderstatus_id, amount_paid, overpaid_refund_total,
		 *                          and is_approved when it has it ) or id.
		 * @return array {
		 *     @type bool   $recorded          Payments are recorded ( false: the old reading, paid in full when the status counts as paid ).
		 *     @type float  $total             grand_total.
		 *     @type float  $paid              Paid toward it.
		 *     @type float  $refunded          refund_total.
		 *     @type float  $overpaid_refunded The part of refund_total that gave back an overpayment.
		 *     @type float  $net               Paid less refunded ( what the store kept ).
		 *     @type float  $balance           To pay ( positive ) or to give back ( negative ).
		 *     @type float  $due               The balance still to pay ( 0 on a refunded or cancelled order ).
		 *     @type float  $over              The overpayment still to give back ( 0 on a refunded or cancelled order ).
		 *     @type string $state             unpaid | partial | paid | overpaid | refunded | cancelled.
		 * }
		 */
		public static function summary( $order ) {
			if ( ! is_object( $order ) ) {
				$order = self::row( (int) $order );
			}
			$out = array(
				'recorded'          => false,
				'total'             => 0.0,
				'paid'              => 0.0,
				'refunded'          => 0.0,
				'overpaid_refunded' => 0.0,
				'net'               => 0.0,
				'balance'           => 0.0,
				'due'               => 0.0,
				'over'              => 0.0,
				'state'             => 'unpaid',
			);
			if ( ! is_object( $order ) || ! isset( $order->grand_total ) ) {
				return $out;
			}
			$status   = isset( $order->orderstatus_id ) ? (int) $order->orderstatus_id : 0;
			$paid_now = isset( $order->orderstatus_id ) ? self::paid_status( $order ) : ! empty( $order->is_approved );
			$total    = round( (float) $order->grand_total, 2 );
			$refunded = round( (float) ( isset( $order->refund_total ) ? $order->refund_total : 0 ), 2 );
			$recorded = property_exists( $order, 'amount_paid' ) && null !== $order->amount_paid;
			if ( $recorded ) {
				$paid = round( (float) $order->amount_paid, 2 );
				if ( $paid_now && $paid < 0.005 && $total >= 0.005 && ! ( isset( $order->order_id ) && self::edited( (int) $order->order_id ) ) ) {
					$paid = $total; /* see stamp(): a paid status with no payment recorded paid the total, unless the order was changed */
				}
				$over_refunded = round( (float) ( isset( $order->overpaid_refund_total ) ? $order->overpaid_refund_total : 0 ), 2 );
				$balance       = round( $total - $paid + $over_refunded, 2 );
			} else {
				$was_paid      = $paid_now || $refunded >= 0.005; /* only a paid order can have been refunded */
				$paid          = $was_paid ? $total : 0.0;
				$over_refunded = 0.0;
				$balance       = $was_paid ? 0.0 : $total;
			}
			if ( 16 === $status ) {
				$state = 'refunded';
			} elseif ( 19 === $status ) {
				$state = 'cancelled';
			} elseif ( $balance >= 0.005 ) {
				$state = ( $paid >= 0.005 || $paid_now ) ? 'partial' : 'unpaid'; /* a paid status with more to pay has a balance */
			} elseif ( $balance <= -0.005 ) {
				$state = 'overpaid';
			} else {
				$state = 'paid';
			}
			$closed = in_array( $state, array( 'refunded', 'cancelled' ), true );
			return array(
				'recorded'          => $recorded,
				'total'             => $total,
				'paid'              => $paid,
				'refunded'          => $refunded,
				'overpaid_refunded' => $over_refunded,
				'net'               => round( $paid - $refunded, 2 ),
				'balance'           => $balance,
				'due'               => $closed ? 0.0 : max( 0.0, $balance ),
				'over'              => $closed ? 0.0 : max( 0.0, -1 * $balance ),
				'state'             => $state,
			);
		}

		/**
		 * What can still be refunded: what was paid less what was refunded. While nothing is recorded as paid ( an order from
		 * before 6.0.2, or one not paid ), the order's total less refunds, as before.
		 *
		 * @param object|int $order Order row or id.
		 * @return float
		 */
		public static function refundable( $order ) {
			$sum = self::summary( $order );
			return max( 0.0, round( self::refund_basis( $sum ) - $sum['refunded'], 2 ) );
		}

		/**
		 * What a refund is measured against to call it full: everything paid, or the order's total while nothing is recorded
		 * as paid.
		 *
		 * @param object|int|array $order Order row, id, or a summary().
		 * @return float
		 */
		public static function refund_basis( $order ) {
			$sum = ( is_array( $order ) && isset( $order['recorded'] ) ) ? $order : self::summary( $order );
			return ( $sum['recorded'] && $sum['paid'] >= 0.005 ) ? $sum['paid'] : $sum['total'];
		}

		// ------------------------------------------------------------------
		// Upgrade.
		// ------------------------------------------------------------------

		/** Action admin_init: finish the upgrade's backfill ( a store that recorded 6.0.2 before this change gets it here ). */
		public static function maybe_backfill() {
			if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
				return;
			}
			$state = get_option( self::BACKFILL_OPTION );
			if ( ( is_array( $state ) && ! empty( $state['done'] ) ) || ! self::ready() ) {
				return;
			}
			self::backfill();
		}

		/**
		 * Record what paid orders from before 6.0.2 were paid: their total, unless their lines or totals were edited ( then what
		 * was paid is not known and they stay unrecorded ). An order with a refund was paid, whatever its status is now ( refunded,
		 * then cancelled ). Unpaid orders are recorded when something next happens to them.
		 * Works in slices of order ids and resumes where it stopped.
		 *
		 * @param int $batch       Order ids per query.
		 * @param int $max_batches Queries this call.
		 * @return bool Finished.
		 */
		public static function backfill( $batch = self::BACKFILL_BATCH, $max_batches = 20 ) {
			global $wpdb;
			if ( ! self::ready() ) {
				return false;
			}
			$state = get_option( self::BACKFILL_OPTION );
			if ( ! is_array( $state ) || ! isset( $state['cursor'], $state['max'] ) ) {
				$state = array(
					'cursor' => 0,
					'max'    => (int) $wpdb->get_var( 'SELECT MAX( order_id ) FROM ec_order' ),
					'done'   => 0,
				);
			}
			if ( ! empty( $state['done'] ) ) {
				return true;
			}
			$batch = max( 1, (int) $batch );
			$keys  = self::edit_logs();
			for ( $i = 0; $i < (int) $max_batches && (int) $state['cursor'] < (int) $state['max']; $i++ ) {
				$from = (int) $state['cursor'];
				$to   = min( (int) $state['max'], $from + $batch );
				// One multi-table UPDATE per slice ( MySQL 5.5 ): the orders with an edit are found once, in a derived table.
				$result = $wpdb->query(
					$wpdb->prepare(
						'UPDATE ec_order
						LEFT JOIN ec_orderstatus ON ec_orderstatus.status_id = ec_order.orderstatus_id
						LEFT JOIN ( SELECT DISTINCT order_id FROM ec_order_log WHERE order_id > %d AND order_id <= %d AND order_log_key IN ( %s, %s, %s, %s ) ) AS wpec_edited ON wpec_edited.order_id = ec_order.order_id
						SET ec_order.amount_paid = ec_order.grand_total
						WHERE ec_order.order_id > %d AND ec_order.order_id <= %d AND ec_order.amount_paid IS NULL AND wpec_edited.order_id IS NULL
						AND ( ec_orderstatus.is_approved = 1 OR ec_order.orderstatus_id = 16 OR ec_order.refund_total >= 0.005 )',
						$from,
						$to,
						$keys[0],
						$keys[1],
						$keys[2],
						$keys[3],
						$from,
						$to
					)
				);
				if ( false === $result ) {
					update_option( self::BACKFILL_OPTION, $state ); // autoloaded: admin_init reads it on every admin request
					return false;
				}
				$state['cursor'] = $to;
			}
			$state['done'] = ( (int) $state['cursor'] >= (int) $state['max'] ) ? 1 : 0;
			update_option( self::BACKFILL_OPTION, $state ); // autoloaded: admin_init reads it on every admin request
			return (bool) $state['done'];
		}
	}

	wp_easycart_order_payments::init();

endif;
