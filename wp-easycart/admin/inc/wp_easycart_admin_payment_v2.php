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
	private static $pro_active = null;

	/* ------------------------------------------------------------------ */
	/* Edition                                                             */
	/* ------------------------------------------------------------------ */

	/** PRO installed, active and licensed ( the gate ). Every free-edition element hides when true. */
	public static function pro_active() {
		if ( null === self::$pro_active ) {
			self::$pro_active = class_exists( 'wp_easycart_admin_pro_gate' ) && wp_easycart_admin_pro_gate::is_enabled();
		}
		return (bool) self::$pro_active;
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

	/**
	 * Refusal sentence for a gateway a licensed but older WP EasyCart PRO cannot run.
	 *
	 * @since 6.0.3
	 * @param array $gw Catalog entry.
	 * @return string
	 */
	private static function update_message( $gw ) {
		/* translators: 1: gateway name, 2: version number, e.g. 6.0.2. */
		return sprintf( __( 'Update WP EasyCart PRO to %2$s or newer to use %1$s.', 'wp-easycart' ), $gw['label'], $gw['min_version'] );
	}

	/** Free edition: EasyCart Connect terms must be accepted before PayPal / Stripe / Square. */
	public static function terms_gate() {
		return ! self::pro_active() && ! get_option( 'ec_option_wpeasycart_terms_accepted' );
	}

	/** Free edition: the 2% application-fee note applies ( 6.0.3: only while the fee is charged, as the setup wizard's badge reads it ). */
	public static function show_fee_note() {
		return ! self::pro_active() && 0 < (float) apply_filters( 'wp_easycart_stripe_connect_fee_rate', 2 );
	}

	/* ------------------------------------------------------------------ */
	/* Catalog                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * The ways to pay ( slots ), in line order, named in the words shoppers would use.
	 *
	 * @since 6.0.3 Card payments, Checkout buttons, Wallets, Pay later ( were Live gateway, Third-party checkout, Bill later, Wallet ).
	 */
	public static function roles() {
		return array(
			'live'        => array( 'label' => __( 'Card payments', 'wp-easycart' ), 'hint' => __( 'Cards entered on your checkout page', 'wp-easycart' ) ),
			'third_party' => array( 'label' => __( 'Checkout buttons', 'wp-easycart' ), 'hint' => __( 'Shoppers finish paying on the provider’s site', 'wp-easycart' ) ),
			'wallet'      => array( 'label' => __( 'Wallets', 'wp-easycart' ), 'hint' => __( 'Pay with a stored account alongside the gateways above', 'wp-easycart' ) ),
			'manual'      => array( 'label' => __( 'Pay later', 'wp-easycart' ), 'hint' => __( 'Order now, pay you offline', 'wp-easycart' ) ),
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
	 *   test ( option name, or array( key, on, off ) ), facts ( option => label, shown on the card ),
	 *   note ( 6.0.3: one short line in the gateway list, searched too ), min_version ( 6.0.3: the WP EasyCart PRO version that
	 *   runs it; an older licensed PRO shows Update instead of the gateway ).
	 */
	public static function catalog() {
		if ( null !== self::$catalog ) {
			return self::$catalog;
		}
		$free = array(
			'stripe_connect' => array( 'label' => 'Stripe', 'role' => 'live', 'connect' => true, 'docs' => 'stripe', 'desc' => __( 'Cards, Apple Pay, Google Pay, Link and local payment methods, entered on your checkout page.', 'wp-easycart' ), 'keywords' => array( 'stripe connect', 'apple pay', 'google pay', 'klarna', 'affirm' ) ),
			'square'         => array( 'label' => 'Square', 'role' => 'live', 'connect' => true, 'docs' => 'square', 'desc' => __( 'Cards on your site, with your Square items brought into your store in a few clicks.', 'wp-easycart' ), 'keywords' => array( 'square', 'pos', 'sync' ) ),
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
			'paytrace'            => 'PayTrace',
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
		/* 6.0.3: one short line per gateway in the list ( what it is, or where it works ); searched too, so "Canada" finds Moneris. */
		$notes = array(
			'stripe_connect'            => __( 'Cards, Apple Pay, Google Pay and Link', 'wp-easycart' ),
			'square'                    => __( 'Cards, plus your Square items brought into your store', 'wp-easycart' ),
			'paypal'                    => __( 'PayPal, Venmo and Pay Later buttons', 'wp-easycart' ),
			'amazonpay'                 => __( 'Shoppers pay with their Amazon account', 'wp-easycart' ),
			'authorize'                 => __( 'United States, Canada, UK, Europe, Australia', 'wp-easycart' ),
			'beanstream'                => __( 'Canada', 'wp-easycart' ),
			'braintree'                 => __( 'Cards and PayPal, many countries', 'wp-easycart' ),
			'cardpointe'                => __( 'United States', 'wp-easycart' ),
			'chronopay'                 => __( 'Older card gateway', 'wp-easycart' ),
			'virtualmerchant'           => __( 'United States, Canada', 'wp-easycart' ),
			'eway'                      => __( 'Australia, New Zealand', 'wp-easycart' ),
			'firstdata'                 => __( 'United States', 'wp-easycart' ),
			'goemerchant'               => __( 'United States', 'wp-easycart' ),
			'intuit'                    => __( 'QuickBooks Payments, United States', 'wp-easycart' ),
			'migs'                      => __( 'Mastercard bank gateway, Asia Pacific and Middle East', 'wp-easycart' ),
			'moneris_ca'                => __( 'Canada', 'wp-easycart' ),
			'moneris_us'                => __( 'United States', 'wp-easycart' ),
			'nmi'                       => __( 'United States, works with many processors', 'wp-easycart' ),
			'sagepay'                   => __( 'United Kingdom, Ireland', 'wp-easycart' ),
			'sagepayus'                 => __( 'United States', 'wp-easycart' ),
			'payline'                   => __( 'Older card gateway', 'wp-easycart' ),
			'paymentexpress'            => __( 'New Zealand, Australia ( Windcave )', 'wp-easycart' ),
			'paypal_pro'                => __( 'Older PayPal card gateway', 'wp-easycart' ),
			'paypal_payments_pro'       => __( 'Older PayPal card gateway', 'wp-easycart' ),
			'paypoint'                  => __( 'United Kingdom', 'wp-easycart' ),
			'paytrace'                  => __( 'United States', 'wp-easycart' ),
			'realex'                    => __( 'Ireland, United Kingdom ( Global Payments )', 'wp-easycart' ),
			'securepay'                 => __( 'Australia', 'wp-easycart' ),
			'stripe'                    => __( 'Stripe with keys you enter by hand; Stripe above replaces it', 'wp-easycart' ),
			'securenet'                 => __( 'United States', 'wp-easycart' ),
			'custom'                    => __( 'Set up in code', 'wp-easycart' ),
			'2checkout_thirdparty'      => __( 'Worldwide ( now Verifone )', 'wp-easycart' ),
			'cashfree'                  => __( 'India', 'wp-easycart' ),
			'dwolla_thirdparty'         => __( 'United States bank transfers', 'wp-easycart' ),
			'nets'                      => __( 'Nordic countries', 'wp-easycart' ),
			'payfast_thirdparty'        => __( 'South Africa', 'wp-easycart' ),
			'payfort'                   => __( 'Middle East ( Amazon Payment Services )', 'wp-easycart' ),
			'paymentexpress_thirdparty' => __( 'New Zealand, Australia ( Windcave )', 'wp-easycart' ),
			'realex_thirdparty'         => __( 'Ireland, United Kingdom ( Global Payments )', 'wp-easycart' ),
			'redsys'                    => __( 'Spain', 'wp-easycart' ),
			'sagepay_paynow_za'         => __( 'South Africa', 'wp-easycart' ),
			'skrill'                    => __( 'Worldwide wallet', 'wp-easycart' ),
			'custom_thirdparty'         => __( 'Set up in code', 'wp-easycart' ),
		);
		foreach ( $notes as $key => $note ) {
			if ( isset( $catalog[ $key ] ) ) {
				$catalog[ $key ]['note'] = $note;
			}
		}
		/* 6.0.3: gateways a WP EasyCart PRO older than this does not have ( its settings form and payment code are missing ). */
		$catalog['paytrace']['min_version'] = '6.0.2';
		$catalog = apply_filters( 'wp_easycart_payment_gateway_catalog', $catalog );
		$pro_active = self::pro_active();
		$clean = array();
		foreach ( (array) $catalog as $key => $gw ) {
			$key = sanitize_key( $key );
			if ( '' === $key || ! is_array( $gw ) || empty( $gw['label'] ) ) {
				continue;
			}
			$gw = wp_parse_args( $gw, array(
				'label'       => $key,
				'role'        => 'live',
				'desc'        => '',
				'docs'        => $key,
				'pro'         => false,
				'available'   => null,
				'file'        => '',
				'drawer'      => true,
				'connect'     => false,
				'enable_key'  => '',
				'creds'       => array(),
				'creds_any'   => array(),
				'test'        => '',
				'facts'       => array(),
				'keywords'    => array(),
				'note'        => '',
				'min_version' => '',
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
				'square'         => __( 'Cards on your site, with your Square items brought into your store in a few clicks.', 'wp-easycart' ),
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

	/**
	 * A licensed WP EasyCart PRO that is older than the gateway's min_version: the store updates PRO, it never sees a plan
	 * lock for it ( the admin's "Update, never upsell" rule ).
	 *
	 * @since 6.0.3
	 * @param array $gw Catalog entry.
	 * @return bool
	 */
	public static function needs_update( $gw ) {
		if ( empty( $gw['min_version'] ) || self::is_locked( $gw ) || ! self::pro_active() || ! defined( 'WP_EASYCART_ADMIN_PRO_VERSION' ) ) {
			return false;
		}
		return version_compare( (string) WP_EASYCART_ADMIN_PRO_VERSION, (string) $gw['min_version'], '<' );
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
					self::fact( $s, __( 'Webhook URL', 'wp-easycart' ), wp_easycart_hook_url( 'stripe-webhook' ) );
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
				/* 6.0.2: what PayPal's notifications are doing ( wp_easycart_paypal_webhooks ), as Square's card says for its sync. */
				if ( $express && $s['connected'] && class_exists( 'wp_easycart_paypal_webhooks' ) && '' !== wp_easycart_paypal_webhooks::connection() ) {
					self::fact( $s, __( 'Notifications', 'wp-easycart' ), wp_easycart_paypal_webhooks::card_text() );
				}
				break;

			case 'manual':
				$s['connected']  = true;
				$s['live_ready'] = true;
				self::fact( $s, __( 'Shown as', 'wp-easycart' ), self::manual_title() );
				$message = trim( wp_strip_all_tags( (string) get_option( 'ec_option_direct_deposit_message' ) ) );
				self::fact( $s, __( 'Instructions', 'wp-easycart' ), '' === $message ? __( 'None written yet', 'wp-easycart' ) : ( strlen( $message ) > 90 ? substr( $message, 0, 87 ) . '…' : $message ) );
				/* 6.0.2: the roles it is offered to ( ec_cartpage::use_manual_payment() ) */
				self::fact( $s, __( 'Offered to', 'wp-easycart' ), self::manual_roles_text() );
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

		$s['ready']  = $s['connected'] || $s['live_ready'] || $s['test_ready'];
		$s['update'] = self::needs_update( $gw );
		if ( $s['locked'] ) {
			$s['state'] = 'locked';
		} elseif ( $s['update'] ) {
			$s['state'] = 'update';
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
	 * @since 6.0.2 $roles: the customer roles Bill later is offered to ( ec_option_manual_payment_roles; empty = everyone ).
	 * @param string     $title   Option name.
	 * @param string     $message Instructions.
	 * @param array|null $roles   Roles, or null to leave them as saved.
	 * @return string|WP_Error message
	 */
	public static function save_manual( $title, $message, $roles = null ) {
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
		if ( is_array( $roles ) ) {
			$known = self::manual_role_options();
			$keep  = array();
			foreach ( $roles as $role ) {
				$role = (string) $role;
				if ( isset( $known[ $role ] ) && ! in_array( $role, $keep, true ) ) {
					$keep[] = $role;
				}
			}
			update_option( 'ec_option_manual_payment_roles', implode( ',', $keep ) );
		}
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

	/**
	 * The roles Bill later can be offered to: Guest checkout and each customer role, as Settings › Documents lists them
	 * for PO numbers ( wp_easycart_settings_documents_role_options() ).
	 *
	 * @since 6.0.2
	 * @return array role => label
	 */
	public static function manual_role_options() {
		if ( ! function_exists( 'wp_easycart_settings_documents_role_options' ) && class_exists( 'wp_easycart_admin_settings_registry' ) ) {
			wp_easycart_admin_settings_registry::pages(); /* loads the declarations, Settings › Documents with its role list among them */
		}
		if ( function_exists( 'wp_easycart_settings_documents_role_options' ) ) {
			return (array) wp_easycart_settings_documents_role_options();
		}
		return array( 'guest' => __( 'Guest checkout', 'wp-easycart' ) );
	}

	/**
	 * The roles Bill later is kept to ( empty = everyone ).
	 *
	 * @since 6.0.2
	 * @return array
	 */
	public static function manual_roles() {
		return array_values( array_filter( array_map( 'trim', explode( ',', (string) get_option( 'ec_option_manual_payment_roles', '' ) ) ), 'strlen' ) );
	}

	/**
	 * "Everyone", or the chosen roles by name, for the Bill later card.
	 *
	 * @since 6.0.2
	 * @return string
	 */
	public static function manual_roles_text() {
		$roles = self::manual_roles();
		if ( ! $roles ) {
			return __( 'Everyone', 'wp-easycart' );
		}
		$labels = self::manual_role_options();
		$names  = array();
		foreach ( $roles as $role ) {
			$names[] = isset( $labels[ $role ] ) ? $labels[ $role ] : $role;
		}
		return implode( ', ', $names );
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
				/* 6.0.2: WP EasyCart Connect's /paypal-v3/ onboarding ( wp_easycart_paypal_connect ), same return handler. */
				$nonce = wp_create_nonce( 'wp-easycart-paypal' );
				return array(
					'live' => wp_easycart_paypal_connect::onboard_url( 'production', $admin . '?wpeasycart_paypal_onboard=production&wp_easycart_nonce=' . $nonce ),
					'test' => wp_easycart_paypal_connect::onboard_url( 'sandbox', $admin . '?wpeasycart_paypal_onboard=sandbox&wp_easycart_nonce=' . $nonce ),
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
			'terms_url'   => 'https://www.wpeasycart.com/terms-and-conditions/',
			'i18n'        => array(
				'loading'          => __( 'Loading settings…', 'wp-easycart' ),
				'load_failed'      => __( 'Could not load this gateway’s settings.', 'wp-easycart' ),
				'close'            => __( 'Close', 'wp-easycart' ),
				'cancel'           => __( 'Cancel', 'wp-easycart' ),
				'docs'             => __( 'Docs', 'wp-easycart' ),
				'save'             => __( 'Save', 'wp-easycart' ),
				'saving'           => __( 'Saving…', 'wp-easycart' ),
				'drawer_note'      => __( 'Changes save from the controls in this form.', 'wp-easycart' ),
				'save_on'          => __( 'Save and turn on', 'wp-easycart' ),
				'saved'            => __( 'Settings saved.', 'wp-easycart' ),
				'unsaved'          => __( 'Unsaved changes', 'wp-easycart' ),
				'confirm_leave'    => __( 'You have unsaved changes in this drawer. Close it and lose them?', 'wp-easycart' ),
				'show'             => __( 'Show', 'wp-easycart' ),
				'hide'             => __( 'Hide', 'wp-easycart' ),
				'copy'             => __( 'Copy', 'wp-easycart' ),
				'copied'           => __( 'Copied', 'wp-easycart' ),
				/* translators: %s: gateway name. */
				'legacy_selects'   => __( 'Saving this form also makes %s the gateway for its slot.', 'wp-easycart' ),
				'manual_title'     => __( 'Bill later wording', 'wp-easycart' ),
				'working'          => __( 'Working…', 'wp-easycart' ),
				'opening'          => __( 'Opening…', 'wp-easycart' ),
				'request_failed'   => __( 'Request failed.', 'wp-easycart' ),
				'confirm_off'      => __( 'Turn this gateway off? Shoppers will no longer see it at checkout.', 'wp-easycart' ),
				'confirm_swap'     => __( 'This replaces your current gateway in this slot. Continue?', 'wp-easycart' ),
				'confirm_live'     => __( 'Take live payments? Real cards are charged from now on.', 'wp-easycart' ),
				'confirm_all_live' => __( 'Switch all active gateways to live mode? Real cards will be charged from now on.', 'wp-easycart' ),
				'tabs_label'       => __( 'Settings sections', 'wp-easycart' ),
				'terms_title'      => __( 'EasyCart Connect terms', 'wp-easycart' ),
				'terms_text'       => __( 'The Free edition includes unlimited products, orders and accounts plus Bill later. PayPal, Stripe and Square are available through EasyCart Connect with a 2% EasyCart fee on each sale. Upgrading to Pro or Premium removes the fee and unlocks 30+ more gateways.', 'wp-easycart' ),
				/* translators: 1: opening link tag, 2: closing link tag. */
				'terms_agree'      => __( 'I agree to the WP EasyCart %1$sterms and privacy policy%2$s.', 'wp-easycart' ),
				'terms_accept'     => __( 'Accept and continue', 'wp-easycart' ),
				'older_show'       => __( 'Show older gateways', 'wp-easycart' ),
				'older_hide'       => __( 'Hide older gateways', 'wp-easycart' ),
				/* translators: %s: what was typed in the gateway search. */
				'no_match'         => __( 'No gateway matches “%s”.', 'wp-easycart' ),
				'rec_done'         => __( 'Every recommended gateway is already on. Choose All to see the rest.', 'wp-easycart' ),
				'none_left'        => __( 'Every gateway in this group is already active.', 'wp-easycart' ),
			),
		) );
	}

	/* ------------------------------------------------------------------ */
	/* Rendering ( never enqueues )                                        */
	/* ------------------------------------------------------------------ */

	/**
	 * Gateways listed first in "Add or change a gateway" ( the Recommended filter ), in this order. Keys missing from the
	 * catalog are dropped.
	 *
	 * @since 6.0.3
	 * @return array gateway keys
	 */
	public static function recommended() {
		$keys = apply_filters( 'wp_easycart_payment_recommended_gateways', array( 'stripe_connect', 'square', 'paypal', 'amazonpay', 'authorize' ) );
		$keep = array();
		foreach ( (array) $keys as $key ) {
			$key = sanitize_key( (string) $key );
			if ( '' !== $key && self::gateway( $key ) && ! in_array( $key, $keep, true ) ) {
				$keep[] = $key;
			}
		}
		return $keep;
	}

	/**
	 * Rarely chosen gateways: listed under "Older gateways", closed until the merchant opens the group or searches. They
	 * stay fully usable, and one already selected shows as usual.
	 *
	 * @since 6.0.3
	 * @return array gateway keys
	 */
	public static function older() {
		$keys = apply_filters(
			'wp_easycart_payment_older_gateways',
			array( 'stripe', 'paypal_pro', 'paypal_payments_pro', 'chronopay', 'migs', 'payline', 'paypoint', 'paymentexpress', 'paymentexpress_thirdparty', 'realex', 'realex_thirdparty', 'sagepay', 'sagepayus', 'securenet', 'firstdata', 'virtualmerchant', 'nets', 'dwolla_thirdparty', '2checkout_thirdparty', 'custom', 'custom_thirdparty' )
		);
		return array_values( array_filter( array_map( 'sanitize_key', array_map( 'strval', (array) $keys ) ), 'strlen' ) );
	}

	/** Customer words for a gateway's state, the dot beside them, and the line's one-sentence detail. */
	private static function line_state( $key, $s, $gw ) {
		switch ( $s['state'] ) {
			case 'locked':
				/* translators: %s: plan name, Pro or Premium ( Pro/Premium when no license is known ). */
				return array( 'dot' => 'bad', 'text' => sprintf( __( '%s license needed', 'wp-easycart' ), self::plan_name() ), 'detail' => sprintf( __( 'This gateway is still selected but needs an active %s license to process payments.', 'wp-easycart' ), self::plan_name() ) );
			case 'update':
				/* translators: %s: version number, e.g. 6.0.2. */
				return array( 'dot' => 'warn', 'text' => __( 'Needs a newer WP EasyCart PRO', 'wp-easycart' ), 'detail' => sprintf( __( 'WP EasyCart PRO %s or newer runs this gateway.', 'wp-easycart' ), $gw['min_version'] ) );
			case 'connected':
				if ( 'manual' === $gw['role'] ) {
					return array( 'dot' => 'on', 'text' => __( 'On', 'wp-easycart' ), 'detail' => self::line_detail( $key, $s, $gw ) );
				}
				return array( 'dot' => $s['test'] ? 'test' : 'on', 'text' => $s['test'] ? __( 'Connected · Test mode', 'wp-easycart' ) : __( 'Connected · Live', 'wp-easycart' ), 'detail' => self::line_detail( $key, $s, $gw ) );
			case 'incomplete':
				return array( 'dot' => 'warn', 'text' => __( 'Selected, not set up', 'wp-easycart' ), 'detail' => $gw['connect'] ? __( 'Connect your account to start taking payments.', 'wp-easycart' ) : __( 'Finish its settings to start taking payments.', 'wp-easycart' ) );
		}
		if ( 'manual' === $gw['role'] ) {
			return array( 'dot' => 'off', 'text' => __( 'Off', 'wp-easycart' ), 'detail' => __( 'Shoppers order now and pay you offline: bank transfer, cheque or at pickup.', 'wp-easycart' ) );
		}
		return array( 'dot' => 'off', 'text' => __( 'Off', 'wp-easycart' ), 'detail' => '' );
	}

	/** The line under a gateway's name: its account and the choices that matter, from the card facts ( never secrets ). */
	private static function line_detail( $key, $s, $gw ) {
		if ( 'manual' === $key ) {
			/* translators: 1: the pay-later option name shoppers see, 2: who it is offered to ( Everyone, or role names ). */
			return sprintf( __( 'Shown as “%1$s” · offered to %2$s', 'wp-easycart' ), self::manual_title(), self::manual_roles_text() );
		}
		$prefer = array(
			'stripe_connect' => array( __( 'Currency', 'wp-easycart' ), __( 'Business country', 'wp-easycart' ), __( 'Extra methods', 'wp-easycart' ) ),
			'square'         => array( __( 'Merchant name', 'wp-easycart' ), __( 'Location', 'wp-easycart' ), __( 'Digital wallets', 'wp-easycart' ) ),
			'paypal'         => array( __( 'Account email', 'wp-easycart' ), __( 'Merchant ID', 'wp-easycart' ), __( 'Extra buttons', 'wp-easycart' ) ),
		);
		$skip  = array( __( 'Accounts', 'wp-easycart' ), __( 'Webhook URL', 'wp-easycart' ), __( 'Notifications', 'wp-easycart' ), __( 'Access renews', 'wp-easycart' ), __( 'Checkout', 'wp-easycart' ) );
		$parts = array();
		if ( isset( $prefer[ $key ] ) ) {
			foreach ( $prefer[ $key ] as $label ) {
				if ( isset( $s['facts'][ $label ] ) && '' !== (string) $s['facts'][ $label ] ) {
					$parts[] = (string) $s['facts'][ $label ];
				}
			}
			if ( 'paypal' === $key && 3 === count( $parts ) && isset( $s['facts'][ __( 'Account email', 'wp-easycart' ) ] ) ) {
				unset( $parts[1] ); /* the email names the account; the merchant ID only when there is no email */
			}
		} else {
			foreach ( (array) $s['facts'] as $label => $value ) {
				if ( ! in_array( $label, $skip, true ) && '' !== (string) $value ) {
					$parts[] = (string) $value;
				}
				if ( count( $parts ) >= 2 ) {
					break;
				}
			}
		}
		return implode( ' · ', $parts );
	}

	/** One short line for a gateway in the list: what it is, or where it works ( searched too ). */
	private static function tile_note( $gw ) {
		if ( '' !== $gw['note'] ) {
			return $gw['note'];
		}
		return $gw['desc'];
	}

	/** Small inline SVGs ( fixed markup ). */
	private static function svg( $name ) {
		switch ( $name ) {
			case 'lock':
				return '<svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true" focusable="false"><rect x="3" y="7" width="10" height="7" rx="1.5"/><path d="M5 7V5a3 3 0 016 0v2"/></svg>';
			case 'more':
				return '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true" focusable="false"><circle cx="3" cy="8" r="1.4"/><circle cx="8" cy="8" r="1.4"/><circle cx="13" cy="8" r="1.4"/></svg>';
			case 'search':
				return '<svg width="15" height="15" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true" focusable="false"><circle cx="7" cy="7" r="4.5"/><path d="M10.5 10.5L14 14"/></svg>';
		}
		return '';
	}

	/** A Connect link: inert ( aria-disabled ) while the EasyCart Connect terms wait, then the onboarding URL. */
	private static function connect_link( $url, $label, $classes, $gate, $extra = '' ) {
		return '<a class="' . esc_attr( $classes . ' ecpay-connect' ) . '" href="' . ( $gate ? '#' : esc_url( $url ) ) . '" data-href="' . esc_url( $url ) . '"' . ( $gate ? ' aria-disabled="true"' : '' ) . $extra . '>' . esc_html( $label ) . '</a>';
	}

	/** The ⋯ menu of a line. $items: array of HTML strings ( menu items ) and '-' separators. */
	private static function render_menu( $id, $label, $items ) {
		$items = array_values( array_filter( $items, 'strlen' ) );
		while ( $items && '-' === $items[0] ) {
			array_shift( $items );
		}
		while ( $items && '-' === end( $items ) ) {
			array_pop( $items );
		}
		if ( ! $items ) {
			return;
		}
		?>
		<div class="ecpay-menu-wrap">
			<button type="button" class="ecv2-btn ecv2-btn-sm ecpay-menu-btn" aria-haspopup="menu" aria-expanded="false" aria-controls="<?php echo esc_attr( $id ); ?>" aria-label="<?php echo esc_attr( $label ); ?>" title="<?php echo esc_attr( $label ); ?>"><?php echo self::svg( 'more' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></button>
			<div class="ecpay-menu" id="<?php echo esc_attr( $id ); ?>" role="menu" hidden>
				<?php
				foreach ( $items as $item ) {
					echo '-' === $item ? '<div class="ecpay-menu-sep" role="separator"></div>' : $item; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- items are built from escaped parts in render_line().
				}
				?>
			</div>
		</div>
		<?php
	}

	/** One menu item: a button carrying the page's data-ecpay-* action attributes ( attributes already escaped ). */
	private static function menu_button( $label, $attrs, $danger = false ) {
		return '<button type="button" role="menuitem" class="ecpay-menu-item' . ( $danger ? ' is-danger' : '' ) . '" ' . $attrs . '>' . esc_html( $label ) . '</button>';
	}

	/** One menu item that leaves the page ( Connect, Reconnect, Renew access, Disconnect ). */
	private static function menu_link( $label, $href, $attrs = '', $danger = false ) {
		return '<a role="menuitem" class="ecpay-menu-item' . ( $danger ? ' is-danger' : '' ) . '" href="' . esc_url( $href ) . '" ' . $attrs . '>' . esc_html( $label ) . '</a>';
	}

	/** The page summary ( moved beside the page title by the script ): one pill per way to pay that is on. */
	private static function render_summary() {
		$roles = self::roles();
		echo '<div class="ecpay-summary" id="ecpay_summary" aria-label="' . esc_attr__( 'Payment summary', 'wp-easycart' ) . '">';
		foreach ( $roles as $role => $meta ) {
			$key = self::active( $role );
			if ( '' === $key ) {
				if ( 'live' === $role ) {
					echo '<span class="ecpay-sum" title="' . esc_attr( $meta['label'] ) . '"><i class="ecpay-dot is-bad" aria-hidden="true"></i>' . esc_html__( 'No card payments', 'wp-easycart' ) . '</span>';
				}
				continue;
			}
			$gw = self::gateway( $key );
			$s  = self::status( $key );
			if ( 'manual' === $role && ! $s['enabled'] ) {
				continue;
			}
			if ( 'wallet' === $role && ! $s['enabled'] ) {
				continue;
			}
			$state = self::line_state( $key, $s, $gw );
			echo '<span class="ecpay-sum" title="' . esc_attr( $meta['label'] . ': ' . $state['text'] ) . '"><i class="ecpay-dot is-' . esc_attr( $state['dot'] ) . '" aria-hidden="true"></i>' . esc_html( $gw['label'] ) . '<span class="screen-reader-text"> · ' . esc_html( $state['text'] ) . '</span></span>';
		}
		echo '</div>';
	}

	/** One line while any selected gateway is in sandbox / test mode. */
	private static function render_test_notice() {
		$tests = self::test_mode_gateways();
		if ( ! $tests ) {
			return;
		}
		$names = array();
		foreach ( $tests as $key ) {
			$gw      = self::gateway( $key );
			$names[] = $gw['label'];
		}
		?>
		<div class="ecpay-notice" id="ecpay_test_banner" role="status">
			<span class="ecpay-notice-text">
				<b>
				<?php
				/* translators: %s: comma-separated gateway names. */
				echo esc_html( sprintf( _n( 'Test mode is on for %s.', 'Test mode is on for %s.', count( $names ), 'wp-easycart' ), implode( ', ', $names ) ) );
				?>
				</b>
				<?php esc_html_e( 'Checkout works, but no real money moves.', 'wp-easycart' ); ?>
			</span>
			<button type="button" class="ecv2-btn ecv2-btn-sm" data-ecpay-test-off="1"><?php esc_html_e( 'Take live payments', 'wp-easycart' ); ?></button>
		</div>
		<?php
	}

	/** Section "How customers pay": flash, the test-mode line and one line per way to pay. */
	public static function render_active( $page, $section ) {
		$gate = self::terms_gate();
		self::render_flash();
		self::render_summary();
		self::render_test_notice();
		echo '<div class="ecpay-lines" role="list">';
		foreach ( self::roles() as $role => $meta ) {
			$key = self::active( $role );
			if ( 'wallet' === $role && '' !== $key ) {
				$s = self::status( $key );
				if ( ! $s['enabled'] ) {
					$key = ''; /* a wallet that is off is a suggestion on the empty line ( or, while locked, a row in the list ) */
				}
			}
			if ( '' === $key ) {
				self::render_empty_line( $role, $meta, $gate );
				continue;
			}
			self::render_line( $role, $meta, $key, $gate );
		}
		echo '</div>';
	}

	/** One line: the slot in customer words, the gateway, its state, Manage ( or the step that is missing ) and the ⋯ menu. */
	private static function render_line( $role, $meta, $key, $gate ) {
		$gw    = self::gateway( $key );
		$s     = self::status( $key );
		$state = self::line_state( $key, $s, $gw );
		$urls  = $gw['connect'] ? self::connect_urls( $key ) : array();
		$items = array();
		/* translators: %s: gateway name. */
		$menu_label = sprintf( __( 'More actions for %s', 'wp-easycart' ), $gw['label'] );
		?>
		<div class="ecpay-line is-<?php echo esc_attr( $s['state'] ); ?>" role="listitem" data-role="<?php echo esc_attr( $role ); ?>" data-gateway="<?php echo esc_attr( $key ); ?>">
			<span class="ecpay-line-slot"><?php echo esc_html( $meta['label'] ); ?></span>
			<div class="ecpay-line-gw">
				<?php self::render_logo( $key, $gw ); ?>
				<div class="ecpay-line-text">
					<strong><?php echo esc_html( $gw['label'] ); ?></strong>
					<span class="ecpay-state"><i class="ecpay-dot is-<?php echo esc_attr( $state['dot'] ); ?>" aria-hidden="true"></i><?php echo esc_html( $state['text'] ); ?></span>
					<?php if ( '' !== $state['detail'] ) : ?><span class="ecpay-line-detail"><?php echo esc_html( $state['detail'] ); ?></span><?php endif; ?>
				</div>
			</div>
			<div class="ecpay-line-act">
				<?php
				if ( 'locked' === $s['state'] ) {
					echo '<button type="button" class="ecst-pro-btn" onclick="ecst.upsell( \'' . esc_js( $key ) . '\' ); return false;">' . self::svg( 'lock' ) . ' ' . esc_html( self::pro_badge() ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG, escaped parts.
				} elseif ( 'update' === $s['state'] ) {
					echo '<a class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" href="' . esc_url( self_admin_url( 'plugins.php' ) ) . '">' . esc_html__( 'Update WP EasyCart PRO →', 'wp-easycart' ) . '</a>';
				} elseif ( 'manual' === $role ) {
					echo '<button type="button" class="ecv2-btn ecv2-btn-sm' . ( $s['enabled'] ? '' : ' ecv2-btn-primary' ) . '" data-ecpay-manual="1">' . esc_html( $s['enabled'] ? __( 'Manage', 'wp-easycart' ) : __( 'Set up', 'wp-easycart' ) ) . '</button>';
				} elseif ( $gw['connect'] && ! $s['connected'] && ! $s['live_ready'] && ! empty( $urls['live'] ) ) {
					echo self::connect_link( $urls['live'], $s['test'] ? __( 'Connect live account', 'wp-easycart' ) : __( 'Connect', 'wp-easycart' ), 'ecv2-btn ecv2-btn-sm ecv2-btn-primary', $gate ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
				} elseif ( ! $s['ready'] && $gw['drawer'] ) {
					echo '<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" data-ecpay-open="' . esc_attr( $key ) . '">' . esc_html( empty( $s['started'] ) ? __( 'Connect', 'wp-easycart' ) : __( 'Finish setup', 'wp-easycart' ) ) . '</button>';
				} elseif ( $gw['drawer'] ) {
					echo '<button type="button" class="ecv2-btn ecv2-btn-sm" data-ecpay-open="' . esc_attr( $key ) . '">' . esc_html__( 'Manage', 'wp-easycart' ) . '</button>';
				}

				if ( 'manual' === $role ) {
					$items[] = self::menu_button( $s['enabled'] ? __( 'Turn off', 'wp-easycart' ) : __( 'Turn on', 'wp-easycart' ), 'data-ecpay-toggle="manual" data-on="' . ( $s['enabled'] ? '0' : '1' ) . '"' );
				} elseif ( 'locked' === $s['state'] || 'update' === $s['state'] ) {
					if ( 'wallet' !== $role ) {
						$items[] = self::menu_button( __( 'Replace with another gateway', 'wp-easycart' ), 'data-ecpay-choose="' . esc_attr( $role ) . '"' );
					}
					$items[] = '-';
					$items[] = self::menu_button( __( 'Turn off', 'wp-easycart' ), 'data-ecpay-toggle="' . esc_attr( $key ) . '" data-on="0"' );
				} else {
					if ( $s['enabled'] && $s['test'] && $s['live_ready'] ) {
						$items[] = self::menu_button( __( 'Take live payments', 'wp-easycart' ), 'data-ecpay-mode="' . esc_attr( $key ) . '" data-mode="live"' );
					} elseif ( $s['enabled'] && ! $s['test'] && $s['test_ready'] && 'stripe' !== $key ) {
						$items[] = self::menu_button( __( 'Switch to test mode', 'wp-easycart' ), 'data-ecpay-mode="' . esc_attr( $key ) . '" data-mode="test"' );
					}
					if ( $gw['connect'] && ! empty( $urls ) ) {
						if ( $s['ready'] && ! $s['test_ready'] && ! empty( $urls['test'] ) ) {
							$items[] = '<a role="menuitem" class="ecpay-menu-item ecpay-connect" href="' . ( $gate ? '#' : esc_url( $urls['test'] ) ) . '" data-href="' . esc_url( $urls['test'] ) . '"' . ( $gate ? ' aria-disabled="true"' : '' ) . '>' . esc_html__( 'Connect a sandbox account', 'wp-easycart' ) . '</a>';
						}
						if ( $s['ready'] ) {
							$env     = ( ( $s['test'] && $s['test_ready'] ) || ! $s['live_ready'] ) ? 'test' : 'live';
							$items[] = '<a role="menuitem" class="ecpay-menu-item ecpay-connect" href="' . ( $gate ? '#' : esc_url( $urls[ $env ] ) ) . '" data-href="' . esc_url( $urls[ $env ] ) . '"' . ( $gate ? ' aria-disabled="true"' : '' ) . '>' . esc_html__( 'Reconnect', 'wp-easycart' ) . '</a>';
							if ( 'square' === $key ) {
								$items[] = self::menu_link( __( 'Renew access', 'wp-easycart' ), wp_nonce_url( add_query_arg( array( 'ec_admin_form_action' => 'square-renew' ), admin_url( 'admin.php?page=wp-easycart-settings&subpage=payment' ) ), 'wp-easycart-payment-square-renew' ), 'data-ecpay-nav="1"' );
							}
						}
					}
					if ( 'wallet' !== $role ) {
						$items[] = self::menu_button( __( 'Replace with another gateway', 'wp-easycart' ), 'data-ecpay-choose="' . esc_attr( $role ) . '"' );
					}
					$items[] = '-';
					if ( $s['enabled'] ) {
						$items[] = self::menu_button( __( 'Turn off', 'wp-easycart' ), 'data-ecpay-toggle="' . esc_attr( $key ) . '" data-on="0"' );
					} elseif ( $s['ready'] ) {
						$items[] = self::menu_button( __( 'Turn on', 'wp-easycart' ), 'data-ecpay-toggle="' . esc_attr( $key ) . '" data-on="1"' );
					}
					if ( $gw['connect'] && $s['ready'] ) {
						$env        = ( ( $s['test'] && $s['test_ready'] ) || ! $s['live_ready'] ) ? 'test' : 'live';
						$disconnect = self::disconnect_url( $key, $env );
						if ( '' !== $disconnect ) {
							/* translators: 1: gateway name, 2: sandbox or live. */
							$confirm = sprintf( __( 'Disconnect %1$s ( %2$s )? The stored keys are removed from this site; revoke access from your %1$s account too.', 'wp-easycart' ), $gw['label'], ( 'test' === $env ) ? __( 'sandbox', 'wp-easycart' ) : __( 'live', 'wp-easycart' ) );
							$items[] = self::menu_link( __( 'Disconnect', 'wp-easycart' ), $disconnect, 'data-ecpay-confirm="' . esc_attr( $confirm ) . '"', true );
						}
					}
				}
				self::render_menu( 'ecpay_menu_' . $role, $menu_label, $items );
				?>
			</div>
		</div>
		<?php
	}

	/** A way to pay with nothing chosen: the recommended choices as one-click suggestions, and the full list one click away. */
	private static function render_empty_line( $role, $meta, $gate ) {
		$suggest = array();
		if ( 'wallet' === $role ) {
			foreach ( self::catalog() as $key => $gw ) {
				if ( 'wallet' === $gw['role'] && ! self::is_locked( $gw ) && ! self::needs_update( $gw ) ) {
					$suggest[ $key ] = '';
				}
			}
			if ( ! $suggest ) {
				return; /* Free: Amazon Pay lives in the gateway list ( locked ) instead of an empty line */
			}
		} elseif ( 'live' === $role || 'third_party' === $role ) {
			$suggest = self::partners( $role );
		}
		$empty = array(
			'live'        => __( 'Shoppers can’t pay by card on your site yet.', 'wp-easycart' ),
			'third_party' => __( 'Optional: PayPal and similar buttons beside your card payments.', 'wp-easycart' ),
			'wallet'      => __( 'Optional: a stored wallet beside your card payments.', 'wp-easycart' ),
		);
		$more  = array(
			'live'        => __( 'All card gateways', 'wp-easycart' ),
			'third_party' => __( 'All checkout buttons', 'wp-easycart' ),
		);
		$rec   = self::recommended();
		?>
		<div class="ecpay-line is-empty" role="listitem" data-role="<?php echo esc_attr( $role ); ?>">
			<span class="ecpay-line-slot"><?php echo esc_html( $meta['label'] ); ?></span>
			<div class="ecpay-line-gw">
				<div class="ecpay-line-text">
					<?php if ( $suggest ) : ?>
						<div class="ecpay-suggest">
							<?php foreach ( $suggest as $key => $benefit ) : ?>
								<?php self::render_suggestion( $key, $role, $gate, isset( $rec[0] ) && $rec[0] === $key ); ?>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
					<?php if ( isset( $empty[ $role ] ) ) : ?><span class="ecpay-line-detail"><?php echo esc_html( $empty[ $role ] ); ?></span><?php endif; ?>
				</div>
			</div>
			<div class="ecpay-line-act">
				<?php if ( isset( $more[ $role ] ) ) : ?>
					<button type="button" class="ecst-link" data-ecpay-choose="<?php echo esc_attr( $role ); ?>"><?php echo esc_html( $more[ $role ] ); ?> &darr;</button>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/** One suggestion chip on an empty line: Connect ( Stripe, Square, PayPal ), Use ( set up already ), or Set up. */
	private static function render_suggestion( $key, $role, $gate, $is_first ) {
		$gw = self::gateway( $key );
		$s  = $gw ? self::status( $key ) : false;
		if ( ! $gw || ! $s ) {
			return;
		}
		ob_start();
		self::render_logo( $key, $gw );
		$logo = ob_get_clean();
		$tag  = $is_first ? '<span class="ecpay-rec">' . esc_html__( 'Recommended', 'wp-easycart' ) . '</span>' : '';
		if ( $s['locked'] ) {
			echo '<button type="button" class="ecpay-suggest-btn is-locked" onclick="ecst.upsell( \'' . esc_js( $key ) . '\' ); return false;">' . $logo . esc_html( $gw['label'] ) . ' ' . self::svg( 'lock' ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts, static SVG.
			return;
		}
		if ( $s['ready'] ) {
			/* translators: %s: gateway name. */
			echo '<button type="button" class="ecpay-suggest-btn" data-ecpay-toggle="' . esc_attr( $key ) . '" data-on="1" data-swap="0">' . $logo . esc_html( sprintf( __( 'Use %s', 'wp-easycart' ), $gw['label'] ) ) . $tag . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
			return;
		}
		$urls = $gw['connect'] ? self::connect_urls( $key ) : array();
		if ( ! empty( $urls['live'] ) ) {
			/* translators: %s: gateway name. */
			echo '<a class="ecpay-suggest-btn ecpay-connect" href="' . ( $gate ? '#' : esc_url( $urls['live'] ) ) . '" data-href="' . esc_url( $urls['live'] ) . '"' . ( $gate ? ' aria-disabled="true"' : '' ) . '>' . $logo . esc_html( sprintf( __( 'Connect %s', 'wp-easycart' ), $gw['label'] ) ) . $tag . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
			return;
		}
		if ( $gw['drawer'] ) {
			/* translators: %s: gateway name. */
			echo '<button type="button" class="ecpay-suggest-btn" data-ecpay-open="' . esc_attr( $key ) . '">' . $logo . esc_html( sprintf( __( 'Set up %s', 'wp-easycart' ), $gw['label'] ) ) . $tag . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
		}
	}

	/** Section "Add or change a gateway": search, filters, the gateway tiles ( older ones folded away ) and the plan notes. */
	public static function render_more( $page, $section ) {
		$gate   = self::terms_gate();
		$active = array();
		foreach ( array_keys( self::roles() ) as $role ) {
			$key = self::active( $role );
			if ( '' !== $key && ( 'manual' !== $role ) ) {
				$s = self::status( $key );
				if ( $s['enabled'] ) {
					$active[ $key ] = true;
				}
			}
		}
		$rec    = self::recommended();
		$older  = array_flip( self::older() );
		$counts = array( 'live' => 0, 'third_party' => 0, 'wallet' => 0 );
		$tiles  = array();
		$locked = 0;
		foreach ( self::catalog() as $key => $gw ) {
			if ( isset( $active[ $key ] ) || ! isset( $counts[ $gw['role'] ] ) ) {
				continue;
			}
			$tiles[ $key ] = $gw;
			$counts[ $gw['role'] ]++;
			if ( self::is_locked( $gw ) ) {
				$locked++;
			}
		}
		/* Recommended first ( in their order ), then the rest in catalog order. */
		$sorted = array();
		foreach ( $rec as $key ) {
			if ( isset( $tiles[ $key ] ) ) {
				$sorted[ $key ] = $tiles[ $key ];
			}
		}
		$tiles   = $sorted + $tiles;
		$filters = array(
			'recommended' => __( 'Recommended', 'wp-easycart' ),
			'live'        => __( 'Card payments', 'wp-easycart' ),
			'third_party' => __( 'Checkout buttons', 'wp-easycart' ),
			'wallet'      => __( 'Wallets', 'wp-easycart' ),
			'all'         => __( 'All', 'wp-easycart' ),
		);
		?>
		<div class="ecpay-catalog" id="ecpay_catalog" data-filter="recommended">
			<?php if ( ! $tiles ) : ?>
				<div class="ecpay-cat-empty"><?php esc_html_e( 'Every gateway is already active.', 'wp-easycart' ); ?></div>
			<?php else : ?>
				<div class="ecpay-cat-tools">
					<label class="ecpay-search" for="ecpay_search">
						<?php echo self::svg( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
						<span class="screen-reader-text"><?php esc_html_e( 'Search gateways', 'wp-easycart' ); ?></span>
						<input type="search" id="ecpay_search" placeholder="<?php esc_attr_e( 'Search gateways, e.g. Moneris or Canada', 'wp-easycart' ); ?>" autocomplete="off" aria-controls="ecpay_tiles" />
					</label>
					<div class="ecpay-filters" role="group" aria-label="<?php esc_attr_e( 'Filter gateways', 'wp-easycart' ); ?>">
						<?php foreach ( $filters as $filter => $label ) : ?>
							<?php
							if ( isset( $counts[ $filter ] ) && ! $counts[ $filter ] ) {
								continue;
							}
							?>
							<button type="button" class="ecpay-filter<?php echo 'recommended' === $filter ? ' is-on' : ''; ?>" data-ecpay-filter="<?php echo esc_attr( $filter ); ?>" aria-pressed="<?php echo 'recommended' === $filter ? 'true' : 'false'; ?>"><?php echo esc_html( $label ); ?><?php if ( isset( $counts[ $filter ] ) ) : ?> <em><?php echo (int) $counts[ $filter ]; ?></em><?php endif; ?></button>
						<?php endforeach; ?>
					</div>
				</div>
				<?php if ( $locked > 0 && ! self::pro_active() ) : ?>
					<div class="ecpay-pro-note">
						<p>
							<b>
							<?php
							/* translators: 1: number of gateways, 2: plan name, Pro or Premium ( Pro/Premium when no license is known ). */
							echo esc_html( sprintf( _n( '%1$d more gateway comes with %2$s.', '%1$d more gateways come with %2$s.', $locked, 'wp-easycart' ), $locked, self::plan_name() ) );
							?>
							</b>
							<?php echo esc_html( self::show_fee_note() ? __( 'The 2% platform fee goes away too. Open a locked gateway for a short preview.', 'wp-easycart' ) : __( 'Open a locked gateway for a short preview.', 'wp-easycart' ) ); ?>
						</p>
						<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" onclick="ecst.upsell( 'payment_fees' ); return false;"><?php esc_html_e( 'See plans', 'wp-easycart' ); ?></button>
					</div>
				<?php endif; ?>
				<div class="ecpay-tiles" id="ecpay_tiles" role="list">
					<?php foreach ( $tiles as $key => $gw ) : ?>
						<?php self::render_tile( $key, $gw, $gate, in_array( $key, $rec, true ), isset( $older[ $key ] ) ); ?>
					<?php endforeach; ?>
				</div>
				<div class="ecpay-older-row">
					<button type="button" class="ecst-link" id="ecpay_older_toggle" aria-expanded="false" hidden></button>
				</div>
				<p class="ecpay-cat-empty" id="ecpay_cat_empty" role="status" hidden></p>
			<?php endif; ?>
			<?php if ( self::show_fee_note() ) : ?>
				<p class="ecpay-fee-note">
					<b><?php esc_html_e( 'Free plan:', 'wp-easycart' ); ?></b>
					<?php esc_html_e( 'EasyCart Connect adds a 2% fee to Stripe, Square and PayPal payments, on top of the provider’s own fees.', 'wp-easycart' ); ?>
					<a href="#" onclick="ecst.upsell( 'payment_fees' ); return false;">
					<?php
					/* translators: %s: plan name, Pro/Premium on the free edition. */
					echo esc_html( sprintf( __( '%s removes it', 'wp-easycart' ), self::plan_name() ) );
					?>
					&rarr;</a>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/** One gateway in the list: logo, name, one short line, and the action that fits its state. */
	private static function render_tile( $key, $gw, $gate, $is_rec, $is_older ) {
		$s       = self::status( $key );
		$note    = self::tile_note( $gw );
		$classes = array( 'ecpay-tile' );
		if ( $s['locked'] ) {
			$classes[] = 'is-locked';
		}
		$search = strtolower( $gw['label'] . ' ' . $note . ' ' . implode( ' ', (array) $gw['keywords'] ) . ' ' . $key );
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" role="listitem" data-gateway="<?php echo esc_attr( $key ); ?>" data-role="<?php echo esc_attr( $gw['role'] ); ?>"<?php echo $is_rec ? ' data-rec="1"' : ''; ?><?php echo $is_older ? ' data-older="1"' : ''; ?> data-search="<?php echo esc_attr( $search ); ?>">
			<?php self::render_logo( $key, $gw ); ?>
			<div class="ecpay-tile-text">
				<b><?php echo esc_html( $gw['label'] ); ?><?php if ( $is_rec && ! $s['locked'] ) : ?> <span class="ecpay-rec"><?php esc_html_e( 'Recommended', 'wp-easycart' ); ?></span><?php endif; ?></b>
				<?php if ( '' !== $note ) : ?><span><?php echo esc_html( $note ); ?></span><?php endif; ?>
				<?php if ( ! $s['locked'] && 'update' !== $s['state'] && $s['ready'] ) : ?>
					<span class="ecpay-tile-state"><?php echo esc_html( $s['test'] ? __( 'Set up with test keys, off', 'wp-easycart' ) : __( 'Set up, off', 'wp-easycart' ) ); ?></span>
				<?php endif; ?>
			</div>
			<div class="ecpay-tile-act">
				<?php if ( $s['locked'] ) : ?>
					<?php /* translators: 1: gateway name, 2: plan name, Pro or Premium ( Pro/Premium when no license is known ). */ ?>
					<button type="button" class="ecpay-lock-btn" onclick="ecst.upsell( '<?php echo esc_js( $key ); ?>' ); return false;" aria-label="<?php echo esc_attr( sprintf( __( '%1$s comes with %2$s. See what’s included', 'wp-easycart' ), $gw['label'], self::plan_name() ) ); ?>" title="<?php echo esc_attr( self::pro_badge() ); ?>"><?php echo self::svg( 'lock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></button>
				<?php elseif ( 'update' === $s['state'] ) : ?>
					<a class="ecv2-btn ecv2-btn-sm" href="<?php echo esc_url( self_admin_url( 'plugins.php' ) ); ?>"><?php esc_html_e( 'Update WP EasyCart PRO →', 'wp-easycart' ); ?></a>
				<?php elseif ( $gw['connect'] && ! $s['ready'] ) : ?>
					<?php $urls = self::connect_urls( $key ); ?>
					<?php echo self::connect_link( $urls['live'], __( 'Connect', 'wp-easycart' ), 'ecv2-btn ecv2-btn-sm ecv2-btn-primary', $gate ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts. ?>
					<?php if ( ! empty( $urls['test'] ) ) : ?>
						<?php echo self::connect_link( $urls['test'], __( 'Try sandbox', 'wp-easycart' ), 'ecv2-btn ecv2-btn-sm ecv2-btn-ghost', $gate ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts. ?>
					<?php endif; ?>
				<?php elseif ( $s['ready'] ) : ?>
					<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" data-ecpay-toggle="<?php echo esc_attr( $key ); ?>" data-on="1" data-swap="<?php echo ( 'wallet' !== $gw['role'] && '' !== self::active( $gw['role'] ) ) ? '1' : '0'; ?>"><?php esc_html_e( 'Turn on', 'wp-easycart' ); ?></button>
					<?php if ( $gw['drawer'] ) : ?><button type="button" class="ecv2-btn ecv2-btn-sm" data-ecpay-open="<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'Settings', 'wp-easycart' ); ?></button><?php endif; ?>
				<?php elseif ( $gw['drawer'] ) : ?>
					<button type="button" class="ecv2-btn ecv2-btn-sm" data-ecpay-open="<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'Set up', 'wp-easycart' ); ?></button>
				<?php else : ?>
					<button type="button" class="ecv2-btn ecv2-btn-sm" data-ecpay-toggle="<?php echo esc_attr( $key ); ?>" data-on="1" data-swap="<?php echo ( '' !== self::active( $gw['role'] ) ) ? '1' : '0'; ?>"><?php esc_html_e( 'Turn on', 'wp-easycart' ); ?></button>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	private static function render_logo( $key, $gw ) {
		$brands = array(
			'stripe_connect'      => array( '#635bff', 'S' ),
			'stripe'              => array( '#8a85ff', 'S1' ),
			'square'              => array( '#1c1c1c', 'Sq' ),
			'paypal'              => array( '#003087', 'PP' ),
			'paypal_pro'          => array( '#3b5998', 'PF' ),
			'paypal_payments_pro' => array( '#3b5998', 'PP' ),
			'manual'              => array( '#475569', '$' ),
			'amazonpay'           => array( '#232f3e', 'a' ),
			'authorize'           => array( '#1b5e9c', 'AN' ),
			'braintree'           => array( '#111827', 'BT' ),
			'moneris_ca'          => array( '#b0121b', 'Mo' ),
			'moneris_us'          => array( '#b0121b', 'Mo' ),
			'nmi'                 => array( '#0b3d91', 'NM' ),
			'paytrace'            => array( '#0066b3', 'PT' ),
			'eway'                => array( '#c2410c', 'eW' ),
			'securepay'           => array( '#0077a8', 'SP' ),
			'beanstream'          => array( '#0b2a4a', 'Bb' ),
			'cardpointe'          => array( '#0a4d8c', 'CP' ),
			'intuit'              => array( '#237a17', 'In' ),
			'payfast_thirdparty'  => array( '#b3122a', 'PF' ),
			'redsys'              => array( '#a50d26', 'Rs' ),
			'skrill'              => array( '#862165', 'Sk' ),
			'cashfree'            => array( '#5a2ee0', 'CF' ),
			'payfort'             => array( '#00739e', 'Pf' ),
		);
		$brand = isset( $brands[ $key ] ) ? $brands[ $key ] : array( '', strtoupper( substr( $gw['label'], 0, 1 ) ) );
		echo '<span class="ecpay-logo"' . ( '' !== $brand[0] ? ' style="background:' . esc_attr( $brand[0] ) . ';color:#fff"' : '' ) . ' aria-hidden="true">' . esc_html( $brand[1] ) . '</span>';
	}

	private static function state_chip( $s, $gw ) {
		switch ( $s['state'] ) {
			case 'locked':
				return array( 'class' => 'ecv2-chip-red', /* translators: %s: plan name, Pro or Premium ( Pro/Premium when no license is known ). */ 'label' => sprintf( __( '%s license needed', 'wp-easycart' ), self::plan_name() ) );
			case 'update':
				return array( 'class' => 'ecv2-chip-amber', 'label' => __( 'Needs a newer WP EasyCart PRO', 'wp-easycart' ) );
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
		/* 6.0.3: a store that just connected Square is one click from bringing its Square items in. */
		$link = ( 'square-connected' === $success ) ? ' <a href="' . esc_url( admin_url( 'admin.php?page=wp-easycart-products&subpage=import&source=square' ) ) . '">' . esc_html__( 'Bring your Square items into your store', 'wp-easycart' ) . '</a>' : '';
		echo '<div class="ecpay-flash is-' . esc_attr( $kind ) . '">' . esc_html( $text ) . $link . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $link is built from escaped parts.
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
		if ( self::needs_update( $gw ) && $on ) {
			return new WP_Error( 'ecpay_update', self::update_message( $gw ) );
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
	 * @since 6.0.3 detail: the line under the gateway name on the page ( its account and main choices ).
	 * @return array enabled, test, connected, state, state_label, state_class, mode_label, detail, can_enable, swap ( turning it on replaces the slot's gateway )
	 */
	public static function drawer_state( $key ) {
		$gw = self::gateway( $key );
		$s  = $gw ? self::status( $key, true ) : false;
		if ( ! $gw || ! $s ) {
			return array( 'enabled' => false, 'test' => false, 'connected' => false, 'state' => '', 'state_label' => '', 'state_class' => '', 'mode_label' => '', 'detail' => '', 'can_enable' => false, 'swap' => false );
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
			'detail'      => ( 'connected' === $s['state'] ) ? self::line_detail( $key, $s, $gw ) : '',
			'can_enable'  => ! $s['enabled'] && ! $s['locked'] && ! $s['update'] && in_array( $gw['role'], array( 'live', 'third_party', 'wallet' ), true ),
			'swap'        => ! $s['enabled'] && in_array( $gw['role'], array( 'live', 'third_party' ), true ) && '' !== self::active( $gw['role'] ),
		);
	}

	/**
	 * The two page sections ( the lines and the gateway list ) rendered fresh, for replacing them in place.
	 *
	 * @since 6.0.3 subs: the "Subscriptions need Stripe" row, which follows the card gateway.
	 */
	public static function sections_html() {
		self::$status = array();
		ob_start();
		self::render_active( array(), array() );
		$active = ob_get_clean();
		ob_start();
		self::render_more( array(), array() );
		$more = ob_get_clean();
		if ( ! function_exists( 'ecst_payment_render_subscriptions' ) && class_exists( 'wp_easycart_admin_settings_registry' ) ) {
			wp_easycart_admin_settings_registry::pages(); /* loads the declarations, Settings › Payment with its subscriptions row among them */
		}
		ob_start();
		if ( function_exists( 'ecst_payment_render_subscriptions' ) ) {
			ecst_payment_render_subscriptions();
		}
		$subs = ob_get_clean();
		return array(
			'active' => $active,
			'more'   => $more,
			'subs'   => $subs,
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
		if ( self::needs_update( $gw ) ) {
			wp_send_json_error( array( 'message' => self::update_message( $gw ) ) );
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
			<?php /* 6.0.2: Bill later for chosen customer roles only ( ec_option_manual_payment_roles ) */ ?>
			<?php $manual_roles = self::manual_roles(); ?>
			<div class="ecdv2-field ecdv2-field-full">
				<span class="ecdv2-label" id="ecpay_manual_roles_label"><?php esc_html_e( 'Offer it to', 'wp-easycart' ); ?></span>
				<div class="ecst-pills ecst-multi ecpay-manual-roles" id="ecpay_manual_roles" role="group" aria-labelledby="ecpay_manual_roles_label">
					<?php foreach ( self::manual_role_options() as $role_value => $role_label ) : ?>
						<?php $role_on = in_array( (string) $role_value, $manual_roles, true ); ?>
						<label class="ecst-pill<?php echo $role_on ? ' is-on' : ''; ?>"><input type="checkbox" class="ecpay-manual-role" value="<?php echo esc_attr( $role_value ); ?>"<?php checked( $role_on ); ?> /><?php echo esc_html( $role_label ); ?></label>
					<?php endforeach; ?>
				</div>
				<span class="ecdv2-field-desc"><?php esc_html_e( 'Leave every role unticked to offer Bill later to everyone. Tick roles, such as wholesale, to offer it only to them. Guest checkout covers shoppers who are not signed in.', 'wp-easycart' ); ?></span>
			</div>
		</div>
		<?php
		$html = ob_get_clean();
		wp_send_json_success( array(
			'html'  => $html,
			'title' => __( 'Bill later wording', 'wp-easycart' ),
			'role'  => __( 'Bill later', 'wp-easycart' ),
			'docs'  => $gw ? self::docs_url( $gw ) : '',
			'state' => self::drawer_state( 'manual' ),
		) );
	}

	/**
	 * POST title, message, roles → saves the Bill later wording.
	 *
	 * @since 6.0.3 enable=1 also turns Bill later on ( Save and turn on ); answers with the redrawn sections instead of a reload.
	 */
	public static function ajax_manual_save() {
		ecv2_settings_guard();
		$title   = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		/* 6.0.2: the roles it is offered to; roles_sent tells none ticked ( everyone ) from an older script that sends none */
		$roles   = null;
		if ( ! empty( $_POST['roles_sent'] ) ) {
			$roles = isset( $_POST['roles'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['roles'] ) ) : array();
		}
		$result  = self::save_manual( $title, $message, $roles );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		if ( isset( $_POST['enable'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['enable'] ) ) && ! get_option( 'ec_option_use_direct_deposit' ) ) {
			$turned = self::set_enabled( 'manual', true );
			if ( ! is_wp_error( $turned ) ) {
				$result = __( 'Bill later wording saved and turned on.', 'wp-easycart' );
			}
		}
		self::$status = array();
		wp_send_json_success( array( 'message' => $result, 'state' => self::drawer_state( 'manual' ), 'sections' => self::sections_html() ) );
	}

	/**
	 * POST gateway, on=1|0 ( or mode=live|test ) → switches the gateway.
	 *
	 * @since 6.0.3 Answers with the redrawn sections; the page no longer reloads.
	 */
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
		wp_send_json_success( array( 'message' => $result, 'sections' => self::sections_html() ) );
	}

	/**
	 * POST → every selected gateway in sandbox / test mode goes live where a live account exists ( the page's test-mode line ).
	 *
	 * @since 6.0.3 Replaces the declared section action, so the page has one test-mode control.
	 */
	public static function ajax_test_off() {
		ecv2_settings_guard();
		$result = self::action_test_mode_off();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( array(
			'message'  => is_string( $result ) ? $result : __( 'Every gateway is taking live payments.', 'wp-easycart' ),
			'sections' => self::sections_html(),
		) );
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
add_action( 'wp_ajax_ecv2_payment_test_off', array( 'wp_easycart_admin_payment_v2', 'ajax_test_off' ) );

endif;
