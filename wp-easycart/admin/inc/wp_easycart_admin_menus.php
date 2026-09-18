<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'wp_easycart_admin_menus' ) ) :
	final class wp_easycart_admin_menus {
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

			add_action( 'wp_easycart_process_get_form_action', array( $this, 'process_delete_menulevel1' ) );
			add_action( 'wp_easycart_process_get_form_action', array( $this, 'process_delete_menulevel2' ) );
			add_action( 'wp_easycart_process_get_form_action', array( $this, 'process_delete_menulevel3' ) );
			add_action( 'wp_easycart_process_get_form_action', array( $this, 'process_bulk_delete_menulevel1' ) );
			add_action( 'wp_easycart_process_get_form_action', array( $this, 'process_bulk_delete_menulevel2' ) );
			add_action( 'wp_easycart_process_get_form_action', array( $this, 'process_bulk_delete_menulevel3' ) );
			/* V2 list + editor */
			include_once( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_safe_delete.php' );
			include_once( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_catalog_v2.php' );
			include_once( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_menu_table.php' );
			include_once( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_menu_editor_v2.php' );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_v2_assets' ), 20 );
		}

		public function is_v2_page() {
			return isset( $_GET['page'] ) && 'wp-easycart-products' === $_GET['page'] && isset( $_GET['subpage'] ) && in_array( $_GET['subpage'], array( 'menus', 'submenus', 'subsubmenus' ), true );
		}
		public function is_v2_editor() {
			return $this->is_v2_page() && isset( $_GET['ec_admin_form_action'] ) && 'edit' === $_GET['ec_admin_form_action'];
		}
		public function enqueue_v2_assets() {
			if ( $this->is_v2_page() ) { wp_easycart_admin_catalog_v2_enqueue( $this->is_v2_editor() ? 'menu-editor' : 'menu-list' ); }
		}


		public function process_delete_menulevel1() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) {
				return;
			}

			if ( isset( $_GET['subpage'] ) && $_GET['subpage'] == 'menus' && $_GET['ec_admin_form_action'] == 'delete-menulevel1' && isset( $_GET['menulevel1_id'] ) && !isset( $_GET['bulk'] ) ) {
				if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-action-delete-menulevel1' ) ) {
					$result = $this->delete_menulevel1();
					wp_cache_delete( 'wpeasycart-get-menu-items', 'wpeasycart-menu' );
					wp_easycart_admin()->redirect( 'wp-easycart-products', 'menus', $result );
				}
			}
		}

		public function process_delete_menulevel2() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) {
				return;
			}

			if ( isset( $_GET['subpage'] ) && $_GET['subpage'] == 'submenus' && $_GET['ec_admin_form_action'] == 'delete-menulevel2' && isset( $_GET['menulevel2_id'] ) && !isset( $_GET['bulk'] ) ) {
				if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-action-delete-menulevel2' ) ) {
					$result = $this->delete_menulevel2();
					wp_cache_delete( 'wpeasycart-get-menu-items', 'wpeasycart-menu' );
					wp_easycart_admin()->redirect( 'wp-easycart-products', 'submenus', $result );
				}
			}
		}

		public function process_delete_menulevel3() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) {
				return;
			}

			if ( isset( $_GET['subpage'] ) && $_GET['subpage'] == 'subsubmenus' && $_GET['ec_admin_form_action'] == 'delete-menulevel3' && isset( $_GET['menulevel3_id'] ) && !isset( $_GET['bulk'] ) ) {
				if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-action-delete-menulevel3' ) ) {
					$result = $this->delete_menulevel3();
					wp_cache_delete( 'wpeasycart-get-menu-items', 'wpeasycart-menu' );
					wp_easycart_admin()->redirect( 'wp-easycart-products', 'subsubmenus', $result );
				}
			}
		}

		public function process_bulk_delete_menulevel1() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) {
				return;
			}

			if ( isset( $_GET['subpage'] ) && $_GET['subpage'] == 'menus' && $_GET['ec_admin_form_action'] == 'delete-menulevel1' && isset( $_GET['bulk'] ) ) {
				if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-bulk-menus' ) ) {
					$result = $this->bulk_delete_menulevel1();
					wp_cache_delete( 'wpeasycart-get-menu-items', 'wpeasycart-menu' );
					wp_easycart_admin()->redirect( 'wp-easycart-products', 'menus', $result );
				}
			}
		}

		public function process_bulk_delete_menulevel2() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) {
				return;
			}

			if ( isset( $_GET['subpage'] ) && $_GET['subpage'] == 'submenus' && $_GET['ec_admin_form_action'] == 'delete-menulevel2' && isset( $_GET['bulk'] ) ) {
				if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-bulk-submenus' ) ) {
					$result = $this->bulk_delete_menulevel2();
					wp_cache_delete( 'wpeasycart-get-menu-items', 'wpeasycart-menu' );
					wp_easycart_admin()->redirect( 'wp-easycart-products', 'submenus', $result );
				}
			}
		}

		public function process_bulk_delete_menulevel3() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) {
				return;
			}

			if ( isset( $_GET['subpage'] ) && $_GET['subpage'] == 'subsubmenus' && $_GET['ec_admin_form_action'] == 'delete-menulevel3' && isset( $_GET['bulk'] ) ) {
				if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-bulk-subsubmenus' ) ) {
					$result = $this->bulk_delete_menulevel3();
					wp_cache_delete( 'wpeasycart-get-menu-items', 'wpeasycart-menu' );
					wp_easycart_admin()->redirect( 'wp-easycart-products', 'subsubmenus', $result );
				}
			}
		}

		public function add_success_messages( $messages ) {
			if ( isset( $_GET['success'] ) && $_GET['success'] == 'menulevel1-inserted' ) {
				$messages[] = __( 'Menu successfully created', 'wp-easycart' );
			} else if ( isset( $_GET['success'] ) && $_GET['success'] == 'menulevel1-updated' ) {
				$messages[] = __( 'Menu successfully updated', 'wp-easycart' );
			} else if ( isset( $_GET['success'] ) && $_GET['success'] == 'menulevel1-deleted' ) {
				$messages[] = __( 'Menu successfully deleted', 'wp-easycart' );
			} else if ( isset( $_GET['success'] ) && $_GET['success'] == 'menulevel2-inserted' ) {
				$messages[] = __( 'Sub-Menu successfully created', 'wp-easycart' );
			} else if ( isset( $_GET['success'] ) && $_GET['success'] == 'menulevel2-updated' ) {
				$messages[] = __( 'Sub-Menu successfully updated', 'wp-easycart' );
			} else if ( isset( $_GET['success'] ) && $_GET['success'] == 'menulevel2-deleted' ) {
				$messages[] = __( 'Sub-Menu successfully deleted', 'wp-easycart' );
			} else if ( isset( $_GET['success'] ) && $_GET['success'] == 'menulevel3-inserted' ) {
				$messages[] = __( 'Sub-Sub-Menu successfully created', 'wp-easycart' );
			} else if ( isset( $_GET['success'] ) && $_GET['success'] == 'menulevel3-updated' ) {
				$messages[] = __( 'Sub-Sub-Menu successfully updated', 'wp-easycart' );
			} else if ( isset( $_GET['success'] ) && $_GET['success'] == 'menulevel3-deleted' ) {
				$messages[] = __( 'Sub-Sub-Menu successfully deleted', 'wp-easycart' );
			}
			return $messages;
		}

		public function add_failure_messages( $messages ) {
			if ( isset( $_GET['error'] ) && $_GET['error'] == 'menulevel1-inserted-error' ) {
				$messages[] = __( 'Menu failed to create', 'wp-easycart' );
			} else if ( isset( $_GET['error'] ) && $_GET['error'] == 'menulevel1-updated-error' ) {
				$messages[] = __( 'Menu failed to update', 'wp-easycart' );
			} else if ( isset( $_GET['error'] ) && $_GET['error'] == 'menulevel1-deleted-error' ) {
				$messages[] = __( 'Menu failed to delete', 'wp-easycart' );
			} else if ( isset( $_GET['error'] ) && $_GET['error'] == 'menulevel1-duplicate' ) {
				$messages[] = __( 'Menu failed to create due to duplicate', 'wp-easycart' );
			} else if ( isset( $_GET['error'] ) && $_GET['error'] == 'menulevel2-inserted-error' ) {
				$messages[] = __( 'Sub-Menu failed to create', 'wp-easycart' );
			} else if ( isset( $_GET['error'] ) && $_GET['error'] == 'menulevel2-updated-error' ) {
				$messages[] = __( 'Sub-Menu failed to update', 'wp-easycart' );
			} else if ( isset( $_GET['error'] ) && $_GET['error'] == 'menulevel2-deleted-error' ) {
				$messages[] = __( 'Sub-Menu failed to delete', 'wp-easycart' );
			} else if ( isset( $_GET['error'] ) && $_GET['error'] == 'menulevel2-duplicate' ) {
				$messages[] = __( 'Sub-Menu failed to create due to duplicate', 'wp-easycart' );
			} else if ( isset( $_GET['error'] ) && $_GET['error'] == 'menulevel3-inserted-error' ) {
				$messages[] = __( 'Sub-Sub-Menu failed to create', 'wp-easycart' );
			} else if ( isset( $_GET['error'] ) && $_GET['error'] == 'menulevel3-updated-error' ) {
				$messages[] = __( 'Sub-Sub-Menu failed to update', 'wp-easycart' );
			} else if ( isset( $_GET['error'] ) && $_GET['error'] == 'menulevel3-deleted-error' ) {
				$messages[] = __( 'Sub-Sub-Menu failed to delete', 'wp-easycart' );
			} else if ( isset( $_GET['error'] ) && $_GET['error'] == 'menulevel3-duplicate' ) {
				$messages[] = __( 'Sub-Sub-Menu failed to create due to duplicate', 'wp-easycart' );
			}
			return $messages;
		}

		public function load_menus_list() {
			if ( isset( $_GET['ec_admin_form_action'] ) && 'edit' === $_GET['ec_admin_form_action'] ) {
				/* New URLs carry level + menu_id; legacy edit links carry menulevelN_id */
				if ( ! isset( $_GET['menu_id'] ) ) {
					for ( $l = 3; $l >= 1; $l-- ) { if ( isset( $_GET[ 'menulevel' . $l . '_id' ] ) ) { $_GET['level'] = $l; $_GET['menu_id'] = (int) $_GET[ 'menulevel' . $l . '_id' ]; break; } }
				}
				$editor = new wp_easycart_admin_menu_editor_v2();
				$editor->output();
				return;
			}
			$table = new wp_easycart_admin_menu_table();
			$table->print_table();
			if ( isset( $_GET['ec_admin_form_action'] ) && 0 === strpos( $_GET['ec_admin_form_action'], 'add-new-menulevel' ) ) {
				$parent = isset( $_GET['menulevel1_id'] ) ? '1:' . (int) $_GET['menulevel1_id'] : ( isset( $_GET['menulevel2_id'] ) ? '2:' . (int) $_GET['menulevel2_id'] : '' );
				echo '<script>jQuery( function() { if ( window.ecv2_catalog ) { ecv2_catalog.new_menu( ' . wp_json_encode( $parent ) . ', 0 ); } } );</script>';
			}
		}

		/** Legacy sub-menu list URLs land on the same unified tree. */
		public function load_submenus_list() {
			if ( isset( $_GET['ec_admin_form_action'] ) && 'edit' === $_GET['ec_admin_form_action'] && isset( $_GET['menulevel2_id'] ) ) { $_GET['level'] = 2; $_GET['menu_id'] = (int) $_GET['menulevel2_id']; }
			$this->load_menus_list();
		}
		public function load_subsubmenus_list() {
			if ( isset( $_GET['ec_admin_form_action'] ) && 'edit' === $_GET['ec_admin_form_action'] && isset( $_GET['menulevel3_id'] ) ) { $_GET['level'] = 3; $_GET['menu_id'] = (int) $_GET['menulevel3_id']; }
			$this->load_menus_list();
		}


		public function delete_menulevel1() {
			if ( !wp_easycart_admin_verification()->verify_access( 'wp-easycart-action-delete-menulevel1' ) ) {
				return false;
			}

			$id = (int) $_GET['menulevel1_id'];
			$report = wp_easycart_admin_safe_delete()->analyze( 'menu1', $id );
			$strategy = 'remove';
			if ( ! is_wp_error( $report ) ) { foreach ( $report['strategies'] as $st ) { if ( 'cascade' === $st['key'] ) { $strategy = 'cascade'; } } }
			wp_easycart_admin_safe_delete()->execute( 'menu1', $id, array( 'strategy' => $strategy, 'redirect' => true ) );

			return array( 'success' => 'menulevel1-deleted' );
		}

		public function bulk_delete_menulevel1() {
			if ( !wp_easycart_admin_verification()->verify_access( 'wp-easycart-bulk-menus' ) ) {
				return false;
			}

			foreach ( (array) $_GET['bulk'] as $bulk_id ) {
				$report = wp_easycart_admin_safe_delete()->analyze( 'menu1', (int) $bulk_id );
				if ( is_wp_error( $report ) ) { continue; }
				$strategy = 'remove';
				foreach ( $report['strategies'] as $st ) { if ( 'cascade' === $st['key'] ) { $strategy = 'cascade'; } }
				wp_easycart_admin_safe_delete()->execute( 'menu1', (int) $bulk_id, array( 'strategy' => $strategy, 'redirect' => true ) );
			}

			return array( 'success' => 'menulevel1-deleted' );
		}

		public function delete_menulevel2() {
			if ( !wp_easycart_admin_verification()->verify_access( 'wp-easycart-action-delete-menulevel2' ) ) {
				return false;
			}

			$id = (int) $_GET['menulevel2_id'];
			$report = wp_easycart_admin_safe_delete()->analyze( 'menu2', $id );
			$strategy = 'remove';
			if ( ! is_wp_error( $report ) ) { foreach ( $report['strategies'] as $st ) { if ( 'cascade' === $st['key'] ) { $strategy = 'cascade'; } } }
			wp_easycart_admin_safe_delete()->execute( 'menu2', $id, array( 'strategy' => $strategy, 'redirect' => true ) );

			return array( 'success' => 'menulevel2-deleted' );
		}

		public function bulk_delete_menulevel2() {
			if ( !wp_easycart_admin_verification()->verify_access( 'wp-easycart-bulk-submenus' ) ) {
				return false;
			}

			foreach ( (array) $_GET['bulk'] as $bulk_id ) {
				$report = wp_easycart_admin_safe_delete()->analyze( 'menu2', (int) $bulk_id );
				if ( is_wp_error( $report ) ) { continue; }
				$strategy = 'remove';
				foreach ( $report['strategies'] as $st ) { if ( 'cascade' === $st['key'] ) { $strategy = 'cascade'; } }
				wp_easycart_admin_safe_delete()->execute( 'menu2', (int) $bulk_id, array( 'strategy' => $strategy, 'redirect' => true ) );
			}

			return array( 'success' => 'menulevel2-deleted' );
		}


		public function delete_menulevel3() {
			if ( !wp_easycart_admin_verification()->verify_access( 'wp-easycart-action-delete-menulevel3' ) ) {
				return false;
			}

			$id = (int) $_GET['menulevel3_id'];
			$report = wp_easycart_admin_safe_delete()->analyze( 'menu3', $id );
			$strategy = 'remove';
			if ( ! is_wp_error( $report ) ) { foreach ( $report['strategies'] as $st ) { if ( 'cascade' === $st['key'] ) { $strategy = 'cascade'; } } }
			wp_easycart_admin_safe_delete()->execute( 'menu3', $id, array( 'strategy' => $strategy, 'redirect' => true ) );

			return array( 'success' => 'menulevel3-deleted' );
		}

		public function bulk_delete_menulevel3() {
			if ( !wp_easycart_admin_verification()->verify_access( 'wp-easycart-bulk-subsubmenus' ) ) {
				return false;
			}

			foreach ( (array) $_GET['bulk'] as $bulk_id ) {
				$report = wp_easycart_admin_safe_delete()->analyze( 'menu3', (int) $bulk_id );
				if ( is_wp_error( $report ) ) { continue; }
				$strategy = 'remove';
				foreach ( $report['strategies'] as $st ) { if ( 'cascade' === $st['key'] ) { $strategy = 'cascade'; } }
				wp_easycart_admin_safe_delete()->execute( 'menu3', (int) $bulk_id, array( 'strategy' => $strategy, 'redirect' => true ) );
			}

			return array( 'success' => 'menulevel3-deleted' );
		}
	}
endif;

function wp_easycart_admin_menus() {
	return wp_easycart_admin_menus::instance();
}
wp_easycart_admin_menus();