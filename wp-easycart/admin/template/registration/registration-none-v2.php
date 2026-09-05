<?php
/**
 * Registration — free edition, no license ( V2 ).
 *
 * Replaces registration_none.php. Three ways to get PRO on this site, each
 * stated once, then the same upsell card used everywhere else. State-aware:
 * when the PRO plugin is already installed but inactive, the "have a key"
 * path becomes Activate instead of Install.
 *
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ecreg_status    = class_exists( 'wp_easycart_admin_pro_gate' ) ? wp_easycart_admin_pro_gate::pro_status() : array( 'installed' => false, 'active' => false, 'version' => '' );
$ecreg_installed = ! empty( $ecreg_status['installed'] );
$ecreg_active    = ! empty( $ecreg_status['active'] );
$ecreg_trial_url = self_admin_url( 'admin.php?page=wp-easycart-registration&ec_trial=start' );
$ecreg_install   = self_admin_url( 'admin.php?page=wp-easycart-registration&ec_install=pro' );
$ecreg_activate  = wp_easycart_admin()->get_pro_activation_link();
$ecreg_pricing   = class_exists( 'wp_easycart_admin_upsell' ) ? wp_easycart_admin_upsell::plan_url( 'pro', 'default' ) : 'https://www.wpeasycart.com/wordpress-shopping-cart-pricing/';
$ecreg_stats     = class_exists( 'wp_easycart_admin_upsell' ) ? wp_easycart_admin_upsell::stats() : array();
?>
<div class="ecv2-wrap ecreg">

	<div class="ecv2-page-header">
		<div class="ecv2-page-header-left">
			<span class="dashicons dashicons-unlock ecv2-page-header-icon"></span>
			<h2 class="ecv2-page-title"><?php esc_html_e( 'Registration & activation', 'wp-easycart' ); ?></h2>
		</div>
		<div class="ecv2-page-header-right">
			<a href="<?php echo esc_url( wp_easycart_admin()->helpsystem->print_docs_url( 'settings', 'registration', 'none' ) ); ?>" target="_blank" class="ecv2-btn ecv2-btn-ghost ecv2-btn-sm"><span class="dashicons dashicons-editor-help"></span> <?php esc_html_e( 'Help', 'wp-easycart' ); ?></a>
		</div>
	</div>

	<p class="ecv2-page-intro"><?php esc_html_e( 'You are running the free edition. Everything below unlocks the PRO features shown throughout the admin; your products, orders and settings stay exactly as they are.', 'wp-easycart' ); ?></p>

	<?php if ( $ecreg_installed && ! $ecreg_active ) : ?>
	<div class="ecreg-notice">
		<span class="dashicons dashicons-info-outline"></span>
		<span><strong><?php esc_html_e( 'PRO is already installed on this site.', 'wp-easycart' ); ?></strong> <?php esc_html_e( 'Activate the plugin, then enter your license key ( or start a trial ) on this page.', 'wp-easycart' ); ?></span>
		<a class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" href="<?php echo esc_url( $ecreg_activate ); ?>"><?php esc_html_e( 'Activate PRO', 'wp-easycart' ); ?></a>
	</div>
	<?php endif; ?>

	<div class="ecreg-paths">

		<!-- Trial -->
		<div class="ecreg-path is-featured">
			<span class="ecreg-path-tag"><?php esc_html_e( 'Most people start here', 'wp-easycart' ); ?></span>
			<span class="dashicons dashicons-clock ecreg-path-icon"></span>
			<h3><?php esc_html_e( 'Try PRO free for 14 days', 'wp-easycart' ); ?></h3>
			<p><?php esc_html_e( 'One click installs and activates PRO on this site. No credit card. When the trial ends the PRO panels lock again and nothing is lost.', 'wp-easycart' ); ?></p>
			<ul class="ecreg-path-list">
				<li><?php esc_html_e( 'Every PRO feature, unlimited', 'wp-easycart' ); ?></li>
				<li><?php esc_html_e( 'Keep all data if you go back to free', 'wp-easycart' ); ?></li>
				<li><?php esc_html_e( 'Upgrade during the trial without reinstalling', 'wp-easycart' ); ?></li>
			</ul>
			<a class="ecv2-btn ecv2-btn-primary ecreg-path-cta" href="<?php echo esc_url( $ecreg_trial_url ); ?>"><?php esc_html_e( 'Start free trial', 'wp-easycart' ); ?></a>
			<span class="ecreg-path-fine"><?php esc_html_e( 'One trial per site. Uses the email on your WordPress account.', 'wp-easycart' ); ?></span>
		</div>

		<!-- Have a key -->
		<div class="ecreg-path">
			<span class="dashicons dashicons-admin-network ecreg-path-icon"></span>
			<h3><?php esc_html_e( 'Already have a license key?', 'wp-easycart' ); ?></h3>
			<?php if ( $ecreg_installed && ! $ecreg_active ) : ?>
				<p><?php esc_html_e( 'PRO is installed. Activate it and this page will show the license form where you paste your key.', 'wp-easycart' ); ?></p>
				<a class="ecv2-btn ecreg-path-cta" href="<?php echo esc_url( $ecreg_activate ); ?>"><?php esc_html_e( 'Activate PRO plugin', 'wp-easycart' ); ?></a>
			<?php elseif ( ! $ecreg_installed ) : ?>
				<p><?php esc_html_e( 'Install the PRO plugin first — it is a one-click download from here — then paste your key on this page.', 'wp-easycart' ); ?></p>
				<a class="ecv2-btn ecreg-path-cta" href="<?php echo esc_url( $ecreg_install ); ?>"><?php esc_html_e( 'Install PRO plugin', 'wp-easycart' ); ?></a>
			<?php else : ?>
				<p><?php esc_html_e( 'Paste your key in the license form on this page.', 'wp-easycart' ); ?></p>
			<?php endif; ?>
			<span class="ecreg-path-fine"><?php esc_html_e( 'One key per WordPress site. Entering it on a new site moves the license there automatically.', 'wp-easycart' ); ?></span>
		</div>

		<!-- Buy -->
		<div class="ecreg-path">
			<span class="dashicons dashicons-cart ecreg-path-icon"></span>
			<h3><?php esc_html_e( 'Buy a license', 'wp-easycart' ); ?></h3>
			<p><?php esc_html_e( 'Professional includes every PRO feature plus a year of priority support and updates. Premium adds every extension.', 'wp-easycart' ); ?></p>
			<a class="ecv2-btn ecreg-path-cta" href="<?php echo esc_url( $ecreg_pricing ); ?>" target="_blank"><?php esc_html_e( 'See plans & pricing', 'wp-easycart' ); ?> <span class="dashicons dashicons-external"></span></a>
			<span class="ecreg-path-fine"><?php esc_html_e( 'Your key arrives by email; come back here to enter it.', 'wp-easycart' ); ?></span>
		</div>

	</div>

	<?php if ( ! empty( $ecreg_stats['orders'] ) || ! empty( $ecreg_stats['products'] ) ) : ?>
	<p class="ecreg-stat"><span class="dashicons dashicons-chart-line"></span>
		<?php
		echo esc_html( sprintf(
			/* translators: %1$s = product count, %2$s = order count. */
			__( 'This store already has %1$s products and %2$s orders — PRO features apply to all of them the moment it is activated.', 'wp-easycart' ),
			number_format_i18n( (int) $ecreg_stats['products'] ),
			number_format_i18n( (int) $ecreg_stats['orders'] )
		) );
		?>
	</p>
	<?php endif; ?>

	<!-- What PRO adds: the standard upsell card, cleared below the paths. -->
	<div class="ecreg-upsell">
		<?php wp_easycart_admin()->show_upgrade(); ?>
	</div>

</div>