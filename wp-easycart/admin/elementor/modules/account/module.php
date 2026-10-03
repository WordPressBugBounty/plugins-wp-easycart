<?php
/**
 * Elementor module: account widgets ( 6.0.2 ).
 *
 * My Account ( the whole account area ) and its parts: Customer Login, Registration Form, Lost Password, Account Navigation,
 * Account Dashboard, Order History, Order Details, Subscriptions, Addresses, Account Details, Payment Methods, Downloads,
 * Logout. Always registered ( no filter ); the two older account widgets ( behind wp_easycart_enable_elementor_account_elements )
 * are retired to them. See includes/class-wp-easycart-elementor-account.php for how links, caching and samples work.
 *
 * Loaded on plugins_loaded by WP_EasyCart_Elementor, whether or not Elementor is active.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-wp-easycart-elementor-account.php';
require_once __DIR__ . '/includes/class-wp-easycart-elementor-account-views.php';
if ( file_exists( EC_PLUGIN_DIRECTORY . '/admin/elementor/wp-easycart-elementor-account-functions.php' ) ) {
	require_once EC_PLUGIN_DIRECTORY . '/admin/elementor/wp-easycart-elementor-account-functions.php';
}

WP_EasyCart_Elementor_Account::init();

if ( ! function_exists( 'wp_easycart_elementor_account_widget_classes' ) ) {
	/**
	 * Adds the account widgets ( class => file ); the base class loads first.
	 *
	 * @since 6.0.2
	 *
	 * @param array $classes Widget classes.
	 * @return array
	 */
	function wp_easycart_elementor_account_widget_classes( $classes ) {
		$classes = is_array( $classes ) ? $classes : array();
		$base    = __DIR__ . '/widgets/class-wp-easycart-elementor-account-widget.php';
		if ( ! class_exists( 'WP_EasyCart_Elementor_Account_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Widget_Base' ) && is_readable( $base ) ) {
			include_once $base;
		}
		if ( ! class_exists( 'WP_EasyCart_Elementor_Account_Widget' ) ) {
			return $classes;
		}
		foreach ( WP_EasyCart_Elementor_Account::widgets() as $widget ) {
			$classes[ $widget['class'] ] = $widget['file'];
		}
		return $classes;
	}
}
add_filter( 'wp_easycart_elementor_widget_classes', 'wp_easycart_elementor_account_widget_classes' );

if ( ! function_exists( 'wp_easycart_elementor_account_retire' ) ) {
	/**
	 * Retires the two older account widgets to the new ones ( they stay registered behind their filter and keep drawing
	 * saved pages ).
	 *
	 * @since 6.0.2
	 *
	 * @param array $retired Retired widgets.
	 * @return array
	 */
	function wp_easycart_elementor_account_retire( $retired ) {
		$retired                                  = is_array( $retired ) ? $retired : array();
		$retired['wp_easycart_account_forms']     = array(
			'replacement' => 'wp_easycart_my_account',
			'title'       => __( 'My Account', 'wp-easycart' ),
			'map'         => 'wp_easycart_elementor_account_map_forms',
		);
		$retired['wp_easycart_account_dashboard'] = array(
			'replacement' => 'wp_easycart_my_account_dashboard',
			'title'       => __( 'Account Dashboard', 'wp-easycart' ),
			'map'         => 'wp_easycart_elementor_account_map_dashboard',
		);
		return $retired;
	}
}
add_filter( 'wp_easycart_elementor_retired_widgets', 'wp_easycart_elementor_account_retire' );

if ( ! function_exists( 'wp_easycart_elementor_account_map_styles' ) ) {
	/**
	 * The style choices an older account widget and the new ones share ( colours, typography, Show to ), renamed to the
	 * new control IDs. Pure.
	 *
	 * @since 6.0.2
	 *
	 * @param array $settings Older widget's saved settings.
	 * @return array
	 */
	function wp_easycart_elementor_account_map_styles( $settings ) {
		$renames     = array(
			'label_color'             => 'label_color',
			'field_text_color'        => 'field_color',
			'field_bg_color'          => 'field_background',
			'button_text_color'       => 'button_color',
			'button_bg_color'         => 'button_background',
			'button_hover_text_color' => 'button_hover_color',
			'button_hover_bg_color'   => 'button_hover_background',
			'success_box_text_color'  => 'success_color',
			'error_box_text_color'    => 'error_color',
			'link_color'              => 'link_color',
			'link_hover_color'        => 'link_hover_color',
			'header_color'            => 'heading_color',
		);
		$out         = array();
		$globals     = ( isset( $settings['__globals__'] ) && is_array( $settings['__globals__'] ) ) ? $settings['__globals__'] : array();
		$new_globals = array();
		foreach ( $renames as $old => $new ) {
			if ( isset( $settings[ $old ] ) && is_string( $settings[ $old ] ) && '' !== $settings[ $old ] ) {
				$out[ $new ] = $settings[ $old ];
			}
			if ( isset( $globals[ $old ] ) && is_string( $globals[ $old ] ) && '' !== $globals[ $old ] ) {
				$new_globals[ $new ] = $globals[ $old ];
			}
		}
		/* Typography groups with the same prefix in both ( label_, field_, button_ ); the older header_ group is the headings. */
		$groups = array(
			'label_typography_'  => 'label_typography_',
			'field_typography_'  => 'field_typography_',
			'button_typography_' => 'button_typography_',
			'header_typography_' => 'heading_typography_',
		);
		foreach ( $settings as $key => $value ) {
			foreach ( $groups as $old => $new ) {
				if ( is_string( $key ) && 0 === strpos( $key, $old ) ) {
					$out[ $new . substr( $key, strlen( $old ) ) ] = $value;
				}
			}
		}
		foreach ( $globals as $key => $value ) {
			foreach ( $groups as $old => $new ) {
				if ( is_string( $key ) && 0 === strpos( $key, $old ) && is_string( $value ) ) {
					$new_globals[ $new . substr( $key, strlen( $old ) ) ] = $value;
				}
			}
		}
		if ( ! empty( $new_globals ) ) {
			$out['__globals__'] = $new_globals;
		}
		if ( isset( $settings['visibility'] ) && in_array( $settings['visibility'], array( 'always', 'logged_in', 'logged_out' ), true ) ) {
			$out['visibility'] = $settings['visibility'];
		}
		return $out;
	}
}

