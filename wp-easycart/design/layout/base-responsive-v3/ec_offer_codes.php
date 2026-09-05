<?php
/**
 * Offers v2 — coupon code input, applied code chips, and code notices.
 * Include where the legacy coupon block renders (replaces it when v2 active).
 * Expects: $cartpage (ec_cartpage), $offer_result (ec_offer_result).
 */
if ( ! isset( $offer_result ) ) {
	$offer_result = ec_offer_integration::evaluate_cart( $cartpage->cart );
}
$offer_code_nonce = wp_create_nonce( 'wp-easycart-offer-code-' . $GLOBALS['ec_cart_data']->ec_cart_id );
$applied_chips = ec_offer_display::get_applied_code_chips( $offer_result );
$code_notices = ec_offer_display::get_code_notices( $offer_result );
?>
<?php if ( get_option( 'ec_option_show_coupons' ) ) { ?>
<div class="ec_offer_codes_container">
	<div class="ec_cart_header"><?php echo wp_easycart_offers_text( 'cart_coupons', 'cart_coupon_title' ); ?></div>

	<?php if ( count( $applied_chips ) > 0 ) { ?>
	<div class="ec_offer_applied_codes">
		<?php foreach ( $applied_chips as $chip ) { ?>
		<div class="ec_offer_code_chip<?php echo ( $chip['legacy'] ) ? ' ec_offer_code_chip_legacy' : ''; ?>">
			<span class="dashicons dashicons-tag"></span>
			<span class="ec_offer_code_chip_code"><?php echo esc_attr( $chip['code'] ); ?></span>
			<?php if ( $chip['amount'] > 0 ) { ?>
			<span class="ec_offer_code_chip_amount">-<?php echo esc_attr( $GLOBALS['currency']->get_currency_display( $chip['amount'] ) ); ?></span>
			<?php } ?>
			<a href="#" class="ec_offer_code_chip_remove" onclick="wpeasycart_offer_remove_code( '<?php echo esc_attr( $chip['code'] ); ?>', '<?php echo esc_attr( $offer_code_nonce ); ?>' ); return false;" aria-label="<?php echo wp_easycart_offers_text( 'cart_coupons', 'coupon_remove_label' ); ?>">&times;</a>
		</div>
		<?php } ?>
	</div>
	<?php } ?>

	<div class="ec_offer_code_notices" id="ec_offer_code_notices">
		<?php foreach ( $code_notices as $notice ) { ?>
		<div class="ec_cart_error_message ec_offer_code_notice" style="display:block;"><?php if ( '' != $notice['code'] ) { echo esc_attr( $notice['code'] ) . ': '; } echo esc_attr( $notice['message'] ); ?></div>
		<?php } ?>
	</div>
	<div class="ec_cart_success_message" id="ec_offer_code_success"></div>
	<div class="ec_cart_error_message" id="ec_offer_code_error"></div>

	<div class="ec_cart_input_row">
		<input type="text" name="ec_offer_code_input" id="ec_offer_code_input" value="" placeholder="<?php echo wp_easycart_offers_text( 'cart_coupons', 'cart_enter_coupon' ); ?>" />
	</div>
	<div class="ec_cart_button_row">
		<div class="ec_cart_button" id="ec_offer_apply_code" onclick="wpeasycart_offer_apply_code( '<?php echo esc_attr( $offer_code_nonce ); ?>' );"><?php echo wp_easycart_offers_text( 'cart_coupons', 'cart_apply_coupon' ); ?></div>
		<div class="ec_cart_button_working" id="ec_offer_applying_code"><?php echo wp_easycart_language()->get_text( 'cart', 'cart_please_wait' ); ?></div>
	</div>
</div>
<?php } ?>