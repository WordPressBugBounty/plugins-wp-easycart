<?php
/**
 * WP EasyCart Admin — Settings › Checkout protection ( 6.0.2 ).
 *
 * The declaration is admin/template/settings/checkout-protection.php; the engine is
 * inc/classes/core/class-wp-easycart-checkout-guard.php. This file draws the parts a declaration can't: the status
 * card, the level explanation ( built from the real limits, so the words always match what the code does ), the key
 * status and key test, the paused-now list, payment processor tips, the activity list, and the admin banner shown on
 * every EasyCart screen while an attack is on. AJAX: ecv2_protection_* ( plain guard ecv2_protection_guard() ); the
 * banner's buttons are admin-post links ( wpec_protection ).
 *
 * @package wp-easycart
 * @since   6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'ecv2_protection_guard' ) ) {
	/**
	 * Nonce and capability check for the Checkout protection AJAX calls ( a plain function so WPCS credits it ).
	 *
	 * @since 6.0.2
	 */
	function ecv2_protection_guard() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wp-easycart-ecv2-protection' ) ) {
			wp_send_json_error( array( 'message' => __( 'This page has expired. Reload it and try again.', 'wp-easycart' ) ) );
		}
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_settings' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- EasyCart's own roles register this capability.
			wp_send_json_error( array( 'message' => __( 'You do not have permission to change checkout protection.', 'wp-easycart' ) ) );
		}
	}
}

