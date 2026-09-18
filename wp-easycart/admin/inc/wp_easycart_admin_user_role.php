<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_user_role' ) ) :

	final class wp_easycart_admin_user_role {

		protected static $_instance = null;

		public static function instance() {

			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;

		}

		public function __construct() {
			include_once( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_catalog_v2.php' );
			include_once( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_user_role_table.php' );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_v2_assets' ), 20 );
		}

		public function load_user_role_list() {
			if ( isset( $_GET['ec_admin_form_action'] ) && 'edit' === $_GET['ec_admin_form_action'] && isset( $_GET['role_id'] ) ) {
				$editor = new wp_easycart_admin_user_role_editor_v2();
				$editor->output();
				return;
			}
			$table = new wp_easycart_admin_user_role_table();
			$table->print_table();
			if ( isset( $_GET['ec_admin_form_action'] ) && 'add-new' === $_GET['ec_admin_form_action'] ) { echo '<script>jQuery( function() { if ( window.ecrole ) { ecrole.new_role(); } } );</script>'; }
		}
		public function is_v2_page() { return isset( $_GET['page'] ) && 'wp-easycart-users' === $_GET['page'] && isset( $_GET['subpage'] ) && 'user-roles' === $_GET['subpage']; }
		public function enqueue_v2_assets() { if ( $this->is_v2_page() ) { include_once( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_catalog_v2.php' ); wp_easycart_admin_catalog_v2_enqueue( ( isset( $_GET['ec_admin_form_action'] ) && 'edit' === $_GET['ec_admin_form_action'] && isset( $_GET['role_id'] ) ) ? 'user-role-editor' : 'user-role-list' ); } }
	}
endif;

function wp_easycart_admin_user_role() {
	return wp_easycart_admin_user_role::instance();
}
wp_easycart_admin_user_role();
