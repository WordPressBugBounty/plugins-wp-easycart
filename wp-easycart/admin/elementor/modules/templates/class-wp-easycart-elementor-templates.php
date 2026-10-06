<?php
/**
 * WP EasyCart Elementor templates ( 6.0.2 ): which template a product or category page uses, and drawing it.
 *
 * Names stored in sites ( never rename ):
 * - Elementor document types wp-easycart-product, wp-easycart-category ( library ) and wp-easycart-single-product ( Theme
 *   Builder, Elementor Pro only ).
 * - Options ec_option_elementor_product_template, ec_option_elementor_category_template ( template post id, 0 = WP EasyCart's
 *   standard layout ).
 * - Post meta _wp_easycart_elementor_template on a product's / category's ec_store post ( written by WP EasyCart PRO:
 *   '' store template, 'standard', or a template id ).
 * - Theme Builder conditions wp_easycart ( group ), wp_easycart_product, wp_easycart_product_in_category,
 *   wp_easycart_category, wp_easycart_manufacturer, wp_easycart_store_page, wp_easycart_cart_page, wp_easycart_account_page.
 *
 * Hooks:
 * - filter wp_easycart_elementor_template_for( $template_id, $type, $object_id ): the template for one product / category
 *   ( 'product' | 'category', product_id | category_id ); 0 = standard layout. WP EasyCart PRO answers it with the
 *   per-product / per-category layout.
 * - filter wp_easycart_elementor_starter_template( $content, $type ): Elementor elements for a new template.
 * - filter wp_easycart_elementor_template_hand_off( $allowed, $atts ): false keeps the standard layout for one shortcode.
 * - filter wp_easycart_store_item_as_page ( wpeasycart.php ): answered false when an Elementor Pro Theme Builder single
 *   template with a WP EasyCart condition draws the page.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed -- the storefront hand-off functions ec_storepage calls live next to the class they wrap.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Templates' ) ) :

	/**
	 * Product and category templates.
	 */
	final class WP_EasyCart_Elementor_Templates {

		const PRODUCT_TYPE       = 'wp-easycart-product';
		const CATEGORY_TYPE      = 'wp-easycart-category';
		const THEME_PRODUCT_TYPE = 'wp-easycart-single-product';
		const PRODUCT_OPTION     = 'ec_option_elementor_product_template';
		const CATEGORY_OPTION    = 'ec_option_elementor_category_template';
		const META_KEY           = '_wp_easycart_elementor_template';
		const CREATE_ACTION      = 'wp_easycart_elementor_create_template';
		const STYLE_HANDLE       = 'wpeasycart-elementor-templates';

		/**
		 * How deep EasyCart is inside one of its own template renders ( no template inside a template ).
		 *
		 * @var int
		 */
		private static $rendering = 0;

		/**
		 * Template ids whose CSS file was enqueued in the head.
		 *
		 * @var array
		 */
		private static $css_enqueued = array();

		/**
		 * The request's EasyCart page ( see page() ).
		 *
		 * @var array|null
		 */
		private static $page = null;

		/**
		 * Template id + type => usable.
		 *
		 * @var array
		 */
		private static $usable = array();

		/**
		 * Resolved template per type + object.
		 *
		 * @var array
		 */
		private static $resolved = array();

		/**
		 * Whether a Theme Builder single template with a WP EasyCart condition draws this request.
		 *
		 * @var bool|null
		 */
		private static $theme_builder_match = null;

		/**
		 * Post id lists for the Theme Builder pickers, per kind.
		 *
		 * @var array
		 */
		private static $picker_rows = array();

		/**
		 * Add the hooks.
		 */
		public static function boot() {
			add_action( 'elementor/documents/register', array( __CLASS__, 'register_documents' ) );
			add_action( 'elementor/theme/register_conditions', array( __CLASS__, 'register_conditions' ) );
			add_filter( 'wp_easycart_elementor_widget_classes', array( __CLASS__, 'widget_classes' ) );
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 1 );
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_page_template' ), 20 );
			add_filter( 'wp_easycart_store_item_as_page', array( __CLASS__, 'store_item_as_page' ) );
			add_action( 'admin_post_' . self::CREATE_ACTION, array( __CLASS__, 'create_template' ) );
			foreach ( array( 'product', 'category', 'manufacturer' ) as $kind ) {
				add_filter( 'elementor/query/get_autocomplete/custom/wp_easycart_' . $kind, array( __CLASS__, 'picker_keeps_post' ), 10, 2 );
				add_filter( 'elementor/query/get_value_titles/custom/wp_easycart_' . $kind, array( __CLASS__, 'picker_keeps_post' ), 10, 2 );
				add_filter( 'elementor/query/get_autocomplete/display/wp_easycart_' . $kind, array( __CLASS__, 'picker_label' ), 10, 2 );
				add_filter( 'elementor/query/get_value_titles/display/wp_easycart_' . $kind, array( __CLASS__, 'picker_label' ), 10, 2 );
			}
		}

		// Elementor registration.

		/**
		 * Whether Elementor is loaded and can draw a template.
		 *
		 * @return bool
		 */
		public static function elementor_ready() {
			return did_action( 'elementor/loaded' ) && class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance ) && isset( \Elementor\Plugin::$instance->frontend ) && is_object( \Elementor\Plugin::$instance->frontend );
		}

		/**
		 * Action elementor/documents/register: the two library types ( and the Theme Builder type with Elementor Pro ).
		 *
		 * @param object $documents_manager \Elementor\Core\Documents_Manager.
		 */
		public static function register_documents( $documents_manager ) {
			if ( ! is_object( $documents_manager ) || ! method_exists( $documents_manager, 'register_document_type' ) || ! class_exists( '\Elementor\Modules\Library\Documents\Library_Document' ) ) {
				return;
			}
			require_once __DIR__ . '/documents/class-wp-easycart-elementor-template-documents.php';
			$documents_manager->register_document_type( self::PRODUCT_TYPE, 'WP_EasyCart_Elementor_Product_Template_Document' );
			$documents_manager->register_document_type( self::CATEGORY_TYPE, 'WP_EasyCart_Elementor_Category_Template_Document' );
			if ( class_exists( '\ElementorPro\Modules\ThemeBuilder\Documents\Single_Base' ) ) {
				require_once __DIR__ . '/theme-builder/class-wp-easycart-elementor-theme-product-document.php';
				if ( class_exists( 'WP_EasyCart_Elementor_Theme_Product_Document' ) ) {
					$documents_manager->register_document_type( self::THEME_PRODUCT_TYPE, 'WP_EasyCart_Elementor_Theme_Product_Document' );
				}
			}
		}

		/**
		 * Action elementor/theme/register_conditions ( Elementor Pro only ): the WP EasyCart condition group.
		 *
		 * @param object $conditions_manager Elementor Pro's conditions manager.
		 */
		public static function register_conditions( $conditions_manager ) {
			if ( ! is_object( $conditions_manager ) || ! method_exists( $conditions_manager, 'get_condition' ) || ! class_exists( '\ElementorPro\Modules\ThemeBuilder\Conditions\Condition_Base' ) ) {
				return;
			}
			require_once __DIR__ . '/theme-builder/class-wp-easycart-elementor-templates-conditions.php';
			$general = $conditions_manager->get_condition( 'general' );
			if ( is_object( $general ) && method_exists( $general, 'register_sub_condition' ) && class_exists( 'WP_EasyCart_Elementor_Condition_Store' ) ) {
				$general->register_sub_condition( new WP_EasyCart_Elementor_Condition_Store() );
			}
		}

		/**
		 * Filter wp_easycart_elementor_widget_classes: the category and manufacturer widgets.
		 *
		 * @param array $classes Class name => file.
		 * @return array
		 */
		public static function widget_classes( $classes ) {
			$dir     = __DIR__ . '/widgets/';
			$widgets = array(
				'WP_EasyCart_Elementor_Templates_Category_Title_Widget'       => 'class-wp-easycart-elementor-templates-category-title-widget.php',
				'WP_EasyCart_Elementor_Templates_Category_Description_Widget' => 'class-wp-easycart-elementor-templates-category-description-widget.php',
				'WP_EasyCart_Elementor_Templates_Category_Image_Widget'       => 'class-wp-easycart-elementor-templates-category-image-widget.php',
				'WP_EasyCart_Elementor_Templates_Subcategories_Widget'        => 'class-wp-easycart-elementor-templates-subcategories-widget.php',
				'WP_EasyCart_Elementor_Templates_Manufacturer_Title_Widget'   => 'class-wp-easycart-elementor-templates-manufacturer-title-widget.php',
				'WP_EasyCart_Elementor_Templates_Manufacturer_Description_Widget' => 'class-wp-easycart-elementor-templates-manufacturer-description-widget.php',
				'WP_EasyCart_Elementor_Templates_Manufacturer_Logo_Widget'    => 'class-wp-easycart-elementor-templates-manufacturer-logo-widget.php',
			);
			$classes = is_array( $classes ) ? $classes : array();
			foreach ( $widgets as $class_name => $file ) {
				$classes[ $class_name ] = $dir . $file;
			}
			return $classes;
		}

		/**
		 * The widgets' stylesheet ( registered on every request, enqueued by Elementor with the widgets that need it ).
		 */
		public static function register_assets() {
			wp_register_style( self::STYLE_HANDLE, plugins_url( 'admin/elementor/modules/templates/assets/templates.css', EC_PLUGIN_DIRECTORY . '/wpeasycart.php' ), array(), EC_CURRENT_VERSION );
		}

		// Templates.

		/**
		 * Document type for 'product' | 'category'.
		 *
		 * @param string $type product | category.
		 * @return string
		 */
		public static function document_type( $type ) {
			return ( 'category' === $type ) ? self::CATEGORY_TYPE : self::PRODUCT_TYPE;
		}

		/**
		 * The store-wide option for 'product' | 'category'.
		 *
		 * @param string $type product | category.
		 * @return string
		 */
		public static function option_name( $type ) {
			return ( 'category' === $type ) ? self::CATEGORY_OPTION : self::PRODUCT_OPTION;
		}

		/**
		 * Templates of a type ( every status but trash ), id => post.
		 *
		 * @param string $type product | category.
		 * @return WP_Post[] At most 100.
		 */
		public static function templates( $type ) {
			static $cache = array();
			if ( isset( $cache[ $type ] ) ) {
				return $cache[ $type ];
			}
			$posts = get_posts(
				array(
					'post_type'        => 'elementor_library',
					'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future' ),
					'posts_per_page'   => 100,
					'orderby'          => 'title',
					'order'            => 'ASC',
					'no_found_rows'    => true,
					'suppress_filters' => false,
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Elementor's own template-type taxonomy, at most 200 admin rows.
					'tax_query'        => array(
						array(
							'taxonomy' => 'elementor_library_type',
							'field'    => 'slug',
							'terms'    => self::document_type( $type ),
						),
					),
				)
			);
			$cache[ $type ] = array();
			foreach ( (array) $posts as $post ) {
				if ( $post instanceof WP_Post ) {
					$cache[ $type ][ (int) $post->ID ] = $post;
				}
			}
			return $cache[ $type ];
		}

		/**
		 * Whether a template can draw pages of a type: an Elementor library post of that type, published.
		 *
		 * @param int    $template_id Template post id.
		 * @param string $type        product | category.
		 * @param bool   $any_status  Accept drafts too ( settings screens ).
		 * @return bool
		 */
		public static function is_usable( $template_id, $type, $any_status = false ) {
			$template_id = (int) $template_id;
			if ( $template_id <= 0 ) {
				return false;
			}
			$key = $template_id . ':' . $type . ':' . ( $any_status ? 'a' : 'p' );
			if ( ! isset( self::$usable[ $key ] ) ) {
				$post                 = get_post( $template_id );
				$ok                   = ( $post instanceof WP_Post && 'elementor_library' === $post->post_type && ( $any_status ? 'trash' !== $post->post_status : 'publish' === $post->post_status ) );
				$ok                   = $ok && ( self::document_type( $type ) === (string) get_post_meta( $template_id, '_elementor_template_type', true ) );
				self::$usable[ $key ] = $ok;
			}
			return self::$usable[ $key ];
		}

		/**
		 * The store-wide template for a type ( 0 = standard layout, also when the chosen one is gone or unpublished ).
		 *
		 * @param string $type product | category.
		 * @return int
		 */
		public static function store_template( $type ) {
			$template_id = (int) get_option( self::option_name( $type ), 0 );
			return self::is_usable( $template_id, $type ) ? $template_id : 0;
		}

		/**
		 * The template one product or category page uses ( 0 = WP EasyCart's standard layout ).
		 *
		 * @param string $type      product | category.
		 * @param int    $object_id product_id | category_id.
		 * @return int
		 */
		public static function template_for( $type, $object_id ) {
			$type = ( 'category' === $type ) ? 'category' : 'product';
			$key  = $type . ':' . (int) $object_id;
			if ( isset( self::$resolved[ $key ] ) ) {
				return self::$resolved[ $key ];
			}
			$store = (int) get_option( self::option_name( $type ), 0 );
			/**
			 * The Elementor template a product or category page uses.
			 *
			 * WP EasyCart PRO answers it with the product's / category's own layout. Return 0 for WP EasyCart's standard layout.
			 * The answer must be a published template of the matching type ( EasyCart Product / EasyCart Category ), else the
			 * store's template is used.
			 *
			 * @since 6.0.2
			 *
			 * @param int    $template_id The store's template for the type ( 0 = standard layout ).
			 * @param string $type        'product' or 'category'.
			 * @param int    $object_id   The product_id or category_id.
			 */
			$chosen = (int) apply_filters( 'wp_easycart_elementor_template_for', $store, $type, (int) $object_id );
			if ( $chosen > 0 && ! self::is_usable( $chosen, $type ) ) {
				$chosen = $store;
			}
			if ( $chosen > 0 && ! self::is_usable( $chosen, $type ) ) {
				$chosen = 0;
			}
			self::$resolved[ $key ] = max( 0, $chosen );
			return self::$resolved[ $key ];
		}

		/**
		 * Whether EasyCart is drawing one of its templates right now ( product widgets inside it can skip what the page
		 * already did: the details hooks, structured data ).
		 *
		 * @return bool
		 */
		public static function rendering() {
			return self::$rendering > 0;
		}

		/**
		 * Whether a store shortcode may hand its page to a template.
		 *
		 * @param array|false $atts The shortcode's arguments.
		 * @return bool
		 */
		private static function can_hand_off( $atts ) {
			if ( self::$rendering > 0 || ! is_array( $atts ) || ! empty( $atts['elementor'] ) || ! self::elementor_ready() ) {
				return false;
			}
			/**
			 * Whether this store shortcode may draw its page with an Elementor template.
			 *
			 * @since 6.0.2
			 *
			 * @param bool  $allowed True.
			 * @param array $atts    The [ec_store] arguments.
			 */
			return (bool) apply_filters( 'wp_easycart_elementor_template_hand_off', true, $atts );
		}

		/**
		 * Draw a product page with its template. Called by ec_storepage after the 404, restricted-store and analytics steps.
		 *
		 * @param ec_product  $product The product.
		 * @param array|false $atts    The [ec_store] arguments.
		 * @return bool True when the template drew the page ( the standard layout is skipped ).
		 */
		public static function render_product( $product, $atts ) {
			if ( ! is_object( $product ) || empty( $product->product_id ) || ! self::can_hand_off( $atts ) ) {
				return false;
			}
			// Only on the product's own page ( its store item, or the store page showing it ): a product a merchant placed on
			// another page with [ec_store modelnumber=…] keeps the standard layout.
			$page = self::page();
			if ( 'product' !== $page['type'] || (int) $page['product_id'] !== (int) $product->product_id ) {
				return false;
			}
			$template_id = self::template_for( 'product', (int) $product->product_id );
			if ( ! $template_id ) {
				return false;
			}
			$context = wp_easycart_elementor_context();
			++self::$rendering;
			$context->push_product( $product );
			try {
				$content = self::builder_content( $template_id );
			} finally {
				$context->pop_product();
				--self::$rendering;
			}
			if ( '' === trim( $content ) ) {
				return false;
			}
			if ( function_exists( 'wp_easycart_product_details_no_cache' ) ) {
				wp_easycart_product_details_no_cache();
			}
			/* The same hooks and data as the standard layout ( ec_product_details_page.php ). */
			do_action( 'wp_easycart_product_details_before', $product );
			if ( function_exists( 'wp_easycart_meta_view_content' ) && ! ( function_exists( 'wp_easycart_elementor_is_editor' ) && wp_easycart_elementor_is_editor() ) ) {
				wp_easycart_meta_view_content( $product );
			}
			if ( class_exists( 'wp_easycart_product_schema' ) ) {
				wp_easycart_product_schema::print_for( $product );
			}
			echo '<div class="wpec-el-template wpec-el-template-product" data-wpec-template="' . esc_attr( (string) $template_id ) . '" data-wpec-product="' . esc_attr( (string) (int) $product->product_id ) . '">';
			echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor's rendered template.
			echo '</div>';
			do_action( 'wp_easycart_product_details_end', $product );
			return true;
		}

		/**
		 * The category a category page shows, when its template may draw it ( 0 otherwise ).
		 *
		 * @param string|int  $group_id The [ec_store] groupid ( 'NOGROUP' when none ).
		 * @param array|false $atts     The [ec_store] arguments.
		 * @return int
		 */
		public static function page_category_id( $group_id, $atts ) {
			if ( ! is_array( $atts ) || ! empty( $atts['elementor'] ) ) {
				return 0;
			}
			foreach ( array(
				'menuid'         => 'NOMENU',
				'submenuid'      => 'NOSUBMENU',
				'subsubmenuid'   => 'NOSUBSUBMENU',
				'manufacturerid' => 'NOMANUFACTURER',
			) as $key => $none ) {
				if ( isset( $atts[ $key ] ) && (string) $none !== (string) $atts[ $key ] ) {
					return 0;
				}
			}
			if ( ! empty( $atts['productid'] ) || ! empty( $atts['category'] ) || ! empty( $atts['manufacturer'] ) ) {
				return 0;
			}
			$group = trim( (string) $group_id );
			if ( '' !== $group && 'NOGROUP' !== $group ) {
				$category_id = ctype_digit( $group ) ? (int) $group : 0;
			} else {
				/* Old linking style: category links are the store page with ?group_id=. */
				$category_id = 0;
				// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only storefront routing.
				if ( get_option( 'ec_option_use_old_linking_style' ) && isset( $_GET['group_id'] ) && ! isset( $_GET['model_number'] ) ) {
					$raw = sanitize_text_field( wp_unslash( $_GET['group_id'] ) );
					if ( ctype_digit( $raw ) && self::is_page( get_queried_object_id(), 'ec_option_storepage' ) ) {
						$category_id = (int) $raw;
					}
				}
				// phpcs:enable WordPress.Security.NonceVerification.Recommended
			}
			if ( $category_id <= 0 ) {
				return 0;
			}
			// Only on the category's own page ( its store item, or the store page showing it ): a category a merchant placed on
			// another page with [ec_store groupid=…] keeps the standard layout and its shortcode arguments.
			$page = self::page();
			if ( 'category' !== $page['type'] || (int) $page['category_id'] !== $category_id ) {
				return 0;
			}
			global $wpdb;
			return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT category_id FROM ec_category WHERE category_id = %d', $category_id ) );
		}

		/**
		 * The template a category page will be drawn with ( 0 = standard layout ). ec_storepage asks before it loads the
		 * product list, so a templated page does not run the standard list's queries.
		 *
		 * @param string|int  $group_id The [ec_store] groupid.
		 * @param array|false $atts     The [ec_store] arguments.
		 * @return int
		 */
		public static function category_template_id( $group_id, $atts ) {
			if ( ! self::can_hand_off( $atts ) ) {
				return 0;
			}
			$category_id = self::page_category_id( $group_id, $atts );
			return $category_id ? self::template_for( 'category', $category_id ) : 0;
		}

		/**
		 * Draw a category page with its template. Called by ec_storepage after the restricted-store check and the notices.
		 *
		 * @param string|int  $group_id The [ec_store] groupid.
		 * @param array|false $atts     The [ec_store] arguments.
		 * @return bool True when the template drew the page.
		 */
		public static function render_category( $group_id, $atts ) {
			$template_id = self::category_template_id( $group_id, $atts );
			if ( ! $template_id ) {
				return false;
			}
			$category_id = self::page_category_id( $group_id, $atts );
			$context     = wp_easycart_elementor_context();
			++self::$rendering;
			$context->push_category( $category_id );
			try {
				$content = self::builder_content( $template_id );
			} finally {
				$context->pop_category();
				--self::$rendering;
			}
			if ( '' === trim( $content ) ) {
				return false;
			}
			echo '<div class="wpec-el-template wpec-el-template-category" data-wpec-template="' . esc_attr( (string) $template_id ) . '" data-wpec-category="' . esc_attr( (string) $category_id ) . '">';
			echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor's rendered template.
			echo '</div>';
			return true;
		}

		/**
		 * Elementor's markup for a template ( its CSS inline unless the head already has it ).
		 *
		 * @param int $template_id Template post id.
		 * @return string
		 */
		private static function builder_content( $template_id ) {
			$with_css = empty( self::$css_enqueued[ $template_id ] );
			$content  = \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $template_id, $with_css );
			return is_string( $content ) ? $content : '';
		}

		/**
		 * Action wp_enqueue_scripts ( 20 ): on a product or category page that a template draws, Elementor's styles and the
		 * template's CSS file go in the head, so the page never shows unstyled first.
		 */
		public static function enqueue_page_template() {
			if ( ! self::elementor_ready() || ( function_exists( 'wp_easycart_elementor_is_editor' ) && wp_easycart_elementor_is_editor() ) ) {
				return;
			}
			$page        = self::page();
			$template_id = 0;
			if ( 'product' === $page['type'] && $page['product_id'] ) {
				$template_id = self::template_for( 'product', $page['product_id'] );
			} elseif ( 'category' === $page['type'] && $page['category_id'] ) {
				$template_id = self::template_for( 'category', $page['category_id'] );
			}
			if ( ! $template_id || ! class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
				return;
			}
			$frontend = \Elementor\Plugin::$instance->frontend;
			/* WP EasyCart 6.0.2 ended store item requests drawn with a theme page template on template_redirect ( priority 1 ),
			 * before Elementor's own setup ( priority 10 ); 6.0.3 no longer does ( ec_fix_store_template() is a template_include
			 * filter ), but a theme or plugin that still ends the request early would leave the page with no kit body class, font
			 * links or frontend scripts. init() only adds hooks, and those it needs run after this one. */
			if ( method_exists( $frontend, 'init' ) && false === has_action( 'wp_footer', array( $frontend, 'wp_footer' ) ) ) {
				$frontend->init();
			}
			if ( method_exists( $frontend, 'enqueue_styles' ) ) {
				$frontend->enqueue_styles();
			}
			$css = \Elementor\Core\Files\CSS\Post::create( $template_id );
			if ( is_object( $css ) && method_exists( $css, 'enqueue' ) ) {
				$css->enqueue();
				self::$css_enqueued[ $template_id ] = true;
			}
		}

		// The request.

		/**
		 * A store page option's page id, in the current language ( WPML ).
		 *
		 * @param string $option ec_option_storepage | ec_option_cartpage | ec_option_accountpage.
		 * @return int
		 */
		public static function store_page_id( $option ) {
			$page_id = (int) get_option( $option );
			if ( 'ec_option_accountpage' === $option ) {
				$page_id = (int) apply_filters( 'wp_easycart_account_page_id', $page_id );
			}
			if ( $page_id && function_exists( 'icl_object_id' ) ) {
				$page_id = (int) icl_object_id( $page_id, 'page', true, defined( 'ICL_LANGUAGE_CODE' ) ? ICL_LANGUAGE_CODE : null );
			}
			return $page_id;
		}

		/**
		 * Whether a post is one of the store pages ( the untranslated id counts too ).
		 *
		 * @param int    $post_id Post id.
		 * @param string $option  Page option.
		 * @return bool
		 */
		private static function is_page( $post_id, $option ) {
			$post_id = (int) $post_id;
			return $post_id > 0 && ( self::store_page_id( $option ) === $post_id || (int) get_option( $option ) === $post_id );
		}

		/**
		 * What this request shows: type product | category | manufacturer | store | cart | account | '', with the ids.
		 * Checks EasyCart's own lookups ( the ec_store post's row, the store page options ), never the post type, so it
		 * holds whether or not store items are drawn as pages.
		 *
		 * @return array
		 */
		public static function page() {
			if ( null !== self::$page ) {
				return self::$page;
			}
			$info = array(
				'type'            => '',
				'post_id'         => 0,
				'product_id'      => 0,
				'product_post_id' => 0,
				'category_id'     => 0,
				'manufacturer_id' => 0,
			);
			if ( ! did_action( 'wp' ) || is_admin() ) {
				return $info;
			}
			global $wpdb;
			$post_id         = (int) get_queried_object_id();
			$info['post_id'] = $post_id;
			if ( $post_id ) {
				$product_id = (int) wp_easycart_elementor_context()->current_post_product_id( $post_id );
				if ( $product_id ) {
					$info['type']            = 'product';
					$info['product_id']      = $product_id;
					$info['product_post_id'] = $post_id;
				} else {
					$category_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT category_id FROM ec_category WHERE post_id = %d LIMIT 1', $post_id ) );
					if ( $category_id ) {
						$info['type']        = 'category';
						$info['category_id'] = $category_id;
					} else {
						$manufacturer_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT manufacturer_id FROM ec_manufacturer WHERE post_id = %d LIMIT 1', $post_id ) );
						if ( $manufacturer_id ) {
							$info['type']            = 'manufacturer';
							$info['manufacturer_id'] = $manufacturer_id;
						} elseif ( self::is_page( $post_id, 'ec_option_storepage' ) ) {
							$info['type'] = 'store';
						} elseif ( self::is_page( $post_id, 'ec_option_cartpage' ) ) {
							$info['type'] = 'cart';
						} elseif ( self::is_page( $post_id, 'ec_option_accountpage' ) ) {
							$info['type'] = 'account';
						}
					}
				}
			}

			// The store page also shows products, categories and manufacturers through its query string.
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only storefront routing.
			if ( 'store' === $info['type'] ) {
				if ( isset( $_GET['model_number'] ) ) {
					$row = $wpdb->get_row( $wpdb->prepare( 'SELECT product_id, post_id FROM ec_product WHERE model_number = %s LIMIT 1', sanitize_text_field( wp_unslash( $_GET['model_number'] ) ) ) );
					if ( $row ) {
						$info['type']            = 'product';
						$info['product_id']      = (int) $row->product_id;
						$info['product_post_id'] = (int) $row->post_id;
					}
				} elseif ( isset( $_GET['group_id'] ) && ctype_digit( (string) sanitize_text_field( wp_unslash( $_GET['group_id'] ) ) ) ) {
					$info['type']        = 'category';
					$info['category_id'] = (int) $_GET['group_id'];
				} elseif ( isset( $_GET['manufacturer'] ) && (int) $_GET['manufacturer'] > 0 ) {
					$info['type']            = 'manufacturer';
					$info['manufacturer_id'] = (int) $_GET['manufacturer'];
				}
			}
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			self::$page = $info;
			return self::$page;
		}

		// Elementor Pro Theme Builder.

		/**
		 * Whether one of the WP EasyCart conditions holds for this request.
		 *
		 * @param string $check   store | product | in_category | category | manufacturer | store_page | cart_page | account_page.
		 * @param int    $post_id The chosen item's post id ( 0 = any ).
		 * @return bool
		 */
		public static function condition_check( $check, $post_id = 0 ) {
			$page    = self::page();
			$post_id = (int) $post_id;
			global $wpdb;
			switch ( $check ) {
				case 'store':
					return in_array( $page['type'], array( 'product', 'category', 'manufacturer', 'store', 'cart', 'account' ), true );
				case 'product':
					return 'product' === $page['type'] && ( ! $post_id || $post_id === $page['product_post_id'] );
				case 'in_category':
					if ( 'product' !== $page['type'] ) {
						return false;
					}
					if ( ! $post_id ) {
						return true;
					}
					$category_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT category_id FROM ec_category WHERE post_id = %d LIMIT 1', $post_id ) );
					return $category_id > 0 && (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_categoryitem WHERE category_id = %d AND product_id = %d', $category_id, $page['product_id'] ) ) > 0;
				case 'category':
					if ( 'category' !== $page['type'] ) {
						return false;
					}
					return ! $post_id || $post_id === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT post_id FROM ec_category WHERE category_id = %d', $page['category_id'] ) );
				case 'manufacturer':
					if ( 'manufacturer' !== $page['type'] ) {
						return false;
					}
					return ! $post_id || $post_id === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT post_id FROM ec_manufacturer WHERE manufacturer_id = %d', $page['manufacturer_id'] ) );
				case 'store_page':
					return 'store' === $page['type'];
				case 'cart_page':
					return 'cart' === $page['type'];
				case 'account_page':
					return 'account' === $page['type'];
			}
			return false;
		}

		/**
		 * Condition name => check ( see condition_check() ).
		 *
		 * @return array
		 */
		public static function condition_names() {
			return array(
				'wp_easycart'                     => 'store',
				'wp_easycart_product'             => 'product',
				'wp_easycart_product_in_category' => 'in_category',
				'wp_easycart_category'            => 'category',
				'wp_easycart_manufacturer'        => 'manufacturer',
				'wp_easycart_store_page'          => 'store_page',
				'wp_easycart_cart_page'           => 'cart_page',
				'wp_easycart_account_page'        => 'account_page',
			);
		}

		/**
		 * Elementor Pro's saved Theme Builder conditions for a location, template id => condition strings.
		 *
		 * @param string $location single | archive | header | footer ...
		 * @return array
		 */
		public static function theme_builder_templates( $location ) {
			if ( ! defined( 'ELEMENTOR_PRO_VERSION' ) ) {
				return array();
			}
			$cache = get_option( 'elementor_pro_theme_builder_conditions' );
			return ( is_array( $cache ) && isset( $cache[ $location ] ) && is_array( $cache[ $location ] ) ) ? $cache[ $location ] : array();
		}

		/**
		 * Split a saved condition ( include/name/sub_name/sub_id ).
		 *
		 * @param string $condition Condition string.
		 * @return array type, name, sub_name, sub_id.
		 */
		public static function parse_condition( $condition ) {
			$parts = array_pad( explode( '/', (string) $condition ), 4, '' );
			return array(
				'type'     => $parts[0],
				'name'     => $parts[1],
				'sub_name' => $parts[2],
				'sub_id'   => (int) $parts[3],
			);
		}

		/**
		 * Whether one of our saved conditions holds for this request ( null when it is not one of ours ).
		 *
		 * @param array $parsed parse_condition() result.
		 * @return bool|null
		 */
		private static function our_condition_passes( $parsed ) {
			$names = self::condition_names();
			if ( ! isset( $names[ $parsed['name'] ] ) ) {
				return null;
			}
			if ( 'wp_easycart' !== $parsed['name'] ) {
				/* One of our sub-conditions saved on its own ( the Single Product template's default condition ). */
				return self::condition_check( $names[ $parsed['name'] ], 0 );
			}
			if ( ! self::condition_check( 'store' ) ) {
				return false;
			}
			if ( '' === $parsed['sub_name'] ) {
				return true;
			}
			return isset( $names[ $parsed['sub_name'] ] ) ? self::condition_check( $names[ $parsed['sub_name'] ], $parsed['sub_id'] ) : false;
		}

		/**
		 * Whether a published Elementor Pro Theme Builder single template with a WP EasyCart condition draws this request.
		 *
		 * @return bool
		 */
		public static function theme_builder_match() {
			if ( null !== self::$theme_builder_match ) {
				return self::$theme_builder_match;
			}
			$match = false;
			foreach ( self::theme_builder_templates( 'single' ) as $template_id => $conditions ) {
				if ( 'publish' !== get_post_status( (int) $template_id ) ) {
					continue;
				}
				$include = false;
				$exclude = false;
				foreach ( (array) $conditions as $condition ) {
					$parsed = self::parse_condition( $condition );
					$passes = self::our_condition_passes( $parsed );
					if ( true !== $passes ) {
						continue;
					}
					if ( 'exclude' === $parsed['type'] ) {
						$exclude = true;
					} else {
						$include = true;
					}
				}
				if ( $include && ! $exclude ) {
					$match = true;
					break;
				}
			}
			if ( did_action( 'wp' ) ) {
				self::$theme_builder_match = $match;
			}
			return $match;
		}

		/**
		 * Filter wp_easycart_store_item_as_page: a product, category or manufacturer page that an Elementor Pro Theme Builder
		 * template with a WP EasyCart condition draws stays a store item ( is_singular( 'ec_store' ) ), and the store page's
		 * theme template does not take it over, so Elementor Pro draws it. Only when Elementor and Elementor Pro's Theme
		 * Builder are running and Elementor Pro confirms it has a single template for the request; unchanged for every other
		 * store.
		 *
		 * @param bool $as_page True.
		 * @return bool
		 */
		public static function store_item_as_page( $as_page ) {
			if ( ! $as_page || ! defined( 'ELEMENTOR_PRO_VERSION' ) || ! self::elementor_ready() || ! class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ) ) {
				return $as_page;
			}
			if ( ! self::theme_builder_match() ) {
				return $as_page;
			}
			return ( false === self::pro_draws_single() ) ? $as_page : false;
		}

		/**
		 * Whether Elementor Pro has a single template for this request ( asked while the page is still a store item ); null
		 * when its conditions manager cannot be asked ( then the WP EasyCart condition match decides ).
		 *
		 * @return bool|null
		 */
		private static function pro_draws_single() {
			static $answer = 'unknown';
			if ( 'unknown' !== $answer ) {
				return $answer;
			}
			$result = null;
			try {
				$module  = \ElementorPro\Modules\ThemeBuilder\Module::instance();
				$manager = ( is_object( $module ) && method_exists( $module, 'get_conditions_manager' ) ) ? $module->get_conditions_manager() : null;
				if ( is_object( $manager ) && method_exists( $manager, 'get_documents_for_location' ) ) {
					$documents = $manager->get_documents_for_location( 'single' );
					$result    = ! empty( $documents );
					if ( ! $result && method_exists( $manager, 'clear_location_cache' ) ) {
						/* The page stays a page: let Elementor Pro look again when it draws the page. */
						$manager->clear_location_cache();
					}
				}
			} catch ( \Throwable $e ) {
				$result = null;
			}
			if ( did_action( 'wp' ) ) {
				$answer = $result;
			}
			return $result;
		}

		/**
		 * Rows the Theme Builder pickers may show, post id => label.
		 *
		 * @param string $kind product | category | manufacturer.
		 * @return array
		 */
		private static function picker_rows( $kind ) {
			if ( isset( self::$picker_rows[ $kind ] ) ) {
				return self::$picker_rows[ $kind ];
			}
			global $wpdb;
			$rows = array();
			if ( 'product' === $kind ) {
				foreach ( (array) $wpdb->get_results( 'SELECT post_id, model_number FROM ec_product WHERE post_id > 0' ) as $row ) {
					$rows[ (int) $row->post_id ] = (string) $row->model_number;
				}
			} else {
				$ids  = ( 'category' === $kind ) ? $wpdb->get_col( 'SELECT post_id FROM ec_category WHERE post_id > 0' ) : $wpdb->get_col( 'SELECT post_id FROM ec_manufacturer WHERE post_id > 0' );
				$rows = array_fill_keys( array_map( 'intval', (array) $ids ), '' );
			}
			self::$picker_rows[ $kind ] = $rows;
			return $rows;
		}

		/**
		 * The kind a picker filter is for ( from the running filter's name ).
		 *
		 * @return string
		 */
		private static function picker_kind() {
			$filter = (string) current_filter();
			foreach ( array( 'product', 'category', 'manufacturer' ) as $kind ) {
				if ( substr( $filter, -strlen( '/wp_easycart_' . $kind ) ) === '/wp_easycart_' . $kind ) {
					return $kind;
				}
			}
			return '';
		}

		/**
		 * Filters elementor/query/get_autocomplete/custom/wp_easycart_{kind}: the Theme Builder pickers list only store items
		 * of their kind ( products, categories or manufacturers are all ec_store posts ).
		 *
		 * @param bool    $keep True.
		 * @param WP_Post $post The store item.
		 * @return bool
		 */
		public static function picker_keeps_post( $keep, $post ) {
			$kind = self::picker_kind();
			if ( ! $keep || '' === $kind || ! is_object( $post ) || ! isset( $post->ID ) ) {
				return $keep;
			}
			$rows = self::picker_rows( $kind );
			return isset( $rows[ (int) $post->ID ] );
		}

		/**
		 * Filters elementor/query/get_autocomplete/display/wp_easycart_{kind}: the product's SKU after its name.
		 *
		 * @param string $title   Post title.
		 * @param int    $post_id Post id.
		 * @return string
		 */
		public static function picker_label( $title, $post_id = 0 ) {
			if ( 'product' === self::picker_kind() ) {
				$rows = self::picker_rows( 'product' );
				if ( ! empty( $rows[ (int) $post_id ] ) ) {
					/* translators: 1: product name, 2: SKU. */
					return sprintf( __( '%1$s ( %2$s )', 'wp-easycart' ), $title, $rows[ (int) $post_id ] );
				}
			}
			return $title;
		}

		// Editor preview.

		/**
		 * A document setting as an id ( the pickers may save an array ).
		 *
		 * @param object $document Elementor document.
		 * @param string $key      Setting.
		 * @return int
		 */
		public static function document_id_setting( $document, $key ) {
			$value = is_object( $document ) && method_exists( $document, 'get_settings' ) ? $document->get_settings( $key ) : 0;
			if ( is_array( $value ) ) {
				$value = reset( $value );
			}
			return (int) $value;
		}

		/**
		 * The first active category ( the default preview ).
		 *
		 * @return int
		 */
		public static function sample_category_id() {
			static $sample = null;
			$filtered      = (int) apply_filters( 'wp_easycart_elementor_preview_category_id', 0 );
			if ( $filtered ) {
				return $filtered;
			}
			if ( null === $sample ) {
				global $wpdb;
				$sample = (int) $wpdb->get_var( 'SELECT category_id FROM ec_category WHERE is_active = 1 ORDER BY priority DESC, category_name ASC LIMIT 1' );
			}
			return $sample;
		}

		// Creating a template.

		/**
		 * Whether the current user may create and assign store templates.
		 *
		 * @return bool
		 */
		public static function user_can_manage() {
			return ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_settings' ) ) && current_user_can( 'edit_posts' );
		}

		/**
		 * The "Create a … template" link ( admin-post, nonce ).
		 *
		 * @param string $type product | category.
		 * @return string
		 */
		public static function create_url( $type ) {
			return wp_nonce_url( admin_url( 'admin-post.php?action=' . self::CREATE_ACTION . '&type=' . ( 'category' === $type ? 'category' : 'product' ) ), 'wp-easycart-elementor-create-' . ( 'category' === $type ? 'category' : 'product' ) );
		}

		/**
		 * Where Elementor edits a template.
		 *
		 * @param int $template_id Template post id.
		 * @return string
		 */
		public static function edit_url( $template_id ) {
			return add_query_arg(
				array(
					'post'   => (int) $template_id,
					'action' => 'elementor',
				),
				admin_url( 'post.php' )
			);
		}

		/**
		 * Elementor elements a new template starts with.
		 *
		 * @param string $type product | category.
		 * @return array
		 */
		public static function starter_content( $type ) {
			$content = array();
			if ( 'category' === $type ) {
				$content = self::category_starter();
			}
			/**
			 * The Elementor elements a new EasyCart Product / Category template starts with ( the same array Elementor saves as
			 * _elementor_data ). Return an empty array for an empty template.
			 *
			 * @since 6.0.2
			 *
			 * @param array  $content Elements.
			 * @param string $type    'product' or 'category'.
			 */
			$content = apply_filters( 'wp_easycart_elementor_starter_template', $content, $type );
			return is_array( $content ) ? array_values( $content ) : array();
		}

		/**
		 * A category page to start from: title, description, subcategories and the products of the category.
		 *
		 * @return array
		 */
		private static function category_starter() {
			$widget     = function ( $type, $settings = array() ) {
				return array(
					'id'         => substr( md5( uniqid( $type, true ) ), 0, 7 ),
					'elType'     => 'widget',
					'widgetType' => $type,
					'settings'   => $settings,
					'elements'   => array(),
				);
			};
			$widgets    = array(
				$widget( 'wp_easycart_category_title' ),
				$widget( 'wp_easycart_category_description' ),
				$widget( 'wp_easycart_subcategories' ),
				$widget( 'wp_easycart_shop', array( 'ec_filter_categories' => '' ) ),
			);
			$containers = ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->experiments ) && method_exists( \Elementor\Plugin::$instance->experiments, 'is_feature_active' ) && \Elementor\Plugin::$instance->experiments->is_feature_active( 'container' ) );
			if ( $containers ) {
				return array(
					array(
						'id'       => substr( md5( uniqid( 'container', true ) ), 0, 7 ),
						'elType'   => 'container',
						'settings' => array(),
						'elements' => $widgets,
						'isInner'  => false,
					),
				);
			}
			return array(
				array(
					'id'       => substr( md5( uniqid( 'section', true ) ), 0, 7 ),
					'elType'   => 'section',
					'settings' => array(),
					'isInner'  => false,
					'elements' => array(
						array(
							'id'       => substr( md5( uniqid( 'column', true ) ), 0, 7 ),
							'elType'   => 'column',
							'settings' => array( '_column_size' => 100 ),
							'isInner'  => false,
							'elements' => $widgets,
						),
					),
				),
			);
		}

		/**
		 * Admin-post wp_easycart_elementor_create_template: make a draft EasyCart Product / Category template, make it the
		 * store's template when none is chosen yet ( it is used once published ), and open it in Elementor.
		 */
		public static function create_template() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which nonce to check; verified on the next line.
			$type = ( isset( $_GET['type'] ) && 'category' === sanitize_key( wp_unslash( $_GET['type'] ) ) ) ? 'category' : 'product';
			check_admin_referer( 'wp-easycart-elementor-create-' . $type );
			if ( ! self::user_can_manage() ) {
				wp_die( esc_html__( 'You are not allowed to create store templates.', 'wp-easycart' ), 403 );
			}
			if ( ! self::elementor_ready() || ! isset( \Elementor\Plugin::$instance->documents ) ) {
				wp_die( esc_html__( 'Activate Elementor to design store pages.', 'wp-easycart' ) );
			}
			$documents = \Elementor\Plugin::$instance->documents;
			if ( ! $documents->get_document_type( self::document_type( $type ), false ) ) {
				wp_die( esc_html__( 'This version of Elementor cannot hold WP EasyCart templates. Update Elementor and try again.', 'wp-easycart' ) );
			}
			$title    = ( 'category' === $type ) ? __( 'Category page', 'wp-easycart' ) : __( 'Product page', 'wp-easycart' );
			$document = $documents->create(
				self::document_type( $type ),
				array(
					'post_title'  => $title,
					'post_status' => 'draft',
				)
			);
			if ( is_wp_error( $document ) || ! is_object( $document ) ) {
				wp_die( esc_html( is_wp_error( $document ) ? $document->get_error_message() : __( 'The template could not be created.', 'wp-easycart' ) ) );
			}
			$template_id = (int) $document->get_main_id();
			$content     = self::starter_content( $type );
			if ( ! empty( $content ) && method_exists( $document, 'save' ) ) {
				$document->save( array( 'elements' => $content ) );
			}
			$current = (int) get_option( self::option_name( $type ), 0 );
			if ( ! self::is_usable( $current, $type, true ) ) {
				update_option( self::option_name( $type ), $template_id );
			}
			wp_safe_redirect( self::edit_url( $template_id ) );
			exit;
		}
	}

