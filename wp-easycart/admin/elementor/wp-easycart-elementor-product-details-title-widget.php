<?php
/**
 * WP EasyCart Product Details Title Widget Display for Elementor
 *
 * @package  Wp_Easycart_Elementor_Product_Details_Title_Widget
 * @author   WP EasyCart
 */

$args = shortcode_atts(
	array(
		'shortcode' => 'product_details_title',
		'use_post_id' => false,
		'product_id' => '',
		'title_element' => 'h1',
	),
	$atts
);

$use_post_id = $args['use_post_id'];
$title_element = $args['title_element'];

$more_atts['product_id'] = (int) $args['product_id'];
$more_atts['use_post_id'] = ( 'yes' == $use_post_id ) ? 1 : 0;
$more_atts['title_element'] = $title_element;

$extra_atts = ' ';
foreach ( $more_atts as $key => $value ) {
	$extra_atts .= $key . '=' . json_encode( $value ) . ' ';
}

echo '<div class="wp-easycart-product-details-title-shortcode-wrapper d-flex">';
echo do_shortcode( '[ec_product_details_title ' . $extra_atts . ']' );
echo '</div>';
