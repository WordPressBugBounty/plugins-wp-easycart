<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_orders' ) ) :

	final class wp_easycart_admin_orders {

		protected static $_instance = null;

		public $order_details;

		public $orders_list_file;
		public $export_orders_csv;
		public $export_orders_stamps_csv;

		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		public function __construct() { 
			$this->orders_list_file = EC_PLUGIN_DIRECTORY . '/admin/template/orders/orders/order-list.php';
			$this->export_orders_csv = apply_filters( 'wpeasycart_admin_order_export_file', EC_PLUGIN_DIRECTORY . '/admin/template/exporters/export-orders-csv.php' );
			$this->export_orders_stamps_csv = apply_filters( 'wpeasycart_admin_order_stamps_export_file', EC_PLUGIN_DIRECTORY . '/admin/template/exporters/export-orders-stamps-csv.php' );
			add_filter( 'wp_easycart_admin_success_messages', array( $this, 'add_success_messages' ) );
			add_filter( 'wp_easycart_admin_error_messages', array( $this, 'add_failure_messages' ) );
			add_action( 'wp_easycart_process_get_form_action', array( $this, 'process_delete_order' ) );
			add_action( 'wp_easycart_process_get_form_action', array( $this, 'process_bulk_delete_order' ) );
			add_action( 'wp_easycart_process_get_form_action', array( $this, 'process_bulk_mark_viewed_order' ) );
			add_action( 'wp_easycart_process_get_form_action', array( $this, 'process_bulk_mark_not_viewed_order' ) );	
			add_action( 'wp_easycart_process_get_form_action', array( $this, 'process_mark_all_viewed_order' ) );	
			add_action( 'wp_easycart_process_get_form_action', array( $this, 'process_mark_all_not_viewed_order' ) );
			add_action( 'wp_easycart_process_get_form_action', array( $this, 'process_export_orders' ) );
			add_action( 'wp_easycart_process_get_form_action', array( $this, 'process_export_orders_stamps' ) );
			add_action( 'wp_easycart_process_get_form_action', array( $this, 'process_resend_email_receipt' ) );
			add_action( 'wp_easycart_process_get_form_action', array( $this, 'process_resend_email_invoice' ) );
			add_action( 'wp_easycart_process_get_form_action', array( $this, 'process_print_receipts' ) );
			add_action( 'wp_easycart_process_get_form_action', array( $this, 'process_print_packing_slips' ) );
			add_action( 'wp_easycart_process_get_form_action', array( $this, 'process_send_order_shipped_email' ) );
			add_action( 'wp_easycart_process_get_form_action', array( $this, 'process_update_order_status' ) );
			add_action( 'wp_easycart_admin_order_details_left_content_end', array( $this, 'print_order_history' ) );
		}
		
		public function print_order_history( ){
			include( EC_PLUGIN_DIRECTORY . '/admin/template/orders/orders/order-history.php' );
		}

		public function process_delete_order() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_orders' ) ) {
				return;
			}

			if ( isset( $_GET['ec_admin_form_action'] ) && 'delete-order' == $_GET['ec_admin_form_action'] && isset( $_GET['order_id'] ) && ! isset( $_GET['bulk'] ) ) {
				if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-action-delete-order' ) ) {
					$result = $this->delete_order();
					wp_easycart_admin()->redirect( 'wp-easycart-orders', 'orders', $result );
				}
			}
		}

		public function process_bulk_delete_order() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_orders' ) ) {
				return;
			}

			if ( isset( $_GET['ec_admin_form_action'] ) && 'delete-order' == $_GET['ec_admin_form_action'] && ! isset( $_GET['order_id'] ) && isset( $_GET['bulk'] ) ) {
				if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-bulk-' . ( ( isset( $_GET['subpage'] ) ) ? 'orders' : '' ) ) ) {
					$result = $this->bulk_delete_order();
					wp_easycart_admin()->redirect( 'wp-easycart-orders', 'orders', $result );
				}
			}
		}

		public function process_bulk_mark_viewed_order() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_orders' ) ) {
				return;
			}

			if ( isset( $_GET['ec_admin_form_action'] ) && 'mark-orders-viewed' == $_GET['ec_admin_form_action'] && isset( $_GET['bulk'] ) ) {
				if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-bulk-' . ( ( isset( $_GET['subpage'] ) ) ? 'orders' : '' ) ) ) {
					$result = $this->bulk_mark_order_viewed();
					wp_easycart_admin()->redirect( 'wp-easycart-orders', 'orders', $result );
				}
			}
		}

		public function process_bulk_mark_not_viewed_order() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_orders' ) ) {
				return;
			}

			if ( isset( $_GET['ec_admin_form_action'] ) && 'mark-orders-not-viewed' == $_GET['ec_admin_form_action'] && isset( $_GET['bulk'] ) ) {
				if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-bulk-' . ( ( isset( $_GET['subpage'] ) ) ? 'orders' : '' ) ) ) {
					$result = $this->bulk_mark_order_not_viewed();
					wp_easycart_admin()->redirect( 'wp-easycart-orders', 'orders', $result );
				}
			}
		}

		public function process_mark_all_viewed_order() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_orders' ) ) {
				return;
			}

			if ( isset( $_GET['ec_admin_form_action'] ) && 'mark-all-orders-viewed' == $_GET['ec_admin_form_action'] ) {
				if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-bulk-' . ( ( isset( $_GET['subpage'] ) ) ? 'orders' : '' ) ) ) {
					$result = $this->mark_all_order_viewed();
					wp_easycart_admin()->redirect( 'wp-easycart-orders', 'orders', $result );
				}
			}
		}

		public function process_mark_all_not_viewed_order() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_orders' ) ) {
				return;
			}

			if ( isset( $_GET['ec_admin_form_action'] ) && 'mark-all-orders-not-viewed' == $_GET['ec_admin_form_action'] ) {
				if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-bulk-' . ( ( isset( $_GET['subpage'] ) ) ? 'orders' : '' ) ) ) {
					$result = $this->mark_all_order_not_viewed();
					wp_easycart_admin()->redirect( 'wp-easycart-orders', 'orders', $result );
				}
			}
		}

		public function process_export_orders() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_orders' ) ) {
				return;
			}

			if ( isset( $_GET['ec_admin_form_action'] ) && ( 'export-orders-csv' == $_GET['ec_admin_form_action'] || 'export-orders-csv-all' == $_GET['ec_admin_form_action'] ) ) {
				if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-bulk-' . ( ( isset( $_GET['subpage'] ) ) ? 'orders' : '' ) ) ) {
					include( $this->export_orders_csv );
					die();
				}
			}
		}

		public function process_export_orders_stamps() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_orders' ) ) {
				return;
			}

			if ( isset( $_GET['ec_admin_form_action'] ) && ( 'export-orders-stamps-csv' == $_GET['ec_admin_form_action'] || 'export-orders-stamps-csv-all' == $_GET['ec_admin_form_action'] ) ) {
				if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-bulk-' . ( ( isset( $_GET['subpage'] ) ) ? 'orders' : '' ) ) ) {
					include( $this->export_orders_stamps_csv );
					die();
				}
			}
		}

		public function process_resend_email_receipt() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_orders' ) ) {
				return;
			}

			if ( isset( $_GET['ec_admin_form_action'] ) && 'resend-email' == $_GET['ec_admin_form_action'] ) {
				if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-bulk-' . ( ( isset( $_GET['subpage'] ) ) ? 'orders' : '' ) ) ) {
					$result = $this->resend_receipts();
					wp_easycart_admin()->redirect( 'wp-easycart-orders', 'orders', $result );
				}
			}
		}

		public function process_resend_email_invoice() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_orders' ) ) {
				return;
			}

			if ( isset( $_GET['ec_admin_form_action'] ) && 'resend-invoice' == $_GET['ec_admin_form_action'] ) {
				if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-bulk-' . ( ( isset( $_GET['subpage'] ) ) ? 'orders' : '' ) ) ) {
					$result = $this->resend_invoices();
					wp_easycart_admin()->redirect( 'wp-easycart-orders', 'orders', $result );
				}
			}
		}

		public function process_print_receipts() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_orders' ) ) {
				return;
			}

			if ( isset( $_GET['ec_admin_form_action'] ) && 'print-receipt' == $_GET['ec_admin_form_action'] ) {
				if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-bulk-' . ( ( isset( $_GET['subpage'] ) ) ? 'orders' : '' ) ) ) {
					$this->print_receipts();
					die();
				}
			}
		}

		public function process_print_packing_slips() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_orders' ) ) {
				return;
			}

			if ( isset( $_GET['ec_admin_form_action'] ) && 'print-packing-slip' == $_GET['ec_admin_form_action'] ) {
				if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-bulk-' . ( ( isset( $_GET['subpage'] ) ) ? 'orders' : '' ) ) ) {
					$this->print_packing_slips();
					die();
				}
			}
		}

		public function process_send_order_shipped_email() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_orders' ) ) {
				return;
			}

			if ( isset( $_GET['ec_admin_form_action'] ) && 'send-shipped-email' == $_GET['ec_admin_form_action'] ) {
				if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-bulk-' . ( ( isset( $_GET['subpage'] ) ) ? 'orders' : '' ) ) ) {
					$result = $this->send_order_shipped_emails();
					wp_easycart_admin()->redirect( 'wp-easycart-orders', 'orders', $result );
				}
			}
		}

		public function process_update_order_status() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_orders' ) ) {
				return;
			}

			if ( isset( $_GET['ec_admin_form_action'] ) && 'change-order-status' == $_GET['ec_admin_form_action'] ) {
				if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-bulk-' . ( ( isset( $_GET['subpage'] ) ) ? 'orders' : '' ) ) ) {
					$result = $this->bulk_update_order_status();
					wp_easycart_admin()->redirect( 'wp-easycart-orders', 'orders', $result );
				}
			}
		}

		public function add_success_messages( $messages ) {
			if ( isset( $_GET['success'] ) && 'order-updated' == $_GET['success'] ) {
				$messages[] = __( 'Order(s) successfully updated', 'wp-easycart' );
			} else if ( isset( $_GET['success'] ) && 'order-deleted' == $_GET['success'] ) {
				$messages[] = __( 'Order(s) successfully deleted', 'wp-easycart' );
			} else if ( isset( $_GET['success'] ) && 'order-viewed' == $_GET['success'] ) {
				$messages[] = __( 'Order(s) marked as viewed', 'wp-easycart' );
			} else if ( isset( $_GET['success'] ) && 'order-not-viewed' == $_GET['success'] ) {
				$messages[] = __( 'Order(s) marked as NOT viewed', 'wp-easycart' );
			} else if ( isset( $_GET['success'] ) && 'receipt-sent' == $_GET['success'] ) {
				$messages[] = __( 'Order(s) receipt was resent to the customer(s)', 'wp-easycart' );
			} else if ( isset( $_GET['success'] ) && 'shipping-email-sent' == $_GET['success'] ) {
				$messages[] = __( 'Shipping email(s) were sent to the customer(s)', 'wp-easycart' );
			}
			return $messages;
		}

		public function add_failure_messages( $messages ) {
			if ( isset( $_GET['error'] ) && 'order-updated-error' == $_GET['error'] ) {
				$messages[] = __( 'Order failed to update', 'wp-easycart' );
			} else if ( isset( $_GET['error'] ) && 'order-deleted-error' == $_GET['error'] ) {
				$messages[] = __( 'Order failed to delete', 'wp-easycart' );
			} else if ( isset( $_GET['error'] ) && 'order-viewed-error' == $_GET['error'] ) {
				$messages[] = __( 'Order failed to mark as viewed', 'wp-easycart' );
			} else if ( isset( $_GET['error'] ) && 'order-duplicate' == $_GET['error'] ) {
				$messages[] = __( 'Order failed to create due to duplicate', 'wp-easycart' );
			}
			return $messages;
		}

		public function load_orders_list() {
			if ( isset( $_GET['ec_admin_form_action'] ) && ( ( isset( $_GET['order_id'] ) && 'edit' == $_GET['ec_admin_form_action'] ) || 'add-new' == $_GET['ec_admin_form_action'] ) ) {
				include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_details_orders.php' );
				$this->order_details = new wp_easycart_admin_details_orders();
				$this->order_details->output( sanitize_key( $_GET['ec_admin_form_action'] ) );
			} else {
				include( $this->orders_list_file );
			}
		}

		public function update_notes() {
			global $wpdb;

			$order_id = (int) $_POST['order_id'];
			$order_customer_notes = sanitize_textarea_field( wp_unslash( $_POST['order_customer_notes'] ) );
			
			do_action( 'wpeasycart_admin_order_customer_notes_update', $order_id, $order_customer_notes );

			$wpdb->query( $wpdb->prepare( 'UPDATE ec_order SET order_customer_notes = %s, last_updated = NOW() WHERE order_id = %d', $order_customer_notes, $order_id ) );
			$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log( order_id, order_log_key ) VALUES( %d, "order-customer-notes-update" )', $order_id ) );
			$order_log_id = $wpdb->insert_id;
			$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "order_customer_notes", %s )', $order_log_id, $order_id, $order_customer_notes ) );

			do_action( 'wpeasycart_order_updated', $order_id );
		}

		public function update_orderstatus() {
			global $wpdb;

			$order_id = (int) $_POST['order_id'];
			$orderstatus_id = (int) $_POST['orderstatus_id'];

			/* Check for Applicable Stock Adjustments */
			$order = $wpdb->get_row( $wpdb->prepare( 'SELECT ec_order.*, ec_orderstatus.is_approved FROM ec_order LEFT JOIN ec_orderstatus ON ec_orderstatus.status_id = ec_order.orderstatus_id WHERE ec_order.order_id = %d', $order_id ) );
			$orderstatus = $wpdb->get_row( $wpdb->prepare( 'SELECT ec_orderstatus.* FROM ec_orderstatus WHERE ec_orderstatus.status_id = %d', $orderstatus_id ) );
			$orderdetails = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ec_orderdetail WHERE ec_orderdetail.order_id = %d', $order_id ) );

			if ( ! $order->is_approved && $orderstatus->is_approved ) { // Take out of stock
				$ec_db = new ec_db();
				foreach ( $orderdetails as $orderdetail ) {
					if ( ! $orderdetail->stock_adjusted ) {
						$product = $wpdb->get_row( $wpdb->prepare( 'SELECT ec_product.* FROM ec_product WHERE ec_product.product_id = %d', $orderdetail->product_id ) );
						if ( $product ) {
							$stock_log_oiq_id = 0;
							$stock_log_old_quantity = (int) $product->stock_quantity;
							if ( $product->use_optionitem_quantity_tracking ) {
								$stock_log_oiq_row = $wpdb->get_row( $wpdb->prepare( 'SELECT optionitemquantity_id, quantity FROM ec_optionitemquantity WHERE product_id = %d AND optionitem_id_1 = %d AND optionitem_id_2 = %d AND optionitem_id_3 = %d AND optionitem_id_4 = %d AND optionitem_id_5 = %d', $orderdetail->product_id, $orderdetail->optionitem_id_1, $orderdetail->optionitem_id_2, $orderdetail->optionitem_id_3, $orderdetail->optionitem_id_4, $orderdetail->optionitem_id_5 ) );
								if ( $stock_log_oiq_row ) {
									$stock_log_oiq_id = (int) $stock_log_oiq_row->optionitemquantity_id;
									$stock_log_old_quantity = (int) $stock_log_oiq_row->quantity;
								}
							}

							if ( $product->use_optionitem_quantity_tracking ) {
								$ec_db->update_quantity_value( $orderdetail->quantity, $orderdetail->product_id, $orderdetail->optionitem_id_1, $orderdetail->optionitem_id_2, $orderdetail->optionitem_id_3, $orderdetail->optionitem_id_4, $orderdetail->optionitem_id_5 );
							}
							$ec_db->update_product_stock( $orderdetail->product_id, $orderdetail->quantity );
							$ec_db->update_details_stock_adjusted( $orderdetail->orderdetail_id );
							$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log( order_id, order_log_key ) VALUES( %d, "order-stock-update" )', $order_id ) );
							$order_log_id = $wpdb->insert_id;
							$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "product_id", %s )', $order_log_id, $order_id, $orderdetail->product_id ) );
							$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "quantity", %s )', $order_log_id, $order_id, '-' . $orderdetail->quantity ) );

							do_action( 'wpeasycart_inventory_stock_changed', array(
								'product_id'            => (int) $orderdetail->product_id,
								'optionitemquantity_id' => $stock_log_oiq_id,
								'old_quantity'          => $stock_log_old_quantity,
								'new_quantity'          => $stock_log_old_quantity - (int) $orderdetail->quantity,
								'delta'                 => -1 * (int) $orderdetail->quantity,
								'reason'                => 'order',
								'source'                => 'order',
								'note'                  => sprintf( __( 'Order #%d approved', 'wp-easycart' ), $order_id ),
								'user_id'               => get_current_user_id(),
							) );
						}
					}
				}
			}
			/* END Stock Adjustment Check */
			$wpdb->query( $wpdb->prepare( 'UPDATE ec_order SET orderstatus_id = %s, last_updated = NOW() WHERE order_id = %d', $orderstatus_id, $order_id ) );
			do_action( 'wpeasycart_order_status_update', $order_id, $orderstatus_id );
			$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log( order_id, order_log_key ) VALUES( %d, "order-status-update" )', $order_id ) );
			$order_log_id = $wpdb->insert_id;
			$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "orderstatus_id", %s )', $order_log_id, $order_id, $orderstatus_id ) );

			if ( '3' == $orderstatus_id || '6' == $orderstatus_id || '10' == $orderstatus_id || '15' == $orderstatus_id ) {
				do_action( 'wpeasycart_order_paid', $order_id );
			} else if ( '2' == $orderstatus_id ) {
				do_action( 'wpeasycart_order_shipped', $order_id );
			} else if ( '16' == $orderstatus_id ) {
				do_action( 'wpeasycart_full_order_refund', $order_id );
			} else if ( '17' == $orderstatus_id ) {
				do_action( 'wpeasycart_partial_order_refund', $order_id );
			}
			do_action( 'wpeasycart_order_updated', $order_id );
		}

		public function update_order_info() {
			global $wpdb;

			$order_id = (int) $_POST['order_id'];
			$order_weight = sanitize_text_field( wp_unslash( $_POST['order_weight'] ) );
			$giftcard_id = sanitize_text_field( wp_unslash( $_POST['giftcard_id'] ) );
			$promo_code = sanitize_text_field( wp_unslash( $_POST['promo_code'] ) );
			$order_notes = sanitize_textarea_field( wp_unslash( $_POST['order_notes'] ) );

			$wpdb->query( $wpdb->prepare( 'UPDATE ec_order SET order_weight = %s, giftcard_id = %s, promo_code = %s, order_notes = %s, last_updated = NOW() WHERE order_id = %d', $order_weight, $giftcard_id, $promo_code, $order_notes, $order_id ) );
			$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log( order_id, order_log_key ) VALUES( %d, "order-info-update" )', $order_id ) );
			$order_log_id = $wpdb->insert_id;
			$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "order_weight", %s )', $order_log_id, $order_id, $order_weight ) );
			$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "giftcard_id", %s )', $order_log_id, $order_id, $giftcard_id ) );
			$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "promo_code", %s )', $order_log_id, $order_id, $promo_code ) );
			$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "order_notes", %s )', $order_log_id, $order_id, $order_notes ) );

			do_action( 'wpeasycart_order_updated', $order_id );
		}

		public function update_shipping_method_info() {
			global $wpdb;

			$order_id = (int) $_POST['order_id'];
			$use_expedited_shipping = ( isset( $_POST['use_expedited_shipping'] ) && $_POST['use_expedited_shipping'] == '1' ) ? 1 : 0;
			$shipping_method = sanitize_text_field( wp_unslash( $_POST['shipping_method'] ) );
			$shipping_carrier = sanitize_text_field( wp_unslash( $_POST['shipping_carrier'] ) );
			$tracking_number = sanitize_text_field( wp_unslash( $_POST['tracking_number'] ) );

			do_action( 'wpeasycart_tracking_info_update', $order_id, $use_expedited_shipping, $shipping_method, $shipping_carrier, $tracking_number );

			$wpdb->query( $wpdb->prepare( 'UPDATE ec_order SET use_expedited_shipping = %d, shipping_method = %s, shipping_carrier = %s, tracking_number = %s, last_updated = NOW() WHERE order_id = %d', $use_expedited_shipping, $shipping_method, $shipping_carrier, $tracking_number, $order_id ) );
			$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log( order_id, order_log_key ) VALUES( %d, "order-shipping-method-update" )', $order_id ) );
			$order_log_id = $wpdb->insert_id;
			$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "use_expedited_shipping", %s )', $order_log_id, $order_id, $use_expedited_shipping ) );
			$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "shipping_method", %s )', $order_log_id, $order_id, $shipping_method ) );
			$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "shipping_carrier", %s )', $order_log_id, $order_id, $shipping_carrier ) );
			$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "tracking_number", %s )', $order_log_id, $order_id, $tracking_number ) );

			do_action( 'wpeasycart_order_updated', $order_id );
		}

		/**
		 * @since 6.0.0 $recipients: send to these addresses only ( order screen send dialog ), without the store copy.
		 */
		public function send_customer_shipping_email( $order_id, $trackingnumber, $shipcarrier, $recipients = null ) {
			global $wpdb;

			$order = $wpdb->get_results( $wpdb->prepare( 'SELECT ec_order.*, billing_country.name_cnt AS billing_country_name, shipping_country.name_cnt AS shipping_country_name FROM ec_order LEFT JOIN ec_country AS billing_country ON billing_country.iso2_cnt = ec_order.billing_country LEFT JOIN ec_country AS shipping_country ON shipping_country.iso2_cnt = ec_order.shipping_country WHERE order_id = %d', $order_id ) );
			$orderdetails = $wpdb->get_results( $wpdb->prepare( 'SELECT ec_orderdetail.* FROM ec_orderdetail WHERE order_id = %d ORDER BY product_id', $order_id ) );
			$email_logo_url = get_option( 'ec_option_email_logo' );
			$orderfromemail = stripslashes( get_option( 'ec_option_order_from_email' ) );

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

			ob_start();
			if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_shipping_email.php' ) ) {
				include EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_shipping_email.php';
			} else {
				include EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_shipping_email.php';
			}
			$message = ob_get_clean();

			$headers = array( );
			$headers[] = 'MIME-Version: 1.0';
			$headers[] = 'Content-Type: text/html; charset=utf-8';
			$headers[] = 'From: ' . stripslashes( get_option( 'ec_option_order_from_email' ) );
			$headers[] = 'Reply-To: ' . stripslashes( get_option( 'ec_option_order_from_email' ) );
			$headers[] = 'X-Mailer: PHP/' . phpversion();

			$admin_email = stripslashes( get_option( 'ec_option_bcc_email_addresses' ) );

			if ( is_array( $recipients ) ) {
				ec_orderdisplay::send_to_recipients( $recipients, wp_easycart_language()->get_text( 'ec_shipping_email', 'shipping_email_title' ) . ' ' . $order_id, $message, $headers );
				self::log_email_sent( $order_id, 'order-shipping-email', $recipients );
				return;
			}

			if ( get_option( 'ec_option_use_wp_mail' ) ) {
				wp_mail( $order[0]->user_email, wp_easycart_language()->get_text( 'ec_shipping_email', 'shipping_email_title' ) . ' ' . $order_id, $message, $headers );
				if ( '' != $order[0]->email_other ) {
					wp_mail( $order[0]->email_other, wp_easycart_language()->get_text( 'ec_shipping_email', 'shipping_email_title' ) . ' ' . $order_id, $message, $headers );
				}
				wp_mail( $admin_email, wp_easycart_language()->get_text( 'ec_shipping_email', 'shipping_email_title' ) . ' ' . $order_id, $message, $headers );
			} else {
				$to = $order[0]->user_email;
				$subject = wp_easycart_language()->get_text( 'ec_shipping_email', 'shipping_email_title' ) . ' ' . $order_id;
				$mailer = new wpeasycart_mailer();
				$mailer->send_order_email( $to, $subject, $message );
				if ( '' != $order[0]->email_other ) {
					$mailer->send_order_email( $order[0]->email_other, $subject, $message );
				}
				$mailer->send_order_email( $admin_email, $subject, $message );
			}
			$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log( order_id, order_log_key ) VALUES( %d, "order-shipping-email" )', $order_id ) );
			$order_log_id = $wpdb->insert_id;
			$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "email", %s )', $order_log_id, $order_id, $order[0]->user_email ) );
			if ( '' != $order[0]->email_other ) {
				$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "email_other", %s )', $order_log_id, $order_id, $order[0]->email_other ) );
			}
		}

		public function delete_order() {
			global $wpdb;

			$order_id = (int) $_GET['order_id'];
			do_action( 'wpeasycart_order_deleting', $order_id );
			$wpdb->query( $wpdb->prepare( 'DELETE FROM ec_order WHERE order_id = %d', $order_id ) );
			$wpdb->query( $wpdb->prepare( 'DELETE FROM ec_orderdetail WHERE order_id = %d', $order_id ) );
			$wpdb->query( $wpdb->prepare( 'DELETE FROM ec_download WHERE order_id = %d', $order_id ) );
			do_action( 'wpeasycart_order_deleted', $order_id );

			return array( 'success' => 'order-deleted' );
		}

		public function bulk_delete_order() {
			global $wpdb;
			$bulk_ids = (array) $_GET['bulk']; // XSS OK. Forced array and each item sanitized.

			foreach ( $bulk_ids as $bulk_id ) {
				do_action( 'wpeasycart_order_deleting', (int) $bulk_id );
				$wpdb->query( $wpdb->prepare( 'DELETE FROM ec_order WHERE order_id = %d', (int) $bulk_id ) );
				$wpdb->query( $wpdb->prepare( 'DELETE FROM ec_orderdetail WHERE order_id = %d', (int) $bulk_id ) );
				$wpdb->query( $wpdb->prepare( 'DELETE FROM ec_download WHERE order_id = %d', (int) $bulk_id ) );
				$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log( order_id, order_log_key ) VALUES( %d, "order-deleted" )', (int) $bulk_id ) );
				do_action( 'wpeasycart_order_deleted', (int) $bulk_id );
			}

			return array( 'success' => 'order-deleted' );
		}

		public function bulk_update_order_status() {
			global $wpdb;
			$bulk_ids = (array) $_GET['bulk']; // XSS OK. Forced array and each item sanitized.
			$orderstatus_id = (int) $_GET['bulk_order_status'];

			foreach ( $bulk_ids as $bulk_id ) {
				$wpdb->query( $wpdb->prepare( 'UPDATE ec_order SET orderstatus_id = %d WHERE order_id = %d', $orderstatus_id, (int) $bulk_id ) );
				do_action( 'wpeasycart_order_status_update', (int) $bulk_id, $orderstatus_id );
				$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log( order_id, order_log_key ) VALUES( %d, "order-status-update" )', (int) $bulk_id ) );
				$order_log_id = $wpdb->insert_id;
				$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "orderstatus_id", %s )', $order_log_id, (int) $bulk_id, $orderstatus_id ) );
			}

			return array( 'success' => 'order-status-updated' );
		}

		public function bulk_mark_order_viewed() {
			global $wpdb;
			$bulk_ids = (array) $_GET['bulk']; // XSS OK. Forced array and each item sanitized.

			foreach ( $bulk_ids as $bulk_id ) {
				$wpdb->query( $wpdb->prepare( 'UPDATE ec_order SET order_viewed = 1, last_updated = NOW() WHERE order_id = %d', (int) $bulk_id ) );
			}

			return array( 'success' => 'order-viewed' );
		}

		public function bulk_mark_order_not_viewed() {
			global $wpdb;
			$bulk_ids = (array) $_GET['bulk']; // XSS OK. Forced array and each item sanitized.

			foreach ( $bulk_ids as $bulk_id ) {
				$wpdb->query( $wpdb->prepare( 'UPDATE ec_order SET order_viewed = 0, last_updated = NOW() WHERE order_id = %d', (int) $bulk_id ) );
			}

			return array( 'success' => 'order-not-viewed' );
		}

		public function mark_all_order_viewed() {
			global $wpdb;
			$wpdb->query( 'UPDATE ec_order SET order_viewed = 1, last_updated = NOW()' );
			return array( 'success' => 'order-viewed' );
		}

		public function mark_all_order_not_viewed() {
			global $wpdb;
			$wpdb->query( 'UPDATE ec_order SET order_viewed = 0, last_updated = NOW()' );
			return array( 'success' => 'order-not-viewed' );
		}

		/**
		 * Resends one gift card email.
		 *
		 * @since 6.0.0 Returns the recipient and logs the send to the order timeline so the order
		 *              details screen can confirm it on screen.
		 *
		 * @return array|false array( order_id, orderdetail_id, email ), or false when the line has no gift card email.
		 */
		public function resendgiftcardemail() {
			global $wpdb;

			// phpcs:disable WordPress.Security.NonceVerification.Missing -- ec_admin_ajax_resend_giftcard_email() checks the wp-easycart-order-details nonce and capability before calling this.
			$order_id = ( isset( $_POST['order_id'] ) ) ? (int) $_POST['order_id'] : 0;
			$orderdetail_id = ( isset( $_POST['orderdetail_id'] ) ) ? (int) $_POST['orderdetail_id'] : 0;
			// phpcs:enable WordPress.Security.NonceVerification.Missing
			$cart_item = $wpdb->get_row( $wpdb->prepare( 'SELECT giftcard_id, gift_card_message, gift_card_from_name, gift_card_to_name, gift_card_email, title, unit_price, unit_price AS gift_card_value, is_deconetwork, deconetwork_image_link, image1, 0 AS image1_optionitem FROM ec_orderdetail WHERE orderdetail_id = %d', $orderdetail_id ) );
			if ( ! $cart_item || '' == trim( (string) $cart_item->gift_card_email ) ) {
				return false;
			}

			$this->send_gift_card_email( $cart_item, $cart_item->giftcard_id );

			if ( $order_id ) {
				$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log( order_id, order_log_key ) VALUES( %d, "order-giftcard-email" )', $order_id ) );
				$order_log_id = $wpdb->insert_id;
				$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "gift_card_email", %s )', $order_log_id, $order_id, $cart_item->gift_card_email ) );
				$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "giftcard_id", %s )', $order_log_id, $order_id, $cart_item->giftcard_id ) );
			}

			return array(
				'order_id'       => $order_id,
				'orderdetail_id' => $orderdetail_id,
				'email'          => $cart_item->gift_card_email,
			);
		}

		private function send_gift_card_email( $cart_item, $giftcard_id ) {
			global $wpdb;
			$cart_item->gift_card_value = $wpdb->get_var( $wpdb->prepare( "SELECT amount FROM ec_giftcard WHERE giftcard_id = %s", $cart_item->giftcard_id ) );
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
			$headers[] = 'MIME-Version: 1.0';
			$headers[] = 'Content-Type: text/html; charset=utf-8';
			$headers[] = 'From: ' . stripslashes( get_option( 'ec_option_order_from_email' ) );
			$headers[] = 'Reply-To: ' . stripslashes( get_option( 'ec_option_order_from_email' ) );
			$headers[] = 'X-Mailer: PHP/' . phpversion();

			ob_start();
			if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_email_giftcard.php' ) )	
				include EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_email_giftcard.php';
			else
				include EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_email_giftcard.php';

			$message = ob_get_clean();

			$email_send_method = get_option( 'ec_option_use_wp_mail' );
			$email_send_method = apply_filters( 'wpeasycart_email_method', $email_send_method );

			if ( $email_send_method == '1' ) {
				wp_mail( $cart_item->gift_card_email, wp_easycart_language()->get_text( 'cart_success', 'cart_giftcard_receipt_title' ), $message, implode( "\r\n", $headers ) );
				wp_mail( stripslashes( get_option( 'ec_option_bcc_email_addresses' ) ), wp_easycart_language()->get_text( 'cart_success', 'cart_giftcard_receipt_title' ), $message, implode( "\r\n", $headers ) );

			} else if ( $email_send_method == '0' ) {
				$admin_email = stripslashes( get_option( 'ec_option_bcc_email_addresses' ) );
				$to = $cart_item->gift_card_email;
				$subject = wp_easycart_language()->get_text( 'cart_success', 'cart_giftcard_receipt_title' );
				$mailer = new wpeasycart_mailer();
				$mailer->send_order_email( $to, $subject, $message );
				$mailer->send_order_email( $admin_email, $subject, $message );

			} else {
				do_action( 'wpeasycart_custom_gift_card_email', stripslashes( get_option( 'ec_option_order_from_email' ) ), $cart_item->gift_card_email, stripslashes( get_option( 'ec_option_bcc_email_addresses' ) ), wp_easycart_language()->get_text( 'cart_success', 'cart_giftcard_receipt_title' ), $message );

			}

		}

		 public function resend_receipts() {
			 if ( isset( $_GET['bulk'] ) && is_array( $_GET['bulk'] ) ) {
				$bulk_count = count( $_GET['bulk'] );
				for ( $i = 0; $i < $bulk_count; $i++ ) {
					$this->resend_receipt( (int) $_GET['bulk'][ $i ] );
				}
				return array( 'success' => 'receipt-sent' );
			} else if ( isset( $_GET['bulk'] ) ) {
				$this->resend_receipt( (int) $_GET['bulk'] );
				return array( 'success' => 'receipt-sent' );
			} else {
				$this->resend_receipt( (int) $_GET['order_id'] );
				return array( 'success' => 'receipt-sent', 'order_id' => (int) $_GET['order_id'], 'ec_admin_form_action' => 'edit' );
			}
		 }

		 public function resend_invoices() {
			 if ( isset( $_GET['bulk'] ) && is_array( $_GET['bulk'] ) ) {
				$bulk_count = count( $_GET['bulk'] );
				for ( $i = 0; $i < $bulk_count; $i++ ) {
					if ( $i > 0 )
						echo '<div class="ec_admin_page_break"></div>';
					$this->resend_invoice( (int) $_GET['bulk'][ $i ] );
				}
				return array( 'success' => 'invoice-sent' );
			} else if ( isset( $_GET['bulk'] ) ) {
				$this->resend_invoice( (int) $_GET['bulk'] );
				return array( 'success' => 'invoice-sent' );
			} else {
				$this->resend_invoice( (int) $_GET['order_id'] );
				return array( 'success' => 'invoice-sent', 'order_id' => (int) $_GET['order_id'], 'ec_admin_form_action' => 'edit' );
			}
		 }

		 public function print_receipts() {
			 if ( isset( $_GET['bulk'] ) && is_array( $_GET['bulk'] ) ) {
				$bulk_count = count( $_GET['bulk'] );
				for ( $i = 0; $i < $bulk_count; $i++ ) {
					if ( $i > 0 )
						echo '<div class="ec_admin_page_break"></div>';
					$this->print_receipt( (int) $_GET['bulk'][ $i ] );
				}
			} else if ( isset( $_GET['bulk'] ) ) {
				$this->print_receipt( (int) $_GET['bulk'] );
			} else {
				$this->print_receipt( (int) $_GET['order_id'] );
			}
		 }

		/**
		 * Order timeline entry for an email sent from the order screen send dialog ( 'email' = To, 'email_other' = Cc and Bcc ).
		 *
		 * @since 6.0.0
		 * @param int    $order_id   Order.
		 * @param string $log_key    order-receipt-email | order-shipping-email.
		 * @param array  $recipients array( 'to' => [], 'cc' => [], 'bcc' => [] ).
		 */
		public static function log_email_sent( $order_id, $log_key, $recipients ) {
			global $wpdb;
			$wpdb->insert( 'ec_order_log', array( 'order_id' => (int) $order_id, 'order_log_key' => $log_key ), array( '%d', '%s' ) );
			$order_log_id = (int) $wpdb->insert_id;
			$wpdb->insert( 'ec_order_log_meta', array( 'order_log_id' => $order_log_id, 'order_id' => (int) $order_id, 'order_log_meta_key' => 'email', 'order_log_meta_value' => implode( ', ', (array) $recipients['to'] ) ), array( '%d', '%d', '%s', '%s' ) );
			$others = array_merge( isset( $recipients['cc'] ) ? (array) $recipients['cc'] : array(), isset( $recipients['bcc'] ) ? (array) $recipients['bcc'] : array() );
			if ( $others ) {
				$wpdb->insert( 'ec_order_log_meta', array( 'order_log_id' => $order_log_id, 'order_id' => (int) $order_id, 'order_log_meta_key' => 'email_other', 'order_log_meta_value' => implode( ', ', $others ) ), array( '%d', '%d', '%s', '%s' ) );
			}
		}

		/**
		 * @since 6.0.0 $recipients: resend to these addresses only ( order screen send dialog ).
		 */
		 public function resend_receipt( $order_id, $recipients = null ) {
			$mysqli = new ec_db_admin();
			$order_row = $mysqli->get_order_row_admin( $order_id );
			if ( $order_row ) {
				$order_display = new ec_orderdisplay( $order_row, true, true );
				$order_display->send_email_receipt( false, $recipients );
				return true;
			} else {
				return false;
			}
		 }

		 public function resend_invoice( $order_id ) {
			$mysqli = new ec_db_admin();
			$order_row = $mysqli->get_order_row_admin( $order_id );
			if ( $order_row ) {
				$order_display = new ec_orderdisplay( $order_row, true, true );
				$order_display->send_invoice();
				return true;
			} else {
				return false;
			}
		 }

		 public function send_order_shipped_emails() {
			global $wpdb;
			if ( isset( $_GET['bulk'] ) && is_array( $_GET['bulk'] ) ) {
				$bulk_count = count( $_GET['bulk'] );
				for ( $i = 0; $i < $bulk_count; $i++ ) {
					$order = $wpdb->get_row( $wpdb->prepare( 'SELECT order_id, tracking_number, shipping_carrier FROM ec_order WHERE order_id = %d', (int) $_GET['bulk'][ $i ] ) );
					$this->send_customer_shipping_email( $order->order_id, $order->tracking_number, $order->shipping_carrier );
				}
				return array( 'success' => 'shipping-email-sent' );
			} else if ( isset( $_GET['bulk'] ) ) {
				$order = $wpdb->get_row( $wpdb->prepare( 'SELECT order_id, tracking_number, shipping_carrier FROM ec_order WHERE order_id = %d', (int) $_GET['bulk'] ) );
				$this->send_customer_shipping_email( $order->order_id, $order->tracking_number, $order->shipping_carrier );
				return array( 'success' => 'shipping-email-sent' );
			} else {
				$order = $wpdb->get_row( $wpdb->prepare( 'SELECT order_id, tracking_number, shipping_carrier FROM ec_order WHERE order_id = %d', (int) $_GET['order_id'] ) );
				$this->send_customer_shipping_email( $order->order_id, $order->tracking_number, $order->shipping_carrier );
				return array( 'success' => 'shipping-email-sent', 'order_id' => (int) $_GET['order_id'], 'ec_admin_form_action' => 'edit' );
			}
		 }

		 public function print_receipt( $order_id ) {
			$mysqli = new ec_db_admin();
			$order = $mysqli->get_order_row_admin( $order_id );
			$bill_country = $mysqli->get_country_name( $order->billing_country );
			$ship_country = $mysqli->get_country_name( $order->shipping_country );
			if ( $bill_country ) {
				$order->billing_country = $bill_country;
			}
			if ( $ship_country ) {
				$order->shipping_country = $ship_country;
			}
			$order_details = $mysqli->get_order_details_admin( $order_id );
			global $wpdb;
			$now_server = $wpdb->get_var( 'SELECT NOW() AS the_time' );
			$now_timestamp = strtotime( $now_server );
			$now_gmt_timestampt = time();
			$storage_offset = $now_timestamp - $now_gmt_timestampt;
			$local_offset = get_option( 'gmt_offset' ) * 60 * 60;
			$date_diff = $local_offset - $storage_offset;
			$date = $order->order_date;
			$date_timestamp = strtotime( $date );
			$date_timestamp = $date_timestamp + $date_diff;
			$order_timestamp = $date_timestamp;
			$country_list = $mysqli->get_countries();
			$tax_struct = new ec_tax( 0, 0, 0, '', '' );
			$total = $GLOBALS['currency']->get_currency_display( $order->grand_total );
			$subtotal = $GLOBALS['currency']->get_currency_display( $order->sub_total );
			$tip = $GLOBALS['currency']->get_currency_display( $order->tip_total );
			$tax = $GLOBALS['currency']->get_currency_display( $order->tax_total );
			if ( $order->duty_total > 0 ) {
				$has_duty = true;
			} else {
				$has_duty = false;
			}
			$duty = $GLOBALS['currency']->get_currency_display( $order->duty_total );
			$vat = $GLOBALS['currency']->get_currency_display( $order->vat_total );
			$shipping = $GLOBALS['currency']->get_currency_display( $order->shipping_total );
			$discount = $GLOBALS['currency']->get_currency_display( $order->discount_total );
			$refund = $GLOBALS['currency']->get_currency_display( $order->refund_total );

			if ( $order->vat_rate > 0 ) {
				$vat_rate = number_format( $order->vat_rate, 0, '', '' );
			} else if ( ( $order->grand_total - $order->vat_total ) > 0 ) {
				$vat_rate = number_format( ( $order->vat_total / ( $order->grand_total - $order->vat_total ) ) * 100, 0, '', '' );
			} else {
				$vat_rate = number_format( 0, 0, '', '' );
			}
			$gst = $order->gst_total;
			$pst = $order->pst_total;
			$hst = $order->hst_total;

			$gst_rate = $order->gst_rate;
			$pst_rate = $order->pst_rate;
			$hst_rate = $order->hst_rate;

			if ( floor( $gst_rate ) == $gst_rate ) {
				$gst_rate = number_format( $gst_rate, 0, '', '' );
			}
			if ( floor( $pst_rate ) == $pst_rate ) {
				$pst_rate = number_format( $pst_rate, 0, '', '' );
			}
			if ( floor( $hst_rate ) == $hst_rate ) {
				$hst_rate = number_format( $hst_rate, 0, '', '' );
			}
			$order_fees = $mysqli->get_order_fees( $order->order_id );

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
			$email_logo_url = get_option( 'ec_option_email_logo' );

			if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_account_print_receipt.php' ) ) {
				include EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_account_print_receipt.php';
			} else {
				include EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_account_print_receipt.php';
			}
		 }

		 public function print_packing_slips() {
			if ( isset( $_GET['bulk'] ) && is_array( $_GET['bulk'] ) ) {
				$bulk_count = count( $_GET['bulk'] );
				for ( $i = 0; $i < $bulk_count; $i++ ) {
					if ( $i > 0 ) {
						echo '<div class="ec_admin_page_break"></div>';
					}
					$this->print_packing_slip( (int) $_GET['bulk'][ $i ] );
				}
			} else if ( isset( $_GET['bulk'] ) ) {
				$this->print_packing_slip( (int) $_GET['bulk'] );
			} else {
				$this->print_packing_slip( (int) $_GET['order_id'] );
			}
		 }

		 public function print_packing_slip( $order_id ) {
			$db = new ec_db_admin();
			$mysqli = new ec_db_admin();
			$order = $db->get_order_row_admin( $order_id );
			$order_details = $db->get_order_details_admin( $order_id );

			global $wpdb;
			$now_server = $wpdb->get_var( 'SELECT NOW() AS the_time' );
			$now_timestamp = strtotime( $now_server );
			$now_gmt_timestampt = time();
			$storage_offset = $now_timestamp - $now_gmt_timestampt;
			$local_offset = get_option( 'gmt_offset' ) * 60 * 60;
			$date_diff = $local_offset - $storage_offset;
			$date = $order->order_date;
			$date_timestamp = strtotime( $date );
			$date_timestamp = $date_timestamp + $date_diff;
			$order_timestamp = $date_timestamp;

			$country_list = $db->get_countries();

			$total = $GLOBALS['currency']->get_currency_display( $order->grand_total );
			$subtotal = $GLOBALS['currency']->get_currency_display( $order->sub_total );
			$tax = $GLOBALS['currency']->get_currency_display( $order->tax_total );
			$tip = $GLOBALS['currency']->get_currency_display( $order->tip_total );
			if ( $order->duty_total > 0 ) { $has_duty = true; } else { $has_duty = false; }
			$duty = $GLOBALS['currency']->get_currency_display( $order->duty_total );
			$vat = $GLOBALS['currency']->get_currency_display( $order->vat_total );
			$vat_rate = number_format( $order->vat_rate, 0, '', '' );
			$shipping = $GLOBALS['currency']->get_currency_display( $order->shipping_total );
			$discount = $GLOBALS['currency']->get_currency_display( $order->discount_total );
			$gst_total = $GLOBALS['currency']->get_currency_display( $order->gst_total );
			$pst_total = $GLOBALS['currency']->get_currency_display( $order->pst_total );
			$hst_total = $GLOBALS['currency']->get_currency_display( $order->hst_total );
			$gst_rate = $order->gst_rate;
			$pst_rate = $order->pst_rate;
			$hst_rate = $order->hst_rate;

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

			if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_admin_packaging_slip.php' ) ) {
				include EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_admin_packaging_slip.php';
			} else {
				include EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_admin_packaging_slip.php';
			}
		}
	}
