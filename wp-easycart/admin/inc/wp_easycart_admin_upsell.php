<?php
/**
 * WP EasyCart — context-aware PRO / Premium upsell.
 *
 * One catalog drives every locked surface in the free admin:
 *
 *  - show_upgrade( $context )   → locked-page RECREATION: real page header,
 *                                 feature strip, and a mock of the live UI
 *                                 ( see preview_* ) under a soft lock
 *  - load_upsell_popup()        → the modal ( upgrade-screen.php, compact )
 *  - print_feature_strip()      → the clickable feature grid, reused by
 *                                 pages that are only partly locked
 *                                 ( Cart Links )
 *  - ecdv2_upsell( { feature } )→ JS entry point every locked control calls
 *
 * Each context's `features` are keyed; the key is what a locked control
 * passes so the popup can highlight exactly what the user just clicked.
 *
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class wp_easycart_admin_upsell {

	const PRICING_URL = 'https://www.wpeasycart.com/wordpress-shopping-cart-pricing/';

	private static $hook_map = array(
		'wp_easycart_admin_offers_hub'                 => 'offers',
		'wp_easycart_admin_promotion_list'             => 'offers',
		'wp_easycart_admin_promotion_details'          => 'offers',
		'wp_easycart_admin_coupon_list'                => 'coupons',
		'wp_easycart_admin_coupon_details'             => 'coupons',
		'wp_easycart_admin_giftcard_list'              => 'giftcards',
		'wp_easycart_admin_giftcard_details'           => 'giftcards',
		'wp_easycart_admin_abandon_cart_load'          => 'abandoned_cart',
		'wp_easycart_admin_subscription_plans_list'    => 'subscriptions',
		'wp_easycart_admin_subscription_plans_details' => 'subscriptions',
		'wp_easycart_admin_subscriptions_list'         => 'subscriptions',
		'wp_easycart_admin_subscriptions_details'      => 'subscriptions',
		'wp_easycart_admin_downloads_list'             => 'downloads',
		'wp_easycart_admin_downloads_details'          => 'downloads',
		'wp_easycart_admin_fee_list'                   => 'fees',
		'wp_easycart_admin_fee_details'                => 'fees',
		'wp_easycart_admin_schedule_list'              => 'schedules',
		'wp_easycart_admin_schedule_details'           => 'schedules',
		'wp_easycart_admin_location_list'              => 'locations',
		'wp_easycart_admin_location_details'           => 'locations',
	);

	private static $stats = null;

	/* ------------------------------------------------------------------ */
	/* License renewal state ( paid licenses only, never trials )          */
	/* ------------------------------------------------------------------ */

	/**
	 * Days until support & updates end, a tone bucket and the renew URL.
	 * tone: 'ok' ( >30 days ), 'soon' ( 8–30 ), 'critical' ( 1–7 ), 'lapsed' ( <=0 ).
	 *
	 * @return array|null null when no paid license is registered.
	 */
	public static function renewal() {
		if ( ! function_exists( 'wp_easycart_admin_license' ) ) {
			return null;
		}
		$ld = wp_easycart_admin_license()->license_data;
		if ( ! $ld || ! empty( $ld->is_trial ) || empty( $ld->support_end_date ) ) {
			return null;
		}
		$end   = strtotime( $ld->support_end_date );
		$days  = self::days_until( $end );
		$model = strtolower( trim( (string) ( isset( $ld->model_number ) ? $ld->model_number : '' ) ) );
		$prem  = class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::is_premium() : ( 'ec410' === $model );
		$info  = get_option( 'wp_easycart_license_info' );
		$key   = ( is_array( $info ) && isset( $info['transaction_key'] ) ) ? $info['transaction_key'] : '';
		$url   = $prem ? 'https://www.wpeasycart.com/products/wp-easycart-premium-support-extensions/' : 'https://www.wpeasycart.com/products/wp-easycart-professional-support-upgrades/';
		if ( '' !== $key ) {
			$url .= '?transaction_key=' . rawurlencode( $key );
		}
		$tone = 'ok';
		if ( $days <= 0 ) {
			$tone = 'lapsed';
		} else if ( $days <= 7 ) {
			$tone = 'critical';
		} else if ( $days <= 30 ) {
			$tone = 'soon';
		}
		return array(
			'days'    => $days,
			'end'     => $end,
			'end_fmt' => date_i18n( get_option( 'date_format' ), $end ),
			'premium' => $prem,
			'tone'    => $tone,
			'url'     => $url,
			'edition' => $prem ? __( 'Premium', 'wp-easycart' ) : __( 'Pro', 'wp-easycart' ),
		);
	}

	/**
	 * Nonce'd URL that deactivates the PRO plugin ( "switch to the free edition" ).
	 * Empty string when PRO is not active or the user cannot manage plugins.
	 */
	public static function pro_deactivate_url() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return '';
		}
		$file = defined( 'WP_EASYCART_ADMIN_PRO_PLUGIN_FILE' ) ? plugin_basename( WP_EASYCART_ADMIN_PRO_PLUGIN_FILE ) : 'wp-easycart-pro/wp-easycart-admin-pro.php';
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! is_plugin_active( $file ) ) {
			return '';
		}
		return wp_nonce_url( self_admin_url( 'plugins.php?action=deactivate&plugin=' . rawurlencode( $file ) . '&plugin_status=all&paged=1&s=' ), 'deactivate-plugin_' . $file );
	}

	/**
	 * Whole calendar days from today until a timestamp ( date-to-date, ignoring time of day ).
	 * Every screen must use this so "days left" agrees everywhere.
	 */
	public static function days_until( $ts ) {
		$today = strtotime( date( 'Y-m-d' ) );
		$day   = strtotime( date( 'Y-m-d', (int) $ts ) );
		return (int) round( ( $day - $today ) / DAY_IN_SECONDS );
	}

	/**
	 * What lapsing costs this store, as short strings — the most concrete ones first.
	 * Uses live counts where they exist so the list reads as "your store", not a brochure.
	 */
	public static function renewal_stakes( $r = null ) {
		$r = $r ? $r : self::renewal();
		$s = self::stats();
		$n = function( $v ) { return number_format_i18n( (int) $v ); };
		$lapsed = ( $r && 'lapsed' === $r['tone'] );
		$plan   = ( $r && $r['premium'] ) ? __( 'Premium', 'wp-easycart' ) : __( 'Pro', 'wp-easycart' );
		$out = array();
		if ( ! empty( $s['pro_products'] ) ) {
			$out[] = $lapsed
				/* translators: %s: number of products. */
				? sprintf( _n( '%s subscription, download, gift card or donation product is not selling', '%s subscription, download, gift card and donation products are not selling', $s['pro_products'], 'wp-easycart' ), $n( $s['pro_products'] ) )
				/* translators: %s: number of products. */
				: sprintf( _n( '%s subscription, download, gift card or donation product stops selling', '%s subscription, download, gift card and donation products stop selling', $s['pro_products'], 'wp-easycart' ), $n( $s['pro_products'] ) );
		}
		if ( ! empty( $s['active_offers'] ) ) {
			$out[] = $lapsed
				? sprintf( _n( '%s active promotion is switched off at checkout', '%s active promotions are switched off at checkout', $s['active_offers'], 'wp-easycart' ), $n( $s['active_offers'] ) )
				: sprintf( _n( '%s active promotion switches off at checkout', '%s active promotions switch off at checkout', $s['active_offers'], 'wp-easycart' ), $n( $s['active_offers'] ) );
		}
		if ( ! empty( $s['orders_30d'] ) && $s['orders_30d'] >= 3 && ! empty( $s['avg_order'] ) ) {
			$out[] = $lapsed
				? sprintf( __( 'the 2%% gateway fee is being charged — about %s a month at your current volume', 'wp-easycart' ), self::money( $s['orders_30d'] * $s['avg_order'] * 0.02 ) )
				: sprintf( __( 'the 2%% gateway fee returns — about %s a month at your current volume', 'wp-easycart' ), self::money( $s['orders_30d'] * $s['avg_order'] * 0.02 ) );
		} else {
			$out[] = $lapsed ? __( 'the 2% gateway fee is being charged on Stripe, Square and PayPal payments', 'wp-easycart' ) : __( 'the 2% gateway fee returns on Stripe, Square and PayPal payments', 'wp-easycart' );
		}
		/* translators: %s: plan name, Pro or Premium. */
		$out[] = $lapsed ? sprintf( __( 'every %s admin panel is locked ( data is kept, not editable )', 'wp-easycart' ), $plan ) : sprintf( __( 'every %s admin panel locks ( data is kept, not editable )', 'wp-easycart' ), $plan );
		$out[] = $lapsed ? __( 'security fixes, updates and priority support are not being delivered', 'wp-easycart' ) : __( 'security fixes, updates and priority support stop', 'wp-easycart' );
		if ( $r && $r['premium'] ) {
			$out[] = $lapsed ? __( 'Premium extensions ( ShipStation, QuickBooks, MailChimp, apps ) are not syncing', 'wp-easycart' ) : __( 'Premium extensions ( ShipStation, QuickBooks, MailChimp, apps ) stop syncing', 'wp-easycart' );
		}
		return $out;
	}

	/**
	 * Should the lapsed-license gate auto-open on this request? Key working pages only,
	 * once per user per day, and never while the license is current.
	 */
	public static function renewal_gate_due( $r = null ) {
		$r = $r ? $r : self::renewal();
		if ( ! $r || 'lapsed' !== $r['tone'] ) {
			return false;
		}
		$page = isset( $_GET['page'] ) ? $_GET['page'] : '';
		$sub  = isset( $_GET['subpage'] ) ? $_GET['subpage'] : '';
		$key_pages = array( 'wp-easycart-products', 'wp-easycart-orders', 'wp-easycart-users', 'wp-easycart-dashboard', 'wp-easycart-status' );
		if ( ! in_array( $page, $key_pages, true ) ) {
			return false;
		}
		if ( 'wp-easycart-products' === $page && '' !== $sub && 'products' !== $sub ) {
			return false;
		}
		return ( (int) get_user_meta( get_current_user_id(), 'wpec_renewal_gate_snooze', true ) <= time() );
	}

	/* ------------------------------------------------------------------ */
	/* Store stats                                                         */
	/* ------------------------------------------------------------------ */

	/**
	 * Store-wide counts behind the upsell copy. Eleven aggregates, so the array is cached 12 hours in the
	 * wpec_upsell_stats transient ( @since 6.0.0 ), stamped with wp_easycart_admin::order_fingerprint() so a
	 * new order from any code path recomputes it; flush_order_caches() drops it on admin-side order changes.
	 * The 30 / 90 day figures are bounded on the indexed order_date / last_changed_date columns.
	 * Returned keys are unchanged.
	 */
	public static function stats() {
		if ( null !== self::$stats ) {
			return self::$stats;
		}
		$fingerprint = ( class_exists( 'wp_easycart_admin' ) && method_exists( 'wp_easycart_admin', 'order_fingerprint' ) ) ? wp_easycart_admin::order_fingerprint() : '';
		$cached      = get_transient( 'wpec_upsell_stats' );
		if ( is_array( $cached ) && isset( $cached['fp'], $cached['stats'] ) && $cached['fp'] === $fingerprint && is_array( $cached['stats'] ) && isset( $cached['stats']['avg_order'] ) ) {
			self::$stats = apply_filters( 'wp_easycart_upsell_stats', $cached['stats'] );
			return self::$stats;
		}
		global $wpdb;
		$s = array(
			'orders'        => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_order' ),
			'orders_30d'    => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_order WHERE order_date > DATE_SUB( NOW(), INTERVAL 30 DAY )' ),
			'products'      => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_product WHERE activate_in_store = 1' ),
			'customers'     => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_user' ),
			'abandoned_30d' => (int) $wpdb->get_var( 'SELECT COUNT( DISTINCT session_id ) FROM ec_tempcart WHERE last_changed_date > DATE_SUB( NOW(), INTERVAL 30 DAY )' ),
			'downloads'     => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_product WHERE is_download = 1' ),
			'tracked'       => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_product WHERE activate_in_store = 1 AND ( show_stock_quantity = 1 OR use_optionitem_quantity_tracking = 1 )' ),
			'pro_products'  => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_product WHERE activate_in_store = 1 AND ( is_subscription_item = 1 OR is_download = 1 OR is_giftcard = 1 OR is_donation = 1 )' ),
			'active_offers' => (int) $wpdb->get_var( "SHOW TABLES LIKE 'ec_offer'" ) ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM ec_offer WHERE offer_status = 'active'" ) : 0,
			'low_stock'     => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_product WHERE activate_in_store = 1 AND show_stock_quantity = 1 AND use_optionitem_quantity_tracking = 0 AND stock_quantity <= 10' ),
			'avg_order'     => (float) $wpdb->get_var( 'SELECT AVG( grand_total ) FROM ec_order WHERE order_date > DATE_SUB( NOW(), INTERVAL 90 DAY )' ),
		);
		set_transient( 'wpec_upsell_stats', array( 'fp' => $fingerprint, 'stats' => $s ), 12 * HOUR_IN_SECONDS );
		self::$stats = apply_filters( 'wp_easycart_upsell_stats', $s );
		return self::$stats;
	}

	public static function money( $v ) {
		return isset( $GLOBALS['currency'] ) ? $GLOBALS['currency']->get_currency_display( (float) $v ) : '$' . number_format( (float) $v, 2 );
	}

	/** Personalized one-liner; '' when the store has no meaningful numbers. */
	private static function stat_line( $context ) {
		$s    = self::stats();
		$n    = 'number_format_i18n';
		$plan = self::plan_label();
		switch ( $context ) {
			case 'offers':
			case 'coupons':
				if ( $s['orders_30d'] >= 5 ) {
					return sprintf( __( 'You took %s orders in the last 30 days. A single "spend %s more, get free shipping" offer typically lifts average order value 10–20%%.', 'wp-easycart' ), $n( $s['orders_30d'] ), self::money( max( 10, round( $s['avg_order'] * 0.25, -1 ) ) ) );
				}
				if ( $s['products'] >= 3 ) {
					return sprintf( __( 'With %s products in your catalog, bundle and buy-one-get-one offers are ready to run the moment you upgrade.', 'wp-easycart' ), $n( $s['products'] ) );
				}
				break;
			case 'abandoned_cart':
				if ( $s['abandoned_30d'] >= 3 ) {
					$rec = max( 1, round( $s['abandoned_30d'] * 0.1 ) );
					$val = $s['avg_order'] > 0 ? ' ' . sprintf( __( '— roughly %s in sales.', 'wp-easycart' ), self::money( $rec * $s['avg_order'] ) ) : '.';
					return sprintf( __( '%s shoppers left items in their cart in the last 30 days. Recovering even 1 in 10 means %s extra orders%s', 'wp-easycart' ), $n( $s['abandoned_30d'] ), $n( $rec ), $val );
				}
				break;
			case 'giftcards':
				if ( $s['customers'] >= 10 ) {
					return sprintf( __( 'You have %s customers. Gift cards let each of them bring you a new one — and the balance is paid up front.', 'wp-easycart' ), $n( $s['customers'] ) );
				}
				break;
			case 'subscriptions':
				if ( $s['orders'] >= 10 ) {
					return sprintf( __( '%s one-time orders so far. Turning your best sellers into subscriptions makes next month\'s revenue predictable.', 'wp-easycart' ), $n( $s['orders'] ) );
				}
				break;
			case 'downloads':
				if ( $s['downloads'] > 0 ) {
					/* translators: 1: number of products, 2: plan name, Pro or Premium. */
					return sprintf( __( 'You already sell %1$s downloadable products. %2$s adds download limits, expiry, and per-order delivery tracking.', 'wp-easycart' ), $n( $s['downloads'] ), $plan );
				}
				break;
			case 'products':
				if ( $s['products'] >= 3 ) {
					return sprintf( __( 'You have %s active products. Variant tracking and tiered pricing apply to any of them the moment you upgrade.', 'wp-easycart' ), $n( $s['products'] ) );
				}
				break;
			case 'reports':
				if ( $s['abandoned_30d'] >= 3 ) {
					/* translators: 1: number of carts, 2: plan name, Pro or Premium. */
					return sprintf( __( '%1$s carts were abandoned in the last 30 days. The free reports count them; %2$s recovers them and shows what came back.', 'wp-easycart' ), $n( $s['abandoned_30d'] ), $plan );
				}
				if ( $s['orders_30d'] >= 5 ) {
					/* translators: 1: number of orders, 2: plan name, Pro or Premium. */
					return sprintf( __( '%1$s orders in the last 30 days. %2$s tells you which products, customers and codes drove them.', 'wp-easycart' ), $n( $s['orders_30d'] ), $plan );
				}
				break;
			case 'orders':
				if ( $s['orders_30d'] >= 5 ) {
					/* translators: 1: number of orders, 2: plan name, Pro or Premium. */
					return sprintf( __( '%1$s orders in the last 30 days. With %2$s, every address fix, size swap or partial refund on those is a drawer away instead of a new order.', 'wp-easycart' ), $n( $s['orders_30d'] ), $plan );
				}
				break;
			case 'inventory':
				if ( $s['low_stock'] >= 1 ) {
					/* translators: 1: number of products, 2: plan name, Pro or Premium. */
					return sprintf( _n( '%1$s product is running low right now. %2$s emails you before it sells out and records every change so you know where the stock went.', '%1$s products are running low right now. %2$s emails you before they sell out and records every change so you know where the stock went.', $s['low_stock'], 'wp-easycart' ), $n( $s['low_stock'] ), $plan );
				}
				if ( $s['tracked'] >= 1 ) {
					/* translators: 1: number of products, 2: plan name, Pro or Premium. */
					return sprintf( __( 'You track stock on %1$s products. %2$s adds a reason and a timestamp to every adjustment, reorder alerts, and CSV import.', 'wp-easycart' ), $n( $s['tracked'] ), $plan );
				}
				break;
			case 'cart_links':
				if ( $s['products'] >= 1 ) {
					/* translators: 1: number of products, 2: plan name, Pro or Premium. */
					return sprintf( __( 'Any of your %1$s products can become a one-click cart link — %2$s lets you attach a discount and a QR code to it.', 'wp-easycart' ), $n( $s['products'] ), $plan );
				}
				break;
		}
		if ( 0 === strpos( $context, 'shipping_' ) && $s['orders_30d'] >= 5 ) {
			return sprintf( __( 'You shipped %s orders last month. Live carrier rates end the guesswork on every one of them.', 'wp-easycart' ), $n( $s['orders_30d'] ) );
		}
		return '';
	}

	/* ------------------------------------------------------------------ */
	/* Catalog                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * The plan name for upsell copy: 'Pro/Premium' on the free edition, else the store's own plan.
	 *
	 * @since 6.0.0
	 */
	public static function plan_label() {
		return class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::plan_name() : __( 'Pro/Premium', 'wp-easycart' );
	}

	/**
	 * Badge text for a catalog entry's plan ( 'pro' or 'premium' ).
	 *
	 * @since 6.0.0
	 */
	public static function plan_badge( $plan = 'pro' ) {
		if ( class_exists( 'wp_easycart_admin_edition' ) ) {
			return wp_easycart_admin_edition::badge( $plan );
		}
		return ( 'premium' === $plan ) ? __( 'Premium', 'wp-easycart' ) : __( 'Pro/Premium', 'wp-easycart' );
	}

	private static function f( $icon, $title, $desc ) {
		return array( 'icon' => $icon, 'title' => $title, 'desc' => $desc );
	}

	public static function catalog() {
		static $catalog = null;
		if ( null !== $catalog ) {
			return $catalog;
		}
		$plan = self::plan_label();
		$catalog = array(
			'default' => array(
				'title'    => $plan,
				'headline' => __( 'Unlock the full WP EasyCart', 'wp-easycart' ),
				'lede'     => __( 'Everything you need to sell more is already built — it just needs a license.', 'wp-easycart' ),
				'plan'     => 'pro',
				'features' => array(
					'marketing' => self::f( 'dashicons-megaphone', __( 'Marketing suite', 'wp-easycart' ), __( 'Offers, coupons, gift cards, and abandoned-cart recovery.', 'wp-easycart' ) ),
					'selling'   => self::f( 'dashicons-cart', __( 'More ways to sell', 'wp-easycart' ), __( 'Subscriptions, downloads, pickup scheduling, multi-location.', 'wp-easycart' ) ),
					'shipping'  => self::f( 'dashicons-airplane', __( 'Live shipping & gateways', 'wp-easycart' ), __( 'UPS, USPS, FedEx, DHL rates and 20+ payment providers.', 'wp-easycart' ) ),
					'support'   => self::f( 'dashicons-businessperson', __( 'Priority support', 'wp-easycart' ), __( 'From the people who build the plugin, usually within hours.', 'wp-easycart' ) ),
				),
			),
			'offers' => array(
				'title'     => __( 'Offers', 'wp-easycart' ),
				'headline'  => __( 'Run promotions that actually move product', 'wp-easycart' ),
				/* translators: %s: plan name, Pro/Premium, Pro or Premium. */
				'lede'      => sprintf( __( 'Offers are the %s promotions engine: discount rules, codes, and scheduling in one place — with revenue tracked per offer.', 'wp-easycart' ), $plan ),
				'plan'      => 'pro',
				'icon'      => 'dashicons-megaphone',
				'new_label' => __( 'New Offer', 'wp-easycart' ),
				'docs'      => 'https://docs.wpeasycart.com/wp-easycart-administrative-console-guide/?section=offers',
				'features'  => array(
					'types'     => self::f( 'dashicons-tag', __( 'Every discount type', 'wp-easycart' ), __( 'Percent, fixed amount, buy-one-get-one, free shipping, and spend-threshold offers.', 'wp-easycart' ) ),
					'targeting' => self::f( 'dashicons-filter', __( 'Precise targeting', 'wp-easycart' ), __( 'Limit an offer to specific products, categories, or customer groups.', 'wp-easycart' ) ),
					'codes'     => self::f( 'dashicons-admin-network', __( 'Codes or automatic', 'wp-easycart' ), __( 'Attach one or many codes, or apply the offer automatically with no code at all.', 'wp-easycart' ) ),
					'schedule'  => self::f( 'dashicons-calendar-alt', __( 'Scheduling & limits', 'wp-easycart' ), __( 'Set start and end dates; cap total redemptions or uses per customer.', 'wp-easycart' ) ),
					'stacking'  => self::f( 'dashicons-editor-ol', __( 'Stacking rules', 'wp-easycart' ), __( 'Decide which offers combine and which are exclusive — no accidental double discounts.', 'wp-easycart' ) ),
					'reporting' => self::f( 'dashicons-chart-bar', __( 'Revenue per offer', 'wp-easycart' ), __( 'See uses, discount given, and orders driven for each offer in the list.', 'wp-easycart' ) ),
				),
			),
			'coupons' => array(
				'title'    => __( 'Coupons', 'wp-easycart' ),
				'headline' => __( 'Coupon codes your customers will actually use', 'wp-easycart' ),
				'lede'     => __( 'Create, track, and expire codes without touching a spreadsheet.', 'wp-easycart' ),
				'plan'     => 'pro',
				'icon'     => 'dashicons-tickets-alt',
				'features' => array(
					'types'    => self::f( 'dashicons-tag', __( 'Percent or fixed', 'wp-easycart' ), __( 'With minimum spend and expiry dates.', 'wp-easycart' ) ),
					'restrict' => self::f( 'dashicons-filter', __( 'Restrictions', 'wp-easycart' ), __( 'Limit to products, categories, or first-time buyers.', 'wp-easycart' ) ),
					'tracking' => self::f( 'dashicons-chart-bar', __( 'Redemption tracking', 'wp-easycart' ), __( 'Uses and revenue per code.', 'wp-easycart' ) ),
				),
			),
			'giftcards' => array(
				'title'    => __( 'Gift Cards', 'wp-easycart' ),
				'headline' => __( 'Sell gift cards and get paid before you ship', 'wp-easycart' ),
				'lede'     => __( 'Physical or emailed, fixed or custom amount — balances tracked for you.', 'wp-easycart' ),
				'plan'     => 'pro',
				'icon'     => 'dashicons-tickets',
				'features' => array(
					'sell'    => self::f( 'dashicons-cart', __( 'Sell in your store', 'wp-easycart' ), __( 'Customers buy a card; the recipient redeems it at checkout.', 'wp-easycart' ) ),
					'balance' => self::f( 'dashicons-money-alt', __( 'Running balances', 'wp-easycart' ), __( 'Partial redemption across multiple orders.', 'wp-easycart' ) ),
					'manual'  => self::f( 'dashicons-edit', __( 'Issue manually', 'wp-easycart' ), __( 'Store credit for refunds, prizes, or goodwill.', 'wp-easycart' ) ),
				),
			),
			'abandoned_cart' => array(
				'title'    => __( 'Abandoned Cart', 'wp-easycart' ),
				'headline' => __( 'Win back the carts you are already losing', 'wp-easycart' ),
				'lede'     => __( 'Automatic reminder emails to shoppers who added to cart and left.', 'wp-easycart' ),
				'plan'     => 'pro',
				'icon'     => 'dashicons-email-alt',
				'features' => array(
					'sequence' => self::f( 'dashicons-clock', __( 'Timed sequence', 'wp-easycart' ), __( 'e.g. 1 hour, 1 day, 3 days after the cart goes cold.', 'wp-easycart' ) ),
					'restore'  => self::f( 'dashicons-undo', __( 'One-click return', 'wp-easycart' ), __( 'The email link restores the exact cart.', 'wp-easycart' ) ),
					'discount' => self::f( 'dashicons-tag', __( 'Optional discount', 'wp-easycart' ), __( 'Sweeten the final reminder with a code.', 'wp-easycart' ) ),
					'report'   => self::f( 'dashicons-chart-line', __( 'Recovery report', 'wp-easycart' ), __( 'See what came back and what it was worth.', 'wp-easycart' ) ),
				),
			),
			'subscriptions' => array(
				'title'    => __( 'Subscriptions', 'wp-easycart' ),
				'headline' => __( 'Recurring revenue, on autopilot', 'wp-easycart' ),
				'lede'     => __( 'Sell any product on a weekly, monthly, or yearly plan.', 'wp-easycart' ),
				'plan'     => 'pro',
				'icon'     => 'dashicons-update',
				'features' => array(
					'rebill' => self::f( 'dashicons-update', __( 'Automatic rebilling', 'wp-easycart' ), __( 'Through Stripe, Authorize.net, or PayPal.', 'wp-easycart' ) ),
					'self'   => self::f( 'dashicons-admin-users', __( 'Customer self-service', 'wp-easycart' ), __( 'Manage, pause, or cancel from their account.', 'wp-easycart' ) ),
					'trial'  => self::f( 'dashicons-clock', __( 'Trials & sign-up fees', 'wp-easycart' ), __( 'Free trials and one-time setup charges.', 'wp-easycart' ) ),
				),
			),
			'downloads' => array(
				'title'    => __( 'Downloads', 'wp-easycart' ),
				'headline' => __( 'Sell digital goods with real delivery control', 'wp-easycart' ),
				'lede'     => __( 'Files, licenses, and courses — delivered securely after payment.', 'wp-easycart' ),
				'plan'     => 'pro',
				'icon'     => 'dashicons-download',
				'features' => array(
					'limits' => self::f( 'dashicons-lock', __( 'Limits & expiry', 'wp-easycart' ), __( 'Download counts and link lifetime per product.', 'wp-easycart' ) ),
					'log'    => self::f( 'dashicons-list-view', __( 'Download log', 'wp-easycart' ), __( 'Per-order record of every delivery.', 'wp-easycart' ) ),
					'large'  => self::f( 'dashicons-cloud', __( 'Large files', 'wp-easycart' ), __( 'Protected storage for big downloads.', 'wp-easycart' ) ),
				),
			),
			'fees' => array(
				'title'     => __( 'Flex-Fees', 'wp-easycart' ),
				'headline'  => __( 'Charge the fees your business needs', 'wp-easycart' ),
				'lede'      => __( 'Handling, card-processing or regional fees, or a discount, added as its own line at checkout.', 'wp-easycart' ),
				'plan'      => 'pro',
				'icon'      => 'dashicons-money-alt',
				'new_label' => __( 'Add fee', 'wp-easycart' ),
				'docs'      => 'https://docs.wpeasycart.com/docs/administrative-console-guide/flex-fee-settings/',
				'features'  => array(
					'amount'   => self::f( 'dashicons-chart-pie', __( 'Percentage or flat amount', 'wp-easycart' ), __( 'Cap a percentage with a minimum and maximum; a negative amount becomes a discount.', 'wp-easycart' ) ),
					'location' => self::f( 'dashicons-location', __( 'Charge by location', 'wp-easycart' ), __( 'Countries, states, cities, ZIP codes or shipping zones.', 'wp-easycart' ) ),
					'customer' => self::f( 'dashicons-groups', __( 'Charge by cart or customer', 'wp-easycart' ), __( 'Product categories and customer roles, such as a wholesale surcharge.', 'wp-easycart' ) ),
					'payment'  => self::f( 'dashicons-money', __( 'Charge by payment method', 'wp-easycart' ), __( 'Pass card, PayPal or pay-later costs on only to shoppers who use them.', 'wp-easycart' ) ),
					'checkout' => self::f( 'dashicons-cart', __( 'Its own checkout line', 'wp-easycart' ), __( 'Shown by name in the cart, on the order and in sales reports.', 'wp-easycart' ) ),
				),
			),
			'schedules' => array(
				'title'    => __( 'Schedules', 'wp-easycart' ),
				'headline' => __( 'Let customers pick a date and time', 'wp-easycart' ),
				'lede'     => __( 'Pickup slots, delivery windows, appointments, or rentals.', 'wp-easycart' ),
				'plan'     => 'pro',
				'icon'     => 'dashicons-calendar-alt',
				'features' => array(
					'capacity' => self::f( 'dashicons-groups', __( 'Capacity per slot', 'wp-easycart' ), __( 'Plus lead-time rules.', 'wp-easycart' ) ),
					'blackout' => self::f( 'dashicons-calendar', __( 'Blackouts & hours', 'wp-easycart' ), __( 'Holidays and business hours respected.', 'wp-easycart' ) ),
					'order'    => self::f( 'dashicons-clipboard', __( 'On the order', 'wp-easycart' ), __( 'Chosen slot on the order and packing slip.', 'wp-easycart' ) ),
				),
			),
			'locations' => array(
				'title'    => __( 'Locations', 'wp-easycart' ),
				'headline' => __( 'Offer local pickup from multiple locations', 'wp-easycart' ),
				'lede'     => __( 'Shoppers choose a store; you get the order tagged to it.', 'wp-easycart' ),
				'plan'     => 'pro',
				'icon'     => 'dashicons-location',
				'features' => array(
					'unlimited' => self::f( 'dashicons-store', __( 'Unlimited locations', 'wp-easycart' ), __( 'Each with hours and pickup instructions.', 'wp-easycart' ) ),
					'schedule'  => self::f( 'dashicons-calendar-alt', __( 'Per-location scheduling', 'wp-easycart' ), __( 'Pickup slots specific to each store.', 'wp-easycart' ) ),
				),
			),
			'cart_links' => array(
				'title'    => __( 'Cart Links', 'wp-easycart' ),
				'headline' => __( 'Do more with cart links', 'wp-easycart' ),
				'lede'     => __( 'Unlock the full toolkit for promotions, print, and tracking.', 'wp-easycart' ),
				'plan'     => 'pro',
				'icon'     => 'dashicons-admin-links',
				'features' => array(
					'multi_product' => self::f( 'dashicons-products', __( 'Multi-product links', 'wp-easycart' ), __( 'Bundle up to 20 products, each with its own quantity and options, into one link.', 'wp-easycart' ) ),
					'modifiers'     => self::f( 'dashicons-admin-settings', __( 'Advanced option preselects', 'wp-easycart' ), __( 'Pre-fill modifiers such as engraving text, add-ons, and swatches — not just basic variations.', 'wp-easycart' ) ),
					'codes'         => self::f( 'dashicons-tag', __( 'Auto-applied coupon & offer codes', 'wp-easycart' ), __( 'Attach codes to the link so the discount is already applied when the cart opens.', 'wp-easycart' ) ),
					'limits'        => self::f( 'dashicons-clock', __( 'Expiry dates & use limits', 'wp-easycart' ), __( 'Run time-boxed promotions or cap a link at a set number of redemptions.', 'wp-easycart' ) ),
					'qr'            => self::f( 'dashicons-smartphone', __( 'Print-ready QR codes', 'wp-easycart' ), __( 'Download a high-resolution QR code for packaging, flyers, and in-store signage.', 'wp-easycart' ) ),
					'conversions'   => self::f( 'dashicons-chart-bar', __( 'Conversion tracking', 'wp-easycart' ), __( 'See how many orders each link produced, right in the list.', 'wp-easycart' ) ),
				),
			),
			'products' => array(
				'title'    => __( 'Advanced Product Tools', 'wp-easycart' ),
				'headline' => __( 'Do more with every product', 'wp-easycart' ),
				'lede'     => __( 'Variant inventory, tiered and B2B pricing, and richer galleries — all managed from this list.', 'wp-easycart' ),
				'plan'     => 'pro',
				'icon'     => 'dashicons-products',
				'docs'     => 'https://docs.wpeasycart.com/wp-easycart-administrative-console-guide/?section=products',
				'features' => array(
					'variants'     => self::f( 'dashicons-randomize', __( 'Variant inventory & pricing', 'wp-easycart' ), __( 'Track stock and set a price per size, color, or any option combination.', 'wp-easycart' ) ),
					'modifiers'    => self::f( 'dashicons-admin-settings', __( 'Product modifiers', 'wp-easycart' ), __( 'Text, number, date and file inputs, add-on checkboxes, radios and quantity grids, each with its own price.', 'wp-easycart' ) ),
					'volume'       => self::f( 'dashicons-chart-bar', __( 'Volume pricing tiers', 'wp-easycart' ), __( 'Buy 10 save 5%, buy 50 save 15% — tiers shown on the product page.', 'wp-easycart' ) ),
					'b2b'          => self::f( 'dashicons-groups', __( 'B2B role pricing', 'wp-easycart' ), __( 'Wholesale and trade prices by customer role; require login to see them.', 'wp-easycart' ) ),
					'advanced'     => self::f( 'dashicons-tag', __( 'Advanced pricing display', 'wp-easycart' ), __( 'Price ranges, custom price labels, and login-to-view pricing.', 'wp-easycart' ) ),
					'images'       => self::f( 'dashicons-format-gallery', __( 'Image manager', 'wp-easycart' ), __( 'Media library galleries, per-option image sets, and hosted video.', 'wp-easycart' ) ),
					'stock_notify' => self::f( 'dashicons-bell', __( 'Back-in-stock alerts', 'wp-easycart' ), __( 'Let shoppers subscribe to a sold-out product and email them when it returns.', 'wp-easycart' ) ),
				),
			),
			'reports' => array(
				'title'    => __( 'Reporting', 'wp-easycart' ),
				'headline' => __( 'See what is selling, to whom, and what you are leaving behind', 'wp-easycart' ),
				/* translators: %s: plan name, Pro/Premium, Pro or Premium. */
				'lede'     => sprintf( __( 'The free reports show totals. %s breaks them down by product, customer and coupon, tracks recovered carts, and mails you a summary.', 'wp-easycart' ), $plan ),
				'plan'     => 'pro',
				'icon'     => 'dashicons-chart-line',
				'docs'     => 'https://docs.wpeasycart.com/wp-easycart-administrative-console-guide/?section=reports',
				'features' => array(
					'products'  => self::f( 'dashicons-products', __( 'Sales by product', 'wp-easycart' ), __( 'Units and revenue per product and per variation, ranked, for any date range.', 'wp-easycart' ) ),
					'customers' => self::f( 'dashicons-groups', __( 'Customer reports', 'wp-easycart' ), __( 'Top customers, first-time vs returning, and lifetime value.', 'wp-easycart' ) ),
					'coupons'   => self::f( 'dashicons-tickets-alt', __( 'Coupon & offer performance', 'wp-easycart' ), __( 'Uses, discount given and revenue driven for every code and promotion.', 'wp-easycart' ) ),
					'abandoned' => self::f( 'dashicons-cart', __( 'Abandoned-cart recovery', 'wp-easycart' ), __( 'How many carts were reminded, how many came back, and what they were worth.', 'wp-easycart' ) ),
					'digest'    => self::f( 'dashicons-email-alt', __( 'Scheduled email summaries', 'wp-easycart' ), __( 'A daily or weekly snapshot of sales, orders and low stock in your inbox.', 'wp-easycart' ) ),
					'export'    => self::f( 'dashicons-download', __( 'Detailed exports', 'wp-easycart' ), __( 'Line-item level CSVs for your accountant or spreadsheet.', 'wp-easycart' ) ),
				),
			),
			'orders' => array(
				'title'    => __( 'Order tools', 'wp-easycart' ),
				'headline' => __( 'Fix, refund and follow up without leaving the order', 'wp-easycart' ),
				'lede'     => __( 'Edit any part of an order, refund through your gateway, print labels, and see exactly what happened and when.', 'wp-easycart' ),
				'plan'     => 'pro',
				'icon'     => 'dashicons-clipboard',
				'docs'     => 'https://docs.wpeasycart.com/wp-easycart-administrative-console-guide/?section=orders',
				'features' => array(
					'edit_details' => self::f( 'dashicons-edit', __( 'Edit customer, addresses & date', 'wp-easycart' ), __( 'Fix a typo in the shipping address, change the email, or correct the order date — in a drawer, not a new order.', 'wp-easycart' ) ),
					'lines'        => self::f( 'dashicons-list-view', __( 'Add, edit & remove line items', 'wp-easycart' ), __( 'Swap a size, change a quantity or add a forgotten item; totals recalculate.', 'wp-easycart' ) ),
					'refunds'      => self::f( 'dashicons-undo', __( 'Refunds through your gateway', 'wp-easycart' ), __( 'Full or partial, per line or by amount, sent straight to Stripe, Square, PayPal or Authorize.net.', 'wp-easycart' ) ),
					'totals'       => self::f( 'dashicons-money-alt', __( 'Adjust totals', 'wp-easycart' ), __( 'Change shipping, tax or discounts on an existing order.', 'wp-easycart' ) ),
					'labels'       => self::f( 'dashicons-printer', __( 'Shipping labels', 'wp-easycart' ), __( 'Buy and print a label from the order; tracking fills in and the shopper is emailed.', 'wp-easycart' ) ),
					'history'      => self::f( 'dashicons-backup', __( 'Full order log', 'wp-easycart' ), __( 'Status changes, payments, refunds, emails sent and staff notes on one timeline.', 'wp-easycart' ) ),
					'emails'       => self::f( 'dashicons-email-alt', __( 'Custom emails & tags', 'wp-easycart' ), __( 'Write the shopper a one-off email from the order, and tag orders for your own workflow.', 'wp-easycart' ) ),
				),
			),
			'inventory' => array(
				'title'    => __( 'Inventory tools', 'wp-easycart' ),
				'headline' => __( 'Know where every unit went', 'wp-easycart' ),
				'lede'     => __( 'Adjust stock with a reason, see the full history, get warned before you sell out, and update hundreds of items at once.', 'wp-easycart' ),
				'plan'     => 'pro',
				'icon'     => 'dashicons-archive',
				'docs'     => 'https://docs.wpeasycart.com/wp-easycart-administrative-console-guide/?section=inventory',
				'features' => array(
					'adjust'   => self::f( 'dashicons-plus-alt', __( 'Adjust stock with a reason', 'wp-easycart' ), __( 'Received, damaged, counted, returned — add or remove units and say why, instead of overwriting the number.', 'wp-easycart' ) ),
					'history'  => self::f( 'dashicons-backup', __( 'Movement history', 'wp-easycart' ), __( 'Every sale, refund and adjustment on a timeline per product, with who did it and when.', 'wp-easycart' ) ),
					'reorder'  => self::f( 'dashicons-flag', __( 'Reorder points & low-stock digest', 'wp-easycart' ), __( 'Set a threshold per item and get a daily email listing what needs reordering.', 'wp-easycart' ) ),
					'bulk'     => self::f( 'dashicons-edit', __( 'Bulk update', 'wp-easycart' ), __( 'Select any number of rows and set, add or subtract quantity for all of them in one go.', 'wp-easycart' ) ),
					'import'   => self::f( 'dashicons-upload', __( 'CSV import', 'wp-easycart' ), __( 'Export, count in a spreadsheet, import — the fastest way to do a full stock take.', 'wp-easycart' ) ),
					'variants' => self::f( 'dashicons-randomize', __( 'Stock per variation', 'wp-easycart' ), __( 'Track Red / Small separately from Red / Large, on the product and in this list.', 'wp-easycart' ) ),
				),
			),
			'paypal_express' => array(
				'title'    => __( 'PayPal Express', 'wp-easycart' ),
				'headline' => __( 'Add PayPal Express to checkout', 'wp-easycart' ),
				'lede'     => __( 'One more way to pay means fewer shoppers bouncing at the last step.', 'wp-easycart' ),
				'plan'     => 'pro',
				'icon'     => 'dashicons-money',
				'docs'     => 'http://docs.wpeasycart.com/wp-easycart-administrative-console-guide/?section=paypal-express',
				'features' => array(
					'express'  => self::f( 'dashicons-money', __( 'PayPal Express buttons', 'wp-easycart' ), __( 'Fast checkout for PayPal account holders.', 'wp-easycart' ) ),
					'gateways' => self::f( 'dashicons-admin-plugins', __( '20+ gateways', 'wp-easycart' ), __( 'Stripe, Square, Authorize.net, and more.', 'wp-easycart' ) ),
				),
			),
		);

		foreach ( array( 'ups' => 'UPS', 'usps' => 'USPS', 'fedex' => 'FedEx', 'dhl' => 'DHL', 'canada_post' => 'Canada Post', 'australia_post' => 'Australia Post' ) as $key => $name ) {
			$catalog[ 'shipping_' . $key ] = array(
				'title'    => $name,
				'headline' => sprintf( __( 'Charge exact %s rates at checkout', 'wp-easycart' ), $name ),
				'lede'     => sprintf( __( 'Live %s quotes replace flat-rate guesswork — stop under- or over-charging for shipping.', 'wp-easycart' ), $name ),
				'plan'     => 'pro',
				'icon'     => 'dashicons-airplane',
				'docs'     => 'http://docs.wpeasycart.com/wp-easycart-administrative-console-guide/?section=shipping-settings',
				'features' => array(
					'live'     => self::f( 'dashicons-airplane', sprintf( __( 'Live %s rates', 'wp-easycart' ), $name ), __( 'Based on weight, dimensions, and destination.', 'wp-easycart' ) ),
					'services' => self::f( 'dashicons-editor-ul', __( 'Several service levels', 'wp-easycart' ), __( 'Ground, 2-day, overnight — shopper picks.', 'wp-easycart' ) ),
					'carriers' => self::f( 'dashicons-admin-site-alt3', __( 'All carriers', 'wp-easycart' ), __( 'UPS, USPS, FedEx, DHL, Canada Post, Australia Post.', 'wp-easycart' ) ),
					'labels'   => self::f( 'dashicons-printer', __( 'Labels ( Premium )', 'wp-easycart' ), __( 'ShipStation integration and label printing.', 'wp-easycart' ) ),
				),
			);
		}

		$catalog = apply_filters( 'wp_easycart_upsell_catalog', $catalog );
		return $catalog;
	}

	public static function resolve_context( $context = '' ) {
		$catalog = self::catalog();
		$context = is_string( $context ) ? str_replace( '-', '_', $context ) : '';
		if ( ! isset( $catalog[ $context ] ) ) {
			$hook = current_action();
			$context = ( $hook && isset( self::$hook_map[ $hook ] ) ) ? self::$hook_map[ $hook ] : '';
		}
		if ( '' === $context && isset( $_GET['subpage'] ) ) {
			$sub = str_replace( '-', '_', sanitize_key( $_GET['subpage'] ) );
			if ( in_array( $sub, array( 'shipping_settings', 'shipping_rates' ), true ) ) {
				$context = 'shipping_ups';
			} else if ( isset( $catalog[ $sub ] ) ) {
				$context = $sub;
			}
		}
		$context = apply_filters( 'wp_easycart_upsell_context', $context );
		return isset( $catalog[ $context ] ) ? $context : 'default';
	}

	public static function entry( $context ) {
		$context = self::resolve_context( $context );
		$catalog = self::catalog();
		$e = $catalog[ $context ];
		$e['key']       = $context;
		$e['badge']     = self::plan_badge( isset( $e['plan'] ) ? $e['plan'] : 'pro' );
		$e['headline']  = isset( $e['headline'] ) ? $e['headline'] : $e['title'];
		$e['stat_line'] = self::stat_line( $context );
		$e['pro_url']   = self::plan_url( 'pro', $context );
		$e['prem_url']  = self::plan_url( 'premium', $context );
		return $e;
	}

	public static function entries_for_js() {
		$out = array();
		foreach ( array_keys( self::catalog() ) as $key ) {
			$out[ $key ] = self::entry( $key );
		}
		return $out;
	}

	public static function plan_url( $plan, $context = 'default' ) {
		/* A registered license changes the destination: a lapsed paid license renews ( or upgrades to
		 * Premium ), an ended trial converts. Either way the key rides along so the store account
		 * gets credit and nothing has to be re-entered. */
		if ( function_exists( 'wp_easycart_admin_license' ) ) {
			$ld   = wp_easycart_admin_license()->license_data;
			$info = get_option( 'wp_easycart_license_info' );
			$key  = ( is_array( $info ) && isset( $info['transaction_key'] ) ) ? $info['transaction_key'] : '';
			if ( $ld && ! empty( $ld->support_end_date ) && self::days_until( strtotime( $ld->support_end_date ) ) <= 0 ) {
				if ( ! empty( $ld->is_trial ) ) {
					return 'https://www.wpeasycart.com/products/wp-easycart-trial-upgrade/?transaction_key=' . rawurlencode( $key ) . '&license_type=' . ( 'premium' === $plan ? 'premium' : 'professional' );
				}
				$prem = class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::is_premium() : ( 'ec410' === strtolower( trim( (string) ( isset( $ld->model_number ) ? $ld->model_number : '' ) ) ) );
				if ( 'premium' === $plan || $prem ) {
					return 'https://www.wpeasycart.com/products/wp-easycart-premium-support-extensions/?transaction_key=' . rawurlencode( $key );
				}
				return 'https://www.wpeasycart.com/products/wp-easycart-professional-support-upgrades/?transaction_key=' . rawurlencode( $key );
			}
		}
		$page = isset( $_GET['subpage'] ) ? sanitize_key( $_GET['subpage'] ) : ( isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '' );
		$url  = add_query_arg( array( 'upsell' => ( 'premium' === $plan ? 6 : 5 ), 'upsellpage' => $page, 'upsellctx' => $context ), self::PRICING_URL );
		return apply_filters( 'premium' === $plan ? 'wp_easycart_upgrade_premium_url' : 'wp_easycart_upgrade_pro_url', $url );
	}

	public static function pro_installed_inactive() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return file_exists( EC_PLUGIN_DIRECTORY . '-pro/wp-easycart-admin-pro.php' ) && ! is_plugin_active( 'wp-easycart-pro/wp-easycart-admin-pro.php' );
	}

	/** onclick handler string for any locked control. */
	public static function onclick( $context, $feature = '' ) {
		return 'ecdv2_upsell( { context: \'' . esc_js( $context ) . '\', feature: \'' . esc_js( $feature ) . '\' } ); return false;';
	}

	/* ------------------------------------------------------------------ */
	/* Renderers                                                           */
	/* ------------------------------------------------------------------ */

	/** Clickable feature grid ( the Cart Links strip ), reusable anywhere. */
	public static function print_feature_strip( $context, $with_header = true, $override = array() ) {
		$e = self::entry( $context );
		$headline = isset( $override['headline'] ) ? $override['headline'] : $e['headline'];
		$lede     = isset( $override['lede'] ) ? $override['lede'] : $e['lede'];
		$badge    = isset( $override['badge'] ) ? $override['badge'] : $e['badge'];
		echo '<div class="ecv2-cl-upsell' . ( ! empty( $override['tone'] ) ? ' is-' . esc_attr( $override['tone'] ) : '' ) . '" data-upsell-context="' . esc_attr( $e['key'] ) . '">';
		if ( $with_header ) {
			echo '<div class="ecv2-cl-upsell-head">';
			echo '<span class="ecv2-cl-pro-badge">' . esc_html( $badge ) . '</span>';
			echo '<div class="ecv2-cl-upsell-copy"><strong>' . esc_html( $headline ) . '</strong> <span>' . esc_html( $lede ) . '</span></div>';
			if ( ! empty( $override['cta_url'] ) ) {
				echo '<a class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" href="' . esc_url( $override['cta_url'] ) . '" target="_blank">' . esc_html( ! empty( $override['cta_label'] ) ? $override['cta_label'] : __( 'Renew now', 'wp-easycart' ) ) . '</a>';
			} else {
				echo '<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" onclick="' . self::onclick( $e['key'] ) . '">' . esc_html__( 'See what\'s included', 'wp-easycart' ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- self::onclick() builds the handler from esc_js() values.
			}
			echo '</div>';
		}
		echo '<div class="ecv2-cl-upsell-grid">';
		foreach ( $e['features'] as $key => $f ) {
			echo '<button type="button" class="ecv2-cl-upsell-feature" data-feature="' . esc_attr( $key ) . '" onclick="' . self::onclick( $e['key'], $key ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- self::onclick() builds the handler from esc_js() values; $key is a catalog() array key.
			echo '<span class="dashicons ' . esc_attr( $f['icon'] ) . '"></span>';
			echo '<span class="ecv2-cl-upsell-feature-text"><strong>' . esc_html( $f['title'] ) . '</strong><span>' . esc_html( $f['desc'] ) . '</span></span>';
			echo '<span class="dashicons dashicons-lock ecv2-cl-upsell-lock"></span>';
			echo '</button>';
		}
		echo '</div></div>';
	}

	/**
	 * Locked-page recreation: a real-looking page header, the feature strip,
	 * then a soft-locked mock of the live UI so the user SEES the feature
	 * instead of reading about it. Any click opens the popup.
	 */
	public static function print_locked_page( $context ) {
		$e = self::entry( $context );
		$ctx = $e['key'];
		echo '<div class="ecv2-wrap ecv2-locked-page" data-upsell-context="' . esc_attr( $ctx ) . '">';

		/* Inside the admin shell, wp_easycart_pro_check() has already printed the "installed but not activated" banner above the content. */
		if ( self::pro_installed_inactive() && ! did_action( 'wp_easycart_admin_messages' ) ) {
			echo '<div class="ecv2-upsell-notice"><span class="dashicons dashicons-info-outline"></span><span>' . esc_html__( 'WP EasyCart PRO, the plugin that runs Pro and Premium licenses, is already installed on this site. It just needs to be switched on.', 'wp-easycart' ) . '</span><a class="ecv2-btn ecv2-btn-sm" href="' . esc_url( wp_easycart_admin()->get_pro_activation_link() ) . '">' . esc_html__( 'Activate WP EasyCart PRO', 'wp-easycart' ) . '</a></div>';
		}

		echo '<div class="ecv2-page-header">';
		echo '<div class="ecv2-page-header-left"><span class="dashicons ' . esc_attr( isset( $e['icon'] ) ? $e['icon'] : 'dashicons-lock' ) . ' ecv2-page-header-icon"></span><h2 class="ecv2-page-title">' . esc_html( $e['title'] ) . '</h2><span class="ecv2-cl-pro-badge">' . esc_html( $e['badge'] ) . '</span></div>';
		echo '<div class="ecv2-page-header-right"><button type="button" class="ecv2-btn ecv2-btn-primary" onclick="' . self::onclick( $ctx ) . '"><span class="dashicons dashicons-lock"></span> + ' . esc_html( isset( $e['new_label'] ) ? $e['new_label'] : __( 'New', 'wp-easycart' ) ) . '</button></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- self::onclick() builds the handler from esc_js() values.
		echo '</div>';

		if ( '' !== $e['stat_line'] ) {
			echo '<p class="ecv2-page-intro ecv2-upsell-stat-inline"><span class="dashicons dashicons-chart-line"></span> ' . esc_html( $e['stat_line'] ) . '</p>';
		}

		self::print_feature_strip( $ctx );

		$method = 'preview_' . $ctx;
		if ( method_exists( __CLASS__, $method ) ) {
			echo '<div class="ecv2-locked-preview" onclick="' . self::onclick( $ctx ) . '" role="button" tabindex="0">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- self::onclick() builds the handler from esc_js() values.
			echo '<div class="ecv2-locked-preview-tag"><span class="dashicons dashicons-visibility"></span> ' . esc_html__( 'Preview with sample data', 'wp-easycart' ) . '</div>';
			call_user_func( array( __CLASS__, $method ), $e );
			echo '<div class="ecv2-locked-preview-veil"><span class="ecv2-btn ecv2-btn-primary"><span class="dashicons dashicons-lock"></span> ' . esc_html( sprintf( __( 'Unlock %s', 'wp-easycart' ), $e['title'] ) ) . '</span></div>';
			echo '</div>';
		}

		do_action( 'wp_easycart_upsell_locked_page_end', $e );
		echo '</div>';
	}

	/** Mock of the Offers hub with sample offers. Add preview_<ctx> for others. */
	private static function preview_offers( $e ) {
		$rows = array(
			array( 'name' => sprintf( __( 'Free shipping over %s', 'wp-easycart' ), self::money( 75 ) ), 'type' => __( 'Free shipping', 'wp-easycart' ), 'rule' => __( 'Auto-applies · all products', 'wp-easycart' ), 'code' => '', 'uses' => 212, 'rev' => 14380, 'status' => 'active', 'chip' => 'offer' ),
			array( 'name' => __( 'Buy 2 tees, get 1 free', 'wp-easycart' ), 'type' => __( 'BOGO', 'wp-easycart' ), 'rule' => __( 'Category: T-Shirts', 'wp-easycart' ), 'code' => '', 'uses' => 87, 'rev' => 4265, 'status' => 'active', 'chip' => 'offer' ),
			array( 'name' => __( 'Welcome 10% off', 'wp-easycart' ), 'type' => __( '10% off', 'wp-easycart' ), 'rule' => __( 'First order only · 1 per customer', 'wp-easycart' ), 'code' => 'WELCOME10', 'uses' => 341, 'rev' => 19752, 'status' => 'active', 'chip' => 'coupon' ),
			array( 'name' => __( 'Labor Day sale', 'wp-easycart' ), 'type' => sprintf( __( '%s off', 'wp-easycart' ), self::money( 15 ) ), 'rule' => sprintf( __( 'Sep 1 – Sep 7 · min. spend %s', 'wp-easycart' ), self::money( 50 ) ), 'code' => 'LABORDAY', 'uses' => 56, 'rev' => 6120, 'status' => 'scheduled', 'chip' => 'coupon' ),
			array( 'name' => __( 'Spring clearance', 'wp-easycart' ), 'type' => __( '25% off', 'wp-easycart' ), 'rule' => __( 'Category: Clearance', 'wp-easycart' ), 'code' => 'SPRING25', 'uses' => 498, 'rev' => 22410, 'status' => 'expired', 'chip' => 'coupon' ),
		);
		$labels = array( 'scheduled' => __( 'Scheduled', 'wp-easycart' ), 'expired' => __( 'Ended', 'wp-easycart' ) );

		echo '<div class="ecv2-locked-kpis">';
		foreach ( array(
			array( __( 'Active offers', 'wp-easycart' ), '3' ),
			array( __( 'Redemptions · 30d', 'wp-easycart' ), '640' ),
			array( __( 'Revenue with offers · 30d', 'wp-easycart' ), self::money( 44397 ) ),
			array( __( 'Avg. order lift', 'wp-easycart' ), '+18%' ),
		) as $kpi ) {
			echo '<div class="ecv2-locked-kpi"><span>' . esc_html( $kpi[0] ) . '</span><strong>' . esc_html( $kpi[1] ) . '</strong></div>';
		}
		echo '</div>';

		echo '<div class="ecv2-table-card"><table class="ecv2-cart-link-table ecv2-locked-table"><thead><tr>';
		foreach ( array( __( 'Offer', 'wp-easycart' ), __( 'Discount', 'wp-easycart' ), __( 'Applies to', 'wp-easycart' ), __( 'Uses', 'wp-easycart' ), __( 'Revenue', 'wp-easycart' ), __( 'Status', 'wp-easycart' ) ) as $h ) {
			echo '<th>' . esc_html( $h ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr>';
			echo '<td><div class="ecv2-cl-label">' . esc_html( $r['name'] ) . '</div><div class="ecv2-cl-url">';
			echo '' !== $r['code'] ? '<code>' . esc_html( $r['code'] ) . '</code>' : '<span class="ecv2-qe-muted">' . esc_html__( 'No code needed', 'wp-easycart' ) . '</span>';
			echo '</div></td>';
			echo '<td><span class="ecv2-clp-chip ecv2-clp-chip-' . esc_attr( $r['chip'] ) . '">' . esc_html( $r['type'] ) . '</span></td>';
			echo '<td>' . esc_html( $r['rule'] ) . '</td>';
			echo '<td>' . esc_html( number_format_i18n( $r['uses'] ) ) . '</td>';
			echo '<td><strong>' . esc_html( self::money( $r['rev'] ) ) . '</strong></td>';
			echo '<td><label class="ecv2-switch"><input type="checkbox"' . ( 'active' === $r['status'] ? ' checked' : '' ) . ' disabled /><span class="ecv2-switch-slider"></span></label>';
			if ( isset( $labels[ $r['status'] ] ) ) {
				echo ' <span class="ecv2-cl-flag">' . esc_html( $labels[ $r['status'] ] ) . '</span>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	/**
	 * Mock of the PRO Flex-Fees list with sample fees. Never reads ec_fee: an unlicensed
	 * store must not see ( or be able to act on ) its real fee rows here.
	 *
	 * @since 6.0.0
	 *
	 * @param array $e Catalog entry ( unused; every preview_*() shares print_locked_page()'s signature ).
	 */
	private static function preview_fees( $e ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- called through call_user_func() with the catalog entry.
		$pct = function ( $v ) {
			return number_format_i18n( $v, 2 ) . '%';
		};
		$row = function ( $name, $amount, $sub, $applies, $discount, $kind ) {
			return array(
				'name'     => $name,
				'amount'   => $amount,
				'sub'      => $sub,
				'applies'  => $applies,
				'discount' => $discount,
				'kind'     => $kind,
			);
		};
		/* translators: %s: smallest amount a percentage fee can charge, e.g. $0.30. */
		$min = sprintf( __( 'min. %s', 'wp-easycart' ), self::money( 0.30 ) );
		/* translators: %s: largest amount a percentage fee or discount can reach, e.g. $20.00. */
		$max  = sprintf( __( 'max. %s', 'wp-easycart' ), self::money( 20 ) );
		$pc   = __( 'percentage', 'wp-easycart' );
		$flat = __( 'flat', 'wp-easycart' );
		$rows = array(
			$row( __( 'Card processing fee', 'wp-easycart' ), $pct( 2.9 ), $min, __( 'Card payments', 'wp-easycart' ), false, $pc ),
			$row( __( 'PayPal fee', 'wp-easycart' ), $pct( 3.49 ), '', __( 'PayPal / third party', 'wp-easycart' ), false, $pc ),
			$row( __( 'Remote area delivery', 'wp-easycart' ), self::money( 15 ), '', __( 'Alaska, Hawaii', 'wp-easycart' ), false, $flat ),
			$row( __( 'Wholesale handling', 'wp-easycart' ), self::money( 5 ), '', __( 'Role: wholesale', 'wp-easycart' ), false, $flat ),
			$row( __( 'Local pickup discount', 'wp-easycart' ), '-' . $pct( 5 ), $max, __( 'Shipping zone: Local', 'wp-easycart' ), true, $pc ),
			$row( __( 'Environmental levy', 'wp-easycart' ), $pct( 1 ), '', '', false, $pc ),
		);

		echo '<div class="ecv2-locked-kpis">';
		foreach ( array(
			array( __( 'All fees', 'wp-easycart' ), '6' ),
			array( __( 'Discounts', 'wp-easycart' ), '1' ),
			array( __( 'On every order', 'wp-easycart' ), '1' ),
			array( __( 'Fees collected · 30d', 'wp-easycart' ), self::money( 1284.60 ) ),
		) as $kpi ) {
			echo '<div class="ecv2-locked-kpi"><span>' . esc_html( $kpi[0] ) . '</span><strong>' . esc_html( $kpi[1] ) . '</strong></div>';
		}
		echo '</div>';

		echo '<div class="ecv2-table-card"><table class="ecv2-locked-table"><thead><tr>';
		foreach ( array( __( 'Fee', 'wp-easycart' ), __( 'Amount', 'wp-easycart' ), __( 'Applies to', 'wp-easycart' ), __( 'Type', 'wp-easycart' ) ) as $h ) {
			echo '<th>' . esc_html( $h ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr>';
			echo '<td><strong>' . esc_html( $r['name'] ) . '</strong></td>';
			echo '<td><strong' . ( $r['discount'] ? ' class="ecv2-locked-discount"' : '' ) . '>' . esc_html( $r['amount'] ) . '</strong>';
			if ( '' !== $r['sub'] ) {
				echo ' <span class="ecv2-locked-sub">' . esc_html( $r['sub'] ) . '</span>';
			}
			echo '</td>';
			echo '<td>' . ( '' === $r['applies'] ? '<span class="ecv2-locked-chip is-amber">' . esc_html__( 'All orders', 'wp-easycart' ) . '</span>' : esc_html( $r['applies'] ) ) . '</td>';
			echo '<td><span class="ecv2-locked-chip' . ( $r['discount'] ? ' is-green' : '' ) . '">' . esc_html( $r['discount'] ? __( 'Discount', 'wp-easycart' ) : __( 'Fee', 'wp-easycart' ) ) . '</span> <span class="ecv2-locked-sub">' . esc_html( $r['kind'] ) . '</span></td>';
			echo '</tr>';
		}
		echo '</tbody></table></div>';
	}

	/* ------------------------------------------------------------------ */
	/* Assets                                                              */
	/* ------------------------------------------------------------------ */

	public static function enqueue() {
		if ( ! isset( $_GET['page'] ) || 0 !== strpos( sanitize_key( $_GET['page'] ), 'wp-easycart' ) ) {
			return;
		}
		wp_enqueue_style( 'wp_easycart_admin_v2_css', plugins_url( 'wp-easycart/admin/css/admin-v2.css', EC_PLUGIN_DIRECTORY ), array(), EC_CURRENT_VERSION );
		wp_enqueue_style( 'wp_easycart_admin_upsell_css', plugins_url( 'wp-easycart/admin/css/admin-upsell.css', EC_PLUGIN_DIRECTORY ), array( 'wp_easycart_admin_v2_css' ), EC_CURRENT_VERSION );
		wp_enqueue_script( 'wp_easycart_admin_upsell_js', plugins_url( 'wp-easycart/admin/js/upsell.js', EC_PLUGIN_DIRECTORY ), array( 'jquery' ), EC_CURRENT_VERSION, true );
		wp_localize_script( 'wp_easycart_admin_upsell_js', 'wp_easycart_upsell_vars', array(
			'entries' => self::entries_for_js(),
			'lang'    => array( 'learn' => __( 'How it works', 'wp-easycart' ), 'full' => __( 'Full feature list', 'wp-easycart' ) ),
		) );
		if ( class_exists( 'wp_easycart_admin_edition' ) ) {
			wp_localize_script( 'wp_easycart_admin_upsell_js', 'wp_easycart_edition', wp_easycart_admin_edition::for_js() );
		}
	}
}
add_action( 'admin_enqueue_scripts', array( 'wp_easycart_admin_upsell', 'enqueue' ), 19 );