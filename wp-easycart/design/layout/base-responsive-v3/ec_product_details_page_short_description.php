<div class="ec_details_description"><?php if ( isset( $product->short_description ) && '' != $product->short_description ) {
	echo wp_easycart_escape_html( nl2br( stripslashes( $product->short_description ) ) );
} ?></div>