<?php
/**
 * Starter pages: the request guard ( 6.0.2 ).
 *
 * A plain function, so the coding standard's nonce check sees it ( registered in .phpcs.xml.dist ).
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wp_easycart_elementor_starter_guard' ) ) {
	/**
	 * Nonce and capability check for Settings › Elementor › Starter pages ( admin-post wp_easycart_elementor_starter_pages ).
	 * Ends the request on failure. Nonce 'wp-easycart-elementor-starter-pages' in _wpnonce; the person manages the store's
	 * settings ( manage_options or wpec_settings ) and can edit posts ( Elementor templates are posts ).
	 *
	 * @since 6.0.2
	 */
	function wp_easycart_elementor_starter_guard() {
		check_admin_referer( 'wp-easycart-elementor-starter-pages' );
		if ( ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_settings' ) ) || ! current_user_can( 'edit_posts' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- EasyCart's own roles register this capability.
			wp_die(
				esc_html__( 'You are not allowed to add store templates.', 'wp-easycart' ),
				esc_html__( 'Not allowed', 'wp-easycart' ),
				array(
					'response'  => 403,
					'back_link' => true,
				)
			);
		}
	}
}
