<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_language_editor' ) ) :

	final class wp_easycart_admin_language_editor {

		protected static $_instance = null;

		public $language_file;
		public $language_settings_file;
		public $settings_file;

		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		public function __construct() {
			$this->language_file = EC_PLUGIN_DIRECTORY . '/admin/template/settings/language-editor/language.php';
			$this->language_settings_file = EC_PLUGIN_DIRECTORY . '/admin/template/settings/language-editor/language-settings.php';
			$this->settings_file = EC_PLUGIN_DIRECTORY . '/admin/template/settings/language-editor/settings.php';

			add_action( 'wpeasycart_admin_language_editor_settings', array( $this, 'load_language_editor_settings' ) );
			add_action( 'wpeasycart_admin_language_editor', array( $this, 'load_language_editor' ) );
			add_filter( 'wp_easycart_admin_success_messages', array( $this, 'add_success_messages' ) );

			add_action( 'wp_easycart_process_post_form_action', array( $this, 'process_add_language' ) );
			add_action( 'wp_easycart_process_post_form_action', array( $this, 'process_update_language' ) );
			add_action( 'wp_easycart_process_post_form_action', array( $this, 'process_update_selected_language' ) );
			add_action( 'wp_easycart_process_get_form_action', array( $this, 'process_delete_language' ) );
		}

		public function load_language() {
			include( $this->settings_file );
		}

		public function load_language_editor_settings() {
			include( $this->language_settings_file );
		}

		public function load_language_editor() {
			include( $this->language_file );
		}

		public function add_success_messages( $messages ){
			if ( isset( $_GET['success'] ) && $_GET['success'] == 'language-added' ) {
				$messages[] = __( 'The new language was added successfully.', 'wp-easycart' );
			} else if ( isset( $_GET['success'] ) && $_GET['success'] == 'language-updated' ) {
				$messages[] = __( 'The language was updated successfully.', 'wp-easycart' );
			} else if ( isset( $_GET['success'] ) && $_GET['success'] == 'language-deleted' ) {
				$messages[] = __( 'The language was deleted.', 'wp-easycart' );
			}
			return $messages;
		}

		public function process_add_language() {
			if ( ! wp_easycart_admin_verification()->verify_access( 'wp-easycart-add-language' ) ) {
				return false;
			}
			if ( isset( $_POST['ec_admin_form_action'] ) && 'add-new-language' == $_POST['ec_admin_form_action'] && isset( $_POST['ec_option_add_language'] ) ) {
				wp_easycart_language( )->add_new_language( sanitize_key( $_POST['ec_option_add_language'] ) );
				wp_cache_flush();
				$args = array(
					'success' => 'language-added',
				);
				wp_easycart_admin()->redirect( 'wp-easycart-settings', 'language-editor', $args );
			}
		}

		public function process_update_language() {
			if ( ! wp_easycart_admin_verification()->verify_access( 'wp-easycart-update-language' ) ) {
				return false;
			}
			if ( isset( $_POST['ec_admin_form_action'] ) && 'update-language' == $_POST['ec_admin_form_action'] ) {
				wp_easycart_language()->update_language_data();
				wp_cache_flush();
				$args = array(
					'success' => 'language-updated',
				);
				wp_easycart_admin()->redirect( 'wp-easycart-settings', 'language-editor', $args );
			}
		}

		public function process_update_selected_language() {
			if ( ! wp_easycart_admin_verification()->verify_access( 'wp-easycart-update-language' ) ) {
				return false;
			}
			if ( isset( $_POST['ec_admin_form_action'] ) && 'update-selected-language' == $_POST['ec_admin_form_action'] && isset( $_POST['ec_option_language'] ) ) {
				update_option( 'ec_option_language', sanitize_key( $_POST['ec_option_language'] ) );
				wp_easycart_language()->update_language_data();
				wp_cache_flush();
				$args = array(
					'success' => 'language-updated',
				);
				wp_easycart_admin()->redirect( 'wp-easycart-settings', 'language-editor', $args );
			}
		}

		public function process_delete_language() {
			if ( ! isset( $_GET['ec_language'] ) ) {
				return false;
			}
			if ( ! wp_easycart_admin_verification()->verify_access( 'wp-easycart-delete-language-' . sanitize_key( $_GET['ec_language'] ) ) ) {
				return false;
			}
			if ( isset( $_GET['ec_admin_form_action'] ) && 'delete-language' == $_GET['ec_admin_form_action'] ) {
				wp_easycart_language( )->remove_language( sanitize_key( $_GET['ec_language'] ) );
				wp_cache_flush();
				$args = array(
					'success' => 'language-deleted',
				);
				wp_easycart_admin()->redirect( 'wp-easycart-settings', 'language-editor', $args );
			}
		}
	}
endif;

function wp_easycart_admin_language_editor() {
	return wp_easycart_admin_language_editor::instance();
}
wp_easycart_admin_language_editor();
