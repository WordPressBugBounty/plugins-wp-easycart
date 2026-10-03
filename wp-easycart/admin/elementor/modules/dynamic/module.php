<?php
/**
 * WP EasyCart Elementor module: dynamic data ( 6.0.2 ).
 *
 * Store data anywhere in Elementor:
 * - dynamic tags ( product title, prices, stock, images, links, add to cart URL, cart and customer ) in the "WP EasyCart"
 *   group, usable in any widget, in Theme Builder templates, in Loop Grid items and in V4 atomic elements;
 * - Loop Grid / Loop Carousel Query IDs ( wpec_products, wpec_featured, ... ) that fill Elementor Pro's loops from
 *   EasyCart's own product queries;
 * - a "WP EasyCart" tab in Elementor's Site Settings that sets the shared --wpec-* style variables;
 * - an add to cart link for any Elementor button ( ?wpec_add_to_cart=ID ), and with Elementor Pro a form action that adds
 *   the visitor to the newsletter ( with consent ) and element display conditions ( cart, customer, product ).
 *
 * Loaded by WP_EasyCart_Elementor::load_modules() on plugins_loaded, with or without Elementor: every Elementor class is
 * only loaded inside the Elementor hook that needs it.
 *
 * Each part is loaded only when its file is there and its class answers init(), so a trimmed copy of the module never
 * breaks a site.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wp_easycart_elementor_dynamic_parts = array(
	'class-wp-easycart-elementor-dynamic.php'             => 'WP_EasyCart_Elementor_Dynamic',
	'class-wp-easycart-elementor-dynamic-loop.php'        => 'WP_EasyCart_Elementor_Dynamic_Loop',
	'class-wp-easycart-elementor-dynamic-add-to-cart.php' => 'WP_EasyCart_Elementor_Dynamic_Add_To_Cart',
	'class-wp-easycart-elementor-dynamic-settings.php'    => 'WP_EasyCart_Elementor_Dynamic_Settings',
);
foreach ( $wp_easycart_elementor_dynamic_parts as $wp_easycart_elementor_dynamic_file => $wp_easycart_elementor_dynamic_class ) {
	if ( is_readable( __DIR__ . '/' . $wp_easycart_elementor_dynamic_file ) ) {
		require_once __DIR__ . '/' . $wp_easycart_elementor_dynamic_file;
	}
	if ( class_exists( $wp_easycart_elementor_dynamic_class ) && method_exists( $wp_easycart_elementor_dynamic_class, 'init' ) ) {
		call_user_func( array( $wp_easycart_elementor_dynamic_class, 'init' ) );
	}
}
unset( $wp_easycart_elementor_dynamic_parts, $wp_easycart_elementor_dynamic_file, $wp_easycart_elementor_dynamic_class );
