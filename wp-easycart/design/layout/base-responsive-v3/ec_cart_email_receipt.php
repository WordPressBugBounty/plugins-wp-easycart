<?php
/**
 * Order receipt email ( customer copy ) and new-order notification ( admin copy, $is_admin true ).
 *
 * Included from ec_orderdisplay::send_email_receipt() as a method of ec_orderdisplay ( $this ), with:
 *   $is_admin, $email_logo_url, $store_page, $permalink_divider, $tax_struct ( ec_tax ), and the formatted amounts
 *   $subtotal, $tip, $tax, $shipping, $discount, $duty ( + $has_duty ), $vat, $vat_rate, $total, $refund,
 *   plus the raw Canadian tax values $gst / $gst_rate, $pst / $pst_rate, $hst / $hst_rate.
 * Reads $this->cart->cart ( order lines ), $this->mysqli ( advanced options ), the order / address properties,
 * $this->order_fees and $this->applied_offers.
 *
 * 6.0.0: rebuilt on the shared email design ( wp_easycart_email_design, inc/classes/core/class-wp-easycart-email-design.php ).
 * The admin copy carries a "Store notification" label and a button to the order in the admin. Copy this file to your
 * theme / wp-easycart-data layout folder to customise it; the file name must stay the same. The PDF / printed receipt is
 * built from ec_account_print_receipt.php, not from this file.
 *
 * Hooks kept ( same names, arguments and relative position ): wp_easycart_email_receipt_top, wp_easycart_order_email_receipt_after_success_lines,
 *   wpeasycart_email_receipt_enable_view_order_button, wp_easycart_email_receipt_pre_items, wp_easycart_email_receipt_image_width,
 *   wpeasycart_email_receipt_line_item, wp_easycart_email_receipt_optionitems, wp_easycart_cart_item_unit_price_display,
 *   wp_easycart_pickup_date_placeholder_format, wp_easycart_pickup_time_close_placeholder_format,
 *   wp_easycart_pickup_time_placeholder_format, wpeasycart_email_receipt_order_notes_after.
 *
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'wp_easycart_email_design' ) ) {
	require_once EC_PLUGIN_DIRECTORY . '/inc/classes/core/class-wp-easycart-email-design.php';
}

global $wpdb;
$ed              = 'wp_easycart_email_design';
$ec_receipt_lang = wp_easycart_language();
if ( ! isset( $is_admin ) ) {
	$is_admin = false;
}
$ec_receipt_order_id = (int) $this->order_id;
$ec_receipt_items    = ( isset( $this->cart ) && is_object( $this->cart ) && isset( $this->cart->cart ) && is_array( $this->cart->cart ) ) ? $this->cart->cart : array();

/* Offers v2: line-level flags keyed by orderdetail_id and the order-level applied-offers snapshot. */
$wpec_offer_line_flags    = array();
$wpec_offer_order_summary = array();
if ( function_exists( 'wp_easycart_offers_active' ) && wp_easycart_offers_active() && $ec_receipt_order_id > 0 ) {
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT orderdetail_id, product_id, is_free_gift, bundle_group_key, bundle_product_id, applied_offers FROM ec_orderdetail WHERE order_id = %d', $ec_receipt_order_id ) ) as $wpec_offer_flag_row ) {
		$wpec_offer_line_flags[ (int) $wpec_offer_flag_row->orderdetail_id ] = $wpec_offer_flag_row;
	}
	$wpec_offer_order_json    = $wpdb->get_var( $wpdb->prepare( 'SELECT applied_offers FROM ec_order WHERE order_id = %d', $ec_receipt_order_id ) );
	$wpec_offer_order_decoded = ( $wpec_offer_order_json ) ? json_decode( (string) $wpec_offer_order_json, true ) : false;
	if ( is_array( $wpec_offer_order_decoded ) && isset( $wpec_offer_order_decoded['applied_offers'] ) && is_array( $wpec_offer_order_decoded['applied_offers'] ) ) {
		$wpec_offer_order_summary = $wpec_offer_order_decoded['applied_offers'];
	}
}

/* Shipping and billing presence. */
$has_shipping = false;
$has_billing  = false;
if ( get_option( 'ec_option_use_shipping' ) ) {
	foreach ( $ec_receipt_items as $cart_item ) {
		if ( ! empty( $cart_item->is_shippable ) ) {
			$has_shipping = true;
		}
	}
}
foreach ( array( 'billing_address_line_1', 'billing_city', 'billing_state', 'billing_zip', 'billing_country' ) as $ec_receipt_billing_key ) {
	if ( isset( $this->{$ec_receipt_billing_key} ) && '' !== (string) $this->{$ec_receipt_billing_key} ) {
		$has_billing = true;
	}
}

