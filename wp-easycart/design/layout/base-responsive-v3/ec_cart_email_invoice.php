<?php
/**
 * Invoice email ( "New Invoice Available", customer copy and store copy ).
 *
 * Included by ec_orderdisplay::send_invoice() twice ( $is_admin false, then true ), running as $this ( ec_orderdisplay,
 * built with order details ). Variables from the sender: $is_admin, $tax_struct ( ec_tax ), $total, $subtotal, $tip, $tax,
 * $has_duty, $duty, $vat, $shipping, $vat_rate, $gst, $gst_rate, $pst, $pst_rate, $hst, $hst_rate, $discount,
 * $email_logo_url, $store_page, $permalink_divider.
 *
 * On the shared email design since 6.0.0 ( wp_easycart_email_design, inc/classes/core/class-wp-easycart-email-design.php ):
 * the amount due and a "pay online" button lead the email, the remit-to address ( ec_option_invoice_address_info ) sits
 * under the header, and the store copy carries a "Store notification" label and a link to the order in the admin.
 * Copy this file to your theme / wp-easycart-data layout folder to customise it; the file name must stay the same.
 *
 * Hooks kept: wp_easycart_email_receipt_top, wpeasycart_email_receipt_line_item, wp_easycart_email_receipt_optionitems,
 *   wp_easycart_cart_item_unit_price_display, wp_easycart_email_receipt_image_width, wp_easycart_account_page_id.
 *
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'wp_easycart_email_design' ) ) {
	require_once EC_PLUGIN_DIRECTORY . '/inc/classes/core/class-wp-easycart-email-design.php';
}

$ed               = 'wp_easycart_email_design';
$is_admin         = ! empty( $is_admin );
$ec_invoice_lang  = wp_easycart_language();
$ec_invoice_cur   = $GLOBALS['currency'];
$ec_invoice_items = ( isset( $this->cart->cart ) && is_array( $this->cart->cart ) ) ? $this->cart->cart : array();
$ec_invoice_order = (int) $this->order_id;
global $wpdb;

/* Pay online link ( same link as before 6.0.0 ). */
$ec_invoice_pay_url  = wpeasycart_links()->get_cart_page(
	'invoice',
	array(
		'order_id'     => (int) $this->order_id,
		'ec_guest_key' => ( ( '' != $this->guest_key ) ? $this->guest_key : null ), // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- link code kept as is.
	)
);
$ec_invoice_pay_text = wp_strip_all_tags( $ec_invoice_lang->get_text( 'cart_success', 'cart_payment_complete_click_here' ) . ' ' . $ec_invoice_lang->get_text( 'cart_success', 'cart_invoice_pay_online' ) );

/* Offers v2: line-level flags keyed by orderdetail_id and the order-level applied-offers snapshot. */
$ec_invoice_offer_flags   = array();
$ec_invoice_offer_summary = array();
if ( function_exists( 'wp_easycart_offers_active' ) && wp_easycart_offers_active() && $ec_invoice_order > 0 ) {
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT orderdetail_id, product_id, is_free_gift, bundle_group_key, bundle_product_id, applied_offers FROM ec_orderdetail WHERE order_id = %d', $ec_invoice_order ) ) as $ec_invoice_flag_row ) {
		$ec_invoice_offer_flags[ (int) $ec_invoice_flag_row->orderdetail_id ] = $ec_invoice_flag_row;
	}
	$ec_invoice_offer_json    = $wpdb->get_var( $wpdb->prepare( 'SELECT applied_offers FROM ec_order WHERE order_id = %d', $ec_invoice_order ) );
	$ec_invoice_offer_decoded = ( $ec_invoice_offer_json ) ? json_decode( (string) $ec_invoice_offer_json, true ) : false;
	if ( is_array( $ec_invoice_offer_decoded ) && isset( $ec_invoice_offer_decoded['applied_offers'] ) && is_array( $ec_invoice_offer_decoded['applied_offers'] ) ) {
		$ec_invoice_offer_summary = $ec_invoice_offer_decoded['applied_offers'];
	}
}
/* The persisted order snapshot renders regardless of PRO state ( same as the receipt ). */
if ( ! $ec_invoice_offer_summary && isset( $this->applied_offers ) && '' !== (string) $this->applied_offers ) {
	$ec_invoice_offer_decoded = json_decode( (string) $this->applied_offers, true );
	if ( is_array( $ec_invoice_offer_decoded ) && isset( $ec_invoice_offer_decoded['applied_offers'] ) && is_array( $ec_invoice_offer_decoded['applied_offers'] ) ) {
		$ec_invoice_offer_summary = $ec_invoice_offer_decoded['applied_offers'];
	}
}

