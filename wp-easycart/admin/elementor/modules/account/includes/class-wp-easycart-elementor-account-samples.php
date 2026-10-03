<?php
/**
 * Sample customer data for the account widgets in the Elementor editor ( 6.0.2 ).
 *
 * The editing admin is rarely signed in to a store account, and a store account may have no orders or subscriptions, so
 * the editor would show empty boxes. These samples are real EasyCart objects ( ec_address, ec_orderdisplay,
 * ec_orderdetail, ec_orderlist, ec_subscription, ec_subscription_list ) built from complete rows, drawn by EasyCart's own
 * templates, so what the merchant styles is what customers see. The order lines use the store's first active products
 * ( titles, images, links ). Every sample id is above the store's highest real id, so no template, hook or query can find a
 * real order, order line or subscription behind them. Only ever used while the editor or its preview draws.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Account_Samples' ) ) :

	/**
	 * Sample customer, orders, subscription and downloads.
	 */
	class WP_EasyCart_Elementor_Account_Samples {

		/**
		 * First sample order id ( above every real one ).
		 *
		 * @var int
		 */
		private $order_base = 0;

		/**
		 * First sample order line id.
		 *
		 * @var int
		 */
		private $detail_base = 0;

		/**
		 * Sample subscription id.
		 *
		 * @var int
		 */
		private $subscription_id = 0;

		/**
		 * Sample products ( id, title, model number, price, image URL ).
		 *
		 * @var array
		 */
		private $products = array();

		/**
		 * Order rows by id.
		 *
		 * @var array
		 */
		private $orders = array();

		/**
		 * Order line rows by order id.
		 *
		 * @var array
		 */
		private $details = array();

		/**
		 * Sets the sample ids and products.
		 */
		public function __construct() {
			global $wpdb;
			$this->order_base      = (int) $wpdb->get_var( 'SELECT MAX(order_id) FROM ec_order' ) + 100001;
			$this->detail_base     = (int) $wpdb->get_var( 'SELECT MAX(orderdetail_id) FROM ec_orderdetail' ) + 100001;
			$this->subscription_id = (int) $wpdb->get_var( 'SELECT MAX(subscription_id) FROM ec_subscription' ) + 100001;
			$this->products        = $this->load_products();
			$this->build_orders();
		}

		/**
		 * The store's first active products, or made-up ones for a store without products.
		 *
		 * @return array
		 */
		private function load_products() {
			global $wpdb;
			$rows     = $wpdb->get_results( 'SELECT product_id, title, model_number, price, image1, product_images, is_shippable FROM ec_product WHERE activate_in_store = 1 ORDER BY product_id ASC LIMIT 3' );
			$products = array();
			foreach ( (array) $rows as $row ) {
				$products[] = array(
					'product_id'   => (int) $row->product_id,
					'title'        => (string) $row->title,
					'model_number' => (string) $row->model_number,
					'price'        => max( 1, (float) $row->price ),
					'image'        => $this->product_image( $row ),
					'shippable'    => (int) $row->is_shippable,
				);
			}
			$made_up = array(
				array( __( 'Sample T-shirt', 'wp-easycart' ), 24.00 ),
				array( __( 'Sample mug', 'wp-easycart' ), 12.50 ),
				array( __( 'Sample poster', 'wp-easycart' ), 18.00 ),
			);
			for ( $index = count( $products ); $index < 3; $index++ ) {
				$products[] = array(
					'product_id'   => 0,
					'title'        => $made_up[ $index ][0],
					'model_number' => 'SAMPLE-' . ( $index + 1 ),
					'price'        => $made_up[ $index ][1],
					'image'        => '',
					'shippable'    => 1,
				);
			}
			return $products;
		}

		/**
		 * A product's first image URL.
		 *
		 * @param object $row Product row.
		 * @return string
		 */
		private function product_image( $row ) {
			$first = ( isset( $row->product_images ) && '' !== (string) $row->product_images ) ? trim( current( explode( ',', (string) $row->product_images ) ) ) : '';
			if ( 'image:' === substr( $first, 0, 6 ) ) {
				return esc_url_raw( substr( $first, 6 ) );
			}
			if ( '' !== $first && ctype_digit( $first ) ) {
				$media = wp_get_attachment_image_src( (int) $first, 'medium' );
				if ( is_array( $media ) && isset( $media[0] ) ) {
					return (string) $media[0];
				}
			}
			if ( class_exists( 'ec_subscription' ) && method_exists( 'ec_subscription', 'resolve_image_url' ) ) {
				return (string) ec_subscription::resolve_image_url( isset( $row->image1 ) ? $row->image1 : '' );
			}
			return '';
		}

		/**
		 * The sample customer: the shopper's ec_user with a sample name, email, card and addresses.
		 *
		 * @param object $base The current ec_user.
		 * @return object
		 */
		public function user( $base ) {
			$user                          = is_object( $base ) ? clone $base : new stdClass();
			$user->user_id                 = 0;
			$user->first_name              = 'Alex';
			$user->last_name               = 'Morgan';
			$user->email                   = 'alex.morgan@example.com';
			$user->email_other             = '';
			$user->vat_registration_number = '';
			$user->is_subscriber           = 1;
			$user->card_type               = 'Visa';
			$user->last4                   = '4242';
			if ( class_exists( 'ec_address' ) ) {
				$user->billing  = new ec_address( 'Alex', 'Morgan', '123 Market Street', 'Suite 4', 'Springfield', 'IL', '62701', 'US', '555-0142', '' );
				$user->shipping = new ec_address( 'Alex', 'Morgan', '123 Market Street', 'Suite 4', 'Springfield', 'IL', '62701', 'US', '555-0142', '' );
			}
			return $user;
		}

		/**
		 * Builds three sample orders and their lines.
		 */
		private function build_orders() {
			global $wpdb;
			$statuses  = $wpdb->get_results( 'SELECT status_id, order_status FROM ec_orderstatus WHERE is_approved = 1 ORDER BY status_id ASC LIMIT 3' );
			$plan      = array(
				array(
					'days'  => 3,
					'lines' => array( array( 0, 2 ), array( 1, 1 ) ),
				),
				array(
					'days'  => 24,
					'lines' => array( array( 2, 1 ) ),
				),
				array(
					'days'  => 61,
					'lines' => array( array( 1, 3 ) ),
				),
			);
			$detail_id = $this->detail_base;
			foreach ( $plan as $index => $order ) {
				$order_id = $this->order_base + ( 2 - $index );
				$status   = ( is_array( $statuses ) && count( $statuses ) ) ? $statuses[ $index % count( $statuses ) ] : null;
				$date     = gmdate( 'Y-m-d H:i:s', time() - $order['days'] * DAY_IN_SECONDS );
				$subtotal = 0;
				$lines    = array();
				foreach ( $order['lines'] as $line ) {
					$product   = $this->products[ $line[0] ];
					$quantity  = (int) $line[1];
					$total     = round( $product['price'] * $quantity, 2 );
					$subtotal += $total;
					$lines[]   = $this->detail_row( $detail_id++, $order_id, $product, $quantity, $total, $date );
				}
				$shipping                   = 5.00;
				$tax                        = round( $subtotal * 0.08, 2 );
				$this->orders[ $order_id ]  = $this->order_row(
					array(
						'order_id'       => $order_id,
						'order_date'     => $date,
						'orderstatus_id' => $status ? (int) $status->status_id : 3,
						'order_status'   => $status ? (string) $status->order_status : __( 'Order Shipped', 'wp-easycart' ),
						'sub_total'      => $subtotal,
						'shipping_total' => $shipping,
						'tax_total'      => $tax,
						'grand_total'    => $subtotal + $shipping + $tax,
					)
				);
				$this->details[ $order_id ] = $lines;
			}
		}

		/**
		 * A complete ec_order row ( every column ec_orderdisplay reads ).
		 *
		 * @param array $values Values that differ from the defaults.
		 * @return object
		 */
		private function order_row( $values ) {
			$row = array(
				'order_id'                 => 0,
				'order_date'               => gmdate( 'Y-m-d H:i:s' ),
				'orderstatus_id'           => 3,
				'order_status'             => '',
				'order_weight'             => 1,
				'is_approved'              => 1,
				'sub_total'                => 0,
				'tip_total'                => 0,
				'shipping_total'           => 0,
				'tax_total'                => 0,
				'discount_total'           => 0,
				'offer_discount_total'     => 0,
				'applied_offers'           => '',
				'duty_total'               => 0,
				'vat_total'                => 0,
				'vat_rate'                 => 0,
				'grand_total'              => 0,
				'refund_total'             => 0,
				'gst_total'                => 0,
				'gst_rate'                 => 0,
				'pst_total'                => 0,
				'pst_rate'                 => 0,
				'hst_total'                => 0,
				'hst_rate'                 => 0,
				'promo_code'               => '',
				'promo_code_message'       => '',
				'giftcard_id'              => '',
				'use_expedited_shipping'   => 0,
				'shipping_method'          => __( 'Standard shipping', 'wp-easycart' ),
				'shipping_carrier'         => '',
				'tracking_number'          => '',
				'user_email'               => 'alex.morgan@example.com',
				'email_other'              => '',
				'user_level'               => 'shopper',
				'billing_first_name'       => 'Alex',
				'billing_last_name'        => 'Morgan',
				'billing_company_name'     => '',
				'billing_address_line_1'   => '123 Market Street',
				'billing_address_line_2'   => 'Suite 4',
				'billing_city'             => 'Springfield',
				'billing_state'            => 'IL',
				'billing_zip'              => '62701',
				'billing_country'          => 'US',
				'billing_country_name'     => 'United States',
				'billing_phone'            => '555-0142',
				'vat_registration_number'  => '',
				'shipping_first_name'      => 'Alex',
				'shipping_last_name'       => 'Morgan',
				'shipping_company_name'    => '',
				'shipping_address_line_1'  => '123 Market Street',
				'shipping_address_line_2'  => 'Suite 4',
				'shipping_city'            => 'Springfield',
				'shipping_state'           => 'IL',
				'shipping_zip'             => '62701',
				'shipping_country'         => 'US',
				'shipping_country_name'    => 'United States',
				'shipping_phone'           => '555-0142',
				'order_customer_notes'     => '',
				'card_holder_name'         => 'Alex Morgan',
				'creditcard_digits'        => '4242',
				'guest_key'                => '',
				'fraktjakt_order_id'       => '',
				'fraktjakt_shipment_id'    => '',
				'subscription_id'          => 0,
				'payment_method'           => 'visa',
				'paypal_email_id'          => '',
				'paypal_payer_id'          => '',
				'includes_preorder_items'  => 0,
				'includes_restaurant_type' => 0,
				'pickup_date'              => '',
				'pickup_asap'              => '',
				'pickup_time'              => '',
				'location_id'              => 0,
				'success_page_shown'       => 1,
			);
			return (object) array_merge( $row, $values );
		}

		/**
		 * A complete order line row ( every column ec_orderdetail reads ).
		 *
		 * @param int    $detail_id Line id.
		 * @param int    $order_id  Order id.
		 * @param array  $product   Sample product.
		 * @param int    $quantity  Quantity.
		 * @param float  $total     Line total.
		 * @param string $date      Order date.
		 * @return object
		 */
		private function detail_row( $detail_id, $order_id, $product, $quantity, $total, $date ) {
			return (object) array(
				'orderdetail_id'             => $detail_id,
				'order_id'                   => $order_id,
				'product_id'                 => $product['product_id'],
				'title'                      => $product['title'],
				'model_number'               => $product['model_number'],
				'order_date'                 => $date,
				'unit_price'                 => $product['price'],
				'unit_discount_promotion'    => 0,
				'unit_discount_coupon'       => 0,
				'total_price'                => $total,
				'total_discount_promotion'   => 0,
				'total_discount_coupon'      => 0,
				'is_free_gift'               => 0,
				'bundle_group_key'           => '',
				'bundle_product_id'          => 0,
				'applied_offers'             => '',
				'quantity'                   => $quantity,
				'image1'                     => $product['image'],
				'optionitem_name_1'          => '',
				'optionitem_name_2'          => '',
				'optionitem_name_3'          => '',
				'optionitem_name_4'          => '',
				'optionitem_name_5'          => '',
				'optionitem_label_1'         => '',
				'optionitem_label_2'         => '',
				'optionitem_label_3'         => '',
				'optionitem_label_4'         => '',
				'optionitem_label_5'         => '',
				'optionitem_price_1'         => '0.00',
				'optionitem_price_2'         => '0.00',
				'optionitem_price_3'         => '0.00',
				'optionitem_price_4'         => '0.00',
				'optionitem_price_5'         => '0.00',
				'use_advanced_optionset'     => 0,
				'use_both_option_types'      => 0,
				'giftcard_id'                => '',
				'gift_card_message'          => '',
				'gift_card_from_name'        => '',
				'gift_card_to_name'          => '',
				'gift_card_email'            => '',
				'is_download'                => 0,
				'is_giftcard'                => 0,
				'is_taxable'                 => 1,
				'is_shippable'               => $product['shippable'],
				'manufacturer_name'          => '',
				'download_file_name'         => '',
				'download_key'               => '',
				'maximum_downloads_allowed'  => 0,
				'download_timelimit_seconds' => 0,
				'is_amazon_download'         => 0,
				'amazon_key'                 => '',
				'include_code'               => 0,
				'subscription_signup_fee'    => 0,
				'is_deconetwork'             => 0,
				'deconetwork_id'             => '',
				'deconetwork_name'           => '',
				'deconetwork_product_code'   => '',
				'deconetwork_options'        => '',
				'deconetwork_color_code'     => '',
				'deconetwork_product_id'     => '',
				'deconetwork_image_link'     => '',
				'customfield_data'           => '',
			);
		}

		/**
		 * Whether an order id is a sample one.
		 *
		 * @param int $order_id Order id.
		 * @return bool
		 */
		public function is_sample_order( $order_id ) {
			return isset( $this->orders[ (int) $order_id ] );
		}

		/**
		 * Whether an order line id is a sample one.
		 *
		 * @param int $detail_id Line id.
		 * @return bool
		 */
		public function is_sample_detail( $detail_id ) {
			return (int) $detail_id >= $this->detail_base;
		}

		/**
		 * The lines of a sample order ( null for a real order ).
		 *
		 * @param int $order_id Order id.
		 * @return array|null
		 */
		public function detail_rows( $order_id ) {
			return isset( $this->details[ (int) $order_id ] ) ? $this->details[ (int) $order_id ] : null;
		}

		/**
		 * The sample order list ( ec_orderlist ).
		 *
		 * @return ec_orderlist|object
		 */
		public function order_list() {
			$list         = class_exists( 'ec_orderlist' ) ? new ec_orderlist( 0 ) : new stdClass();
			$list->orders = array();
			foreach ( $this->orders as $row ) {
				$list->orders[] = new ec_orderdisplay( $row );
			}
			usort(
				$list->orders,
				function ( $a, $b ) {
					return (int) $b->order_id - (int) $a->order_id;
				}
			);
			$list->num_orders = count( $list->orders );
			return $list;
		}

		/**
		 * The newest sample order, built for its details page.
		 *
		 * @return ec_orderdisplay
		 */
		public function order_details() {
			$order_id = max( array_keys( $this->orders ) );
			$order    = new ec_orderdisplay( $this->orders[ $order_id ] );
			$cart     = array();
			$lines    = array();
			foreach ( $this->details[ $order_id ] as $row ) {
				$cart[]  = (object) array(
					'orderdetail_id' => $row->orderdetail_id,
					'product_id'     => $row->product_id,
					'title'          => $row->title,
					'quantity'       => $row->quantity,
					'unit_price'     => $row->unit_price,
					'total_price'    => $row->total_price,
					'is_shippable'   => $row->is_shippable,
					'is_download'    => 0,
					'image1'         => $row->image1,
					'model_number'   => $row->model_number,
				);
				$lines[] = new ec_orderdetail( $row );
			}
			$order->cart         = (object) array( 'cart' => $cart );
			$order->orderdetails = $lines;
			return $order;
		}

		/**
		 * A sample subscription.
		 *
		 * @param bool $details Built for its details page.
		 * @return ec_subscription
		 */
		public function subscription( $details = false ) {
			$product = $this->products[0];
			$row     = (object) array(
				'subscription_id'           => $this->subscription_id,
				'user_id'                   => 0,
				'title'                     => $product['title'],
				'price'                     => $product['price'],
				'quantity'                  => 1,
				'product_id'                => $product['product_id'],
				'trial_period_days'         => 0,
				'payment_length'            => 1,
				'payment_period'            => 'M',
				'payment_duration'          => 0,
				'subscription_status'       => 'Active',
				'last_payment_date'         => (string) ( time() - 10 * DAY_IN_SECONDS ),
				'next_payment_date'         => (string) ( time() + 20 * DAY_IN_SECONDS ),
				'credit_card_type'          => 'Visa',
				'credit_card_last4'         => '4242',
				'stripe_subscription_id'    => '',
				'membership_page'           => '',
				'subscription_type'         => '',
				'start_date'                => gmdate( 'Y-m-d H:i:s', time() - 70 * DAY_IN_SECONDS ),
				'number_payments_completed' => 3,
				'num_failed_payment'        => 0,
				'model_number'              => $product['model_number'],
			);
			return new ec_subscription( $row, $details );
		}

		/**
		 * The sample subscription list ( ec_subscription_list ).
		 *
		 * @param object $user Customer the list belongs to.
		 * @return ec_subscription_list|object
		 */
		public function subscription_list( $user ) {
			$list                    = class_exists( 'ec_subscription_list' ) ? new ec_subscription_list( $user ) : new stdClass();
			$list->subscription_list = array( $this->subscription( false ) );
			return $list;
		}

		/**
		 * Download rows for the dashboard template ( ec_db::get_download_list() shape ).
		 *
		 * @return array
		 */
		public function download_rows() {
			$order_id = max( array_keys( $this->orders ) );
			$line     = $this->details[ $order_id ][0];
			return array(
				(object) array(
					'download_id'                => 'sample',
					'order_id'                   => $order_id,
					'orderdetail_id'             => $line->orderdetail_id,
					'title'                      => __( 'Sample guide (PDF)', 'wp-easycart' ),
					'is_download'                => 1,
					'is_approved'                => 1,
					'download_count'             => 1,
					'maximum_downloads_allowed'  => 5,
					'download_timelimit_seconds' => 0,
				),
			);
		}

		/**
		 * Download items for the Downloads view ( see WP_EasyCart_Elementor_Account_Page::wpec_downloads() ).
		 *
		 * @return array
		 */
		public function download_items() {
			$order_id                        = max( array_keys( $this->orders ) );
			$row                             = clone $this->details[ $order_id ][0];
			$row->title                      = __( 'Sample guide (PDF)', 'wp-easycart' );
			$item                            = new ec_orderdetail( $row ); /* built as a plain line: a download line would look its file up */
			$item->is_download               = 1;
			$item->download_id               = 'sample';
			$item->download_count            = 1;
			$item->maximum_downloads_allowed = 5;
			$item->timecheck                 = 0;
			return array(
				array(
					'item'     => $item,
					'extra'    => array(),
					'order_id' => $order_id,
				),
			);
		}
	}

endif;
