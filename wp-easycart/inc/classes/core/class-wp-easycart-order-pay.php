<?php
/**
 * WP EasyCart — pay an existing order from a link ( 6.0.2 ).
 *
 * An unpaid order ( an invoice sent from the order screen, an order built in the admin, a manual-payment order ) has a pay
 * link: the cart page with ec_page=invoice, the order id and the order's guest key ( 30 random letters, the link's secret ).
 * The page shows the order, lets the customer correct the billing address and pays exactly what is due ( the order's total,
 * or the balance of an order changed after it was paid, see amount_due() and wp_easycart_order_payments ) with:
 *
 *  - Stripe or Stripe Connect ( Payment Element; the PaymentIntent is described "Order N (pay link)" so the webhook can
 *    finish a payment whose page never came back );
 *  - Square ( Web Payments SDK card, charged through ec_square's own request and response handling );
 *  - PayPal ( smart buttons, Orders v2, the store's own app or the WP EasyCart partner app );
 *  - bank transfer instructions when manual payment is on ( nothing to charge ).
 *
 * Any other card gateway or third party cannot take payments here yet: wp_easycart_order_pay::incompatible() names it, and
 * Settings › Documents, the send dialog and the invoice email all say so or leave the Pay button out.
 *
 * A paid order gets its payment details, its stock taken ( lines not yet adjusted ), an approved status, the paid hooks
 * ( wpeasycart_order_paid, wp_easycart_invoice_paid, wp_easycart_order_pay_complete ) and its receipt, once.
 *
 * Loaded from inc/ec_config.php on every request ( the page, its AJAX and the Stripe webhook use it ).
 *
 * @since 6.0.2
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wp_easycart_order_pay_guard' ) ) {
	/**
	 * The pay page's AJAX guard: its nonce ( per order ) and the order's key. Answers and stops unless the order can be paid.
	 *
	 * @since 6.0.2
	 * @param bool $allow_paid Return a paid order instead of stopping ( completion calls ).
	 * @return object The order.
	 */
	function wp_easycart_order_pay_guard( $allow_paid = false ) {
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wp-easycart-order-pay-' . $order_id ) ) {
			wp_send_json_error( array( 'message' => wp_easycart_order_pay::text( 'pay_expired', __( 'This page has expired. Please reload it and try again.', 'wp-easycart' ) ) ) );
		}
		if ( ! wp_easycart_order_pay::available() ) {
			wp_send_json_error( array( 'message' => wp_easycart_order_pay::text( 'pay_unavailable', __( 'Online payment is not available for this order. Please contact us to arrange payment.', 'wp-easycart' ) ) ) );
		}
		$key   =isset( $_POST['key'] ) ? wp_easycart_order_pay::clean_key( sanitize_text_field( wp_unslash( $_POST['key'] ) ) ) : '';
		$order = wp_easycart_order_pay::find( $order_id, $key );
		$state = $order ? wp_easycart_order_pay::state( $order ) : 'missing';
		if ( 'paid' === $state && $allow_paid ) {
			return $order;
		}
		if ( 'paid' === $state ) {
			wp_send_json_success( array( 'paid' => true ) );
		}
		if ( 'payable' !== $state ) {
			wp_send_json_error( array( 'message' => wp_easycart_order_pay::state_message( $state ) ) );
		}
		return $order;
	}
}

