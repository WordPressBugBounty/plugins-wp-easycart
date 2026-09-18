<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_option' ) ) :

	final class wp_easycart_admin_option {

		protected static $_instance = null;


		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		public function __construct() {

			/* Process Admin Messages */
			add_filter( 'wp_easycart_admin_success_messages', array( $this, 'add_success_messages' ) );
			add_filter( 'wp_easycart_admin_error_messages', array( $this, 'add_failure_messages' ) );

			/* Process Form Actions */

			add_action( 'wp_easycart_process_get_form_action', array( $this, 'process_duplicate_option' ) );
			add_action( 'wp_easycart_process_get_form_action', array( $this, 'process_duplicate_optionitem' ) );
			add_action( 'wp_easycart_process_get_form_action', array( $this, 'process_delete_optionitem' ) );
			add_action( 'wp_easycart_process_get_form_action', array( $this, 'process_bulk_delete_optionitem' ) );
			add_action( 'wp_easycart_process_get_form_action', array( $this, 'process_delete_option' ) );
			add_action( 'wp_easycart_process_get_form_action', array( $this, 'process_bulk_delete_option' ) );

			/* V2 list + editor ( option sets ) */
			include_once( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_safe_delete.php' );
			include_once( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_option_table.php' );
			include_once( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_option_editor_v2.php' );
			include_once( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_catalog_v2.php' );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_v2_assets' ), 20 );
		}

		public function is_v2_page() {
			return isset( $_GET['page'] ) && 'wp-easycart-products' === $_GET['page'] && isset( $_GET['subpage'] ) && in_array( $_GET['subpage'], array( 'option', 'optionitems' ), true );
		}

		public function is_v2_editor() {
			return $this->is_v2_page() && ( 'optionitems' === $_GET['subpage'] || ( isset( $_GET['ec_admin_form_action'] ) && 'edit' === $_GET['ec_admin_form_action'] && isset( $_GET['option_id'] ) ) );
		}

		public function enqueue_v2_assets() {
			if ( ! $this->is_v2_page() ) {
				return;
			}
			wp_easycart_admin_catalog_v2_enqueue( $this->is_v2_editor() ? 'option-editor' : 'option-list' );
		}

		public function process_duplicate_option() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) {
				return;
			}

			if ( isset( $_GET['subpage'] ) && 'option' == $_GET['subpage'] && isset( $_GET['ec_admin_form_action'] ) && 'duplicate-option' == $_GET['ec_admin_form_action'] && isset( $_GET['option_id'] ) && ! isset( $_GET['bulk'] ) ) {
				$result = $this->duplicate_option();
				wp_cache_flush();
				wp_easycart_admin()->redirect( 'wp-easycart-products', 'option', $result );
			}
		}

		public function process_duplicate_optionitem() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) {
				return;
			}

			if ( isset( $_GET['subpage'] ) && 'optionitems' == $_GET['subpage'] && isset( $_GET['ec_admin_form_action'] ) && 'duplicate-optionitem' == $_GET['ec_admin_form_action'] && isset( $_GET['optionitem_id'] ) && ! isset( $_GET['bulk'] ) ) {
				$result = $this->duplicate_optionitem();
				wp_cache_flush();
				wp_easycart_admin()->redirect( 'wp-easycart-products', 'optionitems', $result );
			}
		}

		public function process_delete_optionitem() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) {
				return;
			}

			if ( isset( $_GET['subpage'] ) && 'optionitems' == $_GET['subpage'] && isset( $_GET['ec_admin_form_action'] ) && 'delete-optionitem' == $_GET['ec_admin_form_action'] && isset( $_GET['optionitem_id'] ) && ! isset( $_GET['bulk'] ) ) {
				$result = $this->delete_optionitem();
				wp_cache_flush();
				wp_easycart_admin()->redirect( 'wp-easycart-products', 'optionitems', $result );
			}
		}

		public function process_bulk_delete_optionitem() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) {
				return;
			}

			if ( isset( $_GET['subpage'] ) && 'optionitems' == $_GET['subpage'] && isset( $_GET['ec_admin_form_action'] ) && 'delete-optionitem' == $_GET['ec_admin_form_action'] && ! isset( $_GET['optionitem_id'] ) && isset( $_GET['bulk'] ) ) {
				$result = $this->bulk_delete_optionitem();
				wp_cache_flush();
				wp_easycart_admin()->redirect( 'wp-easycart-products', 'optionitems', $result );
			}
		}

		public function process_delete_option() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) {
				return;
			}

			if ( isset( $_GET['subpage'] ) && 'option' == $_GET['subpage'] && isset( $_GET['ec_admin_form_action'] ) && 'delete-option' == $_GET['ec_admin_form_action'] && isset( $_GET['option_id'] ) && ! isset( $_GET['bulk'] ) ) {
				$result = $this->delete_option();
				wp_cache_flush();
				wp_easycart_admin()->redirect( 'wp-easycart-products', 'option', $result );
			}
		}

		public function process_bulk_delete_option() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) {
				return;
			}

			if ( isset( $_GET['subpage'] ) && 'option' == $_GET['subpage'] && isset( $_GET['ec_admin_form_action'] ) && 'delete-option' == $_GET['ec_admin_form_action'] && ! isset( $_GET['option_id'] ) && isset( $_GET['bulk'] ) ) {
				$result = $this->bulk_delete_option();
				wp_cache_flush();
				wp_easycart_admin()->redirect( 'wp-easycart-products', 'option', $result );
			}
		}

		public function add_success_messages( $messages ) {
			if ( isset( $_GET['success'] ) && 'option-inserted' == $_GET['success'] ) {
				$messages[] = __( 'Option successfully created', 'wp-easycart' );
			} else if ( isset( $_GET['success'] ) && 'option-updated' == $_GET['success'] ) {
				$messages[] = __( 'Option successfully updated', 'wp-easycart' );
			} else if ( isset( $_GET['success'] ) && 'option-deleted' == $_GET['success'] ) {
				$messages[] = __( 'Option successfully deleted', 'wp-easycart' );
			} else if ( isset( $_GET['success'] ) && 'option-item-inserted' == $_GET['success'] ) {
				$messages[] = __( 'Option item successfully created', 'wp-easycart' );
			} else if ( isset( $_GET['success'] ) && 'option-item-updated' == $_GET['success'] ) {
				$messages[] = __( 'Option item successfully updated', 'wp-easycart' );
			} else if ( isset( $_GET['success'] ) && 'option-item-deleted' == $_GET['success'] ) {
				$messages[] = __( 'Option item successfully deleted', 'wp-easycart' );
			} else if ( isset( $_GET['success'] ) && 'option-item-duplicated' == $_GET['success'] ) {
				$messages[] = __( 'Option item successfully duplicated', 'wp-easycart' );
			}
			return $messages;
		}

		public function add_failure_messages( $messages ) {
			if ( isset( $_GET['error'] ) && 'option-inserted-error' == $_GET['error'] ) {
				$messages[] = __( 'Option failed to create', 'wp-easycart' );
			} else if ( isset( $_GET['error'] ) && 'option-updated-error' == $_GET['error'] ) {
				$messages[] = __( 'Option failed to update', 'wp-easycart' );
			} else if ( isset( $_GET['error'] ) && 'option-deleted-error' == $_GET['error'] ) {
				$messages[] = __( 'Option failed to delete', 'wp-easycart' );
			} else if ( isset( $_GET['error'] ) && 'option-duplicate' == $_GET['error'] ) {
				$messages[] = __( 'Option failed to create due to duplicate', 'wp-easycart' );
			} else if ( isset( $_GET['error'] ) && 'option-item-duplicate' == $_GET['error'] ) {
				$messages[] = __( 'Option item failed to create due to duplicate', 'wp-easycart' );
			} else if ( isset( $_GET['error'] ) && 'option-item-inserted-error' == $_GET['error'] ) {
				$messages[] = __( 'Option item failed to create', 'wp-easycart' );
			} else if ( isset( $_GET['error'] ) && 'option-item-deleted-error' == $_GET['error'] ) {
				$messages[] = __( 'Option item failed to delete', 'wp-easycart' );
			} else if ( isset( $_GET['error'] ) && 'option-item-duplicate-error' == $_GET['error'] ) {
				$messages[] = __( 'Option item failed to duplicate', 'wp-easycart' );
			}
			return $messages;
		}

		public function load_option_list() {
			if ( isset( $_GET['ec_admin_form_action'] ) && 'edit' === $_GET['ec_admin_form_action'] && isset( $_GET['option_id'] ) ) {
				$editor = new wp_easycart_admin_option_editor_v2();
				$editor->output();
				return;
			}
			/* add-new-option lands here too: the list renders and the V2 slideout auto-opens ( option-set-slideout-v2.js ). */
			$table = new wp_easycart_admin_option_table();
			$table->print_table();
			wp_easycart_admin()->load_new_slideout( 'optionset' );
		}

		/**
		 * Legacy "optionitems" subpage ( item list / item details ) now opens the V2 editor,
		 * which edits the set and all of its choices on one screen.
		 */
		public function load_optionitem_list() {
			if ( ! isset( $_GET['option_id'] ) && isset( $_GET['optionitem_id'] ) ) {
				global $wpdb;
				$_GET['option_id'] = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT option_id FROM ec_optionitem WHERE optionitem_id = %d', (int) $_GET['optionitem_id'] ) );
			}
			$editor = new wp_easycart_admin_option_editor_v2();
			$editor->output();
		}

		/**
		 * Legacy form handlers ( no longer rendered, still routable ): modifier option sets are PRO, so
		 * refuse to create, change or copy them when PRO isn't licensed. Same rule as the V2 editor.
		 *
		 * @since 6.0.0
		 */
		private function modifier_locked( $option_id = 0, $type = null ) {
			if ( ! class_exists( 'wp_easycart_admin_option_editor_v2' ) ) {
				return false;
			}
			if ( null !== $type && wp_easycart_admin_option_editor_v2::is_locked_type( $type ) ) {
				return true;
			}
			return $option_id ? wp_easycart_admin_option_editor_v2::is_locked_option( $option_id ) : false;
		}

		public function insert_option() {
			if ( ! wp_easycart_admin_verification()->verify_access( 'wp-easycart-option-details' ) ) {
				return false;
			}
			if ( $this->modifier_locked( 0, isset( $_POST['option_type'] ) ? sanitize_text_field( wp_unslash( $_POST['option_type'] ) ) : '' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verify_access() above checks the nonce.
				return false;
			}

			global $wpdb;

			$option_name = ( isset( $_POST['option_name'] ) ) ? sanitize_text_field( wp_unslash( $_POST['option_name'] ) ) : '';
			$option_label = ( isset( $_POST['option_label'] ) ) ? wp_easycart_escape_html( wp_unslash( $_POST['option_label'] ) ) : '';
			$option_type = ( isset( $_POST['option_type'] ) ) ? sanitize_text_field( wp_unslash( $_POST['option_type'] ) ) : '';
			$option_error_text = ( isset( $_POST['option_error_text'] ) ) ? wp_easycart_escape_html( wp_unslash( $_POST['option_error_text'] ) ) : '';
			$url_var = ( isset( $_POST['option_meta_url_var'] ) ) ? preg_replace( '/[^a-zA-Z0-9\_]+/', '', sanitize_text_field( wp_unslash( $_POST['option_meta_url_var'] ) ) ) : '';
			$option_meta = array(
				'min' => ( isset( $_POST['option_meta_min'] ) ) ? sanitize_text_field( wp_unslash( $_POST['option_meta_min'] ) ) : '',
				'max' => ( isset( $_POST['option_meta_max'] ) ) ? sanitize_text_field( wp_unslash( $_POST['option_meta_max'] ) ) : '',
				'min_length' => ( isset( $_POST['option_meta_min_length'] ) ) ? sanitize_text_field( wp_unslash( $_POST['option_meta_min_length'] ) ) : '',
				'max_length' => ( isset( $_POST['option_meta_max_length'] ) ) ? sanitize_text_field( wp_unslash( $_POST['option_meta_max_length'] ) ) : '',
				'step' => ( isset( $_POST['option_meta_step'] ) ) ? sanitize_text_field( wp_unslash( $_POST['option_meta_step'] ) ) : '',
				'url_var' => $url_var,
				'swatch_size' => ( isset( $_POST['option_meta_swatch_size'] ) ) ? sanitize_text_field( wp_unslash( $_POST['option_meta_swatch_size'] ) ) : 30,
			);
			$option_required = 0;
			if ( isset( $_POST['option_required'] ) || 'basic-swatch' == $option_type || 'basic-combo' == $option_type ) {
				$option_required = 1;
			}

			$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_option( option_name, option_label, option_type, option_required, option_error_text, option_meta ) VALUES( %s, %s, %s, %d, %s, %s )', $option_name, $option_label, $option_type, $option_required, $option_error_text, maybe_serialize( $option_meta ) ) );
			$option_id = $wpdb->insert_id;

			if ( 'file' == $option_type || 'text' == $option_type || 'number' == $option_type || 'textarea' == $option_type || 'date' == $option_type || 'dimensions1' == $option_type || 'dimensions2' == $option_type ) {
				if ( 'file' == $option_type ) {
					$option_name = 'File Field';
				}
				if ( 'text' == $option_type ) {
					$option_name = 'Text Box Input';
				}
				if ( 'number' == $option_type ) {
					$option_name = 'Number Box Input';
				}
				if ( 'textarea' == $option_type ) {
					$option_name = 'Text Area Input';
				}
				if ( 'date' == $option_type ) {
					$option_name = 'Date Field';
				}
				if ( 'dimensions1' == $option_type ) {
					$option_name = 'DimensionType1';
				}
				if ( 'dimensions2' == $option_type ) {
					$option_name = 'DimensionType2';
				}

				$wpdb->query( $wpdb->prepare( "INSERT INTO ec_optionitem( option_id, optionitem_name, optionitem_price, optionitem_price_onetime, optionitem_price_override, optionitem_weight, optionitem_weight_onetime, optionitem_weight_override, optionitem_order, optionitem_icon, optionitem_initial_value ) VALUES( %d, %s, '0.00', '0.00', '-1', '0.00', '0.00', '-1.00', 1, '', '' )", $option_id, $option_name ) );
			}

			do_action( 'wp_easycart_optionset_created', $option_id );

			return array( 'success' => 'option-inserted' );
		}


		public function duplicate_option() {
			if ( ! wp_easycart_admin_verification()->verify_access( 'wp-easycart-action-duplicate-option' ) ) {
				return false;
			}

			if ( ! isset( $_GET['option_id'] ) ) {
				return false;
			}

			if ( $this->modifier_locked( (int) $_GET['option_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verify_access() above checks the nonce.
				return false;
			}

			global $wpdb;
			$option_id = (int) $_GET['option_id'];

			$original_record = $wpdb->get_row( $wpdb->prepare( 'SELECT ec_option.* FROM ec_option WHERE option_id = %d', $option_id ) );
			$optionitems = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ec_optionitem WHERE option_id = %d', $option_id ) );
			$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_option() VALUES()' ) );
			$new_option_id = $wpdb->insert_id;

			$sql = 'UPDATE ec_option SET ';
			foreach ( $original_record as $key => $value ) {
				if ( 'option_id' != $key && 'square_id' != $key ) {
					$sql .= '`'.$key.'` = ' . $wpdb->prepare( '%s', $value ) .', ';
				}
			}

			$sql = substr( $sql, 0, strlen( $sql ) - 2 );
			$wpdb->query( $sql . $wpdb->prepare( ' WHERE option_id = %d', $new_option_id ) );

			foreach ( $optionitems as $optionitem ) {
				$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_optionitem( option_id ) VALUES( %d )', $new_option_id ) );
				$new_optionitem_id = $wpdb->insert_id;
				$sql = 'UPDATE ec_optionitem SET ';
				foreach ( $optionitem as $key => $value ) {
					if ( $key != 'optionitem_id' && $key != 'option_id' && $key != 'square_id' ) {
						$sql .= '`' . $key . '` = ' . $wpdb->prepare( '%s', $value ) . ', ';
					}
				}
				$sql = substr( $sql, 0, strlen( $sql ) - 2 );
				$wpdb->query( $sql . $wpdb->prepare( ' WHERE optionitem_id = %d', $new_optionitem_id ) );
			}

			do_action( 'wp_easycart_optionset_created', $new_optionitem_id );

			$args = array( 'success' => 'option-duplicated' );

			if ( isset( $_GET['pagenum'] ) ) {
				$args['pagenum'] = (int) $_GET['pagenum'];
			}
			$valid_orderby = array( 'option_name', 'option_type', 'option_id', 'option_required', 'optionitem_name', 'optionitem_price', 'optionitem_weight' );
			if ( isset( $_GET['orderby'] ) && in_array( $_GET['orderby'], $valid_orderby ) ) {
				$args['orderby'] = sanitize_text_field( wp_unslash( $_GET['orderby'] ) );
			}
			if ( isset( $_GET['order'] ) && 'desc' == strtolower( esc_attr( wp_unslash( $_GET['order'] ) ) ) ) {
				$args['order'] = 'desc';
			} else {
				$args['order'] = 'asc';
			}
			return $args;
		}

		public function delete_option() {
			if ( ! wp_easycart_admin_verification()->verify_access( 'wp-easycart-action-delete-option' ) ) {
				return false;
			}

			if ( ! isset( $_GET['option_id'] ) ) {
				return false;
			}

			global $wpdb;

			$option_id = (int) $_GET['option_id'];

			/* Legacy entry point: route through safe-delete so product slots, stock rows and images are cleaned up and the deletion is undoable. */
			wp_easycart_admin_safe_delete()->execute( 'option', $option_id, array( 'strategy' => 'remove' ) );

			$args = array( 'success' => 'option-deleted' );

			if ( isset( $_GET['pagenum'] ) ) {
				$args['pagenum'] = (int) $_GET['pagenum'];
			}
			$valid_orderby = array( 'option_name', 'option_type', 'option_id', 'option_required', 'optionitem_name', 'optionitem_price', 'optionitem_weight' );
			if ( isset( $_GET['orderby'] ) && in_array( $_GET['orderby'], $valid_orderby ) ) {
				$args['orderby'] = sanitize_text_field( wp_unslash( $_GET['orderby'] ) );
			}
			if ( isset( $_GET['order'] ) && 'desc' == strtolower( esc_attr( wp_unslash( $_GET['order'] ) ) ) ) {
				$args['order'] = 'desc';
			} else {
				$args['order'] = 'asc';
			}

			return $args;
		}

		public function bulk_delete_option() {
			if ( ! wp_easycart_admin_verification()->verify_access( 'wp-easycart-bulk-option' ) ) {
				return false;
			}

			if ( ! isset( $_GET['bulk'] ) ) {
				return false;
			}

			global $wpdb;
			$bulk_ids = (array) $_GET['bulk']; // XSS OK. Forced array and each item sanitized.

			foreach ( $bulk_ids as $bulk_id ) {
				wp_easycart_admin_safe_delete()->execute( 'option', (int) $bulk_id, array( 'strategy' => 'remove' ) );
			}

			$args = array( 'success' => 'option-deleted' );

			if ( isset( $_GET['pagenum'] ) ) {
				$args['pagenum'] = (int) $_GET['pagenum'];
			}
			$valid_orderby = array( 'option_name', 'option_type', 'option_id', 'option_required', 'optionitem_name', 'optionitem_price', 'optionitem_weight' );
			if ( isset( $_GET['orderby'] ) && in_array( $_GET['orderby'], $valid_orderby ) ) {
				$args['orderby'] = sanitize_text_field( $_GET['orderby'] );
			}
			if ( isset( $_GET['order'] ) && 'desc' == strtolower( $_GET['order'] ) ) {
				$args['order'] = 'desc';
			} else {
				$args['order'] = 'asc';
			}
			return $args;
		}

		public function duplicate_optionitem() {
			if ( ! wp_easycart_admin_verification()->verify_access( 'wp-easycart-action-duplicate-optionitem' ) ) {
				return false;
			}

			if ( ! isset( $_GET['optionitem_id'] ) ) {
				return false;
			}

			global $wpdb;

			$optionitem_id = (int) $_GET['optionitem_id'];
			$args = array();

			$optionitem = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_optionitem WHERE optionitem_id = %d', $optionitem_id ) );
			if ( ! $optionitem || $this->modifier_locked( (int) $optionitem->option_id ) ) {
				return false;
			}
			$option_id = (int) $optionitem->option_id;
			$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_optionitem( option_id ) VALUES( %d )', $option_id ) );
			$new_optionitem_id = $wpdb->insert_id;
			$sql = 'UPDATE ec_optionitem SET ';
			foreach ( $optionitem as $key => $value ) {
				if ( 'optionitem_id' != $key && 'option_id' != $key && 'square_id' != $key ) {
					$sql .= '`' . $key . '` = ' . $wpdb->prepare( '%s', $value ) . ', ';
				}
			}
			$sql = substr( $sql, 0, strlen( $sql ) - 2 );
			$wpdb->query( $sql . $wpdb->prepare( ' WHERE optionitem_id = %d', $new_optionitem_id ) );
			do_action( 'wp_easycart_optionitem_created', $new_optionitem_id, $option_id );

			$args['option_id'] = (int) $option_id;
			$args['ec_admin_form_action'] = 'edit-optionitem';
			$args['success'] = 'option-item-duplicated';

			if ( isset( $_GET['pagenum'] ) ) {
				$args['pagenum'] = (int) $_GET['pagenum'];
			}
			$valid_orderby = array( 'option_name', 'option_type', 'option_id', 'option_required', 'optionitem_name', 'optionitem_price', 'optionitem_weight' );
			if ( isset( $_GET['orderby'] ) && in_array( $_GET['orderby'], $valid_orderby ) ) {
				$args['orderby'] = sanitize_text_field( wp_unslash( $_GET['orderby'] ) );
			}
			if ( isset( $_GET['order'] ) && 'desc' == strtolower( $_GET['order'] ) ) {
				$args['order'] = 'desc';
			} else {
				$args['order'] = 'asc';
			}
			return $args;
		}

		public function delete_optionitem() {
			if ( ! wp_easycart_admin_verification()->verify_access( 'wp-easycart-action-delete-optionitem' ) ) {
				return false;
			}

			if ( ! isset( $_GET['optionitem_id'] ) ) {
				return false;
			}

			global $wpdb;

			$optionitem_id = (int) $_GET['optionitem_id'];
			$option_id = $wpdb->get_var( $wpdb->prepare( 'SELECT option_id FROM ec_optionitem WHERE optionitem_id = %d', $optionitem_id ) );
			do_action( 'wp_easycart_optionitem_deleting', $optionitem_id, $option_id );
			$wpdb->query( $wpdb->prepare( 'DELETE FROM ec_optionitem WHERE optionitem_id = %d', $optionitem_id ) );
			do_action( 'wp_easycart_optionitem_deleted', $optionitem_id, $option_id );

			$args = array(
				'success' => 'option-item-deleted',
				'option_id' => (int) $option_id,
			);

			if ( isset( $_GET['pagenum'] ) ) {
				$args['pagenum'] = (int) $_GET['pagenum'];
			}
			$valid_orderby = array( 'option_name', 'option_type', 'option_id', 'option_required', 'optionitem_name', 'optionitem_price', 'optionitem_weight' );
			if ( isset( $_GET['orderby'] ) && in_array( $_GET['orderby'], $valid_orderby ) ) {
				$args['orderby'] = sanitize_text_field( wp_unslash( $_GET['orderby'] ) );
			}
			if ( isset( $_GET['order'] ) && 'desc' == strtolower( $_GET['order'] ) ) {
				$args['order'] = 'desc';
			} else {
				$args['order'] = 'asc';
			}
			return $args;
		}

		public function bulk_delete_optionitem() {
			if ( ! wp_easycart_admin_verification()->verify_access( 'wp-easycart-bulk-optionitems' ) ) {
				return false;
			}

			if ( ! isset( $_GET['bulk'] ) ) {
				return false;
			}

			global $wpdb;

			$bulk_ids = (array) $_GET['bulk']; // XSS OK. Forced array and each item sanitized.
			$query_vars = array();

			if ( count( $bulk_ids ) > 0 ) {
				$option_id = $wpdb->get_var( $wpdb->prepare( 'SELECT option_id FROM ec_optionitem WHERE optionitem_id = %d', (int) $bulk_ids[0] ) );
			}

			foreach ( $bulk_ids as $bulk_id ) {
				do_action( 'wp_easycart_optionitem_deleting', (int) $bulk_id, $option_id );
				$wpdb->query( $wpdb->prepare( 'DELETE FROM ec_optionitem WHERE optionitem_id = %d', (int) $bulk_id ) );
				do_action( 'wp_easycart_optionitem_deleted', (int) $bulk_id, $option_id );
			}

			$args = array(
				'success' => 'option-item-deleted',
				'option_id' => (int) $option_id,
			);

			if ( isset( $_GET['pagenum'] ) ) {
				$args['pagenum'] = (int) $_GET['pagenum'];
			}
			$valid_orderby = array( 'option_name', 'option_type', 'option_id', 'option_required', 'optionitem_name', 'optionitem_price', 'optionitem_weight' );
			if ( isset( $_GET['orderby'] ) && in_array( $_GET['orderby'], $valid_orderby ) ) {
				$args['orderby'] = sanitize_text_field( wp_unslash( $_GET['orderby'] ) );
			}
			if ( isset( $_GET['order'] ) && 'desc' == strtolower( $_GET['order'] ) ) {
				$args['order'] = 'desc';
			} else {
				$args['order'] = 'asc';
			}
			return $args;
		}

	}
endif;

function wp_easycart_admin_option() {
	return wp_easycart_admin_option::instance();
}
wp_easycart_admin_option();
