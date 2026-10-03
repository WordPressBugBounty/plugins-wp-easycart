<?php
/**
 * WP EasyCart cookie consent bridge ( 6.0.2 ).
 *
 * Settings › Integrations › Cookie consent ( ec_option_marketing_consent, setting() ) says where the shopper's answer comes
 * from: off ( tags load without asking ), auto ( the cookie banner found on this site ), wp_consent_api ( the WP Consent API
 * plugin ), google_consent_mode ( Google Consent Mode v2, set by the store's cookie banner ), cookiebot, cookieyes or complianz
 * ( those banners' own answers ). source() ( also mode() ) is where the answer is really read:
 *
 * - auto: the banner plugin found on this site ( found(): Complianz, CookieYes, Cookiebot, first one wins ), else the WP Consent
 *   API when its plugin is active, else 'any' ( every banner EasyCart can read, asked in the shopper's browser, for banners added
 *   with a script );
 * - wp_consent_api without the WP Consent API plugin: the same as auto. Complianz and the others only talk to the WP Consent API
 *   when that separate plugin is installed, so without it nothing would ever answer; their own cookies still do.
 *
 * status() explains the choice to the merchant ( Settings › Integrations › Cookie consent, Store Status ).
 *
 * Consent decides tracking only: the Meta Pixel, Google tags, order sources and server events
 * ( wp_easycart_has_marketing_consent() ). Checkout and newsletter sign-up never ask it; the newsletter box is its own consent
 * to email. While consent is asked the retired Universal Analytics tag no longer loads ( Google stopped processing it in 2024 )
 * and the old Google Ads conversion tag on the order confirmation page waits for marketing consent ( option filters below ).
 *
 * - In PHP, has( $category ) reads the answer from the request's cookies ( the banner's own cookie, else the wpec_consent copy
 *   the browser keeps ); wp_easycart_has_marketing_consent() calls it.
 * - In the browser, window.wpeasycart_consent ( printed first in wp_head ) answers has( category ) and known( category ), calls
 *   on( fn ) listeners and fires the DOM event wpeasycart_consent_change ( detail: { marketing, statistics } ) when the answer
 *   changes. It keeps the wpec_consent cookie ( m1s0 style ) so the next request sees the same answer, which Google Consent
 *   Mode needs: its state lives only in the page. A store page opened with ?wpec_consent_check=1 shows a small panel ( and a
 *   console line ) with what was read, for the rest of that browser tab's session; ?wpec_consent_check=0 ends it.
 *
 * Categories are the WP Consent API's names; marketing ( ads ) and statistics ( analytics ) are the two EasyCart uses.
 *
 * @package wp-easycart
 * @since 6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_consent' ) ) :

	/**
	 * Where the shopper's consent comes from, and what it says.
	 */
	class wp_easycart_consent {

		/** The browser's copy of the answer ( "m1s0": marketing yes, statistics no ). */
		const COOKIE = 'wpec_consent';

		/** Query argument that turns the storefront consent check on ( 1 ) or off ( 0 ) for a browser tab. */
		const CHECK = 'wpec_consent_check';

		/**
		 * This request's detection and sources.
		 *
		 * @var array
		 */
		private static $cache = array();

		/**
		 * The choices of Settings › Integrations › Cookie consent.
		 *
		 * @return array mode => label.
		 */
		public static function modes() {
			return array(
				'off'                 => __( 'Load tags without asking', 'wp-easycart' ),
				'auto'                => __( 'Follow my cookie banner ( find it automatically )', 'wp-easycart' ),
				'wp_consent_api'      => __( 'WP Consent API ( banners that support it )', 'wp-easycart' ),
				'google_consent_mode' => __( 'Google Consent Mode v2 ( set by my cookie banner )', 'wp-easycart' ),
				'cookiebot'           => __( 'Cookiebot', 'wp-easycart' ),
				'cookieyes'           => __( 'CookieYes', 'wp-easycart' ),
				'complianz'           => __( 'Complianz', 'wp-easycart' ),
			);
		}

		/**
		 * The choices' keys, untranslated. Checking a value against modes() translates every label, and the checkout session
		 * asks for consent while plugins load ( wp_easycart_order_source::consent_given() ), before WordPress allows
		 * translations ( WordPress 6.7 logs "_load_textdomain_just_in_time was called incorrectly" ).
		 *
		 * @since 6.0.2
		 * @return array
		 */
		public static function keys() {
			return array( 'off', 'auto', 'wp_consent_api', 'google_consent_mode', 'cookiebot', 'cookieyes', 'complianz' );
		}

		/**
		 * The saved choice ( an unknown value counts as off ).
		 *
		 * @return string
		 */
		public static function setting() {
			$mode = (string) get_option( 'ec_option_marketing_consent', 'off' );
			return in_array( $mode, self::keys(), true ) ? $mode : 'off';
		}

		/**
		 * Where the answer is read ( source() ). Kept for callers from before auto existed: they all want the reader.
		 *
		 * @return string off | wp_consent_api | google_consent_mode | cookiebot | cookieyes | complianz | any
		 */
		public static function mode() {
			return self::source();
		}

		/**
		 * Where the answer is read for a choice ( default: the saved one ).
		 *
		 * @param string|null $setting A choice of modes().
		 * @return string off | wp_consent_api | google_consent_mode | cookiebot | cookieyes | complianz | any
		 */
		public static function source( $setting = null ) {
			$setting = ( null === $setting ) ? self::setting() : (string) $setting;
			if ( ! in_array( $setting, self::keys(), true ) ) {
				$setting = 'off';
			}
			$key = 'source_' . $setting;
			if ( isset( self::$cache[ $key ] ) && self::cacheable() ) {
				return self::$cache[ $key ];
			}
			$source = $setting;
			if ( 'auto' === $setting || ( 'wp_consent_api' === $setting && ! self::api_active() ) ) {
				$found = self::found();
				if ( $found ) {
					$ids    = array_keys( $found );
					$source = $ids[0];
				} elseif ( self::api_active() ) {
					$source = 'wp_consent_api';
				} else {
					$source = 'any';
				}
			}
			/**
			 * Where WP EasyCart reads the shopper's cookie consent.
			 *
			 * @since 6.0.2
			 * @param string $source  off ( tags load without asking ) | wp_consent_api | google_consent_mode | cookiebot | cookieyes |
			 *                        complianz | any ( every banner WP EasyCart can read ).
			 * @param string $setting The choice on Settings › Integrations › Cookie consent.
			 */
			$filtered = (string) apply_filters( 'wp_easycart_consent_source', $source, $setting );
			if ( in_array( $filtered, self::sources(), true ) ) {
				$source = $filtered;
			}
			if ( self::cacheable() ) {
				self::$cache[ $key ] = $source;
			}
			return $source;
		}

		/**
		 * Can this request's detection be kept? Not before every plugin has loaded ( a banner's constants are not there yet ).
		 *
		 * @return bool
		 */
		private static function cacheable() {
			return ! function_exists( 'did_action' ) || did_action( 'plugins_loaded' );
		}

		/**
		 * Every value source() can answer.
		 *
		 * @return array
		 */
		public static function sources() {
			return array( 'off', 'wp_consent_api', 'google_consent_mode', 'cookiebot', 'cookieyes', 'complianz', 'any' );
		}

		/**
		 * Do tags wait for the shopper's answer?
		 *
		 * @return bool
		 */
		public static function asks() {
			return 'off' !== self::setting();
		}

		/**
		 * Forget this request's detection ( a plugin was switched on or off ).
		 */
		public static function flush() {
			self::$cache = array();
		}

		/* === What is on this site === */

		/**
		 * The cookie banners WP EasyCart reads directly, in the order auto prefers them.
		 *
		 * @return array id => array( name, plugins ( plugin files ), loaded ( its code is running ) ).
		 */
		public static function banners() {
			return array(
				'complianz' => array(
					'name'    => 'Complianz',
					'plugins' => array( 'complianz-gdpr/complianz-gpdr.php', 'complianz-gdpr-premium/complianz-gpdr-premium.php' ),
					'loaded'  => defined( 'CMPLZ_VERSION' ) || class_exists( 'COMPLIANZ', false ),
				),
				'cookieyes' => array(
					'name'    => 'CookieYes',
					'plugins' => array( 'cookie-law-info/cookie-law-info.php' ),
					'loaded'  => defined( 'CKY_APP_URL' ) || defined( 'CLI_PLUGIN_BASENAME' ) || class_exists( 'Cookie_Law_Info', false ),
				),
				'cookiebot' => array(
					'name'    => 'Cookiebot',
					'plugins' => array( 'cookiebot/cookiebot.php' ),
					'loaded'  => defined( 'CYBOT_COOKIEBOT_PLUGIN_DIR' ) || defined( 'CYBOT_COOKIEBOT_VERSION' ) || class_exists( 'Cookiebot_WP', false ) || function_exists( 'cybot\cookiebot\lib\cookiebot' ),
				),
			);
		}

		/**
		 * The banner plugins active on this site.
		 *
		 * @return array id => name, in the order of banners().
		 */
		public static function found() {
			if ( isset( self::$cache['found'] ) && self::cacheable() ) {
				return self::$cache['found'];
			}
			$found = array();
			foreach ( self::banners() as $id => $banner ) {
				if ( $banner['loaded'] || self::plugin_active( $banner['plugins'] ) ) {
					$found[ $id ] = $banner['name'];
				}
			}
			if ( self::cacheable() ) {
				self::$cache['found'] = $found;
			}
			return $found;
		}

		/**
		 * Is the WP Consent API plugin running?
		 *
		 * @return bool
		 */
		public static function api_active() {
			return function_exists( 'wp_has_consent' );
		}

		/**
		 * Is one of these plugin files active ( on this site or the whole network )?
		 *
		 * @param array $files Plugin files ( folder/file.php ).
		 * @return bool
		 */
		private static function plugin_active( $files ) {
			$active  = (array) get_option( 'active_plugins', array() );
			$network = ( function_exists( 'is_multisite' ) && is_multisite() ) ? (array) get_site_option( 'active_sitewide_plugins', array() ) : array();
			foreach ( (array) $files as $file ) {
				if ( in_array( $file, $active, true ) || isset( $network[ $file ] ) ) {
					return true;
				}
			}
			return false;
		}

		/* === The answer === */

		/**
		 * Has the shopper agreed to a category, going by this request? Without an answer yet, no.
		 *
		 * @param string $category marketing | statistics ( or another WP Consent API category ).
		 * @return bool
		 */
		public static function has( $category = 'marketing' ) {
			$category = sanitize_key( $category );
			if ( '' === $category ) {
				$category = 'marketing';
			}
			$source = self::source();
			if ( 'off' === $source ) {
				return true;
			}
			$answer = self::answer( $source, $category );
			if ( null === $answer ) {
				$answer = self::mirrored( $category );
			}
			return true === $answer;
		}

		/**
		 * One source's answer for a category, from this request.
		 *
		 * @param string $source   A value of sources().
		 * @param string $category Category.
		 * @return bool|null Null: no answer yet ( Google Consent Mode only answers in the page ).
		 */
		public static function answer( $source, $category ) {
			switch ( $source ) {
				case 'wp_consent_api':
					if ( function_exists( 'wp_has_consent' ) ) {
						return (bool) wp_has_consent( $category );
					}
					return self::api_cookie( $category );
				case 'cookiebot':
					return self::cookiebot( $category );
				case 'cookieyes':
					return self::cookieyes( $category );
				case 'complianz':
					return self::complianz( $category );
				case 'any':
					$answer = self::complianz( $category );
					if ( null === $answer ) {
						$answer = self::cookieyes( $category );
					}
					if ( null === $answer ) {
						$answer = self::cookiebot( $category );
					}
					if ( null === $answer ) {
						$answer = self::api_cookie( $category );
					}
					return $answer;
			}
			return null;
		}

		/**
		 * The WP Consent API's cookie ( wp_consent_marketing=allow|deny ), for when its script ran but its plugin is not here.
		 *
		 * @param string $category Category.
		 * @return bool|null
		 */
		private static function api_cookie( $category ) {
			$raw = self::cookie_value( 'wp_consent_' . $category );
			return ( null === $raw ) ? null : ( 'allow' === $raw );
		}

		/**
		 * Complianz's cookies: cmplz_marketing=allow|deny ( prefix cmplz_rt_ on a network's main site that keeps its own ).
		 *
		 * @param string $category Category.
		 * @return bool|null
		 */
		private static function complianz( $category ) {
			foreach ( array_unique( array( self::complianz_prefix(), 'cmplz_', 'cmplz_rt_' ) ) as $prefix ) {
				$raw = self::cookie_value( $prefix . $category );
				if ( null !== $raw && '' !== $raw ) {
					return 'allow' === $raw;
				}
			}
			return null;
		}

		/**
		 * The prefix Complianz gives its cookies on this site.
		 *
		 * @return string
		 */
		public static function complianz_prefix() {
			$prefix = 'cmplz_';
			if ( class_exists( 'COMPLIANZ', false ) && isset( COMPLIANZ::$banner_loader ) && is_object( COMPLIANZ::$banner_loader ) && method_exists( COMPLIANZ::$banner_loader, 'get_cookie_prefix' ) ) {
				try {
					$theirs = (string) COMPLIANZ::$banner_loader->get_cookie_prefix();
					if ( preg_match( '/^[A-Za-z0-9_\-]{1,40}$/', $theirs ) ) {
						$prefix = $theirs;
					}
				} catch ( Throwable $e ) {
					$prefix = 'cmplz_';
				}
			}
			return $prefix;
		}

		/**
		 * Cookiebot's CookieConsent cookie: -1 when the visitor's region needs no consent, else a list such as
		 * {stamp:'…',necessary:true,preferences:false,statistics:true,marketing:false,…}.
		 *
		 * @param string $category Category.
		 * @return bool|null
		 */
		private static function cookiebot( $category ) {
			$raw = self::cookie_value( 'CookieConsent' );
			if ( null === $raw ) {
				return null;
			}
			if ( '-1' === $raw ) {
				return true;
			}
			if ( preg_match( '/' . preg_quote( $category, '/' ) . '\s*:\s*(true|false)/i', $raw, $match ) ) {
				return 'true' === strtolower( $match[1] );
			}
			return null;
		}

		/**
		 * CookieYes' cookieyes-consent cookie: consentid:…,consent:yes,action:yes,necessary:yes,analytics:no,advertisement:no,…
		 * ( marketing is advertisement there, statistics is analytics ). Stores still on its legacy banner keep
		 * viewed_cookie_policy=yes|no and cookielawinfo-checkbox-<category>=yes|no instead.
		 *
		 * @param string $category Category.
		 * @return bool|null
		 */
		private static function cookieyes( $category ) {
			$names = array(
				'marketing'  => 'advertisement',
				'statistics' => 'analytics',
			);
			$key   = isset( $names[ $category ] ) ? $names[ $category ] : $category;
			$raw   = self::cookie_value( 'cookieyes-consent' );
			if ( null !== $raw && preg_match( '/(?:^|,)\s*action\s*:\s*yes/i', $raw ) && preg_match( '/(?:^|,)\s*' . preg_quote( $key, '/' ) . '\s*:\s*(yes|no)/i', $raw, $match ) ) {
				return 'yes' === strtolower( $match[1] );
			}
			$viewed = self::cookie_value( 'viewed_cookie_policy' );
			if ( null === $viewed ) {
				return null; /* the banner has not been answered yet */
			}
			$box = self::cookie_value( 'cookielawinfo-checkbox-' . $key );
			return ( null !== $box ) ? ( 'yes' === strtolower( $box ) ) : ( 'yes' === strtolower( $viewed ) );
		}

		/**
		 * The browser's copy ( window.wpeasycart_consent keeps it ).
		 *
		 * @param string $category Category.
		 * @return bool|null
		 */
		private static function mirrored( $category ) {
			$raw = self::cookie_value( self::COOKIE );
			if ( null === $raw ) {
				return null;
			}
			$letter = ( 'statistics' === $category ) ? 's' : ( ( 'marketing' === $category ) ? 'm' : '' );
			if ( '' === $letter || ! preg_match( '/' . $letter . '([01])/', $raw, $match ) ) {
				return null;
			}
			return '1' === $match[1];
		}

		/**
		 * A request cookie, decoded, or null.
		 *
		 * @param string $name Cookie.
		 * @return string|null
		 */
		private static function cookie_value( $name ) {
			if ( ! isset( $_COOKIE[ $name ] ) || ! is_string( $_COOKIE[ $name ] ) ) {
				return null;
			}
			/* Decoded before cleaning: sanitize_text_field() drops %XX octets, and the banners' cookies are URL-encoded. */
			return sanitize_text_field( rawurldecode( wp_unslash( $_COOKIE[ $name ] ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized after rawurldecode(), see above.
		}

		/* === Words for the merchant === */

		/**
		 * A source's short name.
		 *
		 * @param string $source A value of sources().
		 * @return string
		 */
		public static function label( $source ) {
			$banners = self::banners();
			if ( isset( $banners[ $source ] ) ) {
				return $banners[ $source ]['name'];
			}
			$labels = array(
				'off'                 => __( 'nothing ( tags load without asking )', 'wp-easycart' ),
				'wp_consent_api'      => __( 'WP Consent API', 'wp-easycart' ),
				'google_consent_mode' => __( 'Google Consent Mode v2', 'wp-easycart' ),
				'any'                 => __( 'any cookie banner WP EasyCart can read', 'wp-easycart' ),
			);
			return isset( $labels[ $source ] ) ? $labels[ $source ] : $source;
		}

		/**
		 * What WP EasyCart reads for a source.
		 *
		 * @param string $source A value of sources().
		 * @return string '' for off.
		 */
		public static function reads( $source ) {
			switch ( $source ) {
				case 'wp_consent_api':
					return __( 'The WP Consent API: your cookie banner records the shopper\'s answer there.', 'wp-easycart' );
				case 'complianz':
					$prefix = self::complianz_prefix();
					/* translators: 1: cookie name, 2: cookie name. */
					return sprintf( __( 'Complianz\'s own cookies, %1$s and %2$s.', 'wp-easycart' ), $prefix . 'marketing', $prefix . 'statistics' );
				case 'cookieyes':
					return __( 'CookieYes\' own cookie, cookieyes-consent ( advertisement and analytics ).', 'wp-easycart' );
				case 'cookiebot':
					return __( 'Cookiebot\'s own cookie, CookieConsent ( marketing and statistics ).', 'wp-easycart' );
				case 'google_consent_mode':
					/* 6.0.2: order sources follow the category they wait for ( Settings › Integrations › Order sources ): ad_storage
					   for Marketing, the default, analytics_storage for Statistics. */
					if ( class_exists( 'wp_easycart_order_source' ) && 'statistics' === wp_easycart_order_source::consent_category() ) {
						return __( 'The Google Consent Mode signals your banner sets: ad_storage for ads and the Meta Pixel, analytics_storage for analytics and order sources.', 'wp-easycart' );
					}
					return __( 'The Google Consent Mode signals your banner sets: ad_storage for ads, the Meta Pixel and order sources, analytics_storage for analytics.', 'wp-easycart' );
				case 'any':
					return __( 'Whichever banner answers in the shopper\'s browser: Complianz, CookieYes, Cookiebot or the WP Consent API.', 'wp-easycart' );
			}
			return '';
		}

		/**
		 * How a choice works on this site, for Settings › Integrations › Cookie consent and Store Status.
		 *
		 * @param string|null $setting A choice of modes() ( default: the saved one ).
		 * @return array setting, source, found ( id => name ), api ( bool ), level ( ok | notice | warn | error | off ), message,
		 *               reads, suggest ( a choice to switch to, or '' ), suggest_label, find_api ( offer the WP Consent API plugin ).
		 */
		public static function status( $setting = null ) {
			$setting = ( null === $setting ) ? self::setting() : (string) $setting;
			if ( ! in_array( $setting, self::keys(), true ) ) {
				$setting = 'off';
			}
			$source  = self::source( $setting );
			$found   = self::found();
			$api     = self::api_active();
			$banners = self::banners();
			$names   = array_values( $found );
			$first   = $names ? $names[0] : '';
			$status  = array(
				'setting'       => $setting,
				'source'        => $source,
				'found'         => $found,
				'api'           => $api,
				'level'         => 'ok',
				'message'       => '',
				'reads'         => self::reads( $source ),
				'suggest'       => '',
				'suggest_label' => '',
				'find_api'      => false,
			);
			$many    = ( count( $found ) > 1 )
				/* translators: 1: list of cookie banners, 2: the one WP EasyCart follows. */
				? ' ' . sprintf( __( 'More than one cookie banner is active ( %1$s ): keep one. WP EasyCart follows %2$s.', 'wp-easycart' ), implode( ', ', $names ), $first )
				: '';

			if ( 'off' === $setting ) {
				if ( $found ) {
					$ids             = array_keys( $found );
					$status['level'] = 'error';
					/* translators: %s: cookie banner name. */
					$status['message'] = sprintf( __( '%s is active on this site, but WP EasyCart loads tags without asking. Shoppers who refuse cookies in your banner are still tracked by the Meta Pixel, Google tags and order sources.', 'wp-easycart' ), $first );
					$status['suggest'] = $ids[0];
					/* translators: %s: cookie banner name. */
					$status['suggest_label'] = sprintf( __( 'Follow %s', 'wp-easycart' ), $first );
				} elseif ( $api ) {
					$status['level']         = 'warn';
					$status['message']       = __( 'The WP Consent API is active on this site, but WP EasyCart loads tags without asking. Follow it so tags wait for the answer your cookie banner records there.', 'wp-easycart' );
					$status['suggest']       = 'wp_consent_api';
					$status['suggest_label'] = __( 'Follow the WP Consent API', 'wp-easycart' );
				} else {
					$status['level']   = 'off';
					$status['message'] = __( 'Tags load for every shopper. No cookie banner plugin was found on this site. If your store needs consent ( visitors from the EU or the UK ), add a banner such as Complianz, CookieYes or Cookiebot, then choose Follow my cookie banner.', 'wp-easycart' );
				}
				return $status;
			}

			if ( 'google_consent_mode' === $setting ) {
				$status['level']   = 'notice';
				$status['message'] = ( '' !== $first )
					/* translators: %s: cookie banner name. */
					? sprintf( __( 'Google tags load at once and follow the Google Consent Mode v2 signals your banner sets; the Meta Pixel and order sources wait for the same signals. Turn on Google Consent Mode in %s\'s settings, or nothing is ever granted.', 'wp-easycart' ), $first )
					: __( 'Google tags load at once and follow the Google Consent Mode v2 signals your banner sets; the Meta Pixel and order sources wait for the same signals. Your banner must set Google\'s consent, or nothing is ever granted.', 'wp-easycart' );
				return $status;
			}

			if ( isset( $banners[ $setting ] ) ) {
				$name = $banners[ $setting ]['name'];
				if ( isset( $found[ $setting ] ) ) {
					/* translators: %s: cookie banner name. */
					$status['message'] = sprintf( __( '%s is active on this site. Tags wait until the shopper accepts in its banner.', 'wp-easycart' ), $name ) . $many;
					$status['level']   = ( '' !== $many ) ? 'warn' : 'ok';
				} elseif ( $found ) {
					$ids             = array_keys( $found );
					$status['level'] = 'error';
					/* translators: 1: the chosen cookie banner, 2: the banner active on the site. */
					$status['message'] = sprintf( __( '%1$s isn\'t active on this site, but %2$s is. Tags wait for an answer %1$s never gives, so they never load, and Reports never counts product views, searches or visits.', 'wp-easycart' ), $name, $first );
					$status['suggest'] = $ids[0];
					/* translators: %s: cookie banner name. */
					$status['suggest_label'] = sprintf( __( 'Follow %s', 'wp-easycart' ), $first );
				} else {
					$status['level'] = 'warn';
					/* translators: %s: cookie banner name. */
					$status['message'] = sprintf( __( 'The %s plugin isn\'t active on this site. That\'s fine if you add its banner with a script; without the banner on your store, tags never load and Reports never counts product views, searches or visits.', 'wp-easycart' ), $name );
				}
				return $status;
			}

			if ( 'wp_consent_api' === $setting && $api ) {
				$status['message'] = __( 'The WP Consent API is active. Tags wait for the answer your cookie banner records there, so your banner must support the WP Consent API ( Complianz, CookieYes, Cookiebot and many others do ).', 'wp-easycart' );
				return $status;
			}

			/* auto, or the WP Consent API without its plugin: source() found a banner, the API, or nothing ( 'any' ). */
			if ( 'wp_consent_api' === $setting ) {
				$status['find_api'] = true;
				if ( isset( $banners[ $source ] ) ) {
					$status['level'] = 'notice';
					/* translators: %s: cookie banner name. */
					$status['message'] = sprintf( __( 'The WP Consent API plugin isn\'t active, so WP EasyCart reads %s\'s own answer instead. Tags still wait until the shopper accepts. Follow your banner directly to clear this note, or install the WP Consent API plugin.', 'wp-easycart' ), $first ) . $many;
					$status['suggest'] = $source;
					/* translators: %s: cookie banner name. */
					$status['suggest_label'] = sprintf( __( 'Follow %s', 'wp-easycart' ), $first );
				} else {
					$status['level']   = 'error';
					$status['message'] = __( 'The WP Consent API plugin isn\'t active and no cookie banner plugin WP EasyCart can read was found, so nothing answers until a banner does: the Meta Pixel and Google tags don\'t load, order sources aren\'t recorded and Reports counts no product views, searches or visits. Install the WP Consent API plugin with a banner that supports it, or choose your banner above.', 'wp-easycart' );
				}
				return $status;
			}

			if ( isset( $banners[ $source ] ) ) {
				/* translators: %s: cookie banner name. */
				$status['message'] = sprintf( __( 'Following %s, found on this site. Tags wait until the shopper accepts in its banner.', 'wp-easycart' ), $first ) . $many;
				$status['level']   = ( '' !== $many ) ? 'warn' : 'ok';
			} elseif ( 'wp_consent_api' === $source ) {
				$status['message'] = __( 'Following the WP Consent API, found on this site. Tags wait for the answer your cookie banner records there, so your banner must support the WP Consent API.', 'wp-easycart' );
			} else {
				$status['level']    = 'warn';
				$status['find_api'] = true;
				$status['message']  = __( 'No cookie banner plugin WP EasyCart can read ( Complianz, CookieYes, Cookiebot ) and no WP Consent API plugin was found on this site. If your banner is added with a script ( its code in your theme or tag manager ), WP EasyCart still reads its answer in the shopper\'s browser. Until a banner answers, the Meta Pixel and Google tags don\'t load, order sources aren\'t recorded and Reports counts no product views, searches or visits; without a banner, nothing ever answers. If your store doesn\'t need a cookie banner, choose Load tags without asking.', 'wp-easycart' );
			}
			return $status;
		}

		/**
		 * "Found on this site" in words.
		 *
		 * @return string
		 */
		public static function found_text() {
			$names = array_values( self::found() );
			if ( self::api_active() ) {
				$names[] = __( 'WP Consent API', 'wp-easycart' );
			}
			if ( ! $names ) {
				return __( 'No Complianz, CookieYes, Cookiebot or WP Consent API plugin is active.', 'wp-easycart' );
			}
			$text = implode( ', ', $names );
			if ( ! self::api_active() ) {
				$text .= ' ' . __( '( the WP Consent API plugin isn\'t active )', 'wp-easycart' );
			}
			return $text;
		}

		/**
		 * The storefront page to open for the consent check.
		 *
		 * @return string
		 */
		public static function check_url() {
			$page_id = (int) get_option( 'ec_option_storepage' );
			$url     = ( $page_id > 0 && function_exists( 'get_permalink' ) ) ? get_permalink( $page_id ) : '';
			if ( ! $url ) {
				$url = home_url( '/' );
			}
			return add_query_arg( self::CHECK, '1', $url );
		}

		/**
		 * Store Status rows ( printed by admin/template/status/store-status/store-status.php ).
		 */
		public static function store_status_rows() {
			$status = self::status();
			$link   = ' <a href="' . esc_url( admin_url( 'admin.php?page=wp-easycart-settings&subpage=integrations#ecst-sec-cookie-consent' ) ) . '">' . esc_html__( 'Open Cookie consent', 'wp-easycart' ) . '</a>';
			if ( 'off' === $status['level'] ) {
				self::status_row( true, esc_html__( 'Cookie consent: tags load without asking ( no cookie banner plugin was found ).', 'wp-easycart' ) );
				return;
			}
			$ok = in_array( $status['level'], array( 'ok', 'notice' ), true );
			self::status_row( $ok, esc_html( __( 'Cookie consent:', 'wp-easycart' ) . ' ' . $status['message'] ) . ( 'ok' === $status['level'] ? '' : $link ) );
		}

		/**
		 * One Store Status row, in that screen's markup ( its summary counts these class names ).
		 *
		 * @param bool   $ok   Passed.
		 * @param string $html Escaped label HTML.
		 */
		private static function status_row( $ok, $html ) {
			echo '<div class="' . ( $ok ? 'ec_status_success' : 'ec_status_error' ) . '"><div class="dashicons-before ' . ( $ok ? 'dashicons-yes' : 'dashicons-no' ) . '"></div><span class="ec_status_label">' . $html . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- callers pass escaped text and links built with esc_url() / esc_html__().
		}

		/* === Hooks === */

		/**
		 * Hooks: the browser bridge first in the storefront's head, and the two older Google tags that cannot ask.
		 */
		public static function init() {
			add_action( 'wp_head', array( __CLASS__, 'print_bridge' ), 0 );
			add_filter( 'option_ec_option_googleanalyticsid', array( __CLASS__, 'universal_analytics_id' ) );
			add_filter( 'option_ec_option_google_adwords_conversion_id', array( __CLASS__, 'legacy_conversion_id' ) );
		}

		/**
		 * Is this request a storefront page or a storefront AJAX call ( ec_ajax_* )?
		 *
		 * @return bool
		 */
		private static function storefront() {
			if ( ! is_admin() ) {
				return true;
			}
			if ( ! function_exists( 'wp_doing_ajax' ) || ! wp_doing_ajax() || ! isset( $_REQUEST['action'] ) || ! is_string( $_REQUEST['action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only which AJAX call this is; nothing is changed.
				return false;
			}
			return 0 === strpos( sanitize_key( wp_unslash( $_REQUEST['action'] ) ), 'ec_ajax_' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only which AJAX call this is; nothing is changed.
		}

		/**
		 * Filter option_ec_option_googleanalyticsid: the retired Universal Analytics tag ( analytics.js ) never asks for consent
		 * and Google stopped processing its data in 2024, so the storefront drops it while consent is asked.
		 *
		 * @param mixed $value Saved ID.
		 * @return mixed
		 */
		public static function universal_analytics_id( $value ) {
			if ( '' !== (string) $value && self::asks() && self::storefront() ) {
				return '';
			}
			return $value;
		}

		/**
		 * Filter option_ec_option_google_adwords_conversion_id: the older Google Ads conversion tag ( conversion.js on the order
		 * confirmation page ) runs only with marketing consent while consent is asked.
		 *
		 * @param mixed $value Saved conversion ID.
		 * @return mixed
		 */
		public static function legacy_conversion_id( $value ) {
			if ( '' === (string) $value || ! self::asks() || ! self::storefront() ) {
				return $value;
			}
			$has = function_exists( 'wp_easycart_has_marketing_consent' ) ? wp_easycart_has_marketing_consent( 'marketing' ) : self::has( 'marketing' );
			return $has ? $value : '';
		}

		/**
		 * Action wp_head: window.wpeasycart_consent, before the Meta Pixel and Google tags ask it.
		 */
		public static function print_bridge() {
			if ( ! self::asks() || is_admin() ) {
				return;
			}
			$source = self::source();
			$config = array(
				'mode'    => $source,
				'setting' => self::setting(),
				'label'   => self::label( $source ),
				'prefix'  => self::complianz_prefix(),
				'cookie'  => self::COOKIE,
				'path'    => ( defined( 'COOKIEPATH' ) && COOKIEPATH ) ? COOKIEPATH : '/',
				/* The consent check panel ( ?wpec_consent_check=1 ). */
				'i18n'    => array(
					'title'      => __( 'WP EasyCart consent check', 'wp-easycart' ),
					'reads'      => __( 'Reading', 'wp-easycart' ),
					'marketing'  => __( 'Marketing ( ads )', 'wp-easycart' ),
					'statistics' => __( 'Statistics ( analytics )', 'wp-easycart' ),
					'yes'        => __( 'accepted', 'wp-easycart' ),
					'no'         => __( 'refused', 'wp-easycart' ),
					'unknown'    => __( 'no answer yet', 'wp-easycart' ),
					'pixel'      => __( 'Meta Pixel, Google Ads', 'wp-easycart' ),
					'analytics'  => __( 'Google Analytics', 'wp-easycart' ),
					'on'         => __( 'running', 'wp-easycart' ),
					'off'        => __( 'held back', 'wp-easycart' ),
					'hint'       => __( 'Answer your cookie banner and watch this change. Buying and newsletter sign-up work either way.', 'wp-easycart' ),
					'close'      => __( 'Close the consent check', 'wp-easycart' ),
				),
			);
			echo '<script id="wpeasycart-consent">window.wpeasycart_consent_config=' . wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP ) . ';' . self::bridge_js() . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON config and a fixed script.
		}

		/**
		 * The browser bridge ( no dependencies; runs before jQuery ).
		 *
		 * Readers answer true, false or null ( no answer yet ); 'any' asks Complianz, CookieYes, Cookiebot and the WP Consent API
		 * cookie in turn. Banners announce answers with their own events, and a click anywhere is checked again shortly after
		 * ( a banner's buttons ), so a banner whose events are missed still counts on the same page.
		 *
		 * @return string
		 */
		public static function bridge_js() {
			return <<<'JS'
(function(w,d){var cfg=w.wpeasycart_consent_config||{},mode=cfg.mode||'off',t=cfg.i18n||{},fns=[],last=null,gcm={},by='',box=null,check=false;
function ck(n){var m=d.cookie.match(new RegExp('(?:^|;\\s*)'+n.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')+'=([^;]*)'));if(!m){return null;}try{return decodeURIComponent(m[1]);}catch(e){return m[1];}}
function gcmScan(list){var i,a;if(!list||!list.length){return;}for(i=0;i<list.length;i++){a=list[i];if(a&&'consent'===a[0]&&('default'===a[1]||'update'===a[1])&&a[2]&&'object'===typeof a[2]){if(a[2].ad_storage){gcm.ad_storage=a[2].ad_storage;}if(a[2].analytics_storage){gcm.analytics_storage=a[2].analytics_storage;}}}}
function api(cat){var v;if('function'===typeof w.wp_has_consent){try{return!!w.wp_has_consent(cat);}catch(e){}}v=ck('wp_consent_'+cat);return null===v?null:'allow'===v;}
function cookiebot(cat){var v,m;if(w.Cookiebot&&w.Cookiebot.consent&&(w.Cookiebot.hasResponse||w.Cookiebot.consented||w.Cookiebot.declined)){return!!w.Cookiebot.consent[cat];}v=ck('CookieConsent');if(null===v){return null;}if('-1'===v){return true;}m=v.match(new RegExp(cat+'\\s*:\\s*(true|false)','i'));return m?'true'===m[1].toLowerCase():null;}
function cookieyes(cat){var k=('statistics'===cat)?'analytics':(('marketing'===cat)?'advertisement':cat),v,m;if('function'===typeof w.getCkyConsent){try{v=w.getCkyConsent();if(v&&v.isUserActionCompleted&&v.categories){return!!v.categories[k];}}catch(e){}}v=ck('cookieyes-consent');if(null!==v&&/(?:^|,)\s*action\s*:\s*yes/i.test(v)){m=v.match(new RegExp('(?:^|,)\\s*'+k+'\\s*:\\s*(yes|no)','i'));if(m){return'yes'===m[1].toLowerCase();}}v=ck('viewed_cookie_policy');if(null===v){return null;}m=ck('cookielawinfo-checkbox-'+k);return'yes'===String(null!==m?m:v).toLowerCase();}
function complianz(cat){var p=[(w.complianz&&w.complianz.prefix)?w.complianz.prefix:'',cfg.prefix||'','cmplz_','cmplz_rt_'],i,v=null;for(i=0;i<p.length&&!v;i++){if(p[i]){v=ck(p[i]+cat);}}if(!v){return null;}if('function'===typeof w.cmplz_has_consent){try{return!!w.cmplz_has_consent(cat);}catch(e){}}return'allow'===v;}
function gcmAsk(cat){var k=('statistics'===cat)?'analytics_storage':'ad_storage';return gcm[k]?'granted'===gcm[k]:null;}
function any(cat){var r=[[complianz,'Complianz'],[cookieyes,'CookieYes'],[cookiebot,'Cookiebot'],[api,'WP Consent API']],i,v;for(i=0;i<r.length;i++){v=r[i][0](cat);if(null!==v){by=r[i][1];return v;}}return null;}
function ask(cat){switch(mode){
case 'off':return true;
case 'wp_consent_api':return api(cat);
case 'google_consent_mode':return gcmAsk(cat);
case 'cookiebot':return cookiebot(cat);
case 'cookieyes':return cookieyes(cat);
case 'complianz':return complianz(cat);
case 'any':return any(cat);}
return null;}
function mirror(){var c=cfg.cookie||'wpec_consent',m=ask('marketing'),s=ask('statistics'),v;if(null===m&&null===s){return;}v='m'+(m?1:0)+'s'+(s?1:0);if(ck(c)!==v){d.cookie=c+'='+v+';path='+(cfg.path||'/')+';max-age=15552000;SameSite=Lax'+('https:'===w.location.protocol?';Secure':'');}}
function word(v){return null===v?(t.unknown||'no answer yet'):(v?(t.yes||'accepted'):(t.no||'refused'));}
function run(v){return v?(t.on||'running'):(t.off||'held back');}
function show(state){var src=(cfg.label||mode)+(by&&'any'===mode?' ( '+by+' )':''),rows,i,el,b;if(!check){return;}try{w.console.info('[WP EasyCart consent] '+(t.reads||'Reading')+': '+src+' | '+(t.marketing||'Marketing')+': '+word(state.marketing)+' | '+(t.statistics||'Statistics')+': '+word(state.statistics)+' | '+(t.pixel||'Meta Pixel, Google Ads')+': '+run(true===state.marketing)+' | '+(t.analytics||'Google Analytics')+': '+run(true===state.statistics));}catch(e){}if(!d.body){return;}if(!box){box=d.createElement('div');box.id='wpeasycart-consent-check';box.setAttribute('role','status');box.setAttribute('style','position:fixed;top:12px;right:12px;z-index:2147483646;max-width:300px;background:#111827;color:#f9fafb;font:13px/1.45 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;padding:12px 14px;border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.3);text-align:left');d.body.appendChild(box);}while(box.firstChild){box.removeChild(box.firstChild);}rows=[[t.title||'WP EasyCart consent check',''],[t.reads||'Reading',src],[t.marketing||'Marketing',word(state.marketing)],[t.statistics||'Statistics',word(state.statistics)],[t.pixel||'Meta Pixel, Google Ads',run(true===state.marketing)],[t.analytics||'Google Analytics',run(true===state.statistics)],['',t.hint||'']];for(i=0;i<rows.length;i++){el=d.createElement('div');if(0===i){el.style.fontWeight='600';el.style.marginBottom='6px';el.style.paddingRight='18px';}else if(rows[i][0]){b=d.createElement('span');b.style.color='#9ca3af';b.textContent=rows[i][0]+': ';el.appendChild(b);}else{el.style.marginTop='6px';el.style.color='#d1d5db';el.style.fontSize='12px';}el.appendChild(d.createTextNode(0===i?rows[i][0]:rows[i][1]));box.appendChild(el);}b=d.createElement('button');b.type='button';b.textContent='×';b.setAttribute('aria-label',t.close||'Close');b.setAttribute('style','position:absolute;top:6px;right:8px;background:none;border:0;color:#9ca3af;font-size:18px;line-height:1;cursor:pointer;padding:2px');b.onclick=function(){try{w.sessionStorage.removeItem('wpec_consent_check');}catch(e){}check=false;box.parentNode.removeChild(box);box=null;};box.appendChild(b);}
function notify(force){var state={marketing:ask('marketing'),statistics:ask('statistics')},key=state.marketing+'|'+state.statistics,i,ev;if(key===last&&true!==force){return;}var changed=(key!==last);last=key;show(state);if(!changed){return;}mirror();for(i=0;i<fns.length;i++){try{fns[i](state);}catch(e){}}try{ev=new CustomEvent('wpeasycart_consent_change',{detail:state});}catch(e){ev=d.createEvent('CustomEvent');ev.initCustomEvent('wpeasycart_consent_change',false,false,state);}d.dispatchEvent(ev);}
function later(){setTimeout(notify,0);}
try{var q=w.location.search||'';if(/[?&]wpec_consent_check=1(?:&|$)/.test(q)){w.sessionStorage.setItem('wpec_consent_check','1');}else if(/[?&]wpec_consent_check=0(?:&|$)/.test(q)){w.sessionStorage.removeItem('wpec_consent_check');}check='1'===w.sessionStorage.getItem('wpec_consent_check');}catch(e){check=/[?&]wpec_consent_check=1(?:&|$)/.test(w.location.search||'');}
w.wpeasycart_consent={mode:mode,setting:cfg.setting||mode,has:function(cat){return true===ask(cat||'marketing');},known:function(cat){return null!==ask(cat||'marketing');},on:function(fn){if('function'===typeof fn){fns.push(fn);}},refresh:later,report:function(){var m=ask('marketing'),s=ask('statistics');return{setting:cfg.setting||mode,reads:mode,answered_by:('any'===mode?by:(cfg.label||mode)),marketing:m,statistics:s};}};
if('google_consent_mode'===mode){var dl=w.dataLayer=w.dataLayer||[],wrap=function(fn){return function(){var r=fn.apply(this,arguments),i;for(i=0;i<arguments.length;i++){if(arguments[i]&&'consent'===arguments[i][0]){gcmScan([arguments[i]]);later();}}return r;};},cur=wrap(dl.push);gcmScan(dl);try{Object.defineProperty(dl,'push',{configurable:true,get:function(){return cur;},set:function(f){cur=wrap(f);}});}catch(e){dl.push=cur;}}
d.addEventListener('wp_listen_for_consent_change',later);d.addEventListener('wp_consent_type_defined',later);
w.addEventListener('CookiebotOnConsentReady',later);w.addEventListener('CookiebotOnAccept',later);w.addEventListener('CookiebotOnDecline',later);
d.addEventListener('cookieyes_consent_update',later);d.addEventListener('cookieyes_banner_load',later);
d.addEventListener('cmplz_status_change',later);d.addEventListener('cmplz_fire_categories',later);d.addEventListener('cmplz_enable_category',later);d.addEventListener('cmplz_revoke',later);
d.addEventListener('click',function(){setTimeout(notify,300);setTimeout(notify,1500);},true);
if('loading'===d.readyState){d.addEventListener('DOMContentLoaded',function(){notify(true);});}else{later();}w.addEventListener('load',later);
})(window,document);
JS;
		}
	}

	wp_easycart_consent::init();

endif;
