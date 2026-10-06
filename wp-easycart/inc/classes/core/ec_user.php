<?php

class ec_user{
	protected $mysqli;

	public $user_id;
	public $email;
	public $email_other;
	public $user_level;
	public $role_id;
	public $is_subscriber;

	public $first_name;
	public $last_name;
	public $vat_registration_number;

	public $billing_id;
	public $shipping_id;

	public $billing;
	public $shipping;

	public $realauth_registered;
	public $stripe_customer_id;

	public $card_type;
	public $last4;
	private $password;

	public $customfields = array();

	public $taxfree;
	public $freeshipping;
	public $allow_shipping_bypass;
	public $is_stripe_test_user;

	function __construct( $email = "" ) {
		$this->mysqli = new ec_db();
		if ( apply_filters( 'wp_easycart_use_wordpress_user', false ) ) {
			/* 6.0.2: made after init ( a sign-up swaps in a fresh ec_user ) it sets itself up now; before, at init priority 1. */
			if ( did_action( 'init' ) ) {
				$this->init_wp_user();
			} else {
				add_action( 'init', array( $this, 'init_wp_user' ), 1 );
			}
		} else {
			$this->user_id = ( ( isset( $GLOBALS['ec_cart_data']->cart_data->user_id ) ) ? (int) $GLOBALS['ec_cart_data']->cart_data->user_id : 0 );
			$this->email = ( ( isset( $GLOBALS['ec_cart_data']->cart_data->email ) ) ? $GLOBALS['ec_cart_data']->cart_data->email : '' );
			$this->init_wp_easycart_user();
			if ( ! did_action( 'init' ) ) {
				/* 6.0.2: an extension that loads after WP EasyCart can still turn the WordPress login on. */
				add_action( 'init', array( $this, 'maybe_use_wp_user' ), 1 );
			}
		}
	}

	/**
	 * Switches to the WordPress login when wp_easycart_use_wordpress_user was added after this object was made.
	 *
	 * @since 6.0.2
	 */
	public function maybe_use_wp_user() {
		if ( apply_filters( 'wp_easycart_use_wordpress_user', false ) ) {
			$this->customfields = array();
			$this->init_wp_user();
		}
	}

	/**
	 * The WordPress login decides who the customer is ( WordPress User Sync ). The store account comes from
	 * wp_easycart_wordpress_users::resolve_store_account(): the link, else the same email, else a new account, each step
	 * filterable. An admin's Login as Customer shows that customer instead. A store account waiting for email
	 * confirmation is not signed in.
	 */
	function init_wp_user() {
		global $wpdb;
		$cart_data = ( isset( $GLOBALS['ec_cart_data'] ) && is_object( $GLOBALS['ec_cart_data'] ) && isset( $GLOBALS['ec_cart_data']->cart_data ) && is_object( $GLOBALS['ec_cart_data']->cart_data ) ) ? $GLOBALS['ec_cart_data']->cart_data : null;
		$previous_cart_user_id = ( $cart_data && isset( $cart_data->user_id ) ) ? (int) $cart_data->user_id : 0;
		$wpec_user_id = 0;
		$row = null;
		if ( is_user_logged_in() && class_exists( 'wp_easycart_wordpress_users' ) ) {
			$wp_user = wp_get_current_user();
			$wpec_user_id = wp_easycart_wordpress_users::impersonating();
			if ( ! $wpec_user_id ) {
				$wpec_user_id = wp_easycart_wordpress_users::resolve_store_account( $wp_user );
			}
			if ( $wpec_user_id ) {
				$row = $wpdb->get_row( $wpdb->prepare( 'SELECT user_id, email, first_name, last_name, user_level FROM ec_user WHERE user_id = %d', (int) $wpec_user_id ) );
				if ( ! $row || 'pending' == $row->user_level ) {
					$wpec_user_id = 0;
					$row = null;
				}
			}
		}

		if ( ! $wpec_user_id ) {
			$this->user_id = 0;
			$this->email = '';
			if ( $cart_data && $previous_cart_user_id ) {
				/* The session still names a store customer the WordPress login does not: nobody is signed in to the store. */
				$cart_data->user_id = '';
				$cart_data->username = '';
				$cart_data->first_name = '';
				$cart_data->last_name = '';
				$cart_data->email = '';
				if ( isset( $GLOBALS['ec_cart_id'] ) && 'not-set' != $GLOBALS['ec_cart_id'] ) {
					$GLOBALS['ec_cart_data']->save_session_to_db();
				}
			}
			$this->init_wp_easycart_user();
			return;
		}

		$this->user_id = (int) $wpec_user_id;
		$this->email = $row->email;
		$this->first_name = $row->first_name;
		$this->last_name = $row->last_name;
		if ( $cart_data ) {
			$cart_data->user_id = $this->user_id;
			$cart_data->email = $this->email;
			$cart_data->is_guest = false;
			$cart_data->guest_key = '';
			$cart_data->first_name = $this->first_name;
			$cart_data->last_name = $this->last_name;
			$cart_data->username = trim( $this->first_name . ' ' . $this->last_name );
			if ( $this->user_id !== $previous_cart_user_id ) {
				wp_easycart_wordpress_users::prefill_session( $this->user_id );
				if ( isset( $GLOBALS['ec_cart_id'] ) && 'not-set' != $GLOBALS['ec_cart_id'] && function_exists( 'wpeasycart_session' ) ) {
					$GLOBALS['ec_cart_data']->save_session_to_db();
					wpeasycart_session()->rotate_session_id();
				}
			}
		}
		$this->init_wp_easycart_user();
	}

