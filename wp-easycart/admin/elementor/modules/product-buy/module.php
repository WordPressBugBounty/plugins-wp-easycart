<?php
/**
 * Elementor module: product purchase widgets ( 6.0.2 ).
 *
 * Widgets ( panel category WP EasyCart · Product ):
 * - wp_easycart_add_to_cart   "Add to Cart"      options, quantity, add to cart / buy now, every product type;
 * - wp_easycart_product_price "Product Price"    sale and regular price, VAT split, quantity discounts, log in for price;
 * - wp_easycart_product_stock "Product Stock"    text or bar, low stock wording;
 * - wp_easycart_product_sku   "Product SKU";
 * - wp_easycart_product_gallery "Product Gallery" thumbnails, slider, zoom, lightbox;
 * - wp_easycart_product_badges  "Product Badges"  sale, new, low stock, sold out, the product's own tag.
 * They replace the legacy wp_easycart_product_addtocart, _details_price, _details_stock, _details_sku and _details_images
 * ( wp_easycart_elementor_retired_widgets, maps in includes/class-wp-easycart-product-buy-maps.php ).
 *
 * Storefront events ( assets/product-buy.js ), for the side cart and other listeners:
 * - 'wpeasycart_item_added' ( jQuery, triggered on the add to cart FORM, bubbles to document ): ( e, item, form ) after a
 *   background add; item is the add answer's item_added ( null when the store sent none ); e.target is the form, whose
 *   data-wpec-after-add says what the merchant chose ( stay | side_cart | cart ).
 * - 'wpeasycart_after_add' ( jQuery.Event on the form, cancelable ): ( e, { mode, item, cart, form } ); a listener that shows the
 *   cart itself calls e.preventDefault() and the widget skips its own "Added to your cart" message.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-wp-easycart-product-buy.php';
require_once __DIR__ . '/includes/class-wp-easycart-product-buy-maps.php';
require_once __DIR__ . '/includes/class-wp-easycart-product-buy-area.php';

if ( ! function_exists( 'wp_easycart_product_buy_widget_classes' ) ) {
	/**
	 * The module's widgets ( class name => file ), for wp_easycart_elementor_widget_classes.
	 *
	 * @since 6.0.2
	 *
	 * @param array $classes Widget classes.
	 * @return array
	 */
	function wp_easycart_product_buy_widget_classes( $classes ) {
		if ( ! is_array( $classes ) ) {
			$classes = array();
		}
		$dir     = __DIR__ . '/widgets/';
		$widgets = array(
			'WP_EasyCart_Elementor_Add_To_Cart_Widget'     => 'class-wp-easycart-elementor-add-to-cart-widget.php',
			'WP_EasyCart_Elementor_Product_Price_Widget'   => 'class-wp-easycart-elementor-product-price-widget.php',
			'WP_EasyCart_Elementor_Product_Stock_Widget'   => 'class-wp-easycart-elementor-product-stock-widget.php',
			'WP_EasyCart_Elementor_Product_Sku_Widget'     => 'class-wp-easycart-elementor-product-sku-widget.php',
			'WP_EasyCart_Elementor_Product_Gallery_Widget' => 'class-wp-easycart-elementor-product-gallery-widget.php',
			'WP_EasyCart_Elementor_Product_Badges_Widget'  => 'class-wp-easycart-elementor-product-badges-widget.php',
		);
		foreach ( $widgets as $class_name => $file ) {
			$classes[ $class_name ] = $dir . $file;
		}
		return $classes;
	}
}
add_filter( 'wp_easycart_elementor_widget_classes', 'wp_easycart_product_buy_widget_classes' );
add_filter( 'wp_easycart_elementor_retired_widgets', array( 'WP_EasyCart_Product_Buy_Maps', 'retired' ) );
add_action( 'wp_enqueue_scripts', array( 'WP_EasyCart_Product_Buy', 'register_assets' ), 5 );
add_action( 'elementor/frontend/after_register_scripts', array( 'WP_EasyCart_Product_Buy', 'register_assets' ) );