endif;

if ( ! function_exists( 'wp_easycart_elementor_render_product_template' ) ) {
	/**
	 * Draw a product page with its Elementor template ( ec_storepage::display_product_details_page() ).
	 *
	 * @since 6.0.2
	 *
	 * @param ec_product  $product The product.
	 * @param array|false $atts    The [ec_store] arguments.
	 * @return bool True when the template drew the page.
	 */
	function wp_easycart_elementor_render_product_template( $product, $atts ) {
		return WP_EasyCart_Elementor_Templates::render_product( $product, $atts );
	}
}

if ( ! function_exists( 'wp_easycart_elementor_category_template_id' ) ) {
	/**
	 * The Elementor template a category page will be drawn with ( 0 = WP EasyCart's standard layout ).
	 *
	 * @since 6.0.2
	 *
	 * @param string|int  $group_id The [ec_store] groupid.
	 * @param array|false $atts     The [ec_store] arguments.
	 * @return int
	 */
	function wp_easycart_elementor_category_template_id( $group_id, $atts ) {
		return WP_EasyCart_Elementor_Templates::category_template_id( $group_id, $atts );
	}
}

if ( ! function_exists( 'wp_easycart_elementor_render_category_template' ) ) {
	/**
	 * Draw a category page with its Elementor template ( ec_storepage::display_store_page() ).
	 *
	 * @since 6.0.2
	 *
	 * @param string|int  $group_id The [ec_store] groupid.
	 * @param array|false $atts     The [ec_store] arguments.
	 * @return bool True when the template drew the page.
	 */
	function wp_easycart_elementor_render_category_template( $group_id, $atts ) {
		return WP_EasyCart_Elementor_Templates::render_category( $group_id, $atts );
	}
}

