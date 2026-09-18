<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_cart_importer' ) ) :

	final class wp_easycart_admin_cart_importer {

		protected static $_instance = null;

		private $wpdb;

		public static function instance( ) {

			if( is_null( self::$_instance ) ) {
				self::$_instance = new self(  );
			}
			return self::$_instance;

		}

		public function __construct( ){
			// Keep reference to wpdb
			global $wpdb;
			$this->wpdb =& $wpdb;
			/* The importer UI is the Integrations declaration ( admin/template/settings/integrations.php ), which
			 * includes cart-importer/oscommerce-import.php itself and drives the engines below over admin-ajax. */
		}

		/* =====================================================================
		   WOOCOMMERCE IMPORT
		   ---------------------------------------------------------------------
		   Batched like the Square importer. woo_import_begin() copies the
		   attribute taxonomies, categories and the "Woo Products" manufacturer
		   and stores the id maps in one non-autoloaded option. woo_import_batch()
		   converts WOO_BATCH products per call; the AJAX action
		   ec_admin_ajax_woo_import loops on { done, next, processed, total }.
		   The last batch wires cross-sells and clears the option.

		   Model numbers: one "WHERE model_number IN (...)" per batch replaces
		   the old per-product SELECT loop; collisions ( repeated SKUs, re-runs )
		   still get the "-N" suffix the old loop produced.

		   @since 6.0.0
		   ===================================================================== */

		const WOO_BATCH = 50;
		const WOO_STATE = 'ec_option_woo_import_state';

		/**
		 * Stage 1: option sets, categories and the manufacturer. Stores the id maps
		 * the product batches need and counts the products to convert.
		 *
		 * @since 6.0.0
		 * @return array { done, next, processed, total }
		 */
		public function woo_import_begin() {
			global $wpdb;
			$prefix = $wpdb->prefix;
			$state  = array(
				'optionsets'      => array(), /* 'pa_<attribute>' => ec_option.option_id */
				'categories'      => array(), /* woo term_id => ec_category.category_id */
				'manufacturer_id' => 0,
				'products'        => array(), /* woo post ID => ec_product.product_id ( for cross-sells ) */
				'crosssale'       => array(), /* ec product_id => [ woo post IDs ] */
				'total'           => 0,
				'processed'       => 0,
				'started'         => time(),
			);

			$optionsets = $wpdb->get_results( 'SELECT * FROM ' . $prefix . 'woocommerce_attribute_taxonomies' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $prefix is $wpdb->prefix; the table name is a literal.

			foreach ( $optionsets as $optionset ) {
				$option_name  = $optionset->attribute_name;
				$option_label = $optionset->attribute_label;
				$option_type  = $optionset->attribute_type;

				if ( 'select' == $option_type ) {
					$option_type = 'combo';
				}

				$optionitems = $wpdb->get_results( $wpdb->prepare( 'SELECT ' . $prefix . 'terms.* FROM ' . $prefix . 'term_taxonomy LEFT JOIN ' . $prefix . 'terms ON (' . $prefix . 'terms.term_id = ' . $prefix . 'term_taxonomy.term_id ) WHERE ' . $prefix . 'term_taxonomy.taxonomy = %s', 'pa_' . $option_name ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $prefix is $wpdb->prefix; table names are literals; the value goes through prepare().

				$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_option( option_name, option_label, option_type, option_required ) VALUES( %s, %s, %s, 0 )', $option_name, $option_label, $option_type ) );
				$option_id = $wpdb->insert_id;
				$state['optionsets'][ 'pa_' . $option_name ] = (int) $option_id;

				$order_num = 0;
				foreach ( $optionitems as $optionitem ) {
					$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_optionitem( option_id, optionitem_name, optionitem_order ) VALUES( %d, %s, %d )', $option_id, $optionitem->name, $order_num ) );
					$order_num++;
				}
			}

			$categories = $wpdb->get_results( 'SELECT ' . $prefix . 'terms.* FROM ' . $prefix . 'term_taxonomy LEFT JOIN ' . $prefix . 'terms ON (' . $prefix . 'terms.term_id = ' . $prefix . 'term_taxonomy.term_id ) WHERE ' . $prefix . 'term_taxonomy.taxonomy = "product_cat"' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $prefix is $wpdb->prefix; table names are literals.

			foreach ( $categories as $category ) {
				$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_category( category_name ) VALUES( %s )', $category->name ) );
				$category_id = $wpdb->insert_id;
				$state['categories'][ (int) $category->term_id ] = (int) $category_id;

				$post = array(
					'post_content' => '[ec_store groupid="' . $category_id . '"]',
					'post_status'  => 'publish',
					'post_title'   => $category->name,
					'post_type'    => 'ec_store',
				);
				wp_easycart_post_sync()->insert( 'category', $category_id, $post );
			}

			$wpdb->query( 'INSERT INTO ec_manufacturer( `name` ) VALUES( "Woo Products" )' );
			$manufacturer_id          = $wpdb->insert_id;
			$state['manufacturer_id'] = (int) $manufacturer_id;

			$post = array(
				'post_content' => '[ec_store manufacturerid="' . $manufacturer_id . '"]',
				'post_status'  => 'publish',
				'post_title'   => 'WOO Products',
				'post_type'    => 'ec_store',
			);
			wp_easycart_post_sync()->insert( 'manufacturer', $manufacturer_id, $post );

			/* Same status rules get_posts() applies in the batches ( no post_status given ), so the total matches what gets imported. */
			$count          = new WP_Query( array( 'post_type' => 'product', 'posts_per_page' => 1, 'fields' => 'ids', 'no_found_rows' => false, 'suppress_filters' => true ) );
			$state['total'] = (int) $count->found_posts;

			update_option( self::WOO_STATE, $state, false );

			return array( 'done' => false, 'next' => 0, 'processed' => 0, 'total' => $state['total'] );
		}

		/**
		 * Stage 2: convert one batch of products starting at $cursor ( post offset,
		 * ordered by ID so paging stays stable while rows are added to ec_product ).
		 *
		 * @since 6.0.0
		 * @param int $cursor Offset into the WooCommerce product list.
		 * @return array { done, next, processed, total, error? }
		 */
		public function woo_import_batch( $cursor ) {
			$state = get_option( self::WOO_STATE );
			if ( ! is_array( $state ) || ! isset( $state['products'] ) ) {
				return array( 'done' => true, 'next' => 0, 'processed' => 0, 'total' => 0, 'error' => __( 'The import has not been started. Click Import WooCommerce data to begin.', 'wp-easycart' ) );
			}
			$cursor = max( 0, (int) $cursor );

			$posts = get_posts( array(
				'posts_per_page' => self::WOO_BATCH,
				'offset'         => $cursor,
				'post_type'      => 'product',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			) );

			if ( empty( $posts ) ) {
				return $this->woo_import_finish( $state );
			}

			$model_numbers = $this->woo_unique_model_numbers( $posts );
			foreach ( $posts as $product ) {
				$this->woo_import_product( $product, $model_numbers[ $product->ID ], $state );
			}

			$state['processed'] += count( $posts );
			update_option( self::WOO_STATE, $state, false );

			if ( count( $posts ) < self::WOO_BATCH ) {
				return $this->woo_import_finish( $state );
			}

			return array(
				'done'      => false,
				'next'      => $cursor + count( $posts ),
				'processed' => (int) $state['processed'],
				'total'     => max( (int) $state['total'], (int) $state['processed'] ),
			);
		}

		/** Stage 3: cross-sells need every product id, so they are wired once all batches are in. */
		private function woo_import_finish( $state ) {
			global $wpdb;
			foreach ( $state['crosssale'] as $product_id => $woo_ids ) {
				$featured = array( 0, 0, 0, 0 );
				$k        = 0;
				foreach ( (array) $woo_ids as $woo_id ) {
					if ( $k >= 4 ) {
						break;
					}
					if ( isset( $state['products'][ (int) $woo_id ] ) ) {
						$featured[ $k ] = (int) $state['products'][ (int) $woo_id ];
						$k++;
					}
				}
				$wpdb->query( $wpdb->prepare( 'UPDATE ec_product SET ec_product.featured_product_id_1 = %d, ec_product.featured_product_id_2 = %d, ec_product.featured_product_id_3 = %d, ec_product.featured_product_id_4 = %d WHERE ec_product.product_id = %d', $featured[0], $featured[1], $featured[2], $featured[3], (int) $product_id ) );
			}

			// Marks the launch checklist "Import your products" item done ( wp_easycart_admin_setup_wizard::get_cart_importer_suggestion() ).
			update_option( 'ec_option_cart_importer_woo_imported', time(), false );
			delete_option( self::WOO_STATE );

			return array( 'done' => true, 'next' => (int) $state['processed'], 'processed' => (int) $state['processed'], 'total' => (int) $state['processed'] );
		}

		/**
		 * Model number per post for one batch: the SKU, or a random 9-digit number
		 * when empty. One IN() query finds the ones already in ec_product; a
		 * collision gets the lowest free "-N" suffix ( LIKE fetch only on collision ).
		 * WooCommerce SKUs can repeat and the import can be re-run; without this a
		 * duplicate SKU silently created a duplicate product.
		 *
		 * @return array post ID => model number
		 */
		private function woo_unique_model_numbers( $posts ) {
			global $wpdb;
			$wanted = array();
			foreach ( $posts as $product ) {
				$sku = trim( (string) get_post_meta( $product->ID, '_sku', true ) );
				$wanted[ $product->ID ] = ( '' === $sku ) ? str_pad( (string) wp_rand( 0, 999999999 ), 9, '0', STR_PAD_LEFT ) : $sku;
			}

			$taken = array();
			$rows  = $wpdb->get_col( $wpdb->prepare( 'SELECT model_number FROM ec_product WHERE model_number IN (' . implode( ',', array_fill( 0, count( $wanted ), '%s' ) ) . ')', array_values( $wanted ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholder list built with array_fill(); every value goes through prepare().
			foreach ( $rows as $mn ) {
				$taken[ strtolower( $mn ) ] = true;
			}

			$out = array();
			foreach ( $wanted as $post_id => $mn ) {
				if ( isset( $taken[ strtolower( $mn ) ] ) ) {
					$like = $wpdb->get_col( $wpdb->prepare( 'SELECT model_number FROM ec_product WHERE model_number LIKE %s', $wpdb->esc_like( $mn ) . '-%' ) );
					foreach ( $like as $l ) {
						$taken[ strtolower( $l ) ] = true;
					}
					$suffix = 1;
					while ( isset( $taken[ strtolower( $mn . '-' . $suffix ) ] ) ) {
						$suffix++;
					}
					$mn = $mn . '-' . $suffix;
				}
				$taken[ strtolower( $mn ) ] = true;
				$out[ $post_id ]            = $mn;
			}
			return $out;
		}

		/** First value of a post meta key, '' when the product never had it ( avoids notices on sparse Woo data ). */
		private function woo_meta( $post_meta, $key ) {
			return ( isset( $post_meta[ $key ][0] ) ) ? $post_meta[ $key ][0] : '';
		}

		/** Convert one WooCommerce product ( same field rules as the original single-request importer ). */
		private function woo_import_product( $product, $model_number, &$state ) {
			global $wpdb;
			$prefix    = $wpdb->prefix;
			$post_meta = get_post_meta( $product->ID );

			$title             = $product->post_title;
			$description       = $product->post_content;
			$short_description = $product->post_excerpt;

			$visibility        = $this->woo_meta( $post_meta, '_visibility' );
			$is_active         = ( 'publish' == $product->post_status ) ? true : false;
			$activate_in_store = ( $is_active && 'visible' == $visibility ) ? true : false;

			$regular_price = $this->woo_meta( $post_meta, '_regular_price' );
			$sale_price    = $this->woo_meta( $post_meta, '_sale_price' );
			$price         = $this->woo_meta( $post_meta, '_price' );
			$list_price    = 0;
			if ( $sale_price != '' ) {
				$price      = $sale_price;
				$list_price = $regular_price;
			}

			$tax_status = $this->woo_meta( $post_meta, '_tax_status' );
			$is_taxable = ( $tax_status == 'taxable' ) ? true : false;

			$manage_stock = $this->woo_meta( $post_meta, '_manage_stock' );
			$stock_status = $this->woo_meta( $post_meta, '_stock_status' );
			$stock        = $this->woo_meta( $post_meta, '_stock' );
			if ( 'yes' == $manage_stock && '' != $stock ) {
				$stock_quantity = $stock;
			} else if ( 'instock' == $stock_status ) {
				$stock_quantity = 9999;
			} else {
				$stock_quantity = 0;
			}
			$show_stock_quantity = ( 'yes' == $manage_stock ) ? true : false;

			$virtual = $this->woo_meta( $post_meta, '_virtual' );
			$weight  = $this->woo_meta( $post_meta, '_weight' );
			if ( '' == $weight || 'yes' == $virtual ) {
				$weight = 0;
			}
			$length = $this->woo_meta( $post_meta, '_length' );
			if ( '' == $length || 'yes' == $virtual ) {
				$length = 0;
			}
			$width = $this->woo_meta( $post_meta, '_width' );
			if ( '' == $width || 'yes' == $virtual ) {
				$width = 0;
			}
			$height = $this->woo_meta( $post_meta, '_height' );
			if ( '' == $height || 'yes' == $virtual ) {
				$height = 0;
			}

			$use_customer_reviews = ( 'open' == $product->comment_status ) ? true : false;
			$reviews              = get_comments( array( 'post_id' => $product->ID ) );

			$downloadable = $this->woo_meta( $post_meta, '_downloadable' ); // no if not downloadable
			$file         = false;
			if ( 'yes' == $downloadable ) {
				$files = maybe_unserialize( $this->woo_meta( $post_meta, '_downloadable_files' ) );
				if ( is_array( $files ) && ! empty( $files ) ) {
					$file = reset( $files );
				}
			}
			if ( is_array( $file ) && ! empty( $file['file'] ) ) {
				$path      = pathinfo( $file['file'] );
				$file_name = $path['filename'] . '_' . rand( 100000, 999999 ) . ( isset( $path['extension'] ) ? '.' . $path['extension'] : '' );
				copy( $file['file'], EC_PLUGIN_DATA_DIRECTORY . '/products/downloads/' . $file_name );

				$is_download                = true;
				$download_file_name         = $file_name;
				$maximum_downloads_allowed  = $this->woo_meta( $post_meta, '_download_limit' );
				$download_timelimit_seconds = (int) $this->woo_meta( $post_meta, '_download_expiry' ) * 24 * 60 * 60;
			} else {
				$is_download                = false;
				$download_file_name         = '';
				$maximum_downloads_allowed  = 0;
				$download_timelimit_seconds = 0;
			}

			$image1 = wp_get_attachment_url( get_post_thumbnail_id( $product->ID ) );
			$image2 = '';
			$image3 = '';
			$image4 = '';
			$image5 = '';

			$gallery_images_string = $this->woo_meta( $post_meta, '_product_image_gallery' );
			$gallery_images_array  = explode( ',', $gallery_images_string );
			if ( '' != $gallery_images_array[0] ) {
				$product_images = array();
				foreach ( $gallery_images_array as $gallery_item ) {
					$product_images[] = wp_get_attachment_url( $gallery_item );
				}

				for ( $i = 0; $i < count( $product_images ) && $i < 5; $i++ ) {
					if ( 0 == $i ) {
						$image1 = $product_images[ $i ];
					} else if ( 1 == $i ) {
						$image2 = $product_images[ $i ];
					} else if ( 2 == $i ) {
						$image3 = $product_images[ $i ];
					} else if ( 3 == $i ) {
						$image4 = $product_images[ $i ];
					} else if ( 4 == $i ) {
						$image5 = $product_images[ $i ];
					}
				}
			}

			$product_attributes = maybe_unserialize( $this->woo_meta( $post_meta, '_product_attributes' ) );
			$product_options    = array();
			if ( is_array( $product_attributes ) ) {
				foreach ( $product_attributes as $key => $value ) {
					if ( isset( $state['optionsets'][ $key ] ) ) {
						$product_options[] = $state['optionsets'][ $key ];
					}
				}
			}

			$product_cats = $wpdb->get_results( $wpdb->prepare( 'SELECT ' . $prefix . 'term_relationships.term_taxonomy_id FROM ' . $prefix . 'term_relationships, ' . $prefix . 'terms, ' . $prefix . 'term_taxonomy WHERE ' . $prefix . 'term_taxonomy.taxonomy = "product_cat" AND ' . $prefix . 'term_taxonomy.term_id = ' . $prefix . 'terms.term_id AND ' . $prefix . 'terms.term_id = ' . $prefix . 'term_relationships.term_taxonomy_id AND ' . $prefix . 'term_relationships.object_id = %d', $product->ID ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $prefix is $wpdb->prefix; table names are literals; the id goes through prepare().

			$product_categories = array();
			foreach ( $product_cats as $value ) {
				if ( isset( $state['categories'][ (int) $value->term_taxonomy_id ] ) ) {
					$product_categories[] = $state['categories'][ (int) $value->term_taxonomy_id ];
				}
			}

			$crosssell_ids   = maybe_unserialize( $this->woo_meta( $post_meta, '_crosssell_ids' ) );
			$show_on_startup = true;
			$is_shippable    = true;
			if ( $is_download || $weight <= 0 ) {
				$is_shippable = false;
			}

			$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_product( model_number, activate_in_store, title, description, price, list_price, stock_quantity, weight, width, height, length, use_customer_reviews, manufacturer_id, download_file_name, image1, image2, image3, image4, image5,  use_advanced_optionset, featured_product_id_1, featured_product_id_2, featured_product_id_3, featured_product_id_4, is_download, is_taxable, is_shippable, show_on_startup, show_stock_quantity, maximum_downloads_allowed, download_timelimit_seconds ) VALUES( %s, %d, %s, %s, %s, %s, %d, %s, %s, %s, %s, %d, %d, %s, %s, %s, %s, %s, %s, 1, 0, 0, 0, 0, %d, %d, %d, %d, %d, %s, %s )', $model_number, $activate_in_store, $title, $description, $price, $list_price, $stock_quantity, $weight, $width, $height, $length, $use_customer_reviews, $state['manufacturer_id'], $download_file_name, $image1, $image2, $image3, $image4, $image5, $is_download, $is_taxable, $is_shippable, $show_on_startup, $show_stock_quantity, $maximum_downloads_allowed, $download_timelimit_seconds ) );
			$product_id = (int) $wpdb->insert_id;
			if ( ! $product_id ) {
				return;
			}
			$state['products'][ (int) $product->ID ] = $product_id;
			if ( is_array( $crosssell_ids ) && ! empty( $crosssell_ids ) ) {
				$state['crosssale'][ $product_id ] = array_map( 'intval', $crosssell_ids );
			}

			$status = ( $activate_in_store ) ? 'publish' : 'private';
			$post   = array(
				'post_content' => '[ec_store modelnumber="' . $model_number . '"]',
				'post_status'  => $status,
				'post_title'   => $title,
				'post_type'    => 'ec_store',
			);
			wp_easycart_post_sync()->insert( 'product', $product_id, $post );

			foreach ( $product_options as $option_id ) {
				$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_option_to_product( option_id, product_id ) VALUES( %d, %d )', $option_id, $product_id ) );
			}

			foreach ( $product_categories as $category_id ) {
				$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_categoryitem( category_id, product_id ) VALUES( %d, %d )', $category_id, $product_id ) );
			}

			foreach ( $reviews as $review ) {
				$approved            = $review->comment_approved;
				$rating              = get_comment_meta( $review->comment_ID, 'rating', true );
				$comment_title       = '';
				$comment_description = $review->comment_content;
				$date_submitted      = $review->comment_date;
				$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_review( product_id, approved, rating, title, description, date_submitted ) VALUES( %d, %d, %d, %s, %s, %s )', $product_id, $approved, $rating, $comment_title, $comment_description, $date_submitted ) );
			}
		}

		public function square_import_modifiers( $cursor, $curr_count ){ // These are our option items
			$square = new ec_square( );
			$response = $square->get_modifiers( $cursor );

			foreach( $response->objects as $object ){
				$square->insert_option_item( $object, (bool) $_POST['sync_modifiers'] );
				$curr_count++;
			}
			if( $response->cursor ){
				echo json_encode( array(
					'has_more'      => true,
					'cursor'        => $response->cursor,
					'curr_count'    => $curr_count
				) );
			}else{
				echo json_encode( array(
					'has_more'      => false,
					'curr_count'    => $curr_count
				) );
			}
		}

		public function square_import_modifier_items( $cursor, $curr_count ){ // Note, these are our options
			$square = new ec_square( );
			$response = $square->get_modifier_items( $cursor );
			$only_new = isset( $_POST['only_new'] ) ? (bool) $_POST['only_new'] : false;

			if ( isset( $response->objects ) ) {
				foreach( $response->objects as $object ){
					 $square->insert_option( $object, (bool) $_POST['sync_modifiers'], $only_new );
					 $curr_count++;
				}
			}
			if ( isset( $response->cursor ) && $response->cursor ) {
				echo json_encode( array(
					'has_more'      => true,
					'cursor'        => $response->cursor,
					'curr_count'    => $curr_count
				) );
			} else {
				echo json_encode( array(
					'has_more'      => false,
					'curr_count'    => $curr_count
				) );
			}
		}

		public function square_import_categories( $cursor, $curr_count ){
			$square = new ec_square( );
			$response = $square->get_catalog( false, 0, $types = array( 'CATEGORY' ) );
			$only_new = isset( $_POST['only_new'] ) ? (bool) $_POST['only_new'] : false;

			if ( isset( $response->objects ) ) {
				foreach( $response->objects as $object ){
					$square->insert_category( $object, true, $only_new );
					$curr_count++;
				}
			}

			echo json_encode( array(
				'has_more'      => false,
				'curr_count'    => $curr_count
			) );
		}
		
		public function square_sync_inventory_items( $cursor, $curr_count ) {
			$square = new ec_square( );
			$response = $square->get_inventory_results( $cursor );
			
			if ( isset( $response->counts ) ) {
				foreach( $response->counts as $object ){
					$this->update_inventory( $object );
					$curr_count++;
				}
			}
			if ( isset( $response->cursor ) && $response->cursor ) {
				echo json_encode( array(
					'has_more'      => true,
					'cursor'        => $response->cursor,
					'curr_count'    => $curr_count
				) );
			} else {
				echo json_encode( array(
					'has_more'      => false,
					'curr_count'    => $curr_count
				) );
			}
		}

		private function update_inventory( $object ) {
			global $wpdb;
			if ( 'ITEM_VARIATION' == $object->catalog_object_type && 'IN_STOCK' == $object->state ) {
				$found_optionitem = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_optionitemquantity WHERE square_id = %s', $object->catalog_object_id ) );
				if ( $found_optionitem ) {
					$wpdb->query( $wpdb->prepare( 'UPDATE ec_optionitemquantity SET quantity = %d WHERE square_id = %s', $object->quantity, $object->catalog_object_id ) );
					$wpdb->query( $wpdb->prepare( 'UPDATE ec_product SET ec_product.stock_quantity = ( SELECT SUM( ec_optionitemquantity.quantity ) FROM ec_optionitemquantity WHERE ec_optionitemquantity.product_id = ec_product.product_id ) WHERE ec_product.product_id = %d', $found_optionitem->product_id ) );
				} else {
					$wpdb->query( $wpdb->prepare( 'UPDATE ec_product SET ec_product.stock_quantity = %d WHERE square_variation_id = %s', $object->quantity, $object->catalog_object_id ) );
				}
			} else if ( 'IN_STOCK' == $object->state ) {
				$wpdb->query( $wpdb->prepare( 'UPDATE ec_product SET stock_quantity = %d WHERE square_id = %s', $object->quantity, $object->catalog_object_id ) );
			}
		}

		public function square_import( $cursor, $curr_count ){
			$square = new ec_square( );
			$response = $square->get_catalog( $cursor );
			$only_new = isset( $_POST['only_new'] ) ? (bool) $_POST['only_new'] : false;

			foreach ( $response->objects as $object ) {
				if( $object->type == "CATEGORY" ){
					$square->insert_category( $object, (bool) $_POST['sync_products'], $only_new );
				}else if( $object->type == "ITEM" ){
					$square->insert_product( $object, (bool) $_POST['sync_products'], (bool) $_POST['sync_inventory'], $only_new );
					$curr_count++;
				}
			}

			if( isset( $response->cursor ) && $response->cursor ){
				echo json_encode( array(
					'has_more'      => true,
					'cursor'        => $response->cursor,
					'curr_count'    => $curr_count
				) );
			} else {
				echo json_encode( array(
					'has_more'      => false,
					'curr_count'    => $curr_count
				) );
				flush_rewrite_rules();
			}
		}
	}
endif;

function wp_easycart_admin_cart_importer( ){
	return wp_easycart_admin_cart_importer::instance( );
}
wp_easycart_admin_cart_importer( );

add_action( 'wp_ajax_ec_admin_ajax_square_modifier_import', 'ec_admin_ajax_square_modifier_import' );
function ec_admin_ajax_square_modifier_import( ){
	if ( ! wp_easycart_admin_verification()->verify_access( 'wp-easycart-cart-importer' ) ) {
		return false;
	}

	wp_easycart_admin_cart_importer( )->square_import_modifiers( sanitize_text_field( wp_unslash( $_POST['cursor'] ) ), (int) $_POST['curr_count'] );
	wp_cache_flush();
	die( );
}
add_action( 'wp_ajax_ec_admin_ajax_square_modifier_items_import', 'ec_admin_ajax_square_modifier_items_import' );
function ec_admin_ajax_square_modifier_items_import( ){
	if ( ! wp_easycart_admin_verification()->verify_access( 'wp-easycart-cart-importer' ) ) {
		return false;
	}

	wp_easycart_admin_cart_importer( )->square_import_modifier_items( sanitize_text_field( wp_unslash( $_POST['cursor'] ) ), (int) $_POST['curr_count'] );
	wp_cache_flush();
	die( );
}
add_action( 'wp_ajax_ec_admin_ajax_square_categories_import', 'ec_admin_ajax_square_categories_import' );
function ec_admin_ajax_square_categories_import( ){
	if ( ! wp_easycart_admin_verification()->verify_access( 'wp-easycart-cart-importer' ) ) {
		return false;
	}

	wp_easycart_admin_cart_importer( )->square_import_categories( sanitize_text_field( wp_unslash( $_POST['cursor'] ) ), (int) $_POST['curr_count'] );
	wp_cache_flush();
	die( );
}
add_action( 'wp_ajax_ec_admin_ajax_square_sync_inventory', 'ec_admin_ajax_square_sync_inventory' );
function ec_admin_ajax_square_sync_inventory( ){
	if ( ! wp_easycart_admin_verification()->verify_access( 'wp-easycart-cart-importer' ) ) {
		return false;
	}

	wp_easycart_admin_cart_importer( )->square_sync_inventory_items( sanitize_text_field( wp_unslash( $_POST['cursor'] ) ), (int) $_POST['curr_count'] );
	wp_cache_flush();
	die( );
}
add_action( 'wp_ajax_ec_admin_ajax_square_import', 'ec_admin_ajax_square_import' );
function ec_admin_ajax_square_import( ){
	if ( ! wp_easycart_admin_verification()->verify_access( 'wp-easycart-cart-importer' ) ) {
		return false;
	}

	wp_easycart_admin_cart_importer( )->square_import( sanitize_text_field( wp_unslash( $_POST['cursor'] ) ), (int) $_POST['curr_count'] );
	wp_cache_flush();
	die( );
}
/**
 * WooCommerce importer batch endpoint ( @since 6.0.0 ).
 * POST stage=begin ( option sets, categories, manufacturer; returns total ) then
 * stage=batch with cursor=<next> until done. Nonce: wp-easycart-woo-importer-settings.
 */
add_action( 'wp_ajax_ec_admin_ajax_woo_import', 'ec_admin_ajax_woo_import' );
function ec_admin_ajax_woo_import() {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_settings' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
	}
	if ( ! wp_easycart_admin_verification()->verify_access( 'wp-easycart-woo-importer-settings' ) ) {
		wp_send_json_error( array( 'message' => __( 'Your session has expired. Reload the page and try again.', 'wp-easycart' ) ) );
	}
	if ( ! class_exists( 'WooCommerce' ) ) {
		wp_send_json_error( array( 'message' => __( 'WooCommerce is not active on this site.', 'wp-easycart' ) ) );
	}
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified above through wp_easycart_admin_verification()->verify_access().
	$stage  = isset( $_POST['stage'] ) ? sanitize_key( wp_unslash( $_POST['stage'] ) ) : 'batch';
	$cursor = isset( $_POST['cursor'] ) ? (int) $_POST['cursor'] : 0;
	// phpcs:enable
	if ( 'begin' === $stage ) {
		$result = wp_easycart_admin_cart_importer()->woo_import_begin();
	} else {
		$result = wp_easycart_admin_cart_importer()->woo_import_batch( $cursor );
	}
	wp_cache_flush();
	if ( ! empty( $result['error'] ) ) {
		wp_send_json_error( array( 'message' => $result['error'] ) );
	}
	wp_send_json_success( $result );
}
