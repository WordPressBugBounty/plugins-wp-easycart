<?php
if( !class_exists( 'ec_taxcloud' ) ) :

final class ec_taxcloud{
	
	protected static $_instance = null;

	/**
	 * TaxCloud's TIC for a nontaxable product or service.
	 *
	 * @since 6.0.2
	 */
	const NONTAXABLE_TIC = '2';

	public $address_verified;
	
	public $tax_amount;
	
	public $subscription_product;
	public $subscription_product_quantity;
	public $subscription_product_discount = 0;
	public $subscription_product_option_price = 0;
	public $subscription_product_option_onetime = 0;
	
	public static function instance( ) {
		
		if( is_null( self::$_instance ) ) {
			self::$_instance = new self(  );
		}
		return self::$_instance;
	
	}
	
	public function __construct( ){
		
		add_action( 'init', array( $this, 'initiate_taxcloud' ), 10 );
		add_action( 'wpeasycart_cart_updated', array( $this, 'update_tax_amount' ), 10 );
		add_action( 'wpeasycart_order_inserted', array( $this, 'add_tax_cloud_order' ), 10, 5 );
		add_action( 'wpeasycart_order_paid', array( $this, 'approve_tax_cloud_order' ), 10, 1 );
		add_action( 'wpeasycart_order_status_update', array( $this, 'order_status_update' ), 10, 2 );
		add_action( 'wpeasycart_full_order_refund', array( $this, 'refund_tax_cloud_order' ), 10, 1 );
		
	}
	
	public function setup_subscription_for_tax( $product, $quantity, $discount = 0, $option_total = 0, $option_total_onetime = 0 ){
		$this->subscription_product = $product;
		$this->subscription_product_option_price = $option_total;
		$this->subscription_product_quantity = $quantity;
		$this->subscription_product_discount = $discount;
		$this->subscription_product_option_onetime = $option_total_onetime;
		$this->update_tax_amount( );
	}
	
	public function initiate_taxcloud( ){
		
		if( $GLOBALS['ec_cart_data']->cart_data->taxcloud_tax_amount != "" ){
			$this->tax_amount = $GLOBALS['ec_cart_data']->cart_data->taxcloud_tax_amount;
			$this->address_verified = $GLOBALS['ec_cart_data']->cart_data->taxcloud_address_verified;
			
		}else{
			$this->tax_amount = 0;
			$this->address_verified = 0;
			
		}
		
	}
	
	/**
	 * TaxCloud is on once both keys are saved.
	 *
	 * @since 6.0.2
	 * @return bool
	 */
	public function is_enabled() {
		if ( ! $this->has_keys() ) {
			return false;
		}
		/* 6.0.2: a store picks one tax service on Settings › Taxes; TaxCloud works only when it is the one. */
		return ! class_exists( 'wp_easycart_tax_providers' ) || wp_easycart_tax_providers::legacy_active( 'taxcloud' );
	}

	/**
	 * Both TaxCloud keys are saved ( TaxCloud may not be the store's tax service any more: returns of orders it already
	 * has still go to it ).
	 *
	 * @since 6.0.2
	 * @return bool
	 */
	public function has_keys() {
		return '' != get_option( 'ec_option_tax_cloud_api_id' ) && '' != get_option( 'ec_option_tax_cloud_api_key' );
	}

	/**
	 * TaxCloud captured this order ( its order log says so ).
	 *
	 * @since 6.0.2
	 * @param int $order_id Order.
	 * @return bool
	 */
	private function was_captured( $order_id ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT order_log_id FROM ec_order_log WHERE order_id = %d AND order_log_key = %s LIMIT 1', (int) $order_id, 'order-taxcloud-captured' ) );
	}

	/**
	 * The session ships to a US address. ec_tax only uses TaxCloud for those, so nothing else is verified or looked up.
	 *
	 * @since 6.0.2
	 * @return bool
	 */
	private function is_us_destination() {
		return 'US' == strtoupper( (string) $GLOBALS['ec_cart_data']->cart_data->shipping_country ) && '' != trim( (string) $GLOBALS['ec_cart_data']->cart_data->shipping_zip );
	}

