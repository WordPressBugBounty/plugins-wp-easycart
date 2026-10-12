<?php

class ec_order{
	protected $mysqli;													// ec_db structure
	
	private $cart;														// ec_cart structure
	private $user;														// ec_user structure
	private $shipping;													// ec_shipping structure
	private $tax;														// ec_tax structure
	private $discount;													// ec_discounts structure
	private $order_totals;												// ec_order_totals structure
	
	public $payment;													// ec_payment structure
	
	public $order_customer_notes;										// BLOB
	public $process_result;												// INT
	public $order_status;												// INT
	
	public $order_id;													// INT

	/**
	 * 6.0.4: set by a checkout that took the money before it saved the order ( Stripe, PayPal ). A gift card that cannot be
	 * redeemed then never removes the order: it is kept, giftcard_hold_note says why, and that checkout holds it.
	 *
	 * @var bool
	 */
	public $payment_taken = false;

	/**
	 * 6.0.4: why the order's gift card could not be redeemed ( '' = it was, or the order used none ). A Square gift card's own
	 * reason ( WP EasyCart PRO ), else "Not a valid gift card number".
	 *
	 * @var string
	 */
	public $giftcard_failed = '';

	/**
	 * 6.0.4: the staff note for a paid order kept without its gift card ( payment_taken ); '' otherwise.
	 *
	 * @var string
	 */
	public $giftcard_hold_note = '';

	private $store_page;												// VARCHAR
	private $cart_page;													// VARCHAR
	private $account_page;												// VARCHAR
	private $permalink_divider;											// CHAR
	
	const SUCCESS 		= 0;											// INT			
	const ORDERERROR 	= 1;											// INT
	const GATEWAYERROR 	= 2;											// INT
	
	function __construct( $cart, $user, $shipping, $tax, $discount, $order_totals, $payment ){
		$this->mysqli = new ec_db( );
		
		$this->cart = $cart;
		$this->user = $user;
		$this->shipping = $shipping;	
		$this->tax = $tax;
		$this->discount = $discount;
		$this->order_totals = $order_totals;
		$this->payment = $payment;
		
		$store_page_id = get_option('ec_option_storepage');
		$cart_page_id = get_option('ec_option_cartpage');
		$account_page_id = apply_filters( 'wp_easycart_account_page_id', get_option( 'ec_option_accountpage' ) );
		
		if( function_exists( 'icl_object_id' ) ){
			$store_page_id = icl_object_id( $store_page_id, 'page', true, ICL_LANGUAGE_CODE );
			$cart_page_id = icl_object_id( $cart_page_id, 'page', true, ICL_LANGUAGE_CODE );
			$account_page_id = icl_object_id( $account_page_id, 'page', true, ICL_LANGUAGE_CODE );
		}
		
		$this->store_page = get_permalink( $store_page_id );
		$this->cart_page = get_permalink( $cart_page_id );
		$this->account_page = get_permalink( $account_page_id );
		
		if( class_exists( "WordPressHTTPS" ) && isset( $_SERVER['HTTPS'] ) ){
			$https_class = new WordPressHTTPS( );
			$this->store_page = $https_class->makeUrlHttps( $this->store_page );
			$this->cart_page = $https_class->makeUrlHttps( $this->cart_page );
			$this->account_page = $https_class->makeUrlHttps( $this->account_page );
		}
		
		if( substr_count( $this->cart_page, '?' ) )					$this->permalink_divider = "&";
		else														$this->permalink_divider = "?";
		
	}

