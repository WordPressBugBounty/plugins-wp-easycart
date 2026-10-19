<?php
/**
 * WP EasyCart Shell V2 — Theme helper.
 *
 * Single source of truth for the admin brand-color shade system.
 * The same recipe exists in three places and MUST stay in sync:
 *   1. wp_easycart_shell_theme_shades() below        (PHP fallback output)
 *   2. the @supports color-mix() block in admin-frame-v2.css (modern browsers)
 *   3. wpEasyCartShellShades() in admin-frame-v2.js          (live color preview)
 *
 * Math note: the existing wp_easycart_admin()->adjust_hex_brightness( $c, $p )
 * is an exact mirror of CSS color-mix:
 *   $p < 0  ===  color-mix( in srgb, $c, #000 abs($p)*100% )
 *   $p > 0  ===  color-mix( in srgb, $c, #fff $p*100% )
 * so we reuse it for black/white mixes and only add mix_hex() for
 * mixing toward an arbitrary color.
 *
 * @since 5.x.x
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Canonical shade recipe. Keys are CSS variable names (minus fallbacks
 * computed elsewhere), values are [ method, amount ].
 *
 * Filterable so extensions / white-label builds can tune the scale.
 */
function wp_easycart_shell_theme_recipe() {
	return apply_filters( 'wp_easycart_shell_theme_recipe', array(
		'--ec-brand-hover'  => array( 'black', 0.12 ),
		'--ec-brand-active' => array( 'black', 0.22 ),
		'--ec-brand-deep'   => array( 'black', 0.42 ),
		'--ec-brand-soft'   => array( 'white', 0.90 ),
		'--ec-brand-softer' => array( 'white', 0.95 ),
	) );
}

/**
 * Mix a hex color toward another hex color by $pct (0..1).
 * Straight per-channel linear interpolation — identical to
 * color-mix( in srgb, $hex, $target $pct*100% ).
 */
function wp_easycart_shell_mix_hex( $hex, $target, $pct ) {
	$hex    = wp_easycart_shell_normalize_hex( $hex );
	$target = wp_easycart_shell_normalize_hex( $target );
	$out    = '#';
	for ( $i = 0; $i < 3; $i++ ) {
		$a    = hexdec( substr( $hex, 1 + $i * 2, 2 ) );
		$b    = hexdec( substr( $target, 1 + $i * 2, 2 ) );
		$out .= str_pad( dechex( (int) round( $a + ( $b - $a ) * $pct ) ), 2, '0', STR_PAD_LEFT );
	}
	return $out;
}

/**
 * Normalize #abc / abc / #aabbcc to #aabbcc. Falls back to EasyCart green.
 */
