<?php
/**
 * WordPress personal data tools for WP EasyCart ( 6.0.2 ).
 *
 * Tools › Export Personal Data gets a "WP EasyCart" exporter: the customer account, its saved addresses, the newsletter
 * subscription with its consent record, every order ( number, date, status, total, items, and since 6.0.2 its addresses,
 * phone numbers, network address, notes and gift details ), saved carts, and ( 6.0.2 ) subscriptions, reviews written,
 * back-in-stock sign-ups and the store's email log. Checkout field answers have their own exporter
 * ( wp_easycart_order_fields ), so they are not repeated here.
 *
 * Tools › Erase Personal Data gets a "WP EasyCart" eraser: it ends the newsletter subscription ( through
 * wp_easycart_subscribers, source privacy, so a connected mailing service hears about it ), deletes saved carts and
 * back-in-stock sign-ups, stops review requests, and fires wp_easycart_privacy_erased for extensions. Customer accounts,
 * orders and reviews are kept and reported as retained: order records are needed for tax and accounting, and staff can
 * remove or anonymize an account from the customer screen.
 *
 * @since 6.0.2
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_privacy' ) ) :

	// phpcs:disable PEAR.NamingConventions.ValidClassName -- WP EasyCart class names are lower case.
	/**
	 * The WP EasyCart personal data exporter and eraser.
	 *
	 * @since 6.0.2
	 */
	final class wp_easycart_privacy {
		// phpcs:enable PEAR.NamingConventions.ValidClassName

		/** Exporter and eraser key. */
		const KEY = 'wp-easycart';

		/** Orders per export page. */
		const ORDERS_PER_PAGE = 50;

		/** Saved carts exported at most. */
		const CARTS_LIMIT = 200;

		/**
		 * Register hooks.
		 *
		 * @since 6.0.2
		 */
		public static function init() {
			add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
			add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
		}

		/**
		 * Filter wp_privacy_personal_data_exporters.
		 *
		 * @since 6.0.2
		 * @param array $exporters Exporters.
		 * @return array
		 */
		public static function register_exporter( $exporters ) {
			$exporters[ self::KEY ] = array(
				'exporter_friendly_name' => __( 'WP EasyCart', 'wp-easycart' ),
				'callback'               => array( __CLASS__, 'export_personal_data' ),
			);
			return $exporters;
		}

		/**
		 * Filter wp_privacy_personal_data_erasers.
		 *
		 * @since 6.0.2
		 * @param array $erasers Erasers.
		 * @return array
		 */
		public static function register_eraser( $erasers ) {
			$erasers[ self::KEY ] = array(
				'eraser_friendly_name' => __( 'WP EasyCart', 'wp-easycart' ),
				'callback'             => array( __CLASS__, 'erase_personal_data' ),
			);
			return $erasers;
		}

		// Export.

		/**
		 * What WP EasyCart keeps about an email address. Page 1 has the account, addresses, newsletter subscription and saved
		 * carts with the first orders; later pages carry the rest of the orders.
		 *
		 * @since 6.0.2
		 * @param string $email Email address.
		 * @param int    $page  Page ( 50 orders each ).
		 * @return array { data, done }
		 */
		public static function export_personal_data( $email, $page = 1 ) {
			$email = trim( (string) $email );
			$page  = max( 1, (int) $page );
			if ( ! is_email( $email ) ) {
				return array(
					'data' => array(),
					'done' => true,
				);
			}
			$user = self::account( $email );
			$data = array();
			if ( 1 === $page ) {
				$user_id = $user ? (int) $user->user_id : 0;
				/* 6.0.2: also subscriptions, reviews written, back-in-stock sign-ups and the emails the store sent. */
				$data = array_merge( self::export_account( $user ), self::export_subscription( $email ), self::export_saved_carts( $email ), self::export_subscriptions( $email, $user_id ), self::export_reviews( $email, $user_id ), self::export_stock_notices( $email ), self::export_email_log( $email ) );
			}
			$orders = self::export_orders( $email, $user ? (int) $user->user_id : 0, $page );
			return array(
				'data' => array_merge( $data, $orders['data'] ),
				'done' => $orders['done'],
			);
		}

		/**
		 * The customer account with this email.
		 *
		 * @param string $email Email address.
		 * @return object|null ec_user row.
		 */
		private static function account( $email ) {
			global $wpdb;
			$user = $wpdb->get_row( $wpdb->prepare( 'SELECT user_id, email, email_other, first_name, last_name, vat_registration_number, is_subscriber, default_card_type, default_card_last4, date_created, last_login FROM ec_user WHERE email = %s LIMIT 1', $email ) );
			return $user ? $user : null;
		}

		/**
		 * The account and its saved addresses.
		 *
		 * @param object|null $user ec_user row.
		 * @return array Export groups.
		 */
		private static function export_account( $user ) {
			global $wpdb;
			if ( ! $user ) {
				return array();
			}
			$card   = ( '' !== (string) $user->default_card_last4 ) ? trim( $user->default_card_type . ' ' . $user->default_card_last4 ) : '';
			$groups = array(
				array(
					'group_id'          => 'wp-easycart-account',
					'group_label'       => __( 'Store account', 'wp-easycart' ),
					'group_description' => __( 'Your customer account in the store.', 'wp-easycart' ),
					'item_id'           => 'wpec-account-' . (int) $user->user_id,
					'data'              => self::items(
						array(
							array( __( 'Email', 'wp-easycart' ), $user->email ),
							array( __( 'Other email', 'wp-easycart' ), $user->email_other ),
							array( __( 'First name', 'wp-easycart' ), $user->first_name ),
							array( __( 'Last name', 'wp-easycart' ), $user->last_name ),
							array( __( 'VAT registration number', 'wp-easycart' ), $user->vat_registration_number ),
							array( __( 'Newsletter', 'wp-easycart' ), (int) $user->is_subscriber ? __( 'Subscribed', 'wp-easycart' ) : __( 'Not subscribed', 'wp-easycart' ) ),
							array( __( 'Saved card', 'wp-easycart' ), $card ),
							array( __( 'Account created', 'wp-easycart' ), self::date( $user->date_created ) ),
							array( __( 'Last signed in', 'wp-easycart' ), self::date( $user->last_login ) ),
						)
					),
				),
			);

			$addresses = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT address_id, first_name, last_name, company_name, address_line_1, address_line_2, city, state, zip, country, phone FROM ec_address WHERE user_id = %d ORDER BY address_id ASC', (int) $user->user_id ) );
			foreach ( $addresses as $address ) {
				$items = self::items(
					array(
						array( __( 'Name', 'wp-easycart' ), trim( $address->first_name . ' ' . $address->last_name ) ),
						array( __( 'Company', 'wp-easycart' ), $address->company_name ),
						array( __( 'Address line 1', 'wp-easycart' ), $address->address_line_1 ),
						array( __( 'Address line 2', 'wp-easycart' ), $address->address_line_2 ),
						array( __( 'City', 'wp-easycart' ), $address->city ),
						array( __( 'State', 'wp-easycart' ), $address->state ),
						array( __( 'Postal code', 'wp-easycart' ), $address->zip ),
						array( __( 'Country', 'wp-easycart' ), $address->country ),
						array( __( 'Phone', 'wp-easycart' ), $address->phone ),
					)
				);
				if ( $items ) {
					$groups[] = array(
						'group_id'    => 'wp-easycart-addresses',
						'group_label' => __( 'Store addresses', 'wp-easycart' ),
						'item_id'     => 'wpec-address-' . (int) $address->address_id,
						'data'        => $items,
					);
				}
			}
			return $groups;
		}

		/**
		 * The newsletter subscription and its consent record.
		 *
		 * @param string $email Email address.
		 * @return array Export groups.
		 */
		private static function export_subscription( $email ) {
			if ( ! class_exists( 'wp_easycart_subscribers' ) ) {
				return array();
			}
			$row = wp_easycart_subscribers::get( $email );
			if ( ! $row ) {
				return array();
			}
			$fields = array(
				array( __( 'Email', 'wp-easycart' ), $row->email ),
				array( __( 'First name', 'wp-easycart' ), $row->first_name ),
				array( __( 'Last name', 'wp-easycart' ), $row->last_name ),
			);
			if ( wp_easycart_subscribers::columns_ready() ) {
				$fields[] = array( __( 'Signed up', 'wp-easycart' ), self::date( $row->date_added ) );
				$fields[] = array( __( 'Signed up from', 'wp-easycart' ), wp_easycart_subscribers::source_label( $row->source ) );
				$fields[] = array( __( 'Network address', 'wp-easycart' ), $row->ip_address );
			}
			return array(
				array(
					'group_id'    => 'wp-easycart-newsletter',
					'group_label' => __( 'Store newsletter', 'wp-easycart' ),
					'item_id'     => 'wpec-subscriber-' . (int) $row->subscriber_id,
					'data'        => self::items( $fields ),
				),
			);
		}

		/**
		 * Carts saved for reminders ( abandoned carts ).
		 *
		 * @param string $email Email address.
		 * @return array Export groups.
		 */
		private static function export_saved_carts( $email ) {
			global $wpdb;
			if ( ! class_exists( 'ec_abandoned_carts' ) || ! ec_abandoned_carts::tables_exist() ) {
				return array();
			}
			$groups = array();
			$carts  = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT abandoned_cart_id, first_name, last_name, phone, items_json, subtotal, currency, status, first_seen, last_activity FROM ec_abandoned_cart WHERE email = %s ORDER BY abandoned_cart_id ASC LIMIT %d', $email, self::CARTS_LIMIT ) );
			foreach ( $carts as $cart ) {
				$lines = array();
				foreach ( ec_abandoned_carts::items( $cart ) as $item ) {
					if ( is_array( $item ) && isset( $item['title'] ) ) {
						$lines[] = ( isset( $item['qty'] ) ? (int) $item['qty'] : 1 ) . ' × ' . (string) $item['title'];
					}
				}
				$subtotal = number_format( (float) $cart->subtotal, 2, '.', '' ) . ( '' !== (string) $cart->currency ? ' ' . $cart->currency : '' );
				$groups[] = array(
					'group_id'    => 'wp-easycart-saved-carts',
					'group_label' => __( 'Store saved carts', 'wp-easycart' ),
					'item_id'     => 'wpec-cart-' . (int) $cart->abandoned_cart_id,
					'data'        => self::items(
						array(
							array( __( 'Name', 'wp-easycart' ), trim( $cart->first_name . ' ' . $cart->last_name ) ),
							array( __( 'Phone', 'wp-easycart' ), $cart->phone ),
							array( __( 'Items', 'wp-easycart' ), implode( ', ', $lines ) ),
							array( __( 'Subtotal', 'wp-easycart' ), $subtotal ),
							array( __( 'Status', 'wp-easycart' ), $cart->status ),
							array( __( 'First seen', 'wp-easycart' ), self::date( $cart->first_seen ) ),
							array( __( 'Last activity', 'wp-easycart' ), self::date( $cart->last_activity ) ),
						)
					),
				);
			}
			return $groups;
		}

		/**
		 * Subscriptions ( recurring products ) with this email or on this account.
		 *
		 * @since 6.0.2
		 * @param string $email   Email address.
		 * @param int    $user_id Account ( 0 for none ).
		 * @return array Export groups.
		 */
		private static function export_subscriptions( $email, $user_id ) {
			global $wpdb;
			if ( ! self::table_exists( 'ec_subscription' ) ) {
				return array();
			}
			$groups = array();
			$rows   = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT subscription_id, title, subscription_status, first_name, last_name, email, price, start_date, last_payment_date, next_payment_date FROM ec_subscription WHERE email = %s OR ( %d > 0 AND user_id = %d ) ORDER BY subscription_id ASC LIMIT %d', $email, $user_id, $user_id, self::CARTS_LIMIT ) );
			foreach ( $rows as $row ) {
				$groups[] = array(
					'group_id'    => 'wp-easycart-subscriptions',
					'group_label' => __( 'Store subscriptions', 'wp-easycart' ),
					'item_id'     => 'wpec-subscription-' . (int) $row->subscription_id,
					'data'        => self::items(
						array(
							array( __( 'Subscription', 'wp-easycart' ), (string) $row->title ),
							array( __( 'Status', 'wp-easycart' ), (string) $row->subscription_status ),
							array( __( 'Name', 'wp-easycart' ), trim( $row->first_name . ' ' . $row->last_name ) ),
							array( __( 'Email', 'wp-easycart' ), (string) $row->email ),
							array( __( 'Price', 'wp-easycart' ), number_format( (float) $row->price, 2, '.', '' ) ),
							array( __( 'Started', 'wp-easycart' ), self::date( $row->start_date ) ),
							array( __( 'Last payment', 'wp-easycart' ), is_numeric( $row->last_payment_date ) ? self::date( gmdate( 'Y-m-d H:i:s', (int) $row->last_payment_date ) ) : self::date( $row->last_payment_date ) ),
							array( __( 'Next payment', 'wp-easycart' ), is_numeric( $row->next_payment_date ) ? self::date( gmdate( 'Y-m-d H:i:s', (int) $row->next_payment_date ) ) : self::date( $row->next_payment_date ) ),
						)
					),
				);
			}
			return $groups;
		}

		/**
		 * Product reviews written with this email or from this account.
		 *
		 * @since 6.0.2
		 * @param string $email   Email address.
		 * @param int    $user_id Account ( 0 for none ).
		 * @return array Export groups.
		 */
		private static function export_reviews( $email, $user_id ) {
			global $wpdb;
			if ( ! self::table_exists( 'ec_review' ) ) {
				return array();
			}
			$by_email = self::column_exists( 'ec_review', 'reviewer_email' );
			if ( ! $by_email && $user_id <= 0 ) {
				return array();
			}
			if ( $by_email ) {
				$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT ec_review.review_id, ec_review.title, ec_review.description, ec_review.rating, ec_review.date_submitted, ec_review.reviewer_name, ec_review.reviewer_email, ec_product.title AS product_title FROM ec_review LEFT JOIN ec_product ON ec_product.product_id = ec_review.product_id WHERE ec_review.reviewer_email = %s OR ( %d > 0 AND ec_review.user_id = %d ) ORDER BY ec_review.review_id ASC LIMIT %d', $email, $user_id, $user_id, self::CARTS_LIMIT ) );
			} else {
				$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT ec_review.review_id, ec_review.title, ec_review.description, ec_review.rating, ec_review.date_submitted, ec_review.reviewer_name, ec_product.title AS product_title FROM ec_review LEFT JOIN ec_product ON ec_product.product_id = ec_review.product_id WHERE ec_review.user_id = %d ORDER BY ec_review.review_id ASC LIMIT %d', $user_id, self::CARTS_LIMIT ) );
			}
			$groups = array();
			foreach ( $rows as $row ) {
				$groups[] = array(
					'group_id'    => 'wp-easycart-reviews',
					'group_label' => __( 'Store product reviews', 'wp-easycart' ),
					'item_id'     => 'wpec-review-' . (int) $row->review_id,
					'data'        => self::items(
						array(
							array( __( 'Product', 'wp-easycart' ), (string) $row->product_title ),
							array( __( 'Name', 'wp-easycart' ), (string) $row->reviewer_name ),
							array( __( 'Email', 'wp-easycart' ), isset( $row->reviewer_email ) ? (string) $row->reviewer_email : '' ),
							array( __( 'Rating', 'wp-easycart' ), (string) (int) $row->rating ),
							array( __( 'Title', 'wp-easycart' ), (string) $row->title ),
							array( __( 'Review', 'wp-easycart' ), (string) $row->description ),
							array( __( 'Written', 'wp-easycart' ), self::date( $row->date_submitted ) ),
						)
					),
				);
			}
			return $groups;
		}

		/**
		 * Back-in-stock sign-ups ( "email me when this is back" ).
		 *
		 * @since 6.0.2
		 * @param string $email Email address.
		 * @return array Export groups.
		 */
		private static function export_stock_notices( $email ) {
			global $wpdb;
			if ( ! self::table_exists( 'ec_product_subscriber' ) ) {
				return array();
			}
			$groups = array();
			$rows   = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT ec_product_subscriber.product_subscriber_id, ec_product_subscriber.status, ec_product_subscriber.last_notified, ec_product.title AS product_title FROM ec_product_subscriber LEFT JOIN ec_product ON ec_product.product_id = ec_product_subscriber.product_id WHERE ec_product_subscriber.email = %s ORDER BY ec_product_subscriber.product_subscriber_id ASC LIMIT %d', $email, self::CARTS_LIMIT ) );
			foreach ( $rows as $row ) {
				$groups[] = array(
					'group_id'    => 'wp-easycart-stock-notices',
					'group_label' => __( 'Store back-in-stock sign-ups', 'wp-easycart' ),
					'item_id'     => 'wpec-stock-notice-' . (int) $row->product_subscriber_id,
					'data'        => self::items(
						array(
							array( __( 'Email', 'wp-easycart' ), $email ),
							array( __( 'Product', 'wp-easycart' ), (string) $row->product_title ),
							array( __( 'Status', 'wp-easycart' ), (string) $row->status ),
							array( __( 'Last emailed', 'wp-easycart' ), self::date( $row->last_notified ) ),
						)
					),
				);
			}
			return $groups;
		}

		/**
		 * The store's record of the emails it sent to this address ( email log ).
		 *
		 * @since 6.0.2
		 * @param string $email Email address.
		 * @return array Export groups.
		 */
		private static function export_email_log( $email ) {
			global $wpdb;
			if ( ! self::table_exists( 'ec_email_log' ) ) {
				return array();
			}
			$groups = array();
			$rows   = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT log_id, created_at, email_type, order_id, to_email, subject, status FROM ec_email_log WHERE to_email = %s ORDER BY log_id DESC LIMIT %d', $email, self::CARTS_LIMIT ) );
			foreach ( $rows as $row ) {
				$groups[] = array(
					'group_id'    => 'wp-easycart-emails',
					'group_label' => __( 'Store emails sent', 'wp-easycart' ),
					'item_id'     => 'wpec-email-' . (int) $row->log_id,
					'data'        => self::items(
						array(
							array( __( 'Sent to', 'wp-easycart' ), (string) $row->to_email ),
							array( __( 'Subject', 'wp-easycart' ), (string) $row->subject ),
							array( __( 'Order number', 'wp-easycart' ), (int) $row->order_id > 0 ? (string) (int) $row->order_id : '' ),
							array( __( 'Date', 'wp-easycart' ), self::date( $row->created_at ) ),
							array( __( 'Status', 'wp-easycart' ), (string) $row->status ),
						)
					),
				);
			}
			return $groups;
		}

		/**
		 * One page of orders placed with this email or by this account.
		 *
		 * @param string $email   Email address.
		 * @param int    $user_id Account ( 0 for none ).
		 * @param int    $page    Page.
		 * @return array { data, done }
		 */
		private static function export_orders( $email, $user_id, $page ) {
			global $wpdb;
			$per    = self::ORDERS_PER_PAGE;
			/* 6.0.2: the whole order row, so the export also carries the addresses, phone numbers, network address, notes and
			   gift details the order holds. */
			$orders = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT ec_order.*, ec_orderstatus.order_status AS wpec_privacy_status FROM ec_order LEFT JOIN ec_orderstatus ON ec_orderstatus.status_id = ec_order.orderstatus_id WHERE ec_order.user_email = %s OR ( %d > 0 AND ec_order.user_id = %d ) ORDER BY ec_order.order_id ASC LIMIT %d OFFSET %d', $email, $user_id, $user_id, $per, ( $page - 1 ) * $per ) );
			$groups = array();
			if ( $orders ) {
				$ids     = implode( ',', array_map( 'intval', wp_list_pluck( $orders, 'order_id' ) ) );
				$details = (array) $wpdb->get_results( "SELECT order_id, title, model_number, quantity FROM ec_orderdetail WHERE order_id IN ( $ids ) ORDER BY orderdetail_id ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $ids is an intval() list.
				$lines   = array();
				foreach ( $details as $detail ) {
					$sku = ( '' !== (string) $detail->model_number ) ? ' ( ' . $detail->model_number . ' )' : '';

					$lines[ (int) $detail->order_id ][] = (int) $detail->quantity . ' × ' . wp_unslash( (string) $detail->title ) . $sku;
				}
				foreach ( $orders as $order ) {
					$order_id = (int) $order->order_id;
					$groups[] = array(
						'group_id'    => 'wp-easycart-orders',
						'group_label' => __( 'Store orders', 'wp-easycart' ),
						'item_id'     => 'wpec-order-' . $order_id,
						'data'        => self::items(
							array(
								array( __( 'Order number', 'wp-easycart' ), (string) $order_id ),
								array( __( 'Order date', 'wp-easycart' ), self::date( $order->order_date ) ),
								array( __( 'Status', 'wp-easycart' ), (string) $order->wpec_privacy_status ),
								array( __( 'Total', 'wp-easycart' ), number_format( (float) $order->grand_total, 2, '.', '' ) ),
								array( __( 'Items', 'wp-easycart' ), isset( $lines[ $order_id ] ) ? implode( ', ', $lines[ $order_id ] ) : '' ),
								array( __( 'Email', 'wp-easycart' ), self::field( $order, 'user_email' ) ),
								array( __( 'Other email', 'wp-easycart' ), self::field( $order, 'email_other' ) ),
								array( __( 'Billing address', 'wp-easycart' ), self::order_address( $order, 'billing' ) ),
								array( __( 'Billing phone', 'wp-easycart' ), self::field( $order, 'billing_phone' ) ),
								array( __( 'Shipping address', 'wp-easycart' ), self::order_address( $order, 'shipping' ) ),
								array( __( 'Shipping phone', 'wp-easycart' ), self::field( $order, 'shipping_phone' ) ),
								array( __( 'VAT registration number', 'wp-easycart' ), self::field( $order, 'vat_registration_number' ) ),
								array( __( 'Network address', 'wp-easycart' ), self::field( $order, 'order_ip_address' ) ),
								array( __( 'Order notes', 'wp-easycart' ), self::field( $order, 'order_customer_notes' ) ),
								array( __( 'PO number', 'wp-easycart' ), self::field( $order, 'po_number' ) ),
								array( __( 'Gift recipient email', 'wp-easycart' ), self::field( $order, 'gift_recipient_email' ) ),
								array( __( 'Gift message', 'wp-easycart' ), self::field( $order, 'gift_message' ) ),
							)
						),
					);
				}
			}
			return array(
				'data' => $groups,
				'done' => count( $orders ) < $per,
			);
		}

		// Erase.

		/**
		 * End the newsletter subscription, delete saved carts and back-in-stock sign-ups, stop review requests; keep the
		 * account, orders and reviews ( reported as retained ).
		 *
		 * @since 6.0.2
		 * @param string $email Email address.
		 * @param int    $page  Page ( one pass ).
		 * @return array { items_removed, items_retained, messages, done }
		 */
		public static function erase_personal_data( $email, $page = 1 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress eraser callback signature.
			global $wpdb;
			$email    = trim( (string) $email );
			$removed  = false;
			$retained = false;
			$messages = array();
			if ( is_email( $email ) ) {
				if ( class_exists( 'wp_easycart_subscribers' ) && wp_easycart_subscribers::remove( $email, 'privacy' ) ) {
					$removed = true;
				}
				if ( class_exists( 'ec_abandoned_carts' ) && ec_abandoned_carts::tables_exist() ) {
					$wpdb->query( $wpdb->prepare( 'DELETE ec_abandoned_cart_event FROM ec_abandoned_cart_event INNER JOIN ec_abandoned_cart ON ec_abandoned_cart.abandoned_cart_id = ec_abandoned_cart_event.abandoned_cart_id WHERE ec_abandoned_cart.email = %s', $email ) );
					if ( (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM ec_abandoned_cart WHERE email = %s', $email ) ) > 0 ) {
						$removed = true;
					}
				}
				/* 6.0.2: no more review requests. The rows stay, with unsubscribed set and the name blanked, because that flag is
				   what keeps later orders from this address from queueing new requests. */
				if ( self::table_exists( 'ec_review_request' ) && self::column_exists( 'ec_review_request', 'unsubscribed' ) ) {
					if ( (int) $wpdb->query( $wpdb->prepare( "UPDATE ec_review_request SET unsubscribed = 1, first_name = '' WHERE email = %s AND ( unsubscribed = 0 OR first_name != '' )", $email ) ) > 0 ) {
						$removed = true;
					}
				}
				/* 6.0.2: back-in-stock sign-ups are deleted, so the address is never emailed when a product returns. */
				if ( self::table_exists( 'ec_product_subscriber' ) ) {
					if ( (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM ec_product_subscriber WHERE email = %s', $email ) ) > 0 ) {
						$removed = true;
					}
				}
				$has_account = (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT user_id FROM ec_user WHERE email = %s LIMIT 1', $email ) );
				$has_orders  = (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT order_id FROM ec_order WHERE user_email = %s LIMIT 1', $email ) );
				if ( $has_account || $has_orders ) {
					$retained   = true;
					$messages[] = __( 'WP EasyCart kept the customer account and orders for this address: order records are needed for tax and accounting. Staff can remove or anonymize the account from the customer screen.', 'wp-easycart' );
				}
				if ( self::table_exists( 'ec_review' ) && self::column_exists( 'ec_review', 'reviewer_email' ) && $wpdb->get_var( $wpdb->prepare( 'SELECT review_id FROM ec_review WHERE reviewer_email = %s LIMIT 1', $email ) ) ) {
					$retained   = true;
					$messages[] = __( 'WP EasyCart kept the product reviews written with this address. Staff can edit or delete them under Products › Reviews.', 'wp-easycart' );
				}

				/**
				 * The WP EasyCart eraser has run for an address ( Tools › Erase Personal Data ). Extensions that keep their own
				 * copy of the customer ( an email marketing service, for example ) remove it here.
				 *
				 * @since 6.0.2
				 * @param string $email Email address.
				 */
				do_action( 'wp_easycart_privacy_erased', $email );
			}
			return array(
				'items_removed'  => $removed,
				'items_retained' => $retained,
				'messages'       => $messages,
				'done'           => true,
			);
		}

		// Helpers.

		/**
		 * One column of a row, '' when the row does not have it ( a column added by a later database update ).
		 *
		 * @since 6.0.2
		 * @param object $row Row.
		 * @param string $key Column.
		 * @return string
		 */
		private static function field( $row, $key ) {
			return ( is_object( $row ) && isset( $row->{$key} ) && is_scalar( $row->{$key} ) ) ? (string) $row->{$key} : '';
		}

		/**
		 * An order's billing or shipping address on one line.
		 *
		 * @since 6.0.2
		 * @param object $order ec_order row.
		 * @param string $side  billing | shipping.
		 * @return string
		 */
		private static function order_address( $order, $side ) {
			$parts = array(
				trim( self::field( $order, $side . '_first_name' ) . ' ' . self::field( $order, $side . '_last_name' ) ),
				self::field( $order, $side . '_company_name' ),
				self::field( $order, $side . '_address_line_1' ),
				self::field( $order, $side . '_address_line_2' ),
				self::field( $order, $side . '_city' ),
				self::field( $order, $side . '_state' ),
				self::field( $order, $side . '_zip' ),
				self::field( $order, $side . '_country' ),
			);
			return implode( ', ', array_filter( array_map( 'trim', $parts ), 'strlen' ) );
		}

		/**
		 * Is a table there ( read once per request )?
		 *
		 * @since 6.0.2
		 * @param string $table Table.
		 * @return bool
		 */
		private static function table_exists( $table ) {
			global $wpdb;
			static $found = array();
			if ( ! isset( $found[ $table ] ) ) {
				$found[ $table ] = ( $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) );
			}
			return $found[ $table ];
		}

		/**
		 * Does a table have a column ( read once per request )?
		 *
		 * @since 6.0.2
		 * @param string $table  Table ( one of this class's own, never user input ).
		 * @param string $column Column.
		 * @return bool
		 */
		private static function column_exists( $table, $column ) {
			global $wpdb;
			static $found = array();
			$key = $table . '.' . $column;
			if ( ! isset( $found[ $key ] ) ) {
				$found[ $key ] = self::table_exists( $table ) && (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM `' . esc_sql( $table ) . '` LIKE %s', $column ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- the table is one of this class's literal names, escaped.
			}
			return $found[ $key ];
		}

		/**
		 * Label and value pairs as export items, leaving out empty values.
		 *
		 * @param array $pairs Each array( label, value ).
		 * @return array
		 */
		private static function items( $pairs ) {
			$items = array();
			foreach ( $pairs as $pair ) {
				$value = trim( wp_unslash( (string) $pair[1] ) );
				if ( '' !== $value ) {
					$items[] = array(
						'name'  => (string) $pair[0],
						'value' => $value,
					);
				}
			}
			return $items;
		}

		/**
		 * A stored date as the export shows it.
		 *
		 * @param string|null $value Database date.
		 * @return string '' when empty.
		 */
		private static function date( $value ) {
			$value = (string) $value;
			if ( '' === $value || 0 === strpos( $value, '0000-00-00' ) ) {
				return '';
			}
			$ts = strtotime( $value );
			return $ts ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts ) : '';
		}
	}

	wp_easycart_privacy::init();

endif;
