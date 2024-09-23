<?php
/**
 * WP EasyCart Product Details Category Widget Display for Elementor
 *
 * @package  Wp_Easycart_Elementor_Product_Details_Category_Widget
 * @author   WP EasyCart
 */

$args = shortcode_atts(
	array(
		'shortcode' => 'product_details_category',
		'use_post_id' => false,
		'product_id' => '',
		'categories_element' => 'div',
		'categories_label' => wp_easycart_language( )->get_text( 'product_details', 'product_details_categories' ),
		'categories_divider' => ',',
	),
	$atts
);

$use_post_id = $args['use_post_id'];
$categories_element = $args['categories_element'];
$categories_label = $args['categories_label'];
$categories_divider = $args['categories_divider'];

$more_atts['product_id'] = (int) $args['product_id'];
$more_atts['use_post_id'] = ( 'yes' == $use_post_id ) ? 1 : 0;
$more_atts['product_id'] = $categories_element;
$more_atts['categories_label'] = $categories_label;
$more_atts['categories_divider'] = $categories_divider;

$extra_atts = ' ';
foreach ( $more_atts as $key => $value ) {
	$extra_atts .= $key . '=' . json_encode( $value ) . ' ';
}

echo '<div class="wp-easycart-product-details-category-shortcode-wrapper d-flex">';
echo do_shortcode( '[ec_product_details_category ' . $extra_atts . ']' );
echo '</div>';
