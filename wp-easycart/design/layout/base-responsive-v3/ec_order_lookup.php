<?php
/**
 * Guest order lookup ( 6.0.4 ): the order number and the email used for the order; when they match, the order's link is
 * emailed to the address on the order.
 *
 * Drawn by wp_easycart_order_lookup::render() ( shortcode [ec_order_lookup], the account page with ec_page=order_lookup, the
 * withdrawal page ) with $lookup: heading, embedded ( inside the withdrawal page, which brings its own styles ), values
 * ( order_ref, email ), errors ( plain text ), sent ( the answer, the same whether or not an order matched ), check ( the robot
 * check: provider, site_key ), action ( where the form posts ). The form carries no page nonce, so it works when the page is
 * cached. Copy this file to your wp-easycart-data layout folder to customise it; the file name and the field names must stay the same.
 *
 * @since 6.0.4
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- a template: it is included inside a method, so its variables are that method's locals.

$ec_lk_t      = function ( $key, $vars = array() ) {
	return wp_easycart_withdrawals::text( $key, $vars );
};
$ec_lk_accent = sanitize_hex_color( (string) get_option( 'ec_option_details_main_color' ) );
$ec_lk_accent = $ec_lk_accent ? $ec_lk_accent : '#222222';
?>
<div class="ec-lk" id="ec-lk" style="--ec-lk-accent:<?php echo esc_attr( $ec_lk_accent ); ?>;">
<?php if ( empty( $lookup['embedded'] ) ) : ?>
<style>
	.ec-lk { max-width:560px; margin:0 auto; font-size:15px; line-height:1.55; }
	.ec-lk * { box-sizing:border-box; }
	.ec-lk h2 { color:inherit; font-family:inherit; font-size:22px; margin:0 0 8px 0; line-height:1.25; }
	.ec-lk p { margin:0 0 12px 0; }
	.ec-lk-field { margin:0 0 14px 0; }
	.ec-lk-field label { display:block; font-size:14px; font-weight:600; margin:0 0 4px 0; color:inherit; }
	.ec-lk-field input { width:100%; padding:9px 11px; border:1px solid #d1d5db; border-radius:6px; font-family:inherit; font-size:15px; background:#fff; color:#1f2937; }
	.ec-lk-button { display:inline-block; padding:12px 20px; border:2px solid var(--ec-lk-accent); border-radius:8px; background:var(--ec-lk-accent); color:#fff; font-family:inherit; font-size:16px; font-weight:700; line-height:1.2; cursor:pointer; text-transform:none; }
	.ec-lk-button:hover, .ec-lk-button:focus { filter:brightness(1.08); color:#fff; background:var(--ec-lk-accent); }
	.ec-lk-button:focus-visible, .ec-lk input:focus-visible { outline:2px solid var(--ec-lk-accent); outline-offset:2px; }
	.ec-lk-notice { padding:12px 16px; border-radius:8px; margin:0 0 16px 0; }
	.ec-lk-notice ul { margin:0; padding:0 0 0 18px; }
	.ec-lk-notice.is-error { background:#fef2f2; color:#7f1d1d; }
	.ec-lk-notice.is-success { background:#ecfdf5; color:#14532d; }
	.ec-lk-hp { position:absolute !important; left:-9999px !important; width:1px; height:1px; overflow:hidden; }
	.ec-lk-check { margin:12px 0; }
</style>
<?php endif; ?>
<div class="<?php echo empty( $lookup['embedded'] ) ? 'ec-lk-body' : 'ec-wd-card'; ?>">
	<?php if ( ! empty( $lookup['heading'] ) ) : ?>
		<h2 class="ec-lk-title"><?php echo esc_html( $ec_lk_t( 'lookup_title' ) ); ?></h2>
	<?php endif; ?>
	<?php if ( ! empty( $lookup['sent'] ) ) : ?>
		<div class="ec-lk-notice ec-wd-notice is-success" role="status" tabindex="-1" id="ec-lk-sent"><?php echo esc_html( $ec_lk_t( 'lookup_sent' ) ); ?></div>
	<?php endif; ?>
	<?php if ( ! empty( $lookup['errors'] ) ) : ?>
		<div class="ec-lk-notice ec-wd-notice is-error" role="alert" tabindex="-1" id="ec-lk-errors">
			<ul>
				<?php foreach ( (array) $lookup['errors'] as $ec_lk_error ) : ?>
					<li><?php echo esc_html( $ec_lk_error ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>
	<p class="ec-lk-intro"><?php echo esc_html( $ec_lk_t( 'lookup_intro' ) ); ?></p>
	<form method="post" action="<?php echo esc_url( $lookup['action'] ); ?>" class="ec-lk-form" novalidate>
		<div class="ec-lk-field ec-wd-field">
			<label for="ec-lk-order"><?php echo esc_html( $ec_lk_t( 'order_number_label' ) ); ?> *</label>
			<input type="text" id="ec-lk-order" name="wpec_lookup_order" value="<?php echo esc_attr( (string) $lookup['values']['order_ref'] ); ?>" inputmode="numeric" autocomplete="off" required />
		</div>
		<div class="ec-lk-field ec-wd-field">
			<label for="ec-lk-email"><?php echo esc_html( $ec_lk_t( 'order_email_label' ) ); ?> *</label>
			<input type="email" id="ec-lk-email" name="wpec_lookup_email" value="<?php echo esc_attr( (string) $lookup['values']['email'] ); ?>" autocomplete="email" required />
		</div>
		<div class="ec-lk-hp ec-wd-hp" aria-hidden="true">
			<label for="ec-lk-website">Website</label>
			<input type="text" id="ec-lk-website" name="wpec_lookup_website" value="" tabindex="-1" autocomplete="off" />
		</div>
		<?php if ( ! empty( $lookup['check']['site_key'] ) ) : ?>
			<div class="ec-lk-check <?php echo ( 'recaptcha' === $lookup['check']['provider'] ) ? 'g-recaptcha' : 'cf-turnstile'; ?>" data-sitekey="<?php echo esc_attr( $lookup['check']['site_key'] ); ?>"></div>
		<?php endif; ?>
		<input type="hidden" name="wpec_lookup_step" value="send" />
		<button type="submit" class="ec-lk-button ec-wd-button"><?php echo esc_html( $ec_lk_t( 'lookup_button' ) ); ?></button>
	</form>
</div>
<script>
( function() {
	var note = document.getElementById( 'ec-lk-errors' ) || document.getElementById( 'ec-lk-sent' );
	if ( note && note.focus ) {
		note.focus();
	}
}() );
</script>
</div>
