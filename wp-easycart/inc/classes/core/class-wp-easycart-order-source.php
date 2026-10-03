<?php
/**
 * Order sources ( 6.0.2 ): where each order came from.
 *
 * design/theme/base-responsive-v3/ec-order-source.js runs on every storefront page and keeps, in the first-party cookie
 * wpec_source, the shopper's first visit and the latest visit that brought them back: the referring site, the campaign
 * tags ( UTM ), which ad click IDs were in the address, and the landing page. It runs in the browser, so fully cached
 * pages count. The checkout session keeps a copy ( ec_tempcart_data.source_data ), because Stripe's webhook can finish an
 * order with no browser present.
 *
 * Every insert in ec_db ( checkout orders, subscription first orders ) calls stamp(): both visits are sorted into a type
 * and a name ( AI assistant / ChatGPT ) and saved on ec_order with the raw values ( source_data, so a later release can
 * sort old orders again when the lists change ). Renewals and orders staff make are stamped as such.
 *
 * WP EasyCart shows the first visit's source in the orders list. Everything else about sources is a WP EasyCart PRO
 * feature: filter wp_easycart_order_sources_pro unlocks it, and until then the order screen card and the Reports card
 * render locked ( upsell context order_sources ).
 *
 * @since 6.0.2
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_order_source' ) ) :

	/**
	 * Recording, sorting and showing order sources.
	 *
	 * @since 6.0.2
	 */
	final class wp_easycart_order_source {

		/** The storefront cookie. */
		const COOKIE = 'wpec_source';

		/** Days a first visit counts for. */
		const FIRST_DAYS = 90;

		/** Minutes of quiet that end a visit. */
		const SESSION_MINUTES = 30;

		/** Version of the lists below, saved with each order so old orders can be sorted again. */
		const LISTS_VERSION = 1;

		/**
		 * Columns found ( per request ).
		 *
		 * @var bool|null
		 */
		private static $ready = null;

		/**
		 * The shopper's consent for recording ( per request, consent_given() ).
		 *
		 * @var bool|null
		 */
		private static $consent = null;

		/** Register hooks. */
		public static function init() {
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
			add_action( 'wp_easycart_admin_order_created', array( __CLASS__, 'staff_order' ), 5 );
			add_action( 'wp_easycart_ecv2_order_duplicated', array( __CLASS__, 'staff_order' ), 5 );
			add_filter( 'wp_easycart_abandoned_cart_restore_url', array( __CLASS__, 'tag_abandoned_cart_url' ), 10, 4 );
			add_action( 'admin_init', array( __CLASS__, 'privacy_policy' ) );
			add_action( 'init', array( __CLASS__, 'cookie_info' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ), 20 );
		}

		/*
		------------------------------------------------------------------ */
		/*
		State                                                               */
		/* ------------------------------------------------------------------ */

		/** @return bool Recording is switched on ( Settings › Integrations › Order sources ). */
		public static function enabled() {
			return '0' !== (string) get_option( 'ec_option_order_sources', '1' );
		}

		/** @return string The WP Consent API category recording waits for. */
		public static function consent_category() {
			$category = (string) get_option( 'ec_option_order_sources_consent', 'marketing' );
			if ( ! in_array( $category, array( 'marketing', 'statistics' ), true ) ) {
				$category = 'marketing';
			}
			return (string) apply_filters( 'wp_easycart_order_sources_consent_category', $category );
		}

		/**
		 * Whether the shopper agreed to the category recording waits for, going by this request. The script only writes the
		 * cookie after consent, but a cookie written before the store asked for consent would otherwise still be read ( and
		 * copied to the checkout session ) while the shopper never answers the banner.
		 *
		 * @since 6.0.2
		 * @return bool True when the store does not ask.
		 */
		public static function consent_given() {
			if ( null !== self::$consent ) {
				return self::$consent;
			}
			$category = self::consent_category();
			if ( class_exists( 'wp_easycart_consent' ) && wp_easycart_consent::asks() ) {
				$given = wp_easycart_consent::has( $category );
			} elseif ( function_exists( 'wp_has_consent' ) ) {
				/* The WP Consent API is active: the script waits for it even with Cookie consent off. */
				$given = (bool) wp_has_consent( $category );
			} else {
				$given = true;
			}
			/* The checkout session is synced while plugins load ( inc/ec_config.php ); banner detection settles on
			   plugins_loaded, so only an answer from then on is kept for the request. */
			if ( ! function_exists( 'did_action' ) || did_action( 'plugins_loaded' ) ) {
				self::$consent = $given;
			}
			return $given;
		}

		/** @return bool The 6.0.2 order columns exist ( read once per request ). */
		public static function ready() {
			global $wpdb;
			if ( null === self::$ready ) {
				self::$ready = (bool) $wpdb->get_var( "SHOW COLUMNS FROM ec_order LIKE 'last_source_type'" );
			}
			return self::$ready;
		}

		/** @return bool WP EasyCart PRO shows sources in full ( licensed WP EasyCart PRO 6.0.2 or newer ). */
		public static function pro() {
			return (bool) apply_filters( 'wp_easycart_order_sources_pro', false );
		}

		/*
		------------------------------------------------------------------ */
		/*
		Lists                                                               */
		/* ------------------------------------------------------------------ */

		/**
		 * What a visit is sorted by. Host keys match the host or any subdomain of it; a key ending in ".*" matches that
		 * name under any country ending ( google.* : google.com, google.co.uk ). Hosts start from Matomo's public-domain
		 * referrer lists.
		 *
		 * @return array
		 */
		public static function lists() {
			static $cache = array();
			$ignore       = (string) get_option( 'ec_option_order_sources_ignore', '' );
			if ( isset( $cache[ $ignore ] ) ) {
				return $cache[ $ignore ];
			}
			$lists = array(
				'ai'             => array(
					'chatgpt.com'             => 'ChatGPT',
					'chat.openai.com'         => 'ChatGPT',
					'perplexity.ai'           => 'Perplexity',
					'gemini.google.com'       => 'Gemini',
					'bard.google.com'         => 'Gemini',
					'copilot.microsoft.com'   => 'Copilot',
					'copilot.cloud.microsoft' => 'Copilot',
					'm365.cloud.microsoft'    => 'Copilot',
					'edgeservices.bing.com'   => 'Copilot',
					'claude.ai'               => 'Claude',
					'grok.com'                => 'Grok',
					'chat.deepseek.com'       => 'DeepSeek',
					'deepseek.com'            => 'DeepSeek',
					'meta.ai'                 => 'Meta AI',
					'chat.mistral.ai'         => 'Le Chat',
					'you.com'                 => 'You.com',
					'poe.com'                 => 'Poe',
					'chat.qwen.ai'            => 'Qwen',
					'duck.ai'                 => 'Duck.ai',
					'notebooklm.google.com'   => 'NotebookLM',
					'phind.com'               => 'Phind',
				),
				/* utm_source values AI assistants add to their links ( ChatGPT: utm_source=chatgpt.com ). */
				'ai_utm'         => array(
					'chatgpt.com'           => 'ChatGPT',
					'chatgpt'               => 'ChatGPT',
					'openai'                => 'ChatGPT',
					'perplexity'            => 'Perplexity',
					'perplexity.ai'         => 'Perplexity',
					'gemini'                => 'Gemini',
					'gemini.google.com'     => 'Gemini',
					'copilot'               => 'Copilot',
					'copilot.com'           => 'Copilot',
					'copilot.microsoft.com' => 'Copilot',
					'claude'                => 'Claude',
					'claude.ai'             => 'Claude',
					'grok'                  => 'Grok',
					'grok.com'              => 'Grok',
					'deepseek'              => 'DeepSeek',
					'meta.ai'               => 'Meta AI',
				),
				'search'         => array(
					'google.*'         => 'Google',
					'bing.com'         => 'Bing',
					'duckduckgo.com'   => 'DuckDuckGo',
					'search.yahoo.com' => 'Yahoo',
					'yahoo.*'          => 'Yahoo',
					'yandex.*'         => 'Yandex',
					'baidu.com'        => 'Baidu',
					'ecosia.org'       => 'Ecosia',
					'search.brave.com' => 'Brave',
					'startpage.com'    => 'Startpage',
					'qwant.com'        => 'Qwant',
					'naver.com'        => 'Naver',
					'seznam.cz'        => 'Seznam',
					'ask.com'          => 'Ask',
					'search.aol.com'   => 'AOL',
					'kagi.com'         => 'Kagi',
				),
				'social'         => array(
					'facebook.com'    => 'Facebook',
					'fb.com'          => 'Facebook',
					'fb.me'           => 'Facebook',
					'instagram.com'   => 'Instagram',
					'x.com'           => 'X',
					'twitter.com'     => 'X',
					't.co'            => 'X',
					'linkedin.com'    => 'LinkedIn',
					'lnkd.in'         => 'LinkedIn',
					'pinterest.*'     => 'Pinterest',
					'pin.it'          => 'Pinterest',
					'tiktok.com'      => 'TikTok',
					'reddit.com'      => 'Reddit',
					'youtube.com'     => 'YouTube',
					'youtu.be'        => 'YouTube',
					'threads.net'     => 'Threads',
					'threads.com'     => 'Threads',
					'bsky.app'        => 'Bluesky',
					'snapchat.com'    => 'Snapchat',
					'tumblr.com'      => 'Tumblr',
					'whatsapp.com'    => 'WhatsApp',
					'wa.me'           => 'WhatsApp',
					't.me'            => 'Telegram',
					'quora.com'       => 'Quora',
					'vk.com'          => 'VK',
					'discord.com'     => 'Discord',
					'mastodon.social' => 'Mastodon',
					'nextdoor.com'    => 'Nextdoor',
				),
				'email'          => array(
					'mail.google.com'       => 'Gmail',
					'outlook.live.com'      => 'Outlook',
					'outlook.office.com'    => 'Outlook',
					'outlook.office365.com' => 'Outlook',
					'mail.yahoo.com'        => 'Yahoo Mail',
					'mail.aol.com'          => 'AOL Mail',
					'mail.proton.me'        => 'Proton Mail',
					'mail.zoho.com'         => 'Zoho Mail',
				),
				/* Android apps send android-app://<package> as the referring site. */
				'apps'           => array(
					'com.openai.chatgpt'           => array( 'ai', 'ChatGPT' ),
					'ai.perplexity.app.android'    => array( 'ai', 'Perplexity' ),
					'com.google.android.apps.bard' => array( 'ai', 'Gemini' ),
					'com.microsoft.copilot'        => array( 'ai', 'Copilot' ),
					'com.anthropic.claude'         => array( 'ai', 'Claude' ),
					'com.google.android.gm'        => array( 'email', 'Gmail' ),
					'com.microsoft.office.outlook' => array( 'email', 'Outlook' ),
					'com.google.android.googlequicksearchbox' => array( 'search', 'Google' ),
					'com.facebook.katana'          => array( 'social', 'Facebook' ),
					'com.instagram.android'        => array( 'social', 'Instagram' ),
					'com.twitter.android'          => array( 'social', 'X' ),
					'com.linkedin.android'         => array( 'social', 'LinkedIn' ),
					'com.pinterest'                => array( 'social', 'Pinterest' ),
					'com.reddit.frontpage'         => array( 'social', 'Reddit' ),
					'com.zhiliaoapp.musically'     => array( 'social', 'TikTok' ),
					'com.google.android.youtube'   => array( 'social', 'YouTube' ),
				),
				/* Ad click IDs: key => the ad network. fbclid is on every link Meta sends, paid or not, so it only names the site. */
				'clicks'         => array(
					'gclid'     => 'Google Ads',
					'gbraid'    => 'Google Ads',
					'wbraid'    => 'Google Ads',
					'dclid'     => 'Google Ads',
					'msclkid'   => 'Microsoft Ads',
					'ttclid'    => 'TikTok Ads',
					'twclid'    => 'X Ads',
					'li_fat_id' => 'LinkedIn Ads',
					'epik'      => 'Pinterest Ads',
					'fbclid'    => '',
				),
				/*
				 * 6.0.2: IDs email services add to the links in their emails: key => the service. Captured like ad click IDs
				 * ( names only, never values ) and sorted under Email, never Paid. Names match in any case.
				 */
				'email_clicks'   => array(
					'mc_cid'            => 'Mailchimp',
					'mc_eid'            => 'Mailchimp',
					'_kx'               => 'Klaviyo',
					'ck_subscriber_id'  => 'Kit',
					'vgo_ee'            => 'ActiveCampaign',
					'omnisendContactID' => 'Omnisend',
				),
				'paid_mediums'   => array( 'cpc', 'ppc', 'paid', 'paidsearch', 'paid_search', 'paid-search', 'paidsocial', 'paid_social', 'paid-social', 'display', 'cpm', 'cpv', 'cpa', 'banner', 'retargeting', 'shopping', 'ads' ),
				'email_mediums'  => array( 'email', 'e-mail', 'e_mail', 'mail', 'newsletter' ),
				'social_mediums' => array( 'social', 'social-network', 'social_network', 'social-media', 'social_media', 'sm', 'organic_social', 'organic-social' ),
				'search_mediums' => array( 'organic', 'seo' ),
				/* Pages a shopper returns from mid-checkout: never a visit of their own. */
				'ignore'         => array(
					'paypal.com',
					'paypal.me',
					'paypalobjects.com',
					'stripe.com',
					'stripe.network',
					'squareup.com',
					'squareupsandbox.com',
					'square.link',
					'klarna.com',
					'afterpay.com',
					'clearpay.co.uk',
					'affirm.com',
					'pay.amazon.com',
					'payments.amazon.com',
					'amazonpay.com',
					'braintreegateway.com',
					'braintree-api.com',
					'cardinalcommerce.com',
					'3dsecure.io',
					'adyen.com',
					'authorize.net',
					'opayo.co.uk',
					'sagepay.com',
					'mollie.com',
					'payfast.co.za',
					'paystack.com',
					'razorpay.com',
					'pay.google.com',
					'accounts.google.com',
					'appleid.apple.com',
					/* 6.0.2: the other payment pages WP EasyCart can send a shopper to. */
					'payandshop.com',
					'realexpayments.com',
					'2checkout.com',
					'nets.eu',
					'bbs.no',
					'payfort.com',
					'redsys.es',
					'skrill.com',
					'moneybookers.com',
					'paymentexpress.com',
					'windcave.com',
					'sagepay.co.za',
					'elavon.com',
					'chronopay.com',
					'dwolla.com',
					'cashfree.com',
					'migs.mastercard.com.au',
					'securepay.com.au',
					'eway.com.au',
					'ewaypayments.com',
					'myvirtualmerchant.com',
				),
			);
			foreach ( preg_split( '/[\s,]+/', strtolower( $ignore ) ) as $host ) {
				$host = self::clean_host( $host );
				if ( '' !== $host ) {
					$lists['ignore'][] = $host;
				}
			}
			$cache[ $ignore ] = apply_filters( 'wp_easycart_order_source_lists', $lists );
			return $cache[ $ignore ];
		}

		/**
		 * A host as the lists hold it: lower case, no scheme, path or www.
		 *
		 * @param string $host Host or address.
		 * @return string
		 */
		public static function clean_host( $host ) {
			$host = strtolower( trim( (string) $host ) );
			$host = preg_replace( '#^[a-z][a-z0-9+.\-]*://#', '', $host );
			$host = preg_replace( '#[/?\#:].*$#', '', $host );
			$host = trim( preg_replace( '/^www\./', '', $host ), '.' );
			/* A real host has a dot ( localhost aside ): "javascript:" and the like are not sites. */
			return ( preg_match( '/^[a-z0-9.\-]{1,253}$/', $host ) && ( false !== strpos( $host, '.' ) || 'localhost' === $host ) ) ? $host : '';
		}

		/**
		 * Does a host match a list key?
		 *
		 * @param string $host Host ( no www ).
		 * @param string $key  List key ( host, or name.* ).
		 * @return bool
		 */
		public static function host_matches( $host, $key ) {
			if ( '' === $host || '' === $key ) {
				return false;
			}
			if ( '.*' === substr( $key, -2 ) ) {
				return (bool) preg_match( '/(^|\.)' . preg_quote( substr( $key, 0, -2 ), '/' ) . '\.[a-z]{2,3}(\.[a-z]{2})?$/', $host );
			}
			return $host === $key || substr( $host, -( strlen( $key ) + 1 ) ) === '.' . $key;
		}

		/**
		 * The label a list gives a host.
		 *
		 * @param string $host Host.
		 * @param array  $list key => label.
		 * @return string '' when it is not on the list.
		 */
		public static function lookup( $host, $list ) {
			foreach ( (array) $list as $key => $label ) {
				if ( self::host_matches( $host, (string) $key ) ) {
					return (string) $label;
				}
			}
			return '';
		}

		/*
		------------------------------------------------------------------ */
		/*
		Reading what the browser kept                                       */
		/* ------------------------------------------------------------------ */

		/**
		 * The cookie's value as the browser sent it ( base64url ).
		 *
		 * @return string
		 */
		public static function cookie_value() {
			if ( empty( $_COOKIE[ self::COOKIE ] ) || ! self::consent_given() ) {
				return '';
			}
			$raw = preg_replace( '/[^A-Za-z0-9_\-]/', '', sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) );
			return ( strlen( $raw ) <= 4000 ) ? $raw : '';
		}

		/**
		 * The visits in a stored value ( the cookie, or the checkout session's copy ).
		 *
		 * @param string $raw base64url JSON.
		 * @return array|null first, last ( visits: t time, r referring site, p landing path, u UTM tags, k click IDs ), visits.
		 */
		public static function decode( $raw ) {
			$raw = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $raw );
			if ( '' === $raw || strlen( $raw ) > 4000 ) {
				return null;
			}
			$json = base64_decode( strtr( $raw, '-_', '+/' ) . str_repeat( '=', ( 4 - strlen( $raw ) % 4 ) % 4 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- the cookie is base64url JSON written by ec-order-source.js.
			$data = is_string( $json ) ? json_decode( $json, true ) : null;
			if ( ! is_array( $data ) || ! isset( $data['v'] ) || 1 !== (int) $data['v'] ) {
				return null;
			}
			$first = isset( $data['f'] ) ? self::clean_visit( $data['f'] ) : null;
			$last  = isset( $data['l'] ) ? self::clean_visit( $data['l'] ) : null;
			if ( ! $first && ! $last ) {
				return null;
			}
			return array(
				'first'  => $first ? $first : $last,
				'last'   => $last ? $last : $first,
				'visits' => isset( $data['n'] ) ? max( 1, min( 9999, (int) $data['n'] ) ) : 1,
			);
		}

		/**
		 * One visit, checked and trimmed.
		 *
		 * @param mixed $visit Decoded visit.
		 * @return array|null
		 */
		public static function clean_visit( $visit ) {
			if ( ! is_array( $visit ) ) {
				return null;
			}
			$time = ( isset( $visit['t'] ) && is_scalar( $visit['t'] ) ) ? (int) $visit['t'] : 0;
			if ( $time <= 0 ) {
				return null;
			}
			/* 6.0.2: the time comes from the shopper's clock. One set wrong ( a day fast, or before 2020 ) no longer loses the
			   visit: it counts as now. */
			if ( $time < 1577836800 || $time > time() + DAY_IN_SECONDS ) {
				$time = time();
			}
			/* 6.0.2: a value that is not text ( a hand-edited cookie ) is left out rather than cast, which warned on PHP 8. */
			$referrer = ( isset( $visit['r'] ) && is_scalar( $visit['r'] ) ) ? strtolower( (string) $visit['r'] ) : '';
			if ( 0 === strpos( $referrer, 'android-app://' ) ) {
				$package  = preg_replace( '/[^a-z0-9._]/', '', substr( $referrer, 14 ) );
				$referrer = '' !== $package ? 'android-app://' . substr( $package, 0, 100 ) : '';
			} else {
				$referrer = self::clean_host( $referrer );
			}
			$path = ( isset( $visit['p'] ) && is_scalar( $visit['p'] ) ) ? (string) $visit['p'] : '';
			$path = '/' . ltrim( self::cut( preg_replace( '/[\x00-\x1F\x7F<>"\']/', '', $path ), 200 ), '/' );
			$tags = array();
			if ( isset( $visit['u'] ) && is_array( $visit['u'] ) ) {
				foreach ( array( 's', 'm', 'c', 't', 'o', 'i' ) as $key ) {
					if ( isset( $visit['u'][ $key ] ) && is_scalar( $visit['u'][ $key ] ) ) {
						/* 6.0.2: cut by characters, not bytes ( campaign names in the store's language ). */
						$value = self::cut( trim( sanitize_text_field( (string) $visit['u'][ $key ] ) ), 100 );
						if ( '' !== $value ) {
							$tags[ $key ] = $value;
						}
					}
				}
			}
			$clicks = array();
			if ( isset( $visit['k'] ) && is_array( $visit['k'] ) ) {
				$known = self::click_names();
				foreach ( $visit['k'] as $click ) {
					if ( ! is_scalar( $click ) ) {
						continue;
					}
					$click = strtolower( (string) $click );
					if ( array_key_exists( $click, $known ) && ! in_array( $click, $clicks, true ) ) {
						$clicks[] = $click;
					}
				}
			}
			return array(
				't' => $time,
				'r' => $referrer,
				'p' => $path,
				'u' => $tags,
				'k' => $clicks,
			);
		}

		/**
		 * Every click ID the capture script looks for, in lower case ( the script reads address names in lower case ): the
		 * ad click IDs, then the email services' click IDs.
		 *
		 * @since 6.0.2
		 * @return array lower-case name => array( paid|email, network or service name ).
		 */
		public static function click_names() {
			$lists = self::lists();
			$names = array();
			foreach ( array( 'clicks' => 'paid', 'email_clicks' => 'email' ) as $list => $type ) {
				if ( empty( $lists[ $list ] ) || ! is_array( $lists[ $list ] ) ) {
					continue;
				}
				foreach ( $lists[ $list ] as $name => $label ) {
					$key = strtolower( (string) $name );
					if ( '' !== $key && ! isset( $names[ $key ] ) ) {
						$names[ $key ] = array( $type, (string) $label );
					}
				}
			}
			return $names;
		}

		/**
		 * What the current request knows: the cookie, or else the checkout session's copy.
		 *
		 * @return array|null decode() output.
		 */
		public static function current() {
			$data = self::decode( self::cookie_value() );
			if ( $data ) {
				return $data;
			}
			$session = self::session_data();
			return ( $session && isset( $session->source_data ) ) ? self::decode( (string) $session->source_data ) : null;
		}

		/** @return object|null The checkout session row, when it has the 6.0.2 column. */
		private static function session_data() {
			if ( ! isset( $GLOBALS['ec_cart_data'] ) || ! is_object( $GLOBALS['ec_cart_data'] ) || ! isset( $GLOBALS['ec_cart_data']->cart_data ) || ! is_object( $GLOBALS['ec_cart_data']->cart_data ) ) {
				return null;
			}
			return property_exists( $GLOBALS['ec_cart_data']->cart_data, 'source_data' ) ? $GLOBALS['ec_cart_data']->cart_data : null;
		}

		/**
		 * Give the checkout session the cookie's value ( it is saved with the session's next save ), so an order that Stripe's
		 * webhook finishes, with no browser, still has it. Runs once the session is loaded ( inc/ec_config.php ).
		 */
		public static function sync_session() {
			$raw     = self::cookie_value();
			$session = self::session_data();
			if ( ! $session ) {
				return;
			}
			if ( '' === $raw ) {
				/* 6.0.2: a request from the shopper's browser ( it carries the store's session cookie ) with no source cookie:
				   consent was refused or withdrawn, or the cookie ran out. The session's copy goes too, so the order is not
				   stamped from it. Requests with no browser ( Stripe's webhook ) never carry the session cookie and keep it. */
				if ( ! empty( $_COOKIE['ec_cart_id'] ) && '' !== (string) $session->source_data ) {
					global $wpdb;
					$session->source_data = '';
					if ( isset( $GLOBALS['ec_cart_data']->ec_cart_id ) && 'not-set' !== (string) $GLOBALS['ec_cart_data']->ec_cart_id ) {
						$wpdb->update( 'ec_tempcart_data', array( 'source_data' => '' ), array( 'session_id' => (string) $GLOBALS['ec_cart_data']->ec_cart_id ), array( '%s' ), array( '%s' ) );
					}
				}
				return;
			}
			if ( ! self::enabled() || (string) $session->source_data === $raw || ! self::decode( $raw ) ) {
				return;
			}
			$session->source_data = $raw;
		}

		/*
		------------------------------------------------------------------ */
		/*
		Sorting                                                             */
		/* ------------------------------------------------------------------ */

		/** @return array type => label. */
		public static function types() {
			return array(
				'ai'       => __( 'AI assistant', 'wp-easycart' ),
				'search'   => __( 'Search', 'wp-easycart' ),
				'paid'     => __( 'Paid ads', 'wp-easycart' ),
				'social'   => __( 'Social', 'wp-easycart' ),
				'email'    => __( 'Email', 'wp-easycart' ),
				'campaign' => __( 'Campaign', 'wp-easycart' ),
				'referral' => __( 'Other sites', 'wp-easycart' ),
				'direct'   => __( 'Direct', 'wp-easycart' ),
				'staff'    => __( 'Staff', 'wp-easycart' ),
				'renewal'  => __( 'Renewal', 'wp-easycart' ),
			);
		}

		/**
		 * A type's label.
		 *
		 * @param string $type Type ( '' = not recorded ).
		 * @return string
		 */
		public static function type_label( $type ) {
			$types = self::types();
			return isset( $types[ $type ] ) ? $types[ $type ] : __( 'Not recorded', 'wp-easycart' );
		}

		/**
		 * Sort one visit.
		 *
		 * @param array $visit clean_visit() output.
		 * @return array { type, name }
		 */
		public static function classify( $visit ) {
			$lists  = self::lists();
			$tags   = isset( $visit['u'] ) ? $visit['u'] : array();
			$clicks = isset( $visit['k'] ) ? $visit['k'] : array();
			$host   = isset( $visit['r'] ) ? (string) $visit['r'] : '';
			$source = isset( $tags['s'] ) ? strtolower( $tags['s'] ) : '';
			$medium = isset( $tags['m'] ) ? strtolower( $tags['m'] ) : '';
			$app    = ( 0 === strpos( $host, 'android-app://' ) ) ? substr( $host, 14 ) : '';

			/* AI assistants: their tag or their site. */
			if ( '' !== $source && isset( $lists['ai_utm'][ $source ] ) ) {
				return array( 'ai', $lists['ai_utm'][ $source ] );
			}
			$ai = ( '' === $app ) ? self::lookup( $host, $lists['ai'] ) : '';
			if ( '' !== $ai ) {
				return array( 'ai', $ai );
			}
			if ( '' !== $app && isset( $lists['apps'][ $app ] ) && 'ai' === $lists['apps'][ $app ][0] ) {
				return array( 'ai', $lists['apps'][ $app ][1] );
			}

			$site = self::site_name( $host, $app, $lists );

			/* Paid: an ad click ID, or campaign tags that say so. */
			foreach ( $clicks as $click ) {
				if ( ! empty( $lists['clicks'][ $click ] ) ) {
					return array( 'paid', $lists['clicks'][ $click ] );
				}
			}
			if ( '' !== $medium && in_array( $medium, $lists['paid_mediums'], true ) ) {
				return array( 'paid', '' !== $source ? $tags['s'] : ( '' !== $site[1] ? $site[1] : __( 'Ads', 'wp-easycart' ) ) );
			}

			/* Email: tags, webmail, or EasyCart's own emails. */
			if ( 'wpeasycart' === $source && 'email' === $medium ) {
				return array( 'email', self::email_name( isset( $tags['c'] ) ? $tags['c'] : '' ) );
			}
			if ( '' !== $medium && in_array( $medium, $lists['email_mediums'], true ) ) {
				return array( 'email', '' !== $source ? $tags['s'] : ( isset( $tags['c'] ) ? $tags['c'] : __( 'Email', 'wp-easycart' ) ) );
			}
			/* 6.0.2: an email service's click ID ( Mailchimp's mc_cid, Klaviyo's _kx ) names the service. */
			$email_clicks = ( isset( $lists['email_clicks'] ) && is_array( $lists['email_clicks'] ) ) ? array_change_key_case( $lists['email_clicks'], CASE_LOWER ) : array();
			foreach ( $clicks as $click ) {
				if ( ! empty( $email_clicks[ $click ] ) ) {
					return array( 'email', (string) $email_clicks[ $click ] );
				}
			}
			if ( 'email' === $site[0] ) {
				return array( 'email', $site[1] );
			}

			/* Social: tags or a social site ( fbclid alone means Facebook ). */
			if ( '' !== $medium && in_array( $medium, $lists['social_mediums'], true ) ) {
				return array( 'social', '' !== $source ? $tags['s'] : ( '' !== $site[1] ? $site[1] : __( 'Social', 'wp-easycart' ) ) );
			}
			if ( 'social' === $site[0] ) {
				return array( 'social', $site[1] );
			}
			if ( in_array( 'fbclid', $clicks, true ) && '' === $source ) {
				return array( 'social', 'Facebook' );
			}

			/* Search. */
			if ( '' !== $medium && in_array( $medium, $lists['search_mediums'], true ) ) {
				return array( 'search', '' !== $source ? $tags['s'] : ( '' !== $site[1] ? $site[1] : __( 'Search', 'wp-easycart' ) ) );
			}
			if ( 'search' === $site[0] ) {
				return array( 'search', $site[1] );
			}

			/* Any other tags, any other site, or none. */
			if ( '' !== $source || '' !== $medium || isset( $tags['c'] ) ) {
				return array( 'campaign', '' !== $source ? $tags['s'] : ( isset( $tags['c'] ) ? $tags['c'] : $tags['m'] ) );
			}
			if ( '' !== $app ) {
				return array( 'referral', $app );
			}
			if ( '' !== $host ) {
				return array( 'referral', $host );
			}
			return array( 'direct', __( 'Direct', 'wp-easycart' ) );
		}

		/**
		 * What kind of site a referring host is.
		 *
		 * @param string $host  Host.
		 * @param string $app   Android package ( or '' ).
		 * @param array  $lists lists().
		 * @return array { type ( email|social|search|'' ), name }
		 */
		private static function site_name( $host, $app, $lists ) {
			if ( '' !== $app ) {
				return isset( $lists['apps'][ $app ] ) ? $lists['apps'][ $app ] : array( '', '' );
			}
			foreach ( array( 'email', 'social', 'search' ) as $type ) {
				$name = self::lookup( $host, $lists[ $type ] );
				if ( '' !== $name ) {
					return array( $type, $name );
				}
			}
			return array( '', '' );
		}

		/**
		 * The name of one of EasyCart's own emails, from its campaign tag.
		 *
		 * @param string $campaign utm_campaign.
		 * @return string
		 */
		public static function email_name( $campaign ) {
			$names = array(
				'abandoned-cart' => __( 'Abandoned-cart email', 'wp-easycart' ),
				'back-in-stock'  => __( 'Back-in-stock email', 'wp-easycart' ),
			);
			if ( isset( $names[ $campaign ] ) ) {
				return $names[ $campaign ];
			}
			return '' !== $campaign ? $campaign : __( 'EasyCart email', 'wp-easycart' );
		}

		/**
		 * The order columns for what a request knows.
		 *
		 * @param array $data decode() output.
		 * @return array Column => value.
		 */
		public static function describe( $data ) {
			list( $first_type, $first_name ) = self::classify( $data['first'] );
			list( $last_type, $last_name )   = self::classify( $data['last'] );
			return array(
				'source_type'      => $first_type,
				'source_name'      => self::cut( $first_name, 100 ),
				'last_source_type' => $last_type,
				'last_source_name' => self::cut( $last_name, 100 ),
				'source_data'      => wp_json_encode(
					array(
						'v'      => 1,
						'lists'  => self::LISTS_VERSION,
						'first'  => $data['first'],
						'last'   => $data['last'],
						'visits' => (int) $data['visits'],
					)
				),
			);
		}

		/*
		------------------------------------------------------------------ */
		/*
		The order                                                           */
		/* ------------------------------------------------------------------ */

		/**
		 * Save where a new order came from. ec_db calls this right after each insert.
		 *
		 * @param int    $order_id Order.
		 * @param string $kind     checkout ( the shopper's visits ) | staff | renewal.
		 */
		public static function stamp( $order_id, $kind = 'checkout' ) {
			global $wpdb;
			$order_id = (int) $order_id;
			if ( $order_id <= 0 || ! self::ready() ) {
				return;
			}
			$data = null;
			if ( 'staff' === $kind || 'renewal' === $kind ) {
				$row = array(
					'source_type'      => $kind,
					'source_name'      => '',
					'last_source_type' => $kind,
					'last_source_name' => '',
					'source_data'      => wp_json_encode(
						array(
							'v'    => 1,
							'kind' => $kind,
						)
					),
				);
			} else {
				$data = self::enabled() ? self::current() : null;
				if ( ! $data ) {
					return;
				}
				$row = self::describe( $data );
			}
			/**
			 * Filters where an order came from before it is saved.
			 *
			 * @since 6.0.2
			 * @param array      $row      source_type, source_name, last_source_type, last_source_name, source_data.
			 * @param int        $order_id Order.
			 * @param string     $kind     checkout | staff | renewal.
			 * @param array|null $data     The visits ( checkout only ).
			 */
			$row = apply_filters( 'wp_easycart_order_source', $row, $order_id, $kind, $data );
			if ( ! is_array( $row ) || empty( $row['source_type'] ) ) {
				return;
			}
			$wpdb->update(
				'ec_order',
				array(
					'source_type'      => substr( sanitize_key( $row['source_type'] ), 0, 20 ),
					'source_name'      => self::cut( (string) $row['source_name'], 100 ),
					'last_source_type' => substr( sanitize_key( isset( $row['last_source_type'] ) ? $row['last_source_type'] : $row['source_type'] ), 0, 20 ),
					'last_source_name' => self::cut( (string) ( isset( $row['last_source_name'] ) ? $row['last_source_name'] : $row['source_name'] ), 100 ),
					'source_data'      => (string) $row['source_data'],
				),
				array( 'order_id' => $order_id ),
				array( '%s', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
			do_action( 'wp_easycart_order_source_saved', $order_id, $row );
		}

		/**
		 * Actions wp_easycart_admin_order_created ( WP EasyCart PRO's New order ) and wp_easycart_ecv2_order_duplicated.
		 *
		 * @param int $order_id The new order.
		 */
		public static function staff_order( $order_id ) {
			self::stamp( (int) $order_id, 'staff' );
		}

		/**
		 * An order's source columns.
		 *
		 * @param int $order_id Order.
		 * @return object|null
		 */
		public static function order_row( $order_id ) {
			global $wpdb;
			if ( ! self::ready() ) {
				return null;
			}
			return $wpdb->get_row( $wpdb->prepare( 'SELECT order_id, order_date, source_type, source_name, last_source_type, last_source_name, source_data FROM ec_order WHERE order_id = %d', (int) $order_id ) );
		}

		/**
		 * An order's visits, read back from source_data.
		 *
		 * @param object $row order_row().
		 * @return array { first, last, visits, kind } ( visits as clean_visit() ), or an empty array.
		 */
		public static function details( $row ) {
			$data = ( $row && ! empty( $row->source_data ) ) ? json_decode( (string) $row->source_data, true ) : null;
			if ( ! is_array( $data ) ) {
				return array();
			}
			return array(
				'first'  => isset( $data['first'] ) ? self::clean_visit( $data['first'] ) : null,
				'last'   => isset( $data['last'] ) ? self::clean_visit( $data['last'] ) : null,
				'visits' => isset( $data['visits'] ) ? (int) $data['visits'] : 0,
				'kind'   => isset( $data['kind'] ) ? sanitize_key( $data['kind'] ) : '',
			);
		}

		/*
		------------------------------------------------------------------ */
		/*
		EasyCart's own emails                                               */
		/* ------------------------------------------------------------------ */

		/**
		 * Add campaign tags to a link to this store in one of EasyCart's own emails.
		 *
		 * @param string $url      Link.
		 * @param string $campaign Email key ( abandoned-cart, back-in-stock ).
		 * @return string
		 */
		public static function tag_url( $url, $campaign ) {
			$url = (string) $url;
			if ( '' === $url || ! self::enabled() || false !== strpos( $url, 'utm_source=' ) ) {
				return $url;
			}
			$home = wp_parse_url( home_url(), PHP_URL_HOST );
			$host = wp_parse_url( $url, PHP_URL_HOST );
			if ( $host && $home && strtolower( $host ) !== strtolower( $home ) ) {
				return $url;
			}
			return add_query_arg(
				array(
					'utm_source'   => 'wpeasycart',
					'utm_medium'   => 'email',
					'utm_campaign' => rawurlencode( sanitize_key( $campaign ) ),
				),
				$url
			);
		}

		/**
		 * Filter wp_easycart_abandoned_cart_restore_url.
		 *
		 * @param string $url    Restore link.
		 * @param object $cart   Abandoned cart.
		 * @param string $coupon Coupon on the link.
		 * @param bool   $track  6.0.2: true only for EasyCart's own reminder emails. Links copied from the admin or handed to
		 *                       an email service ( its own cart flows, which add their own click IDs ) stay untagged, so
		 *                       those orders are not all sorted as EasyCart's abandoned-cart email.
		 * @return string
		 */
		public static function tag_abandoned_cart_url( $url, $cart = null, $coupon = '', $track = true ) {
			return $track ? self::tag_url( $url, 'abandoned-cart' ) : $url;
		}

		/**
		 * The campaign tags and click IDs of the current request, to carry across a redirect ( the abandoned-cart restore
		 * link, Cart Links and buy links land on the cart through one, and the cart page is not a referrer of its own ).
		 *
		 * @return array name => URL-encoded value.
		 */
		public static function request_tags() {
			$tags = array();
			foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id' ) as $key ) {
				if ( isset( $_GET[ $key ] ) && is_scalar( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- campaign tags on a public link, copied onto the redirect only.
					$value = self::cut( sanitize_text_field( wp_unslash( $_GET[ $key ] ) ), 100 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- as above.
					if ( '' !== $value ) {
						$tags[ $key ] = rawurlencode( $value );
					}
				}
			}
			/* 6.0.2: the ad and email click IDs too ( gclid, fbclid, msclkid, mc_cid … ), under the name the link used: the
			   capture script only notes that one was there, but it needs it in the address to do so. */
			$clicks = self::click_names();
			foreach ( $_GET as $name => $raw ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- as above; each value is sanitized below.
				$name = (string) $name;
				if ( isset( $tags[ $name ] ) || ! isset( $clicks[ strtolower( $name ) ] ) || ! is_scalar( $raw ) ) {
					continue;
				}
				$value = self::cut( sanitize_text_field( wp_unslash( (string) $raw ) ), 255 );
				if ( '' !== $value && preg_match( '/^[A-Za-z0-9_\-]{1,64}$/', $name ) ) {
					$tags[ $name ] = rawurlencode( $value );
				}
			}
			return $tags;
		}

		/**
		 * Cut a value to a number of characters, never inside a multibyte character ( a cut one is invalid UTF-8, and
		 * WordPress then refuses the whole order update ).
		 *
		 * @since 6.0.2
		 * @param string $value Value.
		 * @param int    $max   Characters.
		 * @return string
		 */
		public static function cut( $value, $max ) {
			$value = (string) $value;
			$value = function_exists( 'mb_substr' ) ? mb_substr( $value, 0, (int) $max, 'UTF-8' ) : substr( $value, 0, (int) $max );
			return function_exists( 'wp_check_invalid_utf8' ) ? wp_check_invalid_utf8( $value, true ) : $value;
		}

		/*
		------------------------------------------------------------------ */
		/*
		Storefront script                                                   */
		/* ------------------------------------------------------------------ */

		/** Action wp_enqueue_scripts: the capture script on every storefront page. */
		public static function enqueue() {
			if ( ! self::enabled() || ! apply_filters( 'wp_easycart_order_sources_script', true ) ) {
				return;
			}
			wp_enqueue_script(
				'wpeasycart_order_source_js',
				plugins_url( 'wp-easycart/design/theme/base-responsive-v3/ec-order-source.js', EC_PLUGIN_DIRECTORY ),
				/* 6.0.2: after the WP Consent API's script when it is there, so the consent check can run. */
				wp_script_is( 'wp-consent-api', 'registered' ) ? array( 'wp-consent-api' ) : array(),
				EC_CURRENT_VERSION,
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				)
			);
			wp_add_inline_script( 'wpeasycart_order_source_js', 'window.wpeasycart_order_source = ' . wp_json_encode( self::script_config() ) . ';', 'before' );
		}

		/** @return array What the capture script needs ( the same on every page, so cached pages carry it ). */
		public static function script_config() {
			$lists = self::lists();
			$home  = self::clean_host( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
			$skip  = array();
			$ids   = array();
			foreach ( array( 'ec_option_cartpage', 'ec_option_accountpage' ) as $option ) {
				$page_id = (int) get_option( $option );
				$link    = $page_id > 0 ? get_permalink( $page_id ) : '';
				$path    = $link ? (string) wp_parse_url( $link, PHP_URL_PATH ) : '';
				if ( '' !== $path && '/' !== $path ) {
					$skip[] = untrailingslashit( $path );
				}
				if ( $page_id > 0 ) {
					$ids[] = $page_id;
				}
			}
			return array(
				'cookie'      => self::COOKIE,
				'days'        => self::FIRST_DAYS,
				'session'     => self::SESSION_MINUTES,
				'consent'     => self::consent_category(),
				/* 6.0.2: the WP Consent API is active, so a missing wp_has_consent() in the browser means "not loaded yet"
				   ( a script optimiser holding it back ), never "allowed". */
				'consent_api' => function_exists( 'wp_has_consent' ),
				/* 6.0.2: Cookie consent reads Google Consent Mode, Cookiebot, CookieYes, Complianz or any banner ( mode() is where the
				   answer is read: the banner found, also when the WP Consent API was chosen without its plugin ): ask the consent bridge. */
				'bridge'      => class_exists( 'wp_easycart_consent' ) && ! in_array( wp_easycart_consent::mode(), array( 'off', 'wp_consent_api' ), true ),
				'secure'      => is_ssl(),
				'path'        => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/',
				'domain'      => defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
				'site'        => $home,
				'skip'        => $skip,
				'skip_ids'    => $ids, /* 6.0.2: the same pages with plain permalinks ( ?page_id=N ) */
				'ignore'      => array_values( array_unique( $lists['ignore'] ) ),
				'strong'      => array_merge( array_keys( $lists['ai'] ), array_keys( $lists['search'] ) ),
				'clicks'      => array_keys( self::click_names() ), /* 6.0.2: ad and email click IDs, in lower case */
				'visits'      => class_exists( 'wp_easycart_store_activity' ) ? wp_easycart_store_activity::visit_url() : '', /* 6.0.2: Reports' visits by source */
			);
		}

		/*
		------------------------------------------------------------------ */
		/*
		Privacy                                                             */
		/* ------------------------------------------------------------------ */

		/** Action admin_init: suggested text for the site's privacy policy. */
		public static function privacy_policy() {
			if ( ! function_exists( 'wp_add_privacy_policy_content' ) || ! self::enabled() ) {
				return;
			}
			$text  = '<p class="privacy-policy-tutorial">' . esc_html__( 'WP EasyCart records how a shopper found the store so that each order can show where it came from. You may want to describe this in your cookie and privacy notices. In the EU and the UK this kind of record usually needs consent: with a cookie banner that supports the WP Consent API, WP EasyCart waits for the consent you choose under Settings › Integrations › Order sources.', 'wp-easycart' ) . '</p>';
			$text .= '<p><strong class="privacy-policy-tutorial">' . esc_html__( 'Suggested text:', 'wp-easycart' ) . ' </strong>' . esc_html__( 'When you visit our store we note the website or campaign that brought you here, the page you arrived on, and when. We keep this in a cookie called wpec_source for up to 90 days and, if you place an order, save it with your order so we can see which of our channels work. It does not include your IP address or identify you on other websites.', 'wp-easycart' ) . '</p>';
			wp_add_privacy_policy_content( 'WP EasyCart', wp_kses_post( $text ) );
		}

		/** Action init: describe the cookie to consent plugins that list cookies ( WP Consent API ). */
		public static function cookie_info() {
			if ( ! function_exists( 'wp_add_cookie_info' ) || ! self::enabled() ) {
				return;
			}
			wp_add_cookie_info(
				self::COOKIE,
				'WP EasyCart',
				self::consent_category(),
				__( '90 days', 'wp-easycart' ),
				__( 'Remembers the website or campaign that brought you to the store, so your order can record it.', 'wp-easycart' )
			);
		}

		/*
		------------------------------------------------------------------ */
		/*
		Admin                                                               */
		/* ------------------------------------------------------------------ */

		/**
		 * Action admin_enqueue_scripts: the chip and card styles on the orders, Reports and customer screens.
		 *
		 * @param string $hook Screen hook.
		 */
		public static function admin_assets( $hook = '' ) {
			$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which admin screen is open, read only.
			if ( ! in_array( $page, array( 'wp-easycart-orders', 'wp-easycart-dashboard', 'wp-easycart-users' ), true ) ) {
				return;
			}
			wp_enqueue_style( 'wp_easycart_order_sources_css', plugins_url( 'wp-easycart/admin/css/order-sources-v2.css', EC_PLUGIN_DIRECTORY ), array(), EC_CURRENT_VERSION );
		}

		/**
		 * A source as a chip.
		 *
		 * @param string $type Type.
		 * @param string $name Name.
		 * @return string HTML.
		 */
		public static function chip( $type, $name ) {
			$type = sanitize_key( (string) $type );
			if ( '' === $type ) {
				return '<span class="ecv2-order-source-chip is-none" title="' . esc_attr__( 'Not recorded', 'wp-easycart' ) . '">' . esc_html__( 'Not recorded', 'wp-easycart' ) . '</span>';
			}
			$label = self::type_label( $type );
			$text  = ( '' !== (string) $name && 'direct' !== $type ) ? (string) $name : $label;
			return '<span class="ecv2-order-source-chip is-' . esc_attr( $type ) . '" title="' . esc_attr( $label ) . '"><i aria-hidden="true"></i>' . esc_html( $text ) . '</span>';
		}

		/**
		 * The order screen's Source card: WP EasyCart PRO prints it in full, otherwise the name with the details locked.
		 *
		 * @param object $order The order screen's order.
		 */
		public static function admin_card( $order ) {
			if ( ! is_object( $order ) || empty( $order->order_id ) || ! self::ready() ) {
				return;
			}
			$row = self::order_row( (int) $order->order_id );
			if ( ! $row ) {
				return;
			}
			if ( self::pro() ) {
				/**
				 * WP EasyCart PRO prints the full Source card.
				 *
				 * @since 6.0.2
				 * @param object $row   order_row().
				 * @param object $order The order screen's order.
				 */
				do_action( 'wp_easycart_admin_order_source_card', $row, $order );
				return;
			}
			$onclick = class_exists( 'wp_easycart_admin_upsell' ) ? wp_easycart_admin_upsell::onclick( 'order_sources', 'details' ) : 'return false;';
			$badge   = class_exists( 'wp_easycart_admin_upsell' ) ? wp_easycart_admin_upsell::badge_for( 'order_sources', 'pro', 'details' ) : 'Pro';
			?>
			<div class="ecdv2-card ecodv2-card-source">
				<div class="ecdv2-card-header">
					<h3 class="ecdv2-card-title"><?php esc_html_e( 'Source', 'wp-easycart' ); ?></h3>
					<span class="ecv2-cl-pro-badge"><?php echo esc_html( $badge ); ?></span>
				</div>
				<div class="ecdv2-card-body">
					<div class="ecodv2-source-lead"><?php echo self::chip( $row->source_type, $row->source_name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- chip() escapes every part. ?></div>
					<button type="button" class="ecodv2-source-locked" onclick="<?php echo esc_attr( $onclick ); ?>">
						<span class="ecodv2-source-mock" aria-hidden="true">
							<span><b><?php esc_html_e( 'First visit', 'wp-easycart' ); ?></b><i></i></span>
							<span><b><?php esc_html_e( 'Latest visit', 'wp-easycart' ); ?></b><i></i></span>
							<span><b><?php esc_html_e( 'Visits', 'wp-easycart' ); ?></b><i></i></span>
							<span><b><?php esc_html_e( 'Landing page', 'wp-easycart' ); ?></b><i></i></span>
						</span>
						<span class="ecodv2-source-cta"><span class="dashicons dashicons-lock"></span> <?php esc_html_e( 'See each visit, the landing page and campaign tags', 'wp-easycart' ); ?></span>
					</button>
				</div>
			</div>
			<?php
		}

		/** Reports: WP EasyCart PRO prints Sales by source, otherwise an example under a soft lock. */
		public static function reports_card() {
			if ( ! self::ready() ) {
				return;
			}
			if ( self::pro() ) {
				/**
				 * WP EasyCart PRO prints the Sales by source card.
				 *
				 * @since 6.0.2
				 */
				do_action( 'wp_easycart_admin_reports_order_sources' );
				return;
			}
			$onclick = class_exists( 'wp_easycart_admin_upsell' ) ? wp_easycart_admin_upsell::onclick( 'order_sources', 'reports' ) : 'return false;';
			$badge   = class_exists( 'wp_easycart_admin_upsell' ) ? wp_easycart_admin_upsell::badge_for( 'order_sources', 'pro', 'reports' ) : 'Pro';
			$rows    = array(
				array( 'search', __( 'Search', 'wp-easycart' ), 71, 33 ),
				array( 'direct', __( 'Direct', 'wp-easycart' ), 58, 22 ),
				array( 'ai', __( 'AI assistants', 'wp-easycart' ), 19, 13 ),
				array( 'social', __( 'Social', 'wp-easycart' ), 27, 11 ),
				array( 'email', __( 'Email', 'wp-easycart' ), 18, 9 ),
				array( 'paid', __( 'Paid ads', 'wp-easycart' ), 11, 7 ),
			);
			?>
			<div class="ecrp-sources is-locked">
				<div class="ecrp-sources-head">
					<h3><?php esc_html_e( 'Sales by source', 'wp-easycart' ); ?></h3>
					<span class="ecv2-cl-pro-badge"><?php echo esc_html( $badge ); ?></span>
				</div>
				<button type="button" class="ecrp-sources-locked" onclick="<?php echo esc_attr( $onclick ); ?>">
					<span class="ecrp-sources-example" aria-hidden="true">
						<?php foreach ( $rows as $wpec_row ) : ?>
						<span class="ecrp-sources-row"><span class="ecv2-order-source-chip is-<?php echo esc_attr( $wpec_row[0] ); ?>"><i></i><?php echo esc_html( $wpec_row[1] ); ?></span><span class="ecrp-sources-bar"><i class="is-<?php echo esc_attr( $wpec_row[0] ); ?>" style="width:<?php echo (int) $wpec_row[3] * 3; ?>%"></i></span><span class="ecrp-sources-num"><?php echo (int) $wpec_row[2]; ?></span></span>
						<?php endforeach; ?>
					</span>
					<span class="ecrp-sources-cta">
						<span class="dashicons dashicons-lock"></span>
						<strong><?php esc_html_e( 'See which sources bring your sales, ChatGPT and other AI assistants included', 'wp-easycart' ); ?></strong>
						<span><?php esc_html_e( 'Every order already records where it came from. The example above shows how the card breaks them down.', 'wp-easycart' ); ?></span>
					</span>
				</button>
			</div>
			<?php
		}
	}

	wp_easycart_order_source::init();

endif;
