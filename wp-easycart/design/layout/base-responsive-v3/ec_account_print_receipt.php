<?php
/**
 * Printable order receipt ( front end "print receipt" link, admin print receipt, and the PRO PDF copy ).
 *
 * Included by inc/scripts/print_receipt.php, admin/inc/wp_easycart_admin_orders.php::print_receipt() and
 * wp-easycart-pro/inc/classes/core/wp_easycart_pdf_receipt.php with: $order ( ec_order row, country columns already
 * replaced by country names ), $order_details, $order_id, $mysqli, $tax_struct, $country_list, $subtotal, $sub_total,
 * $total, $tip, $tax, $has_duty, $duty, $vat, $vat_rate, $shipping, $discount, $refund, $order_fees, $gst, $pst, $hst,
 * $gst_rate, $pst_rate, $hst_rate, $store_page, $permalink_divider, $email_logo_url and ( PDF only ) $order_timestamp.
 *
 * 6.0.0: rebuilt on the shared email design ( wp_easycart_email_design ) so the printed / PDF receipt matches the
 * order emails. Everything is table-based with simple inline CSS because dompdf renders this same file for the PRO PDF
 * attachment: no flex, no floats, fixed-width image cells. @media print rules only affect the browser view.
 * Copy this file to your theme / wp-easycart-data layout folder to customise it; the file name must stay the same.
 *
 * Hooks kept: wp_easycart_print_receipt_optionitems, wpeasycart_print_receipt_order_notes_after,
 *   wp_easycart_order_details_show_discount_unit / _total, wp_easycart_cart_item_unit_price_display,
 *   wp_easycart_order_details_discount_display, wp_easycart_pickup_date_placeholder_format,
 *   wp_easycart_pickup_time_close_placeholder_format, wp_easycart_pickup_time_placeholder_format.
 *
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'wp_easycart_email_design' ) ) {
	require_once EC_PLUGIN_DIRECTORY . '/inc/classes/core/class-wp-easycart-email-design.php';
}

$ed          = 'wp_easycart_email_design';
$ec_pr_lang  = wp_easycart_language();
$ec_pr_curr  = $GLOBALS['currency'];
$ec_pr_items = is_array( $order_details ) ? $order_details : array();

/* Offers v2: line-level offer flags keyed by orderdetail_id, and the order-level applied-offers snapshot. */
$wpec_offer_line_flags    = array();
$wpec_offer_order_summary = array();
if ( function_exists( 'wp_easycart_offers_active' ) && wp_easycart_offers_active() ) {
	global $wpdb;
	$wpec_offer_flag_order_id = ( isset( $order_id ) ) ? (int) $order_id : 0;
	if ( $wpec_offer_flag_order_id > 0 ) {
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT orderdetail_id, product_id, is_free_gift, bundle_group_key, bundle_product_id, applied_offers FROM ec_orderdetail WHERE order_id = %d', $wpec_offer_flag_order_id ) ) as $wpec_offer_flag_row ) {
			$wpec_offer_line_flags[ (int) $wpec_offer_flag_row->orderdetail_id ] = $wpec_offer_flag_row;
		}
		$wpec_offer_order_json    = $wpdb->get_var( $wpdb->prepare( 'SELECT applied_offers FROM ec_order WHERE order_id = %d', $wpec_offer_flag_order_id ) );
		$wpec_offer_order_decoded = ( $wpec_offer_order_json ) ? json_decode( (string) $wpec_offer_order_json, true ) : false;
		if ( is_array( $wpec_offer_order_decoded ) && isset( $wpec_offer_order_decoded['applied_offers'] ) && is_array( $wpec_offer_order_decoded['applied_offers'] ) ) {
			$wpec_offer_order_summary = $wpec_offer_order_decoded['applied_offers'];
		}
	}
}

