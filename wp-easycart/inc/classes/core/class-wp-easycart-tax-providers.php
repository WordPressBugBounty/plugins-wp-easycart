<?php
/**
 * Tax services ( 6.0.2 ): the layer a live tax service such as Avalara AvaTax plugs into.
 *
 * Before 6.0.2 a service had to remember a number for the shopper's session and hand it back through the
 * wp_easycart_tax_total filter, which gave the wrong tax anywhere the engine was built for something other than the
 * shopper's own cart ( subscriptions, New order, the Stripe webhook ) and left it stale whenever the cart changed without
 * wpeasycart_cart_updated firing. Now ec_tax asks the active service for a quote with everything it needs:
 *
 *   wp_easycart_tax_provider   Base class a service extends and registers through the wp_easycart_tax_providers filter.
 *   wp_easycart_tax_request    What is being taxed: lines, discount, shipping, the destination, the customer, the context.
 *                              Built from the tax engine ( from_engine() ) or from a saved order ( from_order() ).
 *   wp_easycart_tax_quote      The answer: tax, shipping tax, an effective rate, a jurisdiction summary, where it came from.
 *   wp_easycart_tax_providers  The registry: which service is active ( one at a time, Settings › Taxes ), per-request
 *                              caching by request key, and the last quote for the order being placed.
 *
 * Only one service calculates tax. ec_option_tax_provider holds the store's choice: '' ( automatic, the order used
 * before 6.0.2: TaxCloud, then TaxJar, then a registered service ), 'none' ( the store's own rates ), 'taxcloud',
 * 'taxjar' or a registered service's id. TaxCloud and TaxJar stay built into the engine; a registered service answers
 * through quote(). A service that cannot answer returns null and the engine uses the store's own rates.
 *
 * @package WP_EasyCart
 * @since   6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_tax_provider' ) ) :

	/**
	 * A live tax service. Extend it, then add the instance on the wp_easycart_tax_providers filter:
	 *
	 *   add_filter( 'wp_easycart_tax_providers', function ( $providers ) {
	 *       $providers['avatax'] = my_avatax_provider::instance();
	 *       return $providers;
	 *   } );
	 *
	 * @since 6.0.2
	 */
	abstract class wp_easycart_tax_provider {

		/**
		 * Short id, stored in ec_option_tax_provider ( a-z, 0-9, _ ).
		 *
		 * @return string
		 */
		abstract public function id();

		/**
		 * Name shown to the merchant.
		 *
		 * @return string
		 */
		abstract public function label();

		/**
		 * Tax for a request.
		 *
		 * @param wp_easycart_tax_request $request What is being taxed.
		 * @return wp_easycart_tax_quote|null null = the engine uses the store's own rates for this request.
		 */
		abstract public function quote( $request );

		/**
		 * Set up and switched on: the engine only asks a ready service.
		 *
		 * @return bool
		 */
		public function is_ready() {
			return true;
		}

		/**
		 * The service taxes this destination.
		 *
		 * @param string                       $country Two-letter country code.
		 * @param wp_easycart_tax_request|null $request The request, when there is one.
		 * @return bool
		 */
		public function covers( $country, $request = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- part of the contract.
			return 'US' === strtoupper( (string) $country );
		}

		/**
		 * The kinds of tax the service's answer replaces for a destination it covers: 'sales' ( always ), and any of
		 * 'vat', 'canada' ( GST / PST / HST ) and 'duty'. Kinds not listed keep coming from the store's own settings.
		 *
		 * @param string $country Two-letter country code.
		 * @return array
		 */
		public function replaces( $country ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- part of the contract.
			return array( 'sales' );
		}

		/**
		 * State for Settings › Taxes: state ready | setup | test | paused | error, a line of text and a settings link.
		 *
		 * @return array
		 */
		public function status() {
			return array(
				'state' => $this->is_ready() ? 'ready' : 'setup',
				'text'  => '',
				'url'   => '',
			);
		}
	}

endif;

