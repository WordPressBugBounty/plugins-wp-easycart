<?php
/**
 * WP EasyCart Elementor dynamic data ( 6.0.2 ): the hooks of the module and the store data every tag, condition and query
 * reads.
 *
 * Loaded on every request ( module.php ). Nothing here needs Elementor: the classes that extend Elementor or Elementor Pro
 * are required inside the Elementor hook that uses them.
 *
 * - Dynamic tags ( group "WP EasyCart" ): elementor/dynamic_tags/register, classes in tags/. They are a core Elementor API.
 * - Site Settings › WP EasyCart: elementor/kit/register_tabs, class-wp-easycart-elementor-dynamic-kit-tab.php.
 * - Elementor Pro: the form action "Add to WP EasyCart newsletter" ( elementor_pro/forms/actions/register ) and the element
 *   display conditions Cart, Customer and Product ( elementor/display_conditions/register, an undocumented Pro API, only
 *   registered on the Pro versions it was written against: conditions_supported() ).
 *
 * Product data follows the product widgets: the product comes from wp_easycart_elementor_context() ( the product of the page,
 * of a Theme Builder template, of a Loop Grid item, or the editor's sample ), a store closed to the visitor
 * ( wp_easycart_store_is_restricted() ) shows nothing, and prices are the ones the product page shows
 * ( wp_easycart_product_schema::pricing() ).
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Dynamic' ) ) :

	/**
	 * Hooks and store data for the dynamic data module.
	 */
	final class WP_EasyCart_Elementor_Dynamic {

		/**
		 * Dynamic tag group.
		 */
		const GROUP = 'wp-easycart';

		/**
		 * Products listed in a tag's product picker ( the rest are reached by ID ).
		 */
		const PICKER_LIMIT = 300;

		/**
		 * Display conditions: the first Elementor Pro version with the API.
		 */
		const CONDITIONS_MIN_PRO = '3.19.0';

		/**
		 * Display conditions: registered below this Elementor Pro version only ( the API is internal; checked against 4.2 ).
		 */
		const CONDITIONS_BELOW_PRO = '5.0.0';

		/**
		 * Script / style handle for the storefront parts ( live cart tags, the "added to cart" note ).
		 */
		const HANDLE = 'wpeasycart-elementor-dynamic';

		/**
		 * Pricing per product id, this request.
		 *
		 * @var array
		 */
		private static $pricing = array();

		/**
		 * The visitor's cart state, this request ( never kept longer ).
		 *
		 * @var array|null
		 */
		private static $cart = null;

		/**
		 * Hooks ( once ).
		 */
		public static function init() {
			static $done = false;
			if ( $done ) {
				return;
			}
			$done = true;
			if ( self::modern_api() ) {
				add_action( 'elementor/dynamic_tags/register', array( __CLASS__, 'register_tags' ) );
			} else {
				add_action( 'elementor/dynamic_tags/register_tags', array( __CLASS__, 'register_tags' ) );
			}
			add_action( 'elementor/kit/register_tabs', array( __CLASS__, 'register_kit_tab' ) );
			add_action( 'elementor_pro/forms/actions/register', array( __CLASS__, 'register_form_action' ) );
			add_action( 'elementor/display_conditions/register_groups', array( __CLASS__, 'register_condition_group' ) );
			add_action( 'elementor/display_conditions/register', array( __CLASS__, 'register_conditions' ) );
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 5 );
			add_action( 'elementor/preview/enqueue_scripts', array( __CLASS__, 'register_assets' ), 5 );
			add_filter( 'wp_easycart_subscriber_source_labels', array( __CLASS__, 'subscriber_source_label' ) );
		}

		/**
		 * Whether the active Elementor has the 3.5 registration API.
		 *
		 * @return bool
		 */
		public static function modern_api() {
			if ( class_exists( 'WP_EasyCart_Elementor' ) && method_exists( 'WP_EasyCart_Elementor', 'modern_api' ) ) {
				return WP_EasyCart_Elementor::modern_api();
			}
			return ! defined( 'ELEMENTOR_VERSION' ) || version_compare( ELEMENTOR_VERSION, '3.5.0', '>=' );
		}

		/**
		 * Whether this request draws the Elementor editor or its preview.
		 *
		 * @return bool
		 */
		public static function is_editor() {
			return function_exists( 'wp_easycart_elementor_is_editor' ) && wp_easycart_elementor_is_editor();
		}

		// Registration.

		/**
		 * Tag slug => class. Each class lives in tags/class-wp-easycart-elementor-dynamic-tag-<slug>.php.
		 *
		 * @return array
		 */
		public static function tag_classes() {
			$tags = array(
				'product-title'             => 'WP_EasyCart_Elementor_Dynamic_Tag_Product_Title',
				'product-price'             => 'WP_EasyCart_Elementor_Dynamic_Tag_Product_Price',
				'product-price-number'      => 'WP_EasyCart_Elementor_Dynamic_Tag_Product_Price_Number',
				'product-regular-price'     => 'WP_EasyCart_Elementor_Dynamic_Tag_Product_Regular_Price',
				'product-sale-price'        => 'WP_EasyCart_Elementor_Dynamic_Tag_Product_Sale_Price',
				'product-sku'               => 'WP_EasyCart_Elementor_Dynamic_Tag_Product_Sku',
				'product-stock'             => 'WP_EasyCart_Elementor_Dynamic_Tag_Product_Stock',
				'product-stock-number'      => 'WP_EasyCart_Elementor_Dynamic_Tag_Product_Stock_Number',
				'product-short-description' => 'WP_EasyCart_Elementor_Dynamic_Tag_Product_Short_Description',
				'product-description'       => 'WP_EasyCart_Elementor_Dynamic_Tag_Product_Description',
				'product-image'             => 'WP_EasyCart_Elementor_Dynamic_Tag_Product_Image',
				'product-gallery'           => 'WP_EasyCart_Elementor_Dynamic_Tag_Product_Gallery',
				'product-url'               => 'WP_EasyCart_Elementor_Dynamic_Tag_Product_Url',
				'add-to-cart-url'           => 'WP_EasyCart_Elementor_Dynamic_Tag_Add_To_Cart_Url',
				'product-rating'            => 'WP_EasyCart_Elementor_Dynamic_Tag_Product_Rating',
				'product-rating-number'     => 'WP_EasyCart_Elementor_Dynamic_Tag_Product_Rating_Number',
				'product-review-count'      => 'WP_EasyCart_Elementor_Dynamic_Tag_Product_Review_Count',
				'product-category'          => 'WP_EasyCart_Elementor_Dynamic_Tag_Product_Category',
				'product-category-url'      => 'WP_EasyCart_Elementor_Dynamic_Tag_Product_Category_Url',
				'product-manufacturer'      => 'WP_EasyCart_Elementor_Dynamic_Tag_Product_Manufacturer',
				'product-manufacturer-url'  => 'WP_EasyCart_Elementor_Dynamic_Tag_Product_Manufacturer_Url',
				'cart-count'                => 'WP_EasyCart_Elementor_Dynamic_Tag_Cart_Count',
				'cart-count-number'         => 'WP_EasyCart_Elementor_Dynamic_Tag_Cart_Count_Number',
				'cart-subtotal'             => 'WP_EasyCart_Elementor_Dynamic_Tag_Cart_Subtotal',
				'customer-first-name'       => 'WP_EasyCart_Elementor_Dynamic_Tag_Customer_First_Name',
			);
			/**
			 * The WP EasyCart dynamic tags Elementor registers ( slug => class ). Removing one keeps saved pages that use it
			 * from showing its value.
			 *
			 * @since 6.0.2
			 *
			 * @param array $tags Slug => class name.
			 */
			$tags = apply_filters( 'wp_easycart_elementor_dynamic_tags', $tags );
			return is_array( $tags ) ? $tags : array();
		}

		/**
		 * Registers the "WP EasyCart" tag group and the tags.
		 *
		 * @param object $manager \Elementor\Core\DynamicTags\Manager.
		 */
		public static function register_tags( $manager ) {
			if ( ! is_object( $manager ) || ! class_exists( '\Elementor\Core\DynamicTags\Tag' ) || ! class_exists( '\Elementor\Core\DynamicTags\Data_Tag' ) ) {
				return;
			}
			if ( method_exists( $manager, 'register_group' ) ) {
				$manager->register_group( self::GROUP, array( 'title' => __( 'WP EasyCart', 'wp-easycart' ) ) );
			}
			$dir = __DIR__ . '/tags/';
			require_once $dir . 'trait-wp-easycart-elementor-dynamic-tag.php';
			require_once $dir . 'class-wp-easycart-elementor-dynamic-text-tag.php';
			require_once $dir . 'class-wp-easycart-elementor-dynamic-data-tag.php';
			foreach ( self::tag_classes() as $slug => $class_name ) {
				if ( ! is_string( $class_name ) || '' === $class_name ) {
					continue;
				}
				if ( ! class_exists( $class_name ) ) {
					$file = $dir . 'class-wp-easycart-elementor-dynamic-tag-' . sanitize_key( $slug ) . '.php';
					if ( is_readable( $file ) ) {
						require_once $file;
					}
				}
				if ( ! class_exists( $class_name ) ) {
					continue;
				}
				if ( method_exists( $manager, 'register' ) && self::modern_api() ) {
					$manager->register( new $class_name() );
				} elseif ( method_exists( $manager, 'register_tag' ) ) {
					$manager->register_tag( $class_name );
				}
			}
		}

		/**
		 * Adds the WP EasyCart tab to Elementor's Site Settings.
		 *
		 * @param object $kit \Elementor\Core\Kits\Documents\Kit.
		 */
		public static function register_kit_tab( $kit ) {
			if ( ! is_object( $kit ) || ! method_exists( $kit, 'register_tab' ) || ! class_exists( '\Elementor\Core\Kits\Documents\Tabs\Tab_Base' ) ) {
				return;
			}
			require_once __DIR__ . '/class-wp-easycart-elementor-dynamic-kit-tab.php';
			if ( class_exists( 'WP_EasyCart_Elementor_Dynamic_Kit_Tab' ) ) {
				$kit->register_tab( WP_EasyCart_Elementor_Dynamic_Kit_Tab::TAB_ID, 'WP_EasyCart_Elementor_Dynamic_Kit_Tab' );
			}
		}

		/**
		 * Adds the form action "Add to WP EasyCart newsletter" ( Elementor Pro forms ).
		 *
		 * @param object $registrar Elementor Pro's form actions registrar.
		 */
		public static function register_form_action( $registrar ) {
			if ( ! is_object( $registrar ) || ! method_exists( $registrar, 'register' ) || ! class_exists( '\ElementorPro\Modules\Forms\Classes\Integration_Base' ) || ! class_exists( 'wp_easycart_subscribers' ) ) {
				return;
			}
			require_once __DIR__ . '/pro/class-wp-easycart-elementor-dynamic-form-action.php';
			if ( class_exists( 'WP_EasyCart_Elementor_Dynamic_Form_Action' ) ) {
				$registrar->register( new WP_EasyCart_Elementor_Dynamic_Form_Action() );
			}
		}

		/**
		 * Whether the element display conditions can be registered: Elementor Pro's classes are there and its version is one
		 * the conditions were written against. Otherwise none is registered and Elementor Pro ignores a saved one ( the
		 * element then shows as if it had no condition ).
		 *
		 * @return bool
		 */
		public static function conditions_supported() {
			$api = class_exists( '\ElementorPro\Modules\DisplayConditions\Conditions\Base\Condition_Base' ) && class_exists( '\ElementorPro\Core\Isolation\Wordpress_Adapter' );
			if ( ! $api || ! defined( 'ELEMENTOR_PRO_VERSION' ) ) {
				return false;
			}
			$version = version_compare( ELEMENTOR_PRO_VERSION, self::CONDITIONS_MIN_PRO, '>=' ) && version_compare( ELEMENTOR_PRO_VERSION, self::CONDITIONS_BELOW_PRO, '<' );
			/**
			 * Whether WP EasyCart registers its element display conditions with this Elementor Pro version.
			 *
			 * @since 6.0.2
			 *
			 * @param bool   $supported Within the tested versions ( 3.19 up to, not including, 5.0 ).
			 * @param string $version   Elementor Pro version.
			 */
			return (bool) apply_filters( 'wp_easycart_elementor_display_conditions_supported', $version, ELEMENTOR_PRO_VERSION );
		}

		/**
		 * The Elementor Pro versions the display conditions register with, as help text names them: array( '3.19', '4.x' ).
		 *
		 * @return string[]
		 */
		public static function conditions_range() {
			$below = (int) self::CONDITIONS_BELOW_PRO; /* A major version ( 5.0.0 ): the last one covered is 4.x. */
			return array( implode( '.', array_slice( explode( '.', self::CONDITIONS_MIN_PRO ), 0, 2 ) ), max( 0, $below - 1 ) . '.x' );
		}

		/**
		 * Where the display conditions stand on this site:
		 * - none: no Elementor Pro ( display conditions are an Elementor Pro feature );
		 * - on: registered;
		 * - old: Elementor Pro older than 3.19, which has no element display conditions;
		 * - newer: Elementor Pro 5.0 or later, where they are not registered until WP EasyCart supports it ( Elementor Pro then
		 *   ignores a saved one, so its element shows to everyone );
		 * - off: a covered version, but Elementor Pro's classes are missing or a filter turned them off.
		 *
		 * @return string
		 */
		public static function conditions_state() {
			if ( ! defined( 'ELEMENTOR_PRO_VERSION' ) ) {
				return 'none';
			}
			if ( self::conditions_supported() ) {
				return 'on';
			}
			if ( version_compare( ELEMENTOR_PRO_VERSION, self::CONDITIONS_MIN_PRO, '<' ) ) {
				return 'old';
			}
			return version_compare( ELEMENTOR_PRO_VERSION, self::CONDITIONS_BELOW_PRO, '>=' ) ? 'newer' : 'off';
		}

		/**
		 * How many published pages and templates use a WP EasyCart display condition ( their saved Elementor data names one ).
		 *
		 * @return int
		 */
		public static function conditions_in_use() {
			global $wpdb;
			static $count = null;
			if ( null !== $count ) {
				return $count;
			}
			if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
				return 0;
			}
			$like = array();
			foreach ( array( 'wp_easycart_cart_status', 'wp_easycart_customer_status', 'wp_easycart_product_status' ) as $name ) {
				$like[] = '%' . $wpdb->esc_like( $name ) . '%';
			}
			$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT( DISTINCT m.post_id ) FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = '_elementor_data' AND p.post_status = 'publish' AND ( m.meta_value LIKE %s OR m.meta_value LIKE %s OR m.meta_value LIKE %s )", $like[0], $like[1], $like[2] ) );
			return $count;
		}

		/**
		 * The display conditions' state for Settings › Elementor and Store Status.
		 *
		 * @return array state ( see conditions_state() ), value ( a few words ), detail ( what it means for the store ), in_use
		 *               ( pages and templates using one, counted only when they are not registered, else null ).
		 */
		public static function conditions_status() {
			$state  = self::conditions_state();
			$range  = self::conditions_range();
			$pro    = defined( 'ELEMENTOR_PRO_VERSION' ) ? (string) ELEMENTOR_PRO_VERSION : '';
			$in_use = in_array( $state, array( 'newer', 'off' ), true ) ? self::conditions_in_use() : null;
			/* translators: 1: first Elementor Pro version, 2: last Elementor Pro version ( for example 3.19 and 4.x ). */
			$works = sprintf( __( 'WP EasyCart\'s display conditions work with Elementor Pro %1$s to %2$s.', 'wp-easycart' ), $range[0], $range[1] );
			$use   = '';
			if ( null !== $in_use ) {
				/* translators: %d: number of pages and templates. */
				$use = ( $in_use > 0 ) ? sprintf( _n( '%d page or template uses one, and its elements now show to every visitor.', '%d pages or templates use one, and their elements now show to every visitor.', $in_use, 'wp-easycart' ), $in_use ) : __( 'None of your pages or templates uses one.', 'wp-easycart' );
			}
			switch ( $state ) {
				case 'on':
					$value = __( 'On', 'wp-easycart' );
					/* translators: 1: first Elementor Pro version, 2: last Elementor Pro version. */
					$detail = sprintf( __( 'Cart, Customer and Product are in every element\'s Advanced › Display Conditions ( Elementor Pro %1$s to %2$s ).', 'wp-easycart' ), $range[0], $range[1] );
					break;
				case 'old':
					/* translators: %s: Elementor Pro version. */
					$value  = sprintf( __( 'Needs Elementor Pro %s', 'wp-easycart' ), $range[0] );
					$detail = $works . ' ' . __( 'Update Elementor Pro to use them.', 'wp-easycart' );
					break;
				case 'newer':
					$value = __( 'Not available', 'wp-easycart' );
					/* translators: %s: Elementor Pro version. */
					$detail = trim( $works . ' ' . sprintf( __( 'They are off with version %s until a WP EasyCart update supports it.', 'wp-easycart' ), $pro ) . ' ' . $use );
					break;
				case 'off':
					$value  = __( 'Off', 'wp-easycart' );
					$detail = trim( __( 'Elementor Pro\'s display conditions could not be found, or a plugin switched WP EasyCart\'s off.', 'wp-easycart' ) . ' ' . $use );
					break;
				default:
					$value  = __( 'Needs Elementor Pro', 'wp-easycart' );
					$detail = $works;
			}
			return array(
				'state'  => $state,
				'value'  => $value,
				'detail' => $detail,
				'in_use' => $in_use,
			);
		}

		/**
		 * Store Status: a row while the display conditions are not registered on an Elementor Pro that could hold saved ones
		 * ( 5.0 or later, or switched off ). A warning when pages use one ( those elements show to every visitor ).
		 */
		public static function store_status_rows() {
			$status = self::conditions_status();
			if ( ! in_array( $status['state'], array( 'newer', 'off' ), true ) ) {
				return;
			}
			$warn = ( $status['in_use'] > 0 );
			$link = function_exists( 'admin_url' ) ? admin_url( 'admin.php?page=wp-easycart-settings&subpage=elementor' ) : '';
			echo '<div class="' . ( $warn ? 'ec_status_warning' : 'ec_status_success' ) . '"><div class="dashicons-before ' . ( $warn ? 'dashicons-warning' : 'dashicons-yes' ) . '"></div><span class="ec_status_label">';
			/* translators: 1: Elementor Pro version, 2: what the display conditions' state means for the store. */
			echo esc_html( sprintf( __( 'Elementor Pro %1$s: %2$s', 'wp-easycart' ), defined( 'ELEMENTOR_PRO_VERSION' ) ? (string) ELEMENTOR_PRO_VERSION : '', $status['detail'] ) );
			if ( $warn && '' !== $link ) {
				echo ' <a href="' . esc_url( $link ) . '">' . esc_html__( 'Open Settings › Elementor', 'wp-easycart' ) . '</a>';
			}
			echo '</span></div>';
		}

		/**
		 * Adds the "WP EasyCart" group to the display conditions.
		 *
		 * @param object $manager Elementor Pro's display conditions manager.
		 */
		public static function register_condition_group( $manager ) {
			if ( ! is_object( $manager ) || ! method_exists( $manager, 'add_group' ) || ! self::conditions_supported() ) {
				return;
			}
			$manager->add_group( 'wp_easycart', array( 'label' => __( 'WP EasyCart', 'wp-easycart' ) ) );
		}

		/**
		 * Registers the display conditions Cart, Customer and Product.
		 *
		 * @param object $manager Elementor Pro's display conditions manager.
		 */
		public static function register_conditions( $manager ) {
			if ( ! is_object( $manager ) || ! method_exists( $manager, 'register_condition_instance' ) || ! self::conditions_supported() ) {
				return;
			}
			$classes = array(
				'cart'     => 'WP_EasyCart_Elementor_Dynamic_Condition_Cart',
				'customer' => 'WP_EasyCart_Elementor_Dynamic_Condition_Customer',
				'product'  => 'WP_EasyCart_Elementor_Dynamic_Condition_Product',
			);
			foreach ( $classes as $slug => $class_name ) {
				if ( ! class_exists( $class_name ) ) {
					require_once __DIR__ . '/pro/class-wp-easycart-elementor-dynamic-condition-' . $slug . '.php';
				}
				if ( class_exists( $class_name ) ) {
					$manager->register_condition_instance( new $class_name( array( new \ElementorPro\Core\Isolation\Wordpress_Adapter() ) ) );
				}
			}
		}

		/**
		 * The comparator choices of the display conditions ( Elementor Pro's own wording when it has it ).
		 *
		 * @return array
		 */
		public static function condition_comparators() {
			$provider = '\ElementorPro\Modules\DisplayConditions\Classes\Comparator_Provider';
			if ( class_exists( $provider ) && method_exists( $provider, 'get_comparators' ) ) {
				$comparators = call_user_func( array( $provider, 'get_comparators' ), array( 'is', 'is_not' ) );
				if ( is_array( $comparators ) && isset( $comparators['is'], $comparators['is_not'] ) ) {
					return $comparators;
				}
			}
			return array(
				'is'     => __( 'Is', 'wp-easycart' ),
				'is_not' => __( 'Is not', 'wp-easycart' ),
			);
		}

		/**
		 * A display condition's answer: the state matched, turned round for "Is not".
		 *
		 * @param array $args  The saved condition ( comparator, status ).
		 * @param bool  $state Whether the chosen state is true now.
		 * @return bool
		 */
		public static function condition_result( $args, $state ) {
			$comparator = ( is_array( $args ) && isset( $args['comparator'] ) ) ? (string) $args['comparator'] : 'is';
			return ( 'is_not' === $comparator ) ? ! $state : (bool) $state;
		}

		/**
		 * "Elementor form" as a newsletter sign-up source ( Marketing › Subscribers ).
		 *
		 * @param array $labels Slug => label.
		 * @return array
		 */
		public static function subscriber_source_label( $labels ) {
			if ( is_array( $labels ) ) {
				$labels['elementor'] = __( 'Elementor form', 'wp-easycart' );
			}
			return $labels;
		}

		/**
		 * Registers the storefront script and stylesheet ( enqueued only by a live cart tag or the "added to cart" note ).
		 */
		public static function register_assets() {
			if ( wp_script_is( self::HANDLE, 'registered' ) ) {
				return;
			}
			$version = defined( 'EC_CURRENT_VERSION' ) ? EC_CURRENT_VERSION : '6.0.2';
			wp_register_script( self::HANDLE, plugins_url( 'assets/wpec-dynamic.js', __FILE__ ), array(), $version, true );
			wp_localize_script(
				self::HANDLE,
				'wpeasycart_elementor_dynamic',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
				)
			);
			wp_register_style( self::HANDLE, plugins_url( 'assets/wpec-dynamic.css', __FILE__ ), array(), $version );
		}

		/**
		 * Enqueues the storefront script ( and the stylesheet ), registering them first when needed.
		 *
		 * @param bool $style Also the stylesheet.
		 */
		public static function enqueue_assets( $style = false ) {
			self::register_assets();
			wp_enqueue_script( self::HANDLE );
			if ( $style ) {
				wp_enqueue_style( self::HANDLE );
			}
		}

		// Store data.

		/**
		 * Whether the store is open to this visitor ( Settings › Products › Who can view the store ).
		 *
		 * @return bool
		 */
		public static function store_visible() {
			return ! ( function_exists( 'wp_easycart_store_is_restricted' ) && wp_easycart_store_is_restricted() );
		}

		/**
		 * Keeps the page out of page caches: its prices, stock or cart belong to this visitor ( as the product widgets do ).
		 */
		public static function no_page_cache() {
			if ( function_exists( 'wp_easycart_product_details_no_cache' ) ) {
				wp_easycart_product_details_no_cache();
			}
		}

		/**
		 * A live cart value as the page prints it: the visitor's own only where no page cache keeps the page ( already uncached, or
		 * the editor ); elsewhere a neutral value the store script replaces once the page loads.
		 *
		 * @param string $value   The visitor's value.
		 * @param string $neutral What a cacheable page prints.
		 * @return string
		 */
		public static function live_cart_value( $value, $neutral ) {
			if ( self::is_editor() || ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE ) ) {
				return (string) $value;
			}
			return (string) $neutral;
		}

		/**
		 * Shopper text from the language file, or the English fallback when the key is missing.
		 *
		 * @param string $section  Section.
		 * @param string $key      Key.
		 * @param string $fallback English text.
		 * @return string
		 */
		public static function text( $section, $key, $fallback ) {
			$value = function_exists( 'wp_easycart_language' ) ? (string) wp_easycart_language()->get_text( $section, $key ) : '';
			return ( '' !== trim( $value ) ) ? $value : $fallback;
		}

		/**
		 * Plain text: tags, shortcodes and extra spaces removed, entities decoded ( escape it when printing ).
		 *
		 * @param string $text  Text.
		 * @param int    $limit Characters ( cut at a word ).
		 * @return string
		 */
		public static function plain( $text, $limit = 100000 ) {
			if ( class_exists( 'wp_easycart_product_schema' ) && method_exists( 'wp_easycart_product_schema', 'plain' ) ) {
				return (string) wp_easycart_product_schema::plain( $text, $limit );
			}
			return trim( html_entity_decode( wp_strip_all_tags( stripslashes( (string) $text ) ), ENT_QUOTES, 'UTF-8' ) );
		}

		/**
		 * The price choices of the price tags ( value => label ).
		 *
		 * @return array
		 */
		public static function price_formats() {
			return array(
				'current' => __( 'Price the shopper pays', 'wp-easycart' ),
				'regular' => __( 'Regular price (before a sale)', 'wp-easycart' ),
				'sale'    => __( 'Sale price (only when on sale)', 'wp-easycart' ),
				'from'    => __( 'Lowest price (products with options)', 'wp-easycart' ),
			);
		}

		/**
		 * A product picked in a tag's settings ( 0 = the product of the page ).
		 *
		 * @param array $settings Tag settings.
		 * @return int
		 */
		public static function picked_product_id( $settings ) {
			if ( ! is_array( $settings ) || empty( $settings['product_source'] ) || 'pick' !== $settings['product_source'] ) {
				return 0;
			}
			$manual = ( isset( $settings['product_id_manual'] ) && is_scalar( $settings['product_id_manual'] ) ) ? absint( $settings['product_id_manual'] ) : 0;
			if ( $manual ) {
				return $manual;
			}
			return ( isset( $settings['product_id'] ) && is_scalar( $settings['product_id'] ) ) ? absint( $settings['product_id'] ) : 0;
		}

		/**
		 * The product for a tag or condition: the picked one, else the product of the page ( template, Loop Grid item, the
		 * editor's sample ). Null when there is none or the store is closed to this visitor.
		 *
		 * @param array $settings Tag settings ( product_source, product_id, product_id_manual ).
		 * @return ec_product|null
		 */
		public static function product( $settings = array() ) {
			if ( ! function_exists( 'wp_easycart_elementor_context' ) || ! self::store_visible() ) {
				return null;
			}
			$id      = self::picked_product_id( $settings );
			$product = wp_easycart_elementor_context()->product(
				array(
					'source'     => $id ? 'pick' : 'current',
					'product_id' => $id,
				)
			);
			return ( is_object( $product ) && ! empty( $product->product_id ) ) ? $product : null;
		}

		/**
		 * The product picker's list: active products by name ( at most PICKER_LIMIT ). Built only for the editor ( wp-admin and
		 * its AJAX ): on the storefront a tag never needs the list, Elementor does not check a saved value against it.
		 *
		 * @return array Product id => name.
		 */
		public static function product_options() {
			static $options = null;
			if ( null !== $options ) {
				return $options;
			}
			$options = array( '' => __( 'Choose a product', 'wp-easycart' ) );
			if ( ! is_admin() ) {
				return $options;
			}
			global $wpdb;
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT product_id, title, model_number FROM ec_product WHERE activate_in_store = 1 ORDER BY title ASC, product_id ASC LIMIT %d', self::PICKER_LIMIT ) );
			foreach ( (array) $rows as $row ) {
				$title = (string) $row->title;
				if ( function_exists( 'wp_easycart_language' ) ) {
					$title = (string) wp_easycart_language()->convert_text( $title );
				}
				$title                                      = trim( wp_strip_all_tags( stripslashes( $title ) ) );
				$model                                      = trim( (string) $row->model_number );
				$options[ (string) (int) $row->product_id ] = ( '' !== $title ? $title : '#' . (int) $row->product_id ) . ( '' !== $model ? ' (' . $model . ')' : '' );
			}
			return $options;
		}

		/**
		 * What the product page shows for price ( wp_easycart_product_schema::pricing() ), once per product per request.
		 *
		 * @param ec_product $product Product.
		 * @return array hidden, price, list, raw, low, high, vat.
		 */
		public static function pricing( $product ) {
			$key = (int) $product->product_id;
			if ( isset( self::$pricing[ $key ] ) ) {
				return self::$pricing[ $key ];
			}
			if ( class_exists( 'wp_easycart_product_schema' ) && method_exists( 'wp_easycart_product_schema', 'pricing' ) ) {
				$pricing = wp_easycart_product_schema::pricing( $product );
			} else {
				$price   = (float) $product->price_options;
				$list    = (float) $product->list_price;
				$pricing = array(
					'hidden' => false,
					'price'  => $price,
					'list'   => ( $list > $price ) ? $list : 0.0,
					'raw'    => (float) $product->price,
					'low'    => 0.0,
					'high'   => 0.0,
					'vat'    => false,
				);
			}
			self::$pricing[ $key ] = $pricing;
			return $pricing;
		}

		/**
		 * A price as the page shows it ( with VAT when the store shows VAT prices ).
		 *
		 * @param ec_product $product Product.
		 * @param float      $amount  Amount.
		 * @return float
		 */
		private static function shown( $product, $amount ) {
			$pricing = self::pricing( $product );
			if ( class_exists( 'wp_easycart_product_schema' ) && method_exists( 'wp_easycart_product_schema', 'shown' ) ) {
				return (float) wp_easycart_product_schema::shown( $product, $amount, ! empty( $pricing['vat'] ) );
			}
			return round( (float) $amount, 2 );
		}

		/**
		 * Whether the product is on sale ( the page strikes a regular price ).
		 *
		 * @param ec_product $product Product.
		 * @return bool
		 */
		public static function on_sale( $product ) {
			$pricing = self::pricing( $product );
			return empty( $pricing['hidden'] ) && (float) $pricing['list'] > 0 && (float) $pricing['list'] > (float) $pricing['price'];
		}

		/**
		 * Whether the product has more than one price ( options, variants or a price range ).
		 *
		 * @param ec_product $product Product.
		 * @return bool
		 */
		public static function has_price_choices( $product ) {
			if ( ! empty( $product->show_custom_price_range ) ) {
				return true;
			}
			return method_exists( $product, 'has_options' ) && $product->has_options();
		}

		/**
		 * One of the product's prices.
		 *
		 * @param ec_product $product Product.
		 * @param string     $format  current ( what the shopper pays now ) | regular ( before the sale ) | sale ( only when on
		 *                            sale ) | from ( the lowest price over its options and variants ).
		 * @return float|null Null when the page shows no such price.
		 */
		public static function price_amount( $product, $format ) {
			$pricing = self::pricing( $product );
			if ( ! empty( $pricing['hidden'] ) ) {
				return null;
			}
			switch ( $format ) {
				case 'regular':
					if ( self::on_sale( $product ) ) {
						return (float) $pricing['list'];
					}
					return ( (float) $pricing['low'] > 0 ) ? null : (float) $pricing['price'];
				case 'sale':
					return self::on_sale( $product ) ? (float) $pricing['price'] : null;
				case 'from':
					return self::lowest_price( $product );
				default:
					return ( (float) $pricing['low'] > 0 ) ? (float) $pricing['low'] : (float) $pricing['price'];
			}
		}

		/**
		 * The lowest price a shopper can pay: the price range's low end, the cheapest variant, or the price with each option
		 * set's cheapest choice.
		 *
		 * @param ec_product $product Product.
		 * @return float|null
		 */
		public static function lowest_price( $product ) {
			$pricing = self::pricing( $product );
			if ( ! empty( $pricing['hidden'] ) ) {
				return null;
			}
			if ( (float) $pricing['low'] > 0 ) {
				return (float) $pricing['low'];
			}
			$candidates = array();
			if ( (float) $pricing['price'] > 0 ) {
				$candidates[] = (float) $pricing['price'];
			}
			$has_variants = false;
			if ( ! empty( $product->use_optionitem_quantity_tracking ) && class_exists( 'wp_easycart_product_schema' ) && method_exists( 'wp_easycart_product_schema', 'variants' ) ) {
				$variants = wp_easycart_product_schema::variants( $product, $pricing, wp_easycart_product_schema::url( $product ), wp_easycart_product_schema::google_attributes( $product ) );
				if ( is_array( $variants ) && ! empty( $variants['items'] ) ) {
					foreach ( $variants['items'] as $item ) {
						if ( isset( $item['offers']['price'] ) && (float) $item['offers']['price'] > 0 ) {
							$candidates[] = (float) $item['offers']['price'];
							$has_variants = true;
						}
					}
				}
			}
			if ( ! $has_variants && ! empty( $product->has_options ) && isset( $product->options ) && is_object( $product->options ) ) {
				$adds = 0.0;
				$any  = false;
				for ( $i = 1; $i <= 5; $i++ ) {
					$set = isset( $product->options->{ 'optionset' . $i } ) ? $product->options->{ 'optionset' . $i } : null;
					if ( ! is_object( $set ) || empty( $set->optionset ) ) {
						continue;
					}
					$lowest_add = null;
					foreach ( (array) $set->optionset as $item ) {
						$add        = isset( $item->optionitem_price ) ? (float) $item->optionitem_price : 0.0;
						$lowest_add = ( null === $lowest_add ) ? $add : min( $lowest_add, $add );
						if ( isset( $item->optionitem_price_override ) && -1 !== (int) $item->optionitem_price_override && (float) $item->optionitem_price_override > 0 ) {
							$candidates[] = self::shown( $product, (float) $item->optionitem_price_override );
						}
					}
					if ( null !== $lowest_add ) {
						$adds += $lowest_add;
						$any   = true;
					}
				}
				if ( $any && (float) $pricing['raw'] + $adds > 0 ) {
					$candidates[] = self::shown( $product, (float) $pricing['raw'] + $adds );
				}
			}
			if ( ! $candidates ) {
				return ( (float) $pricing['price'] >= 0 ) ? (float) $pricing['price'] : null;
			}
			return min( $candidates );
		}

		/**
		 * An amount as the store shows it.
		 *
		 * @param float $amount Amount in the store's currency.
		 * @param bool  $symbol With the currency symbol.
		 * @return string
		 */
		public static function money( $amount, $symbol = true ) {
			if ( ! isset( $GLOBALS['currency'] ) || ! is_object( $GLOBALS['currency'] ) ) {
				return number_format( (float) $amount, 2 );
			}
			return $symbol ? (string) $GLOBALS['currency']->get_currency_display( $amount ) : (string) $GLOBALS['currency']->get_number_only( $amount );
		}

		/**
		 * An amount as a plain number ( a dot for decimals, no grouping ) in the shopper's currency, for number controls.
		 *
		 * @param float $amount Amount.
		 * @return string
		 */
		public static function number( $amount ) {
			if ( ! isset( $GLOBALS['currency'] ) || ! is_object( $GLOBALS['currency'] ) ) {
				return (string) round( (float) $amount, 2 );
			}
			return (string) $GLOBALS['currency']->get_number_safe( $amount );
		}

		/**
		 * A price as text: "$12.00", a range "$10.00 - $20.00", "Starting At $10.00", with the billing period for a
		 * subscription.
		 *
		 * @param ec_product $product Product.
		 * @param string     $format  See price_amount().
		 * @param bool       $symbol  With the currency symbol.
		 * @return string '' when the page shows no such price.
		 */
		public static function price_text( $product, $format, $symbol = true ) {
			$pricing = self::pricing( $product );
			if ( ! empty( $pricing['hidden'] ) ) {
				return '';
			}
			$starting = self::text( 'product_details', 'product_details_starting_at', __( 'Starting At', 'wp-easycart' ) );
			if ( 'current' === $format && (float) $pricing['low'] > 0 ) {
				if ( (float) $pricing['high'] > (float) $pricing['low'] ) {
					return self::money( $pricing['low'], $symbol ) . ' - ' . self::money( $pricing['high'], $symbol );
				}
				return $starting . ' ' . self::money( $pricing['low'], $symbol );
			}
			$amount = self::price_amount( $product, $format );
			if ( null === $amount ) {
				return '';
			}
			if ( $symbol && 'regular' !== $format && ! empty( $product->is_subscription_item ) && method_exists( $product, 'get_price_formatted' ) && $amount > 0 ) {
				$text = (string) $product->get_price_formatted( 1, $amount );
			} else {
				$text = self::money( $amount, $symbol );
			}
			if ( 'from' === $format && self::has_price_choices( $product ) ) {
				$text = $starting . ' ' . $text;
			}
			return wp_strip_all_tags( $text );
		}

		/**
		 * Stock as the page knows it.
		 *
		 * @param ec_product $product Product.
		 * @return array tracked ( bool ), quantity ( int|null ), in_stock ( bool ), backorder ( bool ).
		 */
		public static function stock( $product ) {
			$tracked  = ! empty( $product->show_stock_quantity ) || ! empty( $product->use_optionitem_quantity_tracking );
			$quantity = $tracked ? max( 0, (int) $product->stock_quantity ) : null;
			$in_stock = ! $tracked || $quantity > 0;
			return array(
				'tracked'   => $tracked,
				'quantity'  => $quantity,
				'in_stock'  => $in_stock,
				'backorder' => ! $in_stock && ! empty( $product->allow_backorders ),
			);
		}

		/**
		 * Stock as text: "12 Left in Stock", "OUT OF STOCK" or "Backordered" ( the product page's wording ).
		 *
		 * @param ec_product $product Product.
		 * @param string     $show    count ( units left, or out of stock ) | status ( only when it can't be bought now ) |
		 *                            all ( as count, and "In stock" for a product whose stock is not counted, 6.0.2 ).
		 * @return string
		 */
		public static function stock_text( $product, $show = 'count' ) {
			$stock = self::stock( $product );
			if ( ! $stock['in_stock'] ) {
				if ( $stock['backorder'] ) {
					return self::text( 'product_details', 'product_details_backordered', __( 'Backordered', 'wp-easycart' ) );
				}
				return self::text( 'product_details', 'product_details_out_of_stock', __( 'Out of stock', 'wp-easycart' ) );
			}
			if ( 'all' === $show && ! $stock['tracked'] ) {
				return self::text( 'elementor_product', 'in_stock', __( 'In stock', 'wp-easycart' ) );
			}
			if ( 'status' === $show || ! $stock['tracked'] ) {
				return '';
			}
			return $stock['quantity'] . ' ' . self::text( 'product_details', 'product_details_left_in_stock', __( 'Left in Stock', 'wp-easycart' ) );
		}

		/**
		 * The product's customer rating.
		 *
		 * @param ec_product $product Product.
		 * @return array average ( 0-5, one decimal ), count.
		 */
		public static function rating( $product ) {
			$out = array(
				'average' => 0.0,
				'count'   => 0,
			);
			if ( empty( $product->use_customer_reviews ) || empty( $product->reviews ) || ! is_array( $product->reviews ) ) {
				return $out;
			}
			$total = 0;
			foreach ( $product->reviews as $review ) {
				$rating = isset( $review->rating ) ? (int) $review->rating : 0;
				if ( $rating >= 1 && $rating <= 5 ) {
					$total += $rating;
					++$out['count'];
				}
			}
			if ( $out['count'] ) {
				$out['average'] = round( $total / $out['count'], 1 );
			}
			return $out;
		}

		/**
		 * The product's images, main first ( the ones the page shows: no videos, no placeholder ), each with its media library
		 * id when it has one ( 0 for images uploaded to the store before the media library was used ).
		 *
		 * @param ec_product $product Product.
		 * @return array List of array( 'id' => int, 'url' => string ).
		 */
		public static function images( $product ) {
			if ( ! class_exists( 'wp_easycart_product_schema' ) || ! method_exists( 'wp_easycart_product_schema', 'images' ) ) {
				return array();
			}
			$urls = wp_easycart_product_schema::images( $product );
			if ( ! $urls ) {
				return array();
			}
			$ids = array();
			foreach ( self::attachment_tokens( $product ) as $attachment_id ) {
				$url = (string) wp_get_attachment_image_url( $attachment_id, 'full' );
				if ( '' === $url ) {
					continue;
				}
				if ( 0 === strpos( $url, '//' ) ) {
					$url = ( is_ssl() ? 'https:' : 'http:' ) . $url;
				}
				$ids[ esc_url_raw( $url ) ] = $attachment_id;
			}
			$out = array();
			foreach ( $urls as $url ) {
				$out[] = array(
					'id'  => isset( $ids[ $url ] ) ? (int) $ids[ $url ] : 0,
					'url' => $url,
				);
			}
			return $out;
		}

		/**
		 * Media library ids among the product's image tokens ( its own and its option image sets' ).
		 *
		 * @param ec_product $product Product.
		 * @return int[]
		 */
		private static function attachment_tokens( $product ) {
			if ( ! isset( $product->images ) || ! is_object( $product->images ) ) {
				return array();
			}
			$lists = array( isset( $product->images->product_images ) ? $product->images->product_images : array() );
			if ( isset( $product->images->imageset ) && is_array( $product->images->imageset ) ) {
				foreach ( $product->images->imageset as $set ) {
					if ( is_object( $set ) && isset( $set->product_images ) ) {
						$lists[] = $set->product_images;
					}
				}
			}
			$ids = array();
			foreach ( $lists as $list ) {
				if ( is_string( $list ) ) {
					$list = ( '' !== $list ) ? explode( ',', $list ) : array();
				}
				foreach ( (array) $list as $token ) {
					$token = trim( (string) $token );
					if ( '' !== $token && ctype_digit( $token ) ) {
						$ids[] = (int) $token;
					}
				}
			}
			return array_values( array_unique( $ids ) );
		}

		/**
		 * The product's active categories.
		 *
		 * @param ec_product $product Product.
		 * @return array List of objects: category_id, category_name ( in the shopper's language ), post_id.
		 */
		public static function categories( $product ) {
			$rows = ( isset( $product->categoryitems ) && is_array( $product->categoryitems ) ) ? $product->categoryitems : null;
			if ( null === $rows && class_exists( 'ec_db' ) ) {
				$db   = new ec_db();
				$rows = $db->get_category_values( (int) $product->product_id );
			}
			$out = array();
			foreach ( (array) $rows as $row ) {
				if ( ! is_object( $row ) || empty( $row->category_id ) ) {
					continue;
				}
				$name = isset( $row->category_name ) ? (string) $row->category_name : '';
				if ( function_exists( 'wp_easycart_language' ) ) {
					$name = (string) wp_easycart_language()->convert_text( $name );
				}
				$out[] = (object) array(
					'category_id'   => (int) $row->category_id,
					'category_name' => trim( wp_strip_all_tags( stripslashes( $name ) ) ),
					'post_id'       => isset( $row->post_id ) ? (int) $row->post_id : 0,
				);
			}
			return $out;
		}

		/**
		 * A category's page.
		 *
		 * @param ec_product $product  Product ( its link style ).
		 * @param object     $category From categories().
		 * @return string
		 */
		public static function category_url( $product, $category ) {
			if ( method_exists( $product, 'get_category_link' ) ) {
				return (string) $product->get_category_link( $category->post_id, $category->category_id );
			}
			return $category->post_id ? (string) get_permalink( $category->post_id ) : '';
		}

		/**
		 * The visitor's cart ( wp_easycart_cart_state(): never creates a session ). Read once per request, never stored.
		 *
		 * @return array count, subtotal_display, items.
		 */
		public static function cart_state() {
			if ( null === self::$cart ) {
				$state = function_exists( 'wp_easycart_cart_state' ) ? wp_easycart_cart_state() : array();
				if ( ! is_array( $state ) ) {
					$state = array();
				}
				self::$cart = array_merge(
					array(
						'count'            => 0,
						'subtotal_display' => '',
						'items'            => array(),
					),
					$state
				);
			}
			return self::$cart;
		}

		/**
		 * Forget the cart read in this request ( after something changed it ).
		 */
		public static function forget_cart() {
			self::$cart = null;
		}

		/**
		 * Whether a customer is signed in to the store ( not a guest checkout ).
		 *
		 * @return bool
		 */
		public static function customer_signed_in() {
			$user = isset( $GLOBALS['ec_user'] ) ? $GLOBALS['ec_user'] : null;
			if ( ! is_object( $user ) || empty( $user->user_id ) || (int) $user->user_id <= 0 ) {
				return false;
			}
			$cart_data = ( isset( $GLOBALS['ec_cart_data'] ) && is_object( $GLOBALS['ec_cart_data'] ) && isset( $GLOBALS['ec_cart_data']->cart_data ) ) ? $GLOBALS['ec_cart_data']->cart_data : null;
			$is_guest  = is_object( $cart_data ) && ! empty( $cart_data->is_guest );
			return ! $is_guest;
		}

		/**
		 * The signed-in customer's first name ( '' when nobody is signed in ).
		 *
		 * @return string
		 */
		public static function customer_first_name() {
			if ( ! self::customer_signed_in() ) {
				return '';
			}
			return trim( wp_strip_all_tags( stripslashes( (string) $GLOBALS['ec_user']->first_name ) ) );
		}
	}

endif;
