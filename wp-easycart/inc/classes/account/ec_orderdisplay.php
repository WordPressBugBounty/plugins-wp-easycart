<?php
class ec_orderdisplay {
	protected $mysqli;							// ec_db structure

	public $order_id; 							// INT
	public $order_date; 						// TIMESTAMP
	public $orderstatus_id;						// INT
	public $order_status; 						// VARCHAR 50
	public $order_weight; 						// FLOAT 9,2
	public $is_approved;						// BOOL

	public $sub_total;							// FLOAT 15,3
	public $tip_total;							// FLOAT 15,3
	public $shipping_total; 					// FLOAT 15,3
	public $tax_total; 							// FLOAT 15,3
	public $duty_total; 						// FLOAT 15,3
	public $vat_total; 							// FLOAT 15,3
	public $vat_rate; 							// FLOAT 15,3
	public $discount_total;						// FLOAT 15,3
	public $offer_discount_total;				// FLOAT 15,3
	public $applied_offers;						// LONGTEXT JSON
	public $grand_total;  						// FLOAT 15,3
	public $refund_total;						// FLOAT 15,3

	public $gst_total;							// FLOAT 15,3
	public $gst_rate;							// FLOAT 15,3
	public $pst_total;							// FLOAT 15,3
	public $pst_rate;							// FLOAT 15,3
	public $hst_total;							// FLOAT 15,3
	public $hst_rate;							// FLOAT 15,3
	public $order_fees;							// Array ( ec_order_fee )

	public $promo_code;  						// VARCHAR 255
	public $promo_code_message;  				// VARCHAR 1024
	public $giftcard_id;  						// VARCHAR 20

	public $use_expedited_shipping; 			// BOOL
	public $shipping_method;  					// VARCHAR 255
	public $shipping_carrier;  					// VARCHAR 64
	public $tracking_number;  					// VARCHAR 100
	/**
	 * The packages the customer sees ( wp_easycart_shipments::customer_rows() ), read once.
	 *
	 * @since 6.0.2
	 * @var array|null
	 */
	private $customer_package_rows = null;

	public $user_email;  						// VARCHAR 255
	public $email_other;  						// VARCHAR 255
	public $user_level;  						// VARCHAR 255

	public $billing_first_name;  				// VARCHAR 255
	public $billing_last_name;  				// VARCHAR 255
	public $billing_company_name;  				// VARCHAR 255
	public $billing_address_line_1; 			// VARCHAR 255 
	public $billing_address_line_2;  			// VARCHAR 255
	public $billing_city;  						// VARCHAR 255
	public $billing_state;  					// VARCHAR 255
	public $billing_zip;  						// VARCHAR 32
	public $billing_country; 					// VARCHAR 255 
	public $billing_country_name; 				// VARCHAR 255 
	public $billing_phone;  					// VARCHAR 32

	public $vat_registration_number;  			// VARCHAR 255

	public $shipping_first_name;  				// VARCHAR 255
	public $shipping_last_name;  				// VARCHAR 255
	public $shipping_company_name;  			// VARCHAR 255
	public $shipping_address_line_1;  			// VARCHAR 255
	public $shipping_address_line_2;  			// VARCHAR 255
	public $shipping_city;  					// VARCHAR 255
	public $shipping_state;  					// VARCHAR 255
	public $shipping_zip;  						// VARCHAR 32
	public $shipping_country;  					// VARCHAR 255
	public $shipping_country_name;  			// VARCHAR 255
	public $shipping_phone;  					// VARCHAR 32

	public $order_customer_notes;				// BLOB
	public $card_holder_name;					// VARCHAR 255
	public $creditcard_digits;					// VARCHAR 4

	public $fraktjakt_order_id;					// VARCHAR
	public $fraktjakt_shipment_id;				// VARCHAR
	public $subscription_id;					// VARCHAR

	public $success_page_shown;

	public $user;								// ec_user class
	public $guest_key;							// Guest Key for order

	public $payment_method; 					// VARCHAR 64

	public $paypal_email_id; 					// VARCHAR 255
	public $paypal_payer_id;					// VARCHAR 255

	public $orderdetails = array();				// array of ec_orderdetail items
	public $cart;

	private $account_page;
	private $cart_page;
	private $store_page;
	private $permalink_divider;

	private $currency;

	private $membership_page;

	public $includes_preorder_items;
	public $includes_restaurant_type;
	public $pickup_date;
	public $pickup_asap;
	public $pickup_time;
	public $location_id;

