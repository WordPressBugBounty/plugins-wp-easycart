<?php
/**
 * Products › Import ( wp_easycart_admin_import::load_page() ).
 *
 * The page shell; admin/js/import-v2.js draws the steps ( source, choices, trial, progress, report ) from the data
 * wp_easycart_admin_import::script_data() hands it.
 *
 * @package WP_EasyCart
 * @since   6.0.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="ecv2-wrap ecimp2" id="ecimp2">

	<div class="ecv2-page-header">
		<div class="ecv2-page-header-left">
			<span class="dashicons dashicons-upload ecv2-page-header-icon" aria-hidden="true"></span>
			<div class="ecv2-page-header-text">
				<h1 class="ecv2-page-title"><?php esc_html_e( 'Import', 'wp-easycart' ); ?></h1>
				<p class="ecv2-page-subline"><?php esc_html_e( 'Bring your products in from another store. Nothing changes until you start an import.', 'wp-easycart' ); ?></p>
			</div>
		</div>
		<div class="ecv2-page-header-right">
			<a class="ecv2-btn ecv2-btn-ghost ecv2-btn-sm" href="<?php echo esc_url( admin_url( 'admin.php?page=wp-easycart-products&subpage=products' ) ); ?>"><span class="dashicons dashicons-products" aria-hidden="true"></span> <span class="ecv2-btn-label"><?php esc_html_e( 'Products', 'wp-easycart' ); ?></span></a>
		</div>
	</div>

	<?php if ( ! wp_easycart_import::ready() ) : ?>
		<div class="ecimp2-notice is-warn" role="status"><?php esc_html_e( 'Finish the WP EasyCart database update first ( Store Status ), then import.', 'wp-easycart' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-easycart-settings&subpage=store-status' ) ); ?>"><?php esc_html_e( 'Open Store Status', 'wp-easycart' ); ?></a></div>
	<?php endif; ?>

	<div id="ecimp2-app" class="ecimp2-app" aria-live="polite">
		<p class="ecimp2-loading"><?php esc_html_e( 'Loading…', 'wp-easycart' ); ?></p>
	</div>
	<noscript><div class="ecimp2-notice is-warn"><?php esc_html_e( 'The importer needs JavaScript. Switch it on in your browser and reload this page.', 'wp-easycart' ); ?></div></noscript>
</div>
