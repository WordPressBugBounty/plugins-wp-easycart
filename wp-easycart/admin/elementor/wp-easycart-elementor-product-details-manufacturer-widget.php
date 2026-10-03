<?php
/**
 * WP EasyCart Product Details Manufacturer Widget Display for Elementor
 *
 * @package  Wp_Easycart_Elementor_Product_Details_Manufacturer_Widget
 * @author   WP EasyCart
 */

$args = shortcode_atts(
	array(
		'shortcode' => 'product_details_manufacturer',
		'use_post_id' => false,
		'product_id' => '',
		'label_text' => wp_easycart_language()->get_text( 'product_details', 'product_details_manufacturer' ),
	),
	$atts
);

$use_post_id = $args['use_post_id'];
$label_text = $args['label_text'];

$more_atts['product_id'] = (int) $args['product_id'];
$more_atts['use_post_id'] = ( 'yes' == $use_post_id ) ? 1 : 0;
$more_atts['label_text'] = $label_text;

$extra_atts = wp_easycart_elementor_shortcode_atts( $more_atts );

echo '<div class="wp-easycart-product-details-manufacturer-shortcode-wrapper d-flex">';
echo wp_easycart_elementor_do_shortcode( '[ec_product_details_manufacturer ' . $extra_atts . ']' );
echo '</div>';
