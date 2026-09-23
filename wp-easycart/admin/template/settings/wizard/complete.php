<?php
/**
 * Step 5 — Done. Launch checklist + secondary actions.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$wizard = wp_easycart_admin_setup_wizard();
$upsell = $wizard->show_upsell();
$demo_installed = (bool) get_option( 'ec_option_demo_data_installed' );
$store_url = wp_easycart_admin()->store_page;
$has_woo    = class_exists( 'WooCommerce' );
$has_square = ( '' != get_option( 'ec_option_square_access_token' ) );
?>
<div class="ecwz-body">
	<div class="ecwz-done-hero">
		<div class="ecwz-done-big"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg></div>
		<div>
			<h2><?php esc_html_e( 'Your store is set up', 'wp-easycart' ); ?></h2>
			<p><?php echo sprintf( esc_html__( 'A few launch tasks remain. This list stays on %s until every item is done, so nothing gets lost when you leave this page.', 'wp-easycart' ), '<strong>' . esc_html__( 'Store Status', 'wp-easycart' ) . '</strong>' ); ?></p>
		</div>
	</div>

	<div class="ecwz-done-grid">
		<div>
			<?php wp_easycart_admin_verification()->print_nonce_field( 'wp_easycart_demo_settings_nonce', 'wp-easycart-initial-setup-demo-setup' ); ?>
			<?php $wizard->render_checklist( 'wizard' ); ?>
		</div>

		<div class="ecwz-ncards">
			<?php if ( ! $demo_installed ) { ?>
			<div class="ecwz-ncard" id="easycart_wizard_demo_data">
				<h4><?php esc_html_e( 'Just trying it out?', 'wp-easycart' ); ?></h4>
				<p><?php esc_html_e( 'Install demo products, categories and options so you can see a working store immediately. Remove them any time.', 'wp-easycart' ); ?></p>
				<a class="ecwz-btn ecwz-btn-sm" href="admin.php?page=wp-easycart-settings&subpage=initial-setup&action=easycart-install-demo-data" id="ecwz_demo_btn" data-ecwz-demo="1" data-busy="<?php esc_attr_e( 'Installing demo data…', 'wp-easycart' ); ?>" data-fail="<?php esc_attr_e( 'The demo data could not be installed. Please try again.', 'wp-easycart' ); ?>"><?php esc_html_e( 'Install demo data', 'wp-easycart' ); ?></a>
			</div>
			<div class="ecwz-ncard" id="easycart_wizard_demo_data_done" style="display:none">
				<h4><?php esc_html_e( 'Demo data installed', 'wp-easycart' ); ?> <span class="ecwz-badge ecwz-badge-green">&#10003;</span></h4>
				<p><?php esc_html_e( 'You are all set. Take a look at your store with sample products in it.', 'wp-easycart' ); ?></p>
				<a class="ecwz-btn ecwz-btn-sm" href="<?php echo esc_url( $store_url ); ?>" target="_blank"><?php esc_html_e( 'View your store', 'wp-easycart' ); ?></a>
			</div>
			<?php } ?>

			<?php if ( $has_woo ) { ?>
			<div class="ecwz-ncard">
				<h4><?php esc_html_e( 'Import from WooCommerce', 'wp-easycart' ); ?> <span class="ecwz-badge ecwz-badge-blue"><?php esc_html_e( 'Detected', 'wp-easycart' ); ?></span></h4>
				<p><?php esc_html_e( 'We noticed WooCommerce on this site. Bring products, categories and customers across automatically.', 'wp-easycart' ); ?></p>
				<a class="ecwz-btn ecwz-btn-sm" href="admin.php?page=wp-easycart-settings&subpage=cart-importer"><?php esc_html_e( 'Open importer', 'wp-easycart' ); ?></a>
			</div>
			<?php } ?>

			<?php if ( $has_square ) { ?>
			<div class="ecwz-ncard">
				<h4><?php esc_html_e( 'Import from Square', 'wp-easycart' ); ?> <span class="ecwz-badge ecwz-badge-blue"><?php esc_html_e( 'Connected', 'wp-easycart' ); ?></span></h4>
				<p><?php esc_html_e( 'Your Square account is connected. Import your catalog into EasyCart now.', 'wp-easycart' ); ?></p>
				<a class="ecwz-btn ecwz-btn-sm" href="admin.php?page=wp-easycart-settings&subpage=cart-importer"><?php esc_html_e( 'Open importer', 'wp-easycart' ); ?></a>
			</div>
			<?php } ?>

			<?php if ( $upsell ) { ?>
			<div class="ecwz-ncard">
				<h4><?php esc_html_e( 'Try Pro free for 14 days', 'wp-easycart' ); ?> <span class="ecwz-badge ecwz-badge-amber">PRO</span></h4>
				<p><?php esc_html_e( 'Live shipping rates, 30+ gateways with no EasyCart fees, subscriptions, gift cards and more.', 'wp-easycart' ); ?></p>
				<a class="ecwz-btn ecwz-btn-sm" href="admin.php?page=wp-easycart-registration&ec_trial=start" target="_blank"><?php esc_html_e( 'Start trial', 'wp-easycart' ); ?></a>
			</div>
			<?php } ?>
		</div>
	</div>

	<div class="ecwz-learn">
		<a href="https://support.wpeasycart.com/video-tutorials/" target="_blank" rel="noopener noreferrer"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M10 9l5 3-5 3z"/></svg><?php esc_html_e( 'Video tutorials', 'wp-easycart' ); ?></a>
		<a href="https://docs.wpeasycart.com/wp-easycart-administrative-console-guide/" target="_blank" rel="noopener noreferrer"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 4h12l4 4v12H4z M8 12h8M8 16h8"/></svg><?php esc_html_e( 'Documentation', 'wp-easycart' ); ?></a>
		<a href="https://www.wpeasycart.com/contact-information/" target="_blank" rel="noopener noreferrer"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 12a8 8 0 01-11.6 7.2L4 21l1.8-5.4A8 8 0 1121 12z"/></svg><?php esc_html_e( 'Ask a question', 'wp-easycart' ); ?></a>
	</div>
</div>
<div class="ecwz-foot">
	<a class="ecwz-btn ecwz-btn-ghost" href="<?php echo esc_url( $wizard->step_url( wp_easycart_admin_setup_wizard::STEP_FINISH ) ); ?>">&larr; <?php esc_html_e( 'Back', 'wp-easycart' ); ?></a>
	<span class="ecwz-grow"></span>
	<a class="ecwz-btn" href="admin.php?page=wp-easycart-license-status"><?php esc_html_e( 'Go to Store Status', 'wp-easycart' ); ?></a>
	<a class="ecwz-btn ecwz-btn-primary" href="<?php echo esc_url( $store_url ); ?>" target="_blank"><?php esc_html_e( 'View my store', 'wp-easycart' ); ?> &#8599;</a>
</div>
<?php
	/* "Create product" on the checklist opens the standard new-product slideout. */
	wp_easycart_admin()->load_new_slideout( 'product' );
	wp_easycart_admin()->load_new_slideout( 'manufacturer' );
	wp_easycart_admin()->load_new_slideout( 'optionset' );
?>
