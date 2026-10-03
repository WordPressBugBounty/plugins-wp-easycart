<?php
/**
 * WP EasyCart Admin Shell V2 — Left navigation.
 *
 * Structure per section:
 *   <div class="ecsh-sb-item [ecsh-active]"> <a>icon + label [+badge]</a> [chevron button] </div>
 *   <div class="ecsh-sb-sub [ecsh-open]" id="..."> submenu links </div>
 *
 * All capability checks, subpage matching (incl. multi-subpage groups like
 * option|optionitems), the wp_easycart_admin_lock_icon filter and the
 * wp_easycart_main_nav_left_end action are preserved from V1. Store schedule
 * and store locations are one "Schedule & Locations" entry ( 6.0.0; the old
 * wp_easycart_enable_multiple_locations filter that hid the locations link was
 * never set by anything ).
 * The six duplicated store-status license blocks are replaced by
 * wp_easycart_shell_store_status().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ecsh_page    = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
$ecsh_subpage = isset( $_GET['subpage'] ) ? sanitize_text_field( wp_unslash( $_GET['subpage'] ) ) : '';
$ecsh_status  = wp_easycart_shell_store_status();

/** Convenience: submenu link with current-state + optional lock icon. */
function ecsh_nav_sublink( $href, $label, $is_current, $locked = false ) {
	echo '<a href="' . esc_attr( $href ) . '"' . ( $is_current ? ' class="ecsh-current"' : '' ) . '>' . esc_html( $label );
	if ( $locked ) {
		$tip = class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::included_text( 'pro' ) : '';
		echo wp_easycart_escape_html( apply_filters( 'wp_easycart_admin_lock_icon', ' <span class="dashicons dashicons-lock" title="' . esc_attr( $tip ) . '" style="color:#FC0; float:right;"></span>' ) );
	}
	echo '</a>' . "\n";
}
?>

<!--REPORTS-->
<?php if ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_reports' ) ) { ?>
<div class="ecsh-sb-item<?php if ( 'wp-easycart-dashboard' === $ecsh_page ) { echo ' ecsh-active'; } ?>">
	<a href="admin.php?page=wp-easycart-dashboard">
		<svg class="ecsh-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 3v18h18"/><path d="m7 14 4-4 3 3 5-6"/></svg>
		<?php esc_attr_e( 'Reports', 'wp-easycart' ); ?>
	</a>
</div>
<?php } ?>

<!--STORE STATUS-->
<?php if ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_store_status' ) ) { ?>
<div class="ecsh-sb-item<?php if ( 'wp-easycart-license-status' === $ecsh_page ) { echo ' ecsh-active'; } ?>">
	<a href="admin.php?page=wp-easycart-license-status">
		<svg class="ecsh-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Z"/><path d="M12 8v4l2.5 2.5"/></svg>
		<?php esc_attr_e( 'Store Status', 'wp-easycart' ); ?>
		<?php if ( 'ok' !== $ecsh_status['level'] ) { ?>
		<span class="ecsh-badge ecsh-<?php echo esc_attr( $ecsh_status['level'] ); ?>"><?php echo esc_attr( $ecsh_status['days_left'] ); ?>d</span>
		<?php } ?>
	</a>
</div>
<?php } ?>

<!--PRODUCTS-->
<?php if ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_products' ) ) {
	$ecsh_in = ( 'wp-easycart-products' === $ecsh_page ); ?>
<div class="ecsh-sb-item<?php if ( $ecsh_in ) { echo ' ecsh-active'; } ?>">
	<a href="admin.php?page=wp-easycart-products&subpage=products">
		<svg class="ecsh-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="M3.3 7 12 12l8.7-5"/><path d="M12 22V12"/></svg>
		<?php esc_attr_e( 'Products', 'wp-easycart' ); ?>
	</a>
	<button class="ecsh-sb-chev" type="button" data-target="#ecsh_sub_products" aria-expanded="<?php echo $ecsh_in ? 'true' : 'false'; ?>" aria-label="<?php esc_attr_e( 'Toggle Products menu', 'wp-easycart' ); ?>">
		<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
	</button>