if ( ! function_exists( 'wp_easycart_elementor_template_rendering' ) ) {
	/**
	 * Whether WP EasyCart is drawing a product or category page with an Elementor template right now. Product widgets
	 * can skip what the page already did ( the details hooks, structured data, view events ).
	 *
	 * @since 6.0.2
	 *
	 * @return bool
	 */
	function wp_easycart_elementor_template_rendering() {
		return WP_EasyCart_Elementor_Templates::rendering();
	}
}

if ( ! function_exists( 'wp_easycart_elementor_templates_manufacturer' ) ) {
	/**
	 * The manufacturer a manufacturer widget shows ( row from ec_manufacturer ), or null.
	 *
	 * Order: a picked one; this page's manufacturer ( a manufacturer page, or the product of a product page ); in the
	 * Elementor editor a sample ( the first manufacturer with a page ).
	 *
	 * @since 6.0.2
	 *
	 * @param array $args 'source' => 'current' | 'pick', 'manufacturer_id' => int, 'allow_sample' => bool.
	 * @return object|null
	 */
	function wp_easycart_elementor_templates_manufacturer( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'source'          => 'current',
				'manufacturer_id' => 0,
				'allow_sample'    => true,
			)
		);
		global $wpdb;
		$context = wp_easycart_elementor_context();
		$id      = ( 'pick' === $args['source'] ) ? (int) $args['manufacturer_id'] : 0;
		if ( ! $id && 'pick' !== $args['source'] ) {
			$row = $context->manufacturer();
			if ( $row ) {
				return $row;
			}
			$product = $context->product(
				array(
					'allow_sample' => false,
					'details'      => false,
				)
			);
			if ( $product && ! empty( $product->manufacturer_id ) ) {
				$id = (int) $product->manufacturer_id;
			}
		}
		if ( ! $id && $args['allow_sample'] && $context->is_editor() ) {
			$product = $context->product( array( 'details' => false ) );
			$id      = ( $product && ! empty( $product->manufacturer_id ) ) ? (int) $product->manufacturer_id : 0;
			if ( ! $id ) {
				$id = (int) $wpdb->get_var( 'SELECT manufacturer_id FROM ec_manufacturer WHERE post_id > 0 ORDER BY name ASC LIMIT 1' );
			}
		}
		if ( ! $id ) {
			return null;
		}
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_manufacturer WHERE manufacturer_id = %d', $id ) );
		return $row ? $row : null;
	}
}

