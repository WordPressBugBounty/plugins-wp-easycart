<?php

class ec_cartpage {
	protected $mysqli;
	public $cart;
	public $user;
	public $tax;
	public $shipping;
	public $discount;
	public $order_totals;
	public $payment;
	public $order;
	public $coupon;
	public $giftcard;
	public $coupon_code;
	public $gift_card;
	public $subscription_option1;
	public $subscription_option2;
	public $subscription_option3;
	public $subscription_option4;
	public $subscription_option5;
	public $subscription_option1_name;
	public $subscription_option2_name;
	public $subscription_option3_name;
	public $subscription_option4_name;
	public $subscription_option5_name;
	public $subscription_option1_label;
	public $subscription_option2_label;
	public $subscription_option3_label;
	public $subscription_option4_label;
	public $subscription_option5_label;
	public $subscription_advanced_options;
	public $has_downloads;
	public $store_page;
	public $cart_page;
	public $account_page;
	public $permalink_divider;
	private $analytics;
	private $is_affirm;
	/** option_to_product_id values whose text input rules rejected the last submission ( see get_advanced_option_vals() ). @since 6.0.0 */
	private $option_input_errors = array();
	/** 6.0.0: why each rejected option failed ( option_to_product_id => 'empty' | 'min_length' | 'file_type' | 'file_size' | 'file_upload' ). */
	private $option_input_error_reasons = array();
	public $shipping_address_allowed;
	public $offer_result;
	/**
	 * Unpaid order the pay link opened ( ec_page=invoice ); its Stripe card payment gets its own intent.
	 *
	 * @since 6.0.2
	 * @var object|null
	 */
	private $invoice_order = null;

	function __construct( $is_affirm = false ) {
		$this->is_affirm = $is_affirm;
		$this->shipping_address_allowed = true;

		$this->mysqli = new ec_db();
		$this->cart = new ec_cart( $GLOBALS['ec_cart_data']->ec_cart_id );
		if ( ! isset( $GLOBALS['ec_cart_data']->cart_data->payment_method ) || '' == $GLOBALS['ec_cart_data']->cart_data->payment_method ) {
			$GLOBALS['ec_cart_data']->cart_data->payment_method = $this->get_selected_payment_method();
		}
		if ( get_option( 'ec_option_ship_to_billing_global' ) && ! $GLOBALS['ec_user']->allow_shipping_bypass ) {
			$this->shipping_address_allowed = false;
		} else {
			if ( ! $GLOBALS['ec_user']->allow_shipping_bypass ) {
				foreach ( $this->cart->cart as $cart_item ) {
					if ( $cart_item->ship_to_billing ) {
						$this->shipping_address_allowed = false;
					}
				}
			}
		}
		$this->user =& $GLOBALS['ec_user'];
		// For the cart, alter the user to use the saved data only.
		if ( $GLOBALS['ec_cart_data']->cart_data->is_guest == "" ) {

			if ( isset( $GLOBALS['ec_cart_data']->cart_data->estimate_shipping_zip ) )
				$estimate_shipping_zip = $GLOBALS['ec_cart_data']->cart_data->estimate_shipping_zip;
			else
				$estimate_shipping_zip = "";

			if ( isset( $GLOBALS['ec_cart_data']->cart_data->estimate_shipping_country ) )
				$estimate_shipping_country = $GLOBALS['ec_cart_data']->cart_data->estimate_shipping_country;
			else
				$estimate_shipping_country = "";

			if ( isset( $GLOBALS['ec_cart_data']->cart_data->billing_zip ) )
				$billing_zip = $GLOBALS['ec_cart_data']->cart_data->billing_zip;
			else
				$billing_zip = "";

			if ( isset( $GLOBALS['ec_cart_data']->cart_data->shipping_zip ) )
				$shipping_zip = $GLOBALS['ec_cart_data']->cart_data->shipping_zip;
			else
				$shipping_zip = "";

			if ( isset( $GLOBALS['ec_cart_data']->cart_data->billing_country ) )
				$billing_country = $GLOBALS['ec_cart_data']->cart_data->billing_country;
			else
				$billing_country = "";

			if ( isset( $GLOBALS['ec_cart_data']->cart_data->shipping_country ) )
				$shipping_country = $GLOBALS['ec_cart_data']->cart_data->shipping_country;
			else
				$shipping_country = "";

			if ( isset( $GLOBALS['ec_cart_data']->cart_data->billing_state ) )
				$billing_state = $GLOBALS['ec_cart_data']->cart_data->billing_state;
			else
				$billing_state = "";

			if ( isset( $GLOBALS['ec_cart_data']->cart_data->shipping_state ) )
				$shipping_state = $GLOBALS['ec_cart_data']->cart_data->shipping_state;
			else
				$shipping_state = "";

			if ( $billing_zip == "" )
				$billing_zip = $estimate_shipping_zip;
			if ( $shipping_zip == "" )
				$shipping_zip = $estimate_shipping_zip;
			if ( $billing_country == "" )
				$billing_country = $estimate_shipping_country;
			if ( $shipping_country == "" )
				$shipping_country = $estimate_shipping_country;

		} else if ( $GLOBALS['ec_cart_data']->cart_data->is_guest != "" && $GLOBALS['ec_cart_data']->cart_data->is_guest ) {
			if ( isset( $GLOBALS['ec_cart_data']->cart_data->billing_zip ) )
				$billing_zip = $GLOBALS['ec_cart_data']->cart_data->billing_zip;
			else
				$billing_zip = "";
			if ( isset( $GLOBALS['ec_cart_data']->cart_data->shipping_zip ) )
				$shipping_zip = $GLOBALS['ec_cart_data']->cart_data->shipping_zip;
			else
				$shipping_zip = "";
			if ( isset( $GLOBALS['ec_cart_data']->cart_data->billing_country ) )
				$billing_country = $GLOBALS['ec_cart_data']->cart_data->billing_country;
			else
				$billing_country = "";
			if ( isset( $GLOBALS['ec_cart_data']->cart_data->shipping_country ) )
				$shipping_country = $GLOBALS['ec_cart_data']->cart_data->shipping_country;
			else
				$shipping_country = "";
		}

		if ( $GLOBALS['ec_cart_data']->cart_data->is_guest != "" && $GLOBALS['ec_cart_data']->cart_data->is_guest ) {

			$billing_first_name = $billing_last_name = $billing_company = $billing_address_line_1 = $billing_address_line_2 = $billing_city = $billing_state = $billing_phone = "";
			if ( isset( $GLOBALS['ec_cart_data']->cart_data->billing_first_name ) )
				$billing_first_name = $GLOBALS['ec_cart_data']->cart_data->billing_first_name;
			if ( isset( $GLOBALS['ec_cart_data']->cart_data->billing_last_name ) )
				$billing_last_name = $GLOBALS['ec_cart_data']->cart_data->billing_last_name;
			if ( isset( $GLOBALS['ec_cart_data']->cart_data->billing_company_name ) )
				$billing_company = $GLOBALS['ec_cart_data']->cart_data->billing_company_name;
			if ( isset( $GLOBALS['ec_cart_data']->cart_data->billing_address_line_1 ) )
				$billing_address_line_1 = $GLOBALS['ec_cart_data']->cart_data->billing_address_line_1;
			if ( isset( $GLOBALS['ec_cart_data']->cart_data->billing_address_line_2 ) )
				$billing_address_line_2 = $GLOBALS['ec_cart_data']->cart_data->billing_address_line_2;
			if ( isset( $GLOBALS['ec_cart_data']->cart_data->billing_city ) )
				$billing_city = $GLOBALS['ec_cart_data']->cart_data->billing_city;
			if ( isset( $GLOBALS['ec_cart_data']->cart_data->billing_state ) )
				$billing_state = $GLOBALS['ec_cart_data']->cart_data->billing_state;
			if ( isset( $GLOBALS['ec_cart_data']->cart_data->billing_phone ) )
				$billing_phone = $GLOBALS['ec_cart_data']->cart_data->billing_phone;
			$this->user->setup_billing_info_data( $billing_first_name, $billing_last_name, $billing_address_line_1, $billing_address_line_2, $billing_city, $billing_state, $billing_country, $billing_zip, $billing_phone, $billing_company );

			$shipping_first_name = $shipping_last_name = $shipping_company = $shipping_address_line_1 = $shipping_address_line_2 = $shipping_city = $shipping_state = $shipping_phone = "";
			if ( isset( $GLOBALS['ec_cart_data']->cart_data->shipping_first_name ) )
				$shipping_first_name = $GLOBALS['ec_cart_data']->cart_data->shipping_first_name;
			if ( isset( $GLOBALS['ec_cart_data']->cart_data->shipping_last_name ) )
				$shipping_last_name = $GLOBALS['ec_cart_data']->cart_data->shipping_last_name;
			if ( isset( $GLOBALS['ec_cart_data']->cart_data->shipping_company_name ) )
				$shipping_company = $GLOBALS['ec_cart_data']->cart_data->shipping_company_name;
			if ( isset( $GLOBALS['ec_cart_data']->cart_data->shipping_address_line_1 ) )
				$shipping_address_line_1 = $GLOBALS['ec_cart_data']->cart_data->shipping_address_line_1;
			if ( isset( $GLOBALS['ec_cart_data']->cart_data->shipping_address_line_2 ) )
				$shipping_address_line_2 = $GLOBALS['ec_cart_data']->cart_data->shipping_address_line_2;
			if ( isset( $GLOBALS['ec_cart_data']->cart_data->shipping_city ) )
				$shipping_city = $GLOBALS['ec_cart_data']->cart_data->shipping_city;
			if ( isset( $GLOBALS['ec_cart_data']->cart_data->shipping_state ) )
				$shipping_state = $GLOBALS['ec_cart_data']->cart_data->shipping_state;
			if ( isset( $GLOBALS['ec_cart_data']->cart_data->shipping_phone ) )
				$shipping_phone = $GLOBALS['ec_cart_data']->cart_data->shipping_phone;
			$this->user->setup_shipping_info_data( $shipping_first_name, $shipping_last_name, $shipping_address_line_1, $shipping_address_line_2, $shipping_city, $shipping_state, $shipping_country, $shipping_zip, $shipping_phone, $shipping_company );

		}

		if ( isset( $GLOBALS['ec_cart_data']->cart_data->coupon_code ) && $GLOBALS['ec_cart_data']->cart_data->coupon_code != "" ) {
			$this->coupon_code = $GLOBALS['ec_cart_data']->cart_data->coupon_code;
			$coupon_result = $GLOBALS['ec_coupons']->redeem_coupon_code( $this->coupon_code );
			if ( $coupon_result ) {
				$this->coupon = $coupon_result;
			}
		} else {
			$this->coupon_code = "";
		}

		if ( isset( $GLOBALS['ec_cart_data']->cart_data->giftcard ) && $GLOBALS['ec_cart_data']->cart_data->giftcard != "" ) {
			$this->gift_card = $GLOBALS['ec_cart_data']->cart_data->giftcard;
			$this->giftcard = $this->mysqli->redeem_gift_card( $this->gift_card );
			if ( !$this->giftcard )
				$this->gift_card = "";
		} else {
			$this->gift_card = "";
		}

		// Create Promotion and apply free shipping if necessary.
		$promotion = new ec_promotion();
		$promotion->apply_free_shipping( $this->cart );

		// Offers v2: single evaluation for this request (engine memoizes).
		$this->offer_result = ( wp_easycart_offers_active() ) ? ec_offer_integration::evaluate_cart( $this->cart ) : null;
		$GLOBALS['wpeasycart_offer_result'] = $this->offer_result;
		$this->set_offer_line_savings();

		// Shipping
		$sales_tax_discount = new ec_discount( $this->cart, $this->cart->discountable_subtotal, 0.00, $this->coupon_code, "", 0 );
		if ( null !== $this->offer_result ) {
			$sales_tax_discount->coupon_discount += $this->offer_result->discount_total;
			$sales_tax_discount->discount_total += $this->offer_result->discount_total;
		}
		$GLOBALS['wpeasycart_current_coupon_discount'] = $sales_tax_discount->coupon_discount;
		$this->shipping = new ec_shipping( $this->cart->shipping_subtotal, $this->cart->weight, $this->cart->store_shippable_items /* 6.0.2: the units the store ships */, 'RADIO', $GLOBALS['ec_user']->freeshipping, $this->cart->length, $this->cart->width, $this->cart->height, $this->cart->cart );
		$shipping_price = $this->shipping->get_shipping_price( $this->cart->get_handling_total() );
		// Tax (no VAT here)
		$sales_tax_discount = new ec_discount( $this->cart, $this->cart->discountable_subtotal, $shipping_price, $this->coupon_code, "", 0 );
		if ( null !== $this->offer_result ) {
			$sales_tax_discount->coupon_discount += $this->offer_result->discount_total;
			$sales_tax_discount->discount_total += $this->offer_result->discount_total;
		}
		if ( $sales_tax_discount->shipping_discount > 0 ) {
			$shipping_price_tax = ( $shipping_price > $sales_tax_discount->shipping_discount ) ? $shipping_price - $sales_tax_discount->shipping_discount : 0;
		} else if ( $this->cart->taxable_subtotal - $sales_tax_discount->coupon_discount < 0 ) { // Apply remainder to shipping
			$shipping_price_tax = $shipping_price - ( $sales_tax_discount->coupon_discount - $this->cart->taxable_subtotal );
		} else {
			$shipping_price_tax = $shipping_price;
		}
		$this->tax = new ec_tax( $this->cart->subtotal, $this->cart->taxable_subtotal - $sales_tax_discount->coupon_discount, 0, $shipping_state, $shipping_country, $GLOBALS['ec_user']->taxfree, $shipping_price_tax, $this->cart );
		// Duty (Based on Product Price) - already calculated in tax
		// Get Total Without VAT, used only breifly
		if ( get_option( 'ec_option_no_vat_on_shipping' ) ) {
			$total_without_vat_or_discount = $this->cart->vat_subtotal + $this->tax->tax_total + $this->tax->pst + $this->tax->hst + $this->tax->gst + $this->tax->duty_total;
		} else {
			$total_without_vat_or_discount = $this->cart->vat_subtotal + $shipping_price + $this->tax->tax_total + $this->tax->pst + $this->tax->hst + $this->tax->gst + $this->tax->duty_total;
		}
		//If a discount used, and no vatable subtotal, we need to set to 0
		if ( $total_without_vat_or_discount < 0 ) {
			$total_without_vat_or_discount = 0;
		}
		// Discount for Coupon
		$this->discount = new ec_discount( $this->cart, $this->cart->discountable_subtotal, $shipping_price, $this->coupon_code, $this->gift_card, $total_without_vat_or_discount );
		if ( null !== $this->offer_result ) {
			$this->discount->coupon_discount += $this->offer_result->discount_total;
			$this->discount->discount_total += $this->offer_result->discount_total;
		}
		// Amount to Apply VAT on
		$promotion = new ec_promotion();
		$vatable_subtotal = $total_without_vat_or_discount - $this->tax->tax_total - $this->discount->coupon_discount - $promotion->get_discount_total( $this->cart->subtotal );
		// If for some reason this is less than zero, we should correct
		if ( $vatable_subtotal < 0 ) {
			$vatable_subtotal = 0;
		}
		$this->cart->apply_coupons_to_cart( $this->discount );
		// Get Tax Again For VAT
		$this->tax = new ec_tax( $this->cart->subtotal, $this->cart->taxable_subtotal - $sales_tax_discount->coupon_discount, $vatable_subtotal, $shipping_state, $shipping_country, $GLOBALS['ec_user']->taxfree, $shipping_price_tax, $this->cart );
		// Discount for Gift Card
		$grand_total = ( $this->cart->subtotal + $this->tax->tax_total + $this->tax->pst + $this->tax->hst + $this->tax->gst + $shipping_price + $this->tax->duty_total );
		$this->discount = new ec_discount( $this->cart, $this->cart->discountable_subtotal, $shipping_price, $this->coupon_code, $this->gift_card, $grand_total );
		if ( null !== $this->offer_result ) {
			$this->discount->coupon_discount += $this->offer_result->discount_total;
			$this->discount->discount_total += $this->offer_result->discount_total;
		}
		// Order Totals
		$this->order_totals = new ec_order_totals( $this->cart, $GLOBALS['ec_user'], $this->shipping, $this->tax, $this->discount );
		$GLOBALS['ec_order_grand_total' ] = $this->order_totals->grand_total;

		// Credit Card
		if ( isset( $_POST['ec_expiration_month'] ) && isset( $_POST['ec_expiration_year'] ) ) {
			$exp_month = sanitize_text_field( $_POST['ec_expiration_month'] );
			$exp_year = sanitize_text_field( $_POST['ec_expiration_year'] );

		} else if ( isset( $_POST['ec_cc_expiration'] ) ) {
			$exp_date = sanitize_text_field( $_POST['ec_cc_expiration'] );
			$exp_month = substr( $exp_date, 0, 2 );
			$exp_year = substr( $exp_date, 5 );
			if ( strlen( $exp_year ) == 2 ) {
				$exp_year = "20" . $exp_year;
			}
		}
		if ( isset( $_POST['ec_cart_payment_type'] ) ) {
			$credit_card = new ec_credit_card( sanitize_text_field( $_POST['ec_cart_payment_type'] ), stripslashes( sanitize_text_field( $_POST['ec_card_holder_name'] ) ), $this->sanatize_card_number( sanitize_text_field( $_POST['ec_card_number'] ) ), $exp_month, $exp_year, sanitize_text_field( $_POST['ec_security_code'] ) );
		} else if ( isset( $_POST['ec_card_number'] ) ) {
			$credit_card = new ec_credit_card( $this->get_payment_type( $this->sanatize_card_number( sanitize_text_field( $_POST['ec_card_number'] ) ) ), stripslashes( sanitize_text_field( $_POST['ec_card_holder_name'] ) ),  $this->sanatize_card_number( sanitize_text_field( $_POST['ec_card_number'] ) ), $exp_month, $exp_year, sanitize_text_field( $_POST['ec_security_code'] ) );
		} else {
			$credit_card = new ec_credit_card( "", "", "", "", "", "" );
		}

		// Payment
		if ( isset( $_POST['ec_cart_payment_selection'] ) ) {
			$this->payment = new ec_payment( $credit_card, sanitize_text_field( $_POST['ec_cart_payment_selection'] ) );
		} else if ( $is_affirm ) {
			$this->payment = new ec_payment( $credit_card, "affirm" );
		} else {
			$this->payment = new ec_payment( $credit_card, "" );
		}

		// Order
		$this->order = new ec_order( $this->cart, $GLOBALS['ec_user'], $this->shipping, $this->tax, $this->discount, $this->order_totals, $this->payment );

		$store_page_id = get_option('ec_option_storepage');
		$cart_page_id = get_option('ec_option_cartpage');
		$account_page_id = apply_filters( 'wp_easycart_account_page_id', get_option( 'ec_option_accountpage' ) );

		if ( function_exists( 'icl_object_id' ) ) {
			$store_page_id = icl_object_id( $store_page_id, 'page', true, ICL_LANGUAGE_CODE );
			$cart_page_id = icl_object_id( $cart_page_id, 'page', true, ICL_LANGUAGE_CODE );
			$account_page_id = icl_object_id( $account_page_id, 'page', true, ICL_LANGUAGE_CODE );
		}

		$this->store_page = get_permalink( $store_page_id );
		$this->cart_page = get_permalink( $cart_page_id );
		$this->account_page = get_permalink( $account_page_id );

		if ( class_exists( "WordPressHTTPS" ) && isset( $_SERVER['HTTPS'] ) ) {
			$https_class = new WordPressHTTPS();
			$this->store_page = $https_class->makeUrlHttps( $this->store_page );
			$this->cart_page = $https_class->makeUrlHttps( $this->cart_page );
			$this->account_page = $https_class->makeUrlHttps( $this->account_page );

		} else if ( get_option( 'ec_option_load_ssl' ) ) {
			$this->store_page = str_replace( 'http://', 'https://', $this->store_page );
			$this->cart_page = str_replace( 'http://', 'https://', $this->cart_page );
			$this->account_page = str_replace( 'http://', 'https://', $this->account_page );

		}

		if ( substr_count( $this->cart_page, '?' ) )					$this->permalink_divider = "&";
		else														$this->permalink_divider = "?";

		// Subscription Options
		$this->subscription_option1 = $this->subscription_option2 = $this->subscription_option3 = $this->subscription_option4 = $this->subscription_option5 = 0;

		if ( ( isset( $GLOBALS['ec_cart_data']->cart_data->subscription_option1 ) && $GLOBALS['ec_cart_data']->cart_data->subscription_option1 != "" ) || 
			( isset( $GLOBALS['ec_cart_data']->cart_data->subscription_option2 ) && $GLOBALS['ec_cart_data']->cart_data->subscription_option2 != "" ) || 
			( isset( $GLOBALS['ec_cart_data']->cart_data->subscription_option3 ) && $GLOBALS['ec_cart_data']->cart_data->subscription_option3 != "" ) || 
			( isset( $GLOBALS['ec_cart_data']->cart_data->subscription_option4 ) && $GLOBALS['ec_cart_data']->cart_data->subscription_option4 != "" ) || 
			( isset( $GLOBALS['ec_cart_data']->cart_data->subscription_option5 ) && $GLOBALS['ec_cart_data']->cart_data->subscription_option5 != "" ) ) {

			$optionitem_list = $GLOBALS['ec_options']->get_all_optionitems();

			if ( isset( $GLOBALS['ec_cart_data']->cart_data->subscription_option1 ) && $GLOBALS['ec_cart_data']->cart_data->subscription_option1 != "" ) {
				$this->subscription_option1 = $GLOBALS['ec_cart_data']->cart_data->subscription_option1;
			}

			if ( isset( $GLOBALS['ec_cart_data']->cart_data->subscription_option2 ) && $GLOBALS['ec_cart_data']->cart_data->subscription_option2 != "" ) {
				$this->subscription_option2 = $GLOBALS['ec_cart_data']->cart_data->subscription_option2;
			}

			if ( isset( $GLOBALS['ec_cart_data']->cart_data->subscription_option3 ) && $GLOBALS['ec_cart_data']->cart_data->subscription_option3 != "" ) {
				$this->subscription_option3 = $GLOBALS['ec_cart_data']->cart_data->subscription_option3;
			}

			if ( isset( $GLOBALS['ec_cart_data']->cart_data->subscription_option4 ) && $GLOBALS['ec_cart_data']->cart_data->subscription_option4 != "" ) {
				$this->subscription_option4 = $GLOBALS['ec_cart_data']->cart_data->subscription_option4;
			}

			if ( isset( $GLOBALS['ec_cart_data']->cart_data->subscription_option5 ) && $GLOBALS['ec_cart_data']->cart_data->subscription_option5 != "" ) {
				$this->subscription_option5 = $GLOBALS['ec_cart_data']->cart_data->subscription_option5;
			}

			foreach( $optionitem_list as $option_item ) {
				if ( $option_item->optionitem_id == $this->subscription_option1 ) {
					$this->subscription_option1_name = $option_item->optionitem_name;
					$this->subscription_option1_label = $option_item->option_label;

				}

				if ( $option_item->optionitem_id == $this->subscription_option2 ) {
					$this->subscription_option2_name = $option_item->optionitem_name;
					$this->subscription_option2_label = $option_item->option_label;

				}

				if ( $option_item->optionitem_id == $this->subscription_option3 ) {
					$this->subscription_option3_name = $option_item->optionitem_name;
					$this->subscription_option3_label = $option_item->option_label;

				}

				if ( $option_item->optionitem_id == $this->subscription_option4 ) {
					$this->subscription_option4_name = $option_item->optionitem_name;
					$this->subscription_option4_label = $option_item->option_label;

				}

				if ( $option_item->optionitem_id == $this->subscription_option5 ) {
					$this->subscription_option5_name = $option_item->optionitem_name;
					$this->subscription_option5_label = $option_item->option_label;
				}
			}

		}

		// Subscription Advanced Options
		if ( isset( $GLOBALS['ec_cart_data']->cart_data->subscription_advanced_option ) && $GLOBALS['ec_cart_data']->cart_data->subscription_advanced_option != "" )
			$this->subscription_advanced_options = maybe_unserialize( $GLOBALS['ec_cart_data']->cart_data->subscription_advanced_option );
		else
			$this->subscription_advanced_options = "";

		// Check for downloads in cart
		$this->has_downloads = false;
		foreach( $this->cart->cart as $cart_item ) {
			if ( $cart_item->is_download ) {
				$this->has_downloads = true;
				break;
			}
		}

		add_filter( 'wp_easycart_shipping_price_display', array( $this, 'apply_promotions_to_shipping' ) );

		$this->cart_page = apply_filters( 'wp_easycart_cart_page_url', $this->cart_page );
		$this->account_page = apply_filters( 'wp_easycart_account_page_url', $this->account_page );

	}

	public function apply_promotions_to_shipping( $rate ) {
		$new_rate = $GLOBALS['ec_promotions']->apply_promotions_to_shipping( $this->order_totals->sub_total, $rate );
		return ( $new_rate >= 0 ) ? $new_rate : 0;
	}

	public function display_cart_success( $success_code = '' ) {
		$success_notes = array(	"account_created" => wp_easycart_language()->get_text( "ec_success", "cart_account_created" ) );

		if ( isset( $_GET['ec_cart_success'] ) ) {
			echo "<div class=\"ec_cart_success\"><div>" . esc_attr( $success_notes[ sanitize_key( $_GET['ec_cart_success'] ) ] ) . "</div></div>";
		} else if ( $success_code != '' ) {
			echo "<div class=\"ec_cart_success\"><div>" . esc_attr( $success_notes[ sanitize_key( $success_code ) ] ) . "</div></div>";
		}
	}

	/**
	 * The cart's error notices by code ( display_cart_error(), and the one-page checkout's order check ).
	 *
	 * @since 6.0.2 ( was inside display_cart_error() )
	 * @return array code => text
	 */
	public function cart_error_notes() {
		$minimum = (float) apply_filters( 'wpeasycart_minimum_order_total', get_option( 'ec_option_minimum_order_total' ) );
		return apply_filters( 'wpeasycart_cart_errors', array( 
			"email_exists"              => wp_easycart_language()->get_text( "ec_errors", "email_exists_error" ),
			"login_failed"              => wp_easycart_language()->get_text( "ec_errors", "login_failed" ),
			"3dsecure_failed"           => wp_easycart_language()->get_text( "ec_errors", "3dsecure_failed" ),
			"manualbill_failed"         => wp_easycart_language()->get_text( "ec_errors", "manualbill_failed" ),
			"thirdparty_failed"         => wp_easycart_language()->get_text( "ec_errors", "thirdparty_failed" ),
			"payment_failed"            => wp_easycart_language()->get_text( "ec_errors", "payment_failed" ),
			"card_error"                => wp_easycart_language()->get_text( "ec_errors", "payment_failed" ),
			"already_subscribed"        => wp_easycart_language()->get_text( "ec_errors", "already_subscribed" ),
			"not_activated"             => wp_easycart_language()->get_text( "ec_errors", "not_activated" ),
			"subscription_not_found"    => wp_easycart_language()->get_text( "ec_errors", "subscription_not_found" ),
			"user_insert_error"         => wp_easycart_language()->get_text( "ec_errors", "user_insert_error" ),
			"subscription_added_failed" => wp_easycart_language()->get_text( "ec_errors", "subscription_added_failed" ),
			"subscription_failed"       => wp_easycart_language()->get_text( "ec_errors", "subscription_failed" ),
			"invalid_address"           => wp_easycart_language()->get_text( "ec_errors", "invalid_address" ),
			"session_expired"           => wp_easycart_language()->get_text( "ec_errors", "session_expired" ),
			"invalid_nonce"             => wp_easycart_language()->get_text( "ec_errors", "session_expired" ), /* 6.0.2: a form whose nonce no longer matches the cart session ( it redirects with cart_error ) */
			"invalid_vat_number"        => wp_easycart_language()->get_text( "ec_errors", "invalid_vat_number" ),
			"stock_invalid"             => wp_easycart_language()->get_text( "ec_errors", "cart_stock_invalid" ),
			"shipping_method"           => wp_easycart_language()->get_text( "ec_errors", "missing_shipping_method" ),
			"invalid_cart_shipping"     => wp_easycart_language()->get_text( "ec_errors", "cart_location_error" ),
			"invalid_checkout"          => wp_easycart_language()->get_text( "cart_form_notices", "cart_notice_checkout_details_errors" ),
			"preorder_pickup"           => wp_easycart_language()->get_text( "ec_errors", "missing_preorder_pickup" ),
			"restaurant_closed"         => wp_easycart_language()->get_text( "cart_payment_information", "restaurant_closed" ),
			"minimum_order"             => wp_easycart_language()->get_text( "cart", "cart_minimum_purchase_amount1" ) . ' ' . $GLOBALS['currency']->get_currency_display( $minimum ) . ' ' . wp_easycart_language()->get_text( "cart", "cart_minimum_purchase_amount2" ),
		) );
	}

	public function display_cart_error( $error_code = '' ) {
		$error_notes = $this->cart_error_notes();
		/**
		 * The saved card decline text before the shopper sees it ( 6.0.2: checkout protection shows a simple message ).
		 *
		 * @since 6.0.2
		 * @param string $card_error Gateway text.
		 */
		$card_error = (string) apply_filters( 'wpeasycart_cart_card_error', (string) $GLOBALS['ec_cart_data']->cart_data->card_error );
		/* 6.0.2: the nonce and manual billing redirects name their code as cart_error; it was never read, so the shopper saw the page again with no message. */
		$wpec_code = '';
		if ( isset( $_GET['ec_cart_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a notice to show; nothing is changed.
			$wpec_code = sanitize_key( $_GET['ec_cart_error'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		} else if ( isset( $_GET['cart_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$wpec_code = sanitize_key( $_GET['cart_error'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( '' !== $wpec_code && 0 !== strpos( $wpec_code, 'protection_' ) && 'session_expired' !== $wpec_code && 'invalid_nonce' !== $wpec_code && $card_error != '' ) { /* a session notice is not about the card */
			echo "<div class=\"ec_cart_error\"><div>" . esc_attr( $card_error ) . "</div></div>";
		} else if ( '' !== $wpec_code && isset( $error_notes[ $wpec_code ] ) ) {
			echo "<div class=\"ec_cart_error\"><div>" . esc_attr( $error_notes[ $wpec_code ] ) . "</div></div>";
		} else if ( $error_code != '' && isset( $error_notes[ sanitize_key( $error_code ) ] ) ) {
			echo "<div class=\"ec_cart_error\"><div>" . esc_attr( $error_notes[ sanitize_key( $error_code ) ] ) . "</div></div>";
		}
	}

	public function display_cart_success_page( $order_id, $success_code = false, $error_code = false ) {
		global $wpdb;

		if ( $GLOBALS['ec_cart_data']->cart_data->is_guest ) {
			$order_row = $this->mysqli->get_guest_order_row( $order_id, $GLOBALS['ec_cart_data']->cart_data->guest_key );
		} else {
			$order_row = $this->mysqli->get_order_row( $order_id, $GLOBALS['ec_cart_data']->cart_data->user_id );
		}

		if ( !$order_row ) {
			$this->display_cart_page();
			return false;
		}

		do_action( 'wpeasycart_order_success' );

		$order = new ec_orderdisplay( $order_row, true );

		if ( $GLOBALS['ec_cart_data']->cart_data->guest_key != "" ) {
			$order_details = $this->mysqli->get_guest_order_details( $order_id, $GLOBALS['ec_cart_data']->cart_data->guest_key );

		} else {
			$order_details = $this->mysqli->get_order_details( $order_id, $GLOBALS['ec_cart_data']->cart_data->user_id );
		}

		$GLOBALS['ec_user']->setup_billing_info_data( $order->billing_first_name, $order->billing_last_name, $order->billing_address_line_1, $order->billing_address_line_2, $order->billing_city, $order->billing_state, $order->billing_country, $order->billing_zip, $order->billing_phone, $order->billing_company_name );

		$GLOBALS['ec_user']->setup_shipping_info_data( $order->shipping_first_name, $order->shipping_last_name, $order->shipping_address_line_1, $order->shipping_address_line_2, $order->shipping_city, $order->shipping_state, $order->shipping_country, $order->shipping_zip, $order->shipping_phone, $order->shipping_company_name );

		$tax_struct = $this->tax;

		$total = $GLOBALS['currency']->get_currency_display( $order->grand_total );
		$subtotal = $GLOBALS['currency']->get_currency_display( $order->sub_total );
		$tax = $GLOBALS['currency']->get_currency_display( $order->tax_total );
		$duty = $GLOBALS['currency']->get_currency_display( $order->duty_total );
		$vat = $GLOBALS['currency']->get_currency_display( $order->vat_total );
		if ( ( $order->grand_total - $order->vat_total ) > 0 )
			$vat_rate = number_format( $this->tax->vat_rate, 0, '', '' );
		else
			$vat_rate = number_format( 0, 0, '', '' );
		$shipping = $GLOBALS['currency']->get_currency_display( $order->shipping_total );
		$discount = $GLOBALS['currency']->get_currency_display( $order->discount_total );

		//google analytics
		$this->analytics = new ec_googleanalytics($order_details, $order->shipping_total, $order->tax_total , $order->grand_total, $order_id);
		$google_urchin_code = get_option('ec_option_googleanalyticsid');
		$google_wp_url = sanitize_text_field( $_SERVER['SERVER_NAME'] );
		//end google analytics
		$this->display_cart_error();

		//Backwards compatibility for an error... Don't want the button showing if user didn't create an account.
		if ( $GLOBALS['ec_cart_data']->cart_data->is_guest != "" && $GLOBALS['ec_cart_data']->cart_data->is_guest ) {
			$GLOBALS['ec_cart_data']->cart_data->email = "guest";
		}

		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_success.php' ) )	{
			include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_success.php' );
		} else {
			include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_success.php' );
		}

		// Update Cart Success Print Variable
		$wpdb->query( $wpdb->prepare( 'UPDATE ec_order SET success_page_shown = 1 WHERE ec_order.order_id = %d', $order_id ) );
	}

	public function print_google_transaction() {
		$this->analytics->print_transaction_js();
		$this->analytics->print_item_js();
	}

	public function display_cart_page() {
		if ( get_option( 'ec_option_payment_process_method' ) == 'stripe' || get_option( 'ec_option_payment_process_method' ) == 'stripe_connect' ) {
			if ( 'stripe' == get_option( 'ec_option_payment_process_method' ) ) {
				$stripe = new ec_stripe();
			} else {
				$stripe = new ec_stripe_connect();
			}

			$stripe_pi_response = $stripe->get_payment_intent( $GLOBALS['ec_cart_data']->cart_data->stripe_paymentintent_id );
			if ( $stripe_pi_response && in_array( $stripe_pi_response->status, array( 'succeeded', 'processing', 'requires_capture', 'canceled' ) ) ) {
				global $wpdb;
				$ec_db_admin = new ec_db_admin();
				$order = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM ec_order WHERE gateway_transaction_id = %s", $stripe_pi_response->id . ':' . $stripe_pi_response->client_secret ) );
				if ( $order ) {
					$order_id = $order->order_id;
				} else {
					/* 6.0.2: paid, but not this cart's total ( the cart changed after the payment ): on hold for the merchant, as the checkout's own completion does. */
					$wpec_hold = 'canceled' != $stripe_pi_response->status && ! wp_easycart_stripe_intent_matches_cart( $stripe_pi_response, $this );
					$source = array(
						'id' => $stripe_pi_response->id,
						'client_secret' => $stripe_pi_response->client_secret,
					);
					$order_id = $this->insert_ideal_order( $source, $stripe_pi_response );
					$stripe->update_payment_intent_description( $stripe_pi_response->id, $order_id );

					global $wpdb;
					$order_status = 6;
					if ( $stripe_pi_response->status == 'succeeded' ) {
						$order_status = 3;
					} else if ( $stripe_pi_response->status == 'requires_capture' ) {
						$order_status = 12;
					} else if ( $stripe_pi_response->status == 'processing' ) {
						$order_status = 12;
					} else if ( $stripe_pi_response->status == 'canceled' ) {
						$order_status = 19;
					}
					if ( $wpec_hold && $order_id ) {
						$order_status = 12;
						/* translators: %s: Stripe payment id. */
						wp_easycart_stripe_hold_order( $order_id, sprintf( __( 'The Stripe payment %s does not match this order’s total ( the cart changed after it was paid ), so the order is on hold. Check the payment in Stripe before you ship it.', 'wp-easycart' ), $stripe_pi_response->id ) );
					}
					if ( $order_id && 19 !== (int) $order_status && class_exists( 'wp_easycart_shipping_groups' ) && wp_easycart_shipping_groups::hold_order( $order_id, $this ) ) {
						$order_status = 12; /* 6.0.2: paid, but a fulfillment partner's shipping could not be confirmed for this address */
					}
					$wpec_previous_status = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT orderstatus_id FROM ec_order WHERE order_id = %d', (int) $order_id ) );
					$wpdb->get_row( $wpdb->prepare( "UPDATE ec_order SET orderstatus_id = %d WHERE order_id = %d", $order_status, (int) $order_id ) );
					/* 6.0.2: the status is written directly, so its listeners hear the change ( a paid order goes to its fulfillment partners ). */
					if ( $order_id && $wpec_previous_status !== (int) $order_status ) {
						do_action( 'wpeasycart_order_status_update', (int) $order_id, (int) $order_status, $wpec_previous_status );
					}
				}
				$ec_db_admin->clear_tempcart( $GLOBALS['ec_cart_data']->ec_cart_id );
				$GLOBALS['ec_cart_data']->checkout_session_complete();
				$GLOBALS['ec_cart_data']->save_session_to_db();
				echo '<div class="wpeasycart-stripe-already-paid" style="position:fixed;top:0;left:0;width:100%;height:100%;z-index:999999;background:rgba(0,0,0,.8);">';
					echo '<div class="wpeasycart-stripe-already-paid-container" style="position:fixed; left:50%; top:50%; margin-left:-250px; margin-top:-80px; width:500px; max-width:100%; max-height:100%; background:#EFEFEF; padding:35px; border-radius:10px; text-align:center;">';
						echo '<div style="text-align:center; font-size:20px;" class="wpeasycart-stripe-already-paid-note">' . wp_easycart_language( )->get_text( 'cart_payment_information', 'payment_processed' ) . '</div>';
						echo '<div style="text-align:center; padding-top:20px;" class="wpeasycart-stripe-already-paid-button-row"><a href="' . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_success', array( 'order_id' => (int) $order_id ) ) ) . '">' . wp_easycart_language( )->get_text( 'cart_payment_information', 'payment_processed_view' ) . '</a></div>';
					echo '</div>';
				echo '</div>';
				return;

			} else if ( apply_filters( 'wp_easycart_stripe_return_listed_requires_action', true ) && $stripe_pi_response && ( $stripe_pi_response->status == 'requires_action' || $stripe_pi_response->status == 'requires_source_action' ) ) {
				if ( isset( $stripe_pi_response->next_action ) && isset( $stripe_pi_response->next_action->type ) && 'redirect_to_url' == $stripe_pi_response->next_action->type ) {
					echo '<div class="wpeasycart-stripe-already-paid" style="position:fixed;top:0;left:0;width:100%;height:100%;z-index:999999;background:rgba(0,0,0,.8);">';
						echo '<div class="wpeasycart-stripe-already-paid-container" style="position:fixed; left:50%; top:50%; margin-left:-250px; margin-top:-80px; width:500px; max-width:100%; max-height:100%; background:#EFEFEF; padding:35px; border-radius:10px; text-align:center;">Just a moment, please wait.</div>';
					echo '</div>';
					echo '<script>window.location.href = "' . wp_json_encode( esc_url_raw( $stripe_pi_response->next_action->redirect_to_url->url ) ) . '";</script>';

				} else if ( isset( $stripe_pi_response->next_source_action ) && isset( $stripe_pi_response->next_source_action->type ) && 'authorize_with_url' == $stripe_pi_response->next_source_action->type ) {
					echo '<div class="wpeasycart-stripe-already-paid" style="position:fixed;top:0;left:0;width:100%;height:100%;z-index:999999;background:rgba(0,0,0,.8);">';
						echo '<div class="wpeasycart-stripe-already-paid-container" style="position:fixed; left:50%; top:50%; margin-left:-250px; margin-top:-80px; width:500px; max-width:100%; max-height:100%; background:#EFEFEF; padding:35px; border-radius:10px; text-align:center;">Just a moment, please wait.</div>';
					echo '</div>';
					echo '<script>window.location.href = "' . wp_json_encode( esc_url_raw( $stripe_pi_response->next_source_action->authorize_with_url->url ) ) . '";</script>';

				} else if ( isset( $stripe_pi_response->next_action ) && isset( $stripe_pi_response->next_action->type ) && 'use_stripe_sdk' == $stripe_pi_response->next_action->type ) {
					if ( get_option( 'ec_option_payment_process_method' ) == 'stripe' ) {
						$pkey = get_option( 'ec_option_stripe_public_api_key' );
					} else if ( get_option( 'ec_option_payment_process_method' ) == 'stripe_connect' && get_option( 'ec_option_stripe_connect_use_sandbox' ) ) {
						$pkey = get_option( 'ec_option_stripe_connect_sandbox_publishable_key' );
					} else {
						$pkey = get_option( 'ec_option_stripe_connect_production_publishable_key' );
					}
					$pkey = apply_filters( 'wp_easycart_stripe_connect_publishable_key', $pkey );
					echo '<script>
					try {
						var wpec_stripe = ( "undefined" !== typeof stripe ) ? stripe : Stripe( "' . esc_attr( $pkey ) . '" ); /* 6.0.2: the page may not have made one yet */
						wpec_stripe.handleNextAction( {
							clientSecret: "' . esc_attr( $stripe_pi_response->client_secret ) . '"
						} ).then( function( result ) {
							if ( result.error ) {
								alert( "There is a problem handling your payment: " + result.error.message + ". Contact Support for assistance." );
							} else {
								window.location.reload(); /* confirmed: this page finishes the order */
							}
						} );
					} catch( err ) {
						alert( "Your WP EasyCart with Stripe has a problem: " + err.message + ". Contact WP EasyCart for assistance." );
					}
					</script>';
				} else if ( isset( $stripe_pi_response->next_action ) && isset( $stripe_pi_response->next_action->type ) && 'alipay_handle_redirect' == $stripe_pi_response->next_action->type ) {
					/* Handle Later? */
				} else if ( isset( $stripe_pi_response->next_action ) && isset( $stripe_pi_response->next_action->type ) && 'oxxo_display_details' == $stripe_pi_response->next_action->type ) {
					/* Handle Later? */
				}
				return;
			}
		} else if ( isset( $stripe_pi_response ) && ( $stripe_pi_response->status == 'requires_confirmation' || $stripe_pi_response->status == 'pending' ) ) {
			echo '<div class="wpeasycart-stripe-already-paid" style="position:fixed;top:0;left:0;width:100%;height:100%;z-index:999999;background:rgba(0,0,0,.8);">';
				echo '<div class="wpeasycart-stripe-already-paid-container" style="position:fixed; left:50%; top:50%; margin-left:-250px; margin-top:-80px; width:500px; max-width:100%; max-height:100%; background:#EFEFEF; padding:35px; border-radius:10px; text-align:center;">Just a moment, please wait.</div>';
			echo '</div>';
			echo '<script>
				ec_stripe_check_order_status( "' . esc_attr( $stripe_pi_response->id ) . '", "' . esc_attr( wp_create_nonce( 'wp-easycart-create-stripe-ideal-order-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ) . '" );
			</script>';
			return;
		}

		if ( get_option( 'ec_option_googleanalyticsid' ) != "UA-XXXXXXX-X" && get_option( 'ec_option_googleanalyticsid' ) != "" ) {
			echo "<script>
			(function(i,s,o,g,r,a,m) {i['GoogleAnalyticsObject']=r;i[r]=i[r]||function() {
			(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
			m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
			})(window,document,'script','//www.google-analytics.com/analytics.js','ga');
			ga('create', '" . esc_attr( get_option( 'ec_option_googleanalyticsid' ) ) . "', 'auto');
			ga('send', 'pageview');
			ga('require', 'ec');
			function ec_google_removeFromCart( model_number, title, quantity, price ) {
			  ga('ec:addProduct', {
				'id': model_number,
				'name': title,
				'price': price,
				'quantity': quantity
			  });
			  ga('ec:setAction', 'remove');
			  ga('send', 'event', 'UX', 'click', 'remove from cart');     // Send data using an event.
			}";

			// Setup Cart
			for( $i=0; $i < count( $this->cart->cart ); $i++ ) {
				echo "
				ga( 'ec:addProduct', {
				  'id': '" . esc_js( $this->cart->cart[$i]->model_number ) . "',
				  'name': '" . esc_js( $this->cart->cart[$i]->title ) . "',
				  'price': '" . esc_js( $this->cart->cart[$i]->unit_price ) . "',
				  'quantity': '" . esc_js( $this->cart->cart[$i]->quantity ) . "'
				});";
			}

			// View of Cart
			if ( !isset( $_GET['ec_page'] )  ) {
				echo "
				ga('ec:setAction','checkout', {
					'step': 1,
					'option': 'Cart View'
				});
				ga('send', 'pageview');";

			// View of Checkout Info
			} else if ( isset( $_GET['ec_page'] ) && $_GET['ec_page'] == "checkout_info" ) {
				echo "
				ga('ec:setAction','checkout', {
					'step': 2,
					'option': 'Checkout Info'
				});
				ga('send', 'pageview');";

			// View of Payment Method
			} else if ( isset( $_GET['ec_page'] ) && $_GET['ec_page'] == "checkout_payment" ) {
				echo "
				ga('ec:setAction','checkout', {
					'step': 3,
					'option': 'Checkout Payment'
				});
				ga('send', 'pageview');";

			// View of thankyou page
			} else if ( isset( $_GET['ec_page'] ) && $_GET['ec_page'] == "checkout_success" ) {
				echo "
				ga('ec:setAction','checkout', {
					'step': 4,
					'option': 'Checkout Success'
				});
				ga('send', 'pageview');";

			}

			echo "</script>";
		}

		if ( '' != get_option( 'ec_option_google_ga4_property_id' ) && ! wp_easycart_onepage_active() ) {
			$ga4_event = false;
			if ( ! isset( $_GET['ec_page'] ) ) {
				$ga4_event = 'view_cart';
			} else if ( isset( $_GET['ec_page'] ) && $_GET['ec_page'] == "checkout_info" ) {
				$ga4_event = 'begin_checkout';
			} else if ( isset( $_GET['ec_page'] ) && $_GET['ec_page'] == "checkout_shipping" ) {
				$ga4_event = 'add_shipping_info';
			} else if ( isset( $_GET['ec_page'] ) && $_GET['ec_page'] == "checkout_payment" ) {
				$ga4_event = 'add_payment_info';
			}

			if ( $ga4_event ) {
				if ( get_option( 'ec_option_google_ga4_tag_manager' ) ) {
					echo '<script>
					jQuery( document ).ready( function() {
						dataLayer.push( { ecommerce: null } );
						dataLayer.push( {
							event: "' . esc_attr( $ga4_event ) . '",
							ecommerce: {
								currency: "' . esc_attr( wp_easycart_base_currency_code() ) . '",
								value: ' . esc_attr( number_format( $this->order_totals->grand_total, 2, '.', '' ) ) . ',
								coupon_code: "' . esc_attr( $this->coupon_code ) . '",';
					if ( 'add_shipping_info' == $ga4_event ) {
						echo '
								shipping_tier: "' . esc_attr( trim( strip_tags( $this->shipping->get_selected_shipping_method() ) ) ) . '",';
					}
					echo '
								items: [ ';
								for( $i=0; $i < count( $this->cart->cart ); $i++ ) {
									echo '{
										item_id: "' . esc_attr( $this->cart->cart[$i]->model_number ) . '",
										item_name: "' . esc_attr( $this->cart->cart[$i]->title ) . '",
										index: ' . esc_attr( $i ) . ',
										price: ' . esc_attr( number_format( $this->cart->cart[$i]->unit_price, 2, '.', '' ) ) . ',
										item_brand: "' . esc_attr( $this->cart->cart[$i]->manufacturer_name ) . '",
										quantity: ' . esc_attr( number_format( $this->cart->cart[$i]->quantity, 2, '.', '' ) ) . '
									}, ';
								}
								echo ' ]
							}
						} );
					} );
					</script>';
				} else {
					echo '<script>
					jQuery( document ).ready( function() {
						gtag( "event", "' . esc_attr( $ga4_event ) . '", {
							currency: "' . esc_attr( wp_easycart_base_currency_code() ) . '",
							value: ' . esc_attr( number_format( $this->order_totals->grand_total, 2, '.', '' ) ) . ',
							coupon_code: "' . esc_attr( $this->coupon_code ) . '",';
					if ( 'add_shipping_info' == $ga4_event ) {
					echo '
							shipping_tier: "' . esc_attr( trim( strip_tags( $this->shipping->get_selected_shipping_method() ) ) ) . '",';
					}
					echo '
							items: [ ';
							for( $i=0; $i < count( $this->cart->cart ); $i++ ) {
								echo '{
									item_id: "' . esc_attr( $this->cart->cart[$i]->model_number ) . '",
									item_name: "' . esc_attr( $this->cart->cart[$i]->title ) . '",
									index: ' . esc_attr( $i ) . ',
									price: ' . esc_attr( number_format( $this->cart->cart[$i]->unit_price, 2, '.', '' ) ) . ',
									item_brand: "' . esc_attr( $this->cart->cart[$i]->manufacturer_name ) . '",
									quantity: ' . esc_attr( number_format( $this->cart->cart[$i]->quantity, 2, '.', '' ) ) . '
								}, ';
							}
							echo ' ]
						} );
					} );
					</script>';
				}
			}
		}

		echo "<div class=\"ec_cart_page\">";
		if ( isset( $_GET['ec_page'] ) && $_GET['ec_page'] == "checkout_success" ) {
			do_action( 'wpeasycart_order_success' );
			$order_id = (int) $_GET['order_id'];
			if ( $GLOBALS['ec_cart_data']->cart_data->is_guest ) {
				$order_row = $this->mysqli->get_guest_order_row( $order_id, $GLOBALS['ec_cart_data']->cart_data->guest_key );
			} else {
				$order_row = $this->mysqli->get_order_row( $order_id, $GLOBALS['ec_cart_data']->cart_data->user_id );
			}
			$order = new ec_orderdisplay( $order_row, true );

			if ( $GLOBALS['ec_cart_data']->cart_data->guest_key != "" ) {
				$order_details = $this->mysqli->get_guest_order_details( $order_id, $GLOBALS['ec_cart_data']->cart_data->guest_key );

			} else {
				$order_details = $this->mysqli->get_order_details( $order_id, $GLOBALS['ec_cart_data']->cart_data->user_id );
			}

			$GLOBALS['ec_user']->setup_billing_info_data( $order->billing_first_name, $order->billing_last_name, $order->billing_address_line_1, $order->billing_address_line_2, $order->billing_city, $order->billing_state, $order->billing_country, $order->billing_zip, $order->billing_phone, $order->billing_company_name );

			$GLOBALS['ec_user']->setup_shipping_info_data( $order->shipping_first_name, $order->shipping_last_name, $order->shipping_address_line_1, $order->shipping_address_line_2, $order->shipping_city, $order->shipping_state, $order->shipping_country, $order->shipping_zip, $order->shipping_phone, $order->shipping_company_name );

			$tax_struct = $this->tax;

			$total = $GLOBALS['currency']->get_currency_display( $order->grand_total );
			$subtotal = $GLOBALS['currency']->get_currency_display( $order->sub_total );
			$tax = $GLOBALS['currency']->get_currency_display( $order->tax_total );
			$duty = $GLOBALS['currency']->get_currency_display( $order->duty_total );
			$vat = $GLOBALS['currency']->get_currency_display( $order->vat_total );
			if ( ( $order->grand_total - $order->vat_total ) > 0 ) {
				$vat_rate = number_format( $this->tax->vat_rate, 0, '', '' );
			} else {
				$vat_rate = number_format( 0, 0, '', '' );
			}
			$shipping = $GLOBALS['currency']->get_currency_display( $order->shipping_total );
			$discount = $GLOBALS['currency']->get_currency_display( $order->discount_total );

			//google analytics
			$this->analytics = new ec_googleanalytics($order_details, $order->shipping_total, $order->tax_total , $order->grand_total, $order_id);
			$google_urchin_code = get_option('ec_option_googleanalyticsid');
			$google_wp_url = sanitize_text_field( $_SERVER['SERVER_NAME'] );
			//end google analytics
			$this->display_cart_error();

			//Backwards compatibility for an error... Don't want the button showing if user didn't create an account.
			if ( $GLOBALS['ec_cart_data']->cart_data->is_guest != "" && $GLOBALS['ec_cart_data']->cart_data->is_guest )
				$GLOBALS['ec_cart_data']->cart_data->email = "guest";

			if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_success.php' ) )	
				include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_success.php' );
			else
				include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_success.php' );

		} else if ( isset( $_GET['ec_page'] ) && $_GET['ec_page'] == "third_party" ) {
			$order_id = (int) $_GET['order_id'];

			if ( $GLOBALS['ec_cart_data']->cart_data->is_guest != "" && $GLOBALS['ec_cart_data']->cart_data->is_guest ) {
				$order = $this->mysqli->get_guest_order_row( $order_id, $GLOBALS['ec_cart_data']->cart_data->guest_key );
				$order_details = $this->mysqli->get_guest_order_details( $this->order_id, $GLOBALS['ec_cart_data']->cart_data->guest_key );
			} else {
				$order = $this->mysqli->get_order_row( $order_id, $GLOBALS['ec_cart_data']->cart_data->user_id );
				$order_details = $this->mysqli->get_order_details( $this->order_id, $GLOBALS['ec_cart_data']->cart_data->user_id );
			}

			//google analytics
			$this->analytics = new ec_googleanalytics($order_details, $order->shipping_total, $order->tax_total , $order->grand_total, $order_id);
			$google_urchin_code = get_option('ec_option_googleanalyticsid');
			$google_wp_url = sanitize_text_field( $_SERVER['SERVER_NAME'] );
			//end google analytics
			if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_third_party.php' ) )	
				include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_third_party.php' );
			else
				include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_third_party.php' );

		} else if ( isset( $_GET['ec_page'] ) && $_GET['ec_page'] == "subscription_info" ) {

			$this->display_subscription_page();

		} else if ( isset( $_GET['ec_page'] ) && $_GET['ec_page'] == "invoice" ) {
			/* 6.0.2: an unpaid order's pay link ( order id + key, Stripe, Square, PayPal, bank transfer ): wp_easycart_order_pay. */
			if ( class_exists( 'wp_easycart_order_pay' ) ) {
				wp_easycart_order_pay::render_page();
			}

		} else {
			if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_page.php' ) )	
				include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_page.php' );
			else
				include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_page.php' );
		}
		echo "</div>";
	}

	public function display_subscription_page( $subscription_product_id = false ) {
		$this->display_cart_error();

		$subscription_found = false;

		if ( isset( $_GET['subscription'] ) || $subscription_product_id ) {

			wpeasycart_session()->handle_session();

			global $wpdb;
			$subscription_cart = array();
			$model_number = ( isset( $_GET['subscription'] ) ) ? sanitize_text_field( $_GET['subscription'] ) : '';
			if ( $subscription_product_id ) {
				$products = $this->mysqli->get_product_list( $wpdb->prepare( " WHERE product.product_id = %d", $subscription_product_id ), "", "", "" );
			} else {
				$products = $this->mysqli->get_product_list( $wpdb->prepare( " WHERE product.model_number = %s", $model_number ), "", "", "" );
			}

			if ( count( $products ) > 0 && ! $products[0]['allow_multiple_subscription_purchases'] && $GLOBALS['ec_user']->has_active_subscription( $products[0]['product_id'] ) ) {
				echo '<div class="ec_subscription_purchased">' . wp_easycart_language()->get_text( 'cart_login', 'cart_subscription_already_purchased' ) . '</div>';
				return;
			}

			/* 6.0.2: subscriptions are billed through Stripe only. With another gateway, or Stripe not connected, the form
			 * below could not charge anyone: say so instead ( before the template, so theme copies of it get this too ). */
			if ( count( $products ) > 0 && class_exists( 'wp_easycart_subscription_gateway' ) && ! wp_easycart_subscription_gateway::ready() ) {
				wp_easycart_subscription_gateway::print_unavailable_page();
				return;
			}

			if ( count( $products ) > 0 ) {
				$model_number = $products[0]['model_number'];
				$subscription_found = true;
				$product = new ec_product( $products[0], 0, 1, 0 );
				$this->cart->cart = array( $product );

				if ( !get_option( 'ec_option_subscription_one_only' ) && $GLOBALS['ec_cart_data']->cart_data->subscription_quantity != "" ) { 
					$subscription_quantity = $GLOBALS['ec_cart_data']->cart_data->subscription_quantity;
				} else { 
					$subscription_quantity = 1; 
				}

				// Get option item price adjustments
				$option_promotion_multiplier = 1;
				$option_promotion_discount = 0;
				$promotions = $GLOBALS['ec_promotions']->promotions;
				for( $i=0; $i<count( $promotions ); $i++ ) {
					if ( $product->promotion_text == $promotions[$i]->promotion_name ) {
						if ( $promotions[$i]->price1 == 0 ) {
							$option_promotion_multiplier = round( $promotions[$i]->percentage1 / 100, 2 );
						} else if ( $promotions[$i]->price1 != 0 ) {
							$option_promotion_discount = $promotions[$i]->price1;
						}
					}
				}

				$option_total = 0;
				$option_total_onetime = 0;
				$option_weight = 0;
				$option_weight_onetime = 0;
				if ( $this->subscription_option1 != 0 ) {
					$subscription_option1 = $GLOBALS['ec_options']->get_optionitem( $this->subscription_option1 );
					if ( $subscription_option1->optionitem_price > 0 ) {
						$option_total += $subscription_option1->optionitem_price;
						$subscription_cart[] = (object) array(
							'vat_enabled' => ( $product->vat_rate != 0 ),
							'is_taxable' => $product->is_taxable,
							'item_total' => round( $subscription_option1->optionitem_price * $subscription_quantity, 2 ),
							'item_discount' => 0,
						);
					}
					if ( $subscription_option1->optionitem_weight > 0 ) {
						$option_weight += $subscription_option1->optionitem_weight;
					}
				}
				if ( $this->subscription_option2 != 0 ) {
					$subscription_option2 = $GLOBALS['ec_options']->get_optionitem( $this->subscription_option2 );
					if ( $subscription_option2->optionitem_price > 0 ) {
						$option_total += $subscription_option2->optionitem_price;
						$subscription_cart[] = (object) array(
							'vat_enabled' => ( $product->vat_rate != 0 ),
							'is_taxable' => $product->is_taxable,
							'item_total' => round( $subscription_option2->optionitem_price * $subscription_quantity, 2 ),
							'item_discount' => 0,
						);
					}
					if ( $subscription_option2->optionitem_weight > 0 ) {
						$option_weight += $subscription_option2->optionitem_weight;
					}
				}
				if ( $this->subscription_option3 != 0 ) {
					$subscription_option3 = $GLOBALS['ec_options']->get_optionitem( $this->subscription_option3 );
					if ( $subscription_option3->optionitem_price > 0 ) {
						$option_total += $subscription_option3->optionitem_price;
						$subscription_cart[] = (object) array(
							'vat_enabled' => ( $product->vat_rate != 0 ),
							'is_taxable' => $product->is_taxable,
							'item_total' => round( $subscription_option3->optionitem_price * $subscription_quantity, 2 ),
							'item_discount' => 0,
						);
					}
					if ( $subscription_option3->optionitem_weight > 0 ) {
						$option_weight += $subscription_option3->optionitem_weight;
					}
				}
				if ( $this->subscription_option4 != 0 ) {
					$subscription_option4 = $GLOBALS['ec_options']->get_optionitem( $this->subscription_option4 );
					if ( $subscription_option4->optionitem_price > 0 ) {
						$option_total += $subscription_option4->optionitem_price;
						$subscription_cart[] = (object) array(
							'vat_enabled' => ( $product->vat_rate != 0 ),
							'is_taxable' => $product->is_taxable,
							'item_total' => round( $subscription_option4->optionitem_price * $subscription_quantity, 2 ),
							'item_discount' => 0,
						);
					}
					if ( $subscription_option4->optionitem_weight > 0 ) {
						$option_weight += $subscription_option4->optionitem_weight;
					}
				}
				if ( $this->subscription_option5 != 0 ) {
					$subscription_option5 = $GLOBALS['ec_options']->get_optionitem( $this->subscription_option5 );
					if ( $subscription_option5->optionitem_price > 0 ) {
						$option_total += $subscription_option5->optionitem_price;
						$subscription_cart[] = (object) array(
							'vat_enabled' => ( $product->vat_rate != 0 ),
							'is_taxable' => $product->is_taxable,
							'item_total' => round( $subscription_option5->optionitem_price * $subscription_quantity, 2 ),
							'item_discount' => 0,
						);
					}
					if ( $subscription_option5->optionitem_weight > 0 ) {
						$option_weight += $subscription_option5->optionitem_weight;
					}
				}
				if ( $this->subscription_advanced_options ) {
					foreach( $this->subscription_advanced_options as $option ) {
						$optionitem = $GLOBALS['ec_options']->get_optionitem( $option['optionitem_id'] );
						if ( $optionitem->optionitem_disallow_shipping ) {
							$product->is_shippable = false;
						}
						if ( $optionitem && $optionitem->optionitem_price > 0 ) {
							if ( 'number' == $option['option_type'] ) {
								$option_total += ( $optionitem->optionitem_price * (int) $option['optionitem_value'] );
								$subscription_cart[] = (object) array(
									'vat_enabled' => ( $product->vat_rate != 0 ),
									'is_taxable' => $product->is_taxable,
									'item_total' => round( ( $optionitem->optionitem_price * (int) $option['optionitem_value'] ) * $subscription_quantity, 2 ),
									'item_discount' => 0,
								);
							} else {
								$option_total += $optionitem->optionitem_price;
								$subscription_cart[] = (object) array(
									'vat_enabled' => ( $product->vat_rate != 0 ),
									'is_taxable' => $product->is_taxable,
									'item_total' => round( $optionitem->optionitem_price * $subscription_quantity, 2 ),
									'item_discount' => 0,
								);
							}
						} else if ( $optionitem && $optionitem->optionitem_price_onetime > 0 ) {
							if ( 'number' == $option['option_type'] ) {
								$option_total_onetime += ( $optionitem->optionitem_price_onetime * (int) $option['optionitem_value'] );
								$subscription_cart[] = (object) array(
									'vat_enabled' => ( $product->vat_rate != 0 ),
									'is_taxable' => $product->is_taxable,
									'item_total' => round( ( $optionitem->optionitem_price_onetime * (int) $option['optionitem_value'] ), 2 ),
									'item_discount' => 0,
								);
							} else {
								$option_total_onetime += $optionitem->optionitem_price_onetime;
								$subscription_cart[] = (object) array(
									'vat_enabled' => ( $product->vat_rate != 0 ),
									'is_taxable' => $product->is_taxable,
									'item_total' => round( $optionitem->optionitem_price_onetime, 2 ),
									'item_discount' => 0,
								);
							}
						} else if ( $optionitem && $optionitem->optionitem_price_override > -1 ) {
							$product->price = $optionitem->optionitem_price_override;
						}
						if ( $optionitem && $optionitem->optionitem_weight > 0 ) {
							if ( 'number' == $option['option_type'] ) {
								$option_weight += ( $optionitem->optionitem_weight * (int) $option['optionitem_value'] );
							} else {
								$option_weight += $optionitem->optionitem_weight;
							}
						} else if ( $optionitem && $optionitem->optionitem_weight_onetime > 0 ) {
							if ( 'number' == $option['option_type'] ) {
								$option_weight_onetime += ( $optionitem->optionitem_weight_onetime * (int) $option['optionitem_value'] );
							} else {
								$option_weight_onetime += $optionitem->optionitem_weight_onetime;
							}
						} else if ( $optionitem && $optionitem->optionitem_weight_override > -1 ) {
							$product->weight = $optionitem->optionitem_weight_override;
						}
					}
				}

				$subscription_cart[] = (object) array(
					'vat_enabled' => ( $product->vat_rate != 0 ),
					'is_taxable' => $product->is_taxable,
					'item_total' => round( $product->price * $subscription_quantity, 2 ),
					'item_discount' => 0,
				);

				if ( get_option( 'ec_option_collect_shipping_for_subscriptions' ) && get_option( 'ec_option_use_shipping' ) && $product->is_shippable ) {
					$ship_price_total = ( $product->price + $option_total ) * $subscription_quantity + $option_total_onetime;
					$ship_weight_total = ( $product->weight + $option_weight ) * $subscription_quantity + $option_weight_onetime;
					$ship_quantity = $subscription_quantity;
				} else {
					$ship_price_total = 0;
					$ship_weight_total = 0;
					$ship_quantity = 0;
				}

				$product->weight = $ship_weight_total;
				do_action( 'wpeasycart_cart_subscription_updated', $product, $subscription_quantity, $ship_weight_total ); /* 6.0.2: the total weight, so live rates stop multiplying it by the quantity again */

				if ( get_option( 'ec_option_collect_shipping_for_subscriptions' ) && get_option( 'ec_option_use_shipping' ) && $product->is_shippable ) {
					$this->shipping = new ec_shipping( $ship_price_total, $ship_weight_total, $ship_quantity, 'RADIO', $GLOBALS['ec_user']->freeshipping, $product->length, $product->width, $product->height * $ship_quantity, array( $product ) );
					$this->shipping->change_shipping_js_func = 'ec_cart_subscription_shipping_method_change';
					$this->cart->shippable_total_items = $subscription_quantity;
					$handling_total = $product->handling_price + ( $product->handling_price_each * $subscription_quantity );
					$shipping_total = floatval( $this->shipping->get_shipping_price( $handling_total ) );
					$shipping_total = floatval( $this->shipping->get_shipping_price( $handling_total ) );
					$subscription_cart[] = (object) array(
						'vat_enabled' => ! get_option( 'ec_option_no_vat_on_shipping' ),
						'is_taxable' => get_option( 'ec_option_collect_tax_on_shipping' ),
						'item_total' => round( $shipping_total, 2 ),
						'item_discount' => 0,
					);
				} else {
					$handling_total = 0;
					$shipping_total = 0;
				}

				// get discount amount
				$discount_amount = 0;
				$is_dollar_discount = false;
				/* 6.0.2: a code another system answers for ( PRO: an Offers code that discounts subscriptions ), also when the
				 * legacy coupon can't be used ( a converted coupon keeps an expired legacy row ). */
				if ( '' != $this->coupon_code ) {
					$wpec_subscription_coupon = $GLOBALS['ec_coupons']->subscription_coupon( $this->coupon_code, $product->product_id );
					if ( $wpec_subscription_coupon && ! empty( $wpec_subscription_coupon->is_offer ) ) {
						$this->coupon = $wpec_subscription_coupon;
					}
				}
				if ( isset( $this->coupon ) ) { // Invalid Coupon
					$is_valid_coupon = false;
					if ( $this->coupon->by_product_id ) { // validate product id match
						if ( $this->coupon->product_id == $product->product_id ) {
							$is_valid_coupon = true;
						}
					} else if ( $this->coupon->by_manufacturer_id ) { // validate manufacturer id match
						if ( $this->coupon->manufacturer_id == $product->manufacturer_id ) {
							$is_valid_coupon = true;
						}
					} else if ( $this->coupon->by_category_id ) { // validate category id match
						if ( $has_categories = $wpdb->get_results( $wpdb->prepare( "SELECT categoryitem_id FROM ec_categoryitem WHERE category_id = %d AND product_id = %d", $this->coupon->category_id, $product->product_id ) ) ) {
							$is_valid_coupon = true;
						}
					} else {
						$is_valid_coupon = true;
					}
					/* 6.0.2: the rule both Stripe paths use ( subscription_checkout_coupon() ): an expired or used-up code ( one can
					 * stay in the session from the cart's code box or an ?ec_coupon link ), or one that takes no percent or amount
					 * off ( shipping, free item ), gets nothing here either, so the page never shows a discount Stripe won't give. */
					if ( $is_valid_coupon && ( ! empty( $this->coupon->coupon_expired ) || ( isset( $this->coupon->max_redemptions ) && 999 != $this->coupon->max_redemptions && $this->coupon->times_redeemed >= $this->coupon->max_redemptions ) || ! ( ( ! empty( $this->coupon->is_percentage_based ) && (float) $this->coupon->promo_percentage > 0 ) || ( ! empty( $this->coupon->is_dollar_based ) && (float) $this->coupon->promo_dollar > 0 ) ) ) ) {
						$is_valid_coupon = false;
					}
					if ( $is_valid_coupon ) {
						/* 6.0.2: the template's success message reads the regular cart's match count, which never holds the
						 * subscription ( a coupon made for this product said it did not apply ). */
						if ( isset( $this->discount ) && is_object( $this->discount ) ) {
							$this->discount->coupon_matches = max( 1, (int) $this->discount->coupon_matches );
						}
						if ( $this->coupon->is_percentage_based ) {
							$coupon_percentage = round( ( $this->coupon->promo_percentage / 100 ), 2  );
							for ( $i = 0; $i < count( $subscription_cart ); $i++ ) {
								$subscription_cart[ $i ]->item_discount = round( (float) $subscription_cart[ $i ]->item_total * $coupon_percentage, 2 );
								$discount_amount += $subscription_cart[ $i ]->item_discount;
							}
						} else if ( $this->coupon->is_dollar_based ) {
							$is_dollar_discount = true;
							$discount_amount = $this->coupon->promo_dollar;
						}
						if ( $discount_amount > ( $product->price + $option_total ) * $subscription_quantity + $option_total_onetime + $shipping_total ) {
							$discount_amount = ( $product->price + $option_total ) * $subscription_quantity + $option_total_onetime + $shipping_total;
						}
						$discount_amount = round( $discount_amount, 2 );
					} else {
						unset( $this->coupon );
					}
				} else if ( $option_promotion_multiplier < 1 ) {
					for ( $i = 0; $i < count( $subscription_cart ); $i++ ) {
						$subscription_cart[ $i ]->item_discount = round( (float) $subscription_cart[ $i ]->item_total * $option_promotion_multiplier, 2 );
						$discount_amount += $subscription_cart[ $i ]->item_discount;
					}
				} else if ( $option_promotion_discount > 0 ) {
					$is_dollar_discount = true;
					$discount_amount = round( $option_promotion_discount, 2 );
				}

				do_action( 'wpeasycart_cart_subscription_pre_tax', $product, $subscription_quantity, $shipping_total, $handling_total, $discount_amount );

				wpeasycart_taxcloud()->setup_subscription_for_tax( $product, $subscription_quantity, $discount_amount, $option_total, $option_total_onetime );
				if ( function_exists( 'wpeasycart_taxjar' ) ) {
					wpeasycart_taxjar()->setup_subscription_for_tax( $product, $subscription_quantity, $discount_amount, $option_total, $option_total_onetime );
				}

				$sub_total = ( ( $product->price + $option_total ) * $subscription_quantity ) + $option_total_onetime;
				if ( $is_dollar_discount ) {
					for ( $i = 0; $i < count( $subscription_cart ); $i++ ) {
						$subscription_cart[$i]->item_total = $subscription_cart[$i]->item_total - round( ( $subscription_cart[$i]->item_total / ( $sub_total + $shipping_total ) ) * $discount_amount, 2 );
					}
				} else {
					for ( $i = 0; $i < count( $subscription_cart ); $i++ ) {
						$subscription_cart[$i]->item_total = $subscription_cart[$i]->item_total - $subscription_cart[$i]->item_discount;
					}
				}

				$tax_subtotal = ( $product->is_taxable ) ? $sub_total - ( $product->subscription_signup_fee * $subscription_quantity ) : 0;
				$vat_subtotal = ( $product->vat_rate > 0 ) ? $sub_total - ( $product->subscription_signup_fee * $subscription_quantity ) : 0;
				$ec_tax = new ec_tax( $sub_total, $tax_subtotal, $vat_subtotal, ( $GLOBALS['ec_cart_data']->cart_data->shipping_state ) ? $GLOBALS['ec_cart_data']->cart_data->shipping_state : $GLOBALS['ec_user']->shipping->state, ( $GLOBALS['ec_cart_data']->cart_data->shipping_country ) ? $GLOBALS['ec_cart_data']->cart_data->shipping_country : $GLOBALS['ec_user']->shipping->country, $GLOBALS['ec_user']->taxfree, 0, $subscription_cart, true );

				$tax_total = round( $ec_tax->tax_total, 2 );
				$vat_rate = $ec_tax->vat_rate;
				$vat_total = round( $ec_tax->vat_total, 2 );

				$hst_total = round( $ec_tax->hst, 2 );
				$pst_total = round( $ec_tax->pst, 2 );
				$gst_total = round( $ec_tax->gst, 2 );

				$hst_rate = $ec_tax->hst_rate;
				$pst_rate = $ec_tax->pst_rate;
				$gst_rate = $ec_tax->gst_rate;

				if ( $product->trial_period_days > 0 ) {
					$grand_total = ( ( $product->subscription_signup_fee ) * $subscription_quantity );
				} else if ( $ec_tax->vat_included ) {
					$grand_total = ( ( $product->price + $option_total + $product->subscription_signup_fee ) * $subscription_quantity ) + $option_total_onetime - $discount_amount + $tax_total + $hst_total + $gst_total + $pst_total + $shipping_total;
				} else {
					$grand_total = ( ( $product->price + $option_total + $product->subscription_signup_fee ) * $subscription_quantity ) + $option_total_onetime - $discount_amount + $vat_total + $tax_total + $hst_total + $pst_total + $gst_total + $shipping_total;
				}

				if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_subscription.php' ) ) {
					include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_subscription.php' );
				} else {
					include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_subscription.php' );
				}
			}
		}
	}

	public function display_cart_process() {
		if (	$this->cart->total_items > 0 || ( isset( $_GET['ec_page'] ) && $_GET['ec_page'] == "checkout_success" ) ) {
			if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_process.php' ) )	
				include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_process.php' );
			else
				include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_process.php' );
		}
	}

	public function display_cart_process_cart_link( $link_text ) {
		if ( isset( $_GET['ec_page'] ) && $_GET['ec_page'] == "checkout_success" ) {
			echo esc_attr( $link_text );
		} else {
			echo "<a href=\"" . esc_attr( $this->cart_page ) . "\" class=\"ec_process_bar_link\">" . esc_attr( $link_text ) . "</a>";
		}
	}

	public function display_cart_process_shipping_link( $link_text ) {
		if ( isset( $_GET['ec_page'] ) && $_GET['ec_page'] == "checkout_success" ) {
			echo esc_attr( $link_text );
		} else {
			echo "<a href=\"" . esc_url( wpeasycart_links()->get_cart_page( 'checkout_info' ) ) . "\" class=\"ec_process_bar_link\">" . esc_attr( $link_text ) . "</a>";
		}
	}

	public function display_cart_process_review_link( $link_text ) {
		if ( isset( $_GET['ec_page'] ) && $_GET['ec_page'] == "checkout_success" ) {
			echo esc_attr( $link_text );
		} else if ( $GLOBALS['ec_cart_data']->cart_data->billing_first_name != "" ) {
			echo "<a href=\"" . esc_url( wpeasycart_links()->get_cart_page( 'checkout_payment' ) ) . "\" class=\"ec_process_bar_link\">" . esc_attr( $link_text ) . "</a>";
		} else {
			echo esc_attr( $link_text );
		}
	}

	public function display_cart_dynamic( $cart_page, $success_code, $error_code ) {
		$ec_db = new ec_db();
		$cart_count = $ec_db->get_cart_count( $GLOBALS['ec_cart_data']->ec_cart_id );

		if ( $cart_count > 0 ) {
			if ( get_option( 'ec_option_payment_process_method' ) == 'stripe' || get_option( 'ec_option_payment_process_method' ) == 'stripe_connect' ) {
				if ( 'stripe' == get_option( 'ec_option_payment_process_method' ) ) {
					$stripe = new ec_stripe();
				} else {
					$stripe = new ec_stripe_connect();
				}

				$stripe_pi_response = $stripe->get_payment_intent( $GLOBALS['ec_cart_data']->cart_data->stripe_paymentintent_id );
				if ( $stripe_pi_response && in_array( $stripe_pi_response->status, array( 'succeeded', 'processing', 'requires_capture', 'canceled' ) ) ) {
					global $wpdb;
					$ec_db_admin = new ec_db_admin();
					$order = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM ec_order WHERE gateway_transaction_id = %s", $stripe_pi_response->id . ':' . $stripe_pi_response->client_secret ) );
					if ( $order ) {
						$order_id = $order->order_id;
					} else {
						/* 6.0.2: paid, but not this cart's total ( the cart changed after the payment ): on hold for the merchant, with no stock taken and no receipt. */
						$wpec_hold = 'canceled' != $stripe_pi_response->status && ! wp_easycart_stripe_intent_matches_cart( $stripe_pi_response, $this );
						$source = array(
							'id' => $stripe_pi_response->id,
							'client_secret' => $stripe_pi_response->client_secret,
						);
						$order_id = $this->insert_ideal_order( $source, $stripe_pi_response );
						$stripe->update_payment_intent_description( $stripe_pi_response->id, $order_id );

						global $wpdb;
						$order_status = 6;
						if ( $stripe_pi_response->status == 'succeeded' ) {
							$order_status = 3;
						} else if ( $stripe_pi_response->status == 'requires_capture' ) {
							$order_status = 12;
						} else if ( $stripe_pi_response->status == 'processing' ) {
							$order_status = 12;
						} else if ( $stripe_pi_response->status == 'canceled' ) {
							$order_status = 19;
						}
						if ( $wpec_hold && $order_id ) {
							$order_status = 12;
							/* translators: %s: Stripe payment id. */
							wp_easycart_stripe_hold_order( $order_id, sprintf( __( 'The Stripe payment %s does not match this order’s total ( the cart changed after it was paid ), so the order is on hold. Check the payment in Stripe before you ship it.', 'wp-easycart' ), $stripe_pi_response->id ) );
						}
						if ( $order_id && 19 !== (int) $order_status && class_exists( 'wp_easycart_shipping_groups' ) && wp_easycart_shipping_groups::hold_order( $order_id, $this ) ) {
							$order_status = 12; /* 6.0.2: paid, but a fulfillment partner's shipping could not be confirmed for this address */
						}
						$wpdb->get_row( $wpdb->prepare( "UPDATE ec_order SET orderstatus_id = %d WHERE order_id = %d", $order_status, (int) $order_id ) );

						// Maybe send email receipts
						if ( $order_status == 3 ) {
							$orderdetails = $ec_db_admin->get_order_details_admin( $order_id );

							/* Update Stock Quantity */
							foreach( $orderdetails as $orderdetail ) {
								$product = $wpdb->get_row( $wpdb->prepare( "SELECT ec_product.* FROM ec_product WHERE ec_product.product_id = %d", $orderdetail->product_id ) );
								if ( $product ) {
									if ( $product->use_optionitem_quantity_tracking ) {
										$ec_db_admin->update_quantity_value( $orderdetail->quantity, $orderdetail->product_id, $orderdetail->optionitem_id_1, $orderdetail->optionitem_id_2, $orderdetail->optionitem_id_3, $orderdetail->optionitem_id_4, $orderdetail->optionitem_id_5 );
									}
									$ec_db_admin->update_product_stock( $orderdetail->product_id, $orderdetail->quantity );
									$this->mysqli->update_details_stock_adjusted( $orderdetail->orderdetail_id );
									$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log( order_id, order_log_key ) VALUES( %d, "order-stock-update" )', $order_id ) );
									$order_log_id = $wpdb->insert_id;
									$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "product_id", %s )', $order_log_id, $order_id, $orderdetail->product_id ) );
									$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "quantity", %s )', $order_log_id, $order_id, '-' . $orderdetail->quantity ) );
								}
							}

							// Update Order Status/Send Alerts
							do_action( 'wpeasycart_order_paid', $order_id );

							// send email
							$order_row = $ec_db_admin->get_order_row_admin( $order_id );
							$order_display = new ec_orderdisplay( $order_row, true, true );
							$order_display->send_email_receipt();
							$order_display->send_gift_cards();
						}
					}
					$ec_db_admin->clear_tempcart( $GLOBALS['ec_cart_data']->ec_cart_id );
					$GLOBALS['ec_cart_data']->checkout_session_complete();
					$GLOBALS['ec_cart_data']->save_session_to_db();
					$wpeasycart_offer_prev_session_id = $GLOBALS['ec_cart_data']->ec_cart_id;
					wpeasycart_session()->rotate_session_id();
					if ( function_exists( 'wp_easycart_offers_active' ) && wp_easycart_offers_active() ) {
						ec_offer_integration::migrate_session_codes( $wpeasycart_offer_prev_session_id );
					}
					echo '<div class="wpeasycart-stripe-already-paid" style="position:fixed;top:0;left:0;width:100%;height:100%;z-index:999999;background:rgba(0,0,0,.8);">';
						echo '<div class="wpeasycart-stripe-already-paid-container" style="position:fixed; left:50%; top:50%; margin-left:-250px; margin-top:-80px; width:500px; max-width:100%; max-height:100%; background:#EFEFEF; padding:35px; border-radius:10px; text-align:center;">';
							echo '<div style="text-align:center; font-size:20px;" class="wpeasycart-stripe-already-paid-note">Your payment has been processed and you may view your order now</div>';
							echo '<div style="text-align:center; padding-top:20px;" class="wpeasycart-stripe-already-paid-button-row"><a href="' . esc_url( wpeasycart_links()->get_cart_page( 'checkout_success', array( 'order_id' => (int) $order_id ) ) ) . '">View Order</a></div>';
						echo '</div>';
					echo '</div>';
					return;
					
				} else if ( apply_filters( 'wp_easycart_stripe_return_listed_requires_action', true ) && $stripe_pi_response && ( $stripe_pi_response->status == 'requires_action' || $stripe_pi_response->status == 'requires_source_action' ) ) {
					if ( isset( $stripe_pi_response->next_action ) && isset( $stripe_pi_response->next_action->type ) && 'redirect_to_url' == $stripe_pi_response->next_action->type ) {
						echo '<div class="wpeasycart-stripe-already-paid" style="position:fixed;top:0;left:0;width:100%;height:100%;z-index:999999;background:rgba(0,0,0,.8);">';
							echo '<div class="wpeasycart-stripe-already-paid-container" style="position:fixed; left:50%; top:50%; margin-left:-250px; margin-top:-80px; width:500px; max-width:100%; max-height:100%; background:#EFEFEF; padding:35px; border-radius:10px; text-align:center;">Just a moment, please wait.</div>';
						echo '</div>';
						echo '<script>window.location.href = "' . wp_json_encode( esc_url_raw( $stripe_pi_response->next_action->redirect_to_url->url ) ) . '";</script>';

					} else if ( isset( $stripe_pi_response->next_source_action ) && isset( $stripe_pi_response->next_source_action->type ) && 'authorize_with_url' == $stripe_pi_response->next_source_action->type ) {
						echo '<div class="wpeasycart-stripe-already-paid" style="position:fixed;top:0;left:0;width:100%;height:100%;z-index:999999;background:rgba(0,0,0,.8);">';
							echo '<div class="wpeasycart-stripe-already-paid-container" style="position:fixed; left:50%; top:50%; margin-left:-250px; margin-top:-80px; width:500px; max-width:100%; max-height:100%; background:#EFEFEF; padding:35px; border-radius:10px; text-align:center;">Just a moment, please wait.</div>';
						echo '</div>';
						echo '<script>window.location.href = "' . wp_json_encode( esc_url_raw( $stripe_pi_response->next_source_action->authorize_with_url->url ) ) . '";</script>';

					} else if ( isset( $stripe_pi_response->next_action ) && isset( $stripe_pi_response->next_action->type ) && 'use_stripe_sdk' == $stripe_pi_response->next_action->type ) {
						if ( get_option( 'ec_option_payment_process_method' ) == 'stripe' ) {
							$pkey = get_option( 'ec_option_stripe_public_api_key' );
						} else if ( get_option( 'ec_option_payment_process_method' ) == 'stripe_connect' && get_option( 'ec_option_stripe_connect_use_sandbox' ) ) {
							$pkey = get_option( 'ec_option_stripe_connect_sandbox_publishable_key' );
						} else {
							$pkey = get_option( 'ec_option_stripe_connect_production_publishable_key' );
						}
						$pkey = apply_filters( 'wp_easycart_stripe_connect_publishable_key', $pkey );
						echo '<script>
						try {
							var wpec_stripe = ( "undefined" !== typeof stripe ) ? stripe : Stripe( "' . esc_attr( $pkey ) . '" ); /* 6.0.2: the page may not have made one yet */
							wpec_stripe.handleNextAction( {
								clientSecret: "' . esc_attr( $stripe_pi_response->client_secret ) . '"
							} ).then( function( result ) {
								if ( result.error ) {
									alert( "There is a problem handling your payment: " + result.error.message + ". Contact Support for assistance." );
								} else {
									window.location.reload(); /* confirmed: this page finishes the order */
								}
							} );
						} catch( err ) {
							alert( "Your WP EasyCart with Stripe has a problem: " + err.message + ". Contact WP EasyCart for assistance." );
						}
						</script>';
					} else if ( isset( $stripe_pi_response->next_action ) && isset( $stripe_pi_response->next_action->type ) && 'alipay_handle_redirect' == $stripe_pi_response->next_action->type ) {
						/* Handle Later? */
					} else if ( isset( $stripe_pi_response->next_action ) && isset( $stripe_pi_response->next_action->type ) && 'oxxo_display_details' == $stripe_pi_response->next_action->type ) {
						/* Handle Later? */
					}
					return;
				} else if ( $stripe_pi_response && ( $stripe_pi_response->status == 'requires_confirmation' || $stripe_pi_response->status == 'pending' ) ) {
					echo '<div class="wpeasycart-stripe-already-paid" style="position:fixed;top:0;left:0;width:100%;height:100%;z-index:999999;background:rgba(0,0,0,.8);">';
						echo '<div class="wpeasycart-stripe-already-paid-container" style="position:fixed; left:50%; top:50%; margin-left:-250px; margin-top:-80px; width:500px; max-width:100%; max-height:100%; background:#EFEFEF; padding:35px; border-radius:10px; text-align:center;">Just a moment, please wait.</div>';
					echo '</div>';
					echo '<script>
						ec_stripe_check_order_status( "' . esc_attr( $stripe_pi_response->id ) . '", "' . esc_attr( wp_create_nonce( 'wp-easycart-create-stripe-ideal-order-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ) . '" );
					</script>';
					return;
				}
			}

			if ( '' != get_option( 'ec_option_google_ga4_property_id' ) && ! wp_easycart_onepage_active() ) {
				$ga4_event = 'view_cart';
				if ( 1 == $cart_page ) {
					$ga4_event = 'view_cart';
				} else if ( 2 == $cart_page ) {
					$ga4_event = 'begin_checkout';
				} else if ( 3 == $cart_page ) {
					$ga4_event = 'add_shipping_info';
				} else if ( 4 == $cart_page ) {
					$ga4_event = 'add_payment_info';
				}

				if ( $ga4_event ) {
					if ( get_option( 'ec_option_google_ga4_tag_manager' ) ) {
						echo '<script>
						jQuery( document ).ready( function() {
							dataLayer.push( { ecommerce: null } );
							dataLayer.push( {
								event: "' . esc_attr( $ga4_event ) . '",
								ecommerce: {
									currency: "' . esc_attr( wp_easycart_base_currency_code() ) . '",
									value: ' . esc_attr( number_format( $this->order_totals->grand_total, 2, '.', '' ) ) . ',
									coupon_code: "' . esc_attr( $this->coupon_code ) . '",';
						if ( 'add_shipping_info' == $ga4_event ) {
							echo '
									shipping_tier: "' . esc_attr( trim( strip_tags( $this->shipping->get_selected_shipping_method() ) ) ) . '",';
						}
						echo '
									items: [ ';
									for( $i=0; $i < count( $this->cart->cart ); $i++ ) {
										echo '{
											item_id: "' . esc_attr( $this->cart->cart[$i]->model_number ) . '",
											item_name: "' . esc_attr( $this->cart->cart[$i]->title ) . '",
											index: ' . esc_attr( $i ) . ',
											price: ' . esc_attr( number_format( $this->cart->cart[$i]->unit_price, 2, '.', '' ) ) . ',
											item_brand: "' . esc_attr( $this->cart->cart[$i]->manufacturer_name ) . '",
											quantity: ' . esc_attr( number_format( $this->cart->cart[$i]->quantity, 2, '.', '' ) ) . '
										}, ';
									}
									echo ' ]
								}
							} );
						} );
						</script>';
					} else {
						echo '<script>
						jQuery( document ).ready( function() {
							gtag( "event", "' . esc_attr( $ga4_event ) . '", {
								currency: "' . esc_attr( wp_easycart_base_currency_code() ) . '",
								value: ' . esc_attr( number_format( $this->order_totals->grand_total, 2, '.', '' ) ) . ',
								coupon_code: "' . esc_attr( $this->coupon_code ) . '",';
						if ( 'add_shipping_info' == $ga4_event ) {
							echo '
								shipping_tier: "' . esc_attr( trim( strip_tags( $this->shipping->get_selected_shipping_method() ) ) ) . '",';
						}
						echo '
								items: [ ';
								for( $i=0; $i < count( $this->cart->cart ); $i++ ) {
									echo '{
										item_id: "' . esc_attr( $this->cart->cart[$i]->model_number ) . '",
										item_name: "' . esc_attr( $this->cart->cart[$i]->title ) . '",
										index: ' . esc_attr( $i ) . ',
										price: ' . esc_attr( number_format( $this->cart->cart[$i]->unit_price, 2, '.', '' ) ) . ',
										item_brand: "' . esc_attr( $this->cart->cart[$i]->manufacturer_name ) . '",
										quantity: ' . esc_attr( number_format( $this->cart->cart[$i]->quantity, 2, '.', '' ) ) . '
									}, ';
								}
								echo ' ]
							} );
						} );
						</script>';
					}
				}
			}
		}

		if ( $cart_count == 0 && (int) substr( $cart_page, 0, 1 ) < 5 ) {
			$this->display_cart_top( 1, $success_code, $error_code );
			$this->display_cart_page();

		} else if ( $cart_count > 0 && (float) apply_filters( 'wpeasycart_minimum_order_total', get_option( 'ec_option_minimum_order_total' ) ) > 0 && (float) apply_filters( 'wpeasycart_minimum_order_total', get_option( 'ec_option_minimum_order_total' ) ) > $this->cart->subtotal ) {
			$this->display_cart_page();

		} else if ( preg_match( '/4\-paypal\-(PAYID-[a-zA-Z0-9]+)\-([a-zA-Z0-9]+)/', $cart_page, $matches ) ) {
			$pid = $matches[1];
			$pyid = $matches[2];
			$this->display_payment_paypal_express( $pid, $pyid );

		} else if ( preg_match( '/4\-paypal\-([a-zA-Z0-9]+)\-([a-zA-Z0-9]+)/', $cart_page, $matches ) ) {
			$oid = $matches[1];
			$pyid = $matches[2];
			$this->display_payment_paypal_express( false, $pyid, $oid );

		} else if ( preg_match( '/4\-ideal\-([a-zA-Z0-9\_]+)\-([a-zA-Z0-9\_]+)/', $cart_page, $matches ) ) {
			$ideal_source = $matches[1];
			$ideal_client_secret = $matches[2];
			$this->display_cart_top( 3, $success_code, $error_code );
			$this->display_payment( $ideal_source, $ideal_client_secret );

		} else if ( $cart_page == 1 ) {
			$this->display_cart_top( 1, $success_code, $error_code );
			$this->display_cart( '' );

		} else if ( $cart_page == 2 ) {
			$this->display_cart_top( 2, $success_code, $error_code );
			$this->display_checkout_details();

		} else if ( $cart_page == 3 ) {
			$this->display_cart_top( 2, $success_code, $error_code );
			$this->display_shipping_method();

		} else if ( $cart_page == 4 ) {
			$this->display_cart_top( 3, $success_code, $error_code );
			$this->display_payment();

		} else if ( preg_match( '/5\-sub\-([0-9]+)/', $cart_page, $matches ) ) {
			$this->display_cart_error( $error_code );
			$this->display_subscription_page( $matches[1] );

		} else if ( substr( $cart_page, 0, 1 ) == 6 ) {
			$this->display_cart_success_page( substr( $cart_page, 2, strlen( $cart_page ) - 1  ), $success_code, $error_code );

		}
	}

	public function display_cart_top( $page_num, $success_code, $error_code ) {
		if ( wp_easycart_onepage_active() ) {
			/* 6.0.2: the one-page checkout shows the same notices as the classic one ( a declined payment, a stock change, a failed login ). */
			echo '<div class="ec_cart_onepage_notices" id="ec_cart_onepage_notices">';
			$this->display_cart_success( $success_code );
			$this->display_cart_error( $error_code );
			echo '</div>';
		} else {
			if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_dynamic_top.php' ) ) {
				include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_dynamic_top.php' );
			} else {
				include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_dynamic_top.php' );
			}
		}
	}

	public function load_cart_total_lines( $is_page_1 = false ) {
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_totals.php' ) ) {
			include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_totals.php' );
		} else {
			include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_totals.php' );
		}
	}

	public function display_cart( $empty_cart_string ) {
		do_action( 'wp_easycart_display_cart_before', $this->cart, $this->order_totals );
		if ( wp_easycart_onepage_active() ) {
			$current_screen = 'cart';
			if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_checkout_v2.php' ) ) {
				include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_checkout_v2.php' );
			} else {
				include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_checkout_v2.php' );
			}
		} else {
			if ( $this->cart->total_items > 0 ) {
				if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart.php' ) ) {
					include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart.php' );
				} else {
					include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart.php' );
				}

				echo '<input type="hidden" name="ec_cart_page" id="ec_cart_page" value="' . esc_attr( $this->cart_page ) . '" />';
				echo '<input type="hidden" name="ec_cart_base_path" id="ec_cart_base_path" value="' . esc_attr( plugins_url() ) . '" />';
			} else {
				echo esc_attr( $empty_cart_string );
			}
		}
		do_action( 'wp_easycart_display_cart_after', $this->cart, $this->order_totals );
	}

	/**
	 * The cart on its own: the classic cart ( ec_cart.php: lines, quantities, remove, coupon, gift card, shipping estimate,
	 * totals and the Checkout button ) and the minimum order notice, without the cart page around it. The Elementor Cart
	 * widget draws it on pages other than the cart page; its Checkout button goes to the cart page's checkout ( classic or
	 * one-page ). The cart page itself keeps display_cart_page().
	 *
	 * @since 6.0.2
	 */
	public function display_cart_contents() {
		do_action( 'wp_easycart_display_cart_before', $this->cart, $this->order_totals );
		if ( $this->cart->total_items > 0 ) {
			$minimum = (float) apply_filters( 'wpeasycart_minimum_order_total', get_option( 'ec_option_minimum_order_total' ) );
			if ( $minimum > 0 ) {
				echo '<div class="ec_minimum_purchase_box" data-min-cart="' . esc_attr( $minimum ) . '"' . ( ( $minimum <= $this->cart->subtotal ) ? ' style="display:none;"' : '' ) . '><p>' . wp_easycart_language()->get_text( 'cart', 'cart_minimum_purchase_amount1' ) . ' ' . esc_attr( $GLOBALS['currency']->get_currency_display( $minimum ) ) . ' ' . wp_easycart_language()->get_text( 'cart', 'cart_minimum_purchase_amount2' ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- language text, printed as ec_cart_page.php prints it.
			}
			if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart.php' ) ) {
				include EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart.php';
			} else {
				include EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart.php';
			}
			echo '<input type="hidden" name="ec_cart_page" id="ec_cart_page" value="' . esc_attr( $this->cart_page ) . '" />';
			echo '<input type="hidden" name="ec_cart_base_path" id="ec_cart_base_path" value="' . esc_attr( plugins_url() ) . '" />';
		}
		do_action( 'wp_easycart_display_cart_after', $this->cart, $this->order_totals );
	}

	public function display_login() {
		if ( $this->cart->total_items > 0 ) {
			if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_login.php' ) ) {
				include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_login.php' );
			} else {
				include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_login.php' );
			}
		}
	}

	public function display_login_complete() {
		if ( $this->cart->total_items > 0 ) {
			if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_login_complete.php' ) ) {
				include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_login_complete.php' );
			} else {
				include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_login_complete.php' );
			}
		}
	}

	public function display_subscription_login_complete() {
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_login_complete.php' ) ) {
			include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_login_complete.php' );
		} else if ( file_exists( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_login_complete.php' ) ) {
			include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_login_complete.php' );
		}
	}

	public function page_allowed( $page ) {
		$shipping = $payment = true;
		if ( get_option( 'ec_option_use_shipping' ) && $this->shipping_address_allowed && ( $this->cart->shippable_total_items > 0 || $this->order_totals->handling_total > 0 || $this->cart->excluded_shippable_total_items > 0 ) ) {
			$shipping_address = ( isset( $GLOBALS['ec_cart_data']->cart_data->shipping_address_line_1 ) ) ? $GLOBALS['ec_cart_data']->cart_data->shipping_address_line_1 : '';
			$shipping_address .= ( isset( $GLOBALS['ec_cart_data']->cart_data->shipping_address_line_2 ) ) ? ' ' . $GLOBALS['ec_cart_data']->cart_data->shipping_address_line_2 : '';
			$shipping_city = ( isset( $GLOBALS['ec_cart_data']->cart_data->shipping_city ) ) ? $GLOBALS['ec_cart_data']->cart_data->shipping_city : '';
			$shipping_state = ( isset( $GLOBALS['ec_cart_data']->cart_data->shipping_state ) ) ? $GLOBALS['ec_cart_data']->cart_data->shipping_state : '';
			$shipping_zip = ( isset( $GLOBALS['ec_cart_data']->cart_data->shipping_zip ) ) ? $GLOBALS['ec_cart_data']->cart_data->shipping_zip : '';
			$shipping_country = ( isset( $GLOBALS['ec_cart_data']->cart_data->shipping_country ) ) ? $GLOBALS['ec_cart_data']->cart_data->shipping_country : '';

			$is_shippable = ( $this->cart->shippable_total_items > 0 ) ? true : false;
			$has_shipping_rate = ( $this->shipping->has_shipping_option() );

			if ( get_option( 'ec_option_onepage_checkout_tabbed' ) && '' == $GLOBALS['ec_cart_data']->cart_data->email ) {
				$shipping = false;
			} else if ( '0' == $GLOBALS['ec_cart_data']->cart_data->shipping_country ) {
				$shipping = false;
			} else if ( '' == $GLOBALS['ec_cart_data']->cart_data->shipping_first_name ) {
				$shipping = false;
			} else if ( '' == $GLOBALS['ec_cart_data']->cart_data->shipping_city ) {
				$shipping = false;
			} else if ( $is_shippable && ! $this->shipping->validate_address( $shipping_address, $shipping_city, $shipping_state, $shipping_zip, $shipping_country ) ) {
				$shipping= false;
			} else if ( ! $this->validate_vat_registration_number( $GLOBALS['ec_cart_data']->cart_data->vat_registration_number ) ) {
				$shipping = false;
			}
			if ( ! $shipping || ! $this->validate_cart_shipping() || ! $has_shipping_rate ) {
				$payment = false;
			}
		} else if ( get_option( 'ec_option_onepage_checkout_tabbed' ) ) {
			$billing_address = ( isset( $GLOBALS['ec_cart_data']->cart_data->billing_address_line_1 ) ) ? $GLOBALS['ec_cart_data']->cart_data->billing_address_line_1 : '';
			$billing_address .= ( isset( $GLOBALS['ec_cart_data']->cart_data->billing_address_line_2 ) ) ? ' ' . $GLOBALS['ec_cart_data']->cart_data->billing_address_line_2 : '';
			$billing_city = ( isset( $GLOBALS['ec_cart_data']->cart_data->billing_city ) ) ? $GLOBALS['ec_cart_data']->cart_data->billing_city : '';
			$billing_state = ( isset( $GLOBALS['ec_cart_data']->cart_data->billing_state ) ) ? $GLOBALS['ec_cart_data']->cart_data->billing_state : '';
			$billing_zip = ( isset( $GLOBALS['ec_cart_data']->cart_data->billing_zip ) ) ? $GLOBALS['ec_cart_data']->cart_data->billing_zip : '';
			$billing_country = ( isset( $GLOBALS['ec_cart_data']->cart_data->billing_country ) ) ? $GLOBALS['ec_cart_data']->cart_data->billing_country : '';

			if ( '' == $GLOBALS['ec_cart_data']->cart_data->email ) {
				$shipping = false;
			} else if ( '0' == $GLOBALS['ec_cart_data']->cart_data->billing_country ) {
				$payment = false;
			} else if ( '' == $GLOBALS['ec_cart_data']->cart_data->billing_first_name ) {
				$payment = false;
			} else if ( '' == $GLOBALS['ec_cart_data']->cart_data->billing_city ) {
				$payment = false;
			} else if ( ! $this->validate_vat_registration_number( $GLOBALS['ec_cart_data']->cart_data->vat_registration_number ) ) {
				$payment = false;
			}
		}

		if ( 'shipping' == $page ) {
			return $shipping;
		} else if ( 'payment' == $page ) {
			return $payment;
		} else {
			return true;
		}
	}

	public function should_display_cart() {
		// Check minimum order amount
		if ( (float) apply_filters( 'wpeasycart_minimum_order_total', get_option( 'ec_option_minimum_order_total' ) ) > 0 && (float) apply_filters( 'wpeasycart_minimum_order_total', get_option( 'ec_option_minimum_order_total' ) ) > $this->cart->subtotal ) {
			return true;
		} else if ( apply_filters( 'wpeasycart_restrict_cart_only', false ) ) {
			return true;
		}

		if ( !$this->should_display_login() )
			return true;
		else
			return false;
	}

	public function should_display_login() {
		return ( isset( $_GET['ec_page'] ) && $_GET['ec_page'] == "checkout_login" && ( $GLOBALS['ec_cart_data']->cart_data->email == "" || $GLOBALS['ec_cart_data']->cart_data->is_guest == "" || $GLOBALS['ec_cart_data']->cart_data->is_guest ) );
	}

	public function payment_processor_requires_billing() {
		if ( get_option( 'ec_option_payment_process_method' ) == "skrill" ) {
			return false;	
		}
	}

	public function should_hide_shipping_panel() {
		return ( $GLOBALS['ec_cart_data']->cart_data->shipping_selector == "" || ( $GLOBALS['ec_cart_data']->cart_data->shipping_selector != "" && $GLOBALS['ec_cart_data']->cart_data->shipping_selector == "false" ) );
	}

	public function should_display_page_one() {
		// Check minimum order amount
		if ( (float) apply_filters( 'wpeasycart_minimum_order_total', get_option( 'ec_option_minimum_order_total' ) ) > 0 && (float) apply_filters( 'wpeasycart_minimum_order_total', get_option( 'ec_option_minimum_order_total' ) ) > $this->cart->subtotal ) {
			return false;
		} else if ( apply_filters( 'wpeasycart_restrict_cart_only', false ) ) {
			return false;
		}

		return ( isset( $_GET['ec_page'] ) && $_GET['ec_page'] == "checkout_info" );
	}

	public function display_page_one_form_start() {
		$next_page = "checkout_shipping";
		if ( !get_option( 'ec_option_use_shipping' ) || $this->order_totals->shipping_total <= 0 )
			$next_page = "checkout_payment";

		echo "<form action=\"" . esc_url( wpeasycart_links()->get_cart_page( $next_page ) ) . "\" method=\"POST\" id=\"wpeasycart_checkout_details_form\"";
		do_action( 'wp_easycart_checkout_form_inner' );
		echo ">";
		echo "<input type=\"hidden\" name=\"ec_cart_form_action\" value=\"save_checkout_info\" />";
		echo "<input type=\"hidden\" name=\"ec_cart_form_nonce\" id=\"ec_cart_form_nonce\" value=\"" . esc_attr( wp_create_nonce( 'wp-easycart-cart-checkout-info-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ) . "\" />";
	}

	public function display_page_one_form_end() {
		echo "</form>";
	}

	public function should_display_page_two() {
		// Check minimum order amount
		if ( (float) apply_filters( 'wpeasycart_minimum_order_total', get_option( 'ec_option_minimum_order_total' ) ) > 0 && (float) apply_filters( 'wpeasycart_minimum_order_total', get_option( 'ec_option_minimum_order_total' ) ) > $this->cart->subtotal ) {
			return false;
		}

		return ( isset( $_GET['ec_page'] ) && $_GET['ec_page'] == "checkout_shipping" && $GLOBALS['ec_cart_data']->cart_data->email != "" );
	}

	public function display_page_two_form_start() {
		echo "<form action=\"" . esc_url( wpeasycart_links()->get_cart_page( 'checkout_payment' ) ) . "\" method=\"post\" id=\"wpeasycart_payment_shipping_method_form\">";
		echo "<input type=\"hidden\" name=\"ec_cart_form_action\" value=\"save_checkout_shipping\" />";
		echo "<input type=\"hidden\" name=\"ec_cart_form_nonce\" id=\"ec_cart_form_nonce\" value=\"" . esc_attr( wp_create_nonce( 'wp-easycart-cart-shipping-method-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ) . "\" />";
	}

	public function display_page_two_form_end() {
		echo "</form>";
	}

	public function should_display_page_three() {
		// Check minimum order amount
		if ( (float) apply_filters( 'wpeasycart_minimum_order_total', get_option( 'ec_option_minimum_order_total' ) ) > 0 && (float) apply_filters( 'wpeasycart_minimum_order_total', get_option( 'ec_option_minimum_order_total' ) ) > $this->cart->subtotal ) {
			return false;
		}

		return ( isset( $_GET['ec_page'] ) && $_GET['ec_page'] == "checkout_payment" && $GLOBALS['ec_cart_data']->cart_data->email != "" );
	}

	public function display_page_three_form_start() {
		if ( get_option( 'ec_option_payment_process_method' ) == "eway" && get_option( 'ec_option_eway_use_rapid_pay' ) ) {
			echo "<form data-eway-encrypt-key=\"" . esc_attr( get_option( 'ec_option_eway_client_key' ) ) . "\" action=\"" . esc_url( wpeasycart_links()->get_cart_page( 'checkout_submit_order' ) ) . "\" method=\"post\" id=\"ec_submit_order_form\">";
		} else {
			echo "<form action=\"" . esc_url( wpeasycart_links()->get_cart_page( 'checkout_submit_order' ) ) . "\" method=\"post\" id=\"ec_submit_order_form\">";
		}
		echo "<input type=\"hidden\" name=\"ec_cart_form_action\" value=\"submit_order\" />";
		echo "<input type=\"hidden\" name=\"ec_cart_form_nonce\" id=\"ec_cart_form_nonce\" value=\"" . esc_attr( wp_create_nonce( 'wp-easycart-cart-submit-order-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ) . "\" />";
	}

	public function display_page_three_form_end() {
		echo "</form>";
	}

	public function display_subscription_form_start( $model_number ) {
		echo "<form action=\"" . esc_url( wpeasycart_links()->get_cart_page( 'checkout_submit_order' ) ) . "\" id=\"ec_submit_order_form\" method=\"post\">";
		echo "<input type=\"hidden\" name=\"ec_cart_form_action\" value=\"insert_subscription\" />";
		echo "<input type=\"hidden\" name=\"ec_cart_form_nonce\" id=\"ec_cart_form_nonce\" value=\"" . esc_attr( wp_create_nonce( 'wp-easycart-cart-insert-subscription-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ) . "\" />";
		echo "<input type=\"hidden\" name=\"ec_cart_model_number\" id=\"ec_cart_model_number\" value=\"" . esc_attr( $model_number ) . "\" />";
	}

	public function display_subscription_form_end() {
		echo "</form>";
	}

	/* START CART FUNCTIONS */
	public function is_cart_type_one() {
		return ( !isset( $_GET['ec_page'] ) || ( isset( $_GET['ec_page'] ) && $GLOBALS['ec_cart_data']->cart_data->email == "" ) );
	}

	public function is_cart_type_two() {
		return ( !isset( $_GET['ec_page'] ) || ( isset( $_GET['ec_page'] ) && $_GET['ec_page'] == "checkout_payment" ) || $GLOBALS['ec_cart_data']->cart_data->email == "" );
	}

	public function is_cart_type_three() {
		return ( ( $this->shipping->shipping_method == "live" ) && $this->cart->weight > 0 && ( !isset( $_GET['ec_page'] ) || $GLOBALS['ec_cart_data']->cart_data->email == "" ) );
	}

	public function display_total_items() {
		echo "<span id=\"ec_cart_total_items\">" . esc_attr( $this->cart->get_total_items() ) . "</span>";
	}

	public function display_cart_items() {
		$this->cart->display_cart_items( $this->tax->vat_enabled, $this->tax->vat_country_match );	
	}

	public function has_cart_total_promotion() {
		if ( $this->cart->cart_total_promotion )
			return true;
		else
			return false;
	}

	public function display_cart_total_promotion() {
		echo esc_attr( $this->cart->cart_total_promotion );
	}

	public function has_cart_shipping_promotion() {
		if ( $this->shipping->get_shipping_promotion_text() )
			return true;
		else
			return false;
	}

	public function display_cart_shipping_promotion() {
		echo esc_attr( $this->shipping->get_shipping_promotion_text() );
	}

	public function get_selected_country( $type = 'billing' ) {
		if ( 'billing' == $type ) {
			if ( '' != $GLOBALS['ec_cart_data']->cart_data->billing_country && '0' != $GLOBALS['ec_cart_data']->cart_data->billing_country ) {
				$selected_country = $GLOBALS['ec_cart_data']->cart_data->billing_country;
			} else if ( 0 != $GLOBALS['ec_user']->billing->get_value( 'country2' ) ) {
				$selected_country = $GLOBALS['ec_user']->billing->get_value( 'country2' );
			} else if ( 1 == count( $countries ) ) {
				$selected_country = $countries[0]->iso2_cnt;
			} else if ( get_option( 'ec_option_default_country' ) ) {
				$selected_country = get_option( 'ec_option_default_country' );
			} else {
				$selected_country = $GLOBALS['ec_user']->billing->get_value( 'country2' );
			}
		} else {
			if ( '' != $GLOBALS['ec_cart_data']->cart_data->shipping_country && '0' != $GLOBALS['ec_cart_data']->cart_data->shipping_country ) {
				$selected_country = $GLOBALS['ec_cart_data']->cart_data->shipping_country;
			} else if ( 0 != $GLOBALS['ec_user']->shipping->get_value( 'country2' ) ) {
				$selected_country = $GLOBALS['ec_user']->shipping->get_value( 'country2' );
			} else if ( 1 == count( $countries ) ) {
				$selected_country = $countries[0]->iso2_cnt;
			} else if ( get_option( 'ec_option_default_country' ) ) {
				$selected_country = get_option( 'ec_option_default_country' );
			} else {
				$selected_country = $GLOBALS['ec_user']->shipping->get_value( 'country2' );
			}
		}
		return $selected_country;
	}

	public function display_shipping_costs_input( $label, $button_text, $label2 = 'Country:', $select_label = 'Select One' ) {

		if ( get_option( 'ec_option_estimate_shipping_country' ) ) {

			$countries = $GLOBALS['ec_countries']->countries;

			if ( $GLOBALS['ec_cart_data']->cart_data->estimate_shipping_country != "" )
				$selected_country = $GLOBALS['ec_cart_data']->cart_data->estimate_shipping_country;
			else if ( count( $countries ) == 1 )
				$selected_country = $countries[0]->iso2_cnt;
			else if ( get_option( 'ec_option_default_country' ) )
				$selected_country = get_option( 'ec_option_default_country' );
			else
				$selected_country = "0";

			echo "<div class=\"ec_estimate_shipping_country\"><span>" . esc_attr( $label2 ) . "</span><select name=\"ec_cart_country\" id=\"ec_cart_country\" class=\"no_wrap\">";
			echo "<option value=\"0\"";
			if ( $selected_country == "0" )
				echo " selected=\"selected\"";
			echo ">" . esc_attr( $select_label ) . "</option>";
			foreach( $countries as $country ) {
				echo "<option value=\"" . esc_attr( $country->iso2_cnt ) . "\"";
				if ( $country->iso2_cnt == $selected_country )
					echo " selected=\"selected\"";
				echo ">" . esc_attr( $country->name_cnt ) . "</option>";
			}
			echo "</select></div>";
		} else {
			echo "<input type=\"hidden\" name=\"ec_cart_country\" id=\"ec_cart_country\" value=\"0\" />";
		}
		echo "<div class=\"ec_estimate_shipping_zip\"><span>" . esc_attr( $label ) . "</span><input type=\"text\" name=\"ec_cart_zip_code\" id=\"ec_cart_zip_code\" value=\"";
		if ( $GLOBALS['ec_cart_data']->cart_data->estimate_shipping_zip != "" )
			echo esc_attr( $GLOBALS['ec_cart_data']->cart_data->estimate_shipping_zip );
		echo "\" /><a href=\"#\" onclick=\"return ec_estimate_shipping_click();\">" . esc_attr( $button_text ) . "</a></div>";
	}

	public function display_estimate_shipping_country_select() {

		$countries = $GLOBALS['ec_countries']->countries;

		if ( $GLOBALS['ec_cart_data']->cart_data->estimate_shipping_country != "" )
			$selected_country = $GLOBALS['ec_cart_data']->cart_data->estimate_shipping_country;
		else if ( count( $countries ) == 1 )
			$selected_country = $countries[0]->iso2_cnt;
		else if ( get_option( 'ec_option_default_country' ) )
			$selected_country = get_option( 'ec_option_default_country' );
		else
			$selected_country = "0";

		echo "<select name=\"ec_estimate_country\" id=\"ec_estimate_country\" class=\"no_wrap\">";
		echo "<option value=\"0\""; if ( $selected_country == "0" ) { echo " selected=\"selected\""; } echo ">" . wp_easycart_language()->get_text( 'cart_estimate_shipping', 'cart_estimate_shipping_select_one' ) . "</option>";
		foreach( $countries as $country ) {
			echo "<option value=\"" . esc_attr( $country->iso2_cnt ) . "\"";
			if ( $country->iso2_cnt == $selected_country )
				echo " selected=\"selected\"";
			echo ">" . esc_attr( $country->name_cnt ) . "</option>";
		}
		echo "</select>";
	}

	public function display_shipping_costs_input_text( $label ) {
		echo "<span>" . esc_attr( $label ) . "</span><input type=\"text\" name=\"ec_cart_zip_code\" id=\"ec_cart_zip_code\" value=\"";
		if ( $GLOBALS['ec_cart_data']->cart_data->estimate_shipping_zip != "" )
			echo esc_attr( $GLOBALS['ec_cart_data']->cart_data->estimate_shipping_zip );
		echo "\" />";
	}

	public function display_shipping_costs_input_button( $button_text ) {
		echo "<a href=\"#\" onclick=\"return ec_estimate_shipping_click();\">" . esc_attr( $button_text ) . "</a>";
	}

	public function display_estimate_shipping_loader() {
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . "/design/theme/" . get_option( 'ec_option_base_theme' ) . "/ec_cart_page/loader.gif" ) )	
			echo "<div class=\"ec_estimate_shipping_loader\" id=\"ec_estimate_shipping_loader\"><img src=\"" . esc_attr( plugins_url( "wp-easycart-data/design/theme/" . get_option( 'ec_option_base_theme' ) . "/ec_cart_page/loader.gif", EC_PLUGIN_DATA_DIRECTORY ) ) . "\" /></div>";	
		else
			echo "<div class=\"ec_estimate_shipping_loader\" id=\"ec_estimate_shipping_loader\"><img src=\"" . esc_attr( plugins_url( "wp-easycart/design/theme/" . get_option( 'ec_option_base_theme' ) . "/ec_cart_page/loader.gif", EC_PLUGIN_DIRECTORY ) ) . "\" /></div>";
	}

	/**
	 * Tell each cart line what Offers took off it, so "Show coupon / promotion savings on each line" strike the price and
	 * show the price after savings for Offers discounts too ( ec_cartitem::set_offer_savings() ). A line is only matched
	 * to a result that priced the same quantity; the Offers refresh after a quantity change draws it again.
	 *
	 * @since 6.0.2
	 */
	private function set_offer_line_savings() {
		if ( ! is_object( $this->offer_result ) || ! isset( $this->offer_result->lines ) || ! is_array( $this->offer_result->lines ) || ! isset( $this->cart->cart ) || ! is_array( $this->cart->cart ) ) {
			return;
		}
		foreach ( $this->cart->cart as $cart_item ) {
			if ( ! is_object( $cart_item ) || ! method_exists( $cart_item, 'set_offer_savings' ) ) {
				continue;
			}
			$offer_discounts = array();
			foreach ( $this->offer_result->lines as $offer_line ) {
				if ( is_object( $offer_line ) && isset( $offer_line->cartitem_id ) && (string) $offer_line->cartitem_id === (string) $cart_item->cartitem_id ) {
					if ( isset( $offer_line->offer_discounts ) && is_array( $offer_line->offer_discounts ) && ( ! isset( $offer_line->quantity ) || (int) $offer_line->quantity === (int) $cart_item->quantity ) ) {
						$offer_discounts = $offer_line->offer_discounts;
					}
					break;
				}
			}
			$cart_item->set_offer_savings( $offer_discounts );
		}
	}

	public function display_subtotal() {
		echo "<span id=\"ec_cart_subtotal\">" . esc_attr( $GLOBALS['currency']->get_currency_display( $this->order_totals->get_converted_sub_total(), false ) ) . "</span>";	
	}

	public function get_subtotal() {
		$subtotal = $this->order_totals->get_converted_sub_total();
		if ( get_option( 'ec_option_show_coupon_discount_total' ) ) {
			foreach ( $this->cart->cart as $cart_item ) {
				if ( $cart_item->coupon_discount_line_total > 0 ) {
					$subtotal -= $cart_item->coupon_discount_line_total;
				}
			}
		}
		return $GLOBALS['currency']->get_currency_display( $subtotal, false );
	}

	public function display_tax_total() {
		echo "<span id=\"ec_cart_tax\">" . esc_attr( $GLOBALS['currency']->get_currency_display( $this->order_totals->tax_total ) ) . "</span>";	
	}

	public function get_tax_total() {
		return $GLOBALS['currency']->get_currency_display( $this->order_totals->tax_total );	
	}

	public function has_duty() {
		if ( $this->tax->duty_total > 0 )			return true;
		else										return false;	
	}

	public function display_duty_total() {
		echo "<span id=\"ec_cart_duty\">" . esc_attr( $GLOBALS['currency']->get_currency_display( $this->order_totals->duty_total ) ) . "</span>";	
	}

	public function get_duty_total() {
		return $GLOBALS['currency']->get_currency_display( $this->order_totals->duty_total );	
	}

	public function get_vat_total() {
		return $this->tax->vat_total;
	}

	public function get_vat_total_formatted() {
		return $GLOBALS['currency']->get_currency_display( $this->tax->vat_total );
	}

	public function get_vat_rate_formatted( $vat_rate = false ) {
		if ( ! $vat_rate ) {
			$vat_rate = $this->tax->vat_rate;
		}
		$vat_rate_formatted = $vat_rate;
		if ( round( $vat_rate_formatted, 0 ) == $vat_rate ) {
			$vat_rate_formatted = number_format( round( $vat_rate_formatted, 0 ), 0, '', '' );

		} else if ( round( $vat_rate_formatted, 1 ) == $vat_rate ) {
			$vat_rate_formatted = number_format( $vat_rate_formatted, 1, '.', '' );

		} else if ( round( $vat_rate_formatted, 2 ) == $vat_rate ) {
			$vat_rate_formatted = number_format( $vat_rate_formatted, 2, '.', '' );

		} else if ( round( $vat_rate_formatted, 3 ) == $vat_rate ) {
			$vat_rate_formatted = number_format( $vat_rate_formatted, 3, '.', '' );

		}
		return apply_filters( 'wpeasycart_format_vat_rate', '(' . $vat_rate_formatted . '%)', $vat_rate );
	}

	public function display_vat_total() {
		echo "<span id=\"ec_cart_vat\">" . esc_attr( $GLOBALS['currency']->get_currency_display( $this->order_totals->vat_total ) ) . "</span>";	
	}

	public function display_shipping_total() {
		echo "<span id=\"ec_cart_shipping\">" . esc_attr( $GLOBALS['currency']->get_currency_display( $this->order_totals->shipping_total ) ) . "</span>";
	}

	public function get_shipping_total() {
		return $GLOBALS['currency']->get_currency_display( number_format( $this->order_totals->shipping_total, 3, '.', '' ) );
	}

	public function display_discount_total() {
		echo "<span id=\"ec_cart_discount\">" . esc_attr( $GLOBALS['currency']->get_currency_display( $this->order_totals->discount_total ) ) . "</span>";
	}

	public function get_discount_total() {
		return $GLOBALS['currency']->get_currency_display( (-1) * $this->order_totals->discount_total );
	}

	public function get_gst_total() {
		return $GLOBALS['currency']->get_currency_display( $this->order_totals->gst_total );	
	}

	public function get_pst_total() {
		return $GLOBALS['currency']->get_currency_display( $this->order_totals->pst_total );	
	}

	public function get_hst_total() {
		return $GLOBALS['currency']->get_currency_display( $this->order_totals->hst_total );	
	}

	public function get_tip_total() {
		return $GLOBALS['currency']->get_currency_display( $this->order_totals->tip_total );	
	}

	public function display_grand_total() {
		echo "<span id=\"ec_cart_grandtotal\">" . esc_attr( $GLOBALS['currency']->get_currency_display( $this->order_totals->get_converted_grand_total(), false ) ) . "</span>"; 	
	}

	public function get_grand_total() {
		return $GLOBALS['currency']->get_currency_display( $this->order_totals->get_converted_grand_total(), false ); 	
	}

	public function display_continue_shopping_button( $button_text ) {
		echo "<a href=\"" . esc_attr( $this->store_page );

		echo "\" class=\"ec_cart_continue_shopping_link\">" . esc_attr( $button_text ) . "</a>";
	}

	public function display_checkout_button( $button_text ) {
		$checkout_page = "checkout_login";
		if ( $GLOBALS['ec_cart_data']->cart_data->email != "" ) {
			$checkout_page = "checkout_info";

		} else if ( get_option( 'ec_option_skip_cart_login' ) ) {
			$checkout_page = "checkout_info";
			$GLOBALS['ec_cart_data']->cart_data->email = "guest";
			$GLOBALS['ec_cart_data']->cart_data->username = "guest";
		}
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . "/design/theme/" . get_option( 'ec_option_base_theme' ) . "/admin_panel.php" ) )
			echo "<a href=\"" . esc_url( wpeasycart_links()->get_cart_page( esc_attr( $checkout_page ) ) ) . "\" class=\"ec_cart_checkout_link\">" . esc_attr( $button_text ) . "</a>";
		else
			echo "<a href=\"" . esc_url( wpeasycart_links()->get_cart_page() ) . "\" class=\"ec_cart_checkout_link\">" . esc_attr( $button_text ) . "</a>";
	}
	/* END CART FUNCTIONS */

	// Forward the page to the cart page minus form submission with success note
	private function forward_cart_success() {

	}

	// Forward the page to the last product page, plus a failed note
	private function forward_product_failed() {

	}

	/* Login Form Functions */
	public function display_cart_login_form_start() {
		echo "<form action=\"". esc_attr( $this->cart_page ) . "\" method=\"post\">";	
	}

	public function display_cart_login_form_start_subscription() {
		echo "<form action=\"". esc_url( wpeasycart_links()->get_cart_page( 'subscription_info', array( 'subscription' => esc_attr( sanitize_text_field( $_GET['subscription'] ) ) ) ) ) . "\" method=\"post\">";
	}

	public function display_cart_login_form_end() {
		if ( isset( $_GET['subscription'] ) ) {
			echo "<input type=\"hidden\" name=\"ec_cart_subscription\" value=\"" . esc_attr( sanitize_text_field( $_GET['subscription'] ) ) . "\" />";
		}
		echo "<input type=\"hidden\" name=\"ec_cart_form_action\" value=\"login_user\" />";
		echo "<input type=\"hidden\" name=\"ec_cart_form_nonce\" id=\"ec_cart_form_nonce\" value=\"" . esc_attr( wp_create_nonce( 'wp-easycart-cart-login-user-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ) . "\" />";
		echo "</form>";
	}

	public function display_cart_login_form_guest_start() {
		echo "<form action=\"". esc_attr( $this->cart_page ) . "\" method=\"post\">";
	}

	public function display_cart_login_form_guest_end() {
		if ( isset( $_GET['subscription'] ) ) {
			echo "<input type=\"hidden\" name=\"ec_cart_subscription\" value=\"" . esc_attr( sanitize_text_field( $_GET['subscription'] ) ) . "\" />";
		}
		echo "<input type=\"hidden\" name=\"ec_cart_form_action\" value=\"login_user\" />";
		echo "<input type=\"hidden\" name=\"ec_cart_form_nonce\" id=\"ec_cart_form_nonce\" value=\"" . esc_attr( wp_create_nonce( 'wp-easycart-cart-login-user-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ) . "\" />";
		echo "<input type=\"hidden\" name=\"ec_cart_login_email\" value=\"guest\" />";
		echo "<input type=\"hidden\" name=\"ec_cart_login_password\" value=\"guest\" />";
		echo "</form>";
	}

	public function display_cart_login_email_input() {
		echo "<input type=\"email\" id=\"ec_cart_login_email\" name=\"ec_cart_login_email\" class=\"ec_cart_login_input\" autocorrect=\"off\" autocapitalize=\"off\" />";
	}

	public function display_cart_login_password_input() {
		echo "<input type=\"password\" id=\"ec_cart_login_password\" name=\"ec_cart_login_password\" class=\"ec_cart_login_input\" />";
	}

	public function display_cart_login_login_button( $input ) {
		echo "<input type=\"submit\" id=\"ec_cart_login_login_button\" name=\"ec_cart_login_login_button\" class=\"ec_cart_login_button\" value=\"" . esc_attr( $input ) . "\" />";
	}

	public function display_cart_login_forgot_password_link( $link_text ) {
		echo "<a href=\"" . esc_url( wpeasycart_links()->get_account_page( 'forgot_password' ) ) . "\" class=\"ec_cart_login_complete_logout_link\">" . esc_attr( $link_text ) . "</a>";
	}

	public function display_cart_login_guest_button( $input ) {
		echo "<input type=\"submit\" id=\"ec_cart_login_guest_button\" name=\"ec_cart_login_guest_button\" class=\"ec_cart_login_button\" value=\"" . esc_attr( $input ) . "\" />";
	}

	public function display_cart_login_complete_user_name( $input ) {
		echo "<input type=\"hidden\" id=\"ec_cart_login_guest_text\" value=\"" . esc_attr( $input ) . "\" /><span id=\"ec_cart_login_complete_username\">";
		if ( $GLOBALS['ec_cart_data']->cart_data->username != "guest" )			
			echo esc_attr( $GLOBALS['ec_cart_data']->cart_data->username );
		else
			echo esc_attr( $input );
		echo "</span>";
	}

	public function display_cart_login_complete_signout_link( $input ) {
		if ( isset( $_GET['subscription'] ) ) {
			echo "<a href=\"" . esc_attr( $this->cart_page . $this->permalink_divider ) . "ec_cart_action=logout&subscription=" . esc_attr( sanitize_text_field( $_GET['subscription'] ) ) . "\" class=\"ec_cart_login_complete_logout_link\">" . esc_attr( $input ) . "</a>";
		} else {
			echo "<a href=\"" . esc_attr( $this->cart_page . $this->permalink_divider ) . "ec_cart_action=logout\" class=\"ec_cart_login_complete_logout_link\">" . esc_attr( $input ) . "</a>";
		}
	}

	/* END LOGIN/LOGOUT FUNCTIONS */

	/* START BILLING FUNCTIONS */
	public function display_checkout_details() {
		do_action( 'wp_easycart_display_checkout_details_pre' );
		if ( function_exists( 'wp_easycart_meta_initiate_checkout' ) && $this->cart->total_items > 0 ) {
			wp_easycart_meta_initiate_checkout( $this ); /* 6.0.2: checkout starts here ( once per checkout session ) */
		}
		if ( wp_easycart_onepage_active() ) {
			$current_screen = 'information';
			if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_checkout_v2.php' ) ) {
				include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_checkout_v2.php' );
			} else {
				include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_checkout_v2.php' );
			}
		} else {
			if ( $this->cart->total_items > 0 && apply_filters( 'wp_easycart_allow_checkout_details', 1 ) ) {
				if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_checkout_details.php' ) ) {
					include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_checkout_details.php' );
				} else {
					include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_checkout_details.php' );
				}
			}
		}
		do_action( 'wp_easycart_display_checkout_details_post' );
	}

	public function display_billing() {
		if (	$this->cart->total_items > 0 ) {
			if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_billing.php' ) )	
				include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_billing.php' );
			else
				include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_billing.php' );
		}
	}

	public function display_billing_input( $name ) {
		$auto_validate_css = ( wp_easycart_onepage_active() ) ? ' ec_cart_auto_validate_v2' : '';
		if ( 'country' == $name ) {
			if ( get_option( 'ec_option_use_country_dropdown' ) || 'square' == get_option( 'ec_option_payment_process_method' ) || 'stripe' == get_option( 'ec_option_payment_process_method' ) || 'stripe_connect' == get_option( 'ec_option_payment_process_method' ) || 'intuit' == get_option( 'ec_option_payment_process_method' ) || 'live' == $GLOBALS['ec_setting']->get_shipping_method() ) {
				$countries = $GLOBALS['ec_countries']->countries;
				if ( '' != $GLOBALS['ec_cart_data']->cart_data->billing_country && '0' != $GLOBALS['ec_cart_data']->cart_data->billing_country ) {
					$selected_country = $GLOBALS['ec_cart_data']->cart_data->billing_country;
				} else if ( 0 != $GLOBALS['ec_user']->billing->get_value( 'country2' ) ) {
					$selected_country = $GLOBALS['ec_user']->billing->get_value( 'country2' );
				} else if ( 1 == count( $countries ) ) {
					$selected_country = $countries[0]->iso2_cnt;
				} else if ( get_option( 'ec_option_default_country' ) ) {
					$selected_country = get_option( 'ec_option_default_country' );
				} else {
					$selected_country = $GLOBALS['ec_user']->billing->get_value( 'country2' );
				}

				echo '<select name="ec_cart_billing_country" id="ec_cart_billing_country" class="ec_cart_billing_input_text no_wrap' . esc_attr( $auto_validate_css ) . '" onchange="wpeasycart_cart_billing_country_update();">';
				echo '<option value="0">' . wp_easycart_language()->get_text( 'cart_billing_information', 'cart_billing_information_select_country' ) . '</option>';
				foreach ( $countries as $country) {
					echo '<option value="' . esc_attr( $country->iso2_cnt ) . '"';
					if ( $country->iso2_cnt == $selected_country ) {
						echo ' selected="selected"';
					}
					echo '>' . esc_attr( $country->name_cnt ) . '</option>';
				}
				echo '</select>';

			} else {
				if ( $GLOBALS['ec_cart_data']->cart_data->billing_country && '' != $GLOBALS['ec_cart_data']->cart_data->billing_country && '0' != $GLOBALS['ec_cart_data']->cart_data->billing_country  ) {
					$selected_country = $GLOBALS['ec_cart_data']->cart_data->billing_country;
				} else {
					$selected_country = $GLOBALS['ec_user']->billing->get_value( 'country' );
				}
				echo '<input type="text" name="ec_cart_billing_country" id="ec_cart_billing_country" class="ec_cart_billing_input_text' . esc_attr( $auto_validate_css ) . '" value="' . esc_attr( htmlspecialchars( $selected_country, ENT_QUOTES ) ) . '" />';
			}

		} else if ( 'state' == $name ) {
			if ( get_option( 'ec_option_use_country_dropdown' ) || 'square' == get_option( 'ec_option_payment_process_method' ) || 'stripe' == get_option( 'ec_option_payment_process_method' ) || 'stripe_connect' == get_option( 'ec_option_payment_process_method' ) || 'intuit' == get_option( 'ec_option_payment_process_method' ) || 'live' == $GLOBALS['ec_setting']->get_shipping_method() ) {
				$states = $this->mysqli->get_states();
				if ( '' != $GLOBALS['ec_cart_data']->cart_data->billing_state && '0' != $GLOBALS['ec_cart_data']->cart_data->billing_state ) {
					$selected_state = $GLOBALS['ec_cart_data']->cart_data->billing_state;
				} else {
					$selected_state = $GLOBALS['ec_user']->billing->get_value( 'state' );
				}
				if ( '' != $GLOBALS['ec_cart_data']->cart_data->billing_country && '0' != $GLOBALS['ec_cart_data']->cart_data->billing_country ) {
					$selected_country = $GLOBALS['ec_cart_data']->cart_data->billing_country;
				} else {
					$selected_country = $GLOBALS['ec_user']->billing->get_value( 'country2' );
				}
				$current_country = '';
				$close_last_state = false;
				$state_found = false;
				$current_state_group = '';
				$close_last_state_group = false;

				foreach ( $states as $state ) {
					if ( isset( $state->iso2_cnt ) ) {
						if ( $current_country != $state->iso2_cnt ) {
							if ( $close_last_state ) {
								echo "</select>";
							}
							echo '<select name="ec_cart_billing_state_' . esc_attr( $state->iso2_cnt ) . '" id="ec_cart_billing_state_' . esc_attr( $state->iso2_cnt ) . '" class="ec_cart_billing_input_text ec_billing_state_dropdown no_wrap' . esc_attr( $auto_validate_css ) . '"';
							if ( $state->iso2_cnt != $selected_country ) {
								echo ' style="display:none;"';
							} else {
								$state_found = true;
							}
							echo '>';

							if ( 'CA' == $state->iso2_cnt ) {
								echo '<option value="0">' . wp_easycart_language()->get_text( 'cart_billing_information', 'cart_billing_information_select_province' ) . '</option>';
							} else if ( 'GB' == $state->iso2_cnt ) {
								echo '<option value="0">' . wp_easycart_language()->get_text( 'cart_billing_information', 'cart_billing_information_select_county' ) . '</option>';
							} else if ( 'US' == $state->iso2_cnt ) {
								echo '<option value="0">' . wp_easycart_language()->get_text( 'cart_billing_information', 'cart_billing_information_select_state' ) . '</option>';
							} else {
								echo '<option value="0">' . wp_easycart_language()->get_text( 'cart_billing_information', 'cart_billing_information_select_other' ) . '</option>';
							}

							$current_country = $state->iso2_cnt;
							$close_last_state = true;
						}

						if ( $current_state_group != $state->group_sta && '' != $state->group_sta ) {
							if ( $close_last_state_group ) {
								echo '</optgroup>';
							}
							echo '<optgroup label="' . esc_attr( $state->group_sta ) . '">';
							$current_state_group = $state->group_sta;
							$close_last_state_group = true;
						}

						echo '<option value="' . esc_attr( $state->code_sta ) . '"';
						if ( $state->code_sta == $selected_state ) {
							echo ' selected="selected"';
						}
						echo '>' . esc_attr( $state->name_sta ) . '</option>';
					}
				}

				if ( $close_last_state_group ) {
					echo '</optgroup>';
				}

				echo '</select>';

				echo '<input type="text" name="ec_cart_billing_state" id="ec_cart_billing_state" class="ec_cart_billing_input_text' . esc_attr( $auto_validate_css ) . '" value="' . esc_attr( htmlspecialchars( $selected_state, ENT_QUOTES ) ) . '"';
				if ( $state_found ) {
					echo ' style="display:none;"';
				}
				echo ' />';

			} else {
				if ( get_option( 'ec_option_use_state_dropdown' ) ) {
					$states = $this->mysqli->get_states();
					if ( '' != $GLOBALS['ec_cart_data']->cart_data->billing_state && '0' != $GLOBALS['ec_cart_data']->cart_data->billing_state ) {
						$selected_state = $GLOBALS['ec_cart_data']->cart_data->billing_state;
					} else {
						$selected_state = $GLOBALS['ec_user']->billing->get_value( 'state' );
					}
					echo '<select name="ec_cart_billing_state" id="ec_cart_billing_state" class="ec_cart_billing_input_text no_wrap' . esc_attr( $auto_validate_css ) . '">';
					echo '<option value="0">' . wp_easycart_language()->get_text( 'cart_billing_information', 'cart_billing_information_select_state' ) . '</option>';
					foreach ( $states as $state ) {
						echo '<option value="' . esc_attr( $state->code_sta ) . '"';
						if ( $state->code_sta == $selected_state ) {
							echo ' selected="selected"';
						}
						echo '>' . esc_attr( $state->name_sta ) . '</option>';
					}
					echo '</select>';
				} else {
					if ( '' != $GLOBALS['ec_cart_data']->cart_data->billing_state && '0' != $GLOBALS['ec_cart_data']->cart_data->billing_state ) {
						$selected_state = $GLOBALS['ec_cart_data']->cart_data->billing_state;
					} else {
						$selected_state = $GLOBALS['ec_user']->billing->get_value( 'state' );
					}
					echo '<input type="text" name="ec_cart_billing_state" id="ec_cart_billing_state" class="ec_cart_billing_input_text' . esc_attr( $auto_validate_css ) . '" value="' . esc_attr( htmlspecialchars( $selected_state, ENT_QUOTES ) ) . '" />';
				}
			}

		} else {
			if ( 'first_name' == $name ) {
				$value = ( isset( $GLOBALS['ec_cart_data']->cart_data->billing_first_name ) && '' != $GLOBALS['ec_cart_data']->cart_data->billing_first_name ) ? $GLOBALS['ec_cart_data']->cart_data->billing_first_name : $GLOBALS['ec_user']->billing->get_value( $name );
			} else if ( 'last_name' == $name ) {
				$value = ( isset( $GLOBALS['ec_cart_data']->cart_data->billing_last_name ) && '' != $GLOBALS['ec_cart_data']->cart_data->billing_last_name ) ? $GLOBALS['ec_cart_data']->cart_data->billing_last_name : $GLOBALS['ec_user']->billing->get_value( $name );
			} else if ( 'company_name' == $name ) {
				$value = ( isset( $GLOBALS['ec_cart_data']->cart_data->billing_company_name ) && '' != $GLOBALS['ec_cart_data']->cart_data->billing_company_name ) ? $GLOBALS['ec_cart_data']->cart_data->billing_company_name : $GLOBALS['ec_user']->billing->get_value( $name );
			} else if ( 'address' == $name ) {
				$value = ( isset( $GLOBALS['ec_cart_data']->cart_data->billing_address_line_1 ) && '' != $GLOBALS['ec_cart_data']->cart_data->billing_address_line_1 ) ? $GLOBALS['ec_cart_data']->cart_data->billing_address_line_1 : $GLOBALS['ec_user']->billing->get_value( $name );
			} else if ( 'address2' == $name ) {
				$value = ( isset( $GLOBALS['ec_cart_data']->cart_data->billing_address_line_2 ) && '' != $GLOBALS['ec_cart_data']->cart_data->billing_address_line_2 ) ? $GLOBALS['ec_cart_data']->cart_data->billing_address_line_2 : $GLOBALS['ec_user']->billing->get_value( $name );
			} else if ( 'city' == $name ) {
				$value = ( isset( $GLOBALS['ec_cart_data']->cart_data->billing_city ) && '' != $GLOBALS['ec_cart_data']->cart_data->billing_city ) ? $GLOBALS['ec_cart_data']->cart_data->billing_city : $GLOBALS['ec_user']->billing->get_value( $name );
			} else if ( 'zip' == $name ) {
				$value = ( isset( $GLOBALS['ec_cart_data']->cart_data->billing_zip ) && '' != $GLOBALS['ec_cart_data']->cart_data->billing_zip ) ? $GLOBALS['ec_cart_data']->cart_data->billing_zip : $GLOBALS['ec_user']->billing->get_value( $name );
			} else if ( 'phone' == $name ) {
				$value = ( isset( $GLOBALS['ec_cart_data']->cart_data->billing_phone ) && '' != $GLOBALS['ec_cart_data']->cart_data->billing_phone ) ? $GLOBALS['ec_cart_data']->cart_data->billing_phone : $GLOBALS['ec_user']->billing->get_value( $name );
			}
			echo '<input type="text" name="ec_cart_billing_' . esc_attr( $name ) . '" id="ec_cart_billing_' . esc_attr( $name ) . '" class="ec_cart_billing_input_text' . esc_attr( $auto_validate_css ) . '" value="' . esc_attr( htmlspecialchars( $value, ENT_QUOTES ) ) . '" />';
		}
	}

	public function display_vat_registration_number_input() {
		if ( isset( $GLOBALS['ec_cart_data']->cart_data->vat_registration_number ) && '' != $GLOBALS['ec_cart_data']->cart_data->vat_registration_number ) {
			$value = $GLOBALS['ec_cart_data']->cart_data->vat_registration_number;
		} else {
			$value = $GLOBALS['ec_user']->vat_registration_number;
		}
		echo '<input type="text" name="ec_cart_billing_' . esc_attr( 'vat_registration_number' ) . '" id="ec_cart_billing_' . esc_attr( 'vat_registration_number' ) . '" class="ec_cart_billing_input_text" value="' . esc_attr( htmlspecialchars( $value, ENT_QUOTES ) ) . '" />';
	}
	/* END BILLING FUNCTIONS */

	/* START SHIPPING FUNCTIONS */
	public function display_shipping() {
		if (	$this->cart->total_items > 0 ) {
			if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_shipping.php' ) )	
				include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_shipping.php' );
			else
				include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_shipping.php' );
		}
	}

	public function display_shipping_selector( $first_opt, $second_opt ) {
		if ( $this->cart->shipping_subtotal > 0 )
			echo "<div class=\"ec_cart_shipping_selector_row\">";
		else
			echo "<div class=\"ec_cart_shipping_selector_row_hidden\">";

		echo "<input type=\"radio\" name=\"ec_shipping_selector\" id=\"ec_cart_use_billing_for_shipping\" value=\"false\"";
		if ( $GLOBALS['ec_cart_data']->cart_data->shipping_selector == "" || ( $GLOBALS['ec_cart_data']->cart_data->shipping_selector != "" && $GLOBALS['ec_cart_data']->cart_data->shipping_selector == "false" ) )
		echo " checked=\"checked\"";
		echo " onchange=\"ec_cart_use_billing_for_shipping_change(); return false;\" />" . esc_attr( $first_opt );
		echo "</div>";

		if ( get_option( 'ec_option_use_shipping' ) ) {
			if ( $this->cart->shipping_subtotal > 0 )
				echo "<div class=\"ec_cart_shipping_selector_row\">";
			else
				echo "<div class=\"ec_cart_shipping_selector_row_hidden\">";

			echo "<input type=\"radio\" name=\"ec_shipping_selector\" id=\"ec_cart_use_shipping_for_shipping\" value=\"true\"";
			if ( $GLOBALS['ec_cart_data']->cart_data->shipping_selector != "" && $GLOBALS['ec_cart_data']->cart_data->shipping_selector == "true" )
			echo " checked=\"checked\"";
			echo " onchange=\"ec_cart_use_shipping_for_shipping_change(); return false;\" />" . esc_attr( $second_opt );
			echo "</div>";
		} else {
			echo "<script>jQuery('.ec_cart_shipping_selector_row').hide();</script>";	
		}
	}

	public function display_shipping_input( $name ) {
		$auto_validate_css = ( wp_easycart_onepage_active() ) ? ' ec_cart_auto_validate_v2' : '';
		if ( 'country' == $name ) {
			if ( get_option( 'ec_option_use_country_dropdown' ) || 'square' == get_option( 'ec_option_payment_process_method' ) || 'stripe' == get_option( 'ec_option_payment_process_method' ) || 'stripe_connect' == get_option( 'ec_option_payment_process_method' ) || 'intuit' == get_option( 'ec_option_payment_process_method' ) || 'live' == $GLOBALS['ec_setting']->get_shipping_method() ) {
				$countries = $GLOBALS['ec_countries']->countries;
				if ( '' != $GLOBALS['ec_cart_data']->cart_data->shipping_country && '0' != $GLOBALS['ec_cart_data']->cart_data->shipping_country ) {
					$selected_country = $GLOBALS['ec_cart_data']->cart_data->shipping_country;
				} else if ( 0 != $GLOBALS['ec_user']->shipping->get_value( 'country2' ) ) {
					$selected_country = $GLOBALS['ec_user']->shipping->get_value( 'country2' );
				} else if ( 1 == count( $countries ) ) {
					$selected_country = $countries[0]->iso2_cnt;
				} else if ( get_option( 'ec_option_default_country' ) ) {
					$selected_country = get_option( 'ec_option_default_country' );
				} else {
					$selected_country = $GLOBALS['ec_user']->shipping->get_value( 'country2' );
				}
				echo '<select name="ec_cart_shipping_country" id="ec_cart_shipping_country" class="ec_cart_shipping_input_text no_wrap' . esc_attr( $auto_validate_css ) . '">';
				echo '<option value="0">' . wp_easycart_language()->get_text( 'cart_shipping_information', 'cart_shipping_information_select_country' ) . '</option>';
				foreach ( $countries as $country ) {
					echo '<option value="' . esc_attr( $country->iso2_cnt ) . '"';
					if ( $country->iso2_cnt == $selected_country ) {
						echo ' selected="selected"';
					}
					echo '>' . esc_attr( $country->name_cnt ) . '</option>';
				}
				echo '</select>';

			} else {
				if ( '' != $GLOBALS['ec_cart_data']->cart_data->shipping_country && 0 != $GLOBALS['ec_cart_data']->cart_data->shipping_country ) {
					$selected_country = $GLOBALS['ec_cart_data']->cart_data->shipping_country;
				} else {
					$selected_country = $GLOBALS['ec_user']->shipping->get_value( 'country' );
				}
				echo '<input type="text" name="ec_cart_shipping_country" id="ec_cart_shipping_country" class="ec_cart_shipping_input_text' . esc_attr( $auto_validate_css ) . '" value="' . esc_attr( htmlspecialchars( $selected_country, ENT_QUOTES ) ) . '" />';
			}

		} else if ( 'state' == $name ) {
			if ( get_option( 'ec_option_use_country_dropdown' ) || 'square' == get_option( 'ec_option_payment_process_method' ) || 'stripe' == get_option( 'ec_option_payment_process_method' ) || 'stripe_connect' == get_option( 'ec_option_payment_process_method' ) || 'intuit' == get_option( 'ec_option_payment_process_method' ) || 'live' == $GLOBALS['ec_setting']->get_shipping_method() ) {
				$states = $this->mysqli->get_states();
				if ( '' != $GLOBALS['ec_cart_data']->cart_data->shipping_state && 0 != $GLOBALS['ec_cart_data']->cart_data->shipping_state ) {
					$selected_state = $GLOBALS['ec_cart_data']->cart_data->shipping_state;
				} else {
					$selected_state = $GLOBALS['ec_user']->shipping->get_value( 'state' );
				}
				if ( '' != $GLOBALS['ec_cart_data']->cart_data->shipping_country && 0 != $GLOBALS['ec_cart_data']->cart_data->shipping_country ) {
					$selected_country = $GLOBALS['ec_cart_data']->cart_data->shipping_country;
				} else {
					$selected_country = $GLOBALS['ec_user']->shipping->get_value( 'country2' );
				}
				$current_country = '';
				$close_last_state = false;
				$state_found = false;
				$current_state_group = '';
				$close_last_state_group = false;

				foreach ( $states as $state ) {
					if ( $current_country != $state->iso2_cnt ) {
						if ( $close_last_state ) {
							echo '</select>';
						}
						echo '<select name="ec_cart_shipping_state_' . esc_attr( $state->iso2_cnt ) . '" id="ec_cart_shipping_state_' . esc_attr( $state->iso2_cnt ) . '" class="ec_cart_shipping_input_text ec_shipping_state_dropdown no_wrap' . esc_attr( $auto_validate_css ) . '"';
						if ( $state->iso2_cnt != $selected_country ) {
							echo ' style="display:none;"';
						} else {
							$state_found = true;
						}
						echo '>';

						if ( 'CA' == $state->iso2_cnt ) {
							echo '<option value="0">' . wp_easycart_language()->get_text( 'cart_shipping_information', 'cart_shipping_information_select_province' ) . '</option>';
						} else if ( 'GB' == $state->iso2_cnt ) {
							echo '<option value="0">' . wp_easycart_language()->get_text( 'cart_shipping_information', 'cart_shipping_information_select_county' ) . '</option>';
						} else if ( 'US' == $state->iso2_cnt ) {
							echo '<option value="0">' . wp_easycart_language()->get_text( 'cart_shipping_information', 'cart_shipping_information_select_state' ) . '</option>';
						} else {
							echo '<option value="0">' . wp_easycart_language()->get_text( 'cart_shipping_information', 'cart_shipping_information_select_other' ) . '</option>';
						}

						$current_country = $state->iso2_cnt;
						$close_last_state = true;
					}

					if ( $current_state_group != $state->group_sta && '' != $state->group_sta ) {
						if ( $close_last_state_group ) {
							echo '</optgroup>';
						}
						echo '<optgroup label="' . esc_attr( $state->group_sta ) . '">';
						$current_state_group = $state->group_sta;
						$close_last_state_group = true;
					}

					echo '<option value="' . esc_attr( $state->code_sta ) . '"';
					if ( $state->code_sta == $selected_state ) {
						echo ' selected="selected"';
					}
					echo '>' . esc_attr( $state->name_sta ) . '</option>';
				}

				if ( $close_last_state_group ) {
					echo "</optgroup>";
				}

				echo '</select>';

				echo '<input type="text" name="ec_cart_shipping_state" id="ec_cart_shipping_state" class="ec_cart_shipping_input_text' . esc_attr( $auto_validate_css ) . '" value="' . esc_attr( htmlspecialchars( $selected_state, ENT_QUOTES ) ) . '"';
				if ( $state_found ) {
					echo ' style="display:none;"';
				}
				echo ' />';

			} else {
				if ( get_option( 'ec_option_use_state_dropdown' ) ) {
					$states = $this->mysqli->get_states();
					if ( '' != $GLOBALS['ec_cart_data']->cart_data->shipping_state && 0 != $GLOBALS['ec_cart_data']->cart_data->shipping_state ) {
						$selected_state = $GLOBALS['ec_cart_data']->cart_data->shipping_state;
					} else {
						$selected_state = $GLOBALS['ec_user']->shipping->get_value( 'state' );
					}
					echo '<select name="ec_cart_shipping_state" id="ec_cart_shipping_state" class="ec_cart_shipping_input_text no_wrap' . esc_attr( $auto_validate_css ) . '">';
					echo '<option value="0">' . wp_easycart_language()->get_text( 'cart_shipping_information', 'cart_shipping_information_select_state' ) . '</option>';
					foreach ( $states as $state ) {
						echo '<option value="' . esc_attr( $state->code_sta ) . '"';
						if ( $state->code_sta == $selected_state ) {
							echo ' selected="selected"';
						}
						echo '>' . esc_attr( $state->name_sta ) . '</option>';
					}
					echo '</select>';

				} else {
					if ( '' != $GLOBALS['ec_cart_data']->cart_data->shipping_state && 0 != $GLOBALS['ec_cart_data']->cart_data->shipping_state ) {
						$selected_state = $GLOBALS['ec_cart_data']->cart_data->shipping_state;
					} else {
						$selected_state = $GLOBALS['ec_user']->shipping->get_value( 'state' );
					}
					echo '<input type="text" name="ec_cart_shipping_state" id="ec_cart_shipping_state" class="ec_cart_shipping_input_text' . esc_attr( $auto_validate_css ) . '" value="' . esc_attr( htmlspecialchars( $selected_state, ENT_QUOTES ) ) . '" />';
				}
			}

		} else {
			if ( 'first_name' == $name ) {
				$value = ( isset( $GLOBALS['ec_cart_data']->cart_data->shipping_first_name ) && '' != $GLOBALS['ec_cart_data']->cart_data->shipping_first_name ) ? $GLOBALS['ec_cart_data']->cart_data->shipping_first_name : $GLOBALS['ec_user']->shipping->get_value( $name );
			} else if ( 'last_name' == $name ) {
				$value = ( isset( $GLOBALS['ec_cart_data']->cart_data->shipping_last_name ) && '' != $GLOBALS['ec_cart_data']->cart_data->shipping_last_name ) ? $GLOBALS['ec_cart_data']->cart_data->shipping_last_name : $GLOBALS['ec_user']->shipping->get_value( $name );
			} else if ( 'company_name' == $name ) {
				$value = ( isset( $GLOBALS['ec_cart_data']->cart_data->shipping_company_name ) && '' != $GLOBALS['ec_cart_data']->cart_data->shipping_company_name ) ? $GLOBALS['ec_cart_data']->cart_data->shipping_company_name : $GLOBALS['ec_user']->shipping->get_value( $name );
			} else if ( 'address' == $name ) {
				$value = ( isset( $GLOBALS['ec_cart_data']->cart_data->shipping_address_line_1 ) && '' != $GLOBALS['ec_cart_data']->cart_data->shipping_address_line_1 ) ? $GLOBALS['ec_cart_data']->cart_data->shipping_address_line_1 : $GLOBALS['ec_user']->shipping->get_value( $name );
			} else if ( 'address2' == $name ) {
				$value = ( isset( $GLOBALS['ec_cart_data']->cart_data->shipping_address_line_2 ) && '' != $GLOBALS['ec_cart_data']->cart_data->shipping_address_line_2 ) ? $GLOBALS['ec_cart_data']->cart_data->shipping_address_line_2 : $GLOBALS['ec_user']->shipping->get_value( $name );
			} else if ( 'city' == $name ) {
				$value = ( isset( $GLOBALS['ec_cart_data']->cart_data->shipping_city ) && '' != $GLOBALS['ec_cart_data']->cart_data->shipping_city ) ? $GLOBALS['ec_cart_data']->cart_data->shipping_city : $GLOBALS['ec_user']->shipping->get_value( $name );
			} else if ( 'zip' == $name ) {
				$value = ( isset( $GLOBALS['ec_cart_data']->cart_data->shipping_zip ) && '' != $GLOBALS['ec_cart_data']->cart_data->shipping_zip ) ? $GLOBALS['ec_cart_data']->cart_data->shipping_zip : $GLOBALS['ec_user']->shipping->get_value( $name );
			} else if ( 'phone' == $name ) {
				$value = ( isset( $GLOBALS['ec_cart_data']->cart_data->shipping_phone ) && '' != $GLOBALS['ec_cart_data']->cart_data->shipping_phone ) ? $GLOBALS['ec_cart_data']->cart_data->shipping_phone : $GLOBALS['ec_user']->shipping->get_value( $name );
			}
			echo '<input type="text" name="ec_cart_shipping_' . esc_attr( $name ) . '" id="ec_cart_shipping_' . esc_attr( $name ) . '" class="ec_cart_shipping_input_text' . esc_attr( $auto_validate_css ) . '" value="' . esc_attr( htmlspecialchars( $value, ENT_QUOTES ) ) . '" />';
		}
	}
	/* END SHIPPING FUNCTIONS */

	/* START SHIPPING METHOD FUNCTIONS */
	public function display_shipping_method() {
		do_action( 'wp_easycart_display_shipping_method_pre' );
		if ( wp_easycart_onepage_active() ) {
			if ( function_exists( 'wp_easycart_meta_initiate_checkout' ) && $this->cart->total_items > 0 ) {
				wp_easycart_meta_initiate_checkout( $this ); /* 6.0.2: a one-page checkout opened at this step still starts checkout */
			}
			$current_screen = 'shipping';
			if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_checkout_v2.php' ) ) {
				include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_checkout_v2.php' );
			} else {
				include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_checkout_v2.php' );
			}
		} else {
			if ( $this->cart->total_items > 0 && apply_filters( 'wp_easycart_allow_shipping_method', 1 ) ) {
				if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_shipping_method.php' ) ) {
					include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_shipping_method.php' );
				} else {
					include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_shipping_method.php' );
				}
			}
		}
		do_action( 'wp_easycart_display_shipping_method_post' );
	}

	public function ec_cart_display_shipping_methods( $standard_text, $express_text, $ship_method ) {
		$this->shipping->print_shipping_options( $standard_text, $express_text, $this->discount );
	}

	public function ec_cart_display_shipping_methods_stripe_dynamic( $standard_text, $express_text ) {
		return $this->shipping->get_shipping_rate_data( $standard_text, $express_text, 100, $this->discount );
	}

	public function ec_cart_display_shipping_methods_square_dynamic( $standard_text, $express_text ) {
		return $this->shipping->get_shipping_rate_data( $standard_text, $express_text, 1, $this->discount  );
	}

	public function ec_cart_display_shipping_methods_paypal_dynamic() {
		return $this->shipping->get_shipping_rate_data( wp_easycart_language()->get_text( 'cart_estimate_shipping', 'cart_estimate_shipping_standard' ), wp_easycart_language()->get_text( 'cart_estimate_shipping', 'cart_estimate_shipping_express' ), 1, $this->discount );
	}

	/**
	 * The totals an Apple Pay / Google Pay button charges: a card payment, so flex fees for Card apply whatever payment
	 * method the page has selected ( the page's own totals follow its selection ). The same object as order_totals when
	 * nothing differs.
	 *
	 * @since 6.0.2
	 * @return ec_order_totals
	 */
	public function get_wallet_order_totals() {
		if ( ! isset( $this->tax ) || ! ( $this->tax instanceof ec_tax ) || ! $this->tax->has_payment_type_fees() || 'card' === ec_tax::fee_payment_type() ) {
			return $this->order_totals;
		}
		$previous  = ec_tax::use_fee_payment_type( 'card' );
		$tax       = clone $this->tax;
		$tax->fees = $tax->calculate_fees();
		$totals    = new ec_order_totals( $this->cart, $GLOBALS['ec_user'], $this->shipping, $tax, $this->discount );
		ec_tax::use_fee_payment_type( $previous );
		return $totals;
	}

	public function get_stripe_express_cart_items() {
		$return_arr = array();
		for ( $i = 0; $i < count( $this->cart->cart ); $i++ ) {
			$return_arr[] = (object) array(
				'name' => esc_attr( $this->cart->cart[$i]->title ),
				'amount' => (int) esc_attr( number_format( $this->cart->cart[$i]->total_price * 100, 0, '', '' ) ),
			);
		}
		if ( $this->order_totals->tax_total > 0 ) {
			$return_arr[] = (object) array(
				'name'     => wp_easycart_language()->get_text( 'cart_totals', 'cart_totals_tax' ),
				'amount'    => (int) esc_attr( number_format( $this->order_totals->tax_total * 100, 0, '.', '' ) ),
			);
		}

		if ( $this->order_totals->tip_total > 0 ) {
			$return_arr[] = (object) array(
				'name'     => wp_easycart_language()->get_text( 'cart_totals', 'cart_totals_tip' ),
				'amount'    => (int) esc_attr( number_format( $this->order_totals->tip_total * 100, 0, '.', '' ) ),
			);
		}

		if ( get_option( 'ec_option_use_shipping' ) && ( $this->cart->shippable_total_items > 0 || $this->order_totals->handling_total > 0 ) && $this->order_totals->shipping_total > 0 ) {
			$return_arr[] = (object) array(
				'name'     => wp_easycart_language()->get_text( 'cart_totals', 'cart_totals_shipping' ),
				'amount'    => (int) esc_attr( number_format( $this->order_totals->shipping_total * 100, 0, '.', '' ) ),
			);
		}

		if ( $this->order_totals->discount_total > 0 ) {
			$return_arr[] = (object) array(
				'name'     => wp_easycart_language()->get_text( 'cart_totals', 'cart_totals_discounts' ),
				'amount'    => (int) esc_attr( number_format( $this->order_totals->discount_total * 100, 0, '.', '' ) ),
			);
		}

		if ( $this->tax->is_duty_enabled() && $this->order_totals->duty_total > 0 ) {
			$return_arr[] = (object) array(
				'name'     => wp_easycart_language()->get_text( 'cart_totals', 'cart_totals_duty' ),
				'amount'    => (int) esc_attr( number_format( $this->order_totals->duty_total * 100, 0, '.', '' ) ),
			);
		}

		if ( $this->tax->is_vat_enabled() && $this->order_totals->vat_total > 0 ) {
			$return_arr[] = (object) array(
				'name'     => wp_easycart_language()->get_text( 'cart_totals', 'cart_totals_vat' ),
				'amount'    => (int) esc_attr( number_format( $this->order_totals->vat_total * 100, 0, '.', '' ) ),
			);
		}

		if ( get_option( 'ec_option_enable_easy_canada_tax' ) && $this->order_totals->gst_total > 0  ) {
			$return_arr[] = (object) array(
				'name'     => wp_easycart_canada_tax_label( 'gst', $this->tax->shipping_state ) . ( ( $this->tax->gst_rate > 0 ) ? ' ' .$this->tax->gst_rate . '%' : '' ),
				'amount'    => (int) esc_attr( number_format( $this->order_totals->gst_total * 100, 0, '.', '' ) ),
			);
		}

		if ( get_option( 'ec_option_enable_easy_canada_tax' ) && $this->order_totals->pst_total > 0  ) {
			$return_arr[] = (object) array(
				'name'     => wp_easycart_canada_tax_label( 'pst', $this->tax->shipping_state ) . ( ( $this->tax->pst_rate > 0 ) ? ' ' .$this->tax->pst_rate . '%' : '' ),
				'amount'    => (int) esc_attr( number_format( $this->order_totals->pst_total * 100, 0, '.', '' ) ),
			);
		}

		if ( get_option( 'ec_option_enable_easy_canada_tax' ) && $this->order_totals->hst_total > 0 ) {
			$return_arr[] = (object) array(
				'name'     => wp_easycart_canada_tax_label( 'hst', $this->tax->shipping_state ) . ( ( $this->tax->hst_rate > 0 ) ? ' ' .$this->tax->hst_rate . '%' : '' ),
				'amount'    => (int) esc_attr( number_format( $this->order_totals->hst_total * 100, 0, '.', '' ) ),
			);
		}
		return $return_arr;
	}

	public function get_stripe_express_shipping_items( $standard_text, $express_text ) {
		$shipping_rates = $this->shipping->get_shipping_rate_data( $standard_text, $express_text, 100, $this->discount );
		$rate_items = array();
		for ( $i = 0; $i < count( $shipping_rates ); $i++ ) {
			$rate_items[] = (object) array(
				'id' => (string) esc_attr( $shipping_rates[ $i ]->id ),
				'displayName' => (string) esc_attr( $shipping_rates[ $i ]->label ),
				'amount' => (int) esc_attr( number_format( $shipping_rates[ $i ]->amount, 0, '', '' ) ),
			);
		}
		return $rate_items;
	}

	/**
	 * Unpaid order a pay link opens ( ec_page=invoice&order_id=…&ec_guest_key=… ).
	 *
	 * The id and the key must both match. Guest keys are not unique ( a returning guest's
	 * orders share one ) and orders duplicated in the admin have none, so the key alone
	 * could open a different order.
	 *
	 * @since 6.0.2
	 *
	 * @param int    $order_id  Order id from the link.
	 * @param string $guest_key Guest key from the link ( 30 letters, A-Z ).
	 * @return object|false The ec_order row, or false when no unpaid order has that id and key.
	 */
	public static function get_invoice_order( $order_id, $guest_key ) {
		global $wpdb;
		$order_id  = (int) $order_id;
		$guest_key = strtoupper( trim( (string) $guest_key ) );
		if ( $order_id <= 0 || ! preg_match( '/^[A-Z]{30}$/', $guest_key ) ) {
			return false;
		}
		$order = $wpdb->get_row( $wpdb->prepare( "SELECT ec_order.* FROM ec_order, ec_orderstatus WHERE ec_order.order_id = %d AND ec_order.guest_key = %s AND ec_order.guest_key != '' AND ec_orderstatus.status_id = ec_order.orderstatus_id AND ec_orderstatus.is_approved = 0", $order_id, $guest_key ) );
		return ( $order ) ? $order : false;
	}

	/**
	 * Transient holding this cart session's pay-link Stripe payments.
	 *
	 * Each entry, keyed by order id, is the order's guest key and the PaymentIntent the page
	 * created for that order's total. It lives outside ec_tempcart_data so the invoice intent
	 * never replaces the cart's own intent ( the cart pages update that one's amount ).
	 *
	 * @since 6.0.2
	 *
	 * @return string|false Transient name, or false when there is no cart session.
	 */
	private static function invoice_payment_bindings_key() {
		$session_id = ( isset( $GLOBALS['ec_cart_data'] ) && isset( $GLOBALS['ec_cart_data']->ec_cart_id ) ) ? (string) $GLOBALS['ec_cart_data']->ec_cart_id : '';
		if ( '' === $session_id || 'not-set' === $session_id ) {
			return false;
		}
		return 'wpec_invoice_pay_' . md5( $session_id );
	}

	/**
	 * Pay-link Stripe payments bound to this cart session ( see invoice_payment_bindings_key() ).
	 *
	 * @since 6.0.2
	 *
	 * @return array Order id => array( 'guest_key', 'payment_intent_id', 'client_secret' ).
	 */
	private static function get_invoice_payment_bindings() {
		$key = self::invoice_payment_bindings_key();
		if ( ! $key ) {
			return array();
		}
		$bindings = get_transient( $key );
		return ( is_array( $bindings ) ) ? $bindings : array();
	}

	/**
	 * Save this session's pay-link Stripe payments, keeping the five most recent.
	 *
	 * @since 6.0.2
	 *
	 * @param array $bindings Order id => binding.
	 * @return bool
	 */
	private static function save_invoice_payment_bindings( $bindings ) {
		$key = self::invoice_payment_bindings_key();
		if ( ! $key ) {
			return false;
		}
		if ( empty( $bindings ) ) {
			delete_transient( $key );
			return true;
		}
		return set_transient( $key, array_slice( $bindings, -5, null, true ), DAY_IN_SECONDS );
	}

	/**
	 * Whether a PaymentIntent charges exactly an order's grand total: same minor-unit amount
	 * ( worked out the way create_payment_intent() does ) and the store's Stripe currency.
	 *
	 * @since 6.0.2
	 *
	 * @param object $payment_intent Stripe PaymentIntent.
	 * @param object $order          ec_order row.
	 * @return bool
	 */
	private static function stripe_intent_matches_order( $payment_intent, $order ) {
		if ( ! is_object( $payment_intent ) || ! isset( $payment_intent->amount ) || ! isset( $payment_intent->currency ) || ! is_object( $order ) || ! isset( $order->grand_total ) ) {
			return false;
		}
		$amount = (int) number_format( (float) $order->grand_total * 100, 0, '', '' );
		return ( $amount > 0 && (int) $payment_intent->amount === $amount && strtolower( (string) $payment_intent->currency ) === strtolower( (string) get_option( 'ec_option_stripe_currency' ) ) );
	}

	/**
	 * Client secret for the card form on a pay link.
	 *
	 * The order gets its own PaymentIntent for its grand total, bound to this session with the
	 * order's id and guest key. An earlier intent is reused only while it is still payable and
	 * still matches the order's total.
	 *
	 * @since 6.0.2
	 *
	 * @param ec_stripe|ec_stripe_connect $stripe  Gateway.
	 * @param object                      $invoice ec_order row from get_invoice_order().
	 * @return string Client secret, or '' when no intent could be created.
	 */
	private function get_stripe_invoice_intent_client_secret( $stripe, $invoice ) {
		if ( ! self::invoice_payment_bindings_key() ) {
			return '';
		}
		$order_id = (int) $invoice->order_id;
		$bindings = self::get_invoice_payment_bindings();
		if ( isset( $bindings[ $order_id ] ) && is_array( $bindings[ $order_id ] ) && isset( $bindings[ $order_id ]['guest_key'] ) && (string) $invoice->guest_key === $bindings[ $order_id ]['guest_key'] && ! empty( $bindings[ $order_id ]['payment_intent_id'] ) ) {
			$response = $stripe->get_payment_intent( $bindings[ $order_id ]['payment_intent_id'] );
			if ( $response && isset( $response->status ) && ! in_array( $response->status, array( 'succeeded', 'canceled' ), true ) && self::stripe_intent_matches_order( $response, $invoice ) ) {
				return ( ! empty( $response->client_secret ) ) ? $response->client_secret : $bindings[ $order_id ]['client_secret'];
			}
		}

		$response = $stripe->create_payment_intent( $invoice );
		if ( ! $response || empty( $response->id ) || empty( $response->client_secret ) ) {
			return '';
		}
		unset( $bindings[ $order_id ] );
		$bindings[ $order_id ] = array(
			'guest_key'         => (string) $invoice->guest_key,
			'payment_intent_id' => (string) $response->id,
			'client_secret'     => (string) $response->client_secret,
		);
		if ( ! self::save_invoice_payment_bindings( $bindings ) ) {
			return '';
		}
		return $response->client_secret;
	}

	/**
	 * The unpaid order and the payment made for it on its pay link in this session.
	 *
	 * The order id, guest key and PaymentIntent all come from the binding made when the page
	 * created the intent; $order_id only says which of this session's pay links is finishing.
	 * The order must still be unpaid, and the intent must have been paid ( or be processing
	 * or authorised ) for exactly the order's grand total.
	 *
	 * @since 6.0.2
	 *
	 * @param ec_stripe|ec_stripe_connect $stripe   Gateway.
	 * @param int                         $order_id Order the page says it is paying.
	 * @return object|false Object with 'order' ( ec_order row ) and 'payment_intent', or false.
	 */
	public function get_verified_stripe_invoice_payment( $stripe, $order_id ) {
		$order_id = (int) $order_id;
		$bindings = self::get_invoice_payment_bindings();
		if ( $order_id <= 0 || ! isset( $bindings[ $order_id ] ) || ! is_array( $bindings[ $order_id ] ) || empty( $bindings[ $order_id ]['guest_key'] ) || empty( $bindings[ $order_id ]['payment_intent_id'] ) ) {
			return false;
		}
		$binding = $bindings[ $order_id ];

		$order = self::get_invoice_order( $order_id, $binding['guest_key'] );
		if ( ! $order ) {
			return false;
		}

		$payment_intent = $stripe->get_payment_intent( $binding['payment_intent_id'] );
		if ( ! $payment_intent || ! isset( $payment_intent->id ) || $binding['payment_intent_id'] !== $payment_intent->id ) {
			return false;
		}
		if ( ! isset( $payment_intent->status ) || ! in_array( $payment_intent->status, array( 'succeeded', 'processing', 'requires_capture' ), true ) ) {
			return false;
		}
		if ( ! self::stripe_intent_matches_order( $payment_intent, $order ) ) {
			$this->mysqli->insert_response( (int) $order->order_id, 1, 'Stripe Invoice Payment', 'PaymentIntent ' . $payment_intent->id . ' (' . (int) $payment_intent->amount . ' ' . $payment_intent->currency . ') does not match the order total ' . $order->grand_total . '; the order was not updated.' );
			return false;
		}

		return (object) array(
			'order'          => $order,
			'payment_intent' => $payment_intent,
		);
	}

	/**
	 * Where to send the shopper when a pay-link payment cannot be completed: back to that
	 * order's pay link when this session opened it, otherwise the cart.
	 *
	 * @since 6.0.2
	 *
	 * @param int $order_id Order the page says it is paying.
	 * @return string URL.
	 */
	public function get_invoice_payment_failed_url( $order_id ) {
		$order_id = (int) $order_id;
		$bindings = self::get_invoice_payment_bindings();
		if ( $order_id > 0 && isset( $bindings[ $order_id ]['guest_key'] ) && '' !== $bindings[ $order_id ]['guest_key'] ) {
			return wpeasycart_links()->get_cart_page(
				'invoice',
				array(
					'order_id'     => $order_id,
					'ec_guest_key' => $bindings[ $order_id ]['guest_key'],
				)
			);
		}
		return wpeasycart_links()->get_cart_page();
	}

	/**
	 * Forget a pay-link payment once it has been recorded on its order.
	 *
	 * @since 6.0.2
	 *
	 * @param int $order_id Order id.
	 */
	private static function forget_invoice_payment_binding( $order_id ) {
		$bindings = self::get_invoice_payment_bindings();
		if ( isset( $bindings[ (int) $order_id ] ) ) {
			unset( $bindings[ (int) $order_id ] );
			self::save_invoice_payment_bindings( $bindings );
		}
	}

	/**
	 * Whether checkout protection is holding Stripe's payment form back for this shopper ( paused, or a human check
	 * owed ). The payment scripts are then left out entirely, so no "Stripe has a problem" alert shows; the
	 * storefront protection script shows the pause or the check in their place.
	 *
	 * @since 6.0.2
	 * @return bool
	 */
	public function stripe_held_by_protection() {
		return class_exists( 'wp_easycart_checkout_guard' ) && ! wp_easycart_checkout_guard::stripe_allowed();
	}

	public function get_stripe_intent_client_secret( $order_totals = false ) {
		if ( get_option( 'ec_option_payment_process_method' ) == 'stripe' || get_option( 'ec_option_payment_process_method' ) == 'stripe_connect' ) {
			if ( get_option( 'ec_option_payment_process_method' ) == 'stripe' ) {
				$stripe = new ec_stripe();
			} else {
				$stripe = new ec_stripe_connect();
			}
			// The pay link passes the order it opened: that order gets its own bound intent ( 6.0.2 ).
			if ( null !== $this->invoice_order && is_object( $order_totals ) && isset( $order_totals->order_id ) && (int) $order_totals->order_id === (int) $this->invoice_order->order_id ) {
				return $this->get_stripe_invoice_intent_client_secret( $stripe, $this->invoice_order );
			}
			if ( ! $order_totals ) {
				$order_totals = $this->order_totals;
			}
			/* 6.0.2 checkout protection: a paused shopper, or one who owes a human check, gets no client secret ( the
			   storefront script shows the pause or the check instead, then reloads the step once it is passed ). */
			if ( class_exists( 'wp_easycart_checkout_guard' ) ) {
				wp_easycart_checkout_guard::mark_seen();
				if ( ! wp_easycart_checkout_guard::stripe_allowed() ) {
					return '';
				}
			}
			$cart_data = $this->mysqli->get_cart_data( $GLOBALS['ec_cart_data']->ec_cart_id );
			if ( $cart_data->stripe_paymentintent_id == '' || $cart_data->stripe_pi_client_secret == '' ) {
				$response = $stripe->create_payment_intent( $order_totals );
				if ( $response ) {
					$GLOBALS['ec_cart_data']->cart_data->stripe_paymentintent_id = $response->id;
					$GLOBALS['ec_cart_data']->cart_data->stripe_pi_client_secret = $response->client_secret;
					$GLOBALS['ec_cart_data']->save_session_to_db();
					wp_cache_flush();
					if ( class_exists( 'wp_easycart_checkout_guard' ) ) {
						wp_easycart_checkout_guard::map_intent( $response->id );
					}
				}
				do_action( 'wpeasycart_cart_updated' );
			} else {
				$response = $stripe->get_payment_intent( $cart_data->stripe_paymentintent_id );
				if ( ! $response || $response->status == 'succeeded' || $response->status == 'canceled' ) {
					$response = $stripe->create_payment_intent( $order_totals );
					if ( $response ) {
						$GLOBALS['ec_cart_data']->cart_data->stripe_paymentintent_id = $response->id;
						$GLOBALS['ec_cart_data']->cart_data->stripe_pi_client_secret = $response->client_secret;
						$GLOBALS['ec_cart_data']->save_session_to_db();
						wp_cache_flush();
						if ( class_exists( 'wp_easycart_checkout_guard' ) ) {
							wp_easycart_checkout_guard::map_intent( $response->id );
						}
					}
					do_action( 'wpeasycart_cart_updated' );
				} else {
					return $cart_data->stripe_pi_client_secret;
				}
			}

			if ( $response ) {
			  return $response->client_secret;
			}
		}

		return '';
	}

	public function get_stripe_intent_client_secret_subscription( $grand_total ) {
		if ( get_option( 'ec_option_payment_process_method' ) == 'stripe' )
			$stripe = new ec_stripe();
		else
			$stripe = new ec_stripe_connect();

		$order_totals = (object) array( 'grand_total' => $grand_total );

		if ( $GLOBALS['ec_cart_data']->cart_data->stripe_paymentintent_id == '' ) {
			$response = $stripe->create_payment_intent( $order_totals );
			$GLOBALS['ec_cart_data']->cart_data->stripe_paymentintent_id = $response->id;
			$GLOBALS['ec_cart_data']->save_session_to_db();
			do_action( 'wpeasycart_cart_updated' );
		} else {
			$response = $stripe->get_payment_intent( $GLOBALS['ec_cart_data']->cart_data->stripe_paymentintent_id );
			if ( ! $response || $response->status == 'succeeded' || $response->status == 'canceled' ) {
				$response = $stripe->create_payment_intent( $order_totals );
				$GLOBALS['ec_cart_data']->cart_data->stripe_paymentintent_id = $response->id;
				$GLOBALS['ec_cart_data']->save_session_to_db();
				do_action( 'wpeasycart_cart_updated' );
			}
		}

		return $response->client_secret;
	}

	public function print_square_payment_script( $is_payment = false ) {
		if( ! $is_payment && ! get_option( 'ec_option_square_digital_wallet' ) ) {
			return;
		}
	
		if ( get_option( 'ec_option_square_application_id' ) != '' ) { 
			$app_id = get_option( 'ec_option_square_application_id' );
		} else { 
			$app_id = ( get_option( 'ec_option_square_is_sandbox' ) ) ? 'sandbox-sq0idb-khAAob2bNi889KPQSVsF6Q' : 'sq0idp-H8Mnz1zzbv1mOyeWyKpF6Q';
		}
		$location_id = ( get_option( 'ec_option_square_is_sandbox' ) ) ? get_option( 'ec_option_square_sandbox_location_id' ) : get_option( 'ec_option_square_location_id' );
		if ( $location_id == '' ) {
			$square = new ec_square();
			$location_id = $square->get_location_id();
		}
		/* 6.0.2: what the shopper reads when a payment stops, and what the browser reports to the store's gateway log. */
		$square_text = array(
			'failed' => html_entity_decode( wp_strip_all_tags( (string) wp_easycart_language()->get_text( 'ec_errors', 'payment_failed' ) ), ENT_QUOTES, 'UTF-8' ),
			'error'  => html_entity_decode( wp_strip_all_tags( (string) wp_easycart_language()->get_text( 'cart_onepage', 'place_order_error' ) ), ENT_QUOTES, 'UTF-8' ),
		);
		if ( wp_easycart_onepage_active() ) {
			$square_checkout = ( get_option( 'ec_option_onepage_checkout_tabbed' ) ) ? 'one-page checkout ( steps )' : 'one-page checkout';
		} else {
			$square_checkout = ( $is_payment ) ? 'classic checkout' : 'cart page express buttons';
		}
		$square_log = array(
			'nonce'    => wp_create_nonce( 'wp-easycart-square-checkout-log' ),
			'checkout' => $square_checkout,
			'amount'   => number_format( (float) $this->order_totals->grand_total, 2, '.', '' ),
		);
		$wallet_totals  = $this->get_wallet_order_totals(); /* 6.0.2: Apple Pay and Google Pay are card payments ( Card flex fees ) */
		$square_wallets = get_option( 'ec_option_square_digital_wallet' ) && apply_filters( 'wp_easycart_allow_paypal_express', false ) && (int) ( $this->order_totals->get_converted_grand_total() * 100 ) >= 50;
		echo '<div id="square-success-cover" style="display:none; cursor:default; position:fixed; top:0; left:0; width:100%; height:100%; z-index:999999; background-color: rgba(0, 0, 0, 0.8); color:#FFF;">
			<style>
			@keyframes rotation{
				0%  { transform:rotate(0deg); }
				100%{ transform:rotate(359deg); }
			}
			</style>
			<div style=\'font-family: "HelveticaNeue", "HelveticaNeue-Light", "Helvetica Neue Light", helvetica, arial, sans-serif; font-size: 14px; text-align: center; -webkit-box-sizing: border-box; -moz-box-sizing: border-box; -ms-box-sizing: border-box; box-sizing: border-box; width: 350px; top: 50%; left: 50%; position: absolute; margin-left: -165px; margin-top: -80px; cursor: pointer; text-align: center;\'>
				<div class="paypal-checkout-loader">
					<div style="height: 30px; width: 30px; display: inline-block; box-sizing: content-box; opacity: 1; filter: alpha(opacity=100); -webkit-animation: rotation .7s infinite linear; -moz-animation: rotation .7s infinite linear; -o-animation: rotation .7s infinite linear; animation: rotation .7s infinite linear; border-left: 8px solid rgba(0, 0, 0, .2); border-right: 8px solid rgba(0, 0, 0, .2); border-bottom: 8px solid rgba(0, 0, 0, .2); border-top: 8px solid #fff; border-radius: 100%;"></div>
				</div>
			</div>
		</div>';
		echo '<script type="text/javascript">
			( function() { /* 6.0.2: its own scope ( the steps layout can load this again ) */
			jQuery( document.getElementById( "square-success-cover" ) ).appendTo( document.body );
			const wpEasyCartAppId = "' . esc_attr( $app_id ) . '";
			const wpEasyCartLocationId = "' . esc_attr( $location_id ) . '";
			const wpEasyCartSquareRequests = []; /* 6.0.2: the wallets\' payment requests, kept on the cart\'s total */
			let wpEasyCartSquareSheetOpen = false;
			jQuery( document ).on( \'wpeasycart_cart_updated\', function( event, cart_data ) {
				if ( wpEasyCartSquareSheetOpen || ! cart_data || ! cart_data.square_wallet ) {
					return;
				}
				wpEasyCartSquareRequests.forEach( function( request ) {
					try {
						request.update( { lineItems: cart_data.square_wallet.items, total: { amount: cart_data.square_wallet.total, label: \'Total\' } } );
					} catch ( e ) {
						console.log( e );
					}
				} );
			} );
			/* 6.0.2: when a payment stops, the shopper reads why, the browser console shows the error and the store\'s gateway log
			   ( Settings › Log entries ) gets a line. Never the card, its token or the verification token. */
			const wpEasyCartSquareText = ' . wp_json_encode( $square_text ) . ';
			const wpEasyCartSquareLog = ' . wp_json_encode( $square_log ) . ';
			let wpEasyCartSquareInitError = null;
			function wpEasyCartSquareDecode( text ) { /* an answer\'s entities as the shopper reads them, without running any markup */
				if ( "string" !== typeof text || "" === text ) {
					return "";
				}
				try {
					var doc = new DOMParser().parseFromString( text, "text/html" );
					return String( doc && doc.body ? doc.body.textContent : text ).trim();
				} catch ( e ) {
					return text;
				}
			}
			function wpEasyCartSquareProblem( stage, message, shopper ) {
				var problem = new Error( message );
				problem.wpecStage = stage;
				problem.wpecShopper = shopper || "";
				return problem;
			}
			function wpEasyCartSquareReport( stage, problem, method ) {
				if ( window.console && window.console.error ) {
					window.console.error( "WP EasyCart Square: the payment stopped ( " + stage + " ).", problem );
				}
				if ( "cancel" === stage || "address" === stage || ( problem && problem.wpecQuiet ) ) {
					return; /* the shopper closed the sheet or mistyped: shown to them, nothing for the store to fix */
				}
				try {
					var body = new FormData();
					body.append( "action", "ec_ajax_square_checkout_log" );
					body.append( "nonce", wpEasyCartSquareLog.nonce );
					body.append( "stage", stage );
					body.append( "message", String( ( problem && problem.message ) ? problem.message : problem ).substring( 0, 500 ) );
					body.append( "error_name", ( problem && problem.name ) ? String( problem.name ).substring( 0, 60 ) : "" );
					body.append( "method", method ? String( method ).substring( 0, 40 ) : "" );
					body.append( "checkout", wpEasyCartSquareLog.checkout );
					body.append( "amount", ( "undefined" !== typeof wpeasycart_onepage_place && wpeasycart_onepage_place.total > 0 ) ? wpeasycart_onepage_place.total.toFixed( 2 ) : wpEasyCartSquareLog.amount );
					if ( ! ( window.navigator && window.navigator.sendBeacon && window.navigator.sendBeacon( wpeasycart_ajax_object.ajax_url, body ) ) ) { /* a beacon still goes when the page moves on */
						fetch( wpeasycart_ajax_object.ajax_url, { method: "POST", body: body, credentials: "same-origin" } ).catch( function() {} );
					}
				} catch ( e ) {
					/* the report is only a help: never let it stop the page */
				}
			}
			function wpEasyCartSquareShowError( message, code ) {
				message = wpEasyCartSquareDecode( message );
				if ( document.getElementById( "wpeasycart_onepage_place" ) && "function" === typeof wpeasycart_onepage_show_errors ) {
					wpeasycart_onepage_show_errors( [ { code: code || "square_payment", message: message } ] ); /* one-page: above Place order */
				} else {
					ec_show_error( "ec_submit_order" );
				}
			}
			function wpEasyCartSquareWallet( tokenResult ) {
				/* Only Apple Pay and Google Pay bring their own addresses. A card token\'s billing holds just the postal code
				   typed in the card form, and a gift card has none: those pay for the addresses the checkout saved. */
				var details = ( tokenResult && tokenResult.details ) ? tokenResult.details : null;
				if ( ! details || -1 !== [ "Card", "Gift Card", "GiftCard" ].indexOf( details.method ) ) {
					return false;
				}
				return !! ( ( details.billing && details.billing.addressLines ) || ( details.shipping && details.shipping.contact ) );
			}';
		if( $is_payment ) {
		echo '
			async function wpEasyCartInitializeCard( payments ) {
				try {
					if ( jQuery( document.getElementById( "wp-easycart-square-card-container" ) ).length ) {
						const wpEasyCartCard = await payments.card();
						try {
							await wpEasyCartCard.attach( \'#wp-easycart-square-card-container\' );
							return wpEasyCartCard;
						} catch ( e ) {
							console.error( \'Square could not attach card\', e );
							wpEasyCartSquareInitError = e;
						}
					}
				} catch ( e ) {
					console.error( \'Square could not initialize card\', e );
					wpEasyCartSquareInitError = e;
				}
			}';
		}
		echo '
			let wpEasyCartSquareInit = async function () {
				let wpEasyCartSquarePayments = null;
				try { /* 6.0.2: a Square.js that did not load, or a wrong application or location id, is reported when the shopper pays */
					if ( ! window.Square ) {
						throw new Error( "Square.js did not load ( blocked by the browser, an ad blocker, a script optimiser or the site\'s content security policy )" );
					}
					wpEasyCartSquarePayments = window.Square.payments( wpEasyCartAppId, wpEasyCartLocationId );
				} catch ( e ) {
					console.error( \'Square could not start\', e );
					wpEasyCartSquareInitError = e;
				}';
		if ( $is_payment ) {
		echo '
				let wpEasyCartCard;
				if ( wpEasyCartSquarePayments ) {
					try {
						wpEasyCartCard = await wpEasyCartInitializeCard( wpEasyCartSquarePayments );
					} catch ( e ) {
						console.error( \'Initializing Card failed\', e );
						wpEasyCartSquareInitError = e;
					}
				}';
		}
		if ( get_option( 'ec_option_square_digital_wallet' ) && apply_filters( 'wp_easycart_allow_paypal_express', false ) && (int) ( $this->order_totals->get_converted_grand_total() * 100 ) >= 50 ) {
		echo '
				let wpEasyCartApplePay;
				try {
					wpEasyCartApplePay = await wpEasyCartInitializeApplePay( wpEasyCartSquarePayments );
				} catch ( e ) {
					console.error( \'Initializing Apple Pay failed\', e );
				}
				let wpEasyCartGooglePay;
				try {
					wpEasyCartGooglePay = await wpEasyCartInitializeGooglePay( wpEasyCartSquarePayments );
				} catch ( e ) {
					console.error( \'Initializing Google Pay failed\', e );
				}';
		}
		if ( get_option( 'ec_option_square_gift_cards' ) && apply_filters( 'wp_easycart_allow_paypal_express', false ) ) {
		echo '
				let wpEasyCartGiftCard;
				try {
					wpEasyCartGiftCard = await wpEasyCartInitializeGiftCard( wpEasyCartSquarePayments );
				} catch ( e ) {
					console.error( \'Initializing Gift Card failed\', e ); /* 6.0.2: the wallets still load ( no gift card box on this page ) */
				}';
		}
		echo '
				async function wpEasyCartCreatePayment( tokenResult, verificationToken, isWallet ) {
					var body = new FormData()
					body.append( \'action\', \'ec_ajax_square_complete_payment\' );
					body.append( \'sourceId\', tokenResult.token );
					body.append( \'wallet\', isWallet ? 1 : 0 ); /* 6.0.2: Apple Pay / Google Pay pay as a card ( Card flex fees ) */';
		if ( wp_easycart_onepage_active() ) {
		/* 6.0.2: a card token carries details.billing too ( the postal code from the card form ), so testing for billing sent every
		   card payment into the wallet branch, where reading the missing shipping contact threw before the store was asked. */
		echo '
					if ( wpEasyCartSquareWallet( tokenResult ) ) { /* 6.0.2: a wallet brings its own addresses; the card and a gift card pay for what Place order saved */';
		}
		if ( ( ! $is_payment || wp_easycart_onepage_active() ) && get_option( 'ec_option_use_shipping' ) && $this->cart->shippable_total_items > 0 ) {
			if ( wp_easycart_onepage_active() ) {
			echo '
					if ( tokenResult.details.shipping && tokenResult.details.shipping.contact ) {';
			}
		echo '
					var allowed_countries = [';
		$first_country = true;
		foreach ( $GLOBALS['ec_countries']->countries as $country ) {
			if ( ! $first_country ) {
				echo ',';
			}
			echo '"' . esc_attr( $country->iso2_cnt ) . '"';
			$first_country = false;
		}
		echo '];
					if ( ! allowed_countries.includes( tokenResult.details.shipping.contact.countryCode ) ) {
						const errorBody = "';
		echo wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_shipping_invalid' ); // XSS OK
		echo '";
						alert( errorBody );
						throw wpEasyCartSquareProblem( "address", errorBody, errorBody );
					}
					body.append( \'shipping_address_line_1\', tokenResult.details.shipping.contact.addressLines[0] );
					body.append( \'shipping_address_line_2\', tokenResult.details.shipping.contact.addressLines[1] );
					body.append( \'shipping_address_city\', tokenResult.details.shipping.contact.city );
					body.append( \'shipping_address_state\', tokenResult.details.shipping.contact.state );
					body.append( \'shipping_address_region\', tokenResult.details.shipping.contact.region );
					body.append( \'shipping_address_dependentLocality\', tokenResult.details.shipping.contact.dependentLocality );
					body.append( \'shipping_address_zip\', tokenResult.details.shipping.contact.postalCode );
					body.append( \'shipping_address_country\', tokenResult.details.shipping.contact.countryCode );
					body.append( \'shipping_address_phone\', tokenResult.details.shipping.contact.phone );
					body.append( \'shipping_address_email\', tokenResult.details.shipping.contact.email );
					body.append( \'shipping_address_first_name\', tokenResult.details.shipping.contact.givenName );
					body.append( \'shipping_address_last_name\', tokenResult.details.shipping.contact.familyName );
					if ( tokenResult.details.shipping.option && tokenResult.details.shipping.option.id ) { /* 6.0.2: Square charges the rate the sheet ends on */
						body.append( \'shipping_method\', tokenResult.details.shipping.option.id );
					}';
			if ( wp_easycart_onepage_active() ) {
			echo '
					}';
			}
		}
		if ( ! $is_payment || wp_easycart_onepage_active() ) {
			if ( wp_easycart_onepage_active() ) {
			echo '
					if ( tokenResult.details.billing && tokenResult.details.billing.addressLines ) {';
			}
		echo '
					body.append( \'billing_address_line_1\', tokenResult.details.billing.addressLines[0] );
					body.append( \'billing_address_line_2\', tokenResult.details.billing.addressLines[1] );
					body.append( \'billing_address_city\', tokenResult.details.billing.city );
					body.append( \'billing_address_state\', tokenResult.details.billing.state );
					body.append( \'billing_address_region\', tokenResult.details.billing.region );
					body.append( \'billing_address_dependentLocality\', tokenResult.details.billing.dependentLocality );
					body.append( \'billing_address_zip\', tokenResult.details.billing.postalCode );
					body.append( \'billing_address_country\', tokenResult.details.billing.countryCode );
					body.append( \'billing_address_phone\', tokenResult.details.billing.phone );
					body.append( \'billing_address_email\', tokenResult.details.billing.email );
					body.append( \'billing_address_first_name\', tokenResult.details.billing.givenName );
					body.append( \'billing_address_last_name\', tokenResult.details.billing.familyName );';
			if ( wp_easycart_onepage_active() ) {
			echo '
					}';
			}
		}
		if ( wp_easycart_onepage_active() ) {
		echo '
					}';
		}
		echo '
					var wpec_card = ( tokenResult.details && tokenResult.details.card ) ? tokenResult.details.card : {}; /* 6.0.2: a gift card may not describe one */
					body.append( \'card_type\', wpec_card.brand || \'\' );
					body.append( \'last_4\', wpec_card.last4 || \'\' );
					body.append( \'exp_month\', wpec_card.expMonth || \'\' );
					body.append( \'exp_year\', wpec_card.expYear || \'\' );';
		if ( get_option( 'ec_option_require_terms_agreement' ) ) {
		echo '
					body.append( \'ec_terms_agree\', 1 );';
		}
		echo '
					if( jQuery( document.getElementById( \'ec_cart_is_subscriber\' ) ).length && jQuery( document.getElementById( \'ec_cart_is_subscriber\' ) ).is( \':checked\' ) ){
						body.append( \'ec_cart_is_subscriber\', 1 );
					}
					if( verificationToken !== undefined ) {
						body.append( \'buyerVerificationToken\', verificationToken );
					}
					body.append( \'easycartnonce\', \'' . esc_attr( wp_create_nonce( 'wp-easycart-get-square-complete-payment-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ) . '\' );
					let paymentResponse;
					try {
						paymentResponse = await fetch( wpeasycart_ajax_object.ajax_url, {
							method: \'POST\',
							body: body,
						} );
					} catch ( e ) {
						throw wpEasyCartSquareProblem( "request", "The payment request did not reach the store: " + ( e && e.message ? e.message : e ) );
					}
					/* 6.0.2: read the answer as text. Anything printed before it ( a PHP notice, another plugin ) must not turn a
					   payment that went through into an error, and an answer that is not one is described in the log. */
					const paymentText = await paymentResponse.text();
					let paymentResults = null;
					try {
						paymentResults = JSON.parse( paymentText );
					} catch ( e ) {
						var wpec_at = Math.max( paymentText.lastIndexOf( "{\"ok\"" ), paymentText.lastIndexOf( "{\"error\"" ) );
						var wpec_end = ( wpec_at >= 0 ) ? paymentText.indexOf( "}", wpec_at ) : -1;
						[ paymentText.substring( wpec_at ), paymentText.substring( wpec_at, wpec_end + 1 ) ].forEach( function( part ) {
							if ( ! paymentResults && wpec_at >= 0 && wpec_end > wpec_at ) {
								try {
									paymentResults = JSON.parse( part );
								} catch ( e2 ) {
									paymentResults = null;
								}
							}
						} );
						if ( paymentResults ) {
							wpEasyCartSquareReport( "response", wpEasyCartSquareProblem( "response", "The store printed other text around its answer ( the payment itself was answered ): " + wpEasyCartSquareDecode( ( paymentText.substring( 0, wpec_at ) + " " + paymentText.substring( wpec_end + 1 ) ).replace( /<[^>]*>/g, " " ) ).replace( /\s+/g, " " ).substring( 0, 300 ) ), ( tokenResult.details && tokenResult.details.method ) ? tokenResult.details.method : "" );
						}
					}
					if ( ! paymentResults || "object" !== typeof paymentResults ) {
						throw wpEasyCartSquareProblem( "response", "The store answered HTTP " + paymentResponse.status + " without a payment result: " + ( wpEasyCartSquareDecode( paymentText.replace( /<[^>]*>/g, " " ) ).replace( /\s+/g, " " ).substring( 0, 300 ) || "( an empty answer )" ) );
					}
					return paymentResults;
				}
				async function wpEasyCartVerifyBuyer( wpEasyCartSquarePayments, token ) {';
		if ( wp_easycart_onepage_active() ) {
			echo "
					var given_name = jQuery( document.getElementById( 'ec_cart_billing_first_name' ) ).val();
					var family_name = jQuery( document.getElementById( 'ec_cart_billing_last_name' ) ).val();
					var address1 = jQuery( document.getElementById( 'ec_cart_billing_address' ) ).val();
					var address2 = jQuery( document.getElementById( 'ec_cart_billing_address2' ) ).val();
					var addressLines = Array( address1 );
					if ( address2 && address2.length > 0 ) {
						addressLines.push( address2 );
					}
					var city = jQuery( document.getElementById( 'ec_cart_billing_city' ) ).val();
					var country = jQuery( document.getElementById( 'ec_cart_billing_country' ) ).val();
					var state = ( jQuery( document.getElementById( 'ec_cart_billing_state_' + country ) ).length ) ? jQuery( document.getElementById( 'ec_cart_billing_state_' + country ) ).val() : jQuery( document.getElementById( 'ec_cart_billing_state' ) ).val();
					var zip = jQuery( document.getElementById( 'ec_cart_billing_zip' ) ).val();
					var phone = jQuery( document.getElementById( 'ec_cart_billing_phone' ) ).val();
					if ( jQuery( document.getElementById( 'billing_address_type_same' ) ).length && jQuery( document.getElementById( 'billing_address_type_same' ) ).is ( ':checked' ) ){
						given_name = jQuery( document.getElementById( 'ec_cart_shipping_first_name' ) ).val();
						family_name = jQuery( document.getElementById( 'ec_cart_shipping_last_name' ) ).val();
						address1 = jQuery( document.getElementById( 'ec_cart_shipping_address' ) ).val();
						address2 = jQuery( document.getElementById( 'ec_cart_shipping_address2' ) ).val();
						addressLines = Array( address1 );
						if ( address2 && address2.length > 0 ) {
							addressLines.push( address2 );
						}
						city = jQuery( document.getElementById( 'ec_cart_shipping_city' ) ).val();
						country = jQuery( document.getElementById( 'ec_cart_shipping_country' ) ).val();
						state = ( jQuery( document.getElementById( 'ec_cart_shipping_state_' + country ) ).length ) ? jQuery( document.getElementById( 'ec_cart_shipping_state_' + country ) ).val() : jQuery( document.getElementById( 'ec_cart_shipping_state' ) ).val();
						zip = jQuery( document.getElementById( 'ec_cart_shipping_zip' ) ).val();
						phone = jQuery( document.getElementById( 'ec_cart_shipping_phone' ) ).val();
					}";
			if ( ! class_exists( 'Email_Encoder' ) && ! function_exists( 'eae_encode_emails' ) ) {
				echo "
					var email = jQuery( document.getElementById( 'ec_contact_email' ) ).val();";
			}
		} else {
			echo '
					var given_name = \'' . esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->billing_first_name, ENT_QUOTES ) ) . '\';
					var family_name = \'' . esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->billing_last_name, ENT_QUOTES ) ) . '\';
					var address1 = \'' . esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->billing_address_line_1, ENT_QUOTES ) ) . '\';
					var address2 = \'' . esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->billing_address_line_2, ENT_QUOTES ) ) . '\';
					var addressLines = Array( address1 );
					if ( address2 && address2.length > 0 ) {
						addressLines.push( address2 );
					}
					var city = \'' . esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->billing_city, ENT_QUOTES ) ) . '\';
					var state = \'' . esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->billing_state, ENT_QUOTES ) ) . '\';
					var zip = \'' . esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->billing_zip, ENT_QUOTES ) ) . '\';
					var country = \'' . esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->billing_country, ENT_QUOTES ) ) . '\';
					var phone = \'' . esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->billing_phone, ENT_QUOTES ) ) . '\';';
			if ( ! class_exists( 'Email_Encoder' ) && ! function_exists( 'eae_encode_emails' ) ) {
				echo '
					var email = \'' . esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->email, ENT_QUOTES ) ) . '\';';
			}
		}
		echo '
					if ( \'undefined\' !== typeof wpeasycart_onepage_place && wpeasycart_onepage_place.session ) { /* 6.0.2 */
						given_name = wpeasycart_onepage_session_value( \'billing_first_name\', given_name );
						family_name = wpeasycart_onepage_session_value( \'billing_last_name\', family_name );
						address1 = wpeasycart_onepage_session_value( \'billing_address_line_1\', address1 );
						address2 = wpeasycart_onepage_session_value( \'billing_address_line_2\', address2 );
						addressLines = Array( address1 );
						if ( address2 && address2.length > 0 ) {
							addressLines.push( address2 );
						}
						city = wpeasycart_onepage_session_value( \'billing_city\', city );
						state = wpeasycart_onepage_session_value( \'billing_state\', state );
						zip = wpeasycart_onepage_session_value( \'billing_zip\', zip );
						country = wpeasycart_onepage_session_value( \'billing_country\', country );
						phone = wpeasycart_onepage_session_value( \'billing_phone\', phone );';
		if ( ! class_exists( 'Email_Encoder' ) && ! function_exists( 'eae_encode_emails' ) ) {
			echo '
						var session_email = wpeasycart_onepage_session_value( \'email\', \'\' ); /* 6.0.2: a signed-in shopper has no email box */
						if ( session_email ) {
							email = session_email;
						}';
		}
		echo '
					}
					var billing_contact = {
						familyName: family_name,
						givenName: given_name,';
		if ( ! class_exists( 'Email_Encoder' ) && ! function_exists( 'eae_encode_emails' ) ){
		echo '
						email: email,';
		}
		echo '
						countryCode: country,
						city: city,
						addressLines: addressLines,
						phone: phone
					};
					if ( country != \'AU\' ) {
						billing_contact.postalCode = zip;
					}
					Object.keys( billing_contact ).forEach( function( key ) { /* 6.0.2: only what is known ( never an undefined email or an empty line ) */
						var value = billing_contact[ key ];
						if ( Array.isArray( value ) ) {
							value = value.filter( function( line ) { return line && String( line ).trim(); } );
							billing_contact[ key ] = value;
						}
						if ( undefined === value || null === value || ( Array.isArray( value ) ? ! value.length : "" === String( value ).trim() ) ) {
							delete billing_contact[ key ];
						}
					} );
					const verificationDetails = {
						amount: ( \'undefined\' !== typeof wpeasycart_onepage_place && wpeasycart_onepage_place.total > 0 ) ? wpeasycart_onepage_place.total.toFixed( 2 ) : \'' . esc_attr( number_format( $this->order_totals->grand_total, 2, '.', '' ) ) . '\',
						intent: \'CHARGE\',
						billingContact: billing_contact,
						currencyCode: \'' . esc_attr( get_option( 'ec_option_square_currency' ) ) . '\'
					};
					let verificationResults;
					try {
						verificationResults = await wpEasyCartSquarePayments.verifyBuyer(
							token,
							verificationDetails
						);
					} catch ( e ) {
						throw wpEasyCartSquareProblem( "verify", "Square could not check the card ( verifyBuyer, amount " + verificationDetails.amount + " " + verificationDetails.currencyCode + ", billing country " + ( billing_contact.countryCode || "missing" ) + ", location " + wpEasyCartLocationId + " ): " + ( e && e.name ? e.name + ": " : "" ) + ( e && e.message ? e.message : e ) );
					}
					if ( ! verificationResults || ! verificationResults.token ) { /* 6.0.2: the card check ( 3-D Secure ) was not completed */
						throw wpEasyCartSquareProblem( "verify", "Square returned no verification token ( the card check was not completed ) for " + verificationDetails.amount + " " + verificationDetails.currencyCode );
					}
					return verificationResults.token;
				}
				async function wpEasyCartTokenize( paymentMethod ) {
					if ( ! paymentMethod ) { /* 6.0.2 */
						throw wpEasyCartSquareProblem( "init", "Square\'s payment form did not load" + ( wpEasyCartSquareInitError ? ": " + ( wpEasyCartSquareInitError.message || wpEasyCartSquareInitError ) : "" ) + " ( application " + wpEasyCartAppId + ", location " + wpEasyCartLocationId + " )" );
					}
					let tokenResult;
					try {
						tokenResult = await paymentMethod.tokenize();
					} catch ( e ) {
						throw wpEasyCartSquareProblem( "tokenize", "Square could not read the payment details: " + ( e && e.name ? e.name + ": " : "" ) + ( e && e.message ? e.message : e ) );
					}
					if ( tokenResult && tokenResult.status === \'OK\' ) {
						return tokenResult;
					}
					var wpec_status = ( tokenResult && tokenResult.status ) ? String( tokenResult.status ) : "Unknown";
					if ( "cancel" === wpec_status.toLowerCase() ) {
						throw wpEasyCartSquareProblem( "cancel", "The shopper closed the payment sheet" );
					}
					var wpec_messages = [];
					( tokenResult && tokenResult.errors ? tokenResult.errors : [] ).forEach( function( error ) {
						if ( error && error.message && -1 === wpec_messages.indexOf( String( error.message ) ) ) {
							wpec_messages.push( String( error.message ) );
						}
					} );
					/* Square\'s own words for what is wrong in the card form ( a number, a date, a postal code ) */
					var wpec_problem = wpEasyCartSquareProblem( "tokenize", "Tokenization failed, status " + wpec_status + ( wpec_messages.length ? ": " + wpec_messages.join( " " ) : "" ), ( "invalid" === wpec_status.toLowerCase() ) ? wpec_messages.join( " " ) : "" );
					wpec_problem.wpecQuiet = ( "invalid" === wpec_status.toLowerCase() ); /* a typing mistake: shown to the shopper, not logged */
					throw wpec_problem;
				}
				function wpEasyCartDisplayPaymentResults( status, message ) {
					if ( jQuery( document.getElementById( \'wp-easycart-square-payment-status-container\' ) ).length ) {
						jQuery( document.getElementById( \'wp-easycart-square-payment-status-container\' ) ).text( wpEasyCartSquareDecode( message ) ); /* 6.0.2: text, never markup */
						const statusContainer = document.getElementById(
							\'wp-easycart-square-payment-status-container\'
						);
						if ( status === \'SUCCESS\' ) {
							statusContainer.classList.remove( \'is-failure\' );
							statusContainer.classList.add( \'is-success\' );
						} else {
							statusContainer.classList.remove( \'is-success\' );
							statusContainer.classList.add( \'is-failure\' );
						}
						statusContainer.style.visibility = \'visible\';
					}
				}
				async function wpEasyCartHandlePaymentMethodSubmission( event, paymentMethod, shouldVerify = false ) {
					var payment_method = "credit_card";
					if( jQuery( \'input:radio[name=ec_cart_payment_selection]:checked\' ).length ) {
						payment_method = jQuery( \'input:radio[name=ec_cart_payment_selection]:checked\' ).val( );
					}
					if( payment_method != \'credit_card\' ){
						return;
					}';
		if ( get_option( 'ec_option_require_terms_agreement' ) ) {
			echo '
					if( jQuery( document.getElementById( \'ec_terms_agree\' ) ).length && ! jQuery( document.getElementById( \'ec_terms_agree\' ) ).is( \':checked\' ) ) {
						if ( ! confirm( \'' . esc_js( wp_easycart_language()->get_text( 'cart_payment_information', 'cart_payment_review_agree' ) ) . '.\' ) ) {
							return;
						} else {
							jQuery( document.getElementById( \'ec_terms_agree\' ) ).prop( \'checked\', true );
						}
					}';
		}
		if ( wp_easycart_onepage_active() ) {
			echo '
					if ( paymentMethod && "Card" == paymentMethod.methodType && ! ec_validate_submit_order() ) {
						return;
					}
			';
		}
		echo '
					event.preventDefault();
					try {';
		if( $is_payment ) {
			echo '
						if ( wpEasyCartCardButton ) { wpEasyCartCardButton.disabled = true; } /* 6.0.2: none on the information step */';
		}
		echo '
						jQuery( document.getElementById( \'square-success-cover\' ) ).show();
						wpEasyCartSquareSheetOpen = true;
						let tokenResult;
						try {
							tokenResult = await wpEasyCartTokenize( paymentMethod );
						} finally {
							wpEasyCartSquareSheetOpen = false;
						}
						let verificationToken;
						if ( shouldVerify ) {
							verificationToken = await wpEasyCartVerifyBuyer(
								wpEasyCartSquarePayments,
								tokenResult.token
							);
						}
						const paymentResults = await wpEasyCartCreatePayment( tokenResult, verificationToken, ' . ( $square_wallets ? '!! paymentMethod && ( paymentMethod === wpEasyCartApplePay || paymentMethod === wpEasyCartGooglePay )' : 'false' ) . ' );
						if( paymentResults.ok ) {
							wpEasyCartDisplayPaymentResults( \'SUCCESS\', \'\' );
							jQuery( location ).attr( \'href\', paymentResults.goto );
						} else {';
		if( $is_payment ) {
			echo '
						if ( wpEasyCartCardButton ) { wpEasyCartCardButton.disabled = false; } /* 6.0.2: none on the information step */';
		}
		echo '
							if ( "stock_error" == paymentResults.error || "stock_invalid" == paymentResults.error ) {
								window.location.href = "' . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_payment', array( 'ec_cart_error' => 'stock_invalid' ) ) ) . '";
							} else if ( "minimum_order" == paymentResults.error || "invalid_cart_shipping" == paymentResults.error ) { /* 6.0.2 */
								window.location.href = "' . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_payment', array( 'ec_cart_error' => 'wpeccode' ) ) ) . '".replace( "wpeccode", paymentResults.error );
							} else {
								/* 6.0.2: the store\'s answer ( the gateway\'s decline, checkout protection, an expired page ), shown where the shopper looks */
								var wpec_refused = wpEasyCartSquareDecode( paymentResults.error ) || wpEasyCartSquareText.failed;
								wpEasyCartDisplayPaymentResults( \'FAILURE\', wpec_refused );
								jQuery( document.getElementById( \'ec_cart_submit_order\' ) ).show( );
								jQuery( document.getElementById( \'ec_cart_submit_order_working\' ) ).hide( );
								wpEasyCartSquareShowError( wpec_refused, paymentResults.code ? String( paymentResults.code ) : "payment_failed" );
								jQuery( document.getElementById( \'square-success-cover\' ) ).hide();
								if ( window.console && window.console.warn ) {
									window.console.warn( "WP EasyCart Square: the store did not take the payment.", paymentResults );
								}
							}
						}
					} catch (e) {';
		if( $is_payment ) {
			echo '
						if ( wpEasyCartCardButton ) { wpEasyCartCardButton.disabled = false; } /* 6.0.2: none on the information step */';
		}
		echo '
						jQuery( document.getElementById( \'ec_cart_submit_order\' ) ).show( );
						jQuery( document.getElementById( \'ec_cart_submit_order_working\' ) ).hide( );
						jQuery( document.getElementById( \'square-success-cover\' ) ).hide();
						/* 6.0.2: the reason, not only "correct the errors" ( a script error used to end here with a console line only ) */
						var wpec_stage = ( e && e.wpecStage ) ? e.wpecStage : "script";
						if ( "cancel" === wpec_stage ) {
							wpEasyCartDisplayPaymentResults( \'SUCCESS\', \'\' );
							return;
						}
						var wpec_message = ( e && e.wpecShopper ) ? e.wpecShopper : ( ( "verify" === wpec_stage || "tokenize" === wpec_stage ) ? wpEasyCartSquareText.failed : ( wpEasyCartSquareText.error || wpEasyCartSquareText.failed ) );
						wpEasyCartDisplayPaymentResults( \'FAILURE\', wpec_message );
						wpEasyCartSquareShowError( wpec_message, "square_" + wpec_stage );
						wpEasyCartSquareReport( wpec_stage, e, ( paymentMethod && paymentMethod.methodType ) ? paymentMethod.methodType : "" );
					}
				}
				function wpEasyCartBuildPaymentRequest( wpEasyCartSquarePayments ) {';
		if ( ( ! $is_payment || wp_easycart_onepage_active() ) && get_option( 'ec_option_use_shipping' ) && $this->cart->shippable_total_items > 0 ) {
		echo '
					const defaultShippingOptions = ' . json_encode( $this->ec_cart_display_shipping_methods_square_dynamic( wp_easycart_language()->get_text( 'cart_estimate_shipping', 'cart_estimate_shipping_standard' ), wp_easycart_language()->get_text( 'cart_estimate_shipping', 'cart_estimate_shipping_express' ) ) );
		}
		echo '
					let lineItems = ' . wp_json_encode( $this->get_dynamic_square_line_items( $wallet_totals ) ) . ';
					let total = { amount: \'' . esc_attr( number_format( (float) $wallet_totals->grand_total, 2, '.', '' ) ) . '\', label: \'Total\' }; /* 6.0.2: the order total ( the discount line is shown, not added ), as a card payment ( Card flex fees ) */
					const paymentRequestDetails = {
						countryCode: \'' . esc_attr( get_option( 'ec_option_square_location_country' ) ) . '\',
						currencyCode: \'' . esc_attr( get_option( 'ec_option_square_currency' ) ) . '\',
						lineItems: lineItems,
						requestBillingContact: true,';
		if ( ( ! $is_payment || wp_easycart_onepage_active() ) && get_option( 'ec_option_use_shipping' ) && $this->cart->shippable_total_items > 0 ) {
		echo '
						shippingOptions: defaultShippingOptions,';
		}
		if ( ! $is_payment || wp_easycart_onepage_active() ) {
		echo '
						requestShippingContact: true,';
		}
		echo '
						total,
					};
					const wpEasyCartRequest = wpEasyCartSquarePayments.paymentRequest( paymentRequestDetails );
					wpEasyCartSquareRequests.push( wpEasyCartRequest );
					return wpEasyCartRequest;
				}
				function wpEasyCartSquareCalculateTotal( lineItems ) {
					const amount = lineItems.reduce( ( total, lineItem ) => {
						return total + parseFloat( lineItem.amount );
					}, 0.0).toFixed( 2 );
					return { amount, label: \'Total\' };
				}
				async function wpEasyCartSquareShippingMethodUpdate( shipping_option ) {
					var body = new FormData()
					body.append( \'action\', \'ec_ajax_update_square_shipping_option_dynamic\' );
					body.append( \'shippingAddress\', shipping_option.id );
					body.append( \'language\', wpeasycart_ajax_object.current_language );
					body.append( \'nonce\', \'' . esc_attr( wp_create_nonce( 'wp-easycart-get-square-shipping-option-dynamic-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ) . '\' );
					const shippingResponse = await fetch( wpeasycart_ajax_object.ajax_url, {
						method: \'POST\',
						body: body,
					} )
						.then( ( shippingResponse ) => shippingResponse.json() )
						.then( ( json_result ) => {
							if ( ! json_result.is_valid ) {
								jQuery( location ).attr( \'href\', json_result.redirect );
							} else {
								ec_update_cart( json_result.cart_data );
								return {
									lineItems: json_result.display_items,
									total: {
										label: "' . esc_attr( get_option( 'ec_option_square_merchant_name' ) ) . '",
										amount: json_result.total,
									}
								}
							}
						} );
					return shippingResponse;
				}
				async function wpEasyCartSquareShippingContactUpdate( contact ) {
					var body = new FormData()
					body.append( \'action\', \'ec_ajax_update_square_shipping_address_dynamic\' );
					body.append( \'shippingAddress[city]\', contact.city );
					body.append( \'shippingAddress[countryCode]\', contact.countryCode );
					body.append( \'shippingAddress[postalCode]\', contact.postalCode );
					body.append( \'shippingAddress[state]\', contact.state );
					body.append( \'language\', wpeasycart_ajax_object.current_language );
					body.append( \'nonce\', \'' . esc_attr( wp_create_nonce( 'wp-easycart-get-square-shipping-address-dynamic-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ) . '\' );
					const shippingResponse = await fetch( wpeasycart_ajax_object.ajax_url, {
						method: \'POST\',
						body: body,
					} )
						.then( ( shippingResponse ) => shippingResponse.json() )
						.then( ( json_result ) => {
							if ( ! json_result.is_valid ) {
								jQuery( location ).attr( \'href\', json_result.redirect );
							} else {
								ec_update_cart( json_result.cart_data );
								return {
									lineItems: json_result.display_items,
									shippingOptions: json_result.shipping_options,
									total: {
										label: "' . esc_attr( get_option( 'ec_option_square_merchant_name' ) ) . '",
										amount: json_result.total,
									}
								};
							}
						} );
					return shippingResponse;
				}';
		if ( get_option( 'ec_option_square_digital_wallet' ) && apply_filters( 'wp_easycart_allow_paypal_express', false ) && (int) ( $this->order_totals->get_converted_grand_total() * 100 ) >= 50 ) {
		echo '
				async function wpEasyCartInitializeApplePay( payments ) {
					const paymentRequest = wpEasyCartBuildPaymentRequest( payments );';
		
		if ( ( ! $is_payment || wp_easycart_onepage_active() ) && get_option( 'ec_option_use_shipping' ) && $this->cart->shippable_total_items > 0 ) {
		echo '
					paymentRequest.addEventListener( \'shippingoptionchanged\', wpEasyCartSquareShippingMethodUpdate );';
		}
		if ( ! $is_payment || wp_easycart_onepage_active() ) {
		echo '
					paymentRequest.addEventListener( \'shippingcontactchanged\', wpEasyCartSquareShippingContactUpdate );';
		}
		echo '
					const wpEasyCartApplePay = await payments.applePay( paymentRequest );
					return wpEasyCartApplePay;
				}
				async function wpEasyCartInitializeGooglePay( payments ) {
					const paymentRequest = wpEasyCartBuildPaymentRequest( payments );';
		if ( ( ! $is_payment || wp_easycart_onepage_active() ) && get_option( 'ec_option_use_shipping' ) && $this->cart->shippable_total_items > 0 ) {
		echo '
					paymentRequest.addEventListener( \'shippingoptionchanged\', wpEasyCartSquareShippingMethodUpdate );';
		}
		if( ! $is_payment || wp_easycart_onepage_active() ) {
		echo '
					paymentRequest.addEventListener( \'shippingcontactchanged\', wpEasyCartSquareShippingContactUpdate );';
		}
		echo '
					const wpEasyCartGooglePay = await payments.googlePay( paymentRequest );
					await wpEasyCartGooglePay.attach( \'#wp-easycart-square-google-pay-button\' );
					return wpEasyCartGooglePay;
				}';
		}
		if ( get_option( 'ec_option_square_gift_cards' ) && apply_filters( 'wp_easycart_allow_paypal_express', false ) ) {
		echo '
				async function wpEasyCartInitializeGiftCard( payments ) {
					const wpEasyCartGiftCard = await payments.giftCard();
					await wpEasyCartGiftCard.attach( \'#wp-easycart-square-gift-card-container\' );
					return wpEasyCartGiftCard;
				}';
		}
		if ( $is_payment ) {
		echo '
				const wpEasyCartCardButton = document.getElementById(
					\'ec_cart_submit_order\'
				);';
			if ( wp_easycart_onepage_active() ) {
				echo '
				const wpEasyCartForm = document.getElementById( \'ec_submit_order_form\' ) || document.getElementById( \'wpeasycart_checkout_details_form\' );
				if ( wpEasyCartForm && ( wpEasyCartCard || document.getElementById( \'wp-easycart-square-card-container\' ) ) ) { /* 6.0.2: paid from the submit that Place order sends after its checks ( a card form that did not load says so instead of posting without a card ) */
					if ( wpEasyCartForm.wpecSquareSubmit ) {
						wpEasyCartForm.removeEventListener( \'submit\', wpEasyCartForm.wpecSquareSubmit );
					}
					wpEasyCartForm.wpecSquareSubmit = function( event ) {
						var payment_method = \'credit_card\';
						if ( jQuery( \'input:radio[name=ec_cart_payment_selection]:checked\' ).length ) {
							payment_method = jQuery( \'input:radio[name=ec_cart_payment_selection]:checked\' ).val();
						}
						if ( \'credit_card\' != payment_method ) {
							return;
						}
						event.preventDefault();
						wpEasyCartHandlePaymentMethodSubmission( event, wpEasyCartCard, true );
					};
					wpEasyCartForm.addEventListener( \'submit\', wpEasyCartForm.wpecSquareSubmit );
				}';
			} else {
				echo '
				wpEasyCartCardButton.addEventListener( \'click\', async function (event) {
					await wpEasyCartHandlePaymentMethodSubmission( event, wpEasyCartCard, true );
				} );';
			}
		}
		if ( get_option( 'ec_option_square_digital_wallet' ) && apply_filters( 'wp_easycart_allow_paypal_express', false ) && (int) ( $this->order_totals->get_converted_grand_total() * 100 ) >= 50 ) {
		echo '
				const wpEasyCartGooglePayButton = document.getElementById( \'wp-easycart-square-google-pay-button\' );
				if ( wpEasyCartGooglePay !== undefined && wpEasyCartGooglePayButton ) {
					wpEasyCartGooglePayButton.addEventListener( \'click\', async function ( event ) {
						await wpEasyCartHandlePaymentMethodSubmission( event, wpEasyCartGooglePay );
					} );
				}
				const wpEasyCartApplePayButton = document.getElementById( \'wp-easycart-square-apple-pay-button\' );
				if ( wpEasyCartApplePay !== undefined && wpEasyCartApplePayButton ) {
					wpEasyCartApplePayButton.addEventListener( \'click\', async function ( event ) {
						await wpEasyCartHandlePaymentMethodSubmission( event, wpEasyCartApplePay );
					} );
				}';
		}
		if ( get_option( 'ec_option_square_gift_cards' ) && apply_filters( 'wp_easycart_allow_paypal_express', false ) ) {
		echo '
				const wpEasyCartGiftCardButton = document.getElementById( \'wp-easycart-square-gift-card-button\' );
				let wpEasyCartGiftCardBusy = false;
				if ( wpEasyCartGiftCard !== undefined && wpEasyCartGiftCardButton ) {
					wpEasyCartGiftCardButton.addEventListener( \'click\', async function ( event ) {';
			if ( wp_easycart_onepage_active() ) {
			echo '
						event.preventDefault();
						if ( wpEasyCartGiftCardBusy ) {
							return;
						}
						wpEasyCartGiftCardBusy = true;
						try { /* 6.0.2: the same checks and saves as Place order before the gift card pays */
							const wpEasyCartReady = ( \'function\' === typeof wpeasycart_onepage_ready_to_pay ) ? await wpeasycart_onepage_ready_to_pay() : true;
							if ( wpEasyCartReady ) {
								await wpEasyCartHandlePaymentMethodSubmission( event, wpEasyCartGiftCard );
							}
						} finally {
							wpEasyCartGiftCardBusy = false;
						}';
			} else {
			echo '
						await wpEasyCartHandlePaymentMethodSubmission( event, wpEasyCartGiftCard );';
			}
			echo '
					} );
				}';
		}
		echo '
			}
			jQuery( document ).ready( function() {
				wpEasyCartSquareInit();
			} );
			} )();
		</script>';
	}

	public function print_square_payment_express() {
		if ( ! apply_filters( 'wpeasycart_express_checkout_allowed', true, 'square', $this ) ) { /* 6.0.2 */
			return;
		}
		if ( get_option( 'ec_option_square_digital_wallet' ) && apply_filters( 'wp_easycart_allow_paypal_express', false ) && (int) ( $this->order_totals->get_converted_grand_total() * 100 ) >= 50 ) {
			echo '<div class="wp-easycart-square-express-checkout">';
				echo '<div id="wp-easycart-square-apple-pay-button"></div>';
				echo '<div id="wp-easycart-square-google-pay-button"></div>';
			echo '</div>';
		}
		
		if ( get_option( 'ec_option_onepage_checkout_tabbed' ) ) {
			$this->print_square_payment_script( true );
		}
	}

	public function print_square_payment_card() {
		if ( get_option( 'ec_option_square_gift_cards' ) && apply_filters( 'wp_easycart_allow_paypal_express', false ) ) {
			echo '<div id="wp-easycart-square-gift-card-container"></div>';
			echo '<button id="wp-easycart-square-gift-card-button" type="button">Pay with Gift Card</button>';
		}

		echo '<div id="wp-easycart-square-card-container"></div>';
		echo '<div id="wp-easycart-square-payment-status-container"></div>';

		$this->print_square_payment_script( true );
	}

	public function print_square_payment_button( $is_payment = false ) {
		if ( ! $is_payment && ! apply_filters( 'wpeasycart_express_checkout_allowed', true, 'square', $this ) ) { /* 6.0.2 */
			return;
		}
		if ( ( ! $is_payment && ! get_option( 'ec_option_square_digital_wallet' ) ) || ( ! $is_payment && '' == $GLOBALS['ec_cart_data']->cart_data->user_id && ( ! get_option( 'ec_option_allow_guest' ) || $this->has_downloads ) ) || ( ! $is_payment && $this->cart->has_preorder_items() ) || ( ! $is_payment && $this->cart->has_restaurant_items() ) ) {
			return;
		}

		if ( get_option( 'ec_option_square_gift_cards' ) && apply_filters( 'wp_easycart_allow_paypal_express', false ) ) {
			echo '<div id="wp-easycart-square-gift-card-container"></div>';
			echo '<button id="wp-easycart-square-gift-card-button" type="button">Pay with Gift Card</button>';
		}

		if ( get_option( 'ec_option_square_digital_wallet' ) && apply_filters( 'wp_easycart_allow_paypal_express', false ) && (int) ( $this->order_totals->get_converted_grand_total() * 100 ) >= 50 ) {
			echo '<div id="wp-easycart-square-apple-pay-button"></div>';
			echo '<div id="wp-easycart-square-google-pay-button"></div>';
		}

		if ( $is_payment ) {
			echo '<div id="wp-easycart-square-card-container"></div>';
		}

		echo '<div id="wp-easycart-square-payment-status-container"></div>';

		$this->print_square_payment_script( $is_payment );
	}

	/**
	 * The Square Apple Pay / Google Pay sheet's lines.
	 *
	 * @param ec_order_totals|null $order_totals 6.0.2: the totals the sheet shows ( get_wallet_order_totals() ), else the page's.
	 * @return array
	 */
	public function get_dynamic_square_line_items( $order_totals = null ) {
		$order_totals = ( $order_totals instanceof ec_order_totals ) ? $order_totals : $this->order_totals;
		$return_arr   = array( (object) array(
			'label'     => wp_easycart_language()->get_text( 'cart_totals', 'cart_totals_subtotal' ),
			'amount'    => number_format( $order_totals->sub_total, 2, '.', '' ),
		) );

		if ( $order_totals->tax_total > 0 ) {
			$return_arr[] = (object) array(
				'label'     => wp_easycart_language()->get_text( 'cart_totals', 'cart_totals_tax' ),
				'amount'    => number_format( $order_totals->tax_total, 2, '.', '' ),
			);
		}

		if ( get_option( 'ec_option_use_shipping' ) && ( $this->cart->shippable_total_items > 0 || $order_totals->handling_total > 0 ) && $order_totals->shipping_total > 0 ) {
			$return_arr[] = (object) array(
				'label'     => wp_easycart_language()->get_text( 'cart_totals', 'cart_totals_shipping' ),
				'amount'    => number_format( $order_totals->shipping_total, 2, '.', '' ),
			);
		}

		if ( $order_totals->discount_total > 0 ) {
			$return_arr[] = (object) array(
				'label'     => wp_easycart_language()->get_text( 'cart_totals', 'cart_totals_discounts' ),
				'amount'    => number_format( $order_totals->discount_total, 2, '.', '' ),
			);
		}

		if ( $this->tax->is_duty_enabled() && $order_totals->duty_total > 0 ) {
			$return_arr[] = (object) array(
				'label'     => wp_easycart_language()->get_text( 'cart_totals', 'cart_totals_duty' ),
				'amount'    => number_format( $order_totals->duty_total, 2, '.', '' ),
			);
		}

		if ( $this->tax->is_vat_enabled() && $order_totals->vat_total > 0 ) {
			$return_arr[] = (object) array(
				'label'     => wp_easycart_language()->get_text( 'cart_totals', 'cart_totals_vat' ),
				'amount'    => number_format( $order_totals->vat_total, 2, '.', '' ),
			);
		}

		if ( get_option( 'ec_option_enable_easy_canada_tax' ) && $order_totals->gst_total > 0  ) {
			$return_arr[] = (object) array(
				'label'     => wp_easycart_canada_tax_label( 'gst', $this->tax->shipping_state ) . ( ( $this->tax->gst_rate > 0 ) ? ' ' .$this->tax->gst_rate . '%' : '' ),
				'amount'    => number_format( $order_totals->gst_total, 2, '.', '' ),
			);
		}

		if ( get_option( 'ec_option_enable_easy_canada_tax' ) && $order_totals->pst_total > 0  ) {
			$return_arr[] = (object) array(
				'label'     => wp_easycart_canada_tax_label( 'pst', $this->tax->shipping_state ) . ( ( $this->tax->pst_rate > 0 ) ? ' ' .$this->tax->pst_rate . '%' : '' ),
				'amount'    => number_format( $order_totals->pst_total, 2, '.', '' ),
			);
		}

		if ( get_option( 'ec_option_enable_easy_canada_tax' ) && $order_totals->hst_total > 0 ) {
			$return_arr[] = (object) array(
				'label'     => wp_easycart_canada_tax_label( 'hst', $this->tax->shipping_state ) . ( ( $this->tax->hst_rate > 0 ) ? ' ' .$this->tax->hst_rate . '%' : '' ),
				'amount'    => number_format( $order_totals->hst_total, 2, '.', '' ),
			);
		}

		/* 6.0.2: flex fees are part of the total, so the sheet lists them too ( a card fee reads as its own line ). */
		foreach ( wp_easycart_wallet_fee_lines( $order_totals ) as $fee_line ) {
			$return_arr[] = (object) array(
				'label'  => $fee_line['label'],
				'amount' => number_format( $fee_line['amount'], 2, '.', '' ),
			);
		}

		return $return_arr;
	}

	public function print_stripe_locale_mapper() {
		echo "
		if ( typeof window.wpeasycart_stripe_map_locale === 'undefined' ) {
			window.wpeasycart_stripe_map_locale = function( raw ) {
				var supported = [ 'ar','bg','cs','da','de','el','en','en-GB','es','es-419','et','fi','fil','fr','fr-CA','he','hr','hu','id','it','ja','ko','lt','lv','ms','mt','nb','nl','pl','pt','pt-BR','ro','ru','sk','sl','sv','th','tr','vi','zh','zh-HK','zh-TW' ];
				var aliases = { 'no': 'nb', 'nn': 'nb', 'tl': 'fil', 'iw': 'he', 'in': 'id' };
				var canonical = {};
				var i;
				for ( i = 0; i < supported.length; i++ ) {
					canonical[ supported[ i ].toLowerCase() ] = supported[ i ];
				}
				raw = String( raw || '' ).trim().replace( /_/g, '-' ).toLowerCase();
				if ( canonical[ raw ] ) { return canonical[ raw ]; }
				var base = raw.split( '-' )[ 0 ];
				if ( aliases[ base ] ) { base = aliases[ base ]; }
				if ( canonical[ base ] ) { return canonical[ base ]; }
				return 'auto';
			};
		}";
	}

	public function get_stripe_element_locale_js() {
		$forced = apply_filters( 'wp_easycart_stripe_element_locale', '' );
		if ( '' !== $forced ) {
			return "'" . esc_attr( $forced ) . "'";
		}
		return 'wpeasycart_stripe_map_locale( wpeasycart_ajax_object.current_language )';
	}

	public function print_stripe_script_v2( $is_payment = false ) {
		$mount_items = array();
		$link_enabled = ( get_option( 'ec_option_stripe_link' ) && '' == $GLOBALS['ec_cart_data']->cart_data->user_id );
		$shipping_enabled = ( get_option( 'ec_option_use_shipping' ) && $this->shipping_address_allowed && ( $this->cart->shippable_total_items > 0 || $this->order_totals->handling_total > 0 || $this->cart->excluded_shippable_total_items > 0 ) );
		$stripe_address_enabled = get_option( 'ec_option_stripe_address_autocomplete' );
		$enable_express = false;
		$wpec_client_secret = $this->get_stripe_intent_client_secret();
		if ( '' === $wpec_client_secret && $this->stripe_held_by_protection() ) {
			return; /* 6.0.2 checkout protection: the storefront script shows the pause or the human check instead */
		}
		echo "<script>
		try {
			jQuery( document ).ready( function() {
				var clientSecret = '" . esc_attr( $wpec_client_secret ) . "';
				const appearance = {
					theme: '" . esc_attr( get_option( 'ec_option_stripe_payment_theme' ) ) . "',
				};
				const options = {
					clientSecret: clientSecret,
					appearance: appearance,
				};
				const elements = stripe.elements( options );";

		if ( $link_enabled && ( ! get_option( 'ec_option_onepage_checkout_tabbed' ) || ! $is_payment ) ) {
			$this->print_stripe_script_link_auth_v2();
			$mount_items[] = 'linkAuthenticationElement.mount( "#link-authentication-element" );';
		}
		if ( $stripe_address_enabled && $shipping_enabled && ( ! get_option( 'ec_option_onepage_checkout_tabbed' ) || ! $is_payment ) ) {
			$this->print_stripe_script_address_v2( true );
			$mount_items[] = 'shippingaddressElement.mount( "#shipping-address-element" );';
		}
		if ( $stripe_address_enabled && ( ( get_option( 'ec_option_onepage_checkout_tabbed' ) && ! $shipping_enabled && ! $is_payment ) || ( get_option( 'ec_option_onepage_checkout_tabbed' ) && $shipping_enabled && $is_payment ) || ! get_option( 'ec_option_onepage_checkout_tabbed' ) ) ) {
			$this->print_stripe_script_address_v2( false, ( ! get_option( 'ec_option_onepage_checkout_tabbed' ) && $shipping_enabled ) );
			$mount_items[] = 'billingaddressElement.mount( "#billing-address-element" );';
		}
		if ( $enable_express && ( ! get_option( 'ec_option_onepage_checkout_tabbed' ) || ! $is_payment ) ) {
			$this->print_stripe_script_payment_express_v2();
			$mount_items[] = 'expressCheckoutElement.mount( "#wpec-express-checkout-element" );';
		}
		if ( ! get_option( 'ec_option_onepage_checkout_tabbed' ) || $is_payment ) {
			$this->print_stripe_script_payment_v2();
			if ( $this->order_totals->grand_total > 0 ) {
				$mount_items[] = 'paymentElement.mount( "#ec_stripe_card_row" );';
			}
		}
		foreach ( $mount_items as $mount_item ) {
			echo '
			' . $mount_item;
		}
		echo '
			} );
		} catch( err ) {
			alert( "Your WP EasyCart with Stripe has a problem: " + err.message + ". Contact WP EasyCart for assistance." );
		}
		</script>';
	}

	public function print_stripe_script_link_auth_v2() {
		echo "
		var emailAddressTimer;
		const linkAuthenticationElement = elements.create( 'linkAuthentication', {
			defaultValues: {
				email: '" . esc_attr( $GLOBALS['ec_cart_data']->cart_data->email ) . "'
			}
		} );
		linkAuthenticationElement.on('change', (event) => {
			jQuery( document.getElementById( 'ec_contact_email' ) ).val( event.value.email );
			if ( event.complete ) {
				jQuery( document.getElementById( 'ec_contact_email_complete' ) ).val( '1' );
				jQuery( document.getElementById( 'ec_email_order1_error' ) ).hide();
				if ( jQuery( document.getElementById( 'ec_email_order2_error' ) ).length ) {
					jQuery( document.getElementById( 'ec_email_order2_error' ) ).hide();
				}
				clearTimeout( emailAddressTimer );
				emailAddressTimer = setTimeout( function() {
					wp_easycart_update_contact_email_v2();
				}, 800 );
			} else {
				if ( jQuery( document.getElementById( 'link-authentication-element' ) ).hasClass( 'ec_cart_stripe_address_is_init' ) ) {
					jQuery( document.getElementById( 'link-authentication-element' ) ).removeClass( 'ec_cart_stripe_address_is_init' );
				} else {
					jQuery( document.getElementById( 'ec_contact_email_complete' ) ).val( '0' );
					jQuery( document.getElementById( 'ec_email_order1_error' ) ).show();
					if ( jQuery( document.getElementById( 'ec_email_order2_error' ) ).length ) {
						jQuery( document.getElementById( 'ec_email_order2_error' ) ).show();
					}
				}
			}
		} );";
	}

	public function print_stripe_script_address_v2( $is_shipping = true, $custom_elements = false ) {
		$country_list = $GLOBALS['ec_countries']->countries;
		$country_string = '';
		$is_first_country = true;
		foreach ( $country_list as $country ) {
			if ( ! $is_first_country ) {
				$country_string .= ',';
			}
			$country_string .= "'" . esc_attr( $country->iso2_cnt ) . "'";
			$is_first_country = false;
		}
		$type = ( $is_shipping ) ? 'shipping' : 'billing';
		echo "var " . esc_attr( $type ) . "AddressTimer;";
		if ( $custom_elements ) {
		$this->print_stripe_locale_mapper();
		echo "
		const billingOptions = {
			clientSecret: clientSecret,
			appearance: appearance,
			locale: " . $this->get_stripe_element_locale_js() . "
		};
		const billingElements = stripe.elements( billingOptions );
		const billingaddressElement = billingElements.create( 'address', {";
		} else {
		echo "
		const " . esc_attr( $type ) . "addressElement = elements.create( 'address', {";
		}
		echo "
			mode: '" . esc_attr( $type ) . "',
			allowedCountries:[" . $country_string . "],";
		if ( get_option( 'ec_option_collect_user_phone' ) ) {
		echo "
			fields: {
				phone: 'always',
			},
			validation: {
				phone: {
					required: 'always',
				},
			},";
		}
		echo "
			defaultValues: {";
		if ( '' != $GLOBALS['ec_cart_data']->cart_data->{ $type . '_first_name' } || '' != $GLOBALS['ec_cart_data']->cart_data->{ $type . '_last_name' } ) {
		echo "
				name: '" . esc_attr( $GLOBALS['ec_cart_data']->cart_data->{ $type . '_first_name' } ) . " " . esc_attr( $GLOBALS['ec_cart_data']->cart_data->{ $type . '_last_name' } ) . "',";
		}
		if ( '' != $GLOBALS['ec_cart_data']->cart_data->{ $type . '_phone' } ) {
		echo "
				phone: '" . esc_attr( $GLOBALS['ec_cart_data']->cart_data->{ $type . '_phone' } ) . "',";
		}
		if ( '' != $GLOBALS['ec_cart_data']->cart_data->{ $type . '_address_line_1' } || '' != $GLOBALS['ec_cart_data']->cart_data->{ $type . '_city' } || '' != $GLOBALS['ec_cart_data']->cart_data->{ $type . '_state' } || '' != $GLOBALS['ec_cart_data']->cart_data->{ $type . '_zip' } || '' != $GLOBALS['ec_cart_data']->cart_data->{ $type . '_country' } ) {
			echo "
				address: {";
			if ( '' != $GLOBALS['ec_cart_data']->cart_data->{ $type . '_address_line_1' } ) {
			echo "
					line1: '" . esc_attr( $GLOBALS['ec_cart_data']->cart_data->{ $type . '_address_line_1' } ) . "',";
			}
			if ( '' != $GLOBALS['ec_cart_data']->cart_data->{ $type . '_address_line_2' } ) {
			echo "
					line2: '" . esc_attr( $GLOBALS['ec_cart_data']->cart_data->{ $type . '_address_line_2' } ) . "',";
			}
			if ( '' != $GLOBALS['ec_cart_data']->cart_data->{ $type . '_city' } ) {
			echo "
					city: '" . esc_attr( $GLOBALS['ec_cart_data']->cart_data->{ $type . '_city' } ) . "',";
			}
			if ( '' != $GLOBALS['ec_cart_data']->cart_data->{ $type . '_state' } ) {
			echo "
					state: '" . esc_attr( $GLOBALS['ec_cart_data']->cart_data->{ $type . '_state' } ) . "',";
			}
			if ( '' != $GLOBALS['ec_cart_data']->cart_data->{ $type . '_zip' } ) {
			echo "
					postal_code: '" . esc_attr( $GLOBALS['ec_cart_data']->cart_data->{ $type . '_zip' } ) . "',";
			}
			if ( '' != $GLOBALS['ec_cart_data']->cart_data->{ $type . '_country' } ) {
			echo "
					country: '" . esc_attr( $GLOBALS['ec_cart_data']->cart_data->{ $type . '_country' } ) . "',";
			}
			echo "
				},";
		}
		echo "
			},
		} ).on( 'change', (event) => {
			jQuery( document.getElementById( 'ec_" . esc_attr( $type ) . "_complete' ) ).val( ( ( event.complete ) ? '1' : '0' ) );
			jQuery( document.getElementById( 'ec_" . esc_attr( $type ) . "_address_line_1' ) ).val( event.value.address.line1 );
			jQuery( document.getElementById( 'ec_" . esc_attr( $type ) . "_address_line_2' ) ).val( event.value.address.line2 );
			jQuery( document.getElementById( 'ec_" . esc_attr( $type ) . "_city' ) ).val( event.value.address.city );
			jQuery( document.getElementById( 'ec_" . esc_attr( $type ) . "_state' ) ).val( event.value.address.state );
			jQuery( document.getElementById( 'ec_" . esc_attr( $type ) . "_zip' ) ).val( event.value.address.postal_code );
			jQuery( document.getElementById( 'ec_" . esc_attr( $type ) . "_country' ) ).val( event.value.address.country );
			jQuery( document.getElementById( 'ec_" . esc_attr( $type ) . "_name' ) ).val( event.value.name );
			jQuery( document.getElementById( 'ec_" . esc_attr( $type ) . "_phone' ) ).val( event.value.phone );
			if ( jQuery( document.getElementById( '" . esc_attr( $type ) . "-address-element' ) ).hasClass( 'ec_cart_stripe_address_is_init' ) ) {
				jQuery( document.getElementById( '" . esc_attr( $type ) . "-address-element' ) ).removeClass( 'ec_cart_stripe_address_is_init' );
			} else {
				if ( ! event.complete ) {
					" . esc_attr( $type ) . "addressElement.getValue();
				}";
		if ( ! get_option( 'ec_option_onepage_checkout_tabbed' ) && 'shipping' == $type ) {
			echo " else {
					clearTimeout( " . esc_attr( $type ) . "AddressTimer );
					" . esc_attr( $type ) . "AddressTimer = setTimeout( function() {
						wp_easycart_goto_shipping_v2( true );
						if ( jQuery( document.getElementById( 'ec_shipping_order_error' ) ).length ) {
							jQuery( document.getElementById( 'ec_shipping_order_error' ) ).hide();
						}
					}, 800 );
				}";
		}
		if ( 'billing' == $type ) {
			echo " else {
					clearTimeout( billingAddressTimer );
					billingAddressTimer = setTimeout( function() {
						var shipping_selector = ( ! jQuery( '#billing_address_type_different' ).length || jQuery( '#billing_address_type_different' ).is( ':checked' ) ) ? '1' : '0';
						ec_update_billing_address_display( shipping_selector, '" . esc_attr( wp_create_nonce( 'wp-easycart-update-billing-address-type-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ) . "' );
						jQuery( document.getElementById( 'ec_billing_order_error' ) ).hide();
					}, 800 );
				}";
		}
		echo"
			}
		} );
		if ( jQuery( document.getElementById( 'ec_cart_submit_order' ) ).length ) {
			jQuery( document.getElementById( 'ec_cart_submit_order' ) ).on( 'click', function() {
				" . esc_attr( $type ) . "addressElement.getValue();
			} );
		}";
	}

	public function print_stripe_script_payment_v2() {
		echo "
			const paymentElement = elements.create( 'payment', {";
		if ( 'accordion' == get_option( 'ec_option_stripe_payment_layout' ) ) {
		echo "
				layout: {
					type: 'accordion',
					defaultCollapsed: false,
					radios: false,
					spacedAccordionItems: false
				},";
		} else {
		echo "
				layout: {
					type: 'tabs',
					defaultCollapsed: false
				},";
		}
		/* 6.0.2: Apple Pay / Google Pay switched off leaves them out of the one-page payment form too, as on the classic step. */
		if ( ! get_option( 'ec_option_stripe_enable_apple_pay' ) ) {
			echo "
				wallets: {
					applePay: 'never',
					googlePay: 'never'
				},";
		}
		echo "
			} );
			paymentElement.addEventListener( 'change', function( event ){
				var displayError = document.getElementById( 'ec_card_errors' );
				if( event.error ){
					displayError.textContent = event.error.message;
				}else{
					displayError.textContent = '';
				}
				if ( event.value && event.value.type ) {
					var data = {
						action: 'ec_ajax_update_payment_type',
						payment_type: event.value.type,
						nonce: '" . esc_attr( wp_create_nonce( 'wp-easycart-update-payment-type-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ) . "'
					};
					jQuery.ajax( { url: wpeasycart_ajax_object.ajax_url, type: 'post', data: data, success: function( response ){
						var response_obj = JSON.parse( response );
						ec_update_cart( response_obj )
					} } );
				}
				if (event.complete) {
					var data = {
						action: 'ec_ajax_update_payment_complete',
						payment_type: event.value.type,
						nonce: '" . esc_attr( wp_create_nonce( 'wp-easycart-update-payment-complete-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ) . "'
					};
					jQuery.ajax( { url: wpeasycart_ajax_object.ajax_url, type: 'post', data: data } );
				}";
		if ( trim( get_option( 'ec_option_fb_pixel' ) ) != '' ) {
			// 6.0.2: the payment step fires AddPaymentInfo once per checkout ( wp_easycart_meta_add_payment_info() ); on the
			// single page one-page checkout a completed card also counts as reaching payment.
			echo "
				if ( event.complete && 'function' === typeof window.wpeasycart_meta_payment_reached ) {
					window.wpeasycart_meta_payment_reached();
				}";
		}
		echo "
			} );
			var form = ( jQuery( document.getElementById( 'ec_submit_order_form' ) ).length ) ? document.getElementById( 'ec_submit_order_form' ) : document.getElementById( 'wpeasycart_checkout_details_form' );
			if ( form.wpecStripeSubmit ) { /* 6.0.2: the section was drawn again: replace the listener, never add a second one */
				form.removeEventListener( 'submit', form.wpecStripeSubmit );
			}
			form.wpecStripeSubmit = function( event ){
				var payment_method = 'credit_card';
				if ( jQuery( 'input:radio[name=ec_cart_payment_selection]:checked' ).length ) {
					payment_method = jQuery( 'input:radio[name=ec_cart_payment_selection]:checked' ).val();
				}
				if ( payment_method != 'credit_card' ) {
					jQuery( document.getElementById( 'ec_submit_order_error' ) ).hide();
				} else {
					event.preventDefault();
					jQuery( document.getElementById( 'ec_cart_submit_order' ) ).hide( );
					jQuery( document.getElementById( 'ec_cart_submit_order_working' ) ).show( );
					jQuery( document.getElementById( 'stripe-success-cover' ) ).show( );
					jQuery( document.getElementById( 'ec_stripe_dynamic_error' ) ).hide( );
					jQuery( document.getElementById( 'ec_card_errors' ) ).hide( );";
			if ( get_option( 'ec_option_onepage_checkout_tabbed' ) || ! get_option( 'ec_option_stripe_address_autocomplete' ) ) {
		echo "
					var name = '" . esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->billing_first_name, ENT_QUOTES ) ) . " " . esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->billing_last_name, ENT_QUOTES ) ) . "';
					var address1 = '" . esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->billing_address_line_1, ENT_QUOTES ) ) . "';
					var address2 = '" . esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->billing_address_line_2, ENT_QUOTES ) ) . "';
					var city = '" . esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->billing_city, ENT_QUOTES ) ) . "';
					var state = '" . esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->billing_state, ENT_QUOTES ) ) . "';
					var zip = '" . esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->billing_zip, ENT_QUOTES ) ) . "';
					var country = '" . esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->billing_country, ENT_QUOTES ) ) . "';
					var phone = '" . esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->billing_phone, ENT_QUOTES ) ) . "';
					var shipping_name = '" . esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->shipping_first_name, ENT_QUOTES ) ) . " " . esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->shipping_last_name, ENT_QUOTES ) ) . "';
					var shipping_address1 = '" . esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->shipping_address_line_1, ENT_QUOTES ) ) . "';
					var shipping_address2 = '" . esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->shipping_address_line_2, ENT_QUOTES ) ) . "';
					var shipping_city = '" . esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->shipping_city, ENT_QUOTES ) ) . "';
					var shipping_state = '" . esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->shipping_state, ENT_QUOTES ) ) . "';
					var shipping_zip = '" . esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->shipping_zip, ENT_QUOTES ) ) . "';
					var shipping_country = '" . esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->shipping_country, ENT_QUOTES ) ) . "';
					var shipping_phone = '" . esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->shipping_phone, ENT_QUOTES ) ) . "';
					if ( 'function' === typeof wpeasycart_onepage_session_value ) { /* 6.0.2: what Place order just saved */
						name = ( wpeasycart_onepage_session_value( 'billing_first_name', '' ) + ' ' + wpeasycart_onepage_session_value( 'billing_last_name', '' ) ).trim() || name;
						address1 = wpeasycart_onepage_session_value( 'billing_address_line_1', address1 );
						address2 = wpeasycart_onepage_session_value( 'billing_address_line_2', address2 );
						city = wpeasycart_onepage_session_value( 'billing_city', city );
						state = wpeasycart_onepage_session_value( 'billing_state', state );
						zip = wpeasycart_onepage_session_value( 'billing_zip', zip );
						country = wpeasycart_onepage_session_value( 'billing_country', country );
						phone = wpeasycart_onepage_session_value( 'billing_phone', phone );
						shipping_name = ( wpeasycart_onepage_session_value( 'shipping_first_name', '' ) + ' ' + wpeasycart_onepage_session_value( 'shipping_last_name', '' ) ).trim() || shipping_name;
						shipping_address1 = wpeasycart_onepage_session_value( 'shipping_address_line_1', shipping_address1 );
						shipping_address2 = wpeasycart_onepage_session_value( 'shipping_address_line_2', shipping_address2 );
						shipping_city = wpeasycart_onepage_session_value( 'shipping_city', shipping_city );
						shipping_state = wpeasycart_onepage_session_value( 'shipping_state', shipping_state );
						shipping_zip = wpeasycart_onepage_session_value( 'shipping_zip', shipping_zip );
						shipping_country = wpeasycart_onepage_session_value( 'shipping_country', shipping_country );
						shipping_phone = wpeasycart_onepage_session_value( 'shipping_phone', shipping_phone );
					}";
			}
			if ( ! class_exists( 'Email_Encoder' ) && ! function_exists( 'eae_encode_emails' ) ) {
		echo "
					var email = '" . esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->email, ENT_QUOTES ) ) . "';
					if ( 'function' === typeof wpeasycart_onepage_session_value ) {
						email = wpeasycart_onepage_session_value( 'email', email );
					}";
			}
		echo "
					var ec_terms_agree = 0;
					if ( jQuery( document.getElementById( 'ec_terms_agree' ) ).length && jQuery( document.getElementById( 'ec_terms_agree' ) ).is( ':checked' ) ) {
						ec_terms_agree = 1;
					}
					var ec_cart_is_subscriber = 0;
					if( jQuery( document.getElementById( 'ec_cart_is_subscriber' ) ).length && jQuery( document.getElementById( 'ec_cart_is_subscriber' ) ).is( ':checked' ) ){
						ec_cart_is_subscriber = 1;
					}";
		if ( get_option( 'ec_option_onepage_checkout_tabbed' ) || ! get_option( 'ec_option_stripe_address_autocomplete' ) ) {
		echo "
					var additionalData = {
						name: name,
						address_line1: address1,
						address_city: city,
						address_state: state,
						address_zip: zip
					};";
		}
		if ( $this->order_totals->grand_total > 0 ) {
		/* 6.0.2: the return address is a JavaScript string, not an HTML attribute: esc_attr() turned its & into &amp;, so a
		   payment method that leaves the page came back without stripe=returning and the order was left to the webhook. */
		$wpec_stripe_return = wp_json_encode( esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_payment', array( 'stripe' => 'returning', 'wpecnonce' => wp_create_nonce( 'wp-easycart-stripe-pi-order-complete-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ) ) ) );
		echo "
					var wpecStripeReturn = " . $wpec_stripe_return . ";"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode() of esc_url_raw().
		echo "
					( elements.fetchUpdates ? elements.fetchUpdates() : Promise.resolve() ).then( function() { return stripe.confirmPayment( {
						elements,
						confirmParams: {
							return_url: wpecStripeReturn,";
		if ( get_option( 'ec_option_onepage_checkout_tabbed' ) || ! get_option( 'ec_option_stripe_address_autocomplete' ) ) {
		echo "
							shipping: {
								address: {
									line1: shipping_address1,
									city: shipping_city,
									country: shipping_country,
									line2: shipping_address2,
									postal_code: shipping_zip,
									state: shipping_state
								},
								name: shipping_name,
								phone: shipping_phone
							},
							payment_method_data: {
								billing_details: {
									address: {
										city: city,
										country: country,
										line1: address1,
										line2: address2,
										postal_code: zip,
										state: state
									},";
		if ( ! class_exists( 'Email_Encoder' ) && !function_exists( 'eae_encode_emails' ) ){
		echo "
									email: email,";
		}
		echo "
									name: name";
		if ( $GLOBALS['ec_cart_data']->cart_data->billing_phone != '' ) {
		echo ",
									phone: phone";
		}
		echo "
								}
							}";
		}
		echo "
						},
						redirect: 'if_required'
					} ); } ).then( function( result ){
						if( result.error ){
							jQuery( document.getElementById( 'ec_cart_submit_order' ) ).show( );
							jQuery( document.getElementById( 'ec_cart_submit_order_working' ) ).hide( );
							jQuery( document.getElementById( 'stripe-success-cover' ) ).fadeOut( );
							jQuery( document.getElementById( 'ec_stripe_dynamic_error' ) ).fadeIn( ).find( 'div' ).html( result.error.message );
							jQuery( document.getElementById( 'ec_card_errors' ) ).fadeIn( ).html( result.error.message );
						}else{
							if ( 'processing' == result.paymentIntent.status || 'succeeded' == result.paymentIntent.status || 'requires_capture' == result.paymentIntent.status ) {
								var data = {
									action: 'ec_ajax_get_stripe_complete_payment_main',
									language: wpeasycart_ajax_object.current_language,
									ec_terms_agree: ec_terms_agree,
									ec_cart_is_subscriber: ec_cart_is_subscriber,
									payment_status: result.paymentIntent.status,
									nonce: '" . esc_attr( wp_create_nonce( 'wp-easycart-get-stripe-complete-payment-main-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ) . "'
								};
								var paidIntent = result.paymentIntent;
								jQuery.ajax({url: wpeasycart_ajax_object.ajax_url, type: 'post', data: data, success: function( result ){
									wpeasycart_checkout_goto( result );
								}, error: function() {
									/* 6.0.2: the payment went through but the order call failed: the return page finds or makes the order
									   for this payment ( as after a redirect ), so the shopper is never left under the cover or asked to pay again. */
									window.location.href = wpecStripeReturn + ( -1 === wpecStripeReturn.indexOf( '?' ) ? '?' : '&' ) + 'payment_intent=' + encodeURIComponent( paidIntent.id ) + '&payment_intent_client_secret=' + encodeURIComponent( paidIntent.client_secret ) + '&redirect_status=' + encodeURIComponent( paidIntent.status );
								} } );
							} else {
								jQuery( document.getElementById( 'ec_cart_submit_order' ) ).show( ); /* 6.0.2: not paid: no order */
								jQuery( document.getElementById( 'ec_cart_submit_order_working' ) ).hide( );
								jQuery( document.getElementById( 'stripe-success-cover' ) ).fadeOut( );
								jQuery( document.getElementById( 'ec_stripe_dynamic_error' ) ).fadeIn( );
							}
						}
					} ).catch( function() {
						/* 6.0.2: Stripe.js refused before any payment ( network, a closed window ): Place order comes back. */
						jQuery( document.getElementById( 'ec_cart_submit_order' ) ).show( );
						jQuery( document.getElementById( 'ec_cart_submit_order_working' ) ).hide( );
						jQuery( document.getElementById( 'stripe-success-cover' ) ).fadeOut( );
						jQuery( document.getElementById( 'ec_stripe_dynamic_error' ) ).fadeIn( );
					} );";
		} else {
			echo "
					var data = {
						action: 'ec_ajax_v2_complete_free_payment',
						language: wpeasycart_ajax_object.current_language,
						ec_terms_agree: ec_terms_agree,
						ec_cart_is_subscriber: ec_cart_is_subscriber,
						nonce: '" . esc_attr( wp_create_nonce( 'wp-easycart-v2-complete-payment-main-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ) . "'
					};
					jQuery.ajax({url: wpeasycart_ajax_object.ajax_url, type: 'post', data: data, success: function( result ){
						wpeasycart_checkout_goto( result );
					} } );";
		}
		echo "
				}
			};
			form.addEventListener( 'submit', form.wpecStripeSubmit );";
	}

	public function print_stripe_script_payment_express_v2() {
		$payment_method_types = array();
		if ( get_option( 'ec_option_stripe_enable_apple_pay' ) ) {
			$payment_method_types[] = "'card'";
		}
		if ( get_option( 'ec_option_stripe_cashapp' ) ) {
			$payment_method_types[] = "'cashapp'";
		}
		if ( get_option( 'ec_option_stripe_alipay' ) ) {
			$payment_method_types[] = "'alipay'";
		}
		if ( get_option( 'ec_option_stripe_grabpay' ) ) {
			$payment_method_types[] = "'grabpay'";
		}
		if ( get_option( 'ec_option_stripe_wechat' ) ) {
			$payment_method_types[] = "'wechat_pay'";
		}
		if ( get_option( 'ec_option_stripe_link' ) ) {
			$payment_method_types[] = "'link'";
		}
		echo "const expressCheckoutElement = elements.create( 'expressCheckout' );
		expressCheckoutElement.on('click', (event) => {
			const options = {
				emailRequired: true,
				phoneNumberRequired: true,
				shippingAddressRequired: true,
				lineItems: " . wp_json_encode( $this->get_stripe_express_cart_items() ) . ",
				shippingRates: " . wp_json_encode( $this->get_stripe_express_shipping_items( wp_easycart_language()->get_text( 'cart_estimate_shipping', 'cart_estimate_shipping_standard' ), wp_easycart_language()->get_text( 'cart_estimate_shipping', 'cart_estimate_shipping_express' ) ) ) . "
			};
			event.resolve( options );
		} );
		expressCheckoutElement.on( 'shippingaddresschange', function( ev ) {
			var data = {
				action: 'ec_ajax_get_stripe_express_shipping_dynamic',
				shippingAddress: ev.address,
				language: wpeasycart_ajax_object.current_language,
				nonce: '" . esc_attr( wp_create_nonce( 'wp-easycart-get-stripe-shipping-dynamic-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ) . "'
			};
			jQuery.ajax( { url: wpeasycart_ajax_object.ajax_url, type: 'post', data: data, success: function( result ) {
				var json_result = JSON.parse( result );
				ec_update_cart( json_result.cart_data );
				if( json_result.shipping_rates.length > 0 ) {
					ev.resolve( {
						shippingRates: json_result.shipping_rates,
						lineItems: json_result.line_items,
					} );
				}
			} } );
		} );
		expressCheckoutElement.on( 'shippingratechange', function( ev ) {
			var data = {
				action: 'ec_ajax_get_stripe_express_shipping_rate_dynamic',
				shippingRate: ev.shippingRate.id,
				language: wpeasycart_ajax_object.current_language,
				nonce: '" . esc_attr( wp_create_nonce( 'wp-easycart-get-stripe-shipping-dynamic-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ) . "'
			};
			jQuery.ajax( { url: wpeasycart_ajax_object.ajax_url, type: 'post', data: data, success: function( result ) {
				var json_result = JSON.parse( result );
				ec_update_cart( json_result.cart_data );
				if( json_result.shipping_rates.length > 0 ) {
					ev.resolve( {
						shippingRates: json_result.shipping_rates,
						lineItems: json_result.line_items,
					} );
				}
			} } );
		} );
		expressCheckoutElement.on( 'paymentmethod', function( ev ) {
			var data = {
				action: 'ec_ajax_get_stripe_shipping_dynamic',
				shippingAddress: ev.shippingAddress,
				language: wpeasycart_ajax_object.current_language,
				nonce: '" . esc_attr( wp_create_nonce( 'wp-easycart-get-stripe-shipping-dynamic-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ) . "',
			};
			jQuery.ajax({url: wpeasycart_ajax_object.ajax_url, type: 'post', data: data, success: function( result ) {
				var json_result = JSON.parse( result );
				if ( ! json_result.is_valid ) {
					jQuery( location ).attr( \'href\', json_result.redirect );
				} else {
					ec_update_cart( json_result.cart_data );
					stripe.confirmPaymentIntent(
						clientSecret,
						{
							payment_method: ev.paymentMethod.id,
						}
					).then(
						function( confirmResult ) {
							if ( confirmResult.error ) {
								ev.complete( 'fail' );
							} else if ( 'succeeded' == confirmResult.paymentIntent.status ) {
								var data = {
									action: 'ec_ajax_get_stripe_complete_payment',
									payment_id: ev.paymentMethod.id,
									shipping_address: ev.shippingAddress,
									shipping_method: ev.shippingOption.id,
									billing_address: ev.paymentMethod.billing_details.address,
									billing_name: ev.paymentMethod.billing_details.name,
									billing_phone: ev.paymentMethod.billing_details.phone,
									billing_email: ev.paymentMethod.billing_details.email,
									card_type: ev.paymentMethod.card.brand,
									last_4: ev.paymentMethod.card.last4,
									exp_month: ev.paymentMethod.card.exp_month,
									exp_year: ev.paymentMethod.card.exp_year,
									email: ev.payerEmail,
									phone: ev.payerPhone,
									clientSecret: clientSecret,
									language: wpeasycart_ajax_object.current_language,
									nonce: '" . esc_attr( wp_create_nonce( 'wp-easycart-get-stripe-complete-payment-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ) . "',
								};
								jQuery.ajax( { url: wpeasycart_ajax_object.ajax_url, type: 'post', data: data, success: function( result ) {
									ev.complete( 'success' );
									wpeasycart_checkout_goto( result );
								} } );
							} else {
								ev.complete( 'success' );
								stripe.handleCardPayment( clientSecret ).then( function( result ) {
									if ( result.error ) {
										// error
									} else {
										var data = {
											action: 'ec_ajax_get_stripe_complete_payment',
											payment_id: ev.paymentMethod.id,
											shipping_address: ev.shippingAddress,
											shipping_method: ev.shippingOption.id,
											billing_address: ev.paymentMethod.billing_details.address,
											billing_name: ev.paymentMethod.billing_details.name,
											billing_phone: ev.paymentMethod.billing_details.phone,
											billing_email: ev.paymentMethod.billing_details.email,
											card_type: ev.paymentMethod.card.brand,
											last_4: ev.paymentMethod.card.last4,
											exp_month: ev.paymentMethod.card.exp_month,
											exp_year: ev.paymentMethod.card.exp_year,
											email: ev.payerEmail,
											phone: ev.payerPhone,
											clientSecret:clientSecret,
											language: wpeasycart_ajax_object.current_language,
											nonce: '" . esc_attr( wp_create_nonce( 'wp-easycart-get-stripe-complete-payment-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ) . "'
										};
										jQuery.ajax({url: wpeasycart_ajax_object.ajax_url, type: 'post', data: data, success: function( result ) {
											wpeasycart_checkout_goto( result );
										} } );
									}
								} );
							}
						}
					);
				}
			} } );
		} );";
	}

	public function print_stripe_payment_button( $is_payment = false ) {
		/**
		 * Show the express buttons outside the checkout form ( cart page, top of the one-page checkout ). They skip the
		 * form, so WP EasyCart PRO hides them while a required checkout field applies.
		 *
		 * @since 6.0.2
		 * @param bool        $allowed  Show them.
		 * @param string      $which    stripe | square | paypal | checkout ( the one-page express area ).
		 * @param ec_cartpage $cartpage The cart.
		 */
		if ( ! $is_payment && ! apply_filters( 'wpeasycart_express_checkout_allowed', true, 'stripe', $this ) ) {
			return false;
		}
		if ( ( get_option( 'ec_option_stripe_disable_wallet_first' ) && ! $is_payment ) || ( ! $is_payment && '' == $GLOBALS['ec_cart_data']->cart_data->user_id && ( ! get_option( 'ec_option_allow_guest' ) || $this->has_downloads ) ) || ( ! $is_payment && $this->cart->has_preorder_items() ) || ( ! $is_payment && $this->cart->has_restaurant_items() ) ) {
			return false;
		}

		if ( get_option( 'ec_option_stripe_enable_apple_pay' ) && apply_filters( 'wp_easycart_allow_paypal_express', false ) && (int) ( $this->order_totals->get_converted_grand_total() * 100 ) >= 50 ) {
			if ( (float) apply_filters( 'wpeasycart_minimum_order_total', get_option( 'ec_option_minimum_order_total' ) ) > 0 && (float) apply_filters( 'wpeasycart_minimum_order_total', get_option( 'ec_option_minimum_order_total' ) ) > $this->cart->subtotal ) {
				echo '<div id="ec-stripe-wallet-button"></div>';
			} else {
				$client_secret = $this->get_stripe_intent_client_secret();
				if ( '' === $client_secret && $this->stripe_held_by_protection() ) {
					return false; /* 6.0.2 checkout protection: no wallet button while this shopper is paused or owes a human check */
				}
				if ( $is_payment ) {
					echo '<div class="ec_cart_option_row" id="ec_apple_pay_row">
						<input type="radio" class="no_wrap" name="ec_cart_payment_selection" id="ec_payment_apple" value="apple_pay"';
						if ( $this->get_selected_payment_method() == "apple_pay" ) {
							echo ' checked="checked"';
						}
						echo 'onChange="ec_update_payment_display( \'' . esc_attr( wp_create_nonce( 'wp-easycart-update-payment-method-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ) . '\' );" /> ' . wp_easycart_language()->get_text( 'cart_payment_information', 'cart_payment_information_apple_pay' ) . '
					</div>
					<div id="ec_apple_pay_form"';
					if ( $this->get_selected_payment_method() == "apple_pay" ) {
						echo ' style="display:block;"';
					} else {
						echo ' style="display:none;"';
					}
					echo '><div class="ec_cart_box_section">';
				} else {
					echo '<div id="ec-stripe-wallet-button">';
				}
				echo '<div id="payment-request-button" style="float:left; width:100%;">
				  <!-- A Stripe Element will be inserted here. -->
				</div>';
				if ( $is_payment ) {
					echo '</div></div>';
				}
				echo '<div id="stripe-success-cover" style="display:none; cursor:default; position:fixed; top:0; left:0; width:100%; height:100%; z-index:999999; background-color: rgba(0, 0, 0, 0.8); color:#FFF;">
					<style>
					@keyframes rotation{
						0%  { transform:rotate(0deg); }
						100%{ transform:rotate(359deg); }
					}
					</style>
					<div style=\'font-family: "HelveticaNeue", "HelveticaNeue-Light", "Helvetica Neue Light", helvetica, arial, sans-serif; font-size: 14px; text-align: center; -webkit-box-sizing: border-box; -moz-box-sizing: border-box; -ms-box-sizing: border-box; box-sizing: border-box; width: 350px; top: 50%; left: 50%; position: absolute; margin-left: -165px; margin-top: -80px; cursor: pointer; text-align: center;\'>
						<div class="paypal-checkout-loader">
							<div style="height: 30px; width: 30px; display: inline-block; box-sizing: content-box; opacity: 1; filter: alpha(opacity=100); -webkit-animation: rotation .7s infinite linear; -moz-animation: rotation .7s infinite linear; -o-animation: rotation .7s infinite linear; animation: rotation .7s infinite linear; border-left: 8px solid rgba(0, 0, 0, .2); border-right: 8px solid rgba(0, 0, 0, .2); border-bottom: 8px solid rgba(0, 0, 0, .2); border-top: 8px solid #fff; border-radius: 100%;"></div>
						</div>
					</div>
				</div>';
				if ( get_option( 'ec_option_payment_process_method' ) == 'stripe' ) {
					$pkey = get_option( 'ec_option_stripe_public_api_key' );
				} else if ( get_option( 'ec_option_payment_process_method' ) == 'stripe_connect' && get_option( 'ec_option_stripe_connect_use_sandbox' ) ) {
					$pkey = get_option( 'ec_option_stripe_connect_sandbox_publishable_key' );
				} else {
					$pkey = get_option( 'ec_option_stripe_connect_production_publishable_key' );
				}
				$pkey = apply_filters( 'wp_easycart_stripe_connect_publishable_key', $pkey );
				echo '<script type="text/javascript">
					jQuery( document.getElementById( \'stripe-success-cover\' ) ).appendTo( document.body );';
				if ( ! wp_easycart_onepage_active() ) {
				echo '
					var stripe = Stripe( \'' . esc_attr( $pkey ) . '\' );';
				}
				$wallet_totals = $this->get_wallet_order_totals(); /* 6.0.2: the wallet is a card payment, so Card flex fees are in its sheet */
				echo '
					var clientSecret = \'' . esc_attr( $client_secret ) . '\';
					var paymentRequest = stripe.paymentRequest({
						country: \'' . esc_attr( get_option( 'ec_option_stripe_company_country' ) ) . '\',
						currency: \'' . esc_attr( strtolower( get_option( 'ec_option_stripe_currency' ) ) ) . '\',
						displayItems:' . json_encode( wpeasycart_get_cart_display_items( $this->cart, $wallet_totals, $wallet_totals->tax ) ) . ',
						total: {
							label: \'' . wp_easycart_language()->get_text( 'cart_totals', 'cart_totals_grand_total' ) . '\',
							amount: ' . (int) ( $wallet_totals->get_converted_grand_total() * 100 ) . ',
						},';
				if ( !$is_payment ) {
					if( get_option( 'ec_option_use_shipping' ) && $this->cart->shippable_total_items > 0 ) {
						echo '
						requestShipping: true,';
					}
					echo '
						requestPayerPhone: true,';
				}
				if ( !$is_payment && !$GLOBALS['ec_user']->user_id ) {
				echo '		
						requestPayerEmail: true,';
				}
				if ( !$is_payment && get_option( 'ec_option_use_shipping' ) && $this->cart->shippable_total_items > 0 ) {
				echo '
						shippingOptions: ' . json_encode( $this->ec_cart_display_shipping_methods_stripe_dynamic( wp_easycart_language()->get_text( 'cart_estimate_shipping', 'cart_estimate_shipping_standard' ),wp_easycart_language()->get_text( 'cart_estimate_shipping', 'cart_estimate_shipping_express' ) ) ) . ',';
				}
				echo '
					});
					paymentRequest.on( \'paymentmethod\', function(ev) {
						jQuery( document.getElementById( \'stripe-success-cover\' ) ).show();
						jQuery( document.getElementById( \'ec_stripe_error\' ) ).fadeOut();';
					if ( ! $is_payment && get_option( 'ec_option_use_shipping' ) && $this->cart->shippable_total_items > 0 ) {
					echo '
						var allowed_countries = [';
						$first_country = true;
						foreach ( $GLOBALS['ec_countries']->countries as $country ) {
							if ( ! $first_country ) {
					echo ',';
							}
					echo '"' . esc_attr( $country->iso2_cnt ) . '"';
							$first_country = false;
						}
					echo '];
						if ( ! allowed_countries.includes( ev.shippingAddress.country ) ) {
							var errorMessage = "';
					echo wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_shipping_invalid' ); // XSS OK
					echo '";
							jQuery( document.getElementById( \'stripe-success-cover\' ) ).fadeOut();
							jQuery( document.getElementById( \'ec_stripe_error\' ) ).fadeIn().find( \'div\' ).html( errorMessage );
							ev.complete( \'fail\' );
						} else {';
					}
					if ( !$is_payment ) {
					echo '
						var data = {
							action: \'ec_ajax_get_stripe_shipping_dynamic\',
							shippingAddress: ev.shippingAddress,';
						if ( get_option( 'ec_option_use_shipping' ) && $this->cart->shippable_total_items > 0 ) {
						echo '
							shipping_method: ( ev.shippingOption && ev.shippingOption.id ) ? ev.shippingOption.id : \'\','; /* 6.0.2: the intent charges the rate the sheet ends on */
						}
						echo '
							language: wpeasycart_ajax_object.current_language,
							nonce: \'' . esc_attr( wp_create_nonce( 'wp-easycart-get-stripe-shipping-dynamic-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ) . '\'
						};
						jQuery.ajax({url: wpeasycart_ajax_object.ajax_url, type: \'post\', data: data, success: function( result ) {
							var json_result = JSON.parse( result );
							if ( ! json_result.is_valid ) {
								jQuery( location ).attr( \'href\', json_result.redirect );
							} else {
								ec_update_cart( json_result.cart_data );';
						}
						echo '
								stripe.confirmPaymentIntent( clientSecret, {
									payment_method: ev.paymentMethod.id,
								} ).then( function( confirmResult ) {
									if ( confirmResult.error ) {
										jQuery( document.getElementById( \'stripe-success-cover\' ) ).fadeOut();
										jQuery( document.getElementById( \'ec_stripe_error\' ) ).fadeIn().find( \'div\' ).html( confirmResult.error.message );
										ev.complete( \'fail\' );
									} else if ( confirmResult.paymentIntent.status == \'succeeded\' ) {
										var data = {
											action: \'ec_ajax_get_stripe_complete_payment\',';
						if ( !$is_payment ) {
						echo '
											payment_id: ev.paymentMethod.id,';
							if( get_option( 'ec_option_use_shipping' ) && $this->cart->shippable_total_items > 0 ) {
								echo '
											shipping_address: ev.shippingAddress,
											shipping_method: ev.shippingOption.id,';
							}
							echo '
											billing_address: ev.paymentMethod.billing_details.address,
											billing_name: ev.paymentMethod.billing_details.name,
											billing_phone: ev.paymentMethod.billing_details.phone,
											billing_email: ev.paymentMethod.billing_details.email,';
						}
						echo '
											card_type: ev.paymentMethod.card.brand,
											last_4: ev.paymentMethod.card.last4,
											exp_month: ev.paymentMethod.card.exp_month,
											exp_year: ev.paymentMethod.card.exp_year,
											email: ev.payerEmail,
											phone: ev.payerPhone,';
						if ( get_option( 'ec_option_require_terms_agreement' ) ) {
						echo '
											ec_terms_agree: 1,';	
						}					
						echo '
											clientSecret:clientSecret,
											language: wpeasycart_ajax_object.current_language,
											nonce: \'' . esc_attr( wp_create_nonce( 'wp-easycart-get-stripe-complete-payment-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ) . '\'
										};
										jQuery.ajax({url: wpeasycart_ajax_object.ajax_url, type: \'post\', data: data, success: function( result ) {
											ev.complete( \'success\' );
											jQuery( location ).attr( \'href\', result );
										} } );
									} else {
										ev.complete( \'success\' );
										stripe.handleCardPayment( clientSecret ).then( function( result ) {
											if ( result.error ) {
												jQuery( document.getElementById( \'stripe-success-cover\' ) ).fadeOut();
												jQuery( document.getElementById( \'ec_stripe_error\' ) ).fadeIn().find( \'div\' ).html( result.error.message );
											} else {
												var data = {
													action: \'ec_ajax_get_stripe_complete_payment\',
													payment_id: ev.paymentMethod.id,';
								if ( !$is_payment ) {
									if( get_option( 'ec_option_use_shipping' ) && $this->cart->shippable_total_items > 0 ) {
										echo '
													shipping_address: ev.shippingAddress,
													shipping_method: ev.shippingOption.id,';
									}
									echo '
													billing_address: ev.paymentMethod.billing_details.address,
													billing_name: ev.paymentMethod.billing_details.name,
													billing_phone: ev.paymentMethod.billing_details.phone,
													billing_email: ev.paymentMethod.billing_details.email,';
								}
								echo '
													card_type: ev.paymentMethod.card.brand,
													last_4: ev.paymentMethod.card.last4,
													exp_month: ev.paymentMethod.card.exp_month,
													exp_year: ev.paymentMethod.card.exp_year,
													email: ev.payerEmail,
													phone: ev.payerPhone,';
								if ( get_option( 'ec_option_require_terms_agreement' ) ) {
								echo '
													ec_terms_agree: 1,';	
								}
								echo '
													clientSecret:clientSecret,
													language: wpeasycart_ajax_object.current_language,
													nonce: \'' . esc_attr( wp_create_nonce( 'wp-easycart-get-stripe-complete-payment-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ) . '\'
												};
												jQuery.ajax({url: wpeasycart_ajax_object.ajax_url, type: \'post\', data: data, success: function( result ) {
													jQuery( location ).attr( \'href\', result );
												} } );
											}
										} );
									}
								} );';
					if ( !$is_payment ) {
					echo '
							}
						} } );';
					}
					if ( ! $is_payment && get_option( 'ec_option_use_shipping' ) && $this->cart->shippable_total_items > 0 ) {
					echo '
						}';
					}
					echo '
					} );';
					if( get_option( 'ec_option_use_shipping' ) && $this->cart->shippable_total_items > 0 ) {
						echo '
					paymentRequest.on( \'shippingaddresschange\', function(ev) {
						var data = {
							action: \'ec_ajax_get_stripe_shipping_dynamic\',
							shippingAddress: ev.shippingAddress,
							language: wpeasycart_ajax_object.current_language,
							nonce: \'' . esc_attr( wp_create_nonce( 'wp-easycart-get-stripe-shipping-dynamic-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ) . '\'
						};
						jQuery.ajax({url: wpeasycart_ajax_object.ajax_url, type: \'post\', data: data, success: function( result ) {
							var json_result = JSON.parse( result );
							if ( ! json_result.is_valid ) {
								jQuery( location ).attr( \'href\', json_result.redirect );
							} else {
								ec_update_cart( json_result.cart_data );
								if( json_result.shipping_options.length > 0 ) {
									ev.updateWith( {
										status: \'success\',
										shippingOptions: json_result.shipping_options,
										displayItems: json_result.display_items,
										total: {
											label: \'' . wp_easycart_language()->get_text( 'cart_totals', 'cart_totals_grand_total' ) . '\',
											amount: json_result.total,
										  },
									} );
								}
							}
						} } );
					} );
					paymentRequest.on( \'shippingoptionchange\', function(ev) {
						var data = {
							action: \'ec_ajax_get_stripe_shipping_option_dynamic\',
							billingAddress: ev.billingAddress,
							shippingAddress: ev.shippingAddress,
							shippingOption: ev.shippingOption,
							language: wpeasycart_ajax_object.current_language,
							nonce: \'' . esc_attr( wp_create_nonce( 'wp-easycart-get-stripe-shipping-option-dynamic-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ) . '\'
						};
						jQuery.ajax({url: wpeasycart_ajax_object.ajax_url, type: \'post\', data: data, success: function( result ) {
							var json_result = JSON.parse( result );
							ec_update_cart( json_result.cart_data );
							ev.updateWith( {
								status: \'success\',
								shippingOptions: json_result.shipping_options,
								displayItems: json_result.display_items,
								total: {
								  label: \'' . wp_easycart_language()->get_text( 'cart_totals', 'cart_totals_grand_total' ) . '\',
								  amount: json_result.total,
								}
							} );
						} } );
					} );';
					}
					echo '
					paymentRequest.on( \'cancel\', function( ev ) {
						jQuery( document.getElementById( \'stripe-success-cover\' ) ).fadeOut();
					} );
					var elements = stripe.elements();
					var prButton = elements.create( \'paymentRequestButton\', {
						paymentRequest: paymentRequest
					} );';
				if ( get_option( 'ec_option_require_terms_agreement' ) ) {
				echo '
					prButton.on( \'click\', function( ev ) {
						if ( !confirm( \'' . esc_js( wp_easycart_language()->get_text( 'cart_payment_information', 'cart_payment_review_agree' ) ) . '.\' ) ) {
							paymentRequest.trigger( \'cancel\' );
						}
					} );';
				}
				echo '
					paymentRequest.canMakePayment().then( function( result ) {
						if ( result ) {';
				if ( $is_payment ) {
					echo '
					ec_update_payment_display( \'' . esc_attr( wp_create_nonce( 'wp-easycart-update-payment-method-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ) . '\' );';
				}
				echo '
							prButton.mount( \'#payment-request-button\' );';
				if ( wp_easycart_onepage_active() ) {
					echo '
							jQuery( \'.ec_cart_express_checkout\' ).show();';
				}
				echo '
						} else {';
				if ( $is_payment ) {
					echo '
							document.getElementById( \'ec_apple_pay_row\' ).style.display = \'none\';
							document.getElementById( \'ec_apple_pay_form\' ).style.display = \'none\';';
				}
				if ( wp_easycart_onepage_active() && 'paypal' != get_option( 'ec_option_payment_third_party' ) ) {
					echo '
							jQuery( \'.ec_cart_express_checkout\' ).hide();';
				}
				echo '
							document.getElementById( \'payment-request-button\' ).style.display = \'none\';
						}
					});
				</script>';
				if ( !$is_payment ) {
					echo '</div>';
				}
			}
		}
	}
	/* END SHIPPING METHOD FUNCTIONS */

	/* START COUPON FUNCTIONS */
	public function display_coupon() {
		if (	$this->cart->total_items > 0 ) {
			if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_coupon.php' ) )	
				include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_coupon.php' );
			else
				include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_coupon.php' );
		}
	}

	public function display_coupon_input( $redeem_text ) {
		echo "<input type=\"text\" name=\"ec_cart_coupon_code\" id=\"ec_cart_coupon_code\" class=\"ec_cart_coupon_input_text\" value=\"";
		if ( $this->coupon_code != "" )
			echo esc_attr( $this->coupon_code );
		echo "\" /><div class=\"ec_cart_coupon_code_redeem_button\"><a href=\"#\" onclick=\"ec_cart_coupon_code_redeem(); return false;\">" . esc_attr( $redeem_text ) . "</a></div>";
	}

	public function display_coupon_input_text() {
		echo "<input type=\"text\" name=\"ec_cart_coupon_code\" id=\"ec_cart_coupon_code\" class=\"ec_cart_coupon_input_text\" value=\"";
		if ( $this->coupon_code != "" )
			echo esc_attr( $this->coupon_code );

		echo "\" />";
	}

	public function display_coupon_input_button( $redeem_text ) {
		echo "<div class=\"ec_cart_coupon_code_redeem_button\"><a href=\"#\" onclick=\"ec_cart_coupon_code_redeem(); return false;\">" . esc_attr( $redeem_text ) . "</a></div>";
	}

	public function display_coupon_loader() {
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . "/design/theme/" . get_option( 'ec_option_base_theme' ) . "/ec_cart_page/loader.gif" ) )	
			echo "<div class=\"ec_cart_coupon_loader\" id=\"ec_cart_coupon_loader\"><img src=\"" . esc_attr( plugins_url( "wp-easycart-data/design/theme/" . get_option( 'ec_option_base_theme' ) . "/ec_cart_page/loader.gif", EC_PLUGIN_DATA_DIRECTORY ) ) . "\" /></div>";	
		else
			echo "<div class=\"ec_cart_coupon_loader\" id=\"ec_cart_coupon_loader\"><img src=\"" . esc_attr( plugins_url( "wp-easycart/design/theme/" . get_option( 'ec_option_base_theme' ) . "/ec_cart_page/loader.gif", EC_PLUGIN_DIRECTORY ) ) . "\" /></div>";
	}

	public function display_coupon_message() {
		if ( isset( $this->coupon ) )
			echo esc_attr( $this->coupon->message );
		else if ( $this->coupon_code != "" )
			echo wp_easycart_language()->get_text( 'cart_coupons', 'cart_invalid_coupon' );
	}
	/* END COUPON FUNCTIONS */

	/* START GIFT CARD FUNCTIONS */
	public function display_gift_card() {
		if (	$this->cart->total_items > 0 ) {
			if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_gift_card.php' ) )	
				include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_gift_card.php' );
			else
				include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_gift_card.php' );
		}
	}

	public function display_gift_card_input( $redeem_text ) {
		echo "<input type=\"text\" name=\"ec_cart_gift_card\" id=\"ec_cart_gift_card\" class=\"ec_cart_gift_card_input_text\" value=\"";
		if ( $this->gift_card != "" )
			echo esc_attr( $this->gift_card );
		echo "\" /><div class=\"ec_cart_gift_card_redeem_button\"><a href=\"#\" onclick=\"ec_cart_gift_card_redeem(); return false;\">" . esc_attr( $redeem_text ) . "</a></div>";
	}

	public function display_gift_card_input_text() {
		echo "<input type=\"text\" name=\"ec_cart_gift_card\" id=\"ec_cart_gift_card\" class=\"ec_cart_gift_card_input_text\" value=\"";
		if ( $this->gift_card != "" )
			echo esc_attr( $this->gift_card );

		echo "\" />";
	}

	public function display_gift_card_input_button( $redeem_text ) {
		echo "<div class=\"ec_cart_gift_card_redeem_button\"><a href=\"#\" onclick=\"ec_cart_gift_card_redeem(); return false;\">" . esc_attr( $redeem_text ) . "</a></div>";
	}

	public function display_gift_card_loader() {
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . "/design/theme/" . get_option( 'ec_option_base_theme' ) . "/ec_cart_page/loader.gif" ) )	
			echo "<div class=\"ec_cart_gift_card_loader\" id=\"ec_cart_gift_card_loader\"><img src=\"" . esc_attr( plugins_url( "wp-easycart-data/design/theme/" . get_option( 'ec_option_base_theme' ) . "/ec_cart_page/loader.gif", EC_PLUGIN_DATA_DIRECTORY ) ) . "\" /></div>";
		else
			echo "<div class=\"ec_cart_gift_card_loader\" id=\"ec_cart_gift_card_loader\"><img src=\"" . esc_attr( plugins_url( "wp-easycart/design/theme/" . get_option( 'ec_option_base_theme' ) . "/ec_cart_page/loader.gif", EC_PLUGIN_DIRECTORY ) ) . "\" /></div>";

	}

	public function display_gift_card_message() {
		if ( isset( $this->giftcard ) )
			echo esc_attr( $this->giftcard->message );
		else if ( $this->gift_card != "" )
			echo wp_easycart_language()->get_text( 'cart_coupons', 'cart_invalid_giftcard' );
	}
	/* END GIFT CARD FUNCTIONS */

	public function display_continue_to_shipping_button( $button_text ) {
		echo "<input type=\"submit\" class=\"ec_cart_continue_to_shipping_button\" value=\"" . esc_attr( $button_text ) . "\" onclick=\"return ec_cart_validate_checkout_info();\" />";
	}

	/* START CONTINUE TO PAYMENT FUNCTIONS */
	public function display_continue_to_payment_button( $button_text ) {
		echo "<input type=\"submit\" class=\"ec_cart_continue_to_payment_button\" value=\"" . esc_attr( $button_text ) . "\" onclick=\"return ec_cart_validate_checkout_shipping();\" />";
	}
	/* END CONTINUE TO PAYMENT FUNCTIONS */

	public function display_submit_order_button( $button_text ) {

		if ( isset( $_GET['subscription'] ) ) {
			echo "<input type=\"submit\" id=\"ec_submit_payment_button\" class=\"ec_cart_submit_order_button\" value=\"" . esc_attr( $button_text ) . "\" onclick=\"return ec_cart_validate_subscription_order();\" />";
		} else {
			echo "<input type=\"submit\" id=\"ec_submit_payment_button\" class=\"ec_cart_submit_order_button\" value=\"" . esc_attr( $button_text ) . "\" onclick=\"return ec_cart_validate_checkout_submit_order();\" />";
		}

	}

	public function display_cancel_order_button( $button_text ) {
		echo "<input type=\"button\" id=\"ec_cancel_payment_button\" class=\"ec_cart_submit_order_button\" value=\"" . esc_attr( $button_text ) . "\" onclick=\"return ec_cart_cancel_order();\" />";
	}

	public function display_order_review_button( $button_text ) {
		echo "<input type=\"button\" id=\"ec_review_payment_button\" class=\"ec_cart_submit_order_button\" value=\"" . esc_attr( $button_text ) . "\" onclick=\"if ( ec_cart_validate_checkout_submit_order() ) { ec_cart_show_review_panel(); } return false;\" />";
	}

	/* START ADDRESS REVIEW FUNCTIONS */
	public function display_address_review() {
		if (	$this->cart->total_items > 0 ) {
			if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_address_review.php' ) )	
				include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_address_review.php' );
			else	
				include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_address_review.php' );
		}

		if ( !get_option( 'ec_option_use_shipping' ) )
			echo "<script>jQuery('.ec_cart_address_review_middle').html('');</script>";
	}

	public function display_edit_address_link( $link_text ) {
		echo "<a href=\"" . esc_attr( wpeasycart_links()->get_cart_page( 'checkout_info' ) ) . "\">" . esc_attr( $link_text ) . "</a>";	
	}

	public function display_review_billing( $name ) {
		if ( $name == "first_name" )
			echo esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->billing_first_name, ENT_QUOTES ) );
		else if ( $name == "last_name" )
			echo esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->billing_last_name, ENT_QUOTES ) );
		else if ( $name == "address" )
			echo esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->billing_address_line_1, ENT_QUOTES ) );
		else if ( $name == "address2" )
			echo esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->billing_address_line_2, ENT_QUOTES ) );
		else if ( $name == "city" )
			echo esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->billing_city, ENT_QUOTES ) );
		else if ( $name == "state" )
			echo esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->billing_state, ENT_QUOTES ) );
		else if ( $name == "zip" )
			echo esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->billing_zip, ENT_QUOTES ) );
		else if ( $name == "country" )
			echo esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->billing_country, ENT_QUOTES ) );
		else if ( $name == "phone" )
			echo esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->billing_phone, ENT_QUOTES ) );

	}

	public function has_billing_address_line2() {
		if ( $GLOBALS['ec_cart_data']->cart_data->shipping_address_line_2 != "" ) {
			return true;
		} else {
			return false;
		}
	}

	public function display_review_shipping( $name ) {
		if ( $name == "first_name" )
			echo esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->shipping_first_name, ENT_QUOTES ) );
		else if ( $name == "last_name" )
			echo esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->shipping_last_name, ENT_QUOTES ) );
		else if ( $name == "address" )
			echo esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->shipping_address_line_1, ENT_QUOTES ) );
		else if ( $name == "address2" )
			echo esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->shipping_address_line_2, ENT_QUOTES ) );
		else if ( $name == "city" )
			echo esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->shipping_city, ENT_QUOTES ) );
		else if ( $name == "state" )
			echo esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->shipping_state, ENT_QUOTES ) );
		else if ( $name == "zip" )
			echo esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->shipping_zip, ENT_QUOTES ) );
		else if ( $name == "country" )
			echo esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->shipping_country, ENT_QUOTES ) );
		else if ( $name == "phone" )
			echo esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->shipping_phone, ENT_QUOTES ) );
	}

	public function has_shipping_address_line2() {
		if ( $GLOBALS['ec_cart_data']->cart_data->shipping_address_line_2 != "" ) {
			return true;
		} else {
			return false;
		}
	}

	public function display_selected_shipping_method() {
		echo wp_easycart_escape_html( $this->shipping->get_selected_shipping_method( $this->discount ) ); // XSS OK.
	}
	/* END ADDRESS REVIEW FUNCTIONS */

	/* START PAYMENT INFORMATION FUNCTIONS */
	public function display_payment( $ideal_source = '', $ideal_client_secret = '' ) {
		do_action( 'wp_easycart_display_payment_pre' );
		if ( wp_easycart_onepage_active() ) {
			if ( function_exists( 'wp_easycart_meta_initiate_checkout' ) && $this->cart->total_items > 0 ) {
				wp_easycart_meta_initiate_checkout( $this ); /* 6.0.2: a one-page checkout opened at this step still starts checkout */
			}
			$current_screen = 'payment';
			if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_checkout_v2.php' ) ) {
				include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_checkout_v2.php' );
			} else {
				include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_checkout_v2.php' );
			}
		} else {
			if ( $this->cart->total_items > 0 && apply_filters( 'wp_easycart_allow_payment', 1 ) ) {
				if ( isset( $_GET['PID'] ) && isset( $_GET['PYID'] ) && file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_paypal_express.php' ) ) {
					include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_paypal_express.php' );
				} else if ( isset( $_GET['PID'] ) && isset( $_GET['PYID'] ) ) {
						include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_paypal_express.php' );
				} else if ( get_option( 'ec_option_payment_third_party' ) == "paypal_advanced" ) {
					$this->payment->show_paypal_iframe( $this->order_totals->grand_total );
				} else if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_payment.php' ) ) {
					include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_payment.php' );
				} else {
					include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_payment.php' );
				}
			}
		}
		do_action( 'wp_easycart_display_payment_post' );
	}

	public function display_payment_paypal_express( $pid = false, $pyid = false, $oid = false ) {
		do_action( 'wp_easycart_display_payment_pre' );
		if (	$this->cart->total_items > 0 && apply_filters( 'wp_easycart_allow_payment', 1 ) ) {
			if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_paypal_express.php' ) ) {
				include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_paypal_express.php' );
			} else {
				include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_paypal_express.php' );
			}
		}
		do_action( 'wp_easycart_display_payment_post' );
	}

	public function display_payment_information() {
		if (	$this->cart->total_items > 0 && $this->order_totals->grand_total > 0 ) {
			if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_payment_information.php' ) )	
				include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_payment_information.php' );
			else
				include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_payment_information.php' );

			echo "<script>jQuery(\"input[name=ec_cart_payment_selection][value='" . esc_attr( get_option( 'ec_option_default_payment_type' ) ) . "']\").attr('checked', 'checked');";
			if ( get_option( 'ec_option_default_payment_type' ) == "manual_bill" ) {
				echo "jQuery('#ec_cart_pay_by_manual_payment').show();";
			} else if ( get_option( 'ec_option_default_payment_type' ) == "affirm" ) {
				echo "jQuery('#ec_cart_pay_by_affirm').show();";
			} else if ( get_option( 'ec_option_default_payment_type' ) == "third_party" ) {
				echo "jQuery('#ec_cart_pay_by_third_party').show();";
			} else if ( get_option( 'ec_option_default_payment_type' ) == "credit_card" ) {
				echo "jQuery('#ec_cart_pay_by_credit_card_holder').show();";
			}
			echo "</script>";
		}
	}

	/**
	 * Can this shopper choose Bill later? The store takes it ( ec_option_use_direct_deposit ) and, since 6.0.2, the shopper's
	 * role is one it is offered to ( ec_option_manual_payment_roles, Settings › Payments › Bill later: none chosen = everyone,
	 * 'guest' = not signed in; a pending account counts as a guest ). Asked by the payment step, the default choice, Place
	 * order and the manual completion call.
	 *
	 * @return bool
	 */
	public function use_manual_payment() {
		if ( ! get_option( 'ec_option_use_direct_deposit' ) ) {
			return false;
		}
		$level   = ( isset( $GLOBALS['ec_user'] ) && is_object( $GLOBALS['ec_user'] ) && ! empty( $GLOBALS['ec_user']->user_id ) ) ? (string) $GLOBALS['ec_user']->user_level : '';
		$role    = ( '' === $level || 'pending' === $level ) ? 'guest' : $level;
		$roles   = array_filter( array_map( 'trim', explode( ',', (string) get_option( 'ec_option_manual_payment_roles', '' ) ) ), 'strlen' );
		$allowed = ( ! $roles || in_array( $role, $roles, true ) );
		/**
		 * Filters whether the shopper can choose Bill later.
		 *
		 * @since 6.0.2
		 * @param bool   $allowed The shopper's role is one Bill later is offered to.
		 * @param string $role    The shopper's role ( 'guest' when not signed in or pending ).
		 * @param array  $roles   The roles chosen ( empty = everyone ).
		 */
		return (bool) apply_filters( 'wp_easycart_manual_payment_allowed', $allowed, $role, array_values( $roles ) );
	}

	public function display_manual_payment_text() {
		echo nl2br( esc_attr( wp_easycart_language()->convert_text( get_option( 'ec_option_direct_deposit_message' ) ) ) );
	}

	public function use_third_party() {
		if ( get_option( 'ec_option_payment_third_party' ) )
			return true;
		else
			return false;
	}

	public function ec_cart_display_third_party_form_start() {
		$this->payment->third_party->initialize( (int) $_GET['order_id'] );
		$this->payment->third_party->display_form_start();
	}

	public function ec_cart_display_third_party_form_end() {
		echo "</form>";
	}

	public function display_third_party_submit_button( $button_text ) {
		echo "<input type=\"submit\" class=\"ec_cart_submit_third_party\" value=\"" . esc_attr( $button_text ) . "\" />";
	}

	public function ec_cart_display_current_third_party_name() {
		if ( get_option( 'ec_option_payment_third_party' ) == "2checkout_thirdparty" )
			echo "2Checkout";
		else if ( get_option( 'ec_option_payment_third_party' ) == "cashfree" )
			echo "Cashfree";
		else if ( get_option( 'ec_option_payment_third_party' ) == "dwolla_thirdparty" )
			echo "Dwolla";
		else if ( get_option( 'ec_option_payment_third_party' ) == "nets" )
			echo "Nets Netaxept";
		else if ( get_option( 'ec_option_payment_third_party' ) == "payfast_thirdparty" )
			echo "Payfast";
		else if ( get_option( 'ec_option_payment_third_party' ) == "payfort" )
			echo "Payfort";
		else if ( get_option( 'ec_option_payment_third_party' ) == "paypal" )
			echo "PayPal";
		else if ( get_option( 'ec_option_payment_third_party' ) == "sagepay_paynow_za" )
			echo "SagePay Pay Now";
		else if ( get_option( 'ec_option_payment_third_party' ) == "skrill" )
			echo "Skrill";
		else if ( get_option( 'ec_option_payment_third_party' ) == "realex_thirdparty" )
			echo "Realex Payments";
		else if ( get_option( 'ec_option_payment_third_party' ) == "redsys" )
			echo "Redsys";
		else if ( get_option( 'ec_option_payment_third_party' ) == "paymentexpress_thirdparty" )
			echo "Payment Express";
		else
			echo esc_attr( get_option( 'ec_option_custom_third_party' ) );
	}

	public function ec_cart_get_current_third_party_name() {
		if ( get_option( 'ec_option_payment_third_party' ) == "dwolla_thirdparty" )
			return "Dwolla";
		else if ( get_option( 'ec_option_payment_third_party' ) == "nets" )
			return "Nets Netaxept";
		else if ( get_option( 'ec_option_payment_third_party' ) == "paypal" )
			return "PayPal";
		else if ( get_option( 'ec_option_payment_third_party' ) == "sagepay_paynow_za" )
			echo "SagePay Pay Now";
		else if ( get_option( 'ec_option_payment_third_party' ) == "skrill" )
			return "Skrill";
		else if ( get_option( 'ec_option_payment_third_party' ) == "realex_thirdparty" )
			return "Realex Payments";
		else if ( get_option( 'ec_option_payment_third_party' ) == "redsys" )
			return "Redsys";
		else if ( get_option( 'ec_option_payment_third_party' ) == "paymentexpress_thirdparty" )
			return "Payment Express";
		else
			return get_option( 'ec_option_custom_third_party' );
	}

	public function ec_cart_display_third_party_logo() {
		if ( get_option( 'ec_option_payment_third_party' ) == "paypal" ) {
			if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . "/design/theme/" . get_option( 'ec_option_base_theme' ) . "/ec_cart_payment_information/paypal.jpg" ) )	
				echo "<img src=\"" . esc_attr( plugins_url( "wp-easycart-data/design/theme/" . get_option( 'ec_option_base_theme' ) . "/ec_cart_payment_information/paypal.jpg", EC_PLUGIN_DATA_DIRECTORY ) ) . "\" alt=\"PayPal\" />";
			else
				echo "<img src=\"" . esc_attr( plugins_url( "wp-easycart/design/theme/" . get_option( 'ec_option_base_theme' ) . "/ec_cart_payment_information/paypal.jpg", EC_PLUGIN_DIRECTORY ) ) . "\" alt=\"PayPal\" />";
		} else if ( get_option( 'ec_option_payment_third_party' ) == "skrill" ) {
			if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . "/design/layout/theme/" . get_option( 'ec_option_base_theme' ) . "/ec_cart_payment_information/skrill-logo.gif" ) )	
				echo "<img src=\"" . esc_attr( plugins_url( "wp-easycart-data/design/theme/" . get_option( 'ec_option_base_theme' ) . "/ec_cart_payment_information/skrill-logo.gif", EC_PLUGIN_DATA_DIRECTORY ) ) . "\" alt=\"Skrill\" />";
			else
				echo "<img src=\"" . esc_attr( plugins_url( "wp-easycart/design/theme/" . get_option( 'ec_option_base_theme' ) . "/ec_cart_payment_information/skrill-logo.gif", EC_PLUGIN_DIRECTORY ) ) . "\" alt=\"Skrill\" />";
		}
	}

	public function use_payment_gateway() {
		if ( get_option( 'ec_option_payment_process_method' ) )
			return true;
		else
			return false;
	}

	public function ec_cart_display_credit_card_images() {
		/* Fall Back only */
	}

	public function ec_cart_display_card_holder_name_input() {
		echo "<input type=\"text\" name=\"ec_card_holder_name\" id=\"ec_card_holder_name\" class=\"ec_cart_payment_information_input_text\" value=\"\" />";
	}

	public function ec_cart_display_card_holder_name_hidden_input() {
		echo "<input type=\"hidden\" name=\"ec_card_holder_name\" id=\"ec_card_holder_name\" class=\"ec_cart_payment_information_input_text\" value=\"" . esc_attr( htmlspecialchars( $GLOBALS['ec_user']->billing->first_name, ENT_QUOTES ) . " " . htmlspecialchars( $GLOBALS['ec_user']->billing->last_name, ENT_QUOTES ) ) . "\" />";
	}

	public function ec_cart_display_card_number_input() {
		if ( get_option( 'ec_option_payment_process_method' ) == "eway" && get_option( 'ec_option_eway_use_rapid_pay' ) ) {
			echo "<input type=\"text\" name=\"ec_card_number\" data-eway-encrypt-name=\"ec_card_number\" id=\"ec_card_number\" class=\"ec_cart_payment_information_input_text\" value=\"\" autocomplete=\"off\" />";
		} else {
			echo "<input type=\"text\" name=\"ec_card_number\" id=\"ec_card_number\" class=\"ec_cart_payment_information_input_text\" value=\"\" autocomplete=\"off\" />";
		}
	}

	public function ec_cart_display_card_expiration_month_input( $select_text ) {
		echo "<select name=\"ec_expiration_month\" id=\"ec_expiration_month\" class=\"ec_cart_payment_information_input_select no_wrap\" autocomplete=\"off\">";
		echo "<option value=\"0\">" . esc_attr( $select_text ) . "</option>";
		for( $i=1; $i<=12; $i++ ) {
			echo "<option value=\"";
			if ( $i<10 )										$month = "0" . $i;
			else											$month = $i;
			echo esc_attr( $month ) . "\">" . esc_attr( $month ) . "</option>";
		}
		echo "</select>";
	}

	public function ec_cart_display_card_expiration_year_input( $select_text ) {
		echo "<select name=\"ec_expiration_year\" id=\"ec_expiration_year\" class=\"ec_cart_payment_information_input_select no_wrap\" autocomplete=\"off\">";
		echo "<option value=\"0\">" . esc_attr( $select_text ) . "</option>";
		for( $i=date( 'Y' ); $i < date( 'Y' ) + 15; $i++ ) {
			echo "<option value=\"" . esc_attr( $i ) . "\">" . esc_attr( $i ) . "</option>";	
		}
		echo "</select>";
	}

	public function ec_cart_display_card_security_code_input() {
		if ( get_option( 'ec_option_payment_process_method' ) == "eway" && get_option( 'ec_option_eway_use_rapid_pay' ) ) {
			echo "<input type=\"text\" name=\"ec_security_code\" data-eway-encrypt-name=\"ec_security_code\" id=\"ec_security_code\" class=\"ec_cart_payment_information_input_text\" value=\"\" autocomplete=\"off\" />";
		} else {
			echo "<input type=\"text\" name=\"ec_security_code\" id=\"ec_security_code\" class=\"ec_cart_payment_information_input_text\" value=\"\" autocomplete=\"off\" />";
		}
	}
	/* END PAYMENT INFORMATION FUNCTIONS */

	/* START CONTACT INFORMATION FUNCTIONS */
	public function display_contact_information() {
		if (	$this->cart->total_items > 0 ) {
			if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_contact_information.php' ) )	
				include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_contact_information.php' );
			else
				include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_contact_information.php' );
		}
	}

	public function ec_cart_display_contact_first_name_input() {
		$auto_validate_css = ( wp_easycart_onepage_active() ) ? ' ec_cart_auto_validate_v2' : '';
		if ( $GLOBALS['ec_cart_data']->cart_data->first_name != "" )
			$first_name = $GLOBALS['ec_cart_data']->cart_data->first_name;
		else
			$first_name = $GLOBALS['ec_user']->first_name;

		if ( $first_name == "guest" )
			$first_name = "";

		echo "<input type=\"text\" name=\"ec_contact_first_name\" id=\"ec_contact_first_name\" class=\"ec_cart_contact_information_input_text" . esc_attr( $auto_validate_css ) . "\" value=\"" . esc_attr( htmlspecialchars( $first_name, ENT_QUOTES ) ) . "\" />";
	}

	public function ec_cart_display_contact_last_name_input() {
		$auto_validate_css = ( wp_easycart_onepage_active() ) ? ' ec_cart_auto_validate_v2' : '';
		if ( $GLOBALS['ec_cart_data']->cart_data->last_name != "" )
			$last_name = $GLOBALS['ec_cart_data']->cart_data->last_name;
		else
			$last_name = $GLOBALS['ec_user']->last_name;

		if ( $last_name == "guest" )
			$last_name = "";

		echo "<input type=\"text\" name=\"ec_contact_last_name\" id=\"ec_contact_last_name\" class=\"ec_cart_contact_information_input_text" . esc_attr( $auto_validate_css ) . "\" value=\"" . esc_attr( htmlspecialchars( $last_name, ENT_QUOTES ) ) . "\" />";
	}

	public function ec_cart_display_contact_email_input() {
		$auto_validate_css = ( wp_easycart_onepage_active() ) ? ' ec_cart_auto_validate_v2' : '';
		if ( $GLOBALS['ec_cart_data']->cart_data->email != "" )
			$email = $GLOBALS['ec_cart_data']->cart_data->email;
		else
			$email = $GLOBALS['ec_user']->email;

		if ( $email == "guest" )
			$email = "";

		echo "<input type=\"text\" name=\"ec_contact_email\" id=\"ec_contact_email\" class=\"ec_cart_contact_information_input_text" . esc_attr( $auto_validate_css ) . "\" value=\"" . esc_attr( htmlspecialchars( $email, ENT_QUOTES ) ) . "\" />";
	}

	public function ec_cart_display_contact_email_retype_input() {
		if ( $GLOBALS['ec_cart_data']->cart_data->email != "" )
			$email = $GLOBALS['ec_cart_data']->cart_data->email;
		else
			$email = $GLOBALS['ec_user']->email;

		if ( $email == "guest" )
			$email = "";

		echo "<input type=\"text\" name=\"ec_contact_email_retype\" id=\"ec_contact_email_retype\" class=\"ec_cart_contact_information_input_text\" value=\"" . esc_attr( htmlspecialchars( $email, ENT_QUOTES ) ) . "\" />";
	}

	public function ec_cart_display_contact_email_other_input() {
		if ( '' != $GLOBALS['ec_cart_data']->cart_data->email_other ) {
			$email_other = $GLOBALS['ec_cart_data']->cart_data->email_other;
		} else if( '' != $GLOBALS['ec_user']->email_other ) {
			$email_other = $GLOBALS['ec_user']->email_other;
		} else {
			$email_other = '';
		}

		echo '<input type="text" name="ec_email_other" id="ec_email_other" class="ec_cart_contact_information_input_text" value="' . esc_attr( htmlspecialchars( $email_other, ENT_QUOTES ) ) . '"';
		if ( wp_easycart_onepage_active() ) {
			echo ' onchange="wp_easycart_save_email_other_v2();"';
		}
		echo ' />';
	}

	public function ec_cart_display_contact_create_account_box() {
		echo "<input type=\"checkbox\" name=\"ec_contact_create_account\" id=\"ec_contact_create_account\" onchange=\"ec_contact_create_account_change();\"";
		if ( $GLOBALS['ec_cart_data']->cart_data->create_account != "" )
			echo " checked=\checked\"";
		echo " />";

		if ( !get_option( 'ec_option_allow_guest' ) ) {
			echo "<script>jQuery('#ec_contact_create_account').hide(); jQuery('#ec_contact_create_account').attr('checked', true);</script>";
		}
	}

	public function ec_cart_display_contact_password_input() {
		$auto_validate_css = ( wp_easycart_onepage_active() ) ? ' ec_cart_auto_validate_v2' : '';
		echo "<input type=\"password\" name=\"ec_contact_password\" id=\"ec_contact_password\" class=\"ec_cart_contact_information_input_text" . esc_attr( $auto_validate_css ) . "\" />";
	}

	public function ec_cart_display_contact_password_retype_input() {
		$auto_validate_css = ( wp_easycart_onepage_active() ) ? ' ec_cart_auto_validate_v2' : '';
		echo "<input type=\"password\" name=\"ec_contact_password_retype\" id=\"ec_contact_password_retype\" class=\"ec_cart_contact_information_input_text" . esc_attr( $auto_validate_css ) . "\" />";
	}

	public function ec_cart_display_contact_is_subscriber_input() {
		echo "<input type=\"checkbox\" name=\"ec_contact_is_subscriber\" id=\"ec_contact_is_subscriber\" />";
	}
	/* END CONTACT INFORMATION FUNCTIONS */

	/* START SUBMIT ORDER DISPLAY FUNCTIONS */
	public function display_submit_order() {
		if (	$this->cart->total_items > 0 ) {
			if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_submit_order.php' ) )	
				include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_submit_order.php' );
			else
				include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_submit_order.php' );
		}
	}

	public function display_customer_order_notes() {
		if ( get_option( 'ec_option_user_order_notes' ) ) {
			echo "<div class=\"ec_cart_payment_information_title\">" . wp_easycart_language()->get_text( 'cart_payment_information', 'cart_payment_information_order_notes_title' ) . "</div>";
			echo "<div class=\"ec_cart_submit_order_message\">" . wp_easycart_language()->get_text( 'cart_payment_information', 'cart_payment_information_order_notes_message' ) . "</div>";	
			echo "<div class=\"ec_cart_payment_information_row\"><textarea name=\"ec_order_notes\" id=\"ec_order_notes\">";
			if ( $GLOBALS['ec_cart_data']->cart_data->order_notes != "" )
				echo esc_textarea( $GLOBALS['ec_cart_data']->cart_data->order_notes );

			echo "</textarea></div><hr />";
		}
	}

	public function display_order_finalize_panel() {
		if (	$this->cart->total_items > 0 ) {
			if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_finalize_order.php' ) )	
				include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_finalize_order.php' );
			else
				include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_finalize_order.php' );
		}
	}

	public function display_ajax_loader( $img ) {
		/* Fall back only */
	}
	/* END SUBMIT ORDER DISPLAY FUNCTIONS */

	/* START SUCCESS PAGE FUNCTIONS */
	public function display_print_receipt_link( $link_text, $order_id ) {
		/* 6.0.2: Settings › Documents › Customer downloads can hide it, and a document rule for some orders ( here, so theme
		   copies of the success page follow ). */
		if ( ! wp_easycart_documents::customer_download( 'success_print_receipt', (int) $order_id ) ) {
			return;
		}
		if ( $GLOBALS['ec_cart_data']->cart_data->is_guest == "" ) {
			echo "<a href=\"" . esc_url( wpeasycart_links()->get_account_page( 'print_receipt', array( 'order_id' => (int) $order_id ) ) ) . "\" target=\"_blank\">" . wp_easycart_escape_html( $link_text ) . "</a>";
		} else {
			echo "<a href=\"" . esc_url( wpeasycart_links()->get_account_page( 'print_receipt', array( 'order_id' => (int) $order_id, 'guest_key' => esc_attr( $GLOBALS['ec_cart_data']->cart_data->guest_key ) ) ) ) . "\" target=\"_blank\">" . wp_easycart_escape_html( $link_text ) . "</a>";
		}
	}

	public function get_printer_icon( $image_name ) {
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/theme/' . get_option( 'ec_option_base_theme' ) . '/images/' . $image_name ) ) {
			return plugins_url( 'wp-easycart-data/design/theme/' . get_option( 'ec_option_base_theme' ) . '/images/' . $image_name, EC_PLUGIN_DATA_DIRECTORY );
		} else {
			return plugins_url( 'wp-easycart/design/theme/' . get_option( 'ec_option_latest_theme' ) . '/images/' . $image_name, EC_PLUGIN_DIRECTORY );
		}
	}

	/*
	 * 6.0.2: the success page "create an account" form is gone ( no bundled layout printed it, and its handler was
	 * removed ). These print nothing and stay only so a store's own copy of an old ec_cart_success.php still loads; the
	 * arguments such a template passes are ignored.
	 */

	/**
	 * Removed success page account form: opening tag.
	 *
	 * @deprecated 6.0.2
	 */
	public function display_success_account_create_form_start() {
		_deprecated_function( __METHOD__, '6.0.2' );
	}

	/**
	 * Removed success page account form: password field.
	 *
	 * @deprecated 6.0.2
	 */
	public function display_success_create_password() {
		_deprecated_function( __METHOD__, '6.0.2' );
	}

	/**
	 * Removed success page account form: verify password field.
	 *
	 * @deprecated 6.0.2
	 */
	public function display_success_verify_password() {
		_deprecated_function( __METHOD__, '6.0.2' );
	}

	/**
	 * Removed success page account form: submit button.
	 *
	 * @deprecated 6.0.2
	 */
	public function display_success_account_create_submit_button() {
		_deprecated_function( __METHOD__, '6.0.2' );
	}

	/**
	 * Removed success page account form: closing tag.
	 *
	 * @deprecated 6.0.2
	 */
	public function display_success_account_create_form_end() {
		_deprecated_function( __METHOD__, '6.0.2' );
	}
	/* END SUCCESS PAGE FUNCTIONS */

	/* START FORM PROCESSING FUNCTIONS */
	// Process the cart page form action
	public function process_form_action( $action ) {
		wpeasycart_session()->handle_session();
		if ( $action == "add_to_cart" )								$this->process_add_to_cart();
		else if ( $action == "add_to_cart_v3" )						$this->process_add_to_cart_v3();
		else if ( $action == "ec_update_action" )					$this->process_update_cartitem( sanitize_text_field( $_POST['ec_update_cartitem_id'] ), (int) $_POST['ec_cartitem_quantity_' . (int) $_POST['ec_update_cartitem_id'] ] );
		else if ( $action == "ec_delete_action" )					$this->process_delete_cartitem( sanitize_text_field( $_POST['ec_delete_cartitem_id'] ) );
		else if ( $action == "submit_order" )						$this->process_submit_order();
		else if ( $action == "3dsecure" )							$this->process_3dsecure_response();
		else if ( $action == "3ds" )									$this->process_3ds_response();
		else if ( $action == "3dsprocess" )							$this->process_3ds_final();
		else if ( $action == "third_party_forward" )					$this->process_third_party_forward();
		else if ( $action == "login_user" )							$this->process_login_user();
		else if ( $action == "save_checkout_info" )					$this->process_save_checkout_info();
		else if ( $action == "save_checkout_shipping" )				$this->process_save_checkout_shipping();
		else if ( $action == "logout" )								$this->process_logout_user();
		else if ( $action == "realex_redirect" )						$this->process_realex_redirect();
		else if ( $action == "realex_response" )						$this->process_realex_response();
		else if ( $action == "paymentexpress_thirdparty_response" )	$this->process_paymentexpress_thirdparty_response();
		else if ( $action == "purchase_subscription" )				$this->process_purchase_subscription();
		else if ( $action == "insert_subscription" )					$this->process_insert_subscription();
		else if ( $action == "send_inquiry" )						$this->process_send_inquiry();
		else if ( $action == "deconetwork_add_to_cart" )				$this->process_deconetwork_add_to_cart();
		else if ( $action == "subscribe_v3" )						$this->process_subscribe_v3();
		else if ( $action == "process_update_subscription_quantity" )$this->process_update_subscription_quantity();
		else if ( $action == "stripe_redirect_action" )				$this->process_stripe_redirect_action();
	}

	// Process the add to cart form submission
	private function process_add_to_cart() {

		/* 6.0.2: the older add to cart form ( ec_product::display_product_details_form_end() ) carries the add to cart nonce the
		   current form uses. A theme copy that writes this form by hand without it must be updated. */
		$product_id = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
		if ( ! isset( $_POST['ec_cart_form_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ec_cart_form_nonce'] ) ), 'wp-easycart-add-to-cart-' . $product_id ) ) {
			header( "location: " . $this->cart_page . $this->permalink_divider . 'cart_error=invalid_nonce' );
			die();
		}

		if ( !$this->check_quantity( (int) $_POST['product_id'], (int) $_POST['product_quantity'] ) ) {
			header( "location: " . $this->store_page . $this->permalink_divider . "model_number=" . sanitize_text_field( $_POST['model_number'] ) . "&ec_store_error=minquantity" );

		} else {

			//add_to_cart_replace Hook
			if ( isset( $GLOBALS['ec_hooks']['add_to_cart_replace'] ) ) {
				$class_args = array( "cart_page" => $this->cart_page, "permalink_divider" => $this->permalink_divider );
				for( $i=0; $i<count( $GLOBALS['ec_hooks']['add_to_cart_replace'] ); $i++ ) {
					ec_call_hook( $GLOBALS['ec_hooks']['add_to_cart_replace'][$i], $class_args );
				}
			} else {
				//Product Info
				$session_id = $GLOBALS['ec_cart_data']->ec_cart_id;
				$product_id = (int) $_POST['product_id'];
				if ( isset( $_POST['product_quantity'] ) )
					$quantity = (int) $_POST['product_quantity'];
				else
					$quantity = 1;

				$model_number = stripslashes( sanitize_text_field( $_POST['model_number'] ) );

				//Optional Gift Card Info
				$gift_card_message = "";
				if ( isset( $_POST['ec_gift_card_message'] ) )
					$gift_card_message = stripslashes( sanitize_textarea_field( $_POST['ec_gift_card_message'] ) );

				$gift_card_to_name = "";
				if ( isset( $_POST['ec_gift_card_to_name'] ) )
					$gift_card_to_name = stripslashes( sanitize_text_field( $_POST['ec_gift_card_to_name'] ) );

				$gift_card_from_name = "";
				if ( isset( $_POST['ec_gift_card_from_name'] ) )
					$gift_card_from_name = stripslashes( sanitize_text_field( $_POST['ec_gift_card_from_name'] ) );

				// Optional Donation Price
				$donation_price = 0.000;
				if ( isset( $_POST['ec_product_input_price'] ) )
					$donation_price = sanitize_text_field( $_POST['ec_product_input_price'] );

				/* 6.0.2: the add to cart rules every path follows, and a donation of at least the product's price
				   ( ec_db::add_to_cart() asks the same rules again ). */
				$refusal = $this->add_to_cart_refusal( $product_id, $donation_price );
				if ( 'donation' === $refusal ) {
					header( 'location: ' . esc_url_raw( $this->store_page . $this->permalink_divider . 'model_number=' . rawurlencode( $model_number ) . '&ec_store_error=donation' ) );
					return;
				} else if ( '' !== $refusal ) {
					header( 'location: ' . esc_url_raw( wp_easycart_storefront_access::product_url( $product_id ) ) );
					return;
				}

				$use_advanced_optionset = false;
				//Product Options
				if ( isset( $_POST['ec_use_advanced_optionset'] ) && (bool) $_POST['ec_use_advanced_optionset'] ) {
					$option1 = "";
					$option2 = "";
					$option3 = "";
					$option4 = "";
					$option5 = "";
					$use_advanced_optionset = true;
				} else {
					$option1 = "";
					if ( isset( $_POST['ec_option1'] ) )
						$option1 = (int) $_POST['ec_option1'];

					$option2 = "";
					if ( isset( $_POST['ec_option2'] ) )
						$option2 = (int) $_POST['ec_option2'];

					$option3 = "";
					if ( isset( $_POST['ec_option3'] ) )
						$option3 = (int) $_POST['ec_option3'];

					$option4 = "";
					if ( isset( $_POST['ec_option4'] ) )
						$option4 = (int) $_POST['ec_option4'];

					$option5 = "";
					if ( isset( $_POST['ec_option5'] ) )
						$option5 = (int) $_POST['ec_option5'];

				}

				// Build the advanced option values BEFORE adding so identical
				// requests can update quantity on the existing cart row instead
				// of inserting a duplicate row.
				$option_vals = array();
				$file_upload_fields = array();
				$grid_quantity = 0;
				if ( $use_advanced_optionset ) {
					$optionsets = $GLOBALS['ec_advanced_optionsets']->get_advanced_optionsets( $product_id );

					foreach( $optionsets as $optionset ) {
						if ( $optionset->option_type == "checkbox" ) {
							$optionitems = $this->mysqli->get_advanced_optionitems( $optionset->option_id );
							foreach( $optionitems as $optionitem ) {
								if ( isset( $_POST['ec_option_' . $optionset->option_id . "_" . $optionitem->optionitem_id] ) ) {
									$option_vals[] = array( 
										"option_id" => $optionset->option_id, 
										"optionitem_id" => $optionitem->optionitem_id, 
										"option_name" => $optionitem->option_name, 
										"optionitem_name" => $optionitem->optionitem_name, 
										"option_type" => $optionitem->option_type, 
										"optionitem_value" => stripslashes( sanitize_text_field( $_POST['ec_option_' . $optionset->option_id . "_" . $optionitem->optionitem_id] ) ), 
										"optionitem_model_number" => $optionitem->optionitem_model_number
									);
								}
							}
						} else if ( $optionset->option_type == "grid" ) {
							$optionitems = $this->mysqli->get_advanced_optionitems( $optionset->option_id );
							foreach( $optionitems as $optionitem ) {
								if ( isset( $_POST['ec_option_' . $optionset->option_id . "_" . $optionitem->optionitem_id] ) && $_POST['ec_option_' . $optionset->option_id . "_" . $optionitem->optionitem_id] > 0 ) {
									$grid_quantity = $grid_quantity + (int) $_POST['ec_option_' . $optionset->option_id . "_" . $optionitem->optionitem_id];
									$option_vals[] = array( 
										"option_id" => $optionset->option_id, 
										"optionitem_id" => $optionitem->optionitem_id, 
										"option_name" => $optionitem->option_name, 
										"optionitem_name" => $optionitem->optionitem_name, 
										"option_type" => $optionitem->option_type, 
										"optionitem_value" => sanitize_text_field( $_POST['ec_option_' . $optionset->option_id . "_" . $optionitem->optionitem_id] ), 
										"optionitem_model_number" => $optionitem->optionitem_model_number 
									);
								}
							}
						} else if ( $optionset->option_type == "combo" || $optionset->option_type == "swatch" || $optionset->option_type == "radio" ) {
							$optionitems = $this->mysqli->get_advanced_optionitems( $optionset->option_id );
							foreach( $optionitems as $optionitem ) {
								if ( $optionitem->optionitem_id == $_POST['ec_option_' . $optionset->option_id] ) {
									$option_vals[] = array( 
										"option_id" => $optionset->option_id, 
										"optionitem_id" => $optionitem->optionitem_id, 
										"option_name" => $optionitem->option_name, 
										"optionitem_name" => $optionitem->optionitem_name, 
										"option_type" => $optionitem->option_type, 
										"optionitem_value" => $optionitem->optionitem_name, 
										"optionitem_model_number" => $optionitem->optionitem_model_number 
									);
								}
							}
						} else if ( $optionset->option_type == "file" ) {
							$optionitems = $this->mysqli->get_advanced_optionitems( $optionset->option_id );
							foreach( $optionitems as $optionitem ) {
								$option_vals[] = array( 
									"option_id" => $optionset->option_id, 
									"optionitem_id" => $optionitem->optionitem_id, 
									"option_name" => $optionitem->option_name, 
									"optionitem_name" => $optionitem->optionitem_name, 
									"option_type" => $optionitem->option_type, 
									"optionitem_value" => stripslashes( sanitize_text_field( $_FILES['ec_option_' . $optionset->option_id]['name'] ) ), 
									"optionitem_model_number" => $optionitem->optionitem_model_number 
								);
							}
						} else {
							$optionitems = $this->mysqli->get_advanced_optionitems( $optionset->option_id );
							$legacy_value = isset( $_POST[ 'ec_option_' . (int) $optionset->option_id ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'ec_option_' . (int) $optionset->option_id ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- legacy add_to_cart form has no nonce; value is sanitised here.
							if ( class_exists( 'wp_easycart_text_input_rules' ) ) {
								$legacy_value = wp_easycart_text_input_rules::validate( $legacy_value, $optionset )['value']; /* 6.0.0 input rules */
							}
							foreach( $optionitems as $optionitem ) {
								$option_vals[] = array(
									"option_id" => $optionset->option_id,
									"optionitem_id" => $optionitem->optionitem_id,
									"option_name" => $optionitem->option_name,
									"optionitem_name" => $optionitem->optionitem_name,
									"option_type" => $optionitem->option_type,
									"optionitem_value" => $legacy_value,
									"optionitem_model_number" => $optionitem->optionitem_model_number 
								);
							}
						}

						if ( $optionset->option_type == "file" ) {
							// Upload after the cart row exists ( see below ).
							$file_upload_fields[ 'ec_option_' . (int) $optionset->option_id ] = $optionset; /* 6.0.0: the option set carries its allowed file types. */
						}
					}
				}

				/* 6.0.0: a chosen file the store cannot accept stops the add, before the cart row exists. */
				foreach ( $file_upload_fields as $file_upload_field => $file_upload_optionset ) {
					$upload_problem = $this->upload_customer_file( $session_id, $file_upload_field, true, $file_upload_optionset );
					if ( '' !== $upload_problem && 'none' !== $upload_problem ) {
						/* reject_option_input() and ec-text-input-rules.js identify the option by its option_to_product_id, not the option_id in the field name. */
						$failed_option_id = (int) $file_upload_optionset->option_to_product_id;
						$this->option_input_errors[] = $failed_option_id;
						$this->option_input_error_reasons[ $failed_option_id ] = 'file_' . $upload_problem;
						$this->reject_option_input( false );
						return;
					}
				}

				/* 6.0.2: a variant that is switched off cannot be added. */
				if ( ! $use_advanced_optionset && class_exists( 'wp_easycart_variants' ) && wp_easycart_variants::is_disabled( $product_id, array( (int) $option1, (int) $option2, (int) $option3, (int) $option4, (int) $option5 ) ) ) {
					header( 'location: ' . esc_url_raw( wpeasycart_links()->get_cart_page( '', array( 'ec_cart_error' => 'variant_unavailable' ) ) ) );
					return;
				}

				$was_merged = false;
				$before_add = function_exists( 'wp_easycart_cart_add_snapshot' ) ? wp_easycart_cart_add_snapshot( $product_id, $session_id ) : 0;
				$tempcart_id = $this->mysqli->add_to_cart( $product_id, $session_id, $quantity, $option1, $option2, $option3, $option4, $option5, $gift_card_message, $gift_card_to_name, $gift_card_from_name, $donation_price, $use_advanced_optionset, false, "", $option_vals, $was_merged );

				// Attach advanced option rows and files to newly created rows only.
				if ( $tempcart_id && ! $was_merged ) {
					foreach ( $file_upload_fields as $file_upload_field => $file_upload_optionset ) {
						/* 6.0.0: the session folder ( not the sequential cart row id ), which is where the order record looks ( ec_db::insert_order_option ). */
						$this->upload_customer_file( $session_id, $file_upload_field, false, $file_upload_optionset );
					}
					for( $i=0; $i<count( $option_vals ); $i++ ) {
						$this->mysqli->add_option_to_cart( $tempcart_id, $GLOBALS['ec_cart_data']->ec_cart_id, $option_vals[$i] );
					}
					if ( $grid_quantity > 0 ) {
						$this->mysqli->update_tempcart_grid_quantity( $tempcart_id, $grid_quantity );
					}
				}
				if ( $tempcart_id && function_exists( 'wp_easycart_announce_cart_item_added' ) ) {
					wp_easycart_announce_cart_item_added( $tempcart_id, $product_id, $before_add, 'details', $session_id ); /* 6.0.2 */
				}

				if ( get_option( 'ec_option_addtocart_return_to_product' ) ) {
					$return_url = esc_url_raw( $_SERVER['HTTP_REFERER'] );
					$return_url = str_replace( "ec_store_success=addtocart", "", $return_url );
					$divider = "?";
					if ( substr_count( $return_url, '?' ) )
						$divider = "&";

					do_action( 'wpeasycart_cart_updated' );


					header( "location: " . $return_url . $divider . "ec_store_success=addtocart&model=" . sanitize_text_field( $_POST['model_number'] ) );
				} else {
					header( "location: " . $this->cart_page );
				}
			}
		}
	}

	/**
	 * An add to cart post for an inquiry-mode product ( a template copy without the inquiry form's own action ): sent as an
	 * inquiry, with every check the inquiry form gets.
	 *
	 * 6.0.2: this path skipped reCAPTCHA and sent a separate, older email; it now runs process_send_inquiry(). The add to cart
	 * nonce was verified by process_add_to_cart_v3().
	 *
	 * @param object $product Product row.
	 */
	private function send_inquiry( $product ) {
		$this->process_send_inquiry( $product );
	}

	/**
	 * Why an add to cart post stops before anything is read or uploaded ( 6.0.2 ): 'unavailable' when the add to cart rules
	 * refuse the product ( wp_easycart_product_can_add_to_cart() ), 'donation' when the donation amount is not a number of at
	 * least the product's price, else ''. ec_db::add_to_cart() asks the same again.
	 *
	 * @since 6.0.2
	 * @param int   $product_id   Product.
	 * @param mixed $donation_raw The posted donation amount.
	 * @return string
	 */
	private function add_to_cart_refusal( $product_id, $donation_raw ) {
		if ( ! class_exists( 'wp_easycart_storefront_access' ) ) {
			return '';
		}
		if ( true !== wp_easycart_product_can_add_to_cart( (int) $product_id ) ) {
			return 'unavailable';
		}
		$row = wp_easycart_storefront_access::product_row( (int) $product_id );
		if ( $row && ! empty( $row->is_donation ) && is_wp_error( wp_easycart_storefront_access::donation_price( $donation_raw, $row->price ) ) ) {
			return 'donation';
		}
		return '';
	}

	private function get_advanced_option_vals( $product_id, $tempcart_id ) {

		$option_vals = array();
		$optionsets = (array) $GLOBALS['ec_advanced_optionsets']->get_advanced_optionsets( $product_id );
		$grid_quantity = 0;

		foreach( $optionsets as $optionset ) {

			$optionitems = apply_filters( 'wp_easycart_advanced_option_items_add_to_cart', (array) $optionset->option_items, $optionset->option_id );
			if ( $optionset->option_type == "checkbox" ) {
				foreach( $optionitems as $optionitem ) {
					if ( isset( $_POST['ec_option_' . (int) $optionset->option_id . "_" . (int) $optionitem->optionitem_id] ) ) {
						$option_vals[] = array( 
							"option_id" => (int) $optionset->option_id, 
							"option_label" => wp_easycart_escape_html( $optionset->option_label ), 
							"option_name" => sanitize_text_field( $optionset->option_name ), 
							"optionitem_name" => wp_easycart_escape_html( $optionitem->optionitem_name ),
							"option_type" => sanitize_text_field( $optionset->option_type ), 
							"optionitem_id" => (int) $optionitem->optionitem_id, 
							"optionitem_value" => stripslashes( sanitize_text_field( $_POST['ec_option_' . (int) $optionset->option_id . "_" . (int) $optionitem->optionitem_id] ) ), 
							"optionitem_model_number" => sanitize_text_field( $optionitem->optionitem_model_number )
						);

					} else if ( isset( $_POST['ec_option_adv_' . (int) $optionset->option_to_product_id . "_" . (int) $optionitem->optionitem_id] ) ) {
						$option_vals[] = array( 
							"option_id" => (int) $optionset->option_id,
							"option_label" => wp_easycart_escape_html( $optionset->option_label ),
							"option_name" => sanitize_text_field( $optionset->option_name ),
							"optionitem_name" => wp_easycart_escape_html( $optionitem->optionitem_name ),
							"option_type" => sanitize_text_field( $optionset->option_type ),
							"optionitem_id" => (int) $optionitem->optionitem_id,
							"optionitem_value" => stripslashes( sanitize_text_field( $_POST['ec_option_adv_' . (int) $optionset->option_to_product_id . "_" . (int) $optionitem->optionitem_id] ) ), 
							"optionitem_model_number" => sanitize_text_field( $optionitem->optionitem_model_number )
						);
					}
				}

			} else if ( $optionset->option_type == "grid" ) {
				foreach( $optionitems as $optionitem ) {
					if ( isset( $_POST['ec_option_' . $optionset->option_id . "_" . $optionitem->optionitem_id] ) && (int) $_POST['ec_option_' . (int) $optionset->option_id . "_" . (int) $optionitem->optionitem_id] > 0 ) {
						$grid_quantity = $grid_quantity + (int) $_POST['ec_option_' . $optionset->option_id . "_" . $optionitem->optionitem_id];
						$option_vals[] = array( 
							"option_id" => (int) $optionset->option_id, 
							"option_label" => wp_easycart_escape_html( $optionset->option_label ), 
							"option_name" => sanitize_text_field( $optionset->option_name ),
							"optionitem_name" => wp_easycart_escape_html( $optionitem->optionitem_name ),
							"option_type" => sanitize_text_field( $optionset->option_type ),
							"optionitem_id" => (int) $optionitem->optionitem_id,
							"optionitem_value" => (int) $_POST['ec_option_' . (int) $optionset->option_id . "_" . (int) $optionitem->optionitem_id], 
							"optionitem_model_number" => sanitize_text_field( $optionitem->optionitem_model_number )
						);

					} else if ( isset( $_POST['ec_option_adv_' . $optionset->option_to_product_id . "_" . $optionitem->optionitem_id] ) && (int) $_POST['ec_option_adv_' . (int) $optionset->option_to_product_id . "_" . (int) $optionitem->optionitem_id] > 0 ) {
						$grid_quantity = $grid_quantity + (int) $_POST['ec_option_adv_' . $optionset->option_to_product_id . "_" . $optionitem->optionitem_id];
						$option_vals[] = array( 
							"option_id" => (int) $optionset->option_id,
							"option_label" => wp_easycart_escape_html( $optionset->option_label ),
							"option_name" => sanitize_text_field( $optionset->option_name ),
							"optionitem_name" => wp_easycart_escape_html( $optionitem->optionitem_name ),
							"option_type" => sanitize_text_field( $optionset->option_type ),
							"optionitem_id" => (int) $optionitem->optionitem_id,
							"optionitem_value" => (int) $_POST['ec_option_adv_' . (int) $optionset->option_to_product_id . "_" . (int) $optionitem->optionitem_id],
							"optionitem_model_number" => sanitize_text_field( $optionitem->optionitem_model_number )
						);
					}
				}

			} else if ( $optionset->option_type == "combo" || $optionset->option_type == "swatch" || $optionset->option_type == "radio" ) {
				foreach( $optionitems as $optionitem ) {
					if ( isset( $_POST['ec_option_' . (int) $optionset->option_id] ) && $optionitem->optionitem_id == (int) $_POST['ec_option_' . (int) $optionset->option_id] ) {
						$option_vals[] = array(
							"option_id" => (int) $optionset->option_id,
							"option_label" => wp_easycart_escape_html( $optionset->option_label ),
							"option_name" => sanitize_text_field( $optionset->option_name ),
							"optionitem_name" => wp_easycart_escape_html( $optionitem->optionitem_name ),
							"option_type" => sanitize_text_field( $optionset->option_type ),
							"optionitem_id" => (int) $optionitem->optionitem_id,
							"optionitem_value" => sanitize_text_field( $optionitem->optionitem_name ),
							"optionitem_model_number" => sanitize_text_field( $optionitem->optionitem_model_number )
						);

					} else if ( isset( $_POST['ec_option_adv_' . (int) $optionset->option_to_product_id] ) && $optionitem->optionitem_id == (int) $_POST['ec_option_adv_' . (int) $optionset->option_to_product_id] ) {
						$option_vals[] = array( 
							"option_id" => (int) $optionset->option_id,
							"option_label" => wp_easycart_escape_html( $optionset->option_label ),
							"option_name" => sanitize_text_field( $optionset->option_name ),
							"optionitem_name" => wp_easycart_escape_html( $optionitem->optionitem_name ),
							"option_type" => sanitize_text_field( $optionset->option_type ),
							"optionitem_id" => (int) $optionitem->optionitem_id,
							"optionitem_value" => sanitize_text_field( $optionitem->optionitem_name ),
							"optionitem_model_number" => sanitize_text_field( $optionitem->optionitem_model_number )
						);
					}
				}

			} else if ( $optionset->option_type == "file" ) {
				foreach( $optionitems as $optionitem ) {
					if ( isset( $_FILES['ec_option_' . (int) $optionset->option_id] ) ) {
						$option_vals[] = array(
							"option_id" => (int) $optionset->option_id,
							"option_label" => wp_easycart_escape_html( $optionset->option_label ),
							"option_name" => sanitize_text_field( $optionset->option_name ),
							"optionitem_name" => wp_easycart_escape_html( $optionitem->optionitem_name ),
							"option_type" => sanitize_text_field( $optionset->option_type ),
							"optionitem_id" => (int) $optionitem->optionitem_id,
							"optionitem_value" => sanitize_text_field( $_FILES['ec_option_' . (int) $optionset->option_id]['name'] ),
							"optionitem_model_number" => sanitize_text_field( $optionitem->optionitem_model_number )
						);

					} else if ( isset( $_FILES['ec_option_adv_' . (int) $optionset->option_to_product_id] ) ) {
						$option_vals[] = array(
							"option_id" => (int) $optionset->option_id,
							"option_label" => wp_easycart_escape_html( $optionset->option_label ),
							"option_name" => sanitize_text_field( $optionset->option_name ),
							"optionitem_name" => wp_easycart_escape_html( $optionitem->optionitem_name ),
							"option_type" => sanitize_text_field( $optionset->option_type ),
							"optionitem_id" => (int) $optionitem->optionitem_id,
							"optionitem_value" => sanitize_text_field( $_FILES['ec_option_adv_' . (int) $optionset->option_to_product_id]['name'] ), 
							"optionitem_model_number" => sanitize_text_field( $optionitem->optionitem_model_number )
						);
					}
				}

			} else if ( $optionset->option_type == "dimensions1" || $optionset->option_type == "dimensions2" ) {
				foreach( $optionitems as $optionitem ) {

					if ( isset( $_POST['ec_option_' . (int) $optionset->option_id . '_width'] ) ) {
						$vals = array();
						$vals[] = sanitize_text_field( $_POST['ec_option_' . (int) $optionset->option_id . '_width'] );

						if ( isset( $_POST['ec_option_' . (int) $optionset->option_id . '_sub_width'] ) ) {
							$vals[] = sanitize_text_field( $_POST['ec_option_' . (int) $optionset->option_id . '_sub_width'] );

						}

						$vals[] = sanitize_text_field( $_POST['ec_option_' . (int) $optionset->option_id . '_height'] );

						if ( isset( $_POST['ec_option_' . (int) $optionset->option_id . '_sub_height'] ) ) {
							$vals[] = sanitize_text_field( $_POST['ec_option_' . (int) $optionset->option_id . '_sub_height'] );

						}

						$option_vals[] = array(
							"option_id" => (int) $optionset->option_id,
							"option_label" => wp_easycart_escape_html( $optionset->option_label ),
							"option_name" => sanitize_text_field( $optionset->option_name ),
							"optionitem_name" => wp_easycart_escape_html( $optionitem->optionitem_name ),
							"option_type" => sanitize_text_field( $optionset->option_type ),
							"optionitem_id" => (int) $optionitem->optionitem_id,
							"optionitem_value" => json_encode( $vals ),
							"optionitem_model_number" => sanitize_text_field( $optionitem->optionitem_model_number )
						);

					} else if ( isset( $_POST['ec_option_adv_' . (int) $optionset->option_to_product_id . '_width'] ) ) {
						$vals = array();
						$vals[] = sanitize_text_field( $_POST['ec_option_adv_' . (int) $optionset->option_to_product_id . '_width'] );

						if ( isset( $_POST['ec_option_adv_' . (int) $optionset->option_to_product_id . '_sub_width'] ) ) {
							$vals[] = sanitize_text_field( $_POST['ec_option_adv_' . (int) $optionset->option_to_product_id . '_sub_width'] );

						}

						$vals[] = sanitize_text_field( $_POST['ec_option_adv_' . (int) $optionset->option_to_product_id . '_height'] );

						if ( isset( $_POST['ec_option_adv_' . (int) $optionset->option_to_product_id . '_sub_height'] ) ) {
							$vals[] = sanitize_text_field( $_POST['ec_option_adv_' . (int) $optionset->option_to_product_id . '_sub_height'] );

						}

						$option_vals[] = array( 
							"option_id" => (int) $optionset->option_id,
							"option_label" => wp_easycart_escape_html( $optionset->option_label ),
							"option_name" => sanitize_text_field( $optionset->option_name ),
							"optionitem_name" => wp_easycart_escape_html( $optionitem->optionitem_name ),
							"option_type" => sanitize_text_field( $optionset->option_type ),
							"optionitem_id" => (int) $optionitem->optionitem_id,
							"optionitem_value" => json_encode( $vals ),
							"optionitem_model_number" => sanitize_text_field( $optionitem->optionitem_model_number )
						);

					}
				}

			} else {
				// phpcs:disable WordPress.Security.NonceVerification.Missing -- every caller ( add_to_cart_v3, subscribe_v3, send_inquiry ) verifies its form nonce first.
				$posted_key = 'ec_option_' . (int) $optionset->option_id;
				if ( ! isset( $_POST[ $posted_key ] ) || '' === $_POST[ $posted_key ] ) {
					$posted_key = 'ec_option_adv_' . (int) $optionset->option_to_product_id;
				}
				$posted_value = ( isset( $_POST[ $posted_key ] ) && '' !== $_POST[ $posted_key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $posted_key ] ) ) : null;
				// phpcs:enable WordPress.Security.NonceVerification.Missing
				/* 6.0.0: text / textarea input rules ( case, allowed characters, max length ) so per-character pricing and the order see the normalised value. */
				if ( null !== $posted_value && class_exists( 'wp_easycart_text_input_rules' ) && wp_easycart_text_input_rules::supports( $optionset->option_type ) ) {
					$checked = wp_easycart_text_input_rules::validate( $posted_value, $optionset );
					if ( $checked['rejected'] ) {
						$this->option_input_errors[] = (int) $optionset->option_to_product_id;
						$this->option_input_error_reasons[ (int) $optionset->option_to_product_id ] = isset( $checked['reason'] ) ? $checked['reason'] : 'empty';
					}
					$posted_value = ( '' === $checked['value'] ) ? null : $checked['value'];
				}
				foreach( $optionitems as $optionitem ) {
					if ( null !== $posted_value ) {
						$option_vals[] = array(
							"option_id" => (int) $optionset->option_id,
							"option_label" => wp_easycart_escape_html( $optionset->option_label ),
							"option_name" => sanitize_text_field( $optionset->option_name ),
							"optionitem_name" => wp_easycart_escape_html( $optionitem->optionitem_name ),
							"option_type" => sanitize_text_field( $optionset->option_type ),
							"optionitem_id" => (int) $optionitem->optionitem_id,
							"optionitem_value" => $posted_value,
							"optionitem_model_number" => sanitize_text_field( $optionitem->optionitem_model_number )
						);
					}
				}
			}

			if ( $optionset->option_type == "file" ) {
				$upload_problem = 'none';
				if ( isset( $_FILES['ec_option_' . (int) $optionset->option_id] ) ) {
					$upload_problem = $this->upload_customer_file( $tempcart_id, 'ec_option_' . (int) $optionset->option_id, false, $optionset );

				} else if ( isset( $_FILES['ec_option_adv_' . (int) $optionset->option_to_product_id] ) ) {
					$upload_problem = $this->upload_customer_file( $tempcart_id, 'ec_option_adv_' . (int) $optionset->option_to_product_id, false, $optionset );
				}
				/* 6.0.0: the shopper chose a file the store could not keep ( type, size, upload or server problem ): send them back with the reason. */
				if ( '' !== $upload_problem && 'none' !== $upload_problem ) {
					$this->option_input_errors[] = (int) $optionset->option_to_product_id;
					$this->option_input_error_reasons[ (int) $optionset->option_to_product_id ] = 'file_' . ( 'storage' === $upload_problem ? 'upload' : $upload_problem );
				}
			}
		}
		$option_vals = apply_filters( 'wp_easycart_advanced_options_add_to_cart', $option_vals, $product_id, $tempcart_id );
		return $option_vals;
	}

	/**
	 * Nothing is added: a text / textarea option failed its input rules, or a chosen file
	 * could not be uploaded. Sends the shopper back to the product with
	 * ?ec_option_error=<option_to_product_id> ( and ec_option_error_reason ), which
	 * ec-text-input-rules.js uses to show that option's error text. AJAX adds ( noredirect ) just stop.
	 *
	 * @since 6.0.0
	 *
	 * @param object $product Product row.
	 */
	private function reject_option_input( $product ) {
		$option_ids = array_values( array_unique( array_map( 'intval', $this->option_input_errors ) ) );
		if ( isset( $_POST['noredirect'] ) && '1' === $_POST['noredirect'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- the calling form handler verified its nonce.
			return;
		}
		$fallback   = ( is_object( $product ) && ! empty( $product->post_id ) ) ? get_permalink( (int) $product->post_id ) : $this->store_page;
		$return_url = wp_get_referer();
		$return_url = $return_url ? wp_validate_redirect( $return_url, $fallback ) : $fallback;
		$return_url = remove_query_arg( array( 'ec_option_error', 'ec_option_error_reason', 'ec_store_success', 'model' ), $return_url );
		$args       = array( 'ec_option_error' => $option_ids[0] );
		if ( isset( $this->option_input_error_reasons[ $option_ids[0] ] ) && in_array( $this->option_input_error_reasons[ $option_ids[0] ], array( 'min_length', 'file_type', 'file_size', 'file_upload' ), true ) ) {
			$args['ec_option_error_reason'] = $this->option_input_error_reasons[ $option_ids[0] ];
		}
		wp_safe_redirect( add_query_arg( $args, $return_url ) );
		exit;
	}

	private function get_grid_quantity( $product_id, $tempcart_id ) {

		$optionsets = (array) $GLOBALS['ec_advanced_optionsets']->get_advanced_optionsets( $product_id );
		$grid_quantity = 0;
		foreach( $optionsets as $optionset ) {

			if ( sanitize_text_field( $optionset->option_type ) == "grid" ) {
				$optionitems = (array) $this->mysqli->get_advanced_optionitems( (int) $optionset->option_id );
				foreach( $optionitems as $optionitem ) {
					if ( isset( $_POST['ec_option_' . (int) $optionset->option_id . "_" . (int) $optionitem->optionitem_id] ) && (int) $_POST['ec_option_' . (int) $optionset->option_id . "_" . (int) $optionitem->optionitem_id] > 0 ) {
						$grid_quantity = $grid_quantity + (int) $_POST['ec_option_' . (int) $optionset->option_id . "_" . (int) $optionitem->optionitem_id];

					} else if ( isset( $_POST['ec_option_adv_' . (int) $optionset->option_to_product_id . "_" . (int) $optionitem->optionitem_id] ) && (int) $_POST['ec_option_adv_' . (int) $optionset->option_to_product_id . "_" . (int) $optionitem->optionitem_id] > 0 ) {
						$grid_quantity = $grid_quantity + (int) $_POST['ec_option_adv_' . (int) $optionset->option_to_product_id . "_" . (int) $optionitem->optionitem_id];
					}
				}
			}
		}
		return $grid_quantity;

	}

	private function process_add_to_cart_v3() {
		$product_id = (int) $_POST['product_id'];
		if ( ! wp_verify_nonce( sanitize_text_field( $_POST['ec_cart_form_nonce'] ), 'wp-easycart-add-to-cart-' . $product_id ) ) {
			header( "location: " . $this->cart_page . $this->permalink_divider . 'cart_error=invalid_nonce' );
			die();
		}

		wpeasycart_session()->handle_session();
		$cart_id = $GLOBALS['ec_cart_data']->ec_cart_id;
		$product = $this->mysqli->get_product( "", $product_id );
		$no_redirect = ( isset( $_POST['noredirect'] ) && '1' === $_POST['noredirect'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified at the top of this method.

		if ( ! $product ) {
			/* 6.0.2: no such product ( it was deleted while the page was open ). */
			if ( ! $no_redirect ) {
				header( 'location: ' . esc_url_raw( $this->store_page ) );
			}
			return;

		} else if ( $product->inquiry_mode ) {
			$this->send_inquiry( $product );

		} else if ( $product->is_subscription_item ) { // && !class_exists( "ec_stripe" ) ) {

		} else {

			if ( isset( $_POST['ec_quantity'] ) ) {
				$quantity = (int) $_POST['ec_quantity'];
			} else {
				$quantity = 1;
			}

			//Optional Gift Card Info
			$gift_card_message = ( isset( $_POST['ec_giftcard_message'] ) ) ? stripslashes( sanitize_textarea_field( $_POST['ec_giftcard_message'] ) ) : "";
			$gift_card_to_name = ( isset( $_POST['ec_giftcard_to_name'] ) ) ? stripslashes( sanitize_text_field( $_POST['ec_giftcard_to_name'] ) ) : "";
			$gift_card_from_name = ( isset( $_POST['ec_giftcard_from_name'] ) ) ? stripslashes( sanitize_text_field( $_POST['ec_giftcard_from_name'] ) ) : "";
			$gift_card_email = ( isset( $_POST['ec_giftcard_to_email'] ) ) ? stripslashes( sanitize_email( $_POST['ec_giftcard_to_email'] ) ) : "";
			$donation_price = ( isset( $_POST['ec_donation_amount'] ) ) ? sanitize_text_field( $_POST['ec_donation_amount'] ) : 0.000;

			/*
			 * 6.0.2: the add to cart rules every path follows ( catalog and inquiry mode, login for pricing, customer role, a store
			 * closed to this shopper ): a refused add opens the product page, which says why. A donation below the product's price
			 * goes back to the form with its error shown ( ec_donation_error ). AJAX adds ( noredirect ) just stop: the storefront
			 * then posts the form, which lands here without noredirect.
			 */
			$refusal = $this->add_to_cart_refusal( $product_id, $donation_price );
			if ( '' !== $refusal ) {
				if ( ! $no_redirect ) {
					if ( 'donation' === $refusal ) {
						$fallback   = wp_easycart_storefront_access::product_url( $product_id );
						$return_url = wp_get_referer();
						$return_url = $return_url ? wp_validate_redirect( $return_url, $fallback ) : $fallback;
						$return_url = remove_query_arg( array( 'ec_donation_error', 'ec_option_error', 'ec_option_error_reason', 'ec_store_success', 'model' ), $return_url );
						header( 'location: ' . esc_url_raw( add_query_arg( 'ec_donation_error', (int) $product_id, $return_url ) ) );
					} else {
						header( 'location: ' . esc_url_raw( wp_easycart_storefront_access::product_url( $product_id ) ) );
					}
				}
				return;
			}
			$use_advanced_optionset = (int) $product->use_advanced_optionset;
			$use_both_option_types = (int) $product->use_both_option_types;

			//Product Options
			$option1 = ( isset( $_POST['ec_option1'] ) ) ? (int) $_POST['ec_option1'] : 0;
			$option2 = ( isset( $_POST['ec_option2'] ) ) ? (int) $_POST['ec_option2'] : 0;
			$option3 = ( isset( $_POST['ec_option3'] ) ) ? (int) $_POST['ec_option3'] : 0;
			$option4 = ( isset( $_POST['ec_option4'] ) ) ? (int) $_POST['ec_option4'] : 0;
			$option5 = ( isset( $_POST['ec_option5'] ) ) ? (int) $_POST['ec_option5'] : 0;

			$option_vals = array();
			if ( $use_advanced_optionset || $use_both_option_types ) {
				$option_vals = $this->get_advanced_option_vals( $product_id, $cart_id );
				if ( ! empty( $this->option_input_errors ) ) {
					$this->reject_option_input( $product );
					return;
				}
			}

			/* 6.0.2: a variant that is switched off cannot be added. */
			if ( class_exists( 'wp_easycart_variants' ) && wp_easycart_variants::is_disabled( $product_id, array( $option1, $option2, $option3, $option4, $option5 ) ) ) {
				if ( ! isset( $_POST['noredirect'] ) || '1' !== $_POST['noredirect'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified at the top of this method.
					header( 'location: ' . esc_url_raw( wpeasycart_links()->get_cart_page( '', array( 'ec_cart_error' => 'variant_unavailable' ) ) ) );
				}
				return;
			}

			$was_merged = false;
			$before_add = function_exists( 'wp_easycart_cart_add_snapshot' ) ? wp_easycart_cart_add_snapshot( $product_id, $cart_id ) : 0;
			$tempcart_id = $this->mysqli->add_to_cart( $product_id, $cart_id, $quantity, $option1, $option2, $option3, $option4, $option5, $gift_card_message, $gift_card_to_name, $gift_card_from_name, $donation_price, count( $option_vals ), false, $gift_card_email, $option_vals, $was_merged );

			// Now insert the advanced option set tempcart table if needed. When
			// the request matched an existing cart row ( $was_merged ), the
			// options are already attached to that row.
			if ( ( $use_advanced_optionset || $use_both_option_types ) && ! $was_merged ) {
				$grid_quantity = $this->get_grid_quantity( $product_id, $tempcart_id );

				for( $i=0; $i<count( $option_vals ); $i++ ) {
					$this->mysqli->add_option_to_cart( $tempcart_id, $cart_id, $option_vals[$i] );
				}

				if ( $grid_quantity > 0 ) {
					$this->mysqli->update_tempcart_grid_quantity( $tempcart_id, $grid_quantity );
				}
			}

			if ( $tempcart_id && function_exists( 'wp_easycart_announce_cart_item_added' ) ) {
				wp_easycart_announce_cart_item_added( $tempcart_id, $product_id, $before_add, 'details', $cart_id ); /* 6.0.2 */
			}
			do_action( 'wpeasycart_item_added_to_cart', $tempcart_id, $cart_id );
			do_action( 'wpeasycart_cart_updated' );

			if( isset( $_POST['noredirect'] ) && $_POST['noredirect'] == '1' ) {
				return;
			}

			if ( get_option( 'ec_option_addtocart_return_to_product' ) ) {
				$return_url = sanitize_text_field( $_SERVER['HTTP_REFERER'] );
				$return_url = str_replace( "ec_store_success=addtocart", "", $return_url );
				$divider = "?";
				if ( substr_count( $return_url, '?' ) )
					$divider = "&";

				header( "location: " . apply_filters( 'wp_easycart_add_to_cart_return_url_product', $return_url . $divider . "ec_store_success=addtocart&model=" . $product->model_number, $tempcart_id, $product_id ) );

			} else {
				header( "location: " . apply_filters( 'wp_easycart_add_to_cart_return_url_cart', $this->cart_page, $tempcart_id, $product_id ) );

			}

		}

	}

	private function check_quantity( $product_id, $quantity ) {

		global $wpdb;
		$min_quantity = $wpdb->get_var( $wpdb->prepare( "SELECT ec_product.min_purchase_quantity FROM ec_product WHERE ec_product.product_id = %d", $product_id ) );

		if ( $min_quantity > 0 ) {
			$current_amount = $quantity;
			foreach( $this->cart->cart as $cartitem ) {
				if ( $cartitem->product_id == $product_id ) {
					$current_amount = $current_amount + $cartitem->quantity;
				}
			}

			if ( $min_quantity <= $current_amount ) {
				return true;

			} else {
				return false;

			}


		} else {
			return true;
		}

	}

	private function process_update_cartitem( $cartitem_id, $new_quantity ) {
		if ( ! wp_verify_nonce( sanitize_text_field( $_POST['ec_cart_form_nonce'] ), 'wp-easycart-cart-update-item-' . $cartitem_id ) ) {
			header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( '', array( 'cart_error', 'invalid_nonce' ) ) ) );
			die();
		}

		$this->mysqli->update_cartitem( $cartitem_id, $GLOBALS['ec_cart_data']->ec_cart_id, $new_quantity );

		do_action( 'wpeasycart_cart_updated' );

		if ( isset( $_GET['ec_page'] ) ) {
			header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( htmlspecialchars( sanitize_key( $_GET['ec_page'] ), ENT_QUOTES ) ) ) );
		} else {
			header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page() ) );
		}
	}

	private function process_delete_cartitem( $cartitem_id ) {
		if ( ! wp_verify_nonce( sanitize_text_field( $_POST['ec_cart_form_nonce'] ), 'wp-easycart-cart-delete-item-' . $cartitem_id ) ) {
			header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( '', array( 'cart_error' => 'invalid_nonce' ) ) ) );
			die();
		}

		$this->mysqli->delete_cartitem( $cartitem_id, $GLOBALS['ec_cart_data']->ec_cart_id );

		do_action( 'wpeasycart_cart_updated' );

		if ( isset( $_GET['ec_page'] ) )
			header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( htmlspecialchars( sanitize_key( $_GET['ec_page'] ), ENT_QUOTES ) ) ) );
		else
			header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page() ) );
	}

	/**
	 * What stops this order from being placed now ( 6.0.2 ). The one-page checkout asks before it takes a payment, and the
	 * completion calls that have not charged anything yet ask again, so skipping the page cannot skip the checks.
	 *
	 * @since 6.0.2
	 * @return string[] Codes of cart_error_notes(); empty when the order can be placed.
	 */
	public function order_errors() {
		$errors  = array();
		$cd      = $GLOBALS['ec_cart_data']->cart_data;
		$minimum = (float) apply_filters( 'wpeasycart_minimum_order_total', get_option( 'ec_option_minimum_order_total' ) );
		if ( ! isset( $this->cart->total_items ) || $this->cart->total_items <= 0 || '' == $cd->email ) {
			$errors[] = 'session_expired';
		} else {
			if ( $minimum > 0 && $minimum > $this->cart->subtotal ) {
				$errors[] = 'minimum_order';
			}
			if ( '0' == $cd->billing_country || '' == $cd->billing_country || '' == $cd->billing_first_name || '' == $cd->billing_last_name || '' == $cd->billing_address_line_1 || '' == $cd->billing_city ) {
				$errors[] = 'invalid_address';
			} else if ( ! apply_filters( 'wpeasycart_validate_submit_order_data', true, $GLOBALS['ec_user'] ) ) {
				$errors[] = 'invalid_checkout'; /* an extension's own check; it can name it through wpeasycart_checkout_order_errors */
			}
			if ( ! $this->validate_cart_shipping() ) {
				$errors[] = 'invalid_cart_shipping';
			}
			if ( ! $this->order->verify_stock() ) {
				$errors[] = 'stock_invalid';
			}
			if ( $this->cart->has_preorder_items() && ( ! isset( $cd->pickup_date ) || '' == $cd->pickup_date ) ) {
				$errors[] = 'preorder_pickup';
			}
			if ( $this->cart->has_restaurant_items() && ! $this->cart->is_restaurant_open() ) {
				$errors[] = 'restaurant_closed';
			}
		}
		/**
		 * The reasons an order cannot be placed now.
		 *
		 * @since 6.0.2
		 * @param string[]     $errors   Codes; add a notice for a new one through wpeasycart_cart_errors.
		 * @param ec_cartpage  $cartpage The checkout.
		 */
		return array_values( array_unique( (array) apply_filters( 'wpeasycart_checkout_order_errors', $errors, $this ) ) );
	}

	/**
	 * order_errors() with their notices, for an AJAX answer.
	 *
	 * @since 6.0.2
	 * @return array[] Each array( 'code' => ..., 'message' => ... ).
	 */
	public function order_error_messages() {
		/* 6.0.2: the checks first, then the notices: a listener that words its notice from what the check found ( PRO checkout
		   fields names the field ) was read before it knew, so its generic text showed. */
		$codes = $this->order_errors();
		$notes = $this->cart_error_notes();
		$out   = array();
		foreach ( $codes as $code ) {
			$entry = array(
				'code'    => $code,
				'message' => wp_strip_all_tags( isset( $notes[ $code ] ) ? $notes[ $code ] : $notes['invalid_checkout'] ),
			);
			/**
			 * One problem as the one-page Place order check sends it to the page ( a listener adds what the page needs, e.g.
			 * WP EasyCart PRO's checkout fields name the rows to mark ); the page fires the jQuery event
			 * wpeasycart_checkout_errors with the list.
			 *
			 * @since 6.0.2
			 * @param array       $entry    code, message.
			 * @param string      $code     The problem's code.
			 * @param ec_cartpage $cartpage The checkout.
			 */
			$filtered = apply_filters( 'wpeasycart_checkout_error_entry', $entry, $code, $this );
			$out[]    = ( is_array( $filtered ) && isset( $filtered['code'], $filtered['message'] ) ) ? $filtered : $entry;
		}
		return $out;
	}

	/**
	 * What stops a subscription from starting now ( 6.0.2 ). The subscription checkout asks once the session holds the
	 * shopper's addresses and before anything is charged: the Stripe subscription call ( submit_stripe_quick_subscription() )
	 * and the classic post ( insert_subscription ). Those paths never ask order_errors().
	 *
	 * @since 6.0.2
	 * @param ec_product $product  The subscription product.
	 * @param int        $quantity Quantity.
	 * @return array[] Each array( 'code' => a cart_error_notes() code, 'message' => its text ); empty when it can start.
	 */
	public function subscription_errors( $product, $quantity = 1 ) {
		/**
		 * The reasons a subscription cannot start now.
		 *
		 * @since 6.0.2
		 * @param string[]    $errors   Codes; add a notice for a new one through wpeasycart_cart_errors.
		 * @param ec_product  $product  The subscription product.
		 * @param int         $quantity Quantity.
		 * @param ec_cartpage $cartpage The checkout.
		 */
		$codes = (array) apply_filters( 'wpeasycart_subscription_checkout_errors', array(), $product, (int) $quantity, $this );
		$codes = array_values( array_unique( array_filter( array_map( 'sanitize_key', $codes ) ) ) );
		if ( ! $codes ) {
			return array();
		}
		$notes = $this->cart_error_notes();
		$out   = array();
		foreach ( $codes as $code ) {
			$out[] = array(
				'code'    => $code,
				'message' => wp_strip_all_tags( isset( $notes[ $code ] ) ? $notes[ $code ] : $notes['invalid_checkout'] ),
			);
		}
		return $out;
	}

	/**
	 * The coupon a subscription checkout hands Stripe ( 6.0.2 ), by the rules of the subscription page's own code box
	 * ( ec_ajax_redeem_subscription_coupon_code(), wp_easycart_subscription_output_ajax_totals() ): the code reaches this
	 * product ( its product, manufacturer or category ), is not expired or used up, and takes a percent or an amount off,
	 * the only discounts a Stripe coupon carries. Both Stripe paths ( submit_stripe_quick_subscription() and the classic
	 * insert_subscription post ) and the first order's record use it. Before 6.0.2 they checked only the product and the
	 * manufacturer, so a code the box had refused ( expired, used up, another category ) still reached Stripe from the
	 * code field, and a shipping or free item coupon made Stripe refuse the whole subscription.
	 *
	 * @since 6.0.2
	 * @param string $code            The code typed, or kept in the checkout session.
	 * @param int    $product_id      The subscription product.
	 * @param int    $manufacturer_id Its manufacturer.
	 * @return array|false False when no coupon applies, else {
	 *     @type object $row           The coupon ( ec_coupons::subscription_coupon() ).
	 *     @type string $stripe        The Stripe coupon id ( an Offers code names its own; a classic coupon's is its code and a
	 *                                 hash of its terms, subscription_stripe_coupon_id() ).
	 *     @type string $code          What the order records.
	 *     @type bool   $amount_off    An amount off ( promo_dollar ), else a percent off ( promo_percentage ).
	 *     @type string $duration      once | repeating | forever.
	 *     @type int    $months        For repeating.
	 *     @type array  $stripe_coupon What ec_stripe / ec_stripe_connect::insert_coupon() makes when Stripe has no coupon
	 *                                 with that id yet ( a classic coupon's also carries 'name', its code ).
	 * }
	 */
	public static function subscription_checkout_coupon( $code, $product_id, $manufacturer_id = 0 ) {
		global $wpdb;
		$code = trim( (string) $code );
		if ( '' === $code || ! isset( $GLOBALS['ec_coupons'] ) || ! is_object( $GLOBALS['ec_coupons'] ) ) {
			return false;
		}
		$row = $GLOBALS['ec_coupons']->subscription_coupon( $code, (int) $product_id );
		if ( ! is_object( $row ) || ! empty( $row->blocked ) ) {
			return false;
		}
		if ( ! empty( $row->by_product_id ) ) {
			$reaches = ( (int) $row->product_id === (int) $product_id );
		} elseif ( ! empty( $row->by_manufacturer_id ) ) {
			$reaches = ( (int) $row->manufacturer_id === (int) $manufacturer_id );
		} elseif ( ! empty( $row->by_category_id ) ) {
			$reaches = (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT categoryitem_id FROM ec_categoryitem WHERE category_id = %d AND product_id = %d LIMIT 1', (int) $row->category_id, (int) $product_id ) );
		} else {
			$reaches = true;
		}
		if ( ! $reaches || ! empty( $row->coupon_expired ) ) {
			return false;
		}
		if ( isset( $row->max_redemptions ) && 999 !== (int) $row->max_redemptions && (int) $row->times_redeemed >= (int) $row->max_redemptions ) {
			return false;
		}
		/* The page shows a percent first, then an amount ( display_subscription_page() ): Stripe gets the same. */
		$percent_off = ! empty( $row->is_percentage_based ) && (float) $row->promo_percentage > 0;
		$amount_off  = ! $percent_off && ! empty( $row->is_dollar_based ) && (float) $row->promo_dollar > 0;
		if ( ! $percent_off && ! $amount_off ) {
			return false;
		}
		$duration = isset( $row->duration ) ? (string) $row->duration : '';
		$duration = in_array( $duration, array( 'once', 'repeating', 'forever' ), true ) ? $duration : 'forever';
		$months   = ( 'repeating' === $duration ) ? max( 1, (int) $row->duration_in_months ) : 1;
		$terms    = array(
			'duration'           => $duration,
			'duration_in_months' => $months,
			'is_amount_off'      => $amount_off,
			'amount_off'         => (float) $row->promo_dollar * 100,
			'percent_off'        => (float) $row->promo_percentage,
			'redeem_by'          => ! empty( $row->expiration_date ) ? strtotime( $row->expiration_date ) + 7 * 60 * 60 : null,
			'max_redemptions'    => isset( $row->max_redemptions ) ? $row->max_redemptions : 999,
		);
		// An Offers code names its own Stripe coupon. A classic coupon's carries a hash of its terms ( 6.0.2; it used to be the
		// code alone ): a Stripe coupon never changes, so a coupon edited after a subscription used it gets a new Stripe coupon
		// instead of Stripe charging the old terms. Stripe coupons made under the code alone stay with the subscriptions that
		// have them. The Stripe coupon's name stays the code ( what Stripe's invoices and receipts show ).
		if ( ! empty( $row->stripe_coupon_id ) ) {
			$stripe = (string) $row->stripe_coupon_id;
		} else {
			$stripe        = self::subscription_stripe_coupon_id( $row->promocode_id, $terms );
			$terms['name'] = (string) $row->promocode_id;
		}
		return array(
			'row'           => $row,
			'stripe'        => $stripe,
			'code'          => (string) $row->promocode_id,
			'amount_off'    => $amount_off,
			'duration'      => $duration,
			'months'        => $months,
			'stripe_coupon' => array_merge( array( 'promocode_id' => $stripe ), $terms ),
		);
	}

	/**
	 * The Stripe coupon id for a classic coupon's terms on a subscription ( 6.0.2 ): the code ( only the characters a Stripe
	 * id can carry in a URL ), then a hash of the code and of everything Stripe fixes when it makes the coupon: percent or
	 * amount off, duration, months, the Stripe currency, the end date and the redemption limit. The same terms always give
	 * the same id, so one Stripe coupon serves every subscription until the coupon changes. WP EasyCart PRO's Offers codes
	 * work the same way ( ec_offer_subscriptions::stripe_coupon_id() ).
	 *
	 * @since 6.0.2
	 * @param string $code  The coupon's code as saved.
	 * @param array  $terms is_amount_off, amount_off ( cents ), percent_off, duration, duration_in_months, redeem_by,
	 *                      max_redemptions ( as subscription_checkout_coupon() hands insert_coupon() ).
	 * @return string
	 */
	public static function subscription_stripe_coupon_id( $code, $terms ) {
		$code     = (string) $code;
		$amount   = ! empty( $terms['is_amount_off'] );
		$value    = $amount ? ( isset( $terms['amount_off'] ) ? $terms['amount_off'] : 0 ) : ( isset( $terms['percent_off'] ) ? $terms['percent_off'] : 0 );
		$duration = isset( $terms['duration'] ) ? (string) $terms['duration'] : '';
		$hash     = implode(
			'|',
			array(
				$code,
				$amount ? 'amount' : 'percent',
				number_format( (float) $value, 2, '.', '' ),
				$duration,
				( 'repeating' === $duration && isset( $terms['duration_in_months'] ) ) ? (int) $terms['duration_in_months'] : 0,
				strtolower( (string) get_option( 'ec_option_stripe_currency', '' ) ),
				! empty( $terms['redeem_by'] ) ? (int) $terms['redeem_by'] : 0,
				isset( $terms['max_redemptions'] ) ? (int) $terms['max_redemptions'] : 999,
			)
		);

		$base = substr( preg_replace( '/[^A-Za-z0-9_\-]/', '', $code ), 0, 60 );
		return ( '' !== $base ? $base : 'WPEC-COUPON' ) . '-' . strtoupper( substr( md5( $hash ), 0, 8 ) );
	}

	/**
	 * Why the classic checkout cannot leave a step ( 6.0.2: WP EasyCart PRO's checkout fields ). The step's own form has
	 * been saved by then; the shopper is sent back to the page that asks for what is missing, with its notice.
	 *
	 * @since 6.0.2
	 * @param string $step information | shipping | payment ( Place order ).
	 * @return array|null array( 'code' => a cart_error_notes() code, 'page' => checkout_info | checkout_shipping | checkout_payment ).
	 */
	public function checkout_step_error( $step ) {
		$pages = array(
			'information' => 'checkout_info',
			'shipping'    => 'checkout_shipping',
			'payment'     => 'checkout_payment',
		);
		if ( ! isset( $pages[ $step ] ) ) {
			return null;
		}
		/**
		 * A reason the classic checkout cannot leave this step.
		 *
		 * @since 6.0.2
		 * @param array|null  $error    array( 'code' => notice code ( add its text through wpeasycart_cart_errors ), 'page' => page to go back to ).
		 * @param string      $step     information | shipping | payment.
		 * @param ec_cartpage $cartpage The checkout.
		 */
		$error = apply_filters( 'wpeasycart_checkout_step_error', null, $step, $this );
		if ( ! is_array( $error ) || empty( $error['code'] ) ) {
			return null;
		}
		return array(
			'code' => sanitize_key( $error['code'] ),
			'page' => ( isset( $error['page'] ) && in_array( $error['page'], $pages, true ) ) ? $error['page'] : $pages[ $step ],
		);
	}

	private function validate_submit_order_data() {

		$data_validated = true;

		// Basic Validation
		if ( $GLOBALS['ec_cart_data']->cart_data->billing_country == "0" || $GLOBALS['ec_cart_data']->cart_data->billing_first_name == "" || $GLOBALS['ec_cart_data']->cart_data->billing_last_name == "" || $GLOBALS['ec_cart_data']->cart_data->billing_address_line_1 == "" || $GLOBALS['ec_cart_data']->cart_data->billing_city == "" || $GLOBALS['ec_cart_data']->cart_data->email == "" ) {
			$data_validated =  false;

		}

		$data_validated = apply_filters( 'wpeasycart_validate_submit_order_data', $data_validated, $GLOBALS['ec_user'] );

		return $data_validated;

	}

	private function validate_checkout_data() {

		$data_validated = true;

		// Basic Validation
		if ( $GLOBALS['ec_cart_data']->cart_data->billing_country == "0" || $GLOBALS['ec_cart_data']->cart_data->billing_first_name == "" || $GLOBALS['ec_cart_data']->cart_data->billing_last_name == "" || $GLOBALS['ec_cart_data']->cart_data->billing_address_line_1 == "" || $GLOBALS['ec_cart_data']->cart_data->billing_city == "" || $GLOBALS['ec_cart_data']->cart_data->email == "" ) {
			$data_validated =  false;

		}

		$data_validated = apply_filters( 'wpeasycart_validate_checkout_data', $data_validated, $GLOBALS['ec_user'] );

		return $data_validated;

	}
	
	public function validate_cart_shipping() {
		global $wpdb;
		$is_cart_shipping_valid = true;
		$user_zones = $this->mysqli->get_zone_ids( $GLOBALS['ec_cart_data']->cart_data->shipping_country, $GLOBALS['ec_cart_data']->cart_data->shipping_state );
		for ( $i = 0; $i <count( $this->cart->cart ); $i++ ) {
			if ( '0' != $this->cart->cart[$i]->shipping_restriction ) {
				$zone_found = false;
				for( $j = 0; $j < count( $user_zones ); $j++ ) {
					if ( $this->cart->cart[$i]->shipping_restriction == $user_zones[$j]->zone_id ) {
						$zone_found = true;
					}
				}
				if ( ! $zone_found ) {
					$is_cart_shipping_valid = false;
				}
			}
		}
		return $is_cart_shipping_valid;
	}

	/**
	 * Checks a VAT registration number with Vatlayer when the store has that turned on.
	 *
	 * @since 6.0.2 the check lives in wp_easycart_vatlayer::validate() ( Settings › Taxes › Test Vatlayer uses the same call ): HTTPS
	 *              when the Vatlayer plan allows it, answers kept for a day ( an invalid one for 10 minutes ), errors logged
	 *              without the access key.
	 * @param string $vat_number Number as entered.
	 * @return bool
	 */
	private function validate_vat_registration_number( $vat_number ) {
		return wp_easycart_vatlayer::validate( $vat_number );
	}

	/**
	 * PayPal's buttons ( ec_cart_paypal_button_code.php ).
	 *
	 * @param bool   $is_payment_page The payment step ( the addresses are already on the checkout ).
	 * @param bool   $is_horizontal   Horizontal layout.
	 * @param string $container_id    6.0.2: the element they render in ( a second set of buttons needs its own ).
	 */
	public function print_paypal_express_button_code( $is_payment_page = false, $is_horizontal = false, $container_id = 'paypal-button-container' ) {
		if ( ! $is_payment_page && ! apply_filters( 'wpeasycart_express_checkout_allowed', true, 'paypal', $this ) ) { /* 6.0.2 */
			return;
		}
		if ( ( ! $is_payment_page && '' == $GLOBALS['ec_cart_data']->cart_data->user_id && ( ! get_option( 'ec_option_allow_guest' ) || $this->has_downloads ) ) || ( ! $is_payment_page && $this->cart->has_preorder_items() ) || ( ! $is_payment_page && $this->cart->has_restaurant_items() ) ) {
			return;
		}
		if ( 'paypal-button-container' == $container_id && file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_paypal_button_code.php' ) ) { /* 6.0.2: a copy made before the payment step's own buttons only draws the express buttons */
			include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_paypal_button_code.php' );
		} else {
				include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_paypal_button_code.php' );
		}
	}

	public function print_paypal_express_button_code_order( $is_payment_page = false, $is_horizontal = false ) {
		if ( ( ! $is_payment_page && '' == $GLOBALS['ec_cart_data']->cart_data->user_id && ( ! get_option( 'ec_option_allow_guest' ) || $this->has_downloads ) ) || ( ! $is_payment_page && $this->cart->has_preorder_items() ) || ( ! $is_payment_page && $this->cart->has_restaurant_items() ) ) {
			return;
		}
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_paypal_button_code_order.php' ) ) {
			include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_paypal_button_code_order.php' );
		} else {
				include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_paypal_button_code_order.php' );
		}
	}

	public function submit_manual_order_v2() {
		global $wpdb;
		$ec_db_admin = new ec_db_admin();
		$response = $this->order->verify_stock();
		if ( $response ) {
			$response = $this->order->submit_order( "manual_bill" );
		}
		if ( '1' == $response ) {
			return esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_success', array( 'order_id' => (int) $this->order->order_id ) ) );
		} else {
			return esc_url_raw( wpeasycart_links()->get_cart_page( '', array( 'ec_cart_error' => 'invalid_cart_shipping' ) ) );
		}
	}

	public function submit_square_quick_payment_v2() {
		global $wpdb;
		$ec_db_admin = new ec_db_admin();
		$response = $this->order->verify_stock();
		if ( $response ) {
			$response = $this->order->submit_order( "credit_card" );
		}
		if ( '1' == $response ) {
			return json_encode( (object) array( 'ok' => true, 'goto' => esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_success', array( 'order_id' => (int) $this->order->order_id ) ) ) ) );
		} else {
			/**
			 * The gateway's decline text before it goes back to the shopper ( 6.0.2: checkout protection can show a
			 * simple message, and never shows fraud reasons ).
			 *
			 * @since 6.0.2
			 * @param string $message Gateway text.
			 * @param string $gateway Gateway.
			 */
			return json_encode( (object) array( 'error' => esc_attr( apply_filters( 'wpeasycart_payment_error_message', (string) $this->order->process_result, 'square' ) ) ) );
		}
	}

	public function submit_square_quick_payment( $nonce, $card_type, $last_4, $exp_month, $exp_year ) {
		global $wpdb;
		$ec_db_admin = new ec_db_admin();
		$response = $this->order->verify_stock();
		if ( $response ) {
			$response = $this->order->submit_order( "credit_card" );
		}
		if ( $response ) {
			$wpdb->query( $wpdb->prepare( "UPDATE ec_order SET card_holder_name = %s, payment_method = %s, creditcard_digits = %s, cc_exp_month = %s, cc_exp_year = %s WHERE order_id = %d", $GLOBALS['ec_cart_data']->cart_data->billing_first_name . ' ' . $GLOBALS['ec_cart_data']->cart_data->billing_last_name, $card_type, $last_4, $exp_month, $exp_year, $this->order->order_id ) );
			do_action( 'wpeasycart_submit_order_complete' );
			return esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_success', array( 'order_id' => (int) $this->order->order_id ) ) );

		} else {
			return '0';

		}
	}

	public function submit_v2_free_quick_payment() {
		global $wpdb;
		$ec_db_admin = new ec_db_admin();

		if ( isset( $_POST['ec_cart_is_subscriber'] ) && '1' == $_POST['ec_cart_is_subscriber'] ) {
			$first_name = $GLOBALS['ec_cart_data']->cart_data->billing_first_name;
			$last_name = $GLOBALS['ec_cart_data']->cart_data->billing_last_name;
			$email = $GLOBALS['ec_cart_data']->cart_data->email;

			$this->mysqli->insert_subscriber( $email, $first_name, $last_name ); // Fires wpeasycart_insert_subscriber ( 6.0.2: not fired a second time below ).

			if ( $GLOBALS['ec_user']->user_id ) {
				global $wpdb;
				$wpdb->query( $wpdb->prepare( "UPDATE ec_user SET is_subscriber = 1 WHERE ec_user.user_id = %d", $GLOBALS['ec_user']->user_id ) );
			}

			// MyMail Hook
			if ( function_exists( 'mailster' ) ) {
				$subscriber_id = mailster('subscribers')->add(array(
					'firstname' => $first_name,
					'lastname' => $last_name,
					'email' => $email,
					'status' => 1,
				), false );
			}
		}

		$this->order->submit_order( "third_party" );
		$order_status = 3;

		// Correct system log, third party pending is only temporary with this payment method
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ec_order_log WHERE order_id = %d AND order_log_key = "order-status-update"', $this->order->order_id ) );

		// Log order status update
		$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log( order_id, order_log_key ) VALUES( %d, "order-status-update" )', $this->order->order_id ) );
		$order_log_id = $wpdb->insert_id;
		$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "orderstatus_id", %s )', $order_log_id, $this->order->order_id, $order_status ) );

		// Maybe send email receipts
		if ( $order_status == 3 ) {
			$order_row = $ec_db_admin->get_order_row_admin( $this->order->order_id );
			$orderdetails = $ec_db_admin->get_order_details_admin( $this->order->order_id );

			/* Update Stock Quantity */
			foreach( $orderdetails as $orderdetail ) {
				$product = $wpdb->get_row( $wpdb->prepare( "SELECT ec_product.* FROM ec_product WHERE ec_product.product_id = %d", $orderdetail->product_id ) );
				if ( $product ) {
					if ( $product->use_optionitem_quantity_tracking ) {
						$ec_db_admin->update_quantity_value( $orderdetail->quantity, $orderdetail->product_id, $orderdetail->optionitem_id_1, $orderdetail->optionitem_id_2, $orderdetail->optionitem_id_3, $orderdetail->optionitem_id_4, $orderdetail->optionitem_id_5 );
					}
					$ec_db_admin->update_product_stock( $orderdetail->product_id, $orderdetail->quantity );
					$this->mysqli->update_details_stock_adjusted( $orderdetail->orderdetail_id );
					$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log( order_id, order_log_key ) VALUES( %d, "order-stock-update" )', $this->order_id ) );
					$order_log_id = $wpdb->insert_id;
					$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "product_id", %s )', $order_log_id, $this->order_id, $orderdetail->product_id ) );
					$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "quantity", %s )', $order_log_id, $this->order_id, '-' . $orderdetail->quantity ) );
				}
			}

			// Update Order Status/Send Alerts
			do_action( 'wpeasycart_order_paid', $this->order->order_id );

			// send email
			$order_display = new ec_orderdisplay( $order_row, true, true );
			$order_display->send_email_receipt();
			$order_display->send_gift_cards();
		} else {
			do_action( 'wpeasycart_order_complete', $this->order->order_id, $order_status );
		}

		// Clear tempcart
		$ec_db_admin->clear_tempcart( $GLOBALS['ec_cart_data']->ec_cart_id );
		$this->order->clear_session();

		$GLOBALS['ec_cart_data']->save_session_to_db();

		return esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_success', array( 'order_id' => (int) $this->order->order_id ) ) );
	}

	/**
	 * @param string $payment_id PaymentIntent.
	 * @param string $card_type  Brand or method.
	 * @param string $last_4     Last four.
	 * @param string $exp_month  Expiry month.
	 * @param string $exp_year   Expiry year.
	 * @param bool   $hold       6.0.2: the payment does not match the cart's total: keep the order pending for the merchant.
	 */
	public function submit_stripe_quick_payment( $payment_id, $card_type, $last_4, $exp_month, $exp_year, $hold = false ) {
		global $wpdb;
		$ec_db_admin = new ec_db_admin();

		if ( isset( $_POST['ec_cart_is_subscriber'] ) && '1' == $_POST['ec_cart_is_subscriber'] ) {
			$first_name = $GLOBALS['ec_cart_data']->cart_data->billing_first_name;
			$last_name = $GLOBALS['ec_cart_data']->cart_data->billing_last_name;
			$email = $GLOBALS['ec_cart_data']->cart_data->email;

			$this->mysqli->insert_subscriber( $email, $first_name, $last_name ); // Fires wpeasycart_insert_subscriber ( 6.0.2: not fired a second time below ).

			if ( $GLOBALS['ec_user']->user_id ) {
				global $wpdb;
				$wpdb->query( $wpdb->prepare( "UPDATE ec_user SET is_subscriber = 1 WHERE ec_user.user_id = %d", $GLOBALS['ec_user']->user_id ) );
			}

			// MyMail Hook
			if ( function_exists( 'mailster' ) ) {
				$subscriber_id = mailster('subscribers')->add(array(
					'firstname' => $first_name,
					'lastname' => $last_name,
					'email' => $email,
					'status' => 1,
				), false );
			}
		}

		$this->order->submit_order( "third_party" );

		// Verify Payment Status in Case Already Successful
		$order_status = 12;
		if ( get_option( 'ec_option_payment_process_method' ) == 'stripe' )
			$stripe = new ec_stripe();
		else
			$stripe = new ec_stripe_connect();

		$stripe->initialize( $this->cart, $this->user, $this->shipping, $this->tax, $this->discount, $this->payment->credit_card, $this->order_totals, $this->order->order_id );

		// Maybe create customer
		if ( get_option( 'ec_option_stripe_order_create_customer' ) && $this->user->user_id != 0 && $this->user->stripe_customer_id == "" ) {
			$customer_id = $stripe->insert_quick_customer( $payment_id );
			$ec_db_admin->update_user_stripe_id( $this->user->user_id, $customer_id );

		} else if ( get_option( 'ec_option_stripe_order_create_customer' ) && $this->user->stripe_customer_id == "" ) {
			$stripe->insert_guest_customer( $payment_id, $this->order->order_id );

		} else if ( get_option( 'ec_option_stripe_order_create_customer' ) ) {
			$payment_intent = $stripe->get_payment_intent( $payment_id );
			$stripe->attach_payment_method( $payment_intent->payment_method, $this->user );

		}

		// Set Order ID and Confirm Current Payment Status
		$payment_status = $stripe->update_payment_intent_description( $payment_id, $this->order->order_id );
		if ( $payment_status && $payment_status->status == 'succeeded' ) {
			$order_status = 3;
		} else if ( $payment_status && $payment_status->status == 'requires_capture' ) {
			$order_status = 12;
		} else if ( $payment_status && $payment_status->status == 'processing' ) {
			$order_status = 12;
		} else if ( $payment_status && $payment_status->status == 'canceled' ) {
			$order_status = 19;
		}
		if ( $hold && 19 != $order_status ) {
			/* 6.0.2: paid, but not this cart's total: pending, no stock taken, no receipt, and a note on the order. */
			$order_status = 12;
			/* translators: %s: Stripe payment id. */
			wp_easycart_stripe_hold_order( $this->order->order_id, sprintf( __( 'The Stripe payment %s does not match this order’s total ( the cart changed after it was paid ), so the order is on hold. Check the payment in Stripe before you ship it.', 'wp-easycart' ), $payment_id ) ); /* the webhook leaves it alone */
		}
		if ( 19 !== (int) $order_status && class_exists( 'wp_easycart_shipping_groups' ) && wp_easycart_shipping_groups::hold_order( $this->order->order_id, $this ) ) {
			$order_status = 12; /* 6.0.2: paid, but a fulfillment partner's shipping could not be confirmed for this address ( the wallet sheet and the one-page completion ) */
		}

		// Get Charge ID
		$stripe_charge_id = '';
		if ( isset( $payment_status->charges ) && isset( $payment_status->charges->data ) && count( $payment_status->charges->data ) > 0 ) {
			$stripe_charge_id = $payment_status->charges->data[0]->id;
		} else if ( isset( $payment_status->latest_charge ) ) {
			$stripe_charge_id = $payment_status->latest_charge;
		}

		// Update Order
		if ( '' == $stripe_charge_id ) {
			$wpdb->query( $wpdb->prepare( "UPDATE ec_order SET order_gateway = %s, creditcard_digits = %s, cc_exp_month = %s, cc_exp_year = %s, gateway_transaction_id = %s, payment_method = %s, orderstatus_id = %d WHERE order_id = %d", get_option( 'ec_option_payment_process_method' ), $last_4, $exp_month, $exp_year, $payment_id, $card_type, $order_status, $this->order->order_id ) );
		} else {
			$wpdb->query( $wpdb->prepare( "UPDATE ec_order SET order_gateway = %s, creditcard_digits = %s, cc_exp_month = %s, cc_exp_year = %s, stripe_charge_id = %s, gateway_transaction_id = %s, payment_method = %s, orderstatus_id = %d WHERE order_id = %d", get_option( 'ec_option_payment_process_method' ), $last_4, $exp_month, $exp_year, $stripe_charge_id, $payment_id, $card_type, $order_status, $this->order->order_id ) );
		}

		// Correct system log, third party pending is only temporary with this payment method
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ec_order_log WHERE order_id = %d AND order_log_key = "order-status-update"', $this->order->order_id ) );

		// Log order status update
		$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log( order_id, order_log_key ) VALUES( %d, "order-status-update" )', $this->order->order_id ) );
		$order_log_id = $wpdb->insert_id;
		$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "orderstatus_id", %s )', $order_log_id, $this->order->order_id, $order_status ) );
		/* 6.0.2: the final status is written directly above, so tell listeners ( offers, reviews, invoices, accounting ) as the other paths do. */
		do_action( 'wpeasycart_order_status_update', (int) $this->order->order_id, (int) $order_status );

		// Maybe send email receipts
		if ( $order_status == 3 ) {
			$order_row = $ec_db_admin->get_order_row_admin( $this->order->order_id );
			$orderdetails = $ec_db_admin->get_order_details_admin( $this->order->order_id );

			/* Update Stock Quantity */
			foreach( $orderdetails as $orderdetail ) {
				$product = $wpdb->get_row( $wpdb->prepare( "SELECT ec_product.* FROM ec_product WHERE ec_product.product_id = %d", $orderdetail->product_id ) );
				if ( $product ) {
					if ( $product->use_optionitem_quantity_tracking ) {
						$ec_db_admin->update_quantity_value( $orderdetail->quantity, $orderdetail->product_id, $orderdetail->optionitem_id_1, $orderdetail->optionitem_id_2, $orderdetail->optionitem_id_3, $orderdetail->optionitem_id_4, $orderdetail->optionitem_id_5 );
					}
					$ec_db_admin->update_product_stock( $orderdetail->product_id, $orderdetail->quantity );
					$this->mysqli->update_details_stock_adjusted( $orderdetail->orderdetail_id );
					$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log( order_id, order_log_key ) VALUES( %d, "order-stock-update" )', $this->order_id ) );
					$order_log_id = $wpdb->insert_id;
					$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "product_id", %s )', $order_log_id, $this->order_id, $orderdetail->product_id ) );
					$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "quantity", %s )', $order_log_id, $this->order_id, '-' . $orderdetail->quantity ) );
				}
			}

			// Update Order Status/Send Alerts
			do_action( 'wpeasycart_order_paid', $this->order->order_id );

			// send email
			$order_display = new ec_orderdisplay( $order_row, true, true );
			$order_display->send_email_receipt();
			$order_display->send_gift_cards();
		} else {
			do_action( 'wpeasycart_order_complete', $this->order->order_id, $order_status );
		}

		// Clear tempcart
		$ec_db_admin->clear_tempcart( $GLOBALS['ec_cart_data']->ec_cart_id );
		$this->order->clear_session();

		$GLOBALS['ec_cart_data']->save_session_to_db();

		return esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_success', array( 'order_id' => (int) $this->order->order_id ) ) );
	}

	/**
	 * Record a Stripe card payment made on a pay link against its order.
	 *
	 * 6.0.2: the order and PaymentIntent come from the verified session binding
	 * ( get_verified_stripe_invoice_payment() ), never from the posted invoice_id alone;
	 * $payment_id is kept for backwards compatibility and replaced by the bound intent.
	 *
	 * @param string      $payment_id Ignored; the bound PaymentIntent is used.
	 * @param string      $card_type  Card brand.
	 * @param string      $last_4     Card last four.
	 * @param string      $exp_month  Card expiry month.
	 * @param string      $exp_year   Card expiry year.
	 * @param object|null $payment    Result of get_verified_stripe_invoice_payment(), when the caller has it.
	 * @return string URL to send the shopper to.
	 */
	public function submit_stripe_invoice_payment( $payment_id, $card_type, $last_4, $exp_month, $exp_year, $payment = null ) {
		global $wpdb;
		$ec_db_admin = new ec_db_admin();

		if ( 'stripe' === get_option( 'ec_option_payment_process_method' ) ) {
			$stripe = new ec_stripe();
		} else {
			$stripe = new ec_stripe_connect();
		}

		$requested_order_id = ( isset( $_POST['invoice_id'] ) ) ? (int) $_POST['invoice_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce checked by ec_ajax_get_stripe_complete_payment_invoice(); only picks one of this session's bindings.
		if ( ! is_object( $payment ) || ! isset( $payment->order ) || ! isset( $payment->payment_intent ) ) {
			$payment = $this->get_verified_stripe_invoice_payment( $stripe, $requested_order_id );
		}
		if ( ! $payment ) {
			return esc_url_raw( $this->get_invoice_payment_failed_url( $requested_order_id ) );
		}
		$invoice_id = (int) $payment->order->order_id;
		$payment_id = $payment->payment_intent->id;

		if ( isset( $_POST['ec_cart_is_subscriber'] ) && '1' == $_POST['ec_cart_is_subscriber'] ) {
			$first_name = $GLOBALS['ec_cart_data']->cart_data->billing_first_name;
			$last_name = $GLOBALS['ec_cart_data']->cart_data->billing_last_name;
			$email = $GLOBALS['ec_cart_data']->cart_data->email;

			$this->mysqli->insert_subscriber( $email, $first_name, $last_name ); // Fires wpeasycart_insert_subscriber ( 6.0.2: not fired a second time below ).

			if ( $GLOBALS['ec_user']->user_id ) {
				global $wpdb;
				$wpdb->query( $wpdb->prepare( "UPDATE ec_user SET is_subscriber = 1 WHERE ec_user.user_id = %d", $GLOBALS['ec_user']->user_id ) );
			}

			// MyMail Hook
			if ( function_exists( 'mailster' ) ) {
				$subscriber_id = mailster('subscribers')->add(array(
					'firstname' => $first_name,
					'lastname' => $last_name,
					'email' => $email,
					'status' => 1,
				), false );
			}
		}

		// Verify Payment Status in Case Already Successful
		$order_status = 12;

		// Maybe create customer
		if ( get_option( 'ec_option_stripe_order_create_customer' ) && $this->user->user_id != 0 && $this->user->stripe_customer_id == "" ) {
			$customer_id = $stripe->insert_quick_customer( $payment_id );
			$ec_db_admin->update_user_stripe_id( $this->user->user_id, $customer_id );

		} else if ( get_option( 'ec_option_stripe_order_create_customer' ) && $this->user->stripe_customer_id == "" ) {
			$stripe->insert_guest_customer( $payment_id, $invoice_id );

		} else if ( get_option( 'ec_option_stripe_order_create_customer' ) ) {
			$stripe->attach_payment_method( $payment->payment_intent->payment_method, $this->user );

		}

		// Set Order ID and Confirm Current Payment Status
		$payment_status = $stripe->update_payment_intent_description( $payment_id, $invoice_id );
		if ( ! $payment_status ) {
			$payment_status = $payment->payment_intent;
		}
		if ( $payment_status && $payment_status->status == 'succeeded' ) {
			$order_status = 3;
		}

		// Get Charge ID
		$stripe_charge_id = '';
		if ( isset( $payment_status->charges ) && isset( $payment_status->charges->data ) && count( $payment_status->charges->data ) > 0 ) {
			$stripe_charge_id = $payment_status->charges->data[0]->id;
		} else if ( isset( $payment_status->latest_charge ) ) {
			$stripe_charge_id = $payment_status->latest_charge;
		}

		// Update Order
		$wpdb->query( $wpdb->prepare( "UPDATE 
				ec_order 
			SET 
				billing_first_name = %s, billing_last_name = %s, billing_company_name = %s, billing_address_line_1 = %s, billing_address_line_2 = %s, 
				billing_city = %s, billing_state = %s, billing_zip = %s, billing_country = %s, billing_phone = %s, 
				shipping_first_name = %s, shipping_last_name = %s, shipping_company_name = %s, shipping_address_line_1 = %s, shipping_address_line_2 = %s, 
				shipping_city = %s, shipping_state = %s, shipping_zip = %s, shipping_country = %s, shipping_phone = %s, 
				order_gateway = %s, creditcard_digits = %s, cc_exp_month = %s, cc_exp_year = %s, stripe_charge_id = %s, 
				gateway_transaction_id = %s, payment_method = 'credit_card', orderstatus_id = %d 
			WHERE order_id = %d", 
				sanitize_text_field( $_POST['billing_address']['first_name'] ), 
				sanitize_text_field( $_POST['billing_address']['last_name'] ),
				sanitize_text_field( $_POST['billing_address']['company_name'] ),
				sanitize_text_field( $_POST['billing_address']['address1'] ),
				sanitize_text_field( $_POST['billing_address']['address2'] ),
				sanitize_text_field( $_POST['billing_address']['city'] ),
				sanitize_text_field( $_POST['billing_address']['state'] ),
				sanitize_text_field( $_POST['billing_address']['zip'] ),
				sanitize_text_field( $_POST['billing_address']['country'] ),
				sanitize_text_field( $_POST['billing_address']['phone'] ),
				sanitize_text_field( $_POST['shipping_address']['first_name'] ),
				sanitize_text_field( $_POST['shipping_address']['last_name'] ), 
				sanitize_text_field( $_POST['shipping_address']['company_name'] ),
				sanitize_text_field( $_POST['shipping_address']['address1'] ),
				sanitize_text_field( $_POST['shipping_address']['address2'] ),
				sanitize_text_field( $_POST['shipping_address']['city'] ),
				sanitize_text_field( $_POST['shipping_address']['state'] ),
				sanitize_text_field( $_POST['shipping_address']['zip'] ),
				sanitize_text_field( $_POST['shipping_address']['country'] ),
				sanitize_text_field( $_POST['shipping_address']['phone'] ),
				sanitize_text_field( get_option( 'ec_option_payment_process_method' ) ),
				$last_4, $exp_month, $exp_year, $stripe_charge_id, 
				$payment_id, $order_status, $invoice_id
		) );

		// Paid: the pay link no longer opens the order, so drop its binding. A processing or authorised payment keeps it, which stops a second charge on the same link.
		if ( 3 === $order_status ) {
			self::forget_invoice_payment_binding( $invoice_id );
		}

		// Clear tempcart
		$ec_db_admin->clear_tempcart( $GLOBALS['ec_cart_data']->ec_cart_id );
		$this->order->clear_session();

		// Maybe send email receipts
		if ( $order_status == 3 ) {
			$order_row = $ec_db_admin->get_order_row_admin( $invoice_id );
			$orderdetails = $ec_db_admin->get_order_details_admin( $invoice_id );

			do_action( 'wp_easycart_invoice_paid', $invoice_id );

			// send email
			$order_display = new ec_orderdisplay( $order_row, true, true );
			$order_display->send_email_receipt();
			$order_display->send_gift_cards();
		}

		$GLOBALS['ec_cart_data']->save_session_to_db();

		return esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_success', array( 'order_id' => $invoice_id ) ) );
	}

	public function submit_stripe_quick_subscription_payment( $payment_id ) {
		global $wpdb;

		if ( get_option( 'ec_option_payment_process_method' ) == 'stripe' )
			$stripe = new ec_stripe();
		else
			$stripe = new ec_stripe_connect();

		/* 6.0.2: only the subscription this checkout just started ( submit_stripe_quick_subscription() ), once. */
		$binding_key = 'wpec_sub_pay_' . md5( $GLOBALS['ec_cart_data']->ec_cart_id );
		$binding = get_transient( $binding_key );
		if ( ! is_array( $binding ) || ! isset( $_POST['subscription_id'] ) || (int) $binding['subscription_id'] !== (int) $_POST['subscription_id'] ) {
			return esc_url_raw( wpeasycart_links()->get_account_page( 'subscriptions' ) );
		}
		delete_transient( $binding_key );

		if ( isset( $_POST['ec_cart_is_subscriber'] ) && '1' == $_POST['ec_cart_is_subscriber'] ) {
			$first_name = $GLOBALS['ec_cart_data']->cart_data->billing_first_name;
			$last_name = $GLOBALS['ec_cart_data']->cart_data->billing_last_name;
			$email = $GLOBALS['ec_cart_data']->cart_data->email;

			$this->mysqli->insert_subscriber( $email, $first_name, $last_name ); // Fires wpeasycart_insert_subscriber ( 6.0.2: not fired a second time below ).

			if ( $GLOBALS['ec_user']->user_id ) {
				$wpdb->query( $wpdb->prepare( "UPDATE ec_user SET is_subscriber = 1 WHERE ec_user.user_id = %d", $GLOBALS['ec_user']->user_id ) );
			}

			// MyMail Hook
			if ( function_exists( 'mailster' ) ) {
				$subscriber_id = mailster('subscribers')->add(array(
					'firstname' => $first_name,
					'lastname' => $last_name,
					'email' => $email,
					'status' => 1,
				), false );
			}
		}

		$model_number = sanitize_text_field( $_POST['model_number'] );
		$products = $this->mysqli->get_product_list( $wpdb->prepare( " WHERE product.model_number = %s", $model_number ), "", "", "" );
		$product = new ec_product( $products[0] );
		$this->cart->cart = array( $product );
		$subscription_cart = array();

		$subscription_row = $this->mysqli->get_subscription_row( (int) $_POST['subscription_id'] );
		$subscription = new ec_subscription( $subscription_row );

		$quantity = 1;
		if ( isset( $_POST['ec_quantity'] ) )
			$quantity = (int) $_POST['ec_quantity'];

		if ( $product->trial_period_days > 0 ) {
			$subscription->send_trial_start_email( $GLOBALS['ec_user'] );

		}

		// Get option item price adjustments
		$option_promotion_multiplier = 1;
		$option_promotion_discount = 0;
		$promotions = $GLOBALS['ec_promotions']->promotions;
		for( $i=0; $i<count( $promotions ); $i++ ) {
			if ( $product->promotion_text == $promotions[$i]->promotion_name ) {
				if ( $promotions[$i]->price1 == 0 ) {
					$option_promotion_multiplier = round( $promotions[$i]->percentage1 / 100, 2 );
				} else if ( $promotions[$i]->price1 != 0 ) {
					$option_promotion_discount = $promotions[$i]->price1;
				}
			}
		}

		// Handle Option Pricing Plans
		$option_price_adjustment = 0;
		$option_price_onetime_adjustment = 0;
		$option_weight_adjustment = 0;
		$option_weight_onetime_adjustment = 0;
		$optionitem_list = $GLOBALS['ec_options']->get_all_optionitems();

		foreach( $optionitem_list as $option_item ) {
			$found = false;
			$check_option = false;
			if ( $option_item->optionitem_id == $this->subscription_option1 || $option_item->optionitem_id == $this->subscription_option2 || $option_item->optionitem_id == $this->subscription_option3 || $option_item->optionitem_id == $this->subscription_option4 || $option_item->optionitem_id == $this->subscription_option5 ) {
				if ( $option_item->optionitem_price > 0 ) {
					$option_price_adjustment += $option_item->optionitem_price;
					$subscription_cart[] = (object) array(
						'vat_enabled' => ( $product->vat_rate != 0 ),
						'is_taxable' => $product->is_taxable,
						'item_total' => round( $option_item->optionitem_price * $quantity, 2 ),
						'item_discount' => 0,
					);
				}
				if ( $option_item->optionitem_weight > 0 ) {
					$option_weight_adjustment += $option_item->optionitem_weight;
				}
			}
		}

		if ( $this->subscription_advanced_options ) {
			foreach( $this->subscription_advanced_options as $option ) {
				$optionitem = $GLOBALS['ec_options']->get_optionitem( $option['optionitem_id'] );
				if ( $optionitem->optionitem_disallow_shipping ) {
					$product->is_shippable = false;
				}
				if ( $optionitem->optionitem_download_override_file ) {
					/* JSON settings object from the option editor, read the same way as ec_cartitem. @since 6.0.0 */
					if ( '{' == substr( $optionitem->optionitem_download_override_file, 0, 1 ) ) {
						$override_file_json = json_decode( $optionitem->optionitem_download_override_file );
						if ( is_object( $override_file_json ) && isset( $override_file_json->is_override_file ) && '1' == $override_file_json->is_override_file ) {
							if ( isset( $override_file_json->is_override_amazon ) && '1' == $override_file_json->is_override_amazon ) {
								$product->is_amazon_download = 1;
								$product->amazon_key = isset( $override_file_json->override_amazon_key ) ? $override_file_json->override_amazon_key : '';
							} else if ( isset( $override_file_json->override_file_name ) ) {
								$product->is_amazon_download = 0;
								$product->download_file_name = $override_file_json->override_file_name;
							}
						}
					} else {
						$product->download_file_name = $optionitem->optionitem_download_override_file;
					}
				}
				if ( $optionitem && $optionitem->optionitem_price > 0 ) {
					if ( 'number' == $option['option_type'] ) {
						$option_price_adjustment += ( $optionitem->optionitem_price * (int) $option['optionitem_value'] );
						$subscription_cart[] = (object) array(
							'vat_enabled' => ( $product->vat_rate != 0 ),
							'is_taxable' => $product->is_taxable,
							'item_total' => round( ( $optionitem->optionitem_price * (int) $option['optionitem_value'] ) * $quantity, 2 ),
							'item_discount' => 0,
						);
					} else {
						$option_price_adjustment += $optionitem->optionitem_price;
						$subscription_cart[] = (object) array(
							'vat_enabled' => ( $product->vat_rate != 0 ),
							'is_taxable' => $product->is_taxable,
							'item_total' => round( $optionitem->optionitem_price * $quantity, 2 ),
							'item_discount' => 0,
						);
					}
				} else if ( $optionitem && $optionitem->optionitem_price_onetime > 0 ) {
					$option_price_onetime_adjustment += $optionitem->optionitem_price_onetime;
					$subscription_cart[] = (object) array(
						'vat_enabled' => ( $product->vat_rate != 0 ),
						'is_taxable' => $product->is_taxable,
						'item_total' => round( $optionitem->optionitem_price_onetime, 2 ),
						'item_discount' => 0,
					);
				} else if ( $optionitem && $optionitem->optionitem_price_override > -1 ) {
					$product->price = $optionitem->optionitem_price_override;
				}
				if ( $optionitem && $optionitem->optionitem_weight > 0 ) {
					if ( 'number' == $option['option_type'] ) {
						$option_weight_adjustment += ( $optionitem->optionitem_weight * (int) $option['optionitem_value'] );
					} else {
						$option_weight_adjustment += $optionitem->optionitem_weight;
					}
				} else if ( $optionitem && $optionitem->optionitem_weight_onetime > 0 ) {
					$option_weight_onetime_adjustment += $optionitem->optionitem_weight_onetime;
				} else if ( $optionitem && $optionitem->optionitem_weight_override > -1 ) {
					$product->weight = $optionitem->optionitem_weight_override;
				}
			}
		}

		$subscription_cart[] = (object) array(
			'vat_enabled' => ( $product->vat_rate != 0 ),
			'is_taxable' => $product->is_taxable,
			'item_total' => round( $product->price * $quantity, 2 ),
			'item_discount' => 0,
		);

		if ( $product->trial_period_days > 0 ) {
			$product->price = 0;
		} else {
			$product->price = $product->price + $option_price_adjustment;
		}

		if ( get_option( 'ec_option_collect_shipping_for_subscriptions' ) && get_option( 'ec_option_use_shipping' ) && $product->is_shippable ) {
			$ship_price_total = ( $product->price * $quantity ) + $option_price_onetime_adjustment;
			$ship_weight_total = ( $product->weight + $option_weight_adjustment ) * $quantity + $option_weight_onetime_adjustment;
			$ship_quantity = $quantity;
		} else {
			$ship_price_total = 0;
			$ship_weight_total = 0;
			$ship_quantity = 0;
		}

		$product->weight = $ship_weight_total;
		do_action( 'wpeasycart_cart_subscription_updated', $product, $quantity, $ship_weight_total ); /* 6.0.2: the total weight */

		$shipping_method = '';
		if ( get_option( 'ec_option_collect_shipping_for_subscriptions' ) && get_option( 'ec_option_use_shipping' ) && $product->is_shippable ) {
			$this->shipping = new ec_shipping( $ship_price_total, $ship_weight_total, $ship_quantity, 'RADIO', $GLOBALS['ec_user']->freeshipping, $product->length, $product->width, $product->height * $quantity, array( $product ) );
			$this->shipping->change_shipping_js_func = 'ec_cart_subscription_shipping_method_change';
			$this->cart->shippable_total_items = $quantity;
			$handling_total = $product->handling_price + ( $product->handling_price_each * $quantity );
			$shipping_total = floatval( $this->shipping->get_shipping_price( $handling_total ) );
			if ( ! get_option( 'ec_option_use_shipping' ) || $shipping_total <= 0 ) {
				$shipping_method = '';
			} else if ( $this->shipping->shipping_method == "fraktjakt" ) {
				$shipping_method = $this->shipping->get_selected_shipping_method();
			} else if ( $GLOBALS['ec_cart_data']->cart_data->shipping_method != "" && $GLOBALS['ec_cart_data']->cart_data->shipping_method != "standard" ) {
				$shipping_method = $this->mysqli->get_shipping_method_name( $GLOBALS['ec_cart_data']->cart_data->shipping_method );
			} else if ( ( $this->shipping->shipping_method == "price" || $this->shipping->shipping_method == "weight" ) && $GLOBALS['ec_cart_data']->cart_data->expedited_shipping != "" ) {
				$shipping_method = wp_easycart_language()->get_text( "cart_estimate_shipping", "cart_estimate_shipping_express" );
			} else {
				$shipping_method = wp_easycart_language()->get_text( "cart_estimate_shipping", "cart_estimate_shipping_standard" );
			}
			$subscription_cart[] = (object) array(
				'vat_enabled' => ! get_option( 'ec_option_no_vat_on_shipping' ),
				'is_taxable' => get_option( 'ec_option_collect_tax_on_shipping' ),
				'item_total' => round( $shipping_total, 2 ),
				'item_discount' => 0,
			);
		} else {
			$handling_total = 0;
			$shipping_total = 0;
			$this->cart->shippable_total_items = 0;
			$this->order_totals->shipping_total = 0;
		}

		// Coupon Information
		$coupon = NULL;
		$discount_total = 0;
		$is_dollar_discount = false;
		$is_match = false;
		$wpec_coupon = false;
		if ( isset( $_POST['coupon_code'] ) && $_POST['coupon_code'] != "" ) {
			/* 6.0.2: the coupon submit_stripe_quick_subscription() gave Stripe, by the same rules; the order records the code. */
			$wpec_coupon = self::subscription_checkout_coupon( sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) ), $product->product_id, $product->manufacturer_id ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- ec_ajax_get_stripe_complete_payment_subscription() checked its nonce.
			if ( $wpec_coupon ) {
				$coupon_row = $wpec_coupon['row'];
				$is_match = true;
				$coupon = $wpec_coupon['code'];
			}
		}

		// IF MATCH FOUND, APPLY TO PRODUCT
		if ( $is_match ) {
			if ( $wpec_coupon['amount_off'] ) {
				$is_dollar_discount = true;
				$discount_total = round( $coupon_row->promo_dollar, 2 );
			} else {
				$coupon_percentage = round( $coupon_row->promo_percentage / 100, 2 );
				for ( $i = 0; $i < count( $subscription_cart ); $i++ ) {
					$subscription_cart[ $i ]->item_discount = round( $subscription_cart[ $i ]->item_total * $coupon_percentage, 2 );
					$discount_total += $subscription_cart[ $i ]->item_discount;
				}
			}
		} else if ( $option_promotion_multiplier != 1 ) {
			for ( $i = 0; $i < count( $subscription_cart ); $i++ ) {
				$subscription_cart[ $i ]->item_discount = round( $subscription_cart[ $i ]->item_total * $option_promotion_multiplier, 2 );
				$discount_total += $subscription_cart[ $i ]->item_discount;
			}
		} else if ( $option_promotion_discount > 0 ) {
			$is_dollar_discount = true;
			$discount_total = round( $option_promotion_discount, 2 );
		}
		// END MATCHING COUPON SECTION

		$this->cart->subtotal = ( $product->price * $quantity ) + $option_price_onetime_adjustment;
		if ( $is_dollar_discount ) {
			for ( $i = 0; $i < count( $subscription_cart ); $i++ ) {
				$subscription_cart[$i]->item_total = $subscription_cart[$i]->item_total - round( ( $subscription_cart[$i]->item_total / ( $this->cart->subtotal + $shipping_total ) ) * $discount_total, 2 );
			}
		} else {
			for ( $i = 0; $i < count( $subscription_cart ); $i++ ) {
				$subscription_cart[$i]->item_total = $subscription_cart[$i]->item_total - $subscription_cart[$i]->item_discount;
			}
		}

		if ( $product->is_taxable || $product->vat_rate ) {
			$taxable_subtotal = 0;
			$vatable_subtotal = 0;
			if ( $product->is_taxable ) {
				$taxable_subtotal = $product->price * $quantity - $discount_total;
			}
			if ( $product->vat_rate ) {
				$vatable_subtotal = $product->price * $quantity - $discount_total;
			}

			do_action( 'wpeasycart_cart_subscription_pre_tax', $product, $quantity, $shipping_total, $handling_total, $discount_total );

			if ( get_option( 'ec_option_tax_cloud_api_id' ) != "" && get_option( 'ec_option_tax_cloud_api_key' ) != "" ) {
				wpeasycart_taxcloud()->setup_subscription_for_tax( $product, $quantity, $discount_total );
			}
			$this->tax = new ec_tax( $product->price * $quantity, $taxable_subtotal, $vatable_subtotal, sanitize_text_field( $_POST['billing_details']['address']['state'] ), sanitize_text_field( $_POST['billing_details']['address']['country'] ), $GLOBALS['ec_user']->taxfree, $this->shipping->get_shipping_price( ( $product->handling_price_each * $quantity ) + $product->handling_price ), $subscription_cart, true );
		} else {
			$this->tax = new ec_tax( 0, 0, 0, sanitize_text_field( $_POST['billing_details']['address']['state'] ), sanitize_text_field( $_POST['billing_details']['address']['country'] ), $GLOBALS['ec_user']->taxfree, $this->shipping->get_shipping_price( ( $product->handling_price_each * $quantity ) + $product->handling_price ), $subscription_cart, true );
		}

		$this->order_totals = new ec_order_totals( $this->cart, $GLOBALS['ec_user'], $this->shipping, $this->tax, $this->discount );
		$this->order_totals->sub_total += ( $product->subscription_signup_fee * $quantity );
		$this->order_totals->shipping_total = $shipping_total;

		// Update Custom Order Total
		if ( $product->trial_period_days > 0 ) {
			$this->order_totals->grand_total = ( $product->subscription_signup_fee * $quantity ) + $shipping_total;

		} else {
			$this->order_totals->grand_total = ( ( $product->price + $product->subscription_signup_fee ) * $quantity ) + $option_price_onetime_adjustment - $discount_total + $this->order_totals->tax_total + $this->tax->hst + $this->tax->pst + $this->tax->gst + $shipping_total;
			if ( !$this->tax->vat_included ) {
				 $this->order_totals->grand_total += $this->order_totals->vat_total;
			}
		}

		$card = new ec_credit_card( sanitize_text_field( $_POST['card']['brand'] ), sanitize_text_field( $_POST['card']['name'] ), sanitize_text_field( $_POST['card']['last4'] ), sanitize_text_field( $_POST['card']['exp_month'] ), sanitize_text_field( $_POST['card']['exp_year'] ), sanitize_text_field( $_POST['card']['cvv'] ) );

		$stripe_charge_id = '';
		/* 6.0.2: the subscription's own invoice PaymentIntent, and only once Stripe says it was paid ( a declined charge is a latest_charge too ). */
		$posted_payment_intent = ( isset( $_POST['paymentintent_id'] ) && is_string( $_POST['paymentintent_id'] ) ) ? sanitize_text_field( wp_unslash( $_POST['paymentintent_id'] ) ) : '';
		$stripe_payment_intent = ( '' !== $posted_payment_intent && hash_equals( (string) $binding['payment_intent'], $posted_payment_intent ) ) ? $stripe->get_payment_intent( $posted_payment_intent ) : false;
		if ( $stripe_payment_intent && isset( $stripe_payment_intent->status ) && in_array( $stripe_payment_intent->status, array( 'succeeded', 'processing', 'requires_capture' ), true ) && isset( $stripe_payment_intent->latest_charge ) ) {
			$stripe_charge_id = $stripe_payment_intent->latest_charge;
			$charge = $stripe->get_charge( $stripe_charge_id );
			if ( $charge && isset( $charge->payment_method_details ) && isset( $charge->payment_method_details->type ) ) {
				$payment_method = $charge->payment_method_details->card->brand;
				$last_4 = $charge->payment_method_details->card->last4;
				$exp_month = $charge->payment_method_details->card->exp_month;
				$exp_year = $charge->payment_method_details->card->exp_year;
				$card = new ec_credit_card( $payment_method, sanitize_text_field( $_POST['card']['name'] ), $last_4, $exp_month, $exp_year, '' );
			}

			$order_id = $this->mysqli->insert_subscription_order( 
				$product,
				$GLOBALS['ec_user'],
				$card,
				(int) $_POST['subscription_id'],
				$coupon,
				( isset( $_POST['order_notes'] ) ) ? strip_tags( sanitize_textarea_field( $_POST['order_notes'] ) ) : '',
				$this->subscription_option1_name,
				$this->subscription_option2_name,
				$this->subscription_option3_name,
				$this->subscription_option4_name,
				$this->subscription_option5_name,
				$this->subscription_option1_label,
				$this->subscription_option2_label,
				$this->subscription_option3_label,
				$this->subscription_option4_label,
				$this->subscription_option5_label,
				$quantity,
				$this->order_totals,
				$shipping_method,
				$this->tax,
				$discount_total,
				$stripe_charge_id,
				sanitize_text_field( $_POST['paymentintent_id'] ),
				$option_price_onetime_adjustment
			);
			$this->mysqli->update_user_default_card( $GLOBALS['ec_user'], $card );

			do_action( 'wpeasycart_subscription_first_order_inserted', $order_id );
			do_action( 'wpeasycart_order_paid', $order_id );

			$order_row = $this->mysqli->get_order_row( $order_id, $GLOBALS['ec_cart_data']->cart_data->user_id );
			$order = new ec_orderdisplay( $order_row );
			$order_details = $this->mysqli->get_order_details( $order_id, $GLOBALS['ec_cart_data']->cart_data->user_id );
			$subscription->send_email_receipt( $GLOBALS['ec_user'], $order, $order_details );
			$this->mysqli->update_product_stock( $product->product_id, $quantity );
			$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log( order_id, order_log_key ) VALUES( %d, "order-stock-update" )', $order_id ) );
			$order_log_id = $wpdb->insert_id;
			$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "product_id", %s )', $order_log_id, $order_id, $product->product_id ) );
			$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "quantity", %s )', $order_log_id, $order_id, '-' . $quantity ) );

			if ( $subscription->payment_duration > 0 && $subscription->payment_duration == 1 ) {
				$stripe->cancel_subscription( $GLOBALS['ec_user'], $subscription->stripe_subscription_id );
				$this->mysqli->cancel_stripe_subscription( $subscription->stripe_subscription_id );
			}

			return esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_success', array( 'order_id' => (int) $order_id ) ) );
		} else {
			return esc_url_raw( wpeasycart_links()->get_account_page( 'subscription_details', array( 'subscription_id' => (int) $subscription->subscription_id ) ) );
		}
	}

	public function submit_stripe_quick_subscription( $payment_id ) {
		wpeasycart_session()->handle_session();
		global $wpdb;
		$model_number = sanitize_text_field( $_POST['model_number'] );
		$products = $this->mysqli->get_product_list( $wpdb->prepare( " WHERE product.model_number = %s", $model_number ), "", "", "" );
		$product = new ec_product( $products[0] );
		$this->cart->cart = array( $product );
		$subscription_cart = array();

		$quantity = 1;
		if ( isset( $_POST['ec_quantity'] ) ) {
			$quantity = (int) $_POST['ec_quantity'];
		}

		// Verify Payment Status in Case Already Successful
		if ( get_option( 'ec_option_payment_process_method' ) == 'stripe' ) {
			$stripe = new ec_stripe();
		} else {
			$stripe = new ec_stripe_connect();
		}

		// Handle Option Pricing Plans
		$option_price_adjustment = 0;
		$option_price_onetime_adjustment = 0;
		$option_weight_adjustment = 0;
		$option_weight_onetime_adjustment = 0;
		$subscription_plan_options = array();
		$subscription_plan_quantities = array();
		$optionitem_list = $GLOBALS['ec_options']->get_all_optionitems();
		$is_override_price = false;

		foreach( $optionitem_list as $option_item ) {
			$found = false;
			$check_option = false;
			if ( $option_item->optionitem_id == $this->subscription_option1 || $option_item->optionitem_id == $this->subscription_option2 || $option_item->optionitem_id == $this->subscription_option3 || $option_item->optionitem_id == $this->subscription_option4 || $option_item->optionitem_id == $this->subscription_option5 ) {
				if ( $option_item->optionitem_price > 0 ) {
					$option_plan_exists = false;
					if ( $option_item->stripe_plan_id && '' != $option_item->stripe_plan_id ) {
						$option_plan_exists = $stripe->get_plan( (object) array( 'subscription_unique_id' => $option_item->stripe_plan_id ) );
					}
					if ( 
						! $option_item->stripe_plan_id || 
						'' == $option_item->stripe_plan_id || 
						! $option_plan_exists || 
						in_array( $option_item->stripe_plan_id, $subscription_plan_options ) ||
						$option_plan_exists->amount != (int) ( $option_item->optionitem_price * 100 ) || 
						$option_plan_exists->nickname != wp_easycart_language()->convert_text( $option_item->optionitem_name )
					) {
						$stripe_plan = $stripe->insert_option_as_plan( $product, $option_item );
						if ( $stripe_plan ) {
							$wpdb->query( $wpdb->prepare( "UPDATE ec_optionitem SET stripe_plan_id = %d WHERE optionitem_id = %d", $stripe_plan->id, $option_item->optionitem_id ) );
							$option_item->stripe_plan_id = $stripe_plan->id;
						}
					}
					$option_price_adjustment += $option_item->optionitem_price;
					$option_weight_adjustment += $option_item->optionitem_weight;
					$subscription_plan_options[] = $option_item->stripe_plan_id;
					$subscription_plan_quantities[] = $quantity;
					$subscription_cart[] = (object) array(
						'vat_enabled' => ( $product->vat_rate != 0 ),
						'is_taxable' => $product->is_taxable,
						'item_total' => $option_item->optionitem_price * $quantity,
						'item_discount' => 0,
					);
				}
			}
		}

		if ( $this->subscription_advanced_options ) {
			foreach( $this->subscription_advanced_options as $option ) {
				$option_item = $GLOBALS['ec_options']->get_optionitem( $option['optionitem_id'] );
				if ( $option_item->optionitem_disallow_shipping ) {
					$product->is_shippable = false;
				}
				if ( $option_item && $option_item->optionitem_price > 0 ) {
					$option_plan_exists = false;
					if ( $option_item->stripe_plan_id && '' != $option_item->stripe_plan_id ) {
						$option_plan_exists = $stripe->get_plan( (object) array( 'subscription_unique_id' => $option_item->stripe_plan_id ) );
					}
					if ( 
						! $option_item->stripe_plan_id || 
						'' == $option_item->stripe_plan_id || 
						! $option_plan_exists || 
						in_array( $option_item->stripe_plan_id, $subscription_plan_options ) ||
						$option_plan_exists->amount != (int) ( $option_item->optionitem_price * 100 ) || 
						$option_plan_exists->nickname != wp_easycart_language()->convert_text( $option_item->optionitem_name )
					) {
						$stripe_plan = $stripe->insert_option_as_plan( $product, $option_item );
						if ( $stripe_plan ) {
							$wpdb->query( $wpdb->prepare( "UPDATE ec_optionitem SET stripe_plan_id = %d WHERE optionitem_id = %d", $stripe_plan->id, $option_item->optionitem_id ) );
							$option_item->stripe_plan_id = $stripe_plan->id;
						}
					}
					if ( 'number' == $option['option_type'] ) {
						$option_price_adjustment += ( $option_item->optionitem_price * (int) $option['optionitem_value'] );
						$subscription_plan_quantities[] = (int) $option['optionitem_value'] * $quantity;
						$subscription_cart[] = (object) array(
							'vat_enabled' => ( $product->vat_rate != 0 ),
							'is_taxable' => $product->is_taxable,
							'item_total' => ( $option_item->optionitem_price * (int) $option['optionitem_value'] ) * $quantity,
							'item_discount' => 0,
						);
					} else {
						$option_price_adjustment += $option_item->optionitem_price;
						$subscription_plan_quantities[] = $quantity;
						$subscription_cart[] = (object) array(
							'vat_enabled' => ( $product->vat_rate != 0 ),
							'is_taxable' => $product->is_taxable,
							'item_total' => $option_item->optionitem_price * $quantity,
							'item_discount' => 0,
						);
					}
					$subscription_plan_options[] = $option_item->stripe_plan_id;
				} else if ( $option_item && $option_item->optionitem_price_onetime > 0 ) {
					$option_plan_exists = false;
					if ( $option_item->stripe_plan_id && '' != $option_item->stripe_plan_id ) {
						$option_plan_exists = $stripe->get_plan( (object) array( 'subscription_unique_id' => $option_item->stripe_plan_id ) );
					}
					if ( 
						! $option_item->stripe_plan_id || 
						'' == $option_item->stripe_plan_id || 
						! $option_plan_exists || 
						in_array( $option_item->stripe_plan_id, $subscription_plan_options ) ||
						$option_plan_exists->amount != (int) ( $option_item->optionitem_price_onetime * 100 ) || 
						$option_plan_exists->nickname != wp_easycart_language()->convert_text( $option_item->optionitem_name )
					) {
						$stripe_plan = $stripe->insert_option_as_plan( $product, $option_item );
						if ( $stripe_plan ) {
							$wpdb->query( $wpdb->prepare( "UPDATE ec_optionitem SET stripe_plan_id = %d WHERE optionitem_id = %d", $stripe_plan->id, $option_item->optionitem_id ) );
							$option_item->stripe_plan_id = $stripe_plan->id;
						}
					}
					$option_price_onetime_adjustment += $option_item->optionitem_price_onetime;
					$subscription_plan_quantities[] = 1;
					$subscription_plan_options[] = $option_item->stripe_plan_id;
					$subscription_cart[] = (object) array(
						'vat_enabled' => ( $product->vat_rate != 0 ),
						'is_taxable' => $product->is_taxable,
						'item_total' => $option_item->optionitem_price_onetime,
						'item_discount' => 0,
					);
				} else if ( $option_item && $option_item->optionitem_price_override > 0 ) {
					$product->price = $option_item->optionitem_price_override;
					$product->title .= ' ' . $option_item->optionitem_name;
					$override_stripe_price_ids = $wpdb->get_var( $wpdb->prepare( 'SELECT stripe_price_id FROM ec_option_to_product WHERE product_id = %d AND option_id = %d', $product->product_id, $option_item->option_id ) );
					$option_item->stripe_price_id = '';
					$override_stripe_price_ids_arr = array();
					if ( isset( $override_stripe_price_ids ) && is_string( $override_stripe_price_ids ) && '' != $override_stripe_price_ids ) {
						$override_stripe_price_ids_arr = json_decode( $override_stripe_price_ids );
						if ( is_array( $override_stripe_price_ids_arr ) ) {
							foreach ( $override_stripe_price_ids_arr as $override_stripe_price_ids_arr_item ) {
								if ( is_object( $override_stripe_price_ids_arr_item ) && isset( $override_stripe_price_ids_arr_item->optionitem_id ) && isset( $override_stripe_price_ids_arr_item->stripe_price_id ) && $override_stripe_price_ids_arr_item->optionitem_id == $option_item->optionitem_id ) {
									$option_item->stripe_price_id = $override_stripe_price_ids_arr_item->stripe_price_id;
								}
							}
						}
					}
					if ( '' == $option_item->stripe_price_id ) {
						$stripe_price_new = $stripe->insert_price( $product, $option_item->optionitem_name );
						$option_item->stripe_price_id = $stripe_price_new->id;
						$product->stripe_default_price_id = $stripe_price_new->id;
						$override_stripe_price_ids_arr[] = (object) array(
							'optionitem_id' => $option_item->optionitem_id,
							'stripe_price_id' => $stripe_price_new->id,
						);
						$wpdb->query( $wpdb->prepare( 'UPDATE ec_option_to_product SET stripe_price_id = %s WHERE product_id = %d AND option_id = %d', json_encode( $override_stripe_price_ids_arr ), $product->product_id, $option_item->option_id ) );
					} else {
						$product->stripe_default_price_id = $option_item->stripe_price_id;
						$price_check = $stripe->get_price( $product->stripe_default_price_id );
						if ( ! $price_check ) {
							$stripe_price_new = $stripe->insert_price( $product );
							$option_item->stripe_price_id = $stripe_price_new->id;
							$product->stripe_default_price_id = $stripe_price_new->id;
							$override_stripe_price_ids_new_arr = array();
							foreach ( $override_stripe_price_ids_arr as $override_stripe_price_ids_arr_item ) {
								if ( is_object( $override_stripe_price_ids_arr_item ) && isset( $override_stripe_price_ids_arr_item->optionitem_id ) && isset( $override_stripe_price_ids_arr_item->stripe_price_id ) && $override_stripe_price_ids_arr_item->optionitem_id != $option_item->optionitem_id ) {
									$override_stripe_price_ids_new_arr[] = $override_stripe_price_ids_arr_item;
								}
							}
							$override_stripe_price_ids_new_arr[] = (object) array(
								'optionitem_id' => $option_item->optionitem_id,
								'stripe_price_id' => $stripe_price_new->id,
							);
							$wpdb->query( $wpdb->prepare( 'UPDATE ec_option_to_product SET stripe_price_id = %s WHERE product_id = %d AND option_id = %d', json_encode( $override_stripe_price_ids_new_arr ), $product->product_id, $option_item->option_id ) );
						}
					}
					$is_override_price = true;
				}
				if ( $option_item && $option_item->optionitem_weight > 0 ) {
					if ( 'number' == $option['option_type'] ) {
						$option_weight_adjustment += ( $option_item->optionitem_weight * (int) $option['optionitem_value'] );
					} else {
						$option_weight_adjustment += $option_item->optionitem_weight;
					}
				} else if ( $option_item && $option_item->optionitem_weight_onetime > 0 ) {
					$option_weight_onetime_adjustment += $option_item->optionitem_weight_onetime;
				} else if ( $option_item && $option_item->optionitem_weight_override > -1 ) {
					$product->weight = $option_item->optionitem_weight_override; /* 6.0.2: an override of 0 counts, as on the other subscription steps and in the cart */
				}
			}
		}

		$subscription_cart[] = (object) array(
			'vat_enabled' => ( $product->vat_rate != 0 ),
			'is_taxable' => $product->is_taxable,
			'item_total' => $product->price * $quantity,
			'item_discount' => 0,
		);

		$product->price = $product->price + $option_price_adjustment;

		if ( get_option( 'ec_option_collect_shipping_for_subscriptions' ) && get_option( 'ec_option_use_shipping' ) && $product->is_shippable ) {
			/* 6.0.2: the option prices are already in the price above ( they were counted twice ), and the one-time weights
			   are added to the weight ( the one-time prices were ). */
			$ship_price_total = ( $product->price * $quantity ) + $option_price_onetime_adjustment;
			$ship_weight_total = ( $product->weight + $option_weight_adjustment ) * $quantity + $option_weight_onetime_adjustment;
			$ship_quantity = $quantity;
		} else {
			$ship_price_total = 0;
			$ship_weight_total = 0;
			$ship_quantity = 0;
		}
		
		$product->weight = $ship_weight_total;
		do_action( 'wpeasycart_cart_subscription_updated', $product, $quantity, $ship_weight_total ); /* 6.0.2: the total weight */

		$shipping_method = '';
		if ( get_option( 'ec_option_collect_shipping_for_subscriptions' ) && get_option( 'ec_option_use_shipping' ) && $product->is_shippable ) {
			$this->shipping = new ec_shipping( $ship_price_total, $ship_weight_total, $ship_quantity, 'RADIO', $GLOBALS['ec_user']->freeshipping, $product->length, $product->width, $product->height * $quantity, array( $product ) );
			$this->shipping->change_shipping_js_func = 'ec_cart_subscription_shipping_method_change';
			$this->cart->shippable_total_items = $quantity;
			$handling_total = $product->handling_price + ( $product->handling_price_each * $quantity );
			$shipping_total = floatval( $this->shipping->get_shipping_price( $handling_total ) );
			$this->order_totals->shipping_total = $shipping_total;
			if ( !get_option( 'ec_option_use_shipping' ) || $shipping_total <= 0 ) {
				$shipping_method = "";
			} else if ( $this->shipping->shipping_method == "fraktjakt" ) {
				$shipping_method = $this->shipping->get_selected_shipping_method();
			} else if ( $GLOBALS['ec_cart_data']->cart_data->shipping_method != "" && $GLOBALS['ec_cart_data']->cart_data->shipping_method != "standard" ) {
				$shipping_method = $this->mysqli->get_shipping_method_name( $GLOBALS['ec_cart_data']->cart_data->shipping_method );
			} else if ( ( $this->shipping->shipping_method == "price" || $this->shipping->shipping_method == "weight" ) && $GLOBALS['ec_cart_data']->cart_data->expedited_shipping != "" ) {
				$shipping_method = wp_easycart_language()->get_text( "cart_estimate_shipping", "cart_estimate_shipping_express" );
			} else {
				$shipping_method = wp_easycart_language()->get_text( "cart_estimate_shipping", "cart_estimate_shipping_standard" );
			}
			$subscription_cart[] = (object) array(
				'vat_enabled' => ! get_option( 'ec_option_no_vat_on_shipping' ),
				'is_taxable' => get_option( 'ec_option_collect_tax_on_shipping' ),
				'item_total' => $shipping_total,
				'item_discount' => 0,
			);

		} else {
			$handling_total = 0;
			$shipping_total = 0;
			$this->cart->shippable_total_items = 0;
			$this->order_totals->shipping_total = 0;
		}

		$stripe_shipping_plan_id = false;
		if ( $product->is_shippable && $product->subscription_shipping_recurring && 0 < $shipping_total ) {
			$stripe_shipping_plan = $stripe->insert_shipping_as_plan( $product, $shipping_total );
			$stripe_shipping_plan_id = ( isset( $stripe_shipping_plan->id ) ) ? $stripe_shipping_plan->id : false;
		}

		// Coupon Information
		$coupon = NULL;
		$discount_total = 0;
		$is_match = false;
		$shipping_discount = 0;

		$wpec_coupon = false;
		if ( isset( $_POST['coupon_code'] ) && $_POST['coupon_code'] != "" ) {
			/* 6.0.2: the code box's rules ( subscription_checkout_coupon() ). An Offers code names its own Stripe coupon; the
			 * order still records the code typed. */
			$wpec_coupon = self::subscription_checkout_coupon( sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) ), $products[0]['product_id'], $products[0]['manufacturer_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- ec_ajax_get_stripe_create_subscription() checked its nonce.
			if ( $wpec_coupon ) {
				$coupon_row = $wpec_coupon['row'];
				$is_match = true;
				$coupon = sanitize_text_field( $wpec_coupon['stripe'] );
			}
		}
		// Coupon Check
		if ( $coupon ) {

			/* 6.0.2: a percent off also takes its share of shipping charged once ( as the page shows it ), every time, not
			 * only when the Stripe coupon is first made. */
			if ( ! $wpec_coupon['amount_off'] ) {
				$shipping_discount = round( $shipping_total * ( $coupon_row->promo_percentage / 100 ), 2 );
			}

			$coupon_exists = $stripe->get_coupon( $coupon );

			// Insert Coupon ( 6.0.2: its terms from subscription_checkout_coupon() )
			if ( $coupon_exists === false ) {
				$stripe_coupon_response = $stripe->insert_coupon( array_merge( $wpec_coupon['stripe_coupon'], array( 'promocode_id' => $coupon ) ) );
				if ( $stripe_coupon_response === false ) {
					return array( 'error' => 'coupon_failed' );
				}
			}

		}
		// END COUPON CHECK

		// BEGIN PROMOTIONS CHECK
		if ( !$coupon && $product->has_promotion_text() ) {
			$promotion_exists = $stripe->get_coupon( preg_replace( "/[^A-Za-z0-9_\-]/", "", strtoupper( $product->promotion_text ) ) );
			$promotions = $GLOBALS['ec_promotions']->promotions;
			$applicable_promotion = false;
			for( $i=0; $i<count( $promotions ); $i++ ) {
				if ( $product->promotion_text == $promotions[$i]->promotion_name ) {
					$applicable_promotion = $promotions[$i];
				}
			}
			$promotion_code = "";
			if ( $applicable_promotion ) {
				// Insert Coupon
				$promotion_code = $coupon = preg_replace( "/[^A-Za-z0-9_\-]/", "", strtoupper( $applicable_promotion->promotion_name ) );
				// Promotion Not Added OR Coupon no Longer Matches Promotion
				if ( 
					$promotion_exists === false || 
					( $applicable_promotion->price1 > 0 && !$promotion_exists->amount_off ) || 
					( $applicable_promotion->price1 <= 0 && !$promotion_exists->percent_off ) || 
					( $applicable_promotion->price1 > 0 && (int) ( $applicable_promotion->price1 * 100 ) != $promotion_exists->amount_off ) || 
					( $applicable_promotion->price1 <= 0 && $applicable_promotion->percentage1 != $promotion_exists->percent_off )
				) {
					$is_amount_off = false;
					if ( $applicable_promotion->price1 > 0 ) {
						$is_amount_off = true;
					} else {
						$shipping_discount = round( $shipping_total * ( $applicable_promotion->percentage1 / 100 ), 2 );
					}
					$redeem_by = strtotime( $applicable_promotion->end_date ) + 7*60*60;
					$stripe_coupon = array(
						"promocode_id"		=> $promotion_code,
						"duration"			=> 'once',
						"is_amount_off"		=> $is_amount_off,
						"amount_off"		=> $applicable_promotion->price1 * 100,
						"percent_off"		=> $applicable_promotion->percentage1,
						"redeem_by"			=> $redeem_by,
						"max_redemptions"	=> 999
					);
					if ( $promotion_exists ) {
						$stripe->delete_coupon( $stripe_coupon['promocode_id'] );
					}
					$stripe_coupon_response = $stripe->insert_coupon( $stripe_coupon );
					if ( $stripe_coupon_response === false ) {
						return array( 'error' => 'coupon_failed' );
					}
				}
			}
		}
		// END PROMOTIONS CHECK

		$is_subscriber = 0;
		if ( $_POST['is_subscriber'] == 1 ) {
			$is_subscriber = 1;
		}
		$is_subscriber_saved = false; // 6.0.2: set once a new account below has subscribed a ticked box itself.

		// CREATE ACCOUNT IF NEEDED
		if ( isset( $_POST['create_account'] ) ) {

			if ( wp_easycart_wordpress_users::email_taken( sanitize_email( $_POST['billing_details']['email'] ) ) ) {
				return array( 'error' => array( 
					'id'		=> 'user_create_error',
					'message'	=> wp_easycart_language()->get_text( "ec_errors", "email_exists_error" )
				) );

			} else {
				$password = wp_easycart_hash_password( $_POST['create_account']['password'] ); // XSS OK. Password Hashed Immediately
				$password = apply_filters( 'wpeasycart_password_hash', $password, $_POST['create_account']['password'] ); // XSS OK. Password should not be hashed.

				$billing_id = $this->mysqli->insert_address( 
					sanitize_text_field( $_POST['billing_details']['first_name'] ), 
					sanitize_text_field( $_POST['billing_details']['last_name'] ), 
					sanitize_text_field( $_POST['billing_details']['address']['line1'] ), 
					sanitize_text_field( $_POST['billing_details']['address']['line2'] ), 
					sanitize_text_field( $_POST['billing_details']['address']['city'] ), 
					sanitize_text_field( $_POST['billing_details']['address']['state'] ), 
					sanitize_text_field( $_POST['billing_details']['address']['postal_code'] ), 
					sanitize_text_field( $_POST['billing_details']['address']['country'] ), 
					sanitize_text_field( $_POST['billing_details']['phone'] ), 
					sanitize_text_field( $_POST['billing_details']['company_name'] ) 
				);

				$shipping_id = $this->mysqli->insert_address( 
					sanitize_text_field( $_POST['shipping_details']['first_name'] ), 
					sanitize_text_field( $_POST['shipping_details']['last_name'] ), 
					sanitize_text_field( $_POST['shipping_details']['address']['line1'] ), 
					sanitize_text_field( $_POST['shipping_details']['address']['line2'] ), 
					sanitize_text_field( $_POST['shipping_details']['address']['city'] ), 
					sanitize_text_field( $_POST['shipping_details']['address']['state'] ), 
					sanitize_text_field( $_POST['shipping_details']['address']['postal_code'] ), 
					sanitize_text_field( $_POST['shipping_details']['address']['country'] ), 
					sanitize_text_field( $_POST['shipping_details']['phone'] ), 
					sanitize_text_field( $_POST['shipping_details']['company_name'] ) 
				);

				$user_id = $this->mysqli->insert_user( 
					sanitize_email( $_POST['billing_details']['email'] ),
					$password,
					sanitize_text_field( $_POST['billing_details']['first_name'] ),
					sanitize_text_field( $_POST['billing_details']['last_name'] ),
					$billing_id,
					$shipping_id,
					"shopper",
					$is_subscriber,
					"",
					preg_replace( '[^a-zA-Z0-9\s]', '', sanitize_text_field( $_POST['vat_registration_number'] ) )
				);
				$GLOBALS['ec_cart_data']->cart_data->user_id = $user_id;

				$is_subscriber_saved = (bool) $is_subscriber;
				$this->mysqli->update_address_user_id( $billing_id, $user_id );
				$this->mysqli->update_address_user_id( $shipping_id, $user_id );

				do_action( 'wpeasycart_account_added', $user_id, sanitize_email( $_POST['billing_details']['email'] ), $_POST['create_account']['password'], 'subscription' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- passwords are used as typed, never sanitized ( 6.0.2 ).

				// WordPress User Sync 1.x: the WordPress user ( 2.0 makes it on wpeasycart_account_added ).
				wp_easycart_wordpress_users::legacy_account_created( $user_id, sanitize_email( $_POST['billing_details']['email'] ), $_POST['create_account']['password'], sanitize_text_field( $_POST['billing_details']['first_name'] ), sanitize_text_field( $_POST['billing_details']['last_name'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- passwords are used as typed, never sanitized ( 6.0.2 ).

				// Send registration email if needed
				if ( get_option( 'ec_option_send_signup_email' ) ) {

					$headers   = array();
					$headers[] = "MIME-Version: 1.0";
					$headers[] = "Content-Type: text/html; charset=utf-8";
					$headers[] = "From: " . stripslashes( get_option( 'ec_option_order_from_email' ) );
					$headers[] = "Reply-To: " . stripslashes( get_option( 'ec_option_order_from_email' ) );
					$headers[] = "X-Mailer: PHP/" . phpversion();

					$message = wp_easycart_account_register_admin_email_html( $email ); // 6.0.0: shared email design.

					if ( get_option( 'ec_option_use_wp_mail' ) ) {
						wp_mail( stripslashes( get_option( 'ec_option_bcc_email_addresses' ) ), wp_easycart_language()->get_text( "account_register", "account_register_email_title" ), $message, implode("\r\n", $headers) );
					} else {
						$admin_email = stripslashes( get_option( 'ec_option_bcc_email_addresses' ) );
						$subject = wp_easycart_language()->get_text( "account_register", "account_register_email_title" );
						$mailer = new wpeasycart_mailer();
						$mailer->send_order_email( $admin_email, $subject, $message );
					}

				}

				$GLOBALS['ec_cart_data']->cart_data->is_guest = false;
				$GLOBALS['ec_cart_data']->cart_data->user_id = $user_id;
				$GLOBALS['ec_cart_data']->cart_data->email = sanitize_email( $_POST['billing_details']['email'] );
				$GLOBALS['ec_cart_data']->cart_data->username = sanitize_text_field( $_POST['billing_details']['first_name'] . ' ' . $_POST['billing_details']['last_name'] );
				$GLOBALS['ec_cart_data']->cart_data->first_name = sanitize_text_field( $_POST['billing_details']['first_name'] );
				$GLOBALS['ec_cart_data']->cart_data->last_name = sanitize_text_field( $_POST['billing_details']['last_name'] );

				$GLOBALS['ec_user'] = new ec_user( "" );
			}

		} else { // Customer already exists, lets update their billing address
			$GLOBALS['ec_user']->vat_registration_number = preg_replace( '/[^a-zA-Z0-9\s]/', '', sanitize_text_field( $_POST['vat_registration_number'] ) );
			$this->mysqli->update_user( $GLOBALS['ec_user']->user_id, preg_replace( '/[^a-zA-Z0-9\s]/', '', sanitize_text_field( $_POST['vat_registration_number'] ) ) );
			if ( $GLOBALS['ec_user']->billing_id == 0 ) {
				$billing_id = $this->mysqli->insert_address( 
					sanitize_text_field( $_POST['billing_details']['first_name'] ),
					sanitize_text_field( $_POST['billing_details']['last_name'] ),
					sanitize_text_field( $_POST['billing_details']['address']['line1'] ),
					sanitize_text_field( $_POST['billing_details']['address']['line2'] ),
					sanitize_text_field( $_POST['billing_details']['address']['city'] ),
					sanitize_text_field( $_POST['billing_details']['address']['state'] ),
					sanitize_text_field( $_POST['billing_details']['address']['postal_code'] ),
					sanitize_text_field( $_POST['billing_details']['address']['country'] ),
					sanitize_text_field( $_POST['billing_details']['phone'] ),
					sanitize_text_field( $_POST['billing_details']['company_name'] )
				);
				$wpdb->query( $wpdb->prepare( "UPDATE ec_user SET default_billing_address_id = %d WHERE user_id = %d", $billing_id, $GLOBALS['ec_user']->user_id ) );
				$this->mysqli->update_address_user_id( $billing_id, $GLOBALS['ec_user']->user_id );

			} else {
				$this->mysqli->update_address( 
					$GLOBALS['ec_user']->billing_id, 
					sanitize_text_field( $_POST['billing_details']['first_name'] ), 
					sanitize_text_field( $_POST['billing_details']['last_name'] ), 
					sanitize_text_field( $_POST['billing_details']['address']['line1'] ), 
					sanitize_text_field( $_POST['billing_details']['address']['line2'] ), 
					sanitize_text_field( $_POST['billing_details']['address']['city'] ), 
					sanitize_text_field( $_POST['billing_details']['address']['state'] ), 
					sanitize_text_field( $_POST['billing_details']['address']['postal_code'] ), 
					sanitize_text_field( $_POST['billing_details']['address']['country'] ), 
					sanitize_text_field( $_POST['billing_details']['phone'] ), 
					sanitize_text_field( $_POST['billing_details']['company_name'] ) 
				);
			}
			if ( $_POST['shipping_details']['first_name'] != '' ) {
				if ( $GLOBALS['ec_user']->shipping_id == 0 ) {
					$shipping_id = $this->mysqli->insert_address( 
						sanitize_text_field( $_POST['shipping_details']['first_name'] ), 
						sanitize_text_field( $_POST['shipping_details']['last_name'] ), 
						sanitize_text_field( $_POST['shipping_details']['address']['line1'] ), 
						sanitize_text_field( $_POST['shipping_details']['address']['line2'] ), 
						sanitize_text_field( $_POST['shipping_details']['address']['city'] ), 
						sanitize_text_field( $_POST['shipping_details']['address']['state'] ), 
						sanitize_text_field( $_POST['shipping_details']['address']['postal_code'] ), 
						sanitize_text_field( $_POST['shipping_details']['address']['country'] ), 
						sanitize_text_field( $_POST['shipping_details']['phone'] ), 
						sanitize_text_field( $_POST['shipping_details']['company_name'] )
					);
					$wpdb->query( $wpdb->prepare( "UPDATE ec_user SET default_shipping_address_id = %d WHERE user_id = %d", $shipping_id, $GLOBALS['ec_user']->user_id ) );
					$this->mysqli->update_address_user_id( $shipping_id, $GLOBALS['ec_user']->user_id );

				} else {
					$this->mysqli->update_address( 
						$GLOBALS['ec_user']->shipping_id, 
						sanitize_text_field( $_POST['shipping_details']['first_name'] ), 
						sanitize_text_field( $_POST['shipping_details']['last_name'] ), 
						sanitize_text_field( $_POST['shipping_details']['address']['line1'] ), 
						sanitize_text_field( $_POST['shipping_details']['address']['line2'] ), 
						sanitize_text_field( $_POST['shipping_details']['address']['city'] ), 
						sanitize_text_field( $_POST['shipping_details']['address']['state'] ), 
						sanitize_text_field( $_POST['shipping_details']['address']['postal_code'] ), 
						sanitize_text_field( $_POST['shipping_details']['address']['country'] ), 
						sanitize_text_field( $_POST['shipping_details']['phone'] ), 
						sanitize_text_field( $_POST['shipping_details']['company_name'] )
					);
				}

			}
		}

		// Set Sessions
		$GLOBALS['ec_cart_data']->cart_data->billing_first_name = sanitize_text_field( $_POST['billing_details']['first_name'] );
		$GLOBALS['ec_cart_data']->cart_data->billing_last_name = sanitize_text_field( $_POST['billing_details']['last_name'] );
		$GLOBALS['ec_cart_data']->cart_data->billing_company_name = sanitize_text_field( $_POST['billing_details']['company_name'] );
		$GLOBALS['ec_cart_data']->cart_data->billing_address_line_1 = sanitize_text_field( $_POST['billing_details']['address']['line1'] );
		$GLOBALS['ec_cart_data']->cart_data->billing_address_line_2 = sanitize_text_field( $_POST['billing_details']['address']['line2'] );
		$GLOBALS['ec_cart_data']->cart_data->billing_city = sanitize_text_field( $_POST['billing_details']['address']['city'] );
		$GLOBALS['ec_cart_data']->cart_data->billing_state = sanitize_text_field( $_POST['billing_details']['address']['state'] );
		$GLOBALS['ec_cart_data']->cart_data->billing_zip = sanitize_text_field( $_POST['billing_details']['address']['postal_code'] );
		$GLOBALS['ec_cart_data']->cart_data->billing_country = sanitize_text_field( $_POST['billing_details']['address']['country'] );
		$GLOBALS['ec_cart_data']->cart_data->billing_phone = sanitize_text_field( $_POST['billing_details']['phone'] );

		$GLOBALS['ec_cart_data']->cart_data->shipping_selector = ( isset( $_POST['shipping_details']['address']['first_name'] ) && strlen( sanitize_text_field( $_POST['shipping_details']['address']['first_name'] ) ) > 0) ? 1 : 0;

		$GLOBALS['ec_cart_data']->cart_data->shipping_first_name = sanitize_text_field( $_POST['shipping_details']['first_name'] );
		$GLOBALS['ec_cart_data']->cart_data->shipping_last_name = sanitize_text_field( $_POST['shipping_details']['last_name'] );
		$GLOBALS['ec_cart_data']->cart_data->shipping_company_name = sanitize_text_field( $_POST['shipping_details']['company_name'] );
		$GLOBALS['ec_cart_data']->cart_data->shipping_address_line_1 = sanitize_text_field( $_POST['shipping_details']['address']['line1'] );
		$GLOBALS['ec_cart_data']->cart_data->shipping_address_line_2 = sanitize_text_field( $_POST['shipping_details']['address']['line2'] );
		$GLOBALS['ec_cart_data']->cart_data->shipping_city = sanitize_text_field( $_POST['shipping_details']['address']['city'] );
		$GLOBALS['ec_cart_data']->cart_data->shipping_state = sanitize_text_field( $_POST['shipping_details']['address']['state'] );
		$GLOBALS['ec_cart_data']->cart_data->shipping_zip = sanitize_text_field( $_POST['shipping_details']['address']['postal_code'] );
		$GLOBALS['ec_cart_data']->cart_data->shipping_country = sanitize_text_field( $_POST['shipping_details']['address']['country'] );
		$GLOBALS['ec_cart_data']->cart_data->shipping_phone = sanitize_text_field( $_POST['shipping_details']['phone'] );

		$GLOBALS['ec_cart_data']->cart_data->first_name = ( isset( $_POST['create_account']['first_name'] ) ) ? sanitize_text_field( $_POST['create_account']['first_name'] ) : $GLOBALS['ec_cart_data']->cart_data->billing_first_name;
		$GLOBALS['ec_cart_data']->cart_data->last_name = ( isset( $_POST['create_account']['last_name'] ) ) ? sanitize_text_field( $_POST['create_account']['last_name'] ) : $GLOBALS['ec_cart_data']->cart_data->billing_last_name;

		$GLOBALS['ec_cart_data']->cart_data->order_notes = sanitize_textarea_field( $_POST['order_notes'] );
		$GLOBALS['ec_cart_data']->cart_data->email_other = sanitize_text_field( ( isset( $_POST['ec_email_other'] ) ) ? $_POST['ec_email_other'] : '' );

		$GLOBALS['ec_cart_data']->save_session_to_db();

		/* 6.0.2: an extension's reason not to start this subscription ( e.g. an age check ), before anything is charged. */
		$wpec_subscription_errors = $this->subscription_errors( $product, $quantity );
		if ( $wpec_subscription_errors ) {
			return array(
				'error' => array(
					'id'      => $wpec_subscription_errors[0]['code'],
					'message' => $wpec_subscription_errors[0]['message'],
				),
			);
		}

		$GLOBALS['ec_user']->setup_billing_info_data(
			$GLOBALS['ec_cart_data']->cart_data->billing_first_name,
			$GLOBALS['ec_cart_data']->cart_data->billing_last_name,
			$GLOBALS['ec_cart_data']->cart_data->billing_address_line_1,
			$GLOBALS['ec_cart_data']->cart_data->billing_address_line_2,
			$GLOBALS['ec_cart_data']->cart_data->billing_city,
			$GLOBALS['ec_cart_data']->cart_data->billing_state,
			$GLOBALS['ec_cart_data']->cart_data->billing_country,
			$GLOBALS['ec_cart_data']->cart_data->billing_zip,
			$GLOBALS['ec_cart_data']->cart_data->billing_phone,
			$GLOBALS['ec_cart_data']->cart_data->billing_company_name
		);
		$GLOBALS['ec_user']->setup_shipping_info_data(
			$GLOBALS['ec_cart_data']->cart_data->shipping_first_name,
			$GLOBALS['ec_cart_data']->cart_data->shipping_last_name,
			$GLOBALS['ec_cart_data']->cart_data->shipping_address_line_1,
			$GLOBALS['ec_cart_data']->cart_data->shipping_address_line_2,
			$GLOBALS['ec_cart_data']->cart_data->shipping_city,
			$GLOBALS['ec_cart_data']->cart_data->shipping_state,
			$GLOBALS['ec_cart_data']->cart_data->shipping_country,
			$GLOBALS['ec_cart_data']->cart_data->shipping_zip,
			$GLOBALS['ec_cart_data']->cart_data->shipping_phone,
			$GLOBALS['ec_cart_data']->cart_data->shipping_company_name
		);

		if ( $is_subscriber ) {
			if ( ! $is_subscriber_saved ) {
				// Fires wpeasycart_insert_subscriber ( 6.0.2: once, not again here or by a new account's insert_user() ).
				$this->mysqli->insert_subscriber(
					sanitize_email( $GLOBALS['ec_user']->email ),
					sanitize_text_field( $_POST['billing_details']['first_name'] ),
					sanitize_text_field( $_POST['billing_details']['last_name'] )
				);
			}

			if ( function_exists( 'mailster' ) ) {
				$subscriber_id = mailster('subscribers')->add(array(
					'firstname' => sanitize_text_field( $_POST['billing_details']['first_name'] ),
					'lastname' => sanitize_text_field( $_POST['billing_details']['last_name'] ),
					'email' => sanitize_email( $GLOBALS['ec_user']->email ),
					'status' => $is_subscriber,
				), false );
			}
		}
		// END SUBSCRIBER

		// Possibly discount the initial fee
		$initial_fee = $product->subscription_signup_fee * $quantity;
		if ( $discount_total > $product->price + $option_price_onetime_adjustment ) {
			$remaining_discount = $discount_total - $product->price + $option_price_onetime_adjustment;
			$initial_fee = $initial_fee - $remaining_discount;
		}

		$customer_id = $GLOBALS['ec_user']->stripe_customer_id;

		$this->cart->subtotal = ( ( $product->price + $option_price_adjustment ) * $quantity ) + $option_price_onetime_adjustment;
		for ( $i = 0; $i < count( $subscription_cart ); $i++ ) {
			if ( $this->cart->subtotal + $shipping_total > 0 ) {
				$subscription_cart[$i]->item_total = $subscription_cart[$i]->item_total - round( ( $subscription_cart[$i]->item_total / ( $this->cart->subtotal + $shipping_total ) ) * $discount_total, 2 );
			}
		}

		if ( $product->is_taxable || $product->vat_rate ) {
			$taxable_subtotal = 0;
			$vatable_subtotal = 0;
			if ( $product->is_taxable ) {
				$taxable_subtotal = $product->price * $quantity + $option_price_onetime_adjustment - $discount_total;
			}
			if ( $product->vat_rate ) {
				$vatable_subtotal = $product->price * $quantity + $option_price_onetime_adjustment - $discount_total;
			}

			do_action( 'wpeasycart_cart_subscription_pre_tax', $product, $quantity, $shipping_total, $handling_total, $discount_total );

			if ( get_option( 'ec_option_tax_cloud_api_id' ) != "" && get_option( 'ec_option_tax_cloud_api_key' ) != "" ) {
				wpeasycart_taxcloud()->setup_subscription_for_tax( $product, $quantity, $discount_total, 0, $option_price_onetime_adjustment );
			}
			if ( function_exists( 'wpeasycart_taxjar' ) && wpeasycart_taxjar()->is_enabled() ) {
				wpeasycart_taxjar()->setup_subscription_for_tax( $product, $quantity, $discount_total, 0, $option_price_onetime_adjustment );
			}
			$this->tax = new ec_tax( $product->price * $quantity + $option_price_onetime_adjustment, $taxable_subtotal, $vatable_subtotal, sanitize_text_field( $_POST['billing_details']['address']['state'] ), sanitize_text_field( $_POST['billing_details']['address']['country'] ), $GLOBALS['ec_user']->taxfree, $this->shipping->get_shipping_price( ( $product->handling_price_each * $quantity ) + $product->handling_price ), $subscription_cart, true );
		} else {
			$this->tax = new ec_tax( 0, 0, 0, sanitize_text_field( $_POST['billing_details']['address']['state'] ), sanitize_text_field( $_POST['billing_details']['address']['country'] ), $GLOBALS['ec_user']->taxfree, $this->shipping->get_shipping_price( ( $product->handling_price_each * $quantity ) + $product->handling_price ), $subscription_cart, true );
		}

		$this->order_totals = new ec_order_totals( $this->cart, $GLOBALS['ec_user'], $this->shipping, $this->tax, $this->discount );
		if ( class_exists( 'wp_easycart_tax_providers' ) && wp_easycart_tax_providers::blocks_checkout( 'subscription' ) ) {
			return array( 'error' => 'tax_unavailable' ); /* 6.0.2: the tax service could not work out the tax */
		}

		$need_to_update_customer_id = false;
		$customer_insert_test = false;

		$customer_balance_adj = $initial_fee;
		if ( ! $product->subscription_shipping_recurring ) {
			$customer_balance_adj += $shipping_total + $this->tax->shipping_tax_total + $this->tax->shipping_vat_total - $shipping_discount;
		}

		if ( $customer_id == "" ) {
			$customer_id = $stripe->insert_customer( $GLOBALS['ec_user'], NULL, $customer_balance_adj );
			$need_to_update_customer_id = true;

		} else {
			$found_customer = $stripe->update_customer( $GLOBALS['ec_user'], $customer_balance_adj );
			if ( !$found_customer ) { // Likely switched from test to live or to a new account, so customer id was wrong
				$customer_id = $stripe->insert_customer( $GLOBALS['ec_user'], NULL, $customer_balance_adj );
				$need_to_update_customer_id = true;
			}
		}

		if ( $need_to_update_customer_id && $customer_id ) { // Customer inserted to stripe successfully
			$this->mysqli->update_user_stripe_id( $GLOBALS['ec_user']->user_id, $customer_id );
			$GLOBALS['ec_user']->stripe_customer_id = $customer_id;
			$customer_insert_test = true;
		} else if ( $need_to_update_customer_id && !$customer_id ) {
			$customer_insert_test = false;
		} else {
			$customer_insert_test = true;
		}

		// Failed to Insert/Update Customer
		if ( ! $customer_insert_test ) { 
			return array( 'error' => 'customer_error' );
		}

		$is_sandbox = apply_filters( 'wp_easycart_is_stripe_sandbox', false );
		$product_check = $stripe->get_product( $product->stripe_product_id );
		if ( ! $product_check ) {
			$stripe_product_new = $stripe->insert_product( $product );
			$product->stripe_product_id = $stripe_product_new->id;
			$product->stripe_default_price_id = $stripe_product_new->default_price;
			if ( ! $is_sandbox ) {
				$wpdb->query( $wpdb->prepare( 'UPDATE ec_product SET stripe_product_id = %s, stripe_default_price_id = %s WHERE product_id = %d', $stripe_product_new->id, $stripe_product_new->default_price, $product->product_id ) );
			} else {
				$wpdb->query( $wpdb->prepare( 'UPDATE ec_product SET stripe_product_id_sandbox = %s, stripe_default_price_id_sandbox = %s WHERE product_id = %d', $stripe_product_new->id, $stripe_product_new->default_price, $product->product_id ) );
			}
		} else {
			$price_check = $stripe->get_price( $product->stripe_default_price_id );
			if ( ! $price_check ) {
				$stripe_price_new = $stripe->insert_price( $product );
				$product->stripe_default_price_id = $stripe_price_new->id;
				if ( ! $is_sandbox ) {
					$wpdb->query( $wpdb->prepare( 'UPDATE ec_product SET stripe_default_price_id = %s WHERE product_id = %d', $stripe_price_new->id, $product->product_id ) );
				} else {
					$wpdb->query( $wpdb->prepare( 'UPDATE ec_product SET stripe_default_price_id_sandbox = %s WHERE product_id = %d', $stripe_price_new->id, $product->product_id ) );
				}
			}
		}

		// Add customer to payment intent
		$card_response = $stripe->insert_card( $GLOBALS['ec_user'], sanitize_text_field( $_POST['stripeToken'] ) );
		if ( ! $card_response ) {
			return array( 'error' => 'payment_fail' );
		}
		$default_response = $stripe->set_default_payment_method( $card_response, $GLOBALS['ec_user'] );

		$prorate = $product->subscription_prorate;
		$trial_end_date = NULL;
		if ( $product->trial_period_days > 0 ) {
			$trial_end_date = strtotime( "+" . $product->trial_period_days . " days" );
		}
		if ( $product->price * $quantity + $option_price_onetime_adjustment - $discount_total > 0 ) {
			$tax_rates = $this->tax->get_stripe_tax_rates(  $product->is_taxable, ( $product->vat_rate > 0 ), ( $this->order_totals->tax_total / ( $product->price * $quantity + $option_price_onetime_adjustment - $discount_total ) ) );
			$tax_rates = apply_filters( 'wp_easycart_subscription_tax_rates_pre_insert', $tax_rates, $product, ( $product->vat_rate > 0 ), ( $this->order_totals->tax_total / ( $product->price * $quantity + $option_price_onetime_adjustment - $discount_total ) ), $this->order_totals->sub_total, $shipping_total );
		} else {
			$tax_rates = array();
		}
		$stripe_response = $stripe->insert_subscription( $product, $GLOBALS['ec_user'], $card_response, $coupon, $prorate, $trial_end_date, $quantity, number_format( $this->tax->get_tax_rate(), 2, '.', '' ), $subscription_plan_options, $tax_rates, $subscription_plan_quantities, $stripe_shipping_plan_id );
		if ( $stripe_response === false ) {
			return array( 'error' => 'subscription_fail' );
		}

		$subscription_id = $this->mysqli->insert_stripe_subscription( $stripe_response, $product, $GLOBALS['ec_user'], NULL, $quantity );
		do_action( 'wp_easycart_subscription_started', $subscription_id );

		/* 6.0.2: submit_stripe_quick_subscription_payment() records the first payment only for this subscription and its invoice's PaymentIntent. */
		set_transient(
			'wpec_sub_pay_' . md5( $GLOBALS['ec_cart_data']->ec_cart_id ),
			array(
				'subscription_id' => (int) $subscription_id,
				'payment_intent'  => ( isset( $stripe_response->latest_invoice->payment_intent->id ) ) ? (string) $stripe_response->latest_invoice->payment_intent->id : '',
			),
			HOUR_IN_SECONDS
		);

		return array( 
			'subscription_id' 	=> $subscription_id,
			'status'			=> $stripe_response->latest_invoice->status,
			'clientSecret'		=> $stripe_response->latest_invoice->payment_intent->client_secret,
			'paymentintent_id'	=> $stripe_response->latest_invoice->payment_intent->id,
			'stripe_charge_id'	=> $stripe_response->latest_invoice->charge
		);
	}

	public function submit_paypal_order( $order_status = 10 ) {
		global $wpdb;
		$ec_db_admin = new ec_db_admin();

		$this->order->submit_order( "third_party" );
		$wpdb->query( $wpdb->prepare( "UPDATE ec_order SET orderstatus_id = %d WHERE order_id = %d", $order_status, $this->order->order_id ) );

		// Log order status update
		$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log( order_id, order_log_key ) VALUES( %d, "order-status-update" )', $this->order->order_id ) );
		$order_log_id = $wpdb->insert_id;
		$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "orderstatus_id", %s )', $order_log_id, $this->order->order_id, $order_status ) );

		// Maybe send email receipts
		if ( $order_status == 10 ) {
			$order_row = $ec_db_admin->get_order_row_admin( $this->order->order_id );
			$orderdetails = $ec_db_admin->get_order_details_admin( $this->order->order_id );

			/* Update Stock Quantity */
			foreach( $orderdetails as $orderdetail ) {
				$product = $wpdb->get_row( $wpdb->prepare( "SELECT ec_product.* FROM ec_product WHERE ec_product.product_id = %d", $orderdetail->product_id ) );
				if ( $product ) {
					if ( $product->use_optionitem_quantity_tracking ) {
						$ec_db_admin->update_quantity_value( $orderdetail->quantity, $orderdetail->product_id, $orderdetail->optionitem_id_1, $orderdetail->optionitem_id_2, $orderdetail->optionitem_id_3, $orderdetail->optionitem_id_4, $orderdetail->optionitem_id_5 );
					}
					$ec_db_admin->update_product_stock( $orderdetail->product_id, $orderdetail->quantity );
					$this->mysqli->update_details_stock_adjusted( $orderdetail->orderdetail_id );
					$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log( order_id, order_log_key ) VALUES( %d, "order-stock-update" )', $this->order->order_id ) );
					$order_log_id = $wpdb->insert_id;
					$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "product_id", %s )', $order_log_id, $this->order->order_id, $orderdetail->product_id ) );
					$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "quantity", %s )', $order_log_id, $this->order->order_id, '-' . $orderdetail->quantity ) );
				}
			}

			// Update Order Status/Send Alerts
			do_action( 'wpeasycart_order_paid', $this->order->order_id );

			// send email
			$order_display = new ec_orderdisplay( $order_row, true, true );
			$order_display->send_email_receipt();
			$order_display->send_gift_cards();
		} else {
			do_action( 'wpeasycart_order_complete', $this->order->order_id, $order_status );
		}

		// Clear tempcart
		$ec_db_admin->clear_tempcart( $GLOBALS['ec_cart_data']->ec_cart_id );
		$this->order->clear_session();

		return $this->order->order_id;
	}

	public function update_authorized_paypal_order( $paypal_response ) {

		if ( isset( $_GET['ec_firstpage'] ) ) {

			if ( isset( $paypal_response->payer ) )
				$payer_info = $paypal_response->payer->payer_info;
			else
				$payer_info = $paypal_response->payer_info;

			$GLOBALS['ec_cart_data']->cart_data->billing_first_name = sanitize_text_field( $payer_info->first_name );
			$GLOBALS['ec_cart_data']->cart_data->billing_last_name = sanitize_text_field( $payer_info->last_name );
			$GLOBALS['ec_cart_data']->cart_data->billing_address_line_1 = sanitize_text_field( $payer_info->shipping_address->line1 );
			$GLOBALS['ec_cart_data']->cart_data->billing_address_line_2 = sanitize_text_field( $payer_info->shipping_address->line2 );
			$GLOBALS['ec_cart_data']->cart_data->billing_city = sanitize_text_field( $payer_info->shipping_address->city );
			$GLOBALS['ec_cart_data']->cart_data->billing_state = sanitize_text_field( $payer_info->shipping_address->state );
			$GLOBALS['ec_cart_data']->cart_data->billing_zip = sanitize_text_field( $payer_info->shipping_address->postal_code );
			$GLOBALS['ec_cart_data']->cart_data->billing_country = sanitize_text_field( $payer_info->shipping_address->country_code );
			if ( isset( $payer_info->phone ) && $payer_info->phone != "" ) {
				$GLOBALS['ec_cart_data']->cart_data->billing_phone = sanitize_text_field( $payer_info->phone );
			}

			$GLOBALS['ec_cart_data']->cart_data->shipping_selector = "";
			$GLOBALS['ec_cart_data']->cart_data->shipping_first_name = sanitize_text_field( $payer_info->first_name );
			$GLOBALS['ec_cart_data']->cart_data->shipping_last_name = sanitize_text_field( $payer_info->last_name );
			$GLOBALS['ec_cart_data']->cart_data->shipping_address_line_1 = sanitize_text_field( $payer_info->shipping_address->line1 );
			$GLOBALS['ec_cart_data']->cart_data->shipping_address_line_2 = sanitize_text_field( $payer_info->shipping_address->line2 );
			$GLOBALS['ec_cart_data']->cart_data->shipping_city = sanitize_text_field( $payer_info->shipping_address->city );
			$GLOBALS['ec_cart_data']->cart_data->shipping_state = sanitize_text_field( $payer_info->shipping_address->state );
			$GLOBALS['ec_cart_data']->cart_data->shipping_zip = sanitize_text_field( $payer_info->shipping_address->postal_code );
			$GLOBALS['ec_cart_data']->cart_data->shipping_country = sanitize_text_field( $payer_info->shipping_address->country_code );
			if ( isset( $payer_info->phone ) && $payer_info->phone != "" ) {
				$GLOBALS['ec_cart_data']->cart_data->shipping_phone = sanitize_text_field( $payer_info->phone );
			}

			$GLOBALS['ec_cart_data']->cart_data->email = sanitize_email( $payer_info->email );
			$GLOBALS['ec_cart_data']->cart_data->username = sanitize_text_field( $payer_info->first_name . " " . $payer_info->last_name );
			$GLOBALS['ec_cart_data']->cart_data->first_name = sanitize_text_field( $payer_info->first_name );
			$GLOBALS['ec_cart_data']->cart_data->last_name = sanitize_text_field( $payer_info->last_name );

			$GLOBALS['ec_cart_data']->cart_data->is_guest = true;
			$GLOBALS['ec_cart_data']->cart_data->guest_key = sanitize_text_field( $GLOBALS['ec_cart_data']->ec_cart_id );

			$GLOBALS['ec_cart_data']->save_session_to_db();
			do_action( 'wpeasycart_cart_updated' );
		}

	}

	public function submit_authorized_paypal_order() {

		global $wpdb;

		if ( ! $this->order->verify_stock() ) {
			header( "location: " . $this->cart_page . $this->permalink_divider . "ec_cart_error=stock_invalid" );
			die();
		}

		// Create Order
		$this->order->submit_order( "third_party" );

		// Execute payment
		$paypal = new ec_paypal();
		$result = $paypal->execute_order( $this->order->order_id, $this->cart, $this->order_totals, $this->tax );

		// Update Order or Remove Order
		if ( $result ) {

			$ec_db_admin = new ec_db_admin();
			$order_row = $ec_db_admin->get_order_row_admin( $this->order->order_id );
			$orderdetails = $ec_db_admin->get_order_details_admin( $this->order->order_id );

			// Clear tempcart
			$ec_db_admin->clear_tempcart( $GLOBALS['ec_cart_data']->ec_cart_id );
			$this->order->clear_session();

			$GLOBALS['ec_cart_data']->save_session_to_db();
			header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_success', array( 'order_id' => (int) $this->order->order_id ) ) ) );
		} else {
			$this->mysqli->remove_order( $this->order->order_id );
			header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( '', array( 'ec_cart_error' => 'payment_failed' ) ) ) );
		}
		die();
	}

	public function insert_ideal_order( $source, $payment_intent = false ) {
		global $wpdb;
		if ( ! $this->order->verify_stock() ) {
			return 0;
		}
		$this->order->submit_order( "ideal" );
		$order_id = $this->order->order_id;
		$order_gateway = 'stripe_connect';
		if ( get_option( 'ec_option_payment_process_method' ) == 'stripe' ) {
			$order_gateway = 'stripe';
		}
		if ( $payment_intent && isset( $payment_intent->latest_charge ) ) {
			$wpdb->query( $wpdb->prepare( "UPDATE ec_order SET order_gateway = %s, gateway_transaction_id = %s, payment_method = %s, stripe_charge_id = %s WHERE order_id = %d", $order_gateway, $source['id'] . ':' . $source['client_secret'], get_option( 'ec_option_payment_process_method' ), $payment_intent->latest_charge, $order_id ) );
		} else {
			$wpdb->query( $wpdb->prepare( "UPDATE ec_order SET order_gateway = %s, gateway_transaction_id = %s, payment_method = %s WHERE order_id = %d", $order_gateway, $source['id'] . ':' . $source['client_secret'], get_option( 'ec_option_payment_process_method' ), $order_id ) );
		}
		return $order_id;
	}

	private function process_submit_order() {
		if ( ! wp_verify_nonce( sanitize_text_field( $_POST['ec_cart_form_nonce'] ), 'wp-easycart-cart-submit-order-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ) {
			header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_info', array( 'ec_cart_error' => 'invalid_nonce' ) ) ) );
			die();
		}

		/**
		 * Place order was posted, before any payment runs ( 6.0.2: save what the payment step asked ).
		 *
		 * @since 6.0.2
		 */
		do_action( 'wpeasycart_submit_order_process' );
		$wpec_step_error = $this->checkout_step_error( 'payment' );
		if ( null !== $wpec_step_error ) {
			$wpec_error_page = $wpec_step_error['page'];
			$wpec_error_args = array( 'ec_cart_error' => $wpec_step_error['code'] );
			/* The PayPal Express review page asks everything itself and only shows with PayPal's ids: back to it, with them. */
			if ( isset( $_POST['paypal_payer_id'] ) && ( isset( $_POST['paypal_payment_id'] ) || isset( $_POST['paypal_order_id'] ) ) ) {
				$wpec_error_page         = 'checkout_payment';
				$wpec_error_args['PID']  = preg_replace( '/[^A-Za-z0-9-]/', '', sanitize_text_field( wp_unslash( isset( $_POST['paypal_payment_id'] ) ? $_POST['paypal_payment_id'] : $_POST['paypal_order_id'] ) ) );
				$wpec_error_args['PYID'] = preg_replace( '/[^A-Za-z0-9-]/', '', sanitize_text_field( wp_unslash( $_POST['paypal_payer_id'] ) ) );
				if ( isset( $_POST['paypal_order_id'] ) ) {
					$wpec_error_args['OID'] = preg_replace( '/[^A-Za-z0-9-]/', '', sanitize_text_field( wp_unslash( $_POST['paypal_order_id'] ) ) );
				}
				if ( isset( $_POST['paypal_payment_method'] ) ) {
					$wpec_error_args['PMETH'] = preg_replace( '/[^A-Za-z0-9_]/', '', sanitize_text_field( wp_unslash( $_POST['paypal_payment_method'] ) ) );
				}
			}
			header( 'location: ' . esc_url_raw( wpeasycart_links()->get_cart_page( $wpec_error_page, $wpec_error_args ) ) );
			die();
		}

		if ( isset( $_POST['ec_cart_is_subscriber'] ) && '1' == $_POST['ec_cart_is_subscriber'] ) {
			$first_name = $GLOBALS['ec_cart_data']->cart_data->billing_first_name;
			$last_name = $GLOBALS['ec_cart_data']->cart_data->billing_last_name;
			$email = $GLOBALS['ec_cart_data']->cart_data->email;

			$this->mysqli->insert_subscriber( $email, $first_name, $last_name ); // Fires wpeasycart_insert_subscriber ( 6.0.2: not fired a second time below ).

			if ( $GLOBALS['ec_user']->user_id ) {
				global $wpdb;
				$wpdb->query( $wpdb->prepare( "UPDATE ec_user SET is_subscriber = 1 WHERE ec_user.user_id = %d", $GLOBALS['ec_user']->user_id ) );
			}

			// MyMail Hook
			if ( function_exists( 'mailster' ) ) {
				$subscriber_id = mailster('subscribers')->add(array(
					'firstname' => $first_name,
					'lastname' => $last_name,
					'email' => $email,
					'status' => 1,
				), false );
			}
		}

		if ( ! $this->order->verify_stock() ) {
			header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_info', array( 'ec_cart_error' => 'stock_invalid' ) ) ) );
			die();
		}

		/* 6.0.2 checkout protection: card and PayPal payments pass the gate first ( manual billing, redirect gateways,
		   Amazon Pay and free orders charge nothing on this site ). */
		$wpec_selection = isset( $_POST['ec_cart_payment_selection'] ) ? sanitize_key( wp_unslash( $_POST['ec_cart_payment_selection'] ) ) : '';
		if ( class_exists( 'wp_easycart_checkout_guard' ) && $this->order_totals->grand_total > 0 && ( 'credit_card' === $wpec_selection || 'affirm' === $wpec_selection || isset( $_POST['paypal_order_id'] ) || isset( $_POST['paypal_payment_id'] ) ) ) {
			$wpec_gate_args = array(
				'gateway' => ( isset( $_POST['paypal_order_id'] ) || isset( $_POST['paypal_payment_id'] ) ) ? 'paypal' : (string) get_option( 'ec_option_payment_process_method' ),
				'amount'  => (float) $this->order_totals->grand_total,
			);
			/* 6.0.2: a card form that posts the card to the store ( the direct card gateways ) is checked against the card's own
			   daily limit too, before it is charged. Stripe and Square never send the card here. */
			if ( 'credit_card' === $wpec_selection && isset( $this->order->payment->credit_card ) && is_object( $this->order->payment->credit_card ) && ! empty( $this->order->payment->credit_card->card_number ) ) {
				$wpec_card     = $this->order->payment->credit_card;
				$wpec_material = wp_easycart_checkout_guard::card_material( $wpec_card->card_number, isset( $wpec_card->expiration_month ) ? $wpec_card->expiration_month : '', isset( $wpec_card->expiration_year ) ? $wpec_card->expiration_year : '' );
				if ( '' !== $wpec_material ) {
					$wpec_gate_args['card_material'] = $wpec_material;
					$wpec_gate_args['card_display']  = wp_easycart_checkout_guard::card_display( isset( $wpec_card->payment_method ) ? $wpec_card->payment_method : '', substr( preg_replace( '/\D/', '', (string) $wpec_card->card_number ), -4 ) );
				}
			}
			$wpec_gate = wp_easycart_checkout_guard::check( 'checkout', $wpec_gate_args );
			if ( is_wp_error( $wpec_gate ) ) {
				header( 'location: ' . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_payment', array( 'ec_cart_error' => $wpec_gate->get_error_code() ) ) ) );
				die();
			}
		}

		if ( isset( $_POST['ec_cart_payment_selection'] ) && 'amazonpay' == sanitize_text_field( $_POST['ec_cart_payment_selection'] ) ) {
			global $wpdb;
			$this->order->submit_order( "amazonpay" );
			$amazonpay = new ec_amazonpay( );
			$amazonpay->process_order( $this->cart_page, $this->permalink_divider, $this->order_totals->grand_total, $this->order->order_id ); 
			
		} else if ( isset( $_POST['paypal_payment_id'] ) || isset( $_POST['paypal_order_id'] ) ) {
			global $wpdb;

			// Create Order
			$this->order->submit_order( "third_party" );

			// Execute payment
			$paypal = new ec_paypal();
			if ( isset( $_POST['paypal_order_id'] ) )
				$result = $paypal->execute_order( $this->order->order_id, $this->cart, $this->order_totals, $this->tax );

			else
				$result = $paypal->execute_payment( $this->order->order_id, $this->cart, $this->order_totals, $this->tax );

			// Update Order or Remove Order
			if ( $result ) {

				$ec_db_admin = new ec_db_admin();
				$order_row = $ec_db_admin->get_order_row_admin( $this->order->order_id );
				$orderdetails = $ec_db_admin->get_order_details_admin( $this->order->order_id );
				if ( $order_row && !isset( $_POST['paypal_order_id'] ) ) {

					/* Update Stock Quantity */
					foreach( $orderdetails as $orderdetail ) {
						$product = $wpdb->get_row( $wpdb->prepare( "SELECT ec_product.* FROM ec_product WHERE ec_product.product_id = %d", $orderdetail->product_id ) );
						if ( $product ) {
							if ( $product->use_optionitem_quantity_tracking ) {
								$ec_db_admin->update_quantity_value( $orderdetail->quantity, $orderdetail->product_id, $orderdetail->optionitem_id_1, $orderdetail->optionitem_id_2, $orderdetail->optionitem_id_3, $orderdetail->optionitem_id_4, $orderdetail->optionitem_id_5 );
							}
							$ec_db_admin->update_product_stock( $orderdetail->product_id, $orderdetail->quantity );
							$this->mysqli->update_details_stock_adjusted( $orderdetail->orderdetail_id );
							$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log( order_id, order_log_key ) VALUES( %d, "order-stock-update" )', $this->order->order_id ) );
							$order_log_id = $wpdb->insert_id;
							$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "product_id", %s )', $order_log_id, $this->order->order_id, $orderdetail->product_id ) );
							$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "quantity", %s )', $order_log_id, $this->order->order_id, '-' . $orderdetail->quantity ) );
						}
					}

					// Update Order Status/Send Alerts
					if ( $result == 'approved' ) {
						$ec_db_admin->update_order_status( $this->order->order_id, "10" );
						do_action( 'wpeasycart_order_paid', $this->order->order_id );
					}

					// send email
					$order_display = new ec_orderdisplay( $order_row, true, true );
					$order_display->send_email_receipt();
					$order_display->send_gift_cards();
				} else {
					do_action( 'wpeasycart_order_complete', $this->order->order_id, $order_status );
				}

				// Clear tempcart
				$ec_db_admin->clear_tempcart( $GLOBALS['ec_cart_data']->ec_cart_id );
				$this->order->clear_session();

				$GLOBALS['ec_cart_data']->save_session_to_db();
				header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_success', array( 'order_id' => (int) $this->order->order_id ) ) ) );
			} else {
				/** This action is documented in inc/classes/core/ec_order.php ( 6.0.2 ). */
				do_action( 'wpeasycart_payment_failed', $this->order->order_id, 'paypal_capture_failed', 'paypal', $this->order );
				$this->mysqli->remove_order( $this->order->order_id );
				header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_payment', array( 'ec_cart_error' => 'payment_failed' ) ) ) );
			}
			die();
		}
		if ( $GLOBALS['ec_cart_data']->cart_data->email == "" ) {
			header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_info', array( 'ec_cart_error' => 'session_expired' ) ) ) );
			die();
		} else if ( !$this->validate_submit_order_data() ) {
			header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_info', array( 'ec_cart_error' => 'invalid_address' ) ) ) );
			die();
		} else if ( class_exists( 'wp_easycart_tax_providers' ) && wp_easycart_tax_providers::blocks_checkout() ) {
			/* 6.0.2: the tax service could not work out the tax and asked for checkout to stop ( e.g. Avalara unreachable, no backup rate ). */
			header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_payment', array( 'ec_cart_error' => 'tax_unavailable' ) ) ) );
			die();
		} else {
			if ( get_option( 'ec_option_skip_shipping_page' ) ) {
				$this->shipping->skip_shipping_selection_page();
			}

			if ( isset( $_POST['ec_cart_payment_selection'] ) )
				$payment_type = sanitize_text_field( $_POST['ec_cart_payment_selection'] );
			else if ( $this->is_affirm )
				$payment_type = "affirm";
			else
				$payment_type = wp_easycart_language()->get_text( "ec_success", "cart_account_free_order" );

			/* 6.0.2: a choice that places the order unpaid ( manual payment, a third party ) only when the store offers it. iDEAL is paid on the payment page and never placed here. */
			if ( $this->order_totals->grand_total > 0 && ( ( 'manual_bill' == $payment_type && ! $this->use_manual_payment() ) || ( 'third_party' == $payment_type && ! $this->use_third_party() ) || 'ideal' == $payment_type ) ) {
				header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_payment', array( 'ec_cart_error' => 'payment_failed' ) ) ) );
				die();
			}

			if ( isset( $_POST['ec_order_notes'] ) ) {
				$GLOBALS['ec_cart_data']->cart_data->order_notes = stripslashes( sanitize_textarea_field( $_POST['ec_order_notes'] ) );
			}

			/************************************** 
			Place 3Ds Payment Processing HERE
			***************************************/
			if ( get_option( 'ec_option_payment_process_method' ) == "nmi" && get_option( 'ec_option_nmi_3ds' ) == "2" ) { // 3D Secure

				$response = $this->order->submit_order( $payment_type );

				if ( $response ) {

					$gateway = new ec_cardinal();
					$gateway->initialize( $this->cart, $this->user, $this->shipping, $this->tax,
										  $this->discount, $this->payment->credit_card, $this->order_totals, $this->order->order_id );

					/* 6.0.2: the verified card form comes back to process_3ds_final(), which charges only this checkout's order, once. */
					set_transient( 'wpec_3ds_order_' . (int) $this->order->order_id, wp_hash( $GLOBALS['ec_cart_data']->ec_cart_id, 'nonce' ), HOUR_IN_SECONDS );

					$response = $gateway->secure_3d_lookup();

					if ( $response == "ERROR" ) { // Failed to Process CC at Cardinal
						$this->mysqli->remove_order( $this->order->order_id );
						header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_payment', array( 'ec_cart_error' => 'payment_failed' ) ) ) );

					} else if ( $response == "NO3DS" ) {
						$this->process_nmi_no_3ds();

					} else { // NO 3DS for User, Process Normally
						$submit_return_val = $this->order->submit_order( $payment_type );
						if ( $submit_return_val == "1" ) {
							$GLOBALS['ec_cart_data']->save_session_to_db();
							header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_success', array( 'order_id' => (int) $this->order->order_id ) ) ) );
						} else {
							$this->mysqli->remove_order( $order_id );
							header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_payment', array( 'ec_cart_error' => 'payment_failed' ) ) ) );
						}
					}
				} else { // order failed to insert
					header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_payment', array( 'ec_cart_error' => 'payment_failed' ) ) ) );
				}

			/************************************** 
			Place Standard Payment Processing HERE
			***************************************/
			} else { // Process Non-3D Secure (V3.2.4 and higher 3Ds)
				$submit_return_val = $this->order->submit_order( $payment_type );
				do_action( 'wpeasycart_submit_order_complete' );

				if ( $this->order_totals->grand_total <= 0 ) {
					$GLOBALS['ec_cart_data']->save_session_to_db();
					header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_success', array( 'order_id' => (int) $this->order->order_id ) ) ) );
				} else if ( $payment_type == "manual_bill" ) { // Show fail message or the success landing page (including the manual bill notice).
					if ( $submit_return_val == "1" ) {
						$GLOBALS['ec_cart_data']->save_session_to_db();
						header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_success', array( 'order_id' => (int) $this->order->order_id ) ) ) );
					} else {
						$GLOBALS['ec_cart_data']->save_session_to_db();
						header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_payment', array( 'ec_cart_error' => 'manualbill_failed' ) ) ) );
					}

				} else if ( $payment_type == "affirm" ) {
					if ( $submit_return_val == "1" ) {
						$GLOBALS['ec_cart_data']->save_session_to_db();
						header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_success', array( 'order_id' => (int) $this->order->order_id ) ) ) );
					} else {
						$GLOBALS['ec_cart_data']->save_session_to_db();
						header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_payment', array( 'ec_cart_error' => 'payment_failed' ) ) ) );
					}

				} else if ( $payment_type == "third_party" ) { // Show the third party landing page
					if ( $submit_return_val == "1" ) {
						$GLOBALS['ec_cart_data']->save_session_to_db();
						header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'third_party', array( 'order_id' => (int) $this->order->order_id ) ) ) );
					} else {
						$GLOBALS['ec_cart_data']->save_session_to_db();
						header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_payment', array( 'ec_cart_error' => 'thirdparty_failed' ) ) ) );
					}

				} else { // Either show the success landing page
					if ( $submit_return_val == "1" ) {
						if ( $this->order->payment->is_3d_auth )
							$this->auth_3d_form();
						else {
							$GLOBALS['ec_cart_data']->save_session_to_db();
							header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_success', array( 'order_id' => (int) $this->order->order_id ) ) ) );
						}
					} else {
						header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_payment', array( 'ec_cart_error' => 'payment_failed' ) ) ) );
					}
				}
			}
		}
	}

	public function auth_3d_form() {
		echo "<form name=\"ec_cart_3dauth_form\" method=\"POST\" action=\"" . esc_attr( $this->order->payment->post_url ) . "\">";
		if( $this->order->payment->post_id_input_name ) {
			echo "<input type=\"hidden\" name=\"" . esc_attr( $this->order->payment->post_id_input_name ) . "\" value=\"" . esc_attr( $this->order->payment->post_id ) . "\">";
		}
		if( $this->order->payment->post_message_input_name ) {
			echo "<input type=\"hidden\" name=\"" . esc_attr( $this->order->payment->post_message_input_name ) . "\" value=\"" . esc_attr( $this->order->payment->post_message ) . "\">";
		}
		if( $this->order->payment->post_return_url_input_name ) {
			echo "<input type=\"hidden\" name=\"" . esc_attr( $this->order->payment->post_return_url_input_name ) . "\" value=\"" . esc_attr( add_query_arg( 'ec_3ds_key', wp_easycart_3ds_return_key( $this->order->order_id ), wpeasycart_links()->get_cart_page( '3dsecure', array( 'order_id' => (int) $this->order->order_id ) ) ) ) . "\">";
		}
		echo "</form>";
		echo "<SCRIPT LANGUAGE=\"Javascript\">document.ec_cart_3dauth_form.submit();</SCRIPT>";
	}

	public function process_nmi_no_3ds() {

		$gateway = new ec_nmi();
		if ( isset( $_POST['ec_expiration_month'] ) && isset( $_POST['ec_expiration_year'] ) ) {
			$exp_month = sanitize_text_field( $_POST['ec_expiration_month'] );
			$exp_year = sanitize_text_field( $_POST['ec_expiration_year'] );
		} else {
			$exp_date = sanitize_text_field( $_POST['ec_cc_expiration'] );
			$exp_month = substr( $exp_date, 0, 2 );
			$exp_year = substr( $exp_date, 5 );
			if ( strlen( $exp_year ) == 2 ) {
				$exp_year = "20" . $exp_year;
			}
		}
		$credit_card = new ec_credit_card( 
			$this->get_payment_type( $this->sanatize_card_number( sanitize_text_field($_POST['ec_card_number'] ) ) ), 
			stripslashes( sanitize_text_field( $GLOBALS['ec_cart_data']->cart_data->billing_first_name ) ) . " " . stripslashes( sanitize_text_field( $GLOBALS['ec_cart_data']->cart_data->billing_last_name ) ),
			$this->sanatize_card_number( sanitize_text_field($_POST['ec_card_number'] ) ),
			$exp_month,
			$exp_year,
			sanitize_text_field( $_POST['ec_security_code'] )
		);
		$order_id = (int) $this->order->order_id; /* 6.0.2: the order this checkout just made ( the checkout form posts no order id ) */
		delete_transient( 'wpec_3ds_order_' . $order_id );
		$gateway->initialize( $this->cart, $this->user, $this->shipping, $this->tax, $this->discount, $credit_card, $this->order_totals, $order_id );
		$result = $gateway->process_credit_card();

		if ( $result ) {

			$this->mysqli->update_order_status( $order_id, "6" );

			do_action( 'wpeasycart_order_paid', $order_id );

			$db_admin = new ec_db_admin();
			$order_row = $db_admin->get_order_row_admin( $order_id );
			$order_display = new ec_orderdisplay( $order_row, true, true );
			$order_display->send_email_receipt();

			$this->mysqli->clear_tempcart( $GLOBALS['ec_cart_data']->ec_cart_id );
			$GLOBALS['ec_cart_data']->checkout_session_complete();

			header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_success', array( 'order_id' => $order_id ) ) ) );

		} else {
			$this->mysqli->remove_order( $order_id );
			$GLOBALS['ec_cart_data']->save_session_to_db();
			header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_payment', array( 'ec_cart_error' => '3dsecure_failed' ) ) ) );
		}
	}

	public function process_3ds_final() {
		/* 6.0.2: only the card form made for this checkout's own order ( NMI with 3-D Secure ), and only once. */
		global $wpdb;
		$order_id = ( isset( $_POST['order_id'] ) ) ? (int) $_POST['order_id'] : 0;
		$bound    = ( $order_id ) ? get_transient( 'wpec_3ds_order_' . $order_id ) : false;
		$unpaid   = ( $order_id ) ? $wpdb->get_var( $wpdb->prepare( 'SELECT ec_order.order_id FROM ec_order LEFT JOIN ec_orderstatus ON ec_orderstatus.status_id = ec_order.orderstatus_id WHERE ec_order.order_id = %d AND ( ec_orderstatus.is_approved IS NULL OR ec_orderstatus.is_approved = 0 )', $order_id ) ) : false;
		if ( ! class_exists( 'ec_nmi' ) || 'nmi' != get_option( 'ec_option_payment_process_method' ) || '2' != get_option( 'ec_option_nmi_3ds' ) || ! $unpaid || ! is_string( $bound ) || ! hash_equals( $bound, wp_hash( $GLOBALS['ec_cart_data']->ec_cart_id, 'nonce' ) ) ) {
			header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_payment', array( 'ec_cart_error' => '3dsecure_failed' ) ) ) );
			die();
		}
		delete_transient( 'wpec_3ds_order_' . $order_id );

		$gateway = new ec_nmi();
		if ( isset( $_POST['ec_expiration_month'] ) && isset( $_POST['ec_expiration_year'] ) ) {
			$exp_month = sanitize_text_field( $_POST['ec_expiration_month'] );
			$exp_year = sanitize_text_field( $_POST['ec_expiration_year'] );
		} else {
			$exp_date = sanitize_text_field( $_POST['ec_cc_expiration'] );
			$exp_month = substr( $exp_date, 0, 2 );
			$exp_year = substr( $exp_date, 5 );
			if ( strlen( $exp_year ) == 2 ) {
				$exp_year = "20" . $exp_year;
			}
		}
		$credit_card = new ec_credit_card( 
			$this->get_payment_type( $this->sanatize_card_number( sanitize_text_field( $_POST['ec_card_number'] ) ) ),
			stripslashes( sanitize_text_field( $GLOBALS['ec_cart_data']->cart_data->billing_first_name ) ) . " " . stripslashes( sanitize_text_field( $GLOBALS['ec_cart_data']->cart_data->billing_last_name ) ),
			$this->sanatize_card_number( sanitize_text_field( $_POST['ec_card_number'] ) ),
			$exp_month,
			$exp_year,
			sanitize_text_field( $_POST['ec_security_code'] )
		);
		$gateway->initialize( $this->cart, $this->user, $this->shipping, $this->tax, $this->discount, $credit_card, $this->order_totals, $order_id );
		$result = $gateway->process_3ds();

		if ( $result ) {
			$this->mysqli->update_order_status( $order_id, "6" );
			do_action( 'wpeasycart_order_paid', $order_id );
			$db_admin = new ec_db_admin();
			$order_row = $db_admin->get_order_row_admin( $order_id );
			$order_display = new ec_orderdisplay( $order_row, true, true );
			$order_display->send_email_receipt();
			$this->mysqli->clear_tempcart( $GLOBALS['ec_cart_data']->ec_cart_id );
			$GLOBALS['ec_cart_data']->checkout_session_complete();
			header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_success', array( 'order_id' => $order_id ) ) ) );
		} else {
			$this->mysqli->remove_order( $order_id );
			$GLOBALS['ec_cart_data']->save_session_to_db();
			header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_payment', array( 'ec_cart_error' => '3dsecure_failed' ) ) ) );
		}
	}

	public function process_3dsecure_response() {

		global $wpdb;
		$success = false;
		$order_id = ( isset( $_GET['order_id'] ) ) ? (int) $_GET['order_id'] : 0;

		// Check if order has already been approved, fixing data error.
		$order = $wpdb->get_row( $wpdb->prepare( "SELECT ec_order.order_id FROM ec_order, ec_orderstatus WHERE ec_order.order_id = %d AND ec_order.orderstatus_id = ec_orderstatus.status_id AND ec_orderstatus.is_approved = 1", $order_id ) );
		if ( $order ) {
			header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_success', array( 'order_id' => $order_id ) ) ) );
			die();
		}

		// Verify the order with the gateway
		if ( get_option( 'ec_option_payment_process_method' ) == "sagepay" && class_exists( 'ec_sagepay' ) ) {
			$gateway = new ec_sagepay();
		} else if ( get_option( 'ec_option_payment_process_method' ) == "realex" && class_exists( 'ec_realex' ) ) {
			$gateway = new ec_realex();
		}

		if ( isset( $gateway ) && $order_id ) {
			$success = $gateway->secure_3d_auth();
			if ( $success ) {

				do_action( 'wpeasycart_order_paid', $order_id );

				$this->order->clear_session();
				if ( $this->discount->giftcard_code )
					$this->mysqli->update_giftcard_total( $this->discount->giftcard_code, $this->discount->giftcard_discount );

				$GLOBALS['ec_cart_data']->save_session_to_db();
				header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_success', array( 'order_id' => $order_id ) ) ) );
				die();
			}
		}

		if ( ! $success ) {
			/* 6.0.2: the unpaid order is removed only through the return link made for it ( the bank's ACS posts back to it ). */
			$return_key = ( isset( $_GET['ec_3ds_key'] ) && is_string( $_GET['ec_3ds_key'] ) ) ? sanitize_text_field( wp_unslash( $_GET['ec_3ds_key'] ) ) : '';
			if ( $order_id && '' !== $return_key && hash_equals( wp_easycart_3ds_return_key( $order_id ), $return_key ) ) {
				$this->mysqli->remove_order( $order_id );
			}
			$GLOBALS['ec_cart_data']->save_session_to_db();
			header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_payment', array( 'ec_cart_error' => '3dsecure_failed' ) ) ) );
			die();
		}
	}

	public function process_3ds_response() {
		if ( isset( $_GET['order_id'] ) && class_exists( 'ec_cardinal' ) ) { /* 6.0.2: Cardinal ships with WP EasyCart PRO */
			$order_id = (int) $_GET['order_id'];
			$db = new ec_db_admin();
			$order = $db->get_order_row_admin( $order_id );
			if ( $order ) {
				$gateway = new ec_cardinal();
				$response = $gateway->secure_3d_auth( $order_id, $order, $_POST );
				if ( ! $response ) {
					header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_payment', array( 'ec_cart_error' => '3dsecure_failed' ) ) ) );
				}
			} else {// No VALID Order ID Returned, Likely Fraud
				header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_payment', array( 'ec_cart_error' => '3dsecure_failed' ) ) ) );
			}
		} else {// No Order ID Returned, Likely Fraud
			header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_payment', array( 'ec_cart_error' => '3dsecure_failed' ) ) ) );
		}
	}

	private function process_realex_redirect() {
		if ( isset( $_POST['AUTHCODE'] ) && isset( $_POST['ORDER_ID'] ) && $_POST['AUTHCODE'] == "00" ) {
			header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_success', array( 'order_id' => (int) $_POST['ORDER_ID'] ) ) ) );
		} else {
			header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_payment', array( 'ec_cart_error' => 'thirdparty_failed' ) ) ) );
		}
	}

	private function process_realex_response() {
		if ( isset( $_POST['ORDER_ID'] ) ) {
			global $wpdb;
			$mysqli = new ec_db();

			$response_string = print_r( $_POST, true );

			$realex_merchant_id = get_option( 'ec_option_realex_thirdparty_merchant_id' );
			$realex_secret = get_option( 'ec_option_realex_thirdparty_secret' );
			$realex_currency = get_option( 'ec_option_realex_thirdparty_currency' );

			$timestamp = sanitize_text_field( $_POST['TIMESTAMP'] );
			$result = sanitize_text_field( $_POST['RESULT'] );
			$order_id = sanitize_text_field( $_POST['ORDER_ID'] );
			$message = sanitize_text_field( $_POST['MESSAGE'] );
			$authcode = sanitize_text_field( $_POST['AUTHCODE'] );
			$pasref = sanitize_text_field( $_POST['PASREF'] );
			$realexmd5 = sanitize_text_field( $_POST['MD5HASH'] );

			$tmp = "$timestamp.$realex_merchant_id.$order_id.$result.$message.$pasref.$authcode";

			$md5hash = md5($tmp);
			$tmp_md5 = "$md5hash.$realex_secret";
			$md5hash = md5($tmp_md5);

			$sha1hash = sha1($tmp);
			$tmp_sha1 = "$sha1hash.$realex_secret";
			$sha1hash = sha1($tmp_sha1);

			if ( $md5hash == $_POST['MD5HASH'] && $sha1hash == $_POST['SHA1HASH'] ) {
				$mysqli->insert_response( $order_id, 0, "Realex Third Party", $response_string );
				if ( $_POST['RESULT'] == '00' ) { 
					$mysqli->update_order_status( $order_id, "10" );
					do_action( 'wpeasycart_order_paid', $order_id );
					$db_admin = new ec_db_admin();
					$order_row = $db_admin->get_order_row_admin( $order_id );
					$orderdetails = $db_admin->get_order_details_admin( $order_id );
					foreach ( $orderdetails as $orderdetail ) {
						$product = $wpdb->get_row( $wpdb->prepare( "SELECT ec_product.* FROM ec_product WHERE ec_product.product_id = %d", $orderdetail->product_id ) );
						if ( $product ) {
							if ( $product->use_optionitem_quantity_tracking ) {
								$db_admin->update_quantity_value( $orderdetail->quantity, $orderdetail->product_id, $orderdetail->optionitem_id_1, $orderdetail->optionitem_id_2, $orderdetail->optionitem_id_3, $orderdetail->optionitem_id_4, $orderdetail->optionitem_id_5 );
							}
							$db_admin->update_product_stock( $orderdetail->product_id, $orderdetail->quantity );
							$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log( order_id, order_log_key ) VALUES( %d, "order-stock-update" )', $order_id ) );
							$order_log_id = $wpdb->insert_id;
							$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "product_id", %s )', $order_log_id, $order_id, $orderdetail->product_id ) );
							$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "quantity", %s )', $order_log_id, $order_id, '-' . $orderdetail->quantity ) );
						}
					}
					$order_display = new ec_orderdisplay( $order_row, true, true );
					$order_display->send_email_receipt();
					$order_display->send_gift_cards();
				} else if ( $_POST['AUTHCODE'] == 'refund' ) { 
					$mysqli->update_order_status( $order_id, "16" );
					do_action( 'wpeasycart_full_order_refund', $order_id );
				} else {
					$mysqli->update_order_status( $order_id, "8" );
				}
			}
		}
	}

	private function process_paymentexpress_thirdparty_response() {
		$gateway = new ec_paymentexpress_thirdparty();
		$gateway->update_order_status();
		$db = new ec_db();
		$db->clear_tempcart( $GLOBALS['ec_cart_data']->ec_cart_id );
		$GLOBALS['ec_cart_data']->save_session_to_db();	
		header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_success', array( 'order_id' => (int) $_GET['order_id'] ) ) ) );
	}

	private function process_third_party_forward() {
		$this->payment->third_party->initialize( (int) $_GET['order_id'] );
		$this->payment->third_party->display_auto_forwarding_form();
		die();
	}

	public function process_login_user( $redirect = true ) {
		$recaptcha_valid = true;
		if ( wp_easycart_recaptcha_ready( 'cart' ) ) { // 6.0.2: only once both keys are saved, as the form shows it
			if ( ! isset( $_POST['ec_grecaptcha_response_login'] ) || '' == $_POST['ec_grecaptcha_response_login'] ) {
				if ( isset( $_POST['ec_cart_subscription'] ) ) {
					if ( $redirect ) {
						header( 'location: ' . esc_url_raw( wpeasycart_links()->get_cart_page( 'subscription_info', array( 'subscription' => sanitize_text_field( $_POST['ec_cart_subscription'] ), 'ec_cart_error' => 'login_failed' ) ) ) );
					} else {
						return (object) array(
							'success' => false,
							'error' => 'login_failed',
							'url' => esc_url_raw( wpeasycart_links()->get_cart_page( 'subscription_info', array( 'subscription' => sanitize_text_field( $_POST['ec_cart_subscription'] ), 'ec_cart_error' => 'login_failed' ) ) ),
						);
					}
				} else {
					if ( $redirect ) {
						header( 'location: ' . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_info', array( 'account_error' => 'login_failed' ) ) ) );
						die();
					} else {
						return (object) array(
							'success' => false,
							'error' => 'login_failed',
							'url' => esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_info', array( 'account_error' => 'login_failed' ) ) ),
						);
					}
				}
			}

			$db = new ec_db_admin();
			$recaptcha_response = sanitize_text_field( $_POST['ec_grecaptcha_response_login'] );

			$data = array(
				'secret' => get_option( 'ec_option_recaptcha_secret_key' ),
				'response' => $recaptcha_response,
			);

			$request = new WP_Http;
			$response = $request->request(
				"https://www.google.com/recaptcha/api/siteverify", 
				array( 
					'method' => 'POST', 
					'body' => http_build_query( $data ),
					'timeout' => 30,
				)
			);
			if ( is_wp_error( $response ) ) {
				$error_message = $response->get_error_message();
				$db->insert_response( 0, 1, 'GOOGLE RECAPTCHA CURL ERROR', $error_message );
				$response = (object) array(
					'error' => $error_message
				);
			} else {
				$response = json_decode( $response['body'] );
				$db->insert_response( 0, 0, 'Google Recaptcha Response', print_r( $response, true ) );
			}

			$recaptcha_valid = ( isset( $response->success ) && $response->success ) ? true : false;
		}

		if ( $recaptcha_valid ) {
			$email = trim( sanitize_email( $_POST['ec_cart_login_email'] ) );
			$password = $_POST['ec_cart_login_password']; // XSS OK. Password should not be sanitized.
			$password_hash = wp_easycart_hash_password( $password );
			$password_hash = apply_filters( 'wpeasycart_password_hash', $password_hash, $password );

			do_action( 'wpeasycart_pre_login_attempt', $email );
			$user = $this->mysqli->get_user_login( $email, $password, $password_hash );
			/* 6.0.2: a password reset before 6.0.2 was saved without WordPress's slashes; one with ' " or \ still signs in ( as My Account's login ). */
			if ( ! $user && is_string( $password ) && wp_unslash( $password ) !== $password ) {
				$password      = wp_unslash( $password );
				$password_hash = apply_filters( 'wpeasycart_password_hash', wp_easycart_hash_password( $password ), $password );
				$user          = $this->mysqli->get_user_login( $email, $password, $password_hash );
			}

			if ( $user && 'pending' == $user->user_level ) {
				$GLOBALS['ec_cart_data']->save_session_to_db();
				do_action( 'wpeasycart_cart_updated' );
				if ( isset( $_POST['ec_cart_subscription'] ) ) {
					if ( $redirect ) {
						header( 'location: ' . esc_url_raw( wpeasycart_links()->get_cart_page( 'subscription_info', array( 'ec_cart_error' => 'not_activated', 'subscription' => sanitize_text_field( $_POST['ec_cart_subscription'] ) ) ) ) );
					} else {
						return (object) array(
							'success' => false,
							'error' => 'not_activated',
							'url' => esc_url_raw( wpeasycart_links()->get_cart_page( 'subscription_info', array( 'ec_cart_error' => 'not_activated', 'subscription' => sanitize_text_field( $_POST['ec_cart_subscription'] ) ) ) ),
						);
					}
				} else {
					if ( $redirect ) {
						header( 'location: ' . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_info', array( 'ec_cart_error' => 'not_activated' ) ) ) );
					} else {
						return (object) array(
							'success' => false,
							'error' => 'not_activated',
							'url' => esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_info', array( 'ec_cart_error' => 'not_activated' ) ) ),
						);
					}
				}

			} else if ( 'guest' == $email ) {
				$GLOBALS['ec_cart_data']->cart_data->email = 'guest';
				$GLOBALS['ec_cart_data']->cart_data->username = 'guest';
				$GLOBALS['ec_cart_data']->save_session_to_db();
				do_action( 'wpeasycart_cart_updated' );
				if ( isset( $_POST['ec_cart_subscription'] ) ) {
					if ( $redirect ) {
						header( 'location: ' . esc_url_raw( wpeasycart_links()->get_cart_page( 'subscription_info', array( 'subscription' => sanitize_text_field( $_POST['ec_cart_subscription'] ) ) ) ) );
					} else {
						return (object) array(
							'success' => true,
							'error' => false,
							'url' => esc_url_raw( wpeasycart_links()->get_cart_page( 'subscription_info', array( 'subscription' => sanitize_text_field( $_POST['ec_cart_subscription'] ) ) ) ),
						);
					}
				} else {
					if ( $redirect ) {
						header( 'location: ' . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_info' ) ) );
					} else {
						return (object) array(
							'success' => true,
							'error' => false,
							'url' => esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_info' ) ),
						);
					}
				}
			} else if ( $user ) {
				// WordPress User Sync 1.x: sign in to the linked WordPress user ( 2.0 does it on wpeasycart_login_success ).
				wp_easycart_wordpress_users::legacy_store_login( $user, $email, $password );

				do_action( 'wpeasycart_login_success', $email );
				$GLOBALS['ec_cart_data']->cart_data->billing_first_name = sanitize_text_field( $user->billing_first_name );
				$GLOBALS['ec_cart_data']->cart_data->billing_last_name = sanitize_text_field( $user->billing_last_name );
				$GLOBALS['ec_cart_data']->cart_data->billing_address_line_1 = sanitize_text_field( $user->billing_address_line_1 );
				$GLOBALS['ec_cart_data']->cart_data->billing_address_line_2 = sanitize_text_field( $user->billing_address_line_2 );
				$GLOBALS['ec_cart_data']->cart_data->billing_city = sanitize_text_field( $user->billing_city );
				$GLOBALS['ec_cart_data']->cart_data->billing_state = sanitize_text_field( $user->billing_state );
				$GLOBALS['ec_cart_data']->cart_data->billing_zip = sanitize_text_field( $user->billing_zip );
				$GLOBALS['ec_cart_data']->cart_data->billing_country = sanitize_text_field( $user->billing_country );
				$GLOBALS['ec_cart_data']->cart_data->billing_phone = sanitize_text_field( $user->billing_phone );

				$GLOBALS['ec_cart_data']->cart_data->shipping_selector = "";
				$GLOBALS['ec_cart_data']->cart_data->shipping_first_name = sanitize_text_field( $user->shipping_first_name );
				$GLOBALS['ec_cart_data']->cart_data->shipping_last_name = sanitize_text_field( $user->shipping_last_name );
				$GLOBALS['ec_cart_data']->cart_data->shipping_address_line_1 = sanitize_text_field( $user->shipping_address_line_1 );
				$GLOBALS['ec_cart_data']->cart_data->shipping_address_line_2 = sanitize_text_field( $user->shipping_address_line_2 );
				$GLOBALS['ec_cart_data']->cart_data->shipping_city = sanitize_text_field( $user->shipping_city );
				$GLOBALS['ec_cart_data']->cart_data->shipping_state = sanitize_text_field( $user->shipping_state );
				$GLOBALS['ec_cart_data']->cart_data->shipping_zip = sanitize_text_field( $user->shipping_zip );
				$GLOBALS['ec_cart_data']->cart_data->shipping_country = sanitize_text_field( $user->shipping_country );
				$GLOBALS['ec_cart_data']->cart_data->shipping_phone = sanitize_text_field( $user->shipping_phone );
				$GLOBALS['ec_cart_data']->cart_data->is_guest = "";
				$GLOBALS['ec_cart_data']->cart_data->guest_key = "";

				$GLOBALS['ec_cart_data']->cart_data->user_id = (int) $user->user_id;
				$GLOBALS['ec_cart_data']->cart_data->email = sanitize_email( $email );
				$GLOBALS['ec_cart_data']->cart_data->username = sanitize_text_field( $user->first_name . " " . $user->last_name );
				$GLOBALS['ec_cart_data']->cart_data->first_name = sanitize_text_field( $user->first_name );
				$GLOBALS['ec_cart_data']->cart_data->last_name = sanitize_text_field( $user->last_name );

				if ( $user->is_stripe_test_user ) {
					$GLOBALS['ec_cart_data']->cart_data->stripe_paymentintent_id = "";
					$GLOBALS['ec_cart_data']->cart_data->stripe_pi_client_secret = "";
					$GLOBALS['ec_cart_data']->cart_data->stripe_last_pi_data = "";
				}

				$GLOBALS['ec_cart_data']->save_session_to_db();
				$wpeasycart_offer_prev_session_id = $GLOBALS['ec_cart_data']->ec_cart_id;
				wpeasycart_session()->rotate_session_id();
				if ( function_exists( 'wp_easycart_offers_active' ) && wp_easycart_offers_active() ) {
					ec_offer_integration::migrate_session_codes( $wpeasycart_offer_prev_session_id );
				}
				do_action( 'wpeasycart_cart_updated' );
				if ( isset( $GLOBALS['ec_cart_data']->cart_data->cart_subscription ) && '' != $GLOBALS['ec_cart_data']->cart_data->cart_subscription ) {
					if ( $redirect ) {
						header( 'location: ' . esc_url_raw( wpeasycart_links()->get_cart_page( 'subscription_info', array( 'subscription' => sanitize_text_field( $_POST['ec_cart_subscription'] ) ) ) ) );
					} else {
						return (object) array(
							'success' => true,
							'error' => false,
							'url' => esc_url_raw( wpeasycart_links()->get_cart_page( 'subscription_info', array( 'subscription' => sanitize_text_field( $_POST['ec_cart_subscription'] ) ) ) ),
						);
					}
				} else if ( isset( $_POST['ec_cart_model_number'] ) ) {
					if ( $redirect ) {
						header( 'location: ' . esc_url_raw( wpeasycart_links()->get_cart_page( 'subscription_info', array( 'subscription' => sanitize_text_field( $_POST['ec_cart_model_number'] ) ) ) ) );
					} else {
						return (object) array(
							'success' => true,
							'error' => false,
							'url' => esc_url_raw( wpeasycart_links()->get_cart_page( 'subscription_info', array( 'subscription' => sanitize_text_field( $_POST['ec_cart_model_number'] ) ) ) ),
						);
					}
				} else {
					if ( wp_easycart_onepage_active() ) {
						if ( $redirect ) {
							header( 'location: ' . esc_url_raw( wpeasycart_links()->get_cart_page( '', array( 'eccheckout' => 'information' ) ) ) );
						} else {
							return (object) array(
								'success' => true,
								'error' => false,
								'url' => esc_url_raw( wpeasycart_links()->get_cart_page( '', array( 'eccheckout' => 'information' ) ) ),
							);
						}
					} else {
						if ( $redirect ) {
							header( 'location: ' . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_info' ) ) );
						} else {
							return (object) array(
								'success' => true,
								'error' => false,
								'url' => esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_info' ) ),
							);
						}
					}
				}
			} else {
				do_action( 'wpeasycart_login_failed', $email );
				$GLOBALS['ec_cart_data']->save_session_to_db();
				if ( isset( $_POST['ec_cart_subscription'] ) ) {
					if ( $redirect ) {
						header( 'location: ' . esc_url_raw( wpeasycart_links()->get_cart_page( 'subscription_info', array( 'subscription' => sanitize_text_field( $_POST['ec_cart_subscription'] ), 'ec_cart_error' => 'login_failed' ) ) ) );
					} else {
						return (object) array(
							'success' => false,
							'error' => 'login_failed',
							'url' => esc_url_raw( wpeasycart_links()->get_cart_page( 'subscription_info', array( 'subscription' => sanitize_text_field( $_POST['ec_cart_subscription'] ), 'ec_cart_error' => 'login_failed' ) ) ),
						);
					}
				} else {
					if ( wp_easycart_onepage_active() ) {
						if ( $redirect ) {
							header( 'location: ' . esc_url_raw( wpeasycart_links()->get_cart_page( '', array( 'eccheckout' => 'information', 'ec_cart_error' => 'login_failed' ) ) ) );
						} else {
							return (object) array(
								'success' => false,
								'error' => 'login_failed',
								'url' => esc_url_raw( wpeasycart_links()->get_cart_page( '', array( 'eccheckout' => 'information', 'ec_cart_error' => 'login_failed' ) ) ),
							);
						}
					} else {
						if ( $redirect ) {
							header( 'location: ' . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_info', array( 'ec_cart_error' => 'login_failed' ) ) ) );
						} else {
							return (object) array(
								'success' => false,
								'error' => 'login_failed',
								'url' => esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_info', array( 'ec_cart_error' => 'login_failed' ) ) ),
							);
						}
					}
				}
			}
		} else if ( ! $redirect ) { /* 6.0.2: the reCAPTCHA check failed: say so instead of answering nothing */
			return (object) array(
				'success' => false,
				'error'   => 'login_failed',
				'url'     => esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_info', array( 'ec_cart_error' => 'login_failed' ) ) ),
			);
		}
	}

	private function process_logout_user() {
		$wpec_logout_user_id = ( isset( $GLOBALS['ec_cart_data']->cart_data->user_id ) && '' != $GLOBALS['ec_cart_data']->cart_data->user_id ) ? (int) $GLOBALS['ec_cart_data']->cart_data->user_id : 0;
		if ( $wpec_logout_user_id > 0 ) {
			do_action( 'wpeasycart_logout', $wpec_logout_user_id ); /* 6.0.2: the cart's sign-out fires it too, like My Account's */
		}
		$GLOBALS['ec_cart_data']->cart_data->user_id = "";
		$GLOBALS['ec_cart_data']->cart_data->email = "";
		$GLOBALS['ec_cart_data']->cart_data->username = "";
		$GLOBALS['ec_cart_data']->cart_data->first_name = "";
		$GLOBALS['ec_cart_data']->cart_data->last_name = "";

		$GLOBALS['ec_cart_data']->cart_data->is_guest = "";
		$GLOBALS['ec_cart_data']->cart_data->guest_key = "";

		$GLOBALS['ec_cart_data']->cart_data->billing_first_name = "";
		$GLOBALS['ec_cart_data']->cart_data->billing_last_name = "";
		$GLOBALS['ec_cart_data']->cart_data->billing_company_name = "";
		$GLOBALS['ec_cart_data']->cart_data->billing_address_line_1 = "";
		$GLOBALS['ec_cart_data']->cart_data->billing_address_line_2 = "";
		$GLOBALS['ec_cart_data']->cart_data->billing_city = "";
		$GLOBALS['ec_cart_data']->cart_data->billing_state = "";
		$GLOBALS['ec_cart_data']->cart_data->billing_zip = "";
		$GLOBALS['ec_cart_data']->cart_data->billing_country = "";
		$GLOBALS['ec_cart_data']->cart_data->billing_phone = "";
		$GLOBALS['ec_cart_data']->cart_data->vat_registration_number = "";

		$GLOBALS['ec_cart_data']->cart_data->shipping_selector = "";

		$GLOBALS['ec_cart_data']->cart_data->shipping_first_name = "";
		$GLOBALS['ec_cart_data']->cart_data->shipping_last_name = "";
		$GLOBALS['ec_cart_data']->cart_data->shipping_company_name = "";
		$GLOBALS['ec_cart_data']->cart_data->shipping_address_line_1 = "";
		$GLOBALS['ec_cart_data']->cart_data->shipping_address_line_2 = "";
		$GLOBALS['ec_cart_data']->cart_data->shipping_city = "";
		$GLOBALS['ec_cart_data']->cart_data->shipping_state = "";
		$GLOBALS['ec_cart_data']->cart_data->shipping_zip = "";
		$GLOBALS['ec_cart_data']->cart_data->shipping_country = "";
		$GLOBALS['ec_cart_data']->cart_data->shipping_phone = "";

		$GLOBALS['ec_cart_data']->cart_data->first_name = "";
		$GLOBALS['ec_cart_data']->cart_data->last_name = "";

		$GLOBALS['ec_cart_data']->cart_data->create_account = "";

		$GLOBALS['ec_cart_data']->cart_data->order_notes = "";
		$GLOBALS['ec_cart_data']->cart_data->email_other = "";

		$GLOBALS['ec_cart_data']->cart_data->shipping_method = "";
		$GLOBALS['ec_cart_data']->cart_data->estimate_shipping_zip = "";
		$GLOBALS['ec_cart_data']->cart_data->estimate_shipping_country = "";

		$GLOBALS['ec_cart_data']->cart_data->stripe_paymentintent_id = "";
		$GLOBALS['ec_cart_data']->cart_data->stripe_pi_client_secret = "";
		$GLOBALS['ec_cart_data']->cart_data->amazon_session_id = "";
		$GLOBALS['ec_cart_data']->cart_data->amazon_buyer_id = "";
		$GLOBALS['ec_cart_data']->cart_data->amazon_payment_selection = "";

		$GLOBALS['ec_cart_data']->save_session_to_db();
		$wpeasycart_offer_prev_session_id = $GLOBALS['ec_cart_data']->ec_cart_id;
		wpeasycart_session()->rotate_session_id();
		if ( function_exists( 'wp_easycart_offers_active' ) && wp_easycart_offers_active() ) {
			ec_offer_integration::migrate_session_codes( $wpeasycart_offer_prev_session_id );
		}

		wp_cache_flush();

		wp_easycart_wordpress_users::store_logout(); /* 6.0.2: ends a Login as Customer, else signs out of WordPress for User Sync 1.x */

		if ( isset( $_GET['subscription'] ) ) {
			header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'subscription_info', array( 'subscription' => sanitize_text_field( $_GET['subscription'] ) ) ) ) );
		} else if ( ! get_option( 'ec_option_skip_cart_login' ) && file_exists( EC_PLUGIN_DATA_DIRECTORY . "/design/theme/" . get_option( 'ec_option_base_theme' ) . "/admin_panel.php" ) ) {
			header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_login' ) ) );
		} else {
			header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_info' ) ) );
		}
	}

	private function process_save_checkout_info() {
		if ( ! wp_verify_nonce( sanitize_text_field( $_POST['ec_cart_form_nonce'] ), 'wp-easycart-cart-checkout-info-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ) {
			header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_info', array( 'ec_cart_error' => 'invalid_nonce' ) ) ) );
			die();
		}

		if ( isset( $_POST['ec_login_selector'] ) ) {
			$this->process_login_user();
		} else {
			$this->process_save_checkout_info_helper();
		}

		do_action( 'wpeasycart_user_updated' );
	}

	private function process_save_checkout_info_helper() {
		$recaptcha_valid = true;
		if ( wp_easycart_recaptcha_ready( 'cart' ) ) { // 6.0.2: only once both keys are saved, as the form shows it

			if ( !isset( $_POST['ec_grecaptcha_response_register'] ) || $_POST['ec_grecaptcha_response_register'] == '' ) {
				header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_info', array( 'cart_error' => 'register_invalid' ) ) ) );
				die();
			}

			$db = new ec_db_admin();
			$recaptcha_response = sanitize_text_field( $_POST['ec_grecaptcha_response_register'] );
			$data = array(
				"secret"	=> get_option( 'ec_option_recaptcha_secret_key' ),
				"response"	=> $recaptcha_response
			);

			$request = new WP_Http;
			$response = $request->request( 
				"https://www.google.com/recaptcha/api/siteverify", 
				array( 
					'method' => 'POST', 
					'body' => http_build_query( $data ),
					'timeout' => 30
				)
			);
			if ( is_wp_error( $response ) ) {
				$error_message = $response->get_error_message();
				$db->insert_response( 0, 1, "GOOGLE RECAPTCHA CURL ERROR", $error_message );
				$response = (object) array( "error" => $error_message );
			} else {
				$response = json_decode( $response['body'] );
				$db->insert_response( 0, 0, "Google Recaptcha Response", print_r( $response, true ) );
			}

			$recaptcha_valid = ( isset( $response->success ) && $response->success ) ? true : false;
		}

		if ( $recaptcha_valid ) {

			$billing_country = $shipping_country = stripslashes( sanitize_text_field( $_POST['ec_cart_billing_country'] ) );

			$billing_first_name = $shipping_first_name = stripslashes( sanitize_text_field( $_POST['ec_cart_billing_first_name'] ) );
			$billing_last_name = $shipping_last_name = stripslashes( sanitize_text_field( $_POST['ec_cart_billing_last_name'] ) );

			if ( isset( $_POST['ec_cart_billing_company_name'] ) ) {
				$billing_company_name = $shipping_company_name = stripslashes( sanitize_text_field( $_POST['ec_cart_billing_company_name'] ) );
			} else {
				$billing_company_name = $shipping_company_name = "";
			}

			if ( isset( $_POST['ec_cart_billing_vat_registration_number'] ) ) {
				$vat_registration_number = stripslashes( sanitize_text_field( $_POST['ec_cart_billing_vat_registration_number'] ) );
			} else {
				$vat_registration_number = "";
			}

			$billing_address = $shipping_address = stripslashes( sanitize_text_field( $_POST['ec_cart_billing_address'] ) );
			if ( isset( $_POST['ec_cart_billing_address2'] ) ) {
				$billing_address2 = $shipping_address2 = stripslashes( sanitize_text_field( $_POST['ec_cart_billing_address2'] ) );
			} else {
				$billing_address2 = $shipping_address2 = "";
			}

			$billing_city = $shipping_city = stripslashes( sanitize_text_field( $_POST['ec_cart_billing_city'] ) );
			if ( isset( $_POST['ec_cart_billing_state_' . $billing_country] ) ) {
				$billing_state = $shipping_state = stripslashes( sanitize_text_field( $_POST['ec_cart_billing_state_' . $billing_country] ) );
			} else {
				$billing_state = $shipping_state = stripslashes( sanitize_text_field( $_POST['ec_cart_billing_state'] ) );
			}

			$billing_zip = $shipping_zip = trim( stripslashes( sanitize_text_field( $_POST['ec_cart_billing_zip'] ) ) );
			if ( isset( $_POST['ec_cart_billing_phone'] ) ) {
				$billing_phone = $shipping_phone = stripslashes( sanitize_text_field( $_POST['ec_cart_billing_phone'] ) );
			} else {
				$billing_phone = "";
			}

			if ( isset( $_POST['ec_shipping_selector'] ) ) {
				$shipping_selector = sanitize_text_field( $_POST['ec_shipping_selector'] );
			} else {
				$shipping_selector = "false";
			}

			if ( $shipping_selector == 'true' && get_option( 'ec_option_use_shipping' ) && $this->shipping_address_allowed && ( $this->cart->shippable_total_items > 0 || $this->order_totals->handling_total > 0 || $this->cart->excluded_shippable_total_items > 0 ) ) {
				$shipping_country = stripslashes( sanitize_text_field( $_POST['ec_cart_shipping_country'] ) );

				$shipping_first_name = stripslashes( sanitize_text_field( $_POST['ec_cart_shipping_first_name'] ) );
				$shipping_last_name = stripslashes( sanitize_text_field( $_POST['ec_cart_shipping_last_name'] ) );

				if ( isset( $_POST['ec_cart_shipping_company_name'] ) ) {
					$shipping_company_name = stripslashes( sanitize_text_field( $_POST['ec_cart_shipping_company_name'] ) );
				} else {
					$shipping_company_name = "";
				}

				$shipping_address = stripslashes( sanitize_text_field( $_POST['ec_cart_shipping_address'] ) );
				if ( isset( $_POST['ec_cart_shipping_address2'] ) ) {
					$shipping_address2 = stripslashes( sanitize_text_field( $_POST['ec_cart_shipping_address2'] ) );
				} else {
					$shipping_address2 = "";
				}

				$shipping_city = stripslashes( sanitize_text_field( $_POST['ec_cart_shipping_city'] ) );

				if ( isset( $_POST['ec_cart_shipping_state_' . $shipping_country] ) ) {
					$shipping_state = stripslashes( sanitize_text_field( $_POST['ec_cart_shipping_state_' . $shipping_country] ) );
				} else {
					$shipping_state = stripslashes( sanitize_text_field( $_POST['ec_cart_shipping_state'] ) );
				}

				$shipping_zip = trim( stripslashes( sanitize_text_field( $_POST['ec_cart_shipping_zip'] ) ) );
				if ( isset( $_POST['ec_cart_shipping_phone'] ) ) {
					$shipping_phone = stripslashes( sanitize_text_field( $_POST['ec_cart_shipping_phone'] ) );
				} else {
					$shipping_phone = "";
				}
			}

			if ( isset( $_POST['ec_order_notes'] ) ) {
				$order_notes = stripslashes( sanitize_textarea_field( $_POST['ec_order_notes'] ) );
			} else if ( $GLOBALS['ec_cart_data']->cart_data->order_notes != "" ) {
				$order_notes = sanitize_textarea_field( $GLOBALS['ec_cart_data']->cart_data->order_notes );
			} else {
				$order_notes = "";
			}

			if ( isset( $_POST['ec_contact_first_name'] ) ) {
				$first_name = stripslashes( sanitize_text_field( $_POST['ec_contact_first_name'] ) );
			} else if ( isset( $_POST['ec_cart_billing_first_name'] ) ) {
				$first_name = stripslashes( sanitize_text_field( $_POST['ec_cart_billing_first_name'] ) );
			} else {
				$first_name = "";
			}
			if ( isset( $_POST['ec_contact_last_name'] ) ) {
				$last_name = stripslashes( sanitize_text_field( $_POST['ec_contact_last_name'] ) );
			} else if ( isset( $_POST['ec_cart_billing_last_name'] ) ) {
				$last_name = stripslashes( sanitize_text_field( $_POST['ec_cart_billing_last_name'] ) );
			} else {
				$last_name = "";
			}

			if ( isset( $_POST['ec_contact_create_account'] ) )
				$create_account = sanitize_text_field( $_POST['ec_contact_create_account'] );
			else if ( isset( $_POST['ec_create_account_selector'] ) )
				$create_account = true;
			else
				$create_account = false;

			$GLOBALS['ec_cart_data']->cart_data->billing_first_name = $billing_first_name;
			$GLOBALS['ec_cart_data']->cart_data->billing_last_name = $billing_last_name;
			$GLOBALS['ec_cart_data']->cart_data->billing_company_name = $billing_company_name;
			$GLOBALS['ec_cart_data']->cart_data->vat_registration_number = $vat_registration_number;
			$GLOBALS['ec_cart_data']->cart_data->billing_address_line_1 = $billing_address;
			$GLOBALS['ec_cart_data']->cart_data->billing_address_line_2 = $billing_address2;
			$GLOBALS['ec_cart_data']->cart_data->billing_city = $billing_city;
			$GLOBALS['ec_cart_data']->cart_data->billing_state = $billing_state;
			$GLOBALS['ec_cart_data']->cart_data->billing_zip = $billing_zip;
			$GLOBALS['ec_cart_data']->cart_data->billing_country = $billing_country;
			$GLOBALS['ec_cart_data']->cart_data->billing_phone = $billing_phone;

			$GLOBALS['ec_cart_data']->cart_data->shipping_selector = $shipping_selector;

			$GLOBALS['ec_cart_data']->cart_data->shipping_first_name = $shipping_first_name;
			$GLOBALS['ec_cart_data']->cart_data->shipping_last_name = $shipping_last_name;
			$GLOBALS['ec_cart_data']->cart_data->shipping_company_name = $shipping_company_name;
			$GLOBALS['ec_cart_data']->cart_data->shipping_address_line_1 = $shipping_address;
			$GLOBALS['ec_cart_data']->cart_data->shipping_address_line_2 = $shipping_address2;
			$GLOBALS['ec_cart_data']->cart_data->shipping_city = $shipping_city;
			$GLOBALS['ec_cart_data']->cart_data->shipping_state = $shipping_state;
			$GLOBALS['ec_cart_data']->cart_data->shipping_zip = $shipping_zip;
			$GLOBALS['ec_cart_data']->cart_data->shipping_country = $shipping_country;
			$GLOBALS['ec_cart_data']->cart_data->shipping_phone = $shipping_phone;

			$GLOBALS['ec_cart_data']->cart_data->first_name = $first_name;
			$GLOBALS['ec_cart_data']->cart_data->last_name = $last_name;

			$GLOBALS['ec_cart_data']->cart_data->order_notes = $order_notes;
			$GLOBALS['ec_cart_data']->cart_data->email_other = sanitize_text_field( ( isset( $_POST['ec_email_other'] ) ) ? $_POST['ec_email_other'] : '' );

			$next_page = "checkout_shipping";
			if ( !get_option( 'ec_option_use_shipping' ) || $this->cart->shippable_total_items == 0 )
				$next_page = "checkout_payment";

			if ( get_option( 'ec_option_skip_shipping_page' ) || $GLOBALS['ec_user']->freeshipping )//|| $this->discount->shipping_discount == $this->discount->shipping_subtotal )
				$next_page = "checkout_payment";

			if ( isset( $_POST['ec_contact_email'] ) ) {
				$email = sanitize_email( $_POST['ec_contact_email'] );
				$GLOBALS['ec_cart_data']->cart_data->email = $email;
			}

			if ( isset( $_POST['ec_contact_email'] ) && !$create_account ) {
				$GLOBALS['ec_cart_data']->cart_data->is_guest = true;
				$GLOBALS['ec_cart_data']->cart_data->guest_key = sanitize_text_field( $GLOBALS['ec_cart_data']->ec_cart_id );
			} else if ( isset( $_POST['ec_contact_email'] ) ) {
				$GLOBALS['ec_cart_data']->cart_data->is_guest = true;
				$GLOBALS['ec_cart_data']->cart_data->guest_key = sanitize_text_field( $GLOBALS['ec_cart_data']->ec_cart_id );
			} else {
				$GLOBALS['ec_cart_data']->cart_data->is_guest = false;
				$GLOBALS['ec_cart_data']->cart_data->guest_key = "";
			}

			do_action( 'wpeasycart_save_checkout_info_process' );

			$GLOBALS['ec_cart_data']->save_session_to_db();

			$wpec_step_error = null;
			if ( !$this->validate_checkout_data() ) {

				header( "location: " . apply_filters( 'wp_easycart_invalid_checkout_details_url', esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_info', array( 'ec_cart_error' => 'invalid_address' ) ) ) ) );

			} else if ( null !== ( $wpec_step_error = $this->checkout_step_error( 'information' ) ) ) { /* 6.0.2 */
				header( 'location: ' . esc_url_raw( wpeasycart_links()->get_cart_page( $wpec_step_error['page'], array( 'ec_cart_error' => $wpec_step_error['code'] ) ) ) );

			} else if ( ! $this->validate_cart_shipping() ) {
				header( "location: " . apply_filters( 'wp_easycart_invalid_checkout_details_url', $this->cart_page . $this->permalink_divider . "ec_cart_error=invalid_cart_shipping" ) );

			} else {
				if ( $create_account ) {
					if ( wp_easycart_wordpress_users::email_taken( sanitize_email( $_POST['ec_contact_email'] ) ) ) {
						do_action( 'wpeasycart_cart_updated' );
						$GLOBALS['ec_cart_data']->save_session_to_db();
						header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_info', array( 'ec_cart_error' => 'email_exists' ) ) ) );
					} else {
						$email = sanitize_email( $_POST['ec_contact_email'] );
						$password = wp_easycart_hash_password( $_POST['ec_contact_password'] ); // XSS OK. Should not sanitize password.
						$password = apply_filters( 'wpeasycart_password_hash', $password, $_POST['ec_contact_password'] ); // XSS OK. Should not sanitize password.

						// INSERT USER
						$billing_id = $this->mysqli->insert_address( $billing_first_name, $billing_last_name, $billing_address, $billing_address2, $billing_city, $billing_state, $billing_zip, $billing_country, $billing_phone, $billing_company_name );
						$shipping_id = $this->mysqli->insert_address( $shipping_first_name, $shipping_last_name, $shipping_address, $shipping_address2, $shipping_city, $shipping_state, $shipping_zip, $shipping_country, $shipping_phone, $shipping_company_name );

						$user_level = "shopper";
						if ( isset( $_POST['ec_cart_is_subscriber'] ) && '1' == $_POST['ec_cart_is_subscriber'] ) {
							$is_subscriber = true;
						} else {
							$is_subscriber = false;
						}

						$user_id = $this->mysqli->insert_user( $email, $password, $first_name, $last_name, $billing_id, $shipping_id, $user_level, $is_subscriber, "", $vat_registration_number );
						if ( $user_id != 0 ) {
							$this->mysqli->update_address_user_id( $billing_id, $user_id );
							$this->mysqli->update_address_user_id( $shipping_id, $user_id );

							// MyMail Hook. insert_user() above already subscribed a ticked box and fired wpeasycart_insert_subscriber ( 6.0.2 ).
							if ( $is_subscriber ) {
								if ( function_exists( 'mailster' ) ) {
									$subscriber_id = mailster('subscribers')->add(array(
										'firstname' => $first_name,
										'lastname' => $last_name,
										'email' => $email,
										'status' => 1,
									), false );
								}
							}

							do_action( 'wpeasycart_account_added', $user_id, $email, $_POST['ec_contact_password'], 'checkout' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- passwords are used as typed, never sanitized ( 6.0.2 ).

							// WordPress User Sync 1.x: the WordPress user, with the password the shopper typed ( not the store's hash ).
							wp_easycart_wordpress_users::legacy_account_created( $user_id, $email, $_POST['ec_contact_password'], $first_name, $last_name ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- passwords are used as typed, never sanitized ( 6.0.2 ).

							// Send registration email if needed
							if ( get_option( 'ec_option_send_signup_email' ) ) {

								$headers   = array();
								$headers[] = "MIME-Version: 1.0";
								$headers[] = "Content-Type: text/html; charset=utf-8";
								$headers[] = "From: " . stripslashes( get_option( 'ec_option_order_from_email' ) );
								$headers[] = "Reply-To: " . stripslashes( get_option( 'ec_option_order_from_email' ) );
								$headers[] = "X-Mailer: PHP/" . phpversion();

								$message = wp_easycart_account_register_admin_email_html( $email ); // 6.0.0: shared email design.

								if ( get_option( 'ec_option_use_wp_mail' ) ) {
									wp_mail( stripslashes( get_option( 'ec_option_bcc_email_addresses' ) ), wp_easycart_language()->get_text( "account_register", "account_register_email_title" ), $message, implode("\r\n", $headers) );
								} else {
									$admin_email = stripslashes( get_option( 'ec_option_bcc_email_addresses' ) );
									$subject = wp_easycart_language()->get_text( "account_register", "account_register_email_title" );
									$mailer = new wpeasycart_mailer();
									$mailer->send_order_email( $admin_email, $subject, $message );
								}

							}

							$GLOBALS['ec_cart_data']->cart_data->is_guest = false;
							$GLOBALS['ec_cart_data']->cart_data->user_id = $user_id;
							$GLOBALS['ec_cart_data']->cart_data->email = $email;
							$GLOBALS['ec_cart_data']->cart_data->username = $first_name . " " . $last_name;
							$GLOBALS['ec_cart_data']->cart_data->first_name = $first_name;
							$GLOBALS['ec_cart_data']->cart_data->last_name = $last_name;

							if ( $this->shipping->validate_address( $shipping_address, $shipping_city, $shipping_state, $shipping_zip, $shipping_country ) ) {
								$GLOBALS['ec_cart_data']->cart_data->is_guest = "";
								$GLOBALS['ec_cart_data']->cart_data->guest_key = "";
								$GLOBALS['ec_cart_data']->save_session_to_db();
								do_action( 'wpeasycart_cart_updated' );	
								if ( ! $this->validate_vat_registration_number( $vat_registration_number ) ) {
									header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_info', array( 'ec_cart_error' => 'invalid_vat_number' ) ) ) );
								} else {
									header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( esc_attr( $next_page ), array( 'ec_cart_success' => 'account_created' ) ) ) );
								}
							} else {
								$GLOBALS['ec_cart_data']->save_session_to_db();
								do_action( 'wpeasycart_cart_updated' );
								header("location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_info', array( 'ec_cart_error' => 'invalid_address' ) ) ) );
							}
						} else {
							$GLOBALS['ec_cart_data']->save_session_to_db();
							header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_info', array( 'ec_cart_error' => 'email_exists' ) ) ) );
						}
					}

				} else {
					$this->mysqli->update_user( $GLOBALS['ec_user']->user_id, $vat_registration_number );
					if ( $this->shipping->validate_address( $shipping_address, $shipping_city, $shipping_state, $shipping_zip, $shipping_country ) ) {
						if ( $GLOBALS['ec_user']->billing_id ) {
							$this->mysqli->update_address( $GLOBALS['ec_user']->billing_id, $billing_first_name, $billing_last_name, $billing_address, $billing_address2, $billing_city, $billing_state, $billing_zip, $billing_country, $billing_phone, $billing_company_name );
						} else {
							$this->mysqli->insert_user_address( $billing_first_name, $billing_last_name, $billing_company_name, $billing_address, $billing_address2, $billing_city, $billing_state, $billing_zip, $billing_country, $billing_phone, $GLOBALS['ec_user']->user_id, "billing" );
						}

						if ( $GLOBALS['ec_user']->shipping_id ) {
								$this->mysqli->update_address( $GLOBALS['ec_user']->shipping_id, $shipping_first_name, $shipping_last_name, $shipping_address, $shipping_address2, $shipping_city, $shipping_state, $shipping_zip, $shipping_country, $shipping_phone, $shipping_company_name );

						} else {
							$this->mysqli->insert_user_address( $shipping_first_name, $shipping_last_name, $shipping_company_name, $shipping_address, $shipping_address2, $shipping_city, $shipping_state, $shipping_zip, $shipping_country, $shipping_phone, $GLOBALS['ec_user']->user_id, "shipping" );

						}

						$GLOBALS['ec_cart_data']->save_session_to_db();
						do_action( 'wpeasycart_cart_updated' );
						do_action( 'wpeasycart_account_updated', $GLOBALS['ec_user']->user_id, true );

						if ( !$this->validate_vat_registration_number( $vat_registration_number ) ) {
							header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_info', array( 'ec_cart_error' => 'invalid_vat_number' ) ) ) );
						} else {
							header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( esc_attr( $next_page ) ) ) );
						}

					} else {
						$GLOBALS['ec_cart_data']->save_session_to_db();
						do_action( 'wpeasycart_cart_updated' );
						header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_info', array( 'ec_cart_error' => 'invalid_address' ) ) ) );
					}
				}
			}
		} // close recaptcha check
	}

	private function process_save_checkout_shipping() {
		if ( ! wp_verify_nonce( sanitize_text_field( $_POST['ec_cart_form_nonce'] ), 'wp-easycart-cart-shipping-method-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ) {
			header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_info', array( 'ec_cart_error' => 'invalid_nonce' ) ) ) );
			die();
		}

		if ( isset( $_POST['ec_cart_shipping_method'] ) && $this->shipping->is_valid_shipping_method( sanitize_text_field( $_POST['ec_cart_shipping_method'] ) ) ) {
			$shipping_method = sanitize_text_field( $_POST['ec_cart_shipping_method'] );
		} else {
			$shipping_method = "";
		}
		if ( isset( $_POST['ec_cart_ship_express'] ) ) {
			$ship_express = sanitize_text_field( $_POST['ec_cart_ship_express'] );
		} else {
			$ship_express = "";
		}
		$GLOBALS['ec_cart_data']->cart_data->shipping_method = $shipping_method;
		$GLOBALS['ec_cart_data']->cart_data->expedited_shipping = $ship_express;

		/**
		 * The classic checkout's shipping method step was posted ( 6.0.2: save what else it asked ).
		 *
		 * @since 6.0.2
		 */
		do_action( 'wpeasycart_save_checkout_shipping_process' );

		$GLOBALS['ec_cart_data']->save_session_to_db();

		do_action( 'wpeasycart_cart_updated' );

		$wpec_step_error = ( '' == $shipping_method ) ? null : $this->checkout_step_error( 'shipping' );
		if( '' == $shipping_method ) {
			$url = wpeasycart_links()->get_cart_page( 'checkout_shipping', array( 'ec_cart_error' => 'shipping_method' ) );
		} else if ( null !== $wpec_step_error ) { /* 6.0.2 */
			$url = wpeasycart_links()->get_cart_page( $wpec_step_error['page'], array( 'ec_cart_error' => $wpec_step_error['code'] ) );
		} else {
			$url = wpeasycart_links()->get_cart_page( 'checkout_payment' );
		}

		if ( isset( $_POST['paypal_payment_id'] ) && isset( $_POST['paypal_payer_id'] ) && isset( $_POST['paypal_payment_method'] ) ) {
			if ( substr_count( $url, '?' ) ) {
				$url .= '&';
			} else {
				$url .= '?';
			}
			$url .= 'PID=' . preg_replace( "/[^A-Za-z0-9\-]/", '', sanitize_text_field( $_POST['paypal_payment_id'] ) ) . '&PYID=' . preg_replace( "/[^A-Za-z0-9\-]/", '', sanitize_text_field( $_POST['paypal_payer_id'] ) ) . '&PMETH=' . preg_replace( "/[^A-Za-z0-9\_]/", '', sanitize_text_field( $_POST['paypal_payment_method'] ) );
		}
		header( "location: " . esc_url_raw( $url ) );
	}

	private function process_purchase_subscription() {

		$model_number = 0;
		if ( isset( $_POST['model_number'] ) )
			$model_number = sanitize_text_field( $_POST['model_number'] );

		header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'subscription_info', array( 'subscription' => esc_attr( $model_number ) ) ) ) );

	}

	private function process_insert_subscription() {
		if ( ! wp_verify_nonce( sanitize_text_field( $_POST['ec_cart_form_nonce'] ), 'wp-easycart-cart-insert-subscription-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ) {
			header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_info', array( 'ec_cart_error' => 'invalid_nonce' ) ) ) );
			die();
		}

		if ( isset( $_POST['ec_login_selector'] ) ) {
			$this->process_login_user();

		} else {
			$this->process_insert_subscription_helper();
		}

	}

	private function process_insert_subscription_helper() {
		global $wpdb;
		$model_number = sanitize_text_field( $_POST['ec_cart_model_number'] );

		/* 6.0.2: subscriptions are billed through Stripe only. A page opened before the gateway changed goes back to the
		 * subscription page, which says the subscription can't be bought, before an account is made or a card is sent. */
		if ( class_exists( 'wp_easycart_subscription_gateway' ) && ! wp_easycart_subscription_gateway::ready() ) {
			header( 'location: ' . esc_url_raw( wpeasycart_links()->get_cart_page( 'subscription_info', array( 'subscription' => $model_number ) ) ) );
			die();
		}

		$products = $this->mysqli->get_product_list( $wpdb->prepare( " WHERE product.model_number = %s", $model_number ), "", "", "" );

		$user_error = false;
		if ( isset( $_POST['ec_contact_email'] ) ) {
			$user_error = wp_easycart_wordpress_users::email_taken( sanitize_email( $_POST['ec_contact_email'] ) );
		}

		// If checkout out as new user and the email already exists, this is an error.
		if ( !$user_error ) {

			if ( count( $products ) > 0 ) { /* 6.0.2: was count( $products > 0 ), a fatal error on PHP 8 */

				// Try to get a subscription for this product and email address!
				if ( isset( $_POST['ec_contact_email'] ) )	$email_test = sanitize_email( $_POST['ec_contact_email'] );
				else										$email_test = sanitize_email( $GLOBALS['ec_cart_data']->cart_data->email );

				$subscription_list = $this->mysqli->find_subscription_match( $email_test, $products[0]['product_id'] );

				// Coupon Information
				$coupon = NULL;
				$discount_total = 0;
				$is_match = false;
				$wpec_coupon = false;
				/* 6.0.2: the code posted, else the one the page's code box applied ( it sits outside this form, so the post
				 * carried no code and the subscription started without the discount the page showed ). The code box's rules
				 * decide ( subscription_checkout_coupon() ): Stripe gets the Stripe coupon, the order the code. */
				if ( isset( $_POST['ec_cart_coupon_code'] ) && $_POST['ec_cart_coupon_code'] != "" ) {
					$wpec_coupon_code = sanitize_text_field( wp_unslash( $_POST['ec_cart_coupon_code'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- process_insert_subscription() checked its nonce.
				} else if ( isset( $_POST['ec_coupon_code'] ) && $_POST['ec_coupon_code'] != "" ) {
					$wpec_coupon_code = sanitize_text_field( wp_unslash( $_POST['ec_coupon_code'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- process_insert_subscription() checked its nonce.
				} else {
					$wpec_coupon_code = isset( $GLOBALS['ec_cart_data']->cart_data->coupon_code ) ? (string) $GLOBALS['ec_cart_data']->cart_data->coupon_code : '';
				}
				if ( '' != $wpec_coupon_code ) {
					$wpec_coupon = self::subscription_checkout_coupon( $wpec_coupon_code, $products[0]['product_id'], $products[0]['manufacturer_id'] );
					if ( $wpec_coupon ) {
						$coupon_row = $wpec_coupon['row'];
						$is_match = true;
						$coupon = $wpec_coupon['stripe'];
					}
				}
				// END COUPON FIND SECTION

				// IF MATCH FOUND, APPLY TO PRODUCT
				if ( $is_match ) {

					if ( $wpec_coupon['amount_off'] ) {
						$discount_total = floatval( $coupon_row->promo_dollar );

					} else {
						$discount_total = ( floatval( $products[0]['price'] ) * ( floatval( $coupon_row->promo_percentage ) / 100 ) );

					}

				}
				// END MATCHING COUPON SECTION

				// Billing Information
				$billing_country = $shipping_country = stripslashes( sanitize_text_field( $_POST['ec_cart_billing_country'] ) );

				$billing_first_name = $shipping_first_name = stripslashes( sanitize_text_field( $_POST['ec_cart_billing_first_name'] ) );
				$billing_last_name = $shipping_last_name = stripslashes( sanitize_text_field( $_POST['ec_cart_billing_last_name'] ) );

				if ( isset( $_POST['ec_cart_billing_company_name'] ) ) {
					$billing_company_name = $shipping_company_name = stripslashes( sanitize_text_field( $_POST['ec_cart_billing_company_name'] ) );
				} else {
					$billing_company_name = $shipping_company_name = "";
				}

				$billing_address = $shipping_address = stripslashes( sanitize_text_field( $_POST['ec_cart_billing_address'] ) );
				if ( isset( $_POST['ec_cart_billing_address2'] ) ) {
					$billing_address2 = $shipping_address2 = stripslashes( sanitize_text_field( $_POST['ec_cart_billing_address2'] ) );
				} else {
					$billing_address2 = $shipping_address2 = "";
				}

				$billing_city = $shipping_city = stripslashes( sanitize_text_field( $_POST['ec_cart_billing_city'] ) );
				if ( isset( $_POST['ec_cart_billing_state_' . $billing_country] ) ) {
					$billing_state = $shipping_state = stripslashes( sanitize_text_field( $_POST['ec_cart_billing_state_' . $billing_country] ) );
				} else {
					$billing_state = $shipping_state = stripslashes( sanitize_text_field( $_POST['ec_cart_billing_state'] ) );
				}

				$billing_zip = $shipping_zip = stripslashes( sanitize_text_field( $_POST['ec_cart_billing_zip'] ) );
				if ( isset( $_POST['ec_cart_billing_phone'] ) ) {
					$billing_phone = $shipping_phone = stripslashes( sanitize_text_field( $_POST['ec_cart_billing_phone'] ) );
				} else {
					$billing_phone = "";
				}
				// END BILLING INFO

				// Shipping Information
				if ( isset( $_POST['ec_shipping_selector'] ) )
					$shipping_selector = sanitize_text_field( $_POST['ec_shipping_selector'] );
				else
					$shipping_selector = "false";

				if ( $shipping_selector == "true" ) {
					$shipping_country = stripslashes( sanitize_text_field( $_POST['ec_cart_shipping_country'] ) );

					$shipping_first_name = stripslashes( sanitize_text_field( $_POST['ec_cart_shipping_first_name'] ) );
					$shipping_last_name = stripslashes( sanitize_text_field( $_POST['ec_cart_shipping_last_name'] ) );

					if ( isset( $_POST['ec_cart_shipping_company_name'] ) ) {
						$shipping_company_name = stripslashes( sanitize_text_field( $_POST['ec_cart_shipping_company_name'] ) );
					} else {
						$shipping_company_name = "";
					}

					$shipping_address = stripslashes( sanitize_text_field( $_POST['ec_cart_shipping_address'] ) );
					if ( isset( $_POST['ec_cart_shipping_address2'] ) ) {
						$shipping_address2 = stripslashes( sanitize_text_field( $_POST['ec_cart_shipping_address2'] ) );
					} else {
						$shipping_address2 = "";
					}

					$shipping_city = stripslashes( sanitize_text_field( $_POST['ec_cart_shipping_city'] ) );

					if ( isset( $_POST['ec_cart_shipping_state_' . $shipping_country] ) ) {
						$shipping_state = stripslashes( sanitize_text_field( $_POST['ec_cart_shipping_state_' . $shipping_country] ) );
					} else {
						$shipping_state = stripslashes( sanitize_text_field( $_POST['ec_cart_shipping_state'] ) );
					}

					$shipping_zip = stripslashes( sanitize_text_field( $_POST['ec_cart_shipping_zip'] ) );
					if ( isset( $_POST['ec_cart_shipping_phone'] ) ) {
						$shipping_phone = stripslashes( sanitize_text_field( $_POST['ec_cart_shipping_phone'] ) );
					} else {
						$shipping_phone = "";
					}
				}
				// END SHIPPING INFO

				// Order Notes
				if ( isset( $_POST['ec_order_notes'] ) ) {
					$order_notes = stripslashes( sanitize_textarea_field( $_POST['ec_order_notes'] ) );
				} else {
					$order_notes = "";
				}

				// Create Account Information
				if ( isset( $_POST['ec_contact_first_name'] ) ) {
					$first_name = stripslashes( sanitize_text_field( $_POST['ec_contact_first_name'] ) );
				} else if ( isset( $_POST['ec_cart_billing_first_name'] ) ) {
					$first_name = stripslashes( sanitize_text_field( $_POST['ec_cart_billing_first_name'] ) );
				} else {
					$first_name = "";
				}
				if ( isset( $_POST['ec_contact_last_name'] ) ) {
					$last_name = stripslashes( sanitize_text_field( $_POST['ec_contact_last_name'] ) );
				} else if ( isset( $_POST['ec_cart_billing_last_name'] ) ) {
					$last_name = stripslashes( sanitize_text_field( $_POST['ec_cart_billing_last_name'] ) );
				} else {
					$last_name = "";
				}

				if ( isset( $_POST['ec_contact_create_account'] ) )
					$create_account = sanitize_text_field( $_POST['ec_contact_create_account'] );
				else if ( isset( $_POST['ec_create_account_selector'] ) )
					$create_account = true;
				else
					$create_account = false;


				// CREATE ACCOUNT IF NEEDED
				if ( isset( $_POST['ec_contact_email'] ) ) {
					$email = sanitize_email( $_POST['ec_contact_email'] );
					$GLOBALS['ec_cart_data']->cart_data->email = $email;
				}

				if ( isset( $_POST['ec_contact_email'] ) && !$create_account ) {
					$GLOBALS['ec_cart_data']->cart_data->is_guest = true;
					$GLOBALS['ec_cart_data']->cart_data->guest_key = $GLOBALS['ec_cart_data']->ec_cart_id;
				} else {
					$GLOBALS['ec_cart_data']->cart_data->is_guest = false;
				}

				if ( $create_account ) {
					$email = sanitize_email( $_POST['ec_contact_email'] );
					$password = wp_easycart_hash_password( $_POST['ec_contact_password'] ); // XSS OK, Password not sanitized
					$password = apply_filters( 'wpeasycart_password_hash', $password, $_POST['ec_contact_password'] ); // XSS OK, Password not sanitized

					// INSERT USER
					$billing_id = $this->mysqli->insert_address( $billing_first_name, $billing_last_name, $billing_address, $billing_address2, $billing_city, $billing_state, $billing_zip, $billing_country, $billing_phone, $billing_company_name );

					$shipping_id = $this->mysqli->insert_address( $shipping_first_name, $shipping_last_name, $shipping_address, $shipping_address2, $shipping_city, $shipping_state, $shipping_zip, $shipping_country, $shipping_phone, $shipping_company_name );

					$user_level = "shopper";
					if ( isset( $_POST['ec_contact_is_subscriber'] ) ) {
						$is_subscriber = true;
					} else {
						$is_subscriber = false;
					}

					$user_id = $this->mysqli->insert_user( $email, $password, $first_name, $last_name, $billing_id, $shipping_id, $user_level, $is_subscriber );
					$this->mysqli->update_address_user_id( $billing_id, $user_id );
					$this->mysqli->update_address_user_id( $shipping_id, $user_id );

					do_action( 'wpeasycart_account_added', $user_id, $email, $_POST['ec_contact_password'], 'subscription' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- passwords are used as typed, never sanitized ( 6.0.2 ).

					// WordPress User Sync 1.x: the WordPress user ( 2.0 makes it on wpeasycart_account_added ).
					wp_easycart_wordpress_users::legacy_account_created( $user_id, $email, $_POST['ec_contact_password'], $first_name, $last_name ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- passwords are used as typed, never sanitized ( 6.0.2 ).

					if ( $is_subscriber ) {
						// MyMail Hook. insert_user() above already subscribed a ticked box and fired wpeasycart_insert_subscriber ( 6.0.2 ).
						if ( function_exists( 'mailster' ) ) {
							$subscriber_id = mailster('subscribers')->add(array(
								'firstname' => $first_name,
								'lastname' => $last_name,
								'email' => $email,
								'status' => 1,
							), false );
						}
					}

					if ( $user_id != 0 ) {

						$GLOBALS['ec_cart_data']->cart_data->user_id = $user_id;
						$GLOBALS['ec_cart_data']->cart_data->email = $email;
						$GLOBALS['ec_cart_data']->cart_data->username = $first_name . " " . $last_name;
						$GLOBALS['ec_cart_data']->cart_data->first_name = $first_name;
						$GLOBALS['ec_cart_data']->cart_data->last_name = $last_name;

						$GLOBALS['ec_user'] = new ec_user( "" );

					}
				} else { // Customer already exists, lets update their billing address
					$user = new ec_user( "" );
					$this->mysqli->update_address( $user->billing_id, $billing_first_name, $billing_last_name, $billing_address, $billing_address2, $billing_city, $billing_state, $billing_zip, $billing_country, $billing_phone, $billing_company_name );
				}
				// END CREATE ACCOUNT

				// Set Sessions
				$GLOBALS['ec_cart_data']->cart_data->billing_first_name = $billing_first_name;
				$GLOBALS['ec_cart_data']->cart_data->billing_last_name = $billing_last_name;
				$GLOBALS['ec_cart_data']->cart_data->billing_company_name = $billing_company_name;
				$GLOBALS['ec_cart_data']->cart_data->billing_address_line_1 = $billing_address;
				$GLOBALS['ec_cart_data']->cart_data->billing_address_line_2 = $billing_address2;
				$GLOBALS['ec_cart_data']->cart_data->billing_city = $billing_city;
				$GLOBALS['ec_cart_data']->cart_data->billing_state = $billing_state;
				$GLOBALS['ec_cart_data']->cart_data->billing_zip = $billing_zip;
				$GLOBALS['ec_cart_data']->cart_data->billing_country = $billing_country;
				$GLOBALS['ec_cart_data']->cart_data->billing_phone = $billing_phone;

				$GLOBALS['ec_cart_data']->cart_data->shipping_selector = $shipping_selector;

				$GLOBALS['ec_cart_data']->cart_data->shipping_first_name = $shipping_first_name;
				$GLOBALS['ec_cart_data']->cart_data->shipping_last_name = $shipping_last_name;
				$GLOBALS['ec_cart_data']->cart_data->shipping_company_name = $shipping_company_name;
				$GLOBALS['ec_cart_data']->cart_data->shipping_address_line_1 = $shipping_address;
				$GLOBALS['ec_cart_data']->cart_data->shipping_address_line_2 = $shipping_address2;
				$GLOBALS['ec_cart_data']->cart_data->shipping_city = $shipping_city;
				$GLOBALS['ec_cart_data']->cart_data->shipping_state = $shipping_state;
				$GLOBALS['ec_cart_data']->cart_data->shipping_zip = $shipping_zip;
				$GLOBALS['ec_cart_data']->cart_data->shipping_country = $shipping_country;
				$GLOBALS['ec_cart_data']->cart_data->shipping_phone = $shipping_phone;

				$GLOBALS['ec_cart_data']->cart_data->first_name = $first_name;
				$GLOBALS['ec_cart_data']->cart_data->last_name = $last_name;

				$GLOBALS['ec_cart_data']->cart_data->order_notes = $order_notes;

				$GLOBALS['ec_cart_data']->save_session_to_db();

				$GLOBALS['ec_user']->setup_billing_info_data( $billing_first_name, $billing_last_name, $billing_address, $billing_address2, $billing_city, $billing_state, $billing_country, $billing_zip, $billing_phone, $billing_company_name );
				$GLOBALS['ec_user']->setup_shipping_info_data( $shipping_first_name, $shipping_last_name, $shipping_address, $shipping_address2, $shipping_city, $shipping_state, $shipping_country, $shipping_zip, $shipping_phone, $shipping_company_name );
				$product = new ec_product( $products[0] );
				$quantity = 1;
				if ( isset( $_POST['ec_quantity'] ) )
					$quantity = (int) $_POST['ec_quantity'];

				/* 6.0.2: an extension's reason not to start this subscription ( e.g. an age check ), before anything is charged. */
				$wpec_subscription_errors = $this->subscription_errors( $product, $quantity );
				if ( $wpec_subscription_errors ) {
					$wpec_subscription_back = array(
						'subscription'  => $product->model_number,
						'ec_cart_error' => $wpec_subscription_errors[0]['code'],
					);
					header( 'location: ' . esc_url_raw( wpeasycart_links()->get_cart_page( 'subscription_info', $wpec_subscription_back ) ) );
					die();
				}

				if ( count( $subscription_list ) <= 0 ) {
					if ( class_exists( "ec_stripe" ) || class_exists( "ec_stripe_connect" ) ) {
						if ( get_option( 'ec_option_payment_process_method' ) == 'stripe' ) {
							$stripe = new ec_stripe();
						} else {
							$stripe = new ec_stripe_connect();
						}

						// Coupon Check ( 6.0.2: subscription_checkout_coupon() already left out every kind Stripe can't carry )
						if ( $coupon && $wpec_coupon ) {
							$coupon_exists = $stripe->get_coupon( $coupon );
							if ( $coupon_exists === false ) {
								$stripe->insert_coupon( array_merge( $wpec_coupon['stripe_coupon'], array( 'promocode_id' => $coupon ) ) );
							}

						}

						// Possibly discount the initial fee
						$initial_fee = $product->subscription_signup_fee;
						if ( $discount_total > $product->price ) {
							$remaining_discount = $discount_total - $product->price;
							$initial_fee = $initial_fee - $remaining_discount;
						}

						// Payment Information
						$payment_method = $this->get_payment_type( $this->sanatize_card_number( sanitize_text_field( $_POST['ec_card_number'] ) ) );
						$card_holder_name = stripslashes( sanitize_text_field( $_POST['ec_cart_billing_first_name'] ) ) . " " . stripslashes( sanitize_text_field( $_POST['ec_cart_billing_last_name'] ) );
						$card_number = sanitize_text_field( $_POST['ec_card_number'] );
						if ( isset( $_POST['ec_expiration_month'] ) && isset( $_POST['ec_expiration_year'] ) ) {
							$exp_month = sanitize_text_field( $_POST['ec_expiration_month'] );
							$exp_year = sanitize_text_field( $_POST['ec_expiration_year'] );
						} else {
							$exp_date = sanitize_text_field( $_POST['ec_cc_expiration'] );
							$exp_month = substr( $exp_date, 0, 2 );
							$exp_year = substr( $exp_date, 5 );
							if ( strlen( $exp_year ) == 2 ) {
								$exp_year = "20" . $exp_year;
							}
						}
						$security_code = sanitize_text_field( $_POST['ec_security_code'] );

						$card = new ec_credit_card( $payment_method, $card_holder_name, $card_number, $exp_month, $exp_year, $security_code );
						$customer_id = $GLOBALS['ec_user']->stripe_customer_id;

						// Tests vars
						$need_to_update_customer_id = false;
						$customer_insert_test = false;

						if ( $customer_id == "" ) {
							$customer_id = $stripe->insert_customer( $GLOBALS['ec_user'], NULL, $initial_fee );
							$need_to_update_customer_id = true;
						} else {
							$found_customer = $stripe->update_customer( $GLOBALS['ec_user'], $initial_fee );
							if ( !$found_customer ) { // Likely switched from test to live or to a new account, so customer id was wrong
								$customer_id = $stripe->insert_customer( $GLOBALS['ec_user'], NULL, $initial_fee );
								$need_to_update_customer_id = true;
							}
						}

						if ( $need_to_update_customer_id && $customer_id ) { // Customer inserted to stripe successfully
							$this->mysqli->update_user_stripe_id( $GLOBALS['ec_user']->user_id, $customer_id );
							$GLOBALS['ec_user']->stripe_customer_id = $customer_id;
							$customer_insert_test = true;
						} else if ( $need_to_update_customer_id && !$customer_id ) {
							$customer_insert_test = false;
						} else {
							$customer_insert_test = true;
						}

						if ( $customer_insert_test ) { // Customer inserted successfully (OR didn't need to be inserted)

							if ( isset( $_POST['stripeToken'] ) ) {
								$card_result = true;
							} else {
								$card_result = $stripe->insert_card( $GLOBALS['ec_user'], $card );
							}

							if ( $card_result ) { //Card Submitted Successfully
								$is_sandbox = apply_filters( 'wp_easycart_is_stripe_sandbox', false );
								$product_check = $stripe->get_product( $product->stripe_product_id );
								if ( ! $product_check ) {
									$stripe_product_new = $stripe->insert_product( $product );
									$product->stripe_product_id = $stripe_product_new->id;
									$product->stripe_default_price_id = $stripe_product_new->default_price;
									if ( ! $is_sandbox ) {
										$wpdb->query( $wpdb->prepare( 'UPDATE ec_product SET stripe_product_id = %s, stripe_default_price_id = %s WHERE product_id = %d', $stripe_product_new->id, $stripe_product_new->default_price, $product->product_id ) );
									} else {
										$wpdb->query( $wpdb->prepare( 'UPDATE ec_product SET stripe_product_id_sandbox = %s, stripe_default_price_id_sandbox = %s WHERE product_id = %d', $stripe_product_new->id, $stripe_product_new->default_price, $product->product_id ) );
									}
								} else {
									$price_check = $stripe->get_price( $product->stripe_default_price_id );
									if ( ! $price_check ) {
										$stripe_price_new = $stripe->insert_price( $product );
										$product->stripe_default_price_id = $stripe_price_new->id;
										if ( ! $is_sandbox ) {
											$wpdb->query( $wpdb->prepare( 'UPDATE ec_product SET stripe_default_price_id = %s WHERE product_id = %d', $stripe_price_new->id, $product->product_id ) );
										} else {
											$wpdb->query( $wpdb->prepare( 'UPDATE ec_product SET stripe_default_price_id_sandbox = %s WHERE product_id = %d', $stripe_price_new->id, $product->product_id ) );
										}
									}
								}

								if ( $product->is_shippable ) {
									$ship_price_total = $product->price * $quantity;
									$ship_weight_total = $product->weight * $quantity;
									$ship_quantity = $quantity;
								} else {
									$ship_price_total = 0;
									$ship_weight_total = 0;
									$ship_quantity = 0;
								}

								do_action( 'wpeasycart_cart_subscription_updated', $product, $quantity, $ship_weight_total ); /* 6.0.2: the total weight */

								$this->shipping = new ec_shipping( $ship_price_total, $ship_weight_total, $ship_quantity, 'RADIO', $GLOBALS['ec_user']->freeshipping, $product->length, $product->width, $product->height * $quantity, array( $product ) );
								if ( get_option( 'ec_option_collect_shipping_for_subscriptions' ) && $product->is_shippable ) {
									$this->cart->shippable_total_items = $quantity;
								}

								$this->cart->subtotal = ( $product->price + $product->subscription_signup_fee ) * $quantity;

								$this->order_totals = new ec_order_totals( $this->cart, $GLOBALS['ec_user'], $this->shipping, $this->tax, $this->discount );

								if ( $product->is_taxable || $product->vat_rate ) {
									$taxable_subtotal = 0;
									$vatable_subtotal = 0;
									if ( $product->is_taxable ) {
										$taxable_subtotal = $product->price * $quantity - $discount_total;
									}
									if ( $product->vat_rate ) {
										$vatable_subtotal = $product->price * $quantity - $discount_total;
									}

									do_action( 'wpeasycart_cart_subscription_pre_tax', $product, $quantity, $shipping_total, $handling_total, $discount_total );

									if ( get_option( 'ec_option_tax_cloud_api_id' ) != "" && get_option( 'ec_option_tax_cloud_api_key' ) != "" ) {
										wpeasycart_taxcloud()->setup_subscription_for_tax( $product, $quantity, $discount_total );
									}
									if ( function_exists( 'wpeasycart_taxjar' ) && wpeasycart_taxjar()->is_enabled() ) {
										wpeasycart_taxjar()->setup_subscription_for_tax( $product, $quantity, $discount_total );
									}
									$this->tax = new ec_tax( $product->price * $quantity, $taxable_subtotal, $vatable_subtotal, $GLOBALS['ec_user']->shipping->state, $GLOBALS['ec_user']->shipping->country, $GLOBALS['ec_user']->taxfree, $this->shipping->get_shipping_price( ( $this->product->handling_price_each * $quantity ) + $this->product->handling_price ), $this->cart );
								} else {
									$this->tax = new ec_tax( 0, 0, 0, $GLOBALS['ec_user']->shipping->state, $GLOBALS['ec_user']->shipping->country, $GLOBALS['ec_user']->taxfree, $this->shipping->get_shipping_price( ( $this->product->handling_price_each * $quantity ) + $this->product->handling_price ), $this->cart );
								}

								$this->order_totals = new ec_order_totals( $this->cart, $GLOBALS['ec_user'], $this->shipping, $this->tax, $this->discount );
								if ( class_exists( 'wp_easycart_tax_providers' ) && wp_easycart_tax_providers::blocks_checkout( 'subscription' ) ) { /* 6.0.2: the tax service could not work out the tax */
									header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'subscription_info', array( 'subscription' => esc_attr( $model_number ), 'ec_cart_error' => 'tax_unavailable' ) ) ) );
									die();
								}

								if ( get_option( 'ec_option_collect_shipping_for_subscriptions' ) && $this->order_totals->shipping_total > 0 ) {
									$stripe->update_customer( $GLOBALS['ec_user'], $this->order_totals->shipping_total );
								}

								$prorate = $product->subscription_prorate;
								$trial_end_date = NULL;
								if ( $product->trial_period_days > 0 ) {
									$trial_end_date = strtotime( "+" . $product->trial_period_days . " days" );
								}
								$stripe_response = $stripe->insert_subscription( $product, $GLOBALS['ec_user'], $card, $coupon, $prorate, $trial_end_date, $quantity, number_format( $this->tax->get_tax_rate(), 2, '.', '' ) );

								if ( $stripe_response ) { // Subscription added successfully
									$subscription_id = $this->mysqli->insert_stripe_subscription( $stripe_response, $product, $GLOBALS['ec_user'], $card, $quantity );
									$subscription_row = $this->mysqli->get_subscription_row( $subscription_id );
									$coupon_promocode_id = "";
									if ( isset( $coupon_row ) && $coupon_row ) {
										$coupon_promocode_id = $coupon_row->promocode_id;
									}
									$this->mysqli->update_user_default_card( $GLOBALS['ec_user'], $card );
									$subscription = new ec_subscription( $subscription_row );

									if ( $product->trial_period_days > 0 ) {
										$subscription->send_trial_start_email( $GLOBALS['ec_user'] );
									} else {
										// Get Shipping Method to Save
										$shipping_method = "";
										if ( !get_option( 'ec_option_use_shipping' ) || $this->order_totals->shipping_total <= 0 ) {
											$shipping_method = "";
										} else if ( $this->shipping->shipping_method == "fraktjakt" ) {
											$shipping_method = $this->shipping->get_selected_shipping_method();
										} else if ( $GLOBALS['ec_cart_data']->cart_data->shipping_method != "" && $GLOBALS['ec_cart_data']->cart_data->shipping_method != "standard" ) {
											$shipping_method = $this->mysqli->get_shipping_method_name( $GLOBALS['ec_cart_data']->cart_data->shipping_method );
										} else if ( ( $this->shipping->shipping_method == "price" || $this->shipping->shipping_method == "weight" ) && $GLOBALS['ec_cart_data']->cart_data->expedited_shipping != "" ) {
											$shipping_method = wp_easycart_language()->get_text( "cart_estimate_shipping", "cart_estimate_shipping_express" );
										} else {
											$shipping_method = wp_easycart_language()->get_text( "cart_estimate_shipping", "cart_estimate_shipping_standard" );
										}

										$order_id = $this->mysqli->insert_subscription_order( $product, $GLOBALS['ec_user'], $card, $subscription_id, $coupon_promocode_id, $order_notes, $this->subscription_option1_name, $this->subscription_option2_name, $this->subscription_option3_name, $this->subscription_option4_name, $this->subscription_option5_name, $this->subscription_option1_label, $this->subscription_option2_label, $this->subscription_option3_label, $this->subscription_option4_label, $this->subscription_option5_label, $quantity, $this->order_totals, $shipping_method, $this->tax, $discount_total );	
										do_action( 'wpeasycart_subscription_first_order_inserted', $order_id );
										do_action( 'wpeasycart_order_paid', $order_id );
										$order_row = $this->mysqli->get_order_row( $order_id, $GLOBALS['ec_cart_data']->cart_data->user_id );
										$order = new ec_orderdisplay( $order_row );
										$order_details = $this->mysqli->get_order_details( $order_id, $GLOBALS['ec_cart_data']->cart_data->user_id );
										$subscription->send_email_receipt( $GLOBALS['ec_user'], $order, $order_details );
										$this->mysqli->update_product_stock( $product->product_id, $quantity );

										$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log( order_id, order_log_key ) VALUES( %d, "order-stock-update" )', $order_id ) );
										$order_log_id = $wpdb->insert_id;
										$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "product_id", %s )', $order_log_id, $order_id, $product->product_id ) );
										$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "quantity", %s )', $order_log_id, $order_id, '-' . $quantity ) );

										if ( $subscription->payment_duration > 0 && $subscription->payment_duration == 1 ) {
											$stripe->cancel_subscription( $GLOBALS['ec_user'], $subscription->stripe_subscription_id );
											$this->mysqli->cancel_stripe_subscription( $subscription->stripe_subscription_id );
										}

									}
									do_action( 'wp_easycart_subscription_started', $subscription_id );

									// Unset Variables Entered
									$GLOBALS['ec_cart_data']->checkout_session_complete();

									if ( $product->trial_period_days > 0 ) {
										header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'subscription_details', array( 'subscription_id' => (int) $subscription_id ) ) ) );
									} else {
										header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_success', array( 'order_id' => (int) $order_id ) ) ) );
									}
								} else {
									header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'subscription_info', array( 'subscription' => esc_attr( $model_number ), 'ec_cart_error' => 'subscription_failed' ) ) ) );
								}// Close check for subscription insertion
							} else {
								header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'subscription_info', array( 'subscription' => esc_attr( $model_number ), 'ec_cart_error' => 'card_error' ) ) ) );
							}// Close check for card insertion
						} else {
							header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'subscription_info', array( 'subscription' => esc_attr( $model_number ), 'ec_cart_error' => 'user_insert_error' ) ) ) );
						}// Close check for customer insertion to stripe check
					} else if ( class_exists( 'ec_paypal' ) ) { // Close check for PayPal
						$coupon_promocode_id = "";
						if ( isset( $coupon_row ) && $coupon_row )
							$coupon_promocode_id = $coupon_row->promocode_id;

						$order_id = $this->mysqli->insert_paypal_subscription_order( $product, $GLOBALS['ec_user'], $coupon_promocode_id, $order_notes, $this->subscription_option1_name, $this->subscription_option2_name, $this->subscription_option3_name, $this->subscription_option4_name, $this->subscription_option5_name, $this->subscription_option1_label, $this->subscription_option2_label, $this->subscription_option3_label, $this->subscription_option4_label, $this->subscription_option5_label, $quantity );
						$paypal = new ec_paypal();
						$paypal->display_subscription_form( $order_id, $GLOBALS['ec_user'], $product );

						// Unset Variables Entered
						$GLOBALS['ec_cart_data']->cart_data->subscription_option1 = "";
						$GLOBALS['ec_cart_data']->cart_data->subscription_option2 = "";
						$GLOBALS['ec_cart_data']->cart_data->subscription_option3 = "";
						$GLOBALS['ec_cart_data']->cart_data->subscription_option4 = "";
						$GLOBALS['ec_cart_data']->cart_data->subscription_option5 = "";

						$GLOBALS['ec_cart_data']->cart_data->subscription_advanced_option = "";

						$GLOBALS['ec_cart_data']->cart_data->billing_first_name = "";
						$GLOBALS['ec_cart_data']->cart_data->billing_last_name = "";
						$GLOBALS['ec_cart_data']->cart_data->billing_address_line_1 = "";
						$GLOBALS['ec_cart_data']->cart_data->billing_address_line_2 = "";
						$GLOBALS['ec_cart_data']->cart_data->billing_city = "";
						$GLOBALS['ec_cart_data']->cart_data->billing_state = "";
						$GLOBALS['ec_cart_data']->cart_data->billing_zip = "";
						$GLOBALS['ec_cart_data']->cart_data->billing_country = "";
						$GLOBALS['ec_cart_data']->cart_data->billing_phone = "";

						$GLOBALS['ec_cart_data']->cart_data->shipping_selector = "";
						$GLOBALS['ec_cart_data']->cart_data->shipping_first_name = "";
						$GLOBALS['ec_cart_data']->cart_data->shipping_last_name = "";
						$GLOBALS['ec_cart_data']->cart_data->shipping_address_line_1 = "";
						$GLOBALS['ec_cart_data']->cart_data->shipping_address_line_2 = "";
						$GLOBALS['ec_cart_data']->cart_data->shipping_city = "";
						$GLOBALS['ec_cart_data']->cart_data->shipping_state = "";
						$GLOBALS['ec_cart_data']->cart_data->shipping_zip = "";
						$GLOBALS['ec_cart_data']->cart_data->shipping_country = "";
						$GLOBALS['ec_cart_data']->cart_data->shipping_phone = "";

						$GLOBALS['ec_cart_data']->cart_data->use_shipping = "";
						$GLOBALS['ec_cart_data']->cart_data->shipping_method = "";
						$GLOBALS['ec_cart_data']->cart_data->expedited_shipping = ""; 

						if ( $GLOBALS['ec_cart_data']->cart_data->user_id == "" ) {
							$GLOBALS['ec_cart_data']->cart_data->email = "";
							$GLOBALS['ec_cart_data']->cart_data->first_name = "";
							$GLOBALS['ec_cart_data']->cart_data->last_name = "";
						}

						$GLOBALS['ec_cart_data']->cart_data->create_account = "";
						$GLOBALS['ec_cart_data']->cart_data->coupon_code = "";
						$GLOBALS['ec_cart_data']->cart_data->giftcard = "";
						$GLOBALS['ec_cart_data']->cart_data->order_notes = "";

						$GLOBALS['ec_cart_data']->clear_db_session();

						$session = wpeasycart_session();
						$session->clear_cart_cookie();
						$session_cart_id = $session->generate_unique_cart_id();
						$GLOBALS['ec_cart_id'] = $session_cart_id;
						$session->set_cart_cookie( $session_cart_id );

						$GLOBALS['ec_cart_data'] = new ec_cart_data( $GLOBALS['ec_cart_id'] );

						die();

					} else { // Close check for paypal
						$GLOBALS['ec_cart_data']->save_session_to_db();
						header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'subscription_info', array( 'subscription' => esc_attr( $model_number ), 'ec_cart_error' => 'subscription_setup_error' ) ) ) );
					}

				} else {
					$GLOBALS['ec_cart_data']->save_session_to_db();
					header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'subscription_info', array( 'subscription' => esc_attr( $model_number ), 'ec_cart_error' => 'already_subscribed' ) ) )  );
				}// Close check for already subscribed error
			} else {
				$GLOBALS['ec_cart_data']->save_session_to_db();
				header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'subscription_info', array( 'subscription' => esc_attr( $model_number ), 'ec_cart_error' => 'subscription_not_found' ) ) ) );
			}// Close check for subscription existing
		} else {
			$GLOBALS['ec_cart_data']->save_session_to_db();
			header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'subscription_info', array( 'subscription' => esc_attr( $model_number ), 'ec_cart_error' => 'email_exists' ) ) ) );
		}// Close user exists error for guest checkout
	}

	/**
	 * The product inquiry form: checks it, emails it to the store ( and a copy to the shopper when asked ), then sends the
	 * shopper back to the page the form was on, where the add to cart templates show "Your inquiry was sent".
	 *
	 * 6.0.2: the form always posts here ( a product's inquiry URL is only ever a link now ); the store copy goes to the order
	 * notification addresses, else the site's admin email; reCAPTCHA is asked for only when the form carried it ( enabled
	 * with a site key ); the shopper returns to the page they were on ( an Elementor page too ) with the product's model
	 * number, which the add to cart templates need to show the message; send_inquiry() ( an add to cart post for an inquiry
	 * product ) runs here as well.
	 *
	 * @param object|null $verified_product Product row when process_add_to_cart_v3() already verified its own form nonce.
	 */
	private function process_send_inquiry( $verified_product = null ) {
		if ( null === $verified_product && ( ! isset( $_POST['ec_cart_form_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ec_cart_form_nonce'] ) ), 'wp-easycart-send-inquiry' ) ) ) {
			header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( '', array( 'cart_error' => 'invalid_nonce' ) ) ) );
			die();
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified above, or by process_add_to_cart_v3() for $verified_product.
		/* 6.0.0: every posted field is read and sanitised once here, and the inquiry abuse
		 * checks ( honeypot, time to submit, rate limits, content rules, sign-in ) run before
		 * the reCAPTCHA round trip, so obvious spam never reaches Google or the mailer. */
		$inquiry_email   = isset( $_POST['ec_inquiry_email'] ) ? stripslashes( sanitize_email( wp_unslash( $_POST['ec_inquiry_email'] ) ) ) : '';
		$inquiry_name    = isset( $_POST['ec_inquiry_name'] ) ? stripslashes( sanitize_text_field( wp_unslash( $_POST['ec_inquiry_name'] ) ) ) : '';
		$inquiry_message = isset( $_POST['ec_inquiry_message'] ) ? stripslashes( sanitize_textarea_field( wp_unslash( $_POST['ec_inquiry_message'] ) ) ) : '';
		$model_number    = isset( $_POST['ec_inquiry_model_number'] ) ? sanitize_text_field( wp_unslash( $_POST['ec_inquiry_model_number'] ) ) : '';
		$send_copy       = isset( $_POST['ec_inquiry_send_copy'] ) ? true : false;
		if ( null !== $verified_product ) {
			$product = $verified_product;
		} else {
			$product = ( '' !== $model_number ) ? $this->mysqli->get_product( $model_number ) : false;
		}
		/* The model number the page was drawn with ( a variant's SKU replaces it below, for the email only ). */
		$return_model = ( is_object( $product ) && isset( $product->model_number ) ) ? (string) $product->model_number : $model_number;

		if ( class_exists( 'wp_easycart_inquiry_guard' ) ) {
			$inquiry_block = wp_easycart_inquiry_guard::check( $product, $inquiry_name, $inquiry_email, $inquiry_message );
			if ( '' !== $inquiry_block ) {
				wp_easycart_inquiry_guard::reject( $product, $inquiry_block, $inquiry_email );
				return;
			}
		}

		$recaptcha_valid = true;
		if ( wp_easycart_recaptcha_ready() ) {
			$db = new ec_db_admin();
			$recaptcha_response = isset( $_POST['ec_grecaptcha_response_inquiry'] ) ? sanitize_text_field( wp_unslash( $_POST['ec_grecaptcha_response_inquiry'] ) ) : '';

			$data = array(
				"secret"	=> get_option( 'ec_option_recaptcha_secret_key' ),
				"response"	=> $recaptcha_response
			);

			$request = new WP_Http;
			$response = $request->request(
				"https://www.google.com/recaptcha/api/siteverify",
				array(
					'method' => 'POST',
					'body' => http_build_query( $data ),
					'timeout' => 30
				)
			);
			if ( is_wp_error( $response ) ) {
				$error_message = $response->get_error_message();
				$db->insert_response( 0, 1, "GOOGLE RECAPTCHA CURL ERROR", $error_message );
				$response = (object) array( "error" => $error_message );
			} else {
				$response = json_decode( $response['body'] );
				$db->insert_response( 0, 0, "Google Recaptcha Response", print_r( $response, true ) );
			}

			$recaptcha_valid = ( isset( $response->success ) && $response->success ) ? true : false;
		}

		/* 6.0.0: a failed reCAPTCHA used to end the request silently, leaving the shopper on a
		 * page that looked as though nothing had happened. Report it the same way as every
		 * other refused inquiry. */
		if ( ! $recaptcha_valid ) {
			if ( class_exists( 'wp_easycart_inquiry_guard' ) ) {
				wp_easycart_inquiry_guard::reject( $product, 'captcha', $inquiry_email );
			}
			return;
		}
		if ( ! is_object( $product ) || empty( $product->product_id ) ) {
			return;
		}

		/* 6.0.0: random, unguessable upload folder ( was rand( 1000000, 999999999 ) ). */
		$file_temp_num = class_exists( 'wp_easycart_customer_uploads' ) ? wp_easycart_customer_uploads::new_folder_name( 'inquiry' ) : 'inquiry-' . wp_generate_password( 32, false, false );
		/* 6.0.2: every option variable exists for the email template, whatever the product's option type. */
		$option_vals = array();
		$option1 = $option2 = $option3 = $option4 = $option5 = '';
		$option1_option = $option2_option = $option3_option = $option4_option = $option5_option = false;
		if ( $product->use_both_option_types || $product->use_advanced_optionset ) {
			$option_vals = $this->get_advanced_option_vals( $product->product_id, $file_temp_num );
			if ( ! empty( $this->option_input_errors ) ) {
				$this->reject_option_input( $product );
				return;
			}
		}
		if ( $product->use_both_option_types || ! $product->use_advanced_optionset ) {
			if ( isset( $_POST['ec_option1'] ) ) {
				$option1 = $GLOBALS['ec_options']->get_optionitem( (int) $_POST['ec_option1'] );
				$option1_option = ( is_object( $option1 ) && isset( $option1->option_id ) ) ? $GLOBALS['ec_options']->get_option( (int) $option1->option_id ) : false;
			}
			if ( isset( $_POST['ec_option2'] ) ) {
				$option2 = $GLOBALS['ec_options']->get_optionitem( (int) $_POST['ec_option2'] );
				$option2_option = ( is_object( $option2 ) && isset( $option2->option_id ) ) ? $GLOBALS['ec_options']->get_option( (int) $option2->option_id ) : false;
			}
			if ( isset( $_POST['ec_option3'] ) ) {
				$option3 = $GLOBALS['ec_options']->get_optionitem( (int) $_POST['ec_option3'] );
				$option3_option = ( is_object( $option3 ) && isset( $option3->option_id ) ) ? $GLOBALS['ec_options']->get_option( (int) $option3->option_id ) : false;
			}
			if ( isset( $_POST['ec_option4'] ) ) {
				$option4 = $GLOBALS['ec_options']->get_optionitem( (int) $_POST['ec_option4'] );
				$option4_option = ( is_object( $option4 ) && isset( $option4->option_id ) ) ? $GLOBALS['ec_options']->get_option( (int) $option4->option_id ) : false;
			}
			if ( isset( $_POST['ec_option5'] ) ) {
				$option5 = $GLOBALS['ec_options']->get_optionitem( (int) $_POST['ec_option5'] );
				$option5_option = ( is_object( $option5 ) && isset( $option5->option_id ) ) ? $GLOBALS['ec_options']->get_option( (int) $option5->option_id ) : false;
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		global $wpdb;
		$variant_row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_optionitemquantity WHERE product_id = %d AND optionitem_id_1 = %d AND optionitem_id_2 = %d AND optionitem_id_3 = %d AND optionitem_id_4 = %d AND optionitem_id_5 = %d', $product->product_id, ( ( is_object( $option1 ) && isset( $option1->optionitem_id ) ) ? $option1->optionitem_id : 0 ), ( ( is_object( $option2 ) && isset( $option2->optionitem_id ) ) ? $option2->optionitem_id : 0 ), ( ( is_object( $option3 ) && isset( $option3->optionitem_id ) ) ? $option3->optionitem_id : 0 ), ( ( is_object( $option4 ) && isset( $option4->optionitem_id ) ) ? $option4->optionitem_id : 0 ), ( ( is_object( $option5 ) && isset( $option5->optionitem_id ) ) ? $option5->optionitem_id : 0 ) ) );
		if ( $variant_row ) {
			if ( '' != $variant_row->sku ) {
				$product->model_number = $variant_row->sku;
			}
		}

		if ( '' == $inquiry_email || '' == $inquiry_name || '' == $inquiry_message ) {
			return;
		}

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

		$filter_options = (object) array(
			'product' => $product,
			'inquiry_name' => $inquiry_name,
			'inquiry_email' => $inquiry_email,
			'inquiry_message' => $inquiry_message,
			'send_copy' => $send_copy,
			'option1' => $option1,
			'option1_option' => $option1_option,
			'option2' => $option2,
			'option2_option' => $option2_option,
			'option3' => $option3,
			'option3_option' => $option3_option,
			'option4' => $option4,
			'option4_option' => $option4_option,
			'option5' => $option5,
			'option5_option' => $option5_option,
			'file_temp_num' => $file_temp_num,
			'option_vals' => $option_vals,
			'email_logo_url' => $email_logo_url,
			'store_page' => $store_page,
			'permalink_divider' => $permalink_divider,
		);

		/* 6.0.2: the store's copy goes where the store gets its order notifications, else to the site's admin email ( with none
		 * set, the inquiry used to go nowhere ). */
		$store_email = trim( stripslashes( (string) get_option( 'ec_option_bcc_email_addresses' ) ) );
		if ( '' === $store_email ) {
			$store_email = (string) get_option( 'admin_email' );
		}
		/**
		 * Where a product inquiry is emailed ( comma separated addresses ).
		 *
		 * @since 6.0.2
		 *
		 * @param string $store_email Addresses.
		 * @param object $product     Product row.
		 */
		$store_email = (string) apply_filters( 'wpeasycart_inquiry_email_recipient', $store_email, $product );

		$headers   = array();
		$headers[] = "MIME-Version: 1.0";
		$headers[] = "Content-Type: text/html; charset=utf-8";
		$headers[] = "From: " . stripslashes( get_option( 'ec_option_password_from_email' ) );
		$headers[] = "Reply-To: " . stripslashes( get_option( 'ec_option_password_from_email' ) );
		$headers[] = "X-Mailer: PHP/".phpversion();

		$headers2   = array();
		$headers2[] = "MIME-Version: 1.0";
		$headers2[] = "Content-Type: text/html; charset=utf-8";
		$headers2[] = "From: " . stripslashes( get_option( 'ec_option_password_from_email' ) );
		$headers2[] = "Reply-To: " . $inquiry_email;
		$headers2[] = "X-Mailer: PHP/" . phpversion();

		$has_product_options = false;

		ob_start();
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_inquiry_email.php' ) ) {
			include EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_inquiry_email.php';
		} else {
			include EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_inquiry_email.php';
		}
		$message = $admin_message = ob_get_clean();
		/* 6.0.0: the shopper copy never carries upload download links ( file name only ); the admin copy keeps them. */
		if ( class_exists( 'wp_easycart_admin_order_uploads' ) ) {
			$message = wp_easycart_admin_order_uploads::strip_links( $message );
		}
		$message = apply_filters( 'wpeasycart_inquiry_email_content', $message, $filter_options );
		$admin_message = apply_filters( 'wpeasycart_inquiry_email_admin_content', $admin_message, $filter_options );
		$subject = $admin_subject = wp_easycart_language()->get_text( "product_details", "product_details_inquiry_title" );
		$subject = apply_filters( 'wpeasycart_inquiry_email_subject', $subject );
		$admin_subject = apply_filters( 'wpeasycart_inquiry_email_admin_subject', $admin_subject );

		$email_send_method = get_option( 'ec_option_use_wp_mail' );
		$email_send_method = apply_filters( 'wpeasycart_email_method', $email_send_method );

		if ( $email_send_method == "1" ) {
			if ( $send_copy ) {
				wp_mail( $inquiry_email, $subject, $message, implode("\r\n", $headers ) );
			}
			wp_mail( $store_email, $admin_subject, $admin_message, implode( "\r\n", $headers2 ) );
		} else if ( $email_send_method == "0" ) {
			$mailer = new wpeasycart_mailer();
			if ( $send_copy ) {
				$mailer->send_order_email( $inquiry_email, $subject, $message );
			}
			$mailer->send_order_email( $store_email, $admin_subject, $admin_message );
		} else {
			if ( $send_copy ) {
				do_action( 'wpeasycart_custom_inquiry_email', stripslashes( get_option( 'ec_option_order_from_email' ) ), $inquiry_email, $store_email, $subject, $message );
			}
			do_action( 'wpeasycart_custom_admin_inquiry_email', stripslashes( get_option( 'ec_option_order_from_email' ) ), $inquiry_email, $store_email, $admin_subject, $admin_message );
		}

		/* 6.0.0: only a sent inquiry counts towards the per IP / email / product limits. */
		if ( class_exists( 'wp_easycart_inquiry_guard' ) ) {
			wp_easycart_inquiry_guard::record( ( isset( $product->product_id ) ? (int) $product->product_id : 0 ), $inquiry_email );
		}

		/**
		 * A product inquiry was emailed.
		 *
		 * @since 6.0.2
		 *
		 * @param object $product        Product row ( model_number is the chosen variant's SKU when it has one ).
		 * @param object $filter_options The inquiry ( name, email, message, options ), as the email filters get it.
		 */
		do_action( 'wpeasycart_inquiry_sent', $product, $filter_options );

		/* 6.0.2: back to the page the form was on ( an Elementor page too ), with the model number the templates match. */
		if ( get_option( 'ec_option_use_old_linking_style' ) || empty( $product->post_id ) ) {
			$fallback = $this->store_page . $this->permalink_divider . 'model_number=' . rawurlencode( $return_model );
		} else {
			$fallback = get_permalink( (int) $product->post_id );
		}
		if ( ! $fallback ) {
			$fallback = home_url( '/' );
		}
		$return_url = wp_get_referer();
		$return_url = ( $return_url ) ? wp_validate_redirect( $return_url, $fallback ) : $fallback;
		$return_url = remove_query_arg( array( 'ec_store_success', 'model', 'ec_inquiry_error', 'ec_inquiry_ref', 'ec_option_error', 'ec_option_error_reason' ), $return_url );
		wp_safe_redirect(
			add_query_arg(
				array(
					'ec_store_success' => 'inquiry_sent',
					'model'            => rawurlencode( $return_model ),
				),
				$return_url
			)
		);
		exit;
	}

	private function process_deconetwork_add_to_cart() {

		$deco_product_id = isset( $_GET['ec_product_id'] ) ? (int) $_GET['ec_product_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- DecoNetwork's return link; the value only labels the add announced below.
		$deco_before = ( $deco_product_id && function_exists( 'wp_easycart_cart_add_snapshot' ) ) ? wp_easycart_cart_add_snapshot( $deco_product_id ) : 0;
		$deco_tempcart_id = $this->mysqli->deconetwork_add_to_cart();
		if ( $deco_tempcart_id && $deco_product_id && function_exists( 'wp_easycart_announce_cart_item_added' ) ) {
			wp_easycart_announce_cart_item_added( $deco_tempcart_id, $deco_product_id, $deco_before, 'deconetwork' ); /* 6.0.2 */
		}
		header( "location: " . $this->cart_page );

	}

	public function process_subscribe_v3() {
		$product_id = (int) $_POST['product_id'];
		if ( ! wp_verify_nonce( sanitize_text_field( $_POST['ec_cart_form_nonce'] ), 'wp-easycart-subscribe-' . $product_id ) ) {
			header( "location: " . $this->cart_page . $this->permalink_divider . 'cart_error=invalid_nonce' );
			die();
		}

		$cart_id = $GLOBALS['ec_cart_data']->ec_cart_id;
		$product = $this->mysqli->get_product( "", $product_id );
		$use_advanced_optionset = $product->use_advanced_optionset;
		$use_both_option_types = $product->use_both_option_types;
		$quantity = 1;
		if ( isset( $_POST['ec_quantity'] ) ) {
			$quantity = (int) $_POST['ec_quantity'];
		}

		$GLOBALS['ec_cart_data']->cart_data->subscription_quantity = $quantity;

		$GLOBALS['ec_cart_data']->cart_data->subscription_option1 = "";
		$GLOBALS['ec_cart_data']->cart_data->subscription_option2 = "";
		$GLOBALS['ec_cart_data']->cart_data->subscription_option3 = "";
		$GLOBALS['ec_cart_data']->cart_data->subscription_option4 = "";
		$GLOBALS['ec_cart_data']->cart_data->subscription_option5 = "";

		if ( ! $use_advanced_optionset || $use_both_option_types ) {
			if ( isset( $_POST['ec_option1'] ) ) {
				$GLOBALS['ec_cart_data']->cart_data->subscription_option1 = (int) $_POST['ec_option1'];
			} else {
				$GLOBALS['ec_cart_data']->cart_data->subscription_option1 = '';
			}

			if ( isset( $_POST['ec_option2'] ) ) {
				$GLOBALS['ec_cart_data']->cart_data->subscription_option2 = (int) $_POST['ec_option2'];
			} else {
				$GLOBALS['ec_cart_data']->cart_data->subscription_option2 = '';
			}

			if ( isset( $_POST['ec_option3'] ) ) {
				$GLOBALS['ec_cart_data']->cart_data->subscription_option3 = (int) $_POST['ec_option3'];
			} else {
				$GLOBALS['ec_cart_data']->cart_data->subscription_option3 = '';
			}

			if ( isset( $_POST['ec_option4'] ) ) {
				$GLOBALS['ec_cart_data']->cart_data->subscription_option4 = (int) $_POST['ec_option4'];
			} else {
				$GLOBALS['ec_cart_data']->cart_data->subscription_option4 = '';
			}

			if ( isset( $_POST['ec_option5'] ) ) {
				$GLOBALS['ec_cart_data']->cart_data->subscription_option5 = (int) $_POST['ec_option5'];
			} else {
				$GLOBALS['ec_cart_data']->cart_data->subscription_option5 = '';
			}
		}

		if ( $use_advanced_optionset || $use_both_option_types ) {
			$option_vals = $this->get_advanced_option_vals( $product_id, $cart_id );
			if ( ! empty( $this->option_input_errors ) ) {
				$this->reject_option_input( $product );
				return;
			}
		}

		$GLOBALS['ec_cart_data']->cart_data->subscription_advanced_option = maybe_serialize( $option_vals );
		$GLOBALS['ec_cart_data']->save_session_to_db();

		header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'subscription_info', array( 'subscription' => esc_attr( $product->model_number ) ) ) ) );

	}

	private function process_update_subscription_quantity() {
		$product_id = (int) $_POST['product_id'];
		if ( ! wp_verify_nonce( sanitize_text_field( $_POST['ec_cart_form_nonce'] ), 'wp-easycart-cart-subscription-update-item-' . $product_id ) ) {
			header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'subscription_info', array( 'cart_error' => 'invalid_nonce' ) ) ) );
			die();
		}
		$product = $this->mysqli->get_product( "", $product_id );
		if ( $product ) {
			$quantity = (int) $_POST[ 'ec_quantity' ];
			$GLOBALS['ec_cart_data']->cart_data->subscription_quantity = $quantity;
		}
		$GLOBALS['ec_cart_data']->save_session_to_db();
		header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'subscription_info', array( 'subscription' => esc_attr( $product->model_number ) ) ) ) );
	}

	private function process_stripe_redirect_action() {
		if ( ! isset( $_GET['wpecnonce'] ) ) {
			return false;
		}

		wpeasycart_session()->handle_session();
		$session_id = $GLOBALS['ec_cart_data']->ec_cart_id;

		if ( ! wp_verify_nonce( sanitize_text_field( $_GET['wpecnonce'] ), 'wp-easycart-stripe-pi-order-complete-' . $session_id ) ) {
			die();
		}

		if ( ! isset( $_GET['payment_intent'] ) ) {
			return false;
		}

		if ( ! isset( $_GET['payment_intent_client_secret'] ) ) {
			return false;
		}

		// Get Payment Intent Info
		$payment_intent_id = ( isset( $_GET['payment_intent'] ) ) ? sanitize_text_field( $_GET['payment_intent'] ) : '';
		$payment_intent_client_secret = htmlspecialchars( sanitize_text_field( $_GET['payment_intent_client_secret'] ), ENT_QUOTES );

		if ( get_option( 'ec_option_payment_process_method' ) == 'stripe' ) {
			$stripe = new ec_stripe();
		} else {
			$stripe = new ec_stripe_connect();
		}
		$payment_intent = $stripe->get_payment_intent( $payment_intent_id );

		// Verify Payment Intent
		if ( ! $payment_intent ) {
			return false;
		}

		if ( ! in_array( $payment_intent->status, array( 'succeeded', 'processing', 'requires_capture', 'canceled' ) ) ) {
			return false;
		}

		global $wpdb;
		$ec_db_admin = new ec_db_admin();
		$order = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM ec_order WHERE gateway_transaction_id = %s", $payment_intent->id . ':' . $payment_intent->client_secret ) );
		if ( $order ) {
			$order_id = $order->order_id;
		} else {
			sleep( 5 ); // Process waits to prevent timing issues and double checks the order.
			$order = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM ec_order WHERE gateway_transaction_id = %s", $payment_intent->id . ':' . $payment_intent->client_secret ) );
			if ( $order ) {
				$order_id = $order->order_id;
			} else {
				$source = array(
					'id' => $payment_intent_id,
					'client_secret' => $payment_intent_client_secret,
				);
				$order_id = $this->insert_ideal_order( $source, $payment_intent );
				$payment_method_name = $order->payment_method;
				$stripe->update_payment_intent_description( $payment_intent_id, $order_id );
				if ( $payment_intent && is_object( $payment_intent ) && isset( $payment_intent->payment_method ) ) {
					$payment_method = $stripe->get_payment_method( $payment_intent->payment_method );
					if ( $payment_method && is_object( $payment_method ) && isset( $payment_method->type ) ) {
						$payment_method_name = $payment_method->type;
					}
				}

				$order_status = 6;
				if ( $payment_intent->status == 'succeeded' ) {
					$order_status = 3;
				} else if ( $payment_intent->status == 'requires_capture' ) {
					$order_status = 12;
				} else if ( $payment_intent->status == 'processing' ) {
					$order_status = 12;
				} else if ( $payment_intent->status == 'canceled' ) {
					$order_status = 19;
				}
				$wpdb->get_row( $wpdb->prepare( "UPDATE ec_order SET orderstatus_id = %d, payment_method = %s WHERE order_id = %d", $order_status, $payment_method_name, (int) $order_id ) );

				$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log( order_id, order_log_key ) VALUES( %d, "order-status-update" )', $order_id ) );
				$order_log_id = $wpdb->insert_id;
				$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "orderstatus_id", %s )', $order_log_id, $order_id, $order_status ) );

				if ( $order_status == 3 ) {
					$order_row = $ec_db_admin->get_order_row_admin( $order_id );
					$orderdetails = $ec_db_admin->get_order_details_admin( $order_id );
					foreach( $orderdetails as $orderdetail ) {
						$product = $wpdb->get_row( $wpdb->prepare( "SELECT ec_product.* FROM ec_product WHERE ec_product.product_id = %d", $orderdetail->product_id ) );
						if ( $product ) {
							if ( $product->use_optionitem_quantity_tracking ) {
								$ec_db_admin->update_quantity_value( $orderdetail->quantity, $orderdetail->product_id, $orderdetail->optionitem_id_1, $orderdetail->optionitem_id_2, $orderdetail->optionitem_id_3, $orderdetail->optionitem_id_4, $orderdetail->optionitem_id_5 );
							}
							$ec_db_admin->update_product_stock( $orderdetail->product_id, $orderdetail->quantity );
							$this->mysqli->update_details_stock_adjusted( $orderdetail->orderdetail_id );
							$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log( order_id, order_log_key ) VALUES( %d, "order-stock-update" )', $order_id ) );
							$order_log_id = $wpdb->insert_id;
							$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "product_id", %s )', $order_log_id, $order_id, $orderdetail->product_id ) );
							$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "quantity", %s )', $order_log_id, $order_id, '-' . $orderdetail->quantity ) );
						}
					}
					do_action( 'wpeasycart_order_paid', $order_id );
					$order_display = new ec_orderdisplay( $order_row, true, true );
					$order_display->send_email_receipt();
					$order_display->send_gift_cards();
				} else {
					do_action( 'wpeasycart_order_complete', $order_id, $order_status );
				}
			}
		}
		$ec_db_admin->clear_tempcart( $GLOBALS['ec_cart_data']->ec_cart_id );
		$GLOBALS['ec_cart_data']->checkout_session_complete();
		$GLOBALS['ec_cart_data']->save_session_to_db();

		wp_redirect( esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_success', array( 'order_id' => (int) $order_id ) ) ) );
	}
	/* END PROCESS FORM SUBMISSION FUNCTIONS */

	/**
	 * Store one customer file upload in its private folder.
	 *
	 * 6.0.0: stored by wp_easycart_customer_uploads, which writes straight into
	 * products/uploads/<folder>/ ( no copy in the public WordPress uploads folder ) and keeps the
	 * deny rules in place. The file name stays sanitize_text_field() of the browser's name, the
	 * value the cart and order record for the option.
	 *
	 * @param string $upload_folder     Cart session id, or a random inquiry folder.
	 * @param string $upload_field_name $_FILES key.
	 * @param bool   $check_only        Validate type / size / PHP upload status without storing.
	 * @param object $optionset         6.0.0: the file option set; its allowed file types ( option_meta['file_types'] ) replace the store default when set.
	 * @return string '' stored ( or valid ) | 'none' nothing chosen | 'type' | 'size' | 'upload' | 'storage'.
	 */
	private function upload_customer_file( $upload_folder, $upload_field_name, $check_only = false, $optionset = null ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- every caller verifies its form nonce first; the entry is validated by wp_easycart_customer_uploads::store_file() and wp_handle_upload().
		if ( ! isset( $_FILES[ $upload_field_name ] ) || ! is_array( $_FILES[ $upload_field_name ] ) ) {
			return 'none';
		}
		if ( ! class_exists( 'wp_easycart_customer_uploads' ) ) {
			return 'storage';
		}
		$upload_file = $_FILES[ $upload_field_name ]; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- file array; name and type are sanitized in check(), tmp_name is checked by wp_handle_upload().
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$max_filesize = wp_easycart_customer_uploads::max_size();
		$filetypes    = wp_easycart_customer_uploads::allowed_types();
		$extensions   = ( null !== $optionset ) ? wp_easycart_customer_uploads::option_extensions( $optionset ) : array();
		if ( $check_only ) {
			return wp_easycart_customer_uploads::check( $upload_file, $max_filesize, $filetypes, $extensions );
		}
		return wp_easycart_customer_uploads::store_file( $upload_file, (string) $upload_folder, $max_filesize, $filetypes, $extensions );
	}

	private function sanatize_card_number( $card_number ) {

		return preg_replace( "/[^0-9]/", "", $card_number );

	}

	private function get_payment_type( $card_number ) {

		if ( preg_match("/^5[1-5]\d{14}$/", $card_number ) )
				return "mastercard";

		else if ( preg_match( "/^4[0-9]{12}(?:[0-9]{3}|[0-9]{6})?$/", $card_number))
				return "visa";

		else if ( preg_match( "/^3[47][0-9]{13}$/", $card_number ) )
				return "amex";

		else if ( preg_match( "/^3(?:0[0-5]|[68][0-9])[0-9]{11}$/", $card_number ) )
				return "diners";

		else if ( preg_match( "/^6(?:011\d{12}|5\d{14}|4[4-9]\d{13}|22(?:1(?:2[6-9]|[3-9]\d)|[2-8]\d{2}|9(?:[01]\d|2[0-5]))\d{10})$/", $card_number ) )
				return "discover";

		else if ( preg_match( "/^(?:2131|1800|35\d{3})\d{11}$/", $card_number ) )
				return "jcb";

		else
				return wp_easycart_language()->get_text( 'cart_payment_information', 'cart_payment_card_type_credit_card' );

	}

	public function display_order_number_link( $order_id ) {

		if ( substr_count( $this->account_page, '?' ) )				$permalink_divider = "&";
		else														$permalink_divider = "?";

		if ( $GLOBALS['ec_cart_data']->cart_data->is_guest == "" ) {
			echo "<a href=\"" . esc_attr( wpeasycart_links()->get_account_page( 'order_details', array( 'order_id' => (int) $order_id ) ) ) . "\">" . esc_attr( $order_id ) . "</a>";
		} else {
			echo "<a href=\"" . esc_attr( wpeasycart_links()->get_account_page( 'order_details', array( 'order_id' => (int) $order_id, 'guest_key' => esc_attr( $GLOBALS['ec_cart_data']->cart_data->guest_key ) ) ) ) . "\">" . esc_attr( $order_id ) . "</a>";
		}
	}

	public function get_shipping_method_name() {
		return $this->mysqli->get_shipping_method_name( $GLOBALS['ec_cart_data']->cart_data->shipping_method );
	}

	public function get_payment_image_source( $image ) {

		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . "/design/theme/" . get_option( 'ec_option_base_theme' ) . "/images/" . $image ) ) {
			return plugins_url( "/wp-easycart-data/design/theme/" . get_option( 'ec_option_base_theme' ) . "/images/" . $image, EC_PLUGIN_DATA_DIRECTORY );
		} else {
			return plugins_url( "/wp-easycart/design/theme/" . get_option( 'ec_option_latest_theme' ) . "/images/" . $image, EC_PLUGIN_DIRECTORY );
		}

	}

	public function get_selected_payment_method() {
		$default_method =  get_option( 'ec_option_default_payment_type' );
		if ( $GLOBALS['ec_cart_data']->cart_data->payment_method != '' )
			return $GLOBALS['ec_cart_data']->cart_data->payment_method;
		else if ( $default_method == "manual_bill" && $this->use_manual_payment() )
			return "manual_bill";
		else if ( $default_method == "affirm" && get_option( 'ec_option_use_affirm' ) )
			return "affirm";
		else if ( $default_method == "third_party" && $this->use_third_party() )
			return "third_party";
		else if ( $default_method == "credit_card" && $this->use_payment_gateway() )
			return "credit_card";
		else if ( $this->use_payment_gateway() )
			return "credit_card";
		else if ( $this->use_third_party() )
			return "third_party";
		else if ( get_option( 'ec_option_use_affirm' ) )
			return "affirm";
		else if ( $this->use_manual_payment() )
			return "manual_bill";
	}

	public function is_coupon_expired() {
		if ( $this->coupon_code == '' || ! isset( $this->coupon ) || ( $this->coupon && ! $this->coupon->coupon_expired && ! $this->discount->coupon_first_failed && ( $this->coupon->max_redemptions == 999 || $this->coupon->times_redeemed < $this->coupon->max_redemptions ) ) ) {
			return false;
		} else {
			return true;
		}
	}

	public function get_coupon_expiration_note() {
		if ( $this->coupon_code == '' || ! isset( $this->coupon ) || ( $this->coupon && ! $this->coupon->coupon_expired && ! $this->discount->coupon_first_failed && ( $this->coupon->max_redemptions == 999 || $this->coupon->times_redeemed < $this->coupon->max_redemptions ) ) ) {
			return "";

		} else if ( $this->coupon && $this->coupon->times_redeemed >= $this->coupon->max_redemptions ) {
			return wp_easycart_language()->get_text( 'cart_coupons', 'cart_max_exceeded_coupon' );

		} else if ( $this->coupon->coupon_expired ) {
			return wp_easycart_language()->get_text( 'cart_coupons', 'cart_coupon_expired' );

		} else if ( $this->discount->coupon_first_failed ) {
			return wp_easycart_language()->get_text( 'cart_coupons', 'coupon_first_only' );
		
		} else {
			return wp_easycart_language()->get_text( 'cart_coupons', 'cart_invalid_coupon' );
		}
	}

	public function return_to_store_page( $url ) {
		return apply_filters( 'wp_easycart_return_store_url', ( get_option( 'ec_option_return_to_store_page_url' ) != "" ) ? get_option( 'ec_option_return_to_store_page_url' ) : $url );
	}

	public function get_cart_promotion() {
		$promotion = new ec_promotion();
		return $promotion->get_cart_total_promotion( $this->order_totals->sub_total, $this->cart->cart );
	}

	public function get_cart_shipping_promotion() {
		$promotion = new ec_promotion();
		$shipping_promotion_text = $this->shipping->get_shipping_promotion_text();
		if ( $this->order_totals->shipping_discount > 0 ) {
			return (object) array(
				'discount' => $this->order_totals->shipping_discount,
				'promotion_name' => $shipping_promotion_text,
			);
		} else if ( '' != $promotion->get_free_shipping_promo_label( $this->cart ) ) {
			return (object) array(
				'discount' => 0,
				'promotion_name' => $promotion->get_free_shipping_promo_label( $this->cart ),
			);
		} else {
			return false;
		}
	}
}
