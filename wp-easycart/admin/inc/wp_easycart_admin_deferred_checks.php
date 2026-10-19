<?php
/**
 * WP EasyCart Admin — checks that run after the page shows ( 6.0.5, admin review M15 ).
 *
 * Some admin rows read the site's own addresses ( whether customer uploads and paid downloads can be fetched by URL, the live
 * robots.txt ). On a busy or single-worker server those requests waited for the page that asked for them: Diagnostics took
 * 21 seconds the first time and Search & AI 10. A check whose answer is cached prints at once; otherwise its row prints a
 * "Checking…" placeholder ( placeholder() ) and admin/js/deferred-checks-v2.js runs it through AJAX ecv2_deferred_check, one
 * check at a time, and puts the answer in its place.
 *
 * Checks: uploads, downloads ( Diagnostics ), crawlers_row ( Diagnostics' Search & AI row ), search_ai_status,
 * search_ai_crawlers ( Settings › Search & AI ). Filter wp_easycart_admin_deferred_checks adds more ( key => caps, ready,
 * render ).
 *
 * @package WP_EasyCart
 * @since   6.0.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_deferred_checks' ) ) :

	/**
	 * Deferred checks.
	 */
	class wp_easycart_admin_deferred_checks {

		const NONCE = 'wp-easycart-ecv2-deferred-check';

		/** Hooks. */
		public static function init() {
			add_action( 'wp_ajax_ecv2_deferred_check', array( __CLASS__, 'ajax' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		}

		/** The script, on EasyCart screens. */
		public static function enqueue() {
			if ( ! isset( $_GET['page'] ) || 0 !== strpos( sanitize_key( wp_unslash( $_GET['page'] ) ), 'wp-easycart' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which screen this is.
				return;
			}
			wp_enqueue_script( 'wp_easycart_deferred_checks_js', plugins_url( 'wp-easycart/admin/js/deferred-checks-v2.js', EC_PLUGIN_DIRECTORY ), array(), EC_CURRENT_VERSION, true );
			wp_localize_script(
				'wp_easycart_deferred_checks_js',
				'wpecDeferred',
				array(
					'ajax'   => admin_url( 'admin-ajax.php' ),
					'nonce'  => wp_create_nonce( self::NONCE ),
					'failed' => __( 'This check did not finish. Reload the page to try again.', 'wp-easycart' ),
				)
			);
		}

		/**
		 * The checks: key => caps ( any one ), ready ( cached, so the row can print now ), render ( prints the row ).
		 *
		 * @return array
		 */
		public static function checks() {
			$diagnostics = array( 'manage_options', 'wpec_diagnostics' );
			$settings    = array( 'manage_options', 'wpec_settings' );
			$checks      = array(
				'uploads'            => array(
					'caps'   => $diagnostics,
					'ready'  => function () {
						return class_exists( 'wp_easycart_customer_uploads' ) && is_array( get_transient( wp_easycart_customer_uploads::TRANSIENT_ACCESS ) );
					},
					'render' => function () {
						self::access_rows( 'uploads' );
					},
				),
				'downloads'          => array(
					'caps'   => $diagnostics,
					'ready'  => function () {
						return class_exists( 'wp_easycart_customer_uploads' ) && is_array( get_transient( wp_easycart_customer_uploads::TRANSIENT_DOWNLOADS_ACCESS ) );
					},
					'render' => function () {
						self::access_rows( 'downloads' );
					},
				),
				'crawlers_row'       => array(
					'caps'   => $diagnostics,
					'ready'  => array( __CLASS__, 'crawlers_ready' ),
					'render' => function () {
						if ( class_exists( 'wp_easycart_admin_search_ai' ) && method_exists( 'wp_easycart_admin_search_ai', 'crawlers_status_row' ) ) {
							wp_easycart_admin_search_ai::crawlers_status_row();
						}
					},
				),
				'search_ai_status'   => array(
					'caps'   => $settings,
					'ready'  => array( __CLASS__, 'crawlers_ready' ),
					'render' => function () {
						if ( class_exists( 'wp_easycart_admin_search_ai' ) ) {
							wp_easycart_admin_search_ai::render_status();
						}
					},
				),
				'search_ai_crawlers' => array(
					'caps'   => $settings,
					'ready'  => array( __CLASS__, 'crawlers_ready' ),
					'render' => function () {
						if ( class_exists( 'wp_easycart_admin_search_ai' ) ) {
							wp_easycart_admin_search_ai::render_crawlers();
						}
					},
				),
			);
			/**
			 * Checks that run after the page shows.
			 *
			 * @since 6.0.5
			 * @param array $checks key => array( caps, ready, render ).
			 */
			return (array) apply_filters( 'wp_easycart_admin_deferred_checks', $checks );
		}

		/** The robots.txt answer is cached. */
		public static function crawlers_ready() {
			return class_exists( 'wp_easycart_admin_search_ai' ) && is_array( get_transient( wp_easycart_admin_search_ai::CRAWL_TRANSIENT ) );
		}

		/**
		 * Whether a check can print now ( cached, during its own AJAX run, or unknown ).
		 *
		 * @param string $key Check.
		 * @return bool
		 */
		public static function ready( $key ) {
			if ( defined( 'WPEC_DEFERRED_RUN' ) || ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) ) {
				return true;
			}
			$checks = self::checks();
			if ( ! isset( $checks[ $key ] ) ) {
				return true;
			}
			return (bool) call_user_func( $checks[ $key ]['ready'] );
		}

		/**
		 * The row printed while a check waits.
		 *
		 * @param string $key   Check.
		 * @param string $text  "Checking whether …".
		 * @param string $class The row's classes ( Diagnostics' rows, or a settings section's box ).
		 */
		public static function placeholder( $key, $text, $class = 'ec_status_subs' ) {
			echo '<div class="' . esc_attr( $class ) . ' ecv2-deferred" data-ecv2-deferred="' . esc_attr( $key ) . '" role="status" aria-live="polite">';
			echo '<span class="ecv2-deferred-spin" aria-hidden="true"></span><span class="ecv2-deferred-text">' . esc_html( $text ) . '</span>';
			echo '</div>';
		}

		/**
		 * Prints a check now when it can, else its placeholder.
		 *
		 * @param string $key   Check.
		 * @param string $text  Placeholder words.
		 * @param string $class Placeholder classes.
		 */
		public static function maybe( $key, $text, $class = 'ec_status_subs' ) {
			$checks = self::checks();
			if ( ! isset( $checks[ $key ] ) ) {
				return;
			}
			if ( self::ready( $key ) ) {
				call_user_func( $checks[ $key ]['render'] );
				return;
			}
			self::placeholder( $key, $text, $class );
		}

		/** Diagnostics' customer uploads or paid downloads row ( the partial access-checks.php ). */
		public static function access_rows( $area ) {
			if ( ! class_exists( 'wp_easycart_admin_store_status' ) ) {
				return;
			}
			$status    = new wp_easycart_admin_store_status();
			$ecds_area = ( 'downloads' === $area ) ? 'downloads' : 'uploads';
			include EC_PLUGIN_DIRECTORY . '/admin/template/status/store-status/access-checks.php';
		}

		/** AJAX ecv2_deferred_check: runs one check and answers its row. */
		public static function ajax() {
			ecv2_deferred_check_guard();
			$key    = isset( $_POST['check'] ) ? sanitize_key( wp_unslash( $_POST['check'] ) ) : '';
			$checks = self::checks();
			if ( ! isset( $checks[ $key ] ) ) {
				wp_send_json_error( array( 'message' => __( 'Unknown check.', 'wp-easycart' ) ), 400 );
			}
			$allowed = false;
			foreach ( (array) $checks[ $key ]['caps'] as $cap ) {
				$allowed = $allowed || current_user_can( $cap );
			}
			if ( ! $allowed ) {
				wp_send_json_error( array( 'message' => __( 'You do not have access to this check.', 'wp-easycart' ) ), 403 );
			}
			if ( ! defined( 'WPEC_DEFERRED_RUN' ) ) {
				define( 'WPEC_DEFERRED_RUN', true );
			}
			ob_start();
			call_user_func( $checks[ $key ]['render'] );
			wp_send_json_success( array( 'html' => (string) ob_get_clean() ) );
		}
	}

	wp_easycart_admin_deferred_checks::init();

endif;

if ( ! function_exists( 'ecv2_deferred_check_guard' ) ) {
	/** Nonce for ecv2_deferred_check ( each check then asks for its own capability ). */
	function ecv2_deferred_check_guard() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), wp_easycart_admin_deferred_checks::NONCE ) ) {
			wp_send_json_error( array( 'message' => __( 'This page has expired. Reload it and try again.', 'wp-easycart' ) ), 403 );
		}
	}
}
