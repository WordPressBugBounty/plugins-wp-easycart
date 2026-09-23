<?php
/**
 * Packing slip: the admin print ( one order or a bulk run ), the PRO PDF and the packing slip email.
 *
 * 6.0.1: rebuilt on the shared email design ( wp_easycart_email_design ) and driven by a packing slip profile from
 * Settings › Documents instead of the ec_option_packing_slip_show_* options directly. Rendered by
 * wp_easycart_documents::render( 'packing_slip', … ), which puts $document ( wp_easycart_document ) in scope: the order,
 * the lines on this slip ( all of them, or the ones chosen for this box ), the lines to follow, and show( $field ).
 * The variables the pre-6.0.1 template used ( $order, $order_details, $mysqli, $store_page, $email_logo_url, the
 * formatted totals … ) are still passed, so a copy of the old file in the data folder keeps working; the Standard
 * profile keeps those options in step. Tables and inline CSS only: dompdf renders this file for the PRO PDF.
 *
 * Copy this file to your wp-easycart-data layout folder to customise it; the file name must stay the same.
 *
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'wp_easycart_email_design' ) ) {
	require_once EC_PLUGIN_DIRECTORY . '/inc/classes/core/class-wp-easycart-email-design.php';
}
if ( ! isset( $document ) || ! ( $document instanceof wp_easycart_document ) ) {
	$document = new wp_easycart_document( 'packing_slip', isset( $order_id ) ? (int) $order_id : 0, wp_easycart_documents::resolve( 'packing_slip' ) );
}
if ( ! $document->order ) {
	return;
}

$ed          = 'wp_easycart_email_design';
$ec_ps_lang  = wp_easycart_language();
$ec_ps_order = $document->order;
$ec_ps_curr  = $GLOBALS['currency'];
$ec_ps_email = ( 'email' === $document->output );
$ec_ps_price = $document->show( 'prices' );
$ec_ps_title = wp_easycart_documents::text( 'packing_slip_title', __( 'Packing Slip', 'wp-easycart' ) );

/* Offers v2: free-gift and bundle-child markers, keyed by orderdetail_id. */
$wpec_offer_line_flags = array();
if ( function_exists( 'wp_easycart_offers_active' ) && wp_easycart_offers_active() ) {
	global $wpdb;
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT orderdetail_id, product_id, is_free_gift, bundle_group_key, bundle_product_id FROM ec_orderdetail WHERE order_id = %d', (int) $ec_ps_order->order_id ) ) as $wpec_offer_flag_row ) {
		$wpec_offer_line_flags[ (int) $wpec_offer_flag_row->orderdetail_id ] = $wpec_offer_flag_row;
	}
}

/* The logo switch and, with 6.0.1, this document's own logo and size ( Settings › Documents ). */
$ed::open(
	wp_easycart_documents::open_args(
		'packing_slip',
		$document->fields,
		array(
			'title' => wp_strip_all_tags( $ec_ps_title ) . ' ' . (int) $ec_ps_order->order_id,
			'flat'  => ! $ec_ps_email,
		)
	)
);

/* Title, order number, date */
$ed::section_start();
$ed::heading( $ec_ps_title );
$ed::key_values(
	array(
		array(
			'label' => wp_easycart_documents::text( 'order_number', __( 'Order', 'wp-easycart' ) ),
			'value' => $document->show( 'order_number' ) ? esc_html( '#' . $ec_ps_order->order_id ) : '',
			'mono'  => true,
		),
		array(
			'label' => wp_easycart_documents::text( 'order_date', __( 'Date', 'wp-easycart' ) ),
			'value' => $document->show( 'order_date' ) ? esc_html( $document->date() ) : '',
		),
	)
);
$ed::section_end();

/* Addresses ( ship to first: it is what the packer reads ) */
$ec_ps_cards = array();
if ( $document->show( 'shipping' ) && get_option( 'ec_option_use_shipping' ) ) {
	$ec_ps_ship = $ed::address( $ec_ps_order, 'shipping' );
	if ( ! $document->show( 'phone' ) ) {
		$ec_ps_ship['phone'] = '';
	}
	$ec_ps_cards[] = array(
		'label'   => wp_easycart_documents::text( 'ship_to', __( 'Ship to', 'wp-easycart' ) ),
		'address' => $ec_ps_ship,
	);
}
if ( $document->show( 'billing' ) ) {
	$ec_ps_bill = $ed::address( $ec_ps_order, 'billing' );
	if ( ! $document->show( 'phone' ) ) {
		$ec_ps_bill['phone'] = '';
	}
	$ec_ps_cards[] = array(
		'label'   => wp_easycart_documents::text( 'bill_to', __( 'Bill to', 'wp-easycart' ) ),
		'address' => $ec_ps_bill,
	);
}
$ed::address_cards( $ec_ps_cards, ( $document->show( 'email' ) && '' !== (string) $ec_ps_order->user_email ) ? esc_html( $ec_ps_order->user_email ) : '' );

