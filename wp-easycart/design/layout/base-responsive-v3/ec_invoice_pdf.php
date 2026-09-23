<?php
/**
 * Invoice PDF ( Settings › Documents › Invoice PDF ): the invoice or receipt WP EasyCart PRO attaches to order emails and
 * lets you download from an order. Until 6.0.1 the PDF was the printable receipt ( ec_account_print_receipt.php, which
 * My Account and the admin still print ) under a heading block; this template draws the same page from a profile.
 *
 * Rendered by wp_easycart_documents::render( 'invoice', ... ) with $document ( wp_easycart_document ): the order, its
 * lines, the profile's switches ( $document->show() ) and choices ( $document->option( 'heading' ) ). The pre-6.0.1
 * printable receipt variables ( $order_details, $mysqli, ... ) are in scope too ( wp_easycart_document::legacy_vars() ).
 *
 * Always "flat" ( no grey page, no card ): dompdf draws this file, and it cannot break a nested table across pages.
 * Tables and inline styles only. Copy this file to your theme / wp-easycart-data layout folder to customise it; the file
 * name must stay the same.
 *
 * Filters kept from the 6.0.0 PDF: wp_easycart_pdf_receipt_title, wp_easycart_pdf_receipt_header_html. Actions kept from
 * the printable receipt: wp_easycart_print_receipt_optionitems, wpeasycart_print_receipt_order_notes_after.
 *
 * @since 6.0.1
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'wp_easycart_email_design' ) ) {
	require_once EC_PLUGIN_DIRECTORY . '/inc/classes/core/class-wp-easycart-email-design.php';
}
if ( ! isset( $document ) || ! ( $document instanceof wp_easycart_document ) ) {
	$document = new wp_easycart_document( 'invoice', isset( $order_id ) ? (int) $order_id : 0, wp_easycart_documents::resolve( 'invoice' ) );
}
if ( ! $document->order ) {
	return;
}

$ed          = 'wp_easycart_email_design';
$ec_in_lang  = wp_easycart_language();
$ec_in_curr  = $GLOBALS['currency'];
$ec_in_order = $document->order;
$ec_in_id    = (int) $ec_in_order->order_id;
$ec_in_db    = ( isset( $mysqli ) && is_object( $mysqli ) ) ? $mysqli : ( class_exists( 'ec_db_admin' ) ? new ec_db_admin() : null );
$ec_in_items = ( isset( $order_details ) && is_array( $order_details ) ) ? $order_details : $document->lines;
$ec_in_price = $document->show( 'prices' );

/* Offers v2: line-level offer flags keyed by orderdetail_id, and the order-level applied-offers snapshot. */
$wpec_offer_line_flags    = array();
$wpec_offer_order_summary = array();
if ( function_exists( 'wp_easycart_offers_active' ) && wp_easycart_offers_active() ) {
	global $wpdb;
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT orderdetail_id, product_id, is_free_gift, bundle_group_key, bundle_product_id, applied_offers FROM ec_orderdetail WHERE order_id = %d', $ec_in_id ) ) as $wpec_offer_flag_row ) {
		$wpec_offer_line_flags[ (int) $wpec_offer_flag_row->orderdetail_id ] = $wpec_offer_flag_row;
	}
	$wpec_offer_order_json    = $wpdb->get_var( $wpdb->prepare( 'SELECT applied_offers FROM ec_order WHERE order_id = %d', $ec_in_id ) );
	$wpec_offer_order_decoded = ( $wpec_offer_order_json ) ? json_decode( (string) $wpec_offer_order_json, true ) : false;
	if ( is_array( $wpec_offer_order_decoded ) && isset( $wpec_offer_order_decoded['applied_offers'] ) && is_array( $wpec_offer_order_decoded['applied_offers'] ) ) {
		$wpec_offer_order_summary = $wpec_offer_order_decoded['applied_offers'];
	}
}

