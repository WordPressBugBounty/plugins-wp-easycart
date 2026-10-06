<?php
/**
 * The options a subscription is bought with: the checkout session holds what the shopper chose on a product page
 * ( process_subscribe_v3(), or an add to cart link ), and every subscription path reads it from there ( the subscription page,
 * its totals, the Stripe calls, the classic post, the first order ). Before 6.0.3 nothing tied those choices to the product, so a
 * second subscription opened later in the same session showed, priced and billed the first one's options and quantity.
 *
 * The guard, guard( $product ), is asked whenever a subscription path starts, before anything reads the session: the choices must have been
 * made for this product ( ec_tempcart_data.subscription_product_id, EC_UPGRADE_DB 123 ), every basic choice must belong to the
 * option set in its slot and form a variant that is still sold, every modifier must belong to one of the product's option sets,
 * and the required choices must be there. Anything that does not belong is taken out of the session; a subscription that still
 * needs choices is sent back to its product page.
 *
 * @package wp-easycart
 * @since   6.0.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_subscription_options' ) ) :

	/**
	 * Subscription options in the checkout session.
	 */
	class wp_easycart_subscription_options {

		/**
		 * The session column that names the product the choices were made for arrived with this database version.
		 */
		const DB_VERSION = 123;

		/**
		 * The guard()'s answers, per product, for this request.
		 *
		 * @var array
		 */
		private static $answers = array();

		/**
		 * Hooks: the subscription checkout's errors and their wording.
		 */
		public static function init() {
			add_filter( 'wpeasycart_subscription_checkout_errors', array( __CLASS__, 'checkout_errors' ), 5, 2 );
			add_filter( 'wpeasycart_cart_errors', array( __CLASS__, 'error_notes' ) );
		}

		/**
		 * Whether the session can say which product its choices were made for.
		 *
		 * @return bool
		 */
		public static function ready() {
			return (int) get_option( 'ec_option_db_new_version' ) >= self::DB_VERSION;
		}

		/**
		 * The checkout session's data, or null.
		 *
		 * @return object|null
		 */
		private static function session() {
			return ( isset( $GLOBALS['ec_cart_data'] ) && is_object( $GLOBALS['ec_cart_data'] ) && isset( $GLOBALS['ec_cart_data']->cart_data ) && is_object( $GLOBALS['ec_cart_data']->cart_data ) ) ? $GLOBALS['ec_cart_data']->cart_data : null;
		}

		/**
		 * The product the session's choices were made for ( 0 = not known ).
		 *
		 * @return int
		 */
		public static function owner() {
			$data = self::session();
			return ( $data && self::ready() && isset( $data->subscription_product_id ) ) ? (int) $data->subscription_product_id : 0;
		}

		/**
		 * The product page is about to save its choices for this product: a choice of quantity made for another product goes.
		 * Call before the choices are written; the caller saves the session.
		 *
		 * @param int $product_id Product.
		 */
		public static function claim( $product_id ) {
			$data = self::session();
			if ( ! $data ) {
				return;
			}
			if ( self::owner() !== (int) $product_id ) {
				$data->subscription_quantity = '';
			}
			if ( self::ready() ) {
				$data->subscription_product_id = (int) $product_id;
			}
			self::$answers = array();
		}

		/**
		 * Forget every choice ( after a subscription is bought, or when they belong to another product ). The caller saves the session.
		 */
		public static function clear() {
			$data = self::session();
			if ( ! $data ) {
				return;
			}
			for ( $slot = 1; $slot <= 5; $slot++ ) {
				$data->{ 'subscription_option' . $slot } = '';
			}
			$data->subscription_advanced_option = '';
			$data->subscription_quantity        = '';
			if ( self::ready() ) {
				$data->subscription_product_id = 0;
			}
			self::$answers = array();
		}

		/**
		 * The basic choices in the session, slot => option item id ( 0 = none ).
		 *
		 * @return int[]
		 */
		public static function basic() {
			$data  = self::session();
			$slots = array();
			for ( $slot = 1; $slot <= 5; $slot++ ) {
				$slots[ $slot ] = ( $data && isset( $data->{ 'subscription_option' . $slot } ) ) ? (int) $data->{ 'subscription_option' . $slot } : 0;
			}
			return $slots;
		}

		/**
		 * The modifiers in the session.
		 *
		 * @return array[]
		 */
		public static function advanced() {
			$data = self::session();
			if ( ! $data || ! isset( $data->subscription_advanced_option ) || '' === (string) $data->subscription_advanced_option ) {
				return array();
			}
			$list = maybe_unserialize( $data->subscription_advanced_option );
			return is_array( $list ) ? $list : array();
		}

		/**
		 * Make the session's choices this product's own, and say whether it can be bought with them.
		 *
		 * @param object|array $product ec_product, or a product row ( product_id, option_id_1..5, use_advanced_optionset,
		 *                              use_both_option_types ).
		 * @return array {
		 *     @type string $status  ok, or choose ( required choices are missing: send the shopper to the product page ).
		 *     @type bool   $changed The session was changed ( and saved ).
		 *     @type array  $removed What was taken out: other ( chosen for another product ), basic ( slot => item ),
		 *                           variant ( a switched off variant ), advanced ( option item ids ).
		 *     @type int[]  $missing Option set ids still to choose.
		 * }
		 */
		public static function guard( $product ) {
			$product    = (object) $product;
			$product_id = isset( $product->product_id ) ? (int) $product->product_id : 0;
			$answer     = array(
				'status'  => 'ok',
				'changed' => false,
				'removed' => array(),
				'missing' => array(),
			);
			$data       = self::session();
			if ( $product_id <= 0 || ! $data ) {
				return $answer;
			}
			if ( isset( self::$answers[ $product_id ] ) ) {
				return self::$answers[ $product_id ];
			}

			// Chosen for another product: none of it applies here ( the quantity neither ).
			$owner = self::owner();
			if ( $owner > 0 && $owner !== $product_id ) {
				self::clear();
				$answer['changed']          = true;
				$answer['removed']['other'] = $owner;
			}
			$owned = ( self::ready() && self::owner() === $product_id );

			$basic_sets = self::basic_sets( $product );
			$adv_sets   = self::advanced_sets( $product );

			// Basic choices: each in the option set of its slot, together a variant that is still sold.
			$slots = self::basic();
			$items = self::item_sets( array_values( array_filter( $slots ) ) );
			foreach ( $slots as $slot => $item_id ) {
				if ( $item_id > 0 && ( ! isset( $basic_sets[ $slot ] ) || ! isset( $items[ $item_id ] ) || $items[ $item_id ] !== $basic_sets[ $slot ] ) ) {
					$data->{ 'subscription_option' . $slot } = '';
					$answer['removed']['basic'][ $slot ]     = $item_id;
					$slots[ $slot ]                          = 0;
				}
			}
			if ( array_filter( $slots ) && class_exists( 'wp_easycart_variants' ) && method_exists( 'wp_easycart_variants', 'is_disabled' ) && wp_easycart_variants::is_disabled( $product_id, $slots ) ) {
				for ( $slot = 1; $slot <= 5; $slot++ ) {
					$data->{ 'subscription_option' . $slot } = '';
				}
				$answer['removed']['variant'] = $slots;
				$slots                        = array_fill( 1, 5, 0 );
			}

			// Modifiers: each from one of the product's option sets.
			$kept = array();
			foreach ( self::advanced() as $entry ) {
				$option_id = ( is_array( $entry ) && isset( $entry['option_id'] ) ) ? (int) $entry['option_id'] : 0;
				$item_id   = ( is_array( $entry ) && isset( $entry['optionitem_id'] ) ) ? (int) $entry['optionitem_id'] : 0;
				if ( isset( $adv_sets[ $option_id ] ) && ( ! $adv_sets[ $option_id ]['items'] || isset( $adv_sets[ $option_id ]['items'][ $item_id ] ) ) ) {
					$kept[] = $entry;
				} else {
					$answer['removed']['advanced'][] = $item_id;
				}
			}
			if ( ! empty( $answer['removed']['advanced'] ) ) {
				$data->subscription_advanced_option = $kept ? maybe_serialize( $kept ) : '';
			}

			// Required choices: trusted when the product page made them ( it checks them, conditional ones included ), unless
			// something was taken out since; otherwise every required set without conditions needs a choice.
			if ( ! $owned || ! empty( $answer['removed'] ) ) {
				foreach ( $basic_sets as $slot => $option_id ) {
					if ( self::required( $option_id ) && empty( $slots[ $slot ] ) ) {
						$answer['missing'][] = $option_id;
					}
				}
				$chosen = array();
				foreach ( $kept as $entry ) {
					$chosen[ (int) $entry['option_id'] ] = true;
				}
				foreach ( $adv_sets as $option_id => $set ) {
					if ( $set['required'] && ! $set['conditional'] && ! isset( $chosen[ $option_id ] ) ) {
						$answer['missing'][] = $option_id;
					}
				}
			}
			if ( $answer['missing'] ) {
				$answer['status'] = 'choose';
			}

			if ( ! empty( $answer['removed'] ) ) {
				$answer['changed'] = true;
				if ( method_exists( $GLOBALS['ec_cart_data'], 'save_session_to_db' ) ) {
					$GLOBALS['ec_cart_data']->save_session_to_db();
				}
			}

			/**
			 * What guard() found for a subscription product ( after the session was put right ).
			 *
			 * @since 6.0.3
			 * @param array  $answer  status ( ok | choose ), changed, removed, missing.
			 * @param object $product The product.
			 */
			$answer = (array) apply_filters( 'wp_easycart_subscription_options_guard', $answer, $product );

			self::$answers[ $product_id ] = $answer;
			return $answer;
		}

		/**
		 * The product's basic option sets, slot => option set id ( only when the product uses basic options ).
		 *
		 * @param object $product Product.
		 * @return int[]
		 */
		private static function basic_sets( $product ) {
			$sets = array();
			if ( ! empty( $product->use_advanced_optionset ) && empty( $product->use_both_option_types ) ) {
				return $sets;
			}
			for ( $slot = 1; $slot <= 5; $slot++ ) {
				$option_id = isset( $product->{ 'option_id_' . $slot } ) ? (int) $product->{ 'option_id_' . $slot } : 0;
				if ( $option_id > 0 ) {
					$sets[ $slot ] = $option_id;
				}
			}
			return $sets;
		}

		/**
		 * The product's modifier option sets, option set id => required, conditional, items ( only when the product uses them ).
		 *
		 * @param object $product Product.
		 * @return array
		 */
		private static function advanced_sets( $product ) {
			global $wpdb;
			$sets = array();
			if ( empty( $product->use_advanced_optionset ) && empty( $product->use_both_option_types ) ) {
				return $sets;
			}
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT ec_option_to_product.option_id, ec_option_to_product.conditional_logic, ec_option.option_required, ec_optionitem.optionitem_id FROM ec_option_to_product INNER JOIN ec_option ON ec_option.option_id = ec_option_to_product.option_id LEFT JOIN ec_optionitem ON ec_optionitem.option_id = ec_option_to_product.option_id WHERE ec_option_to_product.product_id = %d', (int) $product->product_id ) );
			foreach ( (array) $rows as $row ) {
				$option_id = (int) $row->option_id;
				if ( ! isset( $sets[ $option_id ] ) ) {
					$rules              = json_decode( (string) $row->conditional_logic );
					$sets[ $option_id ] = array(
						'required'    => ! empty( $row->option_required ),
						'conditional' => ( is_object( $rules ) && ! empty( $rules->enabled ) && ! empty( $rules->rules ) ),
						'items'       => array(),
					);
				}
				if ( ! empty( $row->optionitem_id ) ) {
					$sets[ $option_id ]['items'][ (int) $row->optionitem_id ] = true;
				}
			}
			return $sets;
		}

		/**
		 * The option set of each option item, item id => option set id.
		 *
		 * @param int[] $item_ids Option item ids.
		 * @return int[]
		 */
		private static function item_sets( $item_ids ) {
			global $wpdb;
			$item_ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $item_ids ) ) ) );
			if ( ! $item_ids ) {
				return array();
			}
			$rows = $wpdb->get_results( 'SELECT optionitem_id, option_id FROM ec_optionitem WHERE optionitem_id IN ( ' . implode( ',', $item_ids ) . ' )' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- integers only ( intval above ).
			$sets = array();
			foreach ( (array) $rows as $row ) {
				$sets[ (int) $row->optionitem_id ] = (int) $row->option_id;
			}
			return $sets;
		}

		/**
		 * Whether a basic option set needs a choice.
		 *
		 * @param int $option_id Option set.
		 * @return bool
		 */
		private static function required( $option_id ) {
			global $wpdb;
			$required = $wpdb->get_var( $wpdb->prepare( 'SELECT option_required FROM ec_option WHERE option_id = %d', (int) $option_id ) );
			return ( null === $required ) ? false : (bool) (int) $required;
		}

		/**
		 * The subscription checkout's errors: subscription_options when required choices are missing ( a shared subscription link,
		 * or choices that no longer belong to the product ). Both Stripe calls and the classic post ask before anything is charged.
		 *
		 * @param string[] $codes   Codes.
		 * @param object   $product The subscription product.
		 * @return string[]
		 */
		public static function checkout_errors( $codes, $product ) {
			if ( is_object( $product ) ) {
				$answer = self::guard( $product );
				if ( 'choose' === $answer['status'] ) {
					$codes[] = 'subscription_options';
				}
			}
			return $codes;
		}

		/**
		 * The wording of subscription_options.
		 *
		 * @param array $notes Code => text.
		 * @return array
		 */
		public static function error_notes( $notes ) {
			if ( is_array( $notes ) && ! isset( $notes['subscription_options'] ) ) {
				$notes['subscription_options'] = self::text( 'subscription_choose_options', 'Choose the options for this subscription on its product page before you check out.' );
			}
			return $notes;
		}

		/**
		 * A cart_login phrase ( escaped, as the language file gives it ), or its English.
		 *
		 * @param string $key      Phrase.
		 * @param string $fallback English.
		 * @return string
		 */
		public static function text( $key, $fallback ) {
			$text = function_exists( 'wp_easycart_language' ) ? wp_easycart_language()->get_text( 'cart_login', $key ) : null;
			return ( null === $text || '' === trim( (string) $text ) ) ? esc_html( $fallback ) : (string) $text;
		}

		/**
		 * The product page of a product row, for the "choose the options" link.
		 *
		 * @param object|array $product Product.
		 * @return string
		 */
		public static function product_url( $product ) {
			$product  = (object) $product;
			$store    = get_option( 'ec_option_storepage' ) ? get_permalink( get_option( 'ec_option_storepage' ) ) : home_url( '/' );
			$fallback = add_query_arg( 'model_number', isset( $product->model_number ) ? (string) $product->model_number : '', $store );
			return function_exists( 'wp_easycart_store_post_link' ) ? wp_easycart_store_post_link( isset( $product->post_id ) ? (int) $product->post_id : 0, $fallback ) : $fallback;
		}

		/**
		 * What the subscription page prints instead of its form while choices are missing.
		 *
		 * @param object|array $product Product.
		 */
		public static function print_choose_notice( $product ) {
			echo '<div class="ec_subscription_choose_options" role="note"><p>' . wp_kses_post( self::text( 'subscription_choose_options', 'Choose the options for this subscription on its product page before you check out.' ) ) . '</p><a class="ec_subscription_choose_options_link" href="' . esc_url( self::product_url( $product ) ) . '">' . wp_kses_post( self::text( 'subscription_choose_options_link', 'Choose options' ) ) . '</a></div>';
		}
	}

	wp_easycart_subscription_options::init();

endif;