/* Shipping method, carrier, tracking */
if ( $document->show( 'tracking' ) && ( '' !== trim( (string) $ec_ps_order->shipping_method ) || '' !== trim( (string) $ec_ps_order->tracking_number ) ) ) {
	$ed::section_start( array( 'top' => 0 ) );
	$ed::card_start( array( 'padding' => '10px 16px' ) );
	$ed::key_values(
		array(
			array(
				'label' => wp_easycart_documents::text( 'shipping_method', __( 'Shipping', 'wp-easycart' ) ),
				'value' => esc_html( wp_unslash( (string) $ec_ps_order->shipping_method ) ),
			),
			array(
				'label' => wp_easycart_documents::text( 'carrier', __( 'Carrier', 'wp-easycart' ) ),
				'value' => esc_html( (string) $ec_ps_order->shipping_carrier ),
			),
			array(
				'label' => wp_easycart_documents::text( 'tracking', __( 'Tracking', 'wp-easycart' ) ),
				'value' => esc_html( (string) $ec_ps_order->tracking_number ),
				'mono'  => true,
			),
		)
	);
	$ed::card_end();
	$ed::section_end();
}

/* Items */
$ec_ps_labels = array(
	'product' => wp_kses_post( $ec_ps_lang->get_text( 'cart_success', 'cart_payment_complete_details_header_1' ) ),
	'qty'     => wp_kses_post( $ec_ps_lang->get_text( 'cart_success', 'cart_payment_complete_details_header_2' ) ),
);
if ( $ec_ps_price ) {
	$ec_ps_labels['unit']  = wp_kses_post( $ec_ps_lang->get_text( 'cart_success', 'cart_payment_complete_details_header_3' ) );
	$ec_ps_labels['total'] = wp_kses_post( $ec_ps_lang->get_text( 'cart_success', 'cart_payment_complete_details_header_4' ) );
}
$ed::items_start( $ec_ps_labels );
foreach ( $document->lines as $ec_ps_line ) {
	$ec_ps_title_html = $document->show( 'title' ) ? wp_easycart_escape_html( $ec_ps_lang->convert_text( $ec_ps_line->title ) ) : '';
	$wpec_line_flags  = isset( $wpec_offer_line_flags[ (int) $ec_ps_line->orderdetail_id ] ) ? $wpec_offer_line_flags[ (int) $ec_ps_line->orderdetail_id ] : false;
	if ( $wpec_line_flags && ! empty( $wpec_line_flags->is_free_gift ) && function_exists( 'wp_easycart_offers_text' ) ) {
		$ec_ps_title_html .= ' <strong>[' . wp_kses_post( wp_easycart_offers_text( 'cart_offers', 'gift_line_label' ) ) . ']</strong>';
	}
	if ( $wpec_line_flags && '' !== (string) $wpec_line_flags->bundle_group_key && (int) $wpec_line_flags->bundle_product_id !== (int) $wpec_line_flags->product_id && function_exists( 'wp_easycart_offers_text' ) ) {
		$ec_ps_title_html .= ' <strong>[' . wp_kses_post( wp_easycart_offers_text( 'cart_offers', 'bundle_line_label' ) ) . ' #' . esc_html( substr( (string) $wpec_line_flags->bundle_group_key, 0, 6 ) ) . ']</strong>';
	}
	$ed::item_start(
		array(
			'image_url'   => $document->show( 'image' ) ? $document->image_url( $ec_ps_line ) : '',
			'image_alt'   => $ec_ps_lang->convert_text( $ec_ps_line->title ),
			'image_width' => 56,
			'title_html'  => $ec_ps_title_html,
		)
	);
	if ( $document->show( 'sku' ) && '' !== (string) $ec_ps_line->model_number ) {
		$ed::detail( esc_html( $ec_ps_line->model_number ), array( 'nolink' => true ) );
	}
	if ( $document->show( 'options' ) ) {
		foreach ( $document->options( $ec_ps_line ) as $ec_ps_option ) {
			$ed::option_detail( esc_html( $ec_ps_option['label'] ), esc_html( $ec_ps_option['value'] ) );
		}
	}
	if ( ! empty( $ec_ps_line->gift_card_to_name ) ) {
		$ed::detail( wp_kses_post( $ec_ps_lang->get_text( 'account_order_details', 'account_orders_details_gift_to' ) ) . esc_html( $ec_ps_line->gift_card_to_name ) );
	}
	if ( ! empty( $ec_ps_line->gift_card_from_name ) ) {
		$ed::detail( wp_kses_post( $ec_ps_lang->get_text( 'account_order_details', 'account_orders_details_gift_from' ) ) . esc_html( $ec_ps_line->gift_card_from_name ) );
	}
	if ( ! empty( $ec_ps_line->gift_card_message ) ) {
		$ed::detail( wp_kses_post( $ec_ps_lang->get_text( 'account_order_details', 'account_orders_details_gift_message' ) ) . esc_html( $ec_ps_line->gift_card_message ) );
	}
	do_action( 'wp_easycart_packing_slip_line_item', $ec_ps_line, $document );
	$ec_ps_end = array( 'qty' => $ec_ps_line->quantity );
	if ( $ec_ps_price ) {
		$ec_ps_end['unit_html']  = $ed::ltr( $ec_ps_curr->get_currency_display( $ec_ps_line->unit_price ) );
		$ec_ps_end['total_html'] = $ed::ltr( $ec_ps_curr->get_currency_display( $ec_ps_line->total_price ) );
	}
	$ed::item_end( $ec_ps_end );
}
$ed::items_end();

