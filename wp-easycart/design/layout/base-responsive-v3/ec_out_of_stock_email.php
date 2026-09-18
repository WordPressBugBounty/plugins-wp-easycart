<?php
/**
 * Out of stock notification ( admin ).
 *
 * Included by ec_notifications::send_out_of_stock_email_admin() ( inc/classes/core/ec_notifications.php ) with:
 *   $product ( product_id, title, show_stock_quantity, use_optionitem_quantity_tracking, stock_quantity, optionitem_name_1..5 ),
 *   $email_logo_url, $store_page, $permalink_divider.
 *
 * 6.0.0: on the shared email design ( wp_easycart_email_design, inc/classes/core/class-wp-easycart-email-design.php ).
 * Admin copy with a danger banner, a product card and a button to edit the product. No hooks.
 *
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'wp_easycart_email_design' ) ) {
	require_once EC_PLUGIN_DIRECTORY . '/inc/classes/core/class-wp-easycart-email-design.php';
}

$ed             = 'wp_easycart_email_design';
$ec_stock_title = (string) $product->title;
$ec_stock_qty   = isset( $product->stock_quantity ) ? (int) $product->stock_quantity : 0;
$ec_stock_pid   = isset( $product->product_id ) ? (int) $product->product_id : 0;

$ed::open(
	array(
		/* translators: %s: product title */
		'title'     => sprintf( __( '%s is out of Stock', 'wp-easycart' ), $ec_stock_title ),
		/* translators: %s: product title */
		'preheader' => sprintf( __( '%s is currently out of stock.', 'wp-easycart' ), $ec_stock_title ),
		'logo_url'  => (string) $email_logo_url,
		'store_url' => (string) $store_page,
		'eyebrow'   => __( 'Store notification', 'wp-easycart' ),
	)
);

/* translators: %s: product title */
$ed::notice( esc_html( sprintf( __( '%s is out of Stock', 'wp-easycart' ), $ec_stock_title ) ), 'danger' );

$ed::section_start( array( 'top' => 16 ) );
/* translators: %s: product title */
$ed::paragraph( esc_html( sprintf( __( '%s is currently out of stock.', 'wp-easycart' ), $ec_stock_title ) ) );
$ed::card_start();
$ed::paragraph(
	esc_html( $ec_stock_title ),
	array(
		'tone'   => 'strong',
		'margin' => '0 0 8px 0',
	)
);
$ed::key_values(
	array(
		array(
			'label' => esc_html__( 'In stock', 'wp-easycart' ),
			'value' => '<span dir="ltr" style="color:#b91c1c;">' . esc_html( $ec_stock_qty ) . '</span>',
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
