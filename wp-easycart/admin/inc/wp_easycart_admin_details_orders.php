<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class wp_easycart_admin_details_orders extends wp_easycart_admin_details {

	public $order;
	public $item;
	public $order_timestamp;

	public function __construct() {
		parent::__construct();
		add_action( 'wp_easycart_admin_orders_details_basic_fields', array( $this, 'basic_fields' ) );
		add_action( 'wp_easycart_admin_orders_details_shipment', array( $this, 'shipment_fields' ) );
		add_action( 'wp_easycart_ecv2_order_details_payment_meta', array( $this, 'payment_fields' ) );
	}

	protected function init() {
		$this->docs_link = 'http://docs.wpeasycart.com/wp-easycart-administrative-console-guide/?wpeasycartadmin=1&section=order-management';
		$this->id = 0;
		$this->page = 'wp-easycart-orders';
		$this->subpage = 'orders';
		$this->action = 'admin.php?page=' . $this->page . '&subpage=' . $this->subpage;
		$this->form_action = 'add-new-order';
		$this->order = (object) array(
			'order_id' => '',
			'promo_code' => '',
			'giftcard_id' => '',
			'order_date' => '',
			'includes_preorder_items' => false,
			'includes_restaurant_type' => false,
			'pickup_date' => '',
			'pickup_asap' => '',
			'pickup_time' => '',
			'orderstatus_id' => '',
			'order_notes' => '',
			'order_customer_notes' => '',
			'creditcard_digits' => '',
			'agreed_to_terms' => '',
			'order_ip_address' => '',
			'cc_exp_month' => '',
			'cc_exp_year' => '',
			'card_holder_name' => '',

			'billing_first_name' => '',
			'billing_last_name' => '',
			'billing_company_name' => '',
			'billing_address_line_1' => '',
			'billing_address_line_2' => '',
			'billing_city' => '',
			'billing_state' => '',
			'billing_country' => '',
			'billing_zip' => '',
			'billing_phone' => '',
			'user_email' => '',

			'shipping_first_name' => '',
			'shipping_last_name' => '',
			'shipping_company_name' => '',
			'shipping_address_line_1' => '',
			'shipping_address_line_2' => '',
			'shipping_city' => '',
			'shipping_state' => '',
			'shipping_country' => '',
			'shipping_zip' => '',
			'shipping_phone' => '',

			'use_expedited_shipping' => '',
			'shipping_method' => '',
			'shipping_carrier' => '',
			'tracking_number' => '',
			'order_weight' => '',

			'sub_total' => '',
			'tax_total' => '',
			'shipping_total' => '',
			'discount_total' => '',
			'vat_total' => '',
			'duty_total' => '',
			'grand_total' => '',
			'refund_total' => '',
			'gst_total' => '',
			'gst_rate' => '',
			'pst_total' => '',
			'pst_rate' => '',
			'hst_total' => '',
			'hst_rate' => '',
			'vat_rate' => '',
			'vat_registration_number' => '',
			'order_fees' => array(),
		);
	}

	protected function init_data() {
		$order_id = ( isset( $_GET['order_id'] ) ) ? (int) $_GET['order_id'] : 0;
		$this->form_action = 'update-order';
		$order = $this->load_record( $this->wpdb->get_row( $this->wpdb->prepare( 'SELECT ec_order.*, ec_user.first_name, ec_user.last_name, billing_country.name_cnt AS billing_country_name, shipping_country.name_cnt AS shipping_country_name, ec_orderstatus.is_approved, ec_orderstatus.order_status FROM ec_order LEFT JOIN ec_orderstatus ON ( ec_orderstatus.status_id = ec_order.orderstatus_id ) LEFT JOIN ec_country AS billing_country ON ( billing_country.iso2_cnt = ec_order.billing_country ) LEFT JOIN ec_country AS shipping_country ON ( shipping_country.iso2_cnt = ec_order.shipping_country ) LEFT JOIN ec_user ON ( ec_user.user_id = ec_order.user_id ) WHERE order_id = %d', $order_id ) ) );
		if ( ! $order ) {
			return;
		}
		$this->order = $order;
		$this->id = $this->order->order_id;
		$this->order->order_fees = $this->wpdb->get_results( $this->wpdb->prepare( 'SELECT * FROM ec_order_fee WHERE order_id = %d ORDER BY order_fee_id ASC', $this->id ) );
		$now_server = $this->wpdb->get_var( 'SELECT NOW() AS the_time' );
		$now_timestamp = strtotime( $now_server );
		$now_gmt_timestampt = time();
		$storage_offset = $now_timestamp - $now_gmt_timestampt;
		$local_offset = get_option( 'gmt_offset' ) * 60 * 60;
		$date_diff = $local_offset - $storage_offset;
		$date = $this->order->order_date;
		$date_timestamp = ( $date ) ? strtotime( $date ) : time();
		$date_timestamp = $date_timestamp + $date_diff;
		$this->order_timestamp = $date_timestamp;
	}

	public function output( $type = 'edit' ) {
		$this->init();
		if ( 'edit' == $type ) {
			$this->init_data();
		}
		if ( $this->record_not_found ) {
			$this->print_record_not_found_notice();
			return;
		}
		include( EC_PLUGIN_DIRECTORY . '/admin/template/orders/orders/order-details.php' );
	}

	public function basic_fields() {
		$fields = apply_filters(
			'wp_easycart_admin_orders_details_basic_fields_list',
			array(
				array(
					'name' => 'order_notes',
					'type' => 'textarea',
					'label' => __( 'Administrative Order Notes', 'wp-easycart' ),
					'required' => false,
					'message' => __( 'Please enter administrative notes.', 'wp-easycart' ),
					'validation_type' => 'textarea',
					'visible' => false,
					'value' => $this->order->order_notes,
				),
				array(
					'name' => 'order_customer_notes',
					'type' => 'textarea',
					'label' => __( 'Customer Order Notes', 'wp-easycart' ),
					'required' => false,
					'message' => __( 'Please enter customer order notes.', 'wp-easycart' ),
					'validation_type' => 'textarea',
					'visible' => false,
					'value' => $this->order->order_customer_notes,
				),
			)
		);
		$this->print_fields( $fields );
	}

	public function shipment_fields() {
		/* V2.1: weight only — gift card + coupon moved to payment_fields()
		   ( Payment & Totals card ). The filter name is unchanged; filters
		   that append extra shipment fields keep working. */
		$fields = apply_filters(
			'wp_easycart_admin_orders_details_shipment_fields_list',
			array(
				array(
					'name' => 'order_weight',
					'type' => 'text',
					'label' => __( 'Order Weight', 'wp-easycart' ),
					'required' => false,
					'message' => __( 'Please enter an order weight.', 'wp-easycart' ),
					'validation_type' => 'text',
					'value' => $this->order->order_weight,
				),
			)
		);
		$this->print_fields( $fields );
	}

	public function payment_fields() {
		/* V2.1: gift card + coupon render inside the Payment & Totals card
		   via 'wp_easycart_ecv2_order_details_payment_meta'. Element IDs are
		   unchanged ( orders.js ec_admin_process_order_info reads them ). */
		$fields = apply_filters(
			'wp_easycart_ecv2_order_details_payment_fields_list',
			array(
				array(
					'name' => 'giftcard_id',
					'type' => 'text',
					'label' => __( 'Gift Card Used', 'wp-easycart' ),
					'required' => false,
					'validation_type' => 'text',
					'value' => $this->order->giftcard_id,
				),
				array(
					'name' => 'promo_code',
					'type' => 'text',
					'label' => __( 'Coupon Code Used', 'wp-easycart' ),
					'required' => false,
					'validation_type' => 'text',
					'value' => $this->order->promo_code,
				),
			)
		);
		$this->print_fields( $fields );
	}
}