$ec_invoice_img_w   = (int) apply_filters( 'wp_easycart_email_receipt_image_width', 70 );
$ec_invoice_address = trim( (string) get_option( 'ec_option_invoice_address_info' ) );

$ed::open(
	array(
		'title'     => 'New Invoice Available',
		'preheader' => wp_strip_all_tags( $ec_invoice_lang->get_text( 'cart_success', 'cart_invoice_line_1' ) ),
		'logo_url'  => (string) $email_logo_url,
		'store_url' => (string) $store_page,
		'eyebrow'   => $is_admin ? __( 'Store notification', 'wp-easycart' ) : '',
	)
);
$ec_invoice_ctx   = $ed::ctx();
$ec_invoice_badge = 'display:inline-block;margin-' . $ec_invoice_ctx['start'] . ':6px;padding:1px 8px;font-size:11px;font-weight:bold;border-radius:3px;text-transform:uppercase;vertical-align:middle;';

/* Hooks here print whole rows ( <tr> ) into the email container. */
do_action( 'wp_easycart_email_receipt_top', $this->order_id, $is_admin );

/* Remit-to address ( "send a check payment to the address listed above" ) */
if ( '' !== $ec_invoice_address ) {
	$ed::section_start( array( 'top' => 8 ) );
	$ed::paragraph(
		nl2br( esc_html( $ec_invoice_address ) ),
		array(
			'tone'   => 'small',
			'margin' => '0',
			'nolink' => true,
		)
	);
	$ed::section_end();
}

