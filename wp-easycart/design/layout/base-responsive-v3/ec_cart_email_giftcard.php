<?php
/**
 * Gift card delivery email ( sent to the gift card recipient, BCC to the store ).
 *
 * Included by ec_orderdisplay::send_gift_cards(), ec_order::send_gift_card_email(),
 * wp_easycart_admin_orders::send_gift_card_email() and the PRO ec_admin_orders::send_gift_card_email() with:
 *   $cart_item ( gift_card_to_name, gift_card_from_name, gift_card_message, gift_card_value, title, image1,
 *   image1_optionitem, is_deconetwork, deconetwork_image_link ), $giftcard_id, $email_logo_url,
 *   $store_page ( not set by ec_order::send_gift_card_email(); the store page is used then ), $permalink_divider.
 *
 * 6.0.0: rebuilt on the shared email design ( wp_easycart_email_design, inc/classes/core/class-wp-easycart-email-design.php ).
 * The gift card ID is shown in a code box, the amount / to / from / message in a card, and the redeem line keeps its
 * link to $store_page. Copy this file to your wp-easycart-data layout folder to customise it; the file name must stay the same.
 *
 * Language strings kept: cart_success / cart_giftcard_receipt_header, _to, _from, _id, _amount, _message.
 *
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'wp_easycart_email_design' ) ) {
	require_once EC_PLUGIN_DIRECTORY . '/inc/classes/core/class-wp-easycart-email-design.php';
}
if ( ! isset( $cart_item ) || ! is_object( $cart_item ) ) {
	return;
}

$ed            = 'wp_easycart_email_design';
$ec_gc_lang    = wp_easycart_language();
$ec_gc_id      = isset( $giftcard_id ) ? (string) $giftcard_id : ( isset( $cart_item->giftcard_id ) ? (string) $cart_item->giftcard_id : '' );
$ec_gc_to      = isset( $cart_item->gift_card_to_name ) ? trim( (string) $cart_item->gift_card_to_name ) : '';
$ec_gc_from    = isset( $cart_item->gift_card_from_name ) ? trim( (string) $cart_item->gift_card_from_name ) : '';
$ec_gc_message = isset( $cart_item->gift_card_message ) ? trim( (string) $cart_item->gift_card_message ) : '';
$ec_gc_value   = isset( $cart_item->gift_card_value ) ? (float) $cart_item->gift_card_value : 0;
$ec_gc_title   = isset( $cart_item->title ) ? (string) $cart_item->title : '';

/* Redeem link: the store page the sender passed ( ec_order::send_gift_card_email() does not set one ). */
$ec_gc_store_page = ( isset( $store_page ) && '' !== (string) $store_page ) ? (string) $store_page : '';
if ( '' === $ec_gc_store_page ) {
	$ec_gc_store_page = (string) get_permalink( get_option( 'ec_option_storepage' ) );
}

/* Gift card image ( same fallback order as before: option item image, product image, default, not-found image ). */
$ec_gc_image_url = '';
if ( get_option( 'ec_option_show_image_on_receipt' ) ) {
	$ec_gc_image_opt = isset( $cart_item->image1_optionitem ) ? (string) $cart_item->image1_optionitem : '';
	if ( ! empty( $cart_item->is_deconetwork ) ) {
		$ec_gc_image_url = $ed::product_image_url( '', true, isset( $cart_item->deconetwork_image_link ) ? $cart_item->deconetwork_image_link : '' );
	} elseif ( 'http://' === substr( $ec_gc_image_opt, 0, 7 ) || 'https://' === substr( $ec_gc_image_opt, 0, 8 ) ) {
		$ec_gc_image_url = $ec_gc_image_opt;
	} elseif ( '' !== $ec_gc_image_opt && '0' !== $ec_gc_image_opt && file_exists( EC_PLUGIN_DATA_DIRECTORY . '/products/pics1/' . $ec_gc_image_opt ) && ! is_dir( EC_PLUGIN_DATA_DIRECTORY . '/products/pics1/' . $ec_gc_image_opt ) ) {
		$ec_gc_image_url = plugins_url( 'wp-easycart-data/products/pics1/' . $ec_gc_image_opt, EC_PLUGIN_DATA_DIRECTORY );
	} else {
		$ec_gc_image_url = $ed::product_image_url( isset( $cart_item->image1 ) ? $cart_item->image1 : '' );
	}
}

