<?php
/**
 * WP EasyCart Elementor module: product and category templates ( 6.0.2 ).
 *
 * - "EasyCart Product" and "EasyCart Category" template types in Elementor's library ( free Elementor ), designed with a
 *   preview product / category.
 * - Settings › Elementor › Product pages / Category pages: the template every product / category page uses, drawn inside
 *   WP EasyCart's own page ( ec_storepage ), so the 404 and restricted-store rules, analytics, structured data and hooks stay.
 * - Per-product / per-category layouts ( WP EasyCart PRO ) through filter wp_easycart_elementor_template_for.
 * - Elementor Pro Theme Builder: a "WP EasyCart" condition group and a "Single Product ( WP EasyCart )" template type.
 * - Category and manufacturer widgets ( title, description, image, subcategories, manufacturer title, description, logo ).
 *
 * Loaded on plugins_loaded on every request ( Elementor or not ) by WP_EasyCart_Elementor::load_modules().
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-wp-easycart-elementor-templates.php';

WP_EasyCart_Elementor_Templates::boot();

if ( is_admin() ) {
	require_once __DIR__ . '/class-wp-easycart-elementor-templates-settings.php';
	WP_EasyCart_Elementor_Templates_Settings::boot();
}
