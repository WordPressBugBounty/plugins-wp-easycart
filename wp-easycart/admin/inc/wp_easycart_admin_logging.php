<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_logging' ) ) :

	final class wp_easycart_admin_logging {

		protected static $_instance = null;

		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		public function __construct() {
			include_once( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_log_table.php' );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_v2_assets' ), 20 );
		}

		public function load_log_list() {
			/* V2 viewer; legacy details URLs ( response_id=N ) open the list with that entry expanded */
			include_once( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_catalog_v2.php' );
			include_once( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_log_table.php' );
			$table = new wp_easycart_admin_log_table();
			$table->print_table();
		}
		public function is_v2_page() { return isset( $_GET['page'] ) && 'wp-easycart-settings' === $_GET['page'] && isset( $_GET['subpage'] ) && 'logs' === $_GET['subpage']; }
		public function enqueue_v2_assets() { if ( $this->is_v2_page() ) { include_once( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_catalog_v2.php' ); wp_easycart_admin_catalog_v2_enqueue( 'log-list' ); } }
	}
endif;

function wp_easycart_admin_logging() {
	return wp_easycart_admin_logging::instance();
}
wp_easycart_admin_logging();
