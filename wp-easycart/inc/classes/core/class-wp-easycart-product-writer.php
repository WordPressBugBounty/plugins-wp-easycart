<?php
/**
 * WP EasyCart — create and update products from code ( importers and catalog extensions ) ( 6.0.2 ).
 *
 * The static class wp_easycart_product_writer writes products the way the admin and the importers do: the ec_product row, its
 * WordPress page ( through wp_easycart_post_sync ), option sets that belong to the product, its variant rows, images
 * copied into the media library and categories. Built for the catalog sync of fulfillment partners ( WP EasyCart Premium's
 * wp_easycart_premium_catalog_writer ) and usable by any importer.
 *
 * Product-owned option sets: ec_option.owner_product_id is the product a set belongs to ( 0 = a shared set from the option
 * set library ). Owned sets are left out of every shared option set list and picker ( shared_sets_sql() ), are deleted
 * with their product and cloned when the product is duplicated.
 *
 * Option item keys: save_options() matches an incoming item to a stored one by its key, then by name. The key is kept
 * in the item's own row, ec_optionitem.optionitem_initial_value. That column only means something for modifier ( advanced )
 * types ( a grid's starting quantity, a text input's default, conditional logic ); for the Dropdown and Swatch variation
 * types save_options() writes, the storefront never reads it, and the option set editor keeps it as it is. Keys longer
 * than 255 characters are stored as 'md5:' + their md5.
 *
 * @package WP_EasyCart
 * @since   6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_product_writer' ) ) :

	/**
	 * Products from code.
	 */
	class wp_easycart_product_writer {

		/**
		 * Products wpeasycart_product_updated already went out for in this request.
		 *
		 * @var array
		 */
		private static $updated = array();

		/**
		 * The columns of ec_product.
		 *
		 * @var array|null
		 */
		private static $columns = null;

		/**
		 * A full cache flush is owed at the end of the request ( an external object cache holds product lists ).
		 *
		 * @var bool
		 */
		private static $flush_owed = false;

		/**
		 * Hooks.
		 */
		public static function init() {
			add_action( 'wpeasycart_product_updated', array( __CLASS__, 'note_updated' ), 1, 1 );
			add_action( 'wpeasycart_product_deleted', array( __CLASS__, 'delete_owned_sets' ), 10, 1 );
			add_action( 'wpeasycart_product_duplicated', array( __CLASS__, 'clone_owned_sets' ), 5, 2 );
		}

		/**
		 * The 6.0.2 product columns exist ( fulfillment_provider, ec_option.owner_product_id; EC_UPGRADE_DB 117 ).
		 *
		 * @return bool
		 */
		public static function ready() {
			return (int) get_option( 'ec_option_db_new_version' ) >= 117;
		}

		/**
		 * SQL to add to a WHERE that lists shared option sets: leaves out the sets that belong to one product. With a
		 * product id, that product's own sets are listed too ( its own editor ).
		 *
		 * @param string $alias      Table alias ( '' = ec_option ).
		 * @param int    $product_id Product whose own sets stay in.
		 * @return string ' AND ( ... )', or '' before the database update.
		 */
		public static function shared_sets_sql( $alias = '', $product_id = 0 ) {
			global $wpdb;
			if ( ! self::ready() ) {
				return '';
			}
			$column = ( '' !== $alias ? preg_replace( '/[^A-Za-z0-9_]/', '', $alias ) : 'ec_option' ) . '.owner_product_id';
			if ( (int) $product_id > 0 ) {
				return $wpdb->prepare( ' AND ( ' . $column . ' = 0 OR ' . $column . ' = %d )', (int) $product_id ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $column is a sanitized alias plus a literal column name.
			}
			return ' AND ' . $column . ' = 0';
		}

		// ---- Events and caches ----.

		/**
		 * Tell listeners a product changed ( wpeasycart_product_updated ), once per product per request.
		 *
		 * @param int         $product_id   Product.
		 * @param string|null $model_number Its SKU ( read when not given ).
		 * @return bool Fired now.
		 */
		public static function updated( $product_id, $model_number = null ) {
			global $wpdb;
			$product_id = (int) $product_id;
			if ( $product_id <= 0 || isset( self::$updated[ $product_id ] ) ) {
				return false;
			}
			if ( null === $model_number ) {
				$model_number = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT model_number FROM ec_product WHERE product_id = %d', $product_id ) );
			}
			do_action( 'wpeasycart_product_updated', $product_id, $model_number );
			return true;
		}

		/**
		 * Action wpeasycart_product_updated ( priority 1 ): remember it went out, however it was fired.
		 *
		 * @param int $product_id Product.
		 */
		public static function note_updated( $product_id ) {
			self::$updated[ (int) $product_id ] = true;
		}

		/**
		 * Drop what the store cached about a product. With an external object cache the product lists are cached too,
		 * so one full flush runs at the end of the request.
		 *
		 * @param int    $product_id   Product.
		 * @param string $model_number SKU it had before ( when it changed ).
		 */
		public static function flush_cache( $product_id, $model_number = '' ) {
			global $wpdb;
			$product_id = (int) $product_id;
			$row        = $wpdb->get_row( $wpdb->prepare( 'SELECT model_number, post_id, option_id_1, option_id_2, option_id_3, option_id_4, option_id_5 FROM ec_product WHERE product_id = %d', $product_id ) );
			$models     = array_filter( array_unique( array( (string) $model_number, $row ? (string) $row->model_number : '' ) ) );
			foreach ( $models as $model ) {
				wp_cache_delete( 'wpeasycart-product-seo-' . $model, 'wpeasycart-product-seo' );
			}
			/* Product rows are cached per viewer inside ec_db::get_product_list(): a new generation replaces them all. */
			if ( method_exists( 'ec_db', 'product_cache_changed' ) ) {
				ec_db::product_cache_changed();
			}
			if ( $row && (int) $row->post_id ) {
				if ( function_exists( 'clean_post_cache' ) ) {
					clean_post_cache( (int) $row->post_id );
				}
			}
			wp_cache_delete( 'wpeasycart-product-categories-' . $product_id, 'wpeasycart-categories' );
			wp_cache_delete( 'wpeascyart-optionitem-images-' . $product_id, 'wpeasycart-optionitems' );
			wp_cache_delete( 'wpeasycart-get-advanced-option-sets-' . $product_id, 'wpeasycart-advanced-options-list' );
			wp_cache_delete( 'wpeasycart-menu-by-product-' . $product_id, 'wpeasycart-menu' );
			wp_cache_delete( 'wpeasycart-all-categories', 'wpeasycart-categories' );
			wp_cache_delete( 'wpeasycart-all-categories' );
			wp_cache_delete( 'wpeasycart-optionset-optionitems-all', 'wpeasycart-options' );
			if ( $row ) {
				for ( $slot = 1; $slot <= 5; $slot++ ) {
					$option_id = (int) $row->{ 'option_id_' . $slot };
					if ( $option_id ) {
						wp_cache_delete( 'wpeasycart-option-' . $option_id, 'wpeasycart-options' );
						wp_cache_delete( 'wpeasycart-optionset-optionitems-' . $option_id, 'wpeasycart-options' );
						wp_cache_delete( 'wpeasycart-optionset-' . $option_id, 'wpeasycart-options' );
						wp_cache_delete( 'wpeasycart-optionitems-' . $option_id, 'wpeasycart-options' );
					}
				}
			}
			if ( class_exists( 'wp_easycart_variants' ) ) {
				wp_easycart_variants::forget( $product_id );
			}
			if ( class_exists( 'wp_easycart_product_lock' ) ) {
				wp_easycart_product_lock::forget( $product_id );
			}
			if ( ! self::$flush_owed && function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() ) {
				self::$flush_owed = true;
				add_action( 'shutdown', 'wp_cache_flush' );
			}
		}

		// ---- Products ----.

		/**
		 * The columns of ec_product.
		 *
		 * @return string[]
		 */
		public static function columns() {
			global $wpdb;
			if ( null === self::$columns ) {
				self::$columns = array();
				foreach ( (array) $wpdb->get_results( 'SHOW COLUMNS FROM ec_product' ) as $column ) {
					self::$columns[] = (string) $column->Field; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- MySQL's column name.
				}
			}
			return self::$columns;
		}

		/**
		 * A model number no other product uses, built from a title.
		 *
		 * @param string $title      Title.
		 * @param int    $product_id Product that may keep it.
		 * @return string
		 */
		public static function unique_model_number( $title, $product_id = 0 ) {
			global $wpdb;
			$title = function_exists( 'remove_accents' ) ? remove_accents( (string) $title ) : (string) $title;
			$base  = strtoupper( trim( preg_replace( '/[^A-Za-z0-9]+/', '-', $title ), '-' ) );
			$base  = substr( '' !== $base ? $base : 'PRODUCT', 0, 40 );
			$try   = $base;
			for ( $i = 2; $i < 1000; $i++ ) {
				if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT product_id FROM ec_product WHERE model_number = %s AND product_id != %d', $try, (int) $product_id ) ) ) {
					return $try;
				}
				$try = $base . '-' . $i;
			}
			return $base . '-' . wp_rand( 100000, 999999 );
		}

		/**
		 * Create or update a product.
		 *
		 * Keys ( only the keys given are written on an update ): product_id ( 0 = new ), title, model_number ( unique; made
		 * from the title when empty on a new product ), price, list_price, product_cost, description, short_description,
		 * weight, length, width, height, is_shippable, is_taxable, activate_in_store, stock_quantity, show_stock_quantity,
		 * use_optionitem_quantity_tracking, manufacturer_id, fulfillment_provider, post_slug, seo_description, and extra
		 * ( column => value, real ec_product columns only ).
		 *
		 * A new product gets its store page the way the importers make it ( wp_easycart_post_sync ) and fires
		 * wpeasycart_product_added and wpeasycart_admin_product_inserted; an update fires wpeasycart_product_updated.
		 *
		 * @param array $data Product data.
		 * @return int|WP_Error Product id.
		 */
		public static function save( $data ) {
			global $wpdb;
			$data       = (array) $data;
			$product_id = isset( $data['product_id'] ) ? (int) $data['product_id'] : 0;
			$is_new     = ( $product_id <= 0 );
			$existing   = null;
			if ( ! $is_new ) {
				$existing = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_product WHERE product_id = %d', $product_id ) );
				if ( ! $existing ) {
					return new WP_Error( 'wp_easycart_product', __( 'Product not found.', 'wp-easycart' ) );
				}
			}
			$columns = self::columns();
			$row     = array();

			$html  = function ( $value ) {
				$value = (string) $value;
				return function_exists( 'wp_easycart_escape_html' ) ? wp_easycart_escape_html( $value ) : wp_kses_post( $value );
			};
			$float = function ( $value ) {
				return (float) preg_replace( '/[^0-9.\-]/', '', (string) $value );
			};
			$flag  = function ( $value ) {
				return empty( $value ) ? 0 : 1;
			};

			if ( array_key_exists( 'title', $data ) ) {
				$row['title'] = $html( wp_strip_all_tags( (string) $data['title'] ) );
				if ( '' === trim( $row['title'] ) ) {
					return new WP_Error( 'wp_easycart_product_title', __( 'A product needs a title.', 'wp-easycart' ) );
				}
			} elseif ( $is_new ) {
				return new WP_Error( 'wp_easycart_product_title', __( 'A product needs a title.', 'wp-easycart' ) );
			}
			foreach ( array( 'description', 'short_description' ) as $key ) {
				if ( array_key_exists( $key, $data ) ) {
					$row[ $key ] = $html( $data[ $key ] );
				}
			}
			foreach ( array( 'price', 'list_price', 'product_cost', 'weight', 'length', 'width', 'height' ) as $key ) {
				if ( array_key_exists( $key, $data ) ) {
					$row[ $key ] = max( 0, $float( $data[ $key ] ) );
				}
			}
			foreach ( array( 'is_shippable', 'is_taxable', 'activate_in_store', 'show_stock_quantity', 'use_optionitem_quantity_tracking' ) as $key ) {
				if ( array_key_exists( $key, $data ) ) {
					$row[ $key ] = $flag( $data[ $key ] );
				}
			}
			foreach ( array( 'stock_quantity', 'manufacturer_id' ) as $key ) {
				if ( array_key_exists( $key, $data ) ) {
					$row[ $key ] = (int) $data[ $key ];
				}
			}
			if ( array_key_exists( 'seo_description', $data ) ) {
				$row['seo_description'] = sanitize_textarea_field( (string) $data['seo_description'] );
			}
			if ( array_key_exists( 'fulfillment_provider', $data ) && in_array( 'fulfillment_provider', $columns, true ) ) {
				$row['fulfillment_provider'] = substr( sanitize_key( (string) $data['fulfillment_provider'] ), 0, 40 );
			}

			/* SKU: unique; made from the title for a new product without one. */
			$old_model = $existing ? (string) $existing->model_number : '';
			if ( array_key_exists( 'model_number', $data ) && '' !== trim( (string) $data['model_number'] ) ) {
				$model = substr( preg_replace( '/[^A-Za-z0-9\-_\/.]/', '', str_replace( ' ', '-', sanitize_text_field( (string) $data['model_number'] ) ) ), 0, 255 );
				if ( '' === $model ) {
					return new WP_Error( 'wp_easycart_model_number', __( 'The SKU can only use letters, numbers, dashes, underscores, dots and slashes.', 'wp-easycart' ) );
				}
				if ( $wpdb->get_var( $wpdb->prepare( 'SELECT product_id FROM ec_product WHERE model_number = %s AND product_id != %d', $model, $product_id ) ) ) {
					/* translators: %s: SKU. */
					return new WP_Error( 'wp_easycart_model_number_taken', sprintf( __( 'Another product already uses the SKU %s.', 'wp-easycart' ), $model ) );
				}
				$row['model_number'] = $model;
			} elseif ( $is_new ) {
				$row['model_number'] = self::unique_model_number( wp_strip_all_tags( (string) $data['title'] ) );
			}

			/* Anything else, as long as it is a real column. */
			if ( ! empty( $data['extra'] ) && is_array( $data['extra'] ) ) {
				foreach ( $data['extra'] as $column => $value ) {
					$column = (string) $column;
					if ( in_array( $column, array( 'product_id', 'post_id' ), true ) || isset( $row[ $column ] ) || ! in_array( $column, $columns, true ) ) {
						continue;
					}
					$row[ $column ] = is_scalar( $value ) || null === $value ? $value : wp_json_encode( $value );
				}
			}

			$slug = array_key_exists( 'post_slug', $data ) ? sanitize_title( (string) $data['post_slug'] ) : '';

			if ( $is_new ) {
				$row = array_merge(
					array(
						'activate_in_store'     => 1,
						'show_on_startup'       => 1,
						'is_shippable'          => 1,
						'is_taxable'            => 1,
						'use_both_option_types' => 1,
						'added_to_db_date'      => current_time( 'mysql' ),
					),
					$row
				);
				$row = array_intersect_key( $row, array_flip( $columns ) );
				if ( false === $wpdb->insert( 'ec_product', $row ) || ! $wpdb->insert_id ) {
					return new WP_Error( 'wp_easycart_product_insert', __( 'The product could not be saved.', 'wp-easycart' ) );
				}
				$product_id = (int) $wpdb->insert_id;
				self::make_page( $product_id, $row, $slug );
				self::flush_cache( $product_id );
				do_action( 'wpeasycart_product_added', $product_id, $row['model_number'] );
				do_action( 'wpeasycart_admin_product_inserted', $product_id, $row['model_number'] );
				return $product_id;
			}

			if ( $row ) {
				$wpdb->update( 'ec_product', $row, array( 'product_id' => $product_id ) );
			}
			if ( isset( $row['title'] ) || isset( $row['model_number'] ) || isset( $row['activate_in_store'] ) || '' !== $slug ) {
				self::update_page( $product_id, $slug );
			}
			self::flush_cache( $product_id, $old_model );
			do_action( 'wpeasycart_product_updated', $product_id, isset( $row['model_number'] ) ? $row['model_number'] : $old_model );
			return $product_id;
		}

		/**
		 * The product's store page, as the importers make it.
		 *
		 * @param int    $product_id Product.
		 * @param array  $row        Its columns.
		 * @param string $slug       Page slug ( '' = from the title ).
		 * @return int Post id.
		 */
		private static function make_page( $product_id, $row, $slug ) {
			if ( ! function_exists( 'wp_easycart_post_sync' ) ) {
				return 0;
			}
			$post = array(
				'post_content'   => '[ec_store modelnumber="' . $row['model_number'] . '"]',
				'post_status'    => ! empty( $row['activate_in_store'] ) ? 'publish' : 'private',
				'post_title'     => function_exists( 'wp_easycart_language' ) ? wp_easycart_language()->convert_text( $row['title'] ) : $row['title'],
				'post_type'      => 'ec_store',
				'comment_status' => 'closed',
			);
			if ( '' !== $slug ) {
				$post['post_name'] = $slug;
			}
			$post_id = (int) wp_easycart_post_sync()->insert( 'product', $product_id, $post );
			if ( $post_id && function_exists( 'wp_set_post_tags' ) ) {
				wp_set_post_tags( $post_id, array( 'product' ), true );
			}
			return $post_id;
		}

		/**
		 * Keep the store page in step with the product's title, SKU, status and slug.
		 *
		 * @param int    $product_id Product.
		 * @param string $slug       New slug ( '' = keep ).
		 */
		private static function update_page( $product_id, $slug ) {
			global $wpdb;
			if ( ! function_exists( 'wp_easycart_post_sync' ) ) {
				return;
			}
			$product = $wpdb->get_row( $wpdb->prepare( 'SELECT product_id, post_id, title, model_number, activate_in_store FROM ec_product WHERE product_id = %d', $product_id ) );
			if ( ! $product ) {
				return;
			}
			$post = array(
				'post_content' => '[ec_store modelnumber="' . $product->model_number . '"]',
				'post_status'  => (int) $product->activate_in_store ? 'publish' : 'private',
				'post_title'   => function_exists( 'wp_easycart_language' ) ? wp_easycart_language()->convert_text( $product->title ) : $product->title,
			);
			if ( '' !== $slug ) {
				$post['post_name'] = $slug;
			}
			if ( (int) $product->post_id ) {
				wp_easycart_post_sync()->update( 'product', $product_id, (int) $product->post_id, $post );
			} else {
				self::make_page( $product_id, (array) $product, $slug );
			}
		}

		// ---- Option sets and variants ----.

		/**
		 * How an item key is stored ( optionitem_initial_value ).
		 *
		 * @param string $key Key.
		 * @return string
		 */
		public static function stored_key( $key ) {
			$key = trim( (string) $key );
			return ( strlen( $key ) > 255 ) ? 'md5:' . md5( $key ) : $key;
		}

		/**
		 * Save the product's own option sets ( variation sets, up to five ), then rebuild its variants.
		 *
		 * Each set: name, label, type ( basic-combo | basic-swatch ), items => list of array( key, name, swatch ( URL, colour
		 * '#rrggbb' or attachment id ), price ( added to the product price ), weight ( added ), model_number ( SKU suffix ) ).
		 * A set already owned by the product is reused ( the one in the same slot, else the one with the same name ); items
		 * are matched by key, then by name, so their ids ( and the variant rows built on them ) are kept. Sets and items no
		 * longer given are deleted. The product's option_id_1..5 point at the sets, its modifiers are left as they are and
		 * use_advanced_optionset is switched off.
		 *
		 * @param int   $product_id Product.
		 * @param array $sets       Option sets, slot 1 first.
		 * @return array|WP_Error array( 'option_ids' => slot => option_id, 'items' => slot => array( key => optionitem_id ) ).
		 */
		public static function save_options( $product_id, $sets ) {
			global $wpdb;
			$product_id = (int) $product_id;
			$product    = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_product WHERE product_id = %d', $product_id ) );
			if ( ! $product ) {
				return new WP_Error( 'wp_easycart_product', __( 'Product not found.', 'wp-easycart' ) );
			}
			if ( ! self::ready() ) {
				return new WP_Error( 'wp_easycart_update_database', __( 'The WP EasyCart database update has not run yet.', 'wp-easycart' ) );
			}

			/* Clean the sets and check the variant count before anything is written. */
			$clean  = array();
			$combos = 1;
			foreach ( array_slice( array_values( (array) $sets ), 0, 5 ) as $set ) {
				$set  = (array) $set;
				$name = isset( $set['name'] ) ? sanitize_text_field( (string) $set['name'] ) : '';
				if ( '' === $name ) {
					return new WP_Error( 'wp_easycart_option_name', __( 'Every option set needs a name.', 'wp-easycart' ) );
				}
				$items = array();
				$seen  = array();
				foreach ( isset( $set['items'] ) ? (array) $set['items'] : array() as $item ) {
					$item      = (array) $item;
					$item_name = isset( $item['name'] ) ? sanitize_text_field( (string) $item['name'] ) : '';
					if ( '' === $item_name ) {
						continue;
					}
					$key = ( isset( $item['key'] ) && '' !== trim( (string) $item['key'] ) ) ? (string) $item['key'] : $item_name;
					if ( isset( $seen[ $key ] ) ) {
						continue;
					}
					$seen[ $key ] = true;
					$items[]      = array(
						'key'          => $key,
						'stored_key'   => self::stored_key( $key ),
						'name'         => $item_name,
						'swatch'       => array_key_exists( 'swatch', $item ) ? (string) $item['swatch'] : null, /* null: keep the stored one */
						'price'        => isset( $item['price'] ) ? (float) $item['price'] : 0,
						'weight'       => isset( $item['weight'] ) ? (float) $item['weight'] : 0,
						'model_number' => isset( $item['model_number'] ) ? preg_replace( '/[^A-Za-z0-9\-_]/', '', (string) $item['model_number'] ) : '',
					);
				}
				if ( empty( $items ) ) {
					/* translators: %s: option set name. */
					return new WP_Error( 'wp_easycart_option_items', sprintf( __( 'The option set %s has no choices.', 'wp-easycart' ), $name ) );
				}
				$type    = ( isset( $set['type'] ) && 'basic-swatch' === $set['type'] ) ? 'basic-swatch' : 'basic-combo';
				$clean[] = array(
					'name'  => $name,
					'label' => ( isset( $set['label'] ) && '' !== trim( (string) $set['label'] ) ) ? sanitize_text_field( (string) $set['label'] ) : $name,
					'type'  => $type,
					'items' => $items,
				);
				$combos *= count( $items );
			}
			if ( class_exists( 'wp_easycart_variants' ) && $clean && $combos > wp_easycart_variants::limit() ) {
				return new WP_Error(
					'wp_easycart_variant_limit',
					/* translators: 1: variations the option sets would create, 2: the limit. */
					sprintf( __( 'These option sets would create %1$s variations, more than the %2$s this store allows. Remove an option set or reduce the items in one of them.', 'wp-easycart' ), $combos, wp_easycart_variants::limit() )
				);
			}

			/* The product's own sets, and the ones in its slots now. */
			$owned = array();
			foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT option_id, option_name FROM ec_option WHERE owner_product_id = %d ORDER BY option_id ASC', $product_id ) ) as $option ) {
				$owned[ (int) $option->option_id ] = (string) $option->option_name;
			}
			$used       = array();
			$option_ids = array(
				1 => 0,
				2 => 0,
				3 => 0,
				4 => 0,
				5 => 0,
			);
			$item_map   = array();
			foreach ( $clean as $index => $set ) {
				$slot      = $index + 1;
				$option_id = 0;
				// The owned set with this name ( so reordered sets keep their items ), else the owned set in this slot ( a
				// renamed set ), else a new one.
				foreach ( $owned as $owned_id => $owned_name ) {
					if ( ! isset( $used[ $owned_id ] ) && 0 === strcasecmp( $owned_name, $set['name'] ) ) {
						$option_id = $owned_id;
						break;
					}
				}
				$in_slot = (int) $product->{ 'option_id_' . $slot };
				if ( ! $option_id && $in_slot && isset( $owned[ $in_slot ] ) && ! isset( $used[ $in_slot ] ) ) {
					$named_later = false;
					foreach ( $clean as $later ) {
						if ( 0 === strcasecmp( $owned[ $in_slot ], $later['name'] ) ) {
							$named_later = true; /* another incoming set is that one, by name */
							break;
						}
					}
					if ( ! $named_later ) {
						$option_id = $in_slot;
					}
				}
				$columns = array(
					'option_name'      => substr( $set['name'], 0, 128 ),
					'option_label'     => $set['label'],
					'option_type'      => $set['type'],
					'option_required'  => 1,
					'owner_product_id' => $product_id,
				);
				if ( $option_id ) {
					$wpdb->update( 'ec_option', $columns, array( 'option_id' => $option_id ) );
				} else {
					$wpdb->insert( 'ec_option', $columns );
					$option_id = (int) $wpdb->insert_id;
					if ( ! $option_id ) {
						return new WP_Error( 'wp_easycart_option_insert', __( 'The option set could not be saved.', 'wp-easycart' ) );
					}
				}
				$used[ $option_id ]  = true;
				$option_ids[ $slot ] = $option_id;
				$item_map[ $slot ]   = self::save_items( $product_id, $option_id, $set['items'] );
			}

			/* Owned sets no longer in a slot go, with their items. */
			$gone = array_diff( array_keys( $owned ), array_keys( $used ) );
			if ( $gone ) {
				self::delete_sets( $gone );
			}

			$wpdb->query( $wpdb->prepare( 'UPDATE ec_product SET option_id_1 = %d, option_id_2 = %d, option_id_3 = %d, option_id_4 = %d, option_id_5 = %d, use_advanced_optionset = 0 WHERE product_id = %d', $option_ids[1], $option_ids[2], $option_ids[3], $option_ids[4], $option_ids[5], $product_id ) );
			if ( class_exists( 'wp_easycart_variants' ) ) {
				/* New combinations start with no stock ( save_variants() writes what the partner reports ). */
				$rebuilt = wp_easycart_variants::rebuild( $product_id, $option_ids, array( 'inherit_stock' => false ) );
				if ( is_wp_error( $rebuilt ) ) {
					return $rebuilt;
				}
			}
			foreach ( $option_ids as $option_id ) {
				if ( $option_id ) {
					do_action( 'wp_easycart_optionset_updated', $option_id );
				}
			}
			self::flush_cache( $product_id );
			return array(
				'option_ids' => $option_ids,
				'items'      => $item_map,
			);
		}

		/**
		 * Save one owned set's items: matched by key ( optionitem_initial_value ), then by name; the rest deleted.
		 *
		 * @param int   $product_id Product ( for its per-option images ).
		 * @param int   $option_id  Option set.
		 * @param array $items      Clean items.
		 * @return array key => optionitem_id
		 */
		private static function save_items( $product_id, $option_id, $items ) {
			global $wpdb;
			$stored = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT optionitem_id, optionitem_name, optionitem_initial_value FROM ec_optionitem WHERE option_id = %d ORDER BY optionitem_order ASC, optionitem_id ASC', $option_id ) );
			$taken  = array();
			$map    = array();
			$order  = 1;
			// Every key match first, then names for the items still unmatched, so a name never takes a row another item's
			// key points at.
			$match = array();
			foreach ( $items as $index => $item ) {
				foreach ( $stored as $row ) {
					if ( ! isset( $taken[ (int) $row->optionitem_id ] ) && '' !== (string) $row->optionitem_initial_value && (string) $row->optionitem_initial_value === $item['stored_key'] ) {
						$match[ $index ]                    = (int) $row->optionitem_id;
						$taken[ (int) $row->optionitem_id ] = true;
						break;
					}
				}
			}
			foreach ( $items as $index => $item ) {
				if ( isset( $match[ $index ] ) ) {
					continue;
				}
				foreach ( $stored as $row ) {
					if ( ! isset( $taken[ (int) $row->optionitem_id ] ) && 0 === strcasecmp( trim( (string) $row->optionitem_name ), $item['name'] ) ) {
						$match[ $index ]                    = (int) $row->optionitem_id;
						$taken[ (int) $row->optionitem_id ] = true;
						break;
					}
				}
			}
			foreach ( $items as $index => $item ) {
				$item_id = isset( $match[ $index ] ) ? $match[ $index ] : 0;
				$columns = array(
					'option_id'                => $option_id,
					'optionitem_name'          => $item['name'],
					'optionitem_price'         => $item['price'],
					'optionitem_weight'        => $item['weight'],
					'optionitem_order'         => $order++,
					'optionitem_model_number'  => $item['model_number'],
					'optionitem_initial_value' => $item['stored_key'],
				);
				if ( null !== $item['swatch'] ) {
					$columns['optionitem_icon'] = self::swatch( $item['swatch'], $product_id );
				}
				if ( $item_id ) {
					$wpdb->update( 'ec_optionitem', $columns, array( 'optionitem_id' => $item_id ) );
				} else {
					$columns = array_merge(
						array(
							'optionitem_price_onetime'   => 0,
							'optionitem_price_override'  => -1,
							'optionitem_weight_onetime'  => 0,
							'optionitem_weight_override' => -1,
							'optionitem_allow_download'  => 1,
						),
						$columns
					);
					$wpdb->insert( 'ec_optionitem', $columns );
					$item_id = (int) $wpdb->insert_id;
				}
				$taken[ $item_id ]   = true;
				$map[ $item['key'] ] = $item_id;
				wp_cache_delete( 'wpeasycart-optionitem-' . $item_id, 'wpeasycart-optionitems' );
			}
			$gone = array();
			foreach ( $stored as $row ) {
				if ( ! isset( $taken[ (int) $row->optionitem_id ] ) ) {
					$gone[] = (int) $row->optionitem_id;
				}
			}
			if ( $gone ) {
				$in = implode( ',', $gone );
				// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $in is an implode of (int) ids.
				$wpdb->query( "DELETE FROM ec_optionitemimage WHERE optionitem_id IN ( $in )" );
				$wpdb->query( "DELETE FROM ec_optionitem WHERE optionitem_id IN ( $in )" );
				// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
			wp_cache_delete( 'wpeasycart-optionset-optionitems-' . $option_id, 'wpeasycart-options' );
			wp_cache_delete( 'wpeasycart-option-' . $option_id, 'wpeasycart-options' );
			return $map;
		}

		/**
		 * A swatch for optionitem_icon: a colour ( '#rrggbb' ) as it is, an attachment id or a URL as a media library URL.
		 *
		 * @param mixed $swatch     Swatch.
		 * @param int   $product_id Product ( the attachment's parent ).
		 * @return string
		 */
		private static function swatch( $swatch, $product_id ) {
			$swatch = trim( (string) $swatch );
			if ( '' === $swatch ) {
				return '';
			}
			if ( preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})(\s*,\s*#([0-9a-f]{3}|[0-9a-f]{6}))?$/i', $swatch ) ) {
				return strtolower( preg_replace( '/\s+/', '', $swatch ) );
			}
			$attachment_id = self::attachment( $swatch, $product_id );
			if ( ! is_wp_error( $attachment_id ) && $attachment_id ) {
				$url = wp_get_attachment_url( $attachment_id );
				if ( $url ) {
					return esc_url_raw( $url );
				}
			}
			return ( 0 === strpos( $swatch, 'http' ) ) ? esc_url_raw( $swatch ) : '';
		}

		/**
		 * Delete option sets with their items and the per-option images on them.
		 *
		 * @param int[] $option_ids Option sets.
		 */
		private static function delete_sets( $option_ids ) {
			global $wpdb;
			$option_ids = array_values( array_filter( array_map( 'intval', (array) $option_ids ) ) );
			if ( ! $option_ids ) {
				return;
			}
			$in    = implode( ',', $option_ids );
			$items = array_map( 'intval', (array) $wpdb->get_col( "SELECT optionitem_id FROM ec_optionitem WHERE option_id IN ( $in )" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $in is an implode of (int) ids.
			if ( $items ) {
				$wpdb->query( 'DELETE FROM ec_optionitemimage WHERE optionitem_id IN ( ' . implode( ',', $items ) . ' )' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- (int) ids.
			}
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $in is an implode of (int) ids.
			$wpdb->query( "DELETE FROM ec_optionitem WHERE option_id IN ( $in )" );
			$wpdb->query( "DELETE FROM ec_option_to_product WHERE option_id IN ( $in )" );
			$wpdb->query( "DELETE FROM ec_option WHERE option_id IN ( $in )" );
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			foreach ( $option_ids as $option_id ) {
				wp_cache_delete( 'wpeasycart-option-' . $option_id, 'wpeasycart-options' );
				wp_cache_delete( 'wpeasycart-optionset-optionitems-' . $option_id, 'wpeasycart-options' );
			}
		}

		/**
		 * Save variant settings. Each variant: items ( option item ids by slot ), sku, price ( -1 = the product's price ),
		 * enabled, quantity, tracking ( stock tracked for it ), weight, cost ( null = not set ), image ( attachment id, URL
		 * or 'image:<url>' ). Only the keys given are written. A variant whose row is missing ( its combination is valid for
		 * the product's sets ) is added.
		 *
		 * @param int   $product_id Product.
		 * @param array $variants   Variants.
		 * @param array $args       rollup ( default true: the product's stock follows its variants when it tracks stock per
		 *                          variant ).
		 * @return array key => optionitemquantity_id
		 */
		public static function save_variants( $product_id, $variants, $args = array() ) {
			global $wpdb;
			$product_id = (int) $product_id;
			$args       = array_merge( array( 'rollup' => true ), (array) $args );
			$ready      = class_exists( 'wp_easycart_variants' ) && wp_easycart_variants::ready();
			$out        = array();
			if ( $product_id <= 0 || ! class_exists( 'wp_easycart_variants' ) ) {
				return $out;
			}
			foreach ( (array) $variants as $variant ) {
				$variant = (array) $variant;
				$slots   = wp_easycart_variants::slots( isset( $variant['items'] ) ? $variant['items'] : array() );
				if ( ! array_filter( $slots ) ) {
					continue;
				}
				$key = implode( '-', $slots );
				$row = wp_easycart_variants::find( $product_id, $slots );
				if ( ! $row ) {
					/* Only a combination the product's option sets make ( the rows rebuild() keeps ). */
					if ( ! wp_easycart_variants::allows( $product_id, $slots ) ) {
						continue;
					}
					$wpdb->insert(
						'ec_optionitemquantity',
						array(
							'product_id'      => $product_id,
							'optionitem_id_1' => $slots[1],
							'optionitem_id_2' => $slots[2],
							'optionitem_id_3' => $slots[3],
							'optionitem_id_4' => $slots[4],
							'optionitem_id_5' => $slots[5],
						)
					);
					$row_id = (int) $wpdb->insert_id;
					wp_easycart_variants::forget( $product_id ); /* the same items again in this call find this row */
				} else {
					$row_id = (int) $row->optionitemquantity_id;
				}
				if ( ! $row_id ) {
					continue;
				}
				$columns = array();
				if ( array_key_exists( 'sku', $variant ) ) {
					$columns['sku'] = substr( sanitize_text_field( (string) $variant['sku'] ), 0, 255 );
				}
				if ( array_key_exists( 'price', $variant ) ) {
					$price            = ( null === $variant['price'] || '' === $variant['price'] ) ? -1 : (float) $variant['price'];
					$columns['price'] = ( $price < 0 ) ? -1 : $price;
				}
				if ( array_key_exists( 'enabled', $variant ) ) {
					$columns['is_enabled'] = empty( $variant['enabled'] ) ? 0 : 1;
				}
				if ( array_key_exists( 'quantity', $variant ) ) {
					$columns['quantity'] = (int) $variant['quantity'];
				}
				if ( array_key_exists( 'tracking', $variant ) ) {
					$columns['is_stock_tracking_enabled'] = empty( $variant['tracking'] ) ? 0 : 1;
				}
				if ( $ready ) {
					foreach ( array( 'weight', 'cost' ) as $column ) {
						if ( array_key_exists( $column, $variant ) ) {
							$columns[ $column ] = ( null === $variant[ $column ] || '' === $variant[ $column ] ) ? null : (float) $variant[ $column ];
						}
					}
					if ( array_key_exists( 'image', $variant ) ) {
						$columns['image'] = self::variant_image( $variant['image'], $product_id );
					}
				}
				if ( $columns ) {
					$wpdb->update( 'ec_optionitemquantity', $columns, array( 'optionitemquantity_id' => $row_id ) );
				}
				$out[ $key ] = $row_id;
			}
			wp_easycart_variants::forget( $product_id );
			if ( $args['rollup'] ) {
				self::rollup_stock( $product_id );
			}
			self::flush_cache( $product_id );
			return $out;
		}

		/**
		 * A variant image for ec_optionitemquantity.image: an attachment id, or 'image:<url>' when it could not be copied.
		 *
		 * @param mixed $image      Attachment id, URL or 'image:<url>'.
		 * @param int   $product_id Product.
		 * @return string
		 */
		private static function variant_image( $image, $product_id ) {
			$image = trim( (string) $image );
			if ( '' === $image ) {
				return '';
			}
			if ( 0 === strpos( $image, 'image:' ) ) {
				$image = substr( $image, 6 );
			}
			$attachment_id = self::attachment( $image, $product_id );
			if ( ! is_wp_error( $attachment_id ) && $attachment_id ) {
				return (string) (int) $attachment_id;
			}
			return ( 0 === strpos( $image, 'http' ) ) ? substr( 'image:' . esc_url_raw( $image ), 0, 512 ) : '';
		}

		/**
		 * A product that tracks stock per variant: its stock is the sum of its enabled, tracked variants ( an untracked
		 * enabled variant counts as 1, as WP EasyCart PRO's variant manager counts ).
		 *
		 * @param int $product_id Product.
		 */
		public static function rollup_stock( $product_id ) {
			if ( class_exists( 'wp_easycart_variants' ) ) {
				wp_easycart_variants::rollup_stock( (int) $product_id ); /* one rule, shared with rebuild() */
			}
		}

		// ---- Images ----.

		/**
		 * The attachment for an image: an attachment id as it is; a URL copied into the media library once ( attachment
		 * meta _wp_easycart_source_url remembers where it came from ). Works outside wp-admin ( cron ).
		 *
		 * @param mixed $image      Attachment id or URL.
		 * @param int   $product_id Product ( the new attachment's parent is its page ).
		 * @return int|WP_Error
		 */
		public static function attachment( $image, $product_id = 0 ) {
			global $wpdb;
			if ( is_numeric( $image ) ) {
				$attachment_id = (int) $image;
				return ( $attachment_id > 0 && 'attachment' === get_post_type( $attachment_id ) ) ? $attachment_id : new WP_Error( 'wp_easycart_image', __( 'That image is not in the media library.', 'wp-easycart' ) );
			}
			$url = esc_url_raw( trim( (string) $image ) );
			if ( '' === $url || ! preg_match( '#^https?://#i', $url ) ) {
				return new WP_Error( 'wp_easycart_image', __( 'An image must be a media library id or a web address.', 'wp-easycart' ) );
			}
			$known = (int) $wpdb->get_var( $wpdb->prepare( "SELECT pm.post_id FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_wp_easycart_source_url' AND pm.meta_value = %s AND p.post_type = 'attachment' ORDER BY pm.post_id ASC LIMIT 1", $url ) );
			if ( $known ) {
				return $known;
			}
			if ( ! function_exists( 'download_url' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			if ( ! function_exists( 'media_handle_sideload' ) ) {
				require_once ABSPATH . 'wp-admin/includes/media.php';
			}
			if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
				require_once ABSPATH . 'wp-admin/includes/image.php';
			}
			$tmp = download_url( $url, 60 );
			if ( is_wp_error( $tmp ) ) {
				return $tmp;
			}
			$path = (string) wp_parse_url( $url, PHP_URL_PATH );
			$name = sanitize_file_name( wp_basename( $path ) );
			$type = wp_check_filetype( $name );
			if ( empty( $type['ext'] ) ) {
				$mime = function_exists( 'wp_get_image_mime' ) ? wp_get_image_mime( $tmp ) : '';
				$exts = array(
					'image/jpeg' => 'jpg',
					'image/png'  => 'png',
					'image/gif'  => 'gif',
					'image/webp' => 'webp',
					'image/avif' => 'avif',
				);
				$name = ( '' !== $name ? preg_replace( '/\.[^.]*$/', '', $name ) : 'image-' . substr( md5( $url ), 0, 8 ) ) . '.' . ( isset( $exts[ $mime ] ) ? $exts[ $mime ] : 'jpg' );
			}
			$post_id       = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT post_id FROM ec_product WHERE product_id = %d', (int) $product_id ) );
			$attachment_id = media_handle_sideload(
				array(
					'name'     => $name,
					'tmp_name' => $tmp,
				),
				$post_id
			);
			if ( is_wp_error( $attachment_id ) ) {
				if ( file_exists( $tmp ) ) {
					wp_delete_file( $tmp );
				}
				return $attachment_id;
			}
			update_post_meta( (int) $attachment_id, '_wp_easycart_source_url', $url );
			return (int) $attachment_id;
		}

		/**
		 * Set the product's images ( the gallery in product_images ) and, optionally, images per option item of slot 1
		 * ( ec_optionitemimage; use_optionitem_images follows ).
		 *
		 * @param int        $product_id Product.
		 * @param array      $images     Attachment ids or URLs, the listing image first.
		 * @param array|null $by_item    optionitem_id => images: the product's per-option images are replaced with these and
		 *                               use_optionitem_images follows ( an empty array removes them ). null ( the default )
		 *                               leaves the per-option images and use_optionitem_images as they are.
		 * @return int[]|WP_Error Attachment ids of the gallery. WP_Error when no image could be used.
		 */
		public static function set_images( $product_id, $images, $by_item = null ) {
			global $wpdb;
			$product_id = (int) $product_id;
			$product    = $wpdb->get_row( $wpdb->prepare( 'SELECT product_id, post_id, option_id_1 FROM ec_product WHERE product_id = %d', $product_id ) );
			if ( ! $product ) {
				return new WP_Error( 'wp_easycart_product', __( 'Product not found.', 'wp-easycart' ) );
			}
			$ids    = array();
			$errors = array();
			foreach ( (array) $images as $image ) {
				$attachment_id = self::attachment( $image, $product_id );
				if ( is_wp_error( $attachment_id ) ) {
					$errors[] = $attachment_id;
					continue;
				}
				if ( ! in_array( $attachment_id, $ids, true ) ) {
					$ids[] = $attachment_id;
				}
			}
			if ( ! $ids && $errors ) {
				return $errors[0];
			}

			$per_item = array();
			$slot_one = (int) $product->option_id_1 ? array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT optionitem_id FROM ec_optionitem WHERE option_id = %d', (int) $product->option_id_1 ) ) ) : array();
			foreach ( (array) $by_item as $optionitem_id => $item_images ) {
				$optionitem_id = (int) $optionitem_id;
				if ( ! in_array( $optionitem_id, $slot_one, true ) ) {
					continue;
				}
				$item_ids = array();
				foreach ( (array) $item_images as $image ) {
					$attachment_id = self::attachment( $image, $product_id );
					if ( ! is_wp_error( $attachment_id ) && ! in_array( $attachment_id, $item_ids, true ) ) {
						$item_ids[] = $attachment_id;
					}
				}
				if ( $item_ids ) {
					$per_item[ $optionitem_id ] = $item_ids;
				}
			}

			if ( null === $by_item ) {
				$wpdb->query( $wpdb->prepare( 'UPDATE ec_product SET product_images = %s WHERE product_id = %d', implode( ',', $ids ), $product_id ) );
			} else {
				$wpdb->query( $wpdb->prepare( 'UPDATE ec_product SET product_images = %s, use_optionitem_images = %d WHERE product_id = %d', implode( ',', $ids ), $per_item ? 1 : 0, $product_id ) );
				$wpdb->query( $wpdb->prepare( 'DELETE FROM ec_optionitemimage WHERE product_id = %d', $product_id ) );
				foreach ( $per_item as $optionitem_id => $item_ids ) {
					$wpdb->insert(
						'ec_optionitemimage',
						array(
							'optionitem_id'  => $optionitem_id,
							'product_id'     => $product_id,
							'product_images' => implode( ',', $item_ids ),
							'image1'         => '',
							'image2'         => '',
							'image3'         => '',
							'image4'         => '',
							'image5'         => '',
						)
					);
					wp_cache_delete( 'wpeasycart-optionitem-image1-' . $product_id . '-' . $optionitem_id, 'wpeasycart-optionitems' );
				}
			}
			/* The page's featured image, when it has none ( SEO plugins and themes read it ). */
			if ( $ids && (int) $product->post_id && function_exists( 'has_post_thumbnail' ) && ! has_post_thumbnail( (int) $product->post_id ) ) {
				set_post_thumbnail( (int) $product->post_id, $ids[0] );
			}
			self::flush_cache( $product_id );
			return $ids;
		}

		// ---- Categories ----.

		/**
		 * Put the product in exactly these categories.
		 *
		 * @param int   $product_id   Product.
		 * @param int[] $category_ids Categories.
		 * @return int[] The categories it is in.
		 */
		public static function set_categories( $product_id, $category_ids ) {
			global $wpdb;
			$product_id   = (int) $product_id;
			$category_ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $category_ids ) ) ) );
			if ( $category_ids ) {
				$category_ids = array_map( 'intval', (array) $wpdb->get_col( 'SELECT category_id FROM ec_category WHERE category_id IN ( ' . implode( ',', $category_ids ) . ' )' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- (int) ids.
			}
			$current = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT category_id FROM ec_categoryitem WHERE product_id = %d', $product_id ) ) );
			$remove  = array_diff( $current, $category_ids );
			$add     = array_diff( $category_ids, $current );
			foreach ( $remove as $category_id ) {
				$wpdb->query( $wpdb->prepare( 'DELETE FROM ec_categoryitem WHERE product_id = %d AND category_id = %d', $product_id, $category_id ) );
			}
			foreach ( $add as $category_id ) {
				$wpdb->insert(
					'ec_categoryitem',
					array(
						'product_id'  => $product_id,
						'category_id' => $category_id,
					)
				);
			}
			if ( ( $remove || $add ) && function_exists( 'wp_set_post_tags' ) ) {
				$post_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT post_id FROM ec_product WHERE product_id = %d', $product_id ) );
				if ( $post_id ) {
					$names = $category_ids ? (array) $wpdb->get_col( 'SELECT category_name FROM ec_category WHERE category_id IN ( ' . implode( ',', $category_ids ) . ' )' ) : array(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- (int) ids.
					$gone  = $remove ? (array) $wpdb->get_col( 'SELECT category_name FROM ec_category WHERE category_id IN ( ' . implode( ',', array_map( 'intval', $remove ) ) . ' )' ) : array(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- (int) ids.
					$tags  = array( 'product' );
					foreach ( (array) wp_get_post_tags( $post_id ) as $tag ) {
						if ( ! in_array( $tag->name, $gone, true ) ) {
							$tags[] = $tag->name;
						}
					}
					wp_set_post_tags( $post_id, array_values( array_unique( array_merge( $tags, $names ) ) ), false );
				}
			}
			self::flush_cache( $product_id );
			return $category_ids;
		}

		/**
		 * A category by name under a parent: found ( case-insensitive ), else made with its store page.
		 *
		 * @param string $name      Name.
		 * @param int    $parent_id Parent category ( 0 = top level ).
		 * @return int|WP_Error Category id.
		 */
		public static function category( $name, $parent_id = 0 ) {
			global $wpdb;
			$name = sanitize_text_field( (string) $name );
			if ( '' === $name ) {
				return new WP_Error( 'wp_easycart_category', __( 'A category needs a name.', 'wp-easycart' ) );
			}
			$found = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT category_id FROM ec_category WHERE LOWER( category_name ) = %s AND parent_id = %d ORDER BY category_id ASC LIMIT 1', strtolower( $name ), (int) $parent_id ) );
			if ( $found ) {
				return $found;
			}
			$wpdb->insert(
				'ec_category',
				array(
					'category_name' => $name,
					'parent_id'     => (int) $parent_id,
				)
			);
			$category_id = (int) $wpdb->insert_id;
			if ( ! $category_id ) {
				return new WP_Error( 'wp_easycart_category', __( 'The category could not be saved.', 'wp-easycart' ) );
			}
			if ( function_exists( 'wp_easycart_post_sync' ) ) {
				wp_easycart_post_sync()->insert(
					'category',
					$category_id,
					array(
						'post_title'     => $name,
						'post_name'      => sanitize_title( $name ),
						'post_content'   => '[ec_store groupid="' . $category_id . '"]',
						'post_status'    => 'publish',
						'post_type'      => 'ec_store',
						'comment_status' => 'closed',
					)
				);
			}
			wp_cache_delete( 'wpeasycart-all-categories', 'wpeasycart-categories' );
			wp_cache_delete( 'wpeasycart-all-categories' );
			wp_cache_delete( 'wpeasycart-category-list', 'wpeasycart-categories' );
			wp_cache_delete( 'wpeasycart-category-list-' . (int) $parent_id, 'wpeasycart-categories' );
			do_action( 'wpeasycart_category_added', $category_id );
			return $category_id;
		}

		// ---- Owned option sets: delete, duplicate, undo ----.

		/**
		 * Action wpeasycart_product_deleted: the product's own option sets go with it.
		 *
		 * @param int $product_id Product.
		 */
		public static function delete_owned_sets( $product_id ) {
			global $wpdb;
			if ( ! self::ready() || (int) $product_id <= 0 ) {
				return;
			}
			self::delete_sets( (array) $wpdb->get_col( $wpdb->prepare( 'SELECT option_id FROM ec_option WHERE owner_product_id = %d', (int) $product_id ) ) );
		}

		/**
		 * Action wpeasycart_product_duplicated: the copy gets its own copies of the original's own option sets ( its slots,
		 * variant rows, per-option images and modifiers point at them ). The copy is the store's own product: it is not
		 * linked to the original's fulfillment partner, so its fulfillment_provider is cleared.
		 *
		 * @param int $new_id Copy.
		 * @param int $old_id Original.
		 */
		public static function clone_owned_sets( $new_id, $old_id ) {
			global $wpdb;
			$new_id = (int) $new_id;
			$old_id = (int) $old_id;
			if ( ! self::ready() || $new_id <= 0 || $old_id <= 0 ) {
				return;
			}
			$wpdb->query( $wpdb->prepare( "UPDATE ec_product SET fulfillment_provider = '' WHERE product_id = %d", $new_id ) );
			$sets = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ec_option WHERE owner_product_id = %d ORDER BY option_id ASC', $old_id ), ARRAY_A );
			if ( ! $sets ) {
				return;
			}
			$option_map = array();
			$item_map   = array();
			foreach ( $sets as $set ) {
				$old_option = (int) $set['option_id'];
				unset( $set['option_id'] );
				$set['owner_product_id'] = $new_id;
				$set['square_id']        = '';
				$wpdb->insert( 'ec_option', $set );
				$new_option = (int) $wpdb->insert_id;
				if ( ! $new_option ) {
					continue;
				}
				$option_map[ $old_option ] = $new_option;
				foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ec_optionitem WHERE option_id = %d ORDER BY optionitem_id ASC', $old_option ), ARRAY_A ) as $item ) {
					$old_item = (int) $item['optionitem_id'];
					unset( $item['optionitem_id'] );
					$item['option_id']      = $new_option;
					$item['square_id']      = '';
					$item['stripe_plan_id'] = '';
					$wpdb->insert( 'ec_optionitem', $item );
					if ( $wpdb->insert_id ) {
						$item_map[ $old_item ] = (int) $wpdb->insert_id;
					}
				}
			}
			self::remap_product( $new_id, $option_map, $item_map );
		}

		/**
		 * Point a product's slots, variant rows, per-option images and modifiers at other option sets and items.
		 *
		 * @param int   $product_id Product.
		 * @param array $option_map Old option id => new.
		 * @param array $item_map   Old option item id => new.
		 */
		private static function remap_product( $product_id, $option_map, $item_map ) {
			global $wpdb;
			$product = $wpdb->get_row( $wpdb->prepare( 'SELECT option_id_1, option_id_2, option_id_3, option_id_4, option_id_5 FROM ec_product WHERE product_id = %d', $product_id ) );
			if ( $product ) {
				$slots = array();
				for ( $slot = 1; $slot <= 5; $slot++ ) {
					$option_id      = (int) $product->{ 'option_id_' . $slot };
					$slots[ $slot ] = isset( $option_map[ $option_id ] ) ? $option_map[ $option_id ] : $option_id;
				}
				$wpdb->query( $wpdb->prepare( 'UPDATE ec_product SET option_id_1 = %d, option_id_2 = %d, option_id_3 = %d, option_id_4 = %d, option_id_5 = %d WHERE product_id = %d', $slots[1], $slots[2], $slots[3], $slots[4], $slots[5], $product_id ) );
			}
			foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT optionitemquantity_id, optionitem_id_1, optionitem_id_2, optionitem_id_3, optionitem_id_4, optionitem_id_5 FROM ec_optionitemquantity WHERE product_id = %d', $product_id ) ) as $row ) {
				$new     = array();
				$changed = false;
				for ( $slot = 1; $slot <= 5; $slot++ ) {
					$item_id      = (int) $row->{ 'optionitem_id_' . $slot };
					$new[ $slot ] = isset( $item_map[ $item_id ] ) ? $item_map[ $item_id ] : $item_id;
					$changed      = $changed || $new[ $slot ] !== $item_id;
				}
				if ( $changed ) {
					$wpdb->query( $wpdb->prepare( 'UPDATE ec_optionitemquantity SET optionitem_id_1 = %d, optionitem_id_2 = %d, optionitem_id_3 = %d, optionitem_id_4 = %d, optionitem_id_5 = %d WHERE optionitemquantity_id = %d', $new[1], $new[2], $new[3], $new[4], $new[5], (int) $row->optionitemquantity_id ) );
				}
			}
			foreach ( $item_map as $old_item => $new_item ) {
				$wpdb->query( $wpdb->prepare( 'UPDATE ec_optionitemimage SET optionitem_id = %d WHERE product_id = %d AND optionitem_id = %d', $new_item, $product_id, $old_item ) );
			}
			foreach ( $option_map as $old_option => $new_option ) {
				$wpdb->query( $wpdb->prepare( 'UPDATE ec_option_to_product SET option_id = %d WHERE product_id = %d AND option_id = %d', $new_option, $product_id, $old_option ) );
			}
			if ( class_exists( 'wp_easycart_variants' ) ) {
				wp_easycart_variants::forget( $product_id );
			}
		}

		/**
		 * The own option sets of these products, for the product list's Undo ( a deleted product's sets are deleted with it ).
		 *
		 * @param int[] $product_ids Products.
		 * @return array array( 'ec_option' => rows, 'ec_optionitem' => rows ).
		 */
		public static function owned_sets_snapshot( $product_ids ) {
			global $wpdb;
			$out         = array(
				'ec_option'     => array(),
				'ec_optionitem' => array(),
			);
			$product_ids = array_values( array_filter( array_map( 'intval', (array) $product_ids ) ) );
			if ( ! self::ready() || ! $product_ids ) {
				return $out;
			}
			$out['ec_option'] = (array) $wpdb->get_results( 'SELECT * FROM ec_option WHERE owner_product_id IN ( ' . implode( ',', $product_ids ) . ' )', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- (int) ids.
			$option_ids       = array();
			foreach ( $out['ec_option'] as $option ) {
				$option_ids[] = (int) $option['option_id'];
			}
			if ( $option_ids ) {
				$out['ec_optionitem'] = (array) $wpdb->get_results( 'SELECT * FROM ec_optionitem WHERE option_id IN ( ' . implode( ',', $option_ids ) . ' )', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- (int) ids.
			}
			return $out;
		}

		/**
		 * Put back option sets from owned_sets_snapshot() ( with their ids ) for the products that were restored.
		 *
		 * @param array $snapshot    From owned_sets_snapshot().
		 * @param int[] $product_ids Products restored.
		 */
		public static function restore_owned_sets( $snapshot, $product_ids ) {
			global $wpdb;
			if ( ! self::ready() || ! is_array( $snapshot ) || empty( $snapshot['ec_option'] ) ) {
				return;
			}
			$back    = array_flip( array_map( 'intval', (array) $product_ids ) );
			$options = array();
			foreach ( (array) $snapshot['ec_option'] as $option ) {
				if ( isset( $back[ (int) $option['owner_product_id'] ] ) && ! $wpdb->get_var( $wpdb->prepare( 'SELECT option_id FROM ec_option WHERE option_id = %d', (int) $option['option_id'] ) ) ) {
					$wpdb->insert( 'ec_option', $option );
					$options[ (int) $option['option_id'] ] = true;
				}
			}
			foreach ( isset( $snapshot['ec_optionitem'] ) ? (array) $snapshot['ec_optionitem'] : array() as $item ) {
				if ( isset( $options[ (int) $item['option_id'] ] ) ) {
					$wpdb->insert( 'ec_optionitem', $item );
				}
			}
		}
	}

	wp_easycart_product_writer::init();

endif;

require_once __DIR__ . '/class-wp-easycart-product-lock.php';
