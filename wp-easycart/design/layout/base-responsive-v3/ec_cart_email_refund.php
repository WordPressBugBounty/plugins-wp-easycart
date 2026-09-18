<?php
/**
 * Refund issued email ( customer copy and store copy ).
 *
 * Included by ec_orderdisplay::send_refund_email() twice ( $is_admin false, then true ), running as $this ( ec_orderdisplay,
 * built with order details ). Variables from the sender: $is_admin, $tax_struct ( ec_tax ), $total, $subtotal, $tip, $tax,
 * $has_duty, $duty, $vat, $shipping, $vat_rate, $gst, $gst_rate, $pst, $pst_rate, $hst, $hst_rate, $discount, $refund,
 * $email_logo_url, $store_page, $permalink_divider.
 *
 * On the shared email design since 6.0.0 ( wp_easycart_email_design, inc/classes/core/class-wp-easycart-email-design.php ):
 * the refunded amount leads the email as a banner and is repeated in the totals, addresses are country-aware cards,
 * the store copy carries a "Store notification" label and a link to the order in the admin. Copy this file to your
 * theme / wp-easycart-data layout folder to customise it; the file name must stay the same.
 *
 * Hooks kept: wp_easycart_email_receipt_top, wp_easycart_order_email_receipt_after_success_lines,
 *   wp_easycart_email_receipt_pre_items, wpeasycart_email_receipt_line_item, wp_easycart_email_receipt_optionitems,
 *   wp_easycart_cart_item_unit_price_display, wpeasycart_email_receipt_order_notes_after,
 *   wp_easycart_pickup_date_placeholder_format, wp_easycart_pickup_time_close_placeholder_format,
 *   wp_easycart_pickup_time_placeholder_format, wp_easycart_account_page_id, wp_easycart_email_receipt_image_width.
 *
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'wp_easycart_email_design' ) ) {
	require_once EC_PLUGIN_DIRECTORY . '/inc/classes/core/class-wp-easycart-email-design.php';
}

$ed              = 'wp_easycart_email_design';
$is_admin        = ! empty( $is_admin );
$ec_refund_lang  = wp_easycart_language();
$ec_refund_cur   = $GLOBALS['currency'];
$ec_refund_items = ( isset( $this->cart->cart ) && is_array( $this->cart->cart ) ) ? $this->cart->cart : array();
$ec_refund_order = (int) $this->order_id;
global $wpdb;

$has_shipping = false;
$has_billing  = false;
if ( get_option( 'ec_option_use_shipping' ) ) {
	foreach ( $ec_refund_items as $cart_item ) {
		if ( ! empty( $cart_item->is_shippable ) ) {
			$has_shipping = true;
		}
	}
}
$ec_refund_billing = $ed::address( $this, 'billing' );
$has_billing       = $ec_refund_billing['has'];
$ec_refund_ship    = $has_shipping ? $ed::address( $this, 'shipping' ) : array(
	'lines' => array(),
	'phone' => '',
	'has'   => false,
);

/* Offers v2: line-level flags keyed by orderdetail_id and the order-level applied-offers snapshot. */
$ec_refund_offer_flags   = array();
$ec_refund_offer_summary = array();
if ( function_exists( 'wp_easycart_offers_active' ) && wp_easycart_offers_active() && $ec_refund_order > 0 ) {
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT orderdetail_id, product_id, is_free_gift, bundle_group_key, bundle_product_id, applied_offers FROM ec_orderdetail WHERE order_id = %d', $ec_refund_order ) ) as $ec_refund_flag_row ) {
		$ec_refund_offer_flags[ (int) $ec_refund_flag_row->orderdetail_id ] = $ec_refund_flag_row;
	}
	$ec_refund_offer_json    = $wpdb->get_var( $wpdb->prepare( 'SELECT applied_offers FROM ec_order WHERE order_id = %d', $ec_refund_order ) );
	$ec_refund_offer_decoded = ( $ec_refund_offer_json ) ? json_decode( (string) $ec_refund_offer_json, true ) : false;
	if ( is_array( $ec_refund_offer_decoded ) && isset( $ec_refund_offer_decoded['applied_offers'] ) && is_array( $ec_refund_offer_decoded['applied_offers'] ) ) {
		$ec_refund_offer_summary = $ec_refund_offer_decoded['applied_offers'];
	}
}
/* The persisted order snapshot renders regardless of PRO state ( same as the receipt ). */
if ( ! $ec_refund_offer_summary && isset( $this->applied_offers ) && '' !== (string) $this->applied_offers ) {
	$ec_refund_offer_decoded = json_decode( (string) $this->applied_offers, true );
	if ( is_array( $ec_refund_offer_decoded ) && isset( $ec_refund_offer_decoded['applied_offers'] ) && is_array( $ec_refund_offer_decoded['applied_offers'] ) ) {
		$ec_refund_offer_summary = $ec_refund_offer_decoded['applied_offers'];
	}
}

