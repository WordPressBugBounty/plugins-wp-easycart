<?php
/**
 * Registration — PRO trial ( active or ended ), V2.
 *
 * Replaces trial.php. Same three-path layout as registration-none-v2.php:
 * upgrade ( featured ), activate a purchased key, and trial details. The two
 * legacy forms ( activateregistration, updateregistrationemail ) post to the
 * same actions with the same field names.
 *
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ectr_license   = wp_easycart_admin_license()->license_data;
$ectr_info      = get_option( 'wp_easycart_license_info' );
$ectr_key       = ( is_array( $ectr_info ) && isset( $ectr_info['transaction_key'] ) ) ? $ectr_info['transaction_key'] : '';
$ectr_email     = ( is_array( $ectr_info ) && isset( $ectr_info['customer_email'] ) ) ? $ectr_info['customer_email'] : '';
$ectr_end_ts    = strtotime( date( 'Y-m-d', strtotime( $ectr_license->support_end_date ) ) );
$ectr_ended     = ( $ectr_end_ts < strtotime( date( 'Y-m-d' ) ) );
$ectr_days      = $ectr_ended ? 0 : max( 0, class_exists( 'wp_easycart_admin_upsell' ) ? wp_easycart_admin_upsell::days_until( $ectr_end_ts ) : (int) round( ( $ectr_end_ts - strtotime( date( 'Y-m-d' ) ) ) / DAY_IN_SECONDS ) );
$ectr_end_label = date_i18n( get_option( 'date_format' ), $ectr_end_ts );
$ectr_upgrade   = 'https://www.wpeasycart.com/products/wp-easycart-trial-upgrade/' . ( '' !== $ectr_key ? '?transaction_key=' . rawurlencode( $ectr_key ) : '' );
$ectr_premium   = $ectr_upgrade . ( '' !== $ectr_key ? '&' : '?' ) . 'license_type=Premium';
$ectr_stats     = class_exists( 'wp_easycart_admin_upsell' ) ? wp_easycart_admin_upsell::stats() : array();
$ectr_activate  = self_admin_url( 'admin.php?page=wp-easycart-registration&subpage=registration&ec_action=activateregistration' );
$ectr_email_act = self_admin_url( 'admin.php?page=wp-easycart-registration&subpage=registration&ec_action=updateregistrationemail' );
?>
<div class="ecv2-wrap ecreg ecreg-trial<?php echo $ectr_ended ? ' is-ended' : ''; ?>">

	<div class="ecv2-page-header">
		<div class="ecv2-page-header-left">
			<span class="dashicons <?php echo $ectr_ended ? 'dashicons-lock' : 'dashicons-clock'; ?> ecv2-page-header-icon"></span>
			<h2 class="ecv2-page-title"><?php echo $ectr_ended ? esc_html__( 'Your PRO trial has ended', 'wp-easycart' ) : esc_html__( 'PRO trial', 'wp-easycart' ); ?></h2>
			<?php if ( ! $ectr_ended ) : ?>
			<span class="ecreg-trial-pill<?php echo $ectr_days <= 3 ? ' is-urgent' : ''; ?>"><?php echo esc_html( sprintf( _n( '%d day left', '%d days left', $ectr_days, 'wp-easycart' ), $ectr_days ) ); ?></span>
			<?php endif; ?>
		</div>
		<div class="ecv2-page-header-right">
			<a href="<?php echo esc_url( wp_easycart_admin()->helpsystem->print_docs_url( 'settings', 'registration', 'none' ) ); ?>" target="_blank" class="ecv2-btn ecv2-btn-ghost ecv2-btn-sm"><span class="dashicons dashicons-editor-help"></span> <?php esc_html_e( 'Help', 'wp-easycart' ); ?></a>
		</div>
	</div>

	<?php if ( $ectr_ended ) : ?>
	<div class="ecreg-notice is-ended">
		<span class="dashicons dashicons-warning"></span>
		<span><strong><?php echo esc_html( sprintf( __( 'The trial ended on %s.', 'wp-easycart' ), $ectr_end_label ) ); ?></strong> <?php esc_html_e( 'PRO panels are locked again. Your products, orders and settings are exactly as you left them — upgrade to pick up where you stopped.', 'wp-easycart' ); ?></span>
	</div>
	<?php else : ?>
	<p class="ecv2-page-intro">
		<?php echo esc_html( sprintf( __( 'Every PRO feature is unlocked until %s. Upgrade before then and nothing changes; let it lapse and the PRO panels lock with your data intact.', 'wp-easycart' ), $ectr_end_label ) ); ?>
	</p>
	<?php endif; ?>

	<div class="ecreg-paths">

		<!-- Upgrade -->
		<div class="ecreg-path is-featured">
			<span class="ecreg-path-tag"><?php echo $ectr_ended ? esc_html__( 'Pick up where you left off', 'wp-easycart' ) : esc_html__( 'Keep everything', 'wp-easycart' ); ?></span>
			<span class="dashicons dashicons-star-filled ecreg-path-icon"></span>
			<h3><?php esc_html_e( 'Upgrade this trial', 'wp-easycart' ); ?></h3>
			<p><?php esc_html_e( 'Your trial key becomes the license — no reinstall, no re-entering anything. Professional includes every PRO feature plus a year of priority support and updates; Premium adds every extension.', 'wp-easycart' ); ?></p>
			<ul class="ecreg-path-list">
				<li><?php esc_html_e( 'Instant — the site stays exactly as configured', 'wp-easycart' ); ?></li>
				<li><?php esc_html_e( 'Removes the 2% gateway fee for good', 'wp-easycart' ); ?></li>
				<li><?php esc_html_e( 'One license per site; move it any time', 'wp-easycart' ); ?></li>
			</ul>
			<div class="ecreg-path-actions">
				<a class="ecv2-btn ecv2-btn-primary ecreg-path-cta" href="<?php echo esc_url( $ectr_upgrade ); ?>" target="_blank"><?php esc_html_e( 'Upgrade to Professional', 'wp-easycart' ); ?></a>
				<a class="ecv2-btn ecreg-path-cta" href="<?php echo esc_url( $ectr_premium ); ?>" target="_blank"><?php esc_html_e( 'Upgrade to Premium', 'wp-easycart' ); ?></a>
			</div>
		</div>

		<!-- Activate a purchased key -->
		<div class="ecreg-path">
			<span class="dashicons dashicons-admin-network ecreg-path-icon"></span>
			<h3><?php esc_html_e( 'Already bought a license?', 'wp-easycart' ); ?></h3>
			<p><?php esc_html_e( 'Enter the key from your purchase email. It replaces the trial on this site immediately.', 'wp-easycart' ); ?></p>
			<form action="<?php echo esc_url( $ectr_activate ); ?>" method="POST" name="wpeasycart_admin_form" id="wpeasycart_admin_form1" class="ecreg-form" novalidate="novalidate">
				<div class="ecreg-f">
					<label for="customername"><?php esc_html_e( 'Full name', 'wp-easycart' ); ?></label>
					<input type="text" class="ecv2-input" name="customername" id="customername" required="required" autocomplete="name" />
				</div>
				<div class="ecreg-f">
					<label for="customeremail_activate"><?php esc_html_e( 'Email address', 'wp-easycart' ); ?></label>
					<input type="email" class="ecv2-input" name="customeremail" id="customeremail_activate" required="required" autocomplete="email" value="<?php echo esc_attr( $ectr_email ); ?>" />
				</div>
				<div class="ecreg-f">
					<label for="transactionkey"><?php esc_html_e( 'License key', 'wp-easycart' ); ?></label>
					<input type="text" class="ecv2-input ecreg-mono" name="transactionkey" id="transactionkey" required="required" autocomplete="off" spellcheck="false" placeholder="XXXX-XXXX-XXXX-XXXX" />
				</div>
				<button type="submit" class="ecv2-btn ecv2-btn-primary ecreg-path-cta"><?php esc_html_e( 'Activate license', 'wp-easycart' ); ?></button>
			</form>
		</div>

		<!-- Trial details -->
		<div class="ecreg-path">
			<span class="dashicons dashicons-info-outline ecreg-path-icon"></span>
			<h3><?php esc_html_e( 'Trial details', 'wp-easycart' ); ?></h3>
			<dl class="ecreg-dl">
				<dt><?php esc_html_e( 'Status', 'wp-easycart' ); ?></dt><dd><?php echo $ectr_ended ? esc_html__( 'Ended', 'wp-easycart' ) : esc_html__( 'Active', 'wp-easycart' ); ?></dd>
				<dt><?php echo $ectr_ended ? esc_html__( 'Ended', 'wp-easycart' ) : esc_html__( 'Ends', 'wp-easycart' ); ?></dt><dd><?php echo esc_html( $ectr_end_label ); ?></dd>
				<?php if ( '' !== $ectr_key ) : ?><dt><?php esc_html_e( 'Trial key', 'wp-easycart' ); ?></dt><dd class="ecreg-mono"><?php echo esc_html( $ectr_key ); ?></dd><?php endif; ?>
			</dl>
			<?php if ( ! $ectr_ended ) : ?>
			<form action="<?php echo esc_url( $ectr_email_act ); ?>" method="POST" name="wpeasycart_admin_form" class="ecreg-form" novalidate="novalidate">
				<div class="ecreg-f">
					<label for="customeremail"><?php esc_html_e( 'Trial email', 'wp-easycart' ); ?> <span class="ecreg-fine"><?php esc_html_e( 'where your upgrade receipt goes', 'wp-easycart' ); ?></span></label>
					<div class="ecreg-inline">
						<input type="email" class="ecv2-input" name="customeremail" id="customeremail" value="<?php echo esc_attr( $ectr_email ); ?>" autocomplete="email" />
						<button type="submit" class="ecv2-btn ecv2-btn-sm"><?php esc_html_e( 'Update', 'wp-easycart' ); ?></button>
					</div>
				</div>
			</form>
			<?php else : ?>
			<p class="ecreg-path-fine"><?php esc_html_e( 'Prefer the free edition? Deactivate and delete WP EasyCart PRO under Plugins. Nothing is lost; the PRO panels simply lock.', 'wp-easycart' ); ?></p>
			<a class="ecv2-btn ecv2-btn-ghost ecv2-btn-sm" href="<?php echo esc_url( self_admin_url( 'plugins.php' ) ); ?>"><?php esc_html_e( 'Go to Plugins', 'wp-easycart' ); ?></a>
			<?php endif; ?>
		</div>

	</div>

	<?php if ( ! empty( $ectr_stats['orders_30d'] ) && $ectr_stats['orders_30d'] >= 3 && ! empty( $ectr_stats['avg_order'] ) ) : ?>
	<p class="ecreg-stat"><span class="dashicons dashicons-chart-line"></span>
		<?php echo esc_html( sprintf( __( 'On your last 30 days (%1$s orders, %2$s average) the free edition\'s 2%% gateway fee would be about %3$s a month. A license removes it.', 'wp-easycart' ), number_format_i18n( $ectr_stats['orders_30d'] ), wp_easycart_admin_upsell::money( $ectr_stats['avg_order'] ), wp_easycart_admin_upsell::money( $ectr_stats['orders_30d'] * $ectr_stats['avg_order'] * 0.02 ) ) ); ?>
	</p>
	<?php endif; ?>

	<?php if ( $ectr_ended ) : ?>
	<div class="ecreg-upsell">
		<?php wp_easycart_admin()->show_upgrade(); ?>
	</div>
	<?php endif; ?>

</div>