	function __construct( $order_row, $is_order_details = false, $is_admin = false ) {
		$this->mysqli = new ec_db( );
		$this->user =& $GLOBALS['ec_user'];
		if ( $order_row ) {
			$this->order_id = $order_row->order_id; 
			$this->order_date = $order_row->order_date; 
			$this->orderstatus_id = $order_row->orderstatus_id;
			$this->order_status = $order_row->order_status; 
			$this->order_weight = $order_row->order_weight; 
			$this->is_approved = $order_row->is_approved;

			$this->sub_total = $order_row->sub_total;
			$this->tip_total = ( isset( $order_row->tip_total ) ) ? $order_row->tip_total : 0.000;
			$this->shipping_total = $order_row->shipping_total;
			$this->tax_total = $order_row->tax_total;
			$this->discount_total = $order_row->discount_total;
			$this->offer_discount_total = ( isset( $order_row->offer_discount_total ) ) ? (float) $order_row->offer_discount_total : 0;
			$this->applied_offers = ( isset( $order_row->applied_offers ) ) ? $order_row->applied_offers : '';
			$this->duty_total = $order_row->duty_total;
			$this->vat_total = $order_row->vat_total;
			$this->vat_rate = $order_row->vat_rate;
			$this->order_fees = $this->mysqli->get_order_fees( $order_row->order_id );
			$this->grand_total = apply_filters( 'wp_easycart_order_display_grand_total', $order_row->grand_total, $order_row );
			$this->refund_total = $order_row->refund_total; 

			$this->gst_total = $order_row->gst_total;
			$this->gst_rate = $order_row->gst_rate;
			if( floor( $this->gst_rate ) == $this->gst_rate )
				$this->gst_rate = number_format( $this->gst_rate, 0, '', '' );
			$this->pst_total = $order_row->pst_total;
			$this->pst_rate = $order_row->pst_rate;
			if( floor( $this->pst_rate ) == $this->pst_rate )
				$this->pst_rate = number_format( $this->pst_rate, 0, '', '' );
			$this->hst_total = $order_row->hst_total;
			$this->hst_rate = $order_row->hst_rate;
			if( floor( $this->hst_rate ) == $this->hst_rate )
				$this->hst_rate = number_format( $this->hst_rate, 0, '', '' );

			$this->promo_code = $order_row->promo_code;
			$this->promo_code_message = $order_row->promo_code_message;
			$this->giftcard_id = $order_row->giftcard_id; 

			$this->use_expedited_shipping = $order_row->use_expedited_shipping;
			$this->shipping_method = wp_easycart_language( )->convert_text( $order_row->shipping_method );
			$this->shipping_carrier = $order_row->shipping_carrier; 
			$this->tracking_number = $order_row->tracking_number; 

			$this->user_email = $order_row->user_email;
			$this->email_other = $order_row->email_other;
			$this->user_level = $order_row->user_level; 

			$this->billing_first_name = $order_row->billing_first_name;
			$this->billing_last_name = $order_row->billing_last_name;
			$this->billing_company_name = $order_row->billing_company_name;
			$this->billing_address_line_1 = $order_row->billing_address_line_1;
			$this->billing_address_line_2 = $order_row->billing_address_line_2;
			$this->billing_city = $order_row->billing_city;
			$this->billing_state = $order_row->billing_state; 
			$this->billing_zip = $order_row->billing_zip; 
			$this->billing_country = $order_row->billing_country; 
			$this->billing_country_name = $order_row->billing_country_name; 
			$this->billing_phone = $order_row->billing_phone;

			$this->vat_registration_number = $order_row->vat_registration_number;

			$this->shipping_first_name = $order_row->shipping_first_name;
			$this->shipping_last_name = $order_row->shipping_last_name;
			$this->shipping_company_name = $order_row->shipping_company_name;
			$this->shipping_address_line_1 = $order_row->shipping_address_line_1;
			$this->shipping_address_line_2 = $order_row->shipping_address_line_2;
			$this->shipping_city = $order_row->shipping_city;
			$this->shipping_state = $order_row->shipping_state; 
			$this->shipping_zip = $order_row->shipping_zip; 
			$this->shipping_country = $order_row->shipping_country; 
			$this->shipping_country_name = $order_row->shipping_country_name; 
			$this->shipping_phone = $order_row->shipping_phone; 

			$this->order_customer_notes = $order_row->order_customer_notes;
			$this->card_holder_name = $order_row->card_holder_name;
			$this->creditcard_digits = $order_row->creditcard_digits;

			$this->guest_key = $order_row->guest_key;

			$this->fraktjakt_order_id = $order_row->fraktjakt_order_id;
			$this->fraktjakt_shipment_id = $order_row->fraktjakt_shipment_id;
			$this->subscription_id = $order_row->subscription_id;

			$this->payment_method = $order_row->payment_method; 

			$this->paypal_email_id = $order_row->paypal_email_id; 
			$this->paypal_payer_id = $order_row->paypal_payer_id;

			$this->includes_preorder_items = ( isset( $order_row->includes_preorder_items ) ) ? $order_row->includes_preorder_items : false;
			$this->includes_restaurant_type = ( isset( $order_row->includes_restaurant_type ) ) ? $order_row->includes_restaurant_type : false;
			$this->pickup_date = ( isset( $order_row->pickup_date ) ) ? $order_row->pickup_date : '';
			$this->pickup_asap = ( isset( $order_row->pickup_asap ) ) ? $order_row->pickup_asap : '';
			$this->pickup_time = ( isset( $order_row->pickup_time ) ) ? $order_row->pickup_time : '';
			$this->location_id = ( isset( $order_row->location_id ) ) ? $order_row->location_id : 0;

			$this->success_page_shown = $order_row->success_page_shown;
		}// end check for valid order row

		if ( $this->subscription_id != 0 ){
			$this->membership_page = $this->mysqli->get_membership_link( $this->subscription_id );
		} else {
			$this->membership_page = "";
		}

		if ( $is_order_details ) {
			$this->cart =(object) array('cart' => array( ) );
			if( $is_admin ){
				$db_admin = new ec_db_admin( );
				$result = $db_admin->get_order_details_admin( $this->order_id );
			}else if( isset( $_GET['ec_guest_key'] ) ){
				$result = $this->mysqli->get_guest_order_details( $this->order_id, sanitize_text_field( $_GET['ec_guest_key'] ) );
			}else if( $GLOBALS['ec_cart_data']->cart_data->is_guest != "" && $GLOBALS['ec_cart_data']->cart_data->is_guest && $GLOBALS['ec_cart_data']->cart_data->guest_key != "" ){
				$result = $this->mysqli->get_guest_order_details( $this->order_id, $GLOBALS['ec_cart_data']->cart_data->guest_key );
			}else{
				$result = $this->mysqli->get_order_details( $this->order_id, $GLOBALS['ec_cart_data']->cart_data->user_id );
			}

			foreach( $result as $item ){
				array_push(
					$this->cart->cart,
					(object) array(
						'orderdetail_id' => $item->orderdetail_id,
						'product_id' => $item->product_id,
						'unit_price' => $item->unit_price,
						'unit_discount_promotion' => $item->unit_discount_promotion,
						'unit_discount_coupon' => $item->unit_discount_coupon,
						'total_price' => $item->total_price,
						'total_discount_promotion' => $item->total_discount_promotion,
						'total_discount_coupon' => $item->total_discount_coupon,
						'title' => $item->title,
						'quantity' => $item->quantity,
						'image1' => $item->image1,
						'optionitem1_name'=>$item->optionitem_name_1,
						'optionitem2_name'=>$item->optionitem_name_2,
						'optionitem3_name'=>$item->optionitem_name_3,
						'optionitem4_name'=>$item->optionitem_name_4,
						'optionitem5_name'=>$item->optionitem_name_5,
						'optionitem1_label' => $item->optionitem_label_1,
						'optionitem2_label' => $item->optionitem_label_2,
						'optionitem3_label' => $item->optionitem_label_3,
						'optionitem4_label' => $item->optionitem_label_4,
						'optionitem5_label' => $item->optionitem_label_5,
						'optionitem1_price' => $item->optionitem_price_1,
						'optionitem2_price' => $item->optionitem_price_2,
						'optionitem3_price' => $item->optionitem_price_3,
						'optionitem4_price' => $item->optionitem_price_4,
						'optionitem5_price' => $item->optionitem_price_5,
						'use_advanced_optionset' => $item->use_advanced_optionset,
						'use_both_option_types' => $item->use_both_option_types,
						'is_download' => $item->is_download,
						'model_number' => $item->model_number,
						'giftcard_id' => $item->giftcard_id,
						'gift_card_message' => $item->gift_card_message,
						'gift_card_from_name' => $item->gift_card_from_name,
						'gift_card_to_name' => $item->gift_card_to_name,
						'is_shippable' => $item->is_shippable,
						'is_giftcard' => $item->is_giftcard,
						'gift_card_email' => $item->gift_card_email,
						'include_code' => $item->include_code,
						'is_deconetwork' => $item->is_deconetwork,
						'image1_optionitem' => $item->image1,
						'orderdetails_model_number' => $item->model_number,
						'manufacturer_name' => $item->manufacturer_name,
					)
				);
				array_push( $this->orderdetails, new ec_orderdetail( $item ) );
			}
		}

		$accountpageid = apply_filters( 'wp_easycart_account_page_id', get_option( 'ec_option_accountpage' ) );
		$cartpageid = get_option('ec_option_cartpage');
		$storepageid = get_option('ec_option_storepage');

		if( function_exists( 'icl_object_id' ) ){
			$accountpageid = icl_object_id( $accountpageid, 'page', true, ICL_LANGUAGE_CODE );
			$cartpageid = icl_object_id( $cartpageid, 'page', true, ICL_LANGUAGE_CODE );
			$storepageid = icl_object_id( $storepageid, 'page', true, ICL_LANGUAGE_CODE );
		}

		$this->account_page = get_permalink( $accountpageid );
		$this->cart_page = get_permalink( $cartpageid );
		$this->store_page = get_permalink( $storepageid );

		if( class_exists( "WordPressHTTPS" ) && isset( $_SERVER['HTTPS'] ) ){
			$https_class = new WordPressHTTPS( );
			$this->account_page = $https_class->makeUrlHttps( $this->account_page );
			$this->cart_page = $https_class->makeUrlHttps( $this->cart_page );
			$this->store_page = $https_class->makeUrlHttps( $this->store_page );
		}

		if( substr_count( $this->account_page, '?' ) )				$this->permalink_divider = "&";
		else														$this->permalink_divider = "?";

		$this->currency = new ec_currency( );
	}