$ec_refund_message = str_replace( '[amount]', '<strong>' . $ed::ltr( $refund ) . '</strong>', wp_kses_post( $ec_refund_lang->get_text( 'cart_success', 'cart_refund_email_message' ) ) );
$ec_refund_img_w   = (int) apply_filters( 'wp_easycart_email_receipt_image_width', 70 );

$ed::open(
	array(
		'title'     => wp_strip_all_tags( $ec_refund_lang->get_text( 'cart_success', 'cart_refund_email_title' ) ) . ' ' . $ec_refund_order,
		'preheader' => str_replace( '[amount]', wp_strip_all_tags( (string) $refund ), wp_strip_all_tags( $ec_refund_lang->get_text( 'cart_success', 'cart_refund_email_message' ) ) ),
		'logo_url'  => (string) $email_logo_url,
		'store_url' => (string) $store_page,
		'eyebrow'   => $is_admin ? __( 'Store notification', 'wp-easycart' ) : '',
	)
);
$ec_refund_ctx   = $ed::ctx();
$ec_refund_badge = 'display:inline-block;margin-' . $ec_refund_ctx['start'] . ':6px;padding:1px 8px;font-size:11px;font-weight:bold;border-radius:3px;text-transform:uppercase;vertical-align:middle;';

/* Pickup banners */
if ( $this->includes_preorder_items ) {
	$ec_pickup_open  = date( apply_filters( 'wp_easycart_pickup_date_placeholder_format', 'F d, Y g:i A' ), strtotime( $this->pickup_date ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date -- legacy pickup format kept.
	$ec_pickup_close = date( apply_filters( 'wp_easycart_pickup_time_close_placeholder_format', 'g:i A' ), strtotime( $this->pickup_date . ' +1 hour' ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date -- legacy pickup format kept.
	$ed::notice( esc_html( str_replace( '[pickup_date]', $ec_pickup_open . ' - ' . $ec_pickup_close, $ec_refund_lang->get_text( 'ec_errors', 'preorder_message' ) ) ), 'success' );
}
if ( $this->includes_restaurant_type ) {
	$ec_pickup_time = date( apply_filters( 'wp_easycart_pickup_time_placeholder_format', 'g:i A F d, Y' ), strtotime( $this->pickup_time ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date -- legacy pickup format kept.
	$ed::notice( esc_html( str_replace( '[pickup_time]', $ec_pickup_time, $ec_refund_lang->get_text( 'ec_errors', 'restaurant_message' ) ) ), 'success' );
}

/* Hooks here print whole rows ( <tr> ) into the email container. */
do_action( 'wp_easycart_email_receipt_top', $this->order_id, $is_admin );

/* Refund banner */
$ed::notice( $ec_refund_message, 'info' );

/* Greeting and order lines */
$ed::section_start( array( 'top' => 20 ) );
$ed::heading( wp_kses_post( $ec_refund_lang->get_text( 'cart_success', 'cart_refund_email_title' ) ) );
$ed::paragraph( wp_kses_post( $ec_refund_lang->get_text( 'cart_success', 'cart_payment_complete_line_1' ) ) . ' ' . esc_html( trim( $this->billing_first_name . ' ' . $this->billing_last_name ) ) . ',' );
$ed::paragraph( wp_kses_post( $ec_refund_lang->get_text( 'cart_success', 'cart_payment_complete_line_2' ) ) . ' <strong style="color:#111827;">' . esc_html( $this->order_id ) . ' &#8213; ' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $this->order_date ) ) ) . '</strong>' );
$ed::paragraph( wp_kses_post( $ec_refund_lang->get_text( 'cart_success', 'cart_payment_complete_line_3' ) ) );
$ed::paragraph( wp_kses_post( $ec_refund_lang->get_text( 'cart_success', 'cart_payment_complete_line_4' ) ) );
do_action( 'wp_easycart_order_email_receipt_after_success_lines', $this );

if ( $this->has_downloads() ) {
	$ed::paragraph( wp_kses_post( $ec_refund_lang->get_text( 'cart_success', ( $this->is_approved ? 'cart_downloads_available' : 'cart_downloads_unavailable' ) ) ) . ' <a href="' . esc_url( wpeasycart_links()->get_account_page( 'order_details', array( 'order_id' => (int) $this->order_id ) ) ) . '" target="_blank" style="' . esc_attr( $ed::css( 'link' ) ) . '">' . esc_html( wp_strip_all_tags( $ec_refund_lang->get_text( 'cart_success', 'cart_downloads_click_to_go' ) ) ) . '</a>' );
}

if ( '' !== (string) $this->promo_code ) {
	$promo_val = $this->promo_code;
	if ( get_option( 'ec_option_show_coupon_message' ) && '' !== (string) $this->promo_code_message ) {
		$promo_val = $this->promo_code_message;
	}
	$ed::paragraph( wp_kses_post( $ec_refund_lang->get_text( 'cart_coupons', 'cart_coupon_title' ) ) . ': ' . esc_html( $promo_val ), array( 'tone' => 'strong' ) );
}

if ( $has_shipping && isset( $this->cart->shipping_subtotal ) && $this->cart->shipping_subtotal > 0 && isset( $this->shipping ) && is_object( $this->shipping ) && isset( $GLOBALS['ec_cart_data'] ) ) {
	$shipping_method = '';
	if ( 'fraktjakt' === $this->shipping->shipping_method ) {
		$shipping_method = $this->shipping->get_selected_shipping_method();
	} elseif ( '' !== (string) $GLOBALS['ec_cart_data']->cart_data->shipping_method && 'standard' !== $GLOBALS['ec_cart_data']->cart_data->shipping_method && method_exists( $this, 'get_shipping_method_name' ) ) {
		$shipping_method = $this->get_shipping_method_name( $GLOBALS['ec_cart_data']->cart_data->shipping_method );
	} elseif ( ( 'price' === $this->shipping->shipping_method || 'weight' === $this->shipping->shipping_method ) && '' !== (string) $GLOBALS['ec_cart_data']->cart_data->ship_express ) {
		$shipping_method = $ec_refund_lang->get_text( 'cart_estimate_shipping', 'cart_estimate_shipping_express' );
	} else {
		$shipping_method = $ec_refund_lang->get_text( 'cart_estimate_shipping', 'cart_estimate_shipping_standard' );
	}
	$ed::paragraph( esc_html( wp_strip_all_tags( $shipping_method ) ), array( 'tone' => 'strong' ) );
} elseif ( $has_shipping && isset( $this->shipping_method ) && '' !== (string) $this->shipping_method ) {
	$ed::paragraph( esc_html( $this->shipping_method ), array( 'tone' => 'strong' ) );
}

if ( get_option( 'ec_option_show_email_on_receipt' ) ) {
	$ed::paragraph(
		esc_html( $this->user_email ) . ( ( isset( $this->email_other ) && '' !== (string) $this->email_other ) ? '<br />' . esc_html( $this->email_other ) : '' ),
		array(
			'tone'   => 'small',
			'nolink' => true,
		)
	);
}
$this->display_order_customer_email_notes();
$ed::section_end();

/* View order ( customer ) and open in admin ( store copy ) */
$ed::section_start(
	array(
		'top'    => 4,
		'bottom' => 8,
	)
);
$ed::button(
	wpeasycart_links()->get_account_page(
		'order_details',
		array(
			'order_id'     => (int) $this->order_id,
			'ec_guest_key' => ( ( '' != $this->guest_key ) ? esc_attr( $this->guest_key ) : null ), // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- link code kept as is.
		)
	),
	wp_strip_all_tags( $ec_refund_lang->get_text( 'cart_success', 'cart_payment_complete_click_here' ) . ' ' . $ec_refund_lang->get_text( 'cart_success', 'cart_payment_complete_to_view_order' ) ),
	array( 'arrow' => true )
);
if ( $is_admin && $ec_refund_order > 0 ) {
	$ed::button(
		admin_url( 'admin.php?page=wp-easycart-orders&subpage=orders&ec_admin_form_action=edit&order_id=' . (int) $ec_refund_order ),
		__( 'Open the order in your store admin', 'wp-easycart' ),
		array(
			'variant' => 'secondary',
			'margin'  => '10px 0 0 0',
		)
	);
}
$ed::section_end();

/* Hooks here print whole rows ( <tr> ) into the email container. */
do_action( 'wp_easycart_email_receipt_pre_items', $this->order_id, $is_admin );

/* Addresses */
$ed::address_cards(
	array(
		array(
			'label'   => wp_kses_post( $ec_refund_lang->get_text( 'cart_success', 'cart_payment_complete_billing_label' ) ),
			'address' => $has_billing ? $ec_refund_billing : array( 'has' => false ),
		),
		array(
			'label'   => wp_kses_post( $ec_refund_lang->get_text( 'cart_success', 'cart_payment_complete_shipping_label' ) ),
			'address' => $ec_refund_ship,
		),
	),
	( $has_billing && '' !== (string) $this->vat_registration_number ) ? '<strong>' . wp_kses_post( $ec_refund_lang->get_text( 'cart_billing_information', 'cart_billing_information_vat_registration_number' ) ) . ':</strong> ' . esc_html( $this->vat_registration_number ) : ''
);

/* Items */
$ed::items_start(
	array(
		'product' => wp_kses_post( $ec_refund_lang->get_text( 'cart_success', 'cart_payment_complete_details_header_1' ) ),
		'qty'     => wp_kses_post( $ec_refund_lang->get_text( 'cart_success', 'cart_payment_complete_details_header_2' ) ),
		'unit'    => wp_kses_post( $ec_refund_lang->get_text( 'cart_success', 'cart_payment_complete_details_header_3' ) ),
		'total'   => wp_kses_post( $ec_refund_lang->get_text( 'cart_success', 'cart_payment_complete_details_header_4' ) ),
	)
);
foreach ( $ec_refund_items as $ec_refund_item ) {
	$unit_price           = $ec_refund_cur->get_currency_display( $ec_refund_item->unit_price );
	$total_price          = $ec_refund_cur->get_currency_display( $ec_refund_item->total_price );
	$ec_refund_line_flags = ( isset( $ec_refund_item->orderdetail_id ) && isset( $ec_refund_offer_flags[ (int) $ec_refund_item->orderdetail_id ] ) ) ? $ec_refund_offer_flags[ (int) $ec_refund_item->orderdetail_id ] : false;

	/* Title with offers v2 badges ( free gift, bundle item, per-line offer amounts ). */
	$ec_refund_title   = wp_kses_post( $ec_refund_lang->convert_text( $ec_refund_item->title ) );
	$ec_refund_is_gift = $ec_refund_line_flags ? ! empty( $ec_refund_line_flags->is_free_gift ) : ! empty( $ec_refund_item->is_free_gift );
	$ec_refund_bundle  = $ec_refund_line_flags ? $ec_refund_line_flags : $ec_refund_item;
	if ( $ec_refund_is_gift && function_exists( 'wp_easycart_offers_text' ) ) {
		$ec_refund_title .= ' <span style="' . esc_attr( $ec_refund_badge ) . 'background:#fce7f0;color:#c2185b;">' . wp_kses_post( wp_easycart_offers_text( 'cart_offers', 'gift_line_label' ) ) . '</span>';
	}
	if ( function_exists( 'wp_easycart_offers_text' ) && isset( $ec_refund_bundle->bundle_group_key ) && '' !== (string) $ec_refund_bundle->bundle_group_key && isset( $ec_refund_bundle->bundle_product_id ) && (int) $ec_refund_bundle->bundle_product_id !== (int) $ec_refund_bundle->product_id ) {
		$ec_refund_title .= ' <span style="' . esc_attr( $ec_refund_badge ) . 'background:#eef1f4;color:#4a5560;">' . wp_kses_post( wp_easycart_offers_text( 'cart_offers', 'bundle_line_label' ) ) . '</span>';
	}
	if ( $ec_refund_line_flags && isset( $ec_refund_line_flags->applied_offers ) && '' !== (string) $ec_refund_line_flags->applied_offers ) {
		$ec_refund_line_offers = json_decode( (string) $ec_refund_line_flags->applied_offers, true );
		if ( is_array( $ec_refund_line_offers ) && count( $ec_refund_line_offers ) > 0 ) {
			$ec_refund_title .= '<div style="margin-top:4px;">';
			foreach ( $ec_refund_line_offers as $ec_refund_line_offer ) {
				if ( ! isset( $ec_refund_line_offer['label'] ) || ! isset( $ec_refund_line_offer['amount'] ) || (float) $ec_refund_line_offer['amount'] <= 0 ) {
					continue;
				}
				$ec_refund_title .= '<span style="display:inline-block;margin:2px 4px 0 0;padding:1px 8px;background:#e7f5ec;color:#1f7a3d;font-size:11px;font-weight:normal;border-radius:3px;">' . esc_html( $ec_refund_line_offer['label'] ) . ' &minus;' . esc_html( $ec_refund_cur->get_currency_display( (float) $ec_refund_line_offer['amount'] ) ) . '</span>';
			}
			$ec_refund_title .= '</div>';
		}
	}

	$ec_refund_image = '';
	if ( get_option( 'ec_option_show_image_on_receipt' ) ) {
		$ec_refund_image = $ed::product_image_url(
			( isset( $ec_refund_item->image1_optionitem ) && '' !== (string) $ec_refund_item->image1_optionitem ) ? $ec_refund_item->image1_optionitem : ( isset( $ec_refund_item->image1 ) ? $ec_refund_item->image1 : '' ),
			! empty( $ec_refund_item->is_deconetwork ),
			isset( $ec_refund_item->deconetwork_image_link ) ? $ec_refund_item->deconetwork_image_link : ''
		);
	}
	$ed::item_start(
		array(
			'image_url'   => $ec_refund_image,
			'image_alt'   => $ec_refund_lang->convert_text( $ec_refund_item->title ),
			'image_width' => $ec_refund_img_w,
			'title_html'  => $ec_refund_title,
		)
	);
	if ( isset( $ec_refund_item->orderdetails_model_number ) && '' !== (string) $ec_refund_item->orderdetails_model_number ) {
		$ed::detail(
			esc_html( $ec_refund_item->orderdetails_model_number ),
			array(
				'nolink' => true,
				'style'  => 'padding:0 0 4px 0;',
			)
		);
	}
	if ( ! empty( $ec_refund_item->gift_card_message ) ) {
		$ed::detail( wp_kses_post( $ec_refund_lang->get_text( 'account_order_details', 'account_orders_details_gift_message' ) ) . esc_html( $ec_refund_item->gift_card_message ) );
	}
	if ( ! empty( $ec_refund_item->gift_card_from_name ) ) {
		$ed::detail( wp_kses_post( $ec_refund_lang->get_text( 'account_order_details', 'account_orders_details_gift_from' ) ) . esc_html( $ec_refund_item->gift_card_from_name ) );
	}
	if ( ! empty( $ec_refund_item->gift_card_to_name ) ) {
		$ed::detail( wp_kses_post( $ec_refund_lang->get_text( 'account_order_details', 'account_orders_details_gift_to' ) ) . esc_html( $ec_refund_item->gift_card_to_name ) );
	}

	do_action( 'wpeasycart_email_receipt_line_item', $ec_refund_item->model_number, $ec_refund_item->orderdetail_id );
	$advanced_option_allow_download = true;

	/* Basic options */
	if ( empty( $ec_refund_item->use_advanced_optionset ) || ! empty( $ec_refund_item->use_both_option_types ) ) {
		for ( $ec_refund_n = 1; $ec_refund_n <= 5; $ec_refund_n++ ) {
			$ec_refund_opt_name  = isset( $ec_refund_item->{'optionitem' . $ec_refund_n . '_name'} ) ? (string) $ec_refund_item->{'optionitem' . $ec_refund_n . '_name'} : '';
			$ec_refund_opt_price = isset( $ec_refund_item->{'optionitem' . $ec_refund_n . '_price'} ) ? (float) $ec_refund_item->{'optionitem' . $ec_refund_n . '_price'} : 0;
			if ( '' === $ec_refund_opt_name ) {
				continue;
			}
			$ec_refund_opt_text = '';
			if ( $ec_refund_opt_price < 0 ) {
				$ec_refund_opt_text = ' (' . $ec_refund_cur->get_currency_display( $ec_refund_opt_price ) . ')';
			} elseif ( $ec_refund_opt_price > 0 ) {
				$ec_refund_opt_text = ' (+' . $ec_refund_cur->get_currency_display( $ec_refund_opt_price ) . ')';
			}
			$ed::detail( wp_kses_post( $ec_refund_opt_name ) . esc_html( $ec_refund_opt_text ) );
		}
	}

	/* Advanced options ( customer uploads link to the gated download on the store copy ) */
	if ( ! empty( $ec_refund_item->use_advanced_optionset ) || ! empty( $ec_refund_item->use_both_option_types ) ) {
		$advanced_options = $this->mysqli->get_order_options( $ec_refund_item->orderdetail_id );
		foreach ( (array) $advanced_options as $advanced_option ) {
			if ( isset( $advanced_option->optionitem_allow_download ) && ! $advanced_option->optionitem_allow_download ) {
				$advanced_option_allow_download = false;
			}
			$ec_refund_adv_price = '';
			if ( ! empty( $advanced_option->optionitem_enable_custom_price_label ) && ( ( isset( $advanced_option->optionitem_price ) && 0 != $advanced_option->optionitem_price ) || ( isset( $advanced_option->optionitem_price_onetime ) && 0 != $advanced_option->optionitem_price_onetime ) ) ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- numeric strings from the database.
				$ec_refund_adv_price = ' ' . $advanced_option->optionitem_custom_price_label;
			} elseif ( isset( $advanced_option->optionitem_price ) && $advanced_option->optionitem_price > 0 ) {
				$ec_refund_adv_price = ' (+' . $ec_refund_cur->get_currency_display( $advanced_option->optionitem_price ) . ' ' . wp_strip_all_tags( $ec_refund_lang->get_text( 'cart', 'cart_item_adjustment' ) ) . ')';
			} elseif ( isset( $advanced_option->optionitem_price ) && $advanced_option->optionitem_price < 0 ) {
				$ec_refund_adv_price = ' (' . $ec_refund_cur->get_currency_display( $advanced_option->optionitem_price ) . ' ' . wp_strip_all_tags( $ec_refund_lang->get_text( 'cart', 'cart_item_adjustment' ) ) . ')';
			} elseif ( isset( $advanced_option->optionitem_price_onetime ) && $advanced_option->optionitem_price_onetime > 0 ) {
				$ec_refund_adv_price = ' (+' . $ec_refund_cur->get_currency_display( $advanced_option->optionitem_price_onetime ) . ' ' . wp_strip_all_tags( $ec_refund_lang->get_text( 'cart', 'cart_order_adjustment' ) ) . ')';
			} elseif ( isset( $advanced_option->optionitem_price_onetime ) && $advanced_option->optionitem_price_onetime < 0 ) {
				$ec_refund_adv_price = ' (' . $ec_refund_cur->get_currency_display( $advanced_option->optionitem_price_onetime ) . ' ' . wp_strip_all_tags( $ec_refund_lang->get_text( 'cart', 'cart_order_adjustment' ) ) . ')';
			} elseif ( isset( $advanced_option->optionitem_price_override ) && $advanced_option->optionitem_price_override > -1 ) {
				$ec_refund_adv_price = ' (' . wp_strip_all_tags( $ec_refund_lang->get_text( 'cart', 'cart_item_new_price_option' ) ) . ' ' . $ec_refund_cur->get_currency_display( $advanced_option->optionitem_price_override ) . ')';
			}

			if ( 'file' === $advanced_option->option_type ) {
				$file_split     = explode( '/', (string) $advanced_option->option_value );
				$ec_upload_name = ( count( $file_split ) > 1 ) ? $file_split[ count( $file_split ) - 1 ] : $advanced_option->option_value;
				if ( $is_admin && class_exists( 'wp_easycart_admin_order_uploads' ) && ! wp_easycart_admin_order_uploads::file_available( $advanced_option->option_value ) ) {
					/* 6.0.0: the upload was not kept; say so rather than link to a missing file. */
					$ec_refund_adv_value = esc_html( $ec_upload_name ) . ' <em>(' . esc_html__( 'file not received', 'wp-easycart' ) . ')</em>';
				} elseif ( $is_admin && class_exists( 'wp_easycart_admin_order_uploads' ) ) {
					/* Admin copy: link the filename to the gated download ( login + order capability required; durable token so the link outlives a nonce ). */
					$ec_refund_adv_value = '<a href="' . esc_url( wp_easycart_admin_order_uploads::url( $ec_refund_item->orderdetail_id, $advanced_option->option_value, true ) ) . '" style="' . esc_attr( $ed::css( 'link' ) ) . '">' . esc_html( $ec_upload_name ) . '</a>';
				} else {
					$ec_refund_adv_value = esc_html( $ec_upload_name );
				}
				$ec_refund_adv_value .= esc_html( $ec_refund_adv_price );
			} elseif ( 'grid' === $advanced_option->option_type ) {
				$ec_refund_adv_value = esc_html( $advanced_option->optionitem_name . ' (' . $advanced_option->option_value . ')' . $ec_refund_adv_price );
			} else {
				$ec_refund_adv_value = esc_html( $advanced_option->option_value . $ec_refund_adv_price );
			}
			$ed::option_detail( wp_kses_post( $advanced_option->option_label ), $ec_refund_adv_value );
		}
	}

	/* Gift card print link / download link */
	if ( ! empty( $ec_refund_item->is_giftcard ) || ( ! empty( $ec_refund_item->is_download ) && $advanced_option_allow_download ) ) {
		$account_page_id = apply_filters( 'wp_easycart_account_page_id', get_option( 'ec_option_accountpage' ) );
		$account_page    = get_permalink( $account_page_id );
		if ( substr_count( $account_page, '?' ) ) {
			$permalink_divider = '&';
		} else {
			$permalink_divider = '?';
		}
		if ( ! empty( $ec_refund_item->is_giftcard ) && $this->is_approved ) {
			$ed::detail( '<a href="' . esc_url( get_site_url() . '?wpeasycarthook=print-giftcard&order_id=' . $this->order_id . '&orderdetail_id=' . $ec_refund_item->orderdetail_id . '&giftcard_id=' . $ec_refund_item->giftcard_id . ( ( '' != $this->guest_key ) ? '&ec_guest_key=' . $this->guest_key : '' ) ) . '" target="_blank" style="' . esc_attr( $ed::css( 'link' ) ) . '">' . wp_kses_post( $ec_refund_lang->get_text( 'account_order_details', 'account_orders_details_print_online' ) ) . '</a>', array( 'style' => 'padding-top:4px;' ) ); // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- link code kept as is.
		} elseif ( ! empty( $ec_refund_item->is_download ) ) {
			$ed::detail( '<a href="' . esc_url( wpeasycart_links()->get_account_page( 'order_details', array( 'order_id' => (int) $this->order_id ) ) ) . '" target="_blank" style="' . esc_attr( $ed::css( 'link' ) ) . '">' . wp_kses_post( $ec_refund_lang->get_text( 'account_order_details', 'account_orders_details_download' ) ) . '</a>', array( 'style' => 'padding-top:4px;' ) );
		}
	}

	/* Codes ( approved orders only ) */
	if ( ! empty( $ec_refund_item->include_code ) && $this->is_approved ) {
		$codes     = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ec_code WHERE ec_code.orderdetail_id = %d', $ec_refund_item->orderdetail_id ) );
		$code_list = array();
		foreach ( (array) $codes as $ec_refund_code ) {
			$code_list[] = $ec_refund_code->code_val;
		}
		$code_list = implode( ', ', $code_list );
		$ed::detail( wp_kses_post( $ec_refund_lang->get_text( 'account_order_details', 'account_orders_details_your_codes' ) ) . ' ' . esc_html( $code_list ) );
	}

	do_action( 'wp_easycart_email_receipt_optionitems', $ec_refund_item );

	$ed::item_end(
		array(
			'qty'        => $ec_refund_item->quantity,
			'unit_html'  => $ed::ltr( wp_strip_all_tags( (string) apply_filters( 'wp_easycart_cart_item_unit_price_display', $unit_price, $ec_refund_item->product_id ) ) ),
			'total_html' => $ed::ltr( $total_price ),
		)
	);
}
$ed::items_end();

/* Totals */
$ec_refund_canada   = get_option( 'ec_option_enable_easy_canada_tax' );
$ec_refund_totals   = array();
$ec_refund_totals[] = array( wp_kses_post( $ec_refund_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_subtotal' ) ), $ed::ltr( $subtotal ) );
if ( $this->tip_total > 0 ) {
	$ec_refund_totals[] = array( wp_kses_post( $ec_refund_lang->get_text( 'cart_totals', 'cart_totals_tip' ) ), $ed::ltr( $tip ) );
}
if ( ( is_object( $tax_struct ) && $tax_struct->is_tax_enabled() && ! $ec_refund_canada ) || ( $ec_refund_canada && $this->tax_total > 0 ) ) {
	$ec_refund_totals[] = array( wp_kses_post( $ec_refund_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_tax' ) ), $ed::ltr( $tax ) );
}
if ( $has_shipping ) {
	$ec_refund_totals[] = array( wp_kses_post( $ec_refund_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_shipping' ) ), $ed::ltr( $shipping ) );
}
foreach ( $ec_refund_offer_summary as $ec_refund_offer_row ) {
	if ( ! isset( $ec_refund_offer_row['label'] ) || ! isset( $ec_refund_offer_row['amount'] ) || (float) $ec_refund_offer_row['amount'] <= 0 ) {
		continue;
	}
	$ec_refund_totals[] = array( esc_html( $ec_refund_offer_row['label'] . ( ( isset( $ec_refund_offer_row['code'] ) && '' !== (string) $ec_refund_offer_row['code'] ) ? ' (' . $ec_refund_offer_row['code'] . ')' : '' ) ), $ed::ltr( '-' . $ec_refund_cur->get_currency_display( (float) $ec_refund_offer_row['amount'] ) ) );
}
if ( 0 != $this->discount_total ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- numeric string from the database.
	$ec_refund_totals[] = array( wp_kses_post( $ec_refund_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_discount' ) ), $ed::ltr( '-' . $discount ) );
}
if ( $has_duty ) {
	$ec_refund_totals[] = array( wp_kses_post( $ec_refund_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_duty' ) ), $ed::ltr( $duty ) );
}
if ( is_object( $tax_struct ) && $tax_struct->is_vat_enabled() ) {
	$ec_refund_totals[] = array( wp_kses_post( $ec_refund_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_vat' ) ) . esc_html( $vat_rate ) . '%', $ed::ltr( $vat ) );
}
foreach ( array(
	'gst' => array( $gst, $gst_rate ),
	'pst' => array( $pst, $pst_rate ),
	'hst' => array( $hst, $hst_rate ),
) as $ec_refund_ca_key => $ec_refund_ca ) {
	if ( $ec_refund_ca[0] > 0 ) {
		$ec_refund_ca_label = function_exists( 'wp_easycart_canada_tax_label' ) ? wp_easycart_canada_tax_label( $ec_refund_ca_key, $this->shipping_state ) : strtoupper( $ec_refund_ca_key );
		$ec_refund_totals[] = array( esc_html( $ec_refund_ca_label . ' (' . $ec_refund_ca[1] . '%)' ), $ed::money( $ec_refund_ca[0] ) );
	}
}
if ( isset( $this->order_fees ) && is_array( $this->order_fees ) ) {
	foreach ( $this->order_fees as $order_fee ) {
		$ec_refund_totals[] = array( esc_html( $order_fee->fee_label ), $ed::money( $order_fee->fee_total ) );
	}
}
$ed::totals( $ec_refund_totals, array( wp_kses_post( $ec_refund_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_grand_total' ) ), $ed::ltr( $total ) ) );

/* Balance and refund */
$ec_refund_after = array();
if ( 14 == $this->orderstatus_id ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- numeric string from the database.
	$ec_refund_after[] = array(
		'label' => wp_kses_post( $ec_refund_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_balance_left' ) ),
		'value' => $ed::ltr( $total ),
		'tone'  => 'strong',
	);
} elseif ( 15 == $this->orderstatus_id ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- numeric string from the database.
	$ec_refund_after[] = array(
		'label' => wp_kses_post( $ec_refund_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_balance_left' ) ),
		'value' => $ed::money( 0.00 ),
		'tone'  => 'strong',
	);
}
if ( $this->refund_total > 0 ) {
	$ec_refund_after[] = array(
		'label' => wp_kses_post( $ec_refund_lang->get_text( 'account_order_details', 'account_orders_details_refund_total_short' ) ),
		'value' => '<strong>' . $ed::ltr( '-' . $refund ) . '</strong>',
		'tone'  => 'danger',
	);
}
if ( $ec_refund_after ) {
	$ed::totals( $ec_refund_after );
}

/* Notes, closing lines */
$ed::section_start(
	array(
		'top'    => 16,
		'bottom' => 8,
	)
);
if ( get_option( 'ec_option_user_order_notes' ) && '' !== trim( (string) $this->order_customer_notes ) ) {
	$ed::label( wp_kses_post( $ec_refund_lang->get_text( 'cart_payment_information', 'cart_payment_information_order_notes_title' ) ) );
	$ed::card_start( array( 'padding' => '12px 14px' ) );
	echo nl2br( esc_html( wp_unslash( $this->order_customer_notes ) ) );
	$ed::card_end();
}
do_action( 'wpeasycart_email_receipt_order_notes_after', $this->order_id );
$ed::paragraph( wp_kses_post( $ec_refund_lang->get_text( 'cart_success', 'cart_payment_complete_bottom_line_1' ) ), array( 'margin' => '16px 0 12px 0' ) );
$ed::paragraph(
	wp_kses_post( $ec_refund_lang->get_text( 'cart_success', 'cart_payment_complete_bottom_line_2' ) ),
	array(
		'tone'   => 'strong',
		'margin' => '0',
	)
);
$ed::section_end();
$ed::close();
