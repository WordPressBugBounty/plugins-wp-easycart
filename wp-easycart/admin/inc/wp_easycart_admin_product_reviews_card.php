<?php
/**
 * Product editor — Customer reviews card AJAX ( the card itself is wp_easycart_admin_details_products_v2::print_reviews_v2() ).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! has_action( 'wp_ajax_ecdv2_reviews_toggle' ) ) :
/* Allow-reviews toggle on the product editor's Reviews card: saves immediately. */
add_action( 'wp_ajax_ecdv2_reviews_toggle', function() {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) { wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) ); }
	check_ajax_referer( 'wp-easycart-ecdv2-reviews-toggle', 'nonce' );
	global $wpdb; $pid = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0; $on = ! empty( $_POST['on'] ) && '0' !== $_POST['on'] ? 1 : 0;
	if ( ! $pid || ! $wpdb->get_var( $wpdb->prepare( 'SELECT product_id FROM ec_product WHERE product_id = %d', $pid ) ) ) { wp_send_json_error( array( 'message' => __( 'Product not found.', 'wp-easycart' ) ) ); }
	$wpdb->update( 'ec_product', array( 'use_customer_reviews' => $on ), array( 'product_id' => $pid ) );
	do_action( 'wp_easycart_product_updated', $pid ); wp_cache_flush();
	wp_send_json_success( array( 'on' => $on, 'message' => $on ? __( 'Customer reviews are on for this product.', 'wp-easycart' ) : __( 'Customer reviews are off for this product.', 'wp-easycart' ) ) );
} );
endif;
