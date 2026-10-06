<?php
/**
 * WP EasyCart — what is left of the classic cart importer ( 6.0.3 ).
 *
 * Importing moved to Products › Import ( admin/inc/wp_easycart_admin_import.php and the import framework in
 * inc/classes/core/import/ ): the WooCommerce and Square engines, their AJAX handlers, admin/js/cart-importer.js and the
 * osCommerce importer are gone. The Shopify precheck stays: it still guards the Shopify import handlers of a WP EasyCart
 * PRO older than 6.0.3.
 *
 * @package WP_EasyCart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'ecv2_shopify_import_precheck' ) ) {
	/**
	 * Shopify import requests, answered by WP EasyCart PRO ( ec_admin_ajax_shopify_import_* ). This runs first and ends
	 * any request from a user who cannot manage settings or without the Integrations page's nonce, so a store still on a
	 * PRO older than 6.0.2 is covered too. The answer is the { has_errors } admin/js/cart-importer.js reads.
	 *
	 * @since 6.0.2
	 * @return void
	 */
	function ecv2_shopify_import_precheck() {
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_settings' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- EasyCart's own roles register this capability.
			$error = __( 'Permission denied.', 'wp-easycart' );
		} elseif ( ! check_ajax_referer( 'wp-easycart-shopify-import', 'wp_easycart_nonce', false ) ) {
			$error = __( 'Your session has expired. Reload the page and try again.', 'wp-easycart' );
		} else {
			return;
		}
		echo wp_json_encode(
			array(
				'has_errors' => true,
				'message'    => $error,
			)
		);
		die();
	}
}
add_action( 'wp_ajax_ec_admin_ajax_shopify_import_products', 'ecv2_shopify_import_precheck', 1 );
add_action( 'wp_ajax_ec_admin_ajax_shopify_import_users', 'ecv2_shopify_import_precheck', 1 );
add_action( 'wp_ajax_ec_admin_ajax_shopify_import_categories', 'ecv2_shopify_import_precheck', 1 );
