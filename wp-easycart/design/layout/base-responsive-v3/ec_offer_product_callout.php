<?php
/**
 * Offers v2 — product page callouts + optional flash-sale countdown.
 * Include below the price block on the details page.
 * Expects: $callout_product_id, $callout_manufacturer_id, $callout_price.
 */
$offer_callouts = ec_offer_display::get_product_callouts( $callout_product_id, $callout_manufacturer_id, $callout_price );
if ( 0 == count( $offer_callouts ) ) {
	return;
}
?>
<div class="ec_offer_product_callouts">
	<?php foreach ( $offer_callouts as $callout ) { ?>
	<div class="ec_offer_product_callout">
		<span class="dashicons dashicons-megaphone"></span>
		<span class="ec_offer_product_callout_text"><?php echo esc_attr( wp_easycart_language()->convert_text( $callout['text'] ) ); ?></span>
		<?php if ( $callout['countdown_ts'] > 0 ) { ?>
		<span class="ec_offer_countdown" data-end-ts="<?php echo esc_attr( $callout['countdown_ts'] ); ?>">
			<span class="ec_offer_countdown_label"><?php echo wp_easycart_offers_text( 'cart_offers', 'countdown_ends_in' ); ?></span>
			<span class="ec_offer_countdown_clock"></span>
		</span>
		<?php } ?>
	</div>
	<?php } ?>
</div>