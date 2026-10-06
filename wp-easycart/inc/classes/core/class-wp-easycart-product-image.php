<?php
/**
 * A product's pictures from its database row ( 6.0.3 ).
 *
 * The ec_product table keeps pictures two ways. product_images is the gallery: a comma list of media library ids, image: addresses,
 * video: / youtube: / vimeo: entries ( the video, then its poster ) and image1 to image5 tokens that point back at the older
 * single picture columns. image1 to image5 hold a web address or a file name in wp-easycart-data/products/picsN. While a
 * product has a gallery the storefront shows only the gallery, so a picture column the gallery does not name is never shown,
 * and the gallery editor never clears it: an old picture can stay there unseen. A product with "different images per option"
 * shows the pictures of its Default set ( ec_optionitemimage, optionitem_id 0 ), else of its first option choice, instead.
 *
 * main_url() answers the picture a product shows first, the way the storefront picks it; the products CSV export uses it so the
 * file shows the picture the store shows. line_picture() is the value to save with an order line or a cart snapshot: image1 as
 * it is for a product without a gallery or per-option pictures ( what was always saved ), else main_url()'s address.
 *
 * @since 6.0.3
 * @package WP_EasyCart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_product_image' ) ) :

	/**
	 * Product pictures from rows.
	 */
	class wp_easycart_product_image {

		/**
		 * The address of the picture a product shows first, or ''.
		 *
		 * @param object|array     $row         An ec_product row ( product_id, use_optionitem_images, product_images, image1 to image5 ).
		 * @param string           $size        Media library size.
		 * @param array|null|false $option_rows The product's ec_optionitemimage rows in the storefront's order ( see option_rows() );
		 *                                      null looks them up when the product uses per-option pictures.
		 * @return string
		 */
		public static function main_url( $row, $size = 'large', $option_rows = null ) {
			$row = (object) $row;
			if ( ! empty( $row->use_optionitem_images ) ) {
				if ( null === $option_rows ) {
					$option_rows = isset( $row->product_id ) ? self::option_rows( (int) $row->product_id ) : array();
				}
				foreach ( (array) $option_rows as $option_row ) {
					$url = self::row_url( (object) $option_row, $size );
					if ( '' !== $url ) {
						return $url;
					}
				}
			}
			return self::row_url( $row, $size );
		}

		/**
		 * The picture to save with an order line, a renewal's line or a cart snapshot, which the receipts, My Account, the
		 * emails and the reminders read as an http(s) address or a file name in wp-easycart-data/products/pics1.
		 *
		 * A product without a gallery and without per-option pictures saves image1 as it is, as it always did. Otherwise
		 * image1 may be an old picture the store no longer shows ( or a file in another picsN folder ), so the line saves the
		 * address of the picture the store shows: with option choices, the first chosen choice that has pictures of its own
		 * ( as the cart shows it ), else main_url().
		 *
		 * @param object|array|null $row            An ec_product row ( product_id, use_optionitem_images, product_images, image1 to image5 ).
		 * @param int[]             $optionitem_ids The line's option choices, in slot order.
		 * @return string
		 */
		public static function line_picture( $row, $optionitem_ids = array() ) {
			if ( ! $row ) {
				return '';
			}
			$row   = (object) $row;
			$saved = isset( $row->image1 ) ? (string) $row->image1 : '';
			if ( self::is_plain( $row ) ) {
				return $saved;
			}
			$option_rows = null;
			if ( ! empty( $row->use_optionitem_images ) ) {
				$option_rows = isset( $row->product_id ) ? self::option_rows( (int) $row->product_id, $optionitem_ids ) : array();
			}
			$url = self::absolute( self::main_url( $row, 'large', $option_rows ) );
			return ( '' !== $url ) ? $url : $saved;
		}

		/**
		 * Whether the store shows the product's picture columns as they are: no gallery and no per-option pictures.
		 *
		 * @param object|array $row An ec_product row.
		 * @return bool
		 */
		public static function is_plain( $row ) {
			$row = (object) $row;
			return empty( $row->use_optionitem_images ) && ! self::tokens( $row );
		}

		/**
		 * The product's row with the columns these methods read, or null.
		 *
		 * @param int $product_id Product.
		 * @return object|null
		 */
		public static function product_row( $product_id ) {
			global $wpdb;
			if ( ! $product_id || ! is_object( $wpdb ) ) {
				return null;
			}
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT product_id, use_optionitem_images, product_images, image1, image2, image3, image4, image5 FROM ec_product WHERE product_id = %d', (int) $product_id ) );
			return $row ? $row : null;
		}

		/**
		 * A protocol-relative address with the site's scheme ( the receipts and emails read http(s) addresses only ).
		 *
		 * @param string $url Address.
		 * @return string
		 */
		public static function absolute( $url ) {
			$url = (string) $url;
			if ( 0 === strpos( $url, '//' ) ) {
				$url = function_exists( 'set_url_scheme' ) ? set_url_scheme( $url ) : 'https:' . $url;
			}
			return $url;
		}

		/**
		 * A row's first picture: its gallery's first entry that is a picture, else its first picture column.
		 *
		 * @param object $row  An ec_product or ec_optionitemimage row.
		 * @param string $size Media library size.
		 * @return string
		 */
		public static function row_url( $row, $size = 'large' ) {
			foreach ( self::tokens( $row ) as $token ) {
				$url = self::token_url( $token, $row, $size );
				if ( '' !== $url ) {
					return $url;
				}
			}
			for ( $slot = 1; $slot <= 5; $slot++ ) {
				$url = self::slot_url( isset( $row->{ 'image' . $slot } ) ? $row->{ 'image' . $slot } : '', $slot );
				if ( '' !== $url ) {
					return $url;
				}
			}
			return '';
		}

		/**
		 * The row's gallery entries, in order.
		 *
		 * @param object $row Row.
		 * @return string[]
		 */
		public static function tokens( $row ) {
			$list = isset( $row->product_images ) ? trim( (string) $row->product_images ) : '';
			return ( '' === $list ) ? array() : array_values( array_filter( array_map( 'trim', explode( ',', $list ) ) ) );
		}

		/**
		 * The picture columns ( 1 to 5 ) the row's gallery names ( image1 to image5 tokens ). With no gallery every column is
		 * shown, so the answer is all five.
		 *
		 * @param object $row Row.
		 * @return int[]
		 */
		public static function used_slots( $row ) {
			$tokens = self::tokens( $row );
			if ( ! $tokens ) {
				return array( 1, 2, 3, 4, 5 );
			}
			$slots = array();
			foreach ( $tokens as $token ) {
				if ( preg_match( '/^image([1-5])$/', $token, $match ) ) {
					$slots[] = (int) $match[1];
				}
			}
			return array_values( array_unique( $slots ) );
		}

		/**
		 * One gallery entry's picture address, or '' when it is not a picture ( a video without a poster, a deleted media item ).
		 *
		 * @param string $token Entry.
		 * @param object $row   The row it belongs to ( for image1 to image5 ).
		 * @param string $size  Media library size.
		 * @return string
		 */
		public static function token_url( $token, $row, $size = 'large' ) {
			$token = trim( (string) $token );
			if ( '' === $token ) {
				return '';
			}
			if ( preg_match( '/^image([1-5])$/', $token, $match ) ) {
				return self::slot_url( isset( $row->{ 'image' . $match[1] } ) ? $row->{ 'image' . $match[1] } : '', (int) $match[1] );
			}
			if ( 0 === strpos( $token, 'image:' ) ) {
				$token = trim( substr( $token, 6 ) );
				return self::is_address( $token ) ? $token : '';
			}
			foreach ( array( 'video:', 'youtube:', 'vimeo:' ) as $prefix ) {
				if ( 0 === strpos( $token, $prefix ) ) {
					$parts = explode( ':::', substr( $token, strlen( $prefix ) ) ); /* the video, then its poster */
					return ( isset( $parts[1] ) && self::is_address( trim( $parts[1] ) ) ) ? trim( $parts[1] ) : '';
				}
			}
			if ( preg_match( '/^\d+$/', $token ) ) {
				$src = function_exists( 'wp_get_attachment_image_src' ) ? wp_get_attachment_image_src( (int) $token, $size ) : false;
				return ( is_array( $src ) && ! empty( $src[0] ) ) ? (string) $src[0] : '';
			}
			return self::is_address( $token ) ? $token : '';
		}

		/**
		 * A picture column's address: a web address as it is, a file name in wp-easycart-data/products/picsN.
		 *
		 * @param string $value Stored value.
		 * @param int    $slot  1 to 5.
		 * @return string
		 */
		public static function slot_url( $value, $slot ) {
			$value = trim( (string) $value );
			if ( '' === $value ) {
				return '';
			}
			if ( self::is_address( $value ) ) {
				return $value;
			}
			if ( ! function_exists( 'plugins_url' ) || ! defined( 'EC_PLUGIN_DATA_DIRECTORY' ) ) {
				return '';
			}
			return plugins_url( '/wp-easycart-data/products/pics' . max( 1, min( 5, (int) $slot ) ) . '/' . $value, EC_PLUGIN_DATA_DIRECTORY );
		}

		/**
		 * The product's per-option picture rows in the storefront's order ( ec_options::get_optionitem_images() ): its Default
		 * set first, then each option choice's by the choice's order. Choices a cart or order line has ( $chosen ) come
		 * before all of them, in the order given ( 6.0.3 ).
		 *
		 * @param int   $product_id Product.
		 * @param int[] $chosen     Option item ids to put first.
		 * @return object[]
		 */
		public static function option_rows( $product_id, $chosen = array() ) {
			global $wpdb;
			if ( ! $product_id || ! is_object( $wpdb ) ) {
				return array();
			}
			$default = $wpdb->get_results( $wpdb->prepare( 'SELECT image1, image2, image3, image4, image5, product_images FROM ec_optionitemimage WHERE product_id = %d AND optionitem_id = 0', $product_id ) );
			$choices = $wpdb->get_results( $wpdb->prepare( 'SELECT ec_optionitemimage.optionitem_id, ec_optionitemimage.image1, ec_optionitemimage.image2, ec_optionitemimage.image3, ec_optionitemimage.image4, ec_optionitemimage.image5, ec_optionitemimage.product_images FROM ec_optionitemimage, ec_optionitem WHERE ec_optionitemimage.product_id = %d AND ec_optionitem.optionitem_id = ec_optionitemimage.optionitem_id ORDER BY ec_optionitem.optionitem_order', $product_id ) );
			$first   = array();
			foreach ( array_unique( array_filter( array_map( 'intval', (array) $chosen ) ) ) as $optionitem_id ) {
				foreach ( (array) $choices as $choice ) {
					if ( (int) $choice->optionitem_id === $optionitem_id ) {
						$first[] = $choice;
					}
				}
			}
			return array_merge( $first, (array) $default, (array) $choices );
		}

		/**
		 * Whether a value is a web address ( http, https or protocol relative ).
		 *
		 * @param string $value Value.
		 * @return bool
		 */
		private static function is_address( $value ) {
			return (bool) preg_match( '#^(https?:)?//#i', (string) $value );
		}
	}

endif;