if ( ! function_exists( 'wp_easycart_elementor_account_map_forms' ) ) {
	/**
	 * The older Account Forms widget to the new part for its form type ( '_wpec_replacement' names it ). Pure. A Connect
	 * Order form has no replacement ( WP_Error: the editor says so and keeps the widget ).
	 *
	 * @since 6.0.2
	 *
	 * @param array $settings Older widget's saved settings.
	 * @return array|WP_Error
	 */
	function wp_easycart_elementor_account_map_forms( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();
		$type     = ( isset( $settings['form_type'] ) && is_string( $settings['form_type'] ) ) ? $settings['form_type'] : 'login';
		$out      = wp_easycart_elementor_account_map_styles( $settings );
		switch ( $type ) {
			case 'register':
				$out['_wpec_replacement'] = 'wp_easycart_my_account_register';
				break;
			case 'forgot-password':
				$out['_wpec_replacement'] = 'wp_easycart_my_account_lost_password';
				break;
			case 'billing':
			case 'shipping':
				$out['_wpec_replacement'] = 'wp_easycart_my_account_addresses';
				$out['show']              = $type;
				break;
			case 'personal':
			case 'password':
				$out['_wpec_replacement'] = 'wp_easycart_my_account_details';
				$out['show']              = $type;
				break;
			case 'connect-order':
				/* No new widget does this: the old form stays ( a whole My Account in its place would be a second account area ). */
				return new WP_Error( 'wpec_elementor_no_replacement', __( 'The new account widgets have no Connect Order form, so keep this widget as it is.', 'wp-easycart' ) );
			default:
				$out['_wpec_replacement'] = 'wp_easycart_my_account_login';
				$out['show_register_box'] = '';
				$out['show_title']        = '';
				if ( isset( $settings['redirect_after_login'] ) && 'yes' === $settings['redirect_after_login'] ) {
					$url = '';
					if ( isset( $settings['redirect_url'] ) ) {
						$url = is_array( $settings['redirect_url'] ) ? ( isset( $settings['redirect_url']['url'] ) ? (string) $settings['redirect_url']['url'] : '' ) : (string) $settings['redirect_url'];
					}
					if ( '' !== trim( $url ) ) {
						$out['login_redirect']     = 'custom';
						$out['login_redirect_url'] = array( 'url' => trim( $url ) );
					} else {
						$out['login_redirect'] = 'page';
					}
				}
		}
		return $out;
	}
}

if ( ! function_exists( 'wp_easycart_elementor_account_map_dashboard' ) ) {
	/**
	 * The older Account Dashboard widget to the new part for its element type. Pure. A Messages box has no replacement
	 * ( WP_Error ).
	 *
	 * @since 6.0.2
	 *
	 * @param array $settings Older widget's saved settings.
	 * @return array|WP_Error
	 */
	function wp_easycart_elementor_account_map_dashboard( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();
		$type     = ( isset( $settings['dashboard_type'] ) && is_string( $settings['dashboard_type'] ) ) ? $settings['dashboard_type'] : 'recent-orders';
		$out      = wp_easycart_elementor_account_map_styles( $settings );
		switch ( $type ) {
			case 'messages':
				/* Every new account widget shows its own messages: nothing replaces this box. */
				return new WP_Error( 'wpec_elementor_no_replacement', __( 'The new account widgets show their own messages. Once this page uses them, delete this widget.', 'wp-easycart' ) );
			case 'subscriptions':
				$out['_wpec_replacement'] = 'wp_easycart_my_account_subscriptions';
				break;
			case 'downloads':
				$out['_wpec_replacement'] = 'wp_easycart_my_account_downloads';
				break;
			case 'email':
				$out['_wpec_replacement'] = 'wp_easycart_my_account_details';
				$out['show']              = 'personal';
				break;
			case 'billing':
			case 'shipping':
				$out['_wpec_replacement'] = 'wp_easycart_my_account_addresses';
				$out['show']              = $type;
				break;
			default:
				$out['_wpec_replacement'] = 'wp_easycart_my_account_orders';
		}
		return $out;
	}
}

if ( ! function_exists( 'wp_easycart_elementor_account_settings_section' ) ) {
	/**
	 * Settings › Elementor: the Account pages section.
	 *
	 * @since 6.0.2
	 *
	 * @param array $page Page declaration.
	 * @return array
	 */
	function wp_easycart_elementor_account_settings_section( $page ) {
		if ( ! is_array( $page ) ) {
			return $page;
		}
		if ( ! isset( $page['sections'] ) || ! is_array( $page['sections'] ) ) {
			$page['sections'] = array();
		}
		$page['sections']['account'] = array(
			'title'  => __( 'Account pages', 'wp-easycart' ),
			'hint'   => __( 'My Account and the separate account widgets.', 'wp-easycart' ),
			'icon'   => 'users',
			'fields' => array(),
			'render' => array( 'WP_EasyCart_Elementor_Account', 'render_settings_section' ),
		);
		return $page;
	}
}
add_filter( 'wp_easycart_settings_page_elementor', 'wp_easycart_elementor_account_settings_section' );
