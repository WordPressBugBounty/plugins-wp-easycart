<?php
/**
 * Credit note PDF ( Settings › Documents › Credit note ): issued by WP EasyCart PRO when an order that has an invoice is
 * refunded. It carries its own number ( CN-{year}-{number} by default ), the invoice it corrects and the amount credited.
 *
 * Rendered by wp_easycart_documents::render( 'credit_note', ... ) with $document ( wp_easycart_document ). The credit note
 * record ( ec_invoice row, invoice_type credit_note ) is $document->args['credit_note'], the invoice it belongs to is
 * $document->args['invoice'], and the order comes from the credit note's snapshot, so the PDF never changes once issued.
 *
 * Always "flat": dompdf draws this file. Tables and inline styles only. Copy this file to your theme / wp-easycart-data
 * layout folder to customise it; the file name must stay the same.
 *
 * @since 6.0.2
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'wp_easycart_email_design' ) ) {
	require_once EC_PLUGIN_DIRECTORY . '/inc/classes/core/class-wp-easycart-email-design.php';
}
if ( ! isset( $document ) || ! ( $document instanceof wp_easycart_document ) ) {
	$document = new wp_easycart_document( 'credit_note', isset( $order_id ) ? (int) $order_id : 0, wp_easycart_documents::resolve( 'credit_note', '', array(), isset( $order_id ) ? (int) $order_id : 0 ) );
}
if ( ! $document->order ) {
	return;
}

$ed          = 'wp_easycart_email_design';
$ec_cn_lang  = wp_easycart_language();
$ec_cn_curr  = $GLOBALS['currency'];
$ec_cn_order = $document->order;
$ec_cn_id    = (int) $ec_cn_order->order_id;
$ec_cn_note  = ( isset( $document->args['credit_note'] ) && is_object( $document->args['credit_note'] ) ) ? $document->args['credit_note'] : null;
$ec_cn_inv   = ( isset( $document->args['invoice'] ) && is_object( $document->args['invoice'] ) ) ? $document->args['invoice'] : null;
$ec_cn_po    = isset( $ec_cn_order->po_number ) ? trim( (string) $ec_cn_order->po_number ) : '';
$ec_cn_date  = ( $ec_cn_note && '' !== (string) $ec_cn_note->issue_date ) ? date_i18n( get_option( 'date_format' ), strtotime( (string) $ec_cn_note->issue_date ) ) : $document->date();
/* A preview before any refund: the order's refunded amount, or its total. */
$ec_cn_amt = $ec_cn_note ? (float) $ec_cn_note->amount : ( ( (float) $ec_cn_order->refund_total > 0 ) ? (float) $ec_cn_order->refund_total : (float) $ec_cn_order->grand_total );

/* Heading block: business details on the left; the title, number, date and the invoice it corrects on the right. */
$ec_cn_title = wp_strip_all_tags( wp_easycart_documents::text( 'credit_note_title', __( 'Credit note', 'wp-easycart' ) ) );
$ec_cn_head  = '';
$ec_cn_cell  = 'vertical-align:top;padding:0 0 10px 0;font-size:10px;line-height:1.45;color:#1d2327;font-family:' . $ed::ctx()['font'] . ';';
if ( $document->show( 'seller' ) || $document->show( 'heading' ) ) {
	$ec_cn_seller = trim( (string) get_option( 'ec_option_pdf_seller_details', '' ) );
	if ( '' === $ec_cn_seller ) {
		$ec_cn_seller = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	}
	$ec_cn_head  = '<table role="presentation" class="wpec-pdf-head" width="100%" border="0" cellpadding="0" cellspacing="0" style="width:100%;margin:0 0 8px 0;border-bottom:2px solid #1d2327;"><tr>';
	$ec_cn_head .= '<td class="wpec-pdf-seller" width="55%" style="' . esc_attr( $ec_cn_cell ) . '">' . ( $document->show( 'seller' ) ? nl2br( esc_html( $ec_cn_seller ) ) : '&nbsp;' ) . '</td>';
	$ec_cn_head .= '<td width="45%" align="right" style="' . esc_attr( $ec_cn_cell . 'text-align:right;' ) . '">';
	if ( $document->show( 'heading' ) ) {
		$ec_cn_head .= '<div class="wpec-pdf-title" style="font-size:22px;font-weight:bold;letter-spacing:1px;text-transform:uppercase;margin:0 0 4px 0;">' . esc_html( $ec_cn_title ) . '</div>';
		if ( $ec_cn_note ) {
			$ec_cn_head .= '<div>' . wp_easycart_documents::text( 'credit_note_number_label', __( 'Credit note number:', 'wp-easycart' ) ) . ' <strong>' . esc_html( $ec_cn_note->invoice_number ) . '</strong></div>';
		}
		$ec_cn_head .= '<div>' . wp_easycart_documents::text( 'invoice_date', __( 'Date:', 'wp-easycart' ) ) . ' ' . esc_html( $ec_cn_date ) . '</div>';
		if ( $ec_cn_inv ) {
			$ec_cn_head .= '<div>' . wp_easycart_documents::text( 'credit_note_for_invoice', __( 'For invoice:', 'wp-easycart' ) ) . ' ' . esc_html( $ec_cn_inv->invoice_number ) . '</div>';
		}
		$ec_cn_head .= '<div>' . wp_easycart_documents::text( 'invoice_order_number', __( 'Order number:', 'wp-easycart' ) ) . ' ' . esc_html( $ec_cn_id ) . '</div>';
	}
	if ( $document->show( 'po_number' ) && '' !== $ec_cn_po ) {
		$ec_cn_head .= '<div>' . wp_easycart_documents::text( 'po_number_label', __( 'PO number:', 'wp-easycart' ) ) . ' ' . esc_html( $ec_cn_po ) . '</div>';
	}
	$ec_cn_head .= '</td></tr></table>';
}