	public function init_wp_easycart_user() {
		$user = $this->mysqli->get_user( $this->user_id, $this->email );
		if ( $user && $user->user_level ) {
			$this->first_name = $user->first_name;
			$this->last_name = $user->last_name;
			$this->email_other = $user->email_other;
			$this->vat_registration_number = $user->vat_registration_number;
			$this->user_level = $user->user_level;
			$this->role_id = $user->role_id;
			$this->is_subscriber = $user->is_subscriber;
			$this->billing_id = $user->default_billing_address_id;
			$this->shipping_id = $user->default_shipping_address_id;
			$this->stripe_customer_id = $user->stripe_customer_id;
			$this->card_type = $user->default_card_type;
			$this->last4 = $user->default_card_last4;
			$this->taxfree = $user->exclude_tax;
			$this->freeshipping = $user->exclude_shipping;
			$this->allow_shipping_bypass = $user->allow_shipping_bypass;
			$this->is_stripe_test_user = $user->is_stripe_test_user;
		} else {
			$this->first_name = "";
			$this->last_name = "";
			$this->vat_registration_number = "";
			$this->user_level = "";
			$this->role_id = 0;
			$this->is_subscriber = "";
			$this->billing_id = "";
			$this->shipping_id = "";
			$this->stripe_customer_id = "";
			$this->taxfree = false;
			$this->freeshipping = false;
			$this->allow_shipping_bypass = false;
			$this->is_stripe_test_user = false;
		}

		if ( $user && $user->billing_first_name ) {
			$this->billing = new ec_address( $user->billing_first_name, $user->billing_last_name, $user->billing_address_line_1, $user->billing_address_line_2, $user->billing_city, $user->billing_state, $user->billing_zip, $user->billing_country, $user->billing_phone, $user->billing_company_name );
		} else {
			$this->billing = new ec_address( "", "", "", "", "", "", "", "", "", "" );
		}

		if ( $user && $user->shipping_first_name ) {
			$this->shipping = new ec_address( $user->shipping_first_name, $user->shipping_last_name, $user->shipping_address_line_1, $user->shipping_address_line_2, $user->shipping_city, $user->shipping_state, $user->shipping_zip, $user->shipping_country, $user->shipping_phone, $user->shipping_company_name );
		} else {
			$this->shipping = new ec_address( "", "", "", "", "", "", "", "", "", "" );
		}

		if( isset( $user ) && $user )
			$this->realauth_registered = $user->realauth_registered;

		if( $user && $user->customfield_data ){
			$customfield_data_array = explode( "---", $user->customfield_data );
			for( $i=0; $i<count( $customfield_data_array ); $i++ ){
				$temp_arr = explode("***", $customfield_data_array[$i]);
				array_push($this->customfields, $temp_arr);
			}
		}
	}

	private function setup_billing_info(){

		if(	isset($_POST['EmailNew']))		setup_billing_info_from_post();
		else								setup_billing_info_from_db();

	}

	private function setup_shipping_info(){

		if(	isset($_POST['EmailNew']))		setup_shipping_info_from_post();
		else								setup_shipping_info_from_db();

	}

	public function setup_billing_info_data( $bname, $blastname, $baddress, $baddress2, $bcity, $bstate, $bcountry, $bzip, $bphone, $bcompany ){

		$this->billing = new ec_address( $bname, $blastname, $baddress, $baddress2, $bcity, $bstate, $bzip, $bcountry, $bphone, $bcompany );

	}

	public function setup_shipping_info_data( $sname, $slastname, $saddress, $saddress2, $scity, $sstate, $scountry, $szip, $sphone, $scompany ){

		$this->shipping = new ec_address( $sname, $slastname, $saddress, $saddress2, $scity, $sstate, $szip, $scountry, $sphone, $scompany );

	}

	public function should_insert_user($userlevel, $createaccount){
		if($userlevel == "guest" && $createaccount)					return true;
		else 														return false;
	}

	public function is_guest( ){
		if( $GLOBALS['ec_cart_data']->cart_data->is_guest != "" && $GLOBALS['ec_cart_data']->cart_data->is_guest )	
																	return true;
		else														return false;	
	}

