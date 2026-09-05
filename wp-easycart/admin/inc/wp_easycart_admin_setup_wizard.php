<?php
/**
 * WP EasyCart — Setup Wizard (v2)
 *
 * Four decision steps + a launch checklist, rendered inside the v2 admin shell.
 *
 *   1  Location & currency      -> process_location_submit()
 *   2  Payments & checkout      -> process_payments_submit()
 *   3  Shipping                 -> process_shipping_submit()
 *   4  Finish up                -> process_finish_submit()   ( marks wizard done )
 *   5  Done / launch checklist
 *
 * The three "recommended" technical settings ( cache compatibility, forced SSL,
 * SEO-friendly links ) are no longer wizard questions. They get defaults at
 * install ( see ensure_recommended_defaults() ), are shown as status on step 4,
 * and are re-checked continuously ( sync_recommended_settings() ) so they turn
 * on by themselves once the host environment allows it.
 *
 * Public helpers other screens can use:
 *   wp_easycart_admin_setup_wizard()->get_recommended_status()   // Store Status health rows
 *   wp_easycart_admin_setup_wizard()->get_checklist()            // launch checklist items
 *   wp_easycart_admin_setup_wizard()->count_checklist_remaining()// sidebar badge
 *   wp_easycart_admin_setup_wizard()->render_checklist( $ctx )   // shared partial
 *
 * @since 5.x.x
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_setup_wizard' ) ) :

	final class wp_easycart_admin_setup_wizard {

		protected static $_instance = null;

		/** Current step ( 1..5 ). 0 is treated as 1. */
		public $step = 0;

		const STEP_LOCATION = 1;
		const STEP_PAYMENTS = 2;
		const STEP_SHIPPING = 3;
		const STEP_FINISH   = 4;
		const STEP_DONE     = 5;

		const OPT_CHECKLIST = 'ec_option_setup_checklist';
		const OPT_OVERRIDES = 'ec_option_recommended_overrides';
		const AJAX_NONCE    = 'wp-easycart-wizard-ajax';

		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		public function __construct() {
			/* Display */
			add_action( 'wp_easycart_admin_wizard_navigation', array( $this, 'load_navigation' ) );
			add_action( 'wp_easycart_admin_wizard_content', array( $this, 'load_content' ) );

			/* Form actions */
			add_action( 'wp_easycart_process_get_form_action', array( $this, 'process_skip_wizard' ) );
			add_action( 'wp_easycart_process_post_form_action', array( $this, 'process_location_submit' ) );
			add_action( 'wp_easycart_process_post_form_action', array( $this, 'process_payments_submit' ) );
			add_action( 'wp_easycart_process_post_form_action', array( $this, 'process_shipping_submit' ) );
			add_action( 'wp_easycart_process_post_form_action', array( $this, 'process_finish_submit' ) );

			/* Assets */
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

			/* Keep recommended settings in step with the environment ( cheap, admin only ) */
			add_action( 'admin_init', array( $this, 'sync_recommended_settings' ) );

			/* AJAX */
			add_action( 'wp_ajax_ec_admin_ajax_wizard_add_menu_items', array( $this, 'ajax_add_menu_items' ) );
			add_action( 'wp_ajax_ec_admin_ajax_wizard_create_page', array( $this, 'ajax_create_page' ) );
			add_action( 'wp_ajax_ec_admin_ajax_wizard_send_test_email', array( $this, 'ajax_send_test_email' ) );
			add_action( 'wp_ajax_ec_admin_ajax_wizard_checklist', array( $this, 'ajax_checklist' ) );
		}

		/* =====================================================================
		   ROUTING
		   ===================================================================== */

		public function is_wizard_screen() {
			if ( ! isset( $_GET['page'] ) || 'wp-easycart-settings' != $_GET['page'] ) {
				return false;
			}
			if ( isset( $_GET['subpage'] ) && 'setup-wizard' == $_GET['subpage'] ) {
				return true;
			}
			if ( ! get_option( 'ec_option_setup_wizard_done' ) && ( ! isset( $_GET['subpage'] ) || 'initial-setup' == $_GET['subpage'] ) ) {
				/* Mirrors load_settings(): a store that already has products is auto-marked done. */
				global $wpdb;
				return ! $wpdb->get_var( 'SELECT product_id FROM ec_product LIMIT 1' );
			}
			return false;
		}

		/** Store Status shows the recommended-settings health rows and the launch checklist. */
		public function is_store_status_screen() {
			return isset( $_GET['page'] ) && 'wp-easycart-status' == $_GET['page'] && ( ! isset( $_GET['subpage'] ) || 'store-status' == $_GET['subpage'] );
		}

		public function load_setup_wizard() {
			self::ensure_recommended_defaults();

			$this->step = (int) get_option( 'ec_option_setup_wizard_step' );
			if ( isset( $_GET['step'] ) ) {
				$this->step = (int) $_GET['step'];
				/* Legacy compatibility: gateway onboarding ( wp_easycart_admin_payments.php,
				   PayPal/Square return handlers ) still redirects to the old Payments step ( 3 ).
				   When those flags are present, land on the new Payments step instead. */
				if ( 3 == $this->step && ( isset( $_GET['success'] ) || isset( $_GET['error'] ) || isset( $_GET['wpeasycart_paypal_onboard'] ) ) ) {
					$this->step = self::STEP_PAYMENTS;
				}
			}
			if ( $this->step < self::STEP_LOCATION ) {
				$this->step = self::STEP_LOCATION;
			}
			if ( $this->step > self::STEP_DONE ) {
				$this->step = self::STEP_DONE;
			}
			update_option( 'ec_option_setup_wizard_step', $this->step );

			include( EC_PLUGIN_DIRECTORY . '/admin/template/settings/wizard/shell.php' );
		}

		public function load_navigation() {
			include( EC_PLUGIN_DIRECTORY . '/admin/template/settings/wizard/navigation.php' );
		}

		public function load_content() {
			$map = array(
				self::STEP_LOCATION => 'location.php',
				self::STEP_PAYMENTS => 'payments.php',
				self::STEP_SHIPPING => 'shipping.php',
				self::STEP_FINISH   => 'finish.php',
				self::STEP_DONE     => 'complete.php',
			);
			$file = isset( $map[ $this->step ] ) ? $map[ $this->step ] : 'location.php';
			include( EC_PLUGIN_DIRECTORY . '/admin/template/settings/wizard/' . $file );
		}

		public function step_url( $step ) {
			return admin_url( 'admin.php?page=wp-easycart-settings&subpage=setup-wizard&step=' . (int) $step );
		}

		public function skip_url() {
			return admin_url( 'admin.php?page=wp-easycart-settings&ec_admin_form_action=skip-wizard&wp_easycart_nonce=' . wp_create_nonce( 'wp-easycart-skip-wizard' ) );
		}

		/** Where "Save & exit" goes: the Settings landing page once done, else stay resumable. */
		public function exit_url() {
			return admin_url( 'admin.php?page=wp-easycart-products&subpage=products' );
		}

		public function get_steps() {
			return array(
				self::STEP_LOCATION => array(
					'label' => __( 'Location & currency', 'wp-easycart' ),
					'sub'   => __( 'Where you sell, what you charge in', 'wp-easycart' ),
				),
				self::STEP_PAYMENTS => array(
					'label' => __( 'Payments & checkout', 'wp-easycart' ),
					'sub'   => __( 'How customers pay you', 'wp-easycart' ),
				),
				self::STEP_SHIPPING => array(
					'label' => __( 'Shipping', 'wp-easycart' ),
					'sub'   => __( 'Starter rates for your region', 'wp-easycart' ),
				),
				self::STEP_FINISH => array(
					'label' => __( 'Finish up', 'wp-easycart' ),
					'sub'   => __( 'Pages, policies, notifications', 'wp-easycart' ),
				),
			);
		}

		/** Highest step the merchant has completed ( used for the stepper checkmarks ). */
		public function completed_through() {
			if ( get_option( 'ec_option_setup_wizard_done' ) ) {
				return self::STEP_FINISH;
			}
			$max = (int) get_option( 'ec_option_setup_wizard_completed', 0 );
			return $max;
		}

		private function mark_completed( $step ) {
			$max = (int) get_option( 'ec_option_setup_wizard_completed', 0 );
			if ( $step > $max ) {
				update_option( 'ec_option_setup_wizard_completed', (int) $step );
			}
		}

		/** Shared button bar for steps 1-4. */
		public function render_footer( $step, $primary_label = '' ) {
			if ( '' == $primary_label ) {
				$primary_label = __( 'Continue', 'wp-easycart' );
			}
			echo '<div class="ecwz-foot">';
			if ( $step > self::STEP_LOCATION ) {
				echo '<a class="ecwz-btn ecwz-btn-ghost" href="' . esc_url( $this->step_url( $step - 1 ) ) . '">&larr; ' . esc_html__( 'Back', 'wp-easycart' ) . '</a>';
			}
			/* translators: 1: current step, 2: total steps */
			echo '<span class="ecwz-hint">' . esc_html( sprintf( __( 'Step %1$d of %2$d', 'wp-easycart' ), $step, self::STEP_FINISH ) ) . '</span>';
			echo '<span class="ecwz-grow"></span>';
			echo '<a class="ecwz-btn ecwz-btn-ghost" href="' . esc_url( $this->exit_url() ) . '">' . esc_html__( 'Save & exit', 'wp-easycart' ) . '</a>';
			echo '<button type="submit" class="ecwz-btn ecwz-btn-primary">' . esc_html( $primary_label ) . '</button>';
			echo '</div>';
		}

		/* =====================================================================
		   RECOMMENDED SETTINGS — defaults, environment, status, auto-sync
		   ===================================================================== */

		/**
		 * Recommended settings defaults. Delegates to ec_wpoptionset::apply_recommended_defaults(),
		 * the single source of truth ( also called from ec_db_manager::install_db() ). It is
		 * guarded by ec_option_recommended_defaults_version so it runs once per install, and
		 * it only overwrites the option-set seeds on stores where the merchant has never
		 * reached the old wizard's page-setup submit.
		 */
		public static function ensure_recommended_defaults() {
			if ( class_exists( 'ec_wpoptionset' ) ) {
				return ec_wpoptionset::apply_recommended_defaults();
			}
			return false;
		}

		/** The option set seeds several fields with placeholder text; treat those as empty. */
		public static function is_placeholder( $value ) {
			$placeholders = array( '', 'youremail@url.com', 'http://yoursite.com/termsandconditions', 'http://yoursite.com/privacypolicy', 'UA-XXXXXXX-X' );
			return in_array( trim( (string) $value ), $placeholders, true );
		}

		/** get_option() that returns '' for unset or placeholder values. */
		public static function real_option( $name ) {
			$v = get_option( $name );
			return self::is_placeholder( $v ) ? '' : $v;
		}

		/**
		 * What the host environment supports right now.
		 * @return array { https:bool, permalinks:bool, permalink_label:string, caching:string|null }
		 */
		public static function get_environment() {
			$scheme = wp_parse_url( home_url(), PHP_URL_SCHEME );
			$structure = (string) get_option( 'permalink_structure' );
			return array(
				'https'           => ( 'https' === $scheme ),
				'host'            => wp_parse_url( home_url(), PHP_URL_HOST ),
				'permalinks'      => ( '' !== $structure ),
				'permalink_label' => self::permalink_label( $structure ),
				'caching'         => self::detect_caching_plugin(),
			);
		}

		private static function permalink_label( $structure ) {
			if ( '' === $structure ) {
				return __( 'Plain', 'wp-easycart' );
			}
			$known = array(
				'/%year%/%monthnum%/%day%/%postname%/' => __( 'Day and name', 'wp-easycart' ),
				'/%year%/%monthnum%/%postname%/'       => __( 'Month and name', 'wp-easycart' ),
				'/archives/%post_id%'                  => __( 'Numeric', 'wp-easycart' ),
				'/%postname%/'                         => __( 'Post name', 'wp-easycart' ),
			);
			return isset( $known[ $structure ] ) ? $known[ $structure ] : __( 'Custom', 'wp-easycart' );
		}

		/** Returns the caching plugin / host layer name, or null. */
		public static function detect_caching_plugin() {
			$checks = array(
				'WP Rocket'             => array( 'const' => 'WP_ROCKET_VERSION' ),
				'W3 Total Cache'        => array( 'const' => 'W3TC' ),
				'LiteSpeed Cache'       => array( 'const' => 'LSCWP_V' ),
				'WP Super Cache'        => array( 'const' => 'WPCACHEHOME' ),
				'WP Fastest Cache'      => array( 'class' => 'WpFastestCache' ),
				'Cache Enabler'         => array( 'const' => 'CACHE_ENABLER_VERSION' ),
				'Hummingbird'           => array( 'const' => 'WPHB_VERSION' ),
				'Breeze'                => array( 'const' => 'BREEZE_VERSION' ),
				'Speed Optimizer (SiteGround)' => array( 'class' => 'SiteGround_Optimizer\Loader\Loader' ),
				'WP Engine cache'       => array( 'const' => 'WPE_APIKEY' ),
				'Kinsta cache'          => array( 'const' => 'KINSTAMU_VERSION' ),
				'Cloudflare APO'        => array( 'const' => 'CLOUDFLARE_PLUGIN_DIR' ),
				'NitroPack'             => array( 'const' => 'NITROPACK_VERSION' ),
				'Autoptimize'           => array( 'const' => 'AUTOPTIMIZE_PLUGIN_VERSION' ),
			);
			foreach ( $checks as $label => $test ) {
				if ( isset( $test['const'] ) && defined( $test['const'] ) ) {
					return $label;
				}
				if ( isset( $test['class'] ) && class_exists( $test['class'] ) ) {
					return $label;
				}
			}
			return apply_filters( 'wp_easycart_detected_caching_plugin', null );
		}

		/** Keys the merchant explicitly changed away from the recommendation. */
		public function get_overrides() {
			$o = get_option( self::OPT_OVERRIDES, array() );
			return is_array( $o ) ? $o : array();
		}

		private function set_override( $key, $on ) {
			$o = $this->get_overrides();
			if ( $on ) {
				$o[ $key ] = 1;
			} else {
				unset( $o[ $key ] );
			}
			update_option( self::OPT_OVERRIDES, $o );
		}

		/**
		 * Status rows for the three recommended settings.
		 * state: ok | warn ( env blocks it ) | off ( merchant turned it off )
		 * locked: true when the environment prevents enabling it.
		 */
		public function get_recommended_status() {
			$env       = self::get_environment();
			$overrides = $this->get_overrides();

			$cache_on = (bool) get_option( 'ec_option_cache_prevent' );
			$ssl_on   = (bool) get_option( 'ec_option_load_ssl' );
			$seo_on   = ! get_option( 'ec_option_use_old_linking_style' );

			$rows = array();

			/* Cache compatibility */
			if ( $env['caching'] ) {
				/* translators: %s: caching plugin name */
				$msg = $cache_on
					? sprintf( __( '%s detected. Cart and account are loaded dynamically so cached pages never show one customer\'s cart to another.', 'wp-easycart' ), '<strong>' . esc_html( $env['caching'] ) . '</strong>' )
					: sprintf( __( '%s detected but cache compatibility is off. Customers may see each other\'s carts. Turn this back on unless your host has told you otherwise.', 'wp-easycart' ), '<strong>' . esc_html( $env['caching'] ) . '</strong>' );
			} else {
				$msg = $cache_on
					? __( 'No caching plugin detected. Cart and account load dynamically anyway, so adding one later is safe.', 'wp-easycart' )
					: __( 'Off. Safe only while you have no page caching; turn on before adding a caching plugin or host-level cache.', 'wp-easycart' );
			}
			$rows['cache'] = array(
				'key'      => 'cache',
				'label'    => __( 'Cache compatibility', 'wp-easycart' ),
				'enabled'  => $cache_on,
				'state'    => $cache_on ? 'ok' : 'off',
				'severity' => ( ! $cache_on && $env['caching'] ) ? 'error' : 'warning',
				'locked'   => false,
				'message'  => $msg,
				'option'   => 'ec_option_cache_prevent',
			);

			/* Secure checkout */
			if ( $env['https'] ) {
				$msg = $ssl_on
					/* translators: %s: hostname */
					? sprintf( __( 'Valid certificate detected for %s. Store pages are served over https.', 'wp-easycart' ), '<strong>' . esc_html( $env['host'] ) . '</strong>' )
					: __( 'Your site supports https but the store is not forcing it. Turn on so checkout is always secure.', 'wp-easycart' );
				$state = $ssl_on ? 'ok' : 'off';
				$locked = false;
			} else {
				$msg = __( 'Your site is served over http. Forcing SSL now would break every store page, so it is off. Ask your host for a certificate; EasyCart enables this automatically once https works.', 'wp-easycart' );
				$state = 'warn';
				$locked = true;
			}
			$rows['ssl'] = array(
				'key'      => 'ssl',
				'label'    => __( 'Secure checkout (SSL)', 'wp-easycart' ),
				'enabled'  => $ssl_on,
				'state'    => $state,
				'severity' => 'warning',
				'locked'   => $locked,
				'message'  => $msg,
				'option'   => 'ec_option_load_ssl',
				'fix_url'  => '',
			);

			/* SEO-friendly links */
			if ( $env['permalinks'] ) {
				$msg = $seo_on
					/* translators: %s: permalink setting label */
					? sprintf( __( 'WordPress permalinks are set to %s. Products use /store/my-widget/.', 'wp-easycart' ), '<strong>' . esc_html( $env['permalink_label'] ) . '</strong>' )
					: __( 'Off. Product links use /store/?model_number=XYZ. Turn on for search-friendly URLs.', 'wp-easycart' );
				$state = $seo_on ? 'ok' : 'off';
				$locked = false;
			} else {
				$msg = __( 'WordPress permalinks are set to Plain, so pretty product URLs can\'t work yet. Change to "Post name" under Settings › Permalinks and this turns on by itself.', 'wp-easycart' );
				$state = 'warn';
				$locked = true;
			}
			$rows['seo'] = array(
				'key'      => 'seo',
				'label'    => __( 'SEO-friendly product links', 'wp-easycart' ),
				'enabled'  => $seo_on,
				'state'    => $state,
				'severity' => 'warning',
				'locked'   => $locked,
				'message'  => $msg,
				'option'   => 'ec_option_use_old_linking_style',
				'fix_url'  => admin_url( 'options-permalink.php' ),
			);

			foreach ( $rows as $k => $r ) {
				$rows[ $k ]['overridden'] = isset( $overrides[ $k ] );
			}
			return apply_filters( 'wp_easycart_recommended_settings_status', $rows, $env );
		}

		/** True when all three are on. */
		public function recommended_all_ok() {
			foreach ( $this->get_recommended_status() as $r ) {
				if ( 'ok' !== $r['state'] ) {
					return false;
				}
			}
			return true;
		}

		/**
		 * Auto-enable SSL / SEO links once the environment allows it, unless the
		 * merchant explicitly turned that setting off. Runs on EasyCart admin
		 * screens only; three get_option() calls, no queries.
		 */
		public function sync_recommended_settings() {
			if ( ! is_admin() || ! isset( $_GET['page'] ) || 0 !== strpos( sanitize_key( $_GET['page'] ), 'wp-easycart' ) ) {
				return;
			}
			if ( ! get_option( 'ec_option_recommended_defaults_version' ) ) {
				/* Defaults have not been applied on this store yet ( happens on first wizard
				   load or the next install_db() run ). Don't auto-adjust before that. */
				return;
			}
			$env = self::get_environment();
			$ov  = $this->get_overrides();
			if ( $env['https'] && ! get_option( 'ec_option_load_ssl' ) && ! isset( $ov['ssl'] ) ) {
				update_option( 'ec_option_load_ssl', 1 );
			}
			if ( ! $env['https'] && get_option( 'ec_option_load_ssl' ) ) {
				/* Certificate went away: never leave a redirect loop in place. */
				update_option( 'ec_option_load_ssl', 0 );
			}
			if ( $env['permalinks'] && get_option( 'ec_option_use_old_linking_style' ) && ! isset( $ov['seo'] ) ) {
				update_option( 'ec_option_use_old_linking_style', 0 );
			}
			if ( ! $env['permalinks'] && ! get_option( 'ec_option_use_old_linking_style' ) ) {
				update_option( 'ec_option_use_old_linking_style', 1 );
			}
		}

		/* =====================================================================
		   LAUNCH CHECKLIST
		   ===================================================================== */

		public function get_checklist_state() {
			$s = get_option( self::OPT_CHECKLIST, array() );
			return is_array( $s ) ? $s : array();
		}

		public function set_checklist_state( $key, $value ) {
			$s = $this->get_checklist_state();
			$s[ $key ] = $value;
			update_option( self::OPT_CHECKLIST, $s );
		}

		/** Are the three store pages in any registered nav menu? */
		public function store_pages_in_menu() {
			$ids = array_filter( array( (int) get_option( 'ec_option_storepage' ), (int) get_option( 'ec_option_cartpage' ), (int) get_option( 'ec_option_accountpage' ) ) );
			if ( empty( $ids ) ) {
				return false;
			}
			$menus = wp_get_nav_menus();
			foreach ( $menus as $menu ) {
				$items = wp_get_nav_menu_items( $menu->term_id );
				if ( ! $items ) {
					continue;
				}
				$found = array();
				foreach ( $items as $item ) {
					if ( 'page' === $item->object && in_array( (int) $item->object_id, $ids, true ) ) {
						$found[ (int) $item->object_id ] = true;
					}
				}
				if ( count( $found ) === count( $ids ) ) {
					return $menu->name;
				}
			}
			return false;
		}

		/**
		 * Launch checklist. Each item: key, label, sub, done, dismissed, action_label, action_url|action_js, optional.
		 */
		public function get_checklist() {
			global $wpdb;
			$state = $this->get_checklist_state();

			$has_product = (bool) $wpdb->get_var( 'SELECT product_id FROM ec_product WHERE is_demo_item = 0 LIMIT 1' );
			$has_order   = (bool) $wpdb->get_var( 'SELECT order_id FROM ec_order WHERE is_demo_item = 0 LIMIT 1' );
			$menu_name   = $this->store_pages_in_menu();

			$items = array(
				'product' => array(
					'label'        => __( 'Create your first product', 'wp-easycart' ),
					'sub'          => __( 'Name, price and a photo is all it needs to go live', 'wp-easycart' ),
					'done'         => $has_product,
					'featured'     => true,
					'action_label' => __( 'Create product', 'wp-easycart' ),
					'action_url'   => admin_url( 'admin.php?page=wp-easycart-products&subpage=products&ec_admin_form_action=add-new' ),
					'action_js'    => "wp_easycart_admin_open_slideout( 'new_product_box' ); return false;",
				),
				'order' => array(
					'label'        => __( 'Place a test order', 'wp-easycart' ),
					'sub'          => __( 'Walk through checkout as a customer', 'wp-easycart' ),
					'done'         => $has_order,
					'action_label' => __( 'Open store', 'wp-easycart' ),
					'action_url'   => wp_easycart_admin()->store_page,
					'target'       => '_blank',
				),
				'email' => array(
					'label'        => __( 'Confirm order emails arrive', 'wp-easycart' ),
					'sub'          => __( 'Send a test and check your inbox and spam folder', 'wp-easycart' ),
					'done'         => ! empty( $state['email_tested'] ),
					'action_label' => __( 'Send test', 'wp-easycart' ),
					'action_js'    => 'wpEasyCartWizard.sendTestEmail( this ); return false;',
					'action_url'   => admin_url( 'admin.php?page=wp-easycart-settings&subpage=email-settings' ),
				),
				'menu' => array(
					'label'        => __( 'Add store pages to your menu', 'wp-easycart' ),
					'sub'          => $menu_name ? sprintf( __( 'Store, Cart and My Account are in "%s"', 'wp-easycart' ), $menu_name ) : __( 'Store, Cart and My Account', 'wp-easycart' ),
					'done'         => (bool) $menu_name,
					'action_label' => __( 'Add to menu', 'wp-easycart' ),
					'action_js'    => 'wpEasyCartWizard.addMenuItems( this ); return false;',
					'action_url'   => admin_url( 'nav-menus.php' ),
				),
				'legal' => array(
					'label'        => __( 'Publish terms & privacy pages', 'wp-easycart' ),
					'sub'          => __( 'Linked from checkout', 'wp-easycart' ),
					'done'         => ( '' != self::real_option( 'ec_option_terms_link' ) && '' != self::real_option( 'ec_option_privacy_link' ) ),
					'action_label' => __( 'Review', 'wp-easycart' ),
					'action_url'   => $this->step_url( self::STEP_FINISH ) . '#ecwz-policies',
				),
				'rec' => array(
					'label'        => __( 'Recommended settings healthy', 'wp-easycart' ),
					'sub'          => $this->recommended_all_ok() ? __( 'Cache compatibility, SSL, SEO links', 'wp-easycart' ) : __( 'One or more settings need attention', 'wp-easycart' ),
					'done'         => $this->recommended_all_ok(),
					'action_label' => __( 'Review', 'wp-easycart' ),
					'action_url'   => $this->step_url( self::STEP_FINISH ) . '#ecwz-recommended',
				),
				'ga' => array(
					'label'        => __( 'Connect Google Analytics', 'wp-easycart' ),
					'sub'          => __( 'Optional. Track visits and conversions', 'wp-easycart' ),
					'done'         => ( '' != self::real_option( 'ec_option_googleanalyticsid' ) ),
					'optional'     => true,
					'action_label' => __( 'Set up', 'wp-easycart' ),
					'action_url'   => admin_url( 'admin.php?page=wp-easycart-settings&subpage=third-party' ),
				),
			);

			foreach ( $items as $k => $item ) {
				$items[ $k ]['key']       = $k;
				$items[ $k ]['dismissed'] = ! empty( $state[ 'dismissed_' . $k ] );
			}
			return apply_filters( 'wp_easycart_setup_checklist', $items );
		}

		/** Items neither done nor dismissed. Use for the Store Status sidebar badge. */
		public function count_checklist_remaining() {
			if ( ! get_option( 'ec_option_setup_wizard_done' ) ) {
				return 0;
			}
			$n = 0;
			foreach ( $this->get_checklist() as $item ) {
				if ( ! $item['done'] && ! $item['dismissed'] ) {
					$n++;
				}
			}
			return $n;
		}

		/** Shared partial. $context = 'wizard' | 'status' */
		public function render_checklist( $context = 'wizard' ) {
			$items = $this->get_checklist();
			include( EC_PLUGIN_DIRECTORY . '/admin/template/settings/wizard/checklist.php' );
		}

		/* =====================================================================
		   PRESETS & GATEWAY STATE ( shared by templates and handlers )
		   ===================================================================== */

		public function get_shipping_presets() {
			return array(
				'static' => array(
					'label' => __( 'Flat rates', 'wp-easycart' ),
					'sub'   => __( 'Recommended to start', 'wp-easycart' ),
					'desc'  => __( 'Customers choose a service level. Simple and predictable.', 'wp-easycart' ),
					'rates' => array(
						array( 'shipping_label' => __( 'Standard Shipping 7-10 Days', 'wp-easycart' ), 'shipping_order' => 1, 'shipping_rate' => '7.99' ),
						array( 'shipping_label' => __( 'Priority 3 Day Shipping', 'wp-easycart' ), 'shipping_order' => 2, 'shipping_rate' => '14.99' ),
						array( 'shipping_label' => __( 'Priority 2 Day Shipping', 'wp-easycart' ), 'shipping_order' => 3, 'shipping_rate' => '19.99' ),
					),
				),
				'price' => array(
					'label' => __( 'By cart total', 'wp-easycart' ),
					'sub'   => __( 'Cheaper shipping on small orders', 'wp-easycart' ),
					'desc'  => __( 'One rate per order, based on the subtotal.', 'wp-easycart' ),
					'rates' => array(
						array( 'trigger_rate' => '0.00', 'shipping_rate' => '7.99' ),
						array( 'trigger_rate' => '20.00', 'shipping_rate' => '9.99' ),
						array( 'trigger_rate' => '50.00', 'shipping_rate' => '12.99' ),
						array( 'trigger_rate' => '100.00', 'shipping_rate' => '19.99' ),
						array( 'trigger_rate' => '500.00', 'shipping_rate' => '29.99' ),
					),
				),
				'weight' => array(
					'label' => __( 'By weight', 'wp-easycart' ),
					'sub'   => __( 'Best for heavy or bulky products', 'wp-easycart' ),
					'desc'  => __( 'One rate per order, based on total weight.', 'wp-easycart' ),
					'rates' => array(
						array( 'trigger_rate' => '0.00', 'shipping_rate' => '7.99' ),
						array( 'trigger_rate' => '20.00', 'shipping_rate' => '9.99' ),
						array( 'trigger_rate' => '50.00', 'shipping_rate' => '12.99' ),
						array( 'trigger_rate' => '100.00', 'shipping_rate' => '19.99' ),
						array( 'trigger_rate' => '500.00', 'shipping_rate' => '29.99' ),
					),
				),
			);
		}

		/**
		 * Existing ( non-demo ) shipping rate rows per calculation type.
		 * @return array { static:int, price:int, weight:int }
		 */
		public function get_shipping_rate_counts() {
			global $wpdb;
			$row = $wpdb->get_row( 'SELECT COALESCE( SUM( is_method_based ), 0 ) AS m, COALESCE( SUM( is_price_based ), 0 ) AS p, COALESCE( SUM( is_weight_based ), 0 ) AS w FROM ec_shippingrate WHERE is_demo_item = 0' );
			return array(
				'static' => $row ? (int) $row->m : 0,
				'price'  => $row ? (int) $row->p : 0,
				'weight' => $row ? (int) $row->w : 0,
			);
		}

		/** Which gateways are already connected ( set by onboarding return handlers ). */
		public function get_gateway_state() {
			$method = get_option( 'ec_option_payment_process_method' );
			$ok     = ! $this->show_terms_gate(); /* Connect gateways don't count as usable until terms are accepted */
			return array(
				'manual' => (bool) get_option( 'ec_option_use_direct_deposit' ),
				'paypal' => $ok && ( 'paypal' == get_option( 'ec_option_payment_third_party' ) ),
				'stripe' => $ok && ( 'stripe_connect' == $method ),
				'square' => $ok && ( 'square' == $method || '' != get_option( 'ec_option_square_access_token' ) ),
			);
		}

		/**
		 * Undo a Connect gateway selection made without accepted terms ( e.g. an onboarding
		 * return handler set the option before the merchant ticked the box ). Leaves
		 * manual payments and any PRO gateway untouched.
		 */
		private function clear_connect_gateways() {
			if ( 'paypal' == get_option( 'ec_option_payment_third_party' ) ) {
				update_option( 'ec_option_payment_third_party', '' );
			}
			$method = get_option( 'ec_option_payment_process_method' );
			if ( 'stripe_connect' == $method || 'square' == $method ) {
				update_option( 'ec_option_payment_process_method', '' );
			}
		}

		/** Free edition shows terms + Connect fees; PRO filters these off. */
		public function show_terms_gate() {
			return ! get_option( 'ec_option_wpeasycart_terms_accepted' ) && '' != apply_filters( 'wp_easycart_admin_lock_icon', 'true' );
		}

		public function show_upsell() {
			return '' != apply_filters( 'wp_easycart_trial_start_content', 'true' );
		}

		/* =====================================================================
		   FORM HANDLERS
		   ===================================================================== */

		public function process_skip_wizard() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_settings' ) ) {
				return false;
			}
			if ( isset( $_GET['ec_admin_form_action'] ) && 'skip-wizard' == $_GET['ec_admin_form_action'] ) {
				if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-skip-wizard' ) ) {
					/* Skipping never un-applies anything: recommended defaults stay, pages stay. */
					self::ensure_recommended_defaults();
					if ( get_option( 'ec_option_allow_tracking' ) == '3' ) {
						update_option( 'ec_option_allow_tracking', 0 );
					}
					update_option( 'ec_option_setup_wizard_done', 1 );
					wp_redirect( $this->step_url( self::STEP_DONE ) );
					exit;
				}
			}
		}

		public function process_location_submit() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_settings' ) ) {
				return false;
			}
			if ( isset( $_POST['ec_admin_form_action'] ) && 'process-wizard-location' == $_POST['ec_admin_form_action'] ) {
				if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-process-wizard-location' ) ) {
					$countries = array(
						'US' => __( 'United States (US)', 'wp-easycart' ),
						'CA' => __( 'Canada', 'wp-easycart' ),
						'GB' => __( 'United Kingdom (UK)', 'wp-easycart' ),
						'AU' => __( 'Australia', 'wp-easycart' ),
						'AF' => __( 'Afghanistan', 'wp-easycart' ),
						'AX' => __( '&#197;land Islands', 'wp-easycart' ),
						'AL' => __( 'Albania', 'wp-easycart' ),
						'DZ' => __( 'Algeria', 'wp-easycart' ),
						'AS' => __( 'American Samoa', 'wp-easycart' ),
						'AD' => __( 'Andorra', 'wp-easycart' ),
						'AO' => __( 'Angola', 'wp-easycart' ),
						'AI' => __( 'Anguilla', 'wp-easycart' ),
						'AQ' => __( 'Antarctica', 'wp-easycart' ),
						'AG' => __( 'Antigua and Barbuda', 'wp-easycart' ),
						'AR' => __( 'Argentina', 'wp-easycart' ),
						'AM' => __( 'Armenia', 'wp-easycart' ),
						'AW' => __( 'Aruba', 'wp-easycart' ),
						'AU' => __( 'Australia', 'wp-easycart' ),
						'AT' => __( 'Austria', 'wp-easycart' ),
						'AZ' => __( 'Azerbaijan', 'wp-easycart' ),
						'BS' => __( 'Bahamas', 'wp-easycart' ),
						'BH' => __( 'Bahrain', 'wp-easycart' ),
						'BD' => __( 'Bangladesh', 'wp-easycart' ),
						'BB' => __( 'Barbados', 'wp-easycart' ),
						'BY' => __( 'Belarus', 'wp-easycart' ),
						'BE' => __( 'Belgium', 'wp-easycart' ),
						'PW' => __( 'Belau', 'wp-easycart' ),
						'BZ' => __( 'Belize', 'wp-easycart' ),
						'BJ' => __( 'Benin', 'wp-easycart' ),
						'BM' => __( 'Bermuda', 'wp-easycart' ),
						'BT' => __( 'Bhutan', 'wp-easycart' ),
						'BO' => __( 'Bolivia', 'wp-easycart' ),
						'BQ' => __( 'Bonaire, Saint Eustatius and Saba', 'wp-easycart' ),
						'BA' => __( 'Bosnia and Herzegovina', 'wp-easycart' ),
						'BW' => __( 'Botswana', 'wp-easycart' ),
						'BV' => __( 'Bouvet Island', 'wp-easycart' ),
						'BR' => __( 'Brazil', 'wp-easycart' ),
						'IO' => __( 'British Indian Ocean Territory', 'wp-easycart' ),
						'VG' => __( 'British Virgin Islands', 'wp-easycart' ),
						'BN' => __( 'Brunei', 'wp-easycart' ),
						'BG' => __( 'Bulgaria', 'wp-easycart' ),
						'BF' => __( 'Burkina Faso', 'wp-easycart' ),
						'BI' => __( 'Burundi', 'wp-easycart' ),
						'KH' => __( 'Cambodia', 'wp-easycart' ),
						'CM' => __( 'Cameroon', 'wp-easycart' ),
						'CA' => __( 'Canada', 'wp-easycart' ),
						'CV' => __( 'Cape Verde', 'wp-easycart' ),
						'KY' => __( 'Cayman Islands', 'wp-easycart' ),
						'CF' => __( 'Central African Republic', 'wp-easycart' ),
						'TD' => __( 'Chad', 'wp-easycart' ),
						'CL' => __( 'Chile', 'wp-easycart' ),
						'CN' => __( 'China', 'wp-easycart' ),
						'CX' => __( 'Christmas Island', 'wp-easycart' ),
						'CC' => __( 'Cocos (Keeling) Islands', 'wp-easycart' ),
						'CO' => __( 'Colombia', 'wp-easycart' ),
						'KM' => __( 'Comoros', 'wp-easycart' ),
						'CG' => __( 'Congo (Brazzaville)', 'wp-easycart' ),
						'CD' => __( 'Congo (Kinshasa)', 'wp-easycart' ),
						'CK' => __( 'Cook Islands', 'wp-easycart' ),
						'CR' => __( 'Costa Rica', 'wp-easycart' ),
						'HR' => __( 'Croatia', 'wp-easycart' ),
						'CU' => __( 'Cuba', 'wp-easycart' ),
						'CW' => __( 'Cura&ccedil;ao', 'wp-easycart' ),
						'CY' => __( 'Cyprus', 'wp-easycart' ),
						'CZ' => __( 'Czech Republic', 'wp-easycart' ),
						'DK' => __( 'Denmark', 'wp-easycart' ),
						'DJ' => __( 'Djibouti', 'wp-easycart' ),
						'DM' => __( 'Dominica', 'wp-easycart' ),
						'DO' => __( 'Dominican Republic', 'wp-easycart' ),
						'EC' => __( 'Ecuador', 'wp-easycart' ),
						'EG' => __( 'Egypt', 'wp-easycart' ),
						'SV' => __( 'El Salvador', 'wp-easycart' ),
						'GQ' => __( 'Equatorial Guinea', 'wp-easycart' ),
						'ER' => __( 'Eritrea', 'wp-easycart' ),
						'EE' => __( 'Estonia', 'wp-easycart' ),
						'ET' => __( 'Ethiopia', 'wp-easycart' ),
						'FK' => __( 'Falkland Islands', 'wp-easycart' ),
						'FO' => __( 'Faroe Islands', 'wp-easycart' ),
						'FJ' => __( 'Fiji', 'wp-easycart' ),
						'FI' => __( 'Finland', 'wp-easycart' ),
						'FR' => __( 'France', 'wp-easycart' ),
						'GF' => __( 'French Guiana', 'wp-easycart' ),
						'PF' => __( 'French Polynesia', 'wp-easycart' ),
						'TF' => __( 'French Southern Territories', 'wp-easycart' ),
						'GA' => __( 'Gabon', 'wp-easycart' ),
						'GM' => __( 'Gambia', 'wp-easycart' ),
						'GE' => __( 'Georgia', 'wp-easycart' ),
						'DE' => __( 'Germany', 'wp-easycart' ),
						'GH' => __( 'Ghana', 'wp-easycart' ),
						'GI' => __( 'Gibraltar', 'wp-easycart' ),
						'GR' => __( 'Greece', 'wp-easycart' ),
						'GL' => __( 'Greenland', 'wp-easycart' ),
						'GD' => __( 'Grenada', 'wp-easycart' ),
						'GP' => __( 'Guadeloupe', 'wp-easycart' ),
						'GU' => __( 'Guam', 'wp-easycart' ),
						'GT' => __( 'Guatemala', 'wp-easycart' ),
						'GG' => __( 'Guernsey', 'wp-easycart' ),
						'GN' => __( 'Guinea', 'wp-easycart' ),
						'GW' => __( 'Guinea-Bissau', 'wp-easycart' ),
						'GY' => __( 'Guyana', 'wp-easycart' ),
						'HT' => __( 'Haiti', 'wp-easycart' ),
						'HM' => __( 'Heard Island and McDonald Islands', 'wp-easycart' ),
						'HN' => __( 'Honduras', 'wp-easycart' ),
						'HK' => __( 'Hong Kong', 'wp-easycart' ),
						'HU' => __( 'Hungary', 'wp-easycart' ),
						'IS' => __( 'Iceland', 'wp-easycart' ),
						'IN' => __( 'India', 'wp-easycart' ),
						'ID' => __( 'Indonesia', 'wp-easycart' ),
						'IR' => __( 'Iran', 'wp-easycart' ),
						'IQ' => __( 'Iraq', 'wp-easycart' ),
						'IE' => __( 'Ireland', 'wp-easycart' ),
						'IM' => __( 'Isle of Man', 'wp-easycart' ),
						'IL' => __( 'Israel', 'wp-easycart' ),
						'IT' => __( 'Italy', 'wp-easycart' ),
						'CI' => __( 'Ivory Coast', 'wp-easycart' ),
						'JM' => __( 'Jamaica', 'wp-easycart' ),
						'JP' => __( 'Japan', 'wp-easycart' ),
						'JE' => __( 'Jersey', 'wp-easycart' ),
						'JO' => __( 'Jordan', 'wp-easycart' ),
						'KZ' => __( 'Kazakhstan', 'wp-easycart' ),
						'KE' => __( 'Kenya', 'wp-easycart' ),
						'KI' => __( 'Kiribati', 'wp-easycart' ),
						'KW' => __( 'Kuwait', 'wp-easycart' ),
						'KG' => __( 'Kyrgyzstan', 'wp-easycart' ),
						'LA' => __( 'Laos', 'wp-easycart' ),
						'LV' => __( 'Latvia', 'wp-easycart' ),
						'LB' => __( 'Lebanon', 'wp-easycart' ),
						'LS' => __( 'Lesotho', 'wp-easycart' ),
						'LR' => __( 'Liberia', 'wp-easycart' ),
						'LY' => __( 'Libya', 'wp-easycart' ),
						'LI' => __( 'Liechtenstein', 'wp-easycart' ),
						'LT' => __( 'Lithuania', 'wp-easycart' ),
						'LU' => __( 'Luxembourg', 'wp-easycart' ),
						'MO' => __( 'Macao S.A.R., China', 'wp-easycart' ),
						'MK' => __( 'Macedonia', 'wp-easycart' ),
						'MG' => __( 'Madagascar', 'wp-easycart' ),
						'MW' => __( 'Malawi', 'wp-easycart' ),
						'MY' => __( 'Malaysia', 'wp-easycart' ),
						'MV' => __( 'Maldives', 'wp-easycart' ),
						'ML' => __( 'Mali', 'wp-easycart' ),
						'MT' => __( 'Malta', 'wp-easycart' ),
						'MH' => __( 'Marshall Islands', 'wp-easycart' ),
						'MQ' => __( 'Martinique', 'wp-easycart' ),
						'MR' => __( 'Mauritania', 'wp-easycart' ),
						'MU' => __( 'Mauritius', 'wp-easycart' ),
						'YT' => __( 'Mayotte', 'wp-easycart' ),
						'MX' => __( 'Mexico', 'wp-easycart' ),
						'FM' => __( 'Micronesia', 'wp-easycart' ),
						'MD' => __( 'Moldova', 'wp-easycart' ),
						'MC' => __( 'Monaco', 'wp-easycart' ),
						'MN' => __( 'Mongolia', 'wp-easycart' ),
						'ME' => __( 'Montenegro', 'wp-easycart' ),
						'MS' => __( 'Montserrat', 'wp-easycart' ),
						'MA' => __( 'Morocco', 'wp-easycart' ),
						'MZ' => __( 'Mozambique', 'wp-easycart' ),
						'MM' => __( 'Myanmar', 'wp-easycart' ),
						'NA' => __( 'Namibia', 'wp-easycart' ),
						'NR' => __( 'Nauru', 'wp-easycart' ),
						'NP' => __( 'Nepal', 'wp-easycart' ),
						'NL' => __( 'Netherlands', 'wp-easycart' ),
						'NC' => __( 'New Caledonia', 'wp-easycart' ),
						'NZ' => __( 'New Zealand', 'wp-easycart' ),
						'NI' => __( 'Nicaragua', 'wp-easycart' ),
						'NE' => __( 'Niger', 'wp-easycart' ),
						'NG' => __( 'Nigeria', 'wp-easycart' ),
						'NU' => __( 'Niue', 'wp-easycart' ),
						'NF' => __( 'Norfolk Island', 'wp-easycart' ),
						'MP' => __( 'Northern Mariana Islands', 'wp-easycart' ),
						'KP' => __( 'North Korea', 'wp-easycart' ),
						'NO' => __( 'Norway', 'wp-easycart' ),
						'OM' => __( 'Oman', 'wp-easycart' ),
						'PK' => __( 'Pakistan', 'wp-easycart' ),
						'PS' => __( 'Palestinian Territory', 'wp-easycart' ),
						'PA' => __( 'Panama', 'wp-easycart' ),
						'PG' => __( 'Papua New Guinea', 'wp-easycart' ),
						'PY' => __( 'Paraguay', 'wp-easycart' ),
						'PE' => __( 'Peru', 'wp-easycart' ),
						'PH' => __( 'Philippines', 'wp-easycart' ),
						'PN' => __( 'Pitcairn', 'wp-easycart' ),
						'PL' => __( 'Poland', 'wp-easycart' ),
						'PT' => __( 'Portugal', 'wp-easycart' ),
						'PR' => __( 'Puerto Rico', 'wp-easycart' ),
						'QA' => __( 'Qatar', 'wp-easycart' ),
						'RE' => __( 'Reunion', 'wp-easycart' ),
						'RO' => __( 'Romania', 'wp-easycart' ),
						'RU' => __( 'Russia', 'wp-easycart' ),
						'RW' => __( 'Rwanda', 'wp-easycart' ),
						'BL' => __( 'Saint Barth&eacute;lemy', 'wp-easycart' ),
						'SH' => __( 'Saint Helena', 'wp-easycart' ),
						'KN' => __( 'Saint Kitts and Nevis', 'wp-easycart' ),
						'LC' => __( 'Saint Lucia', 'wp-easycart' ),
						'MF' => __( 'Saint Martin (French part)', 'wp-easycart' ),
						'SX' => __( 'Saint Martin (Dutch part)', 'wp-easycart' ),
						'PM' => __( 'Saint Pierre and Miquelon', 'wp-easycart' ),
						'VC' => __( 'Saint Vincent and the Grenadines', 'wp-easycart' ),
						'SM' => __( 'San Marino', 'wp-easycart' ),
						'ST' => __( 'S&atilde;o Tom&eacute; and Pr&iacute;ncipe', 'wp-easycart' ),
						'SA' => __( 'Saudi Arabia', 'wp-easycart' ),
						'SN' => __( 'Senegal', 'wp-easycart' ),
						'RS' => __( 'Serbia', 'wp-easycart' ),
						'SC' => __( 'Seychelles', 'wp-easycart' ),
						'SL' => __( 'Sierra Leone', 'wp-easycart' ),
						'SG' => __( 'Singapore', 'wp-easycart' ),
						'SK' => __( 'Slovak Republic', 'wp-easycart' ),
						'SI' => __( 'Slovenia', 'wp-easycart' ),
						'SB' => __( 'Solomon Islands', 'wp-easycart' ),
						'SO' => __( 'Somalia', 'wp-easycart' ),
						'ZA' => __( 'South Africa', 'wp-easycart' ),
						'GS' => __( 'South Georgia/Sandwich Islands', 'wp-easycart' ),
						'KR' => __( 'South Korea', 'wp-easycart' ),
						'SS' => __( 'South Sudan', 'wp-easycart' ),
						'ES' => __( 'Spain', 'wp-easycart' ),
						'LK' => __( 'Sri Lanka', 'wp-easycart' ),
						'SD' => __( 'Sudan', 'wp-easycart' ),
						'SR' => __( 'Suriname', 'wp-easycart' ),
						'SJ' => __( 'Svalbard and Jan Mayen', 'wp-easycart' ),
						'SZ' => __( 'Swaziland', 'wp-easycart' ),
						'SE' => __( 'Sweden', 'wp-easycart' ),
						'CH' => __( 'Switzerland', 'wp-easycart' ),
						'SY' => __( 'Syria', 'wp-easycart' ),
						'TW' => __( 'Taiwan', 'wp-easycart' ),
						'TJ' => __( 'Tajikistan', 'wp-easycart' ),
						'TZ' => __( 'Tanzania', 'wp-easycart' ),
						'TH' => __( 'Thailand', 'wp-easycart' ),
						'TL' => __( 'Timor-Leste', 'wp-easycart' ),
						'TG' => __( 'Togo', 'wp-easycart' ),
						'TK' => __( 'Tokelau', 'wp-easycart' ),
						'TO' => __( 'Tonga', 'wp-easycart' ),
						'TT' => __( 'Trinidad and Tobago', 'wp-easycart' ),
						'TN' => __( 'Tunisia', 'wp-easycart' ),
						'TR' => __( 'Turkey', 'wp-easycart' ),
						'TM' => __( 'Turkmenistan', 'wp-easycart' ),
						'TC' => __( 'Turks and Caicos Islands', 'wp-easycart' ),
						'TV' => __( 'Tuvalu', 'wp-easycart' ),
						'UG' => __( 'Uganda', 'wp-easycart' ),
						'UA' => __( 'Ukraine', 'wp-easycart' ),
						'AE' => __( 'United Arab Emirates', 'wp-easycart' ),
						'GB' => __( 'United Kingdom (UK)', 'wp-easycart' ),
						'US' => __( 'United States (US)', 'wp-easycart' ),
						'UM' => __( 'United States (US) Minor Outlying Islands', 'wp-easycart' ),
						'VI' => __( 'United States (US) Virgin Islands', 'wp-easycart' ),
						'UY' => __( 'Uruguay', 'wp-easycart' ),
						'UZ' => __( 'Uzbekistan', 'wp-easycart' ),
						'VU' => __( 'Vanuatu', 'wp-easycart' ),
						'VA' => __( 'Vatican', 'wp-easycart' ),
						'VE' => __( 'Venezuela', 'wp-easycart' ),
						'VN' => __( 'Vietnam', 'wp-easycart' ),
						'WF' => __( 'Wallis and Futuna', 'wp-easycart' ),
						'EH' => __( 'Western Sahara', 'wp-easycart' ),
						'WS' => __( 'Samoa', 'wp-easycart' ),
						'YE' => __( 'Yemen', 'wp-easycart' ),
						'ZM' => __( 'Zambia', 'wp-easycart' ),
						'ZW' => __( 'Zimbabwe', 'wp-easycart' ),
					);

					$locales =  array(
						'US' => array(
							'currency_code'  => 'USD',
							'currency_pos'	=> 'left',
							'thousand_sep'	=> ',',
							'decimal_sep'	 => '.',
							'num_decimals'	=> 2,
							'weight_unit'	 => 'lbs',
							'dimension_unit' => 'in',
							'tax_rates'		=> array(
								'AL' => array(
									array(
										'country'  => 'US',
										'state'    => 'AL',
										'rate'     => '4.0000',
										'name'     => 'State Tax',
										'shipping' => false,
									),
								),
								'AZ' => array(
									array(
										'country'  => 'US',
										'state'    => 'AZ',
										'rate'     => '5.6000',
										'name'     => 'State Tax',
										'shipping' => false,
									),
								),
								'AR' => array(
									array(
										'country'  => 'US',
										'state'    => 'AR',
										'rate'     => '6.5000',
										'name'     => 'State Tax',
										'shipping' => true,
									),
								),
								'CA' => array(
									array(
										'country'  => 'US',
										'state'    => 'CA',
										'rate'     => '7.5000',
										'name'     => 'State Tax',
										'shipping' => false,
									),
								),
								'CO' => array(
									array(
										'country'  => 'US',
										'state'    => 'CO',
										'rate'     => '2.9000',
										'name'     => 'State Tax',
										'shipping' => false,
									),
								),
								'CT' => array(
									array(
										'country'  => 'US',
										'state'    => 'CT',
										'rate'     => '6.3500',
										'name'     => 'State Tax',
										'shipping' => true,
									),
								),
								'DC' => array(
									array(
										'country'  => 'US',
										'state'    => 'DC',
										'rate'     => '5.7500',
										'name'     => 'State Tax',
										'shipping' => true,
									),
								),
								'FL' => array(
									array(
										'country'  => 'US',
										'state'    => 'FL',
										'rate'     => '6.0000',
										'name'     => 'State Tax',
										'shipping' => true,
									),
								),
								'GA' => array(
									array(
										'country'  => 'US',
										'state'    => 'GA',
										'rate'     => '4.0000',
										'name'     => 'State Tax',
										'shipping' => true,
									),
								),
								'GU' => array(
									array(
										'country'  => 'US',
										'state'    => 'GU',
										'rate'     => '4.0000',
										'name'     => 'State Tax',
										'shipping' => false,
									),
								),
								'HI' => array(
									array(
										'country'  => 'US',
										'state'    => 'HI',
										'rate'     => '4.0000',
										'name'     => 'State Tax',
										'shipping' => true,
									),
								),
								'ID' => array(
									array(
										'country'  => 'US',
										'state'    => 'ID',
										'rate'     => '6.0000',
										'name'     => 'State Tax',
										'shipping' => false,
									),
								),
								'IL' => array(
									array(
										'country'  => 'US',
										'state'    => 'IL',
										'rate'     => '6.2500',
										'name'     => 'State Tax',
										'shipping' => false,
									),
								),
								'IN' => array(
									array(
										'country'  => 'US',
										'state'    => 'IN',
										'rate'     => '7.0000',
										'name'     => 'State Tax',
										'shipping' => false,
									),
								),
								'IA' => array(
									array(
										'country'  => 'US',
										'state'    => 'IA',
										'rate'     => '6.0000',
										'name'     => 'State Tax',
										'shipping' => false,
									),
								),
								'KS' => array(
									array(
										'country'  => 'US',
										'state'    => 'KS',
										'rate'     => '6.1500',
										'name'     => 'State Tax',
										'shipping' => true,
									),
								),
								'KY' => array(
									array(
										'country'  => 'US',
										'state'    => 'KY',
										'rate'     => '6.0000',
										'name'     => 'State Tax',
										'shipping' => true,
									),
								),
								'LA' => array(
									array(
										'country'  => 'US',
										'state'    => 'LA',
										'rate'     => '4.0000',
										'name'     => 'State Tax',
										'shipping' => false,
									),
								),
								'ME' => array(
									array(
										'country'  => 'US',
										'state'    => 'ME',
										'rate'     => '5.5000',
										'name'     => 'State Tax',
										'shipping' => false,
									),
								),
								'MD' => array(
									array(
										'country'  => 'US',
										'state'    => 'MD',
										'rate'     => '6.0000',
										'name'     => 'State Tax',
										'shipping' => false,
									),
								),
								'MA' => array(
									array(
										'country'  => 'US',
										'state'    => 'MA',
										'rate'     => '6.2500',
										'name'     => 'State Tax',
										'shipping' => false,
									),
								),
								'MI' => array(
									array(
										'country'  => 'US',
										'state'    => 'MI',
										'rate'     => '6.0000',
										'name'     => 'State Tax',
										'shipping' => true,
									),
								),
								'MN' => array(
									array(
										'country'  => 'US',
										'state'    => 'MN',
										'rate'     => '6.8750',
										'name'     => 'State Tax',
										'shipping' => true,
									),
								),
								'MS' => array(
									array(
										'country'  => 'US',
										'state'    => 'MS',
										'rate'     => '7.0000',
										'name'     => 'State Tax',
										'shipping' => true,
									),
								),
								'MO' => array(
									array(
										'country'  => 'US',
										'state'    => 'MO',
										'rate'     => '4.225',
										'name'     => 'State Tax',
										'shipping' => false,
									),
								),
								'NE' => array(
									array(
										'country'  => 'US',
										'state'    => 'NE',
										'rate'     => '5.5000',
										'name'     => 'State Tax',
										'shipping' => true,
									),
								),
								'NV' => array(
									array(
										'country'  => 'US',
										'state'    => 'NV',
										'rate'     => '6.8500',
										'name'     => 'State Tax',
										'shipping' => false,
									),
								),
								'NJ' => array(
									array(
										'country'  => 'US',
										'state'    => 'NJ',
										'rate'     => '7.0000',
										'name'     => 'State Tax',
										'shipping' => true,
									),
								),
								'NM' => array(
									array(
										'country'  => 'US',
										'state'    => 'NM',
										'rate'     => '5.1250',
										'name'     => 'State Tax',
										'shipping' => true,
									),
								),
								'NY' => array(
									array(
										'country'  => 'US',
										'state'    => 'NY',
										'rate'     => '4.0000',
										'name'     => 'State Tax',
										'shipping' => true,
									),
								),
								'NC' => array(
									array(
										'country'  => 'US',
										'state'    => 'NC',
										'rate'     => '4.7500',
										'name'     => 'State Tax',
										'shipping' => true,
									),
								),
								'ND' => array(
									array(
										'country'  => 'US',
										'state'    => 'ND',
										'rate'     => '5.0000',
										'name'     => 'State Tax',
										'shipping' => true,
									),
								),
								'OH' => array(
									array(
										'country'  => 'US',
										'state'    => 'OH',
										'rate'     => '5.7500',
										'name'     => 'State Tax',
										'shipping' => true,
									),
								),
								'OK' => array(
									array(
										'country'  => 'US',
										'state'    => 'OK',
										'rate'     => '4.5000',
										'name'     => 'State Tax',
										'shipping' => false,
									),
								),
								'PA' => array(
									array(
										'country'  => 'US',
										'state'    => 'PA',
										'rate'     => '6.0000',
										'name'     => 'State Tax',
										'shipping' => true,
									),
								),
								'PR' => array(
									array(
										'country'  => 'US',
										'state'    => 'PR',
										'rate'     => '6.0000',
										'name'     => 'State Tax',
										'shipping' => false,
									),
								),
								'RI' => array(
									array(
										'country'  => 'US',
										'state'    => 'RI',
										'rate'     => '7.0000',
										'name'     => 'State Tax',
										'shipping' => false,
									),
								),
								'SC' => array(
									array(
										'country'  => 'US',
										'state'    => 'SC',
										'rate'     => '6.0000',
										'name'     => 'State Tax',
										'shipping' => true,
									),
								),
								'SD' => array(
									array(
										'country'  => 'US',
										'state'    => 'SD',
										'rate'     => '4.0000',
										'name'     => 'State Tax',
										'shipping' => true,
									),
								),
								'TN' => array(
									array(
										'country'  => 'US',
										'state'    => 'TN',
										'rate'     => '7.0000',
										'name'     => 'State Tax',
										'shipping' => true,
									),
								),
								'TX' => array(
									array(
										'country'  => 'US',
										'state'    => 'TX',
										'rate'     => '6.2500',
										'name'     => 'State Tax',
										'shipping' => true,
									),
								),
								'UT' => array(
									array(
										'country'  => 'US',
										'state'    => 'UT',
										'rate'     => '5.9500',
										'name'     => 'State Tax',
										'shipping' => false,
									),
								),
								'VT' => array(
									array(
										'country'  => 'US',
										'state'    => 'VT',
										'rate'     => '6.0000',
										'name'     => 'State Tax',
										'shipping' => true,
									),
								),
								'VA' => array(
									array(
										'country'  => 'US',
										'state'    => 'VA',
										'rate'     => '5.3000',
										'name'     => 'State Tax',
										'shipping' => false,
									),
								),
								'WA' => array(
									array(
										'country'  => 'US',
										'state'    => 'WA',
										'rate'     => '6.5000',
										'name'     => 'State Tax',
										'shipping' => true,
									),
								),
								'WV' => array(
									array(
										'country'  => 'US',
										'state'    => 'WV',
										'rate'     => '6.0000',
										'name'     => 'State Tax',
										'shipping' => true,
									),
								),
								'WI' => array(
									array(
										'country'  => 'US',
										'state'    => 'WI',
										'rate'     => '5.0000',
										'name'     => 'State Tax',
										'shipping' => true,
									),
								),
								'WY' => array(
									array(
										'country'  => 'US',
										'state'    => 'WY',
										'rate'     => '4.0000',
										'name'     => 'State Tax',
										'shipping' => true,
									),
								),
							),
						),
						'CA' => array(
							'currency_code'  => 'CAD',
							'currency_pos'   => 'left',
							'thousand_sep'   => ',',
							'decimal_sep'    => '.',
							'num_decimals'   => 2,
							'weight_unit'    => 'kg',
							'dimension_unit' => 'cm',
							'tax_rates'      => array(
								'BC' => array(
									array(
										'country'  => 'CA',
										'state'    => 'BC',
										'rate'     => '7.0000',
										'name'     => _x( 'PST', 'Canadian Tax Rates', 'wp-easycart' ),
										'shipping' => false,
										'priority' => 2,
									),
								),
								'SK' => array(
									array(
										'country'  => 'CA',
										'state'    => 'SK',
										'rate'     => '5.0000',
										'name'     => _x( 'PST', 'Canadian Tax Rates', 'wp-easycart' ),
										'shipping' => false,
										'priority' => 2,
									),
								),
								'MB' => array(
									array(
										'country'  => 'CA',
										'state'    => 'MB',
										'rate'     => '8.0000',
										'name'     => _x( 'PST', 'Canadian Tax Rates', 'wp-easycart' ),
										'shipping' => false,
										'priority' => 2,
									),
								),
								'QC' => array(
									array(
										'country'  => 'CA',
										'state'    => 'QC',
										'rate'     => '9.975',
										'name'     => _x( 'PST', 'Canadian Tax Rates', 'wp-easycart' ),
										'shipping' => false,
										'priority' => 2,
									),
								),
								'*' => array(
									array(
										'country'  => 'CA',
										'state'    => 'ON',
										'rate'     => '13.0000',
										'name'     => _x( 'HST', 'Canadian Tax Rates', 'wp-easycart' ),
										'shipping' => true,
									),
									array(
										'country'  => 'CA',
										'state'    => 'NL',
										'rate'     => '13.0000',
										'name'     => _x( 'HST', 'Canadian Tax Rates', 'wp-easycart' ),
										'shipping' => true,
									),
									array(
										'country'  => 'CA',
										'state'    => 'NB',
										'rate'     => '13.0000',
										'name'     => _x( 'HST', 'Canadian Tax Rates', 'wp-easycart' ),
										'shipping' => true,
									),
									array(
										'country'  => 'CA',
										'state'    => 'PE',
										'rate'     => '14.0000',
										'name'     => _x( 'HST', 'Canadian Tax Rates', 'wp-easycart' ),
										'shipping' => true,
									),
									array(
										'country'  => 'CA',
										'state'    => 'NS',
										'rate'     => '15.0000',
										'name'     => _x( 'HST', 'Canadian Tax Rates', 'wp-easycart' ),
										'shipping' => true,
									),
									array(
										'country'  => 'CA',
										'state'    => 'AB',
										'rate'     => '5.0000',
										'name'     => _x( 'GST', 'Canadian Tax Rates', 'wp-easycart' ),
										'shipping' => true,
									),
									array(
										'country'  => 'CA',
										'state'    => 'BC',
										'rate'     => '5.0000',
										'name'     => _x( 'GST', 'Canadian Tax Rates', 'wp-easycart' ),
										'shipping' => true,
									),
									array(
										'country'  => 'CA',
										'state'    => 'NT',
										'rate'     => '5.0000',
										'name'     => _x( 'GST', 'Canadian Tax Rates', 'wp-easycart' ),
										'shipping' => true,
									),
									array(
										'country'  => 'CA',
										'state'    => 'NU',
										'rate'     => '5.0000',
										'name'     => _x( 'GST', 'Canadian Tax Rates', 'wp-easycart' ),
										'shipping' => true,
									),
									array(
										'country'  => 'CA',
										'state'    => 'YT',
										'rate'     => '5.0000',
										'name'     => _x( 'GST', 'Canadian Tax Rates', 'wp-easycart' ),
										'shipping' => true,
									),
									array(
										'country'  => 'CA',
										'state'    => 'SK',
										'rate'     => '5.0000',
										'name'     => _x( 'GST', 'Canadian Tax Rates', 'wp-easycart' ),
										'shipping' => true,
									),
									array(
										'country'  => 'CA',
										'state'    => 'MB',
										'rate'     => '5.0000',
										'name'     => _x( 'GST', 'Canadian Tax Rates', 'wp-easycart' ),
										'shipping' => true,
									),
									array(
										'country'  => 'CA',
										'state'    => 'QC',
										'rate'     => '5.0000',
										'name'     => _x( 'GST', 'Canadian Tax Rates', 'wp-easycart' ),
										'shipping' => true,
									),
								),
							),
						),
						'AT' => array(
							'currency_code'  => 'EUR',
							'currency_pos'   => 'left',
							'thousand_sep'   => '.',
							'decimal_sep'    => ',',
							'num_decimals'   => 2,
							'weight_unit'    => 'kg',
							'dimension_unit' => 'in',
							'tax_rates'      => array(
								'' => array(
									array(
										'country'  => 'AT',
										'state'    => '',
										'rate'     => '20.0000',
										'name'     => 'VAT',
										'shipping' => true,
									),
								),
							),
						),
						'AU' => array(
							'currency_code'  => 'AUD',
							'currency_pos'   => 'left',
							'thousand_sep'   => ',',
							'decimal_sep'    => '.',
							'num_decimals'   => 2,
							'weight_unit'    => 'kg',
							'dimension_unit' => 'cm',
							'tax_rates'      => array(
								'' => array(
									array(
										'country'  => 'AU',
										'state'    => '',
										'rate'     => '10.0000',
										'name'     => 'GST',
										'shipping' => true,
									),
								),
							),
						),
						'BD' => array(
							'currency_code'  => 'BDT',
							'currency_pos'   => 'left',
							'thousand_sep'   => ',',
							'decimal_sep'    => '.',
							'num_decimals'   => 2,
							'weight_unit'    => 'kg',
							'dimension_unit' => 'in',
							'tax_rates'      => array(
								'' => array(
									array(
										'country'  => 'BD',
										'state'    => '',
										'rate'     => '15.0000',
										'name'     => 'VAT',
										'shipping' => true,
									),
								),
							),
						),
						'BE' => array(
							'currency_code'  => 'EUR',
							'currency_pos'   => 'left',
							'thousand_sep'   => ' ',
							'decimal_sep'    => ',',
							'num_decimals'   => 2,
							'weight_unit'    => 'kg',
							'dimension_unit' => 'cm',
							'tax_rates'      => array(
								'' => array(
								  array(
										'country'  => 'BE',
										'state'    => '',
										'rate'     => '21.0000',
										'name'     => 'BTW',
										'shipping' => true,
									),
								),
							),
						),
						'BR' => array(
							'currency_code'  => 'BRL',
							'currency_pos'   => 'left',
							'thousand_sep'   => '.',
							'decimal_sep'    => ',',
							'num_decimals'   => 2,
							'weight_unit'    => 'kg',
							'dimension_unit' => 'cm',
							'tax_rates'      => array(),
						),
						'CH' => array(
							'currency_code'  => 'CHF',
							'currency_pos'   => 'left',
							'thousand_sep'   => "'",
							'decimal_sep'    => '.',
							'num_decimals'   => 2,
							'weight_unit'    => 'kg',
							'dimension_unit' => 'cm',
							'tax_rates'      => array(
								'' => array(
									array(
										'country'  => 'CH',
										'state'    => '',
										'rate'     => '7.7000',
										'name'     => 'VAT',
										'shipping' => true,
									),
								),
							),
						),
						'CZ' => array(
							'currency_code'  => 'CZK',
							'currency_pos'   => 'left',
							'thousand_sep'   => '.',
							'decimal_sep'    => ',',
							'num_decimals'   => 2,
							'weight_unit'    => 'kg',
							'dimension_unit' => 'cm',
							'tax_rates'      => array(
								'' => array(
									array(
										'country'  => 'CZ',
										'state'    => '',
										'rate'     => '21.0000',
										'name'     => 'VAT',
										'shipping' => true,
									),
								),
							),
						),
						'DE' => array(
							'currency_code'  => 'EUR',
							'currency_pos'   => 'left',
							'thousand_sep'   => '.',
							'decimal_sep'    => ',',
							'num_decimals'   => 2,
							'weight_unit'    => 'kg',
							'dimension_unit' => 'cm',
							'tax_rates'      => array(
								'' => array(
									array(
										'country'  => 'DE',
										'state'    => '',
										'rate'     => '19.0000',
										'name'     => 'Mwst.',
										'shipping' => true,
									),
								),
							),
						),
						'DK' => array(
							'currency_code'  => 'DKK',
							'currency_pos'   => 'left',
							'thousand_sep'   => '.',
							'decimal_sep'    => ',',
							'num_decimals'   => 2,
							'weight_unit'    => 'kg',
							'dimension_unit' => 'cm',
							'tax_rates'      => array(
								'' => array(
									array(
										'country'  => 'DK',
										'state'    => '',
										'rate'     => '25.0000',
										'name'     => 'VAT',
										'shipping' => true,
									),
								),
							),
						),
						'EE' => array(
							'currency_code'  => 'EUR',
							'currency_pos'   => 'right',
							'thousand_sep'   => '.',
							'decimal_sep'    => ',',
							'num_decimals'   => 2,
							'weight_unit'    => 'kg',
							'dimension_unit' => 'cm',
							'tax_rates'      => array(
								'' => array(
									array(
										'country'  => 'EE',
										'state'    => '',
										'rate'     => '20.0000',
										'name'     => 'VAT',
										'shipping' => true,
									),
								),
							),
						),
						'ES' => array(
							'currency_code'  => 'EUR',
							'currency_pos'   => 'right',
							'thousand_sep'   => '.',
							'decimal_sep'    => ',',
							'num_decimals'   => 2,
							'weight_unit'    => 'kg',
							'dimension_unit' => 'cm',
							'tax_rates'      => array(
								'' => array(
									array(
										'country'  => 'ES',
										'state'    => '',
										'rate'     => '21.0000',
										'name'     => 'VAT',
										'shipping' => true,
									),
								),
							),
						),
						'FI' => array(
							'currency_code'  => 'EUR',
							'currency_pos'   => 'right_space',
							'thousand_sep'   => ' ',
							'decimal_sep'    => ',',
							'num_decimals'   => 2,
							'weight_unit'    => 'kg',
							'dimension_unit' => 'cm',
							'tax_rates'      => array(
								'' => array(
									array(
										'country'  => 'FI',
										'state'    => '',
										'rate'     => '24.0000',
										'name'     => 'ALV',
										'shipping' => true,
									),
								),
							),
						),
						'FR' => array(
							'currency_code'  => 'EUR',
							'currency_pos'   => 'right',
							'thousand_sep'   => ' ',
							'decimal_sep'    => ',',
							'num_decimals'   => 2,
							'weight_unit'    => 'kg',
							'dimension_unit' => 'cm',
							'tax_rates'      => array(
								'' => array(
									array(
										'country'  => 'FR',
										'state'    => '',
										'rate'     => '20.0000',
										'name'     => 'TVA',
										'shipping' => true,
									),
								),
							),
						),
						'GB' => array(
							'currency_code'  => 'GBP',
							'currency_pos'	=> 'left',
							'thousand_sep'	=> ',',
							'decimal_sep'	 => '.',
							'num_decimals'	=> 2,
							'weight_unit'	 => 'kg',
							'dimension_unit' => 'cm',
							'tax_rates'		=> array(
								'' => array(
									array(
										'country'  => 'GB',
										'state'	 => '',
										'rate'	  => '20.0000',
										'name'	  => 'VAT',
										'shipping' => true,
									),
								),
							),
						),
						'GR' => array(
							'currency_code'  => 'EUR',
							'currency_pos'   => 'left',
							'thousand_sep'   => '.',
							'decimal_sep'    => ',',
							'num_decimals'   => 2,
							'weight_unit'    => 'kg',
							'dimension_unit' => 'cm',
							'tax_rates'      => array(
								'' => array(
									array(
										'country'  => 'GR',
										'state'    => '',
										'rate'     => '24.0000',
										'name'     => 'VAT',
										'shipping' => true,
									),
								),
							),
						),
						'HU' => array(
							'currency_code'  => 'HUF',
							'currency_pos'   => 'right_space',
							'thousand_sep'   => ' ',
							'decimal_sep'    => ',',
							'num_decimals'   => 0,
							'weight_unit'    => 'kg',
							'dimension_unit' => 'cm',
							'tax_rates'      => array(
								'' => array(
									array(
										'country'  => 'HU',
										'state'    => '',
										'rate'     => '27.0000',
										'name'     => 'ÁFA',
										'shipping' => true,
									),
								),
							),
						),
						'IE' => array(
							'currency_code'  => 'EUR',
							'currency_pos'   => 'left',
							'thousand_sep'   => ',',
							'decimal_sep'    => '.',
							'num_decimals'   => 2,
							'weight_unit'    => 'kg',
							'dimension_unit' => 'cm',
							'tax_rates'      => array(
								'' => array(
									array(
										'country'  => 'IE',
										'state'    => '',
										'rate'     => '23.0000',
										'name'     => 'VAT',
										'shipping' => true,
									),
								),
							),
						),
						'IS' => array(
							'currency_code'  => 'EUR',
							'currency_pos'   => 'right',
							'thousand_sep'   => '.',
							'decimal_sep'    => ',',
							'num_decimals'   => 2,
							'weight_unit'    => 'kg',
							'dimension_unit' => 'cm',
							'tax_rates'      => array(
								'' => array(
									array(
										'country'  => 'IS',
										'state'    => '',
										'rate'     => '24.0000',
										'name'     => 'VAT',
										'shipping' => true,
									),
								),
							),
						),
						'IT' => array(
							'currency_code'  => 'EUR',
							'currency_pos'   => 'right',
							'thousand_sep'   => '.',
							'decimal_sep'    => ',',
							'num_decimals'   => 2,
							'weight_unit'    => 'kg',
							'dimension_unit' => 'cm',
							'tax_rates'      => array(
								'' => array(
									array(
										'country'  => 'IT',
										'state'    => '',
										'rate'     => '22.0000',
										'name'     => 'IVA',
										'shipping' => true,
									),
								),
							),
						),
						'JP' => array(
							'currency_code'  => 'JPY',
							'currency_pos'   => 'left',
							'thousand_sep'   => ',',
							'decimal_sep'    => '.',
							'num_decimals'   => 0,
							'weight_unit'    => 'kg',
							'dimension_unit' => 'cm',
							'tax_rates'      => array(
								'' => array(
									array(
										'country'  => 'JP',
										'state'    => '',
										'rate'     => '8.0000',
										'name'     => __( 'Consumption tax', 'wp-easycart' ),
										'shipping' => true,
									),
								),
							),
						),
						'LV' => array(
							'currency_code'  => 'EUR',
							'currency_pos'   => 'left',
							'thousand_sep'   => ',',
							'decimal_sep'    => '.',
							'num_decimals'   => 2,
							'weight_unit'    => 'kg',
							'dimension_unit' => 'cm',
							'tax_rates'      => array(
								'' => array(
									array(
										'country'  => 'LV',
										'state'    => '',
										'rate'     => '21.0000',
										'name'     => 'VAT',
										'shipping' => true,
									),
								),
							),
						),
						'LU' => array(
							'currency_code'  => 'EUR',
							'currency_pos'   => 'left',
							'thousand_sep'   => ',',
							'decimal_sep'    => '.',
							'num_decimals'   => 2,
							'weight_unit'    => 'kg',
							'dimension_unit' => 'cm',
							'tax_rates'      => array(
								'' => array(
									array(
										'country'  => 'LU',
										'state'    => '',
										'rate'     => '17.0000',
										'name'     => 'VAT',
										'shipping' => true,
									),
								),
							),
						),
						'NL' => array(
							'currency_code'  => 'EUR',
							'currency_pos'   => 'left',
							'thousand_sep'   => ',',
							'decimal_sep'    => '.',
							'num_decimals'   => 2,
							'weight_unit'    => 'kg',
							'dimension_unit' => 'cm',
							'tax_rates'      => array(
								'' => array(
									array(
										'country'  => 'NL',
										'state'    => '',
										'rate'     => '21.0000',
										'name'     => 'VAT',
										'shipping' => true,
									),
								),
							),
						),
						'NO' => array(
							'currency_code'  => 'Kr',
							'currency_pos'   => 'left_space',
							'thousand_sep'   => '.',
							'decimal_sep'    => ',',
							'num_decimals'   => 2,
							'weight_unit'    => 'kg',
							'dimension_unit' => 'cm',
							'tax_rates'      => array(
								'' => array(
									array(
										'country'  => 'NO',
										'state'    => '',
										'rate'     => '25.0000',
										'name'     => 'MVA',
										'shipping' => true,
									),
								),
							),
						),
						'NP' => array(
							'currency_code'  => 'NPR',
							'currency_pos'   => 'left_space',
							'thousand_sep'   => ',',
							'decimal_sep'    => '.',
							'num_decimals'   => 2,
							'weight_unit'    => 'kg',
							'dimension_unit' => 'cm',
							'tax_rates'      => array(
								'' => array(
									array(
										'country'  => 'NP',
										'state'    => '',
										'rate'     => '13.0000',
										'name'     => 'VAT',
										'shipping' => true,
									),
								),
							),
						),
						'PL' => array(
							'currency_code'  => 'PLN',
							'currency_pos'   => 'right_space',
							'thousand_sep'   => ' ',
							'decimal_sep'    => ',',
							'num_decimals'   => 2,
							'weight_unit'    => 'kg',
							'dimension_unit' => 'cm',
							'tax_rates'      => array(
								'' => array(
									array(
										'country'  => 'PL',
										'state'    => '',
										'rate'     => '23.0000',
										'name'     => 'VAT',
										'shipping' => true,
									),
								),
							),
						),
						'PT' => array(
							'currency_code'  => 'EUR',
							'currency_pos'   => 'right_space',
							'thousand_sep'   => '.',
							'decimal_sep'    => ',',
							'num_decimals'   => 2,
							'weight_unit'    => 'kg',
							'dimension_unit' => 'cm',
							'tax_rates'      => array(
								'' => array(
									array(
										'country'  => 'PT',
										'state'    => '',
										'rate'     => '23.0000',
										'name'     => 'VAT',
										'shipping' => true,
									),
								),
							),
						),
						'SE' => array(
							'currency_code'  => 'SEK',
							'currency_pos'   => 'right_space',
							'thousand_sep'   => ' ',
							'decimal_sep'    => ',',
							'num_decimals'   => 2,
							'weight_unit'    => 'kg',
							'dimension_unit' => 'cm',
							'tax_rates'      => array(
								'' => array(
									array(
										'country'  => 'SE',
										'state'    => '',
										'rate'     => '25.0000',
										'name'     => 'VAT',
										'shipping' => true,
									),
								),
							),
						),
						'SI' => array(
							'currency_code'  => 'EUR',
							'currency_pos'   => 'right_space',
							'thousand_sep'   => '.',
							'decimal_sep'    => ',',
							'num_decimals'   => 2,
							'weight_unit'    => 'kg',
							'dimension_unit' => 'cm',
							'tax_rates'      => array(
								'' => array(
									array(
										'country'  => 'SI',
										'state'    => '',
										'rate'     => '22.0000',
										'name'     => 'VAT',
										'shipping' => true,
									),
								),
							),
						),
						'SK' => array(
							'currency_code'  => 'EUR',
							'currency_pos'   => 'right_space',
							'thousand_sep'   => '.',
							'decimal_sep'    => ',',
							'num_decimals'   => 2,
							'weight_unit'    => 'kg',
							'dimension_unit' => 'cm',
							'tax_rates'      => array(
								'' => array(
									array(
										'country'  => 'SK',
										'state'    => '',
										'rate'     => '20.0000',
										'name'     => 'VAT',
										'shipping' => true,
									),
								),
							),
						),
						'RO' => array(
							'currency_code'  => 'RON',
							'currency_pos'   => 'right_space',
							'thousand_sep'   => '.',
							'decimal_sep'    => ',',
							'num_decimals'   => 2,
							'weight_unit'    => 'kg',
							'dimension_unit' => 'cm',
							'tax_rates'      => array(
								'' => array(
									array(
										'country'  => 'RO',
										'state'    => '',
										'rate'     => '19.0000',
										'name'     => 'TVA',
										'shipping' => true,
									),
								),
							),
						),
						'TH' => array(
							'currency_code'  => 'THB',
							'currency_pos'   => 'left',
							'thousand_sep'   => ',',
							'decimal_sep'    => '.',
							'num_decimals'   => 2,
							'weight_unit'    => 'kg',
							'dimension_unit' => 'cm',
							'tax_rates'      => array(
								'' => array(
									array(
										'country'  => 'TH',
										'state'    => '',
										'rate'     => '7.0000',
										'name'     => 'VAT',
										'shipping' => true,
									),
								),
							),
						),
						'TR' => array(
							'currency_code'  => 'TRY',
							'currency_pos'   => 'left_space',
							'thousand_sep'   => '.',
							'decimal_sep'    => ',',
							'num_decimals'   => 2,
							'weight_unit'    => 'kg',
							'dimension_unit' => 'cm',
							'tax_rates'      => array(
								'' => array(
									array(
										'country'  => 'TR',
										'state'    => '',
										'rate'     => '18.0000',
										'name'     => 'KDV',
										'shipping' => true,
									),
								),
							),
						),
						'ZA' => array(
							'currency_code'  => 'ZAR',
							'currency_pos'   => 'left',
							'thousand_sep'   => ',',
							'decimal_sep'    => '.',
							'num_decimals'   => 2,
							'weight_unit'    => 'kg',
							'dimension_unit' => 'cm',
							'tax_rates'      => array(
								'' => array(
									array(
										'country'  => 'ZA',
										'state'    => '',
										'rate'     => '15.0000',
										'name'     => 'VAT',
										'shipping' => true,
									),
								),
							),
						),
					);

					$currency_symbols = array(
						'AED' => '&#x62f;.&#x625;',
						'AFN' => '&#x60b;',
						'ALL' => 'L',
						'AMD' => 'AMD',
						'ANG' => '&fnof;',
						'AOA' => 'Kz',
						'ARS' => '$',
						'AUD' => '$',
						'AWG' => 'Afl.',
						'AZN' => 'AZN',
						'BAM' => 'KM',
						'BBD' => '$',
						'BDT' => '&#2547;&nbsp;',
						'BGN' => '&#1083;&#1074;.',
						'BHD' => '.&#x62f;.&#x628;',
						'BIF' => 'Fr',
						'BMD' => '$',
						'BND' => '$',
						'BOB' => 'Bs.',
						'BRL' => '&#82;$',
						'BSD' => '$',
						'BTC' => '&#3647;',
						'BTN' => 'Nu.',
						'BWP' => 'P',
						'BYR' => 'Br',
						'BZD' => '$',
						'CAD' => '$',
						'CDF' => 'Fr',
						'CHF' => '&#67;&#72;&#70;',
						'CLP' => '$',
						'CNY' => '&yen;',
						'COP' => '$',
						'CRC' => '&#x20a1;',
						'CUC' => '$',
						'CUP' => '$',
						'CVE' => '$',
						'CZK' => '&#75;&#269;',
						'DJF' => 'Fr',
						'DKK' => 'DKK',
						'DOP' => 'RD$',
						'DZD' => '&#x62f;.&#x62c;',
						'EGP' => 'EGP',
						'ERN' => 'Nfk',
						'ETB' => 'Br',
						'EUR' => '&euro;',
						'FJD' => '$',
						'FKP' => '&pound;',
						'GBP' => '&pound;',
						'GEL' => '&#x10da;',
						'GGP' => '&pound;',
						'GHS' => '&#x20b5;',
						'GIP' => '&pound;',
						'GMD' => 'D',
						'GNF' => 'Fr',
						'GTQ' => 'Q',
						'GYD' => '$',
						'HKD' => '$',
						'HNL' => 'L',
						'HRK' => 'Kn',
						'HTG' => 'G',
						'HUF' => '&#70;&#116;',
						'IDR' => 'Rp',
						'ILS' => '&#8362;',
						'IMP' => '&pound;',
						'INR' => '&#8377;',
						'IQD' => '&#x639;.&#x62f;',
						'IRR' => '&#xfdfc;',
						'IRT' => '&#x062A;&#x0648;&#x0645;&#x0627;&#x0646;',
						'ISK' => 'kr.',
						'JEP' => '&pound;',
						'JMD' => '$',
						'JOD' => '&#x62f;.&#x627;',
						'JPY' => '&yen;',
						'KES' => 'KSh',
						'KGS' => '&#x441;&#x43e;&#x43c;',
						'KHR' => '&#x17db;',
						'KMF' => 'Fr',
						'KPW' => '&#x20a9;',
						'KRW' => '&#8361;',
						'KWD' => '&#x62f;.&#x643;',
						'KYD' => '$',
						'KZT' => 'KZT',
						'LAK' => '&#8365;',
						'LBP' => '&#x644;.&#x644;',
						'LKR' => '&#xdbb;&#xdd4;',
						'LRD' => '$',
						'LSL' => 'L',
						'LYD' => '&#x644;.&#x62f;',
						'MAD' => '&#x62f;.&#x645;.',
						'MDL' => 'MDL',
						'MGA' => 'Ar',
						'MKD' => '&#x434;&#x435;&#x43d;',
						'MMK' => 'Ks',
						'MNT' => '&#x20ae;',
						'MOP' => 'P',
						'MRO' => 'UM',
						'MUR' => '&#x20a8;',
						'MVR' => '.&#x783;',
						'MWK' => 'MK',
						'MXN' => '$',
						'MYR' => '&#82;&#77;',
						'MZN' => 'MT',
						'NAD' => '$',
						'NGN' => '&#8358;',
						'NIO' => 'C$',
						'NOK' => '&#107;&#114;',
						'NPR' => '&#8360;',
						'NZD' => '$',
						'OMR' => '&#x631;.&#x639;.',
						'PAB' => 'B/.',
						'PEN' => 'S/.',
						'PGK' => 'K',
						'PHP' => '&#8369;',
						'PKR' => '&#8360;',
						'PLN' => '&#122;&#322;',
						'PRB' => '&#x440;.',
						'PYG' => '&#8370;',
						'QAR' => '&#x631;.&#x642;',
						'RMB' => '&yen;',
						'RON' => 'lei',
						'RSD' => '&#x434;&#x438;&#x43d;.',
						'RUB' => '&#8381;',
						'RWF' => 'Fr',
						'SAR' => '&#x631;.&#x633;',
						'SBD' => '$',
						'SCR' => '&#x20a8;',
						'SDG' => '&#x62c;.&#x633;.',
						'SEK' => '&#107;&#114;',
						'SGD' => '$',
						'SHP' => '&pound;',
						'SLL' => 'Le',
						'SOS' => 'Sh',
						'SRD' => '$',
						'SSP' => '&pound;',
						'STD' => 'Db',
						'SYP' => '&#x644;.&#x633;',
						'SZL' => 'L',
						'THB' => '&#3647;',
						'TJS' => '&#x405;&#x41c;',
						'TMT' => 'm',
						'TND' => '&#x62f;.&#x62a;',
						'TOP' => 'T$',
						'TRY' => '&#8378;',
						'TTD' => '$',
						'TWD' => '&#78;&#84;$',
						'TZS' => 'Sh',
						'UAH' => '&#8372;',
						'UGX' => 'UGX',
						'USD' => '$',
						'UYU' => '$',
						'UZS' => 'UZS',
						'VEF' => 'Bs F',
						'VND' => '&#8363;',
						'VUV' => 'Vt',
						'WST' => 'T',
						'XAF' => 'CFA',
						'XCD' => '$',
						'XOF' => 'CFA',
						'XPF' => 'Fr',
						'YER' => '&#xfdfc;',
						'ZAR' => '&#82;',
						'ZMW' => 'ZK',
					);
					$states = array();
					$states['CA'] = array(
						'AB' => __( 'Alberta', 'woocommerce' ),
						'BC' => __( 'British Columbia', 'woocommerce' ),
						'MB' => __( 'Manitoba', 'woocommerce' ),
						'NB' => __( 'New Brunswick', 'woocommerce' ),
						'NL' => __( 'Newfoundland', 'woocommerce' ),
						'NT' => __( 'Northwest Territories', 'woocommerce' ),
						'NS' => __( 'Nova Scotia', 'woocommerce' ),
						'NU' => __( 'Nunavut', 'woocommerce' ),
						'ON' => __( 'Ontario', 'woocommerce' ),
						'PE' => __( 'Prince Edward Island', 'woocommerce' ),
						'QC' => __( 'Quebec', 'woocommerce' ),
						'SK' => __( 'Saskatchewan', 'woocommerce' ),
						'YT' => __( 'Yukon', 'woocommerce' ),
					);

					/* Posted as "CC" or "CC_ST". Split first, then validate the country part —
					   the previous check ran isset() on the combined string, which never matched
					   a state locale, so US state tax rates were silently skipped. */
					$posted   = isset( $_POST['locale'] ) ? sanitize_text_field( wp_unslash( $_POST['locale'] ) ) : 'US';
					$exploded = explode( '_', $posted );
					$selected_locale       = ( isset( $countries[ $exploded[0] ] ) ) ? $exploded[0] : 'US';
					$selected_state_locale = ( count( $exploded ) > 1 ) ? preg_replace( '/[^A-Z0-9]/', '', strtoupper( $exploded[1] ) ) : '';
					$selected_currency = ( isset( $currency_symbols[$_POST['currency']] ) ) ? sanitize_text_field( $_POST['currency'] ) : 'USD';
					update_option( 'ec_option_store_locale', $selected_locale );
					update_option( 'ec_option_base_currency', $selected_currency );
					update_option( 'ec_option_currency', $currency_symbols[$selected_currency] );
					if ( isset( $locales[$selected_locale] ) ) {
						if ( $locales[$selected_locale]['currency_pos'] == 'left' ) {
							update_option( 'ec_option_currency_symbol_location', 1 );
						} else {
							update_option( 'ec_option_currency_symbol_location', 0 );
						}
						update_option( 'ec_option_currency_decimal_symbol', $locales[$selected_locale]['decimal_sep'] );
						update_option( 'ec_option_currency_thousands_seperator', $locales[$selected_locale]['thousand_sep'] );
						update_option( 'ec_option_currency_decimal_places', $locales[$selected_locale]['num_decimals'] );
						update_option( 'ec_option_paypal_currency_code', $selected_currency );
						$paypal_locales = array( 'US', 'AU', 'AT', 'BE', 'BR', 'CA', 'CH', 'CN', 'DE', 'ES', 'GB', 'FR', 'IT', 'NL', 'PL', 'PT', 'RU' );
						if ( in_array( $selected_locale, $paypal_locales ) )
							update_option( 'ec_option_paypal_lc', $selected_locale );
						if ( $locales[$selected_locale]['weight_unit'] == 'kg' )
							update_option( 'ec_option_paypal_weight_unit', 'kgs');
						else
							update_option( 'ec_option_paypal_weight_unit', 'lbs' );

						global $wpdb;

						if ( isset( $_POST['sales_tax'] ) ) {
							if ( $selected_locale == 'US' ) {
								foreach ( $locales[$selected_locale]['tax_rates'] as $state_code => $tax_rates ) {
									if ( $selected_state_locale == $state_code ) {
										for( $i=0; $i<count( $tax_rates ); $i++ ) {
											$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_taxrate( tax_by_state, state_rate, state_code ) VALUES( 1, %s, %s )', $tax_rates[$i]['rate'], $tax_rates[$i]['state'] ) );
										}
									}
								}

							} else if ( $selected_locale == 'CA' ) {
								$canada_tax_rates = array();
								foreach ( $locales[$selected_locale]['tax_rates'] as $tax_rates ) {
									for( $i=0; $i<count( $tax_rates ); $i++ ) {
										if ( !isset( $canada_tax_rates['ec_option_collect_'.str_replace( ' ', '_', strtolower( $states['CA'][$tax_rates[$i]['state']] ) ).'_tax_shopper'] ) ) {
											$canada_tax_rates['ec_option_collect_'.str_replace( ' ', '_', strtolower( $states['CA'][$tax_rates[$i]['state']] ) ).'_tax_shopper'] = 1;
											$canada_tax_rates['ec_option_'.str_replace( ' ', '_', strtolower( $states['CA'][$tax_rates[$i]['state']] ) ).'_tax_shopper_gst'] = 0.000;
											$canada_tax_rates['ec_option_'.str_replace( ' ', '_', strtolower( $states['CA'][$tax_rates[$i]['state']] ) ).'_tax_shopper_hst'] = 0.000;
											$canada_tax_rates['ec_option_'.str_replace( ' ', '_', strtolower( $states['CA'][$tax_rates[$i]['state']] ) ).'_tax_shopper_pst'] = 0.000;
										}
										$canada_tax_rates['ec_option_'.str_replace( ' ', '_', strtolower( $states['CA'][$tax_rates[$i]['state']] ) ).'_tax_shopper_'.strtolower( $tax_rates[$i]['name'] )] = $tax_rates[$i]['rate'] / 100;
									}
								}

								if ( $selected_locale == 'CA' ) {
									update_option( 'ec_option_enable_easy_canada_tax', 1 );
									update_option( 'ec_option_canada_tax_options', $canada_tax_rates );
								}

							} else { // VAT or Other
								if ( $selected_locale == 'AU' ) {
									wp_easycart_language()->set_language_data( json_decode( html_entity_decode( str_replace( 'VAT', 'GST', json_encode( wp_easycart_language()->get_language_data() ) ) ) ) );
									wp_easycart_language()->update_language_data();
								}
								foreach ( $locales[$selected_locale]['tax_rates'] as $tax_rates ) {
									for( $i=0; $i<count( $tax_rates ); $i++ ) {
										if ( $check_row = $wpdb->get_row( 'SELECT taxrate_id FROM ec_taxrate WHERE tax_by_vat = 1' ) )
											$wpdb->query( $wpdb->prepare( 'UPDATE ec_taxrate SET vat_rate = %s WHERE taxrate_id = %d', $tax_rates[$i]['rate'], $check_row->taxrate_id ) );
										else
											$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_taxrate( tax_by_vat, vat_rate ) VALUES( 1, %s )', $tax_rates[$i]['rate'] ) );

										if ( $check_row = $wpdb->get_row( $wpdb->prepare( 'SELECT id_cnt FROM ec_country WHERE iso2_cnt = %s', $tax_rates[$i]['country'] ) ) )
											$wpdb->query( $wpdb->prepare( 'UPDATE ec_country SET vat_rate_cnt = %s WHERE id_cnt = %d', $tax_rates[$i]['rate'], $check_row->id_cnt ) );
										else
											$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_country( name_cnt, iso2_cnt, vat_rate_cnt ) VALUES( 1, %s )', $countries[$tax_rates[$i]['country']], $tax_rates[$i]['country'], $tax_rates[$i]['rate'] ) );
									}
								}
							}
						}
					}
					$this->mark_completed( self::STEP_LOCATION );
					wp_redirect( $this->step_url( self::STEP_PAYMENTS ) );
					exit;
				}
			}
		}


		public function process_payments_submit() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_settings' ) ) {
				return false;
			}
			if ( isset( $_POST['ec_admin_form_action'] ) && 'process-wizard-payments' == $_POST['ec_admin_form_action'] ) {
				if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-process-wizard-payments' ) ) {

					if ( isset( $_POST['accept_terms'] ) ) {
						update_option( 'ec_option_wpeasycart_terms_accepted', 1 );
					}

					/* Hard rule on the Free edition: no Connect gateway without accepted terms.
					   Manual payments are always allowed. */
					$wants_gateway = isset( $_POST['paypal_standard'] ) || isset( $_POST['use_stripe'] ) || isset( $_POST['use_square'] );
					if ( $wants_gateway && $this->show_terms_gate() ) {
						$this->clear_connect_gateways();
						wp_redirect( $this->step_url( self::STEP_PAYMENTS ) . '&error=terms-required' );
						exit;
					}

					update_option( 'ec_option_use_direct_deposit', isset( $_POST['manual_billing'] ) ? 1 : 0 );

					/* Gateway flags are hidden inputs mirroring the connected state set by the
					   onboarding return handlers; re-saving is idempotent. */
					if ( isset( $_POST['paypal_standard'] ) ) {
						update_option( 'ec_option_payment_third_party', 'paypal' );
					}
					if ( isset( $_POST['use_stripe'] ) ) {
						update_option( 'ec_option_payment_process_method', 'stripe_connect' );
						update_option( 'ec_option_default_payment_type', 'credit_card' );
					}
					if ( isset( $_POST['use_square'] ) ) {
						update_option( 'ec_option_payment_process_method', 'square' );
						update_option( 'ec_option_default_payment_type', 'credit_card' );
					}

					/* Checkout preference */
					update_option( 'ec_option_allow_guest', isset( $_POST['allow_guest'] ) ? 1 : 0 );

					wp_cache_delete( 'wpeasycart-settings', 'wpeasycart-settings' );
					$this->mark_completed( self::STEP_PAYMENTS );
					wp_redirect( $this->step_url( self::STEP_SHIPPING ) );
					exit;
				}
			}
		}

		public function process_shipping_submit() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_settings' ) ) {
				return false;
			}
			if ( isset( $_POST['ec_admin_form_action'] ) && 'process-wizard-shipping' == $_POST['ec_admin_form_action'] ) {
				if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-process-wizard-shipping' ) ) {
					global $wpdb;
					$presets = $this->get_shipping_presets();
					$method  = isset( $_POST['shipping_method'] ) ? sanitize_key( $_POST['shipping_method'] ) : 'static';
					if ( ! isset( $presets[ $method ] ) ) {
						$method = 'static';
					}

					/* Install presets for the chosen type only when that type has no rates yet.
					   Rates of other types are left alone; re-submitting never duplicates rows. */
					$counts  = $this->get_shipping_rate_counts();
					$install = ( 0 === $counts[ $method ] );
					$db_method = array( 'static' => 'method', 'price' => 'price', 'weight' => 'weight' );

					$wpdb->query( $wpdb->prepare( 'UPDATE ec_setting SET shipping_method = %s', $db_method[ $method ] ) );

					if ( $install ) {
						foreach ( $presets[ $method ]['rates'] as $rate ) {
							if ( 'static' == $method ) {
								$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_shippingrate( is_method_based, shipping_label, shipping_rate, shipping_order ) VALUES( 1, %s, %s, %d )', $rate['shipping_label'], $rate['shipping_rate'], $rate['shipping_order'] ) );
							} else if ( 'price' == $method ) {
								$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_shippingrate( is_price_based, trigger_rate, shipping_rate ) VALUES( 1, %s, %s )', $rate['trigger_rate'], $rate['shipping_rate'] ) );
							} else {
								$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_shippingrate( is_weight_based, trigger_rate, shipping_rate ) VALUES( 1, %s, %s )', $rate['trigger_rate'], $rate['shipping_rate'] ) );
							}
						}
					}

					wp_cache_delete( 'wpeasycart-settings', 'wpeasycart-settings' );
					$this->mark_completed( self::STEP_SHIPPING );
					wp_redirect( $this->step_url( self::STEP_FINISH ) );
					exit;
				}
			}
		}

		public function process_finish_submit() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_settings' ) ) {
				return false;
			}
			if ( isset( $_POST['ec_admin_form_action'] ) && 'process-wizard-finish' == $_POST['ec_admin_form_action'] ) {
				if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-process-wizard-finish' ) ) {

					/* Policies */
					$terms_id = isset( $_POST['terms_page'] ) ? (int) $_POST['terms_page'] : 0;
					$priv_id  = isset( $_POST['privacy_page'] ) ? (int) $_POST['privacy_page'] : 0;
					update_option( 'ec_option_terms_link', $terms_id ? get_permalink( $terms_id ) : '' );
					update_option( 'ec_option_privacy_link', $priv_id ? get_permalink( $priv_id ) : '' );
					update_option( 'ec_option_require_terms_agreement', isset( $_POST['require_terms_agreement'] ) ? 1 : 0 );

					/* Notifications */
					if ( isset( $_POST['order_from_email'] ) && is_email( sanitize_email( wp_unslash( $_POST['order_from_email'] ) ) ) ) {
						$from = sanitize_email( wp_unslash( $_POST['order_from_email'] ) );
						update_option( 'ec_option_order_from_email', $from );
						if ( '' == self::real_option( 'ec_option_password_from_email' ) ) {
							update_option( 'ec_option_password_from_email', $from );
						}
					}
					$bcc = isset( $_POST['bcc_email'] ) ? sanitize_text_field( wp_unslash( $_POST['bcc_email'] ) ) : '';
					if ( '' != $bcc ) {
						$clean = array();
						foreach ( explode( ',', $bcc ) as $addr ) {
							$addr = sanitize_email( trim( $addr ) );
							if ( is_email( $addr ) ) {
								$clean[] = $addr;
							}
						}
						update_option( 'ec_option_bcc_email_addresses', implode( ',', $clean ) );

						if ( isset( $_POST['subscribe_me'] ) && ! empty( $clean ) ) {
							/* Same call the previous wizard made ( newsletter / trial registration ). */
							$site_url = str_replace( array( 'http://', 'https://', 'www.' ), '', site_url() );
							$request  = new WP_Http;
							$request->request(
								sprintf( 'https://licensing.wpeasycart.com/licensing/activatetrial.php?customeremail=%s&customername=%s&siteurl=%s', urlencode( $clean[0] ), urlencode( get_bloginfo( 'name' ) ), urlencode( esc_url_raw( $site_url ) ) ),
								array( 'method' => 'GET', 'timeout' => 5 )
							);
						}
					}

					/* Usage data ( absorbs the old banner ) */
					update_option( 'ec_option_allow_tracking', isset( $_POST['allow_tracking'] ) ? '1' : '-1' );

					/* Recommended settings — only writable via the Advanced disclosure, and only
					   when the environment allows it. Record explicit overrides so sync doesn't
					   fight the merchant. */
					$status = $this->get_recommended_status();

					$cache = isset( $_POST['adv_cache'] ) ? 1 : 0;
					update_option( 'ec_option_cache_prevent', $cache );
					$this->set_override( 'cache', ! $cache );

					if ( ! $status['ssl']['locked'] ) {
						$ssl = isset( $_POST['adv_ssl'] ) ? 1 : 0;
						update_option( 'ec_option_load_ssl', $ssl );
						$this->set_override( 'ssl', ! $ssl );
					}
					if ( ! $status['seo']['locked'] ) {
						$seo = isset( $_POST['adv_seo'] ) ? 1 : 0;
						update_option( 'ec_option_use_old_linking_style', $seo ? 0 : 1 );
						$this->set_override( 'seo', ! $seo );
					}

					update_option( 'ec_option_setup_wizard_done', 1 );
					wp_cache_delete( 'wpeasycart-settings', 'wpeasycart-settings' );
					$this->mark_completed( self::STEP_FINISH );
					wp_redirect( $this->step_url( self::STEP_DONE ) );
					exit;
				}
			}
		}

		/* =====================================================================
		   AJAX
		   ===================================================================== */

		private function ajax_guard() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_settings' ) ) {
				wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
			}
			check_ajax_referer( self::AJAX_NONCE, 'nonce' );
		}

		/** Add Store / Cart / Account pages to the primary menu ( creating one if needed ). */
		public function ajax_add_menu_items() {
			$this->ajax_guard();

			$pages = array(
				(int) get_option( 'ec_option_storepage' )   => __( 'Store', 'wp-easycart' ),
				(int) get_option( 'ec_option_cartpage' )    => __( 'Cart', 'wp-easycart' ),
				(int) get_option( 'ec_option_accountpage' ) => __( 'My Account', 'wp-easycart' ),
			);
			unset( $pages[0] );
			if ( empty( $pages ) ) {
				wp_send_json_error( array( 'message' => __( 'Store pages have not been created yet.', 'wp-easycart' ) ) );
			}

			/* Pick the menu: first assigned theme location, else first menu, else create one. */
			$menu_id   = 0;
			$locations = get_nav_menu_locations();
			$registered = get_registered_nav_menus();
			foreach ( array_keys( $registered ) as $loc ) {
				if ( ! empty( $locations[ $loc ] ) ) {
					$menu_id = (int) $locations[ $loc ];
					break;
				}
			}
			if ( ! $menu_id ) {
				$menus = wp_get_nav_menus();
				if ( ! empty( $menus ) ) {
					$menu_id = (int) $menus[0]->term_id;
				}
			}
			if ( ! $menu_id ) {
				$menu_id = wp_create_nav_menu( __( 'Primary Menu', 'wp-easycart' ) );
				if ( is_wp_error( $menu_id ) ) {
					wp_send_json_error( array( 'message' => $menu_id->get_error_message() ) );
				}
				if ( ! empty( $registered ) ) {
					$first = array_keys( $registered );
					$locations[ $first[0] ] = $menu_id;
					set_theme_mod( 'nav_menu_locations', $locations );
				}
			}

			$existing = wp_get_nav_menu_items( $menu_id );
			$have     = array();
			if ( $existing ) {
				foreach ( $existing as $item ) {
					if ( 'page' === $item->object ) {
						$have[ (int) $item->object_id ] = true;
					}
				}
			}
			$added = 0;
			foreach ( $pages as $page_id => $label ) {
				if ( isset( $have[ $page_id ] ) ) {
					continue;
				}
				$r = wp_update_nav_menu_item( $menu_id, 0, array(
					'menu-item-title'     => get_the_title( $page_id ) ? get_the_title( $page_id ) : $label,
					'menu-item-object'    => 'page',
					'menu-item-object-id' => $page_id,
					'menu-item-type'      => 'post_type',
					'menu-item-status'    => 'publish',
				) );
				if ( ! is_wp_error( $r ) ) {
					$added++;
				}
			}
			$menu = wp_get_nav_menu_object( $menu_id );
			wp_send_json_success( array(
				'menu'    => $menu ? $menu->name : '',
				'added'   => $added,
				'edit'    => admin_url( 'nav-menus.php?action=edit&menu=' . $menu_id ),
				/* translators: %s: menu name */
				'message' => sprintf( __( 'Store, Cart & Checkout and My Account are in "%s".', 'wp-easycart' ), $menu ? $menu->name : '' ),
			) );
		}

		/** Create a draft Terms or Privacy page from a starter outline. */
		public function ajax_create_page() {
			$this->ajax_guard();
			$type = isset( $_POST['type'] ) ? sanitize_key( $_POST['type'] ) : '';
			$name = get_bloginfo( 'name' );

			if ( 'terms' == $type ) {
				$title   = __( 'Terms & Conditions', 'wp-easycart' );
				$content = '<h2>' . esc_html__( 'Orders and payment', 'wp-easycart' ) . '</h2><p>' . esc_html__( 'Describe how orders are accepted, when payment is taken, and which payment methods you accept.', 'wp-easycart' ) . '</p>'
					. '<h2>' . esc_html__( 'Shipping and delivery', 'wp-easycart' ) . '</h2><p>' . esc_html__( 'State processing times, carriers, regions you ship to, and who is responsible for duties or lost parcels.', 'wp-easycart' ) . '</p>'
					. '<h2>' . esc_html__( 'Returns and refunds', 'wp-easycart' ) . '</h2><p>' . esc_html__( 'Explain your return window, condition requirements, who pays return shipping, and how refunds are issued.', 'wp-easycart' ) . '</p>'
					. '<h2>' . esc_html__( 'Contact', 'wp-easycart' ) . '</h2><p>' . esc_html( sprintf( __( 'How customers can reach %s with questions about an order.', 'wp-easycart' ), $name ) ) . '</p>';
			} else if ( 'privacy' == $type ) {
				$title   = __( 'Privacy Policy', 'wp-easycart' );
				$content = '<h2>' . esc_html__( 'What we collect', 'wp-easycart' ) . '</h2><p>' . esc_html__( 'List the information collected at checkout and account creation: name, email, addresses, phone, and order history.', 'wp-easycart' ) . '</p>'
					. '<h2>' . esc_html__( 'How we use it', 'wp-easycart' ) . '</h2><p>' . esc_html__( 'Fulfilling orders, sending order emails, customer support, and any marketing customers opt into.', 'wp-easycart' ) . '</p>'
					. '<h2>' . esc_html__( 'Payment processing', 'wp-easycart' ) . '</h2><p>' . esc_html__( 'Name the payment providers you use and note that card details are handled by them, not stored on this site.', 'wp-easycart' ) . '</p>'
					. '<h2>' . esc_html__( 'Your choices', 'wp-easycart' ) . '</h2><p>' . esc_html__( 'How customers can view, update, or delete their information and unsubscribe from emails.', 'wp-easycart' ) . '</p>';
			} else {
				wp_send_json_error( array( 'message' => __( 'Unknown page type.', 'wp-easycart' ) ) );
			}

			$id = wp_insert_post( array(
				'post_title'   => $title,
				'post_content' => $content,
				'post_status'  => 'draft',
				'post_type'    => 'page',
			), true );
			if ( is_wp_error( $id ) ) {
				wp_send_json_error( array( 'message' => $id->get_error_message() ) );
			}
			if ( 'privacy' == $type && ! get_option( 'wp_page_for_privacy_policy' ) ) {
				update_option( 'wp_page_for_privacy_policy', $id );
			}
			wp_send_json_success( array(
				'id'    => $id,
				/* translators: %s: page title */
				'title' => sprintf( __( '%s (draft)', 'wp-easycart' ), $title ),
				'edit'  => get_edit_post_link( $id, '' ),
			) );
		}

		/** Send a test order-style email through wp_mail() using the store's From address. */
		public function ajax_send_test_email() {
			$this->ajax_guard();
			$to = isset( $_POST['to'] ) ? sanitize_text_field( wp_unslash( $_POST['to'] ) ) : '';
			$to = array_values( array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( ',', $to ) ) ), 'is_email' ) );
			if ( empty( $to ) ) {
				$to = array( get_option( 'admin_email' ) );
			}
			$from = isset( $_POST['from'] ) ? sanitize_email( wp_unslash( $_POST['from'] ) ) : self::real_option( 'ec_option_order_from_email' );
			if ( ! is_email( $from ) ) {
				$from = get_option( 'admin_email' );
			}
			$headers = array( 'Content-Type: text/html; charset=UTF-8', 'From: ' . get_bloginfo( 'name' ) . ' <' . $from . '>', 'Reply-To: ' . $from );
			/* translators: %s: site name */
			$subject = sprintf( __( '[%s] Test email from WP EasyCart', 'wp-easycart' ), get_bloginfo( 'name' ) );
			$body    = '<p>' . esc_html__( 'If you are reading this, order emails from your store can reach this inbox.', 'wp-easycart' ) . '</p>'
				. '<p>' . esc_html__( 'Sent by WP EasyCart via wp_mail() from', 'wp-easycart' ) . ' <strong>' . esc_html( $from ) . '</strong> ' . esc_html__( 'at', 'wp-easycart' ) . ' ' . esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ) . '.</p>'
				. '<p style="color:#6b7280;font-size:12px">' . esc_html__( 'If this landed in spam, consider an SMTP plugin or a transactional email service. See Settings › Email.', 'wp-easycart' ) . '</p>';

			$sent = wp_mail( $to, $subject, $body, $headers );
			if ( ! $sent ) {
				wp_send_json_error( array( 'message' => __( 'wp_mail() reported a failure. Your host may be blocking outgoing mail; see Settings › Email for SMTP options.', 'wp-easycart' ) ) );
			}
			$this->set_checklist_state( 'email_tested', time() );
			wp_send_json_success( array(
				'to'      => implode( ', ', $to ),
				'time'    => wp_date( get_option( 'time_format' ) ),
				/* translators: 1: recipient, 2: time */
				'message' => sprintf( __( 'Test email sent to %1$s at %2$s. Check spam if it isn\'t in your inbox within a minute.', 'wp-easycart' ), '<strong>' . esc_html( implode( ', ', $to ) ) . '</strong>', esc_html( wp_date( get_option( 'time_format' ) ) ) ),
			) );
		}

		/** Dismiss / restore an optional checklist item. */
		public function ajax_checklist() {
			$this->ajax_guard();
			$key    = isset( $_POST['item'] ) ? sanitize_key( $_POST['item'] ) : '';
			$action = isset( $_POST['do'] ) ? sanitize_key( $_POST['do'] ) : 'dismiss';
			$items  = $this->get_checklist();
			if ( ! isset( $items[ $key ] ) ) {
				wp_send_json_error( array( 'message' => __( 'Unknown item.', 'wp-easycart' ) ) );
			}
			$this->set_checklist_state( 'dismissed_' . $key, ( 'dismiss' == $action ) ? time() : 0 );
			wp_send_json_success( array( 'remaining' => $this->count_checklist_remaining() ) );
		}

		/* =====================================================================
		   ASSETS
		   ===================================================================== */

		public function enqueue_assets() {
			if ( ! $this->is_wizard_screen() && ! $this->is_store_status_screen() ) {
				return;
			}
			wp_register_style( 'wp_easycart_setup_wizard_v2_css', plugins_url( 'wp-easycart/admin/css/setup-wizard-v2.css', EC_PLUGIN_DIRECTORY ), array( 'wp_easycart_admin_css' ), EC_CURRENT_VERSION );
			wp_enqueue_style( 'wp_easycart_setup_wizard_v2_css' );

			$deps = array( 'jquery' );
			if ( wp_script_is( 'wp_easycart_admin_select2_js', 'registered' ) ) {
				$deps[] = 'wp_easycart_admin_select2_js';
			}
			wp_register_script( 'wp_easycart_setup_wizard_v2_js', plugins_url( 'wp-easycart/admin/js/setup-wizard-v2.js', EC_PLUGIN_DIRECTORY ), $deps, EC_CURRENT_VERSION, true );
			wp_localize_script( 'wp_easycart_setup_wizard_v2_js', 'wpEasyCartWizardData', array(
				'ajax_url'    => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( self::AJAX_NONCE ),
				'terms_nonce' => wp_create_nonce( 'wp-easycart-terms-accept' ),
				'i18n'        => array(
					'sending'     => __( 'Sending…', 'wp-easycart' ),
					'send_again'  => __( 'Send again', 'wp-easycart' ),
					'sent'        => __( 'Sent', 'wp-easycart' ),
					'added'       => __( 'Added', 'wp-easycart' ),
					'working'     => __( 'Working…', 'wp-easycart' ),
					'edit_menu'   => __( 'Edit menu', 'wp-easycart' ),
					'edit_page'   => __( 'Edit page', 'wp-easycart' ),
					'draft_made'  => __( 'Draft created', 'wp-easycart' ),
					'error'       => __( 'Something went wrong. Please try again.', 'wp-easycart' ),
					'accept_first'=> __( 'Accept the terms above to connect a gateway.', 'wp-easycart' ),
					'select_state'=> __( 'Select a state / province', 'wp-easycart' ),
				),
			) );
			wp_enqueue_script( 'wp_easycart_setup_wizard_v2_js' );
		}
	}
endif;

function wp_easycart_admin_setup_wizard() {
	return wp_easycart_admin_setup_wizard::instance();
}
wp_easycart_admin_setup_wizard();