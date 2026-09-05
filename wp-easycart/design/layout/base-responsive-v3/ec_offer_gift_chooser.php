<?php
/**
 * Offers v2 — "claim your free gift" chooser for customer-choice gifts.
 * Include above the cart table.
 * Expects: $offer_result.
 */
if ( ! isset( $offer_result ) ) {
	return;
}
$pending_gifts = ec_offer_display::get_pending_gift_choices( $offer_result );
if ( 0 == count( $pending_gifts ) ) {
	return;
}
$offer_gift_nonce = wp_create_nonce( 'wp-easycart-offer-gift-' . $GLOBALS['ec_cart_data']->ec_cart_id );
?>
<div class="ec_offer_gift_chooser_container">
	<?php foreach ( $pending_gifts as $pending ) { ?>
	<?php
	$pending_claimed_id = ( isset( $pending['claimed_product_id'] ) ) ? (int) $pending['claimed_product_id'] : 0;
	$pending_claimed_title = '';
	if ( $pending_claimed_id > 0 ) {
		foreach ( $pending['products'] as $gift_product ) {
			if ( (int) $gift_product->product_id == $pending_claimed_id ) {
				$pending_claimed_title = $gift_product->title;
				break;
			}
		}
	}
	?>
	<div class="ec_offer_gift_chooser<?php if ( $pending_claimed_id > 0 ) { ?> ec_offer_gift_chooser_claimed<?php } ?>" id="ec_offer_gift_chooser_<?php echo esc_attr( $pending['offer_id'] ); ?>">
		<?php if ( $pending_claimed_id > 0 ) { ?>
		<div class="ec_offer_gift_chooser_title ec_offer_gift_claimed_bar">
			<span class="dashicons dashicons-heart"></span>
			<?php echo wp_easycart_offers_text( 'cart_offers', 'gift_claimed_label' ); ?>
			<strong><?php echo esc_attr( $pending_claimed_title ); ?></strong>
			<span class="ec_offer_gift_change_link" onclick="wpeasycart_offer_gift_toggle( <?php echo esc_attr( $pending['offer_id'] ); ?> ); return false;" role="button"><?php echo wp_easycart_offers_text( 'cart_offers', 'gift_change_link' ); ?></span>
		</div>
		<?php } else { ?>
		<div class="ec_offer_gift_chooser_title"><span class="dashicons dashicons-heart"></span> <?php echo esc_attr( wp_easycart_language()->convert_text( $pending['label'] ) ); ?> &mdash; <?php echo wp_easycart_offers_text( 'cart_offers', 'gift_chooser_title' ); ?></div>
		<?php } ?>
		<div class="ec_offer_gift_chooser_viewport">
			<div class="ec_offer_gift_chooser_nav ec_offer_gift_nav_prev ec_offer_gift_nav_hidden" onclick="wpeasycart_offer_gift_scroll( <?php echo esc_attr( $pending['offer_id'] ); ?>, -1 );" role="button" aria-label="<?php echo esc_attr( wp_easycart_offers_text( 'cart_offers', 'gift_nav_previous' ) ); ?>">&lsaquo;</div>
			<div class="ec_offer_gift_chooser_options" id="ec_offer_gift_options_<?php echo esc_attr( $pending['offer_id'] ); ?>">
				<?php foreach ( $pending['products'] as $gift_product ) { ?>
				<div class="ec_offer_gift_option">
					<?php if ( '' != $gift_product->image1 ) { ?>
					<img src="<?php echo esc_attr( $gift_product->image1 ); ?>" alt="<?php echo esc_attr( $gift_product->title ); ?>" class="ec_offer_gift_option_image" />
					<?php } ?>
					<div class="ec_offer_gift_option_title"><?php echo esc_attr( $gift_product->title ); ?></div>
					<div class="ec_offer_gift_option_value"><span class="ec_offer_gift_option_strike"><?php echo esc_attr( $GLOBALS['currency']->get_currency_display( $gift_product->price ) ); ?></span> <span class="ec_offer_gift_option_free"><?php echo wp_easycart_offers_text( 'cart_offers', 'gift_free_label' ); ?></span></div>
					<?php if ( $pending_claimed_id > 0 && (int) $gift_product->product_id == $pending_claimed_id ) { ?>
					<div class="ec_cart_button ec_offer_gift_option_button ec_offer_gift_option_selected"><?php echo wp_easycart_offers_text( 'cart_offers', 'gift_selected_button' ); ?></div>
					<?php } else { ?>
					<div class="ec_cart_button ec_offer_gift_option_button" onclick="wpeasycart_offer_choose_gift( <?php echo esc_attr( $pending['offer_id'] ); ?>, <?php echo esc_attr( $gift_product->product_id ); ?>, '<?php echo esc_attr( $offer_gift_nonce ); ?>' );"><?php echo wp_easycart_offers_text( 'cart_offers', 'gift_claim_button' ); ?></div>
					<?php } ?>
				</div>
				<?php } ?>
			</div>
			<div class="ec_offer_gift_chooser_nav ec_offer_gift_nav_next ec_offer_gift_nav_hidden" onclick="wpeasycart_offer_gift_scroll( <?php echo esc_attr( $pending['offer_id'] ); ?>, 1 );" role="button" aria-label="<?php echo esc_attr( wp_easycart_offers_text( 'cart_offers', 'gift_nav_next' ) ); ?>">&rsaquo;</div>
		</div>
		<div class="ec_offer_gift_chooser_error" id="ec_offer_gift_error_<?php echo esc_attr( $pending['offer_id'] ); ?>" style="display:none;"></div>
	</div>
	<?php } ?>
</div>