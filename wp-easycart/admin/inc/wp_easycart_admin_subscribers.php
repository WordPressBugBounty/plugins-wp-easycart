<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_subscribers' ) ) :

	final class wp_easycart_admin_subscribers {

		protected static $_instance = null;

		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		public function __construct() {
			include_once( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_catalog_v2.php' );
			include_once( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_subscriber_table.php' );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_v2_assets' ), 20 );
		}

		public function load_subscriber_list() {
			/* 6.0.1: after a delete the list reloads onto the shared Undo bar, like orders, products and customers. */
			if ( class_exists( 'wp_easycart_admin_undo' ) ) {
				wp_easycart_admin_undo::maybe_print_bar( __( 'Subscriber deleted.', 'wp-easycart' ) );
			}
			/* No details page: legacy edit URLs open the list with the row highlighted; add-new opens the drawer */
			$table = new wp_easycart_admin_subscriber_table();
			$table->print_table();
			if ( isset( $_GET['ec_admin_form_action'] ) && 'add-new' === $_GET['ec_admin_form_action'] ) { echo '<script>jQuery( function() { if ( window.ecsub ) { ecsub.add(); } } );</script>'; }
		}
		public function is_v2_page() { return isset( $_GET['page'] ) && 'wp-easycart-users' === $_GET['page'] && isset( $_GET['subpage'] ) && 'subscribers' === $_GET['subpage']; }
		public function enqueue_v2_assets() { if ( $this->is_v2_page() ) { include_once( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_catalog_v2.php' ); wp_easycart_admin_catalog_v2_enqueue( 'subscribers' ); } }
	}
endif;

function wp_easycart_admin_subscribers() {
	return wp_easycart_admin_subscribers::instance();
}
wp_easycart_admin_subscribers();
