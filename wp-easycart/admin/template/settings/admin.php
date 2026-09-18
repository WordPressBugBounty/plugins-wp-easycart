<?php
/**
 * Settings › Admin ( V2 declaration ).
 *
 * Replaces the catch-all "Additional Settings" page ( legacy slug `miscellaneous` ).
 * The storefront search options and product export block size moved to Products,
 * the cart icon / newsletter popup options moved to Design and the abandoned cart
 * delay moved to Checkout; everything else from that page lives here.
 *
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wp_easycart_settings_admin_legacy_auth_saved' ) ) {
	/**
	 * Legacy app login side effect: turning it off wipes the stored v1 admin
	 * credentials ( ec_user.password_admin_v1 ). WordPress core already fires
	 * wpeasycart.php's update_option_ec_option_enable_legacy_app_auth hook, but
	 * only when the value changes; calling the purge here as well is idempotent
	 * and keeps the behavior even if that hook is ever removed.
	 */
	function wp_easycart_settings_admin_legacy_auth_saved( $value, $old, $field ) {
		if ( function_exists( 'wp_easycart_maybe_purge_admin_password_backup' ) ) {
			wp_easycart_maybe_purge_admin_password_backup( $old, $value );
		}
	}
}

if ( ! function_exists( 'wp_easycart_settings_admin_link_style_saved' ) ) {
	/**
	 * Product link style side effect: the setup wizard's sync_recommended_settings()
	 * flips basic links back to permalinks on every EasyCart admin load unless the
	 * merchant's choice is recorded as an override, so record it here exactly the way
	 * the wizard's Advanced disclosure does.
	 */
	function wp_easycart_settings_admin_link_style_saved( $value, $old, $field ) {
		$option    = class_exists( 'wp_easycart_admin_setup_wizard' ) ? wp_easycart_admin_setup_wizard::OPT_OVERRIDES : 'ec_option_recommended_overrides';
		$overrides = get_option( $option, array() );
		if ( ! is_array( $overrides ) ) {
			$overrides = array();
		}
		if ( '1' === (string) $value ) {
			$overrides['seo'] = 1;
		} else {
			unset( $overrides['seo'] );
		}
		update_option( $option, $overrides );
	}
}

if ( ! function_exists( 'wp_easycart_settings_admin_link_style_validate' ) ) {
	/** Permalinks off means the wizard sync forces basic links back on; say so. */
	function wp_easycart_settings_admin_link_style_validate( $value, $field ) {
		if ( '0' === (string) $value && '' === (string) get_option( 'permalink_structure' ) ) {
			return __( 'WordPress permalinks are set to Plain, so basic links stay in use until you choose another structure under Settings › Permalinks.', 'wp-easycart' );
		}
		return '';
	}
}

if ( ! function_exists( 'wp_easycart_settings_admin_tracking_saved' ) ) {
	/** Usage data side effect: the legacy "allow" handlers sent the initial snapshot immediately. */
	function wp_easycart_settings_admin_tracking_saved( $value, $old, $field ) {
		if ( '1' !== (string) $value ) {
			return;
		}
		if ( ! function_exists( 'wp_easycart_admin_tracking' ) ) {
			include EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_tracking.php';
		}
		do_action( 'wpeasycart_admin_usage_tracking_accepted' );
	}
}

if ( ! function_exists( 'wp_easycart_settings_admin_delete_gateway_log' ) ) {
	/** "Delete Log File" link from the legacy page. */
	function wp_easycart_settings_admin_delete_gateway_log( $action, $page ) {
		if ( function_exists( 'wp_easycart_admin_miscellaneous' ) ) {
			wp_easycart_admin_miscellaneous()->delete_gateway_log();
		} else {
			global $wpdb;
			$wpdb->query( 'DELETE FROM ec_webhook' );
			$wpdb->query( 'DELETE FROM ec_response' );
		}
		return __( 'Gateway and webhook log entries deleted.', 'wp-easycart' );
	}
}

if ( ! function_exists( 'wp_easycart_settings_admin_clear_stats' ) ) {
	/** "Clear Stats" link from the legacy page. */
	function wp_easycart_settings_admin_clear_stats( $action, $page ) {
		if ( function_exists( 'wp_easycart_admin_miscellaneous' ) ) {
			wp_easycart_admin_miscellaneous()->clear_stats();
		} else {
			global $wpdb;
			$wpdb->query( 'UPDATE ec_menulevel1, ec_menulevel2, ec_menulevel3 SET ec_menulevel1.clicks = 0, ec_menulevel2.clicks = 0, ec_menulevel3.clicks = 0' );
			$wpdb->query( 'UPDATE ec_product SET ec_product.views = 0' );
		}
		return __( 'Product view and category click counts reset to zero.', 'wp-easycart' );
	}
}

