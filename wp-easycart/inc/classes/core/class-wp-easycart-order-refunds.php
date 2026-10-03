<?php
/**
 * Every payment an order was paid by, and how each one is refunded ( 6.0.2 ).
 *
 * An order can be paid more than once: at checkout, then from its pay link for the balance after items were added, or by a
 * payment staff recorded ( Record payment, Mark as paid, an accounting app ). Each payment is a row of the Reports ledger
 * ( ec_order_transaction ) with its gateway and reference, and a refund row names the payment it gave money back from
 * ( details.payment ). This class reads them as the order's payment register: what each payment paid, what was refunded
 * from it, what is left, and how a refund of it is made ( its route ):
 *
 * - order:  the order's own payment details ( the checkout's gateway, refunded as before by WP EasyCart PRO ).
 * - stripe: a pay link payment by Stripe ( its payment intent and charge ).
 * - square: a pay link payment by Square ( its payment id ).
 * - paypal: a pay link payment by PayPal ( its capture ).
 * - record: money taken outside the store ( Record payment, Mark as paid, an accounting app ), or a gateway that cannot
 *           refund: the refund is recorded on the order and nothing is sent back.
 *
 * Refunds made before 6.0.2 name no payment: they count against the order's first payment ( they went through it ).
 *
 * @since 6.0.2
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_order_refunds' ) ) :

	// phpcs:disable PEAR.NamingConventions.ValidClassName -- WP EasyCart class names are lower case.
	/**
	 * An order's payments and their refunds.
	 *
	 * @since 6.0.2
	 */
	final class wp_easycart_order_refunds {
		// phpcs:enable PEAR.NamingConventions.ValidClassName

		/** How long a refund in progress keeps the Stripe webhook from counting it again ( seconds ). */
		const PENDING_TTL = 300;

		/**
		 * Can the register be read ( the Reports ledger is there )?
		 *
		 * @return bool
		 */
		public static function ready() {
			return class_exists( 'wp_easycart_order_ledger' ) && wp_easycart_order_ledger::ready();
		}

		/**
		 * The gateways a refund can be sent through ( WP EasyCart PRO's refund drawer reads the same filter ).
		 *
		 * @return string[]
		 */
		public static function refundable_gateways() {
			/** This filter is documented in WP EasyCart PRO ( wp_easycart_admin_orders_pro::get_refundable_gateways() ). */
			return (array) apply_filters( 'wp_easycart_refundable_gateways', array( 'affirm', 'amazonpay', 'stripe', 'stripe_connect', 'authorize', 'beanstream', 'braintree', 'nmi', 'intuit', 'square', 'paypal-express', 'paytrace' ) );
		}

		/**
		 * The order row with its status's is_approved.
		 *
		 * @param object|int $order Order row or id.
		 * @return object|null
		 */
		private static function order_row( $order ) {
			global $wpdb;
			if ( is_object( $order ) && isset( $order->order_id ) && property_exists( $order, 'order_gateway' ) && property_exists( $order, 'refund_total' ) ) {
				return $order;
			}
			$order_id = is_object( $order ) ? (int) $order->order_id : (int) $order;
			if ( $order_id <= 0 ) {
				return null;
			}
			return $wpdb->get_row( $wpdb->prepare( 'SELECT ec_order.*, ec_orderstatus.is_approved FROM ec_order LEFT JOIN ec_orderstatus ON ec_orderstatus.status_id = ec_order.orderstatus_id WHERE ec_order.order_id = %d', $order_id ) );
		}

		/**
		 * Was this money taken outside the store ( a payment staff or an app recorded ), rather than by the store's gateway?
		 *
		 * @param string $source Ledger source.
		 * @return bool
		 */
		private static function outside( $source ) {
			return ! in_array( (string) $source, array( '', 'checkout', 'status', 'approved', 'pay_link', 'backfill', 'renewal', 'adjustment' ), true );
		}

		/**
		 * A gateway's name.
		 *
		 * @param string $gateway Gateway key.
		 * @return string
		 */
		public static function gateway_name( $gateway ) {
			$gateway = strtolower( (string) $gateway );
			$names   = array(
				'stripe'         => 'Stripe',
				'stripe_connect' => 'Stripe',
				'square'         => 'Square',
				'paypal-express' => 'PayPal',
				'paypal'         => 'PayPal',
				'affirm'         => 'Affirm',
				'amazonpay'      => 'Amazon Pay',
			);
			if ( isset( $names[ $gateway ] ) ) {
				return $names[ $gateway ];
			}
			if ( class_exists( 'wp_easycart_order_pay' ) && method_exists( 'wp_easycart_order_pay', 'gateway_label' ) && '' !== $gateway ) {
				$label = (string) wp_easycart_order_pay::gateway_label( $gateway );
				if ( '' !== $label ) {
					return $label;
				}
			}
			return ( '' !== $gateway ) ? ucwords( str_replace( array( '_', '-' ), ' ', $gateway ) ) : '';
		}

		/**
		 * The order's payments, oldest first, each with what is left to refund and how.
		 *
		 * @param object|int $order Order row or id.
		 * @return array[] id, date ( UTC ), amount, refunded, refundable, source, gateway, reference, charge, card, balance,
		 *                 original, route ( order | stripe | square | paypal | record ), name, kind, note.
		 */
		public static function payments( $order ) {
			$order = self::order_row( $order );
			if ( ! $order || ! self::ready() ) {
				return array();
			}
			$order_id = (int) $order->order_id;
			wp_easycart_order_ledger::sync( $order_id );

			$entries = array();
			$adjust  = 0.0;
			$tied    = array();
			$untied  = 0.0;
			foreach ( wp_easycart_order_ledger::transactions( $order_id ) as $row ) {
				$details = json_decode( (string) $row->details, true );
				$details = is_array( $details ) ? $details : array();
				$amount  = round( (float) $row->amount, 2 );
				if ( 'payment' === $row->txn_type ) {
					if ( $amount >= 0.005 ) {
						$entries[] = array(
							'id'        => (int) $row->transaction_id,
							'date'      => (string) $row->created_at,
							'amount'    => $amount,
							'source'    => (string) $row->source,
							'label'     => (string) $row->reason,
							'gateway'   => (string) $row->gateway,
							'reference' => (string) $row->reference,
							'charge'    => isset( $details['charge'] ) ? (string) $details['charge'] : '',
							'card'      => isset( $details['card'] ) ? (string) $details['card'] : '',
							'balance'   => ! empty( $details['balance'] ),
						);
					} else {
						$adjust += -$amount;
					}
				} elseif ( 'refund' === $row->txn_type ) {
					$payment_id = isset( $details['payment'] ) ? (int) $details['payment'] : 0;
					if ( $payment_id > 0 ) {
						$tied[ $payment_id ] = ( isset( $tied[ $payment_id ] ) ? $tied[ $payment_id ] : 0.0 ) + $amount;
					} else {
						$untied += $amount;
					}
				}
			}
			if ( ! $entries ) {
				return array();
			}

			/* What the order says it was paid went down ( an adjustment ): it comes off the newest payments. */
			for ( $i = count( $entries ) - 1; $i >= 0 && $adjust >= 0.005; $i-- ) {
				$take                    = min( $entries[ $i ]['amount'], $adjust );
				$entries[ $i ]['amount'] = round( $entries[ $i ]['amount'] - $take, 2 );
				$adjust                 -= $take;
			}

			/* The order's own payment: the first one that is not toward a balance. */
			$original = 0;
			foreach ( $entries as $index => $entry ) {
				if ( ! $entry['balance'] ) {
					$original = $index;
					break;
				}
			}

			$ids = array();
			foreach ( $entries as $index => $entry ) {
				$ids[ $entry['id'] ]           = $index;
				$entries[ $index ]['refunded'] = 0.0;
			}
			foreach ( $tied as $payment_id => $amount ) {
				if ( isset( $ids[ $payment_id ] ) ) {
					$entries[ $ids[ $payment_id ] ]['refunded'] += $amount;
				} else {
					$untied += $amount;
				}
			}
			/* Refunds that name no payment ( before 6.0.2, or made elsewhere ) went through the order's own payment first. */
			$order_of = array_merge( array( $original ), array_diff( array_keys( $entries ), array( $original ) ) );
			foreach ( $order_of as $index ) {
				if ( $untied < 0.005 ) {
					break;
				}
				$room                           = max( 0, $entries[ $index ]['amount'] - $entries[ $index ]['refunded'] );
				$take                           = min( $room, $untied );
				$entries[ $index ]['refunded'] += $take;
				$untied                        -= $take;
			}
			if ( abs( $untied ) >= 0.005 ) {
				$entries[ $original ]['refunded'] = max( 0, $entries[ $original ]['refunded'] + $untied );
			}

			foreach ( $entries as $index => $entry ) {
				$entry['refunded']   = round( $entry['refunded'], 2 );
				$entry['refundable'] = round( max( 0, $entry['amount'] - $entry['refunded'] ), 2 );
				$entry['original']   = ( $index === $original );
				if ( $entry['original'] && '' === $entry['card'] && isset( $order->creditcard_digits ) && '' !== trim( (string) $order->creditcard_digits ) && ! self::outside( $entry['source'] ) ) {
					$entry['card'] = '•••• ' . substr( preg_replace( '/[^0-9]/', '', (string) $order->creditcard_digits ), -4 );
				}
				$entry['route']    = self::route( $entry, $order );
				$entry['name']     = self::payment_name( $entry );
				$entry['kind']     = self::payment_kind( $entry );
				$entry['note']     = self::payment_note( $entry, $order );
				$entries[ $index ] = $entry;
			}
			return array_values( $entries );
		}

		/**
		 * How a refund of a payment is made.
		 *
		 * @param array  $entry Payment.
		 * @param object $order Order row.
		 * @return string order | stripe | square | paypal | record
		 */
		private static function route( $entry, $order ) {
			if ( self::outside( $entry['source'] ) ) {
				return 'record';
			}
			$gateway = strtolower( (string) $entry['gateway'] );
			if ( $entry['original'] ) {
				return in_array( (string) $order->order_gateway, self::refundable_gateways(), true ) ? 'order' : 'record';
			}
			if ( in_array( $gateway, array( 'stripe', 'stripe_connect' ), true ) && ( '' !== $entry['charge'] || 1 === preg_match( '/^(pi|ch|py)_/', (string) $entry['reference'] ) ) ) {
				return 'stripe';
			}
			if ( 'square' === $gateway && '' !== (string) $entry['reference'] ) {
				return 'square';
			}
			if ( in_array( $gateway, array( 'paypal-express', 'paypal' ), true ) && '' !== (string) $entry['reference'] ) {
				return 'paypal';
			}
			return 'record';
		}

		/**
		 * The payment method's name: the gateway, the card, or a recorded payment.
		 *
		 * @param array $entry Payment.
		 * @return string
		 */
		private static function payment_name( $entry ) {
			if ( self::outside( $entry['source'] ) ) {
				return ( '' !== $entry['label'] && strtolower( $entry['label'] ) !== strtolower( $entry['source'] ) ) ? $entry['label'] : __( 'Recorded payment', 'wp-easycart' );
			}
			$gateway = in_array( strtolower( (string) $entry['gateway'] ), array( '', 'manual', 'manual_bill', 'direct_deposit', 'third_party' ), true ) ? '' : self::gateway_name( $entry['gateway'] );
			$name    = ( '' !== $gateway ) ? $gateway : __( 'Manual payment', 'wp-easycart' );
			return ( '' !== $entry['card'] ) ? $name . ' · ' . $entry['card'] : $name;
		}

		/**
		 * Where the payment came from.
		 *
		 * @param array $entry Payment.
		 * @return string
		 */
		private static function payment_kind( $entry ) {
			if ( self::outside( $entry['source'] ) ) {
				return ( 'admin' === $entry['source'] ) ? __( 'Recorded by staff', 'wp-easycart' ) : __( 'Recorded by an app', 'wp-easycart' );
			}
			if ( 'pay_link' === $entry['source'] ) {
				return $entry['balance'] ? __( 'Balance paid by pay link', 'wp-easycart' ) : __( 'Paid by pay link', 'wp-easycart' );
			}
			return __( 'Order payment', 'wp-easycart' );
		}

		/**
		 * One sentence on how a refund of the payment is made.
		 *
		 * @param array  $entry Payment.
		 * @param object $order Order row.
		 * @return string
		 */
		private static function payment_note( $entry, $order ) {
			if ( 'record' === $entry['route'] ) {
				return __( 'A refund of this payment is recorded only. No money is sent back: refund the customer yourself.', 'wp-easycart' );
			}
			$gateway = ( 'order' === $entry['route'] ) ? self::gateway_name( $order->order_gateway ) : self::gateway_name( $entry['gateway'] );
			/* translators: %s: payment gateway name, such as Stripe. */
			return sprintf( __( 'Refunds go back to the customer through %s.', 'wp-easycart' ), $gateway );
		}

		/**
		 * The payments that can still be refunded.
		 *
		 * @param array[] $payments payments().
		 * @return array[]
		 */
		public static function refundable( $payments ) {
			return array_values(
				array_filter(
					(array) $payments,
					function ( $entry ) {
						return $entry['refundable'] >= 0.005;
					}
				)
			);
		}

		/**
		 * Split a refund across payments: the amounts the admin chose ( checked against each payment ), else newest payment
		 * first.
		 *
		 * @param array[]    $payments payments().
		 * @param float      $amount   The refund.
		 * @param array|null $wanted   payment id => amount, or null for the default split.
		 * @return array|WP_Error payment id => amount, in the order the refunds are made.
		 */
		public static function allocate( $payments, $amount, $wanted = null ) {
			$amount = round( (float) $amount, 2 );
			$by_id  = array();
			foreach ( (array) $payments as $entry ) {
				$by_id[ (int) $entry['id'] ] = $entry;
			}
			$out = array();
			if ( is_array( $wanted ) && $wanted ) {
				$sum = 0.0;
				foreach ( $wanted as $payment_id => $part ) {
					$payment_id = (int) $payment_id;
					$part       = round( max( 0, (float) $part ), 2 );
					if ( $part < 0.005 ) {
						continue;
					}
					if ( ! isset( $by_id[ $payment_id ] ) ) {
						return new WP_Error( 'refund_payment', __( 'That payment is not on this order. Reload the order and try again.', 'wp-easycart' ) );
					}
					if ( $part > $by_id[ $payment_id ]['refundable'] + 0.004 ) {
						/* translators: 1: payment name, 2: amount. */
						return new WP_Error( 'refund_payment_amount', sprintf( __( '%1$s has %2$s left to refund.', 'wp-easycart' ), $by_id[ $payment_id ]['name'], self::money( $by_id[ $payment_id ]['refundable'] ) ) );
					}
					$out[ $payment_id ] = $part;
					$sum               += $part;
				}
				if ( abs( round( $sum, 2 ) - $amount ) >= 0.01 ) {
					/* translators: %s: amount. */
					return new WP_Error( 'refund_payment_sum', sprintf( __( 'The amounts from each payment must add up to the refund ( %s ).', 'wp-easycart' ), self::money( $amount ) ) );
				}
				return self::ordered( $out, $by_id );
			}
			$rest = $amount;
			foreach ( array_reverse( array_values( (array) $payments ) ) as $entry ) {
				if ( $rest < 0.005 ) {
					break;
				}
				$take = round( min( $rest, $entry['refundable'] ), 2 );
				if ( $take >= 0.005 ) {
					$out[ (int) $entry['id'] ] = $take;
					$rest                      = round( $rest - $take, 2 );
				}
			}
			if ( $rest >= 0.005 ) {
				return new WP_Error( 'refund_too_much', __( 'The refund is more than is left to refund on this order.', 'wp-easycart' ) );
			}
			return self::ordered( $out, $by_id );
		}

		/**
		 * The parts in the order refunds are made: the gateway ones first ( newest first ), the recorded ones last, so a
		 * declined gateway refund leaves nothing recorded for a payment that was not refunded.
		 *
		 * @param array $parts payment id => amount.
		 * @param array $by_id payment id => payment.
		 * @return array
		 */
		private static function ordered( $parts, $by_id ) {
			$gateway = array();
			$record  = array();
			foreach ( array_reverse( array_keys( $by_id ) ) as $payment_id ) {
				if ( ! isset( $parts[ $payment_id ] ) ) {
					continue;
				}
				if ( 'record' === $by_id[ $payment_id ]['route'] ) {
					$record[ $payment_id ] = $parts[ $payment_id ];
				} else {
					$gateway[ $payment_id ] = $parts[ $payment_id ];
				}
			}
			return $gateway + $record;
		}

		/**
		 * An amount as the store shows money.
		 *
		 * @param float $amount Amount.
		 * @return string
		 */
		private static function money( $amount ) {
			return ( isset( $GLOBALS['currency'] ) && is_object( $GLOBALS['currency'] ) ) ? wp_strip_all_tags( $GLOBALS['currency']->get_currency_display( $amount ) ) : number_format( (float) $amount, 2 );
		}

		/**
		 * Refund part of a payment that has its own gateway reference ( routes stripe, square and paypal ). The order's own
		 * payment ( route order ) is refunded by WP EasyCart PRO through the order's payment details, and route record sends
		 * nothing.
		 *
		 * @param object|int $order   Order row or id.
		 * @param array      $payment A payments() entry.
		 * @param float      $amount  Amount.
		 * @return array|WP_Error reference ( the gateway's refund id when it gives one ), charge.
		 */
		public static function refund( $order, $payment, $amount ) {
			$order  = self::order_row( $order );
			$amount = round( (float) $amount, 2 );
			if ( ! $order || $amount < 0.005 ) {
				return new WP_Error( 'refund_amount', __( 'Nothing to refund.', 'wp-easycart' ) );
			}
			/* translators: %s: payment name. */
			$declined = sprintf( __( 'The refund of %s was declined by the payment gateway.', 'wp-easycart' ), $payment['name'] );
			if ( 'stripe' === $payment['route'] ) {
				$api = self::stripe_api( $payment['gateway'] );
				if ( ! $api ) {
					return new WP_Error( 'refund_gateway', __( 'Stripe is not set up on this store, so this payment cannot be refunded here. Refund it in Stripe.', 'wp-easycart' ) );
				}
				$charge = self::stripe_charge( $api, $payment );
				if ( '' === $charge ) {
					return new WP_Error( 'refund_gateway', __( 'The Stripe charge of this payment could not be found. Refund it in Stripe.', 'wp-easycart' ) );
				}
				self::pending_begin( $charge, $amount );
				if ( ! $api->refund_charge( $charge, $amount ) ) {
					self::pending_end( $charge );
					return new WP_Error( 'refund_declined', $declined );
				}
				$reference = '';
				if ( method_exists( $api, 'get_charge_refunds' ) ) {
					$list      = $api->get_charge_refunds( $charge, 1 );
					$reference = ( $list && isset( $list->data[0]->id ) ) ? (string) $list->data[0]->id : '';
				}
				return array(
					'reference' => $reference,
					'charge'    => $charge,
				);
			}
			if ( 'square' === $payment['route'] ) {
				if ( ! class_exists( 'ec_square' ) ) {
					return new WP_Error( 'refund_gateway', __( 'Square is not set up on this store, so this payment cannot be refunded here. Refund it in Square.', 'wp-easycart' ) );
				}
				$square = new ec_square();
				if ( ! $square->refund_charge_payment( (string) $payment['reference'], $amount ) ) {
					return new WP_Error( 'refund_declined', $declined );
				}
				return array(
					'reference' => '',
					'charge'    => (string) $payment['reference'],
				);
			}
			if ( 'paypal' === $payment['route'] ) {
				if ( ! class_exists( 'ec_paypal' ) ) {
					return new WP_Error( 'refund_gateway', __( 'PayPal is not set up on this store, so this payment cannot be refunded here. Refund it in PayPal.', 'wp-easycart' ) );
				}
				$paypal = new ec_paypal();
				if ( ! $paypal->refund_express_charge( (int) $order->order_id, (string) $payment['reference'], $amount ) ) {
					/* 6.0.2: WP EasyCart Connect refused the refund's signature ( the store's PayPal key ): say that, not "declined". */
					return new WP_Error( 'refund_declined', ( isset( $paypal->refund_error ) && '' !== (string) $paypal->refund_error ) ? (string) $paypal->refund_error : $declined );
				}
				return array(
					'reference' => '',
					'charge'    => (string) $payment['reference'],
				);
			}
			return new WP_Error( 'refund_route', __( 'This payment is refunded another way.', 'wp-easycart' ) );
		}

		/**
		 * The Stripe gateway class for a payment's gateway ( the store's Stripe Connect or API key connection ).
		 *
		 * @param string $gateway stripe | stripe_connect.
		 * @return object|null
		 */
		private static function stripe_api( $gateway ) {
			if ( 'stripe' === $gateway && class_exists( 'ec_stripe' ) ) {
				return new ec_stripe();
			}
			if ( 'stripe_connect' === $gateway && class_exists( 'ec_stripe_connect' ) ) {
				return new ec_stripe_connect();
			}
			return null;
		}

		/**
		 * The Stripe charge of a payment: kept with it, its reference when that is a charge, else the payment intent's latest
		 * charge.
		 *
		 * @param object $api     Stripe gateway class.
		 * @param array  $payment Payment.
		 * @return string
		 */
		private static function stripe_charge( $api, $payment ) {
			if ( '' !== (string) $payment['charge'] ) {
				return (string) $payment['charge'];
			}
			$reference = (string) $payment['reference'];
			if ( 1 === preg_match( '/^(ch|py)_/', $reference ) ) {
				return $reference;
			}
			if ( 0 === strpos( $reference, 'pi_' ) && method_exists( $api, 'get_payment_intent' ) ) {
				$intent = $api->get_payment_intent( $reference );
				if ( $intent && isset( $intent->latest_charge ) ) {
					return is_object( $intent->latest_charge ) ? (string) $intent->latest_charge->id : (string) $intent->latest_charge;
				}
				if ( $intent && isset( $intent->charges->data[0]->id ) ) {
					return (string) $intent->charges->data[0]->id;
				}
			}
			return '';
		}

		/**
		 * Mark a refund in progress on a charge: the Stripe webhook for it counts it as known until the order records it.
		 *
		 * @param string $charge Charge ( or payment intent ) id.
		 * @param float  $amount Amount.
		 */
		public static function pending_begin( $charge, $amount ) {
			if ( '' === (string) $charge ) {
				return;
			}
			$key = 'wpec_refund_pending_' . md5( (string) $charge );
			set_transient( $key, round( (float) get_transient( $key ) + (float) $amount, 2 ), self::PENDING_TTL );
		}

		/**
		 * The order recorded the refund ( or the gateway refused it ).
		 *
		 * @param string $charge Charge ( or payment intent ) id.
		 */
		public static function pending_end( $charge ) {
			if ( '' !== (string) $charge ) {
				delete_transient( 'wpec_refund_pending_' . md5( (string) $charge ) );
			}
		}

		/**
		 * A refund in progress on any of these charges.
		 *
		 * @param string[] $charges Charge / payment intent ids.
		 * @return float
		 */
		public static function pending( $charges ) {
			$total = 0.0;
			foreach ( array_unique( array_filter( array_map( 'strval', (array) $charges ) ) ) as $charge ) {
				$total += (float) get_transient( 'wpec_refund_pending_' . md5( $charge ) );
			}
			return round( $total, 2 );
		}

		/**
		 * The order and payment a Stripe charge belongs to ( charge.refunded ): a pay link payment by its charge or payment
		 * intent, else the order whose own payment it is.
		 *
		 * @param object $charge Stripe charge.
		 * @return array|null order ( row ), payment ( payments() entry ), charges ( ids to check for refunds in progress ).
		 */
		public static function for_stripe_charge( $charge ) {
			global $wpdb;
			if ( ! self::ready() || ! is_object( $charge ) || empty( $charge->id ) ) {
				return null;
			}
			$charge_id    = (string) $charge->id;
			$intent       = isset( $charge->payment_intent ) ? ( is_object( $charge->payment_intent ) ? (string) $charge->payment_intent->id : (string) $charge->payment_intent ) : '';
			$ids          = array_values( array_filter( array( $charge_id, $intent ) ) );
			$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%s' ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $placeholders is one %s per id.
			$row      = $wpdb->get_row( $wpdb->prepare( "SELECT transaction_id, order_id FROM ec_order_transaction WHERE txn_type = 'payment' AND ( reference IN ( $placeholders ) OR details LIKE %s ) ORDER BY transaction_id DESC LIMIT 1", array_merge( $ids, array( '%' . $wpdb->esc_like( '"charge":"' . $charge_id . '"' ) . '%' ) ) ) );
			$order_id = $row ? (int) $row->order_id : (int) $wpdb->get_var( $wpdb->prepare( 'SELECT order_id FROM ec_order WHERE stripe_charge_id = %s ORDER BY order_id DESC LIMIT 1', $charge_id ) );
			if ( $order_id <= 0 ) {
				return null;
			}
			$order    = self::order_row( $order_id );
			$payments = self::payments( $order );
			if ( ! $order || ! $payments ) {
				return null;
			}
			$found = null;
			foreach ( $payments as $entry ) {
				if ( $row && (int) $entry['id'] === (int) $row->transaction_id ) {
					$found = $entry;
					break;
				}
			}
			if ( ! $found ) {
				/* The order's own payment ( its charge is on the order ). */
				foreach ( $payments as $entry ) {
					if ( $entry['original'] ) {
						$found = $entry;
						break;
					}
				}
			}
			if ( ! $found ) {
				return null;
			}
			return array(
				'order'   => $order,
				'payment' => $found,
				'charges' => array_values( array_unique( array_filter( array( $charge_id, $intent, (string) $found['charge'], (string) $found['reference'], $found['original'] ? (string) $order->stripe_charge_id : '' ) ) ) ),
			);
		}
	}

endif;
