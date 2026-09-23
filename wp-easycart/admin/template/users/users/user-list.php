<?php
/**
 * User (Customer) List Page - V2
 *
 * Uses the new wp_easycart_admin_user_table class which extends
 * wp_easycart_admin_table_v2. All configuration is encapsulated in
 * the user table class's setup() method.
 *
 * @since 5.9.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* 6.0.1: after a delete, the bar offering it back ( admin/inc/wp_easycart_admin_undo.php ). */
if ( class_exists( 'wp_easycart_admin_undo' ) ) {
	wp_easycart_admin_undo::maybe_print_bar( __( 'Customer deleted.', 'wp-easycart' ) );
}

/* 6.0.1: the shared review card ( admin/inc/wp_easycart_admin.php ). */
wp_easycart_admin_print_review_prompt();

if ( isset( $user_not_found ) && $user_not_found ) {
	echo '<div class="wp-easycart-admin-error-box">' . esc_html__( 'Customer not found. They may have been removed.', 'wp-easycart' ) . '</div>';
}

// PRO can render an Accounts / Guest Checkouts view switcher here.
do_action( 'wp_easycart_admin_user_list_views' );

$table = new wp_easycart_admin_user_table();
$table->setup();
$table->print_table();