</div>
<div class="ecsh-sb-sub<?php if ( $ecsh_in ) { echo ' ecsh-open'; } ?>" id="ecsh_sub_products">
	<div class="ecsh-sb-sub-inner">
		<?php
		ecsh_nav_sublink( 'admin.php?page=wp-easycart-products&subpage=products', __( 'Products', 'wp-easycart' ), $ecsh_in && ( 'products' === $ecsh_subpage || '' === $ecsh_subpage ) );
		ecsh_nav_sublink( 'admin.php?page=wp-easycart-products&subpage=inventory', __( 'Inventory', 'wp-easycart' ), 'inventory' === $ecsh_subpage );
		ecsh_nav_sublink( 'admin.php?page=wp-easycart-products&subpage=option', __( 'Option Sets', 'wp-easycart' ), in_array( $ecsh_subpage, array( 'option', 'optionitems' ), true ) );
		ecsh_nav_sublink( 'admin.php?page=wp-easycart-products&subpage=category', __( 'Categories', 'wp-easycart' ), in_array( $ecsh_subpage, array( 'category', 'category-products', 'category-products-manage' ), true ) );
		ecsh_nav_sublink( 'admin.php?page=wp-easycart-products&subpage=menus', __( 'Menus', 'wp-easycart' ), in_array( $ecsh_subpage, array( 'menus', 'submenus', 'subsubmenus' ), true ) );
		ecsh_nav_sublink( 'admin.php?page=wp-easycart-products&subpage=manufacturers', __( 'Manufacturers', 'wp-easycart' ), 'manufacturers' === $ecsh_subpage );
		ecsh_nav_sublink( 'admin.php?page=wp-easycart-products&subpage=reviews', __( 'Product Reviews', 'wp-easycart' ), 'reviews' === $ecsh_subpage );
		ecsh_nav_sublink( 'admin.php?page=wp-easycart-products&subpage=subscriptionplans', __( 'Subscription Plans', 'wp-easycart' ), 'subscriptionplans' === $ecsh_subpage, true );
		?>
	</div>
</div>
<?php } ?>

<!--ORDERS-->
<?php if ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_orders' ) ) {
	$ecsh_in = ( 'wp-easycart-orders' === $ecsh_page ); ?>
<div class="ecsh-sb-item<?php if ( $ecsh_in ) { echo ' ecsh-active'; } ?>">
	<a href="admin.php?page=wp-easycart-orders&subpage=orders">
		<svg class="ecsh-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
		<?php esc_attr_e( 'Orders', 'wp-easycart' ); ?>
	</a>
	<button class="ecsh-sb-chev" type="button" data-target="#ecsh_sub_orders" aria-expanded="<?php echo $ecsh_in ? 'true' : 'false'; ?>" aria-label="<?php esc_attr_e( 'Toggle Orders menu', 'wp-easycart' ); ?>">
		<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
	</button>
</div>
<div class="ecsh-sb-sub<?php if ( $ecsh_in ) { echo ' ecsh-open'; } ?>" id="ecsh_sub_orders">
	<div class="ecsh-sb-sub-inner">
		<?php
		ecsh_nav_sublink( 'admin.php?page=wp-easycart-orders&subpage=orders', __( 'Orders', 'wp-easycart' ), $ecsh_in && ( 'orders' === $ecsh_subpage || '' === $ecsh_subpage ) );
		ecsh_nav_sublink( 'admin.php?page=wp-easycart-orders&subpage=subscriptions', __( 'Subscriptions', 'wp-easycart' ), 'subscriptions' === $ecsh_subpage, true );
		ecsh_nav_sublink( 'admin.php?page=wp-easycart-orders&subpage=downloads', __( 'Manage Downloads', 'wp-easycart' ), 'downloads' === $ecsh_subpage, true );
		?>
	</div>
</div>
<?php } ?>

<!--USERS-->
<?php if ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_users' ) ) {
	$ecsh_in = ( 'wp-easycart-users' === $ecsh_page ); ?>
