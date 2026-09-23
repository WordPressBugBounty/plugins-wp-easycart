<?php
/**
 * Store Status — license, readiness checks and features ( V2 ).
 *
 * Same checks and license states as the legacy template ( conditions and
 * links preserved verbatim ), laid out as: license hero + four readiness
 * checks, then a feature grid where locked tiles open the contextual upsell.
 * The 'wpeasycart_store_status_bubble_list_start/end' hooks still fire so
 * PRO / extensions can add their own status bubbles.
 *
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$status        = new wp_easycart_admin_store_status();
$license_data  = false;
$days_left     = 0;
$is_pro        = false;
$is_premium    = false;
$is_trial      = false;
$renew_url     = 'https://www.wpeasycart.com/wordpress-shopping-cart-pricing/';
$upgrade_url   = 'https://www.wpeasycart.com/wordpress-ecommerce-premium-edition/';
$transaction_key = '';
if ( function_exists( 'wp_easycart_admin_license' ) ) {
	$license_data    = wp_easycart_admin_license()->license_data;
	$license_info    = get_option( 'wp_easycart_license_info' );
	$transaction_key = ( is_array( $license_info ) && isset( $license_info['transaction_key'] ) ) ? $license_info['transaction_key'] : '';
	$days_left       = max( 0, class_exists( 'wp_easycart_admin_upsell' ) ? wp_easycart_admin_upsell::days_until( strtotime( $license_data->support_end_date ) ) : (int) round( ( strtotime( $license_data->support_end_date ) - time() ) / DAY_IN_SECONDS ) );
	$model           = strtolower( trim( (string) ( isset( $license_data->model_number ) ? $license_data->model_number : '' ) ) );
	$is_trial        = ! empty( $license_data->is_trial );
	$is_premium      = ( 'ec410' === $model );
	/* Any other active, non-trial license is PRO — don't depend on the exact model code. */
	$is_pro          = ( ! $is_premium && ! $is_trial );
	if ( $is_trial ) {
		$renew_url   = 'https://www.wpeasycart.com/products/wp-easycart-trial-upgrade/?transaction_key=' . $transaction_key;
		$upgrade_url = 'https://www.wpeasycart.com/products/wp-easycart-trial-upgrade/?transaction_key=' . $transaction_key . '&license_type=Premium';
	} else {
		$renew_url   = $is_pro ? 'https://www.wpeasycart.com/products/wp-easycart-professional-support-upgrades/?transaction_key=' . $transaction_key : 'https://www.wpeasycart.com/products/wp-easycart-premium-support-extensions/?transaction_key=' . $transaction_key;
		$upgrade_url = 'https://www.wpeasycart.com/products/wp-easycart-premium-support-extensions/?transaction_key=' . $transaction_key;
	}
}
$is_free      = ! $license_data;
$is_expired   = ( $license_data && $days_left <= 0 );
$has_pro_now  = ( $license_data && $days_left > 0 && ( $is_pro || $is_premium || $is_trial ) );
$has_prem_now = ( $license_data && $days_left > 0 && $is_premium );
$stats        = class_exists( 'wp_easycart_admin_upsell' ) ? wp_easycart_admin_upsell::stats() : array();
/* Plan name for copy: 'Pro/Premium' with no license, else this store's own plan ( a trial is a Pro trial ). */
$ecss_plan    = $is_free ? __( 'Pro/Premium', 'wp-easycart' ) : ( $is_premium ? __( 'Premium', 'wp-easycart' ) : __( 'Pro', 'wp-easycart' ) );

