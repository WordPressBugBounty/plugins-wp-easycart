<?php
/**
 * Printable gift card ( front end: ?wpeasycarthook=print-giftcard, the "print online" link on an order ).
 *
 * Included by wpeasycart.php with: $ec_orderdetail ( ec_orderdetail ), $giftcard_id, $giftcard_total,
 * $store_page, $permalink_divider, $email_logo_url, $order_row, $orderdetail_row, $order_id, $orderdetail_id.
 *
 * 6.0.0: rebuilt on the shared email design ( wp_easycart_email_design ) so the printed card matches the gift card
 * email: centred 600px card, store logo, the product image constrained ( skipped when there is none ), the amount as
 * the hero, the code in a code box, single To / From / message labels ( the old page printed the label twice, e.g.
 * "To: to: Matt" ) and redemption instructions. @media print drops the page background and the screen-only button.
 * Copy this file to your theme / wp-easycart-data layout folder to customise it; the file name must stay the same.
 *
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'wp_easycart_email_design' ) ) {
	require_once EC_PLUGIN_DIRECTORY . '/inc/classes/core/class-wp-easycart-email-design.php';
}

$ed           = 'wp_easycart_email_design';
$ec_gc_lang   = wp_easycart_language();
$ec_gc_detail = isset( $ec_orderdetail ) ? $ec_orderdetail : null;
$ec_gc_to     = ( $ec_gc_detail && isset( $ec_gc_detail->gift_card_to_name ) ) ? trim( (string) $ec_gc_detail->gift_card_to_name ) : '';
$ec_gc_from   = ( $ec_gc_detail && isset( $ec_gc_detail->gift_card_from_name ) ) ? trim( (string) $ec_gc_detail->gift_card_from_name ) : '';
$ec_gc_note   = ( $ec_gc_detail && isset( $ec_gc_detail->gift_card_message ) ) ? trim( (string) $ec_gc_detail->gift_card_message ) : '';
$ec_gc_image  = '';
if ( $ec_gc_detail && get_option( 'ec_option_show_image_on_receipt' ) ) {
	$ec_gc_image = $ed::product_image_url(
		isset( $ec_gc_detail->image1 ) ? $ec_gc_detail->image1 : '',
		! empty( $ec_gc_detail->is_deconetwork ),
		isset( $ec_gc_detail->deconetwork_image_link ) ? $ec_gc_detail->deconetwork_image_link : ''
	);
	if ( false !== strpos( $ec_gc_image, 'ec_image_not_found' ) ) {
		$ec_gc_image = ''; // no picture on this product: leave the card clean instead of printing a placeholder.
	}
}

/*
 * The "to:" / "from:" / "message:" language strings are labels in themselves ( account_order_details ), so the value
 * rows below print one label only — the page used to print the cart_success label and the account label together.
 */
$ec_gc_rows = array();
if ( '' !== $ec_gc_to ) {
	$ec_gc_rows[] = array( wp_kses_post( $ec_gc_lang->get_text( 'cart_success', 'cart_giftcard_receipt_to' ) ), esc_html( $ec_gc_to ) );
}
if ( '' !== $ec_gc_from ) {
	$ec_gc_rows[] = array( wp_kses_post( $ec_gc_lang->get_text( 'cart_success', 'cart_giftcard_receipt_from' ) ), esc_html( $ec_gc_from ) );
}

$ed::open(
	array(
		/* translators: %s: gift card code. */
		'title'     => sprintf( __( 'Gift Card %s', 'wp-easycart' ), $giftcard_id ),
		'preheader' => wp_strip_all_tags( $ec_gc_lang->get_text( 'cart_success', 'cart_giftcard_receipt_header' ) ),
		'logo_url'  => (string) $email_logo_url,
		'store_url' => (string) $store_page,
		'extra_css' => '@media print{'
			. 'body{background-color:#ffffff !important;}'
			. '.ec-email-bg{background-color:#ffffff !important;}'
			. '.ec-email-shell{padding:0 !important;}'
			. '.ec-email-container{border:0 !important;width:100% !important;max-width:100% !important;}'
			. '.ec-print-hide{display:none !important;}'
			. 'a{color:#111827 !important;text-decoration:none !important;}'
			. 'a[href]:after{content:"" !important;}'
			. '}',
	)
);

