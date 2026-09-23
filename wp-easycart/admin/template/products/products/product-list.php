<?php
/**
 * Product List Page - V2
 *
 * Uses the new wp_easycart_admin_product_table class which extends
 * wp_easycart_admin_table_v2. All configuration is encapsulated in
 * the product table class's setup() method.
 *
 * @since 5.x.x
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* 6.0.1: after a delete, the bar offering it back ( admin/inc/wp_easycart_admin_undo.php ). */
if ( class_exists( 'wp_easycart_admin_undo' ) ) {
	wp_easycart_admin_undo::maybe_print_bar( __( 'Product deleted.', 'wp-easycart' ) );
}

/* 6.0.1: the shared review card ( admin/inc/wp_easycart_admin.php ). */
wp_easycart_admin_print_review_prompt();

$table = new wp_easycart_admin_product_table();
$table->setup();
$table->print_table();

// Load slideouts (unchanged from v1).
wp_easycart_admin()->load_new_slideout( 'product' );
wp_easycart_admin()->load_new_slideout( 'manufacturer' );
wp_easycart_admin()->load_new_slideout( 'optionset' );