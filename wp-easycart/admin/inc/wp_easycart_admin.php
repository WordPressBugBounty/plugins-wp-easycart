<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin' ) ) :

	final class wp_easycart_admin{

		protected static $_instance = null;

		private $wpdb;

		public $gutenberg;
		public $available_url;
		public $preloader;

		public $date_diff;
		/** @since 6.0.0 DB NOW() and store-local now, as UTC-parsed wall-clock timestamps ( see set_date_diff() ). */
		public $now_storage_ts;
		public $now_local_ts;
		/** @since 6.0.0 memoized order fingerprint ( see order_fingerprint() ). */
		private static $order_fingerprint = null;
		/** @since 6.0.0 orders per report-export batch. */
		const REPORT_BATCH = 1000;

		public $month_sales_total;
		public $month_name;
		public $month_percentage_change;
		public $month_percentage_goal;
		public $month_goal_total;

		public $daily_sales;
		public $weekly_sales;
		public $monthly_sales;
		public $yearly_sales;

		public $daily_items_sold;
		public $weekly_items_sold;
		public $monthly_items_sold;
		public $yearly_items_sold;

		public $daily_abandonment;
		public $weekly_abandonment;
		public $monthly_abandonment;
		public $yearly_abandonment;

		public $new_orders;
		public $new_unviewed_orders;
		public $pending_reviews;
		public $cart_users;

		public $settings;
		public $shipping_zones;
		public $shipping_zones_items;
		public $countries;
		public $states;

		public $store_page;
		public $cart_page;
		public $account_page;
		public $permalink_divider;
		public $helpsystem;

		public static function instance( ) {

			if( is_null( self::$_instance ) ) {
				self::$_instance = new self(  );
			}
			return self::$_instance;

		}

		public function __construct( ){ 

			if( !defined( 'WP_EASYCART_ADMIN_PLUGIN_DIR' ) )
				define( 'WP_EASYCART_ADMIN_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

			if( ! defined( 'WP_EASYCART_ADMIN_PLUGIN_URL' ) )
				define( 'WP_EASYCART_ADMIN_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

			if( ! defined( 'WP_EASYCART_ADMIN_PLUGIN_FILE' ) )
				define( 'WP_EASYCART_ADMIN_PLUGIN_FILE', __FILE__ );

			if( !defined( 'WP_EASYCART_ADMIN_DB_VERSION' ) )
				define( 'WP_EASYCART_ADMIN_DB_VERSION', 0.1 );

			// Keep reference to wpdb
			global $wpdb;
			$this->wpdb =& $wpdb;

			$this->gutenberg = new Wp_Easycart_Gutenberg();

			$this->preloader = new wp_easycart_admin_preloader( );
			$this->helpsystem = new wp_easycart_admin_online_docs( );

			$this->set_date_diff( );

			/* 6.0.0: order-derived caches ( badge count, month totals, report datasets, upsell stats ) are keyed by
			   order_fingerprint() and flushed by the admin-side order hooks. Frontend checkouts never load this
			   file, so the fingerprint ( MAX( order_id ) ) is what catches those inserts. */
			add_action( 'wpeasycart_order_inserted', array( __CLASS__, 'flush_order_caches' ), 5, 0 );
			add_action( 'wpeasycart_subscription_first_order_inserted', array( __CLASS__, 'flush_order_caches' ), 5, 0 );
			add_action( 'wpeasycart_order_status_update', array( __CLASS__, 'flush_order_caches' ), 5, 0 );
			add_action( 'wpeasycart_order_updated', array( __CLASS__, 'flush_order_caches' ), 5, 0 );
			add_action( 'wpeasycart_order_deleted', array( __CLASS__, 'flush_order_caches' ), 5, 0 );
			add_action( 'wp_easycart_ecv2_order_duplicated', array( __CLASS__, 'flush_order_caches' ), 5, 0 );
			if ( self::request_touches_order_viewed() ) {
				/* The handler for this request flips order_viewed after we run: drop the cached badge count now
				   so the next page load recounts, and never re-store it during this request. */
				delete_transient( 'wpec_unviewed_orders' );
			}

			if( isset( $_GET['page'] ) && (
				$_GET['page'] == 'wp-easycart-dashboard' || 
				$_GET['page'] == 'wp-easycart-license-status' ||
				$_GET['page'] == 'wp-easycart-products' || 
				$_GET['page'] == 'wp-easycart-orders' || 
				$_GET['page'] == 'wp-easycart-users' || 
				$_GET['page'] == 'wp-easycart-rates' || 
				$_GET['page'] == 'wp-easycart-settings' || 
				$_GET['page'] == 'wp-easycart-status' || 
				$_GET['page'] == 'wp-easycart-registration'
			) ){
				// Setup Basic Variables for Admin Design
				$this->month_sales_total = $this->get_month_sales_total( );
				$this->month_name = date( 'F' );
				$this->month_percentage_change = $this->get_month_percentage_change( );
				$this->month_goal_total = number_format( (float) get_option( 'ec_option_admin_sales_goal' ), 2, '.', '' );
				if ( $this->month_goal_total < .01 ) {
					$this->month_goal_total = 1;
				}
				$this->month_percentage_goal = ( $this->month_sales_total / $this->month_goal_total ) * 100;

				if( $_GET['page'] == 'wp-easycart-dashboard' ){
					$this->new_orders = $this->get_total_new_orders( );
					$this->pending_reviews = $this->get_total_new_reviews( );
					$this->cart_users = $this->get_total_cart_users( );
				}
			}

			/* The badge is only rendered by setup_menu() ( admin_menu ), which never runs on admin-ajax. */
			$this->new_unviewed_orders = ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) ? 0 : $this->get_total_new_unviewed_orders( );

			// EasyCart Admin Actions
			add_action( 'wp_easycart_admin_messages', array( $this, 'print_core_notices_in_shell' ), 5 );
			/* Store / cart / account page missing its shortcode: in-shell admin notice on every EasyCart screen. @since 6.0.0 */
			if ( class_exists( 'wp_easycart_admin_settings_page_v2' ) ) {
				add_action( 'wp_easycart_admin_messages', array( 'wp_easycart_admin_settings_page_v2', 'print_store_pages_notice' ), 6 );
			}
			add_action( 'wp_easycart_admin_messages', array( $this, 'load_upsell_image' ) );
			add_action( 'wp_easycart_admin_messages', array( $this, 'load_renewal_notice' ) );
			add_action( 'wp_ajax_ec_admin_ajax_ecv2_dismiss_renewal', array( $this, 'ajax_dismiss_renewal_notice' ) );
			add_action( 'wp_easycart_admin_upsell_popup', array( $this, 'load_upsell_popup' ) );
			add_action( 'wp_easycart_admin_mobile_navigation', array( $this, 'load_mobile_navigation' ), 1, 0 );
			add_action( 'wp_easycart_admin_left_navigation', array( $this, 'load_left_navigation' ), 1, 0 );
			add_action( 'wp_easycart_admin_head_navigation', array( $this, 'load_head_navigation' ), 1, 0 );
			add_action( 'wp_easycart_admin_messages', array( $this, 'print_admin_message' ) );
			add_action( 'wp_dashboard_setup', array( $this, 'add_ec_nag_widget' ) );

			// Hook Actions
			add_action( 'admin_init', array( $this, 'create_user_role' ) );
			add_filter( 'ure_capabilities_groups_tree', array( $this, 'add_user_role_editor_group' ) );
			add_filter( 'ure_custom_capability_groups', array( $this, 'add_user_role_editor_cap_to_group' ), 10, 2 );
			add_action( 'admin_init', array( $this, 'save_perpage' ) );
			add_action( 'admin_init', array( $this, 'delete_gateway_log' ) );
			add_action( 'admin_init', array( $this, 'complete_init' ) );
			add_action( 'admin_init', array( $this, 'setup_pro_hooks' ) );
			add_action( 'admin_init', array( $this, 'process_actions' ) );
			add_action( 'admin_init', array( $this, 'change_uploads_dir' ), 999 );
			add_action( 'admin_menu', array( $this, 'setup_menu' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'load_scripts' ) );
			add_action( 'admin_notices', array( $this, 'wp_easycart_pro_check' ) );
			add_action( 'admin_notices', array( $this, 'square_check' ) );
			add_action( 'admin_notices', array( $this, 'database_check' ) );
			add_action( 'admin_notices', array( $this, 'database_install_errors_check' ) );
			add_action( 'admin_notices', array( $this, 'download_recovery_check' ) );
			add_action( 'add_meta_boxes', array( $this, 'page_lock_meta' ), 10, 2 );
			add_action( 'save_post', array( $this, 'save_page_lock_meta' ) );
			add_action( 'wpeasycart_product_activated', array( $this, 'wp_easycart_sync_product_post_status' ) );
			add_action( 'wpeasycart_product_deactivated', array( $this, 'wp_easycart_sync_product_post_status' ) );

			// WordPress Filters
			add_filter( 'admin_title', array( $this, 'set_title' ), 10, 2 );
		}

		public function get_available_url() {
			if ( ! isset( $this->available_url ) ) {
				$this->available_url = "https://connect.wpeasycart.com";
				// Backup removed of $this->available_url = "https://support.wpeasycart.com";
			}
			return $this->available_url;
		}

		public function add_user_role_editor_group( $groups ) {
			if ( current_user_can( 'wpec_manager' ) ) {
				$groups['wpeasycart'] = array(
					'caption' => esc_html__( 'WP EasyCart', 'wp-easycart' ),
					'parent' => 'custom', 
					'level' => 2,
					'caps' => array( 'wpec_manager' )
				);
			}
			return $groups;
		}
		
		public function add_user_role_editor_cap_to_group( $groups, $cap_id ) {
			$wpec_capabilities = array( 'wpec_manager', 'wpec_reports', 'wpec_store_status', 'wpec_products', 'wpec_orders', 'wpec_users', 'wpec_marketing', 'wpec_settings', 'wpec_diagnostics', 'wpec_registration' );
			if ( in_array( $cap_id, $wpec_capabilities ) ) {
				$groups[] = 'wpeasycart';
			}
			return $groups;
		}

		public function create_user_role() {
			global $wp_roles;

			if ( ! class_exists( 'WP_Roles' ) ) {
				return;
			}

			if ( ! isset( $wp_roles ) ) {
				$wp_roles = new WP_Roles();
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			if ( wp_roles()->is_role( 'wpec_store_manager' ) ) {
				$manager_role = get_role( 'wpec_store_manager' );
				$manager_role->add_cap( 'edit_files' );
				$manager_role->add_cap( 'upload_files' );
				return;
			}

			$wpec_capabilities = array( 'wpec_manager', 'wpec_reports', 'wpec_store_status', 'wpec_products', 'wpec_orders', 'wpec_users', 'wpec_marketing', 'wpec_settings', 'wpec_diagnostics', 'wpec_registration' );

			// remove_role( 'wpec_store_manager' );
			add_role(
				'wpec_store_manager',
				__( 'WP EasyCart Store Manager', 'wp-easycart' ),
				array(
					'read' => true,
					'edit_files' => true,
					'upload_files' => true,
					'wpec_manager' => true,
					'wpec_reports' => true,
					'wpec_store_status' => true,
					'wpec_products' => true,
					'wpec_orders' => true,
					'wpec_users' => true,
					'wpec_marketing' => true,
					'wpec_settings' => true,
					'wpec_diagnostics' => true,
					'wpec_registration' => true,
				)
			);

			foreach ( $wpec_capabilities as $wpec_capability ) {
				$wp_roles->add_cap( 'wpec_store_manager', $wpec_capability );
				$wp_roles->add_cap( 'administrator', $wpec_capability );
			}
		}

		public function save_perpage() {
			if ( isset( $_GET['perpage'] ) ) {
				$valid_values = array( 10, 25, 50, 100, 250, 500 );
				if( in_array( (int) $_GET['perpage'], $valid_values ) ){
					setcookie( 'wpeasycart_admin_perpage', '', time() - 3600 );
					setcookie( 'wpeasycart_admin_perpage', '', time() - 3600, defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/', defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? COOKIE_DOMAIN : '' );
					setcookie( 'wpeasycart_admin_perpage', (int) $_GET['perpage'], time() + ( 3600 * 24 * 1 ), defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/', defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? COOKIE_DOMAIN : '' );
				}
			}
		}

		public function save_page_lock_meta( $post_id ){
			/* 6.0.0: only the plugin's own meta box may write the page-lock keys ( they are protected meta ): its nonce, no autosaves / revisions, and the right to edit this post. */
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return;
			}
			if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
				return;
			}
			if ( ! isset( $_POST['wp_easycart_page_lock_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_easycart_page_lock_nonce'] ) ), 'wp_easycart_page_lock_' . $post_id ) ) {
				return;
			}
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}
			if( current_user_can( 'manage_options' ) || current_user_can( 'wpec_manager' ) ){
				if( array_key_exists( 'wpeasycart_restrict_product_id', $_POST ) ){
					$wpeasycart_restrict_product_id = array( );
					if( is_array( $_POST['wpeasycart_restrict_product_id'] ) ){
						foreach( (array) $_POST['wpeasycart_restrict_product_id'] as $product_id ){ // XSS OK. Forced array and each item sanitized.
							$wpeasycart_restrict_product_id[] = (int) $product_id;
						}
					} else {
						$wpeasycart_restrict_product_id = sanitize_text_field( $_POST['wpeasycart_restrict_product_id'] );
					}
					update_post_meta( $post_id, 'wpeasycart_restrict_product_id', $wpeasycart_restrict_product_id );
				}

				if( array_key_exists( 'wpeasycart_restrict_user_id', $_POST ) ){
					if( is_array( $_POST['wpeasycart_restrict_user_id'] ) ){
						$wpeasycart_restrict_user_id = array( );
						foreach( (array) $_POST['wpeasycart_restrict_user_id'] as $user_id ){ // XSS OK. Forced array and each item sanitized.
							$wpeasycart_restrict_user_id[] = (int) $user_id;
						}
					}else{
						$wpeasycart_restrict_user_id = sanitize_text_field( $_POST['wpeasycart_restrict_user_id'] );
					}
					update_post_meta( $post_id, 'wpeasycart_restrict_user_id', $wpeasycart_restrict_user_id );
				}

				if( array_key_exists( 'wpeasycart_restrict_role_id', $_POST ) ){
					$wpeasycart_restrict_role_id = array( );
					if( is_array( $_POST['wpeasycart_restrict_role_id'] ) ){
						foreach( (array) $_POST['wpeasycart_restrict_role_id'] as $user_role_id ){ // XSS OK. Forced array and each item sanitized.
							$wpeasycart_restrict_role_id[] = sanitize_text_field( $user_role_id );
						}
					}
					update_post_meta( $post_id, 'wpeasycart_restrict_role_id', $wpeasycart_restrict_role_id );
				}

				if( array_key_exists( 'wpeasycart_restrict_redirect_url', $_POST ) ){
					update_post_meta( $post_id, 'wpeasycart_restrict_redirect_url', esc_url_raw( $_POST['wpeasycart_restrict_redirect_url'] ) );
				}

				if( array_key_exists( 'wpeasycart_restrict_redirect_url_auth', $_POST ) ){
					update_post_meta( $post_id, 'wpeasycart_restrict_redirect_url_auth', esc_url_raw( $_POST['wpeasycart_restrict_redirect_url_auth'] ) );
				}

				if( array_key_exists( 'wpeasycart_restrict_redirect_url_not_auth', $_POST ) ){
					update_post_meta( $post_id, 'wpeasycart_restrict_redirect_url_not_auth', esc_url_raw( $_POST['wpeasycart_restrict_redirect_url_not_auth'] ) );
				}
			}
		}
		
		public function wp_easycart_sync_product_post_status( $product_id ) {
			global $wpdb;
			$product = $wpdb->get_row( $wpdb->prepare( 'SELECT post_id, activate_in_store, model_number FROM ec_product WHERE product_id = %d', $product_id ) );
			if ( ! $product || empty( $product->post_id ) ) {
				return;
			}
			$target  = $product->activate_in_store ? 'publish' : 'private';
			$current = get_post_status( $product->post_id );
			if ( $current && $current !== $target && in_array( $current, array( 'publish', 'private' ), true ) ) {
				$wpdb->query( $wpdb->prepare(
					'UPDATE ' . $wpdb->prefix . 'posts SET post_status = %s, post_modified = NOW( ), post_modified_gmt = UTC_TIMESTAMP( ) WHERE ID = %d',
					$target,
					$product->post_id
				) );
				clean_post_cache( $product->post_id );
				wp_cache_delete( 'wpeasycart-product-only-' . $product->model_number, 'wpeasycart-product-list' );
			}
		}

		public function page_lock_meta( $post_type, $post ){
			if( ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_manager' ) ) && ( $post_type == 'page' || $post_type == 'post' || $post_type == 'ec_store' ) ){
				add_meta_box( 
					'wp-easycart-product-lock',
					__( 'WP EasyCart Limit Access', 'wp-easycart' ),
					array( $this, 'load_page_lock_meta_box' ),
					array( 'page', 'post', 'ec_store' ),
					'side',
					'default'
				);
			}
		}

		public function load_page_lock_meta_box( $post ){
			global $wpdb;
			wp_nonce_field( 'wp_easycart_page_lock_' . $post->ID, 'wp_easycart_page_lock_nonce' );
			$user_roles = $wpdb->get_results( "SELECT role_label FROM ec_role ORDER BY role_label ASC" );

			$selected_product = get_post_meta( $post->ID, 'wpeasycart_restrict_product_id', true );
			$selected_user = get_post_meta( $post->ID, 'wpeasycart_restrict_user_id', true );
			$selected_role = get_post_meta( $post->ID, 'wpeasycart_restrict_role_id', true );
			$selected_redirect = get_post_meta( $post->ID, 'wpeasycart_restrict_redirect_url', true );
			$selected_redirect_auth = get_post_meta( $post->ID, 'wpeasycart_restrict_redirect_url_auth', true );
			$selected_redirect_not_auth = get_post_meta( $post->ID, 'wpeasycart_restrict_redirect_url_not_auth', true );

			/* 6.0.0: search-as-you-type pickers. Only the stored ids are looked up; nothing lists the catalog or the customer table. */
			$product_ids    = self::id_list( $selected_product );
			$product_labels = array();
			if ( $product_ids ) {
				$rows = $wpdb->get_results( 'SELECT product_id, title FROM ec_product WHERE product_id IN ( ' . implode( ',', array_map( 'intval', $product_ids ) ) . ' )' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- integer ids cast with intval.
				foreach ( $product_ids as $product_id ) {
					$product_labels[ $product_id ] = '#' . $product_id;
				}
				foreach ( (array) $rows as $row ) {
					$product_labels[ (int) $row->product_id ] = wp_unslash( $row->title );
				}
			}
			echo '<label for="wpeasycart_restrict_product_id">' . esc_attr__( 'Option 1: Restrict by Product', 'wp-easycart' ) . '</label>';
			self::print_picker( array(
				'id'          => 'wpeasycart_restrict_product_id',
				'mode'        => 'product',
				'name'        => 'wpeasycart_restrict_product_id[]',
				'multiple'    => true,
				'selected'    => $product_labels,
				'placeholder' => __( 'Search products by name or SKU', 'wp-easycart' ),
			) );

			$user_ids    = self::id_list( $selected_user );
			$user_labels = array();
			if ( $user_ids ) {
				$rows = $wpdb->get_results( 'SELECT user_id, first_name, last_name FROM ec_user WHERE user_id IN ( ' . implode( ',', array_map( 'intval', $user_ids ) ) . ' )' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- integer ids cast with intval.
				foreach ( $user_ids as $user_id ) {
					$user_labels[ $user_id ] = '#' . $user_id;
				}
				foreach ( (array) $rows as $row ) {
					$user_labels[ (int) $row->user_id ] = wp_unslash( $row->last_name . ', ' . $row->first_name ) . ' (' . (int) $row->user_id . ')';
				}
			}
			echo '<label for="wpeasycart_restrict_user_id">' . esc_attr__( 'Option 2: Restrict by User', 'wp-easycart' ) . '</label>';
			self::print_picker( array(
				'id'          => 'wpeasycart_restrict_user_id',
				'mode'        => 'user',
				'name'        => 'wpeasycart_restrict_user_id[]',
				'multiple'    => true,
				'selected'    => $user_labels,
				'placeholder' => __( 'Search customers by name or email', 'wp-easycart' ),
			) );

			echo '<label for="wpeasycart_restrict_role_id">' . esc_attr__( 'Option 3: Restrict by User Role', 'wp-easycart' ) . '</label>';
			echo '<select name="wpeasycart_restrict_role_id[]" id="wpeasycart_restrict_role_id" class="postbox" multiple>';
			echo '<option value="">' . esc_attr__( 'No Restriction', 'wp-easycart' ) . '</option>';
			foreach( $user_roles as $role ){
				echo '<option value="' . esc_attr( $role->role_label ) . '"' . ( ( ( is_array( $selected_role ) && in_array( $role->role_label, $selected_role ) ) || ( !is_array( $selected_role ) && $role->role_label == $selected_role ) ) ? ' selected="selected"' : '' ). '>' . esc_attr( $role->role_label ) . '</option>';
			}
			echo '</select>';

			echo '<label for="wpeasycart_restrict_redirect_url">' . esc_attr__( 'Redirect - Not Logged In + Not Authorized', 'wp-easycart' ) . '</label>';
			echo '<input type="text" name="wpeasycart_restrict_redirect_url" id="wpeasycart_restrict_redirect_url" class="postbox" value="' . esc_url_raw( $selected_redirect ) . '" placeholder="https://www.site.com">';

			echo '<label for="wpeasycart_restrict_redirect_url_auth">' . esc_attr__( 'Redirect - Logged In + Authorized', 'wp-easycart' ) . '</label>';
			echo '<input type="text" name="wpeasycart_restrict_redirect_url_auth" id="wpeasycart_restrict_redirect_url_auth" class="postbox" value="' . esc_url_raw( $selected_redirect_auth ) . '" placeholder="https://www.site.com">';

			echo '<label for="wpeasycart_restrict_redirect_url_not_auth">' . esc_attr__( 'Redirect - Logged In + Not Authorized', 'wp-easycart' ) . '</label>';
			echo '<input type="text" name="wpeasycart_restrict_redirect_url_not_auth" id="wpeasycart_restrict_redirect_url_not_auth" class="postbox" value="' . esc_url_raw( $selected_redirect_not_auth ) . '" placeholder="https://www.site.com">';

			echo '<p>' . esc_attr__( 'Note: You must turn off guest checkout or select a download or subscription product from the menu above. Subscription products will check the user has an active subscription. You must create a page to redirect to if a user does not have authorization.', 'wp-easycart' ) . '</p>';
		}

		/**
		 * Positive integer ids from a stored meta value ( array, or the legacy comma string ).
		 *
		 * @since 6.0.0
		 * @return int[]
		 */
		private static function id_list( $value ) {
			if ( ! is_array( $value ) ) {
				$value = explode( ',', (string) $value );
			}
			$ids = array();
			foreach ( $value as $id ) {
				$id = (int) $id;
				if ( $id > 0 && ! in_array( $id, $ids, true ) ) {
					$ids[] = $id;
				}
			}
			return $ids;
		}

		/**
		 * Search-as-you-type picker for products or customers. Replaces the 500-row <select>s on the page-lock
		 * meta box and the Reports product filter. Products search through ec_admin_ajax_ecv2_product_search
		 * ( capability-gated, read-only ); customers through ec_admin_ajax_get_order_users ( order-details nonce ).
		 * The widget is dependency-free: it posts to ajaxurl and needs no select2 on post edit screens.
		 *
		 * @since 6.0.0
		 * @param array $args {
		 *   @type string $id          Element id.
		 *   @type string $mode        'product' | 'user'.
		 *   @type string $name        POST field name for the chosen ids ( use "field[]" for multiple ); '' for none.
		 *   @type string $target      Id of a hidden input to receive the single chosen id ( '0' when cleared ).
		 *   @type bool   $multiple    Allow more than one chip.
		 *   @type array  $selected    id => label of the current choice(s).
		 *   @type string $placeholder Search box placeholder.
		 *   @type string $on_change   Global JS function to call after a change.
		 *   @type string $class       Extra classes for the search input.
		 * }
		 */
		public static function print_picker( $args ) {
			static $assets_printed = false;
			$a = wp_parse_args( $args, array(
				'id'          => '',
				'mode'        => 'product',
				'name'        => '',
				'target'      => '',
				'multiple'    => false,
				'selected'    => array(),
				'placeholder' => '',
				'on_change'   => '',
				'class'       => '',
			) );
			$mode  = ( 'user' === $a['mode'] ) ? 'user' : 'product';
			$id    = ( '' !== $a['id'] ) ? $a['id'] : 'wpec-pick-' . $mode . '-' . wp_rand( 1000, 999999 );
			$nonce = ( 'user' === $mode ) ? wp_create_nonce( 'wp-easycart-order-details' ) : '';

			echo '<div class="wpec-pick" id="' . esc_attr( $id ) . '" data-mode="' . esc_attr( $mode ) . '" data-multiple="' . ( $a['multiple'] ? '1' : '0' ) . '" data-name="' . esc_attr( $a['name'] ) . '" data-target="' . esc_attr( $a['target'] ) . '" data-nonce="' . esc_attr( $nonce ) . '" data-onchange="' . esc_attr( $a['on_change'] ) . '" data-empty="' . esc_attr__( 'No matches.', 'wp-easycart' ) . '" data-remove="' . esc_attr__( 'Remove', 'wp-easycart' ) . '">';
			echo '<div class="wpec-pick-chips">';
			foreach ( (array) $a['selected'] as $value => $label ) {
				echo '<span class="wpec-pick-chip" data-id="' . esc_attr( $value ) . '"><span>' . esc_html( $label ) . '</span><button type="button" class="wpec-pick-x" aria-label="' . esc_attr__( 'Remove', 'wp-easycart' ) . '">&times;</button>';
				if ( '' !== $a['name'] ) {
					echo '<input type="hidden" name="' . esc_attr( $a['name'] ) . '" value="' . esc_attr( $value ) . '" />';
				}
				echo '</span>';
			}
			if ( '' !== $a['name'] && $a['multiple'] && empty( $a['selected'] ) ) {
				/* An empty submission saves "no restriction" ( the legacy select did the same through its blank option ). */
				echo '<input type="hidden" name="' . esc_attr( $a['name'] ) . '" value="" class="wpec-pick-none" />';
			}
			echo '</div>';
			echo '<input type="search" class="wpec-pick-input ' . esc_attr( $a['class'] ) . '" placeholder="' . esc_attr( $a['placeholder'] ) . '" autocomplete="off" />';
			echo '<div class="wpec-pick-results" hidden></div>';
			echo '</div>';

			if ( $assets_printed ) {
				return;
			}
			$assets_printed = true;
			?>
<style>
.wpec-pick{position:relative;margin:2px 0 10px}
.wpec-pick-chips{display:flex;flex-wrap:wrap;gap:4px;margin:0 0 4px}
.wpec-pick-chips:empty{margin:0}
.wpec-pick-chip{display:inline-flex;align-items:center;gap:4px;max-width:100%;padding:2px 4px 2px 8px;border:1px solid #c3c4c7;border-radius:12px;background:#f0f0f1;font-size:12px;line-height:18px}
.wpec-pick-chip > span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.wpec-pick-x{border:0;background:transparent;color:#646970;cursor:pointer;font-size:14px;line-height:1;padding:0 2px}
.wpec-pick-x:hover{color:#d63638}
.wpec-pick-input{width:100%;box-sizing:border-box}
.wpec-pick-results{position:absolute;left:0;right:0;z-index:100;max-height:220px;overflow:auto;margin-top:2px;border:1px solid #c3c4c7;border-radius:4px;background:#fff;box-shadow:0 4px 12px rgba(0,0,0,.08)}
.wpec-pick-item{display:block;width:100%;padding:6px 10px;border:0;background:transparent;text-align:left;cursor:pointer;font-size:13px;line-height:1.4}
.wpec-pick-item:hover,.wpec-pick-item:focus{background:#f0f6fc;outline:0}
.wpec-pick-item small{color:#646970;margin-left:4px}
.wpec-pick-empty{padding:6px 10px;color:#646970;font-size:12px}
.ecrp-filters .wpec-pick{margin:0;min-width:220px}
.ecrp-filters .wpec-pick-chips{margin:0 0 2px}
</style>
<script>
( function() {
	function esc( s ) { return String( s == null ? '' : s ).replace( /[&<>"']/g, function( c ) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ]; } ); }
	function ajaxUrl() { return window.ajaxurl || ( window.wpeasycart_admin_ajax_object && wpeasycart_admin_ajax_object.ajax_url ) || '/wp-admin/admin-ajax.php'; }
	function init( root ) {
		if ( root.wpecPickReady ) { return; }
		root.wpecPickReady = true;
		var mode = root.getAttribute( 'data-mode' ), multiple = root.getAttribute( 'data-multiple' ) === '1', name = root.getAttribute( 'data-name' ) || '', target = root.getAttribute( 'data-target' ) || '', nonce = root.getAttribute( 'data-nonce' ) || '', onchange = root.getAttribute( 'data-onchange' ) || '';
		var chips = root.querySelector( '.wpec-pick-chips' ), input = root.querySelector( '.wpec-pick-input' ), results = root.querySelector( '.wpec-pick-results' ), timer = null, seq = 0;
		function ids() { var out = []; chips.querySelectorAll( '.wpec-pick-chip' ).forEach( function( c ) { out.push( String( c.getAttribute( 'data-id' ) ) ); } ); return out; }
		function sync() {
			var none = chips.querySelector( '.wpec-pick-none' ), has = !! chips.querySelector( '.wpec-pick-chip' );
			if ( name && multiple ) {
				if ( ! has && ! none ) { none = document.createElement( 'input' ); none.type = 'hidden'; none.name = name; none.value = ''; none.className = 'wpec-pick-none'; chips.appendChild( none ); }
				else if ( has && none ) { none.parentNode.removeChild( none ); }
			}
			if ( target ) { var t = document.getElementById( target ); if ( t ) { var list = ids(); t.value = list.length ? list[ 0 ] : '0'; } }
			if ( onchange && typeof window[ onchange ] === 'function' ) { window[ onchange ](); }
		}
		function add( id, label ) {
			if ( ! multiple ) { chips.innerHTML = ''; }
			if ( ids().indexOf( String( id ) ) !== -1 ) { return; }
			var chip = document.createElement( 'span' );
			chip.className = 'wpec-pick-chip';
			chip.setAttribute( 'data-id', id );
			chip.innerHTML = '<span>' + esc( label ) + '</span><button type="button" class="wpec-pick-x" aria-label="' + esc( root.getAttribute( 'data-remove' ) ) + '">&times;</button>' + ( name ? '<input type="hidden" name="' + esc( name ) + '" value="' + esc( id ) + '" />' : '' );
			chips.appendChild( chip );
			sync();
		}
		function render( items ) {
			results.innerHTML = '';
			if ( ! items.length ) { results.innerHTML = '<div class="wpec-pick-empty">' + esc( root.getAttribute( 'data-empty' ) ) + '</div>'; results.hidden = false; return; }
			items.forEach( function( it ) {
				var b = document.createElement( 'button' );
				b.type = 'button'; b.className = 'wpec-pick-item';
				b.setAttribute( 'data-id', it.id ); b.setAttribute( 'data-label', it.label );
				b.innerHTML = esc( it.label ) + ( it.meta ? ' <small>' + esc( it.meta ) + '</small>' : '' );
				results.appendChild( b );
			} );
			results.hidden = false;
		}
		function search( q ) {
			var my = ++seq, body;
			if ( mode === 'user' ) { body = 'action=ec_admin_ajax_get_order_users&q=' + encodeURIComponent( q ) + '&wp_easycart_nonce=' + encodeURIComponent( nonce ); }
			else { body = 'action=ec_admin_ajax_ecv2_product_search&page=1&q=' + encodeURIComponent( q ); }
			var xhr = new XMLHttpRequest();
			xhr.open( 'POST', ajaxUrl() );
			xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8' );
			xhr.onload = function() {
				if ( my !== seq ) { return; }
				var d = {}, items = [];
				try { d = JSON.parse( xhr.responseText ); } catch ( e ) { d = {}; }
				if ( mode === 'user' ) { ( d.items || [] ).forEach( function( u ) { if ( String( u.id ) !== '0' ) { items.push( { id: u.id, label: u.text } ); } } ); }
				else { ( d.results || [] ).forEach( function( p ) { items.push( { id: p.id, label: p.title || p.text, meta: p.sku || '' } ); } ); }
				render( items.slice( 0, 20 ) );
			};
			xhr.send( body );
		}
		chips.addEventListener( 'click', function( e ) { var x = e.target.closest( '.wpec-pick-x' ); if ( ! x ) { return; } var chip = x.closest( '.wpec-pick-chip' ); chip.parentNode.removeChild( chip ); sync(); } );
		results.addEventListener( 'click', function( e ) { var b = e.target.closest( '.wpec-pick-item' ); if ( ! b ) { return; } add( b.getAttribute( 'data-id' ), b.getAttribute( 'data-label' ) ); results.hidden = true; input.value = ''; } );
		input.addEventListener( 'input', function() { clearTimeout( timer ); var q = input.value.trim(); timer = setTimeout( function() { search( q ); }, 250 ); } );
		input.addEventListener( 'focus', function() { if ( results.children.length ) { results.hidden = false; } else { search( input.value.trim() ); } } );
		input.addEventListener( 'keydown', function( e ) { if ( e.key === 'Escape' ) { results.hidden = true; } } );
		document.addEventListener( 'click', function( e ) { if ( ! root.contains( e.target ) ) { results.hidden = true; } } );
	}
	function boot() { document.querySelectorAll( '.wpec-pick' ).forEach( init ); }
	window.wpecPickInit = boot;
	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', boot ); } else { boot(); }
} )();
</script>
			<?php
		}

		public function add_ec_nag_widget( ){
			wp_add_dashboard_widget( 'ec_free_dashboard_widget', esc_attr__( 'WP EasyCart FREE Edition', 'wp-easycart' ), array( $this, 'ec_dashboard_nag_widget' ) );
			global $wp_meta_boxes;
			$normal_dashboard = $wp_meta_boxes['dashboard']['normal']['core'];
			$widget_backup = array( 'ec_free_dashboard_widget' => $normal_dashboard['ec_free_dashboard_widget'] );
			unset( $normal_dashboard['ec_free_dashboard_widget'] );
			$sorted_dashboard = array_merge( $widget_backup, $normal_dashboard );
			$wp_meta_boxes['dashboard']['normal']['core'] = $sorted_dashboard;
		}

		public function ec_dashboard_nag_widget( $post, $callback_args ){
			echo "<div style='text-align:center;font-size: 1.3em;'>" . esc_attr__( 'Are you enjoying your FREE Shopping Cart?', 'wp-easycart' ) . '<br>' . esc_attr__( 'Want to unlock more awesome features?', 'wp-easycart' ) . '<br/><br/>' . esc_attr__( 'Upgrade to', 'wp-easycart' ) . ' <strong>' . esc_attr__( 'Pro', 'wp-easycart' ) . '</strong> & <strong>' . esc_attr__( 'Premium', 'wp-easycart' ) . '</strong> ' . esc_attr__( 'editions!', 'wp-easycart' ) . '<br/>';
			echo "<a href='https://www.wpeasycart.com/wordpress-shopping-cart-pricing/?upsell=9' target='_blank'><img src='" . esc_attr( plugins_url( "wp-easycart/admin/images/ec_dashboard_nag_image.jpg", EC_PLUGIN_DIRECTORY ) ) . "' style='max-width:100%;margin: 10px;'/></a>";
			echo "<a class='button button-primary' href='https://www.wpeasycart.com/wordpress-shopping-cart-pricing/?upsell=9' target='_blank'>" . esc_attr__( 'Upgrade Today!', 'wp-easycart' ) . "</a></div>";
		}

		public function delete_gateway_log( ){ 
			if( ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_manager' ) ) && isset( $_GET['ec_admin_form_action'])  && $_GET['ec_admin_form_action'] == 'ec_delete_gateway_log' ){
				wp_easycart_admin_miscellaneous( )->delete_gateway_log( );
			}
		}

		public function complete_init( ){

			// Link Information
			$store_page_id = get_option('ec_option_storepage');
			$cart_page_id = get_option('ec_option_cartpage');
			$account_page_id = get_option('ec_option_accountpage');

			if( function_exists( 'icl_object_id' ) ){
				$store_page_id = icl_object_id( $store_page_id, 'page', true, ICL_LANGUAGE_CODE );
				$cart_page_id = icl_object_id( $cart_page_id, 'page', true, ICL_LANGUAGE_CODE );
				$account_page_id = icl_object_id( $account_page_id, 'page', true, ICL_LANGUAGE_CODE );
			}

			$this->store_page = get_permalink( $store_page_id );
			$this->cart_page = get_permalink( $cart_page_id );
			$this->account_page = get_permalink( $account_page_id );

			if( class_exists( "WordPressHTTPS" ) && isset( $_SERVER['HTTPS'] ) ){
				$https_class = new WordPressHTTPS( );
				$this->store_page = $https_class->makeUrlHttps( $this->store_page );
				$this->cart_page = $https_class->makeUrlHttps( $this->cart_page );
				$this->account_page = $https_class->makeUrlHttps( $this->account_page );
			}

			if( substr_count( $this->cart_page, '?' ) )					$this->permalink_divider = "&";
			else														$this->permalink_divider = "?";

		}

		public function process_actions( ){ 
			if( ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_manager' ) ) && (isset( $_POST['ec_admin_form_action'] ) || isset( $_GET['ec_admin_form_action'] )) ){
				$actions = new wp_easycart_admin_actions( );
				$actions->process_action( );
			}

			if( ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_manager' ) ) && isset( $_GET['ec_trial'] ) && $_GET['ec_trial'] == 'start' ){
				$this->start_pro_trial( );
				wp_redirect( "admin.php?page=wp-easycart-registration" );
			}

			if( ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_manager' ) ) && isset( $_GET['ec_install'] ) && $_GET['ec_install'] == 'pro' ){
				if( !file_exists( EC_PLUGIN_DIRECTORY . '-pro/wp-easycart-admin-pro.php' ) ){
					$this->install_pro_plugin( 0 );
				}
				if( file_exists( EC_PLUGIN_DIRECTORY . '-pro/wp-easycart-admin-pro.php' ) && !is_plugin_active( 'wp-easycart-pro/wp-easycart-admin-pro.php' ) ){
					activate_plugin( EC_PLUGIN_DIRECTORY . '-pro/wp-easycart-admin-pro.php', NULL, 0, 1 );
				}
				wp_redirect( "admin.php?page=wp-easycart-registration" );
			}
		}

		public function change_uploads_dir( ){
			add_filter( 'upload_dir', array( $this, 'custom_download_location' ) );
		}

		public function custom_download_location( $upload ){
			if( isset( $_REQUEST['is_wpec_download'] ) && $_REQUEST['is_wpec_download'] == '1' ){
				if( !is_dir( $upload['basedir'] . '/wp-easycart' ) ){
					mkdir( $upload['basedir'] . '/wp-easycart', 0755 );
					$index_file = fopen( $upload['basedir'] . '/wp-easycart/index.html', "w" );
					fclose( $index_file );
				}
				/* 6.0.0: paid download files are served by the account download handler, never by URL. */
				if ( class_exists( 'wp_easycart_customer_uploads' ) && method_exists( 'wp_easycart_customer_uploads', 'protect_area' ) ) {
					wp_easycart_customer_uploads::ensure_protected( $upload['basedir'] . '/wp-easycart', 'downloads' );
				}
				$upload['subdir']  = "/wp-easycart";
				$upload['path']    = $upload['basedir'] . "/wp-easycart";
				$upload['url']     = $upload['baseurl'] . "/wp-easycart";
			}
			return $upload;
		}

		/* STATS FUNCTIONS */
		private function set_date_diff( ){
			global $wpdb;
			$now_server = $this->wpdb->get_var( "SELECT NOW( ) AS the_time" );
			$now_timestamp = strtotime( $now_server );
			$now_gmt_timestampt = time( );
			$storage_offset = $now_timestamp - $now_gmt_timestampt;
			$local_offset = get_option('gmt_offset') * 60 * 60;
			$this->date_diff = ( $local_offset - $storage_offset ) / 3600;
			/* 6.0.0: wall-clock "now" on the storage clock and on the store's local clock, as UTC-parsed timestamps. */
			$this->now_storage_ts = (int) $now_timestamp;
			$this->now_local_ts   = (int) $now_timestamp + (int) round( $this->date_diff * 3600 );
		}

		/**
		 * Per-request order fingerprint: the stats version option ( bumped by flush_order_caches() ) plus
		 * MAX( order_id ). Folded into every order-derived cache key so a new order from any code path,
		 * including frontend checkouts that never load the admin, misses the cache. One primary-key seek.
		 *
		 * @since 6.0.0
		 * @return string
		 */
		public static function order_fingerprint() {
			if ( null === self::$order_fingerprint ) {
				global $wpdb;
				self::$order_fingerprint = (string) get_option( 'ec_option_order_stats_version', '1' ) . '-' . (int) $wpdb->get_var( 'SELECT MAX( order_id ) FROM ec_order' );
			}
			return self::$order_fingerprint;
		}

		/**
		 * Invalidate every order-derived cache: bumps the stats version ( so fingerprinted keys change ) and
		 * drops the fixed-key transients. Hooked to the admin-side order insert / update / delete actions.
		 *
		 * @since 6.0.0
		 */
		public static function flush_order_caches() {
			update_option( 'ec_option_order_stats_version', (string) microtime( true ) );
			delete_transient( 'wpec_unviewed_orders' );
			delete_transient( 'wpec_upsell_stats' );
			self::$order_fingerprint = null;
		}

		/**
		 * Does this request change order_viewed after the badge count is taken? ( order details marks the
		 * order viewed while rendering; the bulk / all viewed actions run on admin_init; the row dot is AJAX. )
		 *
		 * @since 6.0.0
		 * @return bool
		 */
		private static function request_touches_order_viewed() {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing -- read-only routing hint; the handlers named here verify their own nonces.
			$action = isset( $_REQUEST['ec_admin_form_action'] ) ? sanitize_key( wp_unslash( $_REQUEST['ec_admin_form_action'] ) ) : '';
			$page   = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
			$ajax   = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';
			// phpcs:enable
			if ( 'wp-easycart-orders' === $page && 'edit' === $action && isset( $_GET['order_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- presence check only.
				return true;
			}
			if ( in_array( $action, array( 'mark-orders-viewed', 'mark-orders-not-viewed', 'mark-all-orders-viewed', 'mark-all-orders-not-viewed' ), true ) ) {
				return true;
			}
			return ( 'ecv2_order_toggle_viewed' === $ajax );
		}

		/**
		 * Transient helpers for order-derived stats. Keys carry the order fingerprint and the timezone shift.
		 *
		 * @since 6.0.0
		 */
		private function stats_cache_key( $key ) {
			return 'wpec_stats_' . md5( self::order_fingerprint() . '|' . $this->date_diff . '|' . self::currency_fingerprint() . '|' . $key );
		}

		/**
		 * Cached stats carry money already formatted, so the currency settings are part of their key: changing the
		 * symbol, its position or the separators shows the new format at once instead of after the cache expires.
		 *
		 * @since 6.0.1
		 * @return string
		 */
		public static function currency_fingerprint() {
			$parts = array();
			foreach ( array( 'ec_option_base_currency', 'ec_option_currency', 'ec_option_currency_symbol_location', 'ec_option_currency_negative_location', 'ec_option_currency_decimal_symbol', 'ec_option_currency_decimal_places', 'ec_option_currency_thousands_seperator', 'ec_option_show_currency_code' ) as $option ) {
				$parts[] = (string) get_option( $option );
			}
			return md5( implode( '|', $parts ) );
		}

		private function stats_cache_get( $key ) {
			return get_transient( $this->stats_cache_key( $key ) );
		}

		private function stats_cache_set( $key, $value, $ttl = 600 ) {
			set_transient( $this->stats_cache_key( $key ), $value, $ttl );
		}

		/**
		 * Storage-clock bounds for a local calendar-day range: [ start 00:00:00, end + 1 day 00:00:00 ).
		 * The timezone shift is applied to the constants so ec_order.order_date stays indexable.
		 *
		 * @since 6.0.0
		 * @param string $start_date Y-m-d ( local ).
		 * @param string $end_date   Y-m-d ( local, inclusive ).
		 * @return array [ from, to ) as Y-m-d H:i:s strings on the storage clock.
		 */
		private function storage_bounds( $start_date, $end_date ) {
			$shift = (int) round( $this->date_diff * 3600 );
			$start = strtotime( substr( trim( (string) $start_date ), 0, 10 ) . ' 00:00:00 UTC' );
			$end   = strtotime( substr( trim( (string) $end_date ), 0, 10 ) . ' 00:00:00 UTC' );
			if ( false === $start || false === $end ) {
				return array( '1970-01-01 00:00:00', '1970-01-01 00:00:00' );
			}
			return array( gmdate( 'Y-m-d H:i:s', $start - $shift ), gmdate( 'Y-m-d H:i:s', $end + DAY_IN_SECONDS - $shift ) );
		}

		/**
		 * Approved net sales ( sub_total - discount - refund ) for one local calendar month, cached 10 minutes.
		 *
		 * @since 6.0.0
		 * @param int $year  Four-digit year.
		 * @param int $month 1-12.
		 * @return float
		 */
		private function get_month_total( $year, $month ) {
			$key    = 'month|' . sprintf( '%04d-%02d', $year, $month );
			$cached = $this->stats_cache_get( $key );
			if ( false !== $cached ) {
				return (float) $cached;
			}
			global $wpdb;
			$shift = (int) round( $this->date_diff * 3600 );
			$from  = gmdate( 'Y-m-d H:i:s', gmmktime( 0, 0, 0, $month, 1, $year ) - $shift );
			$to    = gmdate( 'Y-m-d H:i:s', gmmktime( 0, 0, 0, $month + 1, 1, $year ) - $shift );
			$total = (float) $wpdb->get_var( $wpdb->prepare(
				'SELECT ( IFNULL( SUM( ec_order.sub_total ), 0 ) - IFNULL( SUM( ec_order.discount_total ), 0 ) - IFNULL( SUM( ec_order.refund_total ), 0 ) ) FROM ec_order INNER JOIN ec_orderstatus ON ec_orderstatus.status_id = ec_order.orderstatus_id WHERE ec_order.order_date >= %s AND ec_order.order_date < %s AND ec_orderstatus.is_approved = 1',
				$from,
				$to
			) );
			$this->stats_cache_set( $key, $total, 10 * MINUTE_IN_SECONDS );
			return $total;
		}

		private function days_in_month( $month, $year ) {
			return (int) gmdate( 't', gmmktime( 0, 0, 0, (int) $month, 1, (int) $year ) );
		}

		private function get_month_sales_total( ){
			return $this->get_month_total( (int) gmdate( 'Y', $this->now_local_ts ), (int) gmdate( 'n', $this->now_local_ts ) );
		}

		private function get_month_percentage_change( ){
			/* Same month as the legacy predicate: the month that contains ( local now - 30 days ). */
			$prev_ts    = $this->now_local_ts - ( 30 * DAY_IN_SECONDS );
			$last_month = $this->get_month_total( (int) gmdate( 'Y', $prev_ts ), (int) gmdate( 'n', $prev_ts ) );
			if($last_month == null) $last_month = 0;
			$datestring = 'first day of last month';
			$dt = date_create( $datestring );
			$month = intval( date_format( $dt, 'm' ) );
			$year = intval( date_format( $dt, 'Y' ) );
			$days_last = $this->days_in_month( $month, $year );
			$days_this_month = intval( date( 'j' ) );

			if( ( $last_month / $days_last ) * $days_this_month == 0 )
				return 0;
			else
				return ( ( $this->month_sales_total / ( ( $last_month / $days_last ) * $days_this_month ) ) - 1 ) * 100; //compare total over same number of days between months
		}

		public function get_dashboard_data( $date_type, $chart_type, $product_id ){
			return $this->{"get_".$date_type."_".$chart_type}( $product_id );
		}

		private function get_daily_sales( $product_id ){
			$sales = array( );
			$product_where = "";
			if( $product_id ){
				$product_where = $this->wpdb->prepare( "AND ec_orderdetail.product_id = %d ", $product_id );
				 if( $this->date_diff < 0 ){
					$sales_data = $this->wpdb->get_results( "SELECT IFNULL( SUM( ec_orderdetail.total_price ), 0 ) as total, 0 AS discount_total, 0 AS refund_total, DATE_FORMAT( DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ), '%m/%d/%Y' ) as date, DAY( DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ) ) AS order_day, MONTH( DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ) ) AS order_month FROM ec_orderdetail LEFT JOIN ec_order ON ec_order.order_id = ec_orderdetail.order_id LEFT JOIN ec_orderstatus ON ec_order.orderstatus_id = ec_orderstatus.status_id WHERE DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ) > DATE_SUB( DATE_SUB( NOW( ), INTERVAL " . ($this->date_diff*-1) . " HOUR ), INTERVAL 14 DAY ) AND ec_orderstatus.is_approved = 1 " . $product_where . "GROUP BY order_day, order_month ORDER BY ec_order.order_date DESC" );
				}else{
					$sales_data = $this->wpdb->get_results( "SELECT IFNULL( SUM( ec_orderdetail.total_price ), 0 ) as total, SUM( ec_order.discount_total ) AS discount_total, SUM( ec_order.refund_total ) AS refund_total, DATE_FORMAT( DATE_ADD( ec_order.order_date, INTERVAL " . $this->date_diff . " HOUR ), '%m/%d/%Y' ) as date, DAY( DATE_ADD( ec_order.order_date, INTERVAL " . $this->date_diff . " HOUR ) ) AS order_day, MONTH( DATE_ADD( ec_order.order_date, INTERVAL " . $this->date_diff . " HOUR ) ) AS order_month FROM ec_orderdetail LEFT JOIN ec_order ON ec_order.order_id = ec_orderdetail.order_id LEFT JOIN ec_orderstatus ON ec_order.orderstatus_id = ec_orderstatus.status_id WHERE ec_order.order_date > DATE_SUB( DATE_ADD( NOW( ), INTERVAL " . $this->date_diff . " HOUR ), INTERVAL 14 DAY ) AND ec_orderstatus.is_approved = 1 " . $product_where . "GROUP BY order_day, order_month ORDER BY ec_order.order_date DESC" );
				}
			}else{
				if( $this->date_diff < 0 ){
					$sales_data = $this->wpdb->get_results( "SELECT SUM( ec_order.sub_total ) as total, SUM( ec_order.discount_total ) AS discount_total, SUM( ec_order.refund_total ) AS refund_total, DATE_FORMAT( DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ), '%m/%d/%Y' ) as date, DAY( DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ) ) AS order_day, MONTH( DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ) ) AS order_month FROM ec_order LEFT JOIN ec_orderstatus ON ec_order.orderstatus_id = ec_orderstatus.status_id WHERE DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ) > DATE_SUB( DATE_SUB( NOW( ), INTERVAL " . ($this->date_diff*-1) . " HOUR ), INTERVAL 14 DAY ) AND ec_orderstatus.is_approved = 1 " . $product_where . "GROUP BY order_day, order_month ORDER BY ec_order.order_date DESC" );
				}else{
					$sales_data = $this->wpdb->get_results( "SELECT SUM( ec_order.sub_total ) as total, SUM( ec_order.discount_total ) AS discount_total, SUM( ec_order.refund_total ) AS refund_total, DATE_FORMAT( DATE_ADD( ec_order.order_date, INTERVAL " . $this->date_diff . " HOUR ), '%m/%d/%Y' ) as date, DAY( DATE_ADD( ec_order.order_date, INTERVAL " . $this->date_diff . " HOUR ) ) AS order_day, MONTH( DATE_ADD( ec_order.order_date, INTERVAL " . $this->date_diff . " HOUR ) ) AS order_month FROM ec_order LEFT JOIN ec_orderstatus ON ec_order.orderstatus_id = ec_orderstatus.status_id WHERE ec_order.order_date > DATE_SUB( DATE_ADD( NOW( ), INTERVAL " . $this->date_diff . " HOUR ), INTERVAL 14 DAY ) AND ec_orderstatus.is_approved = 1 " . $product_where . "GROUP BY order_day, order_month ORDER BY ec_order.order_date DESC" );
				}
			}
			$current_index = 0;
			for( $i=0; $i<14; $i++ ){
				if( count( $sales_data ) > $current_index && $sales_data[$current_index]->date == date( 'm/d/Y', strtotime( '- ' . $i . 'day' ) ) ){
					$sales[] = $sales_data[$current_index];
					$current_index++;
				}else{
					$sales[] = (object) array( 'date' => date( 'm/d/Y', strtotime( '- ' . $i . 'day' ) ), 'total' => 0, 'discount_total' => 0, 'refund_total' => 0 );
				}
			}
			return $sales;
		}

		private function get_weekly_sales( $product_id ){
			$sales = array( );
			$product_where = "";
			if( $product_id ){
				$product_where = $this->wpdb->prepare( "AND ec_orderdetail.product_id = %d ", $product_id );
				if( $this->date_diff < 0 ){
					$sales_data = $this->wpdb->get_results( "SELECT IFNULL( SUM( ec_orderdetail.total_price ), 0 ) as total, 0 AS discount_total, 0 AS refund_total, DATE_FORMAT( DATE_SUB( DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ), INTERVAL ( DAYOFWEEK( DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ) ) - 1 ) DAY ), '%m/%d/%Y' ) as date, WEEK( DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ) ) AS order_week, YEAR( DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ) ) AS order_year FROM ec_orderdetail LEFT JOIN ec_order ON ec_order.order_id = ec_orderdetail.order_id LEFT JOIN ec_orderstatus ON ec_order.orderstatus_id = ec_orderstatus.status_id WHERE DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ) > DATE_SUB( DATE_SUB( NOW( ), INTERVAL " . ($this->date_diff*-1) . " HOUR ), INTERVAL 14 WEEK ) AND ec_orderstatus.is_approved = 1 " . $product_where . "GROUP BY date ORDER BY ec_order.order_date DESC" );
				}else{
					$sales_data = $this->wpdb->get_results( "SELECT IFNULL( SUM( ec_orderdetail.total_price ), 0 ) as total, 0 AS discount_total, 0 AS refund_total, DATE_FORMAT( DATE_SUB( DATE_ADD( ec_order.order_date, INTERVAL " . $this->date_diff . " HOUR ), INTERVAL ( DAYOFWEEK( DATE_ADD( ec_order.order_date, INTERVAL " . $this->date_diff . " HOUR ) ) - 1 ) DAY ), '%m/%d/%Y' ) as date, WEEK( DATE_ADD( ec_order.order_date, INTERVAL " . $this->date_diff . " HOUR ) ) AS order_week, YEAR( DATE_ADD( ec_order.order_date, INTERVAL " . $this->date_diff . " HOUR ) ) AS order_year FROM ec_orderdetail LEFT JOIN ec_order ON ec_order.order_id = ec_orderdetail.order_id LEFT JOIN ec_orderstatus ON ec_order.orderstatus_id = ec_orderstatus.status_id WHERE ec_order.order_date > DATE_SUB( DATE_ADD( NOW( ), INTERVAL " . $this->date_diff . " HOUR ), INTERVAL 14 WEEK ) AND ec_orderstatus.is_approved = 1 " . $product_where . "GROUP BY date ORDER BY ec_order.order_date DESC" );
				}
			}else{
				if( $this->date_diff < 0 ){
					$sales_data = $this->wpdb->get_results( "SELECT IFNULL( SUM( ec_order.sub_total ), 0 ) as total, SUM( ec_order.discount_total ) AS discount_total, SUM( ec_order.refund_total ) AS refund_total, DATE_FORMAT( DATE_SUB( DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ), INTERVAL ( DAYOFWEEK( DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ) ) - 1 ) DAY ), '%m/%d/%Y' ) as date, WEEK( DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ) ) AS order_week, YEAR( DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ) ) AS order_year FROM ec_order LEFT JOIN ec_orderstatus ON ec_order.orderstatus_id = ec_orderstatus.status_id WHERE DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ) > DATE_SUB( DATE_SUB( NOW( ), INTERVAL " . ($this->date_diff*-1) . " HOUR ), INTERVAL 14 WEEK ) AND ec_orderstatus.is_approved = 1 " . $product_where . "GROUP BY date ORDER BY ec_order.order_date DESC" );
				}else{
					$sales_data = $this->wpdb->get_results( "SELECT IFNULL( SUM( ec_order.sub_total ), 0 ) as total, SUM( ec_order.discount_total ) AS discount_total, SUM( ec_order.refund_total ) AS refund_total, DATE_FORMAT( DATE_SUB( DATE_ADD( ec_order.order_date, INTERVAL " . $this->date_diff . " HOUR ), INTERVAL ( DAYOFWEEK( DATE_ADD( ec_order.order_date, INTERVAL " . $this->date_diff . " HOUR ) ) - 1 ) DAY ), '%m/%d/%Y' ) as date, WEEK( DATE_ADD( ec_order.order_date, INTERVAL " . $this->date_diff . " HOUR ) ) AS order_week, YEAR( DATE_ADD( ec_order.order_date, INTERVAL " . $this->date_diff . " HOUR ) ) AS order_year FROM ec_order LEFT JOIN ec_orderstatus ON ec_order.orderstatus_id = ec_orderstatus.status_id WHERE ec_order.order_date > DATE_SUB( DATE_ADD( NOW( ), INTERVAL " . $this->date_diff . " HOUR ), INTERVAL 14 WEEK ) AND ec_orderstatus.is_approved = 1 " . $product_where . "GROUP BY date ORDER BY ec_order.order_date DESC" );
				}
			}
			$current_index = 0;
			for( $i=0; $i<14; $i++ ){
				if( count( $sales_data ) > $current_index && $sales_data[$current_index]->date == date( 'm/d/Y', strtotime( '- ' . $i . 'weeks', strtotime( '-' . date( 'w' ) . ' days' ) ) ) ){
					$sales[] = $sales_data[$current_index];
					$current_index++;
				}else{
					$sales[] = (object) array( 'date' => date( 'm/d/Y', strtotime( '- ' . $i . 'week', strtotime( '-' . date( 'w' ) . ' days' ) ) ), 'total' => 0, 'discount_total' => 0, 'refund_total' => 0 );
				}
			}
			return $sales;
		}

		private function get_monthly_sales( $product_id ){
			$sales = array( );
			$product_where = "";
			if( $product_id ){
				$product_where = $this->wpdb->prepare( "AND ec_orderdetail.product_id = %d ", $product_id );
				if( $this->date_diff < 0 ){
					$sales_data = $this->wpdb->get_results( "SELECT IFNULL( SUM( ec_orderdetail.total_price ), 0 ) as total, 0 AS discount_total, 0 AS refund_total, DATE_FORMAT( DATE_SUB( DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ), INTERVAL ( DAYOFMONTH( DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ) ) - 1 ) DAY ), '%m/%d/%Y' ) as date, MONTH( DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ) ) AS order_month, MONTHNAME( DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ) ) AS order_month, YEAR( DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ) ) AS order_year FROM ec_orderdetail LEFT JOIN ec_order ON ec_order.order_id = ec_orderdetail.order_id LEFT JOIN ec_orderstatus ON ec_order.orderstatus_id = ec_orderstatus.status_id WHERE DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ) > DATE_SUB( DATE_SUB( NOW( ), INTERVAL " . ($this->date_diff*-1) . " HOUR ), INTERVAL 14 MONTH ) AND ec_orderstatus.is_approved = 1 " . $product_where . "GROUP BY order_month, order_year ORDER BY ec_order.order_date DESC" );
				}else{
					$sales_data = $this->wpdb->get_results( "SELECT IFNULL( SUM( ec_orderdetail.total_price ), 0 ) as total, 0 AS discount_total, 0 AS refund_total, DATE_FORMAT( DATE_SUB( DATE_ADD( ec_order.order_date, INTERVAL " . $this->date_diff . " HOUR ), INTERVAL ( DAYOFMONTH( DATE_ADD( ec_order.order_date, INTERVAL " . $this->date_diff . " HOUR ) ) - 1 ) DAY ), '%m/%d/%Y' ) as date, MONTH( DATE_ADD( ec_order.order_date, INTERVAL " . $this->date_diff . " HOUR ) ) AS order_month, MONTHNAME( DATE_ADD( ec_order.order_date, INTERVAL " . $this->date_diff . " HOUR ) ) AS order_month, YEAR( DATE_ADD( ec_order.order_date, INTERVAL " . $this->date_diff . " HOUR ) ) AS order_year FROM ec_orderdetail LEFT JOIN ec_order ON ec_order.order_id = ec_orderdetail.order_id LEFT JOIN ec_orderstatus ON ec_order.orderstatus_id = ec_orderstatus.status_id WHERE DATE_ADD( ec_order.order_date, INTERVAL " . $this->date_diff . " HOUR ) > DATE_SUB( DATE_ADD( NOW( ), INTERVAL " . $this->date_diff . " HOUR ), INTERVAL 14 MONTH ) AND ec_orderstatus.is_approved = 1 " . $product_where . "GROUP BY order_month, order_year ORDER BY ec_order.order_date DESC" );
				}
			}else{
				if( $this->date_diff < 0 ){
					$sales_data = $this->wpdb->get_results( "SELECT IFNULL( SUM( ec_order.sub_total ), 0 ) as total, SUM( ec_order.discount_total ) AS discount_total, SUM( ec_order.refund_total ) AS refund_total, DATE_FORMAT( DATE_SUB( DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ), INTERVAL ( DAYOFMONTH( DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ) ) - 1 ) DAY ), '%m/%d/%Y' ) as date, MONTH( DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ) ) AS order_month, MONTHNAME( DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ) ) AS order_month, YEAR( DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ) ) AS order_year FROM ec_order LEFT JOIN ec_orderstatus ON ec_order.orderstatus_id = ec_orderstatus.status_id WHERE DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ) > DATE_SUB( DATE_SUB( NOW( ), INTERVAL " . ($this->date_diff*-1) . " HOUR ), INTERVAL 14 MONTH ) AND ec_orderstatus.is_approved = 1 " . $product_where . "GROUP BY order_month, order_year ORDER BY ec_order.order_date DESC" );
				}else{
					$sales_data = $this->wpdb->get_results( "SELECT IFNULL( SUM( ec_order.sub_total ), 0 ) as total, SUM( ec_order.discount_total ) AS discount_total, SUM( ec_order.refund_total ) AS refund_total, DATE_FORMAT( DATE_SUB( DATE_ADD( ec_order.order_date, INTERVAL " . $this->date_diff . " HOUR ), INTERVAL ( DAYOFMONTH( DATE_ADD( ec_order.order_date, INTERVAL " . $this->date_diff . " HOUR ) ) - 1 ) DAY ), '%m/%d/%Y' ) as date, MONTH( DATE_ADD( ec_order.order_date, INTERVAL " . $this->date_diff . " HOUR ) ) AS order_month, MONTHNAME( DATE_ADD( ec_order.order_date, INTERVAL " . $this->date_diff . " HOUR ) ) AS order_month, YEAR( DATE_ADD( ec_order.order_date, INTERVAL " . $this->date_diff . " HOUR ) ) AS order_year FROM ec_order LEFT JOIN ec_orderstatus ON ec_order.orderstatus_id = ec_orderstatus.status_id WHERE DATE_ADD( ec_order.order_date, INTERVAL " . $this->date_diff . " HOUR ) > DATE_SUB( DATE_ADD( NOW( ), INTERVAL " . $this->date_diff . " HOUR ), INTERVAL 14 MONTH ) AND ec_orderstatus.is_approved = 1 " . $product_where . "GROUP BY order_month, order_year ORDER BY ec_order.order_date DESC" );
				}
			}
			$current_index = 0;
			for( $i=0; $i<14; $i++ ){
				if( count( $sales_data ) > $current_index && $sales_data[$current_index]->date == date( 'm/d/Y', strtotime( '- ' . $i . 'months', strtotime( '-' . (date( 'd' )-1) . ' days' ) ) ) ){
					$sales[] = $sales_data[$current_index];
					$current_index++;
				}else{
					$sales[] = (object) array( 'date' => date( 'm/d/Y', strtotime( '- ' . $i . 'month', strtotime( '-' . (date( 'd' )-1) . ' days' ) ) ), 'total' => 0, 'discount_total' => 0, 'refund_total' => 0 );
				}
			}
			return $sales;
		}

		private function get_yearly_sales( $product_id ){
			$sales = array( );
			$product_where = "";
			if( $product_id ){
				$product_where = $this->wpdb->prepare( "AND ec_orderdetail.product_id = %d ", $product_id );
				if( $this->date_diff < 0 ){
					$sales_data = $this->wpdb->get_results( "SELECT IFNULL( SUM( ec_orderdetail.total_price ), 0 ) as total, 0 AS discount_total, 0 AS refund_total, DATE_FORMAT( DATE_SUB( DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ), INTERVAL ( DAYOFYEAR( DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ) ) - 1 ) DAY ), '%m/%d/%Y' ) as date, YEAR( DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ) ) AS order_year FROM ec_orderdetail LEFT JOIN ec_order ON ec_order.order_id = ec_orderdetail.order_id LEFT JOIN ec_orderstatus ON ec_order.orderstatus_id = ec_orderstatus.status_id WHERE DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ) > DATE_SUB( DATE_SUB( NOW( ), INTERVAL " . ($this->date_diff*-1) . " HOUR ), INTERVAL 14 YEAR ) AND ec_orderstatus.is_approved = 1 " . $product_where . "GROUP BY order_year ORDER BY ec_order.order_date DESC" );
				}else{
					$sales_data = $this->wpdb->get_results( "SELECT IFNULL( SUM( ec_orderdetail.total_price ), 0 ) as total, 0 AS discount_total, 0 AS refund_total, DATE_FORMAT( DATE_SUB( DATE_ADD( ec_order.order_date, INTERVAL " . $this->date_diff . " HOUR ), INTERVAL ( DAYOFYEAR( DATE_ADD( ec_order.order_date, INTERVAL " . $this->date_diff . " HOUR ) ) - 1 ) DAY ), '%m/%d/%Y' ) as date, YEAR( DATE_ADD( ec_order.order_date, INTERVAL " . $this->date_diff . " HOUR ) ) AS order_year FROM ec_orderdetail LEFT JOIN ec_order ON ec_order.order_id = ec_orderdetail.order_id LEFT JOIN ec_orderstatus ON ec_order.orderstatus_id = ec_orderstatus.status_id WHERE ec_order.order_date > DATE_SUB( DATE_ADD( NOW( ), INTERVAL " . $this->date_diff . " HOUR ), INTERVAL 14 YEAR ) AND ec_orderstatus.is_approved = 1 " . $product_where . "GROUP BY order_year ORDER BY ec_order.order_date DESC" );
				}
			}else{
				if( $this->date_diff < 0 ){
					$sales_data = $this->wpdb->get_results( "SELECT IFNULL( SUM( ec_order.sub_total ), 0 ) as total, SUM( ec_order.discount_total ) AS discount_total, SUM( ec_order.refund_total ) AS refund_total, DATE_FORMAT( DATE_SUB( DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ), INTERVAL ( DAYOFYEAR( DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ) ) - 1 ) DAY ), '%m/%d/%Y' ) as date, YEAR( DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ) ) AS order_year FROM ec_order LEFT JOIN ec_orderstatus ON ec_order.orderstatus_id = ec_orderstatus.status_id WHERE DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ) > DATE_SUB( DATE_SUB( NOW( ), INTERVAL " . ($this->date_diff*-1) . " HOUR ), INTERVAL 14 YEAR ) AND ec_orderstatus.is_approved = 1 " . $product_where . "GROUP BY order_year ORDER BY ec_order.order_date DESC" );
				}else{
					$sales_data = $this->wpdb->get_results( "SELECT IFNULL( SUM( ec_order.sub_total ), 0 ) as total, SUM( ec_order.discount_total ) AS discount_total, SUM( ec_order.refund_total ) AS refund_total, DATE_FORMAT( DATE_SUB( DATE_ADD( ec_order.order_date, INTERVAL " . $this->date_diff . " HOUR ), INTERVAL ( DAYOFYEAR( DATE_ADD( ec_order.order_date, INTERVAL " . $this->date_diff . " HOUR ) ) - 1 ) DAY ), '%m/%d/%Y' ) as date, YEAR( DATE_ADD( ec_order.order_date, INTERVAL " . $this->date_diff . " HOUR ) ) AS order_year FROM ec_order LEFT JOIN ec_orderstatus ON ec_order.orderstatus_id = ec_orderstatus.status_id WHERE ec_order.order_date > DATE_SUB( DATE_ADD( NOW( ), INTERVAL " . $this->date_diff . " HOUR ), INTERVAL 14 YEAR ) AND ec_orderstatus.is_approved = 1 " . $product_where . "GROUP BY order_year ORDER BY ec_order.order_date DESC" );
				}
			}
			$current_index = 0;
			for( $i=0; $i<14; $i++ ){
				if( count( $sales_data ) > $current_index && $sales_data[$current_index]->date == date( 'm/d/Y', strtotime( '- ' . $i . 'years', strtotime( '-' . date('z') . ' days' ) ) ) ){
					$sales[] = $sales_data[$current_index];
					$current_index++;
				}else{
					$sales[] = (object) array( 'date' => date( 'm/d/Y', strtotime( '- ' . $i . 'years', strtotime( '-' . date('z') . ' days' ) ) ), 'total' => 0, 'discount_total' => 0, 'refund_total' => 0 );
				}
			}
			return $sales;
		}

		private function get_daily_items_sold( $product_id ){
			$sales = array( );
			$product_where = "";
			if( $product_id ){
				$product_where = $this->wpdb->prepare( "AND ec_orderdetail.product_id = %d ", $product_id );
			}
			if( $this->date_diff < 0 ){
				$sales_data = $this->wpdb->get_results( "SELECT IFNULL( SUM( ec_orderdetail.quantity ), 0 ) as total, 0 AS discount_total, 0 AS refund_total, DATE_FORMAT( DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ), '%m/%d/%Y' ) as date, DAY( DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ) ) AS order_day FROM ec_orderdetail LEFT JOIN ec_order ON ec_order.order_id = ec_orderdetail.order_id LEFT JOIN ec_orderstatus ON ec_order.orderstatus_id = ec_orderstatus.status_id WHERE DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ) > DATE_SUB( DATE_SUB( NOW( ), INTERVAL " . ($this->date_diff*-1) . " HOUR ), INTERVAL 14 DAY ) AND ec_orderstatus.is_approved = 1 " . $product_where . "GROUP BY order_day ORDER BY ec_order.order_date DESC" );
			}else{
				$sales_data = $this->wpdb->get_results( "SELECT IFNULL( SUM( ec_orderdetail.quantity ), 0 ) as total, 0 AS discount_total, 0 AS refund_total, DATE_FORMAT( DATE_ADD( ec_order.order_date, INTERVAL " . $this->date_diff . " HOUR ), '%m/%d/%Y' ) as date, DAY( DATE_ADD( ec_order.order_date, INTERVAL " . $this->date_diff . " HOUR ) ) AS order_day FROM ec_orderdetail LEFT JOIN ec_order ON ec_order.order_id = ec_orderdetail.order_id LEFT JOIN ec_orderstatus ON ec_order.orderstatus_id = ec_orderstatus.status_id WHERE ec_order.order_date > DATE_SUB( DATE_ADD( NOW( ), INTERVAL " . $this->date_diff . " HOUR ), INTERVAL 14 DAY ) AND ec_orderstatus.is_approved = 1 " . $product_where . "GROUP BY order_day ORDER BY ec_order.order_date DESC" );
			}
			$current_index = 0;
			for( $i=0; $i<14; $i++ ){
				if( count( $sales_data ) > $current_index && $sales_data[$current_index]->date == date( 'm/d/Y', strtotime( '- ' . $i . 'day' ) ) ){
					$sales[] = $sales_data[$current_index];
					$current_index++;
				}else{
					$sales[] = (object) array( 'date' => date( 'm/d/Y', strtotime( '- ' . $i . 'day' ) ), 'total' => 0, 'discount_total' => 0, 'refund_total' => 0 );
				}
			}
			return $sales;
		}

		private function get_weekly_items_sold( $product_id ){
			$sales = array( );
			$product_where = "";
			if( $product_id ){
				$product_where = $this->wpdb->prepare( "AND ec_orderdetail.product_id = %d ", $product_id );
			}
			if( $this->date_diff < 0 ){
				$sales_data = $this->wpdb->get_results( "SELECT IFNULL( SUM( ec_orderdetail.quantity ), 0 ) as total, 0 AS discount_total, 0 AS refund_total, DATE_FORMAT( DATE_SUB( DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ), INTERVAL ( DAYOFWEEK( DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ) ) - 1 ) DAY ), '%m/%d/%Y' ) as date, WEEK( DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ) ) AS order_week, YEAR( DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ) ) AS order_year FROM ec_orderdetail LEFT JOIN ec_order ON ec_order.order_id = ec_orderdetail.order_id LEFT JOIN ec_orderstatus ON ec_order.orderstatus_id = ec_orderstatus.status_id WHERE ec_order.order_date > DATE_SUB( DATE_SUB( NOW( ), INTERVAL " . ($this->date_diff*-1) . " HOUR ), INTERVAL 14 WEEK ) AND ec_orderstatus.is_approved = 1 " . $product_where . "GROUP BY order_week, order_year ORDER BY ec_order.order_date DESC" );
			}else{
				$sales_data = $this->wpdb->get_results( "SELECT IFNULL( SUM( ec_orderdetail.quantity ), 0 ) as total, 0 AS discount_total, 0 AS refund_total, DATE_FORMAT( DATE_SUB( DATE_ADD( ec_order.order_date, INTERVAL " . $this->date_diff . " HOUR ), INTERVAL ( DAYOFWEEK( DATE_ADD( ec_order.order_date, INTERVAL " . $this->date_diff . " HOUR ) ) - 1 ) DAY ), '%m/%d/%Y' ) as date, WEEK( DATE_ADD( ec_order.order_date, INTERVAL " . $this->date_diff . " HOUR ) ) AS order_week, YEAR( DATE_ADD( ec_order.order_date, INTERVAL " . $this->date_diff . " HOUR ) ) AS order_year FROM ec_orderdetail LEFT JOIN ec_order ON ec_order.order_id = ec_orderdetail.order_id LEFT JOIN ec_orderstatus ON ec_order.orderstatus_id = ec_orderstatus.status_id WHERE ec_order.order_date > DATE_SUB( DATE_ADD( NOW( ), INTERVAL " . $this->date_diff . " HOUR ), INTERVAL 14 WEEK ) AND ec_orderstatus.is_approved = 1 " . $product_where . "GROUP BY order_week, order_year ORDER BY ec_order.order_date DESC" );
			}
			$current_index = 0;
			for( $i=0; $i<14; $i++ ){
				if( count( $sales_data ) > $current_index && $sales_data[$current_index]->date == date( 'm/d/Y', strtotime( '- ' . $i . 'weeks', strtotime( '-' . date( 'w' ) . ' days' ) ) ) ){
					$sales[] = $sales_data[$current_index];
					$current_index++;
				}else{
					$sales[] = (object) array( 'date' => date( 'm/d/Y', strtotime( '- ' . $i . 'week', strtotime( '-' . date( 'w' ) . ' days' ) ) ), 'total' => 0, 'discount_total' => 0, 'refund_total' => 0 );
				}
			}
			return $sales;
		}

		private function get_monthly_items_sold( $product_id ){
			$sales = array( );
			$product_where = "";
			if( $product_id ){
				$product_where = $this->wpdb->prepare( "AND ec_orderdetail.product_id = %d ", $product_id );
			}
			if( $this->date_diff < 0 ){
				$sales_data = $this->wpdb->get_results( "SELECT IFNULL( SUM( ec_orderdetail.quantity ), 0 ) as total, 0 AS discount_total, 0 AS refund_total, DATE_FORMAT( DATE_SUB( DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ), INTERVAL ( DAYOFMONTH( DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ) ) - 1 ) DAY ), '%m/%d/%Y' ) as date, MONTHNAME( DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ) ) AS order_month, MONTH( DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ) ) AS order_month, YEAR( DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ) ) AS order_year FROM ec_orderdetail LEFT JOIN ec_order ON ec_order.order_id = ec_orderdetail.order_id LEFT JOIN ec_orderstatus ON ec_order.orderstatus_id = ec_orderstatus.status_id WHERE ec_order.order_date > DATE_SUB( DATE_SUB( NOW( ), INTERVAL " . ($this->date_diff*-1) . " HOUR ), INTERVAL 14 MONTH ) AND ec_orderstatus.is_approved = 1 " . $product_where . "GROUP BY order_month, order_year ORDER BY ec_order.order_date DESC" );
			}else{
				$sales_data = $this->wpdb->get_results( "SELECT IFNULL( SUM( ec_orderdetail.quantity ), 0 ) as total, 0 AS discount_total, 0 AS refund_total, DATE_FORMAT( DATE_SUB( DATE_ADD( ec_order.order_date, INTERVAL " . $this->date_diff . " HOUR ), INTERVAL ( DAYOFMONTH( DATE_ADD( ec_order.order_date, INTERVAL " . $this->date_diff . " HOUR ) ) - 1 ) DAY ), '%m/%d/%Y' ) as date, MONTHNAME( DATE_ADD( ec_order.order_date, INTERVAL " . $this->date_diff . " HOUR ) ) AS order_month, MONTH( DATE_ADD( ec_order.order_date, INTERVAL " . $this->date_diff . " HOUR ) ) AS order_month, YEAR( DATE_ADD( ec_order.order_date, INTERVAL " . $this->date_diff . " HOUR ) ) AS order_year FROM ec_orderdetail LEFT JOIN ec_order ON ec_order.order_id = ec_orderdetail.order_id LEFT JOIN ec_orderstatus ON ec_order.orderstatus_id = ec_orderstatus.status_id WHERE ec_order.order_date > DATE_SUB( DATE_ADD( NOW( ), INTERVAL " . $this->date_diff . " HOUR ), INTERVAL 14 MONTH ) AND ec_orderstatus.is_approved = 1 " . $product_where . "GROUP BY order_month, order_year ORDER BY ec_order.order_date DESC" );
			}
			$current_index = 0;
			for( $i=0; $i<14; $i++ ){
				if( count( $sales_data ) > $current_index && $sales_data[$current_index]->date == date( 'm/d/Y', strtotime( '- ' . $i . 'months', strtotime( '-' . (date( 'd' )-1) . ' days' ) ) ) ){
					$sales[] = $sales_data[$current_index];
					$current_index++;
				}else{
					$sales[] = (object) array( 'date' => date( 'm/d/Y', strtotime( '- ' . $i . 'month', strtotime( '-' . (date( 'd' )-1) . ' days' ) ) ), 'total' => 0, 'discount_total' => 0, 'refund_total' => 0 );
				}
			}
			return $sales;
		}

		private function get_yearly_items_sold( $product_id ){
			$sales = array( );
			$product_where = "";
			if( $product_id ){
				$product_where = $this->wpdb->prepare( "AND ec_orderdetail.product_id = %d ", $product_id );
			}
			if( $this->date_diff < 0 ){
				$sales_data = $this->wpdb->get_results( "SELECT IFNULL( SUM( ec_orderdetail.quantity ), 0 ) as total, 0 AS discount_total, 0 AS refund_total, DATE_FORMAT( DATE_SUB( DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ), INTERVAL ( DAYOFYEAR( DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ) ) - 1 ) DAY ), '%m/%d/%Y' ) as date, YEAR( DATE_SUB( ec_order.order_date, INTERVAL " . ($this->date_diff*-1) . " HOUR ) ) AS order_year FROM ec_orderdetail LEFT JOIN ec_order ON ec_order.order_id = ec_orderdetail.order_id LEFT JOIN ec_orderstatus ON ec_order.orderstatus_id = ec_orderstatus.status_id WHERE ec_order.order_date > DATE_SUB( DATE_SUB( NOW( ), INTERVAL " . ($this->date_diff*-1) . " HOUR ), INTERVAL 14 YEAR ) AND ec_orderstatus.is_approved = 1 " . $product_where . "GROUP BY order_year ORDER BY ec_order.order_date DESC" );
			}else{
				$sales_data = $this->wpdb->get_results( "SELECT IFNULL( SUM( ec_orderdetail.quantity ), 0 ) as total, 0 AS discount_total, 0 AS refund_total, DATE_FORMAT( DATE_SUB( DATE_ADD( ec_order.order_date, INTERVAL " . $this->date_diff . " HOUR ), INTERVAL ( DAYOFYEAR( DATE_ADD( ec_order.order_date, INTERVAL " . $this->date_diff . " HOUR ) ) - 1 ) DAY ), '%m/%d/%Y' ) as date, YEAR( DATE_ADD( ec_order.order_date, INTERVAL " . $this->date_diff . " HOUR ) ) AS order_year FROM ec_orderdetail LEFT JOIN ec_order ON ec_order.order_id = ec_orderdetail.order_id LEFT JOIN ec_orderstatus ON ec_order.orderstatus_id = ec_orderstatus.status_id WHERE ec_order.order_date > DATE_SUB( DATE_ADD( NOW( ), INTERVAL " . $this->date_diff . " HOUR ), INTERVAL 14 YEAR ) AND ec_orderstatus.is_approved = 1 " . $product_where . "GROUP BY order_year ORDER BY ec_order.order_date DESC" );
			}
			$current_index = 0;
			for( $i=0; $i<14; $i++ ){
				if( count( $sales_data ) > $current_index && $sales_data[$current_index]->date == date( 'm/d/Y', strtotime( '- ' . $i . 'years', strtotime( '-' . date('z') . ' days' ) ) ) ){
					$sales[] = $sales_data[$current_index];
					$current_index++;
				}else{
					$sales[] = (object) array( 'date' => date( 'm/d/Y', strtotime( '- ' . $i . 'years', strtotime( '-' . date('z') . ' days' ) ) ), 'total' => 0, 'discount_total' => 0, 'refund_total' => 0 );
				}
			}
			return $sales;
		}

		private function get_daily_abandonment( $product_id ){
			$sales = array( );
			$product_where = "";
			if( $product_id ){
				$product_where = $this->wpdb->prepare( "AND ec_tempcart.product_id = %d ", $product_id );
			}
			if( $this->date_diff < 0 ){
				$sales_data = $this->wpdb->get_results( "SELECT COUNT( ec_tempcart.session_id ) as total, 0 AS discount_total, 0 AS refund_total, DATE_FORMAT( ec_tempcart.last_changed_date, '%m/%d/%Y') as date, DAY( ec_tempcart.last_changed_date ) as cart_day, MONTH( ec_tempcart.last_changed_date ) as cart_month FROM ec_tempcart WHERE ec_tempcart.last_changed_date > DATE_SUB( DATE_SUB( NOW( ), INTERVAL " . ($this->date_diff*-1) . " HOUR ), INTERVAL 14 DAY ) " . $product_where . "GROUP BY cart_day, cart_month ORDER BY ec_tempcart.last_changed_date DESC" );
			}else{
				$sales_data = $this->wpdb->get_results( "SELECT COUNT( ec_tempcart.session_id ) as total, 0 AS discount_total, 0 AS refund_total, DATE_FORMAT( ec_tempcart.last_changed_date, '%m/%d/%Y') as date, DAY( ec_tempcart.last_changed_date ) as cart_day, MONTH( ec_tempcart.last_changed_date ) as cart_month FROM ec_tempcart WHERE ec_tempcart.last_changed_date > DATE_SUB( DATE_ADD( NOW( ), INTERVAL " . $this->date_diff . " HOUR ), INTERVAL 14 DAY ) " . $product_where . "GROUP BY cart_day, cart_month ORDER BY ec_tempcart.last_changed_date DESC" );
			}
			$current_index = 0;
			for( $i=0; $i<14; $i++ ){
				if( count( $sales_data ) > $current_index && $sales_data[$current_index]->date == date( 'm/d/Y', strtotime( '- ' . $i . 'day' ) ) ){
					$sales[] = $sales_data[$current_index];
					$current_index++;
				}else{
					$sales[] = (object) array( 'date' => date( 'm/d/Y', strtotime( '- ' . $i . 'day' ) ), 'total' => 0, 'discount_total' => 0, 'refund_total' => 0 );
				}
			}
			return $sales;
		}

		private function get_weekly_abandonment( $product_id ){
			$sales = array( );
			$product_where = "";
			if( $product_id ){
				$product_where = $this->wpdb->prepare( "AND ec_tempcart.product_id = %d ", $product_id );
			}
			if( $this->date_diff < 0 ){
				$sales_data = $this->wpdb->get_results( "SELECT COUNT( ec_tempcart.session_id ) as total, 0 AS discount_total, 0 AS refund_total, DATE_FORMAT( DATE_SUB( ec_tempcart.last_changed_date, INTERVAL ( DAYOFWEEK( ec_tempcart.last_changed_date ) - 1 ) DAY ), '%m/%d/%Y' ) as date, WEEK( ec_tempcart.last_changed_date ) as cart_week, YEAR( ec_tempcart.last_changed_date ) as cart_year FROM ec_tempcart WHERE ec_tempcart.last_changed_date > DATE_SUB( DATE_SUB( NOW( ), INTERVAL " . ($this->date_diff*-1) . " HOUR ), INTERVAL 14 WEEK ) " . $product_where . "GROUP BY cart_week, cart_year ORDER BY ec_tempcart.last_changed_date DESC" );
			}else{
				$sales_data = $this->wpdb->get_results( "SELECT COUNT( ec_tempcart.session_id ) as total, 0 AS discount_total, 0 AS refund_total, DATE_FORMAT( DATE_SUB( ec_tempcart.last_changed_date, INTERVAL ( DAYOFWEEK( ec_tempcart.last_changed_date ) - 1 ) DAY ), '%m/%d/%Y' ) as date, WEEK( ec_tempcart.last_changed_date ) as cart_week, YEAR( ec_tempcart.last_changed_date ) as cart_year FROM ec_tempcart WHERE ec_tempcart.last_changed_date > DATE_SUB( DATE_ADD( NOW( ), INTERVAL " . $this->date_diff . " HOUR ), INTERVAL 14 WEEK ) " . $product_where . "GROUP BY cart_week, cart_year ORDER BY ec_tempcart.last_changed_date DESC" );
			}
			$current_index = 0;
			for( $i=0; $i<14; $i++ ){
				if( count( $sales_data ) > $current_index && $sales_data[$current_index]->date == date( 'm/d/Y', strtotime( '- ' . $i . 'weeks', strtotime( '-' . date( 'w' ) . ' days' ) ) ) ){
					$sales[] = $sales_data[$current_index];
					$current_index++;
				}else{
					$sales[] = (object) array( 'date' => date( 'm/d/Y', strtotime( '- ' . $i . 'week', strtotime( '-' . date( 'w' ) . ' days' ) ) ), 'total' => 0, 'discount_total' => 0, 'refund_total' => 0 );
				}
			}
			return $sales;
		}

		private function get_monthly_abandonment( $product_id ){
			$sales = array( );
			$product_where = "";
			if( $product_id ){
				$product_where = $this->wpdb->prepare( "AND ec_tempcart.product_id = %d ", $product_id );
			}
			if( $this->date_diff < 0 ){
				$sales_data = $this->wpdb->get_results( "SELECT COUNT( ec_tempcart.session_id ) as total, 0 AS discount_total, 0 AS refund_total, DATE_FORMAT( DATE_SUB( ec_tempcart.last_changed_date, INTERVAL ( DAYOFMONTH( ec_tempcart.last_changed_date ) - 1 ) DAY ), '%m/%d/%Y' ) as date, MONTHNAME( ec_tempcart.last_changed_date ) AS cart_month, MONTH( ec_tempcart.last_changed_date ) as cart_month, YEAR( ec_tempcart.last_changed_date ) as cart_year FROM ec_tempcart WHERE ec_tempcart.last_changed_date > DATE_SUB( DATE_SUB( NOW( ), INTERVAL " . ($this->date_diff*-1) . " HOUR ), INTERVAL 14 MONTH ) " . $product_where . "GROUP BY cart_month, cart_year ORDER BY ec_tempcart.last_changed_date DESC" );
			}else{
				$sales_data = $this->wpdb->get_results( "SELECT COUNT( ec_tempcart.session_id ) as total, 0 AS discount_total, 0 AS refund_total, DATE_FORMAT( DATE_SUB( ec_tempcart.last_changed_date, INTERVAL ( DAYOFMONTH( ec_tempcart.last_changed_date ) - 1 ) DAY ), '%m/%d/%Y' ) as date, MONTHNAME( ec_tempcart.last_changed_date ) AS cart_month, MONTH( ec_tempcart.last_changed_date ) as cart_month, YEAR( ec_tempcart.last_changed_date ) as cart_year FROM ec_tempcart WHERE ec_tempcart.last_changed_date > DATE_SUB( DATE_ADD( NOW( ), INTERVAL " . $this->date_diff . " HOUR ), INTERVAL 14 MONTH ) " . $product_where . "GROUP BY cart_month, cart_year ORDER BY ec_tempcart.last_changed_date DESC" );
			}
			$current_index = 0;
			for( $i=0; $i<14; $i++ ){
				if( count( $sales_data ) > $current_index && $sales_data[$current_index]->date == date( 'm/d/Y', strtotime( '- ' . $i . 'months', strtotime( '-' . (date( 'd' )-1) . ' days' ) ) ) ){
					$sales[] = $sales_data[$current_index];
					$current_index++;
				}else{
					$sales[] = (object) array( 'date' => date( 'm/d/Y', strtotime( '- ' . $i . 'month', strtotime( '-' . (date( 'd' )-1) . ' days' ) ) ), 'total' => 0, 'discount_total' => 0, 'refund_total' => 0 );
				}
			}
			return $sales;
		}

		private function get_yearly_abandonment( $product_id ){
			$sales = array( );
			$product_where = "";
			if( $product_id ){
				$product_where = $this->wpdb->prepare( "AND ec_tempcart.product_id = %d ", $product_id );
			}
			if( $this->date_diff < 0 ){
				$sales_data = $this->wpdb->get_results( "SELECT COUNT( ec_tempcart.session_id ) as total, 0 AS discount_total, 0 AS refund_total, DATE_FORMAT( DATE_SUB( ec_tempcart.last_changed_date, INTERVAL ( DAYOFYEAR( ec_tempcart.last_changed_date ) - 1 ) DAY ), '%m/%d/%Y' ) as date, YEAR( ec_tempcart.last_changed_date ) as cart_year FROM ec_tempcart WHERE ec_tempcart.last_changed_date > DATE_SUB( DATE_SUB( NOW( ), INTERVAL " . ($this->date_diff*-1) . " HOUR ), INTERVAL 14 YEAR ) " . $product_where . "GROUP BY cart_year ORDER BY ec_tempcart.last_changed_date DESC" );
			}else{
				$sales_data = $this->wpdb->get_results( "SELECT COUNT( ec_tempcart.session_id ) as total, 0 AS discount_total, 0 AS refund_total, DATE_FORMAT( DATE_SUB( ec_tempcart.last_changed_date, INTERVAL ( DAYOFYEAR( ec_tempcart.last_changed_date ) - 1 ) DAY ), '%m/%d/%Y' ) as date, YEAR( ec_tempcart.last_changed_date ) as cart_year FROM ec_tempcart WHERE ec_tempcart.last_changed_date > DATE_SUB( DATE_ADD( NOW( ), INTERVAL " . $this->date_diff . " HOUR ), INTERVAL 14 YEAR ) " . $product_where . "GROUP BY cart_year ORDER BY ec_tempcart.last_changed_date DESC" );
			}
			$current_index = 0;
			for( $i=0; $i<14; $i++ ){
				if( count( $sales_data ) > $current_index && $sales_data[$current_index]->date == date( 'm/d/Y', strtotime( '- ' . $i . 'years', strtotime( '-' . date('z') . ' days' ) ) ) ){
					$sales[] = $sales_data[$current_index];
					$current_index++;
				}else{
					$sales[] = (object) array( 'date' => date( 'm/d/Y', strtotime( '- ' . $i . 'years', strtotime( '-' . date('z') . ' days' ) ) ), 'total' => 0, 'discount_total' => 0, 'refund_total' => 0 );
				}
			}
			return $sales;
		}

		private function get_total_new_orders( ){
			/* 6.0.0: local( order_date ) > local( now ) - 1 week  <=>  order_date > storage now - 1 week ( indexable ). */
			global $wpdb;
			$since = gmdate( 'Y-m-d H:i:s', $this->now_storage_ts - WEEK_IN_SECONDS );
			return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT( ec_order.order_id ) as total FROM ec_order WHERE ec_order.order_date > %s', $since ) );
		}

		private function get_total_new_reviews( ){
			return $this->wpdb->get_var( "SELECT COUNT( ec_review.review_id ) as total FROM ec_review WHERE ec_review.approved = 0" );
		}

		private function get_total_cart_users( ){
			return $this->wpdb->get_var( "SELECT COUNT( ec_user.user_id ) as total FROM ec_user" );
		}

		private function get_sales_dataset( $start_date, $end_date, $range = 'daily', $product_id = false, $country = false, $billing_country = false, $location_id = 0 ){
			$days_length = $this->get_sales_dataset_length( $start_date, $end_date, $range );
			if( $range == 'daily' && $days_length <= 2 ){
				$days_length = ($days_length+1) * 24;
				$range = 'hourly';
			}

			$product_where = "";
			$country_where = "";
			$billing_country_where = "";
			$location_id_where = '';
			if( $product_id ){
				$product_where = $this->wpdb->prepare( "AND ec_orderdetail.product_id = %d ", $product_id );
			}
			if( $country ){
				$country_where = $this->wpdb->prepare( "AND ec_order.shipping_country = %s ", $country );
			}
			if( $billing_country ){
				$billing_country_where = $this->wpdb->prepare( "AND ec_order.billing_country = %s ", $billing_country );
			}
			if( $location_id ){
				$location_id_where = $this->wpdb->prepare( "AND ec_order.location_id = %d ", $location_id );
			}

			if( $range == 'hourly' ){
				$groupby = 'order_hour, order_day';
			}else if( $range == 'daily' ){
				$groupby = 'order_day, order_month';
			}else if( $range == 'weekly' ){
				$groupby = 'order_week, order_year';
			}else if( $range == 'monthly' ){
				$groupby = 'order_month, order_year';
			}else if( $range == 'yearly' ){
				$groupby = 'order_year';
			}

			$date_diff_func = ( $this->date_diff < 0 ) ? 'DATE_SUB' : 'DATE_ADD';
			$date_diff      = abs( (int) round( $this->date_diff * 60 ) );
			list( $from, $to ) = $this->storage_bounds( $start_date, $end_date );
			$range_where    = $this->wpdb->prepare( 'ec_order.order_date >= %s AND ec_order.order_date < %s', $from, $to );
			$item_join      = '';
			$item_total_sql = 'SUM( ec_order.sub_total )';
			if ( $product_id ) {
				/* One grouped pass over the line items in range replaces the per-order correlated subquery. */
				$item_join      = $this->wpdb->prepare( 'LEFT JOIN ( SELECT ec_orderdetail.order_id, SUM( ec_orderdetail.total_price ) AS item_total FROM ec_orderdetail INNER JOIN ec_order AS od_order ON od_order.order_id = ec_orderdetail.order_id WHERE od_order.order_date >= %s AND od_order.order_date < %s AND ec_orderdetail.product_id = %d GROUP BY ec_orderdetail.order_id ) AS od ON od.order_id = ec_order.order_id', $from, $to, $product_id );
				$item_total_sql = 'SUM( IFNULL( od.item_total, 0 ) )';
			}

			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- every variable fragment is prepared above or is a function name / integer minute offset / GROUP BY keyword list built from fixed strings.
			$sales_data = $this->wpdb->get_results( "SELECT
					SUM( ec_order.sub_total ) as total,
					" . $item_total_sql . " as item_total,
					SUM( ec_order.tax_total ) AS tax_total,
					SUM( ec_order.vat_total ) AS vat_total,
					SUM( ec_order.gst_total ) AS gst_total,
					SUM( ec_order.hst_total ) AS hst_total,
					SUM( ec_order.pst_total ) AS pst_total,
					SUM( ec_order.shipping_total ) AS shipping_total,
					SUM( ec_order.discount_total ) AS discount_total,
					SUM( ec_order.refund_total ) AS refund_total,
					DATE_FORMAT( " . $date_diff_func . "( ec_order.order_date, INTERVAL " . $date_diff . " MINUTE ), '%m/%d/%Y' ) as date,
					HOUR( " . $date_diff_func . "( ec_order.order_date, INTERVAL " . $date_diff . " MINUTE ) ) AS order_hour,
					DAY( " . $date_diff_func . "( ec_order.order_date, INTERVAL " . $date_diff . " MINUTE ) ) AS order_day,
					WEEK( " . $date_diff_func . "( ec_order.order_date, INTERVAL " . $date_diff . " MINUTE ) ) AS order_week,
					MONTH( " . $date_diff_func . "( ec_order.order_date, INTERVAL " . $date_diff . " MINUTE ) ) AS order_month,
					YEAR( " . $date_diff_func . "( ec_order.order_date, INTERVAL " . $date_diff . " MINUTE ) ) AS order_year
				FROM
					ec_order
					INNER JOIN ec_orderstatus ON ec_order.orderstatus_id = ec_orderstatus.status_id
					" . $item_join . "
				WHERE
					" . $range_where . " AND
					ec_orderstatus.is_approved = 1 " . $country_where . " " . $billing_country_where . " " . $location_id_where . "
				GROUP BY
					" . $groupby . "
				ORDER BY
					MIN( ec_order.order_date ) ASC"
			);
			// phpcs:enable

			$sales = array( );
			for( $i=0; $i<=$days_length; $i++ ){
				$found = false;
				if( $range == 'hourly' ){
					$test_day = date( 'm/d/Y H:m', strtotime( '+' . $i . ' hours', strtotime( $start_date ) ) );
				}else if( $range == 'daily' ){
					$test_day = date( 'm/d/Y', strtotime( '+' . $i . ' days', strtotime( $start_date ) ) );
				}else if( $range == 'weekly' ){
					$test_day = date( 'm/d/Y', strtotime( 'sunday last week', strtotime( '+' . ($i*7) . ' day', strtotime( $start_date ) ) ) );
				}else if( $range == 'monthly' ){
					$test_day = date( 'm/d/Y',  strtotime( 'first day of this month', strtotime( '+' . $i . ' month', strtotime( $start_date ) ) ) );
				}else if( $range == 'yearly' ){
					$test_day = date( 'm/d/Y', strtotime( '+' . $i . ' year', strtotime( $start_date ) ) );
				}
				for( $j=0; $j<count( $sales_data ) && !$found; $j++ ){
					if( $range == 'hourly' && $test_day == date( 'm/d/Y H:m', strtotime( $sales_data[$j]->date . ' ' . $sales_data[$j]->order_hour . ':00' ) ) ){
						$sales_data[$j]->date = date( 'm/d/Y H:m', strtotime( $sales_data[$j]->date . ' ' . $sales_data[$j]->order_hour . ':00' ) );
						$sales[] = $sales_data[$j];
						$found = true;
					}else if( $range == 'weekly' && $test_day == date( 'm/d/Y', strtotime( 'sunday last week', strtotime( $sales_data[$j]->date ) ) ) ){
						$sales_data[$j]->date = date( 'm/d/Y', strtotime( 'sunday last week', strtotime( $sales_data[$j]->date ) ) );
						$sales[] = $sales_data[$j];
						$found = true;
					}else if( $range == 'monthly' && $test_day == date( 'm/d/Y', strtotime( 'first day of this month', strtotime( $sales_data[$j]->date ) ) ) ){
						$sales_data[$j]->date = date( 'm/d/Y', strtotime( 'first day of this month', strtotime( $sales_data[$j]->date ) ) );
						$sales[] = $sales_data[$j];
						$found = true;
					}else if( $range == 'yearly' && $test_day == date( 'm/d/Y', strtotime( 'first day of january', strtotime( $sales_data[$j]->date ) ) ) ){
						$sales_data[$j]->date = date( 'm/d/Y', strtotime( 'first day of january', strtotime( $sales_data[$j]->date ) ) );
						$sales[] = $sales_data[$j];
						$found = true;
					}else if( $test_day == $sales_data[$j]->date ){
						$sales[] = $sales_data[$j];
						$found = true;
					}
				}
				if( !$found ){
					if( $range == 'hourly' ){
						$sales[] = (object) array(
							'total'	=> 0,
							'discount_total' => 0,
							'refund_total' => 0,
							'shipping_total' => 0,
							'tax_total' => 0,
							'vat_total' => 0,
							'gst_total' => 0,
							'hst_total' => 0,
							'pst_total' => 0,
							'item_total' => 0,
							'date' => date( 'm/d/Y H:m', strtotime( '+' . $i . ' hours', strtotime( $start_date ) ) ),
							'order_day'	=> date( 'd', strtotime( '+' . $i . ' hours', strtotime( $start_date ) ) ),
							'order_month' => date( 'm', strtotime( '+' . $i . ' hours', strtotime( $start_date ) ) )
						);
					}else if( $range == 'daily' ){
						$sales[] = (object) array(
							'total'	=> 0,
							'discount_total' => 0,
							'refund_total' => 0,
							'shipping_total' => 0,
							'tax_total' => 0,
							'vat_total' => 0,
							'gst_total' => 0,
							'hst_total' => 0,
							'pst_total' => 0,
							'item_total' => 0,
							'date' => date( 'm/d/Y', strtotime( '+' . $i . ' days', strtotime( $start_date ) ) ),
							'order_day'	=> date( 'd', strtotime( '+' . $i . ' days', strtotime( $start_date ) ) ),
							'order_month' => date( 'm', strtotime( '+' . $i . ' days', strtotime( $start_date ) ) )
						);
					}else if( $range == 'weekly' ){
						$sales[] = (object) array(
							'total'	=> 0,
							'discount_total' => 0,
							'refund_total' => 0,
							'shipping_total' => 0,
							'tax_total' => 0,
							'vat_total' => 0,
							'gst_total' => 0,
							'hst_total' => 0,
							'pst_total' => 0,
							'item_total' => 0,
							'date' => date( 'm/d/Y', strtotime( '+' . ($i*7) . ' days', strtotime( $start_date ) ) ),
							'order_day'	=> date( 'd', strtotime( '+' . ($i*7) . ' days', strtotime( $start_date ) ) ),
							'order_month' => date( 'm', strtotime( '+' . ($i*7) . ' days', strtotime( $start_date ) ) )
						);
					}else if( $range == 'monthly' ){
						$sales[] = (object) array(
							'total'	=> 0,
							'discount_total' => 0,
							'refund_total' => 0,
							'shipping_total' => 0,
							'tax_total' => 0,
							'vat_total' => 0,
							'gst_total' => 0,
							'hst_total' => 0,
							'pst_total' => 0,
							'item_total' => 0,
							'date' => date( 'm/d/Y', strtotime( '+' . $i . ' months', strtotime( $start_date ) ) ),
							'order_day'	=> date( 'd', strtotime( '+' . $i . ' months', strtotime( $start_date ) ) ),
							'order_month' => date( 'm', strtotime( '+' . $i . ' months', strtotime( $start_date ) ) )
						);
					}else if( $range == 'yearly' ){
						$sales[] = (object) array(
							'total'	=> 0,
							'discount_total' => 0,
							'refund_total' => 0,
							'shipping_total' => 0,
							'tax_total' => 0,
							'vat_total' => 0,
							'gst_total' => 0,
							'hst_total' => 0,
							'pst_total' => 0,
							'item_total' => 0,
							'date' => date( 'm/d/Y', strtotime( '+' . $i . ' years', strtotime( $start_date ) ) ),
							'order_day'	=> date( 'd', strtotime( '+' . $i . ' years', strtotime( $start_date ) ) ),
							'order_month' => date( 'm', strtotime( '+' . $i . ' years', strtotime( $start_date ) ) )
						);
					}
				}
			}

			$data_items = array( );
			for( $i=0; $i<count( $sales ); $i++ ){
				if( $product_id ){
					$data_items[] =  $sales[$i]->item_total;
				}else{
					$data_items[] = ( $sales[$i]->total + $sales[$i]->shipping_total + $sales[$i]->tax_total + $sales[$i]->vat_total + $sales[$i]->gst_total + $sales[$i]->hst_total + $sales[$i]->pst_total - $sales[$i]->discount_total - $sales[$i]->refund_total );
				}
			}

			return $data_items;
		}

		private function get_items_dataset( $start_date, $end_date, $range = 'daily', $product_id = false, $country = false, $billing_country = false, $location_id = 0 ){
			$days_length = $this->get_sales_dataset_length( $start_date, $end_date, $range );
			if( $range == 'daily' && $days_length <= 2 ){
				$days_length = ($days_length+1) * 24;
				$range = 'hourly';
			}

			$product_where = "";
			$country_where = "";
			$billing_country_where = "";
			$location_id_where = '';
			if( $product_id ){
				$product_where = $this->wpdb->prepare( "AND ec_orderdetail.product_id = %d ", $product_id );
			}
			if( $country ){
				$country_where = $this->wpdb->prepare( "AND ec_order.shipping_country = %s ", $country );
			}
			if( $billing_country ){
				$billing_country_where = $this->wpdb->prepare( "AND ec_order.billing_country = %s ", $billing_country );
			}
			if( $location_id ){
				$location_id_where = $this->wpdb->prepare( "AND ec_order.location_id = %d ", $location_id );
			}

			if( $range == 'hourly' ){
				$groupby = 'order_hour, order_day';
			}else if( $range == 'daily' ){
				$groupby = 'order_day, order_month';
			}else if( $range == 'weekly' ){
				$groupby = 'order_week, order_year';
			}else if( $range == 'monthly' ){
				$groupby = 'order_month, order_year';
			}else if( $range == 'yearly' ){
				$groupby = 'order_year';
			}

			$date_diff_func = ( $this->date_diff < 0 ) ? 'DATE_SUB' : 'DATE_ADD';
			$date_diff      = abs( (int) round( $this->date_diff * 60 ) );
			list( $from, $to ) = $this->storage_bounds( $start_date, $end_date );
			$range_where    = $this->wpdb->prepare( 'ec_order.order_date >= %s AND ec_order.order_date < %s', $from, $to );

			/* Quantity is summed straight off the joined line rows ( the legacy per-order subquery re-added the
			   whole order's quantity once per line, over-counting multi-line orders ). */
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- every variable fragment is prepared above or is a function name / integer minute offset / GROUP BY keyword list built from fixed strings.
			$sales_data = $this->wpdb->get_results( "SELECT
					IFNULL( SUM( ec_orderdetail.quantity ), 0 ) as total,
					0 AS discount_total,
					0 AS refund_total,
					DATE_FORMAT( " . $date_diff_func . "( ec_order.order_date, INTERVAL " . $date_diff . " MINUTE ), '%m/%d/%Y' ) as date,
					HOUR( " . $date_diff_func . "( ec_order.order_date, INTERVAL " . $date_diff . " MINUTE ) ) AS order_hour,
					DAY( " . $date_diff_func . "( ec_order.order_date, INTERVAL " . $date_diff . " MINUTE ) ) AS order_day,
					WEEK( " . $date_diff_func . "( ec_order.order_date, INTERVAL " . $date_diff . " MINUTE ) ) AS order_week,
					MONTH( " . $date_diff_func . "( ec_order.order_date, INTERVAL " . $date_diff . " MINUTE ) ) AS order_month,
					YEAR( " . $date_diff_func . "( ec_order.order_date, INTERVAL " . $date_diff . " MINUTE ) ) AS order_year
				FROM
					ec_order
					INNER JOIN ec_orderdetail ON ec_orderdetail.order_id = ec_order.order_id
					INNER JOIN ec_orderstatus ON ec_order.orderstatus_id = ec_orderstatus.status_id
				WHERE
					" . $range_where . " AND
					ec_orderstatus.is_approved = 1
					" . $product_where . " " . $country_where . " " . $billing_country_where . " " . $location_id_where . "
				GROUP BY
					" . $groupby . "
				ORDER BY
					MIN( ec_order.order_date ) DESC"
			);
			// phpcs:enable

			$sales = array( );
			for( $i=0; $i<=$days_length; $i++ ){
				$found = false;
				if( $range == 'hourly' ){
					$test_day = date( 'm/d/Y H:m', strtotime( '+' . $i . ' hours', strtotime( $start_date ) ) );
				}else if( $range == 'daily' ){
					$test_day = date( 'm/d/Y', strtotime( '+' . $i . ' days', strtotime( $start_date ) ) );
				}else if( $range == 'weekly' ){
					$test_day = date( 'm/d/Y', strtotime( 'sunday last week', strtotime( '+' . ($i*7) . ' day', strtotime( $start_date ) ) ) );
				}else if( $range == 'monthly' ){
					$test_day = date( 'm/d/Y',  strtotime( 'first day of this month', strtotime( '+' . $i . ' month', strtotime( $start_date ) ) ) );
				}else if( $range == 'yearly' ){
					$test_day = date( 'm/d/Y', strtotime( '+' . $i . ' year', strtotime( $start_date ) ) );
				}
				for( $j=0; $j<count( $sales_data ) && !$found; $j++ ){
					if( $range == 'hourly' && $test_day == date( 'm/d/Y H:m', strtotime( $sales_data[$j]->date . ' ' . $sales_data[$j]->order_hour . ':00' ) ) ){
						$sales_data[$j]->date = date( 'm/d/Y H:m', strtotime( $sales_data[$j]->date . ' ' . $sales_data[$j]->order_hour . ':00' ) );
						$sales[] = $sales_data[$j];
						$found = true;
					}else if( $range == 'weekly' && $test_day == date( 'm/d/Y', strtotime( 'sunday last week', strtotime( $sales_data[$j]->date ) ) ) ){
						$sales_data[$j]->date = date( 'm/d/Y', strtotime( 'sunday last week', strtotime( $sales_data[$j]->date ) ) );
						$sales[] = $sales_data[$j];
						$found = true;
					}else if( $range == 'monthly' && $test_day == date( 'm/d/Y', strtotime( 'first day of this month', strtotime( $sales_data[$j]->date ) ) ) ){
						$sales_data[$j]->date = date( 'm/d/Y', strtotime( 'first day of this month', strtotime( $sales_data[$j]->date ) ) );
						$sales[] = $sales_data[$j];
						$found = true;
					}else if( $range == 'yearly' && $test_day == date( 'm/d/Y', strtotime( 'first day of january', strtotime( $sales_data[$j]->date ) ) ) ){
						$sales_data[$j]->date = date( 'm/d/Y', strtotime( 'first day of january', strtotime( $sales_data[$j]->date ) ) );
						$sales[] = $sales_data[$j];
						$found = true;
					}else if( $test_day == $sales_data[$j]->date ){
						$sales[] = $sales_data[$j];
						$found = true;
					}
				}
				if( !$found ){
					if( $range == 'hourly' ){
						$sales[] = (object) array(
							'total'	=> 0,
							'discount_total' => 0,
							'refund_total' => 0,
							'date' => date( 'm/d/Y H:m', strtotime( '+' . $i . ' hours', strtotime( $start_date ) ) ),
							'order_day'	=> date( 'd', strtotime( '+' . $i . ' hours', strtotime( $start_date ) ) ),
							'order_month' => date( 'm', strtotime( '+' . $i . ' hours', strtotime( $start_date ) ) )
						);
					}else if( $range == 'daily' ){
						$sales[] = (object) array(
							'total'	=> 0,
							'discount_total' => 0,
							'refund_total' => 0,
							'date' => date( 'm/d/Y', strtotime( '+' . $i . ' days', strtotime( $start_date ) ) ),
							'order_day'	=> date( 'd', strtotime( '+' . $i . ' days', strtotime( $start_date ) ) ),
							'order_month' => date( 'm', strtotime( '+' . $i . ' days', strtotime( $start_date ) ) )
						);
					}else if( $range == 'weekly' ){
						$sales[] = (object) array(
							'total'	=> 0,
							'discount_total' => 0,
							'refund_total' => 0,
							'date' => date( 'm/d/Y', strtotime( '+' . ($i*7) . ' days', strtotime( $start_date ) ) ),
							'order_day'	=> date( 'd', strtotime( '+' . ($i*7) . ' days', strtotime( $start_date ) ) ),
							'order_month' => date( 'm', strtotime( '+' . ($i*7) . ' days', strtotime( $start_date ) ) )
						);
					}else if( $range == 'monthly' ){
						$sales[] = (object) array(
							'total'	=> 0,
							'discount_total' => 0,
							'refund_total' => 0,
							'date' => date( 'm/d/Y', strtotime( '+' . $i . ' months', strtotime( $start_date ) ) ),
							'order_day'	=> date( 'd', strtotime( '+' . $i . ' months', strtotime( $start_date ) ) ),
							'order_month' => date( 'm', strtotime( '+' . $i . ' months', strtotime( $start_date ) ) )
						);
					}else if( $range == 'yearly' ){
						$sales[] = (object) array(
							'total'	=> 0,
							'discount_total' => 0,
							'refund_total' => 0,
							'date' => date( 'm/d/Y', strtotime( '+' . $i . ' years', strtotime( $start_date ) ) ),
							'order_day'	=> date( 'd', strtotime( '+' . $i . ' years', strtotime( $start_date ) ) ),
							'order_month' => date( 'm', strtotime( '+' . $i . ' years', strtotime( $start_date ) ) )
						);
					}
				}
			}

			$data_items = array( );
			for( $i=0; $i<count( $sales ); $i++ ){
				$data_items[] = ( $sales[$i]->total - $sales[$i]->discount_total - $sales[$i]->refund_total );
			}

			return $data_items;
		}

		private function get_carts_dataset( $start_date, $end_date, $range = 'daily', $product_id = false, $country = false, $billing_country = false, $location_id = 0 ){
			$days_length = $this->get_sales_dataset_length( $start_date, $end_date, $range );
			if( $range == 'daily' && $days_length <= 2 ){
				$days_length = ($days_length+1) * 24;
				$range = 'hourly';
			}

			$product_where = "";
			$country_where = "";
			$billing_country_where = "";
			$location_id_where = '';
			if( $product_id ){
				$product_where = $this->wpdb->prepare( "AND ec_tempcart.product_id = %d ", $product_id );
			}
			if( $country ){
				$country_where = $this->wpdb->prepare( "AND ec_tempcart_data.shipping_country = %s ", $country );
			}
			if( $billing_country ){
				$billing_country_where = $this->wpdb->prepare( "AND ec_tempcart_data.billing_country = %s ", $billing_country );
			}
			if( $location_id ){
				$location_id_where = $this->wpdb->prepare( "AND ec_tempcart_data.pickup_location = %d ", $location_id );
			}

			if( $range == 'hourly' ){
				$groupby = 'order_hour, order_day';
			}else if( $range == 'daily' ){
				$groupby = 'order_day, order_month';
			}else if( $range == 'weekly' ){
				$groupby = 'order_week, order_year';
			}else if( $range == 'monthly' ){
				$groupby = 'order_month, order_year';
			}else if( $range == 'yearly' ){
				$groupby = 'order_year';
			}

			$date_diff_func = ( $this->date_diff < 0 ) ? 'DATE_SUB' : 'DATE_ADD';
			$date_diff      = abs( (int) round( $this->date_diff * 60 ) );
			list( $from, $to ) = $this->storage_bounds( $start_date, $end_date );
			$range_where    = $this->wpdb->prepare( 'ec_tempcart.last_changed_date >= %s AND ec_tempcart.last_changed_date < %s', $from, $to );

			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- every variable fragment is prepared above or is a function name / integer minute offset / GROUP BY keyword list built from fixed strings.
			$sales_data = $this->wpdb->get_results( "SELECT
					COUNT( ec_tempcart.session_id ) as total,
					0 AS discount_total,
					0 AS refund_total,
					DATE_FORMAT( ec_tempcart.last_changed_date, '%m/%d/%Y') as date,
					HOUR( " . $date_diff_func . "( ec_tempcart.last_changed_date, INTERVAL " . $date_diff . " MINUTE ) ) AS order_hour,
					DAY( " . $date_diff_func . "( ec_tempcart.last_changed_date, INTERVAL " . $date_diff . " MINUTE ) ) AS order_day,
					WEEK( " . $date_diff_func . "( ec_tempcart.last_changed_date, INTERVAL " . $date_diff . " MINUTE ) ) AS order_week,
					MONTH( " . $date_diff_func . "( ec_tempcart.last_changed_date, INTERVAL " . $date_diff . " MINUTE ) ) AS order_month,
					YEAR( " . $date_diff_func . "( ec_tempcart.last_changed_date, INTERVAL " . $date_diff . " MINUTE ) ) AS order_year
				FROM
					ec_tempcart
					LEFT JOIN ec_tempcart_data ON ( ec_tempcart_data.session_id = ec_tempcart.session_id )
				WHERE
					" . $range_where . "
					" . $product_where . "
					" . $country_where . "
					" . $billing_country_where . "
					" . $location_id_where . "
				GROUP BY
					" . $groupby . "
				ORDER BY
					MIN( ec_tempcart.last_changed_date ) DESC"
			);
			// phpcs:enable

			$sales = array( );
			for( $i=0; $i<=$days_length; $i++ ){
				$found = false;
				if( $range == 'hourly' ){
					$test_day = date( 'm/d/Y H:m', strtotime( '+' . $i . ' hours', strtotime( $start_date ) ) );
				}else if( $range == 'daily' ){
					$test_day = date( 'm/d/Y', strtotime( '+' . $i . ' days', strtotime( $start_date ) ) );
				}else if( $range == 'weekly' ){
					$test_day = date( 'm/d/Y', strtotime( 'sunday last week', strtotime( '+' . ($i*7) . ' day', strtotime( $start_date ) ) ) );
				}else if( $range == 'monthly' ){
					$test_day = date( 'm/d/Y',  strtotime( 'first day of this month', strtotime( '+' . $i . ' month', strtotime( $start_date ) ) ) );
				}else if( $range == 'yearly' ){
					$test_day = date( 'm/d/Y', strtotime( '+' . $i . ' year', strtotime( $start_date ) ) );
				}
				for( $j=0; $j<count( $sales_data ) && !$found; $j++ ){
					if( $range == 'hourly' && $test_day == date( 'm/d/Y H:m', strtotime( $sales_data[$j]->date . ' ' . $sales_data[$j]->order_hour . ':00' ) ) ){
						$sales_data[$j]->date = date( 'm/d/Y H:m', strtotime( $sales_data[$j]->date . ' ' . $sales_data[$j]->order_hour . ':00' ) );
						$sales[] = $sales_data[$j];
						$found = true;
					}else if( $range == 'weekly' && $test_day == date( 'm/d/Y', strtotime( 'sunday last week', strtotime( $sales_data[$j]->date ) ) ) ){
						// $sales_data[$j]->date = date( 'm/d/Y', strtotime( 'sunday last week', $sales_data[$j]->date ) );
						$sales[] = $sales_data[$j];
						$found = true;
					}else if( $range == 'monthly' && $test_day == date( 'm/d/Y', strtotime( 'first day of this month', strtotime( $sales_data[$j]->date ) ) ) ){
						$sales_data[$j]->date = date( 'm/d/Y', strtotime( 'first day of this month', strtotime( $sales_data[$j]->date ) ) );
						$sales[] = $sales_data[$j];
						$found = true;
					}else if( $range == 'yearly' && $test_day == date( 'm/d/Y', strtotime( 'first day of january', strtotime( $sales_data[$j]->date ) ) ) ){
						$sales_data[$j]->date = date( 'm/d/Y', strtotime( 'first day of january', strtotime( $sales_data[$j]->date ) ) );
						$sales[] = $sales_data[$j];
						$found = true;
					}else if( $test_day == $sales_data[$j]->date ){
						$sales[] = $sales_data[$j];
						$found = true;
					}
				}
				if( !$found ){
					if( $range == 'hourly' ){
						$sales[] = (object) array(
							'total'	=> 0,
							'discount_total' => 0,
							'refund_total' => 0,
							'date' => date( 'm/d/Y H:m', strtotime( '+' . $i . ' hours', strtotime( $start_date ) ) ),
							'order_day'	=> date( 'd', strtotime( '+' . $i . ' hours', strtotime( $start_date ) ) ),
							'order_month' => date( 'm', strtotime( '+' . $i . ' hours', strtotime( $start_date ) ) )
						);
					}else if( $range == 'daily' ){
						$sales[] = (object) array(
							'total'	=> 0,
							'discount_total' => 0,
							'refund_total' => 0,
							'date' => date( 'm/d/Y', strtotime( '+' . $i . ' days', strtotime( $start_date ) ) ),
							'order_day'	=> date( 'd', strtotime( '+' . $i . ' days', strtotime( $start_date ) ) ),
							'order_month' => date( 'm', strtotime( '+' . $i . ' days', strtotime( $start_date ) ) )
						);
					}else if( $range == 'weekly' ){
						$sales[] = (object) array(
							'total'	=> 0,
							'discount_total' => 0,
							'refund_total' => 0,
							'date' => date( 'm/d/Y', strtotime( '+' . ($i*7) . ' days', strtotime( $start_date ) ) ),
							'order_day'	=> date( 'd', strtotime( '+' . ($i*7) . ' days', strtotime( $start_date ) ) ),
							'order_month' => date( 'm', strtotime( '+' . ($i*7) . ' days', strtotime( $start_date ) ) )
						);
					}else if( $range == 'monthly' ){
						$sales[] = (object) array(
							'total'	=> 0,
							'discount_total' => 0,
							'refund_total' => 0,
							'date' => date( 'm/d/Y', strtotime( '+' . $i . ' months', strtotime( $start_date ) ) ),
							'order_day'	=> date( 'd', strtotime( '+' . $i . ' months', strtotime( $start_date ) ) ),
							'order_month' => date( 'm', strtotime( '+' . $i . ' months', strtotime( $start_date ) ) )
						);
					}else if( $range == 'yearly' ){
						$sales[] = (object) array(
							'total'	=> 0,
							'discount_total' => 0,
							'refund_total' => 0,
							'date' => date( 'm/d/Y', strtotime( '+' . $i . ' years', strtotime( $start_date ) ) ),
							'order_day'	=> date( 'd', strtotime( '+' . $i . ' years', strtotime( $start_date ) ) ),
							'order_month' => date( 'm', strtotime( '+' . $i . ' years', strtotime( $start_date ) ) )
						);
					}
				}
			}

			$data_items = array( );
			for( $i=0; $i<count( $sales ); $i++ ){
				$data_items[] = ( $sales[$i]->total - $sales[$i]->discount_total - $sales[$i]->refund_total );
			}

			return $data_items;
		}

		private function get_sales_dataset_length( $start_date, $end_date, $range = 'daily' ){
			$diff = strtotime( $end_date ) - strtotime( $start_date );
			if( $range == 'daily' )
				return round( $diff / ( 60 * 60 * 24 ) );
			else if( $range == 'weekly' )
				return ceil( $diff / ( 60 * 60 * 24 * 7 ) ) - 1;
			else if( $range == 'monthly' )
				return ( (int) date( 'm', strtotime( $end_date ) ) - (int) date( 'm', strtotime( $start_date ) ) ) + ( 12 * ( (int) date( 'Y', strtotime( $end_date ) ) - (int) date( 'Y', strtotime( $start_date ) ) ) );
			else if( $range == 'yearly' )
				return (int) date( 'Y', strtotime( $end_date ) ) - (int) date( 'Y', strtotime( $start_date ) );
		}

		function hex2rgba( $color, $opacity = false ){
			$default = 'rgba( 0, 0, 0, .7 )';
			if( empty( $color ) ){
				return $default; 
			}

			if( $color[0] == '#' ){
				$color = substr( $color, 1 );
			}

			if( strlen( $color ) == 6 ){
				$hex = array( $color[0] . $color[1], $color[2] . $color[3], $color[4] . $color[5] );
			}else if( strlen( $color ) == 3 ){
				$hex = array( $color[0] . $color[0], $color[1] . $color[1], $color[2] . $color[2] );
			}else{
				return $default;
			}

			$rgb =  array_map( 'hexdec', $hex );

			if( $opacity ){
				if( abs( $opacity ) > 1 ){
					$opacity = 1.0;
				}
				$output = 'rgba(' . implode( ",", $rgb ) . ',' . $opacity . ')';
			}else{
				$output = 'rgb(' . implode( ",", $rgb ) . ')';
			}

			return $output;
		}

		/* ------------------------------------------------------------------ */
		/* Report exports ( resumable jobs )                                   */
		/*                                                                    */
		/* 6.0.0: the order and tax CSVs are written by a job that walks      */
		/* ec_order by primary key ( order_id > cursor, LIMIT 1000 ), fetches  */
		/* the batch's line items / options / fees / gateway responses with   */
		/* IN ( ... ) lists and appends to the file. The AJAX caller loops    */
		/* until done; get_order_report() / get_tax_report() run the same     */
		/* engine to completion for any direct caller. Columns are unchanged. */
		/* ------------------------------------------------------------------ */

		/**
		 * Column map for the order export: CSV key => array( source, column ).
		 * Sources: o = ec_order row ( plus joined order_status / billing_country_name / shipping_country_name ),
		 * d = ec_orderdetail row, r = the order's first ec_response text.
		 *
		 * @since 6.0.0
		 */
		private static function order_report_columns() {
			$o = array(
				'order_date', 'order_status', 'orderdetail_id', 'order_id', 'payment_method', 'sub_total', 'tip_total', 'tax_total',
				'refund_total', 'shipping_total', 'discount_total', 'vat_total', 'vat_rate', 'duty_total', 'gst_total', 'gst_rate',
				'pst_total', 'pst_rate', 'hst_total', 'hst_rate', 'grand_total', 'user_id', 'use_expedited_shipping', 'shipping_method',
				'shipping_carrier', 'shipping_service_code', 'tracking_number', 'gift_card_used', 'promo_code_used', 'product_id', 'title',
				'model_number', 'unit_price', 'total_price', 'quantity', 'optionitem_name_1', 'optionitem_name_2', 'optionitem_name_3',
				'optionitem_name_4', 'optionitem_name_5', 'order_notes', 'order_customer_notes', 'user_email', 'user_level',
				'billing_first_name', 'billing_last_name', 'billing_company_name', 'billing_address_line_1', 'billing_address_line_2',
				'billing_city', 'billing_state', 'billing_zip', 'billing_country', 'billing_country_name', 'billing_phone',
				'shipping_first_name', 'shipping_last_name', 'shipping_company_name', 'shipping_address_line_1', 'shipping_address_line_2',
				'shipping_city', 'shipping_state', 'shipping_zip', 'shipping_country', 'shipping_country_name', 'shipping_phone',
				'vat_registration_number', 'agreed_to_terms', 'order_ip_address', 'use_advanced_optionset', 'giftcard_id', 'shipper_id',
				'shipper_first_name', 'shipper_last_name', 'gift_card_message', 'gift_card_from_name', 'gift_card_to_name', 'gift_card_email',
				'download_file_name', 'download_key', 'deconetwork_id', 'deconetwork_name', 'deconetwork_product_code', 'deconetwork_options',
				'deconetwork_color_code', 'deconetwork_product_id', 'deconetwork_image_link', 'subscription_signup_fee', 'order_weight',
				'order_gateway', 'card_holder_name', 'creditcard_digits', 'cc_exp_month', 'cc_exp_year', 'subscription_id', 'stripe_charge_id',
				'nets_transaction_id', 'gateway_transaction_id', 'paypal_email_id', 'paypal_transaction_id', 'paypal_payer_id',
				'fraktjakt_order_id', 'fraktjakt_shipment_id', 'gateway_response',
			);
			$detail = array(
				'orderdetail_id', 'product_id', 'title', 'model_number', 'unit_price', 'total_price', 'quantity', 'optionitem_name_1',
				'optionitem_name_2', 'optionitem_name_3', 'optionitem_name_4', 'optionitem_name_5', 'use_advanced_optionset', 'giftcard_id',
				'shipper_id', 'shipper_first_name', 'shipper_last_name', 'gift_card_message', 'gift_card_from_name', 'gift_card_to_name',
				'gift_card_email', 'download_file_name', 'download_key', 'deconetwork_id', 'deconetwork_name', 'deconetwork_product_code',
				'deconetwork_options', 'deconetwork_color_code', 'deconetwork_product_id', 'deconetwork_image_link', 'subscription_signup_fee',
			);
			$alias = array(
				'gift_card_used'  => 'giftcard_id',
				'promo_code_used' => 'promo_code',
			);
			$map = array();
			foreach ( $o as $key ) {
				if ( 'gateway_response' === $key ) {
					$map[ $key ] = array( 'r', '' );
				} elseif ( in_array( $key, $detail, true ) ) {
					$map[ $key ] = array( 'd', $key );
				} else {
					$map[ $key ] = array( 'o', isset( $alias[ $key ] ) ? $alias[ $key ] : $key );
				}
			}
			return $map;
		}

		/**
		 * Column map for the tax export ( same shape as order_report_columns() ).
		 *
		 * @since 6.0.0
		 */
		private static function tax_report_columns() {
			return array(
				'order_date'              => array( 'o', 'order_date' ),
				'order_status'            => array( 'o', 'order_status' ),
				'order_id'                => array( 'o', 'order_id' ),
				'orderdetail_id'          => array( 'd', 'orderdetail_id' ),
				'tax_state'               => array( 'o', 'shipping_state' ),
				'tax_country'             => array( 'o', 'shipping_country' ),
				'grand_total'             => array( 'o', 'grand_total' ),
				'shipping_total'          => array( 'o', 'shipping_total' ),
				'refund_total'            => array( 'o', 'refund_total' ),
				'tax_total'               => array( 'o', 'tax_total' ),
				'vat_total'               => array( 'o', 'vat_total' ),
				'vat_rate'                => array( 'o', 'vat_rate' ),
				'vat_registration_number' => array( 'o', 'vat_registration_number' ),
				'duty_total'              => array( 'o', 'duty_total' ),
				'gst_total'               => array( 'o', 'gst_total' ),
				'gst_rate'                => array( 'o', 'gst_rate' ),
				'pst_total'               => array( 'o', 'pst_total' ),
				'pst_rate'                => array( 'o', 'pst_rate' ),
				'hst_total'               => array( 'o', 'hst_total' ),
				'hst_rate'                => array( 'o', 'hst_rate' ),
				'unit_price'              => array( 'd', 'unit_price' ),
				'total_price'             => array( 'd', 'total_price' ),
				'quantity'                => array( 'd', 'quantity' ),
			);
		}

		/**
		 * Create the CSV under uploads/<month>/wpec-reports/ ( same location and name pattern as before ).
		 *
		 * @since 6.0.0
		 * @return array|false array( path, url ) or false when the directory is not writable.
		 */
		private function report_file( $prefix, $start_date, $end_date ) {
			$upload_dir     = wp_upload_dir( );
			$wp_reports_dir = $upload_dir['path'] . '/wpec-reports/';
			$wp_reports_url = set_url_scheme( $upload_dir['url'] . '/wpec-reports/' );
			if ( ! is_dir( $wp_reports_dir ) ) {
				wp_mkdir_p( $wp_reports_dir );
			}
			if ( ! is_dir( $wp_reports_dir ) ) {
				return false;
			}
			if ( ! file_exists( $wp_reports_dir . 'index.php' ) ) {
				$index_file = fopen( $wp_reports_dir . 'index.php', 'w' );
				if ( $index_file ) {
					fclose( $index_file );
				}
			}
			$clean     = preg_replace( '/[^0-9A-Za-z_-]/', '', (string) $start_date ) . '_' . preg_replace( '/[^0-9A-Za-z_-]/', '', (string) $end_date );
			$file_name = $prefix . $clean . '-' . wp_rand( 1000000, 999999999 ) . '.csv';
			return array( $wp_reports_dir . $file_name, $wp_reports_url . $file_name );
		}

		/**
		 * Prepare one report phase: resolve the column / fee / single-use key lists, create the file and write
		 * the header row. The phase array is what the job stores and report_phase_batch() advances.
		 *
		 * @since 6.0.0
		 * @param string $type 'order' | 'tax'.
		 * @param string $key  Key in the final reports object ( report1, report2, reporttax ).
		 * @return array|false
		 */
		private function report_phase_init( $type, $key, $start_date, $end_date ) {
			$file = $this->report_file( ( 'tax' === $type ) ? 'tax-report-' : 'order-report-', $start_date, $end_date );
			if ( ! $file ) {
				return false;
			}

			$fee_rows = $this->wpdb->get_results( 'SELECT fee_label FROM ec_order_fee GROUP BY fee_label ORDER BY fee_label ASC' );
			if ( 'order' === $type ) {
				$fee_rows = apply_filters( 'wp_easycart_order_export_fee_types', $fee_rows );
			}
			$fee_keys = array();
			if ( $fee_rows && is_array( $fee_rows ) ) {
				foreach ( $fee_rows as $fee_row ) {
					if ( is_object( $fee_row ) && isset( $fee_row->fee_label ) ) {
						$fee_keys[] = (string) $fee_row->fee_label;
					}
				}
			}

			if ( 'order' === $type ) {
				$keys   = array_keys( self::order_report_columns() );
				$keys[] = 'advanced_product_options';
				$single = apply_filters(
					'wp_easycart_order_export_single_keys',
					array(
						'sub_total', 'tip_total', 'tax_total', 'tax_total', 'shipping_total', 'discount_total', 'vat_total', 'refund_total',
						'vat_rate', 'hst_total', 'hst_rate', 'pst_total', 'pst_rate', 'gst_total', 'gst_rate', 'grand_total',
						'order_date', 'order_status', 'payment_method', 'shipping_method', 'tracking_number', 'promo_code_used',
						'order_customer_notes', 'agreed_to_terms', 'order_ip_address', 'order_weight',
						'order_gateway', 'card_holder_name', 'creditcard_digits', 'cc_exp_month', 'cc_exp_year', 'stripe_charge_id', 'order_notes',
						'gateway_response',
					)
				);
			} else {
				$keys   = array_keys( self::tax_report_columns() );
				$single = array(
					'order_date', 'order_status', 'order_id', 'grand_total', 'shipping_total', 'refund_total', 'tax_total', 'vat_total',
					'vat_rate', 'vat_registration_number', 'duty_total', 'gst_total', 'gst_rate',
					'pst_total', 'pst_rate', 'hst_total', 'hst_rate',
				);
			}
			foreach ( $fee_keys as $fee_key ) {
				$keys[]   = $fee_key;
				$single[] = $fee_key;
			}
			if ( 'order' === $type ) {
				$keys = apply_filters( 'wp_easycart_order_export_keys', $keys );
			}
			$keys = array_values( array_map( 'strval', (array) $keys ) );

			$fh = fopen( $file[0], 'w' );
			if ( ! $fh ) {
				return false;
			}
			fputcsv( $fh, $keys );
			fclose( $fh );

			return array(
				'type'     => $type,
				'key'      => $key,
				'start'    => (string) $start_date,
				'end'      => (string) $end_date,
				'path'     => $file[0],
				'url'      => $file[1],
				'keys'     => $keys,
				'single'   => array_values( array_map( 'strval', (array) $single ) ),
				'fee_keys' => $fee_keys,
				'cursor'   => 0,
				'done'     => false,
			);
		}

		/**
		 * Append the next batch of orders ( order_id > cursor, LIMIT REPORT_BATCH ) to the phase's file.
		 *
		 * @since 6.0.0
		 * @param array $phase Phase state ( by reference: cursor / done advance ).
		 * @param array $args  Job args ( product_id, country, billing_country, location_id ).
		 * @return int Orders written in this call.
		 */
		private function report_phase_batch( &$phase, $args ) {
			list( $from, $to ) = $this->storage_bounds( $phase['start'], $phase['end'] );
			$product_id = isset( $args['product_id'] ) ? (int) $args['product_id'] : 0;

			$where = $this->wpdb->prepare( 'ec_order.order_date >= %s AND ec_order.order_date < %s AND ec_order.order_id > %d', $from, $to, (int) $phase['cursor'] );
			if ( ! empty( $args['country'] ) ) {
				$where .= $this->wpdb->prepare( ' AND ec_order.shipping_country = %s', $args['country'] );
			}
			if ( ! empty( $args['billing_country'] ) ) {
				$where .= $this->wpdb->prepare( ' AND ec_order.billing_country = %s', $args['billing_country'] );
			}
			if ( ! empty( $args['location_id'] ) ) {
				$where .= $this->wpdb->prepare( ' AND ec_order.location_id = %d', (int) $args['location_id'] );
			}
			if ( $product_id ) {
				/* The legacy LEFT OUTER JOIN ... AND product_id = %d kept only orders with a matching line. */
				$where .= $this->wpdb->prepare( ' AND EXISTS ( SELECT 1 FROM ec_orderdetail AS od_x WHERE od_x.order_id = ec_order.order_id AND od_x.product_id = %d )', $product_id );
			}

			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- $where is built from prepared fragments above; the IN lists are integer ids cast with intval.
			$orders = $this->wpdb->get_results(
				'SELECT ec_order.*, ec_orderstatus.order_status, billing_country.name_cnt AS billing_country_name, shipping_country.name_cnt AS shipping_country_name
				FROM ec_order
				LEFT JOIN ec_country AS billing_country ON billing_country.iso2_cnt = ec_order.billing_country
				LEFT JOIN ec_country AS shipping_country ON shipping_country.iso2_cnt = ec_order.shipping_country
				LEFT JOIN ec_orderstatus ON ec_orderstatus.status_id = ec_order.orderstatus_id
				WHERE ' . $where . '
				ORDER BY ec_order.order_id ASC
				LIMIT ' . (int) self::REPORT_BATCH,
				ARRAY_A
			);
			if ( empty( $orders ) ) {
				$phase['done'] = true;
				return 0;
			}
			if ( count( $orders ) < self::REPORT_BATCH ) {
				$phase['done'] = true;
			}

			$ids = array();
			foreach ( $orders as $order ) {
				$ids[] = (int) $order['order_id'];
			}
			$in = implode( ',', array_map( 'intval', $ids ) );

			$detail_sql = 'SELECT * FROM ec_orderdetail WHERE order_id IN ( ' . $in . ' )';
			if ( $product_id ) {
				$detail_sql .= $this->wpdb->prepare( ' AND product_id = %d', $product_id );
			}
			$detail_sql      .= ' ORDER BY order_id ASC, orderdetail_id ASC';
			$details_by_order = array();
			$detail_ids       = array();
			$detail_rows      = $this->wpdb->get_results( $detail_sql, ARRAY_A );
			if ( $detail_rows ) {
				foreach ( $detail_rows as $detail_row ) {
					$details_by_order[ (int) $detail_row['order_id'] ][] = $detail_row;
					$detail_ids[] = (int) $detail_row['orderdetail_id'];
				}
			}

			$options_by_detail = array();
			$response_by_order = array();
			if ( 'order' === $phase['type'] ) {
				if ( $detail_ids ) {
					$option_rows = $this->wpdb->get_results( 'SELECT orderdetail_id, option_value FROM ec_order_option WHERE orderdetail_id IN ( ' . implode( ',', array_map( 'intval', $detail_ids ) ) . ' ) ORDER BY order_option_id ASC' );
					if ( $option_rows ) {
						foreach ( $option_rows as $option_row ) {
							$options_by_detail[ (int) $option_row->orderdetail_id ][] = (string) $option_row->option_value;
						}
					}
				}
				$response_rows = $this->wpdb->get_results( 'SELECT order_id, response_text FROM ec_response WHERE order_id IN ( ' . $in . ' ) ORDER BY response_id ASC' );
				if ( $response_rows ) {
					foreach ( $response_rows as $response_row ) {
						if ( ! isset( $response_by_order[ (int) $response_row->order_id ] ) ) {
							$response_by_order[ (int) $response_row->order_id ] = $response_row->response_text;
						}
					}
				}
			}

			$fees_by_order = array();
			if ( ! empty( $phase['fee_keys'] ) ) {
				$fee_rows = $this->wpdb->get_results( 'SELECT order_id, fee_label, fee_total FROM ec_order_fee WHERE order_id IN ( ' . $in . ' ) ORDER BY fee_label ASC', ARRAY_A );
				if ( $fee_rows ) {
					foreach ( $fee_rows as $fee_row ) {
						$fees_by_order[ (int) $fee_row['order_id'] ][] = $fee_row;
					}
				}
			}
			// phpcs:enable

			$fh = fopen( $phase['path'], 'a' );
			if ( ! $fh ) {
				$phase['done']  = true;
				$phase['error'] = 'file';
				return 0;
			}
			foreach ( $orders as $order ) {
				$order_id = (int) $order['order_id'];
				$lines    = isset( $details_by_order[ $order_id ] ) ? $details_by_order[ $order_id ] : array( array() );
				$is_new   = true;
				foreach ( $lines as $detail ) {
					fputcsv( $fh, $this->report_line( $phase, $order, $detail, $is_new, $options_by_detail, $response_by_order, $fees_by_order ) );
					$is_new = false;
				}
				$phase['cursor'] = $order_id;
			}
			fclose( $fh );

			return count( $orders );
		}

		/**
		 * One CSV row, built exactly as the legacy per-row loop did ( single-use keys zeroed after the first
		 * line of an order, zip codes wrapped for Excel, fee columns only on an order's first line ).
		 *
		 * @since 6.0.0
		 * @return array
		 */
		private function report_line( $phase, $order, $detail, $is_new_order, $options_by_detail, $response_by_order, $fees_by_order ) {
			$is_order = ( 'order' === $phase['type'] );
			$columns  = $is_order ? self::order_report_columns() : self::tax_report_columns();
			$order_id = (int) $order['order_id'];

			$gateway_response = '';
			if ( $is_order ) {
				$gateway_response = ( isset( $response_by_order[ $order_id ] ) && null !== $response_by_order[ $order_id ] ) ? (string) $response_by_order[ $order_id ] : '';
				$gateway          = isset( $order['order_gateway'] ) ? $order['order_gateway'] : '';
				if ( 'authorize' === $gateway ) {
					$response_exploded = explode( ',', $gateway_response );
					if ( count( $response_exploded ) > 3 ) {
						$gateway_response = $response_exploded[3];
					}
				} elseif ( 'paypal' === $gateway ) {
					preg_match_all( "/\[payment_status\] \=\> (.*)\n/", $gateway_response, $output_array );
					$gateway_response = isset( $output_array[1][0] ) ? $output_array[1][0] : '';
				} else {
					$gateway_response = str_replace( "\n", '', str_replace( "\r", '', $gateway_response ) );
				}
			}

			$new_line = array();
			foreach ( $phase['keys'] as $key ) {
				if ( $is_order && 'advanced_product_options' === $key ) {
					$detail_id  = isset( $detail['orderdetail_id'] ) ? (int) $detail['orderdetail_id'] : 0;
					$new_line[] = ( $detail_id && isset( $options_by_detail[ $detail_id ] ) ) ? implode( ', ', $options_by_detail[ $detail_id ] ) : '';
					continue;
				}
				if ( in_array( $key, $phase['fee_keys'], true ) ) {
					continue;
				}
				$value = null;
				if ( isset( $columns[ $key ] ) ) {
					$source = $columns[ $key ][0];
					$column = $columns[ $key ][1];
					if ( 'o' === $source ) {
						$value = isset( $order[ $column ] ) ? $order[ $column ] : null;
					} elseif ( 'd' === $source ) {
						$value = isset( $detail[ $column ] ) ? $detail[ $column ] : null;
					} else {
						$value = $gateway_response;
					}
				}
				if ( in_array( $key, $phase['single'], true ) && ! $is_new_order ) {
					$new_line[] = '0.00';
				} elseif ( null === $value || '' === (string) $value ) {
					$new_line[] = '';
				} elseif ( $is_order && ( 'billing_zip' === $key || 'shipping_zip' === $key ) ) {
					$new_line[] = '="' . $value . '"';
				} else {
					$new_line[] = $value;
				}
			}

			if ( $is_new_order && ! empty( $phase['fee_keys'] ) ) {
				$order_fees = isset( $fees_by_order[ $order_id ] ) ? $fees_by_order[ $order_id ] : array();
				foreach ( $phase['fee_keys'] as $fee_key ) {
					$found = false;
					foreach ( $order_fees as $order_fee ) {
						if ( (string) $order_fee['fee_label'] === (string) $fee_key ) {
							$new_line[] = $order_fee['fee_total'];
							$found      = true;
						}
					}
					if ( ! $found ) {
						$new_line[] = '0.000';
					}
				}
			}

			return $new_line;
		}

		/**
		 * Start an export job: main order report, optional compare-range order report, tax report.
		 *
		 * @since 6.0.0
		 * @param array $args start_date, end_date, start_date2, end_date2, product_id, country, billing_country, location_id.
		 * @return string|false Job token, or false when the reports directory is not writable.
		 */
		public function report_job_create( $args ) {
			$args = array_merge(
				array( 'start_date' => '', 'end_date' => '', 'start_date2' => '', 'end_date2' => '', 'product_id' => 0, 'country' => '', 'billing_country' => '', 'location_id' => 0 ),
				(array) $args
			);
			$phases  = array();
			$phase   = $this->report_phase_init( 'order', 'report1', $args['start_date'], $args['end_date'] );
			if ( ! $phase ) {
				return false;
			}
			$phases[] = $phase;
			if ( ! empty( $args['start_date2'] ) ) {
				$phase = $this->report_phase_init( 'order', 'report2', $args['start_date2'], $args['end_date2'] );
				if ( ! $phase ) {
					return false;
				}
				$phases[] = $phase;
			}
			$phase = $this->report_phase_init( 'tax', 'reporttax', $args['start_date'], $args['end_date'] );
			if ( ! $phase ) {
				return false;
			}
			$phases[] = $phase;

			$token = md5( uniqid( 'wpec-report', true ) . wp_rand() );
			$job   = array(
				'user_id'   => get_current_user_id(),
				'args'      => $args,
				'phases'    => $phases,
				'phase'     => 0,
				'processed' => 0,
			);
			set_transient( 'wpec_report_job_' . $token, $job, HOUR_IN_SECONDS );
			return $token;
		}

		/**
		 * Run one batch of an export job. The stored cursor is authoritative ( a retried call never re-appends ).
		 *
		 * @since 6.0.0
		 * @param string $token Job token from report_job_create().
		 * @return array|false { done, processed, total, next: { job, phase, last_order_id }, reports?: object } or false for an unknown / foreign job.
		 */
		public function report_job_step( $token ) {
			$job = get_transient( 'wpec_report_job_' . $token );
			if ( ! is_array( $job ) || empty( $job['phases'] ) || (int) $job['user_id'] !== (int) get_current_user_id() ) {
				return false;
			}
			$processed = 0;
			while ( $job['phase'] < count( $job['phases'] ) ) {
				$written = $this->report_phase_batch( $job['phases'][ $job['phase'] ], $job['args'] );
				$processed += $written;
				if ( ! empty( $job['phases'][ $job['phase'] ]['done'] ) ) {
					$job['phase']++;
					if ( $written > 0 ) {
						break;
					}
					continue; /* an empty phase costs nothing: move straight on to the next one */
				}
				break;
			}
			$job['processed'] += $processed;
			$done = ( $job['phase'] >= count( $job['phases'] ) );

			$out = array(
				'done'      => $done,
				'processed' => $processed,
				'total'     => (int) $job['processed'],
				'next'      => array(
					'job'           => $token,
					'phase'         => (int) $job['phase'],
					'last_order_id' => $done ? 0 : (int) $job['phases'][ $job['phase'] ]['cursor'],
				),
			);
			if ( $done ) {
				$reports = (object) array( 'report1' => '', 'report2' => false, 'reporttax' => '' );
				foreach ( $job['phases'] as $phase ) {
					$reports->{ $phase['key'] } = $phase['url'];
				}
				$out['reports'] = apply_filters( 'wp_easycart_export_report_list', $reports, $job['args']['start_date'], $job['args']['end_date'], (int) $job['args']['product_id'], $job['args']['country'], $job['args']['billing_country'] );
				delete_transient( 'wpec_report_job_' . $token );
			} else {
				set_transient( 'wpec_report_job_' . $token, $job, HOUR_IN_SECONDS );
			}
			return $out;
		}

		/**
		 * Run one report to completion in-process ( direct callers ). The AJAX export uses the job API instead.
		 *
		 * @since 6.0.0
		 * @return string File URL, or '' when the reports directory is not writable.
		 */
		private function run_report_sync( $type, $start_date, $end_date, $product_id, $country, $billing_country, $location_id ) {
			$args  = array( 'product_id' => (int) $product_id, 'country' => (string) $country, 'billing_country' => (string) $billing_country, 'location_id' => (int) $location_id );
			$phase = $this->report_phase_init( $type, 'report1', $start_date, $end_date );
			if ( ! $phase ) {
				return '';
			}
			while ( empty( $phase['done'] ) ) {
				$this->report_phase_batch( $phase, $args );
			}
			return $phase['url'];
		}

		public function get_tax_report( $start_date, $end_date, $product_id = false, $country = false, $billing_country = false, $location_id = 0 ) {
			return $this->run_report_sync( 'tax', $start_date, $end_date, $product_id, $country, $billing_country, $location_id );
		}

		public function get_order_report( $start_date, $end_date, $product_id = false, $country = false, $billing_country = false, $location_id = 0 ){
			return $this->run_report_sync( 'order', $start_date, $end_date, $product_id, $country, $billing_country, $location_id );
		}

		/**
		 * Summary cards for the Reports page, cached 10 minutes per ( ranges + filters ) under the order fingerprint.
		 *
		 * @since 6.0.0 caching + sargable queries; the returned object is unchanged.
		 */
		public function get_single_stats( $start_date, $end_date, $start_date2 = false, $end_date2 = false, $product_id = false, $country = false, $billing_country = false, $location_id = 0 ){
			$key    = 'single|' . implode( '|', array( (string) $start_date, (string) $end_date, (string) $start_date2, (string) $end_date2, (int) $product_id, (string) $country, (string) $billing_country, (int) $location_id ) );
			$cached = $this->stats_cache_get( $key );
			if ( is_object( $cached ) && isset( $cached->gross_revenue ) ) {
				return $cached;
			}
			$stats = $this->compute_single_stats( $start_date, $end_date, $start_date2, $end_date2, $product_id, $country, $billing_country, $location_id );
			$this->stats_cache_set( $key, $stats, 10 * MINUTE_IN_SECONDS );
			return $stats;
		}

		/**
		 * The four aggregates behind get_single_stats() for one range. Date bounds are constants on the
		 * indexed order_date / last_changed_date columns; the per-order correlated line-item subqueries are
		 * replaced by ec_order.sub_total and, with a product filter, one grouped derived table in range.
		 *
		 * @since 6.0.0
		 * @return array [ $sales_data, $sales_data_item, $sales_data_cart, $sales_data_flex_fees ]
		 */
		private function single_stats_set( $start_date, $end_date, $product_id, $w ) {
			list( $from, $to ) = $this->storage_bounds( $start_date, $end_date );
			$order_range = $this->wpdb->prepare( 'ec_order.order_date >= %s AND ec_order.order_date < %s', $from, $to );
			$cart_range  = $this->wpdb->prepare( 'ec_tempcart.last_changed_date >= %s AND ec_tempcart.last_changed_date < %s', $from, $to );
			$approved    = '( ec_orderstatus.is_approved = 1 OR ec_orderstatus.status_id = 16 )';

			$item_join      = '';
			$item_rows_sql  = 'COUNT( ec_order.order_id )';
			$item_total_sql = 'SUM( ec_order.sub_total )';
			if ( $product_id ) {
				$item_join      = $this->wpdb->prepare( 'LEFT JOIN ( SELECT ec_orderdetail.order_id, COUNT( ec_orderdetail.orderdetail_id ) AS item_rows, SUM( ec_orderdetail.total_price ) AS item_total FROM ec_orderdetail INNER JOIN ec_order AS od_order ON od_order.order_id = ec_orderdetail.order_id WHERE od_order.order_date >= %s AND od_order.order_date < %s AND ec_orderdetail.product_id = %d GROUP BY ec_orderdetail.order_id ) AS od ON od.order_id = ec_order.order_id', $from, $to, $product_id );
				$item_rows_sql  = 'SUM( IFNULL( od.item_rows, 0 ) )';
				$item_total_sql = 'SUM( IFNULL( od.item_total, 0 ) )';
			}

			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- every variable fragment is prepared above ( range / join ) or by the caller ( filter clauses ).
			$sales_data = $this->wpdb->get_row( "SELECT
					COUNT( ec_order.order_id ) AS order_count,
					COUNT( ec_order.user_id ) AS customer_count,
					" . $item_rows_sql . " AS item_order_count,
					" . $item_rows_sql . " AS item_customer_count,
					SUM( ec_order.sub_total ) AS total,
					" . $item_total_sql . " AS item_total,
					SUM( ec_order.discount_total ) AS discount_total,
					SUM( ec_order.refund_total ) AS refund_total,
					SUM( ec_order.shipping_total ) AS shipping_total,
					SUM( ec_order.tax_total ) AS tax_total,
					SUM( ec_order.vat_total ) AS vat_total,
					SUM( ec_order.gst_total ) AS gst_total,
					SUM( ec_order.pst_total ) AS pst_total,
					SUM( ec_order.hst_total ) AS hst_total
				FROM
					ec_order
					INNER JOIN ec_orderstatus ON ec_order.orderstatus_id = ec_orderstatus.status_id
					" . $item_join . "
				WHERE
					" . $order_range . " AND " . $approved . " " . $w['country'] . " " . $w['billing'] . " " . $w['location']
			);

			$sales_data_item = $this->wpdb->get_row( "SELECT
					IFNULL( SUM( ec_orderdetail.quantity ), 0 ) as item_count
				FROM
					ec_order
					INNER JOIN ec_orderdetail ON ec_orderdetail.order_id = ec_order.order_id
					INNER JOIN ec_orderstatus ON ec_order.orderstatus_id = ec_orderstatus.status_id
				WHERE
					" . $order_range . " AND " . $approved . " " . $w['product'] . " " . $w['country'] . " " . $w['billing'] . " " . $w['location']
			);

			$sales_data_cart = $this->wpdb->get_row( "SELECT
					COUNT( ec_tempcart.session_id ) as cart_total
				FROM
					ec_tempcart
					LEFT JOIN ec_tempcart_data ON ( ec_tempcart_data.session_id = ec_tempcart.session_id )
				WHERE
					" . $cart_range . " " . $w['product_cart'] . " " . $w['country_cart'] . " " . $w['billing_cart'] . " " . $w['location_cart']
			);

			$sales_data_flex_fees = $this->wpdb->get_results( "SELECT
					IFNULL( SUM( ec_order_fee.fee_total ), 0 ) as total_fees,
					ec_order_fee.fee_label
				FROM
					ec_order
					INNER JOIN ec_order_fee ON ec_order_fee.order_id = ec_order.order_id
					INNER JOIN ec_orderstatus ON ec_order.orderstatus_id = ec_orderstatus.status_id
				WHERE
					" . $order_range . " AND " . $approved . " " . $w['country'] . " " . $w['billing'] . " " . $w['location'] . "
				GROUP BY ec_order_fee.fee_label"
			);
			// phpcs:enable

			return array( $sales_data, $sales_data_item, $sales_data_cart, $sales_data_flex_fees );
		}

		/**
		 * One chart dataset, cached 10 minutes per ( type + range + filters ) under the order fingerprint.
		 *
		 * @since 6.0.0
		 */
		private function get_dataset_cached( $type, $start_date, $end_date, $range = 'daily', $product_id = false, $country = false, $billing_country = false, $location_id = 0 ) {
			$type = in_array( $type, array( 'sales', 'items', 'carts' ), true ) ? $type : 'sales';
			$key  = 'dataset|' . implode( '|', array( $type, (string) $start_date, (string) $end_date, (string) $range, (int) $product_id, (string) $country, (string) $billing_country, (int) $location_id ) );
			$cached = $this->stats_cache_get( $key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
			$data = $this->{'get_' . $type . '_dataset'}( $start_date, $end_date, $range, $product_id, $country, $billing_country, $location_id );
			$this->stats_cache_set( $key, $data, 10 * MINUTE_IN_SECONDS );
			return $data;
		}

		private function compute_single_stats( $start_date, $end_date, $start_date2 = false, $end_date2 = false, $product_id = false, $country = false, $billing_country = false, $location_id = 0 ){

			$product_where = "";
			$product_where_cart = "";
			$country_where = "";
			$country_where_cart = "";
			$billing_country_where = "";
			$billing_country_where_cart = "";
			$location_id_where = '';
			$location_id_where_cart = '';
			if( $product_id ){
				$product_where = $this->wpdb->prepare( "AND ec_orderdetail.product_id = %d ", $product_id );
				$product_where_cart = $this->wpdb->prepare( "AND ec_tempcart.product_id = %d ", $product_id );
			}
			if( $country ){
				$country_where = $this->wpdb->prepare( "AND ec_order.shipping_country = %s ", $country );
				$country_where_cart = $this->wpdb->prepare( "AND ec_tempcart_data.shipping_country = %s ", $country );
			}
			if( $billing_country ){
				$billing_country_where = $this->wpdb->prepare( "AND ec_order.billing_country = %s ", $billing_country );
				$billing_country_where_cart = $this->wpdb->prepare( "AND ec_tempcart_data.billing_country = %s ", $billing_country );
			}
			if( $location_id ){
				$location_id_where = $this->wpdb->prepare( "AND ec_order.location_id = %d ", $location_id );
				$location_id_where_cart = $this->wpdb->prepare( "AND ec_tempcart_data.pickup_location = %d ", $location_id );
			}
			$where = array(
				'product'       => $product_where,
				'product_cart'  => $product_where_cart,
				'country'       => $country_where,
				'country_cart'  => $country_where_cart,
				'billing'       => $billing_country_where,
				'billing_cart'  => $billing_country_where_cart,
				'location'      => $location_id_where,
				'location_cart' => $location_id_where_cart,
			);
			list( $sales_data, $sales_data_item, $sales_data_cart, $sales_data_flex_fees ) = $this->single_stats_set( $start_date, $end_date, $product_id, $where );

			$sales_data2 = false;
			$sales_data_item2 = false;
			$sales_data_cart2 = false;
			$sales_data_flex_fees2 = array();
			if( $start_date2 ){
				list( $sales_data2, $sales_data_item2, $sales_data_cart2, $sales_data_flex_fees2 ) = $this->single_stats_set( $start_date2, $end_date2, $product_id, $where );
			}
			$fee_types = $this->wpdb->get_results( 'SELECT fee_label FROM ec_order_fee GROUP BY fee_label ORDER BY fee_label ASC' );
			$fees = array();
			$fees_total_1 = 0;
			$fees_total_2 = 0;
			if ( $fee_types && is_array( $fee_types) ) {
				foreach ( $fee_types as $fee_type ) {
					$selected_fees = false;
					$selected_fees2 = false;
					if ( isset( $sales_data_flex_fees ) && is_array( $sales_data_flex_fees ) ) {
						foreach ( $sales_data_flex_fees as $sales_data_flex_fee ) {
							if ( $sales_data_flex_fee->fee_label == $fee_type->fee_label ) {
								$selected_fees = $sales_data_flex_fee;
							}
						}
					}
					if ( isset( $sales_data_flex_fees2 ) && is_array( $sales_data_flex_fees2 ) ) {
						foreach ( $sales_data_flex_fees2 as $sales_data_flex_fee ) {
							if ( $sales_data_flex_fee->fee_label == $fee_type->fee_label ) {
								$selected_fees2 = $sales_data_flex_fee;
							}
						}
					}
					$fees_total_1 += number_format( ( $selected_fees ) ? $selected_fees->total_fees : 0, 2, '.', '' );
					$fees_total_2 += number_format( ( $selected_fees2 ) ? $selected_fees2->total_fees : 0, 2, '.', '' );
					$fees[] = (object) array(
						'fee_label' => $fee_type->fee_label,
						'set1' => ( $selected_fees ) ? $GLOBALS['currency']->get_currency_display( $selected_fees->total_fees ) : $GLOBALS['currency']->get_currency_display( 0 ),
						'set2' => ( $selected_fees2 ) ? $GLOBALS['currency']->get_currency_display( $selected_fees2->total_fees ) : $GLOBALS['currency']->get_currency_display( 0 ),
						'diff' => ( $start_date2 && $selected_fees2 && $selected_fees2->total_fees > 0 ) ? number_format( ( ( ( $selected_fees && $selected_fees->total_fees > 0 ) ? $selected_fees->total_fees : 0 ) - $selected_fees2->total_fees ) * 100, 2, '.', '' ) : '',
					);
				}
			}

			return (object) array(
				'gross_revenue' => (object) array(
					'set1' => ( ( $product_id ) ? $GLOBALS['currency']->get_currency_display( ( $sales_data ) ? $sales_data->item_total : 0 ) : $GLOBALS['currency']->get_currency_display( ( $sales_data ) ? $sales_data->total + $sales_data->shipping_total + $sales_data->tax_total + $sales_data->vat_total + $sales_data->gst_total + $sales_data->hst_total + $sales_data->pst_total + $fees_total_1 - $sales_data->refund_total - $sales_data->discount_total : 0 ) ),
					'set2' => ( ( $product_id ) ? $GLOBALS['currency']->get_currency_display( ( $start_date2 && $sales_data2 ) ? $sales_data2->item_total : 0 ) : $GLOBALS['currency']->get_currency_display( ( $start_date2 && $sales_data2 ) ? $sales_data2->total + $sales_data2->shipping_total + $sales_data2->tax_total + $sales_data2->vat_total + $sales_data2->gst_total + $sales_data2->hst_total + $sales_data2->pst_total + $fees_total_2 - $sales_data2->refund_total - $sales_data2->discount_total : 0 ) ),
					'diff' => ( ( $product_id ) ? ( ( !$start_date2 || !$sales_data2 || !$sales_data2->item_total ) ? '' : number_format( ( ( ( $sales_data ) ? $sales_data->item_total : 0 ) - ( ( $start_date2 && $sales_data2 ) ? $sales_data2->item_total : 0 ) ) / ( ( $start_date2 && $sales_data2 ) ? $sales_data2->item_total : 0 ) * 100, 2, '.', '' ) ) : ( ( !$start_date2 || !$sales_data2 || !$sales_data2->item_total ) ? '' : number_format( ( ( ( $sales_data ) ? $sales_data->total + $sales_data->shipping_total + $sales_data->tax_total + $sales_data->vat_total + $sales_data->gst_total + $sales_data->hst_total + $sales_data->pst_total + $fees_total_1 - $sales_data->refund_total - $sales_data->discount_total : 0 ) - ( ( $start_date2 && $sales_data2 ) ? $sales_data2->total + $sales_data2->shipping_total + $sales_data2->tax_total + $sales_data2->vat_total + $sales_data2->gst_total + $sales_data2->hst_total + $sales_data2->pst_total + $fees_total_2 - $sales_data2->refund_total - $sales_data2->discount_total : 0 ) ) / ( ( $start_date2 && $sales_data2 ) ? $sales_data2->total + $sales_data2->shipping_total + $sales_data2->tax_total + $sales_data2->vat_total + $sales_data2->gst_total + $sales_data2->hst_total + $sales_data2->pst_total + $fees_total_2 - $sales_data2->refund_total - $sales_data2->discount_total : 0 ) * 100, 2, '.', '' ) ) )
				),
				'shipping' => (object) array(
					'set1' => ( $product_id ) ? $GLOBALS['currency']->get_currency_display( 0 ) : $GLOBALS['currency']->get_currency_display( ( $sales_data ) ? $sales_data->shipping_total : 0 ),
					'set2' => ( $product_id ) ? $GLOBALS['currency']->get_currency_display( 0 ) : $GLOBALS['currency']->get_currency_display( ( $start_date2 && $sales_data2 ) ? $sales_data2->shipping_total : 0 ),
					'diff' => ( $product_id ) ? '' : ( ( $start_date2 && $sales_data2 && $sales_data2->shipping_total > 0 ) ? number_format( ( ( ( $sales_data ) ? $sales_data->shipping_total : 0 ) - ( ( $start_date2 && $sales_data2 && $sales_data2->shipping_total > 0 ) ? $sales_data2->shipping_total : 0 ) ) / ( ( $start_date2 && $sales_data2 ) ? $sales_data2->shipping_total : 0 ) * 100, 2, '.', '' ) : '' )
				),
				'tax' => (object) array(
					'set1' => ( $product_id ) ? $GLOBALS['currency']->get_currency_display( 0 ) : $GLOBALS['currency']->get_currency_display( ( $sales_data ) ? $sales_data->tax_total + $sales_data->vat_total + $sales_data->gst_total + $sales_data->pst_total + $sales_data->hst_total : 0 ),
					'set2' => ( $product_id ) ? $GLOBALS['currency']->get_currency_display( 0 ) : $GLOBALS['currency']->get_currency_display( ( $start_date2 && $sales_data2 ) ? $sales_data2->tax_total + $sales_data2->vat_total + $sales_data2->gst_total + $sales_data2->pst_total + $sales_data2->hst_total : 0 ),
					'diff' => ( $product_id ) ? '' : ( ( $start_date2 && $sales_data2 && ( $sales_data2->tax_total + $sales_data2->vat_total + $sales_data2->gst_total + $sales_data2->pst_total + $sales_data2->hst_total ) > 0 ) ? number_format( ( ( ( $sales_data ) ? $sales_data->tax_total + $sales_data->vat_total + $sales_data->gst_total + $sales_data->pst_total + $sales_data->hst_total : 0 ) - ( ( $start_date2 && $sales_data2 ) ? $sales_data2->tax_total + $sales_data2->vat_total + $sales_data2->gst_total + $sales_data2->pst_total + $sales_data2->hst_total : 0 ) ) / ( ( $start_date2 && $sales_data2 ) ? $sales_data2->tax_total + $sales_data2->vat_total + $sales_data2->gst_total + $sales_data2->pst_total + $sales_data2->hst_total : 0 ) * 100, 2, '.', '' ) : '' )
				),
				'discount' => (object) array(
					'set1' => ( $product_id ) ? $GLOBALS['currency']->get_currency_display( 0 ) : $GLOBALS['currency']->get_currency_display( ( $sales_data ) ? $sales_data->discount_total : 0 ),
					'set2' => ( $product_id ) ? $GLOBALS['currency']->get_currency_display( 0 ) : $GLOBALS['currency']->get_currency_display( ( $start_date2 && $sales_data2 ) ? $sales_data2->discount_total : 0 ),
					'diff' => ( $product_id ) ? '' : ( ( $start_date2 && $sales_data2 && $sales_data2->discount_total > 0 ) ? number_format( ( ( ( $sales_data ) ? $sales_data->discount_total : 0 ) - ( ( $start_date2 && $sales_data2 ) ? $sales_data2->discount_total : 0 ) ) / ( ( $start_date2 && $sales_data2 ) ? $sales_data2->discount_total : 0 ) * 100, 2, '.', '' ) : '' )
				),
				'refund' => (object) array(
					'set1' => ( $product_id ) ? $GLOBALS['currency']->get_currency_display( 0 ) : $GLOBALS['currency']->get_currency_display( ( $sales_data ) ? $sales_data->refund_total : 0 ),
					'set2' => ( $product_id ) ? $GLOBALS['currency']->get_currency_display( 0 ) : $GLOBALS['currency']->get_currency_display( ( $start_date2 && $sales_data2 ) ? $sales_data2->refund_total : 0 ),
					'diff' => ( $product_id ) ? '' : ( ( $start_date2 && $sales_data2 && $sales_data2->refund_total > 0 ) ? number_format( ( ( ( $sales_data ) ? $sales_data->refund_total : 0 ) - ( ( $start_date2 && $sales_data2 ) ? $sales_data2->refund_total : 0 ) ) / ( ( $start_date2 && $sales_data2 ) ? $sales_data2->refund_total : 0 ) * 100, 2, '.', '' ) : '' )
				),
				'net_revenue' => (object) array(
					'set1' => ( $product_id ) ? $GLOBALS['currency']->get_currency_display( 0 ) : $GLOBALS['currency']->get_currency_display( ( $sales_data ) ? $sales_data->total - $sales_data->discount_total - $sales_data->refund_total : 0 ),
					'set2' => ( $product_id ) ? $GLOBALS['currency']->get_currency_display( 0 ) : $GLOBALS['currency']->get_currency_display( ( $start_date2 && $sales_data2 ) ? $sales_data2->total - $sales_data2->discount_total - $sales_data2->refund_total : 0 ),
					'diff' => ( $product_id || !$sales_data || !$sales_data2 || $sales_data2->total - $sales_data2->discount_total - $sales_data2->refund_total == 0 ) ? '' : ( ( $start_date2 && $sales_data2 ) ? number_format( ( ( ( $sales_data ) ? $sales_data->total - $sales_data->discount_total - $sales_data->refund_total : 0 ) - ( ( $start_date2 && $sales_data2 ) ? $sales_data2->total - $sales_data2->discount_total - $sales_data2->refund_total : 0 ) ) / ( ( $start_date2 && $sales_data2 ) ? $sales_data2->total - $sales_data2->discount_total - $sales_data2->refund_total : 0 ) * 100, 2, '.', '' ) : '' )
				),
				'items' => (object) array(
					'set1' => ( $sales_data_item ) ? $sales_data_item->item_count : 0,
					'set2' => ( $start_date2 && $sales_data_item2 ) ? $sales_data_item2->item_count : 0,
					'diff' => ( $start_date2 && $sales_data_item2 && $sales_data_item2->item_count > 0 ) ? number_format( ( ( ( $sales_data_item ) ? $sales_data_item->item_count : 0 ) - ( ( $start_date2 && $sales_data_item2 ) ? $sales_data_item2->item_count : 0 ) ) / ( ( $start_date2 && $sales_data_item2 ) ? $sales_data_item2->item_count : 0 ) * 100, 2, '.', '' ) : ''
				),
				'customers' => (object) array(
					'set1' => ( $product_id ) ? ( ( $sales_data && $sales_data->item_customer_count ) ? $sales_data->item_customer_count : 0 ) : ( ( $sales_data && $sales_data->customer_count ) ? $sales_data->customer_count : 0 ),
					'set2' => ( $product_id ) ? ( ( $start_date2 && $sales_data2 && $sales_data2->item_customer_count ) ? $sales_data2->item_customer_count : 0 ) : ( ( $start_date2 && $sales_data2 && $sales_data2->customer_count ) ? $sales_data2->customer_count : 0 ),
					'diff' => ( $product_id ) ? ( ( $start_date2 && $sales_data2 && $sales_data2->item_customer_count > 0 ) ? number_format( ( ( ( $sales_data ) ? $sales_data->item_customer_count : 0 ) - ( ( $start_date2 && $sales_data2 ) ? $sales_data2->item_customer_count : 0 ) ) / ( ( $start_date2 && $sales_data2 ) ? $sales_data2->item_customer_count : 0 ) * 100, 2, '.', '' ) : '' ) : ( ( $start_date2 && $sales_data2 && $sales_data2->customer_count > 0 ) ? number_format( ( ( ( $sales_data ) ? $sales_data->customer_count : 0 ) - ( ( $start_date2 && $sales_data2 ) ? $sales_data2->customer_count : 0 ) ) / ( ( $start_date2 && $sales_data2 ) ? $sales_data2->customer_count : 0 ) * 100, 2, '.', '' ) : '' )
				),
				'carts' => (object) array(
					'set1' => ( $sales_data_cart ) ? $sales_data_cart->cart_total : 0,
					'set2' => ( $start_date2 && $sales_data_cart2 ) ? $sales_data_cart2->cart_total : 0,
					'diff' => ( $start_date2 && $sales_data2 && $sales_data_cart2->cart_total > 0 ) ? number_format( ( ( ( $sales_data_cart ) ? $sales_data_cart->cart_total : 0 ) - ( ( $start_date2 && $sales_data_cart2 ) ? $sales_data_cart2->cart_total : 0 ) ) / ( ( $start_date2 && $sales_data_cart2 ) ? $sales_data_cart2->cart_total : 0 ) * 100, 2, '.', '' ) : ''
				),
				'orders' => (object) array(
					'set1' => ( $product_id ) ? ( ( $sales_data && $sales_data->item_order_count ) ? $sales_data->item_order_count : 0 ) : ( ( $sales_data && $sales_data->order_count ) ? $sales_data->order_count : 0 ),
					'set2' => ( $product_id ) ? ( ( $start_date2 && $sales_data2 && $sales_data2->item_order_count ) ? $sales_data2->item_order_count : 0 ) : ( ( $start_date2 && $sales_data2 && $sales_data2->order_count ) ? $sales_data2->order_count : 0 ),
					'diff' => ( $product_id ) ? ( ( $start_date2 && $sales_data2 && $sales_data2->item_order_count > 0 ) ? number_format( ( ( ( $sales_data ) ? $sales_data->item_order_count : 0 ) - ( ( $start_date2 && $sales_data2 ) ? $sales_data2->item_order_count : 0 ) ) / ( ( $start_date2 && $sales_data2 ) ? $sales_data2->item_order_count : 0 ) * 100, 2, '.', '' ) : '' ) : ( ( $start_date2 && $sales_data2 && $sales_data2->order_count > 0 ) ? number_format( ( ( ( $sales_data ) ? $sales_data->order_count : 0 ) - ( ( $start_date2 && $sales_data2 ) ? $sales_data2->order_count : 0 ) ) / ( ( $start_date2 && $sales_data2 ) ? $sales_data2->order_count : 0 ) * 100, 2, '.', '' ) : '' )
				),
				'fees' => $fees,
			);
		}

		public function get_stats( $type, $start_date, $end_date, $start_date2 = false, $end_date2 = false, $range = 'daily', $product_id = false, $country = false, $billing_country = false, $location_id = 0 ){
			if( $type == 'sales' ){
				$chart_label = __( 'Sales', 'wp-easycart' );
			}else if( $type == 'items' ){
				$chart_label = __( 'Items Sold', 'wp-easycart' );
			}else if( $type == 'carts' ){
				$chart_label = __( 'Abandoned Carts', 'wp-easycart' );
			}
			if( $start_date2 ){
				$data_items = $this->get_dataset_cached( $type, $start_date, $end_date, $range, $product_id, $country, $billing_country, $location_id );
				$data_items2 = $this->get_dataset_cached( $type, $start_date2, $end_date2, $range, $product_id, $country, $billing_country, $location_id );
				$days1 = $this->get_sales_dataset_length( $start_date, $end_date, $range );
				if( $range == 'daily' && $days1 <= 2 ){
					$days1 = ($days1+1) * 24;
					$range = 'hourly';
				}
				$labels = array( );
				$labels1 = array( );
				$labels2 = array( );
				for( $i=0; $i<=$days1; $i++ ){
					if( $range == 'hourly' ){
						$labels[] = ( $i % 2 ) ? date( 'g:00 a', strtotime( '+' . $i . ' hour', strtotime( $start_date ) ) ) : '';
						$labels1[] = date( 'h:00 l', strtotime( '+' . $i . ' hour', strtotime( $start_date ) ) );
						$labels2[] = date( 'h:00 l', strtotime( '+' . $i . ' hour', strtotime( $start_date2 ) ) );

					}else if( $range == 'daily' ){
						$labels[] = ( $i % 2 ) ? date( 'M d', strtotime( '+' . $i . ' day', strtotime( $start_date ) ) ) : '';
						$labels1[] = date( 'M d', strtotime( '+' . $i . ' day', strtotime( $start_date ) ) );
						$labels2[] = date( 'M d', strtotime( '+' . $i . ' day', strtotime( $start_date2 ) ) );

					}else if( $range == 'weekly' ){
						$labels[] = date( 'M d, Y', strtotime( 'sunday last week', strtotime( '+' . ($i*7) . ' day', strtotime( $start_date ) ) ) );
						$labels1[] = date( 'M d, Y', strtotime( 'sunday last week', strtotime( '+' . ($i*7) . ' day', strtotime( $start_date ) ) ) );
						$labels2[] = date( 'M d, Y', strtotime( 'sunday last week', strtotime( '+' . ($i*7) . ' day', strtotime( $start_date2 ) ) ) );

					}else if( $range == 'monthly' ){
						$labels[] = date( 'M d, Y', strtotime( '+' . $i . ' month', strtotime( $start_date ) ) );
						$labels1[] = date( 'M d, Y', strtotime( '+' . $i . ' month', strtotime( $start_date ) ) );
						$labels2[] = date( 'M d, Y', strtotime( '+' . $i . ' month', strtotime( $start_date2 ) ) );

					}else if( $range == 'yearly' ){
						$labels[] = date( 'M d, Y', strtotime( '+' . $i . ' year', strtotime( $start_date ) ) );
						$labels1[] = date( 'M d, Y', strtotime( '+' . $i . ' year', strtotime( $start_date ) ) );
						$labels2[] = date( 'M d, Y', strtotime( '+' . $i . ' year', strtotime( $start_date2 ) ) );

					}
				}

				$data = (object) array(
					'labels'	=> $labels,
					'datasets'	=> array( 
						(object) array(
							'label'	=> $chart_label . " " . date( 'M, d', strtotime( $start_date ) ) . ' - ' . date( 'M, d Y', strtotime( $end_date ) ),
							'backgroundColor' => $this->hex2rgba( get_option( 'ec_option_admin_color' ), .15 ),
							'borderColor'   => $this->hex2rgba( get_option( 'ec_option_admin_color' ), .8 ),
							'borderWidth'	=> 1,
							'data' => $data_items,
							'datalabels' => $labels1
						),
						(object) array(
							'label'	=> $chart_label . " " . date( 'M, d', strtotime( $start_date2 ) ) . ' - ' . date( 'M, d Y', strtotime( $end_date2 ) ),
							'backgroundColor' => 'rgba( 0, 0, 0, .05 )',
							'borderColor'   => 'rgba( 0, 0, 0, .2 )',
							'borderWidth'	=> 1,
							'data' => $data_items2,
							'datalabels' => $labels2
						)
					)
				);

			}else{
				$data_items = $this->get_dataset_cached( $type, $start_date, $end_date, $range, $product_id, $country, $billing_country, $location_id );
				$days1 = $this->get_sales_dataset_length( $start_date, $end_date, $range );
				if( $range == 'daily' && $days1 <= 2 ){
					$days1 = ( $days1 + 1 ) * 24;
					$range = 'hourly';
				}
				$labels = array( );
				$labels1 = array( );
				for( $i=0; $i<=$days1; $i++ ){
					if( $range == 'hourly' ){
						$labels[] = ( $i % 2 ) ? date( 'g:00 a', strtotime( '+' . $i . ' hour', strtotime( $start_date ) ) ) : '';
						$labels1[] = date( 'h:00 l', strtotime( '+' . $i . ' hour', strtotime( $start_date ) ) );

					}else if( $range == 'daily' ){
						$labels[] = ( $i % 2 ) ? date( 'M d', strtotime( '+' . $i . ' day', strtotime( $start_date ) ) ) : '';
						$labels1[] = date( 'M d', strtotime( '+' . $i . ' day', strtotime( $start_date ) ) );

					}else if( $range == 'weekly' ){
						$labels[] = date( 'M d, Y', strtotime( 'sunday last week', strtotime( '+' . ($i*7) . ' day', strtotime( $start_date ) ) ) );
						$labels1[] = date( 'M d, Y', strtotime( 'sunday last week', strtotime( '+' . ($i*7) . ' day', strtotime( $start_date ) ) ) );

					}else if( $range == 'monthly' ){
						$labels[] = date( 'M d, Y', strtotime( '+' . $i . ' month', strtotime( $start_date ) ) );
						$labels1[] = date( 'M d, Y', strtotime( '+' . $i . ' month', strtotime( $start_date ) ) );

					}else if( $range == 'yearly' ){
						$labels[] = date( 'M d, Y', strtotime( '+' . $i . ' year', strtotime( $start_date ) ) );
						$labels1[] = date( 'M d, Y', strtotime( '+' . $i . ' year', strtotime( $start_date ) ) );

					}
				}

				$data = (object) array(
					'labels'	=> $labels,
					'datasets'	=> array( 
						(object) array(
							'label'	=> $chart_label . " " . date( 'M, d', strtotime( $start_date ) ) . ' - ' . date( 'M, d', strtotime( $end_date ) ),
							'backgroundColor' => $this->hex2rgba( get_option( 'ec_option_admin_color' ), .15 ),
							'borderColor'   => $this->hex2rgba( get_option( 'ec_option_admin_color' ), .8 ),
							'borderWidth'	=> 1,
							'data' => $data_items,
							'datalabels' => $labels1
						)
					)
				);
			}
			return json_encode( $data );
		}

		/**
		 * Unviewed-order badge count. Cached 60 seconds in wpec_unviewed_orders, stamped with the order
		 * fingerprint ( a new order from any path misses ), skipped and dropped on requests that change
		 * order_viewed ( see request_touches_order_viewed() ), and flushed by flush_order_caches().
		 *
		 * @since 6.0.0 cached; the count itself is unchanged.
		 */
		private function get_total_new_unviewed_orders( ){
			$bypass = self::request_touches_order_viewed();
			if ( ! $bypass ) {
				$cached = get_transient( 'wpec_unviewed_orders' );
				if ( is_array( $cached ) && isset( $cached['fp'], $cached['count'] ) && $cached['fp'] === self::order_fingerprint() ) {
					return (int) $cached['count'];
				}
			}
			$count = (int) $this->wpdb->get_var( "SELECT COUNT( ec_order.order_id ) as total FROM ec_order WHERE ec_order.order_viewed = 0" );
			if ( ! $bypass ) {
				set_transient( 'wpec_unviewed_orders', array( 'fp' => self::order_fingerprint(), 'count' => $count ), MINUTE_IN_SECONDS );
			}
			return $count;
		}
		/* END STATS FUNCTIONS */

		public function setup_menu( ){

			if( function_exists( 'wp_easycart_admin_license' ) ){
				$license = wp_easycart_admin_license( )->license_check();
				$license_data = wp_easycart_admin_license( )->license_data;
				$license_info = get_option( 'wp_easycart_license_info' );
			}

			if( function_exists( 'wp_easycart_admin_license' ) && ( !wp_easycart_admin_license( )->active_license || $license_data->is_trial ) ){
				$registration_count = 1;
				$registration_label = sprintf( __( 'Registration %s', 'wp-easycart' ), "<span class='update-plugins count-$registration_count' title='" . __( 'License', 'wp-easycart' ) . "'><span class='update-count'>" . number_format_i18n($registration_count) . "</span></span>" );
				$license_label = sprintf( __( 'Store Status %s', 'wp-easycart' ), "<span class='update-plugins count-$registration_count' title='" . __( 'License', 'wp-easycart' ) . "'><span class='update-count'>" . number_format_i18n($registration_count) . "</span></span>" );
			} else {
				$registration_count = 0;
				$registration_label = sprintf( __( 'Registration %s', 'wp-easycart' ), "<span class='update-plugins count-$registration_count' title='" . __( 'License', 'wp-easycart' ) . "'><span class='update-count'>" . number_format_i18n($registration_count) . "</span></span>" );

				if( function_exists( 'wp_easycart_admin_license' ) ){
					$test_now = time( );
					$test_expiration = strtotime( $license_data->support_end_date );
					$test_diff = $test_expiration - $test_now;
					$days_left = round( $test_diff / ( 60 * 60 * 24 ) );
					$days_left = ( $days_left < 0 ) ? 0 : $days_left; // No Negative
					if( $days_left < 70 ){
						$license_label = sprintf( __( 'Store Status %s', 'wp-easycart' ), "<span class='update-plugins count-1' title='" . __( 'License', 'wp-easycart' ) . "'><span class='update-count'>" . number_format_i18n(1) . "</span></span>" );
					}else{
						$license_label = __( 'Store Status', 'wp-easycart' );
					}
				}else{
					$license_label = __( 'Store Status', 'wp-easycart' );
				}
			}

			//new unread order notification
			$orders_count = $this->new_unviewed_orders;
			$order_label = sprintf( __( 'Orders %s', 'wp-easycart' ), "<span class='update-plugins count-$orders_count' title='" . __( 'New Orders', 'wp-easycart' ) . "'><span class='update-count'>" . number_format_i18n($orders_count) . "</span></span>" );

			//total notifications
			$total_notifications = $registration_count + $orders_count;
			$mainmenu_label = sprintf( __( 'WP EasyCart %s', 'wp-easycart' ), "<span class='update-plugins count-$total_notifications' title='" . __( 'New Orders', 'wp-easycart' ) . "'><span class='update-count'>" . number_format_i18n($total_notifications) . "</span></span>" );

			$store_status_label = __( 'Diagnostics', 'wp-easycart' );
			if( !$this->database_check_current( ) ){
				$store_status_label .= '<span class="update-plugins count-1" title="' . __( 'Status Errors', 'wp-easycart' ) . '"><span class="update-count">1</span></span>';
			}

			if ( current_user_can( 'wpec_reports' ) ) {
				add_menu_page( 'WP EasyCart', $mainmenu_label, 'wpec_reports', 'wp-easycart-dashboard', array( $this, 'load_dashboard' ), 'dashicons-cart', 58 );
			} else if ( current_user_can( 'wpec_store_status' ) ) {
				add_menu_page( 'WP EasyCart', $mainmenu_label, 'wpec_store_status', 'wp-easycart-license-status', array( $this, 'load_license_status' ), 'dashicons-cart', 58 );
			} else if ( current_user_can( 'wpec_products' ) ) {
				add_menu_page( 'WP EasyCart', $mainmenu_label, 'wpec_products', 'wp-easycart-products', array( $this, 'load_products' ), 'dashicons-cart', 58 );
			} else if ( current_user_can( 'wpec_orders' ) ) {
				add_menu_page( 'WP EasyCart', $mainmenu_label, 'wpec_orders', 'wp-easycart-orders', array( $this, 'load_orders' ), 'dashicons-cart', 58 );
			} else if ( current_user_can( 'wpec_users' ) ) {
				add_menu_page( 'WP EasyCart', $mainmenu_label, 'wpec_users', 'wp-easycart-users', array( $this, 'load_users' ), 'dashicons-cart', 58 );
			} else if ( current_user_can( 'wpec_marketing' ) ) {
				add_menu_page( 'WP EasyCart', $mainmenu_label, 'wpec_marketing', 'wp-easycart-rates', array( $this, 'load_marketing' ), 'dashicons-cart', 58 );
			} else if ( current_user_can( 'wpec_settings' ) ) {
				add_menu_page( 'WP EasyCart', $mainmenu_label, 'wpec_settings', 'wp-easycart-settings', array( $this, 'load_settings' ), 'dashicons-cart', 58 );
			} else if ( current_user_can( 'wpec_diagnostics' ) ) {
				add_menu_page( 'WP EasyCart', $mainmenu_label, 'wpec_diagnostics', 'wp-easycart-status', array( $this, 'load_status' ), 'dashicons-cart', 58 );
			} else if ( current_user_can( 'wpec_registration' ) ) {
				add_menu_page( 'WP EasyCart', $mainmenu_label, 'wpec_registration', 'wp-easycart-registration', array( $this, 'load_registration' ), 'dashicons-cart', 58 );
			} else if ( current_user_can( 'manage_options' ) ) {
				add_menu_page( 'WP EasyCart', $mainmenu_label, 'manage_options', 'wp-easycart-dashboard', array( $this, 'load_dashboard' ), 'dashicons-cart', 58 );
			}

			if ( current_user_can( 'wpec_manager' ) ) {
				add_menu_page( __( 'Extensions', 'wp-easycart' ), __( 'Extensions', 'wp-easycart' ), 'wpec_manager', 'ec_adminv2', array( $this, 'load_extensions_page' ), 'dashicons-cart', 59 );
			} else if ( current_user_can( 'manage_options' ) ) {
				add_menu_page( __( 'Extensions', 'wp-easycart' ), __( 'Extensions', 'wp-easycart' ), 'manage_options', 'ec_adminv2', array( $this, 'load_extensions_page' ), 'dashicons-cart', 59 );
			}

			if ( current_user_can( 'wpec_reports' ) ) {
				add_submenu_page( 'wp-easycart-dashboard', __( 'WP EasyCart Reports', 'wp-easycart' ), __( 'Reports', 'wp-easycart' ), 'wpec_reports', 'wp-easycart-dashboard', array( $this, 'load_dashboard' ) );
			} else if ( current_user_can( 'manage_options' ) ) {
				add_submenu_page( 'wp-easycart-dashboard', __( 'WP EasyCart Reports', 'wp-easycart' ), __( 'Reports', 'wp-easycart' ), 'manage_options', 'wp-easycart-dashboard', array( $this, 'load_dashboard' ) );
			}

			if ( current_user_can( 'wpec_store_status' ) ) {
				add_submenu_page( 'wp-easycart-dashboard', __( 'WP EasyCart Store Status', 'wp-easycart' ), $license_label, 'wpec_store_status', 'wp-easycart-license-status', array( $this, 'load_license_status' ) );
			} else if ( current_user_can( 'manage_options' ) ) {
				add_submenu_page( 'wp-easycart-dashboard', __( 'WP EasyCart Store Status', 'wp-easycart' ), $license_label, 'manage_options', 'wp-easycart-license-status', array( $this, 'load_license_status' ) );
			}

			if ( current_user_can( 'wpec_products' ) ) {
				add_submenu_page( 'wp-easycart-dashboard', __( 'WP EasyCart Products', 'wp-easycart' ), __( 'Products', 'wp-easycart' ), 'wpec_products', 'wp-easycart-products', array( $this, 'load_products' ) );
			} else if ( current_user_can( 'manage_options' ) ) {
				add_submenu_page( 'wp-easycart-dashboard', __( 'WP EasyCart Products', 'wp-easycart' ), __( 'Products', 'wp-easycart' ), 'manage_options', 'wp-easycart-products', array( $this, 'load_products' ) );
			}

			if ( current_user_can( 'wpec_orders' ) ) {
				add_submenu_page( 'wp-easycart-dashboard', __( 'WP EasyCart Orders', 'wp-easycart' ), $order_label, 'wpec_orders', 'wp-easycart-orders', array( $this, 'load_orders' ) );
			} else if ( current_user_can( 'manage_options' ) ) {
				add_submenu_page( 'wp-easycart-dashboard', __( 'WP EasyCart Orders', 'wp-easycart' ), $order_label, 'manage_options', 'wp-easycart-orders', array( $this, 'load_orders' ) );
			}

			if ( current_user_can( 'wpec_users' ) ) {
				add_submenu_page( 'wp-easycart-dashboard', __( 'WP EasyCart Users', 'wp-easycart' ), __( 'Users', 'wp-easycart' ), 'wpec_users', 'wp-easycart-users', array( $this, 'load_users' ) );
			} else if ( current_user_can( 'manage_options' ) ) {
				add_submenu_page( 'wp-easycart-dashboard', __( 'WP EasyCart Users', 'wp-easycart' ), __( 'Users', 'wp-easycart' ), 'manage_options', 'wp-easycart-users', array( $this, 'load_users' ) );
			}

			if ( current_user_can( 'wpec_marketing' ) ) {
				add_submenu_page( 'wp-easycart-dashboard', __( 'WP EasyCart Marketing', 'wp-easycart' ), __( 'Marketing', 'wp-easycart' ), 'wpec_marketing', 'wp-easycart-rates', array( $this, 'load_marketing' ) );
			} else if ( current_user_can( 'manage_options' ) ) {
				add_submenu_page( 'wp-easycart-dashboard', __( 'WP EasyCart Marketing', 'wp-easycart' ), __( 'Marketing', 'wp-easycart' ), 'manage_options', 'wp-easycart-rates', array( $this, 'load_marketing' ) );
			}

			if ( current_user_can( 'wpec_settings' ) ) {
				add_submenu_page( 'wp-easycart-dashboard', __( 'WP EasyCart Settings', 'wp-easycart' ), __( 'Settings', 'wp-easycart' ), 'wpec_settings', 'wp-easycart-settings', array( $this, 'load_settings' ) );
			} else if ( current_user_can( 'manage_options' ) ) {
				add_submenu_page( 'wp-easycart-dashboard', __( 'WP EasyCart Settings', 'wp-easycart' ), __( 'Settings', 'wp-easycart' ), 'manage_options', 'wp-easycart-settings', array( $this, 'load_settings' ) );
			}

			if ( current_user_can( 'wpec_diagnostics' ) ) {
				add_submenu_page( 'wp-easycart-dashboard', __( 'WP EasyCart Diagnostics', 'wp-easycart' ), $store_status_label, 'wpec_diagnostics', 'wp-easycart-status', array( $this, 'load_status' ) );
			} else if ( current_user_can( 'manage_options' ) ) {
				add_submenu_page( 'wp-easycart-dashboard', __( 'WP EasyCart Diagnostics', 'wp-easycart' ), $store_status_label, 'manage_options', 'wp-easycart-status', array( $this, 'load_status' ) );
			}

			if ( current_user_can( 'wpec_registration' ) ) {
				add_submenu_page( 'wp-easycart-dashboard', __( 'WP EasyCart Registration', 'wp-easycart' ), $registration_label , 'wpec_registration', 'wp-easycart-registration', array( $this, 'load_registration' ) );
			} else if ( current_user_can( 'manage_options' ) ) {
				add_submenu_page( 'wp-easycart-dashboard', __( 'WP EasyCart Registration', 'wp-easycart' ), $registration_label , 'manage_options', 'wp-easycart-registration', array( $this, 'load_registration' ) );
			}

			if( ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_reports' ) ) && function_exists( 'wp_easycart_admin_license' ) ) {
				if( isset( $license_data->is_trial ) && $license_data->is_trial ){
					if ( current_user_can( 'wpec_reports' ) ) {
						add_submenu_page( 'wp-easycart-dashboard', __( 'Upgrade to Premium', 'wp-easycart' ), '<strong id="wp-easycart-premium-link" style="color:#c0fc14;">' . __( 'Upgrade to Premium', 'wp-easycart' ) . '</strong>', 'wpec_reports', 'wp-easycart-license-manage-trial', array( $this, 'upgrade_premium_none' ) );
					} else {
						add_submenu_page( 'wp-easycart-dashboard', __( 'Upgrade to Premium', 'wp-easycart' ), '<strong id="wp-easycart-premium-link" style="color:#c0fc14;">' . __( 'Upgrade to Premium', 'wp-easycart' ) . '</strong>', 'manage_options', 'wp-easycart-license-manage-trial', array( $this, 'upgrade_premium_none' ) );
					}
					add_filter( 'clean_url', array( $this, 'upgrade_premium' ), 10, 3);

				}else if( isset( $license_data->support_end_date ) && time( ) > strtotime( $license_data->support_end_date ) ){ // Expired
					if ( current_user_can( 'wpec_reports' ) ) {
						add_submenu_page( 'wp-easycart-dashboard', __( 'RENEW LICENSE', 'wp-easycart' ), '<strong id="wp-easycart-premium-link" style="color:#ffa800;">' . __( 'RENEW LICENSE', 'wp-easycart' ) . '</strong>', 'wpec_reports', 'wp-easycart-license-renew', array( $this, 'upgrade_premium_none' ) );
					} else {
						add_submenu_page( 'wp-easycart-dashboard', __( 'RENEW LICENSE', 'wp-easycart' ), '<strong id="wp-easycart-premium-link" style="color:#ffa800;">' . __( 'RENEW LICENSE', 'wp-easycart' ) . '</strong>', 'manage_options', 'wp-easycart-license-renew', array( $this, 'upgrade_premium_none' ) );
					}
					add_filter( 'clean_url', array( $this, 'upgrade_premium' ), 10, 3);

				}else if( isset( $license_data->support_end_date ) && $license_data->model_number == 'ec400' ){ // Pro User
					if ( current_user_can( 'wpec_reports' ) ) {
						add_submenu_page( 'wp-easycart-dashboard', __( 'Upgrade to Premium', 'wp-easycart' ), '<strong id="wp-easycart-premium-link" style="color:#c0fc14;">' . __( 'Upgrade to Premium', 'wp-easycart' ) . '</strong>', 'wpec_reports', 'wp-easycart-license-upgrade', array( $this, 'upgrade_premium_none' ) );
					} else {
						add_submenu_page( 'wp-easycart-dashboard', __( 'Upgrade to Premium', 'wp-easycart' ), '<strong id="wp-easycart-premium-link" style="color:#c0fc14;">' . __( 'Upgrade to Premium', 'wp-easycart' ) . '</strong>', 'manage_options', 'wp-easycart-license-upgrade', array( $this, 'upgrade_premium_none' ) );
					}
					add_filter( 'clean_url', array( $this, 'upgrade_premium' ), 10, 3);

				}else if( isset( $license_data->support_end_date ) && $license_data->model_number == 'ec410' ){ // Premium User
					if ( current_user_can( 'wpec_reports' ) ) {
						add_submenu_page( 'wp-easycart-dashboard', __( 'Check License Status', 'wp-easycart' ), '<strong id="wp-easycart-premium-link" style="color:#c0fc14;">' . __( 'Check License Status', 'wp-easycart' ) . '</strong>', 'wpec_reports', 'wp-easycart-license-manage', array( $this, 'upgrade_premium_none' ) );
					} else {
						add_submenu_page( 'wp-easycart-dashboard', __( 'Check License Status', 'wp-easycart' ), '<strong id="wp-easycart-premium-link" style="color:#c0fc14;">' . __( 'Check License Status', 'wp-easycart' ) . '</strong>', 'manage_options', 'wp-easycart-license-manage', array( $this, 'upgrade_premium_none' ) );
					}
					add_filter( 'clean_url', array( $this, 'upgrade_premium' ), 10, 3);

				}else{ // Missing Key Data?
					if ( current_user_can( 'wpec_reports' ) ) {
						add_submenu_page( 'wp-easycart-dashboard', __( 'Check License Status', 'wp-easycart' ), '<strong id="wp-easycart-premium-link" style="color:#c0fc14;">' . __( 'Check License Status', 'wp-easycart' ) . '</strong>', 'wpec_reports', 'wp-easycart-license-manage', array( $this, 'upgrade_premium_none' ) );
					} else {
						add_submenu_page( 'wp-easycart-dashboard', __( 'Check License Status', 'wp-easycart' ), '<strong id="wp-easycart-premium-link" style="color:#c0fc14;">' . __( 'Check License Status', 'wp-easycart' ) . '</strong>', 'manage_options', 'wp-easycart-license-manage', array( $this, 'upgrade_premium_none' ) );
					}
					add_filter( 'clean_url', array( $this, 'upgrade_premium' ), 10, 3);

				}

			} else if( current_user_can( 'manage_options' ) || current_user_can( 'wpec_reports' ) ) { // FREE USER 
				if ( current_user_can( 'wpec_reports' ) ) {
					add_submenu_page( 'wp-easycart-dashboard', __( 'Upgrade to Premium', 'wp-easycart' ), '<strong id="wp-easycart-premium-link" style="color:#c0fc14;">' . __( 'Upgrade to Premium', 'wp-easycart' ) . '</strong>', 'wpec_reports', 'wp-easycart-premium', array( $this, 'upgrade_premium_none' ) );
				} else {
					add_submenu_page( 'wp-easycart-dashboard', __( 'Upgrade to Premium', 'wp-easycart' ), '<strong id="wp-easycart-premium-link" style="color:#c0fc14;">' . __( 'Upgrade to Premium', 'wp-easycart' ) . '</strong>', 'manage_options', 'wp-easycart-premium', array( $this, 'upgrade_premium_none' ) );
				}
				add_filter( 'clean_url', array( $this, 'upgrade_premium' ), 10, 3);
			}

		}

		public function setup_pro_hooks( ){
			/* Products Tab*/
			add_action( 'wp_easycart_admin_subscription_plans_list', array( $this, 'show_upgrade' ) );
			add_action( 'wp_easycart_admin_subscription_plans_details', array( $this, 'show_upgrade' ) );
			add_filter( 'wp_easycart_admin_advanced_option_type', array( $this, 'filter_option_type' ) );

			/* Marketing Tab */
			add_action( 'wp_easycart_admin_subscriptions_list', array( $this, 'show_upgrade' ) );
			add_action( 'wp_easycart_admin_subscriptions_details', array( $this, 'show_upgrade' ) );
			add_action( 'wp_easycart_admin_downloads_list', array( $this, 'show_upgrade' ) );
			add_action( 'wp_easycart_admin_downloads_details', array( $this, 'show_upgrade' ) );
			add_action( 'wp_easycart_admin_abandon_cart_load', array( $this, 'show_upgrade' ) );
			add_action( 'wp_easycart_admin_coupon_list', array( $this, 'show_upgrade' ) );
			add_action( 'wp_easycart_admin_coupon_details', array( $this, 'show_upgrade' ) );
			add_action( 'wp_easycart_admin_giftcard_list', array( $this, 'show_upgrade' ) );
			add_action( 'wp_easycart_admin_giftcard_details', array( $this, 'show_upgrade' ) );
			add_action( 'wp_easycart_admin_promotion_list', array( $this, 'show_upgrade' ) );
			add_action( 'wp_easycart_admin_promotion_details', array( $this, 'show_upgrade' ) );
			add_action( 'wp_easycart_admin_offers_hub', array( $this, 'show_upgrade' ) );
			/* Flex-Fees is PRO-only: the locked page, never the store's real fee rows ( @since 6.0.0 ). */
			add_action( 'wp_easycart_admin_fee_list', array( $this, 'show_fee_locked_page' ) );
			add_action( 'wp_easycart_admin_fee_details', array( $this, 'show_fee_locked_page' ) );
			add_action( 'wp_easycart_admin_schedule_list', array( $this, 'show_upgrade' ) );
			add_action( 'wp_easycart_admin_schedule_details', array( $this, 'show_upgrade' ) );
			add_action( 'wp_easycart_admin_location_list', array( $this, 'show_upgrade' ) );
			add_action( 'wp_easycart_admin_location_details', array( $this, 'show_upgrade' ) );

			$pro_plugin_base = 'wp-easycart-pro/wp-easycart-admin-pro.php';
			$pro_plugin_file = EC_PLUGIN_DIRECTORY . '-pro/wp-easycart-admin-pro.php';

			$pro_plugin_base_legacy = 'wp-easycart-admin/wp-easycart-admin-pro.php';
			$pro_plugin_file_legacy = EC_PLUGIN_DIRECTORY . '-admin/wp-easycart-admin-pro.php';

			if( file_exists( $pro_plugin_file ) || file_exists( $pro_plugin_file_legacy ) ){
				remove_action( 'wp_easycart_admin_messages', array( wp_easycart_admin( ), 'load_upsell_image' ) );
				remove_filter( 'wp_easycart_admin_advanced_option_type', array( $this, 'filter_option_type' ) );
				remove_action( 'wp_dashboard_setup', array( $this, 'add_ec_nag_widget' ) );
			}

			/* 6.0.0: a PRO older than wp_easycart_admin_pro_gate::MIN_PRO_VERSION still calls admin controllers this
			   release removed, so its admin is not loaded at all; wp_easycart_pro_check() asks for the PRO update. */
			if ( class_exists( 'wp_easycart_admin_pro_gate' ) && wp_easycart_admin_pro_gate::is_outdated() ) {
				return;
			}

			do_action( 'wp_easycart_admin_pro_ready' );
		}

		public function remove_lock_icon( $content ){
			return "";
		}

		public function load_dashboard( ){
			add_action( 'wp_easycart_admin_shell_content', array( $this, 'load_dashboard_content' ), 1, 0 );
			$this->load_admin_shell( );
		}

		public function load_dashboard_content( ){
			include( EC_PLUGIN_DIRECTORY . '/admin/template/dashboard.php' );
		}

		public function load_license_status( ){
			add_action( 'wp_easycart_admin_shell_content', array( $this, 'load_license_status_content' ), 1, 0 );
			$this->load_admin_shell( );
		}

		public function load_license_status_content( ){
			include( EC_PLUGIN_DIRECTORY . '/admin/template/license-status.php' );
		}

		public function display_stat_circle( $label, $percent, $box_title = false, $box_content = false, $box_link = false, $box_link_text = false ){
			$color = 'green';
			if( $percent < .7 && $percent > .35 ){
				$color = 'orange';
			}else if( $percent <= .35 && $percent != 0 ){
				$color = 'red';
			}else if( $percent == 0 ){
				$color = 'grey';
			}else if( $percent == -1 ){
				$color = 'grey';
			}
			$degree = number_format( $percent * 360, 1, '.', '' );
			if( $percent == -1 ){
				$degree = 0;
			}
			$target = ( $box_link && strtolower( substr( $box_link, 0, 5 ) ) == 'https' ) ? '_blank' : '_self';
			echo '<div class="ec_admin_status_circle ' . ( ( $percent >= .5 ) ? 'over-half ' : '' ) . esc_attr( $color ) . '">
				<span' . ( ( $percent == -1 ) ? ' class="expired"' : '' ) . '>' . esc_attr( $label ) . '</span>
				<div class="ec_admin_status_circle_slice">
					<div class="ec_admin_status_circle_bar" style="-webkit-transform: rotate(' . esc_attr( $degree ) . 'deg); -moz-transform: rotate(' . esc_attr( $degree ) . 'deg); -ms-transform: rotate(' . esc_attr( $degree ) . 'deg); -o-transform: rotate(' . esc_attr( $degree ) . 'deg); transform: rotate(' . esc_attr( $degree ) . 'deg);"></div>
					<div class="ec_admin_status_circle_fill"></div>
				</div>
			</div>';

			if( $box_content ){
				echo '<div class="ec_admin_status_circle_content">
					<h4>' . esc_attr( $box_title ) . '</h4>
					<div>' . esc_attr( $box_content ) . '</div>';

				if( $box_link && $box_link_text ){
					echo '
						<a href="' . esc_url( $box_link ) . '"' . ( ( $percent == -1 ) ? ' style="background-color:#e03333"' : '' ) . ' target="' . esc_attr( $target ) . '">' . esc_attr( $box_link_text ) . '</a>';
				}

				echo '</div>';
			}
		}

		public function upgrade_premium_none( ){
			// External Link
		}

		public function upgrade_premium( $url, $original_url, $_context ){
			if( preg_match( '/(?:wp-easycart-premium)$/i', $url ) ){
				remove_filter( 'clean_url', array( $this, 'upgrade_premium' ), 10 );
				$url = 'https://www.wpeasycart.com/wordpress-shopping-cart-pricing/';
			}else if( preg_match( '/(?:wp-easycart-license-manage)$/i', $url ) ){
				remove_filter( 'clean_url', array( $this, 'upgrade_premium' ), 10 );
				$url = 'https://www.wpeasycart.com/my-account/';
			}else if( preg_match( '/(?:wp-easycart-license-manage-trial)$/i', $url ) ){
				$license_info = get_option( 'wp_easycart_license_info' );
				remove_filter( 'clean_url', array( $this, 'upgrade_premium' ), 10 );
				$url = 'https://www.wpeasycart.com/products/wp-easycart-trial-upgrade/?transaction_key=' . $license_info['transaction_key'] . '&license_type=Premium';
			}else if( preg_match( '/(?:wp-easycart-license-renew)$/i', $url ) ){
				$license_data = wp_easycart_admin_license( )->license_data;
				$license_info = get_option( 'wp_easycart_license_info' );
				$url = ( is_object( $license_data ) && isset( $license_data->model_number ) && $license_data->model_number == 'ec400' ) ? 'https://www.wpeasycart.com/products/wp-easycart-professional-support-upgrades/?transaction_key=' . $license_info['transaction_key'] : 'https://www.wpeasycart.com/products/wp-easycart-premium-support-extensions/?transaction_key=' . $license_info['transaction_key'];
				remove_filter( 'clean_url', array( $this, 'upgrade_premium' ), 10 );
			}else if( preg_match( '/(?:wp-easycart-license-upgrade)$/i', $url ) ){
				$license_data = wp_easycart_admin_license( )->license_data;
				$license_info = get_option( 'wp_easycart_license_info' );
				if ( is_array( $license_info ) && isset( $license_info['transaction_key'] ) ) {
					$url = 'https://www.wpeasycart.com/products/wp-easycart-premium-support-extensions/?transaction_key=' . $license_info['transaction_key'];
				} else {
					$url = 'https://www.wpeasycart.com/products/wp-easycart-premium-support-extensions/';
				}
				remove_filter( 'clean_url', array( $this, 'upgrade_premium' ), 10 );
			}
			return $url;
		}

		public function load_extensions_page( ){
			add_action( 'wp_easycart_admin_shell_content', array( $this, 'load_extensions_content' ), 1, 0 );
			$this->load_admin_shell( );
		}

		public function load_settings( ){
			add_action( 'wp_easycart_admin_shell_content', array( $this, 'load_settings_content' ), 1, 0 );
			$this->load_admin_shell( );
		}

		public function load_products( ){
			add_action( 'wp_easycart_admin_shell_content', array( $this, 'load_products_content' ), 1, 0 );
			$this->load_admin_shell( );
		}

		public function load_orders( ){
			add_action( 'wp_easycart_admin_shell_content', array( $this, 'load_orders_content' ), 1, 0 );
			$this->load_admin_shell( );
		}

		public function load_users( ){
			add_action( 'wp_easycart_admin_shell_content', array( $this, 'load_users_content' ), 1, 0 );
			$this->load_admin_shell( );
		}

		public function load_marketing( ){
			add_action( 'wp_easycart_admin_shell_content', array( $this, 'load_marketing_content' ), 1, 0 );
			$this->load_admin_shell( );
		}

		public function load_status( ){
			add_action( 'wp_easycart_admin_shell_content', array( $this, 'load_status_content' ), 1, 0 );
			$this->load_admin_shell( );
		}
		public function load_registration( ){
			add_action( 'wp_easycart_admin_shell_content', array( $this, 'load_registration_content' ), 1, 0 );
			$this->load_admin_shell( );
		}

		public function init_shipping_data( ){

			$this->settings = $this->wpdb->get_row( "SELECT * FROM ec_setting" );
			$this->shipping_zones = $this->wpdb->get_results( "SELECT * FROM ec_zone ORDER BY zone_name ASC" );
			$this->shipping_zones_items = $this->wpdb->get_results( "SELECT ec_zone_to_location.zone_to_location_id, ec_zone_to_location.zone_id, ec_zone_to_location.iso2_cnt, ec_zone_to_location.code_sta, ec_country.name_cnt AS country_name, ec_state.name_sta AS state_name FROM ec_zone, ec_zone_to_location LEFT JOIN ec_country ON ec_country.iso2_cnt = ec_zone_to_location.iso2_cnt LEFT JOIN ec_state ON ( ec_state.code_sta = ec_zone_to_location.code_sta AND ec_state.idcnt_sta =  ec_country.id_cnt ) WHERE ec_zone.zone_id = ec_zone_to_location.zone_id ORDER BY ec_zone.zone_name ASC" );
			$this->countries = $this->wpdb->get_results( "SELECT * FROM ec_country ORDER BY sort_order ASC" );
			$this->states = $this->wpdb->get_results( "SELECT ec_state.*, ec_country.name_cnt as country_name FROM ec_state LEFT JOIN ec_country ON ec_country.id_cnt = ec_state.idcnt_sta ORDER BY ec_state.sort_order ASC" );

		}

		public function load_settings_content( ){

			$this->init_shipping_data( );
			global $wpdb;
			if( !get_option( 'ec_option_setup_wizard_done' ) && $result = $wpdb->get_row( "SELECT product_id FROM ec_product LIMIT 1" ) ){
			   update_option( 'ec_option_setup_wizard_done', 1 );
			}

			// Display Page Setup
			$ecst_subpage = isset( $_GET['subpage'] ) ? sanitize_key( wp_unslash( $_GET['subpage'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing.
			$ecst_owner   = ( '' !== $ecst_subpage && class_exists( 'wp_easycart_admin_settings_registry' ) ) ? wp_easycart_admin_settings_registry::page_for_legacy( $ecst_subpage ) : '';
			if ( 'initial-setup' === $ecst_subpage && ! get_option( 'ec_option_setup_wizard_done' ) ) {
				$ecst_subpage = ''; // Until the wizard is finished, Initial Setup still means the wizard.
				$ecst_owner   = '';
			}
			if ( class_exists( 'wp_easycart_admin_settings_page_v2' ) && wp_easycart_admin_settings_page_v2::is_classic_settings_screen() ) {
				// Classic settings pages keep their own header; the settings-wide search sits just above it.
				echo '<div class="ecv2-wrap ecst-wrap ecst-global-search">';
				wp_easycart_admin_settings_page_v2::print_search( 'compact', $ecst_subpage );
				echo '</div>';
			}
			if ( 'home' === $ecst_subpage && class_exists( 'wp_easycart_admin_settings_home' ) ) {
				wp_easycart_admin_settings_home::render();
			} else if ( '' !== $ecst_subpage && class_exists( 'wp_easycart_admin_settings_registry' ) && wp_easycart_admin_settings_registry::has( $ecst_subpage ) ) {
				// Page converted to the V2 settings layout ( admin/template/settings/<slug>.php ).
				wp_easycart_admin_settings_page_v2::render( $ecst_subpage );
			} else if ( '' !== $ecst_owner && $ecst_owner !== $ecst_subpage ) {
				// Old subpage slug now lives inside a converted page; keep bookmarks working.
				wp_easycart_admin_settings_page_v2::render( $ecst_owner );
			} else if ( isset( $_GET['subpage'] ) && $_GET['subpage'] == "setup-wizard" ) {
				wp_easycart_admin_setup_wizard( )->load_setup_wizard( );
			} else if ( isset( $_GET['subpage'] ) && $_GET['subpage'] == "initial-setup" ){
				// Reached only while the setup wizard is unfinished ( the registry serves Initial Setup afterwards ).
				wp_easycart_admin_setup_wizard( )->load_setup_wizard( );
			} else if ( isset( $_GET['subpage'] ) && $_GET['subpage'] == "fee" ) {
				 wp_easycart_admin_fee()->load_fee_list( );
			} else if ( isset( $_GET['subpage'] ) && $_GET['subpage'] == "country" ) {
				wp_easycart_admin_country( )->load_country_list( );
			} else if ( isset( $_GET['subpage'] ) && $_GET['subpage'] == "states" ) {
				wp_easycart_admin_states( )->load_states_list( );
			} else if ( isset( $_GET['subpage'] ) && $_GET['subpage'] == "perpage" ) {
				wp_easycart_admin_perpage( )->load_perpage_list( );
			} else if ( isset( $_GET['subpage'] ) && $_GET['subpage'] == "pricepoint" ) {
				wp_easycart_admin_pricepoint( )->load_pricepoint_list( );
			} else if ( isset( $_GET['subpage'] ) && $_GET['subpage'] == "schedule" ) {
				wp_easycart_admin_schedule( )->load_schedule_list( );
			} else if ( isset( $_GET['subpage'] ) && $_GET['subpage'] == "location" ) {
				wp_easycart_admin_location( )->load_location_list( );
			} else if ( isset( $_GET['subpage'] ) && $_GET['subpage'] == "logs" ) {
				wp_easycart_admin_logging( )->load_log_list( );
			} else {
				if ( ! get_option( 'ec_option_setup_wizard_done' ) ) {
					wp_easycart_admin_setup_wizard( )->load_setup_wizard( );
				} else {
					wp_easycart_admin_settings_home::render();
				}
			}
		}

		public function load_products_content( ){
			if( isset( $_GET['subpage'] ) && $_GET['subpage'] == "option" ){
				wp_easycart_admin_option( )->load_option_list( );
			}else if( isset( $_GET['subpage'] ) && $_GET['subpage'] == "inventory" ){
				wp_easycart_admin_inventory( )->load_inventory_list( );
			}else if( isset( $_GET['subpage'] ) && $_GET['subpage'] == "optionitems" ){
				wp_easycart_admin_option( )->load_optionitem_list( );
			}else if( isset( $_GET['subpage'] ) && $_GET['subpage'] == "category" ){
				wp_easycart_admin_category( )->load_category_list( );
			}else if( isset( $_GET['subpage'] ) && $_GET['subpage'] == "category-products" ){
				wp_easycart_admin_category( )->load_category_product_list( );
			}else if( isset( $_GET['subpage'] ) && $_GET['subpage'] == "category-products-manage" ){
				wp_easycart_admin_category( )->load_category_product_manage_list( );
			}else if( isset( $_GET['subpage'] ) && $_GET['subpage'] == "menus" ){
				wp_easycart_admin_menus( )->load_menus_list( );
			}else if( isset( $_GET['subpage'] ) && $_GET['subpage'] == "submenus" ){
				wp_easycart_admin_menus( )->load_submenus_list( );
			}else if( isset( $_GET['subpage'] ) && $_GET['subpage'] == "subsubmenus" ){
				wp_easycart_admin_menus( )->load_subsubmenus_list( );
			}else if( isset( $_GET['subpage'] ) && $_GET['subpage'] == "manufacturers" ){
				wp_easycart_admin_manufacturers( )->load_manufacturers_list( );
			}else if( isset( $_GET['subpage'] ) && $_GET['subpage'] == "reviews" ){
				wp_easycart_admin_reviews( )->load_reviews_list( );
			}else if( isset( $_GET['subpage'] ) && $_GET['subpage'] == "subscriptionplans" ){
				wp_easycart_admin_subscription_plans( )->load_subscription_plans_list( );
			}else if( isset( $_GET['subpage'] ) && $_GET['subpage'] == "products" ){
				wp_easycart_admin_products( )->load_products_list( );
			}else{
				wp_easycart_admin_products( )->load_products_list( );
			}
		}

		public function load_orders_content( ){
			if( isset( $_GET['subpage'] ) && $_GET['subpage'] == "orders" ){
				wp_easycart_admin_orders( )->load_orders_list( );
			}else if( isset( $_GET['subpage'] ) && $_GET['subpage'] == "subscriptions" ){
				$subscriptions = new wp_easycart_admin_subscriptions( );
				$subscriptions->load_subscriptions_list( );
			}else if( isset( $_GET['subpage'] ) && $_GET['subpage'] == "downloads" ){
				$downloads = new wp_easycart_admin_downloads( );
				$downloads->load_downloads_list( );
			}else{
				wp_easycart_admin_orders( )->load_orders_list( );
			}
		}

		public function load_users_content( ){
			if( isset( $_GET['subpage'] ) && $_GET['subpage'] == "accounts" ){
				wp_easycart_admin_users( )->load_users_list( );
			}else if( isset( $_GET['subpage'] ) && $_GET['subpage'] == "user-roles" ){
				wp_easycart_admin_user_role( )->load_user_role_list( );
			}else if( isset( $_GET['subpage'] ) && $_GET['subpage'] == "subscribers" ){
				wp_easycart_admin_subscribers( )->load_subscriber_list( );
			}else{
				wp_easycart_admin_users( )->load_users_list( );
			}
		}

		public function load_marketing_content( ){
			if ( isset( $_GET['subpage'] ) && $_GET['subpage'] == "gift-cards" ) {
				$giftcards = new wp_easycart_admin_giftcards( );
				$giftcards = $giftcards->load_giftcards_list();
			} else if( isset( $_GET['subpage'] ) && $_GET['subpage'] == "coupons" ) {
				$coupons = new wp_easycart_admin_coupons( );
				$coupons = $coupons->load_coupons_list();
			} else if( isset( $_GET['subpage'] ) && $_GET['subpage'] == "promotions" ) {
				$promotions = new wp_easycart_admin_promotions( );
				$promotions = $promotions->load_promotions_list();
			} else if( isset( $_GET['subpage'] ) && $_GET['subpage'] == "abandon-cart" ) {
				$abandon_cart = new wp_easycart_admin_abandon_cart( );
				$abandon_cart->load_abandon_cart( );
			} else if( isset( $_GET['subpage'] ) && $_GET['subpage'] == "cart-links" ) {
				$cart_links = new wp_easycart_admin_cart_links( );
				$cart_links->load_cart_links_list( );
			} else {
				$offers = new wp_easycart_admin_offers( );
				$offers->load_offers_hub();
			}
		}

		public function load_status_content( ){
			wp_easycart_admin_store_status( )->load_status( );
		}

		public function load_registration_content( ){
			$registration = new wp_easycart_admin_registration( );
			$registration->load_registration_status( );
		}

		public function load_extensions( ){
			add_action( 'wp_easycart_admin_shell_content', array( $this, 'load_extensions_content' ), 1, 0 );
			$this->load_admin_shell( );
		}

		public function load_extensions_content( ){
			wp_easycart_admin_extensions( )->load_extensions( );
		}

		private function load_admin_shell( ){
			/* 6.0.0: a companion plugin on a mismatched version replaces every EasyCart screen with the update / deactivate
			   page. The shell is never rendered then: an older PRO hooks its nav and message boxes as soon as it loads. */
			if ( class_exists( 'wp_easycart_admin_compat_lock' ) && wp_easycart_admin_compat_lock::is_locked() ) {
				wp_easycart_admin_compat_lock::render_page();
				return;
			}
			include( EC_PLUGIN_DIRECTORY . '/admin/template/shell.php' );
		}

		public function load_mobile_navigation( ){
			include( EC_PLUGIN_DIRECTORY . '/admin/template/mobile_nav.php' );
		}

		public function load_left_navigation( ){
			include( EC_PLUGIN_DIRECTORY . '/admin/template/left_nav.php' );
		}

		public function load_head_navigation( ){
			include( EC_PLUGIN_DIRECTORY . '/admin/template/head_nav.php' );
		}

		public function set_title( $admin_title, $title ){
			if ( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-products" && ( !isset( $_GET['subpage'] ) || $_GET['subpage'] == "products" ) ) {
				return __( 'WP EasyCart Products', 'wp-easycart' );
			} else if ( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-products" && isset( $_GET['subpage'] ) && $_GET['subpage'] == "inventory" ) {
				return __( 'WP EasyCart Inventory', 'wp-easycart' );
			} else if ( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-products" && isset( $_GET['subpage'] ) && ( $_GET['subpage'] == "option" || $_GET['subpage'] == "optionitems" ) ) {
				return __( 'WP EasyCart Option Sets', 'wp-easycart' );
			} else if ( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-products" && isset( $_GET['subpage'] ) && ( $_GET['subpage'] == "category" || $_GET['subpage'] == "category-products" || $_GET['subpage'] == "category-products-manage" ) ) {
				return __( 'WP EasyCart Categories', 'wp-easycart' );
			} else if ( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-products" && isset( $_GET['subpage'] ) && ( $_GET['subpage'] == "menus" || $_GET['subpage'] == "submenus" || $_GET['subpage'] == "subsubmenus" ) ){
				return __( 'WP EasyCart Menus', 'wp-easycart' );
			} else if ( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-products" && isset( $_GET['subpage'] ) && $_GET['subpage'] == "manufacturers" ) {
				return __( 'WP EasyCart Manufacturers', 'wp-easycart' );
			} else if ( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-products" && isset( $_GET['subpage'] ) && $_GET['subpage'] == "reviews" ) {
				return __( 'WP EasyCart Product Reviews', 'wp-easycart' );
			} else if ( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-products" && isset( $_GET['subpage'] ) && $_GET['subpage'] == "subscriptionplans" ) {
				return __( 'WP EasyCart Subscription Plans', 'wp-easycart' );

			} else if ( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-orders" && ( !isset( $_GET['subpage'] ) || $_GET['subpage'] == "orders" ) ) {
				return __( 'WP EasyCart Orders', 'wp-easycart' );
			} else if ( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-orders" && isset( $_GET['subpage'] )&& $_GET['subpage'] == "subscriptions" ) {
				return __( 'WP EasyCart Subscriptions', 'wp-easycart' );
			} else if ( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-orders" && isset( $_GET['subpage'] ) && $_GET['subpage'] == "downloads" ) {
				return __( 'WP EasyCart Downloads', 'wp-easycart' );

			} else if ( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-users" && ( !isset( $_GET['subpage'] ) || $_GET['subpage'] == "accounts" ) ) {
				return __( 'WP EasyCart User Accounts', 'wp-easycart' );
			} else if ( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-users" && isset( $_GET['subpage'] ) && $_GET['subpage'] == "user-roles" ) {
				return __( 'WP EasyCart User Roles', 'wp-easycart' );
			} else if ( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-users" && isset( $_GET['subpage'] ) && $_GET['subpage'] == "subscribers" ) {
				return __( 'WP EasyCart Subscribers', 'wp-easycart' );

			} else if ( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-rates" && ( !isset( $_GET['subpage'] ) || $_GET['subpage'] == "offers" ) ) {
				return __( 'WP EasyCart Offers', 'wp-easycart' );
			} else if ( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-rates" && isset( $_GET['subpage'] ) && $_GET['subpage'] == "coupons" ) {
				return __( 'WP EasyCart Coupons', 'wp-easycart' );
			} else if ( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-rates" && isset( $_GET['subpage'] ) && $_GET['subpage'] == "gift-cards" ) {
				return __( 'WP EasyCart Gift Cards', 'wp-easycart' );
			} else if ( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-rates" && isset( $_GET['subpage'] ) && $_GET['subpage'] == "promotions" ) {
				return __( 'WP EasyCart Promotions', 'wp-easycart' );
			} else if ( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-rates" && isset( $_GET['subpage'] ) && $_GET['subpage'] == "abandon-cart" ) {
				return __( 'WP EasyCart Abandoned Cart', 'wp-easycart' );
			} else if ( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-rates" && isset( $_GET['subpage'] ) && $_GET['subpage'] == "cart-links" ) {
				return __( 'WP EasyCart Cart Links', 'wp-easycart' );

			} else if ( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-settings" && ( !isset( $_GET['subpage'] ) || $_GET['subpage'] == "initial-setup" ) ) {
				return __( 'WP EasyCart Initial Setup', 'wp-easycart' );
			} else if ( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-settings" && isset( $_GET['subpage'] ) && $_GET['subpage'] == "products" ) {
				return __( 'WP EasyCart Product Settings', 'wp-easycart' );
			} else if ( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-settings" && isset( $_GET['subpage'] ) && $_GET['subpage'] == "tax" ) {
				return __( 'WP EasyCart Tax Settings', 'wp-easycart' );
			} else if ( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-settings" && isset( $_GET['subpage'] ) && $_GET['subpage'] == "fee" ) {
				return __( 'WP EasyCart Flex-Fee Settings', 'wp-easycart' );
			} else if ( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-settings" && isset( $_GET['subpage'] ) && $_GET['subpage'] == "schedule" ) {
				return __( 'WP EasyCart Schedule Settings', 'wp-easycart' );
			} else if ( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-settings" && isset( $_GET['subpage'] ) && $_GET['subpage'] == "location" ) {
				return __( 'WP EasyCart Location Settings', 'wp-easycart' );
			} else if ( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-settings" && isset( $_GET['subpage'] ) && $_GET['subpage'] == "shipping-settings" ) {
				return __( 'WP EasyCart Shipping Settings', 'wp-easycart' );
			} else if ( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-settings" && isset( $_GET['subpage'] ) && $_GET['subpage'] == "shipping-rates" ) {
				return __( 'WP EasyCart Shipping Rates', 'wp-easycart' );
			} else if ( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-settings" && isset( $_GET['subpage'] ) && $_GET['subpage'] == "payment" ) {
				return __( 'WP EasyCart Payment Settings', 'wp-easycart' );
			} else if ( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-settings" && isset( $_GET['subpage'] ) && $_GET['subpage'] == "checkout" ) {
				return __( 'WP EasyCart Checkout Settings', 'wp-easycart' );
			} else if ( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-settings" && isset( $_GET['subpage'] ) && $_GET['subpage'] == "home" ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page title.
				return __( 'WP EasyCart Settings', 'wp-easycart' );
			} else if ( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-settings" && isset( $_GET['subpage'] ) && class_exists( 'wp_easycart_admin_settings_registry' ) && wp_easycart_admin_settings_registry::has( sanitize_key( wp_unslash( $_GET['subpage'] ) ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page title.
				$ecst_page = wp_easycart_admin_settings_registry::page( sanitize_key( wp_unslash( $_GET['subpage'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page title.
				return sprintf( __( 'WP EasyCart %s', 'wp-easycart' ), $ecst_page['title'] );
			} else if ( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-settings" && isset( $_GET['subpage'] ) && $_GET['subpage'] == "account" ) {
				return __( 'WP EasyCart Account Settings', 'wp-easycart' );
			} else if ( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-settings" && isset( $_GET['subpage'] ) && $_GET['subpage'] == "miscellaneous" ) {
				return __( 'WP EasyCart Additional Settings', 'wp-easycart' );
			} else if ( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-settings" && isset( $_GET['subpage'] ) && $_GET['subpage'] == "language-editor" ) {
				return __( 'WP EasyCart Language Editor', 'wp-easycart' );
			} else if ( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-settings" && isset( $_GET['subpage'] ) && $_GET['subpage'] == "design" ) {
				return __( 'WP EasyCart Design Settings', 'wp-easycart' );
			} else if ( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-settings" && isset( $_GET['subpage'] ) && $_GET['subpage'] == "email-setup" ) {
				return __( 'WP EasyCart Email Setup', 'wp-easycart' );
			} else if ( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-settings" && isset( $_GET['subpage'] ) && $_GET['subpage'] == "third-party" ) {
				return __( 'WP EasyCart Third Party Settings', 'wp-easycart' );
			} else if ( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-settings" && isset( $_GET['subpage'] ) && $_GET['subpage'] == "cart-importer" ) {
				return __( 'WP EasyCart Cart Importer', 'wp-easycart' );
			} else if ( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-settings" && isset( $_GET['subpage'] ) && $_GET['subpage'] == "country" ) {
				return __( 'WP EasyCart Countries & Regions', 'wp-easycart' );
			} else if ( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-settings" && isset( $_GET['subpage'] ) && $_GET['subpage'] == "states" ) {
				return __( 'WP EasyCart Countries & Regions', 'wp-easycart' );
			} else if ( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-settings" && isset( $_GET['subpage'] ) && $_GET['subpage'] == "perpage" ) {
				return __( 'WP EasyCart Per Page Settings', 'wp-easycart' );
			} else if ( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-settings" && isset( $_GET['subpage'] ) && $_GET['subpage'] == "pricepoint" ) {
				return __( 'WP EasyCart Price Point Settings', 'wp-easycart' );
			} else if ( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-settings" && isset( $_GET['subpage'] ) && $_GET['subpage'] == "logs" ) {
				return __( 'WP EasyCart Logs', 'wp-easycart' );

			} else {
				return $admin_title;
			}
		}

		public function load_scripts( ){

			if( current_user_can( 'manage_options' ) || current_user_can( 'wpec_manager' ) ){
				$https_link = "";
				if( class_exists( "WordPressHTTPS" ) ){
					$https_class = new WordPressHTTPS( );
					$https_link = $https_class->makeUrlHttps( admin_url( 'admin-ajax.php' ) );
				}else{
					$https_link = str_replace( "http://", "https://", admin_url( 'admin-ajax.php' ) );
				}

				wp_register_style( 'wp_easycart_deactivate_css', plugins_url( 'wp-easycart/admin/css/deactivate.css', EC_PLUGIN_DIRECTORY ), array( ), EC_CURRENT_VERSION );
				wp_enqueue_style( 'wp_easycart_deactivate_css' );

				wp_register_script( 'wp_easycart_deactivate_js', plugins_url( 'wp-easycart/admin/js/deactivate.js', EC_PLUGIN_DIRECTORY ), array( 'jquery' ), EC_CURRENT_VERSION );
				wp_enqueue_script( 'wp_easycart_deactivate_js' );

				$wp_easycart_deactivate_language = array(
					'quick-feedback'            => __( 'QUICK FEEDBACK', 'wp-easycart' ),
					'why-deactivating'          => __( 'If you have a moment, please let us know why you are deactiving.', 'wp-easycart' ),
					'plugin-didnt-work'         => __( 'The plugin didn\'t work.', 'wp-easycart' ),
					'better-plugin'             => __( 'I found a better plugin.', 'wp-easycart' ),
					'what-plugin'               => __( 'What\'s the plugin\'s name?', 'wp-easycart' ),
					'too-expensive'             => __( 'I need a Pro or Premium feature and the upgrade cost is too high.', 'wp-easycart' ),
					'missing-feature'           => __( 'Plugin is missing a feature that my project requires.', 'wp-easycart' ),
					'what-feature'              => __( 'What feature is missing?', 'wp-easycart' ),
					'temporary-deactivation'    => __( 'It\'s a temporary deactivation. I\'m just debugging an issue.', 'wp-easycart' ),
					'other'                     => __( 'Other.', 'wp-easycart' ),
					'how-improve'               => __( 'Please tell us the reason so we can improve.', 'wp-easycart' ),
					'anonymous'                 => __( 'Feedback is Anonymous', 'wp-easycart' ),
					'skip-deactivate'           => __( 'Skip & Deactivate', 'wp-easycart' ),
					'cancel'                    => __( 'Cancel', 'wp-easycart' ),
					'submit-deactivate'         => __( 'Submit & Deactivate', 'wp-easycart' ),
					'deactivate-nonce'          => esc_attr( wp_create_nonce( 'wp-easycart-deactivate-why' ) )
				);

				if ( is_ssl() ) {
					wp_localize_script( 'wp_easycart_deactivate_js', 'wpeasycart_admin_ajax_object', array(
						'ajax_url' => $https_link,
						'wp_easycart_deactivate_language' => $wp_easycart_deactivate_language,
						'ga4_id' => esc_attr( get_option( 'ec_option_google_ga4_property_id' ) ),
						'ga4_conv_id' => esc_attr( get_option( 'ec_option_google_adwords_tag_id' ) ),
						'is_tag_manager' => esc_attr( get_option( 'ec_option_google_ga4_tag_manager' ) ),
					) );
					wp_localize_script( 'wp_easycart_deactivate_js', 'ajax_object', array( 'ajax_url' => $https_link ) );
				} else {
					wp_localize_script( 'wp_easycart_deactivate_js', 'wpeasycart_admin_ajax_object', array(
						'ajax_url' => admin_url( 'admin-ajax.php' ),
						'wp_easycart_deactivate_language' => $wp_easycart_deactivate_language,
						'ga4_id' => esc_attr( get_option( 'ec_option_google_ga4_property_id' ) ),
						'ga4_conv_id' => esc_attr( get_option( 'ec_option_google_adwords_tag_id' ) ),
						'is_tag_manager' => esc_attr( get_option( 'ec_option_google_ga4_tag_manager' ) ),
					) );
					wp_localize_script( 'wp_easycart_deactivate_js', 'ajax_object', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
				}
			}

			if( ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_manager' ) ) && isset( $_GET['page'] ) && ( substr( sanitize_text_field( wp_unslash( $_GET['page'] ) ), 0, 11 ) == "wp-easycart" || sanitize_text_field( wp_unslash( $_GET['page'] ) ) == 'ec_adminv2' ) ){

				$https_link = "";
				if( class_exists( "WordPressHTTPS" ) ){
					$https_class = new WordPressHTTPS( );
					$https_link = $https_class->makeUrlHttps( admin_url( 'admin-ajax.php' ) );
				}else{
					$https_link = str_replace( "http://", "https://", admin_url( 'admin-ajax.php' ) );
				}

				wp_enqueue_media( );

				wp_enqueue_style( 'wp_easycart_select2_css', plugins_url( 'wp-easycart/admin/css/select2.min.css', EC_PLUGIN_DIRECTORY ), array( ), EC_CURRENT_VERSION );
				wp_enqueue_script( 'wp_easycart_select2_js', plugins_url( 'wp-easycart/admin/js/select2.min.js', EC_PLUGIN_DIRECTORY ), array( 'jquery' ), EC_CURRENT_VERSION );

				wp_register_script( 'wp_easycart_validation_js', plugins_url( 'wp-easycart/admin/js/validation.js', EC_PLUGIN_DIRECTORY ), array( 'jquery' ), EC_CURRENT_VERSION );
				wp_enqueue_script( 'wp_easycart_validation_js' );
				wp_localize_script( 'wp_easycart_validation_js', 'wp_easycart_admin_validation_language', array(
					'processing'                => __( 'PROCESSING...', 'wp-easycart' )
				) );

				wp_register_script( 'wp_easycart_admin_js', plugins_url( 'wp-easycart/admin/js/admin.js', EC_PLUGIN_DIRECTORY ), array( 'jquery', 'jquery-ui-sortable' ), EC_CURRENT_VERSION );
				wp_enqueue_script( 'wp_easycart_admin_js' );

				$wp_easycart_admin_language = array(
					'processing'            => __( 'PROCESSING...', 'wp-easycart' ),
					'choose-image'          => __( 'Choose Image', 'wp-easycart' ),
					'congrats'              => __( 'Congratulations!', 'wp-easycart' ),
					'congrats-note1'        => __( 'You\'ve just installed WP EasyCart! Start by entering the administrator email address where you would like to receive alerts for your shopping system:', 'wp-easycart' ),
					'get-notifications'     => __( 'Get Notifications', 'wp-easycart' ),
					'congrats-note2'        => __( 'Also join our WordPress eCommerce email list to receive eCommerce updates and WP EasyCart news.', 'wp-easycart' ),
					'select-file'           => __( 'Select File', 'wp-easycart' ),
					'use-file'              => __( 'Use File', 'wp-easycart' ),
					'select-image'          => __( 'Select Image', 'wp-easycart' ),
					'use-image'             => __( 'Use Image', 'wp-easycart' ),
					'select-import-file'    => __( 'Select Import File', 'wp-easycart' ),
				);

				if ( is_ssl( ) ) {
					wp_localize_script( 'wp_easycart_admin_js', 'wpeasycart_admin_ajax_object', array(
						'ajax_url' => $https_link,
						'wp_easycart_admin_language' => $wp_easycart_admin_language,
						'ga4_id' => esc_attr( get_option( 'ec_option_google_ga4_property_id' ) ),
						'ga4_conv_id' => esc_attr( get_option( 'ec_option_google_adwords_tag_id' ) ),
						'is_tag_manager' => esc_attr( get_option( 'ec_option_google_ga4_tag_manager' ) ),
					) );
					wp_localize_script( 'wp_easycart_admin_js', 'ajax_object', array( 'ajax_url' => $https_link ) );
				} else {
					wp_localize_script( 'wp_easycart_admin_js', 'wpeasycart_admin_ajax_object', array(
						'ajax_url' => admin_url( 'admin-ajax.php' ),
						'wp_easycart_admin_language' => $wp_easycart_admin_language,
						'ga4_id' => esc_attr( get_option( 'ec_option_google_ga4_property_id' ) ),
						'ga4_conv_id' => esc_attr( get_option( 'ec_option_google_adwords_tag_id' ) ),
						'is_tag_manager' => esc_attr( get_option( 'ec_option_google_ga4_tag_manager' ) ),
					) );
					wp_localize_script( 'wp_easycart_admin_js', 'ajax_object', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
				}

				if( isset( $_GET['page'] ) && $_GET['page'] == 'wp-easycart-dashboard' ){
					wp_register_script( 'wp_easycart_moment_js', plugins_url( 'wp-easycart/admin/js/moment.min.js', EC_PLUGIN_DIRECTORY ), array( 'jquery' ), EC_CURRENT_VERSION );
					wp_enqueue_script( 'wp_easycart_moment_js' );

					wp_register_script( 'wp_easycart_charts_js', plugins_url( 'wp-easycart/admin/js/Chart.js', EC_PLUGIN_DIRECTORY ), array( 'jquery' ), EC_CURRENT_VERSION );
					wp_enqueue_script( 'wp_easycart_charts_js' );

					wp_register_script( 'wp_easycart_daterange_js', plugins_url( 'wp-easycart/admin/js/daterangepicker.min.js', EC_PLUGIN_DIRECTORY ), array( 'jquery' ), EC_CURRENT_VERSION );
					wp_enqueue_script( 'wp_easycart_daterange_js' );

					wp_enqueue_style( 'wp_easycart_daterange_css', plugins_url( 'wp-easycart/admin/css/daterangepicker.css', EC_PLUGIN_DIRECTORY ), array( ), EC_CURRENT_VERSION );

				}

				wp_enqueue_script( 'wp-color-picker' );
				wp_enqueue_style( 'wp-color-picker' );

				$color_picker_strings = array(
					'clear'            => __( 'Clear', 'wp-easycart' ),
					'clearAriaLabel'   => __( 'Clear color', 'wp-easycart' ),
					'defaultString'    => __( 'Default', 'wp-easycart' ),
					'defaultAriaLabel' => __( 'Select default color', 'wp-easycart' ),
					'pick'             => __( 'Select Color', 'wp-easycart' ),
					'defaultLabel'     => __( 'Color value', 'wp-easycart' ),
				);
				wp_localize_script( 'wp-color-picker', 'wpColorPickerL10n', $color_picker_strings );

				wp_enqueue_script( 'jquery-ui-datepicker' );
				wp_register_style( 'wpeasycart-jquery-ui-css', plugins_url( 'wp-easycart/admin/css/smoothness-jquery-ui.css', EC_PLUGIN_DIRECTORY ), array( ), EC_CURRENT_VERSION );

				wp_enqueue_style( 'wpeasycart-jquery-ui-css' );
				wp_enqueue_style( 'wp-color-picker' );

				if( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-orders" && ( !isset( $_GET['subpage'] ) || $_GET['subpage'] == "orders" ) ){
					wp_enqueue_script('jquery-ui-datepicker');
					wp_enqueue_style('jquery-ui-datepicker');
					wp_register_script( 'wp_easycart_admin_orders_js', plugins_url( 'wp-easycart/admin/js/orders.js', EC_PLUGIN_DIRECTORY ), array( 'jquery', 'jquery-ui-datepicker' ), EC_CURRENT_VERSION );
					wp_enqueue_script( 'wp_easycart_admin_orders_js' );
					$this->wp_easycart_enqueue_orders_v2_script();
					if ( isset( $_GET['page'] ) && 'wp-easycart-orders' == $_GET['page'] && ( ! isset( $_GET['subpage'] ) || 'orders' == $_GET['subpage'] ) && isset( $_GET['order_id'] ) && isset( $_GET['ec_admin_form_action'] ) && 'edit' == $_GET['ec_admin_form_action'] ) {
						wp_enqueue_style( 'wp_easycart_admin_v2_css', plugins_url( '../css/admin-v2.css', __FILE__ ), array(), EC_CURRENT_VERSION );
						wp_enqueue_style( 'wp_easycart_admin_details_v2_css', plugins_url( '../css/admin-details-v2.css', __FILE__ ), array(), EC_CURRENT_VERSION );
						wp_enqueue_style( 'wp_easycart_admin_order_details_v2_css', plugins_url( '../css/admin-order-details-v2.css', __FILE__ ), array(), EC_CURRENT_VERSION );
						wp_enqueue_script( 'wp_easycart_admin_orders_details_v2_js', plugins_url( '../js/orders-details-v2.js', __FILE__ ), array( 'jquery' ), EC_CURRENT_VERSION, true );
					}
				} else if( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-products" && isset( $_GET['subpage'] ) && ( $_GET['subpage'] == "option" || $_GET['subpage'] == "optionitems" ) ){
					$this->enqueue_option_set_slideout_assets();
				} else if( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-products" && ( !isset( $_GET['subpage'] ) || $_GET['subpage'] == "products" ) ){
					$this->wp_easycart_enqueue_products_script( );
				} else if( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-users" && ( !isset( $_GET['subpage'] ) || $_GET['subpage'] == "accounts" || $_GET['subpage'] == "user-roles" ) ){
					wp_register_script( 'wp_easycart_admin_users_js', plugins_url( 'wp-easycart/admin/js/users.js', EC_PLUGIN_DIRECTORY ), array( 'jquery' ), EC_CURRENT_VERSION );
					wp_enqueue_script( 'wp_easycart_admin_users_js' );
					wp_register_script( 'wp_easycart_admin_users_v2', plugins_url( 'wp-easycart/admin/js/users-v2.js', EC_PLUGIN_DIRECTORY ), array( 'jquery' ), EC_CURRENT_VERSION );
					wp_enqueue_script( 'wp_easycart_admin_users_v2' );
					wp_register_style( 'wp_easycart_admin_users_v2_css', plugins_url( 'wp-easycart/admin/css/admin-users-v2.css', EC_PLUGIN_DIRECTORY ), array( 'wp_easycart_admin_v2_css' ), EC_CURRENT_VERSION );
					wp_enqueue_style( 'wp_easycart_admin_users_v2_css' );

					wp_localize_script( 'wp_easycart_admin_users_v2', 'ecv2_user_nonces', array(
						'inline_update' => wp_create_nonce( 'wp-easycart-ecv2-user-inline-update' ),
						'bulk_edit'     => wp_create_nonce( 'wp-easycart-ecv2-user-bulk-edit' ),
						'quick_edit'    => wp_create_nonce( 'wp-easycart-ecv2-user-quick-edit' ),
						/* 6.0.1: the safe-delete engine, for reassigning a deleted customer's orders. */
						'safe_delete'   => wp_create_nonce( class_exists( 'wp_easycart_admin_safe_delete' ) ? wp_easycart_admin_safe_delete::NONCE : 'wp-easycart-safe-delete' ),
					) );

					wp_localize_script( 'wp_easycart_admin_users_v2', 'ecv2_user_lang', array(
						'saved'                    => esc_html__( 'Saved successfully.', 'wp-easycart' ),
						'error'                    => esc_html__( 'An error occurred. Please try again.', 'wp-easycart' ),
						'undone'                   => esc_html__( 'Change reverted.', 'wp-easycart' ),
						'undo_available'           => esc_html__( 'Action completed. Undo available.', 'wp-easycart' ),
						'field_updated'            => esc_html__( 'Field updated', 'wp-easycart' ),
						'click_undo'               => esc_html__( 'Click Undo to revert.', 'wp-easycart' ),
						'applying_filters'         => esc_html__( 'Applying filters…', 'wp-easycart' ),
						'clear_filter'             => esc_html__( 'Clear this filter', 'wp-easycart' ),
						'customers'                => esc_html__( 'customers', 'wp-easycart' ),
						'no_changes'               => esc_html__( 'No changes selected.', 'wp-easycart' ),
						'no_name'                  => esc_html__( '(no name)', 'wp-easycart' ),
						'bulk_no_action'           => esc_html__( 'Please select a bulk action.', 'wp-easycart' ),
						'bulk_none_selected'       => esc_html__( 'Please select customers first.', 'wp-easycart' ),
						'bulk_max_exceeded'        => esc_html__( 'You can only process up to 100 customers at a time with this action. Please narrow your selection.', 'wp-easycart' ),
						'bulk_max_exceeded_500'    => esc_html__( 'You can only process up to 500 customers at a time.', 'wp-easycart' ),
						'bulk_confirm_delete_title' => esc_html__( 'Delete customers?', 'wp-easycart' ),
						'bulk_confirm_delete_one'  => esc_html__( 'Permanently delete this customer account? Their addresses are removed too. Orders are kept. This cannot be undone.', 'wp-easycart' ),
						/* translators: %d: number of customer accounts. */
						'bulk_confirm_delete_many' => esc_html__( 'Permanently delete %d customer accounts? Their addresses are removed too. Orders are kept. This cannot be undone.', 'wp-easycart' ),
						'bulk_confirm_reset_title' => esc_html__( 'Force password reset?', 'wp-easycart' ),
						/* translators: %d: number of customer accounts. */
						'bulk_confirm_reset'       => esc_html__( 'Invalidate the current password for %d customer(s) and email each a reset link?', 'wp-easycart' ),
						'row_confirm_reset_title'  => esc_html__( 'Send password reset?', 'wp-easycart' ),
						'row_confirm_reset'        => esc_html__( 'This invalidates the customer’s current password immediately and emails them a reset link. Continue?', 'wp-easycart' ),
						/* 6.0.1: the customer delete window ( users-v2.js, ecv2_user_safe_delete ). Inserted with .text(), so not escaped here. */
						'delete'                    => __( 'Delete', 'wp-easycart' ),
						'deleting'                  => __( 'Deleting…', 'wp-easycart' ),
						'cancel'                    => __( 'Cancel', 'wp-easycart' ),
						'close'                     => __( 'Close', 'wp-easycart' ),
						'change'                    => __( 'Change', 'wp-easycart' ),
						'view'                      => __( 'View', 'wp-easycart' ),
						'recommended'               => __( 'Recommended', 'wp-easycart' ),
						'delete_checking'           => __( 'Checking what this affects…', 'wp-easycart' ),
						/* translators: %s: customer name. */
						'delete_named'              => __( 'Delete “%s”?', 'wp-easycart' ),
						'target_search_placeholder' => __( 'Search customers by name, email or #', 'wp-easycart' ),
						'target_searching'          => __( 'Searching…', 'wp-easycart' ),
						/* translators: %s: what was typed in the search box. */
						'target_none'               => __( 'No other customer matches “%s”.', 'wp-easycart' ),
						'target_more'               => __( 'Showing the first 20. Type more to narrow it down.', 'wp-easycart' ),
						'target_min'                => __( 'Keep typing…', 'wp-easycart' ),
						'target_no_other'           => __( 'There is no other customer to move them to.', 'wp-easycart' ),
						'target_required'           => __( 'Choose the customer their orders should move to.', 'wp-easycart' ),
						/* translators: %d: number of days a deletion can be undone. */
						'undo_note'                 => __( 'You can undo this for %d days from Store Status › Recently deleted.', 'wp-easycart' ),
						'pro_gate'                 => wp_easycart_admin_pro_gate::evaluate( array( 'min_version' => '5.9.3' ) ),
					) );
					
					$wpec_is_user_details = isset( $_GET['ec_admin_form_action'] ) && in_array( $_GET['ec_admin_form_action'], array( 'edit', 'add-new' ), true );
					if ( ( ! isset( $_GET['subpage'] ) || 'accounts' == $_GET['subpage'] ) && $wpec_is_user_details ) {
						wp_register_style( 'wp_easycart_admin_details_v2_css', plugins_url( 'wp-easycart/admin/css/admin-details-v2.css', EC_PLUGIN_DIRECTORY ), array( ), EC_CURRENT_VERSION );
						wp_enqueue_style( 'wp_easycart_admin_details_v2_css' );
						wp_register_style( 'wp_easycart_admin_users_v2_css', plugins_url( 'wp-easycart/admin/css/admin-users-v2.css', EC_PLUGIN_DIRECTORY ), array( ), EC_CURRENT_VERSION );
						wp_enqueue_style( 'wp_easycart_admin_users_v2_css' );
						wp_register_style( 'wp_easycart_admin_users_details_v2_css', plugins_url( 'wp-easycart/admin/css/admin-users-details-v2.css', EC_PLUGIN_DIRECTORY ), array( 'wp_easycart_admin_details_v2_css' ), EC_CURRENT_VERSION );
						wp_enqueue_style( 'wp_easycart_admin_users_details_v2_css' );

						wp_register_script( 'wp_easycart_admin_users_details_v2', plugins_url( 'wp-easycart/admin/js/users-details-v2.js', EC_PLUGIN_DIRECTORY ), array( 'jquery' ), EC_CURRENT_VERSION );
						wp_enqueue_script( 'wp_easycart_admin_users_details_v2' );
						wp_localize_script( 'wp_easycart_admin_users_details_v2', 'ecudv2_lang', array(
							'first_required'  => esc_html__( 'Please enter a first name.', 'wp-easycart' ),
							'last_required'   => esc_html__( 'Please enter a last name.', 'wp-easycart' ),
							'email_required'  => esc_html__( 'Please enter a valid email address.', 'wp-easycart' ),
							'role_required'   => esc_html__( 'Please select a user access level.', 'wp-easycart' ),
							'password_length' => esc_html__( 'Please enter a password 8 characters or greater.', 'wp-easycart' ),
							'password_match'  => esc_html__( 'Passwords do not match.', 'wp-easycart' ),
							'nothing_to_save' => esc_html__( 'No changes to save.', 'wp-easycart' ),
							'save_failed'     => esc_html__( 'Save failed. Please try again.', 'wp-easycart' ),
							'saved'           => esc_html__( 'Customer saved.', 'wp-easycart' ),
							'save_first'      => esc_html__( 'Save the essentials first to unlock this section.', 'wp-easycart' ),
							'email_in_use'    => esc_html__( 'Another account already uses this email address.', 'wp-easycart' ),
							'copied'          => esc_html__( 'Billing address copied to shipping.', 'wp-easycart' ),
							'active'          => esc_html__( 'Active', 'wp-easycart' ),
							'activated'       => esc_html__( 'Activated', 'wp-easycart' ),
							'error'           => esc_html__( 'An error occurred. Please try again.', 'wp-easycart' ),
							'reset_title'     => esc_html__( 'Send password reset?', 'wp-easycart' ),
							'reset_body'      => esc_html__( 'This invalidates the customer’s current password immediately and emails them a reset link. Continue?', 'wp-easycart' ),
							'delete_title'    => esc_html__( 'Delete customer?', 'wp-easycart' ),
							'delete_body'     => esc_html__( 'Permanently delete this customer account? The profile and addresses are removed. Orders are kept. This cannot be undone.', 'wp-easycart' ),
							'created'         => esc_html__( 'Customer created. All sections are now unlocked.', 'wp-easycart' ),
							'created_no_password' => __( 'Customer created. No password was set, so send a password reset from Password & Security when they need to sign in.', 'wp-easycart' ),
						) );
					}
				} else if( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-settings" && ( !isset( $_GET['subpage'] ) || $_GET['subpage'] == "setup-wizard" ) ){
					$this->wp_easycart_enqueue_products_script( );
				}

				wp_register_style( 'wp_easycart_admin_css', plugins_url( 'wp-easycart/admin/css/admin.css', EC_PLUGIN_DIRECTORY ), array( ), EC_CURRENT_VERSION );
				wp_enqueue_style( 'wp_easycart_admin_css' );

				wp_register_style( 'wp_easycart_admin_v2_css', plugins_url( 'wp-easycart/admin/css/admin-v2.css', EC_PLUGIN_DIRECTORY ), array( ), EC_CURRENT_VERSION );
				wp_enqueue_style( 'wp_easycart_admin_v2_css' );

				wp_register_style( 'wp_easycart_upgrade_css', plugins_url( 'wp-easycart/admin/css/upgrade.css', EC_PLUGIN_DIRECTORY ), array( ), EC_CURRENT_VERSION );
				wp_enqueue_style( 'wp_easycart_upgrade_css' );
				
				if ( isset( $_GET['page'] ) && $_GET['page'] == "wp-easycart-products" && ( !isset( $_GET['subpage'] ) || $_GET['subpage'] == "products" ) ) {
					wp_register_style( 'wp_easycart_admin_details_v2_css', plugins_url( 'wp-easycart/admin/css/admin-details-v2.css', EC_PLUGIN_DIRECTORY ), array( ), EC_CURRENT_VERSION );
					wp_enqueue_style( 'wp_easycart_admin_details_v2_css' );

					wp_register_script( 'wp-easycart-products-details-v2', plugins_url( 'wp-easycart/admin/js/products-details-v2.js', EC_PLUGIN_DIRECTORY ), array( 'jquery' ), EC_CURRENT_VERSION );
					wp_enqueue_script( 'wp-easycart-products-details-v2' );
					wp_localize_script( 'wp-easycart-products-details-v2', 'wpeasycart_ecdv2_i18n', array(
						'save' => __( 'Save', 'wp-easycart' ),
						'saved' => __( 'Product saved.', 'wp-easycart' ),
						/* Free Media tab. */
						'searching'           => __( 'Searching…', 'wp-easycart' ),
						'no_products'         => __( 'No products match', 'wp-easycart' ),
						'loading_more'        => __( 'Loading more…', 'wp-easycart' ),
						'inactive'            => __( 'inactive', 'wp-easycart' ),
						'select_image'        => __( 'Select image', 'wp-easycart' ),
						'use_image'           => __( 'Use this image', 'wp-easycart' ),
						'choose_image'        => __( 'Choose', 'wp-easycart' ),
						'replace_image'       => __( 'Replace', 'wp-easycart' ),
						'one_extra_image'     => __( '1 extra image', 'wp-easycart' ),
						/* translators: %d is a count. */
						'n_extra_images'      => __( '%d extra images', 'wp-easycart' ),
						'option_image_sets'   => __( 'the per-option image sets', 'wp-easycart' ),
						/* translators: %d is a count. */
						'n_image_gallery'     => __( 'the %d-image gallery', 'wp-easycart' ),
						/* Free Options tab. */
						'loading'             => __( 'Loading…', 'wp-easycart' ),
						'opt_no_sets'         => __( 'No option sets — this product sells as a single item.', 'wp-easycart' ),
						/* translators: %1 = set name, %2 = count. */
						'opt_one_set'         => __( '%1 · %2 variations', 'wp-easycart' ),
						/* translators: %1 = set name, %2 = set name, %3 = count. */
						'opt_two_sets'        => __( '%1 × %2 · %3 variations', 'wp-easycart' ),
						'one_extra_set'       => __( '1 extra option set', 'wp-easycart' ),
						/* translators: %d is a count. */
						'n_extra_sets'        => __( '%d extra option sets', 'wp-easycart' ),
						'one_modifier'        => __( '1 modifier', 'wp-easycart' ),
						/* translators: %d is a count. */
						'n_modifiers'         => __( '%d modifiers', 'wp-easycart' ),
						/* translators: %d is a count. */
						'variant_stock'       => __( 'stock tracking on %d variations', 'wp-easycart' ),
						/* translators: %s describes the PRO option data that will be removed. */
						'lossy_options_confirm' => sprintf( __( 'Saving will remove %1$s from this product and keep only the first two option sets. This cannot be undone without %2$s. Save anyway?', 'wp-easycart' ), '%s', ( class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::plan_name() : __( 'Pro/Premium', 'wp-easycart' ) ) ),
						/* translators: %s describes the PRO media that will be removed. */
						'lossy_media_confirm' => sprintf( __( 'Saving will remove %1$s from this product and keep only the first two images. This cannot be undone without %2$s. Save anyway?', 'wp-easycart' ), '%s', ( class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::plan_name() : __( 'Pro/Premium', 'wp-easycart' ) ) ),
					) );

					wp_add_inline_script( 'wp-easycart-products-details-v2', 'window.wpeasycart_ecdv2_currency = ' . wp_json_encode( get_option( 'ec_option_currency_symbol', '$' ) ) . ';', 'before' );

					// Unified Create / Quick Edit product slideout.
					wp_register_style( 'wp_easycart_product_slideout_v2_css', plugins_url( 'wp-easycart/admin/css/product-slideout-v2.css', EC_PLUGIN_DIRECTORY ), array( 'wp_easycart_admin_v2_css' ), EC_CURRENT_VERSION );
					wp_enqueue_style( 'wp_easycart_product_slideout_v2_css' );
					wp_register_script( 'wp_easycart_product_slideout_v2_js', plugins_url( 'wp-easycart/admin/js/product-slideout-v2.js', EC_PLUGIN_DIRECTORY ), array( 'jquery', 'wp_easycart_admin_js' ), EC_CURRENT_VERSION, true );
					wp_enqueue_script( 'wp_easycart_product_slideout_v2_js' );
					$this->enqueue_option_set_slideout_assets();
					wp_localize_script( 'wp_easycart_product_slideout_v2_js', 'ecpsv2_vars', array(
						'currency'     => get_option( 'ec_option_currency_symbol', '$' ),
						'decimals'     => (int) $GLOBALS['currency']->get_decimal_length(),
						'is_pro'       => ( '' === apply_filters( 'wp_easycart_admin_lock_icon', 'locked' ) ),
						'variants_pro' => ( function_exists( 'ecv2_is_variant_tracking_enabled' ) && ecv2_is_variant_tracking_enabled() ),
						'default_tax'  => '0',
						'lang'         => array(
							'untitled'         => __( 'Untitled product', 'wp-easycart' ),
							'loading'          => __( 'Loading…', 'wp-easycart' ),
							'saved'            => __( 'Product saved.', 'wp-easycart' ),
							/* translators: %s is the product title. */
							'created_named'    => __( '“%s” created.', 'wp-easycart' ),
							'error'            => __( 'An error occurred. Please try again.', 'wp-easycart' ),
							'network'          => __( 'Network error. Please check your connection and try again.', 'wp-easycart' ),
							'confirm_discard'  => __( 'You have unsaved changes. Close without saving?', 'wp-easycart' ),
							'sku_blank'        => __( 'Leave blank to generate from the title.', 'wp-easycart' ),
							'sku_generated'    => __( 'Generated from the title — edit to override.', 'wp-easycart' ),
							'sku_custom'       => __( 'Custom SKU.', 'wp-easycart' ),
							'sku_edit_hint'    => __( 'Changing the SKU changes the product URL.', 'wp-easycart' ),
							'sku_required'     => __( 'A SKU is required.', 'wp-easycart' ),
							'sku_duplicate'    => __( 'That SKU is already in use — choose another.', 'wp-easycart' ),
							/* translators: %1 = list price, %2 = percent off. */
							'disc_shows'       => __( 'Storefront shows %1 struck through · %2% off', 'wp-easycart' ),
							'disc_not_higher'  => __( 'List price should be higher than the price.', 'wp-easycart' ),
							'disc_default'     => __( 'Shows a strike-through price and "% off" badge.', 'wp-easycart' ),
							/* translators: %s: plan name, Pro/Premium, Pro or Premium. */
							'type_pro_hint'    => sprintf( __( 'Downloads, subscriptions, gift cards and more are %s types.', 'wp-easycart' ), ( class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::plan_name() : __( 'Pro/Premium', 'wp-easycart' ) ) ),
							'type_stripe'      => __( 'Subscriptions and memberships bill through Stripe, Authorize.net or PayPal.', 'wp-easycart' ),
							'type_digital'     => __( 'This type is delivered without shipping.', 'wp-easycart' ),
							'opt_max'          => __( 'Maximum of 5 — use modifiers for more', 'wp-easycart' ),
							/* translators: %d is the free-edition option set limit. */
							'opt_max_free'     => sprintf( __( 'Free edition allows %1$s — %2$s allows 5', 'wp-easycart' ), '%d', ( class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::plan_name() : __( 'Pro/Premium', 'wp-easycart' ) ) ),
							'opt_one_left'     => __( '1 more option set available', 'wp-easycart' ),
							/* translators: %d is a count. */
							'opt_left'         => __( '%d more option sets available', 'wp-easycart' ),
							'no_options'       => __( 'No option sets or modifiers.', 'wp-easycart' ),
							'opt_pick'         => __( '+ Add an option set…', 'wp-easycart' ),
							'opt_none_left'    => __( 'All option sets added', 'wp-easycart' ),
							'drag'             => __( 'Drag to reorder', 'wp-easycart' ),
							'remove'           => __( 'Remove', 'wp-easycart' ),
							'manu_existing'    => __( 'Existing brand.', 'wp-easycart' ),
							/* translators: %s is the typed brand name. */
							'manu_new'         => __( '“%s” will be created as a new brand when you save.', 'wp-easycart' ),
							/* translators: %s is the typed brand name. */
							'manu_create'      => __( 'Create “%s”', 'wp-easycart' ),
							/* translators: %d is a count. */
							'modifiers_n'      => __( '%d modifiers', 'wp-easycart' ),
							'no_image'         => __( 'No main image yet', 'wp-easycart' ),
							'select_image'     => __( 'Select image', 'wp-easycart' ),
							'use_image'        => __( 'Use this image', 'wp-easycart' ),
							'chip_volume'      => __( '+ Volume tiers', 'wp-easycart' ),
							/* translators: %d is a count. */
							'chip_volume_n'    => __( '%d volume tiers', 'wp-easycart' ),
							'chip_b2b'         => __( '+ B2B pricing', 'wp-easycart' ),
							/* translators: %d is a count. */
							'chip_b2b_n'       => __( '%d B2B roles', 'wp-easycart' ),
							'chip_advanced'    => __( '+ Price label, range, login-to-view', 'wp-easycart' ),
							'chip_advanced_on' => __( 'Price label / range set', 'wp-easycart' ),
							/* translators: %d is a count. */
							'images_n'         => __( '%d images in gallery', 'wp-easycart' ),
							'images_none'      => __( 'Gallery is empty.', 'wp-easycart' ),
						),
					) );
				}

				wp_register_style( 'wp_easycart_shell_v2_css', plugins_url( 'wp-easycart/admin/css/shell-v2.css', EC_PLUGIN_DIRECTORY ), array( 'wp_easycart_admin_css' ), EC_CURRENT_VERSION );
				wp_enqueue_style( 'wp_easycart_shell_v2_css' );
				wp_register_style( 'wp_easycart_settings_v2_css', plugins_url( 'wp-easycart/admin/css/settings-v2.css', EC_PLUGIN_DIRECTORY ), array( 'wp_easycart_admin_css', 'wp_easycart_shell_v2_css' ), EC_CURRENT_VERSION );
				wp_enqueue_style( 'wp_easycart_settings_v2_css' );
				wp_register_script( 'wp_easycart_shell_v2_js', plugins_url( 'wp-easycart/admin/js/shell-v2.js', EC_PLUGIN_DIRECTORY ), array( 'jquery' ), EC_CURRENT_VERSION, true );
				/* 6.0.0: wording the shell adds to the page itself ( the editors' mobile section panel ). */
				wp_localize_script(
					'wp_easycart_shell_v2_js',
					'ecv2_shell_lang',
					array(
						'sections' => __( 'Sections', 'wp-easycart' ),
						'close'    => __( 'Close', 'wp-easycart' ),
					)
				);
				wp_enqueue_script( 'wp_easycart_shell_v2_js' );

				add_editor_style( );
				add_thickbox( );
				wp_enqueue_script('common');
				wp_enqueue_script( 'post' );
				wp_enqueue_script('jquery-color');
				wp_enqueue_script( 'editor' );
				wp_enqueue_script( 'media-upload' );
				wp_enqueue_script( 'tiny_mce' );
				wp_enqueue_script( 'editorremov' );
				wp_enqueue_script( 'editor-functions' );

			}

			wp_register_style( 'wp_easycart_editor_css', plugins_url( 'wp-easycart/admin/css/editor.css', EC_PLUGIN_DIRECTORY ), array( ), EC_CURRENT_VERSION );
			wp_enqueue_style( 'wp_easycart_editor_css' );

			wp_localize_script( 'wp_easycart_admin_js', 'wp_easycart_admin_vars', array(
				'ajaxURL' => admin_url( 'admin-ajax.php' ),
				'ec_option_newsletter_done' => ( ( get_option( 'ec_option_bcc_email_addresses' ) != 'youremail@url.com' || get_option( 'ec_option_newsletter_done' ) ) ? 1 : 0 ),
				'ec_option_currency'		=> get_option( 'ec_option_currency' )
			) );
		}

		public function wp_easycart_enqueue_products_script( ){
			wp_register_script( 'wp_easycart_admin_product_js', plugins_url( 'wp-easycart/admin/js/products.js', EC_PLUGIN_DIRECTORY ), array( 'jquery', 'jquery-ui-sortable' ), EC_CURRENT_VERSION );
			wp_enqueue_script( 'wp_easycart_admin_product_js' );
			wp_localize_script( 'wp_easycart_admin_product_js', 'wp_easycart_products_language', array(
				'processing'                => esc_html__( 'Processing Import File...  Please wait.', 'wp-easycart' ),
				'completed'                 => esc_html__( 'Completed!  You may refresh your screen.', 'wp-easycart' ),
				'subscription-note-1'       => esc_html__( 'Subscription with yearly interval has a max 1 year billing interval.', 'wp-easycart' ),
				'subscription-note-2'       => esc_html__( 'Subscription with monthly interval has a max 12 month billing interval.', 'wp-easycart' ),
				'catalog-note'              => esc_html__( 'Are you sure you want to enable catalog mode? Your customers will no longer be able to add products to the cart!', 'wp-easycart' ),
				'advanced-option-note1'     => esc_html__( 'You are currently using option item images AND option item quantity tracking. By switching to advanced options you will lose both of these features. Please confirm you wish to continue.', 'wp-easycart' ),
				'advanced-option-note2'     => esc_html__( 'You are currently using option item images. By switching to advanced options you will lose this feature. Please confirm you wish to continue.', 'wp-easycart' ),
				'advanced-option-note3'     => esc_html__( 'You are currently using option item quantity tracking. By switching to advanced options you will lose this feature. Please confirm you wish to continue.', 'wp-easycart' ),
				'advaced-options-note4'     => esc_html__( 'You cannot use option item images with advanced option sets. Please change to basic option sets to use this feature.', 'wp-easycart' ),
				'none-selected'             => esc_html__( 'None Selected', 'wp-easycart' ),
				'product-not-category'      => esc_html__( 'Product is Not in a Category', 'wp-easycart' ),
				'advanced-option-note5'     => esc_html__( 'You cannot use option item quantity tracking with advanced option sets. Please change to basic option sets to use this feature.', 'wp-easycart' ),
				'no-option-item-quantities' => esc_html__( 'No Option Item Quantities Setup', 'wp-easycart' ),
				'optionitem-tracking-note'  => esc_html( class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::requires_text( __( 'Option item quantity tracking', 'wp-easycart' ) ) : __( 'Option item quantity tracking is included with Pro and Premium licenses.', 'wp-easycart' ) ),
				'no-volume-pricing'         => esc_html__( 'No Volume Pricing Setup', 'wp-easycart' ),
				'no-b2b-pricing'            => esc_html__( 'No B2B Pricing Setup', 'wp-easycart' ),
				'total-views'               => esc_html__( 'Total Views', 'wp-easycart' ),
				'deactivate'                => esc_html__( 'Deactivate', 'wp-easycart' ),
				'activate'                  => esc_html__( 'Activate', 'wp-easycart' ),
				'edit-product'              => esc_html__( 'EDIT PRODUCT', 'wp-easycart' ),
			) );

			// V2 Product List JS.
			wp_register_script( 'wp_easycart_admin_product_v2_js', plugins_url( 'wp-easycart/admin/js/products-v2.js', EC_PLUGIN_DIRECTORY ), array( 'jquery', 'jquery-ui-sortable' ), EC_CURRENT_VERSION );
			wp_enqueue_script( 'wp_easycart_admin_product_v2_js' );

			// V2 Nonces
			wp_localize_script( 'wp_easycart_admin_product_v2_js', 'ecv2_nonces', array(
				'inline_update'   => wp_create_nonce( 'wp-easycart-ecv2-inline-update' ),
				'bulk_edit'       => wp_create_nonce( 'wp-easycart-ecv2-bulk-edit' ),
				'bulk_delete'     => wp_create_nonce( 'wp-easycart-ecv2-bulk-delete' ),
				'bulk_activate'   => wp_create_nonce( 'wp-easycart-ecv2-bulk-activate' ),
				'bulk_deactivate' => wp_create_nonce( 'wp-easycart-ecv2-bulk-deactivate' ),
				'bulk_export'     => wp_create_nonce( 'wp-easycart-ecv2-bulk-export' ),
				'get_sale_data'   => wp_create_nonce( 'wp-easycart-ecv2-get-sale-data' ),
				'remove_sale'     => wp_create_nonce( 'wp-easycart-ecv2-remove-sale' ),
				'category_search' => wp_create_nonce( 'wp-easycart-ecv2-category-search' ),
				'image_manager'   => wp_create_nonce( 'wp-easycart-ecv2-image-manager' ),
			) );

			// V2 Language strings.
			$ecv2_variant_gate = ecv2_get_variant_tracking_gate();
			$ecv2_pro_gate     = wp_easycart_admin_pro_gate::evaluate( array( 'min_version' => '5.8.15' ) );
			wp_localize_script( 'wp_easycart_admin_product_v2_js', 'ecv2_lang', array(
				'saved'              => esc_html__( 'Saved successfully.', 'wp-easycart' ),
				'error'              => esc_html__( 'An error occurred. Please try again.', 'wp-easycart' ),
				'activated'          => esc_html__( 'Product activated.', 'wp-easycart' ),
				'deactivated'        => esc_html__( 'Product deactivated.', 'wp-easycart' ),
				'click_undo'         => esc_html__( 'Click Undo to revert.', 'wp-easycart' ),
				'undo_available'     => esc_html__( 'Action completed. Undo available.', 'wp-easycart' ),
				'undone'             => esc_html__( 'Change reverted.', 'wp-easycart' ),
				'field_updated'      => esc_html__( 'Field updated', 'wp-easycart' ),
				'products'           => esc_html__( 'products', 'wp-easycart' ),
				'products_updated'   => esc_html__( 'products updated.', 'wp-easycart' ),
				'no_changes'         => esc_html__( 'No changes selected.', 'wp-easycart' ),
				'schedule_sale'      => esc_html__( 'Schedule Sale', 'wp-easycart' ),
				'activate_sale'      => esc_html__( 'Activate Sale', 'wp-easycart' ),
				'sale_active'        => esc_html__( 'This product currently has a sale price.', 'wp-easycart' ),
				'sale_saved'         => esc_html__( 'Sale price saved.', 'wp-easycart' ),
				'sale_removed'       => esc_html__( 'Sale removed.', 'wp-easycart' ),
				'enter_sale_price'   => esc_html__( 'Please enter a valid sale price.', 'wp-easycart' ),
				'confirm_remove_sale'=> esc_html__( 'Are you sure you want to remove this sale?', 'wp-easycart' ),
				'sale_price_higher'  => esc_html__( 'Warning: Sale price is not lower than the current price.', 'wp-easycart' ),
				'large_discount'     => esc_html__( 'Note: This is a discount of more than 50%.', 'wp-easycart' ),
				'scheduled'          => esc_html__( 'Scheduled', 'wp-easycart' ),
				'starts'             => esc_html__( 'Starts', 'wp-easycart' ),
				'active_until'       => esc_html__( 'Active until', 'wp-easycart' ),
				'starts_immediately' => esc_html__( 'Starts immediately when saved.', 'wp-easycart' ),
				'stock_saved'        => esc_html__( 'Stock quantity updated.', 'wp-easycart' ),
				'tracking_changed'   => esc_html__( 'Tracking type changed.', 'wp-easycart' ),
				'confirm_tracking_title' => esc_html__( 'Change Tracking Type', 'wp-easycart' ),
				'confirm_unlimited'  => esc_html__( 'Switch to Unlimited? Stock will no longer be tracked for this product.', 'wp-easycart' ),
				'confirm_basic'      => esc_html__( 'Switch to Basic Tracking? Stock will be tracked as a single quantity value.', 'wp-easycart' ),
				'confirm_option'     => esc_html__( 'Switch to Option/Variant Tracking? Stock will be tracked per product variation. Ensure option sets are configured.', 'wp-easycart' ),
				'manage_variants'    => esc_html__( 'Manage Variants', 'wp-easycart' ),
				'price_saved'        => esc_html__( 'Price saved.', 'wp-easycart' ),
				'list_price_not_higher' => esc_html__( 'List price should be higher than price for a sale display.', 'wp-easycart' ),
				'save_label'         => esc_html__( 'Save', 'wp-easycart' ),
				'loading'            => esc_html__( 'Loading...', 'wp-easycart' ),
				'all'                => esc_html__( 'All', 'wp-easycart' ),
				'in_stock'           => esc_html__( 'in stock', 'wp-easycart' ),
				'left'               => esc_html__( 'left', 'wp-easycart' ),
				'out_of_stock'       => esc_html__( 'Out of Stock', 'wp-easycart' ),
				'unlimited_label'    => esc_html__( 'Unlimited', 'wp-easycart' ),
				'cat_search_placeholder' => esc_html__( 'Search categories...', 'wp-easycart' ),
				'cat_no_results'     => esc_html__( 'No categories found.', 'wp-easycart' ),
				'cat_type_to_search' => esc_html__( 'Type to search categories', 'wp-easycart' ),
				'cat_assigned'       => esc_html__( 'Assigned Categories', 'wp-easycart' ),
				'cat_none_assigned'  => esc_html__( 'No categories assigned.', 'wp-easycart' ),
				'cat_added'          => esc_html__( 'Category added.', 'wp-easycart' ),
				'cat_removed'        => esc_html__( 'Category removed.', 'wp-easycart' ),
				'cat_already_assigned' => esc_html__( 'Already assigned', 'wp-easycart' ),
				'bulk_none_selected' => esc_html__( 'Please select products first.', 'wp-easycart' ),
				'bulk_no_action'     => esc_html__( 'Please select a bulk action.', 'wp-easycart' ),
				'bulk_max_exceeded'  => esc_html__( 'You can only process up to 500 products at a time. Please narrow your selection and try again.', 'wp-easycart' ),
				'bulk_confirm_delete_title' => esc_html__( 'Delete products?', 'wp-easycart' ),
				/* translators: %d is the number of products to delete. */
				'bulk_confirm_delete_one'   => esc_html__( 'Permanently delete this product? This action cannot be undone.', 'wp-easycart' ),
				/* translators: %d is the number of products to delete. */
				'bulk_confirm_delete_many'  => esc_html__( 'Permanently delete %d products? This action cannot be undone.', 'wp-easycart' ),
				'bulk_confirm_deactivate_title' => esc_html__( 'Deactivate products?', 'wp-easycart' ),
				/* translators: %d is the number of products to deactivate. */
				'bulk_confirm_deactivate'       => esc_html__( 'Deactivate %d products? They will no longer be visible in your store.', 'wp-easycart' ),
				'bulk_apply_to'      => esc_html__( 'Apply to %d selected', 'wp-easycart' ),
				'bulk_working'       => esc_html__( 'Working…', 'wp-easycart' ),
				'bulk_network_error' => esc_html__( 'Network error. Please check your connection and try again.', 'wp-easycart' ),
				'bulk_export_ready_title'   => esc_html__( 'Your export is ready', 'wp-easycart' ),
				/* translators: %d is the number of products in the export. */
				'bulk_export_ready_message' => esc_html__( '%d products ready for download. This link is single-use and expires in 10 minutes.', 'wp-easycart' ),
				'bulk_export_download'      => esc_html__( 'Download CSV', 'wp-easycart' ),
				'bulk_export_dismiss'       => esc_html__( 'Dismiss', 'wp-easycart' ),
				'bulk_export_downloading'   => esc_html__( 'Starting download…', 'wp-easycart' ),
				/* translators: %1$d = items on current page, %2$d = total matching. */
				'select_all_matching_prompt' => esc_html__( 'All %1$d products on this page are selected.', 'wp-easycart' ),
				/* translators: %d is the total number of matching products. */
				'select_all_matching_link'   => esc_html__( 'Select all %d matching products', 'wp-easycart' ),
				'clear_selection'            => esc_html__( 'Clear selection', 'wp-easycart' ),
				'processing'         => esc_html__( 'Processing...', 'wp-easycart' ),
				'apply'              => esc_html__( 'Apply', 'wp-easycart' ),
				'applying_filters'   => esc_html__( 'Applying filters…', 'wp-easycart' ),
				'img_manage_title'   => esc_html__( 'Manage Images', 'wp-easycart' ),
				'img_images'         => esc_html__( 'images', 'wp-easycart' ),
				'img_remove'         => esc_html__( 'Remove', 'wp-easycart' ),
				'img_pro_required'   => esc_html( class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::included_text( 'pro' ) : __( 'Included with Pro and Premium licenses.', 'wp-easycart' ) ),
				'img_select_images'  => esc_html__( 'Select Images', 'wp-easycart' ),
				'img_use_images'     => esc_html__( 'Use Selected Images', 'wp-easycart' ),
				'img_select_thumbnail' => esc_html__( 'Select Thumbnail', 'wp-easycart' ),
				'img_use_image'      => esc_html__( 'Use Image', 'wp-easycart' ),
				'img_enter_image_url' => esc_html__( 'Enter full image URL', 'wp-easycart' ),
				'img_enter_video_url' => esc_html__( 'Enter video URL and thumbnail', 'wp-easycart' ),
				'img_enter_youtube_url' => esc_html__( 'Enter YouTube embed URL and thumbnail', 'wp-easycart' ),
				'img_enter_vimeo_url' => esc_html__( 'Enter Vimeo embed URL and thumbnail', 'wp-easycart' ),
				'img_save_images'    => esc_html__( 'Save Images', 'wp-easycart' ),
				'img_saved'          => esc_html__( 'Images saved successfully.', 'wp-easycart' ),
				'img_type_basic_desc'     => esc_html__( 'Use a single set of images for this product.', 'wp-easycart' ),
				'img_type_variant_desc'   => esc_html__( 'Use different images for each product option (basic option set).', 'wp-easycart' ),
				'img_type_modifier_desc'  => esc_html__( 'Use different images for each modifier (advanced option).', 'wp-easycart' ),
				'img_no_basic_options'    => esc_html__( 'This product has no basic options. Add a product option first.', 'wp-easycart' ),
				'img_no_advanced_options' => esc_html__( 'This product has no modifiers (advanced options). Add a modifier first.', 'wp-easycart' ),
				'tier_singular'      => esc_html__( 'tier', 'wp-easycart' ),
				'tier_plural'        => esc_html__( 'tiers', 'wp-easycart' ),
				'b2b_singular'       => esc_html__( 'B2B role', 'wp-easycart' ),
				'b2b_plural'         => esc_html__( 'B2B roles', 'wp-easycart' ),
				'flag_login'                => esc_html__( 'Login', 'wp-easycart' ),
				'flag_login_action_title'   => esc_html__( 'Login required to view price — click to manage B2B role pricing', 'wp-easycart' ),
				'flag_label_default'        => esc_html__( 'Custom label', 'wp-easycart' ),
				'flag_label_action_title'   => esc_html__( 'Custom price label is enabled — click to edit', 'wp-easycart' ),
				'flag_range_action_title'   => esc_html__( 'Displayed as a price range on the storefront — click to edit', 'wp-easycart' ),
				'flag_tier_action_title'    => esc_html__( 'Volume pricing — click to manage', 'wp-easycart' ),
				'flag_b2b_action_title'     => esc_html__( 'B2B role pricing — click to manage', 'wp-easycart' ),
				'advanced_pip_aria'  => esc_html__( 'Advanced pricing options are configured for this product', 'wp-easycart' ),
				'advanced_pip_title' => esc_html__( 'Advanced pricing configured', 'wp-easycart' ),
				'base_label'         => esc_html__( 'Base:', 'wp-easycart' ),
				'square_locked'      => esc_html__( 'Quick edit disabled - this product is synced with Square. Edit the full product by clicking the title, or make changes in your Square dashboard.', 'wp-easycart' ),
				'variant_tracking_enabled' => ( 'enabled' === $ecv2_variant_gate['state'] ) ? 1 : 0,
				'variant_gate'             => $ecv2_variant_gate,
				'pro_gate'                 => $ecv2_pro_gate,
				'pro_required_variants'    => esc_html( class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::requires_text( __( 'Variant tracking', 'wp-easycart' ) ) : __( 'Variant tracking is included with Pro and Premium licenses.', 'wp-easycart' ) ),
				'bulk_square_all'       => esc_html__( 'All selected products are synced with Square. Price and stock changes are managed by Square and cannot be edited here.', 'wp-easycart' ),
				'bulk_square_some'      => esc_html__( '%d of %s selected products are synced with Square. Price and stock changes will be skipped for those products.', 'wp-easycart' ),
				'square_managed'        => esc_html__( 'Managed by Square', 'wp-easycart' ),
				'square_skipped_suffix' => esc_html__( '(%d Square-synced product(s) skipped — managed by Square)', 'wp-easycart' ),
				'image_missing'      => esc_html__( 'Image could not be loaded', 'wp-easycart' ),
			) );
		}

		public function wp_easycart_enqueue_orders_v2_script() {
			// V2 Order List JS + CSS.
			wp_register_script( 'wp_easycart_admin_orders_v2_js', plugins_url( 'wp-easycart/admin/js/orders-v2.js', EC_PLUGIN_DIRECTORY ), array( 'jquery' ), EC_CURRENT_VERSION );
			wp_enqueue_script( 'wp_easycart_admin_orders_v2_js' );

			wp_register_style( 'wp_easycart_admin_orders_v2_css', plugins_url( 'wp-easycart/admin/css/orders-v2.css', EC_PLUGIN_DIRECTORY ), array(), EC_CURRENT_VERSION );
			wp_enqueue_style( 'wp_easycart_admin_orders_v2_css' );

			// Select2 for the filter drawer selects (same assets the products page uses).
			wp_enqueue_script( 'wp-easycart-select2' );
			wp_enqueue_style( 'wp-easycart-select2' );

			// Language strings + PRO gate payload for wpec_gate popups.
			$ecv2_order_pro_gate = ecv2_get_order_pro_gate();
			wp_localize_script( 'wp_easycart_admin_orders_v2_js', 'ecv2_lang', array(
				'saved'                 => esc_html__( 'Saved successfully.', 'wp-easycart' ),
				'error'                 => esc_html__( 'An error occurred. Please try again.', 'wp-easycart' ),
				'undone'                => esc_html__( 'Change reverted.', 'wp-easycart' ),
				'click_undo'            => esc_html__( 'Click Undo to revert.', 'wp-easycart' ),
				'undo_available'        => esc_html__( 'Action completed. Undo available.', 'wp-easycart' ),
				'copied'                => esc_html__( 'Copied to clipboard.', 'wp-easycart' ),
				'order_status_updated'  => esc_html__( 'Order status updated.', 'wp-easycart' ),
				'select_bulk_action'    => esc_html__( 'Please choose a bulk action.', 'wp-easycart' ),
				'select_orders'         => esc_html__( 'Please select at least one order.', 'wp-easycart' ),
				'delete_orders_title'   => esc_html__( 'Delete Orders', 'wp-easycart' ),
				'delete_orders_confirm' => esc_html__( 'Permanently delete the selected orders? This cannot be undone.', 'wp-easycart' ),
				'order_pro_gate'        => $ecv2_order_pro_gate,
				'pro_gate'              => $ecv2_order_pro_gate,
				/* 6.0.0: the fulfillment cell follows status changes without a reload ( orders-v2.js / orders-v2-pro.js ). */
				'fulfilled_status_ids'  => method_exists( 'wp_easycart_admin_order_table', 'fulfilled_status_ids' ) ? wp_easycart_admin_order_table::fulfilled_status_ids() : array( 2, 18 ),
				'fulfilled_chip'        => method_exists( 'wp_easycart_admin_order_table', 'fulfilled_chip_html' ) ? wp_easycart_admin_order_table::fulfilled_chip_html() : '',
				'fulfill_button'        => method_exists( 'wp_easycart_admin_order_table', 'fulfill_button_html' ) ? wp_easycart_admin_order_table::fulfill_button_html( $ecv2_order_pro_gate ) : '',
				/* 6.0.1: "Picked up" ( status Order Picked Up ) and "Mark picked up" ( a Free Local Pickup row, data-pickup="1" ). */
				'fulfilled_chip_pickup' => method_exists( 'wp_easycart_admin_order_table', 'pickup_fulfill_supported' ) ? wp_easycart_admin_order_table::fulfilled_chip_html( 'pickup' ) : '',
				'fulfill_button_pickup' => ( method_exists( 'wp_easycart_admin_order_table', 'pickup_fulfill_supported' ) && wp_easycart_admin_order_table::pickup_fulfill_supported() ) ? wp_easycart_admin_order_table::fulfill_button_html( $ecv2_order_pro_gate, 'pickup' ) : '',
			) );
		}

		/**
		 * True on any EasyCart admin shell page. Notices for these pages
		 * render inside the shell (wp_easycart_admin_messages) instead of
		 * the wp-admin top-of-page admin_notices position, which sits
		 * above/outside the shell chrome in the V2 design.
		 */
		private function is_easycart_admin_page( ){
			if ( ! isset( $_GET['page'] ) ) {
				return false;
			}
			$ec_admin_page = sanitize_text_field( wp_unslash( $_GET['page'] ) );
			return ( 0 === strpos( $ec_admin_page, 'wp-easycart' ) || 'ec_adminv2' == $ec_admin_page );
		}

		/** Fired at priority 5 on wp_easycart_admin_messages (see shell.php). */
		public function print_core_notices_in_shell( ){
			$this->wp_easycart_pro_check( true );
			$this->square_check( true );
			$this->database_check( true );
			$this->database_install_errors_check( true );
			$this->download_recovery_check( true );
		}

		public function wp_easycart_pro_check( $in_shell = false ){
			if ( ! $in_shell && $this->is_easycart_admin_page( ) ) {
				return; // rendered in-shell via print_core_notices_in_shell instead
			}
			if ( ! function_exists( 'is_plugin_active' ) ) {
				include_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$pro_plugin_base = 'wp-easycart-pro/wp-easycart-admin-pro.php';
			$pro_plugin_file = EC_PLUGIN_DIRECTORY . '-pro/wp-easycart-admin-pro.php';
			if ( class_exists( 'wp_easycart_admin_pro_gate' ) && wp_easycart_admin_pro_gate::is_outdated() ) {
				return; // wp_easycart_admin_compat_lock shows the update / deactivate page and its own notice instead.
			}
			if( file_exists( $pro_plugin_file ) && !is_plugin_active( $pro_plugin_base ) ) {
				if ( $in_shell ) {
					echo '<div id="ec_pro_activate_message" class="wpec-pro-notice wpec-pro-notice--brand">';
					echo '<span class="wpec-pro-notice-icon dashicons dashicons-admin-plugins"></span>';
					echo '<div class="wpec-pro-notice-body"><strong>' . esc_html__( 'WP EasyCart PRO is installed but not activated.', 'wp-easycart' ) . '</strong> ' . esc_html__( 'It runs both Pro and Premium licenses. Activate it to unlock your features.', 'wp-easycart' ) . '</div>';
					echo '<a class="wpec-pro-notice-button" href="' . esc_url( $this->get_pro_activation_link( ) ) . '">' . esc_html__( 'Activate WP EasyCart PRO', 'wp-easycart' ) . '</a>';
					echo '</div>';
				} else {
					echo '<div class="updated">';
					echo '<p>' . esc_attr__( 'WP EasyCart PRO is installed but not activated. Please', 'wp-easycart' ) . ' <a href="' . esc_url( $this->get_pro_activation_link( ) ) . '">' . esc_attr__( 'click here to activate your WP EasyCart PRO plugin', 'wp-easycart' ) . '</a>.</p>';
					echo '</div>';
				}
			}
		}

		public function square_check( $in_shell = false ){
			if ( ! $in_shell && $this->is_easycart_admin_page( ) ) {
				return; // rendered in-shell via print_core_notices_in_shell instead
			}
			$square_message = '';
			if( ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_settings' ) ) && get_option( 'ec_option_payment_process_method' ) == 'square' && !get_option( 'ec_option_square_is_sandbox' ) && get_option( 'ec_option_square_access_token' ) == '' ){
				$square_message = esc_attr__( 'Your Square connection is no longer active and you cannot receive payments. Please visit the Settings -> Payments and reconnect to begin processing payments again.', 'wp-easycart' );
			}else if( ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_settings' ) ) && get_option( 'ec_option_payment_process_method' ) == 'square' && !get_option( 'ec_option_square_is_sandbox' ) && get_option( 'ec_option_square_access_token' ) != '' && strtotime( '+15 day', strtotime( get_option( 'ec_option_square_token_expires' ) ) ) < time( ) ){
				$square_message = esc_attr__( 'Your Square connection has expired, please visit the Settings -> Payments and renew the connection to begin processing payments again.', 'wp-easycart' );
			}
			if ( '' != $square_message ) {
				if ( $in_shell ) {
					echo '<div id="ec_square_check_message" class="wpec-pro-notice wpec-pro-notice--danger">';
					echo '<span class="wpec-pro-notice-icon dashicons dashicons-warning"></span>';
					echo '<div class="wpec-pro-notice-body">' . $square_message . '</div>';
					echo '<a class="wpec-pro-notice-button" href="admin.php?page=wp-easycart-settings&subpage=payment">' . esc_html__( 'Open Payment Settings', 'wp-easycart' ) . '</a>';
					echo '</div>';
				} else {
					echo '<div class="error notice"><p>' . $square_message . '</p></div>';
				}
			}
		}

		public function database_check( $in_shell = false ){
			if ( ! $in_shell && $this->is_easycart_admin_page( ) ) {
				return; // rendered in-shell via print_core_notices_in_shell instead
			}
			if( !$this->database_check_current( ) ){
				$db_manager = new ec_db_manager( );
				$errors = $db_manager->verify_db( );
				if( count( $errors ) ){
					if ( $in_shell ) {
						echo '<div class="wpec-pro-notice wpec-pro-notice--danger wpec-pro-notice--stacked">';
						echo '<span class="wpec-pro-notice-icon dashicons dashicons-database"></span>';
						echo '<div class="wpec-pro-notice-body">';
					} else {
						echo '<div class="error notice">';
					}
						echo '<p>' . esc_attr__( 'We have found problems with your WP EasyCart database structure.', 'wp-easycart' ) . ' <a href="admin.php?page=wp-easycart-status&subpage=store-status&ec_admin_form_action=repair-database">' . esc_attr__( 'Click to Repair!', 'wp-easycart' ) . '</a> ' . esc_attr__( 'If you would like to dismiss this notice', 'wp-easycart' ) . ', <a href="admin.php?page=wp-easycart-status&subpage=store-status&ec_admin_form_action=dismiss-database-error">' . esc_attr__( 'please click here', 'wp-easycart' ) . '</a>.</p>';
						echo '<p><span id="wpeasycart_database_errors_min">' . esc_attr__( 'For Complete Details', 'wp-easycart' ) . ', <a href="#" onclick="jQuery( \'#wpeasycart_database_errors\' ).show( ); jQuery( \'#wpeasycart_database_errors_min\' ).hide( ); return false;">' . esc_attr__( 'Click Here', 'wp-easycart' ) . '</a></span><ul id="wpeasycart_database_errors" style="display:none;">';
						foreach( $errors as $error ){
							echo '<li>' . esc_attr( $error['error'] ) . '</li>';
						}
						echo '</ul></p>';
					if ( $in_shell ) {
						echo '</div></div>';
					} else {
						echo '</div>';
					}
				}

			}
		}

		public function database_check_current( ){
			if( !get_option( 'ec_option_db_version_verified' ) || version_compare( str_replace( '_', '.', EC_CURRENT_VERSION ), get_option( 'ec_option_db_version_verified' ), '<' ) ){
				return false;
			}
			return true;
		}

		public function database_install_errors_check( $in_shell = false ) {
			if ( ! $in_shell && $this->is_easycart_admin_page( ) ) {
				return; // rendered in-shell via print_core_notices_in_shell instead
			}
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_diagnostics' ) ) {
				return;
			}
			if ( ! class_exists( 'ec_db_manager' ) ) {
				return;
			}
			$db_manager = new ec_db_manager();
			if ( ! method_exists( $db_manager, 'get_install_errors' ) ) {
				return;
			}
			$install_errors = $db_manager->get_install_errors();
			if ( ! is_array( $install_errors ) || ! count( $install_errors ) ) {
				return;
			}
			$retry_url = wp_nonce_url( admin_url( 'admin.php?page=wp-easycart-status&subpage=store-status&ec_admin_form_action=retry-database-install' ), 'wp-easycart-action-retry-database-install', 'wp_easycart_nonce' );
			if ( $in_shell ) {
				echo '<div class="wpec-pro-notice wpec-pro-notice--danger wpec-pro-notice--stacked">';
				echo '<span class="wpec-pro-notice-icon dashicons dashicons-database"></span>';
				echo '<div class="wpec-pro-notice-body">';
			} else {
				echo '<div class="error notice">';
			}
			echo '<p>' . esc_html__( 'The WP EasyCart database install could not complete. The following tables failed to create:', 'wp-easycart' ) . '</p>';
			echo '<ul style="list-style:disc;padding-left:20px;">';
			foreach ( $install_errors as $install_error ) {
				echo '<li>' . esc_html( $install_error ) . '</li>';
			}
			echo '</ul>';
			echo '<p><a href="' . esc_url( $retry_url ) . '">' . esc_html__( 'Retry the database install now', 'wp-easycart' ) . '</a> ' . esc_html__( 'or contact your host with the errors above (they usually indicate a missing CREATE privilege or an unsupported storage engine).', 'wp-easycart' ) . '</p>';
			/*
			 * 6.0.1: a step that cannot succeed on this database used to be retried for ever, and because
			 * the version is only written once every step lands, the store sat on "upgrade in progress"
			 * with no way out. The message in square brackets above is the statement that failed, so it can
			 * be run by hand; this link records the step as handled so the rest of the upgrade can finish.
			 */
			if ( class_exists( 'ec_db_manager' ) && method_exists( 'ec_db_manager', 'get_failing_steps' ) && ec_db_manager::get_failing_steps() ) {
				$skip_url = wp_nonce_url( admin_url( 'admin.php?page=wp-easycart-status&subpage=store-status&ec_admin_form_action=skip-database-step' ), 'wp-easycart-action-skip-database-step', 'wp_easycart_nonce' );
				echo '<p>' . esc_html__( 'If retrying keeps failing, the statement shown in brackets can be run by hand in phpMyAdmin or by your host. Once it has been, retry above.', 'wp-easycart' ) . '</p>';
				echo '<p><a href="' . esc_url( $skip_url ) . '">' . esc_html__( 'Continue without this change', 'wp-easycart' ) . '</a> ' . esc_html__( 'finishes the rest of the update and stops the retries. Store Status will keep reporting the change as outstanding.', 'wp-easycart' ) . '</p>';
			}			if ( $in_shell ) {
				echo '</div></div>';
			} else {
				echo '</div>';
			}
		}

		public function download_recovery_check( $in_shell = false ) {
			if ( ! $in_shell && $this->is_easycart_admin_page( ) ) {
				return; // rendered in-shell via print_core_notices_in_shell instead
			}
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) {
				return;
			}
			if ( get_option( 'ec_option_dismiss_download_recovery_notice' ) ) {
				return;
			}
			$count = get_transient( 'ec_download_recovery_count' );
			if ( false === $count ) {
				global $wpdb;
				$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM ec_product WHERE is_download = 1 AND ( download_file_name IS NULL OR download_file_name = '' )" );
				set_transient( 'ec_download_recovery_count', $count, DAY_IN_SECONDS );
			}
			if ( (int) $count <= 0 ) {
				return;
			}
			$dismiss_url = wp_nonce_url( admin_url( 'admin.php?page=wp-easycart-status&subpage=store-status&ec_admin_form_action=dismiss-download-recovery' ), 'wp-easycart-action-dismiss-download-recovery', 'wp_easycart_nonce' );
			if ( $in_shell ) {
				echo '<div class="wpec-pro-notice wpec-pro-notice--danger wpec-pro-notice--stacked">';
				echo '<span class="wpec-pro-notice-icon dashicons dashicons-download"></span>';
				echo '<div class="wpec-pro-notice-body">';
			} else {
				echo '<div class="error notice">';
			}
			/* translators: %d: number of downloadable products missing a file name */
			echo '<p>' . esc_html( sprintf( _n( '%d downloadable product has no download file attached. If your site was repaired after a database error, the file names may need to be re-saved on each product.', '%d downloadable products have no download file attached. If your site was repaired after a database error, the file names may need to be re-saved on each product.', (int) $count, 'wp-easycart' ), (int) $count ) ) . '</p>';
			echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=wp-easycart-products&subpage=products&health_filter=download_missing' ) ) . '">' . esc_html__( 'Show the products to fix', 'wp-easycart' ) . '</a> | <a href="' . esc_url( $dismiss_url ) . '">' . esc_html__( 'Dismiss this notice', 'wp-easycart' ) . '</a></p>';
			if ( $in_shell ) {
				echo '</div></div>';
			} else {
				echo '</div>';
			}
		}

		public function get_pro_activation_link( ){ 
			$pro_plugin_file = EC_PLUGIN_DIRECTORY . '-pro/wp-easycart-admin-pro.php';
			if( strpos( $pro_plugin_file, '/' ) ){
				$pro_plugin_file = str_replace( '/', '%2F', $pro_plugin_file );
			}
			$activate_url = sprintf( admin_url( 'plugins.php?action=activate&plugin=%s&plugin_status=all&paged=1&s' ), $pro_plugin_file ); 
			$_REQUEST['plugin'] = $pro_plugin_file;
			$activate_url = wp_nonce_url( $activate_url, 'activate-plugin_' . $pro_plugin_file );
			return $activate_url;
		}

		/**
		 * Messages from the success / warning / error filters ( driven by ?success= / ?error= after a redirect ),
		 * shown as V2 notices at the top of the shell content. A message may be a plain string or an array:
		 * array( 'text' => '', 'tone' => 'success|warning|error|info', 'detail' => '', 'actions' => array( array( 'label', 'url', 'target' ) ) ).
		 */
		public function print_admin_message() {
			$groups = array(
				'success' => apply_filters( 'wp_easycart_admin_success_messages', array() ),
				'warning' => apply_filters( 'wp_easycart_admin_warning_messages', array() ),
				'error'   => apply_filters( 'wp_easycart_admin_error_messages', array() ),
			);
			foreach ( $groups as $tone => $messages ) {
				foreach ( (array) $messages as $message ) {
					if ( is_array( $message ) ) {
						$text = isset( $message['text'] ) ? (string) $message['text'] : '';
						$args = $message;
						$item_tone = isset( $message['tone'] ) ? $message['tone'] : $tone;
					} else {
						$text = (string) $message;
						$args = array();
						$item_tone = $tone;
					}
					if ( '' !== trim( $text ) ) {
						echo self::notice_html( $item_tone, $text, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- notice_html() escapes every part.
					}
				}
			}

			if ( ! get_option( 'ec_option_allow_tracking' ) ) {
				$allow_url = admin_url( 'admin.php?page=wp-easycart-settings&subpage=miscellaneous&ec_admin_form_action=allow-usage-tracking&wp_easycart_nonce=' . wp_create_nonce( 'wp-easycart-enable-usage-tracking' ) );
				$deny_url  = admin_url( 'admin.php?page=wp-easycart-settings&subpage=miscellaneous&ec_admin_form_action=deny-usage-tracking&wp_easycart_nonce=' . wp_create_nonce( 'wp-easycart-enable-usage-tracking' ) );
				echo self::notice_html( 'info', __( 'Help improve WP EasyCart by sharing basic usage data.', 'wp-easycart' ), array( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- notice_html() escapes every part.
					'class'       => 'ec_admin_allow_tracking',
					'dismissible' => false,
					'actions'     => array(
						array( 'label' => __( 'Allow', 'wp-easycart' ), 'url' => $allow_url, 'primary' => true, 'onclick' => "wp_easycart_allow_tracking( '" . wp_create_nonce( 'wp-easycart-tracking' ) . "' ); jQuery( this ).closest( '.ecv2-flash' ).fadeOut(); return false;" ),
						array( 'label' => __( 'No thanks', 'wp-easycart' ), 'url' => $deny_url, 'onclick' => "wp_easycart_deny_tracking( '" . wp_create_nonce( 'wp-easycart-disable-usage-tracking' ) . "' ); jQuery( this ).closest( '.ecv2-flash' ).fadeOut(); return false;" ),
						array( 'label' => __( 'What is shared', 'wp-easycart' ), 'url' => 'https://www.wpeasycart.com/terms-and-conditions/', 'target' => '_blank', 'link' => true ),
					),
				) );
			}
		}

		/**
		 * One V2 admin notice. Used by the shell messages above and by PRO screens that print their own
		 * ( through wp_easycart_admin_notice_html() ).
		 *
		 * @since 6.0.0
		 * @param string $tone    success | warning | error | info.
		 * @param string $text    Main message ( plain text ).
		 * @param array  $args    detail ( plain text ), actions ( label, url, target, primary, link, onclick ), dismissible ( bool ), class.
		 * @return string
		 */
		public static function notice_html( $tone, $text, $args = array() ) {
			$tone = in_array( $tone, array( 'success', 'warning', 'error', 'info' ), true ) ? $tone : 'info';
			$args = wp_parse_args( $args, array( 'detail' => '', 'actions' => array(), 'dismissible' => true, 'class' => '' ) );
			$icons = array(
				'success' => '<path d="M20 6 9 17l-5-5"/>',
				'warning' => '<path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>',
				'error'   => '<circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6"/><path d="M9 9l6 6"/>',
				'info'    => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
			);
			$html  = '<div class="ecv2-flash is-' . esc_attr( $tone ) . ( '' !== $args['class'] ? ' ' . esc_attr( $args['class'] ) : '' ) . '" role="' . ( 'error' === $tone ? 'alert' : 'status' ) . '">';
			$html .= '<span class="ecv2-flash-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $icons[ $tone ] . '</svg></span>';
			$html .= '<div class="ecv2-flash-body"><span class="ecv2-flash-text">' . esc_html( $text ) . '</span>';
			if ( '' !== (string) $args['detail'] ) {
				$html .= '<span class="ecv2-flash-detail">' . esc_html( $args['detail'] ) . '</span>';
			}
			$html .= '</div>';
			if ( ! empty( $args['actions'] ) ) {
				$html .= '<div class="ecv2-flash-actions">';
				foreach ( (array) $args['actions'] as $action ) {
					if ( empty( $action['label'] ) || empty( $action['url'] ) ) {
						continue;
					}
					$class = ! empty( $action['link'] ) ? 'ecv2-flash-link' : ( ! empty( $action['primary'] ) ? 'ecv2-btn ecv2-btn-sm ecv2-btn-primary' : 'ecv2-btn ecv2-btn-sm' );
					$html .= '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $action['url'] ) . '"';
					if ( ! empty( $action['target'] ) ) {
						$html .= ' target="' . esc_attr( $action['target'] ) . '" rel="noopener noreferrer"';
					}
					if ( ! empty( $action['onclick'] ) ) {
						$html .= ' onclick="' . esc_attr( $action['onclick'] ) . '"';
					}
					$html .= '>' . esc_html( $action['label'] ) . '</a>';
				}
				$html .= '</div>';
			}
			if ( $args['dismissible'] ) {
				$html .= '<button type="button" class="ecv2-flash-x" aria-label="' . esc_attr__( 'Dismiss', 'wp-easycart' ) . '"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></button>';
			}
			$html .= '</div>';
			return $html;
		}

		/**
		 * Renewal urgency notice for paid licenses inside 30 days of expiry ( and after ).
		 * Dismissible for 24 hours while there is more than a week left; not dismissible in
		 * the final week or once lapsed. Never shown for trials ( PRO handles those ).
		 */
		public function load_renewal_notice() {
			if ( ! class_exists( 'wp_easycart_admin_upsell' ) ) {
				return;
			}
			$r = wp_easycart_admin_upsell::renewal();
			if ( ! $r || 'ok' === $r['tone'] ) {
				return;
			}
			$dismissible = ( 'soon' === $r['tone'] );
			if ( $dismissible && (int) get_user_meta( get_current_user_id(), 'wpec_renewal_notice_snooze', true ) > time() ) {
				return;
			}
			$stakes = wp_easycart_admin_upsell::renewal_stakes( $r );
			if ( 'lapsed' === $r['tone'] ) {
				$title = sprintf( __( 'Your %1$s license lapsed on %2$s.', 'wp-easycart' ), $r['edition'], $r['end_fmt'] );
				$lead  = $r['premium']
					? __( 'Premium features are locked, your extensions are not syncing, and the 2% gateway fee is back. Renew to reopen everything — nothing has been deleted.', 'wp-easycart' )
					: __( 'Pro features are locked and the 2% gateway fee is back. Renew to reopen everything — nothing has been deleted.', 'wp-easycart' );
			} else if ( 'critical' === $r['tone'] ) {
				$title = sprintf( _n( 'Your %2$s license ends tomorrow ( %3$s ).', 'Your %2$s license ends in %1$d days ( %3$s ).', $r['days'], 'wp-easycart' ), $r['days'], $r['edition'], $r['end_fmt'] );
				$lead  = __( 'When it does:', 'wp-easycart' );
			} else {
				$title = sprintf( __( 'Support & updates end in %1$d days ( %2$s ).', 'wp-easycart' ), $r['days'], $r['end_fmt'] );
				$lead  = __( 'Renewing now adds a full year on top — nothing is lost by renewing early. If it lapses:', 'wp-easycart' );
			}
			echo '<div class="ecv2-renewal-notice is-' . esc_attr( $r['tone'] ) . ( 'lapsed' !== $r['tone'] ? ' has-stakes' : '' ) . '" id="ecv2_renewal_notice">';
			echo '<span class="ecv2-renewal-icon"><span class="dashicons ' . ( 'lapsed' === $r['tone'] ? 'dashicons-lock' : 'dashicons-clock' ) . '"></span></span>';
			echo '<div class="ecv2-renewal-body">';
			echo '<strong>' . esc_html( $title ) . '</strong> <span>' . esc_html( $lead ) . '</span>';
			if ( 'lapsed' !== $r['tone'] ) {
				echo '<ul class="ecv2-renewal-stakes">';
				foreach ( array_slice( $stakes, 0, 4 ) as $st ) {
					echo '<li>' . esc_html( $st ) . '</li>';
				}
				echo '</ul>';
			}
			echo '</div>';
			echo '<div class="ecv2-renewal-actions">';
			echo '<a class="ecv2-btn ecv2-btn-primary" href="' . esc_url( $r['url'] ) . '" target="_blank">' . esc_html( 'lapsed' === $r['tone'] ? __( 'Renew & reopen', 'wp-easycart' ) : __( 'Renew now', 'wp-easycart' ) ) . '</a>';
			echo '<a class="ecv2-btn ecv2-btn-ghost ecv2-btn-sm" href="' . esc_url( self_admin_url( 'admin.php?page=wp-easycart-registration' ) ) . '">' . esc_html__( 'License details', 'wp-easycart' ) . '</a>';
			if ( 'lapsed' === $r['tone'] ) {
				$deact = wp_easycart_admin_upsell::pro_deactivate_url();
				if ( '' !== $deact ) {
					echo '<a class="ecv2-renewal-free" href="' . esc_url( $deact ) . '" onclick="return window.confirm( ' . esc_attr( wp_json_encode( ( $r['premium'] ? __( 'Switch to the free edition? This deactivates the WP EasyCart PRO plugin, which runs your Premium license. Your products, orders and settings are kept; Premium features stop until you renew and activate it again.', 'wp-easycart' ) : __( 'Switch to the free edition? This deactivates the WP EasyCart PRO plugin, which runs your Pro license. Your products, orders and settings are kept; Pro features stop until you renew and activate it again.', 'wp-easycart' ) ) ) ) . ' );">' . esc_html__( 'or switch to the free edition', 'wp-easycart' ) . '</a>';
				}
			}
			if ( $dismissible ) {
				echo '<button type="button" class="ecv2-renewal-snooze" onclick="ecv2_renewal_snooze( this ); return false;" data-nonce="' . esc_attr( wp_create_nonce( 'wpec-renewal-snooze' ) ) . '" title="' . esc_attr__( 'Hide for a day', 'wp-easycart' ) . '" aria-label="' . esc_attr__( 'Hide for a day', 'wp-easycart' ) . '"><span class="dashicons dashicons-no-alt"></span></button>';
			}
			echo '</div>';
			echo '</div>';
			echo '<script>function ecv2_renewal_snooze( b ) { var n = document.getElementById( "ecv2_renewal_notice" ); if ( n ) { n.style.display = "none"; } jQuery.post( ajaxurl, { action: "ec_admin_ajax_ecv2_dismiss_renewal", nonce: b.getAttribute( "data-nonce" ) } ); }</script>';
		}

		public function ajax_dismiss_renewal_notice() {
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'wpec-renewal-snooze' ) || ! current_user_can( 'wpec_diagnostics' ) && ! current_user_can( 'manage_options' ) ) {
				die();
			}
			$which = ( isset( $_POST['which'] ) && 'gate' === $_POST['which'] ) ? 'wpec_renewal_gate_snooze' : 'wpec_renewal_notice_snooze';
			update_user_meta( get_current_user_id(), $which, time() + DAY_IN_SECONDS );
			die();
		}

		public function load_upsell_image( ){
			if ( isset( $_GET['page'] ) && $_GET['page'] == 'wp-easycart-settings' && !isset( $_GET['subpage'] ) ) {
				return;
			}

			if ( isset( $_GET['subpage'] ) && $_GET['subpage'] == 'setup-wizard' ) {
				return;
			}

			if ( isset( $_GET['page'] ) && $_GET['page'] == 'wp-easycart-dashboard' ) {
				return;
			}

			if ( isset( $_GET['page'] ) && $_GET['page'] == 'wp-easycart-license-status' ) {
				return;
			}

			/* Modern upsell strip ( replaces the banner image ). Same trigger, same pages. */
			$ecv2_banner_stats = class_exists( 'wp_easycart_admin_upsell' ) ? wp_easycart_admin_upsell::stats() : array();
			$ecv2_banner_text  = __( 'Free edition. Card payments carry a 2% fee; a Pro or Premium license removes it and unlocks the locked panels you see around the admin.', 'wp-easycart' );
			if ( ! empty( $ecv2_banner_stats['orders_30d'] ) && $ecv2_banner_stats['orders_30d'] >= 3 && ! empty( $ecv2_banner_stats['avg_order'] ) ) {
				$ecv2_banner_text = sprintf(
					/* translators: %1$s = orders last 30 days, %2$s = estimated monthly fee. */
					__( 'Free edition. On your last 30 days ( %1$s orders ) the 2%% gateway fee came to about %2$s — a Pro or Premium license removes it and unlocks the locked panels around the admin.', 'wp-easycart' ),
					number_format_i18n( $ecv2_banner_stats['orders_30d'] ),
					wp_easycart_admin_upsell::money( $ecv2_banner_stats['orders_30d'] * $ecv2_banner_stats['avg_order'] * 0.02 )
				);
			}
			echo '<div class="ecv2-upsell-banner">';
			echo '<span class="ecv2-upsell-banner-pill">' . esc_html( ( class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::plan_name() : __( 'Pro/Premium', 'wp-easycart' ) ) ) . '</span>';
			echo '<span class="ecv2-upsell-banner-text">' . esc_html( $ecv2_banner_text ) . '</span>';
			if ( class_exists( 'wp_easycart_admin_upsell' ) ) {
				echo '<button type="button" class="ecv2-btn ecv2-btn-sm" onclick="ecdv2_upsell( { context: \'default\' } ); return false;">' . esc_html__( "See what's included", 'wp-easycart' ) . '</button>';
			}
			echo '<a class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" href="' . esc_url( self_admin_url( 'admin.php?page=wp-easycart-registration&ec_trial=start' ) ) . '">' . esc_html__( 'Try Pro free', 'wp-easycart' ) . '</a>';
			echo '</div>';
		}

		public function load_upsell_popup( ){
			echo '<div id="ec_admin_upsell_popup"><div class="ec_admin_upsell_popup_close"><a href="#" onclick="hide_pro_required( ); return false;"><div class="dashicons-before dashicons-dismiss"></div></a></div><div class="ec_admin_upsell_popup_inner"><div class="ec_admin_upsell_popup_content">';
			$this->show_upgrade( );
			echo '<div style="clear:both;"></div></div></div></div>';
			echo '<script>jQuery( document.getElementById( \'ec_admin_upsell_popup\' ) ).appendTo( document.body );</script>';
		}
	
		/**
		 * Settings › Flex-Fees ( list and editor ) without a licensed PRO: the locked page — header, feature
		 * strip and a sample-data preview under a soft lock. It used to print the legacy editable table of
		 * the store's real ec_fee rows above the upsell.
		 *
		 * PRO removes this callback once licensed; the check below also covers PRO builds that only remove
		 * the old callbacks, so a licensed store never sees the lock under its fee list.
		 *
		 * @since 6.0.0
		 */
		public function show_fee_locked_page() {
			if ( class_exists( 'wp_easycart_admin_fee_pro' ) && function_exists( 'wp_easycart_admin_license' ) && wp_easycart_admin_license()->is_licensed() ) {
				return;
			}
			if ( ! class_exists( 'wp_easycart_admin_upsell' ) || ( function_exists( 'wp_easycart_admin_license' ) && wp_easycart_admin_license()->license_expired ) ) {
				/* Lapsed license or trial: PRO swaps in its "paused until you renew" body, as on every other PRO page. */
				$this->show_upgrade();
				return;
			}
			wp_easycart_admin_upsell::print_locked_page( 'fees' );
		}

		/**
		 * Back-compat alias for show_fee_locked_page().
		 *
		 * @deprecated 6.0.0 Printed the store's real fee rows to unlicensed stores. Use show_fee_locked_page().
		 */
		public function show_fee_list_example() {
			$this->show_fee_locked_page();
		}

		public function show_upgrade( ){
			include( apply_filters( 'wp_easycart_admin_upgrade_file', EC_PLUGIN_DIRECTORY . '/admin/template/upgrade/upgrade-screen.php' ) );
		}

		/**
		 * Assets for the V2 Create Option Set slideout. Safe to call more than once.
		 */
		public function enqueue_option_set_slideout_assets() {
			if ( wp_script_is( 'wp_easycart_option_set_slideout_v2_js', 'enqueued' ) ) {
				return;
			}
			wp_register_style( 'wp_easycart_option_set_slideout_v2_css', plugins_url( 'wp-easycart/admin/css/option-set-slideout-v2.css', EC_PLUGIN_DIRECTORY ), array( 'wp_easycart_admin_v2_css' ), EC_CURRENT_VERSION );
			wp_enqueue_style( 'wp_easycart_option_set_slideout_v2_css' );
			wp_register_script( 'wp_easycart_option_set_slideout_v2_js', plugins_url( 'wp-easycart/admin/js/option-set-slideout-v2.js', EC_PLUGIN_DIRECTORY ), array( 'jquery', 'jquery-ui-sortable', 'wp_easycart_admin_js' ), EC_CURRENT_VERSION, true );
			wp_enqueue_script( 'wp_easycart_option_set_slideout_v2_js' );
			wp_localize_script( 'wp_easycart_option_set_slideout_v2_js', 'ecosv2_vars', array(
				'currency'            => get_option( 'ec_option_currency_symbol', '$' ),
				'decimals'            => (int) $GLOBALS['currency']->get_decimal_length(),
				'weight_unit'         => get_option( 'ec_option_weight_unit', 'lb' ),
				'reload_after_create' => ( isset( $_GET['subpage'] ) && in_array( $_GET['subpage'], array( 'option', 'optionitems' ), true ) ),
				/* Option Sets screens: after "Create option set" go straight to the full editor for the new set. */
				'edit_after_create'   => ( isset( $_GET['subpage'] ) && in_array( $_GET['subpage'], array( 'option', 'optionitems' ), true ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput -- read-only page check against a fixed list.
				'lang'                => array(
					'title'           => __( 'Create an option set', 'wp-easycart' ),
					/* translators: %s is the option set name. */
					'title_named'     => __( 'Create “%s”', 'wp-easycart' ),
					/* translators: %s is the option set name, lower-cased. */
					'label_tpl'       => __( 'Choose a %s', 'wp-easycart' ),
					'label_generated' => __( 'Generated from the name — edit to override.', 'wp-easycart' ),
					'label_custom'    => __( 'Custom label.', 'wp-easycart' ),
					/* translators: %1 = product title, %2 = slot number. */
					'sub_product'     => __( 'It will be added to <strong>%1</strong> as slot %2. Reusable on any other product too.', 'wp-easycart' ),
					'this_product'    => __( 'this product', 'wp-easycart' ),
					'choice_name'     => __( 'Choice name', 'wp-easycart' ),
					'one_choice'      => __( '1 choice', 'wp-easycart' ),
					/* translators: %d is a count. */
					'n_choices'       => __( '%d choices', 'wp-easycart' ),
					'need_two'        => __( 'add at least 2', 'wp-easycart' ),
					'dropdown'        => __( 'Dropdown', 'wp-easycart' ),
					'swatches'        => __( 'Swatches', 'wp-easycart' ),
					'preview_empty'   => __( 'Add a choice to see it here.', 'wp-easycart' ),
					'drag'            => __( 'Drag to reorder', 'wp-easycart' ),
					'remove'          => __( 'Remove', 'wp-easycart' ),
					'pick_photo'      => __( 'Choose a photo', 'wp-easycart' ),
					'use_photo'       => __( 'Use this photo', 'wp-easycart' ),
					'swatch_title'    => __( 'Set a color or image', 'wp-easycart' ),
					'color'           => __( 'Color', 'wp-easycart' ),
					'image'           => __( 'Image', 'wp-easycart' ),
					'two_tone'        => __( 'Two-tone', 'wp-easycart' ),
					'image_hint'      => __( 'Use a photo or pattern instead of a flat color.', 'wp-easycart' ),
					'choose_image'    => __( 'Choose image…', 'wp-easycart' ),
					/* translators: %s: plan name, Pro/Premium, Pro or Premium. */
					'image_pro'       => sprintf( __( 'Photo swatches are a %s feature. Color swatches are included with every store.', 'wp-easycart' ), ( class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::plan_name() : __( 'Pro/Premium', 'wp-easycart' ) ) ),
					/* translators: %s: plan name, Pro/Premium, Pro or Premium. */
					'unlock'          => sprintf( __( 'Unlock with %s', 'wp-easycart' ), ( class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::plan_name() : __( 'Pro/Premium', 'wp-easycart' ) ) ),
					'remove_swatch'   => __( 'Remove', 'wp-easycart' ),
					'cancel'          => __( 'Cancel', 'wp-easycart' ),
					'apply'           => __( 'Apply', 'wp-easycart' ),
					'no_media'        => __( 'The media library is not available on this page.', 'wp-easycart' ),
					/* translators: %s is the option set name. */
					'created'         => __( '“%s” created.', 'wp-easycart' ),
					'error'           => __( 'An error occurred. Please try again.', 'wp-easycart' ),
					'network'         => __( 'Network error. Please check your connection and try again.', 'wp-easycart' ),
				),
			) );
		}

		public function load_new_slideout( $slide ){
			if ( $slide == 'product' ) {
				include( EC_PLUGIN_DIRECTORY . '/admin/template/products/products/product-slideout-v2.php' );

			} else if( $slide == 'manufacturer' ) {
				include( EC_PLUGIN_DIRECTORY . '/admin/template/products/manufacturers/new-manufacturer-slideout.php' );

			}else if( $slide == 'optionset' || $slide == 'advanced-optionset' ){
				static $option_slideout_loaded = false;
				if ( ! $option_slideout_loaded ) {
					$option_slideout_loaded = true;
					include( EC_PLUGIN_DIRECTORY . '/admin/template/products/options/option-set-slideout-v2.php' );
				}

			} else if( $slide == 'order' ) {
				include( EC_PLUGIN_DIRECTORY . '/admin/template/orders/orders/order-quick-edit-slideout.php' );
				include( EC_PLUGIN_DIRECTORY . '/admin/template/orders/orders/order-duplicate-slideout.php' );
			}
		}

		public function filter_option_type( ){
			return 'upgrade_required';
		}

		public function redirect( $page, $subpage, $args ){
			$url = $this->get_redirect_url( $page, $subpage, $args );
			wp_redirect( $url );
			exit( );
		}

		private function get_redirect_url( $page, $subpage, $args ){
			$url = "admin.php?page=" . $page . "&subpage=" . $subpage;
			foreach( $args as $key => $value ){
				$url .= "&" . $key . '=' . $value;
			}
			return $url;
		}

		public function start_pro_trial() {
			$current_user = wp_get_current_user( );
			$name = $current_user->user_firstname . " " . $current_user->user_lastname;
			$email = $current_user->user_email;

			if ( ! file_exists( EC_PLUGIN_DIRECTORY . '-pro/wp-easycart-admin-pro.php' ) ) {
				$this->install_pro_plugin(  );
			}

			if ( ! file_exists( EC_PLUGIN_DIRECTORY . '-pro/wp-easycart-admin-pro.php' ) ) {
				/* translators: %s: support email address. */
				echo esc_attr( sprintf( __( 'Error installing the WP EasyCart PRO plugin. Please try again or contact %s for assistance.', 'wp-easycart' ), 'support@wpeasycart.com' ) );
				die( );
			}

			if ( ! is_plugin_active( 'wp-easycart-pro/wp-easycart-admin-pro.php' ) ) {
				activate_plugin( EC_PLUGIN_DIRECTORY . '-pro/wp-easycart-admin-pro.php', NULL, 0, 1 );
			}

			if ( ! is_plugin_active( 'wp-easycart-pro/wp-easycart-admin-pro.php' ) ) {
				echo esc_attr( sprintf( __( 'Error activating WP EasyCart PRO, please visit your plugins page and click activate or contact %s for assistance.', 'wp-easycart' ), 'support@wpeasycart.com' ) );
				die( );
			}

			if ( ! class_exists( 'ec_license_manager' ) ) {
				include( EC_PLUGIN_DIRECTORY . '-pro/license/ec_license_manager.php' );
			}

			$license_key = $this->create_trial_license( $name, $email );
			if ( ! $license_key ) {
				echo esc_attr( sprintf( __( 'Error creating trial key. Something may be wrong with our server, please contact %s for assistance.', 'wp-easycart' ), 'support@wpeasycart.com' ) ) . '<br>';
				die( );
			} else if ( $license_key == "key_exists" ) {
				// Should load from
			} else {
				$license_manager = new ec_license_manager( );
				$license_manager->ec_activate_license( $name, $email, $license_key );
			}
		}

		private function install_pro_plugin( $is_trial = 1 ){
			echo '<html>';
				echo '<head>';
					echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
					echo '<title>' . esc_attr__( 'Install WP EasyCart PRO', 'wp-easycart' ) . '</title>';
					echo '<style type="text/css">';
						echo 'html{ height:100%; margin:0; padding:0; }';
						echo 'body{ display:block; height:100%; margin:0; padding:0; color:#444; font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif; font-size:13px; line-height:1.4em; min-width:600px; }';
						echo 'img{ display:block; margin:0 auto; }';
						echo '.box-container{ -moz-box-shadow:0px 0px 100px rgba(0, 0, 0, 0.5); -webkit-box-shadow:0px 0px 100px rgba(0, 0, 0, 0.5); box-shadow:0px 0px 100px rgba(0, 0, 0, 0.5); -moz-border-radius:10px; -webkit-border-radius:10px; border-radius:10px; position:fixed; top:15%; left:50%; width:550px; margin:0 0 0 -225px; background:#FFF; overflow:auto; padding:0px; -webkit-box-sizing:border-box; -moz-box-sizing:border-box; box-sizing:border-box; z-index:99999; max-height:420px; }';
						echo '.box-container > div{ padding:25px; border-color:#FFFFFF; border-width:4px; border-style:solid; border-radius:0px; }';
						echo 'h1{ font-weight:normal; margin:10px 0 25px; text-align:center; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif; font-size:26px; }';
						echo 'a{ background:#79af40; margin:15px auto 0; display:block; text-align:center; color:#FFF; padding:12px 20px; border-radius:5px; text-decoration:none; font-size:16px; font-weight:normal; }';
						echo 'a:hover{ background:#92c845; }';
					echo '</style>';
				echo '</head>';
				echo '<body style="background:#f7f4e8;" bgcolor="#f7f4e8">';
					echo '<div class="box-container" style="max-height:550px;"><div>';
					echo '<img src="' . esc_attr( plugins_url( "wp-easycart/admin/images/easycart-logo-1-11-2018.png", EC_PLUGIN_DIRECTORY ) ) . '" alt="WP EasyCart" title="WP EasyCart" />';

			$url = "https://connect.wpeasycart.com/downloads/professional-admin/wp-easycart-pro.zip";
			$method = '';

			if ( false === ( $creds = request_filesystem_credentials( esc_url_raw( $url ), $method, false, false, array() ) ) ) {
				return false;
			}

			if ( ! WP_Filesystem( $creds ) ) {
				request_filesystem_credentials( esc_url_raw( $url ), $method, true, false, array() );
				return false;
			}

			if ( ! class_exists( 'Plugin_Upgrader', false ) ) {
				include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			}
			include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
			include_once ABSPATH . 'wp-admin/includes/file.php';

			$skin_args = array(
				'type'   => 'upload',
				'title'  => esc_attr__( 'Installing WP EasyCart PRO', 'wp-easycart' ),
				'url'    => esc_url_raw( $url ),
				'nonce'  => 'install-plugin_wp-easycart-pro',
				'plugin' => 'wp-easycart-pro/wp-easycart-admin-pro.php',
			);

			add_filter( 'install_plugin_complete_actions', array( $this, 'remove_actions_install', 3 ) );
			$skin = new Plugin_Installer_Skin( $skin_args );
			$upgrader = new Plugin_Upgrader( $skin );
			try {
				do_action( 'upgrader_pre_install', $upgrader, array() );
				$installer_result = $upgrader->install( $url );
				do_action( 'upgrader_post_install', $upgrader, array() );
			} catch ( Throwable $t ) {
				$installer_result = false;
			}

			if ( is_wp_error( $installer_result ) ) {
				echo '<a href="https://docs.wpeasycart.com/docs/installation-guide/installing-the-plugin/#manualinstall" target="_blank">' . esc_attr__( 'AN ERROR HAS OCCURED. CLICK HERE FOR HELP.', 'wp-easycart' ) . '</a>';
				echo '<a href="admin.php?page=wp-easycart-registration&ec_trial=start">' . esc_attr__( 'RETURN TO WP EASYCART', 'wp-easycart' ) . '</a>';
			} else if( $is_trial ){
				echo '<a href="admin.php?page=wp-easycart-registration&ec_trial=start">' . esc_attr__( 'CLICK HERE TO COMPLETE INSTALLATION', 'wp-easycart' ) . '</a>';
			}else{
				echo '<a href="admin.php?page=wp-easycart-registration&ec_install=pro">' . esc_attr__( 'CLICK HERE TO COMPLETE INSTALLATION', 'wp-easycart' ) . '</a>';
			}
			echo '<a href="https://docs.wpeasycart.com/docs/installation-guide/installing-the-plugin/#manualinstall" target="_blank">' . esc_attr__( 'Did something go wrong? If so click here for manual install instructions.', 'wp-easycart' ) . '</a>';

			echo '</div></div></body></html>';

			die( );
		}

		private function remove_actions_install( $actions, $api, $file ){
			return array( '' );
		}

		private function create_trial_license( $name, $email ){
			$action_url = 'https://licensing.wpeasycart.com/trial/start/start.php';

			$url = site_url( );
			$url = str_replace( 'http://', '', $url );
			$url = str_replace( 'https://', '', $url );
			$url = str_replace( 'www.', '', $url );

			$action_url .= '?ec_action=start_trial';
			$action_url .= '&site_url=' . esc_url_raw( $url );
			$action_url .= '&customername=' . sanitize_text_field( wp_unslash( $name ) );
			$action_url .= '&customeremail=' . sanitize_email( wp_unslash( $email ) );

			$response = wp_remote_get( $action_url, array( 'timeout' => 30, 'sslverify' => false ) );
			if ( is_wp_error( $response ) ) {
				return false;
			}
			return wp_remote_retrieve_body( $response );
		}

		public function adjust_hex_brightness( $hexCode, $adjustPercent ){
			$hexCode = ltrim($hexCode, '#');
			if( strlen( $hexCode ) == 3 ){
				$hexCode = $hexCode[0] . $hexCode[0] . $hexCode[1] . $hexCode[1] . $hexCode[2] . $hexCode[2];
			}
			$hexCode = array_map( 'hexdec', str_split( $hexCode, 2 ) );
			foreach( $hexCode as & $color ){
				$adjustableLimit = $adjustPercent < 0 ? $color : 255 - $color;
				$adjustAmount = ceil( $adjustableLimit * $adjustPercent );
				$color = str_pad( dechex( $color + $adjustAmount ), 2, '0', STR_PAD_LEFT );
			}
			return '#' . implode( $hexCode );
		}

		public function convert_hex_to_rgba( $color, $opacity = 1.0 ) {
			if ( abs( $opacity ) > 1 ){
				$opacity = 1.0;
			} else {
				$opacity = number_format( $opacity, 1, '.', '' );
			}
			if ( ! isset( $color ) ) {
				return 'rgb( 255, 255, 255, ' . esc_attr( $opacity ) . ' )';
			}
			if ( strlen( $color ) > 0 && '#' == $color[0] ) {
				$color = substr( $color, 1 );
			}
			if ( 6 == strlen( $color ) ) {
				$hex = array(
					$color[0] . $color[1],
					$color[2] . $color[3],
					$color[4] . $color[5]
				);
			} else if ( strlen( $color ) == 3 ) {
				$hex = array(
					$color[0] . $color[0],
					$color[1] . $color[1],
					$color[2] . $color[2]
				);
			} else {
				return 'rgb( 255, 255, 255, ' . esc_attr( $opacity ) . ' )';
			}
			$rgb = array_map( 'hexdec', $hex );
			return 'rgba( ' . implode( ', ', $rgb ) . ', ' . $opacity . ' )';
		}

		public function load_toggle_group( $id, $change_func, $enabled, $title, $subtitle, $row_id = false, $default_show = true ){
			echo '<div class="wp-easycart-admin-toggle-group"' . ( ( $row_id ) ? ' id="' . esc_attr( $row_id ) . '"' : '' ) . ( ( !$default_show ) ? ' style="display:none;"' : '' ) . '>
				<input type="checkbox" name="' . esc_attr( $id ) . '" id="' . esc_attr( $id ) . '" onchange="' . esc_attr( $change_func ) . '( jQuery( this ) );" value="1"';
				if( $enabled == "1" ){ 
					echo " checked=\"checked\""; 
				}
				echo '/> 
				<label for="' . esc_attr( $id ) . '">
					<span class="wp-easycart-admin-aural">' . esc_attr__( 'Show', 'wp-easycart' ) . ': </span> ';
				if ( 'show_pro_required' == substr( $change_func, 0, 17 ) ) {
					echo '<span class="dashicons dashicons-lock" style="color:#FC0; margin-top:-3px;"></span>';
				}
				echo '
				</label>
				<div class="wp-easycart-admin-onoffswitch wp-easycart-admin-pull-right" aria-hidden="true">
					<div class="wp-easycart-admin-onoffswitch-label">
						<div class="wp-easycart-admin-onoffswitch-inner"></div>
						<div class="wp-easycart-admin-onoffswitch-switch">
							<div class="wp-easycart-admin-dual-ring wp_easycart_toggle_saving" style="display: none;"></div>
							<div class="dashicons-before dashicons-yes-alt wp_easycart_toggle_saved" style="display: none;"></div>
						</div>
					</div>
				</div>
				<div class="wp-easycart-group-title">' . esc_attr( $title ) . '</div>
				<div class="wp-easycart-group-subtitle">' . esc_attr( $subtitle ) . '</div>
			</div>';
		}

		public function load_toggle_group_text( $id, $change_func, $value, $title, $subtitle, $placeholder, $row_id, $default_show = true, $last = false, $show_loader = true ){
			echo '<div class="wp-easycart-admin-toggle-group-text" style="';
			if( $last ){
				echo 'margin-bottom:0px !important;';
			}
			if( !$default_show ){ 
				echo ' display:none;';
			}
			echo '" id="' . esc_attr( $row_id ) . '">
				<label for="' . esc_attr( $id ) . '">
					' . esc_attr( $title ) . '
					<div class="subtitle">' . esc_attr( $subtitle ) . '</div>
				</label>
				<fieldset class="wp-easycart-admin-field-container">
					<input name="' . esc_attr( $id ) . '" id="' . esc_attr( $id ) . '" type="text" placeholder="' . esc_attr( $placeholder ) . '" value="' . esc_html( $value ) . '" onchange="return ' . esc_attr( $change_func ) . '( jQuery( this ) );" class="wp-easycart-admin-field" />';
			if( $show_loader ){
			echo '
					<div class="wp-easycart-admin-icons-container">
						<div class="wp-easycart-admin-icon-close" onclick="return ' . esc_attr( $change_func ) . '( jQuery( this ) );">
							<div class="wp-easycart-admin-icon-close-check"></div>
							<div class="wp-easycart-admin-dual-ring wp_easycart_toggle_saving" style="display: none;"></div>
							<div class="dashicons-before dashicons-yes-alt wp_easycart_toggle_saved" style="display: none;"></div>
						</div>
					</div>';
			}
			echo '
				</fieldset>
			</div>';
		}

		public function load_toggle_group_number( $id, $change_func, $value, $title, $subtitle, $placeholder, $row_id, $default_show = true, $last = false, $show_loader = true ){
			echo '<div class="wp-easycart-admin-toggle-group-number" style="';
			if( $last ){
				echo 'margin-bottom:0px !important;';
			}
			if( !$default_show ){ 
				echo ' display:none;';
			}
			echo '" id="' . esc_attr( $row_id ) . '">
				<label for="' . esc_attr( $id ) . '">
					' . esc_attr( $title ) . '
					<div class="subtitle">' . esc_attr( $subtitle ) . '</div>
				</label>
				<fieldset class="wp-easycart-admin-field-container">
					<input name="' . esc_attr( $id ) . '" id="' . esc_attr( $id ) . '" type="number" placeholder="' . esc_attr( $placeholder ) . '" value="' . esc_html( $value ) . '" onchange="return ' . esc_attr( $change_func ) . '( jQuery( this ) );" class="wp-easycart-admin-field" />';
			if( $show_loader ){
			echo '
					<div class="wp-easycart-admin-icons-container">
						<div class="wp-easycart-admin-icon-close" onclick="return ' . esc_attr( $change_func ) . '( jQuery( this ) );">
							<div class="wp-easycart-admin-icon-close-check"></div>
							<div class="wp-easycart-admin-dual-ring wp_easycart_toggle_saving" style="display: none;"></div>
							<div class="dashicons-before dashicons-yes-alt wp_easycart_toggle_saved" style="display: none;"></div>
						</div>
					</div>';
			}
			echo '
				</fieldset>
			</div>';
		}

		public function load_toggle_group_percentage( $id, $change_func, $value, $title, $subtitle, $placeholder, $row_id, $default_show = true, $last = false, $show_loader = true ){
			if ( ! is_numeric( $value ) || is_nan( $value ) ) {
				$value = 0;
			}
			if( $value < 1 ){
				$value = $value * 100;
			}
			echo '<div class="wp-easycart-admin-toggle-group-text" style="';
			if( $last ){
				echo 'margin-bottom:0px !important;';
			}
			if( !$default_show ){ 
				echo ' display:none;';
			}
			echo '" id="' . esc_attr( $row_id ) . '">
				<label for="' . esc_attr( $id ) . '">
					' . esc_attr( $title ) . '
					<div class="subtitle">' . esc_attr( $subtitle ) . '</div>
				</label>
				<fieldset class="wp-easycart-admin-field-container">
					<input name="' . esc_attr( $id ) . '" id="' . esc_attr( $id ) . '" type="number" step=".01" placeholder="' . esc_attr( $placeholder ) . '" value="' . esc_attr( $value ) . '" onchange="return ' . esc_attr( $change_func ) . '( jQuery( this ) );" class="wp-easycart-admin-field" />
					<span class="wp-easycart-admin-field-percentage">%</span>';
			if( $show_loader ){
			echo '
					<div class="wp-easycart-admin-icons-container">
						<div class="wp-easycart-admin-icon-close" onclick="return ' . esc_attr( $change_func ) . '( jQuery( this ) );">
							<div class="wp-easycart-admin-icon-close-check"></div>
							<div class="wp-easycart-admin-dual-ring wp_easycart_toggle_saving" style="display: none;"></div>
							<div class="dashicons-before dashicons-yes-alt wp_easycart_toggle_saved" style="display: none;"></div>
						</div>
					</div>';
			}
			echo '
				</fieldset>
			</div>';
		}

		public function load_toggle_group_textarea( $id, $change_func, $value, $title, $subtitle, $placeholder, $row_id, $default_show = true, $last = false ){
			echo '<div class="wp-easycart-admin-toggle-group-text" style="';
			if( $last ){
				echo 'margin-bottom:0px !important;';
			}
			if( !$default_show ){ 
				echo ' display:none;';
			}
			echo '" id="' . esc_attr( $row_id ) . '">
				<label for="' . esc_attr( $id ) . '">
					' . esc_attr( $title ) . '
					<div class="subtitle">' . esc_attr( $subtitle ) . '</div>
				</label>
				<fieldset class="wp-easycart-admin-field-container">
					<textarea name="' . esc_attr( $id ) . '" id="' . esc_attr( $id ) . '" placeholder="' . esc_attr( $placeholder ) . '" onchange="return ' . esc_attr( $change_func ) . '( jQuery( this ) );" class="wp-easycart-admin-field">' . wp_easycart_escape_html( wp_unslash( $value ) ) . '</textarea>
					<div class="wp-easycart-admin-icons-container">
						<div class="wp-easycart-admin-icon-close" onclick="return ' . esc_attr( $change_func ) . '( jQuery( this ) );">
							<div class="wp-easycart-admin-icon-close-check"></div>
							<div class="wp-easycart-admin-dual-ring wp_easycart_toggle_saving" style="display: none;"></div>
							<div class="dashicons-before dashicons-yes-alt wp_easycart_toggle_saved" style="display: none;"></div>
						</div>
					</div>
				</fieldset>
			</div>';
		}

		public function load_toggle_group_color( $id, $change_func, $value, $title, $subtitle, $placeholder, $row_id, $default_show = true, $last = false ){
			echo '<div class="wp-easycart-admin-toggle-group-text" style="';
			if( $last ){
				echo 'margin-bottom:0px !important;';
			}
			if( !$default_show ){ 
				echo ' display:none;';
			}
			echo '" id="' . esc_attr( $row_id ) . '">
				<label for="' . esc_attr( $id ) . '">
					' . esc_attr( $title ) . '
					<div class="subtitle">' . esc_attr( $subtitle ) . '</div>
				</label>
				<fieldset class="wp-easycart-admin-field-container">
					<input name="' . esc_attr( $id ) . '" id="' . esc_attr( $id ) . '" type="text" placeholder="' . esc_attr( $placeholder ) . '" value="' . esc_attr( $value ) . '" onchange="return ' . esc_attr( $change_func ) . '( jQuery( this ) );" class="wp-easycart-admin-field ec_color_block_input" />
					<div class="wp-easycart-admin-icons-container">
						<div class="wp-easycart-admin-icon-close" onclick="return ' . esc_attr( $change_func ) . '( jQuery( this ) );">
							<div class="wp-easycart-admin-icon-close-check"></div>
							<div class="wp-easycart-admin-dual-ring wp_easycart_toggle_saving" style="display: none;"></div>
							<div class="dashicons-before dashicons-yes-alt wp_easycart_toggle_saved" style="display: none;"></div>
						</div>
					</div>
				</fieldset>
			</div>';
		}

		public function load_toggle_group_image( $id, $change_func, $value, $title, $subtitle, $placeholder, $row_id, $default_show = true, $last = false ){
			echo '<div class="wp-easycart-admin-toggle-group-text" style="';
			if( $last ){
				echo 'margin-bottom:0px !important;';
			}
			if( !$default_show ){ 
				echo ' display:none;';
			}
			echo '" id="' . esc_attr( $row_id ) . '">
				<label for="' . esc_attr( $id ) . '">
					' . esc_attr( $title ) . '
					<div class="subtitle">' . esc_attr( $subtitle ) . '</div>
				</label>
				<fieldset class="wp-easycart-admin-field-container">
					<input name="' . esc_attr( $id ) . '" id="' . esc_attr( $id ) . '" type="text" placeholder="' . esc_attr( $placeholder ) . '" value="' . esc_attr( $value ) . '" onchange="return ' . esc_attr( $change_func ) . '( jQuery( this ) );" class="wp-easycart-admin-field" />
					<input type="button" class="wp_easycart_admin_image_upload_button" data-input-id="' . esc_attr( $id ) . '" data-image-id="' . esc_attr( $id ) . '_image" data-delete-id="' . esc_attr( $id ) . '_remove_link" id="' . esc_attr( $id ) . '_upload_logo_button" type="button" value="' . esc_attr__( 'Upload Image', 'wp-easycart' ) . '" />
					<div class="wp-easycart-admin-icons-container">
						<div class="wp-easycart-admin-icon-close" onclick="return ' . esc_attr( $change_func ) . '( jQuery( this ) );">
							<div class="wp-easycart-admin-icon-close-check"></div>
							<div class="wp-easycart-admin-dual-ring wp_easycart_toggle_saving" style="display: none;"></div>
							<div class="dashicons-before dashicons-yes-alt wp_easycart_toggle_saved" style="display: none;"></div>
						</div>
					</div>
				</fieldset>
			</div>';

			echo '<div style="float:left; height:auto; position:relative;">
				<a href="#" onclick="wp_easycart_admin_remove_image( jQuery( this ) ); return false;" id="' . esc_attr( $id ) . '_remove_link" style="position:absolute; top:13px; right:5px; padding:8px 10px; text-decoration:none; background:#7bb141; border-radius:100px; color:#FFF; font-weight:bold; line-height:1em;' . ( ( $value == '' ) ? ' display:none;' : '' ) . '">delete</a>
				<img src="' . esc_attr( $value ) . '" id="' . esc_attr( $id ) . '_image" style="float:left; max-width:100%; max-height:250px; margin:10px 0; padding:10px; border:1px solid #a2ab9f;' . ( ( $value == '' ) ? ' display:none;' : '' ) . '" />
			</div>';
		}

		public function load_toggle_group_select( $id, $change_func, $value, $title, $subtitle, $options, $row_id, $default_show = true, $last = false, $multiple = false, $show_loader = true ){
			echo '<div class="wp-easycart-admin-toggle-group-text" style="';
			if( $last ){
				echo 'margin-bottom:0px !important;';
			}
			if( !$default_show ){ 
				echo ' display:none;';
			}
			echo '" id="' . esc_attr( $row_id ) . '">
				<label for="' . esc_attr( $id ) . '">
					' . esc_attr( $title ) . '
					<div class="subtitle">' . esc_attr( $subtitle ) . '</div>
				</label>
				<fieldset class="wp-easycart-admin-field-container">
					<select name="' . esc_attr( $id ) . '" id="' . esc_attr( $id ) . '" onchange="return ' . esc_attr( $change_func ) . '( jQuery( this ) );" class="wp-easycart-admin-field' . ( ($multiple) ? ' wp-easycart-admin-field-select-multiple' : '' ) . '"' . ( ($multiple) ? ' multiple' : '' ) . '>';

			foreach( $options as $option ){
				echo '<option value="' . esc_attr( $option->value ) . '"' . ( ( ( $multiple && is_array( $value ) && in_array( $option->value, $value ) ) || ( ! $multiple && $option->value == $value ) ) ? ' selected="selected"' : '' ) . '>' . esc_attr( strip_tags( $option->label ) ) . '</option>';
			}

			echo '
					</select>';
			if( $show_loader ){
			echo '
					<div class="wp-easycart-admin-icons-container">
						<div class="wp-easycart-admin-icon-close">
							<div class="wp-easycart-admin-dual-ring wp_easycart_toggle_saving" style="display: none;"></div>
							<div class="dashicons-before dashicons-yes-alt wp_easycart_toggle_saved" style="display: none;"></div>
						</div>
					</div>';
			}
			echo '
				</fieldset>
			</div>';
		}

		public function load_editable_table( $table_id, $columns, $data, $actions, $add_new_func, $update_func, $bulk_actions = array( 'delete' => 'Delete Selected' ), $nonce_field = false  ){
			echo '<div class="wp-easycart-editable-table-holder" id="' . esc_attr( $table_id ) . '">';
				$this->print_editable_table_bulk_actions( $table_id, $bulk_actions, $nonce_field );
				echo '<table class="wp-easycart-editable-table pagination-10" data-update-func="' . esc_attr( $update_func ) . '" data-nonce-field="' . esc_attr( $nonce_field ) . '">';
					$this->print_editable_table_header( $table_id, $columns, $actions, $bulk_actions );
					echo '<tbody>';
						foreach( $data as $item ){
							$this->print_editable_table_row( $table_id, $columns, $item, $actions, $bulk_actions, $nonce_field );
						}
						$this->print_editable_table_row_default( $table_id, $columns, $actions, $bulk_actions, $nonce_field );
						$this->print_editable_table_row_none( $table_id, $columns, count( $data ), $bulk_actions, $nonce_field );
						if( count( $actions ) > 0 ){
						   $this->print_editable_table_add_new( $table_id, $columns, $add_new_func, $bulk_actions, $nonce_field );
						}
					echo '</tbody>';
				echo '</table>';
				$this->print_editable_table_pagination( $table_id, $data );
			echo '</div>';
		}

		private function print_editable_table_bulk_actions( $table_id, $bulk_actions, $nonce_field ){
			echo '<div class="wp-easycart-editable-table-bulk">';
				if( count( $bulk_actions ) ){
					echo '<select id="' . esc_attr( $table_id ) . '_bulk_action" data-table-id="' . esc_attr( $table_id ) . '" data-nonce-field="' . esc_attr( $nonce_field ) . '">';
						echo '<option value="">' . esc_attr__( 'Select an Action', 'wp-easycart' ) . '</option>';
						foreach( $bulk_actions as $bulk_action => $bulk_action_label ){
							echo '<option value="' . esc_attr( $bulk_action ) . '">' . esc_attr( $bulk_action_label ) . '</option>';
						}
					echo '</select>';
					echo '<button class="wp-easycart-editable-table-bulk-apply" data-table-id="' . esc_attr( $table_id ) . '">' . esc_attr__( 'Apply', 'wp-easycart' ) . '</button>';
				}
				echo '<div class="wp-easycart-editable-table-search-bar">';
					echo '<input type="search" name="search" pattern=".*\S.*">';
					echo '<button class="wp-easycart-editable-table-search-btn" onclick="return false;">';
						echo '<span>' . esc_attr__( 'Search', 'wp-easycart' ) . '</span>';
					echo '</button>';
				echo '</div>';
			echo '</div>';
		}

		private function print_editable_table_pagination( $table_id, $data ){
			echo '<div class="wp-easycart-editable-table-pagination">';
				echo '<select class="' . esc_attr( $table_id ) . '_per_page">';
					echo '<option value="10" selected="selected">10 ' . esc_attr__( 'rows', 'wp-easycart' ) . '</option>';
					echo '<option value="25">25 ' . esc_attr__( 'rows', 'wp-easycart' ) . '</option>';
					echo '<option value="50">50 ' . esc_attr__( 'rows', 'wp-easycart' ) . '</option>';
					echo '<option value="100">100 ' . esc_attr__( 'rows', 'wp-easycart' ) . '</option>';
				echo '</select>';
				echo '<ul>';
					if( ceil( count( $data ) / 10 ) > 3 ){
						echo '<li data-page="1" class="wp-easycart-editable-table-pagination-first">&#60;&#60;</li>';
					}
					for( $i=1; $i<=ceil( count( $data ) / 10 ); $i++ ){
						echo '<li data-page="' . esc_attr( $i ) . '" class="wp-easycart-editable-page-item' . esc_attr( ( ( $i==1 ) ? ' selected' : '' ) ) . '">' . esc_attr( $i ) . '</li>';
					}
					if( ceil( count( $data ) / 10 ) > 3 ){
						echo '<li data-page="' . esc_attr( ceil( count( $data ) / 10 ) ) . '" class="wp-easycart-editable-table-pagination-last">&#62;&#62;</li>';
					}
				echo '</ul>';
				echo '<div class="wp-easycart-editable-table-paging">' . sprintf( esc_attr__( 'Showing %s of %s', 'wp-easycart' ), '<span class="wp-easycart-editable-table-paging-showing">' . esc_attr( ( ( count( $data ) > 0 ) ? 1 : 0 ) ) . '-' . esc_attr( ( ( count( $data ) > 10 ) ? 10 : count( $data ) ) ) . '</span>', '<span class="wp-easycart-editable-table-paging-total">' . esc_attr( count( $data ) ) . '</span>' ) . '</div>';
			echo '</div>';
		}

		private function print_editable_table_header( $table_id, $columns, $actions, $bulk_actions ){
			echo '<thead>';
				echo '<tr>';
					if( count( $bulk_actions ) ){
						echo '<th><input type="checkbox" class="wpeasycart-editable-table-select-all" /></th>';
					}
					foreach( $columns as $column ){
						echo '<th style="text-align:' . ( ( isset( $column['labelpos'] ) ) ? esc_attr( $column['labelpos'] ) : 'left' ) . ';' . ( ( isset( $column['width'] ) ) ? ' width:' . esc_attr( $column['width'] ) . ';' : '' ) . '"' . ( ( !isset( $column['sort'] ) || $column['sort'] ) ? ' class="sortable"' : '' ) . ' data-column="' . esc_attr( $column['id'] ) . '" data-type="' . ( ( isset( $column['type'] ) ) ? esc_attr( $column['type'] ) : '' ) . '">' . esc_attr( $column['label'] ) . ( ( !isset( $column['sort'] ) || $column['sort'] ) ? '<span class="dashicons dashicons-sort wpeasycart-editable-table-sort"></span>' : '' ) . '</th>';
					}
					if( count( $actions ) > 0 ){
						echo '<th></th>';
					}
				echo '</tr>';
			echo '</thead>';
		}

		private function print_editable_table_row_default( $table_id, $columns, $actions, $bulk_actions, $nonce_field ){
			echo '<tr class="wp-easycart-editable-table-row-default">';
			if( count( $bulk_actions ) ){
				echo '<td><input type="checkbox" id="' . esc_attr( $table_id ) . '_" class="wp-easycart-editable-table-select-item" data-nonce-field="' . esc_attr( $nonce_field ) . '" /></td>';
			}
			foreach( $columns as $column ){
				$this->print_editable_table_column( $table_id, $column, ( ( isset( $column['default'] ) ) ? $column['default'] : '' ), '', $nonce_field );
			}
			$this->print_editable_table_actions( $table_id, $actions, '', $nonce_field );
			echo '</tr>';
		}

		private function print_editable_table_row( $table_id, $columns, $item, $actions, $bulk_actions, $nonce_field ){
			echo '<tr class="wp-easycart-editable-table-row" data-id="' . esc_attr( $item->id ) . '">';
			if( count( $bulk_actions ) ){
				echo '<td><input type="checkbox" id="' . esc_attr( $table_id ) . '_' . esc_attr( $item->id ) . '" class="wp-easycart-editable-table-select-item" data-nonce-field="' . esc_attr( $nonce_field ) . '" /></td>';
			}
			foreach( $columns as $column ){
				$this->print_editable_table_column( $table_id, $column, $item->{$column['id']}, $item->id, $nonce_field );
			}
			$this->print_editable_table_actions( $table_id, $actions, $item->id, $nonce_field );
			echo '</tr>';
		}

		private function print_editable_table_row_none( $table_id, $columns, $row_count, $bulk_actions, $nonce_field ){
			$add_columns = ( count( $bulk_actions ) > 0 ) ? 2 : 1;
			echo '<tr class="wp-easycart-editable-table-row-none"' . ( ( $row_count > 0 ) ? 'style="display:none;"' : '' ) . '>';
				echo '<td colspan="' . esc_attr( ( count( $columns ) + $add_columns ) ) . '">' . esc_attr__( 'No rows, add new below.', 'wp-easycart' ) . '</td>';
			echo '</tr>';
		}

		private function print_editable_table_add_new( $table_id, $columns, $add_new_func, $bulk_actions, $nonce_field ){
			echo '<tr class="wp-easycart-editable-table-add-new-break"><td colspan="' . esc_attr( ( count( $columns ) + 2 ) ) . '"></td></tr>';
			echo '<tr class="wp-easycart-editable-table-add-new">';
				if( count( $bulk_actions ) ){
					echo '<td></td>';
				}
				foreach( $columns as $column ){
				   $this->print_editable_table_column( $table_id, $column, ( ( isset( $column['default'] ) && is_array( $column['default'] ) ) ? $column['default']['value'] : ( ( isset( $column['default'] ) ) ? $column['default'] : '' ) ), '0', $nonce_field );
				}
				echo '<td><div class="dashicons dashicons-plus wpeasycart-editable-table-add-new" data-table="' . esc_attr( $table_id ) . '" data-func="' . ( ( is_array( $add_new_func ) ) ? esc_attr( $add_new_func['add_func'] ) : esc_attr( $add_new_func ) ) . '"' . ( ( is_array( $add_new_func ) ) ? 'data-callback="' . esc_attr( $add_new_func['callback_func'] ) . '"' : '' ) . ' data-nonce-field="' . esc_attr( $nonce_field ) . '"></div></td>';
			echo '</tr>';
		}

		private function print_editable_table_column( $table_id, $column, $value, $id, $nonce_field = '' ){
			echo '<td' . ( ( isset( $column['labelpos'] ) ) ? ' style="text-align:' . esc_attr( $column['labelpos'] ) . ';"' : '' ) . ' data-column="' . esc_attr( $column['id'] ) . '">';
				if ( $column['type'] == 'combo' ) {
					$this->print_editable_table_column_select( $table_id, $column, $value, $id, $nonce_field );
				} else if ( $column['type'] == 'text' ) {
					$this->print_editable_table_column_text( $table_id, $column, $value, $id, $nonce_field );
				} else if ( $column['type'] == 'number' ) {
					$this->print_editable_table_column_number( $table_id, $column, $value, $id, $nonce_field );
				} else if ( $column['type'] == 'percentage' ) {
					$this->print_editable_table_column_percentage( $table_id, $column, $value, $id, $nonce_field );
				} else if ( $column['type'] == 'checkbox' ) {
					$this->print_editable_table_column_checkbox( $table_id, $column, $value, $id, $nonce_field );
				} else if ( $column['type'] == 'multitag' ) {
					$this->print_editable_table_column_multitag( $table_id, $column, $value, $id, $nonce_field );
				} else {
					echo '<div class="wp-easycart-editable-table-read-only" id="' . esc_attr( $table_id ) . '_' . esc_attr( $column['id'] ) . '_' . esc_attr( $id ) . '">' . esc_attr( $value ) . '</div>';
				}
			echo '</td>';
		}

		private function print_editable_table_column_select( $table_id, $column, $value, $id, $nonce_field = '' ){
			$group_id = '';
			if( $id != '0' ){
				echo '<button class="wp-easycart-editable-table-update-row"><div class="dashicons dashicons-yes"></div></button>';
			}
			echo '<select id="' . esc_attr( $table_id ) . '_' . esc_attr( $column['id'] ) . '_' . esc_attr( $id ) . '" class="wp-easycart-editable-table-input ' . ( ( !isset( $column['required'] ) || $column['required'] ) ? 'wp-easycart-editable-table-input-required' : '' ) . ' ' . ( ( isset( $column['cssclass'] ) ) ? esc_attr( $column['cssclass'] ) : '' ) . ( ( isset( $column['multiple'] ) ) ? ' select2-multiple' : '' ) . ' ' . esc_attr( $table_id ) . '_input_' . esc_attr( $id ) . '" data-nonce-field="' . esc_attr( $nonce_field ) . '" data-id="' . esc_attr( $column['id'] ) . '" data-default="' . esc_attr( $column['default']['value'] ) . '"' . ( ( $id != '0' && isset( $column['readonly'] ) && $column['readonly'] ) ? ' disabled="disabled"' : '' ) . ( ( isset( $column['multiple'] ) ) ? ' multiple="multiple"' : '' ) . '>';
				echo '<option value="' . esc_attr( $column['default']['value'] ) . '">' . esc_attr__( esc_attr( $column['default']['label'] ), 'wp-easycart' ) . '</option>';
			foreach( $column['options'] as $option ){
				if( isset( $option->group_id ) && $option->group_id != $group_id ){
					if( $group_id != '' ){
						echo '</optgroup>';
					}
					echo '<optgroup label="' . esc_attr( $option->group_id ) . '">';
					$group_id = $option->group_id;
				}
				$selected = false;
				if ( isset( $column['multiple'] ) ) {
					if ( isset( $value ) && is_string( $value ) ) {
						$values = json_decode( $value );
						if ( $values && is_array( $values ) ) {
							foreach ( $values as $value_item ) {
								if ( $option->value == $value_item ) {
									$selected = true;
								}
							}
						}
					}
				} else if ( $option->value == $value ) {
					$selected = true;
				}
				echo '<option value="' . esc_attr( $option->value ) . '"' . ( ( $selected ) ? ' selected="selected"' : '' ) . ( ( isset( $option->group_id ) ) ? ' data-group="' . esc_attr( $option->group_id ) : '' ) . '">' . esc_attr( $option->label ) . '</option>';
			}
			if( $group_id != 0 ){
				echo '</optgroup>';
			}
			echo '</select>';
		}

		private function print_editable_table_column_text( $table_id, $column, $value, $id, $nonce_field = '' ){
			if( $id != '0' ){
				echo '<button class="wp-easycart-editable-table-update-row"><div class="dashicons dashicons-yes"></div></button>';
			}
			echo '<input type="text" value="' . esc_attr( $value ) . '" id="' . esc_attr( $table_id ) . '_' . esc_attr( $column['id'] ) . '_' . esc_attr( $id ) . '" class="wp-easycart-editable-table-input ' . ( ( !isset( $column['required'] ) || $column['required'] ) ? 'wp-easycart-editable-table-input-required' : '' ) . ' ' . ( ( isset( $column['cssclass'] ) ) ? esc_attr( $column['cssclass'] ) : '' ) . ' ' . esc_attr( $table_id ) . '_input_' . esc_attr( $id ) . '" data-nonce-field="' . esc_attr( $nonce_field ) . '" data-id="' . esc_attr( $column['id'] ) . '" data-default="' . ( ( isset( $column['default'] ) ) ? esc_attr( $column['default'] ) : '' ) . '"' . ( ( $id != '0' && isset( $column['readonly'] ) && $column['readonly'] ) ? ' readonly="readonly"' : '' ) . ' />';
		}

		private function print_editable_table_column_number( $table_id, $column, $value, $id, $nonce_field = '' ){
			if( $id != '0' ){
				echo '<button class="wp-easycart-editable-table-update-row"><div class="dashicons dashicons-yes"></div></button>';
			}
			echo '<input type="number" value="' . esc_attr( $value ) . '" step="' . ( ( isset( $column['step'] ) ) ? $column['step'] : .01 ) . '" min="' . ( ( isset( $column['min'] ) ) ? $column['min'] : .01 ) . '" max="' . ( ( isset( $column['max'] ) ) ? $column['max'] : .01 ) . '" id="' . esc_attr( $table_id ) . '_' . esc_attr( $column['id'] ) . '_' . esc_attr( $id ) . '" class="wp-easycart-editable-table-input ' . ( ( !isset( $column['required'] ) || $column['required'] ) ? 'wp-easycart-editable-table-input-required' : '' ) . ' ' . ( ( isset( $column['cssclass'] ) ) ? esc_attr( $column['cssclass'] ) : '' ) . ' ' . esc_attr( $table_id ) . '_input_' . esc_attr( $id ) . '" data-nonce-field="' . esc_attr( $nonce_field ) . '" data-id="' . esc_attr( $column['id'] ) . '" data-default="' . ( ( isset( $column['default'] ) ) ? esc_attr( $column['default'] ) : '' ) . '"' . ( ( $id != '0' && isset( $column['readonly'] ) && $column['readonly'] ) ? ' readonly="readonly"' : '' ) . ' />';
		}

		private function print_editable_table_column_percentage( $table_id, $column, $value, $id, $nonce_field = '' ){
			if( $id != '0' ){
				echo '<button class="wp-easycart-editable-table-update-row"><div class="dashicons dashicons-yes"></div></button>';
			}
			echo '<input type="text" value="' . esc_attr( $value ) . '" id="' . esc_attr( $table_id ) . '_' . esc_attr( $column['id'] ) . '_' . esc_attr( $id ) . '" class="wp-easycart-editable-table-input ' . ( ( !isset( $column['required'] ) || $column['required'] ) ? 'wp-easycart-editable-table-input-required' : '' ) . ' ' . ( ( isset( $column['cssclass'] ) ) ? esc_attr( $column['cssclass'] ) : '' ) . ' percentage ' . esc_attr( $table_id ) . '_input_' . esc_attr( $id ) . '" data-nonce-field="' . esc_attr( $nonce_field ) . '" data-id="' . esc_attr( $column['id'] ) . '" data-default="' . ( ( isset( $column['default'] ) ) ? esc_attr( $column['default'] ) : '' ) . '"' . ( ( $id != '0' && isset( $column['readonly'] ) && $column['readonly'] ) ? ' readonly="readonly"' : '' ) . ' />%';
		}

		private function print_editable_table_column_checkbox( $table_id, $column, $value, $id, $nonce_field = '' ){
			echo '<input type="checkbox" value="1"';
			if( $value ){
				echo ' checked="checked"';
			}
			echo ' id="' . esc_attr( $table_id ) . '_' . esc_attr( $column['id'] ) . '_' . esc_attr( $id ) . '" class="wp-easycart-editable-table-input ' . ( ( !isset( $column['required'] ) || $column['required'] ) ? 'wp-easycart-editable-table-input-required' : '' ) . ' ' . ( ( isset( $column['cssclass'] ) ) ? esc_attr( $column['cssclass'] ) : '' ) . ' ' . esc_attr( $table_id ) . '_input_' . esc_attr( $id ) . '" data-nonce-field="' . esc_attr( $nonce_field ) . '" data-id="' . esc_attr( $column['id'] ) . '" data-default="' . ( ( isset( $column['default'] ) ) ? esc_attr( $column['default'] ) : '' ) . '"' . ( ( $id != '0' && isset( $column['readonly'] ) && $column['readonly'] ) ? ' readonly="readonly"' : '' ) . ' />';
		}

		private function print_editable_table_column_multitag( $table_id, $column, $value, $id, $nonce_field = '' ){
			$group_id = '';
			$value = json_decode( $value );
			if( $id != '0' ){
				echo '<button class="wp-easycart-editable-table-update-row"><div class="dashicons dashicons-yes"></div></button>';
			}
			echo '<select multiple="multiple" id="' . esc_attr( $table_id ) . '_' . esc_attr( $column['id'] ) . '_' . esc_attr( $id ) . '" class="select2-multiple wp-easycart-editable-table-input ' . ( ( !isset( $column['required'] ) || $column['required'] ) ? 'wp-easycart-editable-table-input-required' : '' ) . ' ' . ( ( isset( $column['cssclass'] ) ) ? esc_attr( $column['cssclass'] ) : '' ) . ' ' . esc_attr( $table_id ) . '_input_' . esc_attr( $id ) . '" data-nonce-field="' . esc_attr( $nonce_field ) . '" data-id="' . esc_attr( $column['id'] ) . '" data-default="' . esc_attr( $column['default']['value'] ) . '"' . ( ( $id != '0' && isset( $column['readonly'] ) && $column['readonly'] ) ? ' disabled="disabled"' : '' ) . '>';
				echo '<option value="' . esc_attr( $column['default']['value'] ) . '"' . ( ( !$value || in_array( $column['default']['value'], $value ) ) ? ' selected="selected"' : '' ) . '>' . esc_attr__( esc_attr( $column['default']['label'] ), 'wp-easycart' ) . '</option>';
			foreach( $column['options'] as $option ){
				if( isset( $option->group_id ) && $option->group_id != $group_id ){
					if( $group_id != '' ){
						echo '</optgroup>';
					}
					echo '<optgroup label="' . esc_attr( $option->group_id ) . '">';
					$group_id = $option->group_id;
				}
				echo '<option value="' . esc_attr( $option->value ) . '"' . ( ( in_array( $option->value, $value ) ) ? ' selected="selected"' : '' ) . ( ( isset( $option->group_id ) ) ? ' data-group="' . esc_attr( $option->group_id ) . '"' : '' ) . '>' . esc_attr( $option->label ) . '</option>';
			}
			if( count( $column['options'] ) <= 1 && is_array( $value ) ){
				foreach( $value as $value_item ){
					if( $value_item != '' ){
						echo '<option value="' . esc_attr( $value_item ) . '" selected="selected">' . esc_attr( $value_item ) . '</option>';
					}
				}
			}
			if( $group_id != 0 ){
				echo '</optgroup>';
			}
			echo '</select>';
		}

		private function print_editable_table_actions( $table_id, $actions, $id, $nonce_field ){
			if( count( $actions ) > 0 ){
				echo '<td class="wp-easycart-editable-table-actions">';
				foreach( $actions as $action_id => $action ){
					echo '<a href="#" class="wpeasycart-editable-table-delete" data-table="' . esc_attr( $table_id ) . '" data-id="' . esc_attr( $id ) . '" data-func="' . esc_attr( $action['function'] ) . '" data-nonce-field="' . esc_attr( $nonce_field ) . '"' . ( ( isset( $action['callback'] ) ) ? ' data-callback="' . esc_attr( $action['callback'] ) . '"' : '' ) . ' title="' . esc_attr__( esc_attr( $action['label'] ), 'wp-easycart' ) . '"><div class="dashicons ' . esc_attr( $action['icon'] ) . '"></div></a>';
				}
				echo '</td>';
			}
		}

	}
endif; // End if class_exists check


/*
 * 6.0.1: the "downloadable product with no file" count is cached for a day, so a product saved without a
 * file did not raise the notice until the transient expired, while the product list tile ( which counts
 * live ) already showed it. Any product write drops the cache so the two agree.
 */
foreach ( array( 'wpeasycart_product_added', 'wpeasycart_product_updated', 'wp_easycart_product_updated', 'wpeasycart_product_deleted', 'wpeasycart_product_restored' ) as $wpec_dl_hook ) {
	add_action( $wpec_dl_hook, 'wp_easycart_admin_clear_download_recovery_cache' );
}
unset( $wpec_dl_hook );
if ( ! function_exists( 'wp_easycart_admin_clear_download_recovery_cache' ) ) {
	/** Forget the cached count of downloadable products missing a file. @since 6.0.1 */
	function wp_easycart_admin_clear_download_recovery_cache() {
		delete_transient( 'ec_download_recovery_count' );
	}
}

if ( ! function_exists( 'wp_easycart_admin_review_prompt_earned' ) ) {
	/**
	 * Has this store put a real order all the way through?
	 *
	 * Asking for a review on the day the plugin is installed asks someone who has not used it yet. The card now
	 * waits for the first non-demo order that is approved, not refunded or cancelled, and fulfilled: shipped or
	 * picked up, carrying a tracking number, or with nothing to ship at all ( downloads, gift cards, services ),
	 * which the order list already treats as fulfilled on payment. Same rule as
	 * wp_easycart_admin_order_table::unfulfilled_where(), inverted.
	 *
	 * The answer only ever goes from no to yes, so it is latched in an option and the query stops running.
	 *
	 * @since 6.0.1
	 * @return bool
	 */
	function wp_easycart_admin_review_prompt_earned() {
		if ( get_option( 'ec_option_review_order_seen' ) ) {
			return true;
		}
		global $wpdb;
		$shipped   = class_exists( 'wp_easycart_admin_order_table' ) ? wp_easycart_admin_order_table::STATUS_SHIPPED : 2;
		$picked_up = class_exists( 'wp_easycart_admin_order_table' ) ? wp_easycart_admin_order_table::STATUS_PICKED_UP : 18;
		$refunded  = class_exists( 'wp_easycart_admin_order_table' ) ? wp_easycart_admin_order_table::STATUS_REFUNDED : 16;
		$cancelled = class_exists( 'wp_easycart_admin_order_table' ) ? wp_easycart_admin_order_table::STATUS_CANCELLED : 19;
		$found     = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT o.order_id FROM ec_order o
			 INNER JOIN ec_orderstatus s ON s.status_id = o.orderstatus_id
			 WHERE o.is_demo_item = 0 AND s.is_approved = 1
			 AND o.orderstatus_id NOT IN ( %d, %d )
			 AND (
				o.tracking_number != \'\'
				OR o.orderstatus_id IN ( %d, %d )
				OR NOT EXISTS ( SELECT 1 FROM ec_orderdetail d WHERE d.order_id = o.order_id AND d.is_shippable = 1 AND d.is_download = 0 AND d.is_giftcard = 0 )
			 )
			 LIMIT 1',
			$refunded,
			$cancelled,
			$shipped,
			$picked_up
		) );
		if ( $found ) {
			update_option( 'ec_option_review_order_seen', 1 );
			return true;
		}
		return false;
	}
}

if ( ! function_exists( 'wp_easycart_admin_print_review_prompt' ) ) {
	/**
	 * The "enjoying WP EasyCart?" card above the product, order and customer lists. Shown until the merchant follows
	 * the link or dismisses it ( ec_option_review_complete, set by ec_admin_ajax_close_review_us ).
	 *
	 * 6.0.1: was a full-width cyan bar from v1; now a quiet V2 card that matches the rest of the admin, and it
	 * holds off until the store has fulfilled its first real order ( wp_easycart_admin_review_prompt_earned ).
	 * Filter 'wp_easycart_show_review_prompt' to override either way.
	 *
	 * @since 6.0.1
	 */
	function wp_easycart_admin_print_review_prompt() {
		$show = ! get_option( 'ec_option_review_complete' ) && wp_easycart_admin_review_prompt_earned();
		if ( ! apply_filters( 'wp_easycart_show_review_prompt', $show ) ) {
			return;
		}
		$stars = '';
		for ( $i = 0; $i < 5; $i++ ) {
			$stars .= '<svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor" aria-hidden="true"><path d="m12 3.6 2.5 5.1 5.6.8-4 3.9 1 5.6-5.1-2.7-5 2.7 1-5.6-4.1-3.9 5.6-.8z"/></svg>';
		}
		?>
		<div class="ecv2-wrap ecv2-review-prompt wp-easycart-admin-review-us-box">
			<span class="ecv2-review-stars" aria-hidden="true"><?php echo $stars; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG built above. ?></span>
			<span class="ecv2-review-text">
				<b><?php esc_html_e( 'Enjoying WP EasyCart?', 'wp-easycart' ); ?></b>
				<span><?php esc_html_e( 'A review on WordPress.org helps other stores find it, and tells us what to build next.', 'wp-easycart' ); ?></span>
			</span>
			<a class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" href="https://wordpress.org/support/plugin/wp-easycart/reviews/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Leave a review', 'wp-easycart' ); ?></a>
			<button type="button" class="ecv2-review-close wp-easycart-admin-review-us-close" onclick="wp_easycart_admin_close_review( '<?php echo esc_attr( wp_create_nonce( 'wp-easycart-review-us' ) ); ?>' );" aria-label="<?php esc_attr_e( 'Dismiss', 'wp-easycart' ); ?>" title="<?php esc_attr_e( 'Dismiss', 'wp-easycart' ); ?>">
				<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
			</button>
		</div>
		<?php
	}
}
function wp_easycart_admin( ){
	return wp_easycart_admin::instance( );
}
wp_easycart_admin( );

if ( ! function_exists( 'wp_easycart_admin_notice_html' ) ) {
	/**
	 * V2 admin notice markup ( success / warning / error / info ). PRO and add-ons should call this
	 * instead of printing the classic .ec_admin_message_* boxes.
	 *
	 * @since 6.0.0
	 */
	function wp_easycart_admin_notice_html( $tone, $text, $args = array() ) {
		return wp_easycart_admin::notice_html( $tone, $text, $args );
	}
}

add_action( 'wp_ajax_ec_admin_ajax_allow_tracking', 'ec_admin_ajax_allow_tracking' );
function ec_admin_ajax_allow_tracking() {
	if ( ! wp_easycart_admin_verification()->verify_access( 'wp-easycart-tracking' ) ) {
		return false;
	}

	update_option( 'ec_option_allow_tracking', '1' );
	if ( ! function_exists( 'wp_easycart_admin_tracking' ) ) {
		include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_tracking.php' );
	}
	do_action( 'wpeasycart_admin_usage_tracking_accepted' );
	die();
}

add_action( 'wp_ajax_ec_admin_ajax_deny_tracking', 'ec_admin_ajax_deny_tracking' );
function ec_admin_ajax_deny_tracking() {
	if ( ! wp_easycart_admin_verification()->verify_access( 'wp-easycart-disable-usage-tracking' ) ) {
		return false;
	}

	update_option( 'ec_option_allow_tracking', '-1' );
	die();
}

add_action( 'wp_ajax_ec_admin_ajax_close_review_us', 'ec_admin_ajax_close_review_us' );
function ec_admin_ajax_close_review_us( ){
	if ( ! wp_easycart_admin_verification()->verify_access( 'wp-easycart-review-us' ) ) {
		return false;
	}

	update_option( 'ec_option_review_complete', '1' );
	die();
}

add_action( 'wp_ajax_ec_admin_ajax_custom_deactivate', 'ec_admin_ajax_custom_deactivate' );
function ec_admin_ajax_custom_deactivate( ){
	if ( ! wp_easycart_admin_verification()->verify_access( 'wp-easycart-deactivate-why' ) ) {
		return false;
	}

	if ( ! function_exists( 'wp_easycart_admin_tracking' ) ) {
		include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_tracking.php' );
	}
	do_action( 'wpeasycart_deactivated' );
	die();
}

add_action( 'wp_ajax_ec_admin_ajax_save_color_scheme', 'ec_admin_ajax_save_color_scheme' );
function ec_admin_ajax_save_color_scheme( ){
	if ( ! wp_easycart_admin_verification()->verify_access( 'wp-easycart-admin-color' ) ) {
		return false;
	}

	update_option( 'ec_option_admin_color', preg_replace( '/\\\x[0-9a-f]{2}/', '', sanitize_text_field( wp_unslash( $_POST['ec_option_admin_color'] ) ) ) );
	die();
}

/**
 * Report filter arguments from a Reports-page POST ( shared by the chart refresh and the export job ).
 *
 * @since 6.0.0
 * @return array start_date, end_date, start_date2, end_date2, range, product_id, country, billing_country, location_id.
 */
function ec_admin_report_request_args() {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- callers verify the wp-easycart-updated-stats / wp-easycart-export-stats nonce through wp_easycart_admin_verification() before reading these.
	$text = function( $key ) {
		return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
	};
	$start_date2 = $text( 'start_date2' );
	$end_date2   = $text( 'end_date2' );
	$args = array(
		'start_date'      => $text( 'start_date' ),
		'end_date'        => $text( 'end_date' ),
		'start_date2'     => ( '' !== $start_date2 && '0' !== $start_date2 ) ? $start_date2 : '',
		'end_date2'       => ( '' !== $end_date2 && '0' !== $end_date2 ) ? $end_date2 : '',
		'range'           => $text( 'range' ),
		'product_id'      => isset( $_POST['product'] ) ? (int) $_POST['product'] : 0,
		'country'         => $text( 'country' ),
		'billing_country' => $text( 'billing_country' ),
		'location_id'     => isset( $_POST['location_id'] ) ? (int) $_POST['location_id'] : 0,
	);
	// phpcs:enable
	if ( '0' === $args['country'] ) {
		$args['country'] = '';
	}
	if ( '0' === $args['billing_country'] ) {
		$args['billing_country'] = '';
	}
	return $args;
}

add_action( 'wp_ajax_ec_admin_get_updated_stat_list', 'ec_admin_get_updated_stat_list' );
function ec_admin_get_updated_stat_list( ){
	if ( ! wp_easycart_admin_verification()->verify_access( 'wp-easycart-updated-stats' ) ) {
		return false;
	}

	$a = ec_admin_report_request_args();
	$stats = ( object ) array (
		'sales'  => wp_easycart_admin()->get_stats( 'sales', $a['start_date'], $a['end_date'], $a['start_date2'], $a['end_date2'], $a['range'], $a['product_id'], $a['country'], $a['billing_country'], $a['location_id'] ),
		'items'  => wp_easycart_admin()->get_stats( 'items', $a['start_date'], $a['end_date'], $a['start_date2'], $a['end_date2'], $a['range'], $a['product_id'], $a['country'], $a['billing_country'], $a['location_id'] ),
		'carts'  => wp_easycart_admin()->get_stats( 'carts', $a['start_date'], $a['end_date'], $a['start_date2'], $a['end_date2'], $a['range'], $a['product_id'], $a['country'], $a['billing_country'], $a['location_id'] ),
		'single' => wp_easycart_admin()->get_single_stats( $a['start_date'], $a['end_date'], $a['start_date2'], $a['end_date2'], $a['product_id'], $a['country'], $a['billing_country'], $a['location_id'] ),
	);
	echo json_encode( $stats );
	die( );
}

/**
 * Resumable CSV export. First call ( no job ) creates the job and runs the first batch; every call returns
 * { done, processed, total, next: { job, phase, last_order_id } } and, once done, `reports` ( report1,
 * report2, reporttax ) exactly as the one-shot handler used to. The stored cursor is authoritative.
 *
 * @since 6.0.0 batched; nonce / capability check unchanged.
 */
add_action( 'wp_ajax_ec_admin_create_report_export', 'ec_admin_create_report_export' );
function ec_admin_create_report_export( ){
	if ( ! wp_easycart_admin_verification()->verify_access( 'wp-easycart-export-stats' ) ) {
		wp_send_json( array( 'error' => 'permission' ) );
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by verify_access() above.
	$token = isset( $_POST['job'] ) ? preg_replace( '/[^a-f0-9]/', '', sanitize_key( wp_unslash( $_POST['job'] ) ) ) : '';
	if ( '' === $token ) {
		$token = wp_easycart_admin()->report_job_create( ec_admin_report_request_args() );
		if ( ! $token ) {
			wp_send_json( array( 'error' => 'file', 'message' => __( 'The uploads folder is not writable, so the report file could not be created.', 'wp-easycart' ) ) );
		}
	}
	$result = wp_easycart_admin()->report_job_step( $token );
	if ( ! $result ) {
		wp_send_json( array( 'error' => 'job', 'message' => __( 'This export has expired. Please start it again.', 'wp-easycart' ) ) );
	}
	wp_send_json( $result );
}

add_action( 'wp_ajax_ec_admin_ajax_save_terms_accepted', 'ec_admin_ajax_save_terms_accepted' );
function ec_admin_ajax_save_terms_accepted( ){
	if ( ! wp_easycart_admin_verification()->verify_access( 'wp-easycart-terms-accept' ) ) {
		return false;
	}

	update_option( 'ec_option_wpeasycart_terms_accepted', 1 );
	die( );
}

add_action( 'wp_ajax_wp_easycart_ecv2_save_order_date', 'wp_easycart_ecv2_save_order_date' );
function wp_easycart_ecv2_save_order_date() {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_orders' ) ) {
		wp_send_json_error( array( 'message' => 'permission' ) );
	}
	check_ajax_referer( 'wp_easycart_ecv2_order_date', 'nonce' );
	global $wpdb;
	$order_id = ( isset( $_POST['order_id'] ) ) ? (int) $_POST['order_id'] : 0;
	$date_raw = ( isset( $_POST['order_date'] ) ) ? sanitize_text_field( wp_unslash( $_POST['order_date'] ) ) : '';
	$time_raw = ( isset( $_POST['order_time'] ) ) ? sanitize_text_field( wp_unslash( $_POST['order_time'] ) ) : '';
	$local_ts = strtotime( $date_raw . ' ' . $time_raw );
	if ( ! $order_id || false === $local_ts ) {
		wp_send_json_error( array( 'message' => 'invalid' ) );
	}
	$now_server = $wpdb->get_var( 'SELECT NOW() AS the_time' );
	$storage_offset = strtotime( $now_server ) - time();
	$local_offset = get_option( 'gmt_offset' ) * 60 * 60;
	$date_diff = $local_offset - $storage_offset;
	$storage_ts = $local_ts - $date_diff;
	$wpdb->update( 'ec_order', array( 'order_date' => date( 'Y-m-d H:i:s', $storage_ts ) ), array( 'order_id' => $order_id ), array( '%s' ), array( '%d' ) );
	do_action( 'wp_easycart_admin_order_date_updated', $order_id, $storage_ts );
	wp_send_json_success( array( 'display' => date( 'M j Y ' . get_option( 'time_format' ), $local_ts ) ) );
}