/* Which addresses does this order actually have? */
$ec_in_has_shipping = false;
$ec_in_has_billing  = false;
if ( get_option( 'ec_option_use_shipping' ) ) {
	foreach ( $ec_in_items as $ec_in_line ) {
		if ( ! empty( $ec_in_line->is_shippable ) ) {
			$ec_in_has_shipping = true;
		}
	}
}
if ( $ec_in_has_shipping && '' === trim( (string) $ec_in_order->shipping_address_line_1 ) && '' === trim( (string) $ec_in_order->shipping_city ) && '' === trim( (string) $ec_in_order->shipping_zip ) ) {
	$ec_in_has_shipping = false;
}
foreach ( array( 'billing_address_line_1', 'billing_city', 'billing_state', 'billing_zip', 'billing_country' ) as $ec_in_billing_field ) {
	if ( isset( $ec_in_order->{$ec_in_billing_field} ) && '' !== trim( (string) $ec_in_order->{$ec_in_billing_field} ) ) {
		$ec_in_has_billing = true;
	}
}

/* Heading block above the accent bar: business details on the left, the heading, order number and date on the right. */
$ec_in_title = ( 'receipt' === $document->option( 'heading' ) )
	? wp_easycart_documents::text( 'receipt_title', __( 'Receipt', 'wp-easycart' ) )
	: wp_easycart_documents::text( 'invoice_title', __( 'Invoice', 'wp-easycart' ) );
$ec_in_title = (string) apply_filters( 'wp_easycart_pdf_receipt_title', wp_strip_all_tags( $ec_in_title ), $ec_in_order );
$ec_in_head  = '';
$ec_in_cell  = 'vertical-align:top;padding:0 0 10px 0;font-size:10px;line-height:1.45;color:#1d2327;font-family:' . $ed::ctx()['font'] . ';';
if ( $document->show( 'seller' ) || $document->show( 'heading' ) ) {
	$ec_in_seller = trim( (string) get_option( 'ec_option_pdf_seller_details', '' ) );
	if ( '' === $ec_in_seller ) {
		$ec_in_seller = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	}
	$ec_in_head  = '<table role="presentation" class="wpec-pdf-head" width="100%" border="0" cellpadding="0" cellspacing="0" style="width:100%;margin:0 0 18px 0;border-bottom:2px solid #1d2327;"><tr>';
	$ec_in_head .= '<td class="wpec-pdf-seller" width="55%" style="' . esc_attr( $ec_in_cell ) . '">' . ( $document->show( 'seller' ) ? nl2br( esc_html( $ec_in_seller ) ) : '&nbsp;' ) . '</td>';
	$ec_in_head .= '<td width="45%" align="right" style="' . esc_attr( $ec_in_cell . 'text-align:right;' ) . '">';
	if ( $document->show( 'heading' ) ) {
		$ec_in_head .= '<div class="wpec-pdf-title" style="font-size:22px;font-weight:bold;letter-spacing:1px;text-transform:uppercase;margin:0 0 4px 0;">' . esc_html( $ec_in_title ) . '</div>';
		$ec_in_head .= '<div>' . wp_easycart_documents::text( 'invoice_order_number', __( 'Order number:', 'wp-easycart' ) ) . ' ' . esc_html( $ec_in_id ) . '</div>';
		$ec_in_head .= '<div>' . wp_easycart_documents::text( 'invoice_date', __( 'Date:', 'wp-easycart' ) ) . ' ' . esc_html( $document->date() ) . '</div>';
	}
	$ec_in_head .= '</td></tr></table>';
}
$ec_in_head = (string) apply_filters( 'wp_easycart_pdf_receipt_header_html', $ec_in_head, $ec_in_order );

$ed::open(
	wp_easycart_documents::open_args(
		'invoice',
		$document->fields,
		array(
			'title'     => $ec_in_title . ' ' . $ec_in_id,
			'flat'      => true,
			'top_html'  => $ec_in_head,
			/* Settings › Documents preview: the page margins the PDF gets from its @page rule. */
			'extra_css' => ( 'preview' === $document->output ) ? 'body{padding:32px 40px !important;box-sizing:border-box !important;}' : '',
		)
	)
);

