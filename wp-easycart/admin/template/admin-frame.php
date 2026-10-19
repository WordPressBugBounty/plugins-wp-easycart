<?php
/**
 * WP EasyCart Admin Shell V2.
 *
 * The old ~50-rule inline <style> block is replaced by a single call to
 * wp_easycart_shell_output_root_css() (see inc/wp_easycart_admin_frame_theme.php).
 * All layout/skin rules live in css/admin-frame-v2.css, which re-derives shades
 * with color-mix() on modern browsers.
 *
 * Preserved hooks: wp_easycart_admin_mobile_navigation,
 * wp_easycart_admin_upsell_popup, wp_easycart_admin_left_navigation,
 * wp_easycart_admin_head_navigation, wp_easycart_admin_messages,
 * wp_easycart_admin_shell_content.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

wp_easycart_shell_output_root_css();

$ecsh_status = wp_easycart_shell_store_status();

/**
 * 6.0.4: a screen that wants room for its content can fold the EasyCart menu to icons ( the product editor does ). The
 * answer is false, or folded ( bool, how the menu starts ), action ( the AJAX action the fold button posts menu = mini |
 * full to, with nonce ) and nonce.
 *
 * @since 6.0.4
 * @param array|false $fold false: the menu does not fold on this screen.
 */
$ecsh_fold = apply_filters( 'wp_easycart_admin_frame_fold', false );
$ecsh_fold = ( is_array( $ecsh_fold ) && ! empty( $ecsh_fold['action'] ) ) ? $ecsh_fold : false;
?>

<?php do_action( 'wp_easycart_admin_upsell_popup' ); ?>
<?php
/* License renewal popup — opened from the top-bar pill and the sidebar block. */
$ecsh_renewal_m = class_exists( 'wp_easycart_admin_upsell' ) ? wp_easycart_admin_upsell::renewal() : null;
if ( $ecsh_renewal_m && 'ok' !== $ecsh_renewal_m['tone'] ) :
	$ecsh_stakes = wp_easycart_admin_upsell::renewal_stakes( $ecsh_renewal_m );
	$ecsh_lapsed = ( 'lapsed' === $ecsh_renewal_m['tone'] );
	$ecsh_gate   = wp_easycart_admin_upsell::renewal_gate_due( $ecsh_renewal_m );
	$ecsh_snooze_nonce = wp_create_nonce( 'wpec-renewal-snooze' );
