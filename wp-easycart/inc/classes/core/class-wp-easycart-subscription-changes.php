<?php
/**
 * Subscription plan changes ( 6.0.3, plan tables Phase 2 ): when a change applies, what it costs, and the Stripe work behind it.
 *
 * The rule ( rule() ): a change happens now or at renewal, or is not allowed. WP EasyCart's own default keeps what Change plan
 * always did ( every change the plan list allows applies now; the product's prorate setting decides whether the difference goes
 * on the next bill; a plan on another billing schedule starts a new period and is charged at once ). Filter
 * wp_easycart_subscription_change_rule lets WP EasyCart PRO answer for plan groups ( by default upgrades now and charged at once,
 * downgrades at renewal ). A change during a free trial applies now, keeps the trial's end and charges nothing ( D9 ), unless the
 * rule says to end the trial.
 *
 * - Now: one Stripe update of the subscription's own item. When Stripe invoices at once ( charge "now", another billing schedule,
 *   or the trial ended ), payment_behavior=pending_if_incomplete, so the change applies only once the payment goes through: a card
 *   that needs 3-D Secure gets the payment's client secret ( My Account confirms it with Stripe.js, then asks sync ), a declined
 *   card leaves the plan as it was.
 * - At renewal: a subscription schedule from the subscription ( phase 1 = what it pays now, to the end of the period; phase 2 = the
 *   new price for one period, then released ). When Stripe moves to phase 2 ( customer.subscription.updated ), reconcile() applies
 *   it here and releases the schedule.
 * - One open change at a time ( scheduled | pending_payment ), kept in ec_subscription_change ( EC_UPGRADE_DB 122 ). A newer change,
 *   a cancellation and the store's own change cancel it first. The customer can cancel it from My Account.
 * - A change that applies fires wp_easycart_subscription_plan_changed ( the Phase 0 hook ) with change_id and when in its context;
 *   wp_easycart_subscription_change_scheduled / _cancelled for the others.
 *
 * Price moves ( Phase 3 ): move_price() tells a subscriber now ( the price change email ) and records a waiting change ( source
 * price_move ) for the first renewal at least N days ( 30 ) away; the daily WP-Cron PRICE_CRON switches the Stripe price with no
 * proration a few days before that renewal ( apply_price_move() ). A customer's quantity change keeps a waiting price move; a
 * change to another product replaces it ( the new product's current price applies ). Customers cannot cancel it; the store can
 * ( stop_price_moves() ). WP EasyCart PRO's plan group editor drives it.
 *
 * Needs the gateway's api() ( WP EasyCart 6.0.3 Stripe Connect, WP EasyCart PRO 6.0.3 Stripe ); without it a change applies now
 * through wp_easycart_change_subscription_plan() as before. My Account AJAX: ec_ajax_subscription_change_{preview,confirm,cancel,sync},
 * plain guard wp_easycart_subscription_change_guard() ( the session nonce the plan panel already prints ).
 *
 * @package wp-easycart
 * @since 6.0.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_subscription_changes' ) ) :

	/**
	 * Plan changes.
	 */
	class wp_easycart_subscription_changes {

		/** WP-Cron event that applies price moves ( daily while any wait ). */
		const PRICE_CRON = 'wp_easycart_subscription_price_moves';

		/** change_source of a price move. */
		const PRICE_SOURCE = 'price_move';

		/** Hooks. */
		public static function init() {
			foreach ( array( 'preview', 'confirm', 'cancel', 'sync' ) as $action ) {
				add_action( 'wp_ajax_ec_ajax_subscription_change_' . $action, array( __CLASS__, 'ajax_' . $action ) );
				add_action( 'wp_ajax_nopriv_ec_ajax_subscription_change_' . $action, array( __CLASS__, 'ajax_' . $action ) );
			}
			add_action( 'wpeasycart_account_subscription_details_before', array( __CLASS__, 'print_open_change' ), 10, 1 );
			add_action( self::PRICE_CRON, array( __CLASS__, 'run_price_moves' ), 10, 0 );
		}

		/**
		 * The change table exists ( EC_UPGRADE_DB 122 ).
		 *
		 * @return bool
		 */
		public static function ready() {
			return (int) get_option( 'ec_option_db_new_version' ) >= 122;
		}

		/**
		 * The Stripe gateway, when it can make the requests changes need.
		 *
		 * @return object|false
		 */
		public static function stripe() {
			$stripe = class_exists( 'wp_easycart_subscription_prices' ) ? wp_easycart_subscription_prices::gateway() : false;
			/**
			 * The gateway plan changes use ( tests hand in a fake ).
			 *
			 * @since 6.0.3
			 * @param object|false $stripe ec_stripe | ec_stripe_connect.
			 */
			$stripe = apply_filters( 'wp_easycart_subscription_change_gateway', $stripe );
			return ( is_object( $stripe ) && method_exists( $stripe, 'api' ) && method_exists( $stripe, 'get_subscription' ) ) ? $stripe : false;
		}

		/**
		 * Changes go through this engine ( the table, and a gateway that can make them ).
		 *
		 * @return bool
		 */
		public static function active() {
			if ( ! self::ready() ) {
				return false;
			}
			if ( has_filter( 'wp_easycart_subscription_change_gateway' ) ) {
				return false !== self::stripe();
			}
			/* Read from the class, without building a gateway ( the storefront script config asks on every page ). */
			$method = (string) get_option( 'ec_option_payment_process_method' );
			$class  = ( 'stripe' === $method ) ? 'ec_stripe' : ( ( 'stripe_connect' === $method ) ? 'ec_stripe_connect' : '' );
			return '' !== $class && class_exists( $class ) && method_exists( $class, 'api' ) && method_exists( $class, 'get_subscription' );
		}

		/* ------------------------------------------------------------------ the rule */

		/**
		 * About how many months a billing period is.
		 *
		 * @param string $period D | W | M | Y.
		 * @param int    $length Every N.
		 * @return float
		 */
		public static function months( $period, $length ) {
			$length = max( 1, (int) $length );
			switch ( strtoupper( substr( (string) $period, 0, 1 ) ) ) {
				case 'D':
					return $length / 30.4375;
				case 'W':
					return $length * 7 / 30.4375;
				case 'Y':
					return $length * 12;
				default:
					return (float) $length;
			}
		}

		/**
		 * What a subscription is on now, for rule().
		 *
		 * @param object      $sub  An ec_subscription row, or an ec_subscription.
		 * @param object|null $live Stripe's subscription, when known ( decides the trial ).
		 * @return array { subscription_id, product_id, period, length, price, quantity, trial }
		 */
		public static function describe_subscription( $sub, $live = null ) {
			$is_object = ( $sub instanceof ec_subscription );
			$get       = function ( $row_name, $object_name, $fallback = '' ) use ( $sub, $is_object ) {
				$name = $is_object ? $object_name : $row_name;
				return isset( $sub->{$name} ) ? $sub->{$name} : $fallback;
			};
			if ( is_object( $live ) && isset( $live->status ) ) {
				$trial = ( 'trialing' === (string) $live->status );
			} elseif ( $is_object && method_exists( $sub, 'in_trial' ) ) {
				/* My Account's own reading: after a change during the trial the product it is on now may have no trial of its own. */
				$trial = $sub->in_trial();
			} else {
				$trial = self::looks_like_trial(
					(string) $get( 'subscription_status', 'status' ),
					(int) $get( 'trial_period_days', 'trial_period_days', 0 ),
					(int) $get( 'number_payments_completed', 'number_payments_completed', 0 ),
					class_exists( 'ec_subscription' ) ? ec_subscription::to_timestamp( $get( 'start_date', 'start_date' ) ) : 0,
					class_exists( 'ec_subscription' ) ? ec_subscription::to_timestamp( $get( 'next_payment_date', 'next_payment' ) ) : 0
				);
			}
			return array(
				'subscription_id' => (int) $get( 'subscription_id', 'subscription_id', 0 ),
				'product_id'      => (int) $get( 'product_id', 'product_id', 0 ),
				'period'          => strtoupper( substr( (string) $get( 'payment_period', 'bill_period', 'M' ), 0, 1 ) ),
				'length'          => max( 1, (int) $get( 'payment_length', 'bill_length', 1 ) ),
				'price'           => (float) $get( 'price', 'amount', 0 ),
				'quantity'        => max( 1, (int) $get( 'quantity', 'quantity', 1 ) ),
				'trial'           => $trial,
			);
		}

		/**
		 * A subscription in its free trial, read without Stripe ( the store keeps a trial as Active ): the product has a trial,
		 * nothing but the trial has been invoiced, and the first period is as long as the trial ( the reminders' rule ).
		 *
		 * @param string $status Stored status.
		 * @param int    $days   The product's trial days.
		 * @param int    $paid   Payments completed.
		 * @param int    $start  Start.
		 * @param int    $next   Next payment.
		 * @return bool
		 */
		public static function looks_like_trial( $status, $days, $paid, $start, $next ) {
			if ( 'trialing' === strtolower( (string) $status ) ) {
				return true;
			}
			if ( $days <= 0 || $paid > 1 || $start <= 0 || $next <= time() ) {
				return false;
			}
			return abs( ( $next - $start ) - $days * DAY_IN_SECONDS ) <= 2 * DAY_IN_SECONDS;
		}

		/**
		 * What a product sells, for rule().
		 *
		 * @param object $product ec_product row ( or an upgrades row ).
		 * @return array { product_id, period, length, price, prorate, plan_id }
		 */
		public static function describe_product( $product ) {
			$get = function ( $name, $fallback = '' ) use ( $product ) {
				return isset( $product->{$name} ) ? $product->{$name} : $fallback;
			};
			return array(
				'product_id' => (int) $get( 'product_id', 0 ),
				'period'     => strtoupper( substr( (string) $get( 'subscription_bill_period', 'M' ), 0, 1 ) ),
				'length'     => max( 1, (int) $get( 'subscription_bill_length', 1 ) ),
				'price'      => (float) $get( 'price', 0 ),
				'prorate'    => ! empty( $product->subscription_prorate ),
				'plan_id'    => (int) $get( 'subscription_plan_id', 0 ),
			);
		}

		/**
		 * When a change applies and what it charges.
		 *
		 * @param array $from    describe_subscription().
		 * @param array $to      describe_product().
		 * @param bool  $allowed The plan list allows it ( its price order and "Customers can also move to a lower-priced product" ).
		 * @return array {
		 *     @type string $when      now | renewal | none.
		 *     @type string $charge    now ( the difference at once ) | next ( on the next bill ) | none.
		 *     @type string $trial     keep | end ( a change during a free trial ).
		 *     @type string $interval  same | longer | shorter.
		 *     @type string $direction up | down | same ( by the price per month; WP EasyCart PRO uses the tier order for groups ).
		 * }
		 */
		public static function rule( $from, $to, $allowed = true ) {
			$from_months = self::months( $from['period'], $from['length'] );
			$to_months   = self::months( $to['period'], $to['length'] );
			$interval    = ( abs( $from_months - $to_months ) < 0.01 ) ? 'same' : ( ( $to_months > $from_months ) ? 'longer' : 'shorter' );
			$from_rate   = $from['price'] / max( 0.01, $from_months );
			$to_rate     = $to['price'] / max( 0.01, $to_months );
			if ( (int) $from['product_id'] === (int) $to['product_id'] ) {
				$direction = 'same';
			} else {
				$direction = ( $to_rate >= $from_rate ) ? 'up' : 'down';
			}
			$rule = array(
				'when'      => $allowed ? 'now' : 'none',
				'charge'    => ( 'same' !== $interval ) ? 'now' : ( $to['prorate'] ? 'next' : 'none' ),
				'trial'     => 'keep',
				'interval'  => $interval,
				'direction' => $direction,
			);
			/**
			 * When a plan change applies and what it charges ( WP EasyCart PRO answers for plan groups ).
			 *
			 * @since 6.0.3
			 * @param array $rule    when ( now | renewal | none ), charge ( now | next | none ), trial ( keep | end ), interval, direction.
			 * @param array $from    describe_subscription().
			 * @param array $to      describe_product().
			 * @param bool  $allowed The plan list's own answer.
			 */
			$rule = array_merge(
				array(
					'when'      => 'none',
					'charge'    => 'none',
					'trial'     => 'keep',
					'interval'  => $interval,
					'direction' => $direction,
				),
				(array) apply_filters( 'wp_easycart_subscription_change_rule', $rule, $from, $to, $allowed )
			);
			if ( ! in_array( $rule['when'], array( 'now', 'renewal', 'none' ), true ) ) {
				$rule['when'] = 'none';
			}
			if ( ! in_array( $rule['charge'], array( 'now', 'next', 'none' ), true ) ) {
				$rule['charge'] = 'none';
			}
			if ( ! empty( $from['trial'] ) && 'none' !== $rule['when'] && 'end' !== $rule['trial'] ) {
				/* D9: during a free trial a change applies now, keeps the trial's end and charges nothing until then. */
				$rule['when']   = 'now';
				$rule['charge'] = 'none';
				$rule['trial']  = 'keep';
			}
			return $rule;
		}

		/**
		 * Change plan choices with when each would apply ( ec_subscription::get_plan_choices() ).
		 *
		 * @param ec_subscription $subscription The subscription.
		 * @param array           $choices      { product_id, current, allowed, row, … }.
		 * @return array The choices the rule allows, each with when ( current | now | renewal ) and note ( plain text ).
		 */
		public static function annotate( $subscription, $choices ) {
			$from   = self::describe_subscription( $subscription );
			$renews = method_exists( $subscription, 'get_next_payment_timestamp' ) ? (int) $subscription->get_next_payment_timestamp() : 0;
			$out    = array();
			foreach ( $choices as $choice ) {
				if ( ! empty( $choice['current'] ) ) {
					$choice['when'] = 'current';
					$choice['note'] = '';
					$out[]          = $choice;
					continue;
				}
				$rule = self::rule( $from, self::describe_product( $choice['row'] ), ! empty( $choice['allowed'] ) );
				if ( 'none' === $rule['when'] ) {
					continue;
				}
				$choice['when'] = $rule['when'];
				if ( 'renewal' === $rule['when'] ) {
					$choice['note'] = ( $renews > 0 ) ? self::text( 'subscription_change_note_renewal', array( 'date' => self::date( $renews ) ) ) : self::text( 'subscription_change_note_later' );
				} elseif ( ! empty( $from['trial'] ) && 'end' === $rule['trial'] ) {
					$choice['note'] = self::text( 'subscription_change_note_trial_end' );
				} elseif ( ! empty( $from['trial'] ) && $renews > 0 ) {
					/* D9: the plan changes now and the trial keeps its end ( the next billing date ), so every choice names that date. */
					$choice['note'] = self::text( 'subscription_change_note_trial', array( 'date' => self::date( $renews ) ) );
				} else {
					$choice['note'] = self::text( 'subscription_change_note_now' );
				}
				$out[] = $choice;
			}
			return $out;
		}

		/* ------------------------------------------------------------------ the open change */

		/**
		 * The change still waiting on a subscription.
		 *
		 * @param int $subscription_id Subscription.
		 * @return object|null ec_subscription_change row.
		 */
		public static function open( $subscription_id ) {
			global $wpdb;
			if ( ! self::ready() ) {
				return null;
			}
			return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM ec_subscription_change WHERE subscription_id = %d AND change_status IN ( 'scheduled', 'pending_payment' ) ORDER BY change_id DESC LIMIT 1", (int) $subscription_id ) );
		}

		/**
		 * Record a change.
		 *
		 * @param array $fields Columns.
		 * @return int change_id.
		 */
		private static function record( $fields ) {
			global $wpdb;
			$now = current_time( 'mysql', true );
			$wpdb->insert(
				'ec_subscription_change',
				array_merge(
					array(
						'created_at' => $now,
						'updated_at' => $now,
					),
					$fields
				)
			);
			return (int) $wpdb->insert_id;
		}

		/**
		 * Move a change to another state, only from the one it was read in ( two requests never both act on it ).
		 *
		 * @param int    $change_id Change.
		 * @param string $from      State it was in.
		 * @param string $to        New state.
		 * @param int    $at        effective_at to write ( 0 keeps it ).
		 * @return bool
		 */
		private static function set_status( $change_id, $from, $to, $at = 0 ) {
			global $wpdb;
			$now = current_time( 'mysql', true );
			if ( $at > 0 ) {
				$done = $wpdb->query( $wpdb->prepare( 'UPDATE ec_subscription_change SET change_status = %s, effective_at = %d, updated_at = %s WHERE change_id = %d AND change_status = %s', $to, (int) $at, $now, (int) $change_id, $from ) );
			} else {
				$done = $wpdb->query( $wpdb->prepare( 'UPDATE ec_subscription_change SET change_status = %s, updated_at = %s WHERE change_id = %d AND change_status = %s', $to, $now, (int) $change_id, $from ) );
			}
			return 1 === (int) $done;
		}

		/* ------------------------------------------------------------------ preview and request */

		/**
		 * Everything a preview or a request needs.
		 *
		 * @param int $subscription_id Subscription.
		 * @param int $product_id      The plan asked for.
		 * @param int $quantity        Quantity ( 0 = keep ).
		 * @return array|WP_Error
		 */
		private static function context( $subscription_id, $product_id, $quantity ) {
			global $wpdb;
			if ( ! class_exists( 'ec_db' ) || ! class_exists( 'wp_easycart_subscription_prices' ) ) {
				return new WP_Error( 'core', 'WP EasyCart is not loaded.' );
			}
			$ec_db = new ec_db(); /* sets ec_db's database handle for its static calls */
			$row   = ec_db::get_subscription_row( (int) $subscription_id );
			if ( ! $row || 'stripe' !== (string) $row->subscription_type || '' === (string) $row->stripe_subscription_id ) {
				return new WP_Error( 'subscription', 'Only Stripe subscriptions can change product.' );
			}
			$product = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_product WHERE product_id = %d', (int) $product_id ) );
			if ( ! $product || ! (int) $product->is_subscription_item ) {
				return new WP_Error( 'product', 'The product is not a subscription.' );
			}
			$stripe = self::stripe();
			if ( ! $stripe ) {
				return new WP_Error( 'gateway', 'The store does not take payments through Stripe.' );
			}
			$customer = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT stripe_customer_id FROM ec_user WHERE user_id = %d', (int) $row->user_id ) );
			$live     = $stripe->get_subscription( $customer, (string) $row->stripe_subscription_id );
			if ( ! is_object( $live ) || empty( $live->id ) ) {
				return new WP_Error( 'stripe', 'Stripe did not find the subscription.' );
			}
			$quantity = ( (int) $quantity > 0 ) ? (int) $quantity : max( 1, (int) $row->quantity );
			$from     = self::describe_subscription( $row, $live );
			$to       = self::describe_product( $product );
			return array(
				'row'      => $row,
				'product'  => $product,
				'stripe'   => $stripe,
				'customer' => $customer,
				'live'     => $live,
				'item'     => wp_easycart_subscription_prices::main_item_object( $live, (int) $row->product_id ),
				'quantity' => $quantity,
				'from'     => $from,
				'to'       => $to,
				'rule'     => self::rule( $from, $to, true ),
				'renews'   => self::period_end( $live ),
			);
		}

		/**
		 * The Stripe price the plan asked for bills: the subscription's own price when only the quantity changes ( the subscriber
		 * keeps the price they pay ), else the product's, checked before use ( wp_easycart_subscription_prices::ensure() ).
		 *
		 * @param array $ctx context().
		 * @return string
		 */
		private static function target_price( $ctx ) {
			if ( (int) $ctx['product']->product_id === (int) $ctx['row']->product_id && $ctx['item'] && isset( $ctx['item']->price->id ) ) {
				return (string) $ctx['item']->price->id;
			}
			return (string) wp_easycart_subscription_prices::ensure( (int) $ctx['product']->product_id, $ctx['stripe'] );
		}

		/**
		 * When the subscription's current period ( or trial ) ends.
		 *
		 * @param object $live Stripe subscription.
		 * @return int
		 */
		public static function period_end( $live ) {
			if ( ! empty( $live->trial_end ) && isset( $live->status ) && 'trialing' === $live->status ) {
				return (int) $live->trial_end;
			}
			if ( ! empty( $live->current_period_end ) ) {
				return (int) $live->current_period_end;
			}
			if ( isset( $live->items->data ) && is_array( $live->items->data ) ) {
				foreach ( $live->items->data as $item ) {
					if ( ! empty( $item->current_period_end ) ) {
						return (int) $item->current_period_end;
					}
				}
			}
			return 0;
		}

		/**
		 * How Stripe makes a change that applies now.
		 *
		 * @param array $rule rule().
		 * @param array $from describe_subscription().
		 * @return array { proration: none | create_prorations | always_invoice, invoices_now: bool, end_trial: bool }
		 */
		private static function now_terms( $rule, $from ) {
			$end_trial = ! empty( $from['trial'] ) && 'end' === $rule['trial'];
			if ( ! empty( $from['trial'] ) && ! $end_trial ) {
				return array(
					'proration'    => 'none',
					'invoices_now' => false,
					'end_trial'    => false,
				);
			}
			if ( $end_trial ) {
				$proration = 'none';
			} elseif ( 'now' === $rule['charge'] ) {
				$proration = 'always_invoice';
			} else {
				$proration = ( 'next' === $rule['charge'] ) ? 'create_prorations' : 'none';
			}
			if ( 'same' !== $rule['interval'] && 'none' === $proration && 'now' === $rule['charge'] ) {
				$proration = 'always_invoice';
			}
			return array(
				'proration'    => $proration,
				/* Stripe invoices at once when the trial ends, when asked to, and when the billing interval changes. */
				'invoices_now' => $end_trial || 'always_invoice' === $proration || 'same' !== $rule['interval'],
				'end_trial'    => $end_trial,
			);
		}

		/**
		 * What a change will do, for the customer to confirm.
		 *
		 * @param int $subscription_id Subscription.
		 * @param int $product_id      The plan asked for.
		 * @param int $quantity        Quantity ( 0 = keep ).
		 * @return array|WP_Error { when, charge, interval, trial, amount_now ( float|null: due today ), amount_next ( float|null: added to
		 *                        the next bill, negative = a credit ), at ( the renewal, trial end or new period's end ), new_total,
		 *                        currency, message }
		 */
		public static function preview( $subscription_id, $product_id, $quantity = 0 ) {
			$ctx = self::context( $subscription_id, $product_id, $quantity );
			if ( is_wp_error( $ctx ) ) {
				return $ctx;
			}
			$rule = $ctx['rule'];
			$out  = array(
				'when'        => $rule['when'],
				'charge'      => $rule['charge'],
				'interval'    => $rule['interval'],
				'trial'       => ! empty( $ctx['from']['trial'] ),
				'amount_now'  => null,
				'amount_next' => null,
				'at'          => (int) $ctx['renews'],
				'new_total'   => round( (float) $ctx['product']->price * $ctx['quantity'], 2 ),
				'period'      => strtoupper( substr( (string) $ctx['product']->subscription_bill_period, 0, 1 ) ),
				'length'      => max( 1, (int) $ctx['product']->subscription_bill_length ),
			);
			if ( 'now' === $rule['when'] ) {
				$terms = self::now_terms( $rule, $ctx['from'] );
				if ( 'none' !== $terms['proration'] || $terms['invoices_now'] ) {
					$price = self::target_price( $ctx );
					if ( '' === $price ) {
						return new WP_Error( 'price', 'Stripe could not make a price for the product.' );
					}
					$item = array(
						'price'    => $price,
						'quantity' => $ctx['quantity'],
					);
					if ( $ctx['item'] && isset( $ctx['item']->id ) ) {
						$item = array_merge( array( 'id' => (string) $ctx['item']->id ), $item );
					}
					$query = array(
						'customer'                        => $ctx['customer'],
						'subscription'                    => (string) $ctx['live']->id,
						'subscription_items'              => array( $item ),
						'subscription_proration_behavior' => ( 'none' === $terms['proration'] ) ? 'none' : 'create_prorations',
						'subscription_proration_date'     => time(),
					);
					if ( $terms['end_trial'] ) {
						$query['subscription_trial_end'] = 'now';
					}
					$invoice = $ctx['stripe']->api( 'GET', 'invoices/upcoming', $query );
					if ( is_wp_error( $invoice ) ) {
						return $invoice;
					}
					$divisor = self::divisor( isset( $invoice->currency ) ? $invoice->currency : '' );
					if ( 'same' !== $rule['interval'] || $terms['end_trial'] ) {
						/* A new billing period starts now: the preview is the invoice Stripe makes today. */
						$out['amount_now'] = max( 0, round( (float) $invoice->amount_due / $divisor, 2 ) );
						$lines_end         = self::lines_period_end( $invoice );
						$out['at']         = $lines_end ? $lines_end : $out['at'];
					} elseif ( 'always_invoice' === $terms['proration'] ) {
						$out['amount_now'] = max( 0, round( self::proration_total( $invoice ) / $divisor, 2 ) );
					} else {
						$out['amount_next'] = round( self::proration_total( $invoice ) / $divisor, 2 );
					}
				}
			}
			$out['message'] = self::message( $out );
			return $out;
		}

		/**
		 * The end of the new period on a preview ( its first line that is not a proration ).
		 *
		 * @param object $invoice Stripe invoice.
		 * @return int
		 */
		private static function lines_period_end( $invoice ) {
			if ( isset( $invoice->lines->data ) && is_array( $invoice->lines->data ) ) {
				foreach ( $invoice->lines->data as $line ) {
					if ( empty( $line->proration ) && isset( $line->period->end ) ) {
						return (int) $line->period->end;
					}
				}
			}
			return 0;
		}

		/**
		 * The proration lines of a preview, with their tax ( smallest currency unit ).
		 *
		 * @param object $invoice Stripe invoice.
		 * @return float
		 */
		private static function proration_total( $invoice ) {
			$total = 0;
			if ( isset( $invoice->lines->data ) && is_array( $invoice->lines->data ) ) {
				foreach ( $invoice->lines->data as $line ) {
					if ( empty( $line->proration ) ) {
						continue;
					}
					$total += isset( $line->amount ) ? (float) $line->amount : 0;
					if ( isset( $line->tax_amounts ) && is_array( $line->tax_amounts ) ) {
						foreach ( $line->tax_amounts as $tax ) {
							if ( empty( $tax->inclusive ) && isset( $tax->amount ) ) {
								$total += (float) $tax->amount;
							}
						}
					}
				}
			}
			return $total;
		}

		/**
		 * 1 for zero-decimal currencies, else 100.
		 *
		 * @param string $currency ISO code.
		 * @return int
		 */
		private static function divisor( $currency ) {
			return in_array( strtolower( (string) $currency ), array( 'bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf' ), true ) ? 1 : 100;
		}

		/**
		 * Make a change: now ( paid first when Stripe invoices at once ) or at renewal ( a schedule ).
		 *
		 * @param int   $subscription_id Subscription.
		 * @param int   $product_id      The plan asked for.
		 * @param int   $quantity        Quantity ( 0 = keep ).
		 * @param array $args            source ( customer | admin | … ), user ( ec_user: refreshes the name kept on the subscription ),
		 *                               when ( the store's own change: now | renewal ).
		 * @return array { state: applied | scheduled | action | declined | refused | error, message, client_secret, change_id, at }
		 */
		public static function request( $subscription_id, $product_id, $quantity = 0, $args = array() ) {
			$args = array_merge(
				array(
					'source' => 'customer',
					'user'   => null,
					'when'   => '',
				),
				(array) $args
			);
			$out  = array(
				'state'         => 'error',
				'message'       => '',
				'client_secret' => '',
				'change_id'     => 0,
				'at'            => 0,
			);
			if ( ! self::active() ) {
				/* No change table yet, or an older gateway ( WP EasyCart PRO before 6.0.3 ): the change applies now, as before. */
				$changed      = function_exists( 'wp_easycart_change_subscription_plan' ) ? wp_easycart_change_subscription_plan( (int) $subscription_id, (int) $product_id, (int) $quantity, $args ) : new WP_Error( 'core', 'missing' );
				$out['state'] = is_wp_error( $changed ) ? 'error' : 'applied';
				return $out;
			}
			$ctx = self::context( $subscription_id, $product_id, $quantity );
			if ( is_wp_error( $ctx ) ) {
				$out['message'] = $ctx->get_error_message();
				return $out;
			}
			$rule = $ctx['rule'];
			if ( in_array( $args['when'], array( 'now', 'renewal' ), true ) ) {
				$rule['when'] = $args['when'];
			}
			if ( 'none' === $rule['when'] ) {
				$out['state'] = 'refused';
				return $out;
			}
			/* One open change at a time: a new one replaces it. A schedule left from an applied change is released too. */
			$released = self::release_for( $ctx, 'replaced' );
			if ( is_wp_error( $released ) ) {
				$out['message'] = $released->get_error_message();
				return $out;
			}
			if ( $released ) {
				$ctx['live']->schedule = null;
			}
			$price = self::target_price( $ctx );
			if ( '' === $price ) {
				$out['message'] = 'Stripe could not make a price for the product.';
				return $out;
			}
			if ( 'renewal' === $rule['when'] ) {
				return self::schedule_change( $ctx, $price, $args );
			}
			return self::change_now( $ctx, $rule, $price, $args );
		}

		/**
		 * The columns a change row starts with.
		 *
		 * @param array  $ctx   context().
		 * @param string $price Stripe price.
		 * @param array  $args  request() args.
		 * @return array
		 */
		private static function base_fields( $ctx, $price, $args ) {
			return array(
				'subscription_id' => (int) $ctx['row']->subscription_id,
				'from_product_id' => (int) $ctx['row']->product_id,
				'to_product_id'   => (int) $ctx['product']->product_id,
				'from_quantity'   => max( 1, (int) $ctx['row']->quantity ),
				'to_quantity'     => (int) $ctx['quantity'],
				'stripe_price_id' => (string) $price,
				'change_source'   => substr( sanitize_key( (string) $args['source'] ), 0, 20 ),
			);
		}

		/**
		 * Apply now in Stripe; here once Stripe says it applied.
		 *
		 * @param array  $ctx   context().
		 * @param array  $rule  rule().
		 * @param string $price Stripe price.
		 * @param array  $args  request() args.
		 * @return array request()'s answer.
		 */
		private static function change_now( $ctx, $rule, $price, $args ) {
			$terms = self::now_terms( $rule, $ctx['from'] );
			$item  = array(
				'price'    => $price,
				'quantity' => (int) $ctx['quantity'],
			);
			if ( $ctx['item'] && isset( $ctx['item']->id ) ) {
				$item = array_merge( array( 'id' => (string) $ctx['item']->id ), $item );
			}
			$data = array(
				'items'              => array( $item ),
				'proration_behavior' => $terms['proration'],
				'expand'             => array( 'latest_invoice.payment_intent' ),
			);
			if ( $terms['invoices_now'] ) {
				$data['payment_behavior'] = 'pending_if_incomplete';
			}
			if ( $terms['end_trial'] ) {
				$data['trial_end'] = 'now';
			}
			$out     = array(
				'state'         => 'error',
				'message'       => '',
				'client_secret' => '',
				'change_id'     => 0,
				'at'            => time(),
			);
			$updated = $ctx['stripe']->api( 'POST', 'subscriptions/' . rawurlencode( (string) $ctx['live']->id ), $data );
			if ( is_wp_error( $updated ) ) {
				$data_error     = $updated->get_error_data();
				$is_card        = ( is_object( $data_error ) && isset( $data_error->type ) && 'card_error' === $data_error->type );
				$out['state']   = $is_card ? 'declined' : 'error';
				$out['message'] = $updated->get_error_message();
				return $out;
			}
			$invoice = ( isset( $updated->latest_invoice ) && is_object( $updated->latest_invoice ) ) ? $updated->latest_invoice : null;
			$fields  = array_merge(
				self::base_fields( $ctx, $price, $args ),
				array(
					'change_when'       => 'now',
					'effective_at'      => time(),
					'stripe_invoice_id' => ( $terms['invoices_now'] && $invoice && isset( $invoice->id ) ) ? (string) $invoice->id : '',
					'amount'            => ( $terms['invoices_now'] && $invoice && isset( $invoice->amount_due ) ) ? round( (float) $invoice->amount_due / self::divisor( isset( $invoice->currency ) ? $invoice->currency : '' ), 3 ) : 0,
				)
			);
			if ( ! empty( $updated->pending_update ) ) {
				/* Stripe keeps the change until the payment goes through ( 3-D Secure ), or drops it ( a declined card ). */
				$out['change_id'] = self::record( array_merge( $fields, array( 'change_status' => 'pending_payment' ) ) );
				$intent           = ( $invoice && isset( $invoice->payment_intent ) && is_object( $invoice->payment_intent ) ) ? $invoice->payment_intent : null;
				if ( $intent && in_array( (string) $intent->status, array( 'requires_action', 'requires_confirmation' ), true ) && ! empty( $intent->client_secret ) ) {
					$out['state']         = 'action';
					$out['client_secret'] = (string) $intent->client_secret;
					return $out;
				}
				$out['state']   = 'declined';
				$out['message'] = ( $intent && isset( $intent->last_payment_error->message ) ) ? (string) $intent->last_payment_error->message : '';
				self::void_pending( $ctx['stripe'], $out['change_id'], 'failed' );
				return $out;
			}
			$out['change_id'] = self::record( array_merge( $fields, array( 'change_status' => 'applied' ) ) );
			self::apply_local( $ctx['row'], $ctx['product'], (int) $ctx['quantity'], $args, $price, $out['change_id'], 'now' );
			$out['state'] = 'applied';
			return $out;
		}

		/**
		 * Change at the end of the period: a subscription schedule ( the current phase to its end, then the new price for one period,
		 * then released ).
		 *
		 * @param array  $ctx   context().
		 * @param string $price Stripe price.
		 * @param array  $args  request() args.
		 * @return array request()'s answer.
		 */
		private static function schedule_change( $ctx, $price, $args ) {
			$out = array(
				'state'         => 'error',
				'message'       => '',
				'client_secret' => '',
				'change_id'     => 0,
				'at'            => 0,
			);
			if ( ! empty( $ctx['live']->schedule ) ) {
				$out['message'] = 'This subscription already has a change waiting in Stripe.';
				return $out;
			}
			$stripe   = $ctx['stripe'];
			$schedule = $stripe->api( 'POST', 'subscription_schedules', array( 'from_subscription' => (string) $ctx['live']->id ) );
			if ( is_wp_error( $schedule ) || empty( $schedule->id ) || empty( $schedule->phases[0] ) ) {
				$out['message'] = is_wp_error( $schedule ) ? $schedule->get_error_message() : 'Stripe did not schedule the change.';
				return $out;
			}
			$phase   = $schedule->phases[0];
			$current = ( $ctx['item'] && isset( $ctx['item']->price->id ) ) ? (string) $ctx['item']->price->id : '';
			$items0  = array();
			$items1  = array();
			$swapped = false;
			foreach ( (array) $phase->items as $phase_item ) {
				$item_price = ( isset( $phase_item->price ) && is_object( $phase_item->price ) ) ? (string) $phase_item->price->id : (string) $phase_item->price;
				$entry      = array(
					'price'    => $item_price,
					'quantity' => isset( $phase_item->quantity ) ? (int) $phase_item->quantity : 1,
				);
				if ( ! empty( $phase_item->tax_rates ) ) {
					$entry['tax_rates'] = self::ids( $phase_item->tax_rates );
				}
				$items0[] = $entry;
				if ( ! $swapped && ( '' === $current || $item_price === $current ) ) {
					$entry['price']    = $price;
					$entry['quantity'] = (int) $ctx['quantity'];
					$swapped           = true;
				}
				$items1[] = $entry;
			}
			$phase0 = array(
				'items'              => $items0,
				'start_date'         => (int) $phase->start_date,
				'end_date'           => (int) $phase->end_date,
				'proration_behavior' => 'none',
			);
			$phase1 = array(
				'items'              => $items1,
				'iterations'         => 1,
				'proration_behavior' => 'none',
			);
			if ( ! empty( $phase->trial_end ) ) {
				$phase0['trial_end'] = (int) $phase->trial_end;
			}
			foreach ( array( 'default_tax_rates', 'coupon', 'collection_method' ) as $key ) {
				if ( ! empty( $phase->{$key} ) ) {
					$value          = ( 'default_tax_rates' === $key ) ? self::ids( $phase->{$key} ) : ( is_object( $phase->{$key} ) ? (string) $phase->{$key}->id : $phase->{$key} );
					$phase0[ $key ] = $value;
					$phase1[ $key ] = $value;
				}
			}
			$update = array(
				'end_behavior' => 'release',
				'phases'       => array( $phase0, $phase1 ),
			);
			/* Phases fall back to the schedule's payment method, which follows the subscriber's card updates ( payment_method_changed() ). */
			$payment_method = self::payment_method_id( $ctx['live'], $phase );
			if ( '' !== $payment_method ) {
				$update['default_settings'] = array( 'default_payment_method' => $payment_method );
			}
			$updated = $stripe->api( 'POST', 'subscription_schedules/' . rawurlencode( (string) $schedule->id ), $update );
			if ( is_wp_error( $updated ) ) {
				$stripe->api( 'POST', 'subscription_schedules/' . rawurlencode( (string) $schedule->id ) . '/release', array() );
				$out['message'] = $updated->get_error_message();
				return $out;
			}
			$out['change_id'] = self::record(
				array_merge(
					self::base_fields( $ctx, $price, $args ),
					array(
						'change_when'        => 'renewal',
						'change_status'      => 'scheduled',
						'effective_at'       => (int) $phase->end_date,
						'stripe_schedule_id' => (string) $schedule->id,
					)
				)
			);
			$out['state']     = 'scheduled';
			$out['at']        = (int) $phase->end_date;
			/**
			 * A plan change was scheduled for the subscription's renewal.
			 *
			 * @since 6.0.3
			 * @param int $subscription_id Subscription.
			 * @param int $product_id      The plan it moves to.
			 * @param int $at              When.
			 * @param int $change_id       ec_subscription_change row.
			 */
			do_action( 'wp_easycart_subscription_change_scheduled', (int) $ctx['row']->subscription_id, (int) $ctx['product']->product_id, (int) $phase->end_date, $out['change_id'] );
			return $out;
		}

		/**
		 * The payment method a schedule bills with: the phase's, else the subscription's.
		 *
		 * @param object $live  Stripe subscription.
		 * @param object $phase The schedule's first phase.
		 * @return string
		 */
		private static function payment_method_id( $live, $phase ) {
			foreach ( array( $phase, $live ) as $source ) {
				if ( is_object( $source ) && ! empty( $source->default_payment_method ) ) {
					return is_object( $source->default_payment_method ) ? (string) $source->default_payment_method->id : (string) $source->default_payment_method;
				}
			}
			return '';
		}

		/**
		 * Ids from a list of Stripe objects or ids.
		 *
		 * @param array $entries Objects or ids.
		 * @return string[]
		 */
		private static function ids( $entries ) {
			$out = array();
			foreach ( (array) $entries as $entry ) {
				$out[] = is_object( $entry ) ? (string) $entry->id : (string) $entry;
			}
			return $out;
		}

		/**
		 * Before another change: an open change is cancelled, and a schedule of ours on the subscription released ( one the store
		 * made in Stripe itself is left alone: WP_Error ).
		 *
		 * @param array  $ctx    context().
		 * @param string $reason replaced | store.
		 * @return bool|WP_Error True when a schedule was released.
		 */
		private static function release_for( $ctx, $reason ) {
			global $wpdb;
			$open = self::open( (int) $ctx['row']->subscription_id );
			if ( $open && self::PRICE_SOURCE === (string) $open->change_source ) {
				/* A waiting price move: a quantity change keeps it ( it applies at its renewal with the new quantity ); a change to
				 * another product replaces it, since that product's current price applies. */
				if ( (int) $ctx['product']->product_id !== (int) $ctx['row']->product_id && self::set_status( (int) $open->change_id, 'scheduled', 'cancelled' ) ) {
					do_action( 'wp_easycart_subscription_change_cancelled', (int) $ctx['row']->subscription_id, (int) $open->change_id, $reason );
				}
				$open = null;
			}
			if ( $open && 'pending_payment' === $open->change_status ) {
				self::void_pending( $ctx['stripe'], (int) $open->change_id, 'cancelled' );
				do_action( 'wp_easycart_subscription_change_cancelled', (int) $ctx['row']->subscription_id, (int) $open->change_id, $reason );
			}
			if ( empty( $ctx['live']->schedule ) ) {
				if ( $open && 'scheduled' === $open->change_status && self::set_status( (int) $open->change_id, 'scheduled', 'cancelled' ) ) {
					do_action( 'wp_easycart_subscription_change_cancelled', (int) $ctx['row']->subscription_id, (int) $open->change_id, $reason );
				}
				return false;
			}
			$schedule_id = is_object( $ctx['live']->schedule ) ? (string) $ctx['live']->schedule->id : (string) $ctx['live']->schedule;
			$ours        = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_subscription_change WHERE stripe_schedule_id = %s ORDER BY change_id DESC LIMIT 1', $schedule_id ) );
			if ( ! $ours ) {
				return new WP_Error( 'schedule', 'This subscription has a change the store set up in Stripe.' );
			}
			$released = $ctx['stripe']->api( 'POST', 'subscription_schedules/' . rawurlencode( $schedule_id ) . '/release', array() );
			if ( is_wp_error( $released ) && ! self::schedule_gone( $released ) ) {
				return $released;
			}
			if ( 'scheduled' === $ours->change_status && self::set_status( (int) $ours->change_id, 'scheduled', 'cancelled' ) ) {
				/**
				 * An open plan change was cancelled ( by the customer, a newer change, the store or the subscription ending ).
				 *
				 * @since 6.0.3
				 * @param int    $subscription_id Subscription.
				 * @param int    $change_id       ec_subscription_change row.
				 * @param string $reason          cancelled | replaced | store | ended.
				 */
				do_action( 'wp_easycart_subscription_change_cancelled', (int) $ctx['row']->subscription_id, (int) $ours->change_id, $reason );
			}
			return true;
		}

		/**
		 * Stripe answered that the schedule is already released, completed or gone.
		 *
		 * @param WP_Error $error Stripe's refusal.
		 * @return bool
		 */
		private static function schedule_gone( $error ) {
			return 'resource_missing' === $error->get_error_code() || false !== stripos( $error->get_error_message(), 'status' );
		}

		/**
		 * End a change waiting on a payment: void its invoice ( Stripe drops the pending update with it ).
		 *
		 * @param object $stripe    Gateway.
		 * @param int    $change_id Change.
		 * @param string $to        cancelled | failed.
		 */
		private static function void_pending( $stripe, $change_id, $to ) {
			global $wpdb;
			$invoice = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT stripe_invoice_id FROM ec_subscription_change WHERE change_id = %d', (int) $change_id ) );
			if ( '' !== $invoice ) {
				$stripe->api( 'POST', 'invoices/' . rawurlencode( $invoice ) . '/void', array() );
			}
			self::set_status( (int) $change_id, 'pending_payment', $to );
		}

		/**
		 * Cancel the open change on a subscription ( My Account, the store's own change or cancellation ).
		 *
		 * @param int    $subscription_id Subscription.
		 * @param string $reason          cancelled | store | ended.
		 * @return bool|WP_Error True when a change was cancelled.
		 */
		public static function cancel( $subscription_id, $reason = 'cancelled' ) {
			$open = self::open( $subscription_id );
			if ( ! $open ) {
				return false;
			}
			$stripe = self::stripe();
			if ( ! $stripe ) {
				return new WP_Error( 'gateway', 'The store does not take payments through Stripe.' );
			}
			if ( 'pending_payment' === $open->change_status ) {
				self::void_pending( $stripe, (int) $open->change_id, 'cancelled' );
			} elseif ( '' === (string) $open->stripe_schedule_id ) {
				/* A price move waits here, not in Stripe. */
				if ( ! self::set_status( (int) $open->change_id, 'scheduled', 'cancelled' ) ) {
					return false;
				}
			} else {
				$released = $stripe->api( 'POST', 'subscription_schedules/' . rawurlencode( (string) $open->stripe_schedule_id ) . '/release', array() );
				if ( is_wp_error( $released ) && ! self::schedule_gone( $released ) ) {
					return $released;
				}
				if ( ! self::set_status( (int) $open->change_id, 'scheduled', 'cancelled' ) ) {
					return false;
				}
			}
			do_action( 'wp_easycart_subscription_change_cancelled', (int) $subscription_id, (int) $open->change_id, $reason );
			return true;
		}

		/**
		 * Before a subscription is cancelled ( My Account, the store ): its open change goes, so Stripe takes the cancellation ( it
		 * refuses cancel_at_period_end on a subscription a schedule manages ).
		 *
		 * @param int $subscription_id Subscription.
		 */
		public static function before_cancel( $subscription_id ) {
			if ( self::ready() && self::open( $subscription_id ) ) {
				self::cancel( $subscription_id, 'ended' );
			}
		}

		/**
		 * The subscription ended ( Stripe's customer.subscription.deleted ): its open change will not happen.
		 *
		 * @param int $subscription_id Subscription.
		 */
		public static function closed( $subscription_id ) {
			global $wpdb;
			if ( ! self::ready() ) {
				return;
			}
			$wpdb->query( $wpdb->prepare( "UPDATE ec_subscription_change SET change_status = 'cancelled', updated_at = %s WHERE subscription_id = %d AND change_status IN ( 'scheduled', 'pending_payment' )", current_time( 'mysql', true ), (int) $subscription_id ) );
		}

		/**
		 * The subscriber changed their card: a scheduled change bills the new one.
		 *
		 * @param int    $subscription_id Subscription.
		 * @param string $payment_method  Stripe payment method.
		 */
		public static function payment_method_changed( $subscription_id, $payment_method ) {
			$open = self::open( $subscription_id );
			if ( ! $open || 'scheduled' !== $open->change_status || '' === (string) $payment_method ) {
				return;
			}
			$stripe = self::stripe();
			if ( $stripe ) {
				$stripe->api( 'POST', 'subscription_schedules/' . rawurlencode( (string) $open->stripe_schedule_id ), array( 'default_settings' => array( 'default_payment_method' => (string) $payment_method ) ) );
			}
		}

		/**
		 * Write a change that applied to ec_subscription and announce it ( wp_easycart_subscription_plan_changed ).
		 *
		 * @param object $row       ec_subscription row before the change.
		 * @param object $product   ec_product row it moved to.
		 * @param int    $quantity  New quantity.
		 * @param array  $args      source, user.
		 * @param string $price     Stripe price it bills now.
		 * @param int    $change_id ec_subscription_change row.
		 * @param string $when      now | renewal.
		 */
		private static function apply_local( $row, $product, $quantity, $args, $price, $change_id, $when ) {
			$ec_db             = new ec_db(); /* sets ec_db's database handle for its static calls */
			$previous_quantity = max( 1, (int) $row->quantity );
			if ( isset( $args['user'] ) && is_object( $args['user'] ) && isset( $args['user']->billing ) && is_object( $args['user']->billing ) ) {
				ec_db::update_subscription( (int) $row->subscription_id, $args['user'], $product, null, $quantity );
			} else {
				ec_db::upgrade_subscription( (int) $row->subscription_id, $product, $quantity );
			}
			/** This action is documented in inc/classes/core/class-wp-easycart-subscription-prices.php */
			do_action(
				'wp_easycart_subscription_plan_changed',
				(int) $row->subscription_id,
				(int) $product->product_id,
				(int) $row->product_id,
				array(
					'quantity'               => (int) $quantity,
					'previous_quantity'      => $previous_quantity,
					'interval_changed'       => ( (string) $row->payment_period . '/' . (int) $row->payment_length !== (string) $product->subscription_bill_period . '/' . (int) $product->subscription_bill_length ),
					'source'                 => isset( $args['source'] ) ? (string) $args['source'] : 'customer',
					'stripe_subscription_id' => (string) $row->stripe_subscription_id,
					'stripe_price_id'        => (string) $price,
					'change_id'              => (int) $change_id,
					'when'                   => $when,
				)
			);
		}

		/* ------------------------------------------------------------------ price moves ( Phase 3 ) */

		/**
		 * Days of notice before a price change ( 30 covers California and New York ), 7 to 90.
		 *
		 * @param int|null $days Asked for.
		 * @return int
		 */
		public static function notice_days( $days = null ) {
			/**
			 * Days of notice before a subscription's price changes.
			 *
			 * @since 6.0.3
			 * @param int $days Asked for ( 30 by default ).
			 */
			$days = (int) apply_filters( 'wp_easycart_subscription_price_notice_days', ( null === $days ) ? 30 : (int) $days );
			return max( 7, min( 90, $days ) );
		}

		/**
		 * The first renewal at or after a time, counted from the current period's end in billing periods ( UTC ). Stripe's own
		 * date wins when the move applies.
		 *
		 * @param int    $end    The current period's end.
		 * @param string $period D | W | M | Y.
		 * @param int    $length Every N.
		 * @param int    $min    Not before.
		 * @return int
		 */
		public static function renewal_after( $end, $period, $length, $min ) {
			$at = (int) $end;
			if ( $at <= 0 ) {
				return 0;
			}
			$units  = array(
				'D' => 'day',
				'W' => 'week',
				'Y' => 'year',
			);
			$period = strtoupper( substr( (string) $period, 0, 1 ) );
			$unit   = isset( $units[ $period ] ) ? $units[ $period ] : 'month';
			$step   = '+' . max( 1, (int) $length ) . ' ' . $unit;
			for ( $guard = 0; $at < (int) $min && $guard < 500; $guard++ ) {
				$date = new DateTime( '@' . $at );
				$date->modify( $step );
				$at = (int) $date->getTimestamp();
			}
			return $at;
		}

		/**
		 * The email a subscriber is told at.
		 *
		 * @param object $row ec_subscription row.
		 * @return string
		 */
		private static function recipient( $row ) {
			global $wpdb;
			$to = trim( (string) $row->email );
			if ( ! is_email( $to ) && (int) $row->user_id > 0 ) {
				$to = trim( (string) $wpdb->get_var( $wpdb->prepare( 'SELECT email FROM ec_user WHERE user_id = %d', (int) $row->user_id ) ) );
			}
			return is_email( $to ) ? $to : '';
		}

		/**
		 * Move a subscriber to its product's current price: the notice goes now, the price changes at the first renewal at least
		 * notice_days away.
		 *
		 * @param int   $subscription_id Subscription.
		 * @param array $args            notice_days ( 30 ).
		 * @return array { state: scheduled | current | busy | skipped | error, message, change_id, at }
		 */
		public static function move_price( $subscription_id, $args = array() ) {
			global $wpdb;
			$args = array_merge( array( 'notice_days' => 30 ), (array) $args );
			$out  = array(
				'state'     => 'error',
				'message'   => '',
				'change_id' => 0,
				'at'        => 0,
			);
			if ( ! self::active() ) {
				$out['message'] = 'gateway';
				return $out;
			}
			$ec_db = new ec_db(); /* sets ec_db's database handle for its static calls */
			$row   = ec_db::get_subscription_row( (int) $subscription_id );
			if ( ! $row ) {
				$out['message'] = 'missing';
				return $out;
			}
			if ( self::open( (int) $subscription_id ) ) {
				$out['state'] = 'busy';
				return $out;
			}
			$ctx = self::context( (int) $subscription_id, (int) $row->product_id, 0 );
			if ( is_wp_error( $ctx ) ) {
				$out['state']   = 'skipped';
				$out['message'] = $ctx->get_error_code();
				return $out;
			}
			$live = $ctx['live'];
			if ( ! in_array( (string) $live->status, array( 'active', 'trialing' ), true ) || ! empty( $live->cancel_at_period_end ) || ! empty( $live->cancel_at ) ) {
				$out['state']   = 'skipped';
				$out['message'] = 'ending';
				return $out;
			}
			if ( ! empty( $live->schedule ) ) {
				$out['state'] = 'busy';
				return $out;
			}
			$price = (string) wp_easycart_subscription_prices::ensure( (int) $row->product_id, $ctx['stripe'] );
			if ( '' === $price ) {
				$out['message'] = 'price';
				return $out;
			}
			$item     = $ctx['item'];
			$now      = ( $item && isset( $item->price ) && is_object( $item->price ) ) ? $item->price : null;
			$new_unit = round( (float) $ctx['product']->price, 2 );
			$divisor  = self::divisor( ( $now && isset( $now->currency ) ) ? $now->currency : get_option( 'ec_option_stripe_currency', 'usd' ) );
			$old_unit = ( $now && isset( $now->unit_amount ) ) ? round( (int) $now->unit_amount / $divisor, 2 ) : round( (float) $row->price, 2 );
			if ( $now && ( (string) $now->id === $price || abs( $old_unit - $new_unit ) < 0.005 ) ) {
				/* Already paying it: keep the stored price in step so the subscriber no longer counts as behind. */
				$wpdb->update( 'ec_subscription', array( 'price' => $new_unit ), array( 'subscription_id' => (int) $row->subscription_id ) );
				$out['state'] = 'current';
				return $out;
			}
			$quantity = ( $item && isset( $item->quantity ) ) ? max( 1, (int) $item->quantity ) : max( 1, (int) $row->quantity );
			$days     = self::notice_days( (int) $args['notice_days'] );
			$at       = self::renewal_after( self::period_end( $live ), (string) $ctx['product']->subscription_bill_period, (int) $ctx['product']->subscription_bill_length, time() + $days * DAY_IN_SECONDS );
			if ( $at <= 0 || '' === self::recipient( $row ) ) {
				$out['state']   = 'skipped';
				$out['message'] = ( $at <= 0 ) ? 'date' : 'email';
				return $out;
			}
			/* The notice first: a subscriber who was not told is not moved. */
			$notice           = clone $row;
			$notice->price    = $old_unit;
			$notice->quantity = $quantity;
			$sent             = class_exists( 'wp_easycart_subscription_reminders' ) && wp_easycart_subscription_reminders::send( 'price', $notice, $at, round( $new_unit * $quantity, 2 ) );
			if ( ! $sent ) {
				$out['message'] = 'email';
				return $out;
			}
			$out['change_id'] = self::record(
				array(
					'subscription_id' => (int) $row->subscription_id,
					'from_product_id' => (int) $row->product_id,
					'to_product_id'   => (int) $row->product_id,
					'from_quantity'   => $quantity,
					'to_quantity'     => $quantity,
					'change_when'     => 'renewal',
					'change_status'   => 'scheduled',
					'effective_at'    => $at,
					'amount'          => $new_unit,
					'stripe_price_id' => $price,
					'change_source'   => self::PRICE_SOURCE,
				)
			);
			self::schedule_price_moves();
			$out['state'] = 'scheduled';
			$out['at']    = $at;
			/**
			 * A subscriber was told their price changes at a renewal.
			 *
			 * @since 6.0.3
			 * @param int   $subscription_id Subscription.
			 * @param int   $change_id       ec_subscription_change row.
			 * @param int   $at              The renewal the new price starts.
			 * @param float $old_unit        Price paid now ( each ).
			 * @param float $new_unit        New price ( each ).
			 */
			do_action( 'wp_easycart_subscription_price_move_scheduled', (int) $row->subscription_id, $out['change_id'], $at, $old_unit, $new_unit );
			return $out;
		}

		/** Run the daily price move job while any move waits. */
		public static function schedule_price_moves() {
			if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::PRICE_CRON ) ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::PRICE_CRON );
			}
		}

		/**
		 * WP-Cron PRICE_CRON: apply the price moves whose renewal is within three days. A full batch that moved something runs again ten
		 * minutes later, so a large store's moves all land before their renewals.
		 *
		 * @param int $limit At most this many.
		 * @return array change_id => apply_price_move()'s answer.
		 */
		public static function run_price_moves( $limit = 50 ) {
			global $wpdb;
			if ( ! self::ready() ) {
				return array();
			}
			$done = array();
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM ec_subscription_change WHERE change_status = 'scheduled' AND change_source = %s AND effective_at <= %d ORDER BY effective_at ASC LIMIT %d", self::PRICE_SOURCE, time() + 3 * DAY_IN_SECONDS, max( 1, (int) $limit ) ) );
			foreach ( (array) $rows as $change ) {
				$done[ (int) $change->change_id ] = self::apply_price_move( $change );
			}
			$left = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT( change_id ) FROM ec_subscription_change WHERE change_status = 'scheduled' AND change_source = %s", self::PRICE_SOURCE ) );
			if ( 0 === $left && function_exists( 'wp_clear_scheduled_hook' ) ) {
				wp_clear_scheduled_hook( self::PRICE_CRON );
			}
			$moved = count( array_diff( $done, array( 'waiting', 'error' ) ) );
			if ( $left > 0 && $moved > 0 && count( $done ) >= max( 1, (int) $limit ) && function_exists( 'wp_schedule_single_event' ) ) {
				wp_schedule_single_event( time() + 10 * MINUTE_IN_SECONDS, self::PRICE_CRON );
			}
			return $done;
		}

		/**
		 * Switch one subscription to its new price, with no proration, before the renewal the subscriber was told about.
		 *
		 * @param object $change ec_subscription_change row.
		 * @return string applied | waiting | cancelled | error | none
		 */
		public static function apply_price_move( $change ) {
			global $wpdb;
			$stripe = self::stripe();
			if ( ! $stripe ) {
				return 'waiting';
			}
			$ec_db = new ec_db(); /* sets ec_db's database handle for its static calls */
			$row   = ec_db::get_subscription_row( (int) $change->subscription_id );
			if ( ! $row ) {
				self::set_status( (int) $change->change_id, 'scheduled', 'cancelled' );
				return 'cancelled';
			}
			$customer = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT stripe_customer_id FROM ec_user WHERE user_id = %d', (int) $row->user_id ) );
			$live     = $stripe->get_subscription( $customer, (string) $row->stripe_subscription_id );
			if ( ! is_object( $live ) || empty( $live->id ) ) {
				return 'waiting';
			}
			if ( in_array( (string) $live->status, array( 'canceled', 'incomplete_expired' ), true ) || ! empty( $live->cancel_at_period_end ) ) {
				if ( self::set_status( (int) $change->change_id, 'scheduled', 'cancelled' ) ) {
					do_action( 'wp_easycart_subscription_change_cancelled', (int) $row->subscription_id, (int) $change->change_id, 'ended' );
				}
				return 'cancelled';
			}
			$end = self::period_end( $live );
			if ( ( $end > 0 && $end < (int) $change->effective_at - 2 * DAY_IN_SECONDS ) || ! empty( $live->schedule ) ) {
				return 'waiting'; /* an earlier renewal comes first, or a change waits in Stripe */
			}
			$item = wp_easycart_subscription_prices::main_item_object( $live, (int) $row->product_id );
			if ( ! $item || empty( $item->id ) ) {
				return 'waiting';
			}
			$quantity = isset( $item->quantity ) ? max( 1, (int) $item->quantity ) : max( 1, (int) $row->quantity );
			$updated  = $stripe->api(
				'POST',
				'subscriptions/' . rawurlencode( (string) $live->id ),
				array(
					'items'              => array(
						array(
							'id'       => (string) $item->id,
							'price'    => (string) $change->stripe_price_id,
							'quantity' => $quantity,
						),
					),
					'proration_behavior' => 'none',
				)
			);
			if ( is_wp_error( $updated ) ) {
				return 'error';
			}
			if ( ! self::set_status( (int) $change->change_id, 'scheduled', 'applied', $end > 0 ? $end : (int) $change->effective_at ) ) {
				return 'none';
			}
			$product = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_product WHERE product_id = %d', (int) $change->to_product_id ) );
			if ( $product ) {
				self::apply_local( $row, $product, $quantity, array( 'source' => self::PRICE_SOURCE ), (string) $change->stripe_price_id, (int) $change->change_id, 'renewal' );
			}
			return 'applied';
		}

		/**
		 * Subscriptions to a product that pay less or more than its price now ( Stripe, running, nothing waiting ).
		 *
		 * @param int $product_id Product.
		 * @param int $after      Subscription ids after this one.
		 * @param int $limit      At most this many ( 0 = count them ).
		 * @return int|int[]
		 */
		public static function price_candidates( $product_id, $after = 0, $limit = 0 ) {
			global $wpdb;
			if ( ! self::ready() ) {
				return ( $limit > 0 ) ? array() : 0;
			}
			$price = $wpdb->get_var( $wpdb->prepare( 'SELECT price FROM ec_product WHERE product_id = %d', (int) $product_id ) );
			if ( null === $price ) {
				return ( $limit > 0 ) ? array() : 0;
			}
			$where = $wpdb->prepare( "s.product_id = %d AND s.subscription_id > %d AND s.subscription_type = 'stripe' AND s.stripe_subscription_id != '' AND s.subscription_status IN ( 'Active', 'trialing' ) AND ROUND( s.price, 2 ) != ROUND( %f, 2 ) AND NOT EXISTS ( SELECT 1 FROM ec_subscription_change c WHERE c.subscription_id = s.subscription_id AND c.change_status IN ( 'scheduled', 'pending_payment' ) )", (int) $product_id, (int) $after, (float) $price );
			if ( $limit <= 0 ) {
				return (int) $wpdb->get_var( 'SELECT COUNT( s.subscription_id ) FROM ec_subscription s WHERE ' . $where ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
			}
			return array_map( 'intval', (array) $wpdb->get_col( 'SELECT s.subscription_id FROM ec_subscription s WHERE ' . $where . ' ORDER BY s.subscription_id ASC LIMIT ' . (int) $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
		}

		/**
		 * Where a product's subscribers stand: on an older price, being moved ( and when the first one moves ).
		 *
		 * @param int $product_id Product.
		 * @return array { behind, moving, next }
		 */
		public static function price_status( $product_id ) {
			global $wpdb;
			$out = array(
				'behind' => 0,
				'moving' => 0,
				'next'   => 0,
			);
			if ( ! self::ready() ) {
				return $out;
			}
			$out['behind'] = (int) self::price_candidates( (int) $product_id );
			$moving        = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT( change_id ) AS moving, MIN( effective_at ) AS next_at FROM ec_subscription_change WHERE change_status = 'scheduled' AND change_source = %s AND to_product_id = %d", self::PRICE_SOURCE, (int) $product_id ) );
			$out['moving'] = $moving ? (int) $moving->moving : 0;
			$out['next']   = $moving ? (int) $moving->next_at : 0;
			return $out;
		}

		/**
		 * The store stops a product's waiting price moves ( subscribers keep the price they pay ).
		 *
		 * @param int $product_id Product.
		 * @return int How many stopped.
		 */
		public static function stop_price_moves( $product_id ) {
			global $wpdb;
			if ( ! self::ready() ) {
				return 0;
			}
			$stopped = 0;
			$rows    = $wpdb->get_results( $wpdb->prepare( "SELECT change_id, subscription_id FROM ec_subscription_change WHERE change_status = 'scheduled' AND change_source = %s AND to_product_id = %d", self::PRICE_SOURCE, (int) $product_id ) );
			foreach ( (array) $rows as $change ) {
				if ( self::set_status( (int) $change->change_id, 'scheduled', 'cancelled' ) ) {
					++$stopped;
					do_action( 'wp_easycart_subscription_change_cancelled', (int) $change->subscription_id, (int) $change->change_id, 'store' );
				}
			}
			return $stopped;
		}

		/* ------------------------------------------------------------------ Stripe's side */

		/**
		 * Stripe's subscription changed ( the customer.subscription.updated webhook, whose body is never trusted: the subscription is
		 * read again from Stripe ). Only subscriptions with an open change ask Stripe.
		 *
		 * @param string $stripe_subscription_id Stripe subscription.
		 * @return string reconcile()'s answer.
		 */
		public static function webhook( $stripe_subscription_id ) {
			global $wpdb;
			if ( ! self::ready() || '' === (string) $stripe_subscription_id ) {
				return 'none';
			}
			$subscription_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT subscription_id FROM ec_subscription WHERE stripe_subscription_id = %s', (string) $stripe_subscription_id ) );
			if ( ! $subscription_id || ! self::open( $subscription_id ) ) {
				return 'none';
			}
			$stripe = self::stripe();
			if ( ! $stripe ) {
				return 'none';
			}
			$live = $stripe->api( 'GET', 'subscriptions/' . rawurlencode( (string) $stripe_subscription_id ), array() );
			return is_wp_error( $live ) ? 'none' : self::reconcile( $live );
		}

		/**
		 * A scheduled change that started, or a pending change that was paid, applies here; one that can no longer happen is closed.
		 *
		 * @param object $live Stripe subscription ( read from Stripe ).
		 * @return string applied | cancelled | failed | waiting | none
		 */
		public static function reconcile( $live ) {
			global $wpdb;
			if ( ! self::ready() || ! is_object( $live ) || empty( $live->id ) ) {
				return 'none';
			}
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_subscription WHERE stripe_subscription_id = %s', (string) $live->id ) );
			if ( ! $row ) {
				return 'none';
			}
			$open = self::open( (int) $row->subscription_id );
			if ( ! $open ) {
				return 'none';
			}
			$on_target = self::live_quantity( $live, (string) $open->stripe_price_id ) === (int) $open->to_quantity;
			$ended     = isset( $live->status ) && in_array( (string) $live->status, array( 'canceled', 'incomplete_expired' ), true );
			if ( $ended ) {
				self::closed( (int) $row->subscription_id );
				return 'cancelled';
			}
			if ( 'pending_payment' === $open->change_status ) {
				if ( ! empty( $live->pending_update ) ) {
					return 'waiting';
				}
				if ( ! $on_target ) {
					self::set_status( (int) $open->change_id, 'pending_payment', 'failed' );
					return 'failed';
				}
			} elseif ( ! $on_target ) {
				$schedule = empty( $live->schedule ) ? '' : ( is_object( $live->schedule ) ? (string) $live->schedule->id : (string) $live->schedule );
				if ( $schedule !== (string) $open->stripe_schedule_id && self::set_status( (int) $open->change_id, 'scheduled', 'cancelled' ) ) {
					/* The schedule was released or cancelled in Stripe before the change started. */
					do_action( 'wp_easycart_subscription_change_cancelled', (int) $row->subscription_id, (int) $open->change_id, 'store' );
					return 'cancelled';
				}
				return 'waiting';
			}
			if ( ! self::set_status( (int) $open->change_id, (string) $open->change_status, 'applied', time() ) ) {
				return 'none'; /* another request applied it */
			}
			$product = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_product WHERE product_id = %d', (int) $open->to_product_id ) );
			if ( $product ) {
				self::apply_local( $row, $product, (int) $open->to_quantity, array( 'source' => (string) $open->change_source ), (string) $open->stripe_price_id, (int) $open->change_id, (string) $open->change_when );
			}
			$stripe = ( 'renewal' === (string) $open->change_when && '' !== (string) $open->stripe_schedule_id ) ? self::stripe() : false;
			if ( $stripe ) {
				/* The schedule's work is done: release it, so later changes and cancellations go to the subscription itself. */
				$stripe->api( 'POST', 'subscription_schedules/' . rawurlencode( (string) $open->stripe_schedule_id ) . '/release', array() );
			}
			return 'applied';
		}

		/**
		 * The quantity a price has on a live subscription ( 0 = not on it ).
		 *
		 * @param object $live  Stripe subscription.
		 * @param string $price Price.
		 * @return int
		 */
		private static function live_quantity( $live, $price ) {
			if ( '' === $price || ! isset( $live->items->data ) || ! is_array( $live->items->data ) ) {
				return 0;
			}
			foreach ( $live->items->data as $item ) {
				$id = isset( $item->price ) ? ( is_object( $item->price ) ? (string) $item->price->id : (string) $item->price ) : '';
				if ( $id === $price ) {
					return isset( $item->quantity ) ? (int) $item->quantity : 1;
				}
			}
			return 0;
		}

		/* ------------------------------------------------------------------ customers */

		/**
		 * A customer's change from the older paths ( template copies before 6.0.3, the classic account form ): the rule applies, and a
		 * payment that needs 3-D Secure, which those pages cannot ask for, is not taken.
		 *
		 * @param int    $subscription_id Subscription.
		 * @param int    $product_id      Plan.
		 * @param int    $quantity        Quantity.
		 * @param object $user            ec_user.
		 * @return bool Applied or scheduled.
		 */
		public static function customer_change( $subscription_id, $product_id, $quantity, $user ) {
			$result = self::request(
				$subscription_id,
				$product_id,
				$quantity,
				array(
					'source' => 'customer',
					'user'   => $user,
				)
			);
			$stripe = ( 'action' === $result['state'] ) ? self::stripe() : false;
			if ( $stripe ) {
				self::void_pending( $stripe, (int) $result['change_id'], 'failed' );
			}
			return in_array( $result['state'], array( 'applied', 'scheduled' ), true );
		}

		/**
		 * The subscription a signed-in customer already has in the plan they are about to buy ( D10: WP EasyCart PRO answers for plan
		 * groups, so a subscriber changes plan instead of buying a second ).
		 *
		 * @param array|object $product The subscription page's product row.
		 * @param object       $user    ec_user.
		 * @return int Subscription id, or 0.
		 */
		public static function existing_for( $product, $user ) {
			$user_id = ( is_object( $user ) && isset( $user->user_id ) ) ? (int) $user->user_id : 0;
			if ( $user_id <= 0 ) {
				return 0;
			}
			/**
			 * The subscription a customer should change instead of buying this product ( 0 = buy as usual ).
			 *
			 * @since 6.0.3
			 * @param int          $subscription_id 0.
			 * @param array|object $product         Product row.
			 * @param int          $user_id         ec_user id.
			 */
			return (int) apply_filters( 'wp_easycart_subscription_page_existing', 0, $product, $user_id );
		}

		/**
		 * The subscription page's notice for D10: change the plan you have.
		 *
		 * @param int          $subscription_id The customer's subscription.
		 * @param array|object $product         The product they opened.
		 */
		public static function print_existing_notice( $subscription_id, $product ) {
			$product_id = is_array( $product ) ? (int) $product['product_id'] : (int) $product->product_id;
			$url        = wpeasycart_links()->get_account_page(
				'subscription_details',
				array(
					'subscription_id' => (int) $subscription_id,
					'change_plan'     => $product_id,
				)
			);
			echo '<div class="ec_subscription_purchased ec_subscription_change_existing"><p>' . esc_html( self::text( 'subscription_change_existing', array(), 'cart_login' ) ) . '</p>';
			echo '<a class="ec_subscription_change_existing_link" href="' . esc_url( $url ) . '">' . esc_html( self::text( 'subscription_change_existing_link', array(), 'cart_login' ) ) . '</a></div>';
		}

		/**
		 * The subscriptions a signed-in customer already has that this subscription page sells: this product's, and with WP EasyCart
		 * PRO its plan group's other plans when the group lets customers buy more than one ( filter wp_easycart_subscription_page_owned ).
		 * Only asked when the product may be bought again ( allow_multiple_subscription_purchases ): the page then reminds them.
		 *
		 * @since 6.0.3
		 * @param array|object $product The subscription page's product row.
		 * @param object       $user    ec_user.
		 * @return object[] { subscription_id, product_id, title }, newest first.
		 */
		public static function owned_for( $product, $user ) {
			global $wpdb;
			$user_id    = ( is_object( $user ) && isset( $user->user_id ) ) ? (int) $user->user_id : 0;
			$product_id = is_array( $product ) ? (int) $product['product_id'] : ( is_object( $product ) ? (int) $product->product_id : 0 );
			if ( $user_id <= 0 || $product_id <= 0 ) {
				return array();
			}
			$statuses = function_exists( 'wp_easycart_subscription_member_status_sql' ) ? wp_easycart_subscription_member_status_sql() : " IN ( 'Active' )";
			$rows     = $wpdb->get_results( $wpdb->prepare( 'SELECT subscription_id, product_id, title FROM ec_subscription WHERE user_id = %d AND product_id = %d AND subscription_status' . $statuses . ' ORDER BY subscription_id DESC', $user_id, $product_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $statuses is prepared by wp_easycart_subscription_member_status_sql().
			/**
			 * The subscriptions a customer already has that the subscription page should remind them of.
			 *
			 * @since 6.0.3
			 * @param object[]     $rows       { subscription_id, product_id, title }.
			 * @param array|object $product    Product row.
			 * @param int          $user_id    ec_user id.
			 */
			$rows = apply_filters( 'wp_easycart_subscription_page_owned', is_array( $rows ) ? $rows : array(), $product, $user_id );
			return is_array( $rows ) ? array_values( $rows ) : array();
		}

		/**
		 * The subscription page's reminder for a customer who already subscribes and may buy another: what they have and where to
		 * manage it. The form stays below.
		 *
		 * @since 6.0.3
		 * @param object[] $rows owned_for().
		 */
		public static function print_owned_notice( $rows ) {
			$rows = array_values( (array) $rows );
			if ( ! $rows ) {
				return;
			}
			if ( 1 === count( $rows ) ) {
				$title = function_exists( 'wp_easycart_language' ) ? wp_easycart_language()->convert_text( (string) $rows[0]->title ) : (string) $rows[0]->title;
				$text  = self::text( 'subscription_owned_one', array( 'title' => wp_strip_all_tags( $title ) ), 'cart_login' );
				$link  = self::text( 'subscription_owned_link_one', array(), 'cart_login' );
				$url   = wpeasycart_links()->get_account_page( 'subscription_details', array( 'subscription_id' => (int) $rows[0]->subscription_id ) );
			} else {
				$text = self::text( 'subscription_owned_many', array( 'count' => count( $rows ) ), 'cart_login' );
				$link = self::text( 'subscription_owned_link_many', array(), 'cart_login' );
				$url  = wpeasycart_links()->get_account_page( 'subscriptions' );
			}
			echo '<div class="ec_subscription_owned" role="status"><p>' . esc_html( $text ) . '</p><a class="ec_subscription_owned_link" href="' . esc_url( $url ) . '">' . esc_html( $link ) . '</a></div>';
		}

		/**
		 * My Account: the open change above the subscription, with Cancel ( wpeasycart_account_subscription_details_before ).
		 *
		 * @param ec_subscription $subscription The subscription.
		 */
		public static function print_open_change( $subscription ) {
			if ( ! is_object( $subscription ) || empty( $subscription->subscription_id ) || ! self::ready() ) {
				return;
			}
			/* What the last change did ( the page the change AJAX sent the customer back to ). Display only. */
			$results = array(
				'applied'   => 'subscription_change_applied',
				'scheduled' => 'subscription_change_scheduled',
				'cancelled' => 'subscription_change_cancelled',
				'waiting'   => 'subscription_change_pending_payment',
			);
			$result  = isset( $_GET['subscription_change'] ) ? sanitize_key( wp_unslash( $_GET['subscription_change'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only: picks one of four fixed notices.
			$open    = self::open( (int) $subscription->subscription_id );
			if ( isset( $results[ $result ] ) && ( 'waiting' !== $result || ! $open ) ) {
				echo '<div class="ec_account_subscription_v2_notice is-change-result ec_account_subscription_change_result" role="status">' . esc_html( self::text( ( 'waiting' === $result ) ? 'subscription_change_applied' : $results[ $result ] ) ) . '</div>';
			}
			if ( ! $open ) {
				return;
			}
			global $wpdb;
			$title = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT title FROM ec_product WHERE product_id = %d', (int) $open->to_product_id ) );
			$title = function_exists( 'wp_easycart_language' ) ? wp_strip_all_tags( wp_easycart_language()->convert_text( $title ) ) : $title;
			if ( (int) $open->to_quantity > 1 ) {
				$title .= ' × ' . (int) $open->to_quantity;
			}
			if ( self::PRICE_SOURCE === (string) $open->change_source ) {
				$product = $wpdb->get_row( $wpdb->prepare( 'SELECT subscription_bill_period, subscription_bill_length FROM ec_product WHERE product_id = %d', (int) $open->to_product_id ) );
				$period  = ( $product && function_exists( 'wp_easycart_subscription_period_text' ) ) ? wp_strip_all_tags( (string) wp_easycart_subscription_period_text( (int) $product->subscription_bill_length, strtoupper( substr( (string) $product->subscription_bill_period, 0, 1 ) ) ) ) : '';
				$fill    = array(
					'price' => self::money( (float) $open->amount * max( 1, (int) $subscription->quantity ) ) . $period,
					'date'  => self::date( (int) $open->effective_at ),
				);
				echo '<div class="ec_account_subscription_v2_notice is-change ec_account_subscription_change_open is-price"><span class="ec_account_subscription_change_open_text">' . esc_html( self::text( 'subscription_change_price', $fill ) ) . '</span></div>';
				return;
			}
			$key   = ( 'pending_payment' === $open->change_status ) ? 'subscription_change_pending_payment' : 'subscription_change_pending';
			$nonce = wp_create_nonce( 'wp-easycart-get-stripe-update-customer-card-' . $GLOBALS['ec_cart_data']->ec_cart_id );
			echo '<div class="ec_account_subscription_v2_notice is-change ec_account_subscription_change_open" data-subscription="' . esc_attr( (int) $subscription->subscription_id ) . '" data-nonce="' . esc_attr( $nonce ) . '">';
			$fill = array(
				'title' => $title,
				'date'  => self::date( (int) $open->effective_at ),
			);
			echo '<span class="ec_account_subscription_change_open_text">' . esc_html( self::text( $key, $fill ) ) . '</span> ';
			echo '<button type="button" class="ec_account_subscription_change_cancel" data-ec-sub-change-cancel="1">' . esc_html( self::text( 'subscription_change_cancel' ) ) . '</button>';
			echo '<span class="ec_account_subscription_change_open_error" role="alert" hidden></span>';
			echo '</div>';
		}

		/* ------------------------------------------------------------------ words */

		/**
		 * A phrase with [amount], [price], [date] and [title] filled in.
		 *
		 * @param string $key     Phrase.
		 * @param array  $fill    Values.
		 * @param string $section Language section.
		 * @return string Plain text.
		 */
		public static function text( $key, $fill = array(), $section = 'account_subscriptions' ) {
			$text = function_exists( 'wp_easycart_language' ) ? trim( (string) wp_easycart_language()->get_text( $section, $key ) ) : '';
			$text = ( '' === $text ) ? self::fallback( $key ) : wp_strip_all_tags( html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ) );
			foreach ( (array) $fill as $name => $value ) {
				$text = str_replace( '[' . $name . ']', (string) $value, $text );
			}
			return $text;
		}

		/**
		 * English for phrases a store's language data does not have yet.
		 *
		 * @param string $key Phrase.
		 * @return string
		 */
		public static function fallback( $key ) {
			$en = array(
				'subscription_change_review'          => 'Review change',
				'subscription_change_confirm'         => 'Confirm change',
				'subscription_change_back'            => 'Back',
				'subscription_change_now_charge'      => 'Your plan changes now. You pay [amount] today for the rest of this billing period, then [price] from [date].',
				'subscription_change_now_next'        => 'Your plan changes now. [amount] for the rest of this billing period is added to your next payment on [date].',
				'subscription_change_now_credit'      => 'Your plan changes now. [amount] for the unused part of this billing period comes off your next payment on [date].',
				'subscription_change_now_none'        => 'Your plan changes now. Your next payment is [price] on [date].',
				'subscription_change_now_interval'    => 'Your plan changes now and a new billing period starts today. You pay [amount] today, then [price] from [date].',
				'subscription_change_trial'           => 'Your plan changes now. Your free trial still ends on [date], then you pay [price].',
				'subscription_change_renewal'         => 'You keep your current plan until [date]. Then your plan changes and you pay [price].',
				'subscription_change_note_now'        => 'Starts now',
				'subscription_change_note_renewal'    => 'Starts [date]',
				'subscription_change_note_later'      => 'Starts at renewal',
				'subscription_change_note_trial'      => 'Starts now, free until [date]',
				'subscription_change_note_trial_end'  => 'Starts now and ends your free trial',
				'subscription_change_pending'         => 'Your plan changes to [title] on [date].',
				'subscription_change_pending_payment' => 'Your change to [title] is waiting for a payment to go through.',
				'subscription_change_cancel'          => 'Cancel this change',
				'subscription_change_cancelled'       => 'The change was cancelled. You keep your current plan.',
				'subscription_change_applied'         => 'Your plan has changed.',
				'subscription_change_scheduled'       => 'Your plan change is scheduled.',
				'subscription_change_declined'        => 'Your card was declined, so your plan did not change. Update your payment method and try again.',
				'subscription_change_error'           => 'Your plan could not be changed right now. Please try again, or contact us.',
				'subscription_change_unavailable'     => 'This change is not available for your subscription.',
				'subscription_change_monthly'         => 'Monthly',
				'subscription_change_yearly'          => 'Yearly',
				'subscription_change_existing'        => 'You already have a subscription to this plan. Change it from your account instead of buying a second one.',
				'subscription_change_existing_link'   => 'Change my plan',
				'subscription_owned_one'              => 'You already have a subscription to [title]. Buy another below, or manage yours in your account.',
				'subscription_owned_many'             => 'You already have [count] subscriptions to this plan. Buy another below, or manage them in your account.',
				'subscription_owned_link_one'         => 'Manage my subscription',
				'subscription_owned_link_many'        => 'Manage my subscriptions',
				'subscription_change_price'           => 'Your price changes to [price] from [date]. Nothing changes before then.',
			);
			return isset( $en[ $key ] ) ? $en[ $key ] : '';
		}

		/**
		 * The sentence a preview shows.
		 *
		 * @param array $preview preview() so far.
		 * @return string Plain text.
		 */
		public static function message( $preview ) {
			$period = function_exists( 'wp_easycart_subscription_period_text' ) ? (string) wp_easycart_subscription_period_text( (int) $preview['length'], (string) $preview['period'] ) : '';
			$fill   = array(
				'price' => self::money( $preview['new_total'] ) . wp_strip_all_tags( $period ),
				'date'  => self::date( $preview['at'] ),
			);
			if ( 'none' === $preview['when'] ) {
				return self::text( 'subscription_change_unavailable' );
			}
			if ( 'renewal' === $preview['when'] ) {
				return self::text( 'subscription_change_renewal', $fill );
			}
			if ( null !== $preview['amount_now'] ) {
				$fill['amount'] = self::money( $preview['amount_now'] );
				return self::text( ( 'same' !== $preview['interval'] || ! empty( $preview['trial'] ) ) ? 'subscription_change_now_interval' : 'subscription_change_now_charge', $fill );
			}
			if ( ! empty( $preview['trial'] ) ) {
				return self::text( 'subscription_change_trial', $fill );
			}
			if ( null !== $preview['amount_next'] && abs( $preview['amount_next'] ) >= 0.005 ) {
				$fill['amount'] = self::money( abs( $preview['amount_next'] ) );
				return self::text( ( $preview['amount_next'] < 0 ) ? 'subscription_change_now_credit' : 'subscription_change_now_next', $fill );
			}
			return self::text( 'subscription_change_now_none', $fill );
		}

		/**
		 * An amount in the store's currency format ( plain text ).
		 *
		 * @param float $amount Amount.
		 * @return string
		 */
		private static function money( $amount ) {
			if ( isset( $GLOBALS['currency'] ) && is_object( $GLOBALS['currency'] ) && method_exists( $GLOBALS['currency'], 'get_currency_display' ) ) {
				return wp_strip_all_tags( html_entity_decode( (string) $GLOBALS['currency']->get_currency_display( (float) $amount ), ENT_QUOTES, 'UTF-8' ) );
			}
			return number_format( (float) $amount, 2 );
		}

		/**
		 * A date in the store's format and time zone.
		 *
		 * @param int $at Timestamp.
		 * @return string
		 */
		public static function date( $at ) {
			if ( (int) $at <= 0 ) {
				return '';
			}
			$format = (string) get_option( 'date_format', 'F j, Y' );
			return function_exists( 'wp_date' ) ? (string) wp_date( $format, (int) $at ) : (string) date_i18n( $format, (int) $at );
		}

		/* ------------------------------------------------------------------ My Account AJAX */

		/**
		 * The subscription a My Account request is about, owned by the signed-in customer ( else a JSON error ).
		 *
		 * @param string $action change_plan ( to a plan offered ) | own.
		 * @return object ec_subscription row.
		 */
		private static function request_subscription( $action ) {
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- wp_easycart_subscription_change_guard() checked the nonce first.
			$subscription_id = isset( $_POST['subscription_id'] ) ? (int) $_POST['subscription_id'] : 0;
			$product_id      = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
			// phpcs:enable WordPress.Security.NonceVerification.Missing
			$ec_db = new ec_db(); /* sets ec_db's database handle for its static calls */
			$row   = ec_db::get_subscription_row( $subscription_id );
			$user  = ( isset( $GLOBALS['ec_cart_data']->cart_data->user_id ) ) ? (int) $GLOBALS['ec_cart_data']->cart_data->user_id : 0;
			if ( ! $row || $user <= 0 || (int) $row->user_id !== $user ) {
				wp_send_json_error( array( 'message' => self::text( 'subscription_change_error' ) ) );
			}
			if ( 'change_plan' === $action && ( ! function_exists( 'wp_easycart_customer_subscription_allows' ) || ! wp_easycart_customer_subscription_allows( $row, 'change_plan', $product_id ) ) ) {
				wp_send_json_error( array( 'message' => self::text( 'subscription_change_unavailable' ) ) );
			}
			return $row;
		}

		/**
		 * The quantity posted ( or the subscription's ).
		 *
		 * @param object $row Subscription.
		 * @return int
		 */
		private static function request_quantity( $row ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- wp_easycart_subscription_change_guard() checked the nonce first.
			$quantity = isset( $_POST['quantity'] ) ? (int) $_POST['quantity'] : 0;
			return ( $quantity > 0 && ! get_option( 'ec_option_subscription_one_only' ) ) ? min( 1000000, $quantity ) : max( 1, (int) $row->quantity );
		}

		/**
		 * The subscription page to return to.
		 *
		 * @param int    $subscription_id Subscription.
		 * @param string $result          applied | scheduled | cancelled | waiting.
		 * @return string
		 */
		private static function page_url( $subscription_id, $result ) {
			return esc_url_raw(
				wpeasycart_links()->get_account_page(
					'subscription_details',
					array(
						'subscription_id'     => (int) $subscription_id,
						'subscription_change' => sanitize_key( $result ),
					)
				)
			);
		}

		/** AJAX: what a change will do. */
		public static function ajax_preview() {
			wp_easycart_subscription_change_guard();
			$row = self::request_subscription( 'change_plan' );
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- wp_easycart_subscription_change_guard() checked the nonce first.
			$preview = self::preview( (int) $row->subscription_id, isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0, self::request_quantity( $row ) );
			if ( is_wp_error( $preview ) ) {
				wp_send_json_error( array( 'message' => self::text( 'subscription_change_error' ) ) );
			}
			if ( 'none' === $preview['when'] ) {
				wp_send_json_error( array( 'message' => self::text( 'subscription_change_unavailable' ) ) );
			}
			wp_send_json_success(
				array(
					'when'    => $preview['when'],
					'message' => $preview['message'],
					'confirm' => self::text( 'subscription_change_confirm' ),
					'back'    => self::text( 'subscription_change_back' ),
				)
			);
		}

		/** AJAX: make the change. */
		public static function ajax_confirm() {
			wp_easycart_subscription_change_guard();
			$row    = self::request_subscription( 'change_plan' );
			$result = self::request(
				(int) $row->subscription_id,
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- wp_easycart_subscription_change_guard() checked the nonce first.
				isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0,
				self::request_quantity( $row ),
				array(
					'source' => 'customer',
					'user'   => isset( $GLOBALS['ec_user'] ) ? $GLOBALS['ec_user'] : null,
				)
			);
			if ( isset( $GLOBALS['ec_cart_data'] ) && is_object( $GLOBALS['ec_cart_data'] ) ) {
				$GLOBALS['ec_cart_data']->save_session_to_db();
			}
			if ( in_array( $result['state'], array( 'applied', 'scheduled' ), true ) ) {
				wp_send_json_success(
					array(
						'state' => $result['state'],
						'url'   => self::page_url( (int) $row->subscription_id, $result['state'] ),
					)
				);
			}
			if ( 'action' === $result['state'] ) {
				wp_send_json_success(
					array(
						'state'         => 'action',
						'client_secret' => $result['client_secret'],
						'key'           => self::publishable_key(),
					)
				);
			}
			$keys = array(
				'declined' => 'subscription_change_declined',
				'refused'  => 'subscription_change_unavailable',
			);
			wp_send_json_error( array( 'message' => self::text( isset( $keys[ $result['state'] ] ) ? $keys[ $result['state'] ] : 'subscription_change_error' ) ) );
		}

		/** AJAX: cancel the open change. */
		public static function ajax_cancel() {
			wp_easycart_subscription_change_guard();
			$row  = self::request_subscription( 'own' );
			$open = self::open( (int) $row->subscription_id );
			if ( $open && self::PRICE_SOURCE === (string) $open->change_source ) {
				/* The store's price move: the customer's choice is to keep the subscription or cancel it. */
				wp_send_json_error( array( 'message' => self::text( 'subscription_change_unavailable' ) ) );
			}
			$cancelled = self::cancel( (int) $row->subscription_id, 'cancelled' );
			if ( is_wp_error( $cancelled ) ) {
				wp_send_json_error( array( 'message' => self::text( 'subscription_change_error' ) ) );
			}
			wp_send_json_success( array( 'url' => self::page_url( (int) $row->subscription_id, 'cancelled' ) ) );
		}

		/** AJAX: after 3-D Secure, ask Stripe whether the change applied. */
		public static function ajax_sync() {
			global $wpdb;
			wp_easycart_subscription_change_guard();
			$row    = self::request_subscription( 'own' );
			$stripe = self::stripe();
			$state  = 'none';
			if ( $stripe ) {
				$live  = $stripe->api( 'GET', 'subscriptions/' . rawurlencode( (string) $row->stripe_subscription_id ), array() );
				$state = is_wp_error( $live ) ? 'none' : self::reconcile( $live );
			}
			if ( 'failed' === $state ) {
				wp_send_json_error( array( 'message' => self::text( 'subscription_change_declined' ) ) );
			}
			wp_send_json_success(
				array(
					'state' => $state,
					'url'   => self::page_url( (int) $row->subscription_id, ( 'applied' === $state ) ? 'applied' : 'waiting' ),
				)
			);
		}

		/**
		 * The publishable key Stripe.js uses on the account page ( 3-D Secure on a change charged at once ).
		 *
		 * @return string
		 */
		public static function publishable_key() {
			if ( 'stripe' === get_option( 'ec_option_payment_process_method' ) ) {
				return (string) get_option( 'ec_option_stripe_public_api_key' );
			}
			$key = get_option( 'ec_option_stripe_connect_use_sandbox' ) ? get_option( 'ec_option_stripe_connect_sandbox_publishable_key' ) : get_option( 'ec_option_stripe_connect_production_publishable_key' );
			return (string) apply_filters( 'wp_easycart_stripe_connect_publishable_key', $key );
		}
	}

	wp_easycart_subscription_changes::init();

endif;

if ( ! function_exists( 'wp_easycart_subscription_change_guard' ) ) {
	/**
	 * Plain nonce guard for the My Account plan change AJAX ( 6.0.3 ): the session nonce the plan panel already prints for Save
	 * ( wp-easycart-get-stripe-update-customer-card-{session} ), so template copies from before 6.0.3 work too.
	 */
	function wp_easycart_subscription_change_guard() {
		if ( function_exists( 'wpeasycart_session' ) ) {
			wpeasycart_session()->handle_session();
		}
		$session = ( isset( $GLOBALS['ec_cart_data'] ) && is_object( $GLOBALS['ec_cart_data'] ) ) ? (string) $GLOBALS['ec_cart_data']->ec_cart_id : '';
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wp-easycart-get-stripe-update-customer-card-' . $session ) ) {
			wp_send_json_error( array( 'message' => wp_easycart_subscription_changes::text( 'subscription_change_error' ) ), 403 );
		}
	}
}