/* Shipping method name. */
$ec_receipt_shipping_method = '';
if ( $has_shipping && isset( $this->cart->shipping_subtotal ) && $this->cart->shipping_subtotal > 0 ) {
	$ec_receipt_cart_data = ( isset( $GLOBALS['ec_cart_data'] ) && is_object( $GLOBALS['ec_cart_data'] ) && isset( $GLOBALS['ec_cart_data']->cart_data ) ) ? $GLOBALS['ec_cart_data']->cart_data : null;
	if ( isset( $this->shipping ) && is_object( $this->shipping ) && 'fraktjakt' === $this->shipping->shipping_method ) {
		$ec_receipt_shipping_method = $this->shipping->get_selected_shipping_method();
	} elseif ( $ec_receipt_cart_data && '' !== (string) $ec_receipt_cart_data->shipping_method && 'standard' !== $ec_receipt_cart_data->shipping_method && method_exists( $this, 'get_shipping_method_name' ) ) {
		$ec_receipt_shipping_method = $this->get_shipping_method_name( $ec_receipt_cart_data->shipping_method );
	} elseif ( isset( $this->shipping ) && is_object( $this->shipping ) && ( 'price' === $this->shipping->shipping_method || 'weight' === $this->shipping->shipping_method ) && $ec_receipt_cart_data && '' !== (string) $ec_receipt_cart_data->ship_express ) {
		$ec_receipt_shipping_method = $ec_receipt_lang->get_text( 'cart_estimate_shipping', 'cart_estimate_shipping_express' );
	} else {
		$ec_receipt_shipping_method = $ec_receipt_lang->get_text( 'cart_estimate_shipping', 'cart_estimate_shipping_standard' );
	}
} elseif ( $has_shipping && isset( $this->shipping_method ) ) {
	$ec_receipt_shipping_method = (string) $this->shipping_method;
}

/* Coupon line. */
$ec_receipt_promo = '';
if ( isset( $this->promo_code ) && '' !== (string) $this->promo_code ) {
	$ec_receipt_promo = $this->promo_code;
	if ( get_option( 'ec_option_show_coupon_message' ) && isset( $this->promo_code_message ) && '' !== (string) $this->promo_code_message ) {
		$ec_receipt_promo = $this->promo_code_message;
	}
}

$ec_receipt_view_url  = wpeasycart_links()->get_account_page( 'order_details', array( 'order_id' => (int) $this->order_id, 'ec_guest_key' => ( ( '' != $this->guest_key ) ? $this->guest_key : null ) ) ); // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- guest_key may be null on older orders.
$ec_receipt_orders_url = wpeasycart_links()->get_account_page( 'order_details', array( 'order_id' => (int) $this->order_id ) );
$ec_receipt_img_width  = (int) apply_filters( 'wp_easycart_email_receipt_image_width', 70 );
$ec_receipt_currency   = $GLOBALS['currency'];

$ed::open(
	array(
		'title'     => wp_strip_all_tags( $ec_receipt_lang->get_text( 'cart_success', 'cart_payment_receipt_title' ) ) . ' ' . $ec_receipt_order_id,
		'preheader' => $ec_receipt_lang->get_text( 'cart_success', 'cart_payment_complete_line_2' ) . ' ' . $ec_receipt_order_id,
		'logo_url'  => (string) $email_logo_url,
		'store_url' => (string) $store_page,
		'eyebrow'   => ( ! empty( $is_admin ) ) ? __( 'Store notification', 'wp-easycart' ) : '',
	)
);
$ec_receipt_ctx = $ed::ctx();

/* Payment status banners */
if ( ! $this->is_approved && ( 7 == $this->orderstatus_id || 9 == $this->orderstatus_id || 19 == $this->orderstatus_id ) ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- numeric strings from the database.
	$ed::notice( wp_kses_post( $ec_receipt_lang->get_text( 'ec_errors', 'delayed_payment_failed' ) ), 'danger' );
} elseif ( ! $this->is_approved && 16 == $this->orderstatus_id ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- numeric strings from the database.
	$ed::notice( wp_kses_post( $ec_receipt_lang->get_text( 'ec_errors', 'order_refunded' ) ), 'danger' );
} elseif ( ! $this->is_approved ) {
	$ed::notice( wp_kses_post( $ec_receipt_lang->get_text( 'ec_errors', 'payment_processing' ) ), 'warning' );
} elseif ( 15 == $this->orderstatus_id ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- numeric strings from the database.
	$ed::notice( wp_kses_post( $ec_receipt_lang->get_text( 'ec_success', 'payment_received' ) ), 'success' );
}

