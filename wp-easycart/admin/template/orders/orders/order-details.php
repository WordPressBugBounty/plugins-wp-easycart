<?php
/**
 * Order Details Template ( 6.0.2 redesign ).
 *
 * Rendered by wp_easycart_admin_details_orders::output(). The page is laid out for the fastest path through an order
 * ( review: https://claude.ai/artifact/9FU888SGrpJoFceHXNspwX, layout B: https://claude.ai/artifact/84saKxSSu3YqMhKLDSMnj5 ):
 *  - Header: Orders / Order #, Find ( Ctrl K: any order, or anything this page does ), Print ( P ), Email ( E ), ⋯ and
 *    previous / next ( J / K ).
 *  - Steps: Placed, Paid, Pack & ship, Shipped, Delivered ( wp_easycart_admin_order_screen::steps() ), with the status
 *    beside them; then a payment on hold or a possible card test.
 *  - Next step panel ( next_panel_html() ): Ship order right there ( carrier, tracking, email the customer ), or the next
 *    step's button ( Review payment, or what WP EasyCart PRO adds through wp_easycart_ecv2_order_next_steps ), and the
 *    shipping queue with Next order to ship ( N ). Both are redrawn after a status change ( status_reply() ).
 *  - Main column: the Gift order card ( wp_easycart_ecv2_order_details_main_top ); Items ( Items to pack, with a Packed
 *    tick on each line, while the store still has items to send ), the shipment and its packages; Payment ( charged,
 *    refunded and net received, or what is due, then the totals in reading order ); Activity.
 *  - Sidebar: Customer ( contact, account, subscribed, stats ), Notes ( pinned for the team, from the customer ), Ship to
 *    ( and Bill to when it differs ), Documents, the extension cards, checkout answers, source and details.
 *
 * Every element id, do_action and apply_filters the scripts and WP EasyCart PRO use is kept. Where one moved, it moved with
 * its hook ( the payment panel sits at the top of the Payment card, shipping_address_pre runs after Ship to ).
 *
 * @since 6.0.0
 * @since 6.0.2 Redesigned.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$prev_order_id = $wpdb->get_var( $wpdb->prepare( 'SELECT order_id FROM ec_order WHERE order_id = (SELECT MIN(order_id) FROM ec_order WHERE order_id > %d)', $this->order->order_id ) );
$next_order_id = $wpdb->get_var( $wpdb->prepare( 'SELECT order_id FROM ec_order WHERE order_id = (SELECT MAX(order_id) FROM ec_order WHERE order_id < %d)', $this->order->order_id ) );

$order_status_list = $wpdb->get_results( 'SELECT ec_orderstatus.* FROM ec_orderstatus ORDER BY status_id' ); /* includes color_code + is_archieved [sic] */
$order_viewed      = $wpdb->query( $wpdb->prepare( 'UPDATE ec_order SET ec_order.order_viewed = 1 WHERE ec_order.order_id = %s', $this->order->order_id ) );

$wpec_status_id  = (int) $this->order->orderstatus_id;
$wpec_pay_sum    = class_exists( 'wp_easycart_order_payments' ) ? wp_easycart_order_payments::summary( $this->order ) : null;
$payment_badge   = wp_easycart_admin_order_screen::payment_badge( $this->order, $wpec_pay_sum );
$wpec_ful        = wp_easycart_admin_order_screen::fulfillment( $this->order );
$fulfillment_state = $wpec_ful['state'];
$wpec_local_pickup = $wpec_ful['local_pickup'];
$wpec_store_open   = $wpec_ful['store_open'];
$wpec_partner_msg  = $wpec_ful['partner'];
$wpec_fstate       = $wpec_ful['fstate'];
$wpec_tracking     = trim( (string) $this->order->tracking_number );
$wpec_msg_fulfilled   = $wpec_local_pickup ? __( 'The customer has picked this order up.', 'wp-easycart' ) : __( 'This order has been fulfilled.', 'wp-easycart' );
$wpec_msg_unfulfilled = $wpec_local_pickup ? ( 'local' === $wpec_ful['pickup_kind'] ? __( 'Free local pickup. This order is waiting for the customer to collect it.', 'wp-easycart' ) : __( 'This order is waiting for the customer to collect it.', 'wp-easycart' ) ) : __( 'This order is waiting to be shipped.', 'wp-easycart' );
$item_count            = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE( SUM( quantity ), 0 ) FROM ec_orderdetail WHERE order_id = %d', $this->order->order_id ) );
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
$wpec_flags = wp_easycart_admin_order_screen::flags( $this->order );
$wpec_next  = wp_easycart_admin_order_screen::next_step(
	$this->order,
	array(
		'fulfillment'  => $fulfillment_state,
		'payment'      => ( $wpec_pay_sum && ! empty( $wpec_pay_sum['recorded'] ) ) ? $wpec_pay_sum['state'] : '',
		'local_pickup' => $wpec_local_pickup,
		'pickup_kind'  => $wpec_ful['pickup_kind'],
		'store_open'   => $wpec_store_open,
		'flags'        => $wpec_flags,
	)
);
$order_details = $wpdb->get_results( $wpdb->prepare( 'SELECT ec_orderdetail.*, ec_order.subscription_id FROM ec_orderdetail LEFT JOIN ec_order ON (ec_order.order_id = ec_orderdetail.order_id) WHERE ec_orderdetail.order_id = %s ORDER BY orderdetail_id', $this->order->order_id ) );
$wpec_line_states = ( in_array( $fulfillment_state, array( 'pickup' ), true ) || $wpec_local_pickup ) ? array() : wp_easycart_admin_order_screen::line_states( $this->order, $order_details );
$wpec_is_pro_edit_forms = wp_easycart_admin_order_screen::pro_prints_edit_forms();
/* What the steps and the next step panel read ( the same as a status change's answer works out ). */
$wpec_ctx = array(
	'summary'     => $wpec_pay_sum,
	'fulfillment' => $wpec_ful,
	'flags'       => $wpec_flags,
	'next'        => $wpec_next,
	'lines'       => $order_details,
	'line_states' => $wpec_line_states,
);
$wpec_steps    = wp_easycart_admin_order_screen::steps( $this->order, $wpec_ctx );
$wpec_carriers = wp_easycart_admin_order_screen::label_carriers( $this->order );
/* Packing: the store still has items to send, so each line to ship gets a Packed tick, kept on the order ( ec_orderdetail.packed_quantity )
   so everyone packing it sees the same; in the browser until the 6.0.2 database update has run. */
$wpec_packing = ! empty( $this->order->is_approved ) && ! $wpec_local_pickup && $wpec_store_open && in_array( $fulfillment_state, array( 'unfulfilled', 'partial' ), true ) && ! in_array( (int) $this->order->orderstatus_id, array( 16, 19 ), true );
$wpec_pack_lines = 0;
$wpec_pack_done  = 0;
$wpec_pack_order = wp_easycart_admin_order_screen::packing_ready();
if ( $wpec_packing ) {
	foreach ( $order_details as $wpec_pl ) {
		$wpec_pl_state = isset( $wpec_line_states[ (int) $wpec_pl->orderdetail_id ] ) ? $wpec_line_states[ (int) $wpec_pl->orderdetail_id ] : null;
		if ( $wpec_pl_state ? ( 'ship' === $wpec_pl_state['group'] && $wpec_pl_state['quantity'] > $wpec_pl_state['shipped'] ) : ( ! empty( $wpec_pl->is_shippable ) && empty( $wpec_pl->is_download ) && empty( $wpec_pl->is_giftcard ) ) ) {
			++$wpec_pack_lines;
			if ( $wpec_pack_order && wp_easycart_admin_order_screen::line_packed( $wpec_pl ) ) {
				++$wpec_pack_done;
			}
		}
	}
	$wpec_packing = $wpec_pack_lines > 0;
}

/* Wording and settings for the page script ( orders-details-v2.js reads #ecodv2_screen_data ). */
$wpec_screen_data = array(
	'order_id' => (int) $this->order->order_id,
	'money'    => wp_easycart_admin_order_screen::money_config(),
	'text'     => array(
		'saving'            => __( 'Saving…', 'wp-easycart' ),
		'saved'             => __( 'Saved', 'wp-easycart' ),
		'failed'            => __( 'This change could not be saved. Reload the page and try again.', 'wp-easycart' ),
		'copied'            => __( 'Copied', 'wp-easycart' ),
		'undo'              => __( 'Undo', 'wp-easycart' ),
		'status_title_16'   => __( 'Mark this order refunded?', 'wp-easycart' ),
		'status_body_16'    => __( 'This changes the order’s status only. No money goes back to the customer: use Refund to return the payment.', 'wp-easycart' ),
		'status_title_19'   => __( 'Cancel this order?', 'wp-easycart' ),
		'status_body_19'    => wp_easycart_order_returns::cancel_text(), /* 6.0.3: says what goes back ( stock, gift card balance ) */
		'status_title_17'   => __( 'Mark this order partly refunded?', 'wp-easycart' ),
		'status_body_17'    => __( 'This changes the order’s status only. No money goes back to the customer: use Refund to return part of the payment.', 'wp-easycart' ),
		'status_confirm'    => __( 'Change status', 'wp-easycart' ),
		'status_refund'     => __( 'Refund the payment instead', 'wp-easycart' ),
		'cancel'            => __( 'Cancel', 'wp-easycart' ),
		'status_failed'     => __( 'The status could not be changed. Reload the page and try again.', 'wp-easycart' ),
		'discard'           => __( 'Discard the changes you have not saved?', 'wp-easycart' ),
		'discard_yes'       => __( 'Discard', 'wp-easycart' ),
		'keep_editing'      => __( 'Keep editing', 'wp-easycart' ),
		'leave_unsaved'     => __( 'You have changes that are not saved.', 'wp-easycart' ),
		'drawer_saved'      => __( 'Order changes saved.', 'wp-easycart' ),
		'drawer_failed'     => __( 'Some changes could not be saved: %s. Check them and save again.', 'wp-easycart' ),
		'section_customer'  => __( 'customer', 'wp-easycart' ),
		'section_billing'   => __( 'billing address', 'wp-easycart' ),
		'section_shipping'  => __( 'shipping address', 'wp-easycart' ),
		'section_fulfill'   => __( 'shipping details', 'wp-easycart' ),
		'section_details'   => __( 'details', 'wp-easycart' ),
		'section_codes'     => __( 'codes', 'wp-easycart' ),
		'pin_saved'         => __( 'Note pinned to the order.', 'wp-easycart' ),
		'pin_removed'       => __( 'Note unpinned.', 'wp-easycart' ),
		'note_saved'        => __( 'Customer note saved.', 'wp-easycart' ),
		'note_failed'       => __( 'The customer note could not be saved.', 'wp-easycart' ),
		'line_saved'        => __( 'Line saved.', 'wp-easycart' ),
		'line_removed'      => __( 'Line removed.', 'wp-easycart' ),
		/* 6.0.2: removing a line that was already refunded lowers the total again. */
		'remove_refunded'   => __( 'This line was refunded. Removing it lowers the order total again, so the order will show a refund due for what was already refunded.', 'wp-easycart' ),
		'line_failed'       => __( 'The line could not be saved. Reload the page and try again.', 'wp-easycart' ),
		/* translators: 1: order total before, 2: order total now. */
		'totals_moved'      => __( 'Total %1$s → %2$s.', 'wp-easycart' ),
		'remove_line'       => __( 'Remove this line from the order?', 'wp-easycart' ),
		'remove'            => __( 'Remove', 'wp-easycart' ),
		'fulfill_need'      => __( 'Enter a tracking number, or tick Mark as shipped.', 'wp-easycart' ),
		'fulfill_failed'    => __( 'The shipment could not be saved. Reload the order and try again.', 'wp-easycart' ),
		'history_events'    => __( 'events', 'wp-easycart' ),
		'history_event'     => __( 'event', 'wp-easycart' ),
		/* translators: 1: position in the list, 2: how many orders the list has. */
		'nav_position'      => __( '%1$d of %2$d', 'wp-easycart' ),
		'agree_yes'         => __( 'Agreed to terms: Yes', 'wp-easycart' ),
		'agree_no'          => __( 'Agreed to terms: No', 'wp-easycart' ),
		'no_email'          => __( 'This order has no email address to send to.', 'wp-easycart' ),
		/* translators: %s: email address. */
		'confirm_shipped'   => __( 'Email the customer at %s that this order has shipped?', 'wp-easycart' ),
		'send'              => __( 'Send', 'wp-easycart' ),
		/* translators: %s: email address. */
		'confirm_giftcard'  => __( 'Send the gift card email to %s again?', 'wp-easycart' ),
		'confirm_giftcard0' => __( 'Send the gift card email again?', 'wp-easycart' ),
		'fulfill_items'     => __( 'Fulfill items', 'wp-easycart' ),
		'fulfill_remaining' => __( 'Fulfill remaining', 'wp-easycart' ),
		'fulfill_msg_none'    => __( 'Payment has not been approved yet.', 'wp-easycart' ),
		'fulfill_msg_digital' => __( 'Nothing to ship: every item is a download, gift card, subscription or a product with shipping turned off.', 'wp-easycart' ),
		'fulfill_msg_partial' => __( 'Partly shipped.', 'wp-easycart' ),
		'fulfill_msg_pickup'  => __( 'This order is a customer pickup.', 'wp-easycart' ),
		'nav_prev'          => __( 'Previous order ( K )', 'wp-easycart' ),
		'nav_next'          => __( 'Next order ( J )', 'wp-easycart' ),
		'pay_paid'          => __( 'Paid in full', 'wp-easycart' ),
		'pay_partial'       => __( 'Balance due', 'wp-easycart' ),
		'pay_unpaid'        => __( 'Not paid', 'wp-easycart' ),
		'pay_overpaid'      => __( 'Refund due', 'wp-easycart' ),
		/* The fast path ( 6.0.2 ): Ship order, packing, the queue, Find ( Ctrl K ) and the keys. */
		'pickup_title'      => __( 'Mark this order picked up?', 'wp-easycart' ),
		'pickup_body'       => ( 'local' === $wpec_ful['pickup_kind'] ) ? __( 'The customer chose local pickup. This sets the order to Order Picked Up. No carrier, tracking number or shipped email is involved.', 'wp-easycart' ) : __( 'The customer collects this order. This sets the order to Order Picked Up. No carrier, tracking number or shipped email is involved.', 'wp-easycart' ),
		'pickup_yes'        => __( 'Mark picked up', 'wp-easycart' ),
		'ship_no_tracking'  => __( 'Ship without a tracking number?', 'wp-easycart' ),
		'ship_no_tracking_body' => __( 'The order is marked shipped, and the shipped email goes out without tracking.', 'wp-easycart' ),
		'ship_anyway'       => __( 'Ship without tracking', 'wp-easycart' ),
		'ship_shipping'     => __( 'Shipping…', 'wp-easycart' ),
		/* 6.0.2 bug round 6: the page drawn again after the packages change, and Mark delivered. */
		'updating'              => __( 'Updating…', 'wp-easycart' ),
		'packages_changed'      => __( 'The packages changed, so the list was brought up to date. Check the tracking numbers and save again.', 'wp-easycart' ),
		'packages_changed_ship' => __( 'The packages changed, so this step was brought up to date. Check it and ship again.', 'wp-easycart' ),
		'delivered_failed'      => __( 'The order could not be marked delivered. Reload the page and try again.', 'wp-easycart' ),
		/* 6.0.2 bug round 14: the Stock not taken notice. */
		'stock_take_title'      => __( 'Take this order’s stock now?', 'wp-easycart' ),
		'stock_take_body'       => __( 'Each product’s stock goes down by what this order holds, as it would have when the order was paid. Choose Already corrected instead if you changed the stock by hand.', 'wp-easycart' ),
		'stock_take_yes'        => __( 'Take stock now', 'wp-easycart' ),
		'stock_failed'          => __( 'The stock could not be changed. Reload the page and try again.', 'wp-easycart' ),
		/* translators: 1: items packed, 2: items to pack. */
		'pack_progress'     => __( '%1$d of %2$d packed', 'wp-easycart' ),
		'pack_done'         => __( 'All packed', 'wp-easycart' ),
		'pack_failed'       => __( 'The packed item could not be saved. Reload the order and try again.', 'wp-easycart' ),
		'scan_title'        => __( 'Scan a tracking barcode', 'wp-easycart' ),
		'scan_hint'         => __( 'Hold the label’s barcode inside the frame.', 'wp-easycart' ),
		'scan_denied'       => __( 'The camera could not be opened. Allow the camera for this site, or type the tracking number.', 'wp-easycart' ),
		'close'             => __( 'Close', 'wp-easycart' ),
		'find_placeholder'  => __( 'Find an order, or type what to do…', 'wp-easycart' ),
		'find_label'        => __( 'Find an order or run an action', 'wp-easycart' ),
		'find_actions'      => __( 'This order', 'wp-easycart' ),
		'find_orders'       => __( 'Orders', 'wp-easycart' ),
		'find_searching'    => __( 'Searching…', 'wp-easycart' ),
		'find_none'         => __( 'Nothing matches.', 'wp-easycart' ),
		/* translators: %s: order number. */
		'find_open'         => __( 'Open order #%s', 'wp-easycart' ),
		'find_hint'         => __( '↑ ↓ to move · Enter to open · Esc to close', 'wp-easycart' ),
		'act_ship'          => __( 'Ship order', 'wp-easycart' ),
		/* 6.0.3: the phone Ship bar's buttons when a label service is the key one. */
		'act_label'         => __( 'Buy label', 'wp-easycart' ),
		'act_ship_short'    => __( 'Ship', 'wp-easycart' ),
		'act_fulfill'       => __( 'Fulfill: make a label and add tracking', 'wp-easycart' ),
		'act_slip'          => __( 'Print the packing slip', 'wp-easycart' ),
		'act_receipt'       => __( 'Print the receipt', 'wp-easycart' ),
		'act_email'         => __( 'Email the customer', 'wp-easycart' ),
		'act_refund'        => __( 'Refund', 'wp-easycart' ),
		'act_totals'        => __( 'Edit totals', 'wp-easycart' ),
		'act_edit'          => __( 'Edit the customer and addresses', 'wp-easycart' ),
		'act_pin'           => __( 'Pin a note for the team', 'wp-easycart' ),
		'act_cnote'         => __( 'Write a note for the customer', 'wp-easycart' ),
		'act_copy_address'  => __( 'Copy the shipping address', 'wp-easycart' ),
		'act_activity'      => __( 'Show all activity', 'wp-easycart' ),
		/* translators: %s: order status name. */
		'act_status'        => __( 'Set status: %s', 'wp-easycart' ),
		'act_next_ship'     => __( 'Next order to ship', 'wp-easycart' ),
		'act_prev'          => __( 'Previous order', 'wp-easycart' ),
		'act_next'          => __( 'Next order', 'wp-easycart' ),
		'act_keys'          => __( 'Keyboard shortcuts', 'wp-easycart' ),
		'keys_title'        => __( 'Keyboard shortcuts', 'wp-easycart' ),
		'key_find'          => __( 'Find an order or run an action', 'wp-easycart' ),
		'key_enter'         => __( 'Enter', 'wp-easycart' ),
		'key_ship'          => __( 'Ship order ( in the tracking number )', 'wp-easycart' ),
		'key_print'         => __( 'Print', 'wp-easycart' ),
		'key_email'         => __( 'Email the customer', 'wp-easycart' ),
		'key_refund'        => __( 'Refund', 'wp-easycart' ),
		'key_next_ship'     => __( 'Next order to ship', 'wp-easycart' ),
		'key_prev_next'     => __( 'Previous / next order', 'wp-easycart' ),
		'key_help'          => __( 'These shortcuts', 'wp-easycart' ),
		/* translators: %s: email address. */
		'shipped_sent'      => __( 'Shipped email sent to %s', 'wp-easycart' ),
		'shipped_sent_bare' => __( 'Shipped email sent', 'wp-easycart' ),
	),
);
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
<script type="application/json" id="ecodv2_screen_data"><?php echo wp_json_encode( $wpec_screen_data, JSON_HEX_TAG | JSON_HEX_AMP ); ?></script>
<?php /* The legacy savers fade these in and out: a thin bar at the top of the window while a save is in flight. */ ?>
<div class="ecodv2-busy-bar" id="ec_admin_order_management" style="display:none;" aria-hidden="true"></div>
<div class="ecodv2-busy-bar" id="ec_admin_shipping_details" style="display:none;" aria-hidden="true"></div>

