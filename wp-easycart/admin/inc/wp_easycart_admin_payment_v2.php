<?php
/**
 * WP EasyCart Admin — Settings › Payment ( V2 ) controller.
 *
 * Backs the declaration in admin/template/settings/payment.php. The page answers
 * "what can shoppers pay with, and is it working" at a glance:
 *
 *  - a gateway catalog ( free gateways + every PRO gateway listed by name and
 *    locked; PRO unlocks / extends it through 'wp_easycart_payment_gateway_catalog' );
 *  - a status resolver per gateway ( enabled, connected, test mode, key facts );
 *  - the "Active gateways" cards, the test-mode banner and the collapsed
 *    "More gateways" list ( section 'render' callables — they never enqueue );
 *  - the drawer ( ecv2_payment_gateway_form ): a gateway that has a form declaration
 *    ( filter 'wp_easycart_payment_gateway_form', see form() ) renders as V2 fields and
 *    saves from the drawer footer through ecv2_payment_gateway_save, which validates
 *    against the declaration and hands the values to the declaration's 'save' callable
 *    ( PRO runs its existing update_<gateway>() handler, so option names and side
 *    effects are unchanged ). A gateway without a declaration still embeds its legacy
 *    partial, driven by admin/js/payment.js; Bill later gets a small V2 form in the same
 *    drawer ( ecv2_payment_manual_form / ecv2_payment_manual_save );
 *  - after any save the cards and the "More gateways" list are re-rendered over AJAX
 *    ( ecv2_payment_state ) instead of reloading the page;
 *  - turn on / off, live / test switching and the one-click "Turn off test mode".
 *
 * Assets are enqueued from the page-level 'enqueue' callable in the declaration
 * ( admin_enqueue_scripts ), never from the render callables.
 *
 * Stored model ( unchanged ): ec_option_payment_process_method ( live gateway key ),
 * ec_option_payment_third_party ( third-party checkout key ), ec_option_use_direct_deposit
 * ( bill later ), ec_option_amazonpay_enable ( wallet ), plus each gateway's own
 * sandbox flag and credential options.
 *
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_payment_v2' ) ) :

class wp_easycart_admin_payment_v2 {

	const PAGE = 'payment';

	private static $catalog  = null;
	private static $status   = array();
	private static $enqueued = false;

	/* ------------------------------------------------------------------ */
	/* Edition                                                             */
	/* ------------------------------------------------------------------ */

	/** PRO installed, active and licensed ( the gate ). Every free-edition element hides when true. */
	public static function pro_active() {
		static $active = null;
		if ( null === $active ) {
			$active = class_exists( 'wp_easycart_admin_pro_gate' ) && wp_easycart_admin_pro_gate::is_enabled();
		}
		return (bool) $active;
	}

	/** Plan name for copy ( wp_easycart_admin_edition::plan_name(), 'Pro/Premium' when the helper is not loaded ). */
	private static function plan_name() {
		return class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::plan_name() : __( 'Pro/Premium', 'wp-easycart' );
	}

	/** Chip text for a locked gateway. */
	private static function pro_badge() {
		return class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::badge( 'pro' ) : __( 'Pro/Premium', 'wp-easycart' );
	}

	/** Refusal sentence for a locked gateway. */
	private static function locked_message( $gw ) {
		if ( class_exists( 'wp_easycart_admin_edition' ) ) {
			return wp_easycart_admin_edition::requires_text( $gw['label'], 'pro' );
		}
		/* translators: %s: gateway name. */
		return sprintf( __( '%s is included with Pro and Premium licenses.', 'wp-easycart' ), $gw['label'] );
	}

	/** Free edition: EasyCart Connect terms must be accepted before PayPal / Stripe / Square. */
	public static function terms_gate() {
		return ! self::pro_active() && ! get_option( 'ec_option_wpeasycart_terms_accepted' );
	}

	/** Free edition: the 2% application-fee note applies. */
	public static function show_fee_note() {
		return ! self::pro_active();
	}

	/* ------------------------------------------------------------------ */
	/* Catalog                                                             */
	/* ------------------------------------------------------------------ */

	/** Slot labels, in card order. */
	public static function roles() {
		return array(
			'live'        => array( 'label' => __( 'Live gateway', 'wp-easycart' ), 'hint' => __( 'Cards entered on your checkout page', 'wp-easycart' ), 'empty' => __( 'No live gateway', 'wp-easycart' ), 'empty_hint' => __( 'Shoppers cannot pay by card on your site until you connect one.', 'wp-easycart' ) ),
			'third_party' => array( 'label' => __( 'Third-party checkout', 'wp-easycart' ), 'hint' => __( 'Shoppers finish paying on the provider’s site', 'wp-easycart' ), 'empty' => __( 'No third-party checkout', 'wp-easycart' ), 'empty_hint' => __( 'PayPal and similar providers redirect the shopper to pay, then bring them back.', 'wp-easycart' ) ),
			'manual'      => array( 'label' => __( 'Bill later', 'wp-easycart' ), 'hint' => __( 'Order now, pay you offline', 'wp-easycart' ), 'empty' => __( 'Bill later', 'wp-easycart' ), 'empty_hint' => '' ),
			'wallet'      => array( 'label' => __( 'Wallet', 'wp-easycart' ), 'hint' => __( 'Pay with a stored account alongside the gateways above', 'wp-easycart' ), 'empty' => __( 'No wallet', 'wp-easycart' ), 'empty_hint' => '' ),
		);
	}

	/**
	 * Every gateway EasyCart knows about, keyed by the value stored in
	 * ec_option_payment_process_method / ec_option_payment_third_party.
	 *
	 * Entry keys ( all optional except label + role ):
	 *   label, role ( live | third_party | manual | wallet ), desc, docs ( helpsystem panel, default = key ),
	 *   pro ( bool: needs PRO ), available ( bool: PRO sets true when licensed ),
	 *   file ( absolute template path; '' = free path through 'wp_easycart_admin_payment_file' ),
	 *   drawer ( bool: has a settings form ), connect ( bool: OAuth-style onboarding ),
	 *   enable_key ( wallet role: the on/off option ),
	 *   creds ( option names that must all be non-empty ), creds_any ( at least one non-empty ),
	 *   test ( option name, or array( key, on, off ) ), facts ( option => label, shown on the card ).
	 */
	public static function catalog() {
		if ( null !== self::$catalog ) {
			return self::$catalog;
		}
		$free = array(
			'stripe_connect' => array( 'label' => 'Stripe', 'role' => 'live', 'connect' => true, 'docs' => 'stripe', 'desc' => __( 'Cards, Apple Pay, Google Pay, Link and local payment methods, entered on your checkout page.', 'wp-easycart' ), 'keywords' => array( 'stripe connect', 'apple pay', 'google pay', 'klarna', 'affirm' ) ),
			'square'         => array( 'label' => 'Square', 'role' => 'live', 'connect' => true, 'docs' => 'square', 'desc' => __( 'Cards on your site, with inventory and products kept in step with your Square account.', 'wp-easycart' ), 'keywords' => array( 'square', 'pos', 'sync' ) ),
			'paypal'         => array( 'label' => 'PayPal', 'role' => 'third_party', 'connect' => true, 'docs' => 'paypal', 'desc' => __( 'PayPal, Venmo and Pay Later buttons. No SSL certificate required.', 'wp-easycart' ), 'keywords' => array( 'paypal', 'venmo', 'pay later', 'express' ) ),
			'manual'         => array( 'label' => __( 'Bill later', 'wp-easycart' ), 'role' => 'manual', 'drawer' => false, 'docs' => 'manual', 'desc' => __( 'The order is placed and you collect payment yourself: bank transfer, cheque, pay on pickup.', 'wp-easycart' ), 'keywords' => array( 'manual', 'direct deposit', 'offline', 'invoice' ) ),
			'amazonpay'      => array( 'label' => 'Amazon Pay', 'role' => 'wallet', 'pro' => true, 'enable_key' => 'ec_option_amazonpay_enable', 'docs' => 'amazonpay', 'desc' => __( 'Shoppers pay with the address and card stored in their Amazon account.', 'wp-easycart' ), 'creds' => array( 'ec_option_amazonpay_store_id', 'ec_option_amazonpay_merchant_id', 'ec_option_amazonpay_public_key', 'ec_option_amazonpay_private_key' ), 'test' => 'ec_option_amazonpay_is_sandbox', 'facts' => array( 'ec_option_amazonpay_merchant_id' => __( 'Merchant ID', 'wp-easycart' ), 'ec_option_amazonpay_region' => __( 'Region', 'wp-easycart' ), 'ec_option_amazonpay_currency' => __( 'Currency', 'wp-easycart' ) ) ),
		);
		/* PRO gateways: listed by name so the upsell is visible; PRO fills in files, credentials and test flags. */
		$pro_live = array(
			'authorize'           => 'Authorize.net',
			'beanstream'          => 'Bambora',
			'braintree'           => 'Braintree S2S',
			'cardpointe'          => 'Cardpointe',
			'chronopay'           => 'Chronopay',
			'virtualmerchant'     => 'Converge (Virtual Merchant)',
			'eway'                => 'Eway',
			'firstdata'           => 'First Data Payeezy (e4)',
			'goemerchant'         => 'GoeMerchant',
			'intuit'              => 'Intuit Payments',
			'migs'                => 'MIGS',
			'moneris_ca'          => 'Moneris Canada',
			'moneris_us'          => 'Moneris USA',
			'nmi'                 => 'Network Merchants (NMI)',
			'sagepay'             => 'Opayo by Elavon (formerly Sagepay)',
			'sagepayus'           => 'Paya (previously Sagepay US)',
			'payline'             => 'Payline',
			'paymentexpress'      => 'Payment Express PxPost',
			'paypal_pro'          => 'PayPal PayFlow Pro',
			'paypal_payments_pro' => 'PayPal Payments Pro',
			'paypoint'            => 'PayPoint',
			'realex'              => 'Realex',
			'securepay'           => 'SecurePay',
			'stripe'              => 'Stripe (API keys, v1)',
			'securenet'           => 'WorldPay',
			'custom'              => __( 'Custom live gateway', 'wp-easycart' ),
		);
		$pro_third = array(
			'2checkout_thirdparty'      => '2Checkout',
			'cashfree'                  => 'CashFree',
			'dwolla_thirdparty'         => 'Dwolla',
			'nets'                      => 'Nets Nexaxept',
			'payfast_thirdparty'        => 'PayFast',
			'payfort'                   => 'Payfort',
			'paymentexpress_thirdparty' => 'Payment Express PxPay 2.0',
			'realex_thirdparty'         => 'Realex (hosted)',
			'redsys'                    => 'Redsys',
			'sagepay_paynow_za'         => 'SagePay Pay Now South Africa',
			'skrill'                    => 'Skrill',
			'custom_thirdparty'         => __( 'Custom third-party gateway', 'wp-easycart' ),
		);
		$catalog = $free;
		foreach ( $pro_live as $key => $label ) {
			$catalog[ $key ] = array( 'label' => $label, 'role' => 'live', 'pro' => true );
		}
		foreach ( $pro_third as $key => $label ) {
			$catalog[ $key ] = array( 'label' => $label, 'role' => 'third_party', 'pro' => true );
		}
		foreach ( array( 'custom', 'custom_thirdparty' ) as $key ) {
			$catalog[ $key ]['drawer'] = false;
			$catalog[ $key ]['desc']   = __( 'Configured in code through the EasyCart gateway hooks; nothing to enter here.', 'wp-easycart' );
		}
		$catalog = apply_filters( 'wp_easycart_payment_gateway_catalog', $catalog );
		$pro_active = self::pro_active();
		$clean = array();
		foreach ( (array) $catalog as $key => $gw ) {
			$key = sanitize_key( $key );
			if ( '' === $key || ! is_array( $gw ) || empty( $gw['label'] ) ) {
				continue;
			}
			$gw = wp_parse_args( $gw, array(
				'label'      => $key,
				'role'       => 'live',
				'desc'       => '',
				'docs'       => $key,
				'pro'        => false,
				'available'  => null,
				'file'       => '',
				'drawer'     => true,
				'connect'    => false,
				'enable_key' => '',
				'creds'      => array(),
				'creds_any'  => array(),
				'test'       => '',
				'facts'      => array(),
				'keywords'   => array(),
			) );
			if ( ! in_array( $gw['role'], array( 'live', 'third_party', 'manual', 'wallet' ), true ) ) {
				$gw['role'] = 'live';
			}
			if ( null === $gw['available'] ) {
				$gw['available'] = ! $gw['pro'];
			}
			/* Licensed PRO whose settings hook did not run ( older PRO build ): the gateway is still real,
			 * and its partial resolves through the legacy 'wp_easycart_admin_payment_file' filter. */
			if ( $pro_active && $gw['pro'] && ! $gw['available'] ) {
				$gw['available'] = true;
			}
			if ( is_string( $gw['test'] ) ) {
				$gw['test'] = ( '' !== $gw['test'] ) ? array( 'key' => $gw['test'], 'on' => '1', 'off' => '0' ) : array();
			} else {
				$gw['test'] = wp_parse_args( (array) $gw['test'], array( 'key' => '', 'on' => '1', 'off' => '0' ) );
				if ( '' === $gw['test']['key'] ) {
					$gw['test'] = array();
				}
			}
			$gw['key']     = $key;
			$gw['creds']   = array_values( (array) $gw['creds'] );
			$gw['facts']   = (array) $gw['facts'];
			$clean[ $key ] = $gw;
		}
		self::$catalog = $clean;
		return self::$catalog;
	}

	/**
	 * Partner gateways shown first, at equal weight, in the "choose a gateway" flow for a slot.
	 * Everything else stays one click away under "Other gateway". Entries missing from the
	 * catalog are dropped; the gating of each gateway is unchanged ( is_locked() still applies ).
	 *
	 * @since 6.0.0
	 * @param string $role live | third_party.
	 * @return array gateway key => one-line benefit.
	 */
	public static function partners( $role ) {
		$partners = array(
			'live'        => array(
				'stripe_connect' => __( 'Cards, Apple Pay, Google Pay, Link and local payment methods on your checkout page.', 'wp-easycart' ),
				'square'         => __( 'Cards on your site, with products and inventory kept in step with your Square account.', 'wp-easycart' ),
			),
			'third_party' => array(
				'paypal' => __( 'PayPal, Venmo and Pay Later buttons that shoppers already know.', 'wp-easycart' ),
			),
		);
		$list = isset( $partners[ $role ] ) ? $partners[ $role ] : array();
		$list = apply_filters( 'wp_easycart_payment_partner_gateways', $list, $role );
		$clean = array();
		foreach ( (array) $list as $key => $benefit ) {
			$gw = self::gateway( $key );
			if ( $gw && $gw['role'] === $role ) {
				$clean[ $gw['key'] ] = (string) $benefit;
			}
		}
		return $clean;
	}

	public static function gateway( $key ) {
		$catalog = self::catalog();
		$key = sanitize_key( $key );
		return isset( $catalog[ $key ] ) ? $catalog[ $key ] : false;
	}

	/** A PRO gateway is locked until PRO marks it available ( catalog filter, or the gate as a fallback ). */
	public static function is_locked( $gw ) {
		return ! empty( $gw['pro'] ) && empty( $gw['available'] );
	}

	/** Key of the gateway that currently fills a slot, or ''. */
	public static function active( $role ) {
		switch ( $role ) {
			case 'live':
				$key = sanitize_key( (string) get_option( 'ec_option_payment_process_method' ) );
				return ( '' !== $key && '0' !== $key && self::gateway( $key ) ) ? $key : '';
			case 'third_party':
				$key = sanitize_key( (string) get_option( 'ec_option_payment_third_party' ) );
				return ( '' !== $key && '0' !== $key && self::gateway( $key ) ) ? $key : '';
			case 'manual':
				return 'manual';
			case 'wallet':
				foreach ( self::catalog() as $key => $gw ) {
					if ( 'wallet' === $gw['role'] && ( $gw['available'] || ( '' !== $gw['enable_key'] && get_option( $gw['enable_key'] ) ) ) ) {
						return $key;
					}
				}
				return '';
		}
		return '';
	}

	/* ------------------------------------------------------------------ */
	/* Status                                                              */
	/* ------------------------------------------------------------------ */

	/**
	 * Resolve one gateway's state from the stored options ( no API calls ).
	 *
	 * @return array|false enabled, connected ( current environment has credentials ), ready ( any
	 *                     environment does ), test, live_ready, test_ready, locked, state, facts[ label => value ]
	 */
	public static function status( $key, $fresh = false ) {
		$key = sanitize_key( $key );
		if ( ! $fresh && isset( self::$status[ $key ] ) ) {
			return self::$status[ $key ];
		}
		$gw = self::gateway( $key );
		if ( ! $gw ) {
			return false;
		}
		$s = array(
			'key'        => $key,
			'enabled'    => false,
			'connected'  => false,
			'test'       => false,
			'live_ready' => false,
			'test_ready' => false,
			'locked'     => self::is_locked( $gw ),
			'facts'      => array(),
		);
		switch ( $gw['role'] ) {
			case 'live':
				$s['enabled'] = ( (string) get_option( 'ec_option_payment_process_method' ) === $key );
				break;
			case 'third_party':
				$s['enabled'] = ( (string) get_option( 'ec_option_payment_third_party' ) === $key );
				break;
			case 'manual':
				$s['enabled'] = (bool) get_option( 'ec_option_use_direct_deposit' );
				break;
			case 'wallet':
				$s['enabled'] = ( '' !== $gw['enable_key'] ) && (bool) get_option( $gw['enable_key'] );
				break;
		}

		switch ( $key ) {
			case 'stripe_connect':
				$s['test']       = (bool) get_option( 'ec_option_stripe_connect_use_sandbox' );
				$s['live_ready'] = '' !== (string) get_option( 'ec_option_stripe_connect_production_access_token' );
				$s['test_ready'] = '' !== (string) get_option( 'ec_option_stripe_connect_sandbox_access_token' );
				$s['connected']  = $s['test'] ? $s['test_ready'] : $s['live_ready'];
				$s['facts'][ __( 'Accounts', 'wp-easycart' ) ] = self::env_text( $s );
				self::fact( $s, __( 'Currency', 'wp-easycart' ), get_option( 'ec_option_stripe_currency' ) );
				self::fact( $s, __( 'Business country', 'wp-easycart' ), get_option( 'ec_option_stripe_company_country' ) );
				$wallets = array();
				foreach ( self::stripe_methods() as $option => $label ) {
					if ( get_option( $option ) ) {
						$wallets[] = $label;
					}
				}
				self::fact( $s, __( 'Extra methods', 'wp-easycart' ), $wallets ? implode( ', ', $wallets ) : __( 'Cards only', 'wp-easycart' ) );
				if ( $s['connected'] ) {
					self::fact( $s, __( 'Webhook URL', 'wp-easycart' ), get_site_url() . '?wpeasycarthook=stripe-webhook' );
				}
				break;

			case 'square':
				$s['test']       = (bool) get_option( 'ec_option_square_is_sandbox' );
				$s['live_ready'] = '' !== (string) get_option( 'ec_option_square_access_token' );
				$s['test_ready'] = '' !== (string) get_option( 'ec_option_square_sandbox_access_token' );
				$s['connected']  = $s['test'] ? $s['test_ready'] : $s['live_ready'];
				$s['facts'][ __( 'Accounts', 'wp-easycart' ) ] = self::env_text( $s );
				self::fact( $s, __( 'Merchant name', 'wp-easycart' ), get_option( 'ec_option_square_merchant_name' ) );
				$location = (string) get_option( $s['test'] ? 'ec_option_square_sandbox_location_id' : 'ec_option_square_location_id' );
				self::fact( $s, __( 'Location', 'wp-easycart' ), ( '' === $location || '0' === $location ) ? __( 'Default location', 'wp-easycart' ) : $location );
				self::fact( $s, __( 'Digital wallets', 'wp-easycart' ), get_option( 'ec_option_square_digital_wallet' ) ? __( 'Apple / Google Pay on', 'wp-easycart' ) : __( 'Off', 'wp-easycart' ) );
				self::fact( $s, __( 'Access renews', 'wp-easycart' ), get_option( $s['test'] ? 'ec_option_square_sandbox_token_expires' : 'ec_option_square_token_expires' ) );
				break;

			case 'paypal':
				$s['test']       = (bool) get_option( 'ec_option_paypal_use_sandbox' );
				$express         = (bool) get_option( 'ec_option_paypal_enable_pay_now' );
				$s['live_ready'] = '' !== (string) get_option( 'ec_option_paypal_production_merchant_id' );
				$s['test_ready'] = '' !== (string) get_option( 'ec_option_paypal_sandbox_merchant_id' );
				$email           = (string) get_option( 'ec_option_paypal_email' );
				$s['connected']  = $s['test'] ? $s['test_ready'] : $s['live_ready'];
				if ( ! $s['connected'] && ! $express && '' !== $email ) {
					$s['connected']  = true; /* PayPal Standard: the merchant email is the whole setup. */
					$s['live_ready'] = true;
				}
				self::fact( $s, __( 'Checkout', 'wp-easycart' ), $express ? __( 'PayPal Checkout buttons on your site', 'wp-easycart' ) : __( 'PayPal Standard ( redirect )', 'wp-easycart' ) );
				$s['facts'][ __( 'Accounts', 'wp-easycart' ) ] = self::env_text( $s );
				self::fact( $s, __( 'Merchant ID', 'wp-easycart' ), get_option( $s['test'] ? 'ec_option_paypal_sandbox_merchant_id' : 'ec_option_paypal_production_merchant_id' ) );
				self::fact( $s, __( 'Account email', 'wp-easycart' ), $email );
				self::fact( $s, __( 'Currency', 'wp-easycart' ), get_option( 'ec_option_paypal_currency_code' ) );
				$buttons = array();
				if ( get_option( 'ec_option_paypal_use_venmo' ) ) {
					$buttons[] = 'Venmo';
				}
				if ( get_option( 'ec_option_paypal_use_card' ) ) {
					$buttons[] = __( 'Card', 'wp-easycart' );
				}
				if ( get_option( 'ec_option_paypal_use_paylater' ) ) {
					$buttons[] = __( 'Pay Later', 'wp-easycart' );
				}
				if ( $express ) {
					self::fact( $s, __( 'Extra buttons', 'wp-easycart' ), $buttons ? implode( ', ', $buttons ) : __( 'PayPal only', 'wp-easycart' ) );
				}
				break;

			case 'manual':
				$s['connected']  = true;
				$s['live_ready'] = true;
				self::fact( $s, __( 'Shown as', 'wp-easycart' ), self::manual_title() );
				$message = trim( wp_strip_all_tags( (string) get_option( 'ec_option_direct_deposit_message' ) ) );
				self::fact( $s, __( 'Instructions', 'wp-easycart' ), '' === $message ? __( 'None written yet', 'wp-easycart' ) : ( strlen( $message ) > 90 ? substr( $message, 0, 87 ) . '…' : $message ) );
				break;

			case 'stripe':
				$pk = (string) get_option( 'ec_option_stripe_public_api_key' );
				$s['connected']  = '' !== $pk && '' !== (string) get_option( 'ec_option_stripe_api_key' );
				$s['test']       = ( 0 === strpos( $pk, 'pk_test' ) );
				$s['live_ready'] = $s['connected'] && ! $s['test'];
				$s['test_ready'] = $s['connected'] && $s['test'];
				self::generic_facts( $s, $gw );
				break;

			default:
				$missing = false;
				foreach ( $gw['creds'] as $option ) {
					if ( '' === trim( (string) get_option( $option ) ) ) {
						$missing = true;
						break;
					}
				}
				if ( ! $missing && ! empty( $gw['creds_any'] ) ) {
					$missing = true;
					foreach ( (array) $gw['creds_any'] as $option ) {
						if ( '' !== trim( (string) get_option( $option ) ) ) {
							$missing = false;
							break;
						}
					}
				}
				$s['connected'] = ! $missing;
				if ( ! empty( $gw['test'] ) ) {
					$s['test'] = ( (string) get_option( $gw['test']['key'] ) === (string) $gw['test']['on'] );
				}
				$s['live_ready'] = $s['connected'];
				$s['test_ready'] = $s['connected'] && ! empty( $gw['test'] );
				self::generic_facts( $s, $gw );
		}

		/* 6.0.1: has anyone started on this gateway? Nothing filled in means "Connect"; a half-filled one means "Finish setup". */
		$s['started'] = $s['connected'] || $s['enabled'];
		if ( ! $s['started'] ) {
			foreach ( array_merge( (array) $gw['creds'], isset( $gw['creds_any'] ) ? (array) $gw['creds_any'] : array() ) as $option ) {
				if ( '' !== trim( (string) get_option( $option ) ) ) {
					$s['started'] = true;
					break;
				}
			}
		}

		$s = apply_filters( 'wp_easycart_payment_gateway_status', $s, $gw );

		$s['ready'] = $s['connected'] || $s['live_ready'] || $s['test_ready'];
		if ( $s['locked'] ) {
			$s['state'] = 'locked';
		} elseif ( $s['enabled'] && $s['connected'] ) {
			$s['state'] = 'connected';
		} elseif ( $s['enabled'] ) {
			$s['state'] = 'incomplete';
		} elseif ( $s['connected'] && 'manual' !== $gw['role'] ) {
			$s['state'] = 'off';
		} else {
			$s['state'] = ( 'manual' === $gw['role'] ) ? 'off' : 'not_setup';
		}
		self::$status[ $key ] = $s;
		return $s;
	}

	private static function fact( &$s, $label, $value ) {
		$value = trim( (string) $value );
		if ( '' !== $value ) {
			$s['facts'][ $label ] = $value;
		}
	}

	private static function generic_facts( &$s, $gw ) {
		foreach ( $gw['facts'] as $option => $label ) {
			self::fact( $s, $label, get_option( $option ) );
		}
	}

	private static function env_text( $s ) {
		$parts = array();
		$parts[] = $s['live_ready'] ? __( 'Live connected', 'wp-easycart' ) : __( 'Live not connected', 'wp-easycart' );
		$parts[] = $s['test_ready'] ? __( 'Sandbox connected', 'wp-easycart' ) : __( 'Sandbox not connected', 'wp-easycart' );
		return implode( ' · ', $parts );
	}

	/** Stripe payment-method options → short labels, for the card facts. */
	public static function stripe_methods() {
		return array(
			'ec_option_stripe_enable_apple_pay' => __( 'Apple / Google Pay', 'wp-easycart' ),
			'ec_option_stripe_link'             => 'Link',
			'ec_option_stripe_affirm'           => 'Affirm',
			'ec_option_stripe_afterpay'         => 'Afterpay',
			'ec_option_stripe_klarna'           => 'Klarna',
			'ec_option_stripe_alipay'           => 'Alipay',
			'ec_option_stripe_grabpay'          => 'GrabPay',
			'ec_option_stripe_wechat'           => 'WeChat Pay',
			'ec_option_stripe_bancontact'       => 'Bancontact',
			'ec_option_stripe_blik'             => 'BLIK',
			'ec_option_stripe_eps'              => 'EPS',
			'ec_option_stripe_fpx'              => 'FPX',
			'ec_option_stripe_giropay'          => 'giropay',
			'ec_option_stripe_enable_ideal'     => 'iDEAL',
			'ec_option_stripe_p24'              => 'Przelewy24',
			'ec_option_stripe_sofort'           => 'Sofort',
			'ec_option_stripe_bacs'             => 'Bacs',
			'ec_option_stripe_becs'             => 'BECS',
			'ec_option_stripe_sepa'             => 'SEPA',
			'ec_option_stripe_pix'              => 'Pix',
			'ec_option_stripe_paynow'           => 'PayNow',
			'ec_option_stripe_promptpay'        => 'PromptPay',
			'ec_option_stripe_boleto'           => 'Boleto',
			'ec_option_stripe_konbini'          => 'Konbini',
			'ec_option_stripe_oxxo'             => 'OXXO',
		);
	}

	/** Keys of every enabled gateway that is currently in sandbox / test mode. */
	public static function test_mode_gateways() {
		$keys = array();
		foreach ( self::catalog() as $key => $gw ) {
			$s = self::status( $key );
			if ( $s && $s['enabled'] && $s['test'] && ! $s['locked'] ) {
				$keys[] = $key;
			}
		}
		return $keys;
	}

	/* ------------------------------------------------------------------ */
	/* Bill later ( manual payment ) options                               */
	/* ------------------------------------------------------------------ */

	/** Language file the legacy form edited: the store language option. */
	private static function language_file() {
		$file = (string) get_option( 'ec_option_language' );
		return '' !== $file ? $file : 'en-us';
	}

	/** The pay-later option name shoppers see ( language string cart_payment_information_manual_payment ). */
	public static function manual_title() {
		if ( function_exists( 'wp_easycart_language' ) ) {
			return (string) wp_easycart_language()->get_text( 'cart_payment_information', 'cart_payment_information_manual_payment' );
		}
		return '';
	}

	/**
	 * Save the Bill later wording. Mirrors update_manual_billing_settings(): the title goes into
	 * ec_option_language_data ( update_language_item ), the message is a plain option, and the
	 * tracking action fires. Values are cleaned with the registry's own sanitizers.
	 *
	 * @return string|WP_Error message
	 */
	public static function save_manual( $title, $message ) {
		if ( ! class_exists( 'wp_easycart_admin_settings_registry' ) ) {
			return new WP_Error( 'ecpay_registry', __( 'The settings engine is not available.', 'wp-easycart' ) );
		}
		$title_field   = wp_easycart_admin_settings_registry::normalize_field( 'cart_payment_information_manual_payment', array( 'type' => 'text' ) );
		$message_field = wp_easycart_admin_settings_registry::normalize_field( 'ec_option_direct_deposit_message', array( 'type' => 'textarea' ) );
		$title   = wp_easycart_admin_settings_registry::sanitize( $title_field, $title );
		$message = wp_easycart_admin_settings_registry::sanitize( $message_field, $message );
		if ( is_wp_error( $title ) ) {
			return $title;
		}
		if ( is_wp_error( $message ) ) {
			return $message;
		}
		if ( '' === trim( (string) $title ) ) {
			return new WP_Error( 'ecpay_title', __( 'Give the pay-later option a name; shoppers pick it by this name at checkout.', 'wp-easycart' ) );
		}
		update_option( 'ec_option_direct_deposit_message', $message );
		if ( function_exists( 'wp_easycart_language' ) ) {
			$file = self::language_file();
			$data = wp_easycart_language()->get_language_data();
			if ( is_object( $data ) && isset( $data->{$file} ) && isset( $data->{$file}->options->cart_payment_information->options->cart_payment_information_manual_payment ) ) {
				wp_easycart_language()->update_language_item( $file, 'cart_payment_information', 'cart_payment_information_manual_payment', $title );
			}
		}
		do_action( 'wpeasycart_manual_billing_updated', (int) get_option( 'ec_option_use_direct_deposit' ) );
		self::$status = array();
		return __( 'Bill later wording saved.', 'wp-easycart' );
	}

	/* ------------------------------------------------------------------ */
	/* Onboarding URLs ( same endpoints and return handlers as before )     */
	/* ------------------------------------------------------------------ */

	/** array( 'live' => url, 'test' => url ) for Connect-style gateways; empty for the rest. */
	public static function connect_urls( $key ) {
		$admin = esc_url_raw( admin_url() );
		$base  = function_exists( 'wp_easycart_admin' ) && method_exists( wp_easycart_admin(), 'get_available_url' ) ? esc_url_raw( wp_easycart_admin()->get_available_url() ) : 'https://connect.wpeasycart.com';
		switch ( $key ) {
			case 'stripe_connect':
				$nonce = wp_create_nonce( 'wp-easycart-stripe' );
				return array(
					'live' => $base . '/connect/?step=start&redirect=' . rawurlencode( $admin . '?ec_admin_form_action=stripe_onboard&env=production&wp_easycart_nonce=' . $nonce ) . '&env=production',
					'test' => $base . '/connect/?step=start&redirect=' . rawurlencode( $admin . '?ec_admin_form_action=stripe_onboard&env=sandbox&wp_easycart_nonce=' . $nonce ) . '&env=sandbox',
				);
			case 'square':
				$state = wp_create_nonce( 'wp-easycart-square' );
				return array(
					'live' => 'https://connect.wpeasycart.com/square-v2/?url=' . rawurlencode( $admin . '?ec_admin_form_action=handle-square' ) . '&state=' . $state,
					'test' => 'https://connect.wpeasycart.com/square-sandbox/?url=' . rawurlencode( $admin . '?ec_admin_form_action=handle-square' ) . '&state=' . $state,
				);
			case 'paypal':
				$nonce = wp_create_nonce( 'wp-easycart-paypal' );
				return array(
					'live' => $base . '/paypal-v2/production_onboard.php?redirect=' . rawurlencode( $admin . '?wpeasycart_paypal_onboard=production&wp_easycart_nonce=' . $nonce ),
					'test' => $base . '/paypal-v2/sandbox_onboard.php?redirect=' . rawurlencode( $admin . '?wpeasycart_paypal_onboard=sandbox&wp_easycart_nonce=' . $nonce ),
				);
		}
		return array();
	}

	/**
	 * Legacy GET form actions ( handled by wp_easycart_admin_payments ) for disconnecting. Square and PayPal
	 * links carry a _wpnonce for 'wp-easycart-payment-<gateway>-disconnect', checked by ecv2_payment_link_guard();
	 * Stripe keeps its wp_easycart_nonce for 'wp-easycart-stripe'.
	 */
	public static function disconnect_url( $key, $env ) {
		$page = admin_url( 'admin.php?page=wp-easycart-settings&subpage=payment' );
		switch ( $key ) {
			case 'stripe_connect':
				return add_query_arg( array( 'ec_admin_form_action' => ( 'test' === $env ) ? 'stripe-connect-sandbox-disconnect' : 'stripe-connect-production-disconnect', 'wp_easycart_nonce' => wp_create_nonce( 'wp-easycart-stripe' ) ), $page );
			case 'square':
				$args = array( 'ec_admin_form_action' => 'square-disconnect' );
				if ( 'test' === $env ) {
					$args['sandbox'] = '1';
				}
				return wp_nonce_url( add_query_arg( $args, $page ), 'wp-easycart-payment-square-disconnect' );
			case 'paypal':
				return wp_nonce_url( add_query_arg( array( 'ec_admin_form_action' => ( 'test' === $env ) ? 'paypal-express-sandbox-disconnect' : 'paypal-express-production-disconnect' ), $page ), 'wp-easycart-payment-paypal-disconnect' );
		}
		return '';
	}

	/** Docs link for a gateway ( helpsystem ), or ''. */
	public static function docs_url( $gw ) {
		if ( function_exists( 'wp_easycart_admin' ) && isset( wp_easycart_admin()->helpsystem ) && method_exists( wp_easycart_admin()->helpsystem, 'print_docs_url' ) ) {
			return (string) wp_easycart_admin()->helpsystem->print_docs_url( 'settings', 'payment', $gw['docs'] );
		}
		return '';
	}

	/* ------------------------------------------------------------------ */
	/* Assets ( called from the declaration's page-level 'enqueue' )        */
	/* ------------------------------------------------------------------ */

	public static function enqueue( $page = null ) {
		if ( self::$enqueued ) {
			return;
		}
		self::$enqueued = true;
		$css = plugins_url( '/admin/css/', EC_PLUGIN_DIRECTORY . '/wpeasycart.php' );
		$js  = plugins_url( '/admin/js/', EC_PLUGIN_DIRECTORY . '/wpeasycart.php' );
		/* Drawer chrome ( .ecdrawer ) lives in the lists stylesheet; reuse it rather than fork it. */
		wp_enqueue_style( 'wp_easycart_admin_settings_lists_v2_css', $css . 'settings-lists-v2.css', array( 'wp_easycart_admin_settings_page_v2_css' ), EC_CURRENT_VERSION );
		wp_enqueue_style( 'wp_easycart_admin_settings_payment_v2_css', $css . 'settings-payment-v2.css', array( 'wp_easycart_admin_settings_page_v2_css', 'wp_easycart_admin_settings_lists_v2_css' ), EC_CURRENT_VERSION );
		/* The legacy gateway partials are driven by admin/js/payment.js; the admin bootstrap registers it for subpage=payment, this covers every other route. */
		if ( ! wp_script_is( 'wp_easycart_admin_payment_js', 'registered' ) ) {
			wp_register_script( 'wp_easycart_admin_payment_js', $js . 'payment.js', array( 'jquery' ), EC_CURRENT_VERSION );
			wp_localize_script( 'wp_easycart_admin_payment_js', 'wp_easycart_payment_language', array(
				'advanced-options' => __( 'Advanced Options', 'wp-easycart' ),
				'one-click-setup'  => __( 'Back to One-Click Express Setup', 'wp-easycart' ),
				'manual-api-input' => __( 'Use Manual API Credential Input', 'wp-easycart' ),
			) );
		}
		wp_enqueue_script( 'wp_easycart_admin_payment_js' );
		wp_enqueue_script( 'wp_easycart_admin_settings_payment_v2_js', $js . 'settings-payment-v2.js', array( 'jquery', 'wp_easycart_admin_settings_page_v2_js', 'wp_easycart_admin_payment_js' ), EC_CURRENT_VERSION, true );
		wp_localize_script( 'wp_easycart_admin_settings_payment_v2_js', 'ecpay_vars', array(
			'ajax'        => admin_url( 'admin-ajax.php' ),
			'nonce'       => wp_create_nonce( wp_easycart_admin_settings_registry::NONCE ),
			'page'        => self::PAGE,
			'user'        => (int) get_current_user_id(),
			'terms_nonce' => wp_create_nonce( 'wp-easycart-terms-accept' ),
			'i18n'        => array(
				'loading'        => __( 'Loading settings…', 'wp-easycart' ),
				'load_failed'    => __( 'Could not load this gateway’s settings.', 'wp-easycart' ),
				'close'          => __( 'Close', 'wp-easycart' ),
				'docs'           => __( 'Docs', 'wp-easycart' ),
				'save'           => __( 'Save', 'wp-easycart' ),
				'saving'         => __( 'Saving…', 'wp-easycart' ),
				'drawer_note'    => __( 'Changes save from the controls in this form.', 'wp-easycart' ),
				'save_on'        => __( 'Save and turn on', 'wp-easycart' ),
				'saved'          => __( 'Settings saved.', 'wp-easycart' ),
				'unsaved'        => __( 'Unsaved changes', 'wp-easycart' ),
				'no_changes'     => __( 'No unsaved changes', 'wp-easycart' ),
				'confirm_leave'  => __( 'You have unsaved changes in this drawer. Close it and lose them?', 'wp-easycart' ),
				'fix_fields'     => __( 'Check the highlighted fields.', 'wp-easycart' ),
				'show'           => __( 'Show', 'wp-easycart' ),
				'hide'           => __( 'Hide', 'wp-easycart' ),
				'copy'           => __( 'Copy', 'wp-easycart' ),
				'copied'         => __( 'Copied', 'wp-easycart' ),
				/* translators: %s: gateway name. */
				'legacy_selects' => __( 'Saving this form also makes %s the gateway for its slot.', 'wp-easycart' ),
				'manual_title'   => __( 'Bill later wording', 'wp-easycart' ),
				'working'        => __( 'Working…', 'wp-easycart' ),
				'opening'        => __( 'Opening…', 'wp-easycart' ),
				'request_failed' => __( 'Request failed.', 'wp-easycart' ),
				'confirm_off'    => __( 'Turn this gateway off? Shoppers will no longer see it at checkout.', 'wp-easycart' ),
				'confirm_swap'   => __( 'This replaces your current gateway in this slot. Continue?', 'wp-easycart' ),
				'terms_first'    => __( 'Accept the EasyCart Connect terms first.', 'wp-easycart' ),
				'browse'         => __( 'Browse gateways', 'wp-easycart' ),
				'collapse'       => __( 'Hide list', 'wp-easycart' ),
			),
		) );
	}

	/* ------------------------------------------------------------------ */
	/* Rendering ( never enqueues )                                        */
	/* ------------------------------------------------------------------ */

	/** Section "Active gateways": flash, terms gate, test-mode banner, one card per slot. */
	public static function render_active( $page, $section ) {
		$gate  = self::terms_gate();
		$tests = self::test_mode_gateways();
		self::render_flash();
		if ( $gate ) {
			self::render_terms();
		}
		if ( $tests ) {
			$names = array();
			foreach ( $tests as $key ) {
				$gw = self::gateway( $key );
				$names[] = $gw['label'];
			}
			?>
			<div class="ecpay-banner" id="ecpay_test_banner" role="status">
				<span class="dashicons dashicons-warning" aria-hidden="true"></span>
				<div class="ecpay-banner-text">
					<b><?php echo esc_html( sprintf( _n( '%s is in test mode', '%s are in test mode', count( $names ), 'wp-easycart' ), implode( ', ', $names ) ) ); ?></b>
					<span><?php esc_html_e( 'Orders are not charged. Switch back to live before you open the store.', 'wp-easycart' ); ?></span>
				</div>
				<button type="button" class="ecv2-btn ecv2-btn-primary ecpay-banner-btn" data-ecpay-test-off="1"><?php esc_html_e( 'Turn off test mode', 'wp-easycart' ); ?></button>
			</div>
			<?php
		}
		/* Partner-first chooser per slot: shown open when the slot is empty, hidden behind "Change gateway" when it is filled.
		 * Taking cards comes first ( above the cards ); the optional third-party checkout follows them. */
		$choosers = array();
		foreach ( array( 'live', 'third_party' ) as $role ) {
			if ( self::partners( $role ) ) {
				$choosers[ $role ] = true;
			}
		}
		if ( isset( $choosers['live'] ) ) {
			self::render_chooser( 'live', $gate );
		}
		echo '<div class="ecpay-cards">';
		foreach ( self::roles() as $role => $meta ) {
			$key = self::active( $role );
			if ( '' === $key ) {
				if ( 'wallet' === $role ) {
					/* 6.0.1: the wallet slot used to be dropped entirely until a wallet was switched on, so on the
					   free edition Amazon Pay appeared nowhere on this page unless the merchant thought to expand
					   "Browse gateways". It gets its own card now: an upsell while it is locked, a Set up button
					   once it is not. */
					self::render_wallet_card( $meta, $gate );
					continue;
				}
				if ( isset( $choosers[ $role ] ) ) {
					continue; /* Live / third-party: the chooser stands in for the empty card. */
				}
				self::render_empty_card( $role, $meta );
				continue;
			}
			self::render_card( $role, $meta, $key, $gate, isset( $choosers[ $role ] ) );
		}
		echo '</div>';
		if ( isset( $choosers['third_party'] ) ) {
			self::render_chooser( 'third_party', $gate );
		}
		if ( self::show_fee_note() ) {
			?>
			<p class="ecpay-fee-note"><?php esc_html_e( 'Free edition: Bill later is free to use. PayPal, Stripe and Square are offered through EasyCart Connect with a 2% application fee on top of the provider’s own fees.', 'wp-easycart' ); ?> <a href="#" onclick="ecst.upsell( 'payment_fees' ); return false;"><?php /* translators: %s: plan name, Pro/Premium on the free edition. */ echo esc_html( sprintf( __( 'Upgrade to %s to remove the fee and unlock 30+ gateways', 'wp-easycart' ), self::plan_name() ) ); ?> →</a></p>
			<?php
		}
	}

	/** Section "More gateways": a one-line summary, expanding in place to the full list. */
	public static function render_more( $page, $section ) {
		$gate   = self::terms_gate();
		$active = array();
		foreach ( array_keys( self::roles() ) as $role ) {
			$key = self::active( $role );
			if ( '' !== $key ) {
				$active[ $key ] = true;
			}
		}
		$groups = array(
			'live'        => array( 'label' => __( 'Card gateways ( live )', 'wp-easycart' ), 'rows' => array() ),
			'third_party' => array( 'label' => __( 'Third-party checkout', 'wp-easycart' ), 'rows' => array() ),
			'wallet'      => array( 'label' => __( 'Wallets', 'wp-easycart' ), 'rows' => array() ),
		);
		foreach ( self::catalog() as $key => $gw ) {
			if ( isset( $active[ $key ] ) || 'manual' === $gw['role'] || ! isset( $groups[ $gw['role'] ] ) ) {
				continue;
			}
			$groups[ $gw['role'] ]['rows'][ $key ] = $gw;
		}
		$parts = array();
		if ( ! empty( $groups['live']['rows'] ) ) {
			$parts[] = sprintf( _n( '%d more card gateway', '%d more card gateways', count( $groups['live']['rows'] ), 'wp-easycart' ), count( $groups['live']['rows'] ) );
		}
		if ( ! empty( $groups['third_party']['rows'] ) ) {
			$parts[] = sprintf( _n( '%d third-party checkout', '%d third-party checkouts', count( $groups['third_party']['rows'] ), 'wp-easycart' ), count( $groups['third_party']['rows'] ) );
		}
		if ( ! empty( $groups['wallet']['rows'] ) ) {
			$parts[] = sprintf( _n( '%d wallet', '%d wallets', count( $groups['wallet']['rows'] ), 'wp-easycart' ), count( $groups['wallet']['rows'] ) );
		}
		if ( ! $parts ) {
			echo '<div class="ecpay-more-empty">' . esc_html__( 'Every gateway is already active.', 'wp-easycart' ) . '</div>';
			return;
		}
		$last    = array_pop( $parts );
		$summary = $parts ? sprintf( __( '%1$s and %2$s available', 'wp-easycart' ), implode( ', ', $parts ), $last ) : sprintf( __( '%s available', 'wp-easycart' ), $last );
		?>
		<div class="ecpay-more" id="ecpay_more">
			<div class="ecpay-more-summary" id="ecpay_more_summary">
				<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
				<b><?php echo esc_html( $summary ); ?></b>
				<span class="ecst-grow"></span>
				<button type="button" class="ecv2-btn ecv2-btn-sm" id="ecpay_more_toggle" aria-expanded="false" aria-controls="ecpay_more_list"><?php esc_html_e( 'Browse gateways', 'wp-easycart' ); ?></button>
			</div>
			<div class="ecpay-more-list" id="ecpay_more_list" hidden>
				<div class="ecpay-more-filter" role="tablist">
					<button type="button" class="ecpay-pill is-on" data-ecpay-filter="all"><?php esc_html_e( 'All', 'wp-easycart' ); ?></button>
					<?php foreach ( $groups as $role => $group ) : ?>
						<?php if ( ! empty( $group['rows'] ) ) : ?>
							<button type="button" class="ecpay-pill" data-ecpay-filter="<?php echo esc_attr( $role ); ?>"><?php echo esc_html( $group['label'] ); ?> <em><?php echo (int) count( $group['rows'] ); ?></em></button>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
				<?php foreach ( $groups as $role => $group ) : ?>
					<?php if ( empty( $group['rows'] ) ) { continue; } ?>
					<div class="ecpay-more-group" data-role="<?php echo esc_attr( $role ); ?>">
						<h4><?php echo esc_html( $group['label'] ); ?></h4>
						<?php foreach ( $group['rows'] as $key => $gw ) : ?>
							<?php $s = self::status( $key ); ?>
							<div class="ecpay-more-row<?php echo $s['locked'] ? ' is-locked' : ''; ?>" data-gateway="<?php echo esc_attr( $key ); ?>" data-search="<?php echo esc_attr( strtolower( $gw['label'] . ' ' . implode( ' ', (array) $gw['keywords'] ) ) ); ?>">
								<?php self::render_logo( $key, $gw ); ?>
								<div class="ecpay-more-text">
									<b><?php echo esc_html( $gw['label'] ); ?><?php if ( $s['locked'] ) : ?> <span class="ecst-pro-tag"><?php echo esc_html( self::pro_badge() ); ?></span><?php endif; ?></b>
									<?php if ( '' !== $gw['desc'] ) : ?><span><?php echo esc_html( $gw['desc'] ); ?></span><?php endif; ?>
								</div>
								<div class="ecpay-more-chips">
									<?php if ( ! $s['locked'] && $s['ready'] ) : ?><span class="ecv2-chip ecv2-chip-gray"><?php esc_html_e( 'Set up, off', 'wp-easycart' ); ?></span><?php endif; ?>
									<?php if ( ! $s['locked'] && $s['ready'] && $s['test'] ) : ?><span class="ecv2-chip ecv2-chip-amber"><?php esc_html_e( 'Test keys', 'wp-easycart' ); ?></span><?php endif; ?>
								</div>
								<div class="ecpay-more-act">
									<?php if ( $s['locked'] ) : ?>
										<button type="button" class="ecst-pro-btn" onclick="ecst.upsell( '<?php echo esc_js( $key ); ?>' ); return false;">🔒 <?php echo esc_html( self::pro_badge() ); ?></button>
									<?php elseif ( $gw['connect'] && ! $s['ready'] ) : ?>
										<?php $urls = self::connect_urls( $key ); ?>
										<a class="ecv2-btn ecv2-btn-sm ecv2-btn-primary ecpay-connect" href="<?php echo $gate ? '#' : esc_url( $urls['live'] ); ?>" data-href="<?php echo esc_url( $urls['live'] ); ?>"<?php echo $gate ? ' aria-disabled="true"' : ''; ?>><?php esc_html_e( 'Connect', 'wp-easycart' ); ?></a>
										<a class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost ecpay-connect" href="<?php echo $gate ? '#' : esc_url( $urls['test'] ); ?>" data-href="<?php echo esc_url( $urls['test'] ); ?>"<?php echo $gate ? ' aria-disabled="true"' : ''; ?>><?php esc_html_e( 'Try sandbox', 'wp-easycart' ); ?></a>
									<?php elseif ( $s['ready'] ) : ?>
										<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" data-ecpay-toggle="<?php echo esc_attr( $key ); ?>" data-on="1" data-swap="<?php echo ( '' !== self::active( $gw['role'] ) && 'wallet' !== $gw['role'] ) ? '1' : '0'; ?>"><?php esc_html_e( 'Turn on', 'wp-easycart' ); ?></button>
										<?php if ( $gw['drawer'] ) : ?><button type="button" class="ecv2-btn ecv2-btn-sm" data-ecpay-open="<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'Settings', 'wp-easycart' ); ?></button><?php endif; ?>
									<?php elseif ( $gw['drawer'] ) : ?>
										<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" data-ecpay-open="<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'Set up', 'wp-easycart' ); ?></button>
									<?php else : ?>
										<button type="button" class="ecv2-btn ecv2-btn-sm" data-ecpay-toggle="<?php echo esc_attr( $key ); ?>" data-on="1" data-swap="<?php echo ( '' !== self::active( $gw['role'] ) ) ? '1' : '0'; ?>"><?php esc_html_e( 'Turn on', 'wp-easycart' ); ?></button>
									<?php endif; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endforeach; ?>
				<div class="ecpay-more-empty" id="ecpay_more_empty" hidden><?php esc_html_e( 'Every gateway in this group is already active.', 'wp-easycart' ); ?></div>
			</div>
		</div>
		<?php
	}

	private static function render_card( $role, $meta, $key, $gate, $can_change = false ) {
		$gw = self::gateway( $key );
		$s  = self::status( $key );
		$state_chip = self::state_chip( $s, $gw );
		$urls = $gw['connect'] ? self::connect_urls( $key ) : array();
		?>
		<article class="ecpay-card is-<?php echo esc_attr( $s['state'] ); ?>" data-role="<?php echo esc_attr( $role ); ?>" data-gateway="<?php echo esc_attr( $key ); ?>">
			<div class="ecpay-card-role"><?php echo esc_html( $meta['label'] ); ?><?php if ( '' !== $meta['hint'] ) : ?><span><?php echo esc_html( $meta['hint'] ); ?></span><?php endif; ?></div>
			<div class="ecpay-card-head">
				<?php self::render_logo( $key, $gw ); ?>
				<div class="ecpay-card-title">
					<h4><?php echo esc_html( $gw['label'] ); ?></h4>
					<?php if ( '' !== $gw['desc'] ) : ?><span><?php echo esc_html( $gw['desc'] ); ?></span><?php endif; ?>
				</div>
			</div>
			<div class="ecpay-chips">
				<span class="ecv2-chip <?php echo esc_attr( $state_chip['class'] ); ?>"><?php echo esc_html( $state_chip['label'] ); ?></span>
				<?php if ( $s['enabled'] && ! $s['locked'] && 'manual' !== $role ) : ?>
					<span class="ecv2-chip <?php echo $s['test'] ? 'ecv2-chip-amber' : 'ecv2-chip-brand'; ?>"><?php echo $s['test'] ? esc_html__( 'Test mode', 'wp-easycart' ) : esc_html__( 'Live', 'wp-easycart' ); ?></span>
				<?php endif; ?>
			</div>
			<?php if ( $s['locked'] ) : ?>
				<p class="ecpay-card-note"><?php /* translators: %s: plan name, Pro or Premium ( Pro/Premium when no license is known ). */ echo esc_html( sprintf( __( 'This gateway is still selected but needs an active %s license to process payments.', 'wp-easycart' ), self::plan_name() ) ); ?></p>
			<?php elseif ( ! empty( $s['facts'] ) ) : ?>
				<dl class="ecpay-facts">
					<?php foreach ( $s['facts'] as $label => $value ) : ?>
						<div><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( $value ); ?></dd></div>
					<?php endforeach; ?>
				</dl>
			<?php endif; ?>
			<div class="ecpay-card-actions">
				<?php if ( $s['locked'] ) : ?>
					<button type="button" class="ecst-pro-btn" onclick="ecst.upsell( '<?php echo esc_js( $key ); ?>' ); return false;">🔒 <?php echo esc_html( self::pro_badge() ); ?></button>
					<span class="ecst-grow"></span>
					<?php if ( $can_change ) : ?>
						<?php self::render_change_button( $role ); ?>
					<?php endif; ?>
					<button type="button" class="ecv2-btn ecv2-btn-sm" data-ecpay-toggle="<?php echo esc_attr( $key ); ?>" data-on="0"><?php esc_html_e( 'Turn off', 'wp-easycart' ); ?></button>
				<?php else : ?>
					<?php if ( 'manual' === $role ) : ?>
						<button type="button" class="ecv2-btn ecv2-btn-sm<?php echo $s['enabled'] ? '' : ' ecv2-btn-primary'; ?>" data-ecpay-toggle="manual" data-on="<?php echo $s['enabled'] ? '0' : '1'; ?>"><?php echo $s['enabled'] ? esc_html__( 'Turn off', 'wp-easycart' ) : esc_html__( 'Turn on', 'wp-easycart' ); ?></button>
						<button type="button" class="ecv2-btn ecv2-btn-sm" data-ecpay-manual="1"><?php esc_html_e( 'Edit instructions', 'wp-easycart' ); ?></button>
					<?php else : ?>
						<?php if ( $gw['connect'] && ! $s['connected'] ) : ?>
							<?php if ( ! $s['live_ready'] ) : ?>
								<a class="ecv2-btn ecv2-btn-sm ecv2-btn-primary ecpay-connect" href="<?php echo $gate ? '#' : esc_url( $urls['live'] ); ?>" data-href="<?php echo esc_url( $urls['live'] ); ?>"<?php echo $gate ? ' aria-disabled="true"' : ''; ?>><?php echo $s['test'] ? esc_html__( 'Connect live account', 'wp-easycart' ) : esc_html__( 'Connect', 'wp-easycart' ); ?></a>
							<?php endif; ?>
							<?php if ( ! $s['test_ready'] ) : ?>
								<a class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost ecpay-connect" href="<?php echo $gate ? '#' : esc_url( $urls['test'] ); ?>" data-href="<?php echo esc_url( $urls['test'] ); ?>"<?php echo $gate ? ' aria-disabled="true"' : ''; ?>><?php esc_html_e( 'Try sandbox', 'wp-easycart' ); ?></a>
							<?php endif; ?>
						<?php elseif ( ! $s['ready'] && $gw['drawer'] ) : ?>
							<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" data-ecpay-open="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( empty( $s['started'] ) ? __( 'Connect', 'wp-easycart' ) : __( 'Finish setup', 'wp-easycart' ) ); ?></button>
						<?php endif; ?>
						<?php if ( $s['ready'] && $gw['drawer'] ) : ?>
							<button type="button" class="ecv2-btn ecv2-btn-sm" data-ecpay-open="<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'Manage', 'wp-easycart' ); ?></button>
						<?php endif; ?>
						<?php if ( $s['enabled'] && $s['test'] && $s['live_ready'] ) : ?>
							<button type="button" class="ecv2-btn ecv2-btn-sm" data-ecpay-mode="<?php echo esc_attr( $key ); ?>" data-mode="live"><?php esc_html_e( 'Switch to live', 'wp-easycart' ); ?></button>
						<?php elseif ( $s['enabled'] && ! $s['test'] && $s['test_ready'] && 'stripe' !== $key ) : ?>
							<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" data-ecpay-mode="<?php echo esc_attr( $key ); ?>" data-mode="test"><?php esc_html_e( 'Switch to sandbox', 'wp-easycart' ); ?></button>
						<?php endif; ?>
						<?php if ( $gw['connect'] && $s['ready'] ) : ?>
							<?php $env = ( $s['test'] && $s['test_ready'] ) || ! $s['live_ready'] ? 'test' : 'live'; ?>
							<a class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" href="<?php echo $gate ? '#' : esc_url( $urls[ $env ] ); ?>" data-href="<?php echo esc_url( $urls[ $env ] ); ?>"<?php echo $gate ? ' aria-disabled="true"' : ''; ?>><?php esc_html_e( 'Reconnect', 'wp-easycart' ); ?></a>
							<?php $disconnect = self::disconnect_url( $key, $env ); ?>
							<?php if ( '' !== $disconnect ) : ?>
								<a class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost ecv2-btn-danger-ghost" href="<?php echo esc_url( $disconnect ); ?>" data-ecpay-confirm="<?php echo esc_attr( sprintf( __( 'Disconnect %1$s ( %2$s )? The stored keys are removed from this site; revoke access from your %1$s account too.', 'wp-easycart' ), $gw['label'], ( 'test' === $env ) ? __( 'sandbox', 'wp-easycart' ) : __( 'live', 'wp-easycart' ) ) ); ?>"><?php esc_html_e( 'Disconnect', 'wp-easycart' ); ?></a>
							<?php endif; ?>
							<?php if ( 'square' === $key ) : ?>
								<a class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'ec_admin_form_action' => 'square-renew' ), admin_url( 'admin.php?page=wp-easycart-settings&subpage=payment' ) ), 'wp-easycart-payment-square-renew' ) ); ?>" data-ecpay-nav="1"><?php esc_html_e( 'Renew access', 'wp-easycart' ); ?></a>
							<?php endif; ?>
						<?php endif; ?>
						<span class="ecst-grow"></span>
						<?php if ( $can_change ) : ?>
							<?php self::render_change_button( $role ); ?>
						<?php endif; ?>
						<?php if ( $s['enabled'] ) : ?>
							<button type="button" class="ecv2-btn ecv2-btn-sm" data-ecpay-toggle="<?php echo esc_attr( $key ); ?>" data-on="0"><?php esc_html_e( 'Turn off', 'wp-easycart' ); ?></button>
						<?php elseif ( $s['ready'] ) : ?>
							<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" data-ecpay-toggle="<?php echo esc_attr( $key ); ?>" data-on="1"><?php esc_html_e( 'Turn on', 'wp-easycart' ); ?></button>
						<?php endif; ?>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</article>
		<?php
	}

	/** "Change gateway" on a filled live / third-party card: reveals that slot's partner-first chooser. */
	private static function render_change_button( $role ) {
		?>
		<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" data-ecpay-change="<?php echo esc_attr( $role ); ?>" aria-controls="<?php echo esc_attr( 'ecpay_chooser_' . $role ); ?>" aria-expanded="false"><?php esc_html_e( 'Change gateway', 'wp-easycart' ); ?></button>
		<?php
	}

	/**
	 * Partner-first chooser for one slot: the partner gateways side by side at equal weight, then a
	 * quieter "Other gateway" tile that opens the full "More gateways" list filtered to the slot.
	 * Open when the slot is empty; rendered hidden behind "Change gateway" when a gateway fills it.
	 * Presentation only: every action reuses the existing Connect URLs, drawer and toggle handlers.
	 *
	 * @since 6.0.0
	 */
	private static function render_chooser( $role, $gate ) {
		$partners = self::partners( $role );
		if ( ! $partners ) {
			return;
		}
		$current    = self::active( $role );
		$current_gw = '' !== $current ? self::gateway( $current ) : false;
		$changing   = (bool) $current_gw;

		/* The "Other" tile: every gateway for this slot that is not a partner. */
		$others     = array();
		$any_locked = false;
		foreach ( self::catalog() as $key => $gw ) {
			if ( $gw['role'] !== $role || isset( $partners[ $key ] ) ) {
				continue;
			}
			$others[ $key ] = $gw;
			if ( self::is_locked( $gw ) ) {
				$any_locked = true;
			}
		}
		$current_is_other = $changing && isset( $others[ $current ] );
		$names            = array();
		foreach ( $others as $key => $gw ) {
			if ( $key === $current || 'custom' === $key || 'custom_thirdparty' === $key ) {
				continue;
			}
			$names[] = $gw['label'];
			if ( count( $names ) >= 3 ) {
				break;
			}
		}
		$listed = count( $others ) - ( $current_is_other ? 1 : 0 );
		$rest   = $listed - count( $names );

		if ( 'live' === $role ) {
			$title = $changing ? __( 'Change how you take cards', 'wp-easycart' ) : __( 'Choose how you take cards', 'wp-easycart' );
			/* translators: %s: name of the gateway currently selected. */
			$hint  = $changing ? sprintf( __( '%s takes cards now. Picking another gateway replaces it for new orders.', 'wp-easycart' ), $current_gw['label'] ) : __( 'Shoppers cannot pay by card on your site until you connect a gateway. Cards are entered right on your checkout page.', 'wp-easycart' );
			$see   = __( 'See all card gateways', 'wp-easycart' );
		} else {
			$title = $changing ? __( 'Change your third-party checkout', 'wp-easycart' ) : __( 'Add a third-party checkout', 'wp-easycart' );
			/* translators: %s: name of the third-party checkout currently selected. */
			$hint  = $changing ? sprintf( __( '%s is your third-party checkout now. Picking another replaces it.', 'wp-easycart' ), $current_gw['label'] ) : __( 'Optional, alongside your card gateway: shoppers finish paying on the provider’s site, then come back to your store.', 'wp-easycart' );
			$see   = __( 'See all third-party checkouts', 'wp-easycart' );
		}
		$id = 'ecpay_chooser_' . $role;
		?>
		<div class="ecpay-chooser<?php echo $changing ? ' is-change' : ''; ?>" id="<?php echo esc_attr( $id ); ?>" data-role="<?php echo esc_attr( $role ); ?>" role="group" aria-labelledby="<?php echo esc_attr( $id . '_title' ); ?>"<?php echo $changing ? ' hidden' : ''; ?>>
			<div class="ecpay-chooser-head">
				<div class="ecpay-chooser-titles">
					<h4 id="<?php echo esc_attr( $id . '_title' ); ?>" tabindex="-1"><?php echo esc_html( $title ); ?><?php if ( ! $changing && 'third_party' === $role ) : ?> <span class="ecv2-chip ecv2-chip-gray"><?php esc_html_e( 'Optional', 'wp-easycart' ); ?></span><?php endif; ?></h4>
					<span><?php echo esc_html( $hint ); ?></span>
				</div>
				<?php if ( $changing ) : ?>
					<button type="button" class="ecv2-btn ecv2-btn-sm" data-ecpay-change-cancel="<?php echo esc_attr( $role ); ?>">
						<?php
						/* translators: %s: name of the gateway currently selected. */
						echo esc_html( sprintf( __( 'Keep %s', 'wp-easycart' ), $current_gw['label'] ) );
						?>
					</button>
				<?php endif; ?>
			</div>
			<div class="ecpay-chooser-grid is-n<?php echo (int) count( $partners ); ?>" role="list">
				<?php foreach ( $partners as $key => $benefit ) : ?>
					<?php self::render_partner( $key, $benefit, $current, $gate ); ?>
				<?php endforeach; ?>
				<?php if ( $listed > 0 || $current_is_other ) : ?>
					<div class="ecpay-partner is-other<?php echo $current_is_other ? ' is-current' : ''; ?>" role="listitem">
						<div class="ecpay-partner-mark is-other"><span class="dashicons dashicons-screenoptions" aria-hidden="true"></span><span class="ecpay-partner-word"><?php esc_html_e( 'Other gateway', 'wp-easycart' ); ?></span></div>
						<p class="ecpay-partner-benefit">
							<?php
							if ( $names && $rest > 0 ) {
								/* translators: 1: comma-separated gateway names, 2: number of further gateways. */
								echo esc_html( sprintf( _n( '%1$s and %2$d more.', '%1$s and %2$d more.', $rest, 'wp-easycart' ), implode( ', ', $names ), $rest ) );
							} elseif ( $names ) {
								echo esc_html( implode( ', ', $names ) . '.' );
							} else {
								esc_html_e( 'Every other gateway EasyCart supports.', 'wp-easycart' );
							}
							if ( $any_locked ) {
								/* translators: %s: plan name, Pro or Premium ( Pro/Premium when no license is known ). */
								echo ' ' . esc_html( sprintf( __( 'Most need a %s license.', 'wp-easycart' ), self::plan_name() ) );
							}
							?>
						</p>
						<?php if ( $current_is_other ) : ?>
							<div class="ecpay-partner-chips">
								<span class="ecv2-chip ecv2-chip-brand">
									<?php
									/* translators: %s: name of the gateway currently selected. */
									echo esc_html( sprintf( __( 'Current: %s', 'wp-easycart' ), $current_gw['label'] ) );
									?>
								</span>
							</div>
						<?php endif; ?>
						<?php if ( $listed > 0 ) : ?>
							<div class="ecpay-partner-act"><button type="button" class="ecst-link" data-ecpay-choose="<?php echo esc_attr( $role ); ?>"><?php echo esc_html( $see ); ?> &rarr;</button></div>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/** One partner tile in the chooser. Same gates and handlers as the card and the "More gateways" row. */
	private static function render_partner( $key, $benefit, $current, $gate ) {
		$gw = self::gateway( $key );
		$s  = self::status( $key );
		if ( ! $gw || ! $s ) {
			return;
		}
		$is_current = ( $key === $current );
		$urls       = $gw['connect'] ? self::connect_urls( $key ) : array();
		$swap       = ( '' !== $current ) ? '1' : '0';
		?>
		<div class="ecpay-partner<?php echo $is_current ? ' is-current' : ''; ?><?php echo $s['locked'] ? ' is-locked' : ''; ?>" role="listitem" data-gateway="<?php echo esc_attr( $key ); ?>" data-label="<?php echo esc_attr( $gw['label'] ); ?>">
			<div class="ecpay-partner-mark"><?php self::render_partner_mark( $key, $gw ); ?><?php if ( $s['locked'] ) : ?> <span class="ecst-pro-tag"><?php echo esc_html( self::pro_badge() ); ?></span><?php endif; ?></div>
			<?php if ( '' !== $benefit ) : ?><p class="ecpay-partner-benefit"><?php echo esc_html( $benefit ); ?></p><?php endif; ?>
			<?php if ( $is_current ) : ?>
				<div class="ecpay-partner-chips"><span class="ecv2-chip ecv2-chip-brand"><?php esc_html_e( 'Current', 'wp-easycart' ); ?></span></div>
			<?php elseif ( ! $s['locked'] && $s['ready'] ) : ?>
				<div class="ecpay-partner-chips">
					<span class="ecv2-chip ecv2-chip-gray"><?php esc_html_e( 'Account connected', 'wp-easycart' ); ?></span>
					<?php if ( ! $s['live_ready'] && $s['test_ready'] ) : ?><span class="ecv2-chip ecv2-chip-amber"><?php esc_html_e( 'Sandbox only', 'wp-easycart' ); ?></span><?php endif; ?>
				</div>
			<?php endif; ?>
			<div class="ecpay-partner-act">
				<?php if ( $s['locked'] ) : ?>
					<button type="button" class="ecst-pro-btn" onclick="ecst.upsell( '<?php echo esc_js( $key ); ?>' ); return false;">🔒 <?php echo esc_html( self::pro_badge() ); ?></button>
				<?php elseif ( $is_current ) : ?>
					<?php if ( $gw['drawer'] ) : ?><button type="button" class="ecv2-btn ecv2-btn-sm" data-ecpay-open="<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'Manage', 'wp-easycart' ); ?></button><?php endif; ?>
				<?php elseif ( $s['ready'] ) : ?>
					<button type="button" class="ecv2-btn ecv2-btn-primary" data-ecpay-toggle="<?php echo esc_attr( $key ); ?>" data-on="1" data-swap="<?php echo esc_attr( $swap ); ?>">
						<?php
						/* translators: %s: gateway name. */
						echo esc_html( sprintf( __( 'Use %s', 'wp-easycart' ), $gw['label'] ) );
						?>
					</button>
					<?php if ( $gw['connect'] && ! $s['live_ready'] && ! empty( $urls['live'] ) ) : ?>
						<a class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost ecpay-connect" href="<?php echo $gate ? '#' : esc_url( $urls['live'] ); ?>" data-href="<?php echo esc_url( $urls['live'] ); ?>"<?php echo $gate ? ' aria-disabled="true"' : ''; ?>><?php esc_html_e( 'Connect live account', 'wp-easycart' ); ?></a>
					<?php elseif ( $gw['drawer'] ) : ?>
						<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" data-ecpay-open="<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'Settings', 'wp-easycart' ); ?></button>
					<?php endif; ?>
				<?php elseif ( $gw['connect'] && ! empty( $urls['live'] ) ) : ?>
					<a class="ecv2-btn ecv2-btn-primary ecpay-connect" href="<?php echo $gate ? '#' : esc_url( $urls['live'] ); ?>" data-href="<?php echo esc_url( $urls['live'] ); ?>"<?php echo $gate ? ' aria-disabled="true"' : ''; ?>>
						<?php
						/* translators: %s: gateway name. */
						echo esc_html( sprintf( __( 'Connect %s', 'wp-easycart' ), $gw['label'] ) );
						?>
					</a>
					<?php if ( ! empty( $urls['test'] ) ) : ?>
						<a class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost ecpay-connect" href="<?php echo $gate ? '#' : esc_url( $urls['test'] ); ?>" data-href="<?php echo esc_url( $urls['test'] ); ?>"<?php echo $gate ? ' aria-disabled="true"' : ''; ?>><?php esc_html_e( 'Try sandbox', 'wp-easycart' ); ?></a>
					<?php endif; ?>
				<?php elseif ( $gw['drawer'] ) : ?>
					<button type="button" class="ecv2-btn ecv2-btn-primary" data-ecpay-open="<?php echo esc_attr( $key ); ?>">
						<?php
						/* translators: %s: gateway name. */
						echo esc_html( sprintf( __( 'Set up %s', 'wp-easycart' ), $gw['label'] ) );
						?>
					</button>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Partner mark: the logo files the plugin already ships ( admin/images, the base theme's PayPal image ),
	 * falling back to the neutral lettermark plus the gateway name.
	 */
	private static function render_partner_mark( $key, $gw ) {
		/* path relative to the plugin root, CSS modifier, print the name beside the image. */
		$marks = array(
			'stripe_connect' => array( 'admin/images/Stripe Logo (blue).png', 'is-wordmark is-stripe', false ),
			'square'         => array( 'admin/images/square-logo.png', 'is-icon', true ),
			'paypal'         => array( 'design/theme/base-responsive-v3/images/paypal.jpg', 'is-wordmark is-paypal', false ),
		);
		if ( isset( $marks[ $key ] ) && defined( 'EC_PLUGIN_DIRECTORY' ) && file_exists( EC_PLUGIN_DIRECTORY . '/' . $marks[ $key ][0] ) ) {
			$mark = $marks[ $key ];
			echo '<img class="ecpay-partner-img ' . esc_attr( $mark[1] ) . '" src="' . esc_url( plugins_url( $mark[0], EC_PLUGIN_DIRECTORY . '/wpeasycart.php' ) ) . '" alt="' . ( $mark[2] ? '' : esc_attr( $gw['label'] ) ) . '" />';
			if ( $mark[2] ) {
				echo '<span class="ecpay-partner-word">' . esc_html( $gw['label'] ) . '</span>';
			}
			return;
		}
		self::render_logo( $key, $gw );
		echo '<span class="ecpay-partner-word">' . esc_html( $gw['label'] ) . '</span>';
	}

	/**
	 * The Wallet slot when no wallet is switched on: the first wallet the catalog knows about, shown as an
	 * upsell when it is locked and as a "Set up" card when it is available. Keeps Amazon Pay on the page for
	 * every edition instead of only inside the collapsed "More gateways" list.
	 *
	 * @since 6.0.1
	 * @param array $meta Role meta from roles().
	 * @param bool  $gate True while the EasyCart Connect terms still have to be accepted.
	 * @return void
	 */
	private static function render_wallet_card( $meta, $gate ) {
		$wallet_key = '';
		$wallet     = array();
		foreach ( self::catalog() as $key => $gw ) {
			if ( 'wallet' === $gw['role'] ) {
				$wallet_key = $key;
				$wallet     = $gw;
				break;
			}
		}
		if ( '' === $wallet_key ) {
			return;
		}
		$status = self::status( $wallet_key );
		?>
		<article class="ecpay-card is-empty<?php echo $status['locked'] ? ' is-locked' : ''; ?>" data-role="wallet" data-gateway="<?php echo esc_attr( $wallet_key ); ?>">
			<div class="ecpay-card-role"><?php echo esc_html( $meta['label'] ); ?><?php if ( '' !== $meta['hint'] ) : ?><span><?php echo esc_html( $meta['hint'] ); ?></span><?php endif; ?></div>
			<div class="ecpay-card-head">
				<?php self::render_logo( $wallet_key, $wallet ); ?>
				<div class="ecpay-card-title">
					<h4><?php echo esc_html( $wallet['label'] ); ?><?php if ( $status['locked'] ) : ?> <span class="ecst-pro-tag"><?php echo esc_html( self::pro_badge() ); ?></span><?php endif; ?></h4>
					<?php if ( '' !== $wallet['desc'] ) : ?><span><?php echo esc_html( $wallet['desc'] ); ?></span><?php endif; ?>
				</div>
			</div>
			<div class="ecpay-chips"><span class="ecv2-chip ecv2-chip-gray"><?php esc_html_e( 'Off', 'wp-easycart' ); ?></span></div>
			<div class="ecpay-card-actions">
				<?php if ( $status['locked'] ) : ?>
					<button type="button" class="ecst-pro-btn" onclick="ecst.upsell( '<?php echo esc_js( $wallet_key ); ?>' ); return false;">&#128274; <?php echo esc_html( self::pro_badge() ); ?></button>
				<?php elseif ( $status['ready'] ) : ?>
					<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" data-ecpay-toggle="<?php echo esc_attr( $wallet_key ); ?>" data-on="1" data-swap="0"><?php esc_html_e( 'Turn on', 'wp-easycart' ); ?></button>
					<?php if ( $wallet['drawer'] ) : ?><button type="button" class="ecv2-btn ecv2-btn-sm" data-ecpay-open="<?php echo esc_attr( $wallet_key ); ?>"><?php esc_html_e( 'Settings', 'wp-easycart' ); ?></button><?php endif; ?>
				<?php elseif ( $wallet['drawer'] ) : ?>
					<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" data-ecpay-open="<?php echo esc_attr( $wallet_key ); ?>"<?php echo $gate ? ' aria-disabled="true"' : ''; ?>><?php esc_html_e( 'Set up', 'wp-easycart' ); ?></button>
				<?php endif; ?>
			</div>
		</article>
		<?php
	}

	private static function render_empty_card( $role, $meta ) {
		?>
		<article class="ecpay-card is-empty" data-role="<?php echo esc_attr( $role ); ?>">
			<div class="ecpay-card-role"><?php echo esc_html( $meta['label'] ); ?><?php if ( '' !== $meta['hint'] ) : ?><span><?php echo esc_html( $meta['hint'] ); ?></span><?php endif; ?></div>
			<div class="ecpay-card-head">
				<span class="ecpay-logo is-empty" aria-hidden="true">?</span>
				<div class="ecpay-card-title">
					<h4><?php echo esc_html( $meta['empty'] ); ?></h4>
					<?php if ( '' !== $meta['empty_hint'] ) : ?><span><?php echo esc_html( $meta['empty_hint'] ); ?></span><?php endif; ?>
				</div>
			</div>
			<div class="ecpay-chips"><span class="ecv2-chip ecv2-chip-gray"><?php esc_html_e( 'Nothing selected', 'wp-easycart' ); ?></span></div>
			<div class="ecpay-card-actions">
				<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" data-ecpay-choose="<?php echo esc_attr( $role ); ?>"><?php esc_html_e( 'Choose a gateway', 'wp-easycart' ); ?></button>
			</div>
		</article>
		<?php
	}

	private static function render_logo( $key, $gw ) {
		$brands = array(
			'stripe_connect'      => array( '#635bff', 'S' ),
			'stripe'              => array( '#635bff', 'S' ),
			'square'              => array( '#000000', '▢' ),
			'paypal'              => array( '#003087', 'PP' ),
			'paypal_pro'          => array( '#003087', 'PP' ),
			'paypal_payments_pro' => array( '#003087', 'PP' ),
			'manual'              => array( '#374151', '$' ),
			'amazonpay'           => array( '#ff9900', 'a' ),
			'authorize'           => array( '#0b3b5c', 'A' ),
			'braintree'           => array( '#111827', 'B' ),
		);
		$brand = isset( $brands[ $key ] ) ? $brands[ $key ] : array( '', strtoupper( substr( $gw['label'], 0, 1 ) ) );
		echo '<span class="ecpay-logo"' . ( '' !== $brand[0] ? ' style="background:' . esc_attr( $brand[0] ) . ';color:#fff"' : '' ) . ' aria-hidden="true">' . esc_html( $brand[1] ) . '</span>';
	}

	private static function state_chip( $s, $gw ) {
		switch ( $s['state'] ) {
			case 'locked':
				return array( 'class' => 'ecv2-chip-red', /* translators: %s: plan name, Pro or Premium ( Pro/Premium when no license is known ). */ 'label' => sprintf( __( '%s license needed', 'wp-easycart' ), self::plan_name() ) );
			case 'connected':
				return array( 'class' => 'ecv2-chip-green', 'label' => ( 'manual' === $gw['role'] ) ? __( 'On', 'wp-easycart' ) : __( 'Connected', 'wp-easycart' ) );
			case 'incomplete':
				return array( 'class' => 'ecv2-chip-amber', 'label' => __( 'Selected, not set up', 'wp-easycart' ) );
			case 'off':
				return array( 'class' => 'ecv2-chip-gray', 'label' => __( 'Off', 'wp-easycart' ) );
			default:
				return array( 'class' => 'ecv2-chip-gray', 'label' => __( 'Not set up', 'wp-easycart' ) );
		}
	}

	/** Onboarding return codes that the legacy page did not turn into a message. */
	private static function render_flash() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only flash codes set by the onboarding redirects.
		$success = isset( $_GET['success'] ) ? sanitize_key( wp_unslash( $_GET['success'] ) ) : '';
		$error   = isset( $_GET['error'] ) ? sanitize_key( wp_unslash( $_GET['error'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$text = '';
		$kind = 'brand';
		if ( 'square-connected' === $success ) {
			$text = __( 'Square is connected and set as your live gateway.', 'wp-easycart' );
		} elseif ( 'connected' === $success ) {
			$text = __( 'Connected.', 'wp-easycart' );
		} elseif ( 'square-failed-to-connect' === $error ) {
			$text = __( 'Square did not finish connecting. Try again, and make sure you finish the authorisation on Square’s side.', 'wp-easycart' );
			$kind = 'amber';
		} elseif ( 'failed-to-connect' === $error ) {
			$text = __( 'The gateway did not finish connecting. Try again.', 'wp-easycart' );
			$kind = 'amber';
		}
		if ( '' === $text ) {
			return;
		}
		echo '<div class="ecpay-flash is-' . esc_attr( $kind ) . '">' . esc_html( $text ) . '</div>';
	}

	/** Free edition terms gate ( replaces the legacy full-page overlay ): Connect buttons stay disabled until accepted. */
	private static function render_terms() {
		?>
		<div class="ecpay-terms" id="ecpay_terms">
			<div class="ecpay-terms-head">
				<b><?php esc_html_e( 'EasyCart Connect terms', 'wp-easycart' ); ?></b>
				<span class="ecv2-chip ecv2-chip-amber"><?php esc_html_e( 'Required for PayPal, Stripe and Square', 'wp-easycart' ); ?></span>
			</div>
			<p><?php esc_html_e( 'The Free edition includes unlimited products, orders and accounts plus Bill later. PayPal, Stripe and Square are available through EasyCart Connect with a 2% EasyCart fee on each sale. Upgrading to Pro or Premium removes the fee and unlocks 30+ more gateways.', 'wp-easycart' ); ?></p>
			<label class="ecpay-terms-row">
				<input type="checkbox" id="ecpay_terms_agree" value="1" />
				<span><?php echo wp_kses( sprintf( __( 'I agree to the WP EasyCart %1$sterms and privacy policy%2$s.', 'wp-easycart' ), '<a href="https://www.wpeasycart.com/terms-and-conditions/" target="_blank" rel="noopener noreferrer">', '</a>' ), array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) ) ); ?></span>
				<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" id="ecpay_terms_accept" disabled><?php esc_html_e( 'Accept and continue', 'wp-easycart' ); ?></button>
			</label>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* State changes                                                       */
	/* ------------------------------------------------------------------ */

	/**
	 * Turn a gateway on ( select it for its slot ) or off. Fires the same actions
	 * the legacy save handlers fired so tracking keeps working.
	 *
	 * @return string|WP_Error message
	 */
	public static function set_enabled( $key, $on ) {
		$gw = self::gateway( $key );
		if ( ! $gw ) {
			return new WP_Error( 'ecpay_unknown', __( 'Unknown gateway.', 'wp-easycart' ) );
		}
		if ( self::is_locked( $gw ) && $on ) {
			return new WP_Error( 'ecpay_locked', self::locked_message( $gw ) );
		}
		$s = self::status( $key, true );
		if ( $on && ! $s['ready'] ) {
			return new WP_Error( 'ecpay_setup', sprintf( __( 'Set up %s first.', 'wp-easycart' ), $gw['label'] ) );
		}
		switch ( $gw['role'] ) {
			case 'live':
				if ( $on ) {
					update_option( 'ec_option_payment_process_method', $key );
					if ( 'stripe_connect' === $key ) {
						update_option( 'ec_option_stripe_connect_use_sandbox', $s['live_ready'] ? 0 : 1 );
					} elseif ( 'square' === $key ) {
						update_option( 'ec_option_square_is_sandbox', $s['live_ready'] ? 0 : 1 );
					}
				} elseif ( (string) get_option( 'ec_option_payment_process_method' ) === $key ) {
					update_option( 'ec_option_payment_process_method', '0' );
				}
				do_action( 'wpeasycart_live_gateway_updated', get_option( 'ec_option_payment_process_method' ) );
				break;
			case 'third_party':
				if ( $on ) {
					update_option( 'ec_option_payment_third_party', $key );
					if ( 'paypal' === $key && ( $s['live_ready'] || $s['test_ready'] ) ) {
						$has_express = '' !== (string) get_option( 'ec_option_paypal_production_merchant_id' ) || '' !== (string) get_option( 'ec_option_paypal_sandbox_merchant_id' );
						if ( $has_express ) {
							update_option( 'ec_option_paypal_enable_pay_now', 1 );
							update_option( 'ec_option_paypal_use_sandbox', ( '' !== (string) get_option( 'ec_option_paypal_production_merchant_id' ) ) ? 0 : 1 );
							update_option( 'ec_option_paypal_sandbox_access_token_expires', 0 );
							update_option( 'ec_option_paypal_production_access_token_expires', 0 );
						}
					}
				} elseif ( (string) get_option( 'ec_option_payment_third_party' ) === $key ) {
					update_option( 'ec_option_payment_third_party', '0' );
				}
				do_action( 'wpeasycart_third_party_payment_updated', get_option( 'ec_option_payment_third_party' ) );
				break;
			case 'manual':
				update_option( 'ec_option_use_direct_deposit', $on ? 1 : 0 );
				do_action( 'wpeasycart_manual_billing_updated', $on ? 1 : 0 );
				break;
			case 'wallet':
				if ( '' === $gw['enable_key'] ) {
					return new WP_Error( 'ecpay_wallet', __( 'This wallet cannot be switched from here.', 'wp-easycart' ) );
				}
				update_option( $gw['enable_key'], $on ? 1 : 0 );
				break;
		}
		do_action( 'wp_easycart_payment_gateway_toggled', $key, (bool) $on, $gw );
		self::$status = array();
		return $on ? sprintf( __( '%s is on.', 'wp-easycart' ), $gw['label'] ) : sprintf( __( '%s is off.', 'wp-easycart' ), $gw['label'] );
	}

	/**
	 * Switch a gateway between live and sandbox / test.
	 *
	 * @return string|WP_Error message
	 */
	public static function set_mode( $key, $test ) {
		$gw = self::gateway( $key );
		if ( ! $gw ) {
			return new WP_Error( 'ecpay_unknown', __( 'Unknown gateway.', 'wp-easycart' ) );
		}
		$s = self::status( $key, true );
		if ( $test && ! $s['test_ready'] ) {
			return new WP_Error( 'ecpay_no_sandbox', sprintf( __( 'Connect a %s sandbox account first.', 'wp-easycart' ), $gw['label'] ) );
		}
		if ( ! $test && ! $s['live_ready'] ) {
			return new WP_Error( 'ecpay_no_live', sprintf( __( 'Connect a live %s account first.', 'wp-easycart' ), $gw['label'] ) );
		}
		switch ( $key ) {
			case 'stripe_connect':
				update_option( 'ec_option_stripe_connect_use_sandbox', $test ? 1 : 0 );
				break;
			case 'square':
				update_option( 'ec_option_square_is_sandbox', $test ? 1 : 0 );
				break;
			case 'paypal':
				update_option( 'ec_option_paypal_use_sandbox', $test ? 1 : 0 );
				update_option( 'ec_option_paypal_sandbox_access_token_expires', 0 );
				update_option( 'ec_option_paypal_production_access_token_expires', 0 );
				break;
			case 'stripe':
				return new WP_Error( 'ecpay_keys', __( 'Stripe ( API keys ) follows the keys you enter: swap the test keys for live keys under Manage.', 'wp-easycart' ) );
			default:
				if ( empty( $gw['test'] ) ) {
					return new WP_Error( 'ecpay_no_mode', sprintf( __( '%s has no test mode setting.', 'wp-easycart' ), $gw['label'] ) );
				}
				update_option( $gw['test']['key'], $test ? $gw['test']['on'] : $gw['test']['off'] );
		}
		do_action( 'wp_easycart_payment_gateway_mode', $key, (bool) $test, $gw );
		self::$status = array();
		return $test ? sprintf( __( '%s is in test mode.', 'wp-easycart' ), $gw['label'] ) : sprintf( __( '%s is live.', 'wp-easycart' ), $gw['label'] );
	}

	/** Declared section action: every active gateway in test mode goes live where a live account exists. */
	public static function action_test_mode_off( $action = array(), $page = array() ) {
		$done  = array();
		$stuck = array();
		foreach ( self::test_mode_gateways() as $key ) {
			$gw = self::gateway( $key );
			$r  = self::set_mode( $key, false );
			if ( is_wp_error( $r ) ) {
				$stuck[] = $gw['label'] . ' — ' . $r->get_error_message();
			} else {
				$done[] = $gw['label'];
			}
		}
		if ( ! $done && ! $stuck ) {
			return new WP_Error( 'ecpay_none', __( 'Nothing is in test mode.', 'wp-easycart' ) );
		}
		if ( ! $stuck ) {
			return array( 'reload' => true );
		}
		$text = '';
		if ( $done ) {
			$text .= sprintf( __( 'Now live: %s.', 'wp-easycart' ), implode( ', ', $done ) ) . ' ';
		}
		$text .= sprintf( __( 'Still in test mode: %s', 'wp-easycart' ), implode( '; ', $stuck ) );
		return $text;
	}

	/* ------------------------------------------------------------------ */
	/* Gateway forms ( V2 drawer )                                         */
	/* ------------------------------------------------------------------ */

	/**
	 * The V2 drawer form for a gateway, or false when the gateway still embeds its legacy partial.
	 *
	 * Declared through apply_filters( 'wp_easycart_payment_gateway_form', null, $key, $gw ). PRO answers
	 * for the gateways it ships ( wp-easycart-pro/admin/template/settings/payment-forms.php ). Shape:
	 *
	 *   array(
	 *     'intro'    => '',                        // optional plain-text notice above the sections
	 *     'save'     => callable( $values, $gw ),  // stores validated values ( option => string ); true | WP_Error
	 *     'sections' => array(
	 *       'credentials' => array(
	 *         'title'  => __( 'Credentials' ), 'hint' => '',
	 *         'fields' => array(
	 *           'ec_option_example' => array(
	 *             'type'        => 'text',   // text | email | select | pills | toggle | textarea | note | copy
	 *             'label'       => '',
	 *             'desc'        => '',       // one sentence, or a list of paragraphs
	 *             'placeholder' => '',
	 *             'required'    => false,    // blocks the save while the row is visible and empty
	 *             'secret'      => false,    // masked, with a Show button ( text and textarea )
	 *             'options'     => array(),  // select / pills: stored value => label
	 *             'on'          => '1',      // toggle: value stored when on
	 *             'off'         => '0',      // toggle: value stored when off
	 *             'default'     => '',       // shown when the option was never stored
	 *             'show_if'     => array(),  // other field key => values that reveal this row ( client and server )
	 *             'width'       => '',       // '' ( full ) | 'short'
	 *             'mono'        => false,    // monospace input ( keys, PEM files )
	 *             'rows'        => 4,        // textarea
	 *             'maxlength'   => 0,
	 *             'pattern'     => '',       // PCRE a non-empty value must match
	 *             'pattern_msg' => '',
	 *             'validate'    => null,     // callable( $value, $values, $field ) → '' | error text
	 *             'html'        => '',       // note: text, limited HTML ( a, strong, em, br, code, ol, ul, li )
	 *             'kind'        => 'info',   // note: info | warn
	 *             'value'       => '',       // copy: read-only text offered with a Copy button
	 *           ),
	 *         ),
	 *       ),
	 *     ),
	 *   )
	 *
	 * Only declared input fields are accepted on save; values the form does not post are left untouched.
	 *
	 * @since 6.0.0
	 * @param array $gw Catalog entry.
	 * @return array|false
	 */
	public static function form( $gw ) {
		if ( ! is_array( $gw ) || empty( $gw['key'] ) ) {
			return false;
		}
		$form = apply_filters( 'wp_easycart_payment_gateway_form', null, $gw['key'], $gw );
		if ( ! is_array( $form ) || empty( $form['sections'] ) || ! is_array( $form['sections'] ) || empty( $form['save'] ) || ! is_callable( $form['save'] ) ) {
			return false;
		}
		$types = array( 'text', 'email', 'select', 'pills', 'toggle', 'textarea', 'note', 'copy' );
		$clean = array(
			'intro'    => isset( $form['intro'] ) ? (string) $form['intro'] : '',
			'save'     => $form['save'],
			'sections' => array(),
		);
		foreach ( $form['sections'] as $slug => $section ) {
			if ( ! is_array( $section ) || empty( $section['fields'] ) || ! is_array( $section['fields'] ) ) {
				continue;
			}
			$fields = array();
			foreach ( $section['fields'] as $key => $field ) {
				$key = (string) $key;
				if ( ! is_array( $field ) || ! preg_match( '/^[A-Za-z0-9_\-]{1,120}$/', $key ) ) {
					continue;
				}
				$field = wp_parse_args(
					$field,
					array(
						'type'        => 'text',
						'label'       => '',
						'desc'        => '',
						'placeholder' => '',
						'required'    => false,
						'secret'      => false,
						'options'     => array(),
						'on'          => '1',
						'off'         => '0',
						'default'     => '',
						'show_if'     => array(),
						'width'       => '',
						'mono'        => false,
						'rows'        => 4,
						'maxlength'   => 0,
						'pattern'     => '',
						'pattern_msg' => '',
						'validate'    => null,
						'html'        => '',
						'kind'        => 'info',
						'value'       => '',
					)
				);
				if ( ! in_array( $field['type'], $types, true ) ) {
					$field['type'] = 'text';
				}
				if ( 'password' === $field['type'] ) {
					$field['type']   = 'text';
					$field['secret'] = true;
				}
				$field['key']     = $key;
				$field['options'] = (array) $field['options'];
				$field['show_if'] = (array) $field['show_if'];
				$field['desc']    = array_values( array_filter( array_map( 'strval', (array) $field['desc'] ), 'strlen' ) );
				$field['on']      = (string) $field['on'];
				$field['off']     = (string) $field['off'];
				$fields[ $key ]   = $field;
			}
			if ( $fields ) {
				$clean['sections'][ sanitize_key( $slug ) ] = array(
					'title'  => isset( $section['title'] ) ? (string) $section['title'] : '',
					'hint'   => isset( $section['hint'] ) ? (string) $section['hint'] : '',
					'fields' => $fields,
				);
			}
		}
		return $clean['sections'] ? $clean : false;
	}

	/** The input fields of a form ( notes and copy rows left out ), keyed by option name. */
	public static function form_inputs( $form ) {
		$inputs = array();
		foreach ( $form['sections'] as $section ) {
			foreach ( $section['fields'] as $key => $field ) {
				if ( 'note' !== $field['type'] && 'copy' !== $field['type'] ) {
					$inputs[ $key ] = $field;
				}
			}
		}
		return $inputs;
	}

	/** Stored value of one form field, as a string ( the declared default when never stored ). */
	private static function form_value( $field ) {
		$value = get_option( $field['key'], null );
		if ( null === $value || false === $value ) {
			$value = $field['default'];
		}
		return is_scalar( $value ) ? (string) $value : '';
	}

	/** Whether a row is revealed by its show_if rules for the given values ( option => string ). */
	private static function form_visible( $field, $values ) {
		foreach ( $field['show_if'] as $parent => $allowed ) {
			$current = isset( $values[ $parent ] ) ? (string) $values[ $parent ] : '';
			if ( ! in_array( $current, array_map( 'strval', (array) $allowed ), true ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Validate posted values against the declaration. Rows hidden by show_if skip the required check.
	 *
	 * @return array option => error message ( empty when everything passed ).
	 */
	public static function validate_form( $form, $values ) {
		$inputs = self::form_inputs( $form );
		$merged = array();
		foreach ( $inputs as $key => $field ) {
			$merged[ $key ] = array_key_exists( $key, $values ) ? $values[ $key ] : self::form_value( $field );
		}
		$errors = array();
		foreach ( $values as $key => $value ) {
			if ( ! isset( $inputs[ $key ] ) ) {
				continue;
			}
			$field   = $inputs[ $key ];
			$trimmed = trim( $value );
			$visible = self::form_visible( $field, $merged );
			$label   = '' !== $field['label'] ? $field['label'] : $key;
			if ( 'toggle' === $field['type'] ) {
				if ( $value !== $field['on'] && $value !== $field['off'] ) {
					/* translators: %s: field label. */
					$errors[ $key ] = sprintf( __( '%s: choose on or off.', 'wp-easycart' ), $label );
				}
				continue;
			}
			if ( 'select' === $field['type'] || 'pills' === $field['type'] ) {
				if ( ! in_array( $value, array_map( 'strval', array_keys( $field['options'] ) ), true ) ) {
					$errors[ $key ] = __( 'Choose one of the listed options.', 'wp-easycart' );
				}
				continue;
			}
			if ( '' === $trimmed ) {
				if ( $field['required'] && $visible ) {
					/* translators: %s: field label. */
					$errors[ $key ] = sprintf( __( '%s is required.', 'wp-easycart' ), $label );
				}
				continue;
			}
			if ( 'email' === $field['type'] && ! is_email( $trimmed ) ) {
				$errors[ $key ] = __( 'Enter a valid email address.', 'wp-easycart' );
				continue;
			}
			if ( $field['maxlength'] > 0 && strlen( $trimmed ) > (int) $field['maxlength'] ) {
				/* translators: %d: maximum number of characters. */
				$errors[ $key ] = sprintf( __( 'Use %d characters or fewer.', 'wp-easycart' ), (int) $field['maxlength'] );
				continue;
			}
			if ( '' !== $field['pattern'] && ! preg_match( $field['pattern'], $trimmed ) ) {
				$errors[ $key ] = '' !== $field['pattern_msg'] ? $field['pattern_msg'] : __( 'This value is not in the expected format.', 'wp-easycart' );
				continue;
			}
			if ( is_callable( $field['validate'] ) ) {
				$message = call_user_func( $field['validate'], $value, $merged, $field );
				if ( is_string( $message ) && '' !== $message ) {
					$errors[ $key ] = $message;
				}
			}
		}
		return $errors;
	}

	/** The drawer body for a declared gateway form. */
	public static function render_form( $gw, $form ) {
		$current = array();
		foreach ( self::form_inputs( $form ) as $key => $field ) {
			$current[ $key ] = self::form_value( $field );
		}
		?>
		<form class="ecpay-gwform" id="ecpay_gwform" data-gateway="<?php echo esc_attr( $gw['key'] ); ?>" novalidate autocomplete="off">
			<?php /* The gateway's own save handler checks this nonce ( wp-easycart-settings-payment ), posted as wp_easycart_nonce. */ ?>
			<input type="hidden" id="ecpay_gw_nonce" value="<?php echo esc_attr( wp_create_nonce( 'wp-easycart-settings-payment' ) ); ?>" />
			<?php if ( '' !== $form['intro'] ) : ?>
				<p class="ecpay-gwintro"><?php echo esc_html( $form['intro'] ); ?></p>
			<?php endif; ?>
			<?php foreach ( $form['sections'] as $slug => $section ) : ?>
				<section class="ecpay-gwsec" data-sec="<?php echo esc_attr( $slug ); ?>">
					<?php if ( '' !== $section['title'] ) : ?>
						<header class="ecpay-gwsec-h">
							<h4><?php echo esc_html( $section['title'] ); ?></h4>
							<?php if ( '' !== $section['hint'] ) : ?><span><?php echo esc_html( $section['hint'] ); ?></span><?php endif; ?>
						</header>
					<?php endif; ?>
					<div class="ecpay-gwrows">
						<?php
						foreach ( $section['fields'] as $field ) {
							self::render_form_row( $field, $current );
						}
						?>
					</div>
				</section>
			<?php endforeach; ?>
		</form>
		<?php
	}

	/** One row of a declared gateway form. */
	private static function render_form_row( $field, $current ) {
		$key     = $field['key'];
		$id      = 'ecpay_f_' . $key;
		$hidden  = ! self::form_visible( $field, $current );
		$classes = array( 'ecpay-gwrow', 'is-' . $field['type'] );
		if ( 'short' === $field['width'] ) {
			$classes[] = 'is-short';
		}
		if ( $field['required'] ) {
			$classes[] = 'is-required';
		}
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" data-row="<?php echo esc_attr( $key ); ?>"<?php if ( $field['show_if'] ) : ?> data-show-if="<?php echo esc_attr( self::show_if_json( $field['show_if'] ) ); ?>"<?php endif; ?><?php echo $hidden ? ' hidden' : ''; ?>>
		<?php
		switch ( $field['type'] ) {
			case 'note':
				$allowed = array(
					'a'      => array( 'href' => array(), 'target' => array(), 'rel' => array() ),
					'strong' => array(),
					'em'     => array(),
					'br'     => array(),
					'code'   => array(),
					'ol'     => array(),
					'ul'     => array(),
					'li'     => array(),
				);
				?>
				<div class="ecpay-gwnote is-<?php echo esc_attr( 'warn' === $field['kind'] ? 'warn' : 'info' ); ?>">
					<?php if ( '' !== $field['label'] ) : ?><b><?php echo esc_html( $field['label'] ); ?></b><?php endif; ?>
					<?php echo wp_kses( $field['html'], $allowed ); ?>
				</div>
				<?php
				break;

			case 'toggle':
				$on = ( $current[ $key ] === $field['on'] );
				?>
				<div class="ecpay-gwtoggle">
					<div class="ecpay-gwtext">
						<label class="ecpay-gwlabel" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
						<?php self::render_form_desc( $field ); ?>
						<span class="ecpay-gwmsg" id="<?php echo esc_attr( $id . '_msg' ); ?>" role="alert" hidden></span>
					</div>
					<label class="ecst-toggle<?php echo $on ? ' is-on' : ''; ?>">
						<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" class="ecpay-in" data-key="<?php echo esc_attr( $key ); ?>" data-on="<?php echo esc_attr( $field['on'] ); ?>" data-off="<?php echo esc_attr( $field['off'] ); ?>" value="<?php echo esc_attr( $field['on'] ); ?>"<?php checked( $on ); ?> />
						<span class="ecst-toggle-track"><span class="ecst-toggle-knob"></span></span>
					</label>
				</div>
				<?php
				break;

			default:
				?>
				<label class="ecpay-gwlabel" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field['label'] ); ?><?php if ( $field['required'] ) : ?> <span class="ecpay-req" aria-hidden="true">*</span><?php endif; ?></label>
				<div class="ecpay-gwctl">
					<?php self::render_form_control( $field, $id, $current[ $key ] ?? '' ); ?>
				</div>
				<?php self::render_form_desc( $field ); ?>
				<span class="ecpay-gwmsg" id="<?php echo esc_attr( $id . '_msg' ); ?>" role="alert" hidden></span>
				<?php
		}
		?>
		</div>
		<?php
	}

	/** show_if rules as JSON for the drawer script: { "option": [ "value", … ] }. */
	private static function show_if_json( $rules ) {
		$out = array();
		foreach ( (array) $rules as $parent => $allowed ) {
			$out[ (string) $parent ] = array_values( array_map( 'strval', (array) $allowed ) );
		}
		return (string) wp_json_encode( $out );
	}

	private static function render_form_desc( $field ) {
		foreach ( $field['desc'] as $i => $line ) {
			echo '<span class="ecpay-gwdesc"' . ( 0 === $i ? ' id="' . esc_attr( 'ecpay_f_' . $field['key'] . '_desc' ) . '"' : '' ) . '>' . esc_html( $line ) . '</span>';
		}
	}

	/** The input for a text, email, select, pills, textarea or copy row. */
	private static function render_form_control( $field, $id, $value ) {
		$key  = $field['key'];
		$desc = ' aria-describedby="' . esc_attr( ( $field['desc'] ? $id . '_desc ' : '' ) . $id . '_msg' ) . '"';
		switch ( $field['type'] ) {
			case 'select':
				?>
				<select id="<?php echo esc_attr( $id ); ?>" class="ecv2-select ecpay-in" data-key="<?php echo esc_attr( $key ); ?>">
					<?php foreach ( $field['options'] as $opt_value => $opt_label ) : ?>
						<option value="<?php echo esc_attr( $opt_value ); ?>"<?php selected( $value, (string) $opt_value ); ?>><?php echo esc_html( $opt_label ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php
				break;

			case 'pills':
				$has = in_array( $value, array_map( 'strval', array_keys( $field['options'] ) ), true );
				$i   = 0;
				?>
				<div class="ecst-pills ecpay-pills" role="radiogroup" id="<?php echo esc_attr( $id ); ?>" aria-label="<?php echo esc_attr( $field['label'] ); ?>">
					<?php foreach ( $field['options'] as $opt_value => $opt_label ) : ?>
						<?php $on = $has ? ( $value === (string) $opt_value ) : ( 0 === $i ); ?>
						<label class="ecst-pill<?php echo $on ? ' is-on' : ''; ?>"><input type="radio" name="<?php echo esc_attr( $id ); ?>" class="ecpay-in" data-key="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $opt_value ); ?>"<?php checked( $on ); ?> /><?php echo esc_html( $opt_label ); ?></label>
						<?php $i++; ?>
					<?php endforeach; ?>
				</div>
				<?php
				break;

			case 'textarea':
				?>
				<span class="ecpay-secret<?php echo $field['secret'] ? ' is-masked' : ''; ?>">
					<textarea id="<?php echo esc_attr( $id ); ?>" class="ecv2-input ecpay-in<?php echo $field['mono'] ? ' is-mono' : ''; ?>" data-key="<?php echo esc_attr( $key ); ?>" rows="<?php echo (int) $field['rows']; ?>" placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>" spellcheck="false"<?php echo $desc; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attribute built with esc_attr() above. ?>><?php echo esc_textarea( $value ); ?></textarea>
					<?php if ( $field['secret'] ) : ?>
						<button type="button" class="ecv2-btn ecv2-btn-sm ecpay-reveal" aria-controls="<?php echo esc_attr( $id ); ?>" aria-pressed="false"><?php esc_html_e( 'Show', 'wp-easycart' ); ?></button>
					<?php endif; ?>
				</span>
				<?php
				break;

			case 'copy':
				?>
				<span class="ecpay-copy">
					<input type="text" id="<?php echo esc_attr( $id ); ?>" class="ecv2-input is-mono" value="<?php echo esc_attr( (string) $field['value'] ); ?>" readonly />
					<button type="button" class="ecv2-btn ecv2-btn-sm ecpay-copy-btn" aria-controls="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Copy', 'wp-easycart' ); ?></button>
				</span>
				<?php
				break;

			default: // text, email.
				$type = $field['secret'] ? 'password' : ( 'email' === $field['type'] ? 'email' : 'text' );
				?>
				<span class="ecpay-secret">
					<input type="<?php echo esc_attr( $type ); ?>" id="<?php echo esc_attr( $id ); ?>" class="ecv2-input ecpay-in<?php echo $field['mono'] ? ' is-mono' : ''; ?>" data-key="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>"<?php if ( $field['maxlength'] > 0 ) : ?> maxlength="<?php echo (int) $field['maxlength']; ?>"<?php endif; ?> autocomplete="<?php echo $field['secret'] ? 'new-password' : 'off'; ?>" spellcheck="false"<?php echo $desc; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attribute built with esc_attr() above. ?> />
					<?php if ( $field['secret'] ) : ?>
						<button type="button" class="ecv2-btn ecv2-btn-sm ecpay-reveal" aria-controls="<?php echo esc_attr( $id ); ?>" aria-pressed="false"><?php esc_html_e( 'Show', 'wp-easycart' ); ?></button>
					<?php endif; ?>
				</span>
				<?php
		}
	}

	/**
	 * What the drawer header and footer need to reflect a gateway's current state.
	 *
	 * @return array enabled, test, connected, state, state_label, state_class, mode_label, can_enable, swap ( turning it on replaces the slot's gateway )
	 */
	public static function drawer_state( $key ) {
		$gw = self::gateway( $key );
		$s  = $gw ? self::status( $key, true ) : false;
		if ( ! $gw || ! $s ) {
			return array( 'enabled' => false, 'test' => false, 'connected' => false, 'state' => '', 'state_label' => '', 'state_class' => '', 'mode_label' => '', 'can_enable' => false, 'swap' => false );
		}
		$chip = self::state_chip( $s, $gw );
		return array(
			'enabled'     => (bool) $s['enabled'],
			'test'        => (bool) $s['test'],
			'connected'   => (bool) $s['connected'],
			'state'       => $s['state'],
			'state_label' => $chip['label'],
			'state_class' => $chip['class'],
			'mode_label'  => ( $s['enabled'] && ! $s['locked'] && 'manual' !== $gw['role'] ) ? ( $s['test'] ? __( 'Test mode', 'wp-easycart' ) : __( 'Live', 'wp-easycart' ) ) : '',
			'can_enable'  => ! $s['enabled'] && ! $s['locked'] && in_array( $gw['role'], array( 'live', 'third_party', 'wallet' ), true ),
			'swap'        => ! $s['enabled'] && in_array( $gw['role'], array( 'live', 'third_party' ), true ) && '' !== self::active( $gw['role'] ),
		);
	}

	/** The two page sections ( cards and "More gateways" ) rendered fresh, for replacing them in place. */
	public static function sections_html() {
		self::$status = array();
		ob_start();
		self::render_active( array(), array() );
		$active = ob_get_clean();
		ob_start();
		self::render_more( array(), array() );
		$more = ob_get_clean();
		return array(
			'active' => $active,
			'more'   => $more,
		);
	}
	public static function form_file( $gw ) {
		if ( '' !== $gw['file'] ) {
			return $gw['file'];
		}
		return apply_filters( 'wp_easycart_admin_payment_file', EC_PLUGIN_DIRECTORY . '/admin/template/settings/payments/' . $gw['key'] . '.php', $gw['key'] );
	}

	/** GET/POST gateway → { html, title, role, docs } : the legacy partial wrapped for the drawer. */
	public static function ajax_gateway_form() {
		ecv2_settings_guard();
		$key = isset( $_REQUEST['gateway'] ) ? sanitize_key( wp_unslash( $_REQUEST['gateway'] ) ) : '';
		$gw  = self::gateway( $key );
		if ( ! $gw ) {
			wp_send_json_error( array( 'message' => __( 'Unknown gateway.', 'wp-easycart' ) ) );
		}
		if ( self::is_locked( $gw ) ) {
			wp_send_json_error( array( 'message' => self::locked_message( $gw ) ) );
		}
		if ( ! $gw['drawer'] ) {
			wp_send_json_error( array( 'message' => __( 'This gateway has no settings form.', 'wp-easycart' ) ) );
		}
		$roles = self::roles();
		$form  = self::form( $gw );
		if ( $form ) {
			ob_start();
			self::render_form( $gw, $form );
			wp_send_json_success( array(
				'mode'  => 'v2',
				'html'  => ob_get_clean(),
				'title' => $gw['label'],
				'role'  => isset( $roles[ $gw['role'] ] ) ? $roles[ $gw['role'] ]['label'] : '',
				'docs'  => self::docs_url( $gw ),
				'state' => self::drawer_state( $key ),
			) );
		}
		$file = self::form_file( $gw );
		if ( ! is_string( $file ) || '' === $file || ! file_exists( $file ) || filesize( $file ) < 1 ) {
			wp_send_json_error( array( 'message' => __( 'The settings form for this gateway is not installed.', 'wp-easycart' ) ) );
		}
		ob_start();
		echo '<div class="ecpay-legacy" data-gateway="' . esc_attr( $key ) . '" data-role="' . esc_attr( $gw['role'] ) . '">';
		/* The legacy save JavaScript reads this nonce ( wp_easycart_payment_settings_nonce ) from the DOM. */
		if ( function_exists( 'wp_easycart_admin_verification' ) ) {
			wp_easycart_admin_verification()->print_nonce_field( 'wp_easycart_payment_settings_nonce', 'wp-easycart-settings-payment' );
		}
		if ( function_exists( 'wp_easycart_admin' ) && isset( wp_easycart_admin()->preloader ) ) {
			/* Spinners the partials' save functions fade in / out. */
			wp_easycart_admin()->preloader->print_preloader( ( 'third_party' === $gw['role'] ) ? 'ec_admin_third_party_display_loader' : 'ec_admin_live_gateway_display_loader' );
		}
		/* Partials expect the classic page's instance context: PRO dwolla_thirdparty.php and realex_thirdparty.php
		 * read $this->cart_page and $this->permalink_divider, which fatal inside this static handler. */
		if ( function_exists( 'wp_easycart_admin_payments' ) && method_exists( 'wp_easycart_admin_payments', 'include_gateway_partial' ) ) {
			wp_easycart_admin_payments()->include_gateway_partial( $file );
		} else {
			include $file;
		}
		echo '</div>';
		$html  = ob_get_clean();
		$state = self::drawer_state( $key );
		wp_send_json_success( array(
			'mode'    => 'legacy',
			'html'    => $html,
			'title'   => $gw['label'],
			'role'    => isset( $roles[ $gw['role'] ] ) ? $roles[ $gw['role'] ]['label'] : '',
			'docs'    => self::docs_url( $gw ),
			'state'   => $state,
			/* The legacy save functions post the slot selection with the settings ( e.g. ec_option_payment_process_method: 'intuit' ). */
			'selects' => ! $gw['connect'] && ! $state['enabled'] && in_array( $gw['role'], array( 'live', 'third_party' ), true ),
		) );
	}

	/**
	 * POST gateway, values ( JSON object option => value ), enable ( 1 = turn the gateway on after saving ),
	 * wp_easycart_nonce ( the Payment settings nonce the form prints, for the gateway's own save handler ).
	 * Validates against the form declaration, runs its 'save' callable and answers with the fresh card state
	 * and the re-rendered page sections. Field errors come back as errors{ option => message }.
	 *
	 * @since 6.0.0
	 */
	public static function ajax_gateway_save() {
		ecv2_settings_guard();
		$key = isset( $_POST['gateway'] ) ? sanitize_key( wp_unslash( $_POST['gateway'] ) ) : '';
		$gw  = self::gateway( $key );
		if ( ! $gw ) {
			wp_send_json_error( array( 'message' => __( 'Unknown gateway.', 'wp-easycart' ) ) );
		}
		if ( self::is_locked( $gw ) ) {
			wp_send_json_error( array( 'message' => self::locked_message( $gw ) ) );
		}
		$form = $gw['drawer'] ? self::form( $gw ) : false;
		if ( ! $form ) {
			wp_send_json_error( array( 'message' => __( 'This gateway saves from the controls inside its own form.', 'wp-easycart' ) ) );
		}
		$raw = isset( $_POST['values'] ) ? json_decode( wp_unslash( $_POST['values'] ), true ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON payload; only keys declared by the gateway form are kept, validated in validate_form() and sanitized by the gateway's save handler.
		if ( ! is_array( $raw ) ) {
			wp_send_json_error( array( 'message' => __( 'Nothing to save.', 'wp-easycart' ) ) );
		}
		$inputs = self::form_inputs( $form );
		$values = array();
		foreach ( $inputs as $option => $field ) {
			if ( array_key_exists( $option, $raw ) && is_scalar( $raw[ $option ] ) ) {
				$values[ $option ] = (string) $raw[ $option ];
			}
		}
		if ( ! $values ) {
			wp_send_json_error( array( 'message' => __( 'Nothing to save.', 'wp-easycart' ) ) );
		}
		$errors = self::validate_form( $form, $values );
		if ( $errors ) {
			wp_send_json_error( array( 'message' => __( 'Check the highlighted fields.', 'wp-easycart' ), 'errors' => $errors ) );
		}
		$result = call_user_func( $form['save'], $values, $gw );
		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			wp_send_json_error( array(
				'message' => $result->get_error_message(),
				'errors'  => ( is_array( $data ) && isset( $data['errors'] ) && is_array( $data['errors'] ) ) ? $data['errors'] : array(),
			) );
		}
		self::$status = array();
		$status  = self::status( $key, true );
		$enable  = isset( $_POST['enable'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['enable'] ) );
		/* translators: %s: gateway name. */
		$message = sprintf( __( '%s settings saved.', 'wp-easycart' ), $gw['label'] );
		$warning = '';
		if ( $enable && ! $status['enabled'] ) {
			$turned = self::set_enabled( $key, true );
			if ( is_wp_error( $turned ) ) {
				$warning = $turned->get_error_message();
			} else {
				/* translators: %s: gateway name. */
				$message = sprintf( __( '%s settings saved and turned on.', 'wp-easycart' ), $gw['label'] );
			}
		} elseif ( $status['enabled'] ) {
			/* The legacy save handlers re-stored the slot selection on every save, which fired these tracking actions. */
			if ( 'live' === $gw['role'] ) {
				do_action( 'wpeasycart_live_gateway_updated', get_option( 'ec_option_payment_process_method' ) );
			} elseif ( 'third_party' === $gw['role'] ) {
				do_action( 'wpeasycart_third_party_payment_updated', get_option( 'ec_option_payment_third_party' ) );
			}
		}
		do_action( 'wp_easycart_payment_gateway_saved', $key, $values, $gw );
		self::$status = array();
		wp_send_json_success( array(
			'message'  => $message,
			'warning'  => $warning,
			'state'    => self::drawer_state( $key ),
			'sections' => self::sections_html(),
		) );
	}

	/** POST gateway ( optional ) → { state, sections } : refreshes the cards after a save made by a legacy form. */
	public static function ajax_state() {
		ecv2_settings_guard();
		$key = isset( $_POST['gateway'] ) ? sanitize_key( wp_unslash( $_POST['gateway'] ) ) : '';
		self::$status = array();
		wp_send_json_success( array(
			'state'    => ( '' !== $key && self::gateway( $key ) ) ? self::drawer_state( $key ) : null,
			'sections' => self::sections_html(),
		) );
	}

	/** GET → { html, title, docs } : the small V2 form for the Bill later wording. */
	public static function ajax_manual_form() {
		ecv2_settings_guard();
		$gw = self::gateway( 'manual' );
		ob_start();
		?>
		<div class="ecpay-manual-form" id="ecpay_manual_form">
			<div class="ecdv2-field ecdv2-field-full">
				<label class="ecdv2-label" for="ecpay_manual_title"><?php esc_html_e( 'Bill later option name', 'wp-easycart' ); ?></label>
				<input type="text" class="ecv2-input" id="ecpay_manual_title" value="<?php echo esc_attr( self::manual_title() ); ?>" placeholder="<?php esc_attr_e( 'Bill me later', 'wp-easycart' ); ?>" autocomplete="off" />
				<span class="ecdv2-field-desc"><?php esc_html_e( 'What the pay-later choice is called on the checkout page and the receipt, in the store language.', 'wp-easycart' ); ?></span>
			</div>
			<div class="ecdv2-field ecdv2-field-full">
				<label class="ecdv2-label" for="ecpay_manual_message"><?php esc_html_e( 'Instructions for the shopper', 'wp-easycart' ); ?></label>
				<textarea class="ecv2-input" id="ecpay_manual_message" rows="7" placeholder="<?php esc_attr_e( 'Please transfer the order total to …', 'wp-easycart' ); ?>"><?php echo esc_textarea( (string) get_option( 'ec_option_direct_deposit_message' ) ); ?></textarea>
				<span class="ecdv2-field-desc"><?php esc_html_e( 'Shown at checkout and on the receipt when they choose to pay later: bank details, where to send a cheque, pickup notes.', 'wp-easycart' ); ?></span>
			</div>
		</div>
		<?php
		$html = ob_get_clean();
		wp_send_json_success( array(
			'html'  => $html,
			'title' => __( 'Bill later wording', 'wp-easycart' ),
			'role'  => __( 'Bill later', 'wp-easycart' ),
			'docs'  => $gw ? self::docs_url( $gw ) : '',
		) );
	}

	/** POST title, message → saves the Bill later wording. */
	public static function ajax_manual_save() {
		ecv2_settings_guard();
		$title   = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		$result  = self::save_manual( $title, $message );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( array( 'message' => $result, 'reload' => true ) );
	}

	/** POST gateway, on=1|0 ( or mode=live|test ) → switches the gateway; page reloads on success. */
	public static function ajax_toggle() {
		ecv2_settings_guard();
		$key  = isset( $_POST['gateway'] ) ? sanitize_key( wp_unslash( $_POST['gateway'] ) ) : '';
		$on   = isset( $_POST['on'] ) ? ( '1' === sanitize_text_field( wp_unslash( $_POST['on'] ) ) ) : false;
		$mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : '';
		if ( ! self::gateway( $key ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown gateway.', 'wp-easycart' ) ) );
		}
		if ( 'live' === $mode || 'test' === $mode ) {
			$result = self::set_mode( $key, 'test' === $mode );
		} else {
			$result = self::set_enabled( $key, $on );
		}
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( array( 'message' => $result, 'reload' => true ) );
	}

	/**
	 * Filter: wp_easycart_settings_search_items. Every gateway is searchable from any settings page
	 * ( "stripe", "paypal", "apple pay" … ) and opens the Payment page at that gateway.
	 *
	 * @since 6.0.0
	 */
	public static function search_items( $items ) {
		$roles  = self::roles();
		$active = array( self::active( 'live' ), self::active( 'third_party' ) );
		$base   = wp_easycart_admin_settings_registry::page_url( 'payment' );
		foreach ( self::catalog() as $key => $gw ) {
			$role   = isset( $roles[ $gw['role'] ] ) ? $roles[ $gw['role'] ]['label'] : '';
			$locked = self::is_locked( $gw );
			if ( in_array( $key, $active, true ) || ( 'wallet' === $gw['role'] && '' !== $gw['enable_key'] && get_option( $gw['enable_key'] ) ) ) {
				$state = __( 'Active', 'wp-easycart' );
			} elseif ( $locked ) {
				$state = self::pro_badge();
			} else {
				$state = __( 'Available', 'wp-easycart' );
			}
			$items[] = array(
				'label'         => $gw['label'],
				'desc'          => '' !== (string) $gw['desc'] ? $gw['desc'] : $role,
				'keywords'      => array_merge( (array) $gw['keywords'], array( $key, $role, 'payment', 'gateway' ) ),
				'url'           => add_query_arg( 'gateway', $key, $base ),
				'page'          => 'payment',
				'page_title'    => __( 'Payment', 'wp-easycart' ),
				'section_title' => $role,
				'value_text'    => $state,
				'locked'        => $locked,
			);
		}
		return $items;
	}
}

add_filter( 'wp_easycart_settings_search_items', array( 'wp_easycart_admin_payment_v2', 'search_items' ) );

add_action( 'wp_ajax_ecv2_payment_gateway_form', array( 'wp_easycart_admin_payment_v2', 'ajax_gateway_form' ) );
add_action( 'wp_ajax_ecv2_payment_gateway_save', array( 'wp_easycart_admin_payment_v2', 'ajax_gateway_save' ) );
add_action( 'wp_ajax_ecv2_payment_state', array( 'wp_easycart_admin_payment_v2', 'ajax_state' ) );
add_action( 'wp_ajax_ecv2_payment_manual_form', array( 'wp_easycart_admin_payment_v2', 'ajax_manual_form' ) );
add_action( 'wp_ajax_ecv2_payment_manual_save', array( 'wp_easycart_admin_payment_v2', 'ajax_manual_save' ) );
add_action( 'wp_ajax_ecv2_payment_toggle', array( 'wp_easycart_admin_payment_v2', 'ajax_toggle' ) );

endif;
