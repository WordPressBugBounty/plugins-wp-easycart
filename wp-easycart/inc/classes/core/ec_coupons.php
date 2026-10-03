<?php

/**
 * Legacy coupon codes ( ec_promocode ).
 *
 * @since 6.0.2 Each code is looked up on its own when it is redeemed. Before, every request loaded the whole table into
 *              memory ( a store with thousands of imported deal codes paid for that on every page ), and a code stored
 *              with a dash or an underscore could never match.
 */
class ec_coupons {

	/**
	 * Codes already looked up in this request, keyed by the lower-case code ( false = no such code ).
	 *
	 * @var array
	 */
	private $found = array();

	/**
	 * Every row, only when something still reads the old public $coupons list.
	 *
	 * @var array|null
	 */
	private $all = null;

	/**
	 * Nothing is loaded up front.
	 */
	public function __construct() {
	}

	/**
	 * Back-compat for code that read the old public $coupons list: loads every row on first read.
	 *
	 * @param string $name Property.
	 * @return mixed
	 */
	public function __get( $name ) {
		if ( 'coupons' !== $name ) {
			return null;
		}
		if ( null === $this->all ) {
			global $wpdb;
			$this->all = $wpdb->get_results( 'SELECT ec_promocode.*, IF( ec_promocode.expiration_date < NOW( ), 1, 0 ) AS coupon_expired FROM ec_promocode' );
			if ( ! is_array( $this->all ) ) {
				$this->all = array();
			}
		}
		return $this->all;
	}

	/**
	 * Back-compat for isset( $coupons->coupons ).
	 *
	 * @param string $name Property.
	 * @return bool
	 */
	public function __isset( $name ) {
		return ( 'coupons' === $name );
	}

	/**
	 * Clears what this request has looked up ( after a coupon changes ).
	 */
	public function forget() {
		$this->found = array();
		$this->all   = null;
	}

