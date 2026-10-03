<?php
/**
 * WP EasyCart Elementor module: Shop ( 6.0.2 ).
 *
 * Products ( grid or list ), Product Carousel, Product Categories, Shop ( the store archive ) and Product Search, the
 * product card they share, their storefront endpoints and the maps that retire the Store, Products and Search widgets
 * from before 6.0.2.
 *
 * Loaded on plugins_loaded by WP_EasyCart_Elementor::load_modules(), with or without Elementor: the storefront endpoints
 * and the retired-widget maps exist on every request. The widget classes load only when Elementor registers widgets.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-wp-easycart-elementor-shop.php';
require_once __DIR__ . '/includes/class-wp-easycart-elementor-shop-query.php';
require_once __DIR__ . '/includes/class-wp-easycart-elementor-shop-card.php';
require_once __DIR__ . '/includes/class-wp-easycart-elementor-shop-ajax.php';
require_once __DIR__ . '/includes/class-wp-easycart-elementor-shop-retire.php';

WP_EasyCart_Elementor_Shop::init();
