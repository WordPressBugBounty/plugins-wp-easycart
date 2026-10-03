<?php
/**
 * WP EasyCart Wrap Class for Elementor
 *
 * @category Class
 * @package  WP_EasyCart_Elementor
 * @author   WP EasyCart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once EC_PLUGIN_DIRECTORY . '/admin/elementor/wp-easycart-elementor-functions.php';
require_once EC_PLUGIN_DIRECTORY . '/admin/elementor/base/class-wp-easycart-elementor-context.php';
require_once EC_PLUGIN_DIRECTORY . '/admin/elementor/base/class-wp-easycart-elementor-editor.php';

/**
 * WP EasyCart Wrap Class for Elementor
 *
 * @category Class
 * @package  Wp_Easycart_Controls_Manager
 * @author   WP EasyCart
 */
class WP_EasyCart_Elementor {

	/**
	 * Widget classes, file slug => class name. The widget names they register ( get_name() ) are stored in every saved
	 * Elementor page, so a widget is never renamed or dropped from this list; retire one by hiding it from the panel.
	 *
	 * @var array
	 */
	private $widgets = array(
		'cart-icon'                         => 'Wp_Easycart_Elementor_Cart_Icon_Widget',
		'store'                             => 'Wp_Easycart_Elementor_Store_Widget',
		'product'                           => 'Wp_Easycart_Elementor_Product_Widget',
		'product-details'                   => 'Wp_Easycart_Elementor_Product_Details_Widget',
		'product-details-breadcrumbs'       => 'Wp_Easycart_Elementor_Product_Details_Breadcrumbs_Widget',
		'product-details-title'             => 'Wp_Easycart_Elementor_Product_Details_Title_Widget',
		'product-details-tabs'              => 'Wp_Easycart_Elementor_Product_Details_Tabs_Widget',
		'product-details-description'       => 'Wp_Easycart_Elementor_Product_Details_Description_Widget',
		'product-details-specifications'    => 'Wp_Easycart_Elementor_Product_Details_Specifications_Widget',
		'product-details-customer-reviews'  => 'Wp_Easycart_Elementor_Product_Details_Customer_Reviews_Widget',
		'product-details-images'            => 'Wp_Easycart_Elementor_Product_Details_Images_Widget',
		'product-details-price'             => 'Wp_Easycart_Elementor_Product_Details_Price_Widget',
		'product-details-rating'            => 'Wp_Easycart_Elementor_Product_Details_Rating_Widget',
		'product-details-stock'             => 'Wp_Easycart_Elementor_Product_Details_Stock_Widget',
		'product-details-short-description' => 'Wp_Easycart_Elementor_Product_Details_Short_Description_Widget',
		'product-details-sku'               => 'Wp_Easycart_Elementor_Product_Details_Sku_Widget',
		'product-details-social'            => 'Wp_Easycart_Elementor_Product_Details_Social_Widget',
		'product-details-category'          => 'Wp_Easycart_Elementor_Product_Details_Category_Widget',
		'product-details-manufacturer'      => 'Wp_Easycart_Elementor_Product_Details_Manufacturer_Widget',
		'product-details-meta'              => 'Wp_Easycart_Elementor_Product_Details_Meta_Widget',
		'product-details-featured-products' => 'Wp_Easycart_Elementor_Product_Details_Featured_Products_Widget',
		'product-addtocart'                 => 'Wp_Easycart_Elementor_Product_Addtocart_Widget',
		'search'                            => 'Wp_Easycart_Elementor_Search_Widget',
	);

