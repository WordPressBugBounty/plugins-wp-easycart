<?php

class ec_subscription{

	private $mysqli;									// ec_db object

	public $subscription_id;							// DB ID for Subscription
	public $user_id;									// USER ID connecting subscription to user
	public $title;										// title of the subscription
	public $created;									// date created in UNIX timestamp format
	public $amount;										// 12.00 format
	public $quantity;									// INT
	public $product_id;									// id of the product
	public $trial_period_days;							// days of the trial
	public $bill_length;								// length of bill cycle, e.g. 4
	public $bill_period;								// period type (D, W, M, Y)
	public $status;										// Active, Suspended, or Canceled
	public $last_billed;								// date in UNIX timestamp format
	public $next_payment;								// date in UNIX timestamp format
	public $card_type;									// credit card type used
	public $last4;										// last 4 digits of credit card

	public $is_details;									// Get more details if this is details
	public $stripe_subscription_id;						// ID of a Stripe Subscription

	public $payment_duration;

	public $upgrades;									// Array of possible upgrades
	public $past_payments;								// Array of past payments

	public $membership_page;							// VARCHAR link

	public $account_page;								// VARCHAR
	public $cart_page;									// VARCHAR
	public $permalink_divider;							// CHAR

	public $subscription_type;							// stripe, paypal
	public $start_date;									// timestamp column ( DATETIME string )
	public $number_payments_completed;					// INT
	public $num_failed_payment;							// INT
	public $model_number;								// VARCHAR

	private $purchase_details = false;					// Lazy cache for get_purchase_details()

	function __construct( $subscription_row, $is_details = false ){

		$this->mysqli = new ec_db();
		$this->is_details = $is_details;

		$accountpageid = apply_filters( 'wp_easycart_account_page_id', get_option( 'ec_option_accountpage' ) );
		$cartpageid = get_option('ec_option_cartpage');

		if( function_exists( 'icl_object_id' ) ){
			$accountpageid = icl_object_id( $accountpageid, 'page', true, ICL_LANGUAGE_CODE );
			$cartpageid = icl_object_id( $cartpageid, 'page', true, ICL_LANGUAGE_CODE );
		}

		$this->account_page = get_permalink( $accountpageid );
		$this->cart_page = get_permalink( $cartpageid );

		if( class_exists( "WordPressHTTPS" ) && isset( $_SERVER['HTTPS'] ) ){
			$https_class = new WordPressHTTPS( );
			$this->account_page = $https_class->makeUrlHttps( $this->account_page );
			$this->cart_page = $https_class->makeUrlHttps( $this->cart_page );
		}

		if( substr_count( $this->account_page, '?' ) )				$this->permalink_divider = "&";
		else														$this->permalink_divider = "?";


		// Initialize data
		$this->set_db_vars( $subscription_row );
		$this->upgrades = $this->mysqli->get_subscription_upgrades( $this->subscription_id );
		$this->past_payments = $this->mysqli->get_subscription_payments( $this->subscription_id, $subscription_row->user_id );

	}

	//////////////////////////////////////////////////////////
	//Display Functions
	//////////////////////////////////////////////////////////

	public function display_title( ){
		echo esc_attr( $this->title );
	}

	public function display_next_bill_date( $date_format = "" ){
		if( $date_format == "" ){
			$date_format = get_option('date_format');
		}
		/* 6.0.0: next_payment_date is VARCHAR and holds epoch seconds or a DATETIME string ( PRO sync ); gmdate() on a DATETIME string fatals on PHP 8. */
		$timestamp = self::to_timestamp( $this->next_payment );
		echo esc_attr( ( $timestamp ) ? gmdate( $date_format, $timestamp ) : '' );
	}

	public function display_last_bill_date( $date_format = "" ){
		if( $date_format == "" ){
			$date_format = get_option('date_format');
		}
		$timestamp = self::to_timestamp( $this->last_billed );
		echo esc_attr( ( $timestamp ) ? gmdate( $date_format, $timestamp ) : '' );
	}

	public function display_price( ){
		echo esc_attr( $GLOBALS['currency']->get_currency_display( $this->amount * $this->quantity ) . $this->get_bill_period_formatted( ) );
	}

	public function display_subscription_link( $text ){
		echo "<a href=\"" . esc_url( wpeasycart_links()->get_account_page( 'subscription_details', array( 'subscription_id' => (int) $this->subscription_id ) ) ) . "\">" . esc_attr( $text ) . "</a>";
	}

	public function has_membership_page( ){
		if ( '' != $this->membership_page ) {
			return true;
		} else {
			return false;
		}
	}

	public function display_membership_page_link( $link_text ){
		echo "<a href=\"" . esc_attr( $this->membership_page ) . "\" class=\"ec_account_membership_page_link\">" . esc_attr( $link_text ) . "</a>";
	}

	public function display_order_customer_email_notes( $order_id ){
		global $wpdb;
		$order_notes = $wpdb->get_results( $wpdb->prepare( "SELECT ec_product.order_completed_email_note FROM ec_order, ec_orderdetail, ec_product WHERE ec_order.order_id = %d AND ec_orderdetail.order_id = ec_order.order_id AND ec_product.product_id = ec_orderdetail.product_id GROUP BY ec_orderdetail.product_id", $order_id ) );
		foreach( $order_notes as $order_note ){
			if( $order_note->order_completed_email_note != '' ){
				$content = do_shortcode( stripslashes( $order_note->order_completed_email_note ) );
				$content = str_replace( ']]>', ']]&gt;', $content );
				echo wp_easycart_escape_html( $content ); //XSS OK.
			}
		}
	}

