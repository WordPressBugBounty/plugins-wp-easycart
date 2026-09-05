<?php
/**
 * Offers v2 — itemized named discount rows for the cart totals block.
 * Include directly ABOVE the existing discount total row in ec_cart_totals.php.
 * Expects: $offer_result.
 */
if ( ! isset( $offer_result ) || 0 == count( $offer_result->applied_offers ) ) {
	return;
}
?>
<div class="ec_offer_totals_rows">
	<?php foreach ( $offer_result->applied_offers as $applied ) { ?>
	<?php if ( $applied['amount'] <= 0 && 'shipping' != $applied['stacking_class'] ) { continue; } ?>
	<div class="ec_cart_price_row ec_offer_totals_row">
		<div class="ec_cart_price_row_label ec_offer_totals_row_label">
			<span class="dashicons dashicons-tag"></span>
			<?php echo esc_attr( wp_easycart_language()->convert_text( $applied['label'] ) ); ?>
			<?php if ( '' != $applied['code'] ) { ?><span class="ec_offer_totals_row_code">(<?php echo esc_attr( $applied['code'] ); ?>)</span><?php } ?>
		</div>
		<div class="ec_cart_price_row_total ec_offer_totals_row_amount">
			<?php if ( $applied['amount'] > 0 ) { ?>-<?php echo esc_attr( $GLOBALS['currency']->get_currency_display( $applied['amount'] ) ); ?><?php } else { ?><?php echo wp_easycart_offers_text( 'cart_offers', 'offer_applied_label' ); ?><?php } ?>
		</div>
	</div>
	<?php } ?>
</div>