	/**
	 * WP EasyCart Elementor Constructor
	 */
	public function __construct() {
		add_action( 'elementor/editor/before_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'elementor/elements/categories_registered', array( $this, 'register_elementor_categories' ), 10, 1 );

		/*
		 * 6.0.2: Elementor 3.5 replaced these hooks ( the old ones still fire, with deprecation notices ). Hook one set
		 * only, or every widget would register twice.
		 */
		if ( self::modern_api() ) {
			add_action( 'elementor/controls/register', array( $this, 'register_elementor_controls' ) );
			add_action( 'elementor/widgets/register', array( $this, 'register_elementor_widgets' ), 10, 1 );
		} else {
			add_action( 'elementor/controls/controls_registered', array( $this, 'register_elementor_controls' ) );
			add_action( 'elementor/widgets/widgets_registered', array( $this, 'register_elementor_widgets' ), 10, 1 );
		}

		include EC_PLUGIN_DIRECTORY . '/admin/elementor/class-wp-easycart-controls-manager.php';

		/*
		 * 6.0.2: the store's scripts and styles are registered early on every page, so widgets can list them in
		 * get_script_depends() / get_style_depends() ( Elementor then loads them only with the widget, also where a filter
		 * keeps EasyCart's own loader off ).
		 */
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 1 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_late_assets' ), 11 );
		add_action( 'elementor/documents/register_controls', array( 'WP_EasyCart_Elementor_Context', 'register_document_controls' ) );
		add_action( 'admin_init', array( __CLASS__, 'refresh_elementor_cache' ) );

		$this->load_modules();
	}

	/**
	 * Widget names registered with Elementor in this request ( name => true ).
	 *
	 * @var array
	 */
	private static $registered = array();

	/**
	 * Whether a widget was registered under this name ( 6.0.2; answers once Elementor registered its widgets ).
	 *
	 * @param string $name Widget name.
	 * @return bool
	 */
	public static function is_widget_registered( $name ) {
		return is_string( $name ) && isset( self::$registered[ $name ] );
	}

	/**
	 * Registers ( never enqueues ) the store's scripts and styles with the same sources, dependencies and versions as
	 * ec_js_loader_v3() / ec_css_loader_v3(), whose later enqueue calls then find them registered and change nothing; plus
	 * the shared widget stylesheet `wpeasycart-elementor` ( 6.0.2 ). Handles for widgets: wpeasycart_js, wpeasycart_css,
	 * wpeasycart_owl_carousel_js, wpeasycart_owl_carousel_css, wpeasycart-elementor.
	 */
	public static function register_assets() {
		$data_theme   = EC_PLUGIN_DATA_DIRECTORY . '/design/theme/' . get_option( 'ec_option_base_theme' );
		$latest_theme = get_option( 'ec_option_latest_theme' );

		if ( ! wp_script_is( 'wpeasycart_js', 'registered' ) ) {
			$dependency_list = array( 'jquery', 'jquery-ui-core' );
			if ( ! get_option( 'ec_option_exclude_accordion' ) ) {
				$dependency_list[] = 'jquery-ui-accordion';
			}
			if ( ! get_option( 'ec_option_exclude_datepicker' ) ) {
				$dependency_list[] = 'jquery-ui-datepicker';
			}
			if ( file_exists( $data_theme . '/ec-store.js' ) ) {
				wp_register_script( 'wpeasycart_js', plugins_url( 'wp-easycart-data/design/theme/' . get_option( 'ec_option_base_theme' ) . '/ec-store.js', EC_PLUGIN_DATA_DIRECTORY ), $dependency_list, EC_CURRENT_VERSION, false );
			} elseif ( get_option( 'ec_option_enabled_minified_scripts' ) ) {
				wp_register_script( 'wpeasycart_js', plugins_url( 'wp-easycart/design/theme/' . $latest_theme . '/ec-store.min.js', EC_PLUGIN_DIRECTORY ), $dependency_list, EC_CURRENT_VERSION, false );
			} else {
				wp_register_script( 'wpeasycart_js', plugins_url( 'wp-easycart/design/theme/' . $latest_theme . '/ec-store.js', EC_PLUGIN_DIRECTORY ), $dependency_list, EC_CURRENT_VERSION, false );
			}
		}
		if ( ! wp_script_is( 'wpeasycart_owl_carousel_js', 'registered' ) ) {
			wp_register_script( 'wpeasycart_owl_carousel_js', plugins_url( 'wp-easycart/design/theme/' . $latest_theme . '/owl.carousel.min.js', EC_PLUGIN_DATA_DIRECTORY ), array( 'jquery' ), EC_CURRENT_VERSION, false );
		}
		if ( ! wp_style_is( 'wpeasycart_owl_carousel_css', 'registered' ) ) {
			wp_register_style( 'wpeasycart_owl_carousel_css', plugins_url( 'wp-easycart/design/theme/' . $latest_theme . '/owl.carousel.css', EC_PLUGIN_DIRECTORY ) ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- the same registration as ec_js_loader_v3(), which enqueues it later.
		}
		if ( ! wp_style_is( 'wpeasycart_css', 'registered' ) ) {
			if ( file_exists( $data_theme . '/ec-store.css' ) ) {
				wp_register_style( 'wpeasycart_css', plugins_url( 'wp-easycart-data/design/theme/' . get_option( 'ec_option_base_theme' ) . '/ec-store.css', EC_PLUGIN_DATA_DIRECTORY ), array( 'jquery-ui' ), EC_CURRENT_VERSION );
			} elseif ( get_option( 'ec_option_enabled_minified_scripts' ) ) {
				wp_register_style( 'wpeasycart_css', plugins_url( 'wp-easycart/design/theme/' . $latest_theme . '/ec-store.min.css', EC_PLUGIN_DIRECTORY ), array( 'jquery-ui' ), EC_CURRENT_VERSION );
			} else {
				wp_register_style( 'wpeasycart_css', plugins_url( 'wp-easycart/design/theme/' . $latest_theme . '/ec-store.css', EC_PLUGIN_DIRECTORY ), array( 'jquery-ui' ), EC_CURRENT_VERSION );
			}
		}
		if ( ! wp_style_is( 'wpeasycart-elementor', 'registered' ) ) {
			wp_register_style( 'wpeasycart-elementor', plugins_url( 'admin/elementor/assets/wpeasycart-elementor.css', EC_PLUGIN_DIRECTORY . '/wpeasycart.php' ), array(), EC_CURRENT_VERSION );
		}
	}

	/**
	 * The generic `jquery-ui` stylesheet the store stylesheet depends on, registered late ( wp_enqueue_scripts 11 ) and only
	 * when nobody registered one: ec_css_loader_v3() registers it at 10 when EasyCart's CSS loads, and a theme's or another
	 * plugin's own jquery-ui stylesheet always wins ( 6.0.2 ). Needed where a filter keeps EasyCart's CSS off and a widget
	 * still lists wpeasycart_css.
	 */
	public static function register_late_assets() {
		if ( wp_style_is( 'jquery-ui', 'registered' ) ) {
			return;
		}
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/theme/' . get_option( 'ec_option_base_theme' ) . '/smoothness-jquery-ui.min.css' ) ) {
			wp_register_style( 'jquery-ui', plugins_url( 'wp-easycart-data/design/theme/' . get_option( 'ec_option_base_theme' ) . '/smoothness-jquery-ui.min.css', EC_PLUGIN_DATA_DIRECTORY ) ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- the same registration as ec_css_loader_v3().
		} else {
			wp_register_style( 'jquery-ui', plugins_url( 'wp-easycart/design/theme/' . get_option( 'ec_option_latest_theme' ) . '/smoothness-jquery-ui.min.css', EC_PLUGIN_DIRECTORY ) ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- the same registration as ec_css_loader_v3().
		}
	}

	/**
	 * Version of the widgets' asset lists ( 6.0.2-r11: bug round 11 added WP EasyCart Tabs' files to Product Tabs and changed
	 * control selectors ). Raise it in a release that changes what widgets list in get_script_depends() /
	 * get_style_depends(): Elementor keeps those per page ( _elementor_page_assets ) and the next admin visit clears its cache.
	 */
	const ASSETS_VERSION = '6.0.2-r11';

	/**
	 * Elementor keeps each page's widget scripts and styles in post meta ( _elementor_page_assets ) and its CSS files; the
	 * 6.0.2 widgets changed what they list, so the cache is cleared once, on the first admin visit of a store manager after
	 * the update, and again only when ASSETS_VERSION changes ( 6.0.2 ).
	 */
	public static function refresh_elementor_cache() {
		if ( self::ASSETS_VERSION === get_option( 'wp_easycart_elementor_assets_version' ) || ! did_action( 'elementor/loaded' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		update_option( 'wp_easycart_elementor_assets_version', self::ASSETS_VERSION, false );
		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) && method_exists( \Elementor\Plugin::$instance->files_manager, 'clear_cache' ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}
	}

	/**
	 * Where the Elementor how-to guides live on docs.wpeasycart.com.
	 */
	const HELP_BASE = 'https://docs.wpeasycart.com/docs/how-to-guides/';

	/**
	 * The Elementor how-to guides: key => array( slug, title ), in reading order ( Settings › Elementor lists them ).
	 *
	 * @since 6.0.2
	 * @return array
	 */
	public static function help_guides() {
		return array(
			'overview' => array( 'elementor-connect-and-widgets-overview', __( 'Connect EasyCart and meet the widgets', 'wp-easycart' ) ),
			'product'  => array( 'elementor-build-a-product-page-template', __( 'Build a product page template', 'wp-easycart' ) ),
			'shop'     => array( 'elementor-build-your-store-and-category-pages', __( 'Build your store and category pages', 'wp-easycart' ) ),
			'category' => array( 'elementor-build-a-category-and-manufacturer-template', __( 'Build a category and manufacturer template', 'wp-easycart' ) ),
			'cart'     => array( 'elementor-design-cart-and-checkout', __( 'Design the cart and checkout', 'wp-easycart' ) ),
			'account'  => array( 'elementor-design-the-account-area', __( 'Design the account area', 'wp-easycart' ) ),
			'dynamic'  => array( 'elementor-put-store-data-anywhere', __( 'Put store data anywhere with dynamic tags', 'wp-easycart' ) ),
		);
	}

	/**
	 * The guide, and the section in it, that explains one widget or screen ( the widgets' $ec_help slugs, the Site Settings
	 * tab, the Theme Builder product type ). Unknown topics get the overview guide. Filter wp_easycart_elementor_help_url.
	 *
	 * @since 6.0.2
	 * @param string $topic Help topic ( a widget's $ec_help slug, 'site-settings', 'single-product', a guide key ).
	 * @param string $name  What asks: a widget's get_name(), the Site Settings tab id … ( passed to the filter ).
	 * @return string
	 */
	public static function help_url( $topic = '', $name = '' ) {
		$product = array( 'product', 'widgets' );
		$map     = array(
			''                                  => array( 'overview', '' ),
			'site-settings'                     => array( 'overview', 'site-settings' ),
			'starter'                           => array( 'overview', 'starter' ),
			'single-product'                    => array( 'product', 'theme-builder' ),
			'add-to-cart'                       => $product,
			'product-badges'                    => $product,
			'product-gallery'                   => $product,
			'product-price'                     => $product,
			'product-sku'                       => $product,
			'product-stock'                     => $product,
			'product-breadcrumbs'               => $product,
			'product-description'               => $product,
			'product-short-description'         => $product,
			'product-meta'                      => $product,
			'product-rating'                    => $product,
			'product-reviews'                   => $product,
			'product-share'                     => $product,
			'product-specifications'            => $product,
			'product-tabs'                      => $product,
			'product-title'                     => $product,
			'related-products'                  => $product,
			'products'                          => array( 'shop', 'products' ),
			'product-carousel'                  => array( 'shop', 'carousel' ),
			'product-categories'                => array( 'shop', 'carousel' ),
			'shop'                              => array( 'shop', 'shop' ),
			'product-search'                    => array( 'shop', 'search' ),
			'category-and-manufacturer-widgets' => array( 'category', 'widgets' ),
			'cart'                              => array( 'cart', 'cart' ),
			'checkout'                          => array( 'cart', 'checkout' ),
			'order-summary'                     => array( 'cart', 'checkout' ),
			'order-confirmation'                => array( 'cart', 'confirmation' ),
			'menu-cart'                         => array( 'cart', 'menu-cart' ),
			'side-cart'                         => array( 'cart', 'side-cart' ),
			'my-account'                        => array( 'account', 'my-account' ),
			'account'                           => array( 'account', 'parts' ),
			'dynamic-tags'                      => array( 'dynamic', 'tags' ),
		);

		$guides = self::help_guides();
		$topic  = (string) $topic;
		if ( isset( $map[ $topic ] ) ) {
			list( $guide, $section ) = $map[ $topic ];
		} elseif ( isset( $guides[ $topic ] ) ) {
			$guide   = $topic;
			$section = '';
		} else {
			$guide   = 'overview';
			$section = '';
		}
		$url = self::HELP_BASE . $guides[ $guide ][0] . '/' . ( '' !== $section ? '#' . $section : '' );
		/**
		 * The "Need help?" address of a WP EasyCart Elementor widget or screen.
		 *
		 * @since 6.0.2
		 * @param string $url  Address.
		 * @param string $name A widget's get_name(), the Site Settings tab id, or the topic.
		 */
		return (string) apply_filters( 'wp_easycart_elementor_help_url', $url, ( '' !== (string) $name ) ? (string) $name : $topic );
	}

	/**
	 * 6.0.2: every admin/elementor/modules/<name>/module.php is loaded here ( on plugins_loaded, Elementor or not ). A module
	 * adds its hooks there: widget classes through wp_easycart_elementor_widget_classes, settings sections through
	 * wp_easycart_settings_page_elementor, retired widgets through wp_easycart_elementor_retired_widgets, dynamic tags, Theme
	 * Builder conditions and so on. A module never edits another module's files or this list.
	 */
	private function load_modules() {
		$modules = glob( EC_PLUGIN_DIRECTORY . '/admin/elementor/modules/*/module.php' );
		if ( ! is_array( $modules ) ) {
			return;
		}
		sort( $modules );
		foreach ( $modules as $module ) {
			include_once $module;
		}
	}

	/**
	 * Panel categories for the 6.0.2 widgets, slug suffix => title ( the legacy widgets keep wp-easycart-elements ).
	 *
	 * @return array
	 */
	public static function categories() {
		return array(
			'product'  => __( 'WP EasyCart · Product', 'wp-easycart' ),
			'shop'     => __( 'WP EasyCart · Shop', 'wp-easycart' ),
			'checkout' => __( 'WP EasyCart · Cart & Checkout', 'wp-easycart' ),
			'account'  => __( 'WP EasyCart · Account', 'wp-easycart' ),
		);
	}

	/**
	 * Whether the active Elementor has the 3.5 registration API ( elementor/widgets/register, ->register() ).
	 *
	 * @return bool
	 */
	public static function modern_api() {
		return ! defined( 'ELEMENTOR_VERSION' ) || version_compare( ELEMENTOR_VERSION, '3.5.0', '>=' );
	}

	/**
	 * Create Elementor Category for WP EasyCart
	 *
	 * @param object $self reference to the elementor editor.
	 */
	public function register_elementor_categories( $self ) {

		foreach ( self::categories() as $slug => $title ) {
			$self->add_category(
				'wp-easycart-' . $slug,
				array(
					'title'       => $title,
					'active'      => true,
					'hideIfEmpty' => true,
				)
			);
		}

		$self->add_category(
			'wp-easycart-elements',
			array(
				'title'       => __( 'WP EasyCart', 'wp-easycart' ),
				'active'      => true,
				'hideIfEmpty' => true,
			)
		);
	}

	/**
	 * Enqueue scripts for WP EasyCart Elementor Widgets.
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( 'wpeasycart_owl_carousel_js', plugins_url( 'wp-easycart/design/theme/' . get_option( 'ec_option_latest_theme' ) . '/owl.carousel.min.js', EC_PLUGIN_DIRECTORY ), array( 'jquery' ), EC_CURRENT_VERSION, false );
		wp_register_style( 'wpeasycart_owl_carousel_css', plugins_url( 'wp-easycart/design/theme/' . get_option( 'ec_option_latest_theme' ) . '/owl.carousel.css', EC_PLUGIN_DIRECTORY ), array(), EC_CURRENT_VERSION );
		wp_enqueue_style( 'wpeasycart_owl_carousel_css' );
	}

	/**
	 * Register WP EasyCart Elementor Controls.
	 *
	 * Registered on every request ( 6.0.2 ): widgets also build their control stacks when a visitor's page renders, and a
	 * missing control type raised a "Control type not found" notice for every product picker on the page.
	 *
	 * @param object $self reference to the elementor controls manager.
	 */
	public function register_elementor_controls( $self ) {
		include_once EC_PLUGIN_DIRECTORY . '/admin/elementor/class-wpeasycart-control-ajax-select2.php';
		$control = new \WPEasyCart_Control_Ajax_Select2();
		if ( method_exists( $self, 'register' ) && self::modern_api() ) {
			$self->register( $control );
		} else {
			$self->register_control( 'wpecajaxselect2', $control );
		}
	}

	/**
	 * Register WP EasyCart Elementor Widgets.
	 *
	 * @param object $self reference to the elementor widgets manager.
	 */
	public function register_elementor_widgets( $self ) {

		if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
			return;
		}

		$widgets = $this->widgets;
		if ( apply_filters( 'wp_easycart_enable_elementor_account_elements', false ) ) {
			$widgets = array(
				'account-forms'     => 'Wp_Easycart_Elementor_Account_Forms_Widget',
				'account-dashboard' => 'Wp_Easycart_Elementor_Account_Dashboard_Widget',
			) + $widgets;
		}

		$files = array();
		foreach ( $widgets as $file => $class_name ) {
			$files[ $class_name ] = EC_PLUGIN_DIRECTORY . '/admin/elementor/class-wp-easycart-elementor-' . $file . '-widget.php';
		}

		/*
		 * 6.0.2 widgets: modules ( and WP EasyCart PRO ) add class name => absolute file. They extend
		 * WP_EasyCart_Elementor_Widget_Base, loaded first. A PRO widget that replaces FREE's locked one keeps its get_name()
		 * and is added after it: Elementor keeps the last widget registered under a name.
		 */
		include_once EC_PLUGIN_DIRECTORY . '/admin/elementor/base/class-wp-easycart-elementor-widget-base.php';
		include_once EC_PLUGIN_DIRECTORY . '/admin/elementor/base/trait-wp-easycart-elementor-legacy-widget.php';
		$module_widgets = apply_filters( 'wp_easycart_elementor_widget_classes', array() );
		if ( is_array( $module_widgets ) ) {
			foreach ( $module_widgets as $class_name => $file ) {
				if ( is_string( $class_name ) && is_string( $file ) && ! isset( $files[ $class_name ] ) ) {
					$files[ $class_name ] = $file;
				}
			}
		}

		foreach ( $files as $class_name => $file ) {
			if ( ! class_exists( $class_name ) && is_readable( $file ) ) {
				include_once $file;
			}
			if ( ! class_exists( $class_name ) ) {
				continue;
			}
			$widget = new $class_name( array(), array( 'widget_name' => $class_name ) );
			if ( method_exists( $self, 'register' ) && self::modern_api() ) {
				$added = $self->register( $widget );
			} else {
				$added = $self->register_widget_type( $widget );
			}
			/* 6.0.2: remembered for wp_easycart_elementor_widget_registered() ( Element Manager can refuse a widget: false ). */
			if ( false !== $added ) {
				self::$registered[ $widget->get_name() ] = true;
			}
		}
	}
}

new WP_EasyCart_Elementor();
