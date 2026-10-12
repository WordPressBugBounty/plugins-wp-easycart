<?php
/**
 * What WP EasyCart keeps in the object cache, and how a change forgets only what it changed.
 *
 * Until 6.0.4 the storefront called wp_cache_flush() on every add to cart, checkout save and payment. Without a persistent object
 * cache that only empties the request's own memory. With one ( Redis, Memcached ) it empties the whole site's cache, every page's and
 * every plugin's, many times a minute, and WordPress keeps transients only there, so whatever the checkout had put in a transient
 * ( checkout protection's marks, a pay link's Stripe intent ) was gone mid-checkout. Each change now forgets the keys it changed, from
 * the shared cache and from the request's memory alike. Without a persistent cache the old flush still runs ( runtime() ): there it
 * only ever emptied the request's own memory, so those stores behave exactly as before.
 *
 * What is cached ( group: keys, for how long ):
 * - wpeasycart-user: wpeasycart-user-<user> ( ec_db::get_user(): the account with its default addresses ), an hour.
 * - wpeasycart-orders: wpeasycart-order-list-<user>, wpeasycart-order-<user>-<order>, wpeasycart-order-details-<user>-<order>, an hour.
 * - wpeasycart-subscriptions: wpeasycart-subscriptions-<user>, an hour.
 * - wpeasycart-product-list: product lookups and product rows under a generation ( ec_db::product_cache_changed() ), a day.
 * - wpeasycart-categories: the category tree, the store page's category lists and each product's categories under a generation
 *   ( ec_db::category_cache_changed(), through categories_changed() ), a day.
 * - The store's settings, options, menus, countries, rates, fees, promotions and page options: until the admin screen that changes
 *   them forgets them.
 * Nothing is cached per cart: the cart and the checkout session are read from the database on every request.
 *
 * @package WP EasyCart
 * @since 6.0.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_cache' ) ) :

	/**
	 * Forget what a storefront change made out of date ( static ).
	 */
	final class wp_easycart_cache {

		/**
		 * The account each order belonged to when it was last forgotten in this request ( an order moved to another account ).
		 *
		 * @var array
		 */
		private static $order_users = array();

		/**
		 * Whether the end of this request forgets product lookups once more.
		 *
		 * @var bool
		 */
		private static $stock_owed = false;

		/**
		 * The cart or the checkout session changed ( an item added, changed or removed, an address, a shipping choice, a code ).
		 *
		 * Nothing is cached per cart, so there is nothing to forget ( runtime() ).
		 */
		public static function cart_changed() {
			self::runtime();
		}

		/**
		 * The shopper's account may have changed with the checkout ( an account made or signed in to, the newsletter box, addresses ).
		 */
		public static function shopper_changed() {
			self::forget_shopper();
			self::runtime();
		}

		/**
		 * Where the old flush ran: never a persistent cache other requests share.
		 *
		 * Without a persistent object cache WordPress's cache is only this request's memory, and wp_cache_flush() is what 6.0.3 did
		 * here. With one, nothing: the keys a change made out of date were deleted, from the request's memory too. Not
		 * wp_cache_flush_runtime(): WordPress's stand-in for a cache without one ( cache-compat.php ) calls wp_cache_flush(), and some
		 * drop-ins define it that way themselves, which would empty the shared cache again.
		 */
		public static function runtime() {
			if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() ) {
				return;
			}
			wp_cache_flush();
		}

		/**
		 * Forget one account: its row ( with its default addresses ), its order list and its subscriptions.
		 *
		 * @param int $user_id Store account ( ec_user ).
		 */
		public static function forget_user( $user_id ) {
			$user_id = (int) $user_id;
			if ( $user_id <= 0 ) {
				return;
			}
			wp_cache_delete( 'wpeasycart-user-' . $user_id, 'wpeasycart-user' );
			wp_cache_delete( 'wpeasycart-order-list-' . $user_id, 'wpeasycart-orders' );
			wp_cache_delete( 'wpeasycart-subscriptions-' . $user_id, 'wpeasycart-subscriptions' );
		}

		/**
		 * Forget the shopper of this request: the account on the checkout session and the signed-in account ( they differ just
		 * after a sign in or a sign out ).
		 */
		public static function forget_shopper() {
			$ids = array();
			if ( isset( $GLOBALS['ec_cart_data']->cart_data ) && is_object( $GLOBALS['ec_cart_data']->cart_data ) && isset( $GLOBALS['ec_cart_data']->cart_data->user_id ) ) {
				$ids[] = (int) $GLOBALS['ec_cart_data']->cart_data->user_id;
			}
			if ( isset( $GLOBALS['ec_user'] ) && is_object( $GLOBALS['ec_user'] ) && isset( $GLOBALS['ec_user']->user_id ) ) {
				$ids[] = (int) $GLOBALS['ec_user']->user_id;
			}
			foreach ( array_unique( $ids ) as $user_id ) {
				self::forget_user( $user_id );
			}
		}

		/**
		 * Forget an order as its customer sees it ( My Account, the order confirmation page ), and the customer's account, whose
		 * stored totals follow its orders.
		 *
		 * @param int $order_id Order.
		 */
		public static function forget_order( $order_id ) {
			global $wpdb;
			$order_id = (int) $order_id;
			if ( $order_id <= 0 || ! isset( $wpdb ) ) {
				return;
			}
			$users                          = isset( self::$order_users[ $order_id ] ) ? array( self::$order_users[ $order_id ] ) : array();
			$now                            = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT user_id FROM ec_order WHERE order_id = %d', $order_id ) );
			self::$order_users[ $order_id ] = $now;
			$users[]                        = $now;
			foreach ( array_unique( $users ) as $user_id ) {
				if ( $user_id > 0 ) {
					wp_cache_delete( 'wpeasycart-order-' . $user_id . '-' . $order_id, 'wpeasycart-orders' );
					wp_cache_delete( 'wpeasycart-order-details-' . $user_id . '-' . $order_id, 'wpeasycart-orders' );
					self::forget_user( $user_id );
				}
			}
		}

		/**
		 * Forget the subscription lists of the accounts that own some subscriptions.
		 *
		 * @param int|string $value  What the subscriptions are found by.
		 * @param string     $column subscription_id, stripe_subscription_id or paypal_subscr_id.
		 */
		public static function forget_subscription( $value, $column = 'subscription_id' ) {
			global $wpdb;
			if ( ! isset( $wpdb ) || '' === (string) $value || ! in_array( $column, array( 'subscription_id', 'stripe_subscription_id', 'paypal_subscr_id' ), true ) ) {
				return;
			}
			$users = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT user_id FROM ec_subscription WHERE `{$column}` = %s", (string) $value ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $column is one of three names checked above.
			foreach ( (array) $users as $user_id ) {
				self::forget_user( $user_id );
			}
		}

		/**
		 * Stock moved ( an order, a pay link, a cancelled order's stock back ): product lookups carry stock, so every one is retired, now
		 * and once more as the request ends ( stock moved later in the same request, such as a bundle's parts, is then covered too ).
		 */
		public static function stock_changed() {
			self::forget_products();
			if ( ! self::$stock_owed && function_exists( 'add_action' ) ) {
				self::$stock_owed = true;
				add_action( 'shutdown', array( __CLASS__, 'forget_products' ), 1 );
			}
		}

		/**
		 * Retire every cached product lookup ( a new generation, ec_db::product_cache_changed() ).
		 */
		public static function forget_products() {
			if ( class_exists( 'ec_db' ) && method_exists( 'ec_db', 'product_cache_changed' ) ) {
				ec_db::product_cache_changed();
			}
		}

		/**
		 * Categories changed ( added, switched on or off, featured, renamed, moved, deleted, put back, linked to another page, products
		 * added to or taken out of one ): every cached category lookup ( the category tree, the store page's category lists, each
		 * product's categories ) and the product lookups, which list a category's products.
		 *
		 * Until 6.0.4 nothing forgot the category lookups, so a store with a persistent object cache kept showing the categories it
		 * had before a change ( a category switched off stayed on the store page ).
		 */
		public static function categories_changed() {
			if ( class_exists( 'ec_db' ) && method_exists( 'ec_db', 'category_cache_changed' ) ) {
				ec_db::category_cache_changed();
			}
			self::forget_products();
		}

		/**
		 * A review was added: the store's review list, the product's rating summary and the product lookups ( ratings ).
		 *
		 * @param int $review_id Review.
		 */
		public static function review_added( $review_id = 0 ) {
			if ( class_exists( 'ec_reviews' ) && method_exists( 'ec_reviews', 'flush' ) ) {
				ec_reviews::flush( (int) $review_id );
			} else {
				wp_cache_delete( 'wpeasycart-reviews' );
			}
			self::forget_products();
		}

		/**
		 * A page's store options changed ( ec_db::update_page_option() ).
		 *
		 * @param int $post_id Page.
		 */
		public static function forget_page_options( $post_id ) {
			wp_cache_delete( 'wpeasycart-get-page-options-' . (int) $post_id, 'wpeasycart-page-options' );
		}
	}

endif;
