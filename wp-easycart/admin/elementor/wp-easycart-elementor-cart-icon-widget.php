<?php
/**
 * WP EasyCart Cart Icon Widget Display for Elementor
 *
 * @package Wp_Easycart_Elementor_Cart_Icon_Widget
 * @author WP EasyCart
 */

$args = shortcode_atts(
	array(
		'shortcode' => 'ec_cart_icon',
		'cart_icon' => array(
			'value' => 'fas fa-shopping-cart'
		),
		'show_quantity' => true,
		'cart_link' => '',
	),
	$atts
);

$shortcode = $args['shortcode'];
$cart_icon = '';
$cart_icon_setting = ( isset( $args['cart_icon'] ) && is_array( $args['cart_icon'] ) ) ? $args['cart_icon'] : array();
if ( isset( $cart_icon_setting['value'] ) && is_string( $cart_icon_setting['value'] ) ) {
	$cart_icon = $cart_icon_setting['value'];
}

/* 6.0.2: an uploaded SVG icon ( its value is an array, which used to break the shortcode ) and Elementor's Inline Font Icons
 * ( Font Awesome's stylesheet is then not loaded, so the `<i>` was blank ) are drawn by Elementor and handed to the
 * shortcode through the wp_easycart_cart_icon_html filter. A font icon with Inline Font Icons off keeps the shortcode's own
 * `<i class="…">`, unchanged. */
$cart_icon_markup = '';
if ( ! empty( $cart_icon_setting['value'] ) && ! empty( $cart_icon_setting['library'] ) && class_exists( '\Elementor\Icons_Manager' ) ) {
	/* 6.0.2: Elementor made Icons_Manager::is_font_icon_inline_svg() private ( method_exists() still says yes, so calling it
	 * stopped the page ). Ask it only while it can be called; otherwise read the Inline Font Icons experiment it reads. */
	$cart_icon_inline_svg = false;
	try {
		if ( is_callable( array( '\Elementor\Icons_Manager', 'is_font_icon_inline_svg' ) ) ) {
			$cart_icon_inline_svg = (bool) \Elementor\Icons_Manager::is_font_icon_inline_svg();
		} elseif ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->experiments ) && is_object( \Elementor\Plugin::$instance->experiments ) && method_exists( \Elementor\Plugin::$instance->experiments, 'is_feature_active' ) ) {
			$cart_icon_inline_svg = (bool) \Elementor\Plugin::$instance->experiments->is_feature_active( 'e_font_icon_svg' );
		}
	} catch ( Throwable $e ) {
		$cart_icon_inline_svg = false;
	}
	if ( is_array( $cart_icon_setting['value'] ) || 'svg' === $cart_icon_setting['library'] || $cart_icon_inline_svg ) {
		ob_start();
		\Elementor\Icons_Manager::render_icon( $cart_icon_setting, array( 'aria-hidden' => 'true' ) );
		$cart_icon_markup = trim( (string) ob_get_clean() );
		/* Size the SVG like the font icon it replaces ( 1em ), so Icon Size ( font-size ) still sets it, and colour it like text
		 * ( currentColor ) so it follows the link and Icon Color the way the font icon did. Paths with their own fill keep it. */
		if ( '' !== $cart_icon_markup && ! preg_match( '/<svg\b[^>]*\sstyle\s*=/i', $cart_icon_markup ) ) {
			$cart_icon_markup = preg_replace( '/<svg\b/i', '<svg style="width:1em;height:1em;fill:currentColor;"', $cart_icon_markup, 1 );
		}
	}
}
$show_quantity = ( 'yes' == $args['show_quantity'] ) ? 1 : 0;
$cart_link = $args['cart_link'];
$cart_link_url = '#';
$cart_link_external = 0;
$cart_link_nofollow = 0;
if ( is_array( $cart_link ) ) {
	if ( isset( $cart_link['url'] ) && $cart_link['url'] ) {
		$cart_link_url = $cart_link['url'];
	}
	if ( isset( $cart_link['is_external'] ) && $cart_link['is_external'] ) {
		$cart_link_external = $cart_link['is_external'];
	}
	if ( isset( $cart_link['nofollow'] ) && $cart_link['nofollow'] ) {
		$cart_link_nofollow = $cart_link['nofollow'];
	}
}

$more_atts['is_elementor'] = 1;
$more_atts['cart_icon'] = $cart_icon;
$more_atts['show_quantity'] = $show_quantity;
$more_atts['cart_link_url'] = $cart_link_url;
$more_atts['cart_link_external'] = $cart_link_external;
$more_atts['cart_link_nofollow'] = $cart_link_nofollow;

$extra_atts = wp_easycart_elementor_shortcode_atts( $more_atts );

$cart_icon_filter = null;
if ( '' !== $cart_icon_markup ) {
	$cart_icon_filter = function () use ( $cart_icon_markup ) {
		return $cart_icon_markup;
	};
	add_filter( 'wp_easycart_cart_icon_html', $cart_icon_filter, 20 );
}

echo '<div class="wp-easycart-cart-icon-shortcode-wrapper d-flex">';
echo wp_easycart_elementor_do_shortcode( '[ec_cart_icon ' . $extra_atts . ']' );
echo '</div>';

if ( null !== $cart_icon_filter ) {
	remove_filter( 'wp_easycart_cart_icon_html', $cart_icon_filter, 20 );
}
