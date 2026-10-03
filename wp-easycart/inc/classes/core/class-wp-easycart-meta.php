<?php
/**
 * WP EasyCart — Meta ( Facebook & Instagram ): content IDs, the browser Pixel and storefront event announcements ( 6.0.2 ).
 *
 * Content IDs ( the IDs a Meta catalog, the Pixel and the Conversions API share ):
 *  - a product without variant rows is its product_id;
 *  - a variant ( a row of ec_optionitemquantity, only on products that track stock per option combination and only rows
 *    that are enabled ) is {product_id}_{optionitemquantity_id}, grouped under item_group_id = product_id.
 *  wp_easycart_meta_content_id(), wp_easycart_meta_variant_id(), wp_easycart_meta_parse_content_id().
 *
 * Browser Pixel ( FREE, runs whenever a Pixel ID is saved ): the base code ( store pages, or every page with
 * ec_option_fb_pixel_sitewide ), Limited Data Use, advanced matching ( hashed email and customer ID ), consent ( while
 * cookie consent is asked, Meta's script is not loaded and the Pixel is revoked before init until the shopper agrees; the
 * consent bridge's answer grants it and loads the script ) and every event call with an
 * eventID. Every storefront event is also announced so WP EasyCart PRO can send the same event from the server with the
 * same ID:
 *
 *   do_action( 'wp_easycart_meta_event', $event_name, $custom_data, $event_id, $context );
 *
 * ViewContent ( product details ), Search ( store search results ), AddToCart ( every add path, through
 * wpeasycart_cart_item_added ), InitiateCheckout ( once per checkout session, when checkout starts ), AddPaymentInfo ( once
 * per checkout session, when the payment step shows ) and CompleteRegistration ( storefront sign-ups ). Purchase is never
 * announced here: the success page fires the browser Purchase with eventID order_{order_id} and PRO sends the server
 * Purchase from wpeasycart_order_paid with the same ID.
 *
 * Add to cart: every add path takes a snapshot of the product's quantity in the session cart before the add and calls
 * wp_easycart_announce_cart_item_added() after it, which fires
 *
 *   do_action( 'wpeasycart_cart_item_added', $tempcart_id, $product_id, $quantity_added, $unit_price, $args );
 *
 * Consent: wp_easycart_has_marketing_consent() ( wp_easycart_consent: ec_option_marketing_consent off | wp_consent_api |
 * google_consent_mode | cookiebot | cookieyes | complianz, filter
 * wpeasycart_marketing_consent ). Google consent mode lives in ec-store.js.
 *
 * Loaded from inc/ec_config.php on every request.
 *
 * @since 6.0.2
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wp_easycart_meta_content_id' ) ) {
	/**
	 * The content ID of a product or one of its variants.
	 *
	 * @since 6.0.2
	 * @param int $product_id            Product.
	 * @param int $optionitemquantity_id Variant row ( ec_optionitemquantity ), 0 for the product itself.
	 * @return string
	 */
	function wp_easycart_meta_content_id( $product_id, $optionitemquantity_id = 0 ) {
		$product_id            = (int) $product_id;
		$optionitemquantity_id = (int) $optionitemquantity_id;
		$content_id            = ( $optionitemquantity_id > 0 ) ? $product_id . '_' . $optionitemquantity_id : (string) $product_id;
		/**
		 * The content ID a product or variant uses in the catalog, the Pixel and the Conversions API.
		 *
		 * @since 6.0.2
		 * @param string $content_id            {product_id} or {product_id}_{optionitemquantity_id}.
		 * @param int    $product_id            Product.
		 * @param int    $optionitemquantity_id Variant row, 0 for the product.
		 */
		return (string) apply_filters( 'wp_easycart_meta_content_id', $content_id, $product_id, $optionitemquantity_id );
	}
}

if ( ! function_exists( 'wp_easycart_meta_variant_id' ) ) {
	/**
	 * The variant row for a choice of basic options, when the product has variants.
	 *
	 * @since 6.0.2
	 * @param int          $product_id     Product.
	 * @param array|object $optionitem_ids Up to five optionitem IDs in slot order ( a list ), or keys / properties
	 *                                     optionitem_id_1 … optionitem_id_5 ( a cart or order row ).
	 * @return int optionitemquantity_id, or 0 when the product tracks no variants or the choice is not an enabled variant.
	 */
	function wp_easycart_meta_variant_id( $product_id, $optionitem_ids ) {
		$map = wp_easycart_meta::variant_map( $product_id );
		if ( empty( $map ) ) {
			return 0;
		}
		$key = wp_easycart_meta::variant_key( $optionitem_ids );
		return isset( $map[ $key ] ) ? (int) $map[ $key ]['id'] : 0;
	}
}

if ( ! function_exists( 'wp_easycart_meta_parse_content_id' ) ) {
	/**
	 * Read a content ID back ( a Shops checkout link, a catalog item ). Only checks the shape: the caller decides whether
	 * the product and the variant still exist and belong together.
	 *
	 * @since 6.0.2
	 * @param string $content_id Content ID.
	 * @return array|false array( product_id, optionitemquantity_id ) ( also keyed 'product_id' and
	 *                     'optionitemquantity_id' ), or false.
	 */
	function wp_easycart_meta_parse_content_id( $content_id ) {
		$content_id = trim( (string) $content_id );
		$parsed     = false;
		if ( preg_match( '/^([0-9]{1,11})(?:_([0-9]{1,11}))?$/', $content_id, $match ) && (int) $match[1] > 0 ) {
			$product_id            = (int) $match[1];
			$optionitemquantity_id = isset( $match[2] ) ? (int) $match[2] : 0;
			$parsed                = array(
				0                       => $product_id,
				1                       => $optionitemquantity_id,
				'product_id'            => $product_id,
				'optionitemquantity_id' => $optionitemquantity_id,
			);
		}
		/**
		 * Read a content ID back. Sites that filter wp_easycart_meta_content_id into another shape answer here.
		 *
		 * @since 6.0.2
		 * @param array|false $parsed     See wp_easycart_meta_parse_content_id().
		 * @param string      $content_id The content ID.
		 */
		return apply_filters( 'wp_easycart_meta_parse_content_id', $parsed, $content_id );
	}
}

if ( ! function_exists( 'wp_easycart_meta_pixel_id' ) ) {
	/**
	 * The store's Meta Pixel ID ( Settings › Integrations › Meta Pixel ), '' when none is set.
	 *
	 * @since 6.0.2
	 * @return string Digits only.
	 */
	function wp_easycart_meta_pixel_id() {
		$pixel_id = preg_replace( '/[^0-9]/', '', trim( (string) get_option( 'ec_option_fb_pixel', '' ) ) );
		/**
		 * The Meta Pixel ID the storefront uses.
		 *
		 * @since 6.0.2
		 * @param string $pixel_id Digits, or ''.
		 */
		return preg_replace( '/[^0-9]/', '', (string) apply_filters( 'wp_easycart_meta_pixel_id', $pixel_id ) );
	}
}

if ( ! function_exists( 'wp_easycart_meta_event_id' ) ) {
	/**
	 * A new event ID: the prefix, an underscore and 24 random lowercase hex characters ( e.g. vc_5f3a… ).
	 *
	 * @since 6.0.2
	 * @param string $prefix Short event prefix ( [a-z0-9] ).
	 * @return string
	 */
	function wp_easycart_meta_event_id( $prefix = 'ev' ) {
		$prefix = preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $prefix ) );
		if ( '' === $prefix ) {
			$prefix = 'ev';
		}
		try {
			$random = bin2hex( random_bytes( 12 ) );
		} catch ( Exception $e ) {
			$random = substr( md5( uniqid( (string) wp_rand(), true ) ), 0, 24 );
		}
		return $prefix . '_' . $random;
	}
}

if ( ! function_exists( 'wp_easycart_meta_currency' ) ) {
	/**
	 * The store currency code. Every amount sent to Meta is in this currency, never a shopper's converted one.
	 *
	 * @since 6.0.2
	 * @return string Three letters.
	 */
	function wp_easycart_meta_currency() {
		$currency = strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', (string) get_option( 'ec_option_base_currency', 'USD' ) ), 0, 3 ) );
		if ( 3 !== strlen( $currency ) ) {
			$currency = 'USD';
		}
		/**
		 * The currency code sent with Meta events.
		 *
		 * @since 6.0.2
		 * @param string $currency ISO 4217 code of the store currency.
		 */
		return (string) apply_filters( 'wp_easycart_meta_currency', $currency );
	}
}

if ( ! function_exists( 'wp_easycart_has_marketing_consent' ) ) {
	/**
	 * May marketing ( or another category of ) tags run for this shopper? Settings › Integrations › Cookie consent:
	 * "off" answers yes; any other choice asks wp_easycart_consent::has() ( the WP Consent API, Google Consent Mode, Cookiebot,
	 * CookieYes or Complianz ). Any consent plugin can answer through the wpeasycart_marketing_consent filter.
	 *
	 * @since 6.0.2
	 * @param string $category WP Consent API category: marketing, statistics, statistics-anonymous, preferences, functional.
	 * @return bool
	 */
	function wp_easycart_has_marketing_consent( $category = 'marketing' ) {
		$category = sanitize_key( $category );
		if ( '' === $category ) {
			$category = 'marketing';
		}
		$has = true;
		if ( class_exists( 'wp_easycart_consent' ) ) {
			$has = wp_easycart_consent::has( $category );
		} elseif ( 'wp_consent_api' === get_option( 'ec_option_marketing_consent', 'off' ) && function_exists( 'wp_has_consent' ) ) {
			$has = (bool) wp_has_consent( $category );
		}
		/**
		 * Has the shopper agreed to this category of tags?
		 *
		 * @since 6.0.2
		 * @param bool   $has      The answer so far.
		 * @param string $category WP Consent API category.
		 */
		return (bool) apply_filters( 'wpeasycart_marketing_consent', $has, $category );
	}
}

