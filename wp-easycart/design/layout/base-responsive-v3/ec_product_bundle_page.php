<?php
/**
 * Offers v2 — bundle contents block for the product details page.
 * Rendered on products with is_bundle = 1 (replaces the standard add-to-cart
 * block; see template integration doc).
 * Expects: $bundle_product (the ec_product object of the bundle).
 */
$bundle_data = ec_offer_display::get_bundle_page_data( $bundle_product->product_id );
if ( false === $bundle_data ) {
	return;
}
$offer_bundle_nonce = wp_create_nonce( 'wp-easycart-offer-bundle-' . $GLOBALS['ec_cart_data']->ec_cart_id );
?>
<div class="ec_bundle_container" id="ec_bundle_container_<?php echo esc_attr( $bundle_product->product_id ); ?>">
	<div class="ec_bundle_items">
		<div class="ec_bundle_items_title"><?php echo wp_easycart_language()->get_text( 'cart_offers', 'bundle_includes_title' ); ?></div>
		<?php foreach ( $bundle_data['items'] as $bundle_item ) { ?>
		<div class="ec_bundle_item" data-bundle-item-id="<?php echo esc_attr( $bundle_item->product_bundle_item_id ); ?>">
			<?php if ( '' != $bundle_item->image1 ) { ?>
			<img src="<?php echo esc_attr( $bundle_item->image1 ); ?>" alt="<?php echo esc_attr( $bundle_item->title ); ?>" class="ec_bundle_item_image" />
			<?php } ?>
			<div class="ec_bundle_item_content">
				<div class="ec_bundle_item_title"><?php echo esc_attr( $bundle_item->quantity ); ?> &times; <?php echo esc_attr( $bundle_item->title ); ?></div>
				<div class="ec_bundle_item_price"><?php echo esc_attr( $GLOBALS['currency']->get_currency_display( $bundle_item->component_price ) ); ?></div>
				<?php if ( $bundle_item->customer_selects_options ) { ?>
				<div class="ec_bundle_item_options" data-component-product-id="<?php echo esc_attr( $bundle_item->component_product_id ); ?>">
					<?php
					/* Render the component's classic optionset selectors
					 * (slots 1-5) straight from the option tables. The
					 * ec_product class constructs from a full product ROW,
					 * not an id, so it is not usable here; selections post
					 * through wpeasycart_offer_add_bundle() and are stock-
					 * validated server-side in add_bundle_to_cart(). */
					global $wpdb;
					$ec_bundle_component_row = $wpdb->get_row( $wpdb->prepare( 'SELECT option_id_1, option_id_2, option_id_3, option_id_4, option_id_5 FROM ec_product WHERE product_id = %d', (int) $bundle_item->component_product_id ) );
					if ( $ec_bundle_component_row ) {
						for ( $ec_bundle_slot = 1; $ec_bundle_slot <= 5; $ec_bundle_slot++ ) {
							$ec_bundle_option_id = (int) $ec_bundle_component_row->{'option_id_' . $ec_bundle_slot};
							if ( $ec_bundle_option_id <= 0 ) {
								continue;
							}
							$ec_bundle_option = $wpdb->get_row( $wpdb->prepare( 'SELECT option_name, option_label FROM ec_option WHERE option_id = %d', $ec_bundle_option_id ) );
							$ec_bundle_optionitems = $wpdb->get_results( $wpdb->prepare( 'SELECT optionitem_id, optionitem_name FROM ec_optionitem WHERE option_id = %d ORDER BY optionitem_order ASC', $ec_bundle_option_id ) );
							if ( 0 == count( $ec_bundle_optionitems ) ) {
								continue;
							}
							$ec_bundle_option_label = ( $ec_bundle_option && '' != $ec_bundle_option->option_label ) ? $ec_bundle_option->option_label : ( ( $ec_bundle_option ) ? $ec_bundle_option->option_name : '' );
							echo '<select class="ec_bundle_item_option_select" data-slot="' . esc_attr( $ec_bundle_slot ) . '">';
							echo '<option value="0">' . esc_attr( wp_easycart_language()->convert_text( $ec_bundle_option_label ) ) . '</option>';
							foreach ( $ec_bundle_optionitems as $ec_bundle_optionitem ) {
								echo '<option value="' . esc_attr( $ec_bundle_optionitem->optionitem_id ) . '">' . esc_attr( wp_easycart_language()->convert_text( $ec_bundle_optionitem->optionitem_name ) ) . '</option>';
							}
							echo '</select>';
						}
					}
					?>
				</div>
				<?php } ?>
			</div>
		</div>
		<?php } ?>
	</div>
	<div class="ec_bundle_pricing">
		<?php if ( $bundle_data['savings'] > 0 ) { ?>
		<div class="ec_bundle_pricing_compare">
			<span class="ec_bundle_pricing_strike"><?php echo esc_attr( $GLOBALS['currency']->get_currency_display( $bundle_data['component_sum'] ) ); ?></span>
			<span class="ec_bundle_pricing_savings"><?php echo wp_easycart_language()->get_text( 'cart_offers', 'bundle_save_label' ); ?> <?php echo esc_attr( $GLOBALS['currency']->get_currency_display( $bundle_data['savings'] ) ); ?></span>
		</div>
		<?php } ?>
		<div class="ec_bundle_pricing_price"><?php echo esc_attr( $GLOBALS['currency']->get_currency_display( $bundle_data['bundle_price'] ) ); ?></div>
	</div>
	<div class="ec_cart_button_row">
		<div class="ec_cart_button ec_bundle_add_button" onclick="wpeasycart_offer_add_bundle( <?php echo esc_attr( $bundle_product->product_id ); ?>, '<?php echo esc_attr( $offer_bundle_nonce ); ?>' );"><?php echo wp_easycart_language()->get_text( 'product_details', 'product_details_add_to_cart' ); ?></div>
		<div class="ec_cart_button_working" id="ec_bundle_adding_<?php echo esc_attr( $bundle_product->product_id ); ?>"><?php echo wp_easycart_language()->get_text( 'cart', 'cart_please_wait' ); ?></div>
	</div>
	<div class="ec_cart_error_message" id="ec_bundle_error_<?php echo esc_attr( $bundle_product->product_id ); ?>"></div>
</div>