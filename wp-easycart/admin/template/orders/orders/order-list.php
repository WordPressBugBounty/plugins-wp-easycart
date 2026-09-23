<?php
/**
 * Order List Page - V2
 *
 * Uses the new wp_easycart_admin_order_table class which extends
 * wp_easycart_admin_table_v2. All configuration is encapsulated in
 * the order table class's setup() method.
 *
 * The V1 GET form-action contract is preserved: bulk actions, exports,
 * printing, quick edit and the duplicate slideout all use the existing
 * handlers in wp_easycart_admin_orders (nonce: wp-easycart-bulk-orders).
 *
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* 6.0.1: after a delete, the bar offering it back ( admin/inc/wp_easycart_admin_undo.php ). */
if ( class_exists( 'wp_easycart_admin_undo' ) ) {
	wp_easycart_admin_undo::maybe_print_bar( __( 'Order deleted.', 'wp-easycart' ) );
}

/* 6.0.1: the shared review card ( admin/inc/wp_easycart_admin.php ). */
wp_easycart_admin_print_review_prompt();

$table = new wp_easycart_admin_order_table();
$table->setup();
$table->print_table();

// Load slideouts (unchanged from v1: quick edit + duplicate).
wp_easycart_admin()->load_new_slideout( 'order' );