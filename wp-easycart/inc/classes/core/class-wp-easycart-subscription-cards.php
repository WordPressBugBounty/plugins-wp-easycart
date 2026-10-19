<?php
/**
 * WP EasyCart — the card each Stripe subscription bills ( 6.0.5, Matt's subscription tests ).
 *
 * My Account showed one card on every subscription page: the account's last card ( ec_user.default_card_type / last4 ). A
 * subscription bills its own payment method in Stripe, so the page was wrong whenever the two differed: a subscription bought
 * with a new card while another card was on file and a free trial ( its first invoice is $0, so checkout never wrote the account
 * card ) showed the old card, and a card changed on one subscription showed on every other one, which read as if all of them
 * had moved. Each subscription now keeps its own card: ec_subscription.credit_card_type ( the brand key, as Stripe names it:
 * visa, mastercard, amex …, or a payment method type such as sepa_debit ), credit_card_last4, credit_card_exp_month,
 * credit_card_exp_year and stripe_payment_method_id ( "customer:" + the id when the subscription has no payment method of its
 * own and bills the Stripe customer's default ). EC_UPGRADE_DB 131.
 *
 * Written at checkout ( ec_cartpage::submit_stripe_quick_subscription() ), when the customer changes a card ( apply() ), from
 * Stripe's customer.subscription.updated and payment_method.updated / payment_method.automatically_updated events, and, for a
 * subscription made before 6.0.5 or one checkout could not describe, by asking Stripe once when its page is shown ( lookup(),
 * at most once a day while Stripe has no card for it ). Renewal orders record it ( order_card() ).
 *
 * Changing a card ( ec_ajax_get_stripe_update_customer_card ) moves the subscription whose page it is, and the customer's other
 * subscriptions only when they choose them: the payment panel asks "This subscription only", "This subscription and others I
 * choose" or "All my subscriptions" ( others() lists what they can choose ).
 *
 * @package WP_EasyCart
 * @since   6.0.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_subscription_cards' ) ) :

	/**
	 * A subscription's card.
	 */
	class wp_easycart_subscription_cards {

		/** The database update that adds the ec_subscription card columns. */
		const DB = 131;

		/** Transient prefix ( + subscription id ): Stripe was asked and had no card, or did not answer; ask again later. */
		const LOOKUP = 'wpec_sub_card_';

		/** Prefix of stripe_payment_method_id: the card is the Stripe customer's default ( the subscription has none of its own ). */
		const CUSTOMER = 'customer:';

		/**
		 * Cards read this request, by subscription id ( false = none recorded ).
		 *
		 * @var array
		 */
		private static $cache = array();

		/**
		 * Hooks.
		 */
		public static function init() {
			add_action( 'wpeasycart_account_subscription_details_before', array( __CLASS__, 'print_result' ), 5, 1 );
		}

		/**
		 * The card columns exist ( WP EasyCart 6.0.5's database update ran ).
		 *
		 * @return bool
		 */
		public static function ready() {
			return (int) get_option( 'ec_option_db_new_version' ) >= self::DB;
		}

		/* ------------------------------------------------------------------ reading */

		/**
		 * A Stripe payment method, card source or source as a card.
		 *
		 * @param object $stripe_object Stripe payment_method ( pm_ ), card ( card_ ) or source ( src_ ).
		 * @return array|null id, brand ( lower case key ), last4, exp_month, exp_year; null when it is not one.
		 */
		public static function normalize( $stripe_object ) {
			if ( ! is_object( $stripe_object ) || empty( $stripe_object->id ) ) {
				return null;
			}
			$type   = isset( $stripe_object->object ) ? (string) $stripe_object->object : '';
			$detail = null;
			$brand  = '';
			if ( 'payment_method' === $type ) {
				$kind = isset( $stripe_object->type ) ? (string) $stripe_object->type : '';
				if ( 'card' === $kind && isset( $stripe_object->card ) && is_object( $stripe_object->card ) ) {
					$detail = $stripe_object->card;
					$brand  = isset( $detail->brand ) ? (string) $detail->brand : 'card';
				} elseif ( '' !== $kind && isset( $stripe_object->{$kind} ) && is_object( $stripe_object->{$kind} ) ) {
					$detail = $stripe_object->{$kind}; /* sepa_debit, us_bank_account, bacs_debit, link …: the type is the "brand" */
					$brand  = $kind;
				} else {
					$brand = ( '' !== $kind ) ? $kind : 'card';
				}
			} elseif ( 'card' === $type ) {
				$detail = $stripe_object; /* a card saved as a source ( card_ ): brand "Visa", "American Express" … */
				$brand  = isset( $stripe_object->brand ) ? (string) $stripe_object->brand : 'card';
			} elseif ( 'source' === $type && isset( $stripe_object->card ) && is_object( $stripe_object->card ) ) {
				$detail = $stripe_object->card;
				$brand  = isset( $detail->brand ) ? (string) $detail->brand : 'card';
			} else {
				return null;
			}
			return array(
				'id'        => (string) $stripe_object->id,
				'brand'     => self::brand_key( $brand ),
				'last4'     => ( $detail && isset( $detail->last4 ) ) ? substr( preg_replace( '/[^0-9A-Za-z]/', '', (string) $detail->last4 ), 0, 20 ) : '',
				'exp_month' => ( $detail && isset( $detail->exp_month ) ) ? (int) $detail->exp_month : 0,
				'exp_year'  => ( $detail && isset( $detail->exp_year ) ) ? (int) $detail->exp_year : 0,
			);
		}

		/**
		 * The card Stripe bills a subscription: its own payment method or source, else the customer's default.
		 *
		 * @param object $subscription Stripe subscription, ideally with default_payment_method, default_source,
		 *                             customer.invoice_settings.default_payment_method and customer.default_source expanded.
		 * @return array|null normalize()'s card; its id carries CUSTOMER when it is the customer's default.
		 */
		public static function from_subscription( $subscription ) {
			if ( ! is_object( $subscription ) ) {
				return null;
			}
			foreach ( array( 'default_payment_method', 'default_source' ) as $field ) {
				if ( isset( $subscription->{$field} ) && is_object( $subscription->{$field} ) ) {
					$card = self::normalize( $subscription->{$field} );
					if ( $card ) {
						return $card;
					}
				}
			}
			$customer = ( isset( $subscription->customer ) && is_object( $subscription->customer ) ) ? $subscription->customer : null;
			if ( ! $customer || ! empty( $customer->deleted ) ) {
				return null;
			}
			$defaults = array();
			if ( isset( $customer->invoice_settings ) && is_object( $customer->invoice_settings ) && isset( $customer->invoice_settings->default_payment_method ) ) {
				$defaults[] = $customer->invoice_settings->default_payment_method;
			}
			if ( isset( $customer->default_source ) ) {
				$defaults[] = $customer->default_source;
			}
			foreach ( $defaults as $default ) {
				$card = is_object( $default ) ? self::normalize( $default ) : null;
				if ( $card ) {
					$card['id'] = self::CUSTOMER . $card['id'];
					return $card;
				}
			}
			return null;
		}

		/**
		 * The cards recorded for some subscriptions.
		 *
		 * @param int[] $subscription_ids Subscriptions.
		 * @return array subscription id => card ( normalize()'s keys ); subscriptions without one are left out.
		 */
		public static function many( $subscription_ids ) {
			$ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $subscription_ids ) ) ) );
			$out = array();
			if ( empty( $ids ) || ! self::ready() ) {
				return $out;
			}
			$ask = array();
			foreach ( $ids as $id ) {
				if ( array_key_exists( $id, self::$cache ) ) {
					if ( self::$cache[ $id ] ) {
						$out[ $id ] = self::$cache[ $id ];
					}
				} else {
					$ask[] = $id;
				}
			}
			if ( ! empty( $ask ) ) {
				global $wpdb;
				$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT subscription_id, credit_card_type, credit_card_last4, credit_card_exp_month, credit_card_exp_year, stripe_payment_method_id FROM ec_subscription WHERE subscription_id IN ( ' . implode( ', ', array_fill( 0, count( $ask ), '%d' ) ) . ' )', $ask ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- one %d placeholder per id, bound by prepare().
				foreach ( $ask as $id ) {
					self::$cache[ $id ] = false;
				}
				foreach ( (array) $rows as $row ) {
					$id = (int) $row->subscription_id;
					if ( '' === (string) $row->credit_card_last4 && '' === (string) $row->credit_card_type ) {
						continue;
					}
					self::$cache[ $id ] = array(
						'id'        => (string) $row->stripe_payment_method_id,
						'brand'     => (string) $row->credit_card_type,
						'last4'     => (string) $row->credit_card_last4,
						'exp_month' => (int) $row->credit_card_exp_month,
						'exp_year'  => (int) $row->credit_card_exp_year,
					);
					$out[ $id ]         = self::$cache[ $id ];
				}
			}
			return $out;
		}

		/**
		 * The card recorded for a subscription.
		 *
		 * @param int $subscription_id Subscription.
		 * @return array|null
		 */
		public static function get( $subscription_id ) {
			$cards = self::many( array( (int) $subscription_id ) );
			return isset( $cards[ (int) $subscription_id ] ) ? $cards[ (int) $subscription_id ] : null;
		}

		/**
		 * The card a subscription bills, for its page: the one recorded, else Stripe's answer ( lookup() ) when $ask.
		 *
		 * @param ec_subscription|object $subscription ec_subscription or an ec_subscription row.
		 * @param bool                   $ask          Ask Stripe when nothing is recorded.
		 * @return array|null
		 */
		public static function card( $subscription, $ask = true ) {
			if ( ! is_object( $subscription ) || empty( $subscription->subscription_id ) || ! self::ready() ) {
				return null;
			}
			$card = self::get( (int) $subscription->subscription_id );
			if ( $card || ! $ask ) {
				return $card;
			}
			$type = isset( $subscription->subscription_type ) ? (string) $subscription->subscription_type : 'stripe';
			$sub  = isset( $subscription->stripe_subscription_id ) ? (string) $subscription->stripe_subscription_id : '';
			if ( 'paypal' === $type || '' === $sub ) {
				return null;
			}
			return self::lookup( (int) $subscription->subscription_id, $sub );
		}

		/**
		 * Ask Stripe which card a subscription bills and record it. Once a day at most while Stripe has none ( 10 minutes after
		 * Stripe did not answer ).
		 *
		 * @param int         $subscription_id        Subscription.
		 * @param string      $stripe_subscription_id Its Stripe id.
		 * @param object|null $gateway                ec_stripe / ec_stripe_connect, else the store's.
		 * @return array|null
		 */
		public static function lookup( $subscription_id, $stripe_subscription_id, $gateway = null ) {
			$subscription_id = (int) $subscription_id;
			if ( $subscription_id <= 0 || '' === (string) $stripe_subscription_id || ! self::ready() || get_transient( self::LOOKUP . $subscription_id ) ) {
				return null;
			}
			$gateway = $gateway ? $gateway : self::gateway();
			if ( ! $gateway || ! method_exists( $gateway, 'api' ) ) {
				return null; /* WP EasyCart PRO before 6.0.3 has no api(): the card is recorded on the next change instead */
			}
			$answer = $gateway->api(
				'GET',
				'subscriptions/' . rawurlencode( (string) $stripe_subscription_id ),
				array( 'expand' => array( 'default_payment_method', 'default_source', 'customer.invoice_settings.default_payment_method', 'customer.default_source' ) )
			);
			if ( is_wp_error( $answer ) ) {
				set_transient( self::LOOKUP . $subscription_id, 1, ( 'stripe_unreachable' === $answer->get_error_code() ) ? 10 * MINUTE_IN_SECONDS : DAY_IN_SECONDS );
				return null;
			}
			$card = self::from_subscription( $answer );
			if ( ! $card ) {
				set_transient( self::LOOKUP . $subscription_id, 1, DAY_IN_SECONDS );
				return null;
			}
			self::record( $subscription_id, $card );
			return $card;
		}

		/* ------------------------------------------------------------------ writing */

		/**
		 * Record the card a subscription bills.
		 *
		 * @param int   $subscription_id Subscription.
		 * @param array $card            normalize()'s card.
		 * @return bool
		 */
		public static function record( $subscription_id, $card ) {
			$subscription_id = (int) $subscription_id;
			if ( $subscription_id <= 0 || ! is_array( $card ) || ! self::ready() ) {
				return false;
			}
			global $wpdb;
			$card = wp_parse_args(
				$card,
				array(
					'id'        => '',
					'brand'     => '',
					'last4'     => '',
					'exp_month' => 0,
					'exp_year'  => 0,
				)
			);
			$done = $wpdb->query( $wpdb->prepare( 'UPDATE ec_subscription SET credit_card_type = %s, credit_card_last4 = %s, credit_card_exp_month = %d, credit_card_exp_year = %d, stripe_payment_method_id = %s WHERE subscription_id = %d', substr( (string) $card['brand'], 0, 50 ), substr( (string) $card['last4'], 0, 20 ), (int) $card['exp_month'], (int) $card['exp_year'], substr( (string) $card['id'], 0, 255 ), $subscription_id ) );
			unset( self::$cache[ $subscription_id ] );
			delete_transient( self::LOOKUP . $subscription_id );
			return false !== $done;
		}

		/**
		 * Record a Stripe payment method ( or card ) on a subscription.
		 *
		 * @param int    $subscription_id Subscription.
		 * @param object $payment_method  Stripe object.
		 * @return array|null The card recorded.
		 */
		public static function record_payment_method( $subscription_id, $payment_method ) {
			$card = self::normalize( $payment_method );
			if ( $card && self::record( $subscription_id, $card ) ) {
				return $card;
			}
			return null;
		}

		/**
		 * Forget a subscription's card, so its page asks Stripe again.
		 *
		 * @param int $subscription_id Subscription.
		 */
		public static function forget( $subscription_id ) {
			self::record(
				$subscription_id,
				array(
					'id'        => '',
					'brand'     => '',
					'last4'     => '',
					'exp_month' => 0,
					'exp_year'  => 0,
				)
			);
		}

		/**
		 * Bill a subscription to a payment method in Stripe ( only its default payment method changes ).
		 *
		 * @param object $gateway        ec_stripe / ec_stripe_connect.
		 * @param object $row            ec_subscription row ( stripe_subscription_id, quantity ).
		 * @param string $payment_method Stripe payment method id.
		 * @param object $user           The customer ( stripe_customer_id ), for WP EasyCart PRO before 6.0.3.
		 * @return bool
		 */
		public static function set_on_stripe( $gateway, $row, $payment_method, $user ) {
			if ( ! $gateway || ! is_object( $row ) || '' === (string) $row->stripe_subscription_id || '' === (string) $payment_method ) {
				return false;
			}
			if ( method_exists( $gateway, 'api' ) ) {
				$answer = $gateway->api( 'POST', 'subscriptions/' . rawurlencode( (string) $row->stripe_subscription_id ), array( 'default_payment_method' => (string) $payment_method ) );
				if ( is_wp_error( $answer ) ) {
					new ec_db(); /* sets ec_db's database handle for its static calls */
					ec_db::insert_response( 0, 1, 'Stripe Update Subscription ' . $row->stripe_subscription_id, 'Payment method not changed: ' . $answer->get_error_message() );
					return false;
				}
				return true;
			}
			/* WP EasyCart PRO before 6.0.3: its own call, which also sends the first item's quantity. */
			$info = $gateway->get_subscription( ( is_object( $user ) && isset( $user->stripe_customer_id ) ) ? $user->stripe_customer_id : '', $row->stripe_subscription_id );
			if ( ! $info || ! isset( $info->items->data[0] ) ) {
				return false;
			}
			return (bool) $gateway->set_subscription_payment_method( (string) $payment_method, $info, $row, max( 1, (int) $row->quantity ) );
		}

		/**
		 * Bill some of a customer's subscriptions to a payment method and record it on each one that moved.
		 *
		 * @param object $gateway        ec_stripe / ec_stripe_connect.
		 * @param array  $rows           ec_subscription rows, the customer's own ( the caller checked ).
		 * @param string $payment_method Stripe payment method id.
		 * @param array  $card           normalize()'s card for it ( null: not recorded ).
		 * @param object $user           The customer.
		 * @return array updated => ids, failed => ids.
		 */
		public static function apply( $gateway, $rows, $payment_method, $card, $user ) {
			$result = array(
				'updated' => array(),
				'failed'  => array(),
			);
			foreach ( (array) $rows as $row ) {
				$id = (int) $row->subscription_id;
				if ( ! self::set_on_stripe( $gateway, $row, $payment_method, $user ) ) {
					$result['failed'][] = $id;
					continue;
				}
				$result['updated'][] = $id;
				if ( $card ) {
					self::record( $id, $card );
				} else {
					self::forget( $id );
				}
				if ( class_exists( 'wp_easycart_subscription_changes' ) ) {
					wp_easycart_subscription_changes::payment_method_changed( $id, (string) $payment_method ); /* 6.0.3: a change waiting for renewal bills the new card */
				}
				/**
				 * A customer moved a subscription to another card.
				 *
				 * @since 6.0.5
				 * @param int        $subscription_id Subscription.
				 * @param string     $payment_method  Stripe payment method id.
				 * @param array|null $card            brand, last4, exp_month, exp_year.
				 */
				do_action( 'wp_easycart_subscription_card_changed', $id, (string) $payment_method, $card );
			}
			return $result;
		}

		/* ------------------------------------------------------------------ the customer's other subscriptions */

		/**
		 * The customer's other subscriptions whose card they can change from My Account ( the payment panel's choices ).
		 *
		 * @param int $subscription_id The subscription whose page it is.
		 * @param int $user_id         The customer.
		 * @return array subscription id => array( id, title, card, row ).
		 */
		public static function others( $subscription_id, $user_id ) {
			$user_id = (int) $user_id;
			$out     = array();
			if ( $user_id <= 0 ) {
				return $out;
			}
			$ec_db = new ec_db();
			foreach ( (array) $ec_db->get_subscriptions( $user_id ) as $row ) {
				if ( ! is_object( $row ) || (int) $row->subscription_id === (int) $subscription_id || (int) $row->user_id !== $user_id ) {
					continue;
				}
				$subscription = new ec_subscription( $row );
				if ( ! $subscription->can_update_payment_method() ) {
					continue;
				}
				$out[ (int) $row->subscription_id ] = array(
					'id'    => (int) $row->subscription_id,
					'title' => wp_strip_all_tags( function_exists( 'wp_easycart_language' ) ? wp_easycart_language()->convert_text( (string) $row->title ) : (string) $row->title ),
					'card'  => null,
					'row'   => $row,
				);
			}
			foreach ( self::many( array_keys( $out ) ) as $id => $card ) {
				$out[ $id ]['card'] = $card;
			}
			return $out;
		}

		/* ------------------------------------------------------------------ Stripe's events */

		/**
		 * Stripe sent customer.subscription.updated: the subscription's own payment method changed in Stripe ( the dashboard, another app ).
		 *
		 * @param object $row  ec_subscription row.
		 * @param object $data The Stripe subscription in the event.
		 */
		public static function webhook_subscription( $row, $data ) {
			if ( ! self::ready() || ! is_object( $row ) || empty( $row->subscription_id ) || ! is_object( $data ) ) {
				return;
			}
			$own = '';
			if ( isset( $data->default_payment_method ) && '' !== (string) ( is_object( $data->default_payment_method ) ? $data->default_payment_method->id : $data->default_payment_method ) ) {
				$own = is_object( $data->default_payment_method ) ? (string) $data->default_payment_method->id : (string) $data->default_payment_method;
			} elseif ( isset( $data->default_source ) && '' !== (string) ( is_object( $data->default_source ) ? $data->default_source->id : $data->default_source ) ) {
				$own = is_object( $data->default_source ) ? (string) $data->default_source->id : (string) $data->default_source;
			}
			$known = self::get( (int) $row->subscription_id );
			$id    = $known ? (string) $known['id'] : '';
			if ( '' === $own ) {
				if ( '' !== $id && 0 !== strpos( $id, self::CUSTOMER ) ) {
					self::forget( (int) $row->subscription_id ); /* it bills the customer's default now: asked again when shown */
				}
				return;
			}
			if ( $own === $id ) {
				return;
			}
			$gateway = self::gateway();
			$object  = ( $gateway && method_exists( $gateway, 'api' ) && 0 === strpos( $own, 'pm_' ) ) ? $gateway->api( 'GET', 'payment_methods/' . rawurlencode( $own ) ) : null;
			if ( is_object( $object ) && ! is_wp_error( $object ) && self::record_payment_method( (int) $row->subscription_id, $object ) ) {
				return;
			}
			self::forget( (int) $row->subscription_id );
		}

		/**
		 * Stripe sent payment_method.updated or payment_method.automatically_updated: the card's details changed ( a new expiry date from the
		 * card network ): every subscription billing it follows.
		 *
		 * @param object $payment_method The Stripe payment method in the event.
		 */
		public static function webhook_payment_method( $payment_method ) {
			$card = self::normalize( $payment_method );
			if ( ! $card || ! self::ready() ) {
				return;
			}
			global $wpdb;
			$wpdb->query( $wpdb->prepare( 'UPDATE ec_subscription SET credit_card_type = %s, credit_card_last4 = %s, credit_card_exp_month = %d, credit_card_exp_year = %d WHERE stripe_payment_method_id IN ( %s, %s )', $card['brand'], $card['last4'], (int) $card['exp_month'], (int) $card['exp_year'], $card['id'], self::CUSTOMER . $card['id'] ) );
			self::$cache = array();
		}

		/* ------------------------------------------------------------------ orders */

		/**
		 * The card a renewal order records: the subscription's, else the account's last card ( as before 6.0.5 ).
		 *
		 * @param int    $subscription_id Subscription.
		 * @param object $user            ec_user row ( default_card_type, default_card_last4 ).
		 * @return array type, last4.
		 */
		public static function order_card( $subscription_id, $user ) {
			$card = self::get( (int) $subscription_id );
			if ( $card && '' !== $card['last4'] ) {
				return array(
					'type'  => $card['brand'],
					'last4' => $card['last4'],
				);
			}
			return array(
				'type'  => ( is_object( $user ) && isset( $user->default_card_type ) ) ? (string) $user->default_card_type : '',
				'last4' => ( is_object( $user ) && isset( $user->default_card_last4 ) ) ? (string) $user->default_card_last4 : '',
			);
		}

		/* ------------------------------------------------------------------ words */

		/**
		 * A brand as a key ( Stripe's payment method keys; card sources name the brand in words ).
		 *
		 * @param string $brand Brand or payment method type.
		 * @return string
		 */
		public static function brand_key( $brand ) {
			$brand = strtolower( trim( (string) $brand ) );
			$map   = array(
				'american express' => 'amex',
				'diners club'      => 'diners',
				'master card'      => 'mastercard',
				'union pay'        => 'unionpay',
			);
			$brand = isset( $map[ $brand ] ) ? $map[ $brand ] : $brand;
			return substr( preg_replace( '/[^a-z0-9_]/', '_', $brand ), 0, 50 );
		}

		/**
		 * A brand's name ( card and payment method names are not translated ).
		 *
		 * @param string $brand brand_key().
		 * @return string
		 */
		public static function brand_label( $brand ) {
			$key    = self::brand_key( $brand );
			$labels = array(
				'visa'             => 'Visa',
				'mastercard'       => 'Mastercard',
				'amex'             => 'American Express',
				'discover'         => 'Discover',
				'diners'           => 'Diners Club',
				'jcb'              => 'JCB',
				'unionpay'         => 'UnionPay',
				'cartes_bancaires' => 'Cartes Bancaires',
				'eftpos_au'        => 'eftpos',
				'interac'          => 'Interac',
				'link'             => 'Link',
				'sepa_debit'       => 'SEPA',
				'us_bank_account'  => 'ACH',
				'bacs_debit'       => 'Bacs',
				'au_becs_debit'    => 'BECS',
				'acss_debit'       => 'PAD',
				'card'             => 'Card',
			);
			$label  = isset( $labels[ $key ] ) ? $labels[ $key ] : ucwords( str_replace( '_', ' ', $key ) );
			/**
			 * The name My Account gives a card brand or payment method.
			 *
			 * @since 6.0.5
			 * @param string $label Name.
			 * @param string $key   brand_key().
			 */
			return (string) apply_filters( 'wp_easycart_subscription_card_label', $label, $key );
		}

		/**
		 * "Visa •••• 4242".
		 *
		 * @param array $card A card.
		 * @return string Plain text.
		 */
		public static function label( $card ) {
			if ( ! is_array( $card ) ) {
				return '';
			}
			$label = self::brand_label( $card['brand'] );
			return ( '' !== (string) $card['last4'] ) ? $label . ' •••• ' . $card['last4'] : $label;
		}

		/**
		 * The card's expiry date ( MM/YYYY ), '' when it has none.
		 *
		 * @param array $card A card.
		 * @return string
		 */
		public static function expiry( $card ) {
			if ( ! is_array( $card ) || (int) $card['exp_month'] < 1 || (int) $card['exp_year'] < 1 ) {
				return '';
			}
			$year = (int) $card['exp_year'];
			return sprintf( '%02d/%04d', (int) $card['exp_month'], ( $year < 100 ) ? 2000 + $year : $year );
		}

		/**
		 * The card's expiry date has passed.
		 *
		 * @param array $card A card.
		 * @param int   $now  Time ( tests ).
		 * @return bool
		 */
		public static function is_expired( $card, $now = 0 ) {
			if ( '' === self::expiry( $card ) ) {
				return false;
			}
			$now        = $now ? (int) $now : time();
			$year       = (int) $card['exp_year'];
			$year       = ( $year < 100 ) ? 2000 + $year : $year;
			$month      = (int) $card['exp_month'];
			$this_year  = (int) gmdate( 'Y', $now );
			$this_month = (int) gmdate( 'n', $now );
			return ( $year < $this_year ) || ( $year === $this_year && $month < $this_month );
		}

		/**
		 * A phrase ( language section account_subscriptions ), English when the store's file has none, with [tokens] filled.
		 *
		 * @param string $key  Phrase key.
		 * @param array  $fill token => text.
		 * @return string Plain text.
		 */
		public static function text( $key, $fill = array() ) {
			$english = array(
				'subscription_details_card_expires'      => 'Expires [date]',
				'subscription_details_card_expired'      => 'Expired [date]',
				'subscription_details_card_updated'      => 'Your payment method was updated.',
				'subscription_details_card_updated_many' => 'Your payment method was updated for [count] subscriptions.',
				'subscription_details_card_failed'       => 'Your payment method could not be changed for [titles]. Open that subscription to try again.',
				'subscription_details_card_apply'        => 'Use this card for',
				'subscription_details_card_apply_this'   => 'This subscription only',
				'subscription_details_card_apply_some'   => 'This subscription and others I choose',
				'subscription_details_card_apply_all'    => 'All my subscriptions ([count])',
				'subscription_details_card_apply_others' => 'Your other subscriptions',
				'subscription_details_card_apply_none'   => 'Choose at least one other subscription, or choose This subscription only.',
				'subscription_details_payments_more'     => 'Load more',
				'subscription_details_payments_shown'    => 'Showing [shown] of [total] payments',
				'subscription_details_view_all_orders'   => 'View all orders',
			);
			$text    = function_exists( 'wp_easycart_language' ) ? trim( (string) wp_easycart_language()->get_text( 'account_subscriptions', $key ) ) : '';
			if ( '' === $text ) {
				$text = isset( $english[ $key ] ) ? $english[ $key ] : '';
			}
			foreach ( (array) $fill as $token => $value ) {
				$text = str_replace( '[' . $token . ']', (string) $value, $text );
			}
			return wp_strip_all_tags( $text );
		}

		/* ------------------------------------------------------------------ the result of a change */

		/**
		 * The result the card change sent the customer back with ( card_updated = how many moved, card_failed = the ids that did
		 * not ). Display only. The account page drawn by AJAX ( cache prevention ) reads them from the page that asked for it.
		 *
		 * @return array updated, failed.
		 */
		public static function result_args() {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only: a count and ids of the customer's own subscriptions.
			$args = array(
				'card_updated' => isset( $_GET['card_updated'] ) ? sanitize_text_field( wp_unslash( $_GET['card_updated'] ) ) : '',
				'card_failed'  => isset( $_GET['card_failed'] ) ? sanitize_text_field( wp_unslash( $_GET['card_failed'] ) ) : '',
			);
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			if ( '' === $args['card_updated'] && '' === $args['card_failed'] && function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() && ! empty( $_SERVER['HTTP_REFERER'] ) ) {
				$query = wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ), PHP_URL_QUERY );
				$found = array();
				if ( is_string( $query ) ) {
					parse_str( $query, $found );
				}
				foreach ( array( 'card_updated', 'card_failed' ) as $name ) {
					if ( isset( $found[ $name ] ) && is_string( $found[ $name ] ) ) {
						$args[ $name ] = sanitize_text_field( $found[ $name ] );
					}
				}
			}
			return array(
				'updated' => max( 0, (int) $args['card_updated'] ),
				'failed'  => array_values( array_filter( array_map( 'intval', explode( ',', $args['card_failed'] ) ) ) ),
			);
		}

		/**
		 * The card change's result above the subscription's page.
		 *
		 * @param ec_subscription $subscription The subscription shown.
		 */
		public static function print_result( $subscription ) {
			if ( ! is_object( $subscription ) || empty( $subscription->subscription_id ) ) {
				return;
			}
			$result = self::result_args();
			if ( $result['updated'] > 0 ) {
				$text = ( $result['updated'] > 1 ) ? self::text( 'subscription_details_card_updated_many', array( 'count' => (int) $result['updated'] ) ) : self::text( 'subscription_details_card_updated' );
				echo '<div class="ec_account_subscription_v2_notice is-change-result ec_account_subscription_card_result" role="status">' . esc_html( $text ) . '</div>';
			}
			if ( ! empty( $result['failed'] ) ) {
				$user_id = isset( $GLOBALS['ec_cart_data']->cart_data->user_id ) ? (int) $GLOBALS['ec_cart_data']->cart_data->user_id : 0;
				$titles  = array();
				if ( $user_id > 0 ) {
					global $wpdb;
					$ids  = array_slice( $result['failed'], 0, 50 );
					$rows = $wpdb->get_col( $wpdb->prepare( 'SELECT title FROM ec_subscription WHERE user_id = %d AND subscription_id IN ( ' . implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) . ' ) ORDER BY subscription_id', array_merge( array( $user_id ), $ids ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- one %d placeholder per id, bound by prepare().
					foreach ( (array) $rows as $title ) {
						$titles[] = wp_strip_all_tags( function_exists( 'wp_easycart_language' ) ? wp_easycart_language()->convert_text( (string) $title ) : (string) $title );
					}
				}
				if ( ! empty( $titles ) ) {
					echo '<div class="ec_account_subscription_v2_notice is-card-failed ec_account_subscription_card_failed" role="alert">' . esc_html( self::text( 'subscription_details_card_failed', array( 'titles' => implode( ', ', $titles ) ) ) ) . '</div>';
				}
			}
		}

		/* ------------------------------------------------------------------ gateway */

		/**
		 * The store's Stripe gateway.
		 *
		 * @return object|false
		 */
		public static function gateway() {
			if ( class_exists( 'wp_easycart_subscription_prices' ) ) {
				return wp_easycart_subscription_prices::gateway();
			}
			$method = (string) get_option( 'ec_option_payment_process_method' );
			if ( 'stripe' === $method && class_exists( 'ec_stripe' ) ) {
				return new ec_stripe();
			}
			if ( 'stripe_connect' === $method && class_exists( 'ec_stripe_connect' ) ) {
				return new ec_stripe_connect();
			}
			return false;
		}
	}

	wp_easycart_subscription_cards::init();

endif;
