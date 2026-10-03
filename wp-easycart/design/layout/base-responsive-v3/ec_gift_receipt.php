<?php
/**
 * Gift receipt ( Settings › Documents › Gift receipt ): what the person receiving a gift order gets, by email or as a PDF.
 * Never prices. WP EasyCart PRO sends it to the gift recipient's email when the order ships ( gift purchases ) and from
 * an order's Send Email dialog.
 *
 * Rendered by wp_easycart_documents::render( 'gift_receipt', ... ) with $document ( wp_easycart_document ): the order,
 * its lines ( or the ones chosen for this parcel ) and the profile's switches ( $document->show() ). Tables and inline CSS
 * only: dompdf renders this file for the PDF. Copy this file to your wp-easycart-data layout folder to customise it; the
 * file name must stay the same.
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
	$document = new wp_easycart_document( 'gift_receipt', isset( $order_id ) ? (int) $order_id : 0, wp_easycart_documents::resolve( 'gift_receipt', '', array(), isset( $order_id ) ? (int) $order_id : 0 ) );
}
if ( ! $document->order ) {
	return;
}

$ed          = 'wp_easycart_email_design';
$ec_gr_lang  = wp_easycart_language();
$ec_gr_order = $document->order;
$ec_gr_email = ( 'email' === $document->output );
$ec_gr_extra = wp_easycart_documents::order_extras( $ec_gr_order );
$ec_gr_title = wp_easycart_documents::text( 'gift_receipt_title', __( 'Gift receipt', 'wp-easycart' ) );
$ec_gr_from  = trim( (string) $ec_gr_order->billing_first_name );

$ed::open(
	wp_easycart_documents::open_args(
		'gift_receipt',
		$document->fields,
		array(
			'title' => wp_strip_all_tags( $ec_gr_title ) . ' ' . (int) $ec_gr_order->order_id,
			'flat'  => ! $ec_gr_email,
		)
	)
);

/* Title and a line for the recipient */
$ed::section_start();
$ed::heading( $ec_gr_title );
$ed::paragraph( wp_easycart_documents::text( 'gift_receipt_intro', __( 'Someone has sent you a gift. Here is what is on its way to you.', 'wp-easycart' ) ) );
$ed::key_values(
	array(
		array(
			'label' => wp_easycart_documents::text( 'gift_from_label', __( 'From', 'wp-easycart' ) ),
			'value' => ( $document->show( 'from' ) && '' !== $ec_gr_from ) ? esc_html( $ec_gr_from ) : '',
		),
		array(
			'label' => wp_easycart_documents::text( 'order_number', __( 'Order', 'wp-easycart' ) ),
			'value' => $document->show( 'order_number' ) ? esc_html( '#' . $ec_gr_order->order_id ) : '',
			'mono'  => true,
		),
		array(
			'label' => wp_easycart_documents::text( 'order_date', __( 'Date', 'wp-easycart' ) ),
			'value' => $document->show( 'order_number' ) ? esc_html( $document->date() ) : '',
		),
	)
);
$ed::section_end();

/* The buyer's message */
if ( $document->show( 'gift_message' ) && '' !== $ec_gr_extra->gift_message ) {
	$ed::section_start( array( 'top' => 4 ) );
	$ed::label( wp_easycart_documents::text( 'gift_message_label', __( 'Gift message', 'wp-easycart' ) ) );
	$ed::card_start( array( 'padding' => '16px 18px', 'background' => '#f7faf6' ) );
	echo '<div style="font-family:Georgia,\'Times New Roman\',serif;font-style:italic;font-size:16px;line-height:1.55;color:#1f2937;">' . nl2br( esc_html( $ec_gr_extra->gift_message ) ) . '</div>';
	$ed::card_end();
	$ed::section_end();
}

/* Where it is going */
if ( $document->show( 'shipping' ) && get_option( 'ec_option_use_shipping' ) && '' !== trim( (string) $ec_gr_order->shipping_address_line_1 ) ) {
	$ec_gr_ship          = $ed::address( $ec_gr_order, 'shipping' );
	$ec_gr_ship['phone'] = '';
	$ed::address_cards(
		array(
			array(
				'label'   => wp_easycart_documents::text( 'ship_to', __( 'Ship to', 'wp-easycart' ) ),
				'address' => $ec_gr_ship,
			),
		)
	);
}

/* Items, without prices */
$ed::items_start(
	array(
		'product' => wp_kses_post( $ec_gr_lang->get_text( 'cart_success', 'cart_payment_complete_details_header_1' ) ),
		'qty'     => wp_kses_post( $ec_gr_lang->get_text( 'cart_success', 'cart_payment_complete_details_header_2' ) ),
	)
);
foreach ( $document->lines as $ec_gr_line ) {
	$ed::item_start(
		array(
			'image_url'   => $document->show( 'image' ) ? $document->image_url( $ec_gr_line ) : '',
			'image_alt'   => $ec_gr_lang->convert_text( $ec_gr_line->title ),
			'image_width' => 64,
			'title_html'  => wp_easycart_escape_html( $ec_gr_lang->convert_text( $ec_gr_line->title ) ),
		)
	);
	if ( $document->show( 'sku' ) && '' !== (string) $ec_gr_line->model_number ) {
		$ed::detail( esc_html( $ec_gr_line->model_number ), array( 'nolink' => true ) );
	}
	if ( $document->show( 'options' ) ) {
		foreach ( $document->options( $ec_gr_line ) as $ec_gr_option ) {
			$ed::option_detail( esc_html( $ec_gr_option['label'] ), esc_html( $ec_gr_option['value'] ) );
		}
	}
	do_action( 'wp_easycart_gift_receipt_line_item', $ec_gr_line, $document );
	$ed::item_end( array( 'qty' => $ec_gr_line->quantity ) );
}
$ed::items_end();

/* Items still to come in another parcel */
if ( $document->is_partial() ) {
	$ed::section_start();
	$ed::label( wp_easycart_documents::text( 'items_to_follow', __( 'To follow in a separate shipment', 'wp-easycart' ) ) );
	$ec_gr_follow = array();
	foreach ( $document->held_back as $ec_gr_line ) {
		$ec_gr_follow[] = esc_html( wp_strip_all_tags( $ec_gr_lang->convert_text( $ec_gr_line->title ) ) . ' × ' . (int) $ec_gr_line->quantity );
	}
	$ed::paragraph( implode( '<br />', $ec_gr_follow ), array( 'nolink' => true ) );
	$ed::section_end();
}

/* How to exchange */
if ( $document->show( 'returns_note' ) ) {
	$ed::section_start( array( 'top' => 20 ) );
	/* 6.0.2: a profile that hides the order number gets a note that does not ask for it. */
	if ( $document->show( 'order_number' ) ) {
		$ed::paragraph( wp_easycart_documents::text( 'gift_returns_note', __( 'Want to exchange something? Get in touch with us and quote the order number above.', 'wp-easycart' ) ), array( 'tone' => 'muted', 'margin' => '0' ) );
	} else {
		$ed::paragraph( wp_easycart_documents::text( 'gift_returns_note_no_number', __( 'Want to exchange something? Get in touch with us and we will help you find your gift.', 'wp-easycart' ) ), array( 'tone' => 'muted', 'margin' => '0' ) );
	}
	$ed::section_end();
}

do_action( 'wp_easycart_gift_receipt_after', $document );

$ed::close( wp_easycart_documents::close_args( 'gift_receipt', $document->fields, array( 'signature_text' => false ) ) );
