<?php
/**
 * Registration — licensed states, V2 ( replaces registration_status.php ).
 *
 * Handles the three statuses load_registration_status() routes here:
 *   deactivated           → get PRO on this site ( trial / key / find your key )
 *   activated             → license card + renew / move / upgrade
 *   communications_error  → licensing server unreachable
 *
 * All forms post to the same ec_action endpoints with the same field names.
 * $license_status is inherited from wp_easycart_admin_registration::load_registration_status().
 *
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ecrs_status   = isset( $license_status ) ? $license_status : 'deactivated';
$ecrs_activate = self_admin_url( 'admin.php?page=wp-easycart-registration&subpage=registration&ec_action=activateregistration' );
$ecrs_deact    = self_admin_url( 'admin.php?page=wp-easycart-registration&subpage=registration&ec_action=deactivateregistration' );
$ecrs_email    = self_admin_url( 'admin.php?page=wp-easycart-registration&subpage=registration&ec_action=updateregistrationemail' );
$ecrs_account  = 'https://www.wpeasycart.com/my-account/';
$ecrs_trial    = self_admin_url( 'admin.php?page=wp-easycart-registration&ec_trial=start' );
$ecrs_docs     = wp_easycart_admin()->helpsystem->print_docs_url( 'settings', 'registration', 'registration' );

/* One-line result of the last action ( same $_GET codes the endpoints redirect with ). */
$ecrs_flash = null;
$ecrs_codes = array(
	'success' => array(
		'activate-complete'   => __( 'License activated. The PRO sections are now open.', 'wp-easycart' ),
		'deactivate-complete' => __( 'License deactivated. You can use the key on another site now.', 'wp-easycart' ),
	),
	'error' => array(
		'activate-no-key-found'          => __( 'No license was found for that key. Check the value and try again.', 'wp-easycart' ),
		'activate-registration-failed'   => __( 'Activation failed. Please try again in a few minutes.', 'wp-easycart' ),
		'deactivate-no-key-found'        => __( 'No license was found for that key. Check the value and try again.', 'wp-easycart' ),
		'deactivate-registration-failed' => __( 'Deactivation failed. Please try again in a few minutes.', 'wp-easycart' ),
	),
);
foreach ( $ecrs_codes as $ecrs_kind => $ecrs_map ) {
	if ( isset( $_GET[ $ecrs_kind ] ) && isset( $ecrs_map[ $_GET[ $ecrs_kind ] ] ) ) {
		$ecrs_flash = array( $ecrs_kind, $ecrs_map[ $_GET[ $ecrs_kind ] ] );
	}
}