$ed::open(
	wp_easycart_documents::open_args(
		'credit_note',
		$document->fields,
		array(
			'title'     => $ec_cn_title . ' ' . ( $ec_cn_note ? $ec_cn_note->invoice_number : $ec_cn_id ),
			'flat'      => true,
			'top_html'  => $ec_cn_head,
			'extra_css' => ( 'preview' === $document->output ) ? 'body{padding:32px 40px !important;box-sizing:border-box !important;}' : '',
		)
	)
);

/* Who it is for */
$ec_cn_cards = array();
if ( $document->show( 'billing' ) && '' !== trim( (string) $ec_cn_order->billing_address_line_1 . $ec_cn_order->billing_city ) ) {
	$ec_cn_cards[] = array(
		'label'   => wp_kses_post( $ec_cn_lang->get_text( 'cart_success', 'cart_payment_complete_billing_label' ) ),
		'address' => $ed::address( $ec_cn_order, 'billing' ),
	);
}
$ec_cn_notes = '';
if ( $document->show( 'vat_number' ) && '' !== (string) $ec_cn_order->vat_registration_number ) {
	$ec_cn_notes = '<strong>' . wp_kses_post( $ec_cn_lang->get_text( 'cart_billing_information', 'cart_billing_information_vat_registration_number' ) ) . ':</strong> ' . esc_html( $ec_cn_order->vat_registration_number );
}
if ( $ec_cn_cards || '' !== $ec_cn_notes ) {
	$ed::address_cards( $ec_cn_cards, $ec_cn_notes );
}

/* The order's items, for reference ( no prices: the credit is the amount below ) */
if ( $document->show( 'items' ) && $document->lines ) {
	$ed::items_start(
		array(
			'product' => wp_kses_post( $ec_cn_lang->get_text( 'cart_success', 'cart_payment_complete_details_header_1' ) ),
			'qty'     => wp_kses_post( $ec_cn_lang->get_text( 'cart_success', 'cart_payment_complete_details_header_2' ) ),
		)
	);
	foreach ( $document->lines as $ec_cn_line ) {
		$ed::item_start( array( 'title_html' => wp_kses_post( $ec_cn_line->title ) ) );
		if ( '' !== (string) $ec_cn_line->model_number ) {
			$ed::detail( esc_html( $ec_cn_line->model_number ), array( 'nolink' => true ) );
		}
		$ed::item_end( array( 'qty' => $ec_cn_line->quantity ) );
	}
	$ed::items_end();
}

/* The amount credited */
$ed::totals(
	array(
		array( wp_easycart_documents::text( 'credit_note_invoice_total', __( 'Invoice total', 'wp-easycart' ) ), $ed::money( $ec_cn_inv ? (float) $ec_cn_inv->amount : (float) $ec_cn_order->grand_total ) ),
	),
	array( wp_easycart_documents::text( 'amount_credited', __( 'Amount credited', 'wp-easycart' ) ), $ed::ltr( '-' . $ec_cn_curr->get_currency_display( $ec_cn_amt ) ) )
);

do_action( 'wp_easycart_credit_note_pdf_after', $document );

$ed::close( wp_easycart_documents::close_args( 'credit_note', $document->fields, array( 'signature_text' => false ) ) );
