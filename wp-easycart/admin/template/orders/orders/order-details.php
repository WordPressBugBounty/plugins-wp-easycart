<?php
/**
 * Order Details Template (V2.1 — approved redesign)
 *
 * Rendered by wp_easycart_admin_details_orders::output(). Implements the
 * approved order-details redesign on the ecdv2 design language:
 *  - Header meta line (customer / items / total) under the title
 *  - Print + shipped-email actions consolidated into the header menu
 *  - Slim items toolbar ( Add Line / Refund + PRO hook buttons )
 *  - Offers panel collapsed to a one-line summary with expandable detail
 *  - Totals restyled; zero rows hidden; Edit moved to the card header
 *  - Private Notes card slimmed; weight/giftcard/coupon hook relocated to
 *    the Shipping card ( wp_easycart_admin_orders_details_shipment )
 *  - Order history + staff comments moved into the sidebar as"Activity"
 *
 * CRITICAL COMPAT: every element ID and every do_action / apply_filters
 * from the previous template is preserved so the existing free AJAX
 * handlers (orders.js) and all PRO forms/hooks keep working unchanged.
 * The shipped-email submit input is retained in the DOM (visually hidden)
 * and triggered from the header menu, preserving its form-submit flow.
 *
 * V2 extension points (unchanged):
 *  - wp_easycart_ecv2_order_details_header_chips        ( $order )
 *  - wp_easycart_ecv2_order_details_header_menu         ( $order )
 *  - wp_easycart_ecv2_order_details_fulfillment_banner  ( $order, $state )
 *  - wp_easycart_ecv2_order_details_payment_panel       ( $order )
 *  - wp_easycart_ecv2_order_details_timeline_pre        ( $order )
 *  - wp_easycart_ecv2_order_details_customer_card       ( $order )
 *  - wp_easycart_ecv2_order_details_modals              ( $order )
 *
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$prev_order_id = $wpdb->get_var( $wpdb->prepare( 'SELECT order_id FROM ec_order WHERE order_id = (SELECT MIN(order_id) FROM ec_order WHERE order_id > %d)', $this->order->order_id ) );
$next_order_id = $wpdb->get_var( $wpdb->prepare( 'SELECT order_id FROM ec_order WHERE order_id = (SELECT MAX(order_id) FROM ec_order WHERE order_id < %d)', $this->order->order_id ) );

$order_status_list = $wpdb->get_results( 'SELECT ec_orderstatus.* FROM ec_orderstatus ORDER BY status_id' ); /* includes color_code + is_archieved [sic] */
$order_viewed = $wpdb->query( $wpdb->prepare( 'UPDATE ec_order SET ec_order.order_viewed = 1 WHERE ec_order.order_id = %s', $this->order->order_id ) );

/* Payment badge state (same mapping the V1 totals box used and orders.js updates by status id). */
$wpec_status_id = (int) $this->order->orderstatus_id;
if ( 17 === $wpec_status_id ) {
	$payment_badge = array( 'class' => 'payment-neutral', 'label' => __( 'Partial Refund', 'wp-easycart' ) );
} else if ( 16 === $wpec_status_id ) {
	$payment_badge = array( 'class' => 'payment-bad', 'label' => __( 'Refunded', 'wp-easycart' ) );
} else if ( ! empty( $this->order->is_approved ) ) {
	$payment_badge = array( 'class' => 'payment-paid', 'label' => __( 'Paid', 'wp-easycart' ) );
} else if ( 19 === $wpec_status_id ) {
	$payment_badge = array( 'class' => 'payment-bad', 'label' => __( 'Canceled', 'wp-easycart' ) );
} else if ( 7 === $wpec_status_id || 9 === $wpec_status_id ) {
	$payment_badge = array( 'class' => 'payment-bad', 'label' => __( 'Failed', 'wp-easycart' ) );
} else {
	$payment_badge = array( 'class' => 'payment-processing', 'label' => __( 'Processing', 'wp-easycart' ) );
}

/* Fulfillment state: fulfilled / pickup / digital ( nothing to ship, 6.0.0 ) / unfulfilled / none. One source of truth: the controller. */
$wpec_tracking = trim( (string) $this->order->tracking_number );
if ( method_exists( $this, 'get_fulfillment_state' ) ) {
	$fulfillment_state = $this->get_fulfillment_state();
} else if ( 18 === $wpec_status_id || 2 === $wpec_status_id || '' !== $wpec_tracking ) {
	$fulfillment_state = 'fulfilled';
} else if ( 11 === $wpec_status_id || ! empty( $this->order->includes_restaurant_type ) ) {
	$fulfillment_state = 'pickup';
} else if ( ! empty( $this->order->is_approved ) && ! in_array( $wpec_status_id, array( 16, 19 ), true ) ) {
	/* 6.0.0: nothing to ship ( downloads, gift cards, subscriptions / services, shipping disabled ) is fulfilled on payment. The page is
	   rendered by the legacy controller ( wp_easycart_admin_orders::load_orders_list ), which has no get_fulfillment_state(), so the rule lives here too. */
	if ( class_exists( 'wp_easycart_admin_order_table' ) && method_exists( 'wp_easycart_admin_order_table', 'requires_shipping' ) && ! wp_easycart_admin_order_table::requires_shipping( $this->order->order_id ) ) {
		$fulfillment_state = 'digital';
	} else {
		$fulfillment_state = 'unfulfilled';
	}
} else {
	$fulfillment_state = 'none';
}
$fulfillment_state = apply_filters( 'wp_easycart_ecv2_order_fulfillment_state', $fulfillment_state, $this->order );
$item_count        = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE( SUM( quantity ), 0 ) FROM ec_orderdetail WHERE order_id = %d', $this->order->order_id ) );
$selected_order_status = false;
foreach ( $order_status_list as $wpec_status_check ) {
	if ( $this->order->orderstatus_id == $wpec_status_check->status_id ) {
		$selected_order_status = $wpec_status_check;
	}
}
$wpec_customer_name = trim( (string) $this->order->billing_first_name . ' ' . (string) $this->order->billing_last_name );
if ( '' === $wpec_customer_name ) {
	$wpec_customer_name = trim( (string) $this->order->shipping_first_name . ' ' . (string) $this->order->shipping_last_name );
}
?>

<?php /* ------- Hidden fields: verbatim from V1 (PRO forms read these). ------- */ ?>
<input type="hidden" name="ec_admin_form_action" value="<?php echo esc_attr( $this->form_action ); ?>" />
<input type="hidden" name="order_id" id="order_id" value="<?php echo esc_attr( $this->order->order_id ); ?>" />
<input type="hidden" name="payment_method" value="<?php echo esc_attr( $this->order->payment_method ); ?>" />
<input type="hidden" name="user_id" value="<?php echo esc_attr( $this->order->user_id ); ?>" />
<input type="hidden" name="user_level" value="<?php echo esc_attr( $this->order->user_level ); ?>" />
<input type="hidden" name="last_updated" value="<?php echo esc_attr( $this->order->last_updated ); ?>" />
<input type="hidden" name="paypal_email_id" value="<?php echo esc_attr( $this->order->paypal_email_id ); ?>" />
<input type="hidden" name="paypal_transaction_id" value="<?php echo esc_attr( $this->order->paypal_transaction_id ); ?>" />
<input type="hidden" name="paypal_payer_id" value="<?php echo esc_attr( $this->order->paypal_payer_id ); ?>" />
<input type="hidden" name="order_viewed" value="<?php echo esc_attr( $this->order->order_viewed ); ?>" />
<input type="hidden" name="txn_id" value="<?php echo esc_attr( $this->order->txn_id ); ?>" />
<input type="hidden" name="edit_sequence" value="<?php echo esc_attr( $this->order->edit_sequence ); ?>" />
<input type="hidden" name="fraktjakt_order_id" value="<?php echo esc_attr( $this->order->fraktjakt_order_id ); ?>" />
<input type="hidden" name="fraktjakt_shipment_id" value="<?php echo esc_attr( $this->order->fraktjakt_shipment_id ); ?>" />
<input type="hidden" name="stripe_charge_id" value="<?php echo esc_attr( $this->order->stripe_charge_id ); ?>" />
<input type="hidden" name="subscription_id" value="<?php echo esc_attr( $this->order->subscription_id ); ?>" />
<input type="hidden" name="order_gateway" id="order_gateway" value="<?php echo esc_attr( $this->order->order_gateway ); ?>" />
<input type="hidden" name="affirm_charge_id" value="<?php echo esc_attr( $this->order->affirm_charge_id ); ?>" />
<input type="hidden" name="guest_key" value="<?php echo esc_attr( $this->order->guest_key ); ?>" />
<input type="hidden" name="gateway_transaction_id" value="<?php echo esc_attr( $this->order->gateway_transaction_id ); ?>" />
<input type="hidden" name="credit_memo_txn_id" value="<?php echo esc_attr( $this->order->credit_memo_txn_id ); ?>" />
<input type="hidden" name="shipping_service_code" value="<?php echo esc_attr( $this->order->shipping_service_code ); ?>" />
<input type="hidden" name="quickbooks_status" value="<?php echo esc_attr( $this->order->quickbooks_status ); ?>" />
<?php wp_easycart_admin_verification()->print_nonce_field( 'wp_easycart_order_details_nonce', 'wp-easycart-order-details' ); ?>