	/**
	 * Action wpeasycart_cart_updated: TaxCloud's tax for the cart, kept in the session.
	 */
	public function update_tax_amount() {
		if ( ! $this->is_enabled() ) {
			return;
		}
		$tax_amount = 0;
		$address_verified = false;

		if ( $this->is_us_destination() ) {
			$cartpage = new ec_cartpage();
			$customerID = $GLOBALS['ec_cart_data']->cart_data->user_id;
			if ( $GLOBALS['ec_cart_data']->cart_data->is_guest ) {
				$customerID = $GLOBALS['ec_cart_data']->cart_data->guest_key;
			}

			/* 6.0.2: an address TaxCloud cannot match is still looked up as entered ( it used to count as verified ). */
			$address_verified = $this->tax_cloud_address_verification();
			$response = $this->request(
				'Lookup',
				array(
					'customerID' => $customerID,
					'cartID' => $GLOBALS['ec_cart_data']->ec_cart_id,
					'cartItems' => $this->get_tax_cloud_cartitems( $cartpage->order_totals->shipping_total, $this->get_taxable_discount( $cartpage ) ),
					'origin' => $this->get_tax_cloud_origin(),
					'destination' => $this->get_tax_cloud_destination(),
					'deliveredBySeller' => false,
					'exemptCert' => null,
				),
				'Lookup'
			);
			if ( false === $response ) {
				return; /* Keeps the last amount, as before. */
			}
			if ( isset( $response->ResponseType ) && 0 != $response->ResponseType && isset( $response->CartItemsResponse ) && is_array( $response->CartItemsResponse ) ) {
				foreach ( $response->CartItemsResponse as $cart_item ) {
					$tax_amount = $tax_amount + floatval( $cart_item->TaxAmount );
				}
			}
		}

		$this->tax_amount = $tax_amount;
		$this->address_verified = $address_verified;
		$GLOBALS['ec_cart_data']->cart_data->taxcloud_tax_amount = $this->tax_amount;
		$GLOBALS['ec_cart_data']->cart_data->taxcloud_address_verified = ( $this->address_verified ) ? 1 : 0;
		$GLOBALS['ec_cart_data']->save_session_to_db( );
	}

	/**
	 * The discount that lowers what is taxed: coupons, offers and cart promotions. A gift card pays for the order, so it
	 * does not lower the tax.
	 *
	 * @since 6.0.2
	 * @param ec_cartpage $cartpage Cart.
	 * @return float
	 */
	private function get_taxable_discount( $cartpage ) {
		if ( ! isset( $cartpage->discount ) || ! is_object( $cartpage->discount ) ) {
			return 0;
		}
		$discount = (float) $cartpage->discount->discount_total - (float) $cartpage->discount->giftcard_discount;
		return ( $discount > 0 ) ? $discount : 0;
	}
	
	// Action from ec_order
	public function add_tax_cloud_order( $order_id, $cart, $order_totals, $user, $payment_type ){
		if ( ! $this->is_enabled() ) {
			return;
		}

		/* 6.0.2: only a US cart had a Lookup to authorize. */
		if ( $this->is_us_destination() ) {
			$customerID = $GLOBALS['ec_cart_data']->cart_data->user_id;
			if ( $GLOBALS['ec_cart_data']->cart_data->is_guest ) {
				$customerID = $GLOBALS['ec_cart_data']->cart_data->guest_key;
			}
			$this->request(
				'Authorized',
				array(
					'customerID' => $customerID,
					'cartID' => $GLOBALS['ec_cart_data']->ec_cart_id,
					'orderID' => $order_id,
					'dateAuthorized' => gmdate( DATE_ATOM ),
					'dateCaptured' => gmdate( DATE_ATOM ),
				),
				'Insert Order'
			);
		}

		$GLOBALS['ec_cart_data']->cart_data->taxcloud_tax_amount = "";
		$GLOBALS['ec_cart_data']->cart_data->taxcloud_address_verified = 0;
		$GLOBALS['ec_cart_data']->cart_data->taxcloud_address_last_verified = '';
		$GLOBALS['ec_cart_data']->save_session_to_db( );
	}

