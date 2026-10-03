<?php
/**
 * WP EasyCart Product Add To Cart Widget Display for Elementor
 *
 * @package Wp_Easycart_Elementor_Product_Addtocart_Widget
 * @author WP EasyCart
 */

$args = shortcode_atts(
	array(
		'shortcode' => 'product_addtocart',
		'enable_v2' => false,
		'use_post_id' => false,
		'product_id_v2' => '',
		'product_id' => '',
		'enable_your_price' => true,
		'enable_quantity' => false,
		'enable_quantity_v2' => false,
		'button_width' => false,
		'button_font' => '',
		'button_bg_color' => '',
		'button_text_color' => '',
		'background_add' => '0',
		'ec_adtw_quantity_minus_button_icon' => '',
		'ec_adtw_quantity_plus_button_icon' => '',
	),
	$atts
);

global $wpdb;

$shortcode = $args['shortcode'];
$enable_v2 = ( 'yes' == $args['enable_v2'] ) ? 1 : 0;
$more_atts = array();

if ( ! $enable_v2 ) {
	/* 6.0.2: every value is cleaned before it reaches the shortcode ( a product that exists, as a number ). */
	$product_id = ( is_scalar( $args['product_id'] ) ) ? absint( $args['product_id'] ) : 0;
	$product_exists = ( $product_id > 0 ) ? $wpdb->get_row( $wpdb->prepare( 'SELECT product_id FROM ec_product WHERE product_id = %d', $product_id ) ) : false;
	if ( ! $product_exists ) {
		$product_id = 0;
	}
	$use_post_id = $args['use_post_id'];
	$enable_quantity = $args['enable_quantity'];
	$button_width = ( isset( $args['button_width'] ) && isset( $args['button_width']['size'] ) ) ? (int) $args['button_width']['size'] : 150;
	$button_font = ( is_scalar( $args['button_font'] ) ) ? sanitize_text_field( (string) $args['button_font'] ) : '';
	$button_bg_color = ( is_scalar( $args['button_bg_color'] ) ) ? preg_replace( '/[^A-Za-z0-9#(),.%\s\-]/', '', (string) $args['button_bg_color'] ) : '';
	$button_text_color = ( is_scalar( $args['button_text_color'] ) ) ? preg_replace( '/[^A-Za-z0-9#(),.%\s\-]/', '', (string) $args['button_text_color'] ) : '';
	/* Any stored choice that is not "No" ( '0' ) adds in the background, as before ( the old default 'featured' included ). */
	$background_add = ( ! empty( $args['background_add'] ) ) ? '1' : '0';

	$fonts = array();

	$more_atts['is_elementor'] = 1;
	$more_atts['use_post_id'] = ( 'yes' == $use_post_id ) ? 1 : 0;
	$more_atts['productid'] = $product_id;
	/* 6.0.2: Display Quantity hides the box only when it was switched off ( stored '' ); a widget that never touched it keeps
	 * the box it always showed ( the default was int 0 before 6.0.2, now 'yes' ). */
	$more_atts['enable_quantity'] = ( '' === $enable_quantity ) ? 0 : 1;
	$more_atts['button_width'] = $button_width;
	if ( '' != $button_font && '0' != $button_font ) {
		if ( ! in_array( $button_font, $fonts ) ) {
			$fonts[] = $button_font;
		}
		$more_atts['button_font'] = $button_font;
	}
	$more_atts['button_bg_color'] = $button_bg_color;
	$more_atts['button_text_color'] = $button_text_color;
	$more_atts['background_add'] = $background_add;

	echo '<div class="wp-easycart-product-details-shortcode-wrapper d-flex">';

	$extra_atts = wp_easycart_elementor_shortcode_atts( $more_atts );

	/* 6.0.2: EasyCart's own template never applies Button Font; only a custom ( wp-easycart-data ) template might, so only
	 * then is the font requested from Google. */
	$custom_template = file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_add_to_cart_shortcode.php' );
	if ( $custom_template && count( $fonts ) > 0 ) {
		$gfont_string = 'https://fonts.googleapis.com/css?family=' . str_replace( ' ', '+', implode( '|', $fonts ) );
		echo '<link rel="stylesheet" href="' . esc_url( $gfont_string ) . '" />';
	}
	echo wp_easycart_elementor_do_shortcode( '[ec_addtocart ' . $extra_atts . ']' );
	echo '</div>';
} else {
	$use_post_id = $args['use_post_id'];
	/* 6.0.2: every value is cleaned before it reaches the shortcode. */
	$product_id = ( is_scalar( $args['product_id_v2'] ) ) ? absint( $args['product_id_v2'] ) : 0;
	$enable_your_price = $args['enable_your_price'];
	$enable_quantity = $args['enable_quantity_v2'];
	$minus_icon = $args['ec_adtw_quantity_minus_button_icon'];
	$plus_icon = $args['ec_adtw_quantity_plus_button_icon'];

	/* 6.0.2: font icons keep their "library value" class list ( each class cleaned ). An uploaded SVG icon ( its value is an
	 * array ) is drawn by Elementor and handed to the template as markup, instead of printing the class "svg Array". */
	$icon_classes = array();
	$icon_html = array();
	foreach ( array( 'minus' => $minus_icon, 'plus' => $plus_icon ) as $icon_key => $icon ) {
		$icon_classes[ $icon_key ] = '';
		if ( ! is_array( $icon ) ) {
			continue;
		}
		$icon_value = ( isset( $icon['value'] ) ) ? $icon['value'] : '';
		if ( is_array( $icon_value ) ) {
			$icon_attachment = ( isset( $icon_value['id'] ) ) ? absint( $icon_value['id'] ) : 0;
			if ( $icon_attachment && 'image/svg+xml' === get_post_mime_type( $icon_attachment ) && class_exists( '\Elementor\Icons_Manager' ) && method_exists( '\Elementor\Icons_Manager', 'render_icon' ) ) {
				ob_start();
				\Elementor\Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) );
				$icon_html[ $icon_key ] = trim( ob_get_clean() );
			}
			continue;
		}
		$icon_library = ( isset( $icon['library'] ) && is_scalar( $icon['library'] ) ) ? (string) $icon['library'] : '';
		$icon_list = preg_split( '/\s+/', trim( $icon_library . ' ' . ( is_scalar( $icon_value ) ? (string) $icon_value : '' ) ) );
		$icon_classes[ $icon_key ] = implode( ' ', array_filter( array_map( 'sanitize_html_class', $icon_list ) ) );
	}

	$more_atts['use_post_id'] = ( 'yes' == $use_post_id ) ? 1 : 0;
	$more_atts['product_id'] = ( $product_id > 0 ) ? $product_id : '';
	$more_atts['enable_your_price'] = ( ! empty( $enable_your_price ) ) ? 'yes' : '';
	$more_atts['enable_quantity'] = ( 'yes' == $enable_quantity ) ? 1 : 0;
	$more_atts['minus_icon'] = $icon_classes['minus'];
	$more_atts['plus_icon'] = $icon_classes['plus'];

	echo '<div class="wp-easycart-product-details-shortcode-wrapper d-flex">';

	$extra_atts = wp_easycart_elementor_shortcode_atts( $more_atts );

	if ( count( $icon_html ) > 0 ) {
		/* Read ( and cleared ) by load_ec_product_details_addtocart() for this one render. */
		$GLOBALS['wp_easycart_addtocart_icon_html'] = $icon_html;
	}
	echo wp_easycart_elementor_do_shortcode( '[ec_product_details_addtocart ' . $extra_atts . ']' );
	unset( $GLOBALS['wp_easycart_addtocart_icon_html'] );
	echo '</div>';
}