function wp_easycart_shell_normalize_hex( $hex ) {
	$hex = ltrim( trim( (string) $hex ), '#' );
	if ( strlen( $hex ) === 3 ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	if ( ! preg_match( '/^[0-9a-fA-F]{6}$/', $hex ) ) {
		$hex = '7bb141';
	}
	return '#' . strtolower( $hex );
}

/**
 * Relative-ish luminance (0..1) used to flip on-brand text dark when a
 * user picks a very light color. Same coefficients as admin-frame-v2.js.
 */
function wp_easycart_shell_luminance( $hex ) {
	$hex = wp_easycart_shell_normalize_hex( $hex );
	$r   = hexdec( substr( $hex, 1, 2 ) );
	$g   = hexdec( substr( $hex, 3, 2 ) );
	$b   = hexdec( substr( $hex, 5, 2 ) );
	return ( 0.299 * $r + 0.587 * $g + 0.114 * $b ) / 255;
}

/**
 * Compute all shell variables for a brand color.
 * Returns array of css-var => hex.
 */
function wp_easycart_shell_theme_shades( $brand ) {
	$brand = wp_easycart_shell_normalize_hex( $brand );
	$admin = wp_easycart_admin();
	$vars  = array( '--ec-brand' => $brand );

	foreach ( wp_easycart_shell_theme_recipe() as $var => $step ) {
		list( $method, $amount ) = $step;
		// adjust_hex_brightness: negative = mix toward black, positive = toward white.
		$vars[ $var ] = $admin->adjust_hex_brightness( $brand, 'black' === $method ? -$amount : $amount );
	}

	$light = wp_easycart_shell_luminance( $brand ) > 0.72;
	$vars['--ec-on-brand']     = $light ? '#1f2937' : '#ffffff';
	$vars['--ec-on-brand-dim'] = $light
		? wp_easycart_shell_mix_hex( '#1f2937', $brand, 0.28 )
		: wp_easycart_shell_mix_hex( '#ffffff', $brand, 0.28 );

	return $vars;
}

/**
 * Output the :root block. This is the ENTIRE replacement for the old
 * ~50-rule inline style block in admin-frame.php. Modern browsers re-derive
 * the shade vars via the @supports color-mix() block in admin-frame-v2.css;
 * old browsers simply use these pre-computed values.
 */
function wp_easycart_shell_output_root_css() {
	$vars = wp_easycart_shell_theme_shades( get_option( 'ec_option_admin_color' ) );
	echo "<style id=\"ecsh-root-vars\">\n:root {\n";
	foreach ( $vars as $name => $value ) {
		echo "\t" . esc_attr( $name ) . ': ' . esc_attr( $value ) . ";\n";
	}
	echo "}\n</style>\n";
}

/**
 * Store-status urgency level for nav badge coloring.
 * Preserves the exact thresholds from the previous left_nav.php:
 *   trial:      <=4 danger, <=9 warn, else ok
 *   non-trial:  <=35 danger (includes <=0), <=69 warn, else ok
 *   AutoPay:    ok until its date passes ( 6.0.4: it renews itself )
 *
 * @return array{ level:string, days_left:int, is_trial:bool, has_license:bool }
 */
function wp_easycart_shell_store_status() {
	$out = array( 'level' => 'ok', 'days_left' => 0, 'is_trial' => false, 'has_license' => false );

	if ( ! function_exists( 'wp_easycart_admin_license' ) ) {
		return $out;
	}
	/* 6.0.5 ( admin review M13 ): the license this site has ( wp_easycart_admin_edition ), never another site's registration. */
	$license_data = class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::license_data() : wp_easycart_admin_license()->license_data;
	if ( ! $license_data ) {
		return $out;
	}
	$out['has_license'] = true;
	$out['is_trial']    = isset( $license_data->is_trial ) && $license_data->is_trial;

	$expiration = isset( $license_data->support_end_date ) ? strtotime( $license_data->support_end_date ) : time();
	$days_left  = (int) round( ( $expiration - time() ) / ( 60 * 60 * 24 ) );
	$out['days_left'] = max( 0, $days_left );

	if ( $out['is_trial'] ) {
		if ( $out['days_left'] <= 4 ) {
			$out['level'] = 'danger';
		} elseif ( $out['days_left'] <= 9 ) {
			$out['level'] = 'warn';
		}
	} elseif ( ! ( $out['days_left'] > 0 && class_exists( 'wp_easycart_admin_edition' ) && wp_easycart_admin_edition::is_auto_renew() ) ) {
		if ( $out['days_left'] <= 35 ) {
			$out['level'] = 'danger';
		} elseif ( $out['days_left'] <= 69 ) {
			$out['level'] = 'warn';
		}
	}
	return $out;
}

/**
 * The admin pages other plugins add to the search ( filter wp_easycart_shell_search_index; entries label, url, group, kw ).
 * 6.0.5: the hand-kept list that was here is gone: the one admin search ( wp_easycart_admin_search ) builds its pages from
 * the menu, the Settings menu and Extensions, and reads this filter for what other plugins add.
 *
 * @return array
 */
function wp_easycart_shell_search_index() {
	return (array) apply_filters( 'wp_easycart_shell_search_index', array() );
}

/**
 * 6.0.5: nothing to print ( the search window fetches its index ). Kept for code that called it.
 */
function wp_easycart_shell_output_search_index() {
}

/**
 * Breadcrumb for the topbar: EasyCart / <Page> / <Subpage>.
 * Reuses the same labels as left_nav.php. Multi-subpage groups
 * (e.g. optionitems, submenus) alias to their parent entry.
 * Filter wp_easycart_shell_breadcrumb lets specific screens extend
 * the trail (e.g. append a product name).
 *
 * @return array{ trail: array<int, array{label:string,url:string}>, here: string }
 */
function wp_easycart_shell_breadcrumb() {
	$page    = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
	$subpage = isset( $_GET['subpage'] ) ? sanitize_text_field( wp_unslash( $_GET['subpage'] ) ) : '';

	$pages = array(
		'wp-easycart-dashboard'      => __( 'Reports', 'wp-easycart' ),
		'wp-easycart-license-status' => __( 'Store Status', 'wp-easycart' ),
		'wp-easycart-products'       => __( 'Products', 'wp-easycart' ),
		'wp-easycart-orders'         => __( 'Orders', 'wp-easycart' ),
		'wp-easycart-users'          => __( 'Users', 'wp-easycart' ),
		'wp-easycart-rates'          => __( 'Marketing', 'wp-easycart' ),
		'wp-easycart-settings'       => __( 'Settings', 'wp-easycart' ),
		'wp-easycart-status'         => __( 'Diagnostics', 'wp-easycart' ),
		'wp-easycart-registration'   => __( 'Registration', 'wp-easycart' ),
		'wp-easycart-extensions'     => __( 'Extensions', 'wp-easycart' ),
	);

	/* Aliases: alternate subpage slugs that belong to a parent entry. */
	$aliases = array(
		'optionitems'              => 'option',
		'category-products'        => 'category',
		'category-products-manage' => 'category',
		'submenus'                 => 'menus',
		'subsubmenus'              => 'menus',
	);
	if ( isset( $aliases[ $subpage ] ) ) {
		$subpage = $aliases[ $subpage ];
	}

	$subpages = array(
		'wp-easycart-products' => array(
			'inventory'         => __( 'Inventory', 'wp-easycart' ),
			'option'            => __( 'Option Sets', 'wp-easycart' ),
			'category'          => __( 'Categories', 'wp-easycart' ),
			'menus'             => __( 'Menus', 'wp-easycart' ),
			'manufacturers'     => __( 'Manufacturers', 'wp-easycart' ),
			'reviews'           => __( 'Product Reviews', 'wp-easycart' ),
			'import'            => __( 'Import', 'wp-easycart' ),
			'subscriptionplans' => __( 'Subscription Plans', 'wp-easycart' ),
		),
		'wp-easycart-orders' => array(
			'subscriptions' => __( 'Subscriptions', 'wp-easycart' ),
			'downloads'     => __( 'Manage Downloads', 'wp-easycart' ),
		),
		'wp-easycart-users' => array(
			'accounts'    => __( 'Customers', 'wp-easycart' ), /* 6.0.5 ( admin review M12 ): the list's own title */
			'user-roles'  => __( 'User Roles', 'wp-easycart' ),
			'subscribers' => __( 'Subscribers', 'wp-easycart' ),
		),
		'wp-easycart-rates' => array(
			'offers'       => __( 'Offers', 'wp-easycart' ),
			'cart-links'   => __( 'Cart Links', 'wp-easycart' ),
			'abandon-cart' => __( 'Abandoned Cart', 'wp-easycart' ),
			'gift-cards'   => __( 'Gift Cards', 'wp-easycart' ),
		),
		'wp-easycart-settings' => array(
			''                  => __( 'Initial Setup', 'wp-easycart' ),
			'products'          => __( 'Products', 'wp-easycart' ),
			'checkout'          => __( 'Checkout', 'wp-easycart' ),
			'account'           => __( 'Accounts', 'wp-easycart' ),
			'payment'           => __( 'Payment', 'wp-easycart' ),
			'tax'               => __( 'Taxes', 'wp-easycart' ),
			'fee'               => __( 'Flex-Fees', 'wp-easycart' ),
			'shipping-settings' => __( 'Shipping Settings', 'wp-easycart' ),
			'shipping-rates'    => __( 'Shipping Rates', 'wp-easycart' ),
			'miscellaneous'     => __( 'Additional Settings', 'wp-easycart' ),
			'design'            => __( 'Design', 'wp-easycart' ),
			'language-editor'   => __( 'Language', 'wp-easycart' ),
			'email-setup'       => __( 'Email', 'wp-easycart' ),
			'documents'         => __( 'Documents', 'wp-easycart' ),
			'country'           => __( 'Countries', 'wp-easycart' ),
			'states'            => __( 'States/Territories', 'wp-easycart' ),
			'perpage'           => __( 'Per Page Options', 'wp-easycart' ),
			'pricepoint'        => __( 'Price Points', 'wp-easycart' ),
			'schedule'          => __( 'Store Schedule', 'wp-easycart' ),
			'location'          => __( 'Store Locations', 'wp-easycart' ),
			'third-party'       => __( 'Third Party', 'wp-easycart' ),
			'cart-importer'     => __( 'Cart Importer', 'wp-easycart' ),
			'logs'              => __( 'Log Entries', 'wp-easycart' ),
		),
	);

	/* 6.0.2: EasyCart › Extensions › <extension>. */
	if ( class_exists( 'wp_easycart_admin_extensions' ) ) {
		foreach ( wp_easycart_admin_extensions::catalog() as $ecsh_ext_slug => $ecsh_ext ) {
			$subpages['wp-easycart-extensions'][ $ecsh_ext_slug ] = $ecsh_ext['name'];
		}
	}

	$crumb = array( 'trail' => array(), 'here' => '' );

	if ( isset( $pages[ $page ] ) ) {
		$sub_label = isset( $subpages[ $page ] ) && isset( $subpages[ $page ][ $subpage ] ) ? $subpages[ $page ][ $subpage ] : '';
		/* 6.0.2: any other settings page names itself from its declaration ( Search & AI, Checkout protection, Email marketing ... ). */
		if ( '' === $sub_label && 'wp-easycart-settings' === $page && '' !== $subpage && class_exists( 'wp_easycart_admin_settings_registry' ) ) {
			$decl      = wp_easycart_admin_settings_registry::page( $subpage );
			$sub_label = ( $decl && 'settings' === $decl['host'] && ! empty( $decl['title'] ) ) ? (string) $decl['title'] : '';
		}
		/* Same-named default subpages (products/products, orders/orders) collapse to two levels. */
		if ( '' !== $sub_label && $sub_label !== $pages[ $page ] ) {
			$crumb['trail'][] = array( 'label' => $pages[ $page ], 'url' => 'admin.php?page=' . $page );
			$crumb['here']    = $sub_label;
		} else {
			$crumb['here'] = $pages[ $page ];
		}
	}

	return apply_filters( 'wp_easycart_shell_breadcrumb', $crumb, $page, $subpage );
}