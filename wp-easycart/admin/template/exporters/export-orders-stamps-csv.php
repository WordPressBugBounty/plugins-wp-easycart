<?php
/**
 * Stamps.com orders CSV exporter.
 *
 * Included by wp_easycart_admin_orders::process_export_orders_stamps() after
 * the capability and nonce checks have passed. Streams the CSV in bounded
 * chunks of orders; the line items used to size the parcel are fetched once
 * per chunk instead of once per order.
 *
 * @since 6.0.0 Rewritten to stream in chunks.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

if ( ! function_exists( 'wpeasycart_stamps_export_calculate_parcel' ) ) {
	/**
	 * Estimate a single parcel size from the products in an order.
	 *
	 * @param array $products Objects with optional width / height / length.
	 * @return array width, height, length.
	 */
	function wpeasycart_stamps_export_calculate_parcel( $products ) {
		$package_dimensions = array( 0, 0, 0 );
		$package_volume = 0;
		$package_volume_empty = 0;
		$package_volume_used = 0;

		foreach ( $products as $product ) {
			$width = ( isset( $product->width ) ) ? $product->width : 1;
			$height = ( isset( $product->height ) ) ? $product->height : 1;
			$length = ( isset( $product->length ) ) ? $product->length : 1;
			$product_dimensions = array( $width, $height, $length );
			rsort( $product_dimensions, SORT_NUMERIC );
			if ( $product_dimensions[0] <= $package_dimensions[0] && $product_dimensions[1] <= $package_dimensions[1] && $product_dimensions[2] <= $package_dimensions[2] && ( $product_dimensions[0] * $product_dimensions[1] * $product_dimensions[2] ) <= $package_volume_empty ) {
				$package_volume_empty -= $product_dimensions[0] * $product_dimensions[1] * $product_dimensions[2];
				$package_volume_used += $product_dimensions[0] * $product_dimensions[1] * $product_dimensions[2];
			} else {
				$package_dimensions[2] += $product_dimensions[2];
				if ( $product_dimensions[1] > $package_dimensions[1] ) {
					$package_dimensions[1] = $product_dimensions[1];
				}
				if ( $product_dimensions[0] > $package_dimensions[0] ) {
					$package_dimensions[0] = $product_dimensions[0];
				}
				rsort( $package_dimensions, SORT_NUMERIC );
				$package_volume = $package_dimensions[0] * $package_dimensions[1] * $package_dimensions[2];
				$package_volume_used += ( $product_dimensions[0] * $product_dimensions[1] * $product_dimensions[2] );
				$package_volume_empty = ( $package_volume - $package_volume_used );
			}
		}
		return array(
			'width' => $package_dimensions[0],
			'height' => $package_dimensions[1],
			'length' => $package_dimensions[2],
		);
	}
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the nonce is verified by wp_easycart_admin_orders::process_export_orders_stamps() before this template is included.
$order_id_array = array();
if ( isset( $_GET['ec_admin_form_action'] ) && 'export-orders-stamps-csv' === sanitize_key( wp_unslash( $_GET['ec_admin_form_action'] ) ) && isset( $_GET['bulk'] ) ) {
	$order_id_array = array_values( array_unique( array_filter( array_map( 'absint', (array) wp_unslash( $_GET['bulk'] ) ) ) ) );
}
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$keys = array(
	'Order ID (required)',
	'Order Date',
	'Order Value',
	'Requested Service',
	'Ship To - Name',
	'Ship To - Company',
	'Ship To - Address 1',
	'Ship To - Address 2',
	'Ship To - Address 3',
	'Ship To - State/Province',
	'Ship To - City',
	'Ship To - Postal Code',
	'Ship To - Country',
	'Ship To - Phone',
	'Ship To - Email',
	'Total Weight in Oz',
	'Dimensions - Length',
	'Dimensions - Width',
	'Dimensions - Height',
	'Notes - From Customer',
	'Notes - Internal',
	'Gift Wrap?',
	'Gift Message',
);