if ( ! function_exists( 'wp_easycart_format_phone' ) ) :
/**
 * Display-format a phone number using the order's country as a hint.
 * Returns array( 'display' => '(541) 969-0424', 'href' => 'tel:+15419690424' ).
 * Covers the common patterns without a libphonenumber dependency;
 * filter 'wp_easycart_ecv2_format_phone' lets PRO/plugins replace it.
 * Global (not a class method) because the V2 template is rendered by the
 * base wp_easycart_admin_details_orders class.
 */
function wp_easycart_format_phone( $raw, $country = '' ) {
	$raw     = trim( (string) $raw );
	$country = strtoupper( trim( (string) $country ) );
	if ( '' === $raw ) {
		return array( 'display' => '', 'href' => '' );
	}
	$has_plus = ( 0 === strpos( $raw, '+' ) ) || ( 0 === strpos( $raw, '00' ) );
	$digits   = preg_replace( '/\D/', '', $raw );
	if ( 0 === strpos( $raw, '00' ) ) {
		$digits = substr( $digits, 2 );
	}
	$display = $raw;
	$href    = '';

	$nanp = array( 'US', 'CA', 'PR', 'VI', 'GU', 'AS', 'MP', 'BS', 'BB', 'BM', 'DO', 'JM', 'TT' );

	if ( $has_plus && strlen( $digits ) >= 8 ) {
		/* International: +CC then group the national part. */
		$cc  = '';
		$nat = $digits;
		foreach ( array( 1, 2, 3 ) as $len ) {
			$try = substr( $digits, 0, $len );
			if ( '1' === $try || ( 2 === $len && in_array( $try, array( '20','27','30','31','32','33','34','36','39','40','41','43','44','45','46','47','48','49','51','52','53','54','55','56','57','58','60','61','62','63','64','65','66','81','82','84','86','90','91','92','93','94','95','98' ), true ) ) || 3 === $len ) {
				$cc  = $try;
				$nat = substr( $digits, $len );
				break;
			}
		}
		if ( '1' === $cc && 10 === strlen( $nat ) ) {
			$display = '+1 (' . substr( $nat, 0, 3 ) . ') ' . substr( $nat, 3, 3 ) . '-' . substr( $nat, 6 );
		} else {
			$display = '+' . $cc . ' ' . wp_easycart_group_phone_digits( $nat );
		}
		$href = '+' . $digits;
	} else if ( in_array( $country, $nanp, true ) || '' === $country ) {
		if ( 11 === strlen( $digits ) && '1' === $digits[0] ) {
			$digits = substr( $digits, 1 );
		}
		if ( 10 === strlen( $digits ) ) {
			$display = '(' . substr( $digits, 0, 3 ) . ') ' . substr( $digits, 3, 3 ) . '-' . substr( $digits, 6 );
			$href    = '+1' . $digits;
		}
	} else if ( 'GB' === $country ) {
		if ( 11 === strlen( $digits ) && '0' === $digits[0] ) {
			if ( '02' === substr( $digits, 0, 2 ) ) {
				$display = substr( $digits, 0, 3 ) . ' ' . substr( $digits, 3, 4 ) . ' ' . substr( $digits, 7 );
			} else if ( '07' === substr( $digits, 0, 2 ) ) {
				$display = substr( $digits, 0, 5 ) . ' ' . substr( $digits, 5 );
			} else {
				$display = substr( $digits, 0, 4 ) . ' ' . substr( $digits, 4, 3 ) . ' ' . substr( $digits, 7 );
			}
			$href = '+44' . substr( $digits, 1 );
		}
	} else if ( 'AU' === $country ) {
		if ( 10 === strlen( $digits ) && '0' === $digits[0] ) {
			$display = ( '04' === substr( $digits, 0, 2 ) )
				? substr( $digits, 0, 4 ) . ' ' . substr( $digits, 4, 3 ) . ' ' . substr( $digits, 7 )
				: substr( $digits, 0, 2 ) . ' ' . substr( $digits, 2, 4 ) . ' ' . substr( $digits, 6 );
			$href = '+61' . substr( $digits, 1 );
		}
	} else if ( 'FR' === $country ) {
		if ( 10 === strlen( $digits ) && '0' === $digits[0] ) {
			$display = implode( ' ', str_split( $digits, 2 ) );
			$href    = '+33' . substr( $digits, 1 );
		}
	} else if ( 'DE' === $country || 'NL' === $country || 'ES' === $country || 'IT' === $country ) {
		if ( strlen( $digits ) >= 9 ) {
			$display = wp_easycart_group_phone_digits( $digits );
		}
	}

	if ( '' === $href ) {
		$href = ( $has_plus ? '+' : '' ) . $digits;
	}
	return apply_filters( 'wp_easycart_ecv2_format_phone', array( 'display' => $display, 'href' => 'tel:' . $href ), $raw, $country );
}

function wp_easycart_group_phone_digits( $d ) {
	$len = strlen( $d );
	if ( $len <= 4 ) {
		return $d;
	}
	/* Leading area-ish block, then 3s, with a final 3 or 4. */
	$first = ( $len % 3 === 0 ) ? 3 : ( $len % 3 === 1 ? 4 : 2 );
	$out   = array( substr( $d, 0, $first ) );
	$rest  = substr( $d, $first );
	while ( strlen( $rest ) > 4 ) {
		$out[] = substr( $rest, 0, 3 );
		$rest  = substr( $rest, 3 );
	}
	if ( '' !== $rest ) {
		$out[] = $rest;
	}
	return implode( ' ', $out );
}
endif;