<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_api_routes' ) ) :

	final class wp_easycart_admin_api_routes {

		/**
		 * Rows per page when a picker searches ( 6.0.2 ). Select2 asks for the next page as the list scrolls.
		 */
		const PER_PAGE = 30;

		/**
		 * Most rows one request may ask for ( 6.0.2 ).
		 */
		const MAX_PER_PAGE = 100;

		/**
		 * Most ids one lookup answers ( 6.0.2 ). A saved selection is never this long; the Elementor picker keeps any id it is
		 * not told about, so a longer one is not lost.
		 */
		const MAX_IDS = 1000;

		protected static $_instance = null;

		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		public function __construct() {
			add_action( 'rest_api_init', array( $this, 'register_rest_api' ) );
		}

		public function register_rest_api() {
			register_rest_route(
				'wp-easycart/v1',
				'/easycart_product/',
				array(
					'methods' => 'GET',
					'callback' => array( $this, 'wp_eayscart_ajax_select_product' ),
					'permission_callback' => function () {
						return current_user_can( 'edit_posts' );
					},
				),
				true
			);
			register_rest_route(
				'wp-easycart/v1',
				'/easycart_product_cat/',
				array(
					'methods' => 'GET',
					'callback' => array( $this, 'wp_eayscart_ajax_select_product_cat' ),
					'permission_callback' => function () {
						return current_user_can( 'edit_posts' );
					},
				),
				true
			);
			register_rest_route(
				'wp-easycart/v1',
				'/easycart_product_brand/',
				array(
					'methods' => 'GET',
					'callback' => array( $this, 'wp_eayscart_ajax_select_product_brand' ),
					'permission_callback' => function () {
						return current_user_can( 'edit_posts' );
					},
				),
				true
			);
			register_rest_route(
				'wp-easycart/v1',
				'/categories/',
				array(
					'methods' => 'GET',
					'callback' => array( $this, 'wp_eayscart_ajax_select_categories' ),
					'permission_callback' => function () {
						return current_user_can( 'edit_posts' );
					},
				),
				true
			);
			register_rest_route(
				'wp-easycart/v1',
				'/easycart_product_optionsets/',
				array(
					'methods' => 'GET',
					'callback' => array( $this, 'wp_eayscart_ajax_select_optionsets' ),
					'permission_callback' => function () {
						return current_user_can( 'edit_posts' );
					},
				),
				true
			);
			register_rest_route(
				'wp-easycart/v1',
				'/products/categories/',
				array(
					'methods' => 'GET',
					'callback' => array( $this, 'wp_eayscart_ajax_select_products_by_categories' ),
					'permission_callback' => function () {
						return current_user_can( 'edit_posts' );
					},
				),
				true
			);
		}

		/**
		 * One query parameter of the REST request ( already unslashed by the REST server ).
		 *
		 * @since 6.0.2
		 *
		 * @param WP_REST_Request|null $request Request.
		 * @param string               $key     Parameter.
		 * @return mixed Null when missing.
		 */
		private function request_param( $request, $key ) {
			if ( is_object( $request ) && method_exists( $request, 'get_param' ) ) {
				return $request->get_param( $key );
			}
			return null;
		}

		/**
		 * The ids a picker asks for: `ids=1,2,3` or `ids[]=1&ids[]=2` ( any keys ), as unique positive integers in the order
		 * given.
		 *
		 * @since 6.0.2
		 *
		 * @param WP_REST_Request|null $request Request.
		 * @return array|false False when the request has no `ids` ( a search ).
		 */
		private function requested_ids( $request ) {
			$raw = $this->request_param( $request, 'ids' );
			if ( null === $raw ) {
				return false;
			}
			if ( ! is_array( $raw ) ) {
				$raw = explode( ',', (string) $raw );
			}
			$ids = array();
			foreach ( $raw as $value ) {
				if ( ! is_scalar( $value ) ) {
					continue;
				}
				$id = absint( trim( (string) $value ) );
				if ( $id > 0 ) {
					$ids[ $id ] = $id;
				}
			}
			return array_slice( array_values( $ids ), 0, self::MAX_IDS );
		}

		/**
		 * The LIKE pattern for the `s` search, or '' for no search.
		 *
		 * @since 6.0.2
		 *
		 * @param WP_REST_Request|null $request Request.
		 * @return string
		 */
		private function search_like( $request ) {
			global $wpdb;
			$search = $this->request_param( $request, 's' );
			if ( ! is_scalar( $search ) ) {
				return '';
			}
			$search = sanitize_text_field( (string) $search );
			return ( '' === $search ) ? '' : '%' . $wpdb->esc_like( $search ) . '%';
		}

		/**
		 * Page size and offset from `page` and `perpage`. Missing, zero or negative values fall back to page 1 and the
		 * default size, so no request builds `LIMIT x, 0` or a negative LIMIT.
		 *
		 * @since 6.0.2
		 *
		 * @param WP_REST_Request|null $request          Request.
		 * @param int                  $default_per_page Size when `perpage` is not given.
		 * @return array { per_page, offset }
		 */
		private function paging( $request, $default_per_page ) {
			$page     = $this->request_param( $request, 'page' );
			$per_page = $this->request_param( $request, 'perpage' );
			$page     = ( is_scalar( $page ) && (int) $page > 0 ) ? (int) $page : 1;
			$per_page = ( is_scalar( $per_page ) && (int) $per_page > 0 ) ? min( self::MAX_PER_PAGE, (int) $per_page ) : (int) $default_per_page;
			return array( $per_page, ( $page - 1 ) * $per_page );
		}

		/**
		 * A page of rows ( fetched one past the page size ) in the shape Select2 reads: results, and whether there is more.
		 *
		 * @since 6.0.2
		 *
		 * @param array|null $rows     Rows.
		 * @param int        $per_page Page size.
		 * @return array
		 */
		private function page_results( $rows, $per_page ) {
			$rows = is_array( $rows ) ? $rows : array();
			return array(
				'results'    => array_slice( $rows, 0, $per_page ),
				'pagination' => array(
					'more' => ( count( $rows ) > $per_page ),
				),
			);
		}

		/**
		 * Products for the Elementor picker: every product in `ids`, or a page of a title search.
		 *
		 * @param WP_REST_Request|null $request Request.
		 * @return array
		 */
		public function wp_eayscart_ajax_select_product( $request = null ) {
			global $wpdb;

			/* 6.0.2: an `ids` lookup ( the picker showing a saved selection ) returns every id asked for; a search is paged. */
			$ids = $this->requested_ids( $request );
			if ( false !== $ids ) {
				if ( empty( $ids ) ) {
					return array( 'results' => array() );
				}
				$products = $wpdb->get_results( $wpdb->prepare( 'SELECT ec_product.product_id AS id, ec_product.title AS text FROM ec_product WHERE ec_product.product_id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ') ORDER BY ec_product.title ASC', $ids ) );
				return array( 'results' => is_array( $products ) ? $products : array() );
			}

			$like = $this->search_like( $request );

			list( $per_page, $offset ) = $this->paging( $request, self::PER_PAGE );
			if ( '' !== $like ) {
				$products = $wpdb->get_results( $wpdb->prepare( 'SELECT ec_product.product_id AS id, ec_product.title AS text FROM ec_product WHERE ec_product.title LIKE %s ORDER BY ec_product.title ASC LIMIT %d, %d', $like, $offset, $per_page + 1 ) );
			} else {
				$products = $wpdb->get_results( $wpdb->prepare( 'SELECT ec_product.product_id AS id, ec_product.title AS text FROM ec_product ORDER BY ec_product.title ASC LIMIT %d, %d', $offset, $per_page + 1 ) );
			}
			return $this->page_results( $products, $per_page );
		}

		/**
		 * Categories for the Elementor picker: every category in `ids`, or a page of a name search.
		 *
		 * @param WP_REST_Request|null $request Request.
		 * @return array
		 */
		public function wp_eayscart_ajax_select_product_cat( $request = null ) {
			global $wpdb;

			$ids = $this->requested_ids( $request );
			if ( false !== $ids ) {
				if ( empty( $ids ) ) {
					return array( 'results' => array() );
				}
				$categories = $wpdb->get_results( $wpdb->prepare( 'SELECT ec_category.category_id AS id, ec_category.category_name AS text FROM ec_category WHERE ec_category.category_id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ') ORDER BY ec_category.priority DESC', $ids ) );
				return array( 'results' => is_array( $categories ) ? $categories : array() );
			}

			$like = $this->search_like( $request );

			list( $per_page, $offset ) = $this->paging( $request, self::PER_PAGE );
			if ( '' !== $like ) {
				$categories = $wpdb->get_results( $wpdb->prepare( 'SELECT ec_category.category_id AS id, ec_category.category_name AS text FROM ec_category WHERE ec_category.category_name LIKE %s ORDER BY ec_category.priority DESC LIMIT %d, %d', $like, $offset, $per_page + 1 ) );
			} else {
				$categories = $wpdb->get_results( $wpdb->prepare( 'SELECT ec_category.category_id AS id, ec_category.category_name AS text FROM ec_category ORDER BY ec_category.priority DESC LIMIT %d, %d', $offset, $per_page + 1 ) );
			}
			return $this->page_results( $categories, $per_page );
		}

		/**
		 * Manufacturers for the Elementor picker: every manufacturer in `ids`, or a page of a name search.
		 *
		 * @param WP_REST_Request|null $request Request.
		 * @return array
		 */
		public function wp_eayscart_ajax_select_product_brand( $request = null ) {
			global $wpdb;

			$ids = $this->requested_ids( $request );
			if ( false !== $ids ) {
				if ( empty( $ids ) ) {
					return array( 'results' => array() );
				}
				$manufacturers = $wpdb->get_results( $wpdb->prepare( 'SELECT ec_manufacturer.manufacturer_id AS id, ec_manufacturer.`name` AS text FROM ec_manufacturer WHERE ec_manufacturer.manufacturer_id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ') ORDER BY ec_manufacturer.`name` ASC', $ids ) );
				return array( 'results' => is_array( $manufacturers ) ? $manufacturers : array() );
			}

			$like = $this->search_like( $request );

			list( $per_page, $offset ) = $this->paging( $request, self::PER_PAGE );
			if ( '' !== $like ) {
				$manufacturers = $wpdb->get_results( $wpdb->prepare( 'SELECT ec_manufacturer.manufacturer_id AS id, ec_manufacturer.`name` AS text FROM ec_manufacturer WHERE ec_manufacturer.`name` LIKE %s ORDER BY ec_manufacturer.`name` ASC LIMIT %d, %d', $like, $offset, $per_page + 1 ) );
			} else {
				$manufacturers = $wpdb->get_results( $wpdb->prepare( 'SELECT ec_manufacturer.manufacturer_id AS id, ec_manufacturer.`name` AS text FROM ec_manufacturer ORDER BY ec_manufacturer.`name` ASC LIMIT %d, %d', $offset, $per_page + 1 ) );
			}
			return $this->page_results( $manufacturers, $per_page );
		}

		/**
		 * Categories with product counts for the Gutenberg store block.
		 *
		 * @param WP_REST_Request|null $request Request.
		 * @return array
		 */
		public function wp_eayscart_ajax_select_categories( $request = null ) {
			global $wpdb;

			/* Used by the Gutenberg store block ( fields category_id / category_name / total_products; a search shows 15 ). */
			$ids = $this->requested_ids( $request );
			if ( false !== $ids ) {
				if ( empty( $ids ) ) {
					return array( 'results' => array() );
				}
				$categories = $wpdb->get_results( $wpdb->prepare( 'SELECT ec_category.category_id, ec_category.category_name, ( SELECT COUNT( ec_categoryitem.category_id ) FROM ec_categoryitem WHERE ec_categoryitem.category_id = ec_category.category_id ) AS total_products FROM ec_category WHERE ec_category.category_id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ') ORDER BY ec_category.category_name ASC', $ids ) );
				return array( 'results' => is_array( $categories ) ? $categories : array() );
			}

			$like = $this->search_like( $request );

			list( $per_page, $offset ) = $this->paging( $request, 15 );
			if ( '' !== $like ) {
				$categories = $wpdb->get_results( $wpdb->prepare( 'SELECT ec_category.category_id, ec_category.category_name, ( SELECT COUNT( ec_categoryitem.category_id ) FROM ec_categoryitem WHERE ec_categoryitem.category_id = ec_category.category_id ) AS total_products FROM ec_category WHERE ec_category.category_name LIKE %s ORDER BY ec_category.category_name ASC LIMIT %d, %d', $like, $offset, $per_page + 1 ) );
			} else {
				$categories = $wpdb->get_results( $wpdb->prepare( 'SELECT ec_category.category_id, ec_category.category_name, ( SELECT COUNT( ec_categoryitem.category_id ) FROM ec_categoryitem WHERE ec_categoryitem.category_id = ec_category.category_id ) AS total_products FROM ec_category ORDER BY ec_category.category_name ASC LIMIT %d, %d', $offset, $per_page + 1 ) );
			}
			return $this->page_results( $categories, $per_page );
		}

		/**
		 * Option sets for the Elementor picker: every option set in `ids`, or a page of a name search.
		 *
		 * @param WP_REST_Request|null $request Request.
		 * @return array
		 */
		public function wp_eayscart_ajax_select_optionsets( $request = null ) {
			global $wpdb;

			$ids = $this->requested_ids( $request );
			if ( false !== $ids ) {
				if ( empty( $ids ) ) {
					return array( 'results' => array() );
				}
				$options = $wpdb->get_results( $wpdb->prepare( 'SELECT ec_option.option_id AS id, ec_option.option_name AS text FROM ec_option WHERE ec_option.option_id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ') ORDER BY ec_option.option_name ASC', $ids ) );
				return array( 'results' => is_array( $options ) ? $options : array() );
			}

			$like = $this->search_like( $request );

			list( $per_page, $offset ) = $this->paging( $request, self::PER_PAGE );
			/* 6.0.2: not the sets that belong to one product ( fulfillment partners' imports ); a saved selection above still finds them. */
			$shared = class_exists( 'wp_easycart_product_writer' ) ? wp_easycart_product_writer::shared_sets_sql() : '';
			if ( '' !== $like ) {
				$options = $wpdb->get_results( $wpdb->prepare( 'SELECT ec_option.option_id AS id, ec_option.option_name AS text FROM ec_option WHERE ec_option.option_name LIKE %s' . $shared . ' ORDER BY ec_option.option_name ASC LIMIT %d, %d', $like, $offset, $per_page + 1 ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $shared is a fixed clause from shared_sets_sql().
			} else {
				$options = $wpdb->get_results( $wpdb->prepare( 'SELECT ec_option.option_id AS id, ec_option.option_name AS text FROM ec_option WHERE 1=1' . $shared . ' ORDER BY ec_option.option_name ASC LIMIT %d, %d', $offset, $per_page + 1 ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $shared is a fixed clause from shared_sets_sql().
			}
			return $this->page_results( $options, $per_page );
		}

		/**
		 * Products in the given categories, with price and first image, for the Gutenberg store block preview.
		 *
		 * @param WP_REST_Request|null $request Request.
		 * @return array
		 */
		public function wp_eayscart_ajax_select_products_by_categories( $request = null ) {
			global $wpdb;

			/* Used by the Gutenberg store block preview: products in the given categories, 25 unless `perpage` says otherwise. */
			$ids = $this->requested_ids( $request );
			if ( false !== $ids && empty( $ids ) ) {
				return array( 'results' => array() );
			}
			list( $per_page, $offset ) = $this->paging( $request, 25 );

			$sql = 'SELECT DISTINCT ec_product.product_id, ec_product.title, ec_product.image1, ec_product.image2, ec_product.image3, ec_product.image4, ec_product.image5, ec_product.product_images, ec_product.price, ec_product.list_price FROM ec_categoryitem, ec_product WHERE ec_categoryitem.product_id = ec_product.product_id';
			if ( false !== $ids ) {
				$products = $wpdb->get_results( $wpdb->prepare( $sql . ' AND ec_categoryitem.category_id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ') LIMIT %d, %d', array_merge( $ids, array( $offset, $per_page ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql is a fixed string above.
			} else {
				$products = $wpdb->get_results( $wpdb->prepare( $sql . ' LIMIT %d, %d', $offset, $per_page ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql is a fixed string above.
			}
			if ( ! is_array( $products ) ) {
				$products = array();
			}

			$product_count = count( $products );
			for ( $i = 0; $i < $product_count; $i++  ) {
				$products[$i]->price = $GLOBALS['currency']->get_currency_display( $products[$i]->price );
				if( substr( $products[$i]->image1, 0, 7 ) == 'http://' || substr( $products[$i]->image1, 0, 8 ) == 'https://' ){
					$products[$i]->first_image = esc_attr( $products[$i]->image1 );
				}else{
					$products[$i]->first_image = esc_attr( plugins_url( "/wp-easycart-data/products/pics1/" . $products[$i]->image1, EC_PLUGIN_DATA_DIRECTORY ) );
				}
				if ( '' != $products[$i]->product_images ) {
					$product_images = explode( ',', $products[$i]->product_images );
					if( 'image1' == $product_images[0] ) {
						if ( substr( $products[$i]->image1, 0, 7 ) == 'http://' || substr( $products[$i]->image1, 0, 8 ) == 'https://' ){
							$products[$i]->first_image = esc_attr( $products[$i]->image1 );
						} else {
							$products[$i]->first_image = esc_attr( plugins_url( "/wp-easycart-data/products/pics1/" . $products[$i]->image1, EC_PLUGIN_DATA_DIRECTORY ) );
						}
					} else if( 'image2' == $product_images[0] ) {
						if ( substr( $products[$i]->image2, 0, 7 ) == 'http://' || substr( $products[$i]->image2, 0, 8 ) == 'https://' ){
							$products[$i]->first_image = esc_attr( $products[$i]->image2 );
						} else {
							$products[$i]->first_image = esc_attr( plugins_url( "/wp-easycart-data/products/pics2/" . $products[$i]->image2, EC_PLUGIN_DATA_DIRECTORY ) );
						}
					} else if( 'image3' == $product_images[0] ) {
						if ( substr( $products[$i]->image3, 0, 7 ) == 'http://' || substr( $products[$i]->image3, 0, 8 ) == 'https://' ){
							$products[$i]->first_image = esc_attr( $products[$i]->image3 );
						} else {
							$products[$i]->first_image = esc_attr( plugins_url( "/wp-easycart-data/products/pics3/" . $products[$i]->image3, EC_PLUGIN_DATA_DIRECTORY ) );
						}
					} else if( 'image4' == $product_images[0] ) {
						if ( substr( $products[$i]->image4, 0, 7 ) == 'http://' || substr( $products[$i]->image4, 0, 8 ) == 'https://' ){
							$products[$i]->first_image = esc_attr( $products[$i]->image4 );
						} else {
							$products[$i]->first_image = esc_attr( plugins_url( "/wp-easycart-data/products/pics4/" . $products[$i]->image4, EC_PLUGIN_DATA_DIRECTORY ) );
						}
					} else if( 'image5' == $product_images[0] ) {
						if ( substr( $products[$i]->image5, 0, 7 ) == 'http://' || substr( $products[$i]->image5, 0, 8 ) == 'https://' ){
							$products[$i]->first_image = esc_attr( $products[$i]->image5 );
						} else {
							$products[$i]->first_image = esc_attr( plugins_url( "/wp-easycart-data/products/pics5/" . $products[$i]->image5, EC_PLUGIN_DATA_DIRECTORY ) );
						}
					} else if( 'image:' == substr( $product_images[0], 0, 6 ) ) {
						$products[$i]->first_image = esc_attr( substr( $product_images[0], 6, strlen( $product_images[0] ) - 6 ) );
					} else if( 'video:' == substr( $product_images[0], 0, 6 ) ) {
						$video_str = substr( $product_images[0], 6, strlen( $product_images[0] ) - 6 );
						$video_arr = explode( ':::', $video_str );
						if ( count( $video_arr ) >= 2 ) {
							$products[$i]->first_image = esc_attr( $video_arr[1] );
						}
					} else if( 'youtube:' == substr( $product_images[0], 0, 8 ) ) {
						$youtube_video_str = substr( $product_images[0], 8, strlen( $product_images[0] ) - 8 );
						$youtube_video_arr = explode( ':::', $youtube_video_str );
						if ( count( $youtube_video_arr ) >= 2 ) {
							$products[$i]->first_image = esc_attr( $youtube_video_arr[1] );
						}
					} else if( 'vimeo:' == substr( $product_images[0], 0, 6 ) ) {
						$vimeo_video_str = substr( $product_images[0], 6, strlen( $product_images[0] ) - 6 );
						$vimeo_video_arr = explode( ':::', $vimeo_video_str );
						if ( count( $vimeo_video_arr ) >= 2 ) {
							$products[$i]->first_image = esc_attr( $vimeo_video_arr[1] );
						}
					} else {
						$product_image_media = wp_get_attachment_image_src( $product_images[0], 'large' );
						if( $product_image_media && isset( $product_image_media[0] ) ) {
							$products[$i]->first_image = esc_attr( $product_image_media[0] );
						}
					}
				}
			}
			return array( 'results' => $products );
		}
	}
endif;

function wp_easycart_admin_api_routes() {
	return wp_easycart_admin_api_routes::instance();
}
wp_easycart_admin_api_routes();
