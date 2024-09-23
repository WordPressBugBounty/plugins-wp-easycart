<?php
do_action( 'wp_easycart_product_details_before', $product );
if( trim( get_option( 'ec_option_fb_pixel' ) ) != '' ){
	echo "<script>
			fbq('track', 'ViewContent', {
				content_name: '" . esc_attr( ucwords( strtolower( strip_tags( $product->title ) ) ) ) . "',
				content_ids: ['" . esc_attr( $product->product_id ) . "'],
				content_type: 'product',";
	if ( ( ! $product->login_for_pricing || $product->is_login_for_pricing_valid() ) && ( ! $product->is_catalog_mode || ! get_option( 'ec_option_hide_price_seasonal' ) ) && ( ! $product->is_inquiry_mode || ! get_option( 'ec_option_hide_price_inquiry' ) ) ) {
	echo "
				value: " . esc_attr( number_format( $product->price, 2, '.', '' ) ) . ",
				currency: '" . esc_attr( $GLOBALS['currency']->get_currency_code( ) ) . "',";
	}
	echo "
			});
		</script>";
}
?>

<?php
/* If using Google Merchant Show Necessary META */
if( isset( $product->google_attributes ) && $product->google_attributes != NULL && $product->google_attributes != "" ){
	$google_attributes = json_decode( $product->google_attributes );
}else{
	$google_attributes = false;
}
?>
<script type="application/ld+json">
{
	"@context": "http://schema.org",
	"@type": "Product",
	"offers": {
		"@type": "Offer",
		"url": "<?php echo esc_js( $product->get_product_link( ) ); ?>",
		"availability": "<?php echo ( !$product->show_stock_quantity || $product->stock_quantity > 0 ) ? 'InStock' : 'OutOfStock'; ?>"<?php echo ( ! $product->show_stock_quantity || $product->stock_quantity > 0 ) ? 'InStock' : 'OutOfStock'; ?>"<?php if ( ( ! $product->login_for_pricing || $product->is_login_for_pricing_valid() ) && ( ! $product->is_catalog_mode || ! get_option( 'ec_option_hide_price_seasonal' ) ) && ( ! $product->is_inquiry_mode || ! get_option( 'ec_option_hide_price_inquiry' ) ) ) { ?>,
		"price": "<?php echo esc_js( number_format( $product->price, 2, '.', '' ) ); ?>",
		"priceValidUntil": "<?php echo esc_js( date( 'Y-m-d', strtotime( '+1 year' ) ) ); ?>",
		"priceCurrency": "<?php echo esc_js( $GLOBALS['currency']->get_currency_code( ) ); ?>"<?php }?><?php if( $google_attributes && isset( $google_attributes->condition ) ){ ?>,
		"itemCondition": "<?php if( 'new' == strtolower( $google_attributes->condition ) || '' == $google_attributes->condition ) { echo 'NewCondition'; }else if( 'used' == strtolower( $google_attributes->condition ) ){ echo 'UsedCondition'; }else{ echo 'RefurbishedCondition'; } ?>"<?php }?>
	},
	"brand": "<?php echo esc_js( $product->manufacturer_name ); ?>",
	"sku": "<?php echo esc_js( $product->model_number ); ?>",
	"name": "<?php echo esc_js( strip_tags( $product->title ) ); ?>",
	"description": "<?php echo  str_replace( "\'", "'", esc_js( ( isset( $product->short_description ) && strlen( $product->short_description ) > 0 ) ? str_replace( "\n", ' ', str_replace( "\r", ' ', $product->short_description ) ) : str_replace( "\n", ' ', str_replace( "\r", ' ', $product->description ) ) ) ); ?>"<?php if( $google_attributes && isset( $google_attributes->gtin ) && strlen( $google_attributes->gtin ) > 0 ){ ?>,
	"gtin": "<?php echo esc_js( $google_attributes->gtin ); ?>"<?php }else if( $google_attributes && isset( $google_attributes->mpn ) && strlen( $google_attributes->mpn ) > 0 ){ ?>,
	"mpn": "<?php echo esc_js( $google_attributes->mpn ); ?>"<?php }?>,
	"url": "<?php echo esc_js( $product->get_product_link( ) ); ?>",<?php if( $product->use_customer_reviews && count( $product->reviews ) > 0 ){ 
	$best_review = false;
	foreach( $product->reviews as $one_review ){
		if( !$best_review || $one_review->rating > $best_review->rating ){
			$best_review = $one_review;
		}
	}
	if( $best_review ){
		$best_review = new ec_review( $best_review );
	?>
	"review": {
		"@type": "Review",
		"reviewRating": {
			"@type": "Rating",
			"ratingValue": "<?php echo esc_js( $best_review->rating ); ?>"
		},
		"author":{
			"@type": "Person",
			"name": "<?php echo esc_js( $best_review->reviewer_name ); ?>"
		},
		"reviewBody": "<?php echo str_replace( "\'", "'", esc_js( str_replace( "\n", ' ', str_replace( "\r", ' ', $best_review->description ) ) ) ); ?>"
	},<?php }?>
	"aggregateRating": {
		"@type": "AggregateRating",
		"reviewCount": "<?php echo esc_js( count( $product->reviews ) ); ?>",
		"ratingValue": "<?php echo esc_js( $product->get_rating( ) ); ?>"
	},<?php }?>
	"image": {
		"@type": "ImageObject",
		"url": "<?php echo esc_js( $product->get_first_image_url( ) ); ?>",
		"image": "<?php echo esc_js( $product->get_first_image_url( ) ); ?>",
		"name": "<?php echo esc_js( strip_tags( stripslashes( $product->title ) ) ); ?>"
	}
}
</script>