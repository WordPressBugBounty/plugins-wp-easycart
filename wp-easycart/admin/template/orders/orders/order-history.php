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
$ecodv2_hist_created  = ( $ecodv2_hist_order && ! empty( $ecodv2_hist_order->order_date ) ) ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $ecodv2_hist_order->order_date ) ) : '';
$ecodv2_hist_total    = ( $ecodv2_hist_order && isset( $ecodv2_hist_order->grand_total ) ) ? $GLOBALS['currency']->get_currency_display( (float) $ecodv2_hist_order->grand_total ) : '';
$ecodv2_hist_status   = ( $ecodv2_hist_order && isset( $ecodv2_hist_order->order_status ) ) ? (string) $ecodv2_hist_order->order_status : '';
$ecodv2_hist_user     = wp_get_current_user();
$ecodv2_hist_who      = ( $ecodv2_hist_user && $ecodv2_hist_user->display_name ) ? $ecodv2_hist_user->display_name : __( 'Store admin', 'wp-easycart' );

/* What PRO would have logged for an order like this one. Sample copy, clearly marked. */
$ecodv2_hist_samples = array(
	array( 'dashicons-money-alt', sprintf( __( 'Payment of %s captured', 'wp-easycart' ), '' !== $ecodv2_hist_total ? $ecodv2_hist_total : '$0.00' ), __( 'Gateway, transaction ID and card type are recorded.', 'wp-easycart' ) ),
	array( 'dashicons-email-alt', __( 'Receipt emailed to the customer', 'wp-easycart' ), __( 'Every email sent from this order, with its subject and time.', 'wp-easycart' ) ),
	array( 'dashicons-flag', '' !== $ecodv2_hist_status ? sprintf( __( 'Status changed to “%s”', 'wp-easycart' ), $ecodv2_hist_status ) : __( 'Status changed', 'wp-easycart' ), sprintf( __( 'By %s — who changed what, and when.', 'wp-easycart' ), $ecodv2_hist_who ) ),
	array( 'dashicons-format-status', __( 'Staff note added', 'wp-easycart' ), __( 'Internal comments your team leaves on the order.', 'wp-easycart' ) ),
);
?>
<div class="ec_admin_settings_input ecodv2-history-free">
	<div class="wpeasycart-timeline-container">
		<div class="wpeasycart-timeline-container-inner">
			<h4><?php esc_attr_e( 'Order History', 'wp-easycart' ); ?></h4>
			<div class="wpeasycart-timline-scrollbox">
				<div class="wpeasycart-timeline-content-container">
					<div class="wpeasycart-timeline">

						<div class="wpeasycart-timeline-item">
							<span class="dashicons dashicons-plus-alt"></span>
							<div class="wpeasycart-timeline-item-info">
								<a href="#" onclick="return false;"><?php echo esc_html( sprintf( __( 'Order #%d was created', 'wp-easycart' ), $ecodv2_hist_order_id ) ); ?></a>
								<small><?php echo '' !== $ecodv2_hist_created ? esc_html( $ecodv2_hist_created ) : esc_html__( 'The only event the free edition records.', 'wp-easycart' ); ?></small>
							</div>
						</div>

						<?php foreach ( $ecodv2_hist_samples as $ecodv2_hs ) : ?>
						<div class="wpeasycart-timeline-item is-locked" onclick="ecodv2_locked( 'history' ); return false;">
							<span class="dashicons <?php echo esc_attr( $ecodv2_hs[0] ); ?>"></span>
							<div class="wpeasycart-timeline-item-info">
								<a href="#" onclick="ecodv2_locked( 'history' ); return false;"><?php echo esc_html( $ecodv2_hs[1] ); ?> <span class="ecodv2-pro-pill"><?php echo esc_html( wp_easycart_admin_edition::badge( 'pro' ) ); ?></span></a>
								<small><?php echo esc_html( $ecodv2_hs[2] ); ?></small>
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