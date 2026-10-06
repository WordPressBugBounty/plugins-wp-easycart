<?php
/**
 * WP EasyCart — the reCAPTCHA key test ( Settings › Accounts › Spam protection, 6.0.3 ).
 *
 * The switch ( ec_option_enable_recaptcha ) turns on only once the saved keys pass a test: the merchant ticks a real
 * reCAPTCHA box drawn with the saved site key, and the server checks that answer with Google using the saved secret key.
 * A pass is kept against those keys ( option ec_option_recaptcha_verified, wp_easycart_recaptcha_test_record() );
 * changing either key clears it, and wp_easycart_recaptcha_ready() keeps reCAPTCHA off the storefront until the new
 * keys pass. AJAX ecv2_recaptcha_test / ecv2_recaptcha_status, plain guard ecv2_recaptcha_guard().
 *
 * @package WP_EasyCart
 * @since   6.0.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'ecv2_recaptcha_guard' ) ) {
	/**
	 * The test's AJAX guard: the page's nonce and a store manager.
	 *
	 * @since 6.0.3
	 */
	function ecv2_recaptcha_guard() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wp-easycart-ecv2-recaptcha' ) ) {
			wp_send_json_error( array( 'message' => __( 'This page has expired. Reload it and try again.', 'wp-easycart' ) ) );
		}
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_settings' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- EasyCart's own roles register this capability.
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'wp-easycart' ) ) );
		}
	}
}