<div class="ecsh-sb-item<?php if ( $ecsh_in ) { echo ' ecsh-active'; } ?>">
	<a href="admin.php?page=wp-easycart-users&subpage=accounts">
		<svg class="ecsh-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
		<?php esc_attr_e( 'Users', 'wp-easycart' ); ?>
	</a>
	<button class="ecsh-sb-chev" type="button" data-target="#ecsh_sub_users" aria-expanded="<?php echo $ecsh_in ? 'true' : 'false'; ?>" aria-label="<?php esc_attr_e( 'Toggle Users menu', 'wp-easycart' ); ?>">
		<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
	</button>
</div>
<div class="ecsh-sb-sub<?php if ( $ecsh_in ) { echo ' ecsh-open'; } ?>" id="ecsh_sub_users">
	<div class="ecsh-sb-sub-inner">
		<?php
		ecsh_nav_sublink( 'admin.php?page=wp-easycart-users&subpage=accounts', __( 'User Accounts', 'wp-easycart' ), $ecsh_in && ( 'accounts' === $ecsh_subpage || '' === $ecsh_subpage ) );
		ecsh_nav_sublink( 'admin.php?page=wp-easycart-users&subpage=user-roles', __( 'User Roles', 'wp-easycart' ), 'user-roles' === $ecsh_subpage );
		ecsh_nav_sublink( 'admin.php?page=wp-easycart-users&subpage=subscribers', __( 'Subscribers', 'wp-easycart' ), 'subscribers' === $ecsh_subpage );
		?>
	</div>
</div>
<?php } ?>

<!--MARKETING-->
<?php if ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_marketing' ) ) {
	$ecsh_in = ( 'wp-easycart-rates' === $ecsh_page ); ?>
<div class="ecsh-sb-item<?php if ( $ecsh_in ) { echo ' ecsh-active'; } ?>">
	<a href="admin.php?page=wp-easycart-rates&subpage=offers">
		<svg class="ecsh-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>
		<?php esc_attr_e( 'Marketing', 'wp-easycart' ); ?>
	</a>
	<button class="ecsh-sb-chev" type="button" data-target="#ecsh_sub_marketing" aria-expanded="<?php echo $ecsh_in ? 'true' : 'false'; ?>" aria-label="<?php esc_attr_e( 'Toggle Marketing menu', 'wp-easycart' ); ?>">
		<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
	</button>
</div>
<div class="ecsh-sb-sub<?php if ( $ecsh_in ) { echo ' ecsh-open'; } ?>" id="ecsh_sub_marketing">
	<div class="ecsh-sb-sub-inner">
		<?php
		/* Offers, Abandoned Cart and Gift Cards are PRO features: pass
		 * locked=true like Subscription Plans does. The lock renders through
		 * the wp_easycart_admin_lock_icon filter, which the PRO plugin
		 * blanks on licensed installs — so paid users never see it and no
		 * license check is needed here. Cart Links stays free (no lock). */
		ecsh_nav_sublink( 'admin.php?page=wp-easycart-rates&subpage=offers', __( 'Offers', 'wp-easycart' ), $ecsh_in && ( 'offers' === $ecsh_subpage || '' === $ecsh_subpage ), true );
		ecsh_nav_sublink( 'admin.php?page=wp-easycart-rates&subpage=cart-links', __( 'Cart Links', 'wp-easycart' ), 'cart-links' === $ecsh_subpage );
		ecsh_nav_sublink( 'admin.php?page=wp-easycart-rates&subpage=abandon-cart', __( 'Abandoned Cart', 'wp-easycart' ), 'abandon-cart' === $ecsh_subpage, true );
		ecsh_nav_sublink( 'admin.php?page=wp-easycart-rates&subpage=gift-cards', __( 'Gift Cards', 'wp-easycart' ), 'gift-cards' === $ecsh_subpage, true );
		?>
	</div>
</div>
<?php } ?>

<!--SETTINGS-->
<?php if ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_settings' ) ) {
	$ecsh_in = ( 'wp-easycart-settings' === $ecsh_page ); ?>
