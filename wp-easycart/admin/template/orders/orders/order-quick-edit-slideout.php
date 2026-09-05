<?php
/**
 * Order Quick Edit — V2 drawer (FREE)
 *
 * Replaces the legacy slideout. Markup is a shell; orders-v2.js populates it
 * via 'ecv2_order_quick_edit_get' and saves via 'ecv2_order_quick_edit_save'
 * (both in wp_easycart_admin_order_table.php).
 *
 * PRO extension points:
 *  - action 'wp_easycart_ecv2_order_quick_edit_fields'  (extra fields, rendered after fulfillment)
 *  - filter 'wp_easycart_ecv2_order_quick_edit_payload' (add data to the load response)
 *  - action 'wp_easycart_ecv2_order_quick_edit_saved'   (persist extra fields on save)
 *
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$ecv2_qe_statuses = $wpdb->get_results( 'SELECT status_id, order_status, is_approved, color_code FROM ec_orderstatus ORDER BY status_id ASC' );
$ecv2_qe_carriers = function_exists( 'ecv2_order_carrier_suggestions' ) ? ecv2_order_carrier_suggestions() : array( 'USPS', 'UPS', 'FedEx', 'DHL' );
$ecv2_qe_shipped  = class_exists( 'wp_easycart_admin_order_table' ) ? wp_easycart_admin_order_table::STATUS_SHIPPED : 2;
?>
<div class="ecv2-drawer-backdrop ecv2-qe-backdrop" id="ecv2-order-qe-backdrop" onclick="ecv2_order_qe_close();"></div>
<div class="ecv2-qe-drawer" id="ecv2-order-qe" role="dialog" aria-modal="true" aria-labelledby="ecv2-qe-title"
	data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp-easycart-ecv2-order-quick-edit' ) ); ?>"
	data-shipped-id="<?php echo esc_attr( $ecv2_qe_shipped ); ?>"
	data-lang-saved="<?php esc_attr_e( 'Order updated.', 'wp-easycart' ); ?>"
	data-lang-saved-email="<?php esc_attr_e( 'Order updated and shipping email sent.', 'wp-easycart' ); ?>"
	data-lang-nochange="<?php esc_attr_e( 'No changes to save.', 'wp-easycart' ); ?>"
	data-lang-loading="<?php esc_attr_e( 'Loading…', 'wp-easycart' ); ?>"
	data-lang-more="<?php esc_attr_e( '+ %d more', 'wp-easycart' ); ?>"
	data-lang-refunded="<?php esc_attr_e( '%d refunded', 'wp-easycart' ); ?>"
	data-lang-email-hint-on="<?php esc_attr_e( 'The customer will receive the shipped email with this tracking number.', 'wp-easycart' ); ?>"
	data-lang-email-hint-off="<?php esc_attr_e( 'Turned on automatically when you add tracking or mark the order shipped.', 'wp-easycart' ); ?>">

	<div class="ecv2-drawer-header ecv2-qe-header">
		<div class="ecv2-drawer-header-left">
			<span class="dashicons dashicons-welcome-write-blog ecv2-drawer-header-icon"></span>
			<h3 class="ecv2-drawer-title" id="ecv2-qe-title"><?php esc_html_e( 'Quick Edit', 'wp-easycart' ); ?> <span class="ecv2-qe-order-id" id="ecv2-qe-order-id"></span></h3>
			<span class="ecv2-qe-status-pill" id="ecv2-qe-status-pill"><span class="ecv2-order-status-swatch"></span><span class="ecv2-qe-status-pill-label"></span></span>
		</div>
		<button type="button" class="ecv2-drawer-close" onclick="ecv2_order_qe_close();" aria-label="<?php esc_attr_e( 'Close', 'wp-easycart' ); ?>"><span class="dashicons dashicons-no-alt"></span></button>
	</div>

	<div class="ecv2-drawer-body ecv2-qe-body" id="ecv2-qe-body">
		<div class="ecv2-qe-loading" id="ecv2-qe-loading"><span class="dashicons dashicons-update ecv2-spin"></span> <?php esc_html_e( 'Loading…', 'wp-easycart' ); ?></div>

		<div class="ecv2-qe-content" id="ecv2-qe-content" style="display:none;">

			<!-- Summary: who / when / how much -->
			<section class="ecv2-qe-summary">
				<div class="ecv2-qe-summary-row">
					<div class="ecv2-qe-customer">
						<strong id="ecv2-qe-customer-name"></strong>
						<span class="ecv2-qe-muted" id="ecv2-qe-customer-company"></span>
						<span class="ecv2-qe-copy-line" id="ecv2-qe-email-line"><span id="ecv2-qe-email"></span><button type="button" class="ecv2-order-copy-btn" onclick="ecv2_order_copy( this );" title="<?php esc_attr_e( 'Copy email', 'wp-easycart' ); ?>"><span class="dashicons dashicons-admin-page"></span></button></span>
						<span class="ecv2-qe-muted" id="ecv2-qe-phone"></span>
					</div>
					<div class="ecv2-qe-money">
						<strong id="ecv2-qe-total"></strong>
						<span class="ecv2-qe-muted" id="ecv2-qe-refund"></span>
						<span class="ecv2-qe-muted" id="ecv2-qe-payment"></span>
						<span class="ecv2-qe-muted" id="ecv2-qe-date"></span>
					</div>
				</div>
				<div class="ecv2-qe-flags" id="ecv2-qe-flags"></div>
				<div class="ecv2-qe-customer-notes" id="ecv2-qe-customer-notes" style="display:none;"><span class="dashicons dashicons-format-quote"></span><span></span></div>
			</section>

			<!-- Items -->
			<section class="ecv2-qe-section">
				<div class="ecv2-qe-section-label"><span><?php esc_html_e( 'Items', 'wp-easycart' ); ?></span><span class="ecv2-qe-count" id="ecv2-qe-item-count"></span></div>
				<ul class="ecv2-qe-items" id="ecv2-qe-items"></ul>
				<button type="button" class="ecv2-qe-items-more" id="ecv2-qe-items-more" style="display:none;" onclick="ecv2_order_qe_show_all_items();"></button>
			</section>

			<!-- Ship to -->
			<section class="ecv2-qe-section">
				<div class="ecv2-qe-section-label"><span><?php esc_html_e( 'Ship to', 'wp-easycart' ); ?></span><button type="button" class="ecv2-order-copy-btn ecv2-qe-copy-address" id="ecv2-qe-copy-address" onclick="ecv2_order_copy( this );" title="<?php esc_attr_e( 'Copy address', 'wp-easycart' ); ?>"><span class="dashicons dashicons-admin-page"></span></button></div>
				<address class="ecv2-qe-address" id="ecv2-qe-address"></address>
			</section>

			<!-- Status -->
			<section class="ecv2-qe-section ecv2-qe-section-edit">
				<label class="ecv2-modal-label" for="ecv2-qe-status"><?php esc_html_e( 'Order status', 'wp-easycart' ); ?></label>
				<div class="ecv2-qe-status-field">
					<span class="ecv2-order-status-swatch" id="ecv2-qe-status-swatch"></span>
					<select id="ecv2-qe-status" class="ecv2-select ecv2-qe-select" onchange="ecv2_order_qe_status_changed();">
						<?php foreach ( $ecv2_qe_statuses as $ecv2_qe_status ) : ?>
							<option value="<?php echo esc_attr( $ecv2_qe_status->status_id ); ?>" data-color="<?php echo esc_attr( '' !== (string) $ecv2_qe_status->color_code ? $ecv2_qe_status->color_code : '#e5e7eb' ); ?>" data-approved="<?php echo esc_attr( (int) $ecv2_qe_status->is_approved ); ?>"><?php echo esc_html( $ecv2_qe_status->order_status ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</section>

			<!-- Fulfillment -->
			<section class="ecv2-qe-section ecv2-qe-section-edit">
				<div class="ecv2-qe-section-label"><span><?php esc_html_e( 'Fulfillment', 'wp-easycart' ); ?></span></div>
				<div class="ecv2-qe-grid">
					<div class="ecv2-qe-field">
						<label class="ecv2-modal-label" for="ecv2-qe-carrier"><?php esc_html_e( 'Carrier', 'wp-easycart' ); ?></label>
						<input type="text" id="ecv2-qe-carrier" class="ecv2-input" list="ecv2-qe-carrier-list" autocomplete="off" placeholder="<?php esc_attr_e( 'USPS, UPS…', 'wp-easycart' ); ?>" />
						<datalist id="ecv2-qe-carrier-list">
							<?php foreach ( $ecv2_qe_carriers as $ecv2_qe_carrier ) : ?>
								<option value="<?php echo esc_attr( $ecv2_qe_carrier ); ?>"></option>
							<?php endforeach; ?>
						</datalist>
					</div>
					<div class="ecv2-qe-field">
						<label class="ecv2-modal-label" for="ecv2-qe-tracking"><?php esc_html_e( 'Tracking number', 'wp-easycart' ); ?></label>
						<input type="text" id="ecv2-qe-tracking" class="ecv2-input" autocomplete="off" placeholder="<?php esc_attr_e( 'Optional', 'wp-easycart' ); ?>" oninput="ecv2_order_qe_tracking_changed();" />
					</div>
					<div class="ecv2-qe-field ecv2-qe-field-wide">
						<label class="ecv2-modal-label" for="ecv2-qe-method"><?php esc_html_e( 'Shipping method', 'wp-easycart' ); ?></label>
						<input type="text" id="ecv2-qe-method" class="ecv2-input" placeholder="<?php esc_attr_e( 'e.g. Priority 3 Day Shipping', 'wp-easycart' ); ?>" />
					</div>
				</div>
				<label class="ecv2-qe-check"><input type="checkbox" id="ecv2-qe-expedited" /> <?php esc_html_e( 'Expedited shipping', 'wp-easycart' ); ?></label>
			</section>

			<?php do_action( 'wp_easycart_ecv2_order_quick_edit_fields' ); ?>

			<!-- Notify -->
			<section class="ecv2-qe-section ecv2-qe-section-edit ecv2-qe-notify" id="ecv2-qe-notify">
				<label class="ecv2-qe-check ecv2-qe-check-strong"><input type="checkbox" id="ecv2-qe-send-email" onchange="ecv2_order_qe_email_touched();" /> <?php esc_html_e( 'Email the customer a shipping confirmation on save', 'wp-easycart' ); ?></label>
				<p class="ecv2-qe-hint" id="ecv2-qe-email-hint"></p>
			</section>

		</div>
	</div>

	<div class="ecv2-drawer-footer ecv2-qe-footer">
		<a href="#" class="ecv2-qe-full-link" id="ecv2-qe-full-link"><?php esc_html_e( 'Open full order', 'wp-easycart' ); ?> <span class="dashicons dashicons-arrow-right-alt"></span></a>
		<div class="ecv2-qe-footer-actions">
			<button type="button" class="ecv2-btn ecv2-btn-ghost" onclick="ecv2_order_qe_close();"><?php esc_html_e( 'Cancel', 'wp-easycart' ); ?></button>
			<button type="button" class="ecv2-btn ecv2-btn-primary" id="ecv2-qe-save" onclick="ecv2_order_qe_save();"><?php esc_html_e( 'Save changes', 'wp-easycart' ); ?></button>
		</div>
	</div>
</div>
<script>jQuery( function( $ ) { $( '#ecv2-order-qe-backdrop, #ecv2-order-qe' ).appendTo( document.body ); } );</script>