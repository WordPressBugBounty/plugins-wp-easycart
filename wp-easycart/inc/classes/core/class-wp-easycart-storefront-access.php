<?php
/**
 * WP EasyCart — what a shopper may add, download and open ( 6.0.2, bug round 14 ).
 *
 * One rule for each, asked by every path that reaches it, so no path is left behind a check the product page makes:
 *
 * - Add to cart ( can_add_to_cart(), wp_easycart_product_can_add_to_cart() ): the product is switched on ( store managers may
 *   add the inactive products they can preview ), the store is open to this shopper ( Who can view the store ), the product is
 *   not kept for another customer role, its price may be shown ( Login for pricing ), and neither the product ( catalog mode,
 *   inquiry mode ) nor the store ( shown as a catalog ) sells nothing. ec_db::add_to_cart() and ec_db::quick_add_to_cart() ask
 *   it, so the product page form, the AJAX adds, the store's add to cart links and cart links all follow it; the checkout asks
 *   again for what is already in the cart ( wp_easycart_variants::line_errors(), code product_unavailable ).
 * - Donation amounts ( donation_price() ): a number, above 0 and at least the product's price, the rule ec-store.js checks
 *   before the form is sent ( the server took any amount, a negative one lowered the order total ).
 * - Memberships ( has_membership(), wp_easycart_user_has_membership() ): a subscription product while its subscription is
 *   Active, or an installment plan that was paid in full; any other product once an order holding it is paid. The
 *   [ec_membership] shortcodes and the page lock read it.
 * - Downloads ( download_allowed() ): only from a paid order ( a refunded or cancelled order loses its downloads, a partial
 *   refund keeps them ) and not for a line whose options switch the download off, the rule the account pages show links by.
 *
 * @package WP_EasyCart
 * @since   6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_storefront_access' ) ) :

	/**
	 * Add to cart, donation, membership and download rules.
	 */
	class wp_easycart_storefront_access {

		/**
		 * The product columns the add to cart rule reads.
		 *
		 * @var string[]
		 */
		const ACCESS_COLUMNS = array( 'product_id', 'model_number', 'post_id', 'price', 'is_donation', 'activate_in_store', 'role_id', 'login_for_pricing', 'login_for_pricing_user_level', 'catalog_mode', 'inquiry_mode' );

		/**
		 * Product rows read for this request, product_id => row ( or false when there is none ).
		 *
		 * @var array
		 */
		private static $rows = array();

		/**
		 * Membership answers for this request, "user:ids" => bool.
		 *
		 * @var array
		 */
		private static $memberships = array();

		/**
		 * Forgets what this request read ( a product saved, or tests ).
		 */
		public static function forget() {
			self::$rows        = array();
			self::$memberships = array();
		}

		/* ------------------------------------------------------------------ Add to cart */

		/**
		 * The product row the rules read: a row that already carries every column ( ec_db::add_to_cart() and
		 * ec_db::quick_add_to_cart() pass theirs ), else read once per request by its id ( an id, or any object with product_id,
		 * such as an ec_product ).
		 *
		 * @param mixed $product Product id, row or object.
		 * @return object|false
		 */
		public static function product_row( $product ) {
			if ( is_object( $product ) ) {
				$complete = true;
				foreach ( self::ACCESS_COLUMNS as $column ) {
					if ( ! property_exists( $product, $column ) ) {
						$complete = false;
						break;
					}
				}
				if ( $complete ) {
					return $product;
				}
				$product = isset( $product->product_id ) ? $product->product_id : 0;
			}
			$product_id = (int) $product;
			if ( $product_id <= 0 ) {
				return false;
			}
			if ( ! array_key_exists( $product_id, self::$rows ) ) {
				self::prime( array( $product_id ) );
			}
			return self::$rows[ $product_id ];
		}

		/**
		 * Reads the rows of several products in one query ( the checkout asks for every line at once ).
		 *
		 * @param int[] $product_ids Product ids.
		 */
		public static function prime( $product_ids ) {
			global $wpdb;
			$ids = array();
			foreach ( (array) $product_ids as $product_id ) {
				$product_id = (int) $product_id;
				if ( $product_id > 0 && ! array_key_exists( $product_id, self::$rows ) ) {
					$ids[ $product_id ] = $product_id;
				}
			}
			if ( empty( $ids ) || ! is_object( $wpdb ) ) {
				return;
			}
			foreach ( $ids as $product_id ) {
				self::$rows[ $product_id ] = false;
			}
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT ' . implode( ', ', self::ACCESS_COLUMNS ) . ' FROM ec_product WHERE product_id IN ( ' . implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) . ' )', array_values( $ids ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed column list; one %d placeholder per id, bound by prepare().
			foreach ( (array) $rows as $row ) {
				self::$rows[ (int) $row->product_id ] = $row;
			}
		}

		/**
		 * The shopper the rules are asked for: the one given, else this visitor's store account.
		 *
		 * @param object|null $user An ec_user ( user_id, user_level, role_id ), or null for this visitor.
		 * @return object|null
		 */
		private static function shopper( $user ) {
			if ( is_object( $user ) ) {
				return $user;
			}
			return ( isset( $GLOBALS['ec_user'] ) && is_object( $GLOBALS['ec_user'] ) ) ? $GLOBALS['ec_user'] : null;
		}

		/**
		 * Whether the person signed in to WordPress manages the store ( they preview products that are switched off ).
		 *
		 * @return bool
		 */
		private static function is_manager() {
			return function_exists( 'current_user_can' ) && ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_manager' ) ); // phpcs:ignore WordPress.WP.Capabilities.Unknown -- EasyCart's own roles register this capability.
		}

		/**
		 * Whether the store is closed to this shopper ( Who can view the store ): wp_easycart_store_is_restricted() for this
		 * visitor, the same rule for another store account.
		 *
		 * @param object|null $user Shopper.
		 * @return bool
		 */
		private static function store_restricted( $user ) {
			$visitor = ( isset( $GLOBALS['ec_user'] ) && is_object( $GLOBALS['ec_user'] ) ) ? $GLOBALS['ec_user'] : null;
			if ( null === $user || $user === $visitor ) {
				return function_exists( 'wp_easycart_store_is_restricted' ) && wp_easycart_store_is_restricted();
			}
			$roles = get_option( 'ec_option_restrict_store' );
			if ( ! $roles ) {
				return false;
			}
			$level      = isset( $user->user_level ) ? (string) $user->user_level : '';
			$restricted = ( 'admin' !== $level && ! in_array( $level, explode( '***', (string) $roles ), true ) );
			/** This filter is documented in wpeasycart.php ( wp_easycart_store_is_restricted() ). */
			return (bool) apply_filters( 'wp_easycart_store_is_restricted', $restricted, $level );
		}

		/**
		 * The product list's customer role rule: role 0 is for everyone, any other role only for shoppers with that role ( a
		 * shopper without one matches role -1 ).
		 *
		 * @param object      $row  Product row.
		 * @param object|null $user Shopper.
		 * @return bool
		 */
		private static function role_allowed( $row, $user ) {
			$product_role = (int) $row->role_id;
			if ( 0 === $product_role ) {
				return true;
			}
			$shopper_role = ( $user && ! empty( $user->role_id ) ) ? (int) $user->role_id : -1;
			return $product_role === $shopper_role;
		}

		/**
		 * The rule of ec_product::is_login_for_pricing_valid() for a product row: a signed-in shopper, with one of the chosen user levels
		 * when the product names any.
		 *
		 * @param object      $row  Product row.
		 * @param object|null $user Shopper.
		 * @return bool
		 */
		public static function login_for_pricing_valid( $row, $user = null ) {
			$user = self::shopper( $user );
			if ( ! $user || empty( $user->user_id ) ) {
				return false;
			}
			$levels = isset( $row->login_for_pricing_user_level ) ? $row->login_for_pricing_user_level : null;
			if ( is_string( $levels ) ) {
				$levels = json_decode( $levels );
			}
			if ( ! is_array( $levels ) || 0 === count( $levels ) || in_array( '0', $levels ) ) { // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict -- stored levels may be strings or numbers, as ec_product compares them.
				return true;
			}
			return in_array( isset( $user->user_level ) ? $user->user_level : '', $levels ); // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict -- as ec_product::is_login_for_pricing_valid().
		}

		/**
		 * Whether this shopper may put the product in the cart.
		 *
		 * @param mixed       $product Product id, row or object.
		 * @param object|null $user    An ec_user, or null for this visitor.
		 * @return true|WP_Error Codes product_missing, product_inactive, store_restricted, product_role, login_for_pricing,
		 *                       catalog_mode, inquiry_mode, store_catalog, product_refused ( a filter said no ).
		 */
		public static function can_add_to_cart( $product, $user = null ) {
			$row  = self::product_row( $product );
			$user = self::shopper( $user );
			$code = '';
			if ( ! $row ) {
				$code = 'product_missing';
			} elseif ( empty( $row->activate_in_store ) && ! self::is_manager() ) {
				$code = 'product_inactive';
			} elseif ( self::store_restricted( $user ) ) {
				$code = 'store_restricted';
			} elseif ( ! self::role_allowed( $row, $user ) ) {
				$code = 'product_role';
			} elseif ( ! empty( $row->login_for_pricing ) && ! self::login_for_pricing_valid( $row, $user ) ) {
				$code = 'login_for_pricing';
			} elseif ( ! empty( $row->catalog_mode ) ) {
				$code = 'catalog_mode';
			} elseif ( ! empty( $row->inquiry_mode ) ) {
				$code = 'inquiry_mode';
			} elseif ( apply_filters( 'wp_easycart_catalog_display', get_option( 'ec_option_display_as_catalog' ) ) ) {
				$code = 'store_catalog'; /* the whole store shown as a catalog ( vacation mode ) */
			}
			$answer = ( '' === $code ) ? true : new WP_Error( $code, __( 'This product cannot be added to the cart.', 'wp-easycart' ) );
			/**
			 * Whether a shopper may put a product in the cart ( every add to cart path asks, and the checkout asks again for the
			 * lines already in the cart ). Return true, or a WP_Error to refuse.
			 *
			 * @since 6.0.2
			 *
			 * @param true|WP_Error $answer  The store's answer.
			 * @param object|false  $row     Product row ( product_id, model_number, post_id, price, is_donation, activate_in_store,
			 *                               role_id, login_for_pricing, login_for_pricing_user_level, catalog_mode, inquiry_mode ).
			 * @param object|null   $user    The shopper's ec_user.
			 */
			$answer = apply_filters( 'wp_easycart_product_can_add_to_cart', $answer, $row, $user );
			if ( true === $answer || is_wp_error( $answer ) ) {
				return $answer;
			}
			return $answer ? true : new WP_Error( 'product_refused', __( 'This product cannot be added to the cart.', 'wp-easycart' ) );
		}

		/**
		 * The page a refused add sends the shopper to: the product's page ( it says why: the inquiry form, the sign-in for
		 * prices, the catalog note ), else the store page ( no such product, or one switched off ).
		 *
		 * @param mixed $product Product id, row or object.
		 * @return string
		 */
		public static function product_url( $product ) {
			$row   = self::product_row( $product );
			$store = get_permalink( (int) get_option( 'ec_option_storepage' ) );
			$store = $store ? (string) $store : home_url( '/' );
			if ( ! $row || ( empty( $row->activate_in_store ) && ! self::is_manager() ) ) {
				return $store;
			}
			if ( ! get_option( 'ec_option_use_old_linking_style' ) && (int) $row->post_id > 0 ) {
				$link = get_permalink( (int) $row->post_id );
				if ( $link ) {
					return (string) $link;
				}
			}
			return add_query_arg( 'model_number', rawurlencode( (string) $row->model_number ), $store );
		}

		/* ------------------------------------------------------------------ Donations */

		/**
		 * The typed donation amount as a number, or null when it is not one. A number box always sends a dot; the older text
		 * box shows the store's own decimal symbol ( ec_product::display_product_input_price() ), so a comma store's "12,50"
		 * reads as 12.50.
		 *
		 * @param mixed $raw Posted amount.
		 * @return float|null
		 */
		public static function donation_amount( $raw ) {
			if ( is_int( $raw ) || is_float( $raw ) ) {
				$amount = (float) $raw;
				return is_finite( $amount ) ? $amount : null;
			}
			if ( ! is_string( $raw ) ) {
				return null;
			}
			$text = str_replace( array( ' ', "\xC2\xA0" ), '', trim( $raw ) );
			if ( ',' === (string) get_option( 'ec_option_currency_decimal_symbol' ) && false !== strpos( $text, ',' ) ) {
				$text = str_replace( array( '.', ',' ), array( '', '.' ), $text );
			}
			if ( '' === $text || ! is_numeric( $text ) ) {
				return null;
			}
			$amount = (float) $text;
			return is_finite( $amount ) ? $amount : null;
		}

		/**
		 * A donation amount the store accepts: a number above 0 and at least the product's price ( the rule ec-store.js checks
		 * before the form is sent, and the product page's error names ).
		 *
		 * @param mixed $raw   Posted amount.
		 * @param float $price The product's price ( the least one can give ).
		 * @return float|WP_Error Code donation_amount.
		 */
		public static function donation_price( $raw, $price ) {
			$amount  = self::donation_amount( $raw );
			$minimum = max( 0, (float) $price );
			if ( null === $amount || $amount <= 0 || $amount < $minimum - 0.000001 ) {
				return new WP_Error( 'donation_amount', __( 'Please enter a donation of at least the amount shown.', 'wp-easycart' ) );
			}
			return $amount;
		}

		/**
		 * Whether the product page shows this product's donation error when it loads: the server refused the amount and sent the
		 * shopper back ( ec_cartpage::process_add_to_cart_v3(), ?ec_donation_error=<product id> ).
		 *
		 * @param int $product_id Product.
		 * @return bool
		 */
		public static function donation_error_shown( $product_id ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read only: which product's error to show; nothing is changed.
			return isset( $_GET['ec_donation_error'] ) && (int) $_GET['ec_donation_error'] === (int) $product_id && (int) $product_id > 0;
		}

		/**
		 * The unit price a donation line is charged: what was given, never below the product's price or 0 ( a cart that took an
		 * amount before 6.0.2 checked it ).
		 *
		 * @param mixed $donation_price The line's donation_price.
		 * @param mixed $price          The product's price.
		 * @return float
		 */
		public static function donation_line_price( $donation_price, $price ) {
			$amount = is_numeric( $donation_price ) ? (float) $donation_price : 0.0;
			if ( ! is_finite( $amount ) ) {
				$amount = 0.0;
			}
			$minimum = is_numeric( $price ) ? (float) $price : 0.0;
			return max( 0.0, $minimum, $amount );
		}

		/* ------------------------------------------------------------------ Memberships */

		/**
		 * Positive product ids from a list ( "1,2,3", an array ).
		 *
		 * @param mixed $product_ids Ids.
		 * @return int[]
		 */
		private static function id_list( $product_ids ) {
			if ( ! is_array( $product_ids ) ) {
				$product_ids = explode( ',', (string) $product_ids );
			}
			$ids = array();
			foreach ( $product_ids as $product_id ) {
				if ( is_scalar( $product_id ) && (int) $product_id > 0 ) {
					$ids[ (int) $product_id ] = (int) $product_id;
				}
			}
			return array_values( $ids );
		}

		/**
		 * Whether a store account holds a membership for any of these products ( decision D5 ):
		 * - a subscription product: its subscription is Active, or it was an installment plan ( payment_duration ) whose every
		 *   payment was made ( as many paid orders as payments; the last payment ends the Stripe subscription, which is why
		 *   paid-in-full plans read Canceled ); a subscription cancelled before that ends the membership;
		 * - any other product: an order holding it is paid ( its status counts as paid; a refunded or cancelled order does not ).
		 * A product that no longer exists counts as a subscription product when it has subscriptions.
		 *
		 * @param int   $user_id     ec_user id.
		 * @param mixed $product_ids Ids ( "1,2" or an array ).
		 * @return bool
		 */
		public static function has_membership( $user_id, $product_ids ) {
			global $wpdb;
			$user_id = (int) $user_id;
			$ids     = self::id_list( $product_ids );
			if ( $user_id <= 0 || empty( $ids ) || ! is_object( $wpdb ) ) {
				return false;
			}
			$key = $user_id . ':' . implode( ',', $ids );
			if ( ! array_key_exists( $key, self::$memberships ) ) {
				$types    = $wpdb->get_results( $wpdb->prepare( 'SELECT product_id, is_subscription_item FROM ec_product WHERE product_id IN ( ' . implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) . ' )', $ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- one %d placeholder per id, bound by prepare().
				$found    = array();
				$recurs   = array();
				$one_time = array();
				foreach ( (array) $types as $type ) {
					$found[ (int) $type->product_id ] = true;
					if ( ! empty( $type->is_subscription_item ) ) {
						$recurs[] = (int) $type->product_id;
					} else {
						$one_time[] = (int) $type->product_id;
					}
				}
				$missing = array_values( array_diff( $ids, array_keys( $found ) ) );
				if ( ! empty( $missing ) ) {
					$with_subscriptions = $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT product_id FROM ec_subscription WHERE product_id IN ( ' . implode( ', ', array_fill( 0, count( $missing ), '%d' ) ) . ' )', $missing ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- one %d placeholder per id, bound by prepare().
					$with_subscriptions = array_map( 'intval', (array) $with_subscriptions );
					foreach ( $missing as $product_id ) {
						if ( in_array( $product_id, $with_subscriptions, true ) ) {
							$recurs[] = $product_id;
						} else {
							$one_time[] = $product_id;
						}
					}
				}
				$member = false;
				if ( ! empty( $recurs ) ) {
					$in_recurs = implode( ', ', array_fill( 0, count( $recurs ), '%d' ) );
					$active    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT( subscription_id ) FROM ec_subscription WHERE user_id = %d AND subscription_status = 'Active' AND product_id IN ( " . $in_recurs . ' )', array_merge( array( $user_id ), $recurs ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- one %d placeholder per id, bound by prepare().
					if ( $active > 0 ) {
						$member = true;
					} else {
						$paid_in_full = $wpdb->get_var( $wpdb->prepare( 'SELECT ec_subscription.subscription_id FROM ec_subscription INNER JOIN ec_order ON ec_order.subscription_id = ec_subscription.subscription_id INNER JOIN ec_orderstatus ON ec_orderstatus.status_id = ec_order.orderstatus_id AND ec_orderstatus.is_approved = 1 WHERE ec_subscription.user_id = %d AND ec_subscription.payment_duration > 0 AND ec_subscription.product_id IN ( ' . $in_recurs . ' ) GROUP BY ec_subscription.subscription_id, ec_subscription.payment_duration HAVING COUNT( DISTINCT ec_order.order_id ) >= ec_subscription.payment_duration LIMIT 1', array_merge( array( $user_id ), $recurs ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- one %d placeholder per id, bound by prepare().
						$member       = ! empty( $paid_in_full );
					}
				}
				if ( ! $member && ! empty( $one_time ) ) {
					$paid   = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT( ec_orderdetail.orderdetail_id ) FROM ec_order INNER JOIN ec_orderstatus ON ec_orderstatus.status_id = ec_order.orderstatus_id AND ec_orderstatus.is_approved = 1 INNER JOIN ec_orderdetail ON ec_orderdetail.order_id = ec_order.order_id WHERE ec_order.user_id = %d AND ec_orderdetail.product_id IN ( ' . implode( ', ', array_fill( 0, count( $one_time ), '%d' ) ) . ' )', array_merge( array( $user_id ), $one_time ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- one %d placeholder per id, bound by prepare().
					$member = ( $paid > 0 );
				}
				self::$memberships[ $key ] = $member;
			}
			/**
			 * Whether a store account holds a membership for any of these products ( the [ec_membership] shortcodes and the page
			 * lock ).
			 *
			 * @since 6.0.2
			 *
			 * @param bool  $member      The store's answer.
			 * @param int   $user_id     ec_user id.
			 * @param int[] $product_ids Product ids.
			 */
			return (bool) apply_filters( 'wp_easycart_user_has_membership', self::$memberships[ $key ], $user_id, $ids );
		}

		/* ------------------------------------------------------------------ Downloads */

		/**
		 * Whether an order line's download may be served: the order is paid ( its status counts as paid, so a refunded or
		 * cancelled order has none and a partial refund keeps them ) and none of the line's options switches the download off
		 * ( optionitem_allow_download, as the account pages decide whether to show the link ).
		 *
		 * @param object $orderdetail_row The line ( ec_db::get_orderdetail_row() ): order_id, orderdetail_id.
		 * @return bool
		 */
		public static function download_allowed( $orderdetail_row ) {
			global $wpdb;
			if ( ! is_object( $orderdetail_row ) || empty( $orderdetail_row->order_id ) || empty( $orderdetail_row->orderdetail_id ) || ! is_object( $wpdb ) ) {
				return false;
			}
			$paid    = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT ec_orderstatus.is_approved FROM ec_order INNER JOIN ec_orderstatus ON ec_orderstatus.status_id = ec_order.orderstatus_id WHERE ec_order.order_id = %d', (int) $orderdetail_row->order_id ) );
			$blocked = ( 1 === $paid ) ? (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT( order_option_id ) FROM ec_order_option WHERE orderdetail_id = %d AND optionitem_allow_download = 0', (int) $orderdetail_row->orderdetail_id ) ) : 0;
			$allowed = ( 1 === $paid && 0 === $blocked );
			/**
			 * Whether a customer's download from an order line is served.
			 *
			 * @since 6.0.2
			 *
			 * @param bool   $allowed         Paid order, and no option switches the download off.
			 * @param object $orderdetail_row The order line.
			 */
			return (bool) apply_filters( 'wp_easycart_download_allowed', $allowed, $orderdetail_row );
		}
	}

endif;

if ( ! function_exists( 'wp_easycart_product_can_add_to_cart' ) ) {
	/**
	 * Whether a shopper may put a product in the cart ( 6.0.2 ): see wp_easycart_storefront_access::can_add_to_cart().
	 *
	 * @since 6.0.2
	 *
	 * @param mixed       $product_row Product id, row or object.
	 * @param object|null $user        An ec_user, or null for this visitor.
	 * @return true|WP_Error
	 */
	function wp_easycart_product_can_add_to_cart( $product_row, $user = null ) {
		return wp_easycart_storefront_access::can_add_to_cart( $product_row, $user );
	}
}

if ( ! function_exists( 'wp_easycart_user_has_membership' ) ) {
	/**
	 * Whether a store account holds a membership for any of these products ( 6.0.2, decision D5 ): see
	 * wp_easycart_storefront_access::has_membership().
	 *
	 * @since 6.0.2
	 *
	 * @param int   $user_id     ec_user id.
	 * @param mixed $product_ids Ids ( "1,2" or an array ).
	 * @return bool
	 */
	function wp_easycart_user_has_membership( $user_id, $product_ids ) {
		return wp_easycart_storefront_access::has_membership( $user_id, $product_ids );
	}
}
