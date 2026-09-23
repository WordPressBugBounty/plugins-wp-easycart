<?php
/**
 * Order shipped email ( "Shipping Confirmation" ).
 *
 * Included by wp_easycart_admin_orders::send_customer_shipping_email() with:
 *   $order ( array, one ec_order row + billing_country_name / shipping_country_name ), $orderdetails ( ec_orderdetail rows ),
 *   $trackingnumber, $shipcarrier, $email_logo_url, $store_page, $permalink_divider.
 *
 * 6.0.0: rebuilt on the shared email design ( wp_easycart_email_design, inc/classes/core/class-wp-easycart-email-design.php ).
 * Each address is one card, lines follow the destination country, and a missing carrier, tracking number, tracking URL
 * or shipping address is left out. Copy this file to your theme / wp-easycart-data layout folder to customise it; the
 * file name must stay the same.
 *
 * Hooks kept: wp_easycart_shipping_email_after_tracking, wpeasycart_email_receipt_line_item, wp_easycart_email_receipt_image_width.
 * Hooks added ( @since 6.0.0 ): wp_easycart_shipping_email_tracking_url, wp_easycart_shipping_email_track_button_text,
 *   wp_easycart_shipping_email_address_lines, wp_easycart_shipping_email_accent_color ( plus the shared design filters ).
 *
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'wp_easycart_email_design' ) ) {
	require_once EC_PLUGIN_DIRECTORY . '/inc/classes/core/class-wp-easycart-email-design.php';
}

$ec_ship_order = ( is_array( $order ) && isset( $order[0] ) && is_object( $order[0] ) ) ? $order[0] : null;
if ( ! $ec_ship_order ) {
	return;
}
$ed               = 'wp_easycart_email_design';
$ec_ship_items    = is_array( $orderdetails ) ? $orderdetails : array();
$ec_ship_order_id = (int) $ec_ship_order->order_id;
$ec_ship_currency = $GLOBALS['currency'];
global $wpdb;

/* 6.0.1: what this email shows comes from its profile in Settings › Documents ( $document_fields from the sender, or
   the default profile ). $document_held_back: lines left for a later shipment when only some items were chosen. */
$ec_ship_doc  = ( isset( $document_fields ) && is_array( $document_fields ) ) ? $document_fields : ( class_exists( 'wp_easycart_documents' ) ? wp_easycart_documents::resolve( 'shipping' ) : null );
$ec_ship_show = function ( $key ) use ( $ec_ship_doc ) {
	return class_exists( 'wp_easycart_documents' ) ? wp_easycart_documents::show( $ec_ship_doc, $key ) : true;
};
$ec_ship_prices = $ec_ship_show( 'prices' );
$ec_ship_held   = ( isset( $document_held_back ) && is_array( $document_held_back ) ) ? $document_held_back : array();

/* Offers v2: line-level flags keyed by orderdetail_id and the order-level applied-offers snapshot. */
$ec_ship_offer_line_flags    = array();
$ec_ship_offer_order_summary = array();
if ( function_exists( 'wp_easycart_offers_active' ) && wp_easycart_offers_active() && $ec_ship_order_id > 0 ) {
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT orderdetail_id, product_id, is_free_gift, bundle_group_key, bundle_product_id, applied_offers FROM ec_orderdetail WHERE order_id = %d', $ec_ship_order_id ) ) as $ec_ship_offer_flag_row ) {
		$ec_ship_offer_line_flags[ (int) $ec_ship_offer_flag_row->orderdetail_id ] = $ec_ship_offer_flag_row;
	}
	$ec_ship_offer_json    = $wpdb->get_var( $wpdb->prepare( 'SELECT applied_offers FROM ec_order WHERE order_id = %d', $ec_ship_order_id ) );
	$ec_ship_offer_decoded = ( $ec_ship_offer_json ) ? json_decode( (string) $ec_ship_offer_json, true ) : false;
	if ( is_array( $ec_ship_offer_decoded ) && isset( $ec_ship_offer_decoded['applied_offers'] ) && is_array( $ec_ship_offer_decoded['applied_offers'] ) ) {
		$ec_ship_offer_order_summary = $ec_ship_offer_decoded['applied_offers'];
	}
}

