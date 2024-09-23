<?php
/**
 * WP EasyCart Product Details Featured Products Widget Display for Elementor
 *
 * @package  Wp_Easycart_Elementor_Product_Details_Featured_Products_Widget
 * @author   WP EasyCart
 */

$args = shortcode_atts(
	array(
		'shortcode' => 'product_details_featured_products',
		'use_post_id' => false,
		'product_id' => '',
		'enable_product1' => true,
		'enable_product2' => true,
		'enable_product3' => true,
		'enable_product4' => true,
		'visible_options' => array(
			'title',
			'price',
			'rating',
			'cart',
			'quickview',
			'desc',
		),
	),
	$atts
);

$use_post_id = $args['use_post_id'];
$enable_product1 = $args['enable_product1'];
$enable_product2 = $args['enable_product2'];
$enable_product3 = $args['enable_product3'];
$enable_product4 = $args['enable_product4'];
$visible_options = $args['visible_options'];

if ( is_string( $visible_options ) ) {
	$visible_options = explode( ',', $visible_options );
}

$more_atts['product_id'] = (int) $args['product_id'];
$more_atts['use_post_id'] = ( 'yes' == $use_post_id ) ? 1 : 0;
$more_atts['enable_product1'] = ( 'yes' == $enable_product1 ) ? 1 : 0;
$more_atts['enable_product2'] = ( 'yes' == $enable_product2 ) ? 1 : 0;
$more_atts['enable_product3'] = ( 'yes' == $enable_product3 ) ? 1 : 0;
$more_atts['enable_product4'] = ( 'yes' == $enable_product4 ) ? 1 : 0;
$more_atts['product_visible_options'] = ( is_array( $visible_options ) ) ? implode( ',', $visible_options ) : '';

$extra_atts = ' ';
foreach ( $more_atts as $key => $value ) {
	$extra_atts .= $key . '=' . json_encode( $value ) . ' ';
}

echo '<div class="wp-easycart-product-details-featured-products-shortcode-wrapper d-flex">';
echo do_shortcode( '[ec_product_details_featured_products ' . $extra_atts . ']' );
echo '</div>';
