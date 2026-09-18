<?php
do_action( 'wpeasycart_admin_load_init' );
// Load Helper Classes 
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_shell_theme.php' );
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_upsell.php' );
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_verification.php' );
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_edition.php' );
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_pro_gate.php' );
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_compat_lock.php' );
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_settings_icons.php' );
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_settings_registry.php' );
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_settings_page_v2.php' );
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_settings_home.php' );
// The Language settings page saves phrases through its own AJAX handlers ( ecv2_language_* ), so it must also load on admin-ajax requests.
include_once( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_language_v2.php' );
// The Payment settings page loads gateway forms and switches gateways through its own AJAX handlers ( ecv2_payment_* ), so it must also load on admin-ajax requests.
include_once( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_payment_v2.php' );
// The Shipping settings page edits zones through its own AJAX handlers ( ecv2_shipping_zone_* ), so it must also load on admin-ajax requests.
include_once( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_shipping_settings_v2.php' );
// The Taxes settings page edits its rate tables, VAT country rates and the Canada grid through its own AJAX handlers ( ecv2_tax_* ), so it must also load on admin-ajax requests.
include_once( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_tax_v2.php' );
// The Shipping rates settings page edits ec_shippingrate rows through its own AJAX handlers ( ecv2_shipping_rate_* ), so it must also load on admin-ajax requests.
include_once( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_shipping_rates_v2.php' );
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_email_health.php' );
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_abandoned_cart_status.php' );
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_actions.php' );
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_category.php' );
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_order_statuses.php' );
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_details.php' );
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_downloads.php' );
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_logging.php' );
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_initial_setup.php' );
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_setup_wizard.php' );

include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_inventory.php' );
	
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_manufacturers.php' );

include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_pricepoint.php' );
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_perpage.php' );
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_country.php' );
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_states.php' );
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_giftcards.php' );
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_cart_links.php' );

// Menus and reviews register their own AJAX handlers ( ecv2_menu_*, ecv2_review_* ), so they must also load on admin-ajax requests.
$wp_easycart_is_ajax = defined( 'DOING_AJAX' ) && DOING_AJAX;
if( $wp_easycart_is_ajax || ( isset( $_GET['page'] ) && isset( $_GET['subpage'] ) && $_GET['page'] == 'wp-easycart-products' && ( $_GET['subpage'] == 'menus' || $_GET['subpage'] == 'submenus' || $_GET['subpage'] == 'subsubmenus' ) ) )
	include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_menus.php' );

include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_miscellaneous.php' );
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_option.php' );
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_orders.php' );
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_payments.php' );
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_preloader.php' );
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_products.php' );

if( $wp_easycart_is_ajax || ( isset( $_GET['page'] ) && isset( $_GET['subpage'] ) && $_GET['page'] == 'wp-easycart-products' && $_GET['subpage'] == 'reviews' ) )
	include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_reviews.php' );

include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_subscriptions.php' );

if( isset( $_GET['page'] ) && isset( $_GET['subpage'] ) && $_GET['page'] == 'wp-easycart-products' && $_GET['subpage'] == 'subscriptionplans' )
	include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_subscription_plans.php' );

include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_table.php' );
// include_once: the V2 catalog table classes load this base themselves ( they extend it and are included from constructors above ).
include_once( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_table_v2.php' );
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_inventory_table.php' );
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_product_table.php' );
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_product_import.php' );
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_order_table.php' );
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_user_table.php' );
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_details_user.php' );
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_details_user_v2.php' );
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_users.php' );
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_user_role.php' );
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_subscribers.php' );
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_cart_importer.php' ); 
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_online_docs.php' ); 
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_abandon_cart.php' ); 
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_store_status.php' ); 
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_registration.php' ); 
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_coupons.php' ); 
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_promotions.php' );
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_offers.php' );
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_extensions.php' );
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_shortcodes.php' );
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_fee.php' );
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_schedule.php' );
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_location.php' );

include( EC_PLUGIN_DIRECTORY . '/admin/gutenberg/class-wp-easycart-gutenberg.php' );

if( get_option( 'ec_option_allow_tracking' ) && get_option( 'ec_option_allow_tracking' ) == '1' ){
	include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_tracking.php' );
}

// Load Main Files Last
include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin.php' );
//include( EC_PLUGIN_DIRECTORY . '/inc/admin/admin_ajax_functions.php' );
do_action( 'wpeasycart_admin_load_complete' );
?>