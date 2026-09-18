<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_perpage' ) ) :

	final class wp_easycart_admin_perpage {

		protected static $_instance = null;

		public static function instance() {

			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;

		}

		public function __construct() { 
			include_once( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_perpage_v2.php' );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_v2_assets' ), 20 );
			add_filter( 'wp_easycart_admin_success_messages', array( $this, 'add_success_messages' ) );
			add_action( 'wp_easycart_process_post_form_action', array( $this, 'process_add_new_perpage' ) );
			add_action( 'wp_easycart_process_post_form_action', array( $this, 'process_update_perpage' ) );
			add_action( 'wp_easycart_process_get_form_action', array( $this, 'process_delete_perpage' ) );
			add_action( 'wp_easycart_process_get_form_action', array( $this, 'process_bulk_delete_perpages' ) );
		}

		public function process_add_new_perpage() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_settings' ) ) {
				return;
			}

			if ( isset( $_POST['ec_admin_form_action'] ) && 'add-new-perpage' == $_POST['ec_admin_form_action'] ) {
				$result = $this->insert_perpage();
				wp_cache_delete( 'wpeasycart-perpages' );
				wp_easycart_admin()->redirect( 'wp-easycart-settings', 'perpage', $result );
			}
		}

		public function process_update_perpage() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_settings' ) ) {
				return;
			}

			if ( isset( $_POST['ec_admin_form_action'] ) && 'update-perpage' == $_POST['ec_admin_form_action'] ) {
				$result = $this->update_perpage();
				wp_cache_delete( 'wpeasycart-perpages' );
				wp_easycart_admin()->redirect( 'wp-easycart-settings', 'perpage', $result );
			}
		}
		public function process_delete_perpage() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_settings' ) ) {
				return;
			}

			if ( isset( $_GET['ec_admin_form_action'] ) && 'delete-perpage' == $_GET['ec_admin_form_action'] && isset( $_GET['perpage_id'] ) && ! isset( $_GET['bulk'] ) ) {
				$result = $this->delete_perpage();
				wp_cache_delete( 'wpeasycart-perpages' );
				wp_easycart_admin()->redirect( 'wp-easycart-settings', 'perpage', $result );
			}
		}
		public function process_bulk_delete_perpages() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_settings' ) ) {
				return;
			}

			if ( isset( $_GET['ec_admin_form_action'] ) && 'delete-perpage' == $_GET['ec_admin_form_action'] && ! isset( $_GET['perpage_id'] ) && isset( $_GET['bulk'] ) ) {
				$result = $this->bulk_delete_perpage();
				wp_cache_delete( 'wpeasycart-perpages' );
				wp_easycart_admin()->redirect( 'wp-easycart-settings', 'perpage', $result );
			}
		}

		public function add_success_messages( $messages ) {
			if ( isset( $_GET['success'] ) && 'perpage-inserted' == $_GET['success'] ) {
				$messages[] = __( 'Per page successfully created', 'wp-easycart' );
			} else if ( isset( $_GET['success'] ) && 'perpage-updated' == $_GET['success'] ) {
				$messages[] = __( 'Per page successfully updated', 'wp-easycart' );
			} else if ( isset( $_GET['success'] ) && 'perpage-deleted' == $_GET['success'] ) {
				$messages[] = __( 'Per page successfully deleted', 'wp-easycart' );
			}
			return $messages;
		}

		public function load_perpage_list() {
			/* V2: one screen replaces list + details; legacy edit/add URLs land here ( edit highlights the row ) */
			include_once( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_catalog_v2.php' );
			include_once( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_perpage_v2.php' );
			$screen = new wp_easycart_admin_perpage_v2();
			$screen->output();
		}
		public function is_v2_page() { return isset( $_GET['page'] ) && 'wp-easycart-settings' === $_GET['page'] && isset( $_GET['subpage'] ) && 'perpage' === $_GET['subpage']; }
		public function enqueue_v2_assets() { if ( $this->is_v2_page() ) { include_once( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_catalog_v2.php' ); wp_easycart_admin_catalog_v2_enqueue( 'perpage' ); } }

		public function insert_perpage() {
			if ( ! wp_easycart_admin_verification()->verify_access( 'wp-easycart-perpage-details' ) ) {
				return false;
			}

			global $wpdb;
			$perpage = (int) $_POST['perpage'];
			$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_perpage( perpage ) VALUES( %d )', $perpage ) );
			return array( 'success' => 'perpage-inserted' );
		}

		public function update_perpage() {	
			if ( ! wp_easycart_admin_verification()->verify_access( 'wp-easycart-perpage-details' ) ) {
				return false;
			}

			global $wpdb;
			$perpage_id = (int) $_POST['perpage_id'];			
			$perpage = (int) $_POST['perpage'];
			$wpdb->query( $wpdb->prepare( 'UPDATE ec_perpage SET perpage = %d WHERE perpage_id = %d', $perpage, $perpage_id ) );
			return array( 'success' => 'perpage-updated' );
		}

		public function delete_perpage() {
			if ( ! wp_easycart_admin_verification()->verify_access( 'wp-easycart-action-delete-perpage' ) ) {
				return false;
			}

			global $wpdb;
			$perpage_id = (int) $_GET['perpage_id'];		
			$wpdb->query( $wpdb->prepare( 'DELETE FROM ec_perpage WHERE perpage_id = %d', $perpage_id ) );
			return array( 'success' => 'perpage-deleted' );
		}

		public function bulk_delete_perpage() {
			if ( ! wp_easycart_admin_verification()->verify_access( 'wp-easycart-bulk-perpage' ) ) {
				return false;
			}

			global $wpdb;
			$bulk_ids = (array) $_GET['bulk']; // XSS OK. Forced array and each item sanitized.

			foreach ( $bulk_ids as $bulk_id ) {
				$wpdb->query( $wpdb->prepare( 'DELETE FROM ec_perpage WHERE perpage_id = %d', (int) $bulk_id ) );
			}
			return array( 'success' => 'perpage-deleted' );
		}
	}
endif;

function wp_easycart_admin_perpage() {
	return wp_easycart_admin_perpage::instance();
}
wp_easycart_admin_perpage();