endif; // End if class_exists check

function wp_easycart_admin_orders() {
	return wp_easycart_admin_orders::instance();
}
wp_easycart_admin_orders();
add_action( 'wp_ajax_ec_admin_ajax_edit_order_info', 'ec_admin_ajax_edit_order_info' );
function ec_admin_ajax_edit_order_info() {
	if ( ! wp_easycart_admin_verification()->verify_access( 'wp-easycart-order-details' ) ) {
		return false;
	}

	wp_easycart_admin_orders()->update_order_info();
	die();
}
add_action( 'wp_ajax_ec_admin_ajax_edit_shipping_method_info', 'ec_admin_ajax_edit_shipping_method_info' );
function ec_admin_ajax_edit_shipping_method_info() {
	if ( ! wp_easycart_admin_verification()->verify_access( 'wp-easycart-order-details' ) ) {
		return false;
	}

	wp_easycart_admin_orders()->update_shipping_method_info();
	die();
}
add_action( 'wp_ajax_ec_admin_ajax_edit_orderstatus', 'ec_admin_ajax_edit_orderstatus' );
function ec_admin_ajax_edit_orderstatus() {
	if ( ! wp_easycart_admin_verification()->verify_access( 'wp-easycart-order-details' ) ) {
		return false;
	}

	wp_easycart_admin_orders()->update_orderstatus();
	die();
}
add_action( 'wp_ajax_ec_admin_ajax_edit_customer_notes', 'ec_admin_ajax_edit_customer_notes' );
function ec_admin_ajax_edit_customer_notes() {
	if ( ! wp_easycart_admin_verification()->verify_access( 'wp-easycart-order-details' ) ) {
		return false;
	}

	wp_easycart_admin_orders()->update_notes();
	die();
}
add_action( 'wp_ajax_ec_admin_ajax_resend_giftcard_email', 'ec_admin_ajax_resend_giftcard_email' );
/**
 * @since 6.0.0 Answers with JSON so the order details screen can confirm the send.
 */
