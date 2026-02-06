<?php
/**
 * WP EasyCart Cart Icon Widget Display for Elementor
 *
 * @package Wp_Easycart_Elementor_Cart_Icon_Widget
 * @author WP EasyCart
 */

$args = shortcode_atts(
	array(
		'shortcode' => 'ec_cart_icon',
		'cart_icon' => array(
			'value' => 'fas fa-shopping-cart'
		),
		'show_quantity' => true,
		'cart_link' => '',
	),
	$atts
);

$shortcode = $args['shortcode'];
if ( isset( $args['cart_icon'] ) && isset( $args['cart_icon']['value'] ) ) {
	$cart_icon = $args['cart_icon']['value'];
}
$show_quantity = ( 'yes' == $args['show_quantity'] ) ? 1 : 0;
$cart_link = $args['cart_link'];
$cart_link_url = '#';
$cart_link_external = 0;
$cart_link_nofollow = 0;
if ( is_array( $cart_link ) ) {
	if ( isset( $cart_link['url'] ) && $cart_link['url'] ) {
		$cart_link_url = $cart_link['url'];
	}
	if ( isset( $cart_link['is_external'] ) && $cart_link['is_external'] ) {
		$cart_link_external = $cart_link['is_external'];
	}
	if ( isset( $cart_link['nofollow'] ) && $cart_link['nofollow'] ) {
		$cart_link_nofollow = $cart_link['nofollow'];
	}
}

$more_atts['is_elementor'] = 1;
$more_atts['cart_icon'] = $cart_icon;
$more_atts['show_quantity'] = $show_quantity;
$more_atts['cart_link_url'] = $cart_link_url;
$more_atts['cart_link_external'] = $cart_link_external;
$more_atts['cart_link_nofollow'] = $cart_link_nofollow;

$extra_atts = ' ';
foreach ( $more_atts as $key => $value ) {
	$extra_atts .= $key . '=' . json_encode( $value ) . ' ';
}

echo '<div class="wp-easycart-cart-icon-shortcode-wrapper d-flex">';
echo do_shortcode( '[ec_cart_icon ' . $extra_atts . ']' );
echo '</div>';
