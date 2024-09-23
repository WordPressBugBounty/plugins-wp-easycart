<?php
/**
 * WP EasyCart Product Details Breadcrumbs Widget Display for Elementor
 *
 * @package  Wp_Easycart_Elementor_Product_Details_Breadcrumbs_Widget
 * @author   WP EasyCart
 */

$args = shortcode_atts(
	array(
		'shortcode' => 'product_details_breadcrumbs',
		'use_post_id' => false,
		'product_id' => '',
		'breadcrumb_element' => 'div',
		'divider_character' => '/',
	),
	$atts
);

$use_post_id = $args['use_post_id'];
$breadcrumb_element = $args['breadcrumb_element'];
$divider_character = $args['divider_character'];

$more_atts['product_id'] = (int) $args['product_id'];
$more_atts['use_post_id'] = ( 'yes' == $use_post_id ) ? 1 : 0;
$more_atts['breadcrumb_element'] = $breadcrumb_element;
$more_atts['divider_character'] = $divider_character;

$extra_atts = ' ';
foreach ( $more_atts as $key => $value ) {
	$extra_atts .= $key . '=' . json_encode( $value ) . ' ';
}

echo '<div class="wp-easycart-product-details-breadcrumbs-shortcode-wrapper d-flex">';
echo do_shortcode( '[ec_product_details_breadcrumbs ' . $extra_atts . ']' );
echo '</div>';
