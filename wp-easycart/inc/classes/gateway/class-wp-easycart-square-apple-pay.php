<?php
/**
 * WP EasyCart — Apple Pay on a live Square store: Square's domain file and the domain's registration with Square.
 *
 * Apple Pay on the web needs the store's domain registered with Square ( RegisterDomain ), and Apple checks the domain by
 * reading Square's domain association file at https://<domain>/.well-known/apple-developer-merchantid-domain-association.
 * Square publishes that file for every seller ( FILE_URL ) and says it can change, so the store keeps a copy ( refreshed
 * weekly ) and puts it in place without anyone uploading anything:
 * - serve(): WordPress answers the address with the copy, on servers that hand unknown addresses to WordPress ( the usual
 *   Apache and NGINX set-up for WordPress );
 * - write_file(): when a request to the address never reaches WordPress ( a host that keeps /.well-known/ for its own
 *   certificates ), the file is written into the site's .well-known folder, never over another payment service's file
 *   unless the store asks;
 * - check() reads the address as Apple does ( a loopback request, no redirects followed ), then register() asks Square to
 *   register the domain and keeps Square's answer ( PENDING or VERIFIED ).
 * Live accounts only: Square's sandbox registration does not work reliably, so a sandbox store is never checked.
 * The Payments page's Square line and WP EasyCart PRO's Square panel show the result ( status(), print_status() ), with
 * the steps to do it by hand whenever the automation cannot.
 *
 * @package wp-easycart
 * @since   6.0.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_square_apple_pay' ) ) :

	/**
	 * Apple Pay domain set-up for a live Square store.
	 */
	class wp_easycart_square_apple_pay {

		/** Square's domain association file, the same for every seller ( RegisterDomain's reference page ). */
		const FILE_URL = 'https://app.squareup.com/digital-wallets/apple-pay/apple-developer-merchantid-domain-association';

		/** Where Apple reads it, from the domain's root. */
		const PATH = '/.well-known/apple-developer-merchantid-domain-association';

		/** The file's name inside .well-known. */
		const FILE_NAME = 'apple-developer-merchantid-domain-association';

		/** Square's RegisterDomain endpoint ( live ). */
		const REGISTER_URL = 'https://connect.squareup.com/v2/apple-pay/domains';

		/** Option ( autoload off ): the copy of Square's file { body, md5, fetched }. */
		const FILE_OPTION = 'wp_easycart_square_apple_pay_file';

		/** Option ( autoload off ): the last check, see run(). */
		const STATE_OPTION = 'wp_easycart_square_apple_pay';

		/** WP-Cron hook: the daily check, and the check soon after the Square panel is saved. */
		const CRON = 'wp_easycart_square_apple_pay_check';

		/** Response header serve() adds, so check() can tell WordPress answered the address. */
		const HEADER = 'X-WP-EasyCart-Apple-Pay';

		/** Nonce action of the "Download the file" link. */
		const DOWNLOAD_NONCE = 'wp-easycart-square-apple-pay-file';

		/** The copy is fetched again once it is this old ( Square: "check for updates regularly" ). */
		const REFRESH_AFTER = WEEK_IN_SECONDS;

		/** A check older than this runs again when the Payments page shows it. */
		const RECHECK_AFTER = DAY_IN_SECONDS;

		/** While Square says PENDING, a check older than this runs again when the Payments page shows it. */
		const PENDING_RECHECK_AFTER = 600;

		/** The largest file accepted from Square ( today's is about 9 KB ). */
		const MAX_BYTES = 65536;

		/** The user agent Square's domain checker sends ( Go's own ), for check_as_square(). */
		const SQUARE_AGENT = 'Go-http-client/1.1';

		/** Hooks. */
		public static function init() {
			add_action( 'plugins_loaded', array( __CLASS__, 'serve' ), 0 );
			add_action( 'init', array( __CLASS__, 'schedule' ) );
			add_action( self::CRON, array( __CLASS__, 'cron' ) );
			add_action( 'admin_post_wp_easycart_square_apple_pay_file', array( __CLASS__, 'download' ) );
		}

		/* ------------------------------------------------------------------ When */

		/**
		 * Square takes the store's card payments with a live account: the gateway is Square, sandbox is off and a live token
		 * is saved. Only then is the file served and the domain checked.
		 *
		 * @return bool
		 */
		public static function live() {
			$live = ( 'square' === (string) get_option( 'ec_option_payment_process_method' ) )
				&& ! get_option( 'ec_option_square_is_sandbox' )
				&& '' !== (string) get_option( 'ec_option_square_access_token' );
			/**
			 * Whether the store takes live payments through Square ( Apple Pay's domain is set up only then ).
			 *
			 * @since 6.0.5
			 * @param bool $live The store's answer.
			 */
			return (bool) apply_filters( 'wp_easycart_square_apple_pay_live', $live );
		}

		/**
		 * Live Square with digital wallets on: Apple Pay should work, so its state is shown and kept checked.
		 *
		 * @return bool
		 */
		public static function wanted() {
			return self::live() && (bool) get_option( 'ec_option_square_digital_wallet' );
		}

		/**
		 * The domain shoppers check out on: the store's home address ( lower case, no port ).
		 *
		 * @return string
		 */
		public static function domain() {
			$host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
			/**
			 * The domain Apple Pay is set up for.
			 *
			 * @since 6.0.5
			 * @param string $host The home address's host.
			 */
			return (string) apply_filters( 'wp_easycart_square_apple_pay_domain', $host );
		}

		/**
		 * The address Apple reads.
		 *
		 * @return string
		 */
		public static function file_address() {
			return 'https://' . self::domain() . self::PATH;
		}

		/**
		 * Is the store in a folder of its domain ( example.com/shop )? WordPress then never sees requests to the domain's root.
		 *
		 * @return bool
		 */
		public static function in_folder() {
			$path = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
			return '' !== $path;
		}

		/**
		 * Addresses Apple never accepts: localhost, a bare IP, and the reserved test domains.
		 *
		 * @param string $host Host.
		 * @return bool
		 */
		public static function local_host( $host ) {
			$host = strtolower( trim( (string) $host, '[]' ) );
			if ( '' === $host || 'localhost' === $host || false !== filter_var( $host, FILTER_VALIDATE_IP ) || false === strpos( $host, '.' ) ) {
				return true;
			}
			foreach ( array( '.localhost', '.local', '.test', '.invalid', '.example', '.lan', '.internal' ) as $end ) {
				if ( substr( $host, -strlen( $end ) ) === $end ) {
					return true;
				}
			}
			return false;
		}

		/* ------------------------------------------------------------------ The file */

		/**
		 * The payment service a domain association file belongs to: its pspId ( the file is hex-encoded JSON ), '' when the
		 * text is no such file.
		 *
		 * @param string $text File contents.
		 * @return string
		 */
		public static function psp( $text ) {
			$text = trim( (string) $text );
			if ( '' === $text || strlen( $text ) > self::MAX_BYTES || 0 !== strlen( $text ) % 2 || ! ctype_xdigit( $text ) ) {
				return '';
			}
			$json = json_decode( (string) hex2bin( $text ), true );
			return ( is_array( $json ) && ! empty( $json['pspId'] ) && is_string( $json['pspId'] ) ) ? $json['pspId'] : '';
		}

		/**
		 * The stored copy of Square's file, fetched from Square when there is none or it is older than REFRESH_AFTER ( $force:
		 * always ). A failed fetch keeps the copy there is. '' when there is no copy at all.
		 *
		 * @param bool $force Fetch now.
		 * @return string
		 */
		public static function file( $force = false ) {
			$stored = get_option( self::FILE_OPTION );
			$body   = ( is_array( $stored ) && isset( $stored['body'] ) ) ? (string) $stored['body'] : '';
			$age    = ( is_array( $stored ) && isset( $stored['fetched'] ) ) ? time() - (int) $stored['fetched'] : PHP_INT_MAX;
			if ( ! $force && '' !== $body && $age < self::REFRESH_AFTER ) {
				return $body;
			}
			$fresh = self::fetch();
			if ( '' === $fresh ) {
				return $body;
			}
			update_option(
				self::FILE_OPTION,
				array(
					'body'    => $fresh,
					'md5'     => md5( $fresh ),
					'fetched' => time(),
				),
				false
			);
			if ( '' !== $body && $fresh !== $body ) {
				self::rewrite_written( $fresh );
			}
			return $fresh;
		}

		/**
		 * Square's current file, or '' when Square could not be reached or answered something that is no such file.
		 *
		 * @return string
		 */
		private static function fetch() {
			/**
			 * Where Square's domain association file is downloaded from.
			 *
			 * @since 6.0.5
			 * @param string $url Square's address.
			 */
			$url      = (string) apply_filters( 'wp_easycart_square_apple_pay_file_url', self::FILE_URL );
			$response = wp_remote_get(
				$url,
				array(
					'timeout'     => 15,
					'redirection' => 2,
				)
			);
			if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
				return '';
			}
			$body = (string) wp_remote_retrieve_body( $response );
			return ( '' !== self::psp( $body ) ) ? $body : '';
		}

		/**
		 * A newer Square file replaces the one written into .well-known earlier ( only the file this class wrote ).
		 *
		 * @param string $file The new file.
		 */
		private static function rewrite_written( $file ) {
			$state = self::state();
			if ( empty( $state['wrote'] ) || ! is_string( $state['wrote'] ) || ! is_file( $state['wrote'] ) ) {
				return;
			}
			$current = (string) file_get_contents( $state['wrote'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local file this class wrote.
			if ( '' !== self::psp( $current ) && self::psp( $current ) === self::psp( $file ) ) {
				file_put_contents( $state['wrote'], $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- the file this class wrote into .well-known.
			}
		}

		/**
		 * The folder the domain's address starts in: WordPress's home folder for a store at the domain's root, else the web
		 * server's document root when WordPress sits inside it. '' when it cannot be told.
		 *
		 * @return string
		 */
		public static function web_root() {
			$root = '';
			if ( ! self::in_folder() ) {
				if ( ! function_exists( 'get_home_path' ) && file_exists( ABSPATH . 'wp-admin/includes/file.php' ) ) {
					require_once ABSPATH . 'wp-admin/includes/file.php';
				}
				$root = function_exists( 'get_home_path' ) ? (string) get_home_path() : (string) ABSPATH;
			} elseif ( ! empty( $_SERVER['DOCUMENT_ROOT'] ) ) {
				$doc  = realpath( sanitize_text_field( wp_unslash( $_SERVER['DOCUMENT_ROOT'] ) ) );
				$wp   = realpath( ABSPATH );
				$root = ( $doc && $wp && 0 === strpos( wp_normalize_path( $wp ), wp_normalize_path( $doc ) ) ) ? $doc : '';
			}
			/**
			 * The folder whose .well-known folder Apple reads ( write_file() writes there ).
			 *
			 * @since 6.0.5
			 * @param string $root Folder path, '' when unknown.
			 */
			$root = (string) apply_filters( 'wp_easycart_square_apple_pay_web_root', $root );
			return ( '' !== $root ) ? untrailingslashit( wp_normalize_path( $root ) ) : '';
		}

		/**
		 * Writes Square's file into <web root>/.well-known/. A different payment service's file there is kept unless $replace.
		 *
		 * @param string $file    Square's file.
		 * @param bool   $replace Replace another service's file.
		 * @return string|WP_Error The path written, or why not ( code no_root, other, folder, write ).
		 */
		public static function write_file( $file, $replace = false ) {
			$root = self::web_root();
			if ( '' === $root ) {
				return new WP_Error( 'no_root', self::in_folder() ? __( 'This store sits in a folder of its domain, so the file has to go in the domain’s main folder, which WordPress cannot reach.', 'wp-easycart' ) : __( 'WordPress could not find your site’s main folder to put the file in.', 'wp-easycart' ) );
			}
			$dir  = $root . '/.well-known';
			$path = $dir . '/' . self::FILE_NAME;
			if ( file_exists( $path ) ) {
				$current = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local file in .well-known.
				if ( trim( $current ) === trim( $file ) ) {
					return $path;
				}
				$theirs = self::psp( $current );
				if ( ! $replace && '' !== $theirs && self::psp( $file ) !== $theirs ) {
					return new WP_Error( 'other', __( 'Another payment service’s Apple Pay file is already in the .well-known folder.', 'wp-easycart' ) );
				}
			}
			if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
				return new WP_Error( 'folder', __( 'WordPress could not create the .well-known folder in your site’s main folder.', 'wp-easycart' ) );
			}
			if ( false === @file_put_contents( $path, $file ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.PHP.NoSilencedErrors.Discouraged -- one small file in .well-known; a refusal is reported, not shown.
				return new WP_Error( 'write', __( 'WordPress could not write the file into the .well-known folder ( the folder is not writable ).', 'wp-easycart' ) );
			}
			return $path;
		}

		/* ------------------------------------------------------------------ Serving */

		/**
		 * What WordPress answers for a request: Square's file for a GET or HEAD of PATH while live() ( null otherwise ).
		 *
		 * @param string $path   Request path.
		 * @param string $method Request method.
		 * @return string|null
		 */
		public static function response_for( $path, $method = 'GET' ) {
			if ( self::PATH !== (string) $path || ! in_array( strtoupper( (string) $method ), array( 'GET', 'HEAD' ), true ) || ! self::live() ) {
				return null;
			}
			$file = self::file();
			return ( '' !== $file ) ? $file : null;
		}

		/** Answers PATH with Square's file ( plugins_loaded 0, before anything can redirect the request ). */
		public static function serve() {
			if ( empty( $_SERVER['REQUEST_URI'] ) || false === strpos( (string) wp_unslash( $_SERVER['REQUEST_URI'] ), 'apple-developer-merchantid-domain-association' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only searched for a fixed string here; parsed and sanitized below.
				return;
			}
			$path   = (string) wp_parse_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH );
			$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'get';
			$file   = self::response_for( $path, $method );
			if ( null === $file ) {
				return;
			}
			status_header( 200 );
			header( 'Content-Type: application/octet-stream' );
			header( 'Content-Length: ' . strlen( $file ) );
			header( 'Cache-Control: public, max-age=3600' );
			header( 'X-Robots-Tag: noindex' );
			header( self::HEADER . ': 1' );
			if ( 'head' !== $method ) {
				echo $file; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Square's file, served as-is ( hex text ).
			}
			exit;
		}

		/* ------------------------------------------------------------------ Checking */

		/**
		 * Reads file_address() as Apple does ( no redirects followed ) and compares it with Square's file.
		 *
		 * @param string $file Square's file.
		 * @return array { result: ok | missing | old | other | redirect | unreachable, detail, served_by: site | file | '' }
		 */
		public static function check( $file ) {
			$response = wp_remote_get(
				self::file_address(),
				array(
					'timeout'     => 10,
					'redirection' => 0,
					/** This filter is documented in wp-includes/class-wp-http-streams.php ( WordPress's own loopback requests ). */
					'sslverify'   => apply_filters( 'https_local_ssl_verify', false ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core's filter for loopback requests.
					'headers'     => array(
						'Cache-Control' => 'no-cache',
						'Pragma'        => 'no-cache',
					),
				)
			);
			if ( is_wp_error( $response ) ) {
				return array(
					'result'    => 'unreachable',
					'detail'    => $response->get_error_message(),
					'served_by' => '',
				);
			}
			$code = (int) wp_remote_retrieve_response_code( $response );
			$body = (string) wp_remote_retrieve_body( $response );
			$by   = ( '' !== (string) wp_remote_retrieve_header( $response, self::HEADER ) ) ? 'site' : 'file';
			if ( $code >= 300 && $code < 400 ) {
				return array(
					'result'    => 'redirect',
					'detail'    => (string) wp_remote_retrieve_header( $response, 'location' ),
					'served_by' => '',
				);
			}
			if ( 200 === $code && '' !== trim( $file ) && trim( $body ) === trim( $file ) ) {
				return array(
					'result'    => 'ok',
					'detail'    => '',
					'served_by' => $by,
					'agent'     => self::check_as_square(),
				);
			}
			$theirs = ( 200 === $code ) ? self::psp( $body ) : '';
			if ( '' !== $theirs ) {
				return array(
					'result'    => ( self::psp( $file ) === $theirs ) ? 'old' : 'other',
					'detail'    => '',
					'served_by' => $by,
				);
			}
			return array(
				'result'    => 'missing',
				'detail'    => (string) $code,
				'served_by' => '',
			);
		}

		/**
		 * Reads file_address() again as Square's checker asks for it ( Go's own user agent ), once the site's own read found the
		 * file. Some hosts' firewalls ( ModSecurity rule sets, Imunify360, Cloudflare's bot rules ) turn that user agent away
		 * across the whole site, before WordPress runs: visitors and WordPress get the file, Square gets a 406 or 403 and
		 * refuses the domain ( a test site, 2026-10-12: "expected GET … to return a 200 response code but instead returned 406" ).
		 * A server that lets its own address through answers 200 here; Square's own answer still tells ( firewall() ).
		 *
		 * @since 6.0.5
		 * @return array { code: the status Square's checker would get ( 0 = could not ask ), by: the firewall's name or '' }
		 */
		public static function check_as_square() {
			$response = wp_remote_get(
				self::file_address(),
				array(
					'timeout'     => 10,
					'redirection' => 0,
					/** This filter is documented in wp-includes/class-wp-http-streams.php ( WordPress's own loopback requests ). */
					'sslverify'   => apply_filters( 'https_local_ssl_verify', false ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core's filter for loopback requests.
					'user-agent'  => self::SQUARE_AGENT,
					'headers'     => array(
						'Cache-Control' => 'no-cache',
						'Pragma'        => 'no-cache',
					),
				)
			);
			if ( is_wp_error( $response ) ) {
				return array(
					'code' => 0,
					'by'   => '',
				);
			}
			$code = (int) wp_remote_retrieve_response_code( $response );
			return array(
				'code' => $code,
				'by'   => ( 200 === $code ) ? '' : self::firewall_name( $response ),
			);
		}

		/**
		 * Which firewall answered, from its page or headers ( '' when it does not say ).
		 *
		 * @since 6.0.5
		 * @param array $response WP_Http's answer.
		 * @return string
		 */
		private static function firewall_name( $response ) {
			$body   = substr( (string) wp_remote_retrieve_body( $response ), 0, 4096 );
			$server = strtolower( (string) wp_remote_retrieve_header( $response, 'server' ) );
			if ( false !== stripos( $body, 'mod_security' ) || false !== stripos( $body, 'modsecurity' ) ) {
				return 'ModSecurity';
			}
			if ( false !== stripos( $body, 'imunify' ) ) {
				return 'Imunify360';
			}
			if ( '' !== (string) wp_remote_retrieve_header( $response, 'cf-ray' ) || false !== strpos( $server, 'cloudflare' ) ) {
				return 'Cloudflare';
			}
			if ( '' !== (string) wp_remote_retrieve_header( $response, 'x-sucuri-id' ) || false !== strpos( $server, 'sucuri' ) ) {
				return 'Sucuri';
			}
			if ( false !== stripos( $body, 'wordfence' ) ) {
				return 'Wordfence';
			}
			return '';
		}

		/**
		 * Is the host's firewall turning Square away? Only once the site's own read found the file and Square did not
		 * register the domain: Square's error names the status it got ( "… but instead returned 406" ) and it is not a 404
		 * ( a missing file is the other message ), or the read as Square was refused.
		 *
		 * @since 6.0.5
		 * @param array|null $state State ( null: the saved one ).
		 * @return array|null { code: the status Square got, by: the firewall's name or '' }, or null.
		 */
		public static function firewall( $state = null ) {
			$state = ( null === $state ) ? self::state() : $state;
			if ( empty( $state ) || ! isset( $state['result'] ) || 'ok' !== $state['result'] || ( isset( $state['square'] ) && 'VERIFIED' === $state['square'] ) ) {
				return null;
			}
			$agent       = ( isset( $state['agent'] ) && is_array( $state['agent'] ) ) ? $state['agent'] : array();
			$agent_code  = isset( $agent['code'] ) ? (int) $agent['code'] : 0;
			$by          = isset( $agent['by'] ) ? (string) $agent['by'] : '';
			$square_code = 0;
			if ( ! empty( $state['square_error'] ) && preg_match( '/returned\s+(\d{3})\b/i', (string) $state['square_error'], $m ) ) {
				$square_code = (int) $m[1];
			}
			if ( $square_code >= 400 && 404 !== $square_code ) {
				return array(
					'code' => $square_code,
					'by'   => ( $agent_code === $square_code ) ? $by : '',
				);
			}
			if ( 0 === $square_code && $agent_code >= 400 && 404 !== $agent_code ) {
				return array(
					'code' => $agent_code,
					'by'   => $by,
				);
			}
			return null;
		}

		/**
		 * A note the store owner can send their host, in the store owner's words ( plain text ).
		 *
		 * @since 6.0.5
		 * @param array $firewall firewall().
		 * @return string
		 */
		public static function host_note( $firewall ) {
			$name = ( '' !== $firewall['by'] ) ? $firewall['by'] : __( 'the firewall', 'wp-easycart' );
			return sprintf(
				/* translators: 1: the store's domain, 2: the address Square reads, 3: the firewall's name ( e.g. ModSecurity ), 4: the status code ( e.g. 406 ), 5: the user agent Square sends. */
				__( "Hello,\n\nWe take Apple Pay on %1\$s through Square. To confirm the domain, Square reads this address:\n%2\$s\n\nOn our server, %3\$s answers Square's request with status %4\$d, so Square can't confirm the domain. Visitors and browsers get the file without a problem: the block seems to be on the user agent Square's checker sends, %5\$s.\n\nCould you let that address ( or that user agent ) through for our site? Thank you.", 'wp-easycart' ),
				self::domain(),
				self::file_address(),
				$name,
				(int) $firewall['code'],
				self::SQUARE_AGENT
			);
		}

		/**
		 * Asks Square to register the domain for Apple Pay ( live account ).
		 *
		 * @param string $domain Domain.
		 * @return array { status: PENDING | VERIFIED | '', error: Square's reason or '' }
		 */
		public static function register( $domain ) {
			$response = wp_remote_post(
				self::REGISTER_URL,
				array(
					'timeout' => 20,
					'headers' => array(
						'Accept'         => 'application/json',
						'Content-Type'   => 'application/json',
						'Authorization'  => 'Bearer ' . (string) get_option( 'ec_option_square_access_token' ),
						'Square-Version' => '2024-08-21',
					),
					'body'    => wp_json_encode( array( 'domain_name' => $domain ) ),
				)
			);
			if ( is_wp_error( $response ) ) {
				self::log( true, $response->get_error_message() );
				return array(
					'status' => '',
					'error'  => __( 'Square could not be reached.', 'wp-easycart' ),
				);
			}
			$body = (string) wp_remote_retrieve_body( $response );
			$json = json_decode( $body );
			if ( isset( $json->errors ) && is_array( $json->errors ) && ! empty( $json->errors ) ) {
				self::log( true, $body );
				$first  = $json->errors[0];
				$detail = isset( $first->detail ) ? (string) $first->detail : ( isset( $first->code ) ? (string) $first->code : '' );
				if ( isset( $first->category ) && 'AUTHENTICATION_ERROR' === (string) $first->category ) {
					$detail = __( 'Square refused this store’s access. Reconnect Square, then check again.', 'wp-easycart' );
				}
				return array(
					'status' => '',
					'error'  => '' !== $detail ? $detail : __( 'Square refused the domain.', 'wp-easycart' ),
				);
			}
			self::log( false, $body );
			$status = isset( $json->status ) ? strtoupper( (string) $json->status ) : '';
			return array(
				'status' => in_array( $status, array( 'PENDING', 'VERIFIED' ), true ) ? $status : '',
				'error'  => in_array( $status, array( 'PENDING', 'VERIFIED' ), true ) ? '' : __( 'Square did not confirm the domain.', 'wp-easycart' ),
			);
		}

		/**
		 * The gateway log ( when switched on ).
		 *
		 * @param bool   $error Is it an error.
		 * @param string $text  Square's answer.
		 */
		private static function log( $error, $text ) {
			if ( class_exists( 'ec_db' ) && method_exists( 'ec_db', 'insert_response' ) ) {
				ec_db::insert_response( 0, $error ? 1 : 0, 'Square Apple Pay Domain', $text );
			}
		}

		/**
		 * The whole set-up: Square's file, the address checked, the file written when the address does not answer, and the
		 * domain registered with Square once Apple can read the file. Saved as the state.
		 *
		 * @param bool $replace Replace another payment service's file in .well-known.
		 * @return array The state ( see state() ).
		 */
		public static function run( $replace = false ) {
			$before = self::state();
			$state  = array(
				'domain'       => self::domain(),
				'checked'      => time(),
				'result'       => '',
				'detail'       => '',
				'served_by'    => '',
				'wrote'        => ! empty( $before['wrote'] ) ? (string) $before['wrote'] : '',
				'write_error'  => '',
				'write_code'   => '',
				'square'       => '',
				'square_error' => '',
				'agent'        => array(),
			);
			if ( ! self::live() ) {
				$state['result'] = 'off';
				return self::save_state( $state );
			}
			if ( self::local_host( $state['domain'] ) ) {
				$state['result'] = 'local';
				return self::save_state( $state );
			}
			if ( 'https' !== wp_parse_url( home_url(), PHP_URL_SCHEME ) ) {
				$state['result'] = 'http';
				return self::save_state( $state );
			}
			$file = self::file();
			if ( '' === $file ) {
				$state['result'] = 'no_file';
				return self::save_state( $state );
			}
			$check = self::check( $file );
			if ( in_array( $check['result'], array( 'missing', 'old' ), true ) || ( $replace && 'other' === $check['result'] ) ) {
				$written = self::write_file( $file, $replace );
				if ( is_wp_error( $written ) ) {
					$state['write_error'] = $written->get_error_message();
					$state['write_code']  = $written->get_error_code();
				} else {
					$state['wrote'] = $written;
					$check          = self::check( $file );
				}
			}
			$state['result']    = $check['result'];
			$state['detail']    = $check['detail'];
			$state['served_by'] = $check['served_by'];
			$state['agent']     = isset( $check['agent'] ) ? $check['agent'] : array(); /* 6.0.5: the read as Square's checker */
			if ( 'ok' === $state['result'] ) {
				$registered            = self::register( $state['domain'] );
				$state['square']       = $registered['status'];
				$state['square_error'] = $registered['error'];
			}
			$state = self::save_state( $state );
			/**
			 * Fires after Apple Pay's domain set-up ran for a live Square store.
			 *
			 * @since 6.0.5
			 * @param array $state The result ( see wp_easycart_square_apple_pay::state() ).
			 */
			do_action( 'wp_easycart_square_apple_pay_checked', $state );
			return $state;
		}

		/**
		 * The last check: domain, checked ( time ), result ( ok | missing | old | other | redirect | unreachable | http | local |
		 * no_file | off ), detail, served_by, wrote ( the path write_file() wrote ), write_error, write_code, square ( PENDING |
		 * VERIFIED | '' ), square_error, agent ( check_as_square(): code, by ). An empty array when it never ran.
		 *
		 * @return array
		 */
		public static function state() {
			$state = get_option( self::STATE_OPTION );
			return is_array( $state ) ? $state : array();
		}

		/**
		 * Saves a state.
		 *
		 * @param array $state State.
		 * @return array
		 */
		private static function save_state( $state ) {
			update_option( self::STATE_OPTION, $state, false );
			return $state;
		}

		/**
		 * Is Apple Pay set up on the domain ( the file is there and Square verified the domain )?
		 *
		 * @param array|null $state State ( null: the saved one ).
		 * @return bool
		 */
		public static function ready( $state = null ) {
			$state = ( null === $state ) ? self::state() : $state;
			return ! empty( $state ) && isset( $state['domain'] ) && self::domain() === $state['domain'] && 'ok' === $state['result'] && 'VERIFIED' === $state['square'];
		}

		/**
		 * Should the check run again before its result is shown? Never ran, another domain, older than RECHECK_AFTER, or
		 * waiting for Apple for longer than PENDING_RECHECK_AFTER.
		 *
		 * @param array|null $state State ( null: the saved one ).
		 * @return bool
		 */
		public static function stale( $state = null ) {
			$state = ( null === $state ) ? self::state() : $state;
			if ( empty( $state ) || empty( $state['checked'] ) || ! isset( $state['domain'] ) || self::domain() !== $state['domain'] || 'off' === $state['result'] ) {
				return true;
			}
			$age = time() - (int) $state['checked'];
			if ( 'ok' === $state['result'] && 'PENDING' === $state['square'] ) {
				return $age > self::PENDING_RECHECK_AFTER;
			}
			return $age > self::RECHECK_AFTER;
		}

		/**
		 * After the Square panel is saved with digital wallets on ( ec_square::register_domain() ): the saved result is marked
		 * stale, so the Payments page checks again when it is next shown, and WP-Cron checks in a few seconds anyway.
		 *
		 * @return bool Whether Apple Pay was already set up.
		 */
		public static function recheck_soon() {
			$state = self::state();
			$ready = self::ready( $state );
			if ( ! empty( $state ) ) {
				$state['checked'] = 0;
				self::save_state( $state );
			}
			if ( ! wp_next_scheduled( self::CRON, array( 'soon' ) ) ) {
				wp_schedule_single_event( time() + 10, self::CRON, array( 'soon' ) );
			}
			return $ready;
		}

		/** The daily check while Apple Pay is wanted; cleared when it is not. */
		public static function schedule() {
			$next = wp_next_scheduled( self::CRON );
			if ( self::wanted() && ! $next ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
			} elseif ( ! self::wanted() && $next ) {
				wp_clear_scheduled_hook( self::CRON );
			}
		}

		/**
		 * WP-Cron: refreshes Square's file and checks the domain while Apple Pay is wanted.
		 *
		 * @param string $why 'soon' after a save of the Square panel, else the daily run.
		 */
		public static function cron( $why = '' ) {
			unset( $why ); /* the same run either way; WP-Cron passes it so the two events stay apart */
			if ( ! self::live() ) {
				return;
			}
			self::file();
			if ( self::wanted() ) {
				self::run();
			}
		}

		/* ------------------------------------------------------------------ Showing it */

		/**
		 * What the store owner sees.
		 *
		 * @param array|null $state State ( null: the saved one ).
		 * @return array { tone: none | checking | ok | warn | bad, title, text, manual ( show the steps ), replace ( offer Replace ),
		 *                 check ( the button's words ), short ( a few words for the Payments line ) }
		 */
		public static function status( $state = null ) {
			$state  = ( null === $state ) ? self::state() : $state;
			$domain = self::domain();
			$out    = array(
				'tone'      => 'none',
				'title'     => '',
				'text'      => '',
				'manual'    => false,
				'replace'   => false,
				'check'     => __( 'Check again', 'wp-easycart' ),
				'short'     => '',
				'host_note' => '',
			);
			if ( ! self::wanted() ) {
				return $out;
			}
			if ( self::stale( $state ) ) {
				$out['tone'] = 'checking';
				/* translators: %s: the store's domain, e.g. example.com. */
				$out['title'] = sprintf( __( 'Checking Apple Pay on %s…', 'wp-easycart' ), $domain );
				$out['short'] = __( 'Apple Pay being checked', 'wp-easycart' );
				return $out;
			}
			$address  = self::file_address();
			$firewall = self::firewall( $state ); /* 6.0.5 */
			switch ( $state['result'] ) {
				case 'ok':
					if ( 'VERIFIED' === $state['square'] ) {
						$out['tone'] = 'ok';
						/* translators: %s: the store's domain. */
						$out['title'] = sprintf( __( 'Apple Pay is ready on %s.', 'wp-easycart' ), $domain );
						$out['text']  = ( 'file' === $state['served_by'] && ! empty( $state['wrote'] ) )
							? __( 'Square’s file is in your site’s .well-known folder and Square has registered the domain. WP EasyCart keeps the file up to date.', 'wp-easycart' )
							: __( 'Your site answers with Square’s file and Square has registered the domain. WP EasyCart keeps the file up to date.', 'wp-easycart' );
						$out['short'] = __( 'Apple Pay ready', 'wp-easycart' );
					} elseif ( 'PENDING' === $state['square'] ) {
						$out['tone'] = 'warn';
						/* translators: %s: the store's domain. */
						$out['title'] = sprintf( __( 'Apple Pay is waiting for Apple to confirm %s.', 'wp-easycart' ), $domain );
						$out['text']  = __( 'Square’s file is in place and Square has the domain. Apple usually confirms within a few minutes; this checks again on its own.', 'wp-easycart' );
						$out['short'] = __( 'Apple Pay waiting for Apple', 'wp-easycart' );
					} elseif ( null !== $firewall ) {
						/* 6.0.5: the host's firewall answers Square's checker ( Go's user agent ) with a 406 or 403 before WordPress runs. */
						$out['tone'] = 'bad';
						/* translators: %s: the store's domain. */
						$out['title'] = sprintf( __( 'Your web host is stopping Square from checking %s.', 'wp-easycart' ), $domain );
						$out['text']  = sprintf(
							/* translators: 1: the firewall's name ( e.g. ModSecurity ) or "Your host's firewall", 2: the status code ( e.g. 406 ). */
							__( 'Square’s file is in place and visitors can open it, but %1$s answers Square’s check with %2$d, so Square can’t register the domain. Ask your host to let Square through ( the note below says what to allow ). WP EasyCart checks again every day and finishes on its own once Square gets through.', 'wp-easycart' ),
							'' !== $firewall['by'] ? $firewall['by'] : __( 'your host’s firewall', 'wp-easycart' ),
							(int) $firewall['code']
						);
						if ( 'ModSecurity' === $firewall['by'] ) {
							$out['text'] .= ' ' . __( 'On cPanel hosting you can also switch ModSecurity off for this domain yourself ( Security › ModSecurity ), then press Check again.', 'wp-easycart' );
						} elseif ( 'Cloudflare' === $firewall['by'] ) {
							$out['text'] .= ' ' . __( 'In your Cloudflare dashboard, a WAF skip rule for /.well-known/ ( or Bot Fight Mode off ) lets Square through; then press Check again.', 'wp-easycart' );
						}
						$out['host_note'] = self::host_note( $firewall );
						$out['short']     = __( 'Apple Pay blocked by your host', 'wp-easycart' );
					} else {
						$out['tone'] = 'bad';
						/* translators: %s: the store's domain. */
						$out['title'] = sprintf( __( 'Square did not register %s for Apple Pay.', 'wp-easycart' ), $domain );
						/* translators: %s: Square's own reason. */
						$out['text']  = sprintf( __( 'Square’s file is in place, but Square answered: %s', 'wp-easycart' ), $state['square_error'] );
						$out['short'] = __( 'Apple Pay not set up', 'wp-easycart' );
					}
					break;
				case 'local':
					$out['tone'] = 'warn';
					/* translators: %s: the store's domain. */
					$out['title'] = sprintf( __( 'Apple Pay can’t be set up on %s.', 'wp-easycart' ), $domain );
					$out['text']  = __( 'Apple only accepts a public domain with a valid security certificate. It is set up once the store runs on its live domain.', 'wp-easycart' );
					$out['short'] = __( 'Apple Pay needs your live domain', 'wp-easycart' );
					break;
				case 'http':
					$out['tone']  = 'bad';
					$out['title'] = __( 'Apple Pay needs your store on https://.', 'wp-easycart' );
					/* translators: %s: the store's domain. */
					$out['text']  = sprintf( __( 'Apple Pay only works on pages sent over https. Turn on a security certificate ( SSL ) for %s, your host can help, then check again.', 'wp-easycart' ), $domain );
					$out['short'] = __( 'Apple Pay needs https', 'wp-easycart' );
					break;
				case 'no_file':
					$out['tone']   = 'bad';
					$out['title']  = __( 'Square’s Apple Pay file could not be downloaded.', 'wp-easycart' );
					$out['text']   = __( 'This site could not reach Square to download it. Check again in a few minutes, or put the file on your site yourself:', 'wp-easycart' );
					$out['manual'] = true;
					$out['short']  = __( 'Apple Pay not set up', 'wp-easycart' );
					break;
				case 'redirect':
					$out['tone'] = 'bad';
					/* translators: %s: the store's domain. */
					$out['title'] = sprintf( __( 'Apple can’t read the Apple Pay address on %s: it redirects.', 'wp-easycart' ), $domain );
					/* translators: 1: the address Apple reads, 2: where it redirects to. */
					$out['text']   = sprintf( __( 'Apple reads %1$s without following redirects, but it leads to %2$s. Turn that redirect off for the /.well-known/ folder ( in your host’s settings or your redirect plugin ), then check again.', 'wp-easycart' ), $address, '' !== $state['detail'] ? $state['detail'] : __( 'another address', 'wp-easycart' ) );
					$out['manual'] = true;
					$out['short']  = __( 'Apple Pay not set up', 'wp-easycart' );
					break;
				case 'other':
					$out['tone'] = 'bad';
					/* translators: %s: the store's domain. */
					$out['title']   = sprintf( __( 'Another payment service’s Apple Pay file is on %s.', 'wp-easycart' ), $domain );
					$out['text']    = __( 'Only one file can sit at that address, and Square needs its own for Apple Pay to work with Square. Replace it with Square’s file?', 'wp-easycart' );
					$out['replace'] = true;
					$out['manual']  = true;
					$out['short']   = __( 'Apple Pay not set up', 'wp-easycart' );
					break;
				case 'unreachable':
					$out['tone'] = 'warn';
					/* translators: %s: the store's domain. */
					$out['title'] = sprintf( __( 'Apple Pay on %s could not be checked.', 'wp-easycart' ), $domain );
					/* translators: 1: the error, 2: the address Apple reads. */
					$out['text']   = sprintf( __( 'This site could not open its own address ( %1$s ). Open %2$s in your browser: one long line of letters and numbers means Apple Pay’s file is in place.', 'wp-easycart' ), $state['detail'], $address );
					$out['manual'] = true;
					$out['short']  = __( 'Apple Pay not checked', 'wp-easycart' );
					break;
				default: /* missing, old: the address does not answer with Square's file and writing it failed */
					$out['tone'] = 'bad';
					/* translators: %s: the store's domain. */
					$out['title'] = sprintf( __( 'Apple Pay is not set up on %s.', 'wp-easycart' ), $domain );
					/* translators: %s: the address Apple reads. */
					$out['text'] = sprintf( __( 'Apple can’t find Square’s file at %s.', 'wp-easycart' ), $address );
					if ( '' !== (string) $state['write_error'] ) {
						$out['text'] .= ' ' . $state['write_error'];
					}
					$out['manual'] = true;
					$out['short']  = __( 'Apple Pay not set up', 'wp-easycart' );
					if ( 'other' === $state['write_code'] ) {
						$out['replace'] = true;
					}
			}
			/**
			 * What the store owner is told about Apple Pay on a live Square store.
			 *
			 * @since 6.0.5
			 * @param array $out   tone, title, text, manual, replace, check, short.
			 * @param array $state The last check.
			 */
			return apply_filters( 'wp_easycart_square_apple_pay_status', $out, $state );
		}

		/**
		 * The "Download the file" link ( admin-post, nonce ).
		 *
		 * @return string
		 */
		public static function download_url() {
			return wp_nonce_url( admin_url( 'admin-post.php?action=wp_easycart_square_apple_pay_file' ), self::DOWNLOAD_NONCE );
		}

		/** Admin-post: Square's file as a download named exactly as Apple expects ( no extension ). */
		public static function download() {
			if ( ! current_user_can( 'manage_options' ) && ! ( current_user_can( 'wpec_settings' ) && current_user_can( 'wpec_manager' ) ) ) {
				wp_die( esc_html__( 'You do not have permission to change the payment settings.', 'wp-easycart' ), '', array( 'response' => 403 ) );
			}
			check_admin_referer( self::DOWNLOAD_NONCE );
			$file = self::file();
			if ( '' === $file ) {
				wp_die( esc_html__( 'Square’s Apple Pay file could not be downloaded from Square. Try again in a few minutes.', 'wp-easycart' ), '', array( 'response' => 502 ) );
			}
			nocache_headers();
			header( 'Content-Type: application/octet-stream' );
			header( 'Content-Disposition: attachment; filename="' . self::FILE_NAME . '"' );
			header( 'Content-Length: ' . strlen( $file ) );
			echo $file; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Square's file, sent as-is ( hex text ).
			exit;
		}

		/**
		 * The steps to put the file in place by hand.
		 *
		 * @return string HTML.
		 */
		public static function manual_html() {
			$domain  = self::domain();
			$address = self::file_address();
			$folder  = self::in_folder()
				/* translators: %s: the store's domain. */
				? sprintf( __( 'the main folder of %s, not the folder WordPress is in', 'wp-easycart' ), $domain )
				: __( 'your site’s main folder, the one with wp-config.php', 'wp-easycart' );
			$steps = array(
				/* translators: %s: the Download link. */
				sprintf( esc_html__( '%s. It has no file extension; keep it that way.', 'wp-easycart' ), '<a href="' . esc_url( self::download_url() ) . '">' . esc_html__( 'Download Square’s file', 'wp-easycart' ) . '</a>' ),
				/* translators: 1: the folder name .well-known, 2: where that folder goes. */
				sprintf( esc_html__( 'With your host’s file manager or FTP, upload it into a folder named %1$s in %2$s. Create the folder if it isn’t there, and don’t rename the file.', 'wp-easycart' ), '<code>.well-known</code>', esc_html( $folder ) ),
				/* translators: %s: the address Apple reads, as a link. */
				sprintf( esc_html__( 'Open %s: it should show one long line of letters and numbers.', 'wp-easycart' ), '<a href="' . esc_url( $address ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $address ) . '</a>' ),
				esc_html__( 'Press Check again.', 'wp-easycart' ),
			);
			return '<ol><li>' . implode( '</li><li>', $steps ) . '</li></ol>';
		}

		/**
		 * The status block: the Payments page's Square line ( $where line: only when something needs doing or is being
		 * checked ) and WP EasyCart PRO's Square panel ( drawer: always while wanted ). A block marked data-stale runs the
		 * check as soon as the page shows it ( settings-payment-v2.js ).
		 *
		 * @param string $where line | drawer.
		 */
		public static function print_status( $where = 'drawer' ) {
			echo self::status_html( $where ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts in status_html().
		}

		/**
		 * HTML of print_status().
		 *
		 * @param string $where line | drawer.
		 * @return string
		 */
		public static function status_html( $where = 'drawer' ) {
			$where = ( 'line' === $where ) ? 'line' : 'drawer';
			if ( 'drawer' === $where && ! self::live() && 'square' === (string) get_option( 'ec_option_payment_process_method' ) && get_option( 'ec_option_square_is_sandbox' ) && get_option( 'ec_option_square_digital_wallet' ) ) {
				return '<div class="ecpay-applepay is-note" role="note"><div class="ecpay-applepay-body"><span>' . esc_html__( 'Apple Pay is set up on your live account only: Square’s sandbox does not reliably accept Apple Pay domains. Once the store takes live payments, WP EasyCart puts Square’s file in place and registers your domain.', 'wp-easycart' ) . '</span></div></div>';
			}
			$status = self::status();
			if ( 'none' === $status['tone'] || ( 'line' === $where && 'ok' === $status['tone'] ) ) {
				return '';
			}
			$icons = array(
				'ok'       => '<path d="M20 6 9 17l-5-5"/>',
				'warn'     => '<circle cx="12" cy="12" r="9"/><path d="M12 8v5"/><path d="M12 16.5h.01"/>',
				'bad'      => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
				'checking' => '<path d="M21 12a9 9 0 1 1-6.2-8.6"/>',
			);
			$html  = '<div class="ecpay-applepay is-' . esc_attr( $status['tone'] ) . '" data-ecpay-applepay="' . esc_attr( $where ) . '" data-stale="' . ( 'checking' === $status['tone'] ? '1' : '0' ) . '" role="status" aria-live="polite">';
			$html .= '<svg class="ecpay-applepay-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $icons[ $status['tone'] ] . '</svg>';
			$html .= '<div class="ecpay-applepay-body"><b>' . esc_html( $status['title'] ) . '</b>';
			if ( '' !== $status['text'] ) {
				$html .= '<span>' . esc_html( $status['text'] ) . '</span>';
			}
			if ( $status['manual'] ) {
				$html .= '<details class="ecpay-applepay-manual"' . ( 'drawer' === $where ? ' open' : '' ) . '><summary>' . esc_html__( 'Set it up yourself', 'wp-easycart' ) . '</summary>' . self::manual_html() . '</details>';
			}
			if ( ! empty( $status['host_note'] ) ) { /* 6.0.5: what to send the host when its firewall turns Square away */
				$html .= '<details class="ecpay-applepay-manual ecpay-applepay-host"' . ( 'drawer' === $where ? ' open' : '' ) . '><summary>' . esc_html__( 'A note for your host', 'wp-easycart' ) . '</summary>';
				$html .= '<textarea class="ecpay-applepay-note" rows="9" readonly aria-label="' . esc_attr__( 'A note for your host', 'wp-easycart' ) . '">' . esc_textarea( $status['host_note'] ) . '</textarea>';
				$html .= '<button type="button" class="ecv2-btn ecv2-btn-sm ecpay-applepay-copy" data-copied="' . esc_attr__( 'Copied', 'wp-easycart' ) . '">' . esc_html__( 'Copy the note', 'wp-easycart' ) . '</button></details>';
			}
			$html .= '</div>';
			if ( 'checking' !== $status['tone'] && 'ok' !== $status['tone'] && 'local' !== self::state_result() ) {
				$html .= '<div class="ecpay-applepay-act">';
				if ( $status['replace'] ) {
					$html .= '<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" data-ecpay-applepay-run="replace">' . esc_html__( 'Replace with Square’s file', 'wp-easycart' ) . '</button>';
				}
				$html .= '<button type="button" class="ecv2-btn ecv2-btn-sm' . ( $status['replace'] ? '' : ' ecv2-btn-primary' ) . '" data-ecpay-applepay-run="check">' . esc_html( $status['check'] ) . '</button>';
				$html .= '</div>';
			} elseif ( 'ok' === $status['tone'] ) {
				$html .= '<div class="ecpay-applepay-act"><button type="button" class="ecv2-btn ecv2-btn-sm" data-ecpay-applepay-run="check">' . esc_html( $status['check'] ) . '</button></div>';
			}
			$html .= '</div>';
			return $html;
		}

		/**
		 * The saved result ( '' when it never ran ).
		 *
		 * @return string
		 */
		private static function state_result() {
			$state = self::state();
			return isset( $state['result'] ) ? (string) $state['result'] : '';
		}
	}

	wp_easycart_square_apple_pay::init();

endif;
