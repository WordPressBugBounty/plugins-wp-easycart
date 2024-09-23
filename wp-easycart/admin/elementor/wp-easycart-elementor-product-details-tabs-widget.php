<?php
/**
 * WP EasyCart Product Details Tabs Widget Display for Elementor
 *
 * @package  Wp_Easycart_Elementor_Product_Details_Tabs_Widget
 * @author   WP EasyCart
 */

$args = shortcode_atts(
	array(
		'shortcode' => 'product_details_tabs',
		'use_post_id' => false,
		'product_id' => '',
		'description' => true,
		'specifications' => true,
		'customer_reviews' => true,
	),
	$atts
);

$use_post_id = $args['use_post_id'];
$description = $args['description'];
$specifications = $args['specifications'];
$customer_reviews = $args['customer_reviews'];

$more_atts['product_id'] = (int) $args['product_id'];
$more_atts['use_post_id'] = ( 'yes' == $use_post_id ) ? 1 : 0;
$more_atts['show_description'] = ( 'yes' == $description ) ? 1 : 0;
$more_atts['show_specifications'] = ( 'yes' == $specifications ) ? 1 : 0;
$more_atts['show_customer_reviews'] = ( 'yes' == $customer_reviews ) ? 1 : 0;

$extra_atts = ' ';
foreach ( $more_atts as $key => $value ) {
	$extra_atts .= $key . '=' . json_encode( $value ) . ' ';
}

echo '<div class="wp-easycart-product-details-tabs-shortcode-wrapper d-flex">';
echo do_shortcode( '[ec_product_details_tabs ' . $extra_atts . ']' );
echo '</div>';