	public function verify_stock() {
		global $wpdb;
		$valid_stock = true;
		$products_in_cart = array();
		$product_ids_in_cart = array();
		$option_combos_in_cart = array();
		$option_combo_ids_in_cart = array();
		for ( $i = 0; $i < count( $this->cart->cart ); $i++ ) {
			if ( ! $this->cart->cart[ $i ]->allow_backorders ) {
				if ( ! in_array( $this->cart->cart[ $i ]->product_id, $product_ids_in_cart ) ) {
					$product_ids_in_cart[] = $this->cart->cart[ $i ]->product_id;
					$products_in_cart[] = (object) array(
						'product_id' => $this->cart->cart[ $i ]->product_id,
						'quantity' => $this->cart->cart[ $i ]->quantity,
						'cart_item_indexes' => array( $i ),
					);
				} else {
					for( $j = 0; $j < count( $products_in_cart ); $j++ ) {
						if ( $products_in_cart[ $j ]->product_id == $this->cart->cart[ $i ]->product_id ) {
							$products_in_cart[ $j ]->quantity += $this->cart->cart[ $i ]->quantity;
							$products_in_cart[ $j ]->cart_item_indexes[] = $i;
						}
					}
				}

				if ( $this->cart->cart[ $i ]->use_optionitem_quantity_tracking && ( ! $this->cart->cart[ $i ]->use_advanced_optionset || $this->cart->cart[ $i ]->use_both_option_types ) ) {
					if ( ! in_array( $this->cart->cart[ $i ]->product_id . '-' . $this->cart->cart[ $i ]->optionitem1_id . '-' . $this->cart->cart[ $i ]->optionitem2_id . '-' . $this->cart->cart[ $i ]->optionitem3_id . '-' . $this->cart->cart[ $i ]->optionitem4_id . '-' . $this->cart->cart[ $i ]->optionitem5_id, $option_combo_ids_in_cart ) ) {
						$option_combo_ids_in_cart[] = $this->cart->cart[ $i ]->product_id . '-' . $this->cart->cart[ $i ]->optionitem1_id . '-' . $this->cart->cart[ $i ]->optionitem2_id . '-' . $this->cart->cart[ $i ]->optionitem3_id . '-' . $this->cart->cart[ $i ]->optionitem4_id . '-' . $this->cart->cart[ $i ]->optionitem5_id;
						$option_combos_in_cart[] = (object) array(
							'option_combo_id' => $this->cart->cart[ $i ]->product_id . '-' . $this->cart->cart[ $i ]->optionitem1_id . '-' . $this->cart->cart[ $i ]->optionitem2_id . '-' . $this->cart->cart[ $i ]->optionitem3_id . '-' . $this->cart->cart[ $i ]->optionitem4_id . '-' . $this->cart->cart[ $i ]->optionitem5_id,
							'product_id' => $this->cart->cart[ $i ]->product_id,
							'optionitem_id_1' => $this->cart->cart[ $i ]->optionitem1_id,
							'optionitem_id_2' => $this->cart->cart[ $i ]->optionitem2_id,
							'optionitem_id_3' => $this->cart->cart[ $i ]->optionitem3_id,
							'optionitem_id_4' => $this->cart->cart[ $i ]->optionitem4_id,
							'optionitem_id_5' => $this->cart->cart[ $i ]->optionitem5_id,
							'quantity' => $this->cart->cart[ $i ]->quantity,
							'cart_item_indexes' => array( $i ),
						);
					} else {
						for( $j = 0; $j < count( $option_combos_in_cart ); $j++ ) {
							if ( $option_combos_in_cart[ $j ]->option_combo_id == $this->cart->cart[ $i ]->product_id . '-' . $this->cart->cart[ $i ]->optionitem1_id . '-' . $this->cart->cart[ $i ]->optionitem2_id . '-' . $this->cart->cart[ $i ]->optionitem3_id . '-' . $this->cart->cart[ $i ]->optionitem4_id . '-' . $this->cart->cart[ $i ]->optionitem5_id ) {
								$option_combos_in_cart[ $j ]->quantity += $this->cart->cart[ $i ]->quantity;
								$option_combos_in_cart[ $j ]->cart_item_indexes[] = $i;
							}
						}
					}
				}
			}
		}

		for ( $i = 0; $i < count( $option_combos_in_cart ); $i++ ) {
			$optionitem_quantity_row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_optionitemquantity WHERE product_id = %d AND optionitem_id_1 = %d AND optionitem_id_2 = %d AND optionitem_id_3 = %d AND optionitem_id_4 = %d AND optionitem_id_5 = %d', $option_combos_in_cart[ $i ]->product_id, $option_combos_in_cart[ $i ]->optionitem_id_1, $option_combos_in_cart[ $i ]->optionitem_id_2, $option_combos_in_cart[ $i ]->optionitem_id_3, $option_combos_in_cart[ $i ]->optionitem_id_4, $option_combos_in_cart[ $i ]->optionitem_id_5 ) );
			if ( $optionitem_quantity_row && is_object( $optionitem_quantity_row ) && $optionitem_quantity_row->is_stock_tracking_enabled && $optionitem_quantity_row->quantity < $option_combos_in_cart[ $i ]->quantity ) {
				$available_stock = $optionitem_quantity_row->quantity;
				foreach ( $option_combos_in_cart[ $i ]->cart_item_indexes as $cart_item_index ) {
					if ( $available_stock <= 0 ) {
						$this->mysqli->delete_cartitem( $this->cart->cart[ $cart_item_index ]->cartitem_id, $GLOBALS['ec_cart_id'] );
					} else if ( $available_stock < $this->cart->cart[ $cart_item_index ]->quantity ) {
						$this->cart->cart[ $cart_item_index ]->quantity = $available_stock;
						$this->mysqli->update_cartitem( $this->cart->cart[ $cart_item_index ]->cartitem_id, $GLOBALS['ec_cart_id'], $available_stock );
						$available_stock = 0;
					} else {
						$available_stock -= $this->cart->cart[ $cart_item_index ]->quantity;
					}
				}
				$valid_stock = false;
			}
		}

		for ( $i = 0; $i < count( $products_in_cart ); $i++ ) {
			$product = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_product WHERE product_id = %d', $products_in_cart[ $i ]->product_id ) );
			if ( $product->show_stock_quantity && $product->stock_quantity < $products_in_cart[ $i ]->quantity ) {
				$available_stock = $product->stock_quantity;
				foreach ( $products_in_cart[ $i ]->cart_item_indexes as $cart_item_index ) {
					if ( $available_stock <= 0 ) {
						$this->mysqli->delete_cartitem( $this->cart->cart[ $cart_item_index ]->cartitem_id, $GLOBALS['ec_cart_id'] );
					} else if ( $available_stock < $this->cart->cart[ $cart_item_index ]->quantity ) {
						$this->cart->cart[ $cart_item_index ]->quantity = $available_stock;
						$this->mysqli->update_cartitem( $this->cart->cart[ $cart_item_index ]->cartitem_id, $GLOBALS['ec_cart_id'], $available_stock );
						$available_stock = 0;
					} else {
						$available_stock -= $this->cart->cart[ $cart_item_index ]->quantity;
					}
				}
				$valid_stock = false;
			}
		}
		if ( ! $valid_stock ) {
			$this->process_result = 'stock_error';
		}
		return $valid_stock;
	}

