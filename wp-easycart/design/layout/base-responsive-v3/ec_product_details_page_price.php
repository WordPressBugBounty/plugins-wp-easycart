<?php
$vat_rate_multiplier = 1;
/* 6.0.2: the Offers price preview starts from the price the page shows ( with the options picked by default ) and gets the
 * list price, so an offer that leaves sale items alone previews nothing on a product on sale ( the cart gives nothing ).
 * data-wpec-offer-* carry the offer's rules, so the Elementor widgets ( product-buy.js ) work the offer price out again
 * when the shopper's options change the price. $wpec_price_prefix / $wpec_price_suffix: text the Product Price widget
 * prints around the price. */
$ec_offer_base = ( isset( $product->price_options ) && is_numeric( $product->price_options ) ) ? (float) $product->price_options : (float) $product->price;
$ec_offer_list = (float) $product->list_price;
$ec_offer_price_preview = false;
$ec_offer_rules = null;
$ec_offer_price_hidden = ( $product->login_for_pricing && ! $product->is_login_for_pricing_valid() ) || ( $product->is_catalog_mode && get_option( 'ec_option_hide_price_seasonal' ) ) || ( $product->is_inquiry_mode && get_option( 'ec_option_hide_price_inquiry' ) );
if ( $atts['show_price'] && ! $ec_offer_price_hidden && wp_easycart_offers_active() && $ec_offer_base > 0 && ! ( $product->replace_price_label && in_array( $product->enable_price_label, array( 2, 4, 6, 7 ) ) ) ) {
	$ec_offer_price_preview = ec_offer_display::get_product_price_preview( $product->product_id, $product->manufacturer_id, $ec_offer_base, $ec_offer_list );
	if ( false !== $ec_offer_price_preview && (float) $ec_offer_price_preview >= $ec_offer_base ) {
		$ec_offer_price_preview = false;
	}
	if ( false !== $ec_offer_price_preview && method_exists( 'ec_offer_display', 'get_product_price_rules' ) ) {
		$ec_offer_rules = array_values( (array) ec_offer_display::get_product_price_rules( $product->product_id, $product->manufacturer_id, $ec_offer_base, $ec_offer_list ) );
	}
}
$ec_offer_attrs = '';
if ( false !== $ec_offer_price_preview ) {
	$ec_offer_attrs = ' data-wpec-offer="1" data-wpec-offer-base="' . esc_attr( $ec_offer_base ) . '" data-wpec-list-price="' . esc_attr( $ec_offer_list ) . '"';
	if ( is_array( $ec_offer_rules ) ) {
		$ec_offer_attrs .= ' data-wpec-offer-rules="' . esc_attr( wp_json_encode( $ec_offer_rules ) ) . '"';
	}
}
$ec_price_prefix_html = ( isset( $wpec_price_prefix ) && '' !== (string) $wpec_price_prefix ) ? '<span class="wpec-price__prefix">' . esc_html( $wpec_price_prefix ) . '</span>' : '';
$ec_price_suffix_html = ( isset( $wpec_price_suffix ) && '' !== (string) $wpec_price_suffix ) ? '<span class="wpec-price__suffix">' . esc_html( $wpec_price_suffix ) . '</span>' : '';
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
	/* 6.0.2: with an Offers price preview the VAT prices show the regular price struck through ( the higher of the list price
	 * and the price ) and the offer price, each with and without VAT as the store shows them. */
	$ec_offer_saved = false;
	if ( false !== $ec_offer_price_preview ) {
		$ec_offer_saved = array( $product->price_options, $product->list_price );
		$product->list_price = max( $ec_offer_list, $ec_offer_base );
		$product->price_options = (float) $ec_offer_price_preview;
	}

	?>
	<?php if ( get_option( 'ec_option_show_multiple_vat_pricing' ) == '1' ) { ?>
	<?php /* 6.0.2: data-wpec-linked-* let the price follow the options chosen in this product's add to cart form ( ec-store.js ). */ ?>
	<div class="ec_details_price ec_details_no_vat_price<?php echo ( false !== $ec_offer_price_preview ) ? ' ec_details_price_has_offer' : ''; ?>" data-wpec-linked-product="<?php echo esc_attr( $product->product_id ); ?>" data-wpec-linked-rand="<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" data-wpec-vat-added="<?php echo ( $vat_row->vat_added ) ? '1' : '0'; ?>" data-wpec-vat-multiplier="<?php echo esc_attr( $vat_rate_multiplier ); ?>"<?php echo $ec_offer_attrs; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped where built above. */ ?>><?php echo $ec_price_prefix_html; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped where built above. */ ?><?php $product->display_product_pricing_no_vat(
		( isset( $atts['price_font'] ) ) ? $atts['price_font'] : false,
		( isset( $atts['price_color'] ) ) ? $atts['price_color'] : false,
		( isset( $atts['list_price_font'] ) ) ? $atts['list_price_font'] : false,
		( isset( $atts['list_price_color'] ) ) ? $atts['list_price_color'] : false,
		$wpeasycart_addtocart_shortcode_rand,
		$atts['show_price'],
		$atts['show_list_price'],
		true
	); ?><?php echo $ec_price_suffix_html; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped where built above. */ ?></div>
	<?php }?>
	<div class="ec_details_price ec_details_vat_price<?php echo ( false !== $ec_offer_price_preview ) ? ' ec_details_price_has_offer' : ''; ?>" data-wpec-linked-product="<?php echo esc_attr( $product->product_id ); ?>" data-wpec-linked-rand="<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" data-wpec-vat-added="<?php echo ( $vat_row->vat_added ) ? '1' : '0'; ?>" data-wpec-vat-multiplier="<?php echo esc_attr( $vat_rate_multiplier ); ?>"<?php echo $ec_offer_attrs; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped where built above. */ ?>><?php echo $ec_price_prefix_html; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped where built above. */ ?><?php $product->display_product_pricing_vat(
		( isset( $atts['price_font'] ) ) ? $atts['price_font'] : false,
		( isset( $atts['price_color'] ) ) ? $atts['price_color'] : false,
		( isset( $atts['list_price_font'] ) ) ? $atts['list_price_font'] : false,
		( isset( $atts['list_price_color'] ) ) ? $atts['list_price_color'] : false,
		$wpeasycart_addtocart_shortcode_rand,
		$atts['show_price'],
		$atts['show_list_price'],
		true
	); ?><?php echo $ec_price_suffix_html; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped where built above. */ ?></div>
	<?php
	if ( $ec_offer_saved ) {
		$product->price_options = $ec_offer_saved[0];
		$product->list_price = $ec_offer_saved[1];
	}
	?>

