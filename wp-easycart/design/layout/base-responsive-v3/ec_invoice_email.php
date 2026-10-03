<?php
/**
 * Invoice email ( Settings › Documents › Invoice email ): the email WP EasyCart PRO sends from an order's Send Email dialog
 * ( Invoice ), usually with the Invoice PDF attached and, while the order is unpaid, a Pay button to its pay link.
 *
 * Rendered by wp_easycart_documents::render( 'invoice_email', ... ) with $document ( wp_easycart_document ): the order, its
 * lines and the profile's switches ( $document->show() ). $document->args['pdf'] says whether the Invoice PDF goes with the
 * email ( its first line says so ); $document->args['invoice'] is the order's invoice ( ec_invoice row ) when one was issued.
 * In the Settings › Documents preview ( output 'preview' ) the Pay button and the due date show whatever the preview order's
 * state, so the profile's switches can be seen; the button links nowhere there.
 *
 * Tables and inline CSS only. Copy this file to your wp-easycart-data layout folder to customise it; the file name must
 * stay the same.
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
	$document = new wp_easycart_document( 'invoice_email', isset( $order_id ) ? (int) $order_id : 0, wp_easycart_documents::resolve( 'invoice_email', '', array(), isset( $order_id ) ? (int) $order_id : 0 ), array( 'output' => 'email' ) );
}
if ( ! $document->order ) {
	return;
}

global $wpdb;
$ed           = 'wp_easycart_email_design';
$ec_ie_lang   = wp_easycart_language();
$ec_ie_curr   = $GLOBALS['currency'];
$ec_ie_order  = $document->order;
$ec_ie_id     = (int) $ec_ie_order->order_id;
$ec_ie_extra  = wp_easycart_documents::order_extras( $ec_ie_order );
$ec_ie_record = ( isset( $document->args['invoice'] ) && is_object( $document->args['invoice'] ) ) ? $document->args['invoice'] : wp_easycart_documents::invoice_for_order( $ec_ie_id );
$ec_ie_sample = ( 'preview' === $document->output );
$ec_ie_pdf    = ! isset( $document->args['pdf'] ) || ! empty( $document->args['pdf'] );
$ec_ie_title  = wp_strip_all_tags( wp_easycart_documents::text( 'invoice_title', __( 'Invoice', 'wp-easycart' ) ) );
$ec_ie_terms  = ( $ec_ie_record && '' !== (string) $ec_ie_record->payment_terms ) ? (string) $ec_ie_record->payment_terms : $ec_ie_extra->payment_terms;
$ec_ie_due    = ( $ec_ie_record && '' !== (string) $ec_ie_record->due_date && 0 !== strpos( (string) $ec_ie_record->due_date, '0000' ) ) ? (string) $ec_ie_record->due_date : $ec_ie_extra->payment_due_date;

/* Paid? ( the order's status now ). A paid order has nothing due and no Pay button. */
$ec_ie_state_row              = clone $ec_ie_order;
$ec_ie_state_row->is_approved = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT is_approved FROM ec_orderstatus WHERE status_id = %d', (int) $ec_ie_order->orderstatus_id ) );
$ec_ie_unpaid                 = empty( $ec_ie_state_row->is_approved ) && (float) $ec_ie_order->grand_total > 0;

/* The Pay button: the order's pay link while this store's payment methods can take it ( Settings › Documents › Pay links ).
   A draft ( New order ) counts as payable: sending its invoice publishes it. */
$ec_ie_pay_url = '';
if ( $document->show( 'pay_button' ) && class_exists( 'wp_easycart_order_pay' ) && wp_easycart_order_pay::available() ) {
	if ( $ec_ie_sample ) {
		$ec_ie_pay_url = '#';
	} elseif ( $ec_ie_unpaid && in_array( wp_easycart_order_pay::state( $ec_ie_state_row ), array( 'payable', 'missing' ), true ) ) {
		$ec_ie_pay_url = wp_easycart_order_pay::url( $ec_ie_id );
	}
}
/* Bank transfer instructions when there is no Pay button. */
$ec_ie_bank = '';
if ( $document->show( 'bank_details' ) && '' === $ec_ie_pay_url && ( $ec_ie_unpaid || $ec_ie_sample ) && get_option( 'ec_option_use_direct_deposit' ) ) {
	$ec_ie_bank = trim( (string) get_option( 'ec_option_direct_deposit_message' ) );
}

$ec_ie_intro = $ec_ie_pdf
	? wp_easycart_documents::text( 'invoice_email_intro', __( 'Your invoice is attached as a PDF.', 'wp-easycart' ) )
	: wp_easycart_documents::text( 'invoice_email_intro_no_pdf', __( 'Here are the details of your invoice.', 'wp-easycart' ) );