/* Intro, amount due, pay button */
$ed::section_start( array( 'top' => 20 ) );
$ed::paragraph( wp_kses_post( $ec_invoice_lang->get_text( 'cart_success', 'cart_invoice_line_1' ) ) );
$ed::card_start();
$ed::key_values(
	array(
		array(
			'label' => wp_kses_post( rtrim( trim( $ec_invoice_lang->get_text( 'account_order_details', 'account_orders_details_order_number' ) ), ':' ) ),
			'value' => $ed::ltr( $this->order_id ),
		),
		array(
			'label' => wp_kses_post( rtrim( trim( $ec_invoice_lang->get_text( 'account_order_details', 'account_orders_details_order_date' ) ), ':' ) ),
			'value' => ( '' !== (string) $this->order_date ) ? esc_html( date_i18n( get_option( 'date_format' ), strtotime( $this->order_date ) ) ) : '',
		),
		array(
			'label' => wp_kses_post( $ec_invoice_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_grand_total' ) ),
			'value' => $ed::ltr( $total ),
		),
	)
);
$ed::button(
	$ec_invoice_pay_url,
	$ec_invoice_pay_text,
	array(
		'margin' => '14px 0 0 0',
		'arrow'  => true,
	)
);
$ed::card_end();
if ( $is_admin && $ec_invoice_order > 0 ) {
	$ed::button(
		admin_url( 'admin.php?page=wp-easycart-orders&subpage=orders&ec_admin_form_action=edit&order_id=' . (int) $ec_invoice_order ),
		__( 'Open the order in your store admin', 'wp-easycart' ),
		array(
			'variant' => 'secondary',
			'margin'  => '12px 0 0 0',
		)
	);
}
$ed::section_end();

/* Items */
$ed::items_start(
	array(
		'product' => wp_kses_post( $ec_invoice_lang->get_text( 'cart_success', 'cart_payment_complete_details_header_1' ) ),
		'qty'     => wp_kses_post( $ec_invoice_lang->get_text( 'cart_success', 'cart_payment_complete_details_header_2' ) ),
		'unit'    => wp_kses_post( $ec_invoice_lang->get_text( 'cart_success', 'cart_payment_complete_details_header_3' ) ),
		'total'   => wp_kses_post( $ec_invoice_lang->get_text( 'cart_success', 'cart_payment_complete_details_header_4' ) ),
	)
);
foreach ( $ec_invoice_items as $ec_invoice_item ) {
	$unit_price            = $ec_invoice_cur->get_currency_display( $ec_invoice_item->unit_price );
	$total_price           = $ec_invoice_cur->get_currency_display( $ec_invoice_item->total_price );
	$ec_invoice_line_flags = ( isset( $ec_invoice_item->orderdetail_id ) && isset( $ec_invoice_offer_flags[ (int) $ec_invoice_item->orderdetail_id ] ) ) ? $ec_invoice_offer_flags[ (int) $ec_invoice_item->orderdetail_id ] : false;

	/* Title with offers v2 badges ( free gift, bundle item, per-line offer amounts ). */
	$ec_invoice_title   = wp_kses_post( $ec_invoice_lang->convert_text( $ec_invoice_item->title ) );
	$ec_invoice_is_gift = $ec_invoice_line_flags ? ! empty( $ec_invoice_line_flags->is_free_gift ) : ! empty( $ec_invoice_item->is_free_gift );
	$ec_invoice_bundle  = $ec_invoice_line_flags ? $ec_invoice_line_flags : $ec_invoice_item;
	if ( $ec_invoice_is_gift && function_exists( 'wp_easycart_offers_text' ) ) {
		$ec_invoice_title .= ' <span style="' . esc_attr( $ec_invoice_badge ) . 'background:#fce7f0;color:#c2185b;">' . wp_kses_post( wp_easycart_offers_text( 'cart_offers', 'gift_line_label' ) ) . '</span>';
	}
	if ( function_exists( 'wp_easycart_offers_text' ) && isset( $ec_invoice_bundle->bundle_group_key ) && '' !== (string) $ec_invoice_bundle->bundle_group_key && isset( $ec_invoice_bundle->bundle_product_id ) && (int) $ec_invoice_bundle->bundle_product_id !== (int) $ec_invoice_bundle->product_id ) {
		$ec_invoice_title .= ' <span style="' . esc_attr( $ec_invoice_badge ) . 'background:#eef1f4;color:#4a5560;">' . wp_kses_post( wp_easycart_offers_text( 'cart_offers', 'bundle_line_label' ) ) . '</span>';
	}
	if ( $ec_invoice_line_flags && isset( $ec_invoice_line_flags->applied_offers ) && '' !== (string) $ec_invoice_line_flags->applied_offers ) {
		$ec_invoice_line_offers = json_decode( (string) $ec_invoice_line_flags->applied_offers, true );
		if ( is_array( $ec_invoice_line_offers ) && count( $ec_invoice_line_offers ) > 0 ) {
			$ec_invoice_title .= '<div style="margin-top:4px;">';
			foreach ( $ec_invoice_line_offers as $ec_invoice_line_offer ) {
				if ( ! isset( $ec_invoice_line_offer['label'] ) || ! isset( $ec_invoice_line_offer['amount'] ) || (float) $ec_invoice_line_offer['amount'] <= 0 ) {
					continue;
				}
				$ec_invoice_title .= '<span style="display:inline-block;margin:2px 4px 0 0;padding:1px 8px;background:#e7f5ec;color:#1f7a3d;font-size:11px;font-weight:normal;border-radius:3px;">' . esc_html( $ec_invoice_line_offer['label'] ) . ' &minus;' . esc_html( $ec_invoice_cur->get_currency_display( (float) $ec_invoice_line_offer['amount'] ) ) . '</span>';
			}
			$ec_invoice_title .= '</div>';
		}
	}

	$ec_invoice_image = '';
	if ( get_option( 'ec_option_show_image_on_receipt' ) ) {
		$ec_invoice_image = $ed::product_image_url(
			( isset( $ec_invoice_item->image1_optionitem ) && '' !== (string) $ec_invoice_item->image1_optionitem ) ? $ec_invoice_item->image1_optionitem : ( isset( $ec_invoice_item->image1 ) ? $ec_invoice_item->image1 : '' ),
			! empty( $ec_invoice_item->is_deconetwork ),
			isset( $ec_invoice_item->deconetwork_image_link ) ? $ec_invoice_item->deconetwork_image_link : ''
		);
	}
	$ed::item_start(
		array(
			'image_url'   => $ec_invoice_image,
			'image_alt'   => $ec_invoice_lang->convert_text( $ec_invoice_item->title ),
			'image_width' => $ec_invoice_img_w,
			'title_html'  => $ec_invoice_title,
		)
	);
	if ( isset( $ec_invoice_item->orderdetails_model_number ) && '' !== (string) $ec_invoice_item->orderdetails_model_number ) {
		$ed::detail(
			esc_html( $ec_invoice_item->orderdetails_model_number ),
			array(
				'nolink' => true,
				'style'  => 'padding:0 0 4px 0;',
			)
		);
	}
	if ( ! empty( $ec_invoice_item->gift_card_message ) ) {
		$ed::detail( wp_kses_post( $ec_invoice_lang->get_text( 'account_order_details', 'account_orders_details_gift_message' ) ) . esc_html( $ec_invoice_item->gift_card_message ) );
	}
	if ( ! empty( $ec_invoice_item->gift_card_from_name ) ) {
		$ed::detail( wp_kses_post( $ec_invoice_lang->get_text( 'account_order_details', 'account_orders_details_gift_from' ) ) . esc_html( $ec_invoice_item->gift_card_from_name ) );
	}
	if ( ! empty( $ec_invoice_item->gift_card_to_name ) ) {
		$ed::detail( wp_kses_post( $ec_invoice_lang->get_text( 'account_order_details', 'account_orders_details_gift_to' ) ) . esc_html( $ec_invoice_item->gift_card_to_name ) );
	}

	do_action( 'wpeasycart_email_receipt_line_item', $ec_invoice_item->model_number, $ec_invoice_item->orderdetail_id );
	$advanced_option_allow_download = true;

	/* Basic options */
	if ( empty( $ec_invoice_item->use_advanced_optionset ) || ! empty( $ec_invoice_item->use_both_option_types ) ) {
		for ( $ec_invoice_n = 1; $ec_invoice_n <= 5; $ec_invoice_n++ ) {
			$ec_invoice_opt_name  = isset( $ec_invoice_item->{'optionitem' . $ec_invoice_n . '_name'} ) ? (string) $ec_invoice_item->{'optionitem' . $ec_invoice_n . '_name'} : '';
			$ec_invoice_opt_price = isset( $ec_invoice_item->{'optionitem' . $ec_invoice_n . '_price'} ) ? (float) $ec_invoice_item->{'optionitem' . $ec_invoice_n . '_price'} : 0;
			if ( '' === $ec_invoice_opt_name ) {
				continue;
			}
			$ec_invoice_opt_text = '';
			if ( $ec_invoice_opt_price < 0 ) {
				$ec_invoice_opt_text = ' (' . $ec_invoice_cur->get_currency_display( $ec_invoice_opt_price ) . ')';
			} elseif ( $ec_invoice_opt_price > 0 ) {
				$ec_invoice_opt_text = ' (+' . $ec_invoice_cur->get_currency_display( $ec_invoice_opt_price ) . ')';
			}
			$ed::detail( wp_kses_post( $ec_invoice_opt_name ) . esc_html( $ec_invoice_opt_text ) );
		}
	}

	/* Advanced options ( customer uploads link to the gated download on the store copy ) */
	if ( ! empty( $ec_invoice_item->use_advanced_optionset ) || ! empty( $ec_invoice_item->use_both_option_types ) ) {
		$advanced_options = $this->mysqli->get_order_options( $ec_invoice_item->orderdetail_id );
		foreach ( (array) $advanced_options as $advanced_option ) {
			if ( isset( $advanced_option->optionitem_allow_download ) && ! $advanced_option->optionitem_allow_download ) {
				$advanced_option_allow_download = false;
			}
			$ec_invoice_adv_price = '';
			if ( ! empty( $advanced_option->optionitem_enable_custom_price_label ) && ( ( isset( $advanced_option->optionitem_price ) && 0 != $advanced_option->optionitem_price ) || ( isset( $advanced_option->optionitem_price_onetime ) && 0 != $advanced_option->optionitem_price_onetime ) ) ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- numeric strings from the database.
				$ec_invoice_adv_price = ' ' . $advanced_option->optionitem_custom_price_label;
			} elseif ( isset( $advanced_option->optionitem_price ) && $advanced_option->optionitem_price > 0 ) {
				$ec_invoice_adv_price = ' (+' . $ec_invoice_cur->get_currency_display( $advanced_option->optionitem_price ) . ' ' . wp_strip_all_tags( $ec_invoice_lang->get_text( 'cart', 'cart_item_adjustment' ) ) . ')';
			} elseif ( isset( $advanced_option->optionitem_price ) && $advanced_option->optionitem_price < 0 ) {
				$ec_invoice_adv_price = ' (' . $ec_invoice_cur->get_currency_display( $advanced_option->optionitem_price ) . ' ' . wp_strip_all_tags( $ec_invoice_lang->get_text( 'cart', 'cart_item_adjustment' ) ) . ')';
			} elseif ( isset( $advanced_option->optionitem_price_onetime ) && $advanced_option->optionitem_price_onetime > 0 ) {
				$ec_invoice_adv_price = ' (+' . $ec_invoice_cur->get_currency_display( $advanced_option->optionitem_price_onetime ) . ' ' . wp_strip_all_tags( $ec_invoice_lang->get_text( 'cart', 'cart_order_adjustment' ) ) . ')';
			} elseif ( isset( $advanced_option->optionitem_price_onetime ) && $advanced_option->optionitem_price_onetime < 0 ) {
				$ec_invoice_adv_price = ' (' . $ec_invoice_cur->get_currency_display( $advanced_option->optionitem_price_onetime ) . ' ' . wp_strip_all_tags( $ec_invoice_lang->get_text( 'cart', 'cart_order_adjustment' ) ) . ')';
			} elseif ( isset( $advanced_option->optionitem_price_override ) && $advanced_option->optionitem_price_override > -1 ) {
				$ec_invoice_adv_price = ' (' . wp_strip_all_tags( $ec_invoice_lang->get_text( 'cart', 'cart_item_new_price_option' ) ) . ' ' . $ec_invoice_cur->get_currency_display( $advanced_option->optionitem_price_override ) . ')';
			}

			if ( 'file' === $advanced_option->option_type ) {
				$file_split     = explode( '/', (string) $advanced_option->option_value );
				$ec_upload_name = ( count( $file_split ) > 1 ) ? $file_split[ count( $file_split ) - 1 ] : $advanced_option->option_value;
				if ( $is_admin && class_exists( 'wp_easycart_admin_order_uploads' ) && ! wp_easycart_admin_order_uploads::file_available( $advanced_option->option_value ) ) {
					/* 6.0.0: the upload was not kept; say so rather than link to a missing file. */
					$ec_invoice_adv_value = esc_html( $ec_upload_name ) . ' <em>(' . esc_html__( 'file not received', 'wp-easycart' ) . ')</em>';
				} elseif ( $is_admin && class_exists( 'wp_easycart_admin_order_uploads' ) ) {
					/* Admin copy: link the filename to the gated download ( login + order capability required; durable token so the link outlives a nonce ). */
					$ec_invoice_adv_value = '<a href="' . esc_url( wp_easycart_admin_order_uploads::url( $ec_invoice_item->orderdetail_id, $advanced_option->option_value, true ) ) . '" style="' . esc_attr( $ed::css( 'link' ) ) . '">' . esc_html( $ec_upload_name ) . '</a>';
				} else {
					$ec_invoice_adv_value = esc_html( $ec_upload_name );
				}
				$ec_invoice_adv_value .= esc_html( $ec_invoice_adv_price );
			} elseif ( 'grid' === $advanced_option->option_type ) {
				$ec_invoice_adv_value = esc_html( $advanced_option->optionitem_name . ' (' . $advanced_option->option_value . ')' . $ec_invoice_adv_price );
			} else {
				$ec_invoice_adv_value = esc_html( $advanced_option->option_value . $ec_invoice_adv_price );
			}
			$ed::option_detail( wp_kses_post( $advanced_option->option_label ), $ec_invoice_adv_value );
		}
	}

	/* Gift card print link ( paid orders; the print page refuses unpaid ones ) / download link */
	if ( ! empty( $ec_invoice_item->is_giftcard ) || ( ! empty( $ec_invoice_item->is_download ) && $advanced_option_allow_download ) ) {
		$account_page_id = apply_filters( 'wp_easycart_account_page_id', get_option( 'ec_option_accountpage' ) );
		$account_page    = get_permalink( $account_page_id );
		if ( substr_count( $account_page, '?' ) ) {
			$permalink_divider = '&';
		} else {
			$permalink_divider = '?';
		}
		if ( ! empty( $ec_invoice_item->is_giftcard ) && $this->is_approved ) {
			$ed::detail( '<a href="' . esc_url( get_site_url() . '?wpeasycarthook=print-giftcard&order_id=' . $this->order_id . '&orderdetail_id=' . $ec_invoice_item->orderdetail_id . '&giftcard_id=' . $ec_invoice_item->giftcard_id . ( ( '' != $this->guest_key ) ? '&ec_guest_key=' . $this->guest_key : '' ) ) . '" target="_blank" style="' . esc_attr( $ed::css( 'link' ) ) . '">' . wp_kses_post( $ec_invoice_lang->get_text( 'account_order_details', 'account_orders_details_print_online' ) ) . '</a>', array( 'style' => 'padding-top:4px;' ) ); // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- link code kept as is.
		} elseif ( ! empty( $ec_invoice_item->is_download ) ) {
			$ed::detail( '<a href="' . esc_url( wpeasycart_links()->get_account_page( 'order_details', array( 'order_id' => (int) $this->order_id ) ) ) . '" target="_blank" style="' . esc_attr( $ed::css( 'link' ) ) . '">' . wp_kses_post( $ec_invoice_lang->get_text( 'account_order_details', 'account_orders_details_download' ) ) . '</a>', array( 'style' => 'padding-top:4px;' ) );
		}
	}

	/* Codes ( approved orders only ) */
	if ( ! empty( $ec_invoice_item->include_code ) && $this->is_approved ) {
		$codes     = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ec_code WHERE ec_code.orderdetail_id = %d', $ec_invoice_item->orderdetail_id ) );
		$code_list = array();
		foreach ( (array) $codes as $ec_invoice_code ) {
			$code_list[] = $ec_invoice_code->code_val;
		}
		$code_list = implode( ', ', $code_list );
		$ed::detail( wp_kses_post( $ec_invoice_lang->get_text( 'account_order_details', 'account_orders_details_your_codes' ) ) . ' ' . esc_html( $code_list ) );
	}

	do_action( 'wp_easycart_email_receipt_optionitems', $ec_invoice_item );

	$ed::item_end(
		array(
			'qty'        => $ec_invoice_item->quantity,
			'unit_html'  => $ed::ltr( wp_strip_all_tags( (string) apply_filters( 'wp_easycart_cart_item_unit_price_display', $unit_price, $ec_invoice_item->product_id ) ) ),
			'total_html' => $ed::ltr( $total_price ),
		)
	);
}
$ed::items_end();

/* Totals */
$ec_invoice_canada   = get_option( 'ec_option_enable_easy_canada_tax' );
$ec_invoice_totals   = array();
$ec_invoice_totals[] = array( wp_kses_post( $ec_invoice_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_subtotal' ) ), $ed::ltr( $subtotal ) );
if ( $this->tip_total > 0 ) {
	$ec_invoice_totals[] = array( wp_kses_post( $ec_invoice_lang->get_text( 'cart_totals', 'cart_totals_tip' ) ), $ed::ltr( $tip ) );
}
if ( ( is_object( $tax_struct ) && $tax_struct->is_tax_enabled() && ! $ec_invoice_canada ) || ( $ec_invoice_canada && $this->tax_total > 0 ) ) {
	$ec_invoice_totals[] = array( wp_kses_post( $ec_invoice_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_tax' ) ), $ed::ltr( $tax ) );
}
if ( get_option( 'ec_option_use_shipping' ) && $this->shipping_total > 0 ) {
	$ec_invoice_totals[] = array( wp_kses_post( $ec_invoice_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_shipping' ) ), $ed::ltr( $shipping ) );
}
foreach ( $ec_invoice_offer_summary as $ec_invoice_offer_row ) {
	if ( ! isset( $ec_invoice_offer_row['label'] ) || ! isset( $ec_invoice_offer_row['amount'] ) || (float) $ec_invoice_offer_row['amount'] <= 0 ) {
		continue;
	}
	$ec_invoice_totals[] = array( esc_html( $ec_invoice_offer_row['label'] . ( ( isset( $ec_invoice_offer_row['code'] ) && '' !== (string) $ec_invoice_offer_row['code'] ) ? ' (' . $ec_invoice_offer_row['code'] . ')' : '' ) ), $ed::ltr( '-' . $ec_invoice_cur->get_currency_display( (float) $ec_invoice_offer_row['amount'] ) ) );
}
if ( 0 != $this->discount_total ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- numeric string from the database.
	$ec_invoice_totals[] = array( wp_kses_post( $ec_invoice_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_discount' ) ), $ed::ltr( '-' . $discount ) );
}
if ( $has_duty ) {
	$ec_invoice_totals[] = array( wp_kses_post( $ec_invoice_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_duty' ) ), $ed::ltr( $duty ) );
}
if ( is_object( $tax_struct ) && $tax_struct->is_vat_enabled() ) {
	$ec_invoice_totals[] = array( wp_kses_post( $ec_invoice_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_vat' ) ) . esc_html( $vat_rate ) . '%', $ed::ltr( $vat ) );
}
foreach ( array(
	'gst' => array( $gst, $gst_rate ),
	'pst' => array( $pst, $pst_rate ),
	'hst' => array( $hst, $hst_rate ),
) as $ec_invoice_ca_key => $ec_invoice_ca ) {
	if ( $ec_invoice_ca[0] > 0 ) {
		$ec_invoice_ca_label = function_exists( 'wp_easycart_canada_tax_label' ) ? wp_easycart_canada_tax_label( $ec_invoice_ca_key, $this->shipping_state ) : strtoupper( $ec_invoice_ca_key );
		$ec_invoice_totals[] = array( esc_html( $ec_invoice_ca_label . ' (' . $ec_invoice_ca[1] . '%)' ), $ed::money( $ec_invoice_ca[0] ) );
	}
}
if ( isset( $this->order_fees ) && is_array( $this->order_fees ) ) {
	foreach ( $this->order_fees as $order_fee ) {
		$ec_invoice_totals[] = array( esc_html( $order_fee->fee_label ), $ed::money( $order_fee->fee_total ) );
	}
}
$ed::totals( $ec_invoice_totals, array( wp_kses_post( $ec_invoice_lang->get_text( 'cart_success', 'cart_payment_complete_order_totals_grand_total' ) ), $ed::ltr( $total ) ) );

/* Second pay button under the totals */
$ed::button_row(
	$ec_invoice_pay_url,
	$ec_invoice_pay_text,
	array(
		'top'    => 16,
		'bottom' => 8,
		'align'  => 'end',
	)
);
$ed::close();
