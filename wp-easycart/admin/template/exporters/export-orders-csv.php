<?php
/**
 * Orders CSV exporter.
 *
 * Included by wp_easycart_admin_orders::process_export_orders() after the
 * capability and nonce checks have passed. Streams the CSV straight to the
 * browser in bounded chunks of orders so a store with hundreds of thousands
 * of orders never holds the result set in memory. The column set and order
 * are identical to the previous single-query exporter.
 *
 * @since 6.0.0 Rewritten to stream in chunks (order line items, options, fees
 *              and the latest gateway response are fetched per chunk).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the nonce is verified by wp_easycart_admin_orders::process_export_orders() before this template is included.
$order_id_array = array();
if ( isset( $_GET['ec_admin_form_action'] ) && 'export-orders-csv' === sanitize_key( wp_unslash( $_GET['ec_admin_form_action'] ) ) && isset( $_GET['bulk'] ) ) {
	$order_id_array = array_values( array_unique( array_filter( array_map( 'absint', (array) wp_unslash( $_GET['bulk'] ) ) ) ) );
}
// phpcs:enable WordPress.Security.NonceVerification.Recommended

if ( ! function_exists( 'wp_easycart_export_orders_csv_value' ) ) {
	/**
	 * Format one cell the way the legacy exporter did.
	 *
	 * @since 6.0.0
	 *
	 * @param string      $key                  Column key.
	 * @param string|null $value                Raw value.
	 * @param bool        $is_new_order         True on the first line of an order.
	 * @param array       $single_use_key_names Columns that only print on the first line.
	 * @return string
	 */
	function wp_easycart_export_orders_csv_value( $key, $value, $is_new_order, $single_use_key_names ) {
		if ( ! $is_new_order && in_array( $key, $single_use_key_names, true ) ) {
			return '0.00';
		}
		if ( null === $value || '' === (string) $value ) {
			return '';
		}
		if ( 'billing_zip' === $key || 'shipping_zip' === $key ) {
			return '="' . $value . '"';
		}
		return htmlspecialchars_decode( (string) $value );
	}
}

if ( ! function_exists( 'wp_easycart_export_orders_csv_gateway_response' ) ) {
	/**
	 * Reduce a raw gateway log entry to the status token the legacy exporter printed.
	 *
	 * @since 6.0.0
	 *
	 * @param string $gateway       ec_order.order_gateway.
	 * @param string $response_text ec_response.response_text.
	 * @return string
	 */
	function wp_easycart_export_orders_csv_gateway_response( $gateway, $response_text ) {
		$response_text = (string) $response_text;
		if ( 'authorize' === $gateway ) {
			$response_exploded = explode( ',', $response_text );
			if ( count( $response_exploded ) > 3 ) {
				return $response_exploded[3];
			}
		} elseif ( 'paypal' === $gateway ) {
			preg_match_all( "/\[payment_status\] \=\> (.*)\n/", $response_text, $output_array );
			return isset( $output_array[1][0] ) ? $output_array[1][0] : '';
		} elseif ( 'stripe' === $gateway ) {
			preg_match_all( "/\[status\] \=\> (.*)\n/", $response_text, $output_array );
			return isset( $output_array[1][0] ) ? $output_array[1][0] : '';
		}
		return $response_text;
	}
}

/*
 * Column list. This is the exact SELECT list (and aliasing) of the previous
 * exporter, in the same order, so the header row does not change.
 */
