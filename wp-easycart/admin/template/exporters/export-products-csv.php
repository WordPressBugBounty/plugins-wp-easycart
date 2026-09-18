<?php
/**
 * Products CSV exporter.
 *
 * Included by wp_easycart_admin_products::process_export_products_csv() and
 * ::process_export_products_csv_staged() after the capability and nonce
 * checks. Streams one CSV to the browser in bounded chunks of products; the
 * category, price tier, B2B price and advanced option side tables are read
 * only for the products in the current chunk. Replaces the previous
 * multi-file redirect / zip flow with a single streamed download.
 *
 * @since 6.0.0 Rewritten to stream in chunks.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Both entry points already allow wpec_products ( the staged V2 export is reachable with that role alone ), so accept it here too ( @since 6.0.0 ). */
if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_manager' ) && ! current_user_can( 'wpec_products' ) ) {
	echo 'Not Authenticated';
	die();
}

global $wpdb;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the nonce is verified by wp_easycart_admin_products::process_export_products_csv() / ::process_export_products_csv_staged() before this template is included.
$product_id_array = array();
if ( isset( $_GET['ec_admin_form_action'] ) && 'export-products-csv' === sanitize_key( wp_unslash( $_GET['ec_admin_form_action'] ) ) && isset( $_GET['bulk'] ) ) {
	$product_id_array = array_values( array_unique( array_filter( array_map( 'absint', (array) wp_unslash( $_GET['bulk'] ) ) ) ) );
}
// phpcs:enable WordPress.Security.NonceVerification.Recommended

if ( ! function_exists( 'wp_easycart_export_products_csv_normalize' ) ) {
	/**
	 * Normalise line endings and strip slashes the way the legacy exporter did.
	 * Quoting is left to fputcsv().
	 *
	 * @since 6.0.0
	 *
	 * @param string|null $value Raw value.
	 * @return string
	 */
	function wp_easycart_export_products_csv_normalize( $value ) {
		$value = str_replace( "\r\n", "\n", (string) $value );
		$value = str_replace( "\r", "\n", $value );
		$value = preg_replace( "/\n{2,}/", "\n\n", $value );
		return stripslashes( $value );
	}
}

/* Products per query block; the option is the legacy "Product Export: Max Block Size". */
$chunk_size = (int) get_option( 'ec_option_product_export_max' );
if ( $chunk_size < 10 ) {
	$chunk_size = 500;
}

$product_sql = "SELECT
			ec_product.*,
			GROUP_CONCAT( DISTINCT ec_categoryitem.category_id SEPARATOR ',' ) AS categories,
			'' AS price_tiers,
			'' AS b2b_prices
		FROM
			ec_product
			LEFT JOIN ec_categoryitem ON ec_categoryitem.product_id = ec_product.product_id
		WHERE
			";

$id_chunks = ( count( $product_id_array ) > 0 ) ? array_chunk( $product_id_array, $chunk_size ) : null;
$id_chunk_index = 0;
$last_product_id = 0;
$keys = null;
$output = null;

if ( function_exists( 'set_time_limit' ) ) {
	@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a large export legitimately outlives max_execution_time; set_time_limit() warns where it is disabled.
}
while ( ob_get_level() > 0 ) {
	ob_end_clean();
}

header( 'Content-type: text/csv; charset=UTF-8' );
header( 'Content-Transfer-Encoding: binary' );
header( 'Content-Disposition: attachment; filename=product-export-' . date( 'Y-m-d' ) . '.csv' );
header( 'Pragma: no-cache' );
header( 'Expires: 0' );