<div class="ecsh-sb-item<?php if ( $ecsh_in ) { echo ' ecsh-active'; } ?>">
	<a href="admin.php?page=wp-easycart-settings&subpage=home">
		<svg class="ecsh-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
		<?php esc_attr_e( 'Settings', 'wp-easycart' ); ?>
	</a>
	<button class="ecsh-sb-chev" type="button" data-target="#ecsh_sub_settings" aria-expanded="<?php echo $ecsh_in ? 'true' : 'false'; ?>" aria-label="<?php esc_attr_e( 'Toggle Settings menu', 'wp-easycart' ); ?>">
		<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
	</button>
</div>
<div class="ecsh-sb-sub<?php if ( $ecsh_in ) { echo ' ecsh-open'; } ?>" id="ecsh_sub_settings">
	<div class="ecsh-sb-sub-inner">
		<?php
		/* 6.0.2: one list for the sidebar and the Settings home ( wp_easycart_admin_settings_home::navigation() ): eight groups, the
		   one holding this page open, the others open on a click. A page joins by declaring its group and order. */
		$ecsh_settings_nav  = class_exists( 'wp_easycart_admin_settings_home' ) && method_exists( 'wp_easycart_admin_settings_home', 'navigation' ) ? wp_easycart_admin_settings_home::navigation() : array();
		$ecsh_settings_here = ( $ecsh_in && $ecsh_settings_nav ) ? wp_easycart_admin_settings_home::current_page( $ecsh_subpage, $ecsh_settings_nav ) : '';
		ecsh_nav_sublink( 'admin.php?page=wp-easycart-settings&subpage=home', __( 'All settings', 'wp-easycart' ), $ecsh_in && ( 'home' === $ecsh_subpage || '' === $ecsh_subpage ) );
		foreach ( $ecsh_settings_nav as $ecsh_group_slug => $ecsh_group ) {
			$ecsh_group_open = ( '' !== $ecsh_settings_here && isset( $ecsh_group['pages'][ $ecsh_settings_here ] ) );
			$ecsh_group_id   = 'ecsh_settings_' . sanitize_html_class( $ecsh_group_slug );
			?>
		<div class="ecsh-sb-group<?php echo $ecsh_group_open ? ' ecsh-open' : ''; ?>">
			<button type="button" class="ecsh-sb-group-head" aria-expanded="<?php echo $ecsh_group_open ? 'true' : 'false'; ?>" aria-controls="<?php echo esc_attr( $ecsh_group_id ); ?>">
				<span><?php echo esc_html( $ecsh_group['label'] ); ?></span>
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
			</button>
			<div class="ecsh-sb-group-pages" id="<?php echo esc_attr( $ecsh_group_id ); ?>" role="group" aria-label="<?php echo esc_attr( $ecsh_group['label'] ); ?>">
				<div class="ecsh-sb-group-inner">
					<?php
					foreach ( $ecsh_group['pages'] as $ecsh_page_slug => $ecsh_entry ) {
						ecsh_nav_sublink( $ecsh_entry['url'], $ecsh_entry['title'], $ecsh_settings_here === $ecsh_page_slug, '' !== $ecsh_entry['plan'] );
					}
					?>
				</div>
			</div>
		</div>
			<?php
		}
		?>
	</div>
</div>
<?php } ?>

