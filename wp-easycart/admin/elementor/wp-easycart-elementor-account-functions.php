<?php
/**
 * WP EasyCart Elementor account widget helpers ( 6.0.2 ).
 *
 * Shared by the Account Forms and Account Dashboard widgets. Loaded with those widget classes, so it may assume WP EasyCart
 * is loaded but not that Elementor is.
 *
 * @package  Wp_Easycart_Elementor
 * @author   WP EasyCart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wp_easycart_elementor_account_signed_in' ) ) {
	/**
	 * Whether a customer is signed in to the store ( the test the [ec_account] shortcode makes ).
	 *
	 * @since 6.0.2
	 *
	 * @return bool
	 */
	function wp_easycart_elementor_account_signed_in() {
		if ( ! isset( $GLOBALS['ec_cart_data'] ) || ! is_object( $GLOBALS['ec_cart_data'] ) || ! isset( $GLOBALS['ec_cart_data']->cart_data ) || ! is_object( $GLOBALS['ec_cart_data']->cart_data ) ) {
			return false;
		}
		return isset( $GLOBALS['ec_cart_data']->cart_data->user_id ) && (int) $GLOBALS['ec_cart_data']->cart_data->user_id > 0;
	}
}

if ( ! function_exists( 'wp_easycart_elementor_account_notice' ) ) {
	/**
	 * A note for the merchant, printed in Elementor's editor and preview only.
	 *
	 * @since 6.0.2
	 *
	 * @param string $text Plain text.
	 */
	function wp_easycart_elementor_account_notice( $text ) {
		echo '<div class="wp-easycart-elementor-editor-notice" style="margin:0 0 10px;padding:10px 12px;border:1px dashed #c3c4c7;border-radius:4px;background:#f6f7f7;color:#50575e;font-size:13px;line-height:1.5;">' . esc_html( $text ) . '</div>';
	}
}

if ( ! function_exists( 'wp_easycart_elementor_account_render_start' ) ) {
	/**
	 * Start of an account widget's render.
	 *
	 * These widgets print the signed-in customer's details and nonces, so the page must never be cached ( the [ec_account]
	 * shortcode renders through AJAX on cached stores; these render on the page ). The template_redirect check in
	 * wpeasycart.php also sends no-cache headers for pages built with them; the constants here cover theme builder templates
	 * ( headers, popups ) that check cannot see.
	 *
	 * Then the widget's Show to setting ( visibility: always | logged_in | logged_out ). The editor always draws the
	 * element, with a note when a visitor in the current state would not see it.
	 *
	 * @since 6.0.2
	 *
	 * @param array $settings Widget settings for display.
	 * @return bool Whether to render the widget.
	 */
	function wp_easycart_elementor_account_render_start( $settings ) {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- the page cache plugins' own constant.
		}
		if ( ! defined( 'DONOTCDN' ) ) {
			define( 'DONOTCDN', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- the page cache plugins' own constant.
		}

		$visibility = ( is_array( $settings ) && isset( $settings['visibility'] ) && is_string( $settings['visibility'] ) ) ? $settings['visibility'] : 'always';
		if ( 'logged_in' !== $visibility && 'logged_out' !== $visibility ) {
			return true;
		}
		$signed_in = wp_easycart_elementor_account_signed_in();
		if ( ( 'logged_in' === $visibility ) === $signed_in ) {
			return true;
		}
		if ( function_exists( 'wp_easycart_elementor_is_editor' ) && wp_easycart_elementor_is_editor() ) {
			wp_easycart_elementor_account_notice(
				( 'logged_in' === $visibility )
					? __( 'Only customers who are signed in see this element.', 'wp-easycart' )
					: __( 'Only visitors who are not signed in see this element.', 'wp-easycart' )
			);
			return true;
		}
		return false;
	}
}

if ( ! function_exists( 'wp_easycart_elementor_account_visibility_control' ) ) {
	/**
	 * Arguments of the Show to control both account widgets add ( control ID visibility ). The default reproduces how
	 * every widget saved before 6.0.2 renders: to everyone.
	 *
	 * @since 6.0.2
	 *
	 * @return array
	 */
	function wp_easycart_elementor_account_visibility_control() {
		return array(
			'label'       => esc_html__( 'Show To', 'wp-easycart' ),
			'type'        => \Elementor\Controls_Manager::SELECT,
			'options'     => array(
				'always'     => esc_html__( 'Everyone', 'wp-easycart' ),
				'logged_in'  => esc_html__( 'Signed-in customers', 'wp-easycart' ),
				'logged_out' => esc_html__( 'Visitors who are not signed in', 'wp-easycart' ),
			),
			'default'     => 'always',
			'description' => esc_html__( 'For example, show the login form to visitors only and the orders to signed-in customers only.', 'wp-easycart' ),
		);
	}
}