$ec_gc_header = $ec_gc_lang->get_text( 'cart_success', 'cart_giftcard_receipt_header' );

$ed::open(
	array(
		'title'     => wp_strip_all_tags( $ec_gc_header ),
		'preheader' => wp_strip_all_tags( $ec_gc_header ) . ' ' . wp_strip_all_tags( $GLOBALS['currency']->get_currency_display( $ec_gc_value ) ) . ( '' !== $ec_gc_from ? ' - ' . $ec_gc_from : '' ),
		'logo_url'  => isset( $email_logo_url ) ? (string) $email_logo_url : (string) get_option( 'ec_option_email_logo' ),
		'store_url' => $ec_gc_store_page,
	)
);

/* Heading and amount */
$ed::section_start();
$ed::heading( wp_kses_post( $ec_gc_header ) );
if ( '' !== $ec_gc_to ) {
	$ed::paragraph( '<strong style="color:#111827;">' . wp_kses_post( $ec_gc_lang->get_text( 'cart_success', 'cart_giftcard_receipt_to' ) ) . ':</strong> ' . esc_html( $ec_gc_to ), array( 'margin' => '0 0 4px 0' ) );
}
if ( '' !== $ec_gc_from ) {
	$ed::paragraph( '<strong style="color:#111827;">' . wp_kses_post( $ec_gc_lang->get_text( 'cart_success', 'cart_giftcard_receipt_from' ) ) . ':</strong> ' . esc_html( $ec_gc_from ), array( 'margin' => '0 0 4px 0' ) );
}
$ed::section_end();

/* Gift card image */
if ( '' !== $ec_gc_image_url ) {
	$ed::section_start( array( 'top' => 16 ) );
	echo '<img src="' . esc_url( $ec_gc_image_url ) . '" alt="' . esc_attr( wp_strip_all_tags( $ec_gc_lang->convert_text( $ec_gc_title ) ) ) . '" width="300" style="display:block;width:100%;max-width:300px;height:auto;border-radius:8px;border:1px solid #e5e7eb;" />';
	$ed::section_end();
}

/* Message from the sender */
if ( '' !== $ec_gc_message ) {
	$ed::section_start( array( 'top' => 16 ) );
	$ed::card_start();
	echo '<div style="' . esc_attr( $ed::css( 'text' ) ) . 'font-style:italic;color:#111827;">' . nl2br( esc_html( $ec_gc_message ) ) . '</div>';
	if ( '' !== $ec_gc_from ) {
		echo '<div style="margin-top:8px;' . esc_attr( $ed::css( 'small' ) ) . '">&mdash; ' . esc_html( $ec_gc_from ) . '</div>';
	}
	$ed::card_end();
	$ed::section_end();
}

/* Gift card ID and amount */
$ed::section_start( array( 'top' => 16 ) );
$ed::code_box( $ec_gc_id, wp_kses_post( $ec_gc_lang->get_text( 'cart_success', 'cart_giftcard_receipt_id' ) ) );
$ed::section_end();
$ed::section_start( array( 'top' => 12 ) );
$ed::key_values(
	array(
		array(
			'label' => wp_kses_post( $ec_gc_lang->get_text( 'cart_success', 'cart_giftcard_receipt_amount' ) ),
			'value' => $ed::money( $ec_gc_value ),
		),
	)
);
$ed::section_end();

/* How to redeem */
$ed::section_start( array( 'top' => 16, 'bottom' => 8 ) );
$ed::paragraph( wp_kses_post( $ec_gc_lang->get_text( 'cart_success', 'cart_giftcard_receipt_message' ) ) . ' <a href="' . esc_url( $ec_gc_store_page ) . '" target="_blank" style="' . esc_attr( $ed::css( 'link' ) ) . '">' . esc_html( $ec_gc_store_page ) . '</a>.', array( 'margin' => '0' ) );
$ed::section_end();

$ed::close();