if ( ! class_exists( 'wp_easycart_tax_request' ) ) :

	/**
	 * What is being taxed.
	 *
	 * @since 6.0.2
	 */
	final class wp_easycart_tax_request {

		/**
		 * Where the tax is worked out: cart ( the shopper's cart and checkout, and the Stripe webhook finishing an
		 * order ), subscription ( a subscription's first payment ), admin_order ( New order ), order ( a saved order ).
		 *
		 * @var string
		 */
		public $context = 'cart';

		/**
		 * Lines: each an array of id, product_id, sku, title, quantity, amount ( the line total before the document
		 * discount ), taxable, tax_code, shippable, download, giftcard.
		 *
		 * @var array
		 */
		public $lines = array();

		/**
		 * Coupon, offer and promotion discount on the taxable lines ( gift cards are payment, never a discount ).
		 *
		 * @var float
		 */
		public $discount = 0.0;

		/**
		 * Shipping charged, after any shipping discount ( handling is part of it ).
		 *
		 * @var float
		 */
		public $shipping = 0.0;

		/**
		 * Where the tax applies: line1, line2, city, state, zip, country. The shipping address, else the billing address.
		 *
		 * @var array
		 */
		public $address = array();

		/**
		 * The billing address, same keys.
		 *
		 * @var array
		 */
		public $billing = array();

		/**
		 * Store location the order is picked up from ( 0 = shipped or delivered ).
		 *
		 * @var int
		 */
		public $pickup_location_id = 0;

		/**
		 * The customer: user_id, email, exempt, vat_number, user_level.
		 *
		 * @var array
		 */
		public $customer = array();

		/**
		 * The order, when there is one.
		 *
		 * @var int
		 */
		public $order_id = 0;

		/**
		 * Three-letter currency code.
		 *
		 * @var string
		 */
		public $currency = 'USD';

		/**
		 * Date of the sale, Y-m-d in the store's time zone.
		 *
		 * @var string
		 */
		public $date = '';

		/**
		 * The customer is tax exempt in WP EasyCart.
		 *
		 * @var bool
		 */
		public $taxfree = false;

		/**
		 * Anything else a caller passed in its context array, for services that want it.
		 *
		 * @var array
		 */
		public $extra = array();

		/**
		 * An empty address.
		 *
		 * @return array
		 */
		public static function blank_address() {
			return array(
				'line1'   => '',
				'line2'   => '',
				'city'    => '',
				'state'   => '',
				'zip'     => '',
				'country' => '',
			);
		}

		/**
		 * Normalise an address array.
		 *
		 * @param array $address Keys line1, line2, city, state, zip, country ( others are ignored ).
		 * @return array
		 */
		public static function clean_address( $address ) {
			$out = self::blank_address();
			foreach ( $out as $key => $unused ) {
				if ( isset( $address[ $key ] ) && is_scalar( $address[ $key ] ) ) {
					$out[ $key ] = trim( (string) $address[ $key ] );
				}
			}
			$out['state']   = strtoupper( $out['state'] );
			$out['country'] = strtoupper( $out['country'] );
			return $out;
		}

		/**
		 * An address from a session or order row, by prefix ( shipping_ / billing_ ).
		 *
		 * @param object|array $row    Row.
		 * @param string       $prefix shipping | billing.
		 * @return array
		 */
		public static function address_from_row( $row, $prefix ) {
			$row = (array) $row;
			$get = function ( $key ) use ( $row, $prefix ) {
				$name = $prefix . '_' . $key;
				return ( isset( $row[ $name ] ) && is_scalar( $row[ $name ] ) ) ? (string) $row[ $name ] : '';
			};
			return self::clean_address(
				array(
					'line1'   => $get( 'address_line_1' ),
					'line2'   => $get( 'address_line_2' ),
					'city'    => $get( 'city' ),
					'state'   => $get( 'state' ),
					'zip'     => $get( 'zip' ),
					'country' => $get( 'country' ),
				)
			);
		}

		/**
		 * One line, normalised.
		 *
		 * @param array $line Raw line.
		 * @param int   $index Position, used when the line has no id.
		 * @return array
		 */
		public static function clean_line( $line, $index = 0 ) {
			$line = (array) $line;
			$out  = array(
				'id'         => isset( $line['id'] ) ? (string) $line['id'] : (string) ( $index + 1 ),
				'product_id' => isset( $line['product_id'] ) ? (int) $line['product_id'] : 0,
				'sku'        => isset( $line['sku'] ) ? trim( (string) $line['sku'] ) : '',
				'title'      => isset( $line['title'] ) ? trim( wp_strip_all_tags( (string) $line['title'] ) ) : '',
				'quantity'   => isset( $line['quantity'] ) ? max( 0, (float) $line['quantity'] ) : 1,
				'amount'     => isset( $line['amount'] ) ? round( (float) $line['amount'], 2 ) : 0.0,
				'taxable'    => isset( $line['taxable'] ) ? (bool) $line['taxable'] : true,
				'tax_code'   => isset( $line['tax_code'] ) ? trim( (string) $line['tax_code'] ) : '',
				'shippable'  => isset( $line['shippable'] ) ? (bool) $line['shippable'] : true,
				'download'   => ! empty( $line['download'] ),
				'giftcard'   => ! empty( $line['giftcard'] ),
			);
			return $out;
		}

		/**
		 * A line from a cart item ( ec_cartitem ) or a subscription row.
		 *
		 * @param object $item  Cart item.
		 * @param int    $index Position.
		 * @return array
		 */
		private static function line_from_cart_item( $item, $index ) {
			$get    = function ( $key, $fallback = '' ) use ( $item ) {
				return ( is_object( $item ) && isset( $item->$key ) ) ? $item->$key : $fallback;
			};
			$amount = $get( 'total_price', null );
			if ( null === $amount ) {
				$amount = (float) $get( 'item_total', 0 );
			}
			return self::clean_line(
				array(
					'id'         => (string) $get( 'cartitem_id', $index + 1 ),
					'product_id' => (int) $get( 'product_id', 0 ),
					'sku'        => (string) $get( 'model_number', '' ),
					'title'      => (string) $get( 'title', '' ),
					'quantity'   => (float) $get( 'quantity', 1 ),
					'amount'     => (float) $amount,
					'taxable'    => (bool) $get( 'is_taxable', true ),
					'tax_code'   => (string) $get( 'TIC', '' ),
					'shippable'  => (bool) $get( 'is_shippable', true ),
					'download'   => (bool) $get( 'is_download', false ),
					'giftcard'   => (bool) $get( 'is_giftcard', false ),
				),
				$index
			);
		}

		/**
		 * The request for a tax engine build, or null when the engine was built for display only ( a product price, a
		 * receipt's labels ): there are no lines to tax.
		 *
		 * @param array $args cart ( ec_cart, array of cart items, or anything else ), cart_subtotal, taxable_subtotal
		 *                    ( after the coupon ), shipping_total, state, country ( the engine's, filtered ), taxfree,
		 *                    is_subscription, context ( the 10th ec_tax argument: a context name or an array with context,
		 *                    lines, address, billing, customer, order_id, session, user, pickup_location_id ).
		 * @return wp_easycart_tax_request|null
		 */
		public static function from_engine( $args ) {
			$args    = wp_parse_args(
				(array) $args,
				array(
					'cart'             => null,
					'cart_subtotal'    => 0,
					'taxable_subtotal' => 0,
					'shipping_total'   => 0,
					'state'            => '',
					'country'          => '',
					'taxfree'          => false,
					'is_subscription'  => false,
					'context'          => null,
				)
			);
			$context = is_array( $args['context'] ) ? $args['context'] : ( is_string( $args['context'] ) && '' !== $args['context'] ? array( 'context' => $args['context'] ) : array() );
			$request = new self();

			$request->context = isset( $context['context'] ) ? sanitize_key( $context['context'] ) : ( $args['is_subscription'] ? 'subscription' : 'cart' );
			$request->taxfree = (bool) $args['taxfree'];

			// Lines.
			$cart  = $args['cart'];
			$items = null;
			if ( isset( $context['lines'] ) && is_array( $context['lines'] ) ) {
				$index = 0;
				foreach ( $context['lines'] as $line ) {
					$request->lines[] = self::clean_line( $line, $index++ );
				}
			} elseif ( $args['is_subscription'] ) {
				$request->lines = self::subscription_lines( $cart );
			} elseif ( class_exists( 'ec_cart' ) && $cart instanceof ec_cart && is_array( $cart->cart ) ) {
				/* Only a real cart: product price displays pass a look-alike object and must never ask a service. */
				$items = $cart->cart;
			}
			if ( is_array( $items ) ) {
				$index = 0;
				foreach ( $items as $item ) {
					if ( is_object( $item ) && ( isset( $item->product_id ) || isset( $item->total_price ) ) ) {
						$request->lines[] = self::line_from_cart_item( $item, $index++ );
					}
				}
			}
			if ( empty( $request->lines ) ) {
				return null;
			}

			// Discount: everything between the taxable lines and the engine's taxable subtotal, which already has the
			// cart-wide promotion ( ec_cart ) and the coupon / offer ( the caller ) taken off. Gift cards are never in it.
			if ( isset( $context['discount'] ) ) {
				$request->discount = max( 0, round( (float) $context['discount'], 2 ) );
			} elseif ( ! $args['is_subscription'] ) {
				$request->discount = max( 0, round( $request->taxable_lines_total() - max( 0, (float) $args['taxable_subtotal'] ), 2 ) );
			}
			$request->shipping = max( 0, round( (float) $args['shipping_total'], 2 ) );

			// Session: the shopper's, or the one the caller passed ( the Stripe webhook finishing an order ).
			$session = null;
			if ( isset( $context['session'] ) && is_object( $context['session'] ) ) {
				$session = $context['session'];
			} elseif ( isset( $GLOBALS['ec_cart_data'] ) && is_object( $GLOBALS['ec_cart_data'] ) && isset( $GLOBALS['ec_cart_data']->cart_data ) && is_object( $GLOBALS['ec_cart_data']->cart_data ) ) {
				$session = $GLOBALS['ec_cart_data']->cart_data;
			}

			// Addresses.
			$engine_state   = strtoupper( trim( (string) $args['state'] ) );
			$engine_country = strtoupper( trim( (string) $args['country'] ) );
			if ( isset( $context['address'] ) && is_array( $context['address'] ) ) {
				$request->address = self::clean_address( $context['address'] );
			} else {
				$shipping = $session ? self::address_from_row( $session, 'shipping' ) : self::blank_address();
				$billing  = $session ? self::address_from_row( $session, 'billing' ) : self::blank_address();
				if ( '' !== $engine_country && $shipping['country'] !== $engine_country && $billing['country'] === $engine_country ) {
					$shipping = $billing; /* The caller taxed the billing address ( wallet and subscription paths ). */
				} elseif ( '' === $engine_country && '' === $shipping['country'] && '' !== $billing['country'] ) {
					$shipping = $billing; /* Billing-only checkout. */
				}
				if ( '' !== $engine_state && $shipping['state'] !== $engine_state && $billing['country'] === $engine_country && $billing['state'] === $engine_state ) {
					$shipping = $billing; /* The caller taxed the billing state ( Stripe and Square subscription paths ). */
				}
				if ( '' !== $engine_country && ( $shipping['country'] !== $engine_country || ( '' !== $engine_state && '' !== $shipping['state'] && $shipping['state'] !== $engine_state ) ) ) {
					/* The engine was given a place the session does not hold: keep only that place, never another street or ZIP. */
					$shipping = self::blank_address();
				}
				if ( '' !== $engine_country ) {
					$shipping['country'] = $engine_country;
					$shipping['state']   = ( '' !== $engine_state ) ? $engine_state : $shipping['state'];
				}
				$request->address = $shipping;
			}
			if ( isset( $context['billing'] ) && is_array( $context['billing'] ) ) {
				$request->billing = self::clean_address( $context['billing'] );
			} else {
				$request->billing = $session ? self::address_from_row( $session, 'billing' ) : self::blank_address();
			}

			// Pickup at a store location: only when nothing is shipped.
			if ( isset( $context['pickup_location_id'] ) ) {
				$request->pickup_location_id = (int) $context['pickup_location_id'];
			} elseif ( $session && isset( $session->pickup_location ) && (int) $session->pickup_location > 0 && get_option( 'ec_option_pickup_enable_locations' ) && ! $request->ships() ) {
				$request->pickup_location_id = (int) $session->pickup_location;
			}

			// Customer.
			if ( isset( $context['customer'] ) && is_array( $context['customer'] ) ) {
				$request->customer = self::clean_customer( $context['customer'] );
			} else {
				$user              = ( isset( $context['user'] ) && is_object( $context['user'] ) ) ? $context['user'] : ( isset( $GLOBALS['ec_user'] ) && is_object( $GLOBALS['ec_user'] ) ? $GLOBALS['ec_user'] : null );
				$request->customer = self::clean_customer(
					array(
						'user_id'    => ( $user && isset( $user->user_id ) ) ? $user->user_id : 0,
						'email'      => ( $user && ! empty( $user->email ) ) ? $user->email : ( ( $session && isset( $session->email ) ) ? $session->email : '' ),
						'exempt'     => (bool) $args['taxfree'],
						'vat_number' => ( $session && ! empty( $session->vat_registration_number ) ) ? $session->vat_registration_number : ( ( $user && isset( $user->vat_registration_number ) ) ? $user->vat_registration_number : '' ),
						'user_level' => ( $user && isset( $user->user_level ) ) ? $user->user_level : '',
					)
				);
			}
			if ( $request->taxfree ) {
				$request->customer['exempt'] = true;
			}

			$request->order_id = isset( $context['order_id'] ) ? (int) $context['order_id'] : 0;
			$request->currency = self::store_currency();
			$request->date     = isset( $context['date'] ) ? substr( (string) $context['date'], 0, 10 ) : current_time( 'Y-m-d' );
			$request->extra    = array_diff_key( $context, array_flip( array( 'context', 'lines', 'discount', 'address', 'billing', 'customer', 'order_id', 'session', 'user', 'pickup_location_id', 'date' ) ) );
			return $request;
		}

		/**
		 * Lines for a subscription's first payment: the subscription product ( captured from
		 * wpeasycart_cart_subscription_pre_tax ) priced at the rows the engine was given, their discounts already taken.
		 *
		 * @param array $rows Subscription rows ( item_total, is_taxable ).
		 * @return array
		 */
		private static function subscription_lines( $rows ) {
			$rows    = is_array( $rows ) ? $rows : array();
			$product = wp_easycart_tax_providers::subscription_product();
			$amount  = 0.0;
			$taxable = null;
			foreach ( $rows as $row ) {
				if ( is_object( $row ) && isset( $row->item_total ) ) {
					$amount += (float) $row->item_total;
					if ( null === $taxable ) {
						$taxable = ! empty( $row->is_taxable );
					}
				}
			}
			if ( 0.0 === $amount && ! $product ) {
				return array();
			}
			$qty = ( $product && ! empty( $product['quantity'] ) ) ? (float) $product['quantity'] : 1;
			return array(
				self::clean_line(
					array(
						'id'         => 'subscription',
						'product_id' => $product ? $product['product_id'] : 0,
						'sku'        => $product ? $product['sku'] : '',
						'title'      => $product ? $product['title'] : '',
						'quantity'   => $qty,
						'amount'     => $amount,
						'taxable'    => ( null !== $taxable ) ? $taxable : ( $product ? $product['taxable'] : true ),
						'tax_code'   => $product ? $product['tax_code'] : '',
						'shippable'  => $product ? $product['shippable'] : false,
						'download'   => $product ? $product['download'] : false,
					)
				),
			);
		}

		/**
		 * The request for a saved order: lines at their totals less their own coupon and promotion discounts, the
		 * shipping charged, the addresses, pickup location and customer on the order. Used to record orders that were not
		 * quoted at checkout ( New order before 6.0.2, renewals, past orders ) and after an order is edited.
		 *
		 * @param int $order_id Order.
		 * @return wp_easycart_tax_request|null
		 */
		public static function from_order( $order_id ) {
			global $wpdb;
			$order = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_order WHERE order_id = %d', (int) $order_id ) );
			if ( ! $order ) {
				return null;
			}
			$rows    = $wpdb->get_results( $wpdb->prepare( 'SELECT ec_orderdetail.*, ec_product.TIC AS product_tax_code FROM ec_orderdetail LEFT JOIN ec_product ON ec_product.product_id = ec_orderdetail.product_id WHERE ec_orderdetail.order_id = %d ORDER BY ec_orderdetail.orderdetail_id ASC', (int) $order_id ) );
			$request = new self();

			$request->context  = 'order';
			$request->order_id = (int) $order->order_id;
			$index             = 0;
			$line_discounts    = 0.0;
			foreach ( (array) $rows as $row ) {
				$discount         = max( 0, (float) $row->total_discount_coupon ) + max( 0, (float) $row->total_discount_promotion );
				$line_discounts  += $discount;
				$request->lines[] = self::clean_line(
					array(
						'id'         => (string) $row->orderdetail_id,
						'product_id' => (int) $row->product_id,
						'sku'        => (string) $row->model_number,
						'title'      => (string) $row->title,
						'quantity'   => (float) $row->quantity,
						'amount'     => max( 0, (float) $row->total_price - $discount ),
						'taxable'    => ! empty( $row->is_taxable ),
						'tax_code'   => isset( $row->product_tax_code ) ? (string) $row->product_tax_code : '',
						'shippable'  => ! empty( $row->is_shippable ),
						'download'   => ! empty( $row->is_download ),
						'giftcard'   => ! empty( $row->is_giftcard ),
					),
					$index++
				);
			}
			/* Offers v2 discount the order as a whole: spread it over the taxable lines. */
			$request->discount = max( 0, round( (float) $order->offer_discount_total, 2 ) );
			$request->shipping = max( 0, round( (float) $order->shipping_total, 2 ) );
			$request->address  = self::address_from_row( $order, 'shipping' );
			$request->billing  = self::address_from_row( $order, 'billing' );
			if ( '' === $request->address['country'] || ( '' === $request->address['zip'] && '' === $request->address['line1'] ) ) {
				$request->address = $request->billing;
			}
			if ( ! empty( $order->location_id ) && ! $request->ships() ) {
				$request->pickup_location_id = (int) $order->location_id;
			}
			$exempt = false;
			if ( (int) $order->user_id > 0 ) {
				$exempt = (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT exclude_tax FROM ec_user WHERE user_id = %d', (int) $order->user_id ) );
			}
			$request->taxfree  = $exempt && (float) $order->tax_total <= 0;
			$request->customer = self::clean_customer(
				array(
					'user_id'    => (int) $order->user_id,
					'email'      => (string) $order->user_email,
					'exempt'     => $request->taxfree,
					'vat_number' => (string) $order->vat_registration_number,
					'user_level' => (string) $order->user_level,
				)
			);
			$request->currency = self::store_currency();
			$request->date     = substr( (string) $order->order_date, 0, 10 );
			$request->extra    = array(
				'po_number'     => isset( $order->po_number ) ? (string) $order->po_number : '',
				'tax_charged'   => round( (float) $order->tax_total, 2 ),
				'line_discount' => round( $line_discounts, 2 ),
			);
			return $request;
		}

		/**
		 * Normalise a customer array.
		 *
		 * @param array $customer user_id, email, exempt, vat_number, user_level.
		 * @return array
		 */
		public static function clean_customer( $customer ) {
			$customer = (array) $customer;
			return array(
				'user_id'    => isset( $customer['user_id'] ) ? (int) $customer['user_id'] : 0,
				'email'      => isset( $customer['email'] ) ? strtolower( trim( (string) $customer['email'] ) ) : '',
				'exempt'     => ! empty( $customer['exempt'] ),
				'vat_number' => isset( $customer['vat_number'] ) ? trim( (string) $customer['vat_number'] ) : '',
				'user_level' => isset( $customer['user_level'] ) ? (string) $customer['user_level'] : '',
			);
		}

		/**
		 * The store's currency.
		 *
		 * @return string
		 */
		public static function store_currency() {
			$code = strtoupper( trim( (string) get_option( 'ec_option_base_currency' ) ) );
			return preg_match( '/^[A-Z]{3}$/', $code ) ? $code : 'USD';
		}

		/**
		 * Something in the order is shipped, or shipping is charged.
		 *
		 * @return bool
		 */
		public function ships() {
			if ( $this->shipping > 0 ) {
				return true;
			}
			foreach ( $this->lines as $line ) {
				if ( $line['shippable'] && ! $line['download'] ) {
					return true;
				}
			}
			return false;
		}

		/**
		 * Total of the taxable lines, before the document discount.
		 *
		 * @return float
		 */
		public function taxable_lines_total() {
			$total = 0.0;
			foreach ( $this->lines as $line ) {
				if ( $line['taxable'] ) {
					$total += (float) $line['amount'];
				}
			}
			return round( $total, 2 );
		}

		/**
		 * The destination has enough to price: a country, and for the US and Canada a postal code and a state or
		 * province. Services can be stricter.
		 *
		 * @return bool
		 */
		public function address_complete() {
			$address = $this->address;
			if ( '' === $address['country'] ) {
				return false;
			}
			if ( 'US' === $address['country'] ) {
				return (bool) preg_match( '/^\d{5}(-?\d{4})?$/', $address['zip'] ) && '' !== $address['state'];
			}
			if ( 'CA' === $address['country'] ) {
				return (bool) preg_match( '/^[A-Za-z]\d[A-Za-z][ -]?\d[A-Za-z]\d$/', $address['zip'] ) && '' !== $address['state'];
			}
			return true;
		}

		/**
		 * A key that is the same for two requests only when the tax must be the same.
		 *
		 * @return string 32 hex characters.
		 */
		public function key() {
			$lines = array();
			foreach ( $this->lines as $line ) {
				$lines[] = array( $line['product_id'], $line['sku'], (float) $line['quantity'], round( (float) $line['amount'], 2 ), $line['taxable'] ? 1 : 0, $line['tax_code'], $line['shippable'] ? 1 : 0, $line['download'] ? 1 : 0 );
			}
			$address = $this->address;
			foreach ( $address as $k => $v ) {
				$address[ $k ] = strtoupper( preg_replace( '/\s+/', ' ', (string) $v ) );
			}
			return md5(
				(string) wp_json_encode(
					array(
						$this->context,
						$lines,
						round( (float) $this->discount, 2 ),
						round( (float) $this->shipping, 2 ),
						$address,
						(int) $this->pickup_location_id,
						$this->customer['user_id'] ?? 0,
						$this->customer['email'] ?? '',
						! empty( $this->customer['exempt'] ) ? 1 : 0,
						$this->customer['vat_number'] ?? '',
						$this->currency,
						$this->date,
						(int) $this->order_id,
					)
				)
			);
		}

		/**
		 * Plain array ( to store with an order, or log ).
		 *
		 * @return array
		 */
		public function to_array() {
			return array(
				'context'            => $this->context,
				'lines'              => $this->lines,
				'discount'           => $this->discount,
				'shipping'           => $this->shipping,
				'address'            => $this->address,
				'billing'            => $this->billing,
				'pickup_location_id' => $this->pickup_location_id,
				'customer'           => $this->customer,
				'order_id'           => $this->order_id,
				'currency'           => $this->currency,
				'date'               => $this->date,
				'taxfree'            => $this->taxfree,
				'extra'              => $this->extra,
			);
		}

		/**
		 * Back from to_array().
		 *
		 * @param array $data Stored array.
		 * @return wp_easycart_tax_request|null
		 */
		public static function from_array( $data ) {
			if ( ! is_array( $data ) || empty( $data['lines'] ) ) {
				return null;
			}
			$request          = new self();
			$request->context = isset( $data['context'] ) ? sanitize_key( $data['context'] ) : 'cart';
			$index            = 0;
			foreach ( (array) $data['lines'] as $line ) {
				$request->lines[] = self::clean_line( $line, $index++ );
			}
			$request->discount           = isset( $data['discount'] ) ? (float) $data['discount'] : 0.0;
			$request->shipping           = isset( $data['shipping'] ) ? (float) $data['shipping'] : 0.0;
			$request->address            = self::clean_address( isset( $data['address'] ) ? (array) $data['address'] : array() );
			$request->billing            = self::clean_address( isset( $data['billing'] ) ? (array) $data['billing'] : array() );
			$request->pickup_location_id = isset( $data['pickup_location_id'] ) ? (int) $data['pickup_location_id'] : 0;
			$request->customer           = self::clean_customer( isset( $data['customer'] ) ? $data['customer'] : array() );
			$request->order_id           = isset( $data['order_id'] ) ? (int) $data['order_id'] : 0;
			$request->currency           = isset( $data['currency'] ) ? (string) $data['currency'] : self::store_currency();
			$request->date               = isset( $data['date'] ) ? (string) $data['date'] : current_time( 'Y-m-d' );
			$request->taxfree            = ! empty( $data['taxfree'] );
			$request->extra              = isset( $data['extra'] ) && is_array( $data['extra'] ) ? $data['extra'] : array();
			return $request;
		}
	}

endif;

if ( ! class_exists( 'wp_easycart_tax_quote' ) ) :

	/**
	 * A service's answer.
	 *
	 * @since 6.0.2
	 */
	final class wp_easycart_tax_quote {

		/**
		 * Sales tax on the whole request, shipping included.
		 *
		 * @var float
		 */
		public $tax_total = 0.0;

		/**
		 * The part of tax_total charged on shipping.
		 *
		 * @var float
		 */
		public $shipping_tax = 0.0;

		/**
		 * Effective rate in percent ( tax_total over what was taxed ), for Stripe and Square tax rates.
		 *
		 * @var float
		 */
		public $rate = 0.0;

		/**
		 * Tax per line: line id => amount.
		 *
		 * @var array
		 */
		public $lines = array();

		/**
		 * Jurisdictions: rows of name, type, rate ( percent ), tax.
		 *
		 * @var array
		 */
		public $summary = array();

		/**
		 * Where the answer came from: provider ( the service ), backup ( the service's backup rates ), none ( nothing to
		 * tax yet, e.g. no postal code ).
		 *
		 * @var string
		 */
		public $source = 'provider';

		/**
		 * The service's id.
		 *
		 * @var string
		 */
		public $provider = '';

		/**
		 * A short reason when the answer is not the service's own ( backup rates, an incomplete address ).
		 *
		 * @var string
		 */
		public $note = '';

		/**
		 * Service data ( document code, response id ).
		 *
		 * @var array
		 */
		public $data = array();

		/**
		 * The service could not answer and wants checkout stopped ( wpeasycart_checkout_order_errors ).
		 *
		 * @var bool
		 */
		public $block = false;

		/**
		 * A quote.
		 *
		 * @param float  $tax_total    Tax.
		 * @param float  $shipping_tax Tax on shipping.
		 * @param string $source       provider | backup | none.
		 * @return wp_easycart_tax_quote
		 */
		public static function make( $tax_total, $shipping_tax = 0.0, $source = 'provider' ) {
			$quote               = new self();
			$quote->tax_total    = round( max( 0, (float) $tax_total ), 2 );
			$quote->shipping_tax = round( max( 0, (float) $shipping_tax ), 2 );
			$quote->source       = (string) $source;
			return $quote;
		}
	}

endif;

if ( ! class_exists( 'wp_easycart_tax_providers' ) ) :

	/**
	 * The registry.
	 *
	 * @since 6.0.2
	 */
	final class wp_easycart_tax_providers {

		/** The store's choice. */
		const OPTION = 'ec_option_tax_provider';

		/**
		 * Quotes made in this request, by request key.
		 *
		 * @var array
		 */
		private static $cache = array();

		/**
		 * The last quote per context: context => array( request, quote ).
		 *
		 * @var array
		 */
		private static $last = array();

		/**
		 * The subscription product being priced ( wpeasycart_cart_subscription_pre_tax ).
		 *
		 * @var array|null
		 */
		private static $subscription = null;

		/**
		 * Registered services.
		 *
		 * @var array|null
		 */
		private static $providers = null;

		/**
		 * Hooks.
		 */
		public static function init() {
			add_action( 'wpeasycart_cart_subscription_pre_tax', array( __CLASS__, 'capture_subscription' ), 1, 5 );
			add_filter( 'wpeasycart_checkout_order_errors', array( __CLASS__, 'checkout_errors' ), 20 );
			add_filter( 'wpeasycart_cart_errors', array( __CLASS__, 'cart_error_notes' ) );
		}

		/**
		 * Registered services, by id.
		 *
		 * @return array id => wp_easycart_tax_provider
		 */
		public static function all() {
			if ( null === self::$providers || ! did_action( 'plugins_loaded' ) ) {
				$out = array();
				foreach ( (array) apply_filters( 'wp_easycart_tax_providers', array() ) as $id => $provider ) {
					if ( $provider instanceof wp_easycart_tax_provider ) {
						$out[ sanitize_key( $provider->id() ) ] = $provider;
					}
				}
				if ( ! did_action( 'plugins_loaded' ) ) {
					return $out;
				}
				self::$providers = $out;
			}
			return self::$providers;
		}

		/**
		 * One registered service.
		 *
		 * @param string $id Id.
		 * @return wp_easycart_tax_provider|null
		 */
		public static function get( $id ) {
			$all = self::all();
			return isset( $all[ $id ] ) ? $all[ $id ] : null;
		}

		/**
		 * Forget the registered services ( tests, and a service that registers late ).
		 */
		public static function reset() {
			self::$providers = null;
			self::$cache     = array();
			self::$last      = array();
		}

		/**
		 * The store's choice: '' automatic, 'none', 'taxcloud', 'taxjar' or a service id.
		 *
		 * @return string
		 */
		public static function choice() {
			return sanitize_key( (string) get_option( self::OPTION, '' ) );
		}

		/**
		 * TaxCloud is set up ( both keys ).
		 *
		 * @return bool
		 */
		public static function taxcloud_configured() {
			return '' !== (string) get_option( 'ec_option_tax_cloud_api_id' ) && '' !== (string) get_option( 'ec_option_tax_cloud_api_key' );
		}

		/**
		 * TaxJar is switched on and loaded ( it lives in WP EasyCart PRO ).
		 *
		 * @return bool
		 */
		public static function taxjar_configured() {
			return function_exists( 'wpeasycart_taxjar' ) && wpeasycart_taxjar()->is_enabled();
		}

		/**
		 * The service that calculates tax: 'taxcloud', 'taxjar', a service id, or '' for the store's own rates.
		 *
		 * @return string
		 */
		public static function active_id() {
			$choice = self::choice();
			if ( 'none' === $choice ) {
				return '';
			}
			if ( 'taxcloud' === $choice ) {
				return self::taxcloud_configured() ? 'taxcloud' : '';
			}
			if ( 'taxjar' === $choice ) {
				return self::taxjar_configured() ? 'taxjar' : '';
			}
			if ( '' !== $choice ) {
				return $choice;
			}
			/* Automatic: the order used before 6.0.2. */
			if ( self::taxcloud_configured() ) {
				return 'taxcloud';
			}
			if ( self::taxjar_configured() ) {
				return 'taxjar';
			}
			foreach ( self::all() as $id => $provider ) {
				if ( $provider->is_ready() ) {
					return $id;
				}
			}
			return '';
		}

		/**
		 * TaxCloud or TaxJar may calculate: the engine's built-in branches ask this.
		 *
		 * @param string $which taxcloud | taxjar.
		 * @return bool
		 */
		public static function legacy_active( $which ) {
			return self::active_id() === $which;
		}

		/**
		 * The registered service that calculates tax, when it is ready.
		 *
		 * @return wp_easycart_tax_provider|null
		 */
		public static function active() {
			$provider = self::get( self::active_id() );
			return ( $provider && $provider->is_ready() ) ? $provider : null;
		}

		/**
		 * Any automated service calculates tax ( TaxCloud, TaxJar or a registered one ).
		 *
		 * @return bool
		 */
		public static function automated_active() {
			$id = self::active_id();
			return 'taxcloud' === $id || 'taxjar' === $id || null !== self::active();
		}

		/**
		 * Ask the active service. Answers are kept for the rest of the request by request key, so the two engine builds
		 * of one cart page, and every totals build after it, ask once.
		 *
		 * @param wp_easycart_tax_request $request Request.
		 * @return wp_easycart_tax_quote|null null = use the store's own rates.
		 */
		public static function quote( $request ) {
			$provider = self::active();
			if ( ! $provider || ! ( $request instanceof wp_easycart_tax_request ) ) {
				return null;
			}
			$country = $request->address['country'];
			if ( ! $provider->covers( $country, $request ) ) {
				return null;
			}
			$key = $provider->id() . ':' . $request->key();
			if ( ! array_key_exists( $key, self::$cache ) ) {
				$quote = $provider->quote( $request );
				if ( $quote instanceof wp_easycart_tax_quote ) {
					$quote->provider = $provider->id();
				} else {
					$quote = null;
				}
				self::$cache[ $key ] = $quote;
			}
			$quote                           = self::$cache[ $key ];
			self::$last[ $request->context ] = array(
				'request' => $request,
				'quote'   => $quote,
			);
			return $quote;
		}

		/**
		 * The last quote the engine used in this request for a context: array( request, quote ), or null.
		 *
		 * @param string $context cart | subscription | admin_order | order.
		 * @return array|null
		 */
		public static function last( $context = 'cart' ) {
			return isset( self::$last[ $context ] ) ? self::$last[ $context ] : null;
		}

		/**
		 * The kinds of tax the active service replaces for a destination ( see wp_easycart_tax_provider::replaces() ).
		 *
		 * @param wp_easycart_tax_provider $provider Service.
		 * @param string                   $country  Country.
		 * @return array
		 */
		public static function replaced( $provider, $country ) {
			$kinds   = (array) $provider->replaces( $country );
			$kinds[] = 'sales';
			return array_values( array_unique( array_map( 'sanitize_key', $kinds ) ) );
		}

		/**
		 * Action wpeasycart_cart_subscription_pre_tax: remember the subscription product the next engine build prices.
		 *
		 * @param object $product  Product.
		 * @param int    $quantity Quantity.
		 */
		public static function capture_subscription( $product, $quantity = 1 ) {
			if ( ! is_object( $product ) ) {
				self::$subscription = null;
				return;
			}
			self::$subscription = array(
				'product_id' => isset( $product->product_id ) ? (int) $product->product_id : 0,
				'sku'        => isset( $product->model_number ) ? (string) $product->model_number : '',
				'title'      => isset( $product->title ) ? (string) $product->title : '',
				'quantity'   => max( 1, (int) $quantity ),
				'taxable'    => ! empty( $product->is_taxable ),
				'tax_code'   => isset( $product->TIC ) ? (string) $product->TIC : '', // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- column name.
				'shippable'  => ! empty( $product->is_shippable ),
				'download'   => ! empty( $product->is_download ),
			);
		}

		/**
		 * The subscription product being priced.
		 *
		 * @return array|null
		 */
		public static function subscription_product() {
			return self::$subscription;
		}

		/**
		 * Filter wpeasycart_checkout_order_errors: a service that could not work out the tax for this checkout and asked
		 * for checkout to stop ( wp_easycart_tax_quote::$block ).
		 *
		 * @param array $errors Errors.
		 * @return array
		 */
		public static function checkout_errors( $errors ) {
			if ( self::blocks_checkout() ) {
				$errors   = is_array( $errors ) ? $errors : array();
				$errors[] = 'tax_unavailable';
			}
			return $errors;
		}

		/**
		 * The tax on the shopper's checkout could not be worked out and the service asked for checkout to stop.
		 *
		 * @param string $context cart ( the checkout ) | subscription ( a subscription's first payment ).
		 * @return bool
		 */
		public static function blocks_checkout( $context = 'cart' ) {
			$last = self::last( $context );
			return $last && $last['quote'] instanceof wp_easycart_tax_quote && $last['quote']->block;
		}

		/**
		 * Filter wpeasycart_cart_errors: the notice for tax_unavailable.
		 *
		 * @param array $notes code => text.
		 * @return array
		 */
		public static function cart_error_notes( $notes ) {
			$notes = is_array( $notes ) ? $notes : array();
			if ( ! isset( $notes['tax_unavailable'] ) ) {
				$notes['tax_unavailable'] = __( 'We could not work out the tax on your order just now, so it has not been placed. Please try again in a few minutes.', 'wp-easycart' );
			}
			return $notes;
		}

		/**
		 * Choices for Settings › Taxes.
		 *
		 * @return array value => label
		 */
		public static function choice_options() {
			$options = array(
				''         => __( 'Automatic ( TaxCloud, then TaxJar, then an extension )', 'wp-easycart' ),
				'none'     => __( 'None: use the rates on this page', 'wp-easycart' ),
				'taxcloud' => __( 'TaxCloud', 'wp-easycart' ),
				'taxjar'   => __( 'TaxJar', 'wp-easycart' ),
			);
			foreach ( self::all() as $id => $provider ) {
				$options[ $id ] = $provider->label();
			}
			$choice = self::choice();
			if ( '' !== $choice && ! isset( $options[ $choice ] ) ) {
				/* translators: %s: the service id of an extension that is no longer active. */
				$options[ $choice ] = sprintf( __( '%s ( not active )', 'wp-easycart' ), $choice );
			}
			return $options;
		}
	}

	wp_easycart_tax_providers::init();

endif;
