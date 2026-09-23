<?php
/**
 * WP EasyCart Admin Order Table - V2
 *
 * Modern order list built on wp_easycart_admin_table_v2. Mirrors the V2
 * product table architecture: health dashboard, filter drawer, table +
 * card views, floating bulk actions, toasts and undo.
 *
 * FREE scope: everything here is a redesign of existing free capabilities
 * (view, search, filter, bulk actions, status change, quick edit, print,
 * export, mark viewed). No new order-editing power is added in free.
 *
 * PRO integration points (all opt-in via hooks/filters — no PRO logic here):
 *  - 'wp_easycart_order_list_pro_enabled'      (bool)  enables PRO features
 *  - 'wp_easycart_ecv2_order_view_modes'       (array) PRO appends 'spreadsheet'
 *  - 'wp_easycart_ecv2_order_saved_views'      (array) PRO supplies saved views
 *  - 'wp_easycart_ecv2_order_health_stats'     (array) PRO appends revenue cards
 *  - 'wp_easycart_ecv2_order_spreadsheet_columns' (array) PRO column set
 *  - 'wp_easycart_admin_ecv2_order_render_modals' (action) PRO modals
 *  - 'wp_easycart_admin_order_list_columns' / '_filters' / bulk filter kept
 *    from V1 for backward compatibility with existing PRO hooks.
 *
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_order_table' ) ) :

	class wp_easycart_admin_order_table extends wp_easycart_admin_table_v2 {

		/**
		 * Health counts cache.
		 *
		 * @var array
		 */
		private $health_data = array();

		/**
		 * All order statuses, keyed once for chips + dropdowns + filters.
		 *
		 * @var array
		 */
		private $order_statuses = array();

		/**
		 * Server-vs-GMT storage offset (seconds), for local-day boundaries.
		 *
		 * @var int
		 */
		private $storage_offset = 0;

		/**
		 * Subscription the list is narrowed to ( ?subscription_id=N ), 0 when not filtering.
		 * Set by "View orders" on a subscription ( PRO ); shown as a removable chip in the toolbar.
		 *
		 * @since 6.0.0
		 * @var int
		 */
		private $subscription_filter = 0;

		/**
		 * Transient for the distinct payment gateways used by orders ( filter pills ). 1 hour.
		 *
		 * @since 6.0.0
		 */
		const GATEWAYS_TRANSIENT = 'ecv2_order_gateways';

		/**
		 * Order counts for the customers on this page: 'u' . user_id for account orders,
		 * 'e' . email for guest orders. Filled by prime_page_rows().
		 *
		 * @var array
		 */
		private $customer_counts = array();

		/* Core status ids (see ec_db_manager defaults). */
		const STATUS_SHIPPED         = 2;
		const STATUS_READY_PICKUP    = 11;
		const STATUS_REFUNDED        = 16;
		const STATUS_PARTIAL_REFUND  = 17;
		const STATUS_PICKED_UP       = 18;
		const STATUS_CANCELLED       = 19;
		const STATUS_CARD_DENIED     = 7;

		public function __construct() {
			parent::__construct();

			$now_server            = $this->wpdb->get_var( 'SELECT NOW() AS the_time' );
			$this->storage_offset  = strtotime( $now_server ) - time();
		}

		/* ------------------------------------------------------------------ */
		/* Setup                                                                */
		/* ------------------------------------------------------------------ */

		public function setup() {
			global $wpdb;

			$this->set_table( 'ec_order', 'order_id' );
			$this->set_table_id( 'ec_admin_order_list_v2' );
			if ( isset( $_GET['email_status'] ) && 'failed' === $_GET['email_status'] && class_exists( 'ec_email' ) && ec_email::tables_exist() ) {
				$this->set_custom_where( " AND ( ec_order.order_id IN ( SELECT order_id FROM ec_email_queue WHERE status IN ( 'pending', 'failed' ) ) OR ec_order.order_id IN ( SELECT order_id FROM ec_email_log WHERE status = 'failed' AND queue_id = 0 AND created_at >= DATE_SUB( NOW(), INTERVAL 30 DAY ) ) )" );
				$this->set_get_vars( array( 'email_status' ) );
			}
			/* ?subscription_id=N: only the orders a subscription created ( "View orders" on the PRO subscriptions list ). */
			$this->subscription_filter = isset( $_GET['subscription_id'] ) ? (int) $_GET['subscription_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter; the value only selects which rows are shown.
			if ( $this->subscription_filter > 0 ) {
				$this->set_custom_where( $this->custom_where . $wpdb->prepare( ' AND ec_order.subscription_id = %d', $this->subscription_filter ) );
			}
			$this->set_table_class( 'ecv2-order-table' );
			$this->set_default_sort( 'order_id', 'DESC' );
			$this->set_header( __( 'Manage Orders', 'wp-easycart' ) );
			$this->set_docs_link( 'orders', 'order-management' );
			$this->set_add_new( false, '', '' );
			$this->set_label( __( 'Order', 'wp-easycart' ), __( 'Orders', 'wp-easycart' ) );
			$this->set_join( 'LEFT JOIN ec_orderstatus ON (ec_orderstatus.status_id = ec_order.orderstatus_id)' );

			/* Table + Card in free. PRO appends 'spreadsheet' via this filter. */
			$this->set_view_modes( apply_filters( 'wp_easycart_ecv2_order_view_modes', array( 'table', 'card' ) ) );

			$this->load_order_statuses();

			/* -------------------------------------------------------------- */
			/* Columns                                                          */
			/* -------------------------------------------------------------- */
			$columns = array(
				/* Viewed dot is rendered inside the Order cell (see print_order_number). */
				array( 'select' => 'ec_order.order_id AS order_number', 'name' => 'order_number', 'orderby' => 'order_id', 'label' => __( 'Order', 'wp-easycart' ), 'format' => 'order_number', 'width' => 88 ),
				array( 'select' => 'ec_order.order_date AS order_date', 'name' => 'order_date', 'label' => __( 'Date', 'wp-easycart' ), 'format' => 'datetime', 'localize_timestamp' => true, 'width' => 120 ),
				array(
					'name'      => 'billing_name',
					'label'     => __( 'Customer', 'wp-easycart' ),
					'format'    => 'order_customer',
					'is_concat' => true,
					'concat'    => 'CONCAT( ec_order.billing_first_name, " ", ec_order.billing_last_name ) AS billing_name',
				),
				array(
					'select' => '(SELECT COUNT(*) FROM ec_orderdetail WHERE ec_orderdetail.order_id = ec_order.order_id) AS item_count',
					'name'   => 'item_count',
					'label'  => __( 'Items', 'wp-easycart' ),
					'format' => 'order_items',
					'tablet_hide' => true,
					'width'  => 84,
				),
				array( 'name' => 'grand_total', 'label' => __( 'Total', 'wp-easycart' ), 'format' => 'order_total', 'width' => 110 ),
				array( 'select' => 'ec_order.order_gateway AS payment_gateway', 'name' => 'payment_gateway', 'label' => __( 'Payment', 'wp-easycart' ), 'format' => 'payment_chip', 'laptop_hide' => true, 'width' => 140 ),
				array( 'select' => 'ec_order.orderstatus_id AS orderstatus_id', 'name' => 'orderstatus_id', 'label' => __( 'Status', 'wp-easycart' ), 'format' => 'status_chip', 'width' => 190 ),
				array( 'name' => 'tracking_number', 'label' => __( 'Fulfillment', 'wp-easycart' ), 'format' => 'fulfillment_cell', 'tablet_hide' => true, 'width' => 230 ),

				/* Hidden data columns used by cell renderers + JS. */
				array( 'select' => 'ec_order.order_viewed', 'name' => 'order_viewed', 'format' => 'hidden', 'label' => '' ),
				array( 'select' => 'ec_order.user_email', 'name' => 'user_email', 'format' => 'hidden', 'label' => '' ),
				array( 'select' => 'ec_order.user_id', 'name' => 'user_id', 'format' => 'hidden', 'label' => '' ),
				array( 'select' => 'ec_order.billing_phone', 'name' => 'billing_phone', 'format' => 'hidden', 'label' => '' ),
				array( 'select' => 'ec_order.billing_company_name', 'name' => 'billing_company_name', 'format' => 'hidden', 'label' => '' ),
				array(
					/* 6.0.0: filled per page by prime_page_rows() ( two grouped queries ) instead of a correlated COUNT(*) per row. */
					'select' => '0 AS customer_order_count',
					'name'   => 'customer_order_count',
					'format' => 'hidden',
					'label'  => '',
				),
				array(
					'select' => '(SELECT SUBSTRING( GROUP_CONCAT( CONCAT( ec_orderdetail.quantity, \'|\', REPLACE( REPLACE( ec_orderdetail.title, \'||\', \' \' ), \'|\', \' \' ) ) SEPARATOR \'||\' ), 1, 800 ) FROM ec_orderdetail WHERE ec_orderdetail.order_id = ec_order.order_id) AS items_preview',
					'name'   => 'items_preview',
					'format' => 'hidden',
					'label'  => '',
				),
				array( 'select' => 'ec_order.refund_total', 'name' => 'refund_total', 'format' => 'hidden', 'label' => '' ),
				array( 'select' => 'ec_order.promo_code', 'name' => 'promo_code', 'format' => 'hidden', 'label' => '' ),
				array( 'select' => 'ec_order.payment_method', 'name' => 'payment_method', 'format' => 'hidden', 'label' => '' ),
				array( 'select' => 'ec_order.creditcard_digits', 'name' => 'creditcard_digits', 'format' => 'hidden', 'label' => '' ),
				array( 'select' => 'ec_orderstatus.order_status AS order_status', 'name' => 'order_status', 'format' => 'hidden', 'label' => '' ),
				array( 'select' => 'ec_orderstatus.color_code AS color_code', 'name' => 'color_code', 'format' => 'hidden', 'label' => '' ),
				array( 'select' => 'ec_orderstatus.is_approved AS is_approved', 'name' => 'is_approved', 'format' => 'hidden', 'label' => '' ),
				array( 'select' => 'ec_order.shipping_carrier', 'name' => 'shipping_carrier', 'format' => 'hidden', 'label' => '' ),
				array( 'select' => 'ec_order.shipping_method', 'name' => 'shipping_method', 'format' => 'hidden', 'label' => '' ),
				array( 'select' => 'ec_order.includes_preorder_items', 'name' => 'includes_preorder_items', 'format' => 'hidden', 'label' => '' ),
				array( 'select' => 'ec_order.includes_restaurant_type', 'name' => 'includes_restaurant_type', 'format' => 'hidden', 'label' => '' ),
				array( 'select' => '( ' . self::requires_shipping_sql() . ' ) AS requires_shipping', 'name' => 'requires_shipping', 'format' => 'hidden', 'label' => '', 'orderby' => false ),
				array( 'select' => 'ec_order.pickup_date', 'name' => 'pickup_date', 'format' => 'hidden', 'label' => '' ),
				array( 'select' => 'ec_order.pickup_time', 'name' => 'pickup_time', 'format' => 'hidden', 'label' => '' ),
				array( 'select' => 'ec_order.subscription_id', 'name' => 'subscription_id', 'format' => 'hidden', 'label' => '' ),
				array( 'select' => 'ec_order.order_notes', 'name' => 'order_notes', 'format' => 'hidden', 'label' => '' ),
			);
			$this->set_list_columns( apply_filters( 'wp_easycart_admin_order_list_columns', $columns ) );

			/* -------------------------------------------------------------- */
			/* Filters                                                          */
			/* -------------------------------------------------------------- */
			$status_options = array();
			foreach ( $this->order_statuses as $status ) {
				$status_options[] = (object) array( 'value' => $status->status_id, 'label' => $status->order_status );
			}

			/* DISTINCT over every order is a full scan: keep the gateway list for an hour ( see GATEWAYS_TRANSIENT ). */
			$gateway_values = get_transient( self::GATEWAYS_TRANSIENT );
			if ( ! is_array( $gateway_values ) ) {
				$gateway_values = (array) $wpdb->get_col( "SELECT DISTINCT order_gateway FROM ec_order WHERE order_gateway != '' ORDER BY order_gateway ASC" );
				set_transient( self::GATEWAYS_TRANSIENT, $gateway_values, HOUR_IN_SECONDS );
			}
			$gateways = array();
			foreach ( $gateway_values as $gateway_value ) {
				$gateways[] = (object) array( 'value' => $gateway_value, 'label' => $this->gateway_label( $gateway_value ) );
			}

			/*
			 * 6.0.0: product and customer filters are typeaheads ( ec_admin_ajax_ecv2_product_search /
			 * ecv2_order_customer_search ); only the currently selected choice is printed so its label shows.
			 */
			$products = array();
			$selected_product = $this->filter_value( 4 );
			if ( '' !== $selected_product ) {
				$product_label = ( (int) $selected_product > 0 ) ? $wpdb->get_var( $wpdb->prepare( 'SELECT title FROM ec_product WHERE product_id = %d', (int) $selected_product ) ) : null;
				$products[] = (object) array( 'value' => $selected_product, 'label' => ( null !== $product_label ) ? wp_unslash( $product_label ) : $selected_product );
			}
			$users = array();
			$selected_user = $this->filter_value( 5 );
			if ( '' !== $selected_user ) {
				$user_label = ( (int) $selected_user > 0 ) ? $wpdb->get_var( $wpdb->prepare( "SELECT CONCAT( last_name, ', ', first_name ) FROM ec_user WHERE user_id = %d", (int) $selected_user ) ) : null;
				if ( null === $user_label ) {
					$user_label = ( '0' === (string) $selected_user ) ? __( 'Guest', 'wp-easycart' ) : $selected_user;
				}
				$users[] = (object) array( 'value' => $selected_user, 'label' => wp_unslash( $user_label ) );
			}

			$filters = array(
				array(
					'data'  => $status_options,
					'label' => __( 'Order Status', 'wp-easycart' ),
					'type'  => 'select',
					'where' => 'ec_order.orderstatus_id = %s',
				),
				array(
					'data'  => array(
						(object) array( 'value' => 'today', 'label' => __( 'Today', 'wp-easycart' ), 'icon' => 'clock' ),
						(object) array( 'value' => 'yesterday', 'label' => __( 'Yesterday', 'wp-easycart' ) ),
						(object) array( 'value' => 'last7', 'label' => __( 'Last 7 Days', 'wp-easycart' ) ),
						(object) array( 'value' => 'last30', 'label' => __( 'Last 30 Days', 'wp-easycart' ) ),
						(object) array( 'value' => 'month', 'label' => __( 'This Month', 'wp-easycart' ) ),
						(object) array( 'value' => 'year', 'label' => __( 'This Year', 'wp-easycart' ) ),
					),
					'label' => __( 'Order Date', 'wp-easycart' ),
					'type'  => 'pills',
					'where_callback' => true,
				),
				array(
					'data'  => array(
						(object) array( 'value' => 'unfulfilled', 'label' => __( 'Awaiting Fulfillment', 'wp-easycart' ), 'icon' => 'warning' ),
						(object) array( 'value' => 'fulfilled', 'label' => __( 'Fulfilled', 'wp-easycart' ), 'icon' => 'yes-alt' ),
						(object) array( 'value' => 'pickup', 'label' => __( 'Pickup', 'wp-easycart' ), 'icon' => 'store' ),
						(object) array( 'value' => 'no_shipping', 'label' => __( 'No Shipping Needed', 'wp-easycart' ), 'icon' => 'download' ),
					),
					'label' => __( 'Fulfillment', 'wp-easycart' ),
					'type'  => 'pills',
					'where_callback' => true,
				),
				array(
					'data'  => $gateways,
					'label' => __( 'Payment Method', 'wp-easycart' ),
					'type'  => 'pills',
					'where' => 'ec_order.order_gateway = %s',
				),
				array(
					'data'   => $products,
					'label'  => __( 'Purchased Product', 'wp-easycart' ),
					'type'   => 'select',
					'ajax'   => array( 'action' => 'ec_admin_ajax_ecv2_product_search' ),
					'where'  => 'ec_order.order_id IN ( SELECT ec_orderdetail.order_id FROM ec_orderdetail WHERE ec_orderdetail.product_id = %s )',
					'where2' => 'ec_order.order_id IN ( SELECT ec_orderdetail.order_id FROM ec_orderdetail WHERE ec_orderdetail.model_number = %s )',
				),
				array(
					'data'   => $users,
					'label'  => __( 'By Customer', 'wp-easycart' ),
					'type'   => 'select',
					'ajax'   => array( 'action' => 'ecv2_order_customer_search', 'nonce' => wp_create_nonce( 'wp-easycart-ecv2-order-customer-search' ), 'min' => 2 ),
					'where'  => 'ec_order.user_id = %d',
					'where2' => '( ec_order.user_id = 0 AND ec_order.user_email <> \'\' AND ec_order.user_email = ( SELECT email FROM ec_user WHERE ec_user.user_id = %d ) )',
				),
			);
			/* V1-compatible filter hook preserved so existing PRO filters (locations) still attach. */
			$this->set_filters( apply_filters( 'wp_easycart_admin_order_list_filters', $filters ) );

			/* -------------------------------------------------------------- */
			/* Search                                                           */
			/* -------------------------------------------------------------- */
			$this->set_search_columns( array(
				'ec_order.order_id',
				'ec_order.user_email',
				'ec_order.billing_first_name',
				'ec_order.billing_last_name',
				'ec_order.shipping_first_name',
				'ec_order.shipping_last_name',
				'ec_orderstatus.order_status',
				'ec_order.billing_company_name',
				'ec_order.shipping_company_name',
				'ec_order.tracking_number',
				'ec_order.gateway_transaction_id',
				'ec_order.billing_phone',
			) );

			/* -------------------------------------------------------------- */
			/* Bulk actions — exact V1 contract (GET form handlers unchanged).  */
			/* -------------------------------------------------------------- */
			$order_status_opts = array();
			foreach ( $this->order_statuses as $status ) {
				$order_status_opts[] = (object) array( 'value' => $status->status_id, 'label' => $status->order_status );
			}
			$this->set_bulk_actions( apply_filters( 'wp_easycart_admin_bulk_order_options', array(
				array( 'name' => 'delete-order', 'label' => __( 'Delete', 'wp-easycart' ) ),
				array( 'name' => 'resend-email', 'label' => __( 'Resend Email Receipt', 'wp-easycart' ) ),
				array( 'name' => 'print-receipt', 'label' => __( 'Print Receipt', 'wp-easycart' ) ),
				array( 'name' => 'print-packing-slip', 'label' => __( 'Print Packing Slip', 'wp-easycart' ) ),
				array( 'name' => 'send-shipped-email', 'label' => __( 'Send Order Shipped Email', 'wp-easycart' ) ),
				array(
					'name'  => 'change-order-status',
					'label' => __( 'Change Order Status', 'wp-easycart' ),
					'alt'   => array( 'id' => 'bulk_order_status', 'options' => $order_status_opts ),
				),
				array( 'name' => 'export-orders-csv', 'label' => __( 'Export Selected CSV', 'wp-easycart' ) ),
				array( 'name' => 'export-orders-csv-all', 'label' => __( 'Export All CSV', 'wp-easycart' ) ),
				array( 'name' => 'mark-orders-viewed', 'label' => __( 'Mark Selected Viewed', 'wp-easycart' ) ),
				array( 'name' => 'mark-orders-not-viewed', 'label' => __( 'Mark Selected Not Viewed', 'wp-easycart' ) ),
				array( 'name' => 'mark-all-orders-viewed', 'label' => __( 'Mark All Viewed', 'wp-easycart' ) ),
				array( 'name' => 'mark-all-orders-not-viewed', 'label' => __( 'Mark All Not Viewed', 'wp-easycart' ) ),
			) ) );

			/* Hidden input inside the GET form so the status modal can populate it. */
			$this->set_bulk_action_hidden_variables( array(
				array( 'name' => 'bulk_order_status', 'label' => '' ),
			) );

			/* -------------------------------------------------------------- */
			/* Row menu (3-dot)                                                 */
			/* -------------------------------------------------------------- */
			$row_actions = array(
				array( 'label' => __( 'View / Edit Order', 'wp-easycart' ), 'name' => 'edit', 'icon' => 'visibility', 'action' => 'edit' ),
				array( 'label' => __( 'Quick Edit', 'wp-easycart' ), 'name' => 'quick-edit', 'icon' => 'welcome-write-blog', 'href' => '#', 'onclick' => 'wp_easycart_open_quick_edit( \'order\', \'{id}\' ); return false;' ),
				array( 'label' => __( 'Print Receipt', 'wp-easycart' ), 'name' => 'print-receipt', 'icon' => 'media-text', 'href' => $this->bulk_get_link( 'print-receipt' ), 'target' => '_blank' ),
				array( 'label' => __( 'Print Packing Slip', 'wp-easycart' ), 'name' => 'print-packing-slip', 'icon' => 'clipboard', 'href' => $this->bulk_get_link( 'print-packing-slip' ), 'target' => '_blank' ),
				array( 'label' => __( 'Resend Receipt Email', 'wp-easycart' ), 'name' => 'resend-email', 'icon' => 'email-alt', 'href' => $this->bulk_get_link( 'resend-email' ) ),
				array( 'label' => __( 'Duplicate', 'wp-easycart' ), 'name' => 'duplicate', 'icon' => 'admin-page', 'href' => '#', 'onclick' => 'wp_easycart_open_order_duplicate( \'{id}\' ); return false;' ),
				array( 'label' => __( 'Delete', 'wp-easycart' ), 'name' => 'delete', 'icon' => 'trash', 'action' => 'delete-order', 'danger' => true, 'confirm' => true, 'confirm_text' => __( 'The order, its line items and its download links are removed. You can put it back for 15 minutes afterwards.', 'wp-easycart' ) ),
			);
			$this->set_row_menu_actions( apply_filters( 'wp_easycart_ecv2_order_row_menu_actions', $row_actions ) );

			/* Spreadsheet columns come from PRO; empty in free ( view mode absent anyway ). */
			$this->set_spreadsheet_columns( apply_filters( 'wp_easycart_ecv2_order_spreadsheet_columns', array() ) );

			/* -------------------------------------------------------------- */
			/* Health dashboard                                                 */
			/* -------------------------------------------------------------- */
			$this->compute_health_data();
			/*
			 * Two groups: 'flow' = where orders sit in the pipeline (actionable filters),
			 * 'period' = recent activity. PRO adds revenue as 'sub' values on the period
			 * tiles rather than extra cards, keeping the strip compact.
			 */
			$health_stats = array(
				array( 'label' => __( 'All', 'wp-easycart' ), 'value' => $this->health_data['total'], 'filter_value' => '', 'color' => 'default', 'group' => 'flow' ),
				array( 'label' => __( 'New', 'wp-easycart' ), 'value' => $this->health_data['new'], 'filter_value' => 'new', 'color' => 'cyan', 'group' => 'flow' ),
				array( 'label' => __( 'Awaiting', 'wp-easycart' ), 'value' => $this->health_data['awaiting'], 'filter_value' => 'awaiting', 'color' => 'amber', 'group' => 'flow' ),
				array( 'label' => __( 'Shipped', 'wp-easycart' ), 'value' => $this->health_data['shipped'], 'filter_value' => 'shipped', 'color' => 'green', 'group' => 'flow' ),
				array( 'label' => __( 'Refunded', 'wp-easycart' ), 'value' => $this->health_data['refunded'], 'filter_value' => 'refunded', 'color' => 'red', 'group' => 'flow' ),
			);
			if ( $this->health_data['pickup_ready'] > 0 ) {
				$health_stats[] = array( 'label' => __( 'Pickup', 'wp-easycart' ), 'value' => $this->health_data['pickup_ready'], 'filter_value' => 'pickup_ready', 'color' => 'cyan', 'group' => 'flow' );
			}
			if ( $this->health_data['preorders'] > 0 ) {
				$health_stats[] = array( 'label' => __( 'Preorders', 'wp-easycart' ), 'value' => $this->health_data['preorders'], 'filter_value' => 'preorders', 'color' => 'gray', 'group' => 'flow' );
			}
			$health_stats[] = array( 'label' => __( 'Today', 'wp-easycart' ), 'value' => $this->health_data['today'], 'filter_value' => 'today', 'color' => 'default', 'group' => 'period' );
			$health_stats[] = array( 'label' => __( 'Last 7 days', 'wp-easycart' ), 'value' => $this->health_data['last7'], 'filter_value' => 'last7', 'color' => 'default', 'group' => 'period' );
			/* PRO adds revenue subs + AOV here. */
			$this->set_health_stats( apply_filters( 'wp_easycart_ecv2_order_health_stats', $health_stats, $this->health_data ) );
		}

		/* ------------------------------------------------------------------ */
		/* Data helpers                                                         */
		/* ------------------------------------------------------------------ */

		private function load_order_statuses() {
			$this->order_statuses = $this->wpdb->get_results( 'SELECT status_id, order_status, is_approved, color_code FROM ec_orderstatus ORDER BY status_id ASC' );
		}

		/**
		 * Fetch the page, then load everything that used to cost a query per row in one pass:
		 * the repeat-customer counts ( two grouped queries ) and the email delivery chips.
		 *
		 * @since 6.0.0
		 */
		protected function get_data() {
			parent::get_data();
			$this->prime_page_rows();
		}

		private function prime_page_rows() {
			$user_ids  = array();
			$emails    = array();
			$order_ids = array();
			foreach ( (array) $this->results as $row ) {
				$order_ids[] = (int) $row->order_id;
				if ( isset( $row->user_id ) && (int) $row->user_id > 0 ) {
					$user_ids[] = (int) $row->user_id;
				} elseif ( isset( $row->user_email ) && '' !== (string) $row->user_email ) {
					$emails[] = (string) $row->user_email;
				}
			}
			/* Skip customers already counted ( get_data() re-runs itself when the page number overflows ). */
			$counts   = $this->customer_counts;
			$user_ids = array_values( array_filter( array_unique( $user_ids ), function( $id ) use ( $counts ) { return ! array_key_exists( 'u' . $id, $counts ); } ) );
			$emails   = array_values( array_filter( array_unique( $emails ), function( $email ) use ( $counts ) { return ! array_key_exists( 'e' . strtolower( $email ), $counts ); } ) );

			if ( ! empty( $user_ids ) ) {
				$rows = $this->wpdb->get_results( 'SELECT user_id, COUNT(*) AS n FROM ec_order WHERE user_id IN ( ' . implode( ',', $user_ids ) . ' ) GROUP BY user_id' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- IN list is an implode of (int)-cast ids.
				foreach ( (array) $rows as $r ) {
					$this->customer_counts[ 'u' . (int) $r->user_id ] = (int) $r->n;
				}
			}
			if ( ! empty( $emails ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $emails ), '%s' ) );
				$rows = $this->wpdb->get_results( $this->wpdb->prepare( 'SELECT user_email, COUNT(*) AS n FROM ec_order WHERE user_email IN ( ' . $placeholders . ' ) GROUP BY user_email', $emails ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $placeholders is a list of %s markers; the emails are the prepare() arguments.
				foreach ( (array) $rows as $r ) {
					$this->customer_counts[ 'e' . strtolower( (string) $r->user_email ) ] = (int) $r->n;
				}
			}
			foreach ( (array) $this->results as $row ) {
				$row->customer_order_count = $this->customer_order_count( $row );
			}

			if ( ! empty( $order_ids ) && class_exists( 'wp_easycart_admin_email_health' ) && method_exists( 'wp_easycart_admin_email_health', 'prime' ) ) {
				wp_easycart_admin_email_health::prime( $order_ids );
			}
		}

		/**
		 * Orders placed by this row's customer: the primed page map, or one lookup for a row
		 * rendered outside get_data() ( e.g. PRO single-row refreshes ).
		 *
		 * @since 6.0.0
		 *
		 * @param object $result Order row.
		 * @return int
		 */
		private function customer_order_count( $result ) {
			$user_id = isset( $result->user_id ) ? (int) $result->user_id : 0;
			$email   = isset( $result->user_email ) ? (string) $result->user_email : '';
			if ( $user_id > 0 ) {
				$key = 'u' . $user_id;
				if ( ! array_key_exists( $key, $this->customer_counts ) ) {
					$this->customer_counts[ $key ] = (int) $this->wpdb->get_var( $this->wpdb->prepare( 'SELECT COUNT(*) FROM ec_order WHERE user_id = %d', $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared through $this->wpdb->prepare(); the sniff only recognises the global $wpdb.
				}
				return (int) $this->customer_counts[ $key ];
			}
			if ( '' === $email ) {
				return 0;
			}
			$key = 'e' . strtolower( $email );
			if ( ! array_key_exists( $key, $this->customer_counts ) ) {
				$this->customer_counts[ $key ] = (int) $this->wpdb->get_var( $this->wpdb->prepare( 'SELECT COUNT(*) FROM ec_order WHERE user_email = %s', $email ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared through $this->wpdb->prepare(); the sniff only recognises the global $wpdb.
			}
			return (int) $this->customer_counts[ $key ];
		}

		public function get_order_statuses() {
			return $this->order_statuses;
		}

		public static function gateway_label( $gateway ) {
			$labels = array(
				'stripe'          => 'Stripe',
				'stripe_connect'  => 'Stripe',
				'paypal'          => 'PayPal',
				'paypal-express'  => 'PayPal Express',
				'square'          => 'Square',
				'authorize'       => 'Authorize.Net',
				'affirm'          => 'Affirm',
				'nmi'             => 'NMI',
				'intuit'          => 'Intuit',
				'manual'          => __( 'Manual', 'wp-easycart' ),
				'third_party'     => __( 'Third Party', 'wp-easycart' ),
			);
			return isset( $labels[ $gateway ] ) ? $labels[ $gateway ] : ucwords( str_replace( array( '-', '_' ), ' ', $gateway ) );
		}

		/**
		 * DB datetime string for the start of a local day, $days_ago days back.
		 * ec_order.order_date is stored in the DB server timezone.
		 */
		private function db_local_day_start( $days_ago = 0 ) {
			$local_offset   = get_option( 'gmt_offset' ) * 3600;
			$local_now      = time() + $local_offset;
			$local_day_gmt  = strtotime( gmdate( 'Y-m-d 00:00:00', $local_now ) ) - $local_offset - ( $days_ago * DAY_IN_SECONDS );
			return gmdate( 'Y-m-d H:i:s', $local_day_gmt + $this->storage_offset );
		}

		private function db_local_month_start() {
			$local_offset  = get_option( 'gmt_offset' ) * 3600;
			$local_now     = time() + $local_offset;
			$month_gmt     = strtotime( gmdate( 'Y-m-01 00:00:00', $local_now ) ) - $local_offset;
			return gmdate( 'Y-m-d H:i:s', $month_gmt + $this->storage_offset );
		}

		private function db_local_year_start() {
			$local_offset  = get_option( 'gmt_offset' ) * 3600;
			$local_now     = time() + $local_offset;
			$year_gmt      = strtotime( gmdate( 'Y-01-01 00:00:00', $local_now ) ) - $local_offset;
			return gmdate( 'Y-m-d H:i:s', $year_gmt + $this->storage_offset );
		}

		public static function unfulfilled_where() {
			return "( ec_orderstatus.is_approved = 1 AND ec_order.tracking_number = '' AND ec_order.orderstatus_id NOT IN ( " . self::STATUS_SHIPPED . ', ' . self::STATUS_READY_PICKUP . ', ' . self::STATUS_REFUNDED . ', ' . self::STATUS_PICKED_UP . ', ' . self::STATUS_CANCELLED . ' ) AND ' . self::requires_shipping_sql() . ' )';
		}

		/**
		 * SQL that is true when the order has at least one line that has to be shipped: not a download, not a gift
		 * card, and a product with shipping enabled ( the ec_orderdetail snapshot, so later product edits do not
		 * change history ). Orders with nothing to ship ( downloads, gift cards, subscriptions / services, products
		 * with shipping disabled ) are fulfilled the moment the payment is approved.
		 *
		 * @since 6.0.0
		 * @param string $order_alias Table / alias holding order_id, `ec_order` in the list query.
		 * @return string
		 */
		public static function requires_shipping_sql( $order_alias = 'ec_order' ) {
			return 'EXISTS ( SELECT 1 FROM ec_orderdetail od_ship WHERE od_ship.order_id = ' . $order_alias . '.order_id AND od_ship.is_shippable = 1 AND od_ship.is_download = 0 AND od_ship.is_giftcard = 0 )';
		}

		/**
		 * Does this order have anything to ship? Cached per request.
		 *
		 * @since 6.0.0
		 * @param int $order_id Order.
		 * @return bool
		 */
		public static function requires_shipping( $order_id ) {
			static $cache = array();
			global $wpdb;
			$order_id = (int) $order_id;
			if ( ! isset( $cache[ $order_id ] ) ) {
				$cache[ $order_id ] = (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_orderdetail WHERE order_id = %d AND is_shippable = 1 AND is_download = 0 AND is_giftcard = 0', $order_id ) );
			}
			return $cache[ $order_id ];
		}

		/** Approved, nothing to ship, and not refunded / cancelled: the "No shipping" fulfillment preset. @since 6.0.0 */
		public static function no_shipping_where() {
			return "( ec_orderstatus.is_approved = 1 AND ec_order.tracking_number = '' AND ec_order.includes_restaurant_type = 0 AND ec_order.orderstatus_id NOT IN ( " . self::STATUS_SHIPPED . ', ' . self::STATUS_READY_PICKUP . ', ' . self::STATUS_REFUNDED . ', ' . self::STATUS_PICKED_UP . ', ' . self::STATUS_CANCELLED . ' ) AND NOT ' . self::requires_shipping_sql() . ' )';
		}

		private function compute_health_data() {
			global $wpdb;

			$today_start = $this->db_local_day_start( 0 );
			$week_start  = $this->db_local_day_start( 6 );

			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- unfulfilled_where() is a static fragment built from class STATUS_* constants.
			$row = $wpdb->get_row( $wpdb->prepare(
				'SELECT
					COUNT(*) AS total,
					SUM( CASE WHEN ec_order.order_viewed = 0 THEN 1 ELSE 0 END ) AS new_orders,
					SUM( CASE WHEN ' . self::unfulfilled_where() . ' THEN 1 ELSE 0 END ) AS awaiting,
					SUM( CASE WHEN ( ec_order.orderstatus_id = %d OR ec_order.tracking_number != \'\' ) THEN 1 ELSE 0 END ) AS shipped,
					SUM( CASE WHEN ec_order.orderstatus_id IN ( %d, %d ) THEN 1 ELSE 0 END ) AS refunded,
					SUM( CASE WHEN ec_order.order_date >= %s THEN 1 ELSE 0 END ) AS today,
					SUM( CASE WHEN ec_order.order_date >= %s THEN 1 ELSE 0 END ) AS last7,
					SUM( CASE WHEN ec_order.orderstatus_id = %d THEN 1 ELSE 0 END ) AS pickup_ready,
					SUM( CASE WHEN ec_order.includes_preorder_items = 1 THEN 1 ELSE 0 END ) AS preorders
				FROM ec_order
				LEFT JOIN ec_orderstatus ON ( ec_orderstatus.status_id = ec_order.orderstatus_id )',
				self::STATUS_SHIPPED,
				self::STATUS_REFUNDED,
				self::STATUS_PARTIAL_REFUND,
				$today_start,
				$week_start,
				self::STATUS_READY_PICKUP
			) );
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

			$this->health_data = array(
				'total'        => $row ? (int) $row->total : 0,
				'new'          => $row ? (int) $row->new_orders : 0,
				'awaiting'     => $row ? (int) $row->awaiting : 0,
				'shipped'      => $row ? (int) $row->shipped : 0,
				'refunded'     => $row ? (int) $row->refunded : 0,
				'today'        => $row ? (int) $row->today : 0,
				'last7'        => $row ? (int) $row->last7 : 0,
				'pickup_ready' => $row ? (int) $row->pickup_ready : 0,
				'preorders'    => $row ? (int) $row->preorders : 0,
				/* Raw boundaries — handy for PRO revenue cards. */
				'today_start'  => $today_start,
				'week_start'   => $week_start,
				'month_start'  => $this->db_local_month_start(),
			);
		}

		protected function get_health_filter_where( $filter_key ) {
			switch ( $filter_key ) {
				case 'new':
					return 'ec_order.order_viewed = 0';
				case 'awaiting':
					return self::unfulfilled_where();
				case 'shipped':
					return '( ec_order.orderstatus_id = ' . self::STATUS_SHIPPED . " OR ec_order.tracking_number != '' )";
				case 'refunded':
					return 'ec_order.orderstatus_id IN ( ' . self::STATUS_REFUNDED . ', ' . self::STATUS_PARTIAL_REFUND . ' )';
				case 'today':
					return $this->wpdb->prepare( 'ec_order.order_date >= %s', $this->db_local_day_start( 0 ) );
				case 'last7':
					return $this->wpdb->prepare( 'ec_order.order_date >= %s', $this->db_local_day_start( 6 ) );
				case 'last30':
					return $this->wpdb->prepare( 'ec_order.order_date >= %s', $this->db_local_day_start( 29 ) );
				case 'pickup_ready':
					return 'ec_order.orderstatus_id = ' . self::STATUS_READY_PICKUP;
				case 'preorders':
					return 'ec_order.includes_preorder_items = 1';
			}
			return apply_filters( 'wp_easycart_ecv2_order_health_filter_where', '', $filter_key, $this );
		}

		protected function get_filter_callback_where( $filter_index, $value ) {
			$filter = isset( $this->filters[ $filter_index ] ) ? $this->filters[ $filter_index ] : false;
			if ( ! $filter ) {
				return '';
			}

			/* Order Date presets. */
			if ( __( 'Order Date', 'wp-easycart' ) === $filter['label'] ) {
				switch ( $value ) {
					case 'today':
						return $this->wpdb->prepare( 'ec_order.order_date >= %s', $this->db_local_day_start( 0 ) );
					case 'yesterday':
						return $this->wpdb->prepare( 'ec_order.order_date >= %s AND ec_order.order_date < %s', $this->db_local_day_start( 1 ), $this->db_local_day_start( 0 ) );
					case 'last7':
						return $this->wpdb->prepare( 'ec_order.order_date >= %s', $this->db_local_day_start( 6 ) );
					case 'last30':
						return $this->wpdb->prepare( 'ec_order.order_date >= %s', $this->db_local_day_start( 29 ) );
					case 'month':
						return $this->wpdb->prepare( 'ec_order.order_date >= %s', $this->db_local_month_start() );
					case 'year':
						return $this->wpdb->prepare( 'ec_order.order_date >= %s', $this->db_local_year_start() );
				}
			}

			/* Fulfillment presets. */
			if ( __( 'Fulfillment', 'wp-easycart' ) === $filter['label'] ) {
				switch ( $value ) {
					case 'unfulfilled':
						return self::unfulfilled_where();
					case 'fulfilled':
						return '( ec_order.orderstatus_id IN ( ' . self::STATUS_SHIPPED . ', ' . self::STATUS_PICKED_UP . " ) OR ec_order.tracking_number != '' )";
					case 'pickup':
						return '( ec_order.includes_restaurant_type = 1 OR ec_order.orderstatus_id IN ( ' . self::STATUS_READY_PICKUP . ', ' . self::STATUS_PICKED_UP . ' )' . self::local_pickup_where() . ' )';
					case 'no_shipping':
						return self::no_shipping_where();
				}
			}

			return apply_filters( 'wp_easycart_ecv2_order_filter_callback_where', '', $filter_index, $value, $this );
		}

		/* ------------------------------------------------------------------ */
		/* Rendering                                                            */
		/* ------------------------------------------------------------------ */

		/**
		 * Direct link that reuses the V1 bulk GET handlers for one order.
		 */
		private function bulk_get_link( $action ) {
			return 'admin.php?page=wp-easycart-orders&subpage=orders&bulk={id}&ec_admin_form_action=' . $action . '&wp_easycart_nonce=' . wp_create_nonce( 'wp-easycart-bulk-orders' );
		}

		/**
		 * Saved views bar above the toolbar. Free renders a locked teaser;
		 * PRO supplies real views via 'wp_easycart_ecv2_order_saved_views'.
		 */
		protected function print_toolbar() {
			$this->print_saved_views_bar();
			parent::print_toolbar();
		}

		private function print_saved_views_bar() {
			$gate = ecv2_get_order_pro_gate();

			echo '<div class="ecv2-order-views-bar" id="ecv2-order-views-bar">';
			echo '<span class="ecv2-order-views-icon dashicons dashicons-star-filled"></span>';

			/* "All Orders" always present — clears filters + search. */
			$has_active = ( isset( $_GET['health_filter'] ) && '' !== $_GET['health_filter'] ) || ( isset( $_GET['s'] ) && '' !== $_GET['s'] );
			for ( $i = 0; $i < count( $this->filters ); $i++ ) {
				if ( isset( $_GET[ 'filter_' . $i ] ) && '' !== $_GET[ 'filter_' . $i ] ) {
					$has_active = true;
				}
			}
			echo '<button type="button" class="ecv2-order-view-pill' . ( $has_active ? '' : ' ecv2-order-view-pill-active' ) . '" onclick="ecv2_clear_filters();">' . esc_html__( 'All Orders', 'wp-easycart' ) . '</button>';

			if ( 'enabled' === $gate['state'] ) {
				$views = apply_filters( 'wp_easycart_ecv2_order_saved_views', array() );
				foreach ( $views as $view ) {
					echo '<span class="ecv2-order-view-pill-wrap">';
					echo '<button type="button" class="ecv2-order-view-pill" data-view-id="' . esc_attr( $view['id'] ) . '" data-view-params="' . esc_attr( $view['params'] ) . '" onclick="ecv2_order_apply_saved_view( this );">' . esc_html( $view['label'] ) . '</button>';
					echo '<button type="button" class="ecv2-order-view-delete" data-view-id="' . esc_attr( $view['id'] ) . '" title="' . esc_attr__( 'Delete view', 'wp-easycart' ) . '" onclick="ecv2_order_delete_saved_view( this );">&times;</button>';
					echo '</span>';
				}
				echo '<button type="button" class="ecv2-order-view-pill ecv2-order-view-save" onclick="ecv2_order_save_current_view();"><span class="dashicons dashicons-plus-alt2"></span> ' . esc_html__( 'Save Current View', 'wp-easycart' ) . '</button>';
			} else {
				echo '<button type="button" class="ecv2-order-view-pill ecv2-order-view-locked" onclick="return wpec_gate.locked_action( ecv2_lang.order_pro_gate );">';
				echo '<span class="dashicons dashicons-lock"></span> ' . esc_html__( 'Saved Views', 'wp-easycart' );
				echo '</button>';
			}

			echo '</div>';
		}

		/**
		 * Carry the subscription filter through form submits ( search, drawer, paging ). The input id follows the
		 * ecv2-filter-input-* pattern so the shared tag-remove and clear-all JS reset it like any other filter.
		 *
		 * @since 6.0.0
		 */
		protected function print_hidden_fields() {
			parent::print_hidden_fields();
			if ( $this->subscription_filter > 0 ) {
				echo '<input type="hidden" name="subscription_id" id="ecv2-filter-input-subscription_id" value="' . esc_attr( $this->subscription_filter ) . '" />';
			}
		}

		/**
		 * Active-filter chip for the subscription filter, next to the regular filter tags.
		 *
		 * @since 6.0.0
		 */
		protected function print_filter_button() {
			parent::print_filter_button();
			if ( $this->subscription_filter > 0 ) {
				echo '<div class="ecv2-active-filter-tags">';
				echo '<span class="ecv2-active-tag">';
				/* translators: %d: subscription id. */
				echo esc_html( sprintf( __( 'Subscription #%d', 'wp-easycart' ), $this->subscription_filter ) );
				echo '<button type="button" class="ecv2-active-tag-remove" data-filter="subscription_id" title="' . esc_attr__( 'Remove filter', 'wp-easycart' ) . '">&times;</button>';
				echo '</span>';
				echo '</div>';
			}
		}

		/**
		 * Add a locked spreadsheet toggle when PRO is not enabled.
		 */
		protected function print_view_toggle() {
			if ( in_array( 'spreadsheet', $this->view_modes, true ) ) {
				parent::print_view_toggle();
				return;
			}
			echo '<div class="ecv2-view-toggle">';
			echo '<button type="button" class="ecv2-view-btn' . ( 'table' === $this->current_view_mode ? ' ecv2-view-btn-active' : '' ) . '" data-mode="table" title="' . esc_attr__( 'Table View', 'wp-easycart' ) . '"><span class="dashicons dashicons-list-view"></span></button>';
			echo '<button type="button" class="ecv2-view-btn' . ( 'card' === $this->current_view_mode ? ' ecv2-view-btn-active' : '' ) . '" data-mode="card" title="' . esc_attr__( 'Card View', 'wp-easycart' ) . '"><span class="dashicons dashicons-grid-view"></span></button>';
			/* translators: %s: plan name, Pro/Premium, Pro or Premium. */
			echo '<button type="button" class="ecv2-view-btn ecv2-view-btn-locked" title="' . esc_attr( sprintf( __( 'Spreadsheet View (%s)', 'wp-easycart' ), ( class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::plan_name() : __( 'Pro/Premium', 'wp-easycart' ) ) ) ) . '" onclick="return wpec_gate.locked_action( ecv2_lang.order_pro_gate );"><span class="dashicons dashicons-editor-table"></span><span class="dashicons dashicons-lock ecv2-view-btn-lock"></span></button>';
			echo '</div>';
		}

		protected function print_table_row( $result ) {
			$row_id = $result->{ $this->key };

			$extra_class = '';
			if ( isset( $result->order_viewed ) && ! $result->order_viewed ) {
				$extra_class .= ' ecv2-row-unviewed';
			}
			if ( isset( $result->orderstatus_id ) && in_array( (int) $result->orderstatus_id, array( self::STATUS_CANCELLED, self::STATUS_CARD_DENIED ), true ) ) {
				$extra_class .= ' ecv2-row-cancelled';
			}
			if ( isset( $result->orderstatus_id ) && self::STATUS_REFUNDED === (int) $result->orderstatus_id ) {
				$extra_class .= ' ecv2-row-refunded';
			}

			echo '<tr class="ecv2-row' . esc_attr( $extra_class ) . '" data-id="' . esc_attr( $row_id ) . '">';
			echo '<td class="ecv2-col-check"><input type="checkbox" name="bulk[]" value="' . esc_attr( $row_id ) . '" class="ecv2-row-check" /></td>';

			foreach ( $this->list_columns as $col ) {
				if ( isset( $col['format'] ) && 'hidden' === $col['format'] ) {
					continue;
				}
				$extra_classes = '';
				if ( isset( $col['tablet_hide'] ) && $col['tablet_hide'] ) {
					$extra_classes .= ' ecv2-hide-tablet';
				}
				if ( isset( $col['laptop_hide'] ) && $col['laptop_hide'] ) {
					$extra_classes .= ' ecv2-hide-laptop';
				}
				echo '<td class="ecv2-cell ecv2-cell-' . esc_attr( $col['name'] ) . $extra_classes . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $extra_classes only ever holds literal class names set above.
				$this->print_cell_content( $result, $col );
				echo '</td>';
			}

			echo '<td class="ecv2-col-actions">';
			echo '<div class="ecv2-order-actions-wrap">';
			/* PRO per-row tools (note, timeline) render here, left of the menu. */
			do_action( 'wp_easycart_ecv2_order_row_actions_start', $result, ecv2_get_order_pro_gate() );
			$this->print_row_actions( $result );
			echo '</div>';
			echo '</td>';
			echo '</tr>';
		}

		protected function print_cell_content( $result, $col ) {
			switch ( $col['format'] ) {
				case 'order_number':
					$this->print_order_number( $result );
					break;
				case 'order_customer':
					$this->print_order_customer( $result );
					break;
				case 'order_items':
					$this->print_order_items( $result );
					break;
				case 'order_total':
					$this->print_order_total( $result );
					break;
				case 'payment_chip':
					$this->print_payment_chip( $result );
					break;
				case 'status_chip':
					$this->print_status_chip( $result );
					break;
				case 'fulfillment_cell':
					$this->print_fulfillment_cell( $result );
					break;
				default:
					parent::print_cell_content( $result, $col );
					break;
			}
		}

		private function print_viewed_dot( $result ) {
			$order_id = (int) $result->order_id;
			$viewed   = ! empty( $result->order_viewed );
			$nonce    = wp_create_nonce( 'wp-easycart-ecv2-order-viewed-' . $order_id );
			$title    = $viewed ? __( 'Viewed — click to mark as new', 'wp-easycart' ) : __( 'New order — click to mark viewed', 'wp-easycart' );
			echo '<button type="button" class="ecv2-order-dot' . ( $viewed ? ' ecv2-order-dot-viewed' : '' ) . '" data-order-id="' . esc_attr( $order_id ) . '" data-nonce="' . esc_attr( $nonce ) . '" title="' . esc_attr( $title ) . '" onclick="ecv2_order_toggle_viewed( this );"></button>';
		}

		private function print_order_number( $result ) {
			$order_id  = (int) $result->order_id;
			$edit_url  = $this->get_url( $this->key, $order_id, false, 'ec_admin_form_action', 'edit' );

			/* Receipt / Packing Slip live in the row menu; keeping them out of the
			   hover row is what lets this column stay narrow. */
			echo '<div class="ecv2-order-number-wrap">';
			echo '<div class="ecv2-order-number-head">';
			$this->print_viewed_dot( $result );
			echo '<a href="' . esc_url( $edit_url ) . '" class="ecv2-order-number ecv2-link-primary" onclick="ecv2_order_open_marks_viewed( ' . esc_attr( $order_id ) . ' );">#' . esc_html( $order_id ) . '</a>';
			echo '</div>';
			if ( function_exists( 'wp_easycart_admin_email_order_chip' ) ) { echo wp_easycart_admin_email_order_chip( $order_id ); } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- order_chip() returns HTML built from esc_attr()/esc_html() and an (int) cast id.
			echo '<div class="ecv2-product-row-actions">';
			echo '<a href="' . esc_url( $edit_url ) . '" class="ecv2-row-action-link">' . esc_html__( 'Edit', 'wp-easycart' ) . '</a>';
			echo '<span class="ecv2-row-action-sep">&middot;</span>';
			echo '<a href="#" class="ecv2-row-action-link" onclick="wp_easycart_open_quick_edit( \'order\', ' . esc_attr( $order_id ) . ' ); return false;">' . esc_html__( 'Quick Edit', 'wp-easycart' ) . '</a>';
			echo '</div>';
			echo '</div>';
		}

		private function print_order_customer( $result ) {
			$name    = trim( isset( $result->billing_name ) ? strip_tags( wp_unslash( $result->billing_name ) ) : '' );
			$email   = isset( $result->user_email ) ? $result->user_email : '';
			$company = isset( $result->billing_company_name ) ? trim( strip_tags( wp_unslash( $result->billing_company_name ) ) ) : '';
			$count   = $this->customer_order_count( $result );
			$is_guest = ( isset( $result->user_id ) && 0 === (int) $result->user_id );

			echo '<div class="ecv2-order-customer">';
			echo '<span class="ecv2-order-customer-name"><span class="ecv2-order-customer-name-text">' . esc_html( '' !== $name ? $name : ( '' !== $email ? $email : __( 'Guest', 'wp-easycart' ) ) ) . '</span>';
			if ( $is_guest ) {
				echo ' <span class="ecv2-order-badge ecv2-order-badge-guest">' . esc_html__( 'Guest', 'wp-easycart' ) . '</span>';
			}
			if ( $count > 1 ) {
				echo ' <span class="ecv2-order-badge ecv2-order-badge-repeat" title="' . esc_attr( sprintf( __( 'This customer has placed %d orders', 'wp-easycart' ), $count ) ) . '"><span class="dashicons dashicons-update"></span>' . esc_html( $count ) . '</span>';
			}
			echo '</span>';
			if ( '' !== $company ) {
				echo '<span class="ecv2-order-customer-company">' . esc_html( $company ) . '</span>';
			}
			if ( '' !== $email && '' !== $name ) {
				echo '<span class="ecv2-order-customer-email">';
				echo '<span class="ecv2-order-copy-text">' . esc_html( $email ) . '</span>';
				echo '<button type="button" class="ecv2-order-copy-btn" data-copy="' . esc_attr( $email ) . '" title="' . esc_attr__( 'Copy email', 'wp-easycart' ) . '" onclick="ecv2_order_copy( this );"><span class="dashicons dashicons-admin-page"></span></button>';
				echo '</span>';
			}
			echo '</div>';
		}

		private function print_order_items( $result ) {
			$count   = isset( $result->item_count ) ? (int) $result->item_count : 0;
			$preview = isset( $result->items_preview ) ? (string) $result->items_preview : '';

			if ( 0 === $count ) {
				echo '<span class="ecv2-sku-empty">&mdash;</span>';
				return;
			}

			echo '<div class="ecv2-order-items-wrap">';
			echo '<button type="button" class="ecv2-order-items-chip" onclick="ecv2_order_toggle_items( this );">';
			echo esc_html( sprintf( _n( '%d item', '%d items', $count, 'wp-easycart' ), $count ) );
			echo '</button>';

			if ( '' !== $preview ) {
				echo '<div class="ecv2-order-items-pop">';
				$items = explode( '||', $preview );
				$shown = 0;
				foreach ( $items as $item ) {
					if ( $shown >= 8 ) {
						break;
					}
					$parts = explode( '|', $item, 2 );
					$qty   = isset( $parts[0] ) ? (int) $parts[0] : 1;
					$title = isset( $parts[1] ) ? $parts[1] : $item;
					echo '<div class="ecv2-order-items-row"><span class="ecv2-order-items-qty">' . esc_html( $qty ) . '&times;</span> <span class="ecv2-order-items-title">' . esc_html( strip_tags( wp_unslash( $title ) ) ) . '</span></div>';
					$shown++;
				}
				if ( $count > $shown ) {
					echo '<div class="ecv2-order-items-more">+ ' . esc_html( $count - $shown ) . ' ' . esc_html__( 'more', 'wp-easycart' ) . '</div>';
				}
				echo '</div>';
			}
			echo '</div>';
		}

		private function print_order_total( $result ) {
			$total  = isset( $result->grand_total ) ? (float) $result->grand_total : 0;
			$refund = isset( $result->refund_total ) ? (float) $result->refund_total : 0;
			$promo  = isset( $result->promo_code ) ? (string) $result->promo_code : '';

			echo '<div class="ecv2-order-total-wrap">';
			echo '<span class="ecv2-order-total">' . esc_html( $GLOBALS['currency']->get_currency_display( $total ) ) . '</span>';
			if ( $refund > 0 ) {
				$full = ( $refund >= $total );
				echo '<span class="ecv2-order-badge ecv2-order-badge-refund" title="' . esc_attr( $full ? __( 'Fully refunded', 'wp-easycart' ) : __( 'Partially refunded', 'wp-easycart' ) ) . '">&minus;' . esc_html( $GLOBALS['currency']->get_currency_display( $refund ) ) . '</span>';
			}
			if ( '' !== $promo ) {
				echo '<span class="ecv2-order-badge ecv2-order-badge-promo" title="' . esc_attr( sprintf( __( 'Promo code used: %s', 'wp-easycart' ), $promo ) ) . '"><span class="dashicons dashicons-tag"></span>' . esc_html( $promo ) . '</span>';
			}
			echo '</div>';
		}

		private function print_payment_chip( $result ) {
			$gateway  = isset( $result->payment_gateway ) ? (string) $result->payment_gateway : '';
			$digits   = isset( $result->creditcard_digits ) ? (string) $result->creditcard_digits : '';
			$approved = isset( $result->is_approved ) ? (bool) $result->is_approved : false;

			if ( '' === $gateway ) {
				echo '<span class="ecv2-sku-empty">&mdash;</span>';
				return;
			}

			$cls = $approved ? 'ecv2-order-pay-ok' : 'ecv2-order-pay-pending';
			echo '<span class="ecv2-order-pay-chip ' . esc_attr( $cls ) . '" title="' . esc_attr( $approved ? __( 'Payment approved', 'wp-easycart' ) : __( 'Payment not approved', 'wp-easycart' ) ) . '">';
			echo '<span class="dashicons ' . ( $approved ? 'dashicons-yes-alt' : 'dashicons-clock' ) . '"></span>';
			echo esc_html( $this->gateway_label( $gateway ) );
			if ( '' !== $digits ) {
				echo ' <span class="ecv2-order-pay-digits">&bull;&bull;' . esc_html( $digits ) . '</span>';
			}
			echo '</span>';
		}

		/**
		 * Colored status chip with inline change dropdown (FREE — this is the
		 * modern UX for the existing free bulk "Change Order Status" action).
		 */
		private function print_status_chip( $result ) {
			$order_id   = (int) $result->order_id;
			$status_id  = (int) $result->orderstatus_id;
			$label      = isset( $result->order_status ) ? $result->order_status : __( 'Unknown', 'wp-easycart' );
			$color      = ( isset( $result->color_code ) && '' !== $result->color_code ) ? $result->color_code : '#e5e7eb';
			$nonce      = wp_create_nonce( 'wp-easycart-ecv2-order-status-' . $order_id );

			echo '<div class="ecv2-order-status-wrap" data-order-id="' . esc_attr( $order_id ) . '" data-status-id="' . esc_attr( $status_id ) . '" data-nonce="' . esc_attr( $nonce ) . '">';
			echo '<button type="button" class="ecv2-order-status-chip" style="' . esc_attr( $this->status_chip_style( $color ) ) . '" onclick="ecv2_order_open_status_menu( this );">';
			echo '<span class="ecv2-order-status-swatch" style="background:' . esc_attr( $color ) . ';"></span>';
			echo '<span class="ecv2-order-status-label">' . esc_html( $label ) . '</span>';
			echo '<span class="dashicons dashicons-arrow-down-alt2 ecv2-order-status-caret"></span>';
			echo '</button>';

			echo '<div class="ecv2-order-status-menu">';
			foreach ( $this->order_statuses as $status ) {
				$active = ( (int) $status->status_id === $status_id );
				$s_color = ( '' !== $status->color_code ) ? $status->color_code : '#e5e7eb';
				echo '<a href="#" class="ecv2-order-status-item' . ( $active ? ' ecv2-order-status-item-active' : '' ) . '" data-status-id="' . esc_attr( $status->status_id ) . '" data-label="' . esc_attr( $status->order_status ) . '" data-color="' . esc_attr( $s_color ) . '" onclick="ecv2_order_set_status( this ); return false;">';
				echo '<span class="ecv2-order-status-swatch" style="background:' . esc_attr( $s_color ) . ';"></span>';
				echo esc_html( $status->order_status );
				if ( $active ) {
					echo '<span class="dashicons dashicons-yes ecv2-order-status-check"></span>';
				}
				echo '</a>';
			}
			echo '</div>';
			echo '</div>';
		}

		public function status_chip_style( $color ) {
			/* Soft background tint from the status color, readable text. */
			return 'border-color:' . $color . ';';
		}

		private function print_fulfillment_cell( $result ) {
			$order_id = (int) $result->order_id;
			$tracking = isset( $result->tracking_number ) ? trim( (string) $result->tracking_number ) : '';
			$carrier  = isset( $result->shipping_carrier ) ? trim( (string) $result->shipping_carrier ) : '';
			$method   = isset( $result->shipping_method ) ? trim( (string) $result->shipping_method ) : '';
			$approved = isset( $result->is_approved ) ? (bool) $result->is_approved : false;
			$status   = isset( $result->orderstatus_id ) ? (int) $result->orderstatus_id : 0;
			$gate     = ecv2_get_order_pro_gate();
			/* 6.0.1: Free Local Pickup is collected, not shipped: "Mark picked up" instead of Fulfill. "Picked up" once the
			   status says so ( Order Picked Up ), whatever the method. */
			$mode     = ( self::is_local_pickup( $result ) && self::pickup_fulfill_supported() ) ? 'pickup' : 'ship';

			/*
			 * Two-line layout:
			 *  1. .ecv2-order-fulfill-state — tracking chip (carrier folded in), Fulfill button, or —
			 *  2. .ecv2-order-fulfill-line2 — shipping method + icon-only flags
			 * JS (orders-v2-pro.js) only ever replaces line 1.
			 */
			echo '<div class="ecv2-order-fulfill-wrap" data-order-id="' . esc_attr( $order_id ) . '" data-tracking="' . esc_attr( $tracking ) . '" data-carrier="' . esc_attr( $carrier ) . '"' . ( 'pickup' === $mode ? ' data-pickup="1"' : '' ) . '>';

			echo '<div class="ecv2-order-fulfill-state">';
			if ( '' !== $tracking ) {
				echo self::tracking_chip_html( $tracking, $carrier ); /* phpcs:ignore WordPress.Security.EscapeOutput -- escaped in helper */
			} else if ( in_array( $status, self::fulfilled_status_ids(), true ) ) {
				/* 6.0.0: shipped or picked up without a tracking number is still fulfilled ( matches the order details banner ). */
				echo self::fulfilled_chip_html( self::STATUS_PICKED_UP === $status ? 'pickup' : 'ship' ); /* phpcs:ignore WordPress.Security.EscapeOutput -- escaped in helper */
			} else if ( $approved && isset( $result->requires_shipping ) && ! (int) $result->requires_shipping && empty( $result->includes_restaurant_type ) && ! in_array( $status, array( self::STATUS_REFUNDED, self::STATUS_CANCELLED, self::STATUS_PICKED_UP ), true ) ) {
				/* 6.0.0: nothing to ship ( downloads, gift cards, services, shipping disabled ): fulfilled on payment, no Fulfill button. */
				echo '<span class="ecv2-order-track-chip ecv2-order-fulfill-digital" title="' . esc_attr__( 'Every item in this order is a download, gift card, subscription or a product with shipping disabled, so there is nothing to ship.', 'wp-easycart' ) . '"><span class="dashicons dashicons-download"></span> ' . esc_html__( 'No shipping', 'wp-easycart' ) . '</span>';
			} else if ( $approved && ! in_array( $status, array( self::STATUS_REFUNDED, self::STATUS_CANCELLED, self::STATUS_PICKED_UP ), true ) ) {
				echo self::fulfill_button_html( $gate, $mode ); /* phpcs:ignore WordPress.Security.EscapeOutput -- escaped in helper */
			} else {
				echo '<span class="ecv2-sku-empty">&mdash;</span>';
			}
			echo '</div>';

			/* Contextual order flags — icon-only in the table, icon + text in card view. */
			$flags = '';
			if ( ! empty( $result->includes_preorder_items ) ) {
				$flags .= self::flag_badge_html( 'backup', __( 'Preorder', 'wp-easycart' ), __( 'Includes preorder items', 'wp-easycart' ) );
			}
			if ( self::is_pickup_order( $result ) ) {
				$flags .= self::flag_badge_html( 'store', __( 'Pickup', 'wp-easycart' ), __( 'Pickup order', 'wp-easycart' ) );
			}
			if ( ! empty( $result->subscription_id ) ) {
				$flags .= self::flag_badge_html( 'controls-repeat', __( 'Subscription', 'wp-easycart' ), __( 'Subscription order', 'wp-easycart' ) );
			}

			if ( '' !== $method || '' !== $flags ) {
				echo '<div class="ecv2-order-fulfill-line2">';
				if ( '' !== $method ) {
					echo '<span class="ecv2-order-fulfill-meta" title="' . esc_attr( $method ) . '">' . esc_html( $method ) . '</span>';
				}
				if ( '' !== $flags ) {
					echo '<span class="ecv2-order-flags">' . $flags . '</span>'; /* phpcs:ignore WordPress.Security.EscapeOutput -- escaped in helper */
				}
				echo '</div>';
			}

			/* Kept for backward compatibility; PRO row tools now hook 'wp_easycart_ecv2_order_row_actions_start'. */
			do_action( 'wp_easycart_ecv2_order_fulfillment_cell_end', $result, $gate );

			echo '</div>';
		}

		/**
		 * Tracking chip markup. Public/static so PRO (and JS via ecv2_lang) can
		 * reproduce the exact same chip after an inline fulfill.
		 */
		/**
		 * Statuses that mean the order is fulfilled even without a tracking number ( same rule as the order details banner ).
		 *
		 * @since 6.0.0
		 * @return int[]
		 */
		public static function fulfilled_status_ids() {
			return array( self::STATUS_SHIPPED, self::STATUS_PICKED_UP );
		}

		/**
		 * Fulfillment cell chip for a fulfilled order that has no tracking number.
		 *
		 * @since 6.0.0
		 * @since 6.0.1 $mode: 'pickup' reads "Picked up" for a Free Local Pickup order.
		 * @param string $mode 'ship' or 'pickup'.
		 * @return string
		 */
		public static function fulfilled_chip_html( $mode = 'ship' ) {
			if ( 'pickup' === $mode ) {
				return '<span class="ecv2-order-track-chip ecv2-order-fulfill-done ecv2-order-fulfill-pickedup" title="' . esc_attr__( 'The customer has picked this order up.', 'wp-easycart' ) . '"><span class="dashicons dashicons-store"></span> ' . esc_html__( 'Picked up', 'wp-easycart' ) . '</span>';
			}
			return '<span class="ecv2-order-track-chip ecv2-order-fulfill-done" title="' . esc_attr__( 'This order has been fulfilled.', 'wp-easycart' ) . '"><span class="dashicons dashicons-yes-alt"></span> ' . esc_html__( 'Fulfilled', 'wp-easycart' ) . '</span>';
		}

		/**
		 * Fulfill button ( locked when PRO is not available ).
		 *
		 * @since 6.0.0
		 * @since 6.0.1 $mode: 'pickup' is "Mark picked up" for a Free Local Pickup order. PRO's ecv2_open_fulfill()
		 *              reads data-fulfill-mode and asks only to confirm: no carrier, tracking or shipped email.
		 * @param array  $gate ecv2_get_order_pro_gate() result.
		 * @param string $mode 'ship' or 'pickup'.
		 * @return string
		 */
		public static function fulfill_button_html( $gate, $mode = 'ship' ) {
			$pickup = ( 'pickup' === $mode );
			$icon   = $pickup ? 'store' : 'airplane';
			$label  = $pickup ? __( 'Mark picked up', 'wp-easycart' ) : __( 'Fulfill', 'wp-easycart' );
			$class  = 'ecv2-btn ecv2-btn-sm ecv2-order-fulfill-btn' . ( $pickup ? ' ecv2-order-pickup-btn' : '' );
			if ( isset( $gate['state'] ) && 'enabled' === $gate['state'] ) {
				return '<button type="button" class="' . esc_attr( $class ) . '" data-fulfill-mode="' . esc_attr( $pickup ? 'pickup' : 'ship' ) . '" onclick="ecv2_open_fulfill( this );"><span class="dashicons dashicons-' . esc_attr( $icon ) . '"></span> ' . esc_html( $label ) . '</button>';
			}
			return '<button type="button" class="' . esc_attr( $class . ' ecv2-order-fulfill-locked' ) . '" onclick="return wpec_gate.locked_action( ecv2_lang.order_pro_gate );"><span class="dashicons dashicons-' . esc_attr( $icon ) . '"></span> ' . esc_html( $label ) . ' <span class="dashicons dashicons-lock"></span></button>';
		}

		/**
		 * Free Local Pickup order? See wp_easycart_order_is_local_pickup() ( admin/inc/wp_easycart_admin_orders.php ).
		 *
		 * @since 6.0.1
		 * @param object $result Order row ( needs shipping_method ).
		 * @return bool
		 */
		public static function is_local_pickup( $result ) {
			return function_exists( 'wp_easycart_order_is_local_pickup' ) && wp_easycart_order_is_local_pickup( $result );
		}

		/**
		 * Can the installed PRO mark an order picked up ( ecv2_order_fulfill mode=pickup, PRO 6.0.1 )? PRO 6.0.0 ignores
		 * the mode and would open its shipping popover with "Mark as Shipped" and the shipped email ticked, so with it the
		 * row keeps the plain Fulfill button. Without PRO the button is the locked upsell either way.
		 *
		 * @since 6.0.1
		 * @return bool
		 */
		public static function pickup_fulfill_supported() {
			return ! defined( 'WP_EASYCART_ADMIN_PRO_VERSION' ) || version_compare( WP_EASYCART_ADMIN_PRO_VERSION, '6.0.1', '>=' );
		}

		/**
		 * " OR <shipping method is a Free Local Pickup label>" for the Pickup filter, or '' when there is nothing to match.
		 *
		 * @since 6.0.1
		 * @return string
		 */
		private static function local_pickup_where() {
			global $wpdb;
			$labels = array();
			foreach ( ( function_exists( 'wp_easycart_local_pickup_labels' ) ? array_keys( wp_easycart_local_pickup_labels() ) : array() ) as $label ) {
				/* The keys are decoded; checkout stores the label escaped ( "&" as "&amp;" ), so match both. */
				$labels[ $label ]                           = true;
				$labels[ strtolower( esc_html( $label ) ) ] = true;
			}
			$labels = array_map( 'strval', array_keys( $labels ) );
			if ( empty( $labels ) ) {
				return '';
			}
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- one %s placeholder per label, built from count().
			return $wpdb->prepare( ' OR LOWER( TRIM( ec_order.shipping_method ) ) IN ( ' . implode( ', ', array_fill( 0, count( $labels ), '%s' ) ) . ' )', $labels );
		}

		public static function tracking_chip_html( $tracking, $carrier = '' ) {
			$html  = '<span class="ecv2-order-track-chip" title="' . esc_attr( ( '' !== $carrier ? $carrier . ' — ' : '' ) . $tracking ) . '">';
			$html .= '<span class="dashicons dashicons-car"></span>';
			if ( '' !== $carrier ) {
				$html .= '<span class="ecv2-order-track-carrier">' . esc_html( $carrier ) . '</span>';
			}
			$html .= '<span class="ecv2-order-track-num">' . esc_html( $tracking ) . '</span>';
			$html .= '<button type="button" class="ecv2-order-copy-btn" data-copy="' . esc_attr( $tracking ) . '" title="' . esc_attr__( 'Copy tracking number', 'wp-easycart' ) . '" onclick="ecv2_order_copy( this );"><span class="dashicons dashicons-admin-page"></span></button>';
			$html .= '</span>';
			return $html;
		}

		/**
		 * Pickup detection. Checkout stores pickup_date via date( ..., strtotime( '' ) )
		 * for non-pickup orders, which yields 1970-01-01 (epoch) rather than the
		 * zero date, so we treat anything before 2000 as "no pickup date".
		 */
		public static function is_pickup_order( $result ) {
			if ( ! empty( $result->includes_restaurant_type ) || self::is_local_pickup( $result ) ) {
				return true;
			}
			if ( isset( $result->orderstatus_id ) && in_array( (int) $result->orderstatus_id, array( self::STATUS_READY_PICKUP, self::STATUS_PICKED_UP ), true ) ) {
				return true;
			}
			$pickup_date = isset( $result->pickup_date ) ? (string) $result->pickup_date : '';
			return ( '' !== $pickup_date && (int) substr( $pickup_date, 0, 4 ) >= 2000 );
		}

		private static function flag_badge_html( $icon, $label, $title ) {
			return '<span class="ecv2-order-badge ecv2-order-badge-flag" title="' . esc_attr( $title ) . '"><span class="dashicons dashicons-' . esc_attr( $icon ) . '"></span><span class="ecv2-order-badge-text">' . esc_html( $label ) . '</span></span>';
		}

		/* ------------------------------------------------------------------ */
		/* Card view — order ticket                                             */
		/* ------------------------------------------------------------------ */

		protected function print_card( $result ) {
			$order_id = (int) $result->order_id;
			$viewed   = ! empty( $result->order_viewed );
			$edit_url = $this->get_url( $this->key, $order_id, false, 'ec_admin_form_action', 'edit' );

			echo '<div class="ecv2-card ecv2-order-card' . ( $viewed ? '' : ' ecv2-order-card-unviewed' ) . '" data-id="' . esc_attr( $order_id ) . '">';

			echo '<div class="ecv2-order-card-head">';
			$this->print_viewed_dot( $result );
			echo '<a href="' . esc_url( $edit_url ) . '" class="ecv2-order-number ecv2-link-primary">#' . esc_html( $order_id ) . '</a>';
			echo '<span class="ecv2-order-card-date">';
			$ts = strtotime( $result->order_date );
			if ( $ts > 0 ) {
				echo esc_html( $this->format_relative_date( $ts + $this->date_diff, time() + $this->date_diff ) );
			}
			echo '</span>';
			echo '</div>';

			echo '<div class="ecv2-order-card-body">';
			$this->print_order_customer( $result );
			echo '<div class="ecv2-order-card-meta">';
			$this->print_order_items( $result );
			$this->print_order_total( $result );
			echo '</div>';
			$this->print_status_chip( $result );
			$this->print_fulfillment_cell( $result );
			echo '</div>';

			echo '<div class="ecv2-card-footer">';
			echo '<input type="checkbox" name="bulk[]" value="' . esc_attr( $order_id ) . '" class="ecv2-row-check" />';
			echo '<div class="ecv2-order-actions-wrap">';
			do_action( 'wp_easycart_ecv2_order_row_actions_start', $result, ecv2_get_order_pro_gate() );
			echo '<div class="ecv2-row-menu-wrap">';
			echo '<button type="button" class="ecv2-row-menu-trigger" onclick="ecv2_toggle_row_menu(this);">&#8943;</button>';
			echo '<div class="ecv2-row-menu">';
			foreach ( $this->row_menu_actions as $action ) {
				$this->print_row_menu_item( $result, $action );
			}
			echo '</div></div>'; // .ecv2-row-menu, .ecv2-row-menu-wrap
			echo '</div>'; // .ecv2-order-actions-wrap
			echo '</div>'; // .ecv2-card-footer

			echo '</div>';
		}

		/* ------------------------------------------------------------------ */
		/* Modals                                                               */
		/* ------------------------------------------------------------------ */

		protected function print_custom_modals() {
			/* Bulk change-status modal — feeds the existing GET handler. */
			echo '<div class="ecv2-modal-overlay" id="ecv2-order-status-modal" style="display:none;">';
			echo '<div class="ecv2-modal ecv2-modal-confirm">';
			echo '<div class="ecv2-modal-header">';
			echo '<h2>' . esc_html__( 'Change Order Status', 'wp-easycart' ) . ' <span id="ecv2-order-status-modal-count"></span></h2>';
			echo '<button type="button" class="ecv2-modal-close" onclick="ecv2_order_close_status_modal();">&times;</button>';
			echo '</div>';
			echo '<div class="ecv2-modal-body">';
			echo '<div class="ecv2-modal-field">';
			echo '<label class="ecv2-modal-label">' . esc_html__( 'New status for the selected orders', 'wp-easycart' ) . '</label>';
			echo '<select id="ecv2-order-status-modal-select" class="ecv2-select">';
			foreach ( $this->order_statuses as $status ) {
				echo '<option value="' . esc_attr( $status->status_id ) . '">' . esc_html( $status->order_status ) . '</option>';
			}
			echo '</select>';
			echo '</div>';
			echo '</div>';
			echo '<div class="ecv2-modal-footer">';
			echo '<button type="button" class="ecv2-btn ecv2-btn-ghost" onclick="ecv2_order_close_status_modal();">' . esc_html__( 'Cancel', 'wp-easycart' ) . '</button>';
			echo '<button type="button" class="ecv2-btn ecv2-btn-primary" onclick="ecv2_order_apply_status_modal();">' . esc_html__( 'Apply', 'wp-easycart' ) . '</button>';
			echo '</div>';
			echo '</div></div>';

			/* PRO modals (fulfill, bulk fulfill, note, timeline). */
			do_action( 'wp_easycart_admin_ecv2_order_render_modals', $this->table_id );
		}
	}

endif;

/* ---------------------------------------------------------------------- */
/* PRO gate helper (mirrors ecv2_get_variant_tracking_gate for products)   */
/* ---------------------------------------------------------------------- */

if ( ! function_exists( 'ecv2_get_order_pro_gate' ) ) {
	function ecv2_get_order_pro_gate() {
		static $gate = null;
		if ( null === $gate ) {
			$gate = wp_easycart_admin_pro_gate::evaluate( array(
				'enabled_filter' => 'wp_easycart_order_list_pro_enabled',
				'min_version'    => '5.8.16',
				'upsell_view'    => 'orders',
			) );
		}
		return $gate;
	}
}

/* ---------------------------------------------------------------------- */
/* FREE AJAX endpoints                                                      */
/* ---------------------------------------------------------------------- */

/**
 * Inline single-order status change. Modern UX for the existing free bulk
 * status-change capability; mirrors bulk_update_order_status() side effects
 * (order log + wpeasycart_order_status_update action).
 */
add_action( 'wp_ajax_ecv2_order_set_status', 'ecv2_order_set_status' );
function ecv2_order_set_status() {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_orders' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
	}
	$order_id = isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : 0;
	if ( ! isset( $_POST['wp_easycart_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_easycart_nonce'] ) ), 'wp-easycart-ecv2-order-status-' . $order_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wp-easycart' ) ) );
	}

	$status_id = isset( $_POST['status_id'] ) ? (int) $_POST['status_id'] : 0;
	if ( ! $order_id || ! $status_id ) {
		wp_send_json_error( array( 'message' => __( 'Invalid request.', 'wp-easycart' ) ) );
	}

	global $wpdb;
	$status = $wpdb->get_row( $wpdb->prepare( 'SELECT status_id, order_status, is_approved, color_code FROM ec_orderstatus WHERE status_id = %d', $status_id ) );
	if ( ! $status ) {
		wp_send_json_error( array( 'message' => __( 'Invalid status.', 'wp-easycart' ) ) );
	}

	$old_status_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT orderstatus_id FROM ec_order WHERE order_id = %d', $order_id ) );

	$wpdb->query( $wpdb->prepare( 'UPDATE ec_order SET orderstatus_id = %d, last_updated = NOW() WHERE order_id = %d', $status_id, $order_id ) );
	do_action( 'wpeasycart_order_status_update', $order_id, $status_id );

	$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log( order_id, order_log_key ) VALUES( %d, "order-status-update" )', $order_id ) );
	$order_log_id = $wpdb->insert_id;
	$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "orderstatus_id", %s )', $order_log_id, $order_id, $status_id ) );

	wp_send_json_success( array(
		'order_id'      => $order_id,
		'status_id'     => (int) $status->status_id,
		'label'         => $status->order_status,
		'color'         => ( '' !== $status->color_code ) ? $status->color_code : '#e5e7eb',
		'is_approved'   => (int) $status->is_approved,
		'old_status_id' => $old_status_id,
	) );
}

/**
 * Customer typeahead for the "By Customer" filter ( @since 6.0.0 ). Read-only; name / email search,
 * 25 rows, select2-shaped { results:[{id,text}], more }. Own handler rather than
 * ec_admin_ajax_get_order_users because that one requires wpec_manager and the orders list is
 * open to wpec_orders.
 */
add_action( 'wp_ajax_ecv2_order_customer_search', 'ecv2_order_customer_search' );
function ecv2_order_customer_search() {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_orders' ) ) {
		wp_send_json( array( 'results' => array(), 'more' => false ) );
	}
	if ( ! isset( $_POST['wp_easycart_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_easycart_nonce'] ) ), 'wp-easycart-ecv2-order-customer-search' ) ) {
		wp_send_json( array( 'results' => array(), 'more' => false ) );
	}
	global $wpdb;
	$q        = isset( $_POST['q'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['q'] ) ) ) : '';
	$page     = isset( $_POST['page'] ) ? max( 1, (int) $_POST['page'] ) : 1;
	$per_page = 25;
	$results  = array();
	if ( '' === $q ) {
		$results[] = array( 'id' => '0', 'text' => __( 'Guest checkouts', 'wp-easycart' ) );
	}
	$like = '%' . $wpdb->esc_like( $q ) . '%';
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT user_id, first_name, last_name, email FROM ec_user WHERE last_name LIKE %s OR first_name LIKE %s OR email LIKE %s OR CONCAT( first_name, ' ', last_name ) LIKE %s ORDER BY last_name ASC, first_name ASC LIMIT %d OFFSET %d", $like, $like, $like, $like, $per_page + 1, ( $page - 1 ) * $per_page ) );
	$rows = is_array( $rows ) ? $rows : array();
	$more = ( count( $rows ) > $per_page );
	if ( $more ) {
		array_pop( $rows );
	}
	foreach ( $rows as $u ) {
		$name = trim( wp_unslash( $u->last_name . ', ' . $u->first_name ), ', ' );
		$results[] = array( 'id' => (int) $u->user_id, 'text' => ( '' !== $name ? $name : $u->email ) . ( '' !== (string) $u->email ? ' (' . $u->email . ')' : '' ) );
	}
	wp_send_json( array( 'results' => $results, 'more' => $more ) );
}

/**
 * Toggle the viewed flag from the row dot (redesign of mark viewed / not viewed).
 */
add_action( 'wp_ajax_ecv2_order_toggle_viewed', 'ecv2_order_toggle_viewed' );
function ecv2_order_toggle_viewed() {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_orders' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
	}
	$order_id = isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : 0;
	if ( ! isset( $_POST['wp_easycart_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_easycart_nonce'] ) ), 'wp-easycart-ecv2-order-viewed-' . $order_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wp-easycart' ) ) );
	}
	if ( ! $order_id ) {
		wp_send_json_error( array( 'message' => __( 'Invalid request.', 'wp-easycart' ) ) );
	}

	global $wpdb;
	$viewed = isset( $_POST['viewed'] ) ? ( (int) $_POST['viewed'] ? 1 : 0 ) : 1;
	$wpdb->query( $wpdb->prepare( 'UPDATE ec_order SET order_viewed = %d, last_updated = NOW() WHERE order_id = %d', $viewed, $order_id ) );

	wp_send_json_success( array( 'order_id' => $order_id, 'viewed' => $viewed ) );
}

