<?php
/**
 * WP EasyCart Shell V2 — Theme helper.
 *
 * Single source of truth for the admin brand-color shade system.
 * The same recipe exists in three places and MUST stay in sync:
 *   1. wp_easycart_shell_theme_shades() below        (PHP fallback output)
 *   2. the @supports color-mix() block in shell-v2.css (modern browsers)
 *   3. wpEasyCartShellShades() in shell-v2.js          (live color preview)
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
 * user picks a very light color. Same coefficients as shell-v2.js.
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
 * ~50-rule inline style block in shell.php. Modern browsers re-derive
 * the shade vars via the @supports color-mix() block in shell-v2.css;
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
 *
 * @return array{ level:string, days_left:int, is_trial:bool, has_license:bool }
 */
function wp_easycart_shell_store_status() {
	$out = array( 'level' => 'ok', 'days_left' => 0, 'is_trial' => false, 'has_license' => false );

	if ( ! function_exists( 'wp_easycart_admin_license' ) ) {
		return $out;
	}
	$license_data = wp_easycart_admin_license()->license_data;
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
	} else {
		if ( $out['days_left'] <= 35 ) {
			$out['level'] = 'danger';
		} elseif ( $out['days_left'] <= 69 ) {
			$out['level'] = 'warn';
		}
	}
	return $out;
}

/**
 * Command-palette search index: pages, subpages, and sections within
 * settings pages. Capability-gated to match left_nav.php. Section entries
 * add &ecshs=<label>; shell-v2.js scrolls to the matching section header
 * on arrival. Filterable via wp_easycart_shell_search_index so extensions
 * can register their own destinations.
 *
 * Entry shape: array( 'label' => string, 'url' => string,
 *   'group' => string (shown as breadcrumb), 'kw' => string (extra match terms) )
 */