	public function insert_user( ){
		$this->billing_id = $this->insert_billing_info( );
		$this->shipping_id = $this->insert_shipping_info( );
		$this->user_id = $this->mysqli->insert_user( $this->email, $this->password, $this->first_name, $this->last_name, $this->billing_id, $this->shipping_id, "shopper", $this->is_subscriber );
		$this->mysqli->update_address_user_id( $this->billing_id, $this->user_id );
		$this->mysqli->update_address_user_id( $this->shipping_id, $this->user_id );

		// MyMail Hook. 6.0.2: only for a newsletter opt-in.
		if ( $this->is_subscriber && function_exists( 'mailster' ) ) {
			$subscriber_id = mailster('subscribers')->add(array(
				'firstname' => $this->first_name,
				'lastname' => $this->last_name,
				'email' => $this->email,
				'status' => 1,
			), false );
		}
	}

	public function insert_billing_info( ){
		$this->mysqli->insert_address( $this->billing->first_name, $this->billing->last_name, $this->billing->address_line_1, $this->billing->city, $this->billing->state, $this->billing->zip, $this->billing->country, $this->billing->phone, $this->billing->company_name );
	}

	public function insert_shipping_info( ){
		$this->mysqli->insert_address( $this->shipping->first_name, $this->shipping->last_name, $this->shipping->address_line_1, $this->shipping->city, $this->shipping->state, $this->shipping->zip, $this->shipping->country, $this->shipping->phone, $this->shipping->company_name );
	}

	public function display_email( ){
		echo esc_attr( $this->email );	
	}

	public function display_email_other( ){
		echo esc_attr( $this->email_other );	
	}

	public function display_custom_input_fields( $divider, $seperator ){
		for( $i=0; $i<count( $this->customfields ) && $this->customfields[$i][0] != ""; $i++ ){
			echo esc_attr( $this->customfields[$i][1] . $divider ) . " <input type=\"text\" name=\"ec_user_custom_field_" . esc_attr( $this->customfields[$i][0] ) . "\" id=\"ec_user_custom_field_" . esc_attr( $this->customfields[$i][0] ) . "\" value=\"" . esc_attr( $this->customfields[$i][2] ) . "\" />" . esc_attr( $seperator );
		}
	}

	public function display_custom_fields( $divider, $seperator ){
		for( $i=0; $i<count( $this->customfields ) && $this->customfields[$i][0] != ""; $i++ ){
			echo esc_attr( $this->customfields[$i][1] . $divider . " " . $this->customfields[$i][2] . $seperator );
		}
	}

	public function display_custom_input_label_single( $i ){
		echo esc_attr( $this->customfields[$i][1] );
	}

	public function display_custom_input_field_single( $i ){
		echo "<input type=\"text\" name=\"ec_user_custom_field_" . esc_attr( $this->customfields[$i][0] ) . "\" id=\"ec_user_custom_field_" . esc_attr( $this->customfields[$i][0] ) . "\" value=\"" . esc_attr( $this->customfields[$i][2] ) . "\" />" . esc_attr( $seperator );
	}

	public function get_payment_list( ){
		$ret_cards = array( );
		if( get_option( 'ec_option_payment_process_method' ) == "stripe" ){
			$stripe = new ec_stripe( );
			$card_list = $stripe->get_card_list( $this->stripe_customer_id );

			foreach( $card_list->data as $card ){
				$ret_cards[] = array( 'id' => $card->id, 'type' => $card->type, 'last4' => $card->last4, 'exp_month' => $card->exp_month, 'exp_year' => $card->exp_year );
			}
		}else if( get_option( 'ec_option_payment_process_method' ) == "stripe_connect" ){
			$stripe = new ec_stripe_connect( );
			$card_list = $stripe->get_card_list( $this->stripe_customer_id );

			foreach( $card_list->data as $card ){
				$ret_cards[] = array( 'id' => $card->id, 'type' => $card->type, 'last4' => $card->last4, 'exp_month' => $card->exp_month, 'exp_year' => $card->exp_year );
			}
		}else{
			return false;
		}

		return $ret_cards;
	}

	public function display_card_type( ){

		echo esc_attr( strtoupper( $this->card_type ) );

	}

	public function display_last4( ){

		echo esc_attr( $this->last4 );

	}
	
	public function has_active_subscription( $product_id ) {
		if ( 0 == $this->user_id ) {
			return false;
		}
		global $wpdb;
		/* 6.0.3: a free trial, or a subscription cancelled at the end of its period, still holds the product. */
		$statuses = ( function_exists( 'wp_easycart_subscription_member_statuses' ) ) ? wp_easycart_subscription_member_statuses() : array( 'Active' );
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ec_subscription WHERE subscription_status IN ( ' . implode( ', ', array_fill( 0, count( $statuses ), '%s' ) ) . ' ) AND product_id = %d AND user_id = %d', array_merge( $statuses, array( $product_id, $this->user_id ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- one %s placeholder per status, bound by prepare().
	}
}
