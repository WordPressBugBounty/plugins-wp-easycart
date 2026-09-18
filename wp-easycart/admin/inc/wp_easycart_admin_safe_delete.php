<?php
/**
 * WP EasyCart — Safe Delete
 *
 * Deleting a catalog record used to be two DELETE statements that left every
 * other table pointing at nothing ( product slots, variant stock, images,
 * child categories, promotions ). This class makes delete a four-step flow:
 *
 *   1. analyze()  — impact report: every dependent record, counted and named,
 *                   plus the strategies that make sense for this record.
 *   2. Merchant picks a strategy in the modal ( replace / remove / lift / move / cascade ).
 *   3. execute()  — snapshots every row it is about to delete or modify into
 *                   the trash, then applies the strategy in dependency order.
 *   4. restore()  — puts the snapshot back ( original ids preserved ) within
 *                   the retention window. Surfaced as Undo in the toast and as
 *                   "Recently deleted" on Store Status.
 *
 * Supported types: 'option' ( option set ), 'category'.
 *
 * AJAX: ecv2_delete_impact, ecv2_delete_execute, ecv2_delete_restore.
 * Nonce: 'wp-easycart-safe-delete'.
 *
 * @since 5.x.x
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_safe_delete' ) ) :

	final class wp_easycart_admin_safe_delete {

		const NONCE          = 'wp-easycart-safe-delete';
		const TRASH_INDEX    = 'ec_option_trash_index';
		const TRASH_PREFIX   = 'ec_trash_';
		const RETENTION_DAYS = 30;

		protected static $_instance = null;

		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		public function __construct() {
			add_action( 'wp_ajax_ecv2_delete_impact', array( $this, 'ajax_impact' ) );
			add_action( 'wp_ajax_ecv2_delete_execute', array( $this, 'ajax_execute' ) );
			add_action( 'wp_ajax_ecv2_delete_restore', array( $this, 'ajax_restore' ) );
		}

		/* =====================================================================
		   IMPACT ANALYSIS
		   ===================================================================== */

		/**
		 * @return array|WP_Error {
		 *   type, id, name,
		 *   impacts: [ { key, label, count, severity: info|warn|block, detail: string, sample: [names] } ],
		 *   strategies: [ { key, label, description, recommended, requires_target, targets: [ {value,label} ] } ],
		 *   redirect: { available: bool, from: url, default_to: url }
		 * }
		 */
		public function analyze( $type, $id ) {
			$id = (int) $id;
			if ( 'option' === $type ) {
				return $this->analyze_option( $id );
			}
			if ( 'category' === $type ) {
				return $this->analyze_category( $id );
			}
			if ( in_array( $type, array( 'menu1', 'menu2', 'menu3' ), true ) ) {
				return $this->analyze_menu( (int) substr( $type, 4 ), $id );
			}
			if ( 'manufacturer' === $type ) {
				return $this->analyze_manufacturer( $id );
			}
			return new WP_Error( 'bad_type', __( 'Unknown record type.', 'wp-easycart' ) );
		}

		private function analyze_option( $option_id ) {
			global $wpdb;
			$set = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_option WHERE option_id = %d', $option_id ) );
			if ( ! $set ) {
				return new WP_Error( 'not_found', __( 'Option set not found.', 'wp-easycart' ) );
			}
			$item_ids = $wpdb->get_col( $wpdb->prepare( 'SELECT optionitem_id FROM ec_optionitem WHERE option_id = %d', $option_id ) );
			$items_in = $item_ids ? implode( ',', array_map( 'intval', $item_ids ) ) : '0';

			/* Products: free slots + PRO advanced attachments */
			$slot_products = $wpdb->get_results( $wpdb->prepare( 'SELECT product_id, title FROM ec_product WHERE option_id_1 = %d OR option_id_2 = %d OR option_id_3 = %d OR option_id_4 = %d OR option_id_5 = %d ORDER BY title LIMIT 200', $option_id, $option_id, $option_id, $option_id, $option_id ) );
			$slot_count    = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_product WHERE option_id_1 = %d OR option_id_2 = %d OR option_id_3 = %d OR option_id_4 = %d OR option_id_5 = %d', $option_id, $option_id, $option_id, $option_id, $option_id ) );
			$adv_count     = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_option_to_product WHERE option_id = %d', $option_id ) );
			$stock_rows    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM ec_optionitemquantity WHERE optionitem_id_1 IN ($items_in) OR optionitem_id_2 IN ($items_in) OR optionitem_id_3 IN ($items_in) OR optionitem_id_4 IN ($items_in) OR optionitem_id_5 IN ($items_in)" );
			$image_rows    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM ec_optionitemimage WHERE optionitem_id IN ($items_in)" );
			$in_carts      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM ec_tempcart WHERE optionitem_id_1 IN ($items_in) OR optionitem_id_2 IN ($items_in) OR optionitem_id_3 IN ($items_in) OR optionitem_id_4 IN ($items_in) OR optionitem_id_5 IN ($items_in)" );
			$order_lines   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM ec_orderdetail WHERE optionitem_id_1 IN ($items_in) OR optionitem_id_2 IN ($items_in) OR optionitem_id_3 IN ($items_in) OR optionitem_id_4 IN ($items_in) OR optionitem_id_5 IN ($items_in)" );
			$offer_targets = $this->table_exists( 'ec_offer_target' ) ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM ec_offer_target WHERE optionitem_id_1 IN ($items_in) OR optionitem_id_2 IN ($items_in) OR optionitem_id_3 IN ($items_in) OR optionitem_id_4 IN ($items_in) OR optionitem_id_5 IN ($items_in)" ) : 0;
			$cart_links    = $this->table_exists( 'ec_cart_link_item' ) ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM ec_cart_link_item WHERE optionitem_id_1 IN ($items_in) OR optionitem_id_2 IN ($items_in) OR optionitem_id_3 IN ($items_in) OR optionitem_id_4 IN ($items_in) OR optionitem_id_5 IN ($items_in)" ) : 0;
			$bundle_items  = $this->table_exists( 'ec_product_bundle_item' ) ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM ec_product_bundle_item WHERE optionitem_id_1 IN ($items_in) OR optionitem_id_2 IN ($items_in) OR optionitem_id_3 IN ($items_in) OR optionitem_id_4 IN ($items_in) OR optionitem_id_5 IN ($items_in)" ) : 0;

			$sample = array();
			foreach ( array_slice( $slot_products, 0, 5 ) as $p ) {
				$sample[] = $p->title;
			}
			$total_products = $slot_count + $adv_count;

			$impacts = array();
			$impacts[] = array( 'key' => 'choices', 'label' => __( 'Choices', 'wp-easycart' ), 'count' => count( $item_ids ), 'severity' => 'info', 'detail' => __( 'Deleted with the set.', 'wp-easycart' ), 'sample' => array() );
			$impacts[] = array( 'key' => 'products', 'label' => __( 'Products using this set', 'wp-easycart' ), 'count' => $total_products, 'severity' => $total_products ? 'warn' : 'info', 'detail' => $total_products ? __( 'The selector disappears from these product pages.', 'wp-easycart' ) : __( 'No products use this set.', 'wp-easycart' ), 'sample' => $sample, 'link' => admin_url( 'admin.php?page=wp-easycart-products&subpage=products&option_set=' . $option_id ) );
			if ( $stock_rows ) {
				$impacts[] = array( 'key' => 'stock', 'label' => __( 'Variant stock rows', 'wp-easycart' ), 'count' => $stock_rows, 'severity' => 'warn', 'detail' => __( 'Per-variant quantities are deleted. Choose “Replace” to keep matching ones.', 'wp-easycart' ), 'sample' => array() );
			}
			if ( $image_rows ) {
				$impacts[] = array( 'key' => 'images', 'label' => __( 'Per-choice product images', 'wp-easycart' ), 'count' => $image_rows, 'severity' => 'info', 'detail' => __( 'Image assignments are deleted; the image files stay in your media.', 'wp-easycart' ), 'sample' => array() );
			}
			if ( $in_carts ) {
				$impacts[] = array( 'key' => 'carts', 'label' => __( 'Items in active carts', 'wp-easycart' ), 'count' => $in_carts, 'severity' => 'warn', 'detail' => __( 'Shoppers with this choice in their cart will need to re-add the product.', 'wp-easycart' ), 'sample' => array() );
			}
			if ( $offer_targets || $cart_links || $bundle_items ) {
				$parts = array();
				if ( $offer_targets ) { $parts[] = sprintf( _n( '%d offer target', '%d offer targets', $offer_targets, 'wp-easycart' ), $offer_targets ); }
				if ( $cart_links ) { $parts[] = sprintf( _n( '%d cart link', '%d cart links', $cart_links, 'wp-easycart' ), $cart_links ); }
				if ( $bundle_items ) { $parts[] = sprintf( _n( '%d bundle item', '%d bundle items', $bundle_items, 'wp-easycart' ), $bundle_items ); }
				$impacts[] = array( 'key' => 'features', 'label' => __( 'Other features referencing these choices', 'wp-easycart' ), 'count' => $offer_targets + $cart_links + $bundle_items, 'severity' => 'warn', 'detail' => implode( ', ', $parts ) . '. ' . __( 'These keep working but will no longer match a choice.', 'wp-easycart' ), 'sample' => array() );
			}
			if ( $order_lines ) {
				$impacts[] = array( 'key' => 'orders', 'label' => __( 'Past order lines', 'wp-easycart' ), 'count' => $order_lines, 'severity' => 'info', 'detail' => __( 'Never touched. Order history keeps the choice names it recorded.', 'wp-easycart' ), 'sample' => array() );
			}

			/* Strategies */
			$targets = $wpdb->get_results( $wpdb->prepare( 'SELECT option_id AS value, CONCAT( option_name, " (", ( SELECT COUNT(*) FROM ec_optionitem WHERE ec_optionitem.option_id = ec_option.option_id ), ")" ) AS label FROM ec_option WHERE option_id != %d AND option_type IN ( %s, %s, %s, %s ) ORDER BY option_name', $option_id, 'basic-combo', 'basic-swatch', 'combo', 'swatch' ) );
			$strategies = array();
			if ( $total_products ) {
				$strategies[] = array(
					'key'             => 'replace',
					'label'           => __( 'Replace with another set on those products', 'wp-easycart' ),
					'description'     => __( 'Keeps a selector on every product. Variant stock rows whose choice names match the new set are preserved; the rest are deleted.', 'wp-easycart' ),
					'recommended'     => ! empty( $targets ),
					'requires_target' => true,
					'targets'         => $targets,
					'disabled'        => empty( $targets ),
				);
			}
			$strategies[] = array(
				'key'         => 'remove',
				'label'       => $total_products ? __( 'Remove from products and delete stock rows', 'wp-easycart' ) : __( 'Delete the set', 'wp-easycart' ),
				'description' => $total_products ? __( 'Products stay active without this option. All variant stock rows for it are deleted.', 'wp-easycart' ) : __( 'Nothing else references it.', 'wp-easycart' ),
				'recommended' => ! $total_products,
			);

			return array(
				'type'       => 'option',
				'id'         => $option_id,
				'name'       => $set->option_name,
				'impacts'    => $impacts,
				'strategies' => $strategies,
				'redirect'   => array( 'available' => false ),
				'undo_days'  => self::RETENTION_DAYS,
			);
		}

		private function analyze_category( $category_id ) {
			global $wpdb;
			$cat = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_category WHERE category_id = %d', $category_id ) );
			if ( ! $cat ) {
				return new WP_Error( 'not_found', __( 'Category not found.', 'wp-easycart' ) );
			}
			$children   = $wpdb->get_results( $wpdb->prepare( 'SELECT category_id, category_name FROM ec_category WHERE parent_id = %d ORDER BY category_name', $category_id ) );
			$desc_ids   = $this->descendant_ids( $category_id );
			$direct     = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_categoryitem WHERE category_id = %d', $category_id ) );
			$desc_in    = $desc_ids ? implode( ',', array_map( 'intval', $desc_ids ) ) : '0';
			$in_tree    = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT product_id) FROM ec_categoryitem WHERE category_id IN ($desc_in)" );
			$only_here  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_categoryitem ci WHERE ci.category_id = %d AND NOT EXISTS ( SELECT 1 FROM ec_categoryitem c2 WHERE c2.product_id = ci.product_id AND c2.category_id != %d )', $category_id, $category_id ) );
			$promos     = $this->table_exists( 'ec_promotion' ) ? (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_promotion WHERE category_id_1 = %d OR category_id_2 = %d OR category_id_3 = %d', $category_id, $category_id, $category_id ) ) : 0;
			$codes      = $this->table_exists( 'ec_promocode' ) ? (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_promocode WHERE category_id = %d OR by_category_id = %d', $category_id, $category_id ) ) : 0;
			$menu_items = $cat->post_id ? (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_menu_item_object_id' AND meta_value = %d", $cat->post_id ) ) : 0;
			$sample_p   = $wpdb->get_col( $wpdb->prepare( 'SELECT p.title FROM ec_categoryitem ci JOIN ec_product p ON p.product_id = ci.product_id WHERE ci.category_id = %d ORDER BY p.title LIMIT 5', $category_id ) );

			$impacts = array();
			$impacts[] = array( 'key' => 'products', 'label' => __( 'Products in this category', 'wp-easycart' ), 'count' => $direct, 'severity' => 'info', 'detail' => sprintf( __( 'Products are never deleted. %d of them are in no other category and would become uncategorized.', 'wp-easycart' ), $only_here ), 'sample' => $sample_p, 'link' => admin_url( 'admin.php?page=wp-easycart-products&subpage=products&filter_2=' . $category_id ) );
			if ( $children ) {
				$names = array();
				foreach ( array_slice( $children, 0, 5 ) as $c ) { $names[] = $c->category_name; }
				$impacts[] = array( 'key' => 'children', 'label' => __( 'Subcategories', 'wp-easycart' ), 'count' => count( $children ), 'severity' => 'warn', 'detail' => sprintf( __( '%d products live somewhere in this tree.', 'wp-easycart' ), $in_tree ), 'sample' => $names );
			}
			if ( $promos || $codes ) {
				$impacts[] = array( 'key' => 'promotions', 'label' => __( 'Promotions & coupon codes targeting it', 'wp-easycart' ), 'count' => $promos + $codes, 'severity' => 'warn', 'detail' => __( 'Their category rule is cleared so they no longer match anything. Review them under Marketing.', 'wp-easycart' ), 'sample' => array(), 'link' => admin_url( 'admin.php?page=wp-easycart-marketing' ) );
			}
			if ( $menu_items ) {
				$impacts[] = array( 'key' => 'menu', 'label' => __( 'Navigation menu items', 'wp-easycart' ), 'count' => $menu_items, 'severity' => 'warn', 'detail' => __( 'Removed from your menus so visitors never see a dead link.', 'wp-easycart' ), 'sample' => array() );
			}
			if ( $cat->post_id ) {
				$impacts[] = array( 'key' => 'page', 'label' => __( 'Category page', 'wp-easycart' ), 'count' => 1, 'severity' => 'info', 'detail' => get_permalink( $cat->post_id ), 'sample' => array() );
			}

			$targets = $wpdb->get_results( "SELECT category_id AS value, category_name AS label FROM ec_category WHERE category_id NOT IN ($desc_in) ORDER BY category_name" );
			$strategies = array();
			$strategies[] = array(
				'key'         => 'lift',
				'label'       => $children ? __( 'Move subcategories up a level, leave products uncategorized', 'wp-easycart' ) : __( 'Delete the category, leave products uncategorized', 'wp-easycart' ),
				'description' => $children ? __( 'Subcategories take this category\'s place in the tree. Direct products lose only this category.', 'wp-easycart' ) : __( 'Products lose only this category.', 'wp-easycart' ),
				'recommended' => true,
			);
			$strategies[] = array(
				'key'             => 'move',
				'label'           => __( 'Move everything to another category', 'wp-easycart' ),
				'description'     => __( 'Subcategories and direct products re-parent to the category you choose. Nothing becomes uncategorized.', 'wp-easycart' ),
				'requires_target' => true,
				'targets'         => $targets,
				'disabled'        => empty( $targets ),
			);
			if ( $children ) {
				$strategies[] = array(
					'key'         => 'cascade',
					'label'       => __( 'Delete subcategories too', 'wp-easycart' ),
					'description' => sprintf( __( 'Removes %1$d subcategories as well. %2$d products lose these categories.', 'wp-easycart' ), count( $desc_ids ) - 1, $in_tree ),
					'danger'      => true,
				);
			}

			$from = $cat->post_id ? get_permalink( $cat->post_id ) : '';
			return array(
				'type'       => 'category',
				'id'         => $category_id,
				'name'       => $cat->category_name,
				'impacts'    => $impacts,
				'strategies' => $strategies,
				'redirect'   => array(
					'available'  => (bool) $from && class_exists( 'ec_url_redirects' ),
					'from'       => $from,
					'default_to' => get_permalink( (int) get_option( 'ec_option_storepage' ) ),
				),
				'undo_days'  => self::RETENTION_DAYS,
			);
		}

		/* =====================================================================
		   EXECUTE
		   ===================================================================== */

		/**
		 * @param array $args { strategy, target_id, redirect(bool) }
		 * @return array|WP_Error { trash_id, message, remaining_products?, ... }
		 */
		public function execute( $type, $id, $args ) {
			$id       = (int) $id;
			$strategy = isset( $args['strategy'] ) ? sanitize_key( $args['strategy'] ) : '';
			$target   = isset( $args['target_id'] ) ? (int) $args['target_id'] : 0;
			$redirect = ! empty( $args['redirect'] );

			$report = $this->analyze( $type, $id );
			if ( is_wp_error( $report ) ) {
				return $report;
			}
			$valid = false;
			foreach ( $report['strategies'] as $s ) {
				if ( $s['key'] === $strategy && empty( $s['disabled'] ) ) {
					$valid = true;
					if ( ! empty( $s['requires_target'] ) ) {
						$allowed = array_map( 'intval', wp_list_pluck( isset( $s['targets'] ) ? $s['targets'] : array(), 'value' ) );
						if ( ! $target || $target === $id || ! in_array( $target, $allowed, true ) ) {
							return new WP_Error( 'target', __( 'Choose a valid target first.', 'wp-easycart' ) );
						}
					}
				}
			}
			if ( ! $valid ) {
				return new WP_Error( 'strategy', __( 'That option is not available for this record.', 'wp-easycart' ) );
			}
			if ( 'option' === $type ) {
				return $this->execute_option( $id, $strategy, $target, $report );
			}
			if ( 'category' === $type ) {
				return $this->execute_category( $id, $strategy, $target, $redirect, $report );
			}
			if ( 'manufacturer' === $type ) {
				return $this->execute_manufacturer( $id, $strategy, $target, $redirect, $report );
			}
			return $this->execute_menu( (int) substr( $type, 4 ), $id, $strategy, $target, $redirect, $report );
		}

		private function execute_option( $option_id, $strategy, $target, $report ) {
			global $wpdb;
			$snap = $this->snapshot_begin( 'option', $option_id, $report['name'], $strategy, $target );

			$set   = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_option WHERE option_id = %d', $option_id ), ARRAY_A );
			$items = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ec_optionitem WHERE option_id = %d', $option_id ), ARRAY_A );
			$ids   = array_map( 'intval', wp_list_pluck( $items, 'optionitem_id' ) );
			$in    = $ids ? implode( ',', $ids ) : '0';

			$this->snapshot_rows( $snap, 'ec_option', 'option_id', array( $set ) );
			$this->snapshot_rows( $snap, 'ec_optionitem', 'optionitem_id', $items );
			$this->snapshot_rows( $snap, 'ec_optionitemimage', 'optionitemimage_id', $wpdb->get_results( "SELECT * FROM ec_optionitemimage WHERE optionitem_id IN ($in)", ARRAY_A ) );

			/* Big tables ( since 6.0.0 ) stream to the bulk snapshot file instead of the option row:
			   a PRO advanced set can sit on tens of thousands of products, and one set can own
			   millions of variant stock rows. ec_option_to_product rows first ( small, full rows ). */
			$this->snapshot_bulk_table( $snap, 'ec_option_to_product', 'option_to_product_id', $wpdb->prepare( 'option_id = %d', $option_id ) );

			/* Variant stock: keep only the id + five slot columns in memory. Full rows stream to the
			   file right before they are deleted; remapped rows store just the slots that change. */
			$stock_where = "optionitem_id_1 IN ($in) OR optionitem_id_2 IN ($in) OR optionitem_id_3 IN ($in) OR optionitem_id_4 IN ($in) OR optionitem_id_5 IN ($in)";
			$stock_ref   = $wpdb->get_results( "SELECT optionitemquantity_id, optionitem_id_1, optionitem_id_2, optionitem_id_3, optionitem_id_4, optionitem_id_5 FROM ec_optionitemquantity WHERE $stock_where", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $in is a list of intval()'d ids.

			/* Product slots: id + only the slots that point at this set ( the columns that change ) */
			$products   = $wpdb->get_results( $wpdb->prepare( 'SELECT product_id, option_id_1, option_id_2, option_id_3, option_id_4, option_id_5 FROM ec_product WHERE option_id_1 = %d OR option_id_2 = %d OR option_id_3 = %d OR option_id_4 = %d OR option_id_5 = %d', $option_id, $option_id, $option_id, $option_id, $option_id ), ARRAY_A );
			$n_products = count( $products );
			foreach ( $products as $p ) {
				$fields = array();
				for ( $n = 1; $n <= 5; $n++ ) {
					if ( (int) $p[ 'option_id_' . $n ] === (int) $option_id ) { $fields[ 'option_id_' . $n ] = (int) $option_id; }
				}
				$this->snapshot_bulk_update( $snap, 'ec_product', 'product_id', (int) $p['product_id'], $fields );
			}
			unset( $products );

			do_action( 'wp_easycart_optionset_deleting', $option_id );

			$kept_stock = 0;
			if ( 'replace' === $strategy ) {
				/* Map old choice -> new choice by case-insensitive name */
				$new_items = $wpdb->get_results( $wpdb->prepare( 'SELECT optionitem_id, optionitem_name FROM ec_optionitem WHERE option_id = %d', $target ) );
				$by_name = array();
				foreach ( $new_items as $ni ) {
					$by_name[ strtolower( trim( $ni->optionitem_name ) ) ] = (int) $ni->optionitem_id;
				}
				$map = array();
				foreach ( $items as $it ) {
					$k = strtolower( trim( $it['optionitem_name'] ) );
					if ( isset( $by_name[ $k ] ) ) {
						$map[ (int) $it['optionitem_id'] ] = $by_name[ $k ];
					}
				}
				/* Product slots -> target */
				for ( $n = 1; $n <= 5; $n++ ) {
					$wpdb->query( $wpdb->prepare( "UPDATE ec_product SET option_id_$n = %d WHERE option_id_$n = %d", $target, $option_id ) );
				}
				$wpdb->query( $wpdb->prepare( 'UPDATE ec_option_to_product SET option_id = %d WHERE option_id = %d', $target, $option_id ) );
				/* Stock rows: decide per row in PHP, then one chunked CASE update per slot for the
				   remapped rows and one chunked DELETE for the rest ( was one UPDATE/DELETE per row ). */
				$lookup = array_flip( $ids );
				$remap  = array( 1 => array(), 2 => array(), 3 => array(), 4 => array(), 5 => array() ); /* slot => stock id => new optionitem id */
				$drop   = array();
				foreach ( $stock_ref as $row ) {
					$ok = true; $changes = array(); $old = array();
					$sid = (int) $row['optionitemquantity_id'];
					for ( $n = 1; $n <= 5; $n++ ) {
						$v = (int) $row[ 'optionitem_id_' . $n ];
						if ( isset( $lookup[ $v ] ) ) {
							if ( isset( $map[ $v ] ) ) { $changes[ $n ] = $map[ $v ]; $old[ 'optionitem_id_' . $n ] = $v; } else { $ok = false; }
						}
					}
					if ( $ok && $changes ) {
						foreach ( $changes as $n => $new ) { $remap[ $n ][ $sid ] = $new; }
						$this->snapshot_bulk_update( $snap, 'ec_optionitemquantity', 'optionitemquantity_id', $sid, $old );
						$kept_stock++;
					} else {
						$drop[] = $sid;
					}
				}
				unset( $stock_ref );
				$this->snapshot_bulk_ids( $snap, 'ec_optionitemquantity', 'optionitemquantity_id', $drop );
				$this->bulk_delete_ids( 'ec_optionitemquantity', 'optionitemquantity_id', $drop );
				for ( $n = 1; $n <= 5; $n++ ) {
					$this->bulk_case_update( 'ec_optionitemquantity', 'optionitemquantity_id', 'optionitem_id_' . $n, $remap[ $n ] );
				}
			} else {
				for ( $n = 1; $n <= 5; $n++ ) {
					$wpdb->query( $wpdb->prepare( "UPDATE ec_product SET option_id_$n = 0 WHERE option_id_$n = %d", $option_id ) );
				}
				$wpdb->query( $wpdb->prepare( 'DELETE FROM ec_option_to_product WHERE option_id = %d', $option_id ) );
				$drop = array_map( 'intval', wp_list_pluck( $stock_ref, 'optionitemquantity_id' ) );
				unset( $stock_ref );
				$this->snapshot_bulk_ids( $snap, 'ec_optionitemquantity', 'optionitemquantity_id', $drop );
				$wpdb->query( "DELETE FROM ec_optionitemquantity WHERE $stock_where" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- built above from intval()'d ids.
			}
			$wpdb->query( "DELETE FROM ec_optionitemimage WHERE optionitem_id IN ($in)" );
			$wpdb->query( $wpdb->prepare( 'DELETE FROM ec_optionitem WHERE option_id = %d', $option_id ) );
			$wpdb->query( $wpdb->prepare( 'DELETE FROM ec_option WHERE option_id = %d', $option_id ) );

			do_action( 'wp_easycart_optionset_deleted', $option_id );
			$trash_id = $this->snapshot_commit( $snap );

			$msg = 'replace' === $strategy
				/* translators: 1: set name, 2: product count, 3: kept stock rows */
				? sprintf( __( 'Deleted “%1$s” and replaced it on %2$d products. %3$d variant stock rows kept.', 'wp-easycart' ), $report['name'], $n_products, $kept_stock )
				/* translators: 1: set name, 2: product count */
				: sprintf( __( 'Deleted “%1$s” and removed it from %2$d products.', 'wp-easycart' ), $report['name'], $n_products );
			return array( 'trash_id' => $trash_id, 'message' => $msg );
		}

		private function execute_category( $category_id, $strategy, $target, $redirect, $report ) {
			global $wpdb;
			$snap = $this->snapshot_begin( 'category', $category_id, $report['name'], $strategy, $target );

			$cat      = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_category WHERE category_id = %d', $category_id ), ARRAY_A );
			$children = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ec_category WHERE parent_id = %d', $category_id ), ARRAY_A );
			$delete_ids = array( $category_id );
			if ( 'cascade' === $strategy ) {
				$delete_ids = $this->descendant_ids( $category_id );
			}
			$del_in = implode( ',', array_map( 'intval', $delete_ids ) );

			$cats_to_delete = $wpdb->get_results( "SELECT * FROM ec_category WHERE category_id IN ($del_in)", ARRAY_A );
			$this->snapshot_rows( $snap, 'ec_category', 'category_id', $cats_to_delete );
			$items = $wpdb->get_results( "SELECT * FROM ec_categoryitem WHERE category_id IN ($del_in)", ARRAY_A );
			$this->snapshot_rows( $snap, 'ec_categoryitem', 'categoryitem_id', $items );
			if ( 'cascade' !== $strategy ) {
				foreach ( $children as $c ) {
					$this->snapshot_update( $snap, 'ec_category', 'category_id', (int) $c['category_id'], array( 'parent_id' => $c['parent_id'] ) );
				}
			}
			/* WP posts + menu items */
			foreach ( $cats_to_delete as $c ) {
				if ( $c['post_id'] ) {
					$post = get_post( (int) $c['post_id'], ARRAY_A );
					if ( $post ) {
						$snap['posts'][] = array( 'post' => $post, 'thumb' => get_post_thumbnail_id( (int) $c['post_id'] ), 'category_id' => (int) $c['category_id'] );
					}
					$menu_ids = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_menu_item_object_id' AND meta_value = %d", (int) $c['post_id'] ) );
					foreach ( $menu_ids as $mid ) {
						$snap['menu_items'][] = array( 'menu_item' => get_post( (int) $mid, ARRAY_A ), 'meta' => get_post_meta( (int) $mid ), 'terms' => wp_get_object_terms( (int) $mid, 'nav_menu', array( 'fields' => 'ids' ) ) );
					}
				}
			}
			/* Promotions / codes */
			if ( $this->table_exists( 'ec_promotion' ) ) {
				$promos = $wpdb->get_results( "SELECT promotion_id, category_id_1, category_id_2, category_id_3 FROM ec_promotion WHERE category_id_1 IN ($del_in) OR category_id_2 IN ($del_in) OR category_id_3 IN ($del_in)", ARRAY_A );
				foreach ( $promos as $p ) {
					$this->snapshot_update( $snap, 'ec_promotion', 'promotion_id', (int) $p['promotion_id'], array( 'category_id_1' => $p['category_id_1'], 'category_id_2' => $p['category_id_2'], 'category_id_3' => $p['category_id_3'] ) );
				}
			}
			if ( $this->table_exists( 'ec_promocode' ) ) {
				$codes = $wpdb->get_results( "SELECT promocode_id, category_id, by_category_id FROM ec_promocode WHERE category_id IN ($del_in) OR by_category_id IN ($del_in)", ARRAY_A );
				foreach ( $codes as $p ) {
					$this->snapshot_update( $snap, 'ec_promocode', 'promocode_id', (int) $p['promocode_id'], array( 'category_id' => $p['category_id'], 'by_category_id' => $p['by_category_id'] ) );
				}
			}

			do_action( 'wpeasycart_category_deleting', $category_id );

			/* Re-parent */
			if ( 'lift' === $strategy ) {
				$wpdb->query( $wpdb->prepare( 'UPDATE ec_category SET parent_id = %d WHERE parent_id = %d', (int) $cat['parent_id'], $category_id ) );
			} else if ( 'move' === $strategy ) {
				$wpdb->query( $wpdb->prepare( 'UPDATE ec_category SET parent_id = %d WHERE parent_id = %d', $target, $category_id ) );
				/* Products: move membership, skipping ones already in target ( new rows are tracked so restore can remove them ) */
				$before_ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT categoryitem_id FROM ec_categoryitem WHERE category_id = %d', $target ) ) );
				$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_categoryitem ( category_id, product_id ) SELECT %d, ci.product_id FROM ec_categoryitem ci WHERE ci.category_id = %d AND NOT EXISTS ( SELECT 1 FROM ec_categoryitem c2 WHERE c2.category_id = %d AND c2.product_id = ci.product_id )', $target, $category_id, $target ) );
				$after_ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT categoryitem_id FROM ec_categoryitem WHERE category_id = %d', $target ) ) );
				foreach ( array_diff( $after_ids, $before_ids ) as $new_id ) { $snap['inserted'][] = array( 'table' => 'ec_categoryitem', 'key' => 'categoryitem_id', 'id' => $new_id ); }
			}
			$wpdb->query( "DELETE FROM ec_categoryitem WHERE category_id IN ($del_in)" );

			/* Promotions / codes: clear the dead reference */
			if ( $this->table_exists( 'ec_promotion' ) ) {
				for ( $n = 1; $n <= 3; $n++ ) {
					$wpdb->query( "UPDATE ec_promotion SET category_id_$n = 0 WHERE category_id_$n IN ($del_in)" );
				}
			}
			if ( $this->table_exists( 'ec_promocode' ) ) {
				$wpdb->query( "UPDATE ec_promocode SET category_id = 0 WHERE category_id IN ($del_in)" );
				$wpdb->query( "UPDATE ec_promocode SET by_category_id = 0 WHERE by_category_id IN ($del_in)" );
			}

			/* Redirect before the post disappears */
			if ( $redirect && class_exists( 'ec_url_redirects' ) && $cat['post_id'] ) {
				$from = get_permalink( (int) $cat['post_id'] );
				$to   = ( 'move' === $strategy ) ? $this->category_url( $target ) : get_permalink( (int) get_option( 'ec_option_storepage' ) );
				if ( $from && $to ) {
					ec_url_redirects::add( $from, $to, 'category-delete:' . $category_id );
					$snap['redirects'][] = $from;
				}
			}

			/* Menu items + posts */
			foreach ( $snap['menu_items'] as $mi ) {
				wp_delete_post( (int) $mi['menu_item']['ID'], true );
			}
			foreach ( $cats_to_delete as $c ) {
				if ( $c['post_id'] ) {
					wp_easycart_post_sync()->delete( 'category', (int) $c['category_id'], (int) $c['post_id'] );
				}
			}
			$wpdb->query( "DELETE FROM ec_category WHERE category_id IN ($del_in)" );

			do_action( 'wpeasycart_category_deleted', $category_id );
			$trash_id = $this->snapshot_commit( $snap );

			$n_children = count( $children );
			if ( 'move' === $strategy ) {
				$msg = sprintf( __( 'Deleted “%1$s” — %2$d products and %3$d subcategories moved to “%4$s”.', 'wp-easycart' ), $report['name'], count( $items ), $n_children, $this->category_name( $target ) );
			} else if ( 'cascade' === $strategy ) {
				$msg = sprintf( __( 'Deleted “%1$s” and %2$d subcategories.', 'wp-easycart' ), $report['name'], count( $delete_ids ) - 1 );
			} else {
				$msg = $n_children ? sprintf( __( 'Deleted “%1$s” — %2$d subcategories moved up a level.', 'wp-easycart' ), $report['name'], $n_children ) : sprintf( __( 'Deleted “%s”.', 'wp-easycart' ), $report['name'] );
			}
			return array( 'trash_id' => $trash_id, 'message' => $msg );
		}


		/* ---------------------------------------------------------------- */
		/* MENUS ( three fixed levels; products store up to three paths )     */
		/* ---------------------------------------------------------------- */

		private function menu_table( $level ) { return 'ec_menulevel' . (int) $level; }
		private function menu_key( $level ) { return 'menulevel' . (int) $level . '_id'; }

		/** All ( level, id ) pairs under a menu item, including itself. */
		public function menu_descendants( $level, $id ) {
			global $wpdb;
			$out = array( array( 'level' => $level, 'id' => (int) $id ) );
			if ( 1 === $level ) {
				$l2 = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT menulevel2_id FROM ec_menulevel2 WHERE menulevel1_id = %d', $id ) ) );
				foreach ( $l2 as $b ) { $out[] = array( 'level' => 2, 'id' => $b ); }
				if ( $l2 ) {
					foreach ( $wpdb->get_col( 'SELECT menulevel3_id FROM ec_menulevel3 WHERE menulevel2_id IN ( ' . implode( ',', $l2 ) . ' )' ) as $c ) { $out[] = array( 'level' => 3, 'id' => (int) $c ); }
				}
			} else if ( 2 === $level ) {
				foreach ( $wpdb->get_col( $wpdb->prepare( 'SELECT menulevel3_id FROM ec_menulevel3 WHERE menulevel2_id = %d', $id ) ) as $c ) { $out[] = array( 'level' => 3, 'id' => (int) $c ); }
			}
			return $out;
		}

		/** Products with a path through this menu item ( any of the three paths; columns are menulevel{PATH}_id_{LEVEL} ). */
		private function menu_products_where( $level, $id ) {
			global $wpdb;
			$l = (int) $level;
			return $wpdb->prepare( "( menulevel1_id_{$l} = %d OR menulevel2_id_{$l} = %d OR menulevel3_id_{$l} = %d )", $id, $id, $id ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $l is the (int) menu level 1..3.
		}

		private function analyze_menu( $level, $id ) {
			global $wpdb;
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $this->menu_table( $level ) . ' WHERE ' . $this->menu_key( $level ) . ' = %d', $id ) );
			if ( ! $row ) {
				return new WP_Error( 'not_found', __( 'Menu item not found.', 'wp-easycart' ) );
			}
			$desc = $this->menu_descendants( $level, $id );
			$children = array_filter( $desc, function( $d ) use ( $level ) { return $d['level'] === $level + 1; } );
			$deeper   = array_filter( $desc, function( $d ) use ( $level ) { return $d['level'] > $level + 1; } );
			$products = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_product WHERE ' . $this->menu_products_where( $level, $id ) );
			$sample   = $wpdb->get_col( 'SELECT title FROM ec_product WHERE ' . $this->menu_products_where( $level, $id ) . ' ORDER BY title LIMIT 5' );
			$menu_items = $row->post_id ? (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_menu_item_object_id' AND meta_value = %d", $row->post_id ) ) : 0;
			$child_names = array();
			foreach ( array_slice( array_values( $children ), 0, 5 ) as $c ) { $child_names[] = $wpdb->get_var( $wpdb->prepare( 'SELECT name FROM ' . $this->menu_table( $c['level'] ) . ' WHERE ' . $this->menu_key( $c['level'] ) . ' = %d', $c['id'] ) ); }

			$impacts = array();
			$impacts[] = array( 'key' => 'products', 'label' => __( 'Products in this menu', 'wp-easycart' ), 'count' => $products, 'severity' => $products ? 'warn' : 'info', 'detail' => $products ? ( 1 === $level ? __( 'Their menu path is cleared. Products are never deleted.', 'wp-easycart' ) : __( 'Their path is shortened to the parent menu. Products are never deleted.', 'wp-easycart' ) ) : __( 'No products use this menu.', 'wp-easycart' ), 'sample' => $sample, 'link' => admin_url( 'admin.php?page=wp-easycart-products&subpage=products&menu=' . $level . ':' . $id ) );
			if ( $children ) {
				$impacts[] = array( 'key' => 'children', 'label' => __( 'Sub-menus', 'wp-easycart' ), 'count' => count( $children ) + count( $deeper ), 'severity' => 'warn', 'detail' => count( $deeper ) ? sprintf( __( '%1$d direct, %2$d nested deeper. Sub-menus cannot exist without a parent.', 'wp-easycart' ), count( $children ), count( $deeper ) ) : __( 'Sub-menus cannot exist without a parent.', 'wp-easycart' ), 'sample' => $child_names );
			}
			if ( $menu_items ) {
				$impacts[] = array( 'key' => 'menu', 'label' => __( 'Navigation menu items', 'wp-easycart' ), 'count' => $menu_items, 'severity' => 'warn', 'detail' => __( 'Removed from your menus so visitors never see a dead link.', 'wp-easycart' ), 'sample' => array() );
			}
			if ( $row->post_id ) {
				$impacts[] = array( 'key' => 'page', 'label' => __( 'Menu page', 'wp-easycart' ), 'count' => 1, 'severity' => 'info', 'detail' => get_permalink( (int) $row->post_id ), 'sample' => array() );
			}

			$strategies = array();
			if ( $children ) {
				$parent_col = $level === 1 ? null : $this->menu_key( $level - 1 );
				$targets = $wpdb->get_results( $wpdb->prepare( 'SELECT ' . $this->menu_key( $level ) . ' AS value, name AS label FROM ' . $this->menu_table( $level ) . ' WHERE ' . $this->menu_key( $level ) . ' != %d ORDER BY name', $id ) );
				$strategies[] = array(
					'key' => 'move', 'label' => __( 'Move sub-menus to another menu', 'wp-easycart' ),
					'description' => __( 'Sub-menus and the products in them re-parent to the menu you choose. Product paths are rewritten to match.', 'wp-easycart' ),
					'requires_target' => true, 'targets' => $targets, 'disabled' => empty( $targets ), 'recommended' => ! empty( $targets ),
				);
				$strategies[] = array( 'key' => 'cascade', 'label' => __( 'Delete sub-menus too', 'wp-easycart' ), 'description' => sprintf( __( 'Removes %d sub-menus as well. Products keep their other menus.', 'wp-easycart' ), count( $children ) + count( $deeper ) ), 'danger' => true, 'recommended' => empty( $targets ) );
			} else {
				$strategies[] = array( 'key' => 'remove', 'label' => __( 'Delete the menu', 'wp-easycart' ), 'description' => $products ? __( 'Product paths are trimmed. Products are never deleted.', 'wp-easycart' ) : __( 'Nothing else references it.', 'wp-easycart' ), 'recommended' => true );
			}
			$from = $row->post_id ? get_permalink( (int) $row->post_id ) : '';
			return array(
				'type' => 'menu' . $level, 'id' => $id, 'name' => $row->name, 'impacts' => $impacts, 'strategies' => $strategies,
				'redirect' => array( 'available' => (bool) $from && class_exists( 'ec_url_redirects' ), 'from' => $from, 'default_to' => get_permalink( (int) get_option( 'ec_option_storepage' ) ) ),
				'undo_days' => self::RETENTION_DAYS,
			);
		}

		/** Snapshot the product path columns for every product touching any of these menu ids. */
		private function snapshot_product_paths( &$snap, $where ) {
			global $wpdb;
			$rows = $wpdb->get_results( 'SELECT product_id, menulevel1_id_1, menulevel2_id_1, menulevel3_id_1, menulevel1_id_2, menulevel2_id_2, menulevel3_id_2, menulevel1_id_3, menulevel2_id_3, menulevel3_id_3 FROM ec_product WHERE ' . $where, ARRAY_A );
			foreach ( $rows as $r ) {
				$pid = (int) $r['product_id']; unset( $r['product_id'] );
				$this->snapshot_update( $snap, 'ec_product', 'product_id', $pid, $r );
			}
			return count( $rows );
		}

		private function execute_menu( $level, $id, $strategy, $target, $redirect, $report ) {
			global $wpdb;
			$snap = $this->snapshot_begin( 'menu' . $level, $id, $report['name'], $strategy, $target );
			$desc = $this->menu_descendants( $level, $id );
			$delete = ( 'cascade' === $strategy ) ? $desc : array( array( 'level' => $level, 'id' => $id ) );
			$children = array_filter( $desc, function( $d ) use ( $level ) { return $d['level'] === $level + 1; } );

			/* Rows to delete ( by level ) + their posts / nav items */
			$all_where = array();
			foreach ( $delete as $d ) {
				$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $this->menu_table( $d['level'] ) . ' WHERE ' . $this->menu_key( $d['level'] ) . ' = %d', $d['id'] ), ARRAY_A );
				if ( ! $row ) { continue; }
				$this->snapshot_rows( $snap, $this->menu_table( $d['level'] ), $this->menu_key( $d['level'] ), array( $row ) );
				$this->snapshot_post( $snap, (int) $row['post_id'], $this->menu_table( $d['level'] ), $this->menu_key( $d['level'] ), $d['id'] );
				$all_where[] = $this->menu_products_where( $d['level'], $d['id'] );
			}
			if ( 'move' === $strategy ) {
				foreach ( $children as $c ) {
					$this->snapshot_update( $snap, $this->menu_table( $c['level'] ), $this->menu_key( $c['level'] ), $c['id'], array( $this->menu_key( $level ) => $id ) );
					$all_where[] = $this->menu_products_where( $c['level'], $c['id'] );
				}
			}
			$this->snapshot_product_paths( $snap, implode( ' OR ', $all_where ) );

			do_action( 'wpeasycart_menu_deleting', $id, $level );

			if ( 'move' === $strategy ) {
				/* Re-parent direct children, then rewrite the parent segment of every product path through them.
				   Product columns are menulevel{PATH}_id_{LEVEL}: path $n, level segment $level / $level + 1. */
				$pk = $this->menu_key( $level ); $lvl = (int) $level; $child_lvl = $lvl + 1;
				$wpdb->query( $wpdb->prepare( 'UPDATE ' . $this->menu_table( $level + 1 ) . " SET $pk = %d WHERE $pk = %d", $target, $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- menu_table()/menu_key() build identifiers from the (int) level; values are %d placeholders.
				foreach ( $children as $c ) {
					for ( $n = 1; $n <= 3; $n++ ) {
						$wpdb->query( $wpdb->prepare( "UPDATE ec_product SET menulevel{$n}_id_{$lvl} = %d WHERE menulevel{$n}_id_{$child_lvl} = %d", $target, $c['id'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $n is the loop counter 1..3, $lvl/$child_lvl are (int) menu levels.
					}
				}
				/* Level-2 move: the target may live under a different level-1, so rewrite that segment too */
				if ( 2 === $level ) {
					$new_l1 = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT menulevel1_id FROM ec_menulevel2 WHERE menulevel2_id = %d', $target ) );
					foreach ( $children as $c ) {
						for ( $n = 1; $n <= 3; $n++ ) { $wpdb->query( $wpdb->prepare( "UPDATE ec_product SET menulevel{$n}_id_1 = %d WHERE menulevel{$n}_id_3 = %d", $new_l1, $c['id'] ) ); } // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $n is the loop counter 1..3.
					}
				}
				/* Level-1 move: also make sure the level-1 segment above a moved level-3 path is coherent */
				if ( 1 === $level ) {
					foreach ( $desc as $d ) {
						if ( 3 !== $d['level'] ) { continue; }
						for ( $n = 1; $n <= 3; $n++ ) { $wpdb->query( $wpdb->prepare( "UPDATE ec_product SET menulevel{$n}_id_1 = %d WHERE menulevel{$n}_id_3 = %d", $target, $d['id'] ) ); } // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $n is the loop counter 1..3.
					}
				}
			}

			/* Product paths through the deleted node(s) themselves ( after any move, so re-homed products are kept ):
			   clear the deleted level and every deeper level of that path ( menulevel{PATH}_id_{LEVEL} ) */
			foreach ( $delete as $d ) {
				$dl = (int) $d['level'];
				for ( $n = 1; $n <= 3; $n++ ) {
					$set = array();
					for ( $l = $dl; $l <= 3; $l++ ) { $set[] = "menulevel{$n}_id_{$l} = 0"; }
					$wpdb->query( $wpdb->prepare( 'UPDATE ec_product SET ' . implode( ', ', $set ) . " WHERE menulevel{$n}_id_{$dl} = %d", $d['id'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $set holds "menulevel{n}_id_{l} = 0" literals built from the loop counters 1..3; $dl is an (int) menu level; the value is a %d placeholder.
				}
			}

			/* Redirect */
			$first = $delete[0];
			$row0 = $snap['rows'][ $this->menu_table( $first['level'] ) ][0];
			if ( $redirect && class_exists( 'ec_url_redirects' ) && ! empty( $row0['post_id'] ) ) {
				$from = get_permalink( (int) $row0['post_id'] );
				$to = '';
				if ( 'move' === $strategy ) { $tp = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT post_id FROM ' . $this->menu_table( $level ) . ' WHERE ' . $this->menu_key( $level ) . ' = %d', $target ) ); $to = $tp ? get_permalink( $tp ) : ''; }
				if ( ! $to ) { $to = get_permalink( (int) get_option( 'ec_option_storepage' ) ); }
				if ( $from && $to && ec_url_redirects::add( $from, $to, 'menu-delete:' . $level . ':' . $id ) ) { $snap['redirects'][] = $from; }
			}

			/* Posts, nav items, rows — deepest first */
			$this->delete_snapshotted_posts( $snap );
			usort( $delete, function( $a, $b ) { return $b['level'] - $a['level']; } );
			foreach ( $delete as $d ) {
				$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $this->menu_table( $d['level'] ) . ' WHERE ' . $this->menu_key( $d['level'] ) . ' = %d', $d['id'] ) );
			}
			do_action( 'wpeasycart_menu_deleted', $id, $level );
			$trash_id = $this->snapshot_commit( $snap );

			if ( 'move' === $strategy ) {
				$tn = $wpdb->get_var( $wpdb->prepare( 'SELECT name FROM ' . $this->menu_table( $level ) . ' WHERE ' . $this->menu_key( $level ) . ' = %d', $target ) );
				$msg = sprintf( __( 'Deleted “%1$s” — %2$d sub-menus moved to “%3$s”.', 'wp-easycart' ), $report['name'], count( $children ), $tn );
			} else if ( 'cascade' === $strategy ) {
				$msg = sprintf( __( 'Deleted “%1$s” and %2$d sub-menus.', 'wp-easycart' ), $report['name'], count( $desc ) - 1 );
			} else {
				$msg = sprintf( __( 'Deleted “%s”.', 'wp-easycart' ), $report['name'] );
			}
			return array( 'trash_id' => $trash_id, 'message' => $msg );
		}

		/* ---------------------------------------------------------------- */
		/* MANUFACTURERS                                                     */
		/* ---------------------------------------------------------------- */

		private function analyze_manufacturer( $id ) {
			global $wpdb;
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_manufacturer WHERE manufacturer_id = %d', $id ) );
			if ( ! $row ) { return new WP_Error( 'not_found', __( 'Manufacturer not found.', 'wp-easycart' ) ); }
			$products = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_product WHERE manufacturer_id = %d', $id ) );
			$sample   = $wpdb->get_col( $wpdb->prepare( 'SELECT title FROM ec_product WHERE manufacturer_id = %d ORDER BY title LIMIT 5', $id ) );
			$promos   = $this->table_exists( 'ec_promotion' ) ? (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_promotion WHERE manufacturer_id_1 = %d OR manufacturer_id_2 = %d OR manufacturer_id_3 = %d', $id, $id, $id ) ) : 0;
			$codes    = $this->table_exists( 'ec_promocode' ) ? (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_promocode WHERE manufacturer_id = %d OR by_manufacturer_id = %d', $id, $id ) ) : 0;
			$menu_items = $row->post_id ? (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_menu_item_object_id' AND meta_value = %d", $row->post_id ) ) : 0;

			$impacts = array();
			$impacts[] = array( 'key' => 'products', 'label' => __( 'Products by this manufacturer', 'wp-easycart' ), 'count' => $products, 'severity' => $products ? 'warn' : 'info', 'detail' => $products ? __( 'Choose “Reassign” to keep them under a manufacturer. Products are never deleted.', 'wp-easycart' ) : __( 'No products use it.', 'wp-easycart' ), 'sample' => $sample, 'link' => admin_url( 'admin.php?page=wp-easycart-products&subpage=products&filter_3=' . $id ) );
			if ( $promos || $codes ) { $impacts[] = array( 'key' => 'promotions', 'label' => __( 'Promotions & coupon codes targeting it', 'wp-easycart' ), 'count' => $promos + $codes, 'severity' => 'warn', 'detail' => __( 'Their manufacturer rule is cleared. Review them under Marketing.', 'wp-easycart' ), 'sample' => array(), 'link' => admin_url( 'admin.php?page=wp-easycart-marketing' ) ); }
			if ( $menu_items ) { $impacts[] = array( 'key' => 'menu', 'label' => __( 'Navigation menu items', 'wp-easycart' ), 'count' => $menu_items, 'severity' => 'warn', 'detail' => __( 'Removed from your menus.', 'wp-easycart' ), 'sample' => array() ); }
			if ( $row->post_id ) { $impacts[] = array( 'key' => 'page', 'label' => __( 'Manufacturer page', 'wp-easycart' ), 'count' => 1, 'severity' => 'info', 'detail' => get_permalink( (int) $row->post_id ), 'sample' => array() ); }

			$targets = $wpdb->get_results( $wpdb->prepare( 'SELECT manufacturer_id AS value, name AS label FROM ec_manufacturer WHERE manufacturer_id != %d ORDER BY name', $id ) );
			$strategies = array();
			if ( $products ) {
				$strategies[] = array( 'key' => 'replace', 'label' => __( 'Reassign products to another manufacturer', 'wp-easycart' ), 'description' => __( 'Every product moves to the manufacturer you choose; promotions targeting this one are retargeted too.', 'wp-easycart' ), 'requires_target' => true, 'targets' => $targets, 'disabled' => empty( $targets ), 'recommended' => ! empty( $targets ) );
			}
			$strategies[] = array( 'key' => 'remove', 'label' => $products ? __( 'Leave products without a manufacturer', 'wp-easycart' ) : __( 'Delete the manufacturer', 'wp-easycart' ), 'description' => $products ? __( 'Products stay active with no manufacturer set.', 'wp-easycart' ) : __( 'Nothing else references it.', 'wp-easycart' ), 'recommended' => ! $products );
			$from = $row->post_id ? get_permalink( (int) $row->post_id ) : '';
			return array( 'type' => 'manufacturer', 'id' => $id, 'name' => $row->name, 'impacts' => $impacts, 'strategies' => $strategies, 'redirect' => array( 'available' => (bool) $from && class_exists( 'ec_url_redirects' ), 'from' => $from, 'default_to' => get_permalink( (int) get_option( 'ec_option_storepage' ) ) ), 'undo_days' => self::RETENTION_DAYS );
		}

		private function execute_manufacturer( $id, $strategy, $target, $redirect, $report ) {
			global $wpdb;
			$snap = $this->snapshot_begin( 'manufacturer', $id, $report['name'], $strategy, $target );
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_manufacturer WHERE manufacturer_id = %d', $id ), ARRAY_A );
			$this->snapshot_rows( $snap, 'ec_manufacturer', 'manufacturer_id', array( $row ) );
			$this->snapshot_post( $snap, (int) $row['post_id'], 'ec_manufacturer', 'manufacturer_id', $id );
			foreach ( $wpdb->get_col( $wpdb->prepare( 'SELECT product_id FROM ec_product WHERE manufacturer_id = %d', $id ) ) as $pid ) { $this->snapshot_update( $snap, 'ec_product', 'product_id', (int) $pid, array( 'manufacturer_id' => $id ) ); }
			if ( $this->table_exists( 'ec_promotion' ) ) {
				foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT promotion_id, manufacturer_id_1, manufacturer_id_2, manufacturer_id_3 FROM ec_promotion WHERE manufacturer_id_1 = %d OR manufacturer_id_2 = %d OR manufacturer_id_3 = %d', $id, $id, $id ), ARRAY_A ) as $p ) { $pid = (int) $p['promotion_id']; unset( $p['promotion_id'] ); $this->snapshot_update( $snap, 'ec_promotion', 'promotion_id', $pid, $p ); }
			}
			if ( $this->table_exists( 'ec_promocode' ) ) {
				foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT promocode_id, manufacturer_id, by_manufacturer_id FROM ec_promocode WHERE manufacturer_id = %d OR by_manufacturer_id = %d', $id, $id ), ARRAY_A ) as $p ) { $pid = (int) $p['promocode_id']; unset( $p['promocode_id'] ); $this->snapshot_update( $snap, 'ec_promocode', 'promocode_id', $pid, $p ); }
			}
			do_action( 'wpeasycart_manufacturer_deleting', $id );
			$new = ( 'replace' === $strategy ) ? $target : 0;
			$wpdb->query( $wpdb->prepare( 'UPDATE ec_product SET manufacturer_id = %d WHERE manufacturer_id = %d', $new, $id ) );
			if ( $this->table_exists( 'ec_promotion' ) ) { for ( $n = 1; $n <= 3; $n++ ) { $wpdb->query( $wpdb->prepare( "UPDATE ec_promotion SET manufacturer_id_$n = %d WHERE manufacturer_id_$n = %d", $new, $id ) ); } }
			if ( $this->table_exists( 'ec_promocode' ) ) { $wpdb->query( $wpdb->prepare( 'UPDATE ec_promocode SET manufacturer_id = %d WHERE manufacturer_id = %d', $new, $id ) ); $wpdb->query( $wpdb->prepare( 'UPDATE ec_promocode SET by_manufacturer_id = %d WHERE by_manufacturer_id = %d', $new, $id ) ); }
			if ( $redirect && class_exists( 'ec_url_redirects' ) && $row['post_id'] ) {
				$from = get_permalink( (int) $row['post_id'] ); $to = '';
				if ( 'replace' === $strategy ) { $tp = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT post_id FROM ec_manufacturer WHERE manufacturer_id = %d', $target ) ); $to = $tp ? get_permalink( $tp ) : ''; }
				if ( ! $to ) { $to = get_permalink( (int) get_option( 'ec_option_storepage' ) ); }
				if ( $from && $to && ec_url_redirects::add( $from, $to, 'manufacturer-delete:' . $id ) ) { $snap['redirects'][] = $from; }
			}
			$this->delete_snapshotted_posts( $snap );
			$wpdb->query( $wpdb->prepare( 'DELETE FROM ec_manufacturer WHERE manufacturer_id = %d', $id ) );
			do_action( 'wpeasycart_manufacturer_deleted', $id );
			$trash_id = $this->snapshot_commit( $snap );
			$n_products = count( array_filter( $snap['updates'], function( $u ) { return 'ec_product' === $u['table']; } ) );
			$msg = 'replace' === $strategy
				? sprintf( __( 'Deleted “%1$s” — %2$d products reassigned to “%3$s”.', 'wp-easycart' ), $report['name'], $n_products, $wpdb->get_var( $wpdb->prepare( 'SELECT name FROM ec_manufacturer WHERE manufacturer_id = %d', $target ) ) )
				: ( $n_products ? sprintf( __( 'Deleted “%1$s” — %2$d products now have no manufacturer.', 'wp-easycart' ), $report['name'], $n_products ) : sprintf( __( 'Deleted “%s”.', 'wp-easycart' ), $report['name'] ) );
			return array( 'trash_id' => $trash_id, 'message' => $msg );
		}

		/* ---------------------------------------------------------------- */
		/* Shared post / nav-item snapshot helpers                           */
		/* ---------------------------------------------------------------- */

		private function snapshot_post( &$snap, $post_id, $table, $key, $record_id ) {
			global $wpdb;
			if ( ! $post_id ) { return; }
			$post = get_post( $post_id, ARRAY_A );
			if ( $post ) { $snap['posts'][] = array( 'post' => $post, 'thumb' => get_post_thumbnail_id( $post_id ), 'table' => $table, 'key' => $key, 'record_id' => (int) $record_id ); }
			foreach ( $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_menu_item_object_id' AND meta_value = %d", $post_id ) ) as $mid ) {
				$snap['menu_items'][] = array( 'menu_item' => get_post( (int) $mid, ARRAY_A ), 'meta' => get_post_meta( (int) $mid ), 'terms' => wp_get_object_terms( (int) $mid, 'nav_menu', array( 'fields' => 'ids' ) ) );
			}
		}

		private function delete_snapshotted_posts( &$snap ) {
			foreach ( $snap['menu_items'] as $mi ) { if ( ! empty( $mi['menu_item']['ID'] ) ) { wp_delete_post( (int) $mi['menu_item']['ID'], true ); } }
			foreach ( $snap['posts'] as $p ) { if ( ! empty( $p['post']['ID'] ) ) { wp_delete_post( (int) $p['post']['ID'], true ); } }
		}

		/* =====================================================================
		   TRASH / RESTORE
		   ===================================================================== */

		private function snapshot_begin( $type, $id, $name, $strategy, $target ) {
			return array(
				'id'         => $type . '_' . $id . '_' . time() . '_' . wp_rand( 100, 999 ),
				'type'       => $type,
				'record_id'  => (int) $id,
				'name'       => $name,
				'strategy'   => $strategy,
				'target'     => (int) $target,
				'time'       => time(),
				'user'       => get_current_user_id(),
				'rows'       => array(),   /* table => [ rows ] ( deleted rows, re-inserted on restore ) */
				'updates'    => array(),   /* [ { table, key, id, fields } ] ( modified rows, reverted on restore ) */
				'posts'      => array(),
				'menu_items' => array(),
				'redirects'  => array(),
				'inserted'   => array(),   /* rows created by the strategy, deleted on restore */
			);
		}

		private function snapshot_rows( &$snap, $table, $key, $rows ) {
			if ( empty( $rows ) ) { return; }
			if ( ! isset( $snap['rows'][ $table ] ) ) { $snap['rows'][ $table ] = array(); }
			foreach ( $rows as $r ) { $snap['rows'][ $table ][] = $r; }
		}

		private function snapshot_update( &$snap, $table, $key, $id, $fields ) {
			$snap['updates'][] = array( 'table' => $table, 'key' => $key, 'id' => (int) $id, 'fields' => $fields );
		}

		/* ---------------------------------------------------------------
		   Bulk snapshot file ( since 6.0.0 )

		   Big tables no longer go into the serialized option row ( multi-MB
		   on large stores; max_allowed_packet risk ). They stream to
		   wp-easycart-data/trash/<trash id>-<random>.ndjson, one JSON record
		   per line; the option keeps only the file name in 'bulk_file'.
		     ["cols", table, [col, ...]]          column order for the "r" lines that follow
		     ["r",    table, [val, ...]]          a deleted row ( REPLACE INTO on restore, in chunks )
		     ["u",    table, key, id, {col: old}] a modified row: id + the changed columns
		                                          ( reverted with one CASE update per column, in chunks )
		   When the data folder cannot be written the helpers fall back to the
		   option-row arrays, so undo keeps working on a locked-down host.
		   --------------------------------------------------------------- */

		const TRASH_DIR  = 'trash';
		const BULK_CHUNK = 500;

		private $bulk_fh   = null;
		private $bulk_cols = array();

		/** Absolute trash folder, created and protected on first use; '' when unavailable. */
		private static function trash_dir() {
			if ( ! defined( 'EC_PLUGIN_DATA_DIRECTORY' ) ) { return ''; }
			$dir = EC_PLUGIN_DATA_DIRECTORY . '/' . self::TRASH_DIR;
			if ( ! is_dir( $dir ) && ( ! is_dir( EC_PLUGIN_DATA_DIRECTORY ) || ! wp_mkdir_p( $dir ) ) ) { return ''; }
			if ( ! is_writable( $dir ) ) { return ''; }
			$guards = array(
				'index.php'  => "<?php\n// Silence is golden.\n",
				'.htaccess'  => "# WP EasyCart undo snapshots. Never served; read by the plugin only.\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tOrder Deny,Allow\n\tDeny from all\n</IfModule>\n",
				'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration>\n\t<system.webServer>\n\t\t<directoryBrowse enabled=\"false\" />\n\t\t<security>\n\t\t\t<requestFiltering>\n\t\t\t\t<fileExtensions allowUnlisted=\"false\" />\n\t\t\t</requestFiltering>\n\t\t</security>\n\t</system.webServer>\n</configuration>\n",
			);
			foreach ( $guards as $name => $content ) {
				if ( ! file_exists( $dir . '/' . $name ) ) { file_put_contents( $dir . '/' . $name, $content ); } // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- plugin data folder, same as the products/ writers.
			}
			return $dir;
		}

		/** Path of a snapshot's bulk file, '' when the stored name is not one we wrote. */
		private static function bulk_path( $name ) {
			$name = basename( (string) $name );
			if ( ! preg_match( '/^[a-z0-9_]+-[A-Za-z0-9]+\.ndjson$/', $name ) || ! defined( 'EC_PLUGIN_DATA_DIRECTORY' ) ) { return ''; }
			return EC_PLUGIN_DATA_DIRECTORY . '/' . self::TRASH_DIR . '/' . $name;
		}

		/** Append one record; false when no file can be used ( callers then fall back to the option row ). */
		private function bulk_write( &$snap, $record ) {
			if ( ! $this->bulk_fh ) {
				$dir = self::trash_dir();
				if ( '' === $dir ) { return false; }
				$name = preg_replace( '/[^a-z0-9_]/', '', $snap['id'] ) . '-' . wp_generate_password( 24, false ) . '.ndjson';
				$fh   = fopen( $dir . '/' . $name, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- streamed write, plugin data folder.
				if ( ! $fh ) { return false; }
				$this->bulk_fh    = $fh;
				$this->bulk_cols  = array();
				$snap['bulk_file'] = $name;
			}
			return false !== fwrite( $this->bulk_fh, wp_json_encode( $record ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- streamed write, plugin data folder.
		}

		/** Deleted rows of a big table ( full rows, column order written once per table ). */
		private function snapshot_bulk_rows( &$snap, $table, $key, $rows ) {
			foreach ( $rows as $r ) {
				if ( ! isset( $this->bulk_cols[ $table ] ) ) {
					$cols = array_keys( $r );
					if ( ! $this->bulk_write( $snap, array( 'cols', $table, $cols ) ) ) { $this->snapshot_rows( $snap, $table, $key, array( $r ) ); continue; }
					$this->bulk_cols[ $table ] = $cols;
				}
				$vals = array();
				foreach ( $this->bulk_cols[ $table ] as $c ) { $vals[] = array_key_exists( $c, $r ) ? $r[ $c ] : null; }
				if ( $this->bulk_write( $snap, array( 'r', $table, $vals ) ) ) {
					$snap['bulk_counts'][ $table ] = isset( $snap['bulk_counts'][ $table ] ) ? $snap['bulk_counts'][ $table ] + 1 : 1;
				} else {
					$this->snapshot_rows( $snap, $table, $key, array( $r ) );
				}
			}
		}

		/** Every row of $table matching $where_sql ( a prepare()'d fragment ), fetched by id in chunks. */
		private function snapshot_bulk_table( &$snap, $table, $key, $where_sql ) {
			global $wpdb;
			$ids = array_map( 'intval', $wpdb->get_col( "SELECT $key FROM $table WHERE $where_sql" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- identifiers are literals from this class; $where_sql is prepare()'d by the caller.
			$this->snapshot_bulk_ids( $snap, $table, $key, $ids );
		}

		/** Full rows for a list of ids, fetched and streamed BULK_CHUNK at a time. */
		private function snapshot_bulk_ids( &$snap, $table, $key, $ids ) {
			global $wpdb;
			foreach ( array_chunk( array_map( 'intval', $ids ), self::BULK_CHUNK ) as $chunk ) {
				$rows = $wpdb->get_results( "SELECT * FROM $table WHERE $key IN (" . implode( ',', $chunk ) . ')', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL -- identifiers are literals from this class; ids are intval()'d.
				$this->snapshot_bulk_rows( $snap, $table, $key, $rows );
			}
		}

		/** Modified row of a big table: id + the columns that change ( their old values ). */
		private function snapshot_bulk_update( &$snap, $table, $key, $id, $fields ) {
			if ( empty( $fields ) ) { return; }
			if ( $this->bulk_write( $snap, array( 'u', $table, $key, (int) $id, $fields ) ) ) {
				$snap['bulk_updates'] = isset( $snap['bulk_updates'] ) ? $snap['bulk_updates'] + 1 : 1;
			} else {
				$this->snapshot_update( $snap, $table, $key, $id, $fields );
			}
		}

		/** DELETE FROM $table WHERE $key IN ( ... ), BULK_CHUNK ids per statement. */
		private function bulk_delete_ids( $table, $key, $ids ) {
			global $wpdb;
			foreach ( array_chunk( array_map( 'intval', $ids ), self::BULK_CHUNK ) as $chunk ) {
				$wpdb->query( "DELETE FROM $table WHERE $key IN (" . implode( ',', $chunk ) . ')' ); // phpcs:ignore WordPress.DB.PreparedSQL -- identifiers are literals from this class; ids are intval()'d.
			}
		}

		/**
		 * UPDATE $table SET $col = CASE $key WHEN id THEN value ... END WHERE $key IN ( ids ),
		 * BULK_CHUNK ids per statement. Integer keys and values ( option item / option ids ).
		 */
		private function bulk_case_update( $table, $key, $col, $id_to_value ) {
			global $wpdb;
			if ( empty( $id_to_value ) || ! preg_match( '/^[a-z0-9_]+$/', $table . $key . $col ) ) { return; }
			foreach ( array_chunk( $id_to_value, self::BULK_CHUNK, true ) as $chunk ) {
				$when = array(); $args = array();
				foreach ( $chunk as $id => $value ) { $when[] = 'WHEN %d THEN %d'; $args[] = (int) $id; $args[] = (int) $value; }
				$in = implode( ',', array_map( 'intval', array_keys( $chunk ) ) );
				$wpdb->query( $wpdb->prepare( "UPDATE $table SET $col = CASE $key " . implode( ' ', $when ) . " END WHERE $key IN ($in)", $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL -- identifiers validated above; ids intval()'d; $when holds only literal "WHEN %d THEN %d"; values through prepare().
			}
		}

		/** REPLACE INTO $table ( cols ) VALUES ( ... ), ( ... ) for one chunk of restored rows. NULLs stay NULL. */
		private function bulk_replace( $table, $cols, $rows ) {
			global $wpdb;
			if ( empty( $rows ) || empty( $cols ) || ! preg_match( '/^[a-z0-9_]+$/', $table ) ) { return; }
			foreach ( $cols as $c ) { if ( ! preg_match( '/^[a-z0-9_]+$/', $c ) ) { return; } }
			$n = count( $cols ); $tuples = array(); $args = array();
			foreach ( $rows as $vals ) {
				if ( ! is_array( $vals ) || count( $vals ) !== $n ) { continue; }
				$ph = array();
				foreach ( $vals as $v ) { if ( null === $v ) { $ph[] = 'NULL'; } else { $ph[] = '%s'; $args[] = $v; } }
				$tuples[] = '(' . implode( ',', $ph ) . ')';
			}
			if ( ! $tuples ) { return; }
			$sql = "REPLACE INTO $table (`" . implode( '`,`', $cols ) . '`) VALUES ' . implode( ',', $tuples );
			$wpdb->query( $args ? $wpdb->prepare( $sql, $args ) : $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- identifiers validated above; every value goes through prepare(); an all-NULL tuple list has no values to prepare.
		}

		/** Replay a bulk file: deleted rows back in ( chunked REPLACE ), modified rows reverted ( chunked CASE updates ). */
		private function bulk_restore( $name ) {
			$path = self::bulk_path( $name );
			if ( '' === $path || ! is_readable( $path ) ) { return false; }
			$fh = fopen( $path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- streamed read, plugin data folder.
			if ( ! $fh ) { return false; }
			$cols = array(); $rows = array(); $updates = array(); /* rows: table => [ tuple ]; updates: table => key => col => id => old */
			while ( ( $line = fgets( $fh ) ) !== false ) {
				$rec = json_decode( $line, true );
				if ( ! is_array( $rec ) || empty( $rec[0] ) || empty( $rec[1] ) ) { continue; }
				$table = (string) $rec[1];
				if ( 'cols' === $rec[0] && isset( $rec[2] ) && is_array( $rec[2] ) ) {
					$cols[ $table ] = $rec[2];
				} elseif ( 'r' === $rec[0] && isset( $rec[2] ) && isset( $cols[ $table ] ) ) {
					$rows[ $table ][] = $rec[2];
					if ( count( $rows[ $table ] ) >= self::BULK_CHUNK ) { $this->bulk_replace( $table, $cols[ $table ], $rows[ $table ] ); $rows[ $table ] = array(); }
				} elseif ( 'u' === $rec[0] && isset( $rec[2], $rec[3], $rec[4] ) && is_array( $rec[4] ) ) {
					$key = (string) $rec[2];
					foreach ( $rec[4] as $col => $old ) {
						$updates[ $table ][ $key ][ $col ][ (int) $rec[3] ] = $old;
						if ( count( $updates[ $table ][ $key ][ $col ] ) >= self::BULK_CHUNK ) { $this->bulk_case_update( $table, $key, $col, $updates[ $table ][ $key ][ $col ] ); $updates[ $table ][ $key ][ $col ] = array(); }
					}
				}
			}
			fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- streamed read, plugin data folder.
			foreach ( $rows as $table => $pending ) { $this->bulk_replace( $table, $cols[ $table ], $pending ); }
			foreach ( $updates as $table => $by_key ) { foreach ( $by_key as $key => $by_col ) { foreach ( $by_col as $col => $pending ) { $this->bulk_case_update( $table, $key, $col, $pending ); } } }
			return true;
		}

		/** Remove one trash entry's option row and its bulk file ( prune and restore ). */
		private function delete_snapshot( $trash_id ) {
			$snap = get_option( self::TRASH_PREFIX . $trash_id );
			if ( is_array( $snap ) && ! empty( $snap['bulk_file'] ) ) {
				$path = self::bulk_path( $snap['bulk_file'] );
				if ( '' !== $path && file_exists( $path ) ) { wp_delete_file( $path ); }
			}
			delete_option( self::TRASH_PREFIX . $trash_id );
		}

		private function snapshot_commit( $snap ) {
			if ( $this->bulk_fh ) { fclose( $this->bulk_fh ); $this->bulk_fh = null; $this->bulk_cols = array(); } // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- streamed write, plugin data folder.
			$this->prune();
			add_option( self::TRASH_PREFIX . $snap['id'], $snap, '', 'no' );
			$index = get_option( self::TRASH_INDEX, array() );
			if ( ! is_array( $index ) ) { $index = array(); }
			$counts = array();
			foreach ( $snap['rows'] as $t => $rows ) { $counts[ $t ] = count( $rows ); }
			foreach ( isset( $snap['bulk_counts'] ) ? $snap['bulk_counts'] : array() as $t => $n ) { $counts[ $t ] = ( isset( $counts[ $t ] ) ? $counts[ $t ] : 0 ) + (int) $n; }
			$index[ $snap['id'] ] = array( 'type' => $snap['type'], 'record_id' => $snap['record_id'], 'name' => $snap['name'], 'strategy' => $snap['strategy'], 'time' => $snap['time'], 'user' => $snap['user'], 'counts' => $counts, 'updates' => count( $snap['updates'] ) + ( isset( $snap['bulk_updates'] ) ? (int) $snap['bulk_updates'] : 0 ) );
			update_option( self::TRASH_INDEX, $index, 'no' );
			return $snap['id'];
		}

		/** Recently deleted, newest first. For Store Status. */
		public function list_trash() {
			$this->prune();
			$index = get_option( self::TRASH_INDEX, array() );
			if ( ! is_array( $index ) ) { return array(); }
			uasort( $index, function( $a, $b ) { return $b['time'] - $a['time']; } );
			foreach ( $index as $k => &$e ) {
				$e['id'] = $k;
				$e['expires'] = $e['time'] + self::RETENTION_DAYS * DAY_IN_SECONDS;
			}
			return $index;
		}

		private function prune() {
			$index = get_option( self::TRASH_INDEX, array() );
			if ( ! is_array( $index ) ) { return; }
			$cut = time() - self::RETENTION_DAYS * DAY_IN_SECONDS;
			$changed = false;
			foreach ( $index as $k => $e ) {
				if ( $e['time'] < $cut ) {
					$this->delete_snapshot( $k );
					unset( $index[ $k ] );
					$changed = true;
				}
			}
			if ( $changed ) { update_option( self::TRASH_INDEX, $index, 'no' ); }
		}

		/** @return array|WP_Error { message } */
		public function restore( $trash_id ) {
			global $wpdb;
			$trash_id = preg_replace( '/[^a-z0-9_]/', '', (string) $trash_id );
			$snap = get_option( self::TRASH_PREFIX . $trash_id );
			if ( ! $snap || ! is_array( $snap ) ) {
				return new WP_Error( 'gone', __( 'This deletion can no longer be undone.', 'wp-easycart' ) );
			}
			/* Conflict check: the primary record id must be free */
			$pks = array( 'option' => array( 'ec_option', 'option_id' ), 'category' => array( 'ec_category', 'category_id' ), 'manufacturer' => array( 'ec_manufacturer', 'manufacturer_id' ), 'menu1' => array( 'ec_menulevel1', 'menulevel1_id' ), 'menu2' => array( 'ec_menulevel2', 'menulevel2_id' ), 'menu3' => array( 'ec_menulevel3', 'menulevel3_id' ) );
			$pk = isset( $pks[ $snap['type'] ] ) ? $pks[ $snap['type'] ] : $pks['option'];
			if ( $wpdb->get_var( $wpdb->prepare( "SELECT {$pk[1]} FROM {$pk[0]} WHERE {$pk[1]} = %d", $snap['record_id'] ) ) ) {
				return new WP_Error( 'conflict', __( 'A record with the same ID already exists, so this cannot be restored automatically.', 'wp-easycart' ) );
			}
			/* The bulk file must be there before anything is put back, or the undo would be partial */
			$bulk_file = ! empty( $snap['bulk_file'] ) ? $snap['bulk_file'] : '';
			if ( '' !== $bulk_file ) {
				$bulk_path = self::bulk_path( $bulk_file );
				if ( '' === $bulk_path || ! is_readable( $bulk_path ) ) {
					return new WP_Error( 'gone', __( 'The undo data for this deletion is missing from wp-easycart-data/trash, so it cannot be restored.', 'wp-easycart' ) );
				}
			}

			/* 1. Re-insert deleted rows, parents first ( order of tables as snapshotted ) */
			foreach ( $snap['rows'] as $table => $rows ) {
				foreach ( $rows as $row ) {
					$wpdb->replace( $table, $row );
				}
			}
			/* 1b. Remove rows the strategy created */
			foreach ( isset( $snap['inserted'] ) ? $snap['inserted'] : array() as $ins ) {
				$wpdb->delete( $ins['table'], array( $ins['key'] => $ins['id'] ) );
			}
			/* 2. Revert modified rows */
			foreach ( $snap['updates'] as $u ) {
				$wpdb->update( $u['table'], $u['fields'], array( $u['key'] => $u['id'] ) );
			}
			/* 2b. Big tables from the bulk file ( since 6.0.0 ): deleted rows back in, changed columns reverted */
			if ( '' !== $bulk_file ) {
				$this->bulk_restore( $bulk_file );
			}
			/* 3. Posts */
			foreach ( $snap['posts'] as $p ) {
				$post = $p['post'];
				$existing = get_post( (int) $post['ID'] );
				if ( ! $existing ) {
					$post['import_id'] = (int) $post['ID'];
					unset( $post['ID'] );
					$new_id = wp_insert_post( wp_slash( $post ), true );
					if ( ! is_wp_error( $new_id ) ) {
						wp_set_post_tags( $new_id, array( 'category' ), true );
						if ( ! empty( $p['thumb'] ) ) { set_post_thumbnail( $new_id, (int) $p['thumb'] ); }
						if ( ! empty( $p['table'] ) ) {
							$wpdb->update( $p['table'], array( 'post_id' => $new_id ), array( $p['key'] => (int) $p['record_id'] ) );
						} else {
							$wpdb->update( 'ec_category', array( 'post_id' => $new_id ), array( 'category_id' => (int) $p['category_id'] ) );
						}
					}
				}
			}
			/* 4. Menu items */
			foreach ( $snap['menu_items'] as $mi ) {
				$post = $mi['menu_item'];
				if ( ! $post ) { continue; }
				unset( $post['ID'] );
				$new_id = wp_insert_post( wp_slash( $post ), true );
				if ( is_wp_error( $new_id ) ) { continue; }
				foreach ( $mi['meta'] as $k => $vals ) {
					foreach ( (array) $vals as $v ) { add_post_meta( $new_id, $k, maybe_unserialize( $v ) ); }
				}
				if ( ! empty( $mi['terms'] ) ) { wp_set_object_terms( $new_id, array_map( 'intval', $mi['terms'] ), 'nav_menu' ); }
			}
			/* 5. Redirects */
			if ( class_exists( 'ec_url_redirects' ) ) {
				foreach ( $snap['redirects'] as $from ) { ec_url_redirects::remove( $from ); }
			}

			delete_option( self::TRASH_PREFIX . $trash_id );
			$index = get_option( self::TRASH_INDEX, array() );
			if ( is_array( $index ) ) { unset( $index[ $trash_id ] ); update_option( self::TRASH_INDEX, $index, 'no' ); }
			wp_cache_delete( 'wpeasycart-settings', 'wpeasycart-settings' );

			return array(
				/* translators: %s: record name */
				'message' => sprintf( __( 'Restored “%s”.', 'wp-easycart' ), $snap['name'] ),
				'type'    => $snap['type'],
				'id'      => $snap['record_id'],
			);
		}

		/* =====================================================================
		   HELPERS
		   ===================================================================== */

		/**
		 * The category plus every descendant id.
		 *
		 * @param int        $category_id
		 * @param array|null $parent_map  Optional category_id => parent_id map ( see parent_map() )
		 *                                so list pages can resolve many rows from one query instead
		 *                                of re-reading ec_category per call.
		 * @since 6.0.0 $parent_map parameter.
		 */
		public function descendant_ids( $category_id, $parent_map = null ) {
			$by_parent = array();
			if ( is_array( $parent_map ) ) {
				foreach ( $parent_map as $cid => $pid ) { $by_parent[ (int) $pid ][] = (int) $cid; }
			} else {
				global $wpdb;
				$all = $wpdb->get_results( 'SELECT category_id, parent_id FROM ec_category' );
				foreach ( $all as $c ) { $by_parent[ (int) $c->parent_id ][] = (int) $c->category_id; }
			}
			$out = array( (int) $category_id ); $queue = array( (int) $category_id ); $seen = array( (int) $category_id => true );
			while ( $queue ) {
				$cur = array_shift( $queue );
				if ( empty( $by_parent[ $cur ] ) ) { continue; }
				foreach ( $by_parent[ $cur ] as $child ) {
					if ( isset( $seen[ $child ] ) ) { continue; }
					$seen[ $child ] = true; $out[] = $child; $queue[] = $child;
				}
			}
			return $out;
		}

		/**
		 * category_id => parent_id for the whole table ( two ints per row ), built once per
		 * request so callers can pass it to descendant_ids() for many categories.
		 *
		 * @since 6.0.0
		 */
		public function parent_map() {
			static $map = null;
			if ( null === $map ) {
				global $wpdb;
				$map = array();
				foreach ( $wpdb->get_results( 'SELECT category_id, parent_id FROM ec_category' ) as $c ) { $map[ (int) $c->category_id ] = (int) $c->parent_id; }
			}
			return $map;
		}

		private function category_url( $category_id ) {
			global $wpdb;
			$post_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT post_id FROM ec_category WHERE category_id = %d', $category_id ) );
			return $post_id ? get_permalink( $post_id ) : '';
		}

		private function category_name( $category_id ) {
			global $wpdb;
			return (string) $wpdb->get_var( $wpdb->prepare( 'SELECT category_name FROM ec_category WHERE category_id = %d', $category_id ) );
		}

		private function table_exists( $table ) {
			global $wpdb;
			static $cache = array();
			if ( ! isset( $cache[ $table ] ) ) {
				$cache[ $table ] = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			}
			return $cache[ $table ];
		}

		/* =====================================================================
		   AJAX
		   ===================================================================== */

		private function guard() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) {
				wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
			}
			check_ajax_referer( self::NONCE, 'nonce' );
		}

		public function ajax_impact() {
			$this->guard();
			$r = $this->analyze( isset( $_POST['type'] ) ? sanitize_key( $_POST['type'] ) : '', isset( $_POST['id'] ) ? (int) $_POST['id'] : 0 );
			if ( is_wp_error( $r ) ) { wp_send_json_error( array( 'message' => $r->get_error_message() ) ); }
			wp_send_json_success( $r );
		}

		public function ajax_execute() {
			$this->guard();
			$r = $this->execute(
				isset( $_POST['type'] ) ? sanitize_key( $_POST['type'] ) : '',
				isset( $_POST['id'] ) ? (int) $_POST['id'] : 0,
				array(
					'strategy'  => isset( $_POST['strategy'] ) ? sanitize_key( $_POST['strategy'] ) : '',
					'target_id' => isset( $_POST['target_id'] ) ? (int) $_POST['target_id'] : 0,
					'redirect'  => ! empty( $_POST['redirect'] ) && '0' !== $_POST['redirect'],
				)
			);
			if ( is_wp_error( $r ) ) { wp_send_json_error( array( 'message' => $r->get_error_message() ) ); }
			wp_send_json_success( $r );
		}

		public function ajax_restore() {
			$this->guard();
			$r = $this->restore( isset( $_POST['trash_id'] ) ? sanitize_text_field( wp_unslash( $_POST['trash_id'] ) ) : '' );
			if ( is_wp_error( $r ) ) { wp_send_json_error( array( 'message' => $r->get_error_message() ) ); }
			wp_send_json_success( $r );
		}
	}

endif;

function wp_easycart_admin_safe_delete() {
	return wp_easycart_admin_safe_delete::instance();
}
wp_easycart_admin_safe_delete();
