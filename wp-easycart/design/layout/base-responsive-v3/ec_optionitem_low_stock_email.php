<?php
/**
 * Low stock notification for an option combination ( admin ).
 *
 * Included by ec_notifications::send_optionitem_low_stock_email_admin() ( inc/classes/core/ec_notifications.php ) with:
 *   $product ( product_id, title, stock fields, optionitem_name_1..5 ), $option_item_stock_quantity,
 *   $email_logo_url, $store_page, $permalink_divider. Sets $option_list ( comma-separated option names ).
 *
 * 6.0.0: on the shared email design ( wp_easycart_email_design, inc/classes/core/class-wp-easycart-email-design.php ).
 * Admin copy with a warning banner, a product card ( options + quantity ) and a button to edit the product. No hooks.
 *
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'wp_easycart_email_design' ) ) {
	require_once EC_PLUGIN_DIRECTORY . '/inc/classes/core/class-wp-easycart-email-design.php';
}

$ec_stock_options = array();
for ( $ec_stock_n = 1; $ec_stock_n <= 5; $ec_stock_n++ ) {
	if ( isset( $product->{'optionitem_name_' . $ec_stock_n} ) && '' !== (string) $product->{'optionitem_name_' . $ec_stock_n} ) {
		$ec_stock_options[] = (string) $product->{'optionitem_name_' . $ec_stock_n};
	}
}
$option_list = implode( ', ', $ec_stock_options );

$ed               = 'wp_easycart_email_design';
$ec_stock_title   = (string) $product->title;
$ec_stock_qty     = (int) $option_item_stock_quantity;
$ec_stock_pid     = isset( $product->product_id ) ? (int) $product->product_id : 0;
/* 6.0.0: the number this combination was actually judged against ( its reorder point, else the store-wide setting ). */
$ec_stock_trigger = function_exists( 'wp_easycart_low_stock_threshold' ) ? wp_easycart_low_stock_threshold( $product ) : get_option( 'ec_option_low_stock_trigger_total' );
/* translators: 1: product title, 2: option names, 3: stock quantity */
$ec_stock_line = sprintf( __( '%1$s with the options %2$s stock level is currently at %3$d.', 'wp-easycart' ), $ec_stock_title, $option_list, $ec_stock_qty );

$ed::open(
	array(
		/* translators: %s: product title */
		'title'     => sprintf( __( 'Stock for %s is Low', 'wp-easycart' ), $ec_stock_title ),
		'preheader' => $ec_stock_line,
		'logo_url'  => (string) $email_logo_url,
		'store_url' => (string) $store_page,
		'eyebrow'   => __( 'Store notification', 'wp-easycart' ),
	)
);

/* translators: %s: product title */
$ed::notice( esc_html( sprintf( __( 'Stock for %s is Low', 'wp-easycart' ), $ec_stock_title ) ), 'warning' );

$ed::section_start( array( 'top' => 16 ) );
$ed::paragraph( esc_html( $ec_stock_line ) );
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
			'label' => esc_html__( 'Options', 'wp-easycart' ),
			'value' => esc_html( $option_list ),
		),
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
$ed::close();