function ec_admin_ajax_resend_giftcard_email() {
	if ( ! wp_easycart_admin_verification()->verify_access( 'wp-easycart-order-details' ) ) {
		wp_send_json_error( array( 'message' => __( 'You do not have permission to send this email.', 'wp-easycart' ) ), 403 );
	}

	$result = wp_easycart_admin_orders()->resendgiftcardemail();
	if ( ! $result ) {
		wp_send_json_error( array( 'message' => __( 'This gift card has no email address to send to.', 'wp-easycart' ) ) );
	}

	wp_send_json_success(
		array(
			'email'   => $result['email'],
			/* translators: %s: gift card recipient email address. */
			'message' => sprintf( __( 'Gift card email sent to %s.', 'wp-easycart' ), $result['email'] ),
		)
	);
}
add_action( 'wp_ajax_ec_admin_ajax_order_details_send_order_shipped_email', 'ec_admin_ajax_order_details_send_order_shipped_email' );
function ec_admin_ajax_order_details_send_order_shipped_email() {
	if ( ! wp_easycart_admin_verification()->verify_access( 'wp-easycart-order-details' ) ) {
		return false;
	}

	global $wpdb;
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verify_access() above checks the wp-easycart-order-details nonce and capability; WPCS cannot see through the method call.
	$order_id = ( isset( $_POST['order_id'] ) ) ? (int) $_POST['order_id'] : 0;
	$order = ( $order_id ) ? $wpdb->get_row( $wpdb->prepare( 'SELECT order_id, user_email, email_other, tracking_number, shipping_carrier FROM ec_order WHERE order_id = %d', $order_id ) ) : null;
	if ( ! $order ) {
		wp_send_json_error( array( 'message' => __( 'The order could not be found.', 'wp-easycart' ) ) );
	}
	$recipients = ecv2_order_email_recipients();
	if ( null === $recipients && '' == trim( (string) $order->user_email ) ) {
		wp_send_json_error( array( 'message' => __( 'This order has no email address to send to. Add one in the Edit Order drawer first.', 'wp-easycart' ) ) );
	}

	wp_easycart_admin_orders()->send_customer_shipping_email( $order->order_id, $order->tracking_number, $order->shipping_carrier, $recipients );

	if ( null !== $recipients ) {
		wp_send_json_success(
			array(
				'email'           => implode( ', ', $recipients['to'] ),
				'email_other'     => implode( ', ', array_merge( $recipients['cc'], $recipients['bcc'] ) ),
				'tracking_number' => (string) $order->tracking_number,
				/* translators: %s: email addresses the shipped email was sent to. */
				'message'         => sprintf( __( 'Order shipped email sent to %s.', 'wp-easycart' ), implode( ', ', $recipients['to'] ) ),
			)
		);
	}

	/* 6.0.0: JSON so the order details screen can confirm the send on screen. The
	   order-shipping-email timeline entry is written by send_customer_shipping_email(). */
	wp_send_json_success(
		array(
			'email'           => $order->user_email,
			'email_other'     => (string) $order->email_other,
			'tracking_number' => (string) $order->tracking_number,
			/* translators: %s: customer email address. */
			'message'         => sprintf( __( 'Order shipped email sent to %s.', 'wp-easycart' ), $order->user_email ),
		)
	);
}

