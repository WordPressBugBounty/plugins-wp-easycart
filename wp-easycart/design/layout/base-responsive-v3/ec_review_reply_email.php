<?php
/**
 * Sent to a reviewer when the store posts a public reply ( PRO ). Rendered by ec_reviews::send_reply_email().
 * Available: $reviewer_name, $review_title, $review_text, $rating, $reply, $signature, $product_title, $product_link,
 *            $store_name, $store_page, $email_logo_url
 *
 * 6.0.0: on the shared email design ( wp_easycart_email_design, inc/classes/core/class-wp-easycart-email-design.php ).
 * The review is quoted, the store reply sits in a card and the product link ( unchanged ) is a button.
 * Filter kept: wp_easycart_review_email_text ( keys reply_title, reply_lead, reply_from, reply_view ).
 *
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'wp_easycart_email_design' ) ) {
	require_once EC_PLUGIN_DIRECTORY . '/inc/classes/core/class-wp-easycart-email-design.php';
}

$t = function ( $key, $fallback ) {
	return apply_filters( 'wp_easycart_review_email_text', $fallback, $key );
};

$ed           = 'wp_easycart_email_design';
$ec_rp_rating = max( 0, min( 5, (int) $rating ) );
/* translators: %s: store name. */
$ec_rp_title = sprintf( $t( 'reply_title', __( '%s replied to your review', 'wp-easycart' ) ), $store_name );
/* translators: %s: product title. */
$ec_rp_lead = sprintf( $t( 'reply_lead', __( 'Thanks again for reviewing %s. We wanted to reply:', 'wp-easycart' ) ), $product_title );

$ed::open(
	array(
		'title'      => $ec_rp_title,
		'preheader'  => $ec_rp_lead,
		'logo_url'   => (string) $email_logo_url,
		'store_url'  => (string) $store_page,
		'store_name' => (string) $store_name,
	)
);
$ec_rp_ctx = $ed::ctx();

$ed::section_start();
$ed::heading( esc_html( $ec_rp_title ) );
/* translators: %s: reviewer name. */
$ed::paragraph( esc_html( '' !== trim( (string) $reviewer_name ) ? sprintf( __( 'Hi %s,', 'wp-easycart' ), $reviewer_name ) : __( 'Hi there,', 'wp-easycart' ) ), array( 'margin' => '0 0 10px 0' ) );
$ed::paragraph( esc_html( $ec_rp_lead ), array( 'margin' => '0' ) );
$ed::section_end();

/* The customer's review */
$ed::section_start( array( 'top' => 16 ) );
echo '<table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0"><tr><td align="' . esc_attr( $ec_rp_ctx['start'] ) . '" style="border-' . esc_attr( $ec_rp_ctx['start'] ) . ':3px solid #e5e7eb;padding:2px 14px 4px 14px;' . esc_attr( $ed::css( 'muted' ) ) . '">';
echo '<div dir="ltr" style="display:inline-block;color:#f59e0b;font-size:16px;letter-spacing:1px;">' . str_repeat( '&#9733;', $ec_rp_rating ) . '<span style="color:#d1d5db;">' . str_repeat( '&#9733;', 5 - $ec_rp_rating ) . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- star entities repeated an integer number of times.
if ( '' !== trim( (string) $review_title ) ) {
	echo '<div style="margin:4px 0 2px 0;' . esc_attr( $ed::css( 'strong' ) ) . '">' . esc_html( $review_title ) . '</div>';
}
echo '<div>' . nl2br( esc_html( $review_text ) ) . '</div>';
echo '</td></tr></table>' . "\n";
$ed::section_end();

/* The store reply */
$ed::section_start( array( 'top' => 16 ) );
$ed::card_start( array( 'padding' => '14px 16px' ) );
/* translators: %s: reply signature. */
echo '<div style="margin:0 0 6px 0;' . esc_attr( $ed::css( 'label' ) ) . '">' . esc_html( sprintf( $t( 'reply_from', __( 'Reply from %s', 'wp-easycart' ) ), $signature ) ) . '</div>';
echo '<div style="' . esc_attr( $ed::css( 'text' ) ) . 'color:#111827;">' . wp_kses(
	nl2br( $reply ),
	array(
		'br'     => array(),
		'a'      => array( 'href' => array() ),
		'b'      => array(),
		'strong' => array(),
		'i'      => array(),
		'em'     => array(),
	)
) . '</div>';
$ed::card_end();
$ed::section_end();

if ( $product_link ) {
	$ed::button_row( $product_link, $t( 'reply_view', __( 'See your review on the product page', 'wp-easycart' ) ), array( 'top' => 16, 'bottom' => 8, 'arrow' => true ) );
}

$ed::close( array( 'signature' => false ) );
