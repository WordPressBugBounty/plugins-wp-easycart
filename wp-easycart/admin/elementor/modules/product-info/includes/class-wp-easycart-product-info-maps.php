<?php
/**
 * Retired product widgets and their settings maps ( module product-info, 6.0.2 ).
 *
 * Each older widget is mapped to its replacement through the filter wp_easycart_elementor_retired_widgets. The foundation
 * hides an older widget from the panel once its replacement is registered, keeps drawing saved pages with it, and offers
 * "Replace with …", which asks the map for the new widget's settings. A map receives the older widget's saved settings ( only
 * what differs from its defaults, as Elementor saves them ) and returns the new widget's: the product, texts, colours,
 * typography, alignment and spacing, plus the Advanced tab ( margin, padding, visibility, classes, motion ). Maps are pure:
 * no output, no writes.
 *
 * The all-in-one Product Details widget ( wp_easycart_product_details ) is not mapped: it stays in the panel.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Product_Info_Maps' ) ) :

	/**
	 * Settings maps from the older product widgets to the product information widgets.
	 */
	final class WP_EasyCart_Product_Info_Maps {

		/**
		 * Responsive setting suffixes ( Elementor's breakpoints ).
		 */
		const SUFFIXES = '(_(?:mobile_extra|mobile|tablet_extra|tablet|laptop|widescreen))?';

		/**
		 * Filter wp_easycart_elementor_retired_widgets: add this module's older widgets.
		 *
		 * @param array $retired Older widget name => entry.
		 * @return array
		 */
		public static function retire( $retired ) {
			$retired = is_array( $retired ) ? $retired : array();
			$table   = array(
				'wp_easycart_product_details_title'        => array( 'wp_easycart_product_title', __( 'Product Title', 'wp-easycart' ), 'title' ),
				'wp_easycart_product_details_breadcrumbs'  => array( 'wp_easycart_product_breadcrumbs', __( 'Breadcrumbs', 'wp-easycart' ), 'breadcrumbs' ),
				'wp_easycart_product_details_tabs'         => array( 'wp_easycart_product_tabs', __( 'Product Tabs', 'wp-easycart' ), 'tabs' ),
				'wp_easycart_product_details_description'  => array( 'wp_easycart_product_description', __( 'Product Description', 'wp-easycart' ), 'description' ),
				'wp_easycart_product_details_specifications' => array( 'wp_easycart_product_specifications', __( 'Product Specifications', 'wp-easycart' ), 'specifications' ),
				'wp_easycart_product_details_customer_reviews' => array( 'wp_easycart_product_reviews', __( 'Product Reviews', 'wp-easycart' ), 'reviews' ),
				'wp_easycart_product_details_rating'       => array( 'wp_easycart_product_rating', __( 'Product Rating', 'wp-easycart' ), 'rating' ),
				'wp_easycart_product_details_short_description' => array( 'wp_easycart_product_short_description', __( 'Product Short Description', 'wp-easycart' ), 'short_description' ),
				'wp_easycart_product_details_social'       => array( 'wp_easycart_product_share', __( 'Share Buttons', 'wp-easycart' ), 'social' ),
				'wp_easycart_product_details_category'     => array( 'wp_easycart_product_meta', __( 'Product Meta', 'wp-easycart' ), 'category' ),
				'wp_easycart_product_details_manufacturer' => array( 'wp_easycart_product_meta', __( 'Product Meta', 'wp-easycart' ), 'manufacturer' ),
				'wp_easycart_product_details_meta'         => array( 'wp_easycart_product_meta', __( 'Product Meta', 'wp-easycart' ), 'meta' ),
				'wp_easycart_product_details_featured_products' => array( 'wp_easycart_related_products', __( 'Related Products', 'wp-easycart' ), 'featured_products' ),
			);
			foreach ( $table as $legacy => $entry ) {
				$retired[ $legacy ] = array(
					'replacement' => $entry[0],
					'title'       => $entry[1],
					'map'         => array( __CLASS__, $entry[2] ),
				);
			}
			return $retired;
		}

		/**
		 * What every map starts from: the product ( "Use in Template" → this page's product, a picked product → that product )
		 * and the Advanced tab.
		 *
		 * @param array $old Older settings.
		 * @return array
		 */
		private static function base( $old ) {
			$out = array();
			if ( ! is_array( $old ) ) {
				return $out;
			}
			$picked = isset( $old['product_id'] ) ? $old['product_id'] : 0;
			if ( is_array( $picked ) ) {
				$picked = reset( $picked );
			}
			$picked = (int) $picked;
			if ( ( ! isset( $old['use_post_id'] ) || 'yes' !== $old['use_post_id'] ) && $picked > 0 ) {
				$out['ec_product_source'] = 'pick';
				$out['ec_product_id']     = (string) $picked;
			}
			foreach ( $old as $key => $value ) {
				if ( self::is_common( $key ) ) {
					$out[ $key ] = $value;
				}
			}
			foreach ( array( '__globals__', '__dynamic__' ) as $bag ) {
				if ( isset( $old[ $bag ] ) && is_array( $old[ $bag ] ) ) {
					foreach ( $old[ $bag ] as $key => $value ) {
						if ( self::is_common( $key ) ) {
							$out[ $bag ][ $key ] = $value;
						}
					}
				}
			}
			return $out;
		}

		/**
		 * An Advanced tab setting every widget shares ( _margin, _padding, _css_classes, hide_mobile, motion_fx_*, … ).
		 *
		 * @param mixed $key Setting key.
		 * @return bool
		 */
		private static function is_common( $key ) {
			return is_string( $key ) && '_id' !== $key && (bool) preg_match( '/^(_(?!_)|hide_|motion_fx_|sticky|custom_css|custom_attributes)/', $key );
		}

		/**
		 * Copy settings under new IDs, with their responsive values, global colours / fonts and dynamic tags.
		 *
		 * @param array $old   Older settings.
		 * @param array $pairs Older ID => new ID.
		 * @param array $out   New settings ( changed in place ).
		 */
		private static function copy( $old, $pairs, &$out ) {
			foreach ( $pairs as $from => $to ) {
				$pattern = '/^' . preg_quote( $from, '/' ) . self::SUFFIXES . '$/';
				foreach ( $old as $key => $value ) {
					if ( is_string( $key ) && preg_match( $pattern, $key, $match ) ) {
						$out[ $to . ( isset( $match[1] ) ? $match[1] : '' ) ] = $value;
					}
				}
				foreach ( array( '__globals__', '__dynamic__' ) as $bag ) {
					if ( ! isset( $old[ $bag ] ) || ! is_array( $old[ $bag ] ) ) {
						continue;
					}
					foreach ( $old[ $bag ] as $key => $value ) {
						if ( is_string( $key ) && preg_match( $pattern, $key, $match ) ) {
							$out[ $bag ][ $to . ( isset( $match[1] ) ? $match[1] : '' ) ] = $value;
						}
					}
				}
			}
		}

		/**
		 * Copy a group control ( typography, border, background ) under a new name: every `<from>_*` key becomes `<to>_*`.
		 *
		 * @param array  $old  Older settings.
		 * @param string $from Older group name.
		 * @param string $to   New group name.
		 * @param array  $out  New settings ( changed in place ).
		 */
		private static function group( $old, $from, $to, &$out ) {
			$prefix = $from . '_';
			foreach ( $old as $key => $value ) {
				if ( is_string( $key ) && 0 === strpos( $key, $prefix ) ) {
					$out[ $to . '_' . substr( $key, strlen( $prefix ) ) ] = $value;
				}
			}
			foreach ( array( '__globals__', '__dynamic__' ) as $bag ) {
				if ( ! isset( $old[ $bag ] ) || ! is_array( $old[ $bag ] ) ) {
					continue;
				}
				foreach ( $old[ $bag ] as $key => $value ) {
					if ( is_string( $key ) && 0 === strpos( $key, $prefix ) ) {
						$out[ $bag ][ $to . '_' . substr( $key, strlen( $prefix ) ) ] = $value;
					}
				}
			}
		}

		/**
		 * The older widget's inner padding and margin become the widget's own ( Advanced tab ), unless it had those already.
		 *
		 * @param array  $old    Older settings.
		 * @param string $prefix Older control prefix ( description, ec_specw, … ).
		 * @param array  $out    New settings ( changed in place ).
		 */
		private static function spacing( $old, $prefix, &$out ) {
			foreach ( array( 'padding', 'margin' ) as $side ) {
				$pattern = '/^' . preg_quote( $prefix . '_' . $side, '/' ) . self::SUFFIXES . '$/';
				foreach ( $old as $key => $value ) {
					if ( is_string( $key ) && preg_match( $pattern, $key, $match ) ) {
						$target = '_' . $side . ( isset( $match[1] ) ? $match[1] : '' );
						if ( ! isset( $out[ $target ] ) ) {
							$out[ $target ] = $value;
						}
					}
				}
			}
		}

		/**
		 * A switcher's value as the older widget drew it: missing is its default.
		 *
		 * @param array  $old     Older settings.
		 * @param string $key     Control ID.
		 * @param bool   $fallback On when missing.
		 * @return bool
		 */
		private static function was_on( $old, $key, $fallback ) {
			if ( ! array_key_exists( $key, $old ) ) {
				return $fallback;
			}
			$value = $old[ $key ];
			return ( 'yes' === $value || true === $value || 1 === $value || '1' === $value );
		}

		/**
		 * Stable repeater item id for mapped rows.
		 *
		 * @param string $seed Seed.
		 * @return string
		 */
		private static function item_id( $seed ) {
			return substr( md5( 'wpec-pi-' . $seed ), 0, 7 );
		}

		/**
		 * Product Title.
		 *
		 * @param array $old Older settings.
		 * @return array
		 */
		public static function title( $old ) {
			$old = is_array( $old ) ? $old : array();
			$out = self::base( $old );
			self::copy(
				$old,
				array(
					'title_element' => 'title_tag',
					'title_color'   => 'title_color',
					'title_align'   => 'align',
				),
				$out
			);
			self::group( $old, 'title_font', 'title_typography', $out );
			return $out;
		}

		/**
		 * Breadcrumbs ( the older widget followed the store's menus; the new one follows the product's categories ).
		 *
		 * @param array $old Older settings.
		 * @return array
		 */
		public static function breadcrumbs( $old ) {
			$old = is_array( $old ) ? $old : array();
			$out = self::base( $old );
			self::copy(
				$old,
				array(
					'divider_character' => 'separator',
					'breadcrumb_align'  => 'align',
					'link_color'        => 'link_color',
					'link_hover_color'  => 'link_hover_color',
					'item_color'        => 'current_color',
					'divider_color'     => 'separator_color',
					'divider_spacing'   => 'gap',
				),
				$out
			);
			self::group( $old, 'link_font', 'link_typography', $out );
			self::group( $old, 'item_font', 'current_typography', $out );
			if ( ! isset( $out['separator'] ) ) {
				$out['separator'] = '/';
			}
			return $out;
		}

		/**
		 * Product Tabs: the three tabs the older widget showed. Its switches defaulted to 1, so an untouched widget drew an empty
		 * list while the panel showed every tab on; the new widget shows what the panel showed.
		 *
		 * @param array $old Older settings.
		 * @return array
		 */
		public static function tabs( $old ) {
			$old  = is_array( $old ) ? $old : array();
			$out  = self::base( $old );
			$rows = array();
			foreach ( array(
				'description'      => 'description',
				'specifications'   => 'specifications',
				'customer_reviews' => 'reviews',
			) as $control => $type ) {
				$rows[] = array(
					'_id'      => self::item_id( 'tabs-' . $type ),
					'tab_type' => $type,
					'tab_show' => self::was_on( $old, $control, true ) ? 'yes' : '',
				);
			}
			$out['tabs'] = $rows;
			return $out;
		}

		/**
		 * Product Description.
		 *
		 * @param array $old Older settings.
		 * @return array
		 */
		public static function description( $old ) {
			$old = is_array( $old ) ? $old : array();
			$out = self::base( $old );
			self::copy(
				$old,
				array(
					'description_color' => 'text_color',
					'description_align' => 'align',
				),
				$out
			);
			self::group( $old, 'description_font', 'text_typography', $out );
			self::spacing( $old, 'description', $out );
			return $out;
		}

		/**
		 * Product Specifications ( now shown only where the product's Specifications switch is on ).
		 *
		 * @param array $old Older settings.
		 * @return array
		 */
		public static function specifications( $old ) {
			$old = is_array( $old ) ? $old : array();
			$out = self::base( $old );
			self::copy(
				$old,
				array(
					'ec_specw_color' => 'text_color',
					'ec_specw_align' => 'align',
				),
				$out
			);
			self::group( $old, 'ec_specw_font', 'text_typography', $out );
			self::spacing( $old, 'ec_specw', $out );
			return $out;
		}

		/**
		 * Product Short Description.
		 *
		 * @param array $old Older settings.
		 * @return array
		 */
		public static function short_description( $old ) {
			$old = is_array( $old ) ? $old : array();
			$out = self::base( $old );
			self::copy(
				$old,
				array(
					'ec_sdw_label_color' => 'text_color',
					'ec_sdw_align'       => 'align',
				),
				$out
			);
			self::group( $old, 'ec_sdw_label_font', 'text_typography', $out );
			self::spacing( $old, 'ec_sdw', $out );
			return $out;
		}

		/**
		 * Product Rating ( the older widget was centred unless the merchant chose otherwise ).
		 *
		 * @param array $old Older settings.
		 * @return array
		 */
		public static function rating( $old ) {
			$old = is_array( $old ) ? $old : array();
			$out = self::base( $old );
			self::copy(
				$old,
				array(
					'ec_rw_color'          => 'star_color',
					'ec_rw_color_inactive' => 'star_empty_color',
					'ec_rw_align'          => 'align',
					'ec_rw_spacing'        => 'star_gap',
				),
				$out
			);
			if ( ! isset( $out['align'] ) ) {
				$out['align'] = 'center';
			}
			self::spacing( $old, 'ec_rw', $out );
			return $out;
		}

		/**
		 * Product Reviews: the older widget's parts and colours ( its switches defaulted to on, the reviewer name to off ). The
		 * rating summary, verified badges and store replies come with the new widget.
		 *
		 * @param array $old Older settings.
		 * @return array
		 */
		public static function reviews( $old ) {
			$old      = is_array( $old ) ? $old : array();
			$out      = self::base( $old );
			$switches = array(
				'enable_review_list'        => 'show_list',
				'enable_review_list_title'  => 'show_list_heading',
				'enable_review_item_title'  => 'show_review_title',
				'enable_review_item_date'   => 'show_date',
				'enable_review_item_rating' => 'show_rating',
				'enable_review_item_review' => 'show_text',
				'enable_review_form'        => 'show_form',
				'enable_review_form_title'  => 'show_form_title',
			);
			foreach ( $switches as $from => $to ) {
				if ( ! self::was_on( $old, $from, true ) ) {
					$out[ $to ] = '';
				}
			}
			$out['show_name'] = self::was_on( $old, 'enable_review_item_user_name', false ) ? 'show' : 'hide';
			if ( isset( $old['form_button_text'] ) && is_string( $old['form_button_text'] ) && '' !== trim( $old['form_button_text'] ) ) {
				$out['button_text'] = $old['form_button_text'];
			}
			if ( isset( $old['customer_reviews_columns'] ) && in_array( $old['customer_reviews_columns'], array( 'column', 'column-reverse' ), true ) ) {
				$out['layout'] = 'stacked';
			}
			self::copy(
				$old,
				array(
					'list_main_title_color'              => 'list_heading_color',
					'list_item_title_color'              => 'review_title_color',
					'list_item_review_color'             => 'review_text_color',
					'list_item_date_color'               => 'review_meta_color',
					'list_item_rating_color'             => 'star_color',
					'list_item_rating_color_inactive'    => 'star_empty_color',
					'list_item_background_color'         => 'review_background',
					'list_item_padding'                  => 'review_padding',
					'form_background_color'              => 'form_background',
					'form_padding'                       => 'form_padding',
					'form_title_color'                   => 'form_title_color',
					'form_label_color'                   => 'form_label_color',
					'form_button_text_color'             => 'button_color',
					'form_button_text_color_hover'       => 'button_hover_color',
					'form_button_background_color'       => 'button_background',
					'form_button_background_hover_color' => 'button_hover_background',
					'button_padding'                     => 'button_padding',
				),
				$out
			);
			self::group( $old, 'list_main_title_font', 'list_heading_typography', $out );
			self::group( $old, 'list_item_title_font', 'review_title_typography', $out );
			self::group( $old, 'list_item_review_font', 'review_text_typography', $out );
			self::group( $old, 'list_item_date_font', 'review_meta_typography', $out );
			self::group( $old, 'form_title_font', 'form_title_typography', $out );
			self::group( $old, 'form_label_font', 'form_label_typography', $out );
			self::group( $old, 'form_button_font', 'button_typography', $out );
			return $out;
		}

		/**
		 * Share Buttons from the older Social Icons list: each row whose link goes to a network the new widget knows, with its
		 * title and icon; one colour for all when every row had the same.
		 *
		 * @param array $old Older settings.
		 * @return array
		 */
		public static function social( $old ) {
			$old = is_array( $old ) ? $old : array();
			$out = self::base( $old );
			if ( isset( $old['social_list'] ) && is_array( $old['social_list'] ) ) {
				$rows   = array();
				$colors = array();
				$hovers = array();
				foreach ( $old['social_list'] as $index => $item ) {
					if ( ! is_array( $item ) ) {
						continue;
					}
					$link    = isset( $item['social_link'] ) ? strtolower( (string) $item['social_link'] ) : '';
					$network = '';
					if ( false !== strpos( $link, 'facebook.' ) ) {
						$network = 'facebook';
					} elseif ( false !== strpos( $link, 'twitter.' ) || false !== strpos( $link, 'x.com' ) ) {
						$network = 'x';
					} elseif ( false !== strpos( $link, 'pinterest.' ) ) {
						$network = 'pinterest';
					} elseif ( false !== strpos( $link, 'linkedin.' ) ) {
						$network = 'linkedin';
					} elseif ( 0 === strpos( $link, 'mailto:' ) ) {
						$network = 'email';
					}
					if ( '' === $network ) {
						continue;
					}
					$row = array(
						'_id'     => isset( $item['_id'] ) ? (string) $item['_id'] : self::item_id( 'share-' . $index ),
						'network' => $network,
					);
					if ( isset( $item['social_icon'] ) && is_array( $item['social_icon'] ) && ! empty( $item['social_icon']['value'] ) ) {
						$row['icon'] = $item['social_icon'];
					}
					$rows[]   = $row;
					$colors[] = isset( $item['social_color'] ) ? (string) $item['social_color'] : '#aaaaaa';
					$hovers[] = isset( $item['social_color_hover'] ) ? (string) $item['social_color_hover'] : '';
				}
				if ( $rows ) {
					$out['networks'] = $rows;
				}
				$out['shape'] = 'plain';
				if ( $colors && 1 === count( array_unique( $colors ) ) && '' !== $colors[0] ) {
					$out['colors']     = 'custom';
					$out['icon_color'] = $colors[0];
					if ( 1 === count( array_unique( $hovers ) ) && '' !== $hovers[0] ) {
						$out['icon_hover_color'] = $hovers[0];
					}
				}
			} else {
				$out['shape'] = 'plain';
			}
			self::copy(
				$old,
				array(
					'ec_siw_align'        => 'align',
					'ec_siw_icon_size'    => 'icon_size',
					'ec_siw_icon_spacing' => 'gap',
				),
				$out
			);
			self::spacing( $old, 'ec_siw', $out );
			return $out;
		}

		/**
		 * Product Meta with only the categories, from the older Product Categories widget.
		 *
		 * @param array $old Older settings.
		 * @return array
		 */
		public static function category( $old ) {
			$old                    = is_array( $old ) ? $old : array();
			$out                    = self::base( $old );
			$out['show_sku']        = '';
			$out['show_brand']      = '';
			$out['show_categories'] = 'yes';
			$out['show_tag']        = '';
			$out['layout']          = 'inline';
			self::copy(
				$old,
				array(
					'categories_label'   => 'categories_label',
					'categories_divider' => 'list_separator',
					'categories_align'   => 'align',
					'label_color'        => 'label_color',
					'link_color'         => 'link_color',
					'link_hover_color'   => 'link_hover_color',
				),
				$out
			);
			if ( isset( $out['list_separator'] ) && is_string( $out['list_separator'] ) && ',' === trim( $out['list_separator'] ) ) {
				$out['list_separator'] = ', ';
			}
			self::group( $old, 'label_font', 'label_typography', $out );
			self::group( $old, 'link_font', 'value_typography', $out );
			return $out;
		}

		/**
		 * Product Meta with only the brand, from the older Product Manufacturer widget ( its "Manufacturer:" label kept ).
		 *
		 * @param array $old Older settings.
		 * @return array
		 */
		public static function manufacturer( $old ) {
			$old                    = is_array( $old ) ? $old : array();
			$out                    = self::base( $old );
			$out['show_sku']        = '';
			$out['show_brand']      = 'yes';
			$out['show_categories'] = '';
			$out['show_tag']        = '';
			$out['layout']          = 'inline';
			self::copy(
				$old,
				array(
					'label_text'         => 'brand_label',
					'ec_mw_align'        => 'align',
					'label_color'        => 'label_color',
					'manufacturer_color' => 'link_color',
				),
				$out
			);
			if ( ! isset( $out['brand_label'] ) && class_exists( 'WP_EasyCart_Product_Info' ) ) {
				$out['brand_label'] = WP_EasyCart_Product_Info::text( 'product_details_manufacturer', __( 'Manufacturer:', 'wp-easycart' ), 'product_details' );
			}
			self::group( $old, 'label_font', 'label_typography', $out );
			self::group( $old, 'manufacturer_font', 'value_typography', $out );
			self::spacing( $old, 'ec_mw', $out );
			return $out;
		}

		/**
		 * Product Meta from the older Product Meta widget, which showed nothing ( it sent the product's view events and search
		 * data ): every detail off, so the page looks the same and the events still go out.
		 *
		 * @param array $old Older settings.
		 * @return array
		 */
		public static function meta( $old ) {
			$old                    = is_array( $old ) ? $old : array();
			$out                    = self::base( $old );
			$out['show_sku']        = '';
			$out['show_brand']      = '';
			$out['show_categories'] = '';
			$out['show_tag']        = '';
			return $out;
		}

		/**
		 * Related Products from the older Featured Items widget: the product's own featured products, as many as it showed, with
		 * the same card parts and no heading.
		 *
		 * @param array $old Older settings.
		 * @return array
		 */
		public static function featured_products( $old ) {
			$old   = is_array( $old ) ? $old : array();
			$out   = self::base( $old );
			$count = 0;
			for ( $slot = 1; $slot <= 4; $slot++ ) {
				if ( self::was_on( $old, 'enable_product' . $slot, true ) ) {
					++$count;
				}
			}
			$out['source']       = 'featured';
			$out['count']        = max( 1, $count );
			$out['show_heading'] = '';
			$parts               = ( isset( $old['visible_options'] ) && is_array( $old['visible_options'] ) ) ? $old['visible_options'] : array( 'title', 'category', 'price', 'rating', 'cart', 'quickview', 'desc' );
			$out['card_parts']   = array_values( array_intersect( $parts, array( 'title', 'category', 'price', 'rating', 'cart', 'quickview', 'desc' ) ) );
			return $out;
		}
	}

endif;
