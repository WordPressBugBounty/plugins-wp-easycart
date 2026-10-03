<?php
/**
 * EasyCart's account page, as the Elementor account widgets draw it ( 6.0.2 ).
 *
 * Extends ec_accountpage, so every view is drawn by the store's own display methods and templates ( and a theme's copies
 * in wp-easycart-data ): pay links, document links and download rules, checkout-field answers, order payments,
 * subscriptions and every account hook come along. The constructor keeps ec_accountpage's ownership rules: the order from
 * ?order_id belongs to the signed-in customer ( or matches its guest key ), the subscription from ?subscription_id to the
 * signed-in customer.
 *
 * Adds the views the classic account page has no page for ( downloads, payment methods ), the customer's latest order for
 * the Order Details widget, and the editor's sample data.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Account_Page' ) && class_exists( 'ec_accountpage' ) ) :

	/**
	 * The account page the widgets draw with.
	 */
	class WP_EasyCart_Elementor_Account_Page extends ec_accountpage {

		/**
		 * Editor samples in use ( null on the storefront ).
		 *
		 * @var WP_EasyCart_Elementor_Account_Samples|null
		 */
		public $wpec_samples = null;

		/**
		 * Everything is sample data ( the editing admin is not signed in to the store ).
		 *
		 * @var bool
		 */
		public $wpec_sample_customer = false;

		/**
		 * Puts the editor's sample data in: everything ( $full ), or only what the signed-in admin's account lacks.
		 *
		 * @param WP_EasyCart_Elementor_Account_Samples $samples Samples.
		 * @param bool                                  $full    Sample customer.
		 */
		public function wpec_use_samples( $samples, $full ) {
			if ( ! class_exists( 'WP_EasyCart_Elementor_Account_Sample_DB' ) ) {
				return;
			}
			$this->wpec_samples         = $samples;
			$this->wpec_sample_customer = (bool) $full;
			$this->mysqli               = new WP_EasyCart_Elementor_Account_Sample_DB( $samples );
			$user                       = $full ? $samples->user( isset( $GLOBALS['ec_user'] ) ? $GLOBALS['ec_user'] : null ) : $GLOBALS['ec_user'];
			if ( $full || ! is_object( $this->orders ) || empty( $this->orders->num_orders ) ) {
				$this->orders = $samples->order_list();
			}
			if ( $full || ! is_object( $this->subscriptions ) || empty( $this->subscriptions->subscription_list ) ) {
				$this->subscriptions = $samples->subscription_list( $user );
			}
			if ( $full || empty( $this->downloads ) ) {
				$this->downloads = $samples->download_rows();
			}
			if ( $full ) {
				$this->order        = null;
				$this->subscription = null;
			}
		}

		/**
		 * No Stripe setup intent for the sample customer.
		 *
		 * @return string
		 */
		public function get_stripe_intent_client_secret() {
			if ( $this->wpec_sample_customer ) {
				return '';
			}
			return parent::get_stripe_intent_client_secret();
		}

		/**
		 * The order the Order Details view shows: the one the link asked for ( ownership checked by ec_accountpage ), else
		 * with $latest the customer's newest order, else in the editor a sample order.
		 *
		 * @param bool $latest Fall back to the newest order.
		 * @return ec_orderdisplay|null
		 */
		public function wpec_details_order( $latest ) {
			if ( $this->order && ! $this->wpec_sample_customer ) {
				return $this->order;
			}
			$user_id = isset( $GLOBALS['ec_cart_data']->cart_data->user_id ) ? (int) $GLOBALS['ec_cart_data']->cart_data->user_id : 0;
			if ( $latest && $user_id > 0 && ! $this->wpec_sample_customer && is_object( $this->orders ) && ! empty( $this->orders->orders ) ) {
				$newest = $this->orders->orders[0];
				if ( isset( $newest->order_id ) && ( ! $this->wpec_samples || ! $this->wpec_samples->is_sample_order( $newest->order_id ) ) ) {
					$row = $this->mysqli->get_order_row( (int) $newest->order_id, $user_id );
					if ( $row ) {
						return new ec_orderdisplay( $row, true );
					}
				}
			}
			if ( $this->wpec_samples && ( $latest || $this->wpec_sample_customer ) ) {
				return $this->wpec_samples->order_details();
			}
			return null;
		}

		/**
		 * Draws an order's details page, or the "no order found" message.
		 *
		 * @param ec_orderdisplay|null $order Order ( from wpec_details_order() ).
		 */
		public function wpec_display_order_details( $order ) {
			if ( $order ) {
				$saved       = $this->order;
				$this->order = $order;
				$this->display_order_details_page();
				$this->order = $saved;
				return;
			}
			echo '<section class="ec_account_page" id="ec_account_order_details">';
			echo '<div class="ec_account_no_order_found">' . esc_html( wp_easycart_language()->get_text( 'account_order_details', 'no_order_found' ) ) . '</div>';
			echo '<div class="ec_account_return_to_dashboard_button"><a href="' . esc_url( wpeasycart_links()->get_account_page( 'orders' ) ) . '">' . esc_html( wp_easycart_language()->get_text( 'account_order_details', 'return_to_dashboard' ) ) . '</a></div>';
			echo '</section>';
		}

		/**
		 * The subscription the details view shows ( ownership checked by ec_accountpage ), or in the editor a sample one.
		 *
		 * @return ec_subscription|false|null
		 */
		public function wpec_details_subscription() {
			if ( $this->subscription && ! $this->wpec_sample_customer ) {
				return $this->subscription;
			}
			if ( $this->wpec_samples && ( $this->wpec_sample_customer || ! $this->subscription ) && WP_EasyCart_Elementor_Account::is_editor() ) {
				return $this->wpec_samples->subscription( true );
			}
			return $this->subscription;
		}

		/**
		 * Draws a subscription's details page.
		 *
		 * @param ec_subscription|false|null $subscription Subscription.
		 */
		public function wpec_display_subscription_details( $subscription ) {
			$saved              = $this->subscription;
			$this->subscription = $subscription;
			$this->display_subscription_details_page();
			$this->subscription = $saved;
		}

		/**
		 * The signed-in customer's downloads: lines with a download on approved orders, minus lines whose chosen options
		 * turn the download off ( as the order details page does ).
		 *
		 * @return array Each array( 'item' => ec_orderdetail, 'extra' => additional file keys, 'order_id' => int ).
		 */
		public function wpec_downloads() {
			if ( $this->wpec_sample_customer && $this->wpec_samples ) {
				return $this->wpec_samples->download_items();
			}
			$user_id = isset( $GLOBALS['ec_cart_data']->cart_data->user_id ) ? (int) $GLOBALS['ec_cart_data']->cart_data->user_id : 0;
			if ( $user_id <= 0 ) {
				return array();
			}
			global $wpdb;
			$order_ids = $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT ec_orderdetail.order_id FROM ec_orderdetail, ec_order, ec_orderstatus WHERE ec_order.order_id = ec_orderdetail.order_id AND ec_orderstatus.status_id = ec_order.orderstatus_id AND ec_orderstatus.is_approved = 1 AND ec_order.user_id = %d AND ec_orderdetail.is_download = 1 ORDER BY ec_orderdetail.order_id DESC LIMIT 100', $user_id ) );
			$items     = array();
			foreach ( (array) $order_ids as $order_id ) {
				$rows = $this->mysqli->get_order_details( (int) $order_id, $user_id );
				foreach ( (array) $rows as $row ) {
					if ( empty( $row->is_download ) ) {
						continue;
					}
					$item    = new ec_orderdetail( $row );
					$allowed = true;
					$extra   = array();
					if ( $item->use_advanced_optionset ) {
						foreach ( (array) $this->mysqli->get_order_options( $item->orderdetail_id ) as $option ) {
							if ( isset( $option->optionitem_allow_download ) && ! $option->optionitem_allow_download ) {
								$allowed = false;
							}
							if ( ! empty( $option->download_addition_file ) ) {
								$extra[] = $option->download_addition_file;
							}
						}
					}
					if ( $allowed ) {
						$items[] = array(
							'item'     => $item,
							'extra'    => $extra,
							'order_id' => (int) $order_id,
						);
					}
				}
			}
			if ( empty( $items ) && $this->wpec_samples && WP_EasyCart_Elementor_Account::is_editor() ) {
				return $this->wpec_samples->download_items();
			}
			return $items;
		}

		/**
		 * The card on file and the subscriptions whose payment method the customer can change.
		 *
		 * @return array array( 'brand' => string, 'last4' => string, 'subscriptions' => ec_subscription[] )
		 */
		public function wpec_payment_methods() {
			$user = isset( $GLOBALS['ec_user'] ) ? $GLOBALS['ec_user'] : null;
			$out  = array(
				'brand'         => ( $user && isset( $user->card_type ) ) ? (string) $user->card_type : '',
				'last4'         => ( $user && isset( $user->last4 ) ) ? (string) $user->last4 : '',
				'subscriptions' => array(),
			);
			$list = ( is_object( $this->subscriptions ) && isset( $this->subscriptions->subscription_list ) ) ? (array) $this->subscriptions->subscription_list : array();
			foreach ( $list as $subscription ) {
				if ( is_object( $subscription ) && ! $subscription->is_canceled() && ( $this->wpec_sample_customer || $subscription->can_update_payment_method() ) ) {
					$out['subscriptions'][] = $subscription;
				}
			}
			return $out;
		}
	}

endif;
