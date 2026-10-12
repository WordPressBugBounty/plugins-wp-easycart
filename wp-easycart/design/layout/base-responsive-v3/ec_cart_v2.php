<?php
if ( ! isset( $cartpage ) ) {
	$cartpage = $this;
}
/* 6.0.4: a one-page checkout phrase, or the English until the store's wording has it ( phrases added in 6.0.4 ). */
$wpec_cart_text = function( $key, $fallback ) {
	$text = wp_easycart_language()->get_text( 'cart_onepage', $key );
	return ( is_string( $text ) && '' !== trim( $text ) ) ? $text : $fallback;
};
?>

<?php if ( '' != get_option( 'ec_option_google_ga4_property_id' ) ) { ?>
<script>
	jQuery( document ).ready( function() {
		<?php if ( get_option( 'ec_option_google_ga4_tag_manager' ) ) { ?>
		dataLayer.push( { ecommerce: null } );
		dataLayer.push( {
			event: "view_cart",
			ecommerce: {
		<?php } else { ?>
		gtag( "event", "view_cart", {
		<?php }?>
			currency: "<?php echo esc_attr( wp_easycart_base_currency_code() ); ?>",
			value: <?php echo esc_attr( number_format( $cartpage->order_totals->grand_total, 2, '.', '' ) ); ?>,
			coupon_code: "<?php echo esc_attr( $cartpage->coupon_code ); ?>",
			items: [
			<?php for( $i=0; $i < count( $cartpage->cart->cart ); $i++ ) { ?>
				{
					item_id: "<?php echo esc_attr( $cartpage->cart->cart[$i]->model_number ); ?>",
					item_name: "<?php echo esc_attr( $cartpage->cart->cart[$i]->title ); ?>",
					index: <?php echo esc_attr( $i ); ?>,
					price: <?php echo esc_attr( number_format( $cartpage->cart->cart[$i]->unit_price, 2, '.', '' ) ); ?>,
					item_brand: "<?php echo esc_attr( $cartpage->cart->cart[$i]->manufacturer_name ) ; ?>",
					quantity: <?php echo esc_attr( number_format( $cartpage->cart->cart[$i]->quantity, 2, '.', '' ) ); ?>
				},
			<?php } ?>
			]
		<?php if ( ! get_option( 'ec_option_google_ga4_tag_manager' ) ) { ?>} );<?php } else { ?>} } );<?php }?>
	} );
</script>
<?php }?>

<?php do_action( 'wpeasycart_cart_top_left', $cartpage ); ?>

<div class="ec_cart_backorders_present" id="ec_cart_backorder_message"<?php if( !$cartpage->cart->has_backordered_item( ) ){ ?> style="display:none;"<?php }?>><?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_backordered_message' ); ?></div>

<?php do_action( 'wpeasycart_cart_post_minimum_notice' ); ?>

