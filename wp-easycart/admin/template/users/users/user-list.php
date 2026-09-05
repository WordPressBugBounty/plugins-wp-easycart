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

// Review prompt (unchanged from v1).
if ( ! get_option( 'ec_option_review_complete' ) ) {
?>
<div class="wp-easycart-admin-review-us-box">
	<?php esc_attr_e( 'Do you like WP EasyCart? If you do, please take a moment to', 'wp-easycart' ); ?> <a href="https://wordpress.org/support/plugin/wp-easycart/reviews/" target="_blank"><?php esc_attr_e( 'submit a review', 'wp-easycart' ); ?></a>, <?php esc_attr_e( 'it really helps us!', 'wp-easycart' ); ?>
	<div class="wp-easycart-admin-review-us-close" onclick="wp_easycart_admin_close_review( '<?php echo esc_attr( wp_create_nonce( 'wp-easycart-review-us' ) ); ?>' );"><div class="dashicons dashicons-no"></div></div>
</div>
<?php
}

if ( isset( $user_not_found ) && $user_not_found ) {
	echo '<div class="wp-easycart-admin-error-box">' . esc_html__( 'Customer not found. They may have been removed.', 'wp-easycart' ) . '</div>';
}

// PRO can render an Accounts / Guest Checkouts view switcher here.
do_action( 'wp_easycart_admin_user_list_views' );

$table = new wp_easycart_admin_user_table();
$table->setup();
$table->print_table();