/* Which addresses does this order actually have? */
$has_shipping = false;
$has_billing  = false;
if ( get_option( 'ec_option_use_shipping' ) ) {
	foreach ( $ec_pr_items as $ec_pr_cart_item ) {
		if ( ! empty( $ec_pr_cart_item->is_shippable ) ) {
			$has_shipping = true;
		}
	}
}
if ( $has_shipping && '' === trim( (string) $order->shipping_address_line_1 ) && '' === trim( (string) $order->shipping_city ) && '' === trim( (string) $order->shipping_zip ) ) {
	$has_shipping = false; // shippable items but no shipping address on the order.
}
foreach ( array( 'billing_address_line_1', 'billing_city', 'billing_state', 'billing_zip', 'billing_country' ) as $ec_pr_billing_field ) {
	if ( isset( $order->{$ec_pr_billing_field} ) && '' !== trim( (string) $order->{$ec_pr_billing_field} ) ) {
		$has_billing = true;
	}
}

/* The country columns already hold the country name here, so use them for display and for the line order. */
$ec_pr_address_map = array(
	'billing'  => array( 'country_name' => 'billing_country' ),
	'shipping' => array( 'country_name' => 'shipping_country' ),
);

$ed::open(
	array(
		'title'     => wp_strip_all_tags( $ec_pr_lang->get_text( 'cart_success', 'cart_payment_receipt_title' ) ) . ' ' . $order_id,
		'preheader' => '',
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
			/* Keep one line item together, but never the whole receipt ( a page-break rule on every table pushes long receipts onto a blank second page ). */
			. '.ec-email-container td.ec_receipt_item_text{page-break-inside:avoid;}'
			. '}',
	)
);

/* Pickup banners */
if ( ! empty( $order->includes_preorder_items ) ) {
	$ec_pr_pickup_open  = date( apply_filters( 'wp_easycart_pickup_date_placeholder_format', 'F d, Y g:i A' ), strtotime( $order->pickup_date ) );
	$ec_pr_pickup_close = date( apply_filters( 'wp_easycart_pickup_time_close_placeholder_format', 'g:i A' ), strtotime( $order->pickup_date . ' +1 hour' ) );
	$ed::notice( esc_html( str_replace( '[pickup_date]', $ec_pr_pickup_open . ' - ' . $ec_pr_pickup_close, $ec_pr_lang->get_text( 'ec_errors', 'preorder_message' ) ) ), 'success' );
}
if ( ! empty( $order->includes_restaurant_type ) ) {
	$ec_pr_pickup_time = date( apply_filters( 'wp_easycart_pickup_time_placeholder_format', 'g:i A F d, Y' ), strtotime( $order->pickup_time ) );
	$ed::notice( esc_html( str_replace( '[pickup_time]', $ec_pr_pickup_time, $ec_pr_lang->get_text( 'ec_errors', 'restaurant_message' ) ) ), 'success' );
}

/* Greeting */
$ec_pr_date = isset( $order_timestamp ) ? date_i18n( get_option( 'date_format' ), $order_timestamp ) : date_i18n( get_option( 'date_format' ), strtotime( $order->order_date ) );
$ed::section_start();
$ed::heading( wp_kses_post( $ec_pr_lang->get_text( 'cart_success', 'cart_payment_complete_line_1' ) ) . ' ' . esc_html( trim( $order->billing_first_name . ' ' . $order->billing_last_name ) ) );
$ed::paragraph( wp_kses_post( $ec_pr_lang->get_text( 'cart_success', 'cart_payment_complete_line_2' ) ) . ' <strong style="color:#111827;">' . esc_html( $order_id ) . ' &mdash; ' . esc_html( $ec_pr_date ) . '</strong>' );
$ed::paragraph( wp_kses_post( $ec_pr_lang->get_text( 'cart_success', 'cart_payment_complete_line_3' ) ) );
$ed::paragraph( wp_kses_post( $ec_pr_lang->get_text( 'cart_success', 'cart_payment_complete_line_4' ) ), array( 'margin' => '0' ) );
$ed::section_end();

