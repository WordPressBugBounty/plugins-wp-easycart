<?php
/**
 * Offers v2 — product page callouts + optional flash-sale countdown.
 * Include below the price block on the details page.
 * Expects: $callout_product_id, $callout_manufacturer_id, $callout_price; optional $callout_list_price ( 6.0.2: the list
 * price, so an offer that leaves sale items alone does not call out on a product on sale, as the cart gives nothing ).
 * 6.0.2: filter wp_easycart_offer_callout_icon changes the icon ( markup; '' for none ).
 */
$offer_callouts = ec_offer_display::get_product_callouts( $callout_product_id, $callout_manufacturer_id, $callout_price, ( isset( $callout_list_price ) ? (float) $callout_list_price : 0 ) );
if ( 0 == count( $offer_callouts ) ) {
	return;
}
?>
<div class="ec_offer_product_callouts">
	<?php foreach ( $offer_callouts as $callout ) { ?>
	<div class="ec_offer_product_callout">
		<?php echo apply_filters( 'wp_easycart_offer_callout_icon', '<span class="dashicons dashicons-megaphone ec_offer_product_callout_icon"></span>', $callout, $callout_product_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon markup from code ( the filter ), the dashicon by default. ?>
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