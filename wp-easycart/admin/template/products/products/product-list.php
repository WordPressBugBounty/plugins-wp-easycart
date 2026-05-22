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

// Review prompt (unchanged from v1).
if ( ! get_option( 'ec_option_review_complete' ) ) {
?>
<div class="wp-easycart-admin-review-us-box">
	<?php esc_attr_e( 'Do you like WP EasyCart? If you do, please take a moment to', 'wp-easycart' ); ?> <a href="https://wordpress.org/support/plugin/wp-easycart/reviews/" target="_blank"><?php esc_attr_e( 'submit a review', 'wp-easycart' ); ?></a>, <?php esc_attr_e( 'it really helps us!', 'wp-easycart' ); ?>
	<div class="wp-easycart-admin-review-us-close" onclick="wp_easycart_admin_close_review( '<?php echo esc_attr( wp_create_nonce( 'wp-easycart-review-us' ) ); ?>' );"><div class="dashicons dashicons-no"></div></div>
</div>
<?php
}

$table = new wp_easycart_admin_product_table();
$table->setup();
$table->print_table();

// Load slideouts (unchanged from v1).
wp_easycart_admin()->load_new_slideout( 'product' );
wp_easycart_admin()->load_new_slideout( 'manufacturer' );
wp_easycart_admin()->load_new_slideout( 'optionset' );