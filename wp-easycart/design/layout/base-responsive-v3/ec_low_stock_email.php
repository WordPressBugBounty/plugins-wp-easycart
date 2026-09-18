<?php
/**
 * Low stock notification ( admin ).
 *
 * Included by ec_notifications::send_low_stock_email_admin() ( inc/classes/core/ec_notifications.php ) with:
 *   $product ( product_id, title, show_stock_quantity, use_optionitem_quantity_tracking, stock_quantity, optionitem_name_1..5 ),
 *   $email_logo_url, $store_page, $permalink_divider.
 *
 * 6.0.0: on the shared email design ( wp_easycart_email_design, inc/classes/core/class-wp-easycart-email-design.php ).
 * Admin copy with a warning banner, a product card and a button to edit the product. No hooks.
 *
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'wp_easycart_email_design' ) ) {
	require_once EC_PLUGIN_DIRECTORY . '/inc/classes/core/class-wp-easycart-email-design.php';
}

$ed               = 'wp_easycart_email_design';
$ec_stock_title   = wp_easycart_language()->convert_text( $product->title );
$ec_stock_qty     = isset( $product->stock_quantity ) ? (int) $product->stock_quantity : 0;
$ec_stock_pid     = isset( $product->product_id ) ? (int) $product->product_id : 0;
/* 6.0.0: the number this item was actually judged against ( its reorder point, else the store-wide setting ). */
$ec_stock_trigger = function_exists( 'wp_easycart_low_stock_threshold' ) ? wp_easycart_low_stock_threshold( $product ) : get_option( 'ec_option_low_stock_trigger_total' );

$ed::open(
	array(
		/* translators: %s: product title */
		'title'     => sprintf( __( 'Stock for %s is Low', 'wp-easycart' ), $ec_stock_title ),
		/* translators: 1: product title, 2: stock quantity */
		'preheader' => sprintf( __( '%1$s stock level is currently at %2$s.', 'wp-easycart' ), $ec_stock_title, $ec_stock_qty ),
		'logo_url'  => (string) $email_logo_url,
		'store_url' => (string) $store_page,
		'eyebrow'   => __( 'Store notification', 'wp-easycart' ),
	)
);

/* translators: %s: product title */
$ed::notice( esc_html( sprintf( __( 'Stock for %s is Low', 'wp-easycart' ), $ec_stock_title ) ), 'warning' );

$ed::section_start( array( 'top' => 16 ) );
/* translators: 1: product title, 2: stock quantity */
$ed::paragraph( esc_html( sprintf( __( '%1$s stock level is currently at %2$s.', 'wp-easycart' ), $ec_stock_title, $ec_stock_qty ) ) );
$ed::card_start();
$ed::paragraph(
	wp_kses_post( $ec_stock_title ),
	array(
		'tone'   => 'strong',
		'margin' => '0 0 8px 0',
	)
);
$ed::key_values(
	array(
		array(
			'label' => esc_html__( 'In stock', 'wp-easycart' ),
			'value' => $ed::ltr( $ec_stock_qty ),
		),
		array(
			'label' => esc_html__( 'Low stock alert at', 'wp-easycart' ),
			'value' => ( '' !== (string) $ec_stock_trigger ) ? $ed::ltr( (int) $ec_stock_trigger ) : '',
		),
	)
);
if ( $ec_stock_pid > 0 ) {
	$ed::button(
		admin_url( 'admin.php?page=wp-easycart-products&subpage=products&ec_admin_form_action=edit&product_id=' . $ec_stock_pid ),
		__( 'Edit product', 'wp-easycart' ),
		array(
			'margin' => '14px 0 0 0',
			'arrow'  => true,
		)
	);
}
$ed::card_end();
$ed::section_end();

$ed::section_start(
	array(
		'top'    => 16,
		'bottom' => 8,
	)
);
$ed::paragraph(
	'<a href="' . esc_url( admin_url( 'admin.php?page=wp-easycart-settings&subpage=checkout' ) ) . '" target="_blank" style="color:#6b7280;text-decoration:underline;">' . esc_html__( 'To turn off these notifications, go to WP EasyCart -> Settings -> Checkout.', 'wp-easycart' ) . '</a>',
	array(
		'tone'   => 'small',
		'margin' => '0',
	)
);
$ed::section_end();
$ed::close( array( 'signature' => false ) );
