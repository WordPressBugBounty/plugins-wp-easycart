<?php
/**
 * WP EasyCart — abandoned cart reminder ( sequence template, PRO ).
 * Override by copying to wp-easycart-data/design/layout/{layout}/ec_abandoned_cart_reminder_email.php.
 *
 * Included by ec_abandoned_sequence::render() ( wp-easycart-pro/inc/classes/core/ec_abandoned_sequence.php ) for
 * steps 1 / 2 / 3, the payment-failed copy of step 1 and the admin test send.
 *
 * Variables: $cart ( ec_abandoned_cart row ), $items ( array ), $step ( array: subject, heading, intro, button ),
 * $restore_url, $unsubscribe_url, $pixel_url ( '' when tracking is off ), $coupon ( array|null: code, label, expires ),
 * $first_name, $store_name, $logo_url, $currency ( object with get_currency_display() ). $step_n ( int ) is also in scope.
 *
 * 6.0.0: on the shared email design ( wp_easycart_email_design, inc/classes/core/class-wp-easycart-email-design.php ).
 * The restore button uses the store accent color, the unsubscribe link sits in the footer and the open pixel is
 * printed after the layout only when $pixel_url is set. No hooks are fired by this template ( the sender filters the
 * finished HTML with wp_easycart_abandoned_cart_step_email ).
 *
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'wp_easycart_email_design' ) ) {
	require_once EC_PLUGIN_DIRECTORY . '/inc/classes/core/class-wp-easycart-email-design.php';
}

$ed       = 'wp_easycart_email_design';
$items    = is_array( $items ) ? $items : array();
$subtotal = 0;
foreach ( $items as $it ) {
	$subtotal += ( isset( $it['price'] ) ? (float) $it['price'] : 0 ) * ( isset( $it['qty'] ) ? (int) $it['qty'] : 0 );
}
$ec_acr_heading = isset( $step['heading'] ) ? (string) $step['heading'] : '';
$ec_acr_intro   = isset( $step['intro'] ) ? (string) $step['intro'] : '';
$ec_acr_button  = isset( $step['button'] ) ? (string) $step['button'] : '';
$ec_acr_failed  = ( isset( $step_n ) && 1 === (int) $step_n && is_object( $cart ) && isset( $cart->stage ) && 'payment_failed' === $cart->stage );
$ec_acr_money   = function ( $amount ) use ( $currency ) {
	return '<span dir="ltr">' . esc_html( is_object( $currency ) ? $currency->get_currency_display( $amount ) : number_format( (float) $amount, 2 ) ) . '</span>';
};

$ed::open(
	array(
		'title'      => isset( $step['subject'] ) ? (string) $step['subject'] : $ec_acr_heading,
		'preheader'  => $ec_acr_intro,
		'logo_url'   => (string) $logo_url,
		'store_name' => (string) $store_name,
	)
);

/* Heading and intro ( the payment-failed copy is shown as a warning banner ). */
$ed::section_start( array( 'top' => 16 ) );
$ed::heading( esc_html( $ec_acr_heading ) );
if ( ! $ec_acr_failed && '' !== $ec_acr_intro ) {
	$ed::paragraph( nl2br( esc_html( $ec_acr_intro ) ), array( 'margin' => '0' ) );
}
$ed::section_end();
if ( $ec_acr_failed && '' !== $ec_acr_intro ) {
	$ed::notice( nl2br( esc_html( $ec_acr_intro ) ), 'warning' );
}

/* Cart lines */
if ( count( $items ) > 0 ) {
	$ed::items_start(
		array(
			'product' => esc_html__( 'Product', 'wp-easycart' ),
			'qty'     => esc_html__( 'Qty', 'wp-easycart' ),
			'unit'    => esc_html__( 'Price', 'wp-easycart' ),
			'total'   => esc_html__( 'Total', 'wp-easycart' ),
		)
	);
	foreach ( $items as $it ) {
		$ec_acr_qty   = isset( $it['qty'] ) ? (int) $it['qty'] : 0;
		$ec_acr_price = isset( $it['price'] ) ? (float) $it['price'] : 0;
		$ed::item_start(
			array(
				'image_url'   => ! empty( $it['image_url'] ) ? $it['image_url'] : '',
				'image_alt'   => isset( $it['title'] ) ? $it['title'] : '',
				'image_width' => 56,
				'title_html'  => esc_html( isset( $it['title'] ) ? $it['title'] : '' ),
			)
		);
		if ( ! empty( $it['options'] ) && is_array( $it['options'] ) ) {
			$ed::detail( esc_html( implode( ' · ', $it['options'] ) ) );
		}
		$ed::item_end(
			array(
				'qty'        => $ec_acr_qty,
				'unit_html'  => $ec_acr_money( $ec_acr_price ),
				'total_html' => $ec_acr_money( $ec_acr_price * $ec_acr_qty ),
			)
		);
	}
	$ed::items_end();
	$ed::totals( array(), array( esc_html__( 'Subtotal', 'wp-easycart' ), $ec_acr_money( $subtotal ) ) );
}

/* Incentive code */
if ( $coupon ) {
	$ed::section_start( array( 'top' => 8 ) );
	$ed::code_box(
		isset( $coupon['code'] ) ? $coupon['code'] : '',
		esc_html( isset( $coupon['label'] ) ? $coupon['label'] : '' ),
		! empty( $coupon['expires'] ) ? esc_html( sprintf( /* translators: %s: expiry date */ __( 'Expires %s · applied automatically when you return', 'wp-easycart' ), $coupon['expires'] ) ) : ''
	);
	$ed::section_end();
}

/* Restore button */
$ed::button_row(
	$restore_url,
	$ec_acr_button,
	array(
		'top'    => 20,
		'bottom' => 8,
	)
);

$ed::close(
	array(
		'footer_html' => '<a href="' . esc_url( $unsubscribe_url ) . '" target="_blank" style="color:#6b7280;text-decoration:underline;">' . esc_html__( 'Unsubscribe from cart reminders', 'wp-easycart' ) . '</a>',
		'after_html'  => $pixel_url ? '<img src="' . esc_url( $pixel_url ) . '" width="1" height="1" alt="" style="display:block;border:0;width:1px;height:1px;" />' : '',
	)
);