/* ---------------------------------------------------------------------- */
/* Quick Edit V2 (drawer) — load + save                                     */
/* Template: admin/template/orders/orders/order-quick-edit-slideout.php    */
/* JS: orders-v2.js (overrides wp_easycart_open_order_quick_edit)          */
/* ---------------------------------------------------------------------- */

/**
 * Shared carrier suggestions (PRO reuses the same filter for its fulfill popover).
 */
function ecv2_order_carrier_suggestions() {
	return apply_filters( 'wp_easycart_ecv2_order_carriers', array( 'USPS', 'UPS', 'FedEx', 'DHL', 'Canada Post', 'Royal Mail', 'Australia Post', 'Other' ) );
}

add_action( 'wp_ajax_ecv2_order_quick_edit_get', 'ecv2_order_quick_edit_get' );
function ecv2_order_quick_edit_get() {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_orders' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
	}
	if ( ! isset( $_POST['wp_easycart_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_easycart_nonce'] ) ), 'wp-easycart-ecv2-order-quick-edit' ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wp-easycart' ) ) );
	}
	$order_id = isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : 0;
	if ( ! $order_id ) {
		wp_send_json_error( array( 'message' => __( 'Invalid request.', 'wp-easycart' ) ) );
	}

	global $wpdb;
	$order = $wpdb->get_row( $wpdb->prepare(
		'SELECT ec_order.order_id, ec_order.order_date, ec_order.orderstatus_id, ec_order.grand_total, ec_order.refund_total, ec_order.order_gateway, ec_order.creditcard_digits, ec_order.payment_method,
				ec_order.user_email, ec_order.billing_first_name, ec_order.billing_last_name, ec_order.billing_phone, ec_order.billing_company_name,
				ec_order.use_expedited_shipping, ec_order.shipping_method, ec_order.shipping_carrier, ec_order.tracking_number,
				ec_order.shipping_first_name, ec_order.shipping_last_name, ec_order.shipping_company_name, ec_order.shipping_address_line_1, ec_order.shipping_address_line_2,
				ec_order.shipping_city, ec_order.shipping_state, ec_order.shipping_zip, ec_order.shipping_country, ec_order.shipping_phone,
				ec_order.includes_preorder_items, ec_order.includes_restaurant_type, ec_order.pickup_date, ec_order.subscription_id, ec_order.order_customer_notes,
				ec_orderstatus.order_status, ec_orderstatus.color_code, ec_orderstatus.is_approved
		 FROM ec_order LEFT JOIN ec_orderstatus ON ec_orderstatus.status_id = ec_order.orderstatus_id
		 WHERE ec_order.order_id = %d',
		$order_id
	) );
	if ( ! $order ) {
		wp_send_json_error( array( 'message' => __( 'Order not found.', 'wp-easycart' ) ) );
	}

	$items = $wpdb->get_results( $wpdb->prepare( 'SELECT title, model_number, quantity, refunded_quantity, unit_price, total_price FROM ec_orderdetail WHERE order_id = %d ORDER BY orderdetail_id ASC', $order_id ) );
	$item_payload = array();
	$item_qty     = 0;
	foreach ( $items as $item ) {
		$item_qty += (int) $item->quantity;
		$item_payload[] = array(
			'title'    => wp_unslash( (string) $item->title ),
			'sku'      => (string) $item->model_number,
			'qty'      => (int) $item->quantity,
			'refunded' => (int) $item->refunded_quantity,
			'total'    => $GLOBALS['currency']->get_currency_display( (float) $item->total_price ),
		);
	}

	$ts = strtotime( $order->order_date );
	$ship_lines = array_values( array_filter( array(
		trim( $order->shipping_first_name . ' ' . $order->shipping_last_name ),
		trim( (string) $order->shipping_company_name ),
		trim( (string) $order->shipping_address_line_1 ),
		trim( (string) $order->shipping_address_line_2 ),
		trim( trim( $order->shipping_city . ', ' . $order->shipping_state, ', ' ) . ' ' . $order->shipping_zip ),
		trim( (string) $order->shipping_country ),
	), 'strlen' ) );

	$payload = array(
		'order_id'      => $order_id,
		'date'          => $ts > 0 ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts ) : '',
		'status_id'     => (int) $order->orderstatus_id,
		'status_label'  => (string) $order->order_status,
		'status_color'  => ( '' !== (string) $order->color_code ) ? $order->color_code : '#e5e7eb',
		'total'         => $GLOBALS['currency']->get_currency_display( (float) $order->grand_total ),
		'refund_total'  => ( (float) $order->refund_total > 0 ) ? $GLOBALS['currency']->get_currency_display( (float) $order->refund_total ) : '',
		'payment'       => trim( wp_easycart_admin_order_table::gateway_label( (string) $order->order_gateway ) . ( '' !== (string) $order->creditcard_digits ? ' ••' . $order->creditcard_digits : '' ) ),
		'customer_name' => trim( wp_unslash( $order->billing_first_name . ' ' . $order->billing_last_name ) ),
		'customer_company' => trim( wp_unslash( (string) $order->billing_company_name ) ),
		'email'         => (string) $order->user_email,
		'phone'         => (string) $order->billing_phone,
		'ship_to'       => array_map( 'wp_unslash', $ship_lines ),
		'ship_phone'    => (string) $order->shipping_phone,
		'items'         => $item_payload,
		'item_qty'      => $item_qty,
		'flags'         => array(
			'preorder'     => ! empty( $order->includes_preorder_items ),
			'pickup'       => wp_easycart_admin_order_table::is_pickup_order( $order ),
			'subscription' => ! empty( $order->subscription_id ),
		),
		'customer_notes' => trim( wp_unslash( (string) $order->order_customer_notes ) ),
		'expedited'     => (int) $order->use_expedited_shipping,
		'method'        => wp_unslash( (string) $order->shipping_method ),
		'carrier'       => (string) $order->shipping_carrier,
		'tracking'      => (string) $order->tracking_number,
		'edit_url'      => admin_url( 'admin.php?page=wp-easycart-orders&subpage=orders&ec_admin_form_action=edit&order_id=' . $order_id ),
	);

	wp_send_json_success( apply_filters( 'wp_easycart_ecv2_order_quick_edit_payload', $payload, $order ) );
}

