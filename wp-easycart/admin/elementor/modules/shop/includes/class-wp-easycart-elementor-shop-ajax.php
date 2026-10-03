<?php
/**
 * Storefront endpoints of the shop widgets ( 6.0.2 ).
 *
 * All four are public and read-only, and none needs a nonce: the pages that call them are often served from a page cache
 * long after a nonce printed into them expired ( review A13 ), and they change nothing:
 * - wp_easycart_el_products   next page of a Products widget ( "Load more" ), from the query the page printed;
 * - wp_easycart_el_quick_view a product's quick view ( its add button carries a fresh nonce );
 * - wp_easycart_el_search     product suggestions for the Product Search widget;
 * - wp_easycart_el_cart_nonce a fresh add-to-cart nonce for a card whose page came from a cache ( products the visitor can see ).
 * Every input is sanitized and kept in bounds, visibility follows the store ( active products, role-only products, the
 * store's "who can view" rule ), and the answers are what any visitor can already read on the storefront.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Shop_Ajax' ) ) :

	/**
	 * The shop widgets' public endpoints.
	 */
	class WP_EasyCart_Elementor_Shop_Ajax {

		/**
		 * Hooks.
		 */
		public static function init() {
			foreach ( array( 'products', 'quick_view', 'search', 'cart_nonce' ) as $name ) {
				add_action( 'wp_ajax_wp_easycart_el_' . $name, array( __CLASS__, $name ) );
				add_action( 'wp_ajax_nopriv_wp_easycart_el_' . $name, array( __CLASS__, $name ) );
			}
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- public, read-only storefront endpoints: see the file comment ( no nonce, so they keep working on cached pages ).

		/**
		 * Load more: { html, more, page, count }.
		 */
		public static function products() {
			$query = isset( $_GET['query'] ) ? json_decode( wp_unslash( $_GET['query'] ), true ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON, every value checked in WP_EasyCart_Elementor_Shop_Query::sanitize().
			$card  = isset( $_GET['card'] ) ? json_decode( wp_unslash( $_GET['card'] ), true ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON, every value checked in WP_EasyCart_Elementor_Shop_Card::sanitize().
			$query = WP_EasyCart_Elementor_Shop_Query::sanitize( $query );
			if ( ! $query ) {
				wp_send_json_error( null, 400 );
			}
			$query['page'] = WP_EasyCart_Elementor_Shop_Query::clamp( isset( $_GET['page'] ) ? absint( $_GET['page'] ) : 2, 1, 1000, 2 );
			$card          = WP_EasyCart_Elementor_Shop_Card::sanitize( $card );
			$card['eager'] = 0;
			if ( WP_EasyCart_Elementor_Shop::restricted() ) {
				wp_send_json_success(
					array(
						'html'  => '',
						'more'  => false,
						'page'  => $query['page'],
						'count' => 0,
					)
				);
			}
			WP_EasyCart_Elementor_Shop_Query::role_no_cache(); /* Role prices and role-only products differ per shopper. */
			$result = WP_EasyCart_Elementor_Shop_Query::run( $query );
			ob_start();
			WP_EasyCart_Elementor_Shop_Card::render_list( $result['products'], $card );
			$html = (string) ob_get_clean();
			wp_send_json_success(
				array(
					'html'  => $html,
					'more'  => ( $query['page'] < $result['pages'] ),
					'page'  => $query['page'],
					'count' => count( $result['products'] ),
				)
			);
		}

		/**
		 * Quick view: { html, title }.
		 */
		public static function quick_view() {
			$product = self::product( isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0 );
			if ( ! $product ) {
				wp_send_json_error( null, 404 );
			}
			wp_send_json_success(
				array(
					'html'  => WP_EasyCart_Elementor_Shop_Card::quick_view_html( $product ),
					'title' => trim( wp_strip_all_tags( html_entity_decode( (string) $product->title, ENT_QUOTES, 'UTF-8' ) ) ),
				)
			);
		}

		/**
		 * Product suggestions: { items: [ { title, url, image, price, regular } ], total }.
		 */
		public static function search() {
			$term = isset( $_GET['q'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['q'] ) ) ) : '';
			if ( '' === $term || ( function_exists( 'mb_strlen' ) ? mb_strlen( $term ) : strlen( $term ) ) > 100 || WP_EasyCart_Elementor_Shop::restricted() ) {
				wp_send_json(
					array(
						'items' => array(),
						'total' => 0,
					)
				);
			}
			$limit  = WP_EasyCart_Elementor_Shop_Query::clamp( isset( $_GET['limit'] ) ? absint( $_GET['limit'] ) : 6, 1, 10, 6 );
			$images = ! isset( $_GET['images'] ) || '0' !== $_GET['images'];
			$prices = ! isset( $_GET['prices'] ) || '0' !== $_GET['prices'];

			$result = self::search_products( $term, $limit );
			$items  = array();
			foreach ( $result['products'] as $product ) {
				$item = array(
					'title' => trim( wp_strip_all_tags( html_entity_decode( (string) $product->title, ENT_QUOTES, 'UTF-8' ) ) ),
					'url'   => esc_url_raw( $product->get_product_link() ),
					'image' => '',
					'price' => '',
				);
				if ( $images ) {
					$image = WP_EasyCart_Elementor_Shop_Card::images( $product, 1 );
					if ( isset( $image[0] ) ) {
						$item['image'] = ! empty( $image[0]['id'] ) ? (string) wp_get_attachment_image_url( (int) $image[0]['id'], 'thumbnail' ) : esc_url_raw( $image[0]['url'] );
					}
				}
				if ( $prices ) {
					$item['price'] = trim( html_entity_decode( wp_strip_all_tags( WP_EasyCart_Elementor_Shop_Card::price_html( $product ) ), ENT_QUOTES, 'UTF-8' ) );
				}
				$items[] = $item;
			}
			wp_send_json(
				array(
					'items' => $items,
					'total' => $result['total'],
				)
			);
		}

		/**
		 * A fresh add-to-cart nonce: { nonce }. Only for a product this visitor can see ( self::product(): active, their
		 * customer role, the store's "who can view" rule ); 404 otherwise.
		 */
		public static function cart_nonce() {
			$product_id = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0;
			if ( ! $product_id ) {
				wp_send_json_error( null, 400 );
			}
			if ( ! self::product( $product_id ) ) {
				wp_send_json_error( null, 404 );
			}
			wp_send_json_success( array( 'nonce' => wp_create_nonce( 'wp-easycart-add-to-cart-' . $product_id ) ) );
		}

		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		/**
		 * Products whose title ( and model number, when the store searches it ) contains the term, best matches first.
		 *
		 * @param string $term  Search text.
		 * @param int    $limit Most products.
		 * @return array array( 'products' => ec_product[], 'total' => int ).
		 */
		public static function search_products( $term, $limit ) {
			global $wpdb;
			$empty = array(
				'products' => array(),
				'total'    => 0,
			);
			if ( ! class_exists( 'ec_db' ) || ! class_exists( 'ec_product' ) ) {
				return $empty;
			}
			$like    = '%' . $wpdb->esc_like( $term ) . '%';
			$columns = array();
			if ( get_option( 'ec_option_search_title', 1 ) ) {
				$columns[] = $wpdb->prepare( 'product.title LIKE %s', $like );
			}
			if ( get_option( 'ec_option_search_model_number', 1 ) ) {
				$columns[] = $wpdb->prepare( 'product.model_number LIKE %s', $like );
			}
			if ( empty( $columns ) ) {
				$columns[] = $wpdb->prepare( 'product.title LIKE %s', $like );
			}
			$where = 'WHERE product.activate_in_store = 1 AND ( ' . implode( ' OR ', $columns ) . ' )' . WP_EasyCart_Elementor_Shop_Query::visibility_sql( 'product' );
			$order = ' ORDER BY ' . $wpdb->prepare( 'CASE WHEN product.title = %s THEN 0 WHEN product.title LIKE %s THEN 1 ELSE 2 END', $term, $wpdb->esc_like( $term ) . '%' ) . ', product.title ASC';
			$db    = new ec_db();
			$rows  = $db->get_product_list( $where, $order, ' LIMIT 0, ' . (int) $limit, '', 'wpeasycart-elementor-search' );
			if ( ! is_array( $rows ) || empty( $rows ) ) {
				return $empty;
			}
			$products = array();
			foreach ( $rows as $index => $row ) {
				$products[] = new ec_product( $row, 1, 0, 1, $index );
			}
			return array(
				'products' => $products,
				'total'    => isset( $rows[0]['product_count'] ) ? (int) $rows[0]['product_count'] : count( $rows ),
			);
		}

		/**
		 * One product the storefront may show, or null: active, and shown to this shopper as the lists show it ( the role-only,
		 * hidden out of stock and pickup location rules of WP_EasyCart_Elementor_Shop_Query::visibility_sql() ).
		 *
		 * @param int $product_id Product id.
		 * @return ec_product|null
		 */
		public static function product( $product_id ) {
			global $wpdb;
			if ( ! $product_id || ! class_exists( 'ec_db' ) || ! class_exists( 'ec_product' ) || WP_EasyCart_Elementor_Shop::restricted() ) {
				return null;
			}
			$db   = new ec_db();
			$rows = $db->get_product_list( $wpdb->prepare( 'WHERE product.product_id = %d AND product.activate_in_store = 1', $product_id ) . WP_EasyCart_Elementor_Shop_Query::visibility_sql( 'product' ), '', '', '', 'wpeasycart-elementor-quick-view' );
			if ( ! is_array( $rows ) || empty( $rows ) ) {
				return null;
			}
			return new ec_product( $rows[0], 1, 0, 1 );
		}
	}

endif;
