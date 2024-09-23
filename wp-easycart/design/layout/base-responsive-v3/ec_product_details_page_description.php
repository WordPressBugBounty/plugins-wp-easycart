<div class="ec_product_details_description">
<?php if ( '[ec' == substr( $product->description, 0, 3 ) ) {
	$product->display_product_description();
} else {
	$content = do_shortcode( stripslashes( $product->description ) );
	$content = str_replace( ']]>', ']]&gt;', $content );
	echo wp_easycart_escape_html( $content ); // XSS OK.
} ?>
</div>