<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_miscellaneous' ) ) :

	final class wp_easycart_admin_miscellaneous {

		protected static $_instance = null;

		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		public function __construct() {
			/* The Additional settings page is a V2 declaration ( admin/template/settings/admin.php ); this class
			 * keeps the usage-tracking GET actions and the tools that declaration calls ( clear_stats, delete_gateway_log ). */
			add_filter( 'wp_easycart_admin_success_messages', array( $this, 'add_success_messages' ) );
			add_action( 'wp_easycart_process_get_form_action', array( $this, 'process_enable_usage_tracking' ) );
			add_action( 'wp_easycart_process_get_form_action', array( $this, 'process_disable_usage_tracking' ) );
		}

		public function process_enable_usage_tracking() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_settings' ) ) {
				return;
			}

			if ( $_GET['ec_admin_form_action'] == "allow-usage-tracking" ) {
				if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-enable-usage-tracking' ) ) {
					update_option( 'ec_option_allow_tracking', '1' );
					if ( !function_exists( 'wp_easycart_admin_tracking' ) ) {
						include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_tracking.php' );
					}
					do_action( 'wpeasycart_admin_usage_tracking_accepted' );
					wp_easycart_admin()->redirect( 'wp-easycart-settings', 'initial-setup', array( 'success' => 'tracking-enabled' ) );
				}
			}
		}

		public function process_disable_usage_tracking() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_settings' ) ) {
				return;
			}

			if ( $_GET['ec_admin_form_action'] == "deny-usage-tracking" ) {
				if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-disable-usage-tracking' ) ) {
					update_option( 'ec_option_allow_tracking', '-1' );
					wp_easycart_admin()->redirect( 'wp-easycart-settings', 'miscellaneous', array( 'success' => 'tracking-disabled' ) );
				}
			}
		}

		public function add_success_messages( $messages ) {
			if ( isset( $_GET['success'] ) && $_GET['success'] == 'tracking-enabled' ) {
				$messages[] = __( 'Thank you for enabling usage data, we really appreciate it!', 'wp-easycart' );
			} else if ( isset( $_GET['success'] ) && $_GET['success'] == 'tracking-disabled' ) {
				$messages[] = __( 'Usage data has been disabled. If you change your mind you can always enable it here in the additional settings.', 'wp-easycart' );
			}
			return $messages;
		}

		public function clear_stats() {
			global $wpdb;
			$results = $wpdb->query( $wpdb->prepare( 'UPDATE ec_menulevel1, ec_menulevel2, ec_menulevel3 SET ec_menulevel1.clicks = 0, ec_menulevel2.clicks = 0, ec_menulevel3.clicks = 0' ) );
			$results = $wpdb->query( $wpdb->prepare( 'UPDATE ec_product SET ec_product.views = 0' ) );
		}

		public function delete_gateway_log() {
			global $wpdb;
			$wpdb->query( 'DELETE FROM ec_webhook' );
			$wpdb->query( 'DELETE FROM ec_response' );
		}
	}
endif; // End if class_exists check

function wp_easycart_admin_miscellaneous() {
	return wp_easycart_admin_miscellaneous::instance();
}
wp_easycart_admin_miscellaneous();
