<?php
/**
 * Offers v2 — product badge for listing widgets and the details page.
 * Include inside the product image wrapper.
 * Expects: $badge_product_id, $badge_manufacturer_id, $badge_price.
 */
$offer_badge = ec_offer_display::get_product_badge( $badge_product_id, $badge_manufacturer_id, $badge_price );
if ( false === $offer_badge ) {
	return;
}
$offer_badge_shape = ( isset( $offer_badge['shape'] ) && '' != $offer_badge['shape'] ) ? $offer_badge['shape'] : 'rounded';
$offer_badge_style = '';
if ( isset( $offer_badge['color'] ) && '' != $offer_badge['color'] ) {
	$offer_badge_style = 'background:' . esc_attr( $offer_badge['color'] ) . ';';
}
?>
<div class="ec_offer_product_badge ec_offer_badge_shape_<?php echo esc_attr( $offer_badge_shape ); ?>" data-offer-id="<?php echo esc_attr( $offer_badge['offer_id'] ); ?>"<?php if ( '' != $offer_badge_style ) { ?> style="<?php echo $offer_badge_style; ?>"<?php } ?>><?php echo esc_attr( $offer_badge['text'] ); ?></div>