<div class="ec_cart_table">
	<div class="ec_cart_table_headers">
		<div class="ec_cart_table_header_spacer ec_cart_table_details"></div>
		<div class="ec_cart_table_header_price ec_cart_table_price"><?php echo wp_easycart_language( )->get_text( 'cart', 'cart_header_column3' )?></div>
		<div class="ec_cart_table_header_quantity ec_cart_table_quantity"><?php echo wp_easycart_language( )->get_text( 'cart', 'cart_header_column4' )?></div>
		<div class="ec_cart_table_header_total ec_cart_table_total"><?php echo wp_easycart_language( )->get_text( 'cart', 'cart_header_column5' )?></div>
	</div>

	<div class="ec_cart_table_body">
		<?php for( $cartitem_index = 0; $cartitem_index<count( $cartpage->cart->cart ); $cartitem_index++ ){ ?>
		<?php do_action( 'wpeasycart_cart_item_row_pre', $cartpage->cart->cart[$cartitem_index], $cartitem_index ); ?>
		<div class="ec_cart_table_error_row" id="ec_cartitem_max_error_<?php echo esc_attr( $cartpage->cart->cart[$cartitem_index]->cartitem_id ); ?>">
			<div><?php echo wp_easycart_language( )->get_text( 'cart', 'cartitem_max_error' )?></div>
		</div>

		<div class="ec_cart_table_error_row" id="ec_cartitem_min_error_<?php echo esc_attr( $cartpage->cart->cart[$cartitem_index]->cartitem_id ); ?>">
			<div><?php echo wp_easycart_language( )->get_text( 'cart', 'cartitem_min_error' )?></div>
		</div>

		<div class="ec_cart_table_row" id="ec_cartitem_row_<?php echo esc_attr( $cartpage->cart->cart[$cartitem_index]->cartitem_id ); ?>">
			<div class="ec_cart_table_column_details ec_cart_table_details">
				<div class="ec_cart_table_image">
					<img src="<?php echo esc_attr( $cartpage->cart->cart[$cartitem_index]->get_image_url( ) ); ?>" alt="<?php echo esc_attr( str_replace( '"', '&quot;', $cartpage->cart->cart[$cartitem_index]->title ) ); ?>" />
				</div>
				<?php /* 6.0.4: the id the cart's scripts write a line's discount notes into ( ec_update_cart(), the Offers fragments ). */ ?>
				<div class="ec_cart_table_details_content" id="ec_cartitem_details_<?php echo esc_attr( $cartpage->cart->cart[$cartitem_index]->cartitem_id ); ?>">
					<a href="<?php echo esc_attr( $cartpage->cart->cart[$cartitem_index]->get_title_link( ) ); ?>" class="ec_cartitem_title"><?php $cartpage->cart->cart[$cartitem_index]->display_title( ); ?></a>
					<div class="ec_cart_table_mobile_price" id="ec_cartitem_price_mobile_<?php echo esc_attr( $cartpage->cart->cart[$cartitem_index]->cartitem_id ); ?>"><?php echo wp_easycart_escape_html( $cartpage->cart->cart[$cartitem_index]->get_unit_price( ) ); ?></div>
					<?php if( 
						$cartpage->cart->cart[$cartitem_index]->use_optionitem_quantity_tracking && 
						$cartpage->cart->cart[$cartitem_index]->allow_backorders
					){ ?>
					<div class="ec_cart_backorder_date" id="ec_cartitem_backorder_<?php echo esc_attr( $cartpage->cart->cart[$cartitem_index]->cartitem_id ); ?>"<?php if( $cartpage->cart->cart[$cartitem_index]->optionitem_stock_quantity >= $cartpage->cart->cart[$cartitem_index]->quantity ){ ?> style="display:none;"<?php }?>><?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_some_backordered' ); ?> <?php echo esc_attr( $cartpage->cart->cart[$cartitem_index]->optionitem_stock_quantity ); ?> <?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_some_backordered_remaining' ); ?><?php if( $cartpage->cart->cart[$cartitem_index]->backorder_fill_date != "" ){ ?> <?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_backorder_until' ); ?> <?php echo wp_easycart_escape_html( $cartpage->cart->cart[$cartitem_index]->backorder_fill_date ); ?><?php }?></div>

					<?php }else if( 
						$cartpage->cart->cart[$cartitem_index]->show_stock_quantity &&
						$cartpage->cart->cart[$cartitem_index]->allow_backorders ){ ?>
					<div class="ec_cart_backorder_date" id="ec_cartitem_backorder_<?php echo esc_attr( $cartpage->cart->cart[$cartitem_index]->cartitem_id ); ?>"<?php if( $cartpage->cart->cart[$cartitem_index]->stock_quantity > $cartpage->cart->cart[$cartitem_index]->quantity ){ ?> style="display:none;"<?php }?>><?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_backordered' ); ?><?php if( $cartpage->cart->cart[$cartitem_index]->backorder_fill_date != "" ){ ?> <?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_backorder_until' ); ?> <?php echo wp_easycart_escape_html( $cartpage->cart->cart[$cartitem_index]->backorder_fill_date ); ?><?php }?></div>

					<?php } ?>
					<?php if( $cartpage->cart->cart[$cartitem_index]->optionitem1_name ){ ?>
						<dl>
							<dt><?php echo esc_attr( $cartpage->cart->cart[$cartitem_index]->optionitem1_name ); ?><?php
								if ( $cartpage->cart->cart[$cartitem_index]->optionitem1_price > 0 ) {
									?><span class="ec_cart_line_optionitem_pricing"> ( +<?php echo esc_attr( $GLOBALS['currency']->get_currency_display( $cartpage->cart->cart[$cartitem_index]->optionitem1_price ) ); ?> )</span><?php
								} else if ( $cartpage->cart->cart[$cartitem_index]->optionitem1_price < 0 ) {
									?><span class="ec_cart_line_optionitem_pricing"> ( <?php echo esc_attr( $GLOBALS['currency']->get_currency_display( $cartpage->cart->cart[$cartitem_index]->optionitem1_price ) ); ?> )</span><?php
								} ?></dt>

						<?php if( $cartpage->cart->cart[$cartitem_index]->optionitem2_name ){ ?>
							<dt><?php echo esc_attr( $cartpage->cart->cart[$cartitem_index]->optionitem2_name ); ?><?php
								if ( $cartpage->cart->cart[$cartitem_index]->optionitem2_price > 0 ) {
									?><span class="ec_cart_line_optionitem_pricing"> ( +<?php echo esc_attr( $GLOBALS['currency']->get_currency_display( $cartpage->cart->cart[$cartitem_index]->optionitem2_price ) ); ?> )</span><?php
								} else if ( $cartpage->cart->cart[$cartitem_index]->optionitem2_price < 0 ) {
									?><span class="ec_cart_line_optionitem_pricing"> ( <?php echo esc_attr( $GLOBALS['currency']->get_currency_display( $cartpage->cart->cart[$cartitem_index]->optionitem2_price ) ); ?> )</span><?php
								} ?></dt>
						<?php }?>

						<?php if( $cartpage->cart->cart[$cartitem_index]->optionitem3_name ){ ?>
							<dt><?php echo esc_attr( $cartpage->cart->cart[$cartitem_index]->optionitem3_name ); ?><?php
								if ( $cartpage->cart->cart[$cartitem_index]->optionitem3_price > 0 ) {
									?><span class="ec_cart_line_optionitem_pricing"> ( +<?php echo esc_attr( $GLOBALS['currency']->get_currency_display( $cartpage->cart->cart[$cartitem_index]->optionitem3_price ) ); ?> )</span><?php
								} else if ( $cartpage->cart->cart[$cartitem_index]->optionitem3_price < 0 ) {
									?><span class="ec_cart_line_optionitem_pricing"> ( <?php echo esc_attr( $GLOBALS['currency']->get_currency_display( $cartpage->cart->cart[$cartitem_index]->optionitem3_price ) ); ?> )</span><?php
								} ?></dt>
						<?php }?>

						<?php if( $cartpage->cart->cart[$cartitem_index]->optionitem4_name ){ ?>
							<dt><?php echo esc_attr( $cartpage->cart->cart[$cartitem_index]->optionitem4_name ); ?><?php
								if ( $cartpage->cart->cart[$cartitem_index]->optionitem4_price > 0 ) {
									?><span class="ec_cart_line_optionitem_pricing"> ( +<?php echo esc_attr( $GLOBALS['currency']->get_currency_display( $cartpage->cart->cart[$cartitem_index]->optionitem4_price ) ); ?> )</span><?php
								} else if ( $cartpage->cart->cart[$cartitem_index]->optionitem4_price < 0 ) {
									?><span class="ec_cart_line_optionitem_pricing"> ( <?php echo esc_attr( $GLOBALS['currency']->get_currency_display( $cartpage->cart->cart[$cartitem_index]->optionitem4_price ) ); ?> )</span><?php
								} ?></dt>
						<?php }?>

						<?php if( $cartpage->cart->cart[$cartitem_index]->optionitem5_name ){ ?>
							<dt><?php echo esc_attr( $cartpage->cart->cart[$cartitem_index]->optionitem5_name ); ?><?php
								if ( $cartpage->cart->cart[$cartitem_index]->optionitem5_price > 0 ) {
									?><span class="ec_cart_line_optionitem_pricing"> ( +<?php echo esc_attr( $GLOBALS['currency']->get_currency_display( $cartpage->cart->cart[$cartitem_index]->optionitem5_price ) ); ?> )</span><?php
								} else if ( $cartpage->cart->cart[$cartitem_index]->optionitem5_price < 0 ) {
									?><span class="ec_cart_line_optionitem_pricing"> ( <?php echo esc_attr( $GLOBALS['currency']->get_currency_display( $cartpage->cart->cart[$cartitem_index]->optionitem5_price ) ); ?> )</span><?php
								} ?></dt>
						<?php }?>
						</dl>
					<?php }?>

					<?php if( $cartpage->cart->cart[$cartitem_index]->use_advanced_optionset || $cartpage->cart->cart[$cartitem_index]->use_both_option_types ){ ?>
					<dl>
						<?php foreach( $cartpage->cart->cart[$cartitem_index]->advanced_options as $advanced_option_set ){ ?>
							<?php if( $advanced_option_set->option_type == "grid" ){ ?>
							<dt><?php echo wp_easycart_escape_html( $advanced_option_set->optionitem_name ); ?>: <?php echo esc_attr( $advanced_option_set->optionitem_value ); ?><?php 
							if ( $advanced_option_set->optionitem_enable_custom_price_label && ( $advanced_option_set->optionitem_price != 0 || ( isset( $advanced_option_set->optionitem_price ) && $advanced_option_set->optionitem_price != 0 ) || ( isset( $advanced_option_set->optionitem_price_onetime ) && $advanced_option_set->optionitem_price_onetime != 0 ) ) ) {
								echo '<span class="ec_cart_line_optionitem_pricing"> ' . esc_attr( wp_easycart_language( )->convert_text( $advanced_option_set->optionitem_custom_price_label ) ) . '</span>';
							} else if ( $advanced_option_set->optionitem_price > 0 ) {
								echo '<span class="ec_cart_line_optionitem_pricing"> (+' . esc_attr( $GLOBALS['currency']->get_currency_display( $advanced_option_set->optionitem_price ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_item_adjustment' ) . ')</span>';
							} else if ( $advanced_option_set->optionitem_price < 0 ) {
								echo '<span class="ec_cart_line_optionitem_pricing"> (' . esc_attr( $GLOBALS['currency']->get_currency_display( $advanced_option_set->optionitem_price ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_item_adjustment' ) . ')'; }else if( $advanced_option_set->optionitem_price_onetime > 0 ){ echo ' (+' . esc_attr( $GLOBALS['currency']->get_currency_display( $advanced_option_set->optionitem_price_onetime ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_order_adjustment' ) . ')</span>';
							} else if ( $advanced_option_set->optionitem_price_onetime < 0 ) {
								echo '<span class="ec_cart_line_optionitem_pricing"> (' . esc_attr( $GLOBALS['currency']->get_currency_display( $advanced_option_set->optionitem_price_onetime ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_order_adjustment' ) . ')</span>';
							} else if ( $advanced_option_set->optionitem_price_override > -1 ) {
								echo '<span class="ec_cart_line_optionitem_pricing"> (' . wp_easycart_language( )->get_text( 'cart', 'cart_item_new_price_option' ) . ' ' . esc_attr( $GLOBALS['currency']->get_currency_display( $advanced_option_set->optionitem_price_override ) ) . ')</span>';
							} ?></dt>
							<?php }else if( $advanced_option_set->option_type == "dimensions1" || $advanced_option_set->option_type == "dimensions2" ){ ?>
								<dt><?php echo wp_easycart_escape_html( $advanced_option_set->option_label ); ?>: <?php $dimensions = json_decode( $advanced_option_set->optionitem_value ); if( count( $dimensions ) == 2 ){ echo esc_attr( $dimensions[0] ); if( !get_option( 'ec_option_enable_metric_unit_display' ) ){ echo "\""; } echo esc_attr( " x " . $dimensions[1] ); if( !get_option( 'ec_option_enable_metric_unit_display' ) ){ echo "\""; } }else if( count( $dimensions ) == 4 ){ echo esc_attr( $dimensions[0] . " " . $dimensions[1] . "\" x " . $dimensions[2] . " " . $dimensions[3] ) . "\""; } ?></dt>

							<?php }else{ ?>
							<dt><?php echo wp_easycart_escape_html( $advanced_option_set->option_label ); ?>: <?php echo esc_attr( $advanced_option_set->optionitem_value ); ?><?php 
							if ( $advanced_option_set->optionitem_enable_custom_price_label && ( $advanced_option_set->optionitem_price != 0 || ( isset( $advanced_option_set->optionitem_price ) && $advanced_option_set->optionitem_price != 0 ) || ( isset( $advanced_option_set->optionitem_price_onetime ) && $advanced_option_set->optionitem_price_onetime != 0 ) ) ) {
								echo '<span class="ec_cart_line_optionitem_pricing"> ' . esc_attr( wp_easycart_language( )->convert_text( $advanced_option_set->optionitem_custom_price_label ) ) . '</span>';
							} else if( $advanced_option_set->optionitem_price > 0 ) {
								echo '<span class="ec_cart_line_optionitem_pricing">' . esc_attr( ' (+' . $GLOBALS['currency']->get_currency_display( $advanced_option_set->optionitem_price ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_item_adjustment' ) . ')</span>';
							} else if ( $advanced_option_set->optionitem_price < 0 ) {
								echo '<span class="ec_cart_line_optionitem_pricing">' . esc_attr( ' (' . $GLOBALS['currency']->get_currency_display( $advanced_option_set->optionitem_price ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_item_adjustment' ) . ')</span>';
							} else if ( $advanced_option_set->optionitem_price_onetime > 0 ) {
								echo '<span class="ec_cart_line_optionitem_pricing">' . esc_attr( ' (+' . $GLOBALS['currency']->get_currency_display( $advanced_option_set->optionitem_price_onetime ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_order_adjustment' ) . ')</span>';
							} else if ( $advanced_option_set->optionitem_price_onetime < 0 ) {
								echo '<span class="ec_cart_line_optionitem_pricing">' . esc_attr( ' (' . $GLOBALS['currency']->get_currency_display( $advanced_option_set->optionitem_price_onetime ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_order_adjustment' ) . ')</span>';
							} else if ( $advanced_option_set->optionitem_price_override > -1 ) {
								echo '<span class="ec_cart_line_optionitem_pricing"> (' . wp_easycart_language( )->get_text( 'cart', 'cart_item_new_price_option' ) . ' ' . esc_attr( $GLOBALS['currency']->get_currency_display( $advanced_option_set->optionitem_price_override ) ) . ')</span>';
							} ?></dt>
							<?php } ?>
						<?php }?>
					</dl>
					<?php }?>

					<?php if( $cartpage->cart->cart[$cartitem_index]->is_giftcard ){ ?>
					<dl>
						<dt><?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_gift_card_recipient_name' ); ?>: <?php echo esc_attr( $cartpage->cart->cart[$cartitem_index]->gift_card_to_name ); ?></dt>
						<dt><?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_gift_card_recipient_email' ); ?>: <?php echo esc_attr( $cartpage->cart->cart[$cartitem_index]->gift_card_email ); ?></dt>
						<dt><?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_gift_card_sender_name' ); ?>: <?php echo esc_attr( $cartpage->cart->cart[$cartitem_index]->gift_card_from_name ); ?></dt>
						<dt><?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_gift_card_message' ); ?>: <?php echo esc_attr( $cartpage->cart->cart[$cartitem_index]->gift_card_message ); ?></dt>
					</dl>
					<?php }?>

					<?php if( $cartpage->cart->cart[$cartitem_index]->is_deconetwork ){ ?>
					<dl>
						<dt><?php echo esc_attr( $cartpage->cart->cart[$cartitem_index]->deconetwork_options ); ?></dt>
						<dt><?php echo "<a href=\"https://" . esc_attr( get_option( 'ec_option_deconetwork_url' ) ) . esc_attr( $cartpage->cart->cart[$cartitem_index]->deconetwork_edit_link ) . "\">" . wp_easycart_language( )->get_text( 'cart', 'deconetwork_edit' ) . "</a>"; ?></dt>
					</dl>
					<?php }?>
					<?php do_action( 'wp_easycart_cartitem_post_optionitems', $cartpage->cart->cart[$cartitem_index] ); ?>
				</div>
			</div>
			<div class="ec_cart_table_column_price ec_cart_table_price" id="ec_cartitem_price_<?php echo esc_attr( $cartpage->cart->cart[$cartitem_index]->cartitem_id ); ?>"><?php echo wp_easycart_escape_html( $cartpage->cart->cart[$cartitem_index]->get_unit_price( ) ); ?></div>
			<div class="ec_cart_table_column_quantity ec_cart_table_quantity">
				<?php if( $cartpage->cart->cart[$cartitem_index]->grid_quantity > 0 ){ ?>
					<?php echo esc_attr( $cartpage->cart->cart[$cartitem_index]->grid_quantity ); ?>
				<?php }else if( $cartpage->cart->cart[$cartitem_index]->is_deconetwork ){ ?>
					<?php echo esc_attr( $cartpage->cart->cart[$cartitem_index]->quantity ); ?>
				<?php }else{ ?>
				<?php if ( wp_easycart_offers_active() && ( $cartpage->cart->cart[ $cartitem_index ]->free_gift_offer_id > 0 || '' != $cartpage->cart->cart[ $cartitem_index ]->bundle_group_key ) ) { ?>
				<div class="ec_cartitem_updating" id="ec_cartitem_updating_<?php echo esc_attr( $cartpage->cart->cart[$cartitem_index]->cartitem_id ); ?>"></div>
				<span class="ec_offer_locked_quantity"><?php echo esc_attr( $cartpage->cart->cart[ $cartitem_index ]->quantity ); ?></span>
				<?php } else { ?>
				<?php /* 6.0.4: a stepper ( − quantity + ); the buttons change the box, whose change event saves it as before. */ ?>
				<div class="ec_cart_table_qty">
					<button type="button" class="ec_cart_table_qty_step ec_cart_table_qty_minus" data-step="-1" aria-label="<?php echo esc_attr( wp_strip_all_tags( $wpec_cart_text( 'quantity_decrease', __( 'Decrease quantity', 'wp-easycart' ) ) ) ); ?>">&minus;</button>
					<input type="number" value="<?php echo esc_attr( $cartpage->cart->cart[$cartitem_index]->quantity ); ?>" id="ec_quantity_<?php echo esc_attr( $cartpage->cart->cart[$cartitem_index]->cartitem_id ); ?>" autocomplete="off" step="1" min="<?php echo esc_attr( $cartpage->cart->cart[$cartitem_index]->min_quantity ); ?>" max="<?php echo esc_attr( $cartpage->cart->cart[$cartitem_index]->max_quantity ); ?>"<?php echo ( class_exists( 'wp_easycart_cart_quantity' ) ) ? wp_easycart_cart_quantity::note_attributes( $cartpage->cart->cart[$cartitem_index] ) : ''; ?> class="ec_quantity ec_table_quantity_box" aria-label="<?php echo esc_attr( wp_strip_all_tags( wp_easycart_language( )->get_text( 'cart', 'cart_header_column4' ) ) ); ?>" onchange="ec_cartitem_update_v2( '<?php echo esc_attr( $cartpage->cart->cart[$cartitem_index]->cartitem_id ); ?>', '<?php echo esc_attr( $cartpage->cart->cart[$cartitem_index]->cartitem_id ); ?>', '<?php echo esc_attr( wp_create_nonce( 'wp-easycart-update-cart-item-' . (int) $cartpage->cart->cart[$cartitem_index]->cartitem_id ) ); ?>' );" />
					<button type="button" class="ec_cart_table_qty_step ec_cart_table_qty_plus" data-step="1" aria-label="<?php echo esc_attr( wp_strip_all_tags( $wpec_cart_text( 'quantity_increase', __( 'Increase quantity', 'wp-easycart' ) ) ) ); ?>">+</button>
					<div class="ec_cartitem_updating" id="ec_cartitem_updating_<?php echo esc_attr( $cartpage->cart->cart[$cartitem_index]->cartitem_id ); ?>"></div>
				</div>
				<?php } ?>
				<?php }?>
				<div class="ec_cartitem_delete<?php if ( wp_easycart_offers_active() && $cartpage->cart->cart[ $cartitem_index ]->free_gift_offer_id > 0 ) { ?> ec_offer_locked_control<?php } ?>" id="ec_cartitem_delete_<?php echo esc_attr( $cartpage->cart->cart[$cartitem_index]->cartitem_id ); ?>" onclick="ec_cartitem_delete( '<?php echo esc_attr( $cartpage->cart->cart[$cartitem_index]->cartitem_id ); ?>', '<?php echo esc_attr( $cartpage->cart->cart[$cartitem_index]->model_number ); ?>', '<?php echo esc_attr( wp_create_nonce( 'wp-easycart-delete-cart-item-' . (int) $cartpage->cart->cart[$cartitem_index]->cartitem_id ) ); ?>' );<?php
				if ( '' != get_option( 'ec_option_google_ga4_property_id' ) ) {
					echo 'ec_ga4_remove_from_cart( \'' . esc_attr( $cartpage->cart->cart[$cartitem_index]->model_number ) . '\', \'' . esc_attr( str_replace( "'", "\'", $cartpage->cart->cart[$cartitem_index]->title ) ) . '\', jQuery( document.getElementById( \'ec_quantity_' . esc_attr( $cartpage->cart->cart[$cartitem_index]->cartitem_id ) . '\' ) ).val(), \'' . esc_attr( number_format( $cartpage->cart->cart[$cartitem_index]->unit_price, 2, '.', '' ) ) . '\', \'' . esc_attr( wp_easycart_base_currency_code() ) . '\' );';
				}
				?>"><?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_onepage', 'remove' ) ); ?></div>
				<div class="ec_cartitem_deleting" id="ec_cartitem_deleting_<?php echo esc_attr( $cartpage->cart->cart[$cartitem_index]->cartitem_id ); ?>"></div>
			</div>
			<div class="ec_cart_table_column_total ec_cart_table_total" id="ec_cartitem_total_<?php echo esc_attr( $cartpage->cart->cart[$cartitem_index]->cartitem_id ); ?>"><?php echo wp_easycart_escape_html( $cartpage->cart->cart[$cartitem_index]->get_total( ) ); ?></div>
		</div>
		<?php }?>
	</div>
</div>


<?php
/* 6.0.4: Stripe's pay later message only for the methods switched on ( Affirm, Klarna, Afterpay ). With none, the empty list made
   Stripe throw an IntegrationError, which stopped the cart's scripts: the subtotal kept its old amount after a quantity change and
   Edit Cart's loader never closed. */
$wpec_paylater_types   = array();
$wpec_paylater_minimum = (int) get_option( 'ec_option_stripe_pay_later_minimum' );
if ( 'stripe' == get_option( 'ec_option_payment_process_method' ) || 'stripe_connect' == get_option( 'ec_option_payment_process_method' ) ) {
	if ( get_option( 'ec_option_stripe_affirm' ) ) {
		$wpec_paylater_types[] = 'affirm';
	}
	if ( get_option( 'ec_option_stripe_klarna' ) ) {
		$wpec_paylater_types[] = 'klarna';
	}
	if ( get_option( 'ec_option_stripe_afterpay' ) ) {
		$wpec_paylater_types[] = 'afterpay_clearpay';
	}
}
$wpec_paylater_min_notice = ( count( $wpec_paylater_types ) > 0 && $wpec_paylater_minimum > 50 );
/* 6.0.4: what the discounts take off before checkout, and the subtotal after them ( ec_cartpage::cart_screen_totals() ). */
$wpec_cart_totals = ( method_exists( $cartpage, 'cart_screen_totals' ) ) ? $cartpage->cart_screen_totals() : null;
?>
<div class="ec_cart_table_subtotal_row">
	<?php if ( count( $wpec_paylater_types ) > 0 ) { ?>
	<div class="ec_cart_table_split_payments">
		<?php if ( $wpec_paylater_min_notice ) { ?>
		<div class="paylater_message_v2" data-min-price="<?php echo (int) $wpec_paylater_minimum; ?>" <?php if ( $cartpage->order_totals->sub_total >= $wpec_paylater_minimum ) { echo ' style="display:none;"'; } ?>><?php echo wp_easycart_language( )->get_text( 'cart_totals', 'cart_totals_min_buy_now' ); ?> <?php echo esc_html( $GLOBALS['currency']->get_currency_display( $wpec_paylater_minimum ) ); ?></div>
		<?php } ?>
		<div id="wpec-payment-method-messaging-element" data-theme="<?php echo esc_attr( get_option( 'ec_option_stripe_payment_theme' ) ); ?>" data-currency="<?php echo esc_attr( $GLOBALS['currency']->get_currency_code() ); ?>" data-types="<?php echo esc_attr( implode( ',', $wpec_paylater_types ) ); ?>" data-country="<?php echo esc_attr( ( '' != $GLOBALS['ec_cart_data']->cart_data->shipping_country ) ? $GLOBALS['ec_cart_data']->cart_data->shipping_country : get_option( 'ec_option_stripe_company_country' ) ); ?>"<?php if ( $wpec_paylater_min_notice && $cartpage->order_totals->sub_total < $wpec_paylater_minimum ) { echo ' style="display:none;"'; } ?>></div>
		<script>
		jQuery( document ).ready( function() {
			ec_cart_stripe_paylater_messaging_v2( <?php echo esc_attr( (int) ( $cartpage->order_totals->sub_total * 100 ) ); ?> );
		} );
		</script>
	</div>
	<?php } ?>

	<div class="ec_cart_table_subtotal">
		<div class="ec_cart_table_summary_rows">
			<div class="ec_cart_table_summary_row ec_cart_table_summary_subtotal">
				<span class="ec_cart_table_subtotal_label"><?php echo wp_easycart_language( )->get_text( 'cart_totals', 'cart_totals_subtotal' )?></span>
				<span class="ec_cart_table_subtotal_amount"><?php echo esc_attr( $cartpage->get_subtotal( ) ); ?></span>
			</div>
			<?php /* 6.0.4: each offer's row, as the cart totals and the checkout show them ( WP EasyCart PRO's Offers redraws them after a change ). */ ?>
			<?php if ( wp_easycart_offers_active() ) { ?>
			<?php wp_easycart_offers_template( 'ec_offer_totals_rows.php', array( 'offer_result' => ( isset( $cartpage->offer_result ) ) ? $cartpage->offer_result : null ) ); ?>
			<?php } ?>
			<?php if ( is_array( $wpec_cart_totals ) ) { ?>
			<div class="ec_cart_table_summary_row ec_cart_table_discount_row ec_cart_price_row_discount_total"<?php if ( ! $wpec_cart_totals['discount_show'] ) { ?> style="display:none;"<?php } ?>>
				<span class="ec_cart_table_summary_label ec_cart_price_row_label"><?php echo wp_kses_post( wp_easycart_language( )->get_text( 'cart_totals', 'cart_totals_discounts' ) ); ?></span>
				<span class="ec_cart_table_discount_amount"><?php echo esc_html( $wpec_cart_totals['discount_display'] ); ?></span>
			</div>
			<div class="ec_cart_table_summary_row ec_cart_table_estimate_row"<?php if ( ! $wpec_cart_totals['estimate_show'] ) { ?> style="display:none;"<?php } ?>>
				<span class="ec_cart_table_summary_label"><?php echo wp_kses_post( $wpec_cart_text( 'estimated_total', __( 'Estimated total', 'wp-easycart' ) ) ); ?></span>
				<span class="ec_cart_table_estimate_amount"><?php echo esc_html( $wpec_cart_totals['estimate_display'] ); ?></span>
			</div>
			<?php } ?>
		</div>

		<div class="ec_cart_table_message"><?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_onepage', 'checkout_note' ) ); ?></div>

		<?php if( apply_filters( 'wpeasycart_show_checkout_button', true ) ){ ?>
		<div class="ec_cart_table_checkout_button_row">
			<?php
				$checkout_page_url = $cartpage->cart_page;
				if ( get_option( 'ec_option_onepage_checkout_cart_first' ) ) {
					$checkout_page_url .= $cartpage->permalink_divider . 'eccheckout=information';
				}
			?>
			<?php /* 6.0.2: InitiateCheckout fires when checkout starts ( wp_easycart_meta_initiate_checkout() ), not on this button. */ ?>
			<a class="ec_cart_table_checkout_button" href="<?php echo esc_url_raw( $checkout_page_url ); ?>" onclick="return true;"<?php do_action( 'wpeasycart_checkout_button_ahref' ); ?>><?php echo wp_easycart_language( )->get_text( 'cart', 'cart_checkout' ); ?>
				<div class="wp-easycart-ld-ring wp-easycart-ld-spin" style="color:#fff"></div>
			</a>
		</div>
		<?php } ?>

		<?php do_action( 'wp_easycart_cart_after_checkout_button', $cartpage ); ?>

		<div class="ec_cart_table_shopping_button">
			<?php do_action( 'wp_easycart_cart_before_continue_shopping' ); ?>
			<a class="ec_cart_table_continue_shopping" href="<?php echo esc_attr( $cartpage->return_to_store_page( $cartpage->store_page ) ); ?>"><?php echo wp_easycart_language( )->get_text( 'cart', 'cart_continue_shopping' ); ?></a>
		</div>
	</div>
</div>
