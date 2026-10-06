<?php
/**
 * WP EasyCart — import from WooCommerce on this site ( 6.0.3 ).
 *
 * Reads WooCommerce's own tables ( posts, postmeta, terms, comments ), so it works whether WooCommerce is active or already
 * switched off, and writes through wp_easycart_product_writer, so every product hook fires ( Google feed, Zapier ).
 *
 * - Categories keep their tree; tags can become categories; brands ( WooCommerce 9.6+ product_brand, Perfect Brands,
 *   YITH ) become manufacturers.
 * - Products: simple, variable ( WooCommerce's variation attributes become the product's own option sets, at most five;
 *   each variation a variant row with its price, sale, SKU, stock, weight, image and on / off; "any" values expand to every
 *   choice; combinations WooCommerce does not sell are switched off ), grouped ( their products come in, the group can
 *   become a category ), external ( a button to the other site ), virtual and downloadable ( the first file is copied
 *   from this server ). Images stay the same media library attachments. Non-variation attributes become specifications.
 * - Prices: a sale running now is kept as the price with the regular price as the list price; a scheduled sale is noted.
 *   Weights convert when the merchant asks, dimensions follow the store's inch / centimetre setting.
 * - Reviews with the reviewer, rating and verified flag; related products from cross-sells and upsells.
 * - Every item gets a map row with its old address ( product/<slug>, product-category/<path> ) for the redirects.
 *
 * @package WP_EasyCart
 * @since   6.0.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_import_woocommerce' ) ) :

	/**
	 * WooCommerce source.
	 */
	class wp_easycart_import_woocommerce extends wp_easycart_import_source {

		const BATCH = 20;

		/**
		 * Meta of the products in the batch: post id => key => value.
		 *
		 * @var array
		 */
		private $meta = array();

		/**
		 * Terms of the products in the batch: post id => taxonomy => list of terms.
		 *
		 * @var array
		 */
		private $terms = array();

		/**
		 * Variations of the products in the batch: parent id => list of rows.
		 *
		 * @var array
		 */
		private $variations = array();

		/**
		 * WooCommerce's global attributes: name => row.
		 *
		 * @var array|null
		 */
		private $attributes = null;

		/**
		 * The answer of detect() for this request.
		 *
		 * @var array|null
		 */
		private $detected = null;

		/**
		 * Ordered terms of a taxonomy for this request.
		 *
		 * @var array
		 */
		private $ordered = array();

		/**
		 * What WP EasyCart 6.0.2's WooCommerce importer recorded ( option ec_option_woo_import_map ).
		 *
		 * @var array|null
		 */
		private $legacy = null;

		/**
		 * The EasyCart id WP EasyCart 6.0.2's importer gave a WooCommerce product or category, while it still exists, so a
		 * product it brought in ( often without a SKU ) is not made again.
		 *
		 * @param string $kind products | categories.
		 * @param int    $id   WooCommerce id.
		 * @return int
		 */
		private function legacy_target( $kind, $id ) {
			global $wpdb;
			$map          = get_option( 'ec_option_woo_import_map' );
			$this->legacy = is_array( $map ) ? $map : array();
			$target = isset( $this->legacy[ $kind ][ (int) $id ] ) ? (int) $this->legacy[ $kind ][ (int) $id ] : 0;
			if ( ! $target ) {
				return 0;
			}
			if ( 'products' === $kind ) {
				return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT product_id FROM ec_product WHERE product_id = %d', $target ) );
			}
			return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT category_id FROM ec_category WHERE category_id = %d', $target ) );
		}

		/**
		 * Source id.
		 *
		 * @return string
		 */
		public function id() {
			return 'woocommerce';
		}

		/**
		 * Name.
		 *
		 * @return string
		 */
		public function label() {
			return 'WooCommerce';
		}

		/**
		 * One line under the name.
		 *
		 * @return string
		 */
		public function description() {
			return __( 'Products, variations, categories, brands and reviews from this WordPress site.', 'wp-easycart' );
		}

		// ---- What is here ----.

		/**
		 * The product statuses read: everything but the trash and auto-drafts ( drafts only when they are wanted ).
		 *
		 * @param array|null $settings Settings ( null = every status ).
		 * @return string[]
		 */
		private function statuses( $settings = null ) {
			if ( null !== $settings && 'skip' === $this->field( $settings, 'drafts', 'inactive' ) ) {
				return array( 'publish' );
			}
			return array( 'publish', 'future', 'draft', 'pending', 'private' );
		}

		/**
		 * SQL list of quoted statuses.
		 *
		 * @param array|null $settings Settings.
		 * @return string
		 */
		private function status_sql( $settings = null ) {
			return "'" . implode( "','", array_map( 'esc_sql', $this->statuses( $settings ) ) ) . "'";
		}

		/**
		 * A table of this site exists.
		 *
		 * @param string $table Full table name.
		 * @return bool
		 */
		private function table_exists( $table ) {
			global $wpdb;
			return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = %s', $table ) );
		}

		/**
		 * The brand taxonomy in use ( WooCommerce 9.6+, Perfect Brands, YITH ), '' when none has brands.
		 *
		 * @return string
		 */
		private function brand_taxonomy() {
			global $wpdb;
			foreach ( array( 'product_brand', 'pwb-brand', 'yith_product_brand' ) as $taxonomy ) {
				if ( (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", $taxonomy ) ) ) {
					return $taxonomy;
				}
			}
			return '';
		}

		/**
		 * The store's weight unit: kg, g, lbs or oz.
		 *
		 * @return string
		 */
		private function store_weight_unit() {
			$unit = strtolower( (string) get_option( 'ec_option_weight_unit', '' ) );
			if ( '' === $unit ) {
				$unit = strtolower( (string) get_option( 'ec_option_weight', 'lbs' ) );
			}
			if ( 0 === strpos( $unit, 'k' ) ) {
				return 'kg';
			}
			if ( 'g' === $unit || 'gram' === substr( $unit, 0, 4 ) ) {
				return 'g';
			}
			if ( 0 === strpos( $unit, 'oz' ) ) {
				return 'oz';
			}
			return 'lbs';
		}

		/**
		 * The store's dimension unit: cm or in.
		 *
		 * @return string
		 */
		private function store_dimension_unit() {
			return get_option( 'ec_option_enable_metric_unit_display' ) ? 'cm' : 'in';
		}

		/**
		 * WooCommerce's weight unit.
		 *
		 * @return string
		 */
		private function woo_weight_unit() {
			$unit = strtolower( (string) get_option( 'woocommerce_weight_unit', 'kg' ) );
			return in_array( $unit, array( 'kg', 'g', 'lbs', 'oz' ), true ) ? $unit : 'kg';
		}

		/**
		 * WooCommerce's dimension unit.
		 *
		 * @return string
		 */
		private function woo_dimension_unit() {
			$unit = strtolower( (string) get_option( 'woocommerce_dimension_unit', 'cm' ) );
			return in_array( $unit, array( 'm', 'cm', 'mm', 'in', 'yd' ), true ) ? $unit : 'cm';
		}

		/**
		 * What WooCommerce left on this site.
		 *
		 * @param bool $fresh Ask again.
		 * @return array
		 */
		public function detect( $fresh = false ) {
			global $wpdb;
			if ( null !== $this->detected && ! $fresh ) {
				return $this->detected;
			}
			$status_sql = $this->status_sql();
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- table names from $wpdb and a fixed status list.
			$products   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status IN ( $status_sql )" );
			$variations = $products ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} v INNER JOIN {$wpdb->posts} p ON p.ID = v.post_parent WHERE v.post_type = 'product_variation' AND v.post_status IN ( 'publish', 'private' ) AND p.post_type = 'product' AND p.post_status IN ( $status_sql )" ) : 0;
			$taxonomy   = function ( $name ) use ( $wpdb ) {
				return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", $name ) );
			};
			$types      = array();
			foreach ( (array) $wpdb->get_results( "SELECT t.slug, COUNT(*) AS n FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id WHERE tt.taxonomy = 'product_type' AND p.post_type = 'product' AND p.post_status IN ( $status_sql ) GROUP BY t.slug" ) as $row ) {
				$types[ (string) $row->slug ] = (int) $row->n;
			}
			$brand_tax = $this->brand_taxonomy();
			$reviews   = $products ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} c INNER JOIN {$wpdb->posts} p ON p.ID = c.comment_post_ID WHERE p.post_type = 'product' AND c.comment_approved = '1' AND c.comment_type IN ( 'review', 'comment', '' )" ) : 0;
			$hpos      = ( 'yes' === get_option( 'woocommerce_custom_orders_table_enabled' ) ) && $this->table_exists( $wpdb->prefix . 'wc_orders' );
			$orders    = $hpos ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders WHERE type = 'shop_order' AND status NOT IN ( 'trash', 'wc-checkout-draft', 'auto-draft' )" ) : (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_order' AND post_status NOT IN ( 'trash', 'wc-checkout-draft', 'auto-draft' )" );
			$customers = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value LIKE %s", $wpdb->prefix . 'capabilities', '%' . $wpdb->esc_like( '"customer"' ) . '%' ) );
			$coupons   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_coupon' AND post_status = 'publish'" );
			$addons    = $products ? (int) $wpdb->get_var( "SELECT COUNT( DISTINCT post_id ) FROM {$wpdb->postmeta} WHERE meta_key = '_product_addons' AND meta_value NOT IN ( '', 'a:0:{}' )" ) : 0;
			$subs      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_subscription' AND post_status NOT IN ( 'trash', 'auto-draft' )" );
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
			if ( $hpos && $this->table_exists( $wpdb->prefix . 'wc_orders' ) ) {
				$subs = max( $subs, (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders WHERE type = 'shop_subscription' AND status NOT IN ( 'trash', 'auto-draft' )" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb.
			}
			$counts  = array(
				'products'   => $products,
				'variations' => $variations,
				'categories' => $taxonomy( 'product_cat' ),
				'tags'       => $taxonomy( 'product_tag' ),
				'brands'     => '' !== $brand_tax ? $taxonomy( $brand_tax ) : 0,
				'reviews'    => $reviews,
				'customers'  => $customers,
				'orders'     => $orders,
				'coupons'    => $coupons,
				'grouped'    => isset( $types['grouped'] ) ? $types['grouped'] : 0,
				'external'   => isset( $types['external'] ) ? $types['external'] : 0,
				'variable'   => isset( $types['variable'] ) ? $types['variable'] : 0,
				'other'      => array_sum( array_diff_key( $types, array_flip( array( 'simple', 'variable', 'grouped', 'external' ) ) ) ),
			);
			$active  = class_exists( 'WooCommerce' );
			$version = (string) get_option( 'woocommerce_version', '' );
			$checks  = array();
			if ( $products ) {
				$woo_currency = strtoupper( (string) get_option( 'woocommerce_currency', '' ) );
				$ec_currency  = strtoupper( (string) get_option( 'ec_option_base_currency', 'USD' ) );
				if ( '' !== $woo_currency && $woo_currency !== $ec_currency ) {
					/* translators: 1: WooCommerce's currency code, 2: the store's currency code. */
					$checks[] = array( 'warn', __( 'Different currency', 'wp-easycart' ), sprintf( __( 'WooCommerce prices are in %1$s and your store sells in %2$s. Prices are copied as they are.', 'wp-easycart' ), $woo_currency, $ec_currency ) );
				} elseif ( '' !== $woo_currency ) {
					/* translators: %s: currency code. */
					$checks[] = array( 'ok', __( 'Same currency', 'wp-easycart' ), sprintf( __( 'Both stores sell in %s.', 'wp-easycart' ), $woo_currency ) );
				}
				if ( 'yes' === get_option( 'woocommerce_prices_include_tax' ) ) {
					$checks[] = array( 'warn', __( 'Prices include tax', 'wp-easycart' ), __( 'WooCommerce prices include tax. They are copied as they are, so check how your store adds tax ( Settings › Taxes ).', 'wp-easycart' ) );
				}
				if ( $this->woo_weight_unit() !== $this->store_weight_unit() ) {
					/* translators: 1: WooCommerce's weight unit, 2: the store's weight unit. */
					$checks[] = array( 'info', __( 'Weight units', 'wp-easycart' ), sprintf( __( 'WooCommerce weighs in %1$s and your store in %2$s. Choose below whether to convert the weights.', 'wp-easycart' ), $this->woo_weight_unit(), $this->store_weight_unit() ) );
				}
				if ( $counts['grouped'] || $counts['external'] ) {
					$checks[] = array( 'info', __( 'Grouped and external products', 'wp-easycart' ), __( 'Choose below what happens to grouped products and to products sold on another site.', 'wp-easycart' ) );
				}
				if ( $counts['other'] ) {
					/* translators: %d: number of products. */
					$checks[] = array( 'warn', __( 'Other product types', 'wp-easycart' ), sprintf( _n( '%d product is a bundle, composite or another type from an extension. It is imported as a simple product.', '%d products are bundles, composites or other types from extensions. They are imported as simple products.', $counts['other'], 'wp-easycart' ), $counts['other'] ) );
				}
				if ( $addons ) {
					/* translators: %d: number of products. */
					$checks[] = array( 'warn', __( 'Product Add-Ons', 'wp-easycart' ), sprintf( _n( '%d product uses Product Add-Ons. Add-ons do not come across: add them again as modifiers ( Products › Option Sets ).', '%d products use Product Add-Ons. Add-ons do not come across: add them again as modifiers ( Products › Option Sets ).', $addons, 'wp-easycart' ), $addons ) );
				}
				if ( $subs ) {
					/* translators: %d: number of subscriptions. */
					$checks[] = array( 'warn', __( 'Subscriptions', 'wp-easycart' ), sprintf( _n( '%d WooCommerce subscription was found. Subscriptions do not come across in this version.', '%d WooCommerce subscriptions were found. Subscriptions do not come across in this version.', $subs, 'wp-easycart' ), $subs ) );
				}
				$before = wp_easycart_import::ready() ? (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM ec_import_map WHERE source = 'woocommerce' AND source_site = %s AND entity = 'product' AND target_id > 0", $this->site() ) ) : 0;
				if ( $before ) {
					/* translators: %d: number of products. */
					$checks[] = array( 'info', __( 'Imported before', 'wp-easycart' ), sprintf( _n( '%d product was imported before. Choose below whether to skip it or update it.', '%d products were imported before. Choose below whether to skip them or update them.', $before, 'wp-easycart' ), $before ) );
				}
			}
			if ( $products ) {
				/* translators: %s: WooCommerce version. */
				$status = $active ? ( '' !== $version ? sprintf( __( 'Found on this site · WooCommerce %s', 'wp-easycart' ), $version ) : __( 'Found on this site', 'wp-easycart' ) ) : __( 'WooCommerce is switched off, but its products are still on this site.', 'wp-easycart' );
			} else {
				$status = __( 'No WooCommerce products on this site.', 'wp-easycart' );
			}
			$this->detected = array(
				'available' => ( $products > 0 ),
				'connected' => ( $products > 0 ),
				'active'    => $active,
				'version'   => $version,
				'status'    => $status,
				'counts'    => $counts,
				'checks'    => $checks,
				'brand_tax' => $brand_tax,
			);
			return $this->detected;
		}

		/**
		 * What can come across.
		 *
		 * @param array $detect From detect().
		 * @return array
		 */
		public function entities( $detect ) {
			$c    = isset( $detect['counts'] ) ? $detect['counts'] : array();
			$n    = function ( $key ) use ( $c ) {
				return isset( $c[ $key ] ) ? (int) $c[ $key ] : 0;
			};
			$next = __( 'Coming in the next update', 'wp-easycart' );
			return array(
				'products'  => array(
					'label'     => __( 'Products', 'wp-easycart' ),
					/* translators: 1: number of products, 2: number of variations. */
					'desc'      => sprintf( __( '%1$s products and %2$s variations, with their categories, images and stock', 'wp-easycart' ), number_format_i18n( $n( 'products' ) ), number_format_i18n( $n( 'variations' ) ) ),
					'count'     => $n( 'products' ),
					'available' => $n( 'products' ) > 0,
					'default'   => true,
				),
				'reviews'   => array(
					'label'     => __( 'Reviews', 'wp-easycart' ),
					'desc'      => __( 'Approved reviews with the reviewer, rating and verified buyer mark', 'wp-easycart' ),
					'count'     => $n( 'reviews' ),
					'available' => $n( 'reviews' ) > 0,
					'default'   => true,
					'requires'  => array( 'products' ),
				),
				'customers' => array(
					'label'     => __( 'Customers', 'wp-easycart' ),
					'desc'      => __( 'Accounts with their addresses', 'wp-easycart' ),
					'count'     => $n( 'customers' ),
					'available' => false,
					'note'      => $next,
				),
				'orders'    => array(
					'label'     => __( 'Orders', 'wp-easycart' ),
					'desc'      => __( 'Order history and refunds', 'wp-easycart' ),
					'count'     => $n( 'orders' ),
					'available' => false,
					'note'      => $next,
				),
				'coupons'   => array(
					'label'     => __( 'Coupons', 'wp-easycart' ),
					'desc'      => __( 'Coupon codes', 'wp-easycart' ),
					'count'     => $n( 'coupons' ),
					'available' => false,
					'note'      => $next,
				),
			);
		}

		/**
		 * The merchant's choices.
		 *
		 * @param array $detect From detect().
		 * @return array
		 */
		public function fields( $detect ) {
			$c      = isset( $detect['counts'] ) ? $detect['counts'] : array();
			$fields = array(
				'existing' => array(
					'type'    => 'select',
					'label'   => __( 'Products already in your store', 'wp-easycart' ),
					'desc'    => __( 'A product imported before, or one with the same SKU.', 'wp-easycart' ),
					'options' => array(
						'skip'   => __( 'Leave them as they are', 'wp-easycart' ),
						'update' => __( 'Update them from WooCommerce', 'wp-easycart' ),
					),
					'default' => 'skip',
				),
				'drafts'   => array(
					'type'    => 'select',
					'label'   => __( 'Drafts, private and hidden products', 'wp-easycart' ),
					'desc'    => __( 'Published products that are visible in the catalog come across switched on.', 'wp-easycart' ),
					'options' => array(
						'inactive' => __( 'Bring them in switched off', 'wp-easycart' ),
						'skip'     => __( 'Leave drafts out', 'wp-easycart' ),
					),
					'default' => 'inactive',
				),
			);
			if ( ! empty( $c['tags'] ) ) {
				$fields['tags'] = array(
					'type'    => 'select',
					'label'   => __( 'Product tags', 'wp-easycart' ),
					'desc'    => __( 'Your store has no tags. They can become categories.', 'wp-easycart' ),
					'options' => array(
						'skip'       => __( 'Leave them out', 'wp-easycart' ),
						'categories' => __( 'Make them categories', 'wp-easycart' ),
					),
					'default' => 'skip',
				);
			}
			if ( ! empty( $c['brands'] ) ) {
				$fields['brands'] = array(
					'type'    => 'select',
					'label'   => __( 'Brands', 'wp-easycart' ),
					'desc'    => __( 'Products without a brand get your store\'s first manufacturer.', 'wp-easycart' ),
					'options' => array(
						'manufacturers' => __( 'Make them manufacturers', 'wp-easycart' ),
						'skip'          => __( 'Leave them out', 'wp-easycart' ),
					),
					'default' => 'manufacturers',
				);
			}
			if ( ! empty( $c['grouped'] ) ) {
				$fields['grouped'] = array(
					'type'    => 'select',
					'label'   => __( 'Grouped products', 'wp-easycart' ),
					'desc'    => __( 'The products in a group always come across.', 'wp-easycart' ),
					'options' => array(
						'category' => __( 'Make each group a category', 'wp-easycart' ),
						'skip'     => __( 'Leave the groups out', 'wp-easycart' ),
					),
					'default' => 'category',
				);
			}
			if ( ! empty( $c['external'] ) ) {
				$fields['external'] = array(
					'type'    => 'select',
					'label'   => __( 'External products', 'wp-easycart' ),
					'desc'    => __( 'Products sold on another site.', 'wp-easycart' ),
					'options' => array(
						'link' => __( 'Import them with a button to that site', 'wp-easycart' ),
						'skip' => __( 'Leave them out', 'wp-easycart' ),
					),
					'default' => 'link',
				);
			}
			if ( $this->woo_weight_unit() !== $this->store_weight_unit() ) {
				$fields['weight'] = array(
					'type'    => 'select',
					'label'   => __( 'Weights', 'wp-easycart' ),
					/* translators: 1: WooCommerce's weight unit, 2: the store's weight unit. */
					'desc'    => sprintf( __( 'WooCommerce: %1$s. Your store: %2$s.', 'wp-easycart' ), $this->woo_weight_unit(), $this->store_weight_unit() ),
					'options' => array(
						/* translators: %s: weight unit. */
						'convert' => sprintf( __( 'Convert to %s', 'wp-easycart' ), $this->store_weight_unit() ),
						'keep'    => __( 'Keep the numbers as they are', 'wp-easycart' ),
					),
					'default' => 'convert',
				);
			}
			$fields['redirects'] = array(
				'type'    => 'toggle',
				'label'   => __( 'Send old WooCommerce addresses to the new pages', 'wp-easycart' ),
				'desc'    => __( 'Links and search results that point at your WooCommerce products and categories keep working ( a permanent redirect ).', 'wp-easycart' ),
				'default' => '1',
			);
			return $fields;
		}

		// ---- Phases ----.

		/**
		 * The phases.
		 *
		 * @param array $settings Settings.
		 * @param bool  $trial    Trial.
		 * @return string[]
		 */
		public function phases( $settings, $trial ) {
			$phases = array( 'categories' );
			if ( 'categories' === $this->field( $settings, 'tags', 'skip' ) ) {
				$phases[] = 'tags';
			}
			if ( 'manufacturers' === $this->field( $settings, 'brands', 'skip' ) ) {
				$phases[] = 'brands';
			}
			$phases[] = 'products';
			if ( ! $trial ) {
				$phases[] = 'links';
				if ( $this->wants( $settings, 'reviews' ) ) {
					$phases[] = 'reviews';
				}
			}
			return $phases;
		}

		/**
		 * A phase's name.
		 *
		 * @param string $phase Phase.
		 * @return string
		 */
		public function phase_label( $phase ) {
			$labels = array(
				'categories' => __( 'Categories', 'wp-easycart' ),
				'tags'       => __( 'Tags', 'wp-easycart' ),
				'brands'     => __( 'Brands', 'wp-easycart' ),
				'products'   => __( 'Products', 'wp-easycart' ),
				'links'      => __( 'Related products and groups', 'wp-easycart' ),
				'reviews'    => __( 'Reviews', 'wp-easycart' ),
			);
			return isset( $labels[ $phase ] ) ? $labels[ $phase ] : parent::phase_label( $phase );
		}

		/**
		 * How many items a phase has.
		 *
		 * @param string $phase    Phase.
		 * @param array  $settings Settings.
		 * @param bool   $trial    Trial.
		 * @return int
		 */
		public function phase_total( $phase, $settings, $trial ) {
			global $wpdb;
			$detect = $this->detect();
			$c      = $detect['counts'];
			switch ( $phase ) {
				case 'categories':
					return (int) $c['categories'];
				case 'tags':
					return (int) $c['tags'];
				case 'brands':
					return (int) $c['brands'];
				case 'products':
				case 'links':
					$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status IN ( " . $this->status_sql( $settings ) . ' )' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- fixed status list.
					return $trial ? min( wp_easycart_import::TRIAL, $total ) : $total;
				case 'reviews':
					return (int) $c['reviews'];
			}
			return 0;
		}

		/**
		 * Work on a phase.
		 *
		 * @param string $phase    Phase.
		 * @param mixed  $cursor   Cursor.
		 * @param array  $job      Job.
		 * @param float  $deadline Stop time.
		 * @return array
		 */
		public function run( $phase, $cursor, &$job, $deadline ) {
			switch ( $phase ) {
				case 'categories':
					return $this->run_terms( 'product_cat', 'category', $cursor, $job, $deadline );
				case 'tags':
					return $this->run_terms( 'product_tag', 'tag', $cursor, $job, $deadline );
				case 'brands':
					return $this->run_brands( $cursor, $job, $deadline );
				case 'products':
					return $this->run_products( $cursor, $job, $deadline );
				case 'links':
					return $this->run_links( $cursor, $job, $deadline );
				case 'reviews':
					return $this->run_reviews( $cursor, $job, $deadline );
			}
			return array(
				'cursor' => null,
				'done'   => true,
				'count'  => 0,
			);
		}

		/**
		 * Every phase is done: the old address bases, the redirects choice, the launch checklist.
		 *
		 * @param array $job Job.
		 */
		public function finish( &$job ) {
			wp_easycart_import::redirect_bases( array( $this->first_part( $this->product_base() ), $this->first_part( $this->category_base() ), $this->first_part( $this->tag_base() ) ) );
			update_option( wp_easycart_import::REDIRECTS_OPTION, '0' === $this->field( $job['settings'], 'redirects', '1' ) ? '0' : '1', false );
			if ( empty( $job['trial'] ) ) {
				update_option( 'ec_option_cart_importer_woo_imported', time(), false ); /* the launch checklist's "Import your products" */
			}
		}

		// ---- Addresses ----.

		/**
		 * WooCommerce's permalink settings.
		 *
		 * @return array
		 */
		private function permalinks() {
			$permalinks = get_option( 'woocommerce_permalinks', array() );
			return is_array( $permalinks ) ? $permalinks : array();
		}

		/**
		 * The product address base ( product, shop/%product_cat% … ).
		 *
		 * @return string
		 */
		private function product_base() {
			$p = $this->permalinks();
			return trim( ! empty( $p['product_base'] ) ? (string) $p['product_base'] : 'product', '/' );
		}

		/**
		 * The category address base.
		 *
		 * @return string
		 */
		private function category_base() {
			$p = $this->permalinks();
			return trim( ! empty( $p['category_base'] ) ? (string) $p['category_base'] : 'product-category', '/' );
		}

		/**
		 * The tag address base.
		 *
		 * @return string
		 */
		private function tag_base() {
			$p = $this->permalinks();
			return trim( ! empty( $p['tag_base'] ) ? (string) $p['tag_base'] : 'product-tag', '/' );
		}

		/**
		 * The first part of a base ( shop of shop/%product_cat% ).
		 *
		 * @param string $base Base.
		 * @return string
		 */
		private function first_part( $base ) {
			$parts = explode( '/', trim( (string) $base, '/' ) );
			return ( '' !== $parts[0] && false === strpos( $parts[0], '%' ) ) ? strtolower( $parts[0] ) : '';
		}

		/**
		 * A product's old address.
		 *
		 * @param string $slug     Its slug.
		 * @param string $category Its first category's slug path ( for %product_cat% ).
		 * @return string
		 */
		private function product_path( $slug, $category = '' ) {
			$base = str_replace( '%product_cat%', $category, $this->product_base() );
			$base = trim( preg_replace( '#/+#', '/', $base ), '/' );
			$base = preg_replace( '/%[^%\/]+%/', '', $base );
			return strtolower( rawurldecode( trim( ( '' !== trim( $base, '/' ) ? trim( $base, '/' ) . '/' : '' ) . $slug, '/' ) ) );
		}

		// ---- Terms: categories, tags, brands ----.

		/**
		 * A taxonomy's terms, parents before their children.
		 *
		 * @param string $taxonomy Taxonomy.
		 * @return array List of objects: term_id, name, slug, description, parent, path.
		 */
		private function ordered_terms( $taxonomy ) {
			global $wpdb;
			if ( isset( $this->ordered[ $taxonomy ] ) ) {
				return $this->ordered[ $taxonomy ];
			}
			$rows  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT t.term_id, t.name, t.slug, tt.description, tt.parent FROM {$wpdb->term_taxonomy} tt INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id WHERE tt.taxonomy = %s ORDER BY t.term_id ASC", $taxonomy ) );
			$by_id = array();
			$kids  = array();
			foreach ( $rows as $row ) {
				$by_id[ (int) $row->term_id ] = $row;
			}
			foreach ( $rows as $row ) {
				$parent            = isset( $by_id[ (int) $row->parent ] ) ? (int) $row->parent : 0;
				$kids[ $parent ][] = (int) $row->term_id;
			}
			$out   = array();
			$queue = isset( $kids[0] ) ? $kids[0] : array();
			$paths = array();
			while ( $queue ) {
				$id           = array_shift( $queue );
				$term         = $by_id[ $id ];
				$up           = isset( $by_id[ (int) $term->parent ] ) ? (int) $term->parent : 0;
				$paths[ $id ] = ( $up && isset( $paths[ $up ] ) ? $paths[ $up ] . '/' : '' ) . $term->slug;
				$term->path   = $paths[ $id ];
				$out[]        = $term;
				if ( isset( $kids[ $id ] ) ) {
					$queue = array_merge( $queue, $kids[ $id ] );
				}
			}
			$this->ordered[ $taxonomy ] = $out;
			return $out;
		}

		/**
		 * Categories ( or tags as categories ), parents first.
		 *
		 * @param string $taxonomy Taxonomy.
		 * @param string $entity   category | tag.
		 * @param mixed  $cursor   Index in the ordered list.
		 * @param array  $job      Job.
		 * @param float  $deadline Stop time.
		 * @return array
		 */
		private function run_terms( $taxonomy, $entity, $cursor, &$job, $deadline ) {
			global $wpdb;
			$terms = $this->ordered_terms( $taxonomy );
			$index = max( 0, (int) $cursor );
			$count = 0;
			$base  = ( 'tag' === $entity ) ? $this->tag_base() : $this->category_base();
			$total = count( $terms );
			while ( $index < $total && ( 0 === $count || $this->time_left( $deadline ) ) ) {
				$term = $terms[ $index++ ];
				++$count;
				$path     = strtolower( rawurldecode( $base . '/' . $term->path ) );
				$existing = wp_easycart_import::map_target( $this->id(), $job['site'], $entity, $term->term_id );
				if ( $existing && $wpdb->get_var( $wpdb->prepare( 'SELECT category_id FROM ec_category WHERE category_id = %d', $existing ) ) ) {
					$this->record(
						$job,
						$entity,
						$term->term_id,
						'skipped',
						array(
							'target_id' => $existing,
							'label'     => $term->name,
						)
					);
					continue;
				}
				$legacy = ( 'category' === $entity ) ? $this->legacy_target( 'categories', $term->term_id ) : 0;
				if ( $legacy ) {
					$this->record(
						$job,
						$entity,
						$term->term_id,
						'skipped',
						array(
							'target_id' => $legacy,
							'label'     => $term->name,
							'created'   => false,
							'path'      => $path,
						)
					);
					continue;
				}
				$parent = ( 'category' === $entity && (int) $term->parent ) ? wp_easycart_import::map_target( $this->id(), $job['site'], 'category', $term->parent ) : 0;
				$name   = wp_specialchars_decode( (string) $term->name, ENT_QUOTES );
				$found  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT category_id FROM ec_category WHERE LOWER( category_name ) = %s AND parent_id = %d ORDER BY category_id ASC LIMIT 1', strtolower( $name ), $parent ) );
				$id     = wp_easycart_product_writer::category( $name, $parent );
				if ( is_wp_error( $id ) ) {
					$this->record(
						$job,
						$entity,
						$term->term_id,
						'failed',
						array(
							'label'   => $name,
							'message' => $id->get_error_message(),
						)
					);
					continue;
				}
				if ( $found ) {
					$this->record(
						$job,
						$entity,
						$term->term_id,
						'skipped',
						array(
							'target_id' => $id,
							'label'     => $name,
							'created'   => false,
							'path'      => $path,
							'message'   => __( 'Matched a category your store already has.', 'wp-easycart' ),
						)
					);
					continue;
				}
				$columns = array();
				if ( '' !== trim( (string) $term->description ) ) {
					$columns['short_description'] = wp_kses_post( (string) $term->description );
				}
				$thumb = (int) $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->termmeta} WHERE term_id = %d AND meta_key = 'thumbnail_id' LIMIT 1", (int) $term->term_id ) );
				if ( $thumb && function_exists( 'wp_get_attachment_url' ) ) {
					$url = wp_get_attachment_url( $thumb );
					if ( $url ) {
						$columns['image'] = esc_url_raw( $url );
					}
				}
				if ( $columns ) {
					$wpdb->update( 'ec_category', $columns, array( 'category_id' => (int) $id ) );
				}
				$this->record(
					$job,
					$entity,
					$term->term_id,
					'imported',
					array(
						'target_id' => $id,
						'label'     => $name,
						'path'      => $path,
					)
				);
			}
			return array(
				'cursor' => $index,
				'done'   => $index >= count( $terms ),
				'count'  => $count,
				'total'  => count( $terms ),
			);
		}

		/**
		 * Brands as manufacturers.
		 *
		 * @param mixed $cursor   Index.
		 * @param array $job      Job.
		 * @param float $deadline Stop time.
		 * @return array
		 */
		private function run_brands( $cursor, &$job, $deadline ) {
			$detect   = $this->detect();
			$taxonomy = isset( $detect['brand_tax'] ) ? (string) $detect['brand_tax'] : '';
			$terms    = '' !== $taxonomy ? $this->ordered_terms( $taxonomy ) : array();
			$index    = max( 0, (int) $cursor );
			$count    = 0;
			$total    = count( $terms );
			while ( $index < $total && ( 0 === $count || $this->time_left( $deadline ) ) ) {
				$term = $terms[ $index++ ];
				++$count;
				$name                 = wp_specialchars_decode( (string) $term->name, ENT_QUOTES );
				list( $id, $created ) = $this->manufacturer( $name );
				if ( ! $id ) {
					$this->record(
						$job,
						'brand',
						$term->term_id,
						'failed',
						array(
							'label'   => $name,
							'message' => __( 'The manufacturer could not be saved.', 'wp-easycart' ),
						)
					);
					continue;
				}
				if ( wp_easycart_import::map_target( $this->id(), $job['site'], 'brand', $term->term_id ) || ! $created ) {
					$this->record(
						$job,
						'brand',
						$term->term_id,
						'skipped',
						array(
							'target_id' => $id,
							'label'     => $name,
							'created'   => false,
						)
					);
					continue;
				}
				$this->record(
					$job,
					'brand',
					$term->term_id,
					'imported',
					array(
						'target_id' => $id,
						'label'     => $name,
					)
				);
			}
			return array(
				'cursor' => $index,
				'done'   => $index >= count( $terms ),
				'count'  => $count,
				'total'  => count( $terms ),
			);
		}

		/**
		 * A manufacturer by name, made with its store page when the store has none by that name.
		 *
		 * @param string $name Name.
		 * @return array( id, created )
		 */
		private function manufacturer( $name ) {
			global $wpdb;
			$name  = sanitize_text_field( $name );
			$found = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT manufacturer_id FROM ec_manufacturer WHERE LOWER( `name` ) = %s ORDER BY manufacturer_id ASC LIMIT 1', strtolower( $name ) ) );
			if ( $found ) {
				return array( $found, false );
			}
			$wpdb->insert( 'ec_manufacturer', array( 'name' => $name ) );
			$id = (int) $wpdb->insert_id;
			if ( $id && function_exists( 'wp_easycart_post_sync' ) ) {
				wp_easycart_post_sync()->insert(
					'manufacturer',
					$id,
					array(
						'post_content' => '[ec_store manufacturerid="' . $id . '"]',
						'post_status'  => 'publish',
						'post_title'   => $name,
						'post_type'    => 'ec_store',
					)
				);
			}
			return array( $id, (bool) $id );
		}

		/**
		 * The manufacturer for a product without a brand: the store's first one, else one named after the store.
		 *
		 * @param array $job Job.
		 * @return int
		 */
		private function default_manufacturer( &$job ) {
			global $wpdb;
			if ( ! empty( $job['state']['manufacturer'] ) && $wpdb->get_var( $wpdb->prepare( 'SELECT manufacturer_id FROM ec_manufacturer WHERE manufacturer_id = %d', (int) $job['state']['manufacturer'] ) ) ) {
				return (int) $job['state']['manufacturer'];
			}
			$id = (int) $wpdb->get_var( 'SELECT manufacturer_id FROM ec_manufacturer ORDER BY manufacturer_id ASC LIMIT 1' );
			if ( ! $id ) {
				$name                 = function_exists( 'get_bloginfo' ) ? wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ) : '';
				list( $id, $created ) = $this->manufacturer( '' !== trim( $name ) ? $name : __( 'My store', 'wp-easycart' ) );
				if ( $id && $created ) {
					$this->record(
						$job,
						'brand',
						'store-default',
						'imported',
						array(
							'target_id' => $id,
							'label'     => $name,
						)
					);
				}
			}
			$job['state']['manufacturer'] = $id;
			return $id;
		}

		// ---- Products ----.

		/**
		 * Load the meta, terms and variations of a batch of products in a few queries.
		 *
		 * @param int[] $ids Product ids.
		 */
		private function prime( $ids ) {
			global $wpdb;
			$this->meta       = array();
			$this->terms      = array();
			$this->variations = array();
			$ids              = array_values( array_filter( array_map( 'intval', (array) $ids ) ) );
			if ( ! $ids ) {
				return;
			}
			$in = implode( ',', $ids );
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- (int) ids and table names from $wpdb.
			$vars = (array) $wpdb->get_results( "SELECT ID, post_parent, post_status, menu_order, post_title FROM {$wpdb->posts} WHERE post_parent IN ( $in ) AND post_type = 'product_variation' AND post_status IN ( 'publish', 'private' ) ORDER BY menu_order ASC, ID ASC" );
			$all  = $ids;
			foreach ( $vars as $var ) {
				$this->variations[ (int) $var->post_parent ][] = $var;
				$all[] = (int) $var->ID;
			}
			$keys = array( '_sku', '_regular_price', '_sale_price', '_sale_price_dates_from', '_sale_price_dates_to', '_price', '_tax_status', '_manage_stock', '_stock', '_stock_status', '_backorders', '_virtual', '_downloadable', '_downloadable_files', '_download_limit', '_download_expiry', '_weight', '_length', '_width', '_height', '_thumbnail_id', '_product_image_gallery', '_product_attributes', '_crosssell_ids', '_upsell_ids', '_children', '_product_url', '_button_text', '_yoast_wpseo_metadesc', 'rank_math_description', '_cogs_total_value' );
			$rows = (array) $wpdb->get_results( "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id IN ( " . implode( ',', $all ) . " ) AND ( meta_key IN ( '" . implode( "','", $keys ) . "' ) OR meta_key LIKE 'attribute\\_%' ) ORDER BY meta_id ASC" );
			foreach ( $rows as $row ) {
				$this->meta[ (int) $row->post_id ][ (string) $row->meta_key ] = (string) $row->meta_value;
			}
			$terms = (array) $wpdb->get_results( "SELECT tr.object_id, tt.taxonomy, t.term_id, t.name, t.slug, tt.parent FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id WHERE tr.object_id IN ( $in ) ORDER BY tr.term_order ASC, t.term_id ASC" );
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
			foreach ( $terms as $term ) {
				$this->terms[ (int) $term->object_id ][ (string) $term->taxonomy ][] = $term;
			}
		}

		/**
		 * A primed meta value.
		 *
		 * @param int    $post_id Post.
		 * @param string $key     Key.
		 * @return string
		 */
		private function m( $post_id, $key ) {
			return isset( $this->meta[ (int) $post_id ][ $key ] ) ? $this->meta[ (int) $post_id ][ $key ] : '';
		}

		/**
		 * A product's primed terms of a taxonomy.
		 *
		 * @param int    $post_id  Post.
		 * @param string $taxonomy Taxonomy.
		 * @return array
		 */
		private function t( $post_id, $taxonomy ) {
			return isset( $this->terms[ (int) $post_id ][ $taxonomy ] ) ? $this->terms[ (int) $post_id ][ $taxonomy ] : array();
		}

		/**
		 * Products, a batch at a time ( by id, never by offset ).
		 *
		 * @param mixed $cursor   Last product id done.
		 * @param array $job      Job.
		 * @param float $deadline Stop time.
		 * @return array
		 */
		private function run_products( $cursor, &$job, $deadline ) {
			global $wpdb;
			$last  = max( 0, (int) $cursor );
			$count = 0;
			$trial = ! empty( $job['trial'] );
			while ( 0 === $count || $this->time_left( $deadline ) ) {
				if ( $trial && $this->trial_count( $job ) >= wp_easycart_import::TRIAL ) {
					return array(
						'cursor' => $last,
						'done'   => true,
						'count'  => $count,
					);
				}
				$limit = $trial ? min( self::BATCH, wp_easycart_import::TRIAL - $this->trial_count( $job ) ) : self::BATCH;
				$posts = (array) $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title, post_name, post_content, post_excerpt, post_status, comment_status, menu_order, post_modified_gmt FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status IN ( " . $this->status_sql( $job['settings'] ) . ' ) AND ID > %d ORDER BY ID ASC LIMIT %d', $last, $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- fixed status list.
				if ( ! $posts ) {
					return array(
						'cursor' => $last,
						'done'   => true,
						'count'  => $count,
					);
				}
				$this->prime( wp_list_pluck( $posts, 'ID' ) );
				foreach ( $posts as $post ) {
					if ( $count > 0 && ! $this->time_left( $deadline ) ) {
						return array(
							'cursor' => $last,
							'done'   => false,
							'count'  => $count,
						);
					}
					try {
						$this->import_product( $post, $job );
					} catch ( \Throwable $e ) {
						$this->record(
							$job,
							'product',
							$post->ID,
							'failed',
							array(
								'label'   => $post->post_title,
								'message' => $e->getMessage(),
							)
						);
					}
					$last = (int) $post->ID;
					++$count;
				}
			}
			return array(
				'cursor' => $last,
				'done'   => false,
				'count'  => $count,
			);
		}

		/**
		 * WooCommerce's product type ( simple, variable, grouped, external, or an extension's ).
		 *
		 * @param int $post_id Product.
		 * @return string
		 */
		private function product_type( $post_id ) {
			$types = $this->t( $post_id, 'product_type' );
			return $types ? (string) $types[0]->slug : 'simple';
		}

		/**
		 * The price now and the list price, from a regular price, a sale price and its dates.
		 *
		 * @param int   $post_id Product or variation.
		 * @param array $notes   Notes ( a scheduled sale ).
		 * @return array( price ( '' = none ), list price )
		 */
		private function prices( $post_id, &$notes ) {
			$regular = $this->m( $post_id, '_regular_price' );
			$sale    = $this->m( $post_id, '_sale_price' );
			$from    = (int) $this->m( $post_id, '_sale_price_dates_from' );
			$to      = (int) $this->m( $post_id, '_sale_price_dates_to' );
			$now     = time();
			if ( '' === $regular ) {
				$regular = $this->m( $post_id, '_price' );
			}
			if ( '' !== $sale && is_numeric( $sale ) && ( ! $from || $from <= $now ) && ( ! $to || $to >= $now ) && ( '' === $regular || (float) $sale < (float) $regular ) ) {
				return array( (float) $sale, '' !== $regular ? (float) $regular : 0 );
			}
			if ( '' !== $sale && $from > $now ) {
				/* translators: 1: sale price, 2: date. */
				$notes['sale'] = sprintf( __( 'A sale at %1$s starting %2$s was not kept: set it again when it starts.', 'wp-easycart' ), $sale, gmdate( 'Y-m-d', $from ) );
			}
			return array( '' !== $regular && is_numeric( $regular ) ? (float) $regular : '', 0 );
		}

		/**
		 * A weight in the store's unit.
		 *
		 * @param string $value    WooCommerce value.
		 * @param array  $settings Settings.
		 * @return float|null
		 */
		private function weight( $value, $settings ) {
			if ( '' === trim( (string) $value ) || ! is_numeric( $value ) ) {
				return null;
			}
			$value = (float) $value;
			if ( 'keep' === $this->field( $settings, 'weight', 'convert' ) ) {
				return $value;
			}
			$kg = array(
				'kg'  => 1,
				'g'   => 0.001,
				'lbs' => 0.45359237,
				'oz'  => 0.028349523125,
			);
			return round( $value * $kg[ $this->woo_weight_unit() ] / $kg[ $this->store_weight_unit() ], 4 );
		}

		/**
		 * A dimension in the store's unit.
		 *
		 * @param string $value WooCommerce value.
		 * @return float|null
		 */
		private function dimension( $value ) {
			if ( '' === trim( (string) $value ) || ! is_numeric( $value ) ) {
				return null;
			}
			$cm = array(
				'm'  => 100,
				'cm' => 1,
				'mm' => 0.1,
				'in' => 2.54,
				'yd' => 91.44,
			);
			return round( (float) $value * $cm[ $this->woo_dimension_unit() ] / ( 'cm' === $this->store_dimension_unit() ? 1 : 2.54 ), 4 );
		}

		/**
		 * Text as the storefront shows it: paragraphs where WooCommerce relied on WordPress to add them.
		 *
		 * @param string $text Text.
		 * @return string
		 */
		private function html( $text ) {
			$text = (string) $text;
			if ( '' !== trim( $text ) && false !== strpos( $text, "\n" ) && false === stripos( $text, '<p' ) && false === strpos( $text, '<!-- wp:' ) && function_exists( 'wpautop' ) ) {
				$text = wpautop( $text );
			}
			return $text;
		}

		/**
		 * Import one product.
		 *
		 * @param object $post Product post.
		 * @param array  $job  Job.
		 */
		private function import_product( $post, &$job ) {
			global $wpdb;
			$id       = (int) $post->ID;
			$settings = $job['settings'];
			$type     = $this->product_type( $id );
			$label    = wp_specialchars_decode( (string) $post->post_title, ENT_QUOTES );
			$cats     = $this->t( $id, 'product_cat' );
			$path     = $this->product_path( (string) $post->post_name, $cats ? $this->category_path( $cats[0] ) : '' );
			$notes    = array();

			/* Already in the store: imported before ( the map ), or a product with the same SKU. */
			$existing = wp_easycart_import::map_target( $this->id(), $job['site'], 'product', $id );
			if ( $existing && ! $wpdb->get_var( $wpdb->prepare( 'SELECT product_id FROM ec_product WHERE product_id = %d', $existing ) ) ) {
				$existing = 0; /* deleted since */
			}
			if ( ! $existing ) {
				$existing = $this->legacy_target( 'products', $id );
				if ( $existing ) {
					$notes['linked'] = __( 'Imported before by the older WooCommerce importer.', 'wp-easycart' );
				}
			}
			$sku = trim( $this->m( $id, '_sku' ) );
			if ( ! $existing && '' !== $sku ) {
				$existing = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT product_id FROM ec_product WHERE model_number = %s LIMIT 1', $sku ) );
				if ( $existing && $wpdb->get_var( $wpdb->prepare( "SELECT map_id FROM ec_import_map WHERE entity = 'product' AND target_id = %d AND NOT ( source = %s AND source_site = %s AND source_id = %s ) LIMIT 1", $existing, $this->id(), $job['site'], (string) $id ) ) ) {
					$existing = 0; /* that SKU belongs to another imported product */
				}
				if ( $existing ) {
					$notes['linked'] = __( 'Matched a product your store already has with the same SKU.', 'wp-easycart' );
				}
			}
			if ( $existing && 'update' !== $this->field( $settings, 'existing', 'skip' ) ) {
				$this->record(
					$job,
					'product',
					$id,
					'skipped',
					array(
						'target_id' => $existing,
						'label'     => $label,
						'created'   => false,
						'path'      => $path,
						'message'   => isset( $notes['linked'] ) ? $notes['linked'] : '',
					)
				);
				return;
			}
			if ( 'grouped' === $type ) {
				$this->record(
					$job,
					'product',
					$id,
					'skipped',
					array(
						'label'   => $label,
						'message' => ( 'category' === $this->field( $settings, 'grouped', 'category' ) ) ? __( 'A group: its products come across and the group becomes a category.', 'wp-easycart' ) : __( 'A group: its products come across, the group is left out.', 'wp-easycart' ),
					)
				);
				return;
			}
			if ( 'external' === $type && 'skip' === $this->field( $settings, 'external', 'link' ) ) {
				$this->record(
					$job,
					'product',
					$id,
					'skipped',
					array(
						'label'   => $label,
						'message' => __( 'Sold on another site: left out.', 'wp-easycart' ),
					)
				);
				return;
			}

			$data = $this->product_data( $post, $type, $job, $notes );
			if ( $existing ) {
				$data['product_id'] = $existing;
				unset( $data['post_slug'] ); /* an update never moves the product's page to another address */
			}
			$product_id = wp_easycart_product_writer::save( $data );
			if ( is_wp_error( $product_id ) && 'wp_easycart_model_number_taken' === $product_id->get_error_code() ) {
				$taken                = $data['model_number'];
				$data['model_number'] = $this->free_model_number( $taken );
				/* translators: 1: SKU, 2: the SKU used instead. */
				$notes['sku'] = sprintf( __( 'Another product already uses the SKU %1$s, so this one is %2$s.', 'wp-easycart' ), $taken, $data['model_number'] );
				$product_id   = wp_easycart_product_writer::save( $data );
			}
			if ( is_wp_error( $product_id ) ) {
				$this->record(
					$job,
					'product',
					$id,
					'failed',
					array(
						'label'   => $label,
						'message' => $product_id->get_error_message(),
						'path'    => $path,
					)
				);
				return;
			}

			if ( 'variable' === $type ) {
				$this->save_variations( $product_id, $id, $job, $notes );
			} elseif ( $existing && class_exists( 'wp_easycart_product_writer' ) && (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_option WHERE owner_product_id = %d', $product_id ) ) ) {
				wp_easycart_product_writer::save_options( $product_id, array() ); /* no longer variable in WooCommerce */
			}

			$images = array();
			foreach ( array_merge( array( $this->m( $id, '_thumbnail_id' ) ), explode( ',', $this->m( $id, '_product_image_gallery' ) ) ) as $image ) {
				if ( (int) $image > 0 && ! in_array( (int) $image, $images, true ) ) {
					$images[] = (int) $image;
				}
			}
			if ( $images ) {
				$set = wp_easycart_product_writer::set_images( $product_id, $images );
				if ( is_wp_error( $set ) ) {
					$notes['images'] = $set->get_error_message();
				}
			}

			$category_ids = array();
			foreach ( $cats as $term ) {
				$category_ids[] = wp_easycart_import::map_target( $this->id(), $job['site'], 'category', $term->term_id );
			}
			if ( 'categories' === $this->field( $settings, 'tags', 'skip' ) ) {
				foreach ( $this->t( $id, 'product_tag' ) as $term ) {
					$category_ids[] = wp_easycart_import::map_target( $this->id(), $job['site'], 'tag', $term->term_id );
				}
			}
			$category_ids = array_values( array_filter( $category_ids ) );
			if ( $category_ids || $existing ) {
				$keep = array();
				if ( $existing ) {
					/* An update keeps the categories the store added itself. */
					$imported_categories = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT target_id FROM ec_import_map WHERE source = %s AND source_site = %s AND entity IN ( 'category', 'tag' )", $this->id(), $job['site'] ) ) );
					foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SELECT category_id FROM ec_categoryitem WHERE product_id = %d', $product_id ) ) as $current ) {
						if ( ! in_array( (int) $current, $imported_categories, true ) ) {
							$keep[] = (int) $current;
						}
					}
				}
				wp_easycart_product_writer::set_categories( $product_id, array_merge( $category_ids, $keep ) );
			}

			$this->record(
				$job,
				'product',
				$id,
				$existing ? 'updated' : 'imported',
				array(
					'target_id' => $product_id,
					'label'     => $label,
					'message'   => implode( ' ', $notes ),
					'path'      => $path,
					'hash'      => md5( (string) $post->post_modified_gmt ),
				)
			);
		}

		/**
		 * A category term's slug path ( parent/child ).
		 *
		 * @param object $term Term.
		 * @return string
		 */
		private function category_path( $term ) {
			foreach ( $this->ordered_terms( 'product_cat' ) as $ordered ) {
				if ( (int) $ordered->term_id === (int) $term->term_id ) {
					return $ordered->path;
				}
			}
			return (string) $term->slug;
		}

		/**
		 * A SKU no product uses, made from one that is taken.
		 *
		 * @param string $sku SKU.
		 * @return string
		 */
		private function free_model_number( $sku ) {
			global $wpdb;
			for ( $i = 2; $i < 1000; $i++ ) {
				$try = substr( $sku, 0, 240 ) . '-' . $i;
				if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT product_id FROM ec_product WHERE model_number = %s', $try ) ) ) {
					return $try;
				}
			}
			return substr( $sku, 0, 230 ) . '-' . wp_rand( 100000, 999999 );
		}

		/**
		 * The product's columns for the writer.
		 *
		 * @param object $post  Product post.
		 * @param string $type  Product type.
		 * @param array  $job   Job.
		 * @param array  $notes Notes.
		 * @return array
		 */
		private function product_data( $post, $type, &$job, &$notes ) {
			$id       = (int) $post->ID;
			$settings = $job['settings'];

			/* Price: a variable product shows its lowest variation price. */
			if ( 'variable' === $type && ! empty( $this->variations[ $id ] ) ) {
				$price = '';
				$list  = 0;
				foreach ( $this->variations[ $id ] as $variation ) {
					$ignore        = array();
					list( $p, $l ) = $this->prices( (int) $variation->ID, $ignore );
					if ( '' !== $p && ( '' === $price || $p < $price ) ) {
						$price = $p;
						$list  = $l;
					}
				}
			} else {
				list( $price, $list ) = $this->prices( $id, $notes );
			}

			$visibility = wp_list_pluck( $this->t( $id, 'product_visibility' ), 'slug' );
			$hidden     = in_array( 'exclude-from-catalog', $visibility, true ) && in_array( 'exclude-from-search', $visibility, true );
			$active     = ( 'publish' === $post->post_status && ! $hidden );

			$manage = ( 'yes' === $this->m( $id, '_manage_stock' ) );
			$stock  = (int) $this->m( $id, '_stock' );
			$data   = array(
				'title'               => wp_specialchars_decode( (string) $post->post_title, ENT_QUOTES ),
				'description'         => $this->html( $post->post_content ),
				'short_description'   => $this->html( $post->post_excerpt ),
				'model_number'        => trim( $this->m( $id, '_sku' ) ),
				'price'               => '' === $price ? 0 : $price,
				'list_price'          => $list,
				'is_shippable'        => ( 'yes' !== $this->m( $id, '_virtual' ) && 'external' !== $type ),
				'is_taxable'          => ! in_array( $this->m( $id, '_tax_status' ), array( 'none', 'shipping' ), true ),
				'activate_in_store'   => $active,
				'show_stock_quantity' => ( $manage || 'outofstock' === $this->m( $id, '_stock_status' ) ),
				'stock_quantity'      => $manage ? max( 0, $stock ) : 0,
				'post_slug'           => (string) $post->post_name,
				'extra'               => array(
					'use_customer_reviews' => ( 'open' === $post->comment_status ) ? 1 : 0,
					'allow_backorders'     => in_array( $this->m( $id, '_backorders' ), array( 'yes', 'notify' ), true ) ? 1 : 0,
					'sort_position'        => (int) $post->menu_order,
				),
			);
			if ( '' === $price && 'external' !== $type && 'variable' !== $type ) {
				$notes['price'] = __( 'No price in WooCommerce: imported at 0.', 'wp-easycart' );
			}
			foreach ( array( 'weight', 'length', 'width', 'height' ) as $key ) {
				$value = ( 'weight' === $key ) ? $this->weight( $this->m( $id, '_weight' ), $settings ) : $this->dimension( $this->m( $id, '_' . $key ) );
				if ( null !== $value ) {
					$data[ $key ] = $value;
				}
			}
			$cost = $this->m( $id, '_cogs_total_value' );
			if ( '' !== $cost && is_numeric( $cost ) ) {
				$data['product_cost'] = (float) $cost;
			}
			$seo = '' !== trim( $this->m( $id, '_yoast_wpseo_metadesc' ) ) ? $this->m( $id, '_yoast_wpseo_metadesc' ) : $this->m( $id, 'rank_math_description' );
			if ( '' !== trim( $seo ) && false === strpos( $seo, '%%' ) && false === strpos( $seo, '%excerpt%' ) ) {
				$data['seo_description'] = $seo;
			}

			/* Brand. */
			$manufacturer = 0;
			if ( 'manufacturers' === $this->field( $settings, 'brands', 'skip' ) ) {
				$detect = $this->detect();
				if ( ! empty( $detect['brand_tax'] ) ) {
					foreach ( $this->t( $id, $detect['brand_tax'] ) as $brand ) {
						$manufacturer = wp_easycart_import::map_target( $this->id(), $job['site'], 'brand', $brand->term_id );
						if ( $manufacturer ) {
							break;
						}
					}
				}
			}
			$data['manufacturer_id'] = $manufacturer ? $manufacturer : $this->default_manufacturer( $job );

			/* External: a button to the other site. */
			if ( 'external' === $type ) {
				$url = esc_url_raw( $this->m( $id, '_product_url' ) );
				if ( '' !== $url ) {
					$data['extra']['inquiry_mode'] = 1;
					$data['extra']['inquiry_url']  = $url;
				}
			}

			/* Specifications: the attributes that are not variations. */
			$specs = $this->specifications( $id );
			if ( '' !== $specs ) {
				$data['extra']['specifications']     = $specs;
				$data['extra']['use_specifications'] = 1;
			}

			/* Download: the first file, copied from this server. */
			if ( 'yes' === $this->m( $id, '_downloadable' ) ) {
				$download = $this->download( $id, $notes );
				if ( '' !== $download ) {
					$limit                                       = (int) $this->m( $id, '_download_limit' );
					$expiry                                      = (int) $this->m( $id, '_download_expiry' );
					$data['extra']['is_download']                = 1;
					$data['extra']['download_file_name']         = $download;
					$data['extra']['maximum_downloads_allowed']  = $limit > 0 ? $limit : 0;
					$data['extra']['download_timelimit_seconds'] = $expiry > 0 ? $expiry * DAY_IN_SECONDS : 0;
				}
			}
			if ( 'variable' === $type || ! in_array( $type, array( 'simple', 'grouped', 'external' ), true ) ) {
				if ( ! in_array( $type, array( 'variable', 'simple' ), true ) ) {
					/* translators: %s: WooCommerce product type. */
					$notes['type'] = sprintf( __( 'A %s product: imported as a simple product.', 'wp-easycart' ), $type );
				}
			}
			return $data;
		}

		/**
		 * The attributes that are not variations, as the specifications text.
		 *
		 * @param int $id Product.
		 * @return string
		 */
		private function specifications( $id ) {
			$lines = array();
			foreach ( $this->attributes_of( $id ) as $key => $attribute ) {
				if ( ! empty( $attribute['is_variation'] ) || empty( $attribute['is_visible'] ) ) {
					continue;
				}
				$values = array();
				foreach ( $this->attribute_items( $id, $key, $attribute ) as $item ) {
					$values[] = $item['name'];
				}
				if ( $values ) {
					$lines[] = '<strong>' . esc_html( $this->attribute_label( $key, $attribute ) ) . ':</strong> ' . esc_html( implode( ', ', $values ) );
				}
			}
			return implode( '<br />', $lines );
		}

		/**
		 * Copy a downloadable product's first file into the store's download folder.
		 *
		 * @param int   $id    Product.
		 * @param array $notes Notes.
		 * @return string The stored file name, '' when it could not be copied.
		 */
		private function download( $id, &$notes ) {
			$files = maybe_unserialize( $this->m( $id, '_downloadable_files' ) );
			if ( ! is_array( $files ) || ! $files ) {
				return '';
			}
			$file = reset( $files );
			$url  = is_array( $file ) && isset( $file['file'] ) ? (string) $file['file'] : '';
			if ( count( $files ) > 1 ) {
				/* translators: %d: number of files. */
				$notes['files'] = sprintf( __( 'Only the first of %d download files came across.', 'wp-easycart' ), count( $files ) );
			}
			$path = $this->local_path( $url );
			if ( '' === $path || ! defined( 'EC_PLUGIN_DATA_DIRECTORY' ) ) {
				$notes['download'] = __( 'The download file is not on this server: add it to the product again.', 'wp-easycart' );
				return '';
			}
			$name   = 'woo-' . $id . '-' . sanitize_file_name( wp_basename( $path ) );
			$folder = EC_PLUGIN_DATA_DIRECTORY . '/products/downloads/';
			if ( ! file_exists( $folder . $name ) && ( ! is_dir( $folder ) || ! @copy( $path, $folder . $name ) ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a failed copy is reported in the note.
				$notes['download'] = __( 'The download file could not be copied: add it to the product again.', 'wp-easycart' );
				return '';
			}
			return $name;
		}

		/**
		 * The file on this server behind a WooCommerce download address ( uploads, or an absolute path ).
		 *
		 * @param string $url Address or path.
		 * @return string '' when it is not a readable local file.
		 */
		private function local_path( $url ) {
			$url = trim( (string) $url );
			if ( '' === $url ) {
				return '';
			}
			if ( function_exists( 'wp_upload_dir' ) ) {
				$uploads = wp_upload_dir( null, false );
				foreach ( array( (string) $uploads['baseurl'], set_url_scheme( (string) $uploads['baseurl'], 'https' ), set_url_scheme( (string) $uploads['baseurl'], 'http' ) ) as $base ) {
					if ( '' !== $base && 0 === strpos( $url, $base ) ) {
						$path = $uploads['basedir'] . substr( $url, strlen( $base ) );
						return ( false === strpos( $path, '..' ) && is_readable( $path ) ) ? $path : '';
					}
				}
			}
			if ( ! preg_match( '#^[a-z]+://#i', $url ) && false === strpos( $url, '..' ) && is_readable( $url ) && is_file( $url ) ) {
				return $url;
			}
			return '';
		}

		// ---- Attributes and variations ----.

		/**
		 * WooCommerce's global attributes, name => row.
		 *
		 * @return array
		 */
		private function global_attributes() {
			global $wpdb;
			if ( null === $this->attributes ) {
				$this->attributes = array();
				if ( $this->table_exists( $wpdb->prefix . 'woocommerce_attribute_taxonomies' ) ) {
					foreach ( (array) $wpdb->get_results( "SELECT attribute_name, attribute_label, attribute_type, attribute_orderby FROM {$wpdb->prefix}woocommerce_attribute_taxonomies" ) as $row ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb.
						$this->attributes[ (string) $row->attribute_name ] = $row;
					}
				}
			}
			return $this->attributes;
		}

		/**
		 * A product's attributes, sorted by position.
		 *
		 * @param int $id Product.
		 * @return array key => attribute.
		 */
		private function attributes_of( $id ) {
			$attributes = maybe_unserialize( $this->m( $id, '_product_attributes' ) );
			if ( ! is_array( $attributes ) ) {
				return array();
			}
			uasort(
				$attributes,
				function ( $a, $b ) {
					return (int) ( isset( $a['position'] ) ? $a['position'] : 0 ) - (int) ( isset( $b['position'] ) ? $b['position'] : 0 );
				}
			);
			return $attributes;
		}

		/**
		 * An attribute's label.
		 *
		 * @param string $key       Attribute key ( pa_size, or a custom attribute's key ).
		 * @param array  $attribute Attribute.
		 * @return string
		 */
		private function attribute_label( $key, $attribute ) {
			if ( ! empty( $attribute['is_taxonomy'] ) ) {
				$globals = $this->global_attributes();
				$name    = preg_replace( '/^pa_/', '', (string) $key );
				if ( isset( $globals[ $name ] ) && '' !== trim( (string) $globals[ $name ]->attribute_label ) ) {
					return wp_specialchars_decode( (string) $globals[ $name ]->attribute_label, ENT_QUOTES );
				}
				return ucfirst( str_replace( array( '-', '_' ), ' ', $name ) );
			}
			return isset( $attribute['name'] ) ? wp_specialchars_decode( (string) $attribute['name'], ENT_QUOTES ) : (string) $key;
		}

		/**
		 * An attribute's choices for a product: key ( a term's slug, a custom value ) and name.
		 *
		 * @param int    $id        Product.
		 * @param string $key       Attribute key.
		 * @param array  $attribute Attribute.
		 * @return array list of array( key, name ).
		 */
		private function attribute_items( $id, $key, $attribute ) {
			global $wpdb;
			$items = array();
			if ( ! empty( $attribute['is_taxonomy'] ) ) {
				$terms = $this->t( $id, (string) $key );
				if ( ! $terms ) {
					return array();
				}
				$globals = $this->global_attributes();
				$name    = preg_replace( '/^pa_/', '', (string) $key );
				$orderby = isset( $globals[ $name ] ) ? (string) $globals[ $name ]->attribute_orderby : 'menu_order';
				$order   = array();
				if ( 'menu_order' === $orderby ) {
					$ids = implode( ',', array_map( 'intval', wp_list_pluck( $terms, 'term_id' ) ) );
					foreach ( (array) $wpdb->get_results( "SELECT term_id, meta_value FROM {$wpdb->termmeta} WHERE term_id IN ( $ids ) AND meta_key IN ( 'order', 'order_" . esc_sql( $key ) . "' )" ) as $row ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- (int) ids and an escaped key.
						$order[ (int) $row->term_id ] = (int) $row->meta_value;
					}
				}
				usort(
					$terms,
					function ( $a, $b ) use ( $orderby, $order ) {
						if ( 'id' === $orderby ) {
							return (int) $a->term_id - (int) $b->term_id;
						}
						if ( 'menu_order' === $orderby ) {
							$oa = isset( $order[ (int) $a->term_id ] ) ? $order[ (int) $a->term_id ] : 0;
							$ob = isset( $order[ (int) $b->term_id ] ) ? $order[ (int) $b->term_id ] : 0;
							if ( $oa !== $ob ) {
								return $oa - $ob;
							}
						}
						return strnatcasecmp( (string) $a->name, (string) $b->name );
					}
				);
				foreach ( $terms as $term ) {
					$items[] = array(
						'key'  => (string) $term->slug,
						'name' => wp_specialchars_decode( (string) $term->name, ENT_QUOTES ),
					);
				}
				return $items;
			}
			foreach ( explode( '|', isset( $attribute['value'] ) ? (string) $attribute['value'] : '' ) as $value ) {
				$value = trim( wp_specialchars_decode( $value, ENT_QUOTES ) );
				if ( '' !== $value ) {
					$items[] = array(
						'key'  => $value,
						'name' => $value,
					);
				}
			}
			return $items;
		}

		/**
		 * A variable product's option sets and variants.
		 *
		 * @param int   $product_id EasyCart product.
		 * @param int   $id         WooCommerce product.
		 * @param array $job        Job.
		 * @param array $notes      Notes.
		 */
		private function save_variations( $product_id, $id, &$job, &$notes ) {
			global $wpdb;
			$slots = array();
			$extra = array();
			foreach ( $this->attributes_of( $id ) as $key => $attribute ) {
				if ( empty( $attribute['is_variation'] ) ) {
					continue;
				}
				$items = $this->attribute_items( $id, $key, $attribute );
				if ( ! $items ) {
					continue;
				}
				if ( count( $slots ) >= 5 ) {
					$extra[] = $this->attribute_label( $key, $attribute );
					continue;
				}
				$slots[] = array(
					'key'   => (string) $key,
					'meta'  => 'attribute_' . sanitize_title( (string) $key ),
					'name'  => $this->attribute_label( $key, $attribute ),
					'items' => $items,
				);
			}
			if ( $extra ) {
				/* translators: %s: attribute names. */
				$notes['attributes'] = sprintf( __( 'A product has at most five choices: %s left out.', 'wp-easycart' ), implode( ', ', $extra ) );
			}
			if ( ! $slots ) {
				wp_easycart_product_writer::save_options( $product_id, array() );
				$notes['variations'] = __( 'Variable in WooCommerce, but no variation choices were found: imported without them.', 'wp-easycart' );
				return;
			}
			$sets = array();
			foreach ( $slots as $slot ) {
				$sets[] = array(
					'name'  => $slot['name'],
					'label' => $slot['name'],
					'type'  => 'basic-combo',
					'items' => $slot['items'],
				);
			}
			$saved = wp_easycart_product_writer::save_options( $product_id, $sets );
			if ( is_wp_error( $saved ) ) {
				$notes['variations'] = $saved->get_error_message();
				return;
			}

			$variants    = array();
			$per_variant = false;
			$split       = 0;
			$unmatched   = 0;
			foreach ( isset( $this->variations[ $id ] ) ? $this->variations[ $id ] : array() as $variation ) {
				$vid     = (int) $variation->ID;
				$choices = array();
				$ok      = true;
				foreach ( $slots as $index => $slot ) {
					$map   = $saved['items'][ $index + 1 ];
					$value = $this->m( $vid, $slot['meta'] );
					if ( '' === $value ) {
						$choices[ $index + 1 ] = array_values( $map ); /* "Any" value: every choice */
						continue;
					}
					$found = $this->match_key( $value, $map );
					if ( ! $found ) {
						$ok = false;
						break;
					}
					$choices[ $index + 1 ] = array( $found );
				}
				if ( ! $ok ) {
					++$unmatched;
					continue;
				}
				$ignore        = array();
				list( $price ) = $this->prices( $vid, $ignore );
				$manage        = ( 'yes' === $this->m( $vid, '_manage_stock' ) );
				$out_of_stock  = ( 'outofstock' === $this->m( $vid, '_stock_status' ) );
				$weight        = $this->weight( $this->m( $vid, '_weight' ), $job['settings'] );
				$combos        = $this->combinations( $choices );
				if ( count( $combos ) > 1 ) {
					++$split;
				}
				if ( $manage || $out_of_stock ) {
					$per_variant = true;
				}
				foreach ( $combos as $combo ) {
					$variant = array(
						'items'    => $combo,
						'sku'      => $this->m( $vid, '_sku' ),
						'price'    => '' === $price ? -1 : $price,
						'enabled'  => ( 'publish' === $variation->post_status ),
						'tracking' => ( $manage || $out_of_stock ),
						'quantity' => $manage ? max( 0, (int) $this->m( $vid, '_stock' ) ) : 0,
						'weight'   => $weight,
					);
					if ( (int) $this->m( $vid, '_thumbnail_id' ) > 0 ) {
						$variant['image'] = (int) $this->m( $vid, '_thumbnail_id' );
					}
					$variants[] = $variant;
				}
			}
			if ( $split ) {
				/* translators: %d: number of variations. */
				$notes['any'] = sprintf( _n( '%d variation was set to "any" choice and became one variant per choice, each with its stock.', '%d variations were set to "any" choice and became one variant per choice, each with its stock.', $split, 'wp-easycart' ), $split );
			}
			if ( $unmatched ) {
				/* translators: %d: number of variations. */
				$notes['unmatched'] = sprintf( _n( '%d variation uses a choice the product no longer has and was left out.', '%d variations use a choice the product no longer has and were left out.', $unmatched, 'wp-easycart' ), $unmatched );
			}
			$wpdb->query( $wpdb->prepare( 'UPDATE ec_product SET use_optionitem_quantity_tracking = %d WHERE product_id = %d', $per_variant ? 1 : 0, $product_id ) );
			$rows = wp_easycart_product_writer::save_variants( $product_id, $variants, array( 'rollup' => $per_variant ) );
			/* Combinations WooCommerce does not sell are switched off. */
			$keep = array_map( 'intval', array_values( $rows ) );
			if ( $keep ) {
				$wpdb->query( $wpdb->prepare( 'UPDATE ec_optionitemquantity SET is_enabled = 0 WHERE product_id = %d AND optionitemquantity_id NOT IN ( ' . implode( ',', $keep ) . ' )', $product_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- (int) ids.
			}
			if ( $per_variant ) {
				/* Stock is counted per variant, not on the product. */
				$wpdb->query( $wpdb->prepare( 'UPDATE ec_product SET show_stock_quantity = 0 WHERE product_id = %d', $product_id ) );
				wp_easycart_product_writer::rollup_stock( $product_id );
			}
		}

		/**
		 * The stored key a variation's value means ( a term slug, or a custom value in any case ).
		 *
		 * @param string $value Variation meta value.
		 * @param array  $map   key => optionitem_id.
		 * @return int Option item id, 0 when none matches.
		 */
		private function match_key( $value, $map ) {
			if ( isset( $map[ $value ] ) ) {
				return (int) $map[ $value ];
			}
			foreach ( $map as $key => $item_id ) {
				if ( 0 === strcasecmp( (string) $key, $value ) || sanitize_title( (string) $key ) === sanitize_title( $value ) ) {
					return (int) $item_id;
				}
			}
			return 0;
		}

		/**
		 * Every combination of the chosen items, slot => list of item ids.
		 *
		 * @param array $choices slot => item ids.
		 * @return array list of slot => item id.
		 */
		private function combinations( $choices ) {
			$combos = array( array() );
			foreach ( $choices as $slot => $items ) {
				$next = array();
				foreach ( $combos as $combo ) {
					foreach ( $items as $item ) {
						$combo[ $slot ] = (int) $item;
						$next[]         = $combo;
					}
				}
				$combos = $next;
				if ( count( $combos ) > 500 ) {
					break;
				}
			}
			return $combos;
		}

		// ---- After every product: related products and groups ----.

		/**
		 * Cross-sells and upsells become the product's related products; groups become categories.
		 *
		 * @param mixed $cursor   Last product id done.
		 * @param array $job      Job.
		 * @param float $deadline Stop time.
		 * @return array
		 */
		private function run_links( $cursor, &$job, $deadline ) {
			global $wpdb;
			$last  = max( 0, (int) $cursor );
			$count = 0;
			while ( 0 === $count || $this->time_left( $deadline ) ) {
				$posts = (array) $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status IN ( " . $this->status_sql( $job['settings'] ) . ' ) AND ID > %d ORDER BY ID ASC LIMIT 50', $last ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- fixed status list.
				if ( ! $posts ) {
					return array(
						'cursor' => $last,
						'done'   => true,
						'count'  => $count,
					);
				}
				$this->prime( wp_list_pluck( $posts, 'ID' ) );
				foreach ( $posts as $post ) {
					$id   = (int) $post->ID;
					$last = $id;
					++$count;
					$type = $this->product_type( $id );
					if ( 'grouped' === $type ) {
						if ( 'category' === $this->field( $job['settings'], 'grouped', 'category' ) ) {
							$this->group_category( $post, $job );
						}
						continue;
					}
					$product_id = wp_easycart_import::map_target( $this->id(), $job['site'], 'product', $id );
					if ( ! $product_id ) {
						continue;
					}
					$related = array();
					foreach ( array( '_crosssell_ids', '_upsell_ids' ) as $key ) {
						foreach ( (array) maybe_unserialize( $this->m( $id, $key ) ) as $other ) {
							$target = wp_easycart_import::map_target( $this->id(), $job['site'], 'product', (int) $other );
							if ( $target && $target !== $product_id && ! in_array( $target, $related, true ) ) {
								$related[] = $target;
							}
						}
					}
					if ( $related ) {
						$related = array_pad( array_slice( $related, 0, 4 ), 4, 0 );
						$wpdb->query( $wpdb->prepare( 'UPDATE ec_product SET featured_product_id_1 = %d, featured_product_id_2 = %d, featured_product_id_3 = %d, featured_product_id_4 = %d WHERE product_id = %d', $related[0], $related[1], $related[2], $related[3], $product_id ) );
					}
				}
			}
			return array(
				'cursor' => $last,
				'done'   => false,
				'count'  => $count,
			);
		}

		/**
		 * A grouped product as a category holding its products.
		 *
		 * @param object $post Grouped product.
		 * @param array  $job  Job.
		 */
		private function group_category( $post, &$job ) {
			global $wpdb;
			$id       = (int) $post->ID;
			$name     = wp_specialchars_decode( (string) $post->post_title, ENT_QUOTES );
			$category = wp_easycart_import::map_target( $this->id(), $job['site'], 'category', 'group:' . $id );
			if ( ! $category || ! $wpdb->get_var( $wpdb->prepare( 'SELECT category_id FROM ec_category WHERE category_id = %d', $category ) ) ) {
				$found    = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT category_id FROM ec_category WHERE LOWER( category_name ) = %s AND parent_id = 0 LIMIT 1', strtolower( $name ) ) );
				$category = wp_easycart_product_writer::category( $name, 0 );
				if ( is_wp_error( $category ) ) {
					return;
				}
				$this->record(
					$job,
					'category',
					'group:' . $id,
					$found ? 'skipped' : 'imported',
					array(
						'target_id' => $category,
						'label'     => $name,
						'created'   => ! $found,
					)
				);
			}
			foreach ( array_map( 'intval', (array) maybe_unserialize( $this->m( $id, '_children' ) ) ) as $child ) {
				$product_id = wp_easycart_import::map_target( $this->id(), $job['site'], 'product', $child );
				if ( $product_id ) {
					$current = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT category_id FROM ec_categoryitem WHERE product_id = %d', $product_id ) ) );
					if ( ! in_array( (int) $category, $current, true ) ) {
						$current[] = (int) $category;
						wp_easycart_product_writer::set_categories( $product_id, $current );
					}
				}
			}
		}

		// ---- Reviews ----.

		/**
		 * Approved reviews of imported products.
		 *
		 * @param mixed $cursor   Last comment id done.
		 * @param array $job      Job.
		 * @param float $deadline Stop time.
		 * @return array
		 */
		private function run_reviews( $cursor, &$job, $deadline ) {
			global $wpdb;
			$last  = max( 0, (int) $cursor );
			$count = 0;
			while ( 0 === $count || $this->time_left( $deadline ) ) {
				$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT c.comment_ID, c.comment_post_ID, c.comment_author, c.comment_author_email, c.comment_date, c.comment_content FROM {$wpdb->comments} c INNER JOIN {$wpdb->posts} p ON p.ID = c.comment_post_ID WHERE p.post_type = 'product' AND c.comment_approved = '1' AND c.comment_type IN ( 'review', 'comment', '' ) AND c.comment_ID > %d ORDER BY c.comment_ID ASC LIMIT 100", $last ) );
				if ( ! $rows ) {
					return array(
						'cursor' => $last,
						'done'   => true,
						'count'  => $count,
					);
				}
				$meta = array();
				foreach ( (array) $wpdb->get_results( "SELECT comment_id, meta_key, meta_value FROM {$wpdb->commentmeta} WHERE comment_id IN ( " . implode( ',', array_map( 'intval', wp_list_pluck( $rows, 'comment_ID' ) ) ) . " ) AND meta_key IN ( 'rating', 'verified' )" ) as $row ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- (int) ids.
					$meta[ (int) $row->comment_id ][ (string) $row->meta_key ] = (string) $row->meta_value;
				}
				foreach ( $rows as $row ) {
					$comment_id = (int) $row->comment_ID;
					$last       = $comment_id;
					++$count;
					$label   = wp_html_excerpt( wp_strip_all_tags( (string) $row->comment_author . ': ' . $row->comment_content ), 80, '…' );
					$product = wp_easycart_import::map_target( $this->id(), $job['site'], 'product', (int) $row->comment_post_ID );
					$rating  = isset( $meta[ $comment_id ]['rating'] ) ? (int) $meta[ $comment_id ]['rating'] : 0;
					if ( ! $product || wp_easycart_import::map_target( $this->id(), $job['site'], 'review', $comment_id ) ) {
						$this->record( $job, 'review', $comment_id, 'skipped', array( 'label' => $label ) );
						continue;
					}
					if ( $rating < 1 || $rating > 5 ) {
						$this->record(
							$job,
							'review',
							$comment_id,
							'skipped',
							array(
								'label'   => $label,
								'message' => __( 'A comment without a star rating: left out.', 'wp-easycart' ),
							)
						);
						continue;
					}
					$wpdb->insert(
						'ec_review',
						array(
							'product_id'     => $product,
							'approved'       => 1,
							'rating'         => $rating,
							'title'          => '',
							'description'    => wp_kses_post( (string) $row->comment_content ),
							'date_submitted' => (string) $row->comment_date,
							'reviewer_name'  => sanitize_text_field( (string) $row->comment_author ),
							'reviewer_email' => sanitize_email( (string) $row->comment_author_email ),
							'verified'       => ! empty( $meta[ $comment_id ]['verified'] ) ? 1 : 0,
						)
					);
					$review_id = (int) $wpdb->insert_id;
					if ( $review_id ) {
						$this->record(
							$job,
							'review',
							$comment_id,
							'imported',
							array(
								'target_id' => $review_id,
								'label'     => $label,
							)
						);
					} else {
						$this->record(
							$job,
							'review',
							$comment_id,
							'failed',
							array(
								'label'   => $label,
								'message' => __( 'The review could not be saved.', 'wp-easycart' ),
							)
						);
					}
				}
			}
			return array(
				'cursor' => $last,
				'done'   => false,
				'count'  => $count,
			);
		}
	}

endif;
