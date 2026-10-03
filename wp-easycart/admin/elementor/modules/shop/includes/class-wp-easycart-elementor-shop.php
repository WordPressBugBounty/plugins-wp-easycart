<?php
/**
 * Shop module bootstrap and shared helpers ( 6.0.2 ).
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Shop' ) ) :

	/**
	 * Hooks, assets, shopper wording and URLs shared by the shop widgets.
	 */
	class WP_EasyCart_Elementor_Shop {

		/**
		 * Language file section for the shop widgets' own wording.
		 */
		const SECTION = 'elementor_shop';

		/**
		 * Hooks ( once ).
		 */
		public static function init() {
			static $done = false;
			if ( $done ) {
				return;
			}
			$done = true;
			add_filter( 'wp_easycart_elementor_widget_classes', array( __CLASS__, 'widget_classes' ) );
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 4 );
			add_action( 'elementor/frontend/after_register_scripts', array( __CLASS__, 'register_assets' ) );
			add_action( 'elementor/frontend/after_register_styles', array( __CLASS__, 'register_assets' ) );
			add_filter( 'wp_easycart_elementor_retired_widgets', array( 'WP_EasyCart_Elementor_Shop_Retire', 'register' ) );
			add_filter( 'wp_easycart_settings_page_elementor', array( __CLASS__, 'settings_section' ) );
			WP_EasyCart_Elementor_Shop_Ajax::init();
			/* Role prices or role-only products may have come or gone ( WP_EasyCart_Elementor_Shop_Query::role_content() ). */
			foreach ( array( 'added', 'updated', 'activated', 'deactivated', 'duplicated', 'restored', 'deleted' ) as $event ) {
				add_action( 'wpeasycart_product_' . $event, array( 'WP_EasyCart_Elementor_Shop_Query', 'forget_role_content' ), 10, 0 );
			}
		}

		/**
		 * Settings › Elementor: where the store-wide rules the shop widgets follow are set.
		 *
		 * @param array $page Page declaration.
		 * @return array
		 */
		public static function settings_section( $page ) {
			if ( ! is_array( $page ) || ! isset( $page['sections'] ) || ! is_array( $page['sections'] ) ) {
				return $page;
			}
			$page['sections']['shop-widgets'] = array(
				'title'  => __( 'Product lists and search', 'wp-easycart' ),
				'hint'   => __( 'Products, Product Carousel, Product Categories, Shop and Product Search follow these store settings.', 'wp-easycart' ),
				'icon'   => 'layout',
				'fields' => array(),
				'render' => array( __CLASS__, 'render_settings_section' ),
			);
			return $page;
		}

		/**
		 * The section's rows: each store-wide rule and where to change it.
		 */
		public static function render_settings_section() {
			$rows = array(
				array( __( 'Search', 'wp-easycart' ), __( 'What a search matches and whether the search box suggests products while typing.', 'wp-easycart' ), self::settings_url( 'products', 'ec_option_use_live_search' ) ),
				array( __( 'Sorting', 'wp-easycart' ), __( 'The sort choices shoppers get in the Shop widget, and the store order other lists use by default.', 'wp-easycart' ), self::settings_url( 'products', 'ec_option_default_store_filter' ) ),
				array( __( 'Add to cart', 'wp-easycart' ), __( 'Whether a card adds in the background or takes the shopper to the cart, and the View cart link.', 'wp-easycart' ), self::settings_url( 'products', 'ec_option_redirect_add_to_cart' ) ),
				array( __( 'Who can view the store', 'wp-easycart' ), __( 'Visitors the store keeps out see no product lists.', 'wp-easycart' ), self::settings_url( 'products', 'ec_option_restrict_store' ) ),
				array( __( 'Products per page', 'wp-easycart' ), __( 'The Shop widget\'s page size unless the widget sets its own.', 'wp-easycart' ), admin_url( 'admin.php?page=wp-easycart-settings&subpage=perpage' ) ),
				array( __( 'Price ranges', 'wp-easycart' ), __( 'The ranges the Shop widget\'s price filter offers.', 'wp-easycart' ), admin_url( 'admin.php?page=wp-easycart-settings&subpage=pricepoint' ) ),
			);
			/* The status card's rows ( wpeasycart-elementor-settings.css ), without a state pill. */
			echo '<div class="ecst-elementor-status"><ul class="ecst-el-list">';
			foreach ( $rows as $row ) {
				echo '<li class="ecst-el-row"><span class="ecst-el-label">' . esc_html( $row[0] ) . '</span><span></span>';
				echo '<span class="ecst-el-detail">' . esc_html( $row[1] ) . ' <a href="' . esc_url( $row[2] ) . '">' . esc_html__( 'Change', 'wp-easycart' ) . '</a></span></li>';
			}
			echo '</ul></div>';
		}

		/**
		 * The module's widgets ( class => file ), loaded when Elementor registers widgets.
		 *
		 * @param array $classes Class name => absolute file.
		 * @return array
		 */
		public static function widget_classes( $classes ) {
			if ( ! is_array( $classes ) ) {
				$classes = array();
			}
			$dir = dirname( __DIR__ ) . '/widgets/';
			$classes['WP_EasyCart_Elementor_Shop_Products_Widget']   = $dir . 'class-wp-easycart-elementor-shop-products-widget.php';
			$classes['WP_EasyCart_Elementor_Shop_Carousel_Widget']   = $dir . 'class-wp-easycart-elementor-shop-carousel-widget.php';
			$classes['WP_EasyCart_Elementor_Shop_Categories_Widget'] = $dir . 'class-wp-easycart-elementor-shop-categories-widget.php';
			$classes['WP_EasyCart_Elementor_Shop_Archive_Widget']    = $dir . 'class-wp-easycart-elementor-shop-archive-widget.php';
			$classes['WP_EasyCart_Elementor_Shop_Search_Widget']     = $dir . 'class-wp-easycart-elementor-shop-search-widget.php';
			return $classes;
		}

		/**
		 * URL of a file in this module.
		 *
		 * @param string $path Path inside admin/elementor/modules/shop/.
		 * @return string
		 */
		public static function url( $path ) {
			return plugins_url( 'admin/elementor/modules/shop/' . ltrim( $path, '/' ), EC_PLUGIN_DIRECTORY . '/wpeasycart.php' );
		}

		/**
		 * Registers the module's scripts and styles ( never enqueued here: each widget names them in get_*_depends(), so
		 * Elementor loads them only on pages that use the widget ).
		 */
		public static function register_assets() {
			if ( wp_script_is( 'wpec-el-shop', 'registered' ) ) {
				return;
			}
			$version = defined( 'EC_CURRENT_VERSION' ) ? EC_CURRENT_VERSION : '6.0.2';
			wp_register_style( 'wpec-el-shop', self::url( 'assets/shop.css' ), array(), $version );
			wp_register_script( 'wpec-el-shop', self::url( 'assets/shop.js' ), array(), $version, true );
			wp_localize_script( 'wpec-el-shop', 'wpecElShop', self::shop_script_data() );
			wp_register_style( 'wpec-el-search', self::url( 'assets/search.css' ), array(), $version );
			wp_register_script( 'wpec-el-search', self::url( 'assets/search.js' ), array(), $version, true );
			wp_localize_script( 'wpec-el-search', 'wpecElSearch', self::search_script_data() );
		}

		/**
		 * Data for shop.js.
		 *
		 * @return array
		 */
		private static function shop_script_data() {
			return array(
				'ajaxUrl'  => self::ajax_url(),
				'cartUrl'  => self::cart_page_url(),
				'viewCart' => get_option( 'ec_option_product_no_checkout_button' ) ? 0 : 1,
				'i18n'     => array(
					'adding'    => self::text( 'shop_adding', 'Adding…' ),
					'added'     => self::text( 'shop_added', 'Added' ),
					'addedNote' => self::text( 'shop_added_note', '[product] was added to your cart.' ),
					'addFailed' => self::text( 'shop_add_failed', 'This product could not be added. Opening the product page.' ),
					'viewCart'  => self::store_text( 'product_page', 'product_view_cart', 'View Cart' ),
					'close'     => self::text( 'shop_close', 'Close' ),
					'loading'   => self::text( 'shop_loading', 'Loading…' ),
					'loaded'    => self::text( 'shop_loaded', '[count] more products loaded' ),
					'prev'      => self::text( 'shop_carousel_prev', 'Previous products' ),
					'next'      => self::text( 'shop_carousel_next', 'Next products' ),
					'pause'     => self::text( 'shop_carousel_pause', 'Pause' ),
					'play'      => self::text( 'shop_carousel_play', 'Play' ),
					'slide'     => self::text( 'shop_carousel_slide', '[index] of [total]' ),
					'goTo'      => self::text( 'shop_carousel_go_to', 'Go to slide [index]' ),
					'quickView' => self::store_text( 'product_page', 'product_quick_view', 'Quick View' ),
				),
			);
		}

		/**
		 * Data for search.js.
		 *
		 * @return array
		 */
		private static function search_script_data() {
			return array(
				'ajaxUrl' => self::ajax_url(),
				'i18n'    => array(
					'none'    => self::text( 'shop_search_none', 'No products match “[term]”.' ),
					'results' => self::text( 'shop_search_results', '[count] products found. Use the up and down arrows to choose one.' ),
					'one'     => self::text( 'shop_search_result', '1 product found. Use the down arrow to choose it.' ),
					'loading' => self::text( 'shop_search_loading', 'Searching…' ),
					'viewAll' => self::text( 'shop_search_view_all', 'View all results for “[term]”' ),
				),
			);
		}

		/**
		 * The admin-ajax.php address on the page's own scheme ( the storefront's rule, see ec_load_js() ).
		 *
		 * @return string
		 */
		public static function ajax_url() {
			$url = admin_url( 'admin-ajax.php' );
			if ( isset( $_SERVER['HTTPS'] ) && 'on' === strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTPS'] ) ) ) ) {
				$url = str_replace( 'http://', 'https://', $url );
			}
			return $url;
		}

		/**
		 * Shopper wording from the language file section elementor_shop, as plain text ( tags removed, entities decoded;
		 * escape it where it is printed ). The language system returns nothing for a key a store's saved language lacks,
		 * so every call carries the English text.
		 *
		 * @param string $key      Key in the elementor_shop section.
		 * @param string $fallback English text.
		 * @return string
		 */
		public static function text( $key, $fallback ) {
			return self::store_text( self::SECTION, $key, $fallback );
		}

		/**
		 * Wording from any language file section, as plain text ( see text() ).
		 *
		 * @param string $section  Section.
		 * @param string $key      Key.
		 * @param string $fallback English text.
		 * @return string
		 */
		public static function store_text( $section, $key, $fallback ) {
			$value = function_exists( 'wp_easycart_language' ) ? wp_easycart_language()->get_text( $section, $key ) : '';
			$value = is_string( $value ) ? trim( html_entity_decode( wp_strip_all_tags( $value ), ENT_QUOTES, 'UTF-8' ) ) : '';
			return ( '' !== $value ) ? $value : $fallback;
		}

		/**
		 * Fills [token] placeholders.
		 *
		 * @param string $text  Text with [tokens].
		 * @param array  $pairs token => value.
		 * @return string
		 */
		public static function fill( $text, $pairs ) {
			foreach ( (array) $pairs as $token => $value ) {
				$text = str_replace( '[' . $token . ']', (string) $value, $text );
			}
			return $text;
		}

		/**
		 * Whether this request draws the Elementor editor or its preview.
		 *
		 * @return bool
		 */
		public static function is_editor() {
			return function_exists( 'wp_easycart_elementor_is_editor' ) && wp_easycart_elementor_is_editor();
		}

		/**
		 * Whether Settings › Products › Who can view the store keeps this visitor out ( never in the editor ).
		 *
		 * Every shop widget asks this before drawing anything. While the store limits who can view it, what a widget shows
		 * depends on the visitor's customer role, so the page is never cached ( a cached copy would hand one visitor's
		 * products, or the empty answer, to everyone ).
		 *
		 * @return bool
		 */
		public static function restricted() {
			if ( self::is_editor() ) {
				return false;
			}
			if ( get_option( 'ec_option_restrict_store' ) && function_exists( 'wp_easycart_product_details_no_cache' ) ) {
				wp_easycart_product_details_no_cache();
			}
			return function_exists( 'wp_easycart_store_is_restricted' ) && wp_easycart_store_is_restricted();
		}

		/**
		 * Whether an EasyCart Elementor template ( templates module ) is drawing the page right now. A category page drawn by
		 * its template sends the page's product list events itself ( GA4 view_item_list, Meta Search,
		 * wp_easycart_view_product_list, wp_easycart_category_view ), so a Shop widget inside it sends none. Products,
		 * Product Carousel and Product Categories never send list events.
		 *
		 * @return bool
		 */
		public static function template_rendering() {
			return function_exists( 'wp_easycart_elementor_template_rendering' ) && wp_easycart_elementor_template_rendering();
		}

		/**
		 * Makes sure a page with a product list carries the Meta Pixel base code ( loader, consent, PageView ), which the
		 * cards' Add to cart events need ( window.wpeasycart_meta_add ).
		 *
		 * The store adds the base code to wp_head only when the page's saved content holds an EasyCart shortcode
		 * ( wp_easycart_check_for_shortcode() ), which a page built from these widgets, or a theme template holding them,
		 * never does. Each list widget calls this as it draws: before wp_head it joins wp_head; after it ( the usual case,
		 * the widgets are in the body ) the base code prints here, ahead of the first card. The Pixel class prints it once
		 * per page ( wp_easycart_meta::print_base_code() ), so a page whose head already has it, or a second list, gets
		 * nothing more. Nothing in the editor or in AJAX answers.
		 */
		public static function pixel_base_code() {
			if ( self::is_editor() || wp_doing_ajax() || ! function_exists( 'wp_easycart_init_facebook_pixel' ) ) {
				return;
			}
			/* The store's own rule for printing it ( wp_easycart_check_for_shortcode() ). */
			if ( '' === trim( (string) get_option( 'ec_option_fb_pixel' ) ) || ! apply_filters( 'wpeasycart_allow_pixel_code', true ) ) {
				return;
			}
			if ( did_action( 'wp_head' ) ) {
				wp_easycart_init_facebook_pixel();
			} else {
				add_action( 'wp_head', 'wp_easycart_init_facebook_pixel' );
			}
		}

		/**
		 * A Settings page in the WP EasyCart admin.
		 *
		 * @param string $slug      Settings page slug.
		 * @param string $field_key Field to highlight.
		 * @return string
		 */
		public static function settings_url( $slug, $field_key = '' ) {
			if ( class_exists( 'wp_easycart_admin_settings_registry' ) && method_exists( 'wp_easycart_admin_settings_registry', 'page_url' ) ) {
				return wp_easycart_admin_settings_registry::page_url( $slug, $field_key, 'settings' );
			}
			return admin_url( 'admin.php?page=wp-easycart-settings&subpage=' . rawurlencode( $slug ) );
		}

		/**
		 * A store page ( store, cart, account ) in the shopper's language, on the page's scheme.
		 *
		 * @param string $option ec_option_storepage, ec_option_cartpage or ec_option_accountpage.
		 * @return string
		 */
		public static function page_url( $option ) {
			$page_id = ( 'ec_option_accountpage' === $option ) ? apply_filters( 'wp_easycart_account_page_id', get_option( $option ) ) : get_option( $option );
			if ( function_exists( 'icl_object_id' ) && defined( 'ICL_LANGUAGE_CODE' ) ) {
				$page_id = icl_object_id( $page_id, 'page', true, ICL_LANGUAGE_CODE );
			}
			$url = $page_id ? get_permalink( $page_id ) : '';
			if ( ! is_string( $url ) ) {
				return '';
			}
			if ( class_exists( 'WordPressHTTPS' ) && isset( $_SERVER['HTTPS'] ) ) {
				$https = new WordPressHTTPS();
				$url   = $https->makeUrlHttps( $url );
			}
			return $url;
		}

		/**
		 * The store page.
		 *
		 * @return string
		 */
		public static function store_page_url() {
			return self::page_url( 'ec_option_storepage' );
		}

		/**
		 * The cart page.
		 *
		 * @return string
		 */
		public static function cart_page_url() {
			return self::page_url( 'ec_option_cartpage' );
		}

		/**
		 * The account page.
		 *
		 * @return string
		 */
		public static function account_page_url() {
			return self::page_url( 'ec_option_accountpage' );
		}

		/**
		 * The page being drawn, with its query ( plain permalinks' page_id, WPML's lang and the store filters stay ), minus
		 * the named arguments. In the editor's own requests it is the edited page.
		 *
		 * @param array $remove Query arguments to leave out.
		 * @return string Not escaped.
		 */
		public static function current_url( $remove = array() ) {
			if ( wp_doing_ajax() || ! isset( $_SERVER['REQUEST_URI'] ) ) {
				$base = get_the_ID() ? get_permalink( get_the_ID() ) : home_url( '/' );
			} else {
				$base = esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );
			}
			if ( ! is_string( $base ) || '' === $base ) {
				$base = home_url( '/' );
			}
			return remove_query_arg( (array) $remove, $base );
		}

		/**
		 * How many products each category holds that this shopper can see in the lists ( one query ): active, and the
		 * lists' own rules ( role-only products, hidden out of stock products, pickup location ).
		 *
		 * @param int[] $category_ids Categories.
		 * @return array category id => count.
		 */
		public static function category_counts( $category_ids ) {
			global $wpdb;
			$ids = WP_EasyCart_Elementor_Shop_Query::ids( $category_ids );
			if ( empty( $ids ) ) {
				return array();
			}
			$rows   = $wpdb->get_results( 'SELECT wpec_ci.category_id, COUNT( DISTINCT wpec_p.product_id ) AS products FROM ec_categoryitem AS wpec_ci INNER JOIN ec_product AS wpec_p ON wpec_p.product_id = wpec_ci.product_id AND wpec_p.activate_in_store = 1' . WP_EasyCart_Elementor_Shop_Query::visibility_sql( 'wpec_p' ) . ' WHERE wpec_ci.category_id IN (' . implode( ',', $ids ) . ') GROUP BY wpec_ci.category_id' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- the ids are integers ( WP_EasyCart_Elementor_Shop_Query::ids() ), visibility_sql() fixed SQL with integers.
			$counts = array();
			foreach ( (array) $rows as $row ) {
				$counts[ (int) $row->category_id ] = (int) $row->products;
			}
			return $counts;
		}

		/**
		 * Whether an active category has active subcategories.
		 *
		 * @param int $category_id Category.
		 * @return bool
		 */
		public static function category_has_children( $category_id ) {
			if ( empty( $GLOBALS['ec_categories']->all_categories ) ) {
				return false;
			}
			foreach ( (array) $GLOBALS['ec_categories']->all_categories as $row ) {
				if ( (int) $row->parent_id === (int) $category_id ) {
					return true;
				}
			}
			return false;
		}

		/**
		 * A category's name in the shopper's language, as plain text.
		 *
		 * @param object $category Row from ec_category.
		 * @return string
		 */
		public static function category_name( $category ) {
			$name = isset( $category->category_name ) ? (string) $category->category_name : '';
			if ( function_exists( 'wp_easycart_language' ) ) {
				$name = wp_easycart_language()->convert_text( $name );
			}
			return trim( wp_strip_all_tags( html_entity_decode( (string) $name, ENT_QUOTES, 'UTF-8' ) ) );
		}

		/**
		 * A category's page ( or the store page filtered to it, with the old linking style ).
		 *
		 * @param object $category Row from ec_category.
		 * @return string
		 */
		public static function category_url( $category ) {
			if ( class_exists( 'ec_category' ) ) {
				$item = new ec_category( $category );
				return (string) $item->get_category_link();
			}
			return add_query_arg( 'group_id', (int) $category->category_id, self::store_page_url() );
		}

		/**
		 * Image sizes for the card image control.
		 *
		 * @return array
		 */
		public static function image_size_options() {
			$options = array();
			foreach ( get_intermediate_image_sizes() as $size ) {
				$options[ $size ] = ucwords( str_replace( array( '_', '-' ), ' ', $size ) );
			}
			$options['full'] = __( 'Full size', 'wp-easycart' );
			return $options;
		}
	}

endif;