/* Carrier, tracking number and tracking link ( each optional ). */
$ec_ship_tracking = trim( (string) $trackingnumber );
if ( in_array( strtolower( $ec_ship_tracking ), array( '0', 'null' ), true ) ) {
	$ec_ship_tracking = '';
}
$ec_ship_carrier = trim( (string) $shipcarrier );
if ( in_array( strtolower( $ec_ship_carrier ), array( '0', 'null' ), true ) ) {
	$ec_ship_carrier = '';
}
$ec_ship_tracking_url = (string) apply_filters( 'wp_easycart_shipping_email_tracking_url', $ed::tracking_url( $ec_ship_carrier, $ec_ship_tracking ), $ec_ship_carrier, $ec_ship_tracking, $ec_ship_order );
$ec_ship_track_text   = (string) apply_filters( 'wp_easycart_shipping_email_track_button_text', __( 'Track your package', 'wp-easycart' ), $ec_ship_order );

/* Addresses ( country-aware lines from the shared formatter; the shipping-email filter still applies ). */
$ec_ship_billing = $ed::address( $ec_ship_order, 'billing' );
$ec_ship_billing['lines'] = (array) apply_filters( 'wp_easycart_shipping_email_address_lines', $ec_ship_billing['lines'], 'billing', $ec_ship_order );
$ec_ship_shipping = array(
	'lines' => array(),
	'phone' => '',
	'has'   => false,
);
if ( get_option( 'ec_option_use_shipping' ) ) {
	$ec_ship_shipping          = $ed::address( $ec_ship_order, 'shipping' );
	$ec_ship_shipping['lines'] = (array) apply_filters( 'wp_easycart_shipping_email_address_lines', $ec_ship_shipping['lines'], 'shipping', $ec_ship_order );
}