<div class="ecdv2-wrap ecodv2-wrap" id="ecodv2_wrap">

	<?php /* =============================== HEADER =============================== */ ?>
	<div class="ecdv2-header ecodv2-header">
		<a href="<?php echo esc_attr( $this->action ); ?>" class="ecdv2-header-back" title="<?php esc_attr_e( 'Back to Orders', 'wp-easycart' ); ?>"><span class="dashicons dashicons-arrow-left-alt2"></span></a>

		<div class="ecdv2-header-meta">
			<p class="ecdv2-header-title ecodv2-title-row">
				<span class="ec_admin_order_details_order_id"><?php esc_attr_e( 'Order', 'wp-easycart' ); ?> #<?php echo esc_attr( $this->order->order_id ); ?></span>
				<?php /* Payment badge — same element the V1 JS updates on status change. */ ?>
				<span id="wpeasycart-payment-status" class="<?php echo esc_attr( $payment_badge['class'] ); ?> ecodv2-payment-badge"><?php echo esc_html( $payment_badge['label'] ); ?></span>
				<?php /* Fulfillment badge — one element; JS retargets class + text on status change. */
				$wpec_fb_map = array(
					'fulfilled'   => array( 'class' => 'ecodv2-fulfill-badge-ok', 'icon' => 'dashicons-yes-alt', 'label' => __( 'Fulfilled', 'wp-easycart' ) ),
					'pickup'      => array( 'class' => 'ecodv2-fulfill-badge-pickup', 'icon' => 'dashicons-store', 'label' => __( 'Pickup', 'wp-easycart' ) ),
					'unfulfilled' => array( 'class' => 'ecodv2-fulfill-badge-warn', 'icon' => 'dashicons-warning', 'label' => __( 'Unfulfilled', 'wp-easycart' ) ),
					'digital'     => array( 'class' => 'ecodv2-fulfill-badge-ok', 'icon' => 'dashicons-download', 'label' => __( 'No shipping', 'wp-easycart' ) ),
				);
				if ( isset( $wpec_fb_map[ $fulfillment_state ] ) ) { $wpec_fb = $wpec_fb_map[ $fulfillment_state ]; ?>
				<span class="ecodv2-fulfill-badge <?php echo esc_attr( $wpec_fb['class'] ); ?>" id="ecodv2_fulfill_badge" data-state="<?php echo esc_attr( $fulfillment_state ); ?>"><span class="dashicons <?php echo esc_attr( $wpec_fb['icon'] ); ?>"></span> <span id="ecodv2_fulfill_badge_label"><?php echo esc_html( $wpec_fb['label'] ); ?></span></span>
				<?php } else { ?>
				<span class="ecodv2-fulfill-badge" id="ecodv2_fulfill_badge" data-state="none" style="display:none;"><span class="dashicons dashicons-yes-alt"></span> <span id="ecodv2_fulfill_badge_label"></span></span>
				<?php } ?>
				<?php /* PRO v2: tags + insight chips. Wrapper is display:contents; ec_admin_ajax_update_order_user re-fills it when the account changes. */ ?>
				<span class="ecodv2-header-chips" id="ecodv2_header_chips"><?php do_action( 'wp_easycart_ecv2_order_details_header_chips', $this->order ); ?></span>
			</p>
			<div class="ecdv2-header-sub">
				<?php $edit_order_date_action = apply_filters( 'wp_easycart_admin_order_details_order_date_edit_action', 'show_pro_required' ); ?>
				<span class="ecodv2-date-view" id="ec_admin_order_details_order_date_row" onclick="ecodv2_open_date_edit( '<?php echo esc_attr( $edit_order_date_action ); ?>' ); return false;" title="<?php esc_attr_e( 'Edit order date', 'wp-easycart' ); ?>">
					<span id="ec_admin_order_details_order_date"><?php echo esc_attr( date( 'M j Y ' . get_option( 'time_format' ), $this->order_timestamp ) ); ?></span>
					<span class="ecodv2-inline-edit ecodv2-date-pencil" id="ec_admin_order_date_edit"><span class="dashicons dashicons-edit"></span></span>
				</span>
				<?php do_action( 'wp_easycart_order_details_order_date' ); ?>
				<?php if ( '' !== $wpec_customer_name ) { ?>
				&middot; <span class="ecodv2-meta-customer"><?php echo esc_html( $wpec_customer_name ); ?></span>
				<?php } ?>
				<?php if ( '' !== (string) $this->order->user_email ) { ?>
				&middot; <a class="ecodv2-meta-email" href="mailto:<?php echo esc_attr( $this->order->user_email ); ?>"><?php echo esc_html( $this->order->user_email ); ?></a>
				<?php } ?>
				&middot; <?php echo esc_html( sprintf( _n( '%d item', '%d items', $item_count, 'wp-easycart' ), $item_count ) ); ?> &mdash; <?php echo esc_html( $GLOBALS['currency']->get_currency_display( $this->order->grand_total ) ); ?>
			</div>
		</div>

		<div class="ecdv2-header-spacer"></div>

		<?php /* Status, prev/next and the actions menu travel as one right-aligned group so a narrow header wraps them together rather than one at a time. */ ?>
		<div class="ecdv2-header-actions ecodv2-header-actions">

		<?php /* Status control — the V1 select stays ( hidden ) and keeps its handler;
		        the pill + swatch menu below is presentation that drives it. */ ?>
		<div class="ecodv2-status-wrap">
			<?php do_action( 'wp_easycart_admin_order_status_box_right' ); ?>
			<?php
			$wpec_current_status_color = ( $selected_order_status && '' !== (string) $selected_order_status->color_code ) ? $selected_order_status->color_code : '#e5e7eb';
			$wpec_current_status_label = ( $selected_order_status ) ? $selected_order_status->order_status : __( 'Processing', 'wp-easycart' );
			$wpec_current_is_archived  = ( $selected_order_status && ! empty( $selected_order_status->is_archieved ) );
			?>
			<button type="button" class="ecodv2-status-pill" id="ecodv2_status_pill" onclick="ecodv2_status_toggle(); return false;" aria-haspopup="listbox">
				<span class="ecodv2-status-dot" id="ecodv2_status_pill_dot" style="background:<?php echo esc_attr( $wpec_current_status_color ); ?>;"></span>
				<span id="ecodv2_status_pill_label"><?php echo esc_html( $wpec_current_status_label ); ?><?php if ( $wpec_current_is_archived ) { ?> <em class="ecodv2-status-archived-note">(<?php esc_attr_e( 'archived', 'wp-easycart' ); ?>)</em><?php } ?></span>
				<span class="dashicons dashicons-arrow-down-alt2 ecodv2-status-caret"></span>
			</button>
			<div class="ecdv2-menu ecodv2-status-menu" id="ecodv2_status_menu" role="listbox">
				<?php foreach ( $order_status_list as $order_status ) { ?>
				<?php
				$wpec_is_current = ( $this->order->orderstatus_id == $order_status->status_id );
				if ( ! empty( $order_status->is_archieved ) && ! $wpec_is_current ) {
					continue; /* archived statuses hide from the picker unless currently applied */
				}
				$wpec_dot = ( '' !== (string) $order_status->color_code ) ? $order_status->color_code : '#e5e7eb';
				?>
				<button type="button" class="ecodv2-status-item<?php if ( $wpec_is_current ) { ?> is-current<?php } ?><?php if ( empty( $order_status->is_approved ) ) { ?> is-unapproved<?php } ?>" data-status-id="<?php echo esc_attr( $order_status->status_id ); ?>" data-color="<?php echo esc_attr( $wpec_dot ); ?>" onclick="ecodv2_set_status( '<?php echo esc_attr( $order_status->status_id ); ?>', this ); return false;" role="option">
					<span class="ecodv2-status-dot" style="background:<?php echo esc_attr( $wpec_dot ); ?>;"></span>
					<span class="ecodv2-status-item-label"><?php echo esc_html( $order_status->order_status ); ?></span>
					<span class="ecodv2-status-check dashicons dashicons-yes"></span>
				</button>
				<?php } ?>
				<div class="ecdv2-menu-sep"></div>
				<button type="button" class="ecodv2-status-add" onclick="ecodv2_set_status( 'add-new', null ); return false;">&#65291; <?php esc_attr_e( 'Add new status', 'wp-easycart' ); ?></button>
			</div>
			<select id="orderstatus_id" class="ecodv2-visually-hidden" name="orderstatus_id" onchange="return ec_admin_edit_order_status(this);" data-partial-refund="<?php echo esc_attr__( 'Partial Refund', 'wp-easycart' ); ?>" data-refunded="<?php echo esc_attr__( 'Refunded', 'wp-easycart' ); ?>" data-paid="<?php echo esc_attr__( 'Paid', 'wp-easycart' ); ?>" data-cancelled="<?php echo esc_attr__( 'Canceled', 'wp-easycart' ); ?>" data-failed="<?php echo esc_attr__( 'Failed', 'wp-easycart' ); ?>" data-pending="<?php echo esc_attr__( 'Processing', 'wp-easycart' ); ?>">
				<?php foreach ( $order_status_list as $order_status ) { ?>
				<option value="<?php echo esc_attr( $order_status->status_id ); ?>" isapproved="<?php echo esc_attr( $order_status->is_approved ); ?>"<?php if ( $this->order->orderstatus_id == $order_status->status_id ) { ?> selected<?php } ?>><?php echo esc_attr( $order_status->order_status ); ?></option>
				<?php } ?>
				<option value="add-new"><?php esc_attr_e( '+ Add New Status', 'wp-easycart' ); ?></option>
			</select>
		</div>

		<?php /* Prev / next navigation. */ ?>
		<div class="ecodv2-nav">
			<?php if ( $prev_order_id ) { ?>
			<a class="ecv2-btn ecv2-btn-sm" href="admin.php?page=wp-easycart-orders&subpage=orders&order_id=<?php echo esc_attr( $prev_order_id ); ?>&ec_admin_form_action=edit" title="<?php esc_attr_e( 'Newer order', 'wp-easycart' ); ?>" id="ecodv2_nav_prev"><span class="dashicons dashicons-arrow-up-alt2"></span></a>
			<?php } else { ?>
			<span class="ecv2-btn ecv2-btn-sm ecodv2-nav-disabled"><span class="dashicons dashicons-arrow-up-alt2"></span></span>
			<?php } ?>
			<?php if ( $next_order_id ) { ?>
			<a class="ecv2-btn ecv2-btn-sm" href="admin.php?page=wp-easycart-orders&subpage=orders&order_id=<?php echo esc_attr( $next_order_id ); ?>&ec_admin_form_action=edit" title="<?php esc_attr_e( 'Older order', 'wp-easycart' ); ?>" id="ecodv2_nav_next"><span class="dashicons dashicons-arrow-down-alt2"></span></a>
			<?php } else { ?>
			<span class="ecv2-btn ecv2-btn-sm ecodv2-nav-disabled"><span class="dashicons dashicons-arrow-down-alt2"></span></span>
			<?php } ?>
		</div>

		<?php /* Actions menu — print + email actions consolidated here. */ ?>
		<div class="ecdv2-menu-wrap ecodv2-menu-wrap">
			<button type="button" class="ecv2-btn" onclick="ecodv2_menu_toggle( this ); return false;" aria-label="<?php esc_attr_e( 'More actions', 'wp-easycart' ); ?>"><span class="dashicons dashicons-ellipsis" style="font-size:14px;width:14px;height:14px;margin-top:3px;"></span></button>
			<div class="ecdv2-menu" id="ecodv2_header_menu">
				<a href="admin.php?page=wp-easycart-orders&subpage=orders&bulk=<?php echo esc_attr( $this->order->order_id ); ?>&ec_admin_form_action=print-packing-slip&wp_easycart_nonce=<?php echo esc_attr( wp_create_nonce( 'wp-easycart-bulk-orders' ) ); ?>" target="_blank" onclick="ecodv2_menu_close();"><span class="dashicons dashicons-clipboard"></span><?php esc_attr_e( 'Print Packing Slip', 'wp-easycart' ); ?></a>
				<a href="admin.php?page=wp-easycart-orders&subpage=orders&bulk=<?php echo esc_attr( $this->order->order_id ); ?>&ec_admin_form_action=print-receipt&wp_easycart_nonce=<?php echo esc_attr( wp_create_nonce( 'wp-easycart-bulk-orders' ) ); ?>" target="_blank" onclick="ecodv2_menu_close();"><span class="dashicons dashicons-media-text"></span><?php esc_attr_e( 'Print Receipt', 'wp-easycart' ); ?></a>
				<?php /* 6.0.0: wording for the send dialog ( orders-details-v2.js, ecodv2_email_dialog ). */ ?>
				<script type="application/json" id="ecodv2_email_i18n"><?php echo wp_json_encode( array( 'receipt_title' => __( 'Resend order receipt', 'wp-easycart' ), 'receipt_button' => __( 'Send receipt', 'wp-easycart' ), 'shipped_title' => __( 'Send order shipped email', 'wp-easycart' ), 'shipped_button' => __( 'Send email', 'wp-easycart' ), 'to' => __( 'To', 'wp-easycart' ), 'cc' => __( 'Cc', 'wp-easycart' ), 'bcc' => __( 'Bcc', 'wp-easycart' ), 'optional' => __( 'optional', 'wp-easycart' ), 'hint' => __( 'Separate several addresses with commas.', 'wp-easycart' ), 'cancel' => __( 'Cancel', 'wp-easycart' ), 'sending' => __( 'Sending…', 'wp-easycart' ), 'need_to' => __( 'Enter at least one email address to send to.', 'wp-easycart' ), 'failed' => __( 'The email could not be sent.', 'wp-easycart' ), 'close' => __( 'Close', 'wp-easycart' ) ), JSON_HEX_TAG | JSON_HEX_AMP ); ?></script>
				<?php /* 6.0.0: resend the order receipt ( same sender as the Orders list bulk action; the PRO PDF attaches itself when PDF receipts are on ). */ ?>
				<a href="#" id="ecodv2_resend_receipt_link" data-email="<?php echo esc_attr( $this->order->user_email ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp-easycart-ecv2-order-email-' . (int) $this->order->order_id ) ); ?>" data-email-other="<?php echo esc_attr( isset( $this->order->email_other ) ? $this->order->email_other : '' ); ?>" onclick="ecodv2_resend_receipt( this ); return false;"><span class="dashicons dashicons-email"></span><?php esc_attr_e( 'Resend Order Receipt', 'wp-easycart' ); ?></a>
				<a href="#" id="ecodv2_send_shipped_link" data-email="<?php echo esc_attr( $this->order->user_email ); ?>" data-email-other="<?php echo esc_attr( isset( $this->order->email_other ) ? $this->order->email_other : '' ); ?>" onclick="ecodv2_menu_close(); ecodv2_send_shipped_dialog( this ); return false;"><span class="dashicons dashicons-email-alt"></span><?php esc_attr_e( 'Send Order Shipped Email', 'wp-easycart' ); ?></a>
				<div class="ecdv2-menu-sep"></div>
				<?php /* 6.0.0: the duplicate drawer lives on the Orders list; orders-v2.js opens it from ecv2_duplicate. */ ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-easycart-orders&subpage=orders&ecv2_duplicate=' . (int) $this->order->order_id ) ); ?>" onclick="ecodv2_menu_close();"><span class="dashicons dashicons-admin-page"></span><?php esc_attr_e( 'Duplicate Order', 'wp-easycart' ); ?></a>
				<?php
				/* The help system prints its own markup (icon + hidden label) which
				   renders as a blank row inside the menu. Capture it and re-emit the
				   link as a normal menu item so it matches its neighbours. */
				ob_start();
				wp_easycart_admin()->helpsystem->print_vids_url( 'orders', 'order-management', 'details' );
				$wpec_vids_html = trim( (string) ob_get_clean() );
				$wpec_vids_href = '';
				$wpec_vids_onclick = '';
				if ( '' !== $wpec_vids_html && preg_match( '/<a\s[^>]*>/i', $wpec_vids_html, $wpec_vids_tag ) ) {
					if ( preg_match( '/href=(["\'])(.*?)\1/i', $wpec_vids_tag[0], $wpec_m ) ) {
						$wpec_vids_href = html_entity_decode( $wpec_m[2] );
					}
					if ( preg_match( '/onclick=(["\'])(.*?)\1/i', $wpec_vids_tag[0], $wpec_m ) ) {
						$wpec_vids_onclick = html_entity_decode( $wpec_m[2] );
					}
				}
				if ( '' !== $wpec_vids_href || '' !== $wpec_vids_onclick ) {
					$wpec_vids_target = ( '' !== $wpec_vids_href && '#' !== $wpec_vids_href && false === strpos( $wpec_vids_onclick, 'video_help' ) ) ? ' target="_blank" rel="noopener"' : '';
					echo '<div class="ecdv2-menu-sep"></div>';
					echo '<a href="' . esc_url( '' !== $wpec_vids_href ? $wpec_vids_href : '#' ) . '"' . $wpec_vids_target . ' onclick="ecodv2_menu_close(); ' . esc_attr( rtrim( $wpec_vids_onclick, '; ' ) ) . ( '' !== $wpec_vids_onclick ? ';' : '' ) . ( '' === $wpec_vids_href || '#' === $wpec_vids_href ? ' return false;' : '' ) . '"><span class="dashicons dashicons-video-alt3"></span>' . esc_html__( 'Watch help video', 'wp-easycart' ) . '</a>';
				}
				?>
				<?php do_action( 'wp_easycart_ecv2_order_details_header_menu', $this->order ); ?>
			</div>
		</div>
		</div><?php /* .ecdv2-header-actions */ ?>
	</div>

	<?php /* =============================== BODY =============================== */ ?>
	<div class="ecdv2-body ecodv2-body">

		<?php /* ------------------------------ MAIN COLUMN ------------------------------ */ ?>
		<div class="ecodv2-main">

			<?php /* ---------- Items & Fulfillment card ---------- */ ?>
			<?php /* ---------- Fulfillment card ( V2.9 — its own section above the items ) ---------- */ ?>
			<div class="ecdv2-card ecodv2-card-fulfillment">
				<div class="ecdv2-card-header">
					<h3 class="ecdv2-card-title"><?php esc_attr_e( 'Fulfillment', 'wp-easycart' ); ?></h3>
				</div>