	public function display_order_detail_product_list( ){

		for( $i=0; $i < count( $this->orderdetails ); $i++ ){
			$order_item = $this->orderdetails[$i];
			if( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_account_order_details_item_display.php' ) )	
				include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_account_order_details_item_display.php' );
			else
				include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_account_order_details_item_display.php' );	
		}
	}

	public function display_sub_total() {
		$sub_total = $this->sub_total;
		if ( get_option( 'ec_option_show_coupon_discount_total' ) ) {
			foreach ( $this->orderdetails as $line_item ) {
				if ( get_option( 'ec_option_show_coupon_discount_total' ) && $line_item->total_discount_coupon > 0 ) {
					$sub_total -= $line_item->total_discount_coupon;
				}
			}
		}
		$sub_total = apply_filters( 'wp_easycart_order_display_sub_total', $sub_total, $this->sub_total, $this->orderdetails );
		echo esc_attr( $this->currency->get_currency_display( $sub_total ) );
	}

	public function display_tip_total( ){
		echo esc_attr( $this->currency->get_currency_display( $this->tip_total ) );
	}

	public function display_shipping_total( ){
		echo esc_attr( $this->currency->get_currency_display( $this->shipping_total ) );
	}

	public function display_tax_total( ){
		echo esc_attr( $this->currency->get_currency_display( $this->tax_total ) );
	}

	public function has_duty( ){
		if( $this->duty_total != "0" )
			return true;
		else
			return false;	
	}

	public function display_duty_total( ){
		echo esc_attr( $this->currency->get_currency_display( $this->duty_total ) );
	}

	public function has_vat( ){
		if( $this->vat_total != 0 )
			return true;
		else
			return false;	
	}

	public function display_vat_total( ){
		echo esc_attr( $this->currency->get_currency_display( $this->vat_total ) );
	}

	public function display_gst_total( ){
		echo esc_attr( $this->currency->get_currency_display( $this->gst_total ) );
	}

	public function display_pst_total( ){
		echo esc_attr( $this->currency->get_currency_display( $this->pst_total ) );
	}

	public function display_hst_total( ){
		echo esc_attr( $this->currency->get_currency_display( $this->hst_total ) );
	}

	public function has_refund( ){
		if( $this->refund_total != 0 )
			return true;
		else
			return false;	
	}

	public function display_refund_total( ){
		echo esc_attr( $this->currency->get_currency_display( $this->refund_total ) );
	}

	public function display_discount_total( ){
		echo esc_attr( $this->currency->get_currency_display( $this->discount_total ) );
	}

	public function display_grand_total( ){
		echo esc_attr( $this->currency->get_currency_display( $this->grand_total ) );
	}

	public function display_order_date( $date_format = "" ){
		$offset = get_option('gmt_offset');
		if( $date_format == "" ){
			$date_format = get_option('date_format');
			$time_format = get_option('time_format');
			echo esc_attr( date( $date_format . " " . $time_format, strtotime( $this->order_date ) + ( $offset * 60 * 60 ) ) );
		}else{
			echo esc_attr( date( $date_format, strtotime( $this->order_date ) + ( $offset * 60 * 60 ) ) );
		}
	}

	public function display_order_id( ){
		echo esc_attr( $this->order_id ); 
	}

	public function display_order_status( ){
		if( wp_easycart_language( )->get_text( 'account_order_details', 'order_status_' . str_replace( " ", "_", strtolower( $this->order_status ) ) ) ){
			echo wp_easycart_language( )->get_text( 'account_order_details', 'order_status_' . esc_attr( str_replace( " ", "_", strtolower( $this->order_status ) ) ) );	
		}else{
			echo esc_attr( $this->order_status );
		}
	}

	public function display_order_shipping_method( ){
		echo esc_attr( $this->shipping_method );

		if( $this->fraktjakt_shipment_id ){
			if( !class_exists( "ec_fraktjakt" ) ){
				if( file_exists( EC_PLUGIN_DIRECTORY . '-pro/inc/classes/shipping/ec_fraktjakt.php' ) ){
					include( EC_PLUGIN_DIRECTORY . '-pro/inc/classes/shipping/ec_fraktjakt.php' );
				}else{
					return;
				}
			}
			$fraktjakt = new ec_fraktjakt( );
			$status = $fraktjakt->get_shipping_status( $this->fraktjakt_shipment_id );

			if( $status != "" ){
				echo "<br><b>Leveransstatus:</b> " . esc_attr( $status );
			}
		}
	}

	public function display_order_email( ){
		echo esc_attr( $this->user_email );
	}

	public function display_order_email_other( ){
		echo esc_attr( $this->email_other );
	}

	public function display_order_promocode( ){
		$output_value = $this->promo_code;
		if ( get_option( 'ec_option_show_coupon_message' ) && '' != $this->promo_code_message ) {
			$output_value = $this->promo_code_message;
		}
		echo esc_attr( $output_value );
	}

	public function display_order_giftcard( ){
		echo esc_attr( $this->giftcard_id );	
	}

	public function has_tracking_number( ){
		if( $this->tracking_number )
			return true;
		/* 6.0.2: a fulfillment partner's package still being made is shown too ( "Being made" ), before any tracking exists. */
		foreach ( $this->customer_package_rows( true ) as $package ) {
			if ( '' !== (string) $package['partner'] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The packages the customer sees ( wp_easycart_shipments::customer_rows(): every package with a tracking number, then
	 * each fulfillment partner's package still being made ).
	 *
	 * @since 6.0.2
	 * @param bool $partner_only Only when the order has a fulfillment partner's package ( one quick look for other orders ).
	 * @return array
	 */
	private function customer_package_rows( $partner_only = false ) {
		global $wpdb;
		if ( null !== $this->customer_package_rows ) {
			return $this->customer_package_rows;
		}
		if ( ! class_exists( 'wp_easycart_shipments' ) || ! wp_easycart_shipments::ready() ) {
			return array();
		}
		if ( $partner_only && ! $wpdb->get_var( $wpdb->prepare( "SELECT shipment_id FROM ec_order_shipment WHERE order_id = %d AND is_return = 0 AND provider <> '' AND status <> 'voided' LIMIT 1", (int) $this->order_id ) ) ) {
			return array();
		}
		$this->customer_package_rows = method_exists( 'wp_easycart_shipments', 'customer_rows' ) ? wp_easycart_shipments::customer_rows( (int) $this->order_id ) : wp_easycart_shipments::tracking_rows( (int) $this->order_id );
		return $this->customer_package_rows;
	}

	public function display_order_tracking_number( ){
		// 6.0.2: every package with its own link and delivery status, and a fulfillment partner's package still being made
		// ( shown even before any package has a tracking number ).
		$packages = $this->customer_package_rows();
		if ( $packages ) {
			$lines = array();
			foreach ( $packages as $i => $package ) {
				$line = '';
				if ( count( $packages ) > 1 && class_exists( 'wp_easycart_documents' ) ) {
					/* 6.0.2: str_replace(), not sprintf(): the phrase is editable, and a stray % or a second %d would be a fatal error. */
					/* translators: %d: package number ( 1, 2, 3 … ). */
					$line .= esc_html( str_replace( array( '%1$d', '%d' ), (string) ( $i + 1 ), wp_strip_all_tags( wp_easycart_documents::text( 'shipment_package', __( 'Package %d', 'wp-easycart' ) ) ) ) ) . ': ';
				}
				$line .= '' !== $package['carrier'] ? esc_html( $package['carrier'] ) . ' ' : '';
				$line .= '' !== $package['url'] ? '<a href="' . esc_url( $package['url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $package['tracking'] ) . '</a>' : esc_html( $package['tracking'] );
				$words = wp_easycart_shipments::customer_status( $package );
				if ( '' !== $words && '' === (string) $package['tracking'] ) {
					$line .= '<span class="ec_account_order_tracking_status">' . esc_html( $words ) . '</span>'; /* 6.0.2: no tracking yet ( being made ) */
				} elseif ( '' !== $words ) {
					$line .= ' <span class="ec_account_order_tracking_status">( ' . esc_html( $words ) . ' )</span>';
				}
				$lines[] = $line;
			}
			echo implode( '<br />', $lines ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
			return;
		}
		$url = ( class_exists( 'wp_easycart_email_design' ) && method_exists( 'wp_easycart_email_design', 'tracking_url' ) ) ? wp_easycart_email_design::tracking_url( (string) $this->shipping_carrier, (string) $this->tracking_number ) : '';
		if ( '' !== $url ) {
			echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $this->tracking_number ) . '</a>';
		} else if ( 'fedex' == strtolower( $this->shipping_carrier ) ) {
			echo '<a href="https://www.fedex.com/fedextrack/summary?trknbr=' . esc_attr( $this->tracking_number ) . '" target="_blank">' . esc_attr( $this->tracking_number ) . '</a>';
		} else if ( 'usps' == strtolower( $this->shipping_carrier ) ) {
			echo '<a href="https://tools.usps.com/go/TrackConfirmAction?tRef=fullpage&tLc=3&text28777=&tLabels=' . esc_attr( $this->tracking_number ) . '" target="_blank">' . esc_attr( $this->tracking_number ) . '</a>';
		} else if ( 'ups' == strtolower( $this->shipping_carrier ) ) {
			echo '<a href="https://www.ups.com/track?loc=en_US&tracknum=' . esc_attr( $this->tracking_number ) . '" target="_blank">' . esc_attr( $this->tracking_number ) . '</a>';
		} else {
			echo esc_attr( $this->tracking_number );
		}
	}

	public function display_order_billing_first_name( ){
		if ( isset( $this->billing_first_name ) && '' != $this->billing_first_name ) {
			echo esc_attr( htmlspecialchars( stripslashes( $this->billing_first_name ), ENT_QUOTES ) );
		}
	}

	public function display_order_billing_last_name( ){
		if ( isset( $this->billing_last_name ) && '' != $this->billing_last_name ) {
			echo esc_attr( htmlspecialchars( stripslashes( $this->billing_last_name ), ENT_QUOTES ) );
		}
	}

	public function display_order_billing_address_line_1( ){
		if ( isset( $this->billing_address_line_1 ) && '' != $this->billing_address_line_1 ) {
			echo esc_attr( htmlspecialchars( stripslashes( $this->billing_address_line_1 ), ENT_QUOTES ) );
		}
	}

	public function display_order_billing_city( ){
		if ( isset( $this->billing_city ) && '' != $this->billing_city ) {
			echo esc_attr( htmlspecialchars( stripslashes( $this->billing_city ), ENT_QUOTES ) );
		}
	}

	public function display_order_billing_state( ){
		if ( isset( $this->billing_state ) && '' != $this->billing_state ) {
			echo esc_attr( htmlspecialchars( stripslashes( $this->billing_state ), ENT_QUOTES ) );
		}
	}

	public function display_order_billing_zip( ){
		if ( isset( $this->billing_zip ) && '' != $this->billing_zip ) {
			echo esc_attr( htmlspecialchars( stripslashes( $this->billing_zip ), ENT_QUOTES ) );
		}
	}

	public function display_order_billing_country( ){
		if ( isset( $this->billing_country_name ) && '' != $this->billing_country_name ) {
			echo esc_attr( htmlspecialchars( stripslashes( $this->billing_country_name ), ENT_QUOTES ) );
		}
	}

	public function display_order_billing_phone( ){
		if ( isset( $this->billing_phone ) && '' != $this->billing_phone ) {
			echo esc_attr( htmlspecialchars( stripslashes( $this->billing_phone ), ENT_QUOTES ) );
		}
	}

	public function display_order_shipping_first_name( ){
		if ( isset( $this->shipping_first_name ) && '' != $this->shipping_first_name ) {
			echo esc_attr( htmlspecialchars( stripslashes( $this->shipping_first_name ), ENT_QUOTES ) );
		}
	}

	public function display_order_shipping_last_name( ){
		if ( isset( $this->shipping_last_name ) && '' != $this->shipping_last_name ) {
			echo esc_attr( htmlspecialchars( stripslashes( $this->shipping_last_name ), ENT_QUOTES ) );
		}
	}

	public function display_order_shipping_address_line_1( ){
		if ( isset( $this->shipping_address_line_1 ) && '' != $this->shipping_address_line_1 ) {
			echo esc_attr( htmlspecialchars( stripslashes( $this->shipping_address_line_1 ), ENT_QUOTES ) );
		}
	}

	public function display_order_shipping_city( ){
		if ( isset( $this->shipping_city ) && '' != $this->shipping_city ) {
			echo esc_attr( htmlspecialchars( stripslashes( $this->shipping_city ), ENT_QUOTES ) );
		}
	}

	public function display_order_shipping_state( ){
		if ( isset( $this->shipping_state ) && '' != $this->shipping_state ) {
			echo esc_attr( htmlspecialchars( stripslashes( $this->shipping_state ), ENT_QUOTES ) );
		}
	}

	public function display_order_shipping_zip( ){
		if ( isset( $this->shipping_zip ) && '' != $this->shipping_zip ) {
			echo esc_attr( htmlspecialchars( stripslashes( $this->shipping_zip ), ENT_QUOTES ) );
		}
	}

	public function display_order_shipping_country( ){
		if ( isset( $this->shipping_country_name ) && '' != $this->shipping_country_name ) {
			echo esc_attr( htmlspecialchars( stripslashes( $this->shipping_country_name ), ENT_QUOTES ) );
		}
	}

	public function display_order_shipping_phone( ){
		if ( isset( $this->shipping_phone ) && '' != $this->shipping_phone ) {
			echo esc_attr( htmlspecialchars( stripslashes( $this->shipping_phone ), ENT_QUOTES ) );
		}
	}

	public function display_payment_method( ){
		if( $this->payment_method == "manual_bill" ){
			echo wp_easycart_language( )->get_text( "account_order_details", "account_order_details_payment_method_manual" );
		}else{
			echo esc_attr( ucwords( $this->payment_method ) );
		}
	}

	public function display_order_link( $link_text ){
		echo "<a href=\"" . esc_url( wpeasycart_links()->get_account_page( 'order_details', array( 'order_id' => (int) $this->order_id ) ) ) . "\">" . esc_attr( $link_text ) . "</a>";
	}

	/**
	 * Clean a list of email addresses typed into the admin send dialog ( comma, semicolon or space separated ).
	 *
	 * @since 6.0.0
	 * @param string|array $raw Addresses.
	 * @return array Valid, unique addresses ( at most 10 ).
	 */
	public static function clean_email_list( $raw ) {
		$parts = is_array( $raw ) ? $raw : preg_split( '/[\s,;]+/', (string) $raw );
		$out   = array();
		foreach ( (array) $parts as $part ) {
			$email = sanitize_email( trim( (string) $part ) );
			if ( '' !== $email && is_email( $email ) && ! in_array( strtolower( $email ), array_map( 'strtolower', $out ), true ) ) {
				$out[] = $email;
			}
		}
		return array_slice( $out, 0, 10 );
	}

	/**
	 * Send one customer email to the addresses chosen in the admin send dialog, with the store's send method.
	 * wp_mail gets real Cc / Bcc headers; the built-in mailer and custom senders get one copy per address.
	 *
	 * @since 6.0.0
	 * @param array  $recipients  array( 'to' => [], 'cc' => [], 'bcc' => [] ).
	 * @param string $subject     Subject.
	 * @param string $message     HTML body.
	 * @param array  $headers     Header lines ( From, Reply-To, content type ).
	 * @param array  $attachments Attachments.
	 * @return bool
	 */
	public static function send_to_recipients( $recipients, $subject, $message, $headers, $attachments = array() ) {
		$to  = isset( $recipients['to'] ) ? (array) $recipients['to'] : array();
		$cc  = isset( $recipients['cc'] ) ? (array) $recipients['cc'] : array();
		$bcc = isset( $recipients['bcc'] ) ? (array) $recipients['bcc'] : array();
		if ( ! $to ) {
			return false;
		}
		$method = apply_filters( 'wpeasycart_email_method', get_option( 'ec_option_use_wp_mail' ) );
		if ( '1' == $method ) {
			foreach ( $cc as $address ) {
				$headers[] = 'Cc: ' . $address;
			}
			foreach ( $bcc as $address ) {
				$headers[] = 'Bcc: ' . $address;
			}
			return (bool) wp_mail( implode( ',', $to ), $subject, $message, implode( "\r\n", $headers ), $attachments );
		}
		$all = array_values( array_unique( array_merge( $to, $cc, $bcc ) ) );
		if ( '0' == $method ) {
			$mailer = new wpeasycart_mailer();
			foreach ( $all as $address ) {
				$mailer->send_order_email( $address, $subject, $message, $attachments );
			}
		} else {
			foreach ( $all as $address ) {
				do_action( 'wpeasycart_custom_order_email', stripslashes( get_option( 'ec_option_order_from_email' ) ), $address, '', $subject, $message, $attachments );
			}
		}
		return true;
	}

	/**
	 * The receipt email's HTML, without sending it ( the send, Settings › Documents previews, the send dialog ).
	 *
	 * @since 6.0.1
	 * @param bool       $is_admin        The store copy.
	 * @param array|null $document_fields Resolved receipt profile ( wp_easycart_documents::resolve() ); null = the default profile.
	 * @return string
	 */
	public function render_email_receipt( $is_admin = false, $document_fields = null ) {
		extract( $this->receipt_template_vars() ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- the receipt template reads these as plain variables.
		$is_admin = (bool) $is_admin;
		if ( null === $document_fields && class_exists( 'wp_easycart_documents' ) ) {
			$document_fields = wp_easycart_documents::resolve( 'receipt', '', array(), (int) $this->order_id ); /* 6.0.2: a document rule or the gift profile may pick it */
		}
		ob_start();
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_email_receipt.php' ) ) {
			include EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_email_receipt.php';
		} else {
			include EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_email_receipt.php';
		}
		return (string) ob_get_clean();
	}

	/**
	 * The formatted amounts and links the receipt template has always been given.
	 *
	 * @since 6.0.1 ( moved out of send_email_receipt() )
	 * @return array
	 */
	private function receipt_template_vars() {
		$tax_struct = new ec_tax( 0,0,0, "", "");
		$total = $GLOBALS['currency']->get_currency_display( $this->grand_total );
		$subtotal = $GLOBALS['currency']->get_currency_display( $this->sub_total );
		$tip = $GLOBALS['currency']->get_currency_display( $this->tip_total );
		$tax = $GLOBALS['currency']->get_currency_display( $this->tax_total );
		$has_duty = ( $this->duty_total > 0 );
		$duty = $GLOBALS['currency']->get_currency_display( $this->duty_total );
		$vat = $GLOBALS['currency']->get_currency_display( $this->vat_total );
		$shipping = $GLOBALS['currency']->get_currency_display( $this->shipping_total );
		if ( $this->vat_rate > 0 ) {
			$vat_rate_formatted = $vat_rate = $this->vat_rate;
		} else if ( ( $this->grand_total - $this->vat_total ) > 0 ) {
			$vat_rate_formatted = $vat_rate = ( $this->vat_total / ( $this->grand_total - $this->vat_total ) ) * 100;
		} else {
			$vat_rate_formatted = $vat_rate = 0;
		}
		if ( round( $vat_rate_formatted, 0 ) == $vat_rate ) {
			$vat_rate_formatted = number_format( round( $vat_rate_formatted, 0 ), 0, '', '' );
		} else if ( round( $vat_rate_formatted, 1 ) == $vat_rate ) {
			$vat_rate_formatted = number_format( $vat_rate_formatted, 1, '.', '' );
		} else if ( round( $vat_rate_formatted, 2 ) == $vat_rate ) {
			$vat_rate_formatted = number_format( $vat_rate_formatted, 2, '.', '' );
		} else if ( round( $vat_rate_formatted, 3 ) == $vat_rate ) {
			$vat_rate_formatted = number_format( $vat_rate_formatted, 3, '.', '' );
		}
		$vat_rate = $vat_rate_formatted;
		$storepageid = get_option( 'ec_option_storepage' );
		if ( function_exists( 'icl_object_id' ) ) {
			$storepageid = icl_object_id( $storepageid, 'page', true, ICL_LANGUAGE_CODE );
		}
		$store_page = get_permalink( $storepageid );
		if ( class_exists( "WordPressHTTPS" ) && isset( $_SERVER['HTTPS'] ) ) {
			$https_class = new WordPressHTTPS();
			$store_page = $https_class->makeUrlHttps( $store_page );
		}
		return array(
			'tax_struct'        => $tax_struct,
			'total'             => $total,
			'subtotal'          => $subtotal,
			'tip'               => $tip,
			'tax'               => $tax,
			'has_duty'          => $has_duty,
			'duty'              => $duty,
			'vat'               => $vat,
			'shipping'          => $shipping,
			'vat_rate'          => $vat_rate,
			'gst'               => $this->gst_total,
			'gst_rate'          => $this->gst_rate,
			'pst'               => $this->pst_total,
			'pst_rate'          => $this->pst_rate,
			'hst'               => $this->hst_total,
			'hst_rate'          => $this->hst_rate,
			'discount'          => $GLOBALS['currency']->get_currency_display( $this->discount_total ),
			'refund'            => $GLOBALS['currency']->get_currency_display( $this->refund_total ),
			'email_logo_url'    => get_option( 'ec_option_email_logo' ),
			'store_page'        => $store_page,
			'permalink_divider' => substr_count( (string) $store_page, '?' ) ? '&' : '?',
		);
	}

	/**
	 * @since 6.0.0 $recipients: send the customer receipt only, to these addresses ( admin resend dialog ).
	 * @since 6.0.1 $args: document_fields ( a resolved receipt profile for this send ), plus anything the send dialog
	 *              passes on to the attachment filters ( documents, profiles, chosen items ).
	 * @param bool       $admin_only Only the store copy.
	 * @param array|null $recipients array( 'to' => [], 'cc' => [], 'bcc' => [] ).
	 * @param array      $args       Per-send choices.
	 */
	public function send_email_receipt( $admin_only = false, $recipients = null, $args = array() ){
		$args            = is_array( $args ) ? $args : array();
		$document_fields = ( isset( $args['document_fields'] ) && is_array( $args['document_fields'] ) ) ? $args['document_fields'] : null;

		$headers   = array();
		$headers[] = "MIME-Version: 1.0";
		$headers[] = "Content-Type: text/html; charset=utf-8";
		$headers[] = "From: " . stripslashes( get_option( 'ec_option_order_from_email' ) );
		$headers[] = "Reply-To: " . stripslashes( get_option( 'ec_option_order_from_email' ) );
		$headers[] = "X-Mailer: PHP/".phpversion();

		$message = $this->render_email_receipt( false, $document_fields );
		$message = apply_filters( 'wpeasycart_order_email_customer_content', $message, $this->order_id );
		$customer_title = wp_easycart_language( )->get_text( "cart_success", "cart_payment_receipt_title" ) . " " . $this->order_id;
		$customer_title= apply_filters( 'wpeasycart_order_email_customer_title', $customer_title, $this->order_id );

		$attachments = array( );
		$attachments = apply_filters( 'wpeasycart_order_email_attachments', $attachments, $this->order_id );

		/* 6.0.0: a resend from the order screen goes to the addresses the admin chose, without the store copy. */
		if ( is_array( $recipients ) && ! $admin_only ) {
			return self::send_to_recipients( $recipients, $customer_title, $message, $headers, $this->get_email_attachments( $attachments, 'customer', 'receipt', $args ) );
		}

		$admin_message = $this->render_email_receipt( true, $document_fields );
		$admin_message = apply_filters( 'wpeasycart_order_email_admin_content', $admin_message, $this->order_id );
		$admin_title = wp_easycart_language( )->get_text( "cart_success", "cart_payment_receipt_title" ) . " " . $this->order_id;
		$admin_title= apply_filters( 'wpeasycart_order_email_admin_title', $admin_title, $this->order_id );
		$admin_email = apply_filters( 'wpeasycart_order_email_admin_email', get_option( 'ec_option_bcc_email_addresses' ), $this->order_id );

		/* 6.0.0: per-recipient attachments ( PRO PDF receipt ); identical to $attachments when nothing hooks in. */
		/* 6.0.1: the store copy's files are asked for after the customer's copies went out ( the email queue records each
		   send's attachments as it happens, and both copies can share one PDF ). */
		$customer_attachments = ( $admin_only ) ? $attachments : $this->get_email_attachments( $attachments, 'customer', 'receipt', $args );

		$email_send_method = get_option( 'ec_option_use_wp_mail' );
		$email_send_method = apply_filters( 'wpeasycart_email_method', $email_send_method );

		/* 6.0.2: each copy goes only to addresses that can take mail. A copy with none is not sent ( the email log shows one
		   "not sent" row, where a blank or invalid store address used to fail and retry five times ); the log types the
		   customer's copy and the store's apart. */
		$customer_to = ( $admin_only ) ? '' : $this->email_recipients( $this->user_email, 'order_receipt', $customer_title );
		$other_to    = ( $admin_only || '' == $this->email_other ) ? '' : $this->email_recipients( $this->email_other, 'order_receipt', $customer_title );
		$admin_to    = $this->email_recipients( $admin_email, 'order_receipt_store', $admin_title, false );

		if( $email_send_method == "1" ){
			$log = $this->email_log_as( 'order_receipt' );
			if ( '' !== $customer_to ) {
				wp_mail( $customer_to, $customer_title, $message, implode("\r\n", $headers), $customer_attachments );
			}
			if ( '' !== $other_to ) {
				wp_mail( $other_to, $customer_title, $message, implode("\r\n", $headers), $customer_attachments );
			}
			$this->email_log_done( $log );
			$headers   = array();
			$headers[] = "MIME-Version: 1.0";
			$headers[] = "Content-Type: text/html; charset=utf-8";
			$headers[] = "From: " . stripslashes( get_option( 'ec_option_order_from_email' ) );
			$headers[] = "Reply-To: " . stripslashes( $this->user_email );
			$headers[] = "X-Mailer: PHP/".phpversion();
			if ( '' !== $admin_to ) {
				$admin_attachments = $this->get_email_attachments( $attachments, 'admin', 'receipt', $args );
				$log = $this->email_log_as( 'order_receipt_store' );
				wp_mail( $admin_to, $admin_title, $admin_message, implode("\r\n", $headers), $admin_attachments );
				$this->email_log_done( $log );
			}
		}else if( $email_send_method == "0" ){
			$mailer = new wpeasycart_mailer( );
			$log = $this->email_log_as( 'order_receipt' );
			if ( '' !== $customer_to ) {
				$mailer->send_order_email( $customer_to, $customer_title, $message, $customer_attachments );
			}
			if ( '' !== $other_to ) {
				$mailer->send_order_email( $other_to, $customer_title, $message, $customer_attachments );
			}
			$this->email_log_done( $log );
			if ( '' !== $admin_to ) {
				$admin_attachments = $this->get_email_attachments( $attachments, 'admin', 'receipt', $args );
				$log = $this->email_log_as( 'order_receipt_store' );
				$mailer->send_order_email( $admin_to, $admin_title, $admin_message, $admin_attachments );
				$this->email_log_done( $log );
			}
		}else{
			/* A custom sender has always been handed the customer's address, also for the store copy alone. */
			$custom_to = ( $admin_only && class_exists( 'ec_email' ) && method_exists( 'ec_email', 'valid_recipients' ) ) ? implode( ',', ec_email::valid_recipients( stripslashes( (string) $this->user_email ) ) ) : $customer_to;
			if ( '' !== $custom_to || '' !== $admin_to ) {
				do_action( 'wpeasycart_custom_order_email', stripslashes( get_option( 'ec_option_order_from_email' ) ), $custom_to, $admin_to, $customer_title, $message, $customer_attachments );
			}
			if ( '' !== $other_to ) {
				do_action( 'wpeasycart_custom_order_email', stripslashes( get_option( 'ec_option_order_from_email' ) ), $other_to, $admin_to, $customer_title, $message, $customer_attachments );
			}
		}

	}

	/**
	 * The addresses one copy of an order email goes to ( comma list ), '' when none of them can take mail. That copy is then
	 * not sent: ec_email logs one "not sent" row naming the value, instead of a failure that retries.
	 *
	 * @since 6.0.2
	 * @param string $raw       Address list as stored ( the order's email, its second email, the store notification addresses ).
	 * @param string $type      Email log type of the copy ( a store copy ends in _store ).
	 * @param string $subject   Subject, for the log row.
	 * @param bool   $log_blank Log a blank list too ( false for the store list: blank means the store wants no copies ).
	 * @return string
	 */
	private function email_recipients( $raw, $type, $subject, $log_blank = true ) {
		$raw = trim( stripslashes( (string) $raw ) );
		if ( ! class_exists( 'ec_email' ) || ! method_exists( 'ec_email', 'send_to' ) ) {
			return $raw;
		}
		return ec_email::send_to( $raw, $type, $this->email_log_order( $type ), $subject, $log_blank );
	}

	/**
	 * The order an email log row names: the store's copies name none, as before 6.0.2, so a failing copy to the store never
	 * marks the order as one whose customer missed an email.
	 *
	 * @since 6.0.2
	 * @param string $type Email log type.
	 * @return int
	 */
	private function email_log_order( $type ) {
		return ( '_store' === substr( (string) $type, -6 ) ) ? 0 : (int) $this->order_id;
	}

	/**
	 * Log the sends that follow under $type ( ec_email::push_context(); a test context the caller set wins ).
	 *
	 * @since 6.0.2
	 * @param string $type Email log type.
	 * @return array|null What email_log_done() puts back.
	 */
	private function email_log_as( $type ) {
		if ( ! class_exists( 'ec_email' ) || ! method_exists( 'ec_email', 'push_context' ) ) {
			return null;
		}
		return ec_email::push_context( $type, $this->email_log_order( $type ) );
	}

	/**
	 * Put back the email log context email_log_as() replaced.
	 *
	 * @since 6.0.2
	 * @param array|null $outer What email_log_as() returned.
	 */
	private function email_log_done( $outer ) {
		if ( class_exists( 'ec_email' ) && method_exists( 'ec_email', 'pop_context' ) ) {
			ec_email::pop_context( $outer );
		}
	}

	public function send_invoice( $admin_only = false ){

		// 6.0.2: the pay link opens an order by its id and guest key together. Orders duplicated in the admin have no key, so give this one its own before the link is built.
		if ( '' === (string) $this->guest_key && (int) $this->order_id > 0 ) {
			global $wpdb;
			$new_guest_key = '';
			for ( $i = 0; $i < 30; $i++ ) {
				$new_guest_key .= chr( 65 + wp_rand( 0, 25 ) );
			}
			$wpdb->query( $wpdb->prepare( "UPDATE ec_order SET guest_key = %s WHERE order_id = %d AND ( guest_key = '' OR guest_key IS NULL )", $new_guest_key, (int) $this->order_id ) );
			$this->guest_key = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT guest_key FROM ec_order WHERE order_id = %d', (int) $this->order_id ) );
		}

		$tax_struct = new ec_tax( 0,0,0, "", "");
		$total = $GLOBALS['currency']->get_currency_display( $this->grand_total );
		$subtotal = $GLOBALS['currency']->get_currency_display( $this->sub_total );
		$tip = $GLOBALS['currency']->get_currency_display( $this->tip_total );
		$tax = $GLOBALS['currency']->get_currency_display( $this->tax_total );
		if( $this->duty_total > 0 ){ $has_duty = true; }else{ $has_duty = false; }
		$duty = $GLOBALS['currency']->get_currency_display( $this->duty_total );
		$vat = $GLOBALS['currency']->get_currency_display( $this->vat_total );
		$shipping = $GLOBALS['currency']->get_currency_display( $this->shipping_total );
		if( $this->vat_rate > 0 )
			$vat_rate = number_format( $this->vat_rate, 0, '', '' );
		else if( ( $this->grand_total - $this->vat_total ) > 0 )
			$vat_rate = number_format( ( $this->vat_total / ( $this->grand_total - $this->vat_total ) ) * 100, 0, '', '' );
		else
			$vat_rate = number_format( 0, 0, '', '' );
		$gst = $this->gst_total;
		$gst_rate = $this->gst_rate;
		$pst = $this->pst_total;
		$pst_rate = $this->pst_rate;
		$hst = $this->hst_total;
		$hst_rate = $this->hst_rate;

		$discount = $GLOBALS['currency']->get_currency_display( $this->discount_total );

		$email_logo_url = get_option( 'ec_option_email_logo' );

		$storepageid = get_option('ec_option_storepage');
		if ( function_exists( 'icl_object_id' ) ) {
			$storepageid = icl_object_id( $storepageid, 'page', true, ICL_LANGUAGE_CODE );
		}
		$store_page = get_permalink( $storepageid );
		if ( class_exists( "WordPressHTTPS" ) && isset( $_SERVER['HTTPS'] ) ) {
			$https_class = new WordPressHTTPS();
			$store_page = $https_class->makeUrlHttps( $store_page );
		}

		if ( substr_count( $store_page, '?' ) ) {
			$permalink_divider = "&";
		} else {
			$permalink_divider = "?";
		}

		$headers = array();
		$headers[] = "MIME-Version: 1.0";
		$headers[] = "Content-Type: text/html; charset=utf-8";
		$headers[] = "From: " . stripslashes( get_option( 'ec_option_order_from_email' ) );
		$headers[] = "Reply-To: " . stripslashes( get_option( 'ec_option_order_from_email' ) );
		$headers[] = "X-Mailer: PHP/".phpversion();

		ob_start();
		$is_admin = false;
		if( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_email_invoice.php' ) )	
			include EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_email_invoice.php';	
		else
			include EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_email_invoice.php';
		$message = ob_get_clean();

		ob_start();
		$is_admin = true;
		if( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_email_invoice.php' ) )	
			include EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_email_invoice.php';	
		else
			include EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_email_invoice.php';
		$admin_message = ob_get_clean();

		$attachments = array( );
		$attachments = apply_filters( 'wpeasycart_order_email_attachments', $attachments, $this->order_id );
		/* 6.0.0: per-recipient attachments ( PRO PDF invoice ); identical to $attachments when nothing hooks in. */
		/* 6.0.1: the store copy's files are asked for just before it goes out, as in send_email_receipt(). */
		$customer_attachments = ( $admin_only ) ? $attachments : $this->get_email_attachments( $attachments, 'customer', 'invoice' );

		$email_send_method = get_option( 'ec_option_use_wp_mail' );
		$email_send_method = apply_filters( 'wpeasycart_email_method', $email_send_method );

		/* 6.0.2: only addresses that can take mail ( see send_email_receipt() ). */
		$subject     = "New Invoice Available";
		$customer_to = ( $admin_only ) ? '' : $this->email_recipients( $this->user_email, 'invoice', $subject );
		$other_to    = ( $admin_only || '' == $this->email_other ) ? '' : $this->email_recipients( $this->email_other, 'invoice', $subject );
		$admin_to    = $this->email_recipients( get_option( 'ec_option_bcc_email_addresses' ), 'invoice_store', $subject, false );

		if( $email_send_method == "1" ){
			$log = $this->email_log_as( 'invoice' );
			if ( '' !== $customer_to ) {
				wp_mail( $customer_to, $subject, $message, implode("\r\n", $headers), $customer_attachments );
			}
			if ( '' !== $other_to ) {
				wp_mail( $other_to, $subject, $message, implode("\r\n", $headers), $customer_attachments );
			}
			$this->email_log_done( $log );
			$headers = array();
			$headers[] = "MIME-Version: 1.0";
			$headers[] = "Content-Type: text/html; charset=utf-8";
			$headers[] = "From: " . stripslashes( get_option( 'ec_option_order_from_email' ) );
			$headers[] = "Reply-To: " . stripslashes( $this->user_email );
			$headers[] = "X-Mailer: PHP/".phpversion();
			if ( '' !== $admin_to ) {
				$admin_attachments = $this->get_email_attachments( $attachments, 'admin', 'invoice' );
				$log = $this->email_log_as( 'invoice_store' );
				wp_mail( $admin_to, $subject, $admin_message, implode("\r\n", $headers), $admin_attachments );
				$this->email_log_done( $log );
			}
		}else if( $email_send_method == "0" ){
			$mailer = new wpeasycart_mailer( );
			$log = $this->email_log_as( 'invoice' );
			if ( '' !== $customer_to ) {
				$mailer->send_order_email( $customer_to, $subject, $message, $customer_attachments );
			}
			if ( '' !== $other_to ) {
				$mailer->send_order_email( $other_to, $subject, $message, $customer_attachments );
			}
			$this->email_log_done( $log );
			if ( '' !== $admin_to ) {
				$admin_attachments = $this->get_email_attachments( $attachments, 'admin', 'invoice' );
				$log = $this->email_log_as( 'invoice_store' );
				$mailer->send_order_email( $admin_to, $subject, $admin_message, $admin_attachments );
				$this->email_log_done( $log );
			}
		}else{
			/* A custom sender has always been handed the customer's address, also for the store copy alone. */
			$custom_to = ( $admin_only && class_exists( 'ec_email' ) && method_exists( 'ec_email', 'valid_recipients' ) ) ? implode( ',', ec_email::valid_recipients( stripslashes( (string) $this->user_email ) ) ) : $customer_to;
			if ( '' !== $custom_to || '' !== $admin_to ) {
				do_action( 'wpeasycart_custom_order_email', stripslashes( get_option( 'ec_option_order_from_email' ) ), $custom_to, $admin_to, $subject, $message, $customer_attachments );
			}
			if ( '' !== $other_to ) {
				do_action( 'wpeasycart_custom_order_email', stripslashes( get_option( 'ec_option_order_from_email' ) ), $other_to, $admin_to, $subject, $message, $customer_attachments );
			}
		}
	}

	/**
	 * Files attached to one recipient's copy of an order receipt or invoice email.
	 *
	 * The free plugin never creates attachments itself; WP EasyCart PRO hooks
	 * 'wp_easycart_order_email_attachments' to add a PDF copy of the order.
	 *
	 * @since 6.0.0
	 *
	 * @param array  $attachments Files from the older 'wpeasycart_order_email_attachments' filter ( every recipient ).
	 * @param string $recipient   'customer' | 'admin'.
	 * @param string $email       'receipt' | 'invoice'.
	 * @param array  $args        6.0.1: per-send choices from the admin send dialog ( documents, profiles, chosen items ).
	 * @return array
	 */
	private function get_email_attachments( $attachments, $recipient, $email, $args = array() ) {
		$attachments = is_array( $attachments ) ? $attachments : array();
		/**
		 * Filters the files attached to one recipient's copy of an order email.
		 *
		 * @since 6.0.0
		 *
		 * @param array  $files     Absolute file paths to attach.
		 * @param int    $order_id  Order ID.
		 * @param string $recipient 'customer' ( shopper and the order's second address ) | 'admin' ( store notification addresses ).
		 * @param array  $context   array( 'email' => 'receipt' | 'invoice', 'order' => ec_orderdisplay, 'args' => per-send choices ( 6.0.1 ) ).
		 */
		$context = array( 'email' => $email, 'order' => $this, 'args' => (array) $args );
		/* 6.0.1: through ec_email so a failed send that is queued can build the same files again on retry. */
		$extra = ( class_exists( 'ec_email' ) && method_exists( 'ec_email', 'attachments' ) ) ? ec_email::attachments( 'wp_easycart_order_email_attachments', array(), (int) $this->order_id, $recipient, $context ) : apply_filters( 'wp_easycart_order_email_attachments', array(), (int) $this->order_id, $recipient, $context );
		if ( ! is_array( $extra ) || empty( $extra ) ) {
			return $attachments;
		}
		return array_merge( $attachments, $extra );
	}

	public function send_refund_email( $admin_only = false ) {
		$tax_struct = new ec_tax( 0,0,0, "", "");
		$total = $GLOBALS['currency']->get_currency_display( $this->grand_total );
		$subtotal = $GLOBALS['currency']->get_currency_display( $this->sub_total );
		$tip = $GLOBALS['currency']->get_currency_display( $this->tip_total );
		$tax = $GLOBALS['currency']->get_currency_display( $this->tax_total );
		if ( $this->duty_total > 0 ) {
			$has_duty = true;
		} else {
			$has_duty = false;
		}
		$duty = $GLOBALS['currency']->get_currency_display( $this->duty_total );
		$vat = $GLOBALS['currency']->get_currency_display( $this->vat_total );
		$shipping = $GLOBALS['currency']->get_currency_display( $this->shipping_total );
		if ( $this->vat_rate > 0 ) {
			$vat_rate_formatted = $vat_rate = $this->vat_rate;
		} else if( ( $this->grand_total - $this->vat_total ) > 0 ) {
			$vat_rate_formatted = $vat_rate = ( $this->vat_total / ( $this->grand_total - $this->vat_total ) ) * 100;
		} else {
			$vat_rate_formatted = $vat_rate = 0;
		}
		if( round( $vat_rate_formatted, 0 ) == $vat_rate ){
			$vat_rate_formatted = number_format( round( $vat_rate_formatted, 0 ), 0, '', '' );
		}else if( round( $vat_rate_formatted, 1 ) == $vat_rate ){
			$vat_rate_formatted = number_format( $vat_rate_formatted, 1, '.', '' );
		}else if( round( $vat_rate_formatted, 2 ) == $vat_rate ){
			$vat_rate_formatted = number_format( $vat_rate_formatted, 2, '.', '' );
		}else if( round( $vat_rate_formatted, 3 ) == $vat_rate ){
			$vat_rate_formatted = number_format( $vat_rate_formatted, 3, '.', '' );
		}
		$vat_rate = $vat_rate_formatted;
		$gst = $this->gst_total;
		$gst_rate = $this->gst_rate;
		$pst = $this->pst_total;
		$pst_rate = $this->pst_rate;
		$hst = $this->hst_total;
		$hst_rate = $this->hst_rate;

		$discount = $GLOBALS['currency']->get_currency_display( $this->discount_total );
		$refund = $GLOBALS['currency']->get_currency_display( $this->refund_total );

		$email_logo_url = get_option( 'ec_option_email_logo' );

		$storepageid = get_option('ec_option_storepage');
		if ( function_exists( 'icl_object_id' ) ) {
			$storepageid = icl_object_id( $storepageid, 'page', true, ICL_LANGUAGE_CODE );
		}
		$store_page = get_permalink( $storepageid );
		if ( class_exists( "WordPressHTTPS" ) && isset( $_SERVER['HTTPS'] ) ) {
			$https_class = new WordPressHTTPS();
			$store_page = $https_class->makeUrlHttps( $store_page );
		}

		if ( substr_count( $store_page, '?' ) ) {
			$permalink_divider = "&";
		} else {
			$permalink_divider = "?";
		}

		$headers   = array();
		$headers[] = "MIME-Version: 1.0";
		$headers[] = "Content-Type: text/html; charset=utf-8";
		$headers[] = "From: " . stripslashes( get_option( 'ec_option_order_from_email' ) );
		$headers[] = "Reply-To: " . stripslashes( get_option( 'ec_option_order_from_email' ) );
		$headers[] = "X-Mailer: PHP/".phpversion();

		ob_start();
		$is_admin = false;
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_email_refund.php' ) ) {
			include EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_email_refund.php';	
		} else {
			include EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_email_refund.php';
		}
		$message = ob_get_clean();
		$message = apply_filters( 'wpeasycart_refund_email_customer_content', $message, $this->order_id );
		$customer_title = wp_easycart_language( )->get_text( "cart_success", "cart_refund_email_title" );
		$customer_title= apply_filters( 'wpeasycart_refund_email_customer_title', $customer_title, $this->order_id );

		ob_start();
		$is_admin = true;
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_email_refund.php' ) ) {
			include EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_email_refund.php';
		} else {
			include EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_email_refund.php';
		}
		$admin_message = ob_get_clean();
		$admin_message = apply_filters( 'wpeasycart_refund_email_admin_content', $admin_message, $this->order_id );
		$admin_title = wp_easycart_language( )->get_text( "cart_success", "cart_refund_email_title" );
		$admin_title= apply_filters( 'wpeasycart_refund_email_admin_title', $admin_title, $this->order_id );
		$admin_email = apply_filters( 'wpeasycart_refund_email_admin_email', get_option( 'ec_option_bcc_email_addresses' ), $this->order_id );

		$attachments = array();
		$attachments = apply_filters( 'wpeasycart_refund_email_attachments', $attachments, $this->order_id );

		$email_send_method = get_option( 'ec_option_use_wp_mail' );
		$email_send_method = apply_filters( 'wpeasycart_email_method', $email_send_method );

		/* 6.0.2: only addresses that can take mail ( see send_email_receipt() ). */
		$customer_to = ( $admin_only ) ? '' : $this->email_recipients( $this->user_email, 'order_refund', $customer_title );
		$other_to    = ( $admin_only || '' == $this->email_other ) ? '' : $this->email_recipients( $this->email_other, 'order_refund', $customer_title );
		$admin_to    = $this->email_recipients( $admin_email, 'order_refund_store', $admin_title, false );

		if( $email_send_method == "1" ){
			$log = $this->email_log_as( 'order_refund' );
			if ( '' !== $customer_to ) {
				wp_mail( $customer_to, $customer_title, $message, implode("\r\n", $headers), $attachments );
			}
			if ( '' !== $other_to ) {
				wp_mail( $other_to, $customer_title, $message, implode("\r\n", $headers), $attachments );
			}
			$this->email_log_done( $log );
			$headers   = array();
			$headers[] = "MIME-Version: 1.0";
			$headers[] = "Content-Type: text/html; charset=utf-8";
			$headers[] = "From: " . stripslashes( get_option( 'ec_option_order_from_email' ) );
			$headers[] = "Reply-To: " . stripslashes( $this->user_email );
			$headers[] = "X-Mailer: PHP/".phpversion();
			if ( '' !== $admin_to ) {
				$log = $this->email_log_as( 'order_refund_store' );
				wp_mail( $admin_to, $admin_title, $admin_message, implode("\r\n", $headers), $attachments );
				$this->email_log_done( $log );
			}
		}else if( $email_send_method == "0" ){
			$mailer = new wpeasycart_mailer( );
			$log = $this->email_log_as( 'order_refund' );
			if ( '' !== $customer_to ) {
				$mailer->send_order_email( $customer_to, $customer_title, $message );
			}
			if ( '' !== $other_to ) {
				$mailer->send_order_email( $other_to, $customer_title, $message );
			}
			$this->email_log_done( $log );
			if ( '' !== $admin_to ) {
				$log = $this->email_log_as( 'order_refund_store' );
				$mailer->send_order_email( $admin_to, $admin_title, $admin_message );
				$this->email_log_done( $log );
			}
		}else{
			/* A custom sender has always been handed the customer's address, also for the store copy alone. */
			$custom_to = ( $admin_only && class_exists( 'ec_email' ) && method_exists( 'ec_email', 'valid_recipients' ) ) ? implode( ',', ec_email::valid_recipients( stripslashes( (string) $this->user_email ) ) ) : $customer_to;
			if ( '' !== $custom_to || '' !== $admin_to ) {
				do_action( 'wpeasycart_custom_refund_email', stripslashes( get_option( 'ec_option_order_from_email' ) ), $custom_to, $admin_to, $customer_title, $message );
			}
			if ( '' !== $other_to ) {
				do_action( 'wpeasycart_custom_refund_email', stripslashes( get_option( 'ec_option_order_from_email' ) ), $other_to, $admin_to, $customer_title, $message );
			}
		}
	}

	public function send_failed_payment() {
		$subscription = $this->mysqli->get_subscription_row( $this->subscription_id );
		$email_logo_url = get_option( 'ec_option_email_logo' );

		$storepageid = get_option('ec_option_storepage');
		if ( function_exists( 'icl_object_id' ) ) {
			$storepageid = icl_object_id( $storepageid, 'page', true, ICL_LANGUAGE_CODE );
		}
		$store_page = get_permalink( $storepageid );
		if ( class_exists( "WordPressHTTPS" ) && isset( $_SERVER['HTTPS'] ) ) {
			$https_class = new WordPressHTTPS();
			$store_page = $https_class->makeUrlHttps( $store_page );
		}

		if ( substr_count( $store_page, '?' ) ) {
			$permalink_divider = "&";
		} else {
			$permalink_divider = "?";
		}

		$headers   = array();
		$headers[] = "MIME-Version: 1.0";
		$headers[] = "Content-Type: text/html; charset=utf-8";
		$headers[] = "From: " . stripslashes( get_option( 'ec_option_order_from_email' ) );
		$headers[] = "Reply-To: " . stripslashes( get_option( 'ec_option_order_from_email' ) );
		$headers[] = "X-Mailer: PHP/" . phpversion( );

		ob_start();
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_payment_failed.php' ) ) {
			include EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_payment_failed.php';
		} else {
			include EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_payment_failed.php';
		}
		$message = ob_get_clean();

		/* 6.0.2: only addresses that can take mail ( see send_email_receipt() ). */
		$subject     = wp_easycart_language( )->get_text( "ec_errors", "subscription_payment_failed_title" );
		$customer_to = $this->email_recipients( $this->user_email, 'payment_failed', $subject );
		$other_to    = ( '' == $this->email_other ) ? '' : $this->email_recipients( $this->email_other, 'payment_failed', $subject );
		$admin_to    = $this->email_recipients( get_option( 'ec_option_bcc_email_addresses' ), 'payment_failed_store', $subject, false );

		if ( get_option( 'ec_option_use_wp_mail' ) ) {
			$log = $this->email_log_as( 'payment_failed' );
			if ( '' !== $customer_to ) {
				wp_mail( $customer_to, $subject, $message, implode("\r\n", $headers) );
			}
			if ( '' !== $other_to ) {
				wp_mail( $other_to, $subject, $message, implode("\r\n", $headers) );
			}
			$this->email_log_done( $log );
			if ( '' !== $admin_to ) {
				$log = $this->email_log_as( 'payment_failed_store' );
				wp_mail( $admin_to, $subject, $message, implode("\r\n", $headers) );
				$this->email_log_done( $log );
			}
		} else {
			$mailer = new wpeasycart_mailer( );
			$log = $this->email_log_as( 'payment_failed' );
			if ( '' !== $customer_to ) {
				$mailer->send_order_email( $customer_to, $subject, $message );
			}
			if ( '' !== $other_to ) {
				$mailer->send_order_email( $other_to, $subject, $message );
			}
			$this->email_log_done( $log );
			if ( '' !== $admin_to ) {
				$log = $this->email_log_as( 'payment_failed_store' );
				$mailer->send_order_email( $admin_to, $subject, $message );
				$this->email_log_done( $log );
			}
		}
	}

	public function send_gift_cards() {
		foreach ( $this->cart->cart as $cart_item ) {
			if ( $cart_item->is_giftcard ) {
				global $wpdb;
				$cart_item->gift_card_value = $cart_item->unit_price;
				$cart_item->gift_card_value = $wpdb->get_var( $wpdb->prepare( "SELECT amount FROM ec_giftcard WHERE giftcard_id = %s", $cart_item->giftcard_id ) );

				$store_page = $this->store_page;
				$email_logo_url = get_option( 'ec_option_email_logo' );
				$giftcard_id = $cart_item->giftcard_id;

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

				/* 6.0.2: only addresses that can take mail ( see send_email_receipt() ). */
				$subject  = wp_easycart_language( )->get_text( "cart_success", "cart_giftcard_receipt_title" );
				$to       = $this->email_recipients( $cart_item->gift_card_email, 'giftcard', $subject );
				$admin_to = $this->email_recipients( get_option( 'ec_option_bcc_email_addresses' ), 'giftcard_store', $subject, false );

				if( get_option( 'ec_option_use_wp_mail' ) ){
					if ( '' !== $to ) {
						$log = $this->email_log_as( 'giftcard' );
						wp_mail( $to, $subject, $message, implode("\r\n", $headers) );
						$this->email_log_done( $log );
					}
					if ( '' !== $admin_to ) {
						$log = $this->email_log_as( 'giftcard_store' );
						wp_mail( $admin_to, $subject, $message, implode("\r\n", $headers) );
						$this->email_log_done( $log );
					}
				}else{
					$mailer = new wpeasycart_mailer( );
					if ( '' !== $to ) {
						$log = $this->email_log_as( 'giftcard' );
						$mailer->send_order_email( $to, $subject, $message );
						$this->email_log_done( $log );
					}
					if ( '' !== $admin_to ) {
						$log = $this->email_log_as( 'giftcard_store' );
						$mailer->send_order_email( $admin_to, $subject, $message );
						$this->email_log_done( $log );
					}
				}

			}
		}
	}

	public function display_subscription_link( $text ) {
		echo "<a href=\"" . esc_url( wpeasycart_links()->get_account_page( 'subscription_details', array( 'subscription_id' => (int) $this->subscription_id ) ) ) . "\">" . esc_attr( $text ) . "</a>";
	}

	public function display_order_customer_notes( ){
		global $wpdb;
		$order_notes = $wpdb->get_results( $wpdb->prepare( "SELECT ec_product.order_completed_note FROM ec_order, ec_orderdetail, ec_product WHERE ec_order.order_id = %d AND ec_orderdetail.order_id = ec_order.order_id AND ec_product.product_id = ec_orderdetail.product_id GROUP BY ec_orderdetail.product_id", $this->order_id ) );
		foreach( $order_notes as $order_note ){
			if( $order_note->order_completed_note != '' ){
				$content = do_shortcode( stripslashes( $order_note->order_completed_note ) );
				$content = str_replace( ']]>', ']]&gt;', $content );
				echo wp_easycart_escape_html( $content );
			}
		}
	}

	public function display_order_customer_email_notes( ){
		global $wpdb;
		$order_notes = $wpdb->get_results( $wpdb->prepare( "SELECT ec_product.order_completed_email_note FROM ec_order, ec_orderdetail, ec_product WHERE ec_order.order_id = %d AND ec_orderdetail.order_id = ec_order.order_id AND ec_product.product_id = ec_orderdetail.product_id GROUP BY ec_orderdetail.product_id", $this->order_id ) );
		foreach( $order_notes as $order_note ){
			if( $order_note->order_completed_email_note != '' ){
				$content = do_shortcode( stripslashes( $order_note->order_completed_email_note ) );
				$content = str_replace( ']]>', ']]&gt;', $content );
				echo wp_easycart_escape_html( $content );
			}
		}
	}

	public function display_order_customer_details_notes( ){
		global $wpdb;
		$order_notes = $wpdb->get_results( $wpdb->prepare( "SELECT ec_product.order_completed_details_note FROM ec_order, ec_orderdetail, ec_product WHERE ec_order.order_id = %d AND ec_orderdetail.order_id = ec_order.order_id AND ec_product.product_id = ec_orderdetail.product_id GROUP BY ec_orderdetail.product_id", $this->order_id ) );
		foreach( $order_notes as $order_note ){
			if( $order_note->order_completed_details_note != '' ){
				$content = do_shortcode( stripslashes( $order_note->order_completed_details_note ) );
				$content = str_replace( ']]>', ']]&gt;', $content );
				echo wp_easycart_escape_html( $content );
			}
		}
	}

	public function has_membership_page( ){
		if( $this->membership_page != "" )
			return true;
		else
			return false;
	}

	public function get_membership_page_link( ){
		return $this->membership_page;
	}

	public function has_downloads( ){
		foreach( $this->orderdetails as $order_item ){
			if( $order_item->is_download )
				return true;
		}
		return false;
	}

	public function get_location() {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_location WHERE location_id = %d', $this->location_id ) );
	}
}