?>
<div class="ecsh-renewal-modal is-<?php echo esc_attr( $ecsh_renewal_m['tone'] ); ?><?php echo $ecsh_lapsed ? ' is-gate' : ''; ?>" id="ecsh_renewal_modal" role="dialog" aria-modal="true" aria-labelledby="ecsh_renewal_title" style="display:none;" data-gate="<?php echo $ecsh_gate ? '1' : '0'; ?>" data-snooze-nonce="<?php echo esc_attr( $ecsh_snooze_nonce ); ?>"<?php echo $ecsh_lapsed ? '' : ' onclick="if ( event.target === this ) { ecsh_renewal_close(); }"'; ?>>
	<div class="ecsh-renewal-card" tabindex="-1">
		<?php if ( ! $ecsh_lapsed ) : ?>
		<button type="button" class="ecsh-renewal-x" onclick="ecsh_renewal_close();" aria-label="<?php esc_attr_e( 'Close', 'wp-easycart' ); ?>"><span class="dashicons dashicons-no-alt"></span></button>
		<?php endif; ?>
		<div class="ecsh-renewal-head">
			<span class="ecsh-renewal-icon"><span class="dashicons <?php echo $ecsh_lapsed ? 'dashicons-lock' : 'dashicons-clock'; ?>"></span></span>
			<div>
				<span class="ecsh-renewal-eyebrow"><?php echo esc_html( $ecsh_renewal_m['edition'] ); ?> &middot; <?php esc_html_e( 'Support & updates', 'wp-easycart' ); ?></span>
				<h3 id="ecsh_renewal_title"><?php
				if ( $ecsh_lapsed ) { echo esc_html( sprintf( __( 'Your license lapsed on %s', 'wp-easycart' ), $ecsh_renewal_m['end_fmt'] ) ); }
				else { echo esc_html( sprintf( _n( 'Your license ends tomorrow, %2$s', 'Your license ends in %1$d days, on %2$s', $ecsh_renewal_m['days'], 'wp-easycart' ), $ecsh_renewal_m['days'], $ecsh_renewal_m['end_fmt'] ) ); }
				?></h3>
			</div>
		</div>
		<p class="ecsh-renewal-lead"><?php
		if ( $ecsh_lapsed ) { esc_html_e( 'Right now on this store, while the license is lapsed:', 'wp-easycart' ); }
		else { esc_html_e( 'Turn on AutoPay in your WP EasyCart account and your license renews by itself. If the license lapses:', 'wp-easycart' ); }
		?></p>
		<ul class="ecsh-renewal-stakes">
			<?php foreach ( $ecsh_stakes as $ecsh_stake ) : ?><li><?php echo esc_html( $ecsh_stake ); ?></li><?php endforeach; ?>
		</ul>
		<?php if ( $ecsh_lapsed ) : ?>
		<p class="ecsh-renewal-keep"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Nothing has been deleted. Renewing reopens everything within a few minutes; switching to free keeps your products, orders and settings.', 'wp-easycart' ); ?></p>
		<?php endif; ?>
		<div class="ecsh-renewal-actions">
			<a class="ecv2-btn ecv2-btn-primary" href="<?php echo esc_url( $ecsh_renewal_m['url'] ); ?>" target="_blank"><?php echo ! empty( $ecsh_renewal_m['auto_renew'] ) ? esc_html__( 'Update payment', 'wp-easycart' ) : ( $ecsh_lapsed ? esc_html__( 'Renew & reopen', 'wp-easycart' ) : esc_html__( 'Renew now', 'wp-easycart' ) ); /* 6.0.4: AutoPay */ ?> <span class="dashicons dashicons-external"></span></a>
			<a class="ecv2-btn" href="<?php echo esc_url( self_admin_url( 'admin.php?page=wp-easycart-registration' ) ); ?>"><?php esc_html_e( 'License details', 'wp-easycart' ); ?></a>
			<?php if ( $ecsh_lapsed ) : ?>
			<button type="button" class="ecv2-btn ecv2-btn-ghost ecsh-renewal-later" onclick="ecsh_renewal_snooze();"><?php esc_html_e( 'Remind me tomorrow', 'wp-easycart' ); ?></button>
			<?php endif; ?>
		</div>
		<div class="ecsh-renewal-foot">
			<span class="ecsh-renewal-fine"><?php esc_html_e( 'Sign in with the account that bought this license so the renewal applies to it.', 'wp-easycart' ); ?></span>
			<?php $ecsh_deact = $ecsh_lapsed ? wp_easycart_admin_upsell::pro_deactivate_url() : ''; ?>
			<?php if ( '' !== $ecsh_deact ) : ?>
			<a class="ecsh-renewal-free" href="<?php echo esc_url( $ecsh_deact ); ?>" onclick="return window.confirm( <?php echo esc_attr( wp_json_encode( ( ! empty( $ecsh_renewal_m['premium'] ) ? __( 'Switch to the free edition? This deactivates the WP EasyCart PRO plugin, which runs your Premium license. Your products, orders and settings are kept; Premium features stop until you renew and activate it again.', 'wp-easycart' ) : __( 'Switch to the free edition? This deactivates the WP EasyCart PRO plugin, which runs your Pro license. Your products, orders and settings are kept; Pro features stop until you renew and activate it again.', 'wp-easycart' ) ) ) ); ?> );"><?php esc_html_e( 'Switch to the free edition instead', 'wp-easycart' ); ?></a>
			<?php endif; ?>
		</div>
	</div>