$ed::open(
	wp_easycart_documents::open_args(
		'invoice_email',
		$document->fields,
		array(
			'title'     => $ec_ie_title . ' ' . ( $ec_ie_record ? $ec_ie_record->invoice_number : '#' . $ec_ie_id ),
			'preheader' => wp_strip_all_tags( $ec_ie_intro ),
		)
	)
);

/* Heading, first line and the invoice's key facts */
$ed::section_start();
$ed::heading( esc_html( $ec_ie_title . ( $ec_ie_record ? ' ' . $ec_ie_record->invoice_number : '' ) ) );
$ed::paragraph( $ec_ie_intro );
$ec_ie_pairs = array(
	array(
		'label' => rtrim( wp_easycart_documents::text( 'invoice_order_number', __( 'Order number:', 'wp-easycart' ) ), ': ' ),
		'value' => $document->show( 'order_number' ) ? esc_html( '#' . $ec_ie_id ) : '',
		'mono'  => true,
	),
	array(
		'label' => rtrim( wp_easycart_documents::text( 'invoice_date', __( 'Date:', 'wp-easycart' ) ), ': ' ),
		'value' => $document->show( 'order_date' ) ? esc_html( $document->date() ) : '',
	),
	array(
		'label' => wp_kses_post( $ec_ie_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_grand_total' ) ),
		'value' => $document->show( 'total' ) ? $ed::money( $ec_ie_order->grand_total ) : '',
	),
	array(
		'label' => rtrim( wp_easycart_documents::text( 'payment_terms_label', __( 'Terms:', 'wp-easycart' ) ), ': ' ),
		'value' => $document->show( 'due_date' ) ? esc_html( $ec_ie_terms ) : '',
	),
	array(
		'label' => rtrim( wp_easycart_documents::text( 'due_date_label', __( 'Due:', 'wp-easycart' ) ), ': ' ),
		'value' => ( $document->show( 'due_date' ) && '' !== $ec_ie_due && ( $ec_ie_unpaid || $ec_ie_sample ) ) ? '<strong style="color:#9a3412;">' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $ec_ie_due ) ) ) . '</strong>' : '',
	),
	array(
		'label' => rtrim( wp_easycart_documents::text( 'po_number_label', __( 'PO number:', 'wp-easycart' ) ), ': ' ),
		'value' => $document->show( 'po_number' ) ? esc_html( $ec_ie_extra->po_number ) : '',
		'mono'  => true,
	),
);
$ec_ie_facts = $ed::get_key_values( $ec_ie_pairs, array( 'per_row' => 3 ) );
if ( '' !== $ec_ie_facts ) {
	$ed::card_start();
	echo $ec_ie_facts; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by wp_easycart_email_design::get_key_values() from escaped values.
	$ed::card_end();
}
$ed::section_end();

/* Pay */
if ( '' !== $ec_ie_pay_url ) {
	$ed::button_row( $ec_ie_pay_url, html_entity_decode( wp_strip_all_tags( wp_easycart_documents::text( 'invoice_pay_button', __( 'Pay invoice', 'wp-easycart' ) ) ), ENT_QUOTES, 'UTF-8' ), array( 'top' => 16 ) );
} elseif ( '' !== $ec_ie_bank ) {
	$ed::section_start( array( 'top' => 16 ) );
	$ed::label( wp_easycart_documents::text( 'pay_bank_tab', __( 'Bank transfer', 'wp-easycart' ) ) );
	$ed::card_start( array( 'padding' => '12px 16px' ) );
	echo wp_kses_post( nl2br( $ec_ie_bank ) );
	$ed::card_end();
	$ed::section_end();
}

/* Billing address */
if ( $document->show( 'billing' ) ) {
	$ed::address_cards(
		array(
			array(
				'label'   => wp_easycart_documents::text( 'bill_to', __( 'Bill to', 'wp-easycart' ) ),
				'address' => $ed::address( $ec_ie_order, 'billing' ),
			),
		)
	);
}

