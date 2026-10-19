<?php
/**
 * Settings › Design › Cart & checkout look › "Fit the cart to your theme's content area" ( 6.0.5, wp_easycart_cart_fit ): what the
 * store's theme gives the cart, measured on the cart page itself, and the recommendation.
 *
 * Measured two ways: a store manager's own visit to the cart page ( the storefront script reports it ), and "Check my theme" on the
 * settings page, which opens the cart page in a hidden frame 1440px wide and reads the cart's width there ( admin/js/cart-fit-v2.js ).
 * The same check runs by itself, once, for a theme not measured yet: on Settings › Design, and on any EasyCart screen a store
 * administrator opens ( at most every six hours while it does not finish ). When the cart gets less room than the screen's own layout needs, the
 * settings row says so and every EasyCart screen shows one notice offering to turn the setting on ( until Not now, per theme ).
 *
 * @package wp-easycart
 * @since   6.0.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_cart_fit' ) ) :

	/**
	 * The admin side of the cart fit.
	 */
	class wp_easycart_admin_cart_fit {

		/**
		 * The admin-post action of the notice's buttons.
		 */
		const NOTICE_ACTION = 'wp_easycart_cart_fit';

		/**
		 * The user transient that spaces out the check run by itself on EasyCart screens.
		 */
		const TRIED = 'wpec_cart_fit_tried';

		/**
		 * Hooks.
		 */
		public static function init() {
			add_action( 'wp_easycart_admin_messages', array( __CLASS__, 'notice' ), 18 );
			add_action( 'admin_post_' . self::NOTICE_ACTION, array( __CLASS__, 'handle_notice' ) );
			add_action( 'wp_ajax_ecv2_cart_fit', array( __CLASS__, 'ajax_check' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_auto' ), 20 );
		}

		/**
		 * The screen sizes as Settings › Design names them.
		 *
		 * @param string $band A wp_easycart_cart_fit::bands() key.
		 * @return string
		 */
		public static function band_label( $band ) {
			$labels = array(
				'desktop'     => __( 'Desktop', 'wp-easycart' ),
				'laptop'      => __( 'Tablet ( landscape )', 'wp-easycart' ),
				'tablet_wide' => __( 'Tablet ( portrait )', 'wp-easycart' ),
				'tablet'      => __( 'Phone ( landscape )', 'wp-easycart' ),
				'smartphone'  => __( 'Phone', 'wp-easycart' ),
			);
			return isset( $labels[ $band ] ) ? $labels[ $band ] : $labels['desktop'];
		}

		/**
		 * The active theme's name.
		 *
		 * @return string
		 */
		public static function theme_name() {
			$theme = function_exists( 'wp_get_theme' ) ? wp_get_theme() : null;
			$name  = $theme ? trim( (string) $theme->get( 'Name' ) ) : '';
			return ( '' !== $name ) ? $name : __( 'Your theme', 'wp-easycart' );
		}

		/**
		 * The cart page's address, '' without one.
		 *
		 * @return string
		 */
		public static function cart_url() {
			$page = (int) get_option( 'ec_option_cartpage' );
			if ( $page <= 0 || 'publish' !== get_post_status( $page ) ) {
				return '';
			}
			$url = get_permalink( $page );
			return $url ? (string) $url : '';
		}

		/**
		 * What the row under the setting says, for the setting on or off.
		 *
		 * @param bool $on The row shown while the setting is on.
		 * @return array tone ( success | warning | info ), text, detail, auto ( measure on load ), checked.
		 */
		public static function status( $on ) {
			$check = wp_easycart_cart_fit::check();
			$theme = self::theme_name();
			if ( '' === self::cart_url() ) {
				return array(
					'tone'    => 'info',
					'text'    => __( 'Your store has no cart page to measure.', 'wp-easycart' ),
					'detail'  => __( 'Choose the cart page in Settings › Store pages, then come back.', 'wp-easycart' ),
					'auto'    => false,
					'checked' => false,
				);
			}
			if ( ! $check ) {
				return array(
					'tone'    => 'info',
					/* translators: %s: the theme's name. */
					'text'    => sprintf( __( 'Your cart page has not been measured with %s yet.', 'wp-easycart' ), $theme ),
					'detail'  => __( 'Checking opens your cart page out of sight and measures the room your theme gives the cart.', 'wp-easycart' ),
					'auto'    => true,
					'checked' => false,
				);
			}
			$ago = sprintf(
				/* translators: %s: how long ago, e.g. 5 minutes. */
				__( 'Measured %s ago.', 'wp-easycart' ),
				human_time_diff( (int) $check['at'], time() )
			);
			if ( 'blocked' === $check['fail'] ) {
				return array(
					'tone'    => 'info',
					'text'    => __( 'Your cart page could not be opened here to measure it.', 'wp-easycart' ),
					'detail'  => __( 'A security setting may stop it showing inside the admin. Open your cart page while you are signed in instead: it is measured as it loads.', 'wp-easycart' ),
					'auto'    => false,
					'checked' => true,
				);
			}
			if ( 'none' === $check['fail'] ) {
				return array(
					'tone'    => 'info',
					'text'    => __( 'No WP EasyCart cart was found on your cart page.', 'wp-easycart' ),
					'detail'  => __( 'The page needs the [ec_cart] shortcode, the WP EasyCart block or an Elementor Cart widget.', 'wp-easycart' ),
					'auto'    => false,
					'checked' => true,
				);
			}
			if ( 'elementor' === $check['page'] ) {
				return array(
					'tone'    => 'info',
					'text'    => __( 'Your cart page draws the cart with an Elementor widget.', 'wp-easycart' ),
					'detail'  => __( 'Set its layout for each device in Elementor: this setting leaves Elementor’s Cart and Checkout widgets as they are.', 'wp-easycart' ),
					'auto'    => false,
					'checked' => true,
				);
			}
			$width    = (int) $check['width'];
			$viewport = (int) $check['viewport'];
			$band     = wp_easycart_cart_fit::band( $width );
			$columns  = wp_easycart_cart_fit::columns();
			$summary  = ( 1 === $columns[ $band ] ) ? __( 'the order summary under the checkout form', 'wp-easycart' ) : __( 'the order summary beside the checkout form', 'wp-easycart' );
			/* translators: 1: the theme's name, 2: the cart's width in pixels, 3: the screen's width in pixels. */
			$gives = sprintf( __( '%1$s gives the cart %2$dpx of a %3$dpx screen.', 'wp-easycart' ), $theme, $width, $viewport );
			if ( ! wp_easycart_cart_fit::changes( $width, $viewport ) ) {
				return array(
					'tone'    => 'success',
					'text'    => $gives,
					'detail'  => trim( ( $on ? __( 'That is enough room for the layout your shoppers’ screens get.', 'wp-easycart' ) : __( 'That is enough room for the desktop layout, so you do not need this.', 'wp-easycart' ) ) . ' ' . $ago ),
					'auto'    => false,
					'checked' => true,
				);
			}
			if ( $on ) {
				return array(
					'tone'    => 'success',
					'text'    => $gives,
					/* translators: 1: a screen size such as Tablet ( portrait ), 2: where the order summary goes. 3: when it was measured. */
					'detail'  => sprintf( __( 'The cart uses the %1$s layout there, with %2$s. %3$s', 'wp-easycart' ), self::band_label( $band ), $summary, $ago ),
					'auto'    => false,
					'checked' => true,
				);
			}
			return array(
				'tone'    => 'warning',
				'text'    => $gives,
				/* translators: 1: the cart's width, 2: a screen size such as Tablet ( portrait ), 3: where the order summary goes, 4: when it was measured. */
				'detail'  => sprintf( __( 'The checkout keeps its desktop columns in that space, so the order summary is squeezed. Turn this on to use the layout made for %1$dpx: %2$s, with %3$s. %4$s', 'wp-easycart' ), $width, self::band_label( $band ), $summary, $ago ),
				'auto'    => false,
				'checked' => true,
			);
		}

		/**
		 * The row under the setting ( a settings html row; one for the setting on, one for off ).
		 *
		 * @param bool $on The row shown while the setting is on.
		 * @return string
		 */
		public static function status_html( $on ) {
			$status  = self::status( $on );
			$actions = array();
			if ( '' !== self::cart_url() ) {
				$actions[] = array(
					'label' => $status['checked'] ? __( 'Check again', 'wp-easycart' ) : __( 'Check my theme', 'wp-easycart' ),
					'url'   => '#wpec-cart-fit-check',
				);
				$actions[] = array(
					'label'  => __( 'Open your cart page', 'wp-easycart' ),
					'url'    => self::cart_url(),
					'target' => '_blank',
					'link'   => true,
				);
			}
			$html = wp_easycart_admin::notice_html(
				$status['tone'],
				$status['text'],
				array(
					'detail'      => $status['detail'],
					'actions'     => $actions,
					'dismissible' => false,
				)
			);
			return '<div class="wpec-cart-fit-status" data-wpec-cart-fit="' . ( $on ? 'on' : 'off' ) . '" data-auto="' . ( $status['auto'] ? '1' : '0' ) . '" aria-live="polite">' . $html . '</div>';
		}

		/**
		 * Settings render callable: the row shown while the setting is on.
		 */
		public static function render_on() {
			echo self::status_html( true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- notice_html() escapes its parts.
		}

		/**
		 * Settings render callable: the row shown while the setting is off.
		 */
		public static function render_off() {
			echo self::status_html( false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- notice_html() escapes its parts.
		}

		/**
		 * On an EasyCart screen a store administrator opens: the check, run by itself, while the active theme is not measured
		 * ( Settings › Design loads it through its section ).
		 */
		public static function enqueue_auto() {
			$page    = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: which screen this is.
			$subpage = isset( $_GET['subpage'] ) ? sanitize_key( wp_unslash( $_GET['subpage'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: which screen this is.
			if ( 0 !== strpos( $page, 'wp-easycart' ) || ( 'wp-easycart-settings' === $page && 'design' === $subpage ) || ! current_user_can( 'manage_options' ) ) {
				return;
			}
			if ( null !== wp_easycart_cart_fit::check() || '' === self::cart_url() || get_transient( self::TRIED . '_' . get_current_user_id() ) ) {
				return;
			}
			set_transient( self::TRIED . '_' . get_current_user_id(), 1, 6 * HOUR_IN_SECONDS );
			self::enqueue( true );
		}

		/**
		 * Settings › Design's section enqueue: the check's script.
		 *
		 * @param bool $auto Run the check as the screen opens ( a screen without the setting's rows ).
		 */
		public static function enqueue( $auto = false ) {
			$url = self::cart_url();
			wp_enqueue_script( 'wp_easycart_admin_cart_fit_v2', plugins_url( '/admin/js/cart-fit-v2.js', EC_PLUGIN_DIRECTORY . '/wpeasycart.php' ), array(), EC_CURRENT_VERSION, true );
			wp_localize_script(
				'wp_easycart_admin_cart_fit_v2',
				'wpecCartFit',
				array(
					'ajax'  => admin_url( 'admin-ajax.php' ),
					'nonce' => wp_create_nonce( wp_easycart_admin_settings_registry::NONCE ),
					'url'   => ( '' !== $url ) ? add_query_arg( wp_easycart_cart_fit::CHECK_ARG, '1', $url ) : '',
					'width' => 1440,
					'auto'  => ( true === $auto ) ? 1 : 0,
					'text'  => array(
						'checking' => __( 'Checking…', 'wp-easycart' ),
						'failed'   => __( 'The check did not finish. Reload the page and try again.', 'wp-easycart' ),
					),
				)
			);
		}

		/**
		 * AJAX ecv2_cart_fit ( Settings › Design's check ): keeps what the hidden frame measured and answers both rows.
		 */
		public static function ajax_check() {
			ecv2_settings_guard();
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- ecv2_settings_guard() above.
			$width    = isset( $_POST['width'] ) ? (int) $_POST['width'] : 0;
			$viewport = isset( $_POST['viewport'] ) ? (int) $_POST['viewport'] : 0;
			$page     = isset( $_POST['page'] ) ? sanitize_key( wp_unslash( $_POST['page'] ) ) : 'classic';
			$fail     = isset( $_POST['fail'] ) ? sanitize_key( wp_unslash( $_POST['fail'] ) ) : '';
			// phpcs:enable WordPress.Security.NonceVerification.Missing
			if ( '' === $fail && 'elementor' !== $page && ( $width <= 0 || $viewport <= 0 ) ) {
				$fail = 'none';
			}
			wp_easycart_cart_fit::record( $width, $viewport, $page, 'check', $fail );
			wp_send_json_success(
				array(
					'on'          => self::status_html( true ),
					'off'         => self::status_html( false ),
					'recommended' => wp_easycart_cart_fit::recommended( false ),
				)
			);
		}

		/**
		 * On EasyCart screens: the recommendation, once a theme is measured as too narrow for the screen's layout ( until Not now ).
		 */
		public static function notice() {
			if ( ! current_user_can( 'manage_options' ) || ! wp_easycart_cart_fit::recommended() ) {
				return;
			}
			if ( isset( $_GET['page'], $_GET['subpage'] ) && 'wp-easycart-settings' === $_GET['page'] && 'design' === $_GET['subpage'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: the setting's own row says it there.
				return;
			}
			$check = wp_easycart_cart_fit::check();
			$band  = wp_easycart_cart_fit::band( (int) $check['width'] );
			$base  = admin_url( 'admin-post.php?action=' . self::NOTICE_ACTION );
			$html  = wp_easycart_admin::notice_html(
				'warning',
				/* translators: %s: the theme's name. */
				sprintf( __( '%s gives your cart a narrow column', 'wp-easycart' ), self::theme_name() ),
				array(
					/* translators: 1: the cart's width in pixels, 2: the screen's width in pixels, 3: a screen size such as Tablet ( portrait ). */
					'detail'      => sprintf( __( 'Your cart page has %1$dpx of a %2$dpx screen, so the checkout’s desktop columns are squeezed there. Fit the cart to your theme’s content area and it uses the layout made for that width ( %3$s ).', 'wp-easycart' ), (int) $check['width'], (int) $check['viewport'], self::band_label( $band ) ),
					'dismissible' => false,
					'actions'     => array(
						array(
							'label'   => __( 'Fit the cart', 'wp-easycart' ),
							'url'     => wp_nonce_url( add_query_arg( 'do', 'on', $base ), self::NOTICE_ACTION ),
							'primary' => true,
						),
						array(
							'label' => __( 'See the setting', 'wp-easycart' ),
							'url'   => admin_url( 'admin.php?page=wp-easycart-settings&subpage=design#cart-checkout' ),
						),
						array(
							'label' => __( 'Not now', 'wp-easycart' ),
							'url'   => wp_nonce_url( add_query_arg( 'do', 'dismiss', $base ), self::NOTICE_ACTION ),
							'link'  => true,
						),
					),
				)
			);
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- notice_html() escapes its parts.
		}

		/**
		 * The admin-post handler of the notice's Fit the cart and Not now.
		 */
		public static function handle_notice() {
			if ( ! current_user_can( 'manage_options' ) || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), self::NOTICE_ACTION ) ) {
				wp_die( esc_html__( 'This link has expired. Go back and try again.', 'wp-easycart' ) );
			}
			$do = isset( $_GET['do'] ) ? sanitize_key( wp_unslash( $_GET['do'] ) ) : '';
			if ( 'on' === $do ) {
				update_option( wp_easycart_cart_fit::OPTION, '1' );
			} elseif ( 'dismiss' === $do ) {
				update_option( wp_easycart_cart_fit::DISMISS_OPTION, get_stylesheet(), false );
			}
			$back = wp_get_referer();
			$back = $back ? $back : admin_url( 'admin.php?page=wp-easycart-settings&subpage=design' );
			if ( 'on' === $do ) {
				$back = add_query_arg( 'wpec_cart_fit', 'on', $back );
			}
			wp_safe_redirect( $back );
			exit;
		}

		/**
		 * Settings › Design › Cart & checkout look: the result of the notice's Fit the cart, on the screen it was pressed on.
		 */
		public static function result() {
			if ( ! isset( $_GET['wpec_cart_fit'] ) || 'on' !== $_GET['wpec_cart_fit'] || ! wp_easycart_cart_fit::enabled() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: a result to show.
				return;
			}
			$html = wp_easycart_admin::notice_html(
				'success',
				__( 'Your cart now fits your theme’s content area', 'wp-easycart' ),
				array(
					'detail' => __( 'The cart and checkout use the layout made for the room your theme gives them. Change it in Settings › Design › Cart & checkout look.', 'wp-easycart' ),
					'class'  => 'ecsh-result',
				)
			);
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- notice_html() escapes its parts.
		}
	}

	wp_easycart_admin_cart_fit::init();
	add_action( 'wp_easycart_admin_messages', array( 'wp_easycart_admin_cart_fit', 'result' ), 4 );

endif;
