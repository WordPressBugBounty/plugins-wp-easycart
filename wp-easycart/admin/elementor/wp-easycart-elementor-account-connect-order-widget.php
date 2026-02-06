<?php
/**
 * WP EasyCart Account Connect Order Widget Display for Elementor
 *
 * @package  Wp_Easycart_Elementor_Account_Connect_Order_Widget
 * @author WP EasyCart
 */
$args = shortcode_atts(
	array(
		'connect_order_label' => esc_html__( 'Your Order Number', 'wp-easycart' ),
		'connect_order_error' => esc_html__( 'Your past order number is required', 'wp-easycart' ),
		'button_text_connect_order' => esc_html__( 'Find Your Order', 'wp-easycart' ),
		'label_type' => 'above',
	),
	$atts
);

echo '<form action="' . esc_url( wpeasycart_links()->get_account_page() ) . '" method="POST">';
	echo '<div class="wp-easycart-connect-order-wrapper">';
	if ( 'floating' == $args['label_type'] ) {
			echo '<div class="ec_cart_error_row" id="ec_account_connect_order_id_error">' . esc_html( $args['connect_order_error'] ) . '</div>';
		}
		echo '<div class="ec_cart_input_row">';
			if ( 'above' == $args['label_type'] ) {
				echo '<label for="ec_account_connect_order_id">' . esc_html( $args['connect_order_label'] ) . '</label>';
			}
			echo '<input type="text" name="ec_account_connect_order_id" id="ec_account_connect_order_id" class="ec_account_input_field" autocomplete="off" autocapitalize="off" placeholder="';
			if ( 'inside' == $args['label_type'] ) {
				echo esc_html( $args['connect_order_label'] );
			} else if ( 'floating' == $args['label_type'] ) {
				echo ' ';
			}
			echo '">';
			if ( 'floating' == $args['label_type'] ) {
				echo '<label for="ec_account_connect_order_id">' . esc_html( $args['connect_order_label'] ) . '</label>';
			}
			if ( 'floating' != $args['label_type'] ) {
				echo '<div class="ec_cart_error_row" id="ec_account_connect_order_id_error">' . esc_html( $args['connect_order_error'] ) . '</div>';
			}
		echo '</div>';

		echo '<div class="wp-easycart-button-row">';
			echo '<button type="submit" class="wp-easycart-button" onclick="return ec_account_connect_order_button_click();">' . esc_html( $args['button_text_connect_order'] ) . '</button>';
		echo '</div>';
	echo '</div>';
	echo '<input type="hidden" name="ec_account_page_id" id="ec_account_page_id" value="' . esc_attr( get_queried_object_id() ) . '" />';
	echo '<input type="hidden" name="ec_account_form_action" value="connect_order" />';
	echo '<input type="hidden" name="ec_account_form_nonce" value="' . esc_attr( wp_create_nonce( 'wp-easycart-account-connect-order' ) ) . '" />';
echo '</form>';
