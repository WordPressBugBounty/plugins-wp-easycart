<?php
/**
 * PayPal notifications ( webhooks ): registration, what came of it, and the drawer's Notifications group ( 6.0.2 ).
 *
 * A store connected through WP EasyCart Connect ( a merchant id per mode ) gets PayPal's events from Connect, which
 * checks PayPal's signature and forwards each event with the key this store registered, in the
 * X-EasyCart-Notification-Key header ( as Square's do ). A store on its own PayPal app ( app id and secret ) creates its
 * own webhook with PayPal, and the receiver checks PayPal's signature against it. The receiver is the paypal-webhook
 * branch of wp_easycart_webhook_catch() ( wpeasycart.php ), with wp_easycart_paypal_webhook_verified().
 *
 * register() registers the mode in use ( sandbox or live ): once when the account is connected or the mode changes,
 * from the Payments drawer ( Secure notifications / Register again ), and from a checkout only through WP-Cron, when
 * nothing is registered for the mode and nothing was tried in the last hour ( ensure() ). Before 6.0.2 every PayPal
 * checkout of a store on WP EasyCart Connect called Connect first ( with a 30 second timeout ).
 *
 * Options: ec_option_paypal_wpeasycart_{mode}_webhook_id ( Connect's registration ), ec_option_paypal_{mode}_webhook_key
 * ( the key, kept only once Connect answers key_saved ), ec_option_paypal_{mode}_webhook_id ( own app ),
 * ec_option_paypal_webhook_error ( the last registration problem for the mode in use ), ec_option_paypal_webhook_state
 * ( per mode: the key being registered, when Connect last answered without a key, the last try ), and the receiver's
 * ec_option_paypal_webhook_log / ec_option_paypal_webhook_last ( wp_easycart_gateway_webhook_log() ).
 *
 * The Connect side of the contract: GitHub/wp-easycart-connect/docs/paypal-webhook-key.md and docs/paypal-v3.md ( outside
 * the plugins ). Every call to Connect goes through wp_easycart_paypal_connect ( /paypal-v3/, filter
 * wp_easycart_paypal_connect_url for its address ).
 *
 * @package wp-easycart
 * @since 6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_paypal_webhooks' ) ) :

	/**
	 * PayPal notifications for the mode in use.
	 */
	final class wp_easycart_paypal_webhooks {

		/** WP-Cron hook a checkout schedules when nothing is registered. */
		const CRON = 'wp_easycart_paypal_webhook_register';

		/** A checkout asks again at most this often ( seconds ). */
		const RETRY = 3600;

		/** Seconds to wait for WP EasyCart Connect or PayPal ( Connect calls the store back to confirm a key before it answers ). */
		const TIMEOUT = 15;

		/** The event WP EasyCart Connect posts to confirm a key before it keeps it ( receiver: key_check() ). */
		const KEY_CHECK = 'WPEASYCART.KEY.CHECK';

		/** The events an own-app webhook subscribes to ( the list the store has always used, and since 6.0.2 the Orders v2 capture's pending, denied and reversed ). */
		const OWN_EVENTS = 'PAYMENT.AUTHORIZATION.CREATED,PAYMENT.AUTHORIZATION.VOIDED,PAYMENT.CAPTURE.COMPLETED,PAYMENT.CAPTURE.REFUNDED,PAYMENT.SALE.COMPLETED,PAYMENT.SALE.REFUNDED,PAYMENT.SALE.PENDING,CHECKOUT.ORDER.PROCESSED,PAYMENT.ORDER.CANCELLED,PAYMENT.ORDER.CREATED,PAYMENT.CAPTURE.PENDING,PAYMENT.CAPTURE.DENIED,PAYMENT.CAPTURE.REVERSED';

		/** Orders v2 capture events capture_event() acts on => what each does. */
		const CAPTURE_EVENTS = array(
			'PAYMENT.CAPTURE.COMPLETED' => 'completed',
			'PAYMENT.CAPTURE.PENDING'   => 'pending',
			'PAYMENT.CAPTURE.DENIED'    => 'denied',
			'PAYMENT.CAPTURE.REVERSED'  => 'reversed',
		);

		/** Hooks. Called once, below. */
		public static function init() {
			add_action( self::CRON, array( __CLASS__, 'run_scheduled' ) );
			add_action( 'update_option_ec_option_paypal_use_sandbox', array( __CLASS__, 'mode_changed' ), 10, 2 );
			add_action( 'wp_easycart_payment_gateway_mode', array( __CLASS__, 'gateway_changed' ), 10, 2 );
			add_action( 'wp_easycart_payment_gateway_toggled', array( __CLASS__, 'gateway_changed' ), 10, 2 );
		}

		/* ------------------------------------------------------------------ */
		/* Facts                                                               */
		/* ------------------------------------------------------------------ */

		/**
		 * The mode in use.
		 *
		 * @return string sandbox | production
		 */
		public static function mode() {
			return get_option( 'ec_option_paypal_use_sandbox' ) ? 'sandbox' : 'production';
		}

		/**
		 * A mode name, or the one in use.
		 *
		 * @param string $mode sandbox | production | ''.
		 * @return string
		 */
		private static function pick( $mode ) {
			return ( 'sandbox' === $mode || 'production' === $mode ) ? $mode : self::mode();
		}

		/**
		 * How the store is connected to PayPal in a mode.
		 *
		 * @param string $mode sandbox | production | '' ( the mode in use ).
		 * @return string connect ( WP EasyCart Connect ) | own ( the store's own PayPal app ) | '' ( neither )
		 */
		public static function connection( $mode = '' ) {
			$mode = self::pick( $mode );
			if ( '' !== (string) get_option( 'ec_option_paypal_' . $mode . '_merchant_id' ) ) {
				return 'connect';
			}
			if ( '' !== (string) get_option( 'ec_option_paypal_' . $mode . '_app_id' ) && '' !== (string) get_option( 'ec_option_paypal_' . $mode . '_secret' ) ) {
				return 'own';
			}
			return '';
		}

		/**
		 * Is anything registered for notifications in a mode?
		 *
		 * @param string $mode sandbox | production | ''.
		 * @return bool
		 */
		public static function registered( $mode = '' ) {
			$mode = self::pick( $mode );
			switch ( self::connection( $mode ) ) {
				case 'connect':
					return '' !== (string) get_option( 'ec_option_paypal_wpeasycart_' . $mode . '_webhook_id' );
				case 'own':
					return '' !== (string) get_option( 'ec_option_paypal_' . $mode . '_webhook_id' );
			}
			return false;
		}

		/**
		 * The key Connect should be forwarding with ( '' = unsigned ).
		 *
		 * @param string $mode sandbox | production | ''.
		 * @return string
		 */
		public static function key( $mode = '' ) {
			return (string) get_option( 'ec_option_paypal_' . self::pick( $mode ) . '_webhook_key' );
		}

		/**
		 * Registration state of a mode.
		 *
		 * @param string $mode sandbox | production.
		 * @return array pending ( key sent, not yet confirmed ), unsigned ( when Connect last answered without a key ), tried,
		 *               unconfirmed ( when Connect last said it could not get this store to confirm the key ).
		 */
		private static function state( $mode ) {
			$all = get_option( 'ec_option_paypal_webhook_state' );
			$one = ( is_array( $all ) && isset( $all[ $mode ] ) && is_array( $all[ $mode ] ) ) ? $all[ $mode ] : array();
			return array(
				'pending'     => isset( $one['pending'] ) ? (string) $one['pending'] : '',
				'unsigned'    => isset( $one['unsigned'] ) ? (int) $one['unsigned'] : 0,
				'tried'       => isset( $one['tried'] ) ? (int) $one['tried'] : 0,
				'unconfirmed' => isset( $one['unconfirmed'] ) ? (int) $one['unconfirmed'] : 0,
			);
		}

		/**
		 * Change part of a mode's registration state.
		 *
		 * @param string $mode    sandbox | production.
		 * @param array  $changes Keys of state() to set.
		 * @return void
		 */
		private static function set_state( $mode, $changes ) {
			$all          = get_option( 'ec_option_paypal_webhook_state' );
			$all          = is_array( $all ) ? $all : array();
			$all[ $mode ] = array_merge( self::state( $mode ), array_intersect_key( (array) $changes, array_flip( array( 'pending', 'unsigned', 'tried', 'unconfirmed' ) ) ) );
			update_option( 'ec_option_paypal_webhook_state', $all, false );
		}

		/**
		 * The key sent with a registration Connect has not confirmed yet. The receiver accepts it besides the stored key,
		 * so notifications keep arriving whichever of the two Connect holds when an answer is lost.
		 *
		 * @param string $mode sandbox | production | ''.
		 * @return string
		 */
		public static function pending_key( $mode = '' ) {
			$state = self::state( self::pick( $mode ) );
			return $state['pending'];
		}

		/**
		 * WP EasyCart Connect's key check ( 6.0.2 ). Before Connect keeps a key for this store it posts a WPEASYCART.KEY.CHECK
		 * event carrying the key ( X-EasyCart-Notification-Key ) and a challenge; the answer repeats the challenge only when
		 * the key is the one this store is registering ( or already holds ) for that mode. Merchant ids and store addresses
		 * are public, so without it anyone could register a key of their own for this store and have its real
		 * notifications turned away.
		 *
		 * @param object $event The posted event ( is_sandbox, challenge ).
		 * @return string The challenge to answer with, or '' to refuse.
		 */
		public static function key_check( $event ) {
			if ( ! is_object( $event ) || ! function_exists( 'wp_easycart_gateway_webhook_key_ok' ) ) {
				return '';
			}
			$mode      = ( isset( $event->is_sandbox ) && 1 === (int) $event->is_sandbox ) ? 'sandbox' : 'production';
			$challenge = ( isset( $event->challenge ) && is_scalar( $event->challenge ) ) ? preg_replace( '/[^A-Za-z0-9]/', '', (string) $event->challenge ) : '';
			if ( '' === $challenge || strlen( $challenge ) > 128 ) {
				return '';
			}
			foreach ( array( self::pending_key( $mode ), self::key( $mode ) ) as $expected ) {
				if ( '' !== $expected && wp_easycart_gateway_webhook_key_ok( $expected ) ) {
					return $challenge;
				}
			}
			return '';
		}

		/* ------------------------------------------------------------------ */
		/* Events                                                              */
		/* ------------------------------------------------------------------ */

		/**
		 * An Orders v2 capture event, already verified and claimed by the receiver ( wpeasycart.php, paypal-webhook ). Checkout
		 * and the pay link keep the capture's id on the order ( gateway_transaction_id ); a capture PayPal holds leaves the
		 * order at Third Party Pending ( 8 ) with no stock taken and no receipt sent.
		 *
		 *  - completed: an unpaid order is marked paid ( wp_easycart_order_pay::mark_paid(): stock, Third Party Approved, the paid
		 *    hooks, the receipt ). An order paid at checkout ( the usual case ), refunded or cancelled is left alone.
		 *  - pending: an unpaid order goes to Third Party Pending.
		 *  - denied: an unpaid order goes to Card Denied, with a staff note.
		 *  - reversed ( the buyer's bank or PayPal took the payment back ): Third Party Error, as the Payments API's reversal
		 *    did, with a staff note; a refunded or cancelled order is left alone.
		 *
		 * @since 6.0.2
		 * @param string $type  The event_type.
		 * @param object $event The event.
		 * @return bool False when this is not a capture event it can read ( the receiver logs it as unmatched ).
		 */
		public static function capture_event( $type, $event ) {
			global $wpdb;
			$events = self::CAPTURE_EVENTS;
			if ( ! isset( $events[ $type ] ) || ! is_object( $event ) || ! isset( $event->resource ) || ! is_object( $event->resource ) ) {
				return false;
			}
			$capture = self::capture_id( $event->resource );
			if ( '' === $capture ) {
				return false;
			}
			$what     = $events[ $type ];
			$db_admin = new ec_db_admin();
			$label    = 'PayPal Webhook ' . strtoupper( $what ) . ' Response';
			$order_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT order_id FROM ec_order WHERE gateway_transaction_id = %s ORDER BY order_id DESC LIMIT 1', $capture ) );
			if ( ! $order_id ) {
				$db_admin->insert_response( 0, 0, $label, 'No order match! ---- ' . print_r( $event, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r -- gateway log entry, as the other PayPal events.
				return true;
			}
			$db_admin->insert_response( $order_id, 0, $label, print_r( $event, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r -- gateway log entry, as the other PayPal events.
			$order = $wpdb->get_row( $wpdb->prepare( 'SELECT ec_order.order_id, ec_order.orderstatus_id, ec_orderstatus.is_approved FROM ec_order LEFT JOIN ec_orderstatus ON ec_orderstatus.status_id = ec_order.orderstatus_id WHERE ec_order.order_id = %d', $order_id ) );
			if ( ! $order ) {
				return true;
			}
			$status = (int) $order->orderstatus_id;
			$paid   = ! empty( $order->is_approved );
			$closed = in_array( $status, array( 16, 19 ), true );
			$amount = ( isset( $event->resource->amount->value ) && is_numeric( $event->resource->amount->value ) ) ? (float) $event->resource->amount->value : '';

			if ( 'completed' === $what ) {
				if ( $paid || $closed || ! class_exists( 'wp_easycart_order_pay' ) ) {
					return true;
				}
				wp_easycart_order_pay::mark_paid(
					$order_id,
					array(
						'source'       => 'paypal',
						'source_label' => 'PayPal',
						'status'       => 10,
						'reference'    => $capture,
						'amount'       => $amount,
					)
				);
			} elseif ( 'pending' === $what ) {
				if ( ! $paid && ! $closed && 8 !== $status ) {
					$db_admin->update_order_status( $order_id, 8 );
				}
			} elseif ( 'denied' === $what ) {
				if ( ! $paid && ! $closed && 7 !== $status ) {
					$db_admin->update_order_status( $order_id, 7 );
					if ( function_exists( 'wp_easycart_order_note' ) ) {
						/* translators: %s: PayPal capture id. */
						wp_easycart_order_note( $order_id, sprintf( __( 'PayPal denied payment %s, which it had been holding. No payment was taken for this order.', 'wp-easycart' ), $capture ) );
					}
				}
			} elseif ( 'reversed' === $what ) {
				if ( ! $closed && 9 !== $status ) {
					$db_admin->update_order_status( $order_id, 9 );
					if ( function_exists( 'wp_easycart_order_note' ) ) {
						/* translators: %s: PayPal capture id. */
						wp_easycart_order_note( $order_id, sprintf( __( 'PayPal reversed payment %s ( the buyer disputed it or their bank took it back ). Check the case in PayPal before shipping or refunding.', 'wp-easycart' ), $capture ) );
					}
				}
			}
			return true;
		}

		/**
		 * The capture an Orders v2 event is about: a refund or reversal links "up" to its capture; a capture event's
		 * resource is the capture.
		 *
		 * @param object $resource The event's resource.
		 * @return string The capture id, '' when there is none.
		 */
		private static function capture_id( $resource ) {
			if ( isset( $resource->links ) && is_array( $resource->links ) ) {
				foreach ( $resource->links as $link ) {
					if ( is_object( $link ) && isset( $link->rel, $link->href ) && 'up' === $link->rel && preg_match( '#/v2/payments/captures/([A-Za-z0-9-]{5,64})#', (string) $link->href, $match ) ) {
						return $match[1];
					}
				}
			}
			$id = ( isset( $resource->id ) && is_scalar( $resource->id ) ) ? (string) $resource->id : '';
			return preg_match( '/^[A-Za-z0-9-]{5,64}$/', $id ) ? $id : '';
		}

		/* ------------------------------------------------------------------ */
		/* When to register                                                    */
		/* ------------------------------------------------------------------ */

		/**
		 * Register when nothing is registered for the mode in use. From a checkout ( 'checkout' ) this only schedules
		 * WP-Cron, at most once an hour, so a shopper never waits on it; from an admin change ( 'admin' ) it registers now.
		 *
		 * @param string $context checkout | admin.
		 * @return bool Whether a registration ran or was scheduled.
		 */
		public static function ensure( $context = 'checkout' ) {
			$mode = self::mode();
			if ( '' === self::connection( $mode ) || self::registered( $mode ) ) {
				return false;
			}
			if ( 'admin' === $context ) {
				self::register( array( 'context' => 'admin' ) );
				return true;
			}
			$state = self::state( $mode );
			if ( $state['tried'] > time() - self::RETRY || false !== wp_next_scheduled( self::CRON ) ) {
				return false;
			}
			self::set_state( $mode, array( 'tried' => time() ) );
			wp_schedule_single_event( time(), self::CRON );
			return true;
		}

		/** WP-Cron: the registration a checkout asked for, if it is still needed. */
		public static function run_scheduled() {
			if ( '' === self::connection() || self::registered() ) {
				return;
			}
			self::register( array( 'context' => 'cron' ) );
		}

		/**
		 * The mode changed ( ec_option_paypal_use_sandbox ): the last problem was about the other mode.
		 *
		 * @param mixed $old_value Previous value.
		 * @param mixed $value     New value.
		 * @return void
		 */
		public static function mode_changed( $old_value = null, $value = null ) {
			if ( (string) (int) $old_value === (string) (int) $value ) {
				return;
			}
			update_option( 'ec_option_paypal_webhook_error', '', false );
		}

		/**
		 * Settings › Payments switched PayPal on, or between sandbox and live, from its card.
		 *
		 * @param string $key  Gateway key.
		 * @param bool   $flag On ( toggled ) or test ( mode ).
		 * @return void
		 */
		public static function gateway_changed( $key, $flag = true ) {
			if ( 'paypal' !== $key ) {
				return;
			}
			if ( 'wp_easycart_payment_gateway_toggled' === current_filter() && ! $flag ) {
				return;
			}
			self::ensure( 'admin' );
		}

		/**
		 * Before a PayPal settings save: what matters for notifications.
		 *
		 * @return array mode, active.
		 */
		public static function snapshot() {
			return array(
				'mode'   => self::mode(),
				'active' => ( 'paypal' === (string) get_option( 'ec_option_payment_third_party' ) && (bool) get_option( 'ec_option_paypal_enable_pay_now' ) ),
			);
		}

		/**
		 * After a PayPal settings save ( the drawer's switches and options ): register when PayPal was just switched on or
		 * moved between sandbox and live and nothing is registered for the mode now in use. Other saves call nothing.
		 *
		 * @param array $before snapshot() taken before the save.
		 * @return void
		 */
		public static function settings_saved( $before ) {
			$now = self::snapshot();
			if ( ! $now['active'] ) {
				return;
			}
			if ( empty( $before['active'] ) || ! isset( $before['mode'] ) || $before['mode'] !== $now['mode'] ) {
				self::ensure( 'admin' );
			}
		}

		/* ------------------------------------------------------------------ */
		/* Registering                                                         */
		/* ------------------------------------------------------------------ */

		/**
		 * Register the mode in use for notifications.
		 *
		 * @param array $args {
		 *     Optional. How to register.
		 *
		 *     @type bool   $rotate  Send a new random key ( Secure notifications, Register again, a new connection ).
		 *     @type string $context admin | connect | cron | legacy, for the gateway log.
		 *     @type int    $timeout Seconds to wait.
		 * }
		 * @return array status(): state, secured, error, … plus message ( a sentence for the merchant, '' on failure ).
		 */
		public static function register( $args = array() ) {
			$args = array_merge(
				array(
					'rotate'  => false,
					'context' => 'admin',
					'timeout' => self::TIMEOUT,
				),
				(array) $args
			);
			$mode = self::mode();
			$conn = self::connection( $mode );
			if ( '' === $conn ) {
				$status            = self::status( $mode );
				$status['error']   = __( 'Connect a PayPal account first; notifications are set up with it.', 'wp-easycart' );
				$status['message'] = '';
				return $status;
			}
			self::set_state( $mode, array( 'tried' => time() ) );
			$ok = ( 'connect' === $conn ) ? self::register_connect( $mode, $args ) : self::register_own( $mode, $args );

			$status = self::status( $mode );
			if ( ! $ok ) {
				$status['message'] = '';
			} elseif ( 'own' === $status['state'] ) {
				$status['message'] = __( 'Notifications are set up. PayPal signs each one and your store checks it.', 'wp-easycart' );
			} elseif ( $status['secured'] && ! empty( $status['unconfirmed'] ) ) {
				/* 6.0.2: Connect could not confirm the new key and kept the one it had ( the stored key, still signed ). */
				$status['message'] = __( 'WP EasyCart Connect could not reach your store to confirm the new key, so it kept the one it had. Notifications stay signed with it.', 'wp-easycart' );
			} elseif ( $status['secured'] ) {
				$status['message'] = __( 'Notifications are signed now. PayPal payments keep working as before.', 'wp-easycart' );
			} elseif ( ! empty( $status['unconfirmed'] ) ) {
				$status['message'] = __( 'Notifications still arrive, but WP EasyCart Connect could not reach your store to confirm its key, so they are not signed yet.', 'wp-easycart' );
			} else {
				$status['message'] = __( 'Notifications still arrive, but WP EasyCart Connect can’t sign them for PayPal yet.', 'wp-easycart' );
			}
			return $status;
		}

		/**
		 * WP EasyCart Connect: action register ( /paypal-v3/, through wp_easycart_paypal_connect ) with this store's key
		 * ( notificationKey ). The key is kept only when Connect answers key_saved; a key Connect never stored would turn
		 * every relayed event away. key_error ( check_failed: Connect could not get this store to confirm the key ) leaves
		 * Connect's key as it was, so the stored key and the pending one both stay; only an answer with neither key_saved
		 * nor key_error ( a relay without keys ) clears the key.
		 *
		 * @param string $mode sandbox | production.
		 * @param array  $args register() arguments.
		 * @return bool Registered.
		 */
		private static function register_connect( $mode, $args ) {
			$merchant_id = (string) get_option( 'ec_option_paypal_' . $mode . '_merchant_id' );
			$stored      = self::key( $mode );
			if ( ! empty( $args['rotate'] ) ) {
				$key = wp_generate_password( 40, false, false );
			} elseif ( '' !== $stored ) {
				$key = $stored;
			} else {
				/* The first key comes from the site's salts, so two registrations at the same moment send the same one. */
				$key = substr( hash_hmac( 'sha256', 'wp-easycart-paypal-webhook|' . $mode . '|' . $merchant_id, wp_salt( 'auth' ) ), 0, 40 );
			}
			/* Pending before the call: WP EasyCart Connect asks this store to confirm the key ( key_check() ) before it answers. */
			if ( $key !== $stored ) {
				self::set_state( $mode, array( 'pending' => $key ) );
			}

			$response = wp_easycart_paypal_connect::request(
				'register',
				array(
					'merchantID'      => $merchant_id,
					'webhookURL'      => self::webhook_url(),
					'notificationKey' => $key,
				),
				null,
				array(
					'method'  => 'GET',
					'timeout' => max( 2, (int) $args['timeout'] ),
					'mode'    => $mode,
				)
			);
			$db = class_exists( 'ec_db' ) ? new ec_db() : null;

			if ( is_wp_error( $response ) ) {
				if ( $db ) {
					$db->insert_response( 0, 1, 'PayPal Webhook Register CURL ERROR', $response->get_error_message() );
				}
				// Connect may have stored the key before the answer was lost: the pending key stays accepted.
				/* translators: %s: the connection error, e.g. "cURL error 28: Operation timed out". */
				return self::failed( sprintf( __( 'Could not reach WP EasyCart Connect ( %s ). Try again in a moment.', 'wp-easycart' ), $response->get_error_message() ) );
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			$body = (string) wp_remote_retrieve_body( $response );
			if ( $db ) {
				$db->insert_response( 0, 0, 'PayPal Webhook Register Response ( ' . $args['context'] . ' )', $code . ' ' . substr( $body, 0, 2000 ) );
			}
			$data = json_decode( $body );

			if ( ! is_object( $data ) ) {
				/* translators: %d: HTTP status code. */
				return self::failed( sprintf( __( 'WP EasyCart Connect did not answer as expected ( HTTP %d ). Try again in a moment.', 'wp-easycart' ), $code ) );
			}

			if ( empty( $data->webhook_id ) || ( isset( $data->success ) && ! $data->success ) ) {
				/* Connect answered, so it did not take the key sent with this call. */
				self::set_state( $mode, array( 'pending' => '' ) );
				$error = '';
				if ( isset( $data->error ) && is_scalar( $data->error ) ) {
					$error = (string) $data->error;
				} elseif ( isset( $data->name ) && is_scalar( $data->name ) ) {
					$error = (string) $data->name;
				}
				return self::failed( self::connect_error_text( $error, $mode ) );
			}

			update_option( 'ec_option_paypal_wpeasycart_' . $mode . '_webhook_id', sanitize_text_field( (string) $data->webhook_id ) );
			if ( ! empty( $data->key_saved ) ) {
				update_option( 'ec_option_paypal_' . $mode . '_webhook_key', $key, false );
				self::set_state(
					$mode,
					array(
						'pending'     => '',
						'unsigned'    => 0,
						'unconfirmed' => 0,
					)
				);
			} elseif ( isset( $data->key_error ) && is_scalar( $data->key_error ) && '' !== (string) $data->key_error ) {
				// 6.0.2 ( relay v3 ): Connect did not take the key and left its own as it was. key_error check_failed: Connect
				// could not get this store to confirm the key ( key_check() ), e.g. a firewall or maintenance mode. The stored key
				// still matches Connect's ( its events and this store's signed calls keep matching ) and the pending one stays
				// accepted, so neither is dropped.
				self::set_state(
					$mode,
					array(
						'unsigned'    => time(),
						'unconfirmed' => ( 'check_failed' === (string) $data->key_error ) ? time() : 0,
					)
				);
			} else {
				// Registered, but Connect keeps no key for this store ( a relay without keys ): its events come unsigned, so
				// none is expected.
				update_option( 'ec_option_paypal_' . $mode . '_webhook_key', '', false );
				self::set_state(
					$mode,
					array(
						'pending'     => '',
						'unsigned'    => time(),
						'unconfirmed' => 0,
					)
				);
			}
			update_option( 'ec_option_paypal_webhook_error', '', false );
			return true;
		}

		/**
		 * WP EasyCart Connect's answer codes, in words for the merchant.
		 *
		 * @param string $code Connect's error ( or PayPal's error name, when Connect passes it on ).
		 * @param string $mode sandbox | production.
		 * @return string
		 */
		public static function connect_error_text( $code, $mode ) {
			$code  = strtolower( trim( (string) $code ) );
			$known = array(
				'duplicate'                  => __( 'This store is already registered for PayPal notifications from before they were signed, so they keep arriving unsigned. Contact WP EasyCart support so the registration can be refreshed with a key.', 'wp-easycart' ),
				'webhook_url_already_exists' => __( 'This store is already registered for PayPal notifications from before they were signed, so they keep arriving unsigned. Contact WP EasyCart support so the registration can be refreshed with a key.', 'wp-easycart' ),
				'invalid notification key'   => __( 'Your store could not agree a key with WP EasyCart Connect. Try again, and contact WP EasyCart support if it keeps happening.', 'wp-easycart' ),
				'invalid merchant'           => ( 'sandbox' === $mode ) ? __( 'WP EasyCart Connect does not know this PayPal sandbox account. Switch accounts to connect it again.', 'wp-easycart' ) : __( 'WP EasyCart Connect does not know this PayPal account. Switch accounts to connect it again.', 'wp-easycart' ),
				'invalid webhook url'        => __( 'PayPal will only send notifications to a public https address. Check the WordPress Address and Site Address under Settings › General.', 'wp-easycart' ),
				'paypal error'               => __( 'PayPal did not accept the registration. Try again in a moment.', 'wp-easycart' ),
			);
			if ( isset( $known[ $code ] ) ) {
				return $known[ $code ];
			}
			if ( '' !== $code ) {
				/* translators: %s: the code WP EasyCart Connect returned. */
				return sprintf( __( 'WP EasyCart Connect refused the registration ( %s ).', 'wp-easycart' ), sanitize_text_field( $code ) );
			}
			return __( 'WP EasyCart Connect did not confirm the registration.', 'wp-easycart' );
		}

		/**
		 * The store's own PayPal app: create its webhook with PayPal ( PayPal signs each event for it ). A URL PayPal
		 * already has for the app is adopted rather than refused.
		 *
		 * @param string $mode sandbox | production.
		 * @param array  $args register() arguments.
		 * @return bool Registered.
		 */
		private static function register_own( $mode, $args ) {
			$token = self::own_token( $mode, (int) $args['timeout'] );
			if ( '' === $token ) {
				return self::failed( __( 'PayPal did not accept your app ID and secret, so notifications could not be set up. Check them in your PayPal developer dashboard.', 'wp-easycart' ) );
			}
			$events = array();
			foreach ( explode( ',', self::OWN_EVENTS ) as $name ) {
				$events[] = array( 'name' => $name );
			}
			$headers  = array(
				'Content-Type'                  => 'application/json',
				'Authorization'                 => 'Bearer ' . $token,
				'PayPal-Partner-Attribution-Id' => 'LevelFourDevelopment_SP_PPM',
			);
			$response = wp_remote_post(
				self::api( $mode ) . '/v1/notifications/webhooks',
				array(
					'headers' => $headers,
					'body'    => wp_json_encode(
						array(
							'url'         => self::webhook_url(),
							'event_types' => $events,
						)
					),
					'timeout' => max( 2, (int) $args['timeout'] ),
				)
			);
			$db = class_exists( 'ec_db' ) ? new ec_db() : null;
			if ( is_wp_error( $response ) ) {
				if ( $db ) {
					$db->insert_response( 0, 1, 'PayPal API Webhook CURL ERROR', $response->get_error_message() );
				}
				/* translators: %s: the connection error. */
				return self::failed( sprintf( __( 'Could not reach PayPal ( %s ). Try again in a moment.', 'wp-easycart' ), $response->get_error_message() ) );
			}
			$body = (string) wp_remote_retrieve_body( $response );
			if ( $db ) {
				$db->insert_response( 0, 0, 'PayPal API Webhook Response ( ' . $args['context'] . ' )', (int) wp_remote_retrieve_response_code( $response ) . ' ' . substr( $body, 0, 2000 ) );
			}
			$data = json_decode( $body );
			$id   = ( is_object( $data ) && ! empty( $data->id ) && is_scalar( $data->id ) ) ? (string) $data->id : '';
			$name = ( is_object( $data ) && isset( $data->name ) && is_scalar( $data->name ) ) ? (string) $data->name : '';

			if ( '' === $id && 'WEBHOOK_URL_ALREADY_EXISTS' === $name ) {
				$id = self::own_find( $mode, $token, (int) $args['timeout'] );
			}
			if ( '' === $id ) {
				$known = array(
					'WEBHOOK_URL_ALREADY_EXISTS'    => __( 'Your PayPal app already has a webhook for this store, but PayPal did not say which. Delete it in your PayPal developer dashboard, then try again.', 'wp-easycart' ),
					'WEBHOOK_NUMBER_LIMIT_EXCEEDED' => __( 'Your PayPal app already has as many webhooks as PayPal allows. Delete one you no longer use in your PayPal developer dashboard, then try again.', 'wp-easycart' ),
					'AUTHENTICATION_FAILURE'        => __( 'PayPal did not accept your app ID and secret, so notifications could not be set up. Check them in your PayPal developer dashboard.', 'wp-easycart' ),
					'INVALID_REQUEST'               => __( 'PayPal refused the webhook address. PayPal only sends notifications to a public https address.', 'wp-easycart' ),
				);
				if ( isset( $known[ $name ] ) ) {
					return self::failed( $known[ $name ] );
				}
				/* translators: %s: PayPal's error name. */
				return self::failed( '' !== $name ? sprintf( __( 'PayPal refused the webhook ( %s ).', 'wp-easycart' ), sanitize_text_field( $name ) ) : __( 'PayPal did not confirm the webhook.', 'wp-easycart' ) );
			}
			update_option( 'ec_option_paypal_' . $mode . '_webhook_id', sanitize_text_field( $id ) );
			update_option( 'ec_option_paypal_webhook_error', '', false );
			return true;
		}

		/**
		 * The own app's webhook for this store's address, when PayPal already has one.
		 *
		 * @param string $mode    sandbox | production.
		 * @param string $token   Access token.
		 * @param int    $timeout Seconds.
		 * @return string Webhook id, or ''.
		 */
		private static function own_find( $mode, $token, $timeout ) {
			$response = wp_remote_get(
				self::api( $mode ) . '/v1/notifications/webhooks',
				array(
					'headers' => array(
						'Content-Type'  => 'application/json',
						'Authorization' => 'Bearer ' . $token,
					),
					'timeout' => max( 2, $timeout ),
				)
			);
			if ( is_wp_error( $response ) ) {
				return '';
			}
			$data = json_decode( (string) wp_remote_retrieve_body( $response ) );
			if ( ! is_object( $data ) || empty( $data->webhooks ) || ! is_array( $data->webhooks ) ) {
				return '';
			}
			$want = untrailingslashit( self::webhook_url() );
			foreach ( $data->webhooks as $hook ) {
				if ( is_object( $hook ) && isset( $hook->url, $hook->id ) && is_scalar( $hook->url ) && untrailingslashit( (string) $hook->url ) === $want ) {
					return (string) $hook->id;
				}
			}
			return '';
		}

		/**
		 * An access token for the store's own PayPal app ( the one checkout keeps, fetched when it has run out ).
		 *
		 * @param string $mode    sandbox | production.
		 * @param int    $timeout Seconds.
		 * @return string '' when PayPal gave none.
		 */
		private static function own_token( $mode, $timeout ) {
			$token   = (string) get_option( 'ec_option_paypal_' . $mode . '_access_token' );
			$expires = (int) get_option( 'ec_option_paypal_' . $mode . '_access_token_expires' );
			if ( '' !== $token && $expires > time() ) {
				return $token;
			}
			$credentials = get_option( 'ec_option_paypal_' . $mode . '_app_id' ) . ':' . get_option( 'ec_option_paypal_' . $mode . '_secret' );
			$response    = wp_remote_post(
				self::api( $mode ) . '/v1/oauth2/token',
				array(
					'headers' => array(
						'Accept'        => 'application/json',
						'Authorization' => 'Basic ' . base64_encode( $credentials ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP Basic authentication.
					),
					'body'    => array( 'grant_type' => 'client_credentials' ),
					'timeout' => max( 2, $timeout ),
				)
			);
			if ( is_wp_error( $response ) ) {
				return '';
			}
			$data = json_decode( (string) wp_remote_retrieve_body( $response ) );
			if ( ! is_object( $data ) || empty( $data->access_token ) || ! is_scalar( $data->access_token ) ) {
				return '';
			}
			update_option( 'ec_option_paypal_' . $mode . '_access_token', (string) $data->access_token );
			update_option( 'ec_option_paypal_' . $mode . '_access_token_expires', time() + ( isset( $data->expires_in ) ? (int) $data->expires_in : 3600 ) - 300 );
			return (string) $data->access_token;
		}

		/**
		 * A registration did not take: say why on the drawer. What was registered before stays as it was.
		 *
		 * @param string $message For the merchant.
		 * @return bool false
		 */
		private static function failed( $message ) {
			update_option( 'ec_option_paypal_webhook_error', sanitize_text_field( $message ), false );
			return false;
		}

		/**
		 * Forget a mode's registration ( Disconnect ).
		 *
		 * @param string $mode sandbox | production.
		 * @return void
		 */
		public static function forget( $mode ) {
			$mode = self::pick( $mode );
			update_option( 'ec_option_paypal_wpeasycart_' . $mode . '_webhook_id', '' );
			update_option( 'ec_option_paypal_' . $mode . '_webhook_key', '' );
			$all = get_option( 'ec_option_paypal_webhook_state' );
			if ( is_array( $all ) && isset( $all[ $mode ] ) ) {
				unset( $all[ $mode ] );
				update_option( 'ec_option_paypal_webhook_state', $all, false );
			}
			if ( self::mode() === $mode ) {
				update_option( 'ec_option_paypal_webhook_error', '', false );
			}
		}

		/**
		 * PayPal's REST API for a mode.
		 *
		 * @param string $mode sandbox | production.
		 * @return string
		 */
		private static function api( $mode ) {
			return ( 'sandbox' === $mode ) ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
		}

		/**
		 * The address PayPal's events are sent to ( the receiver in wpeasycart.php ).
		 *
		 * @return string
		 */
		public static function webhook_url() {
			return wp_easycart_hook_url( 'paypal-webhook' );
		}

		/* ------------------------------------------------------------------ */
		/* Status                                                              */
		/* ------------------------------------------------------------------ */

		/**
		 * What notifications are doing in a mode.
		 *
		 * @param string $mode sandbox | production | ''.
		 * @return array {
		 *     @type string $state      off | unregistered | signed | unsigned | own | error.
		 *     @type bool   $secured    A forged notification is turned away ( signed or own ).
		 *     @type bool   $registered Something is registered for the mode.
		 *     @type string $connection connect | own | ''.
		 *     @type string $mode       sandbox | production.
		 *     @type string $error      The last registration problem ( '' = none ).
		 *     @type bool   $refused    Connect answered the last registration without keeping a key.
		 *     @type int    $last       When a notification last arrived ( 0 = never ).
		 *     @type int    $count      Notifications in the log.
		 *     @type int    $rejected   Requests turned away in the last 7 days.
		 *     @type string $label      Short wording for the card.
		 * }
		 */
		public static function status( $mode = '' ) {
			$mode       = self::pick( $mode );
			$connection = self::connection( $mode );
			$registered = self::registered( $mode );
			$error      = ( self::mode() === $mode ) ? trim( (string) get_option( 'ec_option_paypal_webhook_error' ) ) : '';
			$state_row  = self::state( $mode );
			$count      = 0;
			$rejected   = 0;
			foreach ( ( function_exists( 'wp_easycart_gateway_webhook_entries' ) ? wp_easycart_gateway_webhook_entries( 'paypal' ) : array() ) as $entry ) {
				if ( ! is_array( $entry ) ) {
					continue;
				}
				if ( isset( $entry['type'] ) && 'rejected' === $entry['type'] ) {
					if ( isset( $entry['time'] ) && (int) $entry['time'] > time() - 7 * DAY_IN_SECONDS ) {
						++$rejected;
					}
				} else {
					++$count;
				}
			}
			$out = array(
				'state'       => 'off',
				'secured'     => false,
				'registered'  => $registered,
				'connection'  => $connection,
				'mode'        => $mode,
				'error'       => $error,
				'refused'     => $state_row['unsigned'] > 0,
				'unconfirmed' => $state_row['unconfirmed'] > 0,
				'last'        => (int) get_option( 'ec_option_paypal_webhook_last' ),
				'count'       => $count,
				'rejected'    => $rejected,
				'label'       => __( 'Off: connect PayPal first', 'wp-easycart' ),
			);
			if ( '' === $connection ) {
				return $out;
			}
			if ( '' !== $error ) {
				$out['state']   = 'error';
				$out['secured'] = $registered && ( 'own' === $connection || '' !== self::key( $mode ) );
				$out['label']   = __( 'Needs attention', 'wp-easycart' );
			} elseif ( ! $registered ) {
				$out['state'] = 'unregistered';
				$out['label'] = __( 'Not set up yet', 'wp-easycart' );
			} elseif ( 'own' === $connection ) {
				$out['state']   = 'own';
				$out['secured'] = true;
				$out['label']   = __( 'On, signed by PayPal', 'wp-easycart' );
			} elseif ( '' !== self::key( $mode ) ) {
				$out['state']   = 'signed';
				$out['secured'] = true;
				$out['label']   = __( 'On and signed', 'wp-easycart' );
			} else {
				$out['state'] = 'unsigned';
				$out['label'] = __( 'On, not signed', 'wp-easycart' );
			}
			return $out;
		}

		/**
		 * One line for the PayPal card on Settings › Payments.
		 *
		 * @return string
		 */
		public static function card_text() {
			$status = self::status();
			if ( 'error' === $status['state'] ) {
				return $status['error'];
			}
			$text = $status['label'];
			if ( $status['last'] && in_array( $status['state'], array( 'signed', 'unsigned', 'own' ), true ) ) {
				/* translators: 1: status, e.g. "On and signed"; 2: how long ago, e.g. "5 mins". */
				$text = sprintf( __( '%1$s, last received %2$s ago', 'wp-easycart' ), $text, human_time_diff( min( $status['last'], time() ), time() ) );
			}
			return $text;
		}

		/**
		 * The Payments drawer's Notifications group ( FREE and WP EasyCart PRO's PayPal panels ): what notifications are
		 * doing, and one button when something can be done ( Set up / Secure / Register again / Try again ). The button
		 * runs ec_admin_paypal_webhooks_secure() ( admin/js/payment.js ), which puts the answer's copy of this group in
		 * place.
		 *
		 * @return void
		 */
		public static function print_group() {
			$status = self::status();
			$mode   = ( 'sandbox' === $status['mode'] ) ? __( 'sandbox', 'wp-easycart' ) : __( 'live', 'wp-easycart' );
			$title  = '';
			$text   = '';
			$button = '';
			$warn   = true;
			switch ( $status['state'] ) {
				case 'off':
					$warn = false;
					/* translators: %s: sandbox or live. */
					$text = sprintf( __( 'Connect a PayPal %s account above; notifications are set up with it.', 'wp-easycart' ), $mode );
					break;
				case 'signed':
					/* 6.0.2: a new key Connect could not confirm; it kept the one this store holds, so they stay signed. */
					if ( ! empty( $status['unconfirmed'] ) ) {
						$title  = __( 'The new key is not confirmed yet', 'wp-easycart' );
						$text   = __( 'Notifications stay signed with the key WP EasyCart Connect already had, because it could not reach your store to confirm the new one. Check that nothing stops connect.wpeasycart.com from reaching your site ( a firewall, a security plugin, maintenance mode or a site password ), then register again.', 'wp-easycart' );
						$button = __( 'Register again', 'wp-easycart' );
					}
					break;
				case 'unregistered':
					$title  = __( 'Notifications are not set up', 'wp-easycart' );
					$text   = __( 'Without them your store does not hear about refunds, reversals or payments that finish later in PayPal. Setting them up takes one click.', 'wp-easycart' );
					$button = __( 'Set up notifications', 'wp-easycart' );
					break;
				case 'unsigned':
					if ( ! empty( $status['unconfirmed'] ) ) {
						$title  = __( 'Notifications arrive, but are not signed yet', 'wp-easycart' );
						$text   = __( 'WP EasyCart Connect could not reach your store to confirm its key, so notifications keep arriving unsigned. Check that nothing stops connect.wpeasycart.com from reaching your site ( a firewall, a security plugin, maintenance mode or a site password ), then register again.', 'wp-easycart' );
						$button = __( 'Register again', 'wp-easycart' );
					} elseif ( $status['refused'] ) {
						$title  = __( 'Notifications arrive, but are not signed yet', 'wp-easycart' );
						$text   = __( 'They keep arriving and being applied as before, but WP EasyCart Connect can’t sign them for PayPal yet, so your store can’t tell a real one from a forged one. Nothing to do on your side; you can try again later.', 'wp-easycart' );
						$button = __( 'Register again', 'wp-easycart' );
					} else {
						$title  = __( 'Notifications are not signed yet', 'wp-easycart' );
						$text   = __( 'This store registered for PayPal notifications before they were signed, so it cannot tell a real one from a forged one. Securing them registers the store again with its own key; payments keep working.', 'wp-easycart' );
						$button = __( 'Secure notifications', 'wp-easycart' );
					}
					break;
				case 'error':
					$title  = __( 'Notifications need attention', 'wp-easycart' );
					$text   = $status['error'];
					$button = __( 'Try again', 'wp-easycart' );
					break;
			}
			$busy   = in_array( $status['state'], array( 'unsigned', 'signed' ), true ) ? __( 'Securing…', 'wp-easycart' ) : __( 'Setting up…', 'wp-easycart' );
			$failed = ( 'own' === $status['connection'] ) ? __( 'Could not reach PayPal. Try again in a moment.', 'wp-easycart' ) : __( 'Could not reach WP EasyCart Connect. Try again in a moment.', 'wp-easycart' );
			/* The status line: an error on a registration that still works keeps saying what does work. */
			$line = $status['label'];
			if ( 'error' === $status['state'] ) {
				if ( 'own' === $status['connection'] ) {
					$line = __( 'On, signed by PayPal', 'wp-easycart' );
				} elseif ( $status['secured'] ) {
					$line = __( 'On and signed', 'wp-easycart' );
				} else {
					$line = __( 'On, not signed', 'wp-easycart' );
				}
			}
			?>
			<div class="ecsq-group ecpp-webhooks" id="ec_paypal_webhooks" data-state="<?php echo esc_attr( $status['state'] ); ?>">
				<div class="ecsq-group-t"><?php esc_html_e( 'Notifications', 'wp-easycart' ); ?></div>
				<?php if ( in_array( $status['state'], array( 'signed', 'own', 'unsigned' ), true ) || ( 'error' === $status['state'] && $status['registered'] ) ) { ?>
					<div class="ec_admin_toggle_note ecsq-status">
						<span class="ecsq-dot<?php echo ( $status['registered'] ) ? ' is-on' : ''; ?>" aria-hidden="true"></span>
						<span>
							<b><?php echo esc_html( $line ); ?>.</b>
							<?php
							if ( 'signed' === $status['state'] ) {
								esc_html_e( 'PayPal tells your store about refunds, reversals and payments that finish later. Each notification carries your store’s key, so a forged one is turned away.', 'wp-easycart' );
							} elseif ( 'own' === $status['state'] ) {
								esc_html_e( 'PayPal sends notifications from your own PayPal app and signs each one; your store checks the signature and turns away anything else.', 'wp-easycart' );
							}
							echo ' ';
							if ( $status['last'] ) {
								/* translators: %s: how long ago the last notification arrived, e.g. "5 mins". */
								echo esc_html( sprintf( __( 'Last notification received %s ago.', 'wp-easycart' ), human_time_diff( min( $status['last'], time() ), time() ) ) );
							} else {
								esc_html_e( 'No notification has arrived yet. PayPal sends one when a payment is refunded, reversed or changes status.', 'wp-easycart' );
							}
							if ( $status['count'] ) {
								echo ' ';
								/* translators: %d: number of recent notifications recorded. */
								echo esc_html( sprintf( _n( '%d recent event recorded.', '%d recent events recorded.', $status['count'], 'wp-easycart' ), $status['count'] ) );
							}
							if ( $status['rejected'] ) {
								echo ' ';
								/* translators: %d: requests turned away in the last week. */
								echo esc_html( sprintf( _n( '%d request turned away in the last week.', '%d requests turned away in the last week.', $status['rejected'], 'wp-easycart' ), $status['rejected'] ) );
							}
							?>
						</span>
					</div>
				<?php } ?>
				<?php if ( '' !== $text ) { ?>
					<div class="ec_admin_toggle_note<?php echo $warn ? ' ec_admin_toggle_note_warn' : ''; ?> ecsq-secure" id="ec_paypal_webhook_secure">
						<span class="ecsq-secure-text">
							<?php if ( '' !== $title ) { ?>
								<b><?php echo esc_html( $title ); ?></b>
							<?php } ?>
							<?php echo esc_html( $text ); ?>
						</span>
						<?php if ( '' !== $button ) { ?>
							<button type="button" class="ecsq-secure-btn" onclick="return ec_admin_paypal_webhooks_secure( this );" data-busy="<?php echo esc_attr( $busy ); ?>" data-failed="<?php echo esc_attr( $failed ); ?>"><?php echo esc_html( $button ); ?></button>
						<?php } ?>
					</div>
				<?php } ?>
				<div class="ec_admin_toggle_note ec_admin_toggle_note_warn" id="ec_paypal_webhook_note" style="display:none;"></div>
			</div>
			<?php
		}
	}

	wp_easycart_paypal_webhooks::init();

endif;
