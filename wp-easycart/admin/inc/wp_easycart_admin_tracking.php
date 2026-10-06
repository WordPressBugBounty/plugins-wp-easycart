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
 * 6.0.3: a weekly check-in ( checkin(): how the store is set up, which features are on, store size and health as bands, never
 * exact counts or anything about customers or orders ), an update event ( updated() ), the getting-started steps ( milestone() ),
 * clicks on locked features ( ajax_upsell(), from upsell.js ), a short list of settings changes ( setting_changed() ), and a
 * forget event when sharing is turned off ( send_forget(): the receiver deletes what it kept for this install ). WP EasyCart
 * activated before the store chose to share sends its activated event with the first batch.
 *
 * Filters: wp_easycart_tracking_url ( the receiver; '' sends nothing ), wp_easycart_tracking_event ( array, or false to drop ),
 * wp_easycart_tracking_checkin_fields ( the check-in's fields; WP EasyCart PRO adds its own ), wp_easycart_tracking_settings ( the
 * settings whose changes are counted ).
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

		/** The weekly check-in's WP-Cron event ( 6.0.3 ). */
		const CHECKIN_HOOK = 'wp_easycart_tracking_checkin';

		/** Getting-started steps already sent ( autoload off, 6.0.3 ). */
		const MILESTONES_OPTION = 'wp_easycart_tracking_milestones';

		/** When WP EasyCart was activated while the store did not share yet ( its activated event waits for consent, 6.0.3 ). */
		const ACTIVATED_OPTION = 'wp_easycart_tracking_activated';

		/** When WP EasyCart was first activated ( 6.0.3; 0 = before this was recorded ). */
		const INSTALLED_OPTION = 'wp_easycart_installed_at';

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
		 * Which of WP EasyCart's tables exist, asked once per check-in ( 6.0.3 ).
		 *
		 * @var array
		 */
		private $tables = array();

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
			/* 6.0.3 */
			add_action( 'wp_ajax_ecv2_tracking_upsell', array( $this, 'ajax_upsell' ) );
			add_action( 'wp_easycart_settings_saved', array( $this, 'setting_changed' ), 10, 4 );
			add_action( 'add_option_ec_option_setup_wizard_done', array( $this, 'wizard_done' ), 10, 0 );
			add_action( 'update_option_ec_option_setup_wizard_done', array( $this, 'wizard_done' ), 10, 0 );
		}

		/** Whether the store chose to share usage data. Nothing is queued or sent otherwise. */
		public static function enabled() {
			return '1' === (string) get_option( 'ec_option_allow_tracking' );
		}

		/**
		 * Sharing turned off: the receiver is asked to delete what it kept for this install ( 6.0.3 ), then the waiting events,
		 * the install id, the steps sent and the WP-Cron events go.
		 */
		public static function forget() {
			self::send_forget();
			delete_option( self::QUEUE_OPTION );
			delete_option( self::ID_OPTION );
			delete_option( self::MILESTONES_OPTION );
			wp_clear_scheduled_hook( self::HOOK );
			wp_clear_scheduled_hook( self::CHECKIN_HOOK );
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

			// 6.0.3: WP EasyCart was activated before the store chose to share ( consent comes after activation ): that activated
			// event goes now, once, when it was within the last 30 days.
			$activated = (int) get_option( self::ACTIVATED_OPTION, 0 );
			if ( $activated > 0 ) {
				delete_option( self::ACTIVATED_OPTION );
				if ( time() - $activated < 30 * DAY_IN_SECONDS ) {
					$this->send_tracking( 'activated', array( 'late' => 1 ) );
				}
			}
			$this->checkin();
			self::schedule_checkin();
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

		/** WP EasyCart was activated ( a store that does not share yet is remembered by wp_easycart_tracking_activated() ). */
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
			if ( '' !== (string) $method && '0' !== (string) $method && ! self::live_in_test( (string) $method ) ) {
				$this->milestone( 'gateway_live' );
			}
		}

		/**
		 * A product was added ( queued once while one waits ).
		 *
		 * @param int $product_id Product ( not sent ).
		 */
		public function product_inserted( $product_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- the hook passes it; only the event is sent.
			$this->send_tracking( 'product_inserted' );
			$this->milestone( 'first_product' );
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

		// 6.0.3: check-in, update, getting started, interest, settings.

		/**
		 * The weekly check-in: how the store is set up and which features are on, with store size and health as bands
		 * ( band() ), never exact counts, amounts, or anything about a customer or an order. Queued like any event.
		 *
		 * @return bool Whether it waits to be sent.
		 */
		public function checkin() {
			if ( ! self::enabled() ) {
				return false;
			}
			return $this->send_tracking( 'checkin', $this->snapshot() );
		}

		/**
		 * The check-in's fields.
		 *
		 * @return array
		 */
		public function snapshot() {
			global $wpdb;
			$this->tables = array();
			$live         = (string) get_option( 'ec_option_payment_process_method' );
			$third        = (string) get_option( 'ec_option_payment_third_party' );
			$lists        = $this->setup_lists();
			$installed    = (int) get_option( self::INSTALLED_OPTION, 0 );
			$first        = $this->table_exists( 'ec_order' ) ? (string) $wpdb->get_var( 'SELECT MIN( order_date ) FROM ec_order' ) : '';
			$first_ts     = ( '' !== $first ) ? strtotime( $first ) : 0;
			$summary      = get_option( 'wp_easycart_reports_summary' );
			$tax          = class_exists( 'wp_easycart_tax_providers' ) && method_exists( 'wp_easycart_tax_providers', 'active_id' ) ? (string) wp_easycart_tax_providers::active_id() : '';

			$data = array(
				'live_gateway'     => ( '0' === $live ) ? '' : $live,
				'live_test'        => ( '' !== $live && '0' !== $live && self::live_in_test( $live ) ) ? 1 : 0,
				'third_party'      => ( '0' === $third ) ? '' : $third,
				'manual'           => get_option( 'ec_option_use_direct_deposit' ) ? 1 : 0,
				'wallet'           => get_option( 'ec_option_amazonpay_enable' ) ? 1 : 0,
				'shipping_type'    => $lists['shipping_type'],
				'carriers'         => implode( ',', $lists['carriers'] ),
				'taxes'            => implode( ',', $lists['taxes'] ),
				'tax_service'      => $tax,
				'onepage'          => get_option( 'ec_option_onepage_checkout' ) ? 1 : 0,
				'protection'       => sanitize_key( (string) get_option( 'ec_option_checkout_protection_level', 'standard' ) ),
				'consent'          => sanitize_key( (string) get_option( 'ec_option_marketing_consent', 'off' ) ),
				'pay_links'        => get_option( 'ec_option_order_pay_links' ) ? 1 : 0,
				'reports_summary'  => ( is_array( $summary ) && ! empty( $summary['on'] ) ) ? 1 : 0,
				'store_activity'   => ( '0' !== (string) get_option( 'ec_option_store_activity', '1' ) ) ? 1 : 0,
				'elementor'        => defined( 'ELEMENTOR_VERSION' ) ? 1 : 0,
				'extensions'       => implode( ',', self::extensions() ),
				'products'         => self::band( $this->count( 'SELECT COUNT(*) FROM ec_product WHERE is_demo_item = 0', 'ec_product' ) ),
				'orders_30d'       => self::band( $this->count( 'SELECT COUNT(*) FROM ec_order WHERE order_date >= DATE_SUB( NOW(), INTERVAL 30 DAY )', 'ec_order' ) ),
				'customers'        => self::band( $this->count( 'SELECT COUNT(*) FROM ec_user', 'ec_user' ) ),
				'subscriptions'    => self::band( $this->count( "SELECT COUNT(*) FROM ec_subscription WHERE subscription_status = 'Active'", 'ec_subscription' ) ),
				'declines_7d'      => self::band( $this->count( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_checkout_event WHERE event_type = %s AND created_at >= %s', 'decline', gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS ) ), 'ec_checkout_event' ) ),
				'blocks_7d'        => self::band( $this->count( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_checkout_event WHERE event_type IN ( %s, %s ) AND created_at >= %s', 'stop', 'code_stop', gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS ) ), 'ec_checkout_event' ) ),
				'wizard'           => get_option( 'ec_option_setup_wizard_done' ) ? 1 : 0,
				'store_age'        => $installed > 0 ? self::days_band( time() - $installed ) : 'unknown',
				'first_order_days' => ( $installed > 0 && $first_ts > 0 ) ? self::days_band( max( 0, $first_ts - $installed ) ) : ( $first_ts > 0 ? 'unknown' : 'none' ),
				'milestones'       => implode( ',', $this->reached() ),
			);
			/**
			 * The weekly check-in's fields ( WP EasyCart PRO adds its own ). Keep to keys, flags and bands: scrub() removes
			 * personal fields, and the receiver keeps only the fields it knows.
			 *
			 * @since 6.0.3
			 * @param array $data Fields.
			 */
			$data = apply_filters( 'wp_easycart_tracking_checkin_fields', $data );
			return is_array( $data ) ? $data : array();
		}

		/**
		 * The gateway, shipping, tax and carrier picture access_granted() and checkin() share.
		 *
		 * @return array shipping_type, taxes ( list ), carriers ( list ).
		 */
		private function setup_lists() {
			global $wpdb;
			$settings = $this->table_exists( 'ec_setting' ) ? $wpdb->get_row( 'SELECT * FROM ec_setting' ) : null;
			$taxes    = $this->table_exists( 'ec_taxrate' ) ? $wpdb->get_row( 'SELECT SUM( tax_by_state ) AS state_count, SUM( tax_by_country ) AS country_count, SUM( tax_by_duty ) AS duty_count, SUM( tax_by_vat ) AS vat_count, SUM( tax_by_single_vat ) AS vat_single_count, SUM( tax_by_all ) AS global_count FROM ec_taxrate' ) : null;
			$col      = function ( $row, $name ) {
				return ( is_object( $row ) && isset( $row->{$name} ) ) ? (string) $row->{$name} : '';
			};
			$filled   = function ( $value ) {
				return is_scalar( $value ) && '' !== trim( (string) $value ) && '0' !== (string) $value;
			};
			$tax_list = array();
			foreach ( array(
				'vat'     => array( 'vat_count', 'vat_single_count' ),
				'duty'    => array( 'duty_count' ),
				'state'   => array( 'state_count' ),
				'country' => array( 'country_count' ),
				'global'  => array( 'global_count' ),
			) as $name => $cols ) {
				foreach ( $cols as $c ) {
					if ( (int) $col( $taxes, $c ) > 0 ) {
						$tax_list[] = $name;
						break;
					}
				}
			}
			if ( $filled( get_option( 'ec_option_tax_cloud_api_id' ) ) ) {
				$tax_list[] = 'taxcloud';
			}
			if ( get_option( 'ec_option_enable_easy_canada_tax' ) ) {
				$tax_list[] = 'canada';
			}
			$carriers = array();
			$checks   = array(
				'auspost'    => $filled( $col( $settings, 'auspost_api_key' ) ),
				'canadapost' => $filled( $col( $settings, 'canadapost_username' ) ),
				'dhl'        => $filled( $col( $settings, 'dhl_password' ) ) || $filled( get_option( 'ec_option_dhl_api_key' ) ),
				'fedex'      => $filled( $col( $settings, 'fedex_key' ) ) || $filled( get_option( 'ec_option_fedex_api_key' ) ),
				'ups'        => $filled( $col( $settings, 'ups_password' ) ) || ! empty( get_option( 'ec_option_ups_token_info' ) ),
				'usps'       => $filled( $col( $settings, 'usps_user_name' ) ) || (bool) get_option( 'ec_option_usps_v3_enable' ),
			);
			foreach ( $checks as $name => $on ) {
				if ( $on ) {
					$carriers[] = $name;
				}
			}
			return array(
				'shipping_type' => $col( $settings, 'shipping_method' ),
				'taxes'         => $tax_list,
				'carriers'      => $carriers,
			);
		}

		/**
		 * Active WP EasyCart extensions, by folder ( wp-easycart-shipstation → shipstation ): plugin names, nothing about the store.
		 *
		 * @return array
		 */
		private static function extensions() {
			$out = array();
			foreach ( (array) get_option( 'active_plugins', array() ) as $plugin ) {
				$folder = strtolower( (string) strtok( (string) $plugin, '/' ) );
				if ( 0 === strpos( $folder, 'wp-easycart-' ) && ! in_array( $folder, array( 'wp-easycart-pro', 'wp-easycart-api' ), true ) ) {
					$out[] = sanitize_key( substr( $folder, 12 ) );
				} elseif ( in_array( $folder, array( 'affiliatewp-affiliate-product-rates' ), true ) ) {
					$out[] = 'affiliatewp';
				}
			}
			$out = array_values( array_unique( array_filter( $out ) ) );
			sort( $out );
			return array_slice( $out, 0, 20 );
		}

		/**
		 * Whether the live gateway runs in sandbox / test mode ( the Connect gateways' own switches; others count as live ).
		 *
		 * @param string $method Gateway key.
		 * @return bool
		 */
		private static function live_in_test( $method ) {
			$flags = array(
				'stripe_connect' => 'ec_option_stripe_connect_use_sandbox',
				'square'         => 'ec_option_square_is_sandbox',
				'authorize'      => 'ec_option_authorize_test_mode',
				'braintree'      => 'ec_option_braintree_environment',
				'paytrace'       => 'ec_option_paytrace_sandbox',
			);
			if ( ! isset( $flags[ $method ] ) ) {
				return false;
			}
			$value = (string) get_option( $flags[ $method ] );
			return 'braintree' === $method ? ( 'sandbox' === $value ) : ( '' !== $value && '0' !== $value );
		}

		/**
		 * A count as a band: 0, 1-10, 11-100, 101-1000, 1000+ ( never the number itself ).
		 *
		 * @param int $n Count.
		 * @return string
		 */
		public static function band( $n ) {
			$n = (int) $n;
			if ( $n <= 0 ) {
				return '0';
			}
			if ( $n <= 10 ) {
				return '1-10';
			}
			if ( $n <= 100 ) {
				return '11-100';
			}
			return $n <= 1000 ? '101-1000' : '1000+';
		}

		/**
		 * A length of time as a band of days: 0-1, 2-7, 8-30, 31-90, 91-365, 365+.
		 *
		 * @param int $seconds Seconds.
		 * @return string
		 */
		public static function days_band( $seconds ) {
			$days = (int) floor( max( 0, (int) $seconds ) / DAY_IN_SECONDS );
			if ( $days <= 1 ) {
				return '0-1';
			}
			if ( $days <= 7 ) {
				return '2-7';
			}
			if ( $days <= 30 ) {
				return '8-30';
			}
			if ( $days <= 90 ) {
				return '31-90';
			}
			return $days <= 365 ? '91-365' : '365+';
		}

		/**
		 * A count from one of WP EasyCart's tables, 0 when the table is not there.
		 *
		 * @param string $sql   Query ( prepared already when it has values ).
		 * @param string $table Table it reads.
		 * @return int
		 */
		private function count( $sql, $table ) {
			global $wpdb;
			if ( ! $this->table_exists( $table ) ) {
				return 0;
			}
			$suppress = $wpdb->suppress_errors( true );
			$n        = (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed queries, or prepared by the caller.
			$wpdb->suppress_errors( $suppress );
			return $n;
		}

		/**
		 * Whether one of WP EasyCart's tables exists ( a fresh or half-upgraded install ).
		 *
		 * @param string $table Table.
		 * @return bool
		 */
		private function table_exists( $table ) {
			global $wpdb;
			if ( ! isset( $this->tables[ $table ] ) ) {
				$this->tables[ $table ] = ( $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) );
			}
			return $this->tables[ $table ];
		}

		/**
		 * The getting-started steps this store has reached, read from its data ( so a store that shares later reports them too ).
		 *
		 * @return array
		 */
		private function reached() {
			$steps = array();
			if ( get_option( 'ec_option_setup_wizard_done' ) ) {
				$steps[] = 'wizard_done';
			}
			if ( $this->count( 'SELECT COUNT(*) FROM ec_product WHERE is_demo_item = 0', 'ec_product' ) > 0 ) {
				$steps[] = 'first_product';
			}
			$live  = (string) get_option( 'ec_option_payment_process_method' );
			$third = (string) get_option( 'ec_option_payment_third_party' );
			$card  = '' !== $live && '0' !== $live && ! self::live_in_test( $live );
			$other = '' !== $third && '0' !== $third && ! ( 'paypal' === $third && get_option( 'ec_option_paypal_use_sandbox' ) );
			if ( $card || $other ) {
				$steps[] = 'gateway_live';
			}
			if ( $this->count( 'SELECT COUNT(*) FROM ec_order', 'ec_order' ) > 0 ) {
				$steps[] = 'first_order';
			}
			return $steps;
		}

		/**
		 * One getting-started step, sent once with how long after installing it came ( wizard_done, first_product, gateway_live,
		 * first_order ).
		 *
		 * @param string $step Step.
		 * @return bool Whether it waits to be sent.
		 */
		public function milestone( $step ) {
			if ( ! self::enabled() ) {
				return false;
			}
			$step = sanitize_key( $step );
			$sent = get_option( self::MILESTONES_OPTION, array() );
			$sent = is_array( $sent ) ? $sent : array();
			if ( isset( $sent[ $step ] ) ) {
				return false;
			}
			$sent[ $step ] = 1;
			update_option( self::MILESTONES_OPTION, $sent, false );
			$installed = (int) get_option( self::INSTALLED_OPTION, 0 );
			return $this->send_tracking(
				'milestone',
				array(
					'step' => $step,
					'days' => $installed > 0 ? self::days_band( time() - $installed ) : 'unknown',
				)
			);
		}

		/** The setup wizard was finished ( or skipped with products already there ). */
		public function wizard_done() {
			if ( get_option( 'ec_option_setup_wizard_done' ) ) {
				$this->milestone( 'wizard_done' );
			}
		}

		/**
		 * WP EasyCart was updated ( wpeasycart_update_check() ): the versions and a fresh check-in.
		 *
		 * @param string $from Version before ( EC_CURRENT_VERSION form, 6_0_2 ).
		 * @param string $to   Version now.
		 */
		public function updated( $from, $to ) {
			if ( ! self::enabled() ) {
				return;
			}
			$this->send_tracking(
				'updated',
				array(
					'from_version' => str_replace( '_', '.', (string) $from ),
					'to_version'   => str_replace( '_', '.', (string) $to ),
				)
			);
			$this->checkin();
			self::schedule_checkin();
		}

		/**
		 * AJAX ecv2_tracking_upsell ( upsell.js, only on stores that share ): a locked feature's preview was opened, or one of
		 * its buttons used. context, feature and action ( open | pro | premium | trial | update ) are keys from the upsell catalog.
		 */
		public function ajax_upsell() {
			ecv2_tracking_guard();
			$context = isset( $_POST['context'] ) ? substr( sanitize_key( wp_unslash( $_POST['context'] ) ), 0, 40 ) : '';
			$feature = isset( $_POST['feature'] ) ? substr( sanitize_key( wp_unslash( $_POST['feature'] ) ), 0, 40 ) : '';
			$action  = isset( $_POST['what'] ) ? sanitize_key( wp_unslash( $_POST['what'] ) ) : 'open';
			if ( ! in_array( $action, array( 'open', 'pro', 'premium', 'trial', 'update' ), true ) ) {
				$action = 'open';
			}
			if ( '' !== $context ) {
				$this->send_tracking(
					'upsell_open',
					array(
						'context' => $context,
						'feature' => $feature,
						'action'  => $action,
					)
				);
			}
			wp_send_json_success();
		}

		/**
		 * A setting on the short list changed ( wp_easycart_settings_saved ): its key and the chosen value, when that value is a
		 * plain key ( on / off, a mode ). Text is never sent.
		 *
		 * @param string $key   Option.
		 * @param mixed  $value Value saved.
		 * @param mixed  $old   Value before.
		 * @param string $page  Settings page.
		 */
		public function setting_changed( $key, $value, $old = null, $page = '' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- the hook passes it.
			/**
			 * The settings whose changes are counted ( option => short name ).
			 *
			 * @since 6.0.3
			 * @param array $settings Settings.
			 */
			$list = apply_filters(
				'wp_easycart_tracking_settings',
				array(
					'ec_option_onepage_checkout'          => 'onepage',
					'ec_option_checkout_protection_level' => 'protection',
					'ec_option_tax_provider'              => 'tax_service',
					'ec_option_marketing_consent'         => 'consent',
					'ec_option_allow_guest'               => 'guest_checkout',
					'ec_option_order_pay_links'           => 'pay_links',
					'ec_option_store_activity'            => 'store_activity',
					'ec_option_enable_recaptcha'          => 'recaptcha',
					'ec_option_cancel_restock'            => 'cancel_restock',
					'ec_option_show_payment_pending_notice' => 'pending_notice',
				)
			);
			if ( ! is_array( $list ) || ! isset( $list[ $key ] ) || ! is_scalar( $value ) || ( is_scalar( $old ) ? (string) $old : null ) === (string) $value ) {
				return;
			}
			$plain = strtolower( (string) $value );
			if ( ! preg_match( '/^[a-z0-9_-]{1,32}$/', $plain ) ) {
				return;
			}
			$this->send_tracking(
				'setting_changed',
				array(
					'setting' => sanitize_key( $list[ $key ] ),
					'value'   => $plain,
				)
			);
		}

		/** The weekly check-in's WP-Cron event, while the store shares. */
		public static function schedule_checkin() {
			if ( self::enabled() && ! wp_next_scheduled( self::CHECKIN_HOOK ) ) {
				wp_schedule_event( time() + DAY_IN_SECONDS, 'weekly', self::CHECKIN_HOOK );
			}
		}

		/**
		 * Sharing was turned off: one last request asks the receiver to delete what it kept for this install ( only when an
		 * install id was ever made; it does not wait for the answer ).
		 *
		 * @return bool Whether a request went out.
		 */
		public static function send_forget() {
			$id = (string) get_option( self::ID_OPTION );
			if ( ! preg_match( '/^[0-9a-f]{32}$/', $id ) ) {
				return false;
			}
			$self   = self::instance();
			$item   = $self->event( 'forget', array(), true );
			$result = $item ? $self->post(
				array( $item ),
				array(
					'blocking' => false,
					'timeout'  => self::DEACTIVATION_TIMEOUT,
				)
			) : 'off';
			return 'off' !== $result;
		}

		/**
		 * WP EasyCart is being deleted ( ec_uninstall() ): a store that shares says so, without waiting for the answer.
		 *
		 * @return bool
		 */
		public function deleted() {
			return $this->send_now( 'deleted' );
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

if ( ! function_exists( 'ecv2_tracking_guard' ) ) {
	/**
	 * AJAX guard for the usage data beacons ( 6.0.3, upsell.js ): the nonce and an EasyCart admin.
	 */
	function ecv2_tracking_guard() {
		if ( ! check_ajax_referer( 'wp-easycart-ecv2-tracking', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'nonce' ), 403 );
		}
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_manager' ) ) {
			wp_send_json_error( array( 'message' => 'cap' ), 403 );
		}
	}
}