if ( ! class_exists( 'wp_easycart_admin_checkout_protection' ) ) :

	/**
	 * Settings › Checkout protection: status, lists, activity and the attack banner.
	 *
	 * @since 6.0.2
	 */
	class wp_easycart_admin_checkout_protection {

		/** Nonce for the AJAX calls and the banner links. */
		const NONCE = 'wp-easycart-ecv2-protection';

		/** When the current Turnstile or reCAPTCHA keys last passed a test ( option ). */
		const KEYS_OK_OPTION = 'wp_easycart_checkout_protection_keys_ok';

		/** First-run notice dismissed ( option ). */
		const NOTICE_OPTION = 'wp_easycart_checkout_protection_notice';

		/**
		 * Register hooks.
		 */
		public static function init() {
			foreach ( array( 'activity', 'unpause', 'block', 'extra', 'test_token', 'keys' ) as $action ) {
				add_action( 'wp_ajax_ecv2_protection_' . $action, array( __CLASS__, 'ajax_' . $action ) );
			}
			add_action( 'admin_post_wpec_protection', array( __CLASS__, 'admin_post' ) );
			/* 6.0.2: inside the EasyCart shell ( its notice card ), not above it as a WordPress notice. */
			add_action( 'wp_easycart_admin_messages', array( __CLASS__, 'banner' ), 8 );
		}

		/**
		 * The settings page, optionally at a section.
		 *
		 * @param string $section Section key.
		 * @return string
		 */
		public static function url( $section = '' ) {
			return admin_url( 'admin.php?page=wp-easycart-settings&subpage=checkout-protection' ) . ( '' !== $section ? '#ecst-sec-' . $section : '' );
		}

		/**
		 * Whether the engine is loaded.
		 *
		 * @return bool
		 */
		private static function engine() {
			return class_exists( 'wp_easycart_checkout_guard' );
		}

		/**
		 * Page assets ( declared as the page's 'enqueue', so they print in the head ).
		 */
		public static function enqueue() {
			wp_enqueue_style( 'wp_easycart_admin_checkout_protection', plugins_url( 'wp-easycart/admin/css/checkout-protection-v2.css', EC_PLUGIN_DIRECTORY ), array(), EC_CURRENT_VERSION );
			wp_enqueue_script( 'wp_easycart_admin_checkout_protection', plugins_url( 'wp-easycart/admin/js/checkout-protection-v2.js', EC_PLUGIN_DIRECTORY ), array( 'jquery' ), EC_CURRENT_VERSION, true );
			$levels = array();
			foreach ( array( 'off', 'watch', 'relaxed', 'standard', 'strict', 'custom' ) as $level ) {
				$levels[ $level ] = self::level_explain_html( $level );
			}
			$site_key = self::engine() ? wp_easycart_checkout_guard::site_key() : '';
			wp_localize_script(
				'wp_easycart_admin_checkout_protection',
				'ecv2_protection',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( self::NONCE ),
					'levels'   => $levels,
					'provider' => self::engine() ? wp_easycart_checkout_guard::settings()['provider'] : 'turnstile',
					'site_key' => $site_key,
					'text'     => array(
						'error'     => __( 'Something went wrong. Reload the page and try again.', 'wp-easycart' ),
						'unpaused'  => __( 'Unpaused', 'wp-easycart' ),
						'blocked'   => __( 'Added to the block list', 'wp-easycart' ),
						'block_ask'   => __( 'Block this shopper? They will only ever see the declined message.', 'wp-easycart' ),
						'block_title' => __( 'Block shopper', 'wp-easycart' ), /* 6.0.2: the V2 confirm dialog's title */
						'loading'   => __( 'Loading…', 'wp-easycart' ),
						'no_key'    => __( 'Save a site key and secret key first, then test them.', 'wp-easycart' ),
						'testing'   => __( 'Checking with the provider…', 'wp-easycart' ),
						'test_ok'   => __( 'The keys work. Your shoppers will be able to pass the check.', 'wp-easycart' ),
						'added_ip'  => __( 'Added. Save to keep it.', 'wp-easycart' ),
					),
				)
			);
		}

		// ------------------------------------------------------------------
		// Status card.
		// ------------------------------------------------------------------

		/**
		 * Store Status rows ( Payment Status section ): is checkout protection on, and is it working?
		 *
		 * @since 6.0.2
		 */
		public static function store_status_rows() {
			if ( ! self::engine() ) {
				return;
			}
			$settings = wp_easycart_checkout_guard::settings();
			$link     = ' <a href="' . esc_url( admin_url( 'admin.php?page=wp-easycart-settings&subpage=checkout-protection' ) ) . '">' . esc_html__( 'Open Checkout protection', 'wp-easycart' ) . '</a>';
			$labels   = array(
				'relaxed'  => __( 'Relaxed level', 'wp-easycart' ),
				'standard' => __( 'Standard level', 'wp-easycart' ),
				'strict'   => __( 'Strict level', 'wp-easycart' ),
				'custom'   => __( 'Custom level', 'wp-easycart' ),
			);
			if ( ! wp_easycart_checkout_guard::ready() ) {
				self::status_row( false, esc_html__( 'Checkout protection is waiting for its database table, so nothing is being checked yet. Repair the database with the link at the top of this page.', 'wp-easycart' ) );
				return;
			}
			if ( 'off' === $settings['level'] ) {
				self::status_row( false, esc_html__( 'Checkout protection is off. Nothing stops bots from testing stolen cards at your checkout, and each declined test can cost you a card fee.', 'wp-easycart' ) . $link );
				return;
			}
			if ( 'watch' === $settings['level'] ) {
				self::status_row( false, esc_html__( 'Checkout protection is only watching: it records card testing but does not stop it.', 'wp-easycart' ) . $link );
				return;
			}
			if ( wp_easycart_checkout_guard::attack_active() ) {
				$attack = wp_easycart_checkout_guard::attack();
				/* translators: %s: time the extra checks end. */
				self::status_row( false, esc_html( sprintf( __( 'Bots are testing cards at your checkout right now. Extra checks are on until %s; review small orders placed during the attack.', 'wp-easycart' ), wp_easycart_checkout_guard::local_time( (int) $attack['until'] ) ) ) . $link );
			} else {
				$level = isset( $labels[ $settings['level'] ] ) ? $labels[ $settings['level'] ] : '';
				if ( 'never' === $settings['human'] || '' === wp_easycart_checkout_guard::site_key() ) {
					/* translators: %s: protection level. */
					$text = sprintf( __( 'Checkout protection is on ( %s ), without a human check: during an attack, payments that didn\'t come from your checkout page are refused instead of checked.', 'wp-easycart' ), $level );
				} else {
					/* translators: %s: protection level. */
					$text = sprintf( __( 'Checkout protection is on ( %s ), with a human check for shoppers who look like bots.', 'wp-easycart' ), $level );
				}
				self::status_row( true, esc_html( $text ) );
			}
			if ( 'never' !== $settings['human'] && '' !== wp_easycart_checkout_guard::site_key() ) {
				$problem = wp_easycart_checkout_guard::provider_problem();
				if ( '' !== $problem ) {
					/* translators: %s: the provider's error. */
					self::status_row( false, esc_html( sprintf( __( 'The human check isn\'t working ( %s ). Payments carry on under stricter limits; check the secret key.', 'wp-easycart' ), $problem ) ) . $link );
				}
			}
		}

		/**
		 * One Store Status row, in that screen's markup ( its summary counts these class names ).
		 *
		 * @param bool   $ok   Passed.
		 * @param string $html Escaped label HTML.
		 */
		private static function status_row( $ok, $html ) {
			echo '<div class="' . ( $ok ? 'ec_status_success' : 'ec_status_error' ) . '"><div class="dashicons-before ' . ( $ok ? 'dashicons-yes' : 'dashicons-no' ) . '"></div><span class="ec_status_label">' . $html . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- callers pass escaped text and links built with esc_url() / esc_html__().
		}

		/**
		 * The shield icon.
		 *
		 * @param string $mark check | alert | dash.
		 * @return string SVG.
		 */
		private static function shield_svg( $mark ) {
			$paths = array(
				'check' => 'M8.5 12l2.5 2.5 4.5-5',
				'alert' => 'M12 8v5M12 16.5v.5',
				'dash'  => 'M8.5 12h7',
			);
			return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="' . esc_attr( $paths[ $mark ] ) . '"/></svg>';
		}

		/**
		 * Section Status: state, numbers, the 14-day chart, buttons and the explainer.
		 */
		public static function render_status() {
			if ( ! self::engine() ) {
				return;
			}
			$settings = wp_easycart_checkout_guard::settings();
			if ( ! wp_easycart_checkout_guard::ready() ) {
				echo '<div class="ecpt-note is-bad">' . esc_html__( 'The database update for checkout protection has not run yet, so nothing is being checked. Load any EasyCart admin page to run it; if this stays, open Store Status.', 'wp-easycart' ) . '</div>';
				return;
			}
			if ( ! get_option( self::NOTICE_OPTION ) ) {
				update_option( self::NOTICE_OPTION, time(), false ); /* the owner has seen the page: the "it's on" notice has done its job */
			}
			$attack  = wp_easycart_checkout_guard::attack();
			$active  = wp_easycart_checkout_guard::attack_active();
			$numbers = wp_easycart_checkout_guard::status_numbers();
			$labels  = array(
				'relaxed'  => __( 'Relaxed level', 'wp-easycart' ),
				'standard' => __( 'Standard level', 'wp-easycart' ),
				'strict'   => __( 'Strict level', 'wp-easycart' ),
				'custom'   => __( 'Custom level', 'wp-easycart' ),
			);
			if ( 'off' === $settings['level'] ) {
				$state = array( 'off', 'dash', __( 'Not protected', 'wp-easycart' ), __( 'Protection is off', 'wp-easycart' ) );
			} elseif ( 'watch' === $settings['level'] ) {
				$state = array( 'warn', 'dash', __( 'Watching only', 'wp-easycart' ), __( 'Nothing is being stopped', 'wp-easycart' ) );
			} elseif ( $active ) {
				/* translators: %s: time extra checks end. */
				$state = array( 'bad', 'alert', __( 'Under attack', 'wp-easycart' ), sprintf( __( 'Extra checks until %s', 'wp-easycart' ), wp_easycart_checkout_guard::local_time( (int) $attack['until'] ) ) );
			} else {
				$state = array( 'ok', 'check', __( 'Protected', 'wp-easycart' ), isset( $labels[ $settings['level'] ] ) ? $labels[ $settings['level'] ] : '' );
			}
			echo '<div class="ecpt-status">';
			echo '<div class="ecpt-shield is-' . esc_attr( $state[0] ) . '">' . self::shield_svg( $state[1] ) . '<b>' . esc_html( $state[2] ) . '</b><small>' . esc_html( $state[3] ) . '</small></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the SVG is built from constants above.
			echo '<div class="ecpt-kpis">';
			self::kpi( (string) (int) $numbers['stopped'], __( 'payment attempts stopped', 'wp-easycart' ) );
			self::kpi( (string) (int) $numbers['declined'], __( 'payments declined by the bank', 'wp-easycart' ) );
			$attack_text = __( 'attacks stopped', 'wp-easycart' );
			if ( ! empty( $attack['ended'] ) ) {
				/* translators: %s: when the last attack ended. */
				$attack_text = sprintf( __( 'attacks stopped · last ended %s', 'wp-easycart' ), wp_easycart_checkout_guard::local_time( (int) $attack['ended'] ) );
			}
			if ( $active ) {
				$attack_text = __( 'attacks · one in progress now', 'wp-easycart' );
			}
			self::kpi( (string) (int) $numbers['attacks'], $attack_text );
			self::kpi( (string) (int) $numbers['caught'], __( 'paused shoppers who later paid ( real customers caught )', 'wp-easycart' ), 0 === (int) $numbers['caught'] ? 'is-ok' : 'is-warn' );
			echo '</div>';
			self::spark( $numbers['series'] );
			echo '</div>';

			echo '<div class="ecpt-actions">';
			if ( $active ) {
				echo '<button type="button" class="ecv2-btn ecv2-btn-sm" data-ecpt-extra="off">' . esc_html__( 'End extra checks now', 'wp-easycart' ) . '</button>';
				echo '<button type="button" class="ecv2-btn ecv2-btn-sm" data-ecpt-extra="hold">' . esc_html__( 'Keep extra checks on for 24 hours', 'wp-easycart' ) . '</button>';
			} elseif ( ! in_array( $settings['level'], array( 'off', 'watch' ), true ) ) {
				echo '<button type="button" class="ecv2-btn ecv2-btn-sm" data-ecpt-extra="on">' . esc_html__( 'Turn on extra checks now', 'wp-easycart' ) . '</button>';
			}
			echo '<a class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" href="#ecst-sec-activity">' . esc_html__( 'See activity', 'wp-easycart' ) . '</a>';
			if ( wp_easycart_checkout_guard::has_flagged_orders() ) {
				echo '<a class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" href="' . esc_url( admin_url( 'admin.php?page=wp-easycart-orders&subpage=orders&health_filter=card_tests' ) ) . '">' . esc_html__( 'Tagged orders to review', 'wp-easycart' ) . '</a>';
			}
			echo '</div>';

			echo '<div class="ecpt-3col">';
			echo '<div><b>' . esc_html__( 'Why this matters', 'wp-easycart' ) . '</b>' . esc_html__( 'Bots use checkouts to find out which stolen cards work. Every attempt costs you a gateway fee, and test charges that go through turn into disputes that can get your payouts held.', 'wp-easycart' ) . '</div>';
			echo '<div><b>' . esc_html__( 'How it works', 'wp-easycart' ) . '</b>' . esc_html__( 'We count failed payments from each device, network address, email and card. Too many in a short time pauses that source. When declines spike across your store, everyone gets a quick human check until it calms down.', 'wp-easycart' ) . '</div>';
			echo '<div><b>' . esc_html__( 'What if a real customer is caught?', 'wp-easycart' ) . '</b>' . esc_html__( 'They see a friendly message and can pay again after a short pause, or straight away after a human check. You can unpause anyone below, and the last number above tells you if it has happened.', 'wp-easycart' ) . '</div>';
			echo '</div>';
		}

		/**
		 * One number tile.
		 *
		 * @param string $value Number.
		 * @param string $label Label.
		 * @param string $modifier Extra class.
		 */
		private static function kpi( $value, $label, $modifier = '' ) {
			echo '<div class="ecpt-kpi ' . esc_attr( $modifier ) . '"><b>' . esc_html( $value ) . '</b><span>' . esc_html( $label ) . '</span></div>';
		}

		/**
		 * The 14-day bar chart: stopped ( dark ) on top of declined ( amber ).
		 *
		 * @param array $series day => array( stopped, declined ).
		 */
		private static function spark( $series ) {
			$max = 1;
			foreach ( (array) $series as $pair ) {
				$max = max( $max, (int) $pair[0] + (int) $pair[1] );
			}
			echo '<div class="ecpt-spark-wrap">';
			echo '<div class="ecpt-spark-label"><span><i class="is-stop"></i>' . esc_html__( 'Stopped', 'wp-easycart' ) . ' <i class="is-dec"></i>' . esc_html__( 'Declined by the bank', 'wp-easycart' ) . '</span><span>' . esc_html__( 'Last 14 days', 'wp-easycart' ) . '</span></div>';
			echo '<div class="ecpt-spark" role="img" aria-label="' . esc_attr__( 'Stopped and declined payment attempts per day for the last 14 days', 'wp-easycart' ) . '">';
			foreach ( (array) $series as $day => $pair ) {
				$stop = (int) $pair[0];
				$dec  = (int) $pair[1];
				/* translators: 1: date, 2: stopped, 3: declined. */
				$title = sprintf( __( '%1$s: %2$d stopped, %3$d declined', 'wp-easycart' ), $day, $stop, $dec );
				echo '<div title="' . esc_attr( $title ) . '"><span class="is-dec" style="height:' . esc_attr( round( $dec / $max * 100, 2 ) ) . '%"></span><span class="is-stop" style="height:' . esc_attr( round( $stop / $max * 100, 2 ) ) . '%"></span></div>';
			}
			echo '</div></div>';
		}

		// ------------------------------------------------------------------
		// Explanations.
		// ------------------------------------------------------------------

		/**
		 * What a level does, in plain words, from its real limits.
		 *
		 * @param string $level Level.
		 * @return string HTML.
		 */
		public static function level_explain_html( $level ) {
			if ( ! self::engine() ) {
				return '';
			}
			if ( 'off' === $level ) {
				return '<div class="ecpt-note is-bad"><b>' . esc_html__( 'Off.', 'wp-easycart' ) . '</b> ' . esc_html__( 'Nothing limits payment attempts. A single bot can run thousands of stolen cards through your checkout, and your payment processor may hold your payouts because of it. We recommend Standard.', 'wp-easycart' ) . '</div>';
			}
			if ( 'watch' === $level ) {
				return '<div class="ecpt-note is-warn"><b>' . esc_html__( 'Watch only.', 'wp-easycart' ) . '</b> ' . esc_html__( 'Nothing is stopped. Everything Standard would have stopped appears in Activity as "Would stop", so you can see what it would do on your store before switching it on.', 'wp-easycart' ) . '</div>';
			}
			$limits  = wp_easycart_checkout_guard::limits( $level );
			$minutes = (int) round( $limits['window'] / 60 );
			$pause   = (int) round( $limits['pause'] / 60 );
			$pause_t = ( $pause >= 60 && 0 === $pause % 60 ) ? sprintf( /* translators: %d: hours. */ _n( '%d hour', '%d hours', (int) ( $pause / 60 ), 'wp-easycart' ), (int) ( $pause / 60 ) ) : sprintf( /* translators: %d: minutes. */ _n( '%d minute', '%d minutes', $pause, 'wp-easycart' ), $pause );
			$titles  = array(
				'relaxed'  => __( 'Relaxed.', 'wp-easycart' ) . ' ' . __( 'For stores where many shoppers share one network, like a school or an office.', 'wp-easycart' ),
				'standard' => __( 'Standard.', 'wp-easycart' ) . ' ' . __( 'Real shoppers almost never meet it.', 'wp-easycart' ),
				'strict'   => __( 'Strict.', 'wp-easycart' ) . ' ' . __( 'For stores that have been attacked before, or sell cheap items bots like.', 'wp-easycart' ),
				'custom'   => __( 'Custom.', 'wp-easycart' ) . ' ' . __( 'Your own numbers, set below.', 'wp-easycart' ),
			);
			$items   = array(
				/* translators: 1: failed payments, 2: minutes, 3: failed payments a day, 4: pause length. */
				sprintf( __( 'A shopper can fail %1$d payments in %2$d minutes ( %3$d in a day ) before a %4$s pause. Most people get it right within two tries.', 'wp-easycart' ), $limits['fails'], $minutes, $limits['daily'], $pause_t ),
				/* translators: %d: failed payments. */
				sprintf( __( 'A card that fails %d times in a day is refused until the next day. Stripe and Square cards are only known after a decline, so there the shopper limits above do this job.', 'wp-easycart' ), $limits['card'] ),
				/* translators: 1: times the usual rate, 2: declines. */
				sprintf( __( 'When declines across your store are %1$s times higher than usual ( at least %2$d in 15 minutes ), everyone gets a human check and each shopper gets the tries set under During an attack.', 'wp-easycart' ), rtrim( rtrim( number_format( $limits['attack_ratio'], 1, '.', '' ), '0' ), '.' ), $limits['attack_count'] ),
			);
			if ( $limits['strict_page'] ) {
				$items[] = __( 'A payment from a session that never opened your checkout page gets a human check.', 'wp-easycart' );
			}
			$html = '<div class="ecpt-note is-ok"><b>' . esc_html( $titles[ isset( $titles[ $level ] ) ? $level : 'standard' ] ) . '</b><ul>';
			foreach ( $items as $item ) {
				$html .= '<li>' . esc_html( $item ) . '</li>';
			}
			return $html . '</ul></div>';
		}

		/**
		 * Section Protection level: the live explanation under the pills ( redrawn by the page script on change ).
		 */
		public static function render_level_explain() {
			if ( ! self::engine() ) {
				return;
			}
			echo '<div id="ecpt-level-explain">' . wp_kses_post( self::level_explain_html( wp_easycart_checkout_guard::settings()['level'] ) ) . '</div>';
		}

		/**
		 * Section Human check: why, how, what if, and the key test.
		 */
		public static function render_human_explain() {
			echo '<div class="ecpt-3col">';
			echo '<div><b>' . esc_html__( 'Why a check helps', 'wp-easycart' ) . '</b>' . esc_html__( 'Limits slow a bot down. A human check stops it, because every attempt needs a pass that bots can\'t produce in bulk.', 'wp-easycart' ) . '</div>';
			echo '<div><b>' . esc_html__( 'How shoppers see it', 'wp-easycart' ) . '</b>' . esc_html__( 'Most people see a small box that ticks itself. A few are asked to click a checkbox. It shows only above Place order, only when it\'s needed.', 'wp-easycart' ) . '</div>';
			echo '<div><b>' . esc_html__( 'What if the check service is down?', 'wp-easycart' ) . '</b>' . esc_html__( 'Payments are never blocked because a check provider is unavailable. They carry on under stricter limits ( half the failed payments before a pause, and a pause of at least an hour ), and this page tells you.', 'wp-easycart' ) . '</div>';
			echo '</div>';
			if ( self::engine() ) {
				/* 6.0.2: printed without a key too ( hidden ), so a key saved on this page can be tested without a reload. */
				echo '<div class="ecpt-test"' . ( '' === wp_easycart_checkout_guard::site_key() ? ' hidden' : '' ) . '><div><b>' . esc_html__( 'Test your keys', 'wp-easycart' ) . '</b><span>' . esc_html__( 'Shows the check here, exactly as shoppers get it, and confirms your secret key with the provider.', 'wp-easycart' ) . '</span></div><button type="button" class="ecv2-btn ecv2-btn-sm" id="ecpt-test-keys">' . esc_html__( 'Show a test check', 'wp-easycart' ) . '</button><div id="ecpt-test-widget"></div><div id="ecpt-test-result" class="ecpt-test-result" aria-live="polite"></div></div>';
			}
		}

		/**
		 * Row: whether the keys are set, tested and working.
		 */
		public static function render_keys_status() {
			/* 6.0.2: in a holder the page script redraws after a key or provider save ( ajax_keys() ). */
			echo '<div id="ecpt-keys-status">' . self::keys_status_html() . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts in keys_status_html().
		}

		/**
		 * The key status note.
		 *
		 * @since 6.0.2
		 * @return string
		 */
		private static function keys_status_html() {
			ob_start();
			self::print_keys_status();
			return (string) ob_get_clean();
		}

		/**
		 * Print the key status note ( was render_keys_status() before 6.0.2 ).
		 */
		private static function print_keys_status() {
			if ( ! self::engine() ) {
				return;
			}
			$settings = wp_easycart_checkout_guard::settings();
			if ( 'never' === $settings['human'] ) {
				return;
			}
			$problem = wp_easycart_checkout_guard::provider_problem();
			$site    = wp_easycart_checkout_guard::site_key();
			$tested  = (array) get_option( self::KEYS_OK_OPTION, array() );
			if ( 'recaptcha' === $settings['provider'] ) {
				$accounts = admin_url( 'admin.php?page=wp-easycart-settings&subpage=account' );
				if ( '' === $site ) {
					echo '<div class="ecpt-note is-warn">' . esc_html__( 'No reCAPTCHA keys yet. Add your reCAPTCHA v2 ( checkbox ) keys on Settings › Accounts.', 'wp-easycart' ) . ' <a href="' . esc_url( $accounts ) . '">' . esc_html__( 'Open Accounts', 'wp-easycart' ) . '</a></div>';
					return;
				}
			} elseif ( '' === $site ) {
				echo '<div class="ecpt-note is-warn"><b>' . esc_html__( 'No Turnstile keys yet.', 'wp-easycart' ) . '</b> ' . esc_html__( 'Protection still works without them: during an attack, payments that didn\'t come from your checkout page are refused instead of checked. Keys make it smoother for real shoppers. They are free:', 'wp-easycart' ) . ' <a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Open Cloudflare Turnstile', 'wp-easycart' ) . ' ↗</a></div>';
				return;
			}
			if ( '' !== $problem ) {
				/* translators: %s: the provider's error. */
				echo '<div class="ecpt-note is-bad">' . esc_html( sprintf( __( 'The check provider isn\'t working right now ( %s ). Payments carry on under stricter limits until it answers again. If this doesn\'t clear in a few minutes, check the secret key.', 'wp-easycart' ), $problem ) ) . '</div>';
				return;
			}
			if ( isset( $tested['site'] ) && $tested['site'] === $site && ! empty( $tested['time'] ) ) {
				/* translators: %s: when the keys were tested. */
				echo '<div class="ecpt-note is-ok">' . esc_html( sprintf( __( 'Keys work · tested %s', 'wp-easycart' ), wp_easycart_checkout_guard::local_time( (int) $tested['time'] ) ) ) . '</div>';
				return;
			}
			echo '<div class="ecpt-note">' . esc_html__( 'Keys saved but not tested yet. Use "Show a test check" below.', 'wp-easycart' ) . '</div>';
		}

		/**
		 * Row: where to change the shopper wording.
		 */
		public static function render_wording() {
			$url = admin_url( 'admin.php?page=wp-easycart-settings&subpage=language-editor' );
			echo '<div class="ecpt-inline"><span>' . esc_html__( 'Change the declined, paused and human-check messages in the Language editor ( section Checkout Protection ), in every language your store uses.', 'wp-easycart' ) . '</span> <a class="ecv2-btn ecv2-btn-sm" href="' . esc_url( $url ) . '">' . esc_html__( 'Edit wording', 'wp-easycart' ) . '</a></div>';
		}

		/**
		 * Row: the address this site sees for you, with how it was worked out.
		 */
		public static function render_detected_ip() {
			if ( ! self::engine() ) {
				return;
			}
			$ip     = wp_easycart_checkout_guard::client_ip();
			$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
			$cf     = isset( $_SERVER['HTTP_CF_CONNECTING_IP'] );
			if ( '' === $ip ) {
				echo '<div class="ecpt-note is-warn">' . esc_html__( 'Your network address can\'t be read on this server, so limits by address are skipped and the other limits still apply.', 'wp-easycart' ) . '</div>';
				return;
			}
			if ( $ip !== $remote && $cf ) {
				/* translators: %s: address. */
				$text = sprintf( __( 'Detected: through Cloudflare. Your address shows as %s.', 'wp-easycart' ), $ip );
			} elseif ( $ip !== $remote ) {
				/* translators: %s: address. */
				$text = sprintf( __( 'Detected: through a proxy. Your address shows as %s.', 'wp-easycart' ), $ip );
			} elseif ( ! wp_easycart_checkout_guard::is_public_ip( $ip ) ) {
				/* translators: %s: address. */
				$text = sprintf( __( 'Your address shows as %s, a private address. If every visitor shows the same address, your host uses a proxy: choose Another proxy and list its address.', 'wp-easycart' ), $ip );
			} else {
				/* translators: %s: address. */
				$text = sprintf( __( 'Detected: a direct connection. Your address shows as %s.', 'wp-easycart' ), $ip );
			}
			echo '<div class="ecpt-note is-ok">' . esc_html( $text ) . '</div>';
		}

		// ------------------------------------------------------------------
		// Lists, tips and activity.
		// ------------------------------------------------------------------

		/**
		 * Section Trusted & blocked: your address helper and who is paused now.
		 */
		public static function render_paused() {
			if ( ! self::engine() ) {
				return;
			}
			$ip = wp_easycart_checkout_guard::client_ip();
			if ( '' !== $ip ) {
				/* translators: %s: address. */
				echo '<div class="ecpt-inline ecpt-myip"><span>' . esc_html( sprintf( __( 'Your address right now: %s', 'wp-easycart' ), $ip ) ) . '</span> <button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" data-ecpt-add-ip="' . esc_attr( $ip ) . '">' . esc_html__( 'Add to never limit', 'wp-easycart' ) . '</button></div>';
			}
			$paused = wp_easycart_checkout_guard::paused_now();
			echo '<div class="ecpt-sub"><b>' . esc_html__( 'Paused right now', 'wp-easycart' ) . '</b> <span class="ecv2-chip">' . (int) count( $paused ) . '</span><span class="ecpt-hint">' . esc_html__( 'These sources hit a limit. Pauses end by themselves; unpause someone who contacts you.', 'wp-easycart' ) . '</span></div>';
			if ( ! $paused ) {
				echo '<p class="ecpt-empty">' . esc_html__( 'Nobody is paused.', 'wp-easycart' ) . '</p>';
				return;
			}
			$kinds = array(
				'session_key' => __( 'Browser session', 'wp-easycart' ),
				'ip_key'      => __( 'Network address', 'wp-easycart' ),
				'email_key'   => __( 'Email', 'wp-easycart' ),
				'card_key'    => __( 'Card', 'wp-easycart' ),
			);
			echo '<div class="ecpt-tablewrap"><table class="ecpt-table"><thead><tr><th>' . esc_html__( 'Paused', 'wp-easycart' ) . '</th><th>' . esc_html__( 'Kind', 'wp-easycart' ) . '</th><th>' . esc_html__( 'Until', 'wp-easycart' ) . '</th><th></th></tr></thead><tbody>';
			foreach ( $paused as $row ) {
				echo '<tr><td class="ecpt-mono">' . esc_html( $row['display'] ) . '</td><td>' . esc_html( $kinds[ $row['column'] ] ) . '</td><td>' . esc_html( wp_easycart_checkout_guard::local_time( (int) $row['until'] ) ) . '</td><td class="ecpt-right"><button type="button" class="ecv2-btn ecv2-btn-sm" data-ecpt-unpause="' . esc_attr( $row['column'] . ':' . $row['key'] ) . '">' . esc_html__( 'Unpause', 'wp-easycart' ) . '</button></td></tr>';
			}
			echo '</tbody></table></div>';
		}

		/**
		 * Section Your payment processor: tips for the gateways in use, and cheap targets in the catalog.
		 */
		public static function render_gateway_tips() {
			$method = (string) get_option( 'ec_option_payment_process_method' );
			$rows   = array();
			if ( in_array( $method, array( 'stripe', 'stripe_connect' ), true ) ) {
				$rows[] = array( 'Stripe', __( 'Radar screens every payment automatically, and Stripe adds its own check during attacks. In your Stripe dashboard, turn on the rule "Block if CVC verification fails": it\'s free and stops most tests that guess a card\'s security code. Make sure the EasyCart webhook is set up, so declines in the payment form reach this page.', 'wp-easycart' ), 'https://dashboard.stripe.com/radar/rules' );
			}
			if ( 'square' === $method ) {
				$rows[] = array( 'Square', __( 'Square screens online payments automatically. In your Square Dashboard, Risk Manager can add a free rule to decline a card used more than 5 times in 24 hours.', 'wp-easycart' ), 'https://squareup.com/dashboard/' );
			}
			if ( get_option( 'ec_option_paypal_enable_pay_now' ) || get_option( 'ec_option_paypal_enable_credit' ) || 'paypal' === get_option( 'ec_option_payment_third_party' ) ) {
				$rows[] = array( 'PayPal', __( 'PayPal screens payments on its side. If you take cards through PayPal\'s card button, check the fraud filters in your PayPal business account.', 'wp-easycart' ), 'https://www.paypal.com/businessmanage/account/accountOverview' );
			}
			if ( ! $rows ) {
				$rows[] = array( __( 'Your gateway', 'wp-easycart' ), __( 'Most gateways offer fraud filters in their own dashboard. Turning on security-code ( CVC ) and address checks there adds a second layer to the protection here.', 'wp-easycart' ), '' );
			}
			global $wpdb;
			$donations = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_product WHERE is_donation = 1 AND activate_in_store = 1 AND price < 5' );
			if ( $donations > 0 ) {
				/* translators: %d: products. */
				$rows[] = array( __( 'Your products', 'wp-easycart' ), sprintf( _n( '%d donation product has a minimum under 5. Bots love $1 donations: a minimum of 5 or more makes your store a poor target.', '%d donation products have a minimum under 5. Bots love $1 donations: a minimum of 5 or more makes your store a poor target.', $donations, 'wp-easycart' ), $donations ), admin_url( 'admin.php?page=wp-easycart-products&subpage=products' ) );
			}
			echo '<div class="ecpt-tips">';
			foreach ( $rows as $row ) {
				echo '<div class="ecpt-tip"><b>' . esc_html( $row[0] ) . '</b><span>' . esc_html( $row[1] ) . '</span>';
				if ( '' !== $row[2] ) {
					$external = 0 === strpos( $row[2], 'https://' ) && false === strpos( $row[2], admin_url() );
					echo '<a class="ecv2-btn ecv2-btn-sm" href="' . esc_url( $row[2] ) . '"' . ( $external ? ' target="_blank" rel="noopener noreferrer"' : '' ) . '>' . esc_html__( 'Open', 'wp-easycart' ) . ( $external ? ' ↗' : '' ) . '</a>';
				}
				echo '</div>';
			}
			echo '</div>';
		}

		/**
		 * Section Activity: filter chips, the first page of rows, and "Show more".
		 */
		public static function render_activity() {
			if ( ! self::engine() ) {
				return;
			}
			$filters = array(
				'all'  => __( 'All', 'wp-easycart' ),
				'stop' => __( 'Stopped', 'wp-easycart' ),
				'dec'  => __( 'Declined', 'wp-easycart' ),
				'pass' => __( 'Human checks', 'wp-easycart' ),
				'att'  => __( 'Attacks', 'wp-easycart' ),
			);
			echo '<div class="ecpt-filter" role="group" aria-label="' . esc_attr__( 'Filter activity', 'wp-easycart' ) . '">';
			foreach ( $filters as $key => $label ) {
				echo '<button type="button" class="ecpt-fbtn" data-ecpt-filter="' . esc_attr( $key ) . '" aria-pressed="' . ( 'all' === $key ? 'true' : 'false' ) . '">' . esc_html( $label ) . '</button>';
			}
			echo '</div>';
			$page = wp_easycart_checkout_guard::activity( 'all', 25, 0 );
			echo '<div class="ecpt-tablewrap"><table class="ecpt-table" id="ecpt-activity"><thead><tr><th>' . esc_html__( 'When', 'wp-easycart' ) . '</th><th>' . esc_html__( 'What happened', 'wp-easycart' ) . '</th><th>' . esc_html__( 'Where', 'wp-easycart' ) . '</th><th>' . esc_html__( 'Shopper', 'wp-easycart' ) . '</th><th>' . esc_html__( 'Card', 'wp-easycart' ) . '</th><th></th></tr></thead><tbody>';
			echo self::activity_rows( $page['rows'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- activity_rows() escapes every value.
			echo '</tbody></table></div>';
			/* translators: 1: rows shown, 2: total rows. */
			echo '<div class="ecpt-foot"><span>' . esc_html( sprintf( __( 'Kept for %d days, then deleted. Network addresses and emails are stored hashed; only a shortened form is shown.', 'wp-easycart' ), wp_easycart_checkout_guard::settings()['retention'] ) ) . '</span><span><span id="ecpt-count">' . esc_html( sprintf( __( 'Showing %1$d of %2$d', 'wp-easycart' ), count( $page['rows'] ), (int) $page['total'] ) ) . '</span> <button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" id="ecpt-more"' . ( count( $page['rows'] ) >= (int) $page['total'] ? ' hidden' : '' ) . '>' . esc_html__( 'Show more', 'wp-easycart' ) . '</button></span></div>';
		}

		/**
		 * Rows for the activity table.
		 *
		 * @param array $rows Event rows.
		 * @return string HTML ( escaped ).
		 */
		public static function activity_rows( $rows ) {
			if ( ! $rows ) {
				return '<tr><td colspan="6" class="ecpt-empty">' . esc_html__( 'Nothing yet. Stops, declines, checks and attacks appear here as they happen.', 'wp-easycart' ) . '</td></tr>';
			}
			$places = array(
				'checkout'     => __( 'Checkout', 'wp-easycart' ),
				'express'      => __( 'Express checkout', 'wp-easycart' ),
				'pay_link'     => __( 'Pay link', 'wp-easycart' ),
				'subscription' => __( 'Subscription', 'wp-easycart' ),
				'paypal'       => __( 'PayPal', 'wp-easycart' ),
				'code'         => __( 'Gift card or coupon', 'wp-easycart' ),
				'card_update'  => __( 'Saved card, My Account', 'wp-easycart' ),
			);
			$html   = '';
			foreach ( $rows as $row ) {
				list( $tag_class, $tag, $text ) = self::describe( $row );
				$where                          = isset( $places[ $row->place ] ) ? $places[ $row->place ] : '';
				$where                         .= ( '' !== (string) $row->gateway && 'code' !== $row->place ) ? ' · ' . ucwords( str_replace( '_', ' ', (string) $row->gateway ) ) : '';
				$shopper                        = trim( (string) $row->ip_display . ( '' !== (string) $row->email_display ? ' · ' . $row->email_display : '' ), ' ·' );
				if ( '' === $shopper && (int) $row->user_id > 0 ) {
					$shopper = __( 'Signed-in customer', 'wp-easycart' );
				}
				$actions = '';
				if ( in_array( $row->event_type, array( 'stop', 'decline', 'would_stop', 'code_stop' ), true ) ) {
					foreach ( array( 'ip_key', 'email_key', 'session_key' ) as $column ) {
						if ( '' !== (string) $row->{$column} ) {
							$actions .= '<button type="button" class="ecv2-btn ecv2-btn-sm" data-ecpt-unpause="' . esc_attr( $column . ':' . $row->{$column} ) . '">' . esc_html__( 'Unpause', 'wp-easycart' ) . '</button>';
							break;
						}
					}
					foreach ( array(
						'ip_key'    => $row->ip_display,
						'email_key' => $row->email_display,
					) as $column => $display ) {
						if ( '' !== (string) $row->{$column} ) {
							$actions .= ' <button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" data-ecpt-block="' . esc_attr( $row->{$column} ) . '" data-ecpt-display="' . esc_attr( $display ) . '">' . esc_html__( 'Block', 'wp-easycart' ) . '</button>';
							break;
						}
					}
				}
				if ( (int) $row->order_id > 0 ) {
					$actions .= ' <a class="ecv2-btn ecv2-btn-sm" href="' . esc_url( admin_url( 'admin.php?page=wp-easycart-orders&subpage=orders&ec_admin_form_action=edit&order_id=' . (int) $row->order_id ) ) . '">' . esc_html__( 'Open order', 'wp-easycart' ) . '</a>';
				}
				$html .= '<tr><td class="ecpt-nowrap">' . esc_html( wp_easycart_checkout_guard::local_time( wp_easycart_checkout_guard::ts( $row->created_at ) ) ) . '</td>';
				$html .= '<td><span class="ecpt-tag ' . esc_attr( $tag_class ) . '">' . esc_html( $tag ) . '</span> ' . esc_html( $text ) . '</td>';
				$html .= '<td>' . esc_html( $where ) . '</td><td class="ecpt-mono">' . esc_html( $shopper ) . '</td><td>' . esc_html( (string) $row->card_display ) . '</td><td class="ecpt-right ecpt-nowrap">' . $actions . '</td></tr>';
			}
			return $html;
		}

		/**
		 * The tag and sentence for one event.
		 *
		 * @param object $row Event.
		 * @return array tag class, tag, text
		 */
		private static function describe( $row ) {
			$reasons = array(
				'blocked'        => __( 'On the block list', 'wp-easycart' ),
				'paused_session' => __( 'Paused: too many failed payments from this browser', 'wp-easycart' ),
				'paused_ip'      => __( 'Paused: too many failed payments from this network address', 'wp-easycart' ),
				'paused_email'   => __( 'Paused: too many failed payments with this email', 'wp-easycart' ),
				'paused_card'    => __( 'Card refused: it failed too many times today', 'wp-easycart' ),
				'check_attack'   => __( 'Asked for a human check ( attack mode )', 'wp-easycart' ),
				'check_always'   => __( 'Asked for a human check', 'wp-easycart' ),
				'check_declined' => __( 'Asked for a human check after a decline', 'wp-easycart' ),
				'check_page'     => __( 'Asked for a human check: no checkout page visit', 'wp-easycart' ),
				'no_page'        => __( 'Refused: no checkout page visit and no human check available', 'wp-easycart' ),
			);
			$amount  = ( (float) $row->amount > 0 && isset( $GLOBALS['currency'] ) && is_object( $GLOBALS['currency'] ) ) ? wp_strip_all_tags( $GLOBALS['currency']->get_currency_display( (float) $row->amount ) ) : '';
			switch ( $row->event_type ) {
				case 'stop':
					return array( 'is-stop', __( 'Stopped', 'wp-easycart' ), isset( $reasons[ $row->reason ] ) ? $reasons[ $row->reason ] : (string) $row->reason );
				case 'would_stop':
					return array( 'is-watch', __( 'Would stop', 'wp-easycart' ), isset( $reasons[ $row->reason ] ) ? $reasons[ $row->reason ] : ( 'spike' === $row->reason ? __( 'Would start attack mode', 'wp-easycart' ) : (string) $row->reason ) );
				case 'check':
					return array( 'is-pause', __( 'Check asked', 'wp-easycart' ), isset( $reasons[ $row->reason ] ) ? $reasons[ $row->reason ] : '' );
				case 'decline':
					/* translators: %s: bank's reason code. */
					$text = ( '' !== (string) $row->reason && 'declined' !== $row->reason ) ? sprintf( __( 'Bank said: %s', 'wp-easycart' ), str_replace( '_', ' ', (string) $row->reason ) ) : __( 'The payment was declined', 'wp-easycart' );
					return array( 'is-dec', __( 'Declined', 'wp-easycart' ), trim( $text . ( '' !== $amount ? ' · ' . $amount : '' ) ) );
				case 'paid':
					if ( 'possible_test' === $row->reason ) {
						/* translators: %s: amount. */
						return array( 'is-att', __( 'Possible card test', 'wp-easycart' ), sprintf( __( 'Paid %s during an attack, after declines', 'wp-easycart' ), $amount ) );
					}
					/* translators: %s: amount. */
					return array( 'is-pass', __( 'Paid', 'wp-easycart' ), sprintf( __( 'Paid %s', 'wp-easycart' ), $amount ) );
				case 'check_pass':
					return array( 'is-pass', __( 'Passed check', 'wp-easycart' ), '' );
				case 'check_fail':
					return array( 'is-stop', __( 'Failed check', 'wp-easycart' ), '' );
				case 'attack_start':
					/* translators: %d: declines. */
					return array( 'is-att', __( 'Attack started', 'wp-easycart' ), 'manual' === $row->reason ? __( 'Extra checks turned on by the store', 'wp-easycart' ) : sprintf( __( '%d declines in 15 minutes', 'wp-easycart' ), (int) $row->detail ) );
				case 'attack_end':
					$summary = json_decode( (string) $row->detail, true );
					/* translators: 1: minutes, 2: attempts stopped. */
					return array( 'is-att', __( 'Attack ended', 'wp-easycart' ), is_array( $summary ) ? sprintf( __( 'lasted %1$d minutes, %2$d stopped', 'wp-easycart' ), (int) $summary['minutes'], (int) $summary['stopped'] ) : '' );
				case 'code_stop':
					return array( 'is-stop', __( 'Stopped', 'wp-easycart' ), __( 'Too many wrong gift card or coupon codes', 'wp-easycart' ) );
				case 'cleared':
					return array( 'is-watch', __( 'Unpaused', 'wp-easycart' ), __( 'Unpaused by the store', 'wp-easycart' ) );
			}
			return array( 'is-watch', (string) $row->event_type, (string) $row->reason );
		}

		// ------------------------------------------------------------------
		// Actions ( ecv2_settings_action callbacks ).
		// ------------------------------------------------------------------

		/**
		 * Action: send a test alert email.
		 *
		 * @return string|WP_Error
		 */
		public static function action_test_alert() {
			if ( ! self::engine() ) {
				return new WP_Error( 'unavailable', __( 'Checkout protection is not loaded.', 'wp-easycart' ) );
			}
			$to = wp_easycart_checkout_guard::recipients();
			if ( ! $to ) {
				return new WP_Error( 'no_recipient', __( 'There is no valid address to send to. Add one above, or set your order notification addresses on Settings › Email.', 'wp-easycart' ) );
			}
			if ( ! wp_easycart_checkout_guard::send_alert( 'test', array( 'recent' => 12 ) ) ) {
				return new WP_Error( 'not_sent', __( 'The test email could not be sent. Check Settings › Email › Deliverability.', 'wp-easycart' ) );
			}
			/* translators: %s: recipients. */
			return sprintf( __( 'Test alert sent to %s.', 'wp-easycart' ), implode( ', ', $to ) );
		}

		/**
		 * Action: back to the recommended settings ( keys and lists stay ).
		 *
		 * @return array
		 */
		public static function action_reset() {
			$defaults = array(
				'level'           => 'standard',
				'human'           => 'smart',
				'attack_limit'    => '5',
				'attack_minutes'  => '60',
				'alerts'          => '1',
				'flag_orders'     => '1',
				'declines'        => 'simple',
				'code_limits'     => '1',
				'trust_customers' => '1',
				'proxy'           => 'auto',
				'require_page'    => '1',
				'retention'       => '30',
			);
			foreach ( $defaults as $key => $value ) {
				update_option( 'ec_option_checkout_protection_' . $key, $value );
			}
			if ( self::engine() ) {
				wp_easycart_checkout_guard::flush_settings();
			}
			return array(
				'message' => __( 'Back to the recommended settings.', 'wp-easycart' ),
				'reload'  => true,
			);
		}

		/**
		 * Action: unpause everyone.
		 *
		 * @return array
		 */
		public static function action_clear_pauses() {
			$cleared = self::engine() ? wp_easycart_checkout_guard::clear_all() : 0;
			return array(
				/* translators: %d: sources unpaused. */
				'message' => sprintf( _n( '%d pause cleared.', '%d pauses cleared.', $cleared, 'wp-easycart' ), $cleared ),
				'reload'  => true,
			);
		}

		// ------------------------------------------------------------------
		// AJAX.
		// ------------------------------------------------------------------

		/**
		 * AJAX: a page of activity.
		 */
		public static function ajax_activity() {
			ecv2_protection_guard();
			$filter = isset( $_POST['filter'] ) ? sanitize_key( wp_unslash( $_POST['filter'] ) ) : 'all';
			$offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
			$page   = self::engine() ? wp_easycart_checkout_guard::activity( $filter, 25, $offset ) : array(
				'rows'  => array(),
				'total' => 0,
			);
			wp_send_json_success(
				array(
					'html'  => self::activity_rows( $page['rows'] ),
					'shown' => $offset + count( $page['rows'] ),
					'total' => (int) $page['total'],
					/* translators: 1: rows shown, 2: total rows. */
					'count' => sprintf( __( 'Showing %1$d of %2$d', 'wp-easycart' ), $offset + count( $page['rows'] ), (int) $page['total'] ),
				)
			);
		}

		/**
		 * AJAX: unpause one key.
		 */
		public static function ajax_unpause() {
			ecv2_protection_guard();
			$raw   = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
			$parts = explode( ':', $raw, 2 );
			if ( 2 !== count( $parts ) || ! self::engine() || ! wp_easycart_checkout_guard::clear_key( $parts[0], $parts[1] ) ) {
				wp_send_json_error( array( 'message' => __( 'That pause could not be cleared.', 'wp-easycart' ) ) );
			}
			wp_send_json_success();
		}

		/**
		 * AJAX: add a hashed key to the block list.
		 */
		public static function ajax_block() {
			ecv2_protection_guard();
			$key     = isset( $_POST['key'] ) ? sanitize_key( wp_unslash( $_POST['key'] ) ) : '';
			$display = isset( $_POST['display'] ) ? sanitize_text_field( wp_unslash( $_POST['display'] ) ) : '';
			if ( ! self::engine() || ! wp_easycart_checkout_guard::block_key( $key, $display ) ) {
				wp_send_json_error( array( 'message' => __( 'That shopper could not be blocked.', 'wp-easycart' ) ) );
			}
			wp_send_json_success();
		}

		/**
		 * AJAX: extra checks on, off, or held for 24 hours.
		 */
		public static function ajax_extra() {
			ecv2_protection_guard();
			$mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : '';
			self::extra( $mode );
			wp_send_json_success();
		}

		/**
		 * Turn extra checks on, off or hold them.
		 *
		 * @param string $mode on | off | hold.
		 */
		private static function extra( $mode ) {
			if ( ! self::engine() ) {
				return;
			}
			if ( 'on' === $mode ) {
				wp_easycart_checkout_guard::start_attack( true );
			} elseif ( 'off' === $mode ) {
				wp_easycart_checkout_guard::end_attack( true );
			} elseif ( 'hold' === $mode ) {
				wp_easycart_checkout_guard::hold_attack();
			}
		}

		/**
		 * AJAX: the saved provider and site key and the key status note, for the page to redraw after a save or a test.
		 *
		 * @since 6.0.2
		 */
		public static function ajax_keys() {
			ecv2_protection_guard();
			wp_send_json_success(
				array(
					'provider' => self::engine() ? wp_easycart_checkout_guard::settings()['provider'] : 'turnstile',
					'site_key' => self::engine() ? wp_easycart_checkout_guard::site_key() : '',
					'status'   => self::keys_status_html(),
				)
			);
		}

		/**
		 * AJAX: verify a token from the test widget with the saved secret key.
		 */
		public static function ajax_test_token() {
			ecv2_protection_guard();
			$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
			if ( ! self::engine() ) {
				wp_send_json_error();
			}
			delete_transient( 'wpec_cp_provider_down' );
			$result = wp_easycart_checkout_guard::verify_token( $token );
			if ( 'pass' !== $result ) {
				$message = ( 'unavailable' === $result ) ? __( 'The provider refused the secret key, or could not be reached. Check the secret key belongs to this site key, then save and test again.', 'wp-easycart' ) : __( 'The check did not pass. Try the test again.', 'wp-easycart' );
				wp_send_json_error( array( 'message' => $message ) );
			}
			update_option(
				self::KEYS_OK_OPTION,
				array(
					'site' => wp_easycart_checkout_guard::site_key(),
					'time' => time(),
				),
				false
			);
			wp_send_json_success();
		}

		// ------------------------------------------------------------------
		// Banner.
		// ------------------------------------------------------------------

		/**
		 * The admin-post link behind a banner button.
		 *
		 * @param string $what off | hold | dismiss.
		 * @return string
		 */
		private static function post_url( $what ) {
			return wp_nonce_url( admin_url( 'admin-post.php?action=wpec_protection&do=' . rawurlencode( $what ) ), self::NONCE );
		}

		/**
		 * Handler for admin-post.php?action=wpec_protection: the banner's buttons.
		 */
		public static function admin_post() {
			check_admin_referer( self::NONCE );
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_settings' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- EasyCart's own roles register this capability.
				wp_die( esc_html__( 'You do not have permission to change checkout protection.', 'wp-easycart' ) );
			}
			$what = isset( $_GET['do'] ) ? sanitize_key( wp_unslash( $_GET['do'] ) ) : '';
			if ( 'dismiss' === $what ) {
				update_option( self::NOTICE_OPTION, time(), false );
			} else {
				self::extra( $what );
			}
			wp_safe_redirect( wp_get_referer() ? wp_get_referer() : self::url() );
			exit;
		}

		/**
		 * Action wp_easycart_admin_messages ( every EasyCart screen, inside the shell ): the attack banner, and once the
		 * "it's on" notice. 6.0.2: the shell's notice card ( .wpec-pro-notice, admin-frame-v2.css ), styled on every screen.
		 */
		public static function banner() {
			if ( ! self::engine() || ! current_user_can( 'manage_options' ) || ! class_exists( 'wp_easycart_admin' ) ) {
				return;
			}
			wp_easycart_checkout_guard::maybe_end_attack();
			if ( wp_easycart_checkout_guard::attack_active() ) {
				$attack  = wp_easycart_checkout_guard::attack();
				$summary = wp_easycart_checkout_guard::attack_summary( (int) $attack['started'], time() );
				$notice = wp_easycart_admin::notice_html(
					'error',
					/* translators: %s: time. */
					sprintf( __( 'Card-testing attack in progress. Extra checks are on until %s.', 'wp-easycart' ), wp_easycart_checkout_guard::local_time( (int) $attack['until'] ) ),
					array(
						'detail'      => ! empty( $attack['manual'] )
							? __( 'You turned on extra checks. Every shopper gets a quick human check until they end.', 'wp-easycart' )
							/* translators: 1: attempts stopped, 2: declines. */
							: sprintf( __( '%1$d payment attempts stopped and %2$d declined so far. Real shoppers can still pay after a quick check. You don\'t need to do anything right now.', 'wp-easycart' ), (int) $summary['stopped'], (int) $summary['declined'] ),
						'dismissible' => false,
						'class'       => 'ecpt-banner',
						'actions'     => array(
							array(
								'label'   => __( 'See what\'s happening', 'wp-easycart' ),
								'url'     => self::url( 'status' ),
								'primary' => true,
							),
							array(
								'label' => __( 'Keep on for 24 hours', 'wp-easycart' ),
								'url'   => self::post_url( 'hold' ),
							),
							array(
								'label' => __( 'End extra checks now', 'wp-easycart' ),
								'url'   => self::post_url( 'off' ),
							),
						),
					)
				);
				echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- notice_html() escapes every part.
				return;
			}
			/* The one-time "it's on" notice, but not on the page it points to ( that page marks it seen ). */
			$on_page  = isset( $_GET['subpage'] ) && 'checkout-protection' === sanitize_key( wp_unslash( $_GET['subpage'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only check of the screen.
			$settings = wp_easycart_checkout_guard::settings();
			if ( ! $on_page && ! get_option( self::NOTICE_OPTION ) && 'off' !== $settings['level'] && wp_easycart_checkout_guard::ready() ) {
				$notice = wp_easycart_admin::notice_html(
					'info',
					__( 'New: checkout protection is on.', 'wp-easycart' ),
					array(
						'detail'      => __( 'Your checkout now stops bots that test stolen cards, without getting in real shoppers\' way. It works as it is; add free Cloudflare Turnstile keys for the smoothest experience during an attack.', 'wp-easycart' ),
						'dismissible' => false,
						'class'       => 'ecpt-banner',
						'actions'     => array(
							array(
								'label'   => __( 'See how it works', 'wp-easycart' ),
								'url'     => self::url(),
								'primary' => true,
							),
							array(
								'label' => __( 'Got it', 'wp-easycart' ),
								'url'   => self::post_url( 'dismiss' ),
							),
						),
					)
				);
				echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- notice_html() escapes every part.
			}
		}
	}

	wp_easycart_admin_checkout_protection::init();

endif;
