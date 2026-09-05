<?php
/**
 * WP EasyCart Admin Inventory ( V2 controller )
 *
 * Routes the Products > Inventory subpage to the modern
 * wp_easycart_admin_inventory_table grid, owns the central stock-change
 * helper ( which fires 'wpeasycart_inventory_stock_changed' so PRO can
 * write the audit log ), the CSV export, and the free AJAX endpoints.
 *
 * @since 6.x.x ( rebuilt from the legacy single-template inventory list )
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_inventory' ) ) :

	final class wp_easycart_admin_inventory {

		protected static $_instance = null;

		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		public function __construct() {
			add_action( 'wp_easycart_process_get_form_action', array( $this, 'process_export_inventory' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ), 15 );
		}

		public static function is_inventory_page() {
			return ( isset( $_GET['page'] ) && 'wp-easycart-products' == $_GET['page'] && isset( $_GET['subpage'] ) && 'inventory' == $_GET['subpage'] );
		}

		public static function user_can_manage() {
			return ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_products' ) );
		}

		/* ------------------------------------------------------------------ */
		/* Page                                                                 */
		/* ------------------------------------------------------------------ */

		public function load_inventory_list() {
			wp_easycart_admin_verification()->print_nonce_field( 'wp_easycart_inventory_nonce', 'wp-easycart-inventory' );
			$table = new wp_easycart_admin_inventory_table();
			$table->print_table();
		}

		public function enqueue_assets() {
			if ( ! self::is_inventory_page() ) {
				return;
			}

			wp_register_style( 'wp_easycart_admin_inventory_v2_css', plugins_url( 'wp-easycart/admin/css/admin-inventory-v2.css', EC_PLUGIN_DIRECTORY ), array( 'wp_easycart_admin_v2_css' ), EC_CURRENT_VERSION );
			wp_enqueue_style( 'wp_easycart_admin_inventory_v2_css' );

			wp_register_script( 'wp_easycart_admin_inventory_v2_js', plugins_url( 'wp-easycart/admin/js/inventory-v2.js', EC_PLUGIN_DIRECTORY ), array( 'jquery' ), EC_CURRENT_VERSION );
			wp_enqueue_script( 'wp_easycart_admin_inventory_v2_js' );

			wp_localize_script( 'wp_easycart_admin_inventory_v2_js', 'ecv2i_nonces', array(
				'set_qty'        => wp_create_nonce( 'wp-easycart-ecv2i-set-qty' ),
				'save_threshold' => wp_create_nonce( 'wp-easycart-ecv2i-threshold' ),
			) );
			wp_localize_script( 'wp_easycart_admin_inventory_v2_js', 'ecv2i_lang', array(
				'saved'          => __( 'Inventory updated.', 'wp-easycart' ),
				'error'          => __( 'Something went wrong. Please try again.', 'wp-easycart' ),
				'threshold_saved'=> __( 'Low stock threshold saved.', 'wp-easycart' ),
				'out'            => __( 'Out', 'wp-easycart' ),
				'low'            => __( 'Low', 'wp-easycart' ),
				'confirm'        => __( 'Confirm', 'wp-easycart' ),
				'cancel'         => __( 'Cancel', 'wp-easycart' ),
				'pro_required'   => __( 'This feature requires WP EasyCart PRO.', 'wp-easycart' ),
				'select_rows'    => __( 'Select at least one row first.', 'wp-easycart' ),
			) );
			wp_localize_script( 'wp_easycart_admin_inventory_v2_js', 'ecv2i_config', array(
				'threshold' => wp_easycart_admin_inventory_table::low_stock_threshold(),
			) );
		}

		/* ------------------------------------------------------------------ */
		/* Central stock-change helper                                          */
		/* ------------------------------------------------------------------ */

		/**
		 * Apply a stock change to a product or a variant row and broadcast it.
		 *
		 * @param array $args {
		 *   @type int    $product_id            Required.
		 *   @type int    $optionitemquantity_id 0 for product-level stock.
		 *   @type string $mode                  'set' or 'adjust'.
		 *   @type int    $value                 New quantity ( set ) or delta ( adjust ).
		 *   @type string $reason                Machine key, e.g. manual-set, received, recount,
		 *                                       damaged, correction, restock-refund, order, import.
		 *   @type string $source                manual | order | import | square | api.
		 *   @type string $note                  Optional free-text note.
		 * }
		 * @return array|false Change data on success ( old_quantity, new_quantity, delta, ... ).
		 */
		public function apply_stock_change( $args ) {
			global $wpdb;

			$defaults = array(
				'product_id'            => 0,
				'optionitemquantity_id' => 0,
				'mode'                  => 'set',
				'value'                 => 0,
				'reason'                => 'manual-set',
				'source'                => 'manual',
				'note'                  => '',
			);
			$args = wp_parse_args( $args, $defaults );

			$product_id = (int) $args['product_id'];
			$oiq_id = (int) $args['optionitemquantity_id'];
			$value = (int) $args['value'];
			if ( $product_id <= 0 ) {
				return false;
			}

			if ( $oiq_id > 0 ) {
				$old = $wpdb->get_var( $wpdb->prepare( 'SELECT quantity FROM ec_optionitemquantity WHERE optionitemquantity_id = %d AND product_id = %d', $oiq_id, $product_id ) );
			} else {
				$old = $wpdb->get_var( $wpdb->prepare( 'SELECT stock_quantity FROM ec_product WHERE product_id = %d', $product_id ) );
			}
			if ( null === $old ) {
				return false;
			}
			$old = (int) $old;

			$new = ( 'adjust' === $args['mode'] ) ? $old + $value : $value;
			$delta = $new - $old;

			if ( $oiq_id > 0 ) {
				$wpdb->query( $wpdb->prepare( 'UPDATE ec_optionitemquantity SET quantity = %d WHERE optionitemquantity_id = %d AND product_id = %d', $new, $oiq_id, $product_id ) );
				/* Keep the parent product's rolled-up total in sync. */
				$wpdb->query( $wpdb->prepare( 'UPDATE ec_product SET stock_quantity = ( SELECT COALESCE( SUM( quantity ), 0 ) FROM ec_optionitemquantity WHERE product_id = %d AND is_enabled = 1 ) WHERE product_id = %d', $product_id, $product_id ) );
			} else {
				$wpdb->query( $wpdb->prepare( 'UPDATE ec_product SET stock_quantity = %d WHERE product_id = %d', $new, $product_id ) );
			}

			$change = array(
				'product_id'            => $product_id,
				'optionitemquantity_id' => $oiq_id,
				'old_quantity'          => $old,
				'new_quantity'          => $new,
				'delta'                 => $delta,
				'reason'                => sanitize_key( $args['reason'] ),
				'source'                => sanitize_key( $args['source'] ),
				'note'                  => sanitize_text_field( $args['note'] ),
				'user_id'               => get_current_user_id(),
			);

			/**
			 * Fires after any inventory quantity write made through the
			 * central helper. PRO hooks this to record ec_inventory_log
			 * entries. Third parties may hook it for sync purposes.
			 *
			 * @param array $change See apply_stock_change() docblock.
			 */
			do_action( 'wpeasycart_inventory_stock_changed', $change );

			return $change;
		}

		/* ------------------------------------------------------------------ */
		/* AJAX                                                                 */
		/* ------------------------------------------------------------------ */

		public function ajax_set_qty() {
			if ( ! self::user_can_manage() ) {
				wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
			}
			if ( ! isset( $_POST['wp_easycart_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_easycart_nonce'] ) ), 'wp-easycart-ecv2i-set-qty' ) ) {
				wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wp-easycart' ) ) );
			}

			$change = $this->apply_stock_change( array(
				'product_id'            => isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0,
				'optionitemquantity_id' => isset( $_POST['oiq_id'] ) ? (int) $_POST['oiq_id'] : 0,
				'mode'                  => 'set',
				'value'                 => isset( $_POST['quantity'] ) ? (int) $_POST['quantity'] : 0,
				'reason'                => 'manual-set',
				'source'                => 'manual',
			) );

			if ( false === $change ) {
				wp_send_json_error( array( 'message' => __( 'Item not found.', 'wp-easycart' ) ) );
			}

			wp_send_json_success( array(
				'quantity'  => $change['new_quantity'],
				'delta'     => $change['delta'],
				'threshold' => wp_easycart_admin_inventory_table::low_stock_threshold(),
			) );
		}

		public function ajax_save_threshold() {
			if ( ! self::user_can_manage() ) {
				wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
			}
			if ( ! isset( $_POST['wp_easycart_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_easycart_nonce'] ) ), 'wp-easycart-ecv2i-threshold' ) ) {
				wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wp-easycart' ) ) );
			}
			$threshold = isset( $_POST['threshold'] ) ? (int) $_POST['threshold'] : 0;
			if ( $threshold < 1 ) {
				wp_send_json_error( array( 'message' => __( 'Threshold must be at least 1.', 'wp-easycart' ) ) );
			}
			update_option( 'ec_option_inventory_low_stock_threshold', $threshold );
			wp_send_json_success( array( 'threshold' => $threshold ) );
		}

		/**
		 * Legacy AJAX handler ( kept for backward compatibility with any
		 * cached admin scripts ). Routes through the central helper so
		 * changes still broadcast + log.
		 */
		public function update_inventory_item() {
			$oiq_id = ( isset( $_POST['quantity_id'] ) ) ? (int) $_POST['quantity_id'] : 0;
			$this->apply_stock_change( array(
				'product_id'            => ( isset( $_POST['product_id'] ) ) ? (int) $_POST['product_id'] : 0,
				'optionitemquantity_id' => ( $oiq_id > 0 ) ? $oiq_id : 0,
				'mode'                  => 'set',
				'value'                 => ( isset( $_POST['quantity'] ) ) ? (int) $_POST['quantity'] : 0,
				'reason'                => 'manual-set',
				'source'                => 'manual',
			) );
		}

		/* ------------------------------------------------------------------ */
		/* CSV export                                                           */
		/* ------------------------------------------------------------------ */

		public function process_export_inventory() {
			if ( ! self::user_can_manage() ) {
				return;
			}
			if ( isset( $_GET['subpage'] ) && 'inventory' == $_GET['subpage'] && isset( $_GET['ec_admin_form_action'] ) && 'export-inventory-list' == $_GET['ec_admin_form_action'] ) {
				if ( wp_easycart_admin_verification()->verify_access( 'wp-easycart-export-inventory' ) ) {
					$this->export_inventory_list();
				}
			}
		}

		public function export_inventory_list() {
			global $wpdb;

			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename=Inventory-Export_' . date( 'Y-m-d' ) . '.csv' );

			$output = fopen( 'php://output', 'w' );

			$header = array(
				__( 'Product', 'wp-easycart' ),
				__( 'Variant', 'wp-easycart' ),
				__( 'SKU', 'wp-easycart' ),
				__( 'Type', 'wp-easycart' ),
				__( 'Tracked', 'wp-easycart' ),
				__( 'Quantity', 'wp-easycart' ),
				__( 'Status', 'wp-easycart' ),
			);
			$header = apply_filters( 'wp_easycart_admin_inventory_export_header', $header );
			fputcsv( $output, $header );

			$threshold = wp_easycart_admin_inventory_table::low_stock_threshold();

			$rows = $wpdb->get_results(
				"( SELECT p.title, '' AS variant_label, p.model_number AS sku, 'product' AS row_type,
					p.show_stock_quantity AS tracked, p.stock_quantity AS quantity,
					p.product_id, 0 AS oiq_id
				FROM ec_product p WHERE p.use_optionitem_quantity_tracking = 0 AND p.activate_in_store = 1 )
				UNION ALL
				( SELECT p.title,
					CONCAT_WS( ', ', oi1.optionitem_name, oi2.optionitem_name, oi3.optionitem_name, oi4.optionitem_name, oi5.optionitem_name ) AS variant_label,
					( CASE WHEN q.sku != '' THEN q.sku ELSE p.model_number END ) AS sku,
					'variant' AS row_type,
					q.is_stock_tracking_enabled AS tracked, q.quantity,
					p.product_id, q.optionitemquantity_id AS oiq_id
				FROM ec_optionitemquantity q
				INNER JOIN ec_product p ON p.product_id = q.product_id AND p.use_optionitem_quantity_tracking = 1
				LEFT JOIN ec_optionitem oi1 ON oi1.optionitem_id = q.optionitem_id_1
				LEFT JOIN ec_optionitem oi2 ON oi2.optionitem_id = q.optionitem_id_2
				LEFT JOIN ec_optionitem oi3 ON oi3.optionitem_id = q.optionitem_id_3
				LEFT JOIN ec_optionitem oi4 ON oi4.optionitem_id = q.optionitem_id_4
				LEFT JOIN ec_optionitem oi5 ON oi5.optionitem_id = q.optionitem_id_5
				WHERE q.is_enabled = 1 AND p.activate_in_store = 1 )
				ORDER BY title ASC, variant_label ASC"
			);

			foreach ( $rows as $row ) {
				if ( ! $row->tracked ) {
					$status = __( 'Not Tracked', 'wp-easycart' );
				} else if ( $row->quantity <= 0 ) {
					$status = __( 'Out of Stock', 'wp-easycart' );
				} else if ( $row->quantity <= $threshold ) {
					$status = __( 'Low Stock', 'wp-easycart' );
				} else {
					$status = __( 'In Stock', 'wp-easycart' );
				}

				$csv_row = array(
					wp_unslash( $row->title ),
					wp_unslash( $row->variant_label ),
					$row->sku,
					( 'variant' == $row->row_type ) ? __( 'Variant', 'wp-easycart' ) : __( 'Product', 'wp-easycart' ),
					$row->tracked ? __( 'Yes', 'wp-easycart' ) : __( 'No', 'wp-easycart' ),
					$row->tracked ? $row->quantity : '',
					$status,
				);
				$csv_row = apply_filters( 'wp_easycart_admin_inventory_export_row', $csv_row, $row );
				fputcsv( $output, $csv_row );
			}
			die();
		}
	}
endif;

function wp_easycart_admin_inventory() {
	return wp_easycart_admin_inventory::instance();
}
wp_easycart_admin_inventory();

add_action( 'wp_ajax_ecv2_inventory_set_qty', 'ecv2_inventory_set_qty' );
function ecv2_inventory_set_qty() {
	wp_easycart_admin_inventory()->ajax_set_qty();
}

add_action( 'wp_ajax_ecv2_inventory_save_threshold', 'ecv2_inventory_save_threshold' );
function ecv2_inventory_save_threshold() {
	wp_easycart_admin_inventory()->ajax_save_threshold();
}

/* Legacy endpoint retained for backward compatibility. */
add_action( 'wp_ajax_ec_admin_ajax_update_inventory_item', 'ec_admin_ajax_update_inventory_item' );
function ec_admin_ajax_update_inventory_item() {
	if ( ! wp_easycart_admin_verification()->verify_access( 'wp-easycart-inventory' ) ) {
		return false;
	}
	wp_easycart_admin_inventory()->update_inventory_item();
	die();
}