</div>
<script>
function ecsh_renewal_open() { var m = document.getElementById( 'ecsh_renewal_modal' ); if ( m ) { m.style.display = 'flex'; document.body.classList.add( 'ecsh-renewal-open' ); var c = m.querySelector( '.ecsh-renewal-card' ); if ( c ) { c.focus( { preventScroll: true } ); } } return false; }
function ecsh_renewal_close() { var m = document.getElementById( 'ecsh_renewal_modal' ); if ( m ) { m.style.display = 'none'; document.body.classList.remove( 'ecsh-renewal-open' ); } return false; }
/* Lapsed gate: closing is a choice — "Remind me tomorrow" snoozes it per user for a day. */
function ecsh_renewal_snooze() { var m = document.getElementById( 'ecsh_renewal_modal' ); if ( m && window.jQuery ) { jQuery.post( ajaxurl, { action: 'ec_admin_ajax_ecv2_dismiss_renewal', which: 'gate', nonce: m.getAttribute( 'data-snooze-nonce' ) } ); } ecsh_renewal_close(); return false; }
document.addEventListener( 'keydown', function( e ) {
	if ( 'Escape' !== e.key || ! document.body.classList.contains( 'ecsh-renewal-open' ) ) { return; }
	var m = document.getElementById( 'ecsh_renewal_modal' );
	if ( m && m.classList.contains( 'is-gate' ) ) { return; } /* pick an option instead */
	ecsh_renewal_close();
} );
document.addEventListener( 'DOMContentLoaded', function() {
	var m = document.getElementById( 'ecsh_renewal_modal' );
	if ( m && '1' === m.getAttribute( 'data-gate' ) ) { setTimeout( ecsh_renewal_open, 400 ); }
} );
</script>
<?php endif; ?>
<?php /* 6.0.0: video lightbox ( admin-frame-v2.js ). A centered 16:9 player sized to the window, with the video title, a YouTube link and a
   close button. The player keeps its id and the container its class, so wp_easycart_admin_open_video_help() callers still work. */ ?>
<div class="ec_admin_help_video_container ecsh-video" role="dialog" aria-modal="true" aria-labelledby="ecsh_video_title" aria-hidden="true">
	<div class="ecsh-video-dialog">
		<div class="ecsh-video-bar">
			<span class="dashicons dashicons-format-video" aria-hidden="true"></span>
			<span class="ecsh-video-title" id="ecsh_video_title"><?php esc_html_e( 'Video tutorial', 'wp-easycart' ); ?></span>
			<a class="ecsh-video-yt" href="https://www.youtube.com/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Watch on YouTube', 'wp-easycart' ); ?> &#8599;</a>
			<button type="button" class="ecsh-video-close" onclick="wp_easycart_admin_close_video_help(); return false;" aria-label="<?php esc_attr_e( 'Close video', 'wp-easycart' ); ?>"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
		</div>
		<div class="ecsh-video-frame"><div id="wp_easycart_admin_help_video_player"></div></div>
	</div>
</div>
<script>jQuery( '.ec_admin_help_video_container' ).prependTo( document.body );</script>

<div class="ecsh-scrim"></div>

