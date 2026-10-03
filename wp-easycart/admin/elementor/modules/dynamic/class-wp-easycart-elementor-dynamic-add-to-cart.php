<?php
/**
 * Add to cart from any link ( 6.0.2 ): the "Add to cart URL" dynamic tag's links.
 *
 *     /?wpec_add_to_cart=<product id>&quantity=<n>              adds the product, then opens the cart
 *     /?wpec_add_to_cart=<product id>&quantity=<n>&wpec_stay=1  adds it, then returns to the page with a short note
 *
 * Only products a link can add in one step: active, the store open to the shopper, no options to choose, not a subscription,
 * gift card, donation or DecoNetwork product, not in catalog or inquiry mode, a price the shopper may see, and in stock ( or
 * on backorder ). Anything else goes to the product page, where the shopper can choose. The request always ends in a
 * redirect ( post / redirect / get ), so reloading the page the shopper lands on never adds the product again; the note
 * shows once, and only after a real add ( a short-lived transient keyed to the shopper's cart ).
 *
 * Nothing is added for requests no shopper made ( 6.0.2 review hardening ): a browser prefetching or prerendering the link
 * ( Sec-Purpose / Purpose / X-Purpose / X-Moz ) gets a 503 it will not reuse for the real click, and an obvious bot is sent to
 * the product page ( automated_request(), filter wp_easycart_elementor_add_to_cart_link_automated ); robots.txt disallows the
 * link pattern. A link adds at most max_quantity() ( 20, filter wp_easycart_elementor_add_to_cart_link_max_quantity ), never
 * more than the stock left when stock is counted, so another site can't fill a cart through an image. The cart session is only
 * started once every check passed, right before the add.
 *
 * The older ?ec_add_to_cart=<model number> links in wpeasycart.php keep working unchanged.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Dynamic_Add_To_Cart' ) ) :

	/**
	 * The add to cart link handler and its note.
	 */
	final class WP_EasyCart_Elementor_Dynamic_Add_To_Cart {

		/**
		 * Query argument naming the product.
		 */
		const ARG = 'wpec_add_to_cart';

		/**
		 * Query argument asking to stay on the page.
		 */
		const STAY_ARG = 'wpec_stay';

		/**
		 * Query argument on the page the shopper returns to.
		 */
		const ADDED_ARG = 'wpec_added';

		/**
		 * Largest quantity any link setting may ask for.
		 */
		const MAX_QUANTITY = 999;

		/**
		 * Largest quantity a link adds unless filtered ( wp_easycart_elementor_add_to_cart_link_max_quantity ).
		 */
		const DEFAULT_LINK_QUANTITY = 20;

		/**
		 * User agents treated as bots ( lower case ): named crawlers and link previewers, the "+http://" contact address bots
		 * put in their agent, headless browsers and HTTP libraries. A plain "bot" is not enough ( a CUBOT phone ).
		 */
		const BOT_PATTERN = '#googlebot|bingbot|bingpreview|yandex|baiduspider|duckduckbot|slurp|applebot|petalbot|ahrefsbot|semrushbot|mj12bot|dotbot|bytespider|gptbot|oai-searchbot|chatgpt-user|claudebot|claude-user|perplexitybot|ccbot|amazonbot|facebookexternalhit|facebot|twitterbot|linkedinbot|slackbot|discordbot|telegrambot|whatsapp|pinterestbot|skypeuripreview|crawler|spider|headlesschrome|lighthouse|\+https?://|^curl/|^wget/|python-requests|python-urllib|go-http-client|^java/|okhttp|libwww-perl#';

		/**
		 * The product whose note this request prints ( id ), 0 for none.
		 *
		 * @var int
		 */
		private static $notice_product = 0;

		/**
		 * Hooks ( once ).
		 */
		public static function init() {
			static $done = false;
			if ( $done ) {
				return;
			}
			$done = true;
			add_action( 'template_redirect', array( __CLASS__, 'handle' ), 5 );
			add_action( 'template_redirect', array( __CLASS__, 'prepare_notice' ), 6 );
			add_action( 'wp_footer', array( __CLASS__, 'print_notice' ), 5 );
			add_filter( 'robots_txt', array( __CLASS__, 'robots_txt' ), 20, 2 );
		}

		/**
		 * Largest quantity one link adds.
		 *
		 * @return int
		 */
		public static function max_quantity() {
			/**
			 * Largest quantity an add to cart link adds ( 1 to 999, default 20 ).
			 *
			 * @since 6.0.2
			 *
			 * @param int $quantity Quantity.
			 */
			return max( 1, min( self::MAX_QUANTITY, (int) apply_filters( 'wp_easycart_elementor_add_to_cart_link_max_quantity', self::DEFAULT_LINK_QUANTITY ) ) );
		}

		/**
		 * The quantity a link adds: at least 1, at most max_quantity(), raised to the product's minimum, capped at its maximum
		 * and, when its stock is counted without backorders, at the units left.
		 *
		 * @param ec_product $product   Product.
		 * @param int        $requested Quantity in the link.
		 * @return int
		 */
		public static function link_quantity( $product, $requested ) {
			$quantity = max( 1, min( self::max_quantity(), (int) $requested ) );
			if ( (int) $product->min_purchase_quantity > $quantity ) {
				$quantity = (int) $product->min_purchase_quantity;
			}
			if ( (int) $product->max_purchase_quantity > 0 && $quantity > (int) $product->max_purchase_quantity ) {
				$quantity = (int) $product->max_purchase_quantity;
			}
			$stock = WP_EasyCart_Elementor_Dynamic::stock( $product );
			if ( $stock['tracked'] && empty( $product->allow_backorders ) && $quantity > (int) $stock['quantity'] ) {
				$quantity = (int) $stock['quantity'];
			}
			return $quantity;
		}

		/**
		 * Whether this request was made by no shopper: 'prefetch' ( a browser prefetching or prerendering the link ), 'bot'
		 * ( a crawler, a link previewer, a script, or no user agent at all ), else ''.
		 *
		 * @return string
		 */
		public static function automated_request() {
			$purpose = '';
			foreach ( array( 'HTTP_SEC_PURPOSE', 'HTTP_PURPOSE', 'HTTP_X_PURPOSE', 'HTTP_X_MOZ' ) as $header ) {
				if ( isset( $_SERVER[ $header ] ) && is_string( $_SERVER[ $header ] ) ) {
					$purpose .= ' ' . strtolower( sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) );
				}
			}
			$agent  = ( isset( $_SERVER['HTTP_USER_AGENT'] ) && is_string( $_SERVER['HTTP_USER_AGENT'] ) ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) ) : '';
			$reason = '';
			if ( preg_match( '/prefetch|prerender|preview/', $purpose ) ) {
				$reason = 'prefetch';
			} elseif ( '' === trim( $agent ) || preg_match( self::BOT_PATTERN, $agent ) ) {
				$reason = 'bot';
			}
			/**
			 * Whether an add to cart link request came from no shopper ( nothing is added ): 'prefetch', 'bot' or ''.
			 *
			 * @since 6.0.2
			 *
			 * @param string $reason 'prefetch' ( answered 503 ), 'bot' ( sent to the product page ) or '' ( a shopper ).
			 * @param string $agent  The user agent, lower case.
			 */
			return (string) apply_filters( 'wp_easycart_elementor_add_to_cart_link_automated', $reason, $agent );
		}

		/**
		 * Keeps crawlers off add to cart links ( the virtual robots.txt ): the rules join the "User-agent: *" group.
		 *
		 * @param string $output    robots.txt.
		 * @param string $is_public Whether the site is public ( '0' already disallows everything ).
		 * @return string
		 */
		public static function robots_txt( $output, $is_public ) {
			$output = (string) $output;
			if ( '0' === (string) $is_public || false !== strpos( $output, self::ARG . '=' ) ) {
				return $output;
			}
			$eol   = ( false !== strpos( $output, "\r\n" ) ) ? "\r\n" : "\n";
			$rules = 'Disallow: /*?' . self::ARG . '=' . $eol . 'Disallow: /*&' . self::ARG . '=';
			if ( preg_match( '/^User-agent:[ \t]*\*[ \t]*(?=\r?$)/mi', $output, $match, PREG_OFFSET_CAPTURE ) ) {
				$at = $match[0][1] + strlen( $match[0][0] );
				return substr( $output, 0, $at ) . $eol . $rules . substr( $output, $at );
			}
			return rtrim( $output ) . ( '' !== trim( $output ) ? $eol . $eol : '' ) . 'User-agent: *' . $eol . $rules . $eol;
		}

		/**
		 * Whether a link can add this product in one step.
		 *
		 * @param ec_product $product Product.
		 * @return bool
		 */
		public static function can_add( $product ) {
			if ( ! is_object( $product ) || empty( $product->product_id ) || empty( $product->activate_in_store ) ) {
				return false;
			}
			if ( ! empty( $product->is_subscription_item ) || ! empty( $product->is_giftcard ) || ! empty( $product->is_donation ) || ! empty( $product->is_deconetwork ) ) {
				return false;
			}
			if ( ! empty( $product->is_catalog_mode ) || ! empty( $product->is_inquiry_mode ) ) {
				return false;
			}
			/* The whole store shown as a catalog ( vacation mode ) sells nothing, as every add to cart button follows. */
			if ( apply_filters( 'wp_easycart_catalog_display', get_option( 'ec_option_display_as_catalog' ) ) ) {
				return false;
			}
			/* A product the shopper's chosen pickup location does not carry. */
			if ( get_option( 'ec_option_pickup_enable_locations' ) && get_option( 'ec_option_pickup_location_select_enabled' ) && method_exists( $product, 'at_current_location' ) && ! $product->at_current_location() ) {
				return false;
			}
			if ( ! empty( $product->login_for_pricing ) && ( ! method_exists( $product, 'is_login_for_pricing_valid' ) || ! $product->is_login_for_pricing_valid() ) ) {
				return false;
			}
			if ( method_exists( $product, 'has_options' ) && $product->has_options() ) {
				return false;
			}
			$stock = WP_EasyCart_Elementor_Dynamic::stock( $product );
			$can   = $stock['in_stock'] || $stock['backorder'];
			/**
			 * Whether an add to cart link may add this product in one step ( false sends the shopper to the product page ).
			 *
			 * @since 6.0.2
			 *
			 * @param bool       $can     The rule's answer.
			 * @param ec_product $product Product.
			 */
			return (bool) apply_filters( 'wp_easycart_elementor_add_to_cart_link_allowed', $can, $product );
		}

		/**
		 * The link for a product: the add to cart address, or its product page when a link can't add it.
		 *
		 * @param ec_product $product  Product.
		 * @param int        $quantity Quantity.
		 * @param bool       $stay     Return to the page instead of opening the cart.
		 * @return string
		 */
		public static function url( $product, $quantity = 1, $stay = false ) {
			if ( ! is_object( $product ) || empty( $product->product_id ) ) {
				return '';
			}
			if ( ! self::can_add( $product ) ) {
				return method_exists( $product, 'get_product_link' ) ? esc_url_raw( (string) $product->get_product_link() ) : '';
			}
			$args = array(
				self::ARG  => (int) $product->product_id,
				'quantity' => max( 1, min( self::max_quantity(), (int) $quantity ) ),
			);
			if ( $stay ) {
				$args[ self::STAY_ARG ] = 1;
			}
			return esc_url_raw( add_query_arg( $args, home_url( '/' ) ) );
		}

		/**
		 * The link handler ( template_redirect ): adds the product and redirects.
		 */
		public static function handle() {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- a public add to cart link ( like ?ec_add_to_cart= ): it only adds a valid product to the visitor's own cart, and a page cache would serve an expired nonce.
			if ( ! isset( $_GET[ self::ARG ] ) || is_admin() ) {
				return;
			}
			$product_id = absint( wp_unslash( $_GET[ self::ARG ] ) );
			$quantity   = isset( $_GET['quantity'] ) ? absint( wp_unslash( $_GET['quantity'] ) ) : 1;
			$stay       = ! empty( $_GET[ self::STAY_ARG ] );
			// phpcs:enable WordPress.Security.NonceVerification.Recommended

			nocache_headers();
			if ( ! headers_sent() ) {
				header( 'X-Robots-Tag: noindex, nofollow' );
			}

			$automated = self::automated_request();
			if ( 'prefetch' === $automated ) {
				/* A prefetch or prerender: a non-2xx answer the browser discards, so the shopper's real click adds the product. */
				status_header( 503 );
				exit;
			}

			$product = ( $product_id && WP_EasyCart_Elementor_Dynamic::store_visible() && function_exists( 'wp_easycart_elementor_context' ) ) ? wp_easycart_elementor_context()->build_product( $product_id, false ) : null;
			if ( ! is_object( $product ) || empty( $product->product_id ) ) {
				self::redirect( self::tagged( self::store_url() ) );
			}
			$product_url = method_exists( $product, 'get_product_link' ) ? (string) $product->get_product_link() : self::store_url();
			if ( '' !== $automated || ! self::can_add( $product ) || ! class_exists( 'wp_easycart_cart_link' ) || ! method_exists( 'wp_easycart_cart_link', 'add_item' ) || ! function_exists( 'wpeasycart_session' ) ) {
				self::redirect( self::tagged( $product_url ) ); /* a bot reads the product page; no cart is made for it */
			}

			$quantity = self::link_quantity( $product, $quantity );
			if ( $quantity < 1 ) {
				self::redirect( self::tagged( $product_url ) );
			}

			/* Every check passed: only now is a cart session started ( a new visitor's cookie and cart ), right before the add. */
			wpeasycart_session()->handle_session();
			/* Announced as a link add ( source 'link' ), like ?ec_add_to_cart= links. */
			$added = wp_easycart_cart_link::add_item(
				array(
					'product_id' => (int) $product->product_id,
					'quantity'   => $quantity,
				),
				'link'
			);
			if ( ! $added ) {
				self::redirect( self::tagged( $product_url ) );
			}
			do_action( 'wpeasycart_cart_updated' );
			WP_EasyCart_Elementor_Dynamic::forget_cart();

			if ( $stay ) {
				if ( '' !== self::notice_key() ) {
					set_transient( self::notice_key(), (int) $product->product_id, 2 * MINUTE_IN_SECONDS );
				}
				$referer = wp_get_referer();
				$back    = $referer ? wp_validate_redirect( $referer, $product_url ) : $product_url;
				$back    = remove_query_arg( array( self::ARG, self::STAY_ARG, 'quantity', self::ADDED_ARG ), ( '' !== (string) $back ) ? $back : $product_url );
				self::redirect( add_query_arg( self::ADDED_ARG, (int) $product->product_id, $back ) );
			}

			$cart = function_exists( 'wpeasycart_links' ) ? (string) wpeasycart_links()->get_cart_page() : home_url( '/' );
			if ( function_exists( 'wp_easycart_with_source_tags' ) ) {
				$cart = wp_easycart_with_source_tags( $cart );
			}
			/** This filter is documented in wpeasycart.php ( the store's own add to cart links ). */
			$cart = apply_filters( 'wp_easycart_add_to_cart_return_url_cart', $cart, 0, (int) $product->product_id );
			self::redirect( $cart );
		}

		/**
		 * The address with the link's campaign tags and click IDs, so the order's source is recorded where the shopper lands
		 * ( a product that can't be added in one step: subscriptions, products with options, out of stock ).
		 *
		 * @since 6.0.2
		 * @param string $url Where to.
		 * @return string
		 */
		private static function tagged( $url ) {
			return function_exists( 'wp_easycart_with_source_tags' ) ? wp_easycart_with_source_tags( $url ) : $url;
		}

		/**
		 * Redirects and ends the request.
		 *
		 * @param string $url Where to.
		 */
		private static function redirect( $url ) {
			wp_safe_redirect( ( is_string( $url ) && '' !== $url ) ? $url : home_url( '/' ) );
			exit;
		}

		/**
		 * The store page ( or the home page ).
		 *
		 * @return string
		 */
		private static function store_url() {
			$store = (int) get_option( 'ec_option_storepage' );
			$url   = $store ? get_permalink( $store ) : '';
			return $url ? (string) $url : home_url( '/' );
		}

		/**
		 * The note's transient for this shopper's cart ( '' without a cart ).
		 *
		 * @return string
		 */
		private static function notice_key() {
			$cart_id = ( isset( $GLOBALS['ec_cart_data'] ) && is_object( $GLOBALS['ec_cart_data'] ) && isset( $GLOBALS['ec_cart_data']->ec_cart_id ) ) ? (string) $GLOBALS['ec_cart_data']->ec_cart_id : '';
			if ( '' === $cart_id || 'not-set' === $cart_id ) {
				return '';
			}
			return 'wpec_el_added_' . md5( $cart_id );
		}

		/**
		 * On the page the shopper returns to: shows the note once, only when this shopper's cart just got that product.
		 */
		public static function prepare_notice() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read only: the note shows only when the shopper's own transient matches.
			$product_id = isset( $_GET[ self::ADDED_ARG ] ) ? absint( wp_unslash( $_GET[ self::ADDED_ARG ] ) ) : 0;
			if ( ! $product_id || is_admin() ) {
				return;
			}
			$key = self::notice_key();
			if ( '' === $key || (int) get_transient( $key ) !== $product_id ) {
				return;
			}
			delete_transient( $key );
			self::$notice_product = $product_id;
			WP_EasyCart_Elementor_Dynamic::no_page_cache();
			nocache_headers();
			WP_EasyCart_Elementor_Dynamic::enqueue_assets( true );
		}

		/**
		 * Prints the "added to your cart" note ( wp_footer ).
		 */
		public static function print_notice() {
			if ( ! self::$notice_product ) {
				return;
			}
			$product = function_exists( 'wp_easycart_elementor_context' ) ? wp_easycart_elementor_context()->build_product( self::$notice_product, false ) : null;
			$title   = is_object( $product ) ? WP_EasyCart_Elementor_Dynamic::plain( $product->title, 150 ) : '';
			$message = WP_EasyCart_Elementor_Dynamic::text( 'ec_success', 'store_added_to_cart', __( 'You have successfully added [prod_title] to your cart.', 'wp-easycart' ) );
			$message = ( '' !== $title ) ? str_replace( '[prod_title]', $title, $message ) : WP_EasyCart_Elementor_Dynamic::text( 'product_page', 'product_product_added_note', __( 'Product successfully added to your cart.', 'wp-easycart' ) );
			$cart    = function_exists( 'wpeasycart_links' ) ? (string) wpeasycart_links()->get_cart_page() : '';
			echo '<div class="wpec-dyn-added" role="status" aria-live="polite" data-wpec-dyn-added>';
			echo '<p class="wpec-dyn-added__text">' . esc_html( wp_strip_all_tags( $message ) ) . '</p>';
			if ( '' !== $cart ) {
				echo '<a class="wpec-dyn-added__link" href="' . esc_url( $cart ) . '">' . esc_html( WP_EasyCart_Elementor_Dynamic::text( 'product_page', 'product_view_cart', __( 'View Cart', 'wp-easycart' ) ) ) . '</a>';
			}
			echo '<button type="button" class="wpec-dyn-added__close" data-wpec-dyn-close aria-label="' . esc_attr( WP_EasyCart_Elementor_Dynamic::text( 'customer_review', 'customer_review_close_button', __( 'Close', 'wp-easycart' ) ) ) . '"><span aria-hidden="true">&times;</span></button>';
			echo '</div>';
		}
	}

endif;
