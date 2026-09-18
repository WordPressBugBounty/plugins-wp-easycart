<?php
/**
 * New customer review notification ( store copy, Settings › Product reviews › notify ).
 *
 * Included by ec_db::submit_customer_review() with:
 *   $review ( ec_review row + product_title, email ), $review_admin_url, $email_logo_url, $store_page, $permalink_divider.
 *
 * 6.0.0: rebuilt on the shared email design ( wp_easycart_email_design, inc/classes/core/class-wp-easycart-email-design.php ).
 * Store notification tone: the review is summarised in a card and $review_admin_url ( unchanged ) is the button.
 * Copy this file to your wp-easycart-data layout folder to customise it; the file name must stay the same.
 *
 * Language strings kept: ec_customer_review_notify_email / email_title, reviewed_product, submitted_by, anonymous,
 * rating_label, review_title, review_comments, link_text.
 *
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'wp_easycart_email_design' ) ) {
	require_once EC_PLUGIN_DIRECTORY . '/inc/classes/core/class-wp-easycart-email-design.php';
}
if ( ! isset( $review ) || ! is_object( $review ) ) {
	return;
}

$ed             = 'wp_easycart_email_design';
$ec_rv_lang     = wp_easycart_language();
$ec_rv_title    = $ec_rv_lang->get_text( 'ec_customer_review_notify_email', 'email_title' );
$ec_rv_product  = isset( $review->product_title ) ? stripslashes( (string) $review->product_title ) : '';
$ec_rv_by       = ( isset( $review->user_id ) && 0 != $review->user_id && isset( $review->email ) ) ? (string) $review->email : wp_strip_all_tags( $ec_rv_lang->get_text( 'ec_customer_review_notify_email', 'anonymous' ) ); // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- user_id is a numeric string.
$ec_rv_rating   = isset( $review->rating ) ? max( 0, min( 5, (int) $review->rating ) ) : 0;
$ec_rv_heading  = isset( $review->title ) ? stripslashes( (string) $review->title ) : '';
$ec_rv_comments = isset( $review->description ) ? stripslashes( (string) $review->description ) : '';
$ec_rv_url      = isset( $review_admin_url ) ? (string) $review_admin_url : '';

$ed::open(
	array(
		'title'     => wp_strip_all_tags( $ec_rv_title ),
		'preheader' => wp_strip_all_tags( $ec_rv_lang->get_text( 'ec_customer_review_notify_email', 'reviewed_product' ) ) . ': ' . $ec_rv_product . ' (' . $ec_rv_rating . '/5)',
		'logo_url'  => isset( $email_logo_url ) ? (string) $email_logo_url : (string) get_option( 'ec_option_email_logo' ),
		'store_url' => ( isset( $store_page ) && '' !== (string) $store_page ) ? (string) $store_page : null,
		'eyebrow'   => __( 'Store notification', 'wp-easycart' ),
	)
);

$ed::section_start();
$ed::heading( wp_kses_post( $ec_rv_title ) );
$ed::section_end();

$ed::section_start( array( 'top' => 4 ) );
$ed::card_start();
$ed::key_values(
	array(
		array(
			'label' => wp_kses_post( $ec_rv_lang->get_text( 'ec_customer_review_notify_email', 'reviewed_product' ) ),
			'value' => esc_html( $ec_rv_product ),
		),
		array(
			'label' => wp_kses_post( $ec_rv_lang->get_text( 'ec_customer_review_notify_email', 'rating_label' ) ),
			'value' => '<span dir="ltr" style="color:#f59e0b;letter-spacing:1px;">' . str_repeat( '&#9733;', $ec_rv_rating ) . '<span style="color:#d1d5db;">' . str_repeat( '&#9733;', 5 - $ec_rv_rating ) . '</span></span> ' . $ed::ltr( $ec_rv_rating . '/5' ),
		),
	)
);
$ed::key_values(
	array(
		array(
			'label' => wp_kses_post( $ec_rv_lang->get_text( 'ec_customer_review_notify_email', 'submitted_by' ) ),
			'value' => '<span class="ec-email-nolink">' . esc_html( $ec_rv_by ) . '</span>',
		),
	)
);
$ed::card_end();
$ed::section_end();

$ed::section_start( array( 'top' => 16 ) );
if ( '' !== trim( $ec_rv_heading ) ) {
	$ed::label( wp_kses_post( $ec_rv_lang->get_text( 'ec_customer_review_notify_email', 'review_title' ) ) );
	$ed::paragraph( esc_html( $ec_rv_heading ), array( 'tone' => 'strong', 'margin' => '0 0 12px 0' ) );
}
$ed::label( wp_kses_post( $ec_rv_lang->get_text( 'ec_customer_review_notify_email', 'review_comments' ) ) );
$ed::card_start( array( 'padding' => '12px 14px', 'background' => '#ffffff' ) );
echo nl2br( esc_html( $ec_rv_comments ) );
$ed::card_end();
$ed::section_end();

$ed::button_row( $ec_rv_url, $ec_rv_lang->get_text( 'ec_customer_review_notify_email', 'link_text' ), array( 'top' => 20, 'bottom' => 8, 'arrow' => true ) );

$ed::close( array( 'signature' => false ) );