	public function submit_order( $payment_type ) {
		// Get payment gateway used (if live) and if we support refunds with this method.
		$order_gateway = "";
		if( $payment_type == "affirm" )
			$order_gateway = "affirm";
		else if( $payment_type == "credit_card" && get_option( 'ec_option_payment_process_method' ) == "stripe" )
			$order_gateway = "stripe";
		else if( $payment_type == "credit_card" && get_option( 'ec_option_payment_process_method' ) == "stripe_connect" )
			$order_gateway = "stripe_connect";
		else if( $payment_type == "credit_card" && get_option( 'ec_option_payment_process_method' ) == "square" )
			$order_gateway = "square";
		else if( $payment_type == "credit_card" && get_option( 'ec_option_payment_process_method' ) == "authorize" )
			$order_gateway = "authorize";
		else if( $payment_type == "credit_card" && get_option( 'ec_option_payment_process_method' ) == "beanstream" )
			$order_gateway = "beanstream";
		else if( $payment_type == "credit_card" && get_option( 'ec_option_payment_process_method' ) == "intuit" )
			$order_gateway = "intuit";
		else if( $payment_type == "credit_card" && get_option( 'ec_option_payment_process_method' ) == "payline" )
			$order_gateway = "payline";
		else if( $payment_type == "credit_card" && get_option( 'ec_option_payment_process_method' ) == "nmi" )
			$order_gateway = "nmi";
		else if( $payment_type == "credit_card" && get_option( 'ec_option_payment_process_method' ) == "braintree" )
			$order_gateway = "braintree";
		else if( $payment_type == "credit_card" && get_option( 'ec_option_payment_process_method' ) == "cardpointe" )
			$order_gateway = "cardpointe";
		else if( $payment_type == "credit_card" && get_option( 'ec_option_payment_process_method' ) == "paytrace" )
			$order_gateway = "paytrace";
		else if( $payment_type == "amazonpay" )
			$order_gateway = "amazonpay";
		// End order gateway section

		$wpec_payment_kind = (string) $payment_type; /* 6.0.4: credit_card, manual_bill, third_party … as asked ( the card's brand replaces credit_card below ) */
		if( $payment_type == "credit_card" || $payment_type == "affirm" )
			$payment_type = $this->payment->credit_card->payment_method;
		
		$this->order_customer_notes = "";
		if( isset( $_POST['ec_order_notes'] ) )
			$this->order_customer_notes = sanitize_textarea_field( $_POST['ec_order_notes'] );
		else if( $GLOBALS['ec_cart_data']->cart_data->order_notes )
			$this->order_customer_notes = sanitize_textarea_field( $GLOBALS['ec_cart_data']->cart_data->order_notes );
		
		$this->order_id = $this->mysqli->insert_order( $this->cart, $this->user, $this->shipping, $this->tax, $this->discount, $this->order_totals, $this->payment, $payment_type, "5", $this->order_customer_notes, $order_gateway );
		
		if ( -1 == $this->order_id ) {
			$this->process_result = 'stock_error';
		} else if ( $this->order_id != 0 ) {
			
			// First process gift cards if using third party system
			$gift_card_processed_successfully = true;
			if( $this->discount->giftcard_discount > 0 ){
				/**
				 * Redeem the order's gift card before the payment is taken. An outside gift card system answers it ( WP EasyCart
				 * PRO's Square gift cards ); WP EasyCart's own cards are taken off once the order is paid ( update_giftcard_total() ).
				 *
				 * @since 6.0.4 $payment_type and $order; a WP_Error answer.
				 * @param string|bool|WP_Error $redeemed     The code: redeemed. False or a WP_Error ( its message goes to the shopper ): not.
				 * @param int                  $order_id     Order.
				 * @param float                $amount       The gift card's share of the order.
				 * @param string               $payment_type credit_card, affirm, manual_bill, third_party, ideal or amazonpay ( as asked: never the card's brand ).
				 * @param ec_order             $order        This order ( payment_taken: the rest was paid before the order was saved ).
				 */
				$wpec_redeemed = apply_filters( 'wpeasycart_redeem_gift_card', $this->discount->giftcard_code, $this->order_id, $this->discount->giftcard_discount, $wpec_payment_kind, $this );
				$gift_card_processed_successfully = ( $wpec_redeemed && ! is_wp_error( $wpec_redeemed ) );
				if ( ! $gift_card_processed_successfully ) {
					$this->giftcard_failed = ( is_wp_error( $wpec_redeemed ) && '' !== $wpec_redeemed->get_error_message() ) ? $wpec_redeemed->get_error_message() : wp_strip_all_tags( (string) wp_easycart_language()->get_text( 'cart_coupons', 'cart_invalid_giftcard' ) );
					if ( $this->payment_taken ) {
						/* 6.0.4: the rest was already paid: a paid order is never removed ( see keep_paid_order_without_giftcard() ). */
						$this->keep_paid_order_without_giftcard();
						$gift_card_processed_successfully = true;
					}
				}
			}

			if( $gift_card_processed_successfully ){
				
				// Now Process Payments
				if( $this->order_totals->grand_total <= 0 ){
					$this->mysqli->update_order_status( $this->order_id, "3" );
					$this->process_result = "1";
					$this->order_status = "3";
					
				}else if( $payment_type == "manual_bill" ){
					$this->mysqli->update_order_status( $this->order_id, "14" );
					$this->process_result = "1";
					$this->order_status = "14";
				
				}else if( $payment_type == "third_party" || $payment_type == "ideal" || $payment_type == "amazonpay" ){
					$this->mysqli->update_order_status( $this->order_id, "8" );
					$this->process_result = "1";
					$this->order_status = "8";
				
				}else if( get_option( 'ec_option_payment_process_method' ) == "nmi" && get_option( 'ec_option_nmi_3ds' ) == "2" ){
					$this->process_result = "1";
					$this->order_status = "5";
					$this->payment->is_3d_auth = true;
				
				}else{
					$this->process_result = $this->payment->process_payment( $this->cart, $this->user, $this->shipping, $this->tax, $this->discount, $this->order_totals, $this->order_id );
					if( $this->process_result == "1" && !$this->payment->is_3d_auth ){
						$this->mysqli->update_order_status( $this->order_id, "6" );
						$this->order_status = "6";
						do_action( 'wpeasycart_payment_processed_successfully', $this->payment, $this->user );
					
					// Held for Review
					}else if( $this->process_result == "2" && !$this->payment->is_3d_auth ){
						$this->mysqli->update_order_status( $this->order_id, "4" );
						$this->order_status = "4";
						do_action( 'wpeasycart_payment_processed_successfully', $this->payment, $this->user );
					}
					
				}
				
				if( $this->process_result == "1" ){
					$this->insert_details( $payment_type );
					$this->update_user_addresses();
					
					do_action( 'wpeasycart_order_inserted', $this->order_id, $this->cart, $this->order_totals, $this->user, $payment_type );
					
					if( $this->shipping->shipping_method == "fraktjakt" ){
						// Insert order for shipping method
						$ship_order_info = $this->shipping->submit_fraktjakt_shipping_order( );
						$this->mysqli->update_order_fraktjakt_info( $this->order_id, $ship_order_info );
					}
					
					// Deconetwork if used
					if( ( $payment_type != "third_party" && !$this->payment->is_3d_auth && $payment_type != "ideal" && $payment_type != "amazonpay" ) || $payment_type == "affirm" || ( $payment_type == "third_party" && ( get_option( 'ec_option_payment_process_method' ) == "stripe" || get_option( 'ec_option_payment_process_method' ) == "stripe_connect" ) ) )
						$this->process_deconetwork_complete( "true" );
					else
						$this->process_deconetwork_complete( "false" );
					
					// Run Necessary Processes Based on Payment Type
					if( !$this->payment->is_3d_auth ){
						if( $payment_type != 'third_party' && $payment_type != "ideal" && $payment_type != "amazonpay" ){

							/* 6.0.4: an order is announced as paid only when its status counts as paid. A bank transfer or check
							   ( Direct Deposit Pending ) is announced once the store marks it paid ( a paid status, Mark as paid,
							   its pay link ); code that wants it as it is placed listens to wpeasycart_order_inserted. */
							if ( $this->status_counts_as_paid() ) {
								do_action( 'wpeasycart_order_paid', $this->order_id );
							}
							$this->send_email_receipt(); //leave it to third party to send email
						
						}
						
						if( $payment_type == 'third_party' && ( get_option( 'ec_option_paypal_enable_pay_now' ) || get_option( 'ec_option_payment_third_party' ) == "paymentexpress_thirdparty" || get_option( 'ec_option_payment_third_party' ) == "payfort" ) ){
							// Try not clearing session for this provider.
						
						}else if( $payment_type == 'ideal' || $payment_type == 'amazonpay' ){
							// Do not clear session for ideal or amazonpay
						
						}else{
							$this->mysqli->clear_tempcart( $GLOBALS['ec_cart_data']->ec_cart_id );
							$this->clear_session( );
						}

						if( $this->discount->giftcard_code && '' === $this->giftcard_failed ){
							$this->mysqli->update_giftcard_total( $this->discount->giftcard_code, $this->discount->giftcard_discount );
							$this->confirm_giftcard();
						}
					}
				
				}else if( $this->process_result == "2" ){
					$this->insert_details( $payment_type );
					$this->update_user_addresses();
					
					do_action( 'wpeasycart_order_inserted', $this->order_id, $this->cart, $this->order_totals, $this->user, $payment_type );
					
					$this->mysqli->clear_tempcart( $GLOBALS['ec_cart_data']->ec_cart_id );
					$this->clear_session( );

					if( $this->discount->giftcard_code && '' === $this->giftcard_failed ){
						$this->mysqli->update_giftcard_total( $this->discount->giftcard_code, $this->discount->giftcard_discount );
						$this->confirm_giftcard();
					}

					$this->process_result = "1";
					
				}else{ // CC Payment Failed, Void GC if applicable
					if( $this->discount->giftcard_discount > 0 ){
						do_action( 'wpeasycart_void_gift_card_redemption', $this->discount->giftcard_code, $this->order_id );
					}
					/**
					 * A card payment was refused ( every direct card gateway, FREE and PRO, passes here ).
					 * Checkout protection counts it; the order row is removed right after.
					 *
					 * @since 6.0.2
					 * @param int      $order_id Order about to be removed.
					 * @param string   $message  The gateway's answer.
					 * @param string   $gateway  Gateway.
					 * @param ec_order $order    This order.
					 */
					do_action( 'wpeasycart_payment_failed', $this->order_id, (string) $this->process_result, $order_gateway, $this );
					$this->mysqli->remove_order( $this->order_id );
				}
			}else{ //Gift Card failed to process
				$this->mysqli->remove_order( $this->order_id );
				/* 6.0.4: the reason goes back to the shopper ( the Square checkout answers process_result, which was left empty ). */
				$this->process_result = $this->giftcard_failed;
				return "gift-card-error";
			}
			
			return $this->process_result;
			
		}else{
			$this->mysqli->insert_response( $this->order_id, 1, __( 'Error Inserting Order', 'wp-easycart' ), '' );
			return "Error Inserting Order";
		}
	}

