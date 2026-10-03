<?php
/**
 * Retires the Store, Products and Search widgets from before 6.0.2 ( 6.0.2 ).
 *
 * Replacements: wp_easycart_store → wp_easycart_shop, wp_easycart_product → wp_easycart_products ( wp_easycart_product_carousel
 * when its layout is the slider ), wp_easycart_search → wp_easycart_product_search. The older widgets keep drawing saved pages; the
 * editor offers "Replace with …", which asks these maps for the new widget's settings.
 *
 * The maps are pure ( no output, no writes ). They receive only the settings a merchant changed ( the editor never saves a
 * control's default ), so a missing key means that control's default in the older widget. What has an equivalent is
 * carried over: products, categories and brands, texts, columns and spacing, the card's visible parts, colours,
 * typography, borders and shadows ( including links to the kit's global colours and fonts ); the rest is dropped.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Shop_Retire' ) ) :

	/**
	 * Settings maps for the retired listing widgets.
	 */
	class WP_EasyCart_Elementor_Shop_Retire {

		/**
		 * Suffixes Elementor stores responsive values under.
		 *
		 * @var array
		 */
		private static $devices = array( '', '_widescreen', '_laptop', '_tablet_extra', '_tablet', '_mobile_extra', '_mobile' );

		/**
		 * Registers the three replacements ( filter wp_easycart_elementor_retired_widgets ).
		 *
		 * @param array $retired Legacy name => entry.
		 * @return array
		 */
		public static function register( $retired ) {
			if ( ! is_array( $retired ) ) {
				$retired = array();
			}
			$retired['wp_easycart_store']   = array(
				'replacement' => 'wp_easycart_shop',
				'title'       => __( 'Shop', 'wp-easycart' ),
				'map'         => array( __CLASS__, 'map_store' ),
			);
			$retired['wp_easycart_product'] = array(
				'replacement' => 'wp_easycart_products',
				'title'       => __( 'Products', 'wp-easycart' ),
				'map'         => array( __CLASS__, 'map_product' ),
			);
			$retired['wp_easycart_search']  = array(
				'replacement' => 'wp_easycart_product_search',
				'title'       => __( 'Product Search', 'wp-easycart' ),
				'map'         => array( __CLASS__, 'map_search' ),
			);
			return $retired;
		}

		/**
		 * Store → Shop.
		 *
		 * @param array $old Saved settings of wp_easycart_store.
		 * @return array
		 */
		public static function map_store( $old ) {
			$old = is_array( $old ) ? $old : array();
			$out = array();

			/* What the list shows. The older widget's untouched default was "featured" ( now the first view only, as the classic store ). */
			$status           = isset( $old['status'] ) ? (string) $old['status'] : 'featured';
			$out['ec_status'] = ( 'all' === $status ) ? '' : ( in_array( $status, array( 'featured', 'on_sale', 'in_stock' ), true ) ? $status : '' );
			$category         = self::first_id( isset( $old['category'] ) ? $old['category'] : '' );
			$brand            = self::first_id( isset( $old['brands'] ) ? $old['brands'] : '' );
			if ( isset( $old['use_dynamic'] ) && 'yes' === $old['use_dynamic'] ) {
				$out['ec_shop_source'] = 'auto';
			} elseif ( $category ) {
				$out['ec_shop_source']   = 'category';
				$out['ec_shop_category'] = (string) $category;
			} elseif ( $brand ) {
				$out['ec_shop_source']       = 'manufacturer';
				$out['ec_shop_manufacturer'] = (string) $brand;
			} else {
				$out['ec_shop_source'] = 'all';
			}

			self::map_columns( $old, $out, '4' );
			self::map_spacing( $old, $out, true );
			self::map_card( $old, $out );

			/* Toolbar and filters: shown only where the older widget showed them. Page numbers always ( the older default listed every product at once ). */
			$out['ec_show_sorting']    = ( isset( $old['sorting'] ) && 'yes' === $old['sorting'] ) ? 'yes' : '';
			$out['ec_show_pagination'] = 'yes';
			if ( isset( $old['sorting_default'] ) && '' !== (string) $old['sorting_default'] && is_numeric( $old['sorting_default'] ) ) {
				$out['ec_default_sort'] = (string) (int) $old['sorting_default'];
			}
			$sidebar                = isset( $old['sidebar'] ) && 'yes' === $old['sidebar'];
			$out['ec_show_filters'] = $sidebar ? 'yes' : '';
			if ( $sidebar ) {
				$position                       = isset( $old['sidebar_position'] ) ? (string) $old['sidebar_position'] : 'left';
				$out['ec_filters_position']     = ( false !== strpos( $position, 'right' ) ) ? 'right' : 'left';
				$out['ec_filter_search']        = self::switched( $old, 'sidebar_include_search', 'yes' );
				$out['ec_filter_categories']    = self::switched( $old, 'sidebar_include_categories', 'yes' );
				$out['ec_filter_manufacturers'] = self::switched( $old, 'sidebar_include_manufacturers', 'no' );
				$out['ec_filter_price']         = self::switched( $old, 'sidebar_include_pricepoints', 'no' );
				$categories                     = self::id_list( isset( $old['sidebar_categories'] ) ? $old['sidebar_categories'] : '' );
				if ( ! empty( $categories ) ) {
					$out['ec_filter_category_ids'] = $categories;
				}
				if ( 'yes' === self::switched( $old, 'sidebar_include_option_filters', 'yes' ) ) {
					$options = self::id_list( isset( $old['sidebar_option_filters'] ) ? $old['sidebar_option_filters'] : '' );
					if ( ! empty( $options ) ) {
						$out['ec_filter_option_ids'] = $options;
					}
				}
			}

			self::move(
				$old,
				$out,
				array(
					'product_bg_color'                => 'card_background',
					'product_container_border_radius' => 'card_radius',
					'product_container_padding'       => 'card_padding',
					'image_border_radius'             => 'image_radius',
					'title_color'                     => 'title_color',
					'title_hover_color'               => 'title_hover_color',
					'price_color'                     => 'price_color',
					'sale_price_color'                => 'sale_price_color',
					'cart_button_color'               => 'button_color',
					'cart_button_bg_color'            => 'button_background',
					'cart_button_hover_color'         => 'button_hover_color',
					'cart_button_hover_bg_color'      => 'button_hover_background',
					'cart_button_border_radius'       => 'button_radius',
					'cart_button_padding'             => 'button_padding',
					'rating_star_color'               => 'star_color',
					'rating_star_empty_color'         => 'star_empty_color',
					'quickview_color'                 => 'quick_view_color',
					'quickview_bg_color'              => 'quick_view_background',
					'paging_text_color'               => 'toolbar_color',
					'paging_button_color'             => 'pagination_color',
					'paging_active_color'             => 'pagination_active_color',
					'paging_active_bg_color'          => 'pagination_active_background',
					'sidebar_bg_color'                => 'filters_background',
					'sidebar_title_color'             => 'filters_title_color',
					'sidebar_link_color'              => 'filters_link_color',
					'sidebar_link_hover_color'        => 'filters_active_color',
					'sidebar_padding'                 => 'filters_padding',
				)
			);
			self::move_groups(
				$old,
				$out,
				array(
					'product_container_border' => 'card_border',
					'product_container_shadow' => 'card_shadow',
					'title_typography'         => 'title_typography',
					'price_typography'         => 'price_typography',
					'cart_button_typography'   => 'button_typography',
					'paging_button_typography' => 'pagination_typography',
					'paging_sort_typography'   => 'toolbar_typography',
				)
			);
			return $out;
		}

		/**
		 * Products → Products ( or Product Carousel for the slider layout ).
		 *
		 * @param array $old Saved settings of wp_easycart_product.
		 * @return array
		 */
		public static function map_product( $old ) {
			$old    = is_array( $old ) ? $old : array();
			$out    = array();
			$slider = isset( $old['layout_mode'] ) && 'slider' === $old['layout_mode'];
			if ( $slider ) {
				/* The foundation inserts this widget instead of the entry's default replacement ( and strips the key ). */
				$out['_wpec_replacement'] = 'wp_easycart_product_carousel';
			}

			/* Heading. */
			if ( isset( $old['title'] ) && '' !== trim( (string) $old['title'] ) ) {
				$out['ec_heading'] = (string) $old['title'];
				if ( ! empty( $old['title_link']['url'] ) ) {
					$out['ec_heading_link'] = $old['title_link'];
				}
				if ( isset( $old['desc'] ) && '' !== trim( (string) $old['desc'] ) ) {
					$out['ec_heading_desc'] = (string) $old['desc'];
				}
				self::move( $old, $out, array( 'title_align' => 'heading_align' ) );
			}

			/* Source: chosen products, else categories, else brands, else the status. */
			$status   = isset( $old['status'] ) ? (string) $old['status'] : '';
			$products = self::id_list( isset( $old['ids'] ) ? $old['ids'] : '' );
			$cats     = self::id_list( isset( $old['category'] ) ? $old['category'] : '' );
			$brands   = self::id_list( isset( $old['brands'] ) ? $old['brands'] : '' );
			if ( ! empty( $products ) ) {
				$out['ec_source']      = 'pick';
				$out['ec_product_ids'] = $products;
			} elseif ( ! empty( $cats ) ) {
				$out['ec_source']       = 'category';
				$out['ec_category_ids'] = $cats;
			} elseif ( ! empty( $brands ) ) {
				$out['ec_source']           = 'manufacturer';
				$out['ec_manufacturer_ids'] = $brands;
			} elseif ( in_array( $status, array( 'featured', 'on_sale' ), true ) ) {
				$out['ec_source'] = $status;
			} else {
				$out['ec_source'] = 'all';
			}
			if ( 'in_stock' === $status && 'pick' !== $out['ec_source'] ) {
				$out['ec_in_stock'] = 'yes';
			}

			/* Order ( the older widget sorted ascending unless told otherwise; with nothing chosen and no order, newest first ). */
			$orderby = isset( $old['orderby'] ) ? (string) $old['orderby'] : '';
			$desc    = isset( $old['order'] ) && 'DESC' === $old['order'];
			$orders  = array(
				'title'            => $desc ? 'title_desc' : 'title',
				'price'            => $desc ? 'price_desc' : 'price',
				'product_id'       => $desc ? 'newest' : 'oldest',
				'added_to_db_date' => $desc ? 'newest' : 'oldest',
				'rand'             => 'random',
				'views'            => 'popular',
				'rating'           => 'rating',
			);
			if ( isset( $orders[ $orderby ] ) ) {
				$out['ec_orderby'] = $orders[ $orderby ];
			} elseif ( 'all' === $out['ec_source'] || in_array( $out['ec_source'], array( 'featured', 'on_sale' ), true ) ) {
				$out['ec_orderby'] = 'newest';
			}

			$count           = ( isset( $old['count'] ) && is_array( $old['count'] ) && isset( $old['count']['size'] ) ) ? (int) $old['count']['size'] : 4;
			$out['ec_limit'] = max( 1, min( WP_EasyCart_Elementor_Shop_Query::MAX_PER_PAGE, $count ) );

			self::map_columns( $old, $out, '4' );
			self::map_spacing( $old, $out, ! $slider );
			self::map_card( $old, $out );

			if ( $slider ) {
				/* The older slider's arrows and dots were off on each device unless switched on there. */
				foreach ( array(
					''        => '',
					'_tablet' => '_tablet',
					'_mobile' => '_mobile',
				) as $from => $to ) {
					$out[ 'ec_arrows' . $to ] = ( isset( $old[ 'slider_nav' . $from ] ) && 'yes' === $old[ 'slider_nav' . $from ] ) ? 'show' : 'hide';
					$out[ 'ec_dots' . $to ]   = ( isset( $old[ 'slider_dot' . $from ] ) && 'yes' === $old[ 'slider_dot' . $from ] ) ? 'show' : 'hide';
				}
				if ( isset( $old['slider_loop'] ) && 'yes' === $old['slider_loop'] ) {
					$out['ec_loop'] = 'yes';
				}
				if ( isset( $old['slider_auto_play'] ) && 'yes' === $old['slider_auto_play'] ) {
					$out['ec_autoplay']       = 'yes';
					$time                     = isset( $old['slider_auto_play_time'] ) ? (int) $old['slider_auto_play_time'] : 10000;
					$out['ec_autoplay_speed'] = max( 2, min( 30, (int) round( $time / 1000 ) ) );
				}
			}
			return $out;
		}

		/**
		 * Search → Product Search.
		 *
		 * @param array $old Saved settings of wp_easycart_search.
		 * @return array
		 */
		public static function map_search( $old ) {
			$old = is_array( $old ) ? $old : array();
			$out = array();
			if ( isset( $old['label'] ) && '' !== trim( (string) $old['label'] ) && 'Search' !== $old['label'] ) {
				$out['ec_button_text'] = (string) $old['label'];
			}
			if ( isset( $old['placeholder_text'] ) && '' !== trim( (string) $old['placeholder_text'] ) && 'Search products...' !== $old['placeholder_text'] ) {
				$out['ec_placeholder'] = (string) $old['placeholder_text'];
			}
			if ( ! empty( $old['postid'] ) && (int) get_option( 'ec_option_storepage' ) !== (int) $old['postid'] ) {
				$url = get_permalink( (int) $old['postid'] );
				if ( is_string( $url ) && '' !== $url ) {
					$out['ec_results_page'] = 'url';
					$out['ec_results_url']  = array(
						'url'         => $url,
						'is_external' => '',
						'nofollow'    => '',
					);
				}
			}
			$live = isset( $old['enable_live_search'] ) ? (string) $old['enable_live_search'] : 'global';
			if ( 'enable' === $live ) {
				$out['ec_suggestions'] = 'yes';
			} elseif ( 'disable' === $live ) {
				$out['ec_suggestions'] = 'no';
			}
			if ( isset( $old['live_search_max_results'] ) && (int) $old['live_search_max_results'] > 0 ) {
				$out['ec_max_suggestions'] = max( 1, min( 10, (int) $old['live_search_max_results'] ) );
			}
			/* The older widget's button showed its text unless an icon was switched on. */
			if ( isset( $old['show_button_icon'] ) && 'yes' === $old['show_button_icon'] ) {
				$out['ec_button_display'] = ( isset( $old['button_icon_position'] ) && 'only' === $old['button_icon_position'] ) ? 'icon' : 'both';
			} else {
				$out['ec_button_display'] = 'text';
			}

			self::move(
				$old,
				$out,
				array(
					'input_text_color'              => 'field_color',
					'input_background_color'        => 'field_background',
					'input_border_radius'           => 'field_radius',
					'input_height'                  => 'field_height',
					'button_text_color'             => 'search_button_color',
					'button_background_color'       => 'search_button_background',
					'button_text_color_hover'       => 'search_button_hover_color',
					'button_background_hover_color' => 'search_button_hover_background',
					'dropdown_background_color'     => 'panel_background',
					'dropdown_hover_bg_color'       => 'panel_active_background',
					'dropdown_text_color'           => 'suggestion_color',
				)
			);
			self::move_groups(
				$old,
				$out,
				array(
					'input_border'        => 'field_border',
					'input_typography'    => 'field_typography',
					'button_typography'   => 'search_button_typography',
					'dropdown_typography' => 'suggestion_typography',
					'dropdown_box_shadow' => 'panel_shadow',
				)
			);
			return $out;
		}

		/**
		 * Columns: desktop from "Columns" ( the older default 4 is kept ), tablet, and phones from "Columns Under Mobile".
		 *
		 * @param array  $old     Older settings.
		 * @param array  $out     New settings ( by reference ).
		 * @param string $desktop The older widget's desktop default.
		 */
		private static function map_columns( $old, &$out, $desktop ) {
			$columns           = ( isset( $old['cols_upper_desktop'] ) && '' !== (string) $old['cols_upper_desktop'] ) ? $old['cols_upper_desktop'] : ( isset( $old['columns'] ) && '' !== (string) $old['columns'] ? $old['columns'] : $desktop );
			$out['ec_columns'] = (string) max( 1, min( 6, (int) $columns ) );
			if ( isset( $old['columns_tablet'] ) && '' !== (string) $old['columns_tablet'] ) {
				$out['ec_columns_tablet'] = (string) max( 1, min( 6, (int) $old['columns_tablet'] ) );
			}
			$mobile = ( isset( $old['cols_under_mobile'] ) && '' !== (string) $old['cols_under_mobile'] ) ? $old['cols_under_mobile'] : ( isset( $old['columns_mobile'] ) ? $old['columns_mobile'] : '' );
			if ( '' !== (string) $mobile ) {
				$out['ec_columns_mobile'] = (string) max( 1, min( 6, (int) $mobile ) );
			}
		}

		/**
		 * Spacing ( a slider in px ) → the gap between products, and between rows for grids.
		 *
		 * @param array $old  Older settings.
		 * @param array $out  New settings ( by reference ).
		 * @param bool  $rows Also the row gap.
		 */
		private static function map_spacing( $old, &$out, $rows ) {
			if ( ! isset( $old['spacing'] ) || ! is_array( $old['spacing'] ) || ! isset( $old['spacing']['size'] ) || '' === (string) $old['spacing']['size'] ) {
				return;
			}
			$gap                  = array(
				'unit'  => 'px',
				'size'  => max( 0, min( 80, (int) $old['spacing']['size'] ) ),
				'sizes' => array(),
			);
			$out['ec_column_gap'] = $gap;
			if ( $rows ) {
				$out['ec_row_gap'] = $gap;
			}
		}

		/**
		 * The card's parts ( "Visible Items" of a Custom type; the Standard type showed name, price, rating, cart, quick
		 * view and short description ), alignment and rounded corners.
		 *
		 * @param array $old Older settings.
		 * @param array $out New settings ( by reference ).
		 */
		private static function map_card( $old, &$out ) {
			$custom  = isset( $old['type'] ) && 'custom' === $old['type'];
			$visible = array( 'title', 'price', 'rating', 'cart', 'quickview', 'desc' );
			if ( $custom ) {
				$visible = ( isset( $old['visible_options'] ) && is_array( $old['visible_options'] ) ) ? $old['visible_options'] : array( 'title', 'category', 'price', 'rating', 'cart', 'quickview', 'desc' );
			}
			$parts = array(
				'title'     => 'ec_show_title',
				'category'  => 'ec_show_category',
				'price'     => 'ec_show_price',
				'rating'    => 'ec_show_rating',
				'cart'      => 'ec_show_button',
				'quickview' => 'ec_show_quick_view',
				'desc'      => 'ec_show_excerpt',
			);
			foreach ( $parts as $part => $control ) {
				$out[ $control ] = in_array( $part, $visible, true ) ? 'yes' : '';
			}
			/* The older widgets centred the card unless told otherwise. */
			$out['ec_align'] = isset( $old['product_align'] ) && in_array( $old['product_align'], array( 'left', 'center', 'right' ), true ) ? $old['product_align'] : 'center';
			foreach ( array( '_tablet', '_mobile' ) as $suffix ) {
				if ( isset( $old[ 'product_align' . $suffix ] ) && in_array( $old[ 'product_align' . $suffix ], array( 'left', 'center', 'right' ), true ) ) {
					$out[ 'ec_align' . $suffix ] = $old[ 'product_align' . $suffix ];
				}
			}
			if ( isset( $old['product_rounded_corners'] ) && 'yes' === $old['product_rounded_corners'] ) {
				$corner             = function ( $key ) use ( $old ) {
					return ( isset( $old[ $key ]['size'] ) && '' !== (string) $old[ $key ]['size'] ) ? (string) (int) $old[ $key ]['size'] : '10';
				};
				$out['card_radius'] = array(
					'unit'     => 'px',
					'top'      => $corner( 'product_rounded_corners_tl' ),
					'right'    => $corner( 'product_rounded_corners_tr' ),
					'bottom'   => $corner( 'product_rounded_corners_br' ),
					'left'     => $corner( 'product_rounded_corners_bl' ),
					'isLinked' => false,
				);
			}
			if ( isset( $old['image_object_fit'] ) && 'contain' === $old['image_object_fit'] ) {
				$out['image_fit'] = 'contain';
			}
		}

		/**
		 * Copies plain controls to new names, on every device, with their links to the kit's globals.
		 *
		 * @param array $old Older settings.
		 * @param array $out New settings ( by reference ).
		 * @param array $map Older id => new id.
		 */
		private static function move( $old, &$out, $map ) {
			foreach ( $map as $from => $to ) {
				foreach ( self::$devices as $suffix ) {
					if ( array_key_exists( $from . $suffix, $old ) && '' !== $old[ $from . $suffix ] && null !== $old[ $from . $suffix ] ) {
						$out[ $to . $suffix ] = $old[ $from . $suffix ];
					}
					self::move_global( $old, $out, $from . $suffix, $to . $suffix );
				}
			}
		}

		/**
		 * Copies group controls ( typography, border, box shadow: every "<name>_<field>" key ) to a new group name.
		 *
		 * @param array $old Older settings.
		 * @param array $out New settings ( by reference ).
		 * @param array $map Older group => new group.
		 */
		private static function move_groups( $old, &$out, $map ) {
			foreach ( $map as $from => $to ) {
				foreach ( $old as $key => $value ) {
					if ( is_string( $key ) && 0 === strpos( $key, $from . '_' ) ) {
						$out[ $to . substr( $key, strlen( $from ) ) ] = $value;
					}
				}
				if ( isset( $old['__globals__'] ) && is_array( $old['__globals__'] ) ) {
					foreach ( $old['__globals__'] as $key => $value ) {
						if ( is_string( $key ) && 0 === strpos( $key, $from . '_' ) ) {
							$out['__globals__'][ $to . substr( $key, strlen( $from ) ) ] = $value;
						}
					}
				}
			}
		}

		/**
		 * Copies a control's link to a kit global ( __globals__ ).
		 *
		 * @param array  $old  Older settings.
		 * @param array  $out  New settings ( by reference ).
		 * @param string $from Older id.
		 * @param string $to   New id.
		 */
		private static function move_global( $old, &$out, $from, $to ) {
			if ( isset( $old['__globals__'][ $from ] ) && '' !== $old['__globals__'][ $from ] ) {
				$out['__globals__'][ $to ] = $old['__globals__'][ $from ];
			}
		}

		/**
		 * A switcher's value ( 'yes' or '' ), with the older control's default when it was never changed.
		 *
		 * @param array  $old     Older settings.
		 * @param string $key     Control id.
		 * @param string $fallback Older default ( 'yes' or 'no' ).
		 * @return string
		 */
		private static function switched( $old, $key, $fallback ) {
			$value = array_key_exists( $key, $old ) ? $old[ $key ] : $fallback;
			return ( 'yes' === $value ) ? 'yes' : '';
		}

		/**
		 * The ids a select2 control stored ( array, or comma list ), as strings in their order.
		 *
		 * @param mixed $value Stored value.
		 * @return array
		 */
		private static function id_list( $value ) {
			return array_map( 'strval', WP_EasyCart_Elementor_Shop_Query::ids( $value ) );
		}

		/**
		 * The first id a select2 control stored, or 0.
		 *
		 * @param mixed $value Stored value.
		 * @return int
		 */
		private static function first_id( $value ) {
			$ids = WP_EasyCart_Elementor_Shop_Query::ids( $value );
			return empty( $ids ) ? 0 : (int) $ids[0];
		}
	}

endif;