<div class="ecsh-app<?php echo $ecsh_fold ? ' ecsh-sb-foldable' . ( ! empty( $ecsh_fold['folded'] ) ? ' ecsh-sb-mini' : '' ) : ''; ?>">
	<script>( function( a ) { if ( a && window.matchMedia && window.matchMedia( '(min-width: 783px) and (max-width: 1100px)' ).matches ) { a.className += ' ecsh-sb-narrow ecsh-sb-mini'; } } )( document.currentScript && document.currentScript.parentNode );</script>

	<!-- ================= SIDEBAR (also the mobile drawer) ================= -->
	<aside class="ecsh-sidebar">
		<a class="ecsh-brand" href="http://www.wpeasycart.com" target="_blank" rel="noopener" aria-label="<?php esc_attr_e( 'WP EasyCart website ( opens in a new tab )', 'wp-easycart' ); ?>">
			<div class="ecsh-brand-logo"></div>
		</a>

		<a class="ecsh-sb-store" href="<?php $storepageid = get_option( 'ec_option_storepage' ); echo esc_attr( get_permalink( $storepageid ) ); ?>" target="_blank">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6"/><path d="M10 14 21 3"/></svg>
			<?php esc_attr_e( 'View my store', 'wp-easycart' ); ?>
		</a>

		<?php if ( get_option( 'ec_option_admin_display_sales_goal' ) == 1 ) { ?>
		<div class="ecsh-sb-sales">
			<div class="ecsh-sb-sales-row">
				<div>
					<div class="ecsh-sb-sales-label"><?php
					/* translators: %s: this month's short name, e.g. Oct. */
					echo esc_html( sprintf( __( '%s sales', 'wp-easycart' ), wp_date( 'M' ) ) ); /* 6.0.5: the month in the site's language */
					?></div>
					<div class="ecsh-sb-sales-amt"><?php echo esc_attr( $GLOBALS['currency']->get_currency_display( $this->month_sales_total ) ); ?></div>
				</div>
				<div class="ecsh-sb-sales-delta<?php if ( $this->month_percentage_change >= 0 ) { echo ' ecsh-up'; } ?>"><?php
					echo ( $this->month_percentage_change >= 0 ) ? '&#9650; ' : '&#9660; ';
					if ( $this->month_percentage_change > 0 ) { echo '+'; }
					echo esc_html( number_format_i18n( (float) $this->month_percentage_change, 1 ) ); /* 6.0.5: the site's decimal mark */
				?>%</div>
			</div>
			<div class="ecsh-sb-goal-track">
				<div class="ecsh-sb-goal-fill" style="width:<?php echo esc_attr( min( 100, (float) number_format( (float) $this->month_percentage_goal, 0, '', '' ) ) ); ?>%;"></div>
			</div>
			<div class="ecsh-sb-goal-caption">
				<span><?php echo esc_attr( number_format( (float) $this->month_percentage_goal, 0, '', '' ) ); ?>% <?php esc_attr_e( 'of goal', 'wp-easycart' ); ?></span>
			</div>
		</div>
		<?php } ?>

		<nav class="ecsh-sb-nav">
			<?php do_action( 'wp_easycart_admin_left_navigation' ); ?>
		</nav>

		<div class="ecsh-sb-foot">
			<?php
			/* 6.0.5: every screen prints the button. From 783 to 1100px wide the menu is the icon rail on every screen, and the
			   button opens the full menu over the page ( admin-frame-v2.js ); wider, only a screen that asks to fold shows it. */
			$ecsh_folded = $ecsh_fold && ! empty( $ecsh_fold['folded'] );
			?>
			<button type="button" class="ecsh-sb-fold<?php echo $ecsh_fold ? '' : ' ecsh-sb-fold-narrow'; ?>" aria-pressed="<?php echo $ecsh_folded ? 'true' : 'false'; ?>" data-fold-action="<?php echo $ecsh_fold ? esc_attr( $ecsh_fold['action'] ) : ''; ?>" data-fold-nonce="<?php echo $ecsh_fold ? esc_attr( $ecsh_fold['nonce'] ) : ''; ?>" data-label-fold="<?php esc_attr_e( 'Fold the menu to icons', 'wp-easycart' ); ?>" data-label-open="<?php esc_attr_e( 'Show the full menu', 'wp-easycart' ); ?>" title="<?php echo $ecsh_folded ? esc_attr__( 'Show the full menu', 'wp-easycart' ) : esc_attr__( 'Fold the menu to icons', 'wp-easycart' ); ?>">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m11 17-5-5 5-5"/><path d="m18 17-5-5 5-5"/></svg>
				<span class="ecsh-sb-fold-text"><?php echo $ecsh_folded ? esc_html__( 'Show the full menu', 'wp-easycart' ) : esc_html__( 'Fold the menu to icons', 'wp-easycart' ); ?></span>
			</button>
			<?php if ( $ecsh_status['has_license'] && $ecsh_status['is_trial'] ) { ?>
			<div class="ecsh-sb-trial">
				<strong><?php esc_attr_e( 'Pro trial', 'wp-easycart' ); ?> &middot; <?php echo esc_attr( $ecsh_status['days_left'] ); ?> <?php esc_attr_e( 'days left', 'wp-easycart' ); ?></strong>
				<a href="admin.php?page=wp-easycart-license-status"><?php esc_attr_e( 'Upgrade now', 'wp-easycart' ); ?></a>
			</div>
			<?php } else if ( isset( $ecsh_renewal ) && $ecsh_renewal && 'ok' !== $ecsh_renewal['tone'] ) { ?>
			<button type="button" class="ecsh-sb-renew is-<?php echo esc_attr( $ecsh_renewal['tone'] ); ?>" onclick="ecsh_renewal_open(); return false;">
				<span class="ecsh-sb-renew-bar" aria-hidden="true"><span style="width:<?php echo esc_attr( max( 3, min( 100, round( $ecsh_renewal['days'] / 30 * 100 ) ) ) ); ?>%"></span></span>
				<strong><?php echo esc_html( $ecsh_renewal['edition'] ); ?> &middot; <?php
				if ( 'lapsed' === $ecsh_renewal['tone'] ) { esc_html_e( 'lapsed', 'wp-easycart' ); }
				else { echo esc_html( sprintf( _n( '%d day left', '%d days left', $ecsh_renewal['days'], 'wp-easycart' ), $ecsh_renewal['days'] ) ); }
				?></strong>
				<span><?php echo 'lapsed' === $ecsh_renewal['tone'] ? ( ! empty( $ecsh_renewal['premium'] ) ? esc_html__( 'Premium features are locked and extensions run without updates — renew to reopen', 'wp-easycart' ) : esc_html__( 'Pro features are locked — renew to reopen', 'wp-easycart' ) ) : esc_html__( 'Renew before fees and locks return', 'wp-easycart' ); ?></span>
			</button>
			<?php } ?>
			<a class="ecsh-sb-powered" href="http://www.wpeasycart.com" target="_blank" rel="noopener"><?php esc_attr_e( 'Powered by WP EasyCart', 'wp-easycart' ); ?></a>
		</div>
	</aside>

	<!-- ================= MAIN ================= -->
	<div class="ecsh-main">
		<header class="ecsh-topbar">
			<button class="ecsh-tb-hamburger" type="button" aria-label="<?php esc_attr_e( 'Open menu', 'wp-easycart' ); ?>">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
			</button>
			<span class="ecsh-tb-logo" aria-hidden="true"></span>

			<div class="ecsh-tb-crumb">
				<span class="ecsh-trail">EasyCart</span>
				<?php
				$ecsh_crumb = wp_easycart_shell_breadcrumb();
				foreach ( $ecsh_crumb['trail'] as $ecsh_crumb_item ) { ?>
				<span class="ecsh-sep ecsh-trail">/</span>
				<a class="ecsh-trail ecsh-crumb-link" href="<?php echo esc_attr( $ecsh_crumb_item['url'] ); ?>"><?php echo esc_html( $ecsh_crumb_item['label'] ); ?></a>
				<?php }
				if ( '' !== $ecsh_crumb['here'] ) { ?>
				<span class="ecsh-sep ecsh-trail">/</span>
				<span class="ecsh-here"><?php echo esc_html( $ecsh_crumb['here'] ); ?></span>
				<?php } ?>
			</div>

			<form class="ecsh-tb-search" method="GET" action="https://docs.wpeasycart.com/" target="_blank" id="wpeasycart_search_form">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
				<input type="text" name="s" placeholder="<?php esc_attr_e( 'Search orders, products, settings…', 'wp-easycart' ); ?>" autocomplete="off" aria-label="<?php esc_attr_e( 'Search everything', 'wp-easycart' ); ?>" aria-haspopup="dialog" aria-keyshortcuts="Control+K Meta+K /" />
				<kbd class="ecsh-tb-search-kbd" data-wpec-search-kbd>⌘K</kbd>
			</form>
			<button class="ecsh-tb-search-btn" type="button" aria-label="<?php esc_attr_e( 'Search everything', 'wp-easycart' ); ?>" aria-haspopup="dialog">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
			</button>

			<?php $ecsh_renewal = class_exists( 'wp_easycart_admin_upsell' ) ? wp_easycart_admin_upsell::renewal() : null; ?>
			<?php if ( $ecsh_renewal && 'ok' !== $ecsh_renewal['tone'] ) : ?>
			<button class="ecsh-tb-license is-<?php echo esc_attr( $ecsh_renewal['tone'] ); ?>" type="button" onclick="ecsh_renewal_open(); return false;" aria-haspopup="dialog" aria-controls="ecsh_renewal_modal" title="<?php esc_attr_e( 'License renewal', 'wp-easycart' ); ?>">
				<span class="dashicons <?php echo 'lapsed' === $ecsh_renewal['tone'] ? 'dashicons-lock' : 'dashicons-clock'; ?>"></span>
				<span class="ecsh-tb-license-text"><?php
				if ( 'lapsed' === $ecsh_renewal['tone'] ) { esc_html_e( 'License lapsed', 'wp-easycart' ); }
				else { echo esc_html( sprintf( _n( 'License ends tomorrow', 'License ends in %d days', $ecsh_renewal['days'], 'wp-easycart' ), $ecsh_renewal['days'] ) ); }
				?></span>
				<span class="ecsh-tb-license-cta"><?php esc_html_e( 'Renew', 'wp-easycart' ); ?></span>
			</button>
			<?php endif; ?>

			<?php /* 6.0.5 ( admin review M11 ): the notices waiting below the one shown ( admin-frame-v2.js counts them ). */ ?>
			<button class="ecsh-tb-btn ecsh-tb-notices" type="button" id="ecsh_notices_btn" aria-expanded="false" aria-controls="ecsh_notices_more" hidden>
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
				<span class="ecsh-tb-notices-label"><?php esc_html_e( 'Notices', 'wp-easycart' ); ?></span>
				<span class="ecsh-tb-notices-n">0</span>
			</button>

			<div class="ecsh-tb-menu">
				<button class="ecsh-tb-btn" type="button" aria-haspopup="true" aria-label="<?php esc_attr_e( 'Help', 'wp-easycart' ); ?>">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg>
					<span class="ecsh-tb-btn-label"><?php esc_html_e( 'Help', 'wp-easycart' ); ?></span>
				</button>
				<div class="ecsh-tb-dropdown">
					<?php do_action( 'wp_easycart_admin_head_navigation' ); ?>
				</div>
			</div>

			<div class="ecsh-tb-color" title="<?php esc_attr_e( 'Select admin color', 'wp-easycart' ); ?>">
				<span class="ecsh-tb-color-swatch">
					<input type="color" value="<?php echo esc_attr( wp_easycart_shell_normalize_hex( get_option( 'ec_option_admin_color' ) ) ); ?>" id="ec_admin_shell_color" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp-easycart-admin-color' ) ); ?>" aria-label="<?php esc_attr_e( 'Select admin color', 'wp-easycart' ); ?>" />
				</span>
				<span class="ecsh-tb-color-label"><?php esc_attr_e( 'Color', 'wp-easycart' ); ?></span>
			</div>
		</header>

		<main class="ecsh-content ec_admin_content_area">
			<?php
			/* 6.0.5 ( admin review M11 ): one banner a screen. admin-frame-v2.js keeps the most urgent notice here ( none on an edit
			   screen ) and moves the rest into #ecsh_notices_more, opened from the header's Notices button. */
			$ecsh_edit_screen = isset( $_GET['ec_admin_form_action'] ) && 'edit' === sanitize_key( wp_unslash( $_GET['ec_admin_form_action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which screen this is, nothing is changed.
			?>
			<div class="ecsh-notices" id="ecsh_notices" data-edit="<?php echo $ecsh_edit_screen ? '1' : '0'; ?>"><?php do_action( 'wp_easycart_admin_messages' ); ?></div>
			<div class="ecsh-notices-more" id="ecsh_notices_more" role="region" aria-label="<?php esc_attr_e( 'Notices', 'wp-easycart' ); ?>" hidden></div>
			<?php do_action( 'wp_easycart_admin_shell_content' ); ?>
		</main>
	</div>
</div>

<?php do_action( 'wp_easycart_admin_mobile_navigation' ); ?>

<?php /* 6.0.5: the search window is drawn by admin/js/admin-search-v2.js ( wp_easycart_admin_search ). */ ?>