if ( ! function_exists( 'wp_easycart_meta_external_id' ) ) {
	/**
	 * The hashed external ID of a shopper: SHA-256 of the account ID, or of the lowercased email for a guest. The
	 * browser Pixel and the server events use the same value.
	 *
	 * @since 6.0.2
	 * @param int    $user_id EasyCart account ID ( ec_user ), 0 for a guest.
	 * @param string $email   Email, used when there is no account.
	 * @return string '' when neither is known.
	 */
	function wp_easycart_meta_external_id( $user_id = 0, $email = '' ) {
		$user_id = (int) $user_id;
		$email   = strtolower( trim( (string) $email ) );
		$raw     = ( $user_id > 0 ) ? (string) $user_id : ( is_email( $email ) ? $email : '' );
		$hashed  = ( '' === $raw ) ? '' : hash( 'sha256', $raw );
		/**
		 * The hashed external ID sent to Meta.
		 *
		 * @since 6.0.2
		 * @param string $hashed  SHA-256 hex, or ''.
		 * @param int    $user_id Account ID.
		 * @param string $email   Lowercased email.
		 */
		return (string) apply_filters( 'wp_easycart_meta_external_id', $hashed, $user_id, $email );
	}
}

if ( ! function_exists( 'wp_easycart_meta_track' ) ) {
	/**
	 * Announce a storefront event: fires wp_easycart_meta_event with a new ( or the given ) event ID. Does nothing without
	 * a Pixel ID or outside a storefront request ( admin, the mobile app, cron, REST, WP-CLI ).
	 *
	 * @since 6.0.2
	 * @param string $event_name  ViewContent | AddToCart | InitiateCheckout | AddPaymentInfo | CompleteRegistration | Search.
	 * @param array  $custom_data Meta custom_data.
	 * @param array  $args        event_id, once ( a key: at most once per request ), browser ( bool ), product_id,
	 *                            order_id, user_id, email.
	 * @return string The event ID, or '' when nothing was announced.
	 */
	function wp_easycart_meta_track( $event_name, $custom_data = array(), $args = array() ) {
		return wp_easycart_meta::track( $event_name, $custom_data, $args );
	}
}

if ( ! function_exists( 'wp_easycart_meta_cart_contents' ) ) {
	/**
	 * Meta contents for a cart: one entry per line with its content ID ( the variant's when the line is one ).
	 *
	 * @since 6.0.2
	 * @param ec_cart|array $cart An ec_cart, or a list of ec_cartitem.
	 * @return array array( 'contents' => list of { id, quantity, item_price }, 'content_ids' => list, 'num_items' => int ).
	 */
	function wp_easycart_meta_cart_contents( $cart ) {
		$items = array();
		if ( is_object( $cart ) && isset( $cart->cart ) && is_array( $cart->cart ) ) {
			$items = $cart->cart;
		} elseif ( is_array( $cart ) ) {
			$items = $cart;
		}
		$lines = array();
		foreach ( $items as $item ) {
			if ( ! is_object( $item ) || empty( $item->product_id ) ) {
				continue;
			}
			$ids = array();
			for ( $slot = 1; $slot <= 5; $slot++ ) {
				$ids[] = isset( $item->{ 'optionitem' . $slot . '_id' } ) ? (int) $item->{ 'optionitem' . $slot . '_id' } : 0;
			}
			$quantity = ( isset( $item->grid_quantity ) && (int) $item->grid_quantity > 0 ) ? (int) $item->grid_quantity : (int) $item->quantity;
			$lines[]  = array(
				'product_id' => (int) $item->product_id,
				'variant_id' => wp_easycart_meta_variant_id( $item->product_id, $ids ),
				'quantity'   => $quantity,
				'price'      => isset( $item->unit_price ) ? (float) $item->unit_price : 0.0,
			);
		}
		return wp_easycart_meta::contents( $lines );
	}
}

if ( ! function_exists( 'wp_easycart_meta_order_contents' ) ) {
	/**
	 * Meta contents for a placed order, from its order lines ( ec_orderdetail ), with the same content IDs as the catalog.
	 *
	 * @since 6.0.2
	 * @param int $order_id Order.
	 * @return array array( 'contents' => list of { id, quantity, item_price }, 'content_ids' => list, 'num_items' => int ).
	 */
	function wp_easycart_meta_order_contents( $order_id ) {
		global $wpdb;
		$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT product_id, optionitem_id_1, optionitem_id_2, optionitem_id_3, optionitem_id_4, optionitem_id_5, quantity, unit_price FROM ec_orderdetail WHERE order_id = %d ORDER BY orderdetail_id ASC', (int) $order_id ) );
		$lines = array();
		foreach ( (array) $rows as $row ) {
			if ( empty( $row->product_id ) ) {
				continue;
			}
			$lines[] = array(
				'product_id' => (int) $row->product_id,
				'variant_id' => wp_easycart_meta_variant_id( $row->product_id, $row ),
				'quantity'   => (int) $row->quantity,
				'price'      => (float) $row->unit_price,
			);
		}
		return wp_easycart_meta::contents( $lines );
	}
}

if ( ! function_exists( 'wp_easycart_cart_add_snapshot' ) ) {
	/**
	 * How many of a product the session cart holds, taken before an add so wp_easycart_announce_cart_item_added() can
	 * tell how many the add put in ( even when it merged into an existing line or was capped by stock ).
	 *
	 * @since 6.0.2
	 * @param int    $product_id Product.
	 * @param string $session_id Cart session, '' for the current one.
	 * @return int
	 */
	function wp_easycart_cart_add_snapshot( $product_id, $session_id = '' ) {
		return wp_easycart_meta::cart_quantity( $product_id, $session_id );
	}
}

if ( ! function_exists( 'wp_easycart_announce_cart_item_added' ) ) {
	/**
	 * Fire wpeasycart_cart_item_added for an add that just wrote its cart row. Every add path calls this once per line.
	 *
	 * @since 6.0.2
	 * @param int    $tempcart_id The cart line ( ec_tempcart ) the add created or added to.
	 * @param int    $product_id  Product.
	 * @param int    $before      wp_easycart_cart_add_snapshot() taken before the add.
	 * @param string $source      details | list | link | cart_link | deconetwork | other.
	 * @param string $session_id  Cart session, '' for the current one.
	 * @param array  $cartitems   The session cart ( ec_db::get_temp_cart() ) when the caller already has it.
	 * @return array|false What was added ( product_id, quantity, content_id, value, currency, event_id, content_name,
	 *                     tracked, tempcart_id ), or false when the add put nothing in the cart.
	 */
	function wp_easycart_announce_cart_item_added( $tempcart_id, $product_id, $before, $source = 'other', $session_id = '', $cartitems = null ) {
		return wp_easycart_meta::announce_add( $tempcart_id, $product_id, $before, $source, $session_id, $cartitems );
	}
}

if ( ! function_exists( 'wp_easycart_cart_item_added_last' ) ) {
	/**
	 * The last add announced in this request ( for AJAX responses ), or null.
	 *
	 * @since 6.0.2
	 * @return array|null See wp_easycart_announce_cart_item_added().
	 */
	function wp_easycart_cart_item_added_last() {
		return wp_easycart_meta::last_add();
	}
}

if ( ! function_exists( 'wp_easycart_meta_view_content' ) ) {
	/**
	 * Product details: announce ViewContent and print its Pixel call ( once per product per request ).
	 *
	 * @since 6.0.2
	 * @param ec_product $product The product shown.
	 * @return void
	 */
	function wp_easycart_meta_view_content( $product ) {
		wp_easycart_meta::view_content( $product );
		if ( class_exists( 'wp_easycart_store_activity' ) ) {
			wp_easycart_store_activity::product_viewed( $product ); /* 6.0.2: Reports' product views */
		}
	}
}

if ( ! function_exists( 'wp_easycart_meta_add_to_cart_js' ) ) {
	/**
	 * Product lists: the JavaScript an Add to cart button runs before its add ( fires AddToCart with a new event ID and
	 * hands that ID to the add request ). Escape it with esc_attr() in an onclick.
	 *
	 * @since 6.0.2
	 * @param ec_product $product  The product.
	 * @param int|string $quantity A number, or the ID of the quantity input.
	 * @return string '' without a Pixel ID.
	 */
	function wp_easycart_meta_add_to_cart_js( $product, $quantity = 1 ) {
		return wp_easycart_meta::list_add_js( $product, $quantity );
	}
}

