<?php 
$vat_rate_multiplier = 1;
if ( $product->login_for_pricing && !$product->is_login_for_pricing_valid( ) ) {
	// No Pricing

}else if( ( $product->is_catalog_mode && get_option( 'ec_option_hide_price_seasonal' ) ) || 
		  ( $product->is_inquiry_mode && get_option( 'ec_option_hide_price_inquiry' ) ) ){ // NO PRICE SHOWN

}else if( $product->vat_rate > 0  && get_option( 'ec_option_show_multiple_vat_pricing' ) ){ 
$shipping_state = '';
	$shipping_country = '';
	if( isset( $GLOBALS['ec_cart_data']->shipping_state ) && $GLOBALS['ec_cart_data']->shipping_state != '' ){
		$shipping_state = $GLOBALS['ec_cart_data']->shipping_state;
	}else if( isset( $GLOBALS['ec_user']->shipping->state ) && $GLOBALS['ec_user']->shipping->state != '' ){
		$shipping_state = $GLOBALS['ec_user']->shipping->state;
	}
	if( isset( $GLOBALS['ec_cart_data']->cart_data->shipping_country ) && $GLOBALS['ec_cart_data']->cart_data->shipping_country != '' ){
		$shipping_country = $GLOBALS['ec_cart_data']->cart_data->shipping_country;
	}else if( isset( $GLOBALS['ec_user']->shipping->country ) && $GLOBALS['ec_user']->shipping->country != '' ){
		$shipping_country = $GLOBALS['ec_user']->shipping->country;
	}
	$vat_tax_class = new ec_tax( $product->price, $product->price, $product->price, $shipping_state, $shipping_country, false, 0, (object) array( 
		'cart' => array( 
			(object) array( 
				'product_id' => $product->product_id, 
				'total_price' => $product->price, 
				'manufacturer_id' => $product->manufacturer_id, 
				'is_taxable' => $product->is_taxable, 
				'vat_enabled' => $product->vat_rate 
			)
		)
	) );
	$vat_rate = apply_filters( 'wp_easycart_product_details_vat_rate', $vat_tax_class->vat_rate, $product );
	$vat_row = (object) array(
		'vat_rate'  => $vat_rate,
		'vat_added' => $vat_tax_class->vat_added,
		'vat_included' => $vat_tax_class->vat_included
	);
	$vat_rate_multiplier = ( $vat_rate / 100 ) + 1;

	?>
	<?php if ( get_option( 'ec_option_show_multiple_vat_pricing' ) == '1' ) { ?>
	<div class="ec_details_price ec_details_no_vat_price"><?php $product->display_product_pricing_no_vat( 
		( isset( $atts['price_font'] ) ) ? $atts['price_font'] : false,
		( isset( $atts['price_color'] ) ) ? $atts['price_color'] : false,
		( isset( $atts['list_price_font'] ) ) ? $atts['list_price_font'] : false,
		( isset( $atts['list_price_color'] ) ) ? $atts['list_price_color'] : false,
		$wpeasycart_addtocart_shortcode_rand,
		$atts['show_price'],
		$atts['show_list_price'],
		true
	); ?></div>
	<?php }?>
	<div class="ec_details_price ec_details_vat_price"><?php $product->display_product_pricing_vat( 
		( isset( $atts['price_font'] ) ) ? $atts['price_font'] : false,
		( isset( $atts['price_color'] ) ) ? $atts['price_color'] : false,
		( isset( $atts['list_price_font'] ) ) ? $atts['list_price_font'] : false,
		( isset( $atts['list_price_color'] ) ) ? $atts['list_price_color'] : false,
		$wpeasycart_addtocart_shortcode_rand,
		$atts['show_price'],
		$atts['show_list_price'],
		true
	); ?></div>

<?php } else { ?>
<div class="ec_details_price ec_details_single_price"><?php
	if ( $atts['show_list_price'] ) {
		$product->display_product_list_price( ( isset( $atts['list_price_font'] ) ) ? $atts['list_price_font'] : false, ( isset( $atts['list_price_color'] ) ) ? $atts['list_price_color'] : false, true );
	}
	if ( $atts['show_price'] ) {
		if ( $product->replace_price_label && in_array( $product->enable_price_label, array( 2, 4, 6, 7 ) ) ) { ?>
			<span class="ec_product_price_ele"><?php echo wp_easycart_escape_html( $product->custom_price_label ); ?></span>
		<?php } else {
			$product->display_price( ( isset( $atts['price_font'] ) ) ? $atts['price_font'] : false, ( isset( $atts['price_color'] ) ) ? $atts['price_color'] : false, $wpeasycart_addtocart_shortcode_rand, true );
		}
		if ( ! $product->replace_price_label && in_array( $product->enable_price_label, array( 2, 4, 6, 7 ) ) ) {
		?><span class="ec_details_price_label"><?php echo wp_easycart_escape_html( $product->custom_price_label ); ?></span><?php
		}
	} ?>
</div>
<?php } ?>

<?php if ( get_option( 'ec_option_show_promotion_discount_total' ) && $product->promotion_discount_total > 0 ) { ?>
	<div class="ec_details_price_promo_discount"><span class="dashicons dashicons-tag"></span><span class="ec_details_price_promo_discount_label"> <?php $product->display_promotion_text(); ?></span><span class="ec_details_price_promo_discount_minus"> -</span><span class="ec_details_price_promo_discount_total"><?php echo esc_attr( $GLOBALS['currency']->get_currency_display( $product->promotion_discount_total ) ); ?></span></div>
<?php }?>
