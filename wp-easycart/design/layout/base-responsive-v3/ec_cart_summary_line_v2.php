<?php
/**
 * One line of the one-page checkout's order summary ( 6.0.4 ).
 *
 * Printed by wp_easycart_summary_lines() for the summary column and the phone summary, and by the cart AJAX answers that redraw the
 * lines. With "Quantities can be changed on the one-page checkout" on, a line has a − / + quantity stepper and a remove button; a
 * line whose quantity cannot change ( an Offers gift or bundle line, a grid or Deconetwork line ) and every line with the setting off
 * show their quantity on the picture instead.
 *
 * Variables: $cartitem ( ec_cartitem ), $cartitem_index ( int ), $wpec_summary_copy ( desktop | mobile ), $wpec_summary_edit ( bool ).
 *
 * @package WP EasyCart
 * @since 6.0.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wpec_summary_offers = ( function_exists( 'wp_easycart_offers_active' ) && wp_easycart_offers_active() );
$wpec_line_offer_locked = ( $wpec_summary_offers && ( $cartitem->free_gift_offer_id > 0 || '' != $cartitem->bundle_group_key ) );
$wpec_line_editable = ( $wpec_summary_edit && $cartitem->grid_quantity <= 0 && ! $cartitem->is_deconetwork && ! $wpec_line_offer_locked );
$wpec_line_removable = ( $wpec_summary_edit && ! ( $wpec_summary_offers && $cartitem->free_gift_offer_id > 0 ) );
$wpec_line_quantity = ( $cartitem->grid_quantity > 0 ) ? (int) $cartitem->grid_quantity : (int) $cartitem->quantity;
$wpec_line_title = wp_strip_all_tags( wp_easycart_language()->convert_text( ( $cartitem->is_deconetwork ) ? $cartitem->deconetwork_name : $cartitem->title ) );
$wpec_line_min = max( 1, (int) $cartitem->min_quantity );
$wpec_line_max = (int) $cartitem->max_quantity;
?>
<div class="ec_cart_price_row_v2 ec_cart_summary_line ec_cartitem_<?php echo esc_attr( $cartitem->cartitem_id ); ?> ec_cart_price_row_cartitem_<?php echo esc_attr( $cartitem_index ); ?><?php echo ( $wpec_line_editable ) ? ' ec_cart_summary_line_editable' : ''; ?><?php echo ( $wpec_line_editable || $wpec_line_removable ) ? ' ec_cart_summary_line_has_controls' : ''; ?>" data-cartitem="<?php echo esc_attr( $cartitem->cartitem_id ); ?>">
	<div class="ec_cart_image_row_v2">
		<img src="<?php echo esc_attr( $cartitem->get_image_url() ); ?>" alt="" />
		<?php if ( ! $wpec_line_editable && $wpec_line_quantity > 1 ) { ?>
		<span class="ec_cart_summary_badge"><span class="ec_cart_summary_sr"><?php echo esc_html( wp_strip_all_tags( wp_easycart_language()->get_text( 'cart', 'cart_header_column4' ) ) ); ?>: </span><?php echo esc_html( (string) $wpec_line_quantity ); ?></span>
		<?php } ?>
	</div>

	<div class="ec_cart_price_row_label_v2">
		<span class="ec_cart_summary_title"><?php $cartitem->display_title(); ?></span>
		<?php if ( $cartitem->stock_quantity <= 0 && $cartitem->allow_backorders ) { ?>
		<div class="ec_cart_backorder_date"><?php echo wp_kses_post( wp_easycart_language()->get_text( 'product_details', 'product_details_backordered' ) ); ?><?php if ( $cartitem->backorder_fill_date != '' ) { ?> <?php echo wp_kses_post( wp_easycart_language()->get_text( 'product_details', 'product_details_backorder_until' ) ); ?> <?php echo wp_easycart_escape_html( $cartitem->backorder_fill_date ); ?><?php } ?></div>
		<?php } ?>
		<?php if ( $cartitem->optionitem1_name ) { ?>
		<dl>
			<?php for ( $wpec_slot = 1; $wpec_slot <= 5; $wpec_slot++ ) {
				$wpec_option_name = $cartitem->{ 'optionitem' . $wpec_slot . '_name' };
				$wpec_option_price = $cartitem->{ 'optionitem' . $wpec_slot . '_price' };
				if ( ! $wpec_option_name ) {
					continue;
				} ?>
			<dt><?php echo esc_attr( $wpec_option_name ); ?><?php if ( $wpec_option_price > 0 ) { ?> ( +<?php echo esc_attr( $GLOBALS['currency']->get_currency_display( $wpec_option_price ) ); ?> )<?php } else if ( $wpec_option_price < 0 ) { ?> ( <?php echo esc_attr( $GLOBALS['currency']->get_currency_display( $wpec_option_price ) ); ?> )<?php } ?></dt>
			<?php } ?>
		</dl>
		<?php } ?>

		<?php if ( $cartitem->use_advanced_optionset || $cartitem->use_both_option_types ) { ?>
		<dl>
		<?php foreach ( $cartitem->advanced_options as $advanced_option_set ) { ?>
			<?php if ( $advanced_option_set->option_type == 'grid' ) { ?>
			<dt><?php echo wp_easycart_escape_html( $advanced_option_set->optionitem_name ); ?>: <?php echo esc_attr( $advanced_option_set->optionitem_value ); ?><?php
				if ( $advanced_option_set->optionitem_enable_custom_price_label && ( $advanced_option_set->optionitem_price != 0 || ( isset( $advanced_option_set->optionitem_price ) && $advanced_option_set->optionitem_price != 0 ) || ( isset( $advanced_option_set->optionitem_price_onetime ) && $advanced_option_set->optionitem_price_onetime != 0 ) ) ) {
					echo '<span class="ec_cart_line_optionitem_pricing"> ' . esc_attr( wp_easycart_language()->convert_text( $advanced_option_set->optionitem_custom_price_label ) ) . '</span>';
				} else if ( $advanced_option_set->optionitem_price > 0 ) {
					echo ' (+' . esc_attr( $GLOBALS['currency']->get_currency_display( $advanced_option_set->optionitem_price ) ) . ' ' . wp_kses_post( wp_easycart_language()->get_text( 'cart', 'cart_item_adjustment' ) ) . ')';
				} else if ( $advanced_option_set->optionitem_price < 0 ) {
					echo ' (' . esc_attr( $GLOBALS['currency']->get_currency_display( $advanced_option_set->optionitem_price ) ) . ' ' . wp_kses_post( wp_easycart_language()->get_text( 'cart', 'cart_item_adjustment' ) ) . ')';
				} else if ( $advanced_option_set->optionitem_price_onetime > 0 ) {
					echo ' (+' . esc_attr( $GLOBALS['currency']->get_currency_display( $advanced_option_set->optionitem_price_onetime ) ) . ' ' . wp_kses_post( wp_easycart_language()->get_text( 'cart', 'cart_order_adjustment' ) ) . ')';
				} else if ( $advanced_option_set->optionitem_price_onetime < 0 ) {
					echo ' (' . esc_attr( $GLOBALS['currency']->get_currency_display( $advanced_option_set->optionitem_price_onetime ) ) . ' ' . wp_kses_post( wp_easycart_language()->get_text( 'cart', 'cart_order_adjustment' ) ) . ')';
				} else if ( $advanced_option_set->optionitem_price_override > -1 ) {
					echo ' (' . wp_kses_post( wp_easycart_language()->get_text( 'cart', 'cart_item_new_price_option' ) ) . ' ' . esc_attr( $GLOBALS['currency']->get_currency_display( $advanced_option_set->optionitem_price_override ) ) . ')';
				} ?></dt>
			<?php } else if ( $advanced_option_set->option_type == 'dimensions1' || $advanced_option_set->option_type == 'dimensions2' ) { ?>
			<strong><?php echo wp_easycart_escape_html( $advanced_option_set->option_label ); ?>:</strong><br /><?php $dimensions = json_decode( $advanced_option_set->optionitem_value ); if ( count( $dimensions ) == 2 ) { echo esc_attr( $dimensions[0] ); if ( ! get_option( 'ec_option_enable_metric_unit_display' ) ) { echo '"'; } echo ' x ' . esc_attr( $dimensions[1] ); if ( ! get_option( 'ec_option_enable_metric_unit_display' ) ) { echo '"'; } } else if ( count( $dimensions ) == 4 ) { echo esc_attr( $dimensions[0] . ' ' . $dimensions[1] . '" x ' . $dimensions[2] . ' ' . $dimensions[3] ) . '"'; } ?><br />

			<?php } else { ?>
			<dt><?php echo wp_easycart_escape_html( $advanced_option_set->option_label ); ?>: <?php echo esc_attr( $advanced_option_set->optionitem_value ); ?><?php
				if ( $advanced_option_set->optionitem_enable_custom_price_label && ( $advanced_option_set->optionitem_price != 0 || ( isset( $advanced_option_set->optionitem_price ) && $advanced_option_set->optionitem_price != 0 ) || ( isset( $advanced_option_set->optionitem_price_onetime ) && $advanced_option_set->optionitem_price_onetime != 0 ) ) ) {
					echo '<span class="ec_cart_line_optionitem_pricing"> ' . esc_attr( wp_easycart_language()->convert_text( $advanced_option_set->optionitem_custom_price_label ) ) . '</span>';
				} else if ( $advanced_option_set->optionitem_price > 0 ) {
					echo ' (+' . esc_attr( $GLOBALS['currency']->get_currency_display( $advanced_option_set->optionitem_price ) ) . ' ' . wp_kses_post( wp_easycart_language()->get_text( 'cart', 'cart_item_adjustment' ) ) . ')';
				} else if ( $advanced_option_set->optionitem_price < 0 ) {
					echo ' (' . esc_attr( $GLOBALS['currency']->get_currency_display( $advanced_option_set->optionitem_price ) ) . ' ' . wp_kses_post( wp_easycart_language()->get_text( 'cart', 'cart_item_adjustment' ) ) . ')';
				} else if ( $advanced_option_set->optionitem_price_onetime > 0 ) {
					echo ' (+' . esc_attr( $GLOBALS['currency']->get_currency_display( $advanced_option_set->optionitem_price_onetime ) ) . ' ' . wp_kses_post( wp_easycart_language()->get_text( 'cart', 'cart_order_adjustment' ) ) . ')';
				} else if ( $advanced_option_set->optionitem_price_onetime < 0 ) {
					echo ' (' . esc_attr( $GLOBALS['currency']->get_currency_display( $advanced_option_set->optionitem_price_onetime ) ) . ' ' . wp_kses_post( wp_easycart_language()->get_text( 'cart', 'cart_order_adjustment' ) ) . ')';
				} else if ( $advanced_option_set->optionitem_price_override > -1 ) {
					echo ' (' . wp_kses_post( wp_easycart_language()->get_text( 'cart', 'cart_item_new_price_option' ) ) . ' ' . esc_attr( $GLOBALS['currency']->get_currency_display( $advanced_option_set->optionitem_price_override ) ) . ')';
				} ?></dt>
			<?php } ?>
		<?php } ?>
		</dl>
		<?php } ?>

		<?php if ( $cartitem->is_giftcard ) { ?>
		<dl>
		<dt><?php echo wp_kses_post( wp_easycart_language()->get_text( 'product_details', 'product_details_gift_card_recipient_name' ) ); ?>: <?php echo esc_attr( $cartitem->gift_card_to_name ); ?></dt>
		<dt><?php echo wp_kses_post( wp_easycart_language()->get_text( 'product_details', 'product_details_gift_card_recipient_email' ) ); ?>: <?php echo esc_attr( $cartitem->gift_card_email ); ?></dt>
		<dt><?php echo wp_kses_post( wp_easycart_language()->get_text( 'product_details', 'product_details_gift_card_sender_name' ) ); ?>: <?php echo esc_attr( $cartitem->gift_card_from_name ); ?></dt>
		<dt><?php echo wp_kses_post( wp_easycart_language()->get_text( 'product_details', 'product_details_gift_card_message' ) ); ?>: <?php echo esc_attr( $cartitem->gift_card_message ); ?></dt>
		</dl>
		<?php } ?>

		<?php if ( $cartitem->is_deconetwork ) { ?>
		<dl>
		<dt><?php echo esc_attr( $cartitem->deconetwork_options ); ?></dt>
		<dt><?php echo '<a href="https://' . esc_attr( get_option( 'ec_option_deconetwork_url' ) ) . esc_attr( $cartitem->deconetwork_edit_link ) . '">' . wp_kses_post( wp_easycart_language()->get_text( 'cart', 'deconetwork_edit' ) ) . '</a>'; ?></dt>
		</dl>
		<?php } ?>

		<?php do_action( 'wp_easycart_cartitem_post_optionitems', $cartitem ); ?>
	</div>

	<div class="ec_cart_price_row_total_v2"><?php echo wp_easycart_escape_html( $cartitem->get_total() ); ?></div>

	<?php /* Under the title and the total ( a grid in ec-store.css ): the stepper, then the bin at the end. */ ?>
	<?php if ( $wpec_line_editable || $wpec_line_removable ) { ?>
	<div class="ec_cart_summary_controls">
		<?php if ( $wpec_line_editable ) { ?>
		<div class="ec_cart_summary_qty">
			<button type="button" class="ec_cart_summary_qty_step ec_cart_summary_qty_minus" data-step="-1" aria-label="<?php echo esc_attr( wp_easycart_summary_text( 'decrease_quantity', 'Decrease quantity of [title]', $wpec_line_title ) ); ?>"<?php echo ( $cartitem->quantity <= $wpec_line_min ) ? ' aria-disabled="true"' : ''; ?>><svg aria-hidden="true" focusable="false" viewBox="0 0 16 16" width="14" height="14"><path d="M3 8h10" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" /></svg></button>
			<input type="number" value="<?php echo esc_attr( $cartitem->quantity ); ?>" id="<?php echo esc_attr( ( 'mobile' == $wpec_summary_copy ) ? 'ec_quantity_mobile_' . $cartitem->cartitem_id : 'ec_quantity_' . $cartitem->cartitem_id ); ?>" data-cartitem="<?php echo esc_attr( $cartitem->cartitem_id ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp-easycart-update-cart-item-' . $cartitem->cartitem_id ) ); ?>" autocomplete="off" inputmode="numeric" aria-label="<?php echo esc_attr( wp_easycart_summary_text( 'quantity_of', 'Quantity of [title]', $wpec_line_title ) ); ?>" data-error="<?php echo esc_attr( wp_strip_all_tags( wp_easycart_language()->get_text( 'cart_onepage', 'place_order_error' ) ) ); ?>" step="1" min="<?php echo esc_attr( $cartitem->min_quantity ); ?>" max="<?php echo esc_attr( $cartitem->max_quantity ); ?>"<?php echo ( class_exists( 'wp_easycart_cart_quantity' ) ) ? wp_easycart_cart_quantity::note_attributes( $cartitem ) : ''; ?> class="ec_quantity_small_v2" />
			<button type="button" class="ec_cart_summary_qty_step ec_cart_summary_qty_plus" data-step="1" aria-label="<?php echo esc_attr( wp_easycart_summary_text( 'increase_quantity', 'Increase quantity of [title]', $wpec_line_title ) ); ?>"<?php echo ( $wpec_line_max > 0 && $cartitem->quantity >= $wpec_line_max ) ? ' aria-disabled="true"' : ''; ?>><svg aria-hidden="true" focusable="false" viewBox="0 0 16 16" width="14" height="14"><path d="M3 8h10M8 3v10" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" /></svg></button>
		</div>
		<?php } ?>
		<?php if ( $wpec_line_removable ) { ?>
		<button type="button" class="ec_cart_summary_remove" data-cartitem="<?php echo esc_attr( $cartitem->cartitem_id ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp-easycart-delete-cart-item-' . (int) $cartitem->cartitem_id ) ); ?>" data-quantity="<?php echo esc_attr( (string) $wpec_line_quantity ); ?>" data-error="<?php echo esc_attr( wp_strip_all_tags( wp_easycart_language()->get_text( 'cart_onepage', 'place_order_error' ) ) ); ?>"<?php
		if ( '' != get_option( 'ec_option_google_ga4_property_id' ) ) {
			echo ' data-ga4="1" data-ga4-tm="' . ( get_option( 'ec_option_google_ga4_tag_manager' ) ? '1' : '0' ) . '" data-model="' . esc_attr( $cartitem->model_number ) . '" data-title="' . esc_attr( $wpec_line_title ) . '" data-price="' . esc_attr( number_format( (float) $cartitem->unit_price, 2, '.', '' ) ) . '" data-currency="' . esc_attr( wp_easycart_base_currency_code() ) . '" data-brand="' . esc_attr( $cartitem->manufacturer_name ) . '"';
		}
		?> aria-label="<?php echo esc_attr( wp_easycart_summary_text( 'remove_item', 'Remove [title]', $wpec_line_title ) ); ?>" title="<?php echo esc_attr( wp_strip_all_tags( wp_easycart_language()->get_text( 'cart_onepage', 'remove' ) ) ); ?>"><svg aria-hidden="true" focusable="false" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16" /><path d="M10 11v6M14 11v6" /><path d="M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12" /><path d="M9 7V4.5A1.5 1.5 0 0 1 10.5 3h3A1.5 1.5 0 0 1 15 4.5V7" /></svg></button>
		<?php } ?>
	</div>
	<?php } ?>
</div>