if ( ! class_exists( 'wp_easycart_admin_recaptcha' ) ) :

	/**
	 * The reCAPTCHA key test.
	 */
	final class wp_easycart_admin_recaptcha {

		/** Google's answer check. */
		const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

		/**
		 * Hooks.
		 */
		public static function init() {
			add_action( 'wp_ajax_ecv2_recaptcha_test', array( __CLASS__, 'ajax_test' ) );
			add_action( 'wp_ajax_ecv2_recaptcha_status', array( __CLASS__, 'ajax_status' ) );
		}

		/**
		 * Where the saved keys stand.
		 *
		 * @return array state ( missing | untested | changed | tested | existing ), tone ( muted | warn | bad | good ), text, can_test,
		 *               site_key, button.
		 */
		public static function status() {
			$site   = trim( (string) get_option( 'ec_option_recaptcha_site_key' ) );
			$secret = trim( (string) get_option( 'ec_option_recaptcha_secret_key' ) );
			$out    = array(
				'site_key' => $site,
				'can_test' => ( '' !== $site && '' !== $secret ),
				'button'   => __( 'Test keys', 'wp-easycart' ),
			);
			if ( ! $out['can_test'] ) {
				return array_merge(
					$out,
					array(
						'state' => 'missing',
						'tone'  => 'muted',
						'text'  => __( 'Enter your site key and secret key and save them, then test them here. reCAPTCHA can be switched on once they pass.', 'wp-easycart' ),
					)
				);
			}
			if ( wp_easycart_recaptcha_verified() ) {
				$record        = wp_easycart_recaptcha_test_record();
				$out['button'] = __( 'Test again', 'wp-easycart' );
				if ( 'existing' === ( isset( $record['source'] ) ? $record['source'] : '' ) || empty( $record['at'] ) ) {
					return array_merge(
						$out,
						array(
							'state' => 'existing',
							'tone'  => 'good',
							'text'  => __( 'These keys were already in use before this update. Test them again any time.', 'wp-easycart' ),
						)
					);
				}
				$when = function_exists( 'wp_date' ) ? wp_date( get_option( 'date_format' ), (int) $record['at'] ) : date_i18n( get_option( 'date_format' ), (int) $record['at'] );
				return array_merge(
					$out,
					array(
						'state' => 'tested',
						'tone'  => 'good',
						/* translators: %s: date the keys passed the test. */
						'text'  => sprintf( __( 'These keys passed a test on %s.', 'wp-easycart' ), $when ),
					)
				);
			}
			if ( get_option( 'ec_option_enable_recaptcha' ) ) {
				return array_merge(
					$out,
					array(
						'state' => 'changed',
						'tone'  => 'bad',
						'text'  => __( 'Your keys changed, so reCAPTCHA is off on your forms until these keys pass a test.', 'wp-easycart' ),
					)
				);
			}
			return array_merge(
				$out,
				array(
					'state' => 'untested',
					'tone'  => 'warn',
					'text'  => __( 'Not tested yet. reCAPTCHA can be switched on once these keys pass a test.', 'wp-easycart' ),
				)
			);
		}

		/**
		 * The test row ( an html row of Settings › Accounts ).
		 */
		public static function print_row() {
			$status = self::status();
			?>
			<div class="ecst-recaptcha" id="ecst_recaptcha_test" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp-easycart-ecv2-recaptcha' ) ); ?>" data-site-key="<?php echo esc_attr( $status['site_key'] ); ?>" data-state="<?php echo esc_attr( $status['state'] ); ?>">
				<p class="ecst-recaptcha-status is-<?php echo esc_attr( $status['tone'] ); ?>"><span class="ecst-recaptcha-dot" aria-hidden="true"></span><span class="ecst-recaptcha-text"><?php echo esc_html( $status['text'] ); ?></span></p>
				<div class="ecst-recaptcha-actions">
					<button type="button" class="ecv2-btn ecv2-btn-sm ecst-recaptcha-run"<?php disabled( ! $status['can_test'] ); ?>><?php echo esc_html( $status['button'] ); ?></button>
					<span class="ecst-recaptcha-unsaved" hidden><?php esc_html_e( 'Save your key changes first: the test uses the saved keys.', 'wp-easycart' ); ?></span>
				</div>
				<div class="ecst-recaptcha-stage" hidden>
					<p class="ecst-recaptcha-hint"><?php esc_html_e( 'Tick the box. Google then checks your answer with the secret key. If the box shows an error instead, the site key is wrong, is not a v2 "I\'m not a robot" key, or does not list this site\'s domain.', 'wp-easycart' ); ?></p>
					<div class="ecst-recaptcha-box"></div>
				</div>
				<p class="ecst-recaptcha-result" role="status" aria-live="polite" hidden></p>
			</div>
			<?php
		}

		/**
		 * Script and styles for the test row ( Settings › Accounts, the Spam protection section's enqueue ).
		 */
		public static function enqueue() {
			wp_enqueue_style( 'wp_easycart_admin_recaptcha_v2', plugins_url( 'wp-easycart/admin/css/settings-recaptcha-v2.css', EC_PLUGIN_DIRECTORY ), array(), EC_CURRENT_VERSION );
			wp_enqueue_script( 'wp_easycart_admin_recaptcha_v2', plugins_url( 'wp-easycart/admin/js/settings-recaptcha-v2.js', EC_PLUGIN_DIRECTORY ), array( 'jquery' ), EC_CURRENT_VERSION, true );
			wp_localize_script(
				'wp_easycart_admin_recaptcha_v2',
				'ecst_recaptcha',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'text'     => array(
						'loading'  => __( 'Loading the reCAPTCHA box…', 'wp-easycart' ),
						'checking' => __( 'Checking with Google…', 'wp-easycart' ),
						'expired'  => __( 'The box expired. Tick it again.', 'wp-easycart' ),
						'no_load'  => __( 'Google’s reCAPTCHA script could not load. Check your connection or any script blocker, then try again.', 'wp-easycart' ),
						'error'    => __( 'Something went wrong. Reload the page and try again.', 'wp-easycart' ),
					),
				)
			);
		}

		/**
		 * Why Google refused the test, in words.
		 *
		 * @param array $codes Google's error-codes.
		 * @return string
		 */
		public static function error_text( $codes ) {
			$codes = array_map( 'strval', (array) $codes );
			if ( array_intersect( $codes, array( 'missing-input-secret', 'invalid-input-secret' ) ) ) {
				return __( 'Google does not recognise the secret key. Copy it again from your reCAPTCHA admin console.', 'wp-easycart' );
			}
			if ( in_array( 'timeout-or-duplicate', $codes, true ) ) {
				return __( 'The box expired or was already used. Tick it again.', 'wp-easycart' );
			}
			if ( array_intersect( $codes, array( 'missing-input-response', 'invalid-input-response' ) ) ) {
				return __( 'The secret key does not belong to this site key. Both keys must come from the same reCAPTCHA site.', 'wp-easycart' );
			}
			/* translators: %s: Google's error codes. */
			return sprintf( __( 'Google refused the test ( %s ).', 'wp-easycart' ), $codes ? implode( ', ', $codes ) : __( 'no reason given', 'wp-easycart' ) );
		}

		/**
		 * AJAX ecv2_recaptcha_test: POST token ( the ticked box's answer ). Checks it with Google using the saved secret key and
		 * records a pass against the saved keys.
		 */
		public static function ajax_test() {
			ecv2_recaptcha_guard();
			$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
			$site  = trim( (string) get_option( 'ec_option_recaptcha_site_key' ) );
			$key   = trim( (string) get_option( 'ec_option_recaptcha_secret_key' ) );
			if ( '' === $site || '' === $key ) {
				wp_send_json_error( array( 'message' => __( 'Save both keys first.', 'wp-easycart' ) ) );
			}
			if ( '' === $token ) {
				wp_send_json_error( array( 'message' => __( 'Tick the reCAPTCHA box first.', 'wp-easycart' ) ) );
			}
			$response = wp_remote_post(
				self::VERIFY_URL,
				array(
					'timeout' => 10,
					'body'    => array(
						'secret'   => $key,
						'response' => $token,
					),
				)
			);
			if ( is_wp_error( $response ) ) {
				/* translators: %s: the connection error. */
				wp_send_json_error( array( 'message' => sprintf( __( 'Google could not be reached: %s', 'wp-easycart' ), $response->get_error_message() ) ) );
			}
			$json = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			if ( ! is_array( $json ) ) {
				wp_send_json_error( array( 'message' => __( 'Google sent an answer that could not be read. Try again.', 'wp-easycart' ) ) );
			}
			if ( empty( $json['success'] ) ) {
				wp_send_json_error(
					array(
						'message' => self::error_text( isset( $json['error-codes'] ) ? $json['error-codes'] : array() ),
						'status'  => self::status(),
					)
				);
			}
			if ( isset( $json['score'] ) ) {
				wp_send_json_error( array( 'message' => __( 'These are reCAPTCHA v3 keys. WP EasyCart uses the v2 "I\'m not a robot" checkbox: create v2 keys for this site.', 'wp-easycart' ) ) );
			}
			update_option(
				'ec_option_recaptcha_verified',
				array(
					'fingerprint' => wp_easycart_recaptcha_fingerprint( $site, $key ),
					'at'          => time(),
					'host'        => isset( $json['hostname'] ) ? sanitize_text_field( (string) $json['hostname'] ) : '',
					'source'      => 'test',
				),
				false
			);
			/**
			 * The saved reCAPTCHA keys passed a test.
			 *
			 * @since 6.0.3
			 * @param string $host The site Google says the box was ticked on.
			 */
			do_action( 'wp_easycart_recaptcha_keys_tested', isset( $json['hostname'] ) ? (string) $json['hostname'] : '' );
			wp_send_json_success(
				array(
					'message' => get_option( 'ec_option_enable_recaptcha' ) ? __( 'The keys work. reCAPTCHA is on for your forms again.', 'wp-easycart' ) : __( 'The keys work. You can switch reCAPTCHA on now.', 'wp-easycart' ),
					'status'  => self::status(),
				)
			);
		}

		/**
		 * AJAX ecv2_recaptcha_status: the row's status after the keys were saved.
		 */
		public static function ajax_status() {
			ecv2_recaptcha_guard();
			wp_send_json_success( array( 'status' => self::status() ) );
		}
	}

	wp_easycart_admin_recaptcha::init();

endif;
