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
<div class="ecv2-upsell" data-upsell-context="<?php echo esc_attr( $ecv2_upsell['key'] ); ?>">
	<button type="button" class="ecv2-upsell-x" onclick="hide_pro_required(); return false;" aria-label="<?php esc_attr_e( 'Close', 'wp-easycart' ); ?>"><span class="dashicons dashicons-no-alt"></span></button>

	<div class="ecv2-upsell-hero">
		<span class="ecv2-cl-pro-badge ecv2-cl-pro-badge-lg" data-upsell-plan><?php echo $ecv2_prem ? 'PREMIUM' : 'PRO'; ?></span>
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

	<?php if ( wp_easycart_admin_upsell::pro_installed_inactive() ) : ?>
	<div class="ecv2-upsell-notice">
		<span class="dashicons dashicons-info-outline"></span>
		<span><?php esc_html_e( 'PRO is already installed on this site — it just needs a license and switching on.', 'wp-easycart' ); ?></span>
		<a class="ecv2-btn ecv2-btn-sm" href="<?php echo esc_url( wp_easycart_admin()->get_pro_activation_link() ); ?>"><?php esc_html_e( 'Activate', 'wp-easycart' ); ?></a>
	</div>
	<?php endif; ?>

	<div class="ecv2-upsell-plans">
		<a class="ecv2-upsell-plan<?php echo $ecv2_prem ? '' : ' is-recommended'; ?>" data-upsell-plan-card="pro" href="<?php echo esc_url( $ecv2_upsell['pro_url'] ); ?>" target="_blank" rel="noopener">
			<span class="ecv2-upsell-plan-tag"><?php esc_html_e( 'Recommended', 'wp-easycart' ); ?></span>
			<strong><?php esc_html_e( 'Professional', 'wp-easycart' ); ?></strong>
			<span class="ecv2-upsell-plan-desc"><?php esc_html_e( 'Everything shown here, plus a year of priority support and updates.', 'wp-easycart' ); ?></span>
			<span class="ecv2-btn ecv2-btn-primary"><?php esc_html_e( 'Get Professional', 'wp-easycart' ); ?> →</span>
		</a>
		<a class="ecv2-upsell-plan<?php echo $ecv2_prem ? ' is-recommended' : ''; ?>" data-upsell-plan-card="premium" href="<?php echo esc_url( $ecv2_upsell['prem_url'] ); ?>" target="_blank" rel="noopener">
			<span class="ecv2-upsell-plan-tag"><?php esc_html_e( 'Recommended', 'wp-easycart' ); ?></span>
			<strong><?php esc_html_e( 'Premium', 'wp-easycart' ); ?></strong>
			<span class="ecv2-upsell-plan-desc"><?php esc_html_e( 'Professional plus every extension: ShipStation, QuickBooks, MailChimp, and more.', 'wp-easycart' ); ?></span>
			<span class="ecv2-btn ecv2-btn-primary"><?php esc_html_e( 'Get Premium', 'wp-easycart' ); ?> →</span>
		</a>
	</div>

	<div class="ecv2-upsell-foot">
		<?php if ( $ecv2_trial_on ) : ?>
		<a class="ecv2-upsell-trial" href="<?php echo esc_url( admin_url( 'admin.php?page=wp-easycart-registration&ec_trial=start' ) ); ?>">
			<span class="dashicons dashicons-clock"></span>
			<span><strong><?php esc_html_e( 'Not ready to buy? Try PRO free for 14 days.', 'wp-easycart' ); ?></strong> <?php esc_html_e( 'No credit card, remove any time.', 'wp-easycart' ); ?></span>
		</a>
		<?php endif; ?>
		<a class="ecv2-upsell-docs" data-upsell-docs href="<?php echo esc_url( ! empty( $ecv2_upsell['docs'] ) ? $ecv2_upsell['docs'] : 'https://www.wpeasycart.com/wordpress-shopping-cart-features/' ); ?>" target="_blank" rel="noopener"><?php echo ! empty( $ecv2_upsell['docs'] ) ? esc_html__( 'How it works', 'wp-easycart' ) : esc_html__( 'Full feature list', 'wp-easycart' ); ?> ↗</a>
	</div>

	<?php do_action( 'wp_easycart_upsell_after', $ecv2_upsell ); ?>
</div>