add_action( 'wp_ajax_ecv2_order_quick_edit_save', 'ecv2_order_quick_edit_save' );
function ecv2_order_quick_edit_save() {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_orders' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
	}
	if ( ! isset( $_POST['wp_easycart_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_easycart_nonce'] ) ), 'wp-easycart-ecv2-order-quick-edit' ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wp-easycart' ) ) );
	}
	$order_id = isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : 0;
	if ( ! $order_id ) {
		wp_send_json_error( array( 'message' => __( 'Invalid request.', 'wp-easycart' ) ) );
	}

	global $wpdb;
	$current = $wpdb->get_row( $wpdb->prepare( 'SELECT orderstatus_id, use_expedited_shipping, shipping_method, shipping_carrier, tracking_number FROM ec_order WHERE order_id = %d', $order_id ) );
	if ( ! $current ) {
		wp_send_json_error( array( 'message' => __( 'Order not found.', 'wp-easycart' ) ) );
	}

	$status_id  = isset( $_POST['status_id'] ) ? (int) $_POST['status_id'] : (int) $current->orderstatus_id;
	$expedited  = isset( $_POST['expedited'] ) ? ( (int) $_POST['expedited'] ? 1 : 0 ) : (int) $current->use_expedited_shipping;
	$method     = isset( $_POST['method'] ) ? sanitize_text_field( wp_unslash( $_POST['method'] ) ) : (string) $current->shipping_method;
	$carrier    = isset( $_POST['carrier'] ) ? sanitize_text_field( wp_unslash( $_POST['carrier'] ) ) : (string) $current->shipping_carrier;
	$tracking   = isset( $_POST['tracking'] ) ? sanitize_text_field( wp_unslash( $_POST['tracking'] ) ) : (string) $current->tracking_number;
	$send_email = ! empty( $_POST['send_email'] );

	$status = $wpdb->get_row( $wpdb->prepare( 'SELECT status_id, order_status, is_approved, color_code FROM ec_orderstatus WHERE status_id = %d', $status_id ) );
	if ( ! $status ) {
		wp_send_json_error( array( 'message' => __( 'Invalid status.', 'wp-easycart' ) ) );
	}

	$status_changed   = ( (int) $current->orderstatus_id !== $status_id );
	$shipping_changed = ( (int) $current->use_expedited_shipping !== $expedited || (string) $current->shipping_method !== $method || (string) $current->shipping_carrier !== $carrier || (string) $current->tracking_number !== $tracking );

	if ( $status_changed || $shipping_changed ) {
		$wpdb->query( $wpdb->prepare( 'UPDATE ec_order SET orderstatus_id = %d, use_expedited_shipping = %d, shipping_method = %s, shipping_carrier = %s, tracking_number = %s, last_updated = NOW() WHERE order_id = %d', $status_id, $expedited, $method, $carrier, $tracking, $order_id ) );

		if ( $status_changed ) {
			do_action( 'wpeasycart_order_status_update', $order_id, $status_id );
		}
		if ( $shipping_changed ) {
			do_action( 'wpeasycart_tracking_info_update', $order_id, $expedited, $method, $carrier, $tracking );
		}

		/* One log entry per save, only the fields that actually changed. */
		$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log( order_id, order_log_key ) VALUES( %d, "order-quick-edit" )', $order_id ) );
		$order_log_id = $wpdb->insert_id;
		$changes = array();
		if ( $status_changed ) { $changes['orderstatus_id'] = $status_id; }
		if ( (int) $current->use_expedited_shipping !== $expedited ) { $changes['use_expedited_shipping'] = $expedited; }
		if ( (string) $current->shipping_method !== $method ) { $changes['shipping_method'] = $method; }
		if ( (string) $current->shipping_carrier !== $carrier ) { $changes['shipping_carrier'] = $carrier; }
		if ( (string) $current->tracking_number !== $tracking ) { $changes['tracking_number'] = $tracking; }
		foreach ( $changes as $key => $value ) {
			$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, %s, %s )', $order_log_id, $order_id, $key, $value ) );
		}
	}

	$email_sent = false;
	if ( $send_email ) {
		wp_easycart_admin_orders()->send_customer_shipping_email( $order_id, $tracking, $carrier );
		$email_sent = true;
	}

	do_action( 'wp_easycart_ecv2_order_quick_edit_saved', $order_id, $status_id, $expedited, $method, $carrier, $tracking, $email_sent );
	do_action( 'wpeasycart_order_updated', $order_id );

	wp_send_json_success( array(
		'order_id'    => $order_id,
		'status_id'   => (int) $status->status_id,
		'label'       => $status->order_status,
		'color'       => ( '' !== $status->color_code ) ? $status->color_code : '#e5e7eb',
		'is_approved' => (int) $status->is_approved,
		'expedited'   => $expedited,
		'method'      => $method,
		'carrier'     => $carrier,
		'tracking'    => $tracking,
		'email_sent'  => $email_sent,
		'changed'     => ( $status_changed || $shipping_changed ),
		'chip_html'   => ( '' !== $tracking ) ? wp_easycart_admin_order_table::tracking_chip_html( $tracking, $carrier ) : '',
	) );
}

