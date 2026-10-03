<?php
/**
 * WP EasyCart — a product's variant rows ( ec_optionitemquantity ), kept when its option sets change ( 6.0.2 ).
 *
 * A product's variants are the combinations of the items of its option sets ( option_id_1..5 ). Each combination is
 * one ec_optionitemquantity row carrying the variant's own SKU, price ( -1 = the product's price ), stock, on / off,
 * stock tracking, Square id, Google attributes and, from 6.0.2, weight, cost and image. Before 6.0.2 every change to
 * the option sets deleted the rows and built new ones, so all of that was lost; rebuild() keeps every row whose items
 * still form a valid combination and only deletes the rest and adds what is missing.
 *
 * Valid combination: the sets are chained from slot 1 until the first slot without a set or without items ( the rule
 * every matrix builder in FREE and PRO uses ); a row is valid when it holds exactly one item of each chained set and
 * nothing beyond them. A row whose items are right but in other slots ( the sets were reordered ) is moved to the new
 * slots and keeps its id. A second row for the same combination is a duplicate and is deleted.
 *
 * Checkout ( F14 ): a line whose variant row is switched off ( is_enabled = 0 ) is refused, code variant_unavailable,
 * and every line is offered to the filter wp_easycart_cart_line_available ( false or a WP_Error refuses it, code
 * line_unavailable ). Both through wpeasycart_checkout_order_errors ( one-page checkout, manual and free completion )
 * and wpeasycart_checkout_step_error for the payment step ( classic Place order, the classic Stripe stock check ). Since bug
 * round 14 a line whose product the add to cart rules now refuse ( wp_easycart_product_can_add_to_cart() ) is refused too,
 * code product_unavailable; free gift and bundle lines are left to the offer that put them in.
 *
 * @package WP_EasyCart
 * @since   6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_variants' ) ) :

	/**
	 * Variant rows of a product.
	 */
	class wp_easycart_variants {

		/**
		 * Answers of find() for this request, "product_id:key" => row or null.
		 *
		 * @var array
		 */
		private static $found = array();

		/**
		 * What the last rebuild() did.
		 *
		 * @var array|null
		 */
		private static $last = null;

		/**
		 * The chained option sets allows() read for this request, "product_id:option ids" => chain(). Cleared by rebuild()
		 * and forget( 0 ).
		 *
		 * @var array
		 */
		private static $chains = array();

		/**
		 * Item ids by option set, option_id => ids, while rebuild_for_options() runs ( every product it rebuilds shares the
		 * sets, whose items do not change meanwhile ); null the rest of the time, when chain() always reads them.
		 *
		 * @var array|null
		 */
		private static $set_items = null;

		/**
		 * What the last rebuild_for_options() did: products rebuilt, products left for later ( over the limit ).
		 *
		 * @var array
		 */
		private static $last_batch = array(
			'products' => 0,
			'rebuilt'  => 0,
			'left'     => 0,
		);

		/**
		 * The message of a WP_Error a wp_easycart_cart_line_available listener returned, for the line_unavailable notice.
		 *
		 * @var string
		 */
		private static $line_message = '';

		/**
		 * Hooks.
		 */
		public static function init() {
			add_filter( 'wpeasycart_checkout_order_errors', array( __CLASS__, 'checkout_errors' ), 25, 2 );
			add_filter( 'wpeasycart_checkout_step_error', array( __CLASS__, 'checkout_step_error' ), 25, 3 );
			add_filter( 'wpeasycart_cart_errors', array( __CLASS__, 'cart_error_notes' ) );
			add_filter( 'wpeasycart_valid_cart_errors', array( __CLASS__, 'valid_cart_errors' ) );
		}

		/**
		 * The 6.0.2 variant columns ( weight, cost, image ) exist.
		 *
		 * @return bool
		 */
		public static function ready() {
			return function_exists( 'get_option' ) && (int) get_option( 'ec_option_db_new_version' ) >= 117;
		}

		/**
		 * Most variants one product may have ( filter wp_easycart_variant_matrix_limit, as PRO's editor ).
		 *
		 * @return int
		 */
		public static function limit() {
			return max( 1, (int) apply_filters( 'wp_easycart_variant_matrix_limit', 20000 ) );
		}

		/**
		 * Five slots from option item ids given as a list ( slot 1 first ), an array keyed 1..5, a row or object with
		 * optionitem_id_1..5, or an array with those keys.
		 *
		 * @param mixed $optionitem_ids Option item ids.
		 * @return int[] Keys 1..5.
		 */
		public static function slots( $optionitem_ids ) {
			$slots = array(
				1 => 0,
				2 => 0,
				3 => 0,
				4 => 0,
				5 => 0,
			);
			if ( is_object( $optionitem_ids ) ) {
				$optionitem_ids = get_object_vars( $optionitem_ids );
			}
			if ( ! is_array( $optionitem_ids ) ) {
				return $slots;
			}
			if ( isset( $optionitem_ids['optionitem_id_1'] ) || array_key_exists( 'optionitem_id_1', $optionitem_ids ) ) {
				for ( $slot = 1; $slot <= 5; $slot++ ) {
					$slots[ $slot ] = isset( $optionitem_ids[ 'optionitem_id_' . $slot ] ) ? (int) $optionitem_ids[ 'optionitem_id_' . $slot ] : 0;
				}
				return $slots;
			}
			if ( ! array_key_exists( 0, $optionitem_ids ) ) {
				for ( $slot = 1; $slot <= 5; $slot++ ) {
					$slots[ $slot ] = isset( $optionitem_ids[ $slot ] ) ? (int) $optionitem_ids[ $slot ] : 0;
				}
				return $slots;
			}
			$values = array_values( $optionitem_ids );
			for ( $slot = 1; $slot <= 5; $slot++ ) {
				$slots[ $slot ] = isset( $values[ $slot - 1 ] ) ? (int) $values[ $slot - 1 ] : 0;
			}
			return $slots;
		}

		/**
		 * A combination's key: the five option item ids by slot, joined with dashes ( '12-34-0-0-0' ).
		 *
		 * @param mixed $optionitem_ids See slots().
		 * @return string
		 */
		public static function key( $optionitem_ids ) {
			return implode( '-', self::slots( $optionitem_ids ) );
		}

		/**
		 * The variant row of a product for these option items ( every column ), or null.
		 *
		 * @param int   $product_id     Product.
		 * @param mixed $optionitem_ids See slots().
		 * @return object|null
		 */
		public static function find( $product_id, $optionitem_ids ) {
			global $wpdb;
			$product_id = (int) $product_id;
			$slots      = self::slots( $optionitem_ids );
			$cache_key  = $product_id . ':' . implode( '-', $slots );
			if ( array_key_exists( $cache_key, self::$found ) ) {
				return self::$found[ $cache_key ];
			}
			$row                       = ( $product_id > 0 ) ? $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_optionitemquantity WHERE product_id = %d AND optionitem_id_1 = %d AND optionitem_id_2 = %d AND optionitem_id_3 = %d AND optionitem_id_4 = %d AND optionitem_id_5 = %d ORDER BY optionitemquantity_id ASC LIMIT 1', $product_id, $slots[1], $slots[2], $slots[3], $slots[4], $slots[5] ) ) : null;
			self::$found[ $cache_key ] = $row ? $row : null;
			return self::$found[ $cache_key ];
		}

		/**
		 * Forget find()'s answers ( for one product, or all ).
		 *
		 * @param int $product_id Product, or 0 for every product.
		 */
		public static function forget( $product_id = 0 ) {
			if ( ! $product_id ) {
				self::$found  = array();
				self::$chains = array();
				return;
			}
			$prefix = (int) $product_id . ':';
			foreach ( array_keys( self::$found ) as $cache_key ) {
				if ( 0 === strpos( $cache_key, $prefix ) ) {
					unset( self::$found[ $cache_key ] );
				}
			}
		}

		/**
		 * A product's option set ids by slot.
		 *
		 * @param int|object $product Product id or row.
		 * @return int[] Keys 1..5.
		 */
		public static function option_ids( $product ) {
			global $wpdb;
			if ( ! is_object( $product ) ) {
				$product = $wpdb->get_row( $wpdb->prepare( 'SELECT option_id_1, option_id_2, option_id_3, option_id_4, option_id_5 FROM ec_product WHERE product_id = %d', (int) $product ) );
			}
			$ids = array();
			for ( $slot = 1; $slot <= 5; $slot++ ) {
				$ids[ $slot ] = ( $product && isset( $product->{ 'option_id_' . $slot } ) ) ? (int) $product->{ 'option_id_' . $slot } : 0;
			}
			return $ids;
		}

		/**
		 * The option sets a matrix is built from: chained from slot 1 until the first slot without a set or without items.
		 *
		 * @param array $option_ids Option set ids by slot ( see slots() for the shapes ).
		 * @return array depth, combinations ( stops counting once over limit() ), items ( slot => item ids ), sets ( slot =>
		 *               item id => true ).
		 */
		private static function chain( $option_ids ) {
			global $wpdb;
			$option_ids = self::slots( $option_ids );
			$out        = array(
				'depth'        => 0,
				'combinations' => 0,
				'items'        => array(),
				'sets'         => array(),
			);
			for ( $slot = 1; $slot <= 5; $slot++ ) {
				if ( $option_ids[ $slot ] <= 0 ) {
					break;
				}
				if ( is_array( self::$set_items ) && isset( self::$set_items[ $option_ids[ $slot ] ] ) ) {
					$items = self::$set_items[ $option_ids[ $slot ] ];
				} else {
					$items = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT optionitem_id FROM ec_optionitem WHERE option_id = %d ORDER BY optionitem_order ASC, optionitem_id ASC', $option_ids[ $slot ] ) ) );
					if ( is_array( self::$set_items ) ) {
						self::$set_items[ $option_ids[ $slot ] ] = $items;
					}
				}
				if ( empty( $items ) ) {
					break;
				}
				$out['items'][ $slot ] = $items;
				$out['sets'][ $slot ]  = array_fill_keys( $items, true );
				$out['depth']          = $slot;
				$out['combinations']   = ( 1 === $slot ) ? count( $items ) : $out['combinations'] * count( $items );
				if ( $out['combinations'] > self::limit() ) {
					break;
				}
			}
			return $out;
		}

		/**
		 * These option items are one of the product's variants as its option sets stand now: one item of each chained set,
		 * in its slot, and nothing beyond them.
		 *
		 * @param int   $product_id     Product.
		 * @param mixed $optionitem_ids See slots().
		 * @return bool
		 */
		public static function allows( $product_id, $optionitem_ids ) {
			$product_id = (int) $product_id;
			$option_ids = self::option_ids( $product_id );
			$cache_key  = $product_id . ':' . implode( '-', $option_ids );
			if ( ! isset( self::$chains[ $cache_key ] ) ) {
				self::$chains[ $cache_key ] = self::chain( $option_ids );
			}
			$chain = self::$chains[ $cache_key ];
			if ( $chain['depth'] <= 0 ) {
				return false;
			}
			$slots = self::slots( $optionitem_ids );
			for ( $slot = 1; $slot <= 5; $slot++ ) {
				if ( $slot <= $chain['depth'] ? ! isset( $chain['sets'][ $slot ][ $slots[ $slot ] ] ) : 0 !== $slots[ $slot ] ) {
					return false;
				}
			}
			return true;
		}

		/**
		 * What rebuild() would do, changing nothing.
		 *
		 * A row that is no longer a valid combination because a set was added ( its items are part of new combinations:
		 * Small becomes Small / Red and Small / Blue ) is named as the parent of each new combination that extends it ( the
		 * one with the most items in common when several do ): rebuild() gives that combination the parent's on / off, and
		 * its stock only when asked to ( the option set editor's Assign, which copied it before 6.0.2 ).
		 *
		 * @since 6.0.2 inherit, parents, inherited.
		 * @param int   $product_id Product.
		 * @param array $option_ids Option set ids by slot ( see slots() for the shapes ).
		 * @return array|WP_Error array( 'depth', 'combinations', 'kept', 'moved', 'deleted', 'added', 'keep' => ids,
		 *                        'reslot' => id => slots, 'delete' => ids, 'insert' => list of slots, 'edited_deleted',
		 *                        'inherit' => insert index => row id it extends, 'parents' => row id => array( quantity,
		 *                        is_enabled ), 'inherited' ) or WP_Error wp_easycart_variant_limit when the sets make more
		 *                        variants than limit().
		 */
		public static function plan( $product_id, $option_ids ) {
			global $wpdb;
			$product_id = (int) $product_id;

			/* The chained sets and their items. */
			$chain      = self::chain( $option_ids );
			$slot_items = $chain['items'];
			$slot_set   = $chain['sets'];
			$depth      = $chain['depth'];
			$combos     = $chain['combinations'];
			$limit      = self::limit();
			if ( $combos > $limit ) {
				return new WP_Error(
					'wp_easycart_variant_limit',
					sprintf(
						/* translators: 1: variations the option sets would create, 2: the limit. */
						__( 'These option sets would create %1$s variations, more than the %2$s this store allows. Remove an option set or reduce the items in one of them.', 'wp-easycart' ),
						function_exists( 'number_format_i18n' ) ? number_format_i18n( $combos ) : $combos,
						function_exists( 'number_format_i18n' ) ? number_format_i18n( $limit ) : $limit
					)
				);
			}

			$rows    = (array) $wpdb->get_results( $wpdb->prepare( "SELECT optionitemquantity_id, optionitem_id_1, optionitem_id_2, optionitem_id_3, optionitem_id_4, optionitem_id_5, quantity, is_enabled, ( sku != '' OR price > -1 OR quantity != 0 OR is_enabled = 0 OR is_stock_tracking_enabled = 0 OR reorder_point != -1 OR square_id != '' OR ( google_merchant IS NOT NULL AND google_merchant != '' ) ) AS edited FROM ec_optionitemquantity WHERE product_id = %d ORDER BY optionitemquantity_id ASC", $product_id ) );
			$claimed = array();
			$keep    = array();
			$reslot  = array();
			$delete  = array();
			$edited  = 0;
			$parents = array(); /* sorted item ids => row, for rows a new combination can extend */
			$by_id   = array();
			foreach ( $rows as $row ) {
				$row_id  = (int) $row->optionitemquantity_id;
				$current = self::slots( $row );
				$target  = ( $depth > 0 ) ? self::target_slots( $current, $depth, $slot_set ) : null;
				$key     = $target ? implode( '-', $target ) : '';
				if ( ! $target || isset( $claimed[ $key ] ) ) {
					$delete[] = $row_id;
					if ( (int) $row->edited ) {
						++$edited;
					}
					/* Fewer items than the sets now chain ( a set was added ): new combinations may extend it. */
					$items = array_values( array_filter( $current ) );
					if ( ! $target && $items && count( $items ) < $depth ) {
						sort( $items );
						$parent_key = implode( '-', $items );
						if ( ! isset( $parents[ $parent_key ] ) ) {
							$parents[ $parent_key ] = $row_id;
							$by_id[ $row_id ]       = array(
								'quantity'   => (int) $row->quantity,
								'is_enabled' => (int) $row->is_enabled,
							);
						}
					}
					continue;
				}
				$claimed[ $key ] = $row_id;
				$keep[]          = $row_id;
				if ( $target !== $current ) {
					$reslot[ $row_id ] = $target;
				}
			}

			/* Every combination, slot 1 first; the ones no kept row covers are added. */
			$insert = array();
			if ( $depth > 0 ) {
				$combinations = array( array() );
				for ( $slot = 1; $slot <= $depth; $slot++ ) {
					$next = array();
					foreach ( $combinations as $partial ) {
						foreach ( $slot_items[ $slot ] as $item_id ) {
							$with          = $partial;
							$with[ $slot ] = $item_id;
							$next[]        = $with;
						}
					}
					$combinations = $next;
				}
				foreach ( $combinations as $combination ) {
					$slots = array(
						1 => 0,
						2 => 0,
						3 => 0,
						4 => 0,
						5 => 0,
					);
					foreach ( $combination as $slot => $item_id ) {
						$slots[ $slot ] = (int) $item_id;
					}
					if ( ! isset( $claimed[ implode( '-', $slots ) ] ) ) {
						$insert[] = $slots;
					}
				}
			}

			// A new combination extends the deleted row with the most of its items ( every subset of its items is looked up:
			// at most 30 per combination ).
			$inherit = array();
			if ( $parents && $insert ) {
				$masks = array();
				for ( $mask = ( 1 << $depth ) - 2; $mask > 0; $mask-- ) {
					$masks[] = $mask;
				}
				usort(
					$masks,
					function ( $a, $b ) {
						return substr_count( decbin( $b ), '1' ) - substr_count( decbin( $a ), '1' );
					}
				);
				foreach ( $insert as $index => $slots ) {
					$items = array_values( array_filter( $slots ) );
					sort( $items );
					foreach ( $masks as $mask ) {
						$subset = array();
						foreach ( $items as $bit => $item_id ) {
							if ( $mask & ( 1 << $bit ) ) {
								$subset[] = $item_id;
							}
						}
						$subset_key = implode( '-', $subset );
						if ( isset( $parents[ $subset_key ] ) ) {
							$inherit[ $index ] = $parents[ $subset_key ];
							break;
						}
					}
				}
			}

			return array(
				'depth'          => $depth,
				'combinations'   => ( $depth > 0 ) ? $combos : 0,
				'kept'           => count( $keep ),
				'moved'          => count( $reslot ),
				'deleted'        => count( $delete ),
				'added'          => count( $insert ),
				'edited_deleted' => $edited,
				'keep'           => $keep,
				'reslot'         => $reslot,
				'delete'         => $delete,
				'insert'         => $insert,
				'inherit'        => $inherit,
				'parents'        => array_intersect_key( $by_id, array_flip( array_unique( $inherit ) ) ),
				'inherited'      => count( $inherit ),
			);
		}

		/**
		 * The slots a row belongs in for these chained sets, or null when it is not a valid combination.
		 *
		 * @param int[] $current  The row's items by slot.
		 * @param int   $depth    Chained slots.
		 * @param array $slot_set Slot => item id => true.
		 * @return int[]|null
		 */
		private static function target_slots( $current, $depth, $slot_set ) {
			/* Already right. */
			$valid = true;
			for ( $slot = 1; $slot <= 5; $slot++ ) {
				$item_id = $current[ $slot ];
				if ( $slot <= $depth ) {
					if ( ! $item_id || ! isset( $slot_set[ $slot ][ $item_id ] ) ) {
						$valid = false;
						break;
					}
				} elseif ( 0 !== $item_id ) {
					$valid = false;
					break;
				}
			}
			if ( $valid ) {
				return $current;
			}
			/* The right items in other slots ( the sets were reordered ): one item for each chained set. */
			$items = array_values( array_filter( $current ) );
			if ( count( $items ) !== $depth ) {
				return null;
			}
			$target = array(
				1 => 0,
				2 => 0,
				3 => 0,
				4 => 0,
				5 => 0,
			);
			foreach ( $items as $item_id ) {
				$placed = false;
				for ( $slot = 1; $slot <= $depth; $slot++ ) {
					if ( ! $target[ $slot ] && isset( $slot_set[ $slot ][ $item_id ] ) ) {
						$target[ $slot ] = $item_id;
						$placed          = true;
						break;
					}
				}
				if ( ! $placed ) {
					return null;
				}
			}
			return $target;
		}

		/**
		 * Bring a product's variant rows in line with these option sets. Rows that still form a valid combination keep
		 * their id and every setting ( SKU, price, stock, on / off, tracking, weight, cost, image, Square id, Google
		 * attributes ); rows that don't are deleted; missing combinations are added with the defaults ( no SKU, the
		 * product's price, no stock, on ), except that a combination extending a deleted row ( a set was added ) takes that
		 * row's on / off ( plan() ). It takes the row's stock too only with $args['inherit_stock']: every combination that
		 * extends a row would get all of it ( Small = 10 becomes Small / Red = 10 and Small / Blue = 10 ), so the product
		 * editor's saves leave new combinations at 0 and only the option set editor's Assign, which always copied it, asks.
		 * When rows were deleted or added, a product that tracks stock per variant gets its stock from its variants again
		 * ( rollup_stock() ). Does not change the product's option_id_1..5 ( the caller saves those ).
		 *
		 * Fires wp_easycart_variants_rebuilt( $product_id, array( 'kept', 'moved', 'deleted' => ids, 'added' => ids,
		 * 'inherited', 'inherited_stock' ) ) when anything changed.
		 *
		 * @since 6.0.2 $args ( inherit_stock ).
		 * @param int   $product_id Product.
		 * @param array $option_ids Option set ids by slot ( see slots() for the shapes ).
		 * @param array $args       inherit_stock ( bool, default false ): a new combination that extends a deleted row also
		 *                          takes that row's stock.
		 * @return true|WP_Error
		 */
		public static function rebuild( $product_id, $option_ids, $args = array() ) {
			global $wpdb;
			$product_id    = (int) $product_id;
			$inherit_stock = is_array( $args ) && ! empty( $args['inherit_stock'] );
			self::$last    = null;
			if ( $product_id <= 0 ) {
				return new WP_Error( 'wp_easycart_product', __( 'Product not found.', 'wp-easycart' ) );
			}
			$plan = self::plan( $product_id, $option_ids );
			if ( is_wp_error( $plan ) ) {
				return $plan;
			}
			self::$chains = array(); /* the sets or their items may have changed */

			foreach ( array_chunk( $plan['delete'], 500 ) as $chunk ) {
				$wpdb->query( 'DELETE FROM ec_optionitemquantity WHERE optionitemquantity_id IN ( ' . implode( ',', array_map( 'intval', $chunk ) ) . ' )' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- every id is (int) cast.
			}
			foreach ( $plan['reslot'] as $row_id => $slots ) {
				$wpdb->query( $wpdb->prepare( 'UPDATE ec_optionitemquantity SET optionitem_id_1 = %d, optionitem_id_2 = %d, optionitem_id_3 = %d, optionitem_id_4 = %d, optionitem_id_5 = %d WHERE optionitemquantity_id = %d AND product_id = %d', $slots[1], $slots[2], $slots[3], $slots[4], $slots[5], (int) $row_id, $product_id ) );
			}
			$added = array();
			// A product sold through Square gets new combinations switched off, as the editor's own builder does: Square sync
			// makes only the rows Square sells, and a row with no Square variation must not be bought.
			$square = ! empty( $plan['insert'] ) && (bool) $wpdb->get_var( $wpdb->prepare( "SELECT product_id FROM ec_product WHERE product_id = %d AND square_id != ''", $product_id ) );
			foreach ( array_chunk( $plan['insert'], 500, true ) as $chunk ) {
				$values = array();
				foreach ( $chunk as $index => $slots ) {
					// A combination that extends a deleted row takes its on / off ( plan() ), and its stock when asked; else no
					// stock, and on ( off for a Square product ).
					$parent   = ( isset( $plan['inherit'][ $index ] ) && isset( $plan['parents'][ $plan['inherit'][ $index ] ] ) ) ? $plan['parents'][ $plan['inherit'][ $index ] ] : null;
					$values[] = $wpdb->prepare( '( %d, %d, %d, %d, %d, %d, %d, %d )', $product_id, $slots[1], $slots[2], $slots[3], $slots[4], $slots[5], ( $parent && $inherit_stock ) ? (int) $parent['quantity'] : 0, $square ? 0 : ( $parent ? (int) $parent['is_enabled'] : 1 ) );
				}
				$wpdb->query( 'INSERT INTO ec_optionitemquantity( product_id, optionitem_id_1, optionitem_id_2, optionitem_id_3, optionitem_id_4, optionitem_id_5, quantity, is_enabled ) VALUES ' . implode( ', ', $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- every row is a $wpdb->prepare() fragment.
			}
			if ( ! empty( $plan['insert'] ) ) {
				/* The new rows' ids, for listeners ( a multi-row INSERT reports only its first id ). */
				$known = array_flip( $plan['keep'] );
				foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SELECT optionitemquantity_id FROM ec_optionitemquantity WHERE product_id = %d', $product_id ) ) as $row_id ) {
					if ( ! isset( $known[ (int) $row_id ] ) ) {
						$added[] = (int) $row_id;
					}
				}
			}
			self::forget( $product_id );
			/* The product's stock follows its variants when it tracks stock per variant ( rows went or came ). */
			if ( $plan['deleted'] || $plan['added'] ) {
				self::rollup_stock( $product_id );
			}

			self::$last = array(
				'kept'            => $plan['kept'],
				'moved'           => $plan['moved'],
				'deleted'         => $plan['delete'],
				'added'           => $added,
				'inherited'       => $plan['inherited'],
				'inherited_stock' => $inherit_stock ? $plan['inherited'] : 0,
			);
			if ( $plan['deleted'] || $plan['added'] || $plan['moved'] ) {
				/**
				 * A product's variant rows changed with its option sets.
				 *
				 * @since 6.0.2
				 * @param int   $product_id Product.
				 * @param array $changes    kept ( count ), moved ( count ), deleted ( row ids ), added ( row ids ), inherited
				 *                          ( count of added rows that extend a deleted row and took its on / off ),
				 *                          inherited_stock ( how many of those took its stock too ).
				 */
				do_action( 'wp_easycart_variants_rebuilt', $product_id, self::$last );
			}
			return true;
		}

		/**
		 * What the last rebuild() did: kept, moved ( counts ), deleted, added ( row ids ), inherited, inherited_stock
		 * ( counts, see rebuild() ); null before one ran.
		 *
		 * @return array|null
		 */
		public static function last() {
			return self::$last;
		}

		/**
		 * A product that tracks stock per variant: its stock is the sum of its enabled, tracked variants ( an untracked
		 * enabled variant counts as 1, as WP EasyCart PRO's variant manager counts ). Other products are left alone.
		 *
		 * @since 6.0.2
		 * @param int $product_id Product.
		 * @return bool The stock was worked out again.
		 */
		public static function rollup_stock( $product_id ) {
			global $wpdb;
			$product_id = (int) $product_id;
			if ( $product_id <= 0 || ! (int) $wpdb->get_var( $wpdb->prepare( 'SELECT use_optionitem_quantity_tracking FROM ec_product WHERE product_id = %d', $product_id ) ) ) {
				return false;
			}
			$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE( SUM( CASE WHEN is_stock_tracking_enabled = 1 THEN quantity ELSE 1 END ), 0 ) FROM ec_optionitemquantity WHERE product_id = %d AND is_enabled = 1', $product_id ) );
			$wpdb->query( $wpdb->prepare( 'UPDATE ec_product SET stock_quantity = %d WHERE product_id = %d', $total, $product_id ) );
			if ( method_exists( 'ec_db', 'product_cache_changed' ) ) {
				ec_db::product_cache_changed();
			}
			return true;
		}

		/**
		 * Rebuild every product that uses one of these option sets in a slot ( after items were added to a set ), at most
		 * wp_easycart_variant_rebuild_limit products in one call ( 200; products that track stock per variant first ): a
		 * set shared by thousands of products would otherwise take one admin request past its time limit. The products
		 * left out keep their rows until the product editor opens them ( it adds the missing combinations ) or saves their
		 * options; last_batch() says how many. The sets' items are read once for the whole call.
		 *
		 * @since 6.0.2 the limit, last_batch().
		 * @param int[] $option_ids   Option sets.
		 * @param int[] $without_ids  Sets to leave out of the products' matrices ( a set that is no longer a variation set ).
		 * @return int Rows deleted.
		 */
		public static function rebuild_for_options( $option_ids, $without_ids = array() ) {
			global $wpdb;
			self::$last_batch = array(
				'products' => 0,
				'rebuilt'  => 0,
				'left'     => 0,
			);

			$option_ids  = array_values( array_filter( array_map( 'intval', (array) $option_ids ) ) );
			$without_ids = array_map( 'intval', (array) $without_ids );
			if ( empty( $option_ids ) ) {
				return 0;
			}
			/**
			 * Most products one option set save rebuilds in its request ( the rest are rebuilt when next opened or saved ).
			 *
			 * @since 6.0.2
			 * @param int   $limit      200.
			 * @param int[] $option_ids The option sets that changed.
			 */
			$limit    = max( 1, (int) apply_filters( 'wp_easycart_variant_rebuild_limit', 200, $option_ids ) );
			$in       = implode( ',', $option_ids );
			$where    = "option_id_1 IN ( $in ) OR option_id_2 IN ( $in ) OR option_id_3 IN ( $in ) OR option_id_4 IN ( $in ) OR option_id_5 IN ( $in )";
			$total    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM ec_product WHERE $where" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is built from $in, an implode of (int) ids.
			$products = (array) $wpdb->get_results( "SELECT product_id, option_id_1, option_id_2, option_id_3, option_id_4, option_id_5 FROM ec_product WHERE $where ORDER BY use_optionitem_quantity_tracking DESC, product_id ASC LIMIT " . (int) $limit ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- $where is built from $in, an implode of (int) ids; the limit is (int).
			$deleted  = 0;
			$rebuilt  = 0;

			self::$set_items = array();
			try {
				foreach ( $products as $product ) {
					$slots = self::option_ids( $product );
					foreach ( $slots as $slot => $option_id ) {
						if ( in_array( $option_id, $without_ids, true ) ) {
							$slots[ $slot ] = 0;
						}
					}
					if ( true === self::rebuild( (int) $product->product_id, $slots ) ) {
						$deleted += count( self::$last['deleted'] );
						++$rebuilt;
					}
				}
			} finally {
				self::$set_items = null;
			}
			self::$last_batch = array(
				'products' => $total,
				'rebuilt'  => $rebuilt,
				'left'     => max( 0, $total - count( $products ) ),
			);
			return $deleted;
		}

		/**
		 * What the last rebuild_for_options() did: products ( using the sets ), rebuilt, left ( over the limit, rebuilt when
		 * next opened or saved ).
		 *
		 * @since 6.0.2
		 * @return array
		 */
		public static function last_batch() {
			return self::$last_batch;
		}

		/**
		 * The variant a cart line was put in as is switched off ( ec_optionitemquantity.is_enabled = 0 ).
		 *
		 * @param int   $product_id     Product.
		 * @param mixed $optionitem_ids See slots().
		 * @return bool
		 */
		public static function is_disabled( $product_id, $optionitem_ids ) {
			$slots = self::slots( $optionitem_ids );
			if ( ! array_filter( $slots ) ) {
				return false; /* no options chosen: the product itself, not a variant */
			}
			$row = self::find( $product_id, $slots );
			return $row && isset( $row->is_enabled ) && ! (int) $row->is_enabled;
		}

		/**
		 * The shopper's delivery address from the checkout session, in the shape the shipping group quote uses.
		 *
		 * @return array country, state, zip, city, address1, address2, phone, name.
		 */
		public static function checkout_address() {
			$cd   = ( isset( $GLOBALS['ec_cart_data'] ) && isset( $GLOBALS['ec_cart_data']->cart_data ) ) ? $GLOBALS['ec_cart_data']->cart_data : null;
			$get  = function ( $key ) use ( $cd ) {
				return ( $cd && isset( $cd->{ $key } ) ) ? (string) $cd->{ $key } : '';
			};
			$kind = ( '' !== $get( 'shipping_country' ) && '0' !== $get( 'shipping_country' ) ) ? 'shipping' : 'billing';
			return array(
				'country'  => $get( $kind . '_country' ),
				'state'    => $get( $kind . '_state' ),
				'zip'      => $get( $kind . '_zip' ),
				'city'     => $get( $kind . '_city' ),
				'address1' => $get( $kind . '_address_line_1' ),
				'address2' => $get( $kind . '_address_line_2' ),
				'phone'    => $get( $kind . '_phone' ),
				'name'     => trim( $get( $kind . '_first_name' ) . ' ' . $get( $kind . '_last_name' ) ),
			);
		}

		/**
		 * Why these cart lines cannot be ordered now: product_unavailable ( 6.0.2: the add to cart rules refuse the product now ),
		 * variant_unavailable ( a switched off variant ) and line_unavailable ( a wp_easycart_cart_line_available listener said no ).
		 *
		 * @param array $cart_items ec_cartitem objects.
		 * @return string[] Codes.
		 */
		public static function line_errors( $cart_items ) {
			$codes   = array();
			$address = self::checkout_address();
			/* 6.0.2: the add to cart rules again for what is in the cart ( wp_easycart_product_can_add_to_cart(): switched off, catalog
			 * or inquiry mode, login for pricing, another customer role, a store closed to this shopper ), one query for every line. */
			$check_products = class_exists( 'wp_easycart_storefront_access' );
			if ( $check_products ) {
				$product_ids = array();
				foreach ( (array) $cart_items as $cart_item ) {
					if ( is_object( $cart_item ) && ! empty( $cart_item->product_id ) ) {
						$product_ids[] = (int) $cart_item->product_id;
					}
				}
				wp_easycart_storefront_access::prime( $product_ids );
			}
			foreach ( (array) $cart_items as $cart_item ) {
				if ( ! is_object( $cart_item ) || empty( $cart_item->product_id ) ) {
					continue;
				}
				/* A free gift or bundle line was put in by the offer that owns it, not added by the shopper: the offer decides. */
				$offer_line = ( ! empty( $cart_item->free_gift_offer_id ) || ( isset( $cart_item->bundle_group_key ) && '' !== (string) $cart_item->bundle_group_key ) );
				if ( $check_products && ! $offer_line && true !== wp_easycart_product_can_add_to_cart( (int) $cart_item->product_id ) ) {
					$codes[] = 'product_unavailable';
					continue;
				}
				$slots = array();
				for ( $slot = 1; $slot <= 5; $slot++ ) {
					$slots[ $slot ] = isset( $cart_item->{ 'optionitem' . $slot . '_id' } ) ? (int) $cart_item->{ 'optionitem' . $slot . '_id' } : 0;
				}
				if ( self::is_disabled( (int) $cart_item->product_id, $slots ) ) {
					$codes[] = 'variant_unavailable';
					continue;
				}
				/**
				 * Whether a cart line can be ordered now ( e.g. a fulfillment partner stopped making the variant, or does not
				 * ship it to the address ). False or a WP_Error stops the checkout; the WP_Error's message is shown.
				 *
				 * @since 6.0.2
				 * @param bool|WP_Error $available  True.
				 * @param ec_cartitem   $cart_item  The line.
				 * @param array         $address    country, state, zip, city, address1, address2, phone, name ( delivery address ).
				 */
				$available = apply_filters( 'wp_easycart_cart_line_available', true, $cart_item, $address );
				if ( false === $available || is_wp_error( $available ) ) {
					$codes[] = 'line_unavailable';
					if ( is_wp_error( $available ) && '' !== (string) $available->get_error_message() && '' === self::$line_message ) {
						self::$line_message = (string) $available->get_error_message();
					}
				}
			}
			return array_values( array_unique( $codes ) );
		}

		/**
		 * Filter wpeasycart_checkout_order_errors.
		 *
		 * @param array       $errors   Codes.
		 * @param ec_cartpage $cartpage The checkout.
		 * @return array
		 */
		public static function checkout_errors( $errors, $cartpage = null ) {
			$errors = is_array( $errors ) ? $errors : array();
			if ( ! is_object( $cartpage ) || ! isset( $cartpage->cart ) || ! isset( $cartpage->cart->cart ) ) {
				return $errors;
			}
			return array_merge( $errors, self::line_errors( $cartpage->cart->cart ) );
		}

		/**
		 * Filter wpeasycart_checkout_step_error: the classic Place order ( payment step ) asks too.
		 *
		 * @param array|null  $error    Earlier answer.
		 * @param string      $step     information | shipping | payment.
		 * @param ec_cartpage $cartpage The checkout.
		 * @return array|null
		 */
		public static function checkout_step_error( $error, $step = '', $cartpage = null ) {
			if ( null !== $error || 'payment' !== $step || ! is_object( $cartpage ) || ! isset( $cartpage->cart ) || ! isset( $cartpage->cart->cart ) ) {
				return $error;
			}
			$codes = self::line_errors( $cartpage->cart->cart );
			if ( empty( $codes ) ) {
				return $error;
			}
			return array(
				'code' => $codes[0],
				'page' => 'checkout_payment',
			);
		}

		/**
		 * Filter wpeasycart_valid_cart_errors: the cart page shows product_unavailable, variant_unavailable and line_unavailable
		 * when a redirect carries them ( a dynamic cart, the one-page checkout, passes only listed codes on ).
		 *
		 * @since 6.0.2
		 * @param array $codes Codes.
		 * @return array
		 */
		public static function valid_cart_errors( $codes ) {
			$codes   = is_array( $codes ) ? $codes : array();
			$codes[] = 'product_unavailable';
			$codes[] = 'variant_unavailable';
			$codes[] = 'line_unavailable';
			return $codes;
		}

		/**
		 * Filter wpeasycart_cart_errors: the notices for product_unavailable, variant_unavailable and line_unavailable.
		 *
		 * @param array $notes Code => text.
		 * @return array
		 */
		public static function cart_error_notes( $notes ) {
			$notes = is_array( $notes ) ? $notes : array();
			if ( ! isset( $notes['product_unavailable'] ) ) {
				$notes['product_unavailable'] = self::text( 'product_unavailable', __( 'An item in your cart is not available to order. Please remove it to continue.', 'wp-easycart' ) );
			}
			if ( ! isset( $notes['variant_unavailable'] ) ) {
				$notes['variant_unavailable'] = self::text( 'variant_unavailable', __( 'An item in your cart is no longer available in the option you chose. Please remove it and choose another option.', 'wp-easycart' ) );
			}
			if ( ! isset( $notes['line_unavailable'] ) ) {
				$notes['line_unavailable'] = ( '' !== self::$line_message ) ? self::$line_message : self::text( 'line_unavailable', __( 'An item in your cart cannot be ordered right now. Please remove it or try again later.', 'wp-easycart' ) );
			}
			return $notes;
		}

		/**
		 * A text from the language file's product_managed section, else the fallback.
		 *
		 * @param string $key      Key.
		 * @param string $fallback English text.
		 * @return string
		 */
		public static function text( $key, $fallback ) {
			$text = function_exists( 'wp_easycart_language' ) ? wp_easycart_language()->get_text( 'product_managed', $key ) : '';
			return ( is_string( $text ) && '' !== $text ) ? $text : $fallback;
		}
	}

	wp_easycart_variants::init();

endif;