/* Addresses */
$ec_pr_cards = array();
if ( $has_shipping ) {
	$ec_pr_cards[] = array(
		'label'   => wp_kses_post( $ec_pr_lang->get_text( 'cart_success', 'cart_payment_complete_shipping_label' ) ),
		'address' => $ed::address( $order, 'shipping', $ec_pr_address_map['shipping'] ),
	);
}
if ( $has_billing ) {
	$ec_pr_cards[] = array(
		'label'   => wp_kses_post( $ec_pr_lang->get_text( 'cart_success', 'cart_payment_complete_billing_label' ) ),
		'address' => $ed::address( $order, 'billing', $ec_pr_address_map['billing'] ),
	);
}
$ed::address_cards(
	$ec_pr_cards,
	( '' !== (string) $order->vat_registration_number ) ? '<strong>' . wp_kses_post( $ec_pr_lang->get_text( 'cart_billing_information', 'cart_billing_information_vat_registration_number' ) ) . ':</strong> ' . esc_html( $order->vat_registration_number ) : ''
);

/* Items */
$ed::items_start(
	array(
		'product' => wp_kses_post( $ec_pr_lang->get_text( 'cart_success', 'cart_payment_complete_details_header_1' ) ),
		'qty'     => wp_kses_post( $ec_pr_lang->get_text( 'cart_success', 'cart_payment_complete_details_header_2' ) ),
		'unit'    => wp_kses_post( $ec_pr_lang->get_text( 'cart_success', 'cart_payment_complete_details_header_3' ) ),
		'total'   => wp_kses_post( $ec_pr_lang->get_text( 'cart_success', 'cart_payment_complete_details_header_4' ) ),
	)
);
foreach ( $ec_pr_items as $ec_pr_item ) {
	$unit_discount_promotion = $ec_pr_item->unit_discount_promotion;
	$unit_discount_coupon    = $ec_pr_item->unit_discount_coupon;
	$unit_price_base         = $ec_pr_item->unit_price;
	if ( get_option( 'ec_option_show_promotion_discount_total' ) && $unit_discount_promotion > 0 ) {
		$unit_price_base += $unit_discount_promotion;
	}
	$unit_price        = $ec_pr_curr->get_currency_display( $unit_price_base );
	$has_unit_discount = ( apply_filters( 'wp_easycart_order_details_show_discount_unit', true ) && ( ( get_option( 'ec_option_show_promotion_discount_total' ) && $unit_discount_promotion > 0 ) || ( get_option( 'ec_option_show_coupon_discount_total' ) && $unit_discount_coupon > 0 ) ) );

	$total_discount_promotion = $ec_pr_item->total_discount_promotion;
	$total_discount_coupon    = $ec_pr_item->total_discount_coupon;
	$total_price_base         = $ec_pr_item->total_price;
	if ( get_option( 'ec_option_show_promotion_discount_total' ) && $total_discount_promotion > 0 ) {
		$total_price_base += $total_discount_promotion;
	}
	$total_price        = $ec_pr_curr->get_currency_display( $total_price_base );
	$has_total_discount = ( apply_filters( 'wp_easycart_order_details_show_discount_total', true ) && ( ( get_option( 'ec_option_show_promotion_discount_total' ) && $total_discount_promotion > 0 ) || ( get_option( 'ec_option_show_coupon_discount_total' ) && $total_discount_coupon > 0 ) ) );

	/* Title, with the offers v2 markers. */
	$ec_pr_title = wp_kses_post( $ec_pr_item->title );
	$wpec_line_flags = ( isset( $ec_pr_item->orderdetail_id ) && isset( $wpec_offer_line_flags[ (int) $ec_pr_item->orderdetail_id ] ) ) ? $wpec_offer_line_flags[ (int) $ec_pr_item->orderdetail_id ] : false;
	if ( $wpec_line_flags && $wpec_line_flags->is_free_gift && function_exists( 'wp_easycart_offers_text' ) ) {
		$ec_pr_title .= ' <span style="display:inline-block; margin-left:6px; padding:1px 8px; background:#fce7f0; color:#c2185b; font-size:11px; font-weight:bold; border-radius:3px; text-transform:uppercase;">' . wp_kses_post( wp_easycart_offers_text( 'cart_offers', 'gift_line_label' ) ) . '</span>';
	}
	if ( $wpec_line_flags && '' != $wpec_line_flags->bundle_group_key && $wpec_line_flags->bundle_product_id != $wpec_line_flags->product_id && function_exists( 'wp_easycart_offers_text' ) ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- numeric strings from the database.
		$ec_pr_title .= ' <span style="display:inline-block; margin-left:6px; padding:1px 8px; background:#eef1f4; color:#4a5560; font-size:11px; font-weight:bold; border-radius:3px; text-transform:uppercase;">' . wp_kses_post( wp_easycart_offers_text( 'cart_offers', 'bundle_line_label' ) ) . '</span>';
	}
	if ( $wpec_line_flags && isset( $wpec_line_flags->applied_offers ) && '' != $wpec_line_flags->applied_offers ) {
		$wpec_line_offer_rows = json_decode( (string) $wpec_line_flags->applied_offers, true );
		if ( is_array( $wpec_line_offer_rows ) && count( $wpec_line_offer_rows ) > 0 ) {
			$ec_pr_title .= '<div style="margin-top:4px;">';
			foreach ( $wpec_line_offer_rows as $wpec_line_offer_row ) {
				if ( ! isset( $wpec_line_offer_row['label'] ) || ! isset( $wpec_line_offer_row['amount'] ) || (float) $wpec_line_offer_row['amount'] <= 0 ) {
					continue;
				}
				$ec_pr_title .= '<span style="display:inline-block; margin:2px 4px 0 0; padding:1px 8px; background:#e7f5ec; color:#1f7a3d; font-size:11px; font-weight:normal; border-radius:3px;">' . esc_html( $wpec_line_offer_row['label'] ) . ' &minus;' . esc_html( $ec_pr_curr->get_currency_display( (float) $wpec_line_offer_row['amount'] ) ) . '</span>';
			}
			$ec_pr_title .= '</div>';
		}
	}

	$ed::item_start(
		array(
			'image_url'   => get_option( 'ec_option_show_image_on_receipt' ) ? $ed::product_image_url( $ec_pr_item->image1, ! empty( $ec_pr_item->is_deconetwork ), isset( $ec_pr_item->deconetwork_image_link ) ? $ec_pr_item->deconetwork_image_link : '' ) : '',
			'image_alt'   => $ec_pr_item->title,
			'image_width' => 70,
			'title_html'  => $ec_pr_title,
		)
	);
	if ( '' !== (string) $ec_pr_item->model_number ) {
		$ed::detail( esc_html( $ec_pr_item->model_number ), array( 'nolink' => true ) );
	}

	/* Basic options */
	if ( empty( $ec_pr_item->use_advanced_optionset ) || ! empty( $ec_pr_item->use_both_option_types ) ) {
		for ( $ec_pr_n = 1; $ec_pr_n <= 5; $ec_pr_n++ ) {
			$ec_pr_opt_name = isset( $ec_pr_item->{'optionitem_name_' . $ec_pr_n} ) ? (string) $ec_pr_item->{'optionitem_name_' . $ec_pr_n} : '';
			if ( '' === $ec_pr_opt_name ) {
				continue;
			}
			$ec_pr_opt_price = isset( $ec_pr_item->{'optionitem_price_' . $ec_pr_n} ) ? (float) $ec_pr_item->{'optionitem_price_' . $ec_pr_n} : 0;
			$ec_pr_opt_text  = '';
			if ( $ec_pr_opt_price < 0 ) {
				$ec_pr_opt_text = ' (' . $ec_pr_curr->get_currency_display( $ec_pr_opt_price ) . ')';
			} elseif ( $ec_pr_opt_price > 0 ) {
				$ec_pr_opt_text = ' (+' . $ec_pr_curr->get_currency_display( $ec_pr_opt_price ) . ')';
			}
			$ed::detail( wp_kses_post( $ec_pr_opt_name ) . esc_html( $ec_pr_opt_text ) );
		}
	}

	/* Advanced ( option set ) options */
	if ( ! empty( $ec_pr_item->use_advanced_optionset ) || ! empty( $ec_pr_item->use_both_option_types ) ) {
		foreach ( (array) $mysqli->get_order_options( $ec_pr_item->orderdetail_id ) as $advanced_option ) {
			if ( 'file' === $advanced_option->option_type ) {
				$ec_pr_file_parts = explode( '/', (string) $advanced_option->option_value );
				$ec_pr_opt_value  = $ec_pr_file_parts[ count( $ec_pr_file_parts ) - 1 ];
			} elseif ( 'grid' === $advanced_option->option_type ) {
				$ec_pr_opt_value = $advanced_option->optionitem_name . ' (' . $advanced_option->option_value . ')';
			} else {
				$ec_pr_opt_value = $advanced_option->option_value;
			}
			$ec_pr_opt_price = '';
			if ( ! empty( $advanced_option->optionitem_enable_custom_price_label ) && ( ( isset( $advanced_option->optionitem_price ) && 0 != $advanced_option->optionitem_price ) || ( isset( $advanced_option->optionitem_price_onetime ) && 0 != $advanced_option->optionitem_price_onetime ) ) ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- numeric strings from the database.
				$ec_pr_opt_price = ' ' . $advanced_option->optionitem_custom_price_label;
			} elseif ( isset( $advanced_option->optionitem_price ) && $advanced_option->optionitem_price > 0 ) {
				$ec_pr_opt_price = ' (+' . $ec_pr_curr->get_currency_display( $advanced_option->optionitem_price ) . ' ' . wp_strip_all_tags( $ec_pr_lang->get_text( 'cart', 'cart_item_adjustment' ) ) . ')';
			} elseif ( isset( $advanced_option->optionitem_price ) && $advanced_option->optionitem_price < 0 ) {
				$ec_pr_opt_price = ' (' . $ec_pr_curr->get_currency_display( $advanced_option->optionitem_price ) . ' ' . wp_strip_all_tags( $ec_pr_lang->get_text( 'cart', 'cart_item_adjustment' ) ) . ')';
			} elseif ( isset( $advanced_option->optionitem_price_onetime ) && $advanced_option->optionitem_price_onetime > 0 ) {
				$ec_pr_opt_price = ' (+' . $ec_pr_curr->get_currency_display( $advanced_option->optionitem_price_onetime ) . ' ' . wp_strip_all_tags( $ec_pr_lang->get_text( 'cart', 'cart_order_adjustment' ) ) . ')';
			} elseif ( isset( $advanced_option->optionitem_price_onetime ) && $advanced_option->optionitem_price_onetime < 0 ) {
				$ec_pr_opt_price = ' (' . $ec_pr_curr->get_currency_display( $advanced_option->optionitem_price_onetime ) . ' ' . wp_strip_all_tags( $ec_pr_lang->get_text( 'cart', 'cart_order_adjustment' ) ) . ')';
			} elseif ( isset( $advanced_option->optionitem_price_override ) && $advanced_option->optionitem_price_override > -1 ) {
				$ec_pr_opt_price = ' (' . wp_strip_all_tags( $ec_pr_lang->get_text( 'cart', 'cart_item_new_price_option' ) ) . ' ' . $ec_pr_curr->get_currency_display( $advanced_option->optionitem_price_override ) . ')';
			}
			$ed::option_detail( wp_kses_post( $advanced_option->option_label ), esc_html( $ec_pr_opt_value . $ec_pr_opt_price ) );
		}
	}

	if ( ! empty( $ec_pr_item->subscription_signup_fee ) && $ec_pr_item->subscription_signup_fee > 0 ) {
		$ed::detail( '<strong style="color:#374151;">' . wp_kses_post( $ec_pr_lang->get_text( 'product_details', 'product_details_signup_fee_notice1' ) ) . '</strong> ' . esc_html( $ec_pr_curr->get_currency_display( $ec_pr_item->subscription_signup_fee ) ) );
	}
	do_action( 'wp_easycart_print_receipt_optionitems', $ec_pr_item );

	/* Prices ( struck through when a discount applies, as before ). */
	if ( $has_unit_discount ) {
		$ec_pr_unit_html = '<span style="color:#b91c1c;text-decoration:line-through;">' . esc_html( apply_filters( 'wp_easycart_cart_item_unit_price_display', $unit_price, $ec_pr_item->product_id ) ) . '</span><br /><strong>' . esc_html( get_option( 'ec_option_show_coupon_discount_total' ) ? $ec_pr_curr->get_currency_display( $ec_pr_item->unit_price - round( $unit_discount_coupon, 2 ) ) : $ec_pr_curr->get_currency_display( $ec_pr_item->unit_price ) ) . '</strong>';
	} else {
		$ec_pr_unit_html = esc_html( apply_filters( 'wp_easycart_cart_item_unit_price_display', $unit_price, $ec_pr_item->product_id ) );
	}
	if ( $has_total_discount ) {
		$ec_pr_total_html = '<span style="color:#b91c1c;text-decoration:line-through;">' . esc_html( $total_price ) . '</span><br /><strong>' . esc_html( get_option( 'ec_option_show_coupon_discount_total' ) ? $ec_pr_curr->get_currency_display( $ec_pr_item->total_price - round( $total_discount_coupon, 2 ) ) : $ec_pr_curr->get_currency_display( $ec_pr_item->total_price ) ) . '</strong>';
	} else {
		$ec_pr_total_html = esc_html( $total_price );
	}

	$ed::item_end(
		array(
			'qty'        => $ec_pr_item->quantity,
			'unit_html'  => $ec_pr_unit_html,
			'total_html' => $ec_pr_total_html,
		)
	);
}
$ed::items_end();

