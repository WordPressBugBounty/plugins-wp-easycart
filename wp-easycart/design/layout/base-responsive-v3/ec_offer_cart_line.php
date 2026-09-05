<?php
/**
 * Offers v2 — per-line additions inside the cart row loop.
 * Include inside the details column of each cart line, after options render.
 * Expects: $cartitem (the ec_cartitem), $cartitem_index, $offer_result.
 *
 * Renders: FREE GIFT tag, bundle membership tag, applied offer labels with
 * line amounts, and the strikethrough original when line discounts apply.
 */
if ( ! isset( $offer_result ) ) {
	return;
}
$line_is_gift = ( isset( $cartitem->free_gift_offer_id ) && $cartitem->free_gift_offer_id > 0 );
$line_bundle_key = ( isset( $cartitem->bundle_group_key ) ) ? $cartitem->bundle_group_key : '';
$line_offer_rows = ( isset( $offer_result->lines[ $cartitem_index ] ) ) ? $offer_result->lines[ $cartitem_index ]->offer_discounts : array();
?>
<?php if ( $line_is_gift ) { ?>
<div class="ec_offer_line_tag ec_offer_line_tag_gift"><span class="dashicons dashicons-heart"></span> <?php echo wp_easycart_offers_text( 'cart_offers', 'gift_line_label' ); ?></div>
<?php } ?>
<?php if ( '' != $line_bundle_key && isset( $cartitem->bundle_product_id ) && $cartitem->bundle_product_id != $cartitem->product_id ) { ?>
<div class="ec_offer_line_tag ec_offer_line_tag_bundle"><span class="dashicons dashicons-archive"></span> <?php echo wp_easycart_offers_text( 'cart_offers', 'bundle_line_label' ); ?></div>
<?php } ?>
<?php foreach ( $line_offer_rows as $line_offer ) { ?>
<div class="ec_offer_line_discount">
	<span class="dashicons dashicons-tag"></span>
	<span class="ec_offer_line_discount_label"><?php echo esc_attr( wp_easycart_language()->convert_text( $line_offer['label'] ) ); ?></span>
	<span class="ec_offer_line_discount_amount">-<?php echo esc_attr( $GLOBALS['currency']->get_currency_display( $line_offer['amount'] ) ); ?></span>
</div>
<?php } ?>