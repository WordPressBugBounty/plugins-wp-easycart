<?php
/**
 * Settings › Shipping rates ( V2 declaration ).
 *
 * Replaces the classic "shipping-rates" subpage ( shipping-rates.php with the
 * static-rates, price-based, weight-based, quantity-based and percentage-rates
 * partials, plus the PRO live-rates partial hooked to
 * 'wpeasycart_admin_shipping_rates' ). The method chooser that headed the classic
 * page is declared on Shipping settings ( ec_option_shipping_method ); this page
 * shows which method is on, offers a compact switcher that saves that same field
 * through ecv2_settings_save ( page 'shipping-settings' ), and edits the rate tables.
 *
 * Nothing here is a wp_option: every table is rows of ec_shippingrate, edited in
 * place through the ecv2_shipping_rate_* handlers in
 * admin/inc/wp_easycart_admin_shipping_rates_v2.php, which also holds the render
 * callables. The PRO live-rate list is declared locked here and rendered by
 * wp-easycart-pro/admin/template/settings/shipping-rates.php through the page filter.
 *
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_shipping_rates_v2' ) && defined( 'EC_PLUGIN_DIRECTORY' ) && file_exists( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_shipping_rates_v2.php' ) ) {
	require_once EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_shipping_rates_v2.php';
}

$ecv2_shipping_rates_sections = array(
	'active' => array(
		'title'  => __( 'Active method', 'wp-easycart' ),
		'hint'   => __( 'How shipping is charged; only this method’s table is used at checkout', 'wp-easycart' ),
		'icon'   => 'truck',
		'fields' => array(),
		'render' => array( 'wp_easycart_admin_shipping_rates_v2', 'render_active' ),
	),
);

if ( class_exists( 'wp_easycart_admin_shipping_rates_v2' ) ) {
	foreach ( wp_easycart_admin_shipping_rates_v2::types() as $ecv2_shipping_rates_type => $ecv2_shipping_rates_def ) {
		$ecv2_shipping_rates_sections[ $ecv2_shipping_rates_type ] = array(
			'title'  => $ecv2_shipping_rates_def['title'],
			'hint'   => $ecv2_shipping_rates_def['hint'],
			'icon'   => $ecv2_shipping_rates_def['icon'],
			'fields' => array(),
			'render' => array( 'wp_easycart_admin_shipping_rates_v2', 'render_section' ),
		);
	}
}

$ecv2_shipping_rates_sections['live'] = array(
	'title'  => __( 'Live carrier rates', 'wp-easycart' ),
	'hint'   => __( 'The carrier services offered when the method is live rates, in the order shoppers see them', 'wp-easycart' ),
	'icon'   => 'zap',
	'pro'    => true,
	'fields' => array(
		'ecv2_shipping_rates_live_note' => array(
			'type'   => 'html',
			'render' => array( 'wp_easycart_admin_shipping_rates_v2', 'render_live_note' ),
		),
	),
	'render' => null, // PRO attaches the live-rate list here via the page filter.
);

return array(
	'slug'        => 'shipping-rates',
	'title'       => __( 'Shipping rates', 'wp-easycart' ),
	'description' => __( 'The rate table behind your shipping method: what each option costs and where it applies.', 'wp-easycart' ),
	'group'       => 'financial',
	'icon'        => 'list',
	'docs'        => array( 'settings', 'shipping-rates', 'shipping-method' ),
	'legacy'      => array( 'shipping-rates' ),
	'upsell'      => 'default',
	'enqueue'     => array( 'wp_easycart_admin_shipping_rates_v2', 'enqueue' ),
	'sections'    => $ecv2_shipping_rates_sections,
);
