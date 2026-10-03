<?php
/**
 * Cart and checkout for Elementor ( 6.0.2 ): widget registration, which widget draws the cart page, assets, the cart state
 * the mini carts read, the order confirmation additions and the helpers the widgets share.
 *
 * Who draws the cart page. EasyCart's cart page is one address for the cart, the checkout steps and the order confirmation
 * ( ec_page / eccheckout in the query ), and its scripts find their parts by id, so its content ( load_ec_cart() ) is drawn
 * once per page. On the cart page:
 *   - the order confirmation belongs to the Order Confirmation widget, else the Checkout widget, else the Cart widget;
 *   - the cart belongs to the Cart widget, else the Checkout widget; with the one-page checkout ( which draws the cart and
 *     every step on one page and moves between them without a page load ) to the Checkout widget, else the Cart widget;
 *   - the checkout steps and every other state ( subscriptions, pay links ... ) belong to the Checkout widget, else the Cart
 *     widget.
 * The others print nothing. A page that also carries the [ec_cart] shortcode leaves it to the shortcode; an [ec_cart] drawn
 * after a widget drew the cart page ( a Theme Builder template around the page's own content ) prints nothing. Widgets under
 * Elementor's display conditions are not counted: when every candidate has one, the first that draws takes the cart page.
 *
 * Settings for requests the page makes later. With cache prevention or the one-page checkout the cart page is drawn by
 * ec_ajax_get_dynamic_cart_page ( and parts of it again by later requests ), where no widget runs. checkout.js sends the
 * saved widget's document and element ids with those requests ( wpec_el_doc, wpec_el_ids ); context_settings() reads that
 * widget's settings from the saved document. Nothing but ids travels, and only published documents ( or ones the visitor
 * may edit ) are read.
 *
 * Round 11: the widgets' own wording ( Texts sections, controls ec_text_{key} of text_keys() ) replaces the store's through
 * the filter wp_easycart_language_text while a widget draws ( push_texts() / pop_texts() ) and in those later requests
 * ( request_texts() ). The sections only register while WP EasyCart has that filter ( language_filter_ready() ). A Cart widget
 * away from the cart page draws once per page ( claim_offpage_cart() ); template_copy_gaps() names older template copies in
 * wp-easycart-data whose markup the widgets' switches need.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Checkout_Module' ) ) :

	/**
	 * Cart and checkout module.
	 */
	class WP_EasyCart_Elementor_Checkout_Module {

		/** Stylesheet handle ( every widget of the module ). */
		const STYLE = 'wpeasycart-elementor-checkout';

		/** Script: the cart page widgets ( empty cart, order summary, request context ). */
		const SCRIPT = 'wpeasycart-elementor-checkout';

		/** Script: the mini cart ( Menu Cart; WP EasyCart PRO's Side Cart builds on it ). */
		const MINI_CART_SCRIPT = 'wpeasycart-elementor-mini-cart';

		/**
		 * The cart page was drawn in this request.
		 *
		 * @var bool
		 */
		private static $flow_rendered = false;

		/**
		 * One of the widgets drew the cart page in this request ( a later [ec_cart] then prints nothing ).
		 *
		 * @var bool
		 */
		private static $widget_rendered = false;

		/**
		 * Documents whose plain content already carries [ec_cart] in this save, keyed by document id.
		 *
		 * @var array
		 */
		private static $plain_cart = array();

		/**
		 * The Elementor document of the widget whose settings apply in this request ( 0 while unknown ).
		 *
		 * @var int
		 */
		private static $context_doc = 0;

		/**
		 * Settings of the widget drawing the cart page in this request, widget name => settings.
		 *
		 * @var array
		 */
		private static $runtime = array();

		/**
		 * Widgets found on a page, keyed by the post ids read.
		 *
		 * @var array
		 */
		private static $page_widgets = array();

		/**
		 * Settings read from a saved document for a later request, widget name => settings or null.
		 *
		 * @var array
		 */
		private static $context = array();

		/**
		 * Settings of the widgets drawing now whose texts replace the store's wording ( round 11 ), innermost last.
		 *
		 * @var array
		 */
		private static $texts = array();

		/**
		 * Whether a later request's widget texts were read ( see request_texts() ).
		 *
		 * @var bool
		 */
		private static $texts_read = false;

		/**
		 * Whether WP EasyCart passes its wording through wp_easycart_language_text ( null until asked ).
		 *
		 * @var bool|null
		 */
		private static $language_filter = null;

		/**
		 * Cart widgets away from the cart page drawn in this request ( EasyCart's cart is id-based: only the first draws ).
		 *
		 * @var int
		 */
		private static $offpage_carts = 0;

		/** Hooks. */
		public static function init() {
			add_filter( 'wp_easycart_elementor_widget_classes', array( __CLASS__, 'widget_classes' ) );
			add_filter( 'wp_easycart_elementor_retired_widgets', array( __CLASS__, 'retired_widgets' ) );
			add_action( 'init', array( __CLASS__, 'register_assets' ), 20 );
			add_action( 'wp', array( __CLASS__, 'maybe_load_cart_scripts' ), 9 ); /* before wpeasycart_prevent_iframe() ( 10 ) reads $is_wpec_cart */
			add_filter( 'pre_do_shortcode_tag', array( __CLASS__, 'note_cart_shortcode' ), 10, 2 );
			add_action( 'elementor/document/before_save', array( __CLASS__, 'reset_plain_cart' ) );
			add_filter( 'wp_easycart_cart_state', array( __CLASS__, 'cart_state' ), 10, 2 );
			add_action( 'wpeasycart_success_page_content_middle', array( __CLASS__, 'confirmation_extras' ), 20, 2 );
			add_filter( 'wp_easycart_settings_page_elementor', array( __CLASS__, 'settings_section' ) );
			add_action( 'wp_ajax_wp_easycart_elementor_cart', array( __CLASS__, 'ajax_cart' ) );
			add_action( 'wp_ajax_nopriv_wp_easycart_elementor_cart', array( __CLASS__, 'ajax_cart' ) );
			add_filter( 'wp_easycart_language_text', array( __CLASS__, 'language_text' ), 10, 3 );
		}

		// Registration.

		/**
		 * Filter wp_easycart_elementor_widget_classes.
		 *
		 * @param array $classes Class name => file.
		 * @return array
		 */
		public static function widget_classes( $classes ) {
			$classes = is_array( $classes ) ? $classes : array();
			$dir     = __DIR__ . '/widgets/';
			$classes['WP_EasyCart_Elementor_Cart_Widget']          = $dir . 'class-wp-easycart-elementor-cart-widget.php';
			$classes['WP_EasyCart_Elementor_Menu_Cart_Widget']     = $dir . 'class-wp-easycart-elementor-menu-cart-widget.php';
			$classes['WP_EasyCart_Elementor_Checkout_Widget']      = $dir . 'class-wp-easycart-elementor-checkout-widget.php';
			$classes['WP_EasyCart_Elementor_Order_Summary_Widget'] = $dir . 'class-wp-easycart-elementor-order-summary-widget.php';
			$classes['WP_EasyCart_Elementor_Thank_You_Widget']     = $dir . 'class-wp-easycart-elementor-thank-you-widget.php';
			$classes['WP_EasyCart_Elementor_Side_Cart_Widget']     = $dir . 'class-wp-easycart-elementor-side-cart-widget.php';
			return $classes;
		}

		/**
		 * Filter wp_easycart_elementor_retired_widgets: the Cart Icon becomes the Menu Cart.
		 *
		 * @param array $retired Legacy name => entry.
		 * @return array
		 */
		public static function retired_widgets( $retired ) {
			$retired                          = is_array( $retired ) ? $retired : array();
			$retired['wp_easycart_cart_icon'] = array(
				'replacement' => 'wp_easycart_menu_cart',
				'title'       => __( 'Menu Cart', 'wp-easycart' ),
				'map'         => array( __CLASS__, 'map_cart_icon' ),
			);
			return $retired;
		}

		/**
		 * Cart Icon settings → Menu Cart settings. Pure: no output, no writes.
		 *
		 * The Cart Icon's defaults are not in its saved settings ( Elementor stores only what differs ), so they are filled in
		 * here where the Menu Cart's own default differs: the red badge, the right alignment and "go to the cart page".
		 *
		 * @param array $old Saved Cart Icon settings.
		 * @return array
		 */
		public static function map_cart_icon( $old ) {
			$old = is_array( $old ) ? $old : array();
			$new = array(
				'ec_click'         => 'link',
				'ec_align'         => 'flex-end',
				'count_color'      => '#ffffff',
				'count_background' => '#dd0000',
			);
			foreach ( $old as $key => $value ) {
				$key = (string) $key;
				if ( '__globals__' === $key || '__dynamic__' === $key ) {
					$inner = array();
					foreach ( (array) $value as $inner_key => $inner_value ) {
						$mapped = self::map_cart_icon_key( (string) $inner_key );
						if ( '' !== $mapped ) {
							$inner[ $mapped ] = $inner_value;
						}
					}
					if ( $inner ) {
						$new[ $key ] = $inner;
					}
					continue;
				}
				if ( 0 === strpos( $key, '_' ) ) {
					$new[ $key ] = $value; /* Advanced tab ( margins, visibility, CSS classes ): the same stack on both widgets. */
					continue;
				}
				if ( 'show_quantity' === $key ) {
					$new['ec_show_count'] = ( 'yes' === $value ) ? 'yes' : '';
					continue;
				}
				if ( 'cart_link' === $key ) {
					/* A link to somewhere other than the cart page stays a link there. */
					if ( is_array( $value ) && ! empty( $value['url'] ) && untrailingslashit( (string) $value['url'] ) !== untrailingslashit( self::cart_url() ) ) {
						$new['ec_link'] = $value;
					}
					continue;
				}
				$mapped = self::map_cart_icon_key( $key );
				if ( '' !== $mapped ) {
					$new[ $mapped ] = $value;
				}
			}
			if ( isset( $new['__dynamic__']['ec_link'] ) ) {
				unset( $new['__dynamic__']['ec_link'] ); /* a dynamic cart link: the Menu Cart goes to the cart page */
			}
			return $new;
		}

		/**
		 * A Cart Icon control id ( with a responsive suffix or group sub-key ) as the Menu Cart's, or '' when it has none.
		 *
		 * @param string $key Cart Icon setting key.
		 * @return string
		 */
		private static function map_cart_icon_key( $key ) {
			$pairs = array(
				'cart_icon'              => 'ec_icon',
				'alignment'              => 'ec_align',
				'icon_color'             => 'icon_color',
				'icon_hover_color'       => 'icon_hover_color',
				'icon_size'              => 'icon_size',
				'quantity_color'         => 'count_color',
				'quantity_bg_color'      => 'count_background',
				'quantity_border_radius' => 'count_radius',
				'quantity_padding'       => 'count_padding',
			);
			$base  = preg_replace( '/_(tablet_extra|mobile_extra|tablet|mobile|widescreen|laptop)$/', '', $key );
			if ( isset( $pairs[ $base ] ) ) {
				return $pairs[ $base ] . substr( $key, strlen( $base ) );
			}
			if ( 0 === strpos( $key, 'quantity_typography_' ) ) {
				return 'count_typography_' . substr( $key, strlen( 'quantity_typography_' ) );
			}
			if ( 0 === strpos( $key, 'quantity_border_' ) && 0 !== strpos( $key, 'quantity_border_radius' ) ) {
				return 'count_border_' . substr( $key, strlen( 'quantity_border_' ) );
			}
			return '';
		}

		/** Styles and scripts ( widgets name them in get_style_depends() / get_script_depends() ). */
		public static function register_assets() {
			$url     = plugins_url( 'assets/', __FILE__ );
			$version = defined( 'EC_CURRENT_VERSION' ) ? EC_CURRENT_VERSION : '6.0.2';
			wp_register_style( self::STYLE, $url . 'checkout.css', array(), $version );
			wp_register_script( self::SCRIPT, $url . 'checkout.js', array( 'jquery' ), $version, true );
			wp_register_script( self::MINI_CART_SCRIPT, $url . 'mini-cart.js', array( 'jquery' ), $version, true );
			$data = array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'text'     => array(
					'remove'   => self::text( 'remove_item', 'Remove [title]' ),
					'close'    => self::text( 'close', 'Close' ),
					'empty'    => self::text( 'cart_empty_cart', 'There are no items in your cart.', 'cart' ),
					'updated'  => self::text( 'cart_updated', 'Your cart was updated.' ),
					'count'    => self::text( 'cart_button_label', 'Cart, [count] items' ),
					'item'     => self::text( 'cart_menu_icon_label', 'Item', 'cart' ),
					'items'    => self::text( 'cart_menu_icon_label_plural', 'Items', 'cart' ),
					'quantity' => self::text( 'quantity_of', 'Quantity of [title]' ),
					'increase' => self::text( 'increase_quantity', 'Increase quantity of [title]' ),
					'decrease' => self::text( 'decrease_quantity', 'Decrease quantity of [title]' ),
					'added'    => self::text( 'item_added', '[title] was added to your cart.' ),
					'error'    => self::text( 'place_order_error', 'Something went wrong. Please check your details and try again.', 'cart_onepage' ),
				),
			);
			wp_localize_script( self::MINI_CART_SCRIPT, 'wpeasycart_elementor_mini_cart', $data );
			wp_localize_script( self::SCRIPT, 'wpeasycart_elementor_checkout', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
		}

		// The cart page.

		/**
		 * The cart page id ( WPML: this language's copy ).
		 *
		 * @return int
		 */
		public static function cart_page_id() {
			$id = (int) get_option( 'ec_option_cartpage' );
			if ( $id ) {
				$id = (int) apply_filters( 'wpml_object_id', $id, 'page', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's own filter ( this language's copy of the page ).
			}
			return $id;
		}

		/**
		 * Is this ( or the current ) page the store's cart page?
		 *
		 * @param int $post_id Post id ( default: the queried page ).
		 * @return bool
		 */
		public static function is_cart_page( $post_id = 0 ) {
			$post_id = $post_id ? (int) $post_id : (int) get_queried_object_id();
			if ( ! $post_id ) {
				return false;
			}
			$cart_page = self::cart_page_id();
			return $cart_page && ( $cart_page === $post_id || (int) get_option( 'ec_option_cartpage' ) === $post_id );
		}

		/**
		 * The cart page's address.
		 *
		 * @return string
		 */
		public static function cart_url() {
			if ( function_exists( 'wpeasycart_links' ) ) {
				return (string) wpeasycart_links()->get_cart_page();
			}
			$id = self::cart_page_id();
			return $id ? (string) get_permalink( $id ) : home_url( '/' );
		}

		/**
		 * Where Checkout buttons go ( the first checkout step ).
		 *
		 * @return string
		 */
		public static function checkout_url() {
			if ( function_exists( 'wpeasycart_links' ) ) {
				return (string) wpeasycart_links()->get_cart_page( 'checkout_info' );
			}
			return self::cart_url();
		}

		/**
		 * The store page's address.
		 *
		 * @return string
		 */
		public static function store_url() {
			$id = (int) get_option( 'ec_option_storepage' );
			if ( $id ) {
				$id = (int) apply_filters( 'wpml_object_id', $id, 'page', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's own filter ( this language's copy of the page ).
			}
			return $id ? (string) get_permalink( $id ) : home_url( '/' );
		}

		/**
		 * What the cart page shows in this request: cart | checkout | confirmation | other.
		 *
		 * Mirrors wp_easycart_dynamic_cart_display() and ec_cartpage::display_cart_page(), read only.
		 *
		 * @return string
		 */
		public static function state() {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only: which part of the cart page to draw, as the cart page itself reads it.
			$ec_page = isset( $_GET['ec_page'] ) ? sanitize_key( wp_unslash( $_GET['ec_page'] ) ) : '';
			$step    = isset( $_GET['eccheckout'] ) ? sanitize_key( wp_unslash( $_GET['eccheckout'] ) ) : '';
			$paypal  = ! empty( $_GET['PID'] ) || ! empty( $_GET['OID'] );
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			$onepage = function_exists( 'wp_easycart_onepage_active' ) && wp_easycart_onepage_active();
			if ( 'checkout_success' === $ec_page || ( $onepage && '' === $ec_page && 'success' === $step ) ) {
				return 'confirmation';
			}
			if ( in_array( $ec_page, array( 'checkout_info', 'checkout_shipping', 'checkout_payment', 'checkout_login' ), true ) ) {
				return 'checkout';
			}
			if ( '' !== $ec_page ) {
				return 'other';
			}
			if ( $paypal ) {
				return 'checkout';
			}
			if ( $onepage ) {
				if ( 'cart' === $step ) {
					return 'cart';
				}
				if ( in_array( $step, array( 'information', 'shipping', 'payment' ), true ) ) {
					return 'checkout';
				}
				return get_option( 'ec_option_onepage_checkout_cart_first' ) ? 'cart' : 'checkout';
			}
			return 'cart';
		}

		/**
		 * The Elementor document being drawn ( a page, or a Theme Builder template ).
		 *
		 * @return int
		 */
		public static function current_document_id() {
			if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance ) && isset( \Elementor\Plugin::$instance->documents ) && is_object( \Elementor\Plugin::$instance->documents ) && method_exists( \Elementor\Plugin::$instance->documents, 'get_current' ) ) {
				$document = \Elementor\Plugin::$instance->documents->get_current();
				if ( $document && method_exists( $document, 'get_main_id' ) ) {
					return (int) $document->get_main_id();
				}
			}
			return 0;
		}

		/**
		 * A post's saved Elementor elements.
		 *
		 * @param int $post_id Post id.
		 * @return array
		 */
		public static function elements_data( $post_id ) {
			$raw  = get_post_meta( (int) $post_id, '_elementor_data', true );
			$data = null;
			if ( is_string( $raw ) && '' !== $raw ) {
				$data = json_decode( $raw, true );
			} elseif ( is_array( $raw ) ) {
				$data = $raw;
			}
			return is_array( $data ) ? $data : array();
		}

		/**
		 * Does a post's Elementor data hold one of these widgets?
		 *
		 * @param int      $post_id Post id.
		 * @param string[] $names   Widget names.
		 * @return bool
		 */
		public static function post_has_widget( $post_id, $names ) {
			$raw = get_post_meta( (int) $post_id, '_elementor_data', true );
			if ( ! is_string( $raw ) || '' === $raw ) {
				return false;
			}
			foreach ( (array) $names as $name ) {
				if ( false !== strpos( $raw, '"' . $name . '"' ) ) {
					return true;
				}
			}
			return false;
		}

		/**
		 * The widgets on the page being drawn ( the current Elementor document and the queried post ), and whether the page
		 * also carries the [ec_cart] shortcode.
		 *
		 * @return array names => array( name => true ), shortcode => bool.
		 */
		public static function page_widgets() {
			$ids = array_values( array_unique( array_filter( array( self::current_document_id(), (int) get_queried_object_id() ) ) ) );
			$key = implode( ',', $ids );
			if ( isset( self::$page_widgets[ $key ] ) ) {
				return self::$page_widgets[ $key ];
			}
			$found = array(
				'names'     => array(),
				'shortcode' => false,
			);
			foreach ( $ids as $id ) {
				self::walk_elements( self::elements_data( $id ), $found, 0 );
			}
			self::$page_widgets[ $key ] = $found;
			return $found;
		}

		/**
		 * Does an element carry Elementor's display conditions ( it may not draw in this request )?
		 *
		 * @param array $element Element data.
		 * @return bool
		 */
		private static function has_display_conditions( $element ) {
			if ( empty( $element['settings'] ) || ! is_array( $element['settings'] ) || empty( $element['settings']['e_display_conditions'] ) ) {
				return false;
			}
			/* Saved as nested lists of JSON; an empty set ( [], [""], ["[]"] ) holds no letter or digit. */
			return (bool) preg_match( '/[a-z0-9]/i', (string) wp_json_encode( $element['settings']['e_display_conditions'] ) );
		}

		/**
		 * Collect widget names and [ec_cart] shortcodes. Elements under display conditions ( and what they hold ) are left out:
		 * whether they draw is only known when they do.
		 *
		 * @param array $elements Elements.
		 * @param array $found    Result.
		 * @param int   $depth    Nesting depth.
		 */
		private static function walk_elements( $elements, &$found, $depth ) {
			if ( $depth > 40 || ! is_array( $elements ) ) {
				return;
			}
			foreach ( $elements as $element ) {
				if ( ! is_array( $element ) || self::has_display_conditions( $element ) ) {
					continue;
				}
				if ( isset( $element['elType'], $element['widgetType'] ) && 'widget' === $element['elType'] ) {
					$type                    = (string) $element['widgetType'];
					$found['names'][ $type ] = true;
					if ( in_array( $type, array( 'shortcode', 'text-editor', 'html' ), true ) && ! empty( $element['settings'] ) ) {
						$text = (string) wp_json_encode( $element['settings'] );
						if ( preg_match( '/\[ec_cart[\s\]\/]/i', $text ) ) {
							$found['shortcode'] = true;
						}
					}
				}
				if ( ! empty( $element['elements'] ) ) {
					self::walk_elements( $element['elements'], $found, $depth + 1 );
				}
			}
		}

		/**
		 * Which widget draws the cart page in this request: a widget name, 'shortcode' ( the page carries [ec_cart] ) or ''
		 * ( the page could not be read, or every candidate is under display conditions: the first widget to draw takes it ).
		 *
		 * @return string
		 */
		public static function owner() {
			$widgets = self::page_widgets();
			if ( $widgets['shortcode'] ) {
				return 'shortcode';
			}
			switch ( self::state() ) {
				case 'confirmation':
					$order = array( 'wp_easycart_thank_you', 'wp_easycart_checkout', 'wp_easycart_cart' );
					break;
				case 'cart':
					// The one-page checkout draws the cart and every step together and moves between them without a page
					// load, so the Checkout widget ( and its layout ) holds it from the start.
					$onepage = function_exists( 'wp_easycart_onepage_active' ) && wp_easycart_onepage_active();
					$order   = $onepage ? array( 'wp_easycart_checkout', 'wp_easycart_cart' ) : array( 'wp_easycart_cart', 'wp_easycart_checkout' );
					break;
				default:
					$order = array( 'wp_easycart_checkout', 'wp_easycart_cart' );
			}
			foreach ( $order as $name ) {
				if ( isset( $widgets['names'][ $name ] ) ) {
					return $name;
				}
			}
			return '';
		}

		/**
		 * Does this widget draw the cart page in this request?
		 *
		 * @param string $name Widget name.
		 * @return bool
		 */
		public static function owns( $name ) {
			if ( self::$flow_rendered ) {
				return false;
			}
			$owner = self::owner();
			return ( $name === $owner || '' === $owner );
		}

		/**
		 * The cart page ( EasyCart's [ec_cart] ), once per request.
		 *
		 * @param string $name     Widget drawing it.
		 * @param array  $settings Its settings ( for the hooks the cart page runs ).
		 * @return bool Whether it was drawn.
		 */
		public static function render_flow( $name, $settings ) {
			if ( self::$flow_rendered || ! function_exists( 'load_ec_cart' ) ) {
				return false;
			}
			// A plugin that draws the content inside <head> ( SEO descriptions, schema ) gets nothing, and the page itself
			// still draws the cart page afterwards.
			if ( doing_action( 'wp_head' ) ) {
				return false;
			}
			self::$flow_rendered    = true;
			self::$widget_rendered  = true;
			self::$runtime[ $name ] = is_array( $settings ) ? $settings : array();
			if ( ! self::$context_doc ) {
				self::$context_doc = self::current_document_id();
			}
			self::push_texts( self::$runtime[ $name ] );
			echo load_ec_cart( array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- EasyCart's own cart page, escaped by its templates.
			self::pop_texts();
			return true;
		}

		/**
		 * Whether the page being edited ( the Elementor document, else the queried page ) is the store's cart page.
		 *
		 * @return bool
		 */
		public static function editing_cart_page() {
			$document = self::current_document_id();
			return ( $document && self::is_cart_page( $document ) ) || self::is_cart_page();
		}

		/**
		 * Claims the one Cart widget away from the cart page that draws the cart in this request ( EasyCart's cart markup and
		 * scripts are id-based, so a second copy would break both ). True for the first caller only.
		 *
		 * @return bool
		 */
		public static function claim_offpage_cart() {
			++self::$offpage_carts;
			return 1 === self::$offpage_carts;
		}

		/**
		 * Filter pre_do_shortcode_tag: an [ec_cart] drawn by the page itself. After a widget drew the cart page ( a Theme
		 * Builder template that also shows the page's own content ) it prints nothing, so the cart page is never drawn twice.
		 *
		 * @param mixed  $output Short-circuit value.
		 * @param string $tag    Shortcode.
		 * @return mixed
		 */
		public static function note_cart_shortcode( $output, $tag ) {
			if ( 'ec_cart' !== $tag || doing_action( 'wp_head' ) ) {
				return $output;
			}
			if ( self::$widget_rendered ) {
				return '';
			}
			self::$flow_rendered = true;
			return $output;
		}

		/**
		 * What a Cart or Checkout widget saves into post_content: [ec_cart] once per document, even with both widgets on it
		 * ( a site without Elementor, or a REST reader, would otherwise draw the cart page twice ).
		 *
		 * @return string
		 */
		public static function plain_cart_shortcode() {
			$key = self::current_document_id();
			if ( ! $key ) {
				$key = (int) get_the_ID();
			}
			if ( isset( self::$plain_cart[ $key ] ) ) {
				return '';
			}
			self::$plain_cart[ $key ] = true;
			return '[ec_cart]';
		}

		/** Action elementor/document/before_save: a new save writes its own plain content. */
		public static function reset_plain_cart() {
			self::$plain_cart = array();
		}

		/**
		 * The Elementor document whose widget settings apply in this request: the one that drew the cart page, the one a later
		 * request named ( wpec_el_doc, once its settings were read ), else the document being drawn. 0 when unknown.
		 *
		 * @return int
		 */
		public static function context_document_id() {
			return self::$context_doc ? (int) self::$context_doc : self::current_document_id();
		}

		/**
		 * Filter wp: a page built with these widgets, and the cart page however it is built ( a Theme Builder template may
		 * draw it ), gets the cart page scripts ( payment SDKs, card fields ) that EasyCart loads for pages whose content
		 * carries [ec_cart]. Runs before wpeasycart_prevent_iframe(), which reads the same flag.
		 */
		public static function maybe_load_cart_scripts() {
			if ( is_admin() || ! function_exists( 'wp_easycart_load_cart_js' ) ) {
				return;
			}
			$names   = array( 'wp_easycart_cart', 'wp_easycart_checkout', 'wp_easycart_thank_you' );
			$post_id = (int) get_queried_object_id();
			$has     = $post_id && ( self::is_cart_page( $post_id ) || self::post_has_widget( $post_id, $names ) );
			if ( ! $has ) {
				/* Round 11: a Cart widget in an Elementor Pro Theme Builder header, footer, single, archive or popup. */
				foreach ( self::theme_document_ids() as $document_id ) {
					if ( self::post_has_widget( $document_id, $names ) ) {
						$has = true;
						break;
					}
				}
			}
			if ( ! $has ) {
				return;
			}
			$GLOBALS['is_wpec_cart'] = true; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- EasyCart's own flag for a page that shows the cart ( is_wpeasycart_cart() ).
			if ( ! has_action( 'wp_enqueue_scripts', 'wp_easycart_load_cart_js' ) ) {
				add_action( 'wp_enqueue_scripts', 'wp_easycart_load_cart_js' );
			}
		}

		/**
		 * Elementor Pro Theme Builder documents this request uses ( header, footer, single, archive, popup ), as the account
		 * widgets read them. Empty without Elementor Pro, or when its API changed.
		 *
		 * @return int[]
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
					foreach ( (array) $documents as $key => $document ) {
						if ( is_object( $document ) && method_exists( $document, 'get_main_id' ) ) {
							$ids[] = (int) $document->get_main_id();
						} elseif ( is_numeric( $key ) ) {
							$ids[] = (int) $key;
						}
					}
				}
			} catch ( \Throwable $e ) {
				unset( $e ); /* Elementor Pro changed: the page itself is still read. */
			}
			return array_values( array_unique( array_filter( $ids ) ) );
		}

		/** A page that shows the visitor's cart must not be cached ( as load_ec_cart() does ). */
		public static function no_cache() {
			if ( get_option( 'ec_option_cache_prevent' ) ) {
				return;
			}
			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- the page cache plugins' shared constant.
			}
			if ( ! defined( 'DONOTCDN' ) ) {
				define( 'DONOTCDN', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- the page cache plugins' shared constant.
			}
		}

		// Settings for later requests.

		/**
		 * The settings of the widget that drew the cart page: in this request, or, for a later request the cart page made
		 * ( wpec_el_doc / wpec_el_ids ), read from the saved document. Null when there is no such widget.
		 *
		 * @param string $name Widget name.
		 * @return array|null
		 */
		public static function context_settings( $name ) {
			if ( isset( self::$runtime[ $name ] ) ) {
				return self::$runtime[ $name ];
			}
			if ( array_key_exists( $name, self::$context ) ) {
				return self::$context[ $name ];
			}
			self::$context[ $name ] = null;
			if ( ! wp_doing_ajax() ) {
				return null;
			}
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- read-only: ids of a saved, published widget whose display settings this request applies; the request's own handler checks its nonce.
			$doc_id = isset( $_POST['wpec_el_doc'] ) ? absint( $_POST['wpec_el_doc'] ) : 0;
			$ids    = isset( $_POST['wpec_el_ids'] ) ? explode( ',', sanitize_text_field( wp_unslash( $_POST['wpec_el_ids'] ) ) ) : array();
			// phpcs:enable WordPress.Security.NonceVerification.Missing
			$ids = array_slice( array_values( array_filter( array_map( 'sanitize_key', $ids ) ) ), 0, 8 );
			if ( ! $doc_id || ! $ids ) {
				return null;
			}
			if ( 'publish' !== get_post_status( $doc_id ) && ! current_user_can( 'edit_post', $doc_id ) ) {
				return null;
			}
			if ( post_password_required( $doc_id ) ) {
				return null;
			}
			$element = self::find_element( self::elements_data( $doc_id ), $ids, $name, 0 );
			if ( ! $element ) {
				return null;
			}
			self::$context[ $name ] = self::element_settings( $element );
			self::$context_doc      = $doc_id;
			return self::$context[ $name ];
		}

		/**
		 * Find a widget by id and name.
		 *
		 * @param array    $elements Elements.
		 * @param string[] $ids      Element ids.
		 * @param string   $name     Widget name.
		 * @param int      $depth    Nesting depth.
		 * @return array|null
		 */
		private static function find_element( $elements, $ids, $name, $depth ) {
			if ( $depth > 40 || ! is_array( $elements ) ) {
				return null;
			}
			foreach ( $elements as $element ) {
				if ( ! is_array( $element ) ) {
					continue;
				}
				if ( isset( $element['id'], $element['widgetType'] ) && $name === $element['widgetType'] && in_array( (string) $element['id'], $ids, true ) ) {
					return $element;
				}
				if ( ! empty( $element['elements'] ) ) {
					$hit = self::find_element( $element['elements'], $ids, $name, $depth + 1 );
					if ( $hit ) {
						return $hit;
					}
				}
			}
			return null;
		}

		/**
		 * A saved widget's settings as Elementor draws them ( defaults, dynamic tags ), else as saved.
		 *
		 * @param array $element Element data.
		 * @return array
		 */
		private static function element_settings( $element ) {
			$settings = ( isset( $element['settings'] ) && is_array( $element['settings'] ) ) ? $element['settings'] : array();
			if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance ) && isset( \Elementor\Plugin::$instance->elements_manager ) ) {
				try {
					$instance = \Elementor\Plugin::$instance->elements_manager->create_element_instance( $element );
					if ( $instance && method_exists( $instance, 'get_settings_for_display' ) ) {
						$display = $instance->get_settings_for_display();
						if ( is_array( $display ) ) {
							$settings = $display;
						}
					}
				} catch ( \Throwable $e ) {
					unset( $e ); /* the saved values then */
				}
			}
			return $settings;
		}

		/**
		 * Attributes a cart page widget prints so later requests find its settings.
		 *
		 * @param string $element_id The widget's element id.
		 * @return string
		 */
		public static function context_attributes( $element_id ) {
			$doc = self::current_document_id();
			if ( ! $doc ) {
				$doc = (int) get_queried_object_id();
			}
			return ' data-wpec-el-doc="' . esc_attr( (string) $doc ) . '" data-wpec-el-id="' . esc_attr( sanitize_key( (string) $element_id ) ) . '"';
		}

		// Cart state, off-page cart.

		/**
		 * Filter wp_easycart_cart_state: what the mini carts need on top of the foundation's state ( line totals, options,
		 * quantity limits and the nonces EasyCart's own update and remove requests check ).
		 *
		 * @param array        $state State.
		 * @param ec_cart|null $cart  The cart.
		 * @return array
		 */
		public static function cart_state( $state, $cart ) {
			if ( ! is_array( $state ) ) {
				return $state;
			}
			$state['cart_url']     = esc_url_raw( self::cart_url() );
			$state['checkout_url'] = esc_url_raw( self::checkout_url() );
			$state['store_url']    = esc_url_raw( self::store_url() );
			if ( ! is_object( $cart ) || empty( $cart->cart ) || ! is_array( $cart->cart ) || empty( $state['items'] ) ) {
				return $state;
			}
			$lines = array();
			foreach ( $cart->cart as $item ) {
				$lines[ (int) $item->cartitem_id ] = $item;
			}
			foreach ( $state['items'] as $index => $row ) {
				$id = isset( $row['cartitem_id'] ) ? (int) $row['cartitem_id'] : 0;
				if ( ! isset( $lines[ $id ] ) ) {
					continue;
				}
				$item                                      = $lines[ $id ];
				$locked                                    = ( ! empty( $item->grid_quantity ) || ! empty( $item->is_deconetwork ) || ( function_exists( 'wp_easycart_offers_active' ) && wp_easycart_offers_active() && ( ! empty( $item->free_gift_offer_id ) || ! empty( $item->bundle_group_key ) ) ) );
				$total                                     = ! empty( $item->is_deconetwork ) ? $item->deconetwork_total : $item->total_price;
				$state['items'][ $index ]['total_display'] = function_exists( 'wp_easycart_cart_state_text' ) ? wp_easycart_cart_state_text( $GLOBALS['currency']->get_currency_display( $total ) ) : '';
				$state['items'][ $index ]['options']       = self::item_options( $item );
				$state['items'][ $index ]['min_quantity']  = max( 1, (int) $item->min_quantity );
				$state['items'][ $index ]['max_quantity']  = (int) $item->max_quantity;
				$state['items'][ $index ]['locked']        = $locked;
				$state['items'][ $index ]['removable']     = ! ( function_exists( 'wp_easycart_offers_active' ) && wp_easycart_offers_active() && ! empty( $item->free_gift_offer_id ) );
				$state['items'][ $index ]['update_nonce']  = wp_create_nonce( 'wp-easycart-update-cart-item-' . $id );
				$state['items'][ $index ]['delete_nonce']  = wp_create_nonce( 'wp-easycart-delete-cart-item-' . $id );
			}
			return $state;
		}

		/**
		 * A cart line's choices as one line of text ( "Blue, Large, Engraving: Anna" ).
		 *
		 * @param object $item ec_cartitem.
		 * @return string
		 */
		public static function item_options( $item ) {
			$parts = array();
			for ( $i = 1; $i <= 5; $i++ ) {
				$key = 'optionitem' . $i . '_name';
				if ( isset( $item->$key ) && '' !== trim( (string) $item->$key ) ) {
					$parts[] = (string) $item->$key;
				}
			}
			if ( ( ! empty( $item->use_advanced_optionset ) || ! empty( $item->use_both_option_types ) ) && ! empty( $item->advanced_options ) && is_array( $item->advanced_options ) ) {
				foreach ( $item->advanced_options as $option ) {
					if ( ! is_object( $option ) || ( isset( $option->option_type ) && in_array( $option->option_type, array( 'dimensions1', 'dimensions2' ), true ) ) ) {
						continue;
					}
					$label = ( isset( $option->option_type ) && 'grid' === $option->option_type ) ? ( isset( $option->optionitem_name ) ? $option->optionitem_name : '' ) : ( isset( $option->option_label ) ? $option->option_label : '' );
					$value = isset( $option->optionitem_value ) ? (string) $option->optionitem_value : '';
					if ( '' !== trim( $value ) ) {
						$parts[] = trim( wp_strip_all_tags( (string) $label ) ) . ': ' . $value;
					}
				}
			}
			$text = implode( ', ', $parts );
			if ( function_exists( 'wp_easycart_language' ) ) {
				$text = wp_easycart_language()->convert_text( $text );
			}
			/* Plain text ( shown with esc_html() and jQuery .text() ): what the shopper typed stays as typed, "<3" included. */
			return trim( html_entity_decode( (string) $text, ENT_QUOTES, 'UTF-8' ) );
		}

		/**
		 * The cart on its own, for a Cart widget away from the cart page on a store with cache prevention ( the page it sits
		 * on may be cached, so the cart is drawn after it loads ). Read only, never cached, and needs no nonce: it draws the
		 * cart of whoever asks ( their own session cookie ), like ec_ajax_cart_state.
		 */
		public static function ajax_cart() {
			nocache_headers();
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- read-only: the visitor's own cart, in the language the page shows.
			if ( isset( $_POST['language'] ) && function_exists( 'wp_easycart_language' ) ) {
				wp_easycart_language()->set_language( sanitize_text_field( wp_unslash( $_POST['language'] ) ) );
			}
			$view = ( isset( $_POST['view'] ) && 'summary' === sanitize_key( wp_unslash( $_POST['view'] ) ) ) ? 'summary' : 'cart';
			// phpcs:enable WordPress.Security.NonceVerification.Missing
			$html  = '';
			$count = 0;
			if ( function_exists( 'wpeasycart_session' ) && class_exists( 'ec_cartpage' ) && wpeasycart_session()->handle_session( false, false ) ) {
				$cartpage = new ec_cartpage();
				$count    = (int) $cartpage->cart->total_items;
				if ( $count > 0 && 'summary' === $view ) {
					$html = self::summary_body( $cartpage );
				} elseif ( $count > 0 && method_exists( $cartpage, 'display_cart_contents' ) ) {
					ob_start();
					$cartpage->display_cart_contents();
					$html = ob_get_clean();
				}
			}
			wp_send_json(
				array(
					'count' => $count,
					'html'  => $html,
				)
			);
		}

		/**
		 * The Order Summary widget's list and totals for a cart ( plain markup with its own classes: the checkout's scripts
		 * find their parts by id and by EasyCart class, so a second summary must use neither ).
		 *
		 * @param ec_cartpage $cartpage The cart page.
		 * @return string
		 */
		public static function summary_body( $cartpage ) {
			$text = function ( $value ) {
				return trim( html_entity_decode( wp_strip_all_tags( (string) $value ), ENT_QUOTES, 'UTF-8' ) );
			};
			$html = '<ul class="wpec-summary__items">';
			foreach ( $cartpage->cart->cart as $item ) {
				$quantity = ( ! empty( $item->grid_quantity ) ) ? (int) $item->grid_quantity : (int) $item->quantity;
				$title    = ( ! empty( $item->is_deconetwork ) && isset( $item->deconetwork_name ) ) ? $item->deconetwork_name : wp_easycart_language()->convert_text( $item->title );
				$total    = ( ! empty( $item->is_deconetwork ) ) ? $item->deconetwork_total : $item->total_price;
				$options  = self::item_options( $item );
				$html    .= '<li class="wpec-summary__item" data-cartitem="' . esc_attr( (string) (int) $item->cartitem_id ) . '">';
				$html    .= '<span class="wpec-summary__image"><img src="' . esc_url( $item->get_image_url() ) . '" alt="" loading="lazy" /></span>';
				$html    .= '<span class="wpec-summary__details"><span class="wpec-summary__title">' . esc_html( $text( $title ) ) . '</span>';
				if ( '' !== $options ) {
					$html .= '<span class="wpec-summary__options">' . esc_html( $options ) . '</span>';
				}
				$html .= '<span class="wpec-summary__quantity">&times; ' . esc_html( (string) $quantity ) . '</span></span>';
				$html .= '<span class="wpec-summary__price">' . esc_html( self::price( $total ) ) . '</span></li>';
			}
			$html  .= '</ul>';
			$totals = $cartpage->order_totals;
			$rows   = array( array( 'subtotal', self::text( 'cart_totals_subtotal', 'Cart Subtotal', 'cart_totals' ), $cartpage->get_subtotal() ) );
			if ( 0.0 !== (float) $totals->discount_total ) {
				$rows[] = array( 'discount', self::text( 'cart_totals_discounts', 'Discounts', 'cart_totals' ), $cartpage->get_discount_total() );
			}
			if ( get_option( 'ec_option_use_shipping' ) && ( (float) $totals->shipping_total > 0 || (int) $cartpage->cart->shippable_total_items > 0 ) ) {
				$rows[] = array( 'shipping', self::text( 'cart_totals_shipping', 'Shipping', 'cart_totals' ), $cartpage->get_shipping_total() );
			}
			if ( isset( $totals->tip_total ) && (float) $totals->tip_total > 0 ) {
				$rows[] = array( 'tip', self::text( 'cart_totals_tip', 'Tip', 'cart_totals' ), $cartpage->get_tip_total() );
			}
			$taxes = array(
				'tax'  => array( 'tax_total', self::text( 'cart_totals_tax', 'Tax', 'cart_totals' ) ),
				'duty' => array( 'duty_total', self::text( 'cart_totals_duty', 'Duty', 'cart_totals' ) ),
				'vat'  => array( 'vat_total', self::text( 'cart_totals_vat', 'VAT', 'cart_totals' ) ),
			);
			foreach ( array( 'gst', 'pst', 'hst' ) as $canada ) {
				$taxes[ $canada ] = array( $canada . '_total', function_exists( 'wp_easycart_canada_tax_label' ) && isset( $cartpage->tax->shipping_state ) ? wp_easycart_canada_tax_label( $canada, (string) $cartpage->tax->shipping_state ) : strtoupper( $canada ) );
			}
			foreach ( $taxes as $key => $tax ) {
				$property = $tax[0];
				if ( isset( $totals->$property ) && (float) $totals->$property > 0 ) {
					$rows[] = array( $key, $tax[1], $GLOBALS['currency']->get_currency_display( $totals->$property ) );
				}
			}
			if ( isset( $cartpage->tax->fees ) && is_array( $cartpage->tax->fees ) ) {
				foreach ( $cartpage->tax->fees as $fee ) {
					$rows[] = array( 'fee', (string) $fee->label, $GLOBALS['currency']->get_currency_display( $fee->amount, false ) );
				}
			}
			$rows[] = array( 'total', self::text( 'cart_totals_grand_total', 'Grand Total', 'cart_totals' ), $cartpage->get_grand_total() );
			$html  .= '<dl class="wpec-summary__totals">';
			foreach ( $rows as $row ) {
				$html .= '<div class="wpec-summary__line wpec-summary__line--' . esc_attr( $row[0] ) . '"><dt>' . esc_html( $text( $row[1] ) ) . '</dt><dd>' . esc_html( $text( $row[2] ) ) . '</dd></div>';
			}
			$html .= '</dl>';
			return $html;
		}

		// Order confirmation.

		/**
		 * The Order Confirmation widget's own headings and texts ( round 11 ): control => array( label, language section, key,
		 * English ).
		 *
		 * @return array
		 */
		public static function confirmation_texts() {
			return array(
				'ec_items_heading'    => array( __( '"Your order" heading', 'wp-easycart' ), 'elementor_checkout', 'confirmation_items', 'Your order' ),
				'ec_payment_heading'  => array( __( '"How to pay" heading', 'wp-easycart' ), 'elementor_checkout', 'confirmation_payment', 'How to pay' ),
				'ec_pay_note'         => array( __( 'Pay link note', 'wp-easycart' ), 'elementor_checkout', 'confirmation_pay_link_note', 'You can pay for this order online.' ),
				'ec_pay_button'       => array( __( 'Pay now button', 'wp-easycart' ), 'elementor_checkout', 'confirmation_pay_now', 'Pay now' ),
				'ec_billing_heading'  => array( __( 'Billing address heading', 'wp-easycart' ), 'cart_success', 'cart_payment_complete_billing_label', 'Billing Address' ),
				'ec_shipping_heading' => array( __( 'Shipping address heading', 'wp-easycart' ), 'cart_success', 'cart_payment_complete_shipping_label', 'Shipping Address' ),
				'ec_account_text'     => array( __( 'Account prompt text', 'wp-easycart' ), 'cart_success', 'cart_success_save_order_text', 'Save your information for next time' ),
				'ec_account_button'   => array( __( 'Create account button', 'wp-easycart' ), 'cart_success', 'cart_success_create_account', 'Create Account' ),
			);
		}

		/**
		 * One of confirmation_texts(): the widget's own text, else the store's wording, as plain text.
		 *
		 * @param array  $settings Order Confirmation widget settings.
		 * @param string $key      Control.
		 * @return string
		 */
		public static function confirmation_text( $settings, $key ) {
			if ( is_array( $settings ) && isset( $settings[ $key ] ) && is_string( $settings[ $key ] ) && '' !== trim( $settings[ $key ] ) ) {
				return trim( $settings[ $key ] );
			}
			$texts = self::confirmation_texts();
			if ( ! isset( $texts[ $key ] ) ) {
				return '';
			}
			return trim( wp_strip_all_tags( self::text( $texts[ $key ][2], $texts[ $key ][3], $texts[ $key ][1] ) ) );
		}

		/**
		 * The order's date, payment method and shipping method ( the Order Confirmation widget's "Order date and methods" ).
		 *
		 * @param array $meta date, payment, shipping ( plain text, '' leaves a line out ).
		 */
		public static function print_order_meta( $meta ) {
			$labels = array(
				'date'     => self::text( 'account_orders_details_order_date', 'Order Date:', 'account_order_details' ),
				'payment'  => self::text( 'account_orders_details_payment_method', 'Payment Method:', 'account_order_details' ),
				'shipping' => self::text( 'account_orders_details_shipping_method', 'Shipping Method:', 'account_order_details' ),
			);
			$rows   = '';
			foreach ( $labels as $key => $label ) {
				$value = isset( $meta[ $key ] ) ? trim( wp_strip_all_tags( (string) $meta[ $key ] ) ) : '';
				if ( '' === $value ) {
					continue;
				}
				$rows .= '<div class="wpec-thank-you__meta-line wpec-thank-you__meta-line--' . esc_attr( $key ) . '"><dt>' . esc_html( rtrim( trim( wp_strip_all_tags( (string) $label ) ), ':' ) ) . '</dt><dd>' . esc_html( $value ) . '</dd></div>';
			}
			if ( '' !== $rows ) {
				echo '<section class="wpec-thank-you__section wpec-thank-you__meta"><dl class="wpec-thank-you__meta-list">' . $rows . '</dl></section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts above.
			}
		}

		/**
		 * Action wpeasycart_success_page_content_middle: what the Order Confirmation widget adds below EasyCart's thank-you
		 * ( its message, the items, totals, addresses, how to pay and an account prompt ). Nothing without that widget.
		 *
		 * @param int                  $order_id Order.
		 * @param ec_orderdisplay|null $order    Order.
		 */
		public static function confirmation_extras( $order_id, $order = null ) {
			$settings = self::context_settings( 'wp_easycart_thank_you' );
			if ( null === $settings || ! is_object( $order ) ) {
				return;
			}
			/* A switch left at its default is not saved: every part shows by default except the account prompt. */
			$on       = function ( $key, $fallback = true ) use ( $settings ) {
				return isset( $settings[ $key ] ) ? 'yes' === $settings[ $key ] : $fallback;
			};
			$currency = $GLOBALS['currency'];
			echo '<div class="wpec-thank-you__extras">';
			if ( ! empty( $settings['ec_message'] ) ) {
				echo '<div class="wpec-thank-you__message">' . wp_kses_post( wpautop( (string) $settings['ec_message'] ) ) . '</div>';
			}
			if ( $on( 'ec_show_meta', false ) ) {
				$payment = '';
				if ( method_exists( $order, 'display_payment_method' ) ) {
					ob_start();
					$order->display_payment_method();
					$payment = (string) ob_get_clean();
				}
				$date = '';
				if ( method_exists( $order, 'display_order_date' ) && ! empty( $order->order_date ) ) {
					ob_start();
					$order->display_order_date( get_option( 'date_format' ) );
					$date = (string) ob_get_clean();
				}
				self::print_order_meta(
					array(
						'date'     => $date,
						'payment'  => $payment,
						'shipping' => isset( $order->shipping_method ) ? wp_easycart_language()->convert_text( (string) $order->shipping_method ) : '',
					)
				);
			}
			if ( $on( 'ec_show_payment' ) ) {
				self::print_payment_instructions( $order, $settings );
			}
			if ( $on( 'ec_show_items' ) && ! empty( $order->orderdetails ) ) {
				echo '<section class="wpec-thank-you__section wpec-thank-you__items" aria-labelledby="wpec-thank-you-items-' . esc_attr( (int) $order_id ) . '">';
				echo '<h3 class="wpec-thank-you__heading" id="wpec-thank-you-items-' . esc_attr( (int) $order_id ) . '">' . esc_html( self::confirmation_text( $settings, 'ec_items_heading' ) ) . '</h3><ul class="wpec-summary__items">';
				foreach ( $order->orderdetails as $detail ) {
					$image   = self::order_line_image( $detail );
					$options = array();
					for ( $i = 1; $i <= 5; $i++ ) {
						$key = 'optionitem_name_' . $i;
						if ( isset( $detail->$key ) && '' !== trim( (string) $detail->$key ) ) {
							$options[] = (string) $detail->$key;
						}
					}
					echo '<li class="wpec-summary__item">';
					if ( '' !== $image ) {
						echo '<span class="wpec-summary__image"><img src="' . esc_url( $image ) . '" alt="" loading="lazy" /></span>';
					}
					echo '<span class="wpec-summary__details"><span class="wpec-summary__title">' . esc_html( wp_strip_all_tags( wp_easycart_language()->convert_text( (string) $detail->title ) ) ) . '</span>';
					if ( $options ) {
						echo '<span class="wpec-summary__options">' . esc_html( wp_strip_all_tags( wp_easycart_language()->convert_text( implode( ', ', $options ) ) ) ) . '</span>';
					}
					echo '<span class="wpec-summary__quantity">&times; ' . esc_html( (string) (int) $detail->quantity ) . '</span></span>';
					echo '<span class="wpec-summary__price">' . esc_html( wp_strip_all_tags( $currency->get_currency_display( $detail->total_price ) ) ) . '</span></li>';
				}
				echo '</ul></section>';
			}
			if ( $on( 'ec_show_totals' ) ) {
				$rows   = array();
				$rows[] = array( 'subtotal', wp_easycart_language()->get_text( 'cart_success', 'cart_payment_complete_order_totals_subtotal' ), $order->sub_total, 'Subtotal' );
				if ( 0.0 !== (float) $order->discount_total ) {
					$rows[] = array( 'discount', wp_easycart_language()->get_text( 'cart_success', 'cart_payment_complete_order_totals_discount' ), -1 * abs( (float) $order->discount_total ), 'Discount' );
				}
				if ( (float) $order->shipping_total > 0 || get_option( 'ec_option_use_shipping' ) ) {
					$rows[] = array( 'shipping', wp_easycart_language()->get_text( 'cart_success', 'cart_payment_complete_order_totals_shipping' ), $order->shipping_total, 'Shipping' );
				}
				if ( isset( $order->tip_total ) && (float) $order->tip_total > 0 ) {
					$rows[] = array( 'tip', wp_easycart_language()->get_text( 'cart_totals', 'cart_totals_tip' ), $order->tip_total, 'Tip' );
				}
				foreach ( array(
					'tax'  => 'cart_payment_complete_order_totals_tax',
					'duty' => 'cart_payment_complete_order_totals_duty',
					'vat'  => 'cart_payment_complete_order_totals_vat',
					'gst'  => '',
					'pst'  => '',
					'hst'  => '',
				) as $type => $label_key ) {
					$property = $type . '_total';
					if ( isset( $order->$property ) && (float) $order->$property > 0 ) {
						$label  = ( '' !== $label_key ) ? wp_easycart_language()->get_text( 'cart_success', $label_key ) : ( function_exists( 'wp_easycart_canada_tax_label' ) ? wp_easycart_canada_tax_label( $type, (string) $order->shipping_state ) : strtoupper( $type ) );
						$rows[] = array( $type, $label, $order->$property, strtoupper( $type ) );
					}
				}
				/* Fees ( flex fees such as a card fee ), as My Account lists them, so the lines add up to the Order Total. */
				if ( ! empty( $order->order_fees ) && is_array( $order->order_fees ) ) {
					foreach ( $order->order_fees as $fee ) {
						if ( is_object( $fee ) && isset( $fee->fee_total ) ) {
							$rows[] = array( 'fee', isset( $fee->fee_label ) ? (string) $fee->fee_label : '', $fee->fee_total, 'Fee' );
						}
					}
				}
				$rows[] = array( 'total', wp_easycart_language()->get_text( 'cart_success', 'cart_payment_complete_order_totals_grand_total' ), $order->grand_total, 'Order Total' );
				echo '<section class="wpec-thank-you__section wpec-thank-you__totals"><dl class="wpec-summary__totals">';
				foreach ( $rows as $row ) {
					$label = trim( wp_strip_all_tags( (string) $row[1] ) );
					echo '<div class="wpec-summary__line wpec-summary__line--' . esc_attr( $row[0] ) . '"><dt>' . esc_html( '' !== $label ? $label : $row[3] ) . '</dt><dd>' . esc_html( wp_strip_all_tags( $currency->get_currency_display( $row[2] ) ) ) . '</dd></div>';
				}
				echo '</dl></section>';
			}
			if ( $on( 'ec_show_addresses' ) ) {
				self::print_addresses( $order, $settings );
			}
			if ( $on( 'ec_show_account', false ) && ( ! isset( $GLOBALS['ec_user'] ) || empty( $GLOBALS['ec_user']->user_id ) ) && function_exists( 'wpeasycart_links' ) ) {
				echo '<section class="wpec-thank-you__section wpec-thank-you__account"><p class="wpec-thank-you__account-text">' . esc_html( self::confirmation_text( $settings, 'ec_account_text' ) ) . '</p>';
				echo '<a class="wpec-thank-you__button" href="' . esc_url( wpeasycart_links()->get_account_page( 'register' ) ) . '">' . esc_html( self::confirmation_text( $settings, 'ec_account_button' ) ) . '</a></section>';
			}
			echo '</div>';
		}

		/**
		 * How to pay an order that is not paid yet: the bank transfer text for a direct deposit order, and the pay link when
		 * the store takes payment online on pay links.
		 *
		 * @param ec_orderdisplay $order    Order.
		 * @param array           $settings Order Confirmation widget settings.
		 */
		private static function print_payment_instructions( $order, $settings = array() ) {
			if ( ! empty( $order->is_approved ) ) {
				return;
			}
			$manual = ( 'manual_bill' === (string) $order->payment_method && get_option( 'ec_option_use_direct_deposit' ) );
			$link   = '';
			if ( $manual && class_exists( 'wp_easycart_order_pay' ) && wp_easycart_order_pay::available() && (float) $order->grand_total > 0 ) {
				$link = wp_easycart_order_pay::url( (int) $order->order_id );
			}
			$text = $manual ? (string) wp_easycart_language()->convert_text( get_option( 'ec_option_direct_deposit_message' ) ) : '';
			if ( '' === trim( $text ) && '' === $link ) {
				return;
			}
			echo '<section class="wpec-thank-you__section wpec-thank-you__payment"><h3 class="wpec-thank-you__heading">' . esc_html( self::confirmation_text( $settings, 'ec_payment_heading' ) ) . '</h3>';
			if ( '' !== trim( $text ) ) {
				echo '<div class="wpec-thank-you__bank">' . nl2br( esc_html( $text ) ) . '</div>';
			}
			if ( '' !== $link ) {
				echo '<p class="wpec-thank-you__pay-note">' . esc_html( self::confirmation_text( $settings, 'ec_pay_note' ) ) . '</p>';
				echo '<a class="wpec-thank-you__button" href="' . esc_url( $link ) . '">' . esc_html( self::confirmation_text( $settings, 'ec_pay_button' ) ) . '</a>';
			}
			echo '</section>';
		}

		/**
		 * The order's billing and shipping addresses.
		 *
		 * @param ec_orderdisplay $order    Order.
		 * @param array           $settings Order Confirmation widget settings.
		 */
		private static function print_addresses( $order, $settings = array() ) {
			$blocks = array(
				'billing'  => self::confirmation_text( $settings, 'ec_billing_heading' ),
				'shipping' => self::confirmation_text( $settings, 'ec_shipping_heading' ),
			);
			$html   = '';
			foreach ( $blocks as $type => $label ) {
				$get  = function ( $field ) use ( $order, $type ) {
					$property = $type . '_' . $field;
					return isset( $order->$property ) ? trim( (string) $order->$property ) : '';
				};
				$name = trim( $get( 'first_name' ) . ' ' . $get( 'last_name' ) );
				if ( '' === $name && '' === $get( 'address_line_1' ) ) {
					continue;
				}
				$country  = $get( 'country_name' );
				$lines    = array_filter(
					array(
						$name,
						$get( 'company_name' ),
						$get( 'address_line_1' ),
						$get( 'address_line_2' ),
						trim( $get( 'city' ) . ( '' !== $get( 'state' ) ? ', ' . $get( 'state' ) : '' ) . ' ' . $get( 'zip' ) ),
						( '' !== $country ) ? $country : $get( 'country' ),
						$get( 'phone' ),
					),
					'strlen'
				);
				$fallback = ( 'billing' === $type ) ? 'Billing Address' : 'Shipping Address';
				$label    = trim( wp_strip_all_tags( (string) $label ) );
				$html    .= '<div class="wpec-thank-you__address wpec-thank-you__address--' . esc_attr( $type ) . '"><h3 class="wpec-thank-you__heading">' . esc_html( '' !== $label ? $label : $fallback ) . '</h3><address>';
				$html    .= implode( '<br />', array_map( 'esc_html', $lines ) );
				$html    .= '</address></div>';
			}
			if ( '' !== $html ) {
				echo '<section class="wpec-thank-you__section wpec-thank-you__addresses">' . $html . '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts above.
			}
		}

		/**
		 * An order line's picture, or ''.
		 *
		 * @param object $detail ec_orderdetail.
		 * @return string
		 */
		private static function order_line_image( $detail ) {
			$image = isset( $detail->image1 ) ? (string) $detail->image1 : '';
			if ( '' === $image ) {
				return '';
			}
			if ( 0 === strpos( $image, 'http://' ) || 0 === strpos( $image, 'https://' ) ) {
				return $image;
			}
			if ( defined( 'EC_PLUGIN_DATA_DIRECTORY' ) && is_file( EC_PLUGIN_DATA_DIRECTORY . '/products/pics1/' . $image ) ) {
				return plugins_url( 'products/pics1/' . $image, EC_PLUGIN_DATA_DIRECTORY . '/wpeasycart.php' );
			}
			return '';
		}

		// Shared helpers.

		/**
		 * Shopper-facing text from the language file, else the English fallback ( the language system answers '' for a key
		 * a file does not have ).
		 *
		 * @param string $key      Key.
		 * @param string $fallback English text.
		 * @param string $section  Language section.
		 * @return string
		 */
		public static function text( $key, $fallback, $section = 'elementor_checkout' ) {
			$text = function_exists( 'wp_easycart_language' ) ? (string) wp_easycart_language()->get_text( $section, $key ) : '';
			return ( '' !== trim( $text ) ) ? $text : $fallback;
		}

		// Texts the widgets replace ( round 11 ).

		/**
		 * The store's wording a widget can replace: key => label ( panel ), section and key in the language file, English.
		 * A widget's control ec_text_{key} holds its own text.
		 *
		 * @return array
		 */
		public static function text_keys() {
			$keys = array(
				/* The cart */
				'cart_header_column1'                     => array( 'cart', __( 'Product column heading', 'wp-easycart' ), 'Product' ),
				'cart_header_column3'                     => array( 'cart', __( 'Price column heading', 'wp-easycart' ), 'Price' ),
				'cart_header_column4'                     => array( 'cart', __( 'Quantity column heading', 'wp-easycart' ), 'Quantity' ),
				'cart_header_column5'                     => array( 'cart', __( 'Total column heading', 'wp-easycart' ), 'Total' ),
				'cart_checkout'                           => array( 'cart', __( 'Checkout button', 'wp-easycart' ), 'Checkout' ),
				'cart_continue_shopping'                  => array( 'cart', __( 'Continue shopping button', 'wp-easycart' ), 'Continue Shopping' ),
				'cart_totals_label'                       => array( 'cart_totals', __( '"Cart Totals" heading', 'wp-easycart' ), 'Cart Totals' ),
				'cart_coupon_title'                       => array( 'cart_coupons', __( '"Coupon" heading', 'wp-easycart' ), 'Coupon' ),
				'cart_enter_coupon'                       => array( 'cart_coupons', __( 'Coupon field placeholder', 'wp-easycart' ), 'Enter Coupon Code' ),
				'cart_apply_coupon'                       => array( 'cart_coupons', __( 'Apply coupon button', 'wp-easycart' ), 'Apply Coupon' ),
				'cart_gift_card_title'                    => array( 'cart_coupons', __( '"Gift Card" heading', 'wp-easycart' ), 'Gift Card' ),
				'cart_enter_gift_code'                    => array( 'cart_coupons', __( 'Gift card field placeholder', 'wp-easycart' ), 'Enter Gift Card' ),
				'cart_redeem_gift_card'                   => array( 'cart_coupons', __( 'Redeem gift card button', 'wp-easycart' ), 'Redeem Gift Card' ),
				'cart_estimate_shipping_button'           => array( 'cart_estimate_shipping', __( 'Estimate shipping heading and button', 'wp-easycart' ), 'Estimate Shipping' ),
				'checkout_note'                           => array( 'cart_onepage', __( 'Note under the subtotal ( one-page checkout )', 'wp-easycart' ), 'Shipping, taxes, and discount codes calculated at checkout.' ),
				'remove'                                  => array( 'cart_onepage', __( 'Remove link ( one-page checkout )', 'wp-easycart' ), 'Remove' ),
				/* Checkout steps */
				'cart_title'                              => array( 'cart', __( 'First step ( the cart )', 'wp-easycart' ), 'SHOPPING CART' ),
				'cart_checkout_details_title'             => array( 'cart', __( 'Second step ( details )', 'wp-easycart' ), 'CHECKOUT DETAILS' ),
				'cart_submit_payment_title'               => array( 'cart', __( 'Last step ( payment )', 'wp-easycart' ), 'SUBMIT PAYMENT' ),
				/* The checkout */
				'contact'                                 => array( 'cart_onepage', __( '"Contact" heading', 'wp-easycart' ), 'Contact' ),
				'cart_login_title'                        => array( 'cart_login', __( '"Returning Customer" heading', 'wp-easycart' ), 'Returning Customer' ),
				'cart_shipping_information_title'         => array( 'cart_shipping_information', __( 'Shipping address heading', 'wp-easycart' ), 'Shipping Information' ),
				'cart_billing_information_title'          => array( 'cart_billing_information', __( 'Billing address heading', 'wp-easycart' ), 'Billing Information' ),
				'billing_address'                         => array( 'cart_onepage', __( 'Billing address heading ( one-page checkout )', 'wp-easycart' ), 'Billing Address' ),
				'cart_shipping_method_title'              => array( 'cart_shipping_method', __( 'Shipping method heading', 'wp-easycart' ), 'Shipping Method' ),
				'cart_payment_information_payment_method' => array( 'cart_payment_information', __( 'Payment method heading', 'wp-easycart' ), 'Payment Method' ),
				'cart_payment_information_order_notes_title' => array( 'cart_payment_information', __( 'Order notes heading', 'wp-easycart' ), 'ORDER NOTES' ),
				'cart_payment_information_review_title'   => array( 'cart_payment_information', __( '"Review your cart" heading', 'wp-easycart' ), 'REVIEW YOUR CART' ),
				'your_cart_title'                         => array( 'cart', __( '"Your cart" heading', 'wp-easycart' ), 'YOUR CART' ),
				'cart_totals_title'                       => array( 'cart_totals', __( 'Totals heading', 'wp-easycart' ), 'Cart Totals' ),
				'order_summary'                           => array( 'cart_onepage', __( '"Order Summary" heading', 'wp-easycart' ), 'Order Summary' ),
				'cart_simple_apply'                       => array( 'cart_coupons', __( 'Apply button ( code boxes )', 'wp-easycart' ), 'Apply' ),
				'cart_payment_information_submit_order_button' => array( 'cart_payment_information', __( 'Place order button', 'wp-easycart' ), 'SUBMIT ORDER' ),
				/* The order confirmation */
				'cart_success_thank_you_title'            => array( 'cart_success', __( 'Thank-you heading', 'wp-easycart' ), 'Thank you for your order' ),
				'cart_success_will_receive_email'         => array( 'cart_success', __( 'Email note', 'wp-easycart' ), 'You will receive an email confirmation shortly at' ),
				'cart_payment_receipt_order_details_link' => array( 'cart_success', __( '"View Order Details" button', 'wp-easycart' ), 'View Order Details' ),
				'cart_success_print_receipt_text'         => array( 'cart_success', __( 'Print receipt link', 'wp-easycart' ), 'Print Receipt' ),
			);
			$out = array();
			foreach ( $keys as $key => $row ) {
				$out[ $key ] = array(
					'section'  => $row[0],
					'label'    => $row[1],
					'fallback' => $row[2],
				);
			}
			return $out;
		}

		/**
		 * The store's own wording for a text key ( the panel's placeholder ), as plain text.
		 *
		 * @param string $key text_keys() key.
		 * @return string
		 */
		public static function store_text( $key ) {
			$keys = self::text_keys();
			if ( ! isset( $keys[ $key ] ) ) {
				return '';
			}
			self::$texts[] = array(); /* nothing replaced while reading the store's own wording */
			$text          = self::text( $key, $keys[ $key ]['fallback'], $keys[ $key ]['section'] );
			array_pop( self::$texts );
			return trim( html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES, 'UTF-8' ) );
		}

		/**
		 * Whether WP EasyCart passes its wording through the filter wp_easycart_language_text ( $text, $section, $key ), asked
		 * once: a probe filter answers a marker for one known key.
		 *
		 * @return bool
		 */
		public static function language_filter_ready() {
			if ( null !== self::$language_filter ) {
				return self::$language_filter;
			}
			self::$language_filter = false;
			if ( ! function_exists( 'wp_easycart_language' ) || ! function_exists( 'remove_filter' ) ) {
				return false;
			}
			$marker = '__wpec_el_text_probe__';
			$probe  = function () use ( $marker ) {
				return $marker;
			};
			add_filter( 'wp_easycart_language_text', $probe, PHP_INT_MAX );
			$answer = wp_easycart_language()->get_text( 'cart', 'cart_checkout' );
			remove_filter( 'wp_easycart_language_text', $probe, PHP_INT_MAX );
			self::$language_filter = ( $marker === $answer );
			return self::$language_filter;
		}

		/**
		 * The widget now drawing: its texts replace the store's wording until pop_texts().
		 *
		 * @param array $settings Widget settings.
		 */
		public static function push_texts( $settings ) {
			self::$texts[] = is_array( $settings ) ? $settings : array();
		}

		/** Undoes push_texts(). */
		public static function pop_texts() {
			array_pop( self::$texts );
		}

		/**
		 * A later request of the cart page ( wpec_el_doc / wpec_el_ids ): the texts of the widgets that drew it, read once. The
		 * Checkout widget's win over the Order Confirmation's, which win over the Cart's ( on a page with both, the Checkout
		 * widget draws the one-page checkout ).
		 *
		 * @return bool Whether any widget's settings were found.
		 */
		private static function request_texts() {
			if ( self::$texts_read ) {
				return ! empty( self::$texts );
			}
			if ( ! did_action( 'init' ) ) {
				return false; /* too early to read a saved widget ( Elementor registers its widgets on init ): asked again later */
			}
			self::$texts_read = true;
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- read-only: whether the request names a saved widget ( context_settings() reads it ).
			if ( ! wp_doing_ajax() || empty( $_POST['wpec_el_doc'] ) ) {
				return false;
			}
			foreach ( array( 'wp_easycart_cart', 'wp_easycart_thank_you', 'wp_easycart_checkout' ) as $name ) {
				$settings = self::context_settings( $name );
				if ( is_array( $settings ) ) {
					self::$texts[] = $settings;
				}
			}
			return ! empty( self::$texts );
		}

		/**
		 * Filter wp_easycart_language_text: the text the widget drawing now ( or the one a later request named ) gives a key.
		 *
		 * @param string|null $text    The store's wording ( HTML ).
		 * @param string      $section Language section.
		 * @param string      $key     Language key.
		 * @return string|null
		 */
		public static function language_text( $text, $section = '', $key = '' ) {
			if ( empty( self::$texts ) && ! self::request_texts() ) {
				return $text;
			}
			static $map = null;
			if ( null === $map ) {
				$map = array();
				foreach ( self::text_keys() as $id => $row ) {
					$map[ $row['section'] . '|' . $id ] = $id;
				}
			}
			$lookup = (string) $section . '|' . (string) $key;
			if ( ! isset( $map[ $lookup ] ) ) {
				return $text;
			}
			$setting = 'ec_text_' . $map[ $lookup ];
			for ( $i = count( self::$texts ) - 1; $i >= 0; $i-- ) {
				if ( isset( self::$texts[ $i ][ $setting ] ) && is_string( self::$texts[ $i ][ $setting ] ) && '' !== trim( self::$texts[ $i ][ $setting ] ) ) {
					return esc_html( trim( self::$texts[ $i ][ $setting ] ) ); /* the templates print the store's wording as HTML */
				}
			}
			return $text;
		}

		// Theme copies of the templates ( round 11 ).

		/**
		 * Copies of the cart page templates in the store's data folder ( wp-easycart-data, which EasyCart reads before its
		 * own ) that miss what a widget's switches need: file name => missing parts ( readable ).
		 *
		 * @param string $widget cart | checkout.
		 * @return array
		 */
		public static function template_copy_gaps( $widget ) {
			if ( ! defined( 'EC_PLUGIN_DATA_DIRECTORY' ) ) {
				return array();
			}
			$layout = (string) get_option( 'ec_option_base_layout' );
			if ( '' === $layout || false !== strpos( $layout, '..' ) ) {
				return array();
			}
			$onepage = function_exists( 'wp_easycart_onepage_active' ) && wp_easycart_onepage_active();
			$fields  = array( 'wpeasycart_checkout_fields' => __( 'text between sections', 'wp-easycart' ) );
			$codes   = array(
				'ec_cart_coupon_part'   => __( 'coupon box', 'wp-easycart' ),
				'ec_cart_giftcard_part' => __( 'gift card box', 'wp-easycart' ),
			);
			if ( 'cart' === $widget ) {
				$checks = $onepage ? array() : array( 'ec_cart.php' => array_merge( $codes, array( 'ec_cart_estimate_part' => __( 'shipping estimate', 'wp-easycart' ) ) ) );
			} elseif ( $onepage ) {
				$checks = array(
					'ec_checkout_v2.php'         => array_merge( $codes, array( 'wp_easycart_checkout_details_right_end' => __( 'text below the order summary', 'wp-easycart' ) ) ),
					'ec_cart_information_v2.php' => array_merge( $fields, array( 'ec_cart_notes_part' => __( 'order notes', 'wp-easycart' ) ) ),
					'ec_cart_shipping_v2.php'    => $fields,
					'ec_cart_payment_v2.php'     => $fields,
				);
			} else {
				$checks = array(
					'ec_checkout_details.php'     => array_merge( $fields, $codes, array( 'ec_cart_notes_part' => __( 'order notes', 'wp-easycart' ) ) ),
					'ec_cart_shipping_method.php' => $fields,
					'ec_cart_payment.php'         => $fields,
				);
			}
			$gaps = array();
			foreach ( $checks as $file => $markers ) {
				$path = EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . $layout . '/' . $file;
				if ( ! is_file( $path ) ) {
					continue;
				}
				$source = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local template file, read for its markers ( editor only ).
				$miss   = array();
				foreach ( $markers as $marker => $label ) {
					if ( false === strpos( $source, $marker ) ) {
						$miss[] = $label;
					}
				}
				if ( $miss ) {
					$gaps[ $file ] = array_values( array_unique( $miss ) );
				}
			}
			return $gaps;
		}

		/**
		 * The editor note for template_copy_gaps(), or ''.
		 *
		 * @param string $widget cart | checkout.
		 * @return string
		 */
		public static function template_copy_note( $widget ) {
			$gaps = self::template_copy_gaps( $widget );
			if ( ! $gaps ) {
				return '';
			}
			$parts = array();
			foreach ( $gaps as $file => $miss ) {
				$parts[] = $file . ' ( ' . implode( ', ', $miss ) . ' )';
			}
			/* translators: %s: template file names with what each lacks. */
			return sprintf( __( 'Your store uses older copies of its checkout templates from the wp-easycart-data folder: %s. The widget\'s switches for those parts do nothing there. Update the copies or remove them to use WP EasyCart\'s own.', 'wp-easycart' ), implode( '; ', $parts ) );
		}

		/**
		 * Lines for the editor's samples: the store's first products ( title, picture, price ), else made-up ones.
		 *
		 * @param int $count How many.
		 * @return array Each: title, image, price, quantity, total, options.
		 */
		public static function sample_lines( $count = 2 ) {
			global $wpdb;
			$lines = array();
			$ids   = $wpdb->get_col( $wpdb->prepare( 'SELECT product_id FROM ec_product WHERE activate_in_store = 1 ORDER BY product_id ASC LIMIT %d', (int) $count ) );
			foreach ( (array) $ids as $n => $id ) {
				$product = function_exists( 'wp_easycart_elementor_context' ) ? wp_easycart_elementor_context()->build_product( (int) $id, false ) : null;
				if ( ! $product ) {
					continue;
				}
				$quantity = ( 0 === $n ) ? 1 : 2;
				$lines[]  = array(
					'title'    => trim( wp_strip_all_tags( wp_easycart_language()->convert_text( (string) $product->title ) ) ),
					'image'    => method_exists( $product, 'get_first_image_url' ) ? (string) $product->get_first_image_url() : '',
					'price'    => wp_strip_all_tags( $GLOBALS['currency']->get_currency_display( $product->price ) ),
					'quantity' => $quantity,
					'total'    => wp_strip_all_tags( $GLOBALS['currency']->get_currency_display( $product->price * $quantity ) ),
					'amount'   => (float) $product->price * $quantity,
					'options'  => ( 0 === $n ) ? __( 'Blue, Large', 'wp-easycart' ) : '',
				);
			}
			if ( ! $lines ) {
				$lines = array(
					array(
						'title'    => __( 'Sample product', 'wp-easycart' ),
						'image'    => '',
						'price'    => wp_strip_all_tags( $GLOBALS['currency']->get_currency_display( 24 ) ),
						'quantity' => 1,
						'total'    => wp_strip_all_tags( $GLOBALS['currency']->get_currency_display( 24 ) ),
						'amount'   => 24.0,
						'options'  => __( 'Blue, Large', 'wp-easycart' ),
					),
					array(
						'title'    => __( 'Another product', 'wp-easycart' ),
						'image'    => '',
						'price'    => wp_strip_all_tags( $GLOBALS['currency']->get_currency_display( 12 ) ),
						'quantity' => 2,
						'total'    => wp_strip_all_tags( $GLOBALS['currency']->get_currency_display( 24 ) ),
						'amount'   => 24.0,
						'options'  => '',
					),
				);
			}
			return $lines;
		}

		/**
		 * A price as the store shows it, as text.
		 *
		 * @param float $amount Amount.
		 * @return string
		 */
		public static function price( $amount ) {
			return trim( html_entity_decode( wp_strip_all_tags( $GLOBALS['currency']->get_currency_display( $amount ) ), ENT_QUOTES, 'UTF-8' ) );
		}

		// Settings › Elementor.

		/**
		 * Filter wp_easycart_settings_page_elementor: a Cart & checkout section.
		 *
		 * @param array $page Page declaration.
		 * @return array
		 */
		public static function settings_section( $page ) {
			if ( ! is_array( $page ) ) {
				return $page;
			}
			if ( ! isset( $page['sections'] ) || ! is_array( $page['sections'] ) ) {
				$page['sections'] = array();
			}
			$page['sections']['cart-checkout'] = array(
				'title'  => __( 'Cart and checkout', 'wp-easycart' ),
				'hint'   => __( 'Where the Cart, Checkout and Order Confirmation widgets go, and what they show.', 'wp-easycart' ),
				'icon'   => 'cart',
				'fields' => array(),
				'render' => array( __CLASS__, 'render_settings' ),
			);
			return $page;
		}

		/** Settings › Elementor › Cart and checkout. */
		public static function render_settings() {
			$cart_page = self::cart_page_id();
			$onepage   = function_exists( 'wp_easycart_onepage_active' ) && wp_easycart_onepage_active();
			echo '<div class="ecst-elementor-cart">';
			if ( $cart_page ) {
				/* translators: %s: the cart page's title. */
				echo '<p>' . esc_html( sprintf( __( 'Your cart page is "%s". Put the Cart and Checkout widgets on it ( and the Order Confirmation widget, which shows after an order is placed ): it holds the cart, every checkout step and the order confirmation at one address, and each widget shows when its part is open.', 'wp-easycart' ), get_the_title( $cart_page ) ) ) . ' ';
				if ( current_user_can( 'edit_post', $cart_page ) ) {
					echo '<a href="' . esc_url( admin_url( 'post.php?post=' . (int) $cart_page . '&action=elementor' ) ) . '">' . esc_html__( 'Edit the cart page with Elementor', 'wp-easycart' ) . '</a>';
				}
				echo '</p>';
			} else {
				echo '<p>' . esc_html__( 'Choose your cart page first: the Cart and Checkout widgets show there.', 'wp-easycart' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=wp-easycart-settings&subpage=initial-setup' ) ) . '">' . esc_html__( 'Choose the cart page', 'wp-easycart' ) . '</a></p>';
			}
			echo '<p>' . esc_html( $onepage ? __( 'The Checkout widget shows your one-page checkout.', 'wp-easycart' ) : __( 'The Checkout widget shows your checkout in steps ( details, shipping, payment ).', 'wp-easycart' ) ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=wp-easycart-settings&subpage=checkout' ) ) . '">' . esc_html__( 'Checkout settings', 'wp-easycart' ) . '</a></p>';
			echo '<p>' . esc_html__( 'The Menu Cart and Order Summary widgets work on any page; the Menu Cart fits a header template.', 'wp-easycart' ) . '</p>';
			echo '</div>';
		}
	}

endif;