while ( true ) {
	if ( null !== $id_chunks ) {
		/* Selected ids: one query block per 500 (chunk_size) ids. */
		if ( ! isset( $id_chunks[ $id_chunk_index ] ) ) {
			break;
		}
		$chunk_ids = $id_chunks[ $id_chunk_index ];
		$id_chunk_index++;
		$results = $wpdb->get_results( $wpdb->prepare( $product_sql . 'ec_product.product_id IN ( ' . implode( ', ', array_fill( 0, count( $chunk_ids ), '%d' ) ) . ' ) GROUP BY ec_product.product_id ORDER BY ec_product.product_id ASC', $chunk_ids ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $product_sql is a static string; the only interpolation is a list of %d placeholders and the ids are prepare() arguments.
		if ( empty( $results ) ) {
			continue;
		}
	} else {
		/* Export all: keyset pagination on the primary key. */
		$results = $wpdb->get_results( $wpdb->prepare( $product_sql . 'ec_product.product_id > %d GROUP BY ec_product.product_id ORDER BY ec_product.product_id ASC LIMIT %d', $last_product_id, $chunk_size ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $product_sql is a static string; the values go through prepare().
		if ( empty( $results ) ) {
			break;
		}
	}

	if ( null === $keys ) {
		$keys = array_keys( $results[0] );
		$keys[] = 'advanced_option_ids';
		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, $keys );
	}

	$chunk_product_ids = array();
	foreach ( $results as $result ) {
		$chunk_product_ids[] = (int) $result['product_id'];
	}
	$last_product_id = max( $last_product_id, max( $chunk_product_ids ) );
	$chunk_placeholders = implode( ', ', array_fill( 0, count( $chunk_product_ids ), '%d' ) );

	/* Side tables, only for the products in this chunk. */
	$price_tiers_by_product = array();
	$price_tier_rows = $wpdb->get_results( $wpdb->prepare( 'SELECT product_id, quantity, price FROM ec_pricetier WHERE product_id IN ( ' . $chunk_placeholders . ' ) ORDER BY product_id ASC, quantity ASC', $chunk_product_ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- the only interpolation is a list of %d placeholders the sniff cannot see; the ids are prepare() arguments.
	if ( $price_tier_rows ) {
		foreach ( $price_tier_rows as $price_tier ) {
			$price_tiers_by_product[ (int) $price_tier->product_id ][] = $price_tier->quantity . ',' . $price_tier->price;
		}
	}

	$role_prices_by_product = array();
	$role_price_rows = $wpdb->get_results( $wpdb->prepare( 'SELECT product_id, role_label, role_price FROM ec_roleprice WHERE product_id IN ( ' . $chunk_placeholders . ' ) ORDER BY product_id ASC, role_label ASC', $chunk_product_ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- the only interpolation is a list of %d placeholders the sniff cannot see; the ids are prepare() arguments.
	if ( $role_price_rows ) {
		foreach ( $role_price_rows as $role_price ) {
			$role_prices_by_product[ (int) $role_price->product_id ][] = $role_price->role_label . ',' . $role_price->role_price;
		}
	}

	$advanced_options_by_product = array();
	$advanced_option_rows = $wpdb->get_results( $wpdb->prepare( 'SELECT product_id, option_id FROM ec_option_to_product WHERE product_id IN ( ' . $chunk_placeholders . ' ) ORDER BY product_id ASC, option_id ASC', $chunk_product_ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- the only interpolation is a list of %d placeholders the sniff cannot see; the ids are prepare() arguments.
	if ( $advanced_option_rows ) {
		foreach ( $advanced_option_rows as $advanced_option ) {
			$advanced_options_by_product[ (int) $advanced_option->product_id ][] = (int) $advanced_option->option_id;
		}
	}

	/* Write the chunk. */
	foreach ( $results as $result ) {
		$product_id = (int) $result['product_id'];
		$line = array();

		foreach ( $result as $key => $value ) {
			if ( 'price_tiers' === $key ) {
				$line[] = isset( $price_tiers_by_product[ $product_id ] ) ? wp_easycart_export_products_csv_normalize( implode( ',', $price_tiers_by_product[ $product_id ] ) ) : '';

			} elseif ( 'b2b_prices' === $key ) {
				$line[] = isset( $role_prices_by_product[ $product_id ] ) ? wp_easycart_export_products_csv_normalize( implode( ',', $role_prices_by_product[ $product_id ] ) ) : '';

			} elseif ( 'product_images' === $key ) {
				$product_images = ( isset( $value ) && is_string( $value ) && strlen( $value ) > 0 ) ? explode( ',', $value ) : array();
				$formatted_product_images = array();
				foreach ( $product_images as $product_image ) {
					if ( false === strpos( $product_image, ':' ) && 'image1' != $product_image && 'image2' != $product_image && 'image3' != $product_image && 'image4' != $product_image && 'image5' != $product_image ) {
						$formatted_product_images[] = 'ml:' . $product_image;
					} else {
						$formatted_product_images[] = $product_image;
					}
				}
				$line[] = wp_easycart_export_products_csv_normalize( htmlspecialchars_decode( implode( ',', $formatted_product_images ) ) );

			} else {
				$line[] = wp_easycart_export_products_csv_normalize( htmlspecialchars_decode( (string) $value ) );
			}
		}

		if ( ! $result['use_advanced_optionset'] && ! $result['use_both_option_types'] ) {
			$line[] = '';
		} else {
			$line[] = isset( $advanced_options_by_product[ $product_id ] ) ? implode( ',', $advanced_options_by_product[ $product_id ] ) : '';
		}

		fputcsv( $output, $line );
	}

	flush();

	if ( null === $id_chunks && count( $results ) < $chunk_size ) {
		break;
	}
}

if ( null === $keys ) {
	echo "\nno matching records found\n";
} else {
	fclose( $output );
}
die();
