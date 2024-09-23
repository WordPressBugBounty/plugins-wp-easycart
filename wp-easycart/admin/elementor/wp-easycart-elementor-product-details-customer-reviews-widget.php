<?php
/**
 * WP EasyCart Product Details Customer Reviews Widget Display for Elementor
 *
 * @package  Wp_Easycart_Elementor_Product_Details_Customer_Reviews_Widget
 * @author   WP EasyCart
 */

$args = shortcode_atts(
	array(
		'shortcode' => 'product_details_customer_reviews',
		'use_post_id' => false,
		'product_id' => '',
		'enable_review_list' => true,
		'enable_review_list_title' => true,
		'enable_review_item_title' => true,
		'enable_review_item_date' => true,
		'enable_review_item_user_name' => false,
		'enable_review_item_rating' => true,
		'enable_review_item_review' => true,
		'enable_review_form' => true,
		'enable_review_form_title' => true,
		'form_button_text' => wp_easycart_language( )->get_text( 'customer_review', 'product_details_your_review_submit' )
	),
	$atts
);

$use_post_id = $args['use_post_id'];
$enable_review_list = $args['enable_review_list'];
$enable_review_list_title = $args['enable_review_list_title'];
$enable_review_item_title = $args['enable_review_item_title'];
$enable_review_item_date = $args['enable_review_item_date'];
$enable_review_item_user_name = $args['enable_review_item_user_name'];
$enable_review_item_rating = $args['enable_review_item_rating'];
$enable_review_item_review = $args['enable_review_item_review'];
$enable_review_form = $args['enable_review_form'];
$enable_review_form_title = $args['enable_review_form_title'];
$form_button_text = $args['form_button_text'];

$more_atts['product_id'] = (int) $args['product_id'];
$more_atts['use_post_id'] = ( 'yes' == $use_post_id ) ? 1 : 0;
$more_atts['enable_review_list'] = ( 'yes' == $enable_review_list ) ? 1 : 0;
$more_atts['enable_review_list_title'] = ( 'yes' == $enable_review_list_title ) ? 1 : 0;
$more_atts['enable_review_item_title'] = ( 'yes' == $enable_review_item_title ) ? 1 : 0;
$more_atts['enable_review_item_date'] = ( 'yes' == $enable_review_item_date ) ? 1 : 0;
$more_atts['enable_review_item_user_name'] = ( 'yes' == $enable_review_item_user_name ) ? 1 : 0;
$more_atts['enable_review_item_rating'] = ( 'yes' == $enable_review_item_rating ) ? 1 : 0;
$more_atts['enable_review_item_review'] = ( 'yes' == $enable_review_item_review ) ? 1 : 0;
$more_atts['enable_review_form'] = ( 'yes' == $enable_review_form ) ? 1 : 0;
$more_atts['enable_review_form_title'] = ( 'yes' == $enable_review_form_title ) ? 1 : 0;
$more_atts['form_button_text'] = $form_button_text;

$extra_atts = ' ';
foreach ( $more_atts as $key => $value ) {
	$extra_atts .= $key . '=' . json_encode( $value ) . ' ';
}

echo '<div class="wp-easycart-product-details-customer-reviews-shortcode-wrapper d-flex">';
echo do_shortcode( '[ec_product_details_customer_reviews ' . $extra_atts . ']' );
echo '</div>';
