<?php
/**
 * WP EasyCart — the size add-on ( advanced option types dimensions1 / dimensions2 ), read safely ( 6.0.5 ).
 *
 * A size is kept on a cart line as JSON: [ width, height ] for whole inches ( dimensions1, item name DimensionType1 ) and
 * [ width, width fraction, height, height fraction ] with fractions of an inch ( dimensions2 ). The line's price and weight are
 * scaled by its area ( ec_cartitem ).
 *
 * The product form always posts the width and height boxes, also for a size that is not required or that conditional logic hides,
 * so WP EasyCart 6.0.4 and older kept [ "", "" ] for a size left empty. PHP 8 cannot work out "" / 12 ( a TypeError ), so that line
 * broke the cart; on PHP 7 it, and an empty dimensions2 size on any PHP, made the line free ( an area of 0 ). Since 6.0.5 a size is
 * kept only when its width and height are each a positive number ( state(), ec_cartpage::get_advanced_option_vals() ), and a cart line
 * leaves out one that is not ( without_empty() ): it is not priced, shown or recorded on the order, as an empty text box is not.
 *
 * A number is what a measure starts with, the rule PHP's own arithmetic used on these values, so every size that priced before
 * prices the same: 24, 24.5, 24" and 24 in are all 24 ( 24.5 ). A box left empty leaves the size out; a box holding 0, a negative
 * number or words makes it a size the store cannot sell ( 'invalid' ): 6.0.4 scaled the price by that area, so a line could cost
 * nothing or less than nothing. The add to cart, the inquiry and the subscription's Sign Up are refused with the option's error
 * text, and the product page checks the same before it posts.
 *
 * A subscription's size is kept the same way, in the checkout session ( subscription_advanced_option ). text() writes a size as the
 * cart does, for the subscription page and its first order; display_list() hands the subscription template each size as its values.
 *
 * @package WP_EasyCart
 * @since   6.0.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_size_option' ) ) :

	/**
	 * The size add-on's values.
	 */
	class wp_easycart_size_option {

		/**
		 * The option is a size add-on.
		 *
		 * @param string $option_type Advanced option type.
		 * @return bool
		 */
		public static function is_size( $option_type ) {
			return 'dimensions1' === $option_type || 'dimensions2' === $option_type;
		}

		/**
		 * The number a typed measure starts with, as PHP's arithmetic read it ( "24" and "24\"" are 24 ), or null when it has none
		 * ( empty, or words ).
		 *
		 * @param mixed $value A width or height as posted or kept.
		 * @return float|null
		 */
		public static function number( $value ) {
			if ( is_int( $value ) || is_float( $value ) ) {
				return (float) $value;
			}
			if ( ! is_string( $value ) || ! preg_match( '/^\s*[+-]?(\d|\.\d)/', $value ) ) {
				return null;
			}
			return (float) $value;
		}

		/**
		 * The fraction of an inch a size with fractions ( dimensions2 ) posts from its list, as the cart reads it: 1/16 to 15/16,
		 * anything else 0.
		 *
		 * @param mixed $value The chosen fraction, such as "3/8".
		 * @return float
		 */
		public static function fraction( $value ) {
			$fractions = array(
				'1/16'  => .0625,
				'1/8'   => .1250,
				'3/16'  => .1875,
				'1/4'   => .2500,
				'5/16'  => .3125,
				'3/8'   => .3750,
				'7/16'  => .4375,
				'1/2'   => .5000,
				'9/16'  => .5625,
				'5/8'   => .6250,
				'11/16' => .6875,
				'3/4'   => .7500,
				'13/16' => .8125,
				'7/8'   => .8750,
				'15/16' => .9375,
			);
			return ( is_scalar( $value ) && isset( $fractions[ (string) $value ] ) ) ? $fractions[ (string) $value ] : 0.0;
		}

		/**
		 * Where a size stands ( 6.0.5 ):
		 *
		 * - 'size': its width and height are each a positive number, and the area they make can be worked out ( above 0, not
		 *   infinite ). Only such a size is kept, priced, shown and recorded.
		 * - 'empty': nothing typed in the width or the height ( or in either ), as a size that is not required, or that conditional
		 *   logic hides, posts. It is left out, as an empty text box is.
		 * - 'invalid': a box holds something that is not a positive number ( 0, a negative number, words ), or the area is 0 or too
		 *   big to work out. No size the store can sell, so the add to cart, the inquiry and the subscription's Sign Up are refused
		 *   ( ec_cartpage::get_advanced_option_vals() ), and a cart line keeps none ( without_empty() ). The product page asks the
		 *   same before it posts ( ec-store.js wpeasycart_details_size_state() ).
		 *
		 * Each side is read as the cart scales by it: the number it starts with ( number(), so 24" is 24 ), with fractions its whole
		 * inches ( intval() ) plus the fraction. A side with fractions whose number is below 0 is refused even when the fraction lifts
		 * it above ( -0.5 and 1/2 ).
		 *
		 * @param mixed     $raw    The JSON kept on a cart line or in the subscription session, or the values as an array.
		 * @param bool|null $metric Sizes are metric ( millimetres, else inches ); null reads the store's setting.
		 * @return string 'size', 'empty' or 'invalid'.
		 */
		public static function state( $raw, $metric = null ) {
			$measure = self::measure( $raw, $metric );
			return $measure['state'];
		}

		/**
		 * What a size scales a line's price by: its area in square feet ( inches / 12 ), or square metres ( millimetres / 1000 ) on a
		 * metric store. Null when it is no size the store can sell ( state() is not 'size' ): the price is left as it is.
		 *
		 * @param mixed     $raw    The JSON kept on a cart line or in the subscription session, or the values as an array.
		 * @param bool|null $metric Sizes are metric; null reads the store's setting.
		 * @return float|null
		 */
		public static function factor( $raw, $metric = null ) {
			$measure = self::measure( $raw, $metric );
			return ( 'size' === $measure['state'] ) ? $measure['factor'] : null;
		}

		/**
		 * A kept size's values when it is a size the store can sell ( state() 'size' ): two ( width, height ) or four ( width, its
		 * fraction, height, its fraction ) values. Null for anything else, such as [ "", "" ], a fraction chosen alone, or a side that
		 * is 0, negative or words.
		 *
		 * @param mixed     $raw    The JSON kept on the cart line, or the values as an array.
		 * @param bool|null $metric Sizes are metric; null reads the store's setting.
		 * @return array|null
		 */
		public static function values( $raw, $metric = null ) {
			$measure = self::measure( $raw, $metric );
			return ( 'size' === $measure['state'] ) ? $measure['values'] : null;
		}

		/**
		 * Reads a size once for state(), factor() and values().
		 *
		 * @param mixed     $raw    The JSON kept, or the values as an array.
		 * @param bool|null $metric Sizes are metric; null reads the store's setting.
		 * @return array state, values, factor.
		 */
		private static function measure( $raw, $metric ) {
			$empty  = array(
				'state'  => 'empty',
				'values' => null,
				'factor' => null,
			);
			$values = is_array( $raw ) ? $raw : ( is_string( $raw ) ? json_decode( $raw, true ) : null );
			if ( ! is_array( $values ) ) {
				return $empty;
			}
			$values = array_values( $values );
			if ( 2 === count( $values ) ) {
				$sides = array( self::side( $values[0], false, null ), self::side( $values[1], false, null ) );
			} elseif ( 4 === count( $values ) ) {
				$sides = array( self::side( $values[0], true, $values[1] ), self::side( $values[2], true, $values[3] ) );
			} else {
				return $empty;
			}
			if ( false === $sides[0] || false === $sides[1] ) {
				$empty['state'] = 'invalid';
				return $empty;
			}
			if ( null === $sides[0] || null === $sides[1] ) {
				return $empty;
			}
			if ( null === $metric ) {
				$metric = (bool) get_option( 'ec_option_enable_metric_unit_display' );
			}
			$per    = $metric ? 1000 : 12;
			$factor = ( $sides[0] / $per ) * ( $sides[1] / $per );
			if ( ! is_finite( $factor ) || $factor <= 0 ) {
				$empty['state'] = 'invalid';
				return $empty;
			}
			return array(
				'state'  => 'size',
				'values' => $values,
				'factor' => $factor,
			);
		}

		/**
		 * One side of a size: its length as the cart scales by it, null when nothing is typed, false when what is typed is not a
		 * positive number.
		 *
		 * @param mixed $typed         The width or height as kept.
		 * @param bool  $has_fraction  The size has fractions ( dimensions2 ).
		 * @param mixed $fraction      The side's fraction.
		 * @return float|null|false
		 */
		private static function side( $typed, $has_fraction, $fraction ) {
			if ( null === $typed || ( is_string( $typed ) && '' === trim( $typed ) ) ) {
				return null;
			}
			$number = self::number( $typed );
			if ( null === $number || ! is_finite( $number ) || $number < 0 ) {
				return false;
			}
			$length = $has_fraction ? floor( $number ) + self::fraction( $fraction ) : $number; /* intval(): the whole inches */
			return ( is_finite( $length ) && $length > 0 ) ? $length : false;
		}

		/**
		 * A size as text, written as the cart writes it: 24" x 36" in whole inches, 24 1/2" x 36 0" with fractions, and without the
		 * inch marks on a metric store ( ec_option_enable_metric_unit_display ). '' when it is no size.
		 *
		 * @param mixed     $raw    The JSON kept on a cart line or in the subscription session, or the values as an array.
		 * @param bool|null $metric Sizes are metric; null reads the store's setting.
		 * @return string
		 */
		public static function text( $raw, $metric = null ) {
			if ( null === $metric ) {
				$metric = (bool) get_option( 'ec_option_enable_metric_unit_display' );
			}
			$values = self::values( $raw, $metric );
			if ( null === $values ) {
				return '';
			}
			$mark  = $metric ? '' : '"';
			$parts = array();
			foreach ( $values as $value ) {
				$parts[] = is_scalar( $value ) ? (string) $value : '';
			}
			if ( 2 === count( $parts ) ) {
				return $parts[0] . $mark . ' x ' . $parts[1] . $mark;
			}
			return $parts[0] . ' ' . $parts[1] . $mark . ' x ' . $parts[2] . ' ' . $parts[3] . $mark;
		}

		/**
		 * The subscription page's add-on list ( filter wp_easycart_subscription_modifiers_display_list ) with each size given as its
		 * values ( an array ) and without the sizes that hold none ( an empty size, or since 6.0.5 one the store cannot sell: state() ).
		 *
		 * The subscription session keeps a size as JSON, as the cart does, but ec_cart_subscription.php read it as an array of values,
		 * so it printed characters of the JSON ( [" x " ) for 24" x 36". The template since 6.0.5 writes it with text(); this keeps
		 * store copies of the older template right too.
		 *
		 * @param mixed $options The session's add-on rows ( arrays: option_type, optionitem_value … ), or '' when there are none.
		 * @return mixed
		 */
		public static function display_list( $options ) {
			if ( ! is_array( $options ) ) {
				return $options;
			}
			$list = array();
			foreach ( $options as $key => $option ) {
				if ( is_array( $option ) && isset( $option['option_type'] ) && self::is_size( $option['option_type'] ) ) {
					$values = self::values( isset( $option['optionitem_value'] ) ? $option['optionitem_value'] : null );
					if ( null === $values ) {
						continue;
					}
					$option['optionitem_value'] = $values;
				}
				$list[ $key ] = $option;
			}
			return $list;
		}

		/**
		 * A cart line's add-on rows without the size add-ons that hold no size the store can sell ( values() ): an empty size kept by
		 * WP EasyCart 6.0.4 and older, and a size with a side that is 0, negative or words, which nothing on the line may scale the
		 * price by ( the line keeps the product's price ). Since 6.0.5 such a size is refused before it reaches a cart.
		 *
		 * @param array $rows The line's rows ( ec_cart_data::get_advanced_cart_options(): option_type set from the option ).
		 * @return array
		 */
		public static function without_empty( $rows ) {
			if ( ! is_array( $rows ) ) {
				return $rows;
			}
			$kept = array();
			foreach ( $rows as $row ) {
				if ( is_object( $row ) && isset( $row->option_type ) && self::is_size( $row->option_type ) && null === self::values( isset( $row->optionitem_value ) ? $row->optionitem_value : null ) ) {
					continue;
				}
				$kept[] = $row;
			}
			return $kept;
		}
	}

	add_filter( 'wp_easycart_subscription_modifiers_display_list', array( 'wp_easycart_size_option', 'display_list' ), 5 );

endif;