add_action( 'wp_ajax_ec_admin_ajax_get_order_users', 'ec_admin_ajax_get_order_users' );
function ec_admin_ajax_get_order_users() {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- verify_access() checks the wp-easycart-order-details nonce and capability; WPCS cannot see through the method call.
	/* 6.0.0: customer lookup now requires the order-details nonce + store capability, and also matches email. */
	if ( ! wp_easycart_admin_verification()->verify_access( 'wp-easycart-order-details' ) ) {
		wp_send_json( (object) array( 'items' => array() ) );
	}
	global $wpdb;
	$guest  = array(
		(object) array(
			'text' => esc_attr__( 'Guest', 'wp-easycart' ),
			'id'   => '0',
		),
	);
	$search = '%' . $wpdb->esc_like( ( isset( $_POST['q'] ) ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '' ) . '%';
	// phpcs:enable WordPress.Security.NonceVerification.Missing
	$users = $wpdb->get_results( $wpdb->prepare( 'SELECT CONCAT( ec_user.last_name, ", ", ec_user.first_name, " (", ec_user.user_id, ")" ) AS text, user_id AS id FROM ec_user WHERE last_name LIKE %s OR first_name LIKE %s OR email LIKE %s ORDER BY last_name ASC, first_name ASC LIMIT 100', $search, $search, $search ) );
	if ( $users ) {
		$results = (object) array(
			'items' => array_merge( $users, $guest ),
		);
	} else {
		$results = (object) array(
			'items' => $guest,
		);
	}
	echo wp_json_encode( $results );
	die();
}

if ( ! function_exists( 'wp_easycart_admin_order_account_badge_html' ) ) {
	/**
	 * Account chip for the order details customer card: "#ID" plus a "View account"
	 * link for an account order, or "Guest" for a guest checkout. Shared by the
	 * order details template and the ec_admin_ajax_update_order_user response so
	 * the card repaints with exactly the markup a page load would print.
	 *
	 * @since 6.0.0
	 *
	 * @param int $user_id The order's user_id ( 0 = guest ).
	 * @return string Escaped HTML.
	 */
	function wp_easycart_admin_order_account_badge_html( $user_id ) {
		$user_id = (int) $user_id;
		if ( $user_id ) {
			$account_url = 'admin.php?page=wp-easycart-users&subpage=accounts&user_id=' . $user_id . '&ec_admin_form_action=edit&wp_easycart_nonce=' . wp_create_nonce( 'wp-easycart-action-edit' );
			return '<span class="ecodv2-user-chip ecodv2-user-chip-account">#' . esc_html( $user_id ) . '</span>'
				. '<a class="ecodv2-user-link" href="' . esc_url( admin_url( $account_url ) ) . '" target="_blank">' . esc_html__( 'View account', 'wp-easycart' ) . ' <span class="dashicons dashicons-external"></span></a>';
		}
		return '<span class="ecodv2-user-chip ecodv2-user-chip-guest">' . esc_html__( 'Guest', 'wp-easycart' ) . '</span>';
	}
}

add_action( 'wp_ajax_ec_admin_ajax_update_order_user', 'ec_admin_ajax_update_order_user' );
/**
 * Assigns an order to a customer account ( or back to guest, user_id 0 ) from the
 * order details drawer, then returns everything on the page that depends on the
 * account so the screen repaints without a reload: the customer card chip + link,
 * the drawer select label, and the server-rendered output of the V2 header chips
 * and customer card hooks ( PRO insight chips and customer stats hang off them ).
 *
 * @since 6.0.0 Returns JSON ( was an empty response ) and rejects unknown users.
 */
function ec_admin_ajax_update_order_user() {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- verify_access() checks the wp-easycart-order-details nonce and capability; WPCS cannot see through the method call.
	if ( ! wp_easycart_admin_verification()->verify_access( 'wp-easycart-order-details' ) ) {
		wp_send_json_error( array( 'message' => __( 'You do not have permission to update this order.', 'wp-easycart' ) ), 403 );
	}

	global $wpdb;
	$order_id = ( isset( $_POST['order_id'] ) ) ? (int) $_POST['order_id'] : 0;
	$user_id  = ( isset( $_POST['user_id'] ) ) ? max( 0, (int) $_POST['user_id'] ) : 0;
	// phpcs:enable WordPress.Security.NonceVerification.Missing

	if ( ! $order_id || ! $wpdb->get_var( $wpdb->prepare( 'SELECT order_id FROM ec_order WHERE order_id = %d', $order_id ) ) ) {
		wp_send_json_error( array( 'message' => __( 'The order could not be found.', 'wp-easycart' ) ) );
	}
	if ( $user_id && ! $wpdb->get_var( $wpdb->prepare( 'SELECT user_id FROM ec_user WHERE user_id = %d', $user_id ) ) ) {
		wp_send_json_error( array( 'message' => __( 'The selected customer account could not be found.', 'wp-easycart' ) ) );
	}

	$wpdb->query( $wpdb->prepare( 'UPDATE ec_order SET user_id = %d WHERE order_id = %d', $user_id, $order_id ) );
	$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log( order_id, order_log_key ) VALUES( %d, "order-user-update" )', $order_id ) );
	$order_log_id = $wpdb->insert_id;
	$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "user_id", %s )', $order_log_id, $order_id, $user_id ) );

	/* Same record the details template renders from ( wp_easycart_admin_details_orders::init_data ). */
	$order = $wpdb->get_row( $wpdb->prepare( 'SELECT ec_order.*, ec_user.first_name, ec_user.last_name, billing_country.name_cnt AS billing_country_name, shipping_country.name_cnt AS shipping_country_name, ec_orderstatus.is_approved, ec_orderstatus.order_status FROM ec_order LEFT JOIN ec_orderstatus ON ( ec_orderstatus.status_id = ec_order.orderstatus_id ) LEFT JOIN ec_country AS billing_country ON ( billing_country.iso2_cnt = ec_order.billing_country ) LEFT JOIN ec_country AS shipping_country ON ( shipping_country.iso2_cnt = ec_order.shipping_country ) LEFT JOIN ec_user ON ( ec_user.user_id = ec_order.user_id ) WHERE ec_order.order_id = %d', $order_id ) );
	foreach ( get_object_vars( $order ) as $key => $value ) {
		if ( null === $value ) {
			$order->$key = '';
		}
	}

	ob_start();
	do_action( 'wp_easycart_ecv2_order_details_header_chips', $order );
	$header_chips_html = ob_get_clean();

	ob_start();
	do_action( 'wp_easycart_ecv2_order_details_customer_card', $order );
	$customer_card_html = ob_get_clean();

	wp_send_json_success(
		array(
			'user_id'            => (int) $order->user_id,
			'is_guest'           => ! (int) $order->user_id,
			'account_html'       => wp_easycart_admin_order_account_badge_html( $order->user_id ),
			'select_label'       => ( (int) $order->user_id ) ? sprintf( '%s, %s (%d)', $order->last_name, $order->first_name, (int) $order->user_id ) : __( 'Guest', 'wp-easycart' ),
			'header_chips_html'  => $header_chips_html,
			'customer_card_html' => $customer_card_html,
			'message'            => ( (int) $order->user_id ) ? __( 'Order assigned to the customer account.', 'wp-easycart' ) : __( 'Order changed to a guest checkout.', 'wp-easycart' ),
		)
	);
}
add_action( 'wp_ajax_ec_admin_ajax_get_download_keys', 'ec_admin_ajax_get_download_keys' );
function ec_admin_ajax_get_download_keys() {
	global $wpdb;
	$users = $wpdb->get_results( $wpdb->prepare( 'SELECT download_id AS text, download_id AS id FROM ec_download WHERE download_id LIKE %s ORDER BY download_id ASC LIMIT 100', '%' . sanitize_text_field( wp_unslash( $_POST['q'] ) ) . '%', (int) $_POST['page'] - 1 ) );
	if ( $users ) {
		$results = (object) array(
			'items' => $users,
		);
	} else {
		$results = (object) array(
			'items' => array(),
		);
	}
	echo json_encode( $results );
	die();
}
add_action( 'wp_ajax_ec_admin_ajax_update_order_download_key', 'ec_admin_ajax_update_order_download_key' );
function ec_admin_ajax_update_order_download_key() {
	if ( ! wp_easycart_admin_verification()->verify_access( 'wp-easycart-order-details' ) ) {
		return false;
	}

	global $wpdb;
	$orderdetail_id = (int) $_POST['orderdetail_id'];
	$download_key = sanitize_text_field( wp_unslash( $_POST['download_key'] ) );
	$wpdb->query( $wpdb->prepare( 'UPDATE ec_orderdetail SET download_key = %s WHERE orderdetail_id = %d', $download_key, $orderdetail_id ) );
	die();
}
add_action( 'wp_ajax_ec_admin_ajax_enable_download_item', 'ec_admin_ajax_enable_download_item' );
function ec_admin_ajax_enable_download_item() {
	if ( ! wp_easycart_admin_verification()->verify_access( 'wp-easycart-order-details' ) ) {
		return false;
	}

	global $wpdb;
	$orderdetail_id = (int) $_POST['orderdetail_id'];
	$wpdb->query( $wpdb->prepare( 'UPDATE ec_order_option SET optionitem_allow_download = 1 WHERE orderdetail_id = %d', $orderdetail_id ) );
	die();
}