$ed::section_start( array( 'align' => 'center' ) );
$ed::heading( wp_kses_post( $ec_gc_lang->get_text( 'cart_success', 'cart_giftcard_receipt_header' ) ) );
$ed::section_end();

if ( '' !== $ec_gc_image ) {
	$ed::block( '<img src="' . esc_url( $ec_gc_image ) . '" alt="' . esc_attr( isset( $ec_gc_detail->title ) ? wp_strip_all_tags( $ec_gc_detail->title ) : '' ) . '" style="display:inline-block;width:260px;max-width:100%;height:auto;border-radius:8px;border:1px solid #e5e7eb;" />', array( 'align' => 'center', 'top' => 0, 'bottom' => 8 ) );
}

/* Amount ( hero ) and the code. */
$ed::section_start( array( 'align' => 'center', 'top' => 8 ) );
$ed::label( wp_kses_post( $ec_gc_lang->get_text( 'cart_success', 'cart_giftcard_receipt_amount' ) ) );
echo '<div style="' . esc_attr( $ed::css( 'heading' ) ) . 'font-size:36px;margin:0 0 16px 0;">' . $ed::money( $giftcard_total ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- both parts are escaped by the design class.
$ed::code_box(
	$giftcard_id,
	wp_kses_post( $ec_gc_lang->get_text( 'cart_success', 'cart_giftcard_receipt_id' ) )
);
$ed::section_end();

/* To / From / message. */
if ( $ec_gc_rows || '' !== $ec_gc_note ) {
	$ed::section_start( array( 'top' => 16 ) );
	$ed::card_start();
	foreach ( $ec_gc_rows as $ec_gc_row ) {
		echo '<div style="margin:0 0 8px 0;">' . $ed::get_label( $ec_gc_row[0] ) . '<span style="' . esc_attr( $ed::css( 'strong' ) ) . '">' . $ec_gc_row[1] . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- label and value escaped above.
	}
	if ( '' !== $ec_gc_note ) {
		echo '<div>' . $ed::get_label( esc_html( rtrim( trim( wp_strip_all_tags( $ec_gc_lang->get_text( 'account_order_details', 'account_orders_details_gift_message' ) ) ), ':' ) ) ) . '<span style="' . esc_attr( $ed::css( 'text' ) ) . '">' . nl2br( esc_html( $ec_gc_note ) ) . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- label and note escaped above.
	}
	$ed::card_end();
	$ed::section_end();
}

/* How to redeem. */
$ed::section_start( array( 'top' => 16 ) );
$ed::paragraph( wp_kses_post( $ec_gc_lang->get_text( 'cart_success', 'cart_giftcard_receipt_message' ) ) . ' <a href="' . esc_url( $store_page ) . '" target="_blank" style="' . esc_attr( $ed::css( 'link' ) ) . '">' . esc_html( $store_page ) . '</a>.' );
$ed::section_end();

/* Screen-only print button. */
echo '<tr class="ec-print-hide"><td class="ec-email-pad" align="center" style="padding:0 32px 8px 32px;">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup.
echo '<a href="#" onclick="window.print(); return false;" style="display:inline-block;padding:11px 22px;border-radius:6px;border:1px solid #d1d5db;background-color:#ffffff;' . esc_attr( $ed::css( 'strong' ) ) . 'text-decoration:none;">' . esc_html__( 'Print this gift card', 'wp-easycart' ) . '</a>';
echo '</td></tr>' . "\n";

$ed::close( array( 'signature' => false ) );