<div class="ecodv2-fulfill-banner ecodv2-fulfill-banner-<?php echo esc_attr( $fulfillment_state ); ?>" id="ecodv2_fulfill_banner"
				data-fulfill-status-ids="18,2"
				data-msg-fulfilled="<?php esc_attr_e( 'This order has been fulfilled.', 'wp-easycart' ); ?>"
				data-msg-unfulfilled="<?php esc_attr_e( 'This order is awaiting fulfillment.', 'wp-easycart' ); ?>">
				<div class="ecodv2-fulfill-row">
					<?php if ( 'fulfilled' === $fulfillment_state ) { ?>
					<span class="dashicons dashicons-yes-alt"></span><span id="ecodv2_fulfill_message"><?php esc_attr_e( 'This order has been fulfilled.', 'wp-easycart' ); ?></span>
					<?php } else if ( 'pickup' === $fulfillment_state ) { ?>
					<span class="dashicons dashicons-store"></span><span id="ecodv2_fulfill_message"><?php esc_attr_e( 'This order is a customer pickup.', 'wp-easycart' ); ?></span>
					<?php } else if ( 'unfulfilled' === $fulfillment_state ) { ?>
					<span class="dashicons dashicons-warning"></span><span id="ecodv2_fulfill_message"><?php esc_attr_e( 'This order is awaiting fulfillment.', 'wp-easycart' ); ?></span>
					<?php } else if ( 'digital' === $fulfillment_state ) { ?>
					<span class="dashicons dashicons-download"></span><span id="ecodv2_fulfill_message"><?php esc_attr_e( 'Nothing to ship. Every item is a download, gift card, subscription or a product with shipping disabled, so this order was fulfilled when the payment was approved.', 'wp-easycart' ); ?></span>
					<?php } else { ?>
					<span class="dashicons dashicons-clock"></span><span id="ecodv2_fulfill_message"><?php esc_attr_e( 'Payment has not been approved yet.', 'wp-easycart' ); ?></span>
					<?php } ?>
					<?php $create_label_action = apply_filters( 'wp_easycart_ecv2_create_label_action', 'show_pro_required' ); ?>
					<button type="button" class="ecv2-btn ecv2-btn-sm" id="ecodv2_create_label_btn"<?php if ( 'fulfilled' === $fulfillment_state || 'digital' === $fulfillment_state ) { ?> style="display:none;"<?php } ?> onclick="ecodv2_open_label_popup( '<?php echo esc_attr( $create_label_action ); ?>' ); return false;"><span class="dashicons dashicons-printer"></span> <?php esc_attr_e( 'Create Label', 'wp-easycart' ); ?></button>
					<?php do_action( 'wp_easycart_ecv2_order_details_fulfillment_banner', $this->order, $fulfillment_state ); ?>
				</div>
			</div>
			<?php
			/* Shipment details, outside the status strip: method / carrier / tracking as one summary with a
			 * single Edit, or an empty state that asks for them. Span ids are the V1 ones orders.js rewrites
			 * after a save ( it appends "<br />", hidden by CSS ); ecodv2_sync_shipment() flips the empty state. */
			$wpec_ship_has_details = '' !== trim( (string) $this->order->shipping_method ) || '' !== trim( (string) $this->order->shipping_carrier ) || '' !== $wpec_tracking || ! empty( $this->order->use_expedited_shipping );
			$wpec_ship_open_drawer = "ecodv2_open_edit_drawer( 'fulfillment', 'ec_admin_process_shipping_method' ); return false;";
			?>
			<div class="ecodv2-shipment<?php echo $wpec_ship_has_details ? '' : ' is-empty'; ?>" id="ec_admin_view_shipping_method">
				<div class="ecodv2-shipment-empty">
					<span class="ecodv2-shipment-empty-icon dashicons dashicons-location"></span>
					<div class="ecodv2-shipment-empty-text">
						<?php if ( 'digital' === $fulfillment_state ) { ?>
						<strong><?php esc_html_e( 'No shipping needed', 'wp-easycart' ); ?></strong>
						<span><?php esc_html_e( 'Nothing in this order ships. You can still record a method or tracking number if you send something separately.', 'wp-easycart' ); ?></span>
						<?php } else { ?>
						<strong><?php esc_html_e( 'No shipping details yet', 'wp-easycart' ); ?></strong>
						<span><?php esc_html_e( 'Add the shipping method, carrier and tracking number when this order ships. Adding a tracking number marks the order fulfilled.', 'wp-easycart' ); ?></span>
						<?php } ?>
					</div>
					<button type="button" class="ecv2-btn ecv2-btn-sm ecodv2-shipment-add" onclick="<?php echo esc_attr( $wpec_ship_open_drawer ); ?>"><span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Add shipping details', 'wp-easycart' ); ?></button>
				</div>
				<div class="ecodv2-shipment-details">
					<div class="ecodv2-shipment-field">
						<span class="ecodv2-shipment-label"><?php esc_html_e( 'Shipping method', 'wp-easycart' ); ?></span>
						<span class="ecodv2-shipment-value"><span id="ec_admin_order_details_shipping_method" data-empty="<?php esc_attr_e( 'Not set', 'wp-easycart' ); ?>"><?php if ( $this->order->shipping_method != '' ) { echo esc_html( $this->order->shipping_method ) . '<br />'; } ?></span><span id="ec_admin_order_details_shipping_type" class="ecodv2-shipment-chip"><?php if ( $this->order->use_expedited_shipping ) { echo esc_html__( 'Expedite Shipping', 'wp-easycart' ) . '<br />'; } ?></span></span>
					</div>
					<div class="ecodv2-shipment-field">
						<span class="ecodv2-shipment-label"><?php esc_html_e( 'Carrier', 'wp-easycart' ); ?></span>
						<span class="ecodv2-shipment-value"><span id="ec_admin_order_details_shipping_carrier" data-empty="<?php esc_attr_e( 'Not set', 'wp-easycart' ); ?>"><?php if ( $this->order->shipping_carrier != '' ) { echo esc_html( $this->order->shipping_carrier ) . '<br />'; } ?></span></span>
					</div>
					<div class="ecodv2-shipment-field">
						<span class="ecodv2-shipment-label"><?php esc_html_e( 'Tracking number', 'wp-easycart' ); ?></span>
						<span class="ecodv2-shipment-value ecodv2-tracking-line"><span id="ec_admin_order_details_tracking_number" data-empty="<?php esc_attr_e( 'Not set', 'wp-easycart' ); ?>"><?php if ( $this->order->tracking_number != '' ) { echo esc_html( $this->order->tracking_number ); } ?></span><button type="button" class="ecodv2-copy-link ecodv2-tracking-copy"<?php if ( '' === $wpec_tracking ) { ?> style="display:none;"<?php } ?> onclick="ecodv2_copy_tracking( this ); return false;" title="<?php esc_attr_e( 'Copy tracking number', 'wp-easycart' ); ?>"><span class="dashicons dashicons-admin-page"></span></button></span>
					</div>
					<button type="button" class="ecodv2-edit-link ecodv2-shipment-edit" id="ec_admin_order_details_shipping_method_edit" onclick="<?php echo esc_attr( $wpec_ship_open_drawer ); ?>"><span class="dashicons dashicons-edit"></span> <?php esc_html_e( 'Edit', 'wp-easycart' ); ?></button>
				</div>
				<?php /* Kept for orders.js, which still shows / hides it by tracking number; the empty state above replaces it. */ ?>
				<div id="ec_admin_order_details_shipping_empty_message" class="ecodv2-legacy-hidden"></div>
			</div>
			</div>

			<div class="ecdv2-card ecodv2-card-items">
				<div class="ecdv2-card-header">
					<h3 class="ecdv2-card-title"><?php esc_attr_e( 'Items', 'wp-easycart' ); ?></h3>
					<span class="ecdv2-card-hint"><?php echo esc_html( sprintf( _n( '%d item', '%d items', $item_count, 'wp-easycart' ), $item_count ) ); ?></span>
				</div>

				<?php /* Fulfillment banner (free = state display; PRO adds actions). */ ?>
				

				<div class="ecdv2-card-body">
					<?php /* Slim toolbar: line-level actions only. Print/email moved to the header menu; PRO hooks preserved. */ ?>
					<div class="ecodv2-item-actions">
						<?php $add_new_line_action = apply_filters( 'wp_easycart_admin_order_details_add_new_line_action', 'show_pro_required' ); ?>
						<?php $refund_action = apply_filters( 'wp_easycart_admin_order_details_refund_action', 'show_pro_required' ); ?>
						<button class="ecv2-btn ecv2-btn-sm" onclick="ecodv2_open_add_line_modal( '<?php echo esc_attr( $add_new_line_action ); ?>' ); return false;"><span class="dashicons dashicons-plus-alt2"></span> <?php esc_attr_e( 'Add Line', 'wp-easycart' ); ?></button>
						<?php if ( $this->order->grand_total > $this->order->refund_total ) { ?>
							<button class="ecv2-btn ecv2-btn-sm" onclick="<?php echo ( 'show_pro_required' === $refund_action ) ? "ecodv2_locked( 'refunds' );" : esc_attr( $refund_action ) . '( );'; ?> return false;" id="ec_admin_refund_button"><span class="dashicons dashicons-undo"></span> <?php esc_attr_e( 'Refund', 'wp-easycart' ); ?><?php if ( 'show_pro_required' === $refund_action ) { ?> <span class="ecodv2-pro-pill"><?php echo esc_html( class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::badge( 'pro' ) : __( 'Pro/Premium', 'wp-easycart' ) ); ?></span><?php } ?></button>
						<?php } ?>
						<?php /* Everything else ( labels / ShipStation / refund calculator — hook output ) lives in More. */ ?>
						<?php
						/* "More" holds hook output ( third-party actions ) or, on free installs, the locked PRO
						 * items. If neither exists the button is not rendered at all — an empty menu is worse
						 * than no menu. */
						ob_start();
						do_action( 'wp_easycart_admin_order_details_button_row_pre', $this->order->order_id );
						do_action( 'wp_easycart_admin_order_details_button_row_post', $this->order->order_id );
						$ecodv2_more_html = trim( ob_get_clean() );
						$ecodv2_more_free = ( '' === $ecodv2_more_html && '' !== apply_filters( 'wp_easycart_admin_lock_icon', 'locked' ) );
						if ( '' !== $ecodv2_more_html || $ecodv2_more_free ) :
						?>
						<div class="ecodv2-toolbar-more">
							<button type="button" class="ecv2-btn ecv2-btn-sm" onclick="ecodv2_toolbar_more_toggle(); return false;" aria-label="<?php esc_attr_e( 'More item actions', 'wp-easycart' ); ?>"><span class="dashicons dashicons-ellipsis"></span> <?php esc_attr_e( 'More', 'wp-easycart' ); ?></button>
							<div class="ecdv2-menu ecodv2-toolbar-menu" id="ecodv2_toolbar_menu">
								<?php
								echo $ecodv2_more_html; // Hook output.
								if ( $ecodv2_more_free ) {
									$ecodv2_more_locked = array(
										array( 'labels',  'dashicons-printer',    __( 'Print shipping label', 'wp-easycart' ) ),
										array( 'refunds', 'dashicons-calculator', __( 'Refund calculator', 'wp-easycart' ) ),
										array( 'emails',  'dashicons-email-alt',  __( 'Email the customer', 'wp-easycart' ) ),
										array( 'emails',  'dashicons-tag',        __( 'Tag this order', 'wp-easycart' ) ),
										array( 'history', 'dashicons-backup',     __( 'Full order log', 'wp-easycart' ) ),
									);
									foreach ( $ecodv2_more_locked as $ecodv2_ml ) {
										echo '<a href="#" class="ecodv2-menu-locked" onclick="ecodv2_toolbar_more_toggle(); ecodv2_locked( \'' . esc_attr( $ecodv2_ml[0] ) . '\' ); return false;"><span class="dashicons ' . esc_attr( $ecodv2_ml[1] ) . '"></span>' . esc_html( $ecodv2_ml[2] ) . '<span class="ecodv2-pro-pill">' . esc_html( class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::badge( 'pro' ) : __( 'Pro/Premium', 'wp-easycart' ) ) . '</span></a>';
									}
								}
								?>
							</div>
						</div>
						<?php endif; ?>
						<?php /* Retained submit input: the header-menu action triggers it so the original form-submit flow is unchanged. */ ?>
						<input type="submit" id="ecodv2_send_shipped_btn" class="ecodv2-visually-hidden" value="<?php esc_attr_e( 'Send Order Shipped Email', 'wp-easycart' ); ?>" onclick="return ec_admin_send_order_shipped_email( )">
					</div>
					<?php do_action( 'wp_easycart_order_details_refund_panel' ); ?>
					<div class="ecodv2-refund-error" id="ec_admin_refund_failed"><div><?php esc_attr_e( 'There was an error completing the refund.', 'wp-easycart' ); ?></div></div>

					<?php /* Line items — reuses the V1 order-item.php template verbatim. */ ?>
					<div class="ecodv2-line-items" id="ec_admin_order_line_items">
					<?php
						$order_details = $wpdb->get_results( $wpdb->prepare( 'SELECT ec_orderdetail.*, ec_order.subscription_id FROM ec_orderdetail LEFT JOIN ec_order ON (ec_order.order_id = ec_orderdetail.order_id) WHERE ec_orderdetail.order_id = %s ORDER BY orderdetail_id', $this->order->order_id ) );
						$ecodv2_line_map = array();
						/* 6.0.0: one query for which ordered products still exist; order-item.php links those titles to the product editor. */
						$ecodv2_live_product_ids = array();
						$ecodv2_line_product_ids = array();
						foreach ( $order_details as $ecodv2_line_row ) {
							if ( isset( $ecodv2_line_row->product_id ) && (int) $ecodv2_line_row->product_id > 0 ) {
								$ecodv2_line_product_ids[ (int) $ecodv2_line_row->product_id ] = (int) $ecodv2_line_row->product_id;
							}
						}
						if ( count( $ecodv2_line_product_ids ) > 0 ) {
							$ecodv2_line_product_ids = array_values( $ecodv2_line_product_ids );
							// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- the IN list is built from one %d placeholder per id and every id is passed to prepare().
							$ecodv2_live_product_ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT product_id FROM ec_product WHERE product_id IN ( ' . implode( ', ', array_fill( 0, count( $ecodv2_line_product_ids ), '%d' ) ) . ' )', $ecodv2_line_product_ids ) ) );
						}
						foreach ( $order_details as $line_item ) {
							$ecodv2_line_map[] = array(
								'id'          => (int) $line_item->orderdetail_id,
								'title'       => strip_tags( wp_unslash( $line_item->title ) ),
								'qty'         => (int) $line_item->quantity,
								'unit'        => (float) $line_item->unit_price,
								'total'       => (float) $line_item->total_price,
								'refunded'    => ( isset( $line_item->refunded_quantity ) ) ? (int) $line_item->refunded_quantity : 0,
								'is_download' => (int) $line_item->is_download,
								'is_giftcard' => (int) $line_item->is_giftcard,
								'is_shippable'=> (int) $line_item->is_shippable,
								'is_free_gift'=> ( isset( $line_item->is_free_gift ) ) ? (int) $line_item->is_free_gift : 0,
								'has_download'=> ( '' != trim( (string) $line_item->download_key ) ) ? 1 : 0,
							);
							include( EC_PLUGIN_DIRECTORY . '/admin/template/orders/orders/order-item.php' );
						}

						// Offers v2: order-level promotions snapshot, collapsed to a summary line.
						if ( function_exists( 'wp_easycart_offers_active' ) && wp_easycart_offers_active() ) {
							$wpec_order_offers = ( isset( $this->order->applied_offers ) && '' != $this->order->applied_offers ) ? json_decode( (string) $this->order->applied_offers, true ) : false;
							if ( is_array( $wpec_order_offers ) && isset( $wpec_order_offers['applied_offers'] ) && count( $wpec_order_offers['applied_offers'] ) > 0 ) {
								$wpec_offer_type_icons = array(
									'item_discount' => 'tag',
									'cart_discount' => 'cart',
									'shipping' => 'car',
									'bxgy' => 'plus-alt',
									'free_gift' => 'awards',
									'tiered' => 'chart-bar',
									'bundle_mix_match' => 'archive',
									'reward' => 'star-filled',
								);
								$wpec_snapshot_ids = array();
								$wpec_offer_count  = 0;
								$wpec_offer_sum    = 0.0;
								foreach ( $wpec_order_offers['applied_offers'] as $wpec_order_offer ) {
									if ( isset( $wpec_order_offer['offer_id'] ) && (int) $wpec_order_offer['offer_id'] > 0 ) {
										$wpec_snapshot_ids[] = (int) $wpec_order_offer['offer_id'];
									}
									$wpec_offer_count++;
									if ( isset( $wpec_order_offer['amount'] ) ) {
										$wpec_offer_sum += (float) $wpec_order_offer['amount'];
									}
								}
								$wpec_live_offer_ids = array();
								if ( count( $wpec_snapshot_ids ) > 0 ) {
									$wpec_live_offer_ids = array_map( 'intval', $wpdb->get_col( 'SELECT offer_id FROM ec_offer WHERE offer_id IN (' . implode( ',', array_map( 'intval', $wpec_snapshot_ids ) ) . ')' ) );
								}
								echo '<div class="ecodv2-offers-panel">';
								echo '<div class="ecodv2-offers-line" onclick="ecodv2_offers_toggle( this ); return false;" role="button" tabindex="0">';
								echo '<span class="dashicons dashicons-tag ecodv2-offers-line-icon"></span>';
								echo '<span class="ecodv2-offers-line-label">' . esc_html( sprintf( _n( '%d offer applied', '%d offers applied', $wpec_offer_count, 'wp-easycart' ), $wpec_offer_count ) ) . '</span>';
								echo '<span class="ecodv2-offers-line-toggle">' . esc_html__( 'details', 'wp-easycart' ) . ' <span class="dashicons dashicons-arrow-down-alt2"></span></span>';
								if ( $wpec_offer_sum > 0 ) {
									echo '<span class="ecodv2-offers-line-amount">&minus;' . esc_html( $GLOBALS['currency']->get_currency_display( $wpec_offer_sum ) ) . '</span>';
								} else {
									echo '<span class="ecodv2-offers-line-amount ecodv2-offers-applied">' . esc_html__( 'applied', 'wp-easycart' ) . '</span>';
								}
								echo '</div>';
								echo '<div class="ecodv2-offers-detail">';
								foreach ( $wpec_order_offers['applied_offers'] as $wpec_order_offer ) {
									$wpec_offer_label = ( isset( $wpec_order_offer['label'] ) && '' != $wpec_order_offer['label'] ) ? $wpec_order_offer['label'] : ( isset( $wpec_order_offer['code'] ) ? $wpec_order_offer['code'] : '' );
									if ( '' == $wpec_offer_label ) {
										continue;
									}
									$wpec_offer_icon = 'tag';
									if ( isset( $wpec_order_offer['trigger_type'] ) && 'code' == $wpec_order_offer['trigger_type'] ) {
										$wpec_offer_icon = 'tickets-alt';
									} else if ( isset( $wpec_order_offer['action_type'] ) && isset( $wpec_offer_type_icons[ $wpec_order_offer['action_type'] ] ) ) {

										$wpec_offer_icon = $wpec_offer_type_icons[ $wpec_order_offer['action_type'] ];
									}
									$wpec_offer_id = ( isset( $wpec_order_offer['offer_id'] ) ) ? (int) $wpec_order_offer['offer_id'] : 0;
									$wpec_offer_link = ( $wpec_offer_id > 0 && in_array( $wpec_offer_id, $wpec_live_offer_ids, true ) ) ? admin_url( 'admin.php?page=wp-easycart-rates&subpage=offers&ec_admin_form_action=edit&offer_id=' . $wpec_offer_id ) : '';
									echo '<div class="ecodv2-offers-row">';
									echo '<span class="ecodv2-offers-left">';
									echo '<span class="dashicons dashicons-' . esc_attr( $wpec_offer_icon ) . '"></span>';
									if ( '' != $wpec_offer_link ) {
										echo '<a href="' . esc_url( $wpec_offer_link ) . '">' . esc_attr( $wpec_offer_label ) . '</a>';
									} else {
										echo '<span>' . esc_attr( $wpec_offer_label ) . '</span>';
										if ( $wpec_offer_id > 0 ) {
											echo ' <span class="ecodv2-offers-deleted">' . esc_attr__( 'offer deleted', 'wp-easycart' ) . '</span>';
										}
									}
									if ( isset( $wpec_order_offer['code'] ) && '' != $wpec_order_offer['code'] ) {
										echo ' <span class="ecodv2-offers-code">(' . esc_attr( $wpec_order_offer['code'] ) . ')</span>';
									}
									echo '</span>';
									if ( isset( $wpec_order_offer['amount'] ) && (float) $wpec_order_offer['amount'] > 0 ) {
										echo '<span class="ecodv2-offers-amount">&minus;' . esc_attr( $GLOBALS['currency']->get_currency_display( (float) $wpec_order_offer['amount'] ) ) . '</span>';
									} else {
										echo '<span class="ecodv2-offers-applied">' . esc_attr__( 'applied', 'wp-easycart' ) . '</span>';
									}
									echo '</div>';
								}
								echo '<div class="ecodv2-offers-note">' . esc_attr__( 'Offers can be edited or deleted over time. The details above are a snapshot of what applied when this order was placed and may no longer match the offer\'s current configuration.', 'wp-easycart' ) . '</div>';
								echo '</div>';
								echo '</div>';
							}
						}
						do_action( 'wp_easycart_admin_order_details_items_end' );
					?>
					</div>
					<script>
					var ecodv2_lines = <?php echo wp_json_encode( $ecodv2_line_map ); ?>;
					var ecodv2_order = {
						shipping: <?php echo (float) $this->order->shipping_total; ?>,
						shipping_refunded: <?php echo ( isset( $this->order->shipping_refund_total ) ) ? (float) $this->order->shipping_refund_total : 0; ?>,
						tax: <?php echo (float) ( $this->order->tax_total + $this->order->vat_total + $this->order->gst_total + $this->order->hst_total + $this->order->pst_total + $this->order->duty_total ); ?>,
						tax_refunded: <?php echo ( isset( $this->order->tax_refund_total ) ) ? (float) $this->order->tax_refund_total : 0; ?>,
						grand: <?php echo (float) $this->order->grand_total; ?>,
						refunded: <?php echo (float) $this->order->refund_total; ?>,
						fulfilled: <?php echo ( '' != trim( (string) $this->order->tracking_number ) ) ? 'true' : 'false'; ?>
					};
					</script>
				</div>
			</div>

			<?php /* ---------- Payment & Totals card ---------- */ ?>
			<div class="ecdv2-card ecodv2-card-totals">
				<div class="ecdv2-card-header">
					<h3 class="ecdv2-card-title"><?php esc_attr_e( 'Payment & Totals', 'wp-easycart' ); ?></h3>
					<?php $edit_totals_action = apply_filters( 'wp_easycart_admin_order_details_totals_edit_action', 'show_pro_required' ); ?>
					<div class="ecdv2-card-header-actions">
						<div class="ecodv2-totals-edit ecodv2-header-edit" id="ec_admin_order_total_edit" onclick="<?php echo ( 'show_pro_required' === $edit_totals_action ) ? "ecodv2_locked( 'totals' );" : esc_attr( $edit_totals_action ) . '( );'; ?> return false;">
							<div class="dashicons-before dashicons-edit"></div><span><?php esc_attr_e( 'Edit', 'wp-easycart' ); ?></span>
						</div>
					</div>
				</div>
				<div class="ecdv2-card-body">
					<?php /* PRO v2: gateway transaction panel. */ ?>
					<?php do_action( 'wp_easycart_ecv2_order_details_payment_panel', $this->order ); ?>

					<div class="ecodv2-totals-box">
						<div id="ec_admin_order_details_totals_content">
							<div class="ecodv2-trow">
								<div class="ecodv2-trow-label"><?php esc_attr_e( 'Sub Total', 'wp-easycart' ); ?>:</div>
								<div class="ecodv2-trow-total"><?php if ( $GLOBALS['currency']->get_symbol_location() ) { echo esc_attr( $GLOBALS['currency']->get_symbol() ); } ?><span id="ec_admin_order_details_totals_sub_total"><?php echo esc_attr( number_format( $this->order->sub_total, 2 ) ); ?></span><?php if ( ! $GLOBALS['currency']->get_symbol_location() ) { echo esc_attr( $GLOBALS['currency']->get_symbol() ); } ?></div>
							</div>
							<div class="ecodv2-trow<?php if ( $this->order->tip_total == 0 ) { ?> ec_admin_initial_hide<?php } ?>" id="ec_admin_order_details_totals_tip_total_row">
								<div class="ecodv2-trow-label"><?php esc_attr_e( 'Tip Total', 'wp-easycart' ); ?>:</div>
								<div class="ecodv2-trow-total"><?php if ( $GLOBALS['currency']->get_symbol_location() ) { echo esc_attr( $GLOBALS['currency']->get_symbol() ); } ?><span id="ec_admin_order_details_totals_tip_total"><?php echo esc_attr( number_format( $this->order->tip_total, 2 ) ); ?></span><?php if ( ! $GLOBALS['currency']->get_symbol_location() ) { echo esc_attr( $GLOBALS['currency']->get_symbol() ); } ?></div>
							</div>
							<div class="ecodv2-trow<?php if ( $this->order->vat_total == 0 ) { ?> ec_admin_initial_hide<?php } ?>" id="ec_admin_order_details_totals_vat_total_row">
								<div class="ecodv2-trow-label"><?php esc_attr_e( 'VAT Total', 'wp-easycart' ); ?> (<span id="ec_admin_order_details_totals_vat_total_rate"><?php echo esc_attr( $this->order->vat_rate ); ?></span>%):</div>
								<div class="ecodv2-trow-total"><?php if ( $GLOBALS['currency']->get_symbol_location() ) { echo esc_attr( $GLOBALS['currency']->get_symbol() ); } ?><span id="ec_admin_order_details_totals_vat_total"><?php echo esc_attr( number_format( $this->order->vat_total, 2 ) ); ?></span><?php if ( ! $GLOBALS['currency']->get_symbol_location() ) { echo esc_attr( $GLOBALS['currency']->get_symbol() ); } ?></div>
							</div>
							<div class="ecodv2-trow<?php if ( $this->order->vat_registration_number == '' ) { ?> ec_admin_initial_hide<?php } ?>" id="ec_admin_order_details_totals_vat_registration_number_row">
								<div class="ecodv2-trow-label"><?php esc_attr_e( 'VAT Registration #', 'wp-easycart' ); ?>:</div>
								<div class="ecodv2-trow-total" id="ec_admin_order_details_totals_vat_registration_number"><?php echo esc_attr( $this->order->vat_registration_number ); ?></div>
							</div>
							<div class="ecodv2-trow<?php if ( $this->order->gst_total == 0 ) { ?> ec_admin_initial_hide<?php } ?>" id="ec_admin_order_details_totals_gst_total_row">
								<div class="ecodv2-trow-label"><?php esc_attr_e( 'GST Total', 'wp-easycart' ); ?> (<span id="ec_admin_order_details_totals_gst_total_rate"><?php echo esc_attr( $this->order->gst_rate ); ?></span>%):</div>
								<div class="ecodv2-trow-total"><?php if ( $GLOBALS['currency']->get_symbol_location() ) { echo esc_attr( $GLOBALS['currency']->get_symbol() ); } ?><span id="ec_admin_order_details_totals_gst_total"><?php echo esc_attr( number_format( $this->order->gst_total, 2 ) ); ?></span><?php if ( ! $GLOBALS['currency']->get_symbol_location() ) { echo esc_attr( $GLOBALS['currency']->get_symbol() ); } ?></div>
							</div>
							<div class="ecodv2-trow<?php if ( $this->order->hst_total == 0 ) { ?> ec_admin_initial_hide<?php } ?>" id="ec_admin_order_details_totals_hst_total_row">
								<div class="ecodv2-trow-label"><?php esc_attr_e( 'HST Total', 'wp-easycart' ); ?> (<span id="ec_admin_order_details_totals_hst_total_rate"><?php echo esc_attr( $this->order->hst_rate ); ?></span>%):</div>
								<div class="ecodv2-trow-total"><?php if ( $GLOBALS['currency']->get_symbol_location() ) { echo esc_attr( $GLOBALS['currency']->get_symbol() ); } ?><span id="ec_admin_order_details_totals_hst_total"><?php echo esc_attr( number_format( $this->order->hst_total, 2 ) ); ?></span><?php if ( ! $GLOBALS['currency']->get_symbol_location() ) { echo esc_attr( $GLOBALS['currency']->get_symbol() ); } ?></div>
							</div>
							<div class="ecodv2-trow<?php if ( $this->order->pst_total == 0 ) { ?> ec_admin_initial_hide<?php } ?>" id="ec_admin_order_details_totals_pst_total_row">
								<div class="ecodv2-trow-label"><?php echo ( 'QC' === strtoupper( (string) $this->order->shipping_state ) ) ? esc_html__( 'QST Total', 'wp-easycart' ) : esc_html__( 'PST Total', 'wp-easycart' ); ?> (<span id="ec_admin_order_details_totals_pst_total_rate"><?php echo esc_attr( $this->order->pst_rate ); ?></span>%):</div>
								<div class="ecodv2-trow-total"><?php if ( $GLOBALS['currency']->get_symbol_location() ) { echo esc_attr( $GLOBALS['currency']->get_symbol() ); } ?><span id="ec_admin_order_details_totals_pst_total"><?php echo esc_attr( number_format( $this->order->pst_total, 2 ) ); ?></span><?php if ( ! $GLOBALS['currency']->get_symbol_location() ) { echo esc_attr( $GLOBALS['currency']->get_symbol() ); } ?></div>
							</div>
							<div class="ecodv2-trow<?php if ( $this->order->duty_total == 0 ) { ?> ec_admin_initial_hide<?php } ?>" id="ec_admin_order_details_totals_duty_total_row">
								<div class="ecodv2-trow-label"><?php esc_attr_e( 'Duty Total', 'wp-easycart' ); ?>:</div>
								<div class="ecodv2-trow-total"><?php if ( $GLOBALS['currency']->get_symbol_location() ) { echo esc_attr( $GLOBALS['currency']->get_symbol() ); } ?><span id="ec_admin_order_details_totals_duty_total"><?php echo esc_attr( number_format( $this->order->duty_total, 2 ) ); ?></span><?php if ( ! $GLOBALS['currency']->get_symbol_location() ) { echo esc_attr( $GLOBALS['currency']->get_symbol() ); } ?></div>
							</div>
							<div class="ecodv2-trow<?php if ( $this->order->tax_total == 0 ) { ?> ec_admin_initial_hide<?php } ?>" id="ec_admin_order_details_totals_tax_total_row">
								<div class="ecodv2-trow-label"><?php esc_attr_e( 'Tax Total', 'wp-easycart' ); ?>:</div>
								<div class="ecodv2-trow-total"><?php if ( $GLOBALS['currency']->get_symbol_location() ) { echo esc_attr( $GLOBALS['currency']->get_symbol() ); } ?><span id="ec_admin_order_details_totals_tax_total"><?php echo esc_attr( number_format( $this->order->tax_total, 2 ) ); ?></span><?php if ( ! $GLOBALS['currency']->get_symbol_location() ) { echo esc_attr( $GLOBALS['currency']->get_symbol() ); } ?></div>
							</div>
							<?php if ( count( $this->order->order_fees ) > 0 ) { ?>
							<?php foreach ( $this->order->order_fees as $order_fee ) { ?>
							<div class="ecodv2-trow" id="ec_admin_order_details_totals_flex_fee_<?php echo esc_attr( $order_fee->order_fee_id ); ?>_row">
								<div class="ecodv2-trow-label"><?php echo esc_attr( $order_fee->fee_label ); ?></div>
								<div class="ecodv2-trow-total"><?php if ( $GLOBALS['currency']->get_symbol_location() ) { echo esc_attr( $GLOBALS['currency']->get_symbol() ); } ?><span id="ec_admin_order_details_totals_flex_fee_<?php echo esc_attr( $order_fee->order_fee_id ); ?>"><?php echo esc_attr( number_format( $order_fee->fee_total, 2 ) ); ?></span><?php if ( ! $GLOBALS['currency']->get_symbol_location() ) { echo esc_attr( $GLOBALS['currency']->get_symbol() ); } ?></div>
							</div>
							<?php } ?>
							<?php } ?>
							<div class="ecodv2-trow">
								<div class="ecodv2-trow-label"><?php esc_attr_e( 'Shipping Total', 'wp-easycart' ); ?>:</div>
								<div class="ecodv2-trow-total"><?php if ( $GLOBALS['currency']->get_symbol_location() ) { echo esc_attr( $GLOBALS['currency']->get_symbol() ); } ?><span id="ec_admin_order_details_totals_shipping_total"><?php echo esc_attr( number_format( $this->order->shipping_total, 2 ) ); ?></span><?php if ( ! $GLOBALS['currency']->get_symbol_location() ) { echo esc_attr( $GLOBALS['currency']->get_symbol() ); } ?></div>
							</div>
							<div class="ecodv2-trow<?php if ( $this->order->discount_total == 0 ) { ?> ec_admin_initial_hide<?php } ?>" id="ec_admin_order_details_totals_discount_total_row">
								<div class="ecodv2-trow-label"><?php esc_attr_e( 'Discount Total', 'wp-easycart' ); ?>:</div>
								<div class="ecodv2-trow-total ecodv2-discount-total">-<?php if ( $GLOBALS['currency']->get_symbol_location() ) { echo esc_attr( $GLOBALS['currency']->get_symbol() ); } ?><span id="ec_admin_order_details_totals_discount_total"><?php echo esc_attr( number_format( $this->order->discount_total, 2 ) ); ?></span><?php if ( ! $GLOBALS['currency']->get_symbol_location() ) { echo esc_attr( $GLOBALS['currency']->get_symbol() ); } ?></div>
							</div>
							<div class="ecodv2-trow ecodv2-trow-refund<?php if ( $this->order->refund_total == 0 ) { ?> ec_admin_initial_hide<?php } ?>" id="ec_admin_order_details_totals_refund_total_row">
								<div class="ecodv2-trow-label"><?php esc_attr_e( 'Refund Total', 'wp-easycart' ); ?>:</div>
								<div class="ecodv2-trow-total"><?php if ( $GLOBALS['currency']->get_symbol_location() ) { echo esc_attr( $GLOBALS['currency']->get_symbol() ); } ?><span id="ec_admin_order_details_totals_refund_total"><?php echo esc_attr( number_format( $this->order->refund_total, 2 ) ); ?></span><?php if ( ! $GLOBALS['currency']->get_symbol_location() ) { echo esc_attr( $GLOBALS['currency']->get_symbol() ); } ?></div>
							</div>
							<div class="ecodv2-trow ecodv2-grand-row">
								<div class="ecodv2-trow-label"><?php esc_attr_e( 'Grand Total', 'wp-easycart' ); ?>:</div>
								<div class="ecodv2-trow-total"><?php if ( $GLOBALS['currency']->get_symbol_location() ) { echo esc_attr( $GLOBALS['currency']->get_symbol() ); } ?><span id="ec_admin_order_details_totals_grand_total"><?php echo esc_attr( number_format( $this->order->grand_total, 2 ) ); ?></span><?php if ( ! $GLOBALS['currency']->get_symbol_location() ) { echo esc_attr( $GLOBALS['currency']->get_symbol() ); } ?></div>
							</div>
						</div>
						<?php do_action( 'wp_easycart_admin_order_details_totals_content_end' ); ?>
					</div>

					<?php /* Gift card + coupon ( inputs must stay in the DOM: orders.js reads
					        #giftcard_id / #promo_code on every notes save ). Collapsed when
					        neither applies; open when either has a value. */ ?>
					<?php $wpec_payment_meta_used = ( '' !== trim( (string) $this->order->giftcard_id ) || '' !== trim( (string) $this->order->promo_code ) ); ?>
					<details class="ecodv2-payment-meta"<?php if ( $wpec_payment_meta_used ) { ?> open<?php } ?>>
						<summary>
							<span class="dashicons dashicons-tickets-alt"></span>
							<?php esc_attr_e( 'Gift card & coupon', 'wp-easycart' ); ?>
							<?php if ( ! $wpec_payment_meta_used ) { ?>
							<span class="ecodv2-payment-meta-none"><?php esc_attr_e( 'none used', 'wp-easycart' ); ?></span>
							<?php } ?>
						</summary>
						<div class="ecodv2-payment-meta-fields">
							<?php do_action( 'wp_easycart_ecv2_order_details_payment_meta' ); ?>
						</div>
					</details>
				</div>
			</div>

			<?php /* ---------- Activity card: staff comments ( PRO ) + pinned note + history ---------- */ ?>
			<div class="ecdv2-card ecodv2-card-activity">
				<div class="ecdv2-card-header">
					<h3 class="ecdv2-card-title"><?php esc_attr_e( 'Activity', 'wp-easycart' ); ?></h3>
					<span class="ecdv2-card-hint"><?php esc_attr_e( 'Internal — not shown to the customer', 'wp-easycart' ); ?></span>
				</div>
				<div class="ecdv2-card-body">
					<?php /* PRO v2: staff comments composer + list. */ ?>
					<?php /* ---------- Pinned note ( V3.8 ) ----------
					        One always-visible sticky note per order, stored in
					        ec_order.order_notes. The legacy #order_notes textarea stays
					        in the DOM ( hidden ) because ec_admin_process_order_info
					        reads it by id on every info save. */ ?>
					<?php $wpec_pin = trim( (string) $this->order->order_notes ); ?>
					<div class="ecodv2-pin-strip"<?php if ( '' === $wpec_pin ) { ?> style="display:none;"<?php } ?> id="ecodv2_pin_strip">
						<span class="dashicons dashicons-sticky"></span>
						<span class="ecodv2-pin-text" id="ecodv2_pin_text"><?php echo esc_html( $wpec_pin ); ?></span>
						<button type="button" class="ecodv2-edit-link" onclick="ecodv2_pin_edit(); return false;"><span class="dashicons dashicons-edit"></span> <?php esc_attr_e( 'Edit', 'wp-easycart' ); ?></button>
					</div>
					<div class="ecodv2-pin-add" id="ecodv2_pin_add"<?php if ( '' !== $wpec_pin ) { ?> style="display:none;"<?php } ?>>
						<a href="#" onclick="ecodv2_pin_edit(); return false;"><span class="dashicons dashicons-sticky"></span> <?php esc_attr_e( 'Pin a note to this order', 'wp-easycart' ); ?></a>
					</div>
					<div class="ecodv2-pin-editor" id="ecodv2_pin_editor" style="display:none;">
						<textarea id="ecodv2_pin_input" placeholder="<?php esc_attr_e( 'Something the whole team should see on this order…', 'wp-easycart' ); ?>"></textarea>
						<div class="ecodv2-pin-editor-foot">
							<button type="button" class="ecodv2-pin-unpin" id="ecodv2_pin_unpin"<?php if ( '' === $wpec_pin ) { ?> style="display:none;"<?php } ?> onclick="ecodv2_pin_save( true ); return false;"><?php esc_attr_e( 'Unpin', 'wp-easycart' ); ?></button>
							<div class="ecodv2-drawer-foot-spacer"></div>
							<button type="button" class="ecv2-btn ecv2-btn-sm" onclick="ecodv2_pin_cancel(); return false;"><?php esc_attr_e( 'Cancel', 'wp-easycart' ); ?></button>
							<button type="button" class="ecv2-btn ecv2-btn-primary ecv2-btn-sm" id="ecodv2_pin_save_btn" onclick="ecodv2_pin_save( false ); return false;"><?php esc_attr_e( 'Save Note', 'wp-easycart' ); ?></button>
						</div>
					</div>
					<div class="ec_admin_initial_hide">
						<textarea name="order_notes" id="order_notes"><?php echo esc_attr( $this->order->order_notes ); ?></textarea>
					</div>

					<?php do_action( 'wp_easycart_ecv2_order_details_timeline_pre', $this->order ); ?>

					<?php /* Order history — free stub or PRO timeline via the V1 hook. */ ?>
					<div class="ecodv2-history-wrap" id="ecodv2_history_wrap">
						<?php do_action( 'wp_easycart_admin_order_details_left_content_end' ); ?>
						<div class="ecodv2-history-showall" id="ecodv2_history_showall">
							<button type="button" class="ecv2-btn ecv2-btn-sm" onclick="ecodv2_open_history_drawer(); return false;"><span class="dashicons dashicons-list-view"></span> <?php esc_attr_e( 'Show all activity', 'wp-easycart' ); ?> (<span id="ecodv2_history_total">0</span>)</button>
						</div>
					</div>
				</div>
			</div>
		</div>

		<?php /* ------------------------------ SIDEBAR ------------------------------ */ ?>
		<div class="ecodv2-side">
			<?php wp_easycart_admin()->preloader->print_preloader( 'ec_admin_shipping_details' ); ?>
			<div class="ecodv2-side-inner">

				<?php /* ---------- Customer card ---------- */ ?>
				<div class="ecdv2-card ecodv2-card-customer">
					<div class="ecdv2-card-header">
						<h3 class="ecdv2-card-title"><?php esc_attr_e( 'Customer', 'wp-easycart' ); ?></h3>
					</div>
					<div class="ecdv2-card-body">
						<?php $edit_order_details_action = apply_filters( 'wp_easycart_admin_order_details_order_edit_action', 'show_pro_required' ); ?>
						<div id="ec_admin_order_details_user_id_content" class="ecodv2-block">
							<div class="ecodv2-block-head">
								<div class="ecodv2-eyebrow"><?php esc_attr_e( 'User Account', 'wp-easycart' ); ?></div>
								<button type="button" class="ecodv2-edit-link" onclick="ecodv2_open_edit_drawer( 'customer', '<?php echo esc_attr( $edit_order_details_action ); ?>' ); return false;"><span class="dashicons dashicons-edit"></span> <?php esc_attr_e( 'Edit', 'wp-easycart' ); ?></button>
							</div>
							<?php
								// Avatar initials: billing name, then email as fallback.
								$wpec_initials = '';
								if ( '' != trim( (string) $this->order->billing_first_name ) ) {
									$wpec_initials .= mb_strtoupper( mb_substr( trim( (string) $this->order->billing_first_name ), 0, 1 ) );
								}
								if ( '' != trim( (string) $this->order->billing_last_name ) ) {
									$wpec_initials .= mb_strtoupper( mb_substr( trim( (string) $this->order->billing_last_name ), 0, 1 ) );
								}
								if ( '' == $wpec_initials && '' != trim( (string) $this->order->user_email ) ) {
									$wpec_initials = mb_strtoupper( mb_substr( trim( (string) $this->order->user_email ), 0, 1 ) );
								}
							?>
							<div class="ecodv2-user-card">
								<div class="ecodv2-avatar<?php if ( ! $this->order->user_id ) { ?> ecodv2-avatar-guest<?php } ?>"><?php if ( '' != $wpec_initials ) { echo esc_html( $wpec_initials ); } else { ?><span class="dashicons dashicons-admin-users"></span><?php } ?></div>
								<div class="ecodv2-user-main">
									<div class="ecodv2-user-topline">
										<span class="ecodv2-user-card-name"><?php echo esc_html( '' !== $wpec_customer_name ? $wpec_customer_name : __( 'Customer', 'wp-easycart' ) ); ?></span>
										<?php /* Account chip + link; repainted from the ec_admin_ajax_update_order_user response. */ ?>
										<span class="ecodv2-user-account" id="ecodv2_user_account"><?php echo wp_easycart_admin_order_account_badge_html( $this->order->user_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the helper escapes every value it prints. ?></span>
									</div>
									<div class="ecodv2-contact-row">
										<span class="dashicons dashicons-email-alt ecodv2-contact-icon"></span>
										<span class="ecodv2-contact-value ecodv2-user-card-email"><span id="ec_admin_order_details_user_email"><a href="mailto: <?php echo esc_attr( $this->order->user_email ); ?>"><?php echo esc_attr( $this->order->user_email ); ?></a></span></span>
										<button type="button" class="ecodv2-copy-link ecodv2-contact-copy" data-copy-target="ec_admin_order_details_user_email" onclick="ecodv2_copy_text( this ); return false;" title="<?php esc_attr_e( 'Copy email', 'wp-easycart' ); ?>"><span class="dashicons dashicons-admin-page"></span></button>
									</div>
									<div class="ecodv2-contact-sub ecodv2-user-card-email"><span id="ec_admin_order_details_email_other"><?php if ( isset( $this->order->email_other ) && '' != $this->order->email_other ) { ?><a href="mailto: <?php echo esc_attr( $this->order->email_other ); ?>"><?php echo esc_attr( $this->order->email_other ); ?></a><?php } ?></span></div>
									<?php if ( '' != trim( (string) $this->order->billing_phone ) ) { $wpec_phone = wp_easycart_format_phone( $this->order->billing_phone, $this->order->billing_country ); ?>
									<div class="ecodv2-contact-row">
										<span class="dashicons dashicons-phone ecodv2-contact-icon"></span>
										<span class="ecodv2-contact-value"><span id="ec_admin_order_details_user_phone"><a href="<?php echo esc_attr( $wpec_phone['href'] ); ?>"><?php echo esc_html( $wpec_phone['display'] ); ?></a></span></span>
										<button type="button" class="ecodv2-copy-link ecodv2-contact-copy" data-copy-target="ec_admin_order_details_user_phone" onclick="ecodv2_copy_text( this ); return false;" title="<?php esc_attr_e( 'Copy phone', 'wp-easycart' ); ?>"><span class="dashicons dashicons-admin-page"></span></button>
									</div>
									<?php } ?>
								</div>
							</div>
						</div>

						<div class="ecodv2-block" id="ec_admin_view_order_information">
							<div class="ecodv2-block-head">
								<div class="ecodv2-eyebrow"><?php esc_attr_e( 'Payment Info', 'wp-easycart' ); ?></div>
								<button type="button" class="ecodv2-edit-link" id="ec_admin_order_details_edit" onclick="ecodv2_open_edit_drawer( 'customer', '<?php echo esc_attr( $edit_order_details_action ); ?>' ); return false;"><span class="dashicons dashicons-edit"></span> <?php esc_attr_e( 'Edit', 'wp-easycart' ); ?></button>
							</div>
							<div class="ecodv2-payment-row">
								<span class="dashicons dashicons-money-alt ecodv2-payment-icon"></span>
								<div class="ecodv2-payment-text">
									<span id="ec_admin_order_details_card_holder_name" class="ecodv2-kv-name"><?php if ( $this->order->card_holder_name != '' ) { ?><?php echo esc_attr( $this->order->card_holder_name ); ?><?php } else { ?><?php echo esc_attr( $this->order->shipping_first_name ); ?> <?php echo esc_attr( $this->order->shipping_last_name ); ?><?php } ?></span>
									<span class="ecodv2-cc-line"><span id="ec_admin_order_details_creditcard_digits"><?php if ( $this->order->creditcard_digits != '' ) { ?><span class="ecodv2-cc-dots">&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;</span> <b><?php echo esc_attr( $this->order->creditcard_digits ); ?></b><?php } ?></span> <span id="ec_admin_order_details_cc_exp"><?php if ( $this->order->cc_exp_month != '' ) { ?><span class="ecodv2-cc-exp">&middot; <?php echo esc_attr( $this->order->cc_exp_month ); ?> / <?php echo esc_attr( $this->order->cc_exp_year ); ?></span><?php } ?></span></span>
								</div>
							</div>
						</div>
						<?php do_action( 'wp_easycart_order_details_order_information' ); ?>

						<?php /* PRO v2: lifetime stats + quick links. Re-filled by ec_admin_ajax_update_order_user when the order's account changes. */ ?>
						<div class="ecodv2-customer-card-extra" id="ecodv2_customer_card_extra"><?php do_action( 'wp_easycart_ecv2_order_details_customer_card', $this->order ); ?></div>
					</div>
				</div>

				<?php /* ---------- Pickup cards (conditional, ids preserved) ---------- */ ?>
				<?php if ( $this->order->includes_preorder_items ) { ?>
				<div class="ecdv2-card">
					<div class="ecdv2-card-header"><h3 class="ecdv2-card-title"><?php esc_attr_e( 'Preorder Pick Up Details', 'wp-easycart' ); ?></h3></div>
					<div class="ecdv2-card-body">
						<div id="ec_admin_order_details_pickup_content" class="ecodv2-block">
							<div>
								<div style="padding:5px 0;">
									<input type="text" class="ec_admin_datepicker" id="ec_order_pickup_date" style="float:none;" value="<?php
									$date_timestamp = strtotime( $this->order->pickup_date );
									if ( $date_timestamp > 0 ) {
										echo esc_attr( date( apply_filters( 'wp_easycart_pickup_date_placeholder_format', 'F d, Y' ), $date_timestamp ) );
									}
									?>" placeholder="<?php echo esc_attr__( 'Choose a Pick Up Date', 'wp-easycart' ); ?>" />
									<select name="ec_order_pickup_date_time" id="ec_order_pickup_date_time" style="margin: 5px 0 0 0; float: left; width: 100%;"><?php $selected_pickup_date_time = ''; if ( isset( $this->order->pickup_date ) && '' != $this->order->pickup_date ) { $selected_pickup_date_time = date( 'H:i', $date_timestamp ); } ?>
										<option value=""<?php if ( '' == $selected_pickup_date_time ) { ?> selected="selected"<?php } ?>><?php echo wp_easycart_language()->get_text( 'cart_payment_information', 'preorder_pickup_time_label' ); ?></option>
										<?php for ( $hour = 0; $hour < 24; $hour++ ) { ?>
										<option value="<?php echo esc_attr( date( 'H:i', strtotime( date( 'Y-m-d ' . $hour . ':00' ) ) ) ); ?>"<?php if ( date( 'H:i', strtotime( date( 'Y-m-d ' . $hour . ':00' ) ) ) == $selected_pickup_date_time ) { ?> selected="selected"<?php } ?>><?php echo esc_attr( date( get_option( 'time_format' ), strtotime( date( 'Y-m-d ' . $hour . ':00' ) ) ) ); ?> - <?php echo esc_attr( date( get_option( 'time_format' ), strtotime( date( 'Y-m-d ' . $hour . ':00' ) . ' + 1 hour' ) ) ); ?></option>
										<?php } ?>
									</select>
								</div>
							</div>
							<?php if ( get_option( 'ec_option_pickup_enable_locations' ) ) {
								$locations = $wpdb->get_results( 'SELECT * FROM ec_location ORDER BY location_label ASC' );
								if ( is_array( $locations ) && count( $locations ) > 0 ) { ?>
							<div>
								<div style="padding:5px 0;">
									<select class="select2" name="ec_order_location_id" id="ec_order_location_id" style="margin: 5px 0 0 0; float: left; width: 100%;">
										<option value="0"><?php echo esc_attr__( 'Choose Pickup Location', 'wp-easycart' ); ?></option>
										<?php foreach ( $locations as $location ) { ?>
										<option value="<?php echo esc_attr( $location->location_id ); ?>"<?php if ( $location->location_id == $this->order->location_id ) { ?> selected="selected"<?php } ?>><?php echo esc_attr( $location->location_label ); ?></option>
										<?php } ?>
									</select>
								</div>
							</div>
							<?php }
							} ?>
						</div>
					</div>
				</div>
				<?php } ?>

				<?php if ( $this->order->includes_restaurant_type ) { ?>
				<div class="ecdv2-card">
					<div class="ecdv2-card-header"><h3 class="ecdv2-card-title"><?php esc_attr_e( 'Pick Up Details', 'wp-easycart' ); ?></h3></div>
					<div class="ecdv2-card-body">
						<div id="ec_admin_order_details_restaurant_content" class="ecodv2-block">
							<div>
								<div style="padding:5px 0;">
									<input type="text" class="ec_admin_datepicker" id="ec_order_pickup_time_date" style="float:none;" value="<?php
									$pickup_time = $this->order->pickup_time;
									$pickup_time_timestamp = strtotime( $pickup_time );
									if ( $pickup_time_timestamp > 0 ) {
										echo esc_attr( date( apply_filters( 'wp_easycart_pickup_date_placeholder_format', 'F d, Y' ), $pickup_time_timestamp ) );
									}
									?>" placeholder="<?php echo esc_attr__( 'Choose a Pick Up Date', 'wp-easycart' ); ?>" />
									<select name="ec_order_pickup_time_time" id="ec_order_pickup_time_time" style="margin: 5px 0 0 0; float: left; width: 100%;"><?php
										$selected_pickup_time_time = '';
										if ( isset( $this->order->pickup_time ) && '' != $this->order->pickup_time ) {
											$pickup_time_minutes = (int) date( 'i', $pickup_time_timestamp );
											$pickup_time_rounded_minutes = round( $pickup_time_minutes / 5 ) * 5;
											$pickup_time_updated_timestamp = strtotime( date( 'Y-m-d H:', $pickup_time_timestamp ) . sprintf( '%02d:00', $pickup_time_rounded_minutes ) );
											$selected_pickup_time_time = date( 'H:i', $pickup_time_updated_timestamp );
										} ?>
										<option value=""<?php if ( '' == $selected_pickup_time_time ) { ?> selected="selected"<?php } ?>><?php echo wp_easycart_language()->get_text( 'cart_payment_information', 'preorder_pickup_time_label' ); ?></option>
										<?php for ( $hour = 0; $hour < 24; $hour++ ) { ?>
											<?php for ( $minute = 0; $minute < 60; $minute = $minute + 5 ) { ?>
										<option value="<?php echo esc_attr( $hour . ':' . sprintf( '%02d', $minute ) ); ?>"<?php if ( $hour . ':' . sprintf( '%02d', $minute ) == $selected_pickup_time_time ) { ?> selected="selected"<?php } ?>><?php echo esc_attr( date( get_option( 'time_format' ), strtotime( date( 'Y-m-d ' . sprintf( '%02d', $hour ) . ':' . sprintf( '%02d', $minute ) ) ) ) ); ?></option>
											<?php } ?>
										<?php } ?>
									</select>
								</div>
							</div>
						</div>
					</div>
				</div>
				<?php } ?>

				<?php if ( $this->order->includes_preorder_items || $this->order->includes_restaurant_type ) { ?>
					<script>
						jQuery( '.ec_admin_datepicker' ).datepicker( {
							dateFormat:"<?php echo esc_attr( apply_filters( 'wp_easycart_pickup_date_jquery_format', 'MM d, yy' ) ); ?>",
						} );
					</script>
				<?php } ?>

				<?php do_action( 'wpeasycart_order_details_shipping_address_pre', $this->order ); ?>

				<?php /* ---------- Shipping card ( address + method/tracking + order details hook ) ---------- */ ?>
				<div class="ecdv2-card ecodv2-card-shipping">
					<div class="ecdv2-card-header"><h3 class="ecdv2-card-title"><?php esc_attr_e( 'Shipping', 'wp-easycart' ); ?></h3></div>
					<div class="ecdv2-card-body">
						<?php $edit_shipping_action = apply_filters( 'wp_easycart_admin_order_details_shipping_edit_action', 'show_pro_required' ); ?>
						<div id="ec_admin_order_details_shipping_content" class="ecodv2-block">
							<div class="ecodv2-block-head">
								<div class="ecodv2-eyebrow"><?php esc_attr_e( 'Shipping Address', 'wp-easycart' ); ?></div>
								<?php $wpec_map_q = rawurlencode( trim( $this->order->shipping_address_line_1 . ' ' . $this->order->shipping_city . ' ' . $this->order->shipping_state . ' ' . $this->order->shipping_zip . ' ' . $this->order->shipping_country_name ) ); ?>
								<a class="ecodv2-copy-link" href="https://www.google.com/maps/search/?api=1&query=<?php echo esc_attr( $wpec_map_q ); ?>" target="_blank" title="<?php esc_attr_e( 'View on Google Maps', 'wp-easycart' ); ?>"><span class="dashicons dashicons-location"></span> <?php esc_attr_e( 'Map', 'wp-easycart' ); ?></a>
								<button type="button" class="ecodv2-copy-link" onclick="ecodv2_copy_address( 'shipping', this ); return false;" title="<?php esc_attr_e( 'Copy address', 'wp-easycart' ); ?>"><span class="dashicons dashicons-admin-page"></span> <?php esc_attr_e( 'Copy', 'wp-easycart' ); ?></button>
								<button type="button" class="ecodv2-edit-link" id="ec_admin_order_shipping_edit_button" onclick="ecodv2_open_edit_drawer( 'shipping', '<?php echo esc_attr( $edit_shipping_action ); ?>' ); return false;"><span class="dashicons dashicons-edit"></span> <?php esc_attr_e( 'Edit', 'wp-easycart' ); ?></button>
							</div>
							<?php do_action( 'wp_easycart_admin_order_details_shipping_after_title', $this->order ); ?>
							<div id="ec_admin_order_details_shipping_name"><?php echo esc_attr( $this->order->shipping_first_name ); ?> <?php echo esc_attr( $this->order->shipping_last_name ); ?></div>
							<?php do_action( 'wp_easycart_admin_order_details_shipping_after_name', $this->order ); ?>
							<div id="ec_admin_order_details_shipping_company"><?php if ( $this->order->shipping_company_name != '' ) { ?><?php echo esc_attr( $this->order->shipping_company_name ); ?><?php } ?></div>
							<?php do_action( 'wp_easycart_admin_order_details_shipping_after_company_name', $this->order ); ?>
							<div id="ec_admin_order_details_shipping_address1"><?php echo esc_attr( $this->order->shipping_address_line_1 ); ?></div>
							<?php do_action( 'wp_easycart_admin_order_details_shipping_after_address1', $this->order ); ?>
							<div id="ec_admin_order_details_shipping_address2"><?php if ( $this->order->shipping_address_line_2 != '' ) { ?><?php echo esc_attr( $this->order->shipping_address_line_2 ); ?><?php } ?></div>
							<?php do_action( 'wp_easycart_admin_order_details_shipping_after_address2', $this->order ); ?>
							<div id="ec_admin_order_details_shipping_address3"><?php echo esc_attr( $this->order->shipping_city ); ?> <?php echo esc_attr( $this->order->shipping_state ); ?> <?php echo esc_attr( $this->order->shipping_zip ); ?></div>
							<?php do_action( 'wp_easycart_admin_order_details_shipping_after_city', $this->order ); ?>
							<div id="ec_admin_order_details_shipping_country"><?php echo esc_attr( $this->order->shipping_country_name ); ?></div>
							<?php do_action( 'wp_easycart_admin_order_details_shipping_after_country_name', $this->order ); ?>
							<div id="ec_admin_order_details_shipping_phone"><?php $wpec_sp = wp_easycart_format_phone( $this->order->shipping_phone, $this->order->shipping_country ); echo esc_html( $wpec_sp['display'] ); ?></div>
							<?php do_action( 'wp_easycart_admin_order_details_shipping_after_phone', $this->order ); ?>
						</div>
						<?php do_action( 'wp_easycart_admin_order_details_shipping_content_end' ); ?>

						
						

						<?php /* Shipment hook ( order weight; gift/coupon now live in Payment & Totals ). */ ?>
						
					</div>
				</div>

				<?php /* ---------- Billing card ---------- */ ?>
				<div class="ecdv2-card ecodv2-card-billing">
					<div class="ecdv2-card-header"><h3 class="ecdv2-card-title"><?php esc_attr_e( 'Billing', 'wp-easycart' ); ?></h3></div>
					<div class="ecdv2-card-body">
						<div id="ec_admin_order_details_billing_content" class="ecodv2-block">
							<?php $edit_billing_action = apply_filters( 'wp_easycart_admin_order_details_billing_edit_action', 'show_pro_required' ); ?>
							<div class="ecodv2-block-head">
								<div class="ecodv2-eyebrow"><?php esc_attr_e( 'Billing Address', 'wp-easycart' ); ?></div>
								<button type="button" class="ecodv2-copy-link" onclick="ecodv2_copy_address( 'billing', this ); return false;" title="<?php esc_attr_e( 'Copy address', 'wp-easycart' ); ?>"><span class="dashicons dashicons-admin-page"></span> <?php esc_attr_e( 'Copy', 'wp-easycart' ); ?></button>
								<button type="button" class="ecodv2-edit-link" id="ec_admin_order_billing_edit_button" onclick="ecodv2_open_edit_drawer( 'billing', '<?php echo esc_attr( $edit_billing_action ); ?>' ); return false;"><span class="dashicons dashicons-edit"></span> <?php esc_attr_e( 'Edit', 'wp-easycart' ); ?></button>
							</div>
							<?php do_action( 'wp_easycart_admin_order_details_billing_after_title', $this->order ); ?>
							<div id="ec_admin_order_details_billing_name"><?php echo esc_attr( $this->order->billing_first_name ); ?> <?php echo esc_attr( $this->order->billing_last_name ); ?></div>
							<?php do_action( 'wp_easycart_admin_order_details_billing_after_name', $this->order ); ?>
							<div id="ec_admin_order_details_billing_company"><?php if ( $this->order->billing_company_name != '' ) { ?><?php echo esc_attr( $this->order->billing_company_name ); ?><?php } ?></div>
							<?php do_action( 'wp_easycart_admin_order_details_billing_after_company_name', $this->order ); ?>
							<div id="ec_admin_order_details_billing_address1"><?php echo esc_attr( $this->order->billing_address_line_1 ); ?></div>
							<?php do_action( 'wp_easycart_admin_order_details_billing_after_address1', $this->order ); ?>
							<div id="ec_admin_order_details_billing_address2"><?php if ( $this->order->billing_address_line_2 != '' ) { ?><?php echo esc_attr( $this->order->billing_address_line_2 ); ?><?php } ?></div>
							<?php do_action( 'wp_easycart_admin_order_details_billing_after_address2', $this->order ); ?>
							<div id="ec_admin_order_details_billing_address3"><?php echo esc_attr( $this->order->billing_city ); ?> <?php echo esc_attr( $this->order->billing_state ); ?> <?php echo esc_attr( $this->order->billing_zip ); ?></div>
							<?php do_action( 'wp_easycart_admin_order_details_billing_after_city', $this->order ); ?>
							<div id="ec_admin_order_details_billing_country"><?php echo esc_attr( $this->order->billing_country_name ); ?></div>
							<?php do_action( 'wp_easycart_admin_order_details_billing_after_country_name', $this->order ); ?>
							<div id="ec_admin_order_details_billing_phone"><?php $wpec_bp = wp_easycart_format_phone( $this->order->billing_phone, $this->order->billing_country ); echo esc_html( $wpec_bp['display'] ); ?></div>
							<?php do_action( 'wp_easycart_admin_order_details_billing_after_phone', $this->order ); ?>
						</div>
						<?php do_action( 'wp_easycart_admin_order_details_billing_content_end' ); ?>
					</div>
				</div>

				<?php /* ---------- Customer Notes card ( V4.1 — self-contained popover editor ) ---------- */ ?>
				<?php $wpec_has_cnotes = ( '' != trim( (string) $this->order->order_customer_notes ) ); ?>
				<div class="ecdv2-card ecodv2-card-customer-notes">
					<div class="ecdv2-card-header">
						<h3 class="ecdv2-card-title"><?php esc_attr_e( 'Customer Notes', 'wp-easycart' ); ?></h3>
						<span class="ecdv2-card-hint"><?php esc_attr_e( 'Provided at checkout', 'wp-easycart' ); ?></span>
						<div class="ecdv2-card-header-actions">
							<button type="button" class="ecodv2-edit-link ecodv2-cnotes-trigger" id="ec_admin_order_details_customer_notes_edit"<?php if ( ! $wpec_has_cnotes ) { ?> style="display:none;"<?php } ?> onclick="ecodv2_open_cnotes_popover(); return false;"><span class="dashicons dashicons-edit"></span> <?php esc_attr_e( 'Edit', 'wp-easycart' ); ?></button>
						</div>
					</div>
					<div class="ecdv2-card-body">
						<div id="ec_admin_order_details_customer_notes_content" class="ecodv2-block ecodv2-cnotes-anchor">
							<span id="ec_admin_order_details_customer_notes" class="ecodv2-cnotes-text"><?php echo nl2br( esc_attr( $this->order->order_customer_notes ) ); ?></span>
							<div id="ec_admin_order_details_customer_notes_empty_message" class="ecodv2-cnotes-empty<?php if ( $wpec_has_cnotes ) { ?> ec_admin_initial_hide<?php } ?>">
								<span class="dashicons dashicons-format-chat"></span>
								<span class="ecodv2-cnotes-empty-text"><?php esc_attr_e( 'No notes were left at checkout.', 'wp-easycart' ); ?></span>
								<button type="button" class="ecv2-btn ecv2-btn-sm ecodv2-cnotes-trigger" onclick="ecodv2_open_cnotes_popover(); return false;"><span class="dashicons dashicons-plus-alt2"></span> <?php esc_attr_e( 'Add a note', 'wp-easycart' ); ?></button>
							</div>

							<?php /* Popover editor — saves directly via ec_admin_ajax_edit_customer_notes;
							        no dependence on the legacy V1 show/hide toggle. */ ?>
							<div class="ecodv2-cnotes-popover" id="ecodv2_cnotes_popover">
								<div class="ecodv2-cnotes-popover-head"><?php esc_attr_e( 'Customer note', 'wp-easycart' ); ?></div>
								<div id="ec_admin_order_details_customer_notes_form" class="ecodv2-cnotes-form">
									<textarea name="order_customer_notes" id="order_customer_notes" placeholder="<?php esc_attr_e( 'Note from or for the customer…', 'wp-easycart' ); ?>"><?php echo esc_attr( $this->order->order_customer_notes ); ?></textarea>
								</div>
								<div class="ecodv2-cnotes-popover-foot">
									<span class="ecodv2-cnotes-hint"><span class="dashicons dashicons-visibility"></span> <?php esc_attr_e( 'Visible to you and the customer', 'wp-easycart' ); ?></span>
									<button type="button" class="ecv2-btn ecv2-btn-sm" onclick="ecodv2_cancel_cnotes(); return false;"><?php esc_attr_e( 'Cancel', 'wp-easycart' ); ?></button>
									<button type="button" class="ecv2-btn ecv2-btn-primary ecv2-btn-sm" id="ec_admin_order_details_customer_notes_save" onclick="ecodv2_save_cnotes(); return false;"><?php esc_attr_e( 'Save', 'wp-easycart' ); ?></button>
								</div>
							</div>
						</div>
					</div>
				</div>

				<?php /* ---------- Metadata card ---------- */ ?>
				<div class="ecdv2-card ecodv2-card-meta">
					<div class="ecdv2-card-header"><h3 class="ecdv2-card-title"><?php esc_attr_e( 'Additional Details', 'wp-easycart' ); ?></h3></div>
					<div class="ecdv2-card-body">
						<div class="ecodv2-block" id="ec_admin_view_order_information_bottom">
							<?php $edit_order_details_bottom_action = apply_filters( 'wp_easycart_admin_order_details_order_bottom_edit_action', 'show_pro_required' ); ?>
							<div class="ecodv2-block-head">
								<div class="ecodv2-eyebrow"><?php esc_attr_e( 'Order Metadata', 'wp-easycart' ); ?></div>
								<button type="button" class="ecodv2-edit-link" id="ec_admin_order_details_edit_bottom" onclick="ecodv2_open_edit_drawer( 'details', '<?php echo esc_attr( $edit_order_details_bottom_action ); ?>' ); return false;"><span class="dashicons dashicons-edit"></span> <?php esc_attr_e( 'Edit', 'wp-easycart' ); ?></button>
							</div>
							<span id="ec_admin_order_details_ip_address"><?php if ( $this->order->order_ip_address != '' ) { echo 'IP: ' . esc_attr( $this->order->order_ip_address ); ?><br /><?php } ?></span>
							<span id="ec_admin_order_details_agreed_to_terms"><?php esc_attr_e( 'Agreed to Terms', 'wp-easycart' ); ?>: <?php if ( ! $this->order->agreed_to_terms ) { echo 'No'; } else { echo 'Yes'; } ?></span>
						</div>
						<?php do_action( 'wp_easycart_order_details_order_bottom_information' ); ?>
					</div>
				</div>

				<?php /* Activity moved to the main column ( V2.2 ). */ ?>

			</div>
		</div>
	</div>

	<?php /* ============ Edit Order drawer ============
	        Sections are populated at load by orders-details-v2.js, which
	        relocates the PRO-included edit forms ( by ID ) into the hosts
	        below. PRO save bindings survive the move; on free installs the
	        forms don't exist and the Edit buttons fall back to the upsell. */ ?>
	<?php /* ============ Create Shipping Label popup ( V3.0 ) ============ */
	$wpec_carrier_defs = array(
		'usps' => array( 'label' => 'USPS', 'sub' => __( 'Click-N-Ship', 'wp-easycart' ), 'url' => 'https://cns.usps.com/', 'configured' => ( ! empty( get_option( 'ec_option_usps_v3_client_id' ) ) ), 'keywords' => 'usps|priority|ground advantage|first-class|first class|media mail|parcel select|click' ),
		'ups' => array( 'label' => 'UPS', 'sub' => __( 'Ship', 'wp-easycart' ), 'url' => 'https://www.ups.com/ship/guided', 'configured' => ( ! empty( get_option( 'ec_option_ups_token_info' ) ) ), 'keywords' => 'ups|2nd day air|second day air|next day air|3 day select|worldwide' ),
		'fedex' => array( 'label' => 'FedEx', 'sub' => __( 'Ship Manager', 'wp-easycart' ), 'url' => 'https://www.fedex.com/en-us/shipping.html', 'configured' => ( ! empty( get_option( 'ec_option_fedex_api_key' ) ) ), 'keywords' => 'fedex|home delivery|smartpost|2day|overnight' ),
		'dhl' => array( 'label' => 'DHL', 'sub' => __( 'MyDHL+', 'wp-easycart' ), 'url' => 'https://mydhl.express.dhl/', 'configured' => ( ! empty( get_option( 'ec_option_dhl_account_number' ) ) ), 'keywords' => 'dhl|express worldwide|express easy' ),
		'canadapost' => array( 'label' => __( 'Canada Post', 'wp-easycart' ), 'sub' => __( 'Ship Online', 'wp-easycart' ), 'url' => 'https://www.canadapost-postescanada.ca/cpc/en/business/shipping.page', 'configured' => false, 'keywords' => 'canada post|xpresspost|expedited parcel|regular parcel' ),
		'auspost' => array( 'label' => __( 'AusPost', 'wp-easycart' ), 'sub' => __( 'MyPost', 'wp-easycart' ), 'url' => 'https://auspost.com.au/mypost-business', 'configured' => false, 'keywords' => 'australia post|auspost|parcel post|express post|satchel' ),
	);
	$wpec_carrier_defs = apply_filters( 'wp_easycart_ecv2_label_carriers', $wpec_carrier_defs, $this->order );
	$wpec_guess_text = strtolower( trim( (string) $this->order->shipping_carrier . ' ' . (string) $this->order->shipping_method ) );
	$wpec_suggested = '';
	foreach ( $wpec_carrier_defs as $wpec_ck => $wpec_cd ) {
		foreach ( explode( '|', $wpec_cd['keywords'] ) as $wpec_kw ) {
			if ( '' !== $wpec_kw && false !== strpos( $wpec_guess_text, $wpec_kw ) ) {
				$wpec_suggested = $wpec_ck;
				break 2;
			}
		}
	}
	$wpec_shippo_on = ( function_exists( 'wp_easycart_shippo' ) && '' !== (string) wp_easycart_shippo()->get_setting( 'api_key' ) );
	$wpec_shipstation_on = apply_filters( 'wp_easycart_ecv2_shipstation_active', class_exists( 'wp_easycart_shipstation' ) );
	$wpec_stamps_on = apply_filters( 'wp_easycart_ecv2_stamps_active', class_exists( 'wp_easycart_stamps' ) );
	$wpec_shipping_name = trim( (string) $this->order->shipping_first_name . ' ' . (string) $this->order->shipping_last_name );
	$wpec_ship_to = trim( $wpec_shipping_name . ' · ' . $this->order->shipping_address_line_1 . ( '' !== (string) $this->order->shipping_address_line_2 ? ' ' . $this->order->shipping_address_line_2 : '' ) . ', ' . $this->order->shipping_city . ' ' . $this->order->shipping_state . ' ' . $this->order->shipping_zip . ', ' . $this->order->shipping_country_name . ( '' !== (string) $this->order->shipping_phone ? ' · ' . $this->order->shipping_phone : '' ) );
	$wpec_package = trim( $this->order->order_weight . ' — ' . sprintf( _n( '%d item', '%d items', $item_count, 'wp-easycart' ), $item_count ) );
	?>
	<?php /* ============ Edit Line Item modal ( V3.3 ) ============
	        The PRO-printed hidden edit containers for the chosen line are
	        relocated into the host at open; save drives the existing
	        ec_order_edit_line_item toggle, so orders-pro.js is unchanged. */ ?>
	<div class="ecodv2-line-backdrop" id="ecodv2_line_backdrop" onclick="ecodv2_line_modal_cancel(); return false;"></div>
	<div class="ecodv2-line-modal" id="ecodv2_line_modal" role="dialog" aria-label="<?php esc_attr_e( 'Edit line item', 'wp-easycart' ); ?>">
		<div class="ecodv2-line-modal-head">
			<h3><?php esc_attr_e( 'Edit Line Item', 'wp-easycart' ); ?></h3>
			<span class="ecodv2-line-modal-chips" id="ecodv2_line_modal_chips"></span>
			<button type="button" class="ecodv2-drawer-x" onclick="ecodv2_line_modal_cancel(); return false;"><span class="dashicons dashicons-no-alt"></span></button>
		</div>
		<div class="ecodv2-line-modal-body" id="ecodv2_line_modal_body"></div>
		<div class="ecodv2-line-modal-foot">
			<button type="button" class="ecodv2-line-remove" id="ecodv2_line_modal_remove"><span class="dashicons dashicons-trash"></span> <?php esc_attr_e( 'Remove line', 'wp-easycart' ); ?></button>
			<div class="ecodv2-drawer-foot-spacer"></div>
			<button type="button" class="ecv2-btn" onclick="ecodv2_line_modal_cancel(); return false;"><?php esc_attr_e( 'Cancel', 'wp-easycart' ); ?></button>
			<button type="button" class="ecv2-btn ecv2-btn-primary" id="ecodv2_line_modal_save"><?php esc_attr_e( 'Save Line', 'wp-easycart' ); ?></button>
		</div>
	</div>

	<div class="ecodv2-label-backdrop" id="ecodv2_label_backdrop" onclick="ecodv2_label_popup_close(); return false;"></div>
	<div class="ecodv2-label-popup" id="ecodv2_label_popup" role="dialog" aria-label="<?php esc_attr_e( 'Create shipping label', 'wp-easycart' ); ?>">
		<div class="ecodv2-label-head">
			<h3><?php esc_attr_e( 'Create shipping label', 'wp-easycart' ); ?></h3>
			<span class="ecodv2-label-ref"><?php esc_attr_e( 'Order', 'wp-easycart' ); ?> #<?php echo esc_attr( $this->order->order_id ); ?><?php if ( '' !== (string) $this->order->order_weight && '0.000' !== (string) $this->order->order_weight ) { ?> &middot; <?php echo esc_attr( $this->order->order_weight ); ?><?php } ?></span>
			<button type="button" class="ecodv2-drawer-x" onclick="ecodv2_label_popup_close(); return false;"><span class="dashicons dashicons-no-alt"></span></button>
		</div>
		<div class="ecodv2-label-body">
			<span class="ecodv2-eyebrow"><?php esc_attr_e( 'Connected services', 'wp-easycart' ); ?></span>
			<div class="ecodv2-label-services">
				<?php if ( $wpec_shippo_on ) { ?>
				<a class="ecodv2-svc ecodv2-svc-integrated" href="https://apps.goshippo.com/orders" target="_blank">
					<span class="ecodv2-svc-logo" style="background:#0aa143;">Sh</span>
					<span><span class="ecodv2-svc-name">Shippo</span><br><span class="ecodv2-svc-sub"><?php esc_attr_e( 'This order is already in your Shippo dashboard — all carriers', 'wp-easycart' ); ?></span></span>
					<span class="ecodv2-svc-cta"><?php esc_attr_e( 'Integrated', 'wp-easycart' ); ?> &#10003; &nbsp;<?php esc_attr_e( 'Create label', 'wp-easycart' ); ?> &#8599;</span>
				</a>
				<?php } ?>
				<?php if ( $wpec_stamps_on ) { ?>
				<a class="ecodv2-svc ecodv2-svc-integrated" href="#" onclick="return false;">
					<span class="ecodv2-svc-logo" style="background:#0b6e3f;">St</span>
					<span><span class="ecodv2-svc-name">Stamps.com</span><br><span class="ecodv2-svc-sub"><?php esc_attr_e( 'USPS labels — prints here, tracking added automatically', 'wp-easycart' ); ?></span></span>
					<span class="ecodv2-svc-cta"><?php esc_attr_e( 'Integrated', 'wp-easycart' ); ?> &#10003; &nbsp;<?php esc_attr_e( 'Create label', 'wp-easycart' ); ?></span>
				</a>
				<?php } ?>
				<?php if ( $wpec_shipstation_on ) { ?>
				<a class="ecodv2-svc" href="https://ship.shipstation.com/orders" target="_blank">
					<span class="ecodv2-svc-logo" style="background:#12a5c6;">SS</span>
					<span><span class="ecodv2-svc-name">ShipStation</span><br><span class="ecodv2-svc-sub"><?php esc_attr_e( 'All carriers — opens ShipStation in a new tab', 'wp-easycart' ); ?></span></span>
					<span class="ecodv2-svc-cta"><?php esc_attr_e( 'Open', 'wp-easycart' ); ?> &#8599;</span>
				</a>
				<?php } ?>
				<?php if ( ! $wpec_shippo_on && ! $wpec_stamps_on && ! $wpec_shipstation_on ) { ?>
				<a class="ecodv2-svc ecodv2-svc-install" href="<?php echo esc_url( apply_filters( 'wp_easycart_ecv2_extensions_url', 'https://www.wpeasycart.com/wordpress-shopping-cart-extensions/' ) ); ?>" target="_blank">
					<span class="ecodv2-svc-logo" style="background:#9ca3af;">&#65291;</span>
					<span><span class="ecodv2-svc-name"><?php esc_attr_e( 'Shippo, Stamps.com or ShipStation', 'wp-easycart' ); ?></span><br><span class="ecodv2-svc-sub"><?php esc_attr_e( 'Print labels without leaving EasyCart, or manage all carriers in one place', 'wp-easycart' ); ?></span></span>
					<span class="ecodv2-svc-cta"><?php esc_attr_e( 'View extensions', 'wp-easycart' ); ?> &#8599;</span>
				</a>
				<?php } ?>
			</div>

			<span class="ecodv2-eyebrow"><?php esc_attr_e( 'Or create at the carrier', 'wp-easycart' ); ?></span>
			<div class="ecodv2-label-carriers">
				<?php foreach ( $wpec_carrier_defs as $wpec_ck => $wpec_cd ) { ?>
				<a class="ecodv2-label-carrier<?php if ( $wpec_ck === $wpec_suggested ) { ?> is-suggested<?php } ?>" href="<?php echo esc_url( $wpec_cd['url'] ); ?>" target="_blank" data-carrier="<?php echo esc_attr( $wpec_cd['label'] ); ?>">
					<?php if ( $wpec_ck === $wpec_suggested ) { ?><span class="ecodv2-label-sug"><?php esc_attr_e( 'SUGGESTED', 'wp-easycart' ); ?></span><?php } ?>
					<span class="ecodv2-label-carrier-name"><?php echo esc_html( $wpec_cd['label'] ); ?></span>
					<span class="ecodv2-label-carrier-sub"><?php echo esc_html( $wpec_cd['sub'] ); ?> &#8599;</span>
				</a>
				<?php } ?>
			</div>

			<div class="ecodv2-label-helper">
				<span class="ecodv2-label-line"><b><?php esc_attr_e( 'Ship to', 'wp-easycart' ); ?></b><?php echo esc_html( $wpec_ship_to ); ?></span>
				<button type="button" class="ecodv2-copy-link" onclick="ecodv2_copy_text( this.parentNode.querySelector( 'span' ).childNodes[1].textContent, this ); return false;"><span class="dashicons dashicons-admin-page"></span> <?php esc_attr_e( 'Copy', 'wp-easycart' ); ?></button>
				<span class="ecodv2-label-line"><b><?php esc_attr_e( 'Package', 'wp-easycart' ); ?></b><?php echo esc_html( $wpec_package ); ?></span>
				<button type="button" class="ecodv2-copy-link" onclick="ecodv2_copy_text( '<?php echo esc_attr( $this->order->order_weight ); ?>', this ); return false;"><span class="dashicons dashicons-admin-page"></span> <?php esc_attr_e( 'Copy', 'wp-easycart' ); ?></button>
			</div>

			<div class="ecodv2-label-after">
				<span class="ecodv2-eyebrow"><?php esc_attr_e( 'When you have the label', 'wp-easycart' ); ?></span>
				<div class="ecodv2-label-after-row">
					<select id="ecodv2_label_carrier_sel">
						<?php foreach ( $wpec_carrier_defs as $wpec_ck => $wpec_cd ) { ?>
						<option value="<?php echo esc_attr( $wpec_cd['label'] ); ?>"<?php if ( $wpec_ck === $wpec_suggested ) { ?> selected="selected"<?php } ?>><?php echo esc_html( $wpec_cd['label'] ); ?></option>
						<?php } ?>
						<option value=""><?php esc_attr_e( 'Other', 'wp-easycart' ); ?></option>
					</select>
					<input type="text" id="ecodv2_label_tracking" placeholder="<?php esc_attr_e( 'Paste tracking number…', 'wp-easycart' ); ?>" />
					<button type="button" class="ecv2-btn ecv2-btn-primary" onclick="ecodv2_label_save_tracking(); return false;"><?php esc_attr_e( 'Save & fulfill', 'wp-easycart' ); ?></button>
				</div>
				<label class="ecodv2-label-email-opt"><input type="checkbox" id="ecodv2_label_send_email" /> <?php esc_attr_e( 'Also send the shipped email to the customer', 'wp-easycart' ); ?></label>
				<div class="ecodv2-label-saved" id="ecodv2_label_saved">&#10003; <?php esc_attr_e( 'Tracking saved — order marked fulfilled.', 'wp-easycart' ); ?></div>
			</div>
		</div>
	</div>

	<?php /* Full activity history drawer ( V4.2 ) — populated by JS from the inline timeline. */ ?>
	<div class="ecodv2-hdrawer-backdrop" id="ecodv2_history_backdrop" onclick="ecodv2_close_history_drawer(); return false;"></div>
	<div class="ecodv2-hdrawer" id="ecodv2_history_drawer" role="dialog" aria-label="<?php esc_attr_e( 'Order activity', 'wp-easycart' ); ?>">
		<div class="ecodv2-hdrawer-head">
			<h3><?php esc_attr_e( 'Activity', 'wp-easycart' ); ?></h3>
			<span class="ecodv2-hdrawer-count" id="ecodv2_history_drawer_count"></span>
			<button type="button" class="ecodv2-hdrawer-x" onclick="ecodv2_close_history_drawer(); return false;" aria-label="<?php esc_attr_e( 'Close', 'wp-easycart' ); ?>"><span class="dashicons dashicons-no-alt"></span></button>
		</div>
		<div class="ecodv2-hdrawer-body ecodv2-history-wrap" id="ecodv2_history_drawer_body"></div>
	</div>

	<div class="ecodv2-drawer-backdrop" id="ecodv2_edit_backdrop" onclick="ecodv2_close_edit_drawer(); return false;"></div>
	<div class="ecodv2-drawer" id="ecodv2_edit_drawer" role="dialog" aria-label="<?php esc_attr_e( 'Edit order', 'wp-easycart' ); ?>">
		<div class="ecodv2-drawer-head">
			<h3><?php echo esc_html( sprintf( __( 'Edit Order #%d', 'wp-easycart' ), (int) $this->order->order_id ) ); ?></h3>
			<span class="ecodv2-drawer-dirty" id="ecodv2_drawer_dirty">&bull; <?php esc_attr_e( 'Unsaved changes', 'wp-easycart' ); ?></span>
			<button type="button" class="ecodv2-drawer-x" onclick="ecodv2_close_edit_drawer(); return false;" aria-label="<?php esc_attr_e( 'Close', 'wp-easycart' ); ?>"><span class="dashicons dashicons-no-alt"></span></button>
		</div>
		<div class="ecodv2-drawer-jump" id="ecodv2_drawer_jump">
			<a href="#" data-section="customer" onclick="ecodv2_drawer_jump( 'customer' ); return false;"><?php esc_attr_e( 'Customer', 'wp-easycart' ); ?></a>
			<a href="#" data-section="billing" onclick="ecodv2_drawer_jump( 'billing' ); return false;"><?php esc_attr_e( 'Billing address', 'wp-easycart' ); ?></a>
			<a href="#" data-section="shipping" onclick="ecodv2_drawer_jump( 'shipping' ); return false;"><?php esc_attr_e( 'Shipping address', 'wp-easycart' ); ?></a>
			<a href="#" data-section="fulfillment" onclick="ecodv2_drawer_jump( 'fulfillment' ); return false;"><?php esc_attr_e( 'Fulfillment', 'wp-easycart' ); ?></a>
			<a href="#" data-section="details" onclick="ecodv2_drawer_jump( 'details' ); return false;"><?php esc_attr_e( 'Details', 'wp-easycart' ); ?></a>
		</div>
		<div class="ecodv2-drawer-body" id="ecodv2_drawer_body">
			<div class="ecodv2-drawer-sec" id="ecodv2_sec_customer" data-section="customer">
				<span class="ecodv2-eyebrow"><?php esc_attr_e( 'Customer', 'wp-easycart' ); ?></span>
				<div class="ecodv2-field ecodv2-field-wide ecodv2-drawer-account">
					<label for="ec_order_user_id"><?php esc_attr_e( 'User account', 'wp-easycart' ); ?></label>
					<select id="ec_order_user_id" class="select2" style="width:100% !important;"><?php if ( $this->order->user_id ) { ?>
						<option value="<?php echo esc_attr( $this->order->user_id ); ?>" selected="selected"><?php echo esc_attr( $this->order->last_name ); ?>, <?php echo esc_attr( $this->order->first_name ); ?> (<?php echo esc_attr( $this->order->user_id ); ?>)</option>
					<?php } else { ?>
						<option value="0" selected="selected"><?php esc_attr_e( 'Guest', 'wp-easycart' ); ?></option>
					<?php } ?></select>
					<?php /* 6.0.0: in-drawer busy / success / error line for the account change ( ecodv2_order_user_status ). */ ?>
					<div class="ecodv2-account-status" id="ecodv2_account_status" role="status" aria-live="polite" hidden data-busy-text="<?php esc_attr_e( 'Updating the customer account…', 'wp-easycart' ); ?>" data-error-text="<?php esc_attr_e( 'The customer account could not be updated. Please try again.', 'wp-easycart' ); ?>"></div>
				</div>
				<div class="ecodv2-sec-host" data-form-id="ec_admin_edit_order_information"></div>
			</div>
			<div class="ecodv2-drawer-sec" id="ecodv2_sec_billing" data-section="billing">
				<span class="ecodv2-eyebrow"><?php esc_attr_e( 'Billing Address', 'wp-easycart' ); ?></span>
				<div class="ecodv2-sec-host" data-form-id="ec_admin_order_details_billing_form"></div>
			</div>
			<div class="ecodv2-drawer-sec" id="ecodv2_sec_shipping" data-section="shipping">
				<span class="ecodv2-eyebrow"><?php esc_attr_e( 'Shipping Address', 'wp-easycart' ); ?></span>
				<div class="ecodv2-sec-tools">
					<button type="button" class="ecodv2-tool-btn" onclick="ecodv2_copy_from_billing(); return false;"><span class="dashicons dashicons-admin-page"></span> <?php esc_attr_e( 'Copy from billing', 'wp-easycart' ); ?></button>
					<span class="ecodv2-copied-ok" id="ecodv2_copied_ok"><?php esc_attr_e( 'Copied', 'wp-easycart' ); ?> &#10003;</span>
				</div>
				<div class="ecodv2-sec-host" data-form-id="ec_admin_order_details_shipping_form"></div>
			</div>
			<div class="ecodv2-drawer-sec" id="ecodv2_sec_fulfillment" data-section="fulfillment">
				<span class="ecodv2-eyebrow"><?php esc_attr_e( 'Fulfillment', 'wp-easycart' ); ?></span>
<div id="ec_admin_order_details_shipping_method_form" class="ecodv2-form ec_admin_initial_hide">
				<div class="ecv2-btn ecodv2-visually-hidden" id="ec_admin_order_details_shipping_method_save"></div>
				<div class="ecodv2-grid">
				<div class="ecodv2-field">
					<label><?php esc_attr_e( 'Speed', 'wp-easycart' ); ?></label>
					<select id="use_expedited_shipping" name="use_expedited_shipping">
						<option value="0"<?php if ( wp_easycart_admin_orders()->order_details->order->use_expedited_shipping == '0' ) { ?> selected="selected"<?php } ?>><?php esc_attr_e( 'Standard Shipping', 'wp-easycart' ); ?></option>
						<option value="1"<?php if ( wp_easycart_admin_orders()->order_details->order->use_expedited_shipping == '1' ) { ?> selected="selected"<?php } ?>><?php esc_attr_e( 'Expedite Shipping', 'wp-easycart' ); ?></option>
					</select>
				</div>
				<div class="ecodv2-field">
					<label><?php esc_attr_e( 'Shipping Method', 'wp-easycart' ); ?></label>
					<input type="text" placeholder="<?php esc_attr_e( 'Shipping Method', 'wp-easycart' ); ?>" id="shipping_method" name="shipping_method" value="<?php echo esc_attr( wp_easycart_admin_orders()->order_details->order->shipping_method ); ?>" />
				</div>
				<div class="ecodv2-field">
					<label><?php esc_attr_e( 'Carrier', 'wp-easycart' ); ?></label>
					<input type="text" placeholder="<?php esc_attr_e( 'Shipping Carrier', 'wp-easycart' ); ?>" id="shipping_carrier" name="shipping_carrier" value="<?php echo esc_attr( wp_easycart_admin_orders()->order_details->order->shipping_carrier ); ?>" />
				</div>
				<div class="ecodv2-field">
					<label><?php esc_attr_e( 'Tracking Number', 'wp-easycart' ); ?></label>
					<input type="text" placeholder="<?php esc_attr_e( 'Tracking Number', 'wp-easycart' ); ?>" id="tracking_number" name="tracking_number" value="<?php echo esc_attr( wp_easycart_admin_orders()->order_details->order->tracking_number ); ?>" />
				</div>
				</div>
			</div>
				<div class="ecodv2-drawer-shipment">
					<span class="ecodv2-eyebrow"><?php esc_attr_e( 'Shipment', 'wp-easycart' ); ?></span>
					<div class="ecodv2-grid ecodv2-drawer-shipment-fields">
						<?php do_action( 'wp_easycart_admin_orders_details_shipment' ); ?>
					</div>
				</div>
			</div>
			<div class="ecodv2-drawer-sec" id="ecodv2_sec_details" data-section="details">
				<span class="ecodv2-eyebrow"><?php esc_attr_e( 'Additional Details', 'wp-easycart' ); ?></span>
				<div class="ecodv2-sec-host" data-form-id="ec_admin_edit_order_information_bottom"></div>
			</div>
		</div>
		<div class="ecodv2-drawer-foot">
			<span class="ecodv2-drawer-hint"><?php esc_attr_e( 'Esc closes · changed sections save together', 'wp-easycart' ); ?></span>
			<div class="ecodv2-drawer-foot-spacer"></div>
			<button type="button" class="ecv2-btn" onclick="ecodv2_close_edit_drawer(); return false;"><?php esc_attr_e( 'Cancel', 'wp-easycart' ); ?></button>
			<button type="button" class="ecv2-btn ecv2-btn-primary" id="ecodv2_drawer_save" disabled onclick="ecodv2_drawer_save(); return false;"><?php esc_attr_e( 'Save Changes', 'wp-easycart' ); ?></button>
		</div>
	</div>

	<?php /* PRO v2 modals (custom email, tag manager, refund calculator). */ ?>
	<?php do_action( 'wp_easycart_ecv2_order_details_modals', $this->order ); ?>
</div>