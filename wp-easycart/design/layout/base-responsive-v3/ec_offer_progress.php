<?php
/**
 * Offers v2 — near-miss progress messages + progress bar.
 * Include near the top of the cart (below headers works well).
 * Expects: $cartpage, $offer_result.
 */
if ( ! get_option( 'ec_option_offer_show_progress_messages' ) || ! isset( $offer_result ) ) {
	return;
}
$progress_rows = ec_offer_display::get_progress_messages( $offer_result );
if ( 0 == count( $progress_rows ) ) {
	return;
}
$cart_subtotal_for_progress = ( isset( $cartpage->order_totals->sub_total ) ) ? (float) $cartpage->order_totals->sub_total : 0;
?>
<div class="ec_offer_progress_container">
	<?php foreach ( $progress_rows as $progress ) { ?>
	<div class="ec_offer_progress_row">
		<div class="ec_offer_progress_message"><span class="dashicons dashicons-arrow-up-alt"></span> <?php echo esc_attr( $progress['message'] ); ?></div>
		<?php if ( 'spend' == $progress['type'] && $cart_subtotal_for_progress > 0 ) { ?>
		<?php $progress_target = $cart_subtotal_for_progress + $progress['remaining']; ?>
		<?php $progress_percent = min( 100, round( $cart_subtotal_for_progress / $progress_target * 100 ) ); ?>
		<div class="ec_offer_progress_bar_track"><div class="ec_offer_progress_bar_fill" style="width:<?php echo esc_attr( $progress_percent ); ?>%;"></div></div>
		<?php } ?>
	</div>
	<?php } ?>
</div>