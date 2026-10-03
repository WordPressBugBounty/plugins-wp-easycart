<?php
/**
 * WP EasyCart Elementor module: product information ( 6.0.2 ).
 *
 * Widgets ( panel category "WP EasyCart · Product" ): Product Title, Product Rating, Product Description, Product Short
 * Description, Product Specifications, Product Tabs, Product Reviews, Breadcrumbs, Product Meta, Related Products and Share
 * Buttons. They replace the older widgets of the same kind, which stay registered so saved pages keep drawing them, and leave
 * the panel through the filter wp_easycart_elementor_retired_widgets ( maps: WP_EasyCart_Product_Info_Maps ).
 *
 * - Helpers ( text, ratings, content, breadcrumbs, share links, related products, the reviews block ):
 *   includes/class-wp-easycart-product-info.php; the reviews markup: templates/reviews.php.
 * - Shopper text: language section elementor_product_info ( English fallbacks in code ).
 * - Assets: handle wp-easycart-el-product-info ( assets/product-info.css, assets/product-info.js ), registered on init and
 *   enqueued by Elementor only with these widgets.
 * - WP EasyCart Tabs 3 ( round 11 ): the Product Tabs widget's "Use the WP EasyCart Tabs design" hands its tabs to the
 *   extension through FREE's tab area functions ( wp_easycart_product_tabs_takeover() … wp_easycart_product_tabs_area() ), as
 *   the product templates do; the extension's stylesheets and script are in that widget's depends
 *   ( WP_EasyCart_Product_Info::tabs_design_available(), tabs_asset_handles() ).
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-wp-easycart-product-info.php';
require_once __DIR__ . '/includes/class-wp-easycart-product-info-maps.php';

WP_EasyCart_Product_Info::setup( __FILE__ );

add_action( 'init', array( 'WP_EasyCart_Product_Info', 'register_assets' ), 20 );
add_filter( 'wp_easycart_elementor_retired_widgets', array( 'WP_EasyCart_Product_Info_Maps', 'retire' ) );

if ( ! function_exists( 'wp_easycart_product_info_widget_classes' ) ) {
	/**
	 * The module's widgets for the Elementor integration ( class => file ).
	 *
	 * @since 6.0.2
	 *
	 * @param array $classes Widget classes from other modules.
	 * @return array
	 */
	function wp_easycart_product_info_widget_classes( $classes ) {
		$widgets = array(
			'title'             => 'Title',
			'rating'            => 'Rating',
			'description'       => 'Description',
			'short-description' => 'Short_Description',
			'specifications'    => 'Specifications',
			'tabs'              => 'Tabs',
			'reviews'           => 'Reviews',
			'breadcrumbs'       => 'Breadcrumbs',
			'meta'              => 'Meta',
			'related'           => 'Related',
			'share'             => 'Share',
		);
		$classes = is_array( $classes ) ? $classes : array();
		foreach ( $widgets as $slug => $name ) {
			$classes[ 'WP_EasyCart_Elementor_Product_Info_' . $name . '_Widget' ] = __DIR__ . '/widgets/class-wp-easycart-elementor-product-info-' . $slug . '-widget.php';
		}
		return $classes;
	}
}
add_filter( 'wp_easycart_elementor_widget_classes', 'wp_easycart_product_info_widget_classes' );
