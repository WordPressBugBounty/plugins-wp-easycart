<?php
/**
 * Settings maps from the legacy product widgets to the 6.0.2 product purchase widgets ( wp_easycart_elementor_retired_widgets ).
 *
 * Each map takes a legacy widget's saved settings ( only what differs from its defaults, as Elementor stores them ) and
 * returns the new widget's settings: the product, texts, colours, typography and spacing the merchant chose. Settings with
 * no equivalent are dropped. Maps are pure: no output, no database writes.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Product_Buy_Maps' ) ) :

	/**
	 * Legacy widget settings → product purchase widget settings ( static ).
	 */
	class WP_EasyCart_Product_Buy_Maps {

		/**
		 * The legacy widgets this module replaces, for wp_easycart_elementor_retired_widgets.
		 *
		 * @param array $retired Legacy widget name => replacement.
		 * @return array
		 */
		public static function retired( $retired ) {
			if ( ! is_array( $retired ) ) {
				$retired = array();
			}
			$retired['wp_easycart_product_addtocart']      = array(
				'replacement' => 'wp_easycart_add_to_cart',
				'title'       => __( 'Add to Cart', 'wp-easycart' ),
				'map'         => array( __CLASS__, 'add_to_cart' ),
			);
			$retired['wp_easycart_product_details_price']  = array(
				'replacement' => 'wp_easycart_product_price',
				'title'       => __( 'Product Price', 'wp-easycart' ),
				'map'         => array( __CLASS__, 'price' ),
			);
			$retired['wp_easycart_product_details_stock']  = array(
				'replacement' => 'wp_easycart_product_stock',
				'title'       => __( 'Product Stock', 'wp-easycart' ),
				'map'         => array( __CLASS__, 'stock' ),
			);
			$retired['wp_easycart_product_details_sku']    = array(
				'replacement' => 'wp_easycart_product_sku',
				'title'       => __( 'Product SKU', 'wp-easycart' ),
				'map'         => array( __CLASS__, 'sku' ),
			);
			$retired['wp_easycart_product_details_images'] = array(
				'replacement' => 'wp_easycart_product_gallery',
				'title'       => __( 'Product Gallery', 'wp-easycart' ),
				'map'         => array( __CLASS__, 'gallery' ),
			);
			return $retired;
		}

		/**
		 * Legacy "WP EasyCart Add to Cart" ( v1 and v2 ) → Add to Cart.
		 *
		 * @param array $old Saved settings.
		 * @return array
		 */
		public static function add_to_cart( $old ) {
			$old = is_array( $old ) ? $old : array();
			$out = array();
			$v2  = ( isset( $old['enable_v2'] ) && 'yes' === $old['enable_v2'] );
			if ( $v2 ) {
				self::product( $old, 'use_post_id', 'product_id_v2', $out );
				/* v2 posted the form: the shopper went to the cart ( or back, with the store's "stay on the product" setting ). */
				$out['after_add'] = 'cart';
				if ( array_key_exists( 'enable_your_price', $old ) && 'yes' !== $old['enable_your_price'] ) {
					$out['show_your_price'] = '';
				}
				if ( array_key_exists( 'enable_quantity_v2', $old ) && 'yes' !== $old['enable_quantity_v2'] ) {
					$out['quantity_style'] = 'none';
				}
				foreach ( array(
					'minus' => 'quantity_minus_icon',
					'plus'  => 'quantity_plus_icon',
				) as $key => $to ) {
					if ( isset( $old[ 'ec_adtw_quantity_' . $key . '_button_icon' ] ) && is_array( $old[ 'ec_adtw_quantity_' . $key . '_button_icon' ] ) ) {
						$out[ $to ] = $old[ 'ec_adtw_quantity_' . $key . '_button_icon' ];
					}
				}
				/* Button */
				self::copy( $old, 'ec_adtw_button_color', 'button_text_color', $out );
				self::copy( $old, 'ec_adtw_button_color_hover', 'button_hover_text_color', $out );
				self::copy_background_color( $old, 'ec_adtw_button_background', 'button_background_color', $out );
				self::copy_background_color( $old, 'ec_adtw_button_background_hover', 'button_hover_background_color', $out );
				self::copy( $old, 'ec_adtw_button_border_color_hover', 'button_hover_border_color', $out );
				self::copy_group( $old, 'ec_adtw_button_font', 'button_typography', $out );
				self::copy_group( $old, 'ec_adtw_button_border', 'button_border', $out );
				self::copy( $old, 'ec_adtw_text_button_border_radius', 'button_border_radius', $out );
				self::copy( $old, 'ec_adtw_button_padding', 'button_padding', $out );
				/* Quantity */
				self::copy( $old, 'ec_adtw_quantity_button_color', 'quantity_button_color', $out );
				self::copy_background_color( $old, 'ec_adtw_quantity_button_background', 'quantity_button_background_color', $out );
				self::copy( $old, 'ec_adtw_quantity_input_box_color', 'quantity_text_color', $out );
				self::copy( $old, 'ec_adtw_text_quantity_border_radius', 'quantity_border_radius', $out );
				/* Option labels */
				self::copy( $old, 'ec_adtw_label_color', 'label_color', $out );
				self::copy_group( $old, 'ec_adtw_label_font', 'label_typography', $out );
				/* Swatches */
				self::copy( $old, 'ec_adtw_swatch_color', 'swatch_text_color', $out );
				self::copy_background_color( $old, 'ec_adtw_swatch_background', 'swatch_background_color', $out );
				self::copy( $old, 'ec_adtw_swatch_color_hover', 'swatch_hover_text_color', $out );
				self::copy_background_color( $old, 'ec_adtw_swatch_background_hover', 'swatch_hover_background_color', $out );
				self::copy( $old, 'ec_adtw_swatch_border_color_hover', 'swatch_hover_border_color', $out );
				self::copy_background_color( $old, 'ec_adtw_swatch_background_selected', 'swatch_selected_background_color', $out );
				self::copy( $old, 'ec_adtw_swatch_border_color_selected', 'swatch_selected_border_color', $out );
				self::copy( $old, 'ec_adtw_swatch_image_border_color_selected', 'swatch_selected_border_color', $out );
				self::copy( $old, 'ec_adtw_swatch_border_radius', 'swatch_border_radius', $out );
				self::copy_group( $old, 'ec_adtw_swatch_font', 'swatch_typography', $out );
				/* Your price and messages */
				self::copy( $old, 'ec_adtw_your_price_color', 'your_price_color', $out );
				self::copy_group( $old, 'ec_adtw_your_price_font', 'your_price_typography', $out );
				self::copy( $old, 'ec_adtw_error_color', 'error_color', $out );
			} else {
				self::product( $old, '', 'product_id', $out );
				/* v1 added in the background unless "No" was chosen ( the old default, 'featured', added in the background ). */
				$out['after_add'] = ( isset( $old['background_add'] ) && '0' === (string) $old['background_add'] ) ? 'cart' : 'stay';
				/* Display Quantity switched off ( stored '' ); untouched widgets always showed the box. */
				if ( array_key_exists( 'enable_quantity', $old ) && '' === $old['enable_quantity'] ) {
					$out['quantity_style'] = 'none';
				}
				self::copy( $old, 'button_bg_color', 'button_background_color', $out );
				self::copy( $old, 'button_text_color', 'button_text_color', $out );
			}
			return $out;
		}

		/**
		 * Legacy "WP EasyCart Product Price" → Product Price.
		 *
		 * @param array $old Saved settings.
		 * @return array
		 */
		public static function price( $old ) {
			$old = is_array( $old ) ? $old : array();
			$out = array();
			self::product( $old, 'use_post_id', 'product_id', $out );
			if ( array_key_exists( 'show_list_price', $old ) && 'yes' !== $old['show_list_price'] ) {
				$out['show_regular_price'] = '';
			}
			self::copy( $old, 'price_color', 'price_color', $out );
			self::copy_group( $old, 'price_font', 'price_typography', $out );
			self::copy( $old, 'sale_price_color', 'sale_price_color', $out );
			self::copy_group( $old, 'sale_price_font', 'sale_price_typography', $out );
			self::copy( $old, 'previous_price_color', 'regular_price_color', $out );
			self::copy_group( $old, 'previous_price_font', 'regular_price_typography', $out );
			self::copy_align( $old, 'price_align', 'align', $out );
			self::copy( $old, 'price_container_background_color', 'box_background_color', $out );
			self::copy( $old, 'price_container_padding', 'box_padding', $out );
			self::copy( $old, 'price_container_margin', 'box_margin', $out );
			self::copy_group( $old, 'price_container_border', 'box_border', $out );
			self::copy( $old, 'price_container_border_radius', 'box_border_radius', $out );
			return $out;
		}

		/**
		 * Legacy "WP EasyCart Product Stock" → Product Stock.
		 *
		 * @param array $old Saved settings.
		 * @return array
		 */
		public static function stock( $old ) {
			$old = is_array( $old ) ? $old : array();
			$out = array();
			self::product( $old, 'use_post_id', 'product_id', $out );
			self::copy( $old, 'ec_stockw_label_color', 'text_color', $out );
			self::copy_group( $old, 'ec_stockw_label_font', 'text_typography', $out );
			self::copy_align( $old, 'ec_stockw_align', 'align', $out );
			self::copy_background_color( $old, 'ec_stockw_background_color', 'box_background_color', $out );
			self::copy( $old, 'ec_stockw_padding', 'box_padding', $out );
			self::copy( $old, 'ec_stockw_margin', 'box_margin', $out );
			self::copy_group( $old, 'ec_stockw_border', 'box_border', $out );
			self::copy( $old, 'ec_stockw_border_radius', 'box_border_radius', $out );
			return $out;
		}

		/**
		 * Legacy "WP EasyCart Product SKU" → Product SKU. The legacy widget showed the number alone.
		 *
		 * @param array $old Saved settings.
		 * @return array
		 */
		public static function sku( $old ) {
			$old = is_array( $old ) ? $old : array();
			$out = array( 'show_label' => '' );
			self::product( $old, 'use_post_id', 'product_id', $out );
			self::copy( $old, 'ec_skw_sku_color', 'value_color', $out );
			self::copy_group( $old, 'ec_skw_sku_font', 'value_typography', $out );
			self::copy_align( $old, 'ec_skw_align', 'align', $out );
			self::copy( $old, 'ec_skw_background_color', 'box_background_color', $out );
			self::copy( $old, 'ec_skw_padding', 'box_padding', $out );
			self::copy( $old, 'ec_skw_margin', 'box_margin', $out );
			self::copy_group( $old, 'ec_skw_border', 'box_border', $out );
			self::copy( $old, 'ec_skw_border_radius', 'box_border_radius', $out );
			return $out;
		}

		/**
		 * Legacy "WP EasyCart Product Images" → Product Gallery.
		 *
		 * @param array $old Saved settings.
		 * @return array
		 */
		public static function gallery( $old ) {
			$old = is_array( $old ) ? $old : array();
			$out = array();
			self::product( $old, 'use_post_id', 'product_id', $out );
			/* Switches the merchant turned off ( stored '' ). Untouched ones take the new widget's defaults. */
			if ( array_key_exists( 'image_hover', $old ) && 'yes' !== $old['image_hover'] ) {
				$out['zoom'] = '';
			}
			if ( array_key_exists( 'lightbox', $old ) && 'yes' !== $old['lightbox'] ) {
				$out['lightbox'] = '';
			}
			$positions = array(
				'column'         => 'below',
				'column-reverse' => 'above',
				'row-reverse'    => 'left',
				'row'            => 'right',
			);
			if ( array_key_exists( 'thumbnails', $old ) && '' === $old['thumbnails'] ) {
				$out['thumbnails_position'] = 'none';
			} else {
				foreach ( $old as $key => $value ) {
					if ( is_string( $key ) && preg_match( '/^thumbnails_position(_[a-z_]+)?$/', $key, $match ) && is_string( $value ) && isset( $positions[ $value ] ) ) {
						$out[ 'thumbnails_position' . ( isset( $match[1] ) ? $match[1] : '' ) ] = $positions[ $value ];
					}
				}
			}
			foreach ( array(
				'ec_imw_image_size_size' => 'image_size',
				'ec_imw_thumb_size_size' => 'thumbnail_size',
			) as $from => $to ) {
				if ( isset( $old[ $from ] ) && is_string( $old[ $from ] ) && 'custom' !== $old[ $from ] && 'small' !== $old[ $from ] ) {
					$out[ $to ] = $old[ $from ];
				}
			}
			self::copy_group( $old, 'ec_imw_main_border', 'image_border', $out );
			self::copy( $old, 'ec_imw_main_border_radius', 'image_border_radius', $out );
			self::copy_group( $old, 'ec_imw_thumb_border', 'thumbnail_border', $out );
			self::copy( $old, 'ec_imw_thumb_border_radius', 'thumbnail_border_radius', $out );
			return $out;
		}

		/**
		 * The product choice: "Use in Template" → this page's product, else the picked product.
		 *
		 * @param array  $old        Saved settings.
		 * @param string $use_post   Legacy "Use in Template" switch ( '' when the widget has none ).
		 * @param string $product_id Legacy product picker.
		 * @param array  $out        New settings ( by reference ).
		 */
		private static function product( $old, $use_post, $product_id, &$out ) {
			if ( '' !== $use_post && isset( $old[ $use_post ] ) && 'yes' === $old[ $use_post ] ) {
				return; /* the new default: the product of the page it is on */
			}
			$id = isset( $old[ $product_id ] ) ? $old[ $product_id ] : '';
			if ( is_array( $id ) ) {
				$id = reset( $id );
			}
			$id = is_scalar( $id ) ? absint( $id ) : 0;
			if ( $id > 0 ) {
				$out['ec_product_source'] = 'pick';
				$out['ec_product_id']     = (string) $id;
			}
		}

		/**
		 * Copies a control's value, with its responsive variants ( _tablet, _mobile … ) and a global colour link.
		 *
		 * @param array  $old  Saved settings.
		 * @param string $from Legacy control id.
		 * @param string $to   New control id.
		 * @param array  $out  New settings ( by reference ).
		 */
		private static function copy( $old, $from, $to, &$out ) {
			foreach ( $old as $key => $value ) {
				if ( ! is_string( $key ) ) {
					continue;
				}
				if ( $key === $from ) {
					$out[ $to ] = $value;
				} elseif ( 0 === strpos( $key, $from . '_' ) && preg_match( '/^_(mobile|mobile_extra|tablet|tablet_extra|laptop|widescreen)$/', substr( $key, strlen( $from ) ) ) ) {
					$out[ $to . substr( $key, strlen( $from ) ) ] = $value;
				}
			}
			if ( isset( $old['__globals__'][ $from ] ) && is_string( $old['__globals__'][ $from ] ) && '' !== $old['__globals__'][ $from ] ) {
				$out['__globals__'][ $to ] = $old['__globals__'][ $from ];
			}
		}

		/**
		 * Copies a group control ( typography, border … ): every stored {name}_{field} key, and linked globals.
		 *
		 * @param array  $old  Saved settings.
		 * @param string $from Legacy group name.
		 * @param string $to   New group name.
		 * @param array  $out  New settings ( by reference ).
		 */
		private static function copy_group( $old, $from, $to, &$out ) {
			foreach ( $old as $key => $value ) {
				if ( is_string( $key ) && 0 === strpos( $key, $from . '_' ) ) {
					$out[ $to . substr( $key, strlen( $from ) ) ] = $value;
				}
			}
			if ( isset( $old['__globals__'] ) && is_array( $old['__globals__'] ) ) {
				foreach ( $old['__globals__'] as $key => $value ) {
					if ( is_string( $key ) && 0 === strpos( $key, $from . '_' ) && is_string( $value ) && '' !== $value ) {
						$out['__globals__'][ $to . substr( $key, strlen( $from ) ) ] = $value;
					}
				}
			}
		}

		/**
		 * A background group's plain colour ( classic background ) → a colour control.
		 *
		 * @param array  $old  Saved settings.
		 * @param string $from Legacy background group name.
		 * @param string $to   New colour control id.
		 * @param array  $out  New settings ( by reference ).
		 */
		private static function copy_background_color( $old, $from, $to, &$out ) {
			if ( isset( $old[ $from . '_color' ] ) && is_string( $old[ $from . '_color' ] ) && '' !== $old[ $from . '_color' ] ) {
				$out[ $to ] = $old[ $from . '_color' ];
			}
			if ( isset( $old['__globals__'][ $from . '_color' ] ) && is_string( $old['__globals__'][ $from . '_color' ] ) && '' !== $old['__globals__'][ $from . '_color' ] ) {
				$out['__globals__'][ $to ] = $old['__globals__'][ $from . '_color' ];
			}
		}

		/**
		 * Left / center / right ( text alignment ) → the new widgets' alignment ( start / center / end ).
		 *
		 * @param array  $old  Saved settings.
		 * @param string $from Legacy alignment control.
		 * @param string $to   New alignment control.
		 * @param array  $out  New settings ( by reference ).
		 */
		private static function copy_align( $old, $from, $to, &$out ) {
			$values = array(
				'left'   => 'start',
				'center' => 'center',
				'right'  => 'end',
			);
			foreach ( $old as $key => $value ) {
				if ( ! is_string( $key ) || ! is_string( $value ) || ! isset( $values[ $value ] ) ) {
					continue;
				}
				if ( $key === $from ) {
					$out[ $to ] = $values[ $value ];
				} elseif ( 0 === strpos( $key, $from . '_' ) && preg_match( '/^_(mobile|mobile_extra|tablet|tablet_extra|laptop|widescreen)$/', substr( $key, strlen( $from ) ) ) ) {
					$out[ $to . substr( $key, strlen( $from ) ) ] = $values[ $value ];
				}
			}
		}
	}

endif;
