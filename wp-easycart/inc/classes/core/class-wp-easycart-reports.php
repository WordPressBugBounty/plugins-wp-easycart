<?php
/**
 * Reports ( 6.0.2 ): the engine behind EasyCart › Reports. Loaded on every request ( inc/ec_config.php ): the summary email
 * runs on WP-Cron, and storefront orders refresh the kept days.
 *
 * Rules, one set everywhere ( review D1 ):
 * - Sales belong to the day the order was placed, refunds to the day they were given, money received to the day it was
 *   paid ( ec_order_transaction, wp_easycart_order_ledger ).
 * - A sale is an order whose status counts as paid, or a refunded order ( status 16: it was a sale; its refund is counted
 *   on the refund's day ).
 * - Days are the store's calendar days in its time zone, daylight saving included ( day_sql(), bounds() ). Ranges never
 *   run past today.
 * - Customers are counted by customer_key ( one per email address, guests included ).
 *
 * Tabs: Overview and Taxes are Free ( D2 ). Products, Customers, Codes, Carts, Sources, Profit, Fulfillment, Searches and
 * Subscriptions are drawn by WP EasyCart PRO 6.0.2 through filter wp_easycart_reports_tab_data ( null, $tab, $args ); PRO
 * lists the tabs it serves in wp_easycart_reports_pro_tabs. A tab PRO does not serve shows the store's own top row and a
 * count, the rest blurred, under a lock ( teaser(), D3 ); an older PRO shows Update instead ( never an upsell ).
 *
 * Tab payload ( JSON for admin/js/reports-v2.js ): kpis [ key, label, value, display, prev, prev_display, change, good,
 * hint ], charts [ id, title, type line|bar|doughnut, money, labels, datasets [ label, data, compare ] ], tables [ id,
 * title, columns [ key, label, align, format money|int|pct|text ], rows, note, csv ], insights [ text ], notes [ text ].
 *
 * Helpers PRO uses: args(), bounds(), day_sql(), sale_join(), sale_where(), filter_sql(), money(), buckets(),
 * bucket_series(), kpi(), customer_expr().
 *
 * Also here: rollups ( ec_report_day, past days only, unfiltered ), the Free weekly summary email ( D4: offered with one
 * click, off until then; PRO adds daily / monthly and sections ), and lock-click counts.
 *
 * @since 6.0.2
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'ecv2_reports_guard' ) ) {
	/**
	 * Nonce and capability for every Reports AJAX call. Plain function so the nonce sniff credits it.
	 *
	 * @since 6.0.2
	 */
	function ecv2_reports_guard() {
		if ( ! check_ajax_referer( 'wp-easycart-ecv2-reports', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Reload the page and try again.', 'wp-easycart' ) ), 403 );
		}
		if ( ! current_user_can( 'wpec_reports' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to view reports.', 'wp-easycart' ) ), 403 );
		}
	}
}

if ( ! class_exists( 'wp_easycart_reports' ) ) :

	// phpcs:disable PEAR.NamingConventions.ValidClassName -- WP EasyCart class names are lower case.
	/**
	 * Reports engine.
	 *
	 * @since 6.0.2
	 */
	final class wp_easycart_reports {
		// phpcs:enable PEAR.NamingConventions.ValidClassName

		/** The WP EasyCart PRO that draws the Pro tabs. */
		const MIN_PRO = '6.0.2';

		/** Rollup rules; change when a day's numbers are worked out differently. */
		const RULES = '1';

		/** Rollup generation, bumped when an order changes in a way a day row cannot follow ( edits, deletes ). */
		const GENERATION_OPTION = 'wp_easycart_reports_rollup_gen';

		/** Summary email settings. */
		const SUMMARY_OPTION = 'wp_easycart_reports_summary';

		/** Summary email job ( daily at 8:00, sends when due ). */
		const SUMMARY_CRON = 'wp_easycart_reports_summary_send';

		/** Clicks on locked tabs, per tab. */
		const LOCK_OPTION = 'wp_easycart_reports_lock_clicks';

		/**
		 * Offsets of the time zone per clock, cached per request.
		 *
		 * @var array
		 */
		private static $transitions = array();

		/**
		 * The kept days' version, per request ( rollup_version() ).
		 *
		 * @var string|null
		 */
		private static $version = null;

		/** Register hooks. */
		public static function init() {
			add_action( 'wp_ajax_ecv2_reports_data', array( __CLASS__, 'ajax_data' ) );
			add_action( 'wp_ajax_ecv2_reports_summary', array( __CLASS__, 'ajax_summary' ) );
			add_action( 'wp_ajax_ecv2_reports_lock_click', array( __CLASS__, 'ajax_lock_click' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
			add_action( self::SUMMARY_CRON, array( __CLASS__, 'send_due_summary' ) );
			add_action( 'admin_init', array( __CLASS__, 'schedule' ) );
			foreach ( array( 'wpeasycart_order_inserted', 'wp_easycart_admin_order_created', 'wpeasycart_subscription_first_order_inserted' ) as $hook ) {
				add_action( $hook, array( __CLASS__, 'forget_order_day' ), 50, 1 );
			}
			foreach ( array( 'wpeasycart_order_updated', 'wpeasycart_order_deleted', 'wp_easycart_ecv2_order_duplicated', 'wp_easycart_admin_undo_restore_order' ) as $hook ) {
				add_action( $hook, array( __CLASS__, 'forget_rollups' ), 50, 0 );
			}
			add_action( 'wpeasycart_order_status_update', array( __CLASS__, 'forget_order_day' ), 50, 1 );
		}

		// ------------------------------------------------------------------
		// Tabs.
		// ------------------------------------------------------------------

		/**
		 * Every Reports tab, in order.
		 *
		 * @return array id => label, icon, plan ( '' | pro ), feature ( upsell feature key ), desc.
		 */
		public static function tabs() {
			$tabs = array(
				'overview'      => array(
					'label' => __( 'Overview', 'wp-easycart' ),
					'plan'  => '',
					'desc'  => __( 'Sales, orders, customers and money in and out', 'wp-easycart' ),
				),
				'products'      => array(
					'label'   => __( 'Products', 'wp-easycart' ),
					'plan'    => 'pro',
					'feature' => 'products',
					'desc'    => __( 'Views, add to cart, units, sales and stock cover per product', 'wp-easycart' ),
				),
				'customers'     => array(
					'label'   => __( 'Customers', 'wp-easycart' ),
					'plan'    => 'pro',
					'feature' => 'customers',
					'desc'    => __( 'New and returning customers, repeat rate, lifetime value and cohorts', 'wp-easycart' ),
				),
				'codes'         => array(
					'label'   => __( 'Codes', 'wp-easycart' ),
					'plan'    => 'pro',
					'feature' => 'coupons',
					'desc'    => __( 'Uses, discount given and sales for every coupon and offer code', 'wp-easycart' ),
				),
				'carts'         => array(
					'label'   => __( 'Carts', 'wp-easycart' ),
					'plan'    => 'pro',
					'feature' => 'abandoned',
					'desc'    => __( 'The checkout funnel, abandoned carts and what reminders brought back', 'wp-easycart' ),
				),
				'sources'       => array(
					'label'   => __( 'Sources', 'wp-easycart' ),
					'plan'    => 'pro',
					'feature' => 'sources',
					'desc'    => __( 'Visits, orders, sales and conversion by where shoppers came from', 'wp-easycart' ),
				),
				'profit'        => array(
					'label'   => __( 'Profit', 'wp-easycart' ),
					'plan'    => 'pro',
					'feature' => 'profit',
					'desc'    => __( 'Sales less product cost and discounts, per product', 'wp-easycart' ),
				),
				'fulfillment'   => array(
					'label'   => __( 'Fulfillment', 'wp-easycart' ),
					'plan'    => 'pro',
					'feature' => 'fulfillment',
					'desc'    => __( 'How long orders take from payment to shipping, and what is waiting', 'wp-easycart' ),
				),
				'searches'      => array(
					'label'   => __( 'Searches', 'wp-easycart' ),
					'plan'    => 'pro',
					'feature' => 'searches',
					'desc'    => __( 'What shoppers search for, and the searches that find nothing', 'wp-easycart' ),
				),
				'subscriptions' => array(
					'label'   => __( 'Subscriptions', 'wp-easycart' ),
					'plan'    => 'pro',
					'feature' => 'subscriptions',
					'desc'    => __( 'Monthly recurring revenue, new subscriptions and churn', 'wp-easycart' ),
				),
				'taxes'         => array(
					'label' => __( 'Taxes', 'wp-easycart' ),
					'plan'  => '',
					'desc'  => __( 'Tax collected by type and by place', 'wp-easycart' ),
				),
			);
			/**
			 * The Reports tabs ( id => label, plan, feature, desc ).
			 *
			 * @since 6.0.2
			 * @param array $tabs Tabs.
			 */
			return (array) apply_filters( 'wp_easycart_reports_tabs', $tabs );
		}

		/**
		 * The Pro tabs WP EasyCart PRO draws for this store now ( licensed ).
		 *
		 * @return string[]
		 */
		public static function pro_tabs() {
			/**
			 * The Pro Reports tabs WP EasyCart PRO serves ( licensed, 6.0.2 and later ).
			 *
			 * @since 6.0.2
			 * @param string[] $tabs Tab ids.
			 */
			return array_map( 'strval', (array) apply_filters( 'wp_easycart_reports_pro_tabs', array() ) );
		}

		/**
		 * A tab's state: free | enabled | upsell | update | license | inactive.
		 *
		 * @param string $tab Tab id.
		 * @return string
		 */
		public static function tab_state( $tab ) {
			$tabs = self::tabs();
			if ( ! isset( $tabs[ $tab ] ) ) {
				return 'upsell';
			}
			if ( empty( $tabs[ $tab ]['plan'] ) ) {
				return 'free';
			}
			if ( in_array( $tab, self::pro_tabs(), true ) ) {
				return 'enabled';
			}
			if ( ! class_exists( 'wp_easycart_admin_pro_gate' ) ) {
				return 'upsell';
			}
			$gate = wp_easycart_admin_pro_gate::evaluate( array( 'min_version' => self::MIN_PRO ) );
			/* Licensed and new enough but not serving the tab: an older build of PRO 6.0.2, or a tab added later. */
			return ( 'enabled' === $gate['state'] ) ? 'update' : $gate['state'];
		}

		// ------------------------------------------------------------------
		// Dates and the clock.
		// ------------------------------------------------------------------

		/**
		 * Today in the store's time zone.
		 *
		 * @return string Y-m-d.
		 */
		public static function today() {
			return current_time( 'Y-m-d' );
		}

		/**
		 * How far the database clock is ahead of UTC, in seconds.
		 *
		 * @return int
		 */
		public static function db_offset() {
			if ( class_exists( 'wp_easycart_order_ledger' ) ) {
				return wp_easycart_order_ledger::db_offset();
			}
			global $wpdb;
			return (int) $wpdb->get_var( 'SELECT TIMESTAMPDIFF( SECOND, UTC_TIMESTAMP(), NOW() )' );
		}

		/**
		 * The UTC moment a local day starts.
		 *
		 * @param string $date Y-m-d ( local ).
		 * @return int
		 */
		public static function day_start( $date ) {
			try {
				$time = new DateTime( substr( (string) $date, 0, 10 ) . ' 00:00:00', wp_timezone() );
				return (int) $time->getTimestamp();
			} catch ( Exception $e ) {
				return (int) strtotime( substr( (string) $date, 0, 10 ) . ' 00:00:00 UTC' );
			}
		}

		/**
		 * [ from, to ) for local days start…end ( inclusive ), on a column's clock.
		 *
		 * @param string $start Y-m-d.
		 * @param string $end   Y-m-d.
		 * @param string $clock db ( order_date, the order log, first_seen ) | utc ( the 6.0.2 columns ).
		 * @return array Two Y-m-d H:i:s strings.
		 */
		public static function bounds( $start, $end, $clock = 'db' ) {
			$shift = ( 'utc' === $clock ) ? 0 : self::db_offset();
			$from  = self::day_start( $start );
			$to    = self::day_start( gmdate( 'Y-m-d', strtotime( substr( (string) $end, 0, 10 ) . ' 12:00:00 UTC' ) + DAY_IN_SECONDS ) );
			return array( gmdate( 'Y-m-d H:i:s', $from + $shift ), gmdate( 'Y-m-d H:i:s', $to + $shift ) );
		}

		/**
		 * SQL that turns a datetime column into the store's local date, following daylight saving between start and end.
		 *
		 * @param string $column Column ( trusted SQL ).
		 * @param string $start  Y-m-d.
		 * @param string $end    Y-m-d.
		 * @param string $clock  db | utc.
		 * @return string
		 */
		public static function day_sql( $column, $start, $end, $clock = 'db' ) {
			$shift = ( 'utc' === $clock ) ? 0 : self::db_offset();
			$from  = self::day_start( $start ) - DAY_IN_SECONDS;
			$to    = self::day_start( $end ) + 2 * DAY_IN_SECONDS;
			$key   = $clock . '|' . $from . '|' . $to;
			if ( ! isset( self::$transitions[ $key ] ) ) {
				$zone  = wp_timezone();
				$steps = array();
				$list  = $zone->getTransitions( $from, $to );
				if ( is_array( $list ) && ! empty( $list ) ) {
					foreach ( $list as $i => $transition ) {
						$steps[] = array( ( 0 === $i ) ? null : (int) $transition['ts'], (int) $transition['offset'] );
					}
				} else {
					$steps[] = array( null, (int) $zone->getOffset( new DateTime( '@' . $from ) ) );
				}
				self::$transitions[ $key ] = $steps;
			}
			$steps = self::$transitions[ $key ];
			if ( 1 === count( $steps ) ) {
				return 'DATE( DATE_ADD( ' . $column . ', INTERVAL ' . (int) ( $steps[0][1] - $shift ) . ' SECOND ) )';
			}
			$case = 'CASE';
			$last = count( $steps ) - 1;
			for ( $i = 0; $i < $last; $i++ ) {
				$case .= ' WHEN ' . $column . " < '" . gmdate( 'Y-m-d H:i:s', $steps[ $i + 1 ][0] + $shift ) . "' THEN " . (int) ( $steps[ $i ][1] - $shift );
			}
			$case .= ' ELSE ' . (int) ( $steps[ $last ][1] - $shift ) . ' END';
			return 'DATE( DATE_ADD( ' . $column . ', INTERVAL ' . $case . ' SECOND ) )';
		}

		/**
		 * The report arguments from a request ( or an array ), checked: dates are Y-m-d, never after today, start before end,
		 * at most ten years; the compare range is '' or a checked range.
		 *
		 * @param array $source Raw values.
		 * @return array tab, start, end, start2, end2, range, product_id, country, billing_country, location_id.
		 */
		public static function args( $source ) {
			$today = self::today();
			$date  = function ( $value ) {
				$value = is_scalar( $value ) ? substr( trim( (string) $value ), 0, 10 ) : '';
				return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) && strtotime( $value . ' 12:00:00 UTC' ) ? $value : '';
			};
			$start = $date( isset( $source['start'] ) ? $source['start'] : '' );
			$end   = $date( isset( $source['end'] ) ? $source['end'] : '' );
			if ( '' === $end || $end > $today ) {
				$end = $today;
			}
			if ( '' === $start || $start > $end ) {
				$start = gmdate( 'Y-m-d', strtotime( $end . ' 12:00:00 UTC' ) - 29 * DAY_IN_SECONDS );
			}
			if ( strtotime( $end . ' 12:00:00 UTC' ) - strtotime( $start . ' 12:00:00 UTC' ) > 3653 * DAY_IN_SECONDS ) {
				$start = gmdate( 'Y-m-d', strtotime( $end . ' 12:00:00 UTC' ) - 3653 * DAY_IN_SECONDS );
			}
			$start2 = $date( isset( $source['start2'] ) ? $source['start2'] : '' );
			$end2   = $date( isset( $source['end2'] ) ? $source['end2'] : '' );
			if ( '' === $start2 || '' === $end2 || $start2 > $end2 || $end2 > $today ) {
				$start2 = '';
				$end2   = '';
			}
			$range = isset( $source['range'] ) ? sanitize_key( $source['range'] ) : '';
			if ( ! in_array( $range, array( 'daily', 'weekly', 'monthly', 'yearly' ), true ) ) {
				$days  = (int) round( ( strtotime( $end . ' 12:00:00 UTC' ) - strtotime( $start . ' 12:00:00 UTC' ) ) / DAY_IN_SECONDS ) + 1;
				$range = ( $days > 730 ) ? 'monthly' : ( ( $days > 92 ) ? 'weekly' : 'daily' );
			}
			$country = isset( $source['country'] ) ? strtoupper( sanitize_text_field( (string) $source['country'] ) ) : '';
			$billing = isset( $source['billing_country'] ) ? strtoupper( sanitize_text_field( (string) $source['billing_country'] ) ) : '';
			return array(
				'tab'             => isset( $source['tab'] ) ? sanitize_key( $source['tab'] ) : 'overview',
				'start'           => $start,
				'end'             => $end,
				'start2'          => $start2,
				'end2'            => $end2,
				'range'           => $range,
				'product_id'      => isset( $source['product'] ) ? max( 0, (int) $source['product'] ) : ( isset( $source['product_id'] ) ? max( 0, (int) $source['product_id'] ) : 0 ),
				'country'         => ( '0' === $country ) ? '' : substr( preg_replace( '/[^A-Z]/', '', $country ), 0, 3 ),
				'billing_country' => ( '0' === $billing ) ? '' : substr( preg_replace( '/[^A-Z]/', '', $billing ), 0, 3 ),
				'location_id'     => isset( $source['location_id'] ) ? max( 0, (int) $source['location_id'] ) : 0,
			);
		}

		/**
		 * Is any filter set ( product, country, place, location )?
		 *
		 * @param array $args args().
		 * @return bool
		 */
		public static function filtered( $args ) {
			return ! empty( $args['product_id'] ) || '' !== $args['country'] || '' !== $args['billing_country'] || ! empty( $args['location_id'] ) || '' !== self::filter_sql( $args, 'o', true );
		}

		// ------------------------------------------------------------------
		// SQL pieces.
		// ------------------------------------------------------------------

		/**
		 * Join the order status.
		 *
		 * @param string $alias Order alias.
		 * @return string
		 */
		public static function sale_join( $alias = 'o' ) {
			return 'INNER JOIN ec_orderstatus s ON s.status_id = ' . $alias . '.orderstatus_id';
		}

		/**
		 * A sale: status counts as paid, or refunded.
		 *
		 * @param string $alias Order alias.
		 * @return string
		 */
		public static function sale_where( $alias = 'o' ) {
			return '( s.is_approved = 1 OR ' . $alias . '.orderstatus_id = 16 )';
		}

		/**
		 * The customer an order belongs to ( its key, else its email ).
		 *
		 * @param string $alias Order alias.
		 * @return string
		 */
		public static function customer_expr( $alias = 'o' ) {
			if ( class_exists( 'wp_easycart_order_ledger' ) && wp_easycart_order_ledger::ready() ) {
				return "COALESCE( NULLIF( {$alias}.customer_key, '' ), LOWER( TRIM( {$alias}.user_email ) ) )";
			}
			return "LOWER( TRIM( {$alias}.user_email ) )";
		}

		/**
		 * The filters as SQL on an order alias ( AND … ).
		 *
		 * @param array  $args       args().
		 * @param string $alias      Order alias.
		 * @param bool   $extra_only Only what extensions add ( filter wp_easycart_reports_order_where ).
		 * @return string
		 */
		public static function filter_sql( $args, $alias = 'o', $extra_only = false ) {
			global $wpdb;
			$sql = '';
			if ( ! $extra_only ) {
				if ( ! empty( $args['product_id'] ) ) {
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $alias is a fixed table alias.
					$sql .= $wpdb->prepare( " AND EXISTS ( SELECT 1 FROM ec_orderdetail fpd WHERE fpd.order_id = {$alias}.order_id AND fpd.product_id = %d )", (int) $args['product_id'] );
				}
				if ( '' !== $args['country'] ) {
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $alias is a fixed table alias.
					$sql .= $wpdb->prepare( " AND {$alias}.shipping_country = %s", $args['country'] );
				}
				if ( '' !== $args['billing_country'] ) {
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $alias is a fixed table alias.
					$sql .= $wpdb->prepare( " AND {$alias}.billing_country = %s", $args['billing_country'] );
				}
				if ( ! empty( $args['location_id'] ) ) {
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $alias is a fixed table alias.
					$sql .= $wpdb->prepare( " AND {$alias}.location_id = %d", (int) $args['location_id'] );
				}
			}
			/**
			 * More conditions on the orders Reports counts ( " AND …", prepared ).
			 *
			 * @since 6.0.2
			 * @param string $sql   '' by default.
			 * @param array  $args  Report arguments.
			 * @param string $alias The ec_order alias.
			 */
			$extra = (string) apply_filters( 'wp_easycart_reports_order_where', '', $args, $alias );
			return $sql . $extra;
		}

		/**
		 * Money as the store shows it.
		 *
		 * @param float $amount Amount.
		 * @return string
		 */
		public static function money( $amount ) {
			if ( isset( $GLOBALS['currency'] ) && is_object( $GLOBALS['currency'] ) && method_exists( $GLOBALS['currency'], 'get_currency_display' ) ) {
				return (string) $GLOBALS['currency']->get_currency_display( (float) $amount, false );
			}
			return number_format_i18n( (float) $amount, 2 );
		}

		/**
		 * The store's money format for the page script.
		 *
		 * @return array
		 */
		public static function money_config() {
			if ( class_exists( 'wp_easycart_admin_order_screen' ) ) {
				return wp_easycart_admin_order_screen::money_config();
			}
			return array(
				'symbol'    => '$',
				'before'    => 1,
				'negBefore' => 1,
				'decimals'  => 2,
				'dec'       => '.',
				'group'     => ',',
				'code'      => '',
			);
		}

		/**
		 * One KPI for the payload.
		 *
		 * @param string     $key    Key.
		 * @param string     $label  Label.
		 * @param float|null $value  Value ( null = not available ).
		 * @param float|null $prev   Compare value ( null = none ).
		 * @param string     $format money | int | pct | hours | text.
		 * @param string     $good   up | down | '' ( which way is better ).
		 * @param string     $hint   One line under the value.
		 * @return array
		 */
		public static function kpi( $key, $label, $value, $prev = null, $format = 'money', $good = 'up', $hint = '' ) {
			$show   = function ( $number ) use ( $format ) {
				if ( null === $number ) {
					return '—';
				}
				switch ( $format ) {
					case 'money':
						return self::money( $number );
					case 'pct':
						return number_format_i18n( (float) $number, 1 ) . '%';
					case 'hours':
						return self::duration( (float) $number );
					case 'text':
						return (string) $number;
					case 'dec':
						return number_format_i18n( (float) $number, 2 );
					default:
						return number_format_i18n( (float) $number );
				}
			};
			$change = null;
			if ( null !== $value && null !== $prev && 'text' !== $format ) {
				if ( abs( (float) $prev ) >= 0.005 ) {
					$change = round( ( (float) $value - (float) $prev ) / abs( (float) $prev ) * 100, 1 );
				} elseif ( abs( (float) $value ) >= 0.005 ) {
					$change = null; /* from nothing: no percentage */
				} else {
					$change = 0.0;
				}
			}
			return array(
				'key'          => $key,
				'label'        => $label,
				'value'        => ( null === $value || 'text' === $format ) ? $value : round( (float) $value, 2 ),
				'display'      => $show( $value ),
				'prev'         => ( null === $prev || 'text' === $format ) ? $prev : round( (float) $prev, 2 ),
				'prev_display' => ( null === $prev ) ? '' : $show( $prev ),
				'change'       => $change,
				'good'         => $good,
				'format'       => $format,
				'hint'         => $hint,
			);
		}

		/**
		 * Hours as words ( 5 h, 2.5 days ).
		 *
		 * @param float $hours Hours.
		 * @return string
		 */
		public static function duration( $hours ) {
			if ( $hours < 1 ) {
				/* translators: %d: minutes. */
				return sprintf( __( '%d min', 'wp-easycart' ), max( 1, (int) round( $hours * 60 ) ) );
			}
			if ( $hours < 48 ) {
				/* translators: %s: hours. */
				return sprintf( __( '%s h', 'wp-easycart' ), number_format_i18n( $hours, $hours < 10 ? 1 : 0 ) );
			}
			/* translators: %s: days. */
			return sprintf( __( '%s days', 'wp-easycart' ), number_format_i18n( $hours / 24, 1 ) );
		}

		// ------------------------------------------------------------------
		// Totals.
		// ------------------------------------------------------------------

		/**
		 * The numbers for one range.
		 *
		 * @param string $start Y-m-d.
		 * @param string $end   Y-m-d.
		 * @param array  $args  args() ( filters ).
		 * @return array
		 */
		public static function totals( $start, $end, $args ) {
			global $wpdb;
			list( $from, $to )         = self::bounds( $start, $end, 'db' );
			list( $utc_from, $utc_to ) = self::bounds( $start, $end, 'utc' );
			$where                     = self::filter_sql( $args );
			$ledger                    = class_exists( 'wp_easycart_order_ledger' ) && wp_easycart_order_ledger::ready();
			$product                   = ! empty( $args['product_id'] );
			$customer                  = self::customer_expr( 'o' );
			$tax_sum                   = 'o.tax_total + o.duty_total + o.vat_total + o.gst_total + o.pst_total + o.hst_total';
			$gift                      = self::has_column( 'ec_order', 'giftcard_total' ) ? 'COALESCE( o.giftcard_total, 0 )' : '0';

			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $where comes from filter_sql() ( prepared ); the other pieces are fixed SQL.
			$row        = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT COUNT(*) AS orders, SUM( o.grand_total ) AS sales, SUM( o.shipping_total ) AS shipping, SUM( {$tax_sum} ) AS tax,
						SUM( o.discount_total - {$gift} ) AS discounts, SUM( {$gift} ) AS giftcards, SUM( o.tip_total ) AS tips,
						SUM( o.refund_total ) AS refunded_on_orders, COUNT( DISTINCT {$customer} ) AS customers
					FROM ec_order o " . self::sale_join() . ' WHERE o.order_date >= %s AND o.order_date < %s AND ' . self::sale_where() . $where,
					$from,
					$to
				)
			);
			$items      = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT SUM( d.quantity ) AS units, SUM( d.total_price ) AS line_sales, COUNT( DISTINCT d.order_id ) AS orders
					FROM ec_orderdetail d INNER JOIN ec_order o ON o.order_id = d.order_id ' . self::sale_join() . ' WHERE o.order_date >= %s AND o.order_date < %s AND ' . self::sale_where() . $where . ( $product ? $wpdb->prepare( ' AND d.product_id = %d', (int) $args['product_id'] ) : '' ),
					$from,
					$to
				)
			);
			$fees       = array();
			$fees_total = 0.0;
			if ( self::table_exists( 'ec_order_fee' ) && ! $product ) {
				foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT f.fee_label AS label, SUM( f.fee_total ) AS total FROM ec_order_fee f INNER JOIN ec_order o ON o.order_id = f.order_id ' . self::sale_join() . ' WHERE o.order_date >= %s AND o.order_date < %s AND ' . self::sale_where() . $where . ' GROUP BY f.fee_label ORDER BY total DESC', $from, $to ) ) as $fee ) {
					$label          = ( '' !== trim( (string) $fee->label ) && '0' !== trim( (string) $fee->label ) ) ? (string) $fee->label : __( 'Fee', 'wp-easycart' );
					$fees[ $label ] = ( isset( $fees[ $label ] ) ? $fees[ $label ] : 0 ) + (float) $fee->total;
					$fees_total    += (float) $fee->total;
				}
			}
			$refunds  = null;
			$received = null;
			if ( ! $product ) {
				if ( $ledger ) {
					$money    = $wpdb->get_row( $wpdb->prepare( "SELECT SUM( CASE WHEN t.txn_type = 'refund' THEN t.amount ELSE 0 END ) AS refunds, SUM( CASE WHEN t.txn_type = 'payment' THEN t.amount ELSE 0 END ) AS received FROM ec_order_transaction t INNER JOIN ec_order o ON o.order_id = t.order_id WHERE t.created_at >= %s AND t.created_at < %s" . $where, $utc_from, $utc_to ) );
					$refunds  = $money ? (float) $money->refunds : 0.0;
					$received = $money ? (float) $money->received : 0.0;
				} else {
					$refunds = $row ? (float) $row->refunded_on_orders : 0.0;
				}
			}
			$new = null;
			if ( $ledger ) {
				$new = (int) $wpdb->get_var(
					$wpdb->prepare(
						'SELECT COUNT( DISTINCT o.customer_key ) FROM ec_order o ' . self::sale_join() . ' WHERE o.order_date >= %s AND o.order_date < %s AND ' . self::sale_where() . " AND o.customer_key <> ''" . $where . '
						AND NOT EXISTS ( SELECT 1 FROM ec_order p INNER JOIN ec_orderstatus ps ON ps.status_id = p.orderstatus_id WHERE p.customer_key = o.customer_key AND p.order_date < %s AND ( ps.is_approved = 1 OR p.orderstatus_id = 16 ) )',
						$from,
						$to,
						$from
					)
				);
			}
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

			$orders = $product ? ( $items ? (int) $items->orders : 0 ) : ( $row ? (int) $row->orders : 0 );
			$sales  = $product ? ( $items ? (float) $items->line_sales : 0.0 ) : ( $row ? (float) $row->sales : 0.0 );
			$out    = array(
				'orders'        => $orders,
				'sales'         => $sales,
				'aov'           => $orders > 0 ? $sales / $orders : 0.0,
				'items'         => $items ? (int) $items->units : 0,
				'customers'     => $row ? (int) $row->customers : 0,
				'new_customers' => $new,
				'shipping'      => $product ? null : ( $row ? (float) $row->shipping : 0.0 ),
				'tax'           => $product ? null : ( $row ? (float) $row->tax : 0.0 ),
				'discounts'     => $product ? null : ( $row ? (float) $row->discounts : 0.0 ),
				'giftcards'     => $product ? null : ( $row ? (float) $row->giftcards : 0.0 ),
				'tips'          => $product ? null : ( $row ? (float) $row->tips : 0.0 ),
				'fees'          => $product ? null : $fees_total,
				'fee_lines'     => $fees,
				'refunds'       => $refunds,
				'received'      => $received,
				'net'           => ( null === $refunds ) ? null : $sales - $refunds,
				'abandoned'     => null,
				'visits'        => null,
				'views'         => null,
				'add_to_carts'  => null,
				'conversion'    => null,
			);
			if ( null !== $new ) {
				$out['returning_customers'] = max( 0, $out['customers'] - $new );
			}
			$out = array_merge( $out, self::activity_totals( $start, $end, $args, $orders ) );
			/**
			 * The Overview numbers for one range.
			 *
			 * @since 6.0.2
			 * @param array  $out   Numbers.
			 * @param string $start Y-m-d.
			 * @param string $end   Y-m-d.
			 * @param array  $args  Report arguments.
			 */
			return (array) apply_filters( 'wp_easycart_reports_totals', $out, $start, $end, $args );
		}

		/**
		 * Visits, views, add to cart, conversion and abandoned carts for a range.
		 *
		 * @param string $start  Y-m-d.
		 * @param string $end    Y-m-d.
		 * @param array  $args   args().
		 * @param int    $orders Orders in the range.
		 * @return array
		 */
		private static function activity_totals( $start, $end, $args, $orders ) {
			global $wpdb;
			$out = array();
			if ( class_exists( 'wp_easycart_order_ledger' ) && wp_easycart_order_ledger::ready() ) {
				if ( ! empty( $args['product_id'] ) ) {
					$views = $wpdb->get_row( $wpdb->prepare( 'SELECT SUM( views ) AS views, SUM( add_to_carts ) AS adds FROM ec_report_product_day WHERE stat_date >= %s AND stat_date <= %s AND product_id = %d', $start, $end, (int) $args['product_id'] ) );
				} else {
					$views = $wpdb->get_row( $wpdb->prepare( 'SELECT SUM( views ) AS views, SUM( add_to_carts ) AS adds FROM ec_report_product_day WHERE stat_date >= %s AND stat_date <= %s', $start, $end ) );
				}
				$out['views']        = $views ? (int) $views->views : 0;
				$out['add_to_carts'] = $views ? (int) $views->adds : 0;
				if ( ! self::filtered( $args ) ) {
					$visits        = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT SUM( visits ) FROM ec_report_visit_day WHERE stat_date >= %s AND stat_date <= %s', $start, $end ) );
					$out['visits'] = $visits;
					if ( $visits > 0 ) {
						$out['conversion'] = min( 100, $orders / $visits * 100 );
					}
				}
			}
			if ( self::table_exists( 'ec_abandoned_cart' ) && ! self::filtered( $args ) ) {
				list( $from, $to ) = self::bounds( $start, $end, 'db' );
				$out['abandoned']  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_abandoned_cart WHERE abandoned_at >= %s AND abandoned_at < %s', $from, $to ) );
			}
			return $out;
		}

		// ------------------------------------------------------------------
		// Days and buckets.
		// ------------------------------------------------------------------

		/**
		 * Numbers per local day: orders, sales, items, refunds.
		 *
		 * @param string $start Y-m-d.
		 * @param string $end   Y-m-d.
		 * @param array  $args  args().
		 * @return array Y-m-d => orders, sales, items, refunds.
		 */
		public static function daily( $start, $end, $args ) {
			$days = array();
			for ( $t = strtotime( $start . ' 12:00:00 UTC' ), $stop = strtotime( $end . ' 12:00:00 UTC' ); $t <= $stop; $t += DAY_IN_SECONDS ) {
				$days[ gmdate( 'Y-m-d', $t ) ] = array(
					'orders'  => 0,
					'sales'   => 0.0,
					'items'   => 0,
					'refunds' => 0.0,
				);
			}
			$rollup = ! self::filtered( $args ) && self::rollups_ready();
			$need   = $days;
			if ( $rollup ) {
				foreach ( self::read_rollups( $start, $end ) as $date => $data ) {
					if ( isset( $days[ $date ] ) ) {
						$days[ $date ] = array_merge( $days[ $date ], $data );
						unset( $need[ $date ] );
					}
				}
			}
			if ( empty( $need ) ) {
				return $days;
			}
			$keys  = array_keys( $need );
			$first = reset( $keys );
			$last  = end( $keys );
			foreach ( self::compute_days( $first, $last, $args ) as $date => $data ) {
				if ( isset( $need[ $date ] ) ) {
					$days[ $date ] = array_merge( $days[ $date ], $data );
				}
			}
			if ( $rollup ) {
				self::write_rollups( array_intersect_key( $days, $need ) );
			}
			return $days;
		}

		/**
		 * Work out days from the orders.
		 *
		 * @param string $start Y-m-d.
		 * @param string $end   Y-m-d.
		 * @param array  $args  args().
		 * @return array
		 */
		private static function compute_days( $start, $end, $args ) {
			global $wpdb;
			list( $from, $to )         = self::bounds( $start, $end, 'db' );
			list( $utc_from, $utc_to ) = self::bounds( $start, $end, 'utc' );
			$where                     = self::filter_sql( $args );
			$product                   = ! empty( $args['product_id'] );
			$day                       = self::day_sql( 'o.order_date', $start, $end, 'db' );
			$out                       = array();
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $day / $where are built by day_sql() / filter_sql(); the rest is fixed SQL.
			if ( ! $product ) {
				foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT {$day} AS d, COUNT(*) AS orders, SUM( o.grand_total ) AS sales FROM ec_order o " . self::sale_join() . ' WHERE o.order_date >= %s AND o.order_date < %s AND ' . self::sale_where() . $where . ' GROUP BY d', $from, $to ) ) as $row ) {
					$out[ $row->d ]['orders'] = (int) $row->orders;
					$out[ $row->d ]['sales']  = (float) $row->sales;
				}
			}
			$lines = $wpdb->get_results( $wpdb->prepare( "SELECT {$day} AS d, SUM( d.quantity ) AS items, SUM( d.total_price ) AS sales, COUNT( DISTINCT d.order_id ) AS orders FROM ec_orderdetail d INNER JOIN ec_order o ON o.order_id = d.order_id " . self::sale_join() . ' WHERE o.order_date >= %s AND o.order_date < %s AND ' . self::sale_where() . $where . ( $product ? $wpdb->prepare( ' AND d.product_id = %d', (int) $args['product_id'] ) : '' ) . ' GROUP BY d', $from, $to ) );
			foreach ( (array) $lines as $row ) {
				$out[ $row->d ]['items'] = (int) $row->items;
				if ( $product ) {
					$out[ $row->d ]['sales']  = (float) $row->sales;
					$out[ $row->d ]['orders'] = (int) $row->orders;
				}
			}
			if ( ! $product && class_exists( 'wp_easycart_order_ledger' ) && wp_easycart_order_ledger::ready() ) {
				$refund_day = self::day_sql( 't.created_at', $start, $end, 'utc' );
				foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT {$refund_day} AS d, SUM( t.amount ) AS refunds FROM ec_order_transaction t INNER JOIN ec_order o ON o.order_id = t.order_id WHERE t.txn_type = 'refund' AND t.created_at >= %s AND t.created_at < %s" . $where . ' GROUP BY d', $utc_from, $utc_to ) ) as $row ) {
					$out[ $row->d ]['refunds'] = (float) $row->refunds;
				}
			}
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
			return $out;
		}

		/**
		 * The bucket a day falls in.
		 *
		 * @param string $date  Y-m-d.
		 * @param string $range daily | weekly | monthly | yearly.
		 * @return string
		 */
		public static function bucket_key( $date, $range ) {
			$time = strtotime( $date . ' 12:00:00 UTC' );
			switch ( $range ) {
				case 'weekly':
					$start_of_week = (int) get_option( 'start_of_week', 1 );
					$back          = ( (int) gmdate( 'w', $time ) - $start_of_week + 7 ) % 7;
					return gmdate( 'Y-m-d', $time - $back * DAY_IN_SECONDS );
				case 'monthly':
					return gmdate( 'Y-m', $time );
				case 'yearly':
					return gmdate( 'Y', $time );
				default:
					return $date;
			}
		}

		/**
		 * A bucket's label.
		 *
		 * @param string $key   bucket_key().
		 * @param string $range Range.
		 * @param bool   $years The range spans more than one year.
		 * @return string
		 */
		public static function bucket_label( $key, $range, $years = false ) {
			switch ( $range ) {
				case 'monthly':
					return date_i18n( 'M Y', strtotime( $key . '-01 12:00:00 UTC' ), true );
				case 'yearly':
					return (string) $key;
				default:
					return date_i18n( $years ? 'M j, Y' : 'M j', strtotime( $key . ' 12:00:00 UTC' ), true );
			}
		}

		/**
		 * Days into buckets.
		 *
		 * @param array  $days  daily() output.
		 * @param string $range Range.
		 * @return array key => sums.
		 */
		public static function buckets( $days, $range ) {
			$out = array();
			foreach ( $days as $date => $data ) {
				$key = self::bucket_key( $date, $range );
				if ( ! isset( $out[ $key ] ) ) {
					$out[ $key ] = array();
				}
				foreach ( $data as $metric => $value ) {
					$out[ $key ][ $metric ] = ( isset( $out[ $key ][ $metric ] ) ? $out[ $key ][ $metric ] : 0 ) + $value;
				}
			}
			return $out;
		}

		/**
		 * A chart: one metric over the range, and the compare range laid on it bucket by bucket.
		 *
		 * @param string     $id      Chart id.
		 * @param string     $title   Title.
		 * @param string     $metric  Metric key in the buckets.
		 * @param array      $current buckets() of the range.
		 * @param array|null $compare buckets() of the compare range.
		 * @param array      $args    args().
		 * @param bool       $money   Money values.
		 * @param string     $type    line | bar.
		 * @return array
		 */
		public static function bucket_series( $id, $title, $metric, $current, $compare, $args, $money = true, $type = 'line' ) {
			$years  = substr( $args['start'], 0, 4 ) !== substr( $args['end'], 0, 4 );
			$labels = array();
			$data   = array();
			$titles = array();
			foreach ( $current as $key => $values ) {
				$labels[] = self::bucket_label( $key, $args['range'], $years );
				$titles[] = self::bucket_label( $key, $args['range'], true );
				$data[]   = round( isset( $values[ $metric ] ) ? (float) $values[ $metric ] : 0, 2 );
			}
			$datasets = array(
				array(
					'label'  => self::range_label( $args['start'], $args['end'] ),
					'data'   => $data,
					'titles' => $titles,
				),
			);
			if ( is_array( $compare ) ) {
				$cdata   = array();
				$ctitles = array();
				foreach ( $compare as $key => $values ) {
					$cdata[]   = round( isset( $values[ $metric ] ) ? (float) $values[ $metric ] : 0, 2 );
					$ctitles[] = self::bucket_label( $key, $args['range'], true );
				}
				$datasets[] = array(
					'label'   => self::range_label( $args['start2'], $args['end2'] ),
					'data'    => array_slice( $cdata, 0, count( $data ) ),
					'titles'  => array_slice( $ctitles, 0, count( $data ) ),
					'compare' => true,
				);
			}
			return array(
				'id'       => $id,
				'title'    => $title,
				'type'     => $type,
				'money'    => (bool) $money,
				'labels'   => $labels,
				'datasets' => $datasets,
			);
		}

		/**
		 * "Sep 1 – Sep 30, 2026".
		 *
		 * @param string $start Y-m-d.
		 * @param string $end   Y-m-d.
		 * @return string
		 */
		public static function range_label( $start, $end ) {
			$a = strtotime( $start . ' 12:00:00 UTC' );
			$b = strtotime( $end . ' 12:00:00 UTC' );
			if ( $start === $end ) {
				return date_i18n( 'M j, Y', $a, true );
			}
			if ( gmdate( 'Y', $a ) === gmdate( 'Y', $b ) ) {
				return date_i18n( 'M j', $a, true ) . ' – ' . date_i18n( 'M j, Y', $b, true );
			}
			return date_i18n( 'M j, Y', $a, true ) . ' – ' . date_i18n( 'M j, Y', $b, true );
		}

		// ------------------------------------------------------------------
		// Rollups ( ec_report_day ).
		// ------------------------------------------------------------------

		/**
		 * Can past days be kept ( the 6.0.2 tables are there and the ledger's history is filled in )?
		 *
		 * @return bool
		 */
		private static function rollups_ready() {
			return class_exists( 'wp_easycart_order_ledger' ) && wp_easycart_order_ledger::ready() && wp_easycart_order_ledger::backfilled();
		}

		/**
		 * The version a kept day must carry.
		 *
		 * @return string
		 */
		private static function rollup_version() {
			global $wpdb;
			if ( null === self::$version ) {
				/* Orders placed before today: an import or a direct insert of older orders ( no hooks ) changes it, today's orders do not. */
				list( $today ) = self::bounds( self::today(), self::today(), 'db' );
				$before        = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_order WHERE order_date < %s', $today ) );
				self::$version = substr( md5( self::RULES . '|' . wp_timezone_string() . '|' . (string) get_option( self::GENERATION_OPTION, '1' ) . '|' . $before ), 0, 32 );
			}
			return self::$version;
		}

		/**
		 * Kept days in a range ( before today only ).
		 *
		 * @param string $start Y-m-d.
		 * @param string $end   Y-m-d.
		 * @return array
		 */
		private static function read_rollups( $start, $end ) {
			global $wpdb;
			$out   = array();
			$today = self::today();
			foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT stat_date, data FROM ec_report_day WHERE stat_date >= %s AND stat_date <= %s AND stat_date < %s AND version = %s', $start, $end, $today, self::rollup_version() ) ) as $row ) {
				$data = json_decode( (string) $row->data, true );
				if ( is_array( $data ) ) {
					$out[ (string) $row->stat_date ] = $data;
				}
			}
			return $out;
		}

		/**
		 * Keep worked-out days ( never today or later ).
		 *
		 * @param array $days date => data.
		 */
		private static function write_rollups( $days ) {
			global $wpdb;
			$today   = self::today();
			$version = self::rollup_version();
			$now     = gmdate( 'Y-m-d H:i:s' );
			foreach ( $days as $date => $data ) {
				if ( $date >= $today ) {
					continue;
				}
				$wpdb->query( $wpdb->prepare( 'REPLACE INTO ec_report_day ( stat_date, data, version, updated_at ) VALUES ( %s, %s, %s, %s )', $date, wp_json_encode( $data ), $version, $now ) );
			}
		}

		/**
		 * An order was placed or changed status: forget its day.
		 *
		 * @param int $order_id Order.
		 */
		public static function forget_order_day( $order_id ) {
			global $wpdb;
			if ( ! self::rollups_ready() || (int) $order_id <= 0 ) {
				return;
			}
			$date = $wpdb->get_var( $wpdb->prepare( 'SELECT order_date FROM ec_order WHERE order_id = %d', (int) $order_id ) );
			if ( ! $date ) {
				return;
			}
			$local = wp_date( 'Y-m-d', strtotime( $date . ' UTC' ) - self::db_offset() );
			$wpdb->query( $wpdb->prepare( 'DELETE FROM ec_report_day WHERE stat_date = %s', $local ) );
		}

		/** An order was edited, deleted, duplicated or put back: every kept day is worked out again. */
		public static function forget_rollups() {
			update_option( self::GENERATION_OPTION, (string) microtime( true ), false );
			self::$version = null;
		}

		// ------------------------------------------------------------------
		// Tabs: Overview, Taxes and the locked teasers.
		// ------------------------------------------------------------------

		/**
		 * Overview payload.
		 *
		 * @param array $args args().
		 * @return array
		 */
		public static function overview( $args ) {
			$compare = '' !== $args['start2'];
			$cur     = self::totals( $args['start'], $args['end'], $args );
			$prev    = $compare ? self::totals( $args['start2'], $args['end2'], $args ) : null;
			$p       = function ( $key ) use ( $prev ) {
				return ( is_array( $prev ) && array_key_exists( $key, $prev ) ) ? $prev[ $key ] : null;
			};
			$product = ! empty( $args['product_id'] );
			$kpis    = array(
				self::kpi( 'sales', $product ? __( 'Product sales', 'wp-easycart' ) : __( 'Sales', 'wp-easycart' ), $cur['sales'], $p( 'sales' ), 'money', 'up', $product ? __( 'This product\'s lines, on the day each order was placed', 'wp-easycart' ) : __( 'Order totals, on the day each order was placed', 'wp-easycart' ) ),
				self::kpi( 'net', __( 'Net sales', 'wp-easycart' ), $cur['net'], $p( 'net' ), 'money', 'up', __( 'Sales less refunds given in the range', 'wp-easycart' ) ),
				self::kpi( 'orders', __( 'Orders', 'wp-easycart' ), $cur['orders'], $p( 'orders' ), 'int' ),
				self::kpi( 'aov', __( 'Average order', 'wp-easycart' ), $cur['aov'], $p( 'aov' ) ),
				self::kpi( 'received', __( 'Received', 'wp-easycart' ), $cur['received'], $p( 'received' ), 'money', 'up', __( 'Payments taken in the range, whenever the order was placed', 'wp-easycart' ) ),
				self::kpi( 'refunds', __( 'Refunds', 'wp-easycart' ), $cur['refunds'], $p( 'refunds' ), 'money', 'down', __( 'Refunds given in the range', 'wp-easycart' ) ),
				self::kpi( 'items', __( 'Items sold', 'wp-easycart' ), $cur['items'], $p( 'items' ), 'int' ),
				self::kpi( 'customers', __( 'Customers', 'wp-easycart' ), $cur['customers'], $p( 'customers' ), 'int', 'up', null !== $cur['new_customers'] ? sprintf( /* translators: %s: number of customers. */ __( '%s ordered for the first time', 'wp-easycart' ), number_format_i18n( (int) $cur['new_customers'] ) ) : '' ),
			);
			if ( null !== $cur['visits'] ) {
				$kpis[] = self::kpi( 'visits', __( 'Visits', 'wp-easycart' ), $cur['visits'], $p( 'visits' ), 'int', 'up', null !== $cur['conversion'] ? sprintf( /* translators: %s: percentage. */ __( '%s became an order', 'wp-easycart' ), number_format_i18n( (float) $cur['conversion'], 1 ) . '%' ) : '' );
			}
			if ( null !== $cur['abandoned'] ) {
				$kpis[] = self::kpi( 'abandoned', __( 'Abandoned carts', 'wp-easycart' ), $cur['abandoned'], $p( 'abandoned' ), 'int', 'down', __( 'Carts with an email address left before paying', 'wp-easycart' ) );
			}
			$kpis[] = self::kpi( 'shipping', __( 'Shipping', 'wp-easycart' ), $cur['shipping'], $p( 'shipping' ), 'money', '' );
			$kpis[] = self::kpi( 'tax', __( 'Taxes', 'wp-easycart' ), $cur['tax'], $p( 'tax' ), 'money', '' );
			$kpis[] = self::kpi( 'discounts', __( 'Discounts', 'wp-easycart' ), $cur['discounts'], $p( 'discounts' ), 'money', '', ( null !== $cur['giftcards'] && $cur['giftcards'] >= 0.005 ) ? sprintf( /* translators: %s: amount. */ __( 'Gift cards paid %s more', 'wp-easycart' ), self::money( $cur['giftcards'] ) ) : '' );
			if ( ! empty( $cur['fee_lines'] ) || ( is_array( $prev ) && ! empty( $prev['fee_lines'] ) ) ) {
				$kpis[] = self::kpi( 'fees', __( 'Fees', 'wp-easycart' ), $cur['fees'], $p( 'fees' ), 'money', '', implode( ', ', array_map( 'strval', array_keys( $cur['fee_lines'] ) ) ) );
			}
			if ( null !== $cur['tips'] && $cur['tips'] >= 0.005 ) {
				$kpis[] = self::kpi( 'tips', __( 'Tips', 'wp-easycart' ), $cur['tips'], $p( 'tips' ), 'money', 'up' );
			}

			$days     = self::daily( $args['start'], $args['end'], $args );
			$current  = self::buckets( $days, $args['range'] );
			$previous = $compare ? self::buckets( self::daily( $args['start2'], $args['end2'], $args ), $args['range'] ) : null;
			foreach ( $current as $key => $values ) {
				$current[ $key ]['net'] = $values['sales'] - $values['refunds'];
			}
			if ( is_array( $previous ) ) {
				foreach ( $previous as $key => $values ) {
					$previous[ $key ]['net'] = $values['sales'] - $values['refunds'];
				}
			}
			$charts = array(
				self::bucket_series( 'sales', __( 'Sales', 'wp-easycart' ), 'sales', $current, $previous, $args, true, 'line' ),
				self::bucket_series( 'orders', __( 'Orders', 'wp-easycart' ), 'orders', $current, $previous, $args, false, 'bar' ),
				self::bucket_series( 'items', __( 'Items sold', 'wp-easycart' ), 'items', $current, $previous, $args, false, 'bar' ),
			);
			return array(
				'tab'      => 'overview',
				'kpis'     => $kpis,
				'charts'   => $charts,
				'insights' => self::insights( $cur, $prev, $days, $args ),
				'notes'    => $product ? array( __( 'Filtered by product: shipping, taxes, discounts, fees and refunds belong to whole orders, so they are not shown.', 'wp-easycart' ) ) : array(),
				'history'  => self::history_note(),
			);
		}

		/**
		 * A note while the payment and refund history is still being filled in.
		 *
		 * @return string
		 */
		private static function history_note() {
			if ( class_exists( 'wp_easycart_order_ledger' ) && wp_easycart_order_ledger::ready() && ! wp_easycart_order_ledger::backfilled() ) {
				return __( 'Reports are still reading your order history. Refunds and money received for older orders may be missing for a few minutes.', 'wp-easycart' );
			}
			if ( ! class_exists( 'wp_easycart_order_ledger' ) || ! wp_easycart_order_ledger::ready() ) {
				return __( 'Finish the WP EasyCart database update to see money received and refunds on the day they happened.', 'wp-easycart' );
			}
			return '';
		}

		/**
		 * One-line takeaways.
		 *
		 * @param array      $cur  totals().
		 * @param array|null $prev totals() of the compare range.
		 * @param array      $days daily().
		 * @param array      $args args().
		 * @return array
		 */
		public static function insights( $cur, $prev, $days, $args ) {
			$out = array();
			if ( is_array( $prev ) && $prev['sales'] >= 0.005 ) {
				$change = ( $cur['sales'] - $prev['sales'] ) / $prev['sales'] * 100;
				if ( abs( $change ) >= 0.5 ) {
					$out[] = array(
						'tone' => $change > 0 ? 'good' : 'bad',
						/* translators: 1: percentage, 2: date range. */
						'text' => sprintf( $change > 0 ? __( 'Sales are up %1$s on %2$s.', 'wp-easycart' ) : __( 'Sales are down %1$s on %2$s.', 'wp-easycart' ), number_format_i18n( abs( $change ), 1 ) . '%', self::range_label( $args['start2'], $args['end2'] ) ),
					);
				}
			}
			$best  = '';
			$value = 0.0;
			foreach ( $days as $date => $data ) {
				if ( $data['sales'] > $value ) {
					$value = $data['sales'];
					$best  = $date;
				}
			}
			if ( '' !== $best && count( $days ) > 1 ) {
				$out[] = array(
					'tone' => '',
					/* translators: 1: date, 2: amount, 3: number of orders. */
					'text' => sprintf( _n( 'Your best day was %1$s: %2$s from %3$s order.', 'Your best day was %1$s: %2$s from %3$s orders.', (int) $days[ $best ]['orders'], 'wp-easycart' ), date_i18n( 'D, M j', strtotime( $best . ' 12:00:00 UTC' ), true ), self::money( $value ), number_format_i18n( (int) $days[ $best ]['orders'] ) ),
				);
			}
			if ( null !== $cur['new_customers'] && $cur['customers'] > 0 ) {
				$share = $cur['new_customers'] / $cur['customers'] * 100;
				$out[] = array(
					'tone' => '',
					/* translators: %s: percentage. */
					'text' => sprintf( __( '%s of your customers ordered for the first time.', 'wp-easycart' ), number_format_i18n( $share, 0 ) . '%' ),
				);
			}
			if ( null !== $cur['conversion'] && $cur['visits'] >= 20 ) {
				$out[] = array(
					'tone' => '',
					/* translators: 1: percentage, 2: number of visits. */
					'text' => sprintf( __( '%1$s of %2$s visits ended in an order.', 'wp-easycart' ), number_format_i18n( (float) $cur['conversion'], 1 ) . '%', number_format_i18n( (int) $cur['visits'] ) ),
				);
			}
			/**
			 * One-line takeaways on the Overview.
			 *
			 * @since 6.0.2
			 * @param array      $out  tone, text.
			 * @param array      $cur  Numbers for the range.
			 * @param array|null $prev Numbers for the compare range.
			 * @param array      $args Report arguments.
			 */
			return (array) apply_filters( 'wp_easycart_reports_insights', $out, $cur, $prev, $args );
		}

		/**
		 * Taxes payload.
		 *
		 * @param array $args args().
		 * @return array
		 */
		public static function taxes( $args ) {
			global $wpdb;
			list( $from, $to ) = self::bounds( $args['start'], $args['end'], 'db' );
			$where             = self::filter_sql( $args );
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $where comes from filter_sql() ( prepared ); the rest is fixed SQL.
			$sum    = $wpdb->get_row( $wpdb->prepare( 'SELECT COUNT(*) AS orders, SUM( o.tax_total ) AS sales_tax, SUM( o.vat_total ) AS vat, SUM( o.gst_total ) AS gst, SUM( o.pst_total ) AS pst, SUM( o.hst_total ) AS hst, SUM( o.duty_total ) AS duty, SUM( o.tax_refund_total ) AS tax_refunded, SUM( o.sub_total - o.discount_total ) AS taxable FROM ec_order o ' . self::sale_join() . ' WHERE o.order_date >= %s AND o.order_date < %s AND ' . self::sale_where() . $where, $from, $to ) );
			$places = $wpdb->get_results( $wpdb->prepare( "SELECT IF( o.shipping_country <> '', o.shipping_country, o.billing_country ) AS country, IF( o.shipping_country <> '', o.shipping_state, o.billing_state ) AS region, COUNT(*) AS orders, SUM( o.grand_total ) AS sales, SUM( o.tax_total ) AS sales_tax, SUM( o.vat_total ) AS vat, SUM( o.gst_total + o.pst_total + o.hst_total ) AS gst, SUM( o.duty_total ) AS duty, SUM( o.tax_total + o.vat_total + o.gst_total + o.pst_total + o.hst_total + o.duty_total ) AS tax FROM ec_order o " . self::sale_join() . ' WHERE o.order_date >= %s AND o.order_date < %s AND ' . self::sale_where() . $where . ' GROUP BY country, region ORDER BY tax DESC, sales DESC LIMIT 500', $from, $to ) );
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
			$kpis  = array();
			$types = array(
				'sales_tax' => __( 'Sales tax', 'wp-easycart' ),
				'vat'       => __( 'VAT', 'wp-easycart' ),
				'gst'       => __( 'GST', 'wp-easycart' ),
				'pst'       => __( 'PST', 'wp-easycart' ),
				'hst'       => __( 'HST', 'wp-easycart' ),
				'duty'      => __( 'Duty', 'wp-easycart' ),
			);
			$total = 0.0;
			foreach ( $types as $key => $label ) {
				$value  = $sum ? (float) $sum->{$key} : 0.0;
				$total += $value;
				if ( $value >= 0.005 || 'sales_tax' === $key ) {
					$kpis[] = self::kpi( $key, $label, $value, null, 'money', '' );
				}
			}
			array_unshift( $kpis, self::kpi( 'tax', __( 'Tax collected', 'wp-easycart' ), $total, null, 'money', '', __( 'On orders placed in the range', 'wp-easycart' ) ) );
			$kpis[] = self::kpi( 'taxable', __( 'Items less discounts', 'wp-easycart' ), $sum ? (float) $sum->taxable : 0.0, null, 'money', '' );
			if ( $sum && (float) $sum->tax_refunded >= 0.005 ) {
				$kpis[] = self::kpi( 'tax_refunded', __( 'Tax refunded', 'wp-easycart' ), (float) $sum->tax_refunded, null, 'money', '', __( 'On these orders, whenever it was refunded', 'wp-easycart' ) );
			}
			$names = self::country_names();
			$rows  = array();
			foreach ( (array) $places as $place ) {
				$rows[] = array(
					'country'   => isset( $names[ $place->country ] ) ? $names[ $place->country ] : (string) $place->country,
					'region'    => (string) $place->region,
					'orders'    => (int) $place->orders,
					'sales'     => round( (float) $place->sales, 2 ),
					'sales_tax' => round( (float) $place->sales_tax, 2 ),
					'vat'       => round( (float) $place->vat, 2 ),
					'gst'       => round( (float) $place->gst, 2 ),
					'duty'      => round( (float) $place->duty, 2 ),
					'tax'       => round( (float) $place->tax, 2 ),
				);
			}
			$columns = array(
				array(
					'key'   => 'country',
					'label' => __( 'Country', 'wp-easycart' ),
				),
				array(
					'key'   => 'region',
					'label' => __( 'State / region', 'wp-easycart' ),
				),
				array(
					'key'    => 'orders',
					'label'  => __( 'Orders', 'wp-easycart' ),
					'format' => 'int',
					'align'  => 'right',
				),
				array(
					'key'    => 'sales',
					'label'  => __( 'Sales', 'wp-easycart' ),
					'format' => 'money',
					'align'  => 'right',
				),
			);
			foreach ( array( 'sales_tax', 'vat', 'gst', 'duty' ) as $key ) {
				$used = false;
				foreach ( $rows as $row ) {
					if ( abs( $row[ $key ] ) >= 0.005 ) {
						$used = true;
						break;
					}
				}
				if ( $used ) {
					$columns[] = array(
						'key'    => $key,
						'label'  => ( 'gst' === $key ) ? __( 'GST / PST / HST', 'wp-easycart' ) : $types[ $key ],
						'format' => 'money',
						'align'  => 'right',
					);
				}
			}
			$columns[] = array(
				'key'    => 'tax',
				'label'  => __( 'Total tax', 'wp-easycart' ),
				'format' => 'money',
				'align'  => 'right',
			);
			return array(
				'tab'    => 'taxes',
				'kpis'   => $kpis,
				'tables' => array(
					array(
						'id'      => 'tax_places',
						'title'   => __( 'Tax by place', 'wp-easycart' ),
						'note'    => __( 'Where the order shipped, or its billing address when nothing shipped. Each order counts on the day it was placed.', 'wp-easycart' ),
						'columns' => $columns,
						'rows'    => $rows,
						'csv'     => true,
					),
				),
				'notes'  => array( __( 'For filings, the Export CSV tax report lists every order.', 'wp-easycart' ) ),
			);
		}

		/**
		 * Country code => name.
		 *
		 * @return array
		 */
		private static function country_names() {
			global $wpdb;
			$out = array();
			foreach ( (array) $wpdb->get_results( 'SELECT iso2_cnt, name_cnt FROM ec_country' ) as $country ) {
				$out[ (string) $country->iso2_cnt ] = (string) $country->name_cnt;
			}
			return $out;
		}

		/**
		 * A locked tab: the store's own top row and how many more, from Free data ( D3 ).
		 *
		 * @param string $tab  Tab id.
		 * @param array  $args args().
		 * @return array columns, row, count, count_label ( or empty when there is nothing yet ).
		 */
		public static function teaser( $tab, $args ) {
			global $wpdb;
			list( $from, $to ) = self::bounds( $args['start'], $args['end'], 'db' );
			$where             = self::filter_sql( $args );
			$row               = null;
			$count             = 0;
			$columns           = array();
			$more              = '';
			$cells             = array();
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $where comes from filter_sql() ( prepared ); the rest is fixed SQL.
			switch ( $tab ) {
				case 'products':
				case 'profit':
					$columns = ( 'profit' === $tab ) ? array( __( 'Product', 'wp-easycart' ), __( 'Sales', 'wp-easycart' ), __( 'Profit', 'wp-easycart' ), __( 'Margin', 'wp-easycart' ) ) : array( __( 'Product', 'wp-easycart' ), __( 'Units', 'wp-easycart' ), __( 'Sales', 'wp-easycart' ), __( 'Views', 'wp-easycart' ) );
					$row     = $wpdb->get_row( $wpdb->prepare( 'SELECT d.product_id, MAX( d.title ) AS title, SUM( d.quantity ) AS units, SUM( d.total_price ) AS sales, SUM( d.unit_cost * d.quantity ) AS cost, SUM( d.unit_cost > 0 ) AS costed FROM ec_orderdetail d INNER JOIN ec_order o ON o.order_id = d.order_id ' . self::sale_join() . ' WHERE o.order_date >= %s AND o.order_date < %s AND ' . self::sale_where() . $where . ' GROUP BY d.product_id ORDER BY sales DESC LIMIT 1', $from, $to ) );
					$count   = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT( DISTINCT d.product_id ) FROM ec_orderdetail d INNER JOIN ec_order o ON o.order_id = d.order_id ' . self::sale_join() . ' WHERE o.order_date >= %s AND o.order_date < %s AND ' . self::sale_where() . $where, $from, $to ) );
					if ( $row ) {
						if ( 'profit' === $tab ) {
							$profit = ( (int) $row->costed > 0 ) ? (float) $row->sales - (float) $row->cost : null;
							$cells  = array( (string) $row->title, self::money( $row->sales ), null === $profit ? __( 'Add a cost', 'wp-easycart' ) : self::money( $profit ), ( null === $profit || (float) $row->sales < 0.005 ) ? '—' : number_format_i18n( $profit / (float) $row->sales * 100, 1 ) . '%' );
						} else {
							$views = ( class_exists( 'wp_easycart_order_ledger' ) && wp_easycart_order_ledger::ready() ) ? (int) $wpdb->get_var( $wpdb->prepare( 'SELECT SUM( views ) FROM ec_report_product_day WHERE product_id = %d AND stat_date >= %s AND stat_date <= %s', (int) $row->product_id, $args['start'], $args['end'] ) ) : 0;
							$cells = array( (string) $row->title, number_format_i18n( (int) $row->units ), self::money( $row->sales ), $views > 0 ? number_format_i18n( $views ) : '—' );
						}
					}
					/* translators: %s: number of products. */
					$more = _n( 'and %s more product', 'and %s more products', max( 0, $count - 1 ), 'wp-easycart' );
					break;

				case 'customers':
					$columns  = array( __( 'Customer', 'wp-easycart' ), __( 'Orders', 'wp-easycart' ), __( 'Spent', 'wp-easycart' ), __( 'First order', 'wp-easycart' ) );
					$customer = self::customer_expr( 'o' );
					$row      = $wpdb->get_row( $wpdb->prepare( "SELECT {$customer} AS who, MAX( CONCAT( o.billing_first_name, ' ', o.billing_last_name ) ) AS name, COUNT(*) AS orders, SUM( o.grand_total ) AS spent, MIN( o.order_date ) AS first_order FROM ec_order o " . self::sale_join() . ' WHERE o.order_date >= %s AND o.order_date < %s AND ' . self::sale_where() . $where . ' GROUP BY who ORDER BY spent DESC LIMIT 1', $from, $to ) );
					$count    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT( DISTINCT {$customer} ) FROM ec_order o " . self::sale_join() . ' WHERE o.order_date >= %s AND o.order_date < %s AND ' . self::sale_where() . $where, $from, $to ) );
					if ( $row ) {
						$cells = array( '' !== trim( (string) $row->name ) ? trim( (string) $row->name ) : __( 'Guest', 'wp-easycart' ), number_format_i18n( (int) $row->orders ), self::money( $row->spent ), wp_date( 'M j, Y', strtotime( (string) $row->first_order . ' UTC' ) - self::db_offset() ) );
					}
					/* translators: %s: number of customers. */
					$more = _n( 'and %s more customer', 'and %s more customers', max( 0, $count - 1 ), 'wp-easycart' );
					break;

				case 'codes':
					$columns = array( __( 'Code', 'wp-easycart' ), __( 'Uses', 'wp-easycart' ), __( 'Discount given', 'wp-easycart' ), __( 'Sales', 'wp-easycart' ) );
					$row     = $wpdb->get_row( $wpdb->prepare( 'SELECT UPPER( o.promo_code ) AS code, COUNT(*) AS uses, SUM( o.discount_total ) AS discount, SUM( o.grand_total ) AS sales FROM ec_order o ' . self::sale_join() . " WHERE o.order_date >= %s AND o.order_date < %s AND o.promo_code <> '' AND " . self::sale_where() . $where . ' GROUP BY code ORDER BY uses DESC, sales DESC LIMIT 1', $from, $to ) );
					$count   = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT( DISTINCT UPPER( o.promo_code ) ) FROM ec_order o ' . self::sale_join() . " WHERE o.order_date >= %s AND o.order_date < %s AND o.promo_code <> '' AND " . self::sale_where() . $where, $from, $to ) );
					if ( $row ) {
						$cells = array( (string) $row->code, number_format_i18n( (int) $row->uses ), self::money( $row->discount ), self::money( $row->sales ) );
					}
					/* translators: %s: number of codes. */
					$more = _n( 'and %s more code', 'and %s more codes', max( 0, $count - 1 ), 'wp-easycart' );
					break;

				case 'carts':
					$columns = array( __( 'Product left in carts', 'wp-easycart' ), __( 'Carts', 'wp-easycart' ), __( 'Units', 'wp-easycart' ), __( 'Value', 'wp-easycart' ) );
					if ( class_exists( 'ec_abandoned_carts' ) && self::table_exists( 'ec_abandoned_cart' ) ) {
						$days = max( 1, (int) round( ( time() - self::day_start( $args['start'] ) ) / DAY_IN_SECONDS ) );
						$top  = ec_abandoned_carts::top_products( $days, 50 );
						if ( ! empty( $top ) ) {
							$first = $top[0];
							$cells = array( (string) $first['title'], number_format_i18n( (int) $first['carts'] ), number_format_i18n( (int) $first['qty'] ), self::money( $first['value'] ) );
						}
						$count = count( $top );
					}
					/* translators: %s: number of products. */
					$more = _n( 'and %s more product', 'and %s more products', max( 0, $count - 1 ), 'wp-easycart' );
					break;

				case 'sources':
					$columns = array( __( 'Source', 'wp-easycart' ), __( 'Orders', 'wp-easycart' ), __( 'Sales', 'wp-easycart' ), __( 'Average order', 'wp-easycart' ) );
					if ( self::has_column( 'ec_order', 'source_type' ) ) {
						$row   = $wpdb->get_row( $wpdb->prepare( 'SELECT o.source_type AS type, COUNT(*) AS orders, SUM( o.grand_total ) AS sales FROM ec_order o ' . self::sale_join() . " WHERE o.order_date >= %s AND o.order_date < %s AND o.source_type <> '' AND " . self::sale_where() . $where . ' GROUP BY o.source_type ORDER BY sales DESC LIMIT 1', $from, $to ) );
						$count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT( DISTINCT o.source_type ) FROM ec_order o ' . self::sale_join() . " WHERE o.order_date >= %s AND o.order_date < %s AND o.source_type <> '' AND " . self::sale_where() . $where, $from, $to ) );
						if ( $row ) {
							$label = class_exists( 'wp_easycart_order_source' ) ? wp_easycart_order_source::type_label( (string) $row->type ) : (string) $row->type;
							$cells = array( $label, number_format_i18n( (int) $row->orders ), self::money( $row->sales ), self::money( (int) $row->orders > 0 ? (float) $row->sales / (int) $row->orders : 0 ) );
						}
					}
					/* translators: %s: number of sources. */
					$more = _n( 'and %s more source', 'and %s more sources', max( 0, $count - 1 ), 'wp-easycart' );
					break;

				case 'fulfillment':
					$columns = array( __( 'Orders shipped', 'wp-easycart' ), __( 'Average time to ship', 'wp-easycart' ), __( 'Within 2 days', 'wp-easycart' ), __( 'Waiting now', 'wp-easycart' ) );
					if ( class_exists( 'wp_easycart_order_ledger' ) && wp_easycart_order_ledger::ready() ) {
						list( $utc_from, $utc_to ) = self::bounds( $args['start'], $args['end'], 'utc' );
						$ship                      = $wpdb->get_row( $wpdb->prepare( 'SELECT COUNT(*) AS n, AVG( TIMESTAMPDIFF( MINUTE, o.paid_at, o.fulfilled_at ) ) AS minutes, SUM( TIMESTAMPDIFF( HOUR, o.paid_at, o.fulfilled_at ) <= 48 ) AS fast FROM ec_order o WHERE o.fulfilled_at >= %s AND o.fulfilled_at < %s AND o.paid_at IS NOT NULL AND o.fulfilled_at >= o.paid_at' . $where, $utc_from, $utc_to ) );
						if ( $ship && (int) $ship->n > 0 ) {
							$waiting = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_order o ' . self::sale_join() . ' WHERE s.is_approved = 1 AND o.paid_at IS NOT NULL AND o.fulfilled_at IS NULL AND o.orderstatus_id NOT IN ( 16, 17, 19 ) AND EXISTS ( SELECT 1 FROM ec_orderdetail d WHERE d.order_id = o.order_id AND d.is_shippable = 1 ) AND o.paid_at >= DATE_SUB( UTC_TIMESTAMP(), INTERVAL 60 DAY )' );
							$cells   = array( number_format_i18n( (int) $ship->n ), self::duration( (float) $ship->minutes / 60 ), number_format_i18n( (int) $ship->fast / (int) $ship->n * 100, 0 ) . '%', number_format_i18n( $waiting ) );
							$count   = 1;
						}
					}
					$more = __( 'and the time each order took, week by week', 'wp-easycart' );
					break;

				case 'searches':
					$columns = array( __( 'Search', 'wp-easycart' ), __( 'Searches', 'wp-easycart' ), __( 'Products found', 'wp-easycart' ), __( 'Share', 'wp-easycart' ) );
					if ( class_exists( 'wp_easycart_order_ledger' ) && wp_easycart_order_ledger::ready() ) {
						$row   = $wpdb->get_row( $wpdb->prepare( 'SELECT term, SUM( searches ) AS n, MIN( results ) AS found FROM ec_report_search_day WHERE stat_date >= %s AND stat_date <= %s GROUP BY term ORDER BY n DESC LIMIT 1', $args['start'], $args['end'] ) );
						$all   = $wpdb->get_row( $wpdb->prepare( 'SELECT COUNT( DISTINCT term ) AS terms, SUM( searches ) AS n FROM ec_report_search_day WHERE stat_date >= %s AND stat_date <= %s', $args['start'], $args['end'] ) );
						$count = $all ? (int) $all->terms : 0;
						if ( $row ) {
							$cells = array( (string) $row->term, number_format_i18n( (int) $row->n ), number_format_i18n( (int) $row->found ), ( $all && (int) $all->n > 0 ) ? number_format_i18n( (int) $row->n / (int) $all->n * 100, 0 ) . '%' : '—' );
						}
					}
					/* translators: %s: number of search terms. */
					$more = _n( 'and %s more search', 'and %s more searches', max( 0, $count - 1 ), 'wp-easycart' );
					break;

				case 'subscriptions':
					$columns = array( __( 'Plan', 'wp-easycart' ), __( 'Active', 'wp-easycart' ), __( 'Price', 'wp-easycart' ), __( 'Billed every', 'wp-easycart' ) );
					if ( self::table_exists( 'ec_subscription' ) ) {
						$row   = $wpdb->get_row( "SELECT MAX( title ) AS title, COUNT(*) AS active, MAX( price ) AS price, MAX( payment_length ) AS length, MAX( payment_period ) AS period FROM ec_subscription WHERE LOWER( subscription_status ) IN ( 'active', 'trialing', 'past_due' ) GROUP BY product_id ORDER BY active DESC LIMIT 1" );
						$count = (int) $wpdb->get_var( "SELECT COUNT( DISTINCT product_id ) FROM ec_subscription WHERE LOWER( subscription_status ) IN ( 'active', 'trialing', 'past_due' )" );
						if ( $row ) {
							$cells = array( (string) $row->title, number_format_i18n( (int) $row->active ), self::money( $row->price ), trim( max( 1, (int) $row->length ) . ' ' . strtolower( (string) $row->period ) ) );
						}
					}
					/* translators: %s: number of plans. */
					$more = _n( 'and %s more plan', 'and %s more plans', max( 0, $count - 1 ), 'wp-easycart' );
					break;
			}
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
			return array(
				'columns' => $columns,
				'row'     => $cells,
				'count'   => $count,
				'more'    => ( $count > 1 ) ? sprintf( $more, number_format_i18n( $count - 1 ) ) : ( 'fulfillment' === $tab && $count ? $more : '' ),
			);
		}

		// ------------------------------------------------------------------
		// AJAX.
		// ------------------------------------------------------------------

		/**
		 * The payload for a tab ( cached ten minutes; the cache follows new orders ).
		 *
		 * @param array $args args().
		 * @return array
		 */
		public static function payload( $args ) {
			$tab   = $args['tab'];
			$tabs  = self::tabs();
			$state = self::tab_state( $tab );
			if ( ! isset( $tabs[ $tab ] ) ) {
				$tab   = 'overview';
				$state = 'free';
			}
			$admin = function_exists( 'wp_easycart_admin' ) ? wp_easycart_admin() : null;
			/* Bug round 7: the Pro tabs' notes name a Cookie consent setting nothing can answer, so a change of it starts a new payload. */
			$block = ( class_exists( 'wp_easycart_store_activity' ) && method_exists( 'wp_easycart_store_activity', 'consent_block' ) && wp_easycart_store_activity::consent_block() ) ? 1 : 0;
			$key   = 'reports|' . md5( wp_json_encode( array( $args, $state, self::rollup_version(), $block ) ) );
			if ( $admin && method_exists( $admin, 'report_cache_get' ) ) {
				$cached = $admin->report_cache_get( $key );
				if ( is_array( $cached ) ) {
					return $cached;
				}
			}
			$data = null;
			if ( 'overview' === $tab ) {
				$data = self::overview( $args );
			} elseif ( 'taxes' === $tab ) {
				$data = self::taxes( $args );
			} elseif ( 'enabled' === $state ) {
				/**
				 * A Pro Reports tab's payload ( see the file comment for the shape ).
				 *
				 * @since 6.0.2
				 * @param array|null $data null.
				 * @param string     $tab  Tab id.
				 * @param array      $args Report arguments ( args() ).
				 */
				$data = apply_filters( 'wp_easycart_reports_tab_data', null, $tab, $args );
			}
			if ( ! is_array( $data ) ) {
				$data = array(
					'tab'    => $tab,
					'locked' => self::lock( $tab, $state ),
					'teaser' => self::teaser( $tab, $args ),
				);
			}
			$data['tab']   = $tab;
			$data['range'] = self::range_label( $args['start'], $args['end'] );
			if ( $admin && method_exists( $admin, 'report_cache_set' ) ) {
				$admin->report_cache_set( $key, $data, 10 * MINUTE_IN_SECONDS );
			}
			return $data;
		}

		/**
		 * What a locked tab says and where its button goes.
		 *
		 * @param string $tab   Tab id.
		 * @param string $state tab_state().
		 * @return array
		 */
		public static function lock( $tab, $state ) {
			$tabs    = self::tabs();
			$label   = isset( $tabs[ $tab ] ) ? $tabs[ $tab ]['label'] : '';
			$feature = isset( $tabs[ $tab ]['feature'] ) ? $tabs[ $tab ]['feature'] : $tab;
			$lock    = array(
				'state'   => $state,
				'feature' => $feature,
				'title'   => isset( $tabs[ $tab ]['desc'] ) ? $tabs[ $tab ]['desc'] : $label,
				'button'  => '',
				'url'     => '',
				'text'    => '',
			);
			switch ( $state ) {
				case 'update':
				case 'inactive':
					$lock['button'] = ( 'update' === $state ) ? __( 'Update WP EasyCart PRO', 'wp-easycart' ) : __( 'Activate WP EasyCart PRO', 'wp-easycart' );
					$lock['url']    = self_admin_url( 'plugins.php' );
					$lock['text']   = ( 'update' === $state ) ? __( 'This report comes with WP EasyCart PRO 6.0.2. Update the plugin to open it.', 'wp-easycart' ) : __( 'WP EasyCart PRO is installed but not active.', 'wp-easycart' );
					break;
				case 'license':
					$lock['button'] = __( 'Check your license', 'wp-easycart' );
					$lock['url']    = self_admin_url( 'admin.php?page=wp-easycart-license-status' );
					$lock['text']   = class_exists( 'wp_easycart_admin_pro_gate' ) ? wp_easycart_admin_pro_gate::message( array( 'state' => 'license' ), $label ) : '';
					break;
				default:
					$lock['button'] = class_exists( 'wp_easycart_admin_edition' ) ? sprintf( /* translators: %s: plan name. */ __( 'Unlock with %s', 'wp-easycart' ), wp_easycart_admin_edition::plan_name() ) : __( 'Unlock', 'wp-easycart' );
					$lock['text']   = class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::requires_text( $label ) : '';
					break;
			}
			return $lock;
		}

		/** AJAX ecv2_reports_data. */
		public static function ajax_data() {
			ecv2_reports_guard();
			$args = self::args( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- every value is checked in args().
			wp_send_json_success( self::payload( $args ) );
		}

		/** AJAX ecv2_reports_lock_click: count a click on a locked tab ( which reports stores ask for ). */
		public static function ajax_lock_click() {
			ecv2_reports_guard();
			$tab    = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : '';
			$tabs   = self::tabs();
			$counts = get_option( self::LOCK_OPTION, array() );
			$counts = is_array( $counts ) ? $counts : array();
			if ( isset( $tabs[ $tab ] ) ) {
				$counts[ $tab ] = ( isset( $counts[ $tab ] ) ? (int) $counts[ $tab ] : 0 ) + 1;
				update_option( self::LOCK_OPTION, $counts, false );
			}
			wp_send_json_success();
		}

		// ------------------------------------------------------------------
		// Summary email.
		// ------------------------------------------------------------------

		/**
		 * The summary settings.
		 *
		 * @return array on, frequency, recipients, sections, offer ( shown | dismissed ), last.
		 */
		public static function summary_settings() {
			$saved = get_option( self::SUMMARY_OPTION, array() );
			$saved = is_array( $saved ) ? $saved : array();
			$out   = array_merge(
				array(
					'on'         => 0,
					'frequency'  => 'weekly',
					'recipients' => '',
					'sections'   => array(),
					'offer'      => '',
					'last'       => '',
				),
				$saved
			);
			if ( ! isset( self::frequencies()[ $out['frequency'] ] ) ) {
				$out['frequency'] = 'weekly';
			}
			return $out;
		}

		/**
		 * How often the summary can be sent ( WP EasyCart PRO adds daily and monthly ).
		 *
		 * @return array key => label.
		 */
		public static function frequencies() {
			/**
			 * Summary email frequencies.
			 *
			 * @since 6.0.2
			 * @param array $frequencies key => label.
			 */
			return (array) apply_filters( 'wp_easycart_reports_summary_frequencies', array( 'weekly' => __( 'Weekly', 'wp-easycart' ) ) );
		}

		/**
		 * Who gets the summary.
		 *
		 * @param array|null $settings summary_settings() ( default: the saved ones ).
		 * @return string[]
		 */
		public static function summary_recipients( $settings = null ) {
			$settings = is_array( $settings ) ? array_merge( self::summary_settings(), $settings ) : self::summary_settings();
			$list     = array();
			foreach ( preg_split( '/[\s,;]+/', (string) $settings['recipients'] ) as $email ) {
				$email = sanitize_email( $email );
				if ( is_email( $email ) ) {
					$list[] = $email;
				}
			}
			if ( empty( $list ) ) {
				$admin = sanitize_email( (string) get_option( 'admin_email' ) );
				if ( is_email( $admin ) ) {
					$list[] = $admin;
				}
			}
			return array_slice( array_unique( $list ), 0, 10 );
		}

		/**
		 * AJAX ecv2_reports_summary: turn on ( the one-click offer ), save, dismiss the offer, or send a test. A test uses the
		 * dialog's choices as they are ( when it sends them ) without saving them.
		 */
		public static function ajax_summary() {
			ecv2_reports_guard();
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_reports' ) ) {
				wp_send_json_error( array( 'message' => __( 'You do not have permission to change the summary email.', 'wp-easycart' ) ) );
			}
			$do       = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
			$settings = self::summary_settings();
			if ( 'dismiss' === $do ) {
				$settings['offer'] = 'dismissed';
			} elseif ( 'on' === $do ) {
				$settings['on']    = 1;
				$settings['offer'] = 'accepted';
			} elseif ( 'save' === $do || ( 'test' === $do && isset( $_POST['frequency'] ) ) ) {
				$settings['on']        = ! empty( $_POST['on'] ) ? 1 : 0;
				$frequency             = isset( $_POST['frequency'] ) ? sanitize_key( wp_unslash( $_POST['frequency'] ) ) : 'weekly';
				$settings['frequency'] = isset( self::frequencies()[ $frequency ] ) ? $frequency : 'weekly';
				$recipients            = array();
				foreach ( preg_split( '/[\s,;]+/', isset( $_POST['recipients'] ) ? sanitize_textarea_field( wp_unslash( $_POST['recipients'] ) ) : '' ) as $email ) {
					$email = sanitize_email( $email );
					if ( is_email( $email ) ) {
						$recipients[] = $email;
					}
				}
				$settings['recipients'] = implode( ', ', array_slice( array_unique( $recipients ), 0, 10 ) );
				$sections               = isset( $_POST['sections'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['sections'] ) ) : array();
				$settings['sections']   = array_values( array_intersect( $sections, array_keys( self::summary_section_choices() ) ) );
				$settings['offer']      = 'accepted';
			}
			if ( 'test' === $do ) {
				$to = self::summary_recipients( $settings );
				if ( empty( $to ) ) {
					wp_send_json_error( array( 'message' => __( 'The test was not sent: there is no valid address to send it to. Add one under Send to.', 'wp-easycart' ) ) );
				}
				if ( ! self::send_summary( $settings['frequency'], true, $settings ) ) {
					wp_send_json_error( array( 'message' => __( 'The test email could not be sent. Check Settings › Email › Sending.', 'wp-easycart' ) ) );
				}
				/* translators: %s: email addresses. */
				wp_send_json_success( array( 'message' => sprintf( __( 'Test email sent to %s.', 'wp-easycart' ), implode( ', ', $to ) ) ) );
			}
			update_option( self::SUMMARY_OPTION, $settings, false );
			self::schedule();
			$names = self::frequencies();
			if ( $settings['on'] ) {
				/* translators: 1: Daily, Weekly or Monthly, 2: email addresses. */
				$message = sprintf( __( 'Summary email saved. %1$s summaries go to %2$s.', 'wp-easycart' ), isset( $names[ $settings['frequency'] ] ) ? $names[ $settings['frequency'] ] : $settings['frequency'], implode( ', ', self::summary_recipients( $settings ) ) );
			} else {
				$message = __( 'Summary email saved. It is off.', 'wp-easycart' );
			}
			wp_send_json_success(
				array(
					'settings' => self::summary_for_js(),
					'message'  => $message,
				)
			);
		}

		/**
		 * The optional sections a summary can carry ( WP EasyCart PRO adds its own ).
		 *
		 * @return array key => label.
		 */
		public static function summary_section_choices() {
			/**
			 * Optional sections of the summary email ( key => label ).
			 *
			 * @since 6.0.2
			 * @param array $choices Choices.
			 */
			return (array) apply_filters( 'wp_easycart_reports_summary_section_choices', array() );
		}

		/**
		 * The summary settings for the page script.
		 *
		 * @return array
		 */
		public static function summary_for_js() {
			$settings = self::summary_settings();
			return array(
				'on'          => (int) $settings['on'],
				'frequency'   => $settings['frequency'],
				'recipients'  => $settings['recipients'],
				'default_to'  => (string) get_option( 'admin_email' ),
				'sections'    => array_values( (array) $settings['sections'] ),
				'offer'       => $settings['offer'],
				'frequencies' => self::frequencies(),
				'choices'     => self::summary_section_choices(),
				'pro'         => count( self::frequencies() ) > 1,
			);
		}

		/** Action admin_init ( and every summary save ): the daily summary check runs while the summary is on. */
		public static function schedule() {
			$settings = self::summary_settings();
			$next     = wp_next_scheduled( self::SUMMARY_CRON );
			if ( $settings['on'] && ! $next ) {
				$time = self::day_start( self::today() ) + 8 * HOUR_IN_SECONDS;
				if ( $time <= time() ) {
					$time += DAY_IN_SECONDS;
				}
				wp_schedule_event( $time, 'daily', self::SUMMARY_CRON );
			} elseif ( ! $settings['on'] && $next ) {
				wp_clear_scheduled_hook( self::SUMMARY_CRON );
			}
		}

		/**
		 * The first day of the period today is in: today ( daily ), the week's first day ( start_of_week ), the 1st ( monthly ).
		 *
		 * @param string $frequency daily | weekly | monthly.
		 * @return string Y-m-d.
		 */
		public static function summary_period_start( $frequency ) {
			$today = self::today();
			$time  = strtotime( $today . ' 12:00:00 UTC' );
			if ( 'daily' === $frequency ) {
				return $today;
			}
			if ( 'monthly' === $frequency ) {
				return gmdate( 'Y-m-01', $time );
			}
			$back = ( (int) gmdate( 'w', $time ) - (int) get_option( 'start_of_week', 1 ) + 7 ) % 7;
			return gmdate( 'Y-m-d', $time - $back * DAY_IN_SECONDS );
		}

		/**
		 * Cron: send the summary when it is due: the last one went out before the current day, week ( from the week's first
		 * day ) or month began, so a job WP-Cron ran after midnight, or days late, still sends it. A summary never sent goes
		 * out on the period's first day or the day after.
		 */
		public static function send_due_summary() {
			$settings = self::summary_settings();
			if ( ! $settings['on'] ) {
				return;
			}
			$today = self::today();
			$last  = (string) $settings['last'];
			if ( 'daily' === $settings['frequency'] ) {
				$due = ( $last !== $today );
			} else {
				$start = self::summary_period_start( $settings['frequency'] );
				/* Never sent: from the period's first day ( a day late still counts ), not at once for a period that began days ago. */
				$due = ( '' === $last ) ? ( $today <= gmdate( 'Y-m-d', strtotime( $start . ' 12:00:00 UTC' ) + DAY_IN_SECONDS ) ) : ( $last < $start );
			}
			if ( ! $due ) {
				return;
			}
			if ( self::send_summary( $settings['frequency'] ) ) {
				$settings['last'] = $today;
				update_option( self::SUMMARY_OPTION, $settings, false );
			}
		}

		/**
		 * The period a summary covers: the last full day, week or month before the current one began
		 * ( summary_period_start() ), and the one before it.
		 *
		 * @param string $frequency daily | weekly | monthly.
		 * @return array args() for the period ( with start2 / end2 ).
		 */
		public static function summary_period( $frequency ) {
			$begins = strtotime( self::summary_period_start( $frequency ) . ' 12:00:00 UTC' );
			$before = $begins - DAY_IN_SECONDS; /* the covered period's last day */
			if ( 'daily' === $frequency ) {
				$start  = gmdate( 'Y-m-d', $before );
				$end    = $start;
				$start2 = gmdate( 'Y-m-d', $before - 7 * DAY_IN_SECONDS );
				$end2   = $start2;
			} elseif ( 'monthly' === $frequency ) {
				$end    = gmdate( 'Y-m-d', $before );
				$start  = substr( $end, 0, 8 ) . '01';
				$end2   = gmdate( 'Y-m-d', strtotime( $start . ' 12:00:00 UTC' ) - DAY_IN_SECONDS );
				$start2 = substr( $end2, 0, 8 ) . '01';
			} else {
				$end    = gmdate( 'Y-m-d', $before );
				$start  = gmdate( 'Y-m-d', $before - 6 * DAY_IN_SECONDS );
				$end2   = gmdate( 'Y-m-d', $before - 7 * DAY_IN_SECONDS );
				$start2 = gmdate( 'Y-m-d', $before - 13 * DAY_IN_SECONDS );
			}
			return self::args(
				array(
					'start'  => $start,
					'end'    => $end,
					'start2' => $start2,
					'end2'   => $end2,
					'range'  => 'daily',
				)
			);
		}

		/**
		 * Send the summary.
		 *
		 * @param string     $frequency daily | weekly | monthly.
		 * @param bool       $test      A test from the Reports page.
		 * @param array|null $settings  summary_settings() to use ( a test uses the dialog's unsaved choices ); default the saved ones.
		 * @return bool
		 */
		public static function send_summary( $frequency, $test = false, $settings = null ) {
			if ( ! class_exists( 'ec_email' ) ) {
				return false;
			}
			$settings = is_array( $settings ) ? array_merge( self::summary_settings(), $settings ) : self::summary_settings();
			$to       = self::summary_recipients( $settings );
			if ( empty( $to ) ) {
				return false;
			}
			$args  = self::summary_period( $frequency );
			$cur   = self::totals( $args['start'], $args['end'], $args );
			$prev  = self::totals( $args['start2'], $args['end2'], $args );
			$store = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
			$label = self::range_label( $args['start'], $args['end'] );
			$names = array(
				'daily'   => __( 'Daily', 'wp-easycart' ),
				'weekly'  => __( 'Weekly', 'wp-easycart' ),
				'monthly' => __( 'Monthly', 'wp-easycart' ),
			);
			/* translators: 1: Daily, Weekly or Monthly, 2: store name, 3: date range. */
			$subject = sprintf( __( '%1$s summary for %2$s: %3$s', 'wp-easycart' ), isset( $names[ $frequency ] ) ? $names[ $frequency ] : $names['weekly'], $store, $label );
			if ( $test ) {
				$subject = '[' . __( 'Test', 'wp-easycart' ) . '] ' . $subject;
			}
			$rows = array(
				array( __( 'Sales', 'wp-easycart' ), $cur['sales'], $prev['sales'], 'money' ),
				array( __( 'Orders', 'wp-easycart' ), $cur['orders'], $prev['orders'], 'int' ),
				array( __( 'Average order', 'wp-easycart' ), $cur['aov'], $prev['aov'], 'money' ),
				array( __( 'Refunds', 'wp-easycart' ), $cur['refunds'], $prev['refunds'], 'money' ),
				array( __( 'Net sales', 'wp-easycart' ), $cur['net'], $prev['net'], 'money' ),
				array( __( 'New customers', 'wp-easycart' ), $cur['new_customers'], $prev['new_customers'], 'int' ),
			);
			if ( null !== $cur['visits'] ) {
				$rows[] = array( __( 'Visits', 'wp-easycart' ), $cur['visits'], $prev['visits'], 'int' );
			}
			$body  = '<p>' . esc_html( sprintf( /* translators: 1: date range, 2: date range. */ __( '%1$s, compared with %2$s.', 'wp-easycart' ), $label, self::range_label( $args['start2'], $args['end2'] ) ) ) . '</p>';
			$body .= self::summary_table( $rows );
			$days  = self::daily( $args['start'], $args['end'], $args );
			foreach ( self::insights( $cur, $prev, $days, $args ) as $insight ) {
				$body .= '<p style="margin:0 0 8px;">' . esc_html( $insight['text'] ) . '</p>';
			}
			/**
			 * Extra sections of the summary email: each title and rows ( label, value ).
			 *
			 * @since 6.0.2
			 * @param array  $sections  Sections.
			 * @param array  $args      The period ( args() with start2 / end2 ).
			 * @param string $frequency daily | weekly | monthly.
			 * @param array  $chosen    Section keys the store picked.
			 */
			$sections = (array) apply_filters( 'wp_easycart_reports_summary_sections', array(), $args, $frequency, (array) $settings['sections'] );
			foreach ( $sections as $section ) {
				if ( ! is_array( $section ) || empty( $section['title'] ) ) {
					continue;
				}
				$body .= '<h3 style="margin:20px 0 8px;font-size:15px;">' . esc_html( $section['title'] ) . '</h3>';
				if ( ! empty( $section['rows'] ) && is_array( $section['rows'] ) ) {
					$lines = array();
					foreach ( $section['rows'] as $row ) {
						$row     = is_array( $row ) ? array_values( $row ) : array();
						$lines[] = array( isset( $row[0] ) && is_scalar( $row[0] ) ? (string) $row[0] : '', isset( $row[1] ) && is_scalar( $row[1] ) ? (string) $row[1] : '' );
					}
					$body .= self::summary_rows( $lines, false );
				}
			}
			if ( empty( $sections ) && 'upsell' === self::tab_state( 'products' ) ) {
				/* translators: %s: plan name. */
				$body .= '<p style="margin:16px 0 0;color:#6b7280;font-size:13px;">' . esc_html( sprintf( __( '%s adds your top products, codes, recovered carts and low stock to this email.', 'wp-easycart' ), class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::plan_name() : __( 'Pro/Premium', 'wp-easycart' ) ) ) . '</p>'; // WP-Cron loads no admin classes: no plan known.
			}
			$url  = admin_url( 'admin.php?page=wp-easycart-dashboard' );
			$html = class_exists( 'wp_easycart_email_design' ) ? wp_easycart_email_design::wrap(
				$body,
				array(
					'heading'     => $store,
					'button_url'  => $url,
					'button_text' => __( 'Open Reports', 'wp-easycart' ),
				)
			) : $body . '<p><a href="' . esc_url( $url ) . '">' . esc_html__( 'Open Reports', 'wp-easycart' ) . '</a></p>';
			return (bool) ec_email::send(
				implode( ',', $to ),
				$subject,
				$html,
				array(
					'type'    => 'reports_summary',
					'channel' => 'order',
				)
			);
		}

		/**
		 * The summary's numbers as an email table.
		 *
		 * @param array $rows label, value, previous, format.
		 * @return string
		 */
		private static function summary_table( $rows ) {
			$lines = array();
			foreach ( $rows as $row ) {
				list( $label, $value, $prev, $format ) = $row;
				if ( null === $value ) {
					continue;
				}
				$show   = ( 'money' === $format ) ? self::money( $value ) : number_format_i18n( (float) $value );
				$change = '';
				if ( null !== $prev && abs( (float) $prev ) >= 0.005 ) {
					$pct    = ( (float) $value - (float) $prev ) / abs( (float) $prev ) * 100;
					$change = ( $pct >= 0 ? '▲ ' : '▼ ' ) . number_format_i18n( abs( $pct ), 0 ) . '%';
				}
				$lines[] = array( $label, $show, $change );
			}
			return self::summary_rows( $lines, true );
		}

		/**
		 * Rows of the summary email: the label on the left and the value in the last column, against the right edge, so the
		 * numbers and every section's values ( Low stock, Top products ) line up there; a change, when a row has one, sits
		 * just before its value. A plain table with inline styles plus align / width / cellpadding attributes, so mail programs
		 * without CSS layout ( Outlook's Word engine, Gmail ) draw it the same.
		 *
		 * @param array $rows   Each: label, value and an optional change ( plain text ).
		 * @param bool  $strong Values in bold ( the main numbers ).
		 * @return string
		 */
		private static function summary_rows( $rows, $strong = false ) {
			$changes = false;
			foreach ( $rows as $row ) {
				if ( isset( $row[2] ) && '' !== (string) $row[2] ) {
					$changes = true;
					break;
				}
			}
			$cell = 'padding:8px 0;border-bottom:1px solid #eeeeee;font-size:14px;line-height:20px;vertical-align:top;';
			$html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;margin:0 0 16px;">';
			foreach ( $rows as $row ) {
				$html .= '<tr>';
				$html .= '<td align="left" style="' . $cell . 'text-align:left;">' . esc_html( isset( $row[0] ) ? (string) $row[0] : '' ) . '</td>';
				if ( $changes ) {
					$html .= '<td align="right" width="1%" style="' . $cell . 'padding-left:16px;text-align:right;white-space:nowrap;color:#6b7280;font-size:13px;">' . esc_html( isset( $row[2] ) ? (string) $row[2] : '' ) . '</td>';
				}
				$html .= '<td align="right" width="1%" style="' . $cell . 'padding-left:16px;text-align:right;white-space:nowrap;' . ( $strong ? 'font-weight:600;' : '' ) . '">' . esc_html( isset( $row[1] ) ? (string) $row[1] : '' ) . '</td>';
				$html .= '</tr>';
			}
			return $html . '</table>';
		}

		// ------------------------------------------------------------------
		// Page.
		// ------------------------------------------------------------------

		/**
		 * Action admin_enqueue_scripts: the Reports page's script and styles.
		 */
		public static function enqueue() {
			if ( ! isset( $_GET['page'] ) || 'wp-easycart-dashboard' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which admin page is open.
				return;
			}
			wp_enqueue_style( 'wp_easycart_reports_v2', plugins_url( 'wp-easycart/admin/css/reports-v2.css', EC_PLUGIN_DIRECTORY ), array(), EC_CURRENT_VERSION );
			wp_enqueue_script( 'wp_easycart_reports_v2', plugins_url( 'wp-easycart/admin/js/reports-v2.js', EC_PLUGIN_DIRECTORY ), array( 'jquery', 'wp_easycart_moment_js', 'wp_easycart_charts_js', 'wp_easycart_daterange_js' ), EC_CURRENT_VERSION, true );
		}

		/**
		 * What the page script starts with ( the Overview for the last 30 days is drawn without a request ).
		 *
		 * @return array
		 */
		public static function page_config() {
			$args  = self::args(
				array(
					'start' => gmdate( 'Y-m-d', strtotime( self::today() . ' 12:00:00 UTC' ) - 29 * DAY_IN_SECONDS ),
					'end'   => self::today(),
				)
			);
			$tabs  = array();
			$state = array();
			foreach ( self::tabs() as $id => $tab ) {
				$tab_state    = self::tab_state( $id );
				$tabs[]       = array(
					'id'    => $id,
					'label' => $tab['label'],
					'plan'  => $tab['plan'],
					'state' => $tab_state,
					'desc'  => isset( $tab['desc'] ) ? $tab['desc'] : '',
					'badge' => ( 'free' === $tab_state || 'enabled' === $tab_state ) ? '' : ( ( 'update' === $tab_state ) ? __( 'Update', 'wp-easycart' ) : ( class_exists( 'wp_easycart_admin_upsell' ) ? wp_easycart_admin_upsell::badge_for( 'reports', 'pro', isset( $tab['feature'] ) ? $tab['feature'] : $id ) : 'Pro' ) ),
				);
				$state[ $id ] = $tab_state;
			}
			/**
			 * Saved report views for this person ( WP EasyCart PRO ); null while locked.
			 *
			 * @since 6.0.2
			 * @param array|null $views Views ( id, name, state ).
			 */
			$views = apply_filters( 'wp_easycart_reports_saved_views', null );
			/**
			 * The research export ( WP EasyCart PRO ): array( 'action' => AJAX action, 'nonce' => … ), null while locked.
			 *
			 * @since 6.0.2
			 * @param array|null $research Research export.
			 */
			$research = apply_filters( 'wp_easycart_reports_research_export', null );
			/* Bug round 7: Cookie consent set to a choice nothing on this site can answer, so browsers never send views, searches or visits. */
			$block = ( class_exists( 'wp_easycart_store_activity' ) && method_exists( 'wp_easycart_store_activity', 'consent_block' ) ) ? wp_easycart_store_activity::consent_block() : array();
			/* The chips on the locked Views menu and Research export: Update when only a newer WP EasyCart PRO is missing, else the store's plan. */
			$locks = array();
			foreach ( array( 'views', 'export' ) as $feature ) {
				$upsell            = class_exists( 'wp_easycart_admin_upsell' );
				$locks[ $feature ] = array(
					'badge'  => $upsell ? wp_easycart_admin_upsell::badge_for( 'reports', 'pro', $feature ) : __( 'Pro/Premium', 'wp-easycart' ),
					'update' => $upsell && '' !== wp_easycart_admin_upsell::update_version( 'reports', $feature ),
				);
			}
			return array(
				'ajax'      => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'wp-easycart-ecv2-reports' ),
				'today'     => self::today(),
				'weekStart' => (int) get_option( 'start_of_week', 1 ),
				'money'     => self::money_config(),
				'tabs'      => $tabs,
				'args'      => $args,
				'initial'   => self::payload( array_merge( $args, array( 'tab' => 'overview' ) ) ),
				'summary'   => self::summary_for_js(),
				'views'     => is_array( $views ) ? array_values( $views ) : null,
				'research'  => is_array( $research ) ? $research : null,
				'locks'     => $locks,
				'activity'  => array(
					'on'         => class_exists( 'wp_easycart_store_activity' ) && wp_easycart_store_activity::enabled(),
					'url'        => admin_url( 'admin.php?page=wp-easycart-settings&subpage=integrations' ),
					'consent'    => $block ? $block['message'] : '',
					'consentUrl' => admin_url( 'admin.php?page=wp-easycart-settings&subpage=integrations#ecst-sec-cookie-consent' ),
				),
				'upgrade'   => array(
					'trial' => ( function_exists( 'wp_easycart_admin' ) && method_exists( wp_easycart_admin(), 'pro_install_url' ) ) ? wp_easycart_admin()->pro_install_url( 'trial', true ) : '',
				),
				'text'      => self::js_text(),
			);
		}

		/**
		 * The summary dialog's line about daily and monthly summaries while they are locked: an update when only a newer
		 * WP EasyCart PRO is missing, else the store's plan.
		 *
		 * @return string
		 */
		private static function summary_locked_text() {
			$update = class_exists( 'wp_easycart_admin_upsell' ) ? wp_easycart_admin_upsell::update_version( 'reports', 'digest' ) : '';
			if ( '' !== $update ) {
				/* translators: %s: WP EasyCart PRO version number. */
				return sprintf( __( 'Daily and monthly summaries, and extra sections, come with WP EasyCart PRO %s. Update the plugin to use them.', 'wp-easycart' ), $update );
			}
			/* translators: %s: plan name, Pro/Premium, Pro or Premium. */
			return sprintf( __( 'Daily and monthly summaries, and extra sections, come with %s.', 'wp-easycart' ), class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::plan_name() : __( 'Pro/Premium', 'wp-easycart' ) );
		}

		/**
		 * Words the page script needs.
		 *
		 * @return array
		 */
		private static function js_text() {
			return array(
				'today'           => __( 'Today', 'wp-easycart' ),
				'yesterday'       => __( 'Yesterday', 'wp-easycart' ),
				'last7'           => __( 'Last 7 days', 'wp-easycart' ),
				'last30'          => __( 'Last 30 days', 'wp-easycart' ),
				'last90'          => __( 'Last 90 days', 'wp-easycart' ),
				'thisMonth'       => __( 'This month', 'wp-easycart' ),
				'lastMonth'       => __( 'Last month', 'wp-easycart' ),
				'thisQuarter'     => __( 'This quarter', 'wp-easycart' ),
				'lastQuarter'     => __( 'Last quarter', 'wp-easycart' ),
				'thisYear'        => __( 'This year', 'wp-easycart' ),
				'lastYear'        => __( 'Last year', 'wp-easycart' ),
				'last12'          => __( 'Last 12 months', 'wp-easycart' ),
				'custom'          => __( 'Custom range', 'wp-easycart' ),
				'apply'           => __( 'Apply', 'wp-easycart' ),
				'cancel'          => __( 'Cancel', 'wp-easycart' ),
				'noCompare'       => __( 'No comparison', 'wp-easycart' ),
				'prevPeriod'      => __( 'Previous period', 'wp-easycart' ),
				'prevYear'        => __( 'Same dates last year', 'wp-easycart' ),
				'vs'              => __( 'vs', 'wp-easycart' ),
				'loading'         => __( 'Loading…', 'wp-easycart' ),
				'error'           => __( 'The report could not be loaded.', 'wp-easycart' ),
				'retry'           => __( 'Try again', 'wp-easycart' ),
				/* translators: %d: number of rows. */
				'showAll'         => __( 'Show all %d', 'wp-easycart' ),
				'empty'           => __( 'Nothing in this range yet.', 'wp-easycart' ),
				'csv'             => __( 'CSV', 'wp-easycart' ),
				'downloadCsv'     => __( 'Download this table as CSV', 'wp-easycart' ),
				'teaserNote'      => __( 'Your top row, from your own orders. The rest opens with the report.', 'wp-easycart' ),
				'teaserEmpty'     => __( 'No data in this range yet. The report fills in as orders come in.', 'wp-easycart' ),
				'summaryOffer'    => __( 'Get these numbers by email every week.', 'wp-easycart' ),
				'summaryOfferOn'  => __( 'Turn on', 'wp-easycart' ),
				'summaryNoThanks' => __( 'No thanks', 'wp-easycart' ),
				'summaryTitle'    => __( 'Summary email', 'wp-easycart' ),
				'summaryOn'       => __( 'Email me a summary', 'wp-easycart' ),
				'summaryHow'      => __( 'How often', 'wp-easycart' ),
				'summaryTo'       => __( 'Send to', 'wp-easycart' ),
				'summaryToHint'   => __( 'Separate addresses with commas. Leave empty for the site admin.', 'wp-easycart' ),
				'summarySections' => __( 'Also include', 'wp-easycart' ),
				'summaryTest'     => __( 'Send a test', 'wp-easycart' ),
				'summaryTestSent' => __( 'Test email sent.', 'wp-easycart' ),
				/* A test's answer never came back ( or could not be read ): the email may still have gone. */
				'summaryNoReply'  => __( 'No answer came back from your site, so the test may not have been sent. Check your inbox, then Settings › Email › Sending.', 'wp-easycart' ),
				'summarySaveFail' => __( 'The summary email settings could not be saved. Try again.', 'wp-easycart' ),
				'summaryProFreq'  => self::summary_locked_text(),
				'save'            => __( 'Save', 'wp-easycart' ),
				'saving'          => __( 'Saving…', 'wp-easycart' ),
				'saved'           => __( 'Saved.', 'wp-easycart' ),
				'sending'         => __( 'Sending…', 'wp-easycart' ),
				'close'           => __( 'Close', 'wp-easycart' ),
				'views'           => __( 'Saved views', 'wp-easycart' ),
				'viewSave'        => __( 'Save this view', 'wp-easycart' ),
				'viewName'        => __( 'Name this view', 'wp-easycart' ),
				'viewDelete'      => __( 'Delete', 'wp-easycart' ),
				'viewNone'        => __( 'No saved views yet.', 'wp-easycart' ),
				'research'        => __( 'Research export', 'wp-easycart' ),
				'researchHint'    => __( 'Every order, line, payment, refund, customer and daily count as CSV files, for a spreadsheet or analysis tool.', 'wp-easycart' ),
				'researchBusy'    => __( 'Preparing the files…', 'wp-easycart' ),
				'researchReady'   => __( 'Your files are ready.', 'wp-easycart' ),
				'download'        => __( 'Download', 'wp-easycart' ),
				'activityOff'     => __( 'Store activity is off, so visits, views and searches are not counted.', 'wp-easycart' ),
				'activitySwitch'  => __( 'Turn it on', 'wp-easycart' ),
				'activityConsent' => __( 'Open Cookie consent', 'wp-easycart' ),
				'previous'        => __( 'Previous', 'wp-easycart' ),
				'history'         => __( 'Some numbers are still being worked out.', 'wp-easycart' ),
			);
		}

		// ------------------------------------------------------------------
		// Small helpers.
		// ------------------------------------------------------------------

		/**
		 * Does a table exist ( per request )?
		 *
		 * @param string $table Table.
		 * @return bool
		 */
		public static function table_exists( $table ) {
			static $known = array();
			global $wpdb;
			if ( ! isset( $known[ $table ] ) ) {
				$known[ $table ] = ( $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) );
			}
			return $known[ $table ];
		}

		/**
		 * Does a column exist ( per request )?
		 *
		 * @param string $table  Table.
		 * @param string $column Column.
		 * @return bool
		 */
		public static function has_column( $table, $column ) {
			static $known = array();
			global $wpdb;
			$key = $table . '.' . $column;
			if ( ! isset( $known[ $key ] ) ) {
				$table         = preg_replace( '/[^a-z_]/', '', $table );
				$known[ $key ] = (bool) $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", $column ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is cleaned to [a-z_].
			}
			return $known[ $key ];
		}
	}

	wp_easycart_reports::init();

endif;