<div class="ecdv2-wrap ecodv2-wrap ecodv2-v3 ecodv2-b<?php echo $wpec_packing ? ' is-packing' : ''; ?>" id="ecodv2_wrap" data-fulfillment="<?php echo esc_attr( $fulfillment_state ); ?>" data-pack-store="<?php echo esc_attr( $wpec_pack_order ? 'order' : 'browser' ); ?>">

	<?php /* =============================== HEADER: where you are, Find ( Ctrl K ), Print, Email, previous / next =============================== */ ?>
	<div class="ecdv2-header ecodv2-header">
		<a href="<?php echo esc_attr( $this->action ); ?>" class="ecdv2-header-back ecodv2-crumb" title="<?php esc_attr_e( 'Back to orders', 'wp-easycart' ); ?>" aria-label="<?php esc_attr_e( 'Back to orders', 'wp-easycart' ); ?>"><span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span><span class="ecodv2-crumb-text"><?php esc_html_e( 'Orders', 'wp-easycart' ); ?></span></a>
		<span class="ecodv2-crumb-sep" aria-hidden="true">/</span>

		<div class="ecdv2-header-meta">
			<p class="ecdv2-header-title ecodv2-title-row">
				<span class="ec_admin_order_details_order_id"><?php esc_html_e( 'Order', 'wp-easycart' ); ?> #<?php echo esc_html( $this->order->order_id ); ?></span>
				<span id="wpeasycart-payment-status" class="<?php echo esc_attr( $payment_badge['class'] ); ?> ecodv2-payment-badge"><?php echo esc_html( $payment_badge['label'] ); ?></span>
				<?php
				$wpec_fb_map = array(
					'fulfilled'   => array( 'class' => 'ecodv2-fulfill-badge-ok', 'icon' => 'dashicons-yes-alt', 'label' => __( 'Fulfilled', 'wp-easycart' ) ),
					'pickup'      => array( 'class' => 'ecodv2-fulfill-badge-pickup', 'icon' => 'dashicons-store', 'label' => __( 'Pickup', 'wp-easycart' ) ),
					'unfulfilled' => array( 'class' => 'ecodv2-fulfill-badge-warn', 'icon' => 'dashicons-warning', 'label' => __( 'To ship', 'wp-easycart' ) ),
					'digital'     => array( 'class' => 'ecodv2-fulfill-badge-ok', 'icon' => 'dashicons-download', 'label' => __( 'No shipping', 'wp-easycart' ) ),
					'partial'     => array( 'class' => 'ecodv2-fulfill-badge-warn', 'icon' => 'dashicons-clock', 'label' => __( 'Partly shipped', 'wp-easycart' ) ),
				);
				if ( $wpec_local_pickup ) {
					$wpec_fb_map['fulfilled']['icon']    = 'dashicons-store';
					$wpec_fb_map['fulfilled']['label']   = __( 'Picked up', 'wp-easycart' );
					$wpec_fb_map['unfulfilled']['icon']  = 'dashicons-store';
					$wpec_fb_map['unfulfilled']['label'] = __( 'Awaiting pickup', 'wp-easycart' );
				}
				if ( isset( $wpec_fb_map[ $fulfillment_state ] ) ) {
					$wpec_fb = $wpec_fb_map[ $fulfillment_state ];
					?>
				<?php
				$wpec_fb_labels = '';
				foreach ( $wpec_fb_map as $wpec_fb_state => $wpec_fb_row ) {
					$wpec_fb_labels .= ' data-label-' . $wpec_fb_state . '="' . esc_attr( $wpec_fb_row['label'] ) . '"';
				}
				?>
				<span class="ecodv2-fulfill-badge <?php echo esc_attr( $wpec_fb['class'] ); ?>" id="ecodv2_fulfill_badge" data-state="<?php echo esc_attr( $fulfillment_state ); ?>"<?php echo $wpec_fb_labels; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built above with esc_attr(). ?>><span class="dashicons <?php echo esc_attr( $wpec_fb['icon'] ); ?>" aria-hidden="true"></span> <span id="ecodv2_fulfill_badge_label"><?php echo esc_html( $wpec_fb['label'] ); ?></span></span>
				<?php } else { ?>
					<?php
					$wpec_fb_labels = '';
					foreach ( $wpec_fb_map as $wpec_fb_state => $wpec_fb_row ) {
						$wpec_fb_labels .= ' data-label-' . $wpec_fb_state . '="' . esc_attr( $wpec_fb_row['label'] ) . '"';
					}
					?>
				<span class="ecodv2-fulfill-badge" id="ecodv2_fulfill_badge" data-state="none"<?php echo $wpec_fb_labels; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built above with esc_attr(). ?> style="display:none;"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> <span id="ecodv2_fulfill_badge_label"></span></span>
				<?php } ?>
				<?php /* WP EasyCart PRO: tags and insight chips; the Gift chip. display:contents; ec_admin_ajax_update_order_user refills it. */ ?>
				<span class="ecodv2-header-chips" id="ecodv2_header_chips"><?php do_action( 'wp_easycart_ecv2_order_details_header_chips', $this->order ); ?></span>
			</p>
			<div class="ecdv2-header-sub">
				<?php $edit_order_date_action = apply_filters( 'wp_easycart_admin_order_details_order_date_edit_action', 'show_pro_required' ); ?>
				<span class="ecodv2-date-view" id="ec_admin_order_details_order_date_row">
					<span id="ec_admin_order_details_order_date"><?php echo esc_html( date_i18n( 'M j Y ' . get_option( 'time_format' ), $this->order_timestamp ) ); ?></span>
				</span>
				<?php do_action( 'wp_easycart_order_details_order_date' ); ?>
				<?php if ( '' !== $wpec_customer_name ) { ?>
				<span class="ecodv2-sub-sep" aria-hidden="true">·</span><span class="ecodv2-meta-customer"><?php echo esc_html( $wpec_customer_name ); ?></span>
				<?php } ?>
				<span class="ecodv2-sub-sep" aria-hidden="true">·</span><span><?php echo esc_html( sprintf( _n( '%d item', '%d items', $item_count, 'wp-easycart' ), $item_count ) ); ?></span>
				<span class="ecodv2-sub-sep" aria-hidden="true">·</span><span class="ecodv2-meta-total"><?php echo esc_html( wp_easycart_admin_order_screen::money( $this->order->grand_total ) ); ?></span>
			</div>
		</div>

		<div class="ecdv2-header-spacer"></div>

		<div class="ecdv2-header-actions ecodv2-header-actions">

			<?php /* Find ( Ctrl K ): any order by number, name or email, and everything this page does, from the keyboard. */ ?>
			<button type="button" class="ecodv2-find" id="ecodv2_find_btn" onclick="ecodv2_cmdk_open(); return false;" aria-label="<?php esc_attr_e( 'Find an order or run an action', 'wp-easycart' ); ?>" title="<?php esc_attr_e( 'Find an order or run an action', 'wp-easycart' ); ?>" aria-haspopup="dialog" aria-keyshortcuts="Control+K Meta+K"><span class="dashicons dashicons-search" aria-hidden="true"></span><span class="ecodv2-find-text"><?php esc_html_e( 'Find an order or run an action', 'wp-easycart' ); ?></span><kbd class="ecodv2-kbd" data-ecodv2-mod>Ctrl K</kbd></button>

			<?php /* Print: every document, once. WP EasyCart PRO adds its PDFs through wp_easycart_ecv2_order_details_print_menu. */ ?>
			<div class="ecdv2-menu-wrap ecodv2-menu-wrap">
				<button type="button" class="ecv2-btn" id="ecodv2_print_btn" onclick="ecodv2_menu_toggle( this, 'ecodv2_print_menu' ); return false;" aria-keyshortcuts="P" aria-haspopup="true" aria-expanded="false" aria-controls="ecodv2_print_menu"><span class="dashicons dashicons-printer" aria-hidden="true"></span> <?php esc_html_e( 'Print', 'wp-easycart' ); ?> <kbd class="ecodv2-kbd">P</kbd></button>
				<div class="ecdv2-menu" id="ecodv2_print_menu">
					<a href="admin.php?page=wp-easycart-orders&subpage=orders&bulk=<?php echo esc_attr( $this->order->order_id ); ?>&ec_admin_form_action=print-packing-slip&wp_easycart_nonce=<?php echo esc_attr( wp_create_nonce( 'wp-easycart-bulk-orders' ) ); ?>" target="_blank" onclick="ecodv2_menu_close();"><span class="dashicons dashicons-clipboard" aria-hidden="true"></span><?php esc_html_e( 'Packing slip', 'wp-easycart' ); ?></a>
					<a href="admin.php?page=wp-easycart-orders&subpage=orders&bulk=<?php echo esc_attr( $this->order->order_id ); ?>&ec_admin_form_action=print-receipt&wp_easycart_nonce=<?php echo esc_attr( wp_create_nonce( 'wp-easycart-bulk-orders' ) ); ?>" target="_blank" onclick="ecodv2_menu_close();"><span class="dashicons dashicons-media-text" aria-hidden="true"></span><?php esc_html_e( 'Receipt', 'wp-easycart' ); ?></a>
					<?php
					/**
					 * More documents in the order screen's Print menu ( WP EasyCart PRO 6.0.2: the Invoice and Packing slip PDFs ).
					 *
					 * @since 6.0.2
					 * @param object $order Order row.
					 */
					ob_start();
					do_action( 'wp_easycart_ecv2_order_details_print_menu', $this->order );
					$wpec_print_more = trim( (string) ob_get_clean() );
					echo $wpec_print_more; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- other plugins' own menu markup.
					?>
				</div>
			</div>

			<?php
			/* One dialog for every order email. pro = WP EasyCart PRO fills its document sections, otherwise they show locked. */
			$wpec_send_strings = array(
				'order_id'          => (int) $this->order->order_id,
				'nonce'             => wp_create_nonce( 'wp-easycart-ecv2-order-email-' . (int) $this->order->order_id ),
				'pro'               => class_exists( 'wp_easycart_documents' ) && wp_easycart_documents::pro_enabled(),
				'pro_badge'         => class_exists( 'wp_easycart_admin_upsell' ) ? wp_easycart_admin_upsell::badge_for( 'documents' ) : ( class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::badge( 'pro' ) : 'Pro' ),
				'send_title'        => __( 'Send email', 'wp-easycart' ),
				'kind_label'        => __( 'Email', 'wp-easycart' ),
				'kind_receipt'      => __( 'Order receipt', 'wp-easycart' ),
				'kind_shipped'      => __( 'Order shipped', 'wp-easycart' ),
				'kind_packing_slip' => __( 'Packing slip', 'wp-easycart' ),
				'receipt_button'    => __( 'Send receipt', 'wp-easycart' ),
				'shipped_button'    => __( 'Send shipped email', 'wp-easycart' ),
				'more_title'        => __( 'Attachments, content and items', 'wp-easycart' ),
				'more_desc'         => __( 'Attach the packing slip as a PDF, leave prices off for this send, or list only the items in this box.', 'wp-easycart' ),
				'preview'           => __( 'Preview', 'wp-easycart' ),
				'preview_failed'    => __( 'The preview could not be loaded for this order.', 'wp-easycart' ),
				'to'                => __( 'To', 'wp-easycart' ),
				'cc'                => __( 'Cc', 'wp-easycart' ),
				'bcc'               => __( 'Bcc', 'wp-easycart' ),
				'optional'          => __( 'optional', 'wp-easycart' ),
				'hint'              => __( 'Separate several addresses with commas.', 'wp-easycart' ),
				'desc_receipt'      => __( 'The order confirmation with the receipt', 'wp-easycart' ),
				'desc_shipped'      => __( 'Tracking and what was shipped', 'wp-easycart' ),
				'desc_packing_slip' => __( 'The packing slip for this order', 'wp-easycart' ),
				'desc_invoice'      => __( 'The invoice, with a way to pay when something is due', 'wp-easycart' ),
				'desc_gift_receipt' => __( 'A receipt without prices, for the gift recipient', 'wp-easycart' ),
				'desc_message'      => __( 'A message you write, with documents attached if you like', 'wp-easycart' ),
				'choose_email'      => __( 'Choose the email to send', 'wp-easycart' ),
				'edit'              => __( 'Edit', 'wp-easycart' ),
				'add_cc'            => __( 'Cc', 'wp-easycart' ),
				'add_bcc'           => __( 'Bcc', 'wp-easycart' ),
				'no_address'        => __( 'Add an email address', 'wp-easycart' ),
				'cancel'            => __( 'Cancel', 'wp-easycart' ),
				'sending'           => __( 'Sending…', 'wp-easycart' ),
				'need_to'           => __( 'Enter at least one email address to send to.', 'wp-easycart' ),
				'failed'            => __( 'The email could not be sent.', 'wp-easycart' ),
				'close'             => __( 'Close', 'wp-easycart' ),
			);
			?>
			<script type="application/json" id="ecodv2_email_i18n"><?php echo wp_json_encode( $wpec_send_strings, JSON_HEX_TAG | JSON_HEX_AMP ); ?></script>
			<?php /* Email: one dialog for every order email; it opens on the shipped email once the order has tracking. */ ?>
			<button type="button" class="ecv2-btn" id="ecodv2_send_email_link" data-kind="<?php echo esc_attr( '' !== $wpec_tracking ? 'shipped' : 'receipt' ); ?>" data-email="<?php echo esc_attr( $this->order->user_email ); ?>" data-email-other="<?php echo esc_attr( isset( $this->order->email_other ) ? $this->order->email_other : '' ); ?>" onclick="ecodv2_send_dialog( this ); return false;" aria-keyshortcuts="E"><span class="dashicons dashicons-email" aria-hidden="true"></span> <?php esc_html_e( 'Email', 'wp-easycart' ); ?> <kbd class="ecodv2-kbd">E</kbd></button>
			<?php /* Kept hidden: orders.js reads the shipped email's address from #ecodv2_send_shipped_link. */ ?>
			<a href="#" id="ecodv2_send_shipped_link" hidden data-email="<?php echo esc_attr( $this->order->user_email ); ?>" data-email-other="<?php echo esc_attr( isset( $this->order->email_other ) ? $this->order->email_other : '' ); ?>" onclick="ecodv2_send_dialog( this, 'shipped' ); return false;"></a>

			<?php /* ⋯: the rest. */ ?>
			<div class="ecdv2-menu-wrap ecodv2-menu-wrap">
				<button type="button" class="ecv2-btn ecodv2-icon-only" onclick="ecodv2_menu_toggle( this, 'ecodv2_header_menu' ); return false;" aria-haspopup="true" aria-expanded="false" aria-controls="ecodv2_header_menu" aria-label="<?php esc_attr_e( 'More actions', 'wp-easycart' ); ?>" title="<?php esc_attr_e( 'More actions', 'wp-easycart' ); ?>"><span class="dashicons dashicons-ellipsis" aria-hidden="true"></span></button>
				<div class="ecdv2-menu" id="ecodv2_header_menu">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-easycart-orders&subpage=orders&ecv2_duplicate=' . (int) $this->order->order_id ) ); ?>" onclick="ecodv2_menu_close();"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span><?php esc_html_e( 'Duplicate order', 'wp-easycart' ); ?></a>
					<a href="#" onclick="ecodv2_menu_close(); ecodv2_open_date_edit( '<?php echo esc_attr( $edit_order_date_action ); ?>' ); return false;"><span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span><?php esc_html_e( 'Change order date', 'wp-easycart' ); ?><?php if ( 'show_pro_required' === $edit_order_date_action ) { ?> <span class="ecodv2-pro-pill"><?php echo esc_html( class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::badge( 'pro' ) : __( 'Pro/Premium', 'wp-easycart' ) ); ?></span><?php } ?></a>
					<?php
					/* The help system prints its own markup ( icon + hidden label ); re-emit it as a plain menu item. */
					ob_start();
					wp_easycart_admin()->helpsystem->print_vids_url( 'orders', 'order-management', 'details' );
					$wpec_vids_html    = trim( (string) ob_get_clean() );
					$wpec_vids_href    = '';
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
						echo '<a href="' . esc_url( '' !== $wpec_vids_href ? $wpec_vids_href : '#' ) . '"' . $wpec_vids_target . ' onclick="ecodv2_menu_close(); ' . esc_attr( rtrim( $wpec_vids_onclick, '; ' ) ) . ( '' !== $wpec_vids_onclick ? ';' : '' ) . ( '' === $wpec_vids_href || '#' === $wpec_vids_href ? ' return false;' : '' ) . '"><span class="dashicons dashicons-video-alt3" aria-hidden="true"></span>' . esc_html__( 'Watch help video', 'wp-easycart' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $wpec_vids_target is a fixed attribute string.
					}
					?>
					<?php do_action( 'wp_easycart_ecv2_order_details_header_menu', $this->order ); ?>
				</div>
			</div>

			<?php /* Previous / next: the list the merchant came from when the page script knows it ( orders-v2.js ), else by number. */ ?>
			<div class="ecodv2-nav" id="ecodv2_nav">
				<?php if ( $prev_order_id ) { ?>
				<a class="ecv2-btn ecv2-btn-sm ecodv2-icon-only" href="admin.php?page=wp-easycart-orders&subpage=orders&order_id=<?php echo esc_attr( $prev_order_id ); ?>&ec_admin_form_action=edit" title="<?php esc_attr_e( 'Previous order ( K )', 'wp-easycart' ); ?>" aria-label="<?php esc_attr_e( 'Previous order', 'wp-easycart' ); ?>" id="ecodv2_nav_prev"><span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span></a>
				<?php } else { ?>
				<span class="ecv2-btn ecv2-btn-sm ecodv2-icon-only ecodv2-nav-disabled" id="ecodv2_nav_prev_off"><span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span></span>
				<?php } ?>
				<span class="ecodv2-nav-pos" id="ecodv2_nav_pos" hidden></span>
				<?php if ( $next_order_id ) { ?>
				<a class="ecv2-btn ecv2-btn-sm ecodv2-icon-only" href="admin.php?page=wp-easycart-orders&subpage=orders&order_id=<?php echo esc_attr( $next_order_id ); ?>&ec_admin_form_action=edit" title="<?php esc_attr_e( 'Next order ( J )', 'wp-easycart' ); ?>" aria-label="<?php esc_attr_e( 'Next order', 'wp-easycart' ); ?>" id="ecodv2_nav_next"><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></a>
				<?php } else { ?>
				<span class="ecv2-btn ecv2-btn-sm ecodv2-icon-only ecodv2-nav-disabled" id="ecodv2_nav_next_off"><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></span>
				<?php } ?>
			</div>

		</div>
	</div>

	<?php /* =============================== WHERE THE ORDER STANDS, AND WHAT IS NEXT =============================== */ ?>
	<div class="ecodv2-top">
		<?php /* The steps: Placed, Paid, Pack & ship, Shipped, Delivered ( wp_easycart_admin_order_screen::steps() ). The status is the setting beside them. */ ?>
		<section class="ecdv2-card ecodv2-steps-card ecodv2-o-steps" aria-label="<?php esc_attr_e( 'Order progress', 'wp-easycart' ); ?>">
			<div class="ecodv2-steps-host" id="ecodv2_steps"><?php echo wp_easycart_admin_order_screen::steps_html( $wpec_steps ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- steps_html() escapes every value. ?></div>
			<div class="ecodv2-steps-status">
				<?php /* Status: the V1 select stays ( hidden, the state the scripts read ); the pill and menu drive it through a confirm. */ ?>
				<div class="ecodv2-status-wrap">
					<?php do_action( 'wp_easycart_admin_order_status_box_right' ); ?>
					<?php
					$wpec_current_status_color = ( $selected_order_status && '' !== (string) $selected_order_status->color_code ) ? $selected_order_status->color_code : '#e5e7eb';
					$wpec_current_status_label = ( $selected_order_status ) ? $selected_order_status->order_status : __( 'Processing', 'wp-easycart' );
					$wpec_current_is_archived  = ( $selected_order_status && ! empty( $selected_order_status->is_archieved ) );
					?>
					<button type="button" class="ecodv2-status-pill" id="ecodv2_status_pill" onclick="ecodv2_status_toggle(); return false;" aria-haspopup="listbox" aria-expanded="false" aria-controls="ecodv2_status_menu">
						<span class="ecodv2-status-eyebrow"><?php esc_html_e( 'Status', 'wp-easycart' ); ?></span>
						<span class="ecodv2-status-dot" id="ecodv2_status_pill_dot" style="background:<?php echo esc_attr( $wpec_current_status_color ); ?>;"></span>
						<span id="ecodv2_status_pill_label"><?php echo esc_html( $wpec_current_status_label ); ?><?php if ( $wpec_current_is_archived ) { ?> <em class="ecodv2-status-archived-note">(<?php esc_html_e( 'archived', 'wp-easycart' ); ?>)</em><?php } ?></span>
						<span class="dashicons dashicons-arrow-down-alt2 ecodv2-status-caret" aria-hidden="true"></span>
					</button>
					<div class="ecdv2-menu ecodv2-status-menu" id="ecodv2_status_menu" role="listbox" aria-label="<?php esc_attr_e( 'Order status', 'wp-easycart' ); ?>">
						<?php foreach ( $order_status_list as $order_status ) { ?>
							<?php
							$wpec_is_current = ( $this->order->orderstatus_id == $order_status->status_id );
							if ( ! empty( $order_status->is_archieved ) && ! $wpec_is_current ) {
								continue; /* archived statuses hide from the picker unless currently applied */
							}
							$wpec_dot = ( '' !== (string) $order_status->color_code ) ? $order_status->color_code : '#e5e7eb';
							?>
						<button type="button" class="ecodv2-status-item<?php echo $wpec_is_current ? ' is-current' : ''; ?><?php echo empty( $order_status->is_approved ) ? ' is-unapproved' : ''; ?>" data-status-id="<?php echo esc_attr( $order_status->status_id ); ?>" data-color="<?php echo esc_attr( $wpec_dot ); ?>" onclick="ecodv2_set_status( '<?php echo esc_attr( $order_status->status_id ); ?>', this ); return false;" role="option" aria-selected="<?php echo $wpec_is_current ? 'true' : 'false'; ?>">
							<span class="ecodv2-status-dot" style="background:<?php echo esc_attr( $wpec_dot ); ?>;"></span>
							<span class="ecodv2-status-item-label"><?php echo esc_html( $order_status->order_status ); ?></span>
							<span class="ecodv2-status-check dashicons dashicons-yes" aria-hidden="true"></span>
						</button>
						<?php } ?>
						<div class="ecdv2-menu-sep"></div>
						<button type="button" class="ecodv2-status-add" onclick="ecodv2_set_status( 'add-new', null ); return false;">&#65291; <?php esc_html_e( 'Add new status', 'wp-easycart' ); ?></button>
					</div>
					<select id="orderstatus_id" class="ecodv2-visually-hidden" name="orderstatus_id" tabindex="-1" aria-hidden="true" data-partial-refund="<?php echo esc_attr__( 'Partial Refund', 'wp-easycart' ); ?>" data-refunded="<?php echo esc_attr__( 'Refunded', 'wp-easycart' ); ?>" data-paid="<?php echo esc_attr__( 'Paid', 'wp-easycart' ); ?>" data-cancelled="<?php echo esc_attr__( 'Canceled', 'wp-easycart' ); ?>" data-failed="<?php echo esc_attr__( 'Failed', 'wp-easycart' ); ?>" data-pending="<?php echo esc_attr__( 'Processing', 'wp-easycart' ); ?>">
						<?php foreach ( $order_status_list as $order_status ) { ?>
						<option value="<?php echo esc_attr( $order_status->status_id ); ?>" isapproved="<?php echo esc_attr( $order_status->is_approved ); ?>"<?php selected( $this->order->orderstatus_id, $order_status->status_id ); ?>><?php echo esc_html( $order_status->order_status ); ?></option>
						<?php } ?>
						<option value="add-new"><?php esc_html_e( '+ Add New Status', 'wp-easycart' ); ?></option>
					</select>
				</div>
			</div>
		</section>

		<?php /* A payment on hold, a possible card test, stock never taken: read before anyone ships or refunds. Drawn again from a status change's answer ( flags_html ). */ ?>
		<div class="ecodv2-attention ecodv2-o-attention" id="ecodv2_attention"<?php if ( ! $wpec_flags ) { ?> hidden<?php } ?>>
			<?php echo wp_easycart_admin_order_screen::flags_html( $wpec_flags ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- flags_html() escapes every value. ?>
		</div>

		<?php /* The next step, with the means to do it ( Ship order right here ), and the shipping queue. Redrawn after a status change. */ ?>
		<?php echo wp_easycart_admin_order_screen::next_panel_html( $this->order, $wpec_ctx ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- next_panel_html() escapes every value; the hook output in it is WP EasyCart PRO's own markup. ?>
	</div>

	<?php /* =============================== BODY =============================== */ ?>
	<div class="ecdv2-body ecodv2-body">

		<?php /* ------------------------------ MAIN COLUMN ------------------------------ */ ?>
		<div class="ecodv2-main">

			<?php
			/**
			 * The top of the order screen's main column: what whoever packs and ships the order must see first ( the Gift order
			 * card ).
			 *
			 * @param object $order The order.
			 */
			do_action( 'wp_easycart_ecv2_order_details_main_top', $this->order );
			?>

			<?php /* ---------- Items & shipping ---------- */ ?>
			<div class="ecdv2-card ecodv2-card-items ecodv2-card-fulfillment ecodv2-o-items">
				<div class="ecdv2-card-header">
					<h3 class="ecdv2-card-title"><?php echo esc_html( $wpec_packing ? __( 'Items to pack', 'wp-easycart' ) : __( 'Items', 'wp-easycart' ) ); ?></h3>
					<?php if ( $wpec_packing ) { ?>
					<?php /* Packed ticks save on the order as they change ( ecv2_order_screen_pack; orders-details-v2.js, ecodv2_pack_save() ). */ ?>
					<span class="ecodv2-pack-progress" id="ecodv2_pack_progress" data-total="<?php echo esc_attr( (string) $wpec_pack_lines ); ?>"><span class="ecodv2-pack-count" id="ecodv2_pack_count"><?php echo esc_html( sprintf( /* translators: 1: items packed, 2: items to pack. */ __( '%1$d of %2$d packed', 'wp-easycart' ), $wpec_pack_done, $wpec_pack_lines ) ); ?></span><span class="ecodv2-pack-bar" aria-hidden="true"><span id="ecodv2_pack_bar"></span></span></span>
					<?php } else { ?>
					<span class="ecdv2-card-hint"><?php echo esc_html( sprintf( _n( '%d item', '%d items', $item_count, 'wp-easycart' ), $item_count ) ); ?></span>
					<?php } ?>
					<div class="ecdv2-card-header-actions ecodv2-item-actions">
						<?php $add_new_line_action = apply_filters( 'wp_easycart_admin_order_details_add_new_line_action', 'show_pro_required' ); ?>
						<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" onclick="ecodv2_open_add_line_modal( '<?php echo esc_attr( $add_new_line_action ); ?>' ); return false;"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> <?php esc_html_e( 'Add item', 'wp-easycart' ); ?><?php if ( 'show_pro_required' === $add_new_line_action ) { ?> <span class="ecodv2-pro-pill"><?php echo esc_html( class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::badge( 'pro' ) : __( 'Pro/Premium', 'wp-easycart' ) ); ?></span><?php } ?></button>
						<?php
						/* Buttons other plugins add to the items ( wp_easycart_admin_order_details_button_row_pre / _post ), in a More menu. */
						ob_start();
						do_action( 'wp_easycart_admin_order_details_button_row_pre', $this->order->order_id );
						do_action( 'wp_easycart_admin_order_details_button_row_post', $this->order->order_id );
						$ecodv2_more_html = trim( ob_get_clean() );
						/* Without WP EasyCart PRO the menu shows what it would add, locked. */
						$ecodv2_more_free = ( '' === $ecodv2_more_html && '' !== apply_filters( 'wp_easycart_admin_lock_icon', 'locked' ) );
						if ( '' !== $ecodv2_more_html || $ecodv2_more_free ) :
							?>
						<div class="ecodv2-toolbar-more ecdv2-menu-wrap">
							<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" onclick="ecodv2_toolbar_more_toggle( this ); return false;" aria-haspopup="true" aria-expanded="false" aria-controls="ecodv2_toolbar_menu"><span class="dashicons dashicons-ellipsis" aria-hidden="true"></span> <?php esc_html_e( 'More', 'wp-easycart' ); ?></button>
							<div class="ecdv2-menu ecodv2-toolbar-menu" id="ecodv2_toolbar_menu">
								<?php
								echo $ecodv2_more_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- other plugins' own markup.
								if ( $ecodv2_more_free ) {
									$ecodv2_more_locked = array(
										array( 'refunds', 'dashicons-calculator', __( 'Refund calculator', 'wp-easycart' ) ),
										array( 'emails', 'dashicons-tag', __( 'Tag this order', 'wp-easycart' ) ),
										array( 'history', 'dashicons-backup', __( 'Full order log', 'wp-easycart' ) ),
									);
									foreach ( $ecodv2_more_locked as $ecodv2_ml ) {
										echo '<a href="#" class="ecodv2-menu-locked" onclick="ecodv2_menu_close(); ecodv2_locked( \'' . esc_attr( $ecodv2_ml[0] ) . '\' ); return false;"><span class="dashicons ' . esc_attr( $ecodv2_ml[1] ) . '" aria-hidden="true"></span>' . esc_html( $ecodv2_ml[2] ) . '<span class="ecodv2-pro-pill">' . esc_html( class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::badge( 'pro' ) : __( 'Pro/Premium', 'wp-easycart' ) ) . '</span></a>';
									}
								}
								?>
							</div>
						</div>
						<?php endif; ?>
						<?php /* Retained submit input: the shipped email's legacy form-submit flow. */ ?>
						<input type="submit" id="ecodv2_send_shipped_btn" class="ecodv2-visually-hidden" tabindex="-1" aria-hidden="true" value="<?php esc_attr_e( 'Send Order Shipped Email', 'wp-easycart' ); ?>" onclick="return ec_admin_send_order_shipped_email( )">
					</div>
				</div>

				<?php /* The shipping line of the older layout: the next step panel above says it now. Kept, hidden, with its ids and states for the
				   scripts that still read or write it. */ ?>
				<div class="ecodv2-fulfill-banner ecodv2-fulfill-banner-<?php echo esc_attr( $fulfillment_state ); ?>" id="ecodv2_fulfill_banner" hidden
					data-fulfill-status-ids="<?php echo esc_attr( implode( ',', class_exists( 'wp_easycart_shipments' ) ? wp_easycart_shipments::fulfilled_status_ids() : array( 18, 2 ) ) ); ?>"<?php if ( $wpec_local_pickup ) { ?> data-local-pickup="1"<?php } ?>
					data-msg-fulfilled="<?php echo esc_attr( $wpec_msg_fulfilled ); ?>"
					data-msg-unfulfilled="<?php echo esc_attr( $wpec_msg_unfulfilled ); ?>">
					<div class="ecodv2-fulfill-row">
						<span class="dashicons dashicons-clock" aria-hidden="true"></span><span id="ecodv2_fulfill_message"></span>
						<?php apply_filters( 'wp_easycart_ecv2_create_label_action', 'show_pro_required' ); /* 6.0.2: Fulfill is free; the filter still runs for anything listening. */ ?>
						<button type="button" class="ecv2-btn ecv2-btn-sm" id="ecodv2_create_label_btn" tabindex="-1" style="display:none;" onclick="ecodv2_fulfill_open(); return false;"><?php esc_html_e( 'Fulfill items', 'wp-easycart' ); ?></button>
					</div>
				</div>

				<div class="ecdv2-card-body">
					<?php do_action( 'wp_easycart_order_details_refund_panel' ); ?>
					<div class="ecodv2-refund-error" id="ec_admin_refund_failed"><div><?php esc_html_e( 'There was an error completing the refund.', 'wp-easycart' ); ?></div></div>

					<?php /* Line items, grouped by where they stand when the order has more than one group. */ ?>
					<div class="ecodv2-line-items" id="ec_admin_order_line_items">
						<?php
						$ecodv2_line_map         = array();
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
						$ecodv2_group_order  = array( 'ship' => 0, 'partner' => 1, 'shipped' => 2, 'delivered' => 3, 'none' => 4 );
						$ecodv2_group_labels = array(
							'ship'      => ( 'none' === $fulfillment_state ) ? __( 'Items', 'wp-easycart' ) : __( 'To ship', 'wp-easycart' ),
							'partner'   => __( 'Being made by a fulfillment partner', 'wp-easycart' ),
							'shipped'   => __( 'Shipped', 'wp-easycart' ),
							'delivered' => __( 'Delivered', 'wp-easycart' ),
							'none'      => __( 'No shipping needed', 'wp-easycart' ),
						);
						$ecodv2_groups = array();
						foreach ( $order_details as $ecodv2_idx => $ecodv2_line_row ) {
							$ecodv2_g = isset( $wpec_line_states[ (int) $ecodv2_line_row->orderdetail_id ] ) ? $wpec_line_states[ (int) $ecodv2_line_row->orderdetail_id ]['group'] : 'ship';
							/* An order marked shipped without packages: every line that ships has gone. */
							if ( 'fulfilled' === $fulfillment_state && 'ship' === $ecodv2_g ) {
								$ecodv2_g = 'shipped';
							}
							$ecodv2_groups[ $ecodv2_g ][] = $ecodv2_line_row;
						}
						uksort(
							$ecodv2_groups,
							function( $a, $b ) use ( $ecodv2_group_order ) {
								return $ecodv2_group_order[ $a ] - $ecodv2_group_order[ $b ];
							}
						);
						$ecodv2_show_groups = count( $ecodv2_groups ) > 1;
						foreach ( $ecodv2_groups as $ecodv2_group => $ecodv2_group_lines ) {
							if ( $ecodv2_show_groups ) {
								$ecodv2_group_qty = 0;
								foreach ( $ecodv2_group_lines as $ecodv2_gl ) {
									$ecodv2_group_qty += (int) $ecodv2_gl->quantity;
								}
								$ecodv2_group_label = $ecodv2_group_labels[ $ecodv2_group ];
								if ( 'partner' === $ecodv2_group ) {
									$ecodv2_partner_name = isset( $wpec_line_states[ (int) $ecodv2_group_lines[0]->orderdetail_id ] ) ? $wpec_line_states[ (int) $ecodv2_group_lines[0]->orderdetail_id ]['partner'] : '';
									/* translators: %s: fulfillment partner, e.g. Printful. */
									$ecodv2_group_label = '' !== $ecodv2_partner_name ? sprintf( __( 'Being made by %s', 'wp-easycart' ), $ecodv2_partner_name ) : $ecodv2_group_label;
								}
								echo '<div class="ecodv2-line-group is-' . esc_attr( $ecodv2_group ) . '"><span class="ecodv2-line-group-dot" aria-hidden="true"></span>' . esc_html( $ecodv2_group_label ) . ' <span class="ecodv2-line-group-count">' . esc_html( (string) $ecodv2_group_qty ) . '</span></div>';
							}
							foreach ( $ecodv2_group_lines as $line_item ) {
								$ecodv2_line_map[] = array(
									'id'           => (int) $line_item->orderdetail_id,
									'title'        => wp_strip_all_tags( wp_unslash( $line_item->title ) ),
									'qty'          => (int) $line_item->quantity,
									'unit'         => (float) $line_item->unit_price,
									'total'        => (float) $line_item->total_price,
									'refunded'     => ( isset( $line_item->refunded_quantity ) ) ? (int) $line_item->refunded_quantity : 0,
									'is_download'  => (int) $line_item->is_download,
									'is_giftcard'  => (int) $line_item->is_giftcard,
									'is_shippable' => (int) $line_item->is_shippable,
									'is_free_gift' => ( isset( $line_item->is_free_gift ) ) ? (int) $line_item->is_free_gift : 0,
									'has_download' => ( '' != trim( (string) $line_item->download_key ) ) ? 1 : 0,
								);
								$ecodv2_line_state = isset( $wpec_line_states[ (int) $line_item->orderdetail_id ] ) ? $wpec_line_states[ (int) $line_item->orderdetail_id ] : null;
								/* A Packed tick on each line the store still has to send. */
								$ecodv2_pack = $wpec_packing && ( $ecodv2_line_state ? ( 'ship' === $ecodv2_line_state['group'] && $ecodv2_line_state['quantity'] > $ecodv2_line_state['shipped'] ) : ( ! empty( $line_item->is_shippable ) && empty( $line_item->is_download ) && empty( $line_item->is_giftcard ) ) );
								$ecodv2_pack_checked = $ecodv2_pack && $wpec_pack_order && wp_easycart_admin_order_screen::line_packed( $line_item );
								include EC_PLUGIN_DIRECTORY . '/admin/template/orders/orders/order-item.php';
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
						fulfilled: <?php echo ( '' !== $wpec_tracking ) ? 'true' : 'false'; ?>
					};
					</script>
				</div>

				<?php
				/* Shipping: how it ships ( method, carrier, tracking ) and its packages. Span ids are the V1 ones orders.js rewrites. */
				$wpec_ship_has_details = '' !== trim( (string) $this->order->shipping_method ) || '' !== trim( (string) $this->order->shipping_carrier ) || '' !== $wpec_tracking || ! empty( $this->order->use_expedited_shipping );
				$wpec_ship_open_drawer = "ecodv2_open_edit_drawer( 'fulfillment', 'ec_admin_process_shipping_method' ); return false;";
				?>
				<div class="ecodv2-shipping-section<?php echo in_array( $fulfillment_state, array( 'digital' ), true ) && ! $wpec_ship_has_details ? ' is-quiet' : ''; ?>">
					<div class="ecodv2-shipment<?php echo $wpec_ship_has_details ? '' : ' is-empty'; ?>" id="ec_admin_view_shipping_method">
						<div class="ecodv2-shipment-empty">
							<div class="ecodv2-shipment-empty-text">
								<?php if ( 'digital' === $fulfillment_state ) { ?>
								<span><?php esc_html_e( 'Nothing in this order ships. You can still record a method or tracking number if you send something separately.', 'wp-easycart' ); ?></span>
								<?php } else { ?>
								<span><?php esc_html_e( 'No shipping method or tracking yet.', 'wp-easycart' ); ?></span>
								<?php } ?>
							</div>
							<button type="button" class="ecodv2-edit-link ecodv2-shipment-add" onclick="<?php echo esc_attr( $wpec_ship_open_drawer ); ?>"><?php esc_html_e( 'Add shipping details', 'wp-easycart' ); ?></button>
						</div>
						<div class="ecodv2-shipment-details">
							<div class="ecodv2-shipment-field">
								<span class="ecodv2-shipment-label"><?php esc_html_e( 'Shipping method', 'wp-easycart' ); ?></span>
								<span class="ecodv2-shipment-value"><span id="ec_admin_order_details_shipping_method" data-empty="<?php esc_attr_e( 'Not set', 'wp-easycart' ); ?>"><?php if ( '' !== (string) $this->order->shipping_method ) { echo esc_html( $this->order->shipping_method ) . '<br />'; } ?></span><span id="ec_admin_order_details_shipping_type" class="ecodv2-shipment-chip"><?php if ( $this->order->use_expedited_shipping ) { echo esc_html__( 'Expedited shipping', 'wp-easycart' ) . '<br />'; } ?></span></span>
							</div>
							<div class="ecodv2-shipment-field">
								<span class="ecodv2-shipment-label"><?php esc_html_e( 'Carrier', 'wp-easycart' ); ?></span>
								<span class="ecodv2-shipment-value"><span id="ec_admin_order_details_shipping_carrier" data-empty="<?php esc_attr_e( 'Not set', 'wp-easycart' ); ?>"><?php if ( '' !== (string) $this->order->shipping_carrier ) { echo esc_html( $this->order->shipping_carrier ) . '<br />'; } ?></span></span>
							</div>
							<div class="ecodv2-shipment-field">
								<span class="ecodv2-shipment-label"><?php esc_html_e( 'Tracking number', 'wp-easycart' ); ?></span>
								<span class="ecodv2-shipment-value ecodv2-tracking-line"><span id="ec_admin_order_details_tracking_number" data-empty="<?php esc_attr_e( 'Not set', 'wp-easycart' ); ?>"><?php echo esc_html( $this->order->tracking_number ); ?></span><button type="button" class="ecodv2-copy-link ecodv2-tracking-copy"<?php if ( '' === $wpec_tracking ) { ?> style="display:none;"<?php } ?> onclick="ecodv2_copy_tracking( this ); return false;" title="<?php esc_attr_e( 'Copy tracking number', 'wp-easycart' ); ?>" aria-label="<?php esc_attr_e( 'Copy tracking number', 'wp-easycart' ); ?>"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span></button></span>
							</div>
							<button type="button" class="ecodv2-edit-link ecodv2-shipment-edit" id="ec_admin_order_details_shipping_method_edit" onclick="<?php echo esc_attr( $wpec_ship_open_drawer ); ?>"><span class="dashicons dashicons-edit" aria-hidden="true"></span> <?php esc_html_e( 'Edit', 'wp-easycart' ); ?></button>
						</div>
						<div id="ec_admin_order_details_shipping_empty_message" class="ecodv2-legacy-hidden"></div>
					</div>
					<?php
					/**
					 * The order's packages ( wp_easycart_admin_packages ), with their labels and tracking.
					 *
					 * @param object $order             Order row.
					 * @param string $fulfillment_state fulfilled | partial | pickup | unfulfilled | digital | none.
					 */
					do_action( 'wp_easycart_ecv2_order_details_packages', $this->order, $fulfillment_state );
					?>
				</div>
			</div>

			<?php /* ---------- Payment ---------- */ ?>
			<?php
			$edit_totals_action = apply_filters( 'wp_easycart_admin_order_details_totals_edit_action', 'show_pro_required' );
			$refund_action      = apply_filters( 'wp_easycart_admin_order_details_refund_action', 'show_pro_required' );
			$wpec_pay_state     = ( $wpec_pay_sum && ! empty( $wpec_pay_sum['recorded'] ) ) ? $wpec_pay_sum['state'] : ( ! empty( $this->order->is_approved ) ? 'paid' : 'unpaid' );
			$wpec_pay_chips     = array(
				'paid'      => array( 'is-good', __( 'Paid in full', 'wp-easycart' ) ),
				'partial'   => array( 'is-warn', __( 'Balance due', 'wp-easycart' ) ),
				'unpaid'    => array( 'is-warn', __( 'Not paid', 'wp-easycart' ) ),
				'overpaid'  => array( 'is-info', __( 'Refund due', 'wp-easycart' ) ),
				'refunded'  => array( 'is-bad', __( 'Refunded', 'wp-easycart' ) ),
				'cancelled' => array( 'is-muted', __( 'Canceled', 'wp-easycart' ) ),
			);
			$wpec_pay_chip      = isset( $wpec_pay_chips[ $wpec_pay_state ] ) ? $wpec_pay_chips[ $wpec_pay_state ] : null;
			?>
			<div class="ecdv2-card ecodv2-card-totals ecodv2-o-payment">
				<div class="ecdv2-card-header">
					<h3 class="ecdv2-card-title"><?php esc_html_e( 'Payment', 'wp-easycart' ); ?></h3>
					<?php if ( $wpec_pay_chip ) { ?>
					<span class="ecodv2-pay-chip <?php echo esc_attr( $wpec_pay_chip[0] ); ?>" id="ecodv2_pay_chip"><?php echo esc_html( $wpec_pay_chip[1] ); ?></span>
					<?php } ?>
					<span class="ecodv2-refund-chip<?php echo ( (float) $this->order->refund_total < 0.005 ) ? ' ec_admin_initial_hide' : ''; ?>" id="ecodv2_refund_chip"><?php esc_html_e( 'Refunded', 'wp-easycart' ); ?> <span class="ecodv2-amt" data-amt-for="ecodv2_refund_chip_amount"><?php echo esc_html( wp_easycart_admin_order_screen::money( $this->order->refund_total ) ); ?></span><span class="ecodv2-amt-raw" id="ecodv2_refund_chip_amount" hidden><?php echo esc_html( number_format( (float) $this->order->refund_total, 2, '.', '' ) ); ?></span></span>
					<div class="ecdv2-card-header-actions">
						<?php
						/* 6.0.3: an order paid with a gift card can be refunded to the card ( WP EasyCart PRO 6.0.3's refund window ). */
						$wpec_card_left   = ( 'show_pro_required' !== $refund_action && apply_filters( 'wp_easycart_admin_order_giftcard_refunds', false ) ) ? wp_easycart_order_returns::giftcard( (int) $this->order->order_id ) : null;
						$wpec_card_refund = $wpec_card_left && $wpec_card_left['returnable'] >= 0.005;
						?>
						<?php if ( (float) $this->order->grand_total > (float) $this->order->refund_total || $wpec_card_refund ) { ?>
						<button type="button" class="ecv2-btn ecv2-btn-sm" onclick="<?php echo ( 'show_pro_required' === $refund_action ) ? "ecodv2_locked( 'refunds' );" : esc_attr( $refund_action ) . '( );'; ?> return false;" id="ec_admin_refund_button" aria-keyshortcuts="R"><span class="dashicons dashicons-undo" aria-hidden="true"></span> <?php esc_html_e( 'Refund', 'wp-easycart' ); ?> <kbd class="ecodv2-kbd">R</kbd><?php if ( 'show_pro_required' === $refund_action ) { ?> <span class="ecodv2-pro-pill"><?php echo esc_html( class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::badge( 'pro' ) : __( 'Pro/Premium', 'wp-easycart' ) ); ?></span><?php } ?></button>
						<?php } ?>
						<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost ecodv2-totals-edit ecodv2-header-edit" id="ec_admin_order_total_edit" onclick="<?php echo ( 'show_pro_required' === $edit_totals_action ) ? "ecodv2_locked( 'totals' );" : esc_attr( $edit_totals_action ) . '( );'; ?> return false;"><span class="dashicons dashicons-edit" aria-hidden="true"></span> <span><?php esc_html_e( 'Edit totals', 'wp-easycart' ); ?></span><?php if ( 'show_pro_required' === $edit_totals_action ) { ?> <span class="ecodv2-pro-pill"><?php echo esc_html( class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::badge( 'pro' ) : __( 'Pro/Premium', 'wp-easycart' ) ); ?></span><?php } ?></button>
						<?php if ( '' !== trim( (string) $this->order->user_email ) ) { ?>
						<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" onclick="ecodv2_send_dialog( document.getElementById( 'ecodv2_send_email_link' ), 'receipt' ); return false;"><span class="dashicons dashicons-email" aria-hidden="true"></span> <?php esc_html_e( 'Send receipt', 'wp-easycart' ); ?></button>
						<?php } ?>
					</div>
				</div>
				<div class="ecdv2-card-body">
					<?php
					/* The payment at a glance: what was charged, refunded and kept, or what is still due. The amounts follow the rows
					   below ( data-ecodv2-mirror ), which WP EasyCart PRO and the older scripts rewrite in place. */
					$wpec_fig_recorded = $wpec_pay_sum && ! empty( $wpec_pay_sum['recorded'] );
					$wpec_fig_paid     = $wpec_fig_recorded ? (float) $wpec_pay_sum['paid'] : ( ! empty( $this->order->is_approved ) ? (float) $this->order->grand_total : 0.0 );
					$wpec_fig_due      = $wpec_fig_recorded ? in_array( $wpec_pay_sum['state'], array( 'unpaid', 'partial' ), true ) : ( empty( $this->order->is_approved ) && ! in_array( (int) $this->order->orderstatus_id, array( 16, 19 ), true ) );
					$wpec_fig_method   = wp_easycart_admin_order_screen::payment_method( $this->order );
					$wpec_fig_how      = implode( ' · ', array_filter( array( $wpec_fig_method['method'], $wpec_fig_method['gateway'] ) ) );
					?>
					<div class="ecodv2-figures<?php echo $wpec_fig_due ? ' is-due' : ''; ?>" id="ecodv2_figures">
						<?php if ( $wpec_fig_due ) { ?>
						<div class="ecodv2-figure"><span class="ecodv2-figure-label"><?php esc_html_e( 'Order total', 'wp-easycart' ); ?></span><span class="ecodv2-figure-amt" data-ecodv2-mirror="ec_admin_order_details_totals_grand_total"><?php echo esc_html( wp_easycart_admin_order_screen::money( $this->order->grand_total ) ); ?></span></div>
						<div class="ecodv2-figure"><span class="ecodv2-figure-label"><?php esc_html_e( 'Paid', 'wp-easycart' ); ?></span><span class="ecodv2-figure-amt"<?php if ( $wpec_fig_recorded ) { ?> data-ecodv2-mirror="ec_admin_order_details_totals_paid"<?php } ?>><?php echo esc_html( wp_easycart_admin_order_screen::money( $wpec_fig_paid ) ); ?></span><?php if ( '' !== $wpec_fig_how && $wpec_fig_paid >= 0.005 ) { ?><span class="ecodv2-figure-sub"><?php echo esc_html( $wpec_fig_how ); ?></span><?php } ?></div>
						<div class="ecodv2-figure is-due"><span class="ecodv2-figure-label"<?php if ( $wpec_fig_recorded ) { ?> data-ecodv2-mirror-text="ec_admin_order_details_totals_balance_label"<?php } ?>><?php echo esc_html( $wpec_fig_recorded && 'partial' === $wpec_pay_sum['state'] ? __( 'Balance due', 'wp-easycart' ) : __( 'Amount due', 'wp-easycart' ) ); ?></span><span class="ecodv2-figure-amt"<?php if ( $wpec_fig_recorded ) { ?> data-ecodv2-mirror="ec_admin_order_details_totals_balance"<?php } ?>><?php echo esc_html( wp_easycart_admin_order_screen::money( $wpec_fig_recorded ? $wpec_pay_sum['due'] : $this->order->grand_total ) ); ?></span></div>
						<?php } else { ?>
						<div class="ecodv2-figure"><span class="ecodv2-figure-label"><?php esc_html_e( 'Charged', 'wp-easycart' ); ?></span><span class="ecodv2-figure-amt"<?php if ( $wpec_fig_recorded ) { ?> data-ecodv2-mirror="ec_admin_order_details_totals_paid"<?php } ?>><?php echo esc_html( wp_easycart_admin_order_screen::money( $wpec_fig_paid ) ); ?></span><?php if ( '' !== $wpec_fig_how ) { ?><span class="ecodv2-figure-sub"><?php echo esc_html( $wpec_fig_how ); ?></span><?php } ?></div>
						<div class="ecodv2-figure is-refund"><span class="ecodv2-figure-label"><?php esc_html_e( 'Refunded', 'wp-easycart' ); ?></span><span class="ecodv2-figure-amt" data-ecodv2-mirror="ec_admin_order_details_totals_refund_total"><?php echo esc_html( wp_easycart_admin_order_screen::money( $this->order->refund_total ) ); ?></span></div>
						<div class="ecodv2-figure is-net"><span class="ecodv2-figure-label"><?php esc_html_e( 'Net received', 'wp-easycart' ); ?></span><span class="ecodv2-figure-amt" data-ecodv2-mirror="ecodv2_totals_net"><?php echo esc_html( wp_easycart_admin_order_screen::money( max( 0, $wpec_fig_paid - (float) $this->order->refund_total ) ) ); ?></span></div>
						<?php } ?>
					</div>
					<?php
					/* How it was paid. WP EasyCart PRO's panel ( gateway, transaction link, card ) takes this line's place; without it
					   the order's own record is shown. The ids stay for the contact form's save. */
					$wpec_has_panel = has_action( 'wp_easycart_ecv2_order_details_payment_panel' );
					$wpec_method    = wp_easycart_admin_order_screen::payment_method( $this->order );
					?>
					<div class="ecodv2-method<?php echo $wpec_has_panel ? ' has-panel' : ''; ?>" id="ec_admin_view_order_information">
						<?php do_action( 'wp_easycart_ecv2_order_details_payment_panel', $this->order ); ?>
						<div class="ecodv2-method-line"<?php if ( $wpec_has_panel ) { ?> hidden<?php } ?>>
							<span class="dashicons dashicons-money-alt ecodv2-payment-icon" aria-hidden="true"></span>
							<span class="ecodv2-method-text">
								<?php if ( '' !== $wpec_method['method'] ) { ?><b><?php echo esc_html( $wpec_method['method'] ); ?></b><?php } ?>
								<?php if ( '' !== $wpec_method['gateway'] ) { ?><span class="ecodv2-method-sep" aria-hidden="true">·</span><span><?php echo esc_html( $wpec_method['gateway'] ); ?></span><?php } ?>
								<?php if ( '' === $wpec_method['method'] && '' === $wpec_method['gateway'] ) { ?><span class="ecodv2-muted"><?php esc_html_e( 'No payment details recorded', 'wp-easycart' ); ?></span><?php } ?>
							</span>
						</div>
						<?php /* The saved card details, kept for the contact form's save ( it writes them back in place ). */ ?>
						<span class="ecodv2-method-card" hidden>
							<span id="ec_admin_order_details_card_holder_name" class="ecodv2-kv-name"><?php echo esc_html( $this->order->card_holder_name ); ?></span>
							<span class="ecodv2-cc-line"><span id="ec_admin_order_details_creditcard_digits"><?php if ( '' !== (string) $this->order->creditcard_digits ) { ?><span class="ecodv2-cc-dots">&bull;&bull;&bull;&bull;</span> <b><?php echo esc_html( $this->order->creditcard_digits ); ?></b><?php } ?></span> <span id="ec_admin_order_details_cc_exp"><?php if ( '' !== (string) $this->order->cc_exp_month ) { ?><span class="ecodv2-cc-exp">&middot; <?php echo esc_html( $this->order->cc_exp_month ); ?> / <?php echo esc_html( $this->order->cc_exp_year ); ?></span><?php } ?></span></span>
						</span>
					</div>

					<div class="ecodv2-totals-box">
						<div id="ec_admin_order_details_totals_content">
							<?php
							$wpec_ship_label = trim( (string) $this->order->shipping_method );
							/* Discounts: offers, the coupon code and the gift card, one row that opens. */
							$wpec_discount_bits = array();
							if ( function_exists( 'wp_easycart_offers_active' ) && wp_easycart_offers_active() ) {
								$wpec_order_offers = ( isset( $this->order->applied_offers ) && '' != $this->order->applied_offers ) ? json_decode( (string) $this->order->applied_offers, true ) : false;
								if ( is_array( $wpec_order_offers ) && ! empty( $wpec_order_offers['applied_offers'] ) ) {
									$wpec_offer_ids = array();
									foreach ( $wpec_order_offers['applied_offers'] as $wpec_order_offer ) {
										if ( isset( $wpec_order_offer['offer_id'] ) && (int) $wpec_order_offer['offer_id'] > 0 ) {
											$wpec_offer_ids[] = (int) $wpec_order_offer['offer_id'];
										}
									}
									$wpec_live_offer_ids = array();
									if ( $wpec_offer_ids ) {
										// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- every id is an int.
										$wpec_live_offer_ids = array_map( 'intval', $wpdb->get_col( 'SELECT offer_id FROM ec_offer WHERE offer_id IN (' . implode( ',', array_map( 'intval', $wpec_offer_ids ) ) . ')' ) );
									}
									foreach ( $wpec_order_offers['applied_offers'] as $wpec_order_offer ) {
										$wpec_offer_label = ( isset( $wpec_order_offer['label'] ) && '' != $wpec_order_offer['label'] ) ? $wpec_order_offer['label'] : ( isset( $wpec_order_offer['code'] ) ? $wpec_order_offer['code'] : '' );
										if ( '' == $wpec_offer_label ) {
											continue;
										}
										$wpec_offer_id        = isset( $wpec_order_offer['offer_id'] ) ? (int) $wpec_order_offer['offer_id'] : 0;
										$wpec_discount_bits[] = array(
											'kind'    => 'offer',
											'label'   => $wpec_offer_label,
											'code'    => isset( $wpec_order_offer['code'] ) ? (string) $wpec_order_offer['code'] : '',
											'amount'  => isset( $wpec_order_offer['amount'] ) ? (float) $wpec_order_offer['amount'] : 0,
											'link'    => ( $wpec_offer_id > 0 && in_array( $wpec_offer_id, $wpec_live_offer_ids, true ) ) ? admin_url( 'admin.php?page=wp-easycart-rates&subpage=offers&ec_admin_form_action=edit&offer_id=' . $wpec_offer_id ) : '',
											'deleted' => ( $wpec_offer_id > 0 && ! in_array( $wpec_offer_id, $wpec_live_offer_ids, true ) ),
										);
									}
								}
							}
							if ( '' !== trim( (string) $this->order->promo_code ) ) {
								$wpec_code_in_offers = false;
								foreach ( $wpec_discount_bits as $wpec_bit ) {
									if ( '' !== $wpec_bit['code'] && strtolower( $wpec_bit['code'] ) === strtolower( trim( (string) $this->order->promo_code ) ) ) {
										$wpec_code_in_offers = true;
									}
								}
								if ( ! $wpec_code_in_offers ) {
									$wpec_discount_bits[] = array( 'kind' => 'coupon', 'label' => __( 'Coupon', 'wp-easycart' ), 'code' => (string) $this->order->promo_code, 'amount' => 0, 'link' => '', 'deleted' => false );
								}
							}
							if ( '' !== trim( (string) $this->order->giftcard_id ) ) {
								$wpec_discount_bits[] = array( 'kind' => 'giftcard', 'label' => __( 'Gift card', 'wp-easycart' ), 'code' => (string) $this->order->giftcard_id, 'amount' => ( isset( $this->order->giftcard_total ) && null !== $this->order->giftcard_total ) ? (float) $this->order->giftcard_total : 0, 'link' => '', 'deleted' => false );
							}
							?>
							<div class="ecodv2-trow">
								<div class="ecodv2-trow-label"><?php esc_html_e( 'Subtotal', 'wp-easycart' ); ?> <span class="ecodv2-trow-note"><?php echo esc_html( sprintf( _n( '%d item', '%d items', $item_count, 'wp-easycart' ), $item_count ) ); ?></span></div>
								<div class="ecodv2-trow-total"><?php echo wp_easycart_admin_order_screen::amount_html( 'ec_admin_order_details_totals_sub_total', $this->order->sub_total ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- amount_html() escapes. ?></div>
							</div>
							<div class="ecodv2-trow ecodv2-trow-discount<?php if ( $this->order->discount_total == 0 ) { ?> ec_admin_initial_hide<?php } ?>" id="ec_admin_order_details_totals_discount_total_row">
								<div class="ecodv2-trow-label">
									<?php if ( $wpec_discount_bits ) { ?>
									<button type="button" class="ecodv2-trow-toggle" onclick="ecodv2_offers_toggle( this ); return false;" aria-expanded="false" aria-controls="ecodv2_discount_detail"><?php esc_html_e( 'Discount', 'wp-easycart' ); ?> <span class="ecodv2-trow-note"><?php echo esc_html( implode( ', ', array_filter( array_map( function( $bit ) { return '' !== $bit['code'] ? $bit['code'] : $bit['label']; }, array_slice( $wpec_discount_bits, 0, 2 ) ) ) ) ); ?></span> <span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></button>
									<?php } else { ?>
										<?php esc_html_e( 'Discount', 'wp-easycart' ); ?>
									<?php } ?>
								</div>
								<div class="ecodv2-trow-total ecodv2-discount-total"><?php echo wp_easycart_admin_order_screen::amount_html( 'ec_admin_order_details_totals_discount_total', $this->order->discount_total, '-' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- amount_html() escapes. ?></div>
							</div>
							<?php if ( $wpec_discount_bits ) { ?>
							<div class="ecodv2-offers-panel ecodv2-discount-detail" id="ecodv2_discount_detail">
								<div class="ecodv2-offers-detail">
									<?php foreach ( $wpec_discount_bits as $wpec_bit ) { ?>
									<div class="ecodv2-offers-row">
										<span class="ecodv2-offers-left">
											<span class="dashicons dashicons-<?php echo esc_attr( 'giftcard' === $wpec_bit['kind'] ? 'money-alt' : ( 'coupon' === $wpec_bit['kind'] || '' !== $wpec_bit['code'] ? 'tickets-alt' : 'tag' ) ); ?>" aria-hidden="true"></span>
											<?php if ( '' !== $wpec_bit['link'] ) { ?>
											<a href="<?php echo esc_url( $wpec_bit['link'] ); ?>"><?php echo esc_html( $wpec_bit['label'] ); ?></a>
											<?php } else { ?>
											<span><?php echo esc_html( $wpec_bit['label'] ); ?></span>
											<?php } ?>
											<?php if ( $wpec_bit['deleted'] ) { ?><span class="ecodv2-offers-deleted"><?php esc_html_e( 'offer deleted', 'wp-easycart' ); ?></span><?php } ?>
											<?php if ( '' !== $wpec_bit['code'] && $wpec_bit['code'] !== $wpec_bit['label'] ) { ?><code class="ecodv2-offers-code"><?php echo esc_html( $wpec_bit['code'] ); ?></code><?php } ?>
										</span>
										<?php if ( $wpec_bit['amount'] > 0 ) { ?>
										<span class="ecodv2-offers-amount">&minus;<?php echo esc_html( wp_easycart_admin_order_screen::money( $wpec_bit['amount'] ) ); ?></span>
										<?php } ?>
									</div>
									<?php } ?>
									<?php if ( 0 < count( array_filter( $wpec_discount_bits, function( $bit ) { return 'offer' === $bit['kind']; } ) ) ) { ?>
									<div class="ecodv2-offers-note"><?php esc_html_e( 'What applied when the order was placed. Offers can change or be deleted later.', 'wp-easycart' ); ?></div>
									<?php } ?>
									<button type="button" class="ecodv2-edit-link" onclick="ecodv2_open_edit_drawer( 'codes', '' ); return false;"><?php esc_html_e( 'Edit the coupon and gift card codes', 'wp-easycart' ); ?></button>
								</div>
							</div>
							<?php } ?>
							<div class="ecodv2-trow">
								<div class="ecodv2-trow-label"><?php esc_html_e( 'Shipping', 'wp-easycart' ); ?><?php if ( '' !== $wpec_ship_label ) { ?> <span class="ecodv2-trow-note"><?php echo esc_html( $wpec_ship_label ); ?></span><?php } ?></div>
								<div class="ecodv2-trow-total"><?php echo wp_easycart_admin_order_screen::amount_html( 'ec_admin_order_details_totals_shipping_total', $this->order->shipping_total ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- amount_html() escapes. ?></div>
							</div>
							<?php if ( count( $this->order->order_fees ) > 0 ) { ?>
								<?php foreach ( $this->order->order_fees as $order_fee ) { ?>
									<?php /* 6.0.2 bug round 14: a fee that came to nothing stays hidden ( orders placed before checkout stopped saving them ); it shows once Edit totals gives it an amount ( data-hide-zero ). */ ?>
							<div class="ecodv2-trow<?php echo ( abs( (float) $order_fee->fee_total ) < 0.005 ) ? ' ec_admin_initial_hide' : ''; ?>" data-hide-zero="1" id="ec_admin_order_details_totals_flex_fee_<?php echo esc_attr( $order_fee->order_fee_id ); ?>_row">
								<div class="ecodv2-trow-label"><?php echo esc_html( wp_unslash( $order_fee->fee_label ) ); ?></div>
								<div class="ecodv2-trow-total"><?php echo wp_easycart_admin_order_screen::amount_html( 'ec_admin_order_details_totals_flex_fee_' . $order_fee->order_fee_id, $order_fee->fee_total ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- amount_html() escapes. ?></div>
							</div>
								<?php } ?>
							<?php } ?>
							<div class="ecodv2-trow<?php if ( $this->order->tax_total == 0 ) { ?> ec_admin_initial_hide<?php } ?>" id="ec_admin_order_details_totals_tax_total_row">
								<div class="ecodv2-trow-label"><?php esc_html_e( 'Tax', 'wp-easycart' ); ?></div>
								<div class="ecodv2-trow-total"><?php echo wp_easycart_admin_order_screen::amount_html( 'ec_admin_order_details_totals_tax_total', $this->order->tax_total ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- amount_html() escapes. ?></div>
							</div>
							<div class="ecodv2-trow<?php if ( $this->order->vat_total == 0 ) { ?> ec_admin_initial_hide<?php } ?>" id="ec_admin_order_details_totals_vat_total_row">
								<div class="ecodv2-trow-label"><?php esc_html_e( 'VAT', 'wp-easycart' ); ?> <span class="ecodv2-trow-note"><span id="ec_admin_order_details_totals_vat_total_rate"><?php echo esc_html( $this->order->vat_rate ); ?></span>%</span></div>
								<div class="ecodv2-trow-total"><?php echo wp_easycart_admin_order_screen::amount_html( 'ec_admin_order_details_totals_vat_total', $this->order->vat_total ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- amount_html() escapes. ?></div>
							</div>
							<div class="ecodv2-trow<?php if ( $this->order->gst_total == 0 ) { ?> ec_admin_initial_hide<?php } ?>" id="ec_admin_order_details_totals_gst_total_row">
								<div class="ecodv2-trow-label"><?php esc_html_e( 'GST', 'wp-easycart' ); ?> <span class="ecodv2-trow-note"><span id="ec_admin_order_details_totals_gst_total_rate"><?php echo esc_html( $this->order->gst_rate ); ?></span>%</span></div>
								<div class="ecodv2-trow-total"><?php echo wp_easycart_admin_order_screen::amount_html( 'ec_admin_order_details_totals_gst_total', $this->order->gst_total ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- amount_html() escapes. ?></div>
							</div>
							<div class="ecodv2-trow<?php if ( $this->order->hst_total == 0 ) { ?> ec_admin_initial_hide<?php } ?>" id="ec_admin_order_details_totals_hst_total_row">
								<div class="ecodv2-trow-label"><?php esc_html_e( 'HST', 'wp-easycart' ); ?> <span class="ecodv2-trow-note"><span id="ec_admin_order_details_totals_hst_total_rate"><?php echo esc_html( $this->order->hst_rate ); ?></span>%</span></div>
								<div class="ecodv2-trow-total"><?php echo wp_easycart_admin_order_screen::amount_html( 'ec_admin_order_details_totals_hst_total', $this->order->hst_total ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- amount_html() escapes. ?></div>
							</div>
							<div class="ecodv2-trow<?php if ( $this->order->pst_total == 0 ) { ?> ec_admin_initial_hide<?php } ?>" id="ec_admin_order_details_totals_pst_total_row">
								<div class="ecodv2-trow-label"><?php echo ( 'QC' === strtoupper( (string) $this->order->shipping_state ) ) ? esc_html__( 'QST', 'wp-easycart' ) : esc_html__( 'PST', 'wp-easycart' ); ?> <span class="ecodv2-trow-note"><span id="ec_admin_order_details_totals_pst_total_rate"><?php echo esc_html( $this->order->pst_rate ); ?></span>%</span></div>
								<div class="ecodv2-trow-total"><?php echo wp_easycart_admin_order_screen::amount_html( 'ec_admin_order_details_totals_pst_total', $this->order->pst_total ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- amount_html() escapes. ?></div>
							</div>
							<div class="ecodv2-trow<?php if ( $this->order->duty_total == 0 ) { ?> ec_admin_initial_hide<?php } ?>" id="ec_admin_order_details_totals_duty_total_row">
								<div class="ecodv2-trow-label"><?php esc_html_e( 'Duty', 'wp-easycart' ); ?></div>
								<div class="ecodv2-trow-total"><?php echo wp_easycart_admin_order_screen::amount_html( 'ec_admin_order_details_totals_duty_total', $this->order->duty_total ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- amount_html() escapes. ?></div>
							</div>
							<div class="ecodv2-trow<?php if ( $this->order->tip_total == 0 ) { ?> ec_admin_initial_hide<?php } ?>" id="ec_admin_order_details_totals_tip_total_row">
								<div class="ecodv2-trow-label"><?php esc_html_e( 'Tip', 'wp-easycart' ); ?></div>
								<div class="ecodv2-trow-total"><?php echo wp_easycart_admin_order_screen::amount_html( 'ec_admin_order_details_totals_tip_total', $this->order->tip_total ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- amount_html() escapes. ?></div>
							</div>
							<?php /* The VAT number belongs to the customer; it shows under Bill to. The row stays ( hidden ) for the totals save. */ ?>
							<div class="ecodv2-trow ecodv2-legacy-hidden" id="ec_admin_order_details_totals_vat_registration_number_row">
								<div class="ecodv2-trow-label"><?php esc_html_e( 'VAT registration number', 'wp-easycart' ); ?></div>
								<div class="ecodv2-trow-total" id="ec_admin_order_details_totals_vat_registration_number"><?php echo esc_html( $this->order->vat_registration_number ); ?></div>
							</div>
							<?php
							$wpec_refunded = (float) $this->order->refund_total;
							$wpec_net_base = ( $wpec_pay_sum && ! empty( $wpec_pay_sum['recorded'] ) ) ? (float) $wpec_pay_sum['paid'] : (float) $this->order->grand_total;
							$wpec_ref_hide = ( $wpec_refunded < 0.005 ) ? ' ec_admin_initial_hide' : '';
							?>
							<div class="ecodv2-trow ecodv2-grand-row">
								<div class="ecodv2-trow-label"><?php esc_html_e( 'Total', 'wp-easycart' ); ?></div>
								<div class="ecodv2-trow-total"><?php echo wp_easycart_admin_order_screen::amount_html( 'ec_admin_order_details_totals_grand_total', $this->order->grand_total ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- amount_html() escapes. ?></div>
							</div>
							<?php
							$wpec_pay_closed = $wpec_pay_sum && in_array( $wpec_pay_sum['state'], array( 'refunded', 'cancelled' ), true );
							if ( $wpec_pay_sum && $wpec_pay_sum['recorded'] ) {
								$wpec_pay_labels = array(
									'unpaid'   => __( 'Amount due', 'wp-easycart' ),
									'partial'  => __( 'Balance due', 'wp-easycart' ),
									'overpaid' => __( 'Refund due', 'wp-easycart' ),
									'paid'     => __( 'Balance', 'wp-easycart' ),
								);
								$wpec_pay_amount = ( 'overpaid' === $wpec_pay_sum['state'] ) ? $wpec_pay_sum['over'] : $wpec_pay_sum['due'];
								?>
							<div class="ecodv2-trow ecodv2-trow-paid<?php echo ( $wpec_pay_sum['paid'] < 0.005 ) ? ' ec_admin_initial_hide' : ''; ?>" id="ec_admin_order_details_totals_paid_row">
								<div class="ecodv2-trow-label"><?php esc_html_e( 'Paid by customer', 'wp-easycart' ); ?></div>
								<div class="ecodv2-trow-total"><?php echo wp_easycart_admin_order_screen::amount_html( 'ec_admin_order_details_totals_paid', $wpec_pay_sum['paid'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- amount_html() escapes. ?></div>
							</div>
							<?php } ?>
							<div class="ecodv2-trow ecodv2-trow-refund<?php echo esc_attr( $wpec_ref_hide ); ?>" id="ec_admin_order_details_totals_refund_total_row">
								<div class="ecodv2-trow-label"><?php esc_html_e( 'Refunded', 'wp-easycart' ); ?></div>
								<div class="ecodv2-trow-total"><?php echo wp_easycart_admin_order_screen::amount_html( 'ec_admin_order_details_totals_refund_total', $wpec_refunded, '-' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- amount_html() escapes. ?></div>
							</div>
							<div class="ecodv2-trow ecodv2-trow-net<?php echo esc_attr( $wpec_ref_hide ); ?>" id="ecodv2_totals_net_row" data-base="<?php echo esc_attr( number_format( $wpec_net_base, 2, '.', '' ) ); ?>">
								<div class="ecodv2-trow-label"><?php esc_html_e( 'Net received', 'wp-easycart' ); ?></div>
								<div class="ecodv2-trow-total"><?php echo wp_easycart_admin_order_screen::amount_html( 'ecodv2_totals_net', max( 0, $wpec_net_base - $wpec_refunded ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- amount_html() escapes. ?></div>
							</div>
							<?php if ( $wpec_pay_sum && $wpec_pay_sum['recorded'] && ! $wpec_pay_closed ) { ?>
							<div class="ecodv2-trow ecodv2-trow-balance is-<?php echo esc_attr( $wpec_pay_sum['state'] ); ?>" id="ec_admin_order_details_totals_balance_row"
								data-paid="<?php echo esc_attr( number_format( $wpec_pay_sum['paid'], 2, '.', '' ) ); ?>"
								data-overpaid-refunded="<?php echo esc_attr( number_format( $wpec_pay_sum['overpaid_refunded'], 2, '.', '' ) ); ?>"
								data-label-unpaid="<?php echo esc_attr( $wpec_pay_labels['unpaid'] ); ?>"
								data-label-partial="<?php echo esc_attr( $wpec_pay_labels['partial'] ); ?>"
								data-label-overpaid="<?php echo esc_attr( $wpec_pay_labels['overpaid'] ); ?>"
								data-label-paid="<?php echo esc_attr( $wpec_pay_labels['paid'] ); ?>"
								title="<?php echo esc_attr( 'overpaid' === $wpec_pay_sum['state'] ? __( 'The customer paid more than the order now comes to. Refund the difference, or add to the order.', 'wp-easycart' ) : '' ); ?>"
								data-title-overpaid="<?php esc_attr_e( 'The customer paid more than the order now comes to. Refund the difference, or add to the order.', 'wp-easycart' ); ?>">
								<div class="ecodv2-trow-label"><span id="ec_admin_order_details_totals_balance_label"><?php echo esc_html( $wpec_pay_labels[ $wpec_pay_sum['state'] ] ); ?></span></div>
								<div class="ecodv2-trow-total"><span class="ecodv2-balance-amount"><?php echo wp_easycart_admin_order_screen::amount_html( 'ec_admin_order_details_totals_balance', $wpec_pay_amount ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- amount_html() escapes. ?></span><span class="ecodv2-balance-pill"><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php esc_html_e( 'Paid in full', 'wp-easycart' ); ?></span></div>
							</div>
							<?php } ?>
							<?php
							if ( $wpec_pay_sum && $wpec_pay_sum['recorded'] ) {
								/**
								 * After Paid and the balance in the order screen's totals ( WP EasyCart PRO: Record payment ).
								 *
								 * @since 6.0.2
								 * @param object $order   Order.
								 * @param array  $summary wp_easycart_order_payments::summary().
								 */
								do_action( 'wp_easycart_admin_order_details_payments', $this->order, $wpec_pay_sum );
							}
							/* 6.0.2: an order paid more than once lists each payment, what was refunded from it and how a refund
							   of it goes back ( wp_easycart_order_refunds ). */
							$wpec_payments = class_exists( 'wp_easycart_order_refunds' ) ? wp_easycart_order_refunds::payments( $this->order ) : array();
							if ( count( $wpec_payments ) > 1 ) {
								?>
							<div class="ecodv2-payments" id="ecodv2_payments">
								<div class="ecodv2-payments-title"><?php esc_html_e( 'Payments', 'wp-easycart' ); ?></div>
								<?php
								foreach ( $wpec_payments as $wpec_payment ) {
									$wpec_payment_at   = strtotime( $wpec_payment['date'] . ' UTC' );
									$wpec_payment_date = $wpec_payment_at ? ( function_exists( 'wp_date' ) ? wp_date( get_option( 'date_format' ), $wpec_payment_at ) : gmdate( 'Y-m-d', $wpec_payment_at ) ) : '';
									$wpec_payment_meta = array_filter( array( $wpec_payment['kind'], $wpec_payment_date, $wpec_payment['reference'] ) );
									?>
								<div class="ecodv2-payment is-<?php echo esc_attr( $wpec_payment['route'] ); ?>">
									<div class="ecodv2-payment-main">
										<span class="ecodv2-payment-name"><?php echo esc_html( $wpec_payment['name'] ); ?></span>
										<span class="ecodv2-payment-meta"><?php echo esc_html( implode( ' · ', $wpec_payment_meta ) ); ?></span>
										<span class="ecodv2-payment-note"><?php echo esc_html( $wpec_payment['note'] ); ?></span>
									</div>
									<div class="ecodv2-payment-amounts">
										<span class="ecodv2-payment-amount"><?php echo esc_html( wp_strip_all_tags( $GLOBALS['currency']->get_currency_display( $wpec_payment['amount'] ) ) ); ?></span>
										<?php if ( $wpec_payment['refunded'] >= 0.005 ) { ?>
										<span class="ecodv2-payment-refunded"><?php echo esc_html( sprintf( /* translators: %s: amount. */ __( 'Refunded %s', 'wp-easycart' ), wp_strip_all_tags( $GLOBALS['currency']->get_currency_display( $wpec_payment['refunded'] ) ) ) ); ?></span>
										<?php } ?>
									</div>
								</div>
									<?php
								}
								?>
							</div>
								<?php
							}
							?>
						</div>
						<?php do_action( 'wp_easycart_admin_order_details_totals_content_end' ); ?>
					</div>
				</div>
			</div>

			<?php /* ---------- Activity: comments ( PRO ) and the order history ---------- */ ?>
			<div class="ecdv2-card ecodv2-card-activity ecodv2-o-activity">
				<div class="ecdv2-card-header">
					<h3 class="ecdv2-card-title"><?php esc_html_e( 'Activity', 'wp-easycart' ); ?></h3>
					<span class="ecdv2-card-hint"><?php esc_html_e( 'Team only, never shown to the customer', 'wp-easycart' ); ?></span>
				</div>
				<div class="ecdv2-card-body">
					<?php do_action( 'wp_easycart_ecv2_order_details_timeline_pre', $this->order ); ?>
					<div class="ecodv2-history-wrap" id="ecodv2_history_wrap">
						<?php do_action( 'wp_easycart_admin_order_details_left_content_end' ); ?>
						<div class="ecodv2-history-showall" id="ecodv2_history_showall">
							<button type="button" class="ecv2-btn ecv2-btn-sm" onclick="ecodv2_open_history_drawer(); return false;"><span class="dashicons dashicons-list-view" aria-hidden="true"></span> <?php esc_html_e( 'Show all activity', 'wp-easycart' ); ?> (<span id="ecodv2_history_total">0</span>)</button>
						</div>
					</div>
				</div>
			</div>
		</div>

		<?php /* ------------------------------ SIDEBAR ------------------------------ */ ?>
		<div class="ecodv2-side">
			<div class="ecodv2-side-inner">

				<?php /* ---------- Customer ---------- */ ?>
				<?php
				$edit_order_details_action = apply_filters( 'wp_easycart_admin_order_details_order_edit_action', 'show_pro_required' );
				$wpec_initials             = '';
				if ( '' !== trim( (string) $this->order->billing_first_name ) ) {
					$wpec_initials .= mb_strtoupper( mb_substr( trim( (string) $this->order->billing_first_name ), 0, 1 ) );
				}
				if ( '' !== trim( (string) $this->order->billing_last_name ) ) {
					$wpec_initials .= mb_strtoupper( mb_substr( trim( (string) $this->order->billing_last_name ), 0, 1 ) );
				}
				if ( '' === $wpec_initials && '' !== trim( (string) $this->order->user_email ) ) {
					$wpec_initials = mb_strtoupper( mb_substr( trim( (string) $this->order->user_email ), 0, 1 ) );
				}
				?>
				<div class="ecdv2-card ecodv2-card-customer ecodv2-o-customer">
					<div class="ecdv2-card-header">
						<h3 class="ecdv2-card-title"><?php esc_html_e( 'Customer', 'wp-easycart' ); ?></h3>
						<div class="ecdv2-card-header-actions">
							<button type="button" class="ecodv2-edit-link" id="ec_admin_order_details_edit" onclick="ecodv2_open_edit_drawer( 'customer', '<?php echo esc_attr( $edit_order_details_action ); ?>' ); return false;"><span class="dashicons dashicons-edit" aria-hidden="true"></span> <?php esc_html_e( 'Edit', 'wp-easycart' ); ?></button>
						</div>
					</div>
					<div class="ecdv2-card-body">
						<div id="ec_admin_order_details_user_id_content" class="ecodv2-block">
							<div class="ecodv2-user-card">
								<div class="ecodv2-avatar<?php echo $this->order->user_id ? '' : ' ecodv2-avatar-guest'; ?>" aria-hidden="true"><?php if ( '' !== $wpec_initials ) { echo esc_html( $wpec_initials ); } else { ?><span class="dashicons dashicons-admin-users"></span><?php } ?></div>
								<div class="ecodv2-user-main">
									<div class="ecodv2-user-topline">
										<span class="ecodv2-user-card-name"><?php echo esc_html( '' !== $wpec_customer_name ? $wpec_customer_name : __( 'Customer', 'wp-easycart' ) ); ?></span>
										<span class="ecodv2-user-account" id="ecodv2_user_account"><?php echo wp_easycart_admin_order_account_badge_html( $this->order->user_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the helper escapes every value it prints. ?></span>
									</div>
									<div class="ecodv2-contact-row">
										<span class="dashicons dashicons-email-alt ecodv2-contact-icon" aria-hidden="true"></span>
										<span class="ecodv2-contact-value ecodv2-user-card-email"><span id="ec_admin_order_details_user_email"><a href="mailto:<?php echo esc_attr( $this->order->user_email ); ?>"><?php echo esc_html( $this->order->user_email ); ?></a></span></span>
										<button type="button" class="ecodv2-copy-link ecodv2-contact-copy" data-copy-target="ec_admin_order_details_user_email" onclick="ecodv2_copy_from( this ); return false;" title="<?php esc_attr_e( 'Copy email', 'wp-easycart' ); ?>" aria-label="<?php esc_attr_e( 'Copy email', 'wp-easycart' ); ?>"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span></button>
									</div>
									<div class="ecodv2-contact-sub ecodv2-user-card-email"><span id="ec_admin_order_details_email_other"><?php if ( isset( $this->order->email_other ) && '' !== (string) $this->order->email_other ) { ?><a href="mailto:<?php echo esc_attr( $this->order->email_other ); ?>"><?php echo esc_html( $this->order->email_other ); ?></a><?php } ?></span></div>
									<?php
									$wpec_phone_raw = '' !== trim( (string) $this->order->billing_phone ) ? $this->order->billing_phone : $this->order->shipping_phone;
									$wpec_phone_cc  = '' !== trim( (string) $this->order->billing_phone ) ? $this->order->billing_country : $this->order->shipping_country;
									$wpec_phone     = wp_easycart_format_phone( $wpec_phone_raw, $wpec_phone_cc );
									?>
									<div class="ecodv2-contact-row<?php echo '' === trim( (string) $wpec_phone_raw ) ? ' is-empty' : ''; ?>">
										<span class="dashicons dashicons-phone ecodv2-contact-icon" aria-hidden="true"></span>
										<span class="ecodv2-contact-value"><span id="ec_admin_order_details_user_phone"><?php if ( '' !== trim( (string) $wpec_phone_raw ) ) { ?><a href="<?php echo esc_attr( $wpec_phone['href'] ); ?>"><?php echo esc_html( $wpec_phone['display'] ); ?></a><?php } ?></span></span>
										<button type="button" class="ecodv2-copy-link ecodv2-contact-copy" data-copy-target="ec_admin_order_details_user_phone" onclick="ecodv2_copy_from( this ); return false;" title="<?php esc_attr_e( 'Copy phone', 'wp-easycart' ); ?>" aria-label="<?php esc_attr_e( 'Copy phone', 'wp-easycart' ); ?>"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span></button>
									</div>
									<?php if ( class_exists( 'wp_easycart_subscribers' ) && '' !== trim( (string) $this->order->user_email ) && wp_easycart_subscribers::is_subscribed( (string) $this->order->user_email ) ) { ?>
									<div class="ecodv2-contact-row ecodv2-subscribed"><span class="dashicons dashicons-megaphone ecodv2-contact-icon" aria-hidden="true"></span><span class="ecodv2-contact-value"><?php esc_html_e( 'Subscribed to your emails', 'wp-easycart' ); ?></span></div>
									<?php } ?>
								</div>
							</div>
						</div>
						<?php
						do_action( 'wp_easycart_order_details_order_information' );
						if ( ! $wpec_is_pro_edit_forms ) {
							include EC_PLUGIN_DIRECTORY . '/admin/template/orders/orders/order-edit-contact.php';
						}
						?>
						<?php /* WP EasyCart PRO: lifetime stats and quick links. Refilled by ec_admin_ajax_update_order_user when the account changes. */ ?>
						<div class="ecodv2-customer-card-extra" id="ecodv2_customer_card_extra"><?php do_action( 'wp_easycart_ecv2_order_details_customer_card', $this->order ); ?></div>
					</div>
				</div>

				<?php /* ---------- Notes: the note pinned for the team, the note from the customer ---------- */ ?>
				<?php
				$wpec_pin        = trim( (string) $this->order->order_notes );
				$wpec_has_cnotes = ( '' !== trim( (string) $this->order->order_customer_notes ) );
				?>
				<div class="ecdv2-card ecodv2-card-notes ecodv2-o-notes" id="ecodv2_notes_card">
					<div class="ecdv2-card-header"><h3 class="ecdv2-card-title"><?php esc_html_e( 'Notes', 'wp-easycart' ); ?></h3></div>
					<div class="ecdv2-card-body ecodv2-notes">
						<?php /* Pinned note ( team only, ec_order.order_notes ). #order_notes stays: the order info save still posts it. */ ?>
						<div class="ecodv2-callout ecodv2-callout-yellow ecodv2-pin-strip" id="ecodv2_pin_strip"<?php if ( '' === $wpec_pin ) { ?> style="display:none;"<?php } ?>>
							<span class="dashicons dashicons-sticky" aria-hidden="true"></span>
							<div class="ecodv2-callout-body"><strong><?php esc_html_e( 'Pinned note', 'wp-easycart' ); ?> <em><?php esc_html_e( 'team only', 'wp-easycart' ); ?></em></strong><span class="ecodv2-pin-text" id="ecodv2_pin_text"><?php echo esc_html( $wpec_pin ); ?></span></div>
							<div class="ecodv2-callout-actions">
								<button type="button" class="ecodv2-edit-link" onclick="ecodv2_pin_edit(); return false;"><?php esc_html_e( 'Edit', 'wp-easycart' ); ?></button>
								<button type="button" class="ecodv2-edit-link" onclick="ecodv2_pin_save( true ); return false;"><?php esc_html_e( 'Unpin', 'wp-easycart' ); ?></button>
							</div>
						</div>
						<div class="ecodv2-pin-editor" id="ecodv2_pin_editor" style="display:none;">
							<label class="screen-reader-text" for="ecodv2_pin_input"><?php esc_html_e( 'Pinned note', 'wp-easycart' ); ?></label>
							<textarea id="ecodv2_pin_input" placeholder="<?php esc_attr_e( 'Something the whole team should see on this order…', 'wp-easycart' ); ?>"></textarea>
							<div class="ecodv2-pin-editor-foot">
								<span class="ecodv2-pin-hint"><?php esc_html_e( 'Only your team sees this note.', 'wp-easycart' ); ?></span>
								<button type="button" class="ecodv2-pin-unpin" id="ecodv2_pin_unpin"<?php if ( '' === $wpec_pin ) { ?> style="display:none;"<?php } ?> onclick="ecodv2_pin_save( true ); return false;"><?php esc_html_e( 'Unpin', 'wp-easycart' ); ?></button>
								<div class="ecodv2-drawer-foot-spacer"></div>
								<button type="button" class="ecv2-btn ecv2-btn-sm" onclick="ecodv2_pin_cancel(); return false;"><?php esc_html_e( 'Cancel', 'wp-easycart' ); ?></button>
								<button type="button" class="ecv2-btn ecv2-btn-primary ecv2-btn-sm" id="ecodv2_pin_save_btn" onclick="ecodv2_pin_save( false ); return false;"><?php esc_html_e( 'Pin note', 'wp-easycart' ); ?></button>
							</div>
						</div>
						<div class="ec_admin_initial_hide">
							<textarea name="order_notes" id="order_notes"><?php echo esc_textarea( $this->order->order_notes ); ?></textarea>
						</div>

						<?php /* The customer's note ( written at checkout, shown on their receipt ). The popover saves through ec_admin_ajax_edit_customer_notes. */ ?>
						<div class="ecodv2-callout ecodv2-callout-blue ecodv2-cnotes-anchor<?php echo $wpec_has_cnotes ? '' : ' is-empty'; ?>" id="ec_admin_order_details_customer_notes_content">
							<span class="dashicons dashicons-format-chat" aria-hidden="true"></span>
							<div class="ecodv2-callout-body"><strong><?php esc_html_e( 'Note from the customer', 'wp-easycart' ); ?> <em><?php esc_html_e( 'shown on their receipt', 'wp-easycart' ); ?></em></strong><span id="ec_admin_order_details_customer_notes" class="ecodv2-cnotes-text"><?php echo nl2br( esc_html( $this->order->order_customer_notes ) ); ?></span></div>
							<div class="ecodv2-callout-actions">
								<button type="button" class="ecodv2-edit-link ecodv2-cnotes-trigger" id="ec_admin_order_details_customer_notes_edit" onclick="ecodv2_open_cnotes_popover(); return false;"><?php esc_html_e( 'Edit', 'wp-easycart' ); ?></button>
							</div>
							<div id="ec_admin_order_details_customer_notes_empty_message" class="ecodv2-legacy-hidden"></div>
							<div class="ecodv2-cnotes-popover" id="ecodv2_cnotes_popover" role="dialog" aria-label="<?php esc_attr_e( 'Note for the customer', 'wp-easycart' ); ?>">
								<div class="ecodv2-cnotes-popover-head"><?php esc_html_e( 'Note for the customer', 'wp-easycart' ); ?></div>
								<div id="ec_admin_order_details_customer_notes_form" class="ecodv2-cnotes-form">
									<label class="screen-reader-text" for="order_customer_notes"><?php esc_html_e( 'Note for the customer', 'wp-easycart' ); ?></label>
									<textarea name="order_customer_notes" id="order_customer_notes" placeholder="<?php esc_attr_e( 'Note from or for the customer…', 'wp-easycart' ); ?>"><?php echo esc_textarea( $this->order->order_customer_notes ); ?></textarea>
								</div>
								<div class="ecodv2-cnotes-popover-foot">
									<span class="ecodv2-cnotes-hint"><span class="dashicons dashicons-visibility" aria-hidden="true"></span> <?php esc_html_e( 'The customer sees this note on their receipt and in their account.', 'wp-easycart' ); ?></span>
									<button type="button" class="ecv2-btn ecv2-btn-sm" onclick="ecodv2_cancel_cnotes(); return false;"><?php esc_html_e( 'Cancel', 'wp-easycart' ); ?></button>
									<button type="button" class="ecv2-btn ecv2-btn-primary ecv2-btn-sm" id="ec_admin_order_details_customer_notes_save" onclick="ecodv2_save_cnotes(); return false;"><?php esc_html_e( 'Save', 'wp-easycart' ); ?></button>
								</div>
							</div>
						</div>

						<?php /* Quiet ways to add either note when the order has none. */ ?>
						<div class="ecodv2-note-adds" id="ecodv2_pin_add">
							<button type="button" class="ecodv2-note-add" id="ecodv2_pin_add_btn"<?php if ( '' !== $wpec_pin ) { ?> hidden<?php } ?> onclick="ecodv2_pin_edit(); return false;"><span class="dashicons dashicons-sticky" aria-hidden="true"></span> <?php esc_html_e( 'Pin a note for the team', 'wp-easycart' ); ?></button>
							<button type="button" class="ecodv2-note-add ecodv2-cnotes-trigger" id="ecodv2_cnotes_add_btn"<?php if ( $wpec_has_cnotes ) { ?> hidden<?php } ?> onclick="ecodv2_open_cnotes_popover(); return false;"><span class="dashicons dashicons-format-chat" aria-hidden="true"></span> <?php esc_html_e( 'Add a note for the customer', 'wp-easycart' ); ?></button>
						</div>
					</div>
				</div>

				<?php /* ---------- Ship to ( + Bill to ) ---------- */ ?>
				<?php
				$edit_shipping_action = apply_filters( 'wp_easycart_admin_order_details_shipping_edit_action', 'show_pro_required' );
				$edit_billing_action  = apply_filters( 'wp_easycart_admin_order_details_billing_edit_action', 'show_pro_required' );
				$wpec_same_address    = wp_easycart_admin_order_screen::same_addresses( $this->order );
				$wpec_map_q           = rawurlencode( trim( $this->order->shipping_address_line_1 . ' ' . $this->order->shipping_city . ' ' . $this->order->shipping_state . ' ' . $this->order->shipping_zip . ' ' . $this->order->shipping_country_name ) );
				?>
				<?php do_action( 'wpeasycart_order_details_shipping_address_pre_ship_to', $this->order ); ?>
				<div class="ecdv2-card ecodv2-card-shipping ecodv2-o-shipto">
					<div class="ecdv2-card-header">
						<h3 class="ecdv2-card-title"><?php echo esc_html( $wpec_local_pickup ? __( 'Customer address', 'wp-easycart' ) : __( 'Ship to', 'wp-easycart' ) ); ?></h3>
						<div class="ecdv2-card-header-actions">
							<a class="ecodv2-copy-link" href="https://www.google.com/maps/search/?api=1&query=<?php echo esc_attr( $wpec_map_q ); ?>" target="_blank" rel="noopener noreferrer" title="<?php esc_attr_e( 'View on Google Maps', 'wp-easycart' ); ?>"><?php esc_html_e( 'Map', 'wp-easycart' ); ?></a>
							<button type="button" class="ecodv2-copy-link" onclick="ecodv2_copy_address( 'shipping', this ); return false;" title="<?php esc_attr_e( 'Copy address', 'wp-easycart' ); ?>"><?php esc_html_e( 'Copy', 'wp-easycart' ); ?></button>
							<button type="button" class="ecodv2-edit-link" id="ec_admin_order_shipping_edit_button" onclick="ecodv2_open_edit_drawer( 'shipping', '<?php echo esc_attr( $edit_shipping_action ); ?>' ); return false;"><span class="dashicons dashicons-edit" aria-hidden="true"></span> <?php esc_html_e( 'Edit', 'wp-easycart' ); ?></button>
						</div>
					</div>
					<div class="ecdv2-card-body">
						<div id="ec_admin_order_details_shipping_content" class="ecodv2-block ecodv2-address">
							<?php do_action( 'wp_easycart_admin_order_details_shipping_after_title', $this->order ); ?>
							<div id="ec_admin_order_details_shipping_name" class="ecodv2-address-name"><?php echo esc_html( trim( $this->order->shipping_first_name . ' ' . $this->order->shipping_last_name ) ); ?></div>
							<?php do_action( 'wp_easycart_admin_order_details_shipping_after_name', $this->order ); ?>
							<div id="ec_admin_order_details_shipping_company"><?php echo esc_html( $this->order->shipping_company_name ); ?></div>
							<?php do_action( 'wp_easycart_admin_order_details_shipping_after_company_name', $this->order ); ?>
							<div id="ec_admin_order_details_shipping_address1"><?php echo esc_html( $this->order->shipping_address_line_1 ); ?></div>
							<?php do_action( 'wp_easycart_admin_order_details_shipping_after_address1', $this->order ); ?>
							<div id="ec_admin_order_details_shipping_address2"><?php echo esc_html( $this->order->shipping_address_line_2 ); ?></div>
							<?php do_action( 'wp_easycart_admin_order_details_shipping_after_address2', $this->order ); ?>
							<div id="ec_admin_order_details_shipping_address3"><?php echo esc_html( trim( $this->order->shipping_city . ' ' . $this->order->shipping_state . ' ' . $this->order->shipping_zip ) ); ?></div>
							<?php do_action( 'wp_easycart_admin_order_details_shipping_after_city', $this->order ); ?>
							<div id="ec_admin_order_details_shipping_country"><?php echo esc_html( $this->order->shipping_country_name ); ?></div>
							<?php do_action( 'wp_easycart_admin_order_details_shipping_after_country_name', $this->order ); ?>
							<div id="ec_admin_order_details_shipping_phone"><?php $wpec_sp = wp_easycart_format_phone( $this->order->shipping_phone, $this->order->shipping_country ); echo esc_html( $wpec_sp['display'] ); ?></div>
							<?php do_action( 'wp_easycart_admin_order_details_shipping_after_phone', $this->order ); ?>
						</div>
						<?php
						do_action( 'wp_easycart_admin_order_details_shipping_content_end' );
						if ( ! $wpec_is_pro_edit_forms ) {
							include EC_PLUGIN_DIRECTORY . '/admin/template/orders/orders/order-edit-shipping.php';
						}
						?>

						<?php /* Bill to: one line when it is the same address; the block ( and its ids ) is always there for the saves. */ ?>
						<div class="ecodv2-billto<?php echo $wpec_same_address ? ' is-same' : ''; ?>" id="ecodv2_billto">
							<div class="ecodv2-billto-head">
								<span class="ecodv2-eyebrow"><?php esc_html_e( 'Bill to', 'wp-easycart' ); ?></span>
								<span class="ecodv2-billto-same"><?php esc_html_e( 'Same as shipping address', 'wp-easycart' ); ?></span>
								<button type="button" class="ecodv2-copy-link ecodv2-billto-copy" onclick="ecodv2_copy_address( 'billing', this ); return false;" title="<?php esc_attr_e( 'Copy address', 'wp-easycart' ); ?>"><?php esc_html_e( 'Copy', 'wp-easycart' ); ?></button>
								<button type="button" class="ecodv2-edit-link" id="ec_admin_order_billing_edit_button" onclick="ecodv2_open_edit_drawer( 'billing', '<?php echo esc_attr( $edit_billing_action ); ?>' ); return false;"><span class="dashicons dashicons-edit" aria-hidden="true"></span> <?php esc_html_e( 'Edit', 'wp-easycart' ); ?></button>
							</div>
							<div id="ec_admin_order_details_billing_content" class="ecodv2-block ecodv2-address">
								<?php do_action( 'wp_easycart_admin_order_details_billing_after_title', $this->order ); ?>
								<div id="ec_admin_order_details_billing_name" class="ecodv2-address-name"><?php echo esc_html( trim( $this->order->billing_first_name . ' ' . $this->order->billing_last_name ) ); ?></div>
								<?php do_action( 'wp_easycart_admin_order_details_billing_after_name', $this->order ); ?>
								<div id="ec_admin_order_details_billing_company"><?php echo esc_html( $this->order->billing_company_name ); ?></div>
								<?php do_action( 'wp_easycart_admin_order_details_billing_after_company_name', $this->order ); ?>
								<div id="ec_admin_order_details_billing_address1"><?php echo esc_html( $this->order->billing_address_line_1 ); ?></div>
								<?php do_action( 'wp_easycart_admin_order_details_billing_after_address1', $this->order ); ?>
								<div id="ec_admin_order_details_billing_address2"><?php echo esc_html( $this->order->billing_address_line_2 ); ?></div>
								<?php do_action( 'wp_easycart_admin_order_details_billing_after_address2', $this->order ); ?>
								<div id="ec_admin_order_details_billing_address3"><?php echo esc_html( trim( $this->order->billing_city . ' ' . $this->order->billing_state . ' ' . $this->order->billing_zip ) ); ?></div>
								<?php do_action( 'wp_easycart_admin_order_details_billing_after_city', $this->order ); ?>
								<div id="ec_admin_order_details_billing_country"><?php echo esc_html( $this->order->billing_country_name ); ?></div>
								<?php do_action( 'wp_easycart_admin_order_details_billing_after_country_name', $this->order ); ?>
								<div id="ec_admin_order_details_billing_phone"><?php $wpec_bp = wp_easycart_format_phone( $this->order->billing_phone, $this->order->billing_country ); echo esc_html( $wpec_bp['display'] ); ?></div>
								<?php do_action( 'wp_easycart_admin_order_details_billing_after_phone', $this->order ); ?>
							</div>
							<?php if ( '' !== trim( (string) $this->order->vat_registration_number ) ) { ?>
							<div class="ecodv2-kv"><span><?php esc_html_e( 'VAT number', 'wp-easycart' ); ?></span><span><?php echo esc_html( $this->order->vat_registration_number ); ?></span></div>
							<?php } ?>
							<?php
							do_action( 'wp_easycart_admin_order_details_billing_content_end' );
							if ( ! $wpec_is_pro_edit_forms ) {
								include EC_PLUGIN_DIRECTORY . '/admin/template/orders/orders/order-edit-billing.php';
							}
							?>
						</div>

						<?php /* Pickup ( preorder or restaurant ): when and where the customer collects the order. */ ?>
						<?php if ( $this->order->includes_preorder_items ) { ?>
						<div class="ecodv2-pickup" id="ec_admin_order_details_pickup_content">
							<span class="ecodv2-eyebrow"><?php esc_html_e( 'Pickup', 'wp-easycart' ); ?></span>
							<label class="screen-reader-text" for="ec_order_pickup_date"><?php esc_html_e( 'Pickup date', 'wp-easycart' ); ?></label>
							<input type="text" class="ec_admin_datepicker" id="ec_order_pickup_date" value="<?php
							$date_timestamp = strtotime( $this->order->pickup_date );
							if ( $date_timestamp > 0 ) {
								echo esc_attr( date( apply_filters( 'wp_easycart_pickup_date_placeholder_format', 'F d, Y' ), $date_timestamp ) );
							}
							?>" placeholder="<?php echo esc_attr__( 'Choose a Pick Up Date', 'wp-easycart' ); ?>" />
							<label class="screen-reader-text" for="ec_order_pickup_date_time"><?php esc_html_e( 'Pickup time', 'wp-easycart' ); ?></label>
							<select name="ec_order_pickup_date_time" id="ec_order_pickup_date_time"><?php $selected_pickup_date_time = ''; if ( isset( $this->order->pickup_date ) && '' != $this->order->pickup_date ) { $selected_pickup_date_time = date( 'H:i', $date_timestamp ); } ?>
								<option value=""<?php if ( '' == $selected_pickup_date_time ) { ?> selected="selected"<?php } ?>><?php echo esc_html( wp_easycart_language()->get_text( 'cart_payment_information', 'preorder_pickup_time_label' ) ); ?></option>
								<?php for ( $hour = 0; $hour < 24; $hour++ ) { ?>
								<option value="<?php echo esc_attr( date( 'H:i', strtotime( date( 'Y-m-d ' . $hour . ':00' ) ) ) ); ?>"<?php if ( date( 'H:i', strtotime( date( 'Y-m-d ' . $hour . ':00' ) ) ) == $selected_pickup_date_time ) { ?> selected="selected"<?php } ?>><?php echo esc_html( date( get_option( 'time_format' ), strtotime( date( 'Y-m-d ' . $hour . ':00' ) ) ) ); ?> - <?php echo esc_html( date( get_option( 'time_format' ), strtotime( date( 'Y-m-d ' . $hour . ':00' ) . ' + 1 hour' ) ) ); ?></option>
								<?php } ?>
							</select>
							<?php
							if ( get_option( 'ec_option_pickup_enable_locations' ) ) {
								$locations = $wpdb->get_results( 'SELECT * FROM ec_location ORDER BY location_label ASC' );
								if ( is_array( $locations ) && count( $locations ) > 0 ) {
									?>
							<label class="screen-reader-text" for="ec_order_location_id"><?php esc_html_e( 'Pickup location', 'wp-easycart' ); ?></label>
							<select class="select2" name="ec_order_location_id" id="ec_order_location_id">
								<option value="0"><?php echo esc_html__( 'Choose Pickup Location', 'wp-easycart' ); ?></option>
									<?php foreach ( $locations as $location ) { ?>
								<option value="<?php echo esc_attr( $location->location_id ); ?>"<?php selected( $location->location_id, $this->order->location_id ); ?>><?php echo esc_html( $location->location_label ); ?></option>
									<?php } ?>
							</select>
									<?php
								}
							}
							?>
						</div>
						<?php } ?>
						<?php if ( $this->order->includes_restaurant_type ) { ?>
						<div class="ecodv2-pickup" id="ec_admin_order_details_restaurant_content">
							<span class="ecodv2-eyebrow"><?php esc_html_e( 'Pickup', 'wp-easycart' ); ?></span>
							<label class="screen-reader-text" for="ec_order_pickup_time_date"><?php esc_html_e( 'Pickup date', 'wp-easycart' ); ?></label>
							<input type="text" class="ec_admin_datepicker" id="ec_order_pickup_time_date" value="<?php
							$pickup_time           = $this->order->pickup_time;
							$pickup_time_timestamp = strtotime( $pickup_time );
							if ( $pickup_time_timestamp > 0 ) {
								echo esc_attr( date( apply_filters( 'wp_easycart_pickup_date_placeholder_format', 'F d, Y' ), $pickup_time_timestamp ) );
							}
							?>" placeholder="<?php echo esc_attr__( 'Choose a Pick Up Date', 'wp-easycart' ); ?>" />
							<label class="screen-reader-text" for="ec_order_pickup_time_time"><?php esc_html_e( 'Pickup time', 'wp-easycart' ); ?></label>
							<select name="ec_order_pickup_time_time" id="ec_order_pickup_time_time">
								<?php
								$selected_pickup_time_time = '';
								if ( isset( $this->order->pickup_time ) && '' != $this->order->pickup_time ) {
									$pickup_time_minutes           = (int) date( 'i', $pickup_time_timestamp );
									$pickup_time_rounded_minutes   = round( $pickup_time_minutes / 5 ) * 5;
									$pickup_time_updated_timestamp = strtotime( date( 'Y-m-d H:', $pickup_time_timestamp ) . sprintf( '%02d:00', $pickup_time_rounded_minutes ) );
									$selected_pickup_time_time     = date( 'H:i', $pickup_time_updated_timestamp );
								}
								?>
								<option value=""<?php if ( '' == $selected_pickup_time_time ) { ?> selected="selected"<?php } ?>><?php echo esc_html( wp_easycart_language()->get_text( 'cart_payment_information', 'preorder_pickup_time_label' ) ); ?></option>
								<?php for ( $hour = 0; $hour < 24; $hour++ ) { ?>
									<?php for ( $minute = 0; $minute < 60; $minute = $minute + 5 ) { ?>
								<option value="<?php echo esc_attr( $hour . ':' . sprintf( '%02d', $minute ) ); ?>"<?php if ( $hour . ':' . sprintf( '%02d', $minute ) == $selected_pickup_time_time ) { ?> selected="selected"<?php } ?>><?php echo esc_html( date( get_option( 'time_format' ), strtotime( date( 'Y-m-d ' . sprintf( '%02d', $hour ) . ':' . sprintf( '%02d', $minute ) ) ) ) ); ?></option>
									<?php } ?>
								<?php } ?>
							</select>
						</div>
						<?php } ?>
						<?php if ( $this->order->includes_preorder_items || $this->order->includes_restaurant_type ) { ?>
						<script>
							jQuery( '.ec_admin_datepicker' ).datepicker( {
								dateFormat:"<?php echo esc_attr( apply_filters( 'wp_easycart_pickup_date_jquery_format', 'MM d, yy' ) ); ?>",
							} );
						</script>
						<?php } ?>
					</div>
				</div>

				<?php /* ---------- Documents: print or send each one ( the Print menu's documents; WP EasyCart PRO adds its PDFs ) ---------- */ ?>
				<?php
				$wpec_receipt_sent = wp_easycart_admin_order_screen::email_sent( (int) $this->order->order_id, 'order-receipt-email' );
				$wpec_shipped_sent = wp_easycart_admin_order_screen::email_sent( (int) $this->order->order_id, 'order-shipping-email' );
				$wpec_has_email    = '' !== trim( (string) $this->order->user_email );
				$wpec_doc_nonce    = wp_create_nonce( 'wp-easycart-bulk-orders' );
				?>
				<div class="ecdv2-card ecodv2-card-documents ecodv2-o-docs" id="ecodv2_documents_card">
					<div class="ecdv2-card-header"><h3 class="ecdv2-card-title"><?php esc_html_e( 'Documents', 'wp-easycart' ); ?></h3></div>
					<div class="ecdv2-card-body ecodv2-docs-list">
						<div class="ecodv2-doc-row">
							<span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
							<span class="ecodv2-doc-name"><?php esc_html_e( 'Packing slip', 'wp-easycart' ); ?></span>
							<a class="ecodv2-doc-act" href="<?php echo esc_url( wp_easycart_admin_order_screen::packing_slip_url( (int) $this->order->order_id ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Print', 'wp-easycart' ); ?><span class="screen-reader-text"> <?php esc_html_e( 'the packing slip', 'wp-easycart' ); ?></span></a>
						</div>
						<div class="ecodv2-doc-row">
							<span class="dashicons dashicons-media-text" aria-hidden="true"></span>
							<span class="ecodv2-doc-name"><?php esc_html_e( 'Receipt', 'wp-easycart' ); ?><?php if ( $wpec_receipt_sent ) { ?> <span class="ecodv2-doc-note"><?php echo esc_html( sprintf( /* translators: %s: date. */ __( 'Sent %s', 'wp-easycart' ), date_i18n( 'M j', $wpec_receipt_sent ) ) ); ?></span><?php } ?></span>
							<a class="ecodv2-doc-act" href="<?php echo esc_url( admin_url( 'admin.php?page=wp-easycart-orders&subpage=orders&bulk=' . (int) $this->order->order_id . '&ec_admin_form_action=print-receipt&wp_easycart_nonce=' . $wpec_doc_nonce ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Print', 'wp-easycart' ); ?><span class="screen-reader-text"> <?php esc_html_e( 'the receipt', 'wp-easycart' ); ?></span></a>
							<?php if ( $wpec_has_email ) { ?>
							<button type="button" class="ecodv2-doc-act" onclick="ecodv2_send_dialog( document.getElementById( 'ecodv2_send_email_link' ), 'receipt' ); return false;"><?php esc_html_e( 'Email', 'wp-easycart' ); ?><span class="screen-reader-text"> <?php esc_html_e( 'the receipt', 'wp-easycart' ); ?></span></button>
							<?php } ?>
						</div>
						<?php if ( $wpec_shipped_sent ) { ?>
						<div class="ecodv2-doc-row">
							<span class="dashicons dashicons-email-alt" aria-hidden="true"></span>
							<span class="ecodv2-doc-name"><?php esc_html_e( 'Shipped email', 'wp-easycart' ); ?> <span class="ecodv2-doc-note"><?php echo esc_html( sprintf( /* translators: %s: date. */ __( 'Sent %s', 'wp-easycart' ), date_i18n( 'M j', $wpec_shipped_sent ) ) ); ?></span></span>
							<?php if ( $wpec_has_email ) { ?>
							<button type="button" class="ecodv2-doc-act" onclick="ecodv2_send_dialog( document.getElementById( 'ecodv2_send_email_link' ), 'shipped' ); return false;"><?php esc_html_e( 'Send again', 'wp-easycart' ); ?><span class="screen-reader-text"> <?php esc_html_e( 'the shipped email', 'wp-easycart' ); ?></span></button>
							<?php } ?>
						</div>
						<?php } ?>
						<?php if ( '' !== $wpec_print_more ) { ?>
						<div class="ecodv2-docs-more"><?php echo $wpec_print_more; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- other plugins' own markup ( the Print menu's ). ?></div>
						<?php } ?>
					</div>
				</div>

				<?php
				/**
				 * Cards other plugins add to the sidebar, after Ship to ( WP EasyCart PRO's Invoice & PO, WP EasyCart Premium's
				 * accounting and fulfillment partner cards ). 6.0.2: runs after the Ship to card ( it ran before it ), so the
				 * customer's contact and address come first.
				 *
				 * @param object $order Order row.
				 */
				do_action( 'wpeasycart_order_details_shipping_address_pre', $this->order );
				?>

				<?php do_action( 'wp_easycart_admin_order_details_after_customer_notes', $this->order ); /* Checkout fields card */ ?>

				<?php /* ---------- Source ( where the order came from ) ---------- */ ?>
				<?php
				if ( class_exists( 'wp_easycart_order_source' ) ) {
					wp_easycart_order_source::admin_card( $this->order );
				}
				?>

				<?php /* ---------- Details ---------- */ ?>
				<?php $edit_order_details_bottom_action = apply_filters( 'wp_easycart_admin_order_details_order_bottom_edit_action', 'show_pro_required' ); ?>
				<div class="ecdv2-card ecodv2-card-meta ecodv2-o-details">
					<div class="ecdv2-card-header">
						<h3 class="ecdv2-card-title"><?php esc_html_e( 'Details', 'wp-easycart' ); ?></h3>
						<div class="ecdv2-card-header-actions">
							<button type="button" class="ecodv2-edit-link" id="ec_admin_order_details_edit_bottom" onclick="ecodv2_open_edit_drawer( 'details', '<?php echo esc_attr( $edit_order_details_bottom_action ); ?>' ); return false;"><span class="dashicons dashicons-edit" aria-hidden="true"></span> <?php esc_html_e( 'Edit', 'wp-easycart' ); ?></button>
						</div>
					</div>
					<div class="ecdv2-card-body">
						<div class="ecodv2-block ecodv2-details-rows" id="ec_admin_view_order_information_bottom">
							<div class="ecodv2-kv"><span><?php esc_html_e( 'Placed from', 'wp-easycart' ); ?></span><span id="ec_admin_order_details_ip_address"><?php echo '' !== (string) $this->order->order_ip_address ? esc_html( $this->order->order_ip_address ) : '<span class="ecodv2-muted">' . esc_html__( 'Not recorded', 'wp-easycart' ) . '</span>'; ?></span></div>
							<div class="ecodv2-kv"><span><?php esc_html_e( 'Terms', 'wp-easycart' ); ?></span><span id="ec_admin_order_details_agreed_to_terms"><?php echo esc_html( $this->order->agreed_to_terms ? __( 'Agreed to terms: Yes', 'wp-easycart' ) : __( 'Agreed to terms: No', 'wp-easycart' ) ); ?></span></div>
						</div>
						<?php do_action( 'wp_easycart_order_details_order_bottom_information' ); ?>
					</div>
				</div>
			</div>
		</div>
	</div>

	<?php
	/* ============ Fulfill ( 6.0.2: one flow for every order ) ============
	   Step 1: how the label is made ( a connected service, a carrier's site, or skip when the label exists ). Step 2: the
	   tracking for each package ( or one number when the order has no packages ), Mark as shipped and Email the customer,
	   the same choices every time. Opened by the next step panel ( Make a label, Add tracking for each package ), a package's
	   Add tracking and Find. The carriers are the Ship form's ( wp_easycart_admin_order_screen::label_carriers() ). */
	$wpec_carrier_defs   = $wpec_carriers['defs'];
	$wpec_suggested      = $wpec_carriers['suggested'];
	$wpec_shippo_on      =( function_exists( 'wp_easycart_shippo' ) && '' !== (string) wp_easycart_shippo()->get_setting( 'api_key' ) );
	$wpec_shipstation_on = apply_filters( 'wp_easycart_ecv2_shipstation_active', class_exists( 'wp_easycart_shipstation' ) );
	$wpec_stamps_on      = apply_filters( 'wp_easycart_ecv2_stamps_active', class_exists( 'wp_easycart_stamps' ) );
	/* Filter wp_easycart_ecv2_label_services ( 6.0.3: asked once per order by wp_easycart_admin_order_screen::label_services(), which
	   the Ship panel's key label button shares ). */
	$wpec_label_rows     = wp_easycart_admin_order_screen::label_services( $this->order );
	$wpec_shippo_on      = $wpec_shippo_on || isset( $wpec_label_rows['shippo'] );
	$wpec_stamps_on      = $wpec_stamps_on || isset( $wpec_label_rows['stamps'] );
	$wpec_shipstation_on = $wpec_shipstation_on || isset( $wpec_label_rows['shipstation'] );
	$wpec_shipping_name  = trim( (string) $this->order->shipping_first_name . ' ' . (string) $this->order->shipping_last_name );
	$wpec_ship_to        = trim( $wpec_shipping_name . ' · ' . $this->order->shipping_address_line_1 . ( '' !== (string) $this->order->shipping_address_line_2 ? ' ' . $this->order->shipping_address_line_2 : '' ) . ', ' . $this->order->shipping_city . ' ' . $this->order->shipping_state . ' ' . $this->order->shipping_zip . ', ' . $this->order->shipping_country_name . ( '' !== (string) $this->order->shipping_phone ? ' · ' . $this->order->shipping_phone : '' ) );
	$wpec_package        = trim( $this->order->order_weight . ' — ' . sprintf( _n( '%d item', '%d items', $item_count, 'wp-easycart' ), $item_count ) );
	$wpec_label_pkgs     = wp_easycart_admin_order_screen::label_rows( $this->order );
	$wpec_label_open     = 0;
	foreach ( $wpec_label_pkgs as $wpec_lp ) {
		if ( empty( $wpec_lp['done'] ) ) {
			++$wpec_label_open;
		}
	}
	$wpec_label_gift      = ( class_exists( 'wp_easycart_order_gift' ) && method_exists( 'wp_easycart_order_gift', 'for_order' ) ) ? wp_easycart_order_gift::for_order( $this->order ) : null;
	?>
	<div class="ecodv2-label-backdrop" id="ecodv2_label_backdrop" onclick="ecodv2_label_popup_close(); return false;"></div>
	<div class="ecodv2-label-popup" id="ecodv2_label_popup" role="dialog" aria-modal="true" aria-labelledby="ecodv2_label_title" data-ecodv2-dialog>
		<div class="ecodv2-label-head">
			<h3 id="ecodv2_label_title"><?php esc_html_e( 'Fulfill order', 'wp-easycart' ); ?></h3>
			<span class="ecodv2-label-ref"><?php esc_html_e( 'Order', 'wp-easycart' ); ?> #<?php echo esc_html( $this->order->order_id ); ?><?php if ( '' !== (string) $this->order->order_weight && '0.000' !== (string) $this->order->order_weight ) { ?> &middot; <?php echo esc_html( $this->order->order_weight ); ?><?php } ?></span>
			<button type="button" class="ecodv2-drawer-x" onclick="ecodv2_label_popup_close(); return false;" aria-label="<?php esc_attr_e( 'Close', 'wp-easycart' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
		</div>
		<div class="ecodv2-label-body">
			<?php if ( $wpec_label_gift ) { ?>
			<p class="ecodv2-label-gift"><span class="ecodv2-label-gift-icon" aria-hidden="true">&#127873;</span> <span><strong><?php esc_html_e( 'Gift order.', 'wp-easycart' ); ?></strong> <?php echo esc_html( wp_easycart_order_gift::slip_hides_prices( (int) $this->order->order_id ) ? __( 'Pack it with the gift packing slip: no prices, and the gift message is printed on it.', 'wp-easycart' ) : __( 'Its packing slip shows prices: choose a gift profile on Settings › Documents before printing it.', 'wp-easycart' ) ); ?></span></p>
			<?php } ?>
			<ol class="ecodv2-label-stepper">
				<li class="is-on" data-label-stepnav="1"><span class="ecodv2-label-stepnum">1</span> <?php esc_html_e( 'Make the label', 'wp-easycart' ); ?></li>
				<li data-label-stepnav="2"><span class="ecodv2-label-stepnum">2</span> <span class="ecodv2-label-stepname"><?php echo esc_html( count( $wpec_label_pkgs ) > 1 ? __( 'Tracking for each package', 'wp-easycart' ) : __( 'Tracking and email', 'wp-easycart' ) ); ?></span></li>
			</ol>
			<div class="ecodv2-label-step" data-label-step="1">
				<span class="ecodv2-eyebrow"><?php esc_html_e( 'Connected services', 'wp-easycart' ); ?></span>
				<div class="ecodv2-label-services">
					<?php foreach ( $wpec_label_rows as $wpec_label_row ) { ?>
						<?php
						if ( ! is_array( $wpec_label_row ) || empty( $wpec_label_row['name'] ) ) {
							continue;
						}
						$wpec_label_row = wp_parse_args( $wpec_label_row, array( 'sub' => '', 'logo' => '', 'color' => '#6b7280', 'cta' => '', 'onclick' => '', 'url' => '', 'integrated' => true ) );
						?>
					<a class="ecodv2-svc<?php echo $wpec_label_row['integrated'] ? ' ecodv2-svc-integrated' : ''; ?>" href="<?php echo esc_url( '' !== $wpec_label_row['url'] ? $wpec_label_row['url'] : '#' ); ?>"<?php if ( '' !== $wpec_label_row['onclick'] ) { ?> onclick="<?php echo esc_attr( $wpec_label_row['onclick'] ); ?>"<?php } elseif ( '' !== $wpec_label_row['url'] ) { ?> target="_blank" rel="noopener noreferrer"<?php } ?>>
						<span class="ecodv2-svc-logo" style="background:<?php echo esc_attr( sanitize_hex_color( $wpec_label_row['color'] ) ? $wpec_label_row['color'] : '#6b7280' ); ?>;"><?php echo esc_html( $wpec_label_row['logo'] ); ?></span>
						<span><span class="ecodv2-svc-name"><?php echo esc_html( $wpec_label_row['name'] ); ?></span><br><span class="ecodv2-svc-sub"><?php echo esc_html( $wpec_label_row['sub'] ); ?></span></span>
						<span class="ecodv2-svc-cta"><?php echo esc_html( $wpec_label_row['cta'] ); ?></span>
					</a>
					<?php } ?>
					<?php if ( $wpec_shippo_on && ! isset( $wpec_label_rows['shippo'] ) ) { ?>
					<a class="ecodv2-svc ecodv2-svc-integrated" href="https://apps.goshippo.com/orders" target="_blank" rel="noopener noreferrer">
						<span class="ecodv2-svc-logo" style="background:#0aa143;">Sh</span>
						<span><span class="ecodv2-svc-name">Shippo</span><br><span class="ecodv2-svc-sub"><?php esc_html_e( 'This order is already in your Shippo dashboard — all carriers', 'wp-easycart' ); ?></span></span>
						<span class="ecodv2-svc-cta"><?php esc_html_e( 'Integrated', 'wp-easycart' ); ?> &#10003; &nbsp;<?php esc_html_e( 'Create label', 'wp-easycart' ); ?> &#8599;</span>
					</a>
					<?php } ?>
					<?php if ( $wpec_stamps_on && ! isset( $wpec_label_rows['stamps'] ) && ( ! defined( 'WP_EASYCART_STAMPS_VERSION' ) || version_compare( WP_EASYCART_STAMPS_VERSION, '2.0.0', '<' ) ) ) { ?>
					<a class="ecodv2-svc ecodv2-svc-integrated" href="<?php echo esc_url( admin_url( 'admin.php?page=wpec-stamps-orders&ec_order_id=' . (int) $this->order->order_id . '&label_option=verify-address' ) ); ?>">
						<span class="ecodv2-svc-logo" style="background:#0b6e3f;">St</span>
						<span><span class="ecodv2-svc-name">Stamps.com</span><br><span class="ecodv2-svc-sub"><?php esc_html_e( 'USPS labels — opens the Stamps.com label steps for this order', 'wp-easycart' ); ?></span></span>
						<span class="ecodv2-svc-cta"><?php esc_html_e( 'Integrated', 'wp-easycart' ); ?> &#10003; &nbsp;<?php esc_html_e( 'Create label', 'wp-easycart' ); ?> &rarr;</span>
					</a>
					<?php } ?>
					<?php if ( $wpec_shipstation_on && ! isset( $wpec_label_rows['shipstation'] ) ) { ?>
					<a class="ecodv2-svc" href="https://ship.shipstation.com/orders" target="_blank" rel="noopener noreferrer">
						<span class="ecodv2-svc-logo" style="background:#12a5c6;">SS</span>
						<span><span class="ecodv2-svc-name">ShipStation</span><br><span class="ecodv2-svc-sub"><?php esc_html_e( 'All carriers — opens ShipStation in a new tab', 'wp-easycart' ); ?></span></span>
						<span class="ecodv2-svc-cta"><?php esc_html_e( 'Open', 'wp-easycart' ); ?> &#8599;</span>
					</a>
					<?php } ?>
					<?php if ( class_exists( 'wp_easycart_admin_extensions' ) ) { ?>
						<?php wp_easycart_admin_extensions::print_label_services( array( 'shipstation' => (bool) $wpec_shipstation_on, 'stamps' => (bool) $wpec_stamps_on, 'shippo' => (bool) $wpec_shippo_on ), $this->order ); ?>
					<?php } elseif ( ! $wpec_shippo_on && ! $wpec_stamps_on && ! $wpec_shipstation_on ) { ?>
					<a class="ecodv2-svc ecodv2-svc-install" href="<?php echo esc_url( apply_filters( 'wp_easycart_ecv2_extensions_url', 'https://www.wpeasycart.com/wordpress-shopping-cart-extensions/' ) ); ?>" target="_blank" rel="noopener noreferrer">
						<span class="ecodv2-svc-logo" style="background:#9ca3af;">&#65291;</span>
						<span><span class="ecodv2-svc-name"><?php esc_html_e( 'Shippo, Stamps.com or ShipStation', 'wp-easycart' ); ?></span><br><span class="ecodv2-svc-sub"><?php esc_html_e( 'Print labels without leaving EasyCart, or manage all carriers in one place', 'wp-easycart' ); ?></span></span>
						<span class="ecodv2-svc-cta"><?php esc_html_e( 'View extensions', 'wp-easycart' ); ?> &#8599;</span>
					</a>
					<?php } ?>
				</div>

				<span class="ecodv2-eyebrow"><?php esc_html_e( 'Or make it at the carrier', 'wp-easycart' ); ?></span>
				<div class="ecodv2-label-carriers">
					<?php foreach ( $wpec_carrier_defs as $wpec_ck => $wpec_cd ) { ?>
					<a class="ecodv2-label-carrier<?php echo $wpec_ck === $wpec_suggested ? ' is-suggested' : ''; ?>" href="<?php echo esc_url( $wpec_cd['url'] ); ?>" target="_blank" rel="noopener noreferrer" data-carrier="<?php echo esc_attr( $wpec_cd['label'] ); ?>">
						<?php if ( $wpec_ck === $wpec_suggested ) { ?><span class="ecodv2-label-sug"><?php esc_html_e( 'Suggested', 'wp-easycart' ); ?></span><?php } ?>
						<span class="ecodv2-label-carrier-name"><?php echo esc_html( $wpec_cd['label'] ); ?></span>
						<span class="ecodv2-label-carrier-sub"><?php echo esc_html( $wpec_cd['sub'] ); ?> &#8599;</span>
					</a>
					<?php } ?>
				</div>

				<div class="ecodv2-label-helper">
					<span class="ecodv2-label-line"><b><?php esc_html_e( 'Ship to', 'wp-easycart' ); ?></b><span class="ecodv2-label-copytext"><?php echo esc_html( $wpec_ship_to ); ?></span></span>
					<button type="button" class="ecodv2-copy-link" onclick="ecodv2_copy_text( this.parentNode.querySelector( '.ecodv2-label-copytext' ).textContent, this ); return false;"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span> <?php esc_html_e( 'Copy', 'wp-easycart' ); ?></button>
					<span class="ecodv2-label-line"><b><?php esc_html_e( 'Package', 'wp-easycart' ); ?></b><?php echo esc_html( $wpec_package ); ?></span>
					<button type="button" class="ecodv2-copy-link" onclick="ecodv2_copy_text( '<?php echo esc_js( (string) $this->order->order_weight ); ?>', this ); return false;"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span> <?php esc_html_e( 'Copy', 'wp-easycart' ); ?></button>
				</div>

				<div class="ecodv2-label-foot">
					<span class="ecodv2-label-foot-hint"><?php esc_html_e( 'Already have the label, or shipping without one? Go on to the tracking.', 'wp-easycart' ); ?></span>
					<button type="button" class="ecv2-btn ecv2-btn-primary" data-label-go="2"><?php esc_html_e( 'Add tracking', 'wp-easycart' ); ?> &rarr;</button>
				</div>
			</div>

			<div class="ecodv2-label-step" data-label-step="2" hidden>
				<?php
				/* 6.0.2 bug round 6: the rows are drawn by label_tracking_html(), and drawn again after the packages change
				   ( ecodv2_screen_refresh() ), so a package's Add tracking always opens them as they are. */
				$wpec_label_nothing = ( $wpec_label_pkgs && 0 === $wpec_label_open );
				?>
				<div class="ecodv2-label-rows" id="ecodv2_label_rows"><?php echo wp_easycart_admin_order_screen::label_tracking_html( $this->order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- label_tracking_html() escapes every value. ?></div>
				<p class="ecodv2-label-status" id="ecodv2_label_status" role="status" aria-live="polite"></p>
				<div class="ecodv2-label-foot">
					<button type="button" class="ecv2-btn" data-label-go="1">&larr; <?php esc_html_e( 'Back', 'wp-easycart' ); ?></button>
					<button type="button" class="ecv2-btn ecv2-btn-primary" id="ecodv2_label_save_all" onclick="ecodv2_fulfill_save(); return false;" data-busy-text="<?php esc_attr_e( 'Saving…', 'wp-easycart' ); ?>"<?php if ( $wpec_label_nothing ) { ?> hidden<?php } ?>><?php esc_html_e( 'Save and fulfill', 'wp-easycart' ); ?></button>
				</div>
			</div>
		</div>
	</div>

	<?php /* ============ Edit Line Item ( moves WP EasyCart PRO's hidden fields for the line in; Save runs its save ) ============ */ ?>
	<div class="ecodv2-line-backdrop" id="ecodv2_line_backdrop" onclick="ecodv2_line_modal_cancel(); return false;"></div>
	<div class="ecodv2-line-modal" id="ecodv2_line_modal" role="dialog" aria-modal="true" aria-labelledby="ecodv2_line_modal_title" data-ecodv2-dialog>
		<div class="ecodv2-line-modal-head">
			<h3 id="ecodv2_line_modal_title"><?php esc_html_e( 'Edit line item', 'wp-easycart' ); ?></h3>
			<span class="ecodv2-line-modal-chips" id="ecodv2_line_modal_chips"></span>
			<button type="button" class="ecodv2-drawer-x" onclick="ecodv2_line_modal_cancel(); return false;" aria-label="<?php esc_attr_e( 'Close', 'wp-easycart' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
		</div>
		<div class="ecodv2-line-modal-body" id="ecodv2_line_modal_body"></div>
		<div class="ecodv2-line-modal-foot">
			<button type="button" class="ecodv2-line-remove" id="ecodv2_line_modal_remove"><span class="dashicons dashicons-trash" aria-hidden="true"></span> <?php esc_html_e( 'Remove line', 'wp-easycart' ); ?></button>
			<div class="ecodv2-drawer-foot-spacer"></div>
			<button type="button" class="ecv2-btn" onclick="ecodv2_line_modal_cancel(); return false;"><?php esc_html_e( 'Cancel', 'wp-easycart' ); ?></button>
			<button type="button" class="ecv2-btn ecv2-btn-primary" id="ecodv2_line_modal_save"><?php esc_html_e( 'Save line', 'wp-easycart' ); ?></button>
		</div>
	</div>

	<?php /* Status change confirm ( Refunded, Cancelled, Partial refund: they change the status only ). Filled by the page script. */ ?>
	<div class="ecodv2-confirm-backdrop" id="ecodv2_confirm_backdrop" hidden></div>
	<div class="ecodv2-confirm" id="ecodv2_confirm" role="alertdialog" aria-modal="true" aria-labelledby="ecodv2_confirm_title" aria-describedby="ecodv2_confirm_body" hidden data-ecodv2-dialog>
		<h3 id="ecodv2_confirm_title"></h3>
		<p id="ecodv2_confirm_body"></p>
		<div class="ecodv2-confirm-actions">
			<button type="button" class="ecv2-btn ecodv2-confirm-alt" id="ecodv2_confirm_alt" hidden></button>
			<div class="ecodv2-drawer-foot-spacer"></div>
			<button type="button" class="ecv2-btn" id="ecodv2_confirm_no"></button>
			<button type="button" class="ecv2-btn ecv2-btn-primary" id="ecodv2_confirm_yes"></button>
		</div>
	</div>

	<?php /* Full activity history drawer — a copy of the inline timeline. */ ?>
	<div class="ecodv2-hdrawer-backdrop" id="ecodv2_history_backdrop" onclick="ecodv2_close_history_drawer(); return false;"></div>
	<div class="ecodv2-hdrawer" id="ecodv2_history_drawer" role="dialog" aria-modal="true" aria-labelledby="ecodv2_history_drawer_title" data-ecodv2-dialog>
		<div class="ecodv2-hdrawer-head">
			<h3 id="ecodv2_history_drawer_title"><?php esc_html_e( 'Activity', 'wp-easycart' ); ?></h3>
			<span class="ecodv2-hdrawer-count" id="ecodv2_history_drawer_count"></span>
			<button type="button" class="ecodv2-hdrawer-x" onclick="ecodv2_close_history_drawer(); return false;" aria-label="<?php esc_attr_e( 'Close', 'wp-easycart' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
		</div>
		<div class="ecodv2-hdrawer-body ecodv2-history-wrap" id="ecodv2_history_drawer_body"></div>
	</div>

	<?php /* ============ Edit drawer: every correction to the order, one section at a time ============ */ ?>
	<div class="ecodv2-drawer-backdrop" id="ecodv2_edit_backdrop" onclick="ecodv2_close_edit_drawer(); return false;"></div>
	<div class="ecodv2-drawer" id="ecodv2_edit_drawer" role="dialog" aria-modal="true" aria-labelledby="ecodv2_edit_drawer_title" data-ecodv2-dialog>
		<div class="ecodv2-drawer-head">
			<h3 id="ecodv2_edit_drawer_title"><?php echo esc_html( sprintf( __( 'Edit order #%d', 'wp-easycart' ), (int) $this->order->order_id ) ); ?></h3>
			<span class="ecodv2-drawer-dirty" id="ecodv2_drawer_dirty">&bull; <?php esc_html_e( 'Unsaved changes', 'wp-easycart' ); ?></span>
			<button type="button" class="ecodv2-drawer-x" onclick="ecodv2_close_edit_drawer(); return false;" aria-label="<?php esc_attr_e( 'Close', 'wp-easycart' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
		</div>
		<div class="ecodv2-drawer-jump" id="ecodv2_drawer_jump">
			<a href="#" data-section="customer" onclick="ecodv2_drawer_jump( 'customer' ); return false;"><?php esc_html_e( 'Customer', 'wp-easycart' ); ?></a>
			<a href="#" data-section="shipping" onclick="ecodv2_drawer_jump( 'shipping' ); return false;"><?php esc_html_e( 'Shipping address', 'wp-easycart' ); ?></a>
			<a href="#" data-section="billing" onclick="ecodv2_drawer_jump( 'billing' ); return false;"><?php esc_html_e( 'Billing address', 'wp-easycart' ); ?></a>
			<a href="#" data-section="fulfillment" onclick="ecodv2_drawer_jump( 'fulfillment' ); return false;"><?php esc_html_e( 'Shipping details', 'wp-easycart' ); ?></a>
			<a href="#" data-section="codes" onclick="ecodv2_drawer_jump( 'codes' ); return false;"><?php esc_html_e( 'Codes', 'wp-easycart' ); ?></a>
			<a href="#" data-section="details" onclick="ecodv2_drawer_jump( 'details' ); return false;"><?php esc_html_e( 'Details', 'wp-easycart' ); ?></a>
		</div>
		<div class="ecodv2-drawer-body" id="ecodv2_drawer_body">
			<div class="ecodv2-drawer-sec" id="ecodv2_sec_customer" data-section="customer">
				<span class="ecodv2-eyebrow"><?php esc_html_e( 'Customer', 'wp-easycart' ); ?></span>
				<div class="ecodv2-field ecodv2-field-wide ecodv2-drawer-account">
					<label for="ec_order_user_id"><?php esc_html_e( 'Customer account', 'wp-easycart' ); ?></label>
					<select id="ec_order_user_id" class="select2" style="width:100% !important;"><?php if ( $this->order->user_id ) { ?>
						<option value="<?php echo esc_attr( $this->order->user_id ); ?>" selected="selected"><?php echo esc_html( $this->order->last_name ); ?>, <?php echo esc_html( $this->order->first_name ); ?> (<?php echo esc_html( $this->order->user_id ); ?>)</option>
					<?php } else { ?>
						<option value="0" selected="selected"><?php esc_html_e( 'Guest', 'wp-easycart' ); ?></option>
					<?php } ?></select>
					<p class="ecodv2-field-note"><?php esc_html_e( 'Changing the account saves at once.', 'wp-easycart' ); ?></p>
					<div class="ecodv2-account-status" id="ecodv2_account_status" role="status" aria-live="polite" hidden data-busy-text="<?php esc_attr_e( 'Updating the customer account…', 'wp-easycart' ); ?>" data-error-text="<?php esc_attr_e( 'The customer account could not be updated. Please try again.', 'wp-easycart' ); ?>"></div>
				</div>
				<div class="ecodv2-sec-host" data-form-id="ec_admin_edit_order_information"></div>
			</div>
			<div class="ecodv2-drawer-sec" id="ecodv2_sec_shipping" data-section="shipping">
				<span class="ecodv2-eyebrow"><?php esc_html_e( 'Shipping address', 'wp-easycart' ); ?></span>
				<div class="ecodv2-sec-tools">
					<button type="button" class="ecodv2-tool-btn" onclick="ecodv2_copy_from_billing(); return false;"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span> <?php esc_html_e( 'Copy from billing', 'wp-easycart' ); ?></button>
					<span class="ecodv2-copied-ok" id="ecodv2_copied_ok"><?php esc_html_e( 'Copied', 'wp-easycart' ); ?> &#10003;</span>
				</div>
				<div class="ecodv2-sec-host" data-form-id="ec_admin_order_details_shipping_form"></div>
			</div>
			<div class="ecodv2-drawer-sec" id="ecodv2_sec_billing" data-section="billing">
				<span class="ecodv2-eyebrow"><?php esc_html_e( 'Billing address', 'wp-easycart' ); ?></span>
				<div class="ecodv2-sec-host" data-form-id="ec_admin_order_details_billing_form"></div>
			</div>
			<div class="ecodv2-drawer-sec" id="ecodv2_sec_fulfillment" data-section="fulfillment">
				<span class="ecodv2-eyebrow"><?php esc_html_e( 'Shipping details', 'wp-easycart' ); ?></span>
				<div id="ec_admin_order_details_shipping_method_form" class="ecodv2-form ec_admin_initial_hide">
					<div class="ecv2-btn ecodv2-visually-hidden" id="ec_admin_order_details_shipping_method_save"></div>
					<div class="ecodv2-grid">
						<div class="ecodv2-field">
							<label for="use_expedited_shipping"><?php esc_html_e( 'Speed', 'wp-easycart' ); ?></label>
							<select id="use_expedited_shipping" name="use_expedited_shipping">
								<option value="0"<?php selected( (string) wp_easycart_admin_orders()->order_details->order->use_expedited_shipping, '0' ); ?>><?php esc_html_e( 'Standard shipping', 'wp-easycart' ); ?></option>
								<option value="1"<?php selected( (string) wp_easycart_admin_orders()->order_details->order->use_expedited_shipping, '1' ); ?>><?php esc_html_e( 'Expedited shipping', 'wp-easycart' ); ?></option>
							</select>
						</div>
						<div class="ecodv2-field">
							<label for="shipping_method"><?php esc_html_e( 'Shipping method', 'wp-easycart' ); ?></label>
							<input type="text" placeholder="<?php esc_attr_e( 'Shipping method', 'wp-easycart' ); ?>" id="shipping_method" name="shipping_method" value="<?php echo esc_attr( wp_easycart_admin_orders()->order_details->order->shipping_method ); ?>" />
						</div>
						<div class="ecodv2-field">
							<label for="shipping_carrier"><?php esc_html_e( 'Carrier', 'wp-easycart' ); ?></label>
							<input type="text" placeholder="<?php esc_attr_e( 'Carrier', 'wp-easycart' ); ?>" id="shipping_carrier" name="shipping_carrier" value="<?php echo esc_attr( wp_easycart_admin_orders()->order_details->order->shipping_carrier ); ?>" />
						</div>
						<div class="ecodv2-field">
							<label for="tracking_number"><?php esc_html_e( 'Tracking number', 'wp-easycart' ); ?></label>
							<input type="text" placeholder="<?php esc_attr_e( 'Tracking number', 'wp-easycart' ); ?>" id="tracking_number" name="tracking_number" value="<?php echo esc_attr( wp_easycart_admin_orders()->order_details->order->tracking_number ); ?>" />
						</div>
					</div>
					<p class="ecodv2-field-note"><?php esc_html_e( 'To ship the order, use Fulfill: it records the tracking, marks the order shipped and emails the customer. Change these for corrections.', 'wp-easycart' ); ?></p>
				</div>
				<div class="ecodv2-drawer-shipment">
					<span class="ecodv2-eyebrow"><?php esc_html_e( 'Shipment', 'wp-easycart' ); ?></span>
					<div class="ecodv2-grid ecodv2-drawer-shipment-fields">
						<?php do_action( 'wp_easycart_admin_orders_details_shipment' ); ?>
					</div>
				</div>
			</div>
			<div class="ecodv2-drawer-sec" id="ecodv2_sec_codes" data-section="codes">
				<span class="ecodv2-eyebrow"><?php esc_html_e( 'Coupon and gift card codes', 'wp-easycart' ); ?></span>
				<div class="ecodv2-form ecodv2-codes-form" id="ecodv2_codes_form">
					<div class="ecv2-btn ecodv2-visually-hidden" id="ecodv2_codes_save"></div>
					<div class="ecodv2-payment-meta-fields">
						<?php do_action( 'wp_easycart_ecv2_order_details_payment_meta' ); ?>
					</div>
					<p class="ecodv2-field-note"><?php esc_html_e( 'The codes recorded on the order. Changing them does not change the discount: use Edit totals for that.', 'wp-easycart' ); ?></p>
				</div>
			</div>
			<div class="ecodv2-drawer-sec" id="ecodv2_sec_details" data-section="details">
				<span class="ecodv2-eyebrow"><?php esc_html_e( 'Details', 'wp-easycart' ); ?></span>
				<div class="ecodv2-sec-host" data-form-id="ec_admin_edit_order_information_bottom"></div>
			</div>
		</div>
		<div class="ecodv2-drawer-foot">
			<span class="ecodv2-drawer-hint"><?php esc_html_e( 'Esc closes. Changed sections save together.', 'wp-easycart' ); ?></span>
			<div class="ecodv2-drawer-foot-spacer"></div>
			<button type="button" class="ecv2-btn" onclick="ecodv2_close_edit_drawer(); return false;"><?php esc_html_e( 'Cancel', 'wp-easycart' ); ?></button>
			<button type="button" class="ecv2-btn ecv2-btn-primary" id="ecodv2_drawer_save" disabled onclick="ecodv2_drawer_save(); return false;"><?php esc_html_e( 'Save changes', 'wp-easycart' ); ?></button>
		</div>
	</div>

	<?php /* WP EasyCart PRO modals ( tags, refund, fulfill, pickup ). */ ?>
	<?php do_action( 'wp_easycart_ecv2_order_details_modals', $this->order ); ?>
</div>
