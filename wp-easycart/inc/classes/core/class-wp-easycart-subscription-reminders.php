<?php
/**
 * Subscription renewal and trial reminders ( 6.0.3, plan tables Phase 1b, D12 ).
 *
 * Two emails, Free and on by default, for every Stripe subscription whatever its plan and whatever the WP EasyCart PRO licence
 * ( a lapsed licence keeps subscribers billing, so their reminders keep going too ):
 *
 * - Renewal reminder: N days ( 30 ) before a subscription billed yearly or less often renews, with the date, the price and a link to
 *   cancel. 30 days falls inside every US state window for yearly terms ( CA, NY 15–45, CO 25–40, MA 5–30, UT 30–60 ).
 * - Trial reminder: N days ( 7 ) before a free trial turns into a paid subscription, with the date, the price and a link to cancel
 *   ( Visa ). A trial no longer than N days keeps Stripe's "trial ending in 3 days" email ( customer.subscription.trial_will_end ),
 *   which now skips a trial whose reminder already went out ( webhook_trial_notice() ).
 *
 * A daily WP-Cron job ( CRON, 9:00 site time ) lists the subscriptions whose next payment falls inside a window, asks Stripe for
 * each one before writing ( status, trial end, period end, cancel at period end, the price and a lasting discount ), and records
 * what it sent on the subscription: ec_subscription.renewal_reminder_for / trial_reminder_for hold the date of the renewal or
 * trial end the reminder was for ( "Y-m-d" ), or "x" + that date when Stripe said there was nothing to remind about. The column is
 * claimed with a conditional UPDATE before the email goes out, so two runs never send the same reminder. Columns from
 * EC_UPGRADE_DB 121 ( ready() ).
 *
 * Settings › Email › Subscription reminders: ec_option_subscription_renewal_reminder ( '1' ), _renewal_reminder_days ( 30 ),
 * ec_option_subscription_trial_reminder ( '1' ), _trial_reminder_days ( 7 ). Emails: ec_cart_subscription_renewal_reminder_email.php
 * and ec_cart_subscription_trial_reminder_email.php ( copyable to wp-easycart-data ), language sections subscription_upcoming
 * ( renewal_reminder_* ) and subscription_trial ( trial_reminder_* ), email log types subscription_renewal_reminder /
 * subscription_trial_reminder.
 *
 * @package wp-easycart
 * @since 6.0.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_subscription_reminders' ) ) :

	/**
	 * Renewal and trial reminders.
	 */
	class wp_easycart_subscription_reminders {

		/** The daily job. */
		const CRON = 'wp_easycart_subscription_reminders';

		/** What the last runs did ( option, not autoloaded ). */
		const STATE = 'wp_easycart_subscription_reminders_state';

		/** MySQL lock held while a run works. */
		const LOCK = 'wpec_subscription_reminders';

		/** Emails per run; the rest go an hour later. */
		const PER_RUN = 100;

		/** Hooks. */
		public static function init() {
			add_action( self::CRON, array( __CLASS__, 'cron' ) );
			add_action( 'init', array( __CLASS__, 'schedule' ), 30 );
		}

		/**
		 * The reminder columns exist ( EC_UPGRADE_DB 121 ).
		 *
		 * @return bool
		 */
		public static function ready() {
			return (int) get_option( 'ec_option_db_new_version' ) >= 121;
		}

		/**
		 * The settings, with their limits applied.
		 *
		 * @return array { renewal: bool, renewal_days: int, trial: bool, trial_days: int }
		 */
		public static function settings() {
			$settings = array(
				'renewal'      => '0' !== (string) get_option( 'ec_option_subscription_renewal_reminder', '1' ),
				'renewal_days' => self::clamp( get_option( 'ec_option_subscription_renewal_reminder_days', 30 ), 5, 60, 30 ),
				'trial'        => '0' !== (string) get_option( 'ec_option_subscription_trial_reminder', '1' ),
				'trial_days'   => self::clamp( get_option( 'ec_option_subscription_trial_reminder_days', 7 ), 7, 30, 7 ),
			);
			/**
			 * The reminder settings.
			 *
			 * @since 6.0.3
			 * @param array $settings renewal, renewal_days, trial, trial_days.
			 */
			$settings = (array) apply_filters( 'wp_easycart_subscription_reminder_settings', $settings );
			return array(
				'renewal'      => ! empty( $settings['renewal'] ),
				'renewal_days' => self::clamp( isset( $settings['renewal_days'] ) ? $settings['renewal_days'] : 30, 1, 90, 30 ),
				'trial'        => ! empty( $settings['trial'] ),
				'trial_days'   => self::clamp( isset( $settings['trial_days'] ) ? $settings['trial_days'] : 7, 1, 90, 7 ),
			);
		}

		/**
		 * A whole number of days inside limits.
		 *
		 * @param mixed $value   Value.
		 * @param int   $min     Lowest.
		 * @param int   $max     Highest.
		 * @param int   $fallback When it is not a number.
		 * @return int
		 */
		private static function clamp( $value, $min, $max, $fallback ) {
			if ( ! is_numeric( $value ) ) {
				return $fallback;
			}
			return max( $min, min( $max, (int) $value ) );
		}

		/* ------------------------------------------------------------------ schedule */

		/**
		 * Action init: the daily job runs while a reminder is on.
		 */
		public static function schedule() {
			$settings = self::settings();
			$on       = self::ready() && ( $settings['renewal'] || $settings['trial'] );
			$next     = wp_next_scheduled( self::CRON );
			if ( $on && ! $next ) {
				wp_schedule_event( self::first_run(), 'daily', self::CRON );
			} elseif ( ! $on && $next ) {
				wp_clear_scheduled_hook( self::CRON );
			}
		}

		/**
		 * The next 9:00 in the store's time zone, so reminders arrive in the morning.
		 *
		 * @return int
		 */
		public static function first_run() {
			if ( ! function_exists( 'wp_timezone' ) ) {
				return time() + HOUR_IN_SECONDS;
			}
			$at = new DateTime( 'now', wp_timezone() );
			$at->setTime( 9, 0, 0 );
			if ( $at->getTimestamp() <= time() + 300 ) {
				$at->modify( '+1 day' );
			}
			return $at->getTimestamp();
		}

		/**
		 * The cron job.
		 */
		public static function cron() {
			self::run();
		}

		/* ------------------------------------------------------------------ the run */

		/**
		 * Send the reminders that are due.
		 *
		 * @param int $limit Emails at most ( 0 = PER_RUN ).
		 * @return array { renewal, trial, skipped, retry, moved, more, locked }
		 */
		public static function run( $limit = 0 ) {
			global $wpdb;
			$result   = array(
				'renewal' => 0,
				'trial'   => 0,
				'skipped' => 0,
				'retry'   => 0,
				'moved'   => 0,
				'more'    => false,
				'locked'  => false,
			);
			$settings = self::settings();
			if ( ! self::ready() || ( ! $settings['renewal'] && ! $settings['trial'] ) ) {
				return $result;
			}
			if ( 1 !== (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK( %s, 0 )', self::LOCK ) ) ) {
				$result['locked'] = true;
				return $result;
			}
			$limit = ( (int) $limit > 0 ) ? (int) $limit : self::PER_RUN;
			foreach ( self::due( $settings ) as $item ) {
				if ( $result['renewal'] + $result['trial'] >= $limit ) {
					$result['more'] = true;
					break;
				}
				$outcome = self::process( $item, $settings );
				if ( isset( $result[ $outcome ] ) ) {
					++$result[ $outcome ];
				}
			}
			$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', self::LOCK ) );
			if ( $result['more'] ) {
				wp_schedule_single_event( time() + HOUR_IN_SECONDS, self::CRON );
			}
			self::record_run( $result );
			return $result;
		}

		/**
		 * The reminders due now, before Stripe is asked.
		 *
		 * @param array|null $settings settings().
		 * @param int|null   $now      Time.
		 * @return array[] Each { row, kind ( renewal | trial ), at, key }.
		 */
		public static function due( $settings = null, $now = null ) {
			global $wpdb;
			$settings = is_array( $settings ) ? $settings : self::settings();
			$now      = ( null === $now ) ? time() : (int) $now;
			$out      = array();
			$after    = 0;
			do {
				$rows = (array) $wpdb->get_results(
					$wpdb->prepare(
						"SELECT s.subscription_id, s.subscription_status, s.user_id, s.email, s.first_name, s.last_name, s.title, s.product_id, s.price, s.quantity, s.payment_length, s.payment_period, s.payment_duration, s.number_payments_completed, s.next_payment_date, s.stripe_subscription_id, s.renewal_reminder_for, s.trial_reminder_for, UNIX_TIMESTAMP( s.start_date ) AS start_ts, IFNULL( p.trial_period_days, 0 ) AS product_trial_days FROM ec_subscription s LEFT JOIN ec_product p ON p.product_id = s.product_id WHERE s.subscription_id > %d AND s.subscription_type = 'stripe' AND s.stripe_subscription_id != '' AND s.subscription_status IN ( 'Active', 'trialing' ) ORDER BY s.subscription_id ASC LIMIT 500",
						$after
					)
				);
				foreach ( $rows as $row ) {
					$after = (int) $row->subscription_id;
					$item  = self::due_item( $row, $settings, $now );
					if ( $item ) {
						$out[] = $item;
					}
				}
				$batch = count( $rows );
			} while ( 500 === $batch );
			return $out;
		}

		/**
		 * Whether one subscription has a reminder due.
		 *
		 * @param object $row      Subscription ( due()'s columns ).
		 * @param array  $settings settings().
		 * @param int    $now      Time.
		 * @return array|null { row, kind, at, key }
		 */
		public static function due_item( $row, $settings, $now ) {
			$at = self::timestamp( $row->next_payment_date );
			if ( $at <= $now ) {
				return null;
			}
			$key   = gmdate( 'Y-m-d', $at );
			$trial = self::in_trial( $row, $at );
			if ( $trial ) {
				if ( ! $settings['trial'] || $at - $now > $settings['trial_days'] * DAY_IN_SECONDS || self::handled( $row->trial_reminder_for, $key ) ) {
					return null;
				}
				/* A trial no longer than the notice keeps Stripe's own "ending in 3 days" email. */
				if ( (int) $row->start_ts > 0 && $at - (int) $row->start_ts <= $settings['trial_days'] * DAY_IN_SECONDS ) {
					return null;
				}
				return array(
					'row'  => $row,
					'kind' => 'trial',
					'at'   => $at,
					'key'  => $key,
				);
			}
			if ( ! $settings['renewal'] || 'Active' !== (string) $row->subscription_status || $at - $now > $settings['renewal_days'] * DAY_IN_SECONDS || self::handled( $row->renewal_reminder_for, $key ) ) {
				return null;
			}
			if ( (int) $row->payment_duration > 0 && (int) $row->number_payments_completed >= (int) $row->payment_duration ) {
				return null; /* the last payment of a fixed number was made: it does not renew */
			}
			/**
			 * Whether a subscription gets a renewal reminder ( default: billed yearly or less often ).
			 *
			 * @since 6.0.3
			 * @param bool   $applies Default from its billing period.
			 * @param object $row     The subscription.
			 */
			if ( ! apply_filters( 'wp_easycart_subscription_renewal_reminder_applies', self::yearly( $row->payment_length, $row->payment_period ), $row ) ) {
				return null;
			}
			return array(
				'row'  => $row,
				'kind' => 'renewal',
				'at'   => $at,
				'key'  => $key,
			);
		}

		/**
		 * The reminder for this date was sent, or Stripe said there was none to send.
		 *
		 * @param string $stored The column.
		 * @param string $key    Y-m-d.
		 * @return bool
		 */
		private static function handled( $stored, $key ) {
			return in_array( (string) $stored, array( $key, 'x' . $key ), true );
		}

		/**
		 * The subscription is in its free trial: Stripe's status says so ( PRO's sync ), or the product has a trial, nothing
		 * has been paid yet and the first period is as long as the trial.
		 *
		 * @param object $row Subscription.
		 * @param int    $at  Its next payment.
		 * @return bool
		 */
		public static function in_trial( $row, $at ) {
			if ( 'trialing' === (string) $row->subscription_status ) {
				return true;
			}
			$days = (int) $row->product_trial_days;
			if ( $days <= 0 || (int) $row->number_payments_completed > 1 || (int) $row->start_ts <= 0 ) {
				return false;
			}
			return abs( ( $at - (int) $row->start_ts ) - $days * DAY_IN_SECONDS ) <= 2 * DAY_IN_SECONDS;
		}

		/**
		 * Billed yearly or less often.
		 *
		 * @param int    $length Every N.
		 * @param string $period D | W | M | Y.
		 * @return bool
		 */
		public static function yearly( $length, $period ) {
			$length = max( 1, (int) $length );
			$per    = array(
				'D' => 365,
				'W' => 52,
				'M' => 12,
				'Y' => 1,
			);
			$period = strtoupper( substr( (string) $period, 0, 1 ) );
			return isset( $per[ $period ] ) && $length >= $per[ $period ];
		}

		/**
		 * A stored next payment date ( epoch seconds, or a DATETIME string from PRO's sync ) as a timestamp.
		 *
		 * @param string $value Stored value.
		 * @return int 0 when it is not a date.
		 */
		public static function timestamp( $value ) {
			$value = trim( (string) $value );
			if ( '' === $value ) {
				return 0;
			}
			if ( ctype_digit( $value ) ) {
				return (int) $value;
			}
			$time = strtotime( $value . ( preg_match( '/[zZ]|[+-]\d\d:?\d\d$/', $value ) ? '' : ' UTC' ) );
			return ( false === $time ) ? 0 : (int) $time;
		}

		/**
		 * Ask Stripe, then send one reminder.
		 *
		 * @param array $item     due_item().
		 * @param array $settings settings().
		 * @return string renewal | trial | skipped | retry | moved
		 */
		private static function process( $item, $settings ) {
			global $wpdb;
			$row    = $item['row'];
			$column = ( 'trial' === $item['kind'] ) ? 'trial_reminder_for' : 'renewal_reminder_for';
			$check  = self::confirm( $item );
			if ( 'retry' === $check['state'] ) {
				return 'retry';
			}
			if ( 'skip' === $check['state'] ) {
				self::claim( (int) $row->subscription_id, $column, (string) $row->{$column}, 'x' . $item['key'] );
				return 'skipped';
			}
			if ( 'moved' === $check['state'] ) {
				/* Stripe's date wins: keep it, and remind now only when the new date is due too. */
				$wpdb->update( 'ec_subscription', array( 'next_payment_date' => (string) (int) $check['at'] ), array( 'subscription_id' => (int) $row->subscription_id ) );
				$row->next_payment_date = (string) (int) $check['at'];
				$again                  = self::due_item( $row, $settings, time() );
				if ( ! $again || $again['kind'] !== $item['kind'] ) {
					return 'moved';
				}
				$item = $again;
			}
			$key = gmdate( 'Y-m-d', (int) $check['at'] );
			if ( ! self::claim( (int) $row->subscription_id, $column, (string) $row->{$column}, $key ) ) {
				return 'skipped'; /* another run sent it */
			}
			if ( ! self::send( $item['kind'], $row, (int) $check['at'], $check['amount'] ) ) {
				self::claim( (int) $row->subscription_id, $column, $key, (string) $row->{$column} );
				return 'retry';
			}
			/**
			 * A subscription reminder was sent.
			 *
			 * @since 6.0.3
			 * @param int    $subscription_id Subscription.
			 * @param string $kind            renewal | trial.
			 * @param int    $at              The renewal or the trial end ( timestamp ).
			 */
			do_action( 'wp_easycart_subscription_reminder_sent', (int) $row->subscription_id, $item['kind'], (int) $check['at'] );
			self::remember( (int) $row->subscription_id, $item['kind'], (int) $check['at'] );
			return $item['kind'];
		}

		/**
		 * Set a reminder column only while it still holds what was read ( so a reminder is sent once ).
		 *
		 * @param int    $subscription_id Subscription.
		 * @param string $column          renewal_reminder_for | trial_reminder_for.
		 * @param string $from            What it held.
		 * @param string $to              What it holds now.
		 * @return bool
		 */
		private static function claim( $subscription_id, $column, $from, $to ) {
			global $wpdb;
			if ( ! in_array( $column, array( 'renewal_reminder_for', 'trial_reminder_for' ), true ) ) {
				return false;
			}
			return 1 === (int) $wpdb->query( $wpdb->prepare( "UPDATE ec_subscription SET `{$column}` = %s WHERE subscription_id = %d AND `{$column}` = %s", $to, (int) $subscription_id, $from ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- the column is one of two fixed names.
		}

		/**
		 * What Stripe says about a due reminder.
		 *
		 * @param array $item due_item().
		 * @return array { state: send | skip | retry | moved, at, amount }
		 */
		public static function confirm( $item ) {
			global $wpdb;
			$row  = $item['row'];
			$none = array(
				'state'  => 'retry',
				'at'     => 0,
				'amount' => null,
			);
			/**
			 * The live subscription for a reminder ( null = ask Stripe ). Return an object shaped like Stripe's.
			 *
			 * @since 6.0.3
			 * @param object|null $live Subscription.
			 * @param object      $row  ec_subscription row.
			 * @param string      $kind renewal | trial.
			 */
			$live = apply_filters( 'wp_easycart_subscription_reminder_live', null, $row, $item['kind'] );
			if ( null === $live ) {
				$stripe = class_exists( 'wp_easycart_subscription_prices' ) ? wp_easycart_subscription_prices::gateway() : false;
				if ( ! $stripe ) {
					return $none;
				}
				$customer = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT stripe_customer_id FROM ec_user WHERE user_id = %d', (int) $row->user_id ) );
				if ( '' === $customer ) {
					return $none;
				}
				$live = $stripe->get_subscription( $customer, (string) $row->stripe_subscription_id );
			}
			if ( ! is_object( $live ) || empty( $live->status ) ) {
				return $none;
			}
			$status = (string) $live->status;
			$cancel = isset( $live->cancel_at ) && (int) $live->cancel_at > 0 ? (int) $live->cancel_at : 0;
			$skip   = array(
				'state'  => 'skip',
				'at'     => 0,
				'amount' => null,
			);
			if ( ! empty( $live->cancel_at_period_end ) ) {
				return $skip;
			}
			if ( 'trial' === $item['kind'] ) {
				if ( 'trialing' !== $status || empty( $live->trial_end ) ) {
					return $skip;
				}
				$at = (int) $live->trial_end;
			} else {
				if ( 'active' !== $status ) {
					return $skip;
				}
				$at = self::period_end( $live );
				if ( $at <= 0 ) {
					return $none;
				}
			}
			if ( $cancel > 0 && $cancel <= $at ) {
				return $skip; /* it ends instead of renewing */
			}
			return array(
				'state'  => ( abs( $at - (int) $item['at'] ) > DAY_IN_SECONDS ) ? 'moved' : 'send',
				'at'     => $at,
				'amount' => self::live_amount( $live, $at ),
			);
		}

		/**
		 * When the current period ends ( on the subscription before Stripe's 2025-03-31 API, on its items after ).
		 *
		 * @param object $live Stripe subscription.
		 * @return int
		 */
		private static function period_end( $live ) {
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
		 * What the next payment comes to: each item's price times its quantity, less a discount that lasts to that date.
		 * Tax is left out ( it depends on the address on the day ).
		 *
		 * @param object $live Stripe subscription.
		 * @param int    $at   The payment date.
		 * @return float|null Null when Stripe's answer has no prices.
		 */
		private static function live_amount( $live, $at ) {
			if ( ! isset( $live->items->data ) || ! is_array( $live->items->data ) || ! $live->items->data ) {
				return null;
			}
			$currency = isset( $live->currency ) ? strtolower( (string) $live->currency ) : '';
			$divisor  = in_array( $currency, array( 'bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf' ), true ) ? 1 : 100;
			$total    = 0;
			foreach ( $live->items->data as $item ) {
				$price = isset( $item->price ) && is_object( $item->price ) ? $item->price : ( isset( $item->plan ) && is_object( $item->plan ) ? $item->plan : null );
				if ( ! $price ) {
					return null;
				}
				$unit = isset( $price->unit_amount ) ? $price->unit_amount : ( isset( $price->amount ) ? $price->amount : null );
				if ( ! is_numeric( $unit ) ) {
					return null;
				}
				$total += (float) $unit * max( 1, isset( $item->quantity ) ? (int) $item->quantity : 1 );
			}
			$discount = ( isset( $live->discount ) && is_object( $live->discount ) ) ? $live->discount : null;
			if ( $discount && isset( $discount->coupon ) && is_object( $discount->coupon ) && ( empty( $discount->end ) || (int) $discount->end > $at ) ) {
				if ( ! empty( $discount->coupon->percent_off ) ) {
					$total = $total * ( 1 - (float) $discount->coupon->percent_off / 100 );
				} elseif ( ! empty( $discount->coupon->amount_off ) && ( empty( $discount->coupon->currency ) || strtolower( (string) $discount->coupon->currency ) === $currency ) ) {
					$total = max( 0, $total - (float) $discount->coupon->amount_off );
				}
			}
			return round( $total / $divisor, 2 );
		}

		/* ------------------------------------------------------------------ the emails */

		/**
		 * Send one reminder.
		 *
		 * @param string     $kind   renewal | trial.
		 * @param object     $row    Subscription.
		 * @param int        $at     The renewal or the trial end.
		 * @param float|null $amount The next payment ( null = the stored price × quantity ).
		 * @return bool Sent, or queued for another try.
		 */
		public static function send( $kind, $row, $at, $amount = null ) {
			global $wpdb;
			$to = trim( (string) $row->email );
			if ( ! is_email( $to ) && (int) $row->user_id > 0 ) {
				$to = trim( (string) $wpdb->get_var( $wpdb->prepare( 'SELECT email FROM ec_user WHERE user_id = %d', (int) $row->user_id ) ) );
			}
			if ( ! is_email( $to ) ) {
				return true; /* nobody to tell: counted as done, so it is not tried every day */
			}
			$built = self::build( $kind, $row, $at, $amount );
			if ( '' === $built['html'] ) {
				return false;
			}
			if ( class_exists( 'ec_email' ) && method_exists( 'ec_email', 'send' ) ) {
				return ec_email::send(
					$to,
					$built['subject'],
					$built['html'],
					array(
						'type'    => ( 'price' === $kind ) ? 'subscription_price_change' : 'subscription_' . $kind . '_reminder',
						'channel' => 'order',
					)
				);
			}
			$from = stripslashes( (string) get_option( 'ec_option_order_from_email' ) );
			return (bool) wp_mail( $to, $built['subject'], $built['html'], array( 'MIME-Version: 1.0', 'Content-Type: text/html; charset=utf-8', 'From: ' . $from, 'Reply-To: ' . $from ) );
		}

		/**
		 * Build one reminder: its subject and its HTML from the template.
		 *
		 * @param string     $kind   renewal | trial | price ( 6.0.3 Phase 3: the notice before a price change, from
		 *                           wp_easycart_subscription_changes::move_price(); the stored price is the one paid now ).
		 * @param object     $row    Subscription ( subscription_id, title, first_name, price, quantity, payment_length,
		 *                           payment_period, payment_duration ).
		 * @param int        $at     The renewal, the trial end, or the renewal the new price starts.
		 * @param float|null $amount The next payment ( null = the stored price × quantity ); the new price for a price change.
		 * @return array { subject, html }
		 */
		public static function build( $kind, $row, $at, $amount = null ) {
			$kind     = in_array( $kind, array( 'trial', 'price' ), true ) ? $kind : 'renewal';
			$field    = function ( $name, $fallback = '' ) use ( $row ) {
				return ( is_object( $row ) && isset( $row->{$name} ) ) ? $row->{$name} : $fallback;
			};
			$amount   = ( null === $amount ) ? round( (float) $field( 'price', 0 ) * max( 1, (int) $field( 'quantity', 1 ) ), 2 ) : (float) $amount;
			$money    = isset( $GLOBALS['currency'] ) && is_object( $GLOBALS['currency'] ) ? (string) $GLOBALS['currency']->get_currency_display( $amount ) : number_format( $amount, 2 );
			$period   = function_exists( 'wp_easycart_subscription_period_text' ) ? wp_easycart_subscription_period_text( (int) $field( 'payment_length', 1 ), strtoupper( substr( (string) $field( 'payment_period', 'M' ), 0, 1 ) ) ) : '';
			$format   = (string) get_option( 'date_format', 'F j, Y' );
			$date     = function_exists( 'wp_date' ) ? (string) wp_date( $format, (int) $at ) : (string) date_i18n( $format, (int) $at + (int) ( (float) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
			$link     = function_exists( 'wpeasycart_links' ) ? (string) wpeasycart_links()->get_account_page( 'subscription_details', array( 'subscription_id' => (int) $field( 'subscription_id', 0 ) ) ) : '';
			$section  = ( 'trial' === $kind ) ? 'subscription_trial' : 'subscription_upcoming';
			$old      = round( (float) $field( 'price', 0 ) * max( 1, (int) $field( 'quantity', 1 ) ), 2 );
			$old_text = isset( $GLOBALS['currency'] ) && is_object( $GLOBALS['currency'] ) ? (string) $GLOBALS['currency']->get_currency_display( $old ) : number_format( $old, 2 );
			$reminder = array(
				'kind'            => $kind,
				'subscription_id' => (int) $field( 'subscription_id', 0 ),
				'title'           => wp_strip_all_tags( wp_unslash( (string) $field( 'title' ) ) ),
				'first_name'      => wp_strip_all_tags( wp_unslash( (string) $field( 'first_name' ) ) ),
				'date'            => $date,
				'at'              => (int) $at,
				'amount'          => $amount,
				'amount_text'     => $money,
				'period_text'     => $period,
				'price_text'      => $money . $period,
				'old_price_text'  => $old_text . $period, /* 6.0.3 Phase 3: what is paid now ( the price change notice ) */
				'link'            => $link,
			);
			/**
			 * The data a subscription reminder is built from.
			 *
			 * @since 6.0.3
			 * @param array  $reminder kind, subscription_id, title, first_name, date, at, amount, amount_text, period_text, price_text, link.
			 * @param object $row      The subscription.
			 */
			$reminder = (array) apply_filters( 'wp_easycart_subscription_reminder_data', $reminder, $row );

			$subject = self::text( $section, ( 'price' === $kind ) ? 'price_change_email_title' : $kind . '_reminder_email_title', $reminder );
			$file    = ( 'price' === $kind ) ? 'ec_cart_subscription_price_change_email.php' : 'ec_cart_subscription_' . $kind . '_reminder_email.php';
			$layout  = (string) get_option( 'ec_option_base_layout' );
			$path    = ( defined( 'EC_PLUGIN_DATA_DIRECTORY' ) && '' !== $layout && file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . $layout . '/' . $file ) ) ? EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . $layout . '/' . $file : EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout', 'base-responsive-v3' ) . '/' . $file;
			if ( ! file_exists( $path ) ) {
				$path = EC_PLUGIN_DIRECTORY . '/design/layout/base-responsive-v3/' . $file;
			}
			$email_logo_url = (string) get_option( 'ec_option_email_logo' );
			$store_page     = self::store_page();
			ob_start();
			include $path;
			$html = (string) ob_get_clean();
			return array(
				'subject' => wp_strip_all_tags( $subject ),
				'html'    => $html,
			);
		}

		/**
		 * A reminder phrase with its [title], [date], [price] and [days] filled in ( plain text, not escaped ).
		 *
		 * @param string $section  Language section.
		 * @param string $key      Phrase.
		 * @param array  $reminder build()'s data.
		 * @return string
		 */
		public static function text( $section, $key, $reminder ) {
			$text = function_exists( 'wp_easycart_language' ) ? (string) wp_easycart_language()->get_text( $section, $key ) : '';
			if ( '' === trim( $text ) ) {
				$text = self::fallback( $key );
			}
			return str_replace(
				array( '[title]', '[date]', '[price]', '[name]', '[old_price]' ),
				array( $reminder['title'], $reminder['date'], $reminder['price_text'], $reminder['first_name'], isset( $reminder['old_price_text'] ) ? $reminder['old_price_text'] : '' ),
				$text
			);
		}

		/**
		 * English for a phrase a store's language data does not have yet.
		 *
		 * @param string $key Phrase.
		 * @return string
		 */
		private static function fallback( $key ) {
			$en = array(
				'renewal_reminder_email_title' => 'Your subscription renews on [date]',
				'renewal_reminder_message'     => 'Your subscription to [title] renews automatically on [date].',
				'renewal_reminder_plan'        => 'Subscription',
				'renewal_reminder_date'        => 'Renews on',
				'renewal_reminder_price'       => 'Renewal price',
				'renewal_reminder_cancel'      => 'If you don’t want it to renew, cancel from your account before [date]. You can update your payment method there too.',
				'renewal_reminder_link'        => 'Manage your subscription',
				'trial_reminder_email_title'   => 'Your free trial ends on [date]',
				'trial_reminder_message'       => 'Your free trial of [title] ends on [date]. Your subscription then starts and your card is charged [price].',
				'trial_reminder_ends'          => 'Trial ends',
				'trial_reminder_then'          => 'Then',
				'trial_reminder_cancel'        => 'Not for you? Cancel from your account before [date] and you won’t be charged.',
				'trial_reminder_link'          => 'Manage your subscription',
				'price_change_email_title'     => 'The price of your subscription is changing',
				'price_change_message'         => 'From [date], your subscription to [title] costs [price] instead of [old_price].',
				'price_change_plan'            => 'Subscription',
				'price_change_old'             => 'Price now',
				'price_change_new'             => 'New price',
				'price_change_date'            => 'From',
				'price_change_cancel'          => 'Nothing changes before then. If you don’t want to continue at the new price, cancel from your account before [date].',
				'price_change_link'            => 'Manage your subscription',
			);
			return isset( $en[ $key ] ) ? $en[ $key ] : '';
		}

		/**
		 * The store page, for the email's logo link.
		 *
		 * @return string
		 */
		private static function store_page() {
			$page_id = get_option( 'ec_option_storepage' );
			if ( function_exists( 'icl_object_id' ) && defined( 'ICL_LANGUAGE_CODE' ) ) {
				$page_id = icl_object_id( $page_id, 'page', true, ICL_LANGUAGE_CODE );
			}
			$url = ( $page_id && function_exists( 'get_permalink' ) ) ? (string) get_permalink( $page_id ) : '';
			return ( '' !== $url ) ? $url : ( function_exists( 'home_url' ) ? home_url( '/' ) : '' );
		}

		/* ------------------------------------------------------------------ Stripe's trial ending notice */

		/**
		 * Stripe's customer.subscription.trial_will_end ( 3 days before a trial ends ): whether Stripe's notice should email the subscriber.
		 * Not when the trial reminder already went out for this trial; otherwise the trial is marked as reminded, so the daily
		 * run does not send a second email.
		 *
		 * @param object $row       ec_subscription row.
		 * @param int    $trial_end Stripe's trial end ( 0 = the stored next payment ).
		 * @return bool
		 */
		public static function webhook_trial_notice( $row, $trial_end = 0 ) {
			global $wpdb;
			if ( ! self::ready() || ! is_object( $row ) || empty( $row->subscription_id ) ) {
				return true;
			}
			/* ec_db::get_stripe_subscription() does not select the reminder column: read it here. */
			$stored = $wpdb->get_row( $wpdb->prepare( 'SELECT trial_reminder_for, next_payment_date FROM ec_subscription WHERE subscription_id = %d', (int) $row->subscription_id ) );
			if ( ! $stored ) {
				return true;
			}
			$at = ( (int) $trial_end > 0 ) ? (int) $trial_end : self::timestamp( $stored->next_payment_date );
			if ( $at <= 0 ) {
				return true;
			}
			$key = gmdate( 'Y-m-d', $at );
			if ( $key === (string) $stored->trial_reminder_for ) {
				return false;
			}
			self::claim( (int) $row->subscription_id, 'trial_reminder_for', (string) $stored->trial_reminder_for, $key );
			return true;
		}

		/* ------------------------------------------------------------------ the record */

		/**
		 * Keep the last sends ( for the settings page ).
		 *
		 * @param int    $subscription_id Subscription.
		 * @param string $kind            renewal | trial.
		 * @param int    $at              Renewal or trial end.
		 */
		private static function remember( $subscription_id, $kind, $at ) {
			$state           = self::state();
			$state['recent'] = array_slice(
				array_merge(
					array(
						array(
							'id'   => (int) $subscription_id,
							'kind' => $kind,
							'for'  => (int) $at,
							'sent' => time(),
						),
					),
					$state['recent']
				),
				0,
				10
			);
			update_option( self::STATE, $state, false );
		}

		/**
		 * Note a run.
		 *
		 * @param array $result run()'s answer.
		 */
		private static function record_run( $result ) {
			$state             = self::state();
			$state['last_run'] = time();
			$state['last']     = array(
				'renewal' => (int) $result['renewal'],
				'trial'   => (int) $result['trial'],
				'retry'   => (int) $result['retry'],
			);
			update_option( self::STATE, $state, false );
		}

		/**
		 * What the runs did.
		 *
		 * @return array { last_run, last { renewal, trial, retry }, recent [ { id, kind, for, sent } ] }
		 */
		public static function state() {
			$state = get_option( self::STATE, array() );
			$state = is_array( $state ) ? $state : array();
			return array(
				'last_run' => isset( $state['last_run'] ) ? (int) $state['last_run'] : 0,
				'last'     => ( isset( $state['last'] ) && is_array( $state['last'] ) ) ? $state['last'] : array(),
				'recent'   => ( isset( $state['recent'] ) && is_array( $state['recent'] ) ) ? array_values( $state['recent'] ) : array(),
			);
		}
	}

	wp_easycart_subscription_reminders::init();

endif;
