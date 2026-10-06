<?php
/**
 * Product queries for the Products and Product Carousel widgets and the load-more endpoint ( 6.0.2 ).
 *
 * A widget's settings become a plain query array ( resolve() ): the current category, manufacturer or product of the page
 * is turned into ids there, so the same array can be printed into the page and sent back by "Load more" without any page
 * context. run() builds the WHERE / ORDER BY and asks ec_db::get_product_list(), the query every EasyCart product list uses
 * ( role prices, role-only products and the product cache come with it ).
 *
 * These lists never follow the store's URL filters ( a search on the store page must not change a "Featured" carousel on
 * it ); the Shop widget does ( WP_EasyCart_Elementor_Shop_Archive_Widget ). Only products switched on in the store are
 * listed, for managers too, so the editor shows what visitors will see.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Shop_Query' ) ) :

	/**
	 * Query sources, ordering and paging for product lists.
	 */
	class WP_EasyCart_Elementor_Shop_Query {

		/**
		 * Most products one page of a list may hold.
		 */
		const MAX_PER_PAGE = 48;

		/**
		 * Most ids a query carries ( chosen products, categories, best sellers, related products ).
		 */
		const MAX_IDS = 100;

		/**
		 * Transient that keeps role_content()'s answer ( 'yes' | 'no' ) for an hour.
		 */
		const ROLE_CONTENT_KEY = 'wpec_el_role_content';

		/**
		 * The answer of role_content() for this request ( null = not asked yet ).
		 *
		 * @var bool|null
		 */
		private static $role_content = null;

		/**
		 * The sources a list can use ( ec_source ).
		 *
		 * @return array
		 */
		public static function sources() {
			return array( 'all', 'featured', 'on_sale', 'newest', 'best_sellers', 'pick', 'category', 'current_category', 'manufacturer', 'current_manufacturer', 'related' );
		}

		/**
		 * The orders a list can use ( ec_orderby ).
		 *
		 * @return array
		 */
		public static function orders() {
			return array( '', 'store', 'title', 'title_desc', 'price', 'price_desc', 'newest', 'oldest', 'rating', 'popular', 'random' );
		}

		/**
		 * A widget's query from its settings.
		 *
		 * @param array $settings  Widget settings ( get_settings_for_display() ).
		 * @param bool  $is_editor Whether the editor draws the widget ( sample category / product allowed ).
		 * @return array array( 'query' => array|null, 'note' => editor note ). A null query shows nothing.
		 */
		public static function resolve( $settings, $is_editor ) {
			$source = isset( $settings['ec_source'] ) ? (string) $settings['ec_source'] : 'all';
			if ( ! in_array( $source, self::sources(), true ) ) {
				$source = 'all';
			}
			$query   = array(
				'source'           => $source,
				'ids'              => array(),
				'category_ids'     => array(),
				'manufacturer_ids' => array(),
				'exclude'          => array(),
				'in_stock'         => ( isset( $settings['ec_in_stock'] ) && 'yes' === $settings['ec_in_stock'] ),
				'orderby'          => ( isset( $settings['ec_orderby'] ) && in_array( $settings['ec_orderby'], self::orders(), true ) ) ? $settings['ec_orderby'] : '',
				'per_page'         => self::clamp( isset( $settings['ec_limit'] ) ? $settings['ec_limit'] : 8, 1, self::MAX_PER_PAGE, 8 ),
				'page'             => 1,
			);
			$note    = '';
			$context = function_exists( 'wp_easycart_elementor_context' ) ? wp_easycart_elementor_context() : null;

			switch ( $source ) {
				case 'pick':
					$query['ids'] = self::ids( isset( $settings['ec_product_ids'] ) ? $settings['ec_product_ids'] : array() );
					if ( empty( $query['ids'] ) ) {
						return array(
							'query' => null,
							'note'  => __( 'Choose the products to show under Content › Products.', 'wp-easycart' ),
						);
					}
					break;

				case 'category':
					$query['category_ids'] = self::ids( isset( $settings['ec_category_ids'] ) ? $settings['ec_category_ids'] : array() );
					if ( empty( $query['category_ids'] ) ) {
						return array(
							'query' => null,
							'note'  => __( 'Choose one or more categories under Content › Products.', 'wp-easycart' ),
						);
					}
					break;

				case 'current_category':
					$category_id = 0;
					$category    = $context ? $context->category( array( 'allow_sample' => false ) ) : null;
					if ( $category && isset( $category->category_id ) ) {
						$category_id = (int) $category->category_id;
					}
					$product = ( ! $category_id && $context ) ? $context->product(
						array(
							'allow_sample' => false,
							'details'      => false,
						)
					) : null;
					if ( ! $category_id && $product ) {
						$category_id        = self::first_category_id( (int) $product->product_id );
						$query['exclude'][] = (int) $product->product_id;
					}
					if ( ! $category_id && $is_editor && $context ) {
						$category = $context->category( array( 'allow_sample' => true ) );
						if ( $category && isset( $category->category_id ) ) {
							$category_id = (int) $category->category_id;
							$note        = __( 'On category pages and category templates this shows that category’s products. While you edit this page, a sample category is shown.', 'wp-easycart' );
						}
					}
					if ( ! $category_id ) {
						return array(
							'query' => null,
							'note'  => __( 'This shows the products of the category page it is on. Add it to a category template, or choose categories under Content › Products.', 'wp-easycart' ),
						);
					}
					$query['category_ids'] = array( $category_id );
					break;

				case 'manufacturer':
					$query['manufacturer_ids'] = self::ids( isset( $settings['ec_manufacturer_ids'] ) ? $settings['ec_manufacturer_ids'] : array() );
					if ( empty( $query['manufacturer_ids'] ) ) {
						return array(
							'query' => null,
							'note'  => __( 'Choose one or more manufacturers under Content › Products.', 'wp-easycart' ),
						);
					}
					break;

				case 'current_manufacturer':
					$manufacturer_id = 0;
					$manufacturer    = $context ? $context->manufacturer() : null;
					if ( $manufacturer && isset( $manufacturer->manufacturer_id ) ) {
						$manufacturer_id = (int) $manufacturer->manufacturer_id;
					}
					$product = ( ! $manufacturer_id && $context ) ? $context->product(
						array(
							'allow_sample' => $is_editor,
							'details'      => false,
						)
					) : null;
					if ( ! $manufacturer_id && $product && ! empty( $product->manufacturer_id ) ) {
						$manufacturer_id    = (int) $product->manufacturer_id;
						$query['exclude'][] = (int) $product->product_id;
						if ( $is_editor && ! $context->current_post_product_id() ) {
							$note = __( 'On manufacturer and product pages this shows that manufacturer’s products. While you edit this page, a sample is shown.', 'wp-easycart' );
						}
					}
					if ( ! $manufacturer_id ) {
						return array(
							'query' => null,
							'note'  => __( 'This shows the products of the manufacturer page or product page it is on. Or choose manufacturers under Content › Products.', 'wp-easycart' ),
						);
					}
					$query['manufacturer_ids'] = array( $manufacturer_id );
					break;

				case 'related':
					$product = $context ? $context->product(
						array(
							'allow_sample' => $is_editor,
							'details'      => false,
						)
					) : null;
					if ( ! $product ) {
						return array(
							'query' => null,
							'note'  => __( 'Related products show on product pages and product templates.', 'wp-easycart' ),
						);
					}
					$query['ids'] = self::related_ids( (int) $product->product_id );
					if ( empty( $query['ids'] ) ) {
						return array(
							'query' => null,
							'note'  => __( 'This product has no related products yet: choose some in its Featured products, or put it in a category with other products.', 'wp-easycart' ),
						);
					}
					if ( $is_editor && ! $context->current_post_product_id() ) {
						$note = __( 'On product pages this shows products related to that product. While you edit this page, a sample product is used.', 'wp-easycart' );
					}
					break;

				case 'best_sellers':
					$query['ids'] = self::best_seller_ids();
					if ( empty( $query['ids'] ) ) {
						if ( ! $is_editor ) {
							return array(
								'query' => null,
								'note'  => '',
							);
						}
						/* The editor shows the newest products until orders come in; visitors see nothing until then. */
						$query['source'] = 'newest';
						$note            = __( 'No sales yet, so the newest products are shown here. Visitors see your best sellers once orders come in.', 'wp-easycart' );
					}
					break;
			}

			return array(
				'query' => $query,
				'note'  => $note,
			);
		}

		/**
		 * A query sent back by the storefront ( load more ): only known values, ids and sizes kept in bounds.
		 *
		 * @param mixed $raw Decoded JSON.
		 * @return array|null
		 */
		public static function sanitize( $raw ) {
			if ( ! is_array( $raw ) ) {
				return null;
			}
			$source = isset( $raw['source'] ) ? (string) $raw['source'] : '';
			/* The page's own category, manufacturer or product was turned into ids when the page was drawn. */
			if ( ! in_array( $source, array( 'all', 'featured', 'on_sale', 'newest', 'best_sellers', 'pick', 'category', 'manufacturer', 'related' ), true ) ) {
				return null;
			}
			return array(
				'source'           => $source,
				'ids'              => self::ids( isset( $raw['ids'] ) ? $raw['ids'] : array() ),
				'category_ids'     => self::ids( isset( $raw['category_ids'] ) ? $raw['category_ids'] : array() ),
				'manufacturer_ids' => self::ids( isset( $raw['manufacturer_ids'] ) ? $raw['manufacturer_ids'] : array() ),
				'exclude'          => self::ids( isset( $raw['exclude'] ) ? $raw['exclude'] : array() ),
				'in_stock'         => ! empty( $raw['in_stock'] ),
				'orderby'          => ( isset( $raw['orderby'] ) && in_array( $raw['orderby'], self::orders(), true ) ) ? $raw['orderby'] : '',
				'per_page'         => self::clamp( isset( $raw['per_page'] ) ? $raw['per_page'] : 8, 1, self::MAX_PER_PAGE, 8 ),
				'page'             => self::clamp( isset( $raw['page'] ) ? $raw['page'] : 1, 1, 1000, 1 ),
			);
		}

		/**
		 * The query in the form printed into the page for "Load more" ( current_* already resolved ).
		 *
		 * @param array $query Query from resolve().
		 * @return array
		 */
		public static function for_client( $query ) {
			$source = $query['source'];
			if ( 'current_category' === $source ) {
				$source = 'category';
			} elseif ( 'current_manufacturer' === $source ) {
				$source = 'manufacturer';
			}
			return array(
				'source'           => $source,
				'ids'              => array_values( $query['ids'] ),
				'category_ids'     => array_values( $query['category_ids'] ),
				'manufacturer_ids' => array_values( $query['manufacturer_ids'] ),
				'exclude'          => array_values( $query['exclude'] ),
				'in_stock'         => $query['in_stock'] ? 1 : 0,
				'orderby'          => $query['orderby'],
				'per_page'         => (int) $query['per_page'],
			);
		}

		/**
		 * Runs a query.
		 *
		 * @param array $query Query from resolve() or sanitize().
		 * @return array array( 'products' => ec_product[], 'total' => int, 'pages' => int ).
		 */
		public static function run( $query ) {
			$empty = array(
				'products' => array(),
				'total'    => 0,
				'pages'    => 0,
			);
			if ( ! is_array( $query ) || ! class_exists( 'ec_db' ) || ! class_exists( 'ec_product' ) ) {
				return $empty;
			}
			$where         = 'WHERE product.activate_in_store = 1' . ( function_exists( 'wp_easycart_unlisted_sql' ) ? wp_easycart_unlisted_sql( 'product' ) : '' ); /* 6.0.3: not plan group products */
			$joins         = '';
			$default_order = '';
			$ids           = self::ids( $query['ids'] );

			switch ( $query['source'] ) {
				case 'pick':
				case 'related':
				case 'best_sellers':
					if ( empty( $ids ) ) {
						return $empty;
					}
					$where        .= ' AND product.product_id IN (' . implode( ',', $ids ) . ')';
					$default_order = ' ORDER BY FIELD( product.product_id, ' . implode( ',', $ids ) . ' )';
					break;
				case 'category':
				case 'current_category':
					$category_ids = self::ids( $query['category_ids'] );
					if ( empty( $category_ids ) ) {
						return $empty;
					}
					/* A subquery with an alias: get_product_list() would otherwise join ec_categoryitem to every row. */
					$where .= ' AND product.product_id IN ( SELECT wpec_ci.product_id FROM ec_categoryitem AS wpec_ci WHERE wpec_ci.category_id IN (' . implode( ',', $category_ids ) . ') )';
					break;
				case 'manufacturer':
				case 'current_manufacturer':
					$manufacturer_ids = self::ids( $query['manufacturer_ids'] );
					if ( empty( $manufacturer_ids ) ) {
						return $empty;
					}
					$where .= ' AND product.manufacturer_id IN (' . implode( ',', $manufacturer_ids ) . ')';
					break;
				case 'featured':
					$where .= ' AND product.show_on_startup = 1';
					break;
				case 'on_sale':
					$where .= ' AND product.list_price > product.price';
					break;
				case 'newest':
					$default_order = ' ORDER BY product.added_to_db_date DESC, product.product_id DESC';
					break;
			}

			$exclude = self::ids( $query['exclude'] );
			if ( ! empty( $exclude ) ) {
				$where .= ' AND product.product_id NOT IN (' . implode( ',', $exclude ) . ')';
			}
			if ( ! empty( $query['in_stock'] ) ) {
				$where .= ' AND ( product.stock_quantity > 0 OR ( product.show_stock_quantity = 0 AND product.use_optionitem_quantity_tracking = 0 ) OR product.allow_backorders = 1 )';
			}
			$where      .= self::hide_out_of_stock_sql( 'product' );
			$location_id = self::location_id();
			if ( $location_id ) {
				/* The store's rule for a chosen pickup location that hides what it does not carry ( as ec_filter ). */
				$joins .= ' INNER JOIN ec_location_to_product AS wpec_lp ON wpec_lp.product_id = product.product_id ';
				$where .= ' AND wpec_lp.location_id = ' . (int) $location_id;
			}

			$order    = self::order_sql( $query['orderby'], $default_order );
			$per_page = self::clamp( $query['per_page'], 1, self::MAX_PER_PAGE, 8 );
			$page     = self::clamp( isset( $query['page'] ) ? $query['page'] : 1, 1, 1000, 1 );
			$limit    = ' LIMIT ' . ( ( $page - 1 ) * $per_page ) . ', ' . $per_page;
			$cache    = ( 'random' === $query['orderby'] ) ? '' : 'wpeasycart-elementor-products';

			$db   = new ec_db();
			$rows = $db->get_product_list( $where, $order, $limit, '', $cache, '', $joins );
			if ( ! is_array( $rows ) || empty( $rows ) ) {
				return $empty;
			}
			$total    = isset( $rows[0]['product_count'] ) ? (int) $rows[0]['product_count'] : count( $rows );
			$products = array();
			foreach ( $rows as $index => $row ) {
				/* As a "featured" product: no store-menu link options are worked out for each card. */
				$products[] = new ec_product( $row, 1, 0, 1, $index );
			}
			return array(
				'products' => $products,
				'total'    => $total,
				'pages'    => (int) ceil( $total / $per_page ),
			);
		}

		/**
		 * ORDER BY for a list.
		 *
		 * @param string $orderby       Order ( '' = the source's own ).
		 * @param string $default_order The source's own order, or ''.
		 * @return string
		 */
		public static function order_sql( $orderby, $default_order = '' ) {
			switch ( $orderby ) {
				case 'title':
					return ' ORDER BY product.title ASC, product.product_id ASC';
				case 'title_desc':
					return ' ORDER BY product.title DESC, product.product_id ASC';
				case 'price':
					return ' ORDER BY product.price ASC, product.product_id ASC';
				case 'price_desc':
					return ' ORDER BY product.price DESC, product.product_id ASC';
				case 'newest':
					return ' ORDER BY product.added_to_db_date DESC, product.product_id DESC';
				case 'oldest':
					return ' ORDER BY product.added_to_db_date ASC, product.product_id ASC';
				case 'rating':
					return ' ORDER BY review_average DESC, product.product_id ASC';
				case 'popular':
					return ' ORDER BY product.views DESC, product.product_id ASC';
				case 'random':
					return ' ORDER BY RAND()';
				case 'store':
					return self::store_order_sql( (int) get_option( 'ec_option_default_store_filter' ) );
			}
			return ( '' !== $default_order ) ? $default_order : self::store_order_sql( (int) get_option( 'ec_option_default_store_filter' ) );
		}

		/**
		 * The store's sort order ( Settings › Products › Product lists: default sort ), as ec_filter::get_order_by_query().
		 *
		 * @param int $filter Sort number 0-8.
		 * @return string
		 */
		public static function store_order_sql( $filter ) {
			switch ( (int) $filter ) {
				case 1:
					return ' ORDER BY product.price ASC, product.product_id ASC';
				case 2:
					return ' ORDER BY product.price DESC, product.product_id ASC';
				case 3:
					return ' ORDER BY product.title ASC, product.product_id ASC';
				case 4:
					return ' ORDER BY product.title DESC, product.product_id ASC';
				case 5:
					return ' ORDER BY product.added_to_db_date DESC, product.product_id DESC';
				case 8:
					return ' ORDER BY product.added_to_db_date ASC, product.product_id ASC';
				case 6:
					return ' ORDER BY review_average DESC, product.product_id ASC';
				case 7:
					return ' ORDER BY product.views DESC, product.product_id ASC';
			}
			return ' ORDER BY product.sort_position ASC, product.price ASC, product.product_id ASC';
		}

		/**
		 * The store's "Hide out of stock products" rule for a product table alias ( '' when the setting is off ).
		 *
		 * @param string $alias Table alias.
		 * @return string SQL starting with ' AND'.
		 */
		public static function hide_out_of_stock_sql( $alias ) {
			if ( ! get_option( 'ec_option_hide_out_of_stock' ) ) {
				return '';
			}
			$a = preg_replace( '/[^a-z0-9_]/i', '', (string) $alias );
			return ' AND ( ( ' . $a . '.show_stock_quantity = 0 AND ' . $a . '.use_optionitem_quantity_tracking = 0 ) OR ( ' . $a . '.stock_quantity > 0 AND ( ' . $a . '.show_stock_quantity = 1 OR ' . $a . '.use_optionitem_quantity_tracking = 1 ) ) OR ' . $a . '.allow_backorders = 1 )';
		}

		/**
		 * The rules every product list follows, for queries that count products outside ec_db::get_product_list() ( the
		 * category, manufacturer and price counts ): role-only products ( get_product_list()'s rule ), "Hide out of stock
		 * products", and a pickup location that hides what it does not carry. Active products are the caller's own clause.
		 *
		 * @param string $alias Table alias of ec_product.
		 * @return string SQL starting with ' AND' ( integers only ).
		 */
		public static function visibility_sql( $alias ) {
			$a       = preg_replace( '/[^a-z0-9_]/i', '', (string) $alias );
			$role_id = ( isset( $GLOBALS['ec_user'] ) && is_object( $GLOBALS['ec_user'] ) && ! empty( $GLOBALS['ec_user']->role_id ) ) ? (int) $GLOBALS['ec_user']->role_id : 0;
			$sql     = ' AND ( ' . $a . '.role_id = 0 OR ' . $a . '.role_id = ' . ( $role_id ? $role_id : -1 ) . ' )';
			$sql    .= self::hide_out_of_stock_sql( $a );
			$sql    .= ( function_exists( 'wp_easycart_unlisted_sql' ) ) ? wp_easycart_unlisted_sql( $a ) : ''; /* 6.0.3: not plan group products */

			$location_id = self::location_id();
			if ( $location_id ) {
				$sql .= ' AND ' . $a . '.product_id IN ( SELECT wpec_lp.product_id FROM ec_location_to_product AS wpec_lp WHERE wpec_lp.location_id = ' . (int) $location_id . ' )';
			}
			return $sql;
		}

		/**
		 * Whether what a product list shows can depend on the shopper's store account: the store has role prices for active
		 * products ( ec_roleprice ) or active products kept for one customer role ( ec_product.role_id ). A shopper signed in
		 * to a store account is not a WordPress login, so a page cache would otherwise keep one shopper's prices or products
		 * and serve them to everyone ( the Products, Product Carousel and Product Categories widgets and Load more are
		 * cacheable; see role_no_cache() ).
		 *
		 * At most one query per request: the answer is kept for an hour ( transient wpec_el_role_content ) and dropped when a
		 * product is added, saved, switched on or off, duplicated, restored or deleted ( forget_role_content() ).
		 *
		 * @since 6.0.2
		 *
		 * @return bool
		 */
		public static function role_content() {
			if ( null === self::$role_content ) {
				$stored = get_transient( self::ROLE_CONTENT_KEY );
				if ( 'yes' === $stored || 'no' === $stored ) {
					self::$role_content = ( 'yes' === $stored );
				} else {
					global $wpdb;
					/* Fixed SQL; each EXISTS stops at the first row it finds ( MySQL 5.5 too ). */
					$found              = $wpdb->get_var( 'SELECT EXISTS( SELECT 1 FROM ec_roleprice AS wpec_rp INNER JOIN ec_product AS wpec_p ON wpec_p.product_id = wpec_rp.product_id WHERE wpec_p.activate_in_store = 1 ) OR EXISTS( SELECT 1 FROM ec_product AS wpec_p WHERE wpec_p.role_id > 0 AND wpec_p.activate_in_store = 1 )' );
					self::$role_content = ( (int) $found > 0 );
					if ( null !== $found ) {
						set_transient( self::ROLE_CONTENT_KEY, self::$role_content ? 'yes' : 'no', HOUR_IN_SECONDS );
					}
				}
			}
			/**
			 * Whether the store's product lists can differ per customer role ( role prices, role-only products ). True keeps
			 * pages with the Products, Product Carousel and Product Categories widgets out of page caches.
			 *
			 * @since 6.0.2
			 *
			 * @param bool $role_content What the store's products say.
			 */
			return (bool) apply_filters( 'wp_easycart_elementor_role_content', self::$role_content );
		}

		/**
		 * Keep this page out of page caches when the store's product lists differ per customer role ( role_content() ).
		 *
		 * @since 6.0.2
		 *
		 * @return bool Whether the page was kept out.
		 */
		public static function role_no_cache() {
			if ( ! self::role_content() ) {
				return false;
			}
			if ( function_exists( 'wp_easycart_product_details_no_cache' ) ) {
				wp_easycart_product_details_no_cache();
			}
			return true;
		}

		/**
		 * Drop role_content()'s kept answer ( product hooks, see WP_EasyCart_Elementor_Shop::init() ).
		 *
		 * @since 6.0.2
		 */
		public static function forget_role_content() {
			self::$role_content = null;
			delete_transient( self::ROLE_CONTENT_KEY );
		}

		/**
		 * The list's "Products shown" rule ( ec_filter's product_status ) for the counts beside its filters: on sale and in stock
		 * narrow the list while the shopper filters; featured needs nothing ( a filter link turns it off ).
		 *
		 * @param string $status Status ( on_sale | in_stock | other ).
		 * @param string $alias  Table alias of ec_product.
		 * @return string SQL starting with ' AND', or ''.
		 */
		public static function status_sql( $status, $alias ) {
			$a = preg_replace( '/[^a-z0-9_]/i', '', (string) $alias );
			if ( 'on_sale' === $status ) {
				return ' AND ' . $a . '.list_price > ' . $a . '.price';
			}
			if ( 'in_stock' === $status ) {
				return ' AND ( ' . $a . '.stock_quantity > 0 OR ( ' . $a . '.show_stock_quantity = 0 AND ' . $a . '.use_optionitem_quantity_tracking = 0 ) OR ' . $a . '.allow_backorders = 1 )';
			}
			return '';
		}

		/**
		 * The shopper's pickup location when the store hides products it does not carry ( ec_filter's rule ), else 0.
		 *
		 * @return int
		 */
		public static function location_id() {
			if ( get_option( 'ec_option_pickup_enable_locations' ) && get_option( 'ec_option_pickup_location_select_enabled' ) && 2 === (int) get_option( 'ec_option_pickup_location_unavailable' ) && isset( $GLOBALS['ec_cart_data']->cart_data->pickup_location ) ) {
				return (int) $GLOBALS['ec_cart_data']->cart_data->pickup_location;
			}
			return 0;
		}

		/**
		 * The most sold products ( approved orders, refunds taken off ), kept for six hours.
		 *
		 * @return int[]
		 */
		public static function best_seller_ids() {
			$ids = get_transient( 'wpec_el_best_sellers' );
			if ( ! is_array( $ids ) ) {
				global $wpdb;
				$rows = $wpdb->get_col(
					$wpdb->prepare(
						'SELECT ec_orderdetail.product_id FROM ec_orderdetail
						INNER JOIN ec_order ON ec_order.order_id = ec_orderdetail.order_id
						INNER JOIN ec_orderstatus ON ec_orderstatus.status_id = ec_order.orderstatus_id
						WHERE ec_orderstatus.is_approved = 1 AND ec_orderdetail.product_id > 0
						GROUP BY ec_orderdetail.product_id
						HAVING SUM( ec_orderdetail.quantity - ec_orderdetail.refunded_quantity ) > 0
						ORDER BY SUM( ec_orderdetail.quantity - ec_orderdetail.refunded_quantity ) DESC
						LIMIT %d',
						self::MAX_IDS
					)
				);
				$ids  = self::ids( $rows );
				set_transient( 'wpec_el_best_sellers', $ids, 6 * HOUR_IN_SECONDS );
			}
			/**
			 * The best-selling products, most sold first.
			 *
			 * @since 6.0.2
			 *
			 * @param int[] $ids Product ids.
			 */
			return self::ids( apply_filters( 'wp_easycart_elementor_best_seller_ids', $ids ) );
		}

		/**
		 * Products related to a product: its own featured products, then products that share a category with it.
		 *
		 * @param int $product_id Product id.
		 * @return int[]
		 */
		public static function related_ids( $product_id ) {
			global $wpdb;
			$product_id = (int) $product_id;
			if ( ! $product_id ) {
				return array();
			}
			$ids      = array();
			$featured = $wpdb->get_row( $wpdb->prepare( 'SELECT featured_product_id_1, featured_product_id_2, featured_product_id_3, featured_product_id_4 FROM ec_product WHERE product_id = %d', $product_id ), ARRAY_N );
			if ( is_array( $featured ) ) {
				$ids = array_merge( $ids, $featured );
			}
			$shared = $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT wpec_b.product_id FROM ec_categoryitem AS wpec_a INNER JOIN ec_categoryitem AS wpec_b ON wpec_b.category_id = wpec_a.category_id WHERE wpec_a.product_id = %d AND wpec_b.product_id <> %d LIMIT 60', $product_id, $product_id ) );
			$ids    = array_diff( self::ids( array_merge( $ids, (array) $shared ) ), array( $product_id ) );
			/**
			 * Products related to a product, in the order they show.
			 *
			 * @since 6.0.2
			 *
			 * @param int[] $ids        Product ids.
			 * @param int   $product_id The product.
			 */
			return self::ids( apply_filters( 'wp_easycart_elementor_related_product_ids', array_values( $ids ), $product_id ) );
		}

		/**
		 * The first category a product is in, or 0.
		 *
		 * @param int $product_id Product id.
		 * @return int
		 */
		public static function first_category_id( $product_id ) {
			global $wpdb;
			return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT wpec_ci.category_id FROM ec_categoryitem AS wpec_ci INNER JOIN ec_category AS wpec_c ON wpec_c.category_id = wpec_ci.category_id AND wpec_c.is_active = 1 WHERE wpec_ci.product_id = %d ORDER BY wpec_ci.categoryitem_id ASC LIMIT 1', (int) $product_id ) );
		}

		/**
		 * Positive, unique integer ids in their order ( at most MAX_IDS ).
		 *
		 * @param mixed $value Array, comma list or single id.
		 * @return int[]
		 */
		public static function ids( $value ) {
			if ( is_string( $value ) || is_numeric( $value ) ) {
				$value = explode( ',', (string) $value );
			}
			$ids = array();
			foreach ( (array) $value as $id ) {
				if ( is_array( $id ) || is_object( $id ) ) {
					continue;
				}
				$id = absint( $id );
				if ( $id && ! in_array( $id, $ids, true ) ) {
					$ids[] = $id;
				}
				if ( count( $ids ) >= self::MAX_IDS ) {
					break;
				}
			}
			return $ids;
		}

		/**
		 * An integer setting kept in bounds.
		 *
		 * @param mixed $value    Value ( a number, or Elementor's { size } array ).
		 * @param int   $min      Lowest.
		 * @param int   $max      Highest.
		 * @param int   $fallback Used when the value is empty.
		 * @return int
		 */
		public static function clamp( $value, $min, $max, $fallback ) {
			if ( is_array( $value ) ) {
				$value = isset( $value['size'] ) ? $value['size'] : '';
			}
			if ( '' === $value || null === $value || ! is_numeric( $value ) ) {
				return (int) $fallback;
			}
			return max( (int) $min, min( (int) $max, (int) $value ) );
		}
	}

endif;
