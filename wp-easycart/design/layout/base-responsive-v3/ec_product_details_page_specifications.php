<div class="ec_product_details_specifications">
	<?php if( isset( $product->specifications ) && '' != $product->specifications && substr( $product->specifications, 0, 3 ) == "[ec" ){
		$product->display_product_specifications( );
	}else{
		$content = ( isset( $product->specifications ) && '' != $product->specifications ) ? do_shortcode( stripslashes( $product->specifications ) ) : '';
		$content = stripslashes( str_replace( ']]>', ']]&gt;', $content ) );
		echo wp_easycart_escape_html( $content ); // XSS OK.
	} ?>
</div>