/**
 * To / Cc / Bcc from the order screen send dialog. Null when the request did not come from the dialog ( no 'to' posted:
 * the shipping-label flow and older callers keep sending to the order's addresses ). Sends a JSON error when the dialog's
 * addresses are unusable. Callers verify the nonce first.
 *
 * @since 6.0.0
 * @return array|null array( 'to' => [], 'cc' => [], 'bcc' => [] ).
 */
function ecv2_order_email_recipients() {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- every caller verifies its nonce before calling.
	if ( ! isset( $_POST['to'] ) ) {
		return null;
	}
	$lists = array();
	foreach ( array( 'to', 'cc', 'bcc' ) as $field ) {
		$raw             = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
		$lists[ $field ] = ec_orderdisplay::clean_email_list( $raw );
		$typed           = array_filter( preg_split( '/[\s,;]+/', $raw ) );
		if ( count( $typed ) > count( $lists[ $field ] ) ) {
			wp_send_json_error( array( 'message' => __( 'One of the email addresses is not valid. Check the To, Cc and Bcc fields.', 'wp-easycart' ), 'field' => $field ) );
		}
	}
	// phpcs:enable WordPress.Security.NonceVerification.Missing
	if ( ! $lists['to'] ) {
		wp_send_json_error( array( 'message' => __( 'Enter at least one email address to send to.', 'wp-easycart' ), 'field' => 'to' ) );
	}
	return $lists;
}

