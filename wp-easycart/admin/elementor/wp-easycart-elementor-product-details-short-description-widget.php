<?php
/**
 * WP EasyCart Product Details Short Description Widget Display for Elementor
 *
 * @package  Wp_Easycart_Elementor_Product_Details_Short Description_Widget
 * @author   WP EasyCart
 */

$args = shortcode_atts(
	array(
		'shortcode' => 'product_details_short_description',
		'use_post_id' => false,
		'product_id' => '',
	),
	$atts
);

$use_post_id = $args['use_post_id'];

$more_atts['product_id'] = (int) $args['product_id'];
$more_atts['use_post_id'] = ( 'yes' == $use_post_id ) ? 1 : 0;

$extra_atts = wp_easycart_elementor_shortcode_atts( $more_atts );

echo '<div class="wp-easycart-product-details-short-description-shortcode-wrapper d-flex">';
echo wp_easycart_elementor_do_shortcode( '[ec_product_details_short_description ' . $extra_atts . ']' );
echo '</div>';