	/**
	 * 6.0.4: the order's gift card was redeemed and the order is paid ( wpeasycart_redeem_gift_card answered yes, the payment
	 * went through ): an outside gift card system takes what it only held ( WP EasyCart PRO completes a Square gift card payment
	 * it held while the card was charged ). The counterpart of wpeasycart_void_gift_card_redemption.
	 */
	private function confirm_giftcard() {
		if ( $this->discount->giftcard_discount <= 0 ) {
			return;
		}
		/**
		 * The order's gift card was redeemed and the order is paid.
		 *
		 * @since 6.0.4
		 * @param string $code     Gift card.
		 * @param int    $order_id Order.
		 * @param float  $amount   The gift card's share of the order.
		 */
		do_action( 'wpeasycart_confirm_gift_card_redemption', $this->discount->giftcard_code, $this->order_id, $this->discount->giftcard_discount );
	}

	/**
	 * 6.0.4: the shopper already paid the rest ( payment_taken: Stripe or PayPal took the money before the order was saved ), but
	 * the gift card could not be redeemed. Removing the order would lose a paid order, so it stays: nothing was taken from the
	 * card, so the order no longer names one ( a cancel or refund would give back what it never took ), and giftcard_hold_note
	 * says what is still owed for the checkout that took the money to hold the order with.
	 */
	private function keep_paid_order_without_giftcard() {
		global $wpdb;
		$code   = (string) $this->discount->giftcard_code;
		$amount = (float) $this->discount->giftcard_discount;
		if ( method_exists( 'ec_db', 'order_accounting_columns_ready' ) && ec_db::order_accounting_columns_ready() ) {
			$wpdb->query( $wpdb->prepare( "UPDATE ec_order SET giftcard_id = '', giftcard_total = 0 WHERE order_id = %d", (int) $this->order_id ) );
		} else {
			$wpdb->query( $wpdb->prepare( "UPDATE ec_order SET giftcard_id = '' WHERE order_id = %d", (int) $this->order_id ) );
		}
		$owed = ( isset( $GLOBALS['currency'] ) && is_object( $GLOBALS['currency'] ) ) ? wp_strip_all_tags( (string) $GLOBALS['currency']->get_currency_display( $amount ) ) : number_format( $amount, 2 );
		$this->giftcard_hold_note = sprintf(
			/* translators: 1: gift card, 2: why it could not be redeemed, 3: amount. */
			__( 'Gift card %1$s could not be redeemed ( %2$s ), but the rest of this order was already paid, so the order was kept and put on hold. The %3$s the gift card was to pay is still owed.', 'wp-easycart' ),
			function_exists( 'wp_easycart_giftcard_label' ) ? wp_easycart_giftcard_label( $code ) : $code,
			$this->giftcard_failed,
			$owed
		);
	}