/* Chosen items: what is not in this box */
if ( $document->is_partial() ) {
	$ed::section_start();
	$ed::label( wp_easycart_documents::text( 'items_to_follow', __( 'To follow in a separate shipment', 'wp-easycart' ) ) );
	$ec_ps_follow = array();
	foreach ( $document->held_back as $ec_ps_line ) {
		$ec_ps_follow[] = esc_html( wp_strip_all_tags( $ec_ps_lang->convert_text( $ec_ps_line->title ) ) . ' × ' . (int) $ec_ps_line->quantity );
	}
	$ed::paragraph( implode( '<br />', $ec_ps_follow ), array( 'nolink' => true ) );
	$ed::section_end();
}

/* Totals ( the whole order's, so they are left off a slip that carries only some of its items ) */
if ( $ec_ps_price && ! $document->is_partial() ) {
	$ec_ps_totals = array();
	if ( $document->show( 'subtotal' ) ) {
		$ec_ps_totals[] = array( wp_kses_post( $ec_ps_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_subtotal' ) ), $ed::money( $ec_ps_order->sub_total ) );
	}
	if ( $document->show( 'tip' ) && $ec_ps_order->tip_total > 0 ) {
		$ec_ps_totals[] = array( wp_kses_post( $ec_ps_lang->get_text( 'cart_totals', 'cart_totals_tip' ) ), $ed::money( $ec_ps_order->tip_total ) );
	}
	if ( $document->show( 'shipping_total' ) && $ec_ps_order->shipping_total > 0 ) {
		$ec_ps_totals[] = array( wp_kses_post( $ec_ps_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_shipping' ) ), $ed::money( $ec_ps_order->shipping_total ) );
	}
	if ( $document->show( 'discounts' ) && 0 != $ec_ps_order->discount_total ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- numeric string from the database.
		$ec_ps_totals[] = array( wp_kses_post( $ec_ps_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_discount' ) ), $ed::ltr( '-' . $ec_ps_curr->get_currency_display( $ec_ps_order->discount_total ) ) );
	}
	if ( $document->show( 'tax' ) ) {
		if ( $ec_ps_order->tax_total > 0 ) {
			$ec_ps_totals[] = array( wp_kses_post( $ec_ps_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_tax' ) ), $ed::money( $ec_ps_order->tax_total ) );
		}
		if ( $ec_ps_order->duty_total > 0 ) {
			$ec_ps_totals[] = array( wp_kses_post( $ec_ps_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_duty' ) ), $ed::money( $ec_ps_order->duty_total ) );
		}
		if ( 0 != $ec_ps_order->vat_rate ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- numeric string from the database.
			$ec_ps_totals[] = array( wp_kses_post( $ec_ps_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_vat' ) ) . esc_html( number_format( (float) $ec_ps_order->vat_rate, 0, '', '' ) ) . '%', $ed::money( $ec_ps_order->vat_total ) );
		}
		foreach ( array( 'gst', 'pst', 'hst' ) as $ec_ps_ca ) {
			if ( 0 != $ec_ps_order->{$ec_ps_ca . '_rate'} ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- numeric string from the database.
				$ec_ps_ca_label = function_exists( 'wp_easycart_canada_tax_label' ) ? wp_easycart_canada_tax_label( $ec_ps_ca, $ec_ps_order->shipping_state ) : strtoupper( $ec_ps_ca );
				$ec_ps_totals[] = array( esc_html( $ec_ps_ca_label . ' (' . $ec_ps_order->{$ec_ps_ca . '_rate'} . '%)' ), $ed::money( $ec_ps_order->{$ec_ps_ca . '_total'} ) );
			}
		}
	}
	$ec_ps_grand = $document->show( 'grand_total' ) ? array( wp_kses_post( $ec_ps_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_grand_total' ) ), $ed::money( $ec_ps_order->grand_total ) ) : array();
	if ( $ec_ps_totals || $ec_ps_grand ) {
		$ed::totals( $ec_ps_totals, $ec_ps_grand );
	}
}

/* Customer's order notes */
if ( $document->show( 'order_notes' ) && '' !== trim( (string) $ec_ps_order->order_customer_notes ) ) {
	$ed::section_start();
	$ed::label( wp_kses_post( $ec_ps_lang->get_text( 'cart_payment_information', 'cart_payment_information_order_notes_title' ) ) );
	$ed::card_start( array( 'padding' => '12px 14px' ) );
	echo nl2br( esc_html( wp_unslash( $ec_ps_order->order_customer_notes ) ) );
	$ed::card_end();
	$ed::section_end();
}

do_action( 'wp_easycart_packing_slip_after', $document );

/* No signature text on a slip; the footer image only when the profile asks for it ( off by default ). */
$ed::close( wp_easycart_documents::close_args( 'packing_slip', $document->fields, array( 'signature_text' => false ) ) );