	/**
	 * Action wpeasycart_order_paid: TaxCloud's Captured, once the order has an approved status. The action also fires at
	 * checkout for bank transfer and other manual payments that are not paid yet; those are captured when a status
	 * change approves them.
	 *
	 * @since 6.0.2 approved orders only, and once per order.
	 * @param int $order_id Order.
	 */
	public function approve_tax_cloud_order( $order_id ) {
		$order_id = (int) $order_id;
		if ( $this->is_enabled() && $this->is_us_order( $order_id, true ) ) {
			$this->once_per_order( $order_id, 'order-taxcloud-captured', 'send_captured' );
		}
	}

	/**
	 * Action wpeasycart_order_status_update: captures an order when its new status is approved.
	 *
	 * @since 6.0.2
	 * @param int $order_id  Order.
	 * @param int $status_id New status.
	 */
	public function order_status_update( $order_id, $status_id = 0 ) {
		$order_id = (int) $order_id;
		if ( in_array( (int) $status_id, array( 16, 17 ), true ) ) {
			return; /* Refunded: wpeasycart_full_order_refund handles it. */
		}
		if ( $this->is_enabled() && $this->is_us_order( $order_id, true, (int) $status_id ) ) {
			$this->once_per_order( $order_id, 'order-taxcloud-captured', 'send_captured' );
		}
	}

	/**
	 * Action wpeasycart_full_order_refund: TaxCloud's Returned, once per order.
	 *
	 * @param int $order_id Order.
	 */
	public function refund_tax_cloud_order( $order_id ) {
		$order_id = (int) $order_id;
		/* 6.0.2: after the store picks another tax service, only orders TaxCloud captured go back to it. */
		if ( $this->has_keys() && $this->is_us_order( $order_id ) && ( $this->is_enabled() || $this->was_captured( $order_id ) ) ) {
			$this->once_per_order( $order_id, 'order-taxcloud-returned', 'send_returned' );
		}
	}

	/**
	 * TaxCloud's Captured: the order is complete and counts in its reports.
	 *
	 * @param int $order_id Order.
	 * @return bool TaxCloud took it.
	 */
	private function send_captured( $order_id ) {
		return $this->is_accepted( $this->request( 'Captured', array( 'orderID' => $order_id ), 'Approve Order' ) );
	}

	/**
	 * TaxCloud's Returned: the whole order came back.
	 *
	 * @param int $order_id Order.
	 * @return bool TaxCloud took it.
	 */
	private function send_returned( $order_id ) {
		$response = $this->request(
			'Returned',
			array(
				'orderID' => $order_id,
				'returnedDate' => gmdate( DATE_ATOM ),
			),
			'Refund Order'
		);
		return $this->is_accepted( $response );
	}

