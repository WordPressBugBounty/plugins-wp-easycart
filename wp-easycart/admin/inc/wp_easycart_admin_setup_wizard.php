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

			include( EC_PLUGIN_DIRECTORY . '/admin/template/settings/wizard/wizard-frame.php' );
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

		/**
		 * Shared button bar for steps 1-4.
		 *
		 * 6.0.5: Save & exit saves the step ( it was a link that left without saving ). Back, the stepper and the summary's Edit links
		 * carry data-ecwz-goto: with changes on the page, setup-wizard-v2.js asks first and can save them on the way ( wizard_goto ).
		 * A hidden first submit button keeps Enter in a field on Continue.
		 */
		public function render_footer( $step, $primary_label = '' ) {
			if ( '' == $primary_label ) {
				$primary_label = __( 'Continue', 'wp-easycart' );
			}
			echo '<input type="hidden" name="wizard_goto" id="ecwz_goto" value="">';
			echo '<div class="ecwz-foot">';
			echo '<button type="submit" class="ecwz-default-submit" tabindex="-1" aria-hidden="true">' . esc_html( $primary_label ) . '</button>';
			if ( $step > self::STEP_LOCATION ) {
				echo '<a class="ecwz-btn ecwz-btn-ghost" href="' . esc_url( $this->step_url( $step - 1 ) ) . '" data-ecwz-goto="' . esc_attr( $step - 1 ) . '">&larr; ' . esc_html__( 'Back', 'wp-easycart' ) . '</a>';
			}
			/* translators: 1: current step, 2: total steps */
			echo '<span class="ecwz-hint">' . esc_html( sprintf( __( 'Step %1$d of %2$d', 'wp-easycart' ), $step, self::STEP_FINISH ) ) . '</span>';
			echo '<span class="ecwz-grow"></span>';
			echo '<button type="submit" class="ecwz-btn ecwz-btn-ghost" name="wizard_goto" value="exit" data-ecwz-exit="1">' . esc_html__( 'Save & exit', 'wp-easycart' ) . '</button>';
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
					'action_url'   => admin_url( 'admin.php?page=wp-easycart-products&subpage=products' ),
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
				/* 6.0.0: "Connect Google Analytics" was dropped from the launch checklist. It is not needed to open a store,
				   and it lives in Settings > Integrations for the stores that want it. */
			);

			/* 6.0.5: tax set up as the store's country expects ( wp_easycart_admin_tax_setup ), before "Recommended settings healthy". */
			$tax_item = class_exists( 'wp_easycart_admin_tax_setup' ) ? wp_easycart_admin_tax_setup::checklist_item() : null;
			if ( $tax_item ) {
				$ordered = array();
				foreach ( $items as $k => $item ) {
					if ( 'rec' === $k ) {
						$ordered['tax'] = $tax_item;
					}
					$ordered[ $k ] = $item;
				}
				$items = $ordered;
			}

			// Moving from Square or WooCommerce: suggest the cart importer right after "Create your first product".
			$importer = $this->get_cart_importer_suggestion();
			if ( $importer ) {
				$ordered = array();
				foreach ( $items as $k => $item ) {
					$ordered[ $k ] = $item;
					if ( 'product' === $k ) {
						$ordered['importer'] = $importer;
					}
				}
				$items = $ordered;
			}

			foreach ( $items as $k => $item ) {
				$items[ $k ]['key']       = $k;
				$items[ $k ]['dismissed'] = ! empty( $state[ 'dismissed_' . $k ] );
			}
			return apply_filters( 'wp_easycart_setup_checklist', $items );
		}

		/**
		 * Launch checklist item pointing a new store at the cart importer ( Settings › Integrations ›
		 * Cart importer ) when there is something to import from.
		 *
		 * Shown only while all of these hold:
		 * - the store is new: no real ( non-demo ) orders and at most
		 *   `wp_easycart_cart_importer_suggest_max_products` ( default 3 ) real products. An import
		 *   pushes the product count past that, so the item disappears once it has run;
		 * - there is a source: Square is the live gateway or has an access token ( live or sandbox ),
		 *   and / or WooCommerce is active or its data is still in the database ( the importer reads
		 *   the tables directly, so a deactivated WooCommerce still counts ).
		 * It is optional, so the merchant can dismiss it. It is done once a product carries a Square
		 * id or the WooCommerce importer has run.
		 *
		 * @since 6.0.0
		 *
		 * @return array|null Checklist item ( without key / dismissed ), or null when it does not apply.
		 */
		public function get_cart_importer_suggestion() {
			global $wpdb;
			if ( ! apply_filters( 'wp_easycart_cart_importer_suggest', true ) ) {
				return null;
			}
			if ( $wpdb->get_var( 'SELECT order_id FROM ec_order WHERE is_demo_item = 0 LIMIT 1' ) ) {
				return null;
			}
			$max_products = max( 0, (int) apply_filters( 'wp_easycart_cart_importer_suggest_max_products', 3 ) );
			$products     = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ( SELECT product_id FROM ec_product WHERE is_demo_item = 0 LIMIT %d ) AS ec_recent_products', $max_products + 1 ) );
			if ( $products > $max_products ) {
				return null;
			}

			$has_square = self::has_square_source();
			$has_woo    = self::has_woocommerce_source();
			if ( ! $has_square && ! $has_woo ) {
				return null;
			}

			$done = false;
			if ( $has_square && $products > 0 && $wpdb->get_var( "SELECT product_id FROM ec_product WHERE square_id != '' LIMIT 1" ) ) {
				$done = true;
			}
			if ( $has_woo && get_option( 'ec_option_cart_importer_woo_imported' ) ) {
				$done = true;
			}

			if ( $has_square && $has_woo ) {
				$label = __( 'Import your products from Square or WooCommerce', 'wp-easycart' );
				$sub   = __( 'Optional. Bring in products, categories and options instead of re-entering them', 'wp-easycart' );
			} elseif ( $has_square ) {
				$label = __( 'Import your products from Square', 'wp-easycart' );
				$sub   = __( 'Optional. Bring in your Square catalogue, modifiers and stock', 'wp-easycart' );
			} else {
				$label = __( 'Import your products from WooCommerce', 'wp-easycart' );
				$sub   = __( 'Optional. Bring in products, categories and attributes from this site', 'wp-easycart' ); /* 6.0.3: the importer reads WooCommerce's data with the plugin switched off too */
			}

			return array(
				'label'        => $label,
				'sub'          => $sub,
				'done'         => $done,
				'optional'     => true,
				'action_label' => __( 'Open importer', 'wp-easycart' ),
				'action_url'   => admin_url( 'admin.php?page=wp-easycart-products&subpage=import' . ( $has_square && ! $has_woo ? '&source=square' : ( $has_woo && ! $has_square ? '&source=woocommerce' : '' ) ) ), /* 6.0.3 */
				'sources'      => array_keys(
					array_filter(
						array(
							'square'      => $has_square,
							'woocommerce' => $has_woo,
						)
					)
				),
			);
		}

		/**
		 * Square is the live gateway, or an access token ( live or sandbox ) is stored.
		 *
		 * @since 6.0.0
		 */
		public static function has_square_source() {
			return 'square' === get_option( 'ec_option_payment_process_method' )
				|| '' !== (string) get_option( 'ec_option_square_access_token', '' )
				|| '' !== (string) get_option( 'ec_option_square_sandbox_access_token', '' );
		}

		/**
		 * WooCommerce is active, or its attribute table and at least one `product` post are still in the
		 * database ( the importer reads both ). The table lookup is cached for six hours.
		 *
		 * @since 6.0.0
		 */
		public static function has_woocommerce_source() {
			if ( class_exists( 'WooCommerce' ) ) {
				return true;
			}
			$cached = get_transient( 'wp_easycart_woo_source_detected' );
			if ( 'yes' === $cached || 'no' === $cached ) {
				return 'yes' === $cached;
			}
			global $wpdb;
			$table = $wpdb->prefix . 'woocommerce_attribute_taxonomies';
			$found = ( $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) )
				&& (bool) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status IN ( 'publish', 'private', 'draft', 'pending' ) LIMIT 1" );
			set_transient( 'wp_easycart_woo_source_detected', $found ? 'yes' : 'no', 6 * HOUR_IN_SECONDS );
			return $found;
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
		   COUNTRY DATA ( 6.0.5: one copy for the location step and its save )
		   ===================================================================== */

		/** Currencies written without decimals, and with three ( ISO 4217 ). */
		const ZERO_DECIMALS  = array( 'BIF', 'CLP', 'DJF', 'GNF', 'ISK', 'JPY', 'KMF', 'KRW', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF' );
		const THREE_DECIMALS = array( 'BHD', 'IQD', 'JOD', 'KWD', 'LYD', 'OMR', 'TND' );

		/** Countries that weigh in pounds and measure in inches ( the US and its territories, Liberia, Myanmar ). */
		const IMPERIAL = array( 'US', 'PR', 'GU', 'VI', 'AS', 'MP', 'UM', 'LR', 'MM' );

		/** What the wizard remembers of its own answers ( the country it pinned, zones it added or took away, where the store ships ). */
		const OPT_ANSWERS = 'ec_option_wizard_answers';

		/** @var array|null admin/data/setup-wizard-countries.php */
		private static $data = null;

		/**
		 * The wizard's country data: countries, formats, states, currency_names, currency_symbols, currency_of.
		 *
		 * @since 6.0.5
		 * @param string $part Key.
		 * @return array
		 */
		public static function data( $part ) {
			if ( null === self::$data ) {
				$file       = EC_PLUGIN_DIRECTORY . '/admin/data/setup-wizard-countries.php';
				self::$data = file_exists( $file ) ? (array) include $file : array();
			}
			return isset( self::$data[ $part ] ) ? (array) self::$data[ $part ] : array();
		}

		/**
		 * The currency a country prices in.
		 *
		 * @since 6.0.5
		 * @param string $cc ISO code.
		 * @return string ISO 4217 code.
		 */
		public static function currency_for( $cc ) {
			$cc   = strtoupper( (string) $cc );
			$map  = self::data( 'currency_of' );
			$code = isset( $map[ $cc ] ) ? (string) $map[ $cc ] : 'USD';
			/**
			 * The currency the setup wizard offers a store in a country.
			 *
			 * @since 6.0.5
			 * @param string $code ISO 4217 code.
			 * @param string $cc   ISO country code.
			 */
			return (string) apply_filters( 'wp_easycart_setup_wizard_country_currency', $code, $cc );
		}

		/**
		 * What the wizard saves for a store in a country: its currency, how amounts are written ( the country's way ), the currency's
		 * decimals, and the units ( pounds and inches in the US, kilograms and centimetres elsewhere ).
		 *
		 * @since 6.0.5
		 * @param string $cc       ISO code.
		 * @param string $currency ISO 4217 code ( default: the country's ).
		 * @return array currency, before, space, dec, thou, decimals, weight ( lbs | kgs ), metric
		 */
		public static function facts( $cc, $currency = '' ) {
			$cc       = strtoupper( (string) $cc );
			$own      = self::currency_for( $cc );
			$currency = '' !== (string) $currency ? strtoupper( (string) $currency ) : $own;
			$formats  = self::data( 'formats' );
			$row      = isset( $formats[ $cc ] ) ? $formats[ $cc ] : null;
			$format   = ( class_exists( 'ec_currency' ) && method_exists( 'ec_currency', 'country_format' ) ) ? ec_currency::country_format( $cc ) : null;
			if ( ! is_array( $format ) ) {
				$pos    = $row ? (string) $row[0] : 'left';
				$format = array(
					'before' => 0 === strpos( $pos, 'left' ),
					'space'  => false !== strpos( $pos, '_space' ),
					'dec'    => $row ? (string) $row[2] : '.',
					'thou'   => $row ? ( ' ' === $row[1] ? "\xC2\xA0" : (string) $row[1] ) : ',',
				);
			}
			if ( in_array( $currency, self::ZERO_DECIMALS, true ) ) {
				$decimals = 0;
			} elseif ( in_array( $currency, self::THREE_DECIMALS, true ) ) {
				$decimals = 3;
			} else {
				/* The older table's decimals hold for the country's own currency ( forints are written without ). */
				$decimals = ( $row && $currency === $own ) ? (int) $row[3] : 2;
			}
			$imperial = in_array( $cc, self::IMPERIAL, true );
			$facts    = array(
				'currency' => $currency,
				'before'   => (bool) $format['before'],
				'space'    => (bool) $format['space'],
				'dec'      => (string) $format['dec'],
				'thou'     => (string) $format['thou'],
				'decimals' => $decimals,
				'weight'   => $imperial ? 'lbs' : 'kgs',
				'metric'   => ! $imperial,
			);
			/**
			 * What the setup wizard saves for a store in a country.
			 *
			 * @since 6.0.5
			 * @param array  $facts currency, before, space, dec, thou, decimals, weight ( lbs | kgs ), metric.
			 * @param string $cc    ISO country code.
			 */
			return (array) apply_filters( 'wp_easycart_setup_wizard_country_facts', $facts, $cc );
		}

		/**
		 * The wizard's own answers ( what it did, so it can undo it when the merchant changes an answer ).
		 *
		 * @since 6.0.5
		 * @return array
		 */
		public function answers() {
			$answers = get_option( self::OPT_ANSWERS, array() );
			return is_array( $answers ) ? $answers : array();
		}

		/**
		 * Keep ( or with null forget ) one of the wizard's answers.
		 *
		 * @param string $key   Key.
		 * @param mixed  $value Value, or null.
		 */
		private function remember( $key, $value ) {
			$answers = $this->answers();
			if ( null === $value ) {
				unset( $answers[ $key ] );
			} else {
				$answers[ $key ] = $value;
			}
			update_option( self::OPT_ANSWERS, $answers, false );
		}

		/**
		 * The languages the store can show shoppers: every language shipped with WP EasyCart ( file => name ).
		 *
		 * @since 6.0.5
		 * @return array
		 */
		public static function store_languages() {
			$out = array();
			if ( class_exists( 'wp_easycart_admin_language_v2' ) ) {
				$names = wp_easycart_admin_language_v2::names();
				foreach ( wp_easycart_admin_language_v2::shipped_files() as $file ) {
					$out[ $file ] = isset( $names[ $file ] ) ? $names[ $file ] : $file;
				}
				foreach ( wp_easycart_admin_language_v2::installed() as $file => $name ) {
					if ( ! isset( $out[ $file ] ) ) {
						$out[ $file ] = $name;
					}
				}
			}
			if ( empty( $out ) ) {
				$out['en-us'] = __( 'English (US)', 'wp-easycart' );
			}
			asort( $out );
			return $out;
		}

		/**
		 * The language the location step offers: the store's, or on a first run the WordPress site's language when WP EasyCart ships it
		 * ( a German site gets German, not US English ).
		 *
		 * @since 6.0.5
		 * @return string Language file.
		 */
		public function store_language_choice() {
			$current = sanitize_key( (string) get_option( 'ec_option_language' ) );
			$current = '' !== $current ? $current : 'en-us';
			if ( 'en-us' !== $current || $this->completed_through() >= self::STEP_LOCATION ) {
				return $current;
			}
			$map    = array(
				'de' => 'german',
				'fr' => 'fr-fr',
				'es' => 'es',
				'nl' => 'dutch',
				'da' => 'da-dk',
				'el' => 'greek',
				'hu' => 'hu-hun',
				'lv' => 'lv-lat',
				'ru' => 'ru-rus',
			);
			$locale = (string) get_locale();
			$lang   = strtolower( substr( $locale, 0, 2 ) );
			if ( in_array( $locale, array( 'zh_TW', 'zh_HK' ), true ) ) {
				$lang = 'zh-tw';
				$map['zh-tw'] = 'ch-tr';
			}
			$languages = self::store_languages();
			return ( isset( $map[ $lang ] ) && isset( $languages[ $map[ $lang ] ] ) ) ? $map[ $lang ] : $current;
		}

		/**
		 * Switch the storefront's language, installing a shipped one first.
		 *
		 * @param string $file Language file.
		 * @return bool Changed.
		 */
		private function save_store_language( $file ) {
			$file      = sanitize_key( (string) $file );
			$languages = self::store_languages();
			if ( '' === $file || ! isset( $languages[ $file ] ) || $file === (string) get_option( 'ec_option_language' ) ) {
				return false;
			}
			$installed = class_exists( 'wp_easycart_admin_language_v2' ) ? wp_easycart_admin_language_v2::installed() : array();
			if ( function_exists( 'wp_easycart_language' ) && ! isset( $installed[ $file ] ) ) {
				wp_easycart_language()->add_new_language( $file );
			}
			update_option( 'ec_option_language', $file );
			$this->remember( 'language', $file );
			return true;
		}

		/**
		 * After a step is saved: the step the merchant asked for ( Back, a step in the stepper, Edit in the summary ), Save & exit's
		 * products list, else the next step.
		 *
		 * @since 6.0.5
		 * @param int $next The next step.
		 */
		private function go_next( $next ) {
			$goto = isset( $_POST['wizard_goto'] ) ? sanitize_key( wp_unslash( $_POST['wizard_goto'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- called by the step handlers after their nonce check.
			if ( 'exit' === $goto ) {
				wp_redirect( $this->exit_url() );
			} elseif ( '' !== $goto && ctype_digit( $goto ) && (int) $goto >= self::STEP_LOCATION && (int) $goto <= self::STEP_DONE ) {
				wp_redirect( $this->step_url( (int) $goto ) );
			} else {
				wp_redirect( $this->step_url( $next ) );
			}
			exit;
		}

		/* =====================================================================
		   PRESETS & GATEWAY STATE ( shared by templates and handlers )
		   ===================================================================== */

		public function get_shipping_presets() {
			$presets = array(
				'static' => array(
					'label' => __( 'Flat rates', 'wp-easycart' ),
					'sub'   => __( 'Recommended to start', 'wp-easycart' ),
					'desc'  => __( 'Customers choose a service level. Simple and predictable.', 'wp-easycart' ),
					'rates' => array(
						/* 6.0.5: names shoppers anywhere understand ( shown at checkout ); the store adds its own delivery times. */
						array( 'shipping_label' => __( 'Standard shipping', 'wp-easycart' ), 'shipping_order' => 1, 'shipping_rate' => '7.99' ),
						array( 'shipping_label' => __( 'Express shipping', 'wp-easycart' ), 'shipping_order' => 2, 'shipping_rate' => '14.99' ),
						array( 'shipping_label' => __( 'Next-day shipping', 'wp-easycart' ), 'shipping_order' => 3, 'shipping_rate' => '19.99' ),
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
			/* 6.0.5: the weight tiers in the store's unit. A kilogram store starts at about the same weights ( 10, 25, 50, 250 kg; it
			   started at 20 to 500 kg ), and an ounce or gram store gets the pound or kilogram tiers in its own unit. */
			if ( class_exists( 'wp_easycart_packages' ) && method_exists( 'wp_easycart_packages', 'carrier_factor' ) ) {
				$kg_tiers = array( '0.00', '10.00', '25.00', '50.00', '250.00' );
				$is_kg    = method_exists( 'wp_easycart_packages', 'carrier_unit' ) && 'kg' === wp_easycart_packages::carrier_unit();
				$factor   = (float) wp_easycart_packages::carrier_factor();
				foreach ( $presets['weight']['rates'] as $i => $rate ) {
					$tier = $is_kg ? (float) $kg_tiers[ $i ] : (float) $rate['trigger_rate'];
					$presets['weight']['rates'][ $i ]['trigger_rate'] = number_format( $factor > 0 ? $tier / $factor : $tier, 2, '.', '' );
				}
			}
			return $presets;
		}

		/**
		 * Existing ( non-demo ) shipping rate rows per calculation type.
		 * @return array { static:int, price:int, weight:int }
		 */
		/* ------------------------------------------------------------------ */
		/* 6.0.5 ( admin review M10 ): where the store ships, and its country first */
		/* ------------------------------------------------------------------ */

		/** Places nobody ships to ( Antarctica, Bouvet Island, South Georgia, Heard and McDonald Islands, the French Southern and US Minor Outlying Islands ). */
		const NO_DELIVERY = array( 'AQ', 'BV', 'GS', 'HM', 'TF', 'UM' );

		/**
		 * The store's region for the ship-to question: the largest default zone ( a continent ) that holds the store's country.
		 *
		 * @param string $country ISO code.
		 * @return array|null name, countries
		 */
		public function ship_to_region( $country ) {
			global $wpdb;
			$zone = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT z.zone_id, z.zone_name, COUNT( DISTINCT a.iso2_cnt ) AS n FROM ec_zone z
					INNER JOIN ec_zone_to_location l ON l.zone_id = z.zone_id AND l.iso2_cnt = %s AND ( l.code_sta = '' OR l.code_sta = '-1' )
					INNER JOIN ec_zone_to_location a ON a.zone_id = z.zone_id
					WHERE NOT EXISTS ( SELECT 1 FROM ec_zone_to_location s WHERE s.zone_id = z.zone_id AND s.code_sta <> '' AND s.code_sta <> '-1' )
					GROUP BY z.zone_id, z.zone_name HAVING n > 1 ORDER BY n DESC LIMIT 1",
					$country
				)
			);
			if ( ! $zone ) {
				return null;
			}
			/* Only codes the store lists as countries ( the default Europe zone also holds a stray "DC" ). */
			$countries = array_map( 'strtoupper', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT l.iso2_cnt FROM ec_zone_to_location l INNER JOIN ec_country c ON c.iso2_cnt = l.iso2_cnt WHERE l.zone_id = %d', (int) $zone->zone_id ) ) );
			$countries = array_values( array_diff( array_unique( array_merge( $countries, array( $country ) ) ), self::NO_DELIVERY ) );
			return array( 'name' => (string) $zone->zone_name, 'countries' => $countries );
		}

		/**
		 * The countries switched on for delivery now, sorted.
		 *
		 * @return array
		 */
		private function ship_to_active() {
			global $wpdb;
			$active = array_map( 'strtoupper', (array) $wpdb->get_col( 'SELECT iso2_cnt FROM ec_country WHERE ship_to_active = 1' ) );
			sort( $active );
			return $active;
		}

		/**
		 * The ship-to choices for the store's country, and which one to show chosen.
		 *
		 * 6.0.5: the answer the wizard gave last is shown again while the list is still the one it made, for the store's country now
		 * ( a store that shipped only within the UK and changes its country to Germany is offered "Only within Germany", where the list
		 * matched no choice and "Worldwide" was ticked ). A list the merchant changed under Countries & Regions matches no choice: then
		 * "Keep the countries I ship to now" ( keep ) is offered and chosen, so continuing never undoes it.
		 *
		 * @return array country, name, region ( array|null ), current country | region | world | keep | '', count
		 */
		public function ship_to_state() {
			$country = class_exists( 'wp_easycart_carriers' ) ? strtoupper( (string) wp_easycart_carriers::store_country() ) : '';
			$active  = $this->ship_to_active();
			if ( '' === $country ) {
				return array( 'country' => '', 'name' => '', 'region' => null, 'current' => '', 'count' => count( $active ) );
			}
			$region  = $this->ship_to_region( $country );
			$current = 'keep';
			$world   = $this->ship_to_list( 'world', $country, $region );
			if ( array( $country ) === $active ) {
				$current = 'country';
			} elseif ( $region && $this->ship_to_list( 'region', $country, $region ) === $active ) {
				$current = 'region';
			} elseif ( $world === $active || count( $active ) >= count( $world ) ) {
				$current = 'world';
			}
			$answers = $this->answers();
			if ( isset( $answers['ship_to'], $answers['ship_to_list'] ) && (array) $answers['ship_to_list'] === $active && in_array( $answers['ship_to'], array( 'country', 'region', 'world' ), true ) ) {
				$current = ( 'region' === $answers['ship_to'] && ! $region ) ? 'world' : $answers['ship_to'];
			}
			return array(
				'country' => $country,
				'name'    => class_exists( 'wp_easycart_address' ) ? wp_easycart_address::country_name( $country ) : $country,
				'region'  => $region,
				'current' => $current,
				'count'   => count( $active ),
			);
		}

		/**
		 * The countries a choice switches on, sorted.
		 *
		 * @param string     $choice  country | region | world.
		 * @param string     $country ISO code.
		 * @param array|null $region  ship_to_region().
		 * @return array
		 */
		private function ship_to_list( $choice, $country, $region ) {
			global $wpdb;
			if ( 'country' === $choice ) {
				return array( $country );
			}
			if ( 'region' === $choice && $region ) {
				$list = $region['countries'];
			} else {
				$list = array_values( array_diff( array_map( 'strtoupper', (array) $wpdb->get_col( 'SELECT iso2_cnt FROM ec_country' ) ), self::NO_DELIVERY ) );
			}
			$list = array_values( array_filter( array_unique( array_map( 'strtoupper', $list ) ), function ( $c ) {
				return (bool) preg_match( '/^[A-Z]{2}$/', $c );
			} ) );
			sort( $list );
			return $list;
		}

		/**
		 * Switches on the countries the store ships to: its own ( country ), its region ( region ) or everywhere people live ( world ).
		 * keep leaves the list as it is.
		 *
		 * @param string $choice country | region | world | keep.
		 * @return int countries switched on
		 */
		public function apply_ship_to( $choice ) {
			global $wpdb;
			$state = $this->ship_to_state();
			if ( '' === $state['country'] || ! in_array( $choice, array( 'country', 'region', 'world' ), true ) ) {
				return count( $this->ship_to_active() );
			}
			if ( 'region' === $choice && ! $state['region'] ) {
				$choice = 'world';
			}
			$keep = $this->ship_to_list( $choice, $state['country'], $state['region'] );
			if ( 'world' === $choice ) {
				$wpdb->query( 'UPDATE ec_country SET ship_to_active = 1' );
				$wpdb->query( "UPDATE ec_country SET ship_to_active = 0 WHERE iso2_cnt IN ( '" . implode( "', '", self::NO_DELIVERY ) . "' )" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- a fixed list of codes.
			} else {
				$wpdb->query( 'UPDATE ec_country SET ship_to_active = 0' );
				$wpdb->query( "UPDATE ec_country SET ship_to_active = 1 WHERE iso2_cnt IN ( '" . implode( "', '", $keep ) . "' )" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- two-letter codes checked in ship_to_list().
			}
			if ( function_exists( 'wp_cache_delete' ) ) {
				wp_cache_delete( 'wpeasycart-countries' );
			}
			/* 6.0.5: remember the answer and the list it made, so the step shows it again ( ship_to_state() ). */
			$this->remember( 'ship_to', $choice );
			$this->remember( 'ship_to_list', $this->ship_to_active() );
			return count( $this->ship_to_active() );
		}

		/**
		 * The store's country first in country lists ( checkout, Countries & Regions ), only while the lists still have the order a
		 * new store starts with ( the US, Canada, Australia, then A to Z ).
		 *
		 * 6.0.5: a country the wizard pinned before goes back to its place first, so changing the store's country on the way back moves
		 * the new one to the top ( the first country chosen used to stay there, also when the store turned out to be in the US ).
		 *
		 * @param string $country ISO code.
		 * @return bool changed
		 */
		public function pin_store_country( $country ) {
			global $wpdb;
			$country = strtoupper( (string) $country );
			if ( ! preg_match( '/^[A-Z]{2}$/', $country ) ) {
				return false;
			}
			$changed = false;
			$pinned  = array_map( 'strtoupper', (array) $wpdb->get_col( 'SELECT iso2_cnt FROM ec_country WHERE sort_order < 10 ORDER BY sort_order ASC' ) );
			$first   = isset( $pinned[0] ) ? $pinned[0] : '';
			$rest    = array_values( array_diff( $pinned, array( $first ) ) );
			$shape   = count( $pinned ) === ( in_array( $first, array( 'CA', 'AU' ), true ) ? 3 : 4 ) && $rest === array_values( array_diff( array( 'US', 'CA', 'AU' ), array( $first ) ) );
			if ( '' !== $first && 'US' !== $first && $first !== $country && $shape ) {
				/* The order pin_store_country() left for another country: put that country back where it was. */
				if ( ! in_array( $first, array( 'CA', 'AU' ), true ) ) {
					$answers = $this->answers();
					$pin     = isset( $answers['pin'] ) ? $answers['pin'] : null;
					$was = ( is_array( $pin ) && isset( $pin['country'], $pin['sort'] ) && $first === $pin['country'] && (int) $pin['sort'] >= 10 ) ? (int) $pin['sort'] : $this->free_sort_order();
					$wpdb->update( 'ec_country', array( 'sort_order' => $was ), array( 'iso2_cnt' => $first ) );
				}
				$wpdb->query( "UPDATE ec_country SET sort_order = CASE iso2_cnt WHEN 'US' THEN 1 WHEN 'CA' THEN 2 ELSE 3 END WHERE iso2_cnt IN ( 'US', 'CA', 'AU' )" );
				$this->remember( 'pin', null );
				$pinned  = array( 'US', 'CA', 'AU' );
				$changed = true;
			}
			if ( array( 'US', 'CA', 'AU' ) === $pinned && 'US' !== $country ) {
				$was = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT sort_order FROM ec_country WHERE iso2_cnt = %s', $country ) );
				$wpdb->query( "UPDATE ec_country SET sort_order = 4 WHERE iso2_cnt IN ( 'US', 'CA', 'AU' )" );
				$wpdb->query( $wpdb->prepare( 'UPDATE ec_country SET sort_order = 1 WHERE iso2_cnt = %s', $country ) );
				/* The other two keep their place after the store's country, in their old order. */
				$wpdb->query( "UPDATE ec_country SET sort_order = CASE iso2_cnt WHEN 'US' THEN 2 WHEN 'CA' THEN 3 WHEN 'AU' THEN 4 ELSE sort_order END WHERE iso2_cnt IN ( 'US', 'CA', 'AU' ) AND iso2_cnt <> '" . esc_sql( $country ) . "'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- escaped above.
				$this->remember( 'pin', array( 'country' => $country, 'sort' => $was ) );
				$changed = true;
			}
			if ( $changed && function_exists( 'wp_cache_delete' ) ) {
				wp_cache_delete( 'wpeasycart-countries' );
			}
			return $changed;
		}

		/**
		 * The place in A to Z a country pinned before 6.0.5 had: the one sort order left free when it moved to the top.
		 *
		 * @return int
		 */
		private function free_sort_order() {
			global $wpdb;
			$expect = 10;
			foreach ( array_map( 'intval', (array) $wpdb->get_col( 'SELECT sort_order FROM ec_country WHERE sort_order >= 10 ORDER BY sort_order ASC' ) ) as $order ) {
				if ( $order > $expect ) {
					return $expect;
				}
				$expect = $order + 1;
			}
			return $expect;
		}

		/**
		 * Whether anything uses a shipping zone: a row of a table with a zone_id column, or flex fees and offers ( their rules can
		 * name zones ).
		 *
		 * @param int $zone_id Zone.
		 * @return bool
		 */
		private function zone_in_use( $zone_id ) {
			global $wpdb;
			foreach ( (array) $wpdb->get_col( "SHOW TABLES LIKE 'ec\\_%'" ) as $table ) {
				if ( in_array( $table, array( 'ec_zone', 'ec_zone_to_location', 'ec_timezone' ), true ) || ! preg_match( '/^ec_[a-z0-9_]+$/', $table ) ) {
					continue;
				}
				if ( $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM $table LIKE %s", 'zone_id' ) ) && (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE zone_id = %d", (int) $zone_id ) ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- a table name checked above.
					return true;
				}
			}
			foreach ( array( 'ec_fee', 'ec_offer' ) as $table ) {
				if ( $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) && (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- a fixed table name.
					return true;
				}
			}
			return false;
		}

		/**
		 * A new store outside the US gets a shipping zone for its own country, and loses the US-only zones it started with
		 * ( "Lower 48 States", "Alaska and Hawaii" ) while nothing uses them: no rate, no fee, no offer, no order yet.
		 *
		 * 6.0.5: when the store's country changes on the way back, the zone added for the country before goes ( while nothing uses it ),
		 * and a store that turns out to be in the US gets the US zones back.
		 *
		 * @param string $country ISO code.
		 * @return array added ( zone id or 0 ), removed ( count ), restored ( count ), dropped ( count )
		 */
		public function seed_zones( $country ) {
			global $wpdb;
			$country = strtoupper( (string) $country );
			$out     = array( 'added' => 0, 'removed' => 0, 'restored' => 0, 'dropped' => 0 );
			if ( ! preg_match( '/^[A-Z]{2}$/', $country ) ) {
				return $out;
			}
			$answers = $this->answers();
			$added   = isset( $answers['zones_added'] ) ? (array) $answers['zones_added'] : array();
			foreach ( $added as $cc => $zone_id ) {
				if ( $cc === $country ) {
					continue;
				}
				if ( ! $this->zone_in_use( (int) $zone_id ) ) {
					$wpdb->delete( 'ec_zone_to_location', array( 'zone_id' => (int) $zone_id ), array( '%d' ) );
					$out['dropped'] += (int) $wpdb->delete( 'ec_zone', array( 'zone_id' => (int) $zone_id ), array( '%d' ) );
				}
				unset( $added[ $cc ] );
			}
			if ( 'US' === $country ) {
				if ( ! empty( $answers['us_zones']['zones'] ) ) {
					foreach ( (array) $answers['us_zones']['zones'] as $row ) {
						if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT zone_id FROM ec_zone WHERE zone_id = %d', (int) $row['zone_id'] ) ) ) {
							$out['restored'] += (int) $wpdb->insert( 'ec_zone', (array) $row );
							foreach ( (array) $answers['us_zones']['locations'] as $location ) {
								if ( (int) $location['zone_id'] === (int) $row['zone_id'] ) {
									$wpdb->insert( 'ec_zone_to_location', (array) $location );
								}
							}
						}
					}
				}
				$this->remember( 'us_zones', null );
				$this->remember( 'zones_added', $added ? $added : null );
				return $out;
			}
			$own = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT z.zone_id FROM ec_zone z INNER JOIN ec_zone_to_location l ON l.zone_id = z.zone_id
					GROUP BY z.zone_id HAVING COUNT(*) = 1 AND MAX( l.iso2_cnt ) = %s AND MAX( l.code_sta ) IN ( '', '-1' )",
					$country
				)
			);
			if ( ! $own ) {
				$name = class_exists( 'wp_easycart_address' ) ? wp_easycart_address::country_name( $country ) : $country;
				$wpdb->insert( 'ec_zone', array( 'zone_name' => $name ) );
				$out['added'] = (int) $wpdb->insert_id;
				if ( $out['added'] ) {
					$wpdb->insert( 'ec_zone_to_location', array( 'zone_id' => $out['added'], 'iso2_cnt' => $country, 'code_sta' => '' ) );
					$added[ $country ] = $out['added'];
				}
			}
			$us_zones = $wpdb->get_results( "SELECT * FROM ec_zone WHERE ( zone_id = 8 AND zone_name = 'Lower 48 States' ) OR ( zone_id = 9 AND zone_name = 'Alaska and Hawaii' )", ARRAY_A );
			if ( $us_zones ) {
				$ids    = implode( ',', array_map( 'intval', wp_list_pluck( $us_zones, 'zone_id' ) ) );
				$in_use = (int) $wpdb->get_var( "SELECT COUNT(*) FROM ec_shippingrate WHERE zone_id IN ( $ids )" ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- integers.
					+ (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_order' );
				foreach ( array( 'ec_fee', 'ec_offer' ) as $table ) {
					if ( $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
						$in_use += (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- a fixed table name.
					}
				}
				if ( 0 === $in_use ) {
					/* Kept, so a store that turns out to be in the US gets them back. */
					$this->remember(
						'us_zones',
						array(
							'zones'     => $us_zones,
							'locations' => $wpdb->get_results( "SELECT * FROM ec_zone_to_location WHERE zone_id IN ( $ids )", ARRAY_A ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- integers.
						)
					);
					$wpdb->query( "DELETE FROM ec_zone_to_location WHERE zone_id IN ( $ids )" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- integers.
					$out['removed'] = (int) $wpdb->query( "DELETE FROM ec_zone WHERE zone_id IN ( $ids )" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- integers.
				}
			}
			$this->remember( 'zones_added', $added ? $added : null );
			if ( function_exists( 'wp_cache_delete' ) ) {
				wp_cache_delete( 'wpeasycart-zone-ids-' . $country . '-' );
			}
			return $out;
		}

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

		/**
		 * The free edition's offers: Start 14-day trial, Try Pro free, the locked live rates card.
		 *
		 * @since 6.0.2 Never for a store with a Pro or Premium license or a trial, current or lapsed: WP EasyCart PRO hid them
		 *              only during a trial, so paying stores were offered the trial.
		 * @return bool
		 */
		public function show_upsell() {
			if ( class_exists( 'wp_easycart_admin_edition' ) && 'free' !== wp_easycart_admin_edition::tier() ) {
				return false;
			}
			return '' != apply_filters( 'wp_easycart_trial_start_content', 'true' );
		}

		/**
		 * The badge on the Stripe, Square and PayPal cards: the WP EasyCart fee those gateways charge right now, read the way
		 * the gateways read it ( 2% on Free and on a lapsed license, none while a license or trial is active ).
		 *
		 * @since 6.0.2 Before, it followed show_upsell(), so a paying store saw "2% fee on Free" and a lapsed one "No EasyCart fees".
		 * @return string
		 */
		public function connect_fee_badge() {
			if ( 0 >= (float) apply_filters( 'wp_easycart_stripe_connect_fee_rate', 2 ) ) {
				return __( 'No EasyCart fees', 'wp-easycart' );
			}
			if ( class_exists( 'wp_easycart_admin_edition' ) && wp_easycart_admin_edition::is_lapsed() ) {
				return __( '2% fee until you renew', 'wp-easycart' );
			}
			return __( '2% fee on Free', 'wp-easycart' );
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

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- each step handler below reads $_POST only after wp_easycart_admin_verification()->verify_access() checked its nonce, a method the sniff cannot see.
		public function process_location_submit() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_settings' ) ) {
				return false;
			}
			if ( isset( $_POST['ec_admin_form_action'] ) && 'process-wizard-location' == $_POST['ec_admin_form_action'] ) {
				if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-process-wizard-location' ) ) {
					/* Posted as "CC" or "CC_ST". Split first, then validate the country part —
					   the previous check ran isset() on the combined string, which never matched
					   a state locale, so US state tax rates were silently skipped. */
					$countries        = self::data( 'countries' );
					$currency_symbols = self::data( 'currency_symbols' );
					$posted   = isset( $_POST['locale'] ) ? sanitize_text_field( wp_unslash( $_POST['locale'] ) ) : 'US';
					$exploded = explode( '_', $posted );
					$selected_locale       = ( isset( $countries[ $exploded[0] ] ) ) ? $exploded[0] : 'US';
					$selected_state_locale = ( count( $exploded ) > 1 ) ? preg_replace( '/[^A-Z0-9]/', '', strtoupper( $exploded[1] ) ) : '';
					$posted_currency   = isset( $_POST['currency'] ) ? strtoupper( sanitize_key( wp_unslash( $_POST['currency'] ) ) ) : '';
					$selected_currency = isset( $currency_symbols[ $posted_currency ] ) ? $posted_currency : self::currency_for( $selected_locale );
					$previous_country  = strtoupper( (string) get_option( 'ec_option_store_country', '' ) );
					update_option( 'ec_option_store_locale', $selected_locale );
					update_option( 'ec_option_store_country', $selected_locale ); /* 6.0.5: Settings › Store details › Store country */
					$this->pin_store_country( $selected_locale ); /* 6.0.5 ( admin review M10 ): its country first in country lists */
					/* 6.0.5: another country on the way back: the shipping zones and the countries shipped to that the wizard set up follow
					   it now, also when the shipping step is not visited again ( Save & exit ). A list changed since is left alone. */
					if ( '' !== $previous_country && $previous_country !== $selected_locale ) {
						$answers = $this->answers();
						if ( ! empty( $answers['zones_added'] ) || ! empty( $answers['us_zones'] ) ) {
							$this->seed_zones( $selected_locale );
						}
						if ( isset( $answers['ship_to'], $answers['ship_to_list'] ) && (array) $answers['ship_to_list'] === $this->ship_to_active() ) {
							$this->apply_ship_to( $answers['ship_to'] );
						}
					}
					/* 6.0.5: the store's language ( the shoppers' words ); a language shipped with WP EasyCart is installed first. */
					if ( isset( $_POST['store_language'] ) ) {
						$this->save_store_language( sanitize_key( wp_unslash( $_POST['store_language'] ) ) );
					}
					/* 6.0.5: a Canadian store's Bill later reads Interac e-Transfer while it has the default wording ( admin review M2 ). */
					if ( 'CA' === $selected_locale && function_exists( 'wp_easycart_language' ) ) {
						$wpec_lang_file = '' !== (string) get_option( 'ec_option_language' ) ? (string) get_option( 'ec_option_language' ) : 'en-us';
						$wpec_interac   = array(
							'en-us' => array( array( 'Pay by bank transfer', 'Pay by Direct Deposit' ), 'Pay by Interac e-Transfer' ),
							'fr-fr' => array( array( 'Payer par virement bancaire', 'Paiement par depot direct' ), 'Payer par virement Interac' ),
						);
						$wpec_manual    = trim( html_entity_decode( (string) wp_easycart_language()->get_text( 'cart_payment_information', 'cart_payment_information_manual_payment' ), ENT_QUOTES, 'UTF-8' ) );
						if ( isset( $wpec_interac[ $wpec_lang_file ] ) && in_array( $wpec_manual, $wpec_interac[ $wpec_lang_file ][0], true ) ) {
							wp_easycart_language()->update_language_item( $wpec_lang_file, 'cart_payment_information', 'cart_payment_information_manual_payment', $wpec_interac[ $wpec_lang_file ][1] );
						}
					}
					update_option( 'ec_option_base_currency', $selected_currency );
					/* 6.0.5 ( FREE #333 ): saved as characters, not the map's HTML entity ( Reports printed &pound;125.55 ). */
					update_option( 'ec_option_currency', html_entity_decode( $currency_symbols[ $selected_currency ], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
					update_option( 'ec_option_paypal_currency_code', $selected_currency );
					/* 6.0.5: every country's format and units ( facts() ). Before, only the 36 countries in the wizard's own table got them:
					   a New Zealand store kept US dollars, pounds and inches, and Norway's table entry ( "Kr" ) saved US dollars. The
					   format is the country's ( ec_currency::country_format(), admin review M9 ), the decimals the currency's. */
					$facts = self::facts( $selected_locale, $selected_currency );
					update_option( 'ec_option_currency_symbol_location', $facts['before'] ? 1 : 0 );
					update_option( 'ec_option_currency_symbol_space', $facts['space'] ? 1 : 0 );
					update_option( 'ec_option_currency_decimal_symbol', $facts['dec'] );
					update_option( 'ec_option_currency_thousands_seperator', $facts['thou'] );
					update_option( 'ec_option_currency_decimal_places', $facts['decimals'] );
					$paypal_locales = array( 'US', 'AU', 'AT', 'BE', 'BR', 'CA', 'CH', 'CN', 'DE', 'ES', 'GB', 'FR', 'IT', 'NL', 'PL', 'PT', 'RU' );
					if ( in_array( $selected_locale, $paypal_locales, true ) ) {
						update_option( 'ec_option_paypal_lc', $selected_locale );
					}
					/* A store weighing in ounces or grams keeps its unit while the country weighs the same way ( lbs: oz, kgs: g ). */
					$weight_now = (string) get_option( 'ec_option_paypal_weight_unit' );
					$same_kind  = ( 'lbs' === $facts['weight'] && in_array( $weight_now, array( 'lbs', 'oz' ), true ) ) || ( 'kgs' === $facts['weight'] && in_array( $weight_now, array( 'kgs', 'g' ), true ) );
					if ( ! $same_kind ) {
						update_option( 'ec_option_paypal_weight_unit', $facts['weight'] );
					}
					/* 6.0.5: sizes follow the country too ( a metric store kept inches until someone found the shipping setting ). */
					update_option( 'ec_option_enable_metric_unit_display', $facts['metric'] ? 1 : 0 );
					/* 6.0.5: tax from the recommended settings for the country ( wp_easycart_tax_setup: rates checked by WP EasyCart, VAT on
					   shipping, businesses abroad, the Canadian grid for every role, the US state saved with its country ), as the summary
					   showed. Re-running the wizard updates tax when the box is ticked; before 6.0.5 it was disabled once any rate existed. */
					$state_missing = false;
					if ( class_exists( 'wp_easycart_tax_setup' ) && wp_easycart_tax_setup::supported( $selected_locale ) ) {
						$tax_choices = array(
							'state'    => $selected_state_locale,
							'included' => ! empty( $_POST['tax_included'] ) ? 1 : 0,
							'eu'       => ( isset( $_POST['eu_vat'] ) && 'oss' === sanitize_key( wp_unslash( $_POST['eu_vat'] ) ) ) ? 'oss' : 'home',
							'pst'      => ! empty( $_POST['canada_pst'] ) ? 'all' : 'home',
						);
						/* 6.0.5: a choice the page does not ask this country keeps what was saved ( the VAT questions of a US store ). */
						$saved_choices = wp_easycart_tax_setup::choices( $selected_locale );
						if ( 'vat' !== wp_easycart_tax_setup::system( $selected_locale ) ) {
							$tax_choices['included'] = $saved_choices['included'];
							$tax_choices['eu']       = $saved_choices['eu'];
						}
						if ( 'CA' !== $selected_locale ) {
							$tax_choices['pst'] = $saved_choices['pst'];
						}
						wp_easycart_tax_setup::save_choices( $selected_locale, $tax_choices );
						/* A US store's state tax and a Canadian store's GST / HST and PST need the state or province ( the page asks for it first ). */
						$state_missing = in_array( $selected_locale, array( 'US', 'CA' ), true ) && '' === $selected_state_locale;
						if ( isset( $_POST['sales_tax'] ) && ! $state_missing ) {
							wp_easycart_tax_setup::apply( $selected_locale, $tax_choices );
						}
					}
					/**
					 * The setup wizard saved the store's location.
					 *
					 * @since 6.0.5
					 * @param string $country          ISO code.
					 * @param string $previous_country The country saved before ( '' on a first run ).
					 * @param string $currency         ISO 4217 code.
					 */
					do_action( 'wp_easycart_setup_wizard_location_saved', $selected_locale, $previous_country, $selected_currency );
					$this->mark_completed( self::STEP_LOCATION );
					if ( $state_missing && isset( $_POST['sales_tax'] ) ) {
						wp_redirect( $this->step_url( self::STEP_LOCATION ) . '&error=state' );
						exit;
					}
					$this->go_next( self::STEP_PAYMENTS );
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
					$this->go_next( self::STEP_SHIPPING );
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

					/*
					 * 6.0.1: "I don't ship anything" installs nothing and leaves the calculation method as it is.
					 * It only records the answer, which settles the Shipping row on the Store readiness card
					 * instead of leaving a warning a downloads-only store can never clear.
					 */
					if ( 'none' === $method ) {
						/*
						 * Switch shipping off for real. Recording the answer alone left any rates the merchant had
						 * already installed still live: checkout kept charging them, the wizard summary kept
						 * reading ec_setting.shipping_method and still said "Flat rates", and the Store readiness
						 * card still saw rates and called shipping set up. The rate rows are deliberately left
						 * where they are, so turning shipping back on restores them untouched.
						 */
						update_option( 'ec_option_use_shipping', 0 );
						if ( class_exists( 'wp_easycart_admin_store_status' ) ) {
							wp_easycart_admin_store_status::set_acknowledged( 'shipping', true );
						}
						$this->mark_completed( self::STEP_SHIPPING );
						$this->go_next( self::STEP_FINISH );
					}

					if ( ! isset( $presets[ $method ] ) ) {
						$method = 'static';
					}
					/* 6.0.5 ( admin review M10 ): where the store ships, and a zone for its own country. */
					$ship_to = isset( $_POST['ship_to'] ) ? sanitize_key( wp_unslash( $_POST['ship_to'] ) ) : '';
					if ( in_array( $ship_to, array( 'country', 'region', 'world' ), true ) ) {
						$this->apply_ship_to( $ship_to );
					} elseif ( 'keep' === $ship_to ) {
						/* 6.0.5: the countries chosen under Countries & Regions stay as they are. */
						$this->remember( 'ship_to', null );
					}
					if ( class_exists( 'wp_easycart_carriers' ) ) {
						$this->seed_zones( wp_easycart_carriers::store_country() );
					}

					/* Rates are being set up after all, so shipping goes back on and the answer above no longer applies. */
					update_option( 'ec_option_use_shipping', 1 );
					if ( class_exists( 'wp_easycart_admin_store_status' ) ) {
						wp_easycart_admin_store_status::set_acknowledged( 'shipping', false );
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
					$this->go_next( self::STEP_FINISH );
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

					/* 6.0.5: Save & exit and Back save this step without finishing setup ( only Finish setup does ). */
					$goto = isset( $_POST['wizard_goto'] ) ? sanitize_key( wp_unslash( $_POST['wizard_goto'] ) ) : '';
					if ( '' === $goto ) {
						update_option( 'ec_option_setup_wizard_done', 1 );
						$this->mark_completed( self::STEP_FINISH );
					}
					wp_cache_delete( 'wpeasycart-settings', 'wpeasycart-settings' );
					$this->go_next( self::STEP_DONE );
				}
			}
		}

		// phpcs:enable WordPress.Security.NonceVerification.Missing

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
			$made = self::create_policy_page( $type );
			if ( is_wp_error( $made ) ) {
				wp_send_json_error( array( 'message' => $made->get_error_message() ) );
			}
			wp_send_json_success( $made );
		}

		/**
		 * Create the draft terms or privacy page with a starting outline. Used by the wizard and, since 6.0.1, by the
		 * Checkout settings page.
		 *
		 * @since 6.0.1
		 * @param string $type terms | privacy.
		 * @return array|WP_Error id, title, url, edit.
		 */
		public static function create_policy_page( $type ) {
			$type = sanitize_key( $type );
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
				return new WP_Error( 'type', __( 'Unknown page type.', 'wp-easycart' ) );
			}

			$id = wp_insert_post( array(
				'post_title'   => $title,
				'post_content' => $content,
				'post_status'  => 'draft',
				'post_type'    => 'page',
			), true );
			if ( is_wp_error( $id ) ) {
				return $id;
			}
			if ( 'privacy' == $type && ! get_option( 'wp_page_for_privacy_policy' ) ) {
				update_option( 'wp_page_for_privacy_policy', $id );
			}
			return array(
				'id'    => $id,
				/* translators: %s: page title */
				'title' => sprintf( __( '%s (draft)', 'wp-easycart' ), $title ),
				'url'   => get_permalink( $id ),
				'edit'  => get_edit_post_link( $id, '' ),
			);
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
			if ( class_exists( 'wp_easycart_email_design' ) ) { /* 6.0.0: shared email design. */
				$ed   = 'wp_easycart_email_design';
				$body = $ed::wrap(
					$ed::get_paragraph( esc_html__( 'If you are reading this, order emails from your store can reach this inbox.', 'wp-easycart' ) )
					. $ed::get_paragraph( esc_html__( 'Sent by WP EasyCart via wp_mail() from', 'wp-easycart' ) . ' <strong>' . esc_html( $from ) . '</strong> ' . esc_html__( 'at', 'wp-easycart' ) . ' ' . esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ) . '.', array( 'nolink' => true ) )
					. $ed::get_paragraph( esc_html__( 'If this landed in spam, consider an SMTP plugin or a transactional email service. See Settings › Email.', 'wp-easycart' ), array( 'tone' => 'small', 'margin' => '0' ) ),
					array(
						'title'     => $subject,
						'heading'   => __( 'Test email from WP EasyCart', 'wp-easycart' ),
						'preheader' => __( 'If you are reading this, order emails from your store can reach this inbox.', 'wp-easycart' ),
						'eyebrow'   => __( 'Test email', 'wp-easycart' ),
					)
				);
			}

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
			// The Settings home embeds the launch checklist and opts in through this filter.
			if ( ! $this->is_wizard_screen() && ! $this->is_store_status_screen() && ! apply_filters( 'wp_easycart_setup_wizard_assets_needed', false ) ) {
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
					/* 6.0.5: leaving a step with changes ( setup-wizard-v2.js askToSave() ). */
					'leave_title'   => __( 'Save your changes on this step?', 'wp-easycart' ),
					'leave_text'    => __( 'You changed something here. Save it before you go, or leave this step as it was.', 'wp-easycart' ),
					'leave_save'    => __( 'Save and go', 'wp-easycart' ),
					'leave_discard' => __( 'Don’t save', 'wp-easycart' ),
					'leave_stay'    => __( 'Stay here', 'wp-easycart' ),
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