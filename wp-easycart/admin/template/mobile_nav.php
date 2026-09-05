<?php
/**
 * WP EasyCart Admin Shell V2 — Mobile bottom tab bar.
 *
 * Replaces the old full-screen overlay menu. The full navigation tree is
 * now the sidebar itself, which becomes a slide-in drawer on mobile
 * (opened by the hamburger or the "More" tab). This partial only renders
 * the persistent bottom bar with the four primary destinations.
 *
 * Capability checks match left_nav.php; hidden tabs simply don't render,
 * and the bar flexes to fill.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ecsh_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
?>
<nav class="ecsh-bottom-nav" aria-label="<?php esc_attr_e( 'EasyCart quick navigation', 'wp-easycart' ); ?>">
	<?php if ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_reports' ) ) { ?>
	<a class="ecsh-bn-item<?php if ( 'wp-easycart-dashboard' === $ecsh_page ) { echo ' ecsh-active'; } ?>" href="admin.php?page=wp-easycart-dashboard">
		<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 3v18h18"/><path d="m7 14 4-4 3 3 5-6"/></svg>
		<?php esc_attr_e( 'Reports', 'wp-easycart' ); ?>
	</a>
	<?php } ?>

	<?php if ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_products' ) ) { ?>
	<a class="ecsh-bn-item<?php if ( 'wp-easycart-products' === $ecsh_page ) { echo ' ecsh-active'; } ?>" href="admin.php?page=wp-easycart-products&subpage=products">
		<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="M3.3 7 12 12l8.7-5"/><path d="M12 22V12"/></svg>
		<?php esc_attr_e( 'Products', 'wp-easycart' ); ?>
	</a>
	<?php } ?>

	<?php if ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_orders' ) ) { ?>
	<a class="ecsh-bn-item<?php if ( 'wp-easycart-orders' === $ecsh_page ) { echo ' ecsh-active'; } ?>" href="admin.php?page=wp-easycart-orders&subpage=orders">
		<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
		<?php esc_attr_e( 'Orders', 'wp-easycart' ); ?>
	</a>
	<?php } ?>

	<?php if ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_users' ) ) { ?>
	<a class="ecsh-bn-item<?php if ( 'wp-easycart-users' === $ecsh_page ) { echo ' ecsh-active'; } ?>" href="admin.php?page=wp-easycart-users&subpage=accounts">
		<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
		<?php esc_attr_e( 'Users', 'wp-easycart' ); ?>
	</a>
	<?php } ?>

	<button class="ecsh-bn-item ecsh-bn-more" type="button">
		<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="1"/><circle cx="5" cy="12" r="1"/><circle cx="19" cy="12" r="1"/></svg>
		<?php esc_attr_e( 'More', 'wp-easycart' ); ?>
	</button>
</nav>
