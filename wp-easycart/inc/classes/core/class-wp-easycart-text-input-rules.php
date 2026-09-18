<?php
/**
 * WP EasyCart — Input rules for free-text modifier option sets ( "text" and "textarea" ).
 *
 * One place that knows how a shopper-typed value is formatted, so the option
 * editor, every storefront template ( via data attributes read by
 * design/theme/base-responsive-v3/ec-text-input-rules.js ) and every server-side
 * add-to-cart path apply exactly the same rules.
 *
 * Storage: ec_option.option_meta ( a PHP-serialized array, written with
 * maybe_serialize() ). 6.0.0 adds three optional keys next to the existing
 * min / max / min_length / max_length / step / url_var / swatch_size:
 *
 *   text_case    'none' ( default ) | 'upper' | 'lower' | 'title'
 *   text_allowed 'any'  ( default ) | 'letters' | 'letters_space' | 'numbers' | 'alnum' | 'alnum_space'
 *   placeholder  string, '' ( default ) = no placeholder
 *
 * The character limits reuse the existing 'min_length' / 'max_length' keys ( the minimum is
 * checked, never applied: a value shorter than it is rejected on add to cart ). Missing or unknown
 * values fall back to the defaults, which reproduce the pre-6.0.0 behavior exactly.
 *
 * Order of operations ( JS mirrors it ): case -> strip disallowed -> max length.
 * "Title Case" capitalises the first letter of each word and leaves the rest as typed.
 * "Letters" means any Unicode letter ( accents, non-Latin scripts ) plus combining marks.
 * "Numbers" means the digits 0-9.
 *
 * @package wp-easycart
 * @since   6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_text_input_rules' ) ) :

	/**
	 * Text input rules helper ( static ).
	 */
	class wp_easycart_text_input_rules {

		/**
		 * Option types the rules apply to.
		 *
		 * @return array
		 */
		public static function types() {
			return array( 'text', 'textarea' );
		}

		/**
		 * Allowed text_case values ( first is the default ).
		 *
		 * @return array
		 */
		public static function cases() {
			return array( 'none', 'upper', 'lower', 'title' );
		}

		/**
		 * Allowed text_allowed values ( first is the default ).
		 *
		 * @return array
		 */
		public static function allowed_sets() {
			return array( 'any', 'letters', 'letters_space', 'numbers', 'alnum', 'alnum_space' );
		}

		/**
		 * Whether an option type takes input rules.
		 *
		 * @param string $option_type Option type.
		 * @return bool
		 */
		public static function supports( $option_type ) {
			return in_array( (string) $option_type, self::types(), true );
		}

		/**
		 * Normalised rules from an option_meta value ( array or serialized string ).
		 *
		 * @param mixed $option_meta Option meta.
		 * @return array { case, allowed, min_length, max_length, placeholder }
		 */
		public static function get_rules( $option_meta ) {
			if ( is_string( $option_meta ) && function_exists( 'maybe_unserialize' ) ) {
				$option_meta = maybe_unserialize( $option_meta );
			}
			if ( ! is_array( $option_meta ) ) {
				$option_meta = array();
			}
			$case    = isset( $option_meta['text_case'] ) ? (string) $option_meta['text_case'] : '';
			$allowed = isset( $option_meta['text_allowed'] ) ? (string) $option_meta['text_allowed'] : '';
			return array(
				'case'        => in_array( $case, self::cases(), true ) ? $case : 'none',
				'allowed'     => in_array( $allowed, self::allowed_sets(), true ) ? $allowed : 'any',
				'min_length'  => isset( $option_meta['min_length'] ) ? max( 0, (int) $option_meta['min_length'] ) : 0,
				'max_length'  => isset( $option_meta['max_length'] ) ? max( 0, (int) $option_meta['max_length'] ) : 0,
				'placeholder' => isset( $option_meta['placeholder'] ) ? (string) $option_meta['placeholder'] : '',
			);
		}

		/**
		 * Sanitise the new meta keys posted by the option editor.
		 *
		 * @param array $meta_in Raw meta from the editor payload.
		 * @return array text_case, text_allowed, placeholder.
		 */
		public static function sanitize_meta( $meta_in ) {
			$meta_in = is_array( $meta_in ) ? $meta_in : array();
			$case    = isset( $meta_in['text_case'] ) ? sanitize_key( $meta_in['text_case'] ) : '';
			$allowed = isset( $meta_in['text_allowed'] ) ? sanitize_key( $meta_in['text_allowed'] ) : '';
			return array(
				'text_case'    => in_array( $case, self::cases(), true ) ? $case : 'none',
				'text_allowed' => in_array( $allowed, self::allowed_sets(), true ) ? $allowed : 'any',
				'placeholder'  => isset( $meta_in['placeholder'] ) ? sanitize_text_field( $meta_in['placeholder'] ) : '',
			);
		}

		/**
		 * Apply case, allowed characters and max length to a value.
		 *
		 * @param string $value     Value ( already sanitised as text ).
		 * @param array  $rules     Rules from get_rules().
		 * @param bool   $multiline Keep line breaks for "letters, numbers and spaces" ( textarea ).
		 * @return string
		 */
		public static function apply( $value, $rules, $multiline = false ) {
			$value = (string) $value;
			if ( '' === $value || ! is_array( $rules ) ) {
				return $value;
			}

			/* Case */
			if ( 'upper' === $rules['case'] ) {
				$value = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $value, 'UTF-8' ) : strtoupper( $value );
			} elseif ( 'lower' === $rules['case'] ) {
				$value = function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
			} elseif ( 'title' === $rules['case'] ) {
				$titled = preg_replace_callback(
					'/(^|\s)(\S)/u',
					function ( $m ) {
						return $m[1] . ( function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $m[2], 'UTF-8' ) : strtoupper( $m[2] ) );
					},
					$value
				);
				$value  = ( null === $titled ) ? ucwords( $value ) : $titled;
			}

			/* Allowed characters */
			$unicode  = '';
			$fallback = '';
			$space    = $multiline ? ' \r\n' : ' ';
			if ( 'letters' === $rules['allowed'] ) {
				$unicode  = '/[^\p{L}\p{M}]+/u';
				$fallback = '/[^A-Za-z]+/';
			} elseif ( 'letters_space' === $rules['allowed'] ) {
				$unicode  = '/[^\p{L}\p{M}' . $space . ']+/u';
				$fallback = '/[^A-Za-z' . $space . ']+/';
			} elseif ( 'numbers' === $rules['allowed'] ) {
				$unicode  = '/[^0-9]+/';
				$fallback = '/[^0-9]+/';
			} elseif ( 'alnum' === $rules['allowed'] ) {
				$unicode  = '/[^\p{L}\p{M}0-9]+/u';
				$fallback = '/[^A-Za-z0-9]+/';
			} elseif ( 'alnum_space' === $rules['allowed'] ) {
				$unicode  = '/[^\p{L}\p{M}0-9' . $space . ']+/u';
				$fallback = '/[^A-Za-z0-9' . $space . ']+/';
			}
			if ( '' !== $unicode ) {
				$stripped = preg_replace( $unicode, '', $value );
				if ( null === $stripped ) {
					/* PCRE without UTF-8 support or invalid UTF-8: be strict rather than let the rule through. */
					$stripped = (string) preg_replace( $fallback, '', $value );
				}
				$value = $stripped;
			}

			/* Max length ( characters, not bytes ) */
			$max = isset( $rules['max_length'] ) ? (int) $rules['max_length'] : 0;
			if ( $max > 0 ) {
				if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
					if ( mb_strlen( $value, 'UTF-8' ) > $max ) {
						$value = mb_substr( $value, 0, $max, 'UTF-8' );
					}
				} elseif ( strlen( $value ) > $max ) {
					$value = substr( $value, 0, $max );
				}
			}

			return $value;
		}

		/**
		 * Number of characters ( not bytes ) in a value.
		 *
		 * @param string $value Value.
		 * @return int
		 */
		public static function length( $value ) {
			return function_exists( 'mb_strlen' ) ? (int) mb_strlen( (string) $value, 'UTF-8' ) : strlen( (string) $value );
		}

		/**
		 * Server-side check for a submitted text / textarea value.
		 *
		 * Rejected when the shopper typed something and either the rules stripped all of
		 * it on a required option ( reason 'empty' ), or what is left is shorter than the
		 * minimum length ( reason 'min_length', required or not ). An empty submission is
		 * never rejected here ( conditional logic can hide required options; that stays
		 * the JS check's job ).
		 *
		 * @param string $value     Submitted value, already sanitize_text_field()'d and unslashed.
		 * @param object $optionset Option set row ( option_type, option_meta, option_required ).
		 * @return array { value: string, rejected: bool, reason: string }
		 */
		public static function validate( $value, $optionset ) {
			$value = (string) $value;
			if ( ! is_object( $optionset ) || ! isset( $optionset->option_type ) || ! self::supports( $optionset->option_type ) ) {
				return array(
					'value'    => $value,
					'rejected' => false,
					'reason'   => '',
				);
			}
			$rules  = self::get_rules( isset( $optionset->option_meta ) ? $optionset->option_meta : array() );
			$clean  = trim( self::apply( $value, $rules, 'textarea' === $optionset->option_type ) );
			$reason = '';
			if ( '' !== trim( $value ) && '' === $clean && ! empty( $optionset->option_required ) ) {
				$reason = 'empty';
			} elseif ( '' !== $clean && $rules['min_length'] > 0 && self::length( $clean ) < $rules['min_length'] ) {
				$reason = 'min_length';
			}
			return array(
				'value'    => $clean,
				'rejected' => '' !== $reason,
				'reason'   => $reason,
			);
		}

		/**
		 * Shopper-facing message for a value shorter than the minimum length
		 * ( language editor: Product Details › Text Option Minimum Length ).
		 *
		 * @param int $min Minimum characters.
		 * @return string Plain text.
		 */
		public static function min_length_message( $min ) {
			$text = function_exists( 'wp_easycart_language' ) ? trim( (string) wp_easycart_language()->get_text( 'product_details', 'product_details_min_length' ) ) : '';
			if ( '' === $text || false === strpos( $text, '%d' ) ) {
				$text = 'Please enter at least %d characters.';
			}
			return html_entity_decode( str_replace( '%d', (string) (int) $min, $text ), ENT_QUOTES, 'UTF-8' );
		}

		/**
		 * HTML attributes for the storefront input / textarea. Empty string when the
		 * option uses the defaults, so existing markup is unchanged. A minimum length adds
		 * data-ec-min-length and its message, which ec-store.js checks on add to cart.
		 *
		 * @param object $optionset      Option set ( option_type, option_meta ).
		 * @param bool   $add_max_length Also print maxlength ( the text template already does ).
		 * @return string Escaped attribute string with a leading space.
		 */
		public static function attributes( $optionset, $add_max_length = false ) {
			if ( ! is_object( $optionset ) || ! isset( $optionset->option_type ) || ! self::supports( $optionset->option_type ) ) {
				return '';
			}
			$rules = self::get_rules( isset( $optionset->option_meta ) ? $optionset->option_meta : array() );
			$out   = '';
			if ( 'none' !== $rules['case'] ) {
				$transform = array(
					'upper' => 'uppercase',
					'lower' => 'lowercase',
					'title' => 'capitalize',
				);
				$autocap   = array(
					'upper' => 'characters',
					'lower' => 'none',
					'title' => 'words',
				);
				$out      .= ' data-ec-text-case="' . esc_attr( $rules['case'] ) . '"';
				$out      .= ' autocapitalize="' . esc_attr( $autocap[ $rules['case'] ] ) . '"';
				$out      .= ' style="text-transform:' . esc_attr( $transform[ $rules['case'] ] ) . ';"';
			}
			if ( 'any' !== $rules['allowed'] ) {
				$out .= ' data-ec-text-allowed="' . esc_attr( $rules['allowed'] ) . '"';
				if ( 'numbers' === $rules['allowed'] ) {
					$out .= ' inputmode="numeric"';
				}
			}
			if ( $rules['min_length'] > 0 ) {
				$out .= ' data-ec-min-length="' . esc_attr( $rules['min_length'] ) . '"';
				$out .= ' data-ec-min-length-error="' . esc_attr( self::min_length_message( $rules['min_length'] ) ) . '"';
			}
			if ( $add_max_length && $rules['max_length'] > 0 ) {
				$out .= ' maxlength="' . esc_attr( $rules['max_length'] ) . '"';
			}
			if ( '' !== $rules['placeholder'] ) {
				$placeholder = function_exists( 'wp_easycart_language' ) ? wp_easycart_language()->convert_text( $rules['placeholder'] ) : $rules['placeholder'];
				$out        .= ' placeholder="' . esc_attr( wp_strip_all_tags( $placeholder ) ) . '"';
			}
			return $out;
		}
	}

endif;
