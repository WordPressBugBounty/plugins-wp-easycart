<?php
/**
 * The subscription page's choices in its order summary ( 6.0.5, Matt: the number of tasks and monthly / yearly under the item's title,
 * not at the top of the page; a line that says what was chosen and turns into a list when clicked ). Most customers keep what they
 * chose on the pricing table, so the page stays quiet: "Billed monthly · Change", "750 tasks per month · Change".
 *
 * Each choice comes from filter wp_easycart_subscription_summary_choices ( WP EasyCart's usage level, WP EasyCart PRO's billing period ).
 * A list choice ( type select ) is a GET form whose line is the summary of a <details>: opening it shows the select, and picking another
 * choice opens the page again with it ( a choice made with the arrow keys waits for the Update button; without scripts the select shows
 * with its Update button ). A choice between pages ( type links: the billing period ) is a row of links, drawn as pills or a switch, so
 * one change always opens the page again before the next. Nothing here needs a script to show: the cart page may be drawn by AJAX
 * ( cache prevention, the one-page checkout ), where an inline script does not run as written. A store's copy of
 * ec_cart_subscription.php without the summary's hook keeps the choices at the top of the page ( in_summary() ).
 *
 * @package wp-easycart
 * @since   6.0.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_subscription_choices' ) ) :

	/**
	 * The subscription page's choices.
	 */
	class wp_easycart_subscription_choices {

		/**
		 * The action ec_cart_subscription.php fires under the item in each summary ( product, desktop | mobile ).
		 */
		const HOOK = 'wpeasycart_subscription_summary_choices';

		/**
		 * The choices by product id, for this request.
		 *
		 * @var array
		 */
		private static $lines = array();

		/**
		 * Whether the page in use draws the summary's hook.
		 *
		 * @var bool|null
		 */
		private static $in_summary = null;

		/**
		 * Hooks.
		 */
		public static function init() {
			add_action( self::HOOK, array( __CLASS__, 'print_lines' ), 10, 2 );
		}

		/**
		 * Whether the subscription page in use shows the choices in its summary: WP EasyCart's own template, or a store's copy that
		 * fires the summary's hook. A copy from before 6.0.5 keeps them at the top ( wpeasycart_subscription_page_plan_choices ).
		 *
		 * @return bool
		 */
		public static function in_summary() {
			if ( null === self::$in_summary ) {
				$copy   = ( defined( 'EC_PLUGIN_DATA_DIRECTORY' ) ? EC_PLUGIN_DATA_DIRECTORY : '' ) . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_subscription.php';
				$inside = ! file_exists( $copy ) || false !== strpos( (string) file_get_contents( $copy ), self::HOOK ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local template file.
				/**
				 * Whether the subscription page shows its choices ( billing, the usage level ) in the order summary.
				 *
				 * @since 6.0.5
				 * @param bool $inside In the summary ( false: at the top of the page ).
				 */
				self::$in_summary = (bool) apply_filters( 'wp_easycart_subscription_choices_in_summary', $inside );
			}
			return self::$in_summary;
		}

		/**
		 * A product's choices, in order. Each: key, order, type ( select | links ), label ( what is chosen ), after ( HTML under it ).
		 * A select: text ( the line: what is chosen now ), action ( the page's address ), hidden ( name => value: the address's
		 * arguments ), name ( the select's ), options ( each value, text, selected ). Links: style ( pills | switch; a switch needs two
		 * options ), options ( each text, url, selected, badge ).
		 *
		 * @param object $product ec_product.
		 * @return array[]
		 */
		public static function lines( $product ) {
			if ( ! is_object( $product ) || empty( $product->product_id ) ) {
				return array();
			}
			$id = (int) $product->product_id;
			if ( ! isset( self::$lines[ $id ] ) ) {
				/**
				 * The subscription page's choices under the item in its summary.
				 *
				 * @since 6.0.5
				 * @param array  $lines   Choices ( see lines() ).
				 * @param object $product ec_product.
				 */
				$lines = apply_filters( 'wp_easycart_subscription_summary_choices', array(), $product );
				$clean = array();
				foreach ( (array) $lines as $line ) {
					if ( ! is_array( $line ) || empty( $line['options'] ) || ! is_array( $line['options'] ) ) {
						continue;
					}
					$links = isset( $line['type'] ) && 'links' === $line['type'];
					if ( $links || ( ! empty( $line['name'] ) && isset( $line['text'] ) && ! empty( $line['action'] ) ) ) {
						$clean[] = $line + array(
							'key'    => '',
							'order'  => 10,
							'type'   => 'select',
							'style'  => 'pills',
							'label'  => '',
							'hidden' => array(),
							'after'  => '',
						);
					}
				}
				usort(
					$clean,
					function ( $a, $b ) {
						return (int) $a['order'] - (int) $b['order'];
					}
				);
				self::$lines[ $id ] = $clean;
			}
			return self::$lines[ $id ];
		}

		/**
		 * The item's title in the summary: the product's, or a shorter one while the lines under it say the rest ( a plan group's tier
		 * name in place of "Professional – Monthly · 750 tasks per month" ).
		 *
		 * @param object $product ec_product.
		 * @return string
		 */
		public static function title( $product ) {
			$title = (string) $product->title;
			if ( ! self::lines( $product ) || ! self::in_summary() ) {
				return $title;
			}
			/**
			 * The subscription item's title in the summary while its choices show under it.
			 *
			 * @since 6.0.5
			 * @param string $title   The product's title ( with its level ).
			 * @param object $product ec_product.
			 */
			$short = (string) apply_filters( 'wp_easycart_subscription_summary_title', $title, $product );
			return '' !== trim( $short ) ? $short : $title;
		}

		/**
		 * Action wpeasycart_subscription_summary_choices: the choices under the item.
		 *
		 * @param object $product ec_product.
		 * @param string $copy    desktop | mobile ( the page has two summaries ).
		 */
		public static function print_lines( $product, $copy = 'desktop' ) {
			$lines = self::lines( $product );
			if ( ! $lines ) {
				return;
			}
			$out = '<div class="ec_subscription_picks">';
			foreach ( $lines as $i => $line ) {
				$key  = sanitize_key( '' !== (string) $line['key'] ? $line['key'] : 'c' . $i );
				$out .= ( 'links' === $line['type'] ) ? self::links_html( $line, $key ) : self::select_html( $line, $key, 'ec_subscription_pick_' . $key . '_' . sanitize_key( $copy ) . '_' . (int) $product->product_id );
				$out .= ( '' !== (string) $line['after'] ) ? wp_kses_post( $line['after'] ) : '';
			}
			echo $out . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped as it was built.
		}

		/**
		 * A list choice: a GET form whose line is a <details> summary ( "750 tasks per month · Change" ); open, it shows the select
		 * and its Update button ( ec-store.js opens the list itself, submits a choice and hides the button: .is-scripted ).
		 *
		 * @param array  $line A choice ( lines() ).
		 * @param string $key  Its key.
		 * @param string $id   The select's id.
		 * @return string HTML.
		 */
		private static function select_html( $line, $key, $id ) {
			$out = '<form class="ec_subscription_pick ec_subscription_pick_' . esc_attr( $key ) . '" method="get" action="' . esc_url( $line['action'] ) . '">';
			foreach ( (array) $line['hidden'] as $name => $value ) {
				if ( is_scalar( $value ) && (string) $name !== (string) $line['name'] ) {
					$out .= '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" />';
				}
			}
			$out .= '<details class="ec_subscription_pick_box"><summary class="ec_subscription_pick_line">';
			$out .= ( '' !== (string) $line['label'] ) ? '<span class="ec_subscription_pick_sr">' . esc_html( $line['label'] ) . ': </span>' : '';
			$out .= '<span class="ec_subscription_pick_text">' . esc_html( $line['text'] ) . '</span> <span class="ec_subscription_pick_change">' . esc_html( self::text( 'subscription_email_change', 'Change' ) ) . '</span></summary>';
			$out .= '<span class="ec_subscription_pick_field">';
			$out .= '<label class="ec_subscription_pick_label" for="' . esc_attr( $id ) . '">' . esc_html( $line['label'] ) . '</label>';
			$out .= '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $line['name'] ) . '" class="ec_subscription_pick_select">';
			foreach ( $line['options'] as $option ) {
				$out .= '<option value="' . esc_attr( isset( $option['value'] ) ? $option['value'] : '' ) . '"' . ( ! empty( $option['selected'] ) ? ' selected="selected"' : '' ) . '>' . esc_html( isset( $option['text'] ) ? $option['text'] : '' ) . '</option>';
			}
			$out .= '</select><button type="submit" class="ec_cart_button ec_subscription_pick_go">' . esc_html( self::text( 'subscription_level_update', 'Update' ) ) . '</button></span></details></form>';
			return $out;
		}

		/**
		 * A choice between pages: one link each ( the chosen one is not a link ), as pills, or with two choices as a switch between
		 * them ( Monthly ( switch ) Yearly ), the pricing table's look. One click opens the page with that choice.
		 *
		 * @param array  $line A choice ( lines() ).
		 * @param string $key  Its key.
		 * @return string HTML.
		 */
		private static function links_html( $line, $key ) {
			$options = array_values( $line['options'] );
			$switch  = ( 'switch' === $line['style'] && 2 === count( $options ) );
			$on      = 0;
			foreach ( $options as $i => $option ) {
				$on = ! empty( $option['selected'] ) ? $i : $on;
			}
			$badge = function ( $option ) {
				return ! empty( $option['badge'] ) ? '<span class="ec_subscription_pick_badge">' . esc_html( $option['badge'] ) . '</span>' : '';
			};
			$link  = function ( $option, $inner = '' ) {
				$text = esc_html( isset( $option['text'] ) ? $option['text'] : '' ) . ( '' !== $inner ? ' ' . $inner : '' );
				if ( ! empty( $option['selected'] ) || empty( $option['url'] ) ) {
					return '<span class="ec_subscription_pick_pill is-current" aria-current="true">' . $text . '</span>';
				}
				return '<a class="ec_subscription_pick_pill" href="' . esc_url( $option['url'] ) . '">' . $text . '</a>';
			};
			$out   = '<div class="ec_subscription_pick ec_subscription_pick_' . esc_attr( $key ) . ' ec_subscription_pick_links ec_subscription_pick_links_' . ( $switch ? 'switch' : 'pills' ) . '" role="group"' . ( '' !== (string) $line['label'] ? ' aria-label="' . esc_attr( $line['label'] ) . '"' : '' ) . ' data-on="' . (int) $on . '">';
			if ( $switch ) {
				$other = $options[ 0 === $on ? 1 : 0 ];
				$out  .= $link( $options[0] );
				$out  .= ! empty( $other['url'] ) ? '<a class="ec_subscription_pick_track" href="' . esc_url( $other['url'] ) . '" tabindex="-1" aria-hidden="true"><span></span></a>' : '<span class="ec_subscription_pick_track" aria-hidden="true"><span></span></span>';
				$out  .= $link( $options[1] ) . $badge( $options[1] ) . $badge( $options[0] );
			} else {
				foreach ( $options as $option ) {
					$out .= $link( $option, $badge( $option ) );
				}
			}
			return $out . '</div>';
		}

		/**
		 * A storefront phrase ( cart_login ), else the English.
		 *
		 * @param string $key      Key.
		 * @param string $fallback English.
		 * @return string
		 */
		private static function text( $key, $fallback ) {
			$text = function_exists( 'wp_easycart_language' ) ? (string) wp_easycart_language()->get_text( 'cart_login', $key ) : '';
			return ( '' === trim( $text ) || $key === $text ) ? $fallback : $text;
		}

		/**
		 * The page's address split for a GET form: the address without its query, and the query's arguments.
		 *
		 * @param string $url The page's address.
		 * @return array { action, hidden }.
		 */
		public static function form_of( $url ) {
			$query = array();
			$parts = wp_parse_url( (string) $url );
			if ( isset( $parts['query'] ) ) {
				parse_str( $parts['query'], $query );
			}
			return array(
				'action' => (string) strtok( (string) $url, '?' ),
				'hidden' => $query,
			);
		}
	}

	wp_easycart_subscription_choices::init();

endif;
