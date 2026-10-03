<?php
/**
 * WP EasyCart — products another service manages ( a fulfillment partner, Square ), and the fields it owns ( 6.0.2 ).
 *
 * Loaded by class-wp-easycart-product-writer.php.
 *
 * Who manages a product: filter wp_easycart_product_managed_by( $slug, $product ). The default is 'square' where the
 * Square lock applies ( Square is the payment method, product or inventory sync is on and the product has a square_id:
 * the rule ecv2_is_square_locked() always used; it wins over a fulfillment partner ), else the product's
 * fulfillment_provider when that provider is active ( wp_easycart_fulfillment::is_active() ), else ''.
 *
 * What it owns: filter wp_easycart_product_locked_fields( array(), $product, $managed_by ), keys among fields():
 *
 *   title        the product title
 *   description  the long description
 *   price        the product price and each variant's price
 *   list_price   the previous ( compare-at ) price
 *   options      which option sets the product uses ( option_id_1..5 )
 *   variants     the variant rows themselves: added or removed by hand, switched on / off, their SKU, price, weight, cost
 *   sku          the product's SKU ( model_number ) and each variant's SKU
 *   stock        stock tracking, the product's stock and each variant's stock
 *   images       the gallery, image1..5 and the per-option images
 *   weight       the product weight
 *   dimensions   length, width and height
 *   categories   the categories the product is in
 *   shipping     the shipping card ( shippable, handling, backorders, restrictions ) and customs / packing
 *
 * FREE locks nothing by default ( Square keeps its own covers, printed by WP EasyCart PRO ); a provider's extension adds
 * its fields through the filter. The V2 product editor covers a section whose fields are all locked and disables the
 * locked inputs of the others; every product save handler keeps the stored value of a locked field ( hold() / release()
 * around the FREE editor saves, refuse() in the list's quick actions, PRO's variant, option and media saves ).
 *
 * @package WP_EasyCart
 * @since   6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_product_lock' ) ) :

	/**
	 * Managed products and their locked fields.
	 */
	class wp_easycart_product_lock {

		/**
		 * Product rows read for this request.
		 *
		 * @var array
		 */
		private static $rows = array();

		/**
		 * Answers for this request, product id => array( 'by', 'fields' ).
		 *
		 * @var array
		 */
		private static $answers = array();

		/**
		 * The field keys a provider can own.
		 *
		 * @return string[]
		 */
		public static function fields() {
			return array( 'title', 'description', 'price', 'list_price', 'options', 'variants', 'sku', 'stock', 'images', 'weight', 'dimensions', 'categories', 'shipping' );
		}

		/**
		 * The product row ( every column ), from an id or a row.
		 *
		 * @param int|object $product Product id or row.
		 * @return object|null
		 */
		public static function product( $product ) {
			global $wpdb;
			if ( is_array( $product ) ) {
				$product = (object) $product;
			}
			if ( is_object( $product ) ) {
				if ( isset( $product->product_id ) && property_exists( $product, 'square_id' ) && ( property_exists( $product, 'fulfillment_provider' ) || ! self::ready() ) ) {
					return $product;
				}
				$product = isset( $product->product_id ) ? (int) $product->product_id : 0;
			}
			$product_id = (int) $product;
			if ( $product_id <= 0 ) {
				return null;
			}
			if ( ! array_key_exists( $product_id, self::$rows ) ) {
				$row                       = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_product WHERE product_id = %d', $product_id ) );
				self::$rows[ $product_id ] = $row ? $row : null;
			}
			return self::$rows[ $product_id ];
		}

		/**
		 * Forget what this request read ( after a save changed the product ).
		 *
		 * @param int $product_id Product, or 0 for all.
		 */
		public static function forget( $product_id = 0 ) {
			if ( $product_id ) {
				unset( self::$rows[ (int) $product_id ], self::$answers[ (int) $product_id ] );
				return;
			}
			self::$rows    = array();
			self::$answers = array();
		}

		/**
		 * The fulfillment columns exist ( EC_UPGRADE_DB 117 ).
		 *
		 * @return bool
		 */
		private static function ready() {
			return (int) get_option( 'ec_option_db_new_version' ) >= 117;
		}

		/**
		 * Square's product or inventory sync owns this product ( the rule ecv2_is_square_locked() used before 6.0.2 ).
		 *
		 * @param object $row Product row.
		 * @return bool
		 */
		public static function square_manages( $row ) {
			if ( 'square' !== get_option( 'ec_option_payment_process_method' ) ) {
				return false;
			}
			if ( ! get_option( 'ec_option_square_auto_product_sync' ) && ! get_option( 'ec_option_square_auto_sync' ) ) {
				return false;
			}
			return is_object( $row ) && isset( $row->square_id ) && '' !== (string) $row->square_id;
		}

		/**
		 * Who manages the product: a provider slug, 'square', or ''.
		 *
		 * @param int|object $product Product id or row.
		 * @return string
		 */
		public static function managed_by( $product ) {
			$row = self::product( $product );
			if ( ! $row ) {
				return '';
			}
			$product_id = (int) $row->product_id;
			if ( isset( self::$answers[ $product_id ]['by'] ) ) {
				return self::$answers[ $product_id ]['by'];
			}
			$slug     = '';
			$provider = ( isset( $row->fulfillment_provider ) && is_string( $row->fulfillment_provider ) ) ? sanitize_key( $row->fulfillment_provider ) : '';
			// Square's lock wins where it applies ( the rule ecv2_is_square_locked() always used ), even for a product a
			// fulfillment partner makes.
			if ( self::square_manages( $row ) ) {
				$slug = 'square';
			} elseif ( '' !== $provider && class_exists( 'wp_easycart_fulfillment' ) && method_exists( 'wp_easycart_fulfillment', 'is_active' ) && wp_easycart_fulfillment::is_active( $provider ) ) {
				$slug = $provider;
			}
			/**
			 * Who manages a product ( its details come from that service and are not edited here ).
			 *
			 * @since 6.0.2
			 * @param string $slug    Provider slug, 'square' or ''. Default: 'square' where Square's sync owns the product,
			 *                        else the product's fulfillment_provider when that provider is active.
			 * @param object $product Product row.
			 */
			$slug                               = sanitize_key( (string) apply_filters( 'wp_easycart_product_managed_by', $slug, $row ) );
			self::$answers[ $product_id ]['by'] = $slug;
			return $slug;
		}

		/**
		 * The fields the managing service owns ( see the file docblock for the keys ).
		 *
		 * @param int|object $product Product id or row.
		 * @return string[]
		 */
		public static function locked_fields( $product ) {
			$row = self::product( $product );
			if ( ! $row ) {
				return array();
			}
			$product_id = (int) $row->product_id;
			if ( isset( self::$answers[ $product_id ]['fields'] ) ) {
				return self::$answers[ $product_id ]['fields'];
			}
			$by = self::managed_by( $row );
			/**
			 * The fields a managed product's service owns: the product editor shows them read-only and every save keeps
			 * their stored value.
			 *
			 * @since 6.0.2
			 * @param string[] $fields     Keys among title, description, price, list_price, options, variants, sku, stock,
			 *                             images, weight, dimensions, categories, shipping. Default none.
			 * @param object   $product    Product row.
			 * @param string   $managed_by wp_easycart_product_managed_by's answer.
			 */
			$fields                                 = (array) apply_filters( 'wp_easycart_product_locked_fields', array(), $row, $by );
			$fields                                 = array_values( array_intersect( self::fields(), array_map( 'sanitize_key', array_filter( $fields, 'is_string' ) ) ) );
			self::$answers[ $product_id ]['fields'] = $fields;
			return $fields;
		}

		/**
		 * The stored ec_product columns of a managed product's locked fields, for a save that writes raw columns ( the
		 * product CSV importer ) to put back afterwards. A product a fulfillment partner manages also keeps its
		 * fulfillment_provider, so a file exported before the partner was connected does not unlink it.
		 *
		 * @since 6.0.2
		 * @param int|object $product Product id or row.
		 * @return array column => stored value ( empty when nothing is locked ).
		 */
		public static function locked_columns( $product ) {
			$row    = self::product( $product );
			$locked = $row ? self::locked_fields( $row ) : array();
			if ( ! $locked ) {
				return array();
			}
			$map     = array(
				'title'       => array( 'title' ),
				'description' => array( 'description' ),
				'price'       => array( 'price' ),
				'list_price'  => array( 'list_price' ),
				'sku'         => array( 'model_number' ),
				'stock'       => array( 'stock_quantity', 'show_stock_quantity', 'use_optionitem_quantity_tracking' ),
				'weight'      => array( 'weight' ),
				'dimensions'  => array( 'width', 'height', 'length' ),
				'shipping'    => array( 'is_shippable', 'exclude_shippable_calculation', 'ship_to_billing', 'allow_backorders', 'backorder_fill_date', 'handling_price', 'handling_price_each', 'shipping_restriction' ),
				'images'      => array( 'product_images', 'image1', 'image2', 'image3', 'image4', 'image5', 'use_optionitem_images' ),
				'options'     => array( 'option_id_1', 'option_id_2', 'option_id_3', 'option_id_4', 'option_id_5', 'use_advanced_optionset', 'use_both_option_types' ),
			);
			$columns = array();
			foreach ( $locked as $field ) {
				foreach ( isset( $map[ $field ] ) ? $map[ $field ] : array() as $column ) {
					$columns[] = $column;
				}
			}
			$by = self::managed_by( $row );
			if ( '' !== $by && 'square' !== $by ) {
				$columns[] = 'fulfillment_provider';
			}
			$out = array();
			foreach ( array_unique( $columns ) as $column ) {
				if ( property_exists( $row, $column ) ) {
					$out[ $column ] = $row->{ $column };
				}
			}
			return $out;
		}

		/**
		 * Any of these fields is locked on the product.
		 *
		 * @param int|object      $product Product id or row.
		 * @param string|string[] $fields  Field keys.
		 * @return bool
		 */
		public static function is_locked( $product, $fields ) {
			$locked = self::locked_fields( $product );
			return (bool) array_intersect( (array) $fields, $locked );
		}

		/**
		 * Something can lock products at all ( a registered fulfillment provider, or a listener on the two filters ), so
		 * lists that show many products only ask per product when it can matter.
		 *
		 * @return bool
		 */
		public static function any() {
			if ( has_filter( 'wp_easycart_product_managed_by' ) || has_filter( 'wp_easycart_product_locked_fields' ) ) {
				return true;
			}
			return class_exists( 'wp_easycart_fulfillment' ) && method_exists( 'wp_easycart_fulfillment', 'providers' ) && (bool) wp_easycart_fulfillment::providers();
		}

		/**
		 * Attributes for a product list row: data-managed-by, data-managed-locked ( field keys ), data-managed-notice
		 * ( one sentence ). Empty when nothing is locked.
		 *
		 * @param int|object $product Product id or row.
		 * @return string Escaped attributes with a leading space, or ''.
		 */
		public static function row_attributes( $product ) {
			if ( ! self::any() ) {
				return '';
			}
			$fields = self::locked_fields( $product );
			if ( ! $fields ) {
				return '';
			}
			$by    = self::managed_by( $product );
			$label = self::label( $by );
			/* translators: 1: the service that manages the product ( e.g. Printful ), 2: what it owns ( e.g. price, stock ). */
			$notice = sprintf( __( 'Managed by %1$s: %2$s. Change them in %1$s and they arrive here on the next sync.', 'wp-easycart' ), $label, self::field_names( $fields ) );
			return ' data-managed-by="' . esc_attr( $by ) . '" data-managed-locked="' . esc_attr( implode( ',', $fields ) ) . '" data-managed-notice="' . esc_attr( $notice ) . '"';
		}

		/**
		 * The name to show for a managing service.
		 *
		 * @param string $slug Slug.
		 * @return string
		 */
		public static function label( $slug ) {
			$slug = (string) $slug;
			if ( '' === $slug ) {
				return __( 'another service', 'wp-easycart' );
			}
			if ( 'square' === $slug ) {
				return 'Square';
			}
			if ( class_exists( 'wp_easycart_fulfillment' ) && method_exists( 'wp_easycart_fulfillment', 'label' ) ) {
				$label = (string) wp_easycart_fulfillment::label( $slug );
				if ( '' !== $label ) {
					return $label;
				}
			}
			return ucfirst( $slug );
		}

		/**
		 * The service's own dashboard, when its provider gives one.
		 *
		 * @param string $slug Slug.
		 * @return string
		 */
		public static function url( $slug ) {
			if ( '' === (string) $slug || ! class_exists( 'wp_easycart_fulfillment' ) || ! method_exists( 'wp_easycart_fulfillment', 'provider' ) ) {
				return '';
			}
			$provider = wp_easycart_fulfillment::provider( $slug );
			return ( is_array( $provider ) && ! empty( $provider['url'] ) && is_string( $provider['url'] ) ) ? esc_url_raw( $provider['url'] ) : '';
		}

		/**
		 * One sentence for a refused change.
		 *
		 * @param int|object $product Product id or row.
		 * @return string
		 */
		public static function message( $product ) {
			$label = self::label( self::managed_by( $product ) );
			/* translators: %s: the service that manages the product ( e.g. Printful ). */
			return sprintf( __( 'This is managed by %s. Change it there and it arrives here on the next sync.', 'wp-easycart' ), $label );
		}

		/**
		 * AJAX: stop here with an error when any of these fields is locked.
		 *
		 * @param int             $product_id Product.
		 * @param string|string[] $fields     Field keys.
		 */
		public static function refuse( $product_id, $fields ) {
			if ( (int) $product_id > 0 && self::is_locked( (int) $product_id, $fields ) ) {
				wp_send_json_error(
					array(
						'message'    => self::message( (int) $product_id ),
						'managed_by' => self::managed_by( (int) $product_id ),
						'locked'     => array_values( array_intersect( (array) $fields, self::locked_fields( (int) $product_id ) ) ),
					)
				);
			}
		}

		/**
		 * The product editor's save sections: 'block' fields refuse the whole save; 'keep' fields put the stored value
		 * back into the posted form ( field => post key => column, or a callable( $row ) for a value worked out from
		 * columns ); 'columns' names the columns release() restores for a field when not every one is a posted column.
		 *
		 * @return array
		 */
		public static function sections() {
			$stock_type = function ( $row ) {
				return (int) $row->use_optionitem_quantity_tracking ? '2' : ( (int) $row->show_stock_quantity ? '1' : '0' );
			};
			$sections   = array(
				'basic'         => array(
					'keep' => array(
						'title'       => array( 'title' => 'title' ),
						'description' => array( 'description' => 'description' ),
						'price'       => array( 'price' => 'price' ),
						'list_price'  => array( 'list_price' => 'list_price' ),
						'sku'         => array( 'model_number' => 'model_number' ),
					),
				),
				'pricing'       => array(
					'keep' => array( 'list_price' => array( 'list_price' => 'list_price' ) ),
				),
				'quantities'    => array(
					'keep'    => array(
						'stock' => array(
							'stock_quantity'      => 'stock_quantity',
							'stock_quantity_type' => $stock_type,
						),
					),
					'columns' => array( 'stock' => array( 'stock_quantity', 'show_stock_quantity', 'use_optionitem_quantity_tracking' ) ),
				),
				'packaging'     => array(
					'keep' => array(
						'weight'     => array( 'weight' => 'weight' ),
						'dimensions' => array(
							'width'  => 'width',
							'height' => 'height',
							'length' => 'length',
						),
					),
				),
				'shipping'      => array(
					'keep' => array(
						'shipping' => array(
							'is_shippable'         => 'is_shippable',
							'exclude_shippable_calculation' => 'exclude_shippable_calculation',
							'ship_to_billing'      => 'ship_to_billing',
							'allow_backorders'     => 'allow_backorders',
							'backorder_fill_date'  => 'backorder_fill_date',
							'handling_price'       => 'handling_price',
							'handling_price_each'  => 'handling_price_each',
							'shipping_restriction' => 'shipping_restriction',
						),
					),
				),
				'customs'       => array( 'block' => array( 'shipping' ) ),
				'options'       => array( 'block' => array( 'options' ) ),
				'images'        => array( 'block' => array( 'images' ) ),
				'categories'    => array( 'block' => array( 'categories' ) ),
				'variant_rows'  => array( 'block' => array( 'variants' ) ),
				'variant_stock' => array( 'block' => array( 'stock' ) ),
				'quick'         => array(
					'keep'    => array(
						'title'      => array( 'title' => 'title' ),
						'sku'        => array( 'model_number' => 'model_number' ),
						'price'      => array( 'price' => 'price' ),
						'list_price' => array( 'list_price' => 'list_price' ),
						'images'     => array( 'image1' => 'image1' ),
						'stock'      => array(
							'stock_option'   => $stock_type,
							'stock_quantity' => 'stock_quantity',
						),
						'shipping'   => array( 'is_shippable' => 'is_shippable' ),
						'weight'     => array( 'weight' => 'weight' ),
						'dimensions' => array(
							'width'  => 'width',
							'height' => 'height',
							'length' => 'length',
						),
					),
					'columns' => array( 'stock' => array( 'stock_quantity', 'show_stock_quantity', 'use_optionitem_quantity_tracking' ) ),
				),
			);
			/**
			 * The product editor's save sections and the locked fields each keeps or refuses.
			 *
			 * @since 6.0.2
			 * @param array $sections Section => array( 'block' => fields, 'keep' => field => post key => column | callable,
			 *                        'columns' => field => columns ).
			 */
			return (array) apply_filters( 'wp_easycart_product_lock_sections', $sections );
		}

		/**
		 * Before a product editor save: put the stored value of every locked field back into $_POST, or refuse the save
		 * when it changes something that is locked as a whole ( option sets, images, categories, variant rows ).
		 *
		 * @param int    $product_id Product ( 0 = a new product: nothing is locked ).
		 * @param string $section    A key of sections().
		 * @return array Hold for release(): product_id, section, managed_by, locked ( fields that applied ), blocked, restore
		 *               ( column => stored value ).
		 */
		public static function hold( $product_id, $section ) {
			$hold     = array(
				'product_id' => (int) $product_id,
				'section'    => (string) $section,
				'managed_by' => '',
				'locked'     => array(),
				'blocked'    => false,
				'restore'    => array(),
			);
			$sections = self::sections();
			if ( $hold['product_id'] <= 0 || ! isset( $sections[ $section ] ) ) {
				return $hold;
			}
			$row = self::product( $hold['product_id'] );
			if ( ! $row ) {
				return $hold;
			}
			$locked = self::locked_fields( $row );
			if ( empty( $locked ) ) {
				return $hold;
			}
			$hold['managed_by'] = self::managed_by( $row );
			$def                = $sections[ $section ];
			$block              = isset( $def['block'] ) ? array_intersect( (array) $def['block'], $locked ) : array();
			if ( ! empty( $block ) ) {
				$hold['blocked'] = true;
				$hold['locked']  = array_values( $block );
				return $hold;
			}
			$keep = isset( $def['keep'] ) ? (array) $def['keep'] : array();
			foreach ( $keep as $field => $posts ) {
				if ( ! in_array( $field, $locked, true ) ) {
					continue;
				}
				$hold['locked'][] = $field;
				foreach ( (array) $posts as $post_key => $source ) {
					$value              = is_callable( $source ) ? call_user_func( $source, $row ) : ( isset( $row->{ $source } ) ? $row->{ $source } : '' );
					$_POST[ $post_key ] = wp_slash( (string) $value ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- the caller verified the request; this writes the stored value back.
					if ( ! is_callable( $source ) && property_exists( $row, $source ) ) {
						$hold['restore'][ $source ] = $row->{ $source };
					}
				}
				if ( isset( $def['columns'][ $field ] ) ) {
					foreach ( (array) $def['columns'][ $field ] as $column ) {
						if ( property_exists( $row, $column ) ) {
							$hold['restore'][ $column ] = $row->{ $column };
						}
					}
				}
			}
			return $hold;
		}

		/**
		 * After the save: put back any locked column the save changed anyway ( sanitizing can reshape a value ), and tell
		 * listeners the product changed ( wpeasycart_product_updated, once per request ).
		 *
		 * @param array $hold From hold().
		 * @param bool  $updated Fire wpeasycart_product_updated.
		 */
		public static function release( $hold, $updated = true ) {
			global $wpdb;
			if ( ! is_array( $hold ) || empty( $hold['product_id'] ) ) {
				return;
			}
			$product_id = (int) $hold['product_id'];
			if ( ! empty( $hold['restore'] ) ) {
				$columns = array_keys( $hold['restore'] );
				$current = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_product WHERE product_id = %d', $product_id ), ARRAY_A );
				$changes = array();
				foreach ( $columns as $column ) {
					if ( is_array( $current ) && array_key_exists( $column, $current ) && (string) $current[ $column ] !== (string) $hold['restore'][ $column ] ) {
						$changes[ $column ] = $hold['restore'][ $column ];
					}
				}
				if ( $changes ) {
					$wpdb->update( 'ec_product', $changes, array( 'product_id' => $product_id ) );
				}
			}
			self::forget( $product_id );
			if ( $updated && ! $hold['blocked'] && class_exists( 'wp_easycart_product_writer' ) ) {
				wp_easycart_product_writer::flush_cache( $product_id );
				wp_easycart_product_writer::updated( $product_id );
			}
		}

		/**
		 * The cover over a product editor section the managing service owns: "Managed by {label}", a sentence, and a
		 * link to the service when its provider gives one.
		 *
		 * @param int|object $product Product id or row.
		 * @param array      $args    title ( heading, default "Managed by {label}" ), text, class ( extra classes ),
		 *                            inline ( a slim banner instead of a cover ), fields ( what is locked, for the banner ).
		 */
		public static function print_cover( $product, $args = array() ) {
			$by    = self::managed_by( $product );
			$label = self::label( $by );
			$url   = self::url( $by );
			$args  = array_merge(
				array(
					/* translators: %s: the service that manages the product ( e.g. Printful ). */
					'title'  => sprintf( __( 'Managed by %s', 'wp-easycart' ), $label ),
					/* translators: %s: the service that manages the product ( e.g. Printful ). */
					'text'   => sprintf( __( 'These details come from %s. Change them there and they arrive here on the next sync.', 'wp-easycart' ), $label ),
					'class'  => '',
					'inline' => false,
				),
				(array) $args
			);
			$link  = '';
			if ( '' !== $url ) {
				/* translators: %s: the service that manages the product ( e.g. Printful ). */
				$link = '<a class="ecv2-btn ecv2-btn-sm ecdv2-managed-open" href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( sprintf( __( 'Open in %s', 'wp-easycart' ), $label ) ) . ' <span class="dashicons dashicons-external" aria-hidden="true"></span></a>';
			}
			$classes = trim( ( $args['inline'] ? 'ecdv2-managed-banner' : 'ecdv2-managed-cover' ) . ' ' . $args['class'] );
			echo '<div class="' . esc_attr( $classes ) . '" role="note" data-ecdv2-managed-by="' . esc_attr( $by ) . '">';
			echo '<div class="ecdv2-managed-card">';
			echo '<span class="ecdv2-managed-mark dashicons dashicons-lock" aria-hidden="true"></span>';
			echo '<span class="ecdv2-managed-text"><b>' . esc_html( $args['title'] ) . '</b><span>' . esc_html( $args['text'] ) . '</span></span>';
			echo $link; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_url() and esc_html() above.
			echo '</div></div>';
		}

		/**
		 * The names of the fields, for "Managed by Printful: title, price".
		 *
		 * @param string[] $fields Field keys.
		 * @return string
		 */
		public static function field_names( $fields ) {
			$names = array(
				'title'       => __( 'title', 'wp-easycart' ),
				'description' => __( 'description', 'wp-easycart' ),
				'price'       => __( 'price', 'wp-easycart' ),
				'list_price'  => __( 'previous price', 'wp-easycart' ),
				'options'     => __( 'option sets', 'wp-easycart' ),
				'variants'    => __( 'variants', 'wp-easycart' ),
				'sku'         => __( 'SKU', 'wp-easycart' ),
				'stock'       => __( 'stock', 'wp-easycart' ),
				'images'      => __( 'images', 'wp-easycart' ),
				'weight'      => __( 'weight', 'wp-easycart' ),
				'dimensions'  => __( 'dimensions', 'wp-easycart' ),
				'categories'  => __( 'categories', 'wp-easycart' ),
				'shipping'    => __( 'shipping', 'wp-easycart' ),
			);
			$out   = array();
			foreach ( (array) $fields as $field ) {
				if ( isset( $names[ $field ] ) ) {
					$out[] = $names[ $field ];
				}
			}
			return implode( ', ', $out );
		}
	}

endif;