function wp_easycart_shell_search_index() {
	$items = array();
	$can   = function( $cap ) { return current_user_can( 'manage_options' ) || current_user_can( $cap ); };
	$sec   = function( $base, $label ) { return $base . '&ecshs=' . rawurlencode( $label ); };

	if ( $can( 'wpec_reports' ) ) {
		$items[] = array( 'label' => __( 'Reports', 'wp-easycart' ), 'url' => 'admin.php?page=wp-easycart-dashboard', 'group' => '', 'kw' => 'dashboard sales stats analytics' );
	}
	if ( $can( 'wpec_store_status' ) ) {
		$items[] = array( 'label' => __( 'Store Status', 'wp-easycart' ), 'url' => 'admin.php?page=wp-easycart-license-status', 'group' => '', 'kw' => 'license trial upgrade' );
	}
	if ( $can( 'wpec_products' ) ) {
		$p = 'admin.php?page=wp-easycart-products&subpage=';
		$g = __( 'Products', 'wp-easycart' );
		$items[] = array( 'label' => __( 'Products', 'wp-easycart' ), 'url' => $p . 'products', 'group' => '', 'kw' => 'manage products catalog items' );
		$items[] = array( 'label' => __( 'Inventory', 'wp-easycart' ), 'url' => $p . 'inventory', 'group' => $g, 'kw' => 'stock quantity' );
		$items[] = array( 'label' => __( 'Option Sets', 'wp-easycart' ), 'url' => $p . 'option', 'group' => $g, 'kw' => 'variants options swatches' );
		$items[] = array( 'label' => __( 'Categories', 'wp-easycart' ), 'url' => $p . 'category', 'group' => $g, 'kw' => '' );
		$items[] = array( 'label' => __( 'Menus', 'wp-easycart' ), 'url' => $p . 'menus', 'group' => $g, 'kw' => '' );
		$items[] = array( 'label' => __( 'Manufacturers', 'wp-easycart' ), 'url' => $p . 'manufacturers', 'group' => $g, 'kw' => 'brands' );
		$items[] = array( 'label' => __( 'Product Reviews', 'wp-easycart' ), 'url' => $p . 'reviews', 'group' => $g, 'kw' => 'ratings' );
		$items[] = array( 'label' => __( 'Subscription Plans', 'wp-easycart' ), 'url' => $p . 'subscriptionplans', 'group' => $g, 'kw' => 'recurring' );
	}
	if ( $can( 'wpec_orders' ) ) {
		$p = 'admin.php?page=wp-easycart-orders&subpage=';
		$g = __( 'Orders', 'wp-easycart' );
		$items[] = array( 'label' => __( 'Orders', 'wp-easycart' ), 'url' => $p . 'orders', 'group' => '', 'kw' => 'sales purchases invoices' );
		$items[] = array( 'label' => __( 'Subscriptions', 'wp-easycart' ), 'url' => $p . 'subscriptions', 'group' => $g, 'kw' => 'recurring billing' );
		$items[] = array( 'label' => __( 'Manage Downloads', 'wp-easycart' ), 'url' => $p . 'downloads', 'group' => $g, 'kw' => 'digital files' );
	}
	if ( $can( 'wpec_users' ) ) {
		$p = 'admin.php?page=wp-easycart-users&subpage=';
		$g = __( 'Users', 'wp-easycart' );
		$items[] = array( 'label' => __( 'User Accounts', 'wp-easycart' ), 'url' => $p . 'accounts', 'group' => '', 'kw' => 'customers users' );
		$items[] = array( 'label' => __( 'User Roles', 'wp-easycart' ), 'url' => $p . 'user-roles', 'group' => $g, 'kw' => 'b2b pricing roles' );
		$items[] = array( 'label' => __( 'Subscribers', 'wp-easycart' ), 'url' => $p . 'subscribers', 'group' => $g, 'kw' => 'newsletter email list' );
	}
	if ( $can( 'wpec_marketing' ) ) {
		$p = 'admin.php?page=wp-easycart-rates&subpage=';
		$g = __( 'Marketing', 'wp-easycart' );
		$items[] = array( 'label' => __( 'Offers', 'wp-easycart' ), 'url' => $p . 'offers', 'group' => $g, 'kw' => 'coupons discounts promotions deals' );
		$items[] = array( 'label' => __( 'Cart Links', 'wp-easycart' ), 'url' => $p . 'cart-links', 'group' => $g, 'kw' => 'buy links' );
		$items[] = array( 'label' => __( 'Abandoned Cart', 'wp-easycart' ), 'url' => $p . 'abandon-cart', 'group' => $g, 'kw' => 'recovery email' );
		$items[] = array( 'label' => __( 'Gift Cards', 'wp-easycart' ), 'url' => $p . 'gift-cards', 'group' => $g, 'kw' => 'vouchers' );
	}
	if ( $can( 'wpec_settings' ) ) {
		$s = 'admin.php?page=wp-easycart-settings';
		$g = __( 'Settings', 'wp-easycart' );

		$items[] = array( 'label' => __( 'Initial Setup', 'wp-easycart' ), 'url' => $s, 'group' => $g, 'kw' => 'wizard setup' );
		$items[] = array( 'label' => __( 'Store Landing Page', 'wp-easycart' ), 'url' => $sec( $s, __( 'Store Landing Page', 'wp-easycart' ) ), 'group' => $g . ' › ' . __( 'Initial Setup', 'wp-easycart' ), 'kw' => 'ec_store shortcode store page' );
		$items[] = array( 'label' => __( 'Cart Page', 'wp-easycart' ), 'url' => $sec( $s, __( 'Cart Page', 'wp-easycart' ) ), 'group' => $g . ' › ' . __( 'Initial Setup', 'wp-easycart' ), 'kw' => 'ec_cart checkout page' );
		$items[] = array( 'label' => __( 'Account Page', 'wp-easycart' ), 'url' => $sec( $s, __( 'Account Page', 'wp-easycart' ) ), 'group' => $g . ' › ' . __( 'Initial Setup', 'wp-easycart' ), 'kw' => 'ec_account order history' );
		$items[] = array( 'label' => __( 'Currency', 'wp-easycart' ), 'url' => $sec( $s, __( 'Currency', 'wp-easycart' ) ), 'group' => $g . ' › ' . __( 'Initial Setup', 'wp-easycart' ), 'kw' => 'usd symbol exchange rates decimal' );
		$items[] = array( 'label' => __( 'eCommerce Goals', 'wp-easycart' ), 'url' => $sec( $s, __( 'eCommerce Goals', 'wp-easycart' ) ), 'group' => $g . ' › ' . __( 'Initial Setup', 'wp-easycart' ), 'kw' => 'monthly goal sales target' );

		$items[] = array( 'label' => __( 'Products Settings', 'wp-easycart' ), 'url' => $s . '&subpage=products', 'group' => $g, 'kw' => 'product display options' );
		$items[] = array( 'label' => __( 'Checkout Settings', 'wp-easycart' ), 'url' => $s . '&subpage=checkout', 'group' => $g, 'kw' => 'guest checkout terms' );
		$items[] = array( 'label' => __( 'Accounts Settings', 'wp-easycart' ), 'url' => $s . '&subpage=account', 'group' => $g, 'kw' => 'registration login' );
		$items[] = array( 'label' => __( 'Payment', 'wp-easycart' ), 'url' => $s . '&subpage=payment', 'group' => $g, 'kw' => 'stripe paypal square gateway credit card' );
		$items[] = array( 'label' => __( 'Taxes', 'wp-easycart' ), 'url' => $s . '&subpage=tax', 'group' => $g, 'kw' => 'vat tax rates taxcloud' );
		$items[] = array( 'label' => __( 'Flex-Fees', 'wp-easycart' ), 'url' => $s . '&subpage=fee', 'group' => $g, 'kw' => 'fees surcharge' );
		$items[] = array( 'label' => __( 'Shipping Settings', 'wp-easycart' ), 'url' => $s . '&subpage=shipping-settings', 'group' => $g, 'kw' => 'ups usps fedex delivery' );
		$items[] = array( 'label' => __( 'Shipping Rates', 'wp-easycart' ), 'url' => $s . '&subpage=shipping-rates', 'group' => $g, 'kw' => 'rates tables live rates' );
		$items[] = array( 'label' => __( 'Design', 'wp-easycart' ), 'url' => $s . '&subpage=design', 'group' => $g, 'kw' => 'theme colors layout' );
		$items[] = array( 'label' => __( 'Language', 'wp-easycart' ), 'url' => $s . '&subpage=language-editor', 'group' => $g, 'kw' => 'translations text labels' );
		$items[] = array( 'label' => __( 'Email', 'wp-easycart' ), 'url' => $s . '&subpage=email-setup', 'group' => $g, 'kw' => 'receipts smtp templates' );
		$items[] = array( 'label' => __( 'Countries', 'wp-easycart' ), 'url' => $s . '&subpage=country', 'group' => $g, 'kw' => '' );
		$items[] = array( 'label' => __( 'States/Territories', 'wp-easycart' ), 'url' => $s . '&subpage=states', 'group' => $g, 'kw' => 'provinces regions' );
		$items[] = array( 'label' => __( 'Per Page Options', 'wp-easycart' ), 'url' => $s . '&subpage=perpage', 'group' => $g, 'kw' => 'pagination' );
		$items[] = array( 'label' => __( 'Price Points', 'wp-easycart' ), 'url' => $s . '&subpage=pricepoint', 'group' => $g, 'kw' => '' );
		$items[] = array( 'label' => __( 'Store Schedule', 'wp-easycart' ), 'url' => $s . '&subpage=schedule', 'group' => $g, 'kw' => 'hours open closed' );
		$items[] = array( 'label' => __( 'Third Party', 'wp-easycart' ), 'url' => $s . '&subpage=third-party', 'group' => $g, 'kw' => 'integrations facebook google tiktok mailchimp' );
		$items[] = array( 'label' => __( 'Cart Importer', 'wp-easycart' ), 'url' => $s . '&subpage=cart-importer', 'group' => $g, 'kw' => 'import woocommerce shopify migrate' );
		$items[] = array( 'label' => __( 'Log Entries', 'wp-easycart' ), 'url' => $s . '&subpage=logs', 'group' => $g, 'kw' => 'gateway log errors debug' );

		$m  = $s . '&subpage=miscellaneous';
		$mg = $g . ' › ' . __( 'Additional Settings', 'wp-easycart' );
		$items[] = array( 'label' => __( 'Additional Settings', 'wp-easycart' ), 'url' => $m, 'group' => $g, 'kw' => 'misc advanced' );
		$items[] = array( 'label' => __( 'Cart Icon Display', 'wp-easycart' ), 'url' => $sec( $m, __( 'Cart Icon Display', 'wp-easycart' ) ), 'group' => $mg, 'kw' => 'header menu cart icon' );
		$items[] = array( 'label' => __( 'Abandoned Cart Email', 'wp-easycart' ), 'url' => $sec( $m, __( 'Abandoned Cart Email', 'wp-easycart' ) ), 'group' => $mg, 'kw' => 'days emailer' );
		$items[] = array( 'label' => __( 'Product Quick Add Options', 'wp-easycart' ), 'url' => $sec( $m, __( 'Product Quick Add Options', 'wp-easycart' ) ), 'group' => $mg, 'kw' => 'quick creation stock shipping tax variants' );
		$items[] = array( 'label' => __( 'Additional Admin Options', 'wp-easycart' ), 'url' => $sec( $m, __( 'Additional Admin Options', 'wp-easycart' ) ), 'group' => $mg, 'kw' => 'records per page refunds product editor v2 pickup' );
		$items[] = array( 'label' => __( 'Search Options', 'wp-easycart' ), 'url' => $sec( $m, __( 'Search Options', 'wp-easycart' ) ), 'group' => $mg, 'kw' => 'store search' );
	}
	if ( $can( 'wpec_diagnostics' ) ) {
		$items[] = array( 'label' => __( 'Diagnostics', 'wp-easycart' ), 'url' => 'admin.php?page=wp-easycart-status&subpage=store-status', 'group' => '', 'kw' => 'troubleshoot system status' );
	}
	if ( $can( 'wpec_registration' ) ) {
		$items[] = array( 'label' => __( 'Registration', 'wp-easycart' ), 'url' => 'admin.php?page=wp-easycart-registration&subpage=registration', 'group' => '', 'kw' => 'license key activate' );
	}

	return apply_filters( 'wp_easycart_shell_search_index', $items );
}

/**
 * Print the search index for shell-v2.js.
 */
function wp_easycart_shell_output_search_index() {
	echo '<script>var wpEasyCartShellSearchIndex = ' . wp_json_encode( wp_easycart_shell_search_index() ) . ';</script>' . "\n";
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
			'subscriptionplans' => __( 'Subscription Plans', 'wp-easycart' ),
		),
		'wp-easycart-orders' => array(
			'subscriptions' => __( 'Subscriptions', 'wp-easycart' ),
			'downloads'     => __( 'Manage Downloads', 'wp-easycart' ),
		),
		'wp-easycart-users' => array(
			'accounts'    => __( 'User Accounts', 'wp-easycart' ),
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

	$crumb = array( 'trail' => array(), 'here' => '' );

	if ( isset( $pages[ $page ] ) ) {
		$sub_label = isset( $subpages[ $page ] ) && isset( $subpages[ $page ][ $subpage ] ) ? $subpages[ $page ][ $subpage ] : '';
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