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

/*
 * 6.0.2: the same product lookup as the other product widgets ( deactivated products for store managers, user roles, the
 * editor's sample product ). This widget had its own query, which fell back to the first product in the store on any page.
 */
$products = wp_easycart_get_shortcode_product_list( $more_atts['use_post_id'], 'NOPRODUCT', $more_atts['product_id'] );

echo '<div class="wp-easycart-product-details-social-shortcode-wrapper d-flex">';
if ( ! is_array( $products ) || ! count( $products ) ) {
	wp_easycart_print_product_not_found();
	echo '</div>';
	return;
}
$product = new ec_product( $products[0], 0, 1, 1 );
wp_easycart_product_details_schema( $product );

echo '<div class="ec_details_social">';
foreach ( $social_list as $item ) {
	$social_link = $item['social_link'];
	/* 6.0.2: {{prod_link}} is the product's own page ( it was the page showing the widget ). */
	$social_link = str_replace( '{{prod_link}}', urlencode( $product->get_product_link() ), $social_link );
	$social_link = str_replace( '{{prod_title}}', urlencode( $product->title ), $social_link );
	$social_link = str_replace( '{{prod_image}}', urlencode( $product->social_icons->get_image_url() ), $social_link );
	echo '<span class="ec_details_social_icon_ele elementor-repeater-item-' . esc_attr( $item['_id'] ) . '">';
	echo '<a href="' . esc_url( $social_link ) . '" title="' . esc_attr( $item['social_title'] ) . '" target="_blank">';
	if ( isset( $item['social_icon']['value'] ) && is_array( $item['social_icon']['value'] ) && class_exists( '\Elementor\Icons_Manager' ) ) {
		/* 6.0.2: an uploaded SVG icon ( its value is the file, not a class name ); font icons keep their <i> below. */
		\Elementor\Icons_Manager::render_icon( $item['social_icon'], array( 'aria-hidden' => 'true' ) );
	} elseif ( isset( $item['social_icon']['value'] ) && ! is_array( $item['social_icon']['value'] ) ) {
		echo '<i class="' . esc_attr( $item['social_icon']['value'] ) . '" title="' . esc_attr( $item['social_title'] ) . '"></i>';
	} else {
		echo esc_attr( $item['social_title'] );
	}
	echo '</a>';
	echo '</span>';
}
echo '</div>';
echo '</div>';