/* ---------------------------------------------------------------------- */
/* Duplicate Order V2 (drawer) — load + create                              */
/* Template: admin/template/orders/orders/order-duplicate-slideout.php     */
/* JS: orders-v2.js (overrides wp_easycart_open_order_duplicate)           */
/* ---------------------------------------------------------------------- */

add_action( 'wp_ajax_ecv2_order_duplicate_get', 'ecv2_order_duplicate_get' );
function ecv2_order_duplicate_get() {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_orders' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
	}
	if ( ! isset( $_POST['wp_easycart_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_easycart_nonce'] ) ), 'wp-easycart-ecv2-order-duplicate' ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wp-easycart' ) ) );
	}
	$order_id = isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : 0;
	if ( ! $order_id ) {
		wp_send_json_error( array( 'message' => __( 'Invalid request.', 'wp-easycart' ) ) );
	}

	global $wpdb;
	$order = $wpdb->get_row( $wpdb->prepare(
		'SELECT ec_order.*, ec_orderstatus.order_status, ec_orderstatus.color_code
		 FROM ec_order LEFT JOIN ec_orderstatus ON ec_orderstatus.status_id = ec_order.orderstatus_id
		 WHERE ec_order.order_id = %d',
		$order_id
	) );
	if ( ! $order ) {
		wp_send_json_error( array( 'message' => __( 'Order not found.', 'wp-easycart' ) ) );
	}

	$items   = $wpdb->get_results( $wpdb->prepare( 'SELECT orderdetail_id, title, model_number, quantity, refunded_quantity, unit_price, total_price, is_download, is_giftcard, is_shippable, is_free_gift, bundle_group_key FROM ec_orderdetail WHERE order_id = %d ORDER BY orderdetail_id ASC', $order_id ) );
	$payload = array();
	foreach ( $items as $item ) {
		$net_qty = max( 0, (int) $item->quantity - (int) $item->refunded_quantity );
		$payload[] = array(
			'id'          => (int) $item->orderdetail_id,
			'title'       => wp_unslash( (string) $item->title ),
			'sku'         => (string) $item->model_number,
			'qty'         => (int) $item->quantity,
			'refunded'    => (int) $item->refunded_quantity,
			'default_qty' => $net_qty,
			'unit_price'  => (float) $item->unit_price,
			'unit_display'=> $GLOBALS['currency']->get_currency_display( (float) $item->unit_price ),
			'is_download' => ! empty( $item->is_download ),
			'is_giftcard' => ! empty( $item->is_giftcard ),
			'is_free_gift'=> ! empty( $item->is_free_gift ),
			'is_bundle'   => '' !== (string) $item->bundle_group_key,
		);
	}

	$ship_lines = array_values( array_filter( array(
		trim( $order->shipping_first_name . ' ' . $order->shipping_last_name ),
		trim( (string) $order->shipping_company_name ),
		trim( (string) $order->shipping_address_line_1 ),
		trim( (string) $order->shipping_address_line_2 ),
		trim( trim( $order->shipping_city . ', ' . $order->shipping_state, ', ' ) . ' ' . $order->shipping_zip ),
		trim( (string) $order->shipping_country ),
	), 'strlen' ) );

	$ts = strtotime( $order->order_date );
	wp_send_json_success( apply_filters( 'wp_easycart_ecv2_order_duplicate_payload', array(
		'order_id'       => $order_id,
		'date'           => $ts > 0 ? date_i18n( get_option( 'date_format' ), $ts ) : '',
		'status_id'      => (int) $order->orderstatus_id,
		'status_label'   => (string) $order->order_status,
		'customer_name'  => trim( wp_unslash( $order->billing_first_name . ' ' . $order->billing_last_name ) ),
		'email'          => (string) $order->user_email,
		'ship_to'        => array_map( 'wp_unslash', $ship_lines ),
		'items'          => $payload,
		/* JS derives prefix/suffix/separators from this sample for live totals. */
		'currency_sample' => $GLOBALS['currency']->get_currency_display( 1234.56 ),
		'totals'         => array(
			'sub_total'      => (float) $order->sub_total,
			'tax_total'      => (float) $order->tax_total + (float) $order->vat_total + (float) $order->gst_total + (float) $order->pst_total + (float) $order->hst_total + (float) $order->duty_total,
			'shipping_total' => (float) $order->shipping_total,
			'discount_total' => (float) $order->discount_total + (float) $order->offer_discount_total,
			'grand_total'    => (float) $order->grand_total,
			'has_promo'      => '' !== (string) $order->promo_code,
			'promo_code'     => (string) $order->promo_code,
		),
		'shipping'       => array(
			'method'    => wp_unslash( (string) $order->shipping_method ),
			'expedited' => (int) $order->use_expedited_shipping,
		),
		'has_customer_notes' => '' !== trim( (string) $order->order_customer_notes ),
	), $order ) );
}