add_action( 'wp_ajax_ecv2_order_resend_receipt', 'ecv2_order_resend_receipt' );
/**
 * Resends the order receipt from the V2 order details screen.
 *
 * Reuses wp_easycart_admin_orders()->resend_receipt(), which is the same sender the
 * Orders list "Resend Receipt" bulk action uses ( ec_orderdisplay::send_email_receipt ),
 * so the PDF attachment ( PRO, when PDF receipts are on ) and every email filter apply
 * exactly as they do on the original send. The send is written to the order timeline
 * the same way the order shipped email is.
 *
 * @since 6.0.0
 */
function ecv2_order_resend_receipt() {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- order_id only builds the nonce action; check_ajax_referer() verifies it on the next line.
	$order_id = ( isset( $_POST['order_id'] ) ) ? (int) $_POST['order_id'] : 0;
	check_ajax_referer( 'wp-easycart-ecv2-order-email-' . $order_id, 'wp_easycart_nonce' );
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_orders' ) ) {
		wp_send_json_error( array( 'message' => __( 'You do not have permission to send order emails.', 'wp-easycart' ) ), 403 );
	}

	global $wpdb;
	$order = ( $order_id ) ? $wpdb->get_row( $wpdb->prepare( 'SELECT order_id, user_email, email_other FROM ec_order WHERE order_id = %d', $order_id ) ) : null;
	if ( ! $order ) {
		wp_send_json_error( array( 'message' => __( 'The order could not be found.', 'wp-easycart' ) ) );
	}
	$recipients = ecv2_order_email_recipients();
	if ( null === $recipients && '' == trim( (string) $order->user_email ) ) {
		wp_send_json_error( array( 'message' => __( 'This order has no email address to send to. Add one in the Edit Order drawer first.', 'wp-easycart' ) ) );
	}

	$sent = wp_easycart_admin_orders()->resend_receipt( $order_id, $recipients );
	if ( ! $sent ) {
		wp_send_json_error( array( 'message' => __( 'The receipt could not be sent. Check Settings > Logs for the mail error.', 'wp-easycart' ) ) );
	}

	/* Sent from the send dialog: log and answer with the chosen addresses. */
	if ( null !== $recipients ) {
		wp_easycart_admin_orders::log_email_sent( $order_id, 'order-receipt-email', $recipients );
		do_action( 'wp_easycart_order_receipt_resent', $order_id );
		wp_send_json_success(
			array(
				'email'   => implode( ', ', $recipients['to'] ),
				/* translators: %s: email addresses the receipt was sent to. */
				'message' => sprintf( __( 'Order receipt sent to %s.', 'wp-easycart' ), implode( ', ', $recipients['to'] ) ),
			)
		);
	}

	/* Timeline entry ( mirrors the order shipped email log ). */
	$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log( order_id, order_log_key ) VALUES( %d, "order-receipt-email" )', $order_id ) );
	$order_log_id = $wpdb->insert_id;
	$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "email", %s )', $order_log_id, $order_id, $order->user_email ) );
	if ( '' != trim( (string) $order->email_other ) ) {
		$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "email_other", %s )', $order_log_id, $order_id, $order->email_other ) );
	}

	do_action( 'wp_easycart_order_receipt_resent', $order_id );

	wp_send_json_success(
		array(
			'email'   => $order->user_email,
			/* translators: %s: customer email address. */
			'message' => sprintf( __( 'Order receipt sent to %s.', 'wp-easycart' ), $order->user_email ),
		)
	);
}
