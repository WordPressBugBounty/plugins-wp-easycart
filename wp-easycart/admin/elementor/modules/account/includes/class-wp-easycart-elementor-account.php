<?php
/**
 * WP EasyCart Elementor account widgets: shared engine ( 6.0.2 ).
 *
 * Loaded on every request with the module ( no Elementor dependency ). Holds everything the account widgets share:
 *
 * - Views. Every widget shows one or more account views, named with the store's own ec_page keys ( login, register,
 *   forgot_password, reset_password, dashboard, orders, order_details, personal_information, password,
 *   billing_information, shipping_information, subscriptions, subscription_details, payment_methods ) plus downloads.
 * - Links on the page where the widgets are. Every account link and redirect goes through wpeasycart_links()
 *   ->get_account_page( $key ), which applies wp_easycart_account_{key}_link. While a widget draws, and while a form one of
 *   them posted is processed ( load_ec_pre() on wp ), those filters point each view this page can show at this page, and
 *   leave every other view on the store's account page. Forms carry the hidden field wpec_el_account ( the views of the
 *   widget that drew them ); nothing in it is a URL, so it can never send a customer to another site.
 * - Page detection ( page_widgets() ): the queried post's Elementor data, one level of embedded templates and popups
 *   opened by a button, and the Elementor Pro Theme Builder documents for the request ( header, footer, single, archive,
 *   popup ). Caching ( page_headers() ): a signed-in customer's pages are never cached; for visitors only pages with a
 *   sign-in, sign-up or lost-password form ( fresh nonces ) or a guest order link. Pages with account widgets are never
 *   framed. Scripts come from the widgets' own dependencies ( reCAPTCHA ); Stripe and checkout protection load for a
 *   subscription's card form.
 * - Customer views ( orders, addresses, downloads … ) never draw for a visitor, whatever Show to says.
 * - Rendering through EasyCart's own account page ( WP_EasyCart_Elementor_Account_Page extends ec_accountpage and its
 *   templates ), so pay links, document links and download rules, checkout-field answers, subscriptions, order payments
 *   and the account hooks come along. Each widget's output is post-processed: the templates' own menu columns ( and the
 *   second sign-in form on the register and lost-password templates ) are removed from the markup, not hidden, so no id
 *   appears twice on the page.
 * - Messages ( account_success / account_error ) print once per page, in the first widget that shows their view.
 * - Editor sample data ( WP_EasyCart_Elementor_Account_Samples ) when the editing admin has no store account or no data.
 * - Round 11: a widget's own wording for the store's forms ( render() 'texts', through the filter
 *   wp_easycart_language_text while it draws; language_filter_ready() says whether the store's language class has it ), and
 *   decorate(): the required * in a span, each order status in a span, placeholders on the fields a widget names.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Account' ) ) :

	/**
	 * Shared engine of the account widgets.
	 */
	class WP_EasyCart_Elementor_Account {

		/**
		 * Hidden form field ( and logout link argument ) naming the views of the widget that drew the form.
		 */
		const MARKER = 'wpec_el_account';

		/**
		 * Language section of the widgets' own wording.
		 */
		const LANGUAGE_SECTION = 'elementor_account';

		/**
		 * Link contexts, innermost last: array( 'here' => url, 'views' => array ).
		 *
		 * @var array
		 */
		private static $links = array();

		/**
		 * Widget names found on this request's page ( null until page_widgets() ran ).
		 *
		 * @var array|null
		 */
		private static $page_widgets = null;

		/**
		 * The account page shared by every widget on the page, and the editor's sample one.
		 *
		 * @var WP_EasyCart_Elementor_Account_Page|null
		 */
		private static $account_page = null;

		/**
		 * Sample account page ( editor only ).
		 *
		 * @var WP_EasyCart_Elementor_Account_Page|null
		 */
		private static $sample_page = null;

		/**
		 * The shopper's own ec_user while a sample customer stands in for it.
		 *
		 * @var object|null
		 */
		private static $saved_user = null;

		/**
		 * Whether this request's account message already printed.
		 *
		 * @var bool
		 */
		private static $message_printed = false;

		/**
		 * What the probe filter answers ( language_filter_ready() ).
		 */
		const LANGUAGE_PROBE = '__wpec_el_account_language_probe__';

		/**
		 * Whether the store's language class runs wp_easycart_language_text ( null until asked ).
		 *
		 * @var bool|null
		 */
		private static $language_ready = null;

		/**
		 * Wording the drawing widgets replace, innermost last: each 'section|key' => text.
		 *
		 * @var array
		 */
		private static $texts = array();

		/**
		 * Hooks ( once ).
		 */
		public static function init() {
			static $done = false;
			if ( $done ) {
				return;
			}
			$done = true;
			add_action( 'wp', array( __CLASS__, 'route_request' ), 1 );
			/* First on template_redirect: anything later may still end the request ( WP EasyCart 6.0.2's ec_fix_store_template() did,
			 * at priority 1, for store items drawn with a custom store template ). */
			add_action( 'template_redirect', array( __CLASS__, 'page_headers' ), 0 );
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 1 );
			add_action( 'elementor/frontend/after_register_scripts', array( __CLASS__, 'register_assets' ) );
			add_action( 'elementor/frontend/after_register_styles', array( __CLASS__, 'register_assets' ) );
		}

		/**
		 * The account widgets: name => array( class, file, views, audience ).
		 *
		 * Audience is the default of the widget's Show to setting: 'logged_in', 'logged_out' or 'always'.
		 *
		 * @return array
		 */
		public static function widgets() {
			$dir = dirname( __DIR__ ) . '/widgets/';
			return array(
				'wp_easycart_my_account'                 => array(
					'class'    => 'WP_EasyCart_Elementor_My_Account_Widget',
					'file'     => $dir . 'class-wp-easycart-elementor-my-account-widget.php',
					'views'    => array( 'all' ),
					'audience' => 'always',
				),
				'wp_easycart_my_account_login'           => array(
					'class'    => 'WP_EasyCart_Elementor_My_Account_Login_Widget',
					'file'     => $dir . 'class-wp-easycart-elementor-my-account-login-widget.php',
					'views'    => array( 'login' ),
					'audience' => 'logged_out',
				),
				'wp_easycart_my_account_register'        => array(
					'class'    => 'WP_EasyCart_Elementor_My_Account_Register_Widget',
					'file'     => $dir . 'class-wp-easycart-elementor-my-account-register-widget.php',
					'views'    => array( 'register' ),
					'audience' => 'logged_out',
				),
				'wp_easycart_my_account_lost_password'   => array(
					'class'    => 'WP_EasyCart_Elementor_My_Account_Lost_Password_Widget',
					'file'     => $dir . 'class-wp-easycart-elementor-my-account-lost-password-widget.php',
					'views'    => array( 'forgot_password', 'reset_password' ),
					'audience' => 'logged_out',
				),
				'wp_easycart_my_account_navigation'      => array(
					'class'    => 'WP_EasyCart_Elementor_My_Account_Navigation_Widget',
					'file'     => $dir . 'class-wp-easycart-elementor-my-account-navigation-widget.php',
					'views'    => array(),
					'audience' => 'logged_in',
				),
				'wp_easycart_my_account_dashboard'       => array(
					'class'    => 'WP_EasyCart_Elementor_My_Account_Dashboard_Widget',
					'file'     => $dir . 'class-wp-easycart-elementor-my-account-dashboard-widget.php',
					'views'    => array( 'dashboard' ),
					'audience' => 'logged_in',
				),
				'wp_easycart_my_account_orders'          => array(
					'class'    => 'WP_EasyCart_Elementor_My_Account_Orders_Widget',
					'file'     => $dir . 'class-wp-easycart-elementor-my-account-orders-widget.php',
					'views'    => array( 'orders', 'order_details' ),
					'audience' => 'logged_in',
				),
				'wp_easycart_my_account_order_details'   => array(
					'class'    => 'WP_EasyCart_Elementor_My_Account_Order_Details_Widget',
					'file'     => $dir . 'class-wp-easycart-elementor-my-account-order-details-widget.php',
					'views'    => array( 'order_details' ),
					'audience' => 'always',
				),
				'wp_easycart_my_account_subscriptions'   => array(
					'class'    => 'WP_EasyCart_Elementor_My_Account_Subscriptions_Widget',
					'file'     => $dir . 'class-wp-easycart-elementor-my-account-subscriptions-widget.php',
					'views'    => array( 'subscriptions', 'subscription_details' ),
					'audience' => 'logged_in',
				),
				'wp_easycart_my_account_addresses'       => array(
					'class'    => 'WP_EasyCart_Elementor_My_Account_Addresses_Widget',
					'file'     => $dir . 'class-wp-easycart-elementor-my-account-addresses-widget.php',
					'views'    => array( 'billing_information', 'shipping_information' ),
					'audience' => 'logged_in',
				),
				'wp_easycart_my_account_details'         => array(
					'class'    => 'WP_EasyCart_Elementor_My_Account_Details_Widget',
					'file'     => $dir . 'class-wp-easycart-elementor-my-account-details-widget.php',
					'views'    => array( 'personal_information', 'password' ),
					'audience' => 'logged_in',
				),
				'wp_easycart_my_account_payment_methods' => array(
					'class'    => 'WP_EasyCart_Elementor_My_Account_Payment_Methods_Widget',
					'file'     => $dir . 'class-wp-easycart-elementor-my-account-payment-methods-widget.php',
					'views'    => array( 'payment_methods' ),
					'audience' => 'logged_in',
				),
				'wp_easycart_my_account_downloads'       => array(
					'class'    => 'WP_EasyCart_Elementor_My_Account_Downloads_Widget',
					'file'     => $dir . 'class-wp-easycart-elementor-my-account-downloads-widget.php',
					'views'    => array( 'downloads' ),
					'audience' => 'logged_in',
				),
				'wp_easycart_my_account_logout'          => array(
					'class'    => 'WP_EasyCart_Elementor_My_Account_Logout_Widget',
					'file'     => $dir . 'class-wp-easycart-elementor-my-account-logout-widget.php',
					'views'    => array(),
					'audience' => 'logged_in',
				),
			);
		}

		/**
		 * Every view key the widgets know ( 'all' = every view ).
		 *
		 * @return array
		 */
		public static function known_views() {
			return array( 'all', 'login', 'register', 'forgot_password', 'reset_password', 'dashboard', 'orders', 'order_details', 'personal_information', 'password', 'billing_information', 'shipping_information', 'subscriptions', 'subscription_details', 'payment_methods', 'downloads' );
		}

		/**
		 * Link keys of wpeasycart_links()->get_account_page() the widgets route ( print_receipt and activate_account work on
		 * any page and stay as they are ).
		 *
		 * @return array
		 */
		private static function link_keys() {
			return array( 'login', 'register', 'forgot_password', 'reset_password', 'dashboard', 'orders', 'order_details', 'personal_information', 'billing_information', 'shipping_information', 'password', 'subscriptions', 'subscription_details', 'payment_methods', 'logout' );
		}

		/**
		 * A view list from a comma-separated string or an array, limited to known views.
		 *
		 * @param mixed $raw Raw list.
		 * @return array
		 */
		public static function sanitize_views( $raw ) {
			if ( is_string( $raw ) ) {
				$raw = explode( ',', $raw );
			}
			$views = array();
			foreach ( (array) $raw as $view ) {
				$view = sanitize_key( (string) $view );
				if ( in_array( $view, self::known_views(), true ) ) {
					$views[] = $view;
				}
			}
			return array_values( array_unique( $views ) );
		}

		/**
		 * Wording shoppers see, from the language file's elementor_account section with an English fallback.
		 *
		 * @param string $key      Language key.
		 * @param string $fallback English text.
		 * @return string Plain text ( escape on output ).
		 */
		public static function text( $key, $fallback ) {
			$text = function_exists( 'wp_easycart_language' ) ? wp_easycart_language()->get_text( self::LANGUAGE_SECTION, $key ) : '';
			$text = is_string( $text ) ? trim( $text ) : '';
			return ( '' === $text ) ? $fallback : $text;
		}

		/**
		 * Wording from any language section, with an English fallback.
		 *
		 * @param string $section  Section.
		 * @param string $key      Key.
		 * @param string $fallback English text.
		 * @return string
		 */
		public static function store_text( $section, $key, $fallback ) {
			$text = function_exists( 'wp_easycart_language' ) ? wp_easycart_language()->get_text( $section, $key ) : '';
			$text = is_string( $text ) ? trim( $text ) : '';
			return ( '' === $text ) ? $fallback : $text;
		}

		/*
		 * ---------------------------------------------------------------------------------------------------------------
		 * Wording a widget replaces ( round 11 ).
		 * ---------------------------------------------------------------------------------------------------------------
		 */

		/**
		 * Whether the store's language class passes its wording through the filter wp_easycart_language_text( $text,
		 * $section, $key ). Asked once: a probe filter answers a marker, and get_text() returns it only when it runs the
		 * filter. The widgets register their text controls only when it does, so no control ever does nothing.
		 *
		 * @return bool
		 */
		public static function language_filter_ready() {
			if ( null !== self::$language_ready ) {
				return self::$language_ready;
			}
			if ( ! function_exists( 'wp_easycart_language' ) ) {
				return false; /* not cached: the language class may load later */
			}
			$language = wp_easycart_language();
			if ( ! is_object( $language ) || ! method_exists( $language, 'get_text' ) ) {
				self::$language_ready = false;
				return false;
			}
			$probe = array( __CLASS__, 'language_probe' );
			add_filter( 'wp_easycart_language_text', $probe, PHP_INT_MAX, 3 );
			$answer = $language->get_text( self::LANGUAGE_SECTION, 'nav_dashboard' );
			remove_filter( 'wp_easycart_language_text', $probe, PHP_INT_MAX );
			self::$language_ready = ( self::LANGUAGE_PROBE === $answer );
			return self::$language_ready;
		}

		/**
		 * The probe filter ( language_filter_ready() ).
		 *
		 * @return string
		 */
		public static function language_probe() {
			return self::LANGUAGE_PROBE;
		}

		/**
		 * While a widget draws: the store's wording it replaces ( 'section|key' => plain text ).
		 *
		 * @param array $texts Replacements.
		 */
		public static function push_texts( $texts ) {
			self::$texts[] = is_array( $texts ) ? $texts : array();
			if ( 1 === count( self::$texts ) ) {
				add_filter( 'wp_easycart_language_text', array( __CLASS__, 'language_text' ), 20, 3 );
			}
		}

		/**
		 * Undoes push_texts().
		 */
		public static function pop_texts() {
			array_pop( self::$texts );
			if ( empty( self::$texts ) ) {
				remove_filter( 'wp_easycart_language_text', array( __CLASS__, 'language_text' ), 20 );
			}
		}

		/**
		 * Filter wp_easycart_language_text: the drawing widget's own wording, escaped like the store's ( the templates
		 * print get_text() as it is, in text and in attribute values ).
		 *
		 * @param string|null $text    The store's wording.
		 * @param string      $section Language section.
		 * @param string      $key     Language key.
		 * @return string|null
		 */
		public static function language_text( $text, $section = '', $key = '' ) {
			$context = end( self::$texts );
			$id      = (string) $section . '|' . (string) $key;
			if ( is_array( $context ) && isset( $context[ $id ] ) && is_string( $context[ $id ] ) && '' !== trim( $context[ $id ] ) ) {
				return esc_html( trim( $context[ $id ] ) );
			}
			return $text;
		}

		/**
		 * Classes and attributes the widgets' Style and Content settings need on EasyCart's markup ( only added, never
		 * changed ): the required * after a label in its own span, each order's status in a span in the order list, and
		 * placeholders on the fields a widget names. Markup a theme's copy of a template draws differently is left as it is.
		 *
		 * @param string $html         Markup.
		 * @param array  $placeholders Field id => placeholder text.
		 * @return string
		 */
		public static function decorate( $html, $placeholders = array() ) {
			$html = (string) preg_replace( '/\*(\s*)<\/label>/', '<span class="wpec-acc-required">*</span>$1</label>', $html );
			$html = (string) preg_replace_callback(
				'/(<div class="ec_account_order_line_column4">)(\s*)(.*?)(\s*)(<\/div>)/s',
				array( __CLASS__, 'wrap_status' ),
				$html
			);
			if ( ! empty( $placeholders ) && is_array( $placeholders ) ) {
				$html = (string) preg_replace_callback(
					'/<input\b[^>]*>/i',
					function ( $found ) use ( $placeholders ) {
						return self::add_placeholder( $found[0], $placeholders );
					},
					$html
				);
			}
			return $html;
		}

		/**
		 * An order status cell's text in a span ( decorate() ).
		 *
		 * @param array $found Regex match.
		 * @return string
		 */
		private static function wrap_status( $found ) {
			if ( '' === trim( $found[3] ) || false !== strpos( $found[3], '<div' ) ) {
				return $found[0];
			}
			return $found[1] . $found[2] . '<span class="wpec-acc-status">' . $found[3] . '</span>' . $found[4] . $found[5];
		}

		/**
		 * A placeholder on an input tag whose id is listed and that has none ( decorate() ).
		 *
		 * @param string $tag          Input tag.
		 * @param array  $placeholders Field id => text.
		 * @return string
		 */
		private static function add_placeholder( $tag, $placeholders ) {
			if ( ! preg_match( '/\bid\s*=\s*(["\'])([^"\']+)\1/i', $tag, $id ) || ! isset( $placeholders[ $id[2] ] ) || preg_match( '/\bplaceholder\s*=/i', $tag ) ) {
				return $tag;
			}
			$end = ( '/>' === substr( $tag, -2 ) ) ? strlen( $tag ) - 2 : strlen( $tag ) - 1;
			return rtrim( substr( $tag, 0, $end ) ) . ' placeholder="' . esc_attr( $placeholders[ $id[2] ] ) . '"' . substr( $tag, $end );
		}

		/**
		 * Whether a customer is signed in to the store.
		 *
		 * @return bool
		 */
		public static function signed_in() {
			if ( function_exists( 'wp_easycart_elementor_account_signed_in' ) ) {
				return wp_easycart_elementor_account_signed_in();
			}
			return isset( $GLOBALS['ec_cart_data']->cart_data->user_id ) && (int) $GLOBALS['ec_cart_data']->cart_data->user_id > 0;
		}

		/**
		 * Whether this request draws Elementor's editor or its preview.
		 *
		 * @return bool
		 */
		public static function is_editor() {
			return function_exists( 'wp_easycart_elementor_is_editor' ) && wp_easycart_elementor_is_editor();
		}

		/**
		 * The page this request shows, without the store's account arguments: where the widgets' links and forms lead.
		 *
		 * @return string
		 */
		public static function here_url() {
			$url     = '';
			$queried = get_queried_object();
			if ( $queried instanceof WP_Post ) {
				$url = (string) get_permalink( $queried );
			}
			if ( '' === $url && ( wp_doing_ajax() || ! did_action( 'wp' ) ) ) {
				$post_id = (int) get_the_ID();
				if ( $post_id ) {
					$url = (string) get_permalink( $post_id );
				}
			}
			if ( '' === $url ) {
				/* The site's own host ( never the request's Host header ), with this request's path and its other arguments. */
				$home   = wp_parse_url( home_url( '/' ) );
				$scheme = is_ssl() ? 'https' : ( isset( $home['scheme'] ) ? $home['scheme'] : 'http' );
				$host   = isset( $home['host'] ) ? $home['host'] : '';
				$port   = isset( $home['port'] ) ? ':' . (int) $home['port'] : '';
				$uri    = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
				if ( '' === $uri || '/' !== substr( $uri, 0, 1 ) ) {
					$uri = '/';
				}
				/* esc_url_raw() keeps ' ( and ): encoded, so a link built from the address is safe inside any quoted script. */
				$uri = str_replace( array( "'", '(', ')' ), array( '%27', '%28', '%29' ), $uri );
				$url = $scheme . '://' . $host . $port . $uri;
			}
			return remove_query_arg( self::request_args(), $url );
		}

		/**
		 * Query arguments of the store's account pages ( removed from the here URL ).
		 *
		 * @return array
		 */
		private static function request_args() {
			return array( 'ec_page', 'order_id', 'orderdetail_id', 'download_id', 'ec_guest_key', 'guest_key', 'subscription_id', 'account_success', 'account_error', 'errcode', 'ec_reset_key', 'email', 'key', self::MARKER, 'wpec_logout_to' );
		}

		/**
		 * URL of a view on a page.
		 *
		 * @param string $view View key.
		 * @param string $base Page URL ( default: here ).
		 * @return string
		 */
		public static function view_url( $view, $base = '' ) {
			if ( '' === $base ) {
				$base = self::here_url();
			}
			return $base . ( ( false === strpos( $base, '?' ) ) ? '?' : '&' ) . 'ec_page=' . rawurlencode( $view );
		}

		/**
		 * Where a view opens from a widget with these views: this page when it can show the view, else the store's account
		 * page.
		 *
		 * @param string $view  View key ( a link key, or downloads ).
		 * @param array  $views Views of the widget asking ( merged with the page's ).
		 * @return string
		 */
		public static function link_to( $view, $views = array() ) {
			$views = array_merge( (array) $views, self::page_views() );
			if ( in_array( 'all', $views, true ) || in_array( $view, $views, true ) ) {
				return self::view_url( $view );
			}
			if ( 'downloads' === $view ) {
				return wpeasycart_links()->get_account_page( 'dashboard' );
			}
			return wpeasycart_links()->get_account_page( $view );
		}

		/**
		 * The sign-out link for a widget.
		 *
		 * @param array  $views Views of the widget.
		 * @param string $to    After signing out: '' ( the sign-in page ), 'page' ( this page ) or 'home'.
		 * @return string
		 */
		public static function logout_url( $views = array(), $to = '' ) {
			$args = array(
				'ec_page'    => 'logout',
				self::MARKER => implode( ',', self::sanitize_views( $views ) ),
			);
			if ( in_array( $to, array( 'page', 'home' ), true ) ) {
				$args['wpec_logout_to'] = $to;
			}
			if ( '' === $args[ self::MARKER ] ) {
				$args[ self::MARKER ] = 'dashboard';
			}
			return add_query_arg( $args, self::here_url() );
		}

		/*
		 * ---------------------------------------------------------------------------------------------------------------
		 * Page detection.
		 * ---------------------------------------------------------------------------------------------------------------
		 */

		/**
		 * Account widget names on this request's page ( memoised once the query ran ).
		 *
		 * @return array
		 */
		public static function page_widgets() {
			if ( null !== self::$page_widgets ) {
				return self::$page_widgets;
			}
			$found = array();
			if ( is_admin() || ! did_action( 'elementor/loaded' ) ) {
				/* wp-admin and the editor's AJAX redraws have no page of their own. */
				if ( is_admin() || did_action( 'wp' ) ) {
					self::$page_widgets = $found;
				}
				return $found;
			}
			$ids     = array();
			$queried = get_queried_object();
			if ( $queried instanceof WP_Post && 'builder' === get_post_meta( $queried->ID, '_elementor_edit_mode', true ) ) {
				$ids[] = (int) $queried->ID;
			}
			$ids  = array_merge( $ids, self::theme_document_ids() );
			$seen = array();
			foreach ( array_unique( $ids ) as $id ) {
				self::scan_document( $id, $found, $seen, 0 );
			}
			$found = array_values( array_unique( $found ) );
			if ( did_action( 'wp' ) ) {
				self::$page_widgets = $found;
			}
			return $found;
		}

		/**
		 * Views the account widgets on this page can show.
		 *
		 * @return array
		 */
		public static function page_views() {
			$widgets = self::widgets();
			$views   = array();
			foreach ( self::page_widgets() as $name ) {
				if ( isset( $widgets[ $name ] ) ) {
					$views = array_merge( $views, $widgets[ $name ]['views'] );
				}
			}
			return array_values( array_unique( $views ) );
		}

		/**
		 * Whether an account widget of this name is on the page.
		 *
		 * @param string $name Widget name.
		 * @return bool
		 */
		public static function page_has( $name ) {
			return in_array( $name, self::page_widgets(), true );
		}

		/**
		 * Collects account widget names from one Elementor document and the templates it embeds.
		 *
		 * @param int   $post_id Document id.
		 * @param array $found   Names found ( by reference ).
		 * @param array $seen    Documents read ( by reference ).
		 * @param int   $depth   Embedding depth.
		 */
		private static function scan_document( $post_id, &$found, &$seen, $depth ) {
			$post_id = (int) $post_id;
			if ( $post_id <= 0 || isset( $seen[ $post_id ] ) || $depth > 2 ) {
				return;
			}
			$seen[ $post_id ] = true;
			$data             = get_post_meta( $post_id, '_elementor_data', true );
			if ( is_array( $data ) ) {
				$data = wp_json_encode( $data );
			}
			if ( ! is_string( $data ) || '' === $data ) {
				return;
			}
			if ( preg_match_all( '/"widgetType"\s*:\s*"(wp_easycart_my_account[a-z_]*)"/', $data, $matches ) ) {
				foreach ( $matches[1] as $name ) {
					$found[] = $name;
				}
			}

			/*
			 * Saved templates it embeds: the Template widget, global widgets, [elementor-template id="…"] and popups a
			 * button's "Open popup" action points to ( an encoded dynamic tag ).
			 */
			$refs = array();
			if ( preg_match_all( '/%22popup%22%3A%22(\d+)%22/i', $data, $matches ) ) {
				$refs = array_merge( $refs, $matches[1] );
			}
			if ( preg_match_all( '/"(?:templateID|template_id)"\s*:\s*"?(\d+)"?/', $data, $matches ) ) {
				$refs = array_merge( $refs, $matches[1] );
			}
			if ( preg_match_all( '/elementor-template\s+id=\\\\?["\']?(\d+)/', $data, $matches ) ) {
				$refs = array_merge( $refs, $matches[1] );
			}
			foreach ( array_unique( $refs ) as $ref ) {
				self::scan_document( (int) $ref, $found, $seen, $depth + 1 );
			}
		}

		/**
		 * Elementor Pro Theme Builder documents this request uses ( header, footer, single, archive, popup ).
		 *
		 * @return array Post ids.
		 */
		private static function theme_document_ids() {
			$ids = array();
			if ( ! class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ) ) {
				return $ids;
			}
			try {
				$module = \ElementorPro\Modules\ThemeBuilder\Module::instance();
				if ( ! is_object( $module ) || ! method_exists( $module, 'get_conditions_manager' ) ) {
					return $ids;
				}
				$manager = $module->get_conditions_manager();
				if ( ! is_object( $manager ) || ! method_exists( $manager, 'get_documents_for_location' ) ) {
					return $ids;
				}
				foreach ( array( 'header', 'footer', 'single', 'archive', 'popup' ) as $location ) {
					$documents = $manager->get_documents_for_location( $location );
					if ( ! is_array( $documents ) ) {
						continue;
					}
					foreach ( $documents as $key => $document ) {
						if ( is_object( $document ) && method_exists( $document, 'get_main_id' ) ) {
							$ids[] = (int) $document->get_main_id();
						} elseif ( is_numeric( $key ) ) {
							$ids[] = (int) $key;
						}
					}
				}
			} catch ( Throwable $e ) {
				unset( $e ); /* Elementor Pro changed: the widgets still set DONOTCACHEPAGE when they draw. */
			}
			return $ids;
		}

		/**
		 * Widgets whose form a visitor gets ( its nonce must be fresh, so the page is never cached ).
		 *
		 * @return array
		 */
		private static function visitor_form_widgets() {
			return array( 'wp_easycart_my_account', 'wp_easycart_my_account_login', 'wp_easycart_my_account_register', 'wp_easycart_my_account_lost_password' );
		}

		/**
		 * Whether the page's own document ( and the templates it embeds ), not a site-wide header, footer or popup, holds an
		 * account widget.
		 *
		 * @return bool
		 */
		private static function own_page_has_widgets() {
			$queried = get_queried_object();
			if ( ! ( $queried instanceof WP_Post ) || 'builder' !== get_post_meta( $queried->ID, '_elementor_edit_mode', true ) ) {
				return false;
			}
			$found = array();
			$seen  = array();
			self::scan_document( (int) $queried->ID, $found, $seen, 0 );
			return ! empty( $found );
		}

		/**
		 * Page caching and headers ( template_redirect, before EasyCart's store template can end the request ).
		 *
		 * - A signed-in customer's pages are never cached, whatever they hold: an account or cart widget can reach them in
		 *   ways no scan sees ( a popup a button opens, another header builder, a template shortcode, a loop item ).
		 * - For visitors only pages with a form they can use ( sign in, sign up, lost password, My Account ) or a guest order
		 *   opened by its link skip the cache. A menu or sign-out button in a site-wide header leaves caching on for them; such a
		 *   form in a site-wide header, footer or popup counts like one on the page ( fresh nonces ), so it keeps every page out
		 *   of the cache ( kept for 6.0.2; Settings › Elementor › Account pages says so ).
		 * - Pages with account widgets are never framed ( not in the editor, whose preview is a frame ).
		 * - A signed-in customer who can change a subscription's card gets Stripe ( in the head: the card form's inline
		 *   script calls it ) and checkout protection's script on pages with My Account or Subscriptions.
		 */
		public static function page_headers() {
			if ( is_admin() ) {
				return;
			}
			$signed_in = self::signed_in();
			$widgets   = self::page_widgets();
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read only: whether a guest's order link opened this page.
			$guest_order = in_array( 'wp_easycart_my_account_order_details', $widgets, true ) && isset( $_GET['order_id'] );
			$visitor     = (bool) array_intersect( self::visitor_form_widgets(), $widgets ) || $guest_order;
			/* A page whose own content holds account widgets ( an Orders page without a sign-in form ) is empty for a visitor:
			 * a cache that kept that copy would serve it to signed-in customers, whose store login no page cache knows. */
			$visitor = $visitor || self::own_page_has_widgets();
			if ( ! $signed_in && ! $visitor ) {
				return;
			}
			self::no_cache();
			if ( ! headers_sent() ) {
				nocache_headers();
				if ( ! empty( $widgets ) && ! self::is_editor() ) {
					header( 'X-Frame-Options: SAMEORIGIN' );
				}
			}
			if ( $signed_in && self::uses_subscriptions() && array_intersect( array( 'wp_easycart_my_account', 'wp_easycart_my_account_subscriptions' ), $widgets ) ) {
				add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_card_scripts' ), 20 );
			}
		}

		/**
		 * Stripe and checkout protection for a subscription's card form ( wp_enqueue_scripts, after register_assets() ).
		 */
		public static function enqueue_card_scripts() {
			if ( wp_script_is( 'wpeasycart_stripe_js', 'registered' ) ) {
				wp_enqueue_script( 'wpeasycart_stripe_js' );
			}
			if ( class_exists( 'wp_easycart_checkout_guard' ) && method_exists( 'wp_easycart_checkout_guard', 'enqueue' ) ) {
				wp_easycart_checkout_guard::enqueue();
			}
		}

		/**
		 * Tells page caches to skip this page ( the widgets print the customer's details and nonces ).
		 */
		public static function no_cache() {
			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- the page cache plugins' own constant.
			}
			if ( ! defined( 'DONOTCDN' ) ) {
				define( 'DONOTCDN', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- the page cache plugins' own constant.
			}
		}

		/*
		 * ---------------------------------------------------------------------------------------------------------------
		 * Links.
		 * ---------------------------------------------------------------------------------------------------------------
		 */

		/**
		 * Starts routing the store's account links to this page for these views.
		 *
		 * @param array $views      Views ( 'all' for every one ).
		 * @param bool  $merge_page Add the views of the page's account widgets ( route_request() merges them itself ).
		 */
		public static function push_links( $views, $merge_page = true ) {
			$views         = self::sanitize_views( $views );
			self::$links[] = array(
				'here'  => self::here_url(),
				'views' => array_values( array_unique( $merge_page ? array_merge( $views, self::page_views() ) : $views ) ),
			);
			if ( 1 === count( self::$links ) ) {
				add_filter( 'wp_easycart_account_link', array( __CLASS__, 'route_link' ), 1 );
				foreach ( self::link_keys() as $key ) {
					add_filter( 'wp_easycart_account_' . $key . '_link', array( __CLASS__, 'route_link' ), 1 );
				}
			}
		}

		/**
		 * Undoes push_links().
		 */
		public static function pop_links() {
			array_pop( self::$links );
			if ( empty( self::$links ) ) {
				remove_filter( 'wp_easycart_account_link', array( __CLASS__, 'route_link' ), 1 );
				foreach ( self::link_keys() as $key ) {
					remove_filter( 'wp_easycart_account_' . $key . '_link', array( __CLASS__, 'route_link' ), 1 );
				}
			}
		}

		/**
		 * Filter for wp_easycart_account_link and wp_easycart_account_{key}_link.
		 *
		 * @param string $url The store's link.
		 * @return string
		 */
		public static function route_link( $url ) {
			$context = end( self::$links );
			if ( ! $context ) {
				return $url;
			}
			$filter = current_filter();
			if ( 'wp_easycart_account_link' === $filter ) {
				return $context['here'];
			}
			$key = substr( $filter, strlen( 'wp_easycart_account_' ), -strlen( '_link' ) );
			if ( 'logout' === $key ) {
				return add_query_arg(
					array(
						'ec_page'    => 'logout',
						self::MARKER => implode( ',', $context['views'] ),
					),
					$context['here']
				);
			}
			if ( in_array( 'all', $context['views'], true ) || in_array( $key, $context['views'], true ) ) {
				return self::view_url( $key, $context['here'] );
			}
			return $url;
		}

		/**
		 * A form an account widget drew, or its sign-out link, is being processed ( wp, before load_ec_pre() ): its
		 * redirects come back to this page for the views it and the page show.
		 */
		public static function route_request() {
			// phpcs:disable WordPress.Security.NonceVerification -- read-only routing: which page the store's own handler redirects to; ec_accountpage verifies the form's nonce.
			$posted = isset( $_POST['ec_account_form_action'], $_POST[ self::MARKER ] );
			$logout = isset( $_GET['ec_page'], $_GET[ self::MARKER ] ) && 'logout' === sanitize_key( wp_unslash( $_GET['ec_page'] ) );
			if ( ! $posted && ! $logout ) {
				return;
			}
			$raw   = $posted ? sanitize_text_field( wp_unslash( $_POST[ self::MARKER ] ) ) : sanitize_text_field( wp_unslash( $_GET[ self::MARKER ] ) );
			$views = self::trusted_views( array_merge( self::sanitize_views( $raw ), self::page_views() ) );
			self::push_links( $views, false );
			if ( $logout ) {
				$to = isset( $_GET['wpec_logout_to'] ) ? sanitize_key( wp_unslash( $_GET['wpec_logout_to'] ) ) : '';
				if ( 'page' === $to ) {
					add_filter( 'wp_easycart_account_logout_redirect_url', array( __CLASS__, 'logout_to_page' ), 20 );
				} elseif ( 'home' === $to ) {
					add_filter( 'wp_easycart_account_logout_redirect_url', array( __CLASS__, 'logout_to_home' ), 20 );
				}
			}
			// phpcs:enable WordPress.Security.NonceVerification
		}

		/**
		 * The posted views this request may route here. Every view but two only changes where a redirect lands on this
		 * same site; 'reset_password' ( and 'all', which holds it ) also decides where the emailed reset link points, so
		 * they are kept only on a post whose account widgets really show them ( anyone can post the lost-password form, so
		 * a reset email must never point at an arbitrary path or query ).
		 *
		 * @param array $views Posted views ( sanitized ).
		 * @return array
		 */
		private static function trusted_views( $views ) {
			$page  = self::page_views();
			$post  = ( get_queried_object() instanceof WP_Post );
			$trust = array();
			foreach ( $views as $view ) {
				if ( 'all' === $view || 'reset_password' === $view ) {
					if ( ! $post || ! ( in_array( 'all', $page, true ) || in_array( $view, $page, true ) ) ) {
						continue;
					}
				}
				$trust[] = $view;
			}
			return $trust;
		}

		/**
		 * After signing out: this page.
		 *
		 * @return string
		 */
		public static function logout_to_page() {
			return self::here_url();
		}

		/**
		 * After signing out: the home page.
		 *
		 * @return string
		 */
		public static function logout_to_home() {
			return home_url( '/' );
		}

		/*
		 * ---------------------------------------------------------------------------------------------------------------
		 * Rendering.
		 * ---------------------------------------------------------------------------------------------------------------
		 */

		/**
		 * The account page every widget on the page draws with ( built once ).
		 *
		 * @param bool $sample The editor's sample customer.
		 * @return WP_EasyCart_Elementor_Account_Page|null
		 */
		public static function account_page( $sample = false ) {
			if ( ! class_exists( 'ec_accountpage' ) ) {
				return null;
			}
			if ( ! class_exists( 'WP_EasyCart_Elementor_Account_Page' ) ) {
				require_once __DIR__ . '/class-wp-easycart-elementor-account-page.php';
			}
			if ( $sample ) {
				if ( null === self::$sample_page ) {
					self::$sample_page = new WP_EasyCart_Elementor_Account_Page();
					self::$sample_page->wpec_use_samples( self::samples(), true );
				}
				return self::$sample_page;
			}
			if ( null === self::$account_page ) {
				self::$account_page = new WP_EasyCart_Elementor_Account_Page();
				if ( self::is_editor() && self::signed_in() ) {
					/* The editing admin's own account, with sample orders, subscriptions or downloads where it has none. */
					self::$account_page->wpec_use_samples( self::samples(), false );
				}
			}
			return self::$account_page;
		}

		/**
		 * The editor's sample data.
		 *
		 * @return WP_EasyCart_Elementor_Account_Samples
		 */
		public static function samples() {
			static $samples = null;
			if ( null === $samples ) {
				if ( ! class_exists( 'WP_EasyCart_Elementor_Account_Samples' ) ) {
					require_once __DIR__ . '/class-wp-easycart-elementor-account-samples.php';
				}
				if ( ! class_exists( 'WP_EasyCart_Elementor_Account_Sample_DB' ) ) {
					require_once __DIR__ . '/class-wp-easycart-elementor-account-sample-db.php';
				}
				$samples = new WP_EasyCart_Elementor_Account_Samples();
			}
			return $samples;
		}

		/**
		 * The widget's Show to setting, with its default.
		 *
		 * @param array  $settings Widget settings.
		 * @param string $audience Default audience.
		 * @return string always | logged_in | logged_out
		 */
		public static function visibility( $settings, $audience ) {
			$value = ( is_array( $settings ) && isset( $settings['visibility'] ) && is_string( $settings['visibility'] ) ) ? $settings['visibility'] : $audience;
			return in_array( $value, array( 'always', 'logged_in', 'logged_out' ), true ) ? $value : $audience;
		}

		/**
		 * Draws one account widget.
		 *
		 * Arguments: name ( widget name ), settings ( for display ), audience ( default of Show to ), views ( the views the
		 * widget shows, for routing ), marker ( views its forms carry, default the views ), customer ( draws a signed-in
		 * customer's data: the editor then shows a sample customer ), messages ( prints the page's account message when it
		 * belongs to one of its views ), strip ( strip_blocks() rules ), classes ( extra wrapper classes ), note ( editor note,
		 * '' = the audience ), force ( draw whatever Show to says: the emailed password reset link ), empty_note ( editor note
		 * when the widget has nothing to show ), needs_page ( false for menus and the sign-out button: no account page is
		 * built ), texts ( the store's wording the widget replaces while it draws, 'section|key' => text; only when
		 * language_filter_ready() ), placeholders ( field id => placeholder, see decorate() ), render ( function(
		 * $account_page, $context ) drawing the body, false when it has nothing ).
		 *
		 * @param array $args Arguments ( see above ).
		 */
		public static function render( $args ) {
			$args       = wp_parse_args(
				$args,
				array(
					'name'         => '',
					'settings'     => array(),
					'audience'     => 'logged_in',
					'views'        => array(),
					'marker'       => null,
					'customer'     => false,
					'messages'     => true,
					'strip'        => array(),
					'classes'      => array(),
					'note'         => '',
					'force'        => false,
					'empty_note'   => '',
					'needs_page'   => true,
					'texts'        => array(),
					'placeholders' => array(),
					'render'       => null,
				)
			);
			$editor     = self::is_editor();
			$signed_in  = self::signed_in();
			$visibility = self::visibility( $args['settings'], $args['audience'] );
			$shown      = ( 'always' === $visibility ) || ( ( 'logged_in' === $visibility ) === $signed_in ) || $args['force'];
			if ( ! $shown && ! $editor ) {
				return;
			}
			/* A customer's own pages ( orders, addresses, downloads … ) never draw for a visitor, whatever Show to says. */
			if ( $args['customer'] && ! $signed_in && ! $editor ) {
				return;
			}
			self::no_cache();

			$context             = array(
				'editor'     => $editor,
				'signed_in'  => $signed_in,
				'visibility' => $visibility,
				'settings'   => $args['settings'],
				'sample'     => ( $editor && ! $signed_in && $args['customer'] ),
				'views'      => self::sanitize_views( $args['views'] ),
			);
			$context['customer'] = $args['customer'] && ( $signed_in || $context['sample'] );
			$marker              = self::sanitize_views( null === $args['marker'] ? $args['views'] : $args['marker'] );

			self::begin( $context['views'], $context['sample'] );
			$texts = ( ! empty( $args['texts'] ) && is_array( $args['texts'] ) && self::language_filter_ready() ) ? $args['texts'] : array();
			if ( $texts ) {
				self::push_texts( $texts );
			}
			ob_start();
			$result         = false;
			$printed_before = self::$message_printed;
			try {
				/* Menus and the sign-out button draw without the account page ( it reads the customer's orders ). */
				$page = $args['needs_page'] ? self::account_page( $context['sample'] ) : null;
				if ( ( $page || ! $args['needs_page'] ) && is_callable( $args['render'] ) ) {
					if ( $page && $args['messages'] ) {
						self::print_message( $page, $context['views'] );
					}
					$result = call_user_func( $args['render'], $page, $context );
				}
			} catch ( Throwable $e ) {
				unset( $e ); /* a view that cannot draw prints nothing rather than a broken page */
				$result = false;
			}
			$html = (string) ob_get_clean();
			if ( $texts ) {
				self::pop_texts();
			}
			self::end( $context['sample'] );

			if ( false === $result || '' === trim( $html ) ) {
				if ( $editor ) {
					self::open_wrapper( $args, $context );
					self::editor_note( $args, $context, true );
					echo '</div>';
				}
				return;
			}
			$html = self::strip_blocks( $html, $args['strip'] );
			$html = self::add_marker( $html, $marker );
			$html = self::decorate( $html, is_array( $args['placeholders'] ) ? $args['placeholders'] : array() );

			/* Menus and the sign-out button ( no account page ) never answer for a view: they do not show it. */
			self::open_wrapper( $args, $context, ( $editor || $context['sample'] ) ? '' : self::answer( $args['needs_page'] ? $context['views'] : array(), ! $printed_before && self::$message_printed ) );
			if ( $editor ) {
				self::editor_note( $args, $context, false );
			}
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- EasyCart's own account templates and the module templates, which escape what they print.
			echo '</div>';
		}

		/**
		 * Whether a widget answers this request: it printed the request's message ( 'message' ), or it shows the view the link
		 * asked for, such as the password reset form ( 'view' ). account.js opens the Elementor Pro popup that holds it.
		 *
		 * @since 6.0.3
		 *
		 * @param array $views   The widget's views ( 'all' = every one ).
		 * @param bool  $message The widget printed the message.
		 * @return string '' | 'message' | 'view'
		 */
		public static function answer( $views, $message ) {
			if ( $message ) {
				return 'message';
			}
			$requested = class_exists( 'WP_EasyCart_Elementor_Account_Views' ) ? WP_EasyCart_Elementor_Account_Views::requested_view() : '';
			if ( '' === $requested || 'logout' === $requested ) {
				return '';
			}
			$views = (array) $views;
			return ( in_array( 'all', $views, true ) || in_array( $requested, $views, true ) ) ? 'view' : '';
		}

		/**
		 * The widget's wrapper.
		 *
		 * @param array  $args    render() arguments.
		 * @param array  $context Render context.
		 * @param string $answer  answer() ( 6.0.3 ).
		 */
		private static function open_wrapper( $args, $context, $answer = '' ) {
			$slug    = str_replace( '_', '-', preg_replace( '/^wp_easycart_my_account_?/', '', (string) $args['name'] ) );
			$classes = array_merge( array( 'wpec-el', 'wpec-acc', 'wpec-acc--' . ( '' === $slug ? 'my-account' : $slug ) ), (array) $args['classes'] );
			if ( $context['sample'] ) {
				$classes[] = 'wpec-acc--sample';
			}
			echo '<div class="' . esc_attr( implode( ' ', array_map( 'sanitize_html_class', $classes ) ) ) . '"' . ( '' !== $answer ? ' data-wpec-acc-answer="' . esc_attr( $answer ) . '"' : '' ) . '>';
		}

		/**
		 * The editor's one-line note: who sees the widget, and when it shows sample data.
		 *
		 * @param array $args    render() arguments.
		 * @param array $context Render context.
		 * @param bool  $nothing The widget has nothing to show.
		 */
		private static function editor_note( $args, $context, $nothing ) {
			$audience = array(
				'always'     => __( 'Shown to everyone.', 'wp-easycart' ),
				'logged_in'  => __( 'Shown to signed-in customers.', 'wp-easycart' ),
				'logged_out' => __( 'Shown to visitors who are not signed in.', 'wp-easycart' ),
			);
			$message  = ( '' !== $args['note'] ) ? $args['note'] : $audience[ $context['visibility'] ];
			$action   = '';
			if ( $context['sample'] ) {
				$action = __( 'You are not signed in to a store account, so this preview uses a sample customer.', 'wp-easycart' );
			}
			if ( $nothing ) {
				$action = ( '' !== $args['empty_note'] ) ? $args['empty_note'] : __( 'Nothing to show here yet: customers see nothing in its place.', 'wp-easycart' );
			}
			echo '<div class="wpec-el-notice wpec-acc__editor-note" role="note"><strong>' . esc_html( $message ) . '</strong>';
			if ( '' !== $action ) {
				echo '<span>' . esc_html( $action ) . '</span>';
			}
			echo '</div>';
		}

		/**
		 * Before a widget draws: its links, the templates' server-side mode, and the sample customer.
		 *
		 * @param array $views  Widget views.
		 * @param bool  $sample Sample customer.
		 */
		private static function begin( $views, $sample ) {
			self::push_links( $views );

			/*
			 * The templates print inline scripts on cache-prevent stores ( they arrive by AJAX there ): drawn on the page,
			 * reCAPTCHA would render before Google's script loads. ec-store.js binds the same things on page load.
			 */
			add_filter( 'pre_option_ec_option_cache_prevent', array( __CLASS__, 'cache_prevent_off' ) );
			if ( $sample && isset( $GLOBALS['ec_user'] ) ) {
				self::$saved_user   = $GLOBALS['ec_user'];
				$GLOBALS['ec_user'] = self::samples()->user( self::$saved_user ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- EasyCart's own customer global: the editor's sample customer, put back in end().
			}
		}

		/**
		 * Undoes begin().
		 *
		 * @param bool $sample Sample customer.
		 */
		private static function end( $sample ) {
			if ( $sample && null !== self::$saved_user ) {
				$GLOBALS['ec_user'] = self::$saved_user; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- EasyCart's own customer global: the shopper's ec_user back.
				self::$saved_user   = null;
			}
			remove_filter( 'pre_option_ec_option_cache_prevent', array( __CLASS__, 'cache_prevent_off' ) );
			self::pop_links();
		}

		/**
		 * Filter: the store's cache-prevent mode reads off while a widget draws.
		 *
		 * @return string
		 */
		public static function cache_prevent_off() {
			return '0';
		}

		/**
		 * Removes blocks from template markup.
		 *
		 * @param string $html  Markup.
		 * @param array  $rules Each array( 'has' => class or classes, 'not' => class ): a div or section whose class list
		 *                      holds every 'has' class ( and not 'not' ), removed with everything inside it.
		 * @return string
		 */
		public static function strip_blocks( $html, $rules ) {
			foreach ( (array) $rules as $rule ) {
				$has    = isset( $rule['has'] ) ? array_filter( (array) $rule['has'], 'strlen' ) : array();
				$not    = isset( $rule['not'] ) ? (string) $rule['not'] : '';
				$offset = 0;
				if ( empty( $has ) ) {
					continue;
				}
				while ( preg_match( '/<(div|section)\b[^>]*\bclass\s*=\s*(["\'])([^"\']*)\2[^>]*>/i', $html, $open, PREG_OFFSET_CAPTURE, $offset ) ) {
					$start   = $open[0][1];
					$after   = $start + strlen( $open[0][0] );
					$classes = preg_split( '/\s+/', trim( $open[3][0] ) );
					if ( count( array_intersect( $has, $classes ) ) !== count( $has ) || ( '' !== $not && in_array( $not, $classes, true ) ) ) {
						$offset = $after;
						continue;
					}
					$end = self::block_end( $html, strtolower( $open[1][0] ), $after );
					if ( false === $end ) {
						break;
					}
					$html   = substr( $html, 0, $start ) . substr( $html, $end );
					$offset = $start;
				}
			}
			return $html;
		}

		/**
		 * End offset of the element opened just before $from.
		 *
		 * @param string $html Markup.
		 * @param string $tag  div | section.
		 * @param int    $from Offset after the opening tag.
		 * @return int|false
		 */
		private static function block_end( $html, $tag, $from ) {
			$depth = 1;
			while ( preg_match( '/<(\/?)' . $tag . '\b[^>]*>/i', $html, $match, PREG_OFFSET_CAPTURE, $from ) ) {
				$from   = $match[0][1] + strlen( $match[0][0] );
				$depth += ( '/' === $match[1][0] ) ? -1 : 1;
				if ( 0 === $depth ) {
					return $from;
				}
			}
			return false;
		}

		/**
		 * Adds the widget's views to every form it drew ( see route_request() ).
		 *
		 * @param string $html  Markup.
		 * @param array  $views Views.
		 * @return string
		 */
		public static function add_marker( $html, $views ) {
			if ( empty( $views ) ) {
				return $html;
			}
			$field = '<input type="hidden" name="' . esc_attr( self::MARKER ) . '" value="' . esc_attr( implode( ',', $views ) ) . '" />';
			return preg_replace( '/(<form\b[^>]*>)/i', '$1' . $field, $html );
		}

		/*
		 * ---------------------------------------------------------------------------------------------------------------
		 * Messages.
		 * ---------------------------------------------------------------------------------------------------------------
		 */

		/**
		 * Views an account message belongs to ( first one preferred ).
		 *
		 * @param string $type success | error.
		 * @param string $code Message code.
		 * @return array
		 */
		private static function message_views( $type, $code ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which page the message belongs to, read only.
			$reset = isset( $_GET['ec_page'] ) && 'reset_password' === sanitize_key( wp_unslash( $_GET['ec_page'] ) );
			if ( 'error' === $type ) {
				$map = array(
					'not_activated'                     => array( 'login' ),
					'login_failed'                      => array( 'login' ),
					'register_email_error'              => array( 'register' ),
					'register_invalid'                  => array( 'register' ),
					'no_reset_email_found'              => array( 'forgot_password' ),
					'reset_link_invalid'                => array( 'forgot_password', 'reset_password' ),
					'password_too_short'                => $reset ? array( 'reset_password' ) : array( 'password' ),
					'password_invalid'                  => $reset ? array( 'reset_password' ) : array( 'password' ),
					'password_no_match'                 => $reset ? array( 'reset_password' ) : array( 'password' ),
					'password_wrong_current'            => array( 'password' ),
					'personal_information_update_error' => array( 'personal_information' ),
					'billing_information_error'         => array( 'billing_information' ),
					'shipping_information_error'        => array( 'shipping_information' ),
					'subscription_update_failed'        => array( 'subscription_details', 'subscriptions' ),
					'subscription_cancel_failed'        => array( 'subscription_details', 'subscriptions' ),
					'invalid_order_id'                  => array( 'orders', 'dashboard' ),
					'order_claim_invalid'               => array( 'orders', 'dashboard' ),
					'order_claim_sign_in'               => array( 'login', 'orders' ),
					'order_claim_limit'                 => array( 'orders', 'dashboard' ),
				);
			} else {
				$map = array(
					'validation_required'          => array( 'login', 'register' ),
					'reset_email_sent'             => array( 'forgot_password', 'login' ),
					'password_reset_success'       => array( 'login', 'reset_password', 'forgot_password' ),
					'resend_activation_sent'       => array( 'login' ),
					'activation_success'           => array( 'login' ),
					'personal_information_updated' => array( 'personal_information', 'dashboard' ),
					'billing_information_updated'  => array( 'billing_information', 'dashboard' ),
					'shipping_information_updated' => array( 'shipping_information', 'dashboard' ),
					'password_updated'             => array( 'password', 'dashboard' ),
					'subscription_updated'         => array( 'subscription_details', 'subscriptions' ),
					'subscription_canceled'        => array( 'subscriptions', 'subscription_details' ),
					'cart_account_created'         => array( 'dashboard' ),
					'order_connected'              => array( 'orders', 'dashboard' ),
					'order_claim_sent'             => array( 'orders', 'dashboard' ),
				);
			}
			return isset( $map[ $code ] ) ? $map[ $code ] : array();
		}

		/**
		 * Prints this request's account message once, in the first widget that shows one of its views.
		 *
		 * @param ec_accountpage $page  Account page.
		 * @param array          $views Views of the widget ( 'all' = every one ).
		 */
		public static function print_message( $page, $views ) {
			if ( self::$message_printed ) {
				return;
			}
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- message codes for display; ec_accountpage checks them against its own lists.
			$type = '';
			$code = '';
			if ( isset( $_GET['account_error'] ) ) {
				$type = 'error';
				$code = sanitize_key( wp_unslash( $_GET['account_error'] ) );
			} elseif ( isset( $_GET['account_success'] ) ) {
				$type = 'success';
				$code = sanitize_key( wp_unslash( $_GET['account_success'] ) );
			}
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			if ( '' === $code ) {
				return;
			}
			$belongs = self::message_views( $type, $code );
			if ( ! in_array( 'all', $views, true ) && ! array_intersect( $belongs, $views ) ) {
				return;
			}
			self::$message_printed = true;
			echo '<div class="wpec-acc__messages" role="status" aria-live="polite">';
			if ( 'error' === $type ) {
				$page->display_account_error();
			} else {
				$page->display_account_success();
			}
			echo '</div>';
		}

		/**
		 * The classic account page's top-of-page hook ( wpeasycart_account_top: WordPress User Sync's notices and its
		 * "join your accounts" form ), once per page.
		 *
		 * @param array $context Render context.
		 */
		public static function print_account_top( $context ) {
			static $done = false;
			if ( $done || ! empty( $context['sample'] ) ) {
				return;
			}
			$done = true;
			do_action( 'wpeasycart_account_top' );
		}

		/*
		 * ---------------------------------------------------------------------------------------------------------------
		 * Assets.
		 * ---------------------------------------------------------------------------------------------------------------
		 */

		/**
		 * Registers the widgets' stylesheet and script, and Stripe's script where Stripe takes payments ( the widgets name
		 * them in get_style_depends() / get_script_depends(); Elementor enqueues them only where a widget draws ).
		 */
		public static function register_assets() {
			$base = plugins_url( 'assets/', dirname( __DIR__ ) . '/module.php' );
			if ( ! wp_style_is( 'wpeasycart-elementor-account', 'registered' ) ) {
				wp_register_style( 'wpeasycart-elementor-account', $base . 'account.css', array(), EC_CURRENT_VERSION );
			}
			if ( ! wp_script_is( 'wpeasycart-elementor-account', 'registered' ) ) {
				wp_register_script( 'wpeasycart-elementor-account', $base . 'account.js', array( 'jquery' ), EC_CURRENT_VERSION, true );
			}
			$gateway = get_option( 'ec_option_payment_process_method' );
			if ( ( 'stripe' === $gateway || 'stripe_connect' === $gateway ) && ! wp_script_is( 'wpeasycart_stripe_js', 'registered' ) ) {
				wp_register_script( 'wpeasycart_stripe_js', 'https://js.stripe.com/v3/', array(), EC_CURRENT_VERSION, false ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.NotInFooter -- the same handle and arguments wp_easycart_load_cart_js() uses.
			}
		}

		/*
		 * ---------------------------------------------------------------------------------------------------------------
		 * Store facts the widgets ask.
		 * ---------------------------------------------------------------------------------------------------------------
		 */

		/**
		 * Whether customers manage subscriptions in their account ( Stripe, and the store shows the link ).
		 *
		 * @return bool
		 */
		public static function uses_subscriptions() {
			$gateway = get_option( 'ec_option_payment_process_method' );
			return ( 'stripe' === $gateway || 'stripe_connect' === $gateway ) && (bool) get_option( 'ec_option_show_account_subscriptions_link' );
		}

		/**
		 * Whether the store sells downloads.
		 *
		 * @return bool
		 */
		public static function sells_downloads() {
			static $sells = null;
			if ( null === $sells ) {
				global $wpdb;
				$sells = (bool) $wpdb->get_var( 'SELECT product_id FROM ec_product WHERE is_download = 1 LIMIT 1' );
			}
			return $sells;
		}

		/**
		 * The account page as Settings › Elementor shows it.
		 */
		public static function render_settings_section() {
			$page_id = (int) apply_filters( 'wp_easycart_account_page_id', get_option( 'ec_option_accountpage' ) );
			$post    = $page_id ? get_post( $page_id ) : null;
			echo '<div class="ecst-elementor-account">';
			echo '<p>' . esc_html__( 'Drop the My Account widget on your account page for the whole account area: sign in, orders, subscriptions, addresses, account details, payment methods and downloads, with a menu. Or build your own layout from the separate account widgets (Customer Login, Order History, Addresses and the others). Links between them stay on the page they are on.', 'wp-easycart' ) . '</p>';
			if ( $post ) {
				$edit = get_edit_post_link( $post->ID, 'raw' );
				/* translators: %s: page title. */
				echo '<p>' . esc_html( sprintf( __( 'Your store\'s account page is "%s". Emails, the checkout and the other widgets link there.', 'wp-easycart' ), get_the_title( $post ) ) ) . ' ';
				if ( $edit ) {
					echo '<a href="' . esc_url( $edit ) . '">' . esc_html__( 'Edit the page', 'wp-easycart' ) . '</a>';
				}
				echo '</p>';
			} else {
				/* 6.0.2: the page's real name, with a link ( as the starter pages note ) */
				echo '<p>' . wp_kses_post(
					sprintf(
						/* translators: %s: link to Settings › Store details › Store pages. */
						__( 'Your store has no account page yet. Choose one under %s so emails and the checkout can link to it.', 'wp-easycart' ),
						'<a href="' . esc_url( admin_url( 'admin.php?page=wp-easycart-settings&subpage=initial-setup' ) ) . '">' . esc_html__( 'Settings › Store details › Store pages', 'wp-easycart' ) . '</a>'
					)
				) . '</p>';
			}
			echo '<p>' . esc_html__( 'Page caches never store what a signed-in customer sees, nor pages with account widgets or a sign-in, sign-up or lost-password form. A menu or sign-out button in a site-wide header leaves your pages cached for visitors, but a sign-in, sign-up or lost-password form (or the My Account widget) in a site-wide header, footer or popup turns page caching off on every page. Keep those forms on your account page and link to it instead.', 'wp-easycart' ) . '</p>';
			echo '</div>';
		}
	}

endif;