$wp_easycart_settings_admin_perpage = array();
foreach ( array( 10, 25, 50, 100, 250, 500 ) as $wp_easycart_settings_admin_size ) {
	/* translators: %d: number of rows */
	$wp_easycart_settings_admin_perpage[ (string) $wp_easycart_settings_admin_size ] = sprintf( __( '%d per page', 'wp-easycart' ), $wp_easycart_settings_admin_size );
}

return array(
	'slug'        => 'admin',
	'title'       => __( 'Admin', 'wp-easycart' ),
	'description' => __( 'How the EasyCart admin behaves: the quick-add panel, order lists, mobile apps, diagnostics and a few store-wide switches.', 'wp-easycart' ),
	'group'       => 'customize',
	'icon'        => 'admin-tools',
	'docs'        => array( 'settings', 'additional-settings', 'additional-options' ),
	'legacy'      => array( 'miscellaneous' ),
	'upsell'      => 'default',
	'sections'    => array(

		'quick-add' => array(
			'title'  => __( 'Quick add panel', 'wp-easycart' ),
			'hint'   => __( 'Extra fields on the Products › Add product slide-out', 'wp-easycart' ),
			'fields' => array(
				'ec_option_admin_product_show_stock_option' => array(
					'type'     => 'toggle',
					'label'    => __( 'Stock fields', 'wp-easycart' ),
					'desc'     => __( 'Adds stock tracking and quantity to the quick-add panel so new products start with inventory set.', 'wp-easycart' ),
					'default'  => 0,
					'keywords' => array( 'inventory', 'quantity', 'new product' ),
					'legacy'   => array( 'page' => 'miscellaneous', 'section' => 'Product Quick Add Options', 'label' => 'Show Stock Options' ),
				),
				'ec_option_admin_product_show_shipping_option' => array(
					'type'     => 'toggle',
					'label'    => __( 'Shipping fields', 'wp-easycart' ),
					'desc'     => __( 'Adds weight and shipping settings to the quick-add panel.', 'wp-easycart' ),
					'default'  => 0,
					'keywords' => array( 'weight', 'dimensions', 'new product' ),
					'legacy'   => array( 'page' => 'miscellaneous', 'section' => 'Product Quick Add Options', 'label' => 'Show Shipping Options' ),
				),
				'ec_option_admin_product_show_tax_option' => array(
					'type'     => 'toggle',
					'label'    => __( 'Tax fields', 'wp-easycart' ),
					'desc'     => __( 'Adds the taxable switch and tax class to the quick-add and quick-edit panels.', 'wp-easycart' ),
					'default'  => 0,
					'keywords' => array( 'taxable', 'vat', 'new product' ),
					'legacy'   => array( 'page' => 'miscellaneous', 'section' => 'Product Quick Add Options', 'label' => 'Show Tax Options' ),
				),
				'ec_option_admin_product_show_variant_option' => array(
					'type'     => 'toggle',
					'label'    => __( 'Variant fields', 'wp-easycart' ),
					'desc'     => __( 'Adds the option-set picker to the quick-add panel so variants can be attached while creating a product.', 'wp-easycart' ),
					'default'  => 1,
					'keywords' => array( 'options', 'option set', 'variations', 'new product' ),
					'legacy'   => array( 'page' => 'miscellaneous', 'section' => 'Product Quick Add Options', 'label' => 'Show Variants' ),
				),
			),
		),

		'orders' => array(
			'title'  => __( 'Orders', 'wp-easycart' ),
			'hint'   => __( 'Extra columns on the order list and what happens after a refund', 'wp-easycart' ),
			'fields' => array(
				'ec_option_admin_orders_list_enable_pickup_date' => array(
					'type'     => 'toggle',
					'label'    => __( 'Pre-order pickup date column', 'wp-easycart' ),
					'desc'     => __( 'Adds a column to Orders showing the pickup date chosen on pre-order items.', 'wp-easycart' ),
					'default'  => 0,
					'keywords' => array( 'preorder', 'pickup', 'order list', 'column' ),
					'legacy'   => array( 'page' => 'miscellaneous', 'section' => 'Additional Admin Options', 'label' => 'Order List: Enable Preorder Pickup Date Display' ),
				),
				'ec_option_admin_orders_list_enable_pickup_time' => array(
					'type'     => 'toggle',
					'label'    => __( 'Restaurant pickup time column', 'wp-easycart' ),
					'desc'     => __( 'Adds a column to Orders showing the expected pickup time on restaurant-style orders.', 'wp-easycart' ),
					'default'  => 0,
					'keywords' => array( 'restaurant', 'pickup', 'order list', 'column' ),
					'legacy'   => array( 'page' => 'miscellaneous', 'section' => 'Additional Admin Options', 'label' => 'Order List: Enable Restaurant Pickup Time Display' ),
				),
				'ec_option_auto_send_refund_email' => array(
					'type'     => 'toggle',
					'label'    => __( 'Email the customer after a refund', 'wp-easycart' ),
					'desc'     => __( 'Sends the refund notification automatically whenever a full or partial refund completes from the order screen.', 'wp-easycart' ),
					'default'  => 0,
					'pro'      => true,
					'keywords' => array( 'refund', 'notification', 'customer email' ),
					'legacy'   => array( 'page' => 'miscellaneous', 'section' => 'Additional Admin Options', 'label' => 'Order Refunds: Automatically send refund email to customer' ),
				),
			),
		),

		'admin-screens' => array(
			'title'  => __( 'Admin screens', 'wp-easycart' ),
			'hint'   => __( 'List paging and promotional panels across the EasyCart admin', 'wp-easycart' ),
			'fields' => array(
				'ec_option_admin_default_perpage' => array(
					'type'     => 'select',
					'label'    => __( 'Rows per page on admin lists', 'wp-easycart' ),
					'desc'     => __( 'Starting page size for products, orders, users and the other list screens. A page size chosen on a list is remembered per admin and per list and overrides this.', 'wp-easycart' ),
					'default'  => '25',
					'options'  => $wp_easycart_settings_admin_perpage,
					'keywords' => array( 'per page', 'pagination', 'records', 'list' ),
					'legacy'   => array( 'page' => 'miscellaneous', 'section' => 'Additional Admin Options', 'label' => 'Admin Lists: Default Records Per Page' ),
				),
				'ec_option_disable_easycart_ad' => array(
					'type'     => 'toggle',
					'label'    => __( 'Hide the text notifications promo', 'wp-easycart' ),
					'desc'     => __( 'Removes the "Add text notifications" panels from the order screens and store status page. Disappears on its own once a text notifications plan is active.', 'wp-easycart' ),
					'default'  => 0,
					'pro'      => true,
					'advanced' => true,
					'keywords' => array( 'ad', 'advert', 'promo', 'sms' ),
					'legacy'   => array( 'page' => 'miscellaneous', 'section' => 'Additional Options', 'label' => 'Disable EasyCart Ad Area' ),
				),
			),
		),

		'mobile-apps' => array(
			'title'  => __( 'Mobile apps', 'wp-easycart' ),
			'hint'   => __( 'The WP EasyCart admin apps for iOS and Android ( Premium )', 'wp-easycart' ),
			'fields' => array(
				'ec_option_enable_push_notifications' => array(
					'type'     => 'toggle',
					'label'    => __( 'Push a notification for each new order', 'wp-easycart' ),
					'desc'     => __( 'Sends the order number and total to your WP EasyCart apps when an order comes in. Notifications must also be allowed on the device.', 'wp-easycart' ),
					'default'  => 1,
					'pro'      => 'premium',
					'keywords' => array( 'push', 'app', 'phone', 'alert' ),
					'legacy'   => array( 'page' => 'miscellaneous', 'section' => 'Admin Apps Options (Premium Only)', 'label' => 'App Notifications' ),
				),
				'ec_option_enable_legacy_app_auth' => array(
					'type'     => 'toggle',
					'label'    => __( 'Allow sign-in from older app versions (v1 login)', 'wp-easycart' ),
					'desc'     => __( 'Keeps a legacy login credential for admin users so outdated apps can still connect. Turn off once every device runs the current app: the stored legacy credentials are wiped immediately, and turning it back on later needs each admin to sign in again before older apps work.', 'wp-easycart' ),
					'default'  => 1,
					'advanced' => true,
					'on_save'  => 'wp_easycart_settings_admin_legacy_auth_saved',
					'keywords' => array( 'app', 'login', 'security', 'legacy' ),
					'legacy'   => array( 'page' => 'miscellaneous', 'section' => 'Admin Apps Options (Premium Only)', 'label' => 'Legacy App Login (v1)' ),
				),
			),
		),

		'storefront' => array(
			'title'  => __( 'Storefront behavior', 'wp-easycart' ),
			'hint'   => __( 'Store-wide switches that change how product pages link and submit', 'wp-easycart' ),
			'fields' => array(
				'ec_option_use_old_linking_style' => array(
					'type'     => 'pills',
					'label'    => __( 'Product link style', 'wp-easycart' ),
					'desc'     => __( 'Permalinks give every product, category and manufacturer its own WordPress post URL (recommended, needs WordPress permalinks on). Basic links open everything on the store page with ?ec_store= query strings.', 'wp-easycart' ),
					'default'  => '0',
					'options'  => array(
						'0' => __( 'Permalinks', 'wp-easycart' ),
						'1' => __( 'Basic links', 'wp-easycart' ),
					),
					'on_save'  => 'wp_easycart_settings_admin_link_style_saved',
					'validate' => 'wp_easycart_settings_admin_link_style_validate',
					'keywords' => array( 'seo', 'permalink', 'url', 'posts' ),
					'legacy'   => array( 'page' => 'miscellaneous', 'section' => 'Additional Options', 'label' => 'Use SEO Friendly Links' ),
				),
				'ec_option_use_inquiry_form' => array(
					'type'     => 'toggle',
					'label'    => __( 'Built-in inquiry form on inquiry-mode products', 'wp-easycart' ),
					'desc'     => __( 'Inquiry-mode products show EasyCart’s name, email and message form and email you the request. Turn off to send shoppers to each product’s inquiry URL instead; products with no URL still show the form.', 'wp-easycart' ),
					'default'  => 1,
					'advanced' => true,
					'keywords' => array( 'inquiry', 'quote', 'contact', 'request' ),
					'legacy'   => array( 'page' => 'miscellaneous', 'section' => 'Additional Options', 'label' => 'Inquiry Submit POST Variables' ),
				),
			),
		),

		'diagnostics' => array(
			'title'   => __( 'Diagnostics', 'wp-easycart' ),
			'hint'    => __( 'Logging, error visibility and usage data', 'wp-easycart' ),
			'fields'  => array(
				'ec_option_enable_gateway_log' => array(
					'type'     => 'toggle',
					'label'    => __( 'Log gateway and webhook responses', 'wp-easycart' ),
					'desc'     => __( 'Records every payment gateway response and incoming webhook under Settings › Log entries. Leave on so failed payments can be traced.', 'wp-easycart' ),
					'default'  => 1,
					'keywords' => array( 'log', 'gateway', 'webhook', 'errors' ),
					'legacy'   => array( 'page' => 'miscellaneous', 'section' => 'Additional Options', 'label' => 'Enable Gateway Log' ),
				),
				'ec_option_enable_debugging_mode' => array(
					'type'     => 'toggle',
					'label'    => __( 'Show PHP errors from the WP EasyCart PRO plugin', 'wp-easycart' ),
					'desc'     => __( 'The WP EasyCart PRO plugin normally silences PHP warnings and notices for maximum compatibility. Turn on while troubleshooting so they show; turn off again when done.', 'wp-easycart' ),
					'default'  => 0,
					'pro'      => true,
					'advanced' => true,
					'keywords' => array( 'debug', 'error reporting', 'troubleshoot' ),
					'legacy'   => array( 'page' => 'miscellaneous', 'section' => 'Additional Options', 'label' => 'Enable Debugging Mode' ),
				),
				'ec_option_allow_tracking' => array(
					'type'     => 'pills',
					'label'    => __( 'Anonymous usage data', 'wp-easycart' ),
					'desc'     => __( 'Sends WP EasyCart basic, anonymous details about how the store is configured (gateway, shipping type, product count) to help prioritise development. No customer or order data is included.', 'wp-easycart' ),
					'default'  => '0',
					'options'  => array(
						'1'  => __( 'Send', 'wp-easycart' ),
						'-1' => __( 'Don’t send', 'wp-easycart' ),
					),
					'on_save'  => 'wp_easycart_settings_admin_tracking_saved',
					'keywords' => array( 'tracking', 'telemetry', 'privacy', 'statistics' ),
					'legacy'   => array( 'page' => 'miscellaneous', 'section' => 'Additional Options', 'label' => 'Send Anonymous Data' ),
				),
			),
			'actions' => array(
				array(
					'id'       => 'delete_gateway_log',
					'label'    => __( 'Delete the gateway log', 'wp-easycart' ),
					'desc'     => __( 'Removes every stored gateway response and webhook entry. Cannot be undone.', 'wp-easycart' ),
					'button'   => __( 'Delete log', 'wp-easycart' ),
					'confirm'  => __( 'Delete all gateway and webhook log entries? This cannot be undone.', 'wp-easycart' ),
					'danger'   => true,
					'callback' => 'wp_easycart_settings_admin_delete_gateway_log',
					'legacy'   => 'miscellaneous › Additional Options › Delete Log File',
				),
				array(
					'id'       => 'clear_stats',
					'label'    => __( 'Reset view and click counts', 'wp-easycart' ),
					'desc'     => __( 'Sets every product view count and category click count back to zero. Cannot be undone.', 'wp-easycart' ),
					'button'   => __( 'Reset counts', 'wp-easycart' ),
					'confirm'  => __( 'Reset all product view and category click counts to zero? This cannot be undone.', 'wp-easycart' ),
					'danger'   => true,
					'callback' => 'wp_easycart_settings_admin_clear_stats',
					'legacy'   => 'miscellaneous › Additional Options › Clear Stats',
				),
			),
		),
	),
);
