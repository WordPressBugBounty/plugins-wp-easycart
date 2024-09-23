<?php
/**
 * WP EasyCart Product Details Images Widget Display for Elementor
 *
 * @package  Wp_Easycart_Elementor_Product_Details_Images_Widget
 * @author   WP EasyCart
 */

$args = shortcode_atts(
	array(
		'shortcode' => 'product_details_price',
		'use_post_id' => false,
		'product_id' => '',
		'show_price' => true,
		'show_list_price' => true,
	),
	$atts
);

$use_post_id = $args['use_post_id'];
$show_price = $args['show_price'];
$show_list_price = $args['show_list_price'];

$more_atts['product_id'] = (int) $args['product_id'];
$more_atts['use_post_id'] = ( 'yes' == $use_post_id ) ? 1 : 0;
$more_atts['show_price'] = ( 'yes' == $show_price ) ? 1 : 0;
$more_atts['show_list_price'] = ( 'yes' == $show_list_price ) ? 1 : 0;

$extra_atts = ' ';
foreach ( $more_atts as $key => $value ) {
	$extra_atts .= $key . '=' . json_encode( $value ) . ' ';
}

echo '<div class="wp-easycart-product-details-price-shortcode-wrapper d-flex">';
echo do_shortcode( '[ec_product_details_price ' . $extra_atts . ']' );
echo '</div>';
