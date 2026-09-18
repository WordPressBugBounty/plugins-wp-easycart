<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_reviews' ) ) :

	final class wp_easycart_admin_reviews{

		protected static $_instance = null;


		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self(  );
			}
			return self::$_instance;
		}

		public function __construct() { 

			/* Process Admin Messages */
			add_filter( 'wp_easycart_admin_success_messages', array( $this, 'add_success_messages' ) );
			add_filter( 'wp_easycart_admin_error_messages', array( $this, 'add_failure_messages' ) );

			/* Process Form Actions */
			add_action( 'wp_easycart_process_get_form_action', array( $this, 'process_approve_review' ) );
			add_action( 'wp_easycart_process_get_form_action', array( $this, 'process_bulk_approve_reviews' ) );
			add_action( 'wp_easycart_process_get_form_action', array( $this, 'process_unapprove_review' ) );
			add_action( 'wp_easycart_process_get_form_action', array( $this, 'process_bulk_unapprove_reviews' ) );
			add_action( 'wp_easycart_process_get_form_action', array( $this, 'process_delete_review' ) );
			add_action( 'wp_easycart_process_get_form_action', array( $this, 'process_bulk_delete_reviews' ) );

			/* V2 list + editor */
			include_once( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_safe_delete.php' );
			include_once( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_catalog_v2.php' );
			include_once( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_review_table.php' );
			include_once( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_review_settings_v2.php' );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_v2_assets' ), 20 );
		}

		public function is_v2_page() { return isset( $_GET['page'] ) && 'wp-easycart-products' === $_GET['page'] && isset( $_GET['subpage'] ) && 'reviews' === $_GET['subpage']; }
		public function is_v2_editor() { return $this->is_v2_page() && isset( $_GET['ec_admin_form_action'] ) && 'edit' === $_GET['ec_admin_form_action'] && isset( $_GET['review_id'] ); }
		public function enqueue_v2_assets() { if ( $this->is_v2_page() ) { wp_easycart_admin_catalog_v2_enqueue( $this->is_v2_editor() ? 'review-editor' : ( $this->is_v2_settings() ? 'review-requests' : 'review-list' ) ); } }

		public function process_approve_review() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) {
				return false;
			}
			if ( isset( $_GET['subpage'] ) && $_GET['subpage'] == 'reviews' && $_GET['ec_admin_form_action'] == 'approve-review' && isset( $_GET['review_id'] ) && !isset( $_GET['bulk'] ) ) {
				if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-action-approve-review' ) ) {
					$result = $this->approve_review();
					wp_cache_delete( 'wpeasycart-reviews' );
					wp_easycart_admin()->redirect( 'wp-easycart-products', 'reviews', $result );
				}
			}
		}

		public function process_bulk_approve_reviews() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) {
				return false;
			}
			if ( isset( $_GET['subpage'] ) && $_GET['subpage'] == 'reviews' && $_GET['ec_admin_form_action'] == 'approve-review' && !isset( $_GET['review_id'] ) && isset( $_GET['bulk'] ) ) {
				if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-bulk-reviews' ) ) {
					$result = $this->bulk_approve_review();
					wp_cache_delete( 'wpeasycart-reviews' );
					wp_easycart_admin()->redirect( 'wp-easycart-products', 'reviews', $result );
				}
			}
		}

		public function process_unapprove_review() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) {
				return false;
			}
			if ( isset( $_GET['subpage'] ) && $_GET['subpage'] == 'reviews' && $_GET['ec_admin_form_action'] == 'unapprove-review' && isset( $_GET['review_id'] ) && !isset( $_GET['bulk'] ) ) {
				if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-action-unapprove-review' ) ) {
					$result = $this->unapprove_review();
					wp_cache_delete( 'wpeasycart-reviews' );
					wp_easycart_admin()->redirect( 'wp-easycart-products', 'reviews', $result );
				}
			}
		}

		public function process_bulk_unapprove_reviews() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) {
				return false;
			}
			if ( isset( $_GET['subpage'] ) && $_GET['subpage'] == 'reviews' && $_GET['ec_admin_form_action'] == 'unapprove-review' && !isset( $_GET['review_id'] ) && isset( $_GET['bulk'] ) ) {
				if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-bulk-reviews' ) ) {
					$result = $this->bulk_unapprove_review();
					wp_cache_delete( 'wpeasycart-reviews' );
					wp_easycart_admin()->redirect( 'wp-easycart-products', 'reviews', $result );
				}
			}
		}

		public function process_delete_review() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) {
				return false;
			}
			if ( isset( $_GET['subpage'] ) && $_GET['subpage'] == 'reviews' && $_GET['ec_admin_form_action'] == 'delete-review' && isset( $_GET['review_id'] ) && !isset( $_GET['bulk'] ) ) {
				if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-action-delete-review' ) ) {
					$result = $this->delete_review();
					wp_cache_delete( 'wpeasycart-reviews' );
					wp_easycart_admin()->redirect( 'wp-easycart-products', 'reviews', $result );
				}
			}
		}

		public function process_bulk_delete_reviews() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) {
				return false;
			}
			if ( isset( $_GET['subpage'] ) && $_GET['subpage'] == 'reviews' && $_GET['ec_admin_form_action'] == 'delete-review' && !isset( $_GET['review_id'] ) && isset( $_GET['bulk'] ) ) {
				if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-bulk-reviews' ) ) {
					$result = $this->bulk_delete_review();
					wp_cache_delete( 'wpeasycart-reviews' );
					wp_easycart_admin()->redirect( 'wp-easycart-products', 'reviews', $result );
				}
			}
		}

		public function add_success_messages( $messages ) {
			if ( isset( $_GET['success'] ) && $_GET['success'] == 'review-updated' ) {
				$messages[] = __( 'Review successfully updated', 'wp-easycart' );
			} else if ( isset( $_GET['success'] ) && $_GET['success'] == 'review-deleted' ) {
				$messages[] = __( 'Review successfully deleted', 'wp-easycart' );
			} else if ( isset( $_GET['success'] ) && $_GET['success'] == 'review-approved' ) {
				$messages[] = __( 'Review(s) successfully approved', 'wp-easycart' );
			} else if ( isset( $_GET['success'] ) && $_GET['success'] == 'review-unapproved' ) {
				$messages[] = __( 'Review(s) successfully denied', 'wp-easycart' );
			}
			return $messages;
		}

		public function add_failure_messages( $messages ) {
			if ( isset( $_GET['error'] ) && $_GET['error'] == 'review-updated-error' ) {
				$messages[] = __( 'Review failed to update', 'wp-easycart' );
			} else if ( isset( $_GET['error'] ) && $_GET['error'] == 'review-deleted-error' ) {
				$messages[] = __( 'Review failed to delete', 'wp-easycart' );
			} else if ( isset( $_GET['error'] ) && $_GET['error'] == 'review-duplicate' ) {
				$messages[] = __( 'Review failed to create due to duplicate', 'wp-easycart' );
			} else if ( isset( $_GET['error'] ) && $_GET['error'] == 'review-approved' ) {
				$messages[] = __( 'There was an issue approving the review(s).', 'wp-easycart' );
			} else if ( isset( $_GET['error'] ) && $_GET['error'] == 'review-unapproved' ) {
				$messages[] = __( 'There was an issue denying the review(s).', 'wp-easycart' );
			}
			return $messages;
		}

		public function load_reviews_list() {
			if ( isset( $_GET['ec_admin_form_action'] ) && 'edit' === $_GET['ec_admin_form_action'] && isset( $_GET['review_id'] ) ) {
				$editor = new wp_easycart_admin_review_editor_v2();
				$editor->output();
				return;
			}
			if ( isset( $_GET['tab'] ) && 'requests' === $_GET['tab'] ) {
				$settings = new wp_easycart_admin_review_settings_v2();
				$settings->output();
				return;
			}
			$table = new wp_easycart_admin_review_table();
			$table->print_table();
		}
		public function is_v2_settings() { return $this->is_v2_page() && isset( $_GET['tab'] ) && 'requests' === $_GET['tab']; }


		public function delete_review() {
			global $wpdb;
			$review_id = (int) $_GET['review_id'];
			$query_vars = array();
			do_action( 'wpeasycart_review_deleting', $review_id );
			$wpdb->query( $wpdb->prepare( "DELETE FROM ec_review WHERE ec_review.review_id = %s", $review_id ) );
			$query_vars['success'] = 'review-deleted';
			return $query_vars;
		}

		public function approve_review() {
			global $wpdb;
			$review_id = (int) $_GET['review_id'];
			$wpdb->query( $wpdb->prepare( "UPDATE ec_review SET approved = 1 WHERE review_id = %d", $review_id ) );
			do_action( 'wpeasycart_review_approved', $review_id );
			$query_vars = array( 'success' => 'review-approved' );
			return $query_vars;
		}

		public function bulk_approve_review() {
			global $wpdb;
			$query_vars = array();
			$bulk_ids = (array) $_GET['bulk']; // XSS OK. Forced array and each item sanitized.

			foreach ( $bulk_ids as $bulk_id ) {
				$wpdb->query( $wpdb->prepare( "UPDATE ec_review SET approved = 1 WHERE review_id = %d", (int) $bulk_id ) );
				do_action( 'wpeasycart_review_approved', (int) $bulk_id );
			}

			$query_vars['success'] = 'review-approved';
			return $query_vars;
		}

		public function unapprove_review() {
			global $wpdb;
			$review_id = (int) $_GET['review_id'];
			$wpdb->query( $wpdb->prepare( "UPDATE ec_review SET approved = 0 WHERE review_id = %d", $review_id ) );
			do_action( 'wpeasycart_review_unapproved', $review_id );
			$query_vars = array( 'success' => 'review-unapproved' );
			return $query_vars;
		}

		public function bulk_unapprove_review() {
			global $wpdb;
			$query_vars = array();
			$bulk_ids = (array) $_GET['bulk']; // XSS OK. Forced array and each item sanitized.

			foreach ( $bulk_ids as $bulk_id ) {
				$wpdb->query( $wpdb->prepare( "UPDATE ec_review SET approved = 0 WHERE review_id = %d", (int) $bulk_id ) );
				do_action( 'wpeasycart_review_unapproved', (int) $bulk_id );
			}

			$query_vars['success'] = 'review-unapproved';
			return $query_vars;
		}

		public function bulk_delete_review() {
			global $wpdb;
			$query_vars = array();
			$bulk_ids = (array) $_GET['bulk']; // XSS OK. Forced array and each item sanitized.

			foreach ( $bulk_ids as $bulk_id ) {
				do_action( 'wpeasycart_review_deleting', (int) $bulk_id );
				$wpdb->query( $wpdb->prepare( "DELETE FROM ec_review WHERE review_id = %d", (int) $bulk_id ) );
			}

			$query_vars['success'] = 'review-deleted';
			return $query_vars;
		}
	}
endif;

function wp_easycart_admin_reviews() {
	return wp_easycart_admin_reviews::instance();
}
wp_easycart_admin_reviews();
