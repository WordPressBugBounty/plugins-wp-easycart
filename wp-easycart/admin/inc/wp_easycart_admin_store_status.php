<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_store_status' ) ) :

	final class wp_easycart_admin_store_status {

		protected static $_instance = null;

		public $store_status_file;
		public $settings;

		/**
		 * Nonce action for the resumable repair jobs ( AJAX action ecv2_status_job ).
		 *
		 * @since 6.0.0
		 */
		const JOB_NONCE = 'wp-easycart-ecv2-status-job';

		/* 6.0.1: nonce for "this store does not charge tax / does not ship". */
		const ACK_NONCE = 'wp-easycart-ecv2-status-ack';

		/**
		 * Nonce action for the "check the database structure again" link ( recheck=1 ).
		 *
		 * @since 6.0.0
		 */
		const RECHECK_NONCE = 'wp-easycart-status-recheck';

		/**
		 * Transient holding the gateway / webhook log row counts shown on the status page.
		 *
		 * @since 6.0.0
		 */
		const LOG_SIZE_TRANSIENT = 'ec_status_log_sizes';

		/**
		 * Rows handled per job request, and the soft time budget ( seconds ) per request.
		 *
		 * @since 6.0.0
		 */
		const JOB_BATCH = 200;
		const JOB_DELETE_BATCH = 5000;
		const JOB_TIME_BUDGET = 20;

		/**
		 * Per-request memo of the shortcode page lookups ( shortcode => row|null ).
		 *
		 * @since 6.0.0
		 * @var array
		 */
		private $shortcode_pages = array();

		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		public function __construct() { 
			$this->store_status_file = EC_PLUGIN_DIRECTORY . '/admin/template/status/store-status/store-status.php';
			$this->settings = EC_PLUGIN_DIRECTORY . '/admin/template/status/store-status/settings.php';

			add_filter( 'wp_easycart_admin_success_messages', array( $this, 'add_success_messages' ) );
			add_action( 'wpeasycart_admin_store_status', array( $this, 'load_store_status' ) );
			add_action( 'wp_easycart_process_get_form_action', array( $this, 'process_repair_database' ) );
			add_action( 'wp_easycart_process_get_form_action', array( $this, 'reset_store_permalinks' ) );
			add_action( 'wp_easycart_process_get_form_action', array( $this, 'fix_category_permalinks' ) );
			add_action( 'wp_easycart_process_get_form_action', array( $this, 'fix_product_permalinks' ) );
			add_action( 'wp_easycart_process_get_form_action', array( $this, 'fix_gateway_log' ) );
			add_action( 'wp_easycart_process_get_form_action', array( $this, 'fix_webhook_log' ) );
			add_action( 'wp_easycart_process_get_form_action', array( $this, 'fix_data_folders' ) );
			add_action( 'wp_easycart_process_get_form_action', array( $this, 'fix_upload_protection' ) );
			add_action( 'wp_easycart_process_get_form_action', array( $this, 'fix_post_tags' ) );

			/* The template constructs a second instance while rendering; only the singleton
			   registers the job engine so the AJAX handler and script are hooked once. @since 6.0.0 */
			if ( is_null( self::$_instance ) ) {
				add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_job_script' ) );
				add_action( 'wp_ajax_ecv2_status_job', array( $this, 'ajax_job' ) );
				/* 6.0.1: "remove the rates for this carrier" from the live shipping checks. */
				add_action( 'wp_ajax_ecv2_status_clear_carrier_rates', array( $this, 'ajax_clear_carrier_rates' ) );
				/* 6.0.1: "we do not charge tax" / "we do not ship anything" from the readiness checks. */
				add_action( 'wp_ajax_ecv2_status_ack', array( $this, 'ajax_ack' ) );
			}
		}

		/**
		 * The resumable repair jobs: key => label shown while the job runs, and the
		 * success flag the page redirects to when the job completes.
		 *
		 * @since 6.0.0
		 *
		 * @return array
		 */
		public static function jobs() {
			return array(
				'reset-store-permalinks'   => array( 'label' => __( 'Resetting store permalinks', 'wp-easycart' ), 'success' => 'reset-store-permalinks', 'phases' => array( 'delete', 'menulevel1', 'menulevel2', 'menulevel3', 'product', 'manufacturer', 'category' ) ),
				'rebuild-store-permalinks' => array( 'label' => __( 'Rebuilding store permalinks', 'wp-easycart' ), 'success' => 'rebuild-store-permalinks', 'phases' => array( 'menulevel1', 'menulevel2', 'menulevel3', 'product', 'manufacturer', 'category' ) ),
				'fix-category-permalinks'  => array( 'label' => __( 'Fixing category permalinks', 'wp-easycart' ), 'success' => 'fix-category-permalinks', 'phases' => array( 'category' ) ),
				'fix-product-permalinks'   => array( 'label' => __( 'Fixing product permalinks', 'wp-easycart' ), 'success' => 'fix-product-permalinks', 'phases' => array( 'product' ) ),
				'fix-post-tags'            => array( 'label' => __( 'Fixing post tags', 'wp-easycart' ), 'success' => 'fix-post-tags', 'phases' => array( 'product', 'category', 'manufacturer' ) ),
				'fix-gateway-log'          => array( 'label' => __( 'Trimming the gateway log', 'wp-easycart' ), 'success' => 'fix-gateway-log', 'phases' => array( 'trim' ) ),
				'fix-webhook-log'          => array( 'label' => __( 'Trimming the webhook log', 'wp-easycart' ), 'success' => 'fix-webhook-log', 'phases' => array( 'trim' ) ),
			);
		}

		/**
		 * Load the job runner on the Diagnostics screen only.
		 *
		 * @since 6.0.0
		 */
		public function enqueue_job_script() {
			if ( ! isset( $_GET['page'] ) || 'wp-easycart-status' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only check of which admin screen is loading.
				return;
			}
			$autostart = isset( $_GET['ecds_job'] ) ? sanitize_key( wp_unslash( $_GET['ecds_job'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- set only by the nonce-checked legacy handlers below; the job itself is nonce-checked in ajax_job().
			if ( '' !== $autostart && ! isset( self::jobs()[ $autostart ] ) ) {
				$autostart = '';
			}
			wp_enqueue_script( 'wp_easycart_admin_store_status_js', plugins_url( '/admin/js/store-status-v2.js', EC_PLUGIN_DIRECTORY . '/wpeasycart.php' ), array( 'jquery' ), EC_CURRENT_VERSION, true );
			$labels = array();
			foreach ( self::jobs() as $key => $job ) {
				$labels[ $key ] = $job['label'];
			}
			wp_localize_script(
				'wp_easycart_admin_store_status_js',
				'ecv2_status_vars',
				array(
					'ajax_url'  => admin_url( 'admin-ajax.php' ),
					'nonce'     => wp_create_nonce( self::JOB_NONCE ),
					'autostart' => $autostart,
					'labels'    => $labels,
					'i18n'      => array(
						'starting'    => __( 'Starting…', 'wp-easycart' ),
						/* translators: 1: rows processed so far, 2: rows in total. */
						'progress'    => __( '%1$s of %2$s', 'wp-easycart' ),
						/* translators: %s: rows processed so far. */
						'progress_na' => __( '%s processed', 'wp-easycart' ),
						'finishing'   => __( 'Finishing…', 'wp-easycart' ),
					/* translators: 1: current step number, 2: number of steps. */
					'step'        => __( 'Step %1$s of %2$s', 'wp-easycart' ),
						'error'       => __( 'The repair stopped before it finished. Your data is safe; click Resume to continue where it left off.', 'wp-easycart' ),
						'network'     => __( 'The connection dropped. Click Resume to continue where it left off.', 'wp-easycart' ),
						'resume'      => __( 'Resume', 'wp-easycart' ),
						'discard'     => __( 'Discard', 'wp-easycart' ),
						'interrupted' => __( 'A repair was interrupted before it finished.', 'wp-easycart' ),
						'running'     => __( 'A repair is already running. Wait for it to finish first.', 'wp-easycart' ),
						'leave'       => __( 'A repair is still running. Leaving this page pauses it; you can resume it later.', 'wp-easycart' ),
						'confirm_reset' => __( 'This deletes every store post and recreates it. Existing store links keep working once the rebuild finishes. Continue?', 'wp-easycart' ),
					),
				)
			);
		}

		/**
		 * Send an old-style tool link into the JS job runner ( keeps bookmarks working ).
		 *
		 * @since 6.0.0
		 *
		 * @param string $job Job key from jobs().
		 */
		private function redirect_to_job( $job ) {
			wp_safe_redirect( add_query_arg( array( 'page' => 'wp-easycart-status', 'subpage' => 'store-status', 'ecds_job' => $job ), admin_url( 'admin.php' ) ) );
			die();
		}

		public function load_status() {
			include( $this->settings );
		}

		public function load_store_status() {
			include( $this->store_status_file );
		}

		public function ec_get_php_version() {
			return phpversion();
		}

		public function add_success_messages( $messages ) {
			if ( isset( $_GET['success'] ) && $_GET['success'] == 'database-repair-complete' ) {
				$messages[] = __( 'The database repair tool has completed and your database structure verified clean.', 'wp-easycart' );
			} else if ( isset( $_GET['success'] ) && $_GET['success'] == 'database-repair-incomplete' ) {
				$messages[] = __( 'The database repair tool ran, but some structure errors could not be fixed automatically. Please review the remaining errors below or contact WP EasyCart support for help.', 'wp-easycart' );
			} else if ( isset( $_GET['success'] ) && $_GET['success'] == 'database-install-complete' ) {
				$messages[] = __( 'The database install completed successfully.', 'wp-easycart' );
			} else if ( isset( $_GET['success'] ) && $_GET['success'] == 'database-install-failed' ) {
				$messages[] = __( 'The database install could not complete. Please review the install errors shown in your admin notices or contact WP EasyCart support for help.', 'wp-easycart' );
			} else if ( isset( $_GET['success'] ) && $_GET['success'] == 'database-step-skipped' ) {
				$messages[] = __( 'The rest of the database update has been applied. The change that could not be made is still outstanding and is listed below; run it by hand, or contact WP EasyCart support.', 'wp-easycart' );
			} else if ( isset( $_GET['success'] ) && $_GET['success'] == 'database-steps-retried' ) {
				$messages[] = __( 'The skipped database changes have been put back in the queue and tried again.', 'wp-easycart' );
			} else if ( isset( $_GET['success'] ) && $_GET['success'] == 'download-recovery-dismissed' ) {
				$messages[] = __( 'The downloadable products notice has been dismissed.', 'wp-easycart' );
			} else if ( isset( $_GET['success'] ) && $_GET['success'] == 'database-repair-dismissed' ) {
				$messages[] = __( 'The database notice has been dismissed, but will continue to show in your store status. Please contact WP EasyCart support for help.', 'wp-easycart' );
			} else if ( isset( $_GET['success'] ) && $_GET['success'] == 'reset-store-permalinks' ) {
				$messages[] = __( 'Your store permalinks have been reset.', 'wp-easycart' );
			} else if ( isset( $_GET['success'] ) && $_GET['success'] == 'rebuild-store-permalinks' ) {
				$messages[] = __( 'Your store permalinks have been rebuilt.', 'wp-easycart' );
			} else if ( isset( $_GET['success'] ) && $_GET['success'] == 'fix-category-permalinks' ) {
				$messages[] = __( 'We have completed an attempt to fix your category permalinks.', 'wp-easycart' );
			} else if ( isset( $_GET['success'] ) && $_GET['success'] == 'fix-product-permalinks' ) {
				$messages[] = __( 'We have completed an attempt to fix your product permalinks.', 'wp-easycart' );
			} else if ( isset( $_GET['success'] ) && $_GET['success'] == 'fix-gateway-log' ) {
				$messages[] = __( 'Your gateway log size has been reduced.', 'wp-easycart' );
			} else if ( isset( $_GET['success'] ) && $_GET['success'] == 'fix-webhook-log' ) {
				$messages[] = __( 'Your webhook log size has been reduced.', 'wp-easycart' );
			} else if ( isset( $_GET['success'] ) && $_GET['success'] == 'fix-data-folders' ) {
				$messages[] = __( 'Your data folders have been repaired.', 'wp-easycart' );
			} else if ( isset( $_GET['success'] ) && 'fix-upload-protection' === sanitize_key( wp_unslash( $_GET['success'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only success flag after a nonce-checked redirect.
				$messages[] = __( 'The protection files for customer uploads and paid downloads were rewritten and the folders were checked again.', 'wp-easycart' );
			} else if ( isset( $_GET['success'] ) && $_GET['success'] == 'fix-post-tags' ) {
				$messages[] = __( 'Your post tags have been fixed.', 'wp-easycart' );
			}
			return $messages;
		}

		public function process_repair_database() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_diagnostics' ) ) {
				return false;
			}
			if ( $_GET['ec_admin_form_action'] == 'repair-database' ) {
				if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-action-repair-database' ) ) {
					$db_manager = new ec_db_manager();
					$remaining = $db_manager->try_repair();
					if ( is_array( $remaining ) && count( $remaining ) ) {
						wp_redirect( 'admin.php?page=wp-easycart-status&subpage=store-status&success=database-repair-incomplete' );
					} else {
						wp_redirect( 'admin.php?page=wp-easycart-status&subpage=store-status&success=database-repair-complete' );
					}
					die();
				}

			} else if ( $_GET['ec_admin_form_action'] == 'retry-database-install' ) {
				if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-action-retry-database-install' ) ) {
					/* Explicit retry from the install-failure notice: clear the backoff
					   and force a fresh install_db() pass. */
					delete_transient( 'ec_db_install_backoff' );
					$db_manager = new ec_db_manager();
					if ( $db_manager->install_db( true ) ) {
						wp_redirect( 'admin.php?page=wp-easycart-status&subpage=store-status&success=database-install-complete' );
					} else {
						wp_redirect( 'admin.php?page=wp-easycart-status&subpage=store-status&success=database-install-failed' );
					}
					die();
				}

			} else if ( $_GET['ec_admin_form_action'] == 'skip-database-step' ) {
				if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-action-skip-database-step' ) ) {
					/* 6.0.1: a step that cannot succeed on this database held the whole upgrade open,
					   because the version is only written once every step lands. Recording it as handled
					   lets the rest finish; the change itself is still outstanding and Store Status says so. */
					ec_db_manager::skip_failing_steps();
					$db_manager = new ec_db_manager();
					$db_manager->try_db_update();
					wp_redirect( 'admin.php?page=wp-easycart-status&subpage=store-status&success=database-step-skipped' );
					die();
				}

			} else if ( $_GET['ec_admin_form_action'] == 'retry-database-steps' ) {
				if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-action-retry-database-steps' ) ) {
					ec_db_manager::clear_skipped_steps();
					$db_manager = new ec_db_manager();
					$db_manager->try_db_update();
					wp_redirect( 'admin.php?page=wp-easycart-status&subpage=store-status&success=database-steps-retried' );
					die();
				}

			} else if ( $_GET['ec_admin_form_action'] == 'repair-database-data' ) {				if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-action-repair-database-data' ) ) {
					$db_manager = new ec_db_manager();
					$db_manager->install_base_data();
					wp_redirect( 'admin.php?page=wp-easycart-status&subpage=store-status&success=database-repair-complete' );
					die();
				}

			} else if ( $_GET['ec_admin_form_action'] == 'dismiss-download-recovery' ) {
				if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-action-dismiss-download-recovery' ) ) {
					update_option( 'ec_option_dismiss_download_recovery_notice', '1' );
					delete_transient( 'ec_download_recovery_count' );
					wp_redirect( 'admin.php?page=wp-easycart-status&subpage=store-status&success=download-recovery-dismissed' );
					die();
				}

			} else if ( $_GET['ec_admin_form_action'] == 'dismiss-database-error' ) {
				if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-action-dismiss-database-error' ) ) {
					update_option( 'ec_option_db_version_verified', str_replace( '_', '.', EC_CURRENT_VERSION ) );
					wp_redirect( 'admin.php?page=wp-easycart-status&subpage=store-status&success=database-repair-dismissed' );
					die();
				}
			}
		}

		public function reset_store_permalinks() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_diagnostics' ) ) {
				return false;
			}
			if ( 'reset-store-permalinks' == $_GET['ec_admin_form_action'] ) {
				if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-reset-store-permalinks' ) ) {
					/* Runs as a resumable batch job now ( ajax_job() ). @since 6.0.0 */
					$this->redirect_to_job( isset( $_GET['ec_reset_phase2'] ) ? 'rebuild-store-permalinks' : 'reset-store-permalinks' );
				}
			}
		}

		public function fix_category_permalinks() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_diagnostics' ) ) {
				return false;
			}
			if ( 'fix-category-permalinks' == $_GET['ec_admin_form_action'] ) {
				if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-fix-category-permalinks' ) ) {
					/* Runs as a resumable batch job now ( ajax_job() ). @since 6.0.0 */
					$this->redirect_to_job( 'fix-category-permalinks' );
				}
			}
		}

		public function fix_product_permalinks() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_diagnostics' ) ) {
				return false;
			}
			if ( 'fix-product-permalinks' == $_GET['ec_admin_form_action'] ) {
				if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-fix-product-permalinks' ) ) {
					/* Runs as a resumable batch job now ( ajax_job() ). @since 6.0.0 */
					$this->redirect_to_job( 'fix-product-permalinks' );
				}
			}
		}

		public function fix_gateway_log() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_diagnostics' ) ) {
				return false;
			}
			if ( 'fix-gateway-log' == $_GET['ec_admin_form_action'] ) {
				if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-fix-gateway-log' ) ) {
					/* Runs as a resumable batch job now ( ajax_job() ). @since 6.0.0 */
					$this->redirect_to_job( 'fix-gateway-log' );
				}
			}
		}

		public function fix_webhook_log() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_diagnostics' ) ) {
				return false;
			}
			if ( 'fix-webhook-log' == $_GET['ec_admin_form_action'] ) {
				if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-fix-webhook-log' ) ) {
					/* Runs as a resumable batch job now ( ajax_job() ). @since 6.0.0 */
					$this->redirect_to_job( 'fix-webhook-log' );
				}
			}
		}

		public function fix_data_folders() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_diagnostics' ) ) {
				return false;
			}
			if ( 'fix-data-folders' == $_GET['ec_admin_form_action'] ) {
				if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-fix-data-folders' ) ) {
					$this->ec_fix_data_folders();
					wp_redirect( 'admin.php?page=wp-easycart-status&subpage=store-status&success=fix-data-folders' );
					die();
				}
			}
		}
		
		/**
		 * Rewrite the deny rules in products/uploads and the downloads folders and re-run the public access checks.
		 *
		 * @since 6.0.0
		 */
		public function fix_upload_protection() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_diagnostics' ) ) {
				return false;
			}
			if ( ! isset( $_GET['ec_admin_form_action'] ) || 'fix-upload-protection' !== sanitize_key( wp_unslash( $_GET['ec_admin_form_action'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only; the nonce is verified by verify_access() below.
				return false;
			}
			if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-fix-upload-protection' ) && class_exists( 'wp_easycart_customer_uploads' ) ) {
				wp_easycart_customer_uploads::protect_all();
				wp_easycart_customer_uploads::check_public_access( true );
				wp_easycart_customer_uploads::check_public_access( true, 'downloads' );
				wp_safe_redirect( admin_url( 'admin.php?page=wp-easycart-status&subpage=store-status&success=fix-upload-protection' ) );
				die();
			}
		}

		/**
		 * Whether customer uploads can be downloaded by URL ( cached loopback check ).
		 *
		 * @since 6.0.0
		 *
		 * @return array|false Result of wp_easycart_customer_uploads::check_public_access(), false when unavailable.
		 */
		public function uploads_access_check() {
			if ( ! class_exists( 'wp_easycart_customer_uploads' ) ) {
				return false;
			}
			return wp_easycart_customer_uploads::check_public_access();
		}

		/**
		 * Whether paid download files can be downloaded by URL ( cached loopback check ).
		 *
		 * @since 6.0.0
		 *
		 * @return array|false Result of wp_easycart_customer_uploads::check_public_access( false, 'downloads' ), false when unavailable.
		 */
		public function downloads_access_check() {
			if ( ! class_exists( 'wp_easycart_customer_uploads' ) || ! method_exists( 'wp_easycart_customer_uploads', 'protect_area' ) ) {
				return false;
			}
			return wp_easycart_customer_uploads::check_public_access( false, 'downloads' );
		}

		/**
		 * Live counts behind the Store Status "Offers" tile.
		 *
		 * Reads ec_offer directly ( no transient ) so the tile is right the moment an
		 * offer is saved. Unconverted legacy coupons ( ec_promocode ) and promotions
		 * ( ec_promotion ) are counted separately so the tile can point the merchant at
		 * the Legacy tab of the Offers hub when there are no v2 offers yet.
		 *
		 * @since 6.0.0
		 *
		 * @return array { 'offers' => int active, unexpired v2 offers ( 0 when ec_offer is missing ), 'legacy' => int unexpired legacy coupons + promotions }
		 */
		public function active_offers_count() {
			global $wpdb;
			$counts = array( 'offers' => 0, 'legacy' => 0 );
			if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
				return $counts;
			}
			if ( $wpdb->get_var( "SHOW TABLES LIKE 'ec_offer'" ) ) {
				$counts['offers'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM ec_offer WHERE offer_status = 'active' AND ( end_date IS NULL OR end_date >= NOW() )" );
			}
			if ( $wpdb->get_var( "SHOW TABLES LIKE 'ec_promocode'" ) ) {
				$counts['legacy'] += (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_promocode WHERE expiration_date IS NULL OR expiration_date >= NOW()' );
			}
			if ( $wpdb->get_var( "SHOW TABLES LIKE 'ec_promotion'" ) ) {
				$counts['legacy'] += (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_promotion WHERE end_date IS NULL OR end_date >= NOW()' );
			}
			return $counts;
		}

		public function fix_post_tags() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_diagnostics' ) ) {
				return false;
			}
			if ( 'fix-post-tags' == $_GET['ec_admin_form_action'] ) {
				if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-fix-post-tags' ) ) {
					/* Runs as a resumable batch job now ( ajax_job() ). @since 6.0.0 */
					$this->redirect_to_job( 'fix-post-tags' );
				}
			}
		}

		/**
		 * Whether this page load asked for a fresh structure check ( recheck=1 with a valid nonce ).
		 *
		 * @since 6.0.0
		 *
		 * @return bool
		 */
		public function recheck_requested() {
			if ( ! isset( $_GET['recheck'] ) || '1' !== sanitize_key( wp_unslash( $_GET['recheck'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the nonce is verified on the next line.
				return false;
			}
			if ( ! isset( $_GET['wp_easycart_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['wp_easycart_nonce'] ) ), self::RECHECK_NONCE ) ) {
				return false;
			}
			return current_user_can( 'manage_options' ) || current_user_can( 'wpec_diagnostics' );
		}

		/**
		 * URL that re-runs the structure check instead of using the cached result.
		 *
		 * @since 6.0.0
		 *
		 * @return string
		 */
		public function recheck_url() {
			return add_query_arg(
				array(
					'page'              => 'wp-easycart-status',
					'subpage'           => 'store-status',
					'recheck'           => '1',
					'wp_easycart_nonce' => wp_create_nonce( self::RECHECK_NONCE ),
				),
				admin_url( 'admin.php' )
			);
		}

		/**
		 * The pending database upgrade step, when ec_db_manager reports one, otherwise ''.
		 * Guarded so this file works with or without the helper on the db manager.
		 *
		 * @since 6.0.0
		 *
		 * @return string
		 */
		public function upgrade_in_progress() {
			if ( ! class_exists( 'ec_db_manager' ) || ! method_exists( 'ec_db_manager', 'update_in_progress' ) ) {
				return '';
			}
			$step = ec_db_manager::update_in_progress();
			if ( ! $step ) {
				return '';
			}
			if ( is_array( $step ) ) {
				if ( isset( $step['step'] ) ) {
					$step = $step['step'];
				} elseif ( isset( $step['function'] ) ) {
					$step = $step['function'];
				} else {
					$step = implode( ' ', array_map( 'strval', array_filter( $step, 'is_scalar' ) ) );
				}
			} elseif ( is_object( $step ) ) {
				$step = isset( $step->step ) ? $step->step : __( 'unknown', 'wp-easycart' );
			}
			return (string) $step;
		}

		public function database_check() {
			$db_manager = new ec_db_manager();
			/* verify_db() may cache its result; only a nonce-checked recheck=1 asks it to run the
			   full check again, and only when the manager actually accepts a $force argument. @since 6.0.0 */
			$force = $this->recheck_requested();
			if ( $force && method_exists( $db_manager, 'verify_db' ) ) {
				$reflection = new ReflectionMethod( $db_manager, 'verify_db' );
				if ( $reflection->getNumberOfParameters() < 1 ) {
					$force = false;
				}
			}
			$errors = $force ? $db_manager->verify_db( true ) : $db_manager->verify_db();
			if ( is_array( $errors ) && count( $errors ) ) {
				return $errors;
			}
			return false;
		}

		/**
		 * Whether a reference table has at least one row ( SELECT 1 … LIMIT 1, never the whole table ).
		 *
		 * @since 6.0.0
		 *
		 * @param string $table One of the fixed ec_* reference tables.
		 * @return bool
		 */
		private function table_has_rows( $table ) {
			global $wpdb;
			$allowed = array( 'ec_setting', 'ec_country', 'ec_orderstatus', 'ec_timezone', 'ec_state', 'ec_zone' );
			if ( ! in_array( $table, $allowed, true ) ) {
				return false;
			}
			return (bool) $wpdb->get_var( 'SELECT 1 FROM ' . $table . ' LIMIT 1' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is validated against the fixed list above.
		}

		public function settings_check() {
			return $this->table_has_rows( 'ec_setting' );
		}

		public function countries_check() {
			return $this->table_has_rows( 'ec_country' );
		}

		public function order_status_check() {
			return $this->table_has_rows( 'ec_orderstatus' );
		}

		public function timezone_check() {
			return $this->table_has_rows( 'ec_timezone' );
		}

		public function state_check() {
			return $this->table_has_rows( 'ec_state' );
		}

		public function zone_check() {
			return $this->table_has_rows( 'ec_zone' );
		}

		public function wpeasycart_is_data_folder_setup() {
			$folders = $this->wpeasycart_get_data_folder_list();
			foreach ( $folders as $dir ) {
				if ( !file_exists( $dir[0] ) || !is_dir( $dir[0] ) ) {
					return false;
				}
			}
			return true;
		}

		public function ec_get_data_folders_error() {
			$error = __( "You are missing the following wp-easycart-data folders", 'wp-easycart' ) . ": ";
			$folders = $this->wpeasycart_get_data_folder_list();
			$first = true;
			foreach ( $folders as $dir ) {
				if ( !file_exists( $dir[0] ) || !is_dir( $dir[0] ) ) {
					if ( !$first )
						$error .= ", ";
					$dir_split = explode( "wp-easycart-data/", $dir[0] );
					$error .= $dir_split[1];
					$first = false;
				}
			}
			return $error;
		}

		public function ec_fix_data_folders() {
			$folders = $this->wpeasycart_get_data_folder_list();
			foreach ( $folders as $dir ) {
				if ( !file_exists( $dir[0] ) || !is_dir( $dir[0] ) ) {
					/* The list holds permissions as strings ( "0751" ). mkdir() read them as decimal 751, which set broken
					 * permissions, and it was not recursive. Convert from octal and create parents too. @since 6.0.0 */
					$mode = is_string( $dir[1] ) ? octdec( $dir[1] ) : (int) $dir[1];
					if ( wp_mkdir_p( $dir[0] ) ) {
						@chmod( $dir[0], $mode ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- best effort; some hosts refuse chmod.
					}
				}
			}
			/* A recreated uploads folder needs its deny rules again. @since 6.0.0 */
			if ( class_exists( 'wp_easycart_customer_uploads' ) ) {
				wp_easycart_customer_uploads::protect_all();
			}
		}
		
		/* ------------------------------------------------------------------
		 * Resumable repair jobs ( @since 6.0.0 )
		 *
		 * Every tool that used to walk a whole table in one request now runs as
		 * a job: the browser posts { job, phase, last_id, processed, total } to
		 * ecv2_status_job, the server handles at most JOB_BATCH rows ( or
		 * JOB_TIME_BUDGET seconds of deletes ) and answers with the cursor to
		 * continue from. Each step is idempotent for a given cursor, so an
		 * interrupted run can be resumed from the last answer.
		 * ------------------------------------------------------------------ */

		/**
		 * AJAX: run one step of a repair job.
		 *
		 * Nonce: JOB_NONCE ( POST nonce ). Capability: the same pair the GET tools
		 * required ( manage_options, or wpec_diagnostics for the page plus
		 * wpec_manager for verify_access() ).
		 *
		 * @since 6.0.0
		 */
		/**
		 * The carriers a shipping rate can be based on: key => the ec_shippingrate column, the carrier's name and the
		 * setting the fix link opens.
		 *
		 * @since 6.0.1
		 * @return array
		 */
		public static function live_carriers() {
			return array(
				'ups'        => array( 'column' => 'is_ups_based', 'label' => 'UPS', 'anchor' => 'ec_option_ups_use_oauth' ),
				'usps'       => array( 'column' => 'is_usps_based', 'label' => 'USPS', 'anchor' => 'ec_option_usps_v3_enable' ),
				'fedex'      => array( 'column' => 'is_fedex_based', 'label' => 'FedEx', 'anchor' => 'ec_option_fedex_use_oauth' ),
				'dhl'        => array( 'column' => 'is_dhl_based', 'label' => 'DHL', 'anchor' => 'ec_option_dhl_enable' ),
				'canadapost' => array( 'column' => 'is_canadapost_based', 'label' => 'Canada Post', 'anchor' => 'ec_option_canadapost_enable' ),
				'auspost'    => array( 'column' => 'is_auspost_based', 'label' => 'Australia Post', 'anchor' => 'ec_option_auspost_enable' ),
			);
		}

		/**
		 * The "this carrier has rates but is not set up" row: says so, then offers the two ways out — open its settings,
		 * or delete the rates that depend on it.
		 *
		 * @since 6.0.1
		 * @param string $carrier Key from live_carriers().
		 */
		public static function print_carrier_fix( $carrier ) {
			$carriers = self::live_carriers();
			if ( ! isset( $carriers[ $carrier ] ) ) {
				return;
			}
			global $wpdb;
			$info  = $carriers[ $carrier ];
			$count = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_shippingrate WHERE ' . $info['column'] . ' = 1' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- column name from live_carriers(), never from input.
			$setup = admin_url( 'admin.php?page=wp-easycart-settings&subpage=shipping-settings#ecst-' . $info['anchor'] );
			echo '<div class="ec_status_error ecss-carrier-fix"><div class="dashicons-before dashicons-no"></div>';
			echo '<span class="ec_status_label">';
			/* translators: 1: carrier name, 2: number of shipping rates that use it. */
			echo esc_html( sprintf( _n( '%1$s live shipping is not set up, but %2$d shipping rate uses it.', '%1$s live shipping is not set up, but %2$d shipping rates use it.', $count, 'wp-easycart' ), $info['label'], $count ) );
			echo ' ' . esc_html__( 'Those rates cannot be quoted at checkout until the carrier connects.', 'wp-easycart' );
			echo '</span>';
			echo '<span class="ecss-carrier-acts">';
			echo '<a class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" href="' . esc_url( $setup ) . '">' . esc_html( sprintf( /* translators: %s: carrier name. */ __( 'Set up %s', 'wp-easycart' ), $info['label'] ) ) . '</a>';
			echo '<button type="button" class="ecv2-btn ecv2-btn-sm ecss-carrier-clear" data-carrier="' . esc_attr( $carrier ) . '" data-nonce="' . esc_attr( wp_create_nonce( self::JOB_NONCE ) ) . '" data-confirm="' . esc_attr( sprintf( /* translators: %s: carrier name. */ __( 'Delete the shipping rates that use %s? Rates for other carriers are left alone.', 'wp-easycart' ), $info['label'] ) ) . '">' . esc_html( sprintf( /* translators: %d: number of rates. */ _n( 'Remove %d rate', 'Remove %d rates', $count, 'wp-easycart' ), $count ) ) . '</button>';
			echo '</span>';
			echo '</div>';
		}

		/**
		 * Delete every shipping rate based on one carrier. The rates are the merchant's own rows; the carrier's
		 * settings are left as they are.
		 *
		 * @since 6.0.1
		 */
		/**
		 * Readiness checks a store can answer "not applicable" to.
		 *
		 * A store that sells downloads, services or collection-only goods has nothing to ship, and plenty of
		 * stores are genuinely tax free. Both used to sit on the Store readiness card as a permanent warning
		 * with no way to settle them, so the card never reached "4 of 4 ready". Saying so here settles the
		 * check without turning anything on; setting real rates or tax later satisfies it on its own merits
		 * and the answer stops mattering.
		 *
		 * @since 6.0.1
		 * @return array key => option name.
		 */
		public static function acknowledgements() {
			return array(
				'tax'      => 'ec_option_no_tax_acknowledged',
				'shipping' => 'ec_option_no_shipping_acknowledged',
			);
		}

		/**
		 * Has the merchant said this check does not apply to their store?
		 *
		 * @since 6.0.1
		 * @param string $key 'tax' | 'shipping'.
		 * @return bool
		 */
		public static function acknowledged( $key ) {
			$map = self::acknowledgements();
			return isset( $map[ $key ] ) ? (bool) get_option( $map[ $key ] ) : false;
		}

		/**
		 * Record, or take back, one of those answers.
		 *
		 * @since 6.0.1
		 * @param string $key 'tax' | 'shipping'.
		 * @param bool   $on  True when the check does not apply to this store.
		 * @return void
		 */
		public static function set_acknowledged( $key, $on ) {
			$map = self::acknowledgements();
			if ( ! isset( $map[ $key ] ) ) {
				return;
			}
			update_option( $map[ $key ], $on ? 1 : 0 );
			do_action( 'wp_easycart_admin_readiness_acknowledged', $key, $on ? 1 : 0 );
		}

		/**
		 * Record, or take back, one of those answers. @since 6.0.1
		 */
		public function ajax_ack() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_manage_settings' ) ) {
				wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
			}
			check_ajax_referer( self::ACK_NONCE, 'nonce' );
			$map = self::acknowledgements();
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- check_ajax_referer() above.
			$key = isset( $_POST['key'] ) ? sanitize_key( wp_unslash( $_POST['key'] ) ) : '';
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- check_ajax_referer() above.
			$on  = ( isset( $_POST['on'] ) && '0' !== (string) $_POST['on'] ) ? 1 : 0;
			if ( ! isset( $map[ $key ] ) ) {
				wp_send_json_error( array( 'message' => __( 'Unknown check.', 'wp-easycart' ) ) );
			}
			self::set_acknowledged( $key, $on );
			wp_send_json_success( array( 'key' => $key, 'on' => $on ) );
		}
		public function ajax_clear_carrier_rates() {
			if ( ! current_user_can( 'manage_options' ) && ! ( current_user_can( 'wpec_diagnostics' ) && current_user_can( 'wpec_manage_settings' ) ) ) {
				wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
			}
			check_ajax_referer( self::JOB_NONCE, 'nonce' );
			global $wpdb;
			$carriers = self::live_carriers();
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- check_ajax_referer() above.
			$carrier = isset( $_POST['carrier'] ) ? sanitize_key( wp_unslash( $_POST['carrier'] ) ) : '';
			if ( ! isset( $carriers[ $carrier ] ) ) {
				wp_send_json_error( array( 'message' => __( 'Unknown carrier.', 'wp-easycart' ) ) );
			}
			$column = $carriers[ $carrier ]['column'];
			$ids    = $wpdb->get_col( 'SELECT shippingrate_id FROM ec_shippingrate WHERE ' . $column . ' = 1' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- column name from live_carriers().
			foreach ( (array) $ids as $id ) {
				do_action( 'wpeasycart_shippingrate_deleting', (int) $id );
			}
			$removed = (int) $wpdb->query( 'DELETE FROM ec_shippingrate WHERE ' . $column . ' = 1' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- column name from live_carriers().
			foreach ( (array) $ids as $id ) {
				do_action( 'wpeasycart_shippingrate_deleted', (int) $id );
			}
			wp_cache_delete( 'wpeasycart-shipping-data', 'wpeasycart-shipping' );
			wp_cache_flush();
			wp_send_json_success( array(
				'removed' => $removed,
				/* translators: %d: number of shipping rates removed. */
				'message' => sprintf( _n( '%d shipping rate removed.', '%d shipping rates removed.', $removed, 'wp-easycart' ), $removed ),
			) );
		}
		public function ajax_job() {
			if ( ! current_user_can( 'manage_options' ) && ! ( current_user_can( 'wpec_diagnostics' ) && current_user_can( 'wpec_manager' ) ) ) {
				wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
			}
			check_ajax_referer( self::JOB_NONCE, 'nonce' );

			$jobs = self::jobs();
			$job = isset( $_POST['job'] ) ? sanitize_key( wp_unslash( $_POST['job'] ) ) : '';
			if ( ! isset( $jobs[ $job ] ) ) {
				wp_send_json_error( array( 'message' => __( 'Unknown repair tool.', 'wp-easycart' ) ) );
			}
			$phases = $jobs[ $job ]['phases'];
			$phase = isset( $_POST['phase'] ) ? sanitize_key( wp_unslash( $_POST['phase'] ) ) : '';
			$cursor = isset( $_POST['last_id'] ) ? sanitize_text_field( wp_unslash( $_POST['last_id'] ) ) : '';
			$processed = isset( $_POST['processed'] ) ? absint( $_POST['processed'] ) : 0;
			$total = isset( $_POST['total'] ) ? absint( $_POST['total'] ) : 0;
			if ( ! in_array( $phase, $phases, true ) ) {
				$phase = $phases[0];
				$cursor = '';
				$processed = 0;
				$total = 0;
			}
			if ( '' === $cursor ) {
				$processed = 0;
				$total = $this->job_phase_total( $job, $phase );
			}

			$deadline = microtime( true ) + self::JOB_TIME_BUDGET;
			$result = $this->run_job_step( $job, $phase, $cursor, $deadline );
			if ( isset( $result['error'] ) ) {
				wp_send_json_error( array( 'message' => $result['error'] ) );
			}
			if ( isset( $result['total'] ) ) {
				$total = (int) $result['total'];
			}
			$processed += (int) $result['count'];
			$next = (string) $result['next'];
			$done = false;

			if ( ! empty( $result['phase_done'] ) ) {
				$index = array_search( $phase, $phases, true );
				if ( isset( $phases[ $index + 1 ] ) ) {
					$phase = $phases[ $index + 1 ];
					$next = '';
					$processed = 0;
					$total = 0;
				} else {
					$done = true;
				}
			}

			$response = array(
				'done'      => $done,
				'next'      => $next,
				'phase'     => $phase,
				'phase_no'  => (int) array_search( $phase, $phases, true ) + 1,
				'phases'    => count( $phases ),
				'processed' => $processed,
				'total'     => $total,
			);
			if ( $done ) {
				$response['redirect'] = add_query_arg(
					array( 'page' => 'wp-easycart-status', 'subpage' => 'store-status', 'success' => $jobs[ $job ]['success'] ),
					admin_url( 'admin.php' )
				);
			}
			wp_send_json_success( $response );
		}

		/**
		 * Fixed table map for the entity phases ( never built from request data ).
		 *
		 * @since 6.0.0
		 *
		 * @return array phase => { table, id, title, attr, type }
		 */
		private function entity_map() {
			return array(
				'menulevel1'   => array( 'table' => 'ec_menulevel1', 'id' => 'menulevel1_id', 'title' => 'name', 'attr' => 'menuid', 'type' => 'menulevel1' ),
				'menulevel2'   => array( 'table' => 'ec_menulevel2', 'id' => 'menulevel2_id', 'title' => 'name', 'attr' => 'submenuid', 'type' => 'menulevel2' ),
				'menulevel3'   => array( 'table' => 'ec_menulevel3', 'id' => 'menulevel3_id', 'title' => 'name', 'attr' => 'subsubmenuid', 'type' => 'menulevel3' ),
				'product'      => array( 'table' => 'ec_product', 'id' => 'product_id', 'title' => 'title', 'attr' => 'modelnumber', 'type' => 'product' ),
				'manufacturer' => array( 'table' => 'ec_manufacturer', 'id' => 'manufacturer_id', 'title' => 'name', 'attr' => 'manufacturerid', 'type' => 'manufacturer' ),
				'category'     => array( 'table' => 'ec_category', 'id' => 'category_id', 'title' => 'category_name', 'attr' => 'groupid', 'type' => 'category' ),
			);
		}

		/**
		 * Row count a phase starts with, so the progress bar has a denominator.
		 *
		 * @since 6.0.0
		 *
		 * @param string $job   Job key.
		 * @param string $phase Phase key.
		 * @return int
		 */
		private function job_phase_total( $job, $phase ) {
			global $wpdb;
			if ( 'fix-gateway-log' === $job || 'fix-webhook-log' === $job ) {
				return 0; // The trim step reports its own total once it knows the cutoff.
			}
			if ( 'delete' === $phase ) {
				return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'ec_store'" );
			}
			$map = $this->entity_map();
			if ( ! isset( $map[ $phase ] ) ) {
				return 0;
			}
			$rebuild = ( 'reset-store-permalinks' === $job || 'rebuild-store-permalinks' === $job );
			return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $map[ $phase ]['table'] . ( $rebuild ? ' WHERE post_id = 0' : '' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name from the fixed entity_map().
		}

		/**
		 * Dispatch one step. Steps return { next, count, phase_done } plus optional { total, error }.
		 *
		 * @since 6.0.0
		 *
		 * @param string $job      Job key.
		 * @param string $phase    Phase key.
		 * @param string $cursor   Cursor from the previous answer ( '' at the start of a phase ).
		 * @param float  $deadline microtime() to stop processing at.
		 * @return array
		 */
		private function run_job_step( $job, $phase, $cursor, $deadline ) {
			switch ( $job ) {
				case 'fix-product-permalinks':
					return $this->step_fix_product_permalinks( (int) $cursor, $deadline );
				case 'fix-category-permalinks':
					return $this->step_fix_category_permalinks( (int) $cursor, $deadline );
				case 'fix-post-tags':
					return $this->step_fix_post_tags( $phase, (int) $cursor, $deadline );
				case 'reset-store-permalinks':
				case 'rebuild-store-permalinks':
					if ( 'delete' === $phase ) {
						return $this->step_delete_store_posts( (int) $cursor, $deadline );
					}
					return $this->step_rebuild_entity( $phase, (int) $cursor, $deadline );
				case 'fix-gateway-log':
					return $this->step_trim_log( 'ec_response', 'response_id', 100, $cursor, $deadline );
				case 'fix-webhook-log':
					return $this->step_trim_log( 'ec_webhook', 'webhook_id', 1000, $cursor, $deadline );
			}
			return array( 'next' => $cursor, 'count' => 0, 'phase_done' => true );
		}

		/**
		 * Shape a batch answer: the phase is done when the batch was short and every row was handled.
		 *
		 * @since 6.0.0
		 *
		 * @param array $rows  Rows fetched for this batch.
		 * @param int   $count Rows actually processed ( fewer than fetched when the deadline hit ).
		 * @param mixed $next  Cursor of the last processed row.
		 * @return array
		 */
		private function batch_result( $rows, $count, $next ) {
			$fetched = is_array( $rows ) ? count( $rows ) : 0;
			return array(
				'next'       => $next,
				'count'      => $count,
				'phase_done' => ( $fetched < self::JOB_BATCH && $count === $fetched ),
			);
		}

		/**
		 * Step: relink / recreate product posts and correct their status, 200 products per call.
		 *
		 * @since 6.0.0
		 */
		private function step_fix_product_permalinks( $last_id, $deadline ) {
			global $wpdb;
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT activate_in_store, post_id, title, product_id, model_number FROM ec_product WHERE product_id > %d ORDER BY product_id LIMIT %d', $last_id, self::JOB_BATCH ) );
			$count = 0;
			$next = $last_id;
			foreach ( (array) $rows as $product ) {
				$target_status = $product->activate_in_store ? 'publish' : 'private';
				$verified_id = wp_easycart_post_sync()->resolve(
					'product',
					$product->product_id,
					$product->post_id,
					array(
						'post_content' => '[ec_store modelnumber="' . $product->model_number . '"]',
						'post_status'  => $target_status,
						'post_title'   => wp_easycart_language()->convert_text( $product->title ),
						'post_type'    => 'ec_store',
					)
				);
				if ( $verified_id ) {
					$current_status = get_post_status( $verified_id );
					if ( $current_status != $target_status && in_array( $current_status, array( 'publish', 'private' ), true ) ) {
						wp_easycart_post_sync()->set_status( 'product', $product->product_id, $verified_id, $target_status );
					}
				}
				$next = (int) $product->product_id;
				$count++;
				if ( microtime( true ) > $deadline ) {
					break;
				}
			}
			return $this->batch_result( $rows, $count, $next );
		}

		/**
		 * Step: relink / recreate category posts, 200 categories per call.
		 *
		 * @since 6.0.0
		 */
		private function step_fix_category_permalinks( $last_id, $deadline ) {
			global $wpdb;
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT category_id, post_id, category_name FROM ec_category WHERE category_id > %d ORDER BY category_id LIMIT %d', $last_id, self::JOB_BATCH ) );
			$count = 0;
			$next = $last_id;
			foreach ( (array) $rows as $category ) {
				wp_easycart_post_sync()->resolve(
					'category',
					$category->category_id,
					$category->post_id,
					array(
						'post_content' => '[ec_store groupid="' . $category->category_id . '"]',
						'post_status'  => 'publish',
						'post_title'   => wp_easycart_language()->convert_text( $category->category_name ),
						'post_type'    => 'ec_store',
					)
				);
				$next = (int) $category->category_id;
				$count++;
				if ( microtime( true ) > $deadline ) {
					break;
				}
			}
			return $this->batch_result( $rows, $count, $next );
		}

		/**
		 * Step: post tags. Phase 'product' rebuilds each product's tag list from its categories;
		 * 'category' and 'manufacturer' add the default tag where none is set. 200 rows per call.
		 *
		 * @since 6.0.0
		 */
		private function step_fix_post_tags( $phase, $last_id, $deadline ) {
			global $wpdb;
			$count = 0;
			$next = $last_id;
			if ( 'product' === $phase ) {
				$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT post_id, product_id FROM ec_product WHERE product_id > %d ORDER BY product_id LIMIT %d', $last_id, self::JOB_BATCH ) );
				foreach ( (array) $rows as $product ) {
					$post_tags = wp_get_post_tags( $product->post_id );
					$new_post_tags = array( 'product' );
					foreach ( (array) $post_tags as $post_tag ) {
						if ( ! in_array( $post_tag->name, $new_post_tags, true ) ) {
							$new_post_tags[] = $post_tag->name;
						}
					}
					$category_items = $wpdb->get_results( $wpdb->prepare( 'SELECT ec_category.category_name FROM ec_categoryitem, ec_category WHERE ec_categoryitem.product_id = %d AND ec_category.category_id = ec_categoryitem.category_id', $product->product_id ) );
					foreach ( (array) $category_items as $category_item ) {
						if ( ! in_array( $category_item->category_name, $new_post_tags, true ) ) {
							$new_post_tags[] = $category_item->category_name;
						}
					}
					wp_set_post_tags( $product->post_id, $new_post_tags, false );
					$next = (int) $product->product_id;
					$count++;
					if ( microtime( true ) > $deadline ) {
						break;
					}
				}
				return $this->batch_result( $rows, $count, $next );
			}

			$map = $this->entity_map();
			if ( ! isset( $map[ $phase ] ) ) {
				return array( 'next' => $next, 'count' => 0, 'phase_done' => true );
			}
			$entity = $map[ $phase ];
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT post_id, ' . $entity['id'] . ' AS entity_id FROM ' . $entity['table'] . ' WHERE ' . $entity['id'] . ' > %d ORDER BY ' . $entity['id'] . ' LIMIT %d', $last_id, self::JOB_BATCH ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- identifiers come from the fixed entity_map(); values are prepared.
			foreach ( (array) $rows as $row ) {
				if ( $row->post_id && ! get_the_tags( $row->post_id ) ) {
					wp_set_post_tags( $row->post_id, array( $phase ) );
				}
				$next = (int) $row->entity_id;
				$count++;
				if ( microtime( true ) > $deadline ) {
					break;
				}
			}
			return $this->batch_result( $rows, $count, $next );
		}

		/**
		 * Step ( reset only ): delete ec_store posts 200 at a time by ascending ID. Once the
		 * table is empty, zero every forward link so the rebuild phases recreate everything.
		 *
		 * @since 6.0.0
		 */
		private function step_delete_store_posts( $last_id, $deadline ) {
			global $wpdb;
			$where = function ( $sql ) use ( $last_id, $wpdb ) {
				return $sql . $wpdb->prepare( " AND {$wpdb->posts}.ID > %d", $last_id );
			};
			add_filter( 'posts_where', $where );
			$ids = get_posts(
				array(
					'post_type'              => 'ec_store',
					'post_status'            => 'any',
					'posts_per_page'         => self::JOB_BATCH,
					'orderby'                => 'ID',
					'order'                  => 'ASC',
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'suppress_filters'       => false,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);
			remove_filter( 'posts_where', $where );

			$count = 0;
			$next = $last_id;
			foreach ( (array) $ids as $post_id ) {
				wp_delete_post( (int) $post_id, true );
				$next = (int) $post_id;
				$count++;
				if ( microtime( true ) > $deadline ) {
					break;
				}
			}
			$result = $this->batch_result( $ids, $count, $next );
			if ( $result['phase_done'] ) {
				$wpdb->query( 'UPDATE ec_product SET ec_product.post_id = 0' );
				$wpdb->query( 'UPDATE ec_menulevel1 SET ec_menulevel1.post_id = 0' );
				$wpdb->query( 'UPDATE ec_menulevel2 SET ec_menulevel2.post_id = 0' );
				$wpdb->query( 'UPDATE ec_menulevel3 SET ec_menulevel3.post_id = 0' );
				$wpdb->query( 'UPDATE ec_category SET ec_category.post_id = 0' );
				$wpdb->query( 'UPDATE ec_manufacturer SET ec_manufacturer.post_id = 0' );
			}
			return $result;
		}

		/**
		 * Step ( reset / rebuild ): create the post for every row of one entity table that has
		 * no post yet, 200 rows per call. Inserting sets post_id, so the cursor and the
		 * post_id = 0 filter both move the batch forward.
		 *
		 * @since 6.0.0
		 */
		private function step_rebuild_entity( $phase, $last_id, $deadline ) {
			global $wpdb;
			$map = $this->entity_map();
			if ( ! isset( $map[ $phase ] ) ) {
				return array( 'next' => $last_id, 'count' => 0, 'phase_done' => true );
			}
			$entity = $map[ $phase ];
			$columns = $entity['id'] . ' AS entity_id, post_id, ' . $entity['title'] . ' AS title';
			if ( 'product' === $phase ) {
				$columns .= ', model_number, description, activate_in_store';
			}
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT ' . $columns . ' FROM ' . $entity['table'] . ' WHERE post_id = 0 AND ' . $entity['id'] . ' > %d ORDER BY ' . $entity['id'] . ' LIMIT %d', $last_id, self::JOB_BATCH ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- identifiers come from the fixed entity_map(); values are prepared.
			$count = 0;
			$next = $last_id;
			foreach ( (array) $rows as $row ) {
				if ( 'product' === $phase ) {
					$post = array(
						'post_content' => '[ec_store modelnumber="' . $row->model_number . '"]',
						'post_status'  => $row->activate_in_store ? 'publish' : 'private',
						'post_title'   => $row->title,
						'post_type'    => 'ec_store',
						'post_excerpt' => $row->description,
					);
				} else {
					$post = array(
						'post_content' => '[ec_store ' . $entity['attr'] . '="' . (int) $row->entity_id . '"]',
						'post_status'  => 'publish',
						'post_title'   => $row->title,
						'post_type'    => 'ec_store',
					);
				}
				wp_easycart_post_sync()->insert( $entity['type'], (int) $row->entity_id, $post );
				$next = (int) $row->entity_id;
				$count++;
				if ( microtime( true ) > $deadline ) {
					break;
				}
			}
			return $this->batch_result( $rows, $count, $next );
		}

		/**
		 * Step: trim a log table to its newest $keep rows. The first call finds the cutoff id
		 * ( the row just past the ones we keep ) and that becomes the cursor; every call then
		 * deletes JOB_DELETE_BATCH rows at a time up to the cutoff until nothing is left or the
		 * time budget runs out. ec_webhook ids are strings ( gateway event ids ) and compare
		 * lexically, matching the ORDER BY the old tool used.
		 *
		 * @since 6.0.0
		 *
		 * @param string $table    ec_response or ec_webhook.
		 * @param string $id_col   Primary key column.
		 * @param int    $keep     Rows to keep.
		 * @param string $cursor   Cutoff id, or '' on the first call.
		 * @param float  $deadline microtime() to stop at.
		 * @return array
		 */
		private function step_trim_log( $table, $id_col, $keep, $cursor, $deadline ) {
			global $wpdb;
			if ( ! in_array( $table, array( 'ec_response', 'ec_webhook' ), true ) ) {
				return array( 'next' => '', 'count' => 0, 'phase_done' => true );
			}
			$is_int = ( 'ec_response' === $table );
			$placeholder = $is_int ? '%d' : '%s';
			$result = array( 'next' => $cursor, 'count' => 0, 'phase_done' => false );

			if ( '' === $cursor ) {
				$cutoff = $wpdb->get_var( $wpdb->prepare( 'SELECT ' . $id_col . ' FROM ' . $table . ' ORDER BY ' . $id_col . ' DESC LIMIT 1 OFFSET %d', $keep ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- identifiers are validated above; the offset is prepared.
				if ( null === $cutoff || '' === $cutoff ) {
					$this->clear_log_sizes();
					return array( 'next' => '', 'count' => 0, 'phase_done' => true, 'total' => 0 );
				}
				$cursor = $is_int ? (string) (int) $cutoff : (string) $cutoff;
				$result['next'] = $cursor;
				$result['total'] = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $id_col . ' <= ' . $placeholder, $is_int ? (int) $cursor : $cursor ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- identifiers are validated above; the value is prepared.
			}

			$value = $is_int ? (int) $cursor : $cursor;
			do {
				$deleted = $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $table . ' WHERE ' . $id_col . ' <= ' . $placeholder . ' ORDER BY ' . $id_col . ' LIMIT %d', $value, self::JOB_DELETE_BATCH ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- identifiers are validated above; values are prepared.
				if ( false === $deleted ) {
					$result['error'] = __( 'The database refused the delete. Check the WP EasyCart log for the SQL error and try again.', 'wp-easycart' );
					return $result;
				}
				$result['count'] += (int) $deleted;
			} while ( $deleted > 0 && microtime( true ) < $deadline );

			if ( 0 === (int) $deleted ) {
				$result['phase_done'] = true;
				$this->clear_log_sizes();
			}
			return $result;
		}

		/**
		 * Gateway and webhook log row counts, cached for five minutes ( the trim tools clear it ).
		 *
		 * @since 6.0.0
		 *
		 * @return array { response: int, webhook: int }
		 */
		public function log_sizes() {
			$sizes = get_transient( self::LOG_SIZE_TRANSIENT );
			if ( ! is_array( $sizes ) || ! isset( $sizes['response'], $sizes['webhook'] ) ) {
				global $wpdb;
				$sizes = array(
					'response' => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_response' ),
					'webhook'  => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_webhook' ),
				);
				set_transient( self::LOG_SIZE_TRANSIENT, $sizes, 5 * MINUTE_IN_SECONDS );
			}
			return $sizes;
		}

		/**
		 * Forget the cached log row counts.
		 *
		 * @since 6.0.0
		 */
		public function clear_log_sizes() {
			delete_transient( self::LOG_SIZE_TRANSIENT );
		}

		public function wpeasycart_get_data_folder_list() {
			$folders = array(
				array( 
					EC_PLUGIN_DATA_DIRECTORY . "/design/",
					"0755"
				),
				array( 
					EC_PLUGIN_DATA_DIRECTORY . "/design/theme/",
					"0755"
				),
				array( 
					EC_PLUGIN_DATA_DIRECTORY . "/design/theme/custom-theme/",
					"0755"
				),
				array( 
					EC_PLUGIN_DATA_DIRECTORY . "/design/layout/",
					"0755"
				),
				array( 
					EC_PLUGIN_DATA_DIRECTORY . "/design/layout/custom-layout/",
					"0755"
				),
				array( 
					EC_PLUGIN_DATA_DIRECTORY . "/products/",
					"0755"
				),
				array( 
					EC_PLUGIN_DATA_DIRECTORY . "/products/banners/",
					"0755"
				),
				array( 
					EC_PLUGIN_DATA_DIRECTORY . "/products/downloads/",
					"0751"
				),
				array( 
					EC_PLUGIN_DATA_DIRECTORY . "/products/pics1/",
					"0755"
				),
				array( 
					EC_PLUGIN_DATA_DIRECTORY . "/products/pics2/",
					"0755"
				),
				array( 
					EC_PLUGIN_DATA_DIRECTORY . "/products/pics3/",
					"0755"
				),
				array( 
					EC_PLUGIN_DATA_DIRECTORY . "/products/pics4/",
					"0755"
				),
				array( 
					EC_PLUGIN_DATA_DIRECTORY . "/products/pics5/",
					"0755"
				),
				array( 
					EC_PLUGIN_DATA_DIRECTORY . "/products/swatches/",
					"0755"
				),
				array( 
					EC_PLUGIN_DATA_DIRECTORY . "/products/uploads/",
					"0751"
				)
			);
			return $folders;
		}

		public function wpeasycart_is_database_setup() {
			$db_manager = new ec_db_manager();
			return $db_manager->check_db();
		}

		public function ec_get_database_error() {
			$db_manager = new ec_db_manager();
			return $db_manager->get_db_errors();
		}

		public function ec_fix_database_errors() {
			/* Explicit admin fix action: bypass the 10-minute failure backoff,
			   otherwise this silently no-ops right after a failed install. */
			delete_transient( 'ec_db_install_backoff' );
			$db_manager = new ec_db_manager();
			return $db_manager->install_db( true );
		}

		/**
		 * Find a published page carrying a shortcode with one indexed-friendly query instead of
		 * loading every page through get_pages(). Prefers the page the store setting points at,
		 * so one row answers both "does any page have it" and "is it the selected page".
		 *
		 * @since 6.0.0
		 *
		 * @param string $shortcode   Shortcode prefix, e.g. '[ec_store'.
		 * @param int    $selected_id Page ID stored in the matching ec_option_*page setting.
		 * @return array { found: bool, match: bool }
		 */
		private function shortcode_page_status( $shortcode, $selected_id ) {
			global $wpdb;
			$selected_id = (int) $selected_id;
			$key = $shortcode . '|' . $selected_id;
			if ( ! array_key_exists( $key, $this->shortcode_pages ) ) {
				$this->shortcode_pages[ $key ] = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' AND post_content LIKE %s ORDER BY ( ID = %d ) DESC, ID ASC LIMIT 1",
						'%' . $wpdb->esc_like( $shortcode ) . '%',
						$selected_id
					)
				);
			}
			$found_id = (int) $this->shortcode_pages[ $key ];
			return array(
				'found' => $found_id > 0,
				'match' => $found_id > 0 && $found_id === $selected_id,
			);
		}

		public function ec_is_store_page_setup() {
			$store = $this->shortcode_page_status( '[ec_store', get_option( 'ec_option_storepage' ) );
			return ( $store['found'] && $store['match'] );
		}

		public function ec_get_store_page_error() {
			$store = $this->shortcode_page_status( '[ec_store', get_option( 'ec_option_storepage' ) );
			$store_page_found = $store['found'];
			$store_is_match = $store['match'];
			if ( !$store_page_found ) {
				return __( "The shortcode [ec_store] was not found on any page. Please add [ec_store] to a WordPress page to correct this.", 'wp-easycart' );
			} else if ( !$store_is_match ) {
				return __( "You have not connected your store page with the EasyCart system. Please go to the setup page and select the correct page from the dropdown menu.", 'wp-easycart' );
			} else {
				return __( "Something went wrong, there may not be an error.", 'wp-easycart' );
			}
		}

		public function ec_is_cart_page_setup() {
			$cart = $this->shortcode_page_status( '[ec_cart', get_option( 'ec_option_cartpage' ) );
			return ( $cart['found'] && $cart['match'] );
		}

		public function ec_get_cart_page_error() {
			$cart = $this->shortcode_page_status( '[ec_cart', get_option( 'ec_option_cartpage' ) );
			$cart_page_found = $cart['found'];
			$cart_is_match = $cart['match'];
			if ( !$cart_page_found ) {
				return __( "The shortcode [ec_cart] was not found on any page. Please add [ec_cart] to a WordPress page to correct this.", 'wp-easycart' );
			} else if ( !$cart_is_match ) {
				return __( "You have not connected your cart page with the EasyCart system. Please go to the setup page and select the correct page from the dropdown menu.", 'wp-easycart' );
			} else {
				return __( "Something went wrong, there may not be an error.", 'wp-easycart' );
			}
		}

		public function ec_is_account_page_setup() {
			$account = $this->shortcode_page_status( '[ec_account', get_option( 'ec_option_accountpage' ) );
			return ( $account['found'] && $account['match'] );
		}

		public function ec_get_account_page_error() {
			$account = $this->shortcode_page_status( '[ec_account', get_option( 'ec_option_accountpage' ) );
			$account_page_found = $account['found'];
			$account_is_match = $account['match'];
			if ( !$account_page_found ) {
				return __( "The shortcode [ec_account] was not found on any page. Please add [ec_account] to a WordPress page to correct this.", 'wp-easycart' );
			} else if ( !$account_is_match ) {
				return __( "You have not connected your account page with the EasyCart system. Please go to the setup page and select the correct page from the dropdown menu.", 'wp-easycart' );
			} else {
				return __( "Something went wrong, there may not be an error.", 'wp-easycart' );
			}
		}

		public function ec_get_basic_missing_settings() {
			$return_text = array();

			if ( get_option( 'ec_option_order_from_email' ) == "youremail@url.com" || get_option( 'ec_option_order_from_email' ) == "" )
				$return_text[] = __( "order from email address", 'wp-easycart' );

			if ( get_option( 'ec_option_password_from_email' ) == "youremail@url.com" || get_option( 'ec_option_password_from_email' ) == "" )
				$return_text[] = __( "password from email address", 'wp-easycart' );

			if ( get_option( 'ec_option_bcc_email_addresses' ) == "youremail@url.com" || get_option( 'ec_option_bcc_email_addresses' ) == "" )
				$return_text[] = __( "receipt copy admin email address", 'wp-easycart' );

			if ( get_option( 'ec_option_terms_link' ) == "http://yoursite.com/termsandconditions" || get_option( 'ec_option_terms_link' ) == "" )
				$return_text[] = __( "terms and conditions page link", 'wp-easycart' );

			if ( get_option( 'ec_option_privacy_link' ) == "http://yoursite.com/privacypolicy" || get_option( 'ec_option_privacy_link' ) == "" )
				$return_text[] = __( "privacy policy page link", 'wp-easycart' );

			return implode( ", ", $return_text );
		}



	////////////////////////////////////////////////
	//Shipping methods
	////////////////////////////////////////////////
		public function ec_get_shipping_method() {
			$db = new ec_db_admin();
			$setting_row = $db->get_settings();
			$settings = new ec_setting( $setting_row );
			$shipping_method = $settings->get_shipping_method();
			return $shipping_method;
		}

		public function ec_using_price_shipping() {
			$shipping_method = $this->ec_get_shipping_method();
			if ( $shipping_method == "price" )
				return true;
			else
				return false;
		}

		public function ec_price_shipping_setup() {
			$db = new ec_db_admin();
			$shippingrates = $db->get_shipping_data();
			$has_price_shipping = false;
			foreach ( $shippingrates as $shiprate ) {
				if ( $shiprate->is_price_based ) {
					$has_price_shipping = true;
					break;
				}
			}
			return $has_price_shipping;
		}

		public function ec_using_weight_shipping() {
			$shipping_method = $this->ec_get_shipping_method();
			if ( $shipping_method == "weight" )
				return true;
			else
				return false;
		}

		public function ec_weight_shipping_setup() {
			$db = new ec_db_admin();
			$shippingrates = $db->get_shipping_data();
			$has_weight_shipping = false;
			foreach ( $shippingrates as $shiprate ) {
				if ( $shiprate->is_weight_based ) {
					$has_weight_shipping = true;
					break;
				}
			}
			return $has_weight_shipping;
		}

		public function ec_using_quantity_shipping() {
			$shipping_method = $this->ec_get_shipping_method();
			if ( $shipping_method == "quantity" )
				return true;
			else
				return false;
		}

		public function ec_quantity_shipping_setup() {
			$db = new ec_db_admin();
			$shippingrates = $db->get_shipping_data();
			$has_quantity_shipping = false;
			foreach ( $shippingrates as $shiprate ) {
				if ( $shiprate->is_quantity_based ) {
					$has_quantity_shipping = true;
					break;
				}
			}
			return $has_quantity_shipping;
		}

		public function ec_using_percentage_shipping() {
			$shipping_method = $this->ec_get_shipping_method();
			if ( $shipping_method == "percentage" )
				return true;
			else
				return false;
		}

		public function ec_percentage_shipping_setup() {
			$db = new ec_db_admin();
			$shippingrates = $db->get_shipping_data();
			$has_percentage_shipping = false;
			foreach ( $shippingrates as $shiprate ) {
				if ( $shiprate->is_percentage_based ) {
					$has_percentage_shipping = true;
					break;
				}
			}
			return $has_percentage_shipping;
		}

		public function ec_using_method_shipping() {
			$shipping_method = $this->ec_get_shipping_method();
			if ( $shipping_method == "method" )
				return true;
			else
				return false;
		}

		public function ec_method_shipping_setup() {
			$db = new ec_db_admin();
			$shippingrates = $db->get_shipping_data();
			$has_method_shipping = false;
			foreach ( $shippingrates as $shiprate ) {
				if ( $shiprate->is_method_based ) {
					$has_method_shipping = true;
					break;
				}
			}
			return $has_method_shipping;
		}

		public function ec_using_live_shipping() {
			$shipping_method = $this->ec_get_shipping_method();
			if ( $shipping_method == "live" )
				return true;
			else
				return false;
		}

		public function ec_live_shipping_setup() {
			$db = new ec_db_admin();
			$shippingrates = $db->get_shipping_data();
			$has_live_shipping = false;
			foreach ( $shippingrates as $shiprate ) {
				if ( $shiprate->is_ups_based || $shiprate->is_usps_based || $shiprate->is_fedex_based || $shiprate->is_dhl_based || $shiprate->is_auspost_based || $shiprate->is_canadapost_based ) {
					$has_live_shipping = true;
					break;
				}
			}
			return $has_live_shipping;
		}

		public function ec_using_ups_shipping() {
			$db = new ec_db_admin();
			$shippingrates = $db->get_shipping_data();
			$has_ups_shipping = false;
			foreach ( $shippingrates as $shiprate ) {
				if ( $shiprate->is_ups_based ) {
					$has_ups_shipping = true;
					break;
				}
			}
			return $has_ups_shipping;
		}

		public function ec_ups_shipping_setup() {
			$ups_has_settings = false;
			$ups_setup = false;
			$ups_error_reason = 0;

			$db = new ec_db_admin();
			$setting_row = $db->get_settings();
			$settings = new ec_setting( $setting_row );

			if ( get_option( 'ec_option_ups_use_oauth' ) && get_option( 'ec_option_ups_token_info' ) ) {
				$ups_has_settings = true;
				$ups_class = new ec_ups( $settings );
				$ups_response = $ups_class->get_rate_test(
					"01",
					$setting_row->ups_ship_from_zip,
					$setting_row->ups_country_code,
					"1", 10, 10, 10, 10,
					array(
						(object) array(
							'quantity' => 1,
							'weight' => 1,
							'width' => 10,
							'length' => 10,
							'height' => 10,
							'is_shippable' => 1,
							'exclude_shippable_calculation' => 0,
							'unit_price' => 1,
						)
					)
				);
				if ( isset( $ups_response->RateResponse ) && isset( $ups_response->RateResponse->Response ) && isset( $ups_response->RateResponse->Response->ResponseStatus ) && isset( $ups_response->RateResponse->Response->ResponseStatus->Code ) && '1' == $ups_response->RateResponse->Response->ResponseStatus->Code ) {
					$ups_setup = true;
				} else {
					$ups_error_reason = esc_attr__( 'UPS is not connected properly. Check your WP EasyCart UPS setup.', 'wp-easycart' );
				}
			}
			return ( $ups_has_settings && $ups_setup );
		}

		public function ec_using_usps_shipping() {
			$db = new ec_db_admin();
			$shippingrates = $db->get_shipping_data();
			$has_usps_shipping = false;
			foreach ( $shippingrates as $shiprate ) {
				if ( $shiprate->is_usps_based ) {
					$has_usps_shipping = true;
					break;
				}
			}
			return $has_usps_shipping;
		}

		public function ec_usps_shipping_setup() {
			$usps_has_settings = false;
			$usps_setup = false;
			$usps_error_reason = 0;
			$db = new ec_db_admin();
			$setting_row = $db->get_settings();
			$settings = new ec_setting( $setting_row );
			if ( ! get_option( 'ec_option_usps_v3_custom_old' ) && get_option( 'ec_option_usps_v3_enable' ) && class_exists( 'ec_usps_v3' ) ) {
				if ( ! get_option( 'ec_option_usps_v3_custom' ) || ( '' != get_option( 'ec_option_usps_v3_client_id' ) && '' != get_option( 'ec_option_usps_v3_client_secret' ) ) ) {
					$usps_has_settings = true;
					$usps_class = new ec_usps_v3( $settings );
					$usps_response = $usps_class->get_rate_test(
						"PRIORITY",
						$setting_row->usps_ship_from_zip,
						"US", "1", 0, 0, 0, 0,
						array(
							(object) array(
								'quantity' => 1,
								'weight' => 1,
								'width' => 1,
								'length' => 1,
								'height' => 1,
								'is_shippable' => 1,
								'exclude_shippable_calculation' => 0,
								'unit_price' => 1,
							)
						)
					);
					if ( $usps_response ) {
						$usps_setup = true;
					} else {
						$usps_error_reason = 3;
					}
				}
			} else if ( $setting_row->usps_user_name && $setting_row->usps_ship_from_zip ) {
				$usps_has_settings = true;
				$usps_class = new ec_usps( $settings );
				$usps_response = $usps_class->get_rate_test(
					"PRIORITY",
					$setting_row->usps_ship_from_zip,
					"US", "1", 0, 0, 0, 0,
					array(
						(object) array(
							'quantity' => 1,
							'weight' => 1,
							'width' => 1,
							'length' => 1,
							'height' => 1,
							'is_shippable' => 1,
							'exclude_shippable_calculation' => 0,
							'unit_price' => 1,
						)
					)
				);
				try {
					$usps_xml = new SimpleXMLElement( $usps_response );
					if ( isset( $usps_xml->Error ) ) {
						$usps_error_reason = 3;
					} else if ( $usps_xml->Number ) {
						$usps_error_reason = 1;
					} else if ( $usps_xml->Package[0]->Error ) {
						$usps_error_reason = 2;
					} else {
						$usps_setup = true;
					}
				} catch ( Exception $e ) {
					// Ignore errors
				}
			}
			return ( $usps_has_settings && $usps_setup );
		}

		public function ec_using_fedex_shipping() {
			$db = new ec_db_admin();
			$shippingrates = $db->get_shipping_data();
			$has_fedex_shipping = false;
			foreach ( $shippingrates as $shiprate ) {
				if ( $shiprate->is_fedex_based ) {
					$has_fedex_shipping = true;
					break;
				}
			}
			return $has_fedex_shipping;
		}

		public function ec_fedex_shipping_setup() {
			$fedex_has_settings = false;
			$fedex_setup = false;
			$fedex_error_reason = 0;

			$db = new ec_db_admin();
			$setting_row = $db->get_settings();
			$settings = new ec_setting( $setting_row );

			if ( get_option( 'ec_option_fedex_use_oauth' ) && '' != get_option( 'ec_option_fedex_api_key' ) && '' != get_option( 'ec_option_fedex_api_secret_key' ) ) {
				$fedex_has_settings = true;
				$fedex_class = new ec_fedex( $settings );
				$fedex_response = $fedex_class->get_rate_test( 'FEDEX_GROUND', $setting_row->fedex_ship_from_zip, $setting_row->fedex_country_code, "1", 10, 10, 10, 10, array( (object) array( 'quantity' => 1, 'weight' => 1, 'width' => 10, 'length' => 10, 'height' => 10, 'is_shippable' => 1, 'unit_price' => 1.00 ) ) );
				if ( is_string( $fedex_response ) && 'ERROR' == $fedex_response ) {
					$fedex_error_reason ='error';
				} else {
					$fedex_setup = true;
				}
			}
			return ( $fedex_has_settings && $fedex_setup );
		}

		public function ec_using_dhl_shipping() {
			$db = new ec_db_admin();
			$shippingrates = $db->get_shipping_data();
			$has_dhl_shipping = false;
			foreach ( $shippingrates as $shiprate ) {
				if ( $shiprate->is_dhl_based ) {
					$has_dhl_shipping = true;
					break;
				}
			}
			return $has_dhl_shipping;
		}

		public function ec_dhl_shipping_setup() {
			$dhl_has_settings = false;
			$dhl_setup = false;
			$dhl_error_reason = 0;

			$db = new ec_db_admin();
			$setting_row = $db->get_settings();
			$settings = new ec_setting( $setting_row );

			if ( $setting_row->dhl_site_id && $setting_row->dhl_password && $setting_row->dhl_ship_from_country && $setting_row->dhl_ship_from_zip && $setting_row->dhl_weight_unit ) {
				$dhl_has_settings = true;

				// Run test of the settings
				$dhl_class = new ec_dhl( $settings );
				$dhl_response = $dhl_class->get_rate_test( "N", $setting_row->dhl_ship_from_zip, $setting_row->dhl_ship_from_country, "1" );
				try {
					$dhl_xml = new SimpleXMLElement( $dhl_response );
					if ( $dhl_xml && $dhl_xml->Response && $dhl_xml->Response->Status && $dhl_xml->Response->Status->ActionStatus && $dhl_xml->Response->Status->ActionStatus == "Error" ) {
						$dhl_error_code = $dhl_xml->Response->Status->Condition->ConditionCode;
						$dhl_error_reason = $dhl_xml->Response->Status->Condition->ConditionData;
					} else if ( $dhl_xml && $dhl_xml->Response && $dhl_xml->Response->Note && count( $dhl_xml->Response->Note ) > 0 && $dhl_xml->Response->Note[0]->Status && $dhl_xml->Response->Note[0]->Status->Condition && $dhl_xml->Response->Note[0]->Status->Condition->ConditionData ) {
						$dhl_error_reason = $dhl_xml->Response->Note[0]->Status->Condition->ConditionData;
					} else {
						$dhl_setup = true;
					}
				} catch ( Exception $e ) {
					// Ignore errors
				}
			}

			return ( $dhl_has_settings && $dhl_setup );
		}

		public function ec_using_auspost_shipping() {
			$db = new ec_db_admin();
			$shippingrates = $db->get_shipping_data();
			$has_auspost_shipping = false;
			foreach ( $shippingrates as $shiprate ) {
				if ( $shiprate->is_auspost_based ) {
					$has_auspost_shipping = true;
					break;
				}
			}
			return $has_auspost_shipping;
		}

		public function ec_auspost_shipping_setup() {
			$auspost_has_settings = false;
			$auspost_setup = false;
			$auspost_error_reason = 0;

			$db = new ec_db_admin();
			$setting_row = $db->get_settings();
			$settings = new ec_setting( $setting_row );

			if ( $setting_row->auspost_api_key && $setting_row->auspost_ship_from_zip ) {
				$auspost_has_settings = true;

				// Run test of the settings
				$auspost_class = new ec_auspost( $settings );
				$auspost_response = $auspost_class->get_rate_test( "AUS_PARCEL_EXPRESS", $setting_row->auspost_ship_from_zip, "AU", "1" );

				if ( !$auspost_response )
					$auspost_error_reason = "1";
				else
					$auspost_setup = true;
			}

			return ( $auspost_has_settings && $auspost_setup );
		}

		public function ec_using_canadapost_shipping() {
			$db = new ec_db_admin();
			$shippingrates = $db->get_shipping_data();
			$has_canadapost_shipping = false;
			foreach ( $shippingrates as $shiprate ) {
				if ( $shiprate->is_canadapost_based ) {
					$has_canadapost_shipping = true;
					break;
				}
			}
			return $has_canadapost_shipping;
		}

		public function ec_canadapost_shipping_setup() {
			$canadapost_has_settings = false;
			$canadapost_setup = false;
			$canadapost_error_reason = 0;

			$db = new ec_db_admin();
			$setting_row = $db->get_settings();
			$settings = new ec_setting( $setting_row );

			if ( $setting_row->canadapost_username && $setting_row->canadapost_password && $setting_row->canadapost_customer_number && $setting_row->canadapost_ship_from_zip ) {
				$canadapost_has_settings = true;

				// Run test of the settings
				$canadapost_class = new ec_canadapost( $settings );
				$canadapost_response = $canadapost_class->get_rate_test( "DOM.RP", $setting_row->canadapost_ship_from_zip, "CA", "1" );

				if ( !$canadapost_response )
					$canadapost_error_reason = "1";
				else
					$canadapost_setup = true;
			}

			return ( $canadapost_has_settings && $canadapost_setup );
		}

		public function ec_using_fraktjakt_shipping() {
			$shipping_method = $this->ec_get_shipping_method();
			if ( $shipping_method == "fraktjakt" )
				return true;
			else
				return false;
		}

		public function ec_fraktjakt_shipping_setup() {
			$fraktjakt_has_settings = false;
			$fraktjakt_setup = false;
			$fraktjakt_error_reason = 0;

			$db = new ec_db_admin();
			$setting_row = $db->get_settings();
			$settings = new ec_setting( $setting_row );

			if ( $setting_row->fraktjakt_customer_id != "" && $setting_row->fraktjakt_login_key != "" ) {
				$fraktjakt_has_settings = true;

				// Run test of the settings
				$fraktjakt_class = new ec_fraktjakt( $settings );
				$test_user = new ec_user( "" );
				$test_user->setup_shipping_info_data( "", "", "152-153 Fleet St", "", "London", "", "GB", "EC4A2DQ", "" );

				$fraktjakt_response = $fraktjakt_class->get_shipping_options_test( $test_user );
				try {
					$xml = new SimpleXMLElement( $fraktjakt_response );
					if ( isset( $xml->shipping_products ) && isset( $xml->shipping_products->shipping_product ) && count( $xml->shipping_products->shipping_product ) > 0 ) {
						$fraktjakt_setup = true;
					} else {
						$fraktjakt_error_reason = "1";
					}
				} catch( Exception $e ) {
					// Ignore errors
				}
			}
			return ( $fraktjakt_has_settings && $fraktjakt_setup );
		}

		public function ec_using_no_tax() {
			$db = new ec_db_admin();
			$taxrates = $db->get_taxrates();
			if ( count( $taxrates ) > 0 || get_option( 'ec_option_enable_easy_canada_tax' ) ) {
				return false;
			} else {
				return true;
			}
		}

		public function ec_using_state_tax() {
			$db = new ec_db_admin();
			$taxrates = $db->get_taxrates();
			foreach ( $taxrates as $taxrate ) {
				if ( $taxrate->tax_by_state ) {
					return true;
				}
			}
			return false;
		}

		public function ec_using_country_tax() {
			$db = new ec_db_admin();
			$taxrates = $db->get_taxrates();
			foreach ( $taxrates as $taxrate ) {
				if ( $taxrate->tax_by_country ) {
					return true;
				}
			}
			return false;
		}

		public function ec_using_global_tax() {
			$db = new ec_db_admin();
			$taxrates = $db->get_taxrates();
			foreach ( $taxrates as $taxrate ) {
				if ( $taxrate->tax_by_all ) {
					return true;
				}
			}
			return false;
		}

		public function ec_using_duty_tax() {
			$db = new ec_db_admin();
			$taxrates = $db->get_taxrates();
			foreach ( $taxrates as $taxrate ) {
				if ( $taxrate->tax_by_duty ) {
					return true;
				}
			}
			return false;
		}

		public function ec_using_vat_tax() {
			$db = new ec_db_admin();
			$taxrates = $db->get_taxrates();
			foreach ( $taxrates as $taxrate ) {
				if ( $taxrate->tax_by_vat ) {
					return true;
				}
			}
			return false;
		}

		public function ec_global_vat_setup() {
			$db = new ec_db_admin();
			$countries = $GLOBALS['ec_countries']->countries;
			foreach ( $countries as $country ) {
				if ( $country->vat_rate_cnt > 0 ) {
					return true;
				}
			}
			return false;
		}






		public function ec_no_payment_selected() {
			$manual_payment = get_option( 'ec_option_use_direct_deposit' );
			$affirm = get_option( 'ec_option_use_affirm' );
			$third_party = get_option( 'ec_option_payment_third_party' );
			$live_payment = get_option( 'ec_option_payment_process_method' );

			if ( $manual_payment || $affirm || $third_party || $live_payment )
				return false;
			else
				return true;
		}

		public function ec_manual_payment_selected() {
			$manual_payment = get_option( 'ec_option_use_direct_deposit' );
			if ( $manual_payment )
				return true;
			else
				return false;
		}

		public function ec_affirm_payment_selected() {
			$affirm = get_option( 'ec_option_use_affirm' );
			if ( $affirm )
				return true;
			else
				return false;
		}

		public function ec_third_party_payment_selected() {
			$third_party = get_option( 'ec_option_payment_third_party' );
			if ( $third_party && $third_party != "0" )
				return true;
			else
				return false;
		}

		public function ec_third_party_payment_setup() {
			$third_party = get_option( 'ec_option_payment_third_party' );
			if ( $third_party == "dwolla_thirdparty" ) {
				if ( get_option( 'ec_option_dwolla_thirdparty_key' ) != "" && get_option( 'ec_option_dwolla_thirdparty_secret' ) != "" )
					return true;
				else
					return false;
			} else if ( $third_party == "nets" ) {
				if ( get_option( 'ec_option_nets_merchant_id' ) != "" && get_option( 'ec_option_nets_token' ) != "" )
					return true;
				else
					return false;
			} else if ( $third_party == "payfast_thirdparty" ) {
				if ( get_option( 'ec_option_payfast_merchant_id' ) != "" && get_option( 'ec_option_payfast_merchant_key' ) != "" && get_option( 'ec_option_payfast_passphrase' ) != "" )
					return true;
				else
					return false;
			} else if ( $third_party == "payfort" ) {
				if ( get_option( 'ec_option_payfort_access_code' ) != "" && get_option( 'ec_option_payfort_merchant_id' ) != "" && get_option( 'ec_option_payfort_request_phrase' ) != "" && get_option( 'ec_option_payfort_currency_code' ) != "" )
					return true;
				else
					return false;
			} else if ( $third_party == "paypal" && get_option( 'ec_option_paypal_enable_pay_now' ) == "0" ) { // PayPal Standard
				if ( get_option( 'ec_option_paypal_email' ) != "" )
					return true;
				else
					return false;
			} else if ( $third_party == "paypal" && get_option( 'ec_option_paypal_enable_pay_now' ) == "1" && get_option( 'ec_option_paypal_use_sandbox' ) == '1' ) { // PayPal Express Sandbox
				if ( get_option( 'ec_option_paypal_sandbox_app_id' ) != "" && get_option( 'ec_option_paypal_sandbox_secret' ) != "" )
					return true;
				else if ( get_option( 'ec_option_paypal_enable_pay_now' ) && get_option( 'ec_option_paypal_sandbox_merchant_id' ) != '' )
					return true;
				else
					return false;
			} else if ( $third_party == "paypal" && get_option( 'ec_option_paypal_enable_pay_now' ) == "1" && get_option( 'ec_option_paypal_use_sandbox' ) == '0' ) { // PayPal Express Sandbox
				if ( get_option( 'ec_option_paypal_production_app_id' ) != "" && get_option( 'ec_option_paypal_production_secret' ) != "" )
					return true;
				else if ( get_option( 'ec_option_paypal_enable_pay_now' ) && get_option( 'ec_option_paypal_production_merchant_id' ) != '' )
					return true;
				else
					return false;
			} else if ( $third_party == "sagepay_paynow_za" ) {
				if ( get_option( 'ec_option_sagepay_paynow_za_service_key' ) != "" )
					return true;
				else
					return false;
			} else if ( $third_party == "paypal_advanced" ) {
				if ( get_option( 'ec_option_paypal_advanced_partner' ) != "" && get_option( 'ec_option_paypal_advanced_user' ) != "" && get_option( 'ec_option_paypal_advanced_vendor' ) != "" && get_option( 'ec_option_paypal_advanced_password' ) != "" )
					return true;
				else
					return false;
			} else if ( $third_party == "skrill" ) {
				if ( get_option( 'ec_option_skrill_merchant_id' ) != "" && get_option( 'ec_option_skrill_company_name' ) != "" && get_option( 'ec_option_skrill_email' ) != "" )
					return true;
				else
					return false;
			} else if ( $third_party == "realex" ) {
				if ( get_option( 'ec_option_realex_thirdparty_merchant_id' ) != "" && get_option( 'ec_option_realex_thirdparty_secret' ) != "" )
					return true;
				else
					return false;
			} else if ( $third_party == "redsys" ) {
				if ( get_option( 'ec_option_redsys_merchant_code' ) != "" && get_option( 'ec_option_redsys_terminal' ) != "" && get_option( 'ec_option_redsys_currency' ) != "" && get_option( 'ec_option_redsys_key' ) != "" )
					return true;
				else
					return false;
			} else if ( $third_party == "paymentexpress_thirdparty" ) {
				if ( get_option( 'ec_option_payment_express_thirdparty_username' ) != "" && get_option( 'ec_option_payment_express_thirdparty_key' ) != "" )
					return true;
				else
					return false;
			} else if ( $third_party == "custom_thirdparty" ) {
				return true;
			}
		}

		public function ec_get_third_party_method() {
			$third_party = get_option( 'ec_option_payment_third_party' );
			if ( $third_party == "dwolla_thirdparty" )
				return "Dwolla";
			else if ( $third_party == "nets" )
				return "Nets Netaxept";
			else if ( $third_party == "payfast_thirdparty" )
				return "Payfast";
			else if ( $third_party == "payfort" )
				return "Payfort";
			else if ( $third_party == "paypal" )
				return "PayPal";
			else if ( $third_party == "sagepay_paynow_za" )
				return "SagePay Pay Now South Africa";
			else if ( $third_party == "paypal_advanced" )
				return "PayPal Advanced";
			else if ( $third_party == "skrill" )
				return "Skrill";
			else if ( $third_party == "realex" )
				return "RealEx";
			else if ( $third_party == "redsys" )
				return "Redsys";
			else if ( $third_party == "paymentexpress_thirdparty" )
				return "Payment Express PxPay 2.0";
			else if ( $third_party == "custom_thirdparty" )
				return __( "Custom Gateway", 'wp-easycart' );
		}

		public function ec_live_payment_selected() {
			$live_payment = get_option( 'ec_option_payment_process_method' );
			if ( $live_payment && $live_payment != "0" )
				return true;
			else
				return false;
		}

		public function ec_live_payment_setup() {
			$live_payment = get_option( 'ec_option_payment_process_method' );
			if ( $live_payment == "authorize" ) {
				if ( get_option( 'ec_option_authorize_login_id' ) != "" && get_option( 'ec_option_authorize_trans_key' ) != "" )
					return true;
				else
					return false;
			} else if ( $live_payment == "beanstream" ) {
				if ( get_option( 'ec_option_beanstream_merchant_id' ) != "" && get_option( 'ec_option_beanstream_api_passcode' ) != "" )
					return true;
				else
					return false;
			} else if ( $live_payment == "braintree" ) {
				if ( get_option( 'ec_option_braintree_merchant_id' ) != "" && get_option( 'ec_option_braintree_public_key' ) != "" &&  get_option( 'ec_option_braintree_private_key' ) != "" )
					return true;
				else
					return false;
			} else if ( $live_payment == "chronopay" ) {
				if ( get_option( 'ec_option_chronopay_currency' ) != "" && get_option( 'ec_option_chronopay_product_id' ) != "" &&  get_option( 'ec_option_chronopay_shared_secret' ) != "" )
					return true;
				else
					return false;
			} else if ( $live_payment == "eway" ) {
				if ( get_option( 'ec_option_eway_customer_id' ) != "" || ( get_option( 'ec_option_eway_customer_id' ) != "" && get_option( 'ec_option_eway_api_key' ) != "" ) )
					return true;
				else
					return false;
			} else if ( $live_payment == "firstdata" ) {
				if ( get_option( 'ec_option_firstdatae4_exact_id' ) != "" && get_option( 'ec_option_firstdatae4_password' ) != "" && get_option( 'ec_option_firstdatae4_key_id' ) != "" && get_option( 'ec_option_firstdatae4_key' ) != "" )
					return true;
				else
					return false;
			} else if ( $live_payment == "goemerchant" ) {
				if ( get_option( 'ec_option_goemerchant_trans_center_id' ) != "" && get_option( 'ec_option_goemerchant_gateway_id' ) != "" &&  get_option( 'ec_option_goemerchant_processor_id' ) != "" )
					return true;
				else
					return false;
			} else if ( $live_payment == "intuit" ) {
				if ( get_option( 'ec_option_intuit_oauth_version' ) == 1 && get_option( 'ec_option_intuit_app_token' ) != "" && get_option( 'ec_option_intuit_consumer_key' ) != "" && get_option( 'ec_option_intuit_consumer_secret' ) != "" && get_option( 'ec_option_intuit_realm_id' ) != "" && get_option( 'ec_option_intuit_access_token_secret' ) != "" )
					return true;
				else if ( get_option( 'ec_option_intuit_oauth_version' ) == 2 && get_option( 'ec_option_intuit_consumer_key' ) != "" && get_option( 'ec_option_intuit_consumer_secret' ) != "" && get_option( 'ec_option_intuit_realm_id' ) != "" )
					return true;
				else if ( get_option( 'ec_option_intuit_oauth_version' ) == 3 && get_option( 'ec_option_intuit_refresh_token' ) != "" )
					return true;
				else
					return false;
			} else if ( $live_payment == "migs" ) {
				if ( get_option( 'ec_option_migs_signature' ) != "" && get_option( 'ec_option_migs_access_code' ) != "" && get_option( 'ec_option_migs_merchant_id' ) != "" )
					return true;
				else
					return false;
			} else if ( $live_payment == "moneris_ca" ) {
				if ( get_option( 'ec_option_moneris_ca_store_id' ) != "" && get_option( 'ec_option_moneris_ca_api_token' ) != "" )
					return true;
				else
					return false;
			} else if ( $live_payment == "moneris_us" ) {
				if ( get_option( 'ec_option_moneris_us_store_id' ) != "" && get_option( 'ec_option_moneris_us_api_token' ) != "" )
					return true;
				else
					return false;
			} else if ( $live_payment == "nmi" ) {
				if ( get_option( 'ec_option_nmi_username' ) != "" && get_option( 'ec_option_nmi_password' ) != "" )
					return true;
				else
					return false;
			} else if ( $live_payment == "payline" ) {
				if ( get_option( 'ec_option_payline_username' ) != "" && get_option( 'ec_option_payline_password' ) != "" && get_option( 'ec_option_payline_processor_id' ) != "" && get_option( 'ec_option_payline_currency' ) != "" )
					return true;
				else
					return false;
			} else if ( $live_payment == "paymentexpress" ) {
				if ( get_option( 'ec_option_payment_express_username' ) != "" && get_option( 'ec_option_payment_express_password' ) != "" )
					return true;
				else
					return false;
			} else if ( $live_payment == "paypal_pro" ) {
				if ( get_option( 'ec_option_paypal_pro_partner' ) != "" && get_option( 'ec_option_paypal_pro_user' ) != "" &&  get_option( 'ec_option_paypal_pro_vendor' ) != "" &&  get_option( 'ec_option_paypal_pro_password' ) != "" )
					return true;
				else
					return false;
			} else if ( $live_payment == "paypal_payments_pro" ) {
				if ( get_option( 'ec_option_paypal_payments_pro_user' ) != "" && get_option( 'ec_option_paypal_payments_pro_password' ) != "" &&  get_option( 'ec_option_paypal_payments_pro_signature' ) != "" )
					return true;
				else
					return false;
			} else if ( $live_payment == "paypoint" ) {
				if ( get_option( 'ec_option_paypoint_merchant_id' ) != "" && get_option( 'ec_option_paypoint_vpn_password' ) != "" &&  get_option( 'ec_option_paypoint_vpn_password' ) != "0" )
					return true;
				else
					return false;
			} else if ( $live_payment == "realex" ) {
				if ( get_option( 'ec_option_realex_merchant_id' ) != "" && get_option( 'ec_option_realex_secret' ) != "" )
					return true;
				else
					return false;
			} else if ( $live_payment == "sagepay" ) {
				if ( get_option( 'ec_option_sagepay_vendor' ) != "" )
					return true;
				else
					return false;
			} else if ( $live_payment == "sagepayus" ) {
				if ( get_option( 'ec_option_sagepayus_mid' ) != "" && get_option( 'ec_option_sagepayus_mkey' ) != "" && get_option( 'ec_option_sagepayus_application_id' ) != "" )
					return true;
				else
					return false;
			} else if ( $live_payment == "securenet" ) {
				if ( get_option( 'ec_option_securenet_id' ) != "" && get_option( 'ec_option_securenet_secure_key' ) != "" )
					return true;
				else
					return false;
			} else if ( $live_payment == "securepay" ) {
				if ( get_option( 'ec_option_securepay_merchant_id' ) != "" && get_option( 'ec_option_securepay_password' ) != "" )
					return true;
				else
					return false;
			} else if ( $live_payment == "stripe" ) {
				if ( get_option( 'ec_option_stripe_api_key' ) != "" )
					return true;
				else
					return false;
			} else if ( $live_payment == "stripe_connect" ) {
				if ( ( get_option( 'ec_option_stripe_connect_use_sandbox' ) && get_option( 'ec_option_stripe_connect_sandbox_access_token' ) != '' ) || ( !get_option( 'ec_option_stripe_connect_use_sandbox' ) && get_option( 'ec_option_stripe_connect_production_access_token' ) != '' ) )
					return true;
				else
					return false;
			} else if ( $live_payment == "square" ) {
				if ( get_option( 'ec_option_square_access_token' ) != "" )
					return true;
				else
					return false;
			} else if ( $live_payment == "virtualmerchant" ) {
				if ( get_option( 'ec_option_virtualmerchant_ssl_merchant_id' ) != "" && get_option( 'ec_option_virtualmerchant_ssl_user_id' ) != "" && get_option( 'ec_option_virtualmerchant_ssl_pin' ) != "" )
					return true;
				else
					return false;
			} else if ( $live_payment == "custom" ) {
				if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/ec_customgateway.php' ) )
					return true;
				else
					return false;
			}
		}

		public function ec_get_live_payment_method() {
			$live_payment = get_option( 'ec_option_payment_process_method' );
			if ( $live_payment == "authorize" ) {
				return "Authorize.Net";
			} else if ( $live_payment == "beanstream" ) {
				return "Beanstream";
			} else if ( $live_payment == "braintree" ) {
				return "Braintree S2S";
			} else if ( $live_payment == "chronopay" ) {
				return "Chronopay";
			} else if ( $live_payment == "eway" ) {
				return "Eway";
			} else if ( $live_payment == "firstdata" ) {
				return "First Data Global Gateway e4";
			} else if ( $live_payment == "goemerchant" ) {
				return "GoeMerchant";
			} else if ( $live_payment == "intuit" ) {
				return "Intuit Payments";
			} else if ( $live_payment == "migs" ) {
				return "MasterCard Internet Gateway Service (MIGS)";
			} else if ( $live_payment == "moneris_ca" ) {
				return "Moneris Canada";
			} else if ( $live_payment == "moneris_us" ) {
				return "Moneris US";
			} else if ( $live_payment == "nmi" ) {
				return "Network Merchants (NMI)";
			} else if ( $live_payment == "payline" ) {
				return "Payline";
			} else if ( $live_payment == "paymentexpress" ) {
				return "Payment Express PxPost";
			} else if ( $live_payment == "paypal_pro" ) {
				return "PayPal PayFlow Pro";
			} else if ( $live_payment == "paypal_payments_pro" ) {
				return "PayPal Payments Pro";
			} else if ( $live_payment == "paypoint" ) {
				return "PayPoint";
			} else if ( $live_payment == "realex" ) {
				return "Realex";
			} else if ( $live_payment == "sagepay" ) {
				return "Sagepay";
			} else if ( $live_payment == "sagepayus" ) {
				return "Sagepay US";
			} else if ( $live_payment == "securenet" ) {
				return "WorldPay";
			} else if ( $live_payment == "securepay" ) {
				return "SecurePay";
			} else if ( $live_payment == "stripe" ) {
				return "Stripe";
			} else if ( $live_payment == "stripe_connect" ) {
				return "Stripe";
			} else if ( $live_payment == "square" ) {
				return "Square";
			} else if ( $live_payment == "virtualmerchant" ) {
				return "Converge (Virtual Merchant)";
			} else if ( $live_payment == "custom" ) {
				return __( "Custom Payment Gateway", 'wp-easycart' );
			}
		}
	}
endif;

function wp_easycart_admin_store_status() {
	return wp_easycart_admin_store_status::instance();
}
wp_easycart_admin_store_status();