$keys = array(
	'order_date',
	'order_status',
	'orderdetail_id',
	'order_id',
	'payment_method',
	'sub_total',
	'tip_total',
	'tax_total',
	'refund_total',
	'shipping_total',
	'discount_total',
	'vat_total',
	'vat_rate',
	'duty_total',
	'gst_total',
	'gst_rate',
	'pst_total',
	'pst_rate',
	'hst_total',
	'hst_rate',
	'grand_total',
	'user_id',
	'use_expedited_shipping',
	'shipping_method',
	'shipping_carrier',
	'shipping_service_code',
	'tracking_number',
	'gift_card_used',
	'promo_code_used',
	'product_id',
	'title',
	'model_number',
	'unit_price',
	'total_price',
	'quantity',
	'optionitem_name_1',
	'optionitem_name_2',
	'optionitem_name_3',
	'optionitem_name_4',
	'optionitem_name_5',
	'order_notes',
	'order_customer_notes',
	'user_email',
	'user_level',
	'billing_first_name',
	'billing_last_name',
	'billing_company_name',
	'billing_address_line_1',
	'billing_address_line_2',
	'billing_city',
	'billing_state',
	'billing_zip',
	'billing_country',
	'billing_country_name',
	'billing_phone',
	'shipping_first_name',
	'shipping_last_name',
	'shipping_company_name',
	'shipping_address_line_1',
	'shipping_address_line_2',
	'shipping_city',
	'shipping_state',
	'shipping_zip',
	'shipping_country',
	'shipping_country_name',
	'shipping_phone',
	'vat_registration_number',
	'agreed_to_terms',
	'order_ip_address',
	'use_advanced_optionset',
	'giftcard_id',
	'shipper_id',
	'shipper_first_name',
	'shipper_last_name',
	'gift_card_message',
	'gift_card_from_name',
	'gift_card_to_name',
	'gift_card_email',
	'download_file_name',
	'download_key',
	'deconetwork_id',
	'deconetwork_name',
	'deconetwork_product_code',
	'deconetwork_options',
	'deconetwork_color_code',
	'deconetwork_product_id',
	'deconetwork_image_link',
	'subscription_signup_fee',
	'order_weight',
	'order_gateway',
	'card_holder_name',
	'creditcard_digits',
	'cc_exp_month',
	'cc_exp_year',
	'subscription_id',
	'stripe_charge_id',
	'nets_transaction_id',
	'gateway_transaction_id',
	'paypal_email_id',
	'paypal_transaction_id',
	'paypal_payer_id',
	'fraktjakt_order_id',
	'fraktjakt_shipment_id',
	'gateway_response',
);

$single_use_key_names = apply_filters(
	'wp_easycart_order_export_single_keys',
	array(
		'sub_total',
		'tip_total',
		'tax_total',
		'tax_total',
		'refund_total',
		'shipping_total',
		'discount_total',
		'vat_total',
		'vat_rate',
		'hst_total',
		'hst_rate',
		'pst_total',
		'pst_rate',
		'gst_total',
		'gst_rate',
		'grand_total',
		'order_date',
		'order_status',
		'payment_method',
		'shipping_method',
		'tracking_number',
		'promo_code_used',
		'order_customer_notes',
		'agreed_to_terms',
		'order_ip_address',
		'order_weight',
		'order_gateway',
		'card_holder_name',
		'creditcard_digits',
		'cc_exp_month',
		'cc_exp_year',
		'stripe_charge_id',
		'order_notes',
		'gateway_response',
	)
);

$keys[] = 'advanced_product_options';

$fee_types = apply_filters( 'wp_easycart_order_export_fee_types', $wpdb->get_results( 'SELECT fee_label FROM ec_order_fee GROUP BY fee_label ORDER BY fee_label ASC' ) );
$fee_type_keys = array();
if ( $fee_types && is_array( $fee_types ) ) {
	foreach ( $fee_types as $fee_type ) {
		$keys[] = $fee_type->fee_label;
		$single_use_key_names[] = $fee_type->fee_label;
		$fee_type_keys[] = $fee_type->fee_label;
	}
}

$keys = apply_filters( 'wp_easycart_order_export_keys', $keys );

$needs_gateway_response = in_array( 'gateway_response', $keys, true );
$needs_advanced_options = in_array( 'advanced_product_options', $keys, true );

/* Chunked query pieces. */
$chunk_size = 500;