/* ---- License hero ( one state ) ------------------------------------- */
if ( $is_free ) {
	$hero = array( 'badge' => __( 'Free', 'wp-easycart' ), 'tone' => 'free', 'ring' => 0, 'title' => __( 'Free edition', 'wp-easycart' ),
		/* translators: %s: plan name, "Pro/Premium". */
		'text' => sprintf( __( 'Everything you need to sell is running. Card payments through Stripe, Square and PayPal carry a 2%% fee on the free edition; a %s license removes it and unlocks the features below.', 'wp-easycart' ), $ecss_plan ),
		'cta' => array( 'admin.php?page=wp-easycart-registration&ec_trial=start', __( 'Try Pro free for 14 days', 'wp-easycart' ), 'primary' ),
		'cta2' => array( 'admin.php?page=wp-easycart-registration', __( 'I have a license key', 'wp-easycart' ), '' ) );
} else if ( $is_trial && $days_left > 0 ) {
	$hero = array( 'badge' => __( 'Trial', 'wp-easycart' ), 'tone' => 'trial', 'ring' => min( 1, $days_left / 14 ), 'title' => sprintf( _n( '%d day left on your Pro trial', '%d days left on your Pro trial', $days_left, 'wp-easycart' ), $days_left ),
		'text' => __( 'Every Pro feature is unlocked. Upgrade before the trial ends and nothing changes; let it lapse and the Pro panels lock again with your data intact.', 'wp-easycart' ),
		'cta' => array( $upgrade_url, __( 'Upgrade now', 'wp-easycart' ), 'primary' ), 'cta2' => null );
} else if ( $is_trial ) {
	$hero = array( 'badge' => __( 'Trial ended', 'wp-easycart' ), 'tone' => 'expired', 'ring' => 0, 'title' => __( 'Your Pro trial has ended', 'wp-easycart' ),
		'text' => __( 'The Pro panels are locked again. Your products, orders and settings are exactly as you left them; upgrade to pick up where you stopped.', 'wp-easycart' ),
		'cta' => array( 'https://www.wpeasycart.com/wordpress-shopping-cart-pricing/', __( 'Upgrade now', 'wp-easycart' ), 'primary' ), 'cta2' => null );
} else if ( $is_expired ) {
	$hero = array( 'badge' => $ecss_plan, 'tone' => 'expired', 'ring' => 0, 'title' => $is_premium ? __( 'Premium license expired', 'wp-easycart' ) : __( 'Pro license expired', 'wp-easycart' ),
		'text' => __( 'Support and updates have lapsed, so the paid panels are locked. Renew to reopen them; no data is lost while you decide.', 'wp-easycart' ),
		'cta' => array( $renew_url, __( 'Renew license', 'wp-easycart' ), 'primary' ), 'cta2' => null );
} else {
	$soon = ( $days_left <= 30 );
	$crit = ( $days_left <= 7 );
	$hero = array( 'badge' => $ecss_plan, 'tone' => $crit ? 'expired' : ( $soon ? 'trial' : 'active' ), 'ring' => min( 1, $days_left / 365 ),
		'title' => $soon
			? sprintf( _n( '%1$s license ends tomorrow', '%1$s license ends in %2$d days', $days_left, 'wp-easycart' ), $ecss_plan, $days_left )
			: ( $is_premium ? __( 'Premium license active', 'wp-easycart' ) : __( 'Pro license active', 'wp-easycart' ) ),
		'text' => $soon
			/* translators: 1: support end date, 2: plan name, Pro or Premium. */
			? sprintf( __( 'Support & updates end %1$s. Renew now and a full year is added on top — nothing is lost by renewing early. Let it lapse and the %2$s panels lock, the 2%% gateway fee returns, and updates stop.', 'wp-easycart' ), date_i18n( get_option( 'date_format' ), strtotime( $license_data->support_end_date ) ), $ecss_plan )
			: sprintf( _n( '%d day of support and updates remaining.', '%d days of support and updates remaining.', $days_left, 'wp-easycart' ), $days_left ),
		'cta' => array( $renew_url, $soon ? __( 'Renew now', 'wp-easycart' ) : __( 'Renew', 'wp-easycart' ), $soon ? 'primary' : '' ), 'cta2' => $is_premium ? null : array( $upgrade_url, __( 'Upgrade to Premium', 'wp-easycart' ), '' ) );
}

/* ---- Readiness checks --------------------------------------------------
   Tax and shipping can be answered "not applicable": a downloads-only store has nothing to ship and a
   tax-free store has no rates to enter. Without that, the card sat on a warning forever. 6.0.1. */
