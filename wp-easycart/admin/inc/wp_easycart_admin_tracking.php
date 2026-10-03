<?php
/**
 * Usage data, for stores that chose to share it ( Settings › Additional settings › Anonymous usage data, the Allow notice or
 * the setup wizard ).
 *
 * 6.0.2: sending is back, without slowing the store down. Events wait in a small queue ( option, autoload off, at most
 * MAX_QUEUE ) and one WP-Cron event posts them in a batch a minute later, so nothing is sent on a shopper's request or while
 * a screen saves. Only the deactivation events go at once ( the plugin is about to stop ), without waiting for the answer.
 * Every send carries a random install id ( never derived from the site ) and the shared fields once: WordPress, PHP and
 * WP EasyCart versions, language and plan. Nothing personal: no site address, emails or names ( scrub() removes them from
 * free text and refuses fields named for them; the request has its own user agent ).
 *
 * Loaded from admin/admin-init.php only while sharing is on; wpeasycart.php loads it for the WP-Cron batch, when sharing is
 * turned on ( wp_easycart_tracking_option_changed() fires wpeasycart_admin_usage_tracking_accepted ) or off ( forget() ), and
 * after an update ( catch_up(): a store that opted in before 6.0.2 sends its setup once ).
 *
 * Filters: wp_easycart_tracking_url ( the receiver; '' sends nothing ), wp_easycart_tracking_event ( array, or false to drop ).
 *
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_tracking' ) ) :

	/**
	 * The usage events, their queue and the sender.
	 */
	final class wp_easycart_admin_tracking {

		/** The receiver. */
		const URL = 'https://connect.wpeasycart.com/logging/logging.php';

		/** Events waiting for the next batch ( autoload off ): array( 'events' => list, 'attempts' => failed sends of the oldest batch ). */
		const QUEUE_OPTION = 'wp_easycart_tracking_queue';

		/** Random 32-hex install id ( autoload off ), made on the first send, forgotten when sharing is turned off. */
		const ID_OPTION = 'wp_easycart_tracking_install_id';

		/** The WP-Cron event that posts the queue. */
		const HOOK = 'wp_easycart_tracking_flush';

		/** Events kept at most ( the oldest go first ). */
		const MAX_QUEUE = 50;

		/** Events per request ( the receiver takes up to 25 ). */
		const BATCH = 25;

		/** Failed sends of one batch before it is dropped. */
		const MAX_ATTEMPTS = 3;

		/** Seconds the WP-Cron send waits for the receiver. */
		const TIMEOUT = 5;

		/** Seconds a deactivation send may take ( it does not wait for the answer ). */
		const DEACTIVATION_TIMEOUT = 2;

		/** Events queued once while they wait ( an import fires product_inserted for every product ). */
		const COALESCE = array( 'product_inserted' );

		/**
		 * The one instance.
		 *
		 * @var wp_easycart_admin_tracking|null
		 */
		protected static $_instance = null; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore -- the singleton name every WP EasyCart controller uses.

		/**
		 * The snapshot goes once per request.
		 *
		 * @var bool
		 */
		private $granted = false;

		/**
		 * COALESCE events already queued in this request.
		 *
		 * @var array
		 */
		private $coalesced = array();

		/**
		 * The instance ( made, with its hooks, on first use ).
		 *
		 * @return wp_easycart_admin_tracking
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		/** The events that are tracked. */
		public function __construct() {
			add_action( 'wpeasycart_admin_usage_tracking_accepted', array( $this, 'access_granted' ) );
			add_action( 'wpeasycart_activated', array( $this, 'plugin_activated' ) );
			add_action( 'wpeasycart_deactivated', array( $this, 'plugin_custom_deactivate' ) );
			add_action( 'deactivated_plugin', array( $this, 'plugin_deactivated' ), 10, 2 );
			add_action( 'wpeasycart_manual_billing_updated', array( $this, 'manual_payment_updated' ), 10, 1 );
			add_action( 'wpeasycart_third_party_payment_updated', array( $this, 'third_party_updated' ), 10, 1 );
			add_action( 'wpeasycart_live_gateway_updated', array( $this, 'live_gateway_updated' ), 10, 1 );
			add_action( 'wpeasycart_admin_product_inserted', array( $this, 'product_inserted' ), 10, 1 );
			add_action( 'wpeasycart_admin_demo_data_installed', array( $this, 'demo_data_installed' ) );
			add_action( 'wpeasycart_pro_activated', array( $this, 'plugin_pro_activated' ) );
		}

		/** Whether the store chose to share usage data. Nothing is queued or sent otherwise. */
		public static function enabled() {
			return '1' === (string) get_option( 'ec_option_allow_tracking' );
		}

		/** Sharing turned off: the waiting events, the install id and the WP-Cron event go. */
		public static function forget() {
			delete_option( self::QUEUE_OPTION );
			delete_option( self::ID_OPTION );
			wp_clear_scheduled_hook( self::HOOK );
		}

		/** The store's setup at the moment sharing is turned on. */
		public function access_granted() {
			if ( $this->granted ) {
				return;
			}
			$this->granted = true;
			global $wpdb;
			$settings_row  = $wpdb->get_row( 'SELECT * FROM ec_setting' );
			$product_count = $wpdb->get_var( 'SELECT COUNT( ec_product.product_id ) FROM ec_product' );
			$tax_counts    = $wpdb->get_row( 'SELECT SUM( tax_by_state ) AS state_count, SUM( tax_by_country ) AS country_count, SUM( tax_by_duty ) AS duty_count, SUM( tax_by_vat ) AS vat_count, SUM( tax_by_single_vat ) AS vat_single_count, SUM( tax_by_all ) AS global_count FROM ec_taxrate' );
			/* 6.0.2: a fresh install may have no settings row or tax rates yet; carriers set up through their newer APIs count too. */
			$column    = function ( $row, $name ) {
				return ( is_object( $row ) && isset( $row->{$name} ) ) ? (string) $row->{$name} : '';
			};
			$filled    = function ( $value ) {
				return is_scalar( $value ) && '' !== trim( (string) $value ) && '0' !== (string) $value;
			};
			$tax       = function ( $name ) use ( $tax_counts, $column ) {
				return ( (int) $column( $tax_counts, $name ) > 0 ) ? 1 : 0;
			};
			$init_data = array(
				'third_party'       => (string) get_option( 'ec_option_payment_third_party' ),
				'live_gateway'      => (string) get_option( 'ec_option_payment_process_method' ),
				'shipping_type'     => $column( $settings_row, 'shipping_method' ),
				'product_count'     => (int) $product_count,
				'using_vat'         => ( $tax( 'vat_count' ) || $tax( 'vat_single_count' ) ) ? 1 : 0,
				'using_duty'        => $tax( 'duty_count' ),
				'using_tax_cloud'   => $filled( get_option( 'ec_option_tax_cloud_api_id' ) ) ? 1 : 0,
				'using_ca_tax'      => ( get_option( 'ec_option_enable_easy_canada_tax' ) ) ? 1 : 0,
				'using_state_tax'   => $tax( 'state_count' ),
				'using_country_tax' => $tax( 'country_count' ),
				'using_global_tax'  => $tax( 'global_count' ),
				'using_auspost'     => $filled( $column( $settings_row, 'auspost_api_key' ) ) ? 1 : 0,
				'using_capost'      => $filled( $column( $settings_row, 'canadapost_username' ) ) ? 1 : 0,
				'using_dhl'         => ( $filled( $column( $settings_row, 'dhl_password' ) ) || $filled( get_option( 'ec_option_dhl_api_key' ) ) ) ? 1 : 0,
				'using_fedex'       => ( $filled( $column( $settings_row, 'fedex_key' ) ) || $filled( get_option( 'ec_option_fedex_api_key' ) ) ) ? 1 : 0,
				'using_ups'         => ( $filled( $column( $settings_row, 'ups_password' ) ) || ! empty( get_option( 'ec_option_ups_token_info' ) ) ) ? 1 : 0,
				'using_usps'        => ( $filled( $column( $settings_row, 'usps_user_name' ) ) || get_option( 'ec_option_usps_v3_enable' ) ) ? 1 : 0,
				'post_linking_type' => ( ! get_option( 'ec_option_use_old_linking_style' ) ) ? 'Permalinks' : 'Basic',
			);
			$this->send_tracking( 'access_granted', $init_data );
		}

		/**
		 * After an update ( wpeasycart_update_check() ): a store that chose to share before sending came back ( 6.0.2 ) has never
		 * sent its setup. Queued once: the install id exists from the first send on, and forget() removes it with the opt-in.
		 */
		public function catch_up() {
			if ( ! self::enabled() || false !== get_option( self::ID_OPTION, false ) ) {
				return;
			}
			$queue = $this->queue();
			foreach ( $queue['events'] as $waiting ) {
				if ( isset( $waiting['event'] ) && 'access_granted' === $waiting['event'] ) {
					return;
				}
			}
			$this->access_granted();
		}

		/** WP EasyCart was activated. */
		public function plugin_activated() {
			$this->send_tracking( 'activated' );
		}

		/**
		 * WP EasyCart was deactivated: this event and anything still waiting go now, and the WP-Cron event goes.
		 *
		 * @param string $plugin             The plugin deactivated.
		 * @param bool   $network_activation Network-wide ( unused ).
		 */
		public function plugin_deactivated( $plugin, $network_activation ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- deactivated_plugin passes it.
			$ours = array( 'wp-easycart/wpeasycart.php' );
			if ( function_exists( 'plugin_basename' ) ) {
				$ours[] = plugin_basename( EC_PLUGIN_DIRECTORY . '/wpeasycart.php' );
			}
			if ( in_array( $plugin, $ours, true ) ) {
				$this->send_now( 'deactivated' );
				wp_clear_scheduled_hook( self::HOOK );
			}
		}

		/**
		 * The Plugins screen's Quick feedback form ( deactivate.js, every store; sent by send_feedback() ). The reason goes in English
		 * whatever the admin language, so every store's answers read the same.
		 */
		public function plugin_custom_deactivate() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_manager' ) ) {
				return;
			}
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- ec_admin_ajax_custom_deactivate() checks the nonce ( verify_access() ) before it fires wpeasycart_deactivated.
			$reason_num = isset( $_POST['reason'] ) ? (int) $_POST['reason'] : 0;
			$reasons    = array(
				1 => "The plugin didn't work.",
				2 => 'I found a better plugin.',
				3 => 'I need a Pro or Premium feature and the upgrade cost is too high.',
				4 => 'Plugin is missing a feature that my project requires.',
				5 => "It's a temporary deactivation. I'm just debugging an issue.",
				6 => 'Other.',
			);
			if ( ! isset( $reasons[ $reason_num ] ) ) {
				return;
			}
			$data  = array(
				'reason' => $reasons[ $reason_num ],
			);
			$extra = array(
				2 => 'plugin',
				4 => 'feature',
				6 => 'other',
			);
			if ( isset( $extra[ $reason_num ] ) ) {
				$field          = $extra[ $reason_num ];
				$data[ $field ] = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
			}
			// phpcs:enable WordPress.Security.NonceVerification.Missing
			$this->send_feedback( $data );
		}

		/**
		 * The Quick feedback form's answer ( 6.0.2: the form asks every store, and says what it sends ). A store that shares
		 * usage data sends it with whatever still waits ( send_now() ); any other store sends only this answer, the person
		 * having chosen to submit it, under a one-off id that is not kept, and nothing else is queued or sent.
		 *
		 * @param array $data reason, and plugin / feature / other when given.
		 * @return bool Whether a request went out.
		 */
		public function send_feedback( $data ) {
			if ( self::enabled() ) {
				return $this->send_now( 'deactivation_reason', $data );
			}
			$item = $this->event( 'deactivation_reason', $data, true );
			if ( ! $item ) {
				return false;
			}
			$result = $this->post(
				array( $item ),
				array(
					'blocking' => false,
					'timeout'  => self::DEACTIVATION_TIMEOUT,
				),
				true
			);
			return 'off' !== $result;
		}

		/**
		 * Manual payment ( direct deposit ) switched on or off.
		 *
		 * @param int|bool $enabled On.
		 */
		public function manual_payment_updated( $enabled ) {
			if ( $enabled ) {
				$this->send_tracking( 'manual_payment_enabled' );
			} else {
				$this->send_tracking( 'manual_payment_disabled' );
			}
		}

		/**
		 * The third party gateway changed.
		 *
		 * @param string $method Gateway slug.
		 */
		public function third_party_updated( $method ) {
			$this->send_tracking(
				'third_party_updated',
				array(
					'method' => $method,
				)
			);
		}

		/**
		 * The live card gateway changed.
		 *
		 * @param string $method Gateway slug.
		 */
		public function live_gateway_updated( $method ) {
			$this->send_tracking(
				'live_gateway_updated',
				array(
					'method' => $method,
				)
			);
		}

		/**
		 * A product was added ( queued once while one waits ).
		 *
		 * @param int $product_id Product ( not sent ).
		 */
		public function product_inserted( $product_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- the hook passes it; only the event is sent.
			$this->send_tracking( 'product_inserted' );
		}

		/** The demo data was installed. */
		public function demo_data_installed() {
			$this->send_tracking( 'demo_data_installed' );
		}

		/** WP EasyCart PRO was activated. */
		public function plugin_pro_activated() {
			$this->send_tracking( 'pro_activated' );
		}

		/**
		 * Queue one event for the next batch ( a minute out, on WP-Cron ). Nothing happens unless the store shares usage data.
		 *
		 * @param string $event Event name.
		 * @param array  $args  The event's own fields.
		 * @return bool Whether it waits to be sent.
		 */
		public function send_tracking( $event, $args = array() ) {
			$item = $this->event( $event, $args );
			if ( ! $item ) {
				return false;
			}
			$queue = $this->queue();
			if ( in_array( $item['event'], self::COALESCE, true ) ) {
				if ( isset( $this->coalesced[ $item['event'] ] ) ) {
					return true;
				}
				$this->coalesced[ $item['event'] ] = true;
				foreach ( $queue['events'] as $waiting ) {
					if ( isset( $waiting['event'] ) && $waiting['event'] === $item['event'] ) {
						return true;
					}
				}
			}
			$queue['events'][] = $item;
			if ( count( $queue['events'] ) > self::MAX_QUEUE ) {
				$queue['events'] = array_slice( $queue['events'], -self::MAX_QUEUE );
			}
			$this->save_queue( $queue );
			$this->schedule( MINUTE_IN_SECONDS );
			return true;
		}

		/**
		 * Send one event now, with whatever still waits, without waiting for the answer ( deactivation: WP-Cron will not run
		 * this plugin again ).
		 *
		 * @param string $event Event name.
		 * @param array  $args  The event's own fields.
		 * @return bool Whether a request went out.
		 */
		public function send_now( $event, $args = array() ) {
			if ( ! self::enabled() ) {
				return false;
			}
			$item   = $this->event( $event, $args );
			$queue  = $this->queue();
			$events = $queue['events'];
			if ( $item ) {
				$events[] = $item;
			}
			delete_option( self::QUEUE_OPTION );
			if ( empty( $events ) ) {
				return false;
			}
			$result = $this->post(
				array_slice( $events, -self::BATCH ),
				array(
					'blocking' => false,
					'timeout'  => self::DEACTIVATION_TIMEOUT,
				)
			);
			return 'off' !== $result;
		}

		/** WP-Cron: post the oldest batch; keep it for a later try when the receiver could not take it. */
		public function flush() {
			if ( ! self::enabled() ) {
				self::forget();
				return;
			}
			$queue = $this->queue();
			if ( empty( $queue['events'] ) ) {
				return;
			}
			$batch  = array_slice( $queue['events'], 0, self::BATCH );
			$result = $this->post( $batch, array( 'timeout' => self::TIMEOUT ) );
			if ( 'off' === $result ) {
				delete_option( self::QUEUE_OPTION );
				return;
			}

			/* Read again: events queued while the request ran stay ( the batch comes off the front by count ). */
			$queue = $this->queue();
			$done  = ( 'retry' !== $result );
			if ( ! $done ) {
				++$queue['attempts'];
				$done = ( $queue['attempts'] >= self::MAX_ATTEMPTS );
			}
			if ( $done ) {
				$queue['events']   = array_slice( $queue['events'], count( $batch ) );
				$queue['attempts'] = 0;
			}
			$this->save_queue( $queue );
			if ( ! empty( $queue['events'] ) ) {
				$this->schedule( $done ? MINUTE_IN_SECONDS : 15 * MINUTE_IN_SECONDS * $queue['attempts'] );
			}
		}

		/**
		 * One request to the receiver: the batch plus the shared fields once.
		 *
		 * @param array $events  Events ( each array( 'event' => name, fields ) ).
		 * @param array $args    wp_remote_post() arguments ( timeout, blocking ).
		 * @param bool  $one_off A one-off id instead of the store's install id ( feedback from a store that does not share ).
		 * @return string sent | rejected ( answered and will never take it ) | retry | off ( no receiver ).
		 */
		private function post( $events, $args, $one_off = false ) {
			/**
			 * Where usage data is sent ( '' sends nothing and empties the queue ).
			 *
			 * @since 6.0.2
			 * @param string $url The receiver.
			 */
			$url = apply_filters( 'wp_easycart_tracking_url', self::URL );
			$url = is_string( $url ) ? esc_url_raw( $url, array( 'https', 'http' ) ) : '';
			if ( '' === $url ) {
				return 'off';
			}
			$body     = array_merge(
				array( 'install_id' => $one_off ? self::random_id() : $this->install_id() ),
				$this->shared(),
				array( 'batch' => wp_json_encode( array_values( $events ) ) )
			);
			$response = wp_remote_post(
				$url,
				array_merge(
					array(
						'timeout'     => self::TIMEOUT,
						'redirection' => 0,
						/* WordPress's own user agent carries the site address. */
						'user-agent'  => 'WP EasyCart/' . str_replace( '_', '.', EC_CURRENT_VERSION ),
						'body'        => $body,
					),
					$args
				)
			);
			if ( is_wp_error( $response ) ) {
				return 'retry';
			}
			if ( isset( $args['blocking'] ) && false === $args['blocking'] ) {
				return 'sent';
			}
			$code = (int) wp_remote_retrieve_response_code( $response );
			if ( $code >= 200 && $code < 300 ) {
				return 'sent';
			}
			if ( 0 === $code || 408 === $code || 429 === $code || $code >= 500 ) {
				return 'retry';
			}
			return 'rejected';
		}

		/** Fields every event shares, read when the batch goes. */
		private function shared() {
			return array(
				'wpversion'   => (string) get_bloginfo( 'version' ),
				'phpversion'  => (string) phpversion(),
				'wpecversion' => EC_CURRENT_VERSION,
				'lang'        => (string) get_bloginfo( 'language' ),
				'license'     => $this->get_license_type(),
			);
		}

		/** Random and made once: never the site's address or anything derived from it. */
		private function install_id() {
			$id = (string) get_option( self::ID_OPTION );
			if ( ! preg_match( '/^[0-9a-f]{32}$/', $id ) ) {
				$id = self::random_id();
				update_option( self::ID_OPTION, $id, false );
			}
			return $id;
		}

		/** 32 random hex characters. */
		private static function random_id() {
			try {
				return bin2hex( random_bytes( 16 ) );
			} catch ( Exception $e ) {
				return md5( wp_generate_password( 64, true, true ) );
			}
		}

		/**
		 * One event as it will be sent, or false ( sharing is off, or a filter dropped it ).
		 *
		 * @param string $event     Event name.
		 * @param array  $args      The event's own fields.
		 * @param bool   $submitted The person submitted it themselves ( the feedback form ), so it goes even when the store
		 *                          does not share usage data.
		 * @return array|false
		 */
		private function event( $event, $args, $submitted = false ) {
			if ( ! $submitted && ! self::enabled() ) {
				return false;
			}
			$data = array( 'event' => sanitize_key( $event ) );
			foreach ( (array) $args as $key => $val ) {
				$data[ $key ] = $val;
			}
			/**
			 * Change or drop one usage event before it is queued ( return false to drop it ). Personal fields are removed after.
			 *
			 * @since 6.0.2
			 * @param array  $data  'event' plus the event's fields.
			 * @param string $event Event name.
			 */
			$data = apply_filters( 'wp_easycart_tracking_event', $data, $event );
			if ( ! is_array( $data ) || empty( $data['event'] ) || ! is_string( $data['event'] ) ) {
				return false;
			}
			return self::scrub( $data );
		}

		/**
		 * Nothing personal leaves the store: fields named for a person, a login or the site are refused, free text loses email
		 * addresses and the site's domain, and values are plain and short.
		 *
		 * @param array $data Event.
		 * @return array
		 */
		public static function scrub( $data ) {
			$personal = array( 'email', 'mail', 'name', 'firstname', 'lastname', 'phone', 'address', 'ip', 'url', 'uri', 'site', 'siteurl', 'home', 'domain', 'host', 'user', 'username', 'login', 'password', 'pass', 'key', 'token', 'secret', 'install', 'batch' );
			$host     = self::site_host();
			$clean    = array();
			foreach ( $data as $key => $value ) {
				$key = sanitize_key( (string) $key );
				if ( '' === $key || array_intersect( preg_split( '/[_-]+/', $key ), $personal ) ) {
					continue;
				}
				if ( is_bool( $value ) ) {
					$value = $value ? 1 : 0;
				} elseif ( is_int( $value ) || ( is_float( $value ) && is_finite( $value ) ) ) {
					$value = $value + 0;
				} elseif ( is_string( $value ) ) {
					$value = preg_replace( '/[\x00-\x1F\x7F]+/', ' ', wp_strip_all_tags( $value ) );
					$value = preg_replace( '/[^\s@<>"\'(),;:]+@[^\s@<>"\'(),;:]+\.[a-z]{2,}/i', '[email]', $value );
					if ( '' !== $host ) {
						$value = str_ireplace( $host, '[site]', $value );
					}
					$value = trim( $value );
					$value = function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 200 ) : substr( $value, 0, 200 );
				} else {
					continue;
				}
				$clean[ $key ] = $value;
			}
			return $clean;
		}

		/** The site's domain ( without www. ), when it has a dot in it. */
		private static function site_host() {
			$host = function_exists( 'home_url' ) ? wp_parse_url( home_url(), PHP_URL_HOST ) : '';
			$host = preg_replace( '/^www\./i', '', strtolower( (string) $host ) );
			return ( false !== strpos( $host, '.' ) ) ? $host : '';
		}

		/**
		 * The waiting events.
		 *
		 * @return array array( 'events' => list, 'attempts' => int ).
		 */
		private function queue() {
			$queue = get_option( self::QUEUE_OPTION );
			if ( ! is_array( $queue ) || ! isset( $queue['events'] ) || ! is_array( $queue['events'] ) ) {
				$queue = array( 'events' => array() );
			}
			$queue['events']   = array_values( $queue['events'] );
			$queue['attempts'] = isset( $queue['attempts'] ) ? (int) $queue['attempts'] : 0;
			return $queue;
		}

		/**
		 * Keep the queue ( an empty one is removed ).
		 *
		 * @param array $queue Queue.
		 */
		private function save_queue( $queue ) {
			if ( empty( $queue['events'] ) ) {
				delete_option( self::QUEUE_OPTION );
				return;
			}
			update_option( self::QUEUE_OPTION, $queue, false );
		}

		/**
		 * One WP-Cron event at a time, however many events wait.
		 *
		 * @param int $delay Seconds from now.
		 */
		private function schedule( $delay ) {
			if ( ! wp_next_scheduled( self::HOOK ) ) {
				wp_schedule_single_event( time() + (int) $delay, self::HOOK );
			}
		}

		/**
		 * The plan, as the receiver knows it: FREE, PRO or PREMIUM, with -EXPIRED for a lapsed license.
		 *
		 * @return string
		 */
		public function get_license_type() {
			$type = 'FREE';
			if ( function_exists( 'wp_easycart_admin_license' ) && wp_easycart_admin_license()->valid_license ) {
				$type = 'PRO';
				if ( function_exists( 'ec_license_manager' ) ) {
					$license_data = ec_license_manager()->ec_get_license();
					if ( isset( $license_data->model_number ) && 'ec410' === strtolower( trim( (string) $license_data->model_number ) ) ) {
						$type = 'PREMIUM';
					}
				}
				if ( ! wp_easycart_admin_license()->active_license ) {
					$type .= '-EXPIRED';
				}
			}
			return $type;
		}
	}
endif;

if ( ! function_exists( 'wp_easycart_admin_tracking' ) ) {
	/**
	 * The usage data instance.
	 *
	 * @return wp_easycart_admin_tracking
	 */
	function wp_easycart_admin_tracking() {
		return wp_easycart_admin_tracking::instance();
	}
}
wp_easycart_admin_tracking();
