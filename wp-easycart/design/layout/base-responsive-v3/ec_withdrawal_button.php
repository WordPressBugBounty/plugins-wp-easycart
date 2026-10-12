<?php
/**
 * The withdrawal button ( "Withdraw from contract here", 6.0.4 ).
 *
 * Drawn by wp_easycart_withdrawals::button_html() on the order confirmation page, My Account order details and the guest order
 * view, the withdrawal page's order list, the foot of every page and the [ec_withdrawal_button] shortcode, with $button: url,
 * label ( the words the law sets, language section withdrawal / withdraw_button ), note ( a line under it, or '' ), context
 * ( success | order | list | footer | shortcode ), styles ( the first button on the page prints the styles ). It must stay easy
 * to see and easy to read. Copy this file to your wp-easycart-data layout folder to customise it; the file name must stay the same.
 *
 * @since 6.0.4
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- a template: it is included inside a method, so its variables are that method's locals.

$ec_wdb_accent = sanitize_hex_color( (string) get_option( 'ec_option_details_main_color' ) );
$ec_wdb_accent = $ec_wdb_accent ? $ec_wdb_accent : '#222222';
?>
<?php if ( ! empty( $button['styles'] ) ) : ?>
<style>
	.ec_withdrawal_button_wrap { margin:16px 0; }
	.ec_withdrawal_button_wrap.is-order { display:inline-block; margin:0 0 0 8px; vertical-align:middle; }
	.ec_withdrawal_button_wrap.is-footer { margin:0; padding:20px 16px; text-align:center; clear:both; }
	.ec_withdrawal_button_wrap.is-success { text-align:center; }
	a.ec_withdrawal_button, a.ec_withdrawal_button:visited { display:inline-block; padding:10px 18px; border:2px solid var(--ec-wdb-accent, #222); border-radius:6px; background:#fff; color:var(--ec-wdb-accent, #222); font-size:15px; font-weight:700; line-height:1.3; text-decoration:none; }
	a.ec_withdrawal_button:hover, a.ec_withdrawal_button:focus { background:var(--ec-wdb-accent, #222); color:#fff; text-decoration:none; }
	a.ec_withdrawal_button:focus-visible { outline:2px solid var(--ec-wdb-accent, #222); outline-offset:2px; }
	.ec_withdrawal_button_wrap.is-order a.ec_withdrawal_button { padding:7px 14px; font-size:14px; }
	.ec_withdrawal_note { margin:6px 0 0 0; font-size:13px; opacity:.85; }
</style>
<?php endif; ?>
<div class="ec_withdrawal_button_wrap is-<?php echo esc_attr( $button['context'] ); ?>" style="--ec-wdb-accent:<?php echo esc_attr( $ec_wdb_accent ); ?>;">
	<a class="ec_withdrawal_button" href="<?php echo esc_url( $button['url'] ); ?>"><?php echo esc_html( $button['label'] ); ?></a>
	<?php if ( '' !== (string) $button['note'] ) : ?>
		<p class="ec_withdrawal_note"><?php echo esc_html( $button['note'] ); ?></p>
	<?php endif; ?>
</div>
