<?php
/**
 * WP EasyCart Elementor module: starter layouts ( 6.0.2 ).
 *
 * - A new EasyCart Product template starts as a finished product page ( gallery beside title, price, add to cart …, tabs
 *   and related products below ), through the templates module's filter wp_easycart_elementor_starter_template.
 * - Settings › Elementor › Starter pages adds five ready layouts to Elementor › Templates › My Templates: Shop page, Cart
 *   page, Checkout page, My Account page and Order confirmation page.
 *
 * Loaded on plugins_loaded by WP_EasyCart_Elementor::load_modules(), with or without Elementor; nothing here needs
 * Elementor until a template is created.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-wp-easycart-elementor-starter-layouts.php';

WP_EasyCart_Elementor_Starter_Layouts::boot();

if ( is_admin() ) {
	require_once __DIR__ . '/includes/wp-easycart-elementor-starter-functions.php';
	require_once __DIR__ . '/includes/class-wp-easycart-elementor-starter-pages.php';
	WP_EasyCart_Elementor_Starter_Pages::boot();
}