/* 6.0.1: rates left behind by an earlier choice do not count while shipping is switched off. */
$shipping_on  = (bool) get_option( 'ec_option_use_shipping' );
$shipping_set = $shipping_on && ! ( false === $status->ec_using_method_shipping() && false === $status->ec_using_live_shipping() && false === $status->ec_using_price_shipping() && false === $status->ec_using_weight_shipping() && false === $status->ec_using_quantity_shipping() && false === $status->ec_using_percentage_shipping() && false === $status->ec_using_fraktjakt_shipping() );
$tax_set      = ! $status->ec_using_no_tax();
$tax_ack      = wp_easycart_admin_store_status::acknowledged( 'tax' );
$ship_ack     = wp_easycart_admin_store_status::acknowledged( 'shipping' );
$checks = array(
	array( 'ok' => true, 'set' => true, 'title' => __( 'Products', 'wp-easycart' ), 'ok_text' => sprintf( _n( '%s product in your catalog. Unlimited on every edition.', '%s products in your catalog. Unlimited on every edition.', isset( $stats['products'] ) ? $stats['products'] : 0, 'wp-easycart' ), number_format_i18n( isset( $stats['products'] ) ? $stats['products'] : 0 ) ), 'bad_text' => '', 'url' => 'admin.php?page=wp-easycart-products&subpage=products', 'link' => __( 'View products', 'wp-easycart' ) ),
	array(
		'ok'        => ( $tax_set || $tax_ack ),
		'set'       => $tax_set,
		'title'     => __( 'Taxes', 'wp-easycart' ),
		'ok_text'   => __( 'Tax or VAT is set up and applying at checkout.', 'wp-easycart' ),
		'bad_text'  => __( 'No tax or VAT is set up. Orders are charged tax-free.', 'wp-easycart' ),
		'url'       => 'admin.php?page=wp-easycart-settings&subpage=tax',
		'link'      => __( 'Tax setup', 'wp-easycart' ),
		'ack_key'   => 'tax',
		'ack'       => $tax_ack,
		'ack_text'  => __( 'This store charges no tax, as you told us. Add rates any time to change that.', 'wp-easycart' ),
		'ack_label' => __( 'We charge no tax', 'wp-easycart' ),
	),
	array(
		'ok'        => ( $shipping_set || $ship_ack ),
		'set'       => $shipping_set,
		'title'     => __( 'Shipping', 'wp-easycart' ),
		'ok_text'   => __( 'Shipping rates are set up for your store.', 'wp-easycart' ),
		'bad_text'  => __( 'No shipping method is set up. Shoppers cannot be charged for delivery.', 'wp-easycart' ),
		'url'       => 'admin.php?page=wp-easycart-settings&subpage=shipping-rates',
		'link'      => __( 'Shipping rates', 'wp-easycart' ),
		'ack_key'   => 'shipping',
		'ack'       => $ship_ack,
		'ack_text'  => __( 'Nothing is shipped from this store, as you told us. Add rates any time to change that.', 'wp-easycart' ),
		'ack_label' => __( 'We ship nothing', 'wp-easycart' ),
	),
	array( 'ok' => ! $status->ec_no_payment_selected(), 'set' => ! $status->ec_no_payment_selected(), 'title' => __( 'Payment', 'wp-easycart' ), 'ok_text' => __( 'A payment method is set up and taking orders.', 'wp-easycart' ), 'bad_text' => __( 'No payment method is set up. Shoppers cannot check out.', 'wp-easycart' ), 'url' => 'admin.php?page=wp-easycart-settings&subpage=payment', 'link' => __( 'Payment setup', 'wp-easycart' ) ),
);
$ready = 0;
foreach ( $checks as $c ) { if ( $c['ok'] ) { $ready++; } }