<!--EXTENSIONS ( 6.0.2 ): the catalog for every store; WP EasyCart Premium adds an entry per installed extension. -->
<?php if ( ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_manager' ) ) && class_exists( 'wp_easycart_admin_extensions' ) ) {
	$ecsh_in        = ( wp_easycart_admin_extensions::PAGE === $ecsh_page );
	$ecsh_ext_items = wp_easycart_admin_extensions::nav_items(); ?>
<div class="ecsh-sb-item<?php if ( $ecsh_in ) { echo ' ecsh-active'; } ?>">
	<a href="<?php echo esc_url( wp_easycart_admin_extensions::url() ); ?>">
		<svg class="ecsh-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19.44 7.85c-.05.32.06.65.29.88l1.57 1.57c.47.47.7 1.09.7 1.7s-.23 1.24-.7 1.71l-1.61 1.61a.98.98 0 0 1-.84.28c-.47-.07-.8-.48-.97-.93a2.5 2.5 0 1 0-3.21 3.21c.45.17.86.5.93.97a.98.98 0 0 1-.28.84l-1.61 1.61a2.4 2.4 0 0 1-3.41 0l-1.57-1.57a1.03 1.03 0 0 0-.87-.29c-.5.07-.84.5-1.02.97a2.5 2.5 0 1 1-3.24-3.24c.46-.18.9-.53.97-1.02a1.03 1.03 0 0 0-.29-.87L2.7 13.7a2.4 2.4 0 0 1 0-3.4l1.53-1.53c.24-.24.58-.35.92-.3.51.08.88.53 1.07 1.01a2.5 2.5 0 1 0 3.26-3.26c-.48-.2-.93-.56-1.01-1.07-.05-.34.06-.68.3-.92L10.3 2.7a2.4 2.4 0 0 1 3.4 0l1.57 1.57c.23.23.56.34.88.29.49-.07.84-.5 1.02-.97a2.5 2.5 0 1 1 3.24 3.24c-.46.18-.9.53-.97 1.02Z"/></svg>
		<?php esc_attr_e( 'Extensions', 'wp-easycart' ); ?>
	</a>
	<?php if ( $ecsh_ext_items ) { ?>
	<button class="ecsh-sb-chev" type="button" data-target="#ecsh_sub_extensions" aria-expanded="<?php echo $ecsh_in ? 'true' : 'false'; ?>" aria-label="<?php esc_attr_e( 'Toggle Extensions menu', 'wp-easycart' ); ?>">
		<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
	</button>
	<?php } ?>
</div>
<?php if ( $ecsh_ext_items ) { ?>
<div class="ecsh-sb-sub<?php if ( $ecsh_in ) { echo ' ecsh-open'; } ?>" id="ecsh_sub_extensions">
	<div class="ecsh-sb-sub-inner">
		<?php
		ecsh_nav_sublink( wp_easycart_admin_extensions::url(), __( 'All extensions', 'wp-easycart' ), $ecsh_in && '' === $ecsh_subpage );
		foreach ( $ecsh_ext_items as $ecsh_ext_item ) {
			ecsh_nav_sublink( $ecsh_ext_item['url'], $ecsh_ext_item['label'], $ecsh_in && ! empty( $ecsh_ext_item['current'] ) );
		}
		?>
	</div>
</div>
<?php } ?>
<?php } ?>

<!--DIAGNOSTICS-->
<?php if ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_diagnostics' ) ) { ?>
<div class="ecsh-sb-item<?php if ( 'wp-easycart-status' === $ecsh_page ) { echo ' ecsh-active'; } ?>">
	<a href="admin.php?page=wp-easycart-status&subpage=store-status">
		<svg class="ecsh-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m14.7 6.3 3 3L21 6l-3-3-3.3 3.3zM3 21l8.7-8.7"/><path d="m14.7 6.3-9.4 9.4a2 2 0 0 0 0 2.8l.2.2a2 2 0 0 0 2.8 0l9.4-9.4"/></svg>
		<?php esc_attr_e( 'Diagnostics', 'wp-easycart' ); ?>
	</a>
</div>
<?php } ?>

<!--REGISTRATION-->
<?php if ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_registration' ) ) { ?>
<div class="ecsh-sb-item<?php if ( 'wp-easycart-registration' === $ecsh_page ) { echo ' ecsh-active'; } ?>">
	<a href="admin.php?page=wp-easycart-registration&subpage=registration">
		<svg class="ecsh-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect width="18" height="11" x="3" y="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
		<?php esc_attr_e( 'Registration', 'wp-easycart' ); ?><?php echo wp_easycart_escape_html( apply_filters( 'wp_easycart_admin_lock_icon', ' <span class="dashicons dashicons-lock" style="color:#FC0; float:right;"></span>' ) ); ?>
	</a>
</div>
<?php } ?>

<?php do_action( 'wp_easycart_main_nav_left_end' ); ?>