<?php } else { ?>
<?php /* 6.0.2: data-wpec-linked-* let the price follow the options chosen in this product's add to cart form ( ec-store.js ). */ ?>
<div class="ec_details_price ec_details_single_price<?php echo ( false !== $ec_offer_price_preview ) ? ' ec_details_price_has_offer' : ''; ?>" data-wpec-linked-product="<?php echo esc_attr( $product->product_id ); ?>" data-wpec-linked-rand="<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"<?php echo $ec_offer_attrs; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped where built above. */ ?>><?php
	echo $ec_price_prefix_html; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped where built above. */
	if ( $atts['show_list_price'] ) {
		$product->display_product_list_price( ( isset( $atts['list_price_font'] ) ) ? $atts['list_price_font'] : false, ( isset( $atts['list_price_color'] ) ) ? $atts['list_price_color'] : false, true );
	}
	if ( $atts['show_price'] ) {
		if ( $product->replace_price_label && in_array( $product->enable_price_label, array( 2, 4, 6, 7 ) ) ) { ?>
			<span class="ec_product_price_ele"><?php echo wp_easycart_escape_html( $product->custom_price_label ); ?></span>
		<?php } else if ( false !== $ec_offer_price_preview ) { ?>
			<span class="ec_offer_price_strike"><?php echo esc_attr( $GLOBALS['currency']->get_currency_display( $ec_offer_base ) ); ?></span>
			<span class="ec_offer_price_preview"><?php echo esc_attr( $GLOBALS['currency']->get_currency_display( $ec_offer_price_preview ) ); ?></span>
		<?php } else {
			$product->display_price( ( isset( $atts['price_font'] ) ) ? $atts['price_font'] : false, ( isset( $atts['price_color'] ) ) ? $atts['price_color'] : false, $wpeasycart_addtocart_shortcode_rand, true );
		}
		if ( ! $product->replace_price_label && in_array( $product->enable_price_label, array( 2, 4, 6, 7 ) ) ) {
		?><span class="ec_details_price_label"><?php echo wp_easycart_escape_html( $product->custom_price_label ); ?></span><?php
		}
	}
	echo $ec_price_suffix_html; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped where built above. */ ?>
</div>
<?php } ?>

<?php if ( get_option( 'ec_option_show_promotion_discount_total' ) && $product->promotion_discount_total > 0 ) { ?>
	<div class="ec_details_price_promo_discount"><span class="dashicons dashicons-tag"></span><span class="ec_details_price_promo_discount_label"> <?php $product->display_promotion_text(); ?></span><span class="ec_details_price_promo_discount_minus"> -</span><span class="ec_details_price_promo_discount_total"><?php echo esc_attr( $GLOBALS['currency']->get_currency_display( $product->promotion_discount_total ) ); ?></span></div>
<?php }?>

<?php wp_easycart_offers_template( 'ec_offer_product_callout.php', array( 'callout_product_id' => $product->product_id, 'callout_manufacturer_id' => $product->manufacturer_id, 'callout_price' => $ec_offer_base, 'callout_list_price' => $ec_offer_list ) ); ?>
