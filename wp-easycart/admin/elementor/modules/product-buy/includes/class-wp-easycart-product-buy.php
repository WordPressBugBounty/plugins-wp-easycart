<?php
/**
 * Product purchase widgets ( 6.0.2 ): the facts and markup the Add to Cart, Product Price, Product Stock, Product SKU,
 * Product Gallery and Product Badges widgets share.
 *
 * Loaded with the module on every request ( no Elementor dependency ), so nothing here may assume Elementor is active.
 *
 * Contract with the storefront script ( assets/product-buy.js ):
 * - every widget root carries data-product-id; the price, stock and SKU values also carry the Phase 0 attributes
 *   data-wpec-linked-product / data-wpec-linked-rand, so ec-store.js's own updaters ( wpeasycart_details_linked_price(),
 *   wpeasycart_details_linked_stock() and the SKU update ) reach them;
 * - a gallery prints the hooks ec_option1_image_change() looks for ( .ec_details_main_image[data-product-id][data-rand-id]
 *   and one #ec_details_thumbnails_{optionitem}_{product}_{rand} set per option image set ), so option images switch it;
 * - the script also listens to the foundation's wpeasycart_product_state event when it exists.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Product_Buy' ) ) :

	/**
	 * Shared helpers of the product purchase widgets ( static ).
	 */
	class WP_EasyCart_Product_Buy {

		/**
		 * Script and style handle of the module.
		 */
		const HANDLE = 'wpeasycart-el-product-buy';

		/**
		 * Language file section of the module's shopper-facing text.
		 */
		const LANGUAGE_SECTION = 'elementor_product';

		/**
		 * The most products one read for the New badge holds ( see is_new() ).
		 */
		const NEW_LIMIT = 1000;

		/**
		 * Products added within $new_window days: product id => age in seconds ( see is_new() ).
		 *
		 * @var array
		 */
		private static $new_ages = array();

		/**
		 * Days $new_ages covers ( 0 = not read yet this request ).
		 *
		 * @var int
		 */
		private static $new_window = 0;

		/**
		 * Whether $new_ages holds every product added within its window ( false when the read reached NEW_LIMIT ).
		 *
		 * @var bool
		 */
		private static $new_complete = true;

		/**
		 * Registers the module's script and stylesheet ( the widgets ask for them through get_script_depends() /
		 * get_style_depends(), so they only load where a widget is used ).
		 */
		public static function register_assets() {
			if ( wp_script_is( self::HANDLE, 'registered' ) ) {
				return;
			}
			$base    = plugins_url( 'assets/', dirname( __DIR__ ) . '/module.php' );
			$version = defined( 'EC_CURRENT_VERSION' ) ? EC_CURRENT_VERSION : '6.0.2';
			wp_register_style( self::HANDLE, $base . 'product-buy.css', array(), $version );
			wp_register_script( self::HANDLE, $base . 'product-buy.js', array( 'jquery' ), $version, true );
		}

		/**
		 * Shopper-facing text from the language file ( section elementor_product unless given ), with an English fallback
		 * ( the language system answers nothing for a key a store's saved language data does not have yet ).
		 *
		 * @param string $key      Language key.
		 * @param string $fallback English text.
		 * @param string $section  Language section.
		 * @return string Text that may contain the language system's own markup ( print it through esc_html() or wp_kses_post() ).
		 */
		public static function text( $key, $fallback, $section = self::LANGUAGE_SECTION ) {
			if ( function_exists( 'wp_easycart_language' ) ) {
				$text = wp_easycart_language()->get_text( $section, $key );
				if ( is_string( $text ) && '' !== trim( $text ) ) {
					return $text;
				}
			}
			return $fallback;
		}

		/**
		 * The shipped storefront template the widgets draw with. A theme copy in wp-easycart-data only changes the legacy
		 * shortcodes and widgets: the new widgets need the 6.0.2 template ( filter to point them at a copy ).
		 *
		 * @param string $file Template file name.
		 * @return string
		 */
		public static function template( $file ) {
			$layout = get_option( 'ec_option_latest_layout' );
			if ( ! is_string( $layout ) || '' === $layout ) {
				$layout = 'base-responsive-v3';
			}
			$path = EC_PLUGIN_DIRECTORY . '/design/layout/' . sanitize_file_name( $layout ) . '/' . $file;
			/**
			 * Template file a product purchase widget includes.
			 *
			 * @since 6.0.2
			 *
			 * @param string $path Absolute path.
			 * @param string $file Template file name.
			 */
			return (string) apply_filters( 'wp_easycart_elementor_product_buy_template', $path, $file );
		}

		/**
		 * The store's own copy of a storefront template that the widgets leave unused, or ''. EasyCart's shortcodes and the
		 * older widgets draw with a copy in wp-easycart-data ( design/layout/<ec_option_base_layout>/ ) when there is one;
		 * the new widgets draw with the shipped template ( template() ), so the Add to Cart widget tells the editor. '' too
		 * when filter wp_easycart_elementor_product_buy_template already points the widgets at that copy.
		 *
		 * @since 6.0.2
		 *
		 * @param string $file Template file name.
		 * @return string Absolute path of the copy, or ''.
		 */
		public static function unused_copy( $file ) {
			$layout = get_option( 'ec_option_base_layout' );
			if ( ! defined( 'EC_PLUGIN_DATA_DIRECTORY' ) || ! is_string( $layout ) || '' === $layout ) {
				return '';
			}
			$copy = EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . sanitize_file_name( $layout ) . '/' . basename( (string) $file );
			if ( ! is_readable( $copy ) ) {
				return '';
			}
			$used = realpath( self::template( $file ) );
			return ( false !== $used && realpath( $copy ) === $used ) ? '' : $copy;
		}

		/**
		 * The add to cart area of the Add to Cart widget ( included by ec_product_details_page_add_to_cart.php ).
		 *
		 * @return string
		 */
		public static function area_template() {
			return dirname( __DIR__ ) . '/templates/add-to-cart-area.php';
		}

		/**
		 * The next per-request product instance number, as the product detail shortcodes count them.
		 *
		 * @return int
		 */
		public static function next_rand() {
			$GLOBALS['wpeasycart_prod_details_count'] = ( isset( $GLOBALS['wpeasycart_prod_details_count'] ) ) ? (int) $GLOBALS['wpeasycart_prod_details_count'] + 1 : 1;
			return (int) $GLOBALS['wpeasycart_prod_details_count'];
		}

		/**
		 * What every product purchase widget does before it draws: keep the page out of page caches ( stock, prices and
		 * nonces ) and print the product's structured data once ( not in the editor, nor in an Elementor Pro Loop Grid
		 * card: a listing page carries no Product block per card ).
		 *
		 * @param ec_product $product   Product.
		 * @param bool       $is_editor Whether the Elementor editor draws the widget.
		 */
		public static function prepare( $product, $is_editor ) {
			if ( function_exists( 'wp_easycart_product_details_no_cache' ) ) {
				wp_easycart_product_details_no_cache();
			}
			$card = class_exists( 'WP_EasyCart_Elementor_Widget_Base' ) && WP_EasyCart_Elementor_Widget_Base::ec_is_loop_card();
			if ( ! $is_editor && ! $card && function_exists( 'wp_easycart_product_details_schema' ) ) {
				wp_easycart_product_details_schema( $product );
			}
		}

		/**
		 * Whether the store hides this product's price from this shopper ( login for pricing, catalog or inquiry mode ).
		 *
		 * @param ec_product $product Product.
		 * @return bool
		 */
		public static function price_hidden( $product ) {
			if ( $product->login_for_pricing && ! $product->is_login_for_pricing_valid() ) {
				return true;
			}
			if ( $product->is_catalog_mode && get_option( 'ec_option_hide_price_seasonal' ) ) {
				return true;
			}
			if ( $product->is_inquiry_mode && get_option( 'ec_option_hide_price_inquiry' ) ) {
				return true;
			}
			return false;
		}

		/**
		 * The "log in for pricing" message of a product whose price needs an account, or an empty array.
		 *
		 * @param ec_product $product Product.
		 * @return array { text, url } ( url '' when the shopper is signed in without access ).
		 */
		public static function login_for_price( $product ) {
			if ( ! $product->login_for_pricing || $product->is_login_for_pricing_valid() ) {
				return array();
			}
			$signed_in = ( isset( $GLOBALS['ec_user'] ) && is_object( $GLOBALS['ec_user'] ) && ! empty( $GLOBALS['ec_user']->user_id ) );
			if ( $signed_in ) {
				$text = self::text( 'product_page_login_for_price_no_access', __( 'Your account does not have access to this price.', 'wp-easycart' ), 'product_page' );
				return array(
					'text' => (string) apply_filters( 'wp_easycart_login_for_pricing_text', $text, $product->product_id ),
					'url'  => '',
				);
			}
			$label = ( '' !== (string) $product->login_for_pricing_label ) ? (string) $product->login_for_pricing_label : self::text( 'product_page_login_for_price', __( 'Log in for pricing', 'wp-easycart' ), 'product_page' );
			return array(
				'text' => $label,
				'url'  => (string) $product->account_page,
			);
		}

		/**
		 * Stock facts of a product.
		 *
		 * @param ec_product $product Product.
		 * @return array {
		 *     @type bool   $tracked   Stock is tracked ( for the product or per option ).
		 *     @type bool   $variants  Stock is tracked per option, so it changes with the shopper's choice.
		 *     @type int    $quantity  Units in stock ( all variants together when tracked per option ).
		 *     @type string $status    in | out | backorder.
		 *     @type bool   $counts    The store shows stock counts ( Settings › Products ).
		 *     @type string $fill_date Backorder fill date text.
		 * }
		 */
		public static function stock( $product ) {
			$tracked  = ( $product->show_stock_quantity || $product->use_optionitem_quantity_tracking );
			$quantity = (int) $product->stock_quantity;
			$status   = 'in';
			if ( $tracked && $quantity <= 0 ) {
				$status = ( $product->allow_backorders ) ? 'backorder' : 'out';
			}
			return array(
				'tracked'   => (bool) $tracked,
				'variants'  => (bool) $product->use_optionitem_quantity_tracking,
				'quantity'  => $quantity,
				'status'    => $status,
				'counts'    => (bool) get_option( 'ec_option_show_stock_quantity' ),
				'fill_date' => (string) $product->backorder_fill_date,
			);
		}

		/**
		 * How much the product is reduced by, in percent ( 0 when it is not on sale or its price is hidden ): the struck
		 * regular price against what the shopper pays, Offers included ( see price_state() ).
		 *
		 * @param ec_product $product Product.
		 * @return int
		 */
		public static function sale_percent( $product ) {
			$state = self::price_state( $product );
			return $state['percent'];
		}

		/**
		 * Whether WP EasyCart PRO's Offers can preview prices on this store.
		 *
		 * @since 6.0.2
		 *
		 * @return bool
		 */
		public static function offers_on() {
			return function_exists( 'wp_easycart_offers_active' ) && wp_easycart_offers_active() && class_exists( 'ec_offer_display' ) && method_exists( 'ec_offer_display', 'get_product_price_preview' );
		}

		/**
		 * The Offers price preview of a price, or false. The list price goes with it, so an offer that leaves sale items
		 * alone previews nothing on a product on sale ( as the cart then gives nothing ).
		 *
		 * @since 6.0.2
		 *
		 * @param ec_product $product Product.
		 * @param float      $price   Price ( with the default options ).
		 * @param float      $list_price List price ( 0 = none ).
		 * @return float|false
		 */
		public static function offer_preview( $product, $price, $list_price ) {
			$price = (float) $price;
			if ( $price <= 0 || ! self::offers_on() ) {
				return false;
			}
			$preview = ec_offer_display::get_product_price_preview( $product->product_id, $product->manufacturer_id, $price, (float) $list_price );
			return ( false !== $preview && is_numeric( $preview ) && (float) $preview < $price ) ? (float) $preview : false;
		}

		/**
		 * The Offers rules behind offer_preview(), for the browser ( product-buy.js works the offer price out again when the
		 * shopper's options change the price ). An empty array when no offer applies; null when the active WP EasyCart PRO
		 * cannot say ( older than 6.0.2 ).
		 *
		 * @since 6.0.2
		 *
		 * @param ec_product $product Product.
		 * @param float      $price   Price.
		 * @param float      $list_price List price.
		 * @return array|null
		 */
		public static function offer_rules( $product, $price, $list_price ) {
			if ( ! self::offers_on() || (float) $price <= 0 ) {
				return array();
			}
			if ( ! method_exists( 'ec_offer_display', 'get_product_price_rules' ) ) {
				return null;
			}
			$rules = ec_offer_display::get_product_price_rules( $product->product_id, $product->manufacturer_id, (float) $price, (float) $list_price );
			$out   = array();
			foreach ( (array) $rules as $rule ) {
				if ( ! is_array( $rule ) || ! isset( $rule['kind'], $rule['value'] ) ) {
					continue;
				}
				$out[] = array(
					'kind'  => in_array( $rule['kind'], array( 'percent', 'amount' ), true ) ? $rule['kind'] : 'fixed',
					'value' => (float) $rule['value'],
					'floor' => isset( $rule['floor'] ) ? (float) $rule['floor'] : 0.0,
				);
			}
			return $out;
		}

		/**
		 * The price offer_rules() give $price, or false when none lowers it. The same sums as WP EasyCart PRO's
		 * ec_offer_display ( and product-buy.js's offerPrice() ).
		 *
		 * @since 6.0.2
		 *
		 * @param array $rules Rules.
		 * @param float $price Price.
		 * @return float|false
		 */
		public static function apply_offer_rules( $rules, $price ) {
			$price = (float) $price;
			$best  = false;
			if ( $price <= 0 || ! is_array( $rules ) ) {
				return false;
			}
			foreach ( $rules as $rule ) {
				$value = isset( $rule['value'] ) ? (float) $rule['value'] : 0.0;
				$kind  = isset( $rule['kind'] ) ? $rule['kind'] : 'percent';
				if ( 'percent' === $kind ) {
					$preview = max( 0, $price - ( $price * $value / 100 ) );
				} elseif ( 'amount' === $kind ) {
					$preview = max( 0, $price - $value );
				} else {
					$preview = min( $price, $value );
				}
				$floor = isset( $rule['floor'] ) ? (float) $rule['floor'] : 0.0;
				if ( $floor > 0 && $preview < $floor ) {
					$preview = min( $price, $floor );
				}
				if ( false === $best || $preview < $best ) {
					$best = $preview;
				}
			}
			return ( false !== $best && $best < $price ) ? (float) $best : false;
		}

		/**
		 * The price before offers, as the product page shows it: the price with the options picked by default.
		 *
		 * @since 6.0.2
		 *
		 * @param ec_product $product Product.
		 * @return float
		 */
		public static function base_price( $product ) {
			return ( isset( $product->price_options ) && is_numeric( $product->price_options ) ) ? (float) $product->price_options : (float) $product->price;
		}

		/**
		 * The one reading of a product's prices that the price, the sale badge, "Your price" and the savings line share.
		 *
		 * @since 6.0.2
		 *
		 * @param ec_product $product Product.
		 * @return array {
		 *     @type bool       $hidden  The price is hidden from this shopper ( nothing else is filled ).
		 *     @type float      $base    The price before offers ( with the default options ).
		 *     @type float      $list    The list price ( 0 = none ).
		 *     @type float      $regular The price shown struck through: the higher of list and base, when above final ( else 0 ).
		 *     @type float      $sale    The sale price ( base, when the list price is above it; else 0 ).
		 *     @type float      $final   What the shopper pays: the Offers preview when there is one, else base.
		 *     @type bool       $offer   An Offers price preview applies.
		 *     @type int        $percent The reduction from regular to final, in percent ( 0 = none ).
		 *     @type float      $save    regular − final ( 0 = none ).
		 *     @type array|null $rules   The Offers rules for the browser ( offer_rules() ).
		 * }
		 */
		public static function price_state( $product ) {
			$state = array(
				'hidden'  => false,
				'base'    => 0.0,
				'list'    => 0.0,
				'regular' => 0.0,
				'sale'    => 0.0,
				'final'   => 0.0,
				'offer'   => false,
				'percent' => 0,
				'save'    => 0.0,
				'rules'   => array(),
			);
			if ( self::price_hidden( $product ) ) {
				$state['hidden'] = true;
				return $state;
			}
			$base  = self::base_price( $product );
			$list  = max( 0.0, (float) $product->list_price );
			$final = $base;
			/* A price label that replaces the price shows no offer price ( as ec_product_details_page_price.php ). */
			$label   = ! empty( $product->replace_price_label ) && isset( $product->enable_price_label ) && in_array( (int) $product->enable_price_label, array( 2, 4, 6, 7 ), true );
			$preview = $label ? false : self::offer_preview( $product, $base, $list );
			if ( false !== $preview ) {
				$final          = $preview;
				$state['offer'] = true;
				$state['rules'] = self::offer_rules( $product, $base, $list );
			}
			$regular          = max( $list, $base );
			$state['base']    = $base;
			$state['list']    = $list;
			$state['sale']    = ( $list > $base ) ? $base : 0.0;
			$state['final']   = $final;
			$state['regular'] = ( $regular > $final ) ? $regular : 0.0;
			if ( $state['regular'] > 0 ) {
				$state['save']    = $state['regular'] - $final;
				$state['percent'] = max( 1, (int) round( 100 - ( ( $final / $state['regular'] ) * 100 ) ) );
			}
			return $state;
		}

		/**
		 * Whether a product was added to the store in the last $days days.
		 *
		 * One read answers every product drawn in the request ( a Loop Grid or template with Badges on each product ): the
		 * products added within the widest window asked so far, newest first, with their age by the database's clock ( the
		 * column holds the database's time ). A wider window reads again. When more than NEW_LIMIT products are that new ( a
		 * large import ), a product missing from the read is looked up on its own, once.
		 *
		 * @param ec_product $product Product.
		 * @param int        $days    Days.
		 * @return bool
		 */
		public static function is_new( $product, $days ) {
			global $wpdb;
			$days       = min( 36500, (int) $days );
			$product_id = (int) $product->product_id;
			if ( $days <= 0 || ! $product_id ) {
				return false;
			}
			if ( $days > self::$new_window ) {
				$rows           = $wpdb->get_results( $wpdb->prepare( 'SELECT product_id, TIMESTAMPDIFF( SECOND, added_to_db_date, NOW() ) AS age FROM ec_product WHERE added_to_db_date >= DATE_SUB( NOW(), INTERVAL %d DAY ) ORDER BY added_to_db_date DESC LIMIT %d', $days, self::NEW_LIMIT ), ARRAY_N );
				self::$new_ages = array();
				foreach ( (array) $rows as $row ) {
					self::$new_ages[ (int) $row[0] ] = (int) $row[1];
				}
				self::$new_window   = $days;
				self::$new_complete = ( count( self::$new_ages ) < self::NEW_LIMIT );
			}
			if ( ! isset( self::$new_ages[ $product_id ] ) && ! self::$new_complete ) {
				$age                           = $wpdb->get_var( $wpdb->prepare( 'SELECT TIMESTAMPDIFF( SECOND, added_to_db_date, NOW() ) FROM ec_product WHERE product_id = %d', $product_id ) );
				self::$new_ages[ $product_id ] = ( null === $age ) ? PHP_INT_MAX : (int) $age;
			}
			return isset( self::$new_ages[ $product_id ] ) && self::$new_ages[ $product_id ] < $days * DAY_IN_SECONDS;
		}

		/**
		 * Quantity price tiers the shopper gets ( none with a role price ), lowest quantity first.
		 *
		 * @param ec_product $product Product.
		 * @return array List of array( quantity, price ).
		 */
		public static function price_tiers( $product ) {
			if ( ! empty( $product->using_role_price ) || ! is_array( $product->pricetiers ) || self::price_hidden( $product ) ) {
				return array();
			}
			$tiers = array();
			foreach ( $product->pricetiers as $tier ) {
				if ( is_array( $tier ) && isset( $tier[0], $tier[1] ) && (int) $tier[1] > 0 ) {
					$tiers[] = array( (int) $tier[1], (float) $tier[0] );
				}
			}
			usort(
				$tiers,
				function ( $a, $b ) {
					return $a[0] - $b[0];
				}
			);
			return $tiers;
		}

		/**
		 * Draws the Product Price widget's price with EasyCart's price template ( VAT split, price labels, the Offers
		 * price preview, promotion lines ), so the options chosen in an add to cart form reach it.
		 *
		 * @param ec_product $product Product.
		 * @param int        $rand    Instance number.
		 * @param bool       $regular Show the regular price when the product is on sale.
		 * @param array      $args    6.0.2: 'prefix' / 'suffix', text printed before / after the price ( inside each price row ).
		 * @return string
		 */
		public static function price_html( $product, $rand, $regular, $args = array() ) {
			$template = self::template( 'ec_product_details_page_price.php' );
			if ( ! is_readable( $template ) ) {
				return '';
			}
			$wpec_price_prefix                   = isset( $args['prefix'] ) ? (string) $args['prefix'] : '';
			$wpec_price_suffix                   = isset( $args['suffix'] ) ? (string) $args['suffix'] : '';
			$atts                                = array(
				'show_price'       => true,
				'show_list_price'  => (bool) $regular,
				'price_font'       => false,
				'price_color'      => false,
				'list_price_font'  => false,
				'list_price_color' => false,
			);
			$wpeasycart_addtocart_shortcode_rand = (int) $rand;
			ob_start();
			include $template;
			return (string) ob_get_clean();
		}

		/**
		 * Draws the Add to Cart widget with EasyCart's add to cart template. $wpec_el_buy switches the template to the
		 * widget's own add to cart area ( templates/add-to-cart-area.php ); everything else ( options, prices, stock data,
		 * validation ) is the template the product page uses.
		 *
		 * @param ec_product $product     Product.
		 * @param array      $wpec_el_buy Widget choices ( see WP_EasyCart_Elementor_Add_To_Cart_Widget::render() ).
		 */
		public static function render_add_to_cart( $product, $wpec_el_buy ) {
			$template = self::template( 'ec_product_details_page_add_to_cart.php' );
			if ( ! is_readable( $template ) ) {
				return;
			}
			/* The variables the template reads. */
			$enable_quantity                     = ( 'none' !== $wpec_el_buy['quantity'] );
			$enable_your_price                   = ! empty( $wpec_el_buy['your_price'] );
			$minus_icon                          = '';
			$plus_icon                           = '';
			$minus_icon_html                     = '';
			$plus_icon_html                      = '';
			$background_add                      = false;
			$atts                                = array();
			$wpeasycart_addtocart_shortcode_rand = 0;
			include $template;
		}

		/**
		 * Image sets of a product for the gallery. Products with images per option get one set per option item ( the set
		 * of optionitem 0 is the product's own images ); the others one set, 0.
		 *
		 * @param ec_product $product Product.
		 * @param array      $args    'image_size', 'thumb_size', 'zoom_size' ( WordPress image sizes ).
		 * @return array { sets: optionitem id => list of items, initial: optionitem id }. An item is an array: type
		 *               ( image | video | embed ), src, srcset, width, height, alt, full, thumb, thumb_srcset, video.
		 */
		public static function gallery( $product, $args ) {
			$args  = wp_parse_args(
				$args,
				array(
					'image_size' => 'large',
					'thumb_size' => 'thumbnail',
					'zoom_size'  => 'full',
				)
			);
			$alt   = wp_strip_all_tags( stripslashes( (string) $product->title ) );
			$sets  = array();
			$first = false;
			if ( $product->use_optionitem_images && isset( $product->images->imageset ) && is_array( $product->images->imageset ) ) {
				$allowed = self::gallery_optionitems( $product );
				$first   = $product->get_details_initial_imageset_id();
				foreach ( $product->images->imageset as $set ) {
					$optionitem_id = (int) $set->optionitem_id;
					if ( 0 !== $optionitem_id && ! in_array( $optionitem_id, $allowed, true ) && $optionitem_id !== (int) $first ) {
						continue;
					}
					$items = array();
					if ( is_array( $set->product_images ) && count( $set->product_images ) > 0 ) {
						foreach ( $set->product_images as $entry ) {
							$item = self::gallery_item( $product, $entry, $set, $args, $alt );
							if ( $item ) {
								$items[] = $item;
							}
						}
					} else {
						for ( $n = 1; $n <= 5; $n++ ) {
							$file = isset( $set->{'image' . $n} ) ? trim( (string) $set->{'image' . $n} ) : '';
							if ( '' !== $file ) {
								$items[] = self::url_item( self::legacy_file_url( $file, $n ), $alt );
							}
						}
					}
					if ( count( $items ) > 0 && ! isset( $sets[ $optionitem_id ] ) ) {
						$sets[ $optionitem_id ] = $items;
					}
				}
			}
			if ( ! $sets ) {
				$items = array();
				if ( isset( $product->images->product_images ) && is_array( $product->images->product_images ) && count( $product->images->product_images ) > 0 ) {
					foreach ( $product->images->product_images as $entry ) {
						$item = self::gallery_item( $product, $entry, null, $args, $alt );
						if ( $item ) {
							$items[] = $item;
						}
					}
				} else {
					$items[] = self::url_item( $product->get_first_image_url(), $alt );
					$more    = array(
						2 => 'get_second_image_url',
						3 => 'get_third_image_url',
						4 => 'get_fourth_image_url',
						5 => 'get_fifth_image_url',
					);
					foreach ( $more as $n => $method ) {
						if ( isset( $product->images->{'image' . $n} ) && '' !== trim( (string) $product->images->{'image' . $n} ) ) {
							$items[] = self::url_item( $product->$method(), $alt );
						}
					}
				}
				$items = array_values( array_filter( $items ) );
				if ( $items ) {
					$sets[0] = $items;
				}
				$first = 0;
			}
			if ( false === $first || ! isset( $sets[ (int) $first ] ) ) {
				$keys  = array_keys( $sets );
				$first = ( isset( $sets[0] ) || ! $keys ) ? 0 : (int) $keys[0];
			}
			/* The default set first: ec_option1_image_change() falls back to the first set on the page. */
			if ( isset( $sets[0] ) ) {
				$sets = array( 0 => $sets[0] ) + $sets;
			}
			return array(
				'sets'    => $sets,
				'initial' => (int) $first,
				'field'   => self::gallery_field( $product ),
			);
		}

		/**
		 * The add to cart form field whose option item picks the image set ( ec_option1, or the first advanced list, swatch or
		 * radio option ), '' without images per option.
		 *
		 * @param ec_product $product Product.
		 * @return string
		 */
		private static function gallery_field( $product ) {
			if ( ! $product->use_optionitem_images ) {
				return '';
			}
			if ( ! $product->use_advanced_optionset ) {
				return 'ec_option1';
			}
			foreach ( (array) $product->advanced_optionsets as $optionset ) {
				if ( in_array( $optionset->option_type, array( 'combo', 'swatch', 'radio' ), true ) ) {
					return 'ec_option_adv_' . (int) $optionset->option_to_product_id;
				}
			}
			return '';
		}

		/**
		 * The option items whose image sets the product page offers ( as ec_product_details_page_images.php works it out ).
		 *
		 * @param ec_product $product Product.
		 * @return int[]
		 */
		private static function gallery_optionitems( $product ) {
			$ids = array();
			if ( $product->use_advanced_optionset ) {
				foreach ( (array) $product->advanced_optionsets as $optionset ) {
					if ( in_array( $optionset->option_type, array( 'combo', 'swatch', 'radio' ), true ) ) {
						foreach ( (array) $product->get_advanced_optionitems( $optionset->option_id ) as $optionitem ) {
							$ids[] = (int) $optionitem->optionitem_id;
						}
						break;
					}
				}
			} elseif ( isset( $product->options->optionset1->optionset ) && is_array( $product->options->optionset1->optionset ) ) {
				foreach ( $product->options->optionset1->optionset as $optionitem ) {
					$id = (int) $optionitem->optionitem_id;
					if ( ! $product->options->verify_optionitem( 1, $id ) ) {
						continue;
					}
					$quantity = ( is_array( $product->option1quantity ) && isset( $product->option1quantity[ $id ] ) ) ? $product->option1quantity[ $id ] : 0;
					if ( $product->allow_backorders || ! $product->use_optionitem_quantity_tracking || $quantity > 0 ) {
						$ids[] = $id;
					}
				}
			}
			return $ids;
		}

		/**
		 * One gallery item from a product image entry ( image1 … image5, image:URL, video:, youtube:, vimeo:, or a media
		 * library attachment id ).
		 *
		 * @param ec_product  $product Product.
		 * @param string      $entry   Entry.
		 * @param object|null $set     Option image set, or null for the product's own images.
		 * @param array       $args    Image sizes.
		 * @param string      $alt     Fallback alternative text.
		 * @return array|null
		 */
		private static function gallery_item( $product, $entry, $set, $args, $alt ) {
			$entry = (string) $entry;
			if ( preg_match( '/^image([1-5])$/', $entry, $match ) ) {
				$n = (int) $match[1];
				if ( $set ) {
					$file = isset( $set->{'image' . $n} ) ? trim( (string) $set->{'image' . $n} ) : '';
					return ( '' !== $file ) ? self::url_item( self::legacy_file_url( $file, $n ), $alt ) : null;
				}
				$methods = array(
					1 => 'get_first_image_url',
					2 => 'get_second_image_url',
					3 => 'get_third_image_url',
					4 => 'get_fourth_image_url',
					5 => 'get_fifth_image_url',
				);
				return self::url_item( $product->{$methods[ $n ]}(), $alt );
			}
			if ( 'image:' === substr( $entry, 0, 6 ) ) {
				$url = (string) apply_filters( 'wp_easycart_product_details_image_url_type', substr( $entry, 6 ) );
				return self::url_item( $url, $alt );
			}
			$videos = array(
				'video'   => 'video',
				'youtube' => 'embed',
				'vimeo'   => 'embed',
			);
			foreach ( $videos as $prefix => $type ) {
				if ( 0 === strpos( $entry, $prefix . ':' ) ) {
					$parts = explode( ':::', substr( $entry, strlen( $prefix ) + 1 ) );
					if ( count( $parts ) < 2 || '' === trim( $parts[0] ) ) {
						return null;
					}
					return array(
						'type'         => $type,
						'video'        => trim( $parts[0] ),
						'src'          => trim( $parts[1] ),
						'srcset'       => '',
						'width'        => 0,
						'height'       => 0,
						'alt'          => $alt,
						'full'         => trim( $parts[1] ),
						'thumb'        => trim( $parts[1] ),
						'thumb_srcset' => '',
					);
				}
			}
			if ( ! ctype_digit( $entry ) ) {
				return null;
			}
			$attachment_id = (int) $entry;
			$main          = wp_get_attachment_image_src( $attachment_id, $args['image_size'] );
			if ( ! $main || empty( $main[0] ) ) {
				return null;
			}
			$full      = wp_get_attachment_image_src( $attachment_id, $args['zoom_size'] );
			$thumb     = wp_get_attachment_image_src( $attachment_id, $args['thumb_size'] );
			$image_alt = trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
			$srcset    = wp_get_attachment_image_srcset( $attachment_id, $args['image_size'] );
			$thumb_set = wp_get_attachment_image_srcset( $attachment_id, $args['thumb_size'] );
			return array(
				'type'         => 'image',
				'video'        => '',
				'src'          => $main[0],
				'srcset'       => ( $srcset ) ? $srcset : '',
				'width'        => isset( $main[1] ) ? (int) $main[1] : 0,
				'height'       => isset( $main[2] ) ? (int) $main[2] : 0,
				'alt'          => ( '' !== $image_alt ) ? $image_alt : $alt,
				'full'         => ( $full && ! empty( $full[0] ) ) ? $full[0] : $main[0],
				'thumb'        => ( $thumb && ! empty( $thumb[0] ) ) ? $thumb[0] : $main[0],
				'thumb_srcset' => ( $thumb_set ) ? $thumb_set : '',
			);
		}

		/**
		 * A gallery item for an image known only by its address ( EasyCart's own upload folders, or a link ).
		 *
		 * @param string $url Image address.
		 * @param string $alt Alternative text.
		 * @return array|null
		 */
		private static function url_item( $url, $alt ) {
			$url = trim( (string) $url );
			if ( '' === $url ) {
				return null;
			}
			return array(
				'type'         => 'image',
				'video'        => '',
				'src'          => $url,
				'srcset'       => '',
				'width'        => 0,
				'height'       => 0,
				'alt'          => $alt,
				'full'         => $url,
				'thumb'        => $url,
				'thumb_srcset' => '',
			);
		}

		/**
		 * Address of an image stored in EasyCart's pics1 … pics5 folders ( or a full link ).
		 *
		 * @param string $file File name or link.
		 * @param int    $n    Image number 1 … 5.
		 * @return string
		 */
		private static function legacy_file_url( $file, $n ) {
			if ( 'http://' === substr( $file, 0, 7 ) || 'https://' === substr( $file, 0, 8 ) ) {
				return $file;
			}
			return plugins_url( '/wp-easycart-data/products/pics' . (int) $n . '/' . $file, EC_PLUGIN_DATA_DIRECTORY );
		}

		/**
		 * The quantity box of the Add to Cart widget. It keeps the classes and data attributes ec-store.js reads
		 * ( .ec_details_quantity data-*, .ec_minus, .ec_plus, .ec_quantity #ec_quantity_{product}_{rand} ); the +/- buttons have
		 * no inline handler, ec-store.js binds them.
		 *
		 * @param ec_product $product Product.
		 * @param int        $rand    Form instance number.
		 * @param array      $args    'style' buttons | input | none, 'max' ( max attribute, 0 = none ), 'data_max'
		 *                            ( data-max-purchase-quantity ), 'minus' / 'plus' ( icon markup ).
		 * @return string
		 */
		public static function quantity_html( $product, $rand, $args ) {
			$args       = wp_parse_args(
				$args,
				array(
					'style'    => 'buttons',
					'max'      => 0,
					'data_max' => 0,
					'minus'    => '',
					'plus'     => '',
				)
			);
			$product_id = (int) $product->product_id;
			$min        = ( $product->min_purchase_quantity > 0 ) ? (int) $product->min_purchase_quantity : 1;
			$input_id   = 'ec_quantity_' . $product_id . '_' . $rand;
			$style      = in_array( $args['style'], array( 'buttons', 'input', 'none' ), true ) ? $args['style'] : 'buttons';
			$out        = '<div class="ec_details_quantity wpec-atc__qty wpec-atc__qty--' . esc_attr( $style ) . '"';
			$out       .= ' data-use-advanced-optionset="' . ( ( $product->use_advanced_optionset || $product->use_both_option_types ) ? '1' : '0' ) . '"';
			$out       .= ' data-product-id="' . esc_attr( $product_id ) . '" data-rand-id="' . esc_attr( $rand ) . '"';
			$out       .= ' data-min-purchase-quantity="' . esc_attr( $min ) . '" data-max-purchase-quantity="' . esc_attr( (int) $args['data_max'] ) . '"';
			$out       .= ' data-show-stock-quantity="' . esc_attr( $product->show_stock_quantity ) . '"' . ( ( 'none' === $style ) ? ' hidden' : '' ) . '>';
			$out       .= '<label class="wpec-sr" for="' . esc_attr( $input_id ) . '">' . esc_html( self::text( 'quantity_label', __( 'Quantity', 'wp-easycart' ) ) ) . '</label>';
			if ( 'buttons' === $style ) {
				$out .= '<button type="button" class="ec_minus wpec-atc__qty-btn" aria-label="' . esc_attr( self::text( 'decrease_quantity', __( 'Decrease quantity', 'wp-easycart' ) ) ) . '">' . ( ( '' !== $args['minus'] ) ? $args['minus'] : '<span aria-hidden="true">&minus;</span>' ) . '</button>';
			}
			$out .= '<input type="number" value="' . esc_attr( $min ) . '" name="ec_quantity" id="' . esc_attr( $input_id ) . '" autocomplete="off" inputmode="numeric" step="1" min="' . esc_attr( $min ) . '" class="ec_quantity wpec-atc__qty-input"' . ( ( (int) $args['max'] > 0 ) ? ' max="' . esc_attr( (int) $args['max'] ) . '"' : '' ) . ' />';
			if ( 'buttons' === $style ) {
				$out .= '<button type="button" class="ec_plus wpec-atc__qty-btn" aria-label="' . esc_attr( self::text( 'increase_quantity', __( 'Increase quantity', 'wp-easycart' ) ) ) . '">' . ( ( '' !== $args['plus'] ) ? $args['plus'] : '<span aria-hidden="true">+</span>' ) . '</button>';
			}
			$out .= '</div>';
			return $out;
		}

		/**
		 * Prints quantity_html().
		 *
		 * @param ec_product $product Product.
		 * @param int        $rand    Form instance number.
		 * @param array      $args    See quantity_html().
		 */
		public static function print_quantity( $product, $rand, $args ) {
			echo self::quantity_html( $product, $rand, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in quantity_html().
		}

		/**
		 * Prints button_html().
		 *
		 * @param array $args See button_html().
		 */
		public static function print_button( $args ) {
			echo self::button_html( $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in button_html().
		}

		/**
		 * Prints stock_html().
		 *
		 * @param ec_product $product Product.
		 * @param array      $args    See stock_html().
		 */
		public static function print_stock( $product, $args ) {
			echo self::stock_html( $product, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in stock_html().
		}

		/**
		 * A button ( submit or link ) of the Add to Cart widget.
		 *
		 * @param array $args 'label', 'href' ( a link when set ), 'onclick' ( JavaScript ), 'class', 'icon' ( markup ),
		 *                    'icon_position' before | after, 'attrs' ( name => value ).
		 * @return string
		 */
		public static function button_html( $args ) {
			$args  = wp_parse_args(
				$args,
				array(
					'label'         => '',
					'href'          => '',
					'onclick'       => '',
					'class'         => '',
					'icon'          => '',
					'icon_position' => 'before',
					'attrs'         => array(),
				)
			);
			$inner = '<span class="wpec-atc__button-text">' . esc_html( wp_strip_all_tags( $args['label'] ) ) . '</span>';
			if ( '' !== $args['icon'] ) {
				$icon  = '<span class="wpec-atc__button-icon" aria-hidden="true">' . $args['icon'] . '</span>';
				$inner = ( 'after' === $args['icon_position'] ) ? $inner . $icon : $icon . $inner;
			}
			$attrs = '';
			foreach ( (array) $args['attrs'] as $name => $value ) {
				$attrs .= ' ' . preg_replace( '/[^a-z0-9\-]/', '', strtolower( (string) $name ) ) . '="' . esc_attr( $value ) . '"';
			}
			$class = trim( 'wpec-atc__button ' . $args['class'] );
			if ( '' !== $args['href'] ) {
				return '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $args['href'] ) . '"' . $attrs . '>' . $inner . '</a>';
			}
			return '<button type="submit" class="' . esc_attr( $class ) . '"' . ( ( '' !== $args['onclick'] ) ? ' onclick="' . esc_attr( $args['onclick'] ) . '"' : '' ) . $attrs . '>' . $inner . '</button>';
		}

		/**
		 * The stock line of the Product Stock widget ( and the Add to Cart widget's stock note ). The hidden
		 * .ec_details_stock_total_ele carries the Phase 0 link, so ec-store.js writes the chosen variant's stock into it and
		 * product-buy.js redraws the line.
		 *
		 * @param ec_product $product Product.
		 * @param array      $args    'rand' ( instance number ), 'style' text | bar, 'low' ( low stock threshold ), 'show_count',
		 *                            'untracked' ( show "In stock" for products without stock tracking ), 'bar_max', 'class';
		 *                            6.0.2: 'texts' ( state => the widget's own text: in, count, low, out, backorder; '' keeps
		 *                            the store's ), 'icons' ( state => icon markup: in, low, out, backorder ), 'hide_in' ( nothing
		 *                            shows while in stock ).
		 * @return string '' when there is nothing to show.
		 */
		public static function stock_html( $product, $args ) {
			$args  = wp_parse_args(
				$args,
				array(
					'rand'       => 0,
					'style'      => 'text',
					'low'        => 5,
					'show_count' => true,
					'untracked'  => false,
					'bar_max'    => 20,
					'class'      => '',
					'texts'      => array(),
					'icons'      => array(),
					'hide_in'    => false,
				)
			);
			$stock = self::stock( $product );
			if ( ! $stock['tracked'] && ! $args['untracked'] ) {
				return '';
			}
			$date  = ( '' !== trim( $stock['fill_date'] ) ) ? ' ' . self::text( 'product_details_backorder_until', __( 'until', 'wp-easycart' ), 'product_details' ) . ' ' . $stock['fill_date'] : '';
			$texts = array(
				'in'        => self::text( 'in_stock', __( 'In stock', 'wp-easycart' ) ),
				'count'     => self::text( 'stock_count', __( '[count] in stock', 'wp-easycart' ) ),
				'low'       => self::text( 'low_stock', __( 'Only [count] left', 'wp-easycart' ) ),
				'out'       => self::text( 'product_details_out_of_stock', __( 'Out of stock', 'wp-easycart' ), 'product_details' ),
				'backorder' => self::text( 'backorder', __( 'Available on backorder', 'wp-easycart' ) ),
			);
			foreach ( (array) $args['texts'] as $text_key => $own ) {
				if ( isset( $texts[ $text_key ] ) && is_string( $own ) && '' !== trim( $own ) ) {
					$texts[ $text_key ] = $own;
				}
			}
			$texts['backorder'] .= $date;
			$icons               = array();
			foreach ( array( 'in', 'low', 'out', 'backorder' ) as $icon_state ) {
				if ( ! empty( $args['icons'][ $icon_state ] ) && is_string( $args['icons'][ $icon_state ] ) ) {
					$icons[ $icon_state ] = $args['icons'][ $icon_state ];
				}
			}
			$counts = ( $stock['counts'] && $args['show_count'] );
			$low    = max( 0, (int) $args['low'] );
			$state  = $stock['status'];
			$text   = $texts[ $state ];
			if ( 'in' === $state && $stock['tracked'] ) {
				if ( $low > 0 && $stock['quantity'] <= $low ) {
					$state = 'low';
					$text  = $counts ? str_replace( '[count]', (string) $stock['quantity'], $texts['low'] ) : $texts['in'];
				} elseif ( $counts ) {
					$text = str_replace( '[count]', (string) $stock['quantity'], $texts['count'] );
				}
			}
			$bar_max = max( 1, (int) $args['bar_max'] );
			$rand    = (int) $args['rand'];
			$style   = ( 'bar' === $args['style'] ) ? 'bar' : 'text';
			$class   = 'wpec-el wpec-stock wpec-stock--' . $style . ' is-' . $state . ( ( '' !== $args['class'] ) ? ' ' . $args['class'] : '' );
			$class  .= ( $icons ? ' wpec-stock--icons' : '' ) . ( $args['hide_in'] ? ' wpec-stock--hide-in' : '' );
			foreach ( array_keys( $icons ) as $icon_state ) {
				$class .= ' wpec-stock--icon-' . $icon_state;
			}
			$out  = '<div class="' . esc_attr( $class ) . '"';
			$out .= ' data-product-id="' . esc_attr( $product->product_id ) . '" data-wpec-status="' . esc_attr( $stock['status'] ) . '"';
			$out .= ' data-wpec-quantity="' . esc_attr( $stock['quantity'] ) . '" data-wpec-tracked="' . ( $stock['tracked'] ? '1' : '0' ) . '"';
			$out .= ' data-wpec-backorders="' . ( $product->allow_backorders ? '1' : '0' ) . '" data-wpec-counts="' . ( $counts ? '1' : '0' ) . '"';
			$out .= ' data-wpec-low="' . esc_attr( $low ) . '" data-wpec-bar-max="' . esc_attr( $bar_max ) . '"';
			$out .= ' data-wpec-texts="' . esc_attr( wp_json_encode( array_map( 'wp_strip_all_tags', $texts ) ) ) . '">';
			$out .= '<span class="wpec-stock__icon" aria-hidden="true"></span>';
			/* 6.0.2: an icon per state ( the widget's choice ); the stylesheet shows the one for the state on the line. */
			foreach ( $icons as $icon_state => $icon ) {
				$out .= '<span class="wpec-stock__state-icon wpec-stock__state-icon--' . esc_attr( $icon_state ) . '" aria-hidden="true">' . $icon . '</span>';
			}
			$out .= '<span class="wpec-stock__text" aria-live="polite">' . esc_html( wp_strip_all_tags( $text ) ) . '</span>';
			if ( 'bar' === $style ) {
				$fill = ( 'in' === $state || 'low' === $state ) ? min( 100, (int) round( ( max( 0, $stock['quantity'] ) / $bar_max ) * 100 ) ) : 0;
				if ( ! $stock['tracked'] ) {
					$fill = 100;
				}
				$out .= '<span class="wpec-stock__bar" aria-hidden="true"><span class="wpec-stock__bar-fill" style="width:' . esc_attr( $fill ) . '%"></span></span>';
			}
			if ( $stock['tracked'] ) {
				$out .= '<span class="ec_details_stock_total_ele wpec-stock__link" data-wpec-linked-product="' . esc_attr( $product->product_id ) . '" data-wpec-linked-rand="' . esc_attr( $rand ) . '" hidden>';
				$out .= '<span id="ec_details_stock_quantity_' . esc_attr( $product->product_id ) . '_' . esc_attr( $rand ) . '">' . esc_html( $stock['quantity'] ) . '</span></span>';
			}
			$out .= '</div>';
			return $out;
		}

		/**
		 * The WordPress image sizes for the panel ( name => label ).
		 *
		 * @return array
		 */
		public static function image_size_options() {
			$options = array();
			if ( function_exists( 'get_intermediate_image_sizes' ) ) {
				foreach ( get_intermediate_image_sizes() as $size ) {
					$options[ $size ] = ucwords( str_replace( array( '_', '-' ), ' ', $size ) );
				}
			}
			$options['full'] = __( 'Full size', 'wp-easycart' );
			return $options;
		}

		/**
		 * A WordPress image size the site has, else $fallback.
		 *
		 * @param mixed  $size     Chosen size.
		 * @param string $fallback Size to use instead.
		 * @return string
		 */
		public static function image_size( $size, $fallback ) {
			$size = is_string( $size ) ? $size : '';
			if ( 'full' === $size || ( function_exists( 'get_intermediate_image_sizes' ) && in_array( $size, get_intermediate_image_sizes(), true ) ) ) {
				return $size;
			}
			return $fallback;
		}
	}

endif;