$order_sql = 'SELECT
			ec_order.order_id,
			ec_order.order_date,
			ec_orderstatus.order_status,
			ec_order.payment_method,
			ec_order.sub_total,
			ec_order.tip_total,
			ec_order.tax_total,
			ec_order.refund_total,
			ec_order.shipping_total,
			ec_order.discount_total,
			ec_order.vat_total,
			ec_order.vat_rate,
			ec_order.duty_total,
			ec_order.gst_total,
			ec_order.gst_rate,
			ec_order.pst_total,
			ec_order.pst_rate,
			ec_order.hst_total,
			ec_order.hst_rate,
			ec_order.grand_total,
			ec_order.user_id,
			ec_order.use_expedited_shipping,
			ec_order.shipping_method,
			ec_order.shipping_carrier,
			ec_order.shipping_service_code,
			ec_order.tracking_number,
			ec_order.giftcard_id AS gift_card_used,
			ec_order.promo_code AS promo_code_used,
			ec_order.order_notes,
			ec_order.order_customer_notes,
			ec_order.user_email,
			ec_order.user_level,
			ec_order.billing_first_name,
			ec_order.billing_last_name,
			ec_order.billing_company_name,
			ec_order.billing_address_line_1,
			ec_order.billing_address_line_2,
			ec_order.billing_city,
			ec_order.billing_state,
			ec_order.billing_zip,
			ec_order.billing_country,
			billing_country.name_cnt AS billing_country_name,
			ec_order.billing_phone,
			ec_order.shipping_first_name,
			ec_order.shipping_last_name,
			ec_order.shipping_company_name,
			ec_order.shipping_address_line_1,
			ec_order.shipping_address_line_2,
			ec_order.shipping_city,
			ec_order.shipping_state,
			ec_order.shipping_zip,
			ec_order.shipping_country,
			shipping_country.name_cnt AS shipping_country_name,
			ec_order.shipping_phone,
			ec_order.vat_registration_number,
			ec_order.agreed_to_terms,
			ec_order.order_ip_address,
			ec_order.order_weight,
			ec_order.order_gateway,
			ec_order.card_holder_name,
			ec_order.creditcard_digits,
			ec_order.cc_exp_month,
			ec_order.cc_exp_year,
			ec_order.subscription_id,
			ec_order.stripe_charge_id,
			ec_order.nets_transaction_id,
			ec_order.gateway_transaction_id,
			ec_order.paypal_email_id,
			ec_order.paypal_transaction_id,
			ec_order.paypal_payer_id,
			ec_order.fraktjakt_order_id,
			ec_order.fraktjakt_shipment_id
		FROM
			ec_order
			LEFT JOIN ec_country AS billing_country ON billing_country.iso2_cnt = ec_order.billing_country
			LEFT JOIN ec_country AS shipping_country ON shipping_country.iso2_cnt = ec_order.shipping_country
			LEFT JOIN ec_orderstatus ON ec_orderstatus.status_id = ec_order.orderstatus_id
		WHERE
			( ec_orderstatus.is_approved = 1 OR ec_orderstatus.is_approved = 0 )
			AND ec_order.order_id > %d';

$order_id_filter_sql = '';
if ( count( $order_id_array ) > 0 ) {
	$order_id_filter_sql = ' AND ec_order.order_id IN ( ' . implode( ', ', array_fill( 0, count( $order_id_array ), '%d' ) ) . ' )';
}

$detail_sql = 'SELECT
			orderdetail_id,
			order_id,
			product_id,
			title,
			model_number,
			unit_price,
			total_price,
			quantity,
			optionitem_name_1,
			optionitem_name_2,
			optionitem_name_3,
			optionitem_name_4,
			optionitem_name_5,
			use_advanced_optionset,
			giftcard_id,
			shipper_id,
			shipper_first_name,
			shipper_last_name,
			gift_card_message,
			gift_card_from_name,
			gift_card_to_name,
			gift_card_email,
			download_file_name,
			download_key,
			deconetwork_id,
			deconetwork_name,
			deconetwork_product_code,
			deconetwork_options,
			deconetwork_color_code,
			deconetwork_product_id,
			deconetwork_image_link,
			subscription_signup_fee
		FROM
			ec_orderdetail
		WHERE
			order_id IN ( {ORDER_IDS} )
		ORDER BY
			order_id ASC, orderdetail_id ASC';

/* Start streaming. */
if ( function_exists( 'set_time_limit' ) ) {
	@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a large export legitimately outlives max_execution_time; set_time_limit() warns where it is disabled.
}
while ( ob_get_level() > 0 ) {
	ob_end_clean();
}

header( 'Content-Type: text/csv; charset=utf-8' );
header( 'Content-Disposition: attachment; filename=order-export-' . date( 'Y-m-d' ) . '.csv' );

$output = fopen( 'php://output', 'w' );
fputcsv( $output, $keys );

