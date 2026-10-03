<?php
/**
 * The order facts Reports and store research read ( 6.0.2, EC_UPGRADE_DB 119 ).
 *
 * - ec_order_transaction: every payment and every refund, with the date it happened. Reports puts sales on the order's
 *   date, refunds on the refund's date and money received on the payment's date. Rows are reconciled against the order,
 *   never guessed: sync() compares what the order says was paid ( wp_easycart_order_payments::summary() ) and refunded
 *   ( ec_order.refund_total ) with the rows kept, and writes the difference as a new row, dated now. A refund row takes its
 *   reason, lines and note from the refund's order log entry ( the refund drawer and the Stripe webhook write one before
 *   they fire the refund hooks ).
 * - ec_order.customer_key: md5 of the order's lower-cased email, so one customer is one key across guest checkouts and an
 *   account ( new vs returning, repeat rate, lifetime value ).
 * - ec_order.paid_at / fulfilled_at ( UTC ): when the order was first paid, and first shipped or picked up.
 * - ec_order.device: phone | tablet | desktop, from the browser that placed the order ( no user agent is kept ).
 * - ec_subscription.cancelled_at ( UTC ).
 * - ec_user lifetime totals ( completed_order_count, lifetime_spend, last_order_date ) with one rule: paid orders, less
 *   refunds ( customer_totals() ).
 *
 * History is filled in by backfill(): every phase is idempotent ( it only writes what is missing ), so the upgrade step,
 * admin_init and a single order ( ensure_backfilled() before its first sync ) can all run it.
 *
 * @since 6.0.2
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_order_ledger' ) ) :

	// phpcs:disable PEAR.NamingConventions.ValidClassName -- WP EasyCart class names are lower case.
	/**
	 * Payments, refunds and order facts for Reports.
	 *
	 * @since 6.0.2
	 */
	final class wp_easycart_order_ledger {
		// phpcs:enable PEAR.NamingConventions.ValidClassName

		/** The database update that added the tables and columns. */
		const DB_VERSION = 119;

		/** Option holding the backfill's progress ( phase, cursor, max, done ). */
		const BACKFILL_OPTION = 'wp_easycart_reports_backfill';

		/** Order ids per backfill query. */
		const BATCH = 5000;

		/** Refund log keys ( the order screen's refund drawer, the legacy refund, the Stripe webhook ). */
		const REFUND_LOGS = array( 'order-refund-full', 'order-refund-partial' );

		/**
		 * The tables and columns found ( per request ).
		 *
		 * @var bool|null
		 */
		private static $ready = null;

		/**
		 * Details of the payment being recorded now, per order ( wp_easycart_order_payment_recorded arguments ).
		 *
		 * @var array
		 */
		private static $pending = array();

		/**
		 * Orders synced in this request, with the state they were synced at ( skips repeat work ).
		 *
		 * @var array
		 */
		private static $synced = array();

		/**
		 * Whether each order status counts as paid ( per request ).
		 *
		 * @var array
		 */
		private static $approved = array();

		/** Register hooks. */
		public static function init() {
			add_action( 'wpeasycart_order_inserted', array( __CLASS__, 'sync' ), 40, 1 );
			add_action( 'wp_easycart_admin_order_created', array( __CLASS__, 'sync' ), 40, 1 );
			add_action( 'wpeasycart_order_status_update', array( __CLASS__, 'status_changed' ), 40, 3 );
			add_action( 'wpeasycart_order_paid', array( __CLASS__, 'sync' ), 40, 1 );
			add_action( 'wp_easycart_order_payment_recorded', array( __CLASS__, 'payment_recorded' ), 5, 3 );
			add_action( 'wpeasycart_partial_order_refund', array( __CLASS__, 'sync' ), 40, 1 );
			add_action( 'wpeasycart_full_order_refund', array( __CLASS__, 'sync' ), 40, 1 );
			add_action( 'wpeasycart_order_updated', array( __CLASS__, 'sync' ), 40, 1 );
			add_action( 'wp_easycart_order_shipment_added', array( __CLASS__, 'shipment_added' ), 40, 3 );
			add_action( 'wpeasycart_subscription_cancelled', array( __CLASS__, 'subscription_cancelled_by_customer' ), 40, 2 );
			add_action( 'wpeasycart_subscription_canceled', array( __CLASS__, 'subscription_changed' ), 40, 1 );
			add_action( 'wpeasycart_subscription_updated', array( __CLASS__, 'subscription_changed' ), 40, 1 );
			add_action( 'admin_init', array( __CLASS__, 'maybe_backfill' ) );
		}

		/**
		 * Are the 6.0.2 report tables and columns there ( EC_UPGRADE_DB 119 )? Read once per request.
		 *
		 * @return bool
		 */
		public static function ready() {
			global $wpdb;
			if ( null === self::$ready ) {
				$version     = (int) get_option( 'ec_option_db_new_version' );
				self::$ready = isset( $wpdb ) && $version >= self::DB_VERSION;
				/* The columns are checked once per database version ( a failed update step leaves them out ), then remembered. */
				if ( self::$ready && (int) get_option( 'wp_easycart_reports_columns', 0 ) !== $version ) {
					self::$ready = (bool) $wpdb->get_var( "SHOW COLUMNS FROM ec_order LIKE 'customer_key'" );
					if ( self::$ready ) {
						update_option( 'wp_easycart_reports_columns', $version );
					}
				}
			}
			return self::$ready;
		}

		/** Forget what ready() found ( the upgrade step adds the tables during a request ). */
		public static function reset() {
			self::$ready    = null;
			self::$synced   = array();
			self::$approved = array();
		}

		/**
		 * Now, in UTC, for the new columns.
		 *
		 * @return string Y-m-d H:i:s.
		 */
		public static function now() {
			return gmdate( 'Y-m-d H:i:s' );
		}

		/**
		 * How far the database clock ( order_date, the order log ) is ahead of UTC, in seconds.
		 *
		 * @return int
		 */
		public static function db_offset() {
			static $offset = null;
			global $wpdb;
			if ( null === $offset ) {
				$offset = (int) $wpdb->get_var( 'SELECT TIMESTAMPDIFF( SECOND, UTC_TIMESTAMP(), NOW() )' );
			}
			return $offset;
		}

		/**
		 * The customer key for an email: md5 of the trimmed, lower-cased address ( '' for none ).
		 *
		 * @param string $email Email.
		 * @return string
		 */
		public static function customer_key( $email ) {
			$email = strtolower( trim( (string) $email ) );
			return ( '' === $email ) ? '' : md5( $email );
		}

		/**
		 * Phone, tablet or desktop, from a user agent ( '' when it is not a browser ).
		 *
		 * @param string $agent User agent.
		 * @return string
		 */
		public static function device_class( $agent ) {
			$agent = (string) $agent;
			if ( '' === $agent || ! preg_match( '/Mozilla|Opera/i', $agent ) || preg_match( '/bot|crawl|spider|slurp|facebookexternalhit|preview|headless|curl|wget|python|stripe/i', $agent ) ) {
				return '';
			}
			if ( preg_match( '/iPad|Tablet|PlayBook|Silk|Kindle|(Android(?!.*Mobile))/i', $agent ) ) {
				return 'tablet';
			}
			if ( preg_match( '/Mobi|iPhone|iPod|Android|Windows Phone|BlackBerry|Opera Mini/i', $agent ) ) {
				return 'phone';
			}
			return 'desktop';
		}

		/**
		 * Does an order status count as paid?
		 *
		 * @param int $status_id Status.
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
		 * An order with what sync() reads.
		 *
		 * @param int $order_id Order.
		 * @return object|null
		 */
		private static function order( $order_id ) {
			global $wpdb;
			return $wpdb->get_row( $wpdb->prepare( 'SELECT ec_order.*, ec_orderstatus.is_approved FROM ec_order LEFT JOIN ec_orderstatus ON ec_orderstatus.status_id = ec_order.orderstatus_id WHERE ec_order.order_id = %d', (int) $order_id ) );
		}

		// ------------------------------------------------------------------
		// A new order ( called by ec_db right after each insert ).
		// ------------------------------------------------------------------

		/**
		 * Record the new order's customer key and device. ec_db calls this after every order insert ( checkout, the Stripe
		 * webhook's order, subscriptions, renewals ), next to wp_easycart_order_source::stamp().
		 *
		 * @param int    $order_id Order.
		 * @param string $kind     checkout | staff | renewal.
		 */
		public static function order_inserted( $order_id, $kind = 'checkout' ) {
			global $wpdb;
			$order_id = (int) $order_id;
			if ( $order_id <= 0 || ! self::ready() ) {
				return;
			}
			$email  = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT user_email FROM ec_order WHERE order_id = %d', $order_id ) );
			$device = '';
			if ( 'checkout' === $kind ) {
				/* The shopper's browser; Stripe's webhook and other server calls are no browser and leave it blank. */
				if ( isset( $_SERVER['HTTP_USER_AGENT'] ) && ! ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) ) {
					$device = self::device_class( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) );
				}
			}
			/**
			 * The device an order was placed from ( phone | tablet | desktop | '' ).
			 *
			 * @since 6.0.2
			 * @param string $device   Device.
			 * @param int    $order_id Order.
			 * @param string $kind     checkout | staff | renewal.
			 */
			$device = (string) apply_filters( 'wp_easycart_order_device', $device, $order_id, $kind );
			$wpdb->update(
				'ec_order',
				array(
					'customer_key' => self::customer_key( $email ),
					'device'       => in_array( $device, array( 'phone', 'tablet', 'desktop' ), true ) ? $device : '',
				),
				array( 'order_id' => $order_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
			if ( 'renewal' === $kind ) {
				/* Renewals never fire wpeasycart_order_inserted: they are paid when they are made. */
				self::sync( $order_id );
			}
		}

		// ------------------------------------------------------------------
		// Keeping the rows in step with the order.
		// ------------------------------------------------------------------

		/**
		 * Action wp_easycart_order_payment_recorded: keep the payment's details for the row sync() writes next.
		 *
		 * @param int   $order_id Order.
		 * @param float $amount   Amount.
		 * @param array $args     source, source_label, gateway, reference, balance.
		 */
		public static function payment_recorded( $order_id, $amount = 0, $args = array() ) {
			self::$pending[ (int) $order_id ] = is_array( $args ) ? $args : array();
			unset( self::$synced[ (int) $order_id ] );
			self::sync( (int) $order_id );
		}

		/**
		 * Action wpeasycart_order_status_update: the first time an order counts as fulfilled ( shipped, delivered, picked up )
		 * is its fulfilled_at; then sync().
		 *
		 * @param int      $order_id  Order.
		 * @param int      $status_id Status now.
		 * @param int|null $previous  Status before.
		 */
		public static function status_changed( $order_id, $status_id = 0, $previous = null ) {
			global $wpdb;
			$order_id = (int) $order_id;
			if ( $order_id <= 0 || ! self::ready() ) {
				return;
			}
			$status_id = (int) $status_id;
			if ( self::is_fulfilled_status( $status_id ) ) {
				$wpdb->query( $wpdb->prepare( 'UPDATE ec_order SET fulfilled_at = %s WHERE order_id = %d AND fulfilled_at IS NULL', self::now(), $order_id ) );
			}
			unset( self::$synced[ $order_id ] );
			self::sync( $order_id );
		}

		/**
		 * Shipped, delivered or picked up?
		 *
		 * @param int $status_id Status.
		 * @return bool
		 */
		public static function is_fulfilled_status( $status_id ) {
			return in_array( (int) $status_id, self::fulfilled_status_ids(), true );
		}

		/**
		 * Shipped, picked up and ( once made ) Delivered.
		 *
		 * @return int[]
		 */
		public static function fulfilled_status_ids() {
			if ( class_exists( 'wp_easycart_shipments' ) && method_exists( 'wp_easycart_shipments', 'fulfilled_status_ids' ) ) {
				return array_map( 'intval', wp_easycart_shipments::fulfilled_status_ids() );
			}
			return array( 2, 18 );
		}

		/**
		 * Action wp_easycart_order_shipment_added: the first package that went out is the order's fulfilled_at.
		 *
		 * @param int   $order_id    Order.
		 * @param int   $shipment_id Package.
		 * @param array $args        What was recorded ( status label | shipped | delivered, is_return ).
		 */
		public static function shipment_added( $order_id, $shipment_id = 0, $args = array() ) {
			global $wpdb;
			if ( ! self::ready() || ( is_array( $args ) && ! empty( $args['is_return'] ) ) ) {
				return;
			}
			$status = ( is_array( $args ) && isset( $args['status'] ) ) ? (string) $args['status'] : 'shipped';
			if ( 'label' === $status ) {
				return; /* a label bought is not a package handed over */
			}
			$wpdb->query( $wpdb->prepare( 'UPDATE ec_order SET fulfilled_at = %s WHERE order_id = %d AND fulfilled_at IS NULL', self::now(), (int) $order_id ) );
		}

		/**
		 * Bring an order's payment and refund rows in step with the order, set paid_at, and refresh its customer's totals.
		 * Safe to call any number of times: it only writes a difference.
		 *
		 * @param int $order_id Order.
		 */
		public static function sync( $order_id ) {
			global $wpdb;
			$order_id = (int) $order_id;
			if ( $order_id <= 0 || ! self::ready() ) {
				return;
			}
			self::ensure_backfilled( $order_id );
			$order = self::order( $order_id );
			if ( ! $order ) {
				return;
			}
			$paid     = self::paid_target( $order );
			$refunded = round( max( 0, (float) $order->refund_total ), 2 );
			$state    = $paid . '|' . $refunded . '|' . (int) $order->orderstatus_id;
			if ( isset( self::$synced[ $order_id ] ) && self::$synced[ $order_id ] === $state && ! isset( self::$pending[ $order_id ] ) ) {
				return;
			}
			self::$synced[ $order_id ] = $state;

			if ( '' === (string) $order->customer_key && '' !== trim( (string) $order->user_email ) ) {
				$wpdb->update( 'ec_order', array( 'customer_key' => self::customer_key( $order->user_email ) ), array( 'order_id' => $order_id ), array( '%s' ), array( '%d' ) );
			}

			/* Payments. */
			$had = self::sum( $order_id, 'payment' );
			$gap = round( $paid - $had, 2 );
			if ( abs( $gap ) >= 0.01 ) {
				$args  = isset( self::$pending[ $order_id ] ) ? self::$pending[ $order_id ] : array();
				$first = ( $had < 0.005 );
				self::write_if_unchanged(
					$order_id,
					'payment',
					$had,
					array(
						'amount'    => $gap,
						'source'    => ! empty( $args['source'] ) ? (string) $args['source'] : ( $gap < 0 ? 'adjustment' : ( $first && doing_action( 'wpeasycart_order_inserted' ) ? 'checkout' : 'status' ) ),
						'gateway'   => ! empty( $args['gateway'] ) ? (string) $args['gateway'] : (string) ( '' !== (string) $order->order_gateway ? $order->order_gateway : $order->payment_method ),
						'reference' => ! empty( $args['reference'] ) ? (string) $args['reference'] : ( $first ? (string) $order->gateway_transaction_id : '' ),
						'reason'    => ! empty( $args['source_label'] ) ? (string) $args['source_label'] : '',
						'details'   => self::payment_details( $args ),
					)
				);
			}
			unset( self::$pending[ $order_id ] );
			if ( $paid >= 0.005 && null === $order->paid_at ) {
				$wpdb->query( $wpdb->prepare( 'UPDATE ec_order SET paid_at = %s WHERE order_id = %d AND paid_at IS NULL', self::now(), $order_id ) );
			}

			/* Refunds. */
			$had = self::sum( $order_id, 'refund' );
			$gap = round( $refunded - $had, 2 );
			if ( abs( $gap ) >= 0.01 ) {
				$log   = ( $gap > 0 ) ? self::refund_log( $order_id ) : null;
				$parts = array();
				/* 6.0.2: a refund split across the order's payments ( the refund drawer, the Stripe webhook ) is one row per
				   payment, each naming the payment it gave money back from ( wp_easycart_order_refunds ). */
				if ( $log && ! empty( $log['payments'] ) ) {
					$sum = 0.0;
					foreach ( $log['payments'] as $part ) {
						$sum += $part['amount'];
					}
					if ( abs( round( $sum, 2 ) - $gap ) < 0.01 ) {
						$parts = $log['payments'];
					}
				}
				if ( ! $parts ) {
					$parts = array(
						array(
							'payment'   => 0,
							'amount'    => $gap,
							'reference' => $log ? $log['reference'] : '',
						),
					);
				}
				foreach ( $parts as $part ) {
					$details = $log ? $log['details'] : array();
					if ( ! empty( $part['payment'] ) ) {
						$details['payment'] = (int) $part['payment'];
					}
					$written = self::write_if_unchanged(
						$order_id,
						'refund',
						$had,
						array(
							'amount'       => $part['amount'],
							'source'       => $log ? $log['source'] : ( $gap < 0 ? 'adjustment' : 'other' ),
							'gateway'      => ( ! empty( $part['gateway'] ) ) ? (string) $part['gateway'] : (string) ( '' !== (string) $order->order_gateway ? $order->order_gateway : $order->payment_method ),
							'reference'    => ( '' !== (string) $part['reference'] ) ? (string) $part['reference'] : ( $log ? $log['reference'] : '' ),
							'reason'       => $log ? $log['reason'] : '',
							'details'      => $details,
							'order_log_id' => $log ? $log['id'] : 0,
						)
					);
					if ( ! $written ) {
						break; /* another request wrote these rows first */
					}
					$had = round( $had + (float) $part['amount'], 2 );
				}
			}

			if ( (int) $order->user_id > 0 ) {
				self::customer_totals( array( (int) $order->user_id ) );
			}
		}

		/**
		 * What the order says was paid: wp_easycart_order_payments::summary() ( amount_paid, or the total while its status
		 * counts as paid and nothing is recorded ). A cancelled order that was never paid has 0.
		 *
		 * @param object $order Order row with is_approved.
		 * @return float
		 */
		private static function paid_target( $order ) {
			if ( class_exists( 'wp_easycart_order_payments' ) ) {
				$sum = wp_easycart_order_payments::summary( $order );
				return round( max( 0, (float) $sum['paid'] ), 2 );
			}
			$paid = ( ! empty( $order->is_approved ) || 16 === (int) $order->orderstatus_id );
			return $paid ? round( max( 0, (float) $order->grand_total ), 2 ) : 0.0;
		}

		/**
		 * The rows of one kind kept for an order, summed.
		 *
		 * @param int    $order_id Order.
		 * @param string $type     payment | refund.
		 * @return float
		 */
		public static function sum( $order_id, $type ) {
			global $wpdb;
			return round( (float) $wpdb->get_var( $wpdb->prepare( 'SELECT SUM( amount ) FROM ec_order_transaction WHERE order_id = %d AND txn_type = %s', (int) $order_id, (string) $type ) ), 2 );
		}

		/**
		 * Write a row only while the rows kept still add up to what was read ( two requests syncing the same order at once
		 * write one row, not two ). No named lock: MySQL before 5.7 holds one per connection, and pay links hold one.
		 *
		 * @param int    $order_id Order.
		 * @param string $type     payment | refund.
		 * @param float  $had      The sum read before.
		 * @param array  $row      amount, source, gateway, reference, reason, details, order_log_id.
		 * @return bool Written.
		 */
		private static function write_if_unchanged( $order_id, $type, $had, $row ) {
			global $wpdb;
			$details = ( ! empty( $row['details'] ) && is_array( $row['details'] ) ) ? wp_json_encode( $row['details'] ) : '';
			$done    = $wpdb->query(
				$wpdb->prepare(
					'INSERT INTO ec_order_transaction ( order_id, txn_type, amount, created_at, source, gateway, reference, reason, details, order_log_id )
					SELECT %d, %s, %s, %s, %s, %s, %s, %s, %s, %d FROM DUAL
					WHERE ( SELECT ROUND( IFNULL( SUM( t.amount ), 0 ), 2 ) FROM ( SELECT amount FROM ec_order_transaction WHERE order_id = %d AND txn_type = %s ) AS t ) = %s',
					(int) $order_id,
					(string) $type,
					(string) round( (float) $row['amount'], 3 ),
					self::now(),
					substr( sanitize_key( (string) $row['source'] ), 0, 40 ),
					substr( sanitize_text_field( (string) $row['gateway'] ), 0, 40 ),
					substr( sanitize_text_field( (string) $row['reference'] ), 0, 100 ),
					substr( sanitize_text_field( (string) $row['reason'] ), 0, 255 ),
					$details,
					isset( $row['order_log_id'] ) ? (int) $row['order_log_id'] : 0,
					(int) $order_id,
					(string) $type,
					number_format( (float) $had, 2, '.', '' )
				)
			);
			if ( $done ) {
				/**
				 * A payment or refund row was kept for an order ( ec_order_transaction ).
				 *
				 * @since 6.0.2
				 * @param int    $order_id Order.
				 * @param string $type     payment | refund.
				 * @param float  $amount   Amount ( negative for an adjustment ).
				 * @param array  $row      source, gateway, reference, reason, details.
				 */
				do_action( 'wp_easycart_order_transaction_recorded', (int) $order_id, (string) $type, round( (float) $row['amount'], 2 ), $row );
			}
			return (bool) $done;
		}

		/**
		 * The newest refund log entry of an order not yet tied to a refund row: its reason, lines, note, who and where.
		 *
		 * @param int $order_id Order.
		 * @return array|null id, reason, source, reference, details.
		 */
		private static function refund_log( $order_id ) {
			global $wpdb;
			$log_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT l.order_log_id FROM ec_order_log l LEFT JOIN ec_order_transaction t ON t.order_log_id = l.order_log_id AND t.txn_type = %s WHERE l.order_id = %d AND l.order_log_key IN ( %s, %s ) AND t.transaction_id IS NULL ORDER BY l.order_log_id DESC LIMIT 1',
					'refund',
					(int) $order_id,
					self::REFUND_LOGS[0],
					self::REFUND_LOGS[1]
				)
			);
			if ( $log_id <= 0 ) {
				return null;
			}
			$meta = array();
			foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT order_log_meta_key AS k, order_log_meta_value AS v FROM ec_order_log_meta WHERE order_log_id = %d', $log_id ) ) as $m ) {
				$meta[ (string) $m->k ] = (string) $m->v;
			}
			$details = array();
			if ( isset( $meta['refund_items'] ) ) {
				$items = json_decode( $meta['refund_items'], true );
				if ( is_array( $items ) ) {
					$details['lines'] = $items;
				}
			}
			$names = array(
				'refund_note'     => 'note',
				'refund_by'       => 'by',
				'refund_shipping' => 'shipping',
				'refund_tax'      => 'tax',
				'refund_manual'   => 'manual',
			);
			foreach ( $names as $key => $name ) {
				if ( isset( $meta[ $key ] ) && '' !== $meta[ $key ] ) {
					$details[ $name ] = $meta[ $key ];
				}
			}
			$payments = array();
			if ( isset( $meta['refund_payments'] ) ) {
				$list = json_decode( $meta['refund_payments'], true );
				foreach ( is_array( $list ) ? $list : array() as $part ) {
					if ( is_array( $part ) && isset( $part['payment'], $part['amount'] ) && (float) $part['amount'] >= 0.005 ) {
						$payments[] = array(
							'payment'   => (int) $part['payment'],
							'amount'    => round( (float) $part['amount'], 2 ),
							'reference' => isset( $part['reference'] ) ? (string) $part['reference'] : '',
							'gateway'   => isset( $part['gateway'] ) ? (string) $part['gateway'] : '',
						);
					}
				}
			}
			return array(
				'id'        => $log_id,
				'payments'  => $payments,
				'reason'    => isset( $meta['refund_reason'] ) ? $meta['refund_reason'] : '',
				'source'    => isset( $meta['refund_source'] ) && '' !== $meta['refund_source'] ? $meta['refund_source'] : 'admin',
				'reference' => isset( $meta['refund_reference'] ) ? $meta['refund_reference'] : '',
				'details'   => $details,
			);
		}

		/**
		 * What a payment row keeps besides its amount: toward a balance, the gateway's charge, the card ( brand and last four ).
		 *
		 * @param array $args wp_easycart_order_payment_recorded arguments.
		 * @return array
		 */
		private static function payment_details( $args ) {
			$details = array();
			if ( ! empty( $args['balance'] ) ) {
				$details['balance'] = 1;
			}
			if ( ! empty( $args['charge'] ) ) {
				$details['charge'] = substr( sanitize_text_field( (string) $args['charge'] ), 0, 100 );
			}
			if ( ! empty( $args['card'] ) ) {
				$details['card'] = substr( sanitize_text_field( (string) $args['card'] ), 0, 60 );
			}
			return $details;
		}

		/**
		 * An order's payment and refund rows, oldest first.
		 *
		 * @param int $order_id Order.
		 * @return array
		 */
		public static function transactions( $order_id ) {
			global $wpdb;
			if ( ! self::ready() ) {
				return array();
			}
			return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ec_order_transaction WHERE order_id = %d ORDER BY created_at ASC, transaction_id ASC', (int) $order_id ) );
		}

		// ------------------------------------------------------------------
		// Customer totals.
		// ------------------------------------------------------------------

		/**
		 * Recompute accounts' order count, lifetime spend and last order with one rule: orders whose status counts as paid
		 * ( a refunded order adds nothing ), their totals less refunds. Used by every path that changes an order, and by the
		 * customers list's history backfill.
		 *
		 * @param int[] $user_ids Accounts.
		 */
		public static function customer_totals( $user_ids ) {
			global $wpdb;
			$ids = array();
			foreach ( (array) $user_ids as $user_id ) {
				$user_id = (int) $user_id;
				if ( $user_id > 0 ) {
					$ids[ $user_id ] = $user_id;
				}
			}
			if ( empty( $ids ) ) {
				return;
			}
			$id_list = implode( ',', $ids );
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $id_list is an implode of (int)-cast ids.
			$wpdb->query(
				"UPDATE ec_user u
				LEFT JOIN (
					SELECT o.user_id,
						SUM( CASE WHEN s.is_approved = 1 AND o.orderstatus_id <> 16 THEN 1 ELSE 0 END ) AS order_count,
						SUM( CASE WHEN s.is_approved = 1 OR o.orderstatus_id = 16 THEN o.grand_total - o.refund_total ELSE 0 END ) AS spend,
						MAX( CASE WHEN s.is_approved = 1 THEN o.order_date ELSE NULL END ) AS last_order
					FROM ec_order o INNER JOIN ec_orderstatus s ON s.status_id = o.orderstatus_id
					WHERE o.user_id IN ( {$id_list} )
					GROUP BY o.user_id
				) t ON t.user_id = u.user_id
				SET u.completed_order_count = COALESCE( t.order_count, 0 ),
					u.lifetime_spend = GREATEST( 0, COALESCE( t.spend, 0 ) ),
					u.last_order_date = t.last_order,
					u.history_aggregates_built = 1
				WHERE u.user_id IN ( {$id_list} )"
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( function_exists( 'wp_cache_delete' ) ) {
				wp_cache_delete( 'wpeasycart-user-list', 'wpeasycart-users' );
			}
		}

		// ------------------------------------------------------------------
		// Subscriptions.
		// ------------------------------------------------------------------

		/**
		 * Action wpeasycart_subscription_cancelled ( the customer cancelled from My Account ).
		 *
		 * @param int $user_id         Account.
		 * @param int $subscription_id Subscription.
		 */
		public static function subscription_cancelled_by_customer( $user_id, $subscription_id = 0 ) {
			self::subscription_changed( (int) $subscription_id );
		}

		/**
		 * Actions wpeasycart_subscription_canceled / _updated: stamp cancelled_at the first time a subscription is seen
		 * cancelled or ended, and clear it when it is active again.
		 *
		 * @param int $subscription_id Subscription.
		 */
		public static function subscription_changed( $subscription_id ) {
			global $wpdb;
			$subscription_id = (int) $subscription_id;
			if ( $subscription_id <= 0 || ! self::ready() ) {
				return;
			}
			$status = strtolower( (string) $wpdb->get_var( $wpdb->prepare( 'SELECT subscription_status FROM ec_subscription WHERE subscription_id = %d', $subscription_id ) ) );
			if ( self::subscription_is_ended( $status ) ) {
				$wpdb->query( $wpdb->prepare( 'UPDATE ec_subscription SET cancelled_at = %s WHERE subscription_id = %d AND cancelled_at IS NULL', self::now(), $subscription_id ) );
			} elseif ( '' !== $status ) {
				$wpdb->query( $wpdb->prepare( 'UPDATE ec_subscription SET cancelled_at = NULL WHERE subscription_id = %d', $subscription_id ) );
			}
		}

		/**
		 * Does a subscription status mean it ended?
		 *
		 * @param string $status Status ( any case ).
		 * @return bool
		 */
		public static function subscription_is_ended( $status ) {
			return in_array( strtolower( trim( (string) $status ) ), array( 'canceled', 'cancelled', 'ended', 'expired', 'incomplete_expired' ), true );
		}

		/**
		 * Daily: subscriptions that ended by a path that fired no hook ( a gateway sync ) get today as their cancelled_at.
		 */
		public static function sweep_subscriptions() {
			global $wpdb;
			if ( ! self::ready() ) {
				return;
			}
			$wpdb->query( $wpdb->prepare( "UPDATE ec_subscription SET cancelled_at = %s WHERE cancelled_at IS NULL AND LOWER( subscription_status ) IN ( 'canceled', 'cancelled', 'ended', 'expired', 'incomplete_expired' )", self::now() ) );
		}

		// ------------------------------------------------------------------
		// History ( the upgrade ).
		// ------------------------------------------------------------------

		/**
		 * The backfill phases, in order. Each writes only what is missing, for the orders in ( $from, $to ].
		 *
		 * @return string[]
		 */
		private static function phases() {
			return array( 'keys', 'paid', 'fulfilled', 'payments', 'refunds', 'customers', 'subscriptions' );
		}

		/** Action admin_init: carry the backfill on ( a large store, or one that recorded 6.0.2 before this change ). */
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
		 * Is the history filled in?
		 *
		 * @return bool
		 */
		public static function backfilled() {
			$state = get_option( self::BACKFILL_OPTION );
			return is_array( $state ) && ! empty( $state['done'] );
		}

		/**
		 * Fill in the history in slices of order ids, phase after phase, and resume where it stopped.
		 *
		 * @param int $max_batches Queries this call.
		 * @return bool Finished.
		 */
		public static function backfill( $max_batches = 20 ) {
			global $wpdb;
			if ( ! self::ready() ) {
				return false;
			}
			$state = get_option( self::BACKFILL_OPTION );
			if ( ! is_array( $state ) || ! isset( $state['phase'], $state['cursor'], $state['max'] ) ) {
				$state = array(
					'phase'  => 0,
					'cursor' => 0,
					'max'    => (int) $wpdb->get_var( 'SELECT MAX( order_id ) FROM ec_order' ),
					'done'   => 0,
				);
			}
			if ( ! empty( $state['done'] ) ) {
				return true;
			}
			$phases = self::phases();
			$count  = count( $phases );
			for ( $i = 0; $i < (int) $max_batches && (int) $state['phase'] < $count; $i++ ) {
				$phase = $phases[ (int) $state['phase'] ];
				$from  = (int) $state['cursor'];
				$to    = min( (int) $state['max'], $from + self::BATCH );
				if ( 'subscriptions' === $phase ) {
					self::sweep_subscriptions();
					$to = (int) $state['max'];
				} elseif ( $from < (int) $state['max'] ) {
					self::run_phase( $phase, $from, $to );
				}
				if ( $to >= (int) $state['max'] ) {
					$state['phase']  = (int) $state['phase'] + 1;
					$state['cursor'] = 0;
				} else {
					$state['cursor'] = $to;
				}
			}
			$state['done'] = ( (int) $state['phase'] >= $count ) ? 1 : 0;
			update_option( self::BACKFILL_OPTION, $state ); // Autoloaded: admin_init reads it on every admin request.
			return (bool) $state['done'];
		}

		/**
		 * Fill in one order's history before its first sync, while the backfill has not reached it.
		 *
		 * @param int $order_id Order.
		 */
		public static function ensure_backfilled( $order_id ) {
			static $done = array();
			$order_id    = (int) $order_id;
			if ( isset( $done[ $order_id ] ) ) {
				return;
			}
			$done[ $order_id ] = true;
			$state             = get_option( self::BACKFILL_OPTION );
			if ( is_array( $state ) && ! empty( $state['done'] ) ) {
				return;
			}
			if ( is_array( $state ) && isset( $state['max'] ) && $order_id > (int) $state['max'] ) {
				return; /* placed after the upgrade: nothing to fill in */
			}
			foreach ( self::phases() as $phase ) {
				if ( 'customers' !== $phase && 'subscriptions' !== $phase ) {
					self::run_phase( $phase, $order_id - 1, $order_id );
				}
			}
		}

		/**
		 * One backfill phase for the orders in ( $from, $to ].
		 *
		 * @param string $phase Phase.
		 * @param int    $from  After this order id.
		 * @param int    $to    Up to this order id.
		 */
		private static function run_phase( $phase, $from, $to ) {
			global $wpdb;
			$from = (int) $from;
			$to   = (int) $to;
			$off  = self::db_offset();
			switch ( $phase ) {
				case 'keys':
					$wpdb->query( $wpdb->prepare( "UPDATE ec_order SET customer_key = MD5( LOWER( TRIM( user_email ) ) ) WHERE order_id > %d AND order_id <= %d AND customer_key = '' AND user_email <> ''", $from, $to ) );
					break;

				case 'paid':
					/* Paid online or marked paid: that moment. Otherwise a paid ( or refunded ) order was paid when it was placed. */
					$wpdb->query(
						$wpdb->prepare(
							'UPDATE ec_order o
							INNER JOIN ( SELECT order_id, MIN( order_log_timestamp ) AS paid_log FROM ec_order_log WHERE order_id > %d AND order_id <= %d AND order_log_key IN ( %s, %s ) GROUP BY order_id ) l ON l.order_id = o.order_id
							SET o.paid_at = DATE_SUB( l.paid_log, INTERVAL %d SECOND )
							WHERE o.order_id > %d AND o.order_id <= %d AND o.paid_at IS NULL',
							$from,
							$to,
							'order-paid-online',
							'order-marked-paid',
							$off,
							$from,
							$to
						)
					);
					$wpdb->query(
						$wpdb->prepare(
							'UPDATE ec_order o INNER JOIN ec_orderstatus s ON s.status_id = o.orderstatus_id
							SET o.paid_at = DATE_SUB( o.order_date, INTERVAL %d SECOND )
							WHERE o.order_id > %d AND o.order_id <= %d AND o.paid_at IS NULL AND ( s.is_approved = 1 OR o.orderstatus_id = 16 OR o.refund_total >= 0.005 )',
							$off,
							$from,
							$to
						)
					);
					break;

				case 'fulfilled':
					if ( self::table_exists( 'ec_order_shipment' ) ) {
						$wpdb->query(
							$wpdb->prepare(
								"UPDATE ec_order o
								INNER JOIN ( SELECT order_id, MIN( shipped_at ) AS first_out FROM ec_order_shipment WHERE order_id > %d AND order_id <= %d AND is_return = 0 AND status IN ( 'shipped', 'delivered' ) AND shipped_at IS NOT NULL GROUP BY order_id ) sh ON sh.order_id = o.order_id
								SET o.fulfilled_at = sh.first_out
								WHERE o.order_id > %d AND o.order_id <= %d AND o.fulfilled_at IS NULL",
								$from,
								$to,
								$from,
								$to
							)
						);
					}
					$list = implode( "','", self::fulfilled_status_ids() );
					// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $list is an implode of (int)-cast status ids.
					$wpdb->query(
						$wpdb->prepare(
							"UPDATE ec_order o
							INNER JOIN (
								SELECT l.order_id, MIN( l.order_log_timestamp ) AS first_fulfilled
								FROM ec_order_log l INNER JOIN ec_order_log_meta m ON m.order_log_id = l.order_log_id AND m.order_log_meta_key = 'orderstatus_id'
								WHERE m.order_id > %d AND m.order_id <= %d AND m.order_log_meta_value IN ( '{$list}' )
								GROUP BY l.order_id
							) f ON f.order_id = o.order_id
							SET o.fulfilled_at = DATE_SUB( f.first_fulfilled, INTERVAL %d SECOND )
							WHERE o.order_id > %d AND o.order_id <= %d AND o.fulfilled_at IS NULL",
							$from,
							$to,
							$off,
							$from,
							$to
						)
					);
					// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					break;

				case 'payments':
					// What each order was paid, on the day it was paid ( amount_paid when recorded, else its total while its status
					// counts as paid, or when nothing is recorded and it has a refund: wp_easycart_order_payments::summary() ), for
					// orders with no payment row yet.
					$wpdb->query(
						$wpdb->prepare(
							"INSERT INTO ec_order_transaction ( order_id, txn_type, amount, created_at, source, gateway, reference )
							SELECT o.order_id, 'payment',
								CASE WHEN o.amount_paid IS NOT NULL AND o.amount_paid >= 0.005 THEN o.amount_paid WHEN ( s.is_approved = 1 OR o.orderstatus_id = 16 OR ( o.amount_paid IS NULL AND o.refund_total >= 0.005 ) ) THEN o.grand_total ELSE 0 END,
								COALESCE( o.paid_at, DATE_SUB( o.order_date, INTERVAL %d SECOND ) ),
								'backfill',
								LEFT( IF( o.order_gateway <> '', o.order_gateway, o.payment_method ), 40 ),
								LEFT( o.gateway_transaction_id, 100 )
							FROM ec_order o
							INNER JOIN ec_orderstatus s ON s.status_id = o.orderstatus_id
							LEFT JOIN ( SELECT DISTINCT order_id FROM ec_order_transaction WHERE txn_type = 'payment' AND order_id > %d AND order_id <= %d ) t ON t.order_id = o.order_id
							WHERE o.order_id > %d AND o.order_id <= %d AND t.order_id IS NULL
							AND ( ( o.amount_paid IS NOT NULL AND o.amount_paid >= 0.005 ) OR ( ( s.is_approved = 1 OR o.orderstatus_id = 16 OR ( o.amount_paid IS NULL AND o.refund_total >= 0.005 ) ) AND o.grand_total >= 0.005 ) )",
							$off,
							$from,
							$to,
							$from,
							$to
						)
					);
					break;

				case 'refunds':
					/* Each refund the order log records, on its day; then whatever refund_total holds beyond them. */
					$wpdb->query(
						$wpdb->prepare(
							"INSERT INTO ec_order_transaction ( order_id, txn_type, amount, created_at, source, reason, order_log_id )
							SELECT l.order_id, 'refund', ROUND( m.order_log_meta_value, 3 ), DATE_SUB( l.order_log_timestamp, INTERVAL %d SECOND ), 'backfill', LEFT( IFNULL( r.order_log_meta_value, '' ), 255 ), l.order_log_id
							FROM ec_order_log l
							INNER JOIN ec_order_log_meta m ON m.order_log_id = l.order_log_id AND m.order_log_meta_key = 'refunded_amount'
							LEFT JOIN ec_order_log_meta r ON r.order_log_id = l.order_log_id AND r.order_log_meta_key = 'refund_reason'
							LEFT JOIN ec_order_transaction t ON t.order_log_id = l.order_log_id AND t.txn_type = 'refund'
							WHERE l.order_id > %d AND l.order_id <= %d AND l.order_log_key IN ( %s, %s ) AND t.transaction_id IS NULL AND ROUND( m.order_log_meta_value, 3 ) > 0",
							$off,
							$from,
							$to,
							self::REFUND_LOGS[0],
							self::REFUND_LOGS[1]
						)
					);
					$wpdb->query(
						$wpdb->prepare(
							"INSERT INTO ec_order_transaction ( order_id, txn_type, amount, created_at, source )
							SELECT o.order_id, 'refund', ROUND( o.refund_total - IFNULL( x.refunded, 0 ), 3 ), DATE_SUB( COALESCE( o.last_updated, o.order_date ), INTERVAL %d SECOND ), 'backfill'
							FROM ec_order o
							LEFT JOIN ( SELECT order_id, SUM( amount ) AS refunded FROM ec_order_transaction WHERE txn_type = 'refund' AND order_id > %d AND order_id <= %d GROUP BY order_id ) x ON x.order_id = o.order_id
							WHERE o.order_id > %d AND o.order_id <= %d AND o.refund_total - IFNULL( x.refunded, 0 ) >= 0.01",
							$off,
							$from,
							$to,
							$from,
							$to
						)
					);
					break;

				case 'customers':
					$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT user_id FROM ec_order WHERE order_id > %d AND order_id <= %d AND user_id > 0', $from, $to ) );
					foreach ( array_chunk( array_map( 'intval', (array) $ids ), 500 ) as $chunk ) {
						self::customer_totals( $chunk );
					}
					break;
			}
		}

		/**
		 * Does a table exist?
		 *
		 * @param string $table Table.
		 * @return bool
		 */
		private static function table_exists( $table ) {
			global $wpdb;
			return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		}
	}

	wp_easycart_order_ledger::init();

endif;
