<?php
/**
 * WP EasyCart Product Details Images Widget Display for Elementor
 *
 * @package  Wp_Easycart_Elementor_Product_Details_Images_Widget
 * @author   WP EasyCart
 */

$args = shortcode_atts(
	array(
		'_id' => '_id',
		'shortcode' => 'product_details_images',
		'use_post_id' => false,
		'product_id' => '',
		'lightbox' => true,
		'thumbnails' => true,
		'image_hover' => true,
		'thumbnails_position' => 'bottom',
		'thumbnails_stack' => 'row',
		'ec_imw_image_size_size' => 'small',
		'ec_imw_image_size_custom_dimension' => false,
		'ec_imw_thumb_size_size' => 'small',
		'ec_imw_thumb_size_custom_dimension' => false,
	),
	$atts
);

$use_post_id = $args['use_post_id'];
$image_hover = $args['image_hover'];
$lightbox = $args['lightbox'];
$thumbnails = $args['thumbnails'];

$more_atts['product_id'] = (int) $args['product_id'];
$more_atts['use_post_id'] = ( 'yes' == $use_post_id ) ? 1 : 0;
$more_atts['show_image_hover'] = ( 'yes' == $image_hover ) ? 1 : 0;
$more_atts['show_lightbox'] = ( 'yes' == $lightbox ) ? 1 : 0;
$more_atts['show_thumbnails'] = ( 'yes' == $thumbnails ) ? 1 : 0;
$more_atts['thumbnails_position'] = $args['thumbnails_position'];
$more_atts['thumbnails_stack'] = $args['thumbnails_stack'];

/*
 * 6.0.2: "Custom" passes its width x height, and the shortcode asks WordPress for the closest size it already made. This
 * registered a size while the page rendered ( which makes no files ) and stored it in a misspelt variable, so the
 * full-size upload loaded.
 */
$more_atts['image_size'] = $args['ec_imw_image_size_size'];
if ( 'custom' === $args['ec_imw_image_size_size'] && is_array( $args['ec_imw_image_size_custom_dimension'] ) ) {
	$more_atts['image_custom_size'] = absint( isset( $args['ec_imw_image_size_custom_dimension']['width'] ) ? $args['ec_imw_image_size_custom_dimension']['width'] : 0 ) . 'x' . absint( isset( $args['ec_imw_image_size_custom_dimension']['height'] ) ? $args['ec_imw_image_size_custom_dimension']['height'] : 0 );
}
$more_atts['thumb_size'] = $args['ec_imw_thumb_size_size'];
if ( 'custom' === $args['ec_imw_thumb_size_size'] && is_array( $args['ec_imw_thumb_size_custom_dimension'] ) ) {
	$more_atts['thumb_custom_size'] = absint( isset( $args['ec_imw_thumb_size_custom_dimension']['width'] ) ? $args['ec_imw_thumb_size_custom_dimension']['width'] : 0 ) . 'x' . absint( isset( $args['ec_imw_thumb_size_custom_dimension']['height'] ) ? $args['ec_imw_thumb_size_custom_dimension']['height'] : 0 );
}

$extra_atts = wp_easycart_elementor_shortcode_atts( $more_atts );

echo '<div class="wp-easycart-product-details-images-shortcode-wrapper d-flex">';
echo wp_easycart_elementor_do_shortcode( '[ec_product_details_images ' . $extra_atts . ']' );
echo '</div>';