if ( ! class_exists( 'wp_easycart_order_pay' ) ) :

	/**
	 * Pay an existing order from its link.
	 *
	 * @since 6.0.2
	 */
	final class wp_easycart_order_pay {

		/** Store switch ( Settings › Documents › Pay links ). */
		const OPTION = 'ec_option_order_pay_links';

		/** Order log entry of a payment made here. */
		const LOG = 'order-paid-online';

		/** Register hooks. */
		public static function init() {
			foreach ( array( 'billing', 'stripe_intent', 'stripe_complete', 'square', 'paypal_create', 'paypal_capture' ) as $action ) {
				add_action( 'wp_ajax_ec_order_pay_' . $action, array( __CLASS__, 'ajax_' . $action ) );
				add_action( 'wp_ajax_nopriv_ec_order_pay_' . $action, array( __CLASS__, 'ajax_' . $action ) );
			}
			add_action( 'wpeasycart_stripe_webhook', array( __CLASS__, 'webhook_event' ), 10, 3 );
			add_filter( 'script_loader_src', array( __CLASS__, 'paypal_sdk_currency' ), 10, 2 );
		}

		/**
		 * Filter script_loader_src: on the pay page PayPal's script uses the store's PayPal currency ( the order's ), not a
		 * shopper's converted currency, or PayPal refuses the order.
		 *
		 * @param string $src    Script URL.
		 * @param string $handle Script handle.
		 * @return string
		 */
		public static function paypal_sdk_currency( $src, $handle ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which page this is; nothing is changed.
			if ( 'wpeasycart_paypal_js' !== $handle || ! isset( $_GET['ec_page'] ) || 'invoice' !== $_GET['ec_page'] ) {
				return $src;
			}
			$currency = strtoupper( (string) get_option( 'ec_option_paypal_currency_code' ) );
			return '' === $currency ? $src : add_query_arg( 'currency', rawurlencode( $currency ), remove_query_arg( 'currency', $src ) );
		}

		/**
		 * A phrase ( Settings › Languages › Order Documents ).
		 *
		 * @param string $key      Phrase.
		 * @param string $fallback English.
		 * @return string Safe HTML.
		 */
		public static function text( $key, $fallback ) {
			return class_exists( 'wp_easycart_documents' ) ? wp_easycart_documents::text( $key, $fallback ) : esc_html( $fallback );
		}

		/* ------------------------------------------------------------------ */
		/* Gateways                                                            */
		/* ------------------------------------------------------------------ */

		/** @return string The Stripe publishable key the checkout uses. */
		public static function stripe_key() {
			$method = (string) get_option( 'ec_option_payment_process_method' );
			if ( 'stripe' === $method ) {
				$key = get_option( 'ec_option_stripe_public_api_key' );
			} elseif ( 'stripe_connect' === $method && get_option( 'ec_option_stripe_connect_use_sandbox' ) ) {
				$key = get_option( 'ec_option_stripe_connect_sandbox_publishable_key' );
			} else {
				$key = get_option( 'ec_option_stripe_connect_production_publishable_key' );
			}
			return (string) apply_filters( 'wp_easycart_stripe_connect_publishable_key', $key );
		}

		/** @return array Square application and location ids ( as the checkout picks them ). */
		public static function square_ids() {
			$app = (string) get_option( 'ec_option_square_application_id' );
			if ( '' === $app ) {
				$app = get_option( 'ec_option_square_is_sandbox' ) ? 'sandbox-sq0idb-khAAob2bNi889KPQSVsF6Q' : 'sq0idp-H8Mnz1zzbv1mOyeWyKpF6Q';
			}
			return array(
				'app'      => $app,
				'location' => (string) ( get_option( 'ec_option_square_is_sandbox' ) ? get_option( 'ec_option_square_sandbox_location_id' ) : get_option( 'ec_option_square_location_id' ) ),
			);
		}

		/**
		 * The ways this store can take payment on a pay link.
		 *
		 * @return array stripe => stripe|stripe_connect, square => square, paypal => paypal, manual => manual.
		 */
		public static function methods() {
			$out    = array();
			$method = (string) get_option( 'ec_option_payment_process_method' );
			if ( ( ( 'stripe' === $method && class_exists( 'ec_stripe' ) ) || ( 'stripe_connect' === $method && class_exists( 'ec_stripe_connect' ) ) ) && '' !== self::stripe_key() ) {
				$out['stripe'] = $method;
			} elseif ( 'square' === $method && class_exists( 'ec_square' ) && '' !== self::square_ids()['location'] ) {
				$out['square'] = 'square';
			}
			if ( 'paypal' === (string) get_option( 'ec_option_payment_third_party' ) && '1' === (string) get_option( 'ec_option_paypal_enable_pay_now' ) && class_exists( 'ec_paypal' ) ) {
				$out['paypal'] = 'paypal';
			}
			if ( get_option( 'ec_option_use_direct_deposit' ) ) {
				$out['manual'] = 'manual';
			}
			return (array) apply_filters( 'wp_easycart_order_pay_methods', $out );
		}

		/** @return bool Can a customer pay online on a pay link ( card or PayPal )? */
		public static function online() {
			$methods = self::methods();
			return isset( $methods['stripe'] ) || isset( $methods['square'] ) || isset( $methods['paypal'] );
		}

		/** @return bool Are pay links on, with a way to pay online? */
		public static function available() {
			return (bool) get_option( self::OPTION, 1 ) && self::online();
		}

		/**
		 * A gateway's name for messages.
		 *
		 * @param string $key Option value.
		 * @return string
		 */
		public static function gateway_label( $key ) {
			$labels = array(
				'authorize'                 => 'Authorize.net',
				'braintree'                 => 'Braintree',
				'cardpointe'                => 'CardPointe',
				'chronopay'                 => 'ChronoPay',
				'eway'                      => 'eWay',
				'firstdata'                 => 'First Data',
				'goemerchant'               => 'GoEmerchant',
				'intuit'                    => 'Intuit',
				'migs'                      => 'MIGS',
				'moneris_ca'                => 'Moneris',
				'moneris_us'                => 'Moneris',
				'nmi'                       => 'NMI',
				'payline'                   => 'Payline',
				'paymentexpress'            => 'Windcave',
				'paypal_payments_pro'       => 'PayPal Payments Pro',
				'paypal_pro'                => 'PayPal Pro',
				'realex'                    => 'Realex',
				'sagepay'                   => 'Opayo',
				'sagepayus'                 => 'Sage Payments',
				'securenet'                 => 'SecureNet',
				'securepay'                 => 'SecurePay',
				'virtualmerchant'           => 'Converge',
				'2checkout_thirdparty'      => '2Checkout',
				'dwolla_thirdparty'         => 'Dwolla',
				'nets'                      => 'Nets',
				'payfast_thirdparty'        => 'PayFast',
				'payfort'                   => 'Payfort',
				'paymentexpress_thirdparty' => 'Windcave',
				'realex_thirdparty'         => 'Realex',
				'redsys'                    => 'Redsys',
				'sagepay_paynow_za'         => 'Sage Pay Now',
				'skrill'                    => 'Skrill',
				'cashfree'                  => 'Cashfree',
				'paypal'                    => 'PayPal Standard',
			);
			return isset( $labels[ $key ] ) ? $labels[ $key ] : ucwords( str_replace( array( '_', '-' ), ' ', (string) $key ) );
		}

		/**
		 * The store's active gateways a pay link cannot use yet.
		 *
		 * @return array Names.
		 */
		public static function incompatible() {
			$out     = array();
			$methods = self::methods();
			$card    = (string) get_option( 'ec_option_payment_process_method' );
			if ( '' !== $card && '0' !== $card && ! in_array( $card, array( 'stripe', 'stripe_connect', 'square' ), true ) ) {
				$out[] = self::gateway_label( $card );
			}
			$third = (string) get_option( 'ec_option_payment_third_party' );
			if ( '' !== $third && '0' !== $third && ! isset( $methods['paypal'] ) ) {
				$out[] = self::gateway_label( $third );
			}
			return $out;
		}

		/**
		 * Settings › Documents › Pay links: which of the store's payment methods work on a pay link.
		 *
		 * @param array $page    Declaration.
		 * @param array $section Section.
		 */
		public static function render_settings_status( $page = array(), $section = array() ) {
			$methods = self::methods();
			$names   = array();
			if ( isset( $methods['stripe'] ) ) {
				$names[] = __( 'Card payments with Stripe', 'wp-easycart' );
			}
			if ( isset( $methods['square'] ) ) {
				$names[] = __( 'Card payments with Square', 'wp-easycart' );
			}
			if ( isset( $methods['paypal'] ) ) {
				$names[] = __( 'PayPal', 'wp-easycart' );
			}
			if ( isset( $methods['manual'] ) ) {
				$names[] = __( 'Bank transfer details ( manual payment )', 'wp-easycart' );
			}
			$blocked = self::incompatible();
			if ( $blocked ) {
				echo '<p class="ecdoc-grid-warn" role="alert">' . esc_html(
					sprintf(
						/* translators: %s: payment gateway names. */
						_n( '%s cannot take payments on pay links yet. Until it can, invoices from this store go out without a Pay button for it.', '%s cannot take payments on pay links yet. Until they can, invoices from this store go out without a Pay button for them.', count( $blocked ), 'wp-easycart' ),
						implode( ', ', $blocked )
					)
				) . '</p>';
			}
			if ( ! self::online() ) {
				echo '<p class="ecdoc-grid-note">' . esc_html__( 'None of your payment methods can take a payment on a pay link yet. Stripe, Square and PayPal ( with Pay Now buttons ) can. Customers who open a pay link see your bank transfer details, or a note to contact you.', 'wp-easycart' ) . '</p>';
			} else {
				echo '<p class="ecdoc-grid-note">' . esc_html(
					sprintf(
						/* translators: %s: list of payment methods. */
						__( 'Customers can pay on a pay link with: %s. The customer can correct the billing address; everything else on the order stays as you set it.', 'wp-easycart' ),
						implode( ', ', $names )
					)
				) . '</p>';
			}
		}

		/* ------------------------------------------------------------------ */
		/* Orders and links                                                    */
		/* ------------------------------------------------------------------ */

		/**
		 * A link key: letters only, upper case, at most 30.
		 *
		 * @param string $raw Key.
		 * @return string
		 */
		public static function clean_key( $raw ) {
			return substr( preg_replace( '/[^A-Z]/', '', strtoupper( (string) $raw ) ), 0, 30 );
		}

		/**
		 * Give an order a key when it has none ( orders duplicated or built in the admin ).
		 *
		 * @param int $order_id Order.
		 * @return string
		 */
		public static function ensure_key( $order_id ) {
			global $wpdb;
			$key = self::clean_key( (string) $wpdb->get_var( $wpdb->prepare( 'SELECT guest_key FROM ec_order WHERE order_id = %d', (int) $order_id ) ) );
			if ( strlen( $key ) >= 20 ) {
				return $key;
			}
			$key = '';
			for ( $i = 0; $i < 30; $i++ ) {
				$key .= chr( 65 + wp_rand( 0, 25 ) );
			}
			$wpdb->update( 'ec_order', array( 'guest_key' => $key ), array( 'order_id' => (int) $order_id ) );
			return $key;
		}

		/**
		 * An order's pay link.
		 *
		 * @param int|object $order Order id or row.
		 * @return string
		 */
		public static function url( $order ) {
			$order_id = is_object( $order ) ? (int) $order->order_id : (int) $order;
			if ( $order_id <= 0 || ! function_exists( 'wpeasycart_links' ) ) {
				return '';
			}
			return wpeasycart_links()->get_cart_page(
				'invoice',
				array(
					'order_id'     => $order_id,
					'ec_guest_key' => self::ensure_key( $order_id ),
				)
			);
		}

		/**
		 * The order a link names ( its id and key must both match ).
		 *
		 * @param int    $order_id Order.
		 * @param string $key      Link key.
		 * @return object|null
		 */
		public static function find( $order_id, $key ) {
			global $wpdb;
			$key = self::clean_key( $key );
			if ( (int) $order_id <= 0 || strlen( $key ) < 20 ) {
				return null;
			}
			$order = $wpdb->get_row( $wpdb->prepare( "SELECT ec_order.*, ec_orderstatus.is_approved FROM ec_order LEFT JOIN ec_orderstatus ON ec_orderstatus.status_id = ec_order.orderstatus_id WHERE ec_order.order_id = %d AND ec_order.guest_key = %s AND ec_order.guest_key != ''", (int) $order_id, $key ) );
			if ( ! $order || ! hash_equals( self::clean_key( $order->guest_key ), $key ) ) {
				return null;
			}
			return $order;
		}

		/**
		 * Can the order be paid now?
		 *
		 * @param object $order Order row ( with is_approved ).
		 * @return string payable | paid | pending | closed | missing ( a draft is not there for the customer yet ).
		 */
		public static function state( $order ) {
			$draft = (int) get_option( 'ec_option_orderstatus_draft', 0 );
			if ( $draft > 0 && $draft === (int) $order->orderstatus_id ) {
				return 'missing';
			}
			$paid = class_exists( 'wp_easycart_order_payments' ) ? wp_easycart_order_payments::summary( $order ) : null;
			if ( $paid && $paid['recorded'] ) {
				/* 6.0.2: what was paid is recorded, so an order changed after it was paid can be paid its balance ( amount_due() ). */
				if ( in_array( $paid['state'], array( 'refunded', 'cancelled' ), true ) ) {
					return 'closed';
				}
				if ( $paid['due'] < 0.005 ) {
					return ( ! empty( $order->is_approved ) || $paid['paid'] >= 0.005 ) ? 'paid' : 'closed';
				}
			} else {
				if ( ! empty( $order->is_approved ) ) {
					return 'paid';
				}
				if ( in_array( (int) $order->orderstatus_id, array( 16, 19 ), true ) || (float) $order->refund_total > 0 || (float) $order->grand_total <= 0 ) {
					return 'closed';
				}
			}
			/* A PayPal payment held for review, or a Stripe payment still processing ( bank debits ): paying again would charge twice. */
			if ( 8 === (int) $order->orderstatus_id && 'paypal-express' === (string) $order->order_gateway && '' !== (string) $order->gateway_transaction_id ) {
				return 'pending';
			}
			if ( get_transient( self::pending_key( $order->order_id ) ) ) {
				return 'pending';
			}
			return 'payable';
		}

		/**
		 * What the pay link charges: the order's balance when what it was paid is recorded ( an order changed after it was paid
		 * owes only the difference ), else its total.
		 *
		 * @since 6.0.2
		 * @param object $order Order row ( ec_order.* ).
		 * @return float
		 */
		public static function amount_due( $order ) {
			if ( class_exists( 'wp_easycart_order_payments' ) ) {
				$paid = wp_easycart_order_payments::summary( $order );
				if ( $paid['recorded'] ) {
					return $paid['due'];
				}
			}
			return round( (float) $order->grand_total, 2 );
		}

		/**
		 * Is a payment on this order toward the balance of an order that was already paid ( its status and payment details
		 * stay as they are; the payment is added to what it was paid )? Already paid: its status counts as paid, or a payment
		 * is recorded ( a paid order put on hold keeps its first payment ).
		 *
		 * @since 6.0.2
		 * @param object $order Order row ( ec_order.*, is_approved ).
		 * @return bool
		 */
		public static function is_balance( $order ) {
			if ( ! class_exists( 'wp_easycart_order_payments' ) ) {
				return false;
			}
			$paid = wp_easycart_order_payments::summary( $order );
			return $paid['recorded'] && $paid['due'] >= 0.005 && ( ! empty( $order->is_approved ) || $paid['paid'] >= 0.005 );
		}

		/**
		 * The transient that marks a payment in progress ( set while Stripe processes it, cleared when it succeeds or fails ).
		 *
		 * @param int $order_id Order.
		 * @return string
		 */
		private static function pending_key( $order_id ) {
			return 'wpec_pay_pending_' . (int) $order_id;
		}

		/**
		 * Mark the order's payment as in progress.
		 *
		 * @param int    $order_id Order.
		 * @param string $ref      Payment reference.
		 */
		private static function set_pending( $order_id, $ref ) {
			set_transient( self::pending_key( $order_id ), (string) $ref, 7 * DAY_IN_SECONDS );
		}

		/**
		 * Can the customer pay this order online from their account?
		 *
		 * @param object $order Order row or ec_orderdisplay ( order_id and guest_key ).
		 * @return bool
		 */
		public static function can_pay( $order ) {
			if ( ! is_object( $order ) || empty( $order->order_id ) || empty( $order->guest_key ) || ! self::available() ) {
				return false;
			}
			$row = self::find( (int) $order->order_id, (string) $order->guest_key );
			return $row && 'payable' === self::state( $row );
		}

		/**
		 * What the page says for a state.
		 *
		 * @param string $state State.
		 * @return string Safe HTML.
		 */
		public static function state_message( $state ) {
			if ( 'paid' === $state ) {
				return self::text( 'pay_paid', __( 'This order is paid. Thank you!', 'wp-easycart' ) );
			}
			if ( 'pending' === $state ) {
				return self::text( 'pay_pending', __( 'Your payment is being processed. We will email you when it is confirmed.', 'wp-easycart' ) );
			}
			if ( 'closed' === $state ) {
				return self::text( 'pay_closed', __( 'This order can no longer be paid online. Please contact us if you have a question.', 'wp-easycart' ) );
			}
			return self::text( 'pay_not_found', __( 'We could not find this order. Check the link in your email, or contact us.', 'wp-easycart' ) );
		}

		/* ------------------------------------------------------------------ */
		/* The page                                                            */
		/* ------------------------------------------------------------------ */

		/** The pay page ( the cart page's ec_page=invoice ). */
		public static function render_page() {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the link's key is the credential; nothing changes without it matching the order.
			$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
			$key      = isset( $_GET['ec_guest_key'] ) ? self::clean_key( sanitize_text_field( wp_unslash( $_GET['ec_guest_key'] ) ) ) : '';
			$order    = self::find( $order_id, $key );
			$state    = $order ? self::state( $order ) : 'missing';
			$notice   = '';
			/* Back from a Stripe payment method that redirects ( bank redirects, wallets ): finish it here. */
			if ( 'payable' === $state && isset( $_GET['payment_intent'], $_GET['payment_intent_client_secret'] ) ) {
				$methods = self::methods();
				$stripe  = isset( $methods['stripe'] ) ? self::stripe() : null;
				$pi      = $stripe ? $stripe->get_payment_intent( sanitize_text_field( wp_unslash( $_GET['payment_intent'] ) ) ) : false;
				if ( $pi && isset( $pi->client_secret ) && hash_equals( (string) $pi->client_secret, sanitize_text_field( wp_unslash( $_GET['payment_intent_client_secret'] ) ) ) ) {
					if ( 'processing' === $pi->status ) {
						self::set_pending( $order->order_id, $pi->id );
						$notice = 'pending';
					} else {
						$done = self::complete_intent( $order, $pi );
						if ( true === $done ) {
							$notice = 'success';
						} elseif ( 'succeeded' === $pi->status ) {
							/* Charged, but not for this order as it stands ( complete_intent() logged it ): hold the form so it is not paid twice. */
							self::set_pending( $order->order_id, $pi->id );
							$notice = 'pending';
						} else {
							$notice = 'error';
						}
					}
					$order = self::find( $order_id, $key );
					$state = $order ? self::state( $order ) : 'missing';
				}
			}
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			$pay_methods = self::methods();
			/* The gateway scripts load in the head of a page with [ec_cart] in its content; a cart page built another way gets them here, in the footer ( the page script waits for them ). */
			if ( 'payable' === $state && function_exists( 'wp_easycart_load_cart_js' ) && function_exists( 'wp_script_is' ) ) {
				$missing = ( isset( $pay_methods['stripe'] ) && ! wp_script_is( 'wpeasycart_stripe_js' ) )
					|| ( isset( $pay_methods['square'] ) && ! wp_script_is( 'wpeasycart_square_js' ) )
					|| ( isset( $pay_methods['paypal'] ) && ! wp_script_is( 'wpeasycart_paypal_js' ) );
				if ( $missing ) {
					wp_easycart_load_cart_js();
				}
			}
			$template = class_exists( 'wp_easycart_documents' ) ? wp_easycart_documents::locate( 'ec_order_pay.php' ) : EC_PLUGIN_DIRECTORY . '/design/layout/base-responsive-v3/ec_order_pay.php';
			if ( is_string( $template ) && '' !== $template && file_exists( $template ) ) {
				include $template;
			}
			/* 6.0.2: the Meta Pixel and Conversions API hear that the customer reached the payment form ( AddPaymentInfo, one
			   event ID for browser and server, once per order in this session ), as checkout's payment step does. */
			if ( 'payable' === $state && function_exists( 'wp_easycart_meta_order_payment_info' ) ) {
				wp_easycart_meta_order_payment_info( (int) $order->order_id, self::amount_due( $order ) );
			}
		}

		/**
		 * The page's data for its script.
		 *
		 * @param object $order Order.
		 * @return array
		 */
		public static function script_data( $order ) {
			$methods = self::methods();
			$square  = self::square_ids();
			return array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'wp-easycart-order-pay-' . (int) $order->order_id ),
				'order_id' => (int) $order->order_id,
				'key'      => self::clean_key( $order->guest_key ),
				'amount'   => number_format( self::amount_due( $order ), 2, '.', '' ),
				'page'     => self::url( $order ),
				'return'   => add_query_arg( 'wpec_pay_return', '1', self::url( $order ) ),
				'email'    => (string) $order->user_email,
				'stripe'   => isset( $methods['stripe'] ) ? self::stripe_key() : '',
				'currency' => isset( $methods['square'] ) ? (string) get_option( 'ec_option_square_currency' ) : '',
				'square'   => isset( $methods['square'] ) ? array(
					'app'      => $square['app'],
					'location' => $square['location'],
				) : null,
				'paypal'   => isset( $methods['paypal'] ),
				'text'     => array(
					'processing' => wp_strip_all_tags( self::text( 'pay_processing', __( 'Processing your payment…', 'wp-easycart' ) ) ),
					'error'      => wp_strip_all_tags( self::text( 'pay_error', __( 'The payment could not be completed. Please try again, or use another payment method.', 'wp-easycart' ) ) ),
					'billing'    => wp_strip_all_tags( self::text( 'pay_billing_error', __( 'Please fill in your billing address.', 'wp-easycart' ) ) ),
					'pending'    => wp_strip_all_tags( self::text( 'pay_pending', __( 'Your payment is being processed. We will email you when it is confirmed.', 'wp-easycart' ) ) ),
				),
			);
		}

		/* ------------------------------------------------------------------ */
		/* Billing                                                             */
		/* ------------------------------------------------------------------ */

		/**
		 * Save the billing address posted with a payment ( JSON 'billing' ), the only thing a customer changes here.
		 *
		 * @param object $order Order.
		 * @return true|WP_Error
		 */
		private static function save_billing( $order ) {
			global $wpdb;
			$raw = ( isset( $_POST['billing'] ) && is_string( $_POST['billing'] ) ) ? json_decode( wp_unslash( $_POST['billing'] ), true ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- every caller ran wp_easycart_order_pay_guard() first; JSON, each value is sanitized below.
			if ( ! is_array( $raw ) ) {
				return new WP_Error( 'billing', wp_strip_all_tags( self::text( 'pay_billing_error', __( 'Please fill in your billing address.', 'wp-easycart' ) ) ) );
			}
			/* Only the fields the page showed: a field the store hides ( company, second line, phone ) keeps what the order has. */
			$fields = array( 'first_name', 'last_name', 'company_name', 'address_line_1', 'address_line_2', 'city', 'state', 'zip', 'country', 'phone' );
			$data   = array();
			foreach ( $fields as $field ) {
				if ( isset( $raw[ $field ] ) && is_scalar( $raw[ $field ] ) ) {
					$data[ 'billing_' . $field ] = substr( sanitize_text_field( (string) $raw[ $field ] ), 0, 255 );
				}
			}
			if ( isset( $data['billing_country'] ) ) {
				$data['billing_country'] = strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', $data['billing_country'] ), 0, 2 ) );
			}
			foreach ( array( 'first_name', 'last_name', 'address_line_1', 'city', 'zip', 'country' ) as $required ) {
				if ( ! isset( $data[ 'billing_' . $required ] ) || '' === trim( $data[ 'billing_' . $required ] ) ) {
					return new WP_Error( 'billing', wp_strip_all_tags( self::text( 'pay_billing_error', __( 'Please fill in your billing address.', 'wp-easycart' ) ) ) );
				}
			}
			if ( get_option( 'ec_option_collect_vat_registration_number' ) && isset( $raw['vat_registration_number'] ) && is_scalar( $raw['vat_registration_number'] ) ) {
				$data['vat_registration_number'] = substr( sanitize_text_field( (string) $raw['vat_registration_number'] ), 0, 255 );
			}
			$wpdb->update( 'ec_order', $data, array( 'order_id' => (int) $order->order_id ) );
			foreach ( $data as $column => $value ) {
				$order->{$column} = $value;
			}
			return true;
		}

		/**
		 * Save the billing address or answer with the error.
		 *
		 * @param object $order Order.
		 */
		private static function billing_or_fail( $order ) {
			$saved = self::save_billing( $order );
			if ( is_wp_error( $saved ) ) {
				wp_send_json_error( array( 'message' => $saved->get_error_message() ) );
			}
		}

		/** AJAX: save the billing address ( before a Stripe payment is confirmed in the browser ). */
		public static function ajax_billing() {
			$order = wp_easycart_order_pay_guard();
			self::billing_or_fail( $order );
			wp_send_json_success();
		}

		/* ------------------------------------------------------------------ */
		/* Completing a payment                                                */
		/* ------------------------------------------------------------------ */

		/**
		 * An amount in the gateway's smallest unit, as the checkout sends it.
		 *
		 * @param float $amount Amount.
		 * @return int
		 */
		private static function cents( $amount ) {
			return (int) number_format( (float) $amount * 100, 0, '', '' );
		}

		/**
		 * Take the order's stock for the lines not yet adjusted: the one way WP EasyCart does it after checkout ( an order made
		 * approved on the order screen, the orders list or in bulk, a pay link, Mark as paid, WP EasyCart PRO's New order, the
		 * order screen's Take stock now ). Each line is claimed first ( stock_adjusted 0 to 1 ), so two requests at once never
		 * take the same line's stock.
		 *
		 * @since 6.0.2 bug round 14: $note, and the units taken are returned.
		 * @since 6.0.3 units a refund already put back in stock are not taken again ( an order cancelled, then reopened ).
		 * @param int    $order_id Order.
		 * @param string $note     The stock history's note ( wpeasycart_inventory_stock_changed ); '' for "Order #N paid".
		 * @return int Units taken.
		 */
		public static function take_stock( $order_id, $note = '' ) {
			global $wpdb;
			$db    = new ec_db();
			$taken = 0;
			/* translators: %d: order number. */
			$note  = ( '' !== (string) $note ) ? (string) $note : sprintf( __( 'Order #%d paid', 'wp-easycart' ), (int) $order_id );
			$lines = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ec_orderdetail WHERE order_id = %d AND stock_adjusted = 0', (int) $order_id ) );
			/* 6.0.3: units the refund window restocked stay in stock. */
			$restocked = ( $lines && class_exists( 'wp_easycart_order_returns' ) ) ? wp_easycart_order_returns::restocked_units( $order_id ) : array();
			foreach ( $lines as $line ) {
				/* Claim the line first: only the request that flips stock_adjusted takes its stock ( two paths at once cannot both ). */
				if ( 1 !== (int) $wpdb->query( $wpdb->prepare( 'UPDATE ec_orderdetail SET stock_adjusted = 1 WHERE orderdetail_id = %d AND stock_adjusted = 0', (int) $line->orderdetail_id ) ) ) {
					continue;
				}
				$units = (int) $line->quantity - ( isset( $restocked[ (int) $line->orderdetail_id ] ) ? (int) $restocked[ (int) $line->orderdetail_id ] : 0 );
				if ( $units <= 0 ) {
					continue;
				}
				$product = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_product WHERE product_id = %d', (int) $line->product_id ) );
				if ( ! $product ) {
					continue;
				}
				$taken += $units;
				$oiq_id = 0;
				$old    = (int) $product->stock_quantity;
				if ( $product->use_optionitem_quantity_tracking ) {
					$oiq = $wpdb->get_row( $wpdb->prepare( 'SELECT optionitemquantity_id, quantity FROM ec_optionitemquantity WHERE product_id = %d AND optionitem_id_1 = %d AND optionitem_id_2 = %d AND optionitem_id_3 = %d AND optionitem_id_4 = %d AND optionitem_id_5 = %d', $line->product_id, $line->optionitem_id_1, $line->optionitem_id_2, $line->optionitem_id_3, $line->optionitem_id_4, $line->optionitem_id_5 ) );
					if ( $oiq ) {
						$oiq_id = (int) $oiq->optionitemquantity_id;
						$old    = (int) $oiq->quantity;
					}
					$db->update_quantity_value( $units, $line->product_id, $line->optionitem_id_1, $line->optionitem_id_2, $line->optionitem_id_3, $line->optionitem_id_4, $line->optionitem_id_5 );
				}
				$db->update_product_stock( $line->product_id, $units );
				$wpdb->insert(
					'ec_order_log',
					array(
						'order_id'      => (int) $order_id,
						'order_log_key' => 'order-stock-update',
					)
				);
				$log_id   = (int) $wpdb->insert_id;
				$log_meta = array(
					'product_id' => (string) $line->product_id,
					'quantity'   => '-' . $units,
				);
				foreach ( $log_meta as $meta_key => $meta_value ) {
					$wpdb->insert(
						'ec_order_log_meta',
						array(
							'order_log_id'         => $log_id,
							'order_id'             => (int) $order_id,
							'order_log_meta_key'   => $meta_key,
							'order_log_meta_value' => $meta_value,
						)
					);
				}
				do_action(
					'wpeasycart_inventory_stock_changed',
					array(
						'product_id'            => (int) $line->product_id,
						'optionitemquantity_id' => $oiq_id,
						'old_quantity'          => $old,
						'new_quantity'          => $old - $units,
						'delta'                 => -1 * $units,
						'reason'                => 'order',
						'source'                => 'order',
						'note'                  => $note,
						'user_id'               => get_current_user_id(),
					)
				);
			}
			return $taken;
		}

		/**
		 * Leave the merchant a note on the order ( a staff comment on the order screen ) and in the gateway log.
		 *
		 * @param int    $order_id Order.
		 * @param string $text     Note.
		 * @param mixed  $data     Payment details for the log.
		 */
		private static function note( $order_id, $text, $data = null ) {
			global $wpdb;
			if ( function_exists( 'wp_easycart_order_note' ) ) {
				wp_easycart_order_note( $order_id, $text, 'WP EasyCart' ); /* 6.0.2: the shared helper */
			} else {
				$wpdb->insert(
					'ec_order_log',
					array(
						'order_id'      => (int) $order_id,
						'order_log_key' => 'staff-comment',
					)
				);
				$log_id = (int) $wpdb->insert_id;
				$meta   = array(
					'comment' => $text,
					'author'  => 'WP EasyCart',
				);
				foreach ( $meta as $meta_key => $meta_value ) {
					$wpdb->insert(
						'ec_order_log_meta',
						array(
							'order_log_id'         => $log_id,
							'order_id'             => (int) $order_id,
							'order_log_meta_key'   => $meta_key,
							'order_log_meta_value' => $meta_value,
						)
					);
				}
			}
			$db = new ec_db();
			$db->insert_response( (int) $order_id, 1, 'Pay Link', $text . ( null !== $data ? "\n" . print_r( $data, true ) : '' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r -- the gateway log records the payment for the merchant.
		}

		/**
		 * Write an order log entry with its meta ( empty values left out ).
		 *
		 * @param int    $order_id Order.
		 * @param string $key      Log key.
		 * @param array  $meta     Meta key => value.
		 */
		private static function log( $order_id, $key, $meta ) {
			global $wpdb;
			$wpdb->insert(
				'ec_order_log',
				array(
					'order_id'      => (int) $order_id,
					'order_log_key' => $key,
				)
			);
			$log_id = (int) $wpdb->insert_id;
			foreach ( $meta as $meta_key => $meta_value ) {
				if ( '' === (string) $meta_value ) {
					continue;
				}
				$wpdb->insert(
					'ec_order_log_meta',
					array(
						'order_log_id'         => $log_id,
						'order_id'             => (int) $order_id,
						'order_log_meta_key'   => $meta_key,
						'order_log_meta_value' => (string) $meta_value,
					)
				);
			}
		}

		/**
		 * Is this payment already on the order's timeline ( a balance payment that the webhook and the page both finish )?
		 *
		 * @param int    $order_id  Order.
		 * @param string $reference Gateway payment id.
		 * @return bool
		 */
		private static function logged_payment( $order_id, $reference ) {
			global $wpdb;
			if ( '' === $reference ) {
				return false;
			}
			return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT ec_order_log_meta.order_log_meta_id FROM ec_order_log_meta INNER JOIN ec_order_log ON ec_order_log.order_log_id = ec_order_log_meta.order_log_id WHERE ec_order_log.order_id = %d AND ec_order_log.order_log_key IN ( 'order-paid-online', 'order-marked-paid' ) AND ec_order_log_meta.order_log_meta_key = 'reference' AND ec_order_log_meta.order_log_meta_value = %s LIMIT 1", (int) $order_id, $reference ) );
		}

		/**
		 * Record a payment on the order, once: payment details, stock, an approved status, the paid hooks and the receipt.
		 *
		 * 6.0.2: the amount is added to what the order has been paid ( wp_easycart_order_payments ). A payment toward the
		 * balance of an order that was already paid ( is_balance() ) only does that, logs it and emails the receipt: the order's
		 * status, payment details and stock stay as they are, and the once-per-order paid hooks do not fire again.
		 *
		 * @param object $order   Order.
		 * @param array  $payment gateway, status ( approved status id ), and any of method, transaction, charge, last4,
		 *                        exp_month, exp_year, amount ( what was charged; default the amount due ), reference ( the
		 *                        gateway's payment id, for the timeline, when transaction is not given ).
		 * @return bool Whether the order is paid now.
		 */
		public static function complete( $order, $payment ) {
			global $wpdb;
			$order_id = (int) $order->order_id;
			$lock     = 'wpec_order_pay_' . $order_id;
			$locked   = '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK( %s, 15 )', $lock ) );
			if ( ! $locked ) {
				/* Another request is finishing this order: record this payment so it can be matched, and let that request finish. */
				self::note( $order_id, __( 'A pay link payment arrived while the order was busy and was not recorded on it. Check the payment in your gateway.', 'wp-easycart' ), $payment );
				return false;
			}
			$now      = $wpdb->get_row( $wpdb->prepare( 'SELECT ec_order.*, ec_orderstatus.is_approved FROM ec_order LEFT JOIN ec_orderstatus ON ec_orderstatus.status_id = ec_order.orderstatus_id WHERE ec_order.order_id = %d', $order_id ) );
			$approved = $now ? $now->is_approved : null;
			$balance  = $now && self::is_balance( $now );
			if ( null === $approved || ( (int) $approved && ! $balance ) ) {
				$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', $lock ) );
				/* Paid already. The same payment coming back ( the webhook and the page both finishing it ) is fine; a different one is a second charge the merchant must refund. */
				if ( $now && ( ! isset( $payment['transaction'] ) || ( (string) $payment['transaction'] !== (string) $now->gateway_transaction_id && ! self::logged_payment( $order_id, (string) $payment['transaction'] ) ) ) ) {
					self::note( $order_id, __( 'A second payment was taken on the pay link after this order was already paid. Refund it in your gateway.', 'wp-easycart' ), $payment );
				}
				return null !== $approved;
			}
			$amount    = ( isset( $payment['amount'] ) && '' !== (string) $payment['amount'] ) ? round( (float) $payment['amount'], 2 ) : self::amount_due( $now );
			$reference = ( isset( $payment['transaction'] ) && '' !== (string) $payment['transaction'] ) ? (string) $payment['transaction'] : ( isset( $payment['reference'] ) ? (string) $payment['reference'] : '' );
			$recorded  = class_exists( 'wp_easycart_order_payments' ) && wp_easycart_order_payments::ready();
			$args      = array(
				'source'    => 'pay_link',
				'gateway'   => (string) $payment['gateway'],
				'reference' => $reference,
				'balance'   => $balance,
				/* 6.0.2: kept with the payment, so a refund of it goes back through its own charge ( wp_easycart_order_refunds ). */
				'charge'    => isset( $payment['charge'] ) ? (string) $payment['charge'] : '',
				'card'      => ( isset( $payment['last4'] ) && '' !== (string) $payment['last4'] ) ? trim( ( isset( $payment['method'] ) && 'credit_card' !== $payment['method'] ? ucfirst( (string) $payment['method'] ) : '' ) . ' •••• ' . (string) $payment['last4'] ) : '',
			);
			$log_meta  = array(
				'gateway'   => (string) $payment['gateway'],
				'amount'    => (string) $amount,
				'reference' => substr( sanitize_text_field( $reference ), 0, 255 ),
				'balance'   => $balance ? '1' : '',
			);
			if ( $balance ) {
				// The balance of an order changed after it was paid. The first payment's details stay on the order ( its refunds
				// go through them ); this one is on the timeline.
				wp_easycart_order_payments::record( $order_id, $amount, $args, false );
				delete_transient( self::pending_key( $order_id ) );
				self::log( $order_id, self::LOG, $log_meta );
				$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', $lock ) );
				wp_cache_delete( 'wpeasycart-order-list-' . (int) $now->user_id, 'wpeasycart-orders' );
				wp_cache_delete( 'wpeasycart-order-' . (int) $now->user_id . '-' . $order_id, 'wpeasycart-orders' );
				wp_easycart_order_payments::announce( $order_id, $amount, $args );
				/** This action is documented in complete() below. */
				do_action(
					'wp_easycart_order_pay_complete',
					$order_id,
					array_merge(
						$payment,
						array(
							'amount'  => $amount,
							'balance' => true,
						)
					)
				);
				do_action( 'wpeasycart_order_updated', $order_id );
				if ( class_exists( 'ec_db_admin' ) && class_exists( 'ec_orderdisplay' ) ) {
					$db_admin = new ec_db_admin();
					$row      = $db_admin->get_order_row_admin( $order_id );
					if ( $row ) {
						$display = new ec_orderdisplay( $row, true, true );
						$display->send_email_receipt();
					}
				}
				return true;
			}
			$columns = array(
				'method'      => 'payment_method',
				'transaction' => 'gateway_transaction_id',
				'charge'      => 'stripe_charge_id',
				'last4'       => 'creditcard_digits',
				'exp_month'   => 'cc_exp_month',
				'exp_year'    => 'cc_exp_year',
			);
			$data    = array( 'order_gateway' => (string) $payment['gateway'] );
			foreach ( $columns as $key => $column ) {
				if ( isset( $payment[ $key ] ) && '' !== (string) $payment[ $key ] ) {
					$data[ $column ] = substr( sanitize_text_field( (string) $payment[ $key ] ), 0, 255 );
				}
			}
			$wpdb->update( 'ec_order', $data, array( 'order_id' => $order_id ) );
			self::take_stock( $order_id );
			if ( $recorded ) {
				wp_easycart_order_payments::record( $order_id, $amount, $args, false );
			}
			$db = new ec_db();
			/* 6.0.2: the status is written under the lock and announced after it ( below ): a listener that takes its own lock,
			   such as tax reporting or invoice numbers, would otherwise nest it, and MySQL before 5.7 then drops this one. */
			$db->update_order_status( $order_id, (int) $payment['status'], false );
			delete_transient( self::pending_key( $order_id ) );
			self::log( $order_id, self::LOG, $log_meta );
			$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', $lock ) );
			wp_cache_delete( 'wpeasycart-order-list-' . (int) $order->user_id, 'wpeasycart-orders' );
			wp_cache_delete( 'wpeasycart-order-' . (int) $order->user_id . '-' . $order_id, 'wpeasycart-orders' );

			if ( $recorded ) {
				wp_easycart_order_payments::announce( $order_id, $amount, $args );
			}
			do_action( 'wpeasycart_order_status_update', $order_id, (int) $payment['status'] );
			do_action( 'wpeasycart_order_paid', $order_id );
			do_action( 'wp_easycart_invoice_paid', $order_id );
			/**
			 * An order was paid from its pay link.
			 *
			 * @since 6.0.2
			 * @param int   $order_id Order.
			 * @param array $payment  gateway, status, method, transaction … ; amount and balance ( true ) when it paid the
			 *                        balance of an order already paid.
			 */
			do_action( 'wp_easycart_order_pay_complete', $order_id, $payment );

			if ( class_exists( 'ec_db_admin' ) && class_exists( 'ec_orderdisplay' ) ) {
				$db_admin = new ec_db_admin();
				$row      = $db_admin->get_order_row_admin( $order_id );
				if ( $row ) {
					$display = new ec_orderdisplay( $row, true, true );
					$display->send_email_receipt();
					$display->send_gift_cards();
				}
			}
			return true;
		}

		/**
		 * Mark an order paid that was paid outside the checkout and its pay link: a bank transfer matched in an accounting
		 * system ( WP EasyCart for Xero ), a cheque entered by staff. Under the pay link's lock it does what a pay link payment
		 * does: stock for the lines still waiting, a paid status, the paid hooks, a timeline entry and, unless asked not to,
		 * the receipt. The order's payment details stay as they are. An order already paid is left alone, and a refunded or
		 * cancelled one is refused.
		 *
		 * 6.0.2: what the order has been paid is recorded ( wp_easycart_order_payments ): marking an unpaid order paid records
		 * its balance. An order already paid that has a balance ( it was changed after it was paid ) takes a payment toward
		 * it: $args['payment'], else the whole balance, recorded and logged; its status and stock stay as they are, the receipt
		 * goes out unless asked not to, and the once-per-order paid hooks do not fire again.
		 *
		 * @since 6.0.2
		 * @param int   $order_id Order.
		 * @param array $args {
		 *     How it was paid.
		 *
		 *     @type string $source       Who marked it paid, as a key ( e.g. 'xero', 'admin' ). Default 'admin'.
		 *     @type string $source_label Its name on the order timeline ( e.g. 'Xero' ). Default: the source.
		 *     @type int    $status       An order status that counts as paid. Default 15 ( Direct Deposit Received ).
		 *     @type string $reference    The payment's reference where it was recorded.
		 *     @type float  $amount       The amount paid, for the timeline.
		 *     @type float  $payment      The payment toward the balance of an order already paid. Default: the balance.
		 *     @type string $date         The date it was paid ( Y-m-d ), for the timeline.
		 *     @type bool   $receipt      Email the customer their receipt. Default true.
		 * }
		 * @return bool|WP_Error True when this call marked it paid ( or took a payment toward its balance ), false when it was
		 *                       paid already, or why it can't be.
		 */
		public static function mark_paid( $order_id, $args = array() ) {
			global $wpdb;
			$order_id = (int) $order_id;
			$args     = wp_parse_args(
				(array) $args,
				array(
					'source'       => 'admin',
					'source_label' => '',
					'status'       => 15,
					'reference'    => '',
					'amount'       => '',
					'payment'      => '',
					'date'         => '',
					'receipt'      => true,
				)
			);
			$status   = (int) $args['status'];
			if ( ! (int) $wpdb->get_var( $wpdb->prepare( 'SELECT is_approved FROM ec_orderstatus WHERE status_id = %d', $status ) ) ) {
				return new WP_Error( 'wpec_mark_paid_status', __( 'Choose an order status that counts as paid.', 'wp-easycart' ) );
			}
			$order = $wpdb->get_row( $wpdb->prepare( 'SELECT order_id, user_id FROM ec_order WHERE order_id = %d', $order_id ) );
			if ( ! $order ) {
				return new WP_Error( 'wpec_mark_paid_order', __( 'The order could not be found.', 'wp-easycart' ) );
			}
			$lock = 'wpec_order_pay_' . $order_id;
			if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK( %s, 15 )', $lock ) ) ) {
				return new WP_Error( 'wpec_mark_paid_busy', __( 'The order is being updated. Try again in a moment.', 'wp-easycart' ) );
			}
			$now     = $wpdb->get_row( $wpdb->prepare( 'SELECT ec_order.*, ec_orderstatus.is_approved FROM ec_order LEFT JOIN ec_orderstatus ON ec_orderstatus.status_id = ec_order.orderstatus_id WHERE ec_order.order_id = %d', $order_id ) );
			$balance = $now && self::is_balance( $now );
			if ( $now && (int) $now->is_approved && ! $balance ) {
				$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', $lock ) );
				return false;
			}
			if ( $now && in_array( (int) $now->orderstatus_id, array( 16, 19 ), true ) ) {
				$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', $lock ) );
				return new WP_Error( 'wpec_mark_paid_closed', __( 'The order was refunded or cancelled, so it was not marked paid.', 'wp-easycart' ) );
			}
			$recorded = class_exists( 'wp_easycart_order_payments' ) && wp_easycart_order_payments::ready();
			$paid     = ( $now && $recorded ) ? self::amount_due( $now ) : 0.0;
			if ( $balance && '' !== (string) $args['payment'] ) {
				$paid = round( (float) $args['payment'], 2 );
			}
			if ( $balance && $paid < 0.005 ) {
				$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', $lock ) );
				return new WP_Error( 'wpec_mark_paid_amount', __( 'Enter the amount that was paid.', 'wp-easycart' ) );
			}
			$record_args = array(
				'source'       => sanitize_key( (string) $args['source'] ),
				'source_label' => (string) $args['source_label'],
				'reference'    => (string) $args['reference'],
				'balance'      => $balance,
			);
			if ( ! $balance ) {
				self::take_stock( $order_id );
				$db = new ec_db();
				$db->update_order_status( $order_id, $status, false ); /* announced after the lock, as in complete() */
			}
			if ( $recorded && $paid >= 0.005 ) {
				wp_easycart_order_payments::record( $order_id, $paid, $record_args, false );
			}
			delete_transient( self::pending_key( $order_id ) );
			$user = wp_get_current_user();
			self::log(
				$order_id,
				'order-marked-paid',
				array(
					'source'       => sanitize_key( (string) $args['source'] ),
					'source_label' => sanitize_text_field( '' !== (string) $args['source_label'] ? (string) $args['source_label'] : (string) $args['source'] ),
					'reference'    => substr( sanitize_text_field( (string) $args['reference'] ), 0, 255 ),
					'amount'       => ( '' !== (string) $args['amount'] ) ? (string) round( (float) $args['amount'], 2 ) : ( $paid >= 0.005 ? (string) $paid : '' ),
					'paid_date'    => sanitize_text_field( (string) $args['date'] ),
					'author'       => ( $user && $user->exists() ) ? $user->user_login : '',
					'balance'      => $balance ? '1' : '',
				)
			);
			$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', $lock ) );
			wp_cache_delete( 'wpeasycart-order-list-' . (int) $order->user_id, 'wpeasycart-orders' );
			wp_cache_delete( 'wpeasycart-order-' . (int) $order->user_id . '-' . $order_id, 'wpeasycart-orders' );

			if ( $recorded && $paid >= 0.005 ) {
				wp_easycart_order_payments::announce( $order_id, $paid, $record_args );
			}
			if ( ! $balance ) {
				do_action( 'wpeasycart_order_status_update', $order_id, $status );
				do_action( 'wpeasycart_order_paid', $order_id );
				do_action( 'wp_easycart_invoice_paid', $order_id );
			}
			$args['payment'] = $paid;
			$args['balance'] = $balance;
			/**
			 * An order was marked paid outside the checkout ( wp_easycart_order_pay::mark_paid() ).
			 *
			 * @since 6.0.2
			 * @param int   $order_id Order.
			 * @param array $args     source, source_label, status, reference, amount, payment ( what was recorded as paid ), date,
			 *                        receipt, balance ( true: a payment toward the balance of an order already paid ).
			 */
			do_action( 'wp_easycart_order_marked_paid', $order_id, $args );
			if ( $balance ) {
				do_action( 'wpeasycart_order_updated', $order_id );
			}

			if ( $args['receipt'] && class_exists( 'ec_db_admin' ) && class_exists( 'ec_orderdisplay' ) ) {
				$db_admin = new ec_db_admin();
				$row      = $db_admin->get_order_row_admin( $order_id );
				if ( $row ) {
					$display = new ec_orderdisplay( $row, true, true );
					$display->send_email_receipt();
					if ( ! $balance ) {
						$display->send_gift_cards();
					}
				}
			}
			return true;
		}

		/* ------------------------------------------------------------------ */
		/* Stripe                                                              */
		/* ------------------------------------------------------------------ */

		/** @return object|null The store's Stripe class. */
		private static function stripe() {
			$method = (string) get_option( 'ec_option_payment_process_method' );
			if ( 'stripe' === $method && class_exists( 'ec_stripe' ) ) {
				return new ec_stripe();
			}
			if ( 'stripe_connect' === $method && class_exists( 'ec_stripe_connect' ) ) {
				return new ec_stripe_connect();
			}
			return null;
		}

		/**
		 * The description a pay link's PaymentIntent carries ( how the webhook recognises it ).
		 *
		 * @param int $order_id Order.
		 * @return string
		 */
		private static function stripe_description( $order_id ) {
			return 'Order ' . (int) $order_id . ' (pay link)';
		}

		/**
		 * Finish a Stripe payment: the intent must be this order's, for its total, and succeeded.
		 *
		 * @param object $order Order.
		 * @param object $pi    PaymentIntent.
		 * @return bool|WP_Error
		 */
		public static function complete_intent( $order, $pi ) {
			if ( ! is_object( $pi ) || empty( $pi->id ) ) {
				return new WP_Error( 'intent', 'missing' );
			}
			if ( self::stripe_description( $order->order_id ) !== (string) ( isset( $pi->description ) ? $pi->description : '' ) ) {
				return new WP_Error( 'intent', 'mismatch' );
			}
			if ( self::cents( self::amount_due( $order ) ) !== (int) $pi->amount || strtolower( (string) $pi->currency ) !== strtolower( (string) get_option( 'ec_option_stripe_currency' ) ) ) {
				/* This order's payment, but for another amount ( the order changed after the page loaded ): never drop it silently. */
				if ( 'succeeded' === $pi->status && ! get_transient( 'wpec_pay_noted_' . md5( $pi->id ) ) ) {
					set_transient( 'wpec_pay_noted_' . md5( $pi->id ), 1, 30 * DAY_IN_SECONDS );
					/* translators: 1: Stripe payment id, 2: amount in the smallest currency unit, 3: currency. */
					self::note( $order->order_id, sprintf( __( 'Stripe payment %1$s ( %2$s %3$s ) was taken on the pay link but does not match the amount due on this order, so it was not recorded. Check it in Stripe, then record or refund it.', 'wp-easycart' ), $pi->id, (int) $pi->amount, strtoupper( (string) $pi->currency ) ) );
				}
				return new WP_Error( 'intent', 'mismatch' );
			}
			if ( 'succeeded' !== $pi->status ) {
				return false;
			}
			$payment = array(
				'gateway'     => (string) get_option( 'ec_option_payment_process_method' ),
				'status'      => 6,
				'method'      => 'credit_card',
				'transaction' => $pi->id,
				'charge'      => isset( $pi->latest_charge ) && is_string( $pi->latest_charge ) ? $pi->latest_charge : '',
				'amount'      => round( (int) $pi->amount / 100, 2 ),
			);
			$stripe  = self::stripe();
			$charge  = ( $stripe && '' !== $payment['charge'] ) ? $stripe->get_charge( $payment['charge'] ) : false;
			if ( $charge && isset( $charge->payment_method_details ) ) {
				$details = $charge->payment_method_details;
				if ( isset( $details->card ) ) {
					$payment['method']    = isset( $details->card->brand ) ? (string) $details->card->brand : 'credit_card';
					$payment['last4']     = isset( $details->card->last4 ) ? (string) $details->card->last4 : '';
					$payment['exp_month'] = isset( $details->card->exp_month ) ? (string) $details->card->exp_month : '';
					$payment['exp_year']  = isset( $details->card->exp_year ) ? (string) $details->card->exp_year : '';
				} elseif ( isset( $details->type ) ) {
					$payment['method'] = (string) $details->type;
				}
			}
			return self::complete( $order, $payment );
		}

		/**
		 * The Stripe webhook's payment_intent.succeeded: a pay link's payment is finished here when its page did not come back.
		 *
		 * @param object $intent Webhook data ( the PaymentIntent ).
		 * @return bool Whether it was a pay link's payment ( the webhook then does nothing more with it ).
		 */
		public static function webhook_intent( $intent ) {
			global $wpdb;
			if ( ! is_object( $intent ) || ! isset( $intent->description ) || ! preg_match( '/^Order (\d+) \(pay link\)$/', (string) $intent->description, $match ) ) {
				return false;
			}
			$order = $wpdb->get_row( $wpdb->prepare( 'SELECT ec_order.*, ec_orderstatus.is_approved FROM ec_order LEFT JOIN ec_orderstatus ON ec_orderstatus.status_id = ec_order.orderstatus_id WHERE ec_order.order_id = %d', (int) $match[1] ) );
			if ( $order && in_array( self::state( $order ), array( 'payable', 'pending' ), true ) ) {
				/* Only what Stripe itself says: the webhook body is never trusted ( it may not be signed ). */
				$stripe = self::stripe();
				$pi     = ( $stripe && isset( $intent->id ) && is_string( $intent->id ) && '' !== $intent->id ) ? $stripe->get_payment_intent( $intent->id ) : false;
				if ( is_object( $pi ) && ! empty( $pi->id ) ) {
					self::complete_intent( $order, $pi );
				}
			}
			return true;
		}

		/**
		 * Action wpeasycart_stripe_webhook: a pay link's payment that failed or was cancelled frees its order for another try.
		 *
		 * @param string $webhook_id   Event id.
		 * @param string $webhook_type Event type.
		 * @param object $webhook_data The PaymentIntent.
		 */
		public static function webhook_event( $webhook_id, $webhook_type, $webhook_data ) {
			if ( ! in_array( $webhook_type, array( 'payment_intent.payment_failed', 'payment_intent.canceled' ), true ) || ! is_object( $webhook_data ) || ! isset( $webhook_data->description, $webhook_data->id ) || ! preg_match( '/^Order (\d+) \(pay link\)$/', (string) $webhook_data->description, $match ) ) {
				return;
			}
			$stripe = self::stripe();
			$pi     = $stripe ? $stripe->get_payment_intent( (string) $webhook_data->id ) : false;
			if ( is_object( $pi ) && isset( $pi->status ) && in_array( $pi->status, array( 'requires_payment_method', 'canceled' ), true ) && (string) get_transient( self::pending_key( $match[1] ) ) === (string) $pi->id ) {
				delete_transient( self::pending_key( $match[1] ) );
			}
		}

		/**
		 * 6.0.2 checkout protection: the gate before a pay-link payment starts ( pauses, block list, human check ).
		 *
		 * @param object $order   Order.
		 * @param string $gateway Gateway.
		 */
		private static function protect( $order, $gateway ) {
			if ( ! class_exists( 'wp_easycart_checkout_guard' ) ) {
				return;
			}
			$gate = wp_easycart_checkout_guard::check(
				'pay_link',
				array(
					'gateway' => $gateway,
					'amount'  => self::amount_due( $order ),
					'email'   => (string) $order->user_email,
				)
			);
			if ( is_wp_error( $gate ) ) {
				wp_send_json_error(
					array(
						'message'    => wp_strip_all_tags( $gate->get_error_message() ),
						'protection' => $gate->get_error_code(),
					)
				);
			}
		}

		/**
		 * 6.0.2 checkout protection: count a declined pay-link payment.
		 *
		 * @param object $order   Order.
		 * @param string $gateway Gateway.
		 * @param string $detail  Gateway text.
		 */
		private static function protect_decline( $order, $gateway, $detail = '' ) {
			if ( class_exists( 'wp_easycart_checkout_guard' ) ) {
				wp_easycart_checkout_guard::record_decline(
					'pay_link',
					array(
						'gateway'  => $gateway,
						'amount'   => self::amount_due( $order ),
						'order_id' => (int) $order->order_id,
						'reason'   => 'declined',
						'detail'   => $detail,
					)
				);
			}
		}

		/** AJAX: the order's PaymentIntent for the Payment Element ( reused while it is still open ). */
		public static function ajax_stripe_intent() {
			$order   = wp_easycart_order_pay_guard();
			$methods = self::methods();
			$stripe  = isset( $methods['stripe'] ) ? self::stripe() : null;
			if ( ! $stripe ) {
				wp_send_json_error( array( 'message' => wp_strip_all_tags( self::text( 'pay_unavailable', __( 'Online payment is not available for this order. Please contact us to arrange payment.', 'wp-easycart' ) ) ) ) );
			}
			self::protect( $order, 'stripe' );
			$transient = 'wpec_pay_pi_' . (int) $order->order_id;
			$saved     = get_transient( $transient );
			$due       = self::amount_due( $order ); /* 6.0.2: the balance of an order changed after it was paid */
			$totals    = (object) array(
				'grand_total' => $due,
				'sub_total'   => min( (float) $order->sub_total, $due ),
			);
			$error     = wp_strip_all_tags( self::text( 'pay_error', __( 'The payment could not be completed. Please try again, or use another payment method.', 'wp-easycart' ) ) );
			/* A card saved for later ( ec_option_stripe_order_create_customer ) belongs to the order's customer, never to whoever opened the link: the intent calls read the shopper from $GLOBALS['ec_user']. */
			$viewer                    = isset( $GLOBALS['ec_user'] ) ? $GLOBALS['ec_user'] : null;
			$owner                     = is_object( $viewer ) ? clone $viewer : new stdClass();
			$row                       = (int) $order->user_id > 0 ? $GLOBALS['wpdb']->get_row( $GLOBALS['wpdb']->prepare( 'SELECT stripe_customer_id FROM ec_user WHERE user_id = %d', (int) $order->user_id ) ) : null;
			$owner->user_id            = $row ? (int) $order->user_id : 0;
			$owner->stripe_customer_id = $row ? (string) $row->stripe_customer_id : '';
			$as_owner                  = function ( $call ) use ( $owner, $viewer ) {
				$GLOBALS['ec_user'] = $owner;
				$result             = $call();
				$GLOBALS['ec_user'] = $viewer;
				return $result;
			};
			if ( is_array( $saved ) && ! empty( $saved['id'] ) ) {
				$pi = $stripe->get_payment_intent( $saved['id'] );
				if ( $pi && 'succeeded' === $pi->status && ( (string) $pi->id === (string) $order->gateway_transaction_id || self::logged_payment( (int) $order->order_id, (string) $pi->id ) ) ) {
					/* 6.0.2: an earlier payment, already on the order ( its webhook finished it ); the balance gets an intent of its own. */
					$pi = null;
					delete_transient( $transient );
				}
				if ( $pi && 'succeeded' === $pi->status && true === self::complete_intent( $order, $pi ) ) {
					wp_send_json_success( array( 'paid' => true ) );
				}
				if ( $pi && in_array( $pi->status, array( 'processing', 'requires_capture' ), true ) ) {
					/* Paid and still being processed: the form must not take a second payment. */
					self::set_pending( $order->order_id, $pi->id );
					wp_send_json_success( array( 'pending' => true ) );
				}
				if ( $pi && in_array( $pi->status, array( 'requires_payment_method', 'requires_confirmation', 'requires_action' ), true ) ) {
					/* The order's total changed since this intent was made: move the open intent to it, so an older tab cannot pay the old amount. */
					if ( self::cents( $due ) !== (int) $pi->amount && 'requires_action' !== $pi->status ) {
						$updated = $as_owner(
							function () use ( $stripe, $pi, $totals ) {
								return $stripe->update_payment_intent_total( $pi->id, $totals );
							}
						);
						$pi      = ( is_object( $updated ) && ! isset( $updated->error ) && isset( $updated->amount ) ) ? $updated : $pi;
					}
					if ( self::cents( $due ) === (int) $pi->amount ) {
						wp_send_json_success( array( 'client_secret' => $pi->client_secret ) );
					}
				}
			}
			$pi = $as_owner(
				function () use ( $stripe, $totals ) {
					return $stripe->create_payment_intent( $totals );
				}
			);
			if ( ! $pi || empty( $pi->id ) || empty( $pi->client_secret ) ) {
				wp_send_json_error( array( 'message' => $error ) );
			}
			/* The description is how the webhook and the return find this order: no description, no payment. */
			if ( ! $stripe->update_payment_intent_description( $pi->id, self::stripe_description( $order->order_id ) ) ) {
				wp_send_json_error( array( 'message' => $error ) );
			}
			if ( class_exists( 'wp_easycart_checkout_guard' ) ) {
				wp_easycart_checkout_guard::map_intent( $pi->id ); /* 6.0.2 checkout protection: its webhook declines count for this shopper */
			}
			set_transient(
				$transient,
				array(
					'id' => $pi->id,
				),
				DAY_IN_SECONDS
			);
			wp_send_json_success( array( 'client_secret' => $pi->client_secret ) );
		}

		/** AJAX: after the browser confirmed the payment. */
		public static function ajax_stripe_complete() {
			$order = wp_easycart_order_pay_guard( true );
			if ( 'paid' === self::state( $order ) ) {
				wp_send_json_success( array( 'paid' => true ) );
			}
			$stripe = self::stripe();
			$pi_id  = isset( $_POST['payment_intent'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_intent'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- wp_easycart_order_pay_guard() verified the nonce.
			$pi     = ( $stripe && '' !== $pi_id ) ? $stripe->get_payment_intent( $pi_id ) : false;
			if ( $pi && in_array( $pi->status, array( 'processing', 'requires_capture' ), true ) && self::stripe_description( $order->order_id ) === (string) ( isset( $pi->description ) ? $pi->description : '' ) ) {
				self::set_pending( $order->order_id, $pi->id );
				wp_send_json_success( array( 'pending' => true ) );
			}
			$done = $pi ? self::complete_intent( $order, $pi ) : false;
			if ( true !== $done && $pi && 'succeeded' === $pi->status && self::stripe_description( $order->order_id ) === (string) ( isset( $pi->description ) ? $pi->description : '' ) ) {
				/* Charged but not recorded ( a changed total, noted on the order, or the order was busy ): hold the form. */
				self::set_pending( $order->order_id, $pi->id );
				wp_send_json_success( array( 'pending' => true ) );
			}
			if ( true !== $done ) {
				wp_send_json_error( array( 'message' => wp_strip_all_tags( self::text( 'pay_error', __( 'The payment could not be completed. Please try again, or use another payment method.', 'wp-easycart' ) ) ) ) );
			}
			delete_transient( 'wpec_pay_pi_' . (int) $order->order_id );
			wp_send_json_success( array( 'paid' => true ) );
		}

		/* ------------------------------------------------------------------ */
		/* Square                                                              */
		/* ------------------------------------------------------------------ */

		/** AJAX: charge the card token from the Web Payments SDK. */
		public static function ajax_square() {
			$order   = wp_easycart_order_pay_guard();
			$methods = self::methods();
			if ( ! isset( $methods['square'] ) ) {
				wp_send_json_error( array( 'message' => wp_strip_all_tags( self::text( 'pay_unavailable', __( 'Online payment is not available for this order. Please contact us to arrange payment.', 'wp-easycart' ) ) ) ) );
			}
			self::billing_or_fail( $order );
			self::protect( $order, 'square' );
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- wp_easycart_order_pay_guard() verified the nonce.
			$token  = isset( $_POST['sourceId'] ) ? sanitize_text_field( wp_unslash( $_POST['sourceId'] ) ) : '';
			$verify = isset( $_POST['buyerVerificationToken'] ) ? sanitize_text_field( wp_unslash( $_POST['buyerVerificationToken'] ) ) : '';
			// phpcs:enable WordPress.Security.NonceVerification.Missing
			if ( '' === $token ) {
				wp_send_json_error( array( 'message' => wp_strip_all_tags( self::text( 'pay_error', __( 'The payment could not be completed. Please try again, or use another payment method.', 'wp-easycart' ) ) ) ) );
			}
			if ( ! class_exists( 'wp_easycart_order_pay_square' ) ) {
				require_once EC_PLUGIN_DIRECTORY . '/inc/classes/gateway/class-wp-easycart-order-pay-square.php';
			}
			$square  = new wp_easycart_order_pay_square();
			$cents   = self::cents( self::amount_due( $order ) );
			$balance = self::is_balance( $order ); /* 6.0.2: the first payment's Square ids and card stay on the order */
			$result  = $square->charge_order( $order, $token, $verify, $cents, $balance );
			if ( is_wp_error( $result ) ) {
				self::protect_decline( $order, 'square', $result->get_error_message() );
				/** This filter is documented in inc/classes/cart/ec_cartpage.php ( 6.0.2 ). */
				wp_send_json_error( array( 'message' => apply_filters( 'wpeasycart_payment_error_message', $result->get_error_message(), 'square' ) ) );
			}
			self::complete(
				$order,
				array(
					'gateway'   => 'square',
					'status'    => 6,
					'amount'    => round( $cents / 100, 2 ),
					'reference' => isset( $square->payment_id ) ? (string) $square->payment_id : '',
				)
			);
			wp_send_json_success( array( 'paid' => true ) );
		}

		/* ------------------------------------------------------------------ */
		/* PayPal                                                              */
		/* ------------------------------------------------------------------ */

		/** @return bool The WP EasyCart partner app ( rather than the store's own PayPal app ). */
		private static function paypal_partner() {
			return get_option( 'ec_option_paypal_use_sandbox' ) ? '' !== (string) get_option( 'ec_option_paypal_sandbox_merchant_id' ) : '' !== (string) get_option( 'ec_option_paypal_production_merchant_id' );
		}

		/**
		 * One PayPal API call the way the checkout makes it ( the store's app with its token, or the partner proxy ).
		 *
		 * @param string $own_url URL with the store's own app.
		 * @param array  $proxy   Through the partner app: WP EasyCart Connect's relay action and its query parameters
		 *                        ( 6.0.2: wp_easycart_paypal_connect, /paypal-v3/ ).
		 * @param mixed  $body    JSON body or null.
		 * @param string $log     Log label.
		 * @return object|null Decoded answer.
		 */
		private static function paypal_call( $own_url, $proxy, $body, $log ) {
			$db   = new ec_db();
			$args = array(
				'method'  => 'POST',
				'timeout' => 30,
			);
			if ( self::paypal_partner() ) {
				$response = wp_easycart_paypal_connect::request( $proxy['action'], $proxy['query'], ( null !== $body ) ? wp_json_encode( $body ) : null, $args );
			} else {
				if ( null !== $body ) {
					$args['body'] = wp_json_encode( $body );
				}
				$args['headers'] = array(
					'Content-Type'                  => 'application/json',
					'Authorization'                 => 'Bearer ' . ( get_option( 'ec_option_paypal_use_sandbox' ) ? get_option( 'ec_option_paypal_sandbox_access_token' ) : get_option( 'ec_option_paypal_production_access_token' ) ),
					'PayPal-Partner-Attribution-Id' => 'LevelFourDevelopment_SP_PPM',
				);
				$request         = new WP_Http();
				$response        = $request->request( $own_url, $args );
			}
			if ( is_wp_error( $response ) ) {
				$db->insert_response( 0, 1, 'PayPal Pay Link ' . $log . ' CURL ERROR', $response->get_error_message() );
				return null;
			}
			$db->insert_response( 0, 0, 'PayPal Pay Link ' . $log, print_r( $response, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r -- the gateway log records the raw answer, as the checkout does.
			$json = json_decode( (string) wp_remote_retrieve_body( $response ) );
			return is_object( $json ) ? $json : null;
		}

		/** @return string The PayPal merchant id of the partner app, or ''. */
		private static function paypal_merchant() {
			return (string) ( get_option( 'ec_option_paypal_use_sandbox' ) ? get_option( 'ec_option_paypal_sandbox_merchant_id' ) : get_option( 'ec_option_paypal_production_merchant_id' ) );
		}

		/** AJAX: create the PayPal order for this order's total. */
		public static function ajax_paypal_create() {
			$order   = wp_easycart_order_pay_guard();
			$methods = self::methods();
			if ( ! isset( $methods['paypal'] ) ) {
				wp_send_json_error( array( 'message' => wp_strip_all_tags( self::text( 'pay_unavailable', __( 'Online payment is not available for this order. Please contact us to arrange payment.', 'wp-easycart' ) ) ) ) );
			}
			self::billing_or_fail( $order );
			self::protect( $order, 'paypal' );
			$paypal = new ec_paypal();
			$paypal->handle_token();
			$currency = strtoupper( (string) get_option( 'ec_option_paypal_currency_code' ) );
			$due      = self::amount_due( $order ); /* 6.0.2: the balance of an order changed after it was paid */
			$value    = number_format( $due, 2, '.', '' );
			$unit     = array(
				'reference_id' => 'WPEASYCART_PAYLINK_' . (int) $order->order_id,
				'custom_id'    => (string) (int) $order->order_id,
				'invoice_id'   => 'WPEC-' . substr( md5( home_url() ), 0, 6 ) . '-' . (int) $order->order_id,
				/* translators: %d: order number. */
				'description'  => sprintf( __( 'Order %d', 'wp-easycart' ), (int) $order->order_id ),
				'amount'       => array(
					'currency_code' => $currency,
					'value'         => $value,
				),
			);
			if ( self::paypal_partner() ) {
				$unit['payee'] = array( 'merchant_id' => self::paypal_merchant() );
			}
			$fee_rate = apply_filters( 'wp_easycart_stripe_connect_fee_rate', 2 );
			if ( ( $due * $fee_rate ) >= 0.01 ) {
				$unit['payment_instruction'] = array(
					'platform_fees' => array(
						array(
							'payee'  => array(
								'email_address' => get_option( 'ec_option_paypal_use_sandbox' ) ? 'paypal-partner-facilitator@wpeasycart.com' : 'paypal-partner@wpeasycart.com',
								'merchant_id'   => get_option( 'ec_option_paypal_use_sandbox' ) ? '55LV5HAER3DNG' : 'U4HGH5W64EUBC',
							),
							'amount' => array(
								'value'         => number_format( $due * $fee_rate / 100, 2, '.', '' ),
								'currency_code' => $currency,
							),
						),
					),
				);
			}
			$page    = self::url( $order );
			$body    = array(
				'intent'         => 'CAPTURE',
				'purchase_units' => array( $unit ),
				'payment_source' => array(
					'paypal' => array(
						'experience_context' => array(
							'shipping_preference' => 'NO_SHIPPING',
							'user_action'         => 'PAY_NOW',
							'return_url'          => $page,
							'cancel_url'          => $page,
						),
					),
				),
			);
			$sandbox = (bool) get_option( 'ec_option_paypal_use_sandbox' );
			$json    = self::paypal_call(
				$sandbox ? 'https://api-m.sandbox.paypal.com/v2/checkout/orders' : 'https://api-m.paypal.com/v2/checkout/orders',
				array(
					'action' => 'create_order',
					'query'  => array( 'merchantID' => self::paypal_merchant() ),
				),
				$body,
				'Create Order'
			);
			if ( ! $json || empty( $json->id ) ) {
				wp_send_json_error( array( 'message' => wp_strip_all_tags( self::text( 'pay_error', __( 'The payment could not be completed. Please try again, or use another payment method.', 'wp-easycart' ) ) ) ) );
			}
			/* Only a PayPal order made here ( this total, currency and payee ) can be captured for this order. */
			set_transient(
				'wpec_pay_pp_' . (int) $order->order_id,
				array(
					'id'       => (string) $json->id,
					'value'    => $value,
					'currency' => $currency,
				),
				DAY_IN_SECONDS
			);
			wp_send_json_success( array( 'id' => (string) $json->id ) );
		}

		/** AJAX: capture the approved PayPal order and record it. */
		public static function ajax_paypal_capture() {
			$order = wp_easycart_order_pay_guard( true );
			if ( 'paid' === self::state( $order ) ) {
				wp_send_json_success( array( 'paid' => true ) );
			}
			$paypal_id = isset( $_POST['paypal_order'] ) ? preg_replace( '/[^A-Za-z0-9\-]/', '', sanitize_text_field( wp_unslash( $_POST['paypal_order'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- wp_easycart_order_pay_guard() verified the nonce.
			$created   = get_transient( 'wpec_pay_pp_' . (int) $order->order_id );
			if ( '' === $paypal_id || ! is_array( $created ) || empty( $created['id'] ) || ! hash_equals( (string) $created['id'], $paypal_id ) || number_format( self::amount_due( $order ), 2, '.', '' ) !== (string) $created['value'] ) {
				wp_send_json_error( array( 'message' => wp_strip_all_tags( self::text( 'pay_error', __( 'The payment could not be completed. Please try again, or use another payment method.', 'wp-easycart' ) ) ) ) );
			}
			$sandbox = (bool) get_option( 'ec_option_paypal_use_sandbox' );
			$webhook = wp_easycart_hook_url( 'paypal-webhook' );
			$json    = self::paypal_call(
				( $sandbox ? 'https://api-m.sandbox.paypal.com/v2/checkout/orders/' : 'https://api-m.paypal.com/v2/checkout/orders/' ) . $paypal_id . '/capture',
				array(
					'action' => 'capture',
					'query'  => array(
						'merchantID' => self::paypal_merchant(),
						'webhookURL' => $webhook,
						'orderID'    => $paypal_id,
					),
				),
				null,
				'Capture'
			);
			$capture = ( $json && isset( $json->purchase_units[0]->payments->captures[0] ) ) ? $json->purchase_units[0]->payments->captures[0] : null;
			$ours    = $capture && isset( $capture->custom_id ) ? (string) $capture->custom_id === (string) (int) $order->order_id : ( $json && isset( $json->purchase_units[0]->custom_id ) && (string) $json->purchase_units[0]->custom_id === (string) (int) $order->order_id );
			$amount  = ( $capture && isset( $capture->amount->value ) ) ? number_format( (float) $capture->amount->value, 2, '.', '' ) : '';
			$paid_in = ( $capture && isset( $capture->amount->currency_code ) ) ? strtoupper( (string) $capture->amount->currency_code ) : '';
			if ( ! $capture || ! $ours || number_format( self::amount_due( $order ), 2, '.', '' ) !== $amount || (string) $created['currency'] !== $paid_in ) {
				if ( ! $capture ) {
					self::protect_decline( $order, 'paypal', 'capture_failed' );
				}
				if ( $capture && isset( $capture->id ) ) {
					/* translators: %s: PayPal capture id. */
					self::note( $order->order_id, sprintf( __( 'PayPal payment %s was captured on the pay link but does not match this order, so it was not recorded. Check it in PayPal, then record or refund it.', 'wp-easycart' ), (string) $capture->id ), $json );
				}
				wp_send_json_error( array( 'message' => wp_strip_all_tags( self::text( 'pay_error', __( 'The payment could not be completed. Please try again, or use another payment method.', 'wp-easycart' ) ) ) ) );
			}
			delete_transient( 'wpec_pay_pp_' . (int) $order->order_id );
			if ( 'COMPLETED' !== strtoupper( (string) $capture->status ) && self::is_balance( $order ) ) {
				// 6.0.2: a balance payment held by PayPal for review. The order stays as it is ( it was paid before ); the form waits
				// and the merchant records the payment once PayPal releases it.
				self::set_pending( (int) $order->order_id, (string) $capture->id );
				/* translators: 1: PayPal capture id, 2: amount. */
				self::note( $order->order_id, sprintf( __( 'PayPal is holding payment %1$s ( %2$s ) toward this order’s balance for review. When PayPal releases it, record it on the order with Record payment.', 'wp-easycart' ), (string) $capture->id, $amount ), $json );
				wp_send_json_success( array( 'pending' => true ) );
			}
			if ( 'COMPLETED' !== strtoupper( (string) $capture->status ) ) {
				/* Held by PayPal for review: the order waits ( Third Party Pending ) until PayPal releases it. */
				global $wpdb;
				$wpdb->update(
					'ec_order',
					array(
						'gateway_transaction_id' => substr( (string) $capture->id, 0, 255 ),
						'order_gateway'          => 'paypal-express',
					),
					array( 'order_id' => (int) $order->order_id )
				);
				$db = new ec_db();
				$db->update_order_status( (int) $order->order_id, 8 );
				wp_send_json_success( array( 'pending' => true ) );
			}
			self::complete(
				$order,
				array(
					'gateway'     => 'paypal-express',
					'status'      => 10,
					'method'      => 'PayPal',
					'transaction' => (string) $capture->id,
					'amount'      => (float) $amount,
				)
			);
			wp_send_json_success( array( 'paid' => true ) );
		}
	}

	wp_easycart_order_pay::init();

endif;