/* ---- Feature tiles ( same set as the legacy bubbles ) ---------------- */
/* tier: pro | premium. When unlocked: link. When locked: upsell context/feature. */
/* Coupons and promotions are one Offers tile since 6.0.0 ( Marketing > Offers; unconverted items sit under its Legacy tab ). */
$offer_counts = ( $has_pro_now && method_exists( $status, 'active_offers_count' ) ) ? $status->active_offers_count() : array( 'offers' => 0, 'legacy' => 0 );
if ( $offer_counts['offers'] > 0 ) {
	$offers_desc = sprintf( _n( '%s active offer running right now.', '%s active offers running right now.', $offer_counts['offers'], 'wp-easycart' ), number_format_i18n( $offer_counts['offers'] ) );
} elseif ( $offer_counts['legacy'] > 0 ) {
	$offers_desc = sprintf( _n( 'No offers yet; %s legacy coupon or promotion is waiting under the Legacy tab.', 'No offers yet; %s legacy coupons and promotions are waiting under the Legacy tab.', $offer_counts['legacy'], 'wp-easycart' ), number_format_i18n( $offer_counts['legacy'] ) );
} else {
	$offers_desc = __( 'Coupon codes, BOGO, free shipping and spend-threshold deals in one place.', 'wp-easycart' );
}
$features = array(
	array( 'tier' => 'pro', 'icon' => 'dashicons-megaphone', 'title' => __( 'Offers', 'wp-easycart' ), 'desc' => $offers_desc, 'url' => 'admin.php?page=wp-easycart-rates&subpage=offers', 'link' => __( 'View offers', 'wp-easycart' ), 'ctx' => 'offers' ),
	array( 'tier' => 'pro', 'icon' => 'dashicons-update', 'title' => __( 'Subscriptions', 'wp-easycart' ), 'desc' => __( 'Recurring billing through Stripe.', 'wp-easycart' ), 'url' => 'admin.php?page=wp-easycart-orders&subpage=subscriptions', 'link' => __( 'View subscriptions', 'wp-easycart' ), 'ctx' => 'subscriptions' ),
	array( 'tier' => 'pro', 'icon' => 'dashicons-download', 'title' => __( 'Downloads', 'wp-easycart' ), 'desc' => __( 'Digital products with limits, expiry and delivery logs.', 'wp-easycart' ), 'url' => 'admin.php?page=wp-easycart-orders&subpage=downloads', 'link' => __( 'Manage downloads', 'wp-easycart' ), 'ctx' => 'downloads' ),
	array( 'tier' => 'pro', 'icon' => 'dashicons-tickets', 'title' => __( 'Gift cards', 'wp-easycart' ), 'desc' => __( 'Sell cards, track balances, issue store credit.', 'wp-easycart' ), 'url' => 'admin.php?page=wp-easycart-rates&subpage=gift-cards', 'link' => __( 'View gift cards', 'wp-easycart' ), 'ctx' => 'giftcards' ),
	/* 6.0.0: Stamps.com replaces the QuickBooks tile ( the QuickBooks extension targets QuickBooks Desktop, which few stores still use ). */
	array( 'tier' => 'premium', 'icon' => 'dashicons-tag', 'title' => __( 'Stamps.com', 'wp-easycart' ), 'desc' => __( 'Buy and print USPS postage labels for your orders.', 'wp-easycart' ), 'url' => 'https://www.wpeasycart.com/my-account/', 'link' => __( 'Download from your account', 'wp-easycart' ), 'ctx' => 'default' ),
	array( 'tier' => 'premium', 'icon' => 'dashicons-airplane', 'title' => __( 'ShipStation', 'wp-easycart' ), 'desc' => __( 'Labels and fulfillment from one place.', 'wp-easycart' ), 'url' => 'https://www.wpeasycart.com/my-account/', 'link' => __( 'Download from your account', 'wp-easycart' ), 'ctx' => 'default' ),
	array( 'tier' => 'premium', 'icon' => 'dashicons-facebook', 'title' => __( 'Facebook & Instagram', 'wp-easycart' ), 'desc' => __( 'Catalog sync for social shops.', 'wp-easycart' ), 'url' => 'https://www.wpeasycart.com/my-account/', 'link' => __( 'Download from your account', 'wp-easycart' ), 'ctx' => 'default' ),
	array( 'tier' => 'premium', 'icon' => 'dashicons-smartphone', 'title' => __( 'Mobile apps', 'wp-easycart' ), 'desc' => __( 'Manage orders from your phone.', 'wp-easycart' ), 'url' => 'https://www.wpeasycart.com/my-account/', 'link' => __( 'Get the apps', 'wp-easycart' ), 'ctx' => 'default' ),
);
$locked_pro = 0; $locked_prem = 0;
foreach ( $features as $f ) {
	$unlocked = ( 'pro' === $f['tier'] ) ? $has_pro_now : $has_prem_now;
	if ( ! $unlocked ) { if ( 'pro' === $f['tier'] ) { $locked_pro++; } else { $locked_prem++; } }
}
$ring_r = 26; $ring_c = 2 * M_PI * $ring_r;
?>
<div class="ecv2-wrap ecss">

	<div class="ecv2-page-header">
		<div class="ecv2-page-header-left">
			<span class="dashicons dashicons-admin-network ecv2-page-header-icon"></span>
			<h2 class="ecv2-page-title"><?php esc_html_e( 'Store status', 'wp-easycart' ); ?></h2>
		</div>
		<div class="ecv2-page-header-right">
			<a href="https://www.wpeasycart.com/professional-edition-ecommerce/" target="_blank" class="ecv2-btn ecv2-btn-ghost ecv2-btn-sm"><?php esc_html_e( 'About Pro', 'wp-easycart' ); ?></a>
			<a href="https://www.wpeasycart.com/wordpress-ecommerce-premium-edition/" target="_blank" class="ecv2-btn ecv2-btn-ghost ecv2-btn-sm"><?php esc_html_e( 'About Premium', 'wp-easycart' ); ?></a>
		</div>
	</div>

	<!-- ============================ HERO ============================ -->
	<div class="ecss-hero">
		<div class="ecss-license ecss-tone-<?php echo esc_attr( $hero['tone'] ); ?>">
			<div class="ecss-ring" aria-hidden="true">
				<svg viewBox="0 0 64 64"><circle class="ecss-ring-bg" cx="32" cy="32" r="<?php echo (int) $ring_r; ?>"/><circle class="ecss-ring-fg" cx="32" cy="32" r="<?php echo (int) $ring_r; ?>" style="stroke-dasharray:<?php echo esc_attr( round( $ring_c, 2 ) ); ?>;stroke-dashoffset:<?php echo esc_attr( round( $ring_c * ( 1 - (float) $hero['ring'] ), 2 ) ); ?>;"/></svg>
				<span class="ecss-ring-label"><?php echo esc_html( $hero['badge'] ); ?></span>
			</div>
			<div class="ecss-license-copy">
				<h3><?php echo esc_html( $hero['title'] ); ?></h3>
				<p><?php echo esc_html( $hero['text'] ); ?></p>
				<div class="ecss-license-actions">
					<a class="ecv2-btn <?php echo 'primary' === $hero['cta'][2] ? 'ecv2-btn-primary' : ''; ?>" href="<?php echo esc_url( $hero['cta'][0] ); ?>"<?php echo ( 0 === strpos( $hero['cta'][0], 'http' ) ) ? ' target="_blank"' : ''; ?>><?php echo esc_html( $hero['cta'][1] ); ?></a>
					<?php if ( ! empty( $hero['cta2'] ) ) : ?>
					<a class="ecv2-btn" href="<?php echo esc_url( $hero['cta2'][0] ); ?>"<?php echo ( 0 === strpos( $hero['cta2'][0], 'http' ) ) ? ' target="_blank"' : ''; ?>><?php echo esc_html( $hero['cta2'][1] ); ?></a>
					<?php endif; ?>
					<?php if ( $is_free && class_exists( 'wp_easycart_admin_upsell' ) ) : ?>
					<button type="button" class="ecv2-btn ecv2-btn-ghost" onclick="ecdv2_upsell( { context: 'default' } ); return false;"><?php esc_html_e( "See what's included", 'wp-easycart' ); ?></button>
					<?php endif; ?>
				</div>
				<?php if ( ! $is_free && ! $is_trial && ! $is_expired && $days_left <= 30 && class_exists( 'wp_easycart_admin_upsell' ) ) : ?>
				<ul class="ecss-stakes">
					<?php foreach ( array_slice( wp_easycart_admin_upsell::renewal_stakes(), 0, 4 ) as $ecss_stake ) : ?><li><?php echo esc_html( $ecss_stake ); ?></li><?php endforeach; ?>
				</ul>
				<?php endif; ?>
				<?php if ( $is_free && ! empty( $stats['orders_30d'] ) && $stats['orders_30d'] >= 3 && ! empty( $stats['avg_order'] ) ) : ?>
				<p class="ecss-fee-line"><span class="dashicons dashicons-chart-line"></span>
					<?php echo esc_html( sprintf( __( 'At %1$s orders a month averaging %2$s, the 2%% free-edition fee is roughly %3$s a month — more than a Pro license.', 'wp-easycart' ), number_format_i18n( $stats['orders_30d'] ), wp_easycart_admin_upsell::money( $stats['avg_order'] ), wp_easycart_admin_upsell::money( $stats['orders_30d'] * $stats['avg_order'] * 0.02 ) ) ); ?>
				</p>
				<?php endif; ?>
			</div>
		</div>

		<div class="ecss-checks">
			<div class="ecss-checks-head">
				<span><?php esc_html_e( 'Store readiness', 'wp-easycart' ); ?></span>
				<span class="ecss-checks-score<?php echo 4 === $ready ? ' is-ok' : ''; ?>"><?php echo esc_html( sprintf( __( '%1$d of %2$d ready', 'wp-easycart' ), $ready, count( $checks ) ) ); ?></span>
			</div>
			<?php
			foreach ( $checks as $c ) :
				$ecss_ack_only = ( ! empty( $c['ack'] ) && empty( $c['set'] ) );
				$ecss_state    = $ecss_ack_only ? ' is-ack' : ( $c['ok'] ? ' is-ok' : ' is-bad' );
				$ecss_text     = $ecss_ack_only ? $c['ack_text'] : ( $c['ok'] ? $c['ok_text'] : $c['bad_text'] );
			?>
			<div class="ecss-check<?php echo esc_attr( $ecss_state ); ?>">
				<span class="ecss-check-dot"><span class="dashicons <?php echo $c['ok'] ? 'dashicons-yes' : 'dashicons-warning'; ?>"></span></span>
				<span class="ecss-check-text"><strong><?php echo esc_html( $c['title'] ); ?></strong><span><?php echo esc_html( $ecss_text ); ?></span></span>
				<span class="ecss-check-actions">
					<?php if ( ! empty( $c['ack_key'] ) && empty( $c['set'] ) ) : ?>
					<button type="button" class="ecss-check-skip" data-ecss-ack="<?php echo esc_attr( $c['ack_key'] ); ?>" data-ecss-on="<?php echo $ecss_ack_only ? '0' : '1'; ?>">
						<?php echo esc_html( $ecss_ack_only ? __( 'Undo', 'wp-easycart' ) : $c['ack_label'] ); ?>
					</button>
					<?php endif; ?>
					<a class="ecss-check-link" href="<?php echo esc_url( $c['url'] ); ?>"><?php echo esc_html( $c['link'] ); ?> <span class="dashicons dashicons-arrow-right-alt2"></span></a>
				</span>
			</div>
			<?php endforeach; ?>
			<?php
			/*
			 * 6.0.1: "we charge no tax" / "we ship nothing" settle the check without switching anything on.
			 * A reload is enough to redraw the card: the score, the icons and the Undo state all come from
			 * the same two options.
			 */
			?>
			<script>
			jQuery( function( $ ) {
				$( '.ecss-check-skip' ).on( 'click', function() {
					var $btn = $( this );
					$btn.prop( 'disabled', true );
					$.post( window.ajaxurl, {
						action: 'ecv2_status_ack',
						key: $btn.data( 'ecss-ack' ),
						on: $btn.data( 'ecss-on' ),
						nonce: '<?php echo esc_js( wp_create_nonce( wp_easycart_admin_store_status::ACK_NONCE ) ); ?>'
					} ).done( function( r ) {
						if ( r && r.success ) { window.location.reload(); return; }
						$btn.prop( 'disabled', false );
					} ).fail( function() { $btn.prop( 'disabled', false ); } );
				} );
			} );
			</script>
		</div>
	</div>

	<!-- ========================== FEATURES ========================== -->
	<div class="ecss-features-head">
		<h3><?php echo esc_html( $has_prem_now ? __( 'Your features', 'wp-easycart' ) : ( $has_pro_now ? __( 'Your features and Premium extensions', 'wp-easycart' ) : __( 'What Pro and Premium add', 'wp-easycart' ) ) ); ?></h3>
		<?php if ( $locked_pro || $locked_prem ) : ?>
		<span class="ecss-features-sub">
			<?php
			$bits = array();
			if ( $locked_pro )  { /* translators: 1: number of features, 2: plan name ( Pro/Premium, Pro or Premium ). */ $bits[] = sprintf( _n( '%1$d %2$s feature locked', '%1$d %2$s features locked', $locked_pro, 'wp-easycart' ), $locked_pro, $ecss_plan ); }
			if ( $locked_prem ) { $bits[] = sprintf( _n( '%d Premium extension locked', '%d Premium extensions locked', $locked_prem, 'wp-easycart' ), $locked_prem ); }
			echo esc_html( implode( ' · ', $bits ) );
			?>
		</span>
		<?php endif; ?>
	</div>

	<div class="ecss-tiles">
		<?php foreach ( $features as $f ) :
			$unlocked = ( 'pro' === $f['tier'] ) ? $has_pro_now : $has_prem_now;
			$expired_tier = ( $is_expired && ( ( 'pro' === $f['tier'] && ( $is_pro || $is_premium ) ) || ( 'premium' === $f['tier'] && $is_premium ) ) );
			$external = ( 0 === strpos( $f['url'], 'http' ) );
			if ( $unlocked ) : ?>
			<a class="ecss-tile is-on" href="<?php echo esc_url( $f['url'] ); ?>"<?php echo $external ? ' target="_blank"' : ''; ?>>
				<span class="dashicons <?php echo esc_attr( $f['icon'] ); ?> ecss-tile-icon"></span>
				<span class="ecss-tile-state"><span class="dashicons dashicons-yes"></span></span>
				<strong><?php echo esc_html( $f['title'] ); ?></strong>
				<span class="ecss-tile-desc"><?php echo esc_html( $f['desc'] ); ?></span>
				<span class="ecss-tile-link"><?php echo esc_html( $f['link'] ); ?></span>
			</a>
			<?php elseif ( $expired_tier ) : ?>
			<a class="ecss-tile is-expired" href="<?php echo esc_url( $renew_url ); ?>" target="_blank">
				<span class="dashicons <?php echo esc_attr( $f['icon'] ); ?> ecss-tile-icon"></span>
				<span class="ecss-tile-pill is-expired"><?php esc_html_e( 'Renew', 'wp-easycart' ); ?></span>
				<strong><?php echo esc_html( $f['title'] ); ?></strong>
				<span class="ecss-tile-desc"><?php esc_html_e( 'Locked until your license is renewed.', 'wp-easycart' ); ?></span>
				<span class="ecss-tile-link"><?php esc_html_e( 'Renew license', 'wp-easycart' ); ?></span>
			</a>
			<?php else : ?>
			<button type="button" class="ecss-tile is-locked is-<?php echo esc_attr( $f['tier'] ); ?>" onclick="<?php echo ( 'premium' === $f['tier'] ) ? "window.open( '" . esc_js( $upgrade_url ) . "', '_blank' );" : "ecdv2_upsell( { context: '" . esc_js( $f['ctx'] ) . "' } );"; ?> return false;">
				<span class="dashicons <?php echo esc_attr( $f['icon'] ); ?> ecss-tile-icon"></span>
				<span class="ecss-tile-pill"><?php echo esc_html( wp_easycart_admin_edition::badge( $f['tier'] ) ); ?></span>
				<strong><?php echo esc_html( $f['title'] ); ?></strong>
				<span class="ecss-tile-desc"><?php echo esc_html( $f['desc'] ); ?></span>
				<span class="ecss-tile-link"><?php echo 'premium' === $f['tier'] ? esc_html__( 'About Premium', 'wp-easycart' ) : esc_html__( 'See how it works', 'wp-easycart' ); ?></span>
			</button>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>

	<?php
	/* Third-party / PRO status bubbles keep rendering through the legacy hooks. */
	ob_start();
	do_action( 'wpeasycart_store_status_bubble_list_start' );
	do_action( 'wpeasycart_store_status_bubble_list_end' );
	$ecss_hook_html = trim( ob_get_clean() );
	if ( '' !== $ecss_hook_html ) {
		echo '<div class="ecdv2-card ecss-hook-card"><div class="ecdv2-card-header"><h3 class="ecdv2-card-title">' . esc_html__( 'Extensions', 'wp-easycart' ) . '</h3></div><div class="ecdv2-card-body ecss-hook-body">' . $ecss_hook_html . '<div style="clear:both;"></div></div></div>'; // Hook output.
	}
	?>

	<?php if ( $is_free ) : ?>
	<div class="ecss-upsell">
		<?php wp_easycart_admin()->show_upgrade(); ?>
	</div>
	<?php endif; ?>

</div>