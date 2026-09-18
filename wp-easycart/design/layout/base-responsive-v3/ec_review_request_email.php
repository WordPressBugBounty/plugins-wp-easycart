<?php
/**
 * Review request email ( also used for the PRO reminder ). Rendered by ec_reviews::send_request_email().
 * Available: $first_name, $order_id, $products [ { title, image, link, stars[1..5] } ], $is_reminder, $intro,
 *            $store_name, $store_page, $email_logo_url, $unsubscribe_url, $signature_text, $signature_image
 * Copy to wp-easycart-data/design/layout/<your layout>/ to customise.
 *
 * 6.0.0: on the shared email design ( wp_easycart_email_design, inc/classes/core/class-wp-easycart-email-design.php ).
 * The product, star and "don't ask me" links are used exactly as ec_reviews builds them; the signature comes from
 * $signature_text / $signature_image and the unsubscribe link sits in the footer.
 * Filter kept: wp_easycart_review_email_text ( keys title, greeting, greeting_name, lead, lead_reminder, order_ref,
 * tap_star, thanks, footer, unsubscribe ).
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
/* translators: %s: customer first name. */
$greeting = '' !== trim( (string) $first_name ) ? sprintf( $t( 'greeting_name', __( 'Hi %s,', 'wp-easycart' ) ), esc_html( $first_name ) ) : $t( 'greeting', __( 'Hi there,', 'wp-easycart' ) );
$lead     = $is_reminder
	? $t( 'lead_reminder', __( 'Just a quick reminder — we would still love to hear how your recent order worked out. It only takes a moment: tap a star below.', 'wp-easycart' ) )
	: $t( 'lead', __( 'Thanks for your recent order! Would you take a moment to tell other shoppers what you think? Tap a star below to get started — it takes less than a minute.', 'wp-easycart' ) );
if ( '' !== trim( (string) $intro ) ) {
	$lead = nl2br( esc_html( $intro ) );
}

$ed = 'wp_easycart_email_design';
$ed::open(
	array(
		'title'      => $t( 'title', __( 'How did you like your order?', 'wp-easycart' ) ),
		'preheader'  => wp_strip_all_tags( $lead ),
		'logo_url'   => (string) $email_logo_url,
		'store_url'  => (string) $store_page,
		'store_name' => (string) $store_name,
	)
);
$ec_rr_ctx = $ed::ctx();

$ed::section_start();
$ed::heading( esc_html( $t( 'title', __( 'How did you like your order?', 'wp-easycart' ) ) ) );
$ed::paragraph( wp_kses_post( $greeting ), array( 'margin' => '0 0 10px 0' ) );
$ed::paragraph( wp_kses_post( $lead ), array( 'margin' => '0 0 6px 0' ) );
/* translators: %d: order number. */
$ed::paragraph( esc_html( sprintf( $t( 'order_ref', __( 'Order #%d', 'wp-easycart' ) ), (int) $order_id ) ), array( 'tone' => 'small', 'margin' => '0' ) );
$ed::section_end();

foreach ( (array) $products as $p ) {
	$ed::section_start( array( 'top' => 16 ) );
	echo '<table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb;border-radius:8px;border-collapse:separate;"><tr>';
	if ( ! empty( $p['image'] ) ) {
		echo '<td valign="top" width="96" style="width:96px;padding:12px;padding-' . esc_attr( $ec_rr_ctx['end'] ) . ':0;"><a href="' . esc_url( $p['link'] ) . '" target="_blank"><img src="' . esc_url( $p['image'] ) . '" width="72" height="72" alt="" style="display:block;width:72px;height:72px;border-radius:6px;object-fit:cover;border:1px solid #e5e7eb;" /></a></td>';
	}
	echo '<td valign="top" align="' . esc_attr( $ec_rr_ctx['start'] ) . '" style="padding:12px 14px;' . esc_attr( $ed::css( 'text' ) ) . '">';
	echo '<div style="margin:0 0 6px 0;' . esc_attr( $ed::css( 'strong' ) ) . 'font-size:15px;"><a href="' . esc_url( $p['link'] ) . '" target="_blank" style="color:#111827;text-decoration:none;">' . esc_html( $p['title'] ) . '</a></div>';
	echo '<div style="margin:0 0 6px 0;' . esc_attr( $ed::css( 'small' ) ) . '">' . esc_html( $t( 'tap_star', __( 'Tap a star to rate it:', 'wp-easycart' ) ) ) . '</div>';
	echo '<div dir="ltr" style="white-space:nowrap;">';
	for ( $i = 1; $i <= 5; $i++ ) {
		/* translators: %d: star rating, 1 to 5. */
		echo '<a href="' . esc_url( $p['stars'][ $i ] ) . '" target="_blank" title="' . esc_attr( sprintf( __( '%d of 5 stars', 'wp-easycart' ), $i ) ) . '" style="text-decoration:none;font-size:30px;line-height:1;color:#f59e0b;padding-right:4px;">&#9733;</a>';
	}
	echo '</div>';
	echo '</td></tr></table>' . "\n";
	$ed::section_end();
}

$ed::section_start( array( 'top' => 24, 'bottom' => 4 ) );
$ed::paragraph( esc_html( $t( 'thanks', __( 'Thank you — every review helps other shoppers and helps us improve.', 'wp-easycart' ) ) ), array( 'margin' => '0' ) );
$ed::section_end();

/* Signature ( the sender passes the store signature settings ). */
if ( $signature_text || $signature_image ) {
	$ed::section_start( array( 'top' => 12 ) );
	if ( $signature_image ) {
		echo '<img src="' . esc_url( $signature_image ) . '" alt="" style="display:block;max-width:200px;height:auto;margin:0 0 8px 0;" />';
	}
	if ( $signature_text ) {
		$ed::paragraph( nl2br( esc_html( stripslashes( $signature_text ) ) ), array( 'tone' => 'muted', 'margin' => '0' ) );
	}
	$ed::section_end();
}

$ed::close(
	array(
		'signature'   => false,
		'footer_html' => esc_html( $t( 'footer', __( 'You received this because you placed an order with', 'wp-easycart' ) ) ) . ' ' . esc_html( $store_name ) . '. <a href="' . esc_url( $unsubscribe_url ) . '" target="_blank" style="color:#6b7280;text-decoration:underline;">' . esc_html( $t( 'unsubscribe', __( 'Don’t ask me for reviews', 'wp-easycart' ) ) ) . '</a>',
	)
);
