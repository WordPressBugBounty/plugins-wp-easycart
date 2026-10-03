<?php
/**
 * The add to cart area of the Elementor Add to Cart widget ( 6.0.2 ).
 *
 * Included by ec_product_details_page_add_to_cart.php inside its form when the widget passes $wpec_el_buy ( $wpec_buy
 * there ), in place of the classic area. Hands the form's values to WP_EasyCart_Product_Buy_Area::render()
 * ( includes/class-wp-easycart-product-buy-area.php ).
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( isset( $product, $wpec_buy ) && is_object( $product ) && is_array( $wpec_buy ) && class_exists( 'WP_EasyCart_Product_Buy_Area' ) ) {
	WP_EasyCart_Product_Buy_Area::render(
		$product,
		isset( $wpeasycart_addtocart_shortcode_rand ) ? (int) $wpeasycart_addtocart_shortcode_rand : 0,
		$wpec_buy,
		array(
			'has_quantity_grid'   => ! empty( $has_quantity_grid ),
			'override_price_grid' => isset( $override_price_grid ) ? $override_price_grid : -1,
			'add_price_grid'      => isset( $add_price_grid ) ? $add_price_grid : 0,
			'selected_location'   => ( isset( $selected_location ) && is_object( $selected_location ) ) ? $selected_location : null,
		)
	);
}