$chunk_size = 500;

/* DATE_FORMAT specifiers are doubled so $wpdb->prepare() leaves them alone. */
$order_sql = "SELECT
			ec_order.order_id,
			DATE_FORMAT( ec_order.order_date, '%%m/%%d/%%Y %%H:%%i' ) AS order_date,
			ec_order.grand_total,
			ec_order.user_email,
			ec_order.shipping_first_name,
			ec_order.shipping_last_name,
			ec_order.shipping_company_name,
			ec_order.shipping_address_line_1,
			ec_order.shipping_address_line_2,
			ec_order.shipping_city,
			ec_order.shipping_state,
			ec_order.shipping_zip,
			ec_order.shipping_country,
			ec_order.shipping_phone,
			ec_order.order_weight
		FROM
			ec_order
			LEFT JOIN ec_orderstatus ON ec_orderstatus.status_id = ec_order.orderstatus_id
		WHERE
			( ec_orderstatus.is_approved = 1 OR ec_orderstatus.is_approved = 0 )
			AND ec_order.order_id > %d";

$order_id_filter_sql = '';
if ( count( $order_id_array ) > 0 ) {
	$order_id_filter_sql = ' AND ec_order.order_id IN ( ' . implode( ', ', array_fill( 0, count( $order_id_array ), '%d' ) ) . ' )';
}

if ( function_exists( 'set_time_limit' ) ) {
	@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a large export legitimately outlives max_execution_time; set_time_limit() warns where it is disabled.
}
while ( ob_get_level() > 0 ) {
	ob_end_clean();
}

header( 'Content-Type: text/csv; charset=utf-8' );
header( 'Content-Disposition: attachment; filename=stamps-export-' . date( 'Y-m-d' ) . '.csv' );

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

	/* Product dimensions for every line item in the chunk. */
	$products_by_order = array();
	$product_rows = $wpdb->get_results( $wpdb->prepare( 'SELECT ec_orderdetail.order_id, ec_product.length, ec_product.width, ec_product.height FROM ec_orderdetail LEFT JOIN ec_product ON ec_product.product_id = ec_orderdetail.product_id WHERE ec_orderdetail.order_id IN ( ' . implode( ', ', array_fill( 0, count( $chunk_order_ids ), '%d' ) ) . ' ) ORDER BY ec_orderdetail.order_id ASC, ec_orderdetail.orderdetail_id ASC', $chunk_order_ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- the only interpolation is a list of %d placeholders; the ids are prepare() arguments.
	if ( $product_rows ) {
		foreach ( $product_rows as $product_row ) {
			$products_by_order[ (int) $product_row->order_id ][] = $product_row;
		}
	}

	foreach ( $orders as $result ) {
		$order_id = (int) $result['order_id'];
		$parcel = wpeasycart_stamps_export_calculate_parcel( isset( $products_by_order[ $order_id ] ) ? $products_by_order[ $order_id ] : array() );
		fputcsv(
			$output,
			array(
				$result['order_id'],
				$result['order_date'],
				number_format( (float) $result['grand_total'], 2, '.', '' ),
				'',
				$result['shipping_first_name'] . ' ' . $result['shipping_last_name'],
				$result['shipping_company_name'],
				$result['shipping_address_line_1'],
				$result['shipping_address_line_2'],
				'',
				$result['shipping_state'],
				$result['shipping_city'],
				$result['shipping_zip'],
				$result['shipping_country'],
				$result['shipping_phone'],
				$result['user_email'],
				$result['order_weight'] * 16,
				$parcel['length'],
				$parcel['width'],
				$parcel['height'],
				'',
				'',
				'',
				'',
			)
		);
	}

	flush();

	if ( count( $orders ) < $chunk_size ) {
		break;
	}
}

fclose( $output );
die();
