<?php
/**
 * The store's own add to cart links ( 6.0.5 ): ?ec_add_to_cart=<model number> ( a buy link with its options in the address ) and
 * ?ec_action=addtocart&model_number=<model number> ( the store list's Add to Cart link, and where a background add goes when its
 * page was cached past its nonce ).
 *
 * Both take an optional quantity ( &quantity=4, issue #464: a booking form sends the customer to the cart with the number of
 * places ): missing or 0 adds 1, as before; it is raised to the product's minimum, capped at its maximum and at max_quantity()
 * ( 20, filter wp_easycart_add_to_cart_link_max_quantity ), so another site can't fill a cart through an image, and
 * ec_db::quick_add_to_cart() caps it at the stock left. A link into a line already in the cart adds the quantity to it.
 *
 * Nothing is added for requests no shopper made, as the 6.0.2 ?wpec_add_to_cart= link: a browser prefetching or prerendering the
 * link gets a 503 it will not reuse for the real click, and an obvious bot ( a crawler, a link previewer, a script ) opens the
 * product page ( automated(), filter wp_easycart_add_to_cart_link_automated ); no cart session is started for either.
 *
 * @package WP EasyCart
 * @since 6.0.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_add_to_cart_links' ) ) :

	/**
	 * Quantity and request rules of the add to cart links.
	 */
	class wp_easycart_add_to_cart_links {

		/** Largest quantity the filter may allow. */
		const MAX_QUANTITY = 999;

		/** Largest quantity a link adds unless filtered ( wp_easycart_add_to_cart_link_max_quantity ). */
		const DEFAULT_QUANTITY = 20;

		/** The query argument carrying the quantity. */
		const QUANTITY_ARG = 'quantity';

		/**
		 * User agents treated as bots ( lower case ): named crawlers and link previewers, the "+http://" contact address bots put
		 * in their agent, headless browsers and HTTP libraries. A plain "bot" is not enough ( a CUBOT phone ). The same list as the
		 * Elementor "Add to cart URL" link ( WP_EasyCart_Elementor_Dynamic_Add_To_Cart::BOT_PATTERN ): change both together.
		 */
		const BOT_PATTERN = '#googlebot|bingbot|bingpreview|yandex|baiduspider|duckduckbot|slurp|applebot|petalbot|ahrefsbot|semrushbot|mj12bot|dotbot|bytespider|gptbot|oai-searchbot|chatgpt-user|claudebot|claude-user|perplexitybot|ccbot|amazonbot|facebookexternalhit|facebot|twitterbot|linkedinbot|slackbot|discordbot|telegrambot|whatsapp|pinterestbot|skypeuripreview|crawler|spider|headlesschrome|lighthouse|\+https?://|^curl/|^wget/|python-requests|python-urllib|go-http-client|^java/|okhttp|libwww-perl#';

		/**
		 * Largest quantity one link adds.
		 *
		 * @return int
		 */
		public static function max_quantity() {
			/**
			 * Largest quantity an ?ec_add_to_cart= or ?ec_action=addtocart link adds ( 1 to 999, default 20 ).
			 *
			 * @since 6.0.5
			 *
			 * @param int $quantity Quantity.
			 */
			return max( 1, min( self::MAX_QUANTITY, (int) apply_filters( 'wp_easycart_add_to_cart_link_max_quantity', self::DEFAULT_QUANTITY ) ) );
		}

		/**
		 * The quantity the link asks for: its &quantity=, or 1 when it has none, it is 0 or not a number, or one of the product's
		 * options reads that argument itself ( an option set whose URL variable is "quantity" ).
		 *
		 * @param array $url_vars The URL variables the product's options read.
		 * @return int
		 */
		public static function requested( $url_vars = array() ) {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- a public add to cart link: it only adds a valid product to the visitor's own cart, and a page cache would serve an expired nonce.
			if ( ! isset( $_GET[ self::QUANTITY_ARG ] ) || is_array( $_GET[ self::QUANTITY_ARG ] ) || in_array( self::QUANTITY_ARG, (array) $url_vars, true ) ) {
				return 1;
			}
			$quantity = absint( wp_unslash( $_GET[ self::QUANTITY_ARG ] ) );
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			return max( 1, $quantity );
		}

		/**
		 * The quantity a link adds: at least 1, at most max_quantity(), raised to the product's minimum and capped at its maximum
		 * ( the stock left is ec_db::quick_add_to_cart()'s ).
		 *
		 * @param object $product   An ec_product row ( min_purchase_quantity, max_purchase_quantity ).
		 * @param int    $requested The quantity asked for.
		 * @return int
		 */
		public static function quantity( $product, $requested ) {
			$quantity = max( 1, min( self::max_quantity(), (int) $requested ) );
			if ( is_object( $product ) ) {
				if ( isset( $product->min_purchase_quantity ) && (int) $product->min_purchase_quantity > $quantity ) {
					$quantity = (int) $product->min_purchase_quantity;
				}
				if ( isset( $product->max_purchase_quantity ) && (int) $product->max_purchase_quantity > 0 && $quantity > (int) $product->max_purchase_quantity ) {
					$quantity = (int) $product->max_purchase_quantity;
				}
			}
			return max( 1, $quantity );
		}

		/**
		 * Whether this request was made by no shopper: 'prefetch' ( a browser prefetching or prerendering the link ), 'bot' ( a
		 * crawler, a link previewer, a script, or no user agent at all ), else ''.
		 *
		 * @return string
		 */
		public static function automated() {
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
			 * Whether an ?ec_add_to_cart= or ?ec_action=addtocart request came from no shopper ( nothing is added ): 'prefetch',
			 * 'bot' or ''.
			 *
			 * @since 6.0.5
			 *
			 * @param string $reason 'prefetch' ( answered 503 ), 'bot' ( sent to the product page ) or '' ( a shopper ).
			 * @param string $agent  The user agent, lower case.
			 */
			return (string) apply_filters( 'wp_easycart_add_to_cart_link_automated', $reason, $agent );
		}

		/**
		 * Stops a request no shopper made before any cart session starts: a prefetch gets a 503 ( the browser discards it, so the
		 * real click adds the product ), a bot opens the product page. Returns for a shopper.
		 *
		 * @param string $product_url Where a bot goes.
		 */
		public static function guard( $product_url ) {
			$automated = self::automated();
			if ( '' === $automated ) {
				return;
			}
			nocache_headers();
			if ( ! headers_sent() ) {
				header( 'X-Robots-Tag: noindex, nofollow' );
			}
			if ( 'prefetch' === $automated ) {
				status_header( 503 );
				exit;
			}
			wp_safe_redirect( esc_url_raw( $product_url ), 302 );
			exit;
		}

		/**
		 * The URL variables a product's options read from an ?ec_add_to_cart= link.
		 *
		 * @param object $product          An ec_product row.
		 * @param array  $advanced_options The product's option sets ( ec_advanced_optionsets ).
		 * @return array
		 */
		public static function url_vars( $product, $advanced_options ) {
			$vars = array();
			foreach ( (array) $advanced_options as $option ) {
				if ( is_object( $option ) && isset( $option->option_meta['url_var'] ) && '' !== (string) $option->option_meta['url_var'] ) {
					$vars[] = (string) $option->option_meta['url_var'];
				}
			}
			if ( is_object( $product ) && isset( $GLOBALS['ec_options'] ) && is_object( $GLOBALS['ec_options'] ) ) {
				for ( $i = 1; $i <= 5; $i++ ) {
					$option_id = isset( $product->{ 'option_id_' . $i } ) ? (int) $product->{ 'option_id_' . $i } : 0;
					if ( $option_id ) {
						$option = $GLOBALS['ec_options']->get_option( $option_id );
						if ( is_object( $option ) && isset( $option->option_meta['url_var'] ) && '' !== (string) $option->option_meta['url_var'] ) {
							$vars[] = (string) $option->option_meta['url_var'];
						}
					}
				}
			}
			return $vars;
		}
	}

endif;
