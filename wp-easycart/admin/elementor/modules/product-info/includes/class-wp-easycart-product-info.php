<?php
/**
 * Product information for the 6.0.2 Elementor widgets ( module product-info ).
 *
 * Shopper text, ratings, product content, the category path, share links, related products, the reviews block, view events
 * and the module's assets. No Elementor dependency: module.php loads it on every request, and the widgets call it while they
 * draw.
 *
 * Shopper text comes from the language section elementor_product_info ( and a few existing product_details / customer_review
 * keys ), with an English fallback here, because the language system answers nothing for a key a store has not merged yet.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Product_Info' ) ) :

	/**
	 * Helpers for the product information widgets.
	 */
	final class WP_EasyCart_Product_Info {

		/**
		 * Script and style handle.
		 */
		const HANDLE = 'wp-easycart-el-product-info';

		/**
		 * Language section of this module.
		 */
		const SECTION = 'elementor_product_info';

		/**
		 * The module's URL ( trailing slash ), set by module.php.
		 *
		 * @var string
		 */
		public static $url = '';

		/**
		 * The module's folder ( trailing slash ), set by module.php.
		 *
		 * @var string
		 */
		public static $dir = '';

		/**
		 * Elementor templates being drawn inside a tab ( a template that holds the same tabs never loops ).
		 *
		 * @var array
		 */
		private static $template_stack = array();

		/**
		 * Products whose view events went out on this page.
		 *
		 * @var array
		 */
		private static $viewed = array();

		/**
		 * Breadcrumb data for search engines went out on this page.
		 *
		 * @var bool
		 */
		private static $breadcrumb_schema = false;

		/**
		 * Category rows looked up on this request ( category id => row or false ).
		 *
		 * @var array
		 */
		private static $categories = array();

		/**
		 * The Product Tabs widget's own tabs ( text, templates ) waiting to print on the panel hook while WP EasyCart Tabs
		 * draws the tab area ( "<product>|<rand>" => HTML ).
		 *
		 * @var array
		 */
		private static $own_panels = array();

		/**
		 * Register the module's stylesheet and script ( widgets list them in get_style_depends() / get_script_depends(); Elementor
		 * enqueues them only on pages that use those widgets ).
		 */
		public static function register_assets() {
			if ( '' === self::$url ) {
				return;
			}
			$version = defined( 'EC_CURRENT_VERSION' ) ? EC_CURRENT_VERSION : '6.0.2';
			wp_register_style( self::HANDLE, self::$url . 'assets/product-info.css', array(), $version );
			wp_register_script( self::HANDLE, self::$url . 'assets/product-info.js', array( 'jquery' ), $version, true );
		}

		/**
		 * Where the module lives ( called from module.php ).
		 *
		 * @param string $module_file module.php.
		 */
		public static function setup( $module_file ) {
			self::$url = plugin_dir_url( $module_file );
			self::$dir = trailingslashit( dirname( $module_file ) );
		}

		/**
		 * Shopper text: the store's language file, else the English fallback. Plain text ( escape it where it is printed ).
		 *
		 * @param string $key      Key in the section.
		 * @param string $fallback English text.
		 * @param string $section  Language section ( default elementor_product_info ).
		 * @return string
		 */
		public static function text( $key, $fallback, $section = self::SECTION ) {
			$value = '';
			if ( function_exists( 'wp_easycart_language' ) ) {
				$value = (string) wp_easycart_language()->get_text( $section, $key );
			}
			$value = trim( html_entity_decode( wp_strip_all_tags( $value ), ENT_QUOTES, 'UTF-8' ) );
			return ( '' !== $value ) ? $value : (string) $fallback;
		}

		/**
		 * A label a widget lets the merchant change: their text, else the shopper text.
		 *
		 * @param array  $settings Widget settings.
		 * @param string $control  Control ID.
		 * @param string $fallback  Shopper text to use when the control is empty.
		 * @return string
		 */
		public static function label( $settings, $control, $fallback ) {
			$value = ( isset( $settings[ $control ] ) && is_string( $settings[ $control ] ) ) ? trim( $settings[ $control ] ) : '';
			return ( '' !== $value ) ? $value : $fallback;
		}

		/**
		 * A number for this block's element IDs. Shares the product widgets' counter, so the IDs never clash with an older
		 * widget on the same page; the editor draws one widget at a time, so it uses a random one there.
		 *
		 * @return int
		 */
		public static function rand() {
			if ( self::is_editor() ) {
				return wp_rand( 100000, 999999 );
			}
			$GLOBALS['wpeasycart_prod_details_count'] = isset( $GLOBALS['wpeasycart_prod_details_count'] ) ? (int) $GLOBALS['wpeasycart_prod_details_count'] + 1 : 1;
			return (int) $GLOBALS['wpeasycart_prod_details_count'];
		}

		/**
		 * Whether Elementor's editor or its preview is drawing.
		 *
		 * @return bool
		 */
		public static function is_editor() {
			return function_exists( 'wp_easycart_elementor_is_editor' ) && wp_easycart_elementor_is_editor();
		}

		/**
		 * A settings switch: 'yes' ( or a true value ) is on.
		 *
		 * @param array  $settings Settings.
		 * @param string $key      Control ID.
		 * @return bool
		 */
		public static function on( $settings, $key ) {
			if ( ! isset( $settings[ $key ] ) ) {
				return false;
			}
			$value = $settings[ $key ];
			return ( 'yes' === $value || true === $value || 1 === $value || '1' === $value );
		}

		/**
		 * A product's rating from its approved reviews ( the reviews the product page lists ): exact average ( one decimal, as
		 * the product's structured data gives it ), count and the 1-5 star distribution.
		 *
		 * @param ec_product $product Product.
		 * @return array { average: float, count: int, dist: int[1..5] }
		 */
		public static function rating( $product ) {
			$dist  = array(
				1 => 0,
				2 => 0,
				3 => 0,
				4 => 0,
				5 => 0,
			);
			$count = 0;
			$sum   = 0;
			if ( is_object( $product ) && ! empty( $product->reviews ) && is_array( $product->reviews ) ) {
				foreach ( $product->reviews as $review ) {
					$stars = isset( $review->rating ) ? (int) $review->rating : 0;
					if ( $stars < 1 || $stars > 5 ) {
						continue;
					}
					++$dist[ $stars ];
					++$count;
					$sum += $stars;
				}
			}
			return array(
				'average' => $count ? round( $sum / $count, 1 ) : 0,
				'count'   => $count,
				'dist'    => $dist,
			);
		}

		/**
		 * A rating as a number the shopper reads ( 4 or 4.5 ).
		 *
		 * @param float $rating Rating.
		 * @return string
		 */
		public static function rating_number( $rating ) {
			$rating = (float) $rating;
			return number_format_i18n( $rating, ( floor( $rating ) === $rating ) ? 0 : 1 );
		}

		/**
		 * "Rated 4.5 out of 5".
		 *
		 * @param float $rating Rating.
		 * @return string
		 */
		public static function rating_label( $rating ) {
			return str_replace( '[rating]', self::rating_number( $rating ), self::text( 'rating_label', __( 'Rated [rating] out of 5', 'wp-easycart' ) ) );
		}

		/**
		 * "1 review" / "12 reviews".
		 *
		 * @param int $count Reviews.
		 * @return string
		 */
		public static function review_count( $count ) {
			$count = (int) $count;
			if ( 1 === $count ) {
				return self::text( 'review_count_one', __( '1 review', 'wp-easycart' ) );
			}
			return str_replace( '[count]', number_format_i18n( $count ), self::text( 'review_count_many', __( '[count] reviews', 'wp-easycart' ) ) );
		}

		/**
		 * Five stars filled to the exact rating ( 3.4 fills three stars and 40% of the fourth; never rounded up ). One element
		 * masked with a star; its fill is a CSS variable.
		 *
		 * @param float  $rating Rating 0-5.
		 * @param string $label  Accessible name ( default "Rated N out of 5" ).
		 * @param bool   $hidden The number is said elsewhere: hide the stars from screen readers.
		 * @param array  $args   'style' => filled ( default ) | outline ( empty stars drawn as outlines ) | icon ( five copies of
		 *                       'icon', escaped markup, filled the same way ).
		 * @return string HTML.
		 */
		public static function stars( $rating, $label = '', $hidden = false, $args = array() ) {
			$rating = max( 0, min( 5, (float) $rating ) );
			$fill   = sprintf( '%.2F', $rating / 5 * 100 );
			if ( '' === $label ) {
				$label = self::rating_label( $rating );
			}
			$aria  = $hidden ? ' aria-hidden="true"' : ' role="img" aria-label="' . esc_attr( $label ) . '"';
			$style = ( is_array( $args ) && isset( $args['style'] ) ) ? (string) $args['style'] : 'filled';
			$icon  = ( is_array( $args ) && isset( $args['icon'] ) ) ? (string) $args['icon'] : '';
			if ( 'icon' === $style && '' !== $icon ) {
				$row = str_repeat( '<span class="wpec-pi-stars__icon">' . $icon . '</span>', 5 );
				return '<span class="wpec-pi-stars wpec-pi-stars--icon"' . $aria . ' style="--wpec-pi-fill:' . esc_attr( $fill ) . '%"><span class="wpec-pi-stars__row wpec-pi-stars__row--empty">' . $row . '</span><span class="wpec-pi-stars__row wpec-pi-stars__row--full">' . $row . '</span></span>';
			}
			return '<span class="wpec-pi-stars' . ( 'outline' === $style ? ' wpec-pi-stars--outline' : '' ) . '"' . $aria . ' style="--wpec-pi-fill:' . esc_attr( $fill ) . '%"></span>';
		}

		/**
		 * The product's description as the product page shows it, or '' when it has none.
		 *
		 * @param ec_product $product Product.
		 * @return string HTML ( cleaned by the store's HTML filter ).
		 */
		public static function description_html( $product ) {
			if ( ! is_object( $product ) || '' === trim( (string) $product->description ) ) {
				return '';
			}
			ob_start();
			if ( '[ec' === substr( $product->description, 0, 3 ) ) {
				$product->display_product_description();
			} else {
				$content = do_shortcode( stripslashes( $product->description ) );
				$content = str_replace( ']]>', ']]&gt;', $content );
				echo wp_easycart_escape_html( $content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the store's HTML filter ( wp_kses with its product content tags ), as on the product page.
			}
			return trim( (string) ob_get_clean() );
		}

		/**
		 * The product's short description, or ''.
		 *
		 * @param ec_product $product Product.
		 * @return string HTML.
		 */
		public static function short_description_html( $product ) {
			if ( ! is_object( $product ) || ! isset( $product->short_description ) || '' === trim( (string) $product->short_description ) ) {
				return '';
			}
			return (string) wp_easycart_escape_html( nl2br( stripslashes( $product->short_description ) ) );
		}

		/**
		 * The product's specifications, or ''.
		 *
		 * @param ec_product $product Product.
		 * @return string HTML.
		 */
		public static function specifications_html( $product ) {
			if ( ! is_object( $product ) || ! isset( $product->specifications ) || '' === trim( (string) $product->specifications ) ) {
				return '';
			}
			ob_start();
			if ( '[ec' === substr( $product->specifications, 0, 3 ) ) {
				$product->display_product_specifications();
			} else {
				$content = do_shortcode( stripslashes( $product->specifications ) );
				$content = stripslashes( str_replace( ']]>', ']]&gt;', $content ) );
				echo wp_easycart_escape_html( $content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the store's HTML filter, as on the product page.
			}
			return trim( (string) ob_get_clean() );
		}

		/**
		 * One category row ( id, name, post, parent ), looked up once per request.
		 *
		 * @param int $category_id Category.
		 * @return object|null
		 */
		private static function category_row( $category_id ) {
			$category_id = (int) $category_id;
			if ( ! $category_id ) {
				return null;
			}
			if ( ! array_key_exists( $category_id, self::$categories ) ) {
				global $wpdb;
				$row                              = $wpdb->get_row( $wpdb->prepare( 'SELECT category_id, category_name, post_id, parent_id FROM ec_category WHERE category_id = %d AND is_active = 1', $category_id ) );
				self::$categories[ $category_id ] = $row ? $row : false;
			}
			return self::$categories[ $category_id ] ? self::$categories[ $category_id ] : null;
		}

		/**
		 * The product's categories as links ( name, url ).
		 *
		 * @param ec_product $product Product ( built as a details page ).
		 * @return array
		 */
		public static function categories( $product ) {
			$out = array();
			if ( ! is_object( $product ) || empty( $product->categoryitems ) || ! is_array( $product->categoryitems ) ) {
				return $out;
			}
			foreach ( $product->categoryitems as $item ) {
				if ( ! isset( $item->category_id ) ) {
					continue;
				}
				$out[] = array(
					'name' => self::convert( $item->category_name ),
					'url'  => $product->get_category_link( $item->post_id, $item->category_id ),
				);
			}
			return $out;
		}

		/**
		 * The product's first category with the categories above it, top first ( name, url ).
		 *
		 * @param ec_product $product Product.
		 * @param bool       $parents Include the parent categories.
		 * @return array
		 */
		public static function category_path( $product, $parents = true ) {
			if ( ! is_object( $product ) || empty( $product->categoryitems ) || ! is_array( $product->categoryitems ) ) {
				return array();
			}
			$first = reset( $product->categoryitems );
			if ( ! isset( $first->category_id ) ) {
				return array();
			}
			$chain = array( $first );
			if ( $parents ) {
				$row   = self::category_row( $first->category_id );
				$seen  = array( (int) $first->category_id );
				$steps = 0;
				while ( $row && (int) $row->parent_id && $steps < 5 && ! in_array( (int) $row->parent_id, $seen, true ) ) {
					++$steps;
					$seen[] = (int) $row->parent_id;
					$row    = self::category_row( $row->parent_id );
					if ( $row ) {
						array_unshift( $chain, $row );
					}
				}
			}
			$out = array();
			foreach ( $chain as $item ) {
				$out[] = array(
					'name' => self::convert( $item->category_name ),
					'url'  => $product->get_category_link( $item->post_id, $item->category_id ),
				);
			}
			return $out;
		}

		/**
		 * Text through the store's language tags ( [EN]…[/EN] ), as plain text.
		 *
		 * @param string $text Text.
		 * @return string
		 */
		public static function convert( $text ) {
			if ( function_exists( 'wp_easycart_language' ) ) {
				$text = wp_easycart_language()->convert_text( (string) $text );
			}
			return trim( html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES, 'UTF-8' ) );
		}

		/**
		 * The product's name as plain text.
		 *
		 * @param ec_product $product Product.
		 * @return string
		 */
		public static function title( $product ) {
			return is_object( $product ) ? trim( html_entity_decode( wp_strip_all_tags( (string) $product->title ), ENT_QUOTES, 'UTF-8' ) ) : '';
		}

		/**
		 * The breadcrumb trail: name + url ( '' for the product itself ).
		 *
		 * @param ec_product $product Product.
		 * @param array      $args    home, home_label, store, store_label, category ( path | category | '' ), current.
		 * @return array
		 */
		public static function breadcrumb_trail( $product, $args ) {
			$items = array();
			if ( ! empty( $args['home'] ) ) {
				$items[] = array(
					'name' => $args['home_label'],
					'url'  => home_url( '/' ),
				);
			}
			if ( ! empty( $args['store'] ) && ! empty( $product->store_page ) ) {
				$store = $product->store_page;
				if ( untrailingslashit( $store ) !== untrailingslashit( home_url( '/' ) ) || empty( $args['home'] ) ) {
					$items[] = array(
						'name' => $args['store_label'],
						'url'  => $store,
					);
				}
			}
			if ( ! empty( $args['category'] ) ) {
				foreach ( self::category_path( $product, ( 'path' === $args['category'] ) ) as $category ) {
					$items[] = $category;
				}
			}
			if ( ! empty( $args['current'] ) ) {
				$items[] = array(
					'name' => self::title( $product ),
					'url'  => '',
				);
			}
			return $items;
		}

		/**
		 * BreadcrumbList structured data for a trail, once per page. Skipped when an SEO plugin is active ( it describes the
		 * page's breadcrumbs itself ); filter wp_easycart_product_breadcrumbs_schema to decide otherwise.
		 *
		 * @param ec_product $product Product.
		 * @param array      $items   breadcrumb_trail().
		 */
		public static function print_breadcrumb_schema( $product, $items ) {
			if ( self::$breadcrumb_schema || count( $items ) < 2 ) {
				return;
			}
			$seo  = ( class_exists( 'wp_easycart_product_schema' ) && method_exists( 'wp_easycart_product_schema', 'seo_plugin' ) ) ? wp_easycart_product_schema::seo_plugin() : '';
			$show = (bool) apply_filters( 'wp_easycart_product_breadcrumbs_schema', ( '' === $seo ), $product, $items );
			if ( ! $show ) {
				return;
			}
			self::$breadcrumb_schema = true;
			$list                    = array();
			$position                = 0;
			foreach ( $items as $item ) {
				++$position;
				$url    = ( '' !== $item['url'] ) ? $item['url'] : $product->get_product_link();
				$list[] = array(
					'@type'    => 'ListItem',
					'position' => $position,
					'name'     => $item['name'],
					'item'     => $url,
				);
			}
			$data = array(
				'@context'        => 'https://schema.org',
				'@type'           => 'BreadcrumbList',
				'itemListElement' => $list,
			);
			echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ) . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON with JSON_HEX_TAG, which cannot close the script tag.
		}

		/**
		 * Share networks: slug => array( name, action text, default icon ).
		 *
		 * @return array
		 */
		public static function share_networks() {
			$x_icon = ( defined( 'ELEMENTOR_VERSION' ) && version_compare( ELEMENTOR_VERSION, '3.20.0', '<' ) ) ? 'fab fa-twitter' : 'fab fa-x-twitter';
			return array(
				'facebook'  => array(
					'name'   => 'Facebook',
					'action' => self::text( 'share_facebook', __( 'Share on Facebook', 'wp-easycart' ) ),
					'icon'   => array(
						'value'   => 'fab fa-facebook-f',
						'library' => 'fa-brands',
					),
				),
				'x'         => array(
					'name'   => 'X',
					'action' => self::text( 'share_x', __( 'Share on X', 'wp-easycart' ) ),
					'icon'   => array(
						'value'   => $x_icon,
						'library' => 'fa-brands',
					),
				),
				'pinterest' => array(
					'name'   => 'Pinterest',
					'action' => self::text( 'share_pinterest', __( 'Pin on Pinterest', 'wp-easycart' ) ),
					'icon'   => array(
						'value'   => 'fab fa-pinterest-p',
						'library' => 'fa-brands',
					),
				),
				'linkedin'  => array(
					'name'   => 'LinkedIn',
					'action' => self::text( 'share_linkedin', __( 'Share on LinkedIn', 'wp-easycart' ) ),
					'icon'   => array(
						'value'   => 'fab fa-linkedin-in',
						'library' => 'fa-brands',
					),
				),
				'email'     => array(
					'name'   => self::text( 'share_email_label', __( 'Email', 'wp-easycart' ) ),
					'action' => self::text( 'share_email', __( 'Share by email', 'wp-easycart' ) ),
					'icon'   => array(
						'value'   => 'fas fa-envelope',
						'library' => 'fa-solid',
					),
				),
				'copy'      => array(
					'name'   => self::text( 'share_copy', __( 'Copy link', 'wp-easycart' ) ),
					'action' => self::text( 'share_copy', __( 'Copy link', 'wp-easycart' ) ),
					'icon'   => array(
						'value'   => 'fas fa-link',
						'library' => 'fa-solid',
					),
				),
				'whatsapp'  => array(
					'name'   => 'WhatsApp',
					'action' => self::text( 'share_whatsapp', __( 'Share on WhatsApp', 'wp-easycart' ) ),
					'icon'   => array(
						'value'   => 'fab fa-whatsapp',
						'library' => 'fa-brands',
					),
				),
				'telegram'  => array(
					'name'   => 'Telegram',
					'action' => self::text( 'share_telegram', __( 'Share on Telegram', 'wp-easycart' ) ),
					'icon'   => array(
						'value'   => 'fab fa-telegram-plane',
						'library' => 'fa-brands',
					),
				),
				'reddit'    => array(
					'name'   => 'Reddit',
					'action' => self::text( 'share_reddit', __( 'Share on Reddit', 'wp-easycart' ) ),
					'icon'   => array(
						'value'   => 'fab fa-reddit-alien',
						'library' => 'fa-brands',
					),
				),
				'threads'   => array(
					'name'   => 'Threads',
					'action' => self::text( 'share_threads', __( 'Share on Threads', 'wp-easycart' ) ),
					'icon'   => array(
						'value'   => 'threads',
						'library' => 'wpec-svg',
					),
				),
				'bluesky'   => array(
					'name'   => 'Bluesky',
					'action' => self::text( 'share_bluesky', __( 'Share on Bluesky', 'wp-easycart' ) ),
					'icon'   => array(
						'value'   => 'bluesky',
						'library' => 'wpec-svg',
					),
				),
				'sms'       => array(
					'name'   => self::text( 'share_sms_label', __( 'Text message', 'wp-easycart' ) ),
					'action' => self::text( 'share_sms', __( 'Share by text message', 'wp-easycart' ) ),
					'icon'   => array(
						'value'   => 'fas fa-sms',
						'library' => 'fa-solid',
					),
				),
				'print'     => array(
					'name'   => self::text( 'share_print_label', __( 'Print', 'wp-easycart' ) ),
					'action' => self::text( 'share_print', __( 'Print this page', 'wp-easycart' ) ),
					'icon'   => array(
						'value'   => 'fas fa-print',
						'library' => 'fa-solid',
					),
				),
				'native'    => array(
					'name'   => self::text( 'share_native_label', __( 'Share', 'wp-easycart' ) ),
					'action' => self::text( 'share_native', __( 'Share with an app on this device', 'wp-easycart' ) ),
					'icon'   => array(
						'value'   => 'fas fa-share-alt',
						'library' => 'fa-solid',
					),
				),
			);
		}

		/**
		 * The share networks' colours ( brand mode ): slug => colour.
		 *
		 * @return array
		 */
		public static function share_brand_colors() {
			return array(
				'facebook'  => '#1877f2',
				'x'         => '#000000',
				'pinterest' => '#e60023',
				'linkedin'  => '#0a66c2',
				'email'     => '#5f6368',
				'copy'      => '#5f6368',
				'whatsapp'  => '#25d366',
				'telegram'  => '#229ed9',
				'reddit'    => '#ff4500',
				'threads'   => '#000000',
				'bluesky'   => '#1185fe',
				'sms'       => '#5f6368',
				'print'     => '#5f6368',
				'native'    => '#5f6368',
			);
		}

		/**
		 * Inline SVG icons the icon fonts may not have ( value of an ICONS setting with library wpec-svg ).
		 *
		 * @param string $name threads | bluesky.
		 * @return string SVG ( fixed markup ), or ''.
		 */
		public static function svg_icon( $name ) {
			$paths = array(
				'threads' => 'M12.19 24h-.01c-3.58-.02-6.33-1.2-8.18-3.51C2.35 18.44 1.5 15.59 1.47 12.01v-.02c.03-3.58.88-6.43 2.53-8.48C5.85 1.2 8.6.02 12.18 0h.01c2.75.02 5.04.72 6.83 2.1 1.68 1.29 2.86 3.13 3.51 5.47l-2.04.57c-1.1-3.96-3.9-5.98-8.3-6.02-2.91.02-5.11.94-6.54 2.72C4.31 6.5 3.62 8.91 3.59 12c.03 3.09.72 5.5 2.06 7.16 1.43 1.78 3.63 2.7 6.54 2.72 2.62-.02 4.36-.63 5.8-2.05 1.65-1.61 1.62-3.59 1.09-4.8-.31-.71-.87-1.3-1.63-1.75-.19 1.35-.62 2.45-1.28 3.27-.89 1.1-2.14 1.7-3.73 1.79-1.2.07-2.36-.22-3.26-.8-1.06-.69-1.69-1.74-1.75-2.96-.07-1.19.41-2.29 1.33-3.08.88-.76 2.12-1.21 3.58-1.29a13.85 13.85 0 0 1 3.02.14c-.13-.74-.38-1.33-.75-1.76-.51-.59-1.31-.88-2.36-.89h-.03c-.84 0-1.99.23-2.72 1.32l-1.76-1.18c.98-1.45 2.57-2.26 4.48-2.26h.04c3.19.02 5.1 1.98 5.29 5.39.11.05.22.09.32.14 1.49.7 2.58 1.76 3.15 3.07.8 1.82.87 4.79-1.55 7.16-1.85 1.81-4.09 2.63-7.28 2.65zm1-11.69c-.24 0-.49.01-.74.02-1.84.1-2.98.95-2.92 2.14.07 1.26 1.45 1.84 2.78 1.77 1.22-.07 2.82-.54 3.09-3.71a10.5 10.5 0 0 0-2.22-.22z',
				'bluesky' => 'M12 10.8c-1.09-2.11-4.05-6.05-6.8-7.99C2.57.94 1.56 1.27.9 1.57.14 1.91 0 3.08 0 3.77c0 .69.38 5.65.62 6.48.82 2.74 3.71 3.66 6.38 3.36-3.91.58-7.39 2-2.83 7.08 5.01 5.19 6.87-1.11 7.82-4.31.95 3.2 2.05 9.27 7.73 4.31 4.27-4.31 1.17-6.5-2.74-7.08 2.67.3 5.57-.63 6.38-3.36.25-.83.62-5.79.62-6.48 0-.69-.14-1.86-.9-2.21-.66-.3-1.66-.62-4.3 1.24C16.05 4.75 13.09 8.69 12 10.8z',
			);
			if ( ! isset( $paths[ $name ] ) ) {
				return '';
			}
			return '<svg class="wpec-pi-svg-icon" viewBox="0 0 24 24" width="1em" height="1em" aria-hidden="true" focusable="false"><path fill="currentColor" d="' . $paths[ $name ] . '"/></svg>';
		}

		/**
		 * An ICONS setting's markup: Elementor's icon ( Icons_Manager ), or one of this module's own SVG icons.
		 *
		 * @param array $icon ICONS value ( value, library ).
		 * @return string HTML ( '' when there is no icon ).
		 */
		public static function icon_html( $icon ) {
			if ( ! is_array( $icon ) || empty( $icon['value'] ) || empty( $icon['library'] ) ) {
				return '';
			}
			if ( 'wpec-svg' === $icon['library'] ) {
				return is_string( $icon['value'] ) ? self::svg_icon( $icon['value'] ) : '';
			}
			if ( ! class_exists( '\Elementor\Icons_Manager' ) ) {
				return '';
			}
			ob_start();
			\Elementor\Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) );
			return trim( (string) ob_get_clean() );
		}

		/**
		 * The product's image for sharing ( full URL ), or ''.
		 *
		 * @param ec_product $product Product.
		 * @return string
		 */
		public static function share_image( $product ) {
			if ( is_object( $product ) && isset( $product->social_icons ) && is_object( $product->social_icons ) && method_exists( $product->social_icons, 'get_image_url' ) ) {
				$image = (string) $product->social_icons->get_image_url();
				if ( preg_match( '#^https?://#i', $image ) && ! preg_match( '#/pics1/$#', $image ) ) {
					return $image;
				}
			}
			return '';
		}

		/**
		 * A network's share link for the product ( the product's own page, whatever page the buttons sit on ).
		 *
		 * @param string     $network Network slug.
		 * @param ec_product $product Product.
		 * @return string
		 */
		public static function share_url( $network, $product ) {
			$url   = $product->get_product_link();
			$title = self::title( $product );
			switch ( $network ) {
				case 'facebook':
					return 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode( $url );
				case 'x':
					return 'https://x.com/intent/tweet?url=' . rawurlencode( $url ) . '&text=' . rawurlencode( $title );
				case 'pinterest':
					$image = self::share_image( $product );
					return 'https://pinterest.com/pin/create/button/?url=' . rawurlencode( $url ) . ( '' !== $image ? '&media=' . rawurlencode( $image ) : '' ) . '&description=' . rawurlencode( $title );
				case 'linkedin':
					return 'https://www.linkedin.com/sharing/share-offsite/?url=' . rawurlencode( $url );
				case 'email':
					return 'mailto:?subject=' . rawurlencode( $title ) . '&body=' . rawurlencode( $title . "\n" . $url );
				case 'whatsapp':
					return 'https://api.whatsapp.com/send?text=' . rawurlencode( $title . ' ' . $url );
				case 'telegram':
					return 'https://t.me/share/url?url=' . rawurlencode( $url ) . '&text=' . rawurlencode( $title );
				case 'reddit':
					return 'https://www.reddit.com/submit?url=' . rawurlencode( $url ) . '&title=' . rawurlencode( $title );
				case 'threads':
					return 'https://www.threads.net/intent/post?text=' . rawurlencode( $title . ' ' . $url );
				case 'bluesky':
					return 'https://bsky.app/intent/compose?text=' . rawurlencode( $title . ' ' . $url );
				case 'sms':
					return 'sms:?&body=' . rawurlencode( $title . ' ' . $url );
			}
			return $url;
		}

		/**
		 * The URL schemes share links may use ( esc_url() drops the rest ).
		 *
		 * @return array
		 */
		public static function share_protocols() {
			return array( 'http', 'https', 'mailto', 'sms' );
		}

		/**
		 * Products to show beside this one: its featured products ( product editor, Featured Products ), then the most viewed
		 * products from its categories.
		 *
		 * @param ec_product $product Product ( built as a details page, which loads its featured products ).
		 * @param string     $source  auto | featured | category.
		 * @param int        $count   How many.
		 * @param array      $args    'orderby' ( default | newest | price_asc | price_desc | views | random: which products from
		 *                            the categories fill the slots ), 'in_stock' ( leave out products that are out of stock and
		 *                            take no backorders ).
		 * @return int[]
		 */
		public static function related_ids( $product, $source, $count, $args = array() ) {
			$count   = max( 1, min( 24, (int) $count ) );
			$ids     = array();
			$args    = is_array( $args ) ? $args : array();
			$orders  = self::related_orders();
			$orderby = ( isset( $args['orderby'] ) && isset( $orders[ $args['orderby'] ] ) ) ? $args['orderby'] : 'default';
			/* Integers and fixed SQL only: out of stock is no stock tracked, stock left, or backorders taken ( load_ec_product()'s in_stock rule ). */
			$stock = ! empty( $args['in_stock'] ) ? ' AND ( p.stock_quantity > 0 OR ( p.show_stock_quantity = 0 AND p.use_optionitem_quantity_tracking = 0 ) OR p.allow_backorders = 1 )' : '';
			if ( ! is_object( $product ) ) {
				return $ids;
			}
			if ( 'category' !== $source && isset( $product->featured_products ) && is_object( $product->featured_products ) ) {
				foreach ( array( 'product1', 'product2', 'product3', 'product4' ) as $slot ) {
					if ( isset( $product->featured_products->$slot ) && is_object( $product->featured_products->$slot ) && ! empty( $product->featured_products->$slot->product_id ) ) {
						$id = (int) $product->featured_products->$slot->product_id;
						if ( $id !== (int) $product->product_id && ! in_array( $id, $ids, true ) ) {
							$ids[] = $id;
						}
					}
				}
			}
			if ( $ids && '' !== $stock ) {
				global $wpdb;
				/* Keep the merchant's order; drop the featured products that cannot be bought now. */
				$ready = array_map( 'intval', (array) $wpdb->get_col( 'SELECT p.product_id FROM ec_product p WHERE p.product_id IN ( ' . implode( ',', array_map( 'intval', $ids ) ) . ' )' . $stock ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- integers only ( intval ) and fixed SQL.
				$ids   = array_values( array_intersect( $ids, $ready ) );
			}
			$ids = array_slice( $ids, 0, $count );
			if ( 'featured' !== $source && count( $ids ) < $count && ! empty( $product->categoryitems ) && is_array( $product->categoryitems ) ) {
				$categories = array();
				foreach ( $product->categoryitems as $item ) {
					if ( isset( $item->category_id ) && (int) $item->category_id ) {
						$categories[] = (int) $item->category_id;
					}
				}
				if ( $categories ) {
					global $wpdb;
					$exclude = array_merge( $ids, array( (int) $product->product_id ) );
					/* Integers only in both lists. EXISTS, not DISTINCT: MySQL 5.7+ refuses DISTINCT with an ORDER BY column it does not select. */
					/* Only products this shopper may see fill the slots ( customer role, hide out of stock, pickup location ): integers only, no %. */
					$visible = class_exists( 'WP_EasyCart_Elementor_Shop_Query' ) ? WP_EasyCart_Elementor_Shop_Query::visibility_sql( 'p' ) : '';
					$sql  = 'SELECT p.product_id FROM ec_product p WHERE p.activate_in_store = 1' . $visible . $stock . ' AND p.product_id NOT IN ( ' . implode( ',', array_map( 'intval', $exclude ) ) . ' ) AND EXISTS ( SELECT 1 FROM ec_categoryitem ci WHERE ci.product_id = p.product_id AND ci.category_id IN ( ' . implode( ',', array_map( 'intval', $categories ) ) . ' ) ) ORDER BY ' . $orders[ $orderby ]['sql'] . ' LIMIT %d';
					$more = $wpdb->get_col( $wpdb->prepare( $sql, $count - count( $ids ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- the two IN lists are integers ( intval ), the order is fixed SQL from related_orders(); the limit is prepared.
					foreach ( (array) $more as $id ) {
						$ids[] = (int) $id;
					}
				}
			}
			return array_slice( $ids, 0, $count );
		}

		/**
		 * Related products' orders: key => sql ( which products from the categories fill the slots, fixed SQL on ec_product p ),
		 * orderby / order ( how load_ec_product() then lists them; '' keeps the order the ids come in ).
		 *
		 * @return array
		 */
		public static function related_orders() {
			return array(
				'default'    => array(
					'sql'     => 'p.views DESC, p.product_id DESC',
					'orderby' => '',
					'order'   => '',
				),
				'newest'     => array(
					'sql'     => 'p.added_to_db_date DESC, p.product_id DESC',
					'orderby' => 'added_to_db_date',
					'order'   => 'DESC',
				),
				'price_asc'  => array(
					'sql'     => 'p.price ASC, p.product_id DESC',
					'orderby' => 'price',
					'order'   => 'ASC',
				),
				'price_desc' => array(
					'sql'     => 'p.price DESC, p.product_id DESC',
					'orderby' => 'price',
					'order'   => 'DESC',
				),
				'views'      => array(
					'sql'     => 'p.views DESC, p.product_id DESC',
					'orderby' => 'views',
					'order'   => 'DESC',
				),
				'random'     => array(
					'sql'     => 'RAND()',
					'orderby' => 'rand',
					'order'   => '',
				),
			);
		}

		/**
		 * Reviews in the order a widget asks for. The store lists them newest first; ties keep that order.
		 *
		 * @param array  $reviews Review rows ( ec_product::$reviews ).
		 * @param string $order   newest | oldest | highest | lowest.
		 * @return array
		 */
		public static function sort_reviews( $reviews, $order ) {
			$reviews = is_array( $reviews ) ? array_values( $reviews ) : array();
			if ( 'oldest' === $order ) {
				return array_reverse( $reviews );
			}
			if ( 'highest' !== $order && 'lowest' !== $order ) {
				return $reviews;
			}
			$keyed = array();
			foreach ( $reviews as $index => $review ) {
				$keyed[] = array( isset( $review->rating ) ? (int) $review->rating : 0, $index, $review );
			}
			$sign = ( 'highest' === $order ) ? -1 : 1;
			usort(
				$keyed,
				function ( $a, $b ) use ( $sign ) {
					if ( $a[0] !== $b[0] ) {
						return ( $a[0] < $b[0] ) ? -$sign : $sign;
					}
					return $a[1] - $b[1];
				}
			);
			$out = array();
			foreach ( $keyed as $row ) {
				$out[] = $row[2];
			}
			return $out;
		}

		/**
		 * The email each review was written from ( the reviewer's, else their account's ), for avatars: review id => email.
		 * One query for the list.
		 *
		 * @param array $reviews Review rows.
		 * @return array
		 */
		public static function review_emails( $reviews ) {
			global $wpdb;
			$ids = array();
			foreach ( is_array( $reviews ) ? $reviews : array() as $review ) {
				if ( isset( $review->review_id ) && (int) $review->review_id ) {
					$ids[] = (int) $review->review_id;
				}
			}
			if ( ! $ids || ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
				return array();
			}
			$rows = $wpdb->get_results( 'SELECT r.review_id, r.reviewer_email, u.email FROM ec_review r LEFT JOIN ec_user u ON u.user_id = r.user_id WHERE r.review_id IN ( ' . implode( ',', array_unique( $ids ) ) . ' )' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- integers only ( intval ).
			$out  = array();
			foreach ( (array) $rows as $row ) {
				$email = ( isset( $row->reviewer_email ) && '' !== trim( (string) $row->reviewer_email ) ) ? $row->reviewer_email : ( isset( $row->email ) ? $row->email : '' );
				if ( '' !== trim( (string) $email ) ) {
					$out[ (int) $row->review_id ] = trim( (string) $email );
				}
			}
			return $out;
		}

		/**
		 * The product's stock as a shopper reads it ( the Stock widget's words ): status in | out | backorder and the text.
		 *
		 * @param ec_product $product Product.
		 * @param bool       $count   Say how many are left when the store shows stock counts.
		 * @return array { status, text }
		 */
		public static function stock_status( $product, $count = true ) {
			$tracked  = ! empty( $product->show_stock_quantity ) || ! empty( $product->use_optionitem_quantity_tracking );
			$quantity = isset( $product->stock_quantity ) ? (int) $product->stock_quantity : 0;
			$status   = 'in';
			if ( $tracked && $quantity <= 0 ) {
				$status = ! empty( $product->allow_backorders ) ? 'backorder' : 'out';
			}
			if ( 'out' === $status ) {
				$text = self::text( 'product_details_out_of_stock', __( 'Out of stock', 'wp-easycart' ), 'product_details' );
			} elseif ( 'backorder' === $status ) {
				$text = self::text( 'meta_backorder', __( 'Available on backorder', 'wp-easycart' ) );
			} elseif ( $tracked && $count && get_option( 'ec_option_show_stock_quantity' ) ) {
				$text = str_replace( '[count]', number_format_i18n( $quantity ), self::text( 'meta_stock_count', __( '[count] in stock', 'wp-easycart' ) ) );
			} else {
				$text = self::text( 'meta_in_stock', __( 'In stock', 'wp-easycart' ) );
			}
			return array(
				'status' => $status,
				'text'   => $text,
			);
		}

		/**
		 * A number as the store writes it: up to two decimals, no trailing zeros.
		 *
		 * @param float $value Value.
		 * @return string
		 */
		public static function measure( $value ) {
			$value = round( (float) $value, 2 );
			return number_format_i18n( $value, ( floor( $value ) === $value ) ? 0 : ( ( floor( $value * 10 ) === $value * 10 ) ? 1 : 2 ) );
		}

		/**
		 * The store's units: dim ( in | cm ), weight ( lb | kg ), as the packages use them.
		 *
		 * @return array
		 */
		public static function units() {
			if ( class_exists( 'wp_easycart_packages' ) && method_exists( 'wp_easycart_packages', 'units' ) ) {
				return wp_easycart_packages::units();
			}
			$weight = (string) get_option( 'ec_option_paypal_weight_unit' );
			return array(
				'dim'    => get_option( 'ec_option_enable_metric_unit_display' ) ? 'cm' : 'in',
				'weight' => ( 'kgs' === $weight || 'kg' === $weight ) ? 'kg' : 'lb',
			);
		}

		/**
		 * The product's weight with the store's unit, or ''.
		 *
		 * @param ec_product $product Product.
		 * @return string
		 */
		public static function weight_text( $product ) {
			if ( ! is_object( $product ) || ! isset( $product->weight ) || (float) $product->weight <= 0 ) {
				return '';
			}
			$units = self::units();
			return self::measure( $product->weight ) . ' ' . $units['weight'];
		}

		/**
		 * The product's dimensions ( length × width × height ) with the store's unit, or '' when none are set.
		 *
		 * @param ec_product $product Product.
		 * @return string
		 */
		public static function dimensions_text( $product ) {
			if ( ! is_object( $product ) ) {
				return '';
			}
			$sizes = array(
				isset( $product->length ) ? (float) $product->length : 0,
				isset( $product->width ) ? (float) $product->width : 0,
				isset( $product->height ) ? (float) $product->height : 0,
			);
			if ( max( $sizes ) <= 0 ) {
				return '';
			}
			$units = self::units();
			return implode( ' × ', array_map( array( __CLASS__, 'measure' ), $sizes ) ) . ' ' . $units['dim'];
		}

		/**
		 * The brand's logo: the featured image of the brand's WordPress page, or what filter wp_easycart_product_brand_logo
		 * answers ( an attachment id or an image URL ). '' when there is none.
		 *
		 * @param ec_product $product Product.
		 * @return string HTML ( an img, alt = the brand's name ).
		 */
		public static function brand_logo( $product ) {
			global $wpdb;
			if ( ! is_object( $product ) || empty( $product->manufacturer_id ) ) {
				return '';
			}
			$logo = 0;
			if ( isset( $wpdb ) && is_object( $wpdb ) && function_exists( 'get_post_thumbnail_id' ) ) {
				$post_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT post_id FROM ec_manufacturer WHERE manufacturer_id = %d', (int) $product->manufacturer_id ) );
				$logo    = $post_id ? (int) get_post_thumbnail_id( $post_id ) : 0;
			}
			/**
			 * The brand logo the Product Meta widget shows ( an attachment id or an image URL; 0 or '' for none ).
			 *
			 * @since 6.0.2
			 * @param int|string $logo    The featured image of the brand's page, or 0.
			 * @param ec_product $product Product.
			 */
			$logo = apply_filters( 'wp_easycart_product_brand_logo', $logo, $product );
			$name = self::convert( $product->manufacturer_name );
			if ( is_numeric( $logo ) && (int) $logo > 0 && function_exists( 'wp_get_attachment_image' ) ) {
				return (string) wp_get_attachment_image(
					(int) $logo,
					'medium',
					false,
					array(
						'class' => 'wpec-pi-meta__logo',
						'alt'   => $name,
					)
				);
			}
			if ( is_string( $logo ) && preg_match( '#^https?://#i', $logo ) ) {
				return '<img class="wpec-pi-meta__logo" src="' . esc_url( $logo ) . '" alt="' . esc_attr( $name ) . '" loading="lazy" decoding="async" />';
			}
			return '';
		}

		/**
		 * WP EasyCart Tabs ( 3.x ) can draw this product's tab area in the store's tab style: something takes over the product
		 * templates' tab area, and WP EasyCart Tabs, when it is that extension, is not set to the classic style.
		 *
		 * @return bool
		 */
		public static function tabs_design_available() {
			if ( ! function_exists( 'wp_easycart_product_tabs_takeover' ) || ! function_exists( 'wp_easycart_product_tabs_area' ) || ! function_exists( 'wp_easycart_product_tabs_panel' ) || ! has_filter( 'wpeasycart_product_details_tabs_takeover' ) ) {
				return false;
			}
			if ( class_exists( 'wp_easycart_tabs' ) && method_exists( 'wp_easycart_tabs', 'is_takeover' ) ) {
				return (bool) wp_easycart_tabs::is_takeover();
			}
			return true;
		}

		/**
		 * The WP EasyCart Tabs settings screen ( its Design settings ).
		 *
		 * @return string
		 */
		public static function tabs_settings_url() {
			if ( class_exists( 'wp_easycart_tabs' ) && method_exists( 'wp_easycart_tabs', 'settings_url' ) ) {
				return (string) wp_easycart_tabs::settings_url();
			}
			return admin_url( 'admin.php?page=wp-easycart-extensions&subpage=tabs' );
		}

		/**
		 * WP EasyCart Tabs' stylesheets and script, so Elementor loads them in the head ( and in the editor's preview ) rather
		 * than the extension printing them late beside the tabs. Empty without the extension.
		 *
		 * @param string $type style | script.
		 * @return array Handles.
		 */
		public static function tabs_asset_handles( $type ) {
			if ( ! class_exists( 'wp_easycart_tabs_storefront' ) || ! defined( 'wp_easycart_tabs_storefront::HANDLE' ) ) {
				return array();
			}
			$handle = (string) constant( 'wp_easycart_tabs_storefront::HANDLE' );
			if ( 'script' === $type ) {
				return array( $handle );
			}
			$styles = array( $handle, $handle . '-blocks-content', $handle . '-blocks-story' );
			if ( defined( 'wp_easycart_tabs_storefront::CLASSIC' ) ) {
				array_unshift( $styles, (string) constant( 'wp_easycart_tabs_storefront::CLASSIC' ) );
			}
			return $styles;
		}

		/**
		 * Keep the Product Tabs widget's own tabs to print on the panel hook while the tab area is drawn.
		 *
		 * @param int    $product_id Product.
		 * @param int    $rand       Block number.
		 * @param string $html       Panels ( div.ec_details_<id>_tab ).
		 */
		public static function hold_own_panels( $product_id, $rand, $html ) {
			if ( '' === $html ) {
				unset( self::$own_panels[ (int) $product_id . '|' . $rand ] );
				return;
			}
			self::$own_panels[ (int) $product_id . '|' . $rand ] = $html;
		}

		/**
		 * Action wpeasycart_addon_product_details_tab_content ( only while the Product Tabs widget hands its tabs to WP EasyCart
		 * Tabs ): the widget's own panels, paired by the extension with the list items the widget passed.
		 *
		 * @param int        $product_id Product.
		 * @param int|string $rand       Block number.
		 */
		public static function print_own_panels( $product_id, $rand = '' ) {
			$key = (int) $product_id . '|' . $rand;
			if ( isset( self::$own_panels[ $key ] ) ) {
				echo self::$own_panels[ $key ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- panels built by the Product Tabs widget ( escaped titles, filtered content ).
				unset( self::$own_panels[ $key ] );
			}
		}

		/**
		 * Whether this shopper may write a review ( Settings › Products › Reviews: only signed-in shoppers ).
		 *
		 * @return bool
		 */
		public static function can_review() {
			if ( ! get_option( 'ec_option_customer_review_require_login' ) ) {
				return true;
			}
			return self::signed_in();
		}

		/**
		 * Whether the shopper is signed in to their store account.
		 *
		 * @return bool
		 */
		public static function signed_in() {
			$user_id = ( isset( $GLOBALS['ec_cart_data'] ) && is_object( $GLOBALS['ec_cart_data'] ) && isset( $GLOBALS['ec_cart_data']->cart_data->user_id ) ) ? $GLOBALS['ec_cart_data']->cart_data->user_id : '';
			return ( '' !== (string) $user_id && '0' !== (string) $user_id );
		}

		/**
		 * A review's date in the site's date format ( the database gives it in English ).
		 *
		 * @param ec_review $review Review.
		 * @return array { text, iso }
		 */
		public static function review_date( $review ) {
			$raw  = isset( $review->review_date ) ? (string) $review->review_date : '';
			$time = ( '' !== $raw ) ? strtotime( $raw ) : false;
			if ( ! $time ) {
				return array(
					'text' => $raw,
					'iso'  => '',
				);
			}
			return array(
				'text' => date_i18n( get_option( 'date_format' ), $time ),
				'iso'  => gmdate( 'Y-m-d', $time ),
			);
		}

		/**
		 * Print the reviews block ( rating summary, reviews, form ) for a product.
		 *
		 * @param ec_product $product Product.
		 * @param array      $args    See templates/reviews.php.
		 */
		public static function reviews( $product, $args = array() ) {
			$args = wp_parse_args(
				$args,
				array(
					'rand'         => 0,
					'summary'      => true,
					'list'         => true,
					'form'         => true,
					'list_heading' => true,
					'item_title'   => true,
					'item_date'    => true,
					'item_name'    => 'store',
					'item_rating'  => true,
					'item_text'    => true,
					'verified'     => true,
					'replies'      => true,
					'form_heading' => true,
					'form_title'   => '',
					'button_text'  => '',
					'class'        => '',
					'sort'         => 'newest',
					'per_page'     => 0,
					'more_text'    => '',
					'avatars'      => false,
					'avatar_size'  => 40,
					'form_guests'  => true,
				)
			);
			if ( ! $args['rand'] ) {
				$args['rand'] = self::rand();
			}
			$file = self::$dir . 'templates/reviews.php';
			if ( is_readable( $file ) ) {
				$wpeasycart_pi_product = $product;
				$wpeasycart_pi_args    = $args;
				include $file;
			}
		}

		/**
		 * Draw a saved Elementor template ( a tab's content ) while the product stays this widget's product. A template drawn
		 * inside itself is skipped.
		 *
		 * @param int        $template_id Template ( elementor_library post ).
		 * @param ec_product $product     Product.
		 * @return string HTML.
		 */
		public static function elementor_template( $template_id, $product ) {
			$template_id = (int) $template_id;
			if ( ! $template_id || ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance->frontend ) || in_array( $template_id, self::$template_stack, true ) ) {
				return '';
			}
			if ( 'elementor_library' !== get_post_type( $template_id ) || 'publish' !== get_post_status( $template_id ) ) {
				return '';
			}
			self::$template_stack[] = $template_id;
			$context                = function_exists( 'wp_easycart_elementor_context' ) ? wp_easycart_elementor_context() : null;
			if ( $context && is_object( $product ) ) {
				/* The id, not the object: the Tabs widget draws from a light build, and each widget inside the template asks the
				 * context for the build it needs ( categories, featured products ); builds stay cached per request. */
				$context->push_product( (int) $product->product_id );
			}
			$html = (string) \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $template_id, true );
			if ( $context && is_object( $product ) ) {
				$context->pop_product();
			}
			array_pop( self::$template_stack );
			return $html;
		}

		/**
		 * Output of an older tab hook ( extensions such as WP EasyCart Tabs add their tabs through it ), with the tabs taking
		 * this widget's tab class instead of the product page's ( whose stylesheet forces its own font and spacing ).
		 *
		 * @param string $hook       Action.
		 * @param int    $product_id Product.
		 * @param int    $rand       Block number.
		 * @return string HTML.
		 */
		public static function extension_tab_hook( $hook, $product_id, $rand ) {
			ob_start();
			switch ( $hook ) {
				case 'wpeasycart_pre_description_tab':
					do_action( 'wpeasycart_pre_description_tab', $product_id, $rand );
					break;
				case 'wpeasycart_pre_specifications_tab':
					do_action( 'wpeasycart_pre_specifications_tab', $product_id, $rand );
					break;
				case 'wpeasycart_pre_customer_reviews_tab':
					do_action( 'wpeasycart_pre_customer_reviews_tab', $product_id, $rand );
					break;
				case 'wpeasycart_addon_product_details_tab':
					do_action( 'wpeasycart_addon_product_details_tab', $product_id, $rand );
					break;
			}
			$html = (string) ob_get_clean();
			if ( '' === trim( $html ) ) {
				return '';
			}
			return (string) preg_replace_callback(
				'/<li\b[^>]*>/i',
				function ( $found ) {
					return preg_replace( '/(\bclass\s*=\s*["\'][^"\']*?)(?<![\w-])ec_details_tab(?![\w-])/i', '$1wpec-pi-tabs__tab wpec-pi-tabs__tab--extension', $found[0], 1 );
				},
				$html
			);
		}

		/**
		 * Output of an older tab panel hook ( extension panels ).
		 *
		 * @param int $product_id Product.
		 * @param int $rand       Block number.
		 * @return string HTML.
		 */
		public static function extension_panel_hook( $product_id, $rand ) {
			ob_start();
			do_action( 'wpeasycart_addon_product_details_tab_content', $product_id, $rand );
			return trim( (string) ob_get_clean() );
		}

		/**
		 * The product page's view events, once per product per page and never in the editor: action
		 * wp_easycart_product_details_before, Meta ViewContent and GA4 view_item ( what the older Product Meta widget sent ).
		 * Not for the cards of a Loop Grid ( the post drawn is not the page's ): a 24-card loop is not 24 product views.
		 *
		 * @param ec_product $product Product.
		 */
		public static function view_events( $product ) {
			if ( ! is_object( $product ) || empty( $product->product_id ) || self::is_editor() ) {
				return;
			}
			/* An assigned EasyCart product template already sends them for its page ( templates module ). */
			if ( function_exists( 'wp_easycart_elementor_template_rendering' ) && wp_easycart_elementor_template_rendering() ) {
				return;
			}
			/* A Loop Grid card: Elementor switched the post to the card's product ( the test the widgets' structured data uses too ). */
			if ( class_exists( 'WP_EasyCart_Elementor_Widget_Base' ) && WP_EasyCart_Elementor_Widget_Base::ec_is_loop_card() ) {
				return;
			}
			$id = (int) $product->product_id;
			if ( isset( self::$viewed[ $id ] ) ) {
				return;
			}
			self::$viewed[ $id ] = true;
			do_action( 'wp_easycart_product_details_before', $product );
			if ( function_exists( 'wp_easycart_meta_view_content' ) ) {
				wp_easycart_meta_view_content( $product );
			}
			if ( ! isset( $GLOBALS['wpeasycart_ga4_view_item_printed'] ) || ! is_array( $GLOBALS['wpeasycart_ga4_view_item_printed'] ) ) {
				$GLOBALS['wpeasycart_ga4_view_item_printed'] = array();
			}
			if ( isset( $GLOBALS['wpeasycart_ga4_view_item_printed'][ $id ] ) || '' === (string) get_option( 'ec_option_google_ga4_property_id' ) || ! isset( $GLOBALS['currency'] ) ) {
				return;
			}
			$GLOBALS['wpeasycart_ga4_view_item_printed'][ $id ] = true;
			$price = (float) number_format( (float) $product->price, 2, '.', '' );
			$event = array(
				'currency' => wp_easycart_base_currency_code(),
				'value'    => $price,
				'items'    => array(
					array(
						'item_id'    => (string) $product->model_number,
						'item_name'  => self::title( $product ),
						'index'      => 0,
						'price'      => $price,
						'item_brand' => trim( html_entity_decode( wp_strip_all_tags( (string) $product->manufacturer_name ), ENT_QUOTES, 'UTF-8' ) ),
						'quantity'   => 1,
					),
				),
			);
			$json  = wp_json_encode( $event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG );
			if ( get_option( 'ec_option_google_ga4_tag_manager' ) ) {
				$js = 'window.dataLayer = window.dataLayer || []; dataLayer.push( { ecommerce: null } ); dataLayer.push( { event: "view_item", ecommerce: ' . $json . ' } );';
			} else {
				$js = 'if ( "function" === typeof gtag ) { gtag( "event", "view_item", ' . $json . ' ); }';
			}
			echo '<script>document.addEventListener( "DOMContentLoaded", function() { ' . $js . ' } );</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed script around JSON encoded with JSON_HEX_TAG.
		}
	}

endif;
