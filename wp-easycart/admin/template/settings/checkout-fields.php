<?php
/**
 * Settings › Checkout fields ( V2 declaration ).
 *
 * The store's own questions at checkout ( 6.0.2 ): which fields, where each is asked in the classic and the one-page
 * checkout, when it applies, and where its answers show. The whole page is WP EasyCart PRO 6.0.2: without it the section
 * shows a locked example of the builder ( wp_easycart_admin_checkout_fields::render_locked() ) with the upgrade popup, or
 * the update wording when WP EasyCart PRO is older. PRO swaps in the builder through the
 * wp_easycart_settings_page_checkout-fields filter ( wp-easycart-pro/admin/inc/wp_easycart_admin_checkout_fields_pro.php ).
 *
 * The answers themselves are kept on orders in ec_order_field and shown by WP EasyCart ( wp_easycart_order_fields ), so
 * they stay visible without PRO.
 *
 * @since 6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'slug'        => 'checkout-fields',
	'title'       => __( 'Checkout fields', 'wp-easycart' ),
	'description' => __( 'Your own questions at checkout, when they are asked and where the answers show.', 'wp-easycart' ),
	'group'       => 'cart-checkout',
	'order'       => 20,
	'plan'        => 'pro', /* 6.0.2: a Pro page ( lock in the sidebar ) */
	'icon'        => 'feedback',
	'docs'        => array( 'settings', 'checkout-fields', 'checkout-fields' ),
	'legacy'      => array(),
	'upsell'      => 'checkout_fields',
	'sections'    => array(
		'fields' => array(
			'title'           => __( 'Checkout fields', 'wp-easycart' ),
			'icon'            => 'list',
			'hint'            => __( 'Questions you add to the classic and the one-page checkout', 'wp-easycart' ),
			'pro'             => true,
			'pro_min_version' => '6.0.2',
			'fields'          => array(),
			'keywords'        => array( 'custom fields', 'checkout fields', 'extra fields', 'custom checkout', 'order fields', 'how did you hear', 'delivery date', 'delivery instructions', 'tax id', 'waiver', 'consent', 'questions', 'conditional fields' ),
			'enqueue'         => array( 'wp_easycart_admin_checkout_fields', 'enqueue_locked' ),
			'render'          => array( 'wp_easycart_admin_checkout_fields', 'render_locked' ),
		),
	),
);