if ( ! function_exists( 'wp_easycart_meta_details_add_js' ) ) {
	/**
	 * Product details: the JavaScript an Add to cart button runs once the form is valid ( fires AddToCart for the chosen
	 * variant and puts the event ID in the form as ec_meta_eid ).
	 *
	 * @since 6.0.2
	 * @param ec_product $product The product.
	 * @param int        $rand_id The details block's number ( $wpeasycart_addtocart_shortcode_rand ).
	 * @param string     $element JavaScript expression for the clicked button ( or null ).
	 * @return string '' without a Pixel ID.
	 */
	function wp_easycart_meta_details_add_js( $product, $rand_id, $element = 'null' ) {
		return wp_easycart_meta::details_add_js( $product, $rand_id, $element );
	}
}

if ( ! function_exists( 'wp_easycart_meta_initiate_checkout' ) ) {
	/**
	 * Checkout starts ( the first classic checkout step, or a checkout screen of the one-page checkout ): announce
	 * InitiateCheckout and print its Pixel call, once per checkout session.
	 *
	 * @since 6.0.2
	 * @param ec_cartpage $cartpage The cart page ( cart and order_totals ).
	 * @return void
	 */
	function wp_easycart_meta_initiate_checkout( $cartpage ) {
		wp_easycart_meta::checkout_event( 'InitiateCheckout', $cartpage, 'now' );
		if ( class_exists( 'wp_easycart_store_activity' ) ) {
			wp_easycart_store_activity::step( 'checkout' ); /* 6.0.2: Reports' checkout steps */
		}
	}
}

if ( ! function_exists( 'wp_easycart_meta_add_payment_info' ) ) {
	/**
	 * The payment step shows: announce AddPaymentInfo and print its Pixel call, once per checkout session. 'deferred'
	 * ( the single page one-page checkout, where payment is on the page from the start ) waits for the shopper to reach
	 * the payment section and announces it then.
	 *
	 * @since 6.0.2
	 * @param ec_cartpage $cartpage The cart page ( cart and order_totals ).
	 * @param string      $when     now | deferred.
	 * @return void
	 */
	function wp_easycart_meta_add_payment_info( $cartpage, $when = 'now' ) {
		wp_easycart_meta::checkout_event( 'AddPaymentInfo', $cartpage, ( 'deferred' === $when ) ? 'deferred' : 'now' );
		if ( 'deferred' !== $when && class_exists( 'wp_easycart_store_activity' ) ) {
			wp_easycart_store_activity::step( 'payment' ); /* 6.0.2: Reports' checkout steps ( the single page one-page checkout has no payment step of its own ) */
		}
	}
}

if ( ! function_exists( 'wp_easycart_meta_order_payment_info' ) ) {
	/**
	 * Paying an existing order ( the classic pay-for-order page ): announce AddPaymentInfo for it and print its Pixel call.
	 *
	 * @since 6.0.2
	 * @param int   $order_id    Order.
	 * @param float $grand_total Amount to pay.
	 * @return void
	 */
	function wp_easycart_meta_order_payment_info( $order_id, $grand_total ) {
		wp_easycart_meta::order_payment_info( $order_id, $grand_total );
	}
}

if ( ! function_exists( 'wp_easycart_meta_purchase' ) ) {
	/**
	 * Order success page: the browser Purchase, eventID order_{order_id}, once per order in this browser. Not announced:
	 * WP EasyCart PRO sends the server Purchase itself when the order is paid.
	 *
	 * @since 6.0.2
	 * @param object $order The order shown ( order_id, grand_total ).
	 * @return void
	 */
	function wp_easycart_meta_purchase( $order ) {
		wp_easycart_meta::purchase( $order );
	}
}

if ( ! function_exists( 'wp_easycart_meta_search' ) ) {
	/**
	 * Store search results: announce Search and print its Pixel call.
	 *
	 * @since 6.0.2
	 * @param string $search_string What the shopper searched for.
	 * @param array  $products      The results shown ( ec_product ).
	 * @return void
	 */
	function wp_easycart_meta_search( $search_string, $products = array() ) {
		wp_easycart_meta::search( $search_string, $products );
	}
}

