<?php
/**
 * Order History — free stub ( V2.1 Activity timeline ).
 *
 * Renders inside the Activity card via the
 * 'wp_easycart_admin_order_details_left_content_end' hook. The PRO plugin
 * replaces this output with the full order log using the same
 * wpeasycart-timeline* class names, so both share one visual language
 * ( styled in admin-order-details-v2.css ).
 *
 * Free edition: the one real event we know ( creation ) is followed by a
 * dimmed sample of the entries PRO would have recorded for this order, so the
 * merchant sees the shape of the log rather than a lock icon. Every locked
 * entry opens the orders upsell on the 'history' feature.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ecodv2_hist_order_id = isset( $_GET['order_id'] ) ? (int) $_GET['order_id'] : 0;
$ecodv2_hist_order    = ( function_exists( 'wp_easycart_admin_orders' ) && isset( wp_easycart_admin_orders()->order_details->order ) ) ? wp_easycart_admin_orders()->order_details->order : null;
/* The order date is the database's clock: read in site time as the order screen's Placed step reads it. */
$ecodv2_hist_at       = ( $ecodv2_hist_order && ! empty( $ecodv2_hist_order->order_date ) ) ? ( ( class_exists( 'wp_easycart_admin_order_screen' ) && method_exists( 'wp_easycart_admin_order_screen', 'local_time' ) ) ? wp_easycart_admin_order_screen::local_time( $ecodv2_hist_order->order_date ) : strtotime( $ecodv2_hist_order->order_date ) ) : 0;
$ecodv2_hist_created  = $ecodv2_hist_at ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ecodv2_hist_at ) : '';
$ecodv2_hist_day      = $ecodv2_hist_at ? date_i18n( get_option( 'date_format' ), $ecodv2_hist_at ) : '';
$ecodv2_hist_time     = $ecodv2_hist_at ? date_i18n( get_option( 'time_format' ), $ecodv2_hist_at ) : '';
$ecodv2_hist_total    = ( $ecodv2_hist_order && isset( $ecodv2_hist_order->grand_total ) ) ? $GLOBALS['currency']->get_currency_display( (float) $ecodv2_hist_order->grand_total ) : '';
$ecodv2_hist_status   = ( $ecodv2_hist_order && isset( $ecodv2_hist_order->order_status ) ) ? (string) $ecodv2_hist_order->order_status : '';
$ecodv2_hist_user     = wp_get_current_user();
$ecodv2_hist_who      = ( $ecodv2_hist_user && $ecodv2_hist_user->display_name ) ? $ecodv2_hist_user->display_name : __( 'Store admin', 'wp-easycart' );

/* What PRO would have logged for an order like this one. Sample copy, clearly marked. */
$ecodv2_hist_samples = array(
	array( 'dashicons-money-alt', 'paid', sprintf( __( 'Payment of %s captured', 'wp-easycart' ), '' !== $ecodv2_hist_total ? $ecodv2_hist_total : '$0.00' ), __( 'Gateway, transaction ID and card type are recorded.', 'wp-easycart' ) ),
	array( 'dashicons-email-alt', 'email', __( 'Receipt emailed to the customer', 'wp-easycart' ), __( 'Every email sent from this order, with its subject and time.', 'wp-easycart' ) ),
	array( 'dashicons-flag', 'status', '' !== $ecodv2_hist_status ? sprintf( __( 'Status changed to “%s”', 'wp-easycart' ), $ecodv2_hist_status ) : __( 'Status changed', 'wp-easycart' ), sprintf( __( 'By %s — who changed what, and when.', 'wp-easycart' ), $ecodv2_hist_who ) ),
	array( 'dashicons-format-status', 'note', __( 'Staff note added', 'wp-easycart' ), __( 'Internal comments your team leaves on the order.', 'wp-easycart' ) ),
);
?>
<div class="ec_admin_settings_input ecodv2-history-free">
	<div class="wpeasycart-timeline-container">
		<div class="wpeasycart-timeline-container-inner">
			<h4><?php esc_attr_e( 'Order History', 'wp-easycart' ); ?></h4>
			<div class="wpeasycart-timline-scrollbox">
				<div class="wpeasycart-timeline-content-container">
					<div class="wpeasycart-timeline">

						<div class="wpeasycart-timeline-item ecodv2-tl-item<?php echo '' !== $ecodv2_hist_day ? ' is-day-first' : ''; ?>" data-cat="all" data-tone="placed" data-day="<?php echo esc_attr( $ecodv2_hist_day ); ?>">
							<div class="ecodv2-tl-day"><?php echo esc_html( $ecodv2_hist_day ); ?></div>
							<span class="dashicons dashicons-plus-alt" aria-hidden="true"></span>
							<div class="wpeasycart-timeline-item-info">
								<div class="ecodv2-tl-head">
									<span class="ecodv2-tl-title"><?php echo esc_html( sprintf( /* translators: %d: order number. */ __( 'Order #%d placed', 'wp-easycart' ), $ecodv2_hist_order_id ) ); ?></span>
									<?php if ( '' !== $ecodv2_hist_time ) { ?><span class="ecodv2-tl-time" title="<?php echo esc_attr( $ecodv2_hist_created ); ?>"><?php echo esc_html( $ecodv2_hist_time ); ?></span><?php } ?>
								</div>
								<small><?php esc_html_e( 'The only event the free edition records.', 'wp-easycart' ); ?></small>
							</div>
						</div>

						<?php foreach ( $ecodv2_hist_samples as $ecodv2_hs ) : ?>
						<div class="wpeasycart-timeline-item ecodv2-tl-item is-locked" data-cat="all" data-tone="<?php echo esc_attr( $ecodv2_hs[1] ); ?>" onclick="ecodv2_locked( 'history' ); return false;">
							<span class="dashicons <?php echo esc_attr( $ecodv2_hs[0] ); ?>" aria-hidden="true"></span>
							<div class="wpeasycart-timeline-item-info">
								<div class="ecodv2-tl-head">
									<a href="#" class="ecodv2-tl-title" onclick="ecodv2_locked( 'history' ); return false;"><?php echo esc_html( $ecodv2_hs[2] ); ?></a>
									<span class="ecodv2-pro-pill"><?php echo esc_html( wp_easycart_admin_edition::badge( 'pro' ) ); ?></span>
								</div>
								<small><?php echo esc_html( $ecodv2_hs[3] ); ?></small>
							</div>
						</div>
						<?php endforeach; ?>

					</div>
				</div>
			</div>
			<button type="button" class="ecodv2-history-cta" onclick="ecodv2_locked( 'history' ); return false;">
				<span class="dashicons dashicons-backup"></span>
				<span class="ecodv2-history-cta-text">
					<strong><?php echo esc_html( sprintf( /* translators: %s: plan name ( Pro/Premium, Pro or Premium ). */ __( 'See the full order log with %s', 'wp-easycart' ), wp_easycart_admin_edition::plan_name() ) ); ?></strong>
					<span><?php echo esc_html( sprintf( /* translators: %s: plan name ( Pro/Premium, Pro or Premium ). */ __( 'Entries above are examples. %s records payments, refunds, status changes, emails and staff notes for every order, automatically.', 'wp-easycart' ), wp_easycart_admin_edition::plan_name() ) ); ?></span>
				</span>
				<span class="ecodv2-pro-pill"><?php echo esc_html( wp_easycart_admin_edition::badge( 'pro' ) ); ?></span>
			</button>
		</div>
	</div>
</div>