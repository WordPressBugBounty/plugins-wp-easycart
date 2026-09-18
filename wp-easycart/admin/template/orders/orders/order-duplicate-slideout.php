<?php
/**
 * Duplicate Order — V2 drawer (FREE)
 *
 * Replaces the legacy slideout. orders-v2.js loads the source order via
 * 'ecv2_order_duplicate_get' and creates via 'ecv2_order_duplicate_create'
 * (both in wp_easycart_admin_order_table.php).
 *
 * PRO extension points:
 *  - filter 'wp_easycart_ecv2_order_duplicate_payload' (add data to the load response)
 *  - action 'wp_easycart_ecv2_order_duplicate_fields'  (extra fields, rendered before Notes)
 *  - filter 'wp_easycart_ecv2_order_duplicate_row'     (adjust the new ec_order row)
 *  - action 'wp_easycart_ecv2_order_duplicated'        (after create)
 *
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$ecv2_dup_statuses  = $wpdb->get_results( 'SELECT status_id, order_status, is_approved, color_code FROM ec_orderstatus ORDER BY status_id ASC' );
$ecv2_dup_confirmed = 3; /* "Order Confirmed" default id (see ec_db_manager base data) */
?>
<div class="ecv2-drawer-backdrop ecv2-qe-backdrop" id="ecv2-order-dup-backdrop" onclick="ecv2_order_dup_close();"></div>
<div class="ecv2-qe-drawer ecv2-dup-drawer" id="ecv2-order-dup" role="dialog" aria-modal="true" aria-labelledby="ecv2-dup-title"
	data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp-easycart-ecv2-order-duplicate' ) ); ?>"
	data-default-status="<?php echo esc_attr( $ecv2_dup_confirmed ); ?>"
	data-lang-created="<?php esc_attr_e( 'Order #%d created.', 'wp-easycart' ); ?>"
	data-lang-created-email="<?php esc_attr_e( 'Order #%d created and receipt emailed.', 'wp-easycart' ); ?>"
	data-lang-open="<?php esc_attr_e( 'Open order', 'wp-easycart' ); ?>"
	data-lang-no-items="<?php esc_attr_e( 'Select at least one item.', 'wp-easycart' ); ?>"
	data-lang-refunded="<?php esc_attr_e( '%d refunded', 'wp-easycart' ); ?>"
	data-lang-free="<?php esc_attr_e( 'Free', 'wp-easycart' ); ?>"
	data-lang-download="<?php esc_attr_e( 'Download', 'wp-easycart' ); ?>"
	data-lang-giftcard="<?php esc_attr_e( 'Gift card', 'wp-easycart' ); ?>"
	data-lang-items-selected="<?php esc_attr_e( '%1$d of %2$d items', 'wp-easycart' ); ?>"
	data-lang-select-all="<?php esc_attr_e( 'Select all', 'wp-easycart' ); ?>"
	data-lang-clear-all="<?php esc_attr_e( 'Clear all', 'wp-easycart' ); ?>">

	<div class="ecv2-drawer-header ecv2-qe-header">
		<div class="ecv2-drawer-header-left">
			<span class="dashicons dashicons-admin-page ecv2-drawer-header-icon"></span>
			<h3 class="ecv2-drawer-title" id="ecv2-dup-title"><?php esc_html_e( 'Duplicate', 'wp-easycart' ); ?> <span class="ecv2-qe-order-id" id="ecv2-dup-order-id"></span></h3>
		</div>
		<button type="button" class="ecv2-drawer-close" onclick="ecv2_order_dup_close();" aria-label="<?php esc_attr_e( 'Close', 'wp-easycart' ); ?>"><span class="dashicons dashicons-no-alt"></span></button>
	</div>

	<div class="ecv2-drawer-body ecv2-qe-body" id="ecv2-dup-body">
		<div class="ecv2-qe-loading" id="ecv2-dup-loading"><span class="dashicons dashicons-update ecv2-spin"></span> <?php esc_html_e( 'Loading…', 'wp-easycart' ); ?></div>

		<div class="ecv2-qe-content" id="ecv2-dup-content" style="display:none;">

			<!-- Merchant notice (kept) -->
			<div class="ecv2-dup-notice">
				<span class="dashicons dashicons-info-outline"></span>
				<div>
					<strong><?php esc_html_e( 'No payment is collected and no inventory is adjusted.', 'wp-easycart' ); ?></strong>
					<?php esc_html_e( 'Duplicating creates a new order record only. Collect any payment separately and manage stock as it applies to your situation.', 'wp-easycart' ); ?>
				</div>
			</div>

			<!-- Source summary -->
			<section class="ecv2-qe-summary ecv2-dup-summary">
				<div class="ecv2-qe-summary-row">
					<div class="ecv2-qe-customer">
						<strong id="ecv2-dup-customer"></strong>
						<span class="ecv2-qe-muted" id="ecv2-dup-email"></span>
					</div>
					<div class="ecv2-qe-money">
						<span class="ecv2-qe-muted" id="ecv2-dup-source-meta"></span>
					</div>
				</div>
				<address class="ecv2-qe-address ecv2-dup-address" id="ecv2-dup-address"></address>
			</section>

			<!-- Reason preset -->
			<section class="ecv2-qe-section ecv2-qe-section-edit">
				<div class="ecv2-qe-section-label"><span><?php esc_html_e( 'What is this for?', 'wp-easycart' ); ?></span></div>
				<div class="ecv2-dup-reasons" role="radiogroup">
					<label class="ecv2-dup-reason"><input type="radio" name="ecv2_dup_reason" value="reorder" checked onchange="ecv2_order_dup_reason_changed();" />
						<span class="ecv2-dup-reason-title"><?php esc_html_e( 'Reorder', 'wp-easycart' ); ?></span>
						<span class="ecv2-dup-reason-desc"><?php esc_html_e( 'Customer wants the same thing again. Copies prices; you collect payment.', 'wp-easycart' ); ?></span></label>
					<label class="ecv2-dup-reason"><input type="radio" name="ecv2_dup_reason" value="replacement" onchange="ecv2_order_dup_reason_changed();" />
						<span class="ecv2-dup-reason-title"><?php esc_html_e( 'Replacement / reship', 'wp-easycart' ); ?></span>
						<span class="ecv2-dup-reason-desc"><?php esc_html_e( 'Lost or damaged shipment. Items at no charge, ready to fulfill.', 'wp-easycart' ); ?></span></label>
					<label class="ecv2-dup-reason"><input type="radio" name="ecv2_dup_reason" value="custom" onchange="ecv2_order_dup_reason_changed();" />
						<span class="ecv2-dup-reason-title"><?php esc_html_e( 'Custom', 'wp-easycart' ); ?></span>
						<span class="ecv2-dup-reason-desc"><?php esc_html_e( 'Set every option yourself.', 'wp-easycart' ); ?></span></label>
				</div>
			</section>

			<!-- Items -->
			<section class="ecv2-qe-section ecv2-qe-section-edit">
				<div class="ecv2-qe-section-label">
					<span><?php esc_html_e( 'Items to include', 'wp-easycart' ); ?></span>
					<span class="ecv2-dup-items-tools"><span class="ecv2-qe-count" id="ecv2-dup-item-count"></span> <button type="button" class="ecv2-qe-items-more" onclick="ecv2_order_dup_toggle_all();" id="ecv2-dup-toggle-all"><?php esc_html_e( 'Select all', 'wp-easycart' ); ?></button></span>
				</div>
				<ul class="ecv2-qe-items ecv2-dup-items" id="ecv2-dup-items"></ul>
			</section>

			<!-- Pricing -->
			<section class="ecv2-qe-section ecv2-qe-section-edit">
				<div class="ecv2-qe-section-label"><span><?php esc_html_e( 'Pricing', 'wp-easycart' ); ?></span></div>
				<div class="ecv2-dup-pricing">
					<label class="ecv2-dup-pill"><input type="radio" name="ecv2_dup_pricing" value="copy" checked onchange="ecv2_order_dup_recalc();" /> <?php esc_html_e( 'Copy original prices', 'wp-easycart' ); ?></label>
					<label class="ecv2-dup-pill"><input type="radio" name="ecv2_dup_pricing" value="zero" onchange="ecv2_order_dup_recalc();" /> <?php esc_html_e( 'No charge (all zero)', 'wp-easycart' ); ?></label>
				</div>
				<div class="ecv2-dup-pricing-opts" id="ecv2-dup-pricing-opts">
					<label class="ecv2-qe-check"><input type="checkbox" id="ecv2-dup-include-shipping" checked onchange="ecv2_order_dup_recalc();" /> <?php esc_html_e( 'Include original shipping charge', 'wp-easycart' ); ?> <span class="ecv2-qe-muted" id="ecv2-dup-shipping-amt"></span></label>
					<label class="ecv2-qe-check" id="ecv2-dup-discount-row"><input type="checkbox" id="ecv2-dup-include-discount" checked onchange="ecv2_order_dup_recalc();" /> <?php esc_html_e( 'Keep discounts / promo code', 'wp-easycart' ); ?> <span class="ecv2-qe-muted" id="ecv2-dup-discount-amt"></span></label>
				</div>
				<div class="ecv2-dup-totals" id="ecv2-dup-totals">
					<div><span><?php esc_html_e( 'Subtotal', 'wp-easycart' ); ?></span><span id="ecv2-dup-t-sub"></span></div>
					<div><span><?php esc_html_e( 'Tax (est.)', 'wp-easycart' ); ?></span><span id="ecv2-dup-t-tax"></span></div>
					<div><span><?php esc_html_e( 'Shipping', 'wp-easycart' ); ?></span><span id="ecv2-dup-t-ship"></span></div>
					<div id="ecv2-dup-t-disc-row"><span><?php esc_html_e( 'Discount', 'wp-easycart' ); ?></span><span id="ecv2-dup-t-disc"></span></div>
					<div class="ecv2-dup-totals-grand"><span><?php esc_html_e( 'New order total', 'wp-easycart' ); ?></span><span id="ecv2-dup-t-grand"></span></div>
				</div>
				<p class="ecv2-qe-hint ecv2-dup-hint" id="ecv2-dup-tax-hint"><?php esc_html_e( 'Tax and discounts are scaled from the original in proportion to the items you keep. Review them on the new order if the amounts matter.', 'wp-easycart' ); ?></p>
			</section>

			<!-- Status + fulfillment -->
			<section class="ecv2-qe-section ecv2-qe-section-edit">
				<label class="ecv2-modal-label" for="ecv2-dup-status"><?php esc_html_e( 'New order status', 'wp-easycart' ); ?></label>
				<div class="ecv2-qe-status-field">
					<span class="ecv2-order-status-swatch" id="ecv2-dup-status-swatch"></span>
					<select id="ecv2-dup-status" class="ecv2-select ecv2-qe-select" onchange="ecv2_order_dup_status_changed();">
						<?php foreach ( $ecv2_dup_statuses as $ecv2_dup_status ) : ?>
							<option value="<?php echo esc_attr( $ecv2_dup_status->status_id ); ?>" data-color="<?php echo esc_attr( '' !== (string) $ecv2_dup_status->color_code ? $ecv2_dup_status->color_code : '#e5e7eb' ); ?>"><?php echo esc_html( $ecv2_dup_status->order_status ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<label class="ecv2-qe-check"><input type="checkbox" id="ecv2-dup-keep-method" checked /> <?php esc_html_e( 'Keep shipping method', 'wp-easycart' ); ?> <span class="ecv2-qe-muted" id="ecv2-dup-method-label"></span></label>
				<label class="ecv2-qe-check"><input type="checkbox" id="ecv2-dup-copy-cust-notes" /> <?php esc_html_e( 'Copy customer checkout note', 'wp-easycart' ); ?></label>
				<p class="ecv2-qe-hint ecv2-dup-hint"><?php esc_html_e( 'Tracking, payment details, refunds and transaction IDs are never copied.', 'wp-easycart' ); ?></p>
			</section>

			<?php do_action( 'wp_easycart_ecv2_order_duplicate_fields' ); ?>

			<!-- Note -->
			<section class="ecv2-qe-section ecv2-qe-section-edit">
				<label class="ecv2-modal-label" for="ecv2-dup-note"><?php esc_html_e( 'Internal note', 'wp-easycart' ); ?> <span class="ecv2-qe-muted"><?php esc_html_e( '(saved on the new order)', 'wp-easycart' ); ?></span></label>
				<textarea id="ecv2-dup-note" class="ecv2-input ecv2-qe-notes" rows="2" placeholder="<?php esc_attr_e( 'e.g. Replacing damaged skirt, customer emailed photos 8/27', 'wp-easycart' ); ?>"></textarea>
			</section>

			<!-- After create -->
			<section class="ecv2-qe-section ecv2-qe-section-edit ecv2-qe-notify">
				<label class="ecv2-qe-check ecv2-qe-check-strong"><input type="checkbox" id="ecv2-dup-send-receipt" /> <?php esc_html_e( 'Email the customer a receipt for the new order', 'wp-easycart' ); ?></label>
				<label class="ecv2-qe-check"><input type="checkbox" id="ecv2-dup-mark-viewed" checked /> <?php esc_html_e( 'Mark the new order as viewed', 'wp-easycart' ); ?></label>
				<label class="ecv2-qe-check"><input type="checkbox" id="ecv2-dup-open-after" checked /> <?php esc_html_e( 'Open the new order after creating', 'wp-easycart' ); ?></label>
			</section>

		</div>
	</div>

	<div class="ecv2-drawer-footer ecv2-qe-footer">
		<span class="ecv2-qe-muted" id="ecv2-dup-footer-summary"></span>
		<div class="ecv2-qe-footer-actions">
			<button type="button" class="ecv2-btn ecv2-btn-ghost" onclick="ecv2_order_dup_close();"><?php esc_html_e( 'Cancel', 'wp-easycart' ); ?></button>
			<button type="button" class="ecv2-btn ecv2-btn-primary" id="ecv2-dup-create" onclick="ecv2_order_dup_create();"><?php esc_html_e( 'Create duplicate', 'wp-easycart' ); ?></button>
		</div>
	</div>
</div>
<script>jQuery( function( $ ) { $( '#ecv2-order-dup-backdrop, #ecv2-order-dup' ).appendTo( document.body ); } );</script>