if ( ! function_exists( 'wp_easycart_elementor_templates_link' ) ) {
	/**
	 * The storefront address of a category or manufacturer ( its page, or the store page's filter with the old linking style ).
	 *
	 * @since 6.0.2
	 *
	 * @param string $kind category | manufacturer.
	 * @param object $row  ec_category / ec_manufacturer row.
	 * @return string
	 */
	function wp_easycart_elementor_templates_link( $kind, $row ) {
		if ( ! is_object( $row ) ) {
			return '';
		}
		if ( ! get_option( 'ec_option_use_old_linking_style' ) && ! empty( $row->post_id ) ) {
			$link = get_permalink( (int) $row->post_id );
			if ( $link ) {
				return $link;
			}
		}
		$store = get_permalink( WP_EasyCart_Elementor_Templates::store_page_id( 'ec_option_storepage' ) );
		if ( ! $store ) {
			return '';
		}
		if ( 'manufacturer' === $kind ) {
			return add_query_arg( 'manufacturer', (int) $row->manufacturer_id, $store );
		}
		return add_query_arg( 'group_id', (int) $row->category_id, $store );
	}
}

if ( ! function_exists( 'wp_easycart_elementor_templates_category_image_url' ) ) {
	/**
	 * The address of a category's image ( ec_category.image: a URL or a file under wp-easycart-data ), '' when none.
	 *
	 * @since 6.0.2
	 *
	 * @param string $image The stored value.
	 * @return string
	 */
	function wp_easycart_elementor_templates_category_image_url( $image ) {
		$image = trim( (string) $image );
		if ( '' === $image ) {
			return '';
		}
		if ( 0 === strpos( $image, 'http://' ) || 0 === strpos( $image, 'https://' ) || 0 === strpos( $image, '//' ) ) {
			return $image;
		}
		$file = ltrim( str_replace( '..', '', $image ), '/' );
		if ( defined( 'EC_PLUGIN_DATA_DIRECTORY' ) && is_file( EC_PLUGIN_DATA_DIRECTORY . '/products/categories/' . $file ) ) {
			return plugins_url( '/wp-easycart-data/products/categories/' . $file, EC_PLUGIN_DATA_DIRECTORY );
		}
		return '';
	}
}