/* Order status approved? ( codes are only revealed on approved orders ). */
$ec_ship_is_approved = false;
if ( isset( $ec_ship_order->orderstatus_id ) ) {
	$ec_ship_is_approved = (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT is_approved FROM ec_orderstatus WHERE status_id = %d', (int) $ec_ship_order->orderstatus_id ) );
}
$ec_ship_order_link = ( function_exists( 'wpeasycart_links' ) ) ? wpeasycart_links()->get_account_page( 'order_details', array( 'order_id' => $ec_ship_order_id ) ) : '';
$ec_ship_fees       = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT fee_label, fee_total FROM ec_order_fee WHERE order_id = %d ORDER BY order_fee_id ASC', $ec_ship_order_id ) );
$ec_ship_db         = class_exists( 'ec_db' ) ? new ec_db() : null;
$ec_ship_img_width  = (int) apply_filters( 'wp_easycart_email_receipt_image_width', 70 );
$ec_ship_lang       = wp_easycart_language();

$ec_ship_open = array(
	'title'     => wp_strip_all_tags( $ec_ship_lang->get_text( 'ec_shipping_email', 'shipping_email_title' ) ) . ' ' . $ec_ship_order_id,
	'preheader' => $ec_ship_lang->get_text( 'ec_shipping_email', 'shipping_subtitle1' ) . ' ' . $ec_ship_order_id . ' ' . $ec_ship_lang->get_text( 'ec_shipping_email', 'shipping_subtitle2' ),
	'accent'    => (string) apply_filters( 'wp_easycart_shipping_email_accent_color', (string) get_option( 'ec_option_details_main_color' ) ),
	'logo_url'  => (string) $email_logo_url,
	'store_url' => (string) $store_page,
);
/* 6.0.1: the logo switch and this document's own logo and size ( Settings › Documents ). */
$ed::open( class_exists( 'wp_easycart_documents' ) ? wp_easycart_documents::open_args( 'shipping', $ec_ship_doc, $ec_ship_open ) : $ec_ship_open );
$ec_ship_ctx = $ed::ctx();

/* Greeting */
$ed::section_start();
$ed::heading( wp_kses_post( $ec_ship_lang->get_text( 'ec_shipping_email', 'shipping_dear' ) ) . ' ' . esc_html( trim( $ec_ship_order->billing_first_name . ' ' . $ec_ship_order->billing_last_name ) ) . ',' );
$ed::paragraph( wp_kses_post( $ec_ship_lang->get_text( 'ec_shipping_email', 'shipping_subtitle1' ) ) . ' <strong style="color:#111827;">' . esc_html( $ec_ship_order_id ) . '</strong> ' . wp_kses_post( $ec_ship_lang->get_text( 'ec_shipping_email', 'shipping_subtitle2' ) ) );
if ( $ec_ship_show( 'email' ) && '' !== (string) $ec_ship_order->user_email ) {
	$ed::paragraph( esc_html( $ec_ship_order->user_email ), array( 'tone' => 'small', 'nolink' => true ) );
}
$ed::section_end();

/* Shipment status: carrier, tracking number, track button */
if ( '' !== $ec_ship_tracking || '' !== $ec_ship_carrier ) {
	$ed::section_start( array( 'top' => 8, 'bottom' => 8 ) );
	$ed::card_start();
	if ( '' !== $ec_ship_tracking ) {
		$ed::paragraph( wp_kses_post( $ec_ship_lang->get_text( 'ec_shipping_email', 'shipping_description' ) ), array( 'tone' => 'small', 'margin' => '0 0 12px 0' ) );
	}
	$ed::key_values(
		array(
			array(
				'label' => wp_kses_post( rtrim( trim( $ec_ship_lang->get_text( 'ec_shipping_email', 'shipping_carrier' ) ), ':' ) ),
				'value' => esc_html( $ec_ship_carrier ),
			),
			array(
				'label' => wp_kses_post( rtrim( trim( $ec_ship_lang->get_text( 'ec_shipping_email', 'shipping_tracking' ) ), ':' ) ),
				'value' => ( '' === $ec_ship_tracking ) ? '' : ( ( '' !== $ec_ship_tracking_url ) ? '<a href="' . esc_url( $ec_ship_tracking_url ) . '" target="_blank" dir="ltr" style="' . esc_attr( $ed::css( 'link' ) ) . '">' . esc_html( $ec_ship_tracking ) . '</a>' : '<span class="ec-email-nolink" dir="ltr">' . esc_html( $ec_ship_tracking ) . '</span>' ),
				'mono'  => true,
			),
		)
	);
	if ( '' !== $ec_ship_tracking_url ) {
		$ed::button( $ec_ship_tracking_url, $ec_ship_track_text, array( 'margin' => '14px 0 0 0', 'arrow' => true ) );
	}
	$ed::card_end();
	$ed::section_end();
}
$ed::section_start( array( 'top' => 0 ) );
do_action( 'wp_easycart_shipping_email_after_tracking', $ec_ship_order );
$ed::section_end();

/* Addresses */
$ed::address_cards(
	array(
		array(
			'label'   => wp_kses_post( $ec_ship_lang->get_text( 'ec_shipping_email', 'shipping_shipping_label' ) ),
			'address' => $ec_ship_show( 'shipping' ) ? $ec_ship_shipping : array( 'has' => false ),
		),
		array(
			'label'   => wp_kses_post( $ec_ship_lang->get_text( 'ec_shipping_email', 'shipping_billing_label' ) ),
			'address' => $ec_ship_show( 'billing' ) ? $ec_ship_billing : array( 'has' => false ),
		),
	),
	( $ec_ship_show( 'billing' ) && '' !== (string) $ec_ship_order->vat_registration_number ) ? '<strong>' . wp_kses_post( $ec_ship_lang->get_text( 'cart_billing_information', 'cart_billing_information_vat_registration_number' ) ) . ':</strong> ' . esc_html( $ec_ship_order->vat_registration_number ) : ''
);

/* Items */
$ec_ship_columns = array(
	'product' => wp_kses_post( $ec_ship_lang->get_text( 'ec_shipping_email', 'shipping_product' ) ),
	'qty'     => wp_kses_post( $ec_ship_lang->get_text( 'ec_shipping_email', 'shipping_quantity' ) ),
);
if ( $ec_ship_prices ) {
	$ec_ship_columns['unit']  = wp_kses_post( $ec_ship_lang->get_text( 'ec_shipping_email', 'shipping_unit_price' ) );
	$ec_ship_columns['total'] = wp_kses_post( $ec_ship_lang->get_text( 'ec_shipping_email', 'shipping_total_price' ) );
}
$ed::items_start( $ec_ship_columns );
foreach ( $ec_ship_items as $ec_ship_item ) {
	$ec_ship_line_flags = ( isset( $ec_ship_item->orderdetail_id ) && isset( $ec_ship_offer_line_flags[ (int) $ec_ship_item->orderdetail_id ] ) ) ? $ec_ship_offer_line_flags[ (int) $ec_ship_item->orderdetail_id ] : false;

	$ec_ship_title = wp_kses_post( $ec_ship_item->title );
	if ( $ec_ship_line_flags && $ec_ship_line_flags->is_free_gift && function_exists( 'wp_easycart_offers_text' ) ) {
		$ec_ship_title .= ' <span style="display:inline-block;margin-' . esc_attr( $ec_ship_ctx['start'] ) . ':6px;padding:1px 8px;background:#fce7f0;color:#c2185b;font-size:11px;font-weight:bold;border-radius:3px;text-transform:uppercase;vertical-align:middle;">' . wp_kses_post( wp_easycart_offers_text( 'cart_offers', 'gift_line_label' ) ) . '</span>';
	}
	if ( $ec_ship_line_flags && '' !== (string) $ec_ship_line_flags->bundle_group_key && $ec_ship_line_flags->bundle_product_id != $ec_ship_line_flags->product_id && function_exists( 'wp_easycart_offers_text' ) ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- numeric strings from the database.
		$ec_ship_title .= ' <span style="display:inline-block;margin-' . esc_attr( $ec_ship_ctx['start'] ) . ':6px;padding:1px 8px;background:#eef1f4;color:#4a5560;font-size:11px;font-weight:bold;border-radius:3px;text-transform:uppercase;vertical-align:middle;">' . wp_kses_post( wp_easycart_offers_text( 'cart_offers', 'bundle_line_label' ) ) . '</span>';
	}
	if ( $ec_ship_prices && $ec_ship_line_flags && isset( $ec_ship_line_flags->applied_offers ) && '' !== (string) $ec_ship_line_flags->applied_offers ) {
		$ec_ship_line_offers = json_decode( (string) $ec_ship_line_flags->applied_offers, true );
		if ( is_array( $ec_ship_line_offers ) && count( $ec_ship_line_offers ) > 0 ) {
			$ec_ship_title .= '<div style="margin-top:4px;">';
			foreach ( $ec_ship_line_offers as $ec_ship_line_offer ) {
				if ( ! isset( $ec_ship_line_offer['label'] ) || ! isset( $ec_ship_line_offer['amount'] ) || (float) $ec_ship_line_offer['amount'] <= 0 ) {
					continue;
				}
				$ec_ship_title .= '<span style="display:inline-block;margin:2px 4px 0 0;padding:1px 8px;background:#e7f5ec;color:#1f7a3d;font-size:11px;font-weight:normal;border-radius:3px;">' . esc_html( $ec_ship_line_offer['label'] ) . ' &minus;' . esc_html( $ec_ship_currency->get_currency_display( (float) $ec_ship_line_offer['amount'] ) ) . '</span>';
			}
			$ec_ship_title .= '</div>';
		}
	}

	$ed::item_start(
		array(
			'image_url'   => $ec_ship_show( 'image' ) ? $ed::product_image_url( $ec_ship_item->image1, ! empty( $ec_ship_item->is_deconetwork ), isset( $ec_ship_item->deconetwork_image_link ) ? $ec_ship_item->deconetwork_image_link : '' ) : '',
			'image_alt'   => $ec_ship_item->title,
			'image_width' => $ec_ship_img_width,
			'title_html'  => $ec_ship_title,
		)
	);
	if ( '' !== (string) $ec_ship_item->model_number && $ec_ship_show( 'sku' ) ) {
		$ed::detail( esc_html( $ec_ship_item->model_number ), array( 'nolink' => true, 'style' => 'padding:0 0 4px 0;' ) );
	}
	if ( ! empty( $ec_ship_item->gift_card_message ) ) {
		$ed::detail( wp_kses_post( $ec_ship_lang->get_text( 'account_order_details', 'account_orders_details_gift_message' ) ) . esc_html( $ec_ship_item->gift_card_message ) );
	}
	if ( ! empty( $ec_ship_item->gift_card_from_name ) ) {
		$ed::detail( wp_kses_post( $ec_ship_lang->get_text( 'account_order_details', 'account_orders_details_gift_from' ) ) . esc_html( $ec_ship_item->gift_card_from_name ) );
	}
	if ( ! empty( $ec_ship_item->gift_card_to_name ) ) {
		$ed::detail( wp_kses_post( $ec_ship_lang->get_text( 'account_order_details', 'account_orders_details_gift_to' ) ) . esc_html( $ec_ship_item->gift_card_to_name ) );
	}
	do_action( 'wpeasycart_email_receipt_line_item', $ec_ship_item->model_number, $ec_ship_item->orderdetail_id );

	$ec_ship_allow_download = true;
	$ec_ship_use_advanced   = ! empty( $ec_ship_item->use_advanced_optionset );
	$ec_ship_use_both       = ! empty( $ec_ship_item->use_both_option_types );
	if ( $ec_ship_show( 'options' ) && ( ! $ec_ship_use_advanced || $ec_ship_use_both ) ) {
		for ( $ec_ship_n = 1; $ec_ship_n <= 5; $ec_ship_n++ ) {
			$ec_ship_opt_name  = isset( $ec_ship_item->{'optionitem_name_' . $ec_ship_n} ) ? (string) $ec_ship_item->{'optionitem_name_' . $ec_ship_n} : '';
			$ec_ship_opt_price = isset( $ec_ship_item->{'optionitem_price_' . $ec_ship_n} ) ? (float) $ec_ship_item->{'optionitem_price_' . $ec_ship_n} : 0;
			if ( '' === $ec_ship_opt_name ) {
				continue;
			}
			$ec_ship_opt_price_text = '';
			if ( ! $ec_ship_prices ) {
				$ec_ship_opt_price_text = '';
			} elseif ( $ec_ship_opt_price < 0 ) {
				$ec_ship_opt_price_text = ' (' . $ec_ship_currency->get_currency_display( $ec_ship_opt_price ) . ')';
			} elseif ( $ec_ship_opt_price > 0 ) {
				$ec_ship_opt_price_text = ' (+' . $ec_ship_currency->get_currency_display( $ec_ship_opt_price ) . ')';
			}
			$ed::detail( wp_kses_post( $ec_ship_opt_name ) . esc_html( $ec_ship_opt_price_text ) );
		}
	}
	if ( ( $ec_ship_use_advanced || $ec_ship_use_both ) && $ec_ship_db ) {
		foreach ( (array) $ec_ship_db->get_order_options( $ec_ship_item->orderdetail_id ) as $ec_ship_adv ) {
			if ( isset( $ec_ship_adv->optionitem_allow_download ) && ! $ec_ship_adv->optionitem_allow_download ) {
				$ec_ship_allow_download = false;
			}
			if ( 'file' === $ec_ship_adv->option_type ) {
				$ec_ship_file_parts = explode( '/', (string) $ec_ship_adv->option_value );
				$ec_ship_adv_value  = $ec_ship_file_parts[ count( $ec_ship_file_parts ) - 1 ];
			} elseif ( 'grid' === $ec_ship_adv->option_type ) {
				$ec_ship_adv_value = $ec_ship_adv->optionitem_name . ' (' . $ec_ship_adv->option_value . ')';
			} else {
				$ec_ship_adv_value = $ec_ship_adv->option_value;
			}
			$ec_ship_adv_price = '';
			if ( ! empty( $ec_ship_adv->optionitem_enable_custom_price_label ) && ( ( isset( $ec_ship_adv->optionitem_price ) && 0 != $ec_ship_adv->optionitem_price ) || ( isset( $ec_ship_adv->optionitem_price_onetime ) && 0 != $ec_ship_adv->optionitem_price_onetime ) ) ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- numeric strings from the database.
				$ec_ship_adv_price = ' ' . $ec_ship_adv->optionitem_custom_price_label;
			} elseif ( isset( $ec_ship_adv->optionitem_price ) && $ec_ship_adv->optionitem_price > 0 ) {
				$ec_ship_adv_price = ' (+' . $ec_ship_currency->get_currency_display( $ec_ship_adv->optionitem_price ) . ' ' . wp_strip_all_tags( $ec_ship_lang->get_text( 'cart', 'cart_item_adjustment' ) ) . ')';
			} elseif ( isset( $ec_ship_adv->optionitem_price ) && $ec_ship_adv->optionitem_price < 0 ) {
				$ec_ship_adv_price = ' (' . $ec_ship_currency->get_currency_display( $ec_ship_adv->optionitem_price ) . ' ' . wp_strip_all_tags( $ec_ship_lang->get_text( 'cart', 'cart_item_adjustment' ) ) . ')';
			} elseif ( isset( $ec_ship_adv->optionitem_price_onetime ) && $ec_ship_adv->optionitem_price_onetime > 0 ) {
				$ec_ship_adv_price = ' (+' . $ec_ship_currency->get_currency_display( $ec_ship_adv->optionitem_price_onetime ) . ' ' . wp_strip_all_tags( $ec_ship_lang->get_text( 'cart', 'cart_order_adjustment' ) ) . ')';
			} elseif ( isset( $ec_ship_adv->optionitem_price_onetime ) && $ec_ship_adv->optionitem_price_onetime < 0 ) {
				$ec_ship_adv_price = ' (' . $ec_ship_currency->get_currency_display( $ec_ship_adv->optionitem_price_onetime ) . ' ' . wp_strip_all_tags( $ec_ship_lang->get_text( 'cart', 'cart_order_adjustment' ) ) . ')';
			}
			if ( $ec_ship_show( 'options' ) ) { /* the loop still runs when options are hidden: it decides the download link */
				$ed::option_detail( wp_kses_post( $ec_ship_adv->option_label ), esc_html( $ec_ship_adv_value . ( $ec_ship_prices ? $ec_ship_adv_price : '' ) ) );
			}
		}
	}
	if ( '' !== $ec_ship_order_link && ( ! empty( $ec_ship_item->is_giftcard ) || ( ! empty( $ec_ship_item->is_download ) && $ec_ship_allow_download ) ) ) {
		$ed::detail( '<a href="' . esc_url( $ec_ship_order_link ) . '" target="_blank" style="' . esc_attr( $ed::css( 'link' ) ) . '">' . wp_kses_post( ! empty( $ec_ship_item->is_giftcard ) ? $ec_ship_lang->get_text( 'account_order_details', 'account_orders_details_print_online' ) : $ec_ship_lang->get_text( 'account_order_details', 'account_orders_details_download' ) ) . '</a>', array( 'style' => 'padding-top:4px;' ) );
	}
	if ( ! empty( $ec_ship_item->include_code ) && $ec_ship_is_approved ) {
		$ec_ship_codes = $wpdb->get_col( $wpdb->prepare( 'SELECT code_val FROM ec_code WHERE ec_code.orderdetail_id = %d', (int) $ec_ship_item->orderdetail_id ) );
		$ed::detail( wp_kses_post( $ec_ship_lang->get_text( 'account_order_details', 'account_orders_details_your_codes' ) ) . ' ' . esc_html( implode( ', ', (array) $ec_ship_codes ) ) );
	}
	$ec_ship_end = array( 'qty' => $ec_ship_item->quantity );
	if ( $ec_ship_prices ) {
		$ec_ship_end['unit_html']  = $ed::money( $ec_ship_item->unit_price );
		$ec_ship_end['total_html'] = $ed::money( $ec_ship_item->total_price );
	}
	$ed::item_end( $ec_ship_end );
}
$ed::items_end();

/* 6.0.1: items chosen for a later shipment */
if ( $ec_ship_held ) {
	$ed::section_start();
	$ed::label( class_exists( 'wp_easycart_documents' ) ? wp_easycart_documents::text( 'items_to_follow', __( 'To follow in a separate shipment', 'wp-easycart' ) ) : esc_html__( 'To follow in a separate shipment', 'wp-easycart' ) );
	$ec_ship_follow = array();
	foreach ( $ec_ship_held as $ec_ship_held_line ) {
		$ec_ship_follow[] = esc_html( wp_strip_all_tags( (string) $ec_ship_held_line->title ) . ' × ' . (int) $ec_ship_held_line->quantity );
	}
	$ed::paragraph( implode( '<br />', $ec_ship_follow ), array( 'nolink' => true ) );
	$ed::section_end();
}

/* Totals ( the whole order's: left off when prices are hidden, or when only some items are in this shipment ) */
if ( $ec_ship_prices && ! $ec_ship_held ) :
$ec_ship_totals   = array();
$ec_ship_totals[] = array( wp_kses_post( $ec_ship_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_subtotal' ) ), $ed::money( $ec_ship_order->sub_total ) );
if ( $ec_ship_order->tip_total > 0 ) {
	$ec_ship_totals[] = array( wp_kses_post( $ec_ship_lang->get_text( 'cart_totals', 'cart_totals_tip' ) ), $ed::money( $ec_ship_order->tip_total ) );
}
if ( $ec_ship_order->tax_total > 0 ) {
	$ec_ship_totals[] = array( wp_kses_post( $ec_ship_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_tax' ) ), $ed::money( $ec_ship_order->tax_total ) );
}
if ( get_option( 'ec_option_use_shipping' ) ) {
	$ec_ship_totals[] = array( wp_kses_post( $ec_ship_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_shipping' ) ), $ed::money( $ec_ship_order->shipping_total ) );
}
foreach ( $ec_ship_offer_order_summary as $ec_ship_offer_row ) {
	if ( ! isset( $ec_ship_offer_row['label'] ) || ! isset( $ec_ship_offer_row['amount'] ) || (float) $ec_ship_offer_row['amount'] <= 0 ) {
		continue;
	}
	$ec_ship_totals[] = array( esc_html( $ec_ship_offer_row['label'] . ( ( isset( $ec_ship_offer_row['code'] ) && '' !== (string) $ec_ship_offer_row['code'] ) ? ' (' . $ec_ship_offer_row['code'] . ')' : '' ) ), $ed::ltr( '-' . $ec_ship_currency->get_currency_display( (float) $ec_ship_offer_row['amount'] ) ) );
}
if ( $ec_ship_order->discount_total > 0 ) {
	$ec_ship_totals[] = array( wp_kses_post( $ec_ship_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_discount' ) ), $ed::ltr( '-' . $ec_ship_currency->get_currency_display( $ec_ship_order->discount_total ) ) );
}
if ( $ec_ship_order->duty_total > 0 ) {
	$ec_ship_totals[] = array( wp_kses_post( $ec_ship_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_duty' ) ), $ed::money( $ec_ship_order->duty_total ) );
}
if ( $ec_ship_order->vat_total > 0 ) {
	$ec_ship_totals[] = array( wp_kses_post( $ec_ship_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_vat' ) ) . esc_html( number_format( (float) $ec_ship_order->vat_rate, 0 ) ) . '%', $ed::money( $ec_ship_order->vat_total ) );
}
foreach ( array( 'gst', 'pst', 'hst' ) as $ec_ship_ca_tax ) {
	if ( $ec_ship_order->{$ec_ship_ca_tax . '_total'} > 0 ) {
		$ec_ship_ca_label = function_exists( 'wp_easycart_canada_tax_label' ) ? wp_easycart_canada_tax_label( $ec_ship_ca_tax, $ec_ship_order->shipping_state ) : strtoupper( $ec_ship_ca_tax );
		$ec_ship_totals[] = array( esc_html( $ec_ship_ca_label . ' (' . $ec_ship_order->{$ec_ship_ca_tax . '_rate'} . '%)' ), $ed::money( $ec_ship_order->{$ec_ship_ca_tax . '_total'} ) );
	}
}
foreach ( $ec_ship_fees as $ec_ship_fee ) {
	$ec_ship_totals[] = array( esc_html( $ec_ship_fee->fee_label ), $ed::money( $ec_ship_fee->fee_total ) );
}
$ed::totals( $ec_ship_totals, array( wp_kses_post( $ec_ship_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_grand_total' ) ), $ed::money( $ec_ship_order->grand_total ) ) );
endif;

/* Notes, closing lines */
if ( get_option( 'ec_option_user_order_notes' ) && $ec_ship_show( 'order_notes' ) && '' !== trim( (string) $ec_ship_order->order_customer_notes ) ) {
	$ed::section_start();
	$ed::label( wp_kses_post( $ec_ship_lang->get_text( 'cart_payment_information', 'cart_payment_information_order_notes_title' ) ) );
	$ed::card_start( array( 'padding' => '12px 14px' ) );
	echo nl2br( esc_html( wp_unslash( $ec_ship_order->order_customer_notes ) ) );
	$ed::card_end();
	$ed::section_end();
}
$ed::section_start( array( 'top' => 24, 'bottom' => 8 ) );
$ed::paragraph( wp_kses_post( $ec_ship_lang->get_text( 'ec_shipping_email', 'shipping_final_note1' ) ), array( 'margin' => '0 0 12px 0' ) );
$ed::paragraph( wp_kses_post( $ec_ship_lang->get_text( 'ec_shipping_email', 'shipping_final_note2' ) ), array( 'tone' => 'strong', 'margin' => '0' ) );
$ed::section_end();
/* 6.0.1: the footer image switch and this document's own footer image and size ( Settings › Documents ). */
$ed::close( class_exists( 'wp_easycart_documents' ) ? wp_easycart_documents::close_args( 'shipping', $ec_ship_doc ) : array() );