/* Items and totals */
if ( $document->show( 'items' ) && $document->lines ) {
	$ed::items_start(
		array(
			'product' => wp_kses_post( $ec_ie_lang->get_text( 'cart_success', 'cart_payment_complete_details_header_1' ) ),
			'qty'     => wp_kses_post( $ec_ie_lang->get_text( 'cart_success', 'cart_payment_complete_details_header_2' ) ),
			'unit'    => wp_kses_post( $ec_ie_lang->get_text( 'cart_success', 'cart_payment_complete_details_header_3' ) ),
			'total'   => wp_kses_post( $ec_ie_lang->get_text( 'cart_success', 'cart_payment_complete_details_header_4' ) ),
		)
	);
	foreach ( $document->lines as $ec_ie_line ) {
		$ed::item_start(
			array(
				'image_url'   => $document->show( 'image' ) ? $document->image_url( $ec_ie_line ) : '',
				'image_alt'   => $ec_ie_lang->convert_text( $ec_ie_line->title ),
				'image_width' => 56,
				'title_html'  => wp_easycart_escape_html( $ec_ie_lang->convert_text( $ec_ie_line->title ) ),
			)
		);
		if ( $document->show( 'sku' ) && '' !== (string) $ec_ie_line->model_number ) {
			$ed::detail( esc_html( $ec_ie_line->model_number ), array( 'nolink' => true ) );
		}
		if ( $document->show( 'options' ) ) {
			foreach ( $document->options( $ec_ie_line ) as $ec_ie_option ) {
				$ed::option_detail( esc_html( $ec_ie_option['label'] ), esc_html( $ec_ie_option['value'] ) );
			}
		}
		do_action( 'wp_easycart_invoice_email_line_item', $ec_ie_line, $document );
		$ed::item_end(
			array(
				'qty'        => $ec_ie_line->quantity,
				'unit_html'  => $ed::money( $ec_ie_line->unit_price ),
				'total_html' => $ed::money( $ec_ie_line->total_price ),
			)
		);
	}
	$ed::items_end();

	$ec_ie_totals   = array();
	$ec_ie_totals[] = array( wp_kses_post( $ec_ie_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_subtotal' ) ), $ed::money( $ec_ie_order->sub_total ) );
	if ( (float) $ec_ie_order->tip_total > 0 ) {
		$ec_ie_totals[] = array( wp_kses_post( $ec_ie_lang->get_text( 'cart_totals', 'cart_totals_tip' ) ), $ed::money( $ec_ie_order->tip_total ) );
	}
	if ( get_option( 'ec_option_use_shipping' ) && (float) $ec_ie_order->shipping_total > 0 ) {
		$ec_ie_totals[] = array( wp_kses_post( $ec_ie_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_shipping' ) ), $ed::money( $ec_ie_order->shipping_total ) );
	}
	if ( (float) $ec_ie_order->discount_total > 0 && apply_filters( 'wp_easycart_order_details_discount_display', true, $ec_ie_order ) ) {
		$ec_ie_totals[] = array( wp_kses_post( $ec_ie_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_discount' ) ), $ed::ltr( '-' . $ec_ie_curr->get_currency_display( $ec_ie_order->discount_total ) ) );
	}
	if ( (float) $ec_ie_order->tax_total > 0 ) {
		$ec_ie_totals[] = array( wp_kses_post( $ec_ie_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_tax' ) ), $ed::money( $ec_ie_order->tax_total ) );
	}
	if ( (float) $ec_ie_order->duty_total > 0 ) {
		$ec_ie_totals[] = array( wp_kses_post( $ec_ie_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_duty' ) ), $ed::money( $ec_ie_order->duty_total ) );
	}
	if ( (float) $ec_ie_order->vat_total > 0 ) {
		$ec_ie_vat_rate = rtrim( rtrim( number_format( (float) $ec_ie_order->vat_rate, 3, '.', '' ), '0' ), '.' );
		$ec_ie_totals[] = array( wp_kses_post( $ec_ie_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_vat' ) ) . ( (float) $ec_ie_order->vat_rate > 0 ? esc_html( $ec_ie_vat_rate ) . '%' : '' ), $ed::money( $ec_ie_order->vat_total ) );
	}
	foreach ( array( 'gst', 'pst', 'hst' ) as $ec_ie_ca ) {
		if ( (float) $ec_ie_order->{$ec_ie_ca . '_total'} > 0 ) {
			$ec_ie_ca_label = function_exists( 'wp_easycart_canada_tax_label' ) ? wp_easycart_canada_tax_label( $ec_ie_ca, $ec_ie_order->shipping_state ) : strtoupper( $ec_ie_ca );
			$ec_ie_totals[] = array( esc_html( $ec_ie_ca_label ), $ed::money( $ec_ie_order->{$ec_ie_ca . '_total'} ) );
		}
	}
	foreach ( $document->fees() as $ec_ie_fee ) {
		$ec_ie_totals[] = array( esc_html( $ec_ie_fee->fee_label ), $ed::money( $ec_ie_fee->fee_total ) );
	}
	if ( (float) $ec_ie_order->refund_total > 0 ) {
		$ec_ie_totals[] = array(
			'label' => wp_kses_post( $ec_ie_lang->get_text( 'account_order_details', 'account_orders_details_refund_total_short' ) ),
			'value' => $ed::ltr( '-' . $ec_ie_curr->get_currency_display( $ec_ie_order->refund_total ) ),
			'tone'  => 'danger',
		);
	}
	$ed::totals( $ec_ie_totals, array( wp_kses_post( $ec_ie_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_grand_total' ) ), $ed::money( apply_filters( 'wp_easycart_order_display_grand_total', $ec_ie_order->grand_total, $ec_ie_order ) ) ) );
}

do_action( 'wp_easycart_invoice_email_after', $document );

$ed::close( wp_easycart_documents::close_args( 'invoice_email', $document->fields ) );