$ecrs_license = ( 'activated' === $ecrs_status && function_exists( 'wp_easycart_admin_license' ) ) ? wp_easycart_admin_license()->license_data : null;
$ecrs_info    = get_option( 'wp_easycart_license_info' );
$ecrs_email_v = ( is_array( $ecrs_info ) && isset( $ecrs_info['customer_email'] ) ) ? $ecrs_info['customer_email'] : '';
$ecrs_key     = ( is_array( $ecrs_info ) && isset( $ecrs_info['transaction_key'] ) ) ? $ecrs_info['transaction_key'] : '';
?>
<div class="ecv2-wrap ecreg ecreg-status">

	<div class="ecv2-page-header">
		<div class="ecv2-page-header-left">
			<span class="dashicons dashicons-admin-network ecv2-page-header-icon"></span>
			<h2 class="ecv2-page-title"><?php esc_html_e( 'Registration & activation', 'wp-easycart' ); ?></h2>
		</div>
		<div class="ecv2-page-header-right">
			<a href="<?php echo esc_url( $ecrs_docs ); ?>" target="_blank" class="ecv2-btn ecv2-btn-ghost ecv2-btn-sm"><span class="dashicons dashicons-editor-help"></span> <?php esc_html_e( 'Help', 'wp-easycart' ); ?></a>
		</div>
	</div>

	<?php if ( $ecrs_flash ) : ?>
	<div class="ecreg-notice <?php echo 'success' === $ecrs_flash[0] ? 'is-ok' : 'is-ended'; ?>">
		<span class="dashicons <?php echo 'success' === $ecrs_flash[0] ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
		<span><?php echo esc_html( $ecrs_flash[1] ); ?></span>
	</div>
	<?php endif; ?>

	<?php if ( 'communications_error' === $ecrs_status ) : ?>
	<!-- ================= LICENSING SERVER UNREACHABLE ================= -->
	<div class="ecreg-notice is-ended">
		<span class="dashicons dashicons-warning"></span>
		<span><strong><?php esc_html_e( 'The licensing server could not be reached.', 'wp-easycart' ); ?></strong> <?php esc_html_e( 'Your store keeps working; only activation and license checks are affected. Try again in a few minutes.', 'wp-easycart' ); ?></span>
		<a class="ecv2-btn ecv2-btn-sm" href="<?php echo esc_url( self_admin_url( 'admin.php?page=wp-easycart-registration' ) ); ?>"><?php esc_html_e( 'Retry', 'wp-easycart' ); ?></a>
	</div>

	<?php elseif ( 'deactivated' === $ecrs_status ) : ?>
	<!-- ================= NO LICENSE ON THIS SITE ================= -->
	<p class="ecv2-page-intro"><?php esc_html_e( 'PRO is installed but no license is attached to this site. Start a trial, or enter a key you already own.', 'wp-easycart' ); ?></p>
	<div class="ecreg-paths">
		<div class="ecreg-path is-featured">
			<span class="ecreg-path-tag"><?php esc_html_e( 'Most people start here', 'wp-easycart' ); ?></span>
			<span class="dashicons dashicons-clock ecreg-path-icon"></span>
			<h3><?php esc_html_e( 'Try PRO free for 14 days', 'wp-easycart' ); ?></h3>
			<p><?php esc_html_e( 'One click activates a trial on this site. No credit card. When it ends the PRO panels lock again and nothing is lost.', 'wp-easycart' ); ?></p>
			<a class="ecv2-btn ecv2-btn-primary ecreg-path-cta" href="<?php echo esc_url( $ecrs_trial ); ?>"><?php esc_html_e( 'Start free trial', 'wp-easycart' ); ?></a>
			<span class="ecreg-path-fine"><?php esc_html_e( 'One trial per site.', 'wp-easycart' ); ?></span>
		</div>
		<div class="ecreg-path">
			<span class="dashicons dashicons-admin-network ecreg-path-icon"></span>
			<h3><?php esc_html_e( 'Already have a license key?', 'wp-easycart' ); ?></h3>
			<p><?php esc_html_e( 'Paste it below. If the key is in use on another site it moves here automatically.', 'wp-easycart' ); ?></p>
			<form action="<?php echo esc_url( $ecrs_activate ); ?>" method="POST" id="wpeasycart_admin_form1" class="ecreg-form" novalidate="novalidate">
				<div class="ecreg-f"><label for="customername"><?php esc_html_e( 'Full name', 'wp-easycart' ); ?></label><input type="text" class="ecv2-input" name="customername" id="customername" required="required" autocomplete="name" /></div>
				<div class="ecreg-f"><label for="customeremail_activate"><?php esc_html_e( 'Email address', 'wp-easycart' ); ?></label><input type="email" class="ecv2-input" name="customeremail" id="customeremail_activate" required="required" autocomplete="email" value="<?php echo esc_attr( $ecrs_email_v ); ?>" /></div>
				<div class="ecreg-f"><label for="transactionkey"><?php esc_html_e( 'License key', 'wp-easycart' ); ?></label><input type="text" class="ecv2-input ecreg-mono" name="transactionkey" id="transactionkey" required="required" autocomplete="off" spellcheck="false" placeholder="XXXX-XXXX-XXXX-XXXX" /></div>
				<button type="submit" class="ecv2-btn ecv2-btn-primary ecreg-path-cta"><?php esc_html_e( 'Activate license', 'wp-easycart' ); ?></button>
			</form>
		</div>
		<div class="ecreg-path">
			<span class="dashicons dashicons-search ecreg-path-icon"></span>
			<h3><?php esc_html_e( 'Forgot your key?', 'wp-easycart' ); ?></h3>
			<p><?php esc_html_e( 'Every key you have bought is listed in your WP EasyCart account, with its support end date and the site it is on.', 'wp-easycart' ); ?></p>
			<a class="ecv2-btn ecreg-path-cta" href="<?php echo esc_url( $ecrs_account ); ?>" target="_blank"><?php esc_html_e( 'Open my account', 'wp-easycart' ); ?> <span class="dashicons dashicons-external"></span></a>
			<span class="ecreg-path-fine"><?php esc_html_e( 'Need a new license? Plans and pricing are there too.', 'wp-easycart' ); ?></span>
		</div>
	</div>

	<?php elseif ( $ecrs_license ) : ?>
	<!-- ================= LICENSE ACTIVE ================= -->
	<?php
	$ecrs_v3        = ( isset( $ecrs_license->key_version ) && 'v3' === $ecrs_license->key_version );
	$ecrs_premium   = ( isset( $ecrs_license->model_number ) && 'ec410' === strtolower( trim( (string) $ecrs_license->model_number ) ) );
	$ecrs_end_ts    = ! empty( $ecrs_license->support_end_date ) ? strtotime( $ecrs_license->support_end_date ) : 0;
	$ecrs_days      = $ecrs_end_ts ? max( 0, class_exists( 'wp_easycart_admin_upsell' ) ? wp_easycart_admin_upsell::days_until( $ecrs_end_ts ) : (int) round( ( $ecrs_end_ts - time() ) / DAY_IN_SECONDS ) ) : 0;
	$ecrs_lapsed    = ( $ecrs_end_ts && $ecrs_days <= 0 );
	$ecrs_renew     = $ecrs_premium ? 'https://www.wpeasycart.com/products/wp-easycart-premium-support-extensions/?transaction_key=' . rawurlencode( $ecrs_key ) : 'https://www.wpeasycart.com/products/wp-easycart-professional-support-upgrades/?transaction_key=' . rawurlencode( $ecrs_key );
	$ecrs_upgrade   = 'https://www.wpeasycart.com/products/wp-easycart-premium-support-extensions/?transaction_key=' . rawurlencode( $ecrs_key );
	$ecrs_ring_r    = 26; $ecrs_ring_c = 2 * M_PI * $ecrs_ring_r;
	$ecrs_ring_pct  = $ecrs_end_ts ? min( 1, $ecrs_days / 365 ) : 1;
	?>
	<?php if ( $ecrs_v3 ) : ?>
	<div class="ecreg-notice">
		<span class="dashicons dashicons-info-outline"></span>
		<span><?php esc_html_e( 'This site is on a legacy ( v3 ) license and the licensing server could not confirm it just now. Everything keeps working; if this message stays for more than a day, contact support.', 'wp-easycart' ); ?></span>
	</div>
	<?php elseif ( $ecrs_lapsed ) : ?>
	<div class="ecreg-notice is-ended">
		<span class="dashicons dashicons-warning"></span>
		<span><strong><?php esc_html_e( 'Support and updates have lapsed.', 'wp-easycart' ); ?></strong> <?php esc_html_e( 'The license is still registered here, but you are no longer receiving security fixes or new features.', 'wp-easycart' ); ?>
			<?php $ecrs_deact = class_exists( 'wp_easycart_admin_upsell' ) ? wp_easycart_admin_upsell::pro_deactivate_url() : ''; ?>
			<?php if ( '' !== $ecrs_deact ) : ?><a class="ecreg-quiet-link" href="<?php echo esc_url( $ecrs_deact ); ?>" onclick="return window.confirm( <?php echo esc_attr( wp_json_encode( __( 'Switch to the free edition? This deactivates the PRO plugin. Your data is kept.', 'wp-easycart' ) ) ); ?> );"><?php esc_html_e( 'Not renewing? Switch to the free edition.', 'wp-easycart' ); ?></a><?php endif; ?>
		</span>
		<a class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" href="<?php echo esc_url( $ecrs_renew ); ?>" target="_blank"><?php esc_html_e( 'Renew now', 'wp-easycart' ); ?></a>
	</div>
	<?php elseif ( $ecrs_end_ts && $ecrs_days <= 30 && class_exists( 'wp_easycart_admin_upsell' ) ) : ?>
	<div class="ecreg-notice <?php echo $ecrs_days <= 7 ? 'is-ended' : ''; ?> ecreg-notice-stakes">
		<span class="dashicons dashicons-clock"></span>
		<span>
			<strong><?php echo esc_html( sprintf( _n( 'Support & updates end tomorrow ( %2$s ).', 'Support & updates end in %1$d days ( %2$s ).', $ecrs_days, 'wp-easycart' ), $ecrs_days, date_i18n( get_option( 'date_format' ), $ecrs_end_ts ) ) ); ?></strong>
			<?php esc_html_e( 'Renewing now adds a full year on top. If it lapses:', 'wp-easycart' ); ?>
			<ul class="ecreg-stakes"><?php foreach ( array_slice( wp_easycart_admin_upsell::renewal_stakes(), 0, 4 ) as $ecrs_stake ) : ?><li><?php echo esc_html( $ecrs_stake ); ?></li><?php endforeach; ?></ul>
		</span>
		<a class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" href="<?php echo esc_url( $ecrs_renew ); ?>" target="_blank"><?php esc_html_e( 'Renew now', 'wp-easycart' ); ?></a>
	</div>
	<?php endif; ?>

	<div class="ecreg-license-card ecss-license ecss-tone-<?php echo $ecrs_lapsed ? 'expired' : 'active'; ?>">
		<div class="ecss-ring" aria-hidden="true">
			<svg viewBox="0 0 64 64"><circle class="ecss-ring-bg" cx="32" cy="32" r="<?php echo (int) $ecrs_ring_r; ?>"/><circle class="ecss-ring-fg" cx="32" cy="32" r="<?php echo (int) $ecrs_ring_r; ?>" style="stroke-dasharray:<?php echo esc_attr( round( $ecrs_ring_c, 2 ) ); ?>;stroke-dashoffset:<?php echo esc_attr( round( $ecrs_ring_c * ( 1 - $ecrs_ring_pct ), 2 ) ); ?>;"/></svg>
			<span class="ecss-ring-label"><?php echo $ecrs_premium ? esc_html__( 'Premium', 'wp-easycart' ) : esc_html__( 'PRO', 'wp-easycart' ); ?></span>
		</div>
		<div class="ecss-license-copy">
			<h3><?php echo $ecrs_premium ? esc_html__( 'Premium license active on this site', 'wp-easycart' ) : esc_html__( 'PRO license active on this site', 'wp-easycart' ); ?></h3>
			<dl class="ecreg-dl ecreg-dl-wide">
				<dt><?php esc_html_e( 'Registered URL', 'wp-easycart' ); ?></dt><dd><?php echo esc_html( isset( $ecrs_license->siteurl ) ? $ecrs_license->siteurl : home_url() ); ?></dd>
				<?php if ( $ecrs_v3 ) : ?>
				<dt><?php esc_html_e( 'Registered', 'wp-easycart' ); ?></dt><dd><?php echo esc_html( ! empty( $ecrs_license->date ) ? date_i18n( get_option( 'date_format' ), strtotime( $ecrs_license->date ) ) : '—' ); ?></dd>
				<dt><?php esc_html_e( 'License version', 'wp-easycart' ); ?></dt><dd>v3</dd>
				<?php else : ?>
				<dt><?php esc_html_e( 'Support & updates', 'wp-easycart' ); ?></dt>
				<dd><?php echo $ecrs_end_ts ? esc_html( sprintf( $ecrs_lapsed ? __( 'Ended %s', 'wp-easycart' ) : __( 'Until %s', 'wp-easycart' ), date_i18n( get_option( 'date_format' ), $ecrs_end_ts ) ) ) : '—'; ?><?php if ( ! $ecrs_lapsed && $ecrs_end_ts ) : ?> <span class="ecreg-days<?php echo $ecrs_days < 60 ? ' is-soon' : ''; ?>"><?php echo esc_html( sprintf( _n( '%d day left', '%d days left', $ecrs_days, 'wp-easycart' ), $ecrs_days ) ); ?></span><?php endif; ?></dd>
				<?php if ( '' !== $ecrs_key ) : ?><dt><?php esc_html_e( 'License key', 'wp-easycart' ); ?></dt><dd class="ecreg-mono"><?php echo esc_html( $ecrs_key ); ?></dd><?php endif; ?>
				<?php endif; ?>
			</dl>
			<?php if ( ! $ecrs_v3 ) : ?>
			<form action="<?php echo esc_url( $ecrs_email ); ?>" method="POST" class="ecreg-form ecreg-form-inline" novalidate="novalidate">
				<div class="ecreg-f">
					<label for="customeremail"><?php esc_html_e( 'License email', 'wp-easycart' ); ?> <span class="ecreg-fine"><?php esc_html_e( 'renewal receipts and notices go here', 'wp-easycart' ); ?></span></label>
					<div class="ecreg-inline"><input type="email" class="ecv2-input" name="customeremail" id="customeremail" value="<?php echo esc_attr( $ecrs_email_v ); ?>" autocomplete="email" /><button type="submit" class="ecv2-btn ecv2-btn-sm"><?php esc_html_e( 'Update', 'wp-easycart' ); ?></button></div>
				</div>
			</form>
			<?php endif; ?>
		</div>
	</div>

	<div class="ecreg-paths ecreg-paths-3">
		<div class="ecreg-path<?php echo ( $ecrs_lapsed || ( $ecrs_end_ts && $ecrs_days <= 30 ) ) ? ' is-featured' : ''; ?>">
			<?php if ( $ecrs_lapsed || ( $ecrs_end_ts && $ecrs_days <= 30 ) ) : ?><span class="ecreg-path-tag"><?php echo $ecrs_lapsed ? esc_html__( 'Action needed', 'wp-easycart' ) : ( $ecrs_days <= 7 ? esc_html__( 'Ends this week', 'wp-easycart' ) : esc_html__( 'Renew before it lapses', 'wp-easycart' ) ); ?></span><?php endif; ?>
			<span class="dashicons dashicons-update ecreg-path-icon"></span>
			<h3><?php esc_html_e( 'Support & updates', 'wp-easycart' ); ?></h3>
			<p><?php esc_html_e( 'Renewing extends security fixes, new features and priority support for another year. Renew early and the time is added on — nothing is lost.', 'wp-easycart' ); ?></p>
			<a class="ecv2-btn <?php echo ( $ecrs_lapsed || ( $ecrs_end_ts && $ecrs_days <= 30 ) ) ? 'ecv2-btn-primary ' : ''; ?>ecreg-path-cta" href="<?php echo esc_url( $ecrs_v3 ? $ecrs_account : $ecrs_renew ); ?>" target="_blank"><?php esc_html_e( 'Renew support & updates', 'wp-easycart' ); ?></a>
			<span class="ecreg-path-fine"><?php esc_html_e( 'Sign in with the account that bought this license so the credit applies.', 'wp-easycart' ); ?></span>
		</div>
		<?php if ( ! $ecrs_premium ) : ?>
		<div class="ecreg-path">
			<span class="dashicons dashicons-star-filled ecreg-path-icon"></span>
			<h3><?php esc_html_e( 'Upgrade to Premium', 'wp-easycart' ); ?></h3>
			<p><?php esc_html_e( 'Everything in PRO plus every extension — ShipStation, QuickBooks, MailChimp, Facebook & Instagram, the mobile apps. Your key stays the same.', 'wp-easycart' ); ?></p>
			<a class="ecv2-btn ecreg-path-cta" href="<?php echo esc_url( $ecrs_v3 ? $ecrs_account : $ecrs_upgrade ); ?>" target="_blank"><?php esc_html_e( 'See Premium', 'wp-easycart' ); ?> <span class="dashicons dashicons-external"></span></a>
		</div>
		<?php else : ?>
		<div class="ecreg-path">
			<span class="dashicons dashicons-download ecreg-path-icon"></span>
			<h3><?php esc_html_e( 'Premium extensions', 'wp-easycart' ); ?></h3>
			<p><?php esc_html_e( 'ShipStation, QuickBooks, MailChimp, Facebook & Instagram and the mobile apps are included. Download them from your account.', 'wp-easycart' ); ?></p>
			<a class="ecv2-btn ecreg-path-cta" href="<?php echo esc_url( $ecrs_account ); ?>" target="_blank"><?php esc_html_e( 'Open my account', 'wp-easycart' ); ?> <span class="dashicons dashicons-external"></span></a>
		</div>
		<?php endif; ?>
		<div class="ecreg-path">
			<span class="dashicons dashicons-migrate ecreg-path-icon"></span>
			<h3><?php esc_html_e( 'Move this license', 'wp-easycart' ); ?></h3>
			<p><?php esc_html_e( 'Deactivate here to use the key on another site. Or skip this step: entering the key on the new site moves it automatically.', 'wp-easycart' ); ?></p>
			<form action="<?php echo esc_url( $ecrs_deact ); ?>" method="POST" id="wpeasycart_admin_form2" class="ecreg-form" novalidate="novalidate" onsubmit="return window.confirm( <?php echo wp_json_encode( __( 'Deactivate the PRO license on this site? The PRO panels will lock until a key is entered again.', 'wp-easycart' ) ); ?> );">
				<div class="ecreg-f"><label for="transactionkey_deact"><?php esc_html_e( 'Confirm the license key', 'wp-easycart' ); ?></label><input type="text" class="ecv2-input ecreg-mono" name="transactionkey" id="transactionkey_deact" required="required" autocomplete="off" spellcheck="false" placeholder="XXXX-XXXX-XXXX-XXXX" /></div>
				<button type="submit" class="ecv2-btn ecreg-path-cta ecreg-danger"><?php esc_html_e( 'Deactivate on this site', 'wp-easycart' ); ?></button>
			</form>
		</div>
	</div>

	<?php endif; ?>

</div>