$last_order_id = 0;
while ( true ) {
	$order_args = array_merge( array( $last_order_id ), $order_id_array, array( $chunk_size ) );
	$orders = $wpdb->get_results( $wpdb->prepare( $order_sql . $order_id_filter_sql . ' ORDER BY ec_order.order_id ASC LIMIT %d', $order_args ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $order_sql is a static string and $order_id_filter_sql only adds %d placeholders; all values go through prepare().
	if ( empty( $orders ) ) {
		break;
	}

	$chunk_order_ids = array();
	foreach ( $orders as $order_row ) {
		$chunk_order_ids[] = (int) $order_row['order_id'];
	}
	$last_order_id = max( $chunk_order_ids );
	$chunk_placeholders = implode( ', ', array_fill( 0, count( $chunk_order_ids ), '%d' ) );

	/* Line items for this chunk. */
	$details_by_order = array();
	$chunk_detail_ids = array();
	$detail_rows = $wpdb->get_results( $wpdb->prepare( str_replace( '{ORDER_IDS}', $chunk_placeholders, $detail_sql ), $chunk_order_ids ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- the only substitution is a list of %d placeholders the sniff cannot see; the ids are prepare() arguments.
	if ( $detail_rows ) {
		foreach ( $detail_rows as $detail_row ) {
			$details_by_order[ (int) $detail_row['order_id'] ][] = $detail_row;
			$chunk_detail_ids[] = (int) $detail_row['orderdetail_id'];
		}
	}

	/* Advanced option values, one query for the chunk. */
	$options_by_detail = array();
	if ( $needs_advanced_options && count( $chunk_detail_ids ) > 0 ) {
		$option_rows = $wpdb->get_results( $wpdb->prepare( 'SELECT orderdetail_id, option_value FROM ec_order_option WHERE orderdetail_id IN ( ' . implode( ', ', array_fill( 0, count( $chunk_detail_ids ), '%d' ) ) . ' ) ORDER BY orderdetail_id ASC, order_option_id ASC', $chunk_detail_ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- the only interpolation is a list of %d placeholders the sniff cannot see; the ids are prepare() arguments.
		if ( $option_rows ) {
			foreach ( $option_rows as $option_row ) {
				$options_by_detail[ (int) $option_row->orderdetail_id ][] = htmlspecialchars_decode( $option_row->option_value );
			}
		}
	}

	/* Latest gateway response per order, one grouped query for the chunk. */
	$response_by_order = array();
	if ( $needs_gateway_response ) {
		$response_rows = $wpdb->get_results( $wpdb->prepare( 'SELECT ec_response.order_id, ec_response.response_text FROM ec_response INNER JOIN ( SELECT order_id, MAX( response_id ) AS response_id FROM ec_response WHERE order_id IN ( ' . $chunk_placeholders . ' ) GROUP BY order_id ) AS latest ON latest.response_id = ec_response.response_id', $chunk_order_ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- the only interpolation is a list of %d placeholders the sniff cannot see; the ids are prepare() arguments.
		if ( $response_rows ) {
			foreach ( $response_rows as $response_row ) {
				$response_by_order[ (int) $response_row->order_id ] = $response_row->response_text;
			}
		}
	}

	/* Fees, one query for the chunk. */
	$fees_by_order = array();
	if ( count( $fee_type_keys ) > 0 ) {
		$fee_rows = $wpdb->get_results( $wpdb->prepare( 'SELECT order_id, fee_label, fee_total FROM ec_order_fee WHERE order_id IN ( ' . $chunk_placeholders . ' ) ORDER BY fee_label ASC, order_fee_id ASC', $chunk_order_ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- the only interpolation is a list of %d placeholders the sniff cannot see; the ids are prepare() arguments.
		if ( $fee_rows ) {
			foreach ( $fee_rows as $fee_row ) {
				$fee_order_id = (int) $fee_row->order_id;
				if ( ! isset( $fees_by_order[ $fee_order_id ][ $fee_row->fee_label ] ) ) {
					$fees_by_order[ $fee_order_id ][ $fee_row->fee_label ] = $fee_row->fee_total;
				}
			}
		}
	}

	/* Write the chunk. */
	foreach ( $orders as $order_row ) {
		$order_id = (int) $order_row['order_id'];
		$order_row['gateway_response'] = isset( $response_by_order[ $order_id ] ) ? wp_easycart_export_orders_csv_gateway_response( $order_row['order_gateway'], $response_by_order[ $order_id ] ) : '';

		/* An order with no line items still exports one row, as before. */
		$lines = isset( $details_by_order[ $order_id ] ) ? $details_by_order[ $order_id ] : array( array() );

		$is_new_order = true;
		foreach ( $lines as $detail ) {
			$row = array_merge( $order_row, $detail );
			$new_line = array();

			foreach ( $keys as $key ) {
				if ( 'advanced_product_options' === $key ) {
					$new_line[] = ( isset( $detail['orderdetail_id'] ) && isset( $options_by_detail[ (int) $detail['orderdetail_id'] ] ) ) ? implode( ', ', $options_by_detail[ (int) $detail['orderdetail_id'] ] ) : '';
				} elseif ( ! in_array( $key, $fee_type_keys, true ) ) {
					$new_line[] = wp_easycart_export_orders_csv_value( $key, isset( $row[ $key ] ) ? $row[ $key ] : null, $is_new_order, $single_use_key_names );
				}
			}

			if ( $is_new_order && count( $fee_type_keys ) > 0 ) {
				foreach ( $fee_types as $fee_type ) {
					$new_line[] = isset( $fees_by_order[ $order_id ][ $fee_type->fee_label ] ) ? $fees_by_order[ $order_id ][ $fee_type->fee_label ] : '0.000';
				}
			}

			fputcsv( $output, $new_line );
			$is_new_order = false;
		}
	}

	flush();

	if ( count( $orders ) < $chunk_size ) {
		break;
	}
}

fclose( $output );
die();
