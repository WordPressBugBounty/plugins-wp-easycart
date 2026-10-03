<?php
/**
 * WP EasyCart — checkout shipping for lines a fulfillment partner ships ( 6.0.2 ).
 *
 * A shippable cart line belongs to a partner's group when its product's fulfillment_provider is an active fulfillment
 * provider ( wp_easycart_fulfillment::is_active() ). Those lines leave the store's shipping: the cart's shipping subtotal,
 * weight and parcel, store handling and per-product handling, and the carriers' quote ( WP EasyCart PRO ). They still
 * count in the cart's shippable_total_items, so checkout asks for a shipping address.
 *
 * The partner prices its own shipping through the filter wp_easycart_shipping_group_quote ( then
 * wp_easycart_shipping_group_fallback when that gives nothing and the shopper's country is known ), answered once per
 * request for each request.
 *
 * - Mixed cart ( the store ships something too ): each group's share, its default service's rate, is added to every store
 *   rate shown and to the charged shipping, in every shipping mode and both checkouts. The store's free shipping ( a rate
 *   row's free-shipping threshold, free-shipping promotions, a free-shipping coupon, an account with free shipping ) only
 *   waives the store's part, unless ec_option_free_shipping_covers_partners is on. WP EasyCart PRO's Offers never waive a
 *   partner's share, and partial shipping discounts never reach it.
 * - All-partner cart ( the store ships nothing ): the choices are the first quoted group's services ( the other groups'
 *   default rates added ), whatever the store's shipping mode. The choice is saved as group:<provider>:<code>.
 * - No country yet: no quote, nothing added, and a note says partner shipping is added once the address is entered. Once
 *   the checkout holds its own shipping address, the partner is quoted for it ( never the cart estimate's or the account's
 *   state and postal code ), and an order is never placed without a shipping country ( blocks_checkout( $cartpage, true ) ).
 * - No answer for a known country: checkout stops with shipping_group_unavailable, the cart page wallets ( Stripe, Square )
 *   refuse before they charge, and a Stripe payment already taken makes its order on hold ( hold_order() ).
 * - The store's shipping switched off ( Settings › Shipping ): nothing is quoted, so a cart with lines a connected partner
 *   ships stops the same way ( shipping_off_groups() ); carts without such lines are untouched.
 *
 * The order keeps what was chosen in ec_order.shipping_groups ( JSON, one entry per provider; for_order() ), written by
 * ec_db::insert_order() so every checkout path records it.
 *
 * @package WP_EasyCart
 * @since   6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_shipping_groups' ) ) :

	/**
	 * Partner shipping groups at checkout.
	 */
	class wp_easycart_shipping_groups {

		/**
		 * Option: the store's free shipping also waives partner shipping ( default off ).
		 */
		const COVERS_OPTION = 'ec_option_free_shipping_covers_partners';

		/**
		 * Prefix of a partner service saved as the checkout's shipping method ( group:<provider>:<code> ).
		 */
		const PREFIX = 'group:';

		/**
		 * Partner answers for this request, md5 of the request => normalised answer, WP_Error or null.
		 *
		 * @var array
		 */
		private static $answers = array();

		/**
		 * Product id => fulfillment_provider, read once per request for lines that do not carry it.
		 *
		 * @var array
		 */
		private static $product_providers = array();

		/**
		 * Provider => code => service label, from every answer seen this request ( names the saved shipping method ).
		 *
		 * @var array
		 */
		private static $service_labels = array();

		/**
		 * What partner shipping added to the last charged price of a cart, cart key => provider => amount.
		 *
		 * @var array
		 */
		private static $charged = array();

		/**
		 * The context of the last main cart shipping was built for ( the cart update response reads it ).
		 *
		 * @var array|null
		 */
		private static $last = null;

		/**
		 * Hooks.
		 */
		public static function init() {
			add_filter( 'wpeasycart_checkout_order_errors', array( __CLASS__, 'checkout_errors' ), 20, 2 );
			add_filter( 'wpeasycart_checkout_step_error', array( __CLASS__, 'checkout_step_error' ), 20, 3 );
			add_filter( 'wpeasycart_cart_errors', array( __CLASS__, 'cart_error_notes' ) );
			add_filter( 'wpeasycart_valid_cart_errors', array( __CLASS__, 'valid_cart_errors' ) );
			add_filter( 'wpeasycart_shipping_method_name', array( __CLASS__, 'method_name' ), 10, 2 );
			add_filter( 'wp_easycart_cart_update_response', array( __CLASS__, 'cart_update_response' ) );
		}

		/**
		 * The fulfillment columns exist and wp_easycart_fulfillment can say which providers are active.
		 *
		 * @return bool
		 */
		public static function ready() {
			return class_exists( 'wp_easycart_fulfillment' ) && method_exists( 'wp_easycart_fulfillment', 'ready' ) && method_exists( 'wp_easycart_fulfillment', 'is_active' ) && wp_easycart_fulfillment::ready();
		}

		/**
		 * The store's free shipping also covers partner shipping.
		 *
		 * @return bool
		 */
		public static function covers() {
			return (bool) get_option( self::COVERS_OPTION );
		}

		/**
		 * A provider's name.
		 *
		 * @param string $slug Provider.
		 * @return string
		 */
		public static function label( $slug ) {
			if ( class_exists( 'wp_easycart_fulfillment' ) && method_exists( 'wp_easycart_fulfillment', 'label' ) ) {
				$label = (string) wp_easycart_fulfillment::label( $slug );
				if ( '' !== $label ) {
					return $label;
				}
			}
			return ucfirst( (string) $slug );
		}

		/**
		 * Forget this request's answers and product lookups ( a product's provider changed, or tests ).
		 */
		public static function forget() {
			self::$answers           = array();
			self::$product_providers = array();
			self::$service_labels    = array();
			self::$charged           = array();
			self::$last              = null;
		}

		/**
		 * Active providers, slug => true. Empty before the database update, and when no partner is connected, so a store
		 * without partners never reads anything.
		 *
		 * @return array
		 */
		private static function active_providers() {
			if ( ! self::ready() ) {
				return array();
			}
			$active = array();
			if ( method_exists( 'wp_easycart_fulfillment', 'providers' ) ) {
				foreach ( (array) wp_easycart_fulfillment::providers() as $slug => $provider ) {
					$slug = (string) $slug;
					if ( '' !== $slug && wp_easycart_fulfillment::is_active( $slug ) ) {
						$active[ $slug ] = true;
					}
				}
			}
			return $active;
		}

		/**
		 * Whether a cart item can be claimed: a shippable ec_cartitem ( subscription products and stand-in lines never are ).
		 *
		 * @param mixed $item Cart item.
		 * @return bool
		 */
		private static function is_cart_line( $item ) {
			return ( $item instanceof ec_cartitem ) && ! empty( $item->is_shippable ) && ! empty( $item->product_id );
		}

		/**
		 * Cart lines a partner ships.
		 *
		 * @param array $cart_items ec_cartitem objects.
		 * @return array Index in $cart_items => provider slug.
		 */
		public static function claimed( $cart_items ) {
			$claimed = array();
			if ( ! is_array( $cart_items ) || empty( $cart_items ) ) {
				return $claimed;
			}
			$active = self::active_providers();
			if ( empty( $active ) ) {
				return $claimed;
			}
			$missing = array();
			foreach ( $cart_items as $index => $item ) {
				if ( ! self::is_cart_line( $item ) ) {
					continue;
				}
				if ( isset( $item->fulfillment_provider ) && is_scalar( $item->fulfillment_provider ) ) {
					$slug = (string) $item->fulfillment_provider;
					if ( '' !== $slug && isset( $active[ $slug ] ) ) {
						$claimed[ $index ] = $slug;
					}
				} else {
					$missing[ $index ] = (int) $item->product_id;
				}
			}
			if ( $missing ) {
				$map = self::product_providers( $missing );
				foreach ( $missing as $index => $product_id ) {
					if ( isset( $map[ $product_id ] ) && isset( $active[ $map[ $product_id ] ] ) ) {
						$claimed[ $index ] = $map[ $product_id ];
					}
				}
				ksort( $claimed );
			}
			return $claimed;
		}

		/**
		 * The providers of products, read once per request.
		 *
		 * @param int[] $product_ids Products.
		 * @return array Product id => provider slug ( products with one only ).
		 */
		private static function product_providers( $product_ids ) {
			global $wpdb;
			$product_ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $product_ids ) ) ) );
			$need        = array_values( array_diff( $product_ids, array_keys( self::$product_providers ) ) );
			if ( $need ) {
				foreach ( $need as $product_id ) {
					self::$product_providers[ $product_id ] = '';
				}
				$placeholders = implode( ', ', array_fill( 0, count( $need ), '%d' ) );
				$rows         = $wpdb->get_results( $wpdb->prepare( "SELECT product_id, fulfillment_provider FROM ec_product WHERE product_id IN ( $placeholders )", $need ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $placeholders is a list of %d built above, one per id.
				foreach ( (array) $rows as $row ) {
					self::$product_providers[ (int) $row->product_id ] = (string) $row->fulfillment_provider;
				}
			}
			$out = array();
			foreach ( $product_ids as $product_id ) {
				if ( isset( self::$product_providers[ $product_id ] ) && '' !== self::$product_providers[ $product_id ] ) {
					$out[ $product_id ] = self::$product_providers[ $product_id ];
				}
			}
			return $out;
		}

		/**
		 * The cart's partner groups.
		 *
		 * @param array $cart_items ec_cartitem objects.
		 * @return array Provider slug => array( 'provider', 'label', 'lines' => cart items ), in cart order.
		 */
		public static function for_cart( $cart_items ) {
			$groups = array();
			foreach ( self::claimed( $cart_items ) as $index => $slug ) {
				if ( ! isset( $groups[ $slug ] ) ) {
					$groups[ $slug ] = array(
						'provider' => $slug,
						'label'    => self::label( $slug ),
						'lines'    => array(),
					);
				}
				$groups[ $slug ]['lines'][] = $cart_items[ $index ];
			}
			return $groups;
		}

		/**
		 * The lines the store ships itself ( every line that is not claimed ), reindexed.
		 *
		 * @param array $cart_items Cart items.
		 * @return array
		 */
		public static function store_lines( $cart_items ) {
			if ( ! is_array( $cart_items ) ) {
				return array();
			}
			$claimed = self::claimed( $cart_items );
			if ( ! $claimed ) {
				return array_values( $cart_items );
			}
			return array_values( array_diff_key( $cart_items, $claimed ) );
		}

		/**
		 * Whether the store ships any of these lines ( shippable, not excluded from shipping, not claimed by a partner ).
		 *
		 * @param array $cart_items Cart items.
		 * @return bool
		 */
		public static function store_ships( $cart_items ) {
			foreach ( self::store_lines( $cart_items ) as $item ) {
				if ( is_object( $item ) && ! empty( $item->is_shippable ) && empty( $item->exclude_shippable_calculation ) ) {
					return true;
				}
			}
			return false;
		}

		/**
		 * The shopper's delivery address. Once the checkout holds a shipping country, the country, state and postal code are
		 * the checkout's own ( a cart estimate's or the account's never stand in for a missing state or postal code ); before
		 * that they are the ones the store's shipping uses ( the cart estimate's, else the account's ). The rest comes from the
		 * checkout.
		 *
		 * @param string $country Country.
		 * @param string $state   State.
		 * @param string $zip     Postal code.
		 * @return array country, state, zip, city, address1, address2, phone, name.
		 */
		public static function address( $country = '', $state = '', $zip = '' ) {
			$cart_data = ( isset( $GLOBALS['ec_cart_data'] ) && is_object( $GLOBALS['ec_cart_data'] ) && isset( $GLOBALS['ec_cart_data']->cart_data ) ) ? $GLOBALS['ec_cart_data']->cart_data : null;
			$get       = function ( $key ) use ( $cart_data ) {
				return ( is_object( $cart_data ) && isset( $cart_data->{ $key } ) && is_scalar( $cart_data->{ $key } ) ) ? trim( (string) $cart_data->{ $key } ) : '';
			};
			if ( '' !== self::order_country() ) {
				$country = $get( 'shipping_country' );
				$state   = $get( 'shipping_state' );
				$zip     = $get( 'shipping_zip' );
			}
			$country = trim( (string) $country );
			return array(
				'country'  => ( '0' === $country ) ? '' : strtoupper( $country ),
				'state'    => trim( (string) $state ),
				'zip'      => trim( (string) $zip ),
				'city'     => $get( 'shipping_city' ),
				'address1' => $get( 'shipping_address_line_1' ),
				'address2' => $get( 'shipping_address_line_2' ),
				'phone'    => $get( 'shipping_phone' ),
				'name'     => trim( $get( 'shipping_first_name' ) . ' ' . $get( 'shipping_last_name' ) ),
			);
		}

		/**
		 * The shipping country the checkout itself holds ( the order's address ), upper case; '' when it has none. Never the
		 * cart estimate's or the account's, which the store's shipping falls back to.
		 *
		 * @return string
		 */
		public static function order_country() {
			$cart_data = ( isset( $GLOBALS['ec_cart_data'] ) && is_object( $GLOBALS['ec_cart_data'] ) && isset( $GLOBALS['ec_cart_data']->cart_data ) && is_object( $GLOBALS['ec_cart_data']->cart_data ) ) ? $GLOBALS['ec_cart_data']->cart_data : null;
			$country   = ( $cart_data && isset( $cart_data->shipping_country ) && is_scalar( $cart_data->shipping_country ) ) ? strtoupper( trim( (string) $cart_data->shipping_country ) ) : '';
			return ( '0' === $country ) ? '' : $country;
		}

		/**
		 * The quote request for a group.
		 *
		 * @param string $provider Provider slug.
		 * @param array  $lines    The group's ec_cartitem objects.
		 * @param array  $address  See address().
		 * @return array
		 */
		public static function request( $provider, $lines, $address ) {
			$request_lines = array();
			$subtotal      = 0.0;
			foreach ( (array) $lines as $item ) {
				$ids = array();
				for ( $slot = 1; $slot <= 5; $slot++ ) {
					$ids[] = isset( $item->{ 'optionitem' . $slot . '_id' } ) ? (int) $item->{ 'optionitem' . $slot . '_id' } : 0;
				}
				$total           = isset( $item->total_price ) ? (float) $item->total_price : 0.0;
				$subtotal       += $total;
				$request_lines[] = array(
					'product_id'            => (int) $item->product_id,
					'optionitem_ids'        => $ids,
					'optionitemquantity_id' => self::variant_id( $item, $ids ),
					'quantity'              => isset( $item->quantity ) ? (int) $item->quantity : 1,
					'unit_price'            => isset( $item->unit_price ) ? (float) $item->unit_price : 0.0,
					'total_price'           => $total,
					'model_number'          => isset( $item->model_number ) ? (string) $item->model_number : '',
					'title'                 => isset( $item->title ) ? wp_strip_all_tags( (string) $item->title ) : '',
				);
			}
			return array(
				'provider' => (string) $provider,
				'currency' => (string) get_option( 'ec_option_base_currency' ),
				'subtotal' => round( $subtotal, 2 ),
				'address'  => $address,
				'lines'    => $request_lines,
			);
		}

		/**
		 * The variant row a line is ( the line's own when it carries one, else looked up ).
		 *
		 * @param object $item Cart item.
		 * @param int[]  $ids  Option item ids by slot.
		 * @return int
		 */
		private static function variant_id( $item, $ids ) {
			if ( isset( $item->optionitemquantity_id ) && (int) $item->optionitemquantity_id > 0 ) {
				return (int) $item->optionitemquantity_id;
			}
			if ( array_sum( $ids ) > 0 && class_exists( 'wp_easycart_variants' ) && method_exists( 'wp_easycart_variants', 'find' ) ) {
				$row = wp_easycart_variants::find( (int) $item->product_id, $ids );
				if ( $row && isset( $row->optionitemquantity_id ) ) {
					return (int) $row->optionitemquantity_id;
				}
			}
			return 0;
		}

		/**
		 * A partner's answer for a request: its quote, else its fallback ( when the country is known ). Asked once per
		 * request for the same request.
		 *
		 * @param string $provider Provider slug.
		 * @param array  $request  See request().
		 * @return array|WP_Error|null Normalised answer ( services by code, default, note, source partner | fallback ).
		 */
		public static function quote( $provider, $request ) {
			$key = md5( (string) wp_json_encode( $request ) );
			if ( array_key_exists( $key, self::$answers ) ) {
				return self::$answers[ $key ];
			}
			/**
			 * A fulfillment partner's shipping services for its lines in the cart.
			 *
			 * @since 6.0.2
			 * @param array|WP_Error|null $answer   Null. Answer array( 'services' => array( array( 'code', 'label', 'rate' ( float, store
			 *                                      currency ), 'min_days', 'max_days' ) ), 'default' => code, 'note' => optional ).
			 * @param string              $provider Provider slug.
			 * @param array               $request  provider, currency, subtotal, address ( country, state, zip, city, address1,
			 *                                      address2, phone, name ), lines ( product_id, optionitem_ids, optionitemquantity_id,
			 *                                      quantity, unit_price, total_price, model_number, title ).
			 */
			$answer = self::ask( 'wp_easycart_shipping_group_quote', $provider, $request );
			if ( ! is_array( $answer ) && isset( $request['address']['country'] ) && '' !== (string) $request['address']['country'] ) {
				/**
				 * Shipping for a partner's lines when the partner gave nothing ( e.g. a flat rate the store sets ).
				 *
				 * @since 6.0.2
				 * @param array|WP_Error|null $answer   Null. Same shape as wp_easycart_shipping_group_quote.
				 * @param string              $provider Provider slug.
				 * @param array               $request  See wp_easycart_shipping_group_quote.
				 */
				$fallback = self::ask( 'wp_easycart_shipping_group_fallback', $provider, $request );
				if ( is_array( $fallback ) ) {
					$fallback['source'] = 'fallback';
					$answer             = $fallback;
				} elseif ( null === $answer ) {
					$answer = $fallback;
				}
			}
			self::$answers[ $key ] = $answer;
			return $answer;
		}

		/**
		 * Ask one filter; a listener that throws counts as an error.
		 *
		 * @param string $filter   Filter.
		 * @param string $provider Provider slug.
		 * @param array  $request  Request.
		 * @return array|WP_Error|null
		 */
		private static function ask( $filter, $provider, $request ) {
			try {
				$raw = apply_filters( $filter, null, $provider, $request ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- wp_easycart_shipping_group_quote or _fallback, documented in quote().
			} catch ( \Throwable $e ) {
				$raw = new WP_Error( 'shipping_group_quote', $e->getMessage() );
			}
			return self::normalize( $raw, $provider );
		}

		/**
		 * A partner's answer, cleaned: services keyed by code ( codes keep letters, digits, _ - . ), rates 0 or more, the
		 * default one of them ( the first service when it names none ).
		 *
		 * @param mixed  $raw      Answer.
		 * @param string $provider Provider slug.
		 * @return array|WP_Error|null
		 */
		private static function normalize( $raw, $provider ) {
			if ( is_wp_error( $raw ) ) {
				return $raw;
			}
			if ( ! is_array( $raw ) || empty( $raw['services'] ) || ! is_array( $raw['services'] ) ) {
				return null;
			}
			$services = array();
			foreach ( $raw['services'] as $service ) {
				$service = (array) $service;
				if ( ! isset( $service['code'], $service['rate'] ) || ! is_numeric( $service['rate'] ) ) {
					continue;
				}
				$code = self::clean_code( $service['code'] );
				if ( '' === $code || isset( $services[ $code ] ) ) {
					continue;
				}
				$label             = isset( $service['label'] ) ? trim( wp_strip_all_tags( (string) $service['label'] ) ) : '';
				$services[ $code ] = array(
					'code'     => $code,
					'label'    => ( '' !== $label ) ? $label : $code,
					'rate'     => max( 0.0, (float) $service['rate'] ),
					'min_days' => self::days( isset( $service['min_days'] ) ? $service['min_days'] : null ),
					'max_days' => self::days( isset( $service['max_days'] ) ? $service['max_days'] : null ),
				);
				self::$service_labels[ (string) $provider ][ $code ] = $services[ $code ]['label'];
			}
			if ( ! $services ) {
				return null;
			}
			$default = isset( $raw['default'] ) ? self::clean_code( $raw['default'] ) : '';
			if ( ! isset( $services[ $default ] ) ) {
				reset( $services );
				$default = (string) key( $services );
			}
			return array(
				'services' => $services,
				'default'  => $default,
				'note'     => isset( $raw['note'] ) ? trim( wp_strip_all_tags( (string) $raw['note'] ) ) : '',
				'source'   => 'partner',
			);
		}

		/**
		 * A service code as it may appear in a shipping method id.
		 *
		 * @param mixed $code Code.
		 * @return string
		 */
		private static function clean_code( $code ) {
			return is_scalar( $code ) ? substr( preg_replace( '/[^A-Za-z0-9_\-\.]/', '', (string) $code ), 0, 64 ) : '';
		}

		/**
		 * Days, or null when not given.
		 *
		 * @param mixed $days Days.
		 * @return int|null
		 */
		private static function days( $days ) {
			return ( is_numeric( $days ) && (int) $days >= 0 ) ? (int) $days : null;
		}

		/**
		 * Partner shipping for a cart the store's shipping is being worked out for. Null when no line is claimed, which is
		 * every cart of a store without an active partner.
		 *
		 * @param array  $cart_items Cart items ( ec_shipping's cart ).
		 * @param string $country    Destination country ( ec_shipping's ).
		 * @param string $state      Destination state.
		 * @param string $zip        Destination postal code.
		 * @return array|null groups, claimed ( index => slug ), store_items, all_partner, partner_subtotal, address, pending,
		 *                    quotes ( slug => answer ), failed ( slug => message ), primary ( all-partner: the group whose services
		 *                    are the choices ), selected ( its chosen code ), key, charged.
		 */
		public static function for_shipping( $cart_items, $country = '', $state = '', $zip = '' ) {
			$main    = self::has_cart_lines( $cart_items );
			$claimed = self::claimed( $cart_items );
			if ( ! $claimed ) {
				if ( $main ) {
					self::$last = null;
				}
				self::normalize_selection( null );
				return null;
			}
			$groups           = self::for_cart( $cart_items );
			$store_items      = 0;
			$partner_subtotal = 0.0;
			foreach ( $cart_items as $index => $item ) {
				if ( isset( $claimed[ $index ] ) ) {
					$partner_subtotal += isset( $item->total_price ) ? (float) $item->total_price : 0.0;
				} elseif ( is_object( $item ) && ! empty( $item->is_shippable ) && empty( $item->exclude_shippable_calculation ) ) {
					$store_items += isset( $item->quantity ) ? (int) $item->quantity : 1;
				}
			}
			$address = self::address( $country, $state, $zip );
			$context = array(
				'groups'           => $groups,
				'claimed'          => $claimed,
				'store_items'      => $store_items,
				'all_partner'      => ( 0 === $store_items ),
				'partner_subtotal' => $partner_subtotal,
				'address'          => $address,
				'pending'          => ( '' === $address['country'] ),
				'order_country'    => self::order_country(),
				'quotes'           => array(),
				'failed'           => array(),
				'primary'          => '',
				'selected'         => '',
				'key'              => self::cart_key( $cart_items, $claimed ),
				'charged'          => null,
			);
			if ( ! $context['pending'] ) {
				foreach ( $groups as $slug => $group ) {
					$answer = self::quote( $slug, self::request( $slug, $group['lines'], $address ) );
					if ( is_array( $answer ) ) {
						$context['quotes'][ $slug ] = $answer;
					} else {
						$context['failed'][ $slug ] = is_wp_error( $answer ) ? (string) $answer->get_error_message() : '';
					}
				}
			}
			if ( $context['all_partner'] && $context['quotes'] ) {
				reset( $context['quotes'] );
				$context['primary']  = (string) key( $context['quotes'] );
				$context['selected'] = self::pick( $context );
			}
			self::normalize_selection( $context );
			if ( $main ) {
				self::$last = $context;
			}
			return $context;
		}

		/**
		 * Whether the items are a real cart ( ec_cartitem objects ), not a subscription product or a stand-in.
		 *
		 * @param array $cart_items Items.
		 * @return bool
		 */
		private static function has_cart_lines( $cart_items ) {
			foreach ( (array) $cart_items as $item ) {
				if ( $item instanceof ec_cartitem ) {
					return true;
				}
			}
			return false;
		}

		/**
		 * The saved shipping method this request works with: a partner service is only kept while the cart is all-partner
		 * ( the default one when the saved choice is not one of its services ), and is dropped once the store ships again.
		 * Changes the session in memory only; the checkout saves it with the next save.
		 *
		 * @param array|null $context See for_shipping().
		 */
		public static function normalize_selection( $context ) {
			if ( ! isset( $GLOBALS['ec_cart_data'] ) || ! is_object( $GLOBALS['ec_cart_data'] ) || ! isset( $GLOBALS['ec_cart_data']->cart_data ) || ! is_object( $GLOBALS['ec_cart_data']->cart_data ) ) {
				return;
			}
			$saved = isset( $GLOBALS['ec_cart_data']->cart_data->shipping_method ) ? (string) $GLOBALS['ec_cart_data']->cart_data->shipping_method : '';
			if ( is_array( $context ) && $context['all_partner'] ) {
				if ( '' !== $context['primary'] ) {
					$GLOBALS['ec_cart_data']->cart_data->shipping_method = self::method_id( $context['primary'], $context['selected'] );
				}
				return;
			}
			if ( 0 === strpos( $saved, self::PREFIX ) ) {
				$GLOBALS['ec_cart_data']->cart_data->shipping_method = '';
			}
		}

		/**
		 * The primary group's chosen service code: the saved choice when it is one of its services, else its default.
		 *
		 * @param array $context See for_shipping().
		 * @return string
		 */
		private static function pick( $context ) {
			$answer = $context['quotes'][ $context['primary'] ];
			$parsed = self::parse_method_id( isset( $GLOBALS['ec_cart_data']->cart_data->shipping_method ) ? $GLOBALS['ec_cart_data']->cart_data->shipping_method : '' );
			if ( $parsed && $parsed['provider'] === $context['primary'] && isset( $answer['services'][ $parsed['code'] ] ) ) {
				return $parsed['code'];
			}
			return $answer['default'];
		}

		/**
		 * The shipping method id of a partner service.
		 *
		 * @param string $provider Provider slug.
		 * @param string $code     Service code.
		 * @return string
		 */
		public static function method_id( $provider, $code ) {
			return self::PREFIX . $provider . ':' . $code;
		}

		/**
		 * A group:<provider>:<code> id, split.
		 *
		 * @param mixed $method_id Shipping method id.
		 * @return array|null provider, code.
		 */
		public static function parse_method_id( $method_id ) {
			if ( ! is_scalar( $method_id ) || 0 !== strpos( (string) $method_id, self::PREFIX ) ) {
				return null;
			}
			$parts = explode( ':', substr( (string) $method_id, strlen( self::PREFIX ) ), 2 );
			if ( 2 !== count( $parts ) || '' === $parts[0] || '' === $parts[1] ) {
				return null;
			}
			return array(
				'provider' => $parts[0],
				'code'     => $parts[1],
			);
		}

		/**
		 * A key for a cart's claimed lines ( the same line objects give the same key, wherever the cart is read from ).
		 *
		 * @param array $cart_items Cart items.
		 * @param array $claimed    See claimed().
		 * @return string
		 */
		private static function cart_key( $cart_items, $claimed ) {
			$hashes = array();
			foreach ( array_keys( $claimed ) as $index ) {
				$hashes[] = is_object( $cart_items[ $index ] ) ? spl_object_hash( $cart_items[ $index ] ) : (string) $index;
			}
			return md5( implode( '|', $hashes ) );
		}

		/**
		 * The service a group ships with ( the chosen one for the primary group of an all-partner cart, else its default ).
		 *
		 * @param array  $context See for_shipping().
		 * @param string $slug    Provider.
		 * @return array|null code, label, rate, min_days, max_days.
		 */
		public static function service( $context, $slug ) {
			if ( ! isset( $context['quotes'][ $slug ] ) ) {
				return null;
			}
			$answer = $context['quotes'][ $slug ];
			$code   = ( $slug === $context['primary'] && '' !== $context['selected'] ) ? $context['selected'] : $answer['default'];
			return isset( $answer['services'][ $code ] ) ? $answer['services'][ $code ] : null;
		}

		/**
		 * What each group adds.
		 *
		 * @param array $context See for_shipping().
		 * @param bool  $waived  The store's free shipping covers partner shipping here.
		 * @return array Provider => amount.
		 */
		public static function amounts( $context, $waived = false ) {
			$out = array();
			foreach ( array_keys( $context['groups'] ) as $slug ) {
				$service      = self::service( $context, $slug );
				$out[ $slug ] = ( $waived || ! $service ) ? 0.0 : (float) $service['rate'];
			}
			return $out;
		}

		/**
		 * What partner shipping adds to each store rate ( mixed cart ), or to each of the primary group's services
		 * ( all-partner cart: the other groups ).
		 *
		 * @param array|null $context See for_shipping().
		 * @param bool       $waived  The store's free shipping covers partner shipping here.
		 * @return float
		 */
		public static function share( $context, $waived = false ) {
			if ( ! is_array( $context ) || $waived ) {
				return 0.0;
			}
			$share = 0.0;
			foreach ( self::amounts( $context ) as $slug => $amount ) {
				if ( $slug !== $context['primary'] ) {
					$share += $amount;
				}
			}
			return $share;
		}

		/**
		 * Remember what partner shipping added to a cart's charged price ( a coupon's shipping discount and the order record
		 * read it ).
		 *
		 * @param array $context See for_shipping().
		 * @param array $amounts Provider => amount.
		 */
		public static function remember( $context, $amounts ) {
			self::$charged[ $context['key'] ] = $amounts;
			if ( is_array( self::$last ) && self::$last['key'] === $context['key'] ) {
				self::$last['charged'] = $amounts;
			}
		}

		/**
		 * The store's shipping subtotal less its part of the cart's discount ( the discount spread over the store's and the
		 * partners' lines by value ), for the store's rate tables and free-shipping thresholds.
		 *
		 * @param array $context  See for_shipping().
		 * @param float $subtotal The store's shipping subtotal.
		 * @param float $discount The cart's discount.
		 * @return float
		 */
		public static function store_subtotal( $context, $subtotal, $discount ) {
			$subtotal = (float) $subtotal;
			$whole    = $subtotal + (float) $context['partner_subtotal'];
			if ( $whole <= 0 ) {
				return $subtotal - (float) $discount;
			}
			return $subtotal - ( (float) $discount * $subtotal / $whole );
		}

		/**
		 * Whether the store gives free shipping to this cart as a whole: a free-shipping promotion ( price, weight, quantity
		 * and percentage tables, and every all-partner cart ), a shipping promotion that takes all of it, or a free-shipping
		 * coupon. A rate row's own threshold, the account's free shipping and a chosen free promotion are for ec_shipping to add.
		 *
		 * @param array $cart_items         Cart items.
		 * @param bool  $table_mode         A free-shipping promotion frees the whole cart ( price, weight, quantity, percentage ).
		 * @param mixed $coupon             ec_discount of the checkout, or false.
		 * @param bool  $include_promotions Promotions count.
		 * @return bool
		 */
		public static function store_free( $cart_items, $table_mode, $coupon = false, $include_promotions = true ) {
			if ( $coupon && self::coupon_frees_shipping( $coupon ) ) {
				return true;
			}
			if ( ! $include_promotions || ! class_exists( 'ec_promotion' ) ) {
				return false;
			}
			$lines     = is_array( $cart_items ) ? $cart_items : array();
			$promotion = new ec_promotion();
			if ( $table_mode && $promotion->has_free_shipping_promotion( $lines ) ) {
				return true;
			}
			$subtotal = 0.0;
			foreach ( $lines as $item ) {
				$price     = isset( $item->unit_price ) ? $item->unit_price : ( isset( $item->price ) ? $item->price : 0 );
				$subtotal += (float) $price * ( isset( $item->quantity ) ? (float) $item->quantity : 1 );
			}
			$probe = 1000000.0;
			$text  = '';
			return (float) $promotion->get_shipping_discounts( $subtotal, $probe, $text ) >= $probe;
		}

		/**
		 * The checkout's coupon gives free shipping ( a matched shipping coupon with no amount ).
		 *
		 * @param mixed $discount ec_discount.
		 * @return bool
		 */
		private static function coupon_frees_shipping( $discount ) {
			if ( ! is_object( $discount ) || empty( $discount->coupon_code ) || empty( $discount->coupon_matches ) || ! isset( $GLOBALS['ec_coupons'] ) || ! is_object( $GLOBALS['ec_coupons'] ) ) {
				return false;
			}
			$row = $GLOBALS['ec_coupons']->redeem_coupon_code( $discount->coupon_code );
			return $row && ! empty( $row->is_shipping_based ) && 0.0 === (float) $row->promo_shipping;
		}

		/**
		 * The shipping a shipping coupon may discount ( ec_discount ): the store's part of the charged shipping, or all of it
		 * when the coupon is free shipping and free shipping covers partners.
		 *
		 * @param ec_cart|array $cart          The cart.
		 * @param float         $shipping      Charged shipping.
		 * @param object        $promocode_row The coupon.
		 * @return float
		 */
		public static function coupon_shipping_base( $cart, $shipping, $promocode_row ) {
			$items   = ( is_object( $cart ) && isset( $cart->cart ) ) ? $cart->cart : ( is_array( $cart ) ? $cart : array() );
			$claimed = self::claimed( $items );
			if ( ! $claimed ) {
				return $shipping;
			}
			$key = self::cart_key( $items, $claimed );
			if ( ! isset( self::$charged[ $key ] ) ) {
				return $shipping;
			}
			$partner = array_sum( self::$charged[ $key ] );
			if ( $partner <= 0 ) {
				return $shipping;
			}
			if ( is_object( $promocode_row ) && 0.0 === (float) $promocode_row->promo_shipping && self::covers() ) {
				return $shipping;
			}
			return max( 0.0, (float) $shipping - $partner );
		}

		/**
		 * A store amount with a partner share added, in the format of get_shipping_rate_data() ( cents as an integer when
		 * $multiplier is 100, else a string with two decimals ).
		 *
		 * @param mixed $amount     Store amount.
		 * @param float $share      Share.
		 * @param int   $multiplier 100 or 1.
		 * @return int|string
		 */
		public static function add_to_amount( $amount, $share, $multiplier ) {
			if ( 100 == $multiplier ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- ec_shipping passes 100 or '100'.
				return (int) number_format( ( (float) $amount / 100 + $share ) * 100, 0, '', '' );
			}
			return number_format( (float) $amount + $share, 2, '.', '' );
		}

		/**
		 * All-partner cart: the primary group's services as rates ( get_shipping_rate_data() rows ). The chosen service comes
		 * first, as the store's own rates do there: Stripe's and Square's wallet sheets take the first option they are sent as
		 * the selected one. The choice is read from the session now ( a wallet call saves a new one after the checkout was built ).
		 *
		 * @param array $context        See for_shipping().
		 * @param int   $multiplier     100 ( cents ) or 1.
		 * @param bool  $waived         The store's free shipping covers partner shipping.
		 * @param bool  $selected_first The chosen service first ( rate data ); false keeps the partner's order ( the printed choices ).
		 * @return array Objects label, amount, id.
		 */
		public static function rate_rows( $context, $multiplier, $waived = false, $selected_first = true ) {
			$rows = array();
			if ( '' === $context['primary'] || ! isset( $context['quotes'][ $context['primary'] ] ) ) {
				return $rows;
			}
			$others   = self::share( $context, $waived );
			$selected = $selected_first ? self::pick( $context ) : '';
			foreach ( $context['quotes'][ $context['primary'] ]['services'] as $code => $service ) {
				$rate = $waived ? 0.0 : (float) $service['rate'] + $others;
				$row  = (object) array(
					'label'  => self::service_label( $service ),
					'amount' => self::add_to_amount( 0, $rate, $multiplier ),
					'id'     => self::method_id( $context['primary'], $code ),
				);
				if ( '' !== $selected && (string) $code === $selected ) {
					array_unshift( $rows, $row );
				} else {
					$rows[] = $row;
				}
			}
			return $rows;
		}

		/**
		 * All-partner cart: the charged shipping ( the chosen service and the other groups' default ones ).
		 *
		 * @param array $context See for_shipping().
		 * @param bool  $waived  The store's free shipping covers partner shipping.
		 * @return float
		 */
		public static function partner_price( $context, $waived = false ) {
			return array_sum( self::amounts( $context, $waived ) );
		}

		/**
		 * A service's label with its delivery time.
		 *
		 * @param array $service Service.
		 * @return string
		 */
		public static function service_label( $service ) {
			$days = self::days_text( $service );
			return ( '' !== $days ) ? sprintf( '%1$s (%2$s)', $service['label'], $days ) : $service['label'];
		}

		/**
		 * "3-5 business days", "4 business days" or ''.
		 *
		 * @param array|null $service Service.
		 * @return string
		 */
		public static function days_text( $service ) {
			if ( ! is_array( $service ) || ( null === $service['min_days'] && null === $service['max_days'] ) ) {
				return '';
			}
			$min  = ( null !== $service['min_days'] ) ? $service['min_days'] : $service['max_days'];
			$max  = ( null !== $service['max_days'] ) ? $service['max_days'] : $service['min_days'];
			$days = ( $min === $max ) ? (string) $min : $min . '-' . $max;
			return str_replace( '[days]', $days, self::text( 'delivery_days', '[days] business days' ) );
		}

		/**
		 * All-partner cart: the primary group's services as radio choices ( the store's rate markup, so both checkouts and
		 * their scripts treat them like any rate ).
		 *
		 * @param array  $context      See for_shipping().
		 * @param string $js_func      The page's shipping change function.
		 * @param bool   $waived       The store's free shipping covers partner shipping.
		 * @param string $display_type RADIO | SELECT.
		 * @return bool Whether there was anything to choose.
		 */
		public static function print_options( $context, $js_func, $waived = false, $display_type = 'RADIO' ) {
			$rows = self::rate_rows( $context, 1, $waived, false ); /* the choices keep their order when one is picked */
			if ( ! $rows ) {
				return false;
			}
			$selected = self::selected_id( $context );
			$cart_id  = ( isset( $GLOBALS['ec_cart_data'] ) && isset( $GLOBALS['ec_cart_data']->ec_cart_id ) ) ? $GLOBALS['ec_cart_data']->ec_cart_id : '';
			$onepage  = function_exists( 'wp_easycart_onepage_active' ) && wp_easycart_onepage_active();
			if ( 'SELECT' === $display_type ) {
				echo '<select name="ec_cart_shipping_method" onchange="' . esc_attr( $js_func ) . '();">';
			}
			foreach ( $rows as $row ) {
				$price = $GLOBALS['currency']->get_currency_display( (float) $row->amount ); /* never through the store's price display filter: Offers must not waive a partner's shipping */
				if ( 'SELECT' === $display_type ) {
					echo '<option value="' . esc_attr( $row->id ) . '"' . ( $row->id === $selected ? ' selected="selected"' : '' ) . '>' . esc_html( $row->label . ' ' . $price ) . '</option>';
					continue;
				}
				$is_selected = ( $row->id === $selected );
				if ( $onepage ) {
					echo '<label class="ec_cart_full_radio">';
				}
				echo '<div class="ec_cart_shipping_method_row ec_cart_shipping_group_row' . ( $is_selected ? ' ec_method_selected' : '' ) . '">';
				echo '<input type="radio" class="no_wrap" name="ec_cart_shipping_method" value="' . esc_attr( $row->id ) . '" onchange="' . esc_attr( $js_func ) . '(\'' . esc_attr( $row->id ) . '\', \'' . esc_attr( $row->amount ) . '\', \'' . esc_attr( wp_create_nonce( 'wp-easycart-update-shipping-method-' . $cart_id . '-' . esc_attr( $row->id ) ) ) . '\');"' . ( $is_selected ? ' checked="checked"' : '' ) . ' />';
				echo '<span class="label">' . esc_html( $row->label ) . '</span> <span class="price">' . esc_html( $price ) . '</span></div>';
				if ( $onepage ) {
					echo '</label>';
				}
			}
			if ( 'SELECT' === $display_type ) {
				echo '</select>';
			}
			return true;
		}

		/**
		 * All-partner cart: the chosen service's id ( group:<provider>:<code> ), or '' when there is nothing to choose.
		 *
		 * @param array $context See for_shipping().
		 * @return string
		 */
		public static function selected_id( $context ) {
			return ( '' !== $context['primary'] && '' !== $context['selected'] ) ? self::method_id( $context['primary'], $context['selected'] ) : '';
		}

		/**
		 * All-partner cart: the chosen service's label.
		 *
		 * @param array $context See for_shipping().
		 * @return string
		 */
		public static function selected_label( $context ) {
			$service = ( '' !== $context['primary'] ) ? self::service( $context, $context['primary'] ) : null;
			return $service ? $service['label'] : '';
		}

		/**
		 * All-partner cart: the chosen service as the review line ( get_selected_shipping_method() ).
		 *
		 * @param array $context See for_shipping().
		 * @param bool  $waived  The store's free shipping covers partner shipping.
		 * @return string HTML.
		 */
		public static function selected_div( $context, $waived = false ) {
			$service = ( '' !== $context['primary'] ) ? self::service( $context, $context['primary'] ) : null;
			if ( ! $service ) {
				return '';
			}
			$id = self::selected_id( $context );
			return '<div class="ec_cart_shipping_method_row" id="' . esc_attr( $id ) . '"> ' . esc_html( self::service_label( $service ) ) . ' (' . esc_html( $GLOBALS['currency']->get_currency_display( self::partner_price( $context, $waived ) ) ) . ')</div>';
		}

		/**
		 * The notes printed under the shipping choices: a partner's items ship separately, with the shipping added and its
		 * delivery time; shipping is added once the address is entered; or a partner could not be priced.
		 *
		 * @param array|null $context See for_shipping().
		 * @param array|null $amounts Provider => what the shopper pays for it ( ec_shipping's charged amounts ), or null.
		 */
		public static function print_note( $context, $amounts = null ) {
			$lines = self::note_lines( $context, false, $amounts );
			if ( ! $lines ) {
				return;
			}
			echo '<div class="ec_cart_shipping_groups_note" style="clear:both; padding:10px 0 0; font-size:0.9em;">';
			foreach ( $lines as $line ) {
				echo '<div class="' . esc_attr( $line['class'] ) . '">' . esc_html( $line['text'] ) . '</div>';
			}
			echo '</div>';
		}

		/**
		 * The short lines under the Shipping total ( cart and checkout totals ).
		 *
		 * @param ec_shipping|array|null $shipping The checkout's shipping, or a context.
		 */
		public static function print_totals_note( $shipping ) {
			echo self::totals_note_html( ( is_object( $shipping ) && method_exists( $shipping, 'get_shipping_groups' ) ) ? $shipping->get_shipping_groups() : $shipping ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- totals_note_html() escapes every part.
		}

		/**
		 * The lines under the Shipping total, as HTML. They carry the promotion list's classes, so the cart script replaces
		 * them with each cart update ( cart_update_response() ).
		 *
		 * @param array|null $context See for_shipping().
		 * @return string
		 */
		public static function totals_note_html( $context ) {
			$html = '';
			foreach ( self::note_lines( $context, true ) as $line ) {
				$html .= '<div class="ec_cart_promotions_list ec_cart_shipping_discount ec_cart_shipping_groups_note"><span class="' . esc_attr( $line['class'] ) . '">' . esc_html( $line['text'] ) . '</span></div>';
			}
			return $html;
		}

		/**
		 * The note's lines.
		 *
		 * @param array|null $context See for_shipping().
		 * @param bool       $totals  The short form, for the totals.
		 * @param array|null $amounts Provider => amount; null reads what the last charged price added.
		 * @return array Each array( 'class', 'text' ).
		 */
		private static function note_lines( $context, $totals, $amounts = null ) {
			$lines = array();
			if ( ! is_array( $context ) || empty( $context['groups'] ) ) {
				return $lines;
			}
			if ( is_array( $amounts ) ) {
				$charged = $amounts;
			} else {
				$charged = ( isset( $context['charged'] ) && is_array( $context['charged'] ) ) ? $context['charged'] : self::amounts( $context );
			}
			foreach ( $context['groups'] as $slug => $group ) {
				$label = $group['label'];
				if ( $context['pending'] ) {
					$lines[] = array(
						'class' => 'ec_cart_shipping_group_pending',
						'text'  => str_replace( '[label]', $label, $totals ? self::text( 'totals_pending', '[label] shipping is added once you enter your address' ) : self::text( 'pending', '[label] items ship separately. Their shipping is added once you enter your address.' ) ),
					);
					continue;
				}
				if ( isset( $context['failed'][ $slug ] ) ) {
					$lines[] = array(
						'class' => 'ec_cart_shipping_group_unavailable',
						'text'  => str_replace( '[label]', $label, $totals ? self::text( 'totals_unavailable', '[label] shipping is unavailable for this address' ) : self::text( 'unavailable', 'We could not get shipping for [label] items to this address. Please check the address or try again in a moment.' ) ),
					);
					continue;
				}
				if ( $slug === $context['primary'] && ! $totals ) {
					continue; /* its services are the choices above */
				}
				$amount  = isset( $charged[ $slug ] ) ? (float) $charged[ $slug ] : 0.0;
				$shown   = ( $amount > 0 ) ? $GLOBALS['currency']->get_currency_display( $amount ) : self::text( 'free', 'Free' );
				$service = self::service( $context, $slug );
				if ( $totals ) {
					$text = str_replace( array( '[label]', '[amount]' ), array( $label, $shown ), self::text( 'totals_included', 'Includes [label] shipping: [amount]' ) );
				} else {
					$text = str_replace( array( '[label]', '[amount]' ), array( $label, $shown ), self::text( 'ships_separately', '[label] items ship separately ( [amount] ).' ) );
					$days = self::days_text( $service );
					if ( '' !== $days ) {
						$text .= ' ' . str_replace( '[days]', $days, self::text( 'delivery_estimate', 'Estimated delivery: [days].' ) );
					}
				}
				$lines[] = array(
					'class' => 'ec_cart_shipping_group_included',
					'text'  => $text,
				);
			}
			return $lines;
		}

		/**
		 * Filter wp_easycart_cart_update_response: the lines under the Shipping total follow the cart ( the cart script
		 * appends shipping_discount_message after removing the old lines ).
		 *
		 * @param array $response Cart update answer.
		 * @return array
		 */
		public static function cart_update_response( $response ) {
			if ( ! is_array( $response ) || ! isset( $response['order_totals'] ) || ! is_array( $response['order_totals'] ) || ! is_array( self::$last ) ) {
				return $response;
			}
			$html = self::totals_note_html( self::$last );
			if ( '' !== $html ) {
				$response['order_totals']['shipping_discount_message'] = ( isset( $response['order_totals']['shipping_discount_message'] ) ? (string) $response['order_totals']['shipping_discount_message'] : '' ) . $html;
			}
			return $response;
		}

		/**
		 * Whether partner shipping stops this checkout: a partner gave no shipping for a known address. When the order is
		 * being placed ( $placing ), also a cart with partner lines and no shipping country of the checkout's own: the partner
		 * would otherwise be charged 0, or priced for the cart estimate's country.
		 *
		 * Order placement asks with $placing: order_errors() ( the one-page check, the manual and free completions ), the
		 * classic Place order, and the cart page wallets ( Square's completion, Stripe's shipping and payment method calls ).
		 *
		 * Also, always, a cart with lines a connected partner ships while the store's shipping is switched off ( Settings ›
		 * Shipping ): ec_shipping then prices nothing, so the partner's shipping would never be charged ( shipping_off_groups() ).
		 *
		 * @param mixed $cartpage ec_cartpage ( or any object whose ->shipping is the checkout's ec_shipping ).
		 * @param bool  $placing  The order is being placed now.
		 * @return bool
		 */
		public static function blocks_checkout( $cartpage, $placing = false ) {
			if ( ! is_object( $cartpage ) || ! isset( $cartpage->shipping ) || ! is_object( $cartpage->shipping ) || ! method_exists( $cartpage->shipping, 'get_shipping_groups' ) ) {
				return false;
			}
			$context = $cartpage->shipping->get_shipping_groups();
			if ( ! is_array( $context ) || empty( $context['groups'] ) ) {
				return ! empty( self::shipping_off_groups( $cartpage ) );
			}
			if ( $placing && ( $context['pending'] || '' === self::checkout_country( $context ) ) ) {
				return true;
			}
			return ! $context['pending'] && ! empty( $context['failed'] );
		}

		/**
		 * The shipping country the checkout held when its shipping was priced ( for_shipping() ). A Stripe completion asks after
		 * the order was placed, when a guest's checkout session is already closed and holds no country.
		 *
		 * @param array $context See for_shipping().
		 * @return string
		 */
		private static function checkout_country( $context ) {
			return isset( $context['order_country'] ) ? (string) $context['order_country'] : self::order_country();
		}

		/**
		 * The cart's partner groups while the store's shipping is switched off ( ec_option_use_shipping ): ec_shipping skips
		 * everything then, partners included, so nothing would charge their shipping ( 6.0.2 ). Empty while shipping is on
		 * ( no lookup at all ), for a cart no connected partner ships, and for an object without the cart ( the Stripe
		 * webhook's order passes its ec_shipping only ).
		 *
		 * @param mixed $cartpage ec_cartpage ( ->cart is its ec_cart ).
		 * @return array See for_cart().
		 */
		private static function shipping_off_groups( $cartpage ) {
			if ( get_option( 'ec_option_use_shipping' ) || ! is_object( $cartpage ) || ! isset( $cartpage->cart ) || ! is_object( $cartpage->cart ) || ! isset( $cartpage->cart->cart ) || ! is_array( $cartpage->cart->cart ) ) {
				return array();
			}
			return self::for_cart( $cartpage->cart->cart );
		}

		/**
		 * The shopper's notice for shipping_group_unavailable, as plain text ( a wallet's JSON answer ).
		 *
		 * @return string
		 */
		public static function error_message() {
			$notes = self::cart_error_notes( array() );
			return wp_strip_all_tags( (string) $notes['shipping_group_unavailable'] );
		}

		/**
		 * After a payment was taken ( a Stripe completion, the Stripe return page, the Stripe webhook's order ): why the new
		 * order must wait for the merchant, when a partner's shipping could not be confirmed for it; else ''.
		 *
		 * @param mixed $cartpage ec_cartpage ( or any object whose ->shipping is the checkout's ec_shipping ).
		 * @return string Staff note.
		 */
		public static function hold_reason( $cartpage ) {
			if ( ! self::blocks_checkout( $cartpage, true ) ) {
				return '';
			}
			$off = self::shipping_off_groups( $cartpage );
			if ( $off ) {
				$labels = array();
				foreach ( $off as $group ) {
					$labels[] = (string) $group['label'];
				}
				/* translators: %1$s: fulfillment partner names, such as Printful. */
				return sprintf( __( 'This order was paid while the store’s shipping is switched off ( Settings › Shipping ), so %1$s shipping was not charged and the order is on hold. Settle the shipping with the customer before you mark the order paid, which sends it to %1$s.', 'wp-easycart' ), implode( ', ', array_unique( $labels ) ) );
			}
			$context    = $cartpage->shipping->get_shipping_groups();
			$no_country = ( $context['pending'] || '' === self::checkout_country( $context ) );
			$labels     = array();
			foreach ( $context['groups'] as $slug => $group ) {
				if ( $no_country || isset( $context['failed'][ $slug ] ) ) {
					$labels[] = (string) $group['label'];
				}
			}
			$names = implode( ', ', array_unique( $labels ) );
			if ( $no_country ) {
				/* translators: %1$s: fulfillment partner names, such as Printful. */
				return sprintf( __( 'This order was paid without a shipping country, so %1$s shipping was not charged and the order is on hold. Add the shipping address and settle the shipping with the customer before you mark the order paid, which sends it to %1$s.', 'wp-easycart' ), $names );
			}
			/* translators: %1$s: fulfillment partner names, such as Printful. */
			return sprintf( __( '%1$s could not price shipping for this order’s address when it was paid, so that shipping was not charged and the order is on hold. Check the address and settle the shipping with the customer before you mark the order paid, which sends it to %1$s.', 'wp-easycart' ), $names );
		}

		/**
		 * After a payment was taken: put a new order on hold when hold_reason() gives one, with the staff note and the
		 * order-payment-hold mark the Stripe webhook checks before it marks anything paid. The caller then saves status 12
		 * ( no stock taken, no receipt, nothing released to the partner ).
		 *
		 * @param int   $order_id Order.
		 * @param mixed $cartpage ec_cartpage ( or any object whose ->shipping is the checkout's ec_shipping ).
		 * @return bool Whether the order is held.
		 */
		public static function hold_order( $order_id, $cartpage ) {
			$order_id = (int) $order_id;
			$reason   = ( $order_id > 0 ) ? self::hold_reason( $cartpage ) : '';
			if ( '' === $reason ) {
				return false;
			}
			if ( function_exists( 'wp_easycart_stripe_hold_order' ) ) {
				wp_easycart_stripe_hold_order( $order_id, $reason );
			} elseif ( function_exists( 'wp_easycart_order_note' ) ) {
				wp_easycart_order_note( $order_id, $reason, 'WP EasyCart' );
			}
			return true;
		}

		/**
		 * Filter wpeasycart_checkout_order_errors ( the one-page checkout and the completion calls: the order is being placed ).
		 *
		 * @param array       $errors   Codes.
		 * @param ec_cartpage $cartpage The checkout.
		 * @return array
		 */
		public static function checkout_errors( $errors, $cartpage = null ) {
			$errors = is_array( $errors ) ? $errors : array();
			if ( self::blocks_checkout( $cartpage, true ) ) {
				$errors[] = 'shipping_group_unavailable';
			}
			return $errors;
		}

		/**
		 * Filter wpeasycart_checkout_step_error: the classic checkout's shipping step and Place order ( payment, where the order
		 * is being placed: no shipping country sends the shopper back to the address ).
		 *
		 * @param array|null  $error    Earlier answer.
		 * @param string      $step     information | shipping | payment.
		 * @param ec_cartpage $cartpage The checkout.
		 * @return array|null
		 */
		public static function checkout_step_error( $error, $step = '', $cartpage = null ) {
			if ( null !== $error || ! in_array( $step, array( 'shipping', 'payment' ), true ) ) {
				return $error;
			}
			$placing = ( 'payment' === $step );
			if ( ! self::blocks_checkout( $cartpage, $placing ) ) {
				return $error;
			}
			if ( self::shipping_off_groups( $cartpage ) ) {
				/* The store's shipping is switched off: the checkout has no shipping step to send the shopper back to. */
				return array(
					'code' => 'shipping_group_unavailable',
					'page' => 'checkout_payment',
				);
			}
			return array(
				'code' => 'shipping_group_unavailable',
				'page' => ( $placing && '' === self::order_country() ) ? 'checkout_info' : 'checkout_shipping',
			);
		}

		/**
		 * Filter wpeasycart_valid_cart_errors: the cart page shows shipping_group_unavailable when a redirect carries it ( a
		 * wallet refused, or the classic checkout sent the shopper back ) with the dynamic cart too.
		 *
		 * @param array $codes Codes.
		 * @return array
		 */
		public static function valid_cart_errors( $codes ) {
			$codes   = is_array( $codes ) ? $codes : array();
			$codes[] = 'shipping_group_unavailable';
			return $codes;
		}

		/**
		 * Filter wpeasycart_cart_errors: the notice for shipping_group_unavailable.
		 *
		 * @param array $notes Code => text.
		 * @return array
		 */
		public static function cart_error_notes( $notes ) {
			$notes = is_array( $notes ) ? $notes : array();
			if ( ! isset( $notes['shipping_group_unavailable'] ) ) {
				$notes['shipping_group_unavailable'] = self::text( 'checkout_error', __( 'We could not work out shipping for some of your items, so your order has not been placed. Please check your shipping address and try again.', 'wp-easycart' ) );
			}
			return $notes;
		}

		/**
		 * Filter wpeasycart_shipping_method_name: the name an order keeps for a partner service ( "Printful - Flat Rate" ).
		 *
		 * @param string|null $name      Name found so far.
		 * @param string      $method_id Chosen shipping method.
		 * @return string|null
		 */
		public static function method_name( $name, $method_id = '' ) {
			$parsed = self::parse_method_id( $method_id );
			if ( ! $parsed || ( null !== $name && '' !== (string) $name ) ) {
				return $name;
			}
			$service = isset( self::$service_labels[ $parsed['provider'] ][ $parsed['code'] ] ) ? self::$service_labels[ $parsed['provider'] ][ $parsed['code'] ] : $parsed['code'];
			return str_replace( array( '[label]', '[service]' ), array( self::label( $parsed['provider'] ), $service ), self::text( 'method_name', '[label] - [service]' ) );
		}

		/**
		 * Record the partner shipping of a new order in ec_order.shipping_groups ( ec_db::insert_order(), so every checkout
		 * path records it ). Each group's amount is what the shopper pays for it: nothing when the store's free shipping
		 * covered it, and less when a coupon took part of it.
		 *
		 * @param int    $order_id     Order.
		 * @param mixed  $shipping     The checkout's ec_shipping.
		 * @param object $order_totals The order's totals ( shipping_total ).
		 * @return array|false The entries written, or false.
		 */
		public static function record( $order_id, $shipping, $order_totals = null ) {
			global $wpdb;
			$order_id = (int) $order_id;
			if ( $order_id <= 0 || ! self::ready() || ! is_object( $shipping ) || ! method_exists( $shipping, 'get_shipping_groups' ) ) {
				return false;
			}
			$context = $shipping->get_shipping_groups();
			if ( ! is_array( $context ) || empty( $context['groups'] ) ) {
				return false;
			}
			$amounts = ( isset( $context['charged'] ) && is_array( $context['charged'] ) ) ? $context['charged'] : self::amounts( $context );
			$total   = array_sum( $amounts );
			if ( $total > 0 && is_object( $order_totals ) && isset( $order_totals->shipping_total ) && (float) $order_totals->shipping_total < $total ) {
				$scale = max( 0.0, (float) $order_totals->shipping_total ) / $total; /* a coupon covered part of it */
				foreach ( $amounts as $slug => $amount ) {
					$amounts[ $slug ] = $amount * $scale;
				}
			}
			$decimals = ( isset( $GLOBALS['currency'] ) && is_object( $GLOBALS['currency'] ) && method_exists( $GLOBALS['currency'], 'get_decimal_length' ) ) ? (int) $GLOBALS['currency']->get_decimal_length() : 2;
			$entries  = array();
			foreach ( $context['groups'] as $slug => $group ) {
				$service   = self::service( $context, $slug );
				$entries[] = array(
					'provider'      => $slug,
					'label'         => $group['label'],
					'service_code'  => $service ? $service['code'] : '',
					'service_label' => $service ? $service['label'] : '',
					'amount'        => round( isset( $amounts[ $slug ] ) ? (float) $amounts[ $slug ] : 0.0, $decimals ),
					'quoted'        => $service ? round( (float) $service['rate'], $decimals ) : 0.0,
					'currency'      => (string) get_option( 'ec_option_base_currency' ),
					'min_days'      => $service ? $service['min_days'] : null,
					'max_days'      => $service ? $service['max_days'] : null,
				);
			}
			$wpdb->query( $wpdb->prepare( 'UPDATE ec_order SET shipping_groups = %s WHERE order_id = %d', wp_json_encode( $entries ), $order_id ) );
			/**
			 * The partner shipping chosen at checkout was recorded on a new order.
			 *
			 * @since 6.0.2
			 * @param int   $order_id Order.
			 * @param array $entries  One per provider ( see wp_easycart_shipping_groups::for_order() ).
			 */
			do_action( 'wp_easycart_order_shipping_groups_recorded', $order_id, $entries );
			return $entries;
		}

		/**
		 * The partner shipping an order was placed with.
		 *
		 * @param int $order_id Order.
		 * @return array Provider => array( provider, label, service_code, service_label, amount ( charged to the shopper ),
		 *               quoted ( the partner's rate ), currency, min_days, max_days ).
		 */
		public static function for_order( $order_id ) {
			global $wpdb;
			if ( ! self::ready() || (int) $order_id <= 0 ) {
				return array();
			}
			$list = json_decode( (string) $wpdb->get_var( $wpdb->prepare( 'SELECT shipping_groups FROM ec_order WHERE order_id = %d', (int) $order_id ) ), true );
			$out  = array();
			foreach ( is_array( $list ) ? $list : array() as $entry ) {
				if ( is_array( $entry ) && ! empty( $entry['provider'] ) ) {
					$out[ (string) $entry['provider'] ] = $entry;
				}
			}
			return $out;
		}

		/**
		 * A text from the language file's shipping_groups section, else the English one.
		 *
		 * @param string $key      Key.
		 * @param string $fallback English text.
		 * @return string
		 */
		public static function text( $key, $fallback ) {
			$text = function_exists( 'wp_easycart_language' ) ? wp_easycart_language()->get_text( 'shipping_groups', $key ) : '';
			return ( is_string( $text ) && '' !== $text ) ? $text : $fallback;
		}
	}

	wp_easycart_shipping_groups::init();

endif;
