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
	$product_id = $args['product_id'];
	$product_exists = $wpdb->get_row( $wpdb->prepare( 'SELECT product_id FROM ec_product WHERE product_id = %d', $product_id ) );
	if ( ! $product_exists ) {
		$product_id = 0;
	}
	$use_post_id = $args['use_post_id'];
	$enable_quantity = $args['enable_quantity'];
	$button_width = ( isset( $args['button_width'] ) && isset( $args['button_width']['size'] ) ) ? (int) $args['button_width']['size'] : 150;
	$button_font = $args['button_font'];
	$button_bg_color = $args['button_bg_color'];
	$button_text_color = $args['button_text_color'];
	$background_add = $args['background_add'];

	$fonts = array();

	$more_atts['is_elementor'] = 1;
	$more_atts['use_post_id'] = ( 'yes' == $use_post_id ) ? 1 : 0;
	$more_atts['productid'] = $product_id;
	$more_atts['enable_quantity'] = ( 'yes' == $enable_quantity ) ? 1 : 0;
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

	$extra_atts = ' ';
	foreach ( $more_atts as $key => $value ) {
		$extra_atts .= $key . '=' . json_encode( $value ) . ' ';
	}

	$extra_atts . "'";

	if ( count( $fonts ) > 0 ) {
		$gfont_string = 'https://fonts.googleapis.com/css?family=' . str_replace( ' ', '+', implode( '|', $fonts ) );
		echo '<link rel="stylesheet" href="' . esc_url( $gfont_string ) . '" />';
	}
	echo do_shortcode( '[ec_addtocart ' . $extra_atts . ']' );
	echo '</div>';
} else {
	$use_post_id = $args['use_post_id'];
	$product_id = $args['product_id_v2'];
	$enable_your_price = $args['enable_your_price'];
	$enable_quantity = $args['enable_quantity_v2'];
	$minus_icon = $args['ec_adtw_quantity_minus_button_icon'];
	$plus_icon = $args['ec_adtw_quantity_plus_button_icon'];

	$more_atts['use_post_id'] = ( 'yes' == $use_post_id ) ? 1 : 0;
	$more_atts['product_id'] = $product_id;
	$more_atts['enable_your_price'] = $enable_your_price;
	$more_atts['enable_quantity'] = ( 'yes' == $enable_quantity ) ? 1 : 0;
	$more_atts['minus_icon'] = $minus_icon['library'] . ' ' . $minus_icon['value'];
	$more_atts['plus_icon'] = $plus_icon['library'] . ' ' . $plus_icon['value'];
	
	echo '<div class="wp-easycart-product-details-shortcode-wrapper d-flex">';

	$extra_atts = ' ';
	foreach ( $more_atts as $key => $value ) {
		$extra_atts .= $key . '=' . json_encode( $value ) . ' ';
	}

	$extra_atts . "'";
	echo do_shortcode( '[ec_product_details_addtocart ' . $extra_atts . ']' );
	echo '</div>';
}
