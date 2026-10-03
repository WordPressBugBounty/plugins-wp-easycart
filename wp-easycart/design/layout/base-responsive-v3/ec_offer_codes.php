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
/* 6.0.2: the one-page checkout shows this box twice ( mobile and desktop summaries ): each copy has its own ids. */
$offer_sfx = ( isset( $id_suffix ) && defined( 'WP_EASYCART_ADMIN_PRO_VERSION' ) && version_compare( WP_EASYCART_ADMIN_PRO_VERSION, '6.0.2', '>=' ) ) ? preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $id_suffix ) : '';
/* 6.0.2: a code that stays on the cart but takes nothing off right now has an inactive chip with the reason and a remove
 * link ( WP EasyCart PRO 6.0.2 ), so that reason is not printed again as an error on every page. */
$inactive_codes = array();
foreach ( $applied_chips as $chip ) {
	if ( ! empty( $chip['inactive'] ) ) {
		$inactive_codes[ strtoupper( (string) $chip['code'] ) ] = true;
	}
}
?>
<?php if ( get_option( 'ec_option_show_coupons' ) ) { ?>
<?php /* 6.0.2: data-offer-suffix names this copy, so WP EasyCart PRO redraws every copy with its own ids after a code is applied or removed. */ ?>
<div class="ec_offer_codes_container" data-offer-suffix="<?php echo esc_attr( $offer_sfx ); ?>">
	<div class="ec_cart_header"><?php echo wp_easycart_offers_text( 'cart_coupons', 'cart_coupon_title' ); ?></div>

	<?php if ( count( $applied_chips ) > 0 ) { ?>
	<div class="ec_offer_applied_codes">
		<?php foreach ( $applied_chips as $chip ) { ?>
		<div class="ec_offer_code_chip<?php echo ( $chip['legacy'] ) ? ' ec_offer_code_chip_legacy' : ''; ?><?php echo ( ! empty( $chip['inactive'] ) ) ? ' ec_offer_code_chip_inactive' : ''; ?>">
			<span class="dashicons dashicons-tag"></span>
			<span class="ec_offer_code_chip_code"><?php echo esc_attr( $chip['code'] ); ?></span>
			<?php if ( $chip['amount'] > 0 ) { ?>
			<span class="ec_offer_code_chip_amount">-<?php echo esc_attr( $GLOBALS['currency']->get_currency_display( $chip['amount'] ) ); ?></span>
			<?php } ?>
			<a href="#" class="ec_offer_code_chip_remove" onclick="wpeasycart_offer_remove_code( '<?php echo esc_attr( $chip['code'] ); ?>', '<?php echo esc_attr( $offer_code_nonce ); ?>', '<?php echo esc_attr( $offer_sfx ); ?>' ); return false;" aria-label="<?php echo wp_easycart_offers_text( 'cart_coupons', 'coupon_remove_label' ); ?>">&times;</a>
			<?php if ( ! empty( $chip['inactive'] ) && ! empty( $chip['message'] ) ) { ?>
			<span class="ec_offer_code_chip_reason"><?php echo esc_html( wp_strip_all_tags( (string) $chip['message'] ) ); ?></span>
			<?php } ?>
		</div>
		<?php } ?>
	</div>
	<?php } ?>

	<div class="ec_offer_code_notices" id="ec_offer_code_notices<?php echo esc_attr( $offer_sfx ); ?>">
		<?php foreach ( $code_notices as $notice ) { ?>
		<?php if ( ! empty( $notice['kept'] ) && isset( $inactive_codes[ strtoupper( (string) $notice['code'] ) ] ) ) { continue; } ?>
		<div class="ec_cart_error_message ec_offer_code_notice" style="display:block;"><?php if ( '' != $notice['code'] ) { echo esc_attr( $notice['code'] ) . ': '; } echo esc_attr( $notice['message'] ); ?></div>
		<?php } ?>
	</div>
	<div class="ec_cart_success_message" id="ec_offer_code_success<?php echo esc_attr( $offer_sfx ); ?>"></div>
	<div class="ec_cart_error_message" id="ec_offer_code_error<?php echo esc_attr( $offer_sfx ); ?>"></div>

	<div class="ec_cart_input_row">
		<input type="text" name="ec_offer_code_input<?php echo esc_attr( $offer_sfx ); ?>" id="ec_offer_code_input<?php echo esc_attr( $offer_sfx ); ?>" class="ec_offer_code_input" data-offer-suffix="<?php echo esc_attr( $offer_sfx ); ?>" value="" placeholder="<?php echo wp_easycart_offers_text( 'cart_coupons', 'cart_enter_coupon' ); ?>" />
	</div>
	<div class="ec_cart_button_row">
		<div class="ec_cart_button" id="ec_offer_apply_code<?php echo esc_attr( $offer_sfx ); ?>" onclick="wpeasycart_offer_apply_code( '<?php echo esc_attr( $offer_code_nonce ); ?>', '<?php echo esc_attr( $offer_sfx ); ?>' );"><?php echo wp_easycart_offers_text( 'cart_coupons', 'cart_apply_coupon' ); ?></div>
		<div class="ec_cart_button_working" id="ec_offer_applying_code<?php echo esc_attr( $offer_sfx ); ?>"><?php echo wp_easycart_language()->get_text( 'cart', 'cart_please_wait' ); ?></div>
	</div>
</div>
<?php } ?>