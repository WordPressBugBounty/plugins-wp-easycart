<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class wp_easycart_admin_details_user extends wp_easycart_admin_details {

	public $user;
	public $billing_info;
	public $shipping_info;
	public $item;
	public $orders = array();
	public $order_items = array();
	public $order_stats;
	public $subscriptions = array();
	public $active_sub_count = 0;
	public $abandoned_carts = array();
	public $notes_feed = array();
	public $downloads = array();
	public $giftcards = array();
	public $top_products = array();
	public $overview_loaded = false;

	protected function init() {
		$this->docs_link = 'http://docs.wpeasycart.com/wp-easycart-administrative-console-guide/?wpeasycartadmin=1&section=user-accounts';
		$this->id = 0;
		$this->page = 'wp-easycart-users';
		$this->subpage = 'accounts';
		$this->action = 'admin.php?page=' . $this->page . '&subpage=' . $this->subpage;
		$this->form_action = 'add-new-user';
		$this->user = (object) array(
			'user_id' => '',
			'email' => '',
			'email_other' => '',
			'first_name' => '',
			'last_name' => '',
			'default_billing_address_id' => '',
			'default_shipping_address_id' => '',
			'user_level' => '',
			'is_subscriber' => '',
			'stripe_customer_id' => '',
			'default_card_type' => '',
			'default_card_last4' => '',
			'exclude_tax' => '',
			'exclude_shipping' => '',
			'user_notes' => '',
			'vat_registration_number' => '',
		);
		$this->billing_info = $this->shipping_info = (object) array(
			'address_id' => '',
			'first_name' => '',
			'last_name' => '',
			'company_name' => '',
			'address_line_1' => '',
			'address_line_2' => '',
			'city' => '',
			'state' => '',
			'zip' => '',
			'country' => '',
			'phone' => '',
		);
	}

	protected function init_data() {
		global $wpdb;
		$this->form_action = 'update-user';
		$user = $wpdb->get_row( $wpdb->prepare( 'SELECT ec_user.* FROM ec_user WHERE user_id = %d', (int) $_GET['user_id'] ) );
		if ( is_object( $user ) ) {
			$this->user = $user;
			$this->id = $this->user->user_id;
			$this->billing_info = $wpdb->get_row( $wpdb->prepare( 'SELECT ec_address.* FROM ec_address WHERE address_id = %d', $this->user->default_billing_address_id ) );
			$this->shipping_info = $wpdb->get_row( $wpdb->prepare( 'SELECT ec_address.* FROM ec_address WHERE address_id = %d', $this->user->default_shipping_address_id ) );
			do_action( 'wp_easycart_admin_user_details_loaded', $this );
		} else {
			$this->record_not_found = true;
		}
	}

	public function get_overview_gate() {
		return wp_easycart_admin_pro_gate::evaluate( array(
			'enabled_filter' => 'wp_easycart_admin_user_overview_enabled',
			'min_version'    => '5.8.17',
			'labels'         => array(
				'enabled' => __( 'Customer Snapshot', 'wp-easycart' ),
			),
		) );
	}

	public function get_currency_display( $amount ) {
		if ( isset( $GLOBALS['currency'] ) && is_object( $GLOBALS['currency'] ) && method_exists( $GLOBALS['currency'], 'get_currency_display' ) ) {
			return $GLOBALS['currency']->get_currency_display( $amount );
		}
		return number_format( (float) $amount, 2 );
	}

	public function print_customer_overview() {
		if ( empty( $this->user->user_id ) ) {
			return;
		}
		include( apply_filters( 'wp_easycart_admin_user_overview_file', EC_PLUGIN_DIRECTORY . '/admin/template/users/users/user-overview.php', $this ) );
	}
}