	/**
	 * The coupon row for a code the shopper typed, or false.
	 *
	 * Codes match without regard to case. A code typed with a dash or underscore also matches a stored code without
	 * them ( the only way those codes matched before 6.0.2 ).
	 *
	 * @param string $promocode_id Code as typed.
	 * @return object|false
	 */
	public function redeem_coupon_code( $promocode_id ) {
		$coupon = $this->lookup( $promocode_id );
		if ( ! $coupon ) {
			return false;
		}

		// Validate Subscription Coupon.
		if ( isset( $_GET['subscription'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: which subscription page the shopper is on.
			global $wpdb;
			$product = $wpdb->get_row( $wpdb->prepare( 'SELECT product_id, manufacturer_id FROM ec_product WHERE model_number = %s', sanitize_text_field( wp_unslash( $_GET['subscription'] ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only lookup.
			if ( $coupon->by_product_id ) { // validate product id match.
				return ( $product && $coupon->product_id == $product->product_id ) ? $coupon : false;
			} elseif ( $coupon->by_manufacturer_id ) { // validate manufacturer id match.
				return ( $product && $coupon->manufacturer_id == $product->manufacturer_id ) ? $coupon : false;
			} elseif ( $coupon->by_category_id ) { // validate category id match.
				if ( ! $product ) {
					return false;
				}
				$has_categories = $wpdb->get_results( $wpdb->prepare( 'SELECT categoryitem_id FROM ec_categoryitem WHERE category_id = %d AND product_id = %d', $coupon->category_id, $product->product_id ) );
				return ( $has_categories ) ? $coupon : false;
			}
		}
		return $coupon;
	}

	/**
	 * The coupon for a code typed on a subscription's checkout: a legacy coupon, or one another system answers for
	 * ( PRO: an Offers code whose offer discounts subscriptions ).
	 *
	 * An answer from the filter is shaped like an ec_promocode row with no product scope ( the answering system has
	 * checked it against this product ), plus stripe_coupon_id ( the Stripe coupon to attach; promocode_id stays the code
	 * the shopper typed, for the order ) and is_offer. A code that system knows but can't apply here comes back with
	 * blocked set, a product scope no product has, and the reason in message.
	 *
	 * The filter is asked first, the order the cart's code box uses ( an Offers code, else the classic coupon ): a classic
	 * coupon with the same code, for another product, used to hide an Offers code made for this subscription.
	 *
	 * @since 6.0.2
	 * @param string $promocode_id Code as typed or as kept in the cart session.
	 * @param int    $product_id   The subscription product.
	 * @return object|false
	 */
	public function subscription_coupon( $promocode_id, $product_id = 0 ) {
		if ( '' === trim( (string) $promocode_id ) ) {
			return false;
		}
		/**
		 * A subscription coupon from somewhere other than ec_promocode, asked before ec_promocode.
		 *
		 * @since 6.0.2
		 * @param object|false $coupon       False, or a legacy-shaped coupon ( see subscription_coupon() ).
		 * @param string       $promocode_id The code.
		 * @param int          $product_id   The subscription product.
		 */
		$other = apply_filters( 'wpeasycart_subscription_coupon', false, (string) $promocode_id, (int) $product_id );
		return ( is_object( $other ) ) ? $other : $this->redeem_coupon_code( $promocode_id );
	}

	/**
	 * The code as it is saved in ec_promocode, for a code as the shopper typed it ( redemption counts must update the
	 * saved row ). A code with no coupon comes back as given.
	 *
	 * @since 6.0.2
	 * @param string $promocode_id Code as typed or as kept in the cart session.
	 * @return string
	 */
	public function stored_code( $promocode_id ) {
		$coupon = $this->lookup( $promocode_id );
		return ( $coupon ) ? (string) $coupon->promocode_id : (string) $promocode_id;
	}

	/**
	 * The coupon row for a typed code: as typed, then without dashes and underscores.
	 *
	 * @param string $promocode_id Code as typed.
	 * @return object|false
	 */
	private function lookup( $promocode_id ) {
		$typed = preg_replace( '/[^A-Za-z0-9_\-\$\%]/', '', stripslashes_deep( (string) $promocode_id ) );
		if ( '' === $typed ) {
			return false;
		}
		$coupon = $this->find( $typed );
		if ( ! $coupon ) {
			$plain = preg_replace( '/[_\-]/', '', $typed );
			if ( '' !== $plain && $plain !== $typed ) {
				$coupon = $this->find( $plain );
			}
		}
		return $coupon;
	}

	/**
	 * One stored coupon by code ( case-insensitive ), remembered for the rest of the request.
	 *
	 * @param string $code Code, already cleaned.
	 * @return object|false
	 */
	private function find( $code ) {
		global $wpdb;
		$key = strtolower( $code );
		if ( ! array_key_exists( $key, $this->found ) ) {
			$select = 'SELECT ec_promocode.*, IF( ec_promocode.expiration_date < NOW( ), 1, 0 ) AS coupon_expired FROM ec_promocode';
			$row    = $wpdb->get_row( $wpdb->prepare( $select . ' WHERE ec_promocode.promocode_id = %s LIMIT 1', $code ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $select is a constant string.
			if ( ! $row ) {
				/* A table with a case-sensitive collation still matches the way the old in-memory compare did. */
				$row = $wpdb->get_row( $wpdb->prepare( $select . ' WHERE LOWER( ec_promocode.promocode_id ) = %s LIMIT 1', $key ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $select is a constant string.
			}
			$this->found[ $key ] = ( $row ) ? $row : false;
		}
		return $this->found[ $key ];
	}
}

add_action( 'wp', 'wp_easycart_apply_query_coupon', 9 );
function wp_easycart_apply_query_coupon( ){
	if( isset( $_GET['ec_coupon'] ) ){
		wpeasycart_session( )->handle_session();
		// Offers v2 codes get first attempt at the URL-applied coupon.
		$offer_handled = false;
		if ( wp_easycart_offers_active() ) {
			$offer_context = ec_offer_integration::get_context();
			$offer_apply = ec_offer_engine::apply_code( sanitize_text_field( $_GET['ec_coupon'] ), $GLOBALS['ec_cart_data']->ec_cart_id, $offer_context );
			if ( $offer_apply['success'] || 'cart_invalid_coupon' != $offer_apply['message_key'] ) {
				$offer_handled = true; // applied, or recognized-but-blocked (limits/stacking)
				wp_cache_flush( );
				do_action( 'wpeasycart_cart_updated' );
			}
		}
		if ( ! $offer_handled ) {
			$coupons = new ec_coupons( );
			if( $coupons->redeem_coupon_code( sanitize_text_field( $_GET['ec_coupon'] ) ) ){
				$GLOBALS['ec_cart_data']->cart_data->coupon_code = htmlspecialchars( sanitize_text_field( preg_replace( "/[^A-Za-z0-9_\-\$\%]/", '', stripslashes_deep( $_GET['ec_coupon'] ) ) ), ENT_QUOTES );
				$GLOBALS['ec_cart_data']->save_session_to_db( );
				wp_cache_flush( );
				do_action( 'wpeasycart_cart_updated' );
			}
		}
	}
}
