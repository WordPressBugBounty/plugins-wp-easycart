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
$ecrs_trial    = wp_easycart_admin()->pro_install_url( 'trial' );
$ecrs_docs     = wp_easycart_admin()->helpsystem->print_docs_url( 'settings', 'registration', 'registration' );

$ecrs_license = ( 'activated' === $ecrs_status && function_exists( 'wp_easycart_admin_license' ) ) ? wp_easycart_admin_license()->license_data : null;
$ecrs_info    = get_option( 'wp_easycart_license_info' );
$ecrs_email_v = ( is_array( $ecrs_info ) && isset( $ecrs_info['customer_email'] ) ) ? $ecrs_info['customer_email'] : '';
$ecrs_key     = ( is_array( $ecrs_info ) && isset( $ecrs_info['transaction_key'] ) ) ? $ecrs_info['transaction_key'] : '';

/* 6.0.4: WP EasyCart PRO 6.0.4's license answer can say this copy is a development site ( dev_site: local | staging |
   subdomain; dev_listed when the key lists it, dev_live = the live store ) or that the license servers have been quiet
   ( offline_since / offline_until; grace_ended once the 30 days are over ). Older PRO sets none of these. */
$ecrs_data       = function_exists( 'wp_easycart_admin_license' ) ? wp_easycart_admin_license()->license_data : null;
$ecrs_dev_site   = ( is_object( $ecrs_data ) && ! empty( $ecrs_data->dev_site ) ) ? (string) $ecrs_data->dev_site : '';
$ecrs_dev_listed = ( '' !== $ecrs_dev_site && ! empty( $ecrs_data->dev_listed ) );
$ecrs_dev_live   = ( $ecrs_dev_listed && ! empty( $ecrs_data->dev_live ) ) ? (string) $ecrs_data->dev_live : '';
$ecrs_offline    = ( is_object( $ecrs_data ) && ! empty( $ecrs_data->offline_since ) ) ? (int) $ecrs_data->offline_since : 0;
$ecrs_off_until  = ( $ecrs_offline && ! empty( $ecrs_data->offline_until ) ) ? (int) $ecrs_data->offline_until : 0;
?>
<div class="ecv2-wrap ecreg ecreg-status">

	<div class="ecv2-page-header">
		<div class="ecv2-page-header-left">
			<span class="dashicons dashicons-admin-network ecv2-page-header-icon"></span>
			<h2 class="ecv2-page-title"><?php esc_html_e( 'Registration & activation', 'wp-easycart' ); ?></h2>
		</div>
		<div class="ecv2-page-header-right">
			<a href="<?php echo esc_url( $ecrs_docs ); ?>" target="_blank" class="ecv2-btn ecv2-btn-ghost ecv2-btn-sm"><span class="dashicons dashicons-editor-help"></span> <span class="ecv2-btn-label"><?php esc_html_e( 'Help', 'wp-easycart' ); ?></span></a>
		</div>
	</div>

	<?php include __DIR__ . '/registration-notice-v2.php'; /* 6.0.2: the last license action's result, shared with the expired and trial screens */ ?>

	<?php if ( 'communications_error' === $ecrs_status ) : ?>
	<!-- ================= LICENSING SERVER UNREACHABLE ================= -->
	<div class="ecreg-notice is-ended">
		<span class="dashicons dashicons-warning"></span>
		<?php if ( $ecrs_offline && ! empty( $ecrs_data->grace_ended ) ) : ?>
		<?php /* 6.0.4: PRO keeps the last answer for 30 days without one; after that its features lock until a server answers. */ ?>
		<span><strong><?php
			/* translators: %s: date. */
			echo esc_html( sprintf( __( 'Pro features are locked: WP EasyCart PRO has not reached the licensing server since %s.', 'wp-easycart' ), date_i18n( get_option( 'date_format' ), $ecrs_offline ) ) );
		?></strong> <?php
			/* translators: %s: a server's address. */
			echo esc_html( sprintf( __( 'They open again as soon as it answers. Ask your host to let this site reach %s.', 'wp-easycart' ), '' !== $ecrs_dev_site ? 'connect.wpeasycart.com' : 'licensing.wpeasycart.com' ) );
		?></span>
		<?php else : ?>
		<span><strong><?php esc_html_e( 'The licensing server could not be reached.', 'wp-easycart' ); ?></strong> <?php esc_html_e( 'Your store keeps working; only activation and license checks are affected. Try again in a few minutes.', 'wp-easycart' ); ?></span>
		<?php endif; ?>
		<a class="ecv2-btn ecv2-btn-sm" href="<?php echo esc_url( self_admin_url( 'admin.php?page=wp-easycart-registration' ) ); ?>"><?php esc_html_e( 'Retry', 'wp-easycart' ); ?></a>
	</div>

	<?php elseif ( 'deactivated' === $ecrs_status ) : ?>
	<!-- ================= NO LICENSE ON THIS SITE ================= -->
	<?php if ( 'subdomain' === $ecrs_dev_site ) : ?>
	<p class="ecv2-page-intro"><?php esc_html_e( 'WP EasyCart PRO is installed but no license is attached to this site. If this is a staging copy of your store, entering your license key adds it as a development site, and your live store keeps its license.', 'wp-easycart' ); ?></p>
	<?php elseif ( '' !== $ecrs_dev_site ) : ?>
	<p class="ecv2-page-intro"><?php esc_html_e( 'This is a development copy ( a local or staging address ). Entering your license key adds it to the license\'s development sites, up to 3, and your live store keeps its license.', 'wp-easycart' ); ?></p>
	<?php else : ?>
	<p class="ecv2-page-intro"><?php esc_html_e( 'WP EasyCart PRO is installed but no license is attached to this site. Start a trial, or enter a key you already own.', 'wp-easycart' ); ?></p>
	<?php endif; ?>
	<?php
	/**
	 * The Registration page's development sites ( WP EasyCart PRO 6.0.4 prints the key's list, on a copy a full key just
	 * refused, so one can be removed there ).
	 *
	 * @since 6.0.4
	 * @param string $status The license status ( deactivated | activated ).
	 */
	do_action( 'wp_easycart_admin_registration_dev_sites', 'deactivated' );
	?>
	<div class="ecreg-paths">
		<div class="ecreg-path is-featured">
			<span class="ecreg-path-tag"><?php esc_html_e( 'Most people start here', 'wp-easycart' ); ?></span>
			<span class="dashicons dashicons-clock ecreg-path-icon"></span>
			<h3><?php esc_html_e( 'Try Pro free for 14 days', 'wp-easycart' ); ?></h3>
			<p><?php esc_html_e( 'One click activates a trial on this site. No credit card. When it ends the Pro panels lock again and nothing is lost.', 'wp-easycart' ); ?></p>
			<a class="ecv2-btn ecv2-btn-primary ecreg-path-cta" href="<?php echo esc_url( $ecrs_trial ); ?>"><?php esc_html_e( 'Start free trial', 'wp-easycart' ); ?></a>
			<span class="ecreg-path-fine"><?php esc_html_e( 'One trial per site.', 'wp-easycart' ); ?></span>
		</div>
		<div class="ecreg-path">
			<span class="dashicons dashicons-admin-network ecreg-path-icon"></span>
			<h3><?php esc_html_e( 'Already have a license key?', 'wp-easycart' ); ?></h3>
			<p><?php echo esc_html( ( '' !== $ecrs_dev_site && 'subdomain' !== $ecrs_dev_site ) ? __( 'Paste it below. This copy joins the key\'s development sites; your live store keeps its license.', 'wp-easycart' ) : __( 'Paste it below. If the key is in use on another site it moves here automatically.', 'wp-easycart' ) ); ?></p>
			<form action="<?php echo esc_url( $ecrs_activate ); ?>" method="POST" id="wpeasycart_admin_form1" class="ecreg-form" novalidate="novalidate">
				<?php wp_nonce_field( 'wp-easycart-license-action', 'wpec_license_nonce' ); ?>
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
	/* 6.0.4: Premium and Elite AutoPay keys ( ec61x / ec62x ) are Premium too; Elite includes every Premium feature. */
	$ecrs_premium   = ( isset( $ecrs_license->model_number ) && wp_easycart_admin_edition::is_premium_model( $ecrs_license->model_number ) );
	$ecrs_elite     = ( isset( $ecrs_license->model_number ) && 'elite' === wp_easycart_admin_edition::model_edition( $ecrs_license->model_number ) );
	$ecrs_plan      = $ecrs_elite ? __( 'Elite', 'wp-easycart' ) : ( $ecrs_premium ? __( 'Premium', 'wp-easycart' ) : __( 'Pro', 'wp-easycart' ) );
	$ecrs_auto      = ( empty( $ecrs_license->is_trial ) && ! empty( $ecrs_license->is_auto_renew ) ); /* 6.0.4: renews through a subscription ( AutoPay ) */
	$ecrs_end_ts    = ! empty( $ecrs_license->support_end_date ) ? strtotime( $ecrs_license->support_end_date ) : 0;
	$ecrs_days      = $ecrs_end_ts ? max( 0, class_exists( 'wp_easycart_admin_upsell' ) ? wp_easycart_admin_upsell::days_until( $ecrs_end_ts ) : (int) round( ( $ecrs_end_ts - time() ) / DAY_IN_SECONDS ) ) : 0;
	$ecrs_lapsed    = ( $ecrs_end_ts && $ecrs_days <= 0 );
	$ecrs_renew     = wp_easycart_admin_edition::renew_url(); /* The WP EasyCart account ( 6.0.5: every paid license ) */
	/* 6.0.2: the store's own Premium offer ( 6.0.5: the account's Change plan for a Pro license, else the pricing page ). */
	$ecrs_upgrade   = class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::premium_url() : 'https://www.wpeasycart.com/wordpress-shopping-cart-pricing/';
	$ecrs_ring_r    = 26; $ecrs_ring_c = 2 * M_PI * $ecrs_ring_r;
	$ecrs_ring_pct  = ( $ecrs_auto && ! $ecrs_lapsed ) ? 1 : ( $ecrs_end_ts ? min( 1, $ecrs_days / 365 ) : 1 );
	?>
	<?php if ( $ecrs_offline ) : ?>
	<?php /* 6.0.4: no answer from the license servers; WP EasyCart PRO keeps the last one for 30 days. */ ?>
	<div class="ecreg-notice<?php echo ( time() - $ecrs_offline ) >= 3 * DAY_IN_SECONDS ? ' is-ended' : ''; ?>">
		<span class="dashicons dashicons-warning"></span>
		<span><strong><?php
			/* translators: %s: date. */
			echo esc_html( sprintf( __( 'WP EasyCart PRO has not reached the licensing server since %s.', 'wp-easycart' ), date_i18n( get_option( 'date_format' ), $ecrs_offline ) ) );
		?></strong> <?php
			/* translators: 1: date, 2: a server's address. */
			echo esc_html( sprintf( __( 'Everything keeps working until %1$s; after that Pro features lock until it answers. Ask your host to let this site reach %2$s.', 'wp-easycart' ), date_i18n( get_option( 'date_format' ), $ecrs_off_until ? $ecrs_off_until : $ecrs_offline + 30 * DAY_IN_SECONDS ), '' !== $ecrs_dev_site ? 'connect.wpeasycart.com' : 'licensing.wpeasycart.com' ) );
		?></span>
		<a class="ecv2-btn ecv2-btn-sm" href="<?php echo esc_url( self_admin_url( 'admin.php?page=wp-easycart-registration' ) ); ?>"><?php esc_html_e( 'Retry', 'wp-easycart' ); ?></a>
	</div>
	<?php elseif ( $ecrs_v3 ) : ?>
	<div class="ecreg-notice">
		<span class="dashicons dashicons-info-outline"></span>
		<span><?php esc_html_e( 'This site is on a legacy ( v3 ) license and the licensing server could not confirm it just now. Everything keeps working; if this message stays for more than a day, contact support.', 'wp-easycart' ); ?></span>
	</div>
	<?php elseif ( $ecrs_lapsed && $ecrs_auto ) : ?>
	<?php /* 6.0.4: an AutoPay license past its date is a payment that did not go through ( Stripe tries again for a while ). */ ?>
	<div class="ecreg-notice is-ended">
		<span class="dashicons dashicons-warning"></span>
		<span><strong><?php
			/* translators: %s: plan name, Pro, Premium or Elite. */
			echo esc_html( sprintf( __( 'The last automatic payment for your %s license did not go through.', 'wp-easycart' ), $ecrs_plan ) );
		?></strong> <?php esc_html_e( 'Support and updates are paused until it does. Update your card in your WP EasyCart account and the license renews straight away. Nothing has been deleted.', 'wp-easycart' ); ?></span>
		<a class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" href="<?php echo esc_url( $ecrs_renew ); ?>" target="_blank"><?php esc_html_e( 'Update payment', 'wp-easycart' ); ?></a>
	</div>
	<?php elseif ( $ecrs_lapsed ) : ?>
	<?php /* 6.0.3: the one place this page says the license has ended ( the shell's renewal banner, WP EasyCart Premium's and the extensions' license notices stay off this page ). */ ?>
	<div class="ecreg-notice is-ended">
		<span class="dashicons dashicons-warning"></span>
		<span><strong><?php
			/* translators: 1: plan name, Pro or Premium, 2: date. */
			echo esc_html( sprintf( __( 'Your %1$s license ended on %2$s.', 'wp-easycart' ), $ecrs_plan, date_i18n( get_option( 'date_format' ), $ecrs_end_ts ) ) );
		?></strong> <?php echo esc_html( $ecrs_premium ? __( 'Premium features are locked, your extensions keep running but can no longer be updated or changed, and the 2% gateway fee is back. Nothing has been deleted: renewing reopens everything.', 'wp-easycart' ) : __( 'Pro features are locked and the 2% gateway fee is back. Nothing has been deleted: renewing reopens everything.', 'wp-easycart' ) ); ?>
			<?php /* 6.0.3: its own name; it reused $ecrs_deact, so the Move this license form below posted to the plugin deactivation link. */ ?>
			<?php $ecrs_free_url = class_exists( 'wp_easycart_admin_upsell' ) ? wp_easycart_admin_upsell::pro_deactivate_url() : ''; ?>
			<?php if ( '' !== $ecrs_free_url ) : ?><a class="ecreg-quiet-link" href="<?php echo esc_url( $ecrs_free_url ); ?>" onclick="return window.confirm( <?php echo esc_attr( wp_json_encode( __( 'Switch to the free edition? This deactivates the WP EasyCart PRO plugin. Your data is kept.', 'wp-easycart' ) ) ); ?> );"><?php esc_html_e( 'Not renewing? Switch to the free edition.', 'wp-easycart' ); ?></a><?php endif; ?>
		</span>
		<a class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" href="<?php echo esc_url( $ecrs_renew ); ?>" target="_blank"><?php esc_html_e( 'Renew now', 'wp-easycart' ); ?></a>
	</div>
	<?php elseif ( $ecrs_end_ts && $ecrs_days <= 30 && ! $ecrs_auto && class_exists( 'wp_easycart_admin_upsell' ) ) : /* 6.0.4: AutoPay renews itself */ ?>
	<div class="ecreg-notice <?php echo $ecrs_days <= 7 ? 'is-ended' : ''; ?> ecreg-notice-stakes">
		<span class="dashicons dashicons-clock"></span>
		<span>
			<strong><?php echo esc_html( sprintf( _n( 'Support & updates end tomorrow ( %2$s ).', 'Support & updates end in %1$d days ( %2$s ).', $ecrs_days, 'wp-easycart' ), $ecrs_days, date_i18n( get_option( 'date_format' ), $ecrs_end_ts ) ) ); ?></strong>
			<?php esc_html_e( 'Turn on AutoPay in your WP EasyCart account and your license renews by itself. If it lapses:', 'wp-easycart' ); ?>
			<ul class="ecreg-stakes"><?php foreach ( array_slice( wp_easycart_admin_upsell::renewal_stakes(), 0, 4 ) as $ecrs_stake ) : ?><li><?php echo esc_html( $ecrs_stake ); ?></li><?php endforeach; ?></ul>
		</span>
		<a class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" href="<?php echo esc_url( $ecrs_renew ); ?>" target="_blank"><?php esc_html_e( 'Renew now', 'wp-easycart' ); ?></a>
	</div>
	<?php endif; ?>

	<div class="ecreg-license-card ecss-license ecss-tone-<?php echo $ecrs_lapsed ? 'expired' : 'active'; ?>">
		<div class="ecss-ring" aria-hidden="true">
			<svg viewBox="0 0 64 64"><circle class="ecss-ring-bg" cx="32" cy="32" r="<?php echo (int) $ecrs_ring_r; ?>"/><circle class="ecss-ring-fg" cx="32" cy="32" r="<?php echo (int) $ecrs_ring_r; ?>" style="stroke-dasharray:<?php echo esc_attr( round( $ecrs_ring_c, 2 ) ); ?>;stroke-dashoffset:<?php echo esc_attr( round( $ecrs_ring_c * ( 1 - $ecrs_ring_pct ), 2 ) ); ?>;"/></svg>
			<span class="ecss-ring-label"><?php echo esc_html( $ecrs_plan ); ?></span>
		</div>
		<div class="ecss-license-copy">
			<h3><?php
			if ( $ecrs_dev_listed ) {
				/* 6.0.4: a development site runs on its live store's license. */
				if ( $ecrs_lapsed ) {
					echo $ecrs_premium ? esc_html__( 'Premium license on this development site has ended', 'wp-easycart' ) : esc_html__( 'Pro license on this development site has ended', 'wp-easycart' );
				} else {
					echo $ecrs_premium ? esc_html__( 'Premium license active on this development site', 'wp-easycart' ) : esc_html__( 'Pro license active on this development site', 'wp-easycart' );
				}
			} elseif ( $ecrs_lapsed ) {
				echo $ecrs_premium ? esc_html__( 'Premium license registered on this site', 'wp-easycart' ) : esc_html__( 'Pro license registered on this site', 'wp-easycart' );
			} else {
				echo $ecrs_premium ? esc_html__( 'Premium license active on this site', 'wp-easycart' ) : esc_html__( 'Pro license active on this site', 'wp-easycart' );
			}
			?></h3>
			<dl class="ecreg-dl ecreg-dl-wide">
				<?php if ( $ecrs_dev_listed ) : ?>
				<dt><?php esc_html_e( 'Development site', 'wp-easycart' ); ?></dt><dd><?php echo esc_html( isset( $ecrs_license->siteurl ) ? $ecrs_license->siteurl : home_url() ); ?></dd>
				<dt><?php esc_html_e( 'Licensed through', 'wp-easycart' ); ?></dt><dd><?php echo esc_html( '' !== $ecrs_dev_live ? $ecrs_dev_live : '—' ); ?></dd>
				<?php else : ?>
				<dt><?php esc_html_e( 'Registered URL', 'wp-easycart' ); ?></dt><dd><?php echo esc_html( isset( $ecrs_license->siteurl ) ? $ecrs_license->siteurl : home_url() ); ?></dd>
				<?php endif; ?>
				<?php if ( $ecrs_v3 ) : ?>
				<dt><?php esc_html_e( 'Registered', 'wp-easycart' ); ?></dt><dd><?php echo esc_html( ! empty( $ecrs_license->date ) ? date_i18n( get_option( 'date_format' ), strtotime( $ecrs_license->date ) ) : '—' ); ?></dd>
				<dt><?php esc_html_e( 'License version', 'wp-easycart' ); ?></dt><dd>v3</dd>
				<?php else : ?>
				<dt><?php esc_html_e( 'Support & updates', 'wp-easycart' ); ?></dt>
				<?php if ( $ecrs_auto && ! $ecrs_lapsed && $ecrs_end_ts ) : /* 6.0.4: AutoPay */ ?>
				<dd><?php
				/* translators: %s: the date the license renews. */
				echo esc_html( sprintf( __( 'Renews automatically on %s', 'wp-easycart' ), date_i18n( get_option( 'date_format' ), $ecrs_end_ts ) ) );
				?></dd>
				<?php else : ?>
				<dd><?php echo $ecrs_end_ts ? esc_html( sprintf( $ecrs_lapsed ? __( 'Ended %s', 'wp-easycart' ) : __( 'Until %s', 'wp-easycart' ), date_i18n( get_option( 'date_format' ), $ecrs_end_ts ) ) ) : '—'; ?><?php if ( ! $ecrs_lapsed && $ecrs_end_ts ) : ?> <span class="ecreg-days<?php echo $ecrs_days < 60 ? ' is-soon' : ''; ?>"><?php echo esc_html( sprintf( _n( '%d day left', '%d days left', $ecrs_days, 'wp-easycart' ), $ecrs_days ) ); ?></span><?php endif; ?></dd>
				<?php endif; ?>
				<?php if ( '' !== $ecrs_key ) : ?><dt><?php esc_html_e( 'License key', 'wp-easycart' ); ?></dt><dd class="ecreg-mono"><?php echo esc_html( $ecrs_key ); ?></dd><?php endif; ?>
				<?php endif; ?>
			</dl>
			<?php if ( ! $ecrs_v3 && ! $ecrs_dev_listed ) : /* 6.0.4: the license email belongs to the live store's registration */ ?>
			<form action="<?php echo esc_url( $ecrs_email ); ?>" method="POST" class="ecreg-form ecreg-form-inline" novalidate="novalidate">
				<?php wp_nonce_field( 'wp-easycart-license-action', 'wpec_license_nonce' ); ?>
				<div class="ecreg-f">
					<label for="customeremail"><?php esc_html_e( 'License email', 'wp-easycart' ); ?> <span class="ecreg-fine"><?php esc_html_e( 'renewal receipts and notices go here', 'wp-easycart' ); ?></span></label>
					<div class="ecreg-inline"><input type="email" class="ecv2-input" name="customeremail" id="customeremail" value="<?php echo esc_attr( $ecrs_email_v ); ?>" autocomplete="email" /><button type="submit" class="ecv2-btn ecv2-btn-sm"><?php esc_html_e( 'Update', 'wp-easycart' ); ?></button></div>
				</div>
			</form>
			<?php endif; ?>
		</div>
	</div>

	<?php
	/** This action is documented above ( the development sites: the key's live store and its copies, with Remove ). */
	do_action( 'wp_easycart_admin_registration_dev_sites', 'activated' );
	?>

	<?php /* 6.0.1: a licensed store gets a direct path to support tickets, which live in the WP EasyCart account. */ ?>
	<div class="ecreg-support">
		<span class="dashicons dashicons-sos ecreg-support-icon" aria-hidden="true"></span>
		<div class="ecreg-support-text">
			<b><?php esc_html_e( 'Need a hand with your store?', 'wp-easycart' ); ?></b>
			<span><?php echo esc_html( $ecrs_lapsed ? __( 'Support tickets are answered while support and updates are active; renewing opens them again. The documentation is always open.', 'wp-easycart' ) : __( 'Your license includes support. Open a ticket from your WP EasyCart account and the team replies by email.', 'wp-easycart' ) ); ?></span>
		</div>
		<?php if ( ! $ecrs_lapsed ) : /* 6.0.3: no ticket button while tickets are not answered */ ?>
		<a class="ecv2-btn ecv2-btn-primary" href="<?php echo esc_url( $ecrs_account ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open a support ticket', 'wp-easycart' ); ?> <span class="dashicons dashicons-external" aria-hidden="true"></span></a>
		<?php endif; ?>
		<a class="ecv2-btn" href="http://docs.wpeasycart.com/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Documentation', 'wp-easycart' ); ?></a>
	</div>

	<div class="ecreg-paths ecreg-paths-3">
		<?php if ( $ecrs_lapsed ) : ?>
		<?php /* 6.0.3: renewing is the notice's button above; a store that bought a new license enters it here ( it replaces this one ). */ ?>
		<div class="ecreg-path">
			<span class="dashicons dashicons-admin-network ecreg-path-icon"></span>
			<h3><?php esc_html_e( 'Bought a new license?', 'wp-easycart' ); ?></h3>
			<p><?php esc_html_e( 'Enter the new key and it replaces the ended one on this site.', 'wp-easycart' ); ?></p>
			<form action="<?php echo esc_url( $ecrs_activate ); ?>" method="POST" id="wpeasycart_admin_form1" class="ecreg-form" novalidate="novalidate">
				<?php wp_nonce_field( 'wp-easycart-license-action', 'wpec_license_nonce' ); ?>
				<div class="ecreg-f"><label for="customername"><?php esc_html_e( 'Full name', 'wp-easycart' ); ?></label><input type="text" class="ecv2-input" name="customername" id="customername" required="required" autocomplete="name" /></div>
				<div class="ecreg-f"><label for="customeremail_activate"><?php esc_html_e( 'Email address', 'wp-easycart' ); ?></label><input type="email" class="ecv2-input" name="customeremail" id="customeremail_activate" required="required" autocomplete="email" value="<?php echo esc_attr( $ecrs_email_v ); ?>" /></div>
				<div class="ecreg-f"><label for="transactionkey"><?php esc_html_e( 'License key', 'wp-easycart' ); ?></label><input type="text" class="ecv2-input ecreg-mono" name="transactionkey" id="transactionkey" required="required" autocomplete="off" spellcheck="false" placeholder="XXXX-XXXX-XXXX-XXXX" /></div>
				<button type="submit" class="ecv2-btn ecreg-path-cta"><?php esc_html_e( 'Activate license', 'wp-easycart' ); ?></button>
			</form>
		</div>
		<?php else : ?>
		<div class="ecreg-path<?php echo ( $ecrs_end_ts && $ecrs_days <= 30 ) ? ' is-featured' : ''; ?>">
			<?php if ( $ecrs_end_ts && $ecrs_days <= 30 ) : ?><span class="ecreg-path-tag"><?php echo $ecrs_days <= 7 ? esc_html__( 'Ends this week', 'wp-easycart' ) : esc_html__( 'Renew before it lapses', 'wp-easycart' ); ?></span><?php endif; ?>
			<span class="dashicons dashicons-update ecreg-path-icon"></span>
			<h3><?php esc_html_e( 'Support & updates', 'wp-easycart' ); ?></h3>
			<p><?php esc_html_e( 'Renewing extends security fixes, new features and priority support for another year. Renew early and the time is added on — nothing is lost.', 'wp-easycart' ); ?></p>
			<a class="ecv2-btn <?php echo ( $ecrs_end_ts && $ecrs_days <= 30 ) ? 'ecv2-btn-primary ' : ''; ?>ecreg-path-cta" href="<?php echo esc_url( $ecrs_v3 ? $ecrs_account : $ecrs_renew ); ?>" target="_blank"><?php esc_html_e( 'Renew support & updates', 'wp-easycart' ); ?></a>
			<span class="ecreg-path-fine"><?php esc_html_e( 'Sign in with the account that bought this license so the credit applies.', 'wp-easycart' ); ?></span>
		</div>
		<?php endif; ?>
		<?php if ( ! $ecrs_premium ) : ?>
		<?php $ecrs_offer = class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::premium_offer() : array( 'mode' => 'get', 'cta' => __( 'See Premium', 'wp-easycart' ) ); ?>
		<div class="ecreg-path">
			<span class="dashicons dashicons-star-filled ecreg-path-icon"></span>
			<h3><?php esc_html_e( 'Upgrade to Premium', 'wp-easycart' ); ?></h3>
			<p><?php echo esc_html( 'upgrade' === $ecrs_offer['mode'] ? __( 'Everything in Pro plus every extension: ShipStation, Stamps.com, AvaTax, QuickBooks Desktop, Facebook & Instagram and the mobile apps, with QuickBooks Online and Xero coming soon. Change your plan in your WP EasyCart account, and your key stays the same.', 'wp-easycart' ) : __( 'Everything in Pro plus every extension: ShipStation, Stamps.com, AvaTax, QuickBooks Desktop, Facebook & Instagram and the mobile apps, with QuickBooks Online and Xero coming soon. Your key stays the same.', 'wp-easycart' ) ); ?></p>
			<a class="ecv2-btn ecreg-path-cta" href="<?php echo esc_url( $ecrs_v3 ? $ecrs_account : $ecrs_upgrade ); ?>" target="_blank"><?php echo esc_html( $ecrs_offer['cta'] ); ?> <span class="dashicons dashicons-external"></span></a>
		</div>
		<?php else : ?>
		<?php
		/* 6.0.2: extensions install, update and are managed from EasyCart › Extensions; WP EasyCart PRO installs
		   WP EasyCart Premium in one click where it can ( wp_easycart_premium_install_url ). */
		$ecrs_ext_install = ( ! $ecrs_lapsed && class_exists( 'wp_easycart_admin_extensions' ) ) ? wp_easycart_admin_extensions::premium_install_url() : '';
		$ecrs_ext_url     = class_exists( 'wp_easycart_admin_extensions' ) ? wp_easycart_admin_extensions::url() : $ecrs_account;
		?>
		<div class="ecreg-path">
			<span class="dashicons dashicons-admin-plugins ecreg-path-icon"></span>
			<h3><?php esc_html_e( 'Premium extensions', 'wp-easycart' ); ?></h3>
			<?php if ( '' !== $ecrs_ext_install ) : ?>
				<p><?php esc_html_e( 'Your license includes every extension. Install WP EasyCart Premium to add, update and manage them from EasyCart › Extensions.', 'wp-easycart' ); ?></p>
				<a class="ecv2-btn ecv2-btn-primary ecreg-path-cta" href="<?php echo esc_url( $ecrs_ext_install ); ?>"><?php esc_html_e( 'Install WP EasyCart Premium', 'wp-easycart' ); ?></a>
			<?php elseif ( $ecrs_lapsed ) : ?>
				<p><?php esc_html_e( 'Your extensions keep running with their current settings. Renew to install, update and change them.', 'wp-easycart' ); ?></p>
				<a class="ecv2-btn ecreg-path-cta" href="<?php echo esc_url( $ecrs_ext_url ); ?>"><?php esc_html_e( 'Open Extensions', 'wp-easycart' ); ?></a>
			<?php else : ?>
				<p><?php esc_html_e( 'ShipStation, Stamps.com, AvaTax, QuickBooks Desktop, Facebook & Instagram and the mobile apps are included with your license, and QuickBooks Online and Xero will be when they\'re released.', 'wp-easycart' ); ?></p>
				<a class="ecv2-btn ecreg-path-cta" href="<?php echo esc_url( $ecrs_ext_url ); ?>"><?php esc_html_e( 'Open Extensions', 'wp-easycart' ); ?></a>
			<?php endif; ?>
		</div>
		<?php endif; ?>
		<div class="ecreg-path">
			<?php if ( $ecrs_dev_listed ) : ?>
			<?php /* 6.0.4: on a development site the same form takes this copy off the key's list ( WP EasyCart PRO never calls the licensing server's deactivate here ). */ ?>
			<span class="dashicons dashicons-laptop ecreg-path-icon"></span>
			<h3><?php esc_html_e( 'Remove this development site', 'wp-easycart' ); ?></h3>
			<p><?php esc_html_e( 'Takes this copy off the license and frees its place for another development site. Your live store keeps its license.', 'wp-easycart' ); ?></p>
			<form action="<?php echo esc_url( $ecrs_deact ); ?>" method="POST" id="wpeasycart_admin_form2" class="ecreg-form" novalidate="novalidate" onsubmit="return window.confirm( <?php echo esc_attr( wp_json_encode( __( 'Remove this development site? WP EasyCart PRO here goes back to unlicensed until the key is entered again. Your live store keeps its license.', 'wp-easycart' ) ) ); ?> );">
				<?php wp_nonce_field( 'wp-easycart-license-action', 'wpec_license_nonce' ); ?>
				<div class="ecreg-f"><label for="transactionkey_deact"><?php esc_html_e( 'Confirm the license key', 'wp-easycart' ); ?></label><input type="text" class="ecv2-input ecreg-mono" name="transactionkey" id="transactionkey_deact" required="required" autocomplete="off" spellcheck="false" placeholder="XXXX-XXXX-XXXX-XXXX" /></div>
				<button type="submit" class="ecv2-btn ecreg-path-cta ecreg-danger"><?php esc_html_e( 'Remove this site', 'wp-easycart' ); ?></button>
			</form>
			<?php else : ?>
			<span class="dashicons dashicons-migrate ecreg-path-icon"></span>
			<h3><?php esc_html_e( 'Move this license', 'wp-easycart' ); ?></h3>
			<p><?php esc_html_e( 'Deactivate here to use the key on another site. Or skip this step: entering the key on the new site moves it automatically.', 'wp-easycart' ); ?></p>
			<form action="<?php echo esc_url( $ecrs_deact ); ?>" method="POST" id="wpeasycart_admin_form2" class="ecreg-form" novalidate="novalidate" onsubmit="return window.confirm( <?php echo wp_json_encode( $ecrs_premium ? __( 'Deactivate the Premium license on this site? The Premium panels will lock until a key is entered again.', 'wp-easycart' ) : __( 'Deactivate the Pro license on this site? The Pro panels will lock until a key is entered again.', 'wp-easycart' ) ); ?> );">
				<?php wp_nonce_field( 'wp-easycart-license-action', 'wpec_license_nonce' ); ?>
				<div class="ecreg-f"><label for="transactionkey_deact"><?php esc_html_e( 'Confirm the license key', 'wp-easycart' ); ?></label><input type="text" class="ecv2-input ecreg-mono" name="transactionkey" id="transactionkey_deact" required="required" autocomplete="off" spellcheck="false" placeholder="XXXX-XXXX-XXXX-XXXX" /></div>
				<button type="submit" class="ecv2-btn ecreg-path-cta ecreg-danger"><?php esc_html_e( 'Deactivate on this site', 'wp-easycart' ); ?></button>
			</form>
			<?php endif; ?>
		</div>
	</div>

	<?php endif; ?>

</div>