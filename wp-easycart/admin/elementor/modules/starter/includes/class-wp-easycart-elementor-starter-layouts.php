<?php
/**
 * Starter layouts for Elementor ( 6.0.2 ).
 *
 * - The elements a new EasyCart Product / Category template starts with ( the templates module's filter
 *   wp_easycart_elementor_starter_template ): a product page in two columns ( gallery | title, price, add to cart … ) with
 *   the tabs and related products below it; a category page ( kept as the templates module builds it ).
 * - The five pages Settings › Elementor › Starter pages adds to Elementor's library ( see WP_EasyCart_Elementor_Starter_Pages ).
 *
 * Every layout uses the 6.0.2 widgets with their default settings, which are made to look finished ( real store data,
 * the site kit's colours and fonts ). Flexbox containers when this Elementor uses them, else a section with columns.
 * Element ids are 7 hexadecimal characters, unique within the layout, like the ones Elementor makes ( Elementor gives
 * library templates new ids again when it saves them ).
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Starter_Layouts' ) ) :

	/**
	 * Elementor element arrays for the starter templates and pages.
	 */
	final class WP_EasyCart_Elementor_Starter_Layouts {

		/**
		 * Element ids handed out in this request ( id => true ).
		 *
		 * @var array
		 */
		private static $ids = array();

		/**
		 * Add the hooks.
		 */
		public static function boot() {
			add_filter( 'wp_easycart_elementor_starter_template', array( __CLASS__, 'starter_template' ), 10, 2 );
		}

		/**
		 * Filter wp_easycart_elementor_starter_template: the product page, and the category page when nothing built one yet.
		 *
		 * The templates module already starts a category template with Category Title, Category Description,
		 * Subcategories and Products ( the current category ): the same layout, so it is kept as it is.
		 *
		 * @param array  $content Elements so far.
		 * @param string $type    'product' or 'category'.
		 * @return array
		 */
		public static function starter_template( $content, $type ) {
			if ( is_array( $content ) && ! empty( $content ) ) {
				return $content;
			}
			if ( 'product' === $type ) {
				return self::product();
			}
			if ( 'category' === $type ) {
				return self::category();
			}
			return $content;
		}

		/**
		 * Whether new layouts use Flexbox containers, else sections and columns.
		 *
		 * Elementor registers the container element only while containers are on ( the experiment, active by default since
		 * 3.16 ), and a saved layout keeps only the element types that are registered, so its registry decides. Without
		 * one to ask: the container experiment is on, or this Elementor has no such experiment ( containers are part of it ).
		 *
		 * @return bool
		 */
		public static function use_containers() {
			if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance ) || ! is_object( \Elementor\Plugin::$instance ) ) {
				return false;
			}
			$plugin = \Elementor\Plugin::$instance;
			if ( isset( $plugin->elements_manager ) && is_object( $plugin->elements_manager ) && method_exists( $plugin->elements_manager, 'get_element_types' ) ) {
				return null !== $plugin->elements_manager->get_element_types( 'container' );
			}
			if ( ! isset( $plugin->experiments ) || ! is_object( $plugin->experiments ) ) {
				return false;
			}
			$experiments = $plugin->experiments;
			if ( method_exists( $experiments, 'get_features' ) && null === $experiments->get_features( 'container' ) ) {
				return defined( 'ELEMENTOR_VERSION' ) && version_compare( ELEMENTOR_VERSION, '3.16.0', '>=' );
			}
			return method_exists( $experiments, 'is_feature_active' ) && (bool) $experiments->is_feature_active( 'container' );
		}

		/**
		 * A new element id: 7 hexadecimal characters, never handed out twice in a request.
		 *
		 * @return string
		 */
		public static function element_id() {
			do {
				$id = sprintf( '%07x', wp_rand( 0x1000000, 0xfffffff ) );
			} while ( isset( self::$ids[ $id ] ) );
			self::$ids[ $id ] = true;
			return $id;
		}

		/**
		 * One widget.
		 *
		 * @param string $type     Widget name ( get_name() ).
		 * @param array  $settings Settings that differ from the widget's defaults.
		 * @return array
		 */
		public static function widget( $type, $settings = array() ) {
			return array(
				'id'         => self::element_id(),
				'elType'     => 'widget',
				'widgetType' => $type,
				'isInner'    => false,
				'settings'   => $settings,
				'elements'   => array(),
			);
		}

		/**
		 * One Flexbox container.
		 *
		 * @param array $elements Its children.
		 * @param array $settings Container settings.
		 * @param bool  $inner    Whether it sits inside another container.
		 * @return array
		 */
		public static function container( $elements, $settings = array(), $inner = false ) {
			return array(
				'id'       => self::element_id(),
				'elType'   => 'container',
				'isInner'  => (bool) $inner,
				'settings' => $settings,
				'elements' => $elements,
			);
		}

		/**
		 * One section ( layouts without containers ).
		 *
		 * @param array $columns  Its columns ( column() ).
		 * @param array $settings Section settings.
		 * @return array
		 */
		public static function section( $columns, $settings = array() ) {
			return array(
				'id'       => self::element_id(),
				'elType'   => 'section',
				'isInner'  => false,
				'settings' => $settings,
				'elements' => $columns,
			);
		}

		/**
		 * One column of a section.
		 *
		 * @param array $elements Its widgets.
		 * @param int   $size     Width in percent.
		 * @return array
		 */
		public static function column( $elements, $size = 100 ) {
			return array(
				'id'       => self::element_id(),
				'elType'   => 'column',
				'isInner'  => false,
				'settings' => array(
					'_column_size' => (int) $size,
					'_inline_size' => null,
				),
				'elements' => $elements,
			);
		}

		/**
		 * Widgets stacked in one full-width block.
		 *
		 * @param array $widgets Widgets.
		 * @return array One top-level element.
		 */
		public static function stack( $widgets ) {
			if ( self::use_containers() ) {
				return self::container( $widgets );
			}
			return self::section( array( self::column( $widgets, 100 ) ) );
		}

		/**
		 * The space between the product page's two columns, and between its tabs and related products ( a Gaps value,
		 * with size / unit for Elementor versions whose container gap was a slider ).
		 *
		 * @return array
		 */
		private static function gap() {
			return array(
				'unit'     => 'px',
				'size'     => 40,
				'column'   => '40',
				'row'      => '40',
				'isLinked' => true,
			);
		}

		/**
		 * A product page: the gallery beside breadcrumbs, title, rating, price, badges, short description, add to cart,
		 * details and share buttons; the tabs ( description, specifications, reviews ) and related products below, full
		 * width. The columns stack on phones.
		 *
		 * @return array Elements.
		 */
		public static function product() {
			$gallery = array(
				self::widget( 'wp_easycart_product_gallery' ),
			);
			$summary = array(
				self::widget( 'wp_easycart_product_breadcrumbs' ),
				self::widget( 'wp_easycart_product_title' ),
				self::widget( 'wp_easycart_product_rating' ),
				self::widget( 'wp_easycart_product_price' ),
				self::widget( 'wp_easycart_product_badges' ),
				self::widget( 'wp_easycart_product_short_description' ),
				self::widget( 'wp_easycart_add_to_cart' ),
				self::widget( 'wp_easycart_product_meta' ),
				self::widget( 'wp_easycart_product_share' ),
			);
			$below   = array(
				self::widget( 'wp_easycart_product_tabs' ),
				self::widget( 'wp_easycart_related_products' ),
			);

			if ( self::use_containers() ) {
				$half = array(
					'content_width' => 'full',
					'width'         => array(
						'unit'  => '%',
						'size'  => 50,
						'sizes' => array(),
					),
					'width_mobile'  => array(
						'unit'  => '%',
						'size'  => 100,
						'sizes' => array(),
					),
				);
				return array(
					self::container(
						array(
							self::container( $gallery, $half, true ),
							self::container( $summary, $half, true ),
						),
						array(
							'flex_direction'        => 'row',
							'flex_direction_mobile' => 'column',
							'flex_gap'              => self::gap(),
						)
					),
					self::container( $below, array( 'flex_gap' => self::gap() ) ),
				);
			}

			return array(
				self::section(
					array(
						self::column( $gallery, 50 ),
						self::column( $summary, 50 ),
					),
					array(
						'structure' => '20',
						'gap'       => 'wide',
					)
				),
				self::section( array( self::column( $below, 100 ) ) ),
			);
		}

		/**
		 * A category page: its title and description, its subcategories and its products ( the Shop widget, which follows the
		 * page's category with page numbers, sorting and filters; its category filter is off, the subcategories show above ).
		 * The same layout the templates module starts a category template with.
		 *
		 * @return array Elements.
		 */
		public static function category() {
			return array(
				self::stack(
					array(
						self::widget( 'wp_easycart_category_title' ),
						self::widget( 'wp_easycart_category_description' ),
						self::widget( 'wp_easycart_subcategories' ),
						self::widget( 'wp_easycart_shop', array( 'ec_filter_categories' => '' ) ),
					)
				),
			);
		}

		/**
		 * The five starter pages, key => array( label, title ( the library template's name ), contains ( one line for
		 * Settings › Elementor ), widgets ( name => title, in order ), settings ( name => settings that differ from the
		 * defaults ) ). The keys are stored on the templates ( post meta _wp_easycart_elementor_starter ): never rename.
		 *
		 * @return array
		 */
		public static function pages() {
			return array(
				'shop'         => array(
					'label'    => __( 'Shop page', 'wp-easycart' ),
					'title'    => __( 'WP EasyCart — Shop page', 'wp-easycart' ),
					'contains' => __( 'A search bar with live suggestions above your products, with sorting, filters and page numbers. For your store page.', 'wp-easycart' ),
					'widgets'  => array(
						'wp_easycart_product_search' => __( 'Product Search', 'wp-easycart' ),
						'wp_easycart_shop'           => __( 'Shop', 'wp-easycart' ),
					),
					/* The search bar sits above the products, so the Shop's own search box in its filters is off. */
					'settings' => array(
						'wp_easycart_shop' => array( 'ec_filter_search' => '' ),
					),
				),
				'cart'         => array(
					'label'    => __( 'Cart page', 'wp-easycart' ),
					'title'    => __( 'WP EasyCart — Cart page', 'wp-easycart' ),
					'contains' => __( 'The shopper\'s cart with its totals and the button to check out. For your cart page.', 'wp-easycart' ),
					'widgets'  => array(
						'wp_easycart_cart' => __( 'Cart', 'wp-easycart' ),
					),
					'settings' => array(),
				),
				'checkout'     => array(
					'label'    => __( 'Checkout page', 'wp-easycart' ),
					'title'    => __( 'WP EasyCart — Checkout page', 'wp-easycart' ),
					'contains' => __( 'Your checkout ( one page or in steps, as set in Settings › Checkout ) with the order summary. For your cart page.', 'wp-easycart' ),
					'widgets'  => array(
						'wp_easycart_checkout' => __( 'Checkout', 'wp-easycart' ),
					),
					'settings' => array(),
				),
				'account'      => array(
					'label'    => __( 'My Account page', 'wp-easycart' ),
					'title'    => __( 'WP EasyCart — My Account page', 'wp-easycart' ),
					'contains' => __( 'Sign in, orders, downloads, subscriptions, addresses and account details, with a menu. For your account page.', 'wp-easycart' ),
					'widgets'  => array(
						'wp_easycart_my_account' => __( 'My Account', 'wp-easycart' ),
					),
					'settings' => array(),
				),
				'confirmation' => array(
					'label'    => __( 'Order confirmation page', 'wp-easycart' ),
					'title'    => __( 'WP EasyCart — Order confirmation page', 'wp-easycart' ),
					'contains' => __( 'What shoppers see after they place an order. For your cart page.', 'wp-easycart' ),
					'widgets'  => array(
						'wp_easycart_thank_you' => __( 'Order Confirmation', 'wp-easycart' ),
					),
					'settings' => array(),
				),
			);
		}

		/**
		 * The elements of one starter page ( its widgets stacked in one full-width block ), or an empty array.
		 *
		 * @param string $key A key of pages().
		 * @return array
		 */
		public static function page( $key ) {
			$pages = self::pages();
			if ( ! isset( $pages[ $key ] ) ) {
				return array();
			}
			$widgets = array();
			foreach ( array_keys( $pages[ $key ]['widgets'] ) as $name ) {
				$settings  = isset( $pages[ $key ]['settings'][ $name ] ) ? $pages[ $key ]['settings'][ $name ] : array();
				$widgets[] = self::widget( $name, $settings );
			}
			return array( self::stack( $widgets ) );
		}
	}

endif;