add_action( 'wp_ajax_ecv2_order_duplicate_create', 'ecv2_order_duplicate_create' );
function ecv2_order_duplicate_create() {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_orders' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
	}
	if ( ! isset( $_POST['wp_easycart_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_easycart_nonce'] ) ), 'wp-easycart-ecv2-order-duplicate' ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wp-easycart' ) ) );
	}
	$order_id = isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : 0;
	if ( ! $order_id ) {
		wp_send_json_error( array( 'message' => __( 'Invalid request.', 'wp-easycart' ) ) );
	}

	global $wpdb;
	$original = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_order WHERE order_id = %d', $order_id ), ARRAY_A );
	if ( ! $original ) {
		wp_send_json_error( array( 'message' => __( 'Order not found.', 'wp-easycart' ) ) );
	}

	/* ---- Options from the drawer ---- */
	$status_id      = isset( $_POST['status_id'] ) ? (int) $_POST['status_id'] : (int) $original['orderstatus_id'];
	$pricing        = ( isset( $_POST['pricing'] ) && 'zero' === $_POST['pricing'] ) ? 'zero' : 'copy';
	$include_ship   = ! empty( $_POST['include_shipping'] );
	$include_disc   = ! empty( $_POST['include_discount'] );
	$keep_method    = ! empty( $_POST['keep_method'] );
	$copy_cust_note = ! empty( $_POST['copy_customer_notes'] );
	$send_receipt   = ! empty( $_POST['send_receipt'] );
	$mark_viewed    = ! empty( $_POST['mark_viewed'] );
	$note           = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
	$reason         = isset( $_POST['reason'] ) ? sanitize_key( $_POST['reason'] ) : 'reorder';

	/* Selected line items: array of orderdetail_id => quantity */
	$raw_items = isset( $_POST['items'] ) && is_array( $_POST['items'] ) ? $_POST['items'] : array();
	$selected  = array();
	foreach ( $raw_items as $detail_id => $qty ) {
		$detail_id = (int) $detail_id;
		$qty       = (int) $qty;
		if ( $detail_id > 0 && $qty > 0 ) {
			$selected[ $detail_id ] = $qty;
		}
	}
	if ( empty( $selected ) ) {
		wp_send_json_error( array( 'message' => __( 'Select at least one item to duplicate.', 'wp-easycart' ) ) );
	}

	$status = $wpdb->get_row( $wpdb->prepare( 'SELECT status_id, order_status FROM ec_orderstatus WHERE status_id = %d', $status_id ) );
	if ( ! $status ) {
		wp_send_json_error( array( 'message' => __( 'Invalid status.', 'wp-easycart' ) ) );
	}

	/* ---- Build the new order row ---- */
	$new = $original;
	unset( $new['order_id'], $new['order_date'] );

	/* Never carry these over: identity, payment, fulfillment, refunds, integrations. */
	$reset_blank = array(
		'tracking_number', 'shipping_carrier', 'shipping_service_code',
		'paypal_email_id', 'paypal_transaction_id', 'paypal_payer_id', 'txn_id', 'payment_txn_id',
		'edit_sequence', 'credit_memo_txn_id', 'card_holder_name', 'creditcard_digits', 'cc_exp_month', 'cc_exp_year',
		'fraktjakt_order_id', 'fraktjakt_shipment_id', 'stripe_charge_id', 'nets_transaction_id', 'affirm_charge_id',
		'gateway_transaction_id', 'guest_key', 'converted_cart_id', 'giftcard_id', 'order_notes',
	);
	foreach ( $reset_blank as $col ) {
		if ( array_key_exists( $col, $new ) ) {
			$new[ $col ] = '';
		}
	}
	$new['orderstatus_id']       = $status_id;
	$new['last_updated']         = current_time( 'mysql' );
	$new['order_viewed']         = $mark_viewed ? 1 : 0;
	$new['refund_total']         = 0;
	$new['shipping_refund_total']= 0;
	$new['tax_refund_total']     = 0;
	$new['subscription_id']      = 0;
	$new['success_page_shown']   = 0;
	$new['agreed_to_terms']      = 1;
	$new['quickbooks_status']    = 'Not Queued';
	$new['order_gateway']        = 'manual';
	$new['payment_method']       = 'manual';
	$new['order_ip_address']     = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	if ( ! $copy_cust_note ) {
		$new['order_customer_notes'] = '';
	}
	if ( ! $keep_method ) {
		$new['shipping_method']        = '';
		$new['use_expedited_shipping'] = 0;
	}

	/* ---- Line items + totals ---- */
	$details = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ec_orderdetail WHERE order_id = %d ORDER BY orderdetail_id ASC', $order_id ), ARRAY_A );
	$orig_sub = (float) $original['sub_total'];
	$new_sub  = 0.0;
	$new_details = array();

	foreach ( $details as $d ) {
		$did = (int) $d['orderdetail_id'];
		if ( ! isset( $selected[ $did ] ) ) {
			continue;
		}
		$qty = $selected[ $did ];
		$orig_qty = max( 1, (int) $d['quantity'] );
		$unit = ( 'zero' === $pricing ) ? 0.0 : (float) $d['unit_price'];

		$nd = $d;
		unset( $nd['orderdetail_id'] );
		$nd['quantity']          = $qty;
		$nd['refunded_quantity'] = 0;
		$nd['stock_adjusted']    = 0;
		$nd['unit_price']        = $unit;
		$nd['total_price']       = $unit * $qty;
		if ( 'zero' === $pricing ) {
			$nd['unit_discount_promotion']  = 0;
			$nd['unit_discount_coupon']     = 0;
			$nd['total_discount_promotion'] = 0;
			$nd['total_discount_coupon']    = 0;
			$nd['subscription_signup_fee']  = 0;
			$nd['applied_offers']           = '';
		} else {
			/* Scale line-level discounts to the new quantity. */
			$nd['total_discount_promotion'] = (float) $d['unit_discount_promotion'] * $qty;
			$nd['total_discount_coupon']    = (float) $d['unit_discount_coupon'] * $qty;
		}
		/* Downloads get a fresh key so the original's link isn't shared. */
		if ( ! empty( $d['is_download'] ) ) {
			$nd['download_key'] = wp_generate_password( 32, false );
		}
		$new_sub += (float) $nd['total_price'];
		$new_details[ $did ] = $nd;
	}

	$ratio = ( $orig_sub > 0 && 'copy' === $pricing ) ? min( 1.0, $new_sub / $orig_sub ) : 0.0;
	$tax_cols = array( 'tax_total', 'vat_total', 'duty_total', 'gst_total', 'pst_total', 'hst_total' );
	foreach ( $tax_cols as $col ) {
		$new[ $col ] = round( (float) $original[ $col ] * $ratio, 3 );
	}
	$new['sub_total']            = round( $new_sub, 3 );
	$new['tip_total']            = 0;
	$new['shipping_total']       = ( $include_ship && 'copy' === $pricing ) ? (float) $original['shipping_total'] : 0;
	$new['discount_total']       = ( $include_disc && 'copy' === $pricing ) ? round( (float) $original['discount_total'] * $ratio, 3 ) : 0;
	$new['offer_discount_total'] = ( $include_disc && 'copy' === $pricing ) ? round( (float) $original['offer_discount_total'] * $ratio, 3 ) : 0;
	if ( ! $include_disc || 'zero' === $pricing ) {
		$new['promo_code']         = '';
		$new['promo_code_message'] = '';
		$new['applied_offers']     = '';
	}
	$new['grand_total'] = round(
		$new['sub_total'] + $new['tax_total'] + $new['vat_total'] + $new['duty_total'] + $new['gst_total'] + $new['pst_total'] + $new['hst_total']
		+ $new['shipping_total'] - $new['discount_total'] - $new['offer_discount_total'],
		3
	);
	if ( $new['grand_total'] < 0 ) {
		$new['grand_total'] = 0;
	}

	/* Internal note: always record provenance, plus whatever the admin typed. */
	$reason_labels = array(
		'reorder'     => __( 'Reorder', 'wp-easycart' ),
		'replacement' => __( 'Replacement / reship', 'wp-easycart' ),
		'custom'      => __( 'Custom', 'wp-easycart' ),
	);
	$provenance = sprintf( __( 'Duplicated from order #%1$d (%2$s) on %3$s.', 'wp-easycart' ), $order_id, isset( $reason_labels[ $reason ] ) ? $reason_labels[ $reason ] : $reason, date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) );
	$new['order_notes'] = trim( $provenance . ( '' !== $note ? "\n" . $note : '' ) );

	$new = apply_filters( 'wp_easycart_admin_duplicate_order_original', $new ); /* legacy filter kept */
	$new = apply_filters( 'wp_easycart_ecv2_order_duplicate_row', $new, $original, $_POST );

	/* ---- Insert ---- */
	$wpdb->insert( 'ec_order', $new );
	$new_order_id = (int) $wpdb->insert_id;
	if ( ! $new_order_id ) {
		wp_send_json_error( array( 'message' => __( 'Could not create the duplicate order.', 'wp-easycart' ) ) );
	}

	foreach ( $new_details as $orig_detail_id => $nd ) {
		$nd['order_id'] = $new_order_id;
		$wpdb->insert( 'ec_orderdetail', $nd );
		$new_detail_id = (int) $wpdb->insert_id;

		/* Option rows (the legacy duplicate dropped these — product options were lost). */
		$opts = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ec_order_option WHERE orderdetail_id = %d', $orig_detail_id ), ARRAY_A );
		foreach ( $opts as $opt ) {
			unset( $opt['order_option_id'] );
			$opt['orderdetail_id'] = $new_detail_id;
			if ( 'zero' === $pricing ) {
				$opt['optionitem_price']          = 0;
				$opt['optionitem_price_onetime']  = 0;
			}
			$wpdb->insert( 'ec_order_option', $opt );
		}
	}

	/* Order-level fees only when copying prices in full. */
	if ( 'copy' === $pricing && $ratio >= 0.999 ) {
		$fees = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ec_order_fee WHERE order_id = %d', $order_id ), ARRAY_A );
		foreach ( $fees as $fee ) {
			unset( $fee['order_fee_id'] );
			$fee['order_id'] = $new_order_id;
			$wpdb->insert( 'ec_order_fee', $fee );
		}
	}

	/* ---- Logs on both orders ---- */
	$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log( order_id, order_log_key ) VALUES( %d, "order-duplicated-from" )', $new_order_id ) );
	$log_id = $wpdb->insert_id;
	$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "source_order_id", %s )', $log_id, $new_order_id, $order_id ) );
	$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "reason", %s )', $log_id, $new_order_id, $reason ) );
	$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log( order_id, order_log_key ) VALUES( %d, "order-duplicated-to" )', $order_id ) );
	$log_id = $wpdb->insert_id;
	$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "new_order_id", %s )', $log_id, $order_id, $new_order_id ) );

	do_action( 'wpeasycart_order_status_update', $new_order_id, $status_id );
	do_action( 'wp_easycart_ecv2_order_duplicated', $new_order_id, $order_id, $_POST );

	$email_sent = false;
	if ( $send_receipt && function_exists( 'wp_easycart_admin_orders' ) ) {
		wp_easycart_admin_orders()->resend_receipt( $new_order_id );
		$email_sent = true;
	}

	wp_send_json_success( array(
		'order_id'     => $new_order_id,
		'source_id'    => $order_id,
		'grand_total'  => $GLOBALS['currency']->get_currency_display( (float) $new['grand_total'] ),
		'status_label' => $status->order_status,
		'email_sent'   => $email_sent,
		'edit_url'     => admin_url( 'admin.php?page=wp-easycart-orders&subpage=orders&ec_admin_form_action=edit&order_id=' . $new_order_id ),
	) );
}