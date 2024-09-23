<div class="ec_details_manufacturer">
	<span class="ec_details_manufacturer_label"><?php echo esc_attr( ( isset( $atts['label_text'] ) ) ? $atts['label_text'] : wp_easycart_language( )->get_text( 'product_details', 'product_details_manufacturer' ) ); ?></span>
	<a href="<?php echo esc_attr( $product->get_manufacturer_link( ) ); ?>" class="ec_details_manufacturer_item"><?php echo wp_easycart_language( )->convert_text( $product->manufacturer_name ); ?></a>
</div>
