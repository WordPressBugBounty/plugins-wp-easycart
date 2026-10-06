<?php
/**
 * WP EasyCart Elementor module: subscriptions ( 6.0.3 ).
 *
 * The Pricing Table widget ( wp_easycart_plan_table ): a WP EasyCart PRO plan group's pricing table, the same as the
 * [ec_plan_table] shortcode and the block. WP EasyCart PRO draws it ( filter wp_easycart_plan_table_html ) and turns the widget on
 * through wp_easycart_elementor_pro_features ( 'plan_tables', PRO 6.0.3 ); without it the editor shows the locked note and
 * visitors see nothing.
 *
 * Loaded on plugins_loaded by WP_EasyCart_Elementor::load_modules(); the widget class loads only when Elementor registers widgets.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wp_easycart_elementor_subscription_widgets' ) ) {
	/**
	 * Filter wp_easycart_elementor_widget_classes: the subscription widgets.
	 *
	 * @param array $classes Class name => absolute file.
	 * @return array
	 */
	function wp_easycart_elementor_subscription_widgets( $classes ) {
		$classes = is_array( $classes ) ? $classes : array();
		$classes['WP_EasyCart_Elementor_Plan_Table_Widget'] = __DIR__ . '/widgets/class-wp-easycart-elementor-plan-table-widget.php';
		return $classes;
	}
	add_filter( 'wp_easycart_elementor_widget_classes', 'wp_easycart_elementor_subscription_widgets' );
}
