<?php
/**
 * EasyCart products in Elementor Pro's Loop Grid and Loop Carousel ( 6.0.2 ).
 *
 * Every EasyCart product is a public ec_store post ( "Store Items" ), so a Loop Grid can list them; categories and
 * manufacturers are ec_store posts too, and a plain "Store Items" loop mixes them in. A Query ID fixes that: typed into the
 * loop's Query › Query ID, it runs EasyCart's own product query and hands the loop the matching product posts, in the store's
 * order:
 *
 *   wpec_products              every product the shopper may see
 *   wpec_featured              products shown on the store's front page ( "Featured" )
 *   wpec_on_sale               products with a regular price above the price
 *   wpec_new                   newest products first
 *   wpec_best_sellers          most sold ( paid orders ), best first
 *   wpec_related               the product's featured products, then products in the same categories
 *   wpec_current_category      the products of the category page it is on ( alias: wpec_category )
 *   wpec_current_manufacturer  the products of the manufacturer page it is on
 *
 * Inactive products, products limited to a customer role the shopper doesn't have, and every product when the store is
 * closed to the shopper ( Settings › Products › Who can view the store ) are always left out. Widgets and dynamic tags in
 * the Loop Item then show each product: Elementor Pro makes each product post the current post.
 *
 * The hooks only fire with Elementor Pro ( elementor/query/{id} fires on WP_Query in the Posts, Portfolio and Loop widgets and
 * the V4 atomic Loop ); the handlers never run a WP_Query themselves ( the hook fires inside one ).
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Dynamic_Loop' ) ) :

	/**
	 * Loop Grid Query IDs.
	 */
	final class WP_EasyCart_Elementor_Dynamic_Loop {

		/**
		 * Best sellers kept per transient ( product ids, refreshed every 6 hours ).
		 */
		const BEST_SELLERS_LIMIT = 100;

		/**
		 * Related products at most.
		 */
		const RELATED_LIMIT = 48;

		/**
		 * Hooks ( once ).
		 */
		public static function init() {
			static $done = false;
			if ( $done ) {
				return;
			}
			$done = true;
			foreach ( self::query_id_keys( true ) as $query_id ) {
				add_action( 'elementor/query/' . $query_id, array( __CLASS__, 'filter_query' ), 10, 2 );
			}
			add_action( 'elementor/element/loop-grid/section_query/before_section_end', array( __CLASS__, 'add_panel_note' ) );
			add_action( 'elementor/element/loop-carousel/section_query/before_section_end', array( __CLASS__, 'add_panel_note' ) );
		}

		/**
		 * The Query IDs, untranslated ( 6.0.2 ). init() runs while plugins load, and translating there makes WordPress 6.7 log
		 * "_load_textdomain_just_in_time was called incorrectly"; the descriptions are only for the panel ( query_ids() ).
		 *
		 * @param bool $with_aliases Include the alias names.
		 * @return string[]
		 */
		public static function query_id_keys( $with_aliases = false ) {
			$keys = array( 'wpec_products', 'wpec_featured', 'wpec_on_sale', 'wpec_new', 'wpec_best_sellers', 'wpec_related', 'wpec_current_category', 'wpec_current_manufacturer' );
			if ( $with_aliases ) {
				$keys[] = 'wpec_category';
			}
			return $keys;
		}

		/**
		 * The Query IDs and what each one shows ( the same keys as query_id_keys() ).
		 *
		 * @param bool $with_aliases Include the alias names.
		 * @return array Query ID => description.
		 */
		public static function query_ids( $with_aliases = false ) {
			$ids = array(
				'wpec_products'             => __( 'Every product the shopper may see, in your store\'s order', 'wp-easycart' ),
				'wpec_featured'             => __( 'Featured products (shown on the store\'s front page)', 'wp-easycart' ),
				'wpec_on_sale'              => __( 'Products on sale', 'wp-easycart' ),
				'wpec_new'                  => __( 'Newest products first', 'wp-easycart' ),
				'wpec_best_sellers'         => __( 'Best sellers, most sold first', 'wp-easycart' ),
				'wpec_related'              => __( 'Related to the product of the page (its featured products, then the same categories)', 'wp-easycart' ),
				'wpec_current_category'     => __( 'Products of the category page it is on', 'wp-easycart' ),
				'wpec_current_manufacturer' => __( 'Products of the manufacturer page it is on', 'wp-easycart' ),
			);
			if ( $with_aliases ) {
				$ids['wpec_category'] = $ids['wpec_current_category'];
			}
			return $ids;
		}

		/**
		 * Fills the loop's query with the products of its Query ID ( elementor/query/{id} ).
		 *
		 * @param WP_Query $query  The loop's query ( before it runs ).
		 * @param object   $widget The loop widget ( unused ).
		 */
		public static function filter_query( $query, $widget = null ) {
			unset( $widget );
			if ( ! is_object( $query ) || ! method_exists( $query, 'set' ) ) {
				return;
			}
			$query_id = str_replace( 'elementor/query/', '', (string) current_action() );
			if ( 'wpec_category' === $query_id ) {
				$query_id = 'wpec_current_category';
			}
			$post_ids = self::post_ids( $query_id );
			/**
			 * The product posts a WP EasyCart Query ID gives an Elementor loop, in order.
			 *
			 * @since 6.0.2
			 *
			 * @param int[]  $post_ids ec_store post ids.
			 * @param string $query_id Query ID.
			 */
			$post_ids = array_values( array_filter( array_map( 'absint', (array) apply_filters( 'wp_easycart_elementor_loop_post_ids', $post_ids, $query_id ) ) ) );

			$query->set( 'post_type', 'ec_store' );
			$query->set( 'post__in', $post_ids ? $post_ids : array( 0 ) );
			$query->set( 'orderby', 'post__in' );
			$query->set( 'ignore_sticky_posts', true );
		}

		/**
		 * The ec_store post ids for a Query ID.
		 *
		 * @param string $query_id Query ID ( without aliases ).
		 * @return int[]
		 */
		public static function post_ids( $query_id ) {
			if ( ! WP_EasyCart_Elementor_Dynamic::store_visible() ) {
				return array();
			}
			global $wpdb;
			/**
			 * Most products a WP EasyCart Query ID hands an Elementor loop.
			 *
			 * @since 6.0.2
			 *
			 * @param int    $limit    Products.
			 * @param string $query_id Query ID.
			 */
			$limit   = max( 1, (int) apply_filters( 'wp_easycart_elementor_loop_limit', 5000, $query_id ) );
			$visible = self::visible_sql();
			$order   = ' ORDER BY product.sort_position ASC, product.price ASC, product.product_id ASC';
			/* The store's default sort ( Settings › Products ), as the Shop widget's lists; the review sort needs a join these
			 * queries do not have, so it keeps the store's own order. */
			$filter = (int) get_option( 'ec_option_default_store_filter' );
			if ( $filter && 6 !== $filter && class_exists( 'WP_EasyCart_Elementor_Shop_Query' ) && method_exists( 'WP_EasyCart_Elementor_Shop_Query', 'store_order_sql' ) ) {
				$order = WP_EasyCart_Elementor_Shop_Query::store_order_sql( $filter );
			}

			switch ( $query_id ) {
				case 'wpec_products':
					$rows = $wpdb->get_col( $wpdb->prepare( 'SELECT product.post_id FROM ec_product AS product WHERE ' . $visible . $order . ' LIMIT %d', $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $visible and $order are built from fixed strings and prepared values.
					break;
				case 'wpec_featured':
					$rows = $wpdb->get_col( $wpdb->prepare( 'SELECT product.post_id FROM ec_product AS product WHERE product.show_on_startup = 1 AND ' . $visible . $order . ' LIMIT %d', $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $visible and $order are built from fixed strings and prepared values.
					break;
				case 'wpec_on_sale':
					$rows = $wpdb->get_col( $wpdb->prepare( 'SELECT product.post_id FROM ec_product AS product WHERE product.list_price > product.price AND ' . $visible . $order . ' LIMIT %d', $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $visible and $order are built from fixed strings and prepared values.
					break;
				case 'wpec_new':
					$rows = $wpdb->get_col( $wpdb->prepare( 'SELECT product.post_id FROM ec_product AS product WHERE ' . $visible . ' ORDER BY product.added_to_db_date DESC, product.product_id DESC LIMIT %d', $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $visible is built from fixed strings and prepared values.
					break;
				case 'wpec_best_sellers':
					$rows = self::best_seller_posts( $visible );
					break;
				case 'wpec_related':
					$rows = self::related_posts( $visible, $order );
					break;
				case 'wpec_current_category':
					$category = function_exists( 'wp_easycart_elementor_context' ) ? wp_easycart_elementor_context()->category() : null;
					$rows     = is_object( $category ) ? $wpdb->get_col( $wpdb->prepare( 'SELECT product.post_id FROM ec_product AS product WHERE product.product_id IN ( SELECT ec_categoryitem.product_id FROM ec_categoryitem WHERE ec_categoryitem.category_id = %d ) AND ' . $visible . $order . ' LIMIT %d', (int) $category->category_id, $limit ) ) : array(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $visible and $order are built from fixed strings and prepared values.
					break;
				case 'wpec_current_manufacturer':
					/* The manufacturer page's, else ( on a product page ) the product's, as the manufacturer widgets do. */
					if ( function_exists( 'wp_easycart_elementor_templates_manufacturer' ) ) {
						$manufacturer = wp_easycart_elementor_templates_manufacturer( array( 'allow_sample' => false ) );
					} else {
						$manufacturer = function_exists( 'wp_easycart_elementor_context' ) ? wp_easycart_elementor_context()->manufacturer() : null;
					}
					$rows = is_object( $manufacturer ) ? $wpdb->get_col( $wpdb->prepare( 'SELECT product.post_id FROM ec_product AS product WHERE product.manufacturer_id = %d AND ' . $visible . $order . ' LIMIT %d', (int) $manufacturer->manufacturer_id, $limit ) ) : array(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $visible and $order are built from fixed strings and prepared values.
					break;
				default:
					$rows = array();
			}
			$post_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $rows ) ) ) );
			if ( ! $post_ids && 'wpec_products' !== $query_id && WP_EasyCart_Elementor_Dynamic::is_editor() ) {
				/* The editor shows sample cards where the page has nothing to match ( a template being designed, no sales yet ). */
				$post_ids = array_values( array_filter( array_map( 'absint', (array) $wpdb->get_col( 'SELECT product.post_id FROM ec_product AS product WHERE ' . $visible . $order . ' LIMIT 12' ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $visible and $order are built from fixed strings and prepared values.
			}
			return $post_ids;
		}

		/**
		 * The WHERE part every Query ID shares: active products with a store page, and the rules of the store's own product
		 * lists ( customer role, hide out of stock, a pickup location that hides what it does not carry ).
		 *
		 * @return string
		 */
		private static function visible_sql() {
			global $wpdb;
			if ( class_exists( 'WP_EasyCart_Elementor_Shop_Query' ) && method_exists( 'WP_EasyCart_Elementor_Shop_Query', 'visibility_sql' ) ) {
				return 'product.activate_in_store = 1 AND product.post_id > 0' . WP_EasyCart_Elementor_Shop_Query::visibility_sql( 'product' );
			}
			$role_id = ( isset( $GLOBALS['ec_user'] ) && is_object( $GLOBALS['ec_user'] ) && ! empty( $GLOBALS['ec_user']->role_id ) ) ? (int) $GLOBALS['ec_user']->role_id : 0;
			$role    = $role_id ? $wpdb->prepare( '( product.role_id = 0 OR product.role_id = %d )', $role_id ) : '( product.role_id = 0 OR product.role_id = -1 )';
			return 'product.activate_in_store = 1 AND product.post_id > 0 AND ' . $role;
		}

		/**
		 * Best sellers: products by units sold in paid orders ( product ids kept 6 hours ), then limited to visible products.
		 *
		 * @param string $visible visible_sql().
		 * @return int[] Post ids, best first.
		 */
		private static function best_seller_posts( $visible ) {
			global $wpdb;
			/* One ranking for every list ( refunds taken off ): the Shop widget fills the same transient. */
			$product_ids = ( class_exists( 'WP_EasyCart_Elementor_Shop_Query' ) && method_exists( 'WP_EasyCart_Elementor_Shop_Query', 'best_seller_ids' ) ) ? WP_EasyCart_Elementor_Shop_Query::best_seller_ids() : get_transient( 'wpec_el_best_sellers' );
			if ( ! is_array( $product_ids ) ) {
				$product_ids = array_map( 'absint', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT ec_orderdetail.product_id FROM ec_orderdetail INNER JOIN ec_order ON ec_order.order_id = ec_orderdetail.order_id INNER JOIN ec_orderstatus ON ec_orderstatus.status_id = ec_order.orderstatus_id WHERE ec_orderstatus.is_approved = 1 AND ec_orderdetail.product_id > 0 GROUP BY ec_orderdetail.product_id ORDER BY SUM( ec_orderdetail.quantity ) DESC LIMIT %d', self::BEST_SELLERS_LIMIT ) ) );
				set_transient( 'wpec_el_best_sellers', $product_ids, 6 * HOUR_IN_SECONDS );
			}
			$product_ids = array_values( array_filter( $product_ids ) );
			if ( ! $product_ids ) {
				return array();
			}
			$in   = implode( ',', array_map( 'absint', $product_ids ) );
			$rows = $wpdb->get_results( 'SELECT product.product_id, product.post_id FROM ec_product AS product WHERE product.product_id IN ( ' . $in . ' ) AND ' . $visible ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $in is a list of absint() ids, $visible is built from fixed strings and prepared values.
			$post = array();
			foreach ( (array) $rows as $row ) {
				$post[ (int) $row->product_id ] = (int) $row->post_id;
			}
			$out = array();
			foreach ( $product_ids as $product_id ) {
				if ( isset( $post[ $product_id ] ) ) {
					$out[] = $post[ $product_id ];
				}
			}
			return $out;
		}

		/**
		 * Related products: the product's own featured products first, then products in its categories.
		 *
		 * @param string $visible visible_sql().
		 * @param string $order   The store's order.
		 * @return int[] Post ids.
		 */
		private static function related_posts( $visible, $order ) {
			global $wpdb;
			$product = function_exists( 'wp_easycart_elementor_context' ) ? wp_easycart_elementor_context()->product() : null;
			if ( ! is_object( $product ) || empty( $product->product_id ) ) {
				return array();
			}
			$product_id = (int) $product->product_id;
			$featured   = $wpdb->get_row( $wpdb->prepare( 'SELECT featured_product_id_1, featured_product_id_2, featured_product_id_3, featured_product_id_4 FROM ec_product WHERE product_id = %d', $product_id ), ARRAY_A );
			$out        = array();
			$picked     = array_values( array_filter( array_map( 'absint', (array) $featured ) ) );
			$picked     = array_values( array_diff( $picked, array( $product_id ) ) );
			if ( $picked ) {
				$in   = implode( ',', $picked );
				$rows = $wpdb->get_results( 'SELECT product.product_id, product.post_id FROM ec_product AS product WHERE product.product_id IN ( ' . $in . ' ) AND ' . $visible ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $in is a list of absint() ids, $visible is built from fixed strings and prepared values.
				$post = array();
				foreach ( (array) $rows as $row ) {
					$post[ (int) $row->product_id ] = (int) $row->post_id;
				}
				foreach ( $picked as $id ) {
					if ( isset( $post[ $id ] ) ) {
						$out[] = $post[ $id ];
					}
				}
			}
			$more = $wpdb->get_col( $wpdb->prepare( 'SELECT product.post_id FROM ec_product AS product WHERE product.product_id <> %d AND product.product_id IN ( SELECT ec_categoryitem.product_id FROM ec_categoryitem WHERE ec_categoryitem.category_id IN ( SELECT own.category_id FROM ec_categoryitem AS own WHERE own.product_id = %d ) ) AND ' . $visible . $order . ' LIMIT %d', $product_id, $product_id, self::RELATED_LIMIT ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $visible and $order are built from fixed strings and prepared values.
			foreach ( (array) $more as $post_id ) {
				$post_id = (int) $post_id;
				if ( $post_id && ! in_array( $post_id, $out, true ) && count( $out ) < self::RELATED_LIMIT ) {
					$out[] = $post_id;
				}
			}
			return $out;
		}

		/**
		 * A note in the loop's Query section while its source is Store Items: the Query IDs to type.
		 *
		 * @param object $element The Loop Grid / Loop Carousel widget.
		 */
		public static function add_panel_note( $element ) {
			if ( ! is_object( $element ) || ! method_exists( $element, 'add_control' ) || ! class_exists( '\Elementor\Controls_Manager' ) ) {
				return;
			}
			$items = '';
			foreach ( self::query_ids() as $query_id => $label ) {
				$items .= '<br><code>' . esc_html( $query_id ) . '</code> ' . esc_html( $label );
			}
			$element->add_control(
				'wpec_loop_query_note',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => '<strong>' . esc_html__( 'WP EasyCart products', 'wp-easycart' ) . '</strong><br>' . esc_html__( 'Type one of these in Query ID to show only products, the way your store lists them:', 'wp-easycart' ) . $items,
					'content_classes' => 'elementor-descriptor',
					'separator'       => 'before',
					'condition'       => array( 'post_query_post_type' => 'ec_store' ),
				)
			);
		}
	}

endif;
