<?php
/**
 * WP EasyCart Product Details Social Widget Display for Elementor
 *
 * @package  Wp_Easycart_Elementor_Product_Details_Social_Widget
 * @author   WP EasyCart
 */

$args = shortcode_atts(
	array(
		'shortcode' => 'product_details_social',
		'use_post_id' => false,
		'product_id' => '',
		'social_list' => array(),
	),
	$atts
);

$use_post_id = $args['use_post_id'];
$social_list = $args['social_list'];

$more_atts['product_id'] = (int) $args['product_id'];
$more_atts['use_post_id'] = ( 'yes' == $use_post_id ) ? 1 : 0;

$extra_atts = ' ';
foreach ( $more_atts as $key => $value ) {
	$extra_atts .= $key . '=' . json_encode( $value ) . ' ';
}

global $wpdb;
$mysqli = new ec_db();
if ( $more_atts['use_post_id'] ) {
	$products = $mysqli->get_product_list( $wpdb->prepare( ' WHERE product.activate_in_store = 1 AND product.post_id = %d', get_the_ID() ), "", "", "" );
} else {
	$products = $mysqli->get_product_list( $wpdb->prepare( ' WHERE product.activate_in_store = 1 AND product.product_id = %d', $more_atts['product_id'] ), "", "", "" );
}

if ( ! is_array( $products ) || ! count( $products ) ) {
	$products = $mysqli->get_product_list( ' WHERE product.activate_in_store = 1', "", "", "" );
}
$product = new ec_product( $products[0], 0, 1, 1 );

echo '<div class="wp-easycart-product-details-social-shortcode-wrapper d-flex">';
	echo '<div class="ec_details_social">';
	foreach ( $social_list as $item ) {
		$link = $item['social_link'];
		$link = str_replace( '{{prod_link}}', urlencode( $product->social_icons->get_product_url() ), $link );
		$link = str_replace( '{{prod_title}}', urlencode( $product->title ), $link );
		$link = str_replace( '{{prod_image}}', urlencode( $product->social_icons->get_image_url() ), $link );
		echo '<span class="ec_details_social_icon_ele elementor-repeater-item-' . esc_attr( $item['_id'] ) . '">';
			echo '<a href="' . esc_url_raw( $link ) . '" title="' . esc_attr( $item['social_title'] ) . '" target="_blank">';
			if ( isset( $item['social_icon'] ) && isset( $item['social_icon']['value'] ) ) {
				echo '<i class="' . esc_attr( $item['social_icon']['value'] ) . '" title="' . esc_attr( $item['social_title'] ) . '"></i>';
			} else {
				echo esc_attr( $item['social_title'] );
			}
			echo '</a>';
		echo '</span>';
	}
	echo '</div>';
echo '</div>';
