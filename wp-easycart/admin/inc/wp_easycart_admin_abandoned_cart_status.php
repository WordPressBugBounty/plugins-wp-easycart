<?php
/**
 * WP EasyCart Admin — Store Status card: abandoned carts ( read-only in the free plugin ).
 * Shows the 30-day count from the core snapshot table. Links to the PRO screen when licensed, otherwise the upsell.
 * Print with: wp_easycart_admin_abandoned_cart_status_card();
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function wp_easycart_admin_abandoned_cart_status_card() {
	if ( ! class_exists( 'ec_abandoned_carts' ) || ! ec_abandoned_carts::tables_exist() ) { return; }
	$n = ec_abandoned_carts::count_recent( 30 ); $st = ec_abandoned_carts::stats( 30 );
	$pro = '' === apply_filters( 'wp_easycart_admin_lock_icon', 'locked' );
	$url = $pro ? admin_url( 'admin.php?page=wp-easycart-rates&subpage=abandon-cart' ) : apply_filters( 'wp_easycart_admin_upgrade_url', 'https://www.wpeasycart.com/wordpress-shopping-cart-pricing/?upsell=abandoned-carts' );
	echo '<div class="ecv2-status-card ecv2-status-abandoned">';
	echo '<div class="ecv2-status-card-h"><h3>' . esc_html__( 'Abandoned carts', 'wp-easycart' ) . '</h3><span class="ecv2-sub">' . esc_html__( 'last 30 days', 'wp-easycart' ) . '</span></div>';
	echo '<div class="ecv2-status-big">' . esc_html( number_format_i18n( $n ) ) . '</div>';
	echo '<div class="ecv2-sub">' . esc_html( sprintf( __( '%s left in carts with an email address', 'wp-easycart' ), $GLOBALS['currency']->get_currency_display( isset( $st['open_value'] ) ? (float) $st['open_value'] : 0 ) ) ) . '</div>';
	if ( $pro ) { echo '<a class="ecv2-btn ecv2-btn-sm" href="' . esc_url( $url ) . '">' . esc_html__( 'Recover them', 'wp-easycart' ) . ' <span class="dashicons dashicons-arrow-right-alt2"></span></a>'; }
	/* translators: %s: plan name, Pro/Premium, Pro or Premium. */
	else { echo '<div class="ecv2-status-upsell"><span class="ecv2-chip ecv2-chip-blue">' . esc_html( class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::badge( 'pro' ) : __( 'Pro/Premium', 'wp-easycart' ) ) . '</span> ' . esc_html__( 'Automatic reminder sequence with discount codes, recovery tracking and revenue reporting.', 'wp-easycart' ) . ' <a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( sprintf( __( 'See %s', 'wp-easycart' ), ( class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::plan_name() : __( 'Pro/Premium', 'wp-easycart' ) ) ) ) . '</a></div>'; }
	echo '</div>';
}

/* Store Status renders through do_action( 'wpeasycart_admin_store_status' ) ( wp_easycart_admin_store_status::load_store_status at 10 ).
 * Hook the card after it so no template edit is needed; remove this action if you place the card inside the template instead. */
add_action( 'wpeasycart_admin_store_status', 'wp_easycart_admin_abandoned_cart_status_section', 20 );
function wp_easycart_admin_abandoned_cart_status_section() {
	if ( ! class_exists( 'ec_abandoned_carts' ) || ! ec_abandoned_carts::tables_exist() ) { return; }
	echo '<div class="ecv2-status-section ecv2-status-section-abandoned">';
	wp_easycart_admin_abandoned_cart_status_card();
	echo '</div>';
}