if ( ! class_exists( 'wp_easycart_meta' ) ) :

	/**
	 * Meta Pixel runtime and storefront event announcements.
	 *
	 * @since 6.0.2
	 */
	final class wp_easycart_meta {

		/** How long a checkout's "already sent" marks last ( InitiateCheckout, AddPaymentInfo ). */
		const CHECKOUT_FLAG_TTL = 43200;

		/** Browser events waiting for the next page ( a registration, an add from a link ). */
		const QUEUE_COOKIE = 'wpec_meta_q';

		/** This browser's advanced matching values ( hashed ), read by the base code. */
		const MATCHING_COOKIE = 'wpec_meta_am';

		/**
		 * Variant rows by product.
		 *
		 * @var array
		 */
		private static $variants = array();

		/**
		 * Keys of events already announced in this request.
		 *
		 * @var array
		 */
		private static $once = array();

		/**
		 * Has this page printed the Pixel base code?
		 *
		 * @var bool
		 */
		private static $base_printed = false;

		/**
		 * Has the request's ec_meta_eid been used by an add?
		 *
		 * @var bool
		 */
		private static $request_eid_used = false;

		/**
		 * The last add announced.
		 *
		 * @var array|null
		 */
		private static $last_add = null;

		/** Register hooks. */
		public static function init() {
			add_action( 'wp', array( __CLASS__, 'maybe_sitewide' ) );
			add_action( 'wp', array( __CLASS__, 'sync_matching_cookie' ) );
			add_action( 'wpeasycart_cart_item_added', array( __CLASS__, 'on_cart_item_added' ), 10, 5 );
			add_action( 'wpeasycart_account_added', array( __CLASS__, 'on_account_added' ), 10, 2 );
			add_action( 'wp_ajax_ec_ajax_meta_payment_info', array( __CLASS__, 'ajax_payment_info' ) );
			add_action( 'wp_ajax_nopriv_ec_ajax_meta_payment_info', array( __CLASS__, 'ajax_payment_info' ) );
			add_action( 'plugins_loaded', array( __CLASS__, 'register_with_consent_api' ) );
		}

		/** The WP Consent API asks every plugin that reads consent to say so. */
		public static function register_with_consent_api() {
			if ( defined( 'EC_PLUGIN_DIRECTORY' ) ) {
				add_filter( 'wp_consent_api_registered_' . plugin_basename( EC_PLUGIN_DIRECTORY . '/wpeasycart.php' ), '__return_true' );
			}
		}

		/** Setting "Load the Pixel on every page": the base code joins wp_head on every front-end page. */
		public static function maybe_sitewide() {
			if ( is_admin() || '' === wp_easycart_meta_pixel_id() || ! get_option( 'ec_option_fb_pixel_sitewide' ) || ! function_exists( 'wp_easycart_init_facebook_pixel' ) ) {
				return;
			}
			if ( apply_filters( 'wpeasycart_allow_pixel_code', true ) ) {
				add_action( 'wp_head', 'wp_easycart_init_facebook_pixel' );
			}
		}

		/* === Requests and shoppers === */

		/**
		 * Is this a shopper's storefront request? Not the admin ( an admin screen, or AJAX sent from one ), the mobile
		 * app ( amfphp ), cron, REST, XML-RPC or WP-CLI.
		 *
		 * @return bool
		 */
		public static function is_storefront() {
			$storefront = true;
			if ( defined( 'WPEASYCART_ACCESSING_AMFPHP' ) || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
				$storefront = false;
			} elseif ( is_admin() ) {
				if ( ! wp_doing_ajax() ) {
					$storefront = false;
				} else {
					$referer = (string) wp_get_raw_referer();
					$admin   = preg_replace( '#^https?://#i', '', admin_url() );
					if ( '' !== $referer && 0 === strpos( preg_replace( '#^https?://#i', '', $referer ), $admin ) ) {
						$storefront = false;
					}
				}
			}
			/**
			 * Is this a storefront request that may announce Meta events?
			 *
			 * @since 6.0.2
			 * @param bool $storefront The answer so far.
			 */
			return (bool) apply_filters( 'wp_easycart_meta_is_storefront_request', $storefront );
		}

		/**
		 * The cart session ID.
		 *
		 * @return string '' when there is none ( including the shared 'not-set' placeholder ).
		 */
		private static function session_id() {
			$session_id = ( isset( $GLOBALS['ec_cart_data'] ) && is_object( $GLOBALS['ec_cart_data'] ) && isset( $GLOBALS['ec_cart_data']->ec_cart_id ) ) ? (string) $GLOBALS['ec_cart_data']->ec_cart_id : '';
			return in_array( $session_id, array( 'not-set', 'deleted' ), true ) ? '' : $session_id;
		}

		/**
		 * The shopper, when known in this request.
		 *
		 * @return array array( user_id, email ).
		 */
		private static function shopper() {
			$user_id = 0;
			$email   = '';
			if ( isset( $GLOBALS['ec_user'] ) && is_object( $GLOBALS['ec_user'] ) ) {
				$user_id = isset( $GLOBALS['ec_user']->user_id ) ? (int) $GLOBALS['ec_user']->user_id : 0;
				$email   = isset( $GLOBALS['ec_user']->email ) ? (string) $GLOBALS['ec_user']->email : '';
			}
			if ( '' === $email && isset( $GLOBALS['ec_cart_data'] ) && is_object( $GLOBALS['ec_cart_data'] ) && isset( $GLOBALS['ec_cart_data']->cart_data->email ) ) {
				$email = (string) $GLOBALS['ec_cart_data']->cart_data->email;
			}
			$email = is_email( trim( $email ) ) ? trim( $email ) : '';
			return array( $user_id, $email );
		}

		/**
		 * The page the event happened on ( the referring page for AJAX and form posts ).
		 *
		 * @return string
		 */
		private static function source_url() {
			$referer = (string) wp_get_raw_referer();
			$method  = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
			if ( '' !== $referer && ( wp_doing_ajax() || 'POST' === $method ) ) {
				return esc_url_raw( $referer );
			}
			$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : wp_parse_url( home_url(), PHP_URL_HOST );
			$uri  = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
			return esc_url_raw( ( is_ssl() ? 'https://' : 'http://' ) . $host . $uri );
		}

		/* === Content IDs === */

		/**
		 * Enabled variant rows of a product that tracks stock per option combination, keyed by variant_key().
		 *
		 * @param int $product_id Product.
		 * @return array key => array( 'id' => optionitemquantity_id, 'price' => float|null ).
		 */
		public static function variant_map( $product_id ) {
			global $wpdb;
			$product_id = (int) $product_id;
			if ( $product_id <= 0 ) {
				return array();
			}
			if ( ! isset( self::$variants[ $product_id ] ) ) {
				$map = array();
				if ( (int) $wpdb->get_var( $wpdb->prepare( 'SELECT use_optionitem_quantity_tracking FROM ec_product WHERE product_id = %d', $product_id ) ) ) {
					$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT optionitemquantity_id, optionitem_id_1, optionitem_id_2, optionitem_id_3, optionitem_id_4, optionitem_id_5, price FROM ec_optionitemquantity WHERE product_id = %d AND is_enabled = 1 ORDER BY optionitemquantity_id ASC', $product_id ) );
					foreach ( (array) $rows as $row ) {
						$key = self::variant_key( $row );
						if ( ! isset( $map[ $key ] ) ) {
							$map[ $key ] = array(
								'id'    => (int) $row->optionitemquantity_id,
								'price' => ( -1.0 !== (float) $row->price ) ? (float) $row->price : null, /* -1.000: the variant uses the product price */
							);
						}
					}
				}
				self::$variants[ $product_id ] = $map;
			}
			return self::$variants[ $product_id ];
		}

		/**
		 * Five optionitem IDs joined by underscores.
		 *
		 * @param array|object $ids A list in slot order, or optionitem_id_1 … _5 keys / properties.
		 * @return string
		 */
		public static function variant_key( $ids ) {
			if ( is_object( $ids ) ) {
				$ids = get_object_vars( $ids );
			}
			$ids  = is_array( $ids ) ? $ids : array();
			$list = array();
			for ( $slot = 1; $slot <= 5; $slot++ ) {
				if ( isset( $ids[ 'optionitem_id_' . $slot ] ) ) {
					$list[] = (int) $ids[ 'optionitem_id_' . $slot ];
				} elseif ( isset( $ids[ $slot - 1 ] ) ) {
					$list[] = (int) $ids[ $slot - 1 ];
				} else {
					$list[] = 0;
				}
			}
			return implode( '_', $list );
		}

		/**
		 * Does this product have variants ( content_type product_group on ViewContent )?
		 *
		 * @param int $product_id Product.
		 * @return bool
		 */
		public static function has_variants( $product_id ) {
			return ! empty( self::variant_map( $product_id ) );
		}

		/**
		 * Contents, content IDs and item count from lines of product_id, variant_id, quantity, price.
		 *
		 * @param array $lines Lines.
		 * @return array
		 */
		public static function contents( $lines ) {
			$contents    = array();
			$content_ids = array();
			$num_items   = 0;
			foreach ( $lines as $line ) {
				$id         = wp_easycart_meta_content_id( $line['product_id'], $line['variant_id'] );
				$contents[] = array(
					'id'         => $id,
					'quantity'   => (int) $line['quantity'],
					'item_price' => round( (float) $line['price'], 2 ),
				);
				if ( ! in_array( $id, $content_ids, true ) ) {
					$content_ids[] = $id;
				}
				$num_items += (int) $line['quantity'];
			}
			return array(
				'contents'    => $contents,
				'content_ids' => $content_ids,
				'num_items'   => $num_items,
			);
		}

		/**
		 * May the shopper see this product's price ( login for pricing, catalog and inquiry modes )?
		 *
		 * @param object $product ec_product.
		 * @return bool
		 */
		private static function price_visible( $product ) {
			if ( ! empty( $product->login_for_pricing ) && ( ! method_exists( $product, 'is_login_for_pricing_valid' ) || ! $product->is_login_for_pricing_valid() ) ) {
				return false;
			}
			if ( ! empty( $product->is_catalog_mode ) && get_option( 'ec_option_hide_price_seasonal' ) ) {
				return false;
			}
			if ( ! empty( $product->is_inquiry_mode ) && get_option( 'ec_option_hide_price_inquiry' ) ) {
				return false;
			}
			return true;
		}

		/**
		 * A product's name for content_name.
		 *
		 * @param object $product ec_product.
		 * @return string
		 */
		private static function product_name( $product ) {
			return isset( $product->title ) ? trim( wp_strip_all_tags( stripslashes( (string) $product->title ) ) ) : '';
		}

		/* === Announcing === */

		/**
		 * See wp_easycart_meta_track().
		 *
		 * @param string $event_name  Event.
		 * @param array  $custom_data custom_data.
		 * @param array  $args        Arguments.
		 * @return string
		 */
		public static function track( $event_name, $custom_data, $args ) {
			$args = wp_parse_args(
				$args,
				array(
					'event_id'   => '',
					'once'       => '',
					'browser'    => null,
					'product_id' => 0,
					'order_id'   => 0,
					'user_id'    => null,
					'email'      => null,
				)
			);
			if ( '' === wp_easycart_meta_pixel_id() || ! self::is_storefront() ) {
				return '';
			}
			if ( '' !== (string) $args['once'] ) {
				if ( isset( self::$once[ $args['once'] ] ) ) {
					return '';
				}
				self::$once[ $args['once'] ] = true;
			}
			$event_id = substr( sanitize_key( (string) $args['event_id'] ), 0, 64 );
			if ( '' === $event_id ) {
				$event_id = wp_easycart_meta_event_id( self::prefix( $event_name ) );
			}
			$custom_data = is_array( $custom_data ) ? $custom_data : array();
			if ( isset( $custom_data['value'] ) && ! isset( $custom_data['currency'] ) ) {
				$custom_data['currency'] = wp_easycart_meta_currency();
			}
			list( $user_id, $email ) = self::shopper();
			$context                 = array(
				'source_url' => self::source_url(),
				'product_id' => (int) $args['product_id'],
				'order_id'   => (int) $args['order_id'],
				'user_id'    => ( null !== $args['user_id'] ) ? (int) $args['user_id'] : $user_id,
				'email'      => ( null !== $args['email'] ) ? (string) $args['email'] : $email,
				'browser'    => ( null !== $args['browser'] ) ? (bool) $args['browser'] : ( self::$base_printed || wp_doing_ajax() ),
			);
			/**
			 * A storefront Meta event happened. WP EasyCart PRO sends it from the server ( Conversions API ) with the same
			 * event ID as the browser Pixel call, so Meta counts it once.
			 *
			 * @since 6.0.2
			 * @param string $event_name  ViewContent | AddToCart | InitiateCheckout | AddPaymentInfo | CompleteRegistration | Search.
			 * @param array  $custom_data Meta custom_data ( value, currency, content_ids, contents, content_type, content_name,
			 *                            num_items, search_string ). Amounts are in the store currency.
			 * @param string $event_id    Event ID shared with the browser.
			 * @param array  $context     source_url, product_id, order_id, user_id, email ( unhashed, when known ), browser
			 *                            ( a browser call with this ID was printed or returned ).
			 */
			do_action( 'wp_easycart_meta_event', $event_name, $custom_data, $event_id, $context );
			return $event_id;
		}

		/**
		 * Event ID prefix of an event.
		 *
		 * @param string $event_name Event.
		 * @return string
		 */
		private static function prefix( $event_name ) {
			$prefixes = array(
				'ViewContent'          => 'vc',
				'AddToCart'            => 'atc',
				'InitiateCheckout'     => 'ic',
				'AddPaymentInfo'       => 'api',
				'CompleteRegistration' => 'reg',
				'Search'               => 'srch',
				'Purchase'             => 'order',
			);
			return isset( $prefixes[ $event_name ] ) ? $prefixes[ $event_name ] : 'ev';
		}

		/**
		 * JSON safe inside a script element and, once esc_attr()'d, inside an attribute.
		 *
		 * @param mixed $value Value.
		 * @return string
		 */
		private static function json( $value ) {
			return (string) wp_json_encode( $value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
		}

		/**
		 * The JavaScript of one Pixel event call ( guarded: nothing happens where the base code is missing ).
		 *
		 * @param string $event_name  Event.
		 * @param array  $custom_data custom_data.
		 * @param string $event_id    Event ID.
		 * @param int    $stamp       Server time the ID was made, 0 when it never goes stale ( see the runtime ).
		 * @return string
		 */
		private static function event_js( $event_name, $custom_data, $event_id, $stamp ) {
			return 'if(window.wpeasycart_meta_fbq){wpeasycart_meta_fbq(' . self::json( $event_name ) . ',' . self::json( (object) $custom_data ) . ',' . self::json( $event_id ) . ',' . (int) $stamp . ');}';
		}

		/**
		 * Print one Pixel event call.
		 *
		 * @param string $event_name  Event.
		 * @param array  $custom_data custom_data.
		 * @param string $event_id    Event ID.
		 * @return void
		 */
		private static function print_event( $event_name, $custom_data, $event_id ) {
			echo '<script>' . self::event_js( $event_name, $custom_data, $event_id, time() ) . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded with JSON_HEX_TAG / _AMP / _APOS / _QUOT; nothing else is variable.
		}

		/* === Base code === */

		/**
		 * The Pixel base code for wp_head ( wp_easycart_init_facebook_pixel() ): loader, runtime, consent, LDU, init with
		 * advanced matching, PageView, then any browser events queued by the previous request.
		 *
		 * @return void
		 */
		public static function print_base_code() {
			$pixel_id = wp_easycart_meta_pixel_id();
			if ( self::$base_printed || '' === $pixel_id ) {
				return;
			}
			self::$base_printed = true;
			$consent_mode       = class_exists( 'wp_easycart_consent' ) ? wp_easycart_consent::mode() : ( ( 'wp_consent_api' === get_option( 'ec_option_marketing_consent', 'off' ) ) ? 'wp_consent_api' : 'off' );
			$consent            = wp_easycart_has_marketing_consent( 'marketing' );
			$config             = array(
				'currency'    => wp_easycart_meta_currency(),
				'ajax_url'    => admin_url( 'admin-ajax.php' ),
				'consent'     => $consent_mode,
				'cookie_path' => ( defined( 'COOKIEPATH' ) && COOKIEPATH ) ? COOKIEPATH : '/',
			);
			$queued             = self::take_queue();

			if ( 'off' === $consent_mode ) {
				$js = "!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');\n";
			} else {
				// 6.0.2: while cookie consent is asked, Meta's script itself waits too: fbq() only queues until the shopper
				// accepts marketing cookies, then wpeasycart_meta_load() adds fbevents.js, which sends what was queued.
				$js = "!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];f.wpeasycart_meta_load=function(){if(t)return;t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');\n";
			}
			$js .= 'window.wpeasycart_meta_config=' . self::json( $config ) . ";\n";
			$js .= self::runtime_js() . "\n";
			if ( 'off' !== $consent_mode || ! $consent ) {
				// Until the shopper agrees the Pixel holds everything back. This head can come from a page cache, so the
				// browser's own answer wins: the consent bridge ( wp_easycart_consent, 6.0.2 ), else the WP Consent API's script
				// or its wp_consent_marketing cookie. With the answer yes, Meta's script loads now.
				$js .= '(function(){var ok=' . ( $consent ? 'true' : 'false' ) . ',m;if(window.wpeasycart_consent&&"off"!==window.wpeasycart_meta_config.consent){ok=window.wpeasycart_consent.has("marketing");}else if("wp_consent_api"===window.wpeasycart_meta_config.consent){if("function"===typeof window.wp_has_consent){ok=!!window.wp_has_consent("marketing");}else if((m=document.cookie.match(/(?:^|;\s*)wp_consent_marketing=([^;]*)/))){ok=("allow"===m[1]);}}if(!ok){fbq("consent","revoke");}else if("function"===typeof window.wpeasycart_meta_load){window.wpeasycart_meta_load();}})();' . "\n";
			}
			if ( get_option( 'ec_option_fb_ldu', 0 ) ) {
				$js .= "fbq('dataProcessingOptions',['LDU'],0,0);\n";
			}
			// Advanced matching comes from this browser's own cookie ( sync_matching_cookie() ), never from the HTML, which a
			// page cache could hand to another visitor.
			$js .= '(function(){var m=document.cookie.match(/(?:^|;\s*)' . self::MATCHING_COOKIE . '=([^;]*)/),o={},p,i,kv;if(m){try{p=decodeURIComponent(m[1]).split("&");for(i=0;i<p.length;i++){kv=p[i].split("=");if(kv[0]&&kv[1]){o[decodeURIComponent(kv[0])]=decodeURIComponent(kv[1]);}}}catch(e){o={};}}if(Object.keys(o).length){fbq("init",' . self::json( $pixel_id ) . ',o);}else{fbq("init",' . self::json( $pixel_id ) . ');}})();' . "\n";
			$js .= 'fbq("track","PageView",{},{eventID:wpeasycart_meta_new_eid("pv")});' . "\n";
			foreach ( $queued as $event ) {
				$js .= self::event_js( $event['n'], $event['d'], $event['e'], 0 ) . "\n";
			}
			if ( ! empty( $queued ) || isset( $_COOKIE[ self::QUEUE_COOKIE ] ) ) {
				$js .= 'document.cookie=' . self::json( self::QUEUE_COOKIE . '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=' . $config['cookie_path'] ) . ";\n";
			}

			echo "<!-- Meta Pixel ( WP EasyCart ) -->\n<script>\n" . $js . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static JavaScript plus values JSON encoded with JSON_HEX_TAG / _AMP / _APOS / _QUOT.
			if ( $consent ) {
				$pixel_args = array(
					'id'       => $pixel_id,
					'ev'       => 'PageView',
					'noscript' => '1',
				);
				if ( get_option( 'ec_option_fb_ldu', 0 ) ) {
					$pixel_args['dpo']   = 'LDU';
					$pixel_args['dpoco'] = '0';
					$pixel_args['dpost'] = '0';
				}
				echo '<noscript><img height="1" width="1" style="display:none" alt="" src="' . esc_url( add_query_arg( $pixel_args, 'https://www.facebook.com/tr' ) ) . '" /></noscript>' . "\n";
			}
		}

		/**
		 * Keep this browser's advanced matching cookie current ( front-end requests, before any output ): the hashed values
		 * while the shopper is known, has agreed to marketing tags and the setting is on; removed otherwise. The base code
		 * reads it, so the HTML never carries them.
		 *
		 * @return void
		 */
		public static function sync_matching_cookie() {
			if ( is_admin() || headers_sent() || '' === wp_easycart_meta_pixel_id() ) {
				return;
			}
			$matching = wp_easycart_has_marketing_consent( 'marketing' ) ? self::advanced_matching() : array();
			$value    = empty( $matching ) ? '' : http_build_query( $matching, '', '&', PHP_QUERY_RFC3986 );
			$current  = isset( $_COOKIE[ self::MATCHING_COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::MATCHING_COOKIE ] ) ) : '';
			if ( $value === $current ) {
				return;
			}
			$path   = ( defined( 'COOKIEPATH' ) && COOKIEPATH ) ? COOKIEPATH : '/';
			$domain = ( defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ) ? COOKIE_DOMAIN : '';
			if ( '' === $value ) {
				setcookie( self::MATCHING_COOKIE, '', time() - HOUR_IN_SECONDS, $path, $domain, is_ssl(), false );
				unset( $_COOKIE[ self::MATCHING_COOKIE ] );
			} else {
				setcookie( self::MATCHING_COOKIE, $value, 0, $path, $domain, is_ssl(), false );
				$_COOKIE[ self::MATCHING_COOKIE ] = $value;
			}
		}

		/**
		 * Advanced matching for fbq init: hashed email and external ID of a known shopper.
		 *
		 * @return array
		 */
		private static function advanced_matching() {
			$matching = array();
			if ( get_option( 'ec_option_fb_advanced_matching', 1 ) ) {
				list( $user_id, $email ) = self::shopper();
				if ( '' !== $email ) {
					$matching['em'] = hash( 'sha256', strtolower( trim( $email ) ) );
				}
				$external_id = wp_easycart_meta_external_id( $user_id, $email );
				if ( '' !== $external_id ) {
					$matching['external_id'] = $external_id;
				}
				/**
				 * Advanced matching parameters for fbq( 'init' ). Values must be normalised and SHA-256 hashed.
				 *
				 * @since 6.0.2
				 * @param array  $matching em, external_id.
				 * @param int    $user_id  Account ID, 0 for a guest.
				 * @param string $email    Email, when known.
				 */
				$matching = apply_filters( 'wp_easycart_meta_advanced_matching', $matching, $user_id, $email );
			}
			return is_array( $matching ) ? $matching : array();
		}

		/**
		 * The storefront helpers every Pixel page needs ( ES5 ):
		 *  - wpeasycart_meta_new_eid( prefix ): a random event ID;
		 *  - wpeasycart_meta_fbq( name, data, eid, stamp ): fbq track with eventID. A page printed more than five minutes
		 *    ago came from a page cache, so its view never reached the server: it gets a fresh ID rather than sharing one
		 *    with every visitor of the cached copy;
		 *  - wpeasycart_meta_add( el, item, quantity ): product list Add to cart ( the ID rides along as ec_meta_eid:
		 *    window.wpeasycart_meta_add_eid for the AJAX add, the link for a redirecting one );
		 *  - wpeasycart_meta_details_add( el, product_id, rand_id, item ): product details Add to cart ( variant from the
		 *    form's ec_option1 … 5, the ID in a hidden ec_meta_eid field );
		 *  - wpeasycart_meta_payment_reached(): the one-page checkout's AddPaymentInfo ( browser now, server by AJAX );
		 *  - consent: grant / revoke when the consent bridge ( or the WP Consent API ) reports an answer; a grant also loads
		 *    Meta's script when it was held back ( wpeasycart_meta_load(), 6.0.2 ).
		 *
		 * @return string
		 */
		private static function runtime_js() {
			return <<<'JS'
(function(w,d){
if(w.wpeasycart_meta_fbq){return;}
var cfg=w.wpeasycart_meta_config||{};
function rid(p){var s='',i,a,c=w.crypto||w.msCrypto;try{a=new Uint8Array(12);c.getRandomValues(a);for(i=0;i<a.length;i++){s+=('0'+a[i].toString(16)).slice(-2);}}catch(e){s='';while(s.length<24){s+=Math.random().toString(36).slice(2);}s=s.slice(0,24);}return String(p||'ev').toLowerCase().replace(/[^a-z0-9]/g,'')+'_'+s;}
function qty(q){var n=q,el;if('string'===typeof q&&(el=d.getElementById(q))){n=el.value;}n=parseFloat(n);return(n>0)?n:1;}
function atc(item,q){var data={content_ids:[item.id],content_type:'product',contents:[{id:item.id,quantity:q}]};if(item.name){data.content_name=item.name;}if('number'===typeof item.price){data.contents[0].item_price=item.price;data.value=Math.round(item.price*q*100)/100;data.currency=cfg.currency;}return data;}
w.wpeasycart_meta_new_eid=rid;
w.wpeasycart_meta_fbq=function(name,data,eid,t){if('function'!==typeof w.fbq){return '';}if(!eid||(t&&Math.abs((new Date()).getTime()/1000-t)>300)){eid=rid('ev');}w.fbq('track',name,data||{},{eventID:eid});return eid;};
w.wpeasycart_meta_add=function(el,item,q){var n=qty(q),eid=rid('atc'),h;w.wpeasycart_meta_add_eid=eid;if(el&&el.tagName&&'A'===el.tagName.toUpperCase()&&el.href&&-1!==el.href.indexOf('ec_action=addtocart')){h=el.href;if(/[?&]ec_meta_eid=/.test(h)){h=h.replace(/([?&]ec_meta_eid=)[^&#]*/,'$1'+eid);}else{h+=(-1===h.indexOf('?')?'?':'&')+'ec_meta_eid='+eid;}el.href=h;}w.wpeasycart_meta_fbq('AddToCart',atc(item,n),eid);return eid;};
w.wpeasycart_meta_details_add=function(el,pid,rand,item){var q=d.getElementById('ec_quantity_'+pid+'_'+rand),form=(el&&el.form)?el.form:(q?q.form:null),n=qty(q?q.value:1),eid=rid('atc'),pick={id:item.id,name:item.name,price:item.price},key=[],i,f,v,input;if(form&&item.variants){for(i=1;i<=5;i++){f=form.elements['ec_option'+i];key.push((f&&f.value)?(parseInt(f.value,10)||0):0);}v=item.variants[key.join('_')];if(v){pick.id=v[0];if('number'===typeof v[1]){pick.price=v[1];}}}if(form){input=form.querySelector('input[name="ec_meta_eid"]');if(!input){input=d.createElement('input');input.type='hidden';input.name='ec_meta_eid';form.appendChild(input);}input.value=eid;}w.wpeasycart_meta_fbq('AddToCart',atc(pick,n),eid);return eid;};
w.wpeasycart_meta_payment_reached=function(){var p=w.wpeasycart_meta_payment,eid,body,x;if(!p||p.sent){return;}p.sent=true;eid=rid('api');w.wpeasycart_meta_fbq('AddPaymentInfo',p.data,eid);if(!cfg.ajax_url||!p.nonce){return;}body='action=ec_ajax_meta_payment_info&nonce='+encodeURIComponent(p.nonce)+'&event_id='+encodeURIComponent(eid);try{if(w.navigator&&w.navigator.sendBeacon&&w.Blob&&w.navigator.sendBeacon(cfg.ajax_url,new Blob([body],{type:'application/x-www-form-urlencoded'}))){return;}}catch(e){}try{x=new XMLHttpRequest();x.open('POST',cfg.ajax_url,true);x.setRequestHeader('Content-Type','application/x-www-form-urlencoded');x.send(body);}catch(e){}};
if(w.wpeasycart_consent&&'off'!==cfg.consent){w.wpeasycart_consent.on(function(s){if('function'===typeof w.fbq&&null!==s.marketing){w.fbq('consent',s.marketing?'grant':'revoke');if(s.marketing&&'function'===typeof w.wpeasycart_meta_load){w.wpeasycart_meta_load();}}});}else if('wp_consent_api'===cfg.consent){d.addEventListener('wp_listen_for_consent_change',function(e){var c=(e&&e.detail)||{};if('function'!==typeof w.fbq||!c.marketing){return;}w.fbq('consent','allow'===c.marketing?'grant':'revoke');if('allow'===c.marketing&&'function'===typeof w.wpeasycart_meta_load){w.wpeasycart_meta_load();}});}
})(window,document);
JS;
		}

		/* === Browser events for the next page === */

		/**
		 * Transient holding this session's queued browser events.
		 *
		 * @return string
		 */
		private static function queue_key() {
			return 'wpec_meta_q_' . md5( self::session_id() );
		}

		/**
		 * Keep a browser event for the next page this shopper opens ( the request that made it redirects ). A cookie marks
		 * that something waits, so other pages never look.
		 *
		 * @param string $event_name  Event.
		 * @param array  $custom_data custom_data.
		 * @param string $event_id    Event ID ( the server event's ).
		 * @return void
		 */
		private static function queue( $event_name, $custom_data, $event_id ) {
			if ( '' === self::session_id() ) {
				return;
			}
			$queued   = get_transient( self::queue_key() );
			$queued   = is_array( $queued ) ? $queued : array();
			$queued[] = array(
				'n' => (string) $event_name,
				'd' => $custom_data,
				'e' => (string) $event_id,
			);
			set_transient( self::queue_key(), array_slice( $queued, -10 ), 10 * MINUTE_IN_SECONDS );
			if ( ! headers_sent() ) {
				setcookie( self::QUEUE_COOKIE, '1', time() + 10 * MINUTE_IN_SECONDS, ( defined( 'COOKIEPATH' ) && COOKIEPATH ) ? COOKIEPATH : '/', ( defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ) ? COOKIE_DOMAIN : '', is_ssl(), false );
			}
			$_COOKIE[ self::QUEUE_COOKIE ] = '1';
		}

		/**
		 * Queued browser events of this session, removed from the queue.
		 *
		 * @return array
		 */
		private static function take_queue() {
			if ( ! isset( $_COOKIE[ self::QUEUE_COOKIE ] ) || '' === self::session_id() ) {
				return array();
			}
			$queued = get_transient( self::queue_key() );
			if ( false !== $queued ) {
				delete_transient( self::queue_key() );
			}
			return is_array( $queued ) ? $queued : array();
		}

		/* === Once per checkout session === */

		/**
		 * Transient marking an event already sent in this checkout session. A placed order starts a new cart session, so
		 * the next checkout sends it again.
		 *
		 * @param string $event_name Event.
		 * @return string
		 */
		private static function flag_key( $event_name ) {
			return 'wpec_meta_' . self::prefix( $event_name ) . '_' . md5( self::session_id() );
		}

		/**
		 * Has this checkout session sent the event?
		 *
		 * @param string $event_name Event.
		 * @return bool
		 */
		private static function flagged( $event_name ) {
			return '' === self::session_id() || false !== get_transient( self::flag_key( $event_name ) );
		}

		/**
		 * Mark the event sent for this checkout session.
		 *
		 * @param string $event_name Event.
		 * @return void
		 */
		private static function flag( $event_name ) {
			if ( '' !== self::session_id() ) {
				set_transient( self::flag_key( $event_name ), 1, self::CHECKOUT_FLAG_TTL );
			}
		}

		/* === Events === */

		/**
		 * See wp_easycart_meta_view_content().
		 *
		 * @param object $product ec_product.
		 * @return void
		 */
		public static function view_content( $product ) {
			if ( ! is_object( $product ) || empty( $product->product_id ) || '' === wp_easycart_meta_pixel_id() ) {
				return;
			}
			$product_id = (int) $product->product_id;
			$data       = array(
				'content_name' => self::product_name( $product ),
				'content_ids'  => array( wp_easycart_meta_content_id( $product_id ) ),
				'content_type' => self::has_variants( $product_id ) ? 'product_group' : 'product',
			);
			if ( self::price_visible( $product ) && isset( $product->price ) ) {
				$data['value']    = round( (float) $product->price, 2 );
				$data['currency'] = wp_easycart_meta_currency();
			}
			$event_id = self::track(
				'ViewContent',
				$data,
				array(
					'product_id' => $product_id,
					'once'       => 'vc_' . $product_id,
				)
			);
			if ( '' !== $event_id ) {
				self::print_event( 'ViewContent', $data, $event_id );
			}
		}

		/**
		 * See wp_easycart_meta_add_to_cart_js().
		 *
		 * @param object     $product  ec_product.
		 * @param int|string $quantity Number or input ID.
		 * @return string
		 */
		public static function list_add_js( $product, $quantity ) {
			if ( ! is_object( $product ) || empty( $product->product_id ) || '' === wp_easycart_meta_pixel_id() ) {
				return '';
			}
			$item = array(
				'id'   => wp_easycart_meta_content_id( $product->product_id ),
				'name' => self::product_name( $product ),
			);
			if ( self::price_visible( $product ) && isset( $product->price ) ) {
				$item['price'] = round( (float) $product->price, 2 );
			}
			$quantity = is_numeric( $quantity ) ? (float) $quantity : (string) $quantity;
			return 'if(window.wpeasycart_meta_add){wpeasycart_meta_add(this,' . self::json( $item ) . ',' . self::json( $quantity ) . ');}';
		}

		/**
		 * See wp_easycart_meta_details_add_js().
		 *
		 * @param object $product ec_product.
		 * @param int    $rand_id Details block number.
		 * @param string $element JavaScript expression of the button.
		 * @return string
		 */
		public static function details_add_js( $product, $rand_id, $element ) {
			if ( ! is_object( $product ) || empty( $product->product_id ) || '' === wp_easycart_meta_pixel_id() ) {
				return '';
			}
			$product_id    = (int) $product->product_id;
			$price_visible = self::price_visible( $product );
			$item          = array(
				'id'   => wp_easycart_meta_content_id( $product_id ),
				'name' => self::product_name( $product ),
			);
			if ( $price_visible && isset( $product->price ) ) {
				$item['price'] = round( (float) $product->price, 2 );
			}
			$variants = array();
			foreach ( self::variant_map( $product_id ) as $key => $variant ) {
				$variants[ $key ] = array( wp_easycart_meta_content_id( $product_id, $variant['id'] ), ( $price_visible && null !== $variant['price'] ) ? round( $variant['price'], 2 ) : null );
			}
			if ( ! empty( $variants ) ) {
				$item['variants'] = $variants;
			}
			$element = preg_match( '/^[A-Za-z_$][A-Za-z0-9_$]*$/', (string) $element ) ? (string) $element : 'null';
			return 'if(window.wpeasycart_meta_details_add){wpeasycart_meta_details_add(' . $element . ',' . $product_id . ',' . (int) $rand_id . ',' . self::json( $item ) . ');}';
		}

		/**
		 * Custom data of a checkout event from the cart page.
		 *
		 * @param object $cartpage ec_cartpage ( cart, order_totals ).
		 * @return array|false
		 */
		private static function checkout_data( $cartpage ) {
			if ( ! is_object( $cartpage ) || ! isset( $cartpage->cart ) || ! is_object( $cartpage->cart ) || empty( $cartpage->cart->cart ) ) {
				return false;
			}
			$contents = wp_easycart_meta_cart_contents( $cartpage->cart );
			$total    = ( isset( $cartpage->order_totals ) && is_object( $cartpage->order_totals ) ) ? (float) $cartpage->order_totals->grand_total : 0.0;
			return array(
				'value'        => round( $total, 2 ),
				'currency'     => wp_easycart_meta_currency(),
				'content_type' => 'product',
				'content_ids'  => $contents['content_ids'],
				'contents'     => $contents['contents'],
				'num_items'    => $contents['num_items'],
			);
		}

		/**
		 * InitiateCheckout / AddPaymentInfo, once per checkout session.
		 *
		 * @param string $event_name InitiateCheckout | AddPaymentInfo.
		 * @param object $cartpage   ec_cartpage.
		 * @param string $when       now | deferred.
		 * @return void
		 */
		public static function checkout_event( $event_name, $cartpage, $when ) {
			if ( '' === wp_easycart_meta_pixel_id() || ! self::is_storefront() || isset( self::$once[ 'checkout_' . $event_name ] ) || self::flagged( $event_name ) ) {
				return;
			}
			$data = self::checkout_data( $cartpage );
			if ( false === $data ) {
				return;
			}
			if ( 'deferred' === $when ) {
				/* Printed each time the payment section is drawn, but a payment already reached on this page stays reached. */
				$payment = array(
					'data'  => $data,
					'nonce' => wp_create_nonce( 'wp-easycart-meta-' . self::session_id() ),
				);
				echo '<script>window.wpeasycart_meta_payment=(window.wpeasycart_meta_payment&&window.wpeasycart_meta_payment.sent)?window.wpeasycart_meta_payment:' . self::json( $payment ) . ';(function($){if(!$){return;}$(document).off(".wpecmeta").on("focusin.wpecmeta click.wpecmeta change.wpecmeta","#ec_cart_onepage_payment",function(){if(window.wpeasycart_meta_payment_reached){window.wpeasycart_meta_payment_reached();}}).on("submit.wpecmeta","#wpeasycart_checkout_details_form, #ec_submit_order_form",function(){if(window.wpeasycart_meta_payment_reached){window.wpeasycart_meta_payment_reached();}});})(window.jQuery);</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static JavaScript plus JSON encoded with JSON_HEX_TAG / _AMP / _APOS / _QUOT.
				return;
			}
			$event_id = self::track( $event_name, $data, array( 'once' => 'checkout_' . $event_name ) );
			if ( '' === $event_id ) {
				return;
			}
			self::flag( $event_name );
			self::print_event( $event_name, $data, $event_id );
			if ( 'AddPaymentInfo' === $event_name ) {
				echo '<script>window.wpeasycart_meta_payment={sent:true};</script>';
			}
		}

		/**
		 * AJAX ec_ajax_meta_payment_info: the single page one-page checkout's payment section was reached; the browser
		 * already fired AddPaymentInfo with this event ID.
		 *
		 * @return void
		 */
		public static function ajax_payment_info() {
			if ( function_exists( 'wpeasycart_session' ) ) {
				wpeasycart_session()->handle_session();
			}
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wp-easycart-meta-' . self::session_id() ) ) {
				wp_send_json_error();
			}
			$event_id = isset( $_POST['event_id'] ) ? substr( sanitize_key( wp_unslash( $_POST['event_id'] ) ), 0, 64 ) : '';
			if ( self::flagged( 'AddPaymentInfo' ) || ! class_exists( 'ec_cart' ) || ! function_exists( 'ec_get_order_totals' ) ) {
				wp_send_json_success( array( 'sent' => false ) );
			}
			$cart     = new ec_cart( self::session_id() );
			$cartpage = (object) array(
				'cart'         => $cart,
				'order_totals' => ec_get_order_totals( $cart ),
			);
			$data     = self::checkout_data( $cartpage );
			$sent     = false;
			if ( false !== $data ) {
				$sent = ( '' !== self::track(
					'AddPaymentInfo',
					$data,
					array(
						'event_id' => $event_id,
						'browser'  => true,
						'once'     => 'checkout_AddPaymentInfo',
					)
				) );
				if ( $sent ) {
					self::flag( 'AddPaymentInfo' );
				}
			}
			wp_send_json_success( array( 'sent' => $sent ) );
		}

		/**
		 * See wp_easycart_meta_order_payment_info().
		 *
		 * @param int   $order_id    Order.
		 * @param float $grand_total Amount.
		 * @return void
		 */
		public static function order_payment_info( $order_id, $grand_total ) {
			$order_id = (int) $order_id;
			if ( $order_id <= 0 || '' === wp_easycart_meta_pixel_id() ) {
				return;
			}
			$contents = wp_easycart_meta_order_contents( $order_id );
			$data     = array(
				'value'        => round( (float) $grand_total, 2 ),
				'currency'     => wp_easycart_meta_currency(),
				'content_type' => 'product',
				'content_ids'  => $contents['content_ids'],
				'contents'     => $contents['contents'],
				'num_items'    => $contents['num_items'],
			);
			$event_id = self::track(
				'AddPaymentInfo',
				$data,
				array(
					'order_id' => $order_id,
					'once'     => 'api_order_' . $order_id,
				)
			);
			if ( '' !== $event_id ) {
				self::print_event( 'AddPaymentInfo', $data, $event_id );
			}
		}

		/**
		 * See wp_easycart_meta_purchase().
		 *
		 * @param object $order Order ( order_id, grand_total ).
		 * @return void
		 */
		public static function purchase( $order ) {
			if ( ! is_object( $order ) || empty( $order->order_id ) || '' === wp_easycart_meta_pixel_id() || isset( self::$once['purchase'] ) ) {
				return;
			}
			$order_id = (int) $order->order_id;
			$cookie   = 'ec_cart_facebook_order_id_tracked_' . $order_id;
			if ( isset( $_COOKIE[ $cookie ] ) ) {
				return;
			}
			self::$once['purchase'] = true;
			$contents               = wp_easycart_meta_order_contents( $order_id );
			$data                   = array(
				'value'        => round( isset( $order->grand_total ) ? (float) $order->grand_total : 0.0, 2 ),
				'currency'     => wp_easycart_meta_currency(),
				'content_type' => 'product',
				'content_ids'  => $contents['content_ids'],
				'contents'     => $contents['contents'],
				'num_items'    => $contents['num_items'],
				'order_id'     => (string) $order_id,
			);
			$path                   = ( defined( 'COOKIEPATH' ) && COOKIEPATH ) ? COOKIEPATH : '/';
			if ( ! headers_sent() ) {
				setcookie( $cookie, '1', time() + 30 * DAY_IN_SECONDS, $path, ( defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ) ? COOKIE_DOMAIN : '' );
			}
			/* The page has usually started by now, so the browser keeps the "already counted" cookie. */
			echo '<script>' . self::event_js( 'Purchase', $data, 'order_' . $order_id, 0 ) . 'document.cookie=' . self::json( $cookie . '=1; max-age=2592000; path=' . $path ) . ';</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded with JSON_HEX_TAG / _AMP / _APOS / _QUOT.
		}

		/**
		 * See wp_easycart_meta_search().
		 *
		 * @param string $search_string Search.
		 * @param array  $products      Results.
		 * @return void
		 */
		public static function search( $search_string, $products ) {
			$search_string = trim( wp_strip_all_tags( (string) $search_string ) );
			if ( '' === $search_string || '' === wp_easycart_meta_pixel_id() ) {
				return;
			}
			$content_ids = array();
			foreach ( array_slice( (array) $products, 0, 10 ) as $product ) {
				if ( is_object( $product ) && ! empty( $product->product_id ) ) {
					$content_ids[] = wp_easycart_meta_content_id( $product->product_id );
				}
			}
			$data     = array(
				'search_string' => $search_string,
				'content_ids'   => $content_ids,
				'content_type'  => 'product',
			);
			$event_id = self::track( 'Search', $data, array( 'once' => 'search' ) );
			if ( '' !== $event_id ) {
				self::print_event( 'Search', $data, $event_id );
			}
		}

		/* === Add to cart === */

		/**
		 * Quantity of a product in a cart session.
		 *
		 * @param int    $product_id Product.
		 * @param string $session_id Session, '' for the current one.
		 * @return int
		 */
		public static function cart_quantity( $product_id, $session_id = '' ) {
			global $wpdb;
			$session_id = ( '' === (string) $session_id ) ? self::session_id() : (string) $session_id;
			if ( '' === $session_id ) {
				return 0;
			}
			return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT SUM( quantity ) FROM ec_tempcart WHERE session_id = %s AND product_id = %d', $session_id, (int) $product_id ) );
		}

		/**
		 * The event ID the storefront script made for this add ( ec_meta_eid ), for the first add of the request only.
		 *
		 * @return string
		 */
		private static function request_event_id() {
			if ( self::$request_eid_used ) {
				return '';
			}
			self::$request_eid_used = true;
			// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- an event ID the storefront script made for this add; it only labels the event, every add path checks its own nonce.
			$raw = '';
			if ( isset( $_POST['ec_meta_eid'] ) ) {
				$raw = sanitize_key( wp_unslash( $_POST['ec_meta_eid'] ) );
			} elseif ( isset( $_GET['ec_meta_eid'] ) ) {
				$raw = sanitize_key( wp_unslash( $_GET['ec_meta_eid'] ) );
			}
			// phpcs:enable
			return substr( $raw, 0, 64 );
		}

		/**
		 * See wp_easycart_announce_cart_item_added().
		 *
		 * @param int        $tempcart_id Cart line.
		 * @param int        $product_id  Product.
		 * @param int        $before      Snapshot.
		 * @param string     $source      Source.
		 * @param string     $session_id  Session.
		 * @param array|null $cartitems   Session cart.
		 * @return array|false
		 */
		public static function announce_add( $tempcart_id, $product_id, $before, $source, $session_id, $cartitems ) {
			$tempcart_id = (int) $tempcart_id;
			$product_id  = (int) $product_id;
			$session_id  = ( '' === (string) $session_id ) ? self::session_id() : (string) $session_id;
			if ( $tempcart_id <= 0 || $product_id <= 0 || '' === $session_id ) {
				return false;
			}
			$added = self::cart_quantity( $product_id, $session_id ) - (int) $before;
			if ( $added <= 0 ) {
				return false; /* at the stock limit, or a re-saved design: nothing went in */
			}
			if ( ! is_array( $cartitems ) && class_exists( 'ec_db' ) ) {
				$db        = new ec_db();
				$cartitems = $db->get_temp_cart( $session_id );
			}
			$line = null;
			foreach ( (array) $cartitems as $cartitem ) {
				if ( is_object( $cartitem ) && isset( $cartitem->cartitem_id ) && (int) $cartitem->cartitem_id === $tempcart_id ) {
					$line = $cartitem;
					break;
				}
			}
			$ids = array();
			for ( $slot = 1; $slot <= 5; $slot++ ) {
				$ids[] = ( $line && isset( $line->{ 'optionitem' . $slot . '_id' } ) ) ? (int) $line->{ 'optionitem' . $slot . '_id' } : 0;
			}
			if ( $line && isset( $line->grid_quantity ) && (int) $line->grid_quantity > 0 ) {
				$added = (int) $line->grid_quantity; /* grid lines are never merged; the grid holds the real quantity */
			}
			$variant_id = wp_easycart_meta_variant_id( $product_id, $ids );
			$content_id = wp_easycart_meta_content_id( $product_id, $variant_id );
			$unit_price = ( $line && isset( $line->unit_price ) ) ? (float) $line->unit_price : 0.0;
			$name       = $line ? self::product_name( $line ) : '';
			$event_id   = self::request_event_id();
			$tracked    = ( '' !== $event_id );
			if ( ! $tracked ) {
				$event_id = wp_easycart_meta_event_id( 'atc' );
			}
			$source = in_array( $source, array( 'details', 'list', 'link', 'cart_link', 'deconetwork', 'other' ), true ) ? $source : 'other';

			self::$last_add = array(
				'product_id'   => $product_id,
				'quantity'     => $added,
				'content_id'   => $content_id,
				'value'        => round( $unit_price * $added, 2 ),
				'currency'     => wp_easycart_meta_currency(),
				'event_id'     => $event_id,
				'content_name' => $name,
				'tracked'      => $tracked,
				'tempcart_id'  => $tempcart_id,
			);

			/**
			 * An add to cart put something in the cart. Fired once per line by every add path ( product details, product
			 * lists, add links, cart links, DecoNetwork ), after the cart row is written.
			 *
			 * @since 6.0.2
			 * @param int   $tempcart_id    The cart line ( ec_tempcart ).
			 * @param int   $product_id     Product.
			 * @param int   $quantity_added What this add put in, even when merged into an existing line.
			 * @param float $unit_price     The line's unit price ( variant price, options, tiers, role prices ).
			 * @param array $args           optionitemquantity_id, content_id, source ( details | list | link | cart_link |
			 *                              deconetwork | other ), event_id ( the Meta event ID of this add ), content_name,
			 *                              browser ( the storefront script already fired AddToCart with event_id ).
			 */
			do_action(
				'wpeasycart_cart_item_added',
				$tempcart_id,
				$product_id,
				$added,
				$unit_price,
				array(
					'optionitemquantity_id' => $variant_id,
					'content_id'            => $content_id,
					'source'                => $source,
					'event_id'              => $event_id,
					'content_name'          => $name,
					'browser'               => $tracked,
				)
			);
			return self::$last_add;
		}

		/**
		 * The last add announced in this request.
		 *
		 * @return array|null
		 */
		public static function last_add() {
			return self::$last_add;
		}

		/**
		 * FREE's own listener: announce AddToCart. An add from a link or a cart link had no storefront script, so its
		 * browser event waits for the next page.
		 *
		 * @param int   $tempcart_id Cart line.
		 * @param int   $product_id  Product.
		 * @param int   $quantity    Quantity added.
		 * @param float $unit_price  Unit price.
		 * @param array $args        See wpeasycart_cart_item_added.
		 * @return void
		 */
		public static function on_cart_item_added( $tempcart_id, $product_id, $quantity, $unit_price, $args ) {
			if ( '' === wp_easycart_meta_pixel_id() ) {
				return;
			}
			$args       = wp_parse_args(
				$args,
				array(
					'optionitemquantity_id' => 0,
					'content_id'            => '',
					'source'                => 'other',
					'event_id'              => '',
					'content_name'          => '',
					'browser'               => false,
				)
			);
			$content_id = ( '' !== (string) $args['content_id'] ) ? (string) $args['content_id'] : wp_easycart_meta_content_id( $product_id, $args['optionitemquantity_id'] );
			$price      = round( (float) $unit_price, 2 );
			$data       = array(
				'value'        => round( $price * (int) $quantity, 2 ),
				'currency'     => wp_easycart_meta_currency(),
				'content_type' => 'product',
				'content_ids'  => array( $content_id ),
				'contents'     => array(
					array(
						'id'         => $content_id,
						'quantity'   => (int) $quantity,
						'item_price' => $price,
					),
				),
			);
			if ( '' !== (string) $args['content_name'] ) {
				$data['content_name'] = (string) $args['content_name'];
			}
			$queue    = ! $args['browser'] && in_array( $args['source'], array( 'link', 'cart_link', 'deconetwork' ), true );
			$event_id = self::track(
				'AddToCart',
				$data,
				array(
					'event_id'   => $args['event_id'],
					'product_id' => (int) $product_id,
					'browser'    => ( $args['browser'] || $queue ),
				)
			);
			if ( $queue && '' !== $event_id ) {
				self::queue( 'AddToCart', $data, $event_id );
			}
		}

		/**
		 * CompleteRegistration for storefront sign-ups ( the account page, and accounts made at checkout ); accounts added
		 * in the admin never count. The browser event shows on the next page.
		 *
		 * @param int    $user_id New account.
		 * @param string $email   Its email.
		 * @return void
		 */
		public static function on_account_added( $user_id, $email = '' ) {
			$data     = array( 'status' => true );
			$event_id = self::track(
				'CompleteRegistration',
				$data,
				array(
					'user_id' => (int) $user_id,
					'email'   => is_email( $email ) ? (string) $email : '',
					'browser' => true,
					'once'    => 'reg',
				)
			);
			if ( '' !== $event_id ) {
				self::queue( 'CompleteRegistration', $data, $event_id );
			}
		}
	}

	wp_easycart_meta::init();

endif;
