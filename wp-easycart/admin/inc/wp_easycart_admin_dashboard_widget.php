<?php
/**
 * WP EasyCart — the store on the WordPress Dashboard ( 6.0.5, admin review L13 ).
 *
 * A small widget for store administrators and managers: today's sales and orders ( wp_easycart_reports::totals(), the store's own
 * day ), the orders waiting to ship ( the orders list's Awaiting tile, wp_easycart_admin_order_screen::queue() ) with how long the
 * oldest has waited, and links to them and to Reports. Hidden from the Dashboard's Screen Options like any widget; filter
 * wp_easycart_dashboard_widget ( false ) leaves it out.
 *
 * @package WP_EasyCart
 * @since   6.0.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_dashboard_widget' ) ) :

	/**
	 * The Dashboard widget.
	 */
	class wp_easycart_admin_dashboard_widget {

		/**
		 * Hooks.
		 */
		public static function init() {
			add_action( 'wp_dashboard_setup', array( __CLASS__, 'register' ) );
		}

		/**
		 * Adds the widget for people who run the store.
		 */
		public static function register() {
			if ( ! ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_manager' ) ) || ! function_exists( 'wp_add_dashboard_widget' ) ) {
				return;
			}
			/**
			 * Whether the WordPress Dashboard shows the WP EasyCart widget.
			 *
			 * @since 6.0.5
			 * @param bool $show Default true.
			 */
			if ( ! apply_filters( 'wp_easycart_dashboard_widget', true ) ) {
				return;
			}
			wp_add_dashboard_widget( 'wp_easycart_dashboard', __( 'WP EasyCart', 'wp-easycart' ), array( __CLASS__, 'render' ) );
		}

		/**
		 * Today's sales and orders, and the orders waiting to ship.
		 *
		 * @return array sales, orders, ship, ship_hours.
		 */
		public static function numbers() {
			$out = array(
				'sales'      => 0.0,
				'orders'     => 0,
				'ship'       => 0,
				'ship_hours' => 0,
			);
			if ( class_exists( 'wp_easycart_reports' ) ) {
				$today  = wp_easycart_reports::today();
				$totals = wp_easycart_reports::totals( $today, $today, wp_easycart_reports::args( array( 'start' => $today, 'end' => $today ) ) );
				$out['sales']  = isset( $totals['sales'] ) ? (float) $totals['sales'] : 0.0;
				$out['orders'] = isset( $totals['orders'] ) ? (int) $totals['orders'] : 0;
			}
			if ( class_exists( 'wp_easycart_admin_order_screen' ) && method_exists( 'wp_easycart_admin_order_screen', 'queue' ) ) {
				$queue             = wp_easycart_admin_order_screen::queue( 0 );
				$out['ship']       = (int) $queue['count'];
				$out['ship_hours'] = (int) $queue['next_hours'];
			}
			return $out;
		}

		/**
		 * How long the oldest order has waited, in words ( "3 hours", "2 days" ).
		 *
		 * @param int $hours Hours.
		 * @return string
		 */
		private static function waited( $hours ) {
			$hours = max( 0, (int) $hours );
			if ( $hours < 1 ) {
				return __( 'less than an hour', 'wp-easycart' );
			}
			if ( $hours < 48 ) {
				/* translators: %d: hours. */
				return sprintf( _n( '%d hour', '%d hours', $hours, 'wp-easycart' ), $hours );
			}
			$days = (int) floor( $hours / 24 );
			/* translators: %d: days. */
			return sprintf( _n( '%d day', '%d days', $days, 'wp-easycart' ), $days );
		}

		/**
		 * The widget.
		 */
		public static function render() {
			$n      = self::numbers();
			$money  = isset( $GLOBALS['currency'] ) && is_object( $GLOBALS['currency'] ) ? $GLOBALS['currency']->get_currency_display( $n['sales'] ) : number_format_i18n( $n['sales'], 2 );
			$orders = admin_url( 'admin.php?page=wp-easycart-orders&subpage=orders' );
			?>
			<style>
				#wp_easycart_dashboard .wpec-dw { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
				#wp_easycart_dashboard .wpec-dw-stat { padding:12px; border:1px solid #dcdcde; border-radius:6px; min-width:0; }
				#wp_easycart_dashboard .wpec-dw-label { color:#50575e; font-size:12px; }
				#wp_easycart_dashboard .wpec-dw-value { margin:4px 0 2px; font-size:22px; font-weight:600; line-height:1.2; font-variant-numeric:tabular-nums; color:#1d2327; }
				#wp_easycart_dashboard .wpec-dw-sub { color:#50575e; font-size:12px; }
				#wp_easycart_dashboard .wpec-dw-stat.is-due .wpec-dw-value { color:#b32d2e; }
				#wp_easycart_dashboard .wpec-dw-links { display:flex; flex-wrap:wrap; gap:6px 16px; margin:12px 0 0; padding:10px 0 0; border-top:1px solid #f0f0f1; }
			</style>
			<div class="wpec-dw">
				<div class="wpec-dw-stat">
					<div class="wpec-dw-label"><?php esc_html_e( 'Sales today', 'wp-easycart' ); ?></div>
					<div class="wpec-dw-value"><?php echo esc_html( html_entity_decode( wp_strip_all_tags( $money ), ENT_QUOTES, 'UTF-8' ) ); ?></div>
					<div class="wpec-dw-sub"><?php echo esc_html( sprintf( /* translators: %d: orders. */ _n( '%d order', '%d orders', $n['orders'], 'wp-easycart' ), $n['orders'] ) ); ?></div>
				</div>
				<div class="wpec-dw-stat<?php echo $n['ship'] > 0 ? ' is-due' : ''; ?>">
					<div class="wpec-dw-label"><?php esc_html_e( 'Orders to ship', 'wp-easycart' ); ?></div>
					<div class="wpec-dw-value"><?php echo esc_html( number_format_i18n( $n['ship'] ) ); ?></div>
					<div class="wpec-dw-sub"><?php echo esc_html( $n['ship'] > 0 ? sprintf( /* translators: %s: how long, e.g. 3 hours. */ __( 'Oldest waiting %s', 'wp-easycart' ), self::waited( $n['ship_hours'] ) ) : __( 'All caught up', 'wp-easycart' ) ); ?></div>
				</div>
			</div>
			<p class="wpec-dw-links">
				<?php if ( $n['ship'] > 0 ) { ?>
				<a href="<?php echo esc_url( add_query_arg( 'health_filter', 'awaiting', $orders ) ); ?>"><?php esc_html_e( 'Ship orders', 'wp-easycart' ); ?></a>
				<?php } ?>
				<a href="<?php echo esc_url( $orders ); ?>"><?php esc_html_e( 'All orders', 'wp-easycart' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-easycart-dashboard' ) ); ?>"><?php esc_html_e( 'Reports', 'wp-easycart' ); ?></a>
			</p>
			<?php
		}
	}

	wp_easycart_admin_dashboard_widget::init();

endif;
