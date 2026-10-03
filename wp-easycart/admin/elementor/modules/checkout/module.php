<?php
/**
 * Elementor module: cart and checkout ( 6.0.2 ).
 *
 * Widgets ( panel category "WP EasyCart · Cart & Checkout" ): Cart ( wp_easycart_cart ), Menu Cart ( wp_easycart_menu_cart ),
 * Checkout ( wp_easycart_checkout ), Order Summary ( wp_easycart_order_summary ), Order Confirmation ( wp_easycart_thank_you )
 * and Side Cart ( wp_easycart_side_cart, WP EasyCart PRO: this plugin registers it locked ).
 *
 * The Cart, Checkout and Order Confirmation widgets draw EasyCart's own cart page ( load_ec_cart(): the classic or the
 * one-page checkout, every gateway, 3-D Secure and the returns from redirect gateways ) and style its markup; they never
 * copy it. See WP_EasyCart_Elementor_Checkout_Module for which of them draws the cart page on a given request.
 *
 * Loaded on plugins_loaded, with or without Elementor.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-wp-easycart-elementor-checkout-module.php';

WP_EasyCart_Elementor_Checkout_Module::init();