	/////////////////////////////////////////////////////////
	// Funtionality Functions
	/////////////////////////////////////////////////////////
	public function send_email_receipt( $user, $order, $order_details ){
		$orderdisplay = new ec_orderdisplay( $order, $order_details );
		$orderdisplay->send_email_receipt();
	}

	public function send_trial_start_email( $user ){

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

		ob_start( );
		if( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_subscription_trial_start_email.php' ) )	
			include EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_subscription_trial_start_email.php';
		else
			include EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_subscription_trial_start_email.php';

		$message = ob_get_clean();

		$email_send_method = get_option( 'ec_option_use_wp_mail' );
		$email_send_method = apply_filters( 'wpeasycart_email_method', $email_send_method );

		if( $email_send_method == "1" ){
			wp_mail( $user->email, wp_easycart_language( )->get_text( "subscription_trial", "subscription_trial_email_title" ), $message, implode("\r\n", $headers) );
			wp_mail( stripslashes( get_option( 'ec_option_bcc_email_addresses' ) ), wp_easycart_language( )->get_text( "subscription_trial", "subscription_trial_email_title" ), $message, implode("\r\n", $headers) );

		}else if( $email_send_method == "0" ){
			$admin_email = stripslashes( get_option( 'ec_option_bcc_email_addresses' ) );
			$to = $user->email;
			$subject = wp_easycart_language( )->get_text( "subscription_trial", "subscription_trial_email_title" );
			$mailer = new wpeasycart_mailer( );
			$mailer->send_order_email( $to, $subject, $message );
			$mailer->send_order_email( $admin_email, $subject, $message );

		}else{
			do_action( 'wpeasycart_custom_subscription_order_email', stripslashes( get_option( 'ec_option_order_from_email' ) ), $user->email, stripslashes( get_option( 'ec_option_bcc_email_addresses' ) ), wp_easycart_language( )->get_text( "subscription_trial", "subscription_trial_email_title" ), $message );

		}

	}

	public function send_subscription_trial_ending_email( $user ){

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

		ob_start( );
		if( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_subscription_trial_ending_email.php' ) )	
			include EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_subscription_trial_ending_email.php';
		else
			include EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_subscription_trial_ending_email.php';

		$message = ob_get_clean();

		$email_send_method = get_option( 'ec_option_use_wp_mail' );
		$email_send_method = apply_filters( 'wpeasycart_email_method', $email_send_method );

		if( $email_send_method == "1" ){
			wp_mail( $user->email, wp_easycart_language( )->get_text( "subscription_trial", "subscription_trial_ending_email_title" ), $message, implode("\r\n", $headers) );
			wp_mail( stripslashes( get_option( 'ec_option_bcc_email_addresses' ) ), wp_easycart_language( )->get_text( "subscription_trial", "subscription_trial_ending_email_title" ), $message, implode("\r\n", $headers) );

		}else if( $email_send_method == "0" ){
			$admin_email = stripslashes( get_option( 'ec_option_bcc_email_addresses' ) );
			$to = $user->email;
			$subject = wp_easycart_language( )->get_text( "subscription_trial", "subscription_trial_ending_email_title" );
			$mailer = new wpeasycart_mailer( );
			$mailer->send_order_email( $to, $subject, $message );
			$mailer->send_order_email( $admin_email, $subject, $message );

		}else{
			do_action( 'wpeasycart_custom_subscription_order_email', stripslashes( get_option( 'ec_option_order_from_email' ) ), $user->email, stripslashes( get_option( 'ec_option_bcc_email_addresses' ) ), wp_easycart_language( )->get_text( "subscription_trial", "subscription_trial_ending_email_title" ), $message );

		}

	}

	public function send_subscription_upcoming_payment_email( $subscription, $user, $webhook_data ) {
		$email_logo_url = get_option( 'ec_option_email_logo' );
		$total = $GLOBALS['currency']->get_currency_display( $webhook_data->amount_due / 100 );
		$upcoming_charge_timestamp = null;
		if ( isset( $webhook_data->next_payment_attempt ) && ! is_null( $webhook_data->next_payment_attempt ) ) {
			$upcoming_charge_timestamp = $webhook_data->next_payment_attempt;
		} else if ( isset( $webhook_data->lines->data[0]->period->end ) ) {
			$upcoming_charge_timestamp = $webhook_data->lines->data[0]->period->end;
		}
		$date = ( $upcoming_charge_timestamp ) ? date_i18n( 'F j, Y', $upcoming_charge_timestamp ) : false;

		$headers = array();
		$headers[] = "MIME-Version: 1.0";
		$headers[] = "Content-Type: text/html; charset=utf-8";
		$headers[] = "From: " . stripslashes( get_option( 'ec_option_order_from_email' ) );
		$headers[] = "Reply-To: " . stripslashes( get_option( 'ec_option_order_from_email' ) );
		$headers[] = "X-Mailer: PHP/" . phpversion( );

		ob_start( );
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_subscription_upcoming_email.php' ) ) {
			include EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_subscription_upcoming_email.php';
		} else {
			include EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_subscription_upcoming_email.php';
		}
		$message = ob_get_clean();

		$email_send_method = get_option( 'ec_option_use_wp_mail' );
		$email_send_method = apply_filters( 'wpeasycart_email_method', $email_send_method );

		if ( $email_send_method == "1" ) {
			wp_mail( $user->email, wp_easycart_language( )->get_text( "subscription_upcoming", "subscription_upcoming_email_title" ), $message, implode("\r\n", $headers) );
			wp_mail( stripslashes( get_option( 'ec_option_bcc_email_addresses' ) ), wp_easycart_language( )->get_text( "subscription_upcoming", "subscription_upcoming_email_title" ), $message, implode("\r\n", $headers) );

		}else if( $email_send_method == "0" ){
			$admin_email = stripslashes( get_option( 'ec_option_bcc_email_addresses' ) );
			$to = $user->email;
			$subject = wp_easycart_language( )->get_text( "subscription_upcoming", "subscription_upcoming_email_title" );
			$mailer = new wpeasycart_mailer( );
			$mailer->send_order_email( $to, $subject, $message );
			$mailer->send_order_email( $admin_email, $subject, $message );

		}else{
			do_action( 'wpeasycart_custom_subscription_order_email', stripslashes( get_option( 'ec_option_order_from_email' ) ), $user->email, stripslashes( get_option( 'ec_option_bcc_email_addresses' ) ), wp_easycart_language( )->get_text( "subscription_upcoming", "subscription_upcoming_email_title" ), $message );
		}
	}

	public function send_subscription_ended_email( $user ){

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

		ob_start( );
		if( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_subscription_ended_email.php' ) )	
			include EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_subscription_ended_email.php';
		else
			include EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_subscription_ended_email.php';

		$message = ob_get_clean();

		$email_send_method = get_option( 'ec_option_use_wp_mail' );
		$email_send_method = apply_filters( 'wpeasycart_email_method', $email_send_method );

		if( $email_send_method == "1" ){
			wp_mail( $user->email, wp_easycart_language( )->get_text( "subscription_ended", "subscription_ended_email_title" ), $message, implode("\r\n", $headers) );
			wp_mail( stripslashes( get_option( 'ec_option_bcc_email_addresses' ) ), wp_easycart_language( )->get_text( "subscription_ended", "subscription_ended_email_title" ), $message, implode("\r\n", $headers) );

		}else if( $email_send_method == "0" ){
			$admin_email = stripslashes( get_option( 'ec_option_bcc_email_addresses' ) );
			$to = $user->email;
			$subject = wp_easycart_language( )->get_text( "subscription_ended", "subscription_ended_email_title" );
			$mailer = new wpeasycart_mailer( );
			$mailer->send_order_email( $to, $subject, $message );
			$mailer->send_order_email( $admin_email, $subject, $message );

		}else{
			do_action( 'wpeasycart_custom_subscription_order_email', stripslashes( get_option( 'ec_option_order_from_email' ) ), $user->email, stripslashes( get_option( 'ec_option_bcc_email_addresses' ) ), wp_easycart_language( )->get_text( "subscription_ended", "subscription_ended_email_title" ), $message );
		}
	}

	public function get_subscription_purchase_link() {
		global $wpdb;
		$model_number = $wpdb->get_var( $wpdb->prepare( "SELECT ec_product.model_number FROM ec_product WHERE ec_product.product_id = %d", $this->product_id ) );
		return esc_url_raw( wpeasycart_links()->get_cart_page( 'subscription_info', array( 'subscription' => esc_attr( $model_number ) ) ) );
	}

	public function print_receipt( $order, $order_details ){
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

		if( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_email_receipt.php' ) )	
			include EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_email_receipt.php';
		else if( file_exists( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_email_receipt.php' ) )
			include EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_email_receipt.php';
		else{

		}

	}

	public function has_upgrades( ){

		if( count( $this->upgrades ) > 0 ){
			foreach( $this->upgrades as $upgrade ){
				if( $this->product_id == $upgrade->product_id ){
					return true;
				}
			}
		}

		echo "<input type=\"hidden\" name=\"ec_selected_plan\" value=\"" . esc_attr( $this->product_id ) . "\" />";

		return false;

	}

	public function display_upgrade_dropdown( ){

		$found_this = false;
		echo "<select name=\"ec_selected_plan\" id=\"ec_selected_plan\">";
		foreach( $this->upgrades as $upgrade ){
			if( $this->product_id == $upgrade->product_id ){
				$found_this = true;
			}

			if( $upgrade->can_downgrade || $found_this ){
				echo "<option value=\"" . esc_attr( $upgrade->product_id ) . "\"";
				if( $this->product_id == $upgrade->product_id ){
					echo " selected=\"selected\"";
				}
				echo ">" . wp_easycart_language( )->convert_text( $upgrade->title ) . " " . esc_attr( $GLOBALS['currency']->get_currency_display( $upgrade->price ) . $this->get_new_bill_period_formatted( $upgrade->subscription_bill_length, $upgrade->subscription_bill_period ) ) . "</option>";
			}
		}
		echo "</select>";

	}

	public function get_stripe_id( ){
		return $this->stripe_subscription_id;
	}

	public function display_past_payments( $date_format = "" ){

		if( $date_format == "" ){
			$date_format = get_option( 'date_format' ) . " " . get_option( 'time_format' );
		}

		if( $this->past_payments ){
			foreach( $this->past_payments as $payment ){
				echo '<div class="ec_account_subscriptions_past_payment_item">' . esc_attr( date( $date_format, strtotime( $payment->order_date ) ) ) . " | " . esc_attr( $GLOBALS['currency']->get_currency_display( $payment->grand_total ) ) . " | <a href=\"" . esc_url( wpeasycart_links()->get_account_page( 'order_details', array( 'order_id' => (int) $payment->order_id ) ) ) . "\">" . wp_easycart_language( )->get_text( 'account_orders', 'account_orders_view_order_button' ) . "</a></div>";
			}
		}
	}

	public function display_cancel_form( $button_text, $confirm_text ){
		echo "<form method=\"POST\" action=\"" . esc_url( wpeasycart_links()->get_account_page( 'subscriptions' ) ) . "\">";
		echo "<input type=\"hidden\" name=\"ec_account_subscription_id\" value=\"" . esc_attr( $this->subscription_id ) . "\">";
		echo "<input type=\"hidden\" name=\"ec_account_form_action\" value=\"cancel_subscription\">";
		echo "<input type=\"hidden\" name=\"ec_account_form_nonce\" value=\"" . esc_attr( wp_create_nonce( 'wp-easycart-account-cancel-subscription-' . (int) $this->subscription_id ) ) . "\" />";
		echo "<input type=\"submit\" value=\"" . esc_attr( $button_text ) . "\" onclick=\"return ec_cancel_subscription_check( '" . esc_attr( $confirm_text ) . "' );\">";
		echo "</form>";
	}

	public function is_canceled( ){
		/* 6.0.0: every ended status counts ( Canceled, cancelled, incomplete_expired, expired ), not only the exact string "Canceled". */
		return in_array( self::get_status_key_for( $this->status ), array( 'canceled', 'expired' ), true );
	}

	/////////////////////////////////////////////////////////
	// 6.0.0: status, customer actions, purchase details
	/////////////////////////////////////////////////////////

	/**
	 * Normalize a stored ec_subscription.subscription_status value.
	 *
	 * Legacy rows use Active / Canceled / Failed. The Stripe webhooks and the PRO sync also write
	 * canceling, paused, trialing, incomplete, past_due, unpaid and incomplete_expired.
	 *
	 * @since 6.0.0
	 * @param string $status Stored status.
	 * @return string active|trialing|past_due|incomplete|paused|suspended|canceling|canceled|expired|other
	 */
	public static function get_status_key_for( $status ) {
		$status = strtolower( trim( (string) $status ) );
		$map    = array(
			'active'             => 'active',
			'trialing'           => 'trialing',
			'trial'              => 'trialing',
			'failed'             => 'past_due',
			'past_due'           => 'past_due',
			'unpaid'             => 'past_due',
			'incomplete'         => 'incomplete',
			'paused'             => 'paused',
			'suspended'          => 'suspended',
			'canceling'          => 'canceling',
			'cancelling'         => 'canceling',
			'canceled'           => 'canceled',
			'cancelled'          => 'canceled',
			'incomplete_expired' => 'expired',
			'expired'            => 'expired',
			'ended'              => 'expired',
			'completed'          => 'expired',
			'complete'           => 'expired',
		);
		return ( isset( $map[ $status ] ) ) ? $map[ $status ] : 'other';
	}

	/**
	 * Normalized status key for this subscription.
	 *
	 * @since 6.0.0
	 * @return string
	 */
	public function get_status_key() {
		return self::get_status_key_for( $this->status );
	}

	/**
	 * Customer-facing status label ( language editor: Account - Subscriptions ).
	 *
	 * @since 6.0.0
	 * @return string Escaped text.
	 */
	public function get_status_label() {
		$key      = $this->get_status_key();
		$defaults = array(
			'active'     => 'Active',
			'trialing'   => 'Trial',
			'past_due'   => 'Payment past due',
			'incomplete' => 'Incomplete',
			'paused'     => 'Paused',
			'suspended'  => 'Suspended',
			'canceling'  => 'Cancels at period end',
			'canceled'   => 'Canceled',
			'expired'    => 'Ended',
		);
		if ( ! isset( $defaults[ $key ] ) ) {
			return esc_html( ucwords( str_replace( '_', ' ', strtolower( (string) $this->status ) ) ) );
		}
		return self::get_text( 'subscription_status_' . $key, $defaults[ $key ] );
	}

	/**
	 * Language text with an English fallback, for keys added in 6.0.0 that an existing
	 * install has not merged into its saved language data yet.
	 *
	 * @since 6.0.0
	 * @param string $key      Language key.
	 * @param string $fallback English fallback.
	 * @param string $section  Language section.
	 * @return string Escaped text.
	 */
	public static function get_text( $key, $fallback, $section = 'account_subscriptions' ) {
		$text = ( function_exists( 'wp_easycart_language' ) ) ? trim( (string) wp_easycart_language()->get_text( $section, $key ) ) : '';
		return ( '' === $text ) ? esc_html( $fallback ) : $text;
	}

	/**
	 * Only Stripe subscriptions can be changed from the account page, and only while the store
	 * still processes payments through Stripe. PayPal subscriptions are managed at PayPal.
	 *
	 * @since 6.0.0
	 * @return bool
	 */
	public function is_customer_manageable() {
		$gateway = get_option( 'ec_option_payment_process_method' );
		if ( 'stripe' != $gateway && 'stripe_connect' != $gateway ) {
			return false;
		}
		if ( '' == $this->stripe_subscription_id || 'paypal' == $this->subscription_type ) {
			return false;
		}
		return true;
	}

	/**
	 * Whether the customer may run an action on this subscription from the account page.
	 * The same check guards the templates and the handlers that make the change.
	 *
	 * @since 6.0.0
	 * @param string $action update_payment|change_plan|cancel.
	 * @return bool
	 */
	public function customer_can( $action ) {
		$allowed_statuses = array(
			'update_payment' => array( 'active', 'trialing', 'past_due', 'incomplete', 'paused' ),
			'change_plan'    => array( 'active', 'trialing' ),
			'cancel'         => array( 'active', 'trialing', 'past_due', 'incomplete', 'paused' ),
		);
		$allowed = ( isset( $allowed_statuses[ $action ] ) && $this->is_customer_manageable() && in_array( $this->get_status_key(), $allowed_statuses[ $action ], true ) );
		if ( $allowed && 'change_plan' == $action ) {
			$allowed = $this->has_plan_choices();
		}
		return (bool) apply_filters( 'wp_easycart_subscription_customer_can', $allowed, $action, $this );
	}

	/** @since 6.0.0 */
	public function can_update_payment_method() {
		return $this->customer_can( 'update_payment' );
	}

	/** @since 6.0.0 */
	public function can_change_plan() {
		return $this->customer_can( 'change_plan' );
	}

	/** @since 6.0.0 */
	public function can_cancel() {
		return $this->customer_can( 'cancel' );
	}

	/**
	 * has_upgrades() without the hidden input it prints.
	 *
	 * @since 6.0.0
	 * @return bool
	 */
	public function has_upgrade_options() {
		if ( is_array( $this->upgrades ) ) {
			foreach ( $this->upgrades as $upgrade ) {
				if ( $this->product_id == $upgrade->product_id ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Something to change: another product in the plan, or the quantity.
	 *
	 * @since 6.0.0
	 * @return bool
	 */
	public function has_plan_choices() {
		return ( $this->has_upgrade_options() || ! get_option( 'ec_option_subscription_one_only' ) );
	}

	/**
	 * Whether a product may be selected by the customer, following the same rules as display_upgrade_dropdown().
	 *
	 * @since 6.0.0
	 * @param int $product_id Selected product.
	 * @return bool
	 */
	public function is_allowed_plan( $product_id ) {
		$product_id = (int) $product_id;
		if ( $product_id == (int) $this->product_id ) {
			return true;
		}
		if ( ! $this->has_upgrade_options() ) {
			return false;
		}
		$found_this = false;
		foreach ( $this->upgrades as $upgrade ) {
			if ( (int) $this->product_id == (int) $upgrade->product_id ) {
				$found_this = true;
			}
			if ( (int) $upgrade->product_id == $product_id ) {
				return ( $upgrade->can_downgrade || $found_this );
			}
		}
		return false;
	}

	/**
	 * Plans the customer can pick on the change-plan panel ( same rules and order as display_upgrade_dropdown() ).
	 *
	 * @since 6.0.0
	 * @return array[] { product_id: int, title: string ( escaped ), amount: string, period: string, current: bool }
	 */
	public function get_plan_choices() {
		$choices = array();
		if ( ! $this->has_upgrade_options() ) {
			return $choices;
		}
		$found_this = false;
		foreach ( $this->upgrades as $upgrade ) {
			$is_current = ( (int) $this->product_id == (int) $upgrade->product_id );
			if ( $is_current ) {
				$found_this = true;
			}
			if ( $upgrade->can_downgrade || $found_this ) {
				$choices[] = array(
					'product_id' => (int) $upgrade->product_id,
					'title'      => wp_easycart_language()->convert_text( $upgrade->title ),
					'amount'     => $GLOBALS['currency']->get_currency_display( $upgrade->price ),
					'period'     => $this->get_new_bill_period_formatted( $upgrade->subscription_bill_length, $upgrade->subscription_bill_period ),
					'current'    => $is_current,
				);
			}
		}
		return $choices;
	}

	/**
	 * Subscription dates are VARCHAR columns holding epoch seconds, a DATETIME string, a zero date or nothing.
	 *
	 * @since 6.0.0
	 * @param mixed $value Stored value.
	 * @return int Timestamp or 0.
	 */
	public static function to_timestamp( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value || 0 === strpos( $value, '0000-00-00' ) ) {
			return 0;
		}
		if ( ctype_digit( $value ) ) {
			return ( (int) $value > 100000 ) ? (int) $value : 0;
		}
		$timestamp = strtotime( $value );
		return ( false === $timestamp || $timestamp < 86400 ) ? 0 : $timestamp;
	}

	/** @since 6.0.0 */
	public function get_next_payment_timestamp() {
		return self::to_timestamp( $this->next_payment );
	}

	/** @since 6.0.0 */
	public function get_last_payment_timestamp() {
		return self::to_timestamp( $this->last_billed );
	}

	/** @since 6.0.0 */
	public function get_start_timestamp() {
		return self::to_timestamp( $this->start_date );
	}

	/**
	 * Localized date for a timestamp.
	 *
	 * @since 6.0.0
	 * @param int    $timestamp   Timestamp.
	 * @param string $date_format PHP date format, site format when empty.
	 * @return string Unescaped text, empty for no date.
	 */
	public static function format_date( $timestamp, $date_format = '' ) {
		if ( ! $timestamp ) {
			return '';
		}
		if ( '' == $date_format ) {
			$date_format = get_option( 'date_format' );
		}
		return ( function_exists( 'wp_date' ) ) ? wp_date( $date_format, $timestamp ) : date_i18n( $date_format, $timestamp );
	}

	/**
	 * Price per billing period, split for the details card.
	 *
	 * @since 6.0.0
	 * @return array { amount: string, period: string } Unescaped text.
	 */
	public function get_price_parts() {
		return array(
			'amount' => $GLOBALS['currency']->get_currency_display( $this->amount * $this->quantity ),
			'period' => $this->get_bill_period_formatted(),
		);
	}

	/**
	 * Image for the subscription: the image saved on the original order line, else the product's first image.
	 *
	 * @since 6.0.0
	 * @return string URL or empty.
	 */
	public function get_image_url() {
		$details = $this->get_purchase_details();
		if ( $details && ! $details['plan_changed'] ) {
			$url = self::resolve_image_url( $details['image1'] );
			if ( '' != $url ) {
				return $url;
			}
		}
		global $wpdb;
		$product = $wpdb->get_row( $wpdb->prepare( 'SELECT image1, product_images FROM ec_product WHERE product_id = %d', $this->product_id ) );
		if ( ! $product ) {
			return '';
		}
		$first = ( isset( $product->product_images ) && '' != $product->product_images ) ? trim( current( explode( ',', $product->product_images ) ) ) : '';
		if ( 'image:' == substr( $first, 0, 6 ) ) {
			return esc_url_raw( substr( $first, 6 ) );
		} else if ( '' != $first && ctype_digit( $first ) && function_exists( 'wp_get_attachment_image_src' ) ) {
			$media = wp_get_attachment_image_src( (int) $first, 'medium' );
			if ( $media && isset( $media[0] ) ) {
				return $media[0];
			}
		}
		return self::resolve_image_url( $product->image1 );
	}

	/**
	 * URL for an image1 value ( full URL or a file name in products/pics1 ).
	 *
	 * @since 6.0.0
	 * @param string $image Stored value.
	 * @return string URL or empty.
	 */
	public static function resolve_image_url( $image ) {
		$image = trim( (string) $image );
		if ( '' === $image ) {
			return '';
		}
		if ( 'http://' === substr( $image, 0, 7 ) || 'https://' === substr( $image, 0, 8 ) ) {
			return $image;
		}
		if ( false !== strpos( $image, '..' ) ) {
			return '';
		}
		if ( defined( 'EC_PLUGIN_DATA_DIRECTORY' ) && file_exists( EC_PLUGIN_DATA_DIRECTORY . '/products/pics1/' . $image ) && ! is_dir( EC_PLUGIN_DATA_DIRECTORY . '/products/pics1/' . $image ) ) {
			return plugins_url( 'wp-easycart-data/products/pics1/' . $image, EC_PLUGIN_DATA_DIRECTORY );
		}
		if ( file_exists( EC_PLUGIN_DIRECTORY . '/products/pics1/' . $image ) && ! is_dir( EC_PLUGIN_DIRECTORY . '/products/pics1/' . $image ) ) {
			return plugins_url( 'wp-easycart/products/pics1/' . $image, EC_PLUGIN_DIRECTORY );
		}
		return '';
	}

	/**
	 * Cached get_purchase_details_for() for this subscription.
	 *
	 * @since 6.0.0
	 * @return array|null
	 */
	public function get_purchase_details() {
		if ( false === $this->purchase_details ) {
			$this->purchase_details = self::get_purchase_details_for( $this->subscription_id, $this->product_id );
		}
		return $this->purchase_details;
	}

	/**
	 * What the customer bought when the subscription started: the order line that created it, with its
	 * option items ( variants ), advanced options ( modifiers ) and one-time sign-up fee.
	 *
	 * Orders link to a subscription through ec_order.subscription_id. The first order is the purchase;
	 * renewal orders copy its options, so if that line holds nothing the first renewal line that does is used.
	 * After a plan change ec_subscription.product_id no longer matches the line, which is flagged as plan_changed.
	 * Also used by the PRO admin subscription screen.
	 *
	 * @since 6.0.0
	 * @param int $subscription_id    Subscription.
	 * @param int $current_product_id Product the subscription is on now.
	 * @return array|null Null when no order line is linked to the subscription.
	 */
	public static function get_purchase_details_for( $subscription_id, $current_product_id = 0 ) {
		global $wpdb;
		$subscription_id = (int) $subscription_id;
		if ( $subscription_id <= 0 ) {
			return null;
		}
		$lines = $wpdb->get_results( $wpdb->prepare( 'SELECT ec_orderdetail.*, ec_order.order_date AS subscription_order_date FROM ec_orderdetail INNER JOIN ec_order ON ec_order.order_id = ec_orderdetail.order_id WHERE ec_order.subscription_id = %d ORDER BY ec_order.order_id ASC, ec_orderdetail.orderdetail_id ASC LIMIT 200', $subscription_id ) );
		if ( ! $lines ) {
			return null;
		}

		$first_order_id = (int) $lines[0]->order_id;
		$source         = null;
		foreach ( $lines as $line ) {
			if ( (int) $line->order_id != $first_order_id ) {
				break;
			}
			if ( null === $source ) {
				$source = $line;
			}
			if ( $current_product_id && (int) $line->product_id == (int) $current_product_id ) {
				$source = $line;
				break;
			}
		}

		$advanced = self::get_line_advanced_options( $source );
		if ( ! self::line_has_details( $source, $advanced ) ) {
			foreach ( $lines as $line ) {
				if ( (int) $line->orderdetail_id == (int) $source->orderdetail_id || (int) $line->product_id != (int) $source->product_id ) {
					continue;
				}
				$line_advanced = self::get_line_advanced_options( $line );
				if ( self::line_has_details( $line, $line_advanced ) ) {
					$source   = $line;
					$advanced = $line_advanced;
					break;
				}
			}
		}

		$options = array();
		for ( $i = 1; $i <= 5; $i++ ) {
			$name  = ( isset( $source->{'optionitem_name_' . $i} ) ) ? (string) $source->{'optionitem_name_' . $i} : '';
			$label = ( isset( $source->{'optionitem_label_' . $i} ) ) ? (string) $source->{'optionitem_label_' . $i} : '';
			if ( '' == $name && '' == $label ) {
				continue;
			}
			$price     = ( isset( $source->{'optionitem_price_' . $i} ) ) ? (float) $source->{'optionitem_price_' . $i} : 0;
			$options[] = array(
				'kind'           => 'basic',
				'type'           => 'basic',
				'label'          => $label,
				'name'           => $label,
				'value'          => $name,
				'raw_value'      => $name,
				'price_text'     => ( 0 != $price ) ? ( ( $price > 0 ) ? '+' : '' ) . $GLOBALS['currency']->get_currency_display( $price ) : '',
				'orderdetail_id' => (int) $source->orderdetail_id,
			);
		}
		foreach ( $advanced as $option ) {
			$value = (string) $option->option_value;
			if ( 'file' == $option->option_type ) {
				$parts = explode( '/', $value );
				$value = (string) end( $parts );
			} else if ( 'grid' == $option->option_type ) {
				$value = $option->optionitem_name . ' (' . $option->option_value . ')';
			}
			$options[] = array(
				'kind'           => 'advanced',
				'type'           => (string) $option->option_type,
				'label'          => ( isset( $option->option_label ) && '' != $option->option_label ) ? (string) $option->option_label : (string) $option->option_name,
				'name'           => ( '' != $option->option_name ) ? (string) $option->option_name : ( isset( $option->option_label ) ? (string) $option->option_label : '' ),
				'value'          => $value,
				'raw_value'      => (string) $option->option_value,
				'price_text'     => self::get_advanced_option_price_text( $option ),
				'orderdetail_id' => (int) $source->orderdetail_id,
			);
		}

		$details = array(
			'order_id'       => (int) $source->order_id,
			'orderdetail_id' => (int) $source->orderdetail_id,
			'order_date'     => (string) $source->subscription_order_date,
			'product_id'     => (int) $source->product_id,
			'title'          => (string) $source->title,
			'model_number'   => (string) $source->model_number,
			'image1'         => (string) $source->image1,
			'quantity'       => (int) $source->quantity,
			'signup_fee'     => ( isset( $source->subscription_signup_fee ) ) ? (float) $source->subscription_signup_fee : 0,
			'plan_changed'   => ( $current_product_id && (int) $source->product_id && (int) $source->product_id != (int) $current_product_id ),
			'options'        => $options,
		);
		return apply_filters( 'wp_easycart_subscription_purchase_details', $details, $subscription_id, $current_product_id );
	}

	/**
	 * ec_order_option rows for an order line.
	 *
	 * @param object $line ec_orderdetail row.
	 * @return array
	 */
	private static function get_line_advanced_options( $line ) {
		global $wpdb;
		if ( ! $line ) {
			return array();
		}
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ec_order_option WHERE orderdetail_id = %d ORDER BY option_order ASC, order_option_id ASC', (int) $line->orderdetail_id ) );
		return ( $rows ) ? $rows : array();
	}

	/**
	 * @param object $line     ec_orderdetail row.
	 * @param array  $advanced ec_order_option rows.
	 * @return bool
	 */
	private static function line_has_details( $line, $advanced ) {
		if ( count( $advanced ) > 0 || ( isset( $line->subscription_signup_fee ) && (float) $line->subscription_signup_fee > 0 ) ) {
			return true;
		}
		for ( $i = 1; $i <= 5; $i++ ) {
			if ( isset( $line->{'optionitem_name_' . $i} ) && '' != $line->{'optionitem_name_' . $i} ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Price note for a modifier, the same wording the order details use ( "+$3.00 per item" ).
	 *
	 * @param object $option ec_order_option row.
	 * @return string Unescaped text.
	 */
	private static function get_advanced_option_price_text( $option ) {
		$currency = $GLOBALS['currency'];
		$price    = ( isset( $option->optionitem_price ) ) ? (float) $option->optionitem_price : 0;
		$onetime  = ( isset( $option->optionitem_price_onetime ) ) ? (float) $option->optionitem_price_onetime : 0;
		$per_item = html_entity_decode( (string) wp_easycart_language()->get_text( 'cart', 'cart_item_adjustment' ), ENT_QUOTES, 'UTF-8' );
		$per_order = html_entity_decode( (string) wp_easycart_language()->get_text( 'cart', 'cart_order_adjustment' ), ENT_QUOTES, 'UTF-8' );
		if ( ! empty( $option->optionitem_enable_custom_price_label ) && ( 0 != $price || 0 != $onetime ) ) {
			return (string) $option->optionitem_custom_price_label;
		} else if ( $price > 0 ) {
			return trim( '+' . $currency->get_currency_display( $price ) . ' ' . $per_item );
		} else if ( $price < 0 ) {
			return trim( $currency->get_currency_display( $price ) . ' ' . $per_item );
		} else if ( $onetime > 0 ) {
			return trim( '+' . $currency->get_currency_display( $onetime ) . ' ' . $per_order );
		} else if ( $onetime < 0 ) {
			return trim( $currency->get_currency_display( $onetime ) . ' ' . $per_order );
		} else if ( isset( $option->optionitem_price_override ) && null !== $option->optionitem_price_override && (float) $option->optionitem_price_override > -1 ) {
			return trim( html_entity_decode( (string) wp_easycart_language()->get_text( 'cart', 'cart_item_new_price_option' ), ENT_QUOTES, 'UTF-8' ) . ' ' . $currency->get_currency_display( $option->optionitem_price_override ) );
		} else if ( isset( $option->option_price_change ) && '' != $option->option_price_change && 0 != (float) $option->option_price_change ) {
			return ( is_numeric( $option->option_price_change ) ) ? $currency->get_currency_display( $option->option_price_change ) : (string) $option->option_price_change;
		}
		return '';
	}

	/////////////////////////////////////////////////////////
	//Help Functions
	/////////////////////////////////////////////////////////

	private function get_new_bill_period_formatted( $length, $period ){

		$ret_string = "/";

		if( $length > 1 ){
			$ret_string .= $length . " ";
		}

		if( $period == "D" ){
			$ret_string .= "day";
		}else if( $period == "W" ){
			$ret_string .= "week";
		}else if( $period == "M" ){
			$ret_string .= "month";
		}else if( $period == "Y" ){
			$ret_string .= "year";
		}

		if( $length > 1 ){
			$ret_string .= "s";
		}

		return $ret_string;

	}

	private function get_bill_period_formatted( ){

		$ret_string = "/";

		if( $this->bill_length > 1 ){
			$ret_string .= $this->bill_length . " ";
		}

		if( $this->bill_period == "D" ){
			$ret_string .= "day";
		}else if( $this->bill_period == "W" ){
			$ret_string .= "week";
		}else if( $this->bill_period == "M" ){
			$ret_string .= "month";
		}else if( $this->bill_period == "Y" ){
			$ret_string .= "year";
		}

		if( $this->bill_length > 1 ){
			$ret_string .= "s";
		}

		global $wpdb;
		$model_number = $wpdb->get_var( $wpdb->prepare( "SELECT ec_product.model_number FROM ec_product WHERE ec_product.product_id = %d", $this->product_id ) );
		$ret_string = apply_filters( 'wp_easycart_subscription_price_formatting', $ret_string, $model_number, $this->product_id );

		return $ret_string;

	}

	////////////////////////////////////////////////////////////
	//
	// MAIN DB FUNCTIONS
	//
	////////////////////////////////////////////////////////////

	private function set_db_vars( $db_row ){

		$this->subscription_id = $db_row->subscription_id;
		$this->user_id = $db_row->user_id;
		$this->title = $db_row->title;
		$this->amount = $db_row->price;
		$this->quantity = $db_row->quantity;
		$this->product_id = $db_row->product_id;
		$this->trial_period_days = $db_row->trial_period_days;
		$this->bill_length = $db_row->payment_length;
		$this->bill_period = $db_row->payment_period;
		$this->payment_duration = $db_row->payment_duration;
		$this->status = $db_row->subscription_status;
		$this->last_billed = $db_row->last_payment_date;
		$this->next_payment = $db_row->next_payment_date;
		if( isset( $db_row->credit_card_type ) )
			$this->card_type = $db_row->credit_card_type;
		else
			$this->card_type = "";
		if( isset( $db_row->credit_card_last4 ) )
			$this->last4 = $db_row->credit_card_last4;
		else
			$this->last4 = "";
		$this->stripe_subscription_id = $db_row->stripe_subscription_id;
		$this->membership_page = $db_row->membership_page;
		$this->subscription_type = ( isset( $db_row->subscription_type ) ) ? $db_row->subscription_type : '';
		$this->start_date = ( isset( $db_row->start_date ) ) ? $db_row->start_date : '';
		$this->number_payments_completed = ( isset( $db_row->number_payments_completed ) ) ? (int) $db_row->number_payments_completed : 0;
		$this->num_failed_payment = ( isset( $db_row->num_failed_payment ) ) ? (int) $db_row->num_failed_payment : 0;
		$this->model_number = ( isset( $db_row->model_number ) ) ? $db_row->model_number : '';

	}

	////////////////////////////////////////////////////////////
	//
	// STRIPE FUNCTIONS
	//
	////////////////////////////////////////////////////////////

	private function set_stripe_vars( $subscription_row ){

		$this->stripe_subscription_id = $subscription_row->id;
		$this->title = $subscription_row->plan->name;
		$this->created = $subscription_row->plan->created;
		$this->amount = $this->convert_from_cents( $subscription_row->plan->amount );
		$this->product_id = $subscription_row->plan->id;
		$this->bill_length = $subscription_row->plan->interval_count;
		$this->bill_period = $this->stripe_convert_bill_period( $subscription_row->plan->interval );
		$this->status = $this->stripe_convert_status( $subscription_row->status );
		$this->last_billed = $subscription_row->current_period_start;
		$this->next_payment = $subscription_row->current_period_end;

	}

	private function convert_from_cents( $price ){
		return ( $price / 100 );
	}

	private function stripe_convert_bill_period( $period ){

		if( $period == "day" )
			return "D";
		else if( $period == "week" )
			return "W";
		else if( $period == "month" )
			return "M";
		else if( $period == "year" )
			return "Y";

	}

	private function stripe_convert_status( $status ){

		if( $status == "active" )
			return "Active";

	}

}

?>