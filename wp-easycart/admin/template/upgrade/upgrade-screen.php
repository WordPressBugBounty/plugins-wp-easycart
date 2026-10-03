<?php
/**
 * Upsell popup content. Rendered once per page by load_upsell_popup() with
 * the current page's context; upsell.js swaps in whichever context/feature a
 * locked control was clicked on ( data-upsell-* hooks below ).
 *
 * Deliberately no heading, ul, or li tags here — legacy admin CSS on some pages hides them.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! isset( $ecv2_upsell ) ) {
	$ecv2_upsell = wp_easycart_admin_upsell::entry( '' );
}
$ecv2_prem = ( 'premium' === $ecv2_upsell['plan'] );
$ecv2_trial_on = ( '' != apply_filters( 'wp_easycart_trial_start_content', 'true' ) );
?>
<?php $ecv2_update = ! empty( $ecv2_upsell['update'] ); /* 6.0.1: only a newer WP EasyCart PRO is missing: the update, not the plans */ ?>
<?php
/* 6.0.2: a Premium context ( the extensions ) offers Premium alone, worded for this store ( get it, upgrade Pro or a trial,
   renew ); the Pro card and the Pro trial link are hidden because neither includes extensions. */
$ecv2_prem_only  = $ecv2_prem && isset( $ecv2_upsell['prem_cta'] );
$ecv2_prem_title = $ecv2_prem_only ? $ecv2_upsell['prem_title'] : __( 'Premium', 'wp-easycart' );
$ecv2_prem_desc  = ( $ecv2_prem_only && '' !== $ecv2_upsell['prem_desc'] ) ? $ecv2_upsell['prem_desc'] : __( 'Everything in Pro, plus every extension: ShipStation, Stamps.com, AvaTax, QuickBooks Desktop and more, with QuickBooks Online and Xero coming soon.', 'wp-easycart' );
$ecv2_prem_cta   = $ecv2_prem_only ? $ecv2_upsell['prem_cta'] : __( 'Get Premium', 'wp-easycart' );
?>
<div class="ecv2-upsell<?php echo $ecv2_update ? ' is-update' : ''; ?><?php echo $ecv2_prem_only ? ' is-premium' : ''; ?><?php echo ( $ecv2_prem_only && 'renew' === $ecv2_upsell['offer_mode'] ) ? ' is-renew' : ''; ?>" data-upsell-context="<?php echo esc_attr( $ecv2_upsell['key'] ); ?>">
	<button type="button" class="ecv2-upsell-x" onclick="hide_pro_required(); return false;" aria-label="<?php esc_attr_e( 'Close', 'wp-easycart' ); ?>"><span class="dashicons dashicons-no-alt"></span></button>

	<div class="ecv2-upsell-hero">
		<span class="ecv2-cl-pro-badge ecv2-cl-pro-badge-lg" data-upsell-plan><?php echo esc_html( $ecv2_update ? $ecv2_upsell['update_badge'] : $ecv2_upsell['badge'] ); ?></span>
		<div class="ecv2-upsell-title" data-upsell-title><?php echo esc_html( $ecv2_upsell['headline'] ); ?></div>
		<div class="ecv2-upsell-lede" data-upsell-lede><?php echo esc_html( $ecv2_upsell['lede'] ); ?></div>
	</div>

	<div class="ecv2-upsell-stat" data-upsell-stat<?php echo '' === $ecv2_upsell['stat_line'] ? ' style="display:none;"' : ''; ?>>
		<span class="dashicons dashicons-chart-line"></span>
		<span class="ecv2-upsell-stat-text"><?php echo esc_html( $ecv2_upsell['stat_line'] ); ?></span>
	</div>

	<div class="ecv2-upsell-features" data-upsell-features>
		<?php foreach ( $ecv2_upsell['features'] as $ecv2_k => $ecv2_f ) : ?>
		<div class="ecv2-upsell-feature" data-feature="<?php echo esc_attr( $ecv2_k ); ?>">
			<span class="dashicons <?php echo esc_attr( $ecv2_f['icon'] ); ?>"></span>
			<span class="ecv2-upsell-feature-text"><strong><?php echo esc_html( $ecv2_f['title'] ); ?></strong><span><?php echo esc_html( $ecv2_f['desc'] ); ?></span></span>
		</div>
		<?php endforeach; ?>
	</div>

	<div class="ecv2-upsell-update" data-upsell-update>
		<span class="dashicons dashicons-update" aria-hidden="true"></span>
		<span data-upsell-update-text><?php echo esc_html( isset( $ecv2_upsell['update_text'] ) ? $ecv2_upsell['update_text'] : '' ); ?></span>
		<a class="ecv2-btn ecv2-btn-primary ecv2-btn-sm" data-upsell-update-link href="<?php echo esc_url( isset( $ecv2_upsell['update_url'] ) ? $ecv2_upsell['update_url'] : self_admin_url( 'plugins.php' ) ); ?>"><?php esc_html_e( 'Update WP EasyCart PRO', 'wp-easycart' ); ?></a>
	</div>

	<?php if ( wp_easycart_admin_upsell::pro_installed_inactive() ) : ?>
	<div class="ecv2-upsell-notice">
		<span class="dashicons dashicons-info-outline"></span>
		<span><?php esc_html_e( 'WP EasyCart PRO, the plugin that runs Pro and Premium licenses, is already installed on this site. It just needs switching on and a license.', 'wp-easycart' ); ?></span>
		<a class="ecv2-btn ecv2-btn-sm" href="<?php echo esc_url( wp_easycart_admin()->get_pro_activation_link() ); ?>"><?php esc_html_e( 'Activate', 'wp-easycart' ); ?></a>
	</div>
	<?php endif; ?>

	<div class="ecv2-upsell-plans">
		<a class="ecv2-upsell-plan<?php echo $ecv2_prem ? '' : ' is-recommended'; ?>" data-upsell-plan-card="pro" href="<?php echo esc_url( $ecv2_upsell['pro_url'] ); ?>" target="_blank" rel="noopener">
			<span class="ecv2-upsell-plan-tag"><?php esc_html_e( 'Recommended', 'wp-easycart' ); ?></span>
			<strong><?php esc_html_e( 'Pro', 'wp-easycart' ); ?></strong>
			<span class="ecv2-upsell-plan-desc"><?php esc_html_e( 'Everything shown here, plus a year of priority support and updates.', 'wp-easycart' ); ?></span>
			<span class="ecv2-btn ecv2-btn-primary"><?php esc_html_e( 'Get Pro', 'wp-easycart' ); ?> →</span>
		</a>
		<a class="ecv2-upsell-plan<?php echo $ecv2_prem ? ' is-recommended' : ''; ?>" data-upsell-plan-card="premium" href="<?php echo esc_url( $ecv2_upsell['prem_url'] ); ?>" target="_blank" rel="noopener">
			<span class="ecv2-upsell-plan-tag"><?php esc_html_e( 'Recommended', 'wp-easycart' ); ?></span>
			<strong data-upsell-prem-title><?php echo esc_html( $ecv2_prem_title ); ?></strong>
			<span class="ecv2-upsell-plan-desc" data-upsell-prem-desc><?php echo esc_html( $ecv2_prem_desc ); ?></span>
			<span class="ecv2-btn ecv2-btn-primary"><span data-upsell-prem-cta><?php echo esc_html( $ecv2_prem_cta ); ?></span> →</span>
		</a>
	</div>

	<div class="ecv2-upsell-foot">
		<?php if ( $ecv2_trial_on ) : ?>
		<a class="ecv2-upsell-trial" href="<?php echo esc_url( wp_easycart_admin()->pro_install_url( 'trial' ) ); ?>">
			<span class="dashicons dashicons-clock"></span>
			<span><strong><?php esc_html_e( 'Not ready to buy? Try Pro free for 14 days.', 'wp-easycart' ); ?></strong> <?php esc_html_e( 'No credit card, remove any time.', 'wp-easycart' ); ?></span>
		</a>
		<?php endif; ?>
		<a class="ecv2-upsell-docs" data-upsell-docs href="<?php echo esc_url( ! empty( $ecv2_upsell['docs'] ) ? $ecv2_upsell['docs'] : 'https://www.wpeasycart.com/wordpress-shopping-cart-features/' ); ?>" target="_blank" rel="noopener"><?php echo ! empty( $ecv2_upsell['docs'] ) ? esc_html__( 'How it works', 'wp-easycart' ) : esc_html__( 'Full feature list', 'wp-easycart' ); ?> ↗</a>
	</div>

	<?php do_action( 'wp_easycart_upsell_after', $ecv2_upsell ); ?>
</div>