/* Pickup banners */
if ( ! empty( $ec_in_order->includes_preorder_items ) ) {
	$ec_in_pickup_open  = date( apply_filters( 'wp_easycart_pickup_date_placeholder_format', 'F d, Y g:i A' ), strtotime( $ec_in_order->pickup_date ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date -- the stored pickup time is already local, as on the printable receipt.
	$ec_in_pickup_close = date( apply_filters( 'wp_easycart_pickup_time_close_placeholder_format', 'g:i A' ), strtotime( $ec_in_order->pickup_date . ' +1 hour' ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date -- as above.
	$ed::notice( esc_html( str_replace( '[pickup_date]', $ec_in_pickup_open . ' - ' . $ec_in_pickup_close, $ec_in_lang->get_text( 'ec_errors', 'preorder_message' ) ) ), 'success' );
}
if ( ! empty( $ec_in_order->includes_restaurant_type ) ) {
	$ec_in_pickup_time = date( apply_filters( 'wp_easycart_pickup_time_placeholder_format', 'g:i A F d, Y' ), strtotime( $ec_in_order->pickup_time ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date -- as above.
	$ed::notice( esc_html( str_replace( '[pickup_time]', $ec_in_pickup_time, $ec_in_lang->get_text( 'ec_errors', 'restaurant_message' ) ) ), 'success' );
}

/* Greeting */
if ( $document->show( 'intro' ) ) {
	$ed::section_start();
	$ed::heading( wp_kses_post( $ec_in_lang->get_text( 'cart_success', 'cart_payment_complete_line_1' ) ) . ' ' . esc_html( trim( $ec_in_order->billing_first_name . ' ' . $ec_in_order->billing_last_name ) ) );
	$ed::paragraph( wp_kses_post( $ec_in_lang->get_text( 'cart_success', 'cart_payment_complete_line_2' ) ) . ' <strong style="color:#111827;">' . esc_html( $ec_in_id ) . ' &mdash; ' . esc_html( $document->date() ) . '</strong>' );
	$ed::paragraph( wp_kses_post( $ec_in_lang->get_text( 'cart_success', 'cart_payment_complete_line_3' ) ) );
	$ed::paragraph( wp_kses_post( $ec_in_lang->get_text( 'cart_success', 'cart_payment_complete_line_4' ) ), array( 'margin' => '0' ) );
	$ed::section_end();
}

/* Addresses, with the customer's VAT number and email under them */
$ec_in_cards = array();
if ( $ec_in_has_shipping && $document->show( 'shipping' ) ) {
	$ec_in_cards[] = array(
		'label'   => wp_kses_post( $ec_in_lang->get_text( 'cart_success', 'cart_payment_complete_shipping_label' ) ),
		'address' => $ed::address( $ec_in_order, 'shipping' ),
	);
}
if ( $ec_in_has_billing && $document->show( 'billing' ) ) {
	$ec_in_cards[] = array(
		'label'   => wp_kses_post( $ec_in_lang->get_text( 'cart_success', 'cart_payment_complete_billing_label' ) ),
		'address' => $ed::address( $ec_in_order, 'billing' ),
	);
}
$ec_in_notes = array();
if ( $document->show( 'vat_number' ) && '' !== (string) $ec_in_order->vat_registration_number ) {
	$ec_in_notes[] = '<strong>' . wp_kses_post( $ec_in_lang->get_text( 'cart_billing_information', 'cart_billing_information_vat_registration_number' ) ) . ':</strong> ' . esc_html( $ec_in_order->vat_registration_number );
}
if ( $document->show( 'email' ) && '' !== (string) $ec_in_order->user_email ) {
	$ec_in_notes[] = esc_html( $ec_in_order->user_email );
}
if ( $ec_in_cards || $ec_in_notes ) {
	$ed::address_cards( $ec_in_cards, implode( '<br />', $ec_in_notes ) );
}

/* Items */
$ec_in_labels = array(
	'product' => wp_kses_post( $ec_in_lang->get_text( 'cart_success', 'cart_payment_complete_details_header_1' ) ),
	'qty'     => wp_kses_post( $ec_in_lang->get_text( 'cart_success', 'cart_payment_complete_details_header_2' ) ),
);
if ( $ec_in_price ) {
	$ec_in_labels['unit']  = wp_kses_post( $ec_in_lang->get_text( 'cart_success', 'cart_payment_complete_details_header_3' ) );
	$ec_in_labels['total'] = wp_kses_post( $ec_in_lang->get_text( 'cart_success', 'cart_payment_complete_details_header_4' ) );
}
$ed::items_start( $ec_in_labels );
foreach ( $ec_in_items as $ec_in_item ) {
	/* Title, with the offers v2 markers. */
	$ec_in_title_html = wp_kses_post( $ec_in_item->title );
	$wpec_line_flags  = ( isset( $ec_in_item->orderdetail_id ) && isset( $wpec_offer_line_flags[ (int) $ec_in_item->orderdetail_id ] ) ) ? $wpec_offer_line_flags[ (int) $ec_in_item->orderdetail_id ] : false;
	if ( $wpec_line_flags && $wpec_line_flags->is_free_gift && function_exists( 'wp_easycart_offers_text' ) ) {
		$ec_in_title_html .= ' <span style="display:inline-block; margin-left:6px; padding:1px 8px; background:#fce7f0; color:#c2185b; font-size:11px; font-weight:bold; border-radius:3px; text-transform:uppercase;">' . wp_kses_post( wp_easycart_offers_text( 'cart_offers', 'gift_line_label' ) ) . '</span>';
	}
	if ( $wpec_line_flags && '' != $wpec_line_flags->bundle_group_key && $wpec_line_flags->bundle_product_id != $wpec_line_flags->product_id && function_exists( 'wp_easycart_offers_text' ) ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- numeric strings from the database.
		$ec_in_title_html .= ' <span style="display:inline-block; margin-left:6px; padding:1px 8px; background:#eef1f4; color:#4a5560; font-size:11px; font-weight:bold; border-radius:3px; text-transform:uppercase;">' . wp_kses_post( wp_easycart_offers_text( 'cart_offers', 'bundle_line_label' ) ) . '</span>';
	}
	if ( $ec_in_price && $wpec_line_flags && isset( $wpec_line_flags->applied_offers ) && '' != $wpec_line_flags->applied_offers ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- string from the database.
		$wpec_line_offer_rows = json_decode( (string) $wpec_line_flags->applied_offers, true );
		if ( is_array( $wpec_line_offer_rows ) && count( $wpec_line_offer_rows ) > 0 ) {
			$ec_in_title_html .= '<div style="margin-top:4px;">';
			foreach ( $wpec_line_offer_rows as $wpec_line_offer_row ) {
				if ( ! isset( $wpec_line_offer_row['label'] ) || ! isset( $wpec_line_offer_row['amount'] ) || (float) $wpec_line_offer_row['amount'] <= 0 ) {
					continue;
				}
				$ec_in_title_html .= '<span style="display:inline-block; margin:2px 4px 0 0; padding:1px 8px; background:#e7f5ec; color:#1f7a3d; font-size:11px; font-weight:normal; border-radius:3px;">' . esc_html( $wpec_line_offer_row['label'] ) . ' &minus;' . esc_html( $ec_in_curr->get_currency_display( (float) $wpec_line_offer_row['amount'] ) ) . '</span>';
			}
			$ec_in_title_html .= '</div>';
		}
	}

	$ed::item_start(
		array(
			'image_url'   => $document->show( 'image' ) ? $ed::product_image_url( $ec_in_item->image1, ! empty( $ec_in_item->is_deconetwork ), isset( $ec_in_item->deconetwork_image_link ) ? $ec_in_item->deconetwork_image_link : '' ) : '',
			'image_alt'   => $ec_in_item->title,
			'image_width' => 70,
			'title_html'  => $ec_in_title_html,
		)
	);
	if ( $document->show( 'sku' ) && '' !== (string) $ec_in_item->model_number ) {
		$ed::detail( esc_html( $ec_in_item->model_number ), array( 'nolink' => true ) );
	}

	if ( $document->show( 'options' ) ) {
		/* Basic options */
		if ( empty( $ec_in_item->use_advanced_optionset ) || ! empty( $ec_in_item->use_both_option_types ) ) {
			for ( $ec_in_n = 1; $ec_in_n <= 5; $ec_in_n++ ) {
				$ec_in_opt_name = isset( $ec_in_item->{'optionitem_name_' . $ec_in_n} ) ? (string) $ec_in_item->{'optionitem_name_' . $ec_in_n} : '';
				if ( '' === $ec_in_opt_name ) {
					continue;
				}
				$ec_in_opt_price = isset( $ec_in_item->{'optionitem_price_' . $ec_in_n} ) ? (float) $ec_in_item->{'optionitem_price_' . $ec_in_n} : 0;
				$ec_in_opt_text  = '';
				if ( $ec_in_price && $ec_in_opt_price < 0 ) {
					$ec_in_opt_text = ' (' . $ec_in_curr->get_currency_display( $ec_in_opt_price ) . ')';
				} elseif ( $ec_in_price && $ec_in_opt_price > 0 ) {
					$ec_in_opt_text = ' (+' . $ec_in_curr->get_currency_display( $ec_in_opt_price ) . ')';
				}
				$ed::detail( wp_kses_post( $ec_in_opt_name ) . esc_html( $ec_in_opt_text ) );
			}
		}
		/* Advanced ( option set ) options */
		if ( $ec_in_db && ( ! empty( $ec_in_item->use_advanced_optionset ) || ! empty( $ec_in_item->use_both_option_types ) ) ) {
			foreach ( (array) $ec_in_db->get_order_options( $ec_in_item->orderdetail_id ) as $advanced_option ) {
				if ( 'file' === $advanced_option->option_type ) {
					$ec_in_file_parts = explode( '/', (string) $advanced_option->option_value );
					$ec_in_opt_value  = $ec_in_file_parts[ count( $ec_in_file_parts ) - 1 ];
				} elseif ( 'grid' === $advanced_option->option_type ) {
					$ec_in_opt_value = $advanced_option->optionitem_name . ' (' . $advanced_option->option_value . ')';
				} else {
					$ec_in_opt_value = $advanced_option->option_value;
				}
				$ec_in_opt_price = '';
				if ( ! $ec_in_price ) {
					$ec_in_opt_price = '';
				} elseif ( ! empty( $advanced_option->optionitem_enable_custom_price_label ) && ( ( isset( $advanced_option->optionitem_price ) && 0 != $advanced_option->optionitem_price ) || ( isset( $advanced_option->optionitem_price_onetime ) && 0 != $advanced_option->optionitem_price_onetime ) ) ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- numeric strings from the database.
					$ec_in_opt_price = ' ' . $advanced_option->optionitem_custom_price_label;
				} elseif ( isset( $advanced_option->optionitem_price ) && $advanced_option->optionitem_price > 0 ) {
					$ec_in_opt_price = ' (+' . $ec_in_curr->get_currency_display( $advanced_option->optionitem_price ) . ' ' . wp_strip_all_tags( $ec_in_lang->get_text( 'cart', 'cart_item_adjustment' ) ) . ')';
				} elseif ( isset( $advanced_option->optionitem_price ) && $advanced_option->optionitem_price < 0 ) {
					$ec_in_opt_price = ' (' . $ec_in_curr->get_currency_display( $advanced_option->optionitem_price ) . ' ' . wp_strip_all_tags( $ec_in_lang->get_text( 'cart', 'cart_item_adjustment' ) ) . ')';
				} elseif ( isset( $advanced_option->optionitem_price_onetime ) && $advanced_option->optionitem_price_onetime > 0 ) {
					$ec_in_opt_price = ' (+' . $ec_in_curr->get_currency_display( $advanced_option->optionitem_price_onetime ) . ' ' . wp_strip_all_tags( $ec_in_lang->get_text( 'cart', 'cart_order_adjustment' ) ) . ')';
				} elseif ( isset( $advanced_option->optionitem_price_onetime ) && $advanced_option->optionitem_price_onetime < 0 ) {
					$ec_in_opt_price = ' (' . $ec_in_curr->get_currency_display( $advanced_option->optionitem_price_onetime ) . ' ' . wp_strip_all_tags( $ec_in_lang->get_text( 'cart', 'cart_order_adjustment' ) ) . ')';
				} elseif ( isset( $advanced_option->optionitem_price_override ) && $advanced_option->optionitem_price_override > -1 ) {
					$ec_in_opt_price = ' (' . wp_strip_all_tags( $ec_in_lang->get_text( 'cart', 'cart_item_new_price_option' ) ) . ' ' . $ec_in_curr->get_currency_display( $advanced_option->optionitem_price_override ) . ')';
				}
				$ed::option_detail( wp_kses_post( $advanced_option->option_label ), esc_html( $ec_in_opt_value . $ec_in_opt_price ) );
			}
		}
	}

	if ( $ec_in_price && ! empty( $ec_in_item->subscription_signup_fee ) && $ec_in_item->subscription_signup_fee > 0 ) {
		$ed::detail( '<strong style="color:#374151;">' . wp_kses_post( $ec_in_lang->get_text( 'product_details', 'product_details_signup_fee_notice1' ) ) . '</strong> ' . esc_html( $ec_in_curr->get_currency_display( $ec_in_item->subscription_signup_fee ) ) );
	}
	do_action( 'wp_easycart_print_receipt_optionitems', $ec_in_item );

	$ec_in_end = array( 'qty' => $ec_in_item->quantity );
	if ( $ec_in_price ) {
		/* Prices, struck through when a discount applies ( as on the printable receipt ). */
		$ec_in_unit_promo  = $ec_in_item->unit_discount_promotion;
		$ec_in_unit_coupon = $ec_in_item->unit_discount_coupon;
		$ec_in_unit_base   = $ec_in_item->unit_price;
		if ( get_option( 'ec_option_show_promotion_discount_total' ) && $ec_in_unit_promo > 0 ) {
			$ec_in_unit_base += $ec_in_unit_promo;
		}
		$ec_in_unit      = $ec_in_curr->get_currency_display( $ec_in_unit_base );
		$ec_in_unit_disc = ( apply_filters( 'wp_easycart_order_details_show_discount_unit', true ) && ( ( get_option( 'ec_option_show_promotion_discount_total' ) && $ec_in_unit_promo > 0 ) || ( get_option( 'ec_option_show_coupon_discount_total' ) && $ec_in_unit_coupon > 0 ) ) );

		$ec_in_total_promo  = $ec_in_item->total_discount_promotion;
		$ec_in_total_coupon = $ec_in_item->total_discount_coupon;
		$ec_in_total_base   = $ec_in_item->total_price;
		if ( get_option( 'ec_option_show_promotion_discount_total' ) && $ec_in_total_promo > 0 ) {
			$ec_in_total_base += $ec_in_total_promo;
		}
		$ec_in_total      = $ec_in_curr->get_currency_display( $ec_in_total_base );
		$ec_in_total_disc = ( apply_filters( 'wp_easycart_order_details_show_discount_total', true ) && ( ( get_option( 'ec_option_show_promotion_discount_total' ) && $ec_in_total_promo > 0 ) || ( get_option( 'ec_option_show_coupon_discount_total' ) && $ec_in_total_coupon > 0 ) ) );

		if ( $ec_in_unit_disc ) {
			$ec_in_end['unit_html'] = '<span style="color:#b91c1c;text-decoration:line-through;">' . esc_html( apply_filters( 'wp_easycart_cart_item_unit_price_display', $ec_in_unit, $ec_in_item->product_id ) ) . '</span><br /><strong>' . esc_html( get_option( 'ec_option_show_coupon_discount_total' ) ? $ec_in_curr->get_currency_display( $ec_in_item->unit_price - round( $ec_in_unit_coupon, 2 ) ) : $ec_in_curr->get_currency_display( $ec_in_item->unit_price ) ) . '</strong>';
		} else {
			$ec_in_end['unit_html'] = esc_html( apply_filters( 'wp_easycart_cart_item_unit_price_display', $ec_in_unit, $ec_in_item->product_id ) );
		}
		if ( $ec_in_total_disc ) {
			$ec_in_end['total_html'] = '<span style="color:#b91c1c;text-decoration:line-through;">' . esc_html( $ec_in_total ) . '</span><br /><strong>' . esc_html( get_option( 'ec_option_show_coupon_discount_total' ) ? $ec_in_curr->get_currency_display( $ec_in_item->total_price - round( $ec_in_total_coupon, 2 ) ) : $ec_in_curr->get_currency_display( $ec_in_item->total_price ) ) . '</strong>';
		} else {
			$ec_in_end['total_html'] = esc_html( $ec_in_total );
		}
	}
	$ed::item_end( $ec_in_end );
}
$ed::items_end();

/* Totals ( the same lines and filters as the printable receipt ) */
if ( $ec_in_price ) {
	$ec_in_sub = $ec_in_order->sub_total;
	if ( get_option( 'ec_option_show_coupon_discount_total' ) ) {
		foreach ( $ec_in_items as $ec_in_line ) {
			if ( $ec_in_line->total_discount_coupon > 0 ) {
				$ec_in_sub -= $ec_in_line->total_discount_coupon;
			}
		}
	}
	$ec_in_sub   = apply_filters( 'wp_easycart_order_display_sub_total', $ec_in_sub, $ec_in_order->sub_total, $ec_in_items );
	$ec_in_grand = apply_filters( 'wp_easycart_order_display_grand_total', $ec_in_order->grand_total, $ec_in_order );

	$ec_in_totals   = array();
	$ec_in_totals[] = array( wp_kses_post( $ec_in_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_subtotal' ) ), $ed::money( $ec_in_sub ) );
	if ( $ec_in_order->tip_total > 0 ) {
		$ec_in_totals[] = array( wp_kses_post( $ec_in_lang->get_text( 'cart_totals', 'cart_totals_tip' ) ), $ed::money( $ec_in_order->tip_total ) );
	}
	if ( $ec_in_order->tax_total > 0 ) {
		$ec_in_totals[] = array( wp_kses_post( $ec_in_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_tax' ) ), $ed::money( $ec_in_order->tax_total ) );
	}
	if ( get_option( 'ec_option_use_shipping' ) ) {
		$ec_in_totals[] = array( wp_kses_post( $ec_in_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_shipping' ) ), $ed::money( $ec_in_order->shipping_total ) );
	}
	$ec_in_offers = ( count( $wpec_offer_order_summary ) > 0 ) ? array( 'applied_offers' => $wpec_offer_order_summary ) : ( ( isset( $ec_in_order->applied_offers ) && '' != $ec_in_order->applied_offers ) ? json_decode( (string) $ec_in_order->applied_offers, true ) : false ); // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- string from the database.
	if ( is_array( $ec_in_offers ) && isset( $ec_in_offers['applied_offers'] ) && is_array( $ec_in_offers['applied_offers'] ) ) {
		foreach ( $ec_in_offers['applied_offers'] as $ec_in_offer ) {
			if ( ! isset( $ec_in_offer['amount'] ) || $ec_in_offer['amount'] <= 0 ) {
				continue;
			}
			$ec_in_totals[] = array( esc_html( $ec_in_offer['label'] . ( ( isset( $ec_in_offer['code'] ) && '' != $ec_in_offer['code'] ) ? ' (' . $ec_in_offer['code'] . ')' : '' ) ), $ed::ltr( '-' . $ec_in_curr->get_currency_display( $ec_in_offer['amount'] ) ) ); // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- string from the database.
		}
	}
	if ( $ec_in_order->discount_total > 0 && apply_filters( 'wp_easycart_order_details_discount_display', true, $ec_in_order ) ) {
		$ec_in_totals[] = array( wp_kses_post( $ec_in_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_discount' ) ), $ed::ltr( '-' . $ec_in_curr->get_currency_display( $ec_in_order->discount_total ) ) );
	}
	if ( $ec_in_order->duty_total > 0 ) {
		$ec_in_totals[] = array( wp_kses_post( $ec_in_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_duty' ) ), $ed::money( $ec_in_order->duty_total ) );
	}
	$ec_in_tax = class_exists( 'ec_tax' ) ? new ec_tax( 0, 0, 0, '', '' ) : null;
	if ( $ec_in_tax && $ec_in_tax->is_vat_enabled() ) {
		if ( $ec_in_order->vat_rate > 0 ) {
			$ec_in_vat_rate = $ec_in_order->vat_rate;
		} elseif ( ( $ec_in_order->grand_total - $ec_in_order->vat_total ) > 0 ) {
			$ec_in_vat_rate = ( $ec_in_order->vat_total / ( $ec_in_order->grand_total - $ec_in_order->vat_total ) ) * 100;
		} else {
			$ec_in_vat_rate = 0;
		}
		$ec_in_vat_rate = rtrim( rtrim( number_format( (float) $ec_in_vat_rate, 3, '.', '' ), '0' ), '.' );
		$ec_in_totals[] = array( wp_kses_post( $ec_in_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_vat' ) ) . esc_html( $ec_in_vat_rate ) . '%', $ed::money( $ec_in_order->vat_total ) );
	}
	foreach ( array( 'gst', 'pst', 'hst' ) as $ec_in_ca ) {
		if ( $ec_in_order->{$ec_in_ca . '_total'} > 0 ) {
			$ec_in_ca_rate  = $ec_in_order->{$ec_in_ca . '_rate'};
			$ec_in_ca_rate  = ( floor( $ec_in_ca_rate ) == $ec_in_ca_rate ) ? number_format( $ec_in_ca_rate, 0, '', '' ) : $ec_in_ca_rate; // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- numeric strings from the database.
			$ec_in_ca_label = function_exists( 'wp_easycart_canada_tax_label' ) ? wp_easycart_canada_tax_label( $ec_in_ca, $ec_in_order->shipping_state ) : strtoupper( $ec_in_ca );
			$ec_in_totals[] = array( esc_html( $ec_in_ca_label . ' (' . $ec_in_ca_rate . '%)' ), $ed::money( $ec_in_order->{$ec_in_ca . '_total'} ) );
		}
	}
	if ( $ec_in_db && method_exists( $ec_in_db, 'get_order_fees' ) ) {
		foreach ( (array) $ec_in_db->get_order_fees( $ec_in_id ) as $ec_in_fee ) {
			$ec_in_totals[] = array( esc_html( $ec_in_fee->fee_label ), $ed::money( $ec_in_fee->fee_total ) );
		}
	}
	if ( $ec_in_order->refund_total > 0 ) {
		$ec_in_totals[] = array(
			'label' => wp_kses_post( $ec_in_lang->get_text( 'account_order_details', 'account_orders_details_refund_total_short' ) ),
			'value' => $ed::ltr( '-' . $ec_in_curr->get_currency_display( $ec_in_order->refund_total ) ),
			'tone'  => 'danger',
		);
	}
	$ed::totals( $ec_in_totals, array( wp_kses_post( $ec_in_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_grand_total' ) ), $ed::money( $ec_in_grand ) ) );
}

/* Customer's order notes */
if ( $document->show( 'order_notes' ) && get_option( 'ec_option_user_order_notes' ) && '' !== trim( (string) $ec_in_order->order_customer_notes ) ) {
	$ed::section_start();
	$ed::label( wp_kses_post( $ec_in_lang->get_text( 'cart_payment_information', 'cart_payment_information_order_notes_title' ) ) );
	$ed::card_start( array( 'padding' => '12px 14px' ) );
	echo nl2br( esc_html( wp_unslash( $ec_in_order->order_customer_notes ) ) );
	$ed::card_end();
	$ed::section_end();
}

/* Closing lines */
$ed::section_start( array( 'top' => 20, 'bottom' => 8 ) );
do_action( 'wpeasycart_print_receipt_order_notes_after', $ec_in_id );
if ( $document->show( 'intro' ) ) {
	$ed::paragraph( wp_kses_post( $ec_in_lang->get_text( 'cart_success', 'cart_payment_complete_bottom_line_1' ) ), array( 'margin' => '0 0 12px 0' ) );
	$ed::paragraph( wp_kses_post( $ec_in_lang->get_text( 'cart_success', 'cart_payment_complete_bottom_line_2' ) ), array( 'tone' => 'strong', 'margin' => '0' ) );
}
$ed::section_end();

do_action( 'wp_easycart_invoice_pdf_after', $document );

/* No signature text on paper; the footer image and the store address only when the profile asks for them. */
$ed::close( wp_easycart_documents::close_args( 'invoice', $document->fields, array( 'signature_text' => false ) ) );
