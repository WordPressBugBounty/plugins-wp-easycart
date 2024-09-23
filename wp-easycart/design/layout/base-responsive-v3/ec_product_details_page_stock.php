<?php if( ( $product->show_stock_quantity || $product->use_optionitem_quantity_tracking ) && $product->stock_quantity > 0 && get_option( 'ec_option_show_stock_quantity' ) ){ ?><div class="ec_details_stock_total_ele">
	<span id="ec_details_stock_quantity_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><?php echo esc_attr( $product->stock_quantity ); ?></span> 
	<?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_left_in_stock' ); ?>
</div>
<?php } ?>