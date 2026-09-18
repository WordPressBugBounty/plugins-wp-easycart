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
				/* translators: %s: new quantity on hand. */
				'saved_set'      => __( 'Quantity set to %s.', 'wp-easycart' ),
				/* translators: 1: units added, 2: new quantity on hand. */
				'saved_add'      => __( 'Inventory added: %1$s. New total %2$s.', 'wp-easycart' ),
				/* translators: 1: units removed, 2: new quantity on hand. */
				'saved_remove'   => __( 'Inventory removed: %1$s. New total %2$s.', 'wp-easycart' ),
				/* translators: %s: quantity on hand. */
				'saved_none'     => __( 'No change. Quantity is still %s.', 'wp-easycart' ),
				'error'          => __( 'Something went wrong. Please try again.', 'wp-easycart' ),
				'threshold_saved'=> __( 'Low stock threshold saved.', 'wp-easycart' ),
				'out'            => __( 'Out of Stock', 'wp-easycart' ),
				'in_stock'       => __( '%s in stock', 'wp-easycart' ),
				'low'            => __( 'Low', 'wp-easycart' ),
				'unlimited'      => __( 'Unlimited', 'wp-easycart' ),
				'tracking_started' => __( 'Stock tracking started.', 'wp-easycart' ),
				'tracking_stopped' => __( 'Stock tracking stopped — the item is unlimited again.', 'wp-easycart' ),
				'stop_confirm_t' => __( 'Stop tracking stock for this item?', 'wp-easycart' ),
				'stop_confirm_b' => __( 'It becomes unlimited: customers can always buy it and it leaves low-stock alerts. The current quantity is kept in case you turn tracking back on.', 'wp-easycart' ),
				'preview_set'    => __( 'Will be %s', 'wp-easycart' ),
				'preview_add'    => __( '%1$s + %2$s = %3$s', 'wp-easycart' ),
				'preview_remove' => __( '%1$s − %2$s = %3$s', 'wp-easycart' ),
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
		 *   @type string $reason                Machine key, e.g. manual-set, manual-add, manual-remove,
		 *                                       tracking-started, received, recount, damaged, correction,
		 *                                       restock-refund, order, import.
		 *   @type string $source                manual | manual-set | order | import | square | api.
		 *                                       manual-set marks an absolute quantity typed by a merchant
		 *                                       ( so history can say "set" even when a reason was chosen ).
		 *   @type string $note                  Optional free-text note.
		 *   @type int    $floor                 Optional lowest quantity a removal may leave, e.g. 0 ( since 6.0.0 ).
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
				'floor'                 => null,
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
			if ( 'adjust' === $args['mode'] && $value < 0 && null !== $args['floor'] ) {
				/* A removal never pushes stock below the floor ( and never lowers stock that is already below it ). */
				$new = max( $new, min( $old, (int) $args['floor'] ) );
			}
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

			global $wpdb;
			$product_id = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
			$oiq_id = isset( $_POST['oiq_id'] ) ? (int) $_POST['oiq_id'] : 0;
			/* Optional tracked flag: 1 = start tracking ( with the quantity below ), 0 = stop tracking ( quantity left as is, item becomes unlimited ). */
			$tracked = isset( $_POST['tracked'] ) && '' !== $_POST['tracked'] ? ( (int) $_POST['tracked'] ? 1 : 0 ) : null;
			if ( null !== $tracked ) {
				if ( $oiq_id ) { $wpdb->update( 'ec_optionitemquantity', array( 'is_stock_tracking_enabled' => $tracked ), array( 'optionitemquantity_id' => $oiq_id ) ); }
				else if ( $product_id ) { $wpdb->update( 'ec_product', array( 'show_stock_quantity' => $tracked ), array( 'product_id' => $product_id ) ); }
				do_action( 'wp_easycart_inventory_tracking_changed', $product_id, $oiq_id, $tracked );
				if ( $product_id ) { do_action( 'wp_easycart_product_updated', $product_id ); }
			}
			if ( 0 === $tracked ) {
				wp_cache_flush();
				wp_send_json_success( array( 'tracked' => 0, 'quantity' => (int) ( $oiq_id ? $wpdb->get_var( $wpdb->prepare( 'SELECT quantity FROM ec_optionitemquantity WHERE optionitemquantity_id = %d', $oiq_id ) ) : $wpdb->get_var( $wpdb->prepare( 'SELECT stock_quantity FROM ec_product WHERE product_id = %d', $product_id ) ) ), 'threshold' => wp_easycart_admin_inventory_table::low_stock_threshold() ) );
			}

			/*
			 * Popover mode: 'set' writes an absolute quantity; 'add' / 'remove' send the amount and are applied
			 * server-side as a delta against the stored quantity ( so history records "Inventory added / removed"
			 * rather than "Quantity set", and a stale row can't overwrite a newer quantity ). Requests without a
			 * mode ( older cached scripts ) keep the absolute-set behavior.
			 */
			$mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'set';
			if ( 1 === $tracked || ! in_array( $mode, array( 'set', 'add', 'remove' ), true ) ) {
				$mode = 'set';
			}
			$amount = isset( $_POST['amount'] ) ? max( 0, (int) $_POST['amount'] ) : 0;

			if ( 1 === $tracked ) {
				$reason = 'tracking-started';
			} elseif ( 'add' === $mode ) {
				$reason = 'manual-add';
			} elseif ( 'remove' === $mode ) {
				$reason = 'manual-remove';
			} else {
				$reason = 'manual-set';
			}

			/**
			 * Filters the reason recorded for a change made from the inventory list's On hand popover.
			 * PRO validates the optional reason picked in the popover and returns it when licensed;
			 * without PRO the default action key above is recorded.
			 *
			 * @since 6.0.0
			 *
			 * @param string $reason    Default machine key ( manual-set, manual-add, manual-remove, tracking-started ).
			 * @param string $requested Reason key posted by the popover ( may be empty ).
			 * @param string $mode      set | add | remove.
			 */
			$requested = isset( $_POST['reason'] ) ? sanitize_key( wp_unslash( $_POST['reason'] ) ) : '';
			$reason    = (string) apply_filters( 'wp_easycart_admin_inventory_qty_reason', $reason, $requested, $mode );

			/**
			 * Filters whether a popover change may be saved without a valid reason. Return a non-empty
			 * message to refuse the save; nothing is written and the popover marks its reason field.
			 * PRO requires a reason ( like Bulk Update ) when licensed. Without PRO nothing is required.
			 *
			 * @since 6.0.0
			 *
			 * @param string $message   Refusal message ( empty = allowed ).
			 * @param string $requested Sanitized reason key posted by the popover ( may be empty ).
			 * @param string $mode      set | add | remove.
			 * @param array  $context   { tracked: null|1, has_mode: bool ( false for scripts older than 6.0.0 ), product_id, oiq_id }.
			 */
			$reason_error = (string) apply_filters(
				'wp_easycart_admin_inventory_qty_reason_error',
				'',
				$requested,
				$mode,
				array(
					'tracked'    => $tracked,
					'has_mode'   => isset( $_POST['mode'] ),
					'product_id' => $product_id,
					'oiq_id'     => $oiq_id,
				)
			);
			if ( '' !== $reason_error ) {
				wp_send_json_error(
					array(
						'message' => $reason_error,
						'field'   => 'reason',
					)
				);
			}

			if ( 'set' === $mode ) {
				$stock_args = array(
					'mode'   => 'set',
					'value'  => isset( $_POST['quantity'] ) ? max( 0, (int) $_POST['quantity'] ) : 0,
					'source' => 'manual-set',
				);
			} else {
				$stock_args = array(
					'mode'   => 'adjust',
					'value'  => ( 'remove' === $mode ) ? -1 * $amount : $amount,
					'source' => 'manual',
					'floor'  => 0,
				);
			}

			$stock_args['product_id']            = $product_id;
			$stock_args['optionitemquantity_id'] = $oiq_id;
			$stock_args['reason']                = $reason;
			$change                              = $this->apply_stock_change( $stock_args );

			if ( false === $change ) {
				wp_send_json_error( array( 'message' => __( 'Item not found.', 'wp-easycart' ) ) );
			}

			wp_send_json_success( array(
				'quantity'     => $change['new_quantity'],
				'old_quantity' => $change['old_quantity'],
				'delta'        => $change['delta'],
				'mode'         => $mode,
				'tracked'      => 1,
				'threshold'    => wp_easycart_admin_inventory_table::low_stock_threshold(),
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
			/* 6.0.0: one store-wide setting, edited in Settings > Checkout > Stock alerts.
			   This handler stays for any cached admin script and now writes that key. */
			update_option( 'ec_option_low_stock_trigger_total', $threshold );
			wp_send_json_success( array( 'threshold' => $threshold ) );
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

		/**
		 * The export's column keys and header labels, in file order.
		 *
		 * Round-trip contract ( @since 6.0.0 ): the PRO inventory importer reads this file back by
		 * header name. Product ID and Variant ID ( ec_optionitemquantity.optionitemquantity_id, empty
		 * on product rows ) identify a stock row even when variants share their parent's SKU, so
		 * an edited export always re-imports unambiguously; SKU and Quantity are the fallback for
		 * hand-made files. Labels are translated, and the importer accepts the translations too.
		 * PRO also builds its sample CSV from this list.
		 *
		 * @since 6.0.0
		 *
		 * @return array column key => header label.
		 */
		public static function export_header() {
			$header = array(
				'product_id' => __( 'Product ID', 'wp-easycart' ),
				'variant_id' => __( 'Variant ID', 'wp-easycart' ),
				'product'    => __( 'Product', 'wp-easycart' ),
				'variant'    => __( 'Variant', 'wp-easycart' ),
				'sku'        => __( 'SKU', 'wp-easycart' ),
				'type'       => __( 'Type', 'wp-easycart' ),
				'tracked'    => __( 'Tracked', 'wp-easycart' ),
				'quantity'   => __( 'Quantity', 'wp-easycart' ),
				'status'     => __( 'Status', 'wp-easycart' ),
			);
			/**
			 * Filters the inventory export header. Keys are the column keys ( product_id, variant_id, product,
			 * variant, sku, type, tracked, quantity, status ); values the labels written to the file. A column
			 * appended here must also be appended to every row through wp_easycart_admin_inventory_export_row.
			 *
			 * @param array $header column key => label.
			 */
			return apply_filters( 'wp_easycart_admin_inventory_export_header', $header );
		}

		/**
		 * Nonced URL of the export route. `$tracked_only` limits the file to items that track stock.
		 *
		 * @since 6.0.0
		 *
		 * @param bool $tracked_only
		 * @return string
		 */
		public static function export_url( $tracked_only = false ) {
			$args = array(
				'page'                 => 'wp-easycart-products',
				'subpage'              => 'inventory',
				'ec_admin_form_action' => 'export-inventory-list',
				'wp_easycart_nonce'    => wp_create_nonce( 'wp-easycart-export-inventory' ),
			);
			if ( $tracked_only ) {
				$args['tracked'] = '1';
			}
			return add_query_arg( $args, admin_url( 'admin.php' ) );
		}

		public function export_inventory_list() {
			global $wpdb;

			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename=Inventory-Export_' . date( 'Y-m-d' ) . '.csv' );

			$output = fopen( 'php://output', 'w' );

			fputcsv( $output, array_values( self::export_header() ) );

			/* Optional: only items that track stock ( the ones an import can change ). Nonce checked in process_export_inventory(). */
			$tracked_only = ( isset( $_GET['tracked'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['tracked'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified by verify_access() in process_export_inventory().

			$threshold = wp_easycart_admin_inventory_table::low_stock_threshold();

			/*
			 * Streamed in 2,000-row chunks — first the plain products, then the tracked
			 * variants — so a store with hundreds of thousands of rows never holds the whole
			 * inventory in memory. Columns and the export_row filter are unchanged.
			 *
			 * @since 6.0.0
			 */
			$chunk       = 2000;
			$flush_chunk = function() {
				if ( function_exists( 'ob_get_level' ) && ob_get_level() > 0 ) {
					@ob_flush(); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a non-flushable buffer is harmless here.
				}
				flush();
			};
			$write_row = function( $row ) use ( $output, $threshold ) {
				if ( ! $row->tracked ) {
					$status = __( 'Not Tracked', 'wp-easycart' );
				} else if ( $row->quantity <= 0 ) {
					$status = __( 'Out of Stock', 'wp-easycart' );
				} else if ( $row->quantity <= $threshold ) {
					$status = __( 'Low Stock', 'wp-easycart' );
				} else {
					$status = __( 'In Stock', 'wp-easycart' );
				}

				/* Same order as export_header(): the two ids first, then the columns the file always had. */
				$csv_row = array(
					(int) $row->product_id,
					( (int) $row->oiq_id > 0 ) ? (int) $row->oiq_id : '',
					wp_unslash( $row->title ),
					wp_unslash( $row->variant_label ),
					$row->sku,
					( 'variant' == $row->row_type ) ? __( 'Variant', 'wp-easycart' ) : __( 'Product', 'wp-easycart' ),
					$row->tracked ? __( 'Yes', 'wp-easycart' ) : __( 'No', 'wp-easycart' ),
					$row->tracked ? $row->quantity : '',
					$status,
				);
				/**
				 * Filters one export row before it is written. $csv_row is positional, in export_header()
				 * order ( product_id, variant_id, product, variant, sku, type, tracked, quantity, status );
				 * $row carries product_id, oiq_id, title, variant_label, sku, row_type, tracked, quantity.
				 *
				 * @param array  $csv_row Cells in header order.
				 * @param object $row     The database row.
				 */
				$csv_row = apply_filters( 'wp_easycart_admin_inventory_export_row', $csv_row, $row );
				fputcsv( $output, $csv_row );
			};

			/* Literal WHERE fragments picked by the boolean above; they carry no input. */
			$tracked_product_sql = $tracked_only ? ' AND p.show_stock_quantity = 1' : '';
			$tracked_variant_sql = $tracked_only ? ' AND q.is_stock_tracking_enabled = 1' : '';

			/* Products that track stock at product level ( keyed on product_id ). */
			$last = 0;
			do {
				$rows = $wpdb->get_results( $wpdb->prepare(
					"SELECT p.title, '' AS variant_label, p.model_number AS sku, 'product' AS row_type,
						p.show_stock_quantity AS tracked, p.stock_quantity AS quantity,
						p.product_id, 0 AS oiq_id
					FROM ec_product p
					WHERE p.use_optionitem_quantity_tracking = 0 AND p.activate_in_store = 1 AND p.product_id > %d"
					. $tracked_product_sql . ' ORDER BY p.product_id ASC LIMIT %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $tracked_product_sql is one of two literal fragments chosen by a boolean.
					$last,
					$chunk
				) );
				foreach ( $rows as $row ) {
					$last = (int) $row->product_id;
					$write_row( $row );
				}
				$flush_chunk();
			} while ( count( $rows ) === $chunk );

			/* Variant rows of products that track stock per variation ( keyed on optionitemquantity_id ). */
			$last = 0;
			do {
				$rows = $wpdb->get_results( $wpdb->prepare(
					"SELECT p.title,
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
					WHERE q.is_enabled = 1 AND p.activate_in_store = 1 AND q.optionitemquantity_id > %d"
					. $tracked_variant_sql . ' ORDER BY q.optionitemquantity_id ASC LIMIT %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $tracked_variant_sql is one of two literal fragments chosen by a boolean.
					$last,
					$chunk
				) );
				foreach ( $rows as $row ) {
					$last = (int) $row->oiq_id;
					$write_row( $row );
				}
				$flush_chunk();
			} while ( count( $rows ) === $chunk );
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