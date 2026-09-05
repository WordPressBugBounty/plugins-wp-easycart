<?php
/**
 * Registration — support & updates expired, V2 ( replaces registration-expired.php ).
 *
 * Same three-path layout as the other registration screens: renew ( featured ),
 * activate a different key, and the license details with deactivate.
 *
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ecre_license = function_exists( 'wp_easycart_admin_license' ) ? wp_easycart_admin_license()->license_data : null;
$ecre_info    = get_option( 'wp_easycart_license_info' );
$ecre_key     = ( is_array( $ecre_info ) && isset( $ecre_info['transaction_key'] ) ) ? $ecre_info['transaction_key'] : '';
$ecre_email   = ( is_array( $ecre_info ) && isset( $ecre_info['customer_email'] ) ) ? $ecre_info['customer_email'] : '';
$ecre_premium = ( $ecre_license && isset( $ecre_license->model_number ) && 'ec410' === strtolower( trim( (string) $ecre_license->model_number ) ) );
$ecre_end_ts  = ( $ecre_license && ! empty( $ecre_license->support_end_date ) ) ? strtotime( $ecre_license->support_end_date ) : 0;
$ecre_renew   = '' !== $ecre_key
	? ( $ecre_premium ? 'https://www.wpeasycart.com/products/wp-easycart-premium-support-extensions/?transaction_key=' . rawurlencode( $ecre_key ) : 'https://www.wpeasycart.com/products/wp-easycart-professional-support-upgrades/?transaction_key=' . rawurlencode( $ecre_key ) )
	: 'https://www.wpeasycart.com/my-account/';
$ecre_activate = self_admin_url( 'admin.php?page=wp-easycart-registration&subpage=registration&ec_action=activateregistration' );
$ecre_deact    = self_admin_url( 'admin.php?page=wp-easycart-registration&subpage=registration&ec_action=deactivateregistration' );
$ecre_stats    = class_exists( 'wp_easycart_admin_upsell' ) ? wp_easycart_admin_upsell::stats() : array();
?>
<div class="ecv2-wrap ecreg ecreg-expired">

	<div class="ecv2-page-header">
		<div class="ecv2-page-header-left">
			<span class="dashicons dashicons-lock ecv2-page-header-icon"></span>
			<h2 class="ecv2-page-title"><?php esc_html_e( 'Support & updates have expired', 'wp-easycart' ); ?></h2>
		</div>
		<div class="ecv2-page-header-right">
			<a href="<?php echo esc_url( wp_easycart_admin()->helpsystem->print_docs_url( 'settings', 'registration', 'expired' ) ); ?>" target="_blank" class="ecv2-btn ecv2-btn-ghost ecv2-btn-sm"><span class="dashicons dashicons-editor-help"></span> <?php esc_html_e( 'Help', 'wp-easycart' ); ?></a>
		</div>
	</div>

	<div class="ecreg-notice is-ended">
		<span class="dashicons dashicons-warning"></span>
		<span><strong><?php echo $ecre_end_ts ? esc_html( sprintf( __( 'Your license lapsed on %s.', 'wp-easycart' ), date_i18n( get_option( 'date_format' ), $ecre_end_ts ) ) ) : esc_html__( 'Your license has lapsed.', 'wp-easycart' ); ?></strong> <?php esc_html_e( 'The PRO panels are locked until support & updates are renewed. Orders, products and settings are untouched and your store keeps selling.', 'wp-easycart' ); ?></span>
	</div>

	<div class="ecreg-paths">

		<div class="ecreg-path is-featured">
			<span class="ecreg-path-tag"><?php esc_html_e( 'Reopens everything', 'wp-easycart' ); ?></span>
			<span class="dashicons dashicons-update ecreg-path-icon"></span>
			<h3><?php esc_html_e( 'Renew support & updates', 'wp-easycart' ); ?></h3>
			<p><?php esc_html_e( 'One renewal reopens every PRO panel on this site within a few minutes, and brings back security fixes, new features and priority support for a year.', 'wp-easycart' ); ?></p>
			<ul class="ecreg-path-list">
				<li><?php esc_html_e( 'Same key, same site — nothing to re-enter', 'wp-easycart' ); ?></li>
				<li><?php esc_html_e( 'All PRO data you set up is still here', 'wp-easycart' ); ?></li>
				<li><?php esc_html_e( 'Sign in with the account that bought the license', 'wp-easycart' ); ?></li>
			</ul>
			<a class="ecv2-btn ecv2-btn-primary ecreg-path-cta" href="<?php echo esc_url( $ecre_renew ); ?>" target="_blank"><?php esc_html_e( 'Renew now', 'wp-easycart' ); ?></a>
			<span class="ecreg-path-fine"><?php esc_html_e( 'Already renewed? Allow a few minutes, then reload this page.', 'wp-easycart' ); ?></span>
		</div>

		<div class="ecreg-path">
			<span class="dashicons dashicons-admin-network ecreg-path-icon"></span>
			<h3><?php esc_html_e( 'Use a different key', 'wp-easycart' ); ?></h3>
			<p><?php esc_html_e( 'Bought a new license instead? Enter it here and it replaces the expired one on this site.', 'wp-easycart' ); ?></p>
			<form action="<?php echo esc_url( $ecre_activate ); ?>" method="POST" id="wpeasycart_admin_form1" class="ecreg-form" novalidate="novalidate">
				<div class="ecreg-f"><label for="customername"><?php esc_html_e( 'Full name', 'wp-easycart' ); ?></label><input type="text" class="ecv2-input" name="customername" id="customername" required="required" autocomplete="name" /></div>
				<div class="ecreg-f"><label for="customeremail"><?php esc_html_e( 'Email address', 'wp-easycart' ); ?></label><input type="email" class="ecv2-input" name="customeremail" id="customeremail" required="required" autocomplete="email" value="<?php echo esc_attr( $ecre_email ); ?>" /></div>
				<div class="ecreg-f"><label for="transactionkey"><?php esc_html_e( 'License key', 'wp-easycart' ); ?></label><input type="text" class="ecv2-input ecreg-mono" name="transactionkey" id="transactionkey" required="required" autocomplete="off" spellcheck="false" placeholder="XXXX-XXXX-XXXX-XXXX" /></div>
				<button type="submit" class="ecv2-btn ecv2-btn-primary ecreg-path-cta"><?php esc_html_e( 'Activate license', 'wp-easycart' ); ?></button>
			</form>
		</div>

		<div class="ecreg-path">
			<span class="dashicons dashicons-info-outline ecreg-path-icon"></span>
			<h3><?php esc_html_e( 'This license', 'wp-easycart' ); ?></h3>
			<dl class="ecreg-dl">
				<dt><?php esc_html_e( 'Edition', 'wp-easycart' ); ?></dt><dd><?php echo $ecre_premium ? esc_html__( 'Premium', 'wp-easycart' ) : esc_html__( 'PRO', 'wp-easycart' ); ?></dd>
				<dt><?php esc_html_e( 'Registered URL', 'wp-easycart' ); ?></dt><dd><?php echo esc_html( ( $ecre_license && isset( $ecre_license->siteurl ) ) ? $ecre_license->siteurl : home_url() ); ?></dd>
				<dt><?php esc_html_e( 'Support ended', 'wp-easycart' ); ?></dt><dd><?php echo $ecre_end_ts ? esc_html( date_i18n( get_option( 'date_format' ), $ecre_end_ts ) ) : '—'; ?></dd>
				<?php if ( '' !== $ecre_key ) : ?><dt><?php esc_html_e( 'License key', 'wp-easycart' ); ?></dt><dd class="ecreg-mono"><?php echo esc_html( $ecre_key ); ?></dd><?php endif; ?>
			</dl>
			<p class="ecreg-path-fine"><?php esc_html_e( 'Prefer the free edition? Deactivate the key here so it can be used elsewhere, or simply leave it — the store keeps running either way.', 'wp-easycart' ); ?></p>
			<form action="<?php echo esc_url( $ecre_deact ); ?>" method="POST" id="wpeasycart_admin_form2" class="ecreg-form" novalidate="novalidate" onsubmit="return window.confirm( <?php echo wp_json_encode( __( 'Deactivate the license on this site?', 'wp-easycart' ) ); ?> );">
				<div class="ecreg-f"><label for="transactionkey_deact"><?php esc_html_e( 'Confirm the license key', 'wp-easycart' ); ?></label><input type="text" class="ecv2-input ecreg-mono" name="transactionkey" id="transactionkey_deact" required="required" autocomplete="off" spellcheck="false" /></div>
				<button type="submit" class="ecv2-btn ecv2-btn-sm ecreg-danger"><?php esc_html_e( 'Deactivate on this site', 'wp-easycart' ); ?></button>
			</form>
		</div>

	</div>

	<?php if ( ! empty( $ecre_stats['orders_30d'] ) && $ecre_stats['orders_30d'] >= 3 ) : ?>
	<p class="ecreg-stat"><span class="dashicons dashicons-chart-line"></span>
		<?php echo esc_html( sprintf( __( '%s orders in the last 30 days went through the PRO features you set up — offers, subscriptions, live rates. Renewing keeps that in place.', 'wp-easycart' ), number_format_i18n( $ecre_stats['orders_30d'] ) ) ); ?>
	</p>
	<?php endif; ?>

</div>