<?php
/**
 * WP EasyCart Product Details Specifications Widget Display for Elementor
 *
 * @package  Wp_Easycart_Elementor_Product_Details_Specifications_Widget
 * @author   WP EasyCart
 */

$args = shortcode_atts(
	array(
		'shortcode' => 'product_details_specifications',
		'use_post_id' => false,
		'product_id' => '',
	),
	$atts
);

$use_post_id = $args['use_post_id'];

$more_atts['product_id'] = (int) $args['product_id'];
$more_atts['use_post_id'] = ( 'yes' == $use_post_id ) ? 1 : 0;

$extra_atts = ' ';
foreach ( $more_atts as $key => $value ) {
	$extra_atts .= $key . '=' . json_encode( $value ) . ' ';
}

echo '<div class="wp-easycart-product-details-specifications-shortcode-wrapper d-flex">';
echo do_shortcode( '[ec_product_details_specifications ' . $extra_atts . ']' );
echo '</div>';