	private function insert_details( $payment_type ) {
		for ( $i = 0; $i < count( $this->cart->cart ); $i++ ) {
			$this->insert_details_helper( $this->cart->cart[ $i ], $payment_type );
		}
	}

	private function insert_details_helper( &$cart_item, $payment_type ){
		global $wpdb;
		if ( $cart_item->is_giftcard || $cart_item->is_download ) {
			$num_times = $cart_item->quantity;
			$cart_item->quantity = 1;
			$cart_item->total_price = $cart_item->unit_price;

			for ( $i = 0; $i < $num_times; $i++ ) {
				$giftcard_id = 0;
				if ( $cart_item->is_giftcard ) {
					/**
					 * Who issues the gift cards this order sells. '' = WP EasyCart, now ( a card in ec_giftcard ). Anything else
					 * ( WP EasyCart PRO's 'square' ) leaves the line without a code: that issuer makes the card once the order is
					 * paid, saves its number on the line and sends the gift card email ( ec_orderdisplay::send_gift_cards() ).
					 *
					 * @since 6.0.4
					 * @param string      $issuer       ''.
					 * @param ec_cartitem $cart_item    The gift card line.
					 * @param int         $order_id     Order.
					 * @param string      $payment_type manual_bill, third_party … ( for a card, the card's brand ).
					 */
					$wpec_issuer = (string) apply_filters( 'wp_easycart_gift_card_issuer', '', $cart_item, $this->order_id, $payment_type );
					$giftcard_id = ( '' === $wpec_issuer ) ? $this->mysqli->insert_new_giftcard( $cart_item->gift_card_value, $cart_item->gift_card_message ) : '';
				}
				$cart_item->giftcard_id = $giftcard_id;

				if ( $cart_item->is_giftcard && '' !== (string) $giftcard_id && $payment_type != "manual_bill" && $payment_type != "third_party" && $payment_type != "ideal" && $payment_type != "amazonpay" && !$this->payment->is_3d_auth ) {
					$this->send_gift_card_email( $cart_item, $giftcard_id );
				}

				$download_id = 0;
				if ( $cart_item->is_download ) {
					$download_id = $this->mysqli->insert_new_download( 	$this->order_id, $cart_item->download_file_name, $cart_item->product_id, $cart_item->is_amazon_download, $cart_item->amazon_key );
				}
				$cart_item->download_id = $download_id;

				for ( $j = 0; $j < count( $cart_item->advanced_options ); $j++ ) {
					if ( $cart_item->advanced_options[ $j ]->optionitem_download_addition_file ) {
						if ( '{' == substr( $cart_item->advanced_options[ $j ]->optionitem_download_addition_file, 0, 1 ) ) {
							$additional_file_json = json_decode( $cart_item->advanced_options[ $j ]->optionitem_download_addition_file );
							if ( is_object( $additional_file_json ) && isset( $additional_file_json->is_additional_file ) && isset( $additional_file_json->is_additional_amazon ) && '1' == $additional_file_json->is_additional_file && '0' == $additional_file_json->is_additional_amazon ) {
								$cart_item->advanced_options[ $j ]->download_id = $this->mysqli->insert_new_download( $this->order_id, $additional_file_json->additional_file_name, $cart_item->product_id, false, "" );
							} else if ( is_object( $additional_file_json ) && isset( $additional_file_json->is_additional_file ) && isset( $additional_file_json->is_additional_amazon ) && '1' == $additional_file_json->is_additional_file && '1' == $additional_file_json->is_additional_amazon ) {
								$cart_item->advanced_options[ $j ]->download_id = $this->mysqli->insert_new_download( $this->order_id, '', $cart_item->product_id, true, $additional_file_json->additional_amazon_key );
							}
						} else {
							$cart_item->advanced_options[ $j ]->download_id = $this->mysqli->insert_new_download( $this->order_id, $cart_item->advanced_options[ $j ]->optionitem_download_addition_file, $cart_item->product_id, false, "" );
						}
					}
				}

				$orderdetail_id = $this->mysqli->insert_order_detail( $this->order_id, $giftcard_id, $download_id, $cart_item );
			}
			$cart_item->quantity = $num_times;

		} else {
			$orderdetail_id = $this->mysqli->insert_order_detail( $this->order_id, 0, 0, $cart_item );
		}

		$cart_item->orderdetail_id = $orderdetail_id;
		
		if ( $payment_type != "manual_bill" && $payment_type != "third_party" && $payment_type != "ideal" && $payment_type != "amazonpay" ) {
			if ( $cart_item->use_optionitem_quantity_tracking ) {
				$this->mysqli->update_quantity_value( $cart_item->quantity, $cart_item->product_id, $cart_item->optionitem1_id, $cart_item->optionitem2_id, $cart_item->optionitem3_id, $cart_item->optionitem4_id, $cart_item->optionitem5_id );
			}
			$this->mysqli->update_product_stock( $cart_item->product_id, $cart_item->quantity );
			$this->mysqli->update_details_stock_adjusted( $orderdetail_id );
			$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log( order_id, order_log_key ) VALUES( %d, "order-stock-update" )', $this->order_id ) );
			$order_log_id = $wpdb->insert_id;
			$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "product_id", %s )', $order_log_id, $this->order_id, $cart_item->product_id ) );
			$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "quantity", %s )', $order_log_id, $this->order_id, '-' . $cart_item->quantity ) );
		}
	}

	/**
	 * Once an order is placed, the customer's account keeps the address it used wherever the account has none yet.
	 *
	 * 6.0.4 ( Matt ): the one-page checkout makes its account first ( Create account: the name only ) and saves the address typed after
	 * it to the checkout session alone, so a new account kept empty addresses after its first order, while the stepped checkout's
	 * Continue saves them. This wrote the account's own saved address back over itself ( a signed-in shopper's ec_user holds the
	 * saved one, never what was typed ). Each side is read from the order just saved ( what checkout recorded ); a side the account
	 * already has is left as it is, and a side the order has no street for ( a pickup ) is skipped.
	 *
	 * @since 6.0.4
	 */
	private function update_user_addresses() {
		global $wpdb;
		$order = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_order WHERE order_id = %d', (int) $this->order_id ) );
		if ( ! $order || ! (int) $order->user_id ) {
			return;
		}
		$user_id = (int) $order->user_id;
		$account = $wpdb->get_row( $wpdb->prepare( 'SELECT default_billing_address_id, default_shipping_address_id FROM ec_user WHERE user_id = %d', $user_id ) );
		if ( ! $account ) {
			return;
		}
		$filled = false;
		foreach ( array( 'billing', 'shipping' ) as $side ) {
			if ( '' === trim( (string) $order->{ $side . '_address_line_1' } ) ) {
				continue;
			}
			$address_id = (int) $account->{ 'default_' . $side . '_address_id' };
			$saved      = ( $address_id ) ? $wpdb->get_row( $wpdb->prepare( 'SELECT address_line_1, city FROM ec_address WHERE address_id = %d AND user_id = %d', $address_id, $user_id ) ) : null;
			if ( $saved && ( '' !== trim( (string) $saved->address_line_1 ) || '' !== trim( (string) $saved->city ) ) ) {
				continue;
			}
			$values = array(
				(string) $order->{ $side . '_first_name' },
				(string) $order->{ $side . '_last_name' },
				(string) $order->{ $side . '_address_line_1' },
				(string) $order->{ $side . '_address_line_2' },
				(string) $order->{ $side . '_city' },
				(string) $order->{ $side . '_state' },
				(string) $order->{ $side . '_zip' },
				(string) $order->{ $side . '_country' },
				(string) $order->{ $side . '_phone' },
				(string) $order->{ $side . '_company_name' },
			);
			if ( $saved ) {
				$this->mysqli->update_user_address( $address_id, $values[0], $values[1], $values[2], $values[3], $values[4], $values[5], $values[6], $values[7], $values[8], $values[9], $user_id );
			} else {
				/* No row of its own ( none saved, or one that belongs to no account ): a new one, made the account's default. */
				$this->mysqli->insert_user_address( $values[0], $values[1], $values[9], $values[2], $values[3], $values[4], $values[5], $values[6], $values[7], $values[8], $user_id, $side );
			}
			$filled = true;
		}
		if ( $filled ) {
			wp_easycart_cache::forget_user( $user_id );
			do_action( 'wpeasycart_account_updated', $user_id, true );
		}
	}
	
	/**
	 * Does the status checkout gave this order count as paid ( ec_orderstatus.is_approved; the built-in flags are fixed )?
	 *
	 * @since 6.0.4
	 * @return bool
	 */
	private function status_counts_as_paid() {
		global $wpdb;
		return (bool) (int) $wpdb->get_var( $wpdb->prepare( 'SELECT is_approved FROM ec_orderstatus WHERE status_id = %d', (int) $this->order_status ) );
	}

	public function send_email_receipt(){

		$db_admin = new ec_db_admin( );
		$order_row = $db_admin->get_order_row_admin( $this->order_id );
		$order_display = new ec_orderdisplay( $order_row, true, true );
		$order_display->send_email_receipt( );
		
	}
	
	public function send_gift_card_email( $cart_item, $giftcard_id ){
		global $wpdb;
		$cart_item->gift_card_value = $wpdb->get_var( $wpdb->prepare( "SELECT amount FROM ec_giftcard WHERE giftcard_id = %s", $cart_item->giftcard_id ) );
		$email_logo_url = get_option( 'ec_option_email_logo' );
	 	
		$headers   = array();
		$headers[] = "MIME-Version: 1.0";
		$headers[] = "Content-Type: text/html; charset=utf-8";
		$headers[] = "From: " . stripslashes( get_option( 'ec_option_order_from_email' ) );
		$headers[] = "Reply-To: " . stripslashes( get_option( 'ec_option_order_from_email' ) );
		$headers[] = "X-Mailer: PHP/" . phpversion( );
		
		ob_start();
        if( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_email_giftcard.php' ) )	
			include EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_email_giftcard.php';
		else
			include EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_email_giftcard.php';
			
        $message = ob_get_clean();
		
		$email_send_method = get_option( 'ec_option_use_wp_mail' );
		$email_send_method = apply_filters( 'wpeasycart_email_method', $email_send_method );
		
		/* 6.0.2: only addresses that can take mail. A copy with none is not sent: the email log shows one "not sent" row, where
		   a blank or invalid store address used to fail and retry five times. */
		$subject  = wp_easycart_language( )->get_text( "cart_success", "cart_giftcard_receipt_title" );
		$to       = trim( stripslashes( (string) $cart_item->gift_card_email ) );
		$admin_to = trim( stripslashes( (string) get_option( 'ec_option_bcc_email_addresses' ) ) );
		$log      = class_exists( 'ec_email' ) && method_exists( 'ec_email', 'send_to' );
		if ( $log ) {
			$to       = ec_email::send_to( $to, 'giftcard', (int) $this->order_id, $subject );
			$admin_to = ec_email::send_to( $admin_to, 'giftcard_store', 0, $subject, false );
		}

		if( $email_send_method == "1" ){
			if ( '' !== $to ) {
				$outer = $log ? ec_email::push_context( 'giftcard', (int) $this->order_id ) : null;
				wp_mail( $to, $subject, $message, implode("\r\n", $headers) );
				if ( $log ) {
					ec_email::pop_context( $outer );
				}
			}
			if ( '' !== $admin_to ) {
				$outer = $log ? ec_email::push_context( 'giftcard_store', 0 ) : null;
				wp_mail( $admin_to, $subject, $message, implode("\r\n", $headers) );
				if ( $log ) {
					ec_email::pop_context( $outer );
				}
			}

		}else if( $email_send_method == "0" ){
			$mailer = new wpeasycart_mailer( );
			if ( '' !== $to ) {
				$outer = $log ? ec_email::push_context( 'giftcard', (int) $this->order_id ) : null;
				$mailer->send_order_email( $to, $subject, $message );
				if ( $log ) {
					ec_email::pop_context( $outer );
				}
			}
			if ( '' !== $admin_to ) {
				$outer = $log ? ec_email::push_context( 'giftcard_store', 0 ) : null;
				$mailer->send_order_email( $admin_to, $subject, $message );
				if ( $log ) {
					ec_email::pop_context( $outer );
				}
			}

		}else if ( '' !== $to || '' !== $admin_to ) {
			do_action( 'wpeasycart_custom_gift_card_email', stripslashes( get_option( 'ec_option_order_from_email' ) ), $to, $admin_to, $subject, $message );

		}
		
	}
	
	public function clear_session(){
		
		$GLOBALS['ec_cart_data']->checkout_session_complete( );
		
	}
	
	public function process_deconetwork_complete( $is_paid ){
		
		if( get_option( 'ec_option_deconetwork_url' ) != "" ){
		
			// Get DecoNetwork IDs if available
			$deconetwork_ids = array( );
			for( $i=0; $i<count( $this->cart->cart ); $i++ ){
				if( $this->cart->cart[$i]->is_deconetwork ){
					$deconetwork_ids[] = $this->cart->cart[$i]->deconetwork_id;
				}
			}
			
			// If IDS, Process the DecoNetwork Order as Paid
			if( count( $deconetwork_ids ) > 0 ){
				
				$data = array( 	"ids" 						=> implode( ",", $deconetwork_ids ),
								"auth" 						=> get_option( 'ec_option_deconetwork_password' ),
								"mark_as_paid"				=> $is_paid,
								"invoice_id"				=> $this->order_id,
								"oid"						=> $GLOBALS['ec_cart_data']->ec_cart_id,
								"billing_country_code"		=> $this->user->billing->country,
								"billing_email"				=> $this->user->email,
								"billing_firstname"			=> $this->user->billing->first_name,
								"billing_lastname"			=> $this->user->billing->last_name,
								"billing_zip"				=> $this->user->billing->zip,
								"billing_ph_number"			=> $this->user->billing->phone,
								"billing_street"			=> $this->user->billing->address_line_1 . " " . $this->user->billing->address_line_2,
								"billing_city"				=> $this->user->billing->city,
								"billing_state"				=> $this->user->billing->state,
								"separate_shipping_details"	=> "true",
								"shipping_country_code"		=> $this->user->shipping->country,
								"shipping_firstname"		=> $this->user->shipping->first_name,
								"shipping_lastname"			=> $this->user->shipping->last_name,
								"shipping_zip"				=> $this->user->shipping->zip,
								"shipping_ph_number"		=> $this->user->shipping->phone,
								"shipping_street"			=> $this->user->shipping->address_line_1 . " " . $this->user->shipping->address_line_2,
								"shipping_city"				=> $this->user->shipping->city,
								"shipping_state"			=> $this->user->shipping->state );
				
				$url = "https://" . get_option( 'ec_option_deconetwork_url' ) . "/external/commit_order?" . http_build_query( $data );
				
				$request = new WP_Http;
                $response = $request->request( 
                    $url, 
                    array( 
                        'method' => 'GET',
                        'timeout' => 30
                    )
                );
                if( is_wp_error( $response ) ){
                    $error_message = $response->get_error_message( );
                    $this->mysqli->insert_response( $this->order_id, 0, "DecoNetwork ERROR", $error_message );
                    return;
                }
				
                $this->mysqli->insert_response( $this->order_id, 0, "DecoNetwork Response", print_r( $response, true ) );
				
			}
			
		}
	
	}
	
	public function get_shipping_method_name( ){
		return $this->mysqli->get_shipping_method_name( $GLOBALS['ec_cart_data']->cart_data->shipping_method );
	}
	
}

?>