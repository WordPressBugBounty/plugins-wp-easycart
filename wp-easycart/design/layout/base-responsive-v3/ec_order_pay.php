<?php
/**
 * Pay for an order ( the pay link of an unpaid order: cart page, ec_page=invoice ).
 *
 * Rendered by wp_easycart_order_pay::render_page() with: $order ( ec_order row with is_approved, or null ), $state
 * ( payable | paid | pending | closed | missing ), $notice ( '' | success | pending | error, after a redirect back from
 * Stripe ) and $pay_methods ( stripe, square, paypal, manual: what this store can take here ). The customer can change the
 * billing address and pay; everything else is the order as it stands. Copy this file to your wp-easycart-data layout folder
 * to customise it; the file name must stay the same.
 *
 * @since 6.0.2
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$ec_pay_lang   = wp_easycart_language();
$ec_pay_cur    = $GLOBALS['currency'];
$ec_pay_accent = sanitize_hex_color( (string) get_option( 'ec_option_details_main_color' ) );
$ec_pay_accent = $ec_pay_accent ? $ec_pay_accent : '#222222';
/* 6.0.2: the store's own look ( Settings › Design ): its second colour for hover, its font, and its dark-background choice. */
$ec_pay_second = sanitize_hex_color( (string) get_option( 'ec_option_details_second_color' ) );
$ec_pay_second = $ec_pay_second ? $ec_pay_second : '#666666';
$ec_pay_dark   = (bool) get_option( 'ec_option_use_dark_bg' );
$ec_pay_font   = (string) get_option( 'ec_option_font_main' );
$ec_pay_font   = ( 'custom' === $ec_pay_font ) ? (string) get_option( 'ec_option_font_custom' ) : $ec_pay_font;
$ec_pay_font   = trim( preg_replace( '/[^A-Za-z0-9 _\-]/', '', $ec_pay_font ) );
$ec_pay_text   = function ( $key, $fallback ) {
	return wp_easycart_order_pay::text( $key, $fallback );
};
?>
<div class="ec-pay<?php echo $ec_pay_dark ? ' is-dark' : ''; ?>" id="ec-pay" style="--ec-pay-accent:<?php echo esc_attr( $ec_pay_accent ); ?>; --ec-pay-accent-hover:<?php echo esc_attr( $ec_pay_second ); ?>;<?php echo ( '' !== $ec_pay_font ) ? ' --ec-pay-font:&quot;' . esc_attr( $ec_pay_font ) . '&quot;, sans-serif;' : ''; ?>">
<style>
	.ec-pay { --ec-pay-text:#1f2937; --ec-pay-muted:#6b7280; --ec-pay-card:#fff; --ec-pay-border:#e5e7eb; --ec-pay-field:#d1d5db; --ec-pay-line:#f3f4f6; --ec-pay-due:#9a3412; --ec-pay-error:#b91c1c; max-width:1040px; margin:0 auto; font-family:var(--ec-pay-font, inherit); font-size:15px; line-height:1.5; color:var(--ec-pay-text); }
	.ec-pay.is-dark { --ec-pay-text:#f9fafb; --ec-pay-muted:#d1d5db; --ec-pay-card:rgba(255,255,255,.06); --ec-pay-border:rgba(255,255,255,.18); --ec-pay-field:rgba(255,255,255,.35); --ec-pay-line:rgba(255,255,255,.12); --ec-pay-due:#fdba74; --ec-pay-error:#fca5a5; }
	.ec-pay * { box-sizing:border-box; }
	.ec-pay h2, .ec-pay h3 { color:inherit; font-family:inherit; }
	.ec-pay-head { display:flex; flex-wrap:wrap; align-items:flex-end; justify-content:space-between; gap:12px 24px; margin:0 0 20px 0; }
	.ec-pay-head h2 { margin:0 0 4px 0; font-size:26px; line-height:1.2; }
	.ec-pay-meta { color:var(--ec-pay-muted); font-size:14px; }
	.ec-pay-meta span + span::before { content:"·"; margin:0 8px; }
	.ec-pay-due { text-align:right; }
	.ec-pay-due small { display:block; color:var(--ec-pay-muted); font-size:12px; text-transform:uppercase; letter-spacing:.04em; }
	.ec-pay-due strong { font-size:28px; line-height:1.1; }
	.ec-pay-due em { display:block; font-style:normal; color:var(--ec-pay-due); font-size:13px; font-weight:600; }
	.ec-pay-grid { display:grid; grid-template-columns:minmax(0,3fr) minmax(0,2fr); gap:24px; align-items:start; }
	.ec-pay-card { background:var(--ec-pay-card); border:1px solid var(--ec-pay-border); border-radius:10px; padding:20px; }
	.ec-pay-card + .ec-pay-card { margin-top:16px; }
	.ec-pay-card h3 { margin:0 0 12px 0; font-size:16px; }
	.ec-pay-row { display:flex; gap:12px; }
	.ec-pay-row > div { flex:1 1 0; min-width:0; }
	.ec-pay-field { margin:0 0 12px 0; }
	.ec-pay-field label { display:block; font-size:13px; font-weight:600; margin:0 0 4px 0; color:inherit; }
	.ec-pay-field input, .ec-pay-field select { width:100%; padding:9px 11px; border:1px solid var(--ec-pay-field); border-radius:6px; font-family:inherit; font-size:15px; background:#fff; color:#1f2937; }
	.ec-pay-field.is-invalid input, .ec-pay-field.is-invalid select { border-color:#dc2626; }
	.ec-pay-tabs { display:flex; gap:8px; margin:0 0 14px 0; flex-wrap:wrap; }
	.ec-pay-tab { border:1px solid var(--ec-pay-field); background:var(--ec-pay-card); color:var(--ec-pay-text); border-radius:999px; padding:6px 14px; font-family:inherit; font-size:14px; line-height:1.4; text-transform:none; cursor:pointer; }
	.ec-pay-tab:hover { border-color:var(--ec-pay-accent); background:var(--ec-pay-card); color:var(--ec-pay-text); }
	.ec-pay-tab:focus-visible, .ec-pay-panel:focus-visible { outline:2px solid var(--ec-pay-accent); outline-offset:2px; }
	.ec-pay-tab[aria-selected="true"] { border-color:var(--ec-pay-accent); box-shadow:inset 0 0 0 1px var(--ec-pay-accent); font-weight:600; }
	.ec-pay-panel[hidden] { display:none; }
	.ec-pay-loading { display:flex; align-items:center; gap:10px; min-height:44px; padding:10px 0; color:var(--ec-pay-muted); font-size:14px; }
	.ec-pay-loading[hidden] { display:none; }
	.ec-pay-spinner { width:18px; height:18px; flex:0 0 auto; border:2px solid var(--ec-pay-border); border-top-color:var(--ec-pay-accent); border-radius:50%; animation:ec-pay-spin .8s linear infinite; }
	@keyframes ec-pay-spin { to { transform:rotate(360deg); } }
	.ec-pay-button { display:block; width:100%; margin:16px 0 0 0; padding:13px 16px; border:0; border-radius:8px; background:var(--ec-pay-accent); color:#fff; font-family:inherit; font-size:16px; font-weight:600; cursor:pointer; }
	.ec-pay-button:hover, .ec-pay-button:focus { background:var(--ec-pay-accent-hover, var(--ec-pay-accent)); color:#fff; }
	.ec-pay-button[disabled], .ec-pay-button[disabled]:hover { opacity:.6; cursor:default; background:var(--ec-pay-accent); }
	.ec-pay-status { margin:12px 0 0 0; font-size:14px; min-height:1em; }
	.ec-pay-status.is-error { color:var(--ec-pay-error); }
	.ec-pay-notice { padding:12px 16px; border-radius:8px; margin:0 0 16px 0; }
	.ec-pay-notice.is-error { background:#fef2f2; color:#7f1d1d; }
	.ec-pay-notice.is-info { background:#eff6ff; color:#1e3a8a; }
	.ec-pay-notice.is-success { background:#ecfdf5; color:#14532d; }
	.ec-pay-lines { list-style:none; margin:0; padding:0; }
	.ec-pay-lines li { display:flex; justify-content:space-between; gap:12px; padding:8px 0; border-bottom:1px solid var(--ec-pay-line); }
	.ec-pay-lines li small { display:block; color:var(--ec-pay-muted); }
	.ec-pay-totals { width:100%; margin:12px 0 0 0; border-collapse:collapse; }
	.ec-pay-totals td { padding:3px 0; border:0; background:transparent; color:inherit; }
	.ec-pay-totals td:last-child { text-align:right; white-space:nowrap; }
	.ec-pay-totals tr.is-grand td { padding-top:10px; border-top:1px solid var(--ec-pay-border); font-weight:700; font-size:17px; }
	.ec-pay-address { color:inherit; opacity:.9; font-size:14px; }
	.ec-pay a { color:var(--ec-pay-accent); }
	.ec-pay a:hover { color:var(--ec-pay-accent-hover, var(--ec-pay-accent)); }
	.ec-pay.is-dark a, .ec-pay.is-dark a:hover { color:inherit; text-decoration:underline; }
	.ec-pay-links a { display:inline-block; margin:8px 12px 0 0; font-size:14px; }
	.ec-pay-bank { white-space:pre-line; }
	.ec-pay-state { max-width:560px; margin:0 auto; text-align:center; }
	@media ( max-width:760px ) {
		.ec-pay-grid { grid-template-columns:1fr; }
		.ec-pay-due { text-align:left; }
		.ec-pay-row { flex-direction:column; gap:0; }
	}
</style>
<?php
if ( 'payable' !== $state ) :
	$ec_pay_title = ( 'paid' === $state ) ? $ec_pay_text( 'pay_paid_title', __( 'Thank you', 'wp-easycart' ) ) : $ec_pay_text( 'pay_title', __( 'Pay for your order', 'wp-easycart' ) );
	$ec_pay_msg   = ( 'success' === $notice && 'paid' === $state ) ? $ec_pay_text( 'pay_success', __( 'Thank you! Your payment was received. We have emailed you a receipt.', 'wp-easycart' ) ) : wp_easycart_order_pay::state_message( $state );
	?>
	<div class="ec-pay-card ec-pay-state">
		<h2><?php echo $ec_pay_title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- text() escapes. ?></h2>
		<p><?php echo $ec_pay_msg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- text() escapes. ?></p>
		<?php
		if ( $order && in_array( $state, array( 'paid', 'pending' ), true ) && function_exists( 'wpeasycart_links' ) ) :
			$ec_pay_order_url = wpeasycart_links()->get_account_page(
				'order_details',
				array(
					'order_id'     => (int) $order->order_id,
					'ec_guest_key' => wp_easycart_order_pay::clean_key( $order->guest_key ),
				)
			);
			?>
			<p><a href="<?php echo esc_url( $ec_pay_order_url ); ?>"><?php echo $ec_pay_text( 'pay_view_order', __( 'View your order', 'wp-easycart' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- text() escapes. ?></a></p>
		<?php endif; ?>
	</div>
</div>
	<?php
	return;
endif;

/* The order as it stands. */
$ec_pay_id      = (int) $order->order_id;
$ec_pay_lines   = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT title, quantity, total_price, optionitem_name_1, optionitem_name_2, optionitem_name_3, optionitem_name_4, optionitem_name_5 FROM ec_orderdetail WHERE order_id = %d ORDER BY orderdetail_id ASC', $ec_pay_id ) );
$ec_pay_fees    = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT fee_label, fee_total FROM ec_order_fee WHERE order_id = %d', $ec_pay_id ) );
$ec_pay_extras  = class_exists( 'wp_easycart_documents' ) && method_exists( 'wp_easycart_documents', 'order_extras' ) ? wp_easycart_documents::order_extras( $order ) : null;
$ec_pay_invoice = class_exists( 'wp_easycart_documents' ) && method_exists( 'wp_easycart_documents', 'invoice_for_order' ) ? wp_easycart_documents::invoice_for_order( $ec_pay_id ) : null;
$ec_pay_due     = ( $ec_pay_invoice && '' !== (string) $ec_pay_invoice->due_date && 0 !== strpos( (string) $ec_pay_invoice->due_date, '0000' ) ) ? (string) $ec_pay_invoice->due_date : ( $ec_pay_extras ? $ec_pay_extras->payment_due_date : '' );
$ec_pay_money   = function ( $amount ) use ( $ec_pay_cur ) {
	return esc_html( $ec_pay_cur->get_currency_display( (float) $amount ) );
};
/* 6.0.2: what this link charges: the order's total, or the balance of an order changed after it was paid. */
$ec_pay_owed    = wp_easycart_order_pay::amount_due( $order );
$ec_pay_sum     = class_exists( 'wp_easycart_order_payments' ) ? wp_easycart_order_payments::summary( $order ) : null;
$ec_pay_part    = $ec_pay_sum && $ec_pay_sum['recorded'] && $ec_pay_sum['paid'] >= 0.005;
$ec_pay_label   = function ( $group, $key ) use ( $ec_pay_lang ) {
	return wp_kses_post( $ec_pay_lang->get_text( $group, $key ) );
};
/* Card and PayPal only while pay links are on ( Settings › Documents › Pay links ); bank details always. */
$ec_pay_online  = ( isset( $pay_methods['stripe'] ) || isset( $pay_methods['square'] ) || isset( $pay_methods['paypal'] ) ) && get_option( wp_easycart_order_pay::OPTION, 1 );
$ec_pay_ship    = ( '' !== trim( (string) $order->shipping_address_line_1 ) && (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_orderdetail WHERE order_id = %d AND is_shippable = 1', $ec_pay_id ) ) > 0 );
$ec_pay_country = (array) $wpdb->get_results( 'SELECT iso2_cnt, name_cnt FROM ec_country ORDER BY sort_order ASC, name_cnt ASC' );
/**
 * Extra links above the summary ( WP EasyCart PRO adds the invoice PDF ): each array( url, label ).
 *
 * @since 6.0.2
 * @param array  $links Links.
 * @param object $order Order.
 */
$ec_pay_links = (array) apply_filters( 'wp_easycart_order_pay_links', array(), $order );
$ec_pay_input = function ( $field, $label, $required, $half = false ) use ( $order ) {
	$value = isset( $order->{'billing_' . $field} ) ? (string) $order->{'billing_' . $field} : '';
	echo '<div class="ec-pay-field"><label for="ec_pay_' . esc_attr( $field ) . '">' . $label . ( $required ? ' *' : '' ) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- labels come from the language file through wp_kses_post().
	echo '<input type="text" id="ec_pay_' . esc_attr( $field ) . '" data-billing="' . esc_attr( $field ) . '" value="' . esc_attr( $value ) . '"' . ( $required ? ' required' : '' ) . ' autocomplete="' . esc_attr( 'billing ' . str_replace( array( 'first_name', 'last_name', 'address_line_1', 'address_line_2', 'city', 'state', 'zip', 'phone', 'company_name' ), array( 'given-name', 'family-name', 'address-line1', 'address-line2', 'address-level2', 'address-level1', 'postal-code', 'tel', 'organization' ), $field ) ) . '" /></div>';
};
?>
	<div class="ec-pay-head">
		<div>
			<h2><?php echo $ec_pay_text( 'pay_title', __( 'Pay for your order', 'wp-easycart' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- text() escapes. ?></h2>
			<div class="ec-pay-meta">
				<span><?php echo $ec_pay_text( 'order_number', __( 'Order', 'wp-easycart' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- text() escapes. ?> #<?php echo (int) $ec_pay_id; ?></span>
				<?php if ( $ec_pay_invoice ) : ?><span><?php echo esc_html( $ec_pay_invoice->invoice_number ); ?></span><?php endif; ?>
				<?php if ( $ec_pay_extras && '' !== $ec_pay_extras->po_number ) : ?><span><?php echo wp_kses_post( rtrim( $ec_pay_text( 'po_number_label', __( 'PO number:', 'wp-easycart' ) ), ': ' ) ); ?> <?php echo esc_html( $ec_pay_extras->po_number ); ?></span><?php endif; ?>
			</div>
		</div>
		<div class="ec-pay-due">
			<small><?php echo $ec_pay_text( 'pay_amount_due', __( 'Amount due', 'wp-easycart' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- text() escapes. ?></small>
			<strong><?php echo $ec_pay_money( $ec_pay_owed ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the closure. ?></strong>
			<?php if ( '' !== $ec_pay_due ) : ?><em><?php echo wp_kses_post( rtrim( $ec_pay_text( 'due_date_label', __( 'Due:', 'wp-easycart' ) ), ': ' ) ); ?> <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $ec_pay_due ) ) ); ?></em><?php endif; ?>
		</div>
	</div>

	<?php if ( 'error' === $notice ) : ?>
		<div class="ec-pay-notice is-error" role="alert"><?php echo $ec_pay_text( 'pay_error', __( 'The payment could not be completed. Please try again, or use another payment method.', 'wp-easycart' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- text() escapes. ?></div>
	<?php elseif ( 'pending' === $notice ) : ?>
		<div class="ec-pay-notice is-info" role="status"><?php echo $ec_pay_text( 'pay_pending', __( 'Your payment is being processed. We will email you when it is confirmed.', 'wp-easycart' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- text() escapes. ?></div>
	<?php endif; ?>

	<div class="ec-pay-grid">
		<div>
			<div class="ec-pay-card">
				<h3><?php echo $ec_pay_label( 'cart_billing_information', 'cart_billing_information_title' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses_post() in the closure. ?></h3>
				<div class="ec-pay-row">
					<div><?php $ec_pay_input( 'first_name', $ec_pay_label( 'cart_billing_information', 'cart_billing_information_first_name' ), true ); ?></div>
					<div><?php $ec_pay_input( 'last_name', $ec_pay_label( 'cart_billing_information', 'cart_billing_information_last_name' ), true ); ?></div>
				</div>
				<?php if ( get_option( 'ec_option_enable_company_name' ) ) : ?>
					<?php $ec_pay_input( 'company_name', $ec_pay_label( 'cart_billing_information', 'cart_billing_information_company_name' ), false ); ?>
				<?php endif; ?>
				<?php $ec_pay_input( 'address_line_1', $ec_pay_label( 'cart_billing_information', 'cart_billing_information_address' ), true ); ?>
				<?php if ( get_option( 'ec_option_use_address2' ) ) : ?>
					<?php $ec_pay_input( 'address_line_2', $ec_pay_label( 'cart_billing_information', 'cart_billing_information_address2' ), false ); ?>
				<?php endif; ?>
				<div class="ec-pay-row">
					<div><?php $ec_pay_input( 'city', $ec_pay_label( 'cart_billing_information', 'cart_billing_information_city' ), true ); ?></div>
					<div><?php $ec_pay_input( 'state', $ec_pay_label( 'cart_billing_information', 'cart_billing_information_state' ), false ); ?></div>
				</div>
				<div class="ec-pay-row">
					<div><?php $ec_pay_input( 'zip', $ec_pay_label( 'cart_billing_information', 'cart_billing_information_zip' ), true ); ?></div>
					<div class="ec-pay-field">
						<label for="ec_pay_country"><?php echo $ec_pay_label( 'cart_billing_information', 'cart_billing_information_country' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses_post() in the closure. ?> *</label>
						<select id="ec_pay_country" data-billing="country" required autocomplete="billing country">
							<?php foreach ( $ec_pay_country as $ec_pay_c ) : ?>
								<option value="<?php echo esc_attr( $ec_pay_c->iso2_cnt ); ?>"<?php selected( strtoupper( (string) $order->billing_country ), strtoupper( (string) $ec_pay_c->iso2_cnt ) ); ?>><?php echo esc_html( $ec_pay_c->name_cnt ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
				<?php if ( get_option( 'ec_option_collect_user_phone' ) ) : ?>
					<?php $ec_pay_input( 'phone', $ec_pay_label( 'cart_billing_information', 'cart_billing_information_phone' ), false ); ?>
				<?php endif; ?>
				<?php if ( get_option( 'ec_option_collect_vat_registration_number' ) ) : ?>
					<div class="ec-pay-field"><label for="ec_pay_vat"><?php echo $ec_pay_label( 'cart_billing_information', 'cart_billing_information_vat_registration_number' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses_post() in the closure. ?></label>
					<input type="text" id="ec_pay_vat" data-billing="vat_registration_number" value="<?php echo esc_attr( (string) $order->vat_registration_number ); ?>" /></div>
				<?php endif; ?>
			</div>

			<div class="ec-pay-card">
				<h3><?php echo $ec_pay_text( 'pay_method_title', __( 'Payment', 'wp-easycart' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- text() escapes. ?></h3>
				<?php
				$ec_pay_tabs = array();
				if ( $ec_pay_online && ( isset( $pay_methods['stripe'] ) || isset( $pay_methods['square'] ) ) ) {
					$ec_pay_tabs['card'] = $ec_pay_text( 'pay_card_tab', __( 'Card', 'wp-easycart' ) );
				}
				if ( $ec_pay_online && isset( $pay_methods['paypal'] ) ) {
					$ec_pay_tabs['paypal'] = $ec_pay_text( 'pay_paypal_tab', __( 'PayPal', 'wp-easycart' ) );
				}
				if ( isset( $pay_methods['manual'] ) ) {
					$ec_pay_tabs['bank'] = $ec_pay_text( 'pay_bank_tab', __( 'Bank transfer', 'wp-easycart' ) );
				}
				$ec_pay_first = key( $ec_pay_tabs );
				?>
				<?php if ( ! $ec_pay_tabs ) : ?>
					<p><?php echo $ec_pay_text( 'pay_unavailable', __( 'Online payment is not available for this order. Please contact us to arrange payment.', 'wp-easycart' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- text() escapes. ?></p>
				<?php else : ?>
					<?php
					/**
					 * Before the payment methods on the pay-link page ( 6.0.2: checkout protection notes the visit here ).
					 *
					 * @since 6.0.2
					 * @param object $order Order being paid.
					 */
					do_action( 'wpeasycart_order_pay_before_button', $order );
					?>
					<?php
					/* 6.0.2: the ARIA tabs pattern ( tab ↔ panel links, one tab stop, arrow keys in the script below ). */
					$ec_pay_has_tabs = count( $ec_pay_tabs ) > 1;
					$ec_pay_panel    = function ( $tab ) use ( $ec_pay_has_tabs, $ec_pay_first ) {
						$attrs = ' id="ec-pay-panel-' . esc_attr( $tab ) . '" data-panel="' . esc_attr( $tab ) . '"';
						if ( $ec_pay_has_tabs ) {
							$attrs .= ' role="tabpanel" aria-labelledby="ec-pay-tab-' . esc_attr( $tab ) . '" tabindex="0"';
						}
						return $attrs . ( ( $tab === $ec_pay_first ) ? '' : ' hidden' );
					};
					$ec_pay_loading  = $ec_pay_text( 'pay_loading', __( 'Loading the secure payment form…', 'wp-easycart' ) );
					?>
					<?php if ( $ec_pay_has_tabs ) : ?>
						<div class="ec-pay-tabs" role="tablist" aria-label="<?php echo esc_attr( wp_strip_all_tags( $ec_pay_text( 'pay_method_title', __( 'Payment', 'wp-easycart' ) ) ) ); ?>">
							<?php foreach ( $ec_pay_tabs as $ec_pay_tab => $ec_pay_tab_label ) : ?>
								<button type="button" class="ec-pay-tab" role="tab" id="ec-pay-tab-<?php echo esc_attr( $ec_pay_tab ); ?>" aria-controls="ec-pay-panel-<?php echo esc_attr( $ec_pay_tab ); ?>" data-tab="<?php echo esc_attr( $ec_pay_tab ); ?>" aria-selected="<?php echo ( $ec_pay_tab === $ec_pay_first ) ? 'true' : 'false'; ?>" tabindex="<?php echo ( $ec_pay_tab === $ec_pay_first ) ? '0' : '-1'; ?>"><?php echo $ec_pay_tab_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- text() escapes. ?></button>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
					<?php /* 6.0.2: checkout protection's notice ( ec-checkout-protection.js ) goes here, above whichever way to pay is open. */ ?>
					<div id="ec_checkout_protection_notice" class="wpec-protect-mount"></div>
					<?php if ( isset( $ec_pay_tabs['card'] ) ) : ?>
						<div class="ec-pay-panel"<?php echo $ec_pay_panel( 'card' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the closure. ?>>
							<?php if ( $ec_pay_online ) : ?>
							<div class="ec-pay-loading" data-loading="card" role="status"><span class="ec-pay-spinner" aria-hidden="true"></span><span><?php echo $ec_pay_loading; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- text() escapes. ?></span></div>
							<?php endif; ?>
							<div id="<?php echo isset( $pay_methods['stripe'] ) ? 'ec-pay-stripe' : 'ec-pay-square'; ?>"></div>
							<button type="button" class="ec-pay-button" id="ec-pay-card-button" disabled><?php echo $ec_pay_text( 'pay_button', __( 'Pay now', 'wp-easycart' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- text() escapes. ?> <?php echo $ec_pay_money( $ec_pay_owed ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the closure. ?></button>
						</div>
					<?php endif; ?>
					<?php if ( isset( $ec_pay_tabs['paypal'] ) ) : ?>
						<div class="ec-pay-panel"<?php echo $ec_pay_panel( 'paypal' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the closure. ?>>
							<div class="ec-pay-loading" data-loading="paypal" role="status"><span class="ec-pay-spinner" aria-hidden="true"></span><span><?php echo $ec_pay_loading; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- text() escapes. ?></span></div>
							<div id="ec-pay-paypal"></div>
						</div>
					<?php endif; ?>
					<?php if ( isset( $ec_pay_tabs['bank'] ) ) : ?>
						<div class="ec-pay-panel"<?php echo $ec_pay_panel( 'bank' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the closure. ?>>
							<p><?php echo $ec_pay_text( 'pay_bank_intro', __( 'To pay by bank transfer, use these details and quote your order number:', 'wp-easycart' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- text() escapes. ?> <strong>#<?php echo (int) $ec_pay_id; ?></strong></p>
							<div class="ec-pay-bank"><?php echo wp_kses_post( (string) get_option( 'ec_option_direct_deposit_message' ) ); ?></div>
						</div>
					<?php endif; ?>
					<div class="ec-pay-status" role="status" aria-live="polite"></div>
				<?php endif; ?>
			</div>
		</div>

		<div>
			<div class="ec-pay-card">
				<h3><?php echo $ec_pay_label( 'cart_success', 'cart_invoice_items_label' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses_post() in the closure. ?></h3>
				<?php if ( $ec_pay_links ) : ?>
					<div class="ec-pay-links">
						<?php foreach ( $ec_pay_links as $ec_pay_link ) : ?>
							<?php if ( is_array( $ec_pay_link ) && ! empty( $ec_pay_link[0] ) ) : ?>
								<a href="<?php echo esc_url( $ec_pay_link[0] ); ?>" target="_blank" rel="noopener nofollow"><?php echo esc_html( wp_strip_all_tags( isset( $ec_pay_link[1] ) ? (string) $ec_pay_link[1] : '' ) ); ?></a>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<ul class="ec-pay-lines">
					<?php foreach ( $ec_pay_lines as $ec_pay_line ) : ?>
						<?php
						$ec_pay_opts = array();
						for ( $ec_pay_n = 1; $ec_pay_n <= 5; $ec_pay_n++ ) {
							if ( '' !== trim( (string) $ec_pay_line->{'optionitem_name_' . $ec_pay_n} ) ) {
								$ec_pay_opts[] = wp_strip_all_tags( (string) $ec_pay_line->{'optionitem_name_' . $ec_pay_n} );
							}
						}
						?>
						<li><span><?php echo esc_html( wp_strip_all_tags( $ec_pay_lang->convert_text( $ec_pay_line->title ) ) ); ?> × <?php echo (int) $ec_pay_line->quantity; ?><?php if ( $ec_pay_opts ) : ?><small><?php echo esc_html( implode( ', ', $ec_pay_opts ) ); ?></small><?php endif; ?></span><span><?php echo $ec_pay_money( $ec_pay_line->total_price ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the closure. ?></span></li>
					<?php endforeach; ?>
				</ul>
				<table class="ec-pay-totals" role="presentation">
					<tr><td><?php echo $ec_pay_label( 'cart_success', 'cart_payment_complete_order_totals_subtotal' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses_post() in the closure. ?></td><td><?php echo $ec_pay_money( $order->sub_total ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the closure. ?></td></tr>
					<?php if ( (float) $order->discount_total > 0 ) : ?>
						<tr><td><?php echo $ec_pay_label( 'cart_success', 'cart_payment_complete_order_totals_discount' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses_post() in the closure. ?></td><td>−<?php echo $ec_pay_money( $order->discount_total ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the closure. ?></td></tr>
					<?php endif; ?>
					<?php if ( (float) $order->shipping_total > 0 ) : ?>
						<tr><td><?php echo $ec_pay_label( 'cart_success', 'cart_payment_complete_order_totals_shipping' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses_post() in the closure. ?></td><td><?php echo $ec_pay_money( $order->shipping_total ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the closure. ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $ec_pay_fees as $ec_pay_fee ) : ?>
						<tr><td><?php echo esc_html( $ec_pay_fee->fee_label ); ?></td><td><?php echo $ec_pay_money( $ec_pay_fee->fee_total ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the closure. ?></td></tr>
					<?php endforeach; ?>
					<?php $ec_pay_tax = (float) $order->tax_total + (float) $order->gst_total + (float) $order->pst_total + (float) $order->hst_total; ?>
					<?php if ( $ec_pay_tax > 0 ) : ?>
						<tr><td><?php echo $ec_pay_label( 'cart_success', 'cart_payment_complete_order_totals_tax' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses_post() in the closure. ?></td><td><?php echo $ec_pay_money( $ec_pay_tax ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the closure. ?></td></tr>
					<?php endif; ?>
					<?php if ( (float) $order->duty_total > 0 ) : ?>
						<tr><td><?php echo $ec_pay_label( 'cart_success', 'cart_payment_complete_order_totals_duty' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses_post() in the closure. ?></td><td><?php echo $ec_pay_money( $order->duty_total ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the closure. ?></td></tr>
					<?php endif; ?>
					<?php if ( (float) $order->vat_total > 0 ) : ?>
						<tr><td><?php echo $ec_pay_label( 'cart_success', 'cart_payment_complete_order_totals_vat' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses_post() in the closure. ?><?php echo esc_html( rtrim( rtrim( number_format( (float) $order->vat_rate, 2, '.', '' ), '0' ), '.' ) ); ?>%</td><td><?php echo $ec_pay_money( $order->vat_total ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the closure. ?></td></tr>
					<?php endif; ?>
					<tr class="is-grand"><td><?php echo $ec_pay_label( 'cart_success', 'cart_payment_complete_order_totals_grand_total' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses_post() in the closure. ?></td><td><?php echo $ec_pay_money( $order->grand_total ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the closure. ?></td></tr>
					<?php if ( $ec_pay_part ) : /* 6.0.2: paid before the order changed ( less any overpayment already given back ) */ ?>
						<tr><td><?php echo $ec_pay_text( 'pay_paid_label', __( 'Paid', 'wp-easycart' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- text() escapes. ?></td><td>−<?php echo $ec_pay_money( $ec_pay_sum['paid'] - $ec_pay_sum['overpaid_refunded'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the closure. ?></td></tr>
						<tr class="is-grand"><td><?php echo $ec_pay_text( 'pay_balance_label', __( 'Balance due', 'wp-easycart' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- text() escapes. ?></td><td><?php echo $ec_pay_money( $ec_pay_owed ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the closure. ?></td></tr>
					<?php endif; ?>
				</table>
			</div>
			<?php if ( $ec_pay_ship ) : ?>
				<div class="ec-pay-card">
					<h3><?php echo $ec_pay_text( 'pay_shipping_title', __( 'Shipping to', 'wp-easycart' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- text() escapes. ?></h3>
					<div class="ec-pay-address">
						<?php echo esc_html( trim( $order->shipping_first_name . ' ' . $order->shipping_last_name ) ); ?><br />
						<?php if ( '' !== trim( (string) $order->shipping_company_name ) ) : ?><?php echo esc_html( $order->shipping_company_name ); ?><br /><?php endif; ?>
						<?php echo esc_html( $order->shipping_address_line_1 ); ?><br />
						<?php if ( '' !== trim( (string) $order->shipping_address_line_2 ) ) : ?><?php echo esc_html( $order->shipping_address_line_2 ); ?><br /><?php endif; ?>
						<?php echo esc_html( trim( $order->shipping_city . ' ' . $order->shipping_state . ' ' . $order->shipping_zip ) ); ?><br />
						<?php echo esc_html( $order->shipping_country ); ?>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</div>

<?php if ( $ec_pay_online ) : ?>
<script type="application/json" id="ec-pay-data"><?php echo wp_json_encode( wp_easycart_order_pay::script_data( $order ), JSON_HEX_TAG | JSON_HEX_AMP ); ?></script>
<script>
( function() {
	var C = {};
	try { C = JSON.parse( document.getElementById( 'ec-pay-data' ).textContent ); } catch ( e ) { return; }
	var root = document.getElementById( 'ec-pay' );
	var status = root.querySelector( '.ec-pay-status' );
	var cardButton = document.getElementById( 'ec-pay-card-button' );

	function say( text, error ) {
		if ( ! status ) { return; }
		status.textContent = text || '';
		status.className = 'ec-pay-status' + ( error ? ' is-error' : '' );
	}
	function busy( on ) {
		if ( cardButton ) { cardButton.disabled = !! on; }
		if ( on ) { say( C.text.processing ); }
	}
	function billing() {
		var out = {}, first = null;
		root.querySelectorAll( '[data-billing]' ).forEach( function( el ) {
			out[ el.getAttribute( 'data-billing' ) ] = ( el.value || '' ).trim();
			var field = el.closest( '.ec-pay-field' );
			var bad = el.hasAttribute( 'required' ) && '' === out[ el.getAttribute( 'data-billing' ) ];
			if ( field ) { field.classList.toggle( 'is-invalid', bad ); }
			if ( bad ) {
				el.setAttribute( 'aria-invalid', 'true' );
				first = first || el;
			} else {
				el.removeAttribute( 'aria-invalid' );
			}
		} );
		if ( first ) {
			say( C.text.billing, true );
			/* 6.0.2: take the payer to the first field to fill in. */
			try { first.focus( { preventScroll: false } ); } catch ( e ) { first.focus(); }
			return null;
		}
		return out;
	}
	/* 6.0.2: the "loading the payment form" line until a gateway's form is on the page ( or can't load ). */
	function loaded( which ) {
		root.querySelectorAll( '[data-loading="' + which + '"]' ).forEach( function( el ) { el.hidden = true; } );
	}
	function post( action, extra ) {
		var data = new FormData();
		data.append( 'action', 'ec_order_pay_' + action );
		data.append( 'order_id', C.order_id );
		data.append( 'key', C.key );
		data.append( 'nonce', C.nonce );
		Object.keys( extra || {} ).forEach( function( k ) { data.append( k, extra[ k ] ); } );
		return fetch( C.ajax_url, { method: 'POST', body: data, credentials: 'same-origin' } ).then( function( r ) { return r.json(); } );
	}
	function done( r ) {
		if ( r && r.success && ( r.data.paid || r.data.pending ) ) {
			say( r.data.pending ? C.text.pending : C.text.processing );
			window.location.href = C.page;
			return true;
		}
		busy( false );
		say( ( r && r.data && r.data.message ) ? String( r.data.message ).replace( /<[^>]+>/g, '' ) : C.text.error, true );
		return false;
	}

	/* A gateway script: in the head on a cart page, or later with the footer when this page had to add it. */
	function sdk( name, start, which ) {
		if ( window[ name ] ) { start(); return; }
		window.addEventListener( 'load', function() {
			if ( window[ name ] ) { start(); } else { loaded( which ); say( C.text.error, true ); }
		} );
	}

	/* Tabs ( 6.0.2: the ARIA tabs pattern: arrow keys, Home and End move between them, one tab stop ). */
	var tabs = Array.prototype.slice.call( root.querySelectorAll( '.ec-pay-tab' ) );
	function selectTab( tab, focus ) {
		tabs.forEach( function( t ) {
			t.setAttribute( 'aria-selected', t === tab ? 'true' : 'false' );
			t.setAttribute( 'tabindex', t === tab ? '0' : '-1' );
		} );
		root.querySelectorAll( '.ec-pay-panel' ).forEach( function( p ) { p.hidden = p.getAttribute( 'data-panel' ) !== tab.getAttribute( 'data-tab' ); } );
		say( '' );
		if ( focus ) { tab.focus(); }
	}
	tabs.forEach( function( tab, index ) {
		tab.addEventListener( 'click', function() { selectTab( tab, false ); } );
		tab.addEventListener( 'keydown', function( e ) {
			var next = -1;
			if ( 'ArrowRight' === e.key || 'ArrowDown' === e.key ) { next = ( index + 1 ) % tabs.length; }
			else if ( 'ArrowLeft' === e.key || 'ArrowUp' === e.key ) { next = ( index - 1 + tabs.length ) % tabs.length; }
			else if ( 'Home' === e.key ) { next = 0; }
			else if ( 'End' === e.key ) { next = tabs.length - 1; }
			if ( next < 0 ) { return; }
			e.preventDefault();
			selectTab( tabs[ next ], true );
		} );
	} );

	/* Stripe: Payment Element on this order's PaymentIntent. */
	function startStripe() {
		var stripe = window.Stripe( C.stripe ), elements = null;
		post( 'stripe_intent', {} ).then( function( r ) {
			if ( r && r.success && r.data && ( r.data.paid || r.data.pending ) ) { window.location.href = C.page; return; }
			if ( ! r || ! r.success ) { loaded( 'card' ); say( ( r && r.data && r.data.message ) ? String( r.data.message ).replace( /<[^>]+>/g, '' ) : C.text.error, true ); return; }
			elements = stripe.elements( { clientSecret: r.data.client_secret } );
			var paymentElement = elements.create( 'payment' );
			paymentElement.on( 'ready', function() { loaded( 'card' ); } );
			paymentElement.on( 'loaderror', function() { loaded( 'card' ); say( C.text.error, true ); } );
			paymentElement.mount( '#ec-pay-stripe' );
			cardButton.disabled = false;
		} ).catch( function() { loaded( 'card' ); say( C.text.error, true ); } );
		cardButton.addEventListener( 'click', function() {
			var b = billing();
			if ( ! b || ! elements ) { return; }
			busy( true );
			post( 'billing', { billing: JSON.stringify( b ) } ).then( function( r ) {
				if ( ! r || ! r.success || ( r.data && ( r.data.paid || r.data.pending ) ) ) { done( r ); return; }
				return stripe.confirmPayment( {
					elements: elements,
					redirect: 'if_required',
					confirmParams: {
						return_url: C.return,
						payment_method_data: { billing_details: { name: ( b.first_name + ' ' + b.last_name ).trim(), email: C.email, address: { line1: b.address_line_1, line2: b.address_line_2 || '', city: b.city, state: b.state || '', postal_code: b.zip, country: b.country } } }
					}
				} ).then( function( result ) {
					if ( result.error ) { busy( false ); say( result.error.message, true ); return; }
					return post( 'stripe_complete', { payment_intent: result.paymentIntent.id } ).then( done );
				} );
			} ).catch( function() { busy( false ); say( C.text.error, true ); } );
		} );
	}

	/* Square: Web Payments SDK card. */
	function startSquare() {
		var payments = window.Square.payments( C.square.app, C.square.location ), sqCard = null;
		payments.card().then( function( card ) {
			sqCard = card;
			return card.attach( '#ec-pay-square' );
		} ).then( function() { loaded( 'card' ); cardButton.disabled = false; } ).catch( function() { loaded( 'card' ); say( C.text.error, true ); } );
		cardButton.addEventListener( 'click', function() {
			var b = billing();
			if ( ! b || ! sqCard ) { return; }
			busy( true );
			sqCard.tokenize().then( function( result ) {
				if ( 'OK' !== result.status ) { throw new Error( 'token' ); }
				var contact = { givenName: b.first_name, familyName: b.last_name, addressLines: [ b.address_line_1, b.address_line_2 || '' ], city: b.city, state: b.state || '', postalCode: b.zip, countryCode: b.country, email: C.email };
				return payments.verifyBuyer( result.token, { amount: C.amount, billingContact: contact, currencyCode: C.currency, intent: 'CHARGE' } ).then( function( v ) {
					return { token: result.token, verify: v && v.token ? v.token : '' };
				} ).catch( function() { return { token: result.token, verify: '' }; } );
			} ).then( function( t ) {
				return post( 'square', { sourceId: t.token, buyerVerificationToken: t.verify, billing: JSON.stringify( b ) } ).then( done );
			} ).catch( function() { busy( false ); say( C.text.error, true ); } );
		} );
	}

	/* PayPal: smart buttons. */
	function startPaypal() {
		window.paypal.Buttons( {
			onClick: function( data, actions ) { return billing() ? actions.resolve() : actions.reject(); },
			createOrder: function() {
				return post( 'paypal_create', { billing: JSON.stringify( billing() || {} ) } ).then( function( r ) {
					if ( ! r || ! r.success || ! r.data || ! r.data.id ) { done( r ); throw new Error( 'create' ); }
					return r.data.id;
				} );
			},
			onApprove: function( data ) {
				say( C.text.processing );
				return post( 'paypal_capture', { paypal_order: data.orderID } ).then( done );
			},
			onError: function() { say( C.text.error, true ); }
		} ).render( '#ec-pay-paypal' ).then( function() { loaded( 'paypal' ); }, function() { loaded( 'paypal' ); say( C.text.error, true ); } );
	}

	if ( C.stripe && document.getElementById( 'ec-pay-stripe' ) ) { sdk( 'Stripe', startStripe, 'card' ); }
	if ( C.square && document.getElementById( 'ec-pay-square' ) ) { sdk( 'Square', startSquare, 'card' ); }
	if ( C.paypal && document.getElementById( 'ec-pay-paypal' ) ) { sdk( 'paypal', startPaypal, 'paypal' ); }
	/* A gateway this page shows but the store has no keys for: nothing will load. */
	if ( ! C.stripe && ! C.square ) { loaded( 'card' ); }
	if ( ! C.paypal ) { loaded( 'paypal' ); }
} )();
</script>
<?php endif; ?>
</div>