/* Pre-order / restaurant pickup banners */
if ( ! empty( $this->includes_preorder_items ) ) {
	$ec_pickup_open  = date( apply_filters( 'wp_easycart_pickup_date_placeholder_format', 'F d, Y g:i A' ), strtotime( $this->pickup_date ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date -- unchanged pickup formatting.
	$ec_pickup_close = date( apply_filters( 'wp_easycart_pickup_time_close_placeholder_format', 'g:i A' ), strtotime( $this->pickup_date . ' +1 hour' ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date -- unchanged pickup formatting.
	$ed::notice( esc_html( str_replace( '[pickup_date]', $ec_pickup_open . ' - ' . $ec_pickup_close, $ec_receipt_lang->get_text( 'ec_errors', 'preorder_message' ) ) ), 'info' );
}
if ( ! empty( $this->includes_restaurant_type ) ) {
	$ec_pickup_time = date( apply_filters( 'wp_easycart_pickup_time_placeholder_format', 'g:i A F d, Y' ), strtotime( $this->pickup_time ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date -- unchanged pickup formatting.
	$ed::notice( esc_html( str_replace( '[pickup_time]', $ec_pickup_time, $ec_receipt_lang->get_text( 'ec_errors', 'restaurant_message' ) ) ), 'info' );
}

/* Hook output here is table rows ( "<tr><td>…</td></tr>" ), as before. */
do_action( 'wp_easycart_email_receipt_top', $this->order_id, $is_admin );

/* Greeting, order number, intro lines */
$ed::section_start();
$ed::heading( wp_kses_post( $ec_receipt_lang->get_text( 'cart_success', 'cart_payment_complete_line_1' ) ) . ' ' . esc_html( trim( $this->billing_first_name . ' ' . $this->billing_last_name ) ) . ',' );
$ed::paragraph( wp_kses_post( $ec_receipt_lang->get_text( 'cart_success', 'cart_payment_complete_line_2' ) ) . ' <strong style="color:#111827;white-space:nowrap;">' . esc_html( $this->order_id ) . ' &#8213; ' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $this->order_date ) ) ) . '</strong>' );
$ed::paragraph( wp_kses_post( $ec_receipt_lang->get_text( 'cart_success', 'cart_payment_complete_line_3' ) ) );
$ed::paragraph( wp_kses_post( $ec_receipt_lang->get_text( 'cart_success', 'cart_payment_complete_line_4' ) ) );
do_action( 'wp_easycart_order_email_receipt_after_success_lines', $this );

if ( method_exists( $this, 'has_downloads' ) && $this->has_downloads() ) {
	$ec_receipt_downloads_text = ( $this->is_approved ) ? $ec_receipt_lang->get_text( 'cart_success', 'cart_downloads_available' ) : $ec_receipt_lang->get_text( 'cart_success', 'cart_downloads_unavailable' );
	$ed::paragraph( wp_kses_post( $ec_receipt_downloads_text ) . ' <a href="' . esc_url( $ec_receipt_orders_url ) . '" target="_blank" style="' . esc_attr( $ed::css( 'link' ) ) . '">' . esc_html( wp_strip_all_tags( $ec_receipt_lang->get_text( 'cart_success', 'cart_downloads_click_to_go' ) ) ) . '</a>' );
}

if ( apply_filters( 'wpeasycart_email_receipt_enable_view_order_button', false ) ) {
	$ed::button( $ec_receipt_view_url, wp_strip_all_tags( $ec_receipt_lang->get_text( 'cart_success', 'cart_payment_view_order' ) ), array( 'margin' => '0 0 16px 0' ) );
} else {
	$ed::paragraph( '<a href="' . esc_url( $ec_receipt_view_url ) . '" target="_blank" style="' . esc_attr( $ed::css( 'link' ) ) . '">' . wp_kses_post( $ec_receipt_lang->get_text( 'cart_success', 'cart_payment_complete_click_here' ) ) . '</a> ' . wp_kses_post( $ec_receipt_lang->get_text( 'cart_success', 'cart_payment_complete_to_view_order' ) ) );
}
if ( ! empty( $is_admin ) && $ec_receipt_order_id > 0 ) {
	$ed::button(
		admin_url( 'admin.php?page=wp-easycart-orders&subpage=orders&ec_admin_form_action=edit&order_id=' . (int) $ec_receipt_order_id ),
		__( 'View order in admin', 'wp-easycart' ),
		array(
			'variant' => 'secondary',
			'margin'  => '0 0 16px 0',
			'arrow'   => true,
		)
	);
}
$ed::section_end();

/* Shipping method, coupon, contact email( s ) */
$ec_receipt_emails = array();
if ( get_option( 'ec_option_show_email_on_receipt' ) ) {
	if ( '' !== (string) $this->user_email ) {
		$ec_receipt_emails[] = esc_html( $this->user_email );
	}
	if ( isset( $this->email_other ) && '' !== (string) $this->email_other ) {
		$ec_receipt_emails[] = esc_html( $this->email_other );
	}
}
if ( '' !== trim( (string) $ec_receipt_shipping_method ) || '' !== (string) $ec_receipt_promo || $ec_receipt_emails ) {
	$ed::section_start( array( 'top' => 0 ) );
	$ed::card_start( array( 'padding' => '12px 16px' ) );
	$ed::key_values(
		array(
			array(
				'label' => wp_kses_post( $ec_receipt_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_shipping' ) ),
				'value' => esc_html( trim( (string) $ec_receipt_shipping_method ) ),
			),
			array(
				'label' => wp_kses_post( $ec_receipt_lang->get_text( 'cart_coupons', 'cart_coupon_title' ) ),
				'value' => ( '' !== (string) $ec_receipt_promo ) ? esc_html( $ec_receipt_promo ) : '',
			),
		)
	);
	if ( $ec_receipt_emails ) {
		$ed::paragraph( implode( '<br />', $ec_receipt_emails ), array( 'tone' => 'strong', 'margin' => '4px 0 0 0', 'nolink' => true ) );
	}
	$ed::card_end();
	$ed::section_end();
}

/* Product order-complete email notes */
if ( method_exists( $this, 'display_order_customer_email_notes' ) ) {
	ob_start();
	$this->display_order_customer_email_notes();
	$ec_receipt_product_notes = trim( (string) ob_get_clean() );
	if ( '' !== $ec_receipt_product_notes ) {
		$ed::block( '<div style="' . esc_attr( $ed::css( 'text' ) ) . '">' . $ec_receipt_product_notes . '</div>', array( 'top' => 16 ) );
	}
}

/* Hook output here is table rows, as before. */
do_action( 'wp_easycart_email_receipt_pre_items', $this->order_id, $is_admin );

/* Addresses */
$ed::address_cards(
	array(
		array(
			'label'   => wp_kses_post( $ec_receipt_lang->get_text( 'cart_success', 'cart_payment_complete_billing_label' ) ),
			'address' => ( $has_billing ) ? $ed::address( $this, 'billing' ) : array( 'has' => false ),
		),
		array(
			'label'   => wp_kses_post( $ec_receipt_lang->get_text( 'cart_success', 'cart_payment_complete_shipping_label' ) ),
			'address' => ( $has_shipping ) ? $ed::address( $this, 'shipping' ) : array( 'has' => false ),
		),
	),
	( $has_billing && isset( $this->vat_registration_number ) && '' !== (string) $this->vat_registration_number ) ? '<strong>' . wp_kses_post( $ec_receipt_lang->get_text( 'cart_billing_information', 'cart_billing_information_vat_registration_number' ) ) . ':</strong> ' . esc_html( $this->vat_registration_number ) : ''
);

/* Items */
$ed::items_start(
	array(
		'product' => wp_kses_post( $ec_receipt_lang->get_text( 'cart_success', 'cart_payment_complete_details_header_1' ) ),
		'qty'     => wp_kses_post( $ec_receipt_lang->get_text( 'cart_success', 'cart_payment_complete_details_header_2' ) ),
		'unit'    => wp_kses_post( $ec_receipt_lang->get_text( 'cart_success', 'cart_payment_complete_details_header_3' ) ),
		'total'   => wp_kses_post( $ec_receipt_lang->get_text( 'cart_success', 'cart_payment_complete_details_header_4' ) ),
	)
);
$ec_receipt_badge_start = 'display:inline-block;margin-' . $ec_receipt_ctx['start'] . ':6px;padding:1px 8px;font-size:11px;font-weight:bold;border-radius:3px;text-transform:uppercase;vertical-align:middle;';
foreach ( $ec_receipt_items as $ec_receipt_item ) {
	$unit_price  = $ec_receipt_currency->get_currency_display( $ec_receipt_item->unit_price );
	$total_price = $ec_receipt_currency->get_currency_display( $ec_receipt_item->total_price );

	/* Title with offers v2 markers ( order-wide flag map first, then flags on the line itself ). */
	$ec_receipt_title    = wp_easycart_escape_html( $ec_receipt_lang->convert_text( $ec_receipt_item->title ) );
	$wpec_line_flags     = ( isset( $ec_receipt_item->orderdetail_id ) && isset( $wpec_offer_line_flags[ (int) $ec_receipt_item->orderdetail_id ] ) ) ? $wpec_offer_line_flags[ (int) $ec_receipt_item->orderdetail_id ] : false;
	$ec_receipt_is_gift  = ( $wpec_line_flags && ! empty( $wpec_line_flags->is_free_gift ) ) || ! empty( $ec_receipt_item->is_free_gift );
	$ec_receipt_is_child = ( $wpec_line_flags && '' !== (string) $wpec_line_flags->bundle_group_key && $wpec_line_flags->bundle_product_id != $wpec_line_flags->product_id ) // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- numeric strings from the database.
		|| ( isset( $ec_receipt_item->bundle_group_key ) && '' !== (string) $ec_receipt_item->bundle_group_key && isset( $ec_receipt_item->bundle_product_id ) && $ec_receipt_item->bundle_product_id != $ec_receipt_item->product_id ); // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- numeric strings from the database.
	if ( $ec_receipt_is_gift && function_exists( 'wp_easycart_offers_text' ) ) {
		$ec_receipt_title .= ' <span style="' . esc_attr( $ec_receipt_badge_start ) . 'background:#fce7f0;color:#c2185b;">' . wp_kses_post( wp_easycart_offers_text( 'cart_offers', 'gift_line_label' ) ) . '</span>';
	}
	if ( $ec_receipt_is_child && function_exists( 'wp_easycart_offers_text' ) ) {
		$ec_receipt_title .= ' <span style="' . esc_attr( $ec_receipt_badge_start ) . 'background:#eef1f4;color:#4a5560;">' . wp_kses_post( wp_easycart_offers_text( 'cart_offers', 'bundle_line_label' ) ) . '</span>';
	}
	if ( $wpec_line_flags && isset( $wpec_line_flags->applied_offers ) && '' !== (string) $wpec_line_flags->applied_offers ) {
		$wpec_line_offer_rows = json_decode( (string) $wpec_line_flags->applied_offers, true );
		if ( is_array( $wpec_line_offer_rows ) && count( $wpec_line_offer_rows ) > 0 ) {
			$ec_receipt_title .= '<div style="margin-top:4px;">';
			foreach ( $wpec_line_offer_rows as $wpec_line_offer_row ) {
				if ( ! isset( $wpec_line_offer_row['label'] ) || ! isset( $wpec_line_offer_row['amount'] ) || (float) $wpec_line_offer_row['amount'] <= 0 ) {
					continue;
				}
				$ec_receipt_title .= '<span style="display:inline-block;margin:2px 4px 0 0;padding:1px 8px;background:#e7f5ec;color:#1f7a3d;font-size:11px;font-weight:normal;border-radius:3px;">' . esc_html( $wpec_line_offer_row['label'] ) . ' &minus;' . esc_html( $ec_receipt_currency->get_currency_display( (float) $wpec_line_offer_row['amount'] ) ) . '</span>';
			}
			$ec_receipt_title .= '</div>';
		}
	}

	/* Image ( option item image first, then the product image and the store fallbacks; https kept ). */
	$ec_receipt_img_url = '';
	if ( get_option( 'ec_option_show_image_on_receipt' ) ) {
		$ec_receipt_img_option = isset( $ec_receipt_item->image1_optionitem ) ? (string) $ec_receipt_item->image1_optionitem : '';
		if ( empty( $ec_receipt_item->is_deconetwork ) && ( 'http://' === substr( $ec_receipt_img_option, 0, 7 ) || 'https://' === substr( $ec_receipt_img_option, 0, 8 ) ) ) {
			$ec_receipt_img_url = $ec_receipt_img_option;
		} elseif ( empty( $ec_receipt_item->is_deconetwork ) && '' !== $ec_receipt_img_option && file_exists( EC_PLUGIN_DATA_DIRECTORY . '/products/pics1/' . $ec_receipt_img_option ) && ! is_dir( EC_PLUGIN_DATA_DIRECTORY . '/products/pics1/' . $ec_receipt_img_option ) ) {
			$ec_receipt_img_url = plugins_url( 'wp-easycart-data/products/pics1/' . $ec_receipt_img_option, EC_PLUGIN_DATA_DIRECTORY );
		} else {
			$ec_receipt_img_url = $ed::product_image_url( isset( $ec_receipt_item->image1 ) ? $ec_receipt_item->image1 : '', ! empty( $ec_receipt_item->is_deconetwork ), isset( $ec_receipt_item->deconetwork_image_link ) ? $ec_receipt_item->deconetwork_image_link : '' );
		}
	}

	$ed::item_start(
		array(
			'image_url'   => $ec_receipt_img_url,
			'image_alt'   => $ec_receipt_lang->convert_text( $ec_receipt_item->title ),
			'image_width' => $ec_receipt_img_width,
			'title_html'  => $ec_receipt_title,
		)
	);
	$ec_receipt_model = isset( $ec_receipt_item->orderdetails_model_number ) ? (string) $ec_receipt_item->orderdetails_model_number : (string) $ec_receipt_item->model_number;
	if ( '' !== $ec_receipt_model ) {
		$ed::detail( esc_html( $ec_receipt_model ), array( 'nolink' => true, 'style' => 'padding:0 0 4px 0;' ) );
	}
	if ( ! empty( $ec_receipt_item->gift_card_message ) ) {
		$ed::detail( wp_kses_post( $ec_receipt_lang->get_text( 'account_order_details', 'account_orders_details_gift_message' ) ) . esc_html( $ec_receipt_item->gift_card_message ) );
	}
	if ( ! empty( $ec_receipt_item->gift_card_from_name ) ) {
		$ed::detail( wp_kses_post( $ec_receipt_lang->get_text( 'account_order_details', 'account_orders_details_gift_from' ) ) . esc_html( $ec_receipt_item->gift_card_from_name ) );
	}
	if ( ! empty( $ec_receipt_item->gift_card_to_name ) ) {
		$ed::detail( wp_kses_post( $ec_receipt_lang->get_text( 'account_order_details', 'account_orders_details_gift_to' ) ) . esc_html( $ec_receipt_item->gift_card_to_name ) );
	}

	/* Hook output here is detail rows ( "<tr><td>…</td></tr>" ), as before. */
	do_action( 'wpeasycart_email_receipt_line_item', $ec_receipt_item->model_number, $ec_receipt_item->orderdetail_id );
	$advanced_option_allow_download = true;

	/* Basic options */
	if ( empty( $ec_receipt_item->use_advanced_optionset ) || ! empty( $ec_receipt_item->use_both_option_types ) ) {
		for ( $ec_receipt_n = 1; $ec_receipt_n <= 5; $ec_receipt_n++ ) {
			$ec_receipt_opt_name  = isset( $ec_receipt_item->{'optionitem' . $ec_receipt_n . '_name'} ) ? (string) $ec_receipt_item->{'optionitem' . $ec_receipt_n . '_name'} : '';
			$ec_receipt_opt_price = isset( $ec_receipt_item->{'optionitem' . $ec_receipt_n . '_price'} ) ? (float) $ec_receipt_item->{'optionitem' . $ec_receipt_n . '_price'} : 0;
			if ( '' === $ec_receipt_opt_name ) {
				continue;
			}
			$ec_receipt_opt_price_text = '';
			if ( $ec_receipt_opt_price < 0 ) {
				$ec_receipt_opt_price_text = ' (' . $ec_receipt_currency->get_currency_display( $ec_receipt_opt_price ) . ')';
			} elseif ( $ec_receipt_opt_price > 0 ) {
				$ec_receipt_opt_price_text = ' (+' . $ec_receipt_currency->get_currency_display( $ec_receipt_opt_price ) . ')';
			}
			$ed::detail( esc_html( $ec_receipt_opt_name . $ec_receipt_opt_price_text ) );
		}
	}

	/* Advanced options ( customer uploads: admin copy links the gated download, or says the file was not received ) */
	if ( ( ! empty( $ec_receipt_item->use_advanced_optionset ) || ! empty( $ec_receipt_item->use_both_option_types ) ) && isset( $this->mysqli ) && is_object( $this->mysqli ) ) {
		$advanced_options = $this->mysqli->get_order_options( $ec_receipt_item->orderdetail_id );
		if ( $advanced_options && count( $advanced_options ) > 0 ) {
			foreach ( $advanced_options as $advanced_option ) {
				if ( isset( $advanced_option->optionitem_allow_download ) && ! $advanced_option->optionitem_allow_download ) {
					$advanced_option_allow_download = false;
				}
				if ( 'file' === $advanced_option->option_type ) {
					$file_split     = explode( '/', (string) $advanced_option->option_value );
					$ec_upload_name = ( count( $file_split ) > 1 ) ? $file_split[ count( $file_split ) - 1 ] : $advanced_option->option_value;
					if ( ! empty( $is_admin ) && class_exists( 'wp_easycart_admin_order_uploads' ) && ! wp_easycart_admin_order_uploads::file_available( $advanced_option->option_value ) ) {
						/* 6.0.0: the upload was not kept; say so rather than link to a missing file. */
						$ec_receipt_adv_value_html = esc_html( $ec_upload_name ) . ' <em>(' . esc_html__( 'file not received', 'wp-easycart' ) . ')</em>';
					} elseif ( ! empty( $is_admin ) && class_exists( 'wp_easycart_admin_order_uploads' ) ) {
						/* Admin copy: link the filename to the gated download ( login + order capability required; durable token so the link outlives a nonce ). */
						$ec_receipt_adv_value_html = '<a href="' . esc_url( wp_easycart_admin_order_uploads::url( $ec_receipt_item->orderdetail_id, $advanced_option->option_value, true ) ) . '" style="' . esc_attr( $ed::css( 'link' ) ) . '">' . esc_html( $ec_upload_name ) . '</a>';
					} else {
						$ec_receipt_adv_value_html = esc_html( $ec_upload_name );
					}
				} elseif ( 'grid' === $advanced_option->option_type ) {
					$ec_receipt_adv_value_html = wp_easycart_escape_html( $advanced_option->optionitem_name . ' (' . $advanced_option->option_value . ')' );
				} else {
					$ec_receipt_adv_value_html = esc_html( $advanced_option->option_value );
				}

				$ec_receipt_adv_price = '';
				if ( ! empty( $advanced_option->optionitem_enable_custom_price_label ) && ( ( isset( $advanced_option->optionitem_price ) && 0 != $advanced_option->optionitem_price ) || ( isset( $advanced_option->optionitem_price_onetime ) && 0 != $advanced_option->optionitem_price_onetime ) ) ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- numeric strings from the database.
					$ec_receipt_adv_price = ' ' . $advanced_option->optionitem_custom_price_label;
				} elseif ( isset( $advanced_option->optionitem_price ) && $advanced_option->optionitem_price > 0 ) {
					$ec_receipt_adv_price = ' (+' . $ec_receipt_currency->get_currency_display( $advanced_option->optionitem_price ) . ' ' . wp_strip_all_tags( $ec_receipt_lang->get_text( 'cart', 'cart_item_adjustment' ) ) . ')';
				} elseif ( isset( $advanced_option->optionitem_price ) && $advanced_option->optionitem_price < 0 ) {
					$ec_receipt_adv_price = ' (' . $ec_receipt_currency->get_currency_display( $advanced_option->optionitem_price ) . ' ' . wp_strip_all_tags( $ec_receipt_lang->get_text( 'cart', 'cart_item_adjustment' ) ) . ')';
				} elseif ( isset( $advanced_option->optionitem_price_onetime ) && $advanced_option->optionitem_price_onetime > 0 ) {
					$ec_receipt_adv_price = ' (+' . $ec_receipt_currency->get_currency_display( $advanced_option->optionitem_price_onetime ) . ' ' . wp_strip_all_tags( $ec_receipt_lang->get_text( 'cart', 'cart_order_adjustment' ) ) . ')';
				} elseif ( isset( $advanced_option->optionitem_price_onetime ) && $advanced_option->optionitem_price_onetime < 0 ) {
					$ec_receipt_adv_price = ' (' . $ec_receipt_currency->get_currency_display( $advanced_option->optionitem_price_onetime ) . ' ' . wp_strip_all_tags( $ec_receipt_lang->get_text( 'cart', 'cart_order_adjustment' ) ) . ')';
				} elseif ( isset( $advanced_option->optionitem_price_override ) && $advanced_option->optionitem_price_override > -1 ) {
					$ec_receipt_adv_price = ' (' . wp_strip_all_tags( $ec_receipt_lang->get_text( 'cart', 'cart_item_new_price_option' ) ) . ' ' . $ec_receipt_currency->get_currency_display( $advanced_option->optionitem_price_override ) . ')';
				}
				$ed::option_detail( wp_easycart_escape_html( $advanced_option->option_label ), $ec_receipt_adv_value_html . esc_html( $ec_receipt_adv_price ) );
			}
		}
	}

	/* Gift card print link / download link */
	$ec_receipt_show_link = ! empty( $ec_receipt_item->is_giftcard ) || ( ! empty( $ec_receipt_item->is_download ) && $advanced_option_allow_download );
	if ( $ec_receipt_show_link && ! empty( $ec_receipt_item->is_giftcard ) && $this->is_approved ) {
		$ed::detail( '<a href="' . esc_url( get_site_url() . '?wpeasycarthook=print-giftcard&order_id=' . $this->order_id . '&orderdetail_id=' . $ec_receipt_item->orderdetail_id . '&giftcard_id=' . $ec_receipt_item->giftcard_id . ( ( $this->guest_key != '' ) ? '&ec_guest_key=' . $this->guest_key : '' ) ) . '" target="_blank" style="' . esc_attr( $ed::css( 'link' ) ) . '">' . wp_kses_post( $ec_receipt_lang->get_text( 'account_order_details', 'account_orders_details_print_online' ) ) . '</a>', array( 'style' => 'padding-top:4px;' ) ); // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual, WordPress.PHP.YodaConditions.NotYoda -- gift card URL built exactly as before; guest_key may be null on older orders.
	} elseif ( $ec_receipt_show_link && ! empty( $ec_receipt_item->is_download ) ) {
		$ed::detail( '<a href="' . esc_url( $ec_receipt_orders_url ) . '" target="_blank" style="' . esc_attr( $ed::css( 'link' ) ) . '">' . wp_kses_post( $ec_receipt_lang->get_text( 'account_order_details', 'account_orders_details_download' ) ) . '</a>', array( 'style' => 'padding-top:4px;' ) );
	}

	/* Codes ( approved orders only ) */
	if ( ! empty( $ec_receipt_item->include_code ) && $this->is_approved ) {
		$codes     = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ec_code WHERE ec_code.orderdetail_id = %d', $ec_receipt_item->orderdetail_id ) );
		$code_list = array();
		foreach ( (array) $codes as $ec_receipt_code ) {
			$code_list[] = $ec_receipt_code->code_val;
		}
		$ed::detail( wp_kses_post( $ec_receipt_lang->get_text( 'account_order_details', 'account_orders_details_your_codes' ) ) . ' <span dir="ltr" style="' . esc_attr( 'font-family:' . $ec_receipt_ctx['mono'] . ';font-weight:600;color:#111827;' ) . '">' . esc_html( implode( ', ', $code_list ) ) . '</span>', array( 'nolink' => true ) );
	}

	/* Hook output here is detail rows, as before. */
	do_action( 'wp_easycart_email_receipt_optionitems', $ec_receipt_item );

	$ed::item_end(
		array(
			'qty'        => $ec_receipt_item->quantity,
			'unit_html'  => $ed::ltr( apply_filters( 'wp_easycart_cart_item_unit_price_display', $unit_price, $ec_receipt_item->product_id ) ),
			'total_html' => $ed::ltr( $total_price ),
		)
	);
}
$ed::items_end();

/* Totals */
$ec_receipt_totals   = array();
$ec_receipt_totals[] = array( wp_kses_post( $ec_receipt_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_subtotal' ) ), $ed::ltr( $subtotal ) );
if ( $this->tip_total > 0 ) {
	$ec_receipt_totals[] = array( wp_kses_post( $ec_receipt_lang->get_text( 'cart_totals', 'cart_totals_tip' ) ), $ed::ltr( $tip ) );
}
$ec_receipt_has_tax_struct = isset( $tax_struct ) && is_object( $tax_struct );
if ( ( $ec_receipt_has_tax_struct && $tax_struct->is_tax_enabled() && ! get_option( 'ec_option_enable_easy_canada_tax' ) ) || ( get_option( 'ec_option_enable_easy_canada_tax' ) && $this->tax_total > 0 ) ) {
	$ec_receipt_totals[] = array( wp_kses_post( $ec_receipt_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_tax' ) ), $ed::ltr( $tax ) );
}
if ( $has_shipping ) {
	$ec_receipt_totals[] = array( wp_kses_post( $ec_receipt_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_shipping' ) ), $ed::ltr( $shipping ) );
}

/* Offers v2: itemized applied-offer rows from the persisted order summary ( reads order data, so it renders whatever the PRO state ). */
$ec_receipt_offers = ( count( $wpec_offer_order_summary ) > 0 ) ? array( 'applied_offers' => $wpec_offer_order_summary ) : ( ( isset( $this->applied_offers ) && '' !== (string) $this->applied_offers ) ? json_decode( (string) $this->applied_offers, true ) : false );
if ( is_array( $ec_receipt_offers ) && isset( $ec_receipt_offers['applied_offers'] ) && is_array( $ec_receipt_offers['applied_offers'] ) ) {
	foreach ( $ec_receipt_offers['applied_offers'] as $ec_receipt_offer ) {
		if ( ! isset( $ec_receipt_offer['amount'] ) || $ec_receipt_offer['amount'] <= 0 ) {
			continue;
		}
		$ec_receipt_offer_label = ( isset( $ec_receipt_offer['label'] ) ? $ec_receipt_offer['label'] : '' ) . ( ( isset( $ec_receipt_offer['code'] ) && '' !== (string) $ec_receipt_offer['code'] ) ? ' (' . $ec_receipt_offer['code'] . ')' : '' );
		$ec_receipt_totals[]    = array( esc_html( $ec_receipt_offer_label ), $ed::ltr( '-' . $ec_receipt_currency->get_currency_display( $ec_receipt_offer['amount'] ) ) );
	}
}
if ( 0 != $this->discount_total ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- numeric strings from the database.
	$ec_receipt_totals[] = array( wp_kses_post( $ec_receipt_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_discount' ) ), $ed::ltr( '-' . $discount ) );
}
if ( $has_duty ) {
	$ec_receipt_totals[] = array( wp_kses_post( $ec_receipt_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_duty' ) ), $ed::ltr( $duty ) );
}
if ( $ec_receipt_has_tax_struct && $tax_struct->is_vat_enabled() ) {
	$ec_receipt_totals[] = array( wp_kses_post( $ec_receipt_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_vat' ) ) . esc_html( $vat_rate ) . '%', $ed::ltr( $vat ) );
}
foreach (
	array(
		'gst' => array( $gst, $gst_rate ),
		'pst' => array( $pst, $pst_rate ),
		'hst' => array( $hst, $hst_rate ),
	) as $ec_receipt_ca_key => $ec_receipt_ca_tax
) {
	if ( $ec_receipt_ca_tax[0] > 0 ) {
		$ec_receipt_ca_label = function_exists( 'wp_easycart_canada_tax_label' ) ? wp_easycart_canada_tax_label( $ec_receipt_ca_key, $this->shipping_state ) : strtoupper( $ec_receipt_ca_key );
		$ec_receipt_totals[] = array( esc_html( $ec_receipt_ca_label . ' (' . $ec_receipt_ca_tax[1] . '%)' ), $ed::money( $ec_receipt_ca_tax[0] ) );
	}
}
if ( isset( $this->order_fees ) && is_array( $this->order_fees ) && count( $this->order_fees ) > 0 ) {
	foreach ( $this->order_fees as $order_fee ) {
		$ec_receipt_totals[] = array( esc_html( $order_fee->fee_label ), $ed::money( $order_fee->fee_total ) );
	}
}
$ed::totals( $ec_receipt_totals, array( wp_kses_post( $ec_receipt_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_grand_total' ) ), $ed::ltr( $total ) ) );

/* Balance and refund rows ( under the order total, as before ) */
$ec_receipt_after_totals = array();
if ( 14 == $this->orderstatus_id ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- numeric strings from the database.
	$ec_receipt_after_totals[] = array(
		'label' => wp_kses_post( $ec_receipt_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_balance_left' ) ),
		'value' => $ed::ltr( $total ),
		'tone'  => 'strong',
	);
} elseif ( 15 == $this->orderstatus_id ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- numeric strings from the database.
	$ec_receipt_after_totals[] = array(
		'label' => wp_kses_post( $ec_receipt_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_balance_left' ) ),
		'value' => $ed::money( 0.00 ),
		'tone'  => 'strong',
	);
}
if ( $this->refund_total > 0 ) {
	$ec_receipt_after_totals[] = array(
		'label' => wp_kses_post( $ec_receipt_lang->get_text( 'account_order_details', 'account_orders_details_refund_total_short' ) ),
		'value' => $ed::ltr( '-' . $refund ),
		'tone'  => 'danger',
	);
}
if ( $ec_receipt_after_totals ) {
	$ed::totals( $ec_receipt_after_totals );
}

/* Order notes, closing lines */
if ( get_option( 'ec_option_user_order_notes' ) && isset( $this->order_customer_notes ) && '' !== trim( (string) $this->order_customer_notes ) ) {
	$ed::section_start();
	$ed::label( wp_kses_post( $ec_receipt_lang->get_text( 'cart_payment_information', 'cart_payment_information_order_notes_title' ) ) );
	$ed::card_start( array( 'padding' => '12px 14px' ) );
	echo nl2br( esc_html( wp_unslash( $this->order_customer_notes ) ) );
	$ed::card_end();
	$ed::section_end();
}
$ed::section_start( array( 'top' => 24, 'bottom' => 8 ) );
do_action( 'wpeasycart_email_receipt_order_notes_after', $this->order_id );
$ed::paragraph( wp_kses_post( $ec_receipt_lang->get_text( 'cart_success', 'cart_payment_complete_bottom_line_1' ) ), array( 'margin' => '0 0 12px 0' ) );
$ed::paragraph( wp_kses_post( $ec_receipt_lang->get_text( 'cart_success', 'cart_payment_complete_bottom_line_2' ) ), array( 'tone' => 'strong', 'margin' => '0' ) );
$ed::section_end();
$ed::close();