/* Totals */
$ec_pr_totals   = array();
$ec_pr_totals[] = array( wp_kses_post( $ec_pr_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_subtotal' ) ), $ed::ltr( $subtotal ) );
if ( $order->tip_total > 0 ) {
	$ec_pr_totals[] = array( wp_kses_post( $ec_pr_lang->get_text( 'cart_totals', 'cart_totals_tip' ) ), $ed::ltr( $tip ) );
}
if ( $order->tax_total > 0 ) {
	$ec_pr_totals[] = array( wp_kses_post( $ec_pr_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_tax' ) ), $ed::ltr( $tax ) );
}
if ( get_option( 'ec_option_use_shipping' ) ) {
	$ec_pr_totals[] = array( wp_kses_post( $ec_pr_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_shipping' ) ), $ed::ltr( $shipping ) );
}
$ec_receipt_offers = ( isset( $wpec_offer_order_summary ) && count( $wpec_offer_order_summary ) > 0 ) ? array( 'applied_offers' => $wpec_offer_order_summary ) : ( ( isset( $order->applied_offers ) && '' != $order->applied_offers ) ? json_decode( (string) $order->applied_offers, true ) : false );
if ( is_array( $ec_receipt_offers ) && isset( $ec_receipt_offers['applied_offers'] ) ) {
	foreach ( $ec_receipt_offers['applied_offers'] as $ec_receipt_offer ) {
		if ( ! isset( $ec_receipt_offer['amount'] ) || $ec_receipt_offer['amount'] <= 0 ) {
			continue;
		}
		$ec_pr_totals[] = array( esc_html( $ec_receipt_offer['label'] . ( ( isset( $ec_receipt_offer['code'] ) && '' != $ec_receipt_offer['code'] ) ? ' (' . $ec_receipt_offer['code'] . ')' : '' ) ), $ed::ltr( '-' . $ec_pr_curr->get_currency_display( $ec_receipt_offer['amount'] ) ) );
	}
}
if ( $order->discount_total > 0 && apply_filters( 'wp_easycart_order_details_discount_display', true, $order ) ) {
	$ec_pr_totals[] = array( wp_kses_post( $ec_pr_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_discount' ) ), $ed::ltr( '-' . $discount ) );
}
if ( $has_duty ) {
	$ec_pr_totals[] = array( wp_kses_post( $ec_pr_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_duty' ) ), $ed::ltr( $duty ) );
}
if ( $tax_struct->is_vat_enabled() ) {
	$ec_pr_totals[] = array( wp_kses_post( $ec_pr_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_vat' ) ) . esc_html( $vat_rate ) . '%', $ed::ltr( $vat ) );
}
if ( $gst > 0 ) {
	$ec_pr_totals[] = array( esc_html( wp_easycart_canada_tax_label( 'gst', $order->shipping_state ) . ' (' . $gst_rate . '%)' ), $ed::money( $gst ) );
}
if ( $pst > 0 ) {
	$ec_pr_totals[] = array( esc_html( wp_easycart_canada_tax_label( 'pst', $order->shipping_state ) . ' (' . $pst_rate . '%)' ), $ed::money( $pst ) );
}
if ( $hst > 0 ) {
	$ec_pr_totals[] = array( esc_html( wp_easycart_canada_tax_label( 'hst', $order->shipping_state ) . ' (' . $hst_rate . '%)' ), $ed::money( $hst ) );
}
foreach ( (array) $order_fees as $order_fee ) {
	$ec_pr_totals[] = array( esc_html( $order_fee->fee_label ), $ed::money( $order_fee->fee_total ) );
}
if ( $order->refund_total > 0 ) {
	$ec_pr_totals[] = array(
		'label' => wp_kses_post( $ec_pr_lang->get_text( 'account_order_details', 'account_orders_details_refund_total_short' ) ),
		'value' => $ed::ltr( '-' . $refund ),
		'tone'  => 'danger',
	);
}
$ed::totals( $ec_pr_totals, array( wp_kses_post( $ec_pr_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_grand_total' ) ), $ed::ltr( $total ) ) );

/* Order notes and closing lines */
if ( get_option( 'ec_option_user_order_notes' ) && '' !== trim( (string) $order->order_customer_notes ) ) {
	$ed::section_start();
	$ed::label( wp_kses_post( $ec_pr_lang->get_text( 'cart_payment_information', 'cart_payment_information_order_notes_title' ) ) );
	$ed::card_start( array( 'padding' => '12px 14px' ) );
	echo nl2br( esc_html( wp_unslash( $order->order_customer_notes ) ) );
	$ed::card_end();
	$ed::section_end();
}
$ed::section_start( array( 'top' => 20, 'bottom' => 8 ) );
do_action( 'wpeasycart_print_receipt_order_notes_after', $order_id );
$ed::paragraph( wp_kses_post( $ec_pr_lang->get_text( 'cart_success', 'cart_payment_complete_bottom_line_1' ) ), array( 'margin' => '0 0 12px 0' ) );
$ed::paragraph( wp_kses_post( $ec_pr_lang->get_text( 'cart_success', 'cart_payment_complete_bottom_line_2' ) ), array( 'tone' => 'strong', 'margin' => '0' ) );
$ed::section_end();

/* Screen-only print button ( hidden in print and never rendered into the PDF, which prints from the PDF library ). */
echo '<tr class="ec-print-hide"><td class="ec-email-pad" align="center" style="padding:8px 32px 0 32px;">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup.
echo '<a href="#" onclick="window.print(); return false;" style="display:inline-block;padding:11px 22px;border-radius:6px;border:1px solid #d1d5db;background-color:#ffffff;' . esc_attr( $ed::css( 'strong' ) ) . 'text-decoration:none;">' . esc_html__( 'Print this receipt', 'wp-easycart' ) . '</a>';
echo '</td></tr>' . "\n";

$ed::close( array( 'signature' => false ) );