	/**
	 * TaxCloud took the call, or says it already has it ( an order captured before 6.0.2 kept no log entry ).
	 *
	 * @since 6.0.2
	 * @param object|false $response Answer from request().
	 * @return bool
	 */
	private function is_accepted( $response ) {
		if ( ! $response || ! isset( $response->ResponseType ) ) {
			return false;
		}
		if ( 0 != $response->ResponseType ) {
			return true;
		}
		if ( isset( $response->Messages ) && is_array( $response->Messages ) ) {
			foreach ( $response->Messages as $message ) {
				if ( isset( $message->Message ) && false !== stripos( (string) $message->Message, 'already' ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * The order ships to the US ( TaxCloud only calculated those ) and, when asked, has an approved status.
	 *
	 * @since 6.0.2
	 * @param int  $order_id      Order.
	 * @param bool $must_approve  Require an approved status.
	 * @param int  $new_status_id Status being set ( read from ec_orderstatus rather than the order ).
	 * @return bool
	 */
	private function is_us_order( $order_id, $must_approve = false, $new_status_id = 0 ) {
		global $wpdb;
		$order = $wpdb->get_row( $wpdb->prepare( 'SELECT ec_order.shipping_country, ec_orderstatus.is_approved FROM ec_order LEFT JOIN ec_orderstatus ON ec_orderstatus.status_id = ec_order.orderstatus_id WHERE ec_order.order_id = %d', $order_id ) );
		if ( ! $order || 'US' != strtoupper( (string) $order->shipping_country ) ) {
			return false;
		}
		if ( ! $must_approve ) {
			return true;
		}
		if ( $new_status_id > 0 ) {
			return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT is_approved FROM ec_orderstatus WHERE status_id = %d', $new_status_id ) );
		}
		return (bool) $order->is_approved;
	}

	/**
	 * Runs a TaxCloud call once per order. Under a lock, it is skipped when the order log already has $log_key, and the
	 * key is logged ( the order timeline shows it ) once TaxCloud accepts the call.
	 *
	 * @since 6.0.2
	 * @param int    $order_id Order.
	 * @param string $log_key  Order log key.
	 * @param string $method   Method that makes the call and answers whether it was accepted.
	 */
	private function once_per_order( $order_id, $log_key, $method ) {
		global $wpdb;
		$lock = 'wpec_' . $log_key . '_' . $order_id;
		$locked = ( '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK( %s, 10 )', $lock ) ) );
		$done = $wpdb->get_var( $wpdb->prepare( 'SELECT order_log_id FROM ec_order_log WHERE order_id = %d AND order_log_key = %s LIMIT 1', $order_id, $log_key ) );
		if ( ! $done && call_user_func( array( $this, $method ), $order_id ) ) {
			$wpdb->insert(
				'ec_order_log',
				array(
					'order_id' => $order_id,
					'order_log_key' => $log_key,
				),
				array( '%d', '%s' )
			);
		}
		if ( $locked ) {
			$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', $lock ) );
		}
	}

	/**
	 * POSTs to the TaxCloud API ( the keys are added here ) and logs the answer.
	 *
	 * @since 6.0.2
	 * @param string $endpoint   Endpoint.
	 * @param array  $parameters Request, without the keys.
	 * @param string $label      Log label.
	 * @return object|false The decoded answer, false when the request failed.
	 */
	private function request( $endpoint, $parameters, $label ) {
		$db = new ec_db();
		$body = wp_json_encode(
			array_merge(
				array(
					'apiLoginID' => get_option( 'ec_option_tax_cloud_api_id' ),
					'apiKey' => get_option( 'ec_option_tax_cloud_api_key' ),
				),
				$parameters
			)
		);
		$request = new WP_Http();
		$response = $request->request(
			$this->get_tax_cloud_url() . $endpoint,
			array(
				'method' => 'POST',
				'body' => $body,
				'headers' => array(
					'Content-Type' => 'application/json',
					'Content-Length' => strlen( $body ),
				),
				'timeout' => 30,
			)
		);
		if ( is_wp_error( $response ) ) {
			$db->insert_response( 0, 1, 'TAX CLOUD ' . $label . ' ERROR', $response->get_error_message() );
			return false;
		}
		$db->insert_response( 0, 0, 'Tax Cloud ' . $label, print_r( $response, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r -- gateway log ( the keys are in the request body, which is not part of it ).
		$json = json_decode( wp_remote_retrieve_body( $response ) );
		return ( is_object( $json ) ) ? $json : false;
	}
	
	private function get_tax_cloud_url( ){
		return "https://api.taxcloud.net/1.0/Taxcloud/";
	}
	
	/**
	 * Checks the session's shipping address with TaxCloud ( USPS ) and saves the ZIP+4 it returns. The answer is kept in
	 * the session per address, so the next cart update with the same address makes no call; a failed request is tried
	 * again next time.
	 *
	 * @since 6.0.2 an address TaxCloud cannot match is recorded as unverified ( it was marked verified ), and the session
	 *              keeps a hash of the address rather than the request with the API keys.
	 * @return bool Verified.
	 */
	public function tax_cloud_address_verification() {
		$address = $this->get_tax_cloud_destination();
		$signature = md5( wp_json_encode( $address ) );
		$last_verified = (string) $GLOBALS['ec_cart_data']->cart_data->taxcloud_address_last_verified;
		if ( $signature . ':1' === $last_verified || $signature . ':0' === $last_verified ) {
			$this->address_verified = ( $signature . ':1' === $last_verified );
			return $this->address_verified;
		}

		$this->address_verified = false;
		$response = $this->request( 'VerifyAddress', $address, 'VerifyAddress' );
		if ( false === $response ) {
			return false;
		}

		if ( isset( $response->ErrNumber ) && '0' === (string) $response->ErrNumber ) {
			if ( isset( $response->Zip5 ) && '' != $response->Zip5 ) {
				$verified_zip = $response->Zip5 . ( ( isset( $response->Zip4 ) && '' != $response->Zip4 ) ? '-' . $response->Zip4 : '' );
				$GLOBALS['ec_cart_data']->cart_data->shipping_zip = $verified_zip;
				if( $GLOBALS['ec_cart_data']->cart_data->shipping_selector == 'false' ){
					$GLOBALS['ec_cart_data']->cart_data->billing_zip = $verified_zip;
				}
				$signature = md5( wp_json_encode( $this->get_tax_cloud_destination() ) );
			}
			$this->address_verified = true;
		}
		$GLOBALS['ec_cart_data']->cart_data->taxcloud_address_last_verified = $signature . ':' . ( ( $this->address_verified ) ? '1' : '0' );
		return $this->address_verified;
	}

	/**
	 * The cart ( or the subscription being bought ) as TaxCloud cartItems, plus shipping. A product that is not taxable
	 * goes as TIC 2, "Nontaxable product or service", at its full price; the discount only lowers the taxable lines, as
	 * ec_tax takes it off the taxable subtotal.
	 *
	 * @since 6.0.2 products that are not taxable are sent as nontaxable ( they went with their own TIC and were taxed ),
	 *              the discount is spread over the taxable lines only, and each line carries its full cart total.
	 * @param float $shipping_total Shipping.
	 * @param float $discount_total Discount that lowers what is taxed ( get_taxable_discount() ); a subscription uses its own.
	 * @return array
	 */
	private function get_tax_cloud_cartitems( $shipping_total, $discount_total ) {
		if ( isset( $this->subscription_product ) ) {
			$discount_total = $this->subscription_product_discount;
		}
		$lines = $this->get_cart_lines();
		$discounts = $this->allocate_discount( $lines, $discount_total );

		$cartitems = array();
		foreach ( $lines as $key => $line ) {
			$cartitems[] = array(
				'Index' => count( $cartitems ),
				'TIC' => ( $line['taxable'] ) ? $line['tic'] : self::NONTAXABLE_TIC,
				'ItemID' => $line['item_id'],
				'Price' => ( $line['total'] - $discounts[ $key ] ) / $line['quantity'],
				'Qty' => $line['quantity'],
			);
		}

		if ( $shipping_total > 0 ) {
			$cartitems[] = array(
				'Index' => count( $cartitems ),
				'TIC' => '11010',
				'ItemID' => 'Shipping',
				'Price' => $shipping_total,
				'Qty' => 1,
			);
		}

		$ec_db = new ec_db();
		$ec_db->insert_response( 0, 0, 'TaxCloud Cart Items', print_r( $cartitems, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r -- gateway log of the lines sent.
		return $cartitems;
	}

	/**
	 * The subscription being bought, or the cart, as lines before any discount: TIC, item ID, quantity, line total and
	 * whether the product is taxable. A cart item's one-time option charge is its own line of quantity 1.
	 *
	 * @since 6.0.2
	 * @return array
	 */
	private function get_cart_lines() {
		$lines = array();
		if ( isset( $this->subscription_product ) ) {
			$quantity = max( 1, (int) $this->subscription_product_quantity );
			$lines[] = array(
				'tic' => $this->subscription_product->TIC,
				'item_id' => $this->subscription_product->model_number,
				'quantity' => $quantity,
				'total' => ( ( (float) $this->subscription_product->price + (float) $this->subscription_product_option_price ) * $quantity ) + (float) $this->subscription_product_option_onetime,
				'taxable' => (bool) $this->subscription_product->is_taxable,
			);
			return $lines;
		}

		$cart = new ec_cart( $GLOBALS['ec_cart_data']->ec_cart_id );
		foreach ( (array) $cart->cart as $cartitem ) {
			$onetime = (float) $cartitem->options_price_onetime;
			$lines[] = array(
				'tic' => $cartitem->TIC,
				'item_id' => $cartitem->model_number,
				'quantity' => max( 1, (int) $cartitem->quantity ),
				'total' => max( 0, (float) $cartitem->total_price - $onetime ), /* total_price is what ec_cart counts as taxable ( grid options included ). */
				'taxable' => (bool) $cartitem->is_taxable,
			);
			if ( $onetime > 0 ) {
				$lines[] = array(
					'tic' => $cartitem->TIC,
					'item_id' => $cartitem->model_number,
					'quantity' => 1,
					'total' => $onetime,
					'taxable' => (bool) $cartitem->is_taxable,
				);
			}
		}
		return $lines;
	}

	/**
	 * Spreads the discount over the taxable lines in proportion to their totals, to the cent. Lines that are not taxable
	 * get none, and no line goes below zero ( discount beyond the taxable lines is not used ).
	 *
	 * @since 6.0.2
	 * @param array $lines    Lines from get_cart_lines().
	 * @param float $discount Discount.
	 * @return array Discount per line, keyed like $lines.
	 */
	private function allocate_discount( $lines, $discount ) {
		$discounts = array_fill_keys( array_keys( $lines ), 0 );
		$taxable_total = 0;
		$last_key = null;
		foreach ( $lines as $key => $line ) {
			if ( $line['taxable'] && $line['total'] > 0 ) {
				$taxable_total += $line['total'];
				$last_key = $key;
			}
		}
		$discount = min( round( (float) $discount, 2 ), round( $taxable_total, 2 ) );
		if ( $discount <= 0 || null === $last_key ) {
			return $discounts;
		}
		$remaining = $discount;
		foreach ( $lines as $key => $line ) {
			if ( ! $line['taxable'] || $line['total'] <= 0 ) {
				continue;
			}
			$share = ( $key === $last_key ) ? round( $remaining, 2 ) : round( $discount * $line['total'] / $taxable_total, 2 );
			$discounts[ $key ] = min( $line['total'], max( 0, $share ) );
			$remaining -= $discounts[ $key ];
		}
		return $discounts;
	}

	private function get_tax_cloud_origin( ){
		
		$zip_split = explode( '-', get_option( 'ec_option_tax_cloud_zip' ) );
		$zip5 = get_option( 'ec_option_tax_cloud_zip' );
		if( count( $zip_split ) > 0 )
			$zip5 = $zip_split[0];
		
		$zip4 = "";
		if( count( $zip_split ) > 1 )
			$zip4 = $zip_split[1];
		
		$origin = array(	"Address1"	=> get_option( 'ec_option_tax_cloud_address' ),
							"City"		=> get_option( 'ec_option_tax_cloud_city' ),
							"State"		=> get_option( 'ec_option_tax_cloud_state' ),
							"Zip5"		=> $zip5,
							"Zip4"		=> $zip4
						 );
		return $origin;
							 
	}
	
	private function get_tax_cloud_destination( ){
		
		$zip_split = explode( '-', $GLOBALS['ec_cart_data']->cart_data->shipping_zip );
		$zip5 = $GLOBALS['ec_cart_data']->cart_data->shipping_zip;
		if( count( $zip_split ) > 0 )
			$zip5 = $zip_split[0];
		
		$zip4 = "";
		if( count( $zip_split ) > 1 )
			$zip4 = $zip_split[1];
		
		$parameters = array( 	"Address1"		=> $GLOBALS['ec_cart_data']->cart_data->shipping_address_line_1,
								"Address2"		=> $GLOBALS['ec_cart_data']->cart_data->shipping_address_line_2,
								"City"			=> $GLOBALS['ec_cart_data']->cart_data->shipping_city,
								"State"			=> $GLOBALS['ec_cart_data']->cart_data->shipping_state,
								"Zip5"			=> $zip5,
								"Zip4"			=> $zip4
							);
		return $parameters; 
		
		
	}
	
}
endif; // End if class_exists check


function wpeasycart_taxcloud( ){

	return ec_taxcloud::instance( );

}

$GLOBALS['wpeasycart_taxcloud'] = wpeasycart_taxcloud( );

?>