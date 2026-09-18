<?php
/**
 * WP EasyCart Admin Order Uploads
 *
 * Gated download endpoint for the customer files uploaded through the "file upload"
 * option / modifier at purchase. The files live in
 * EC_PLUGIN_DATA_DIRECTORY . '/products/uploads/<folder>/<filename>' and the
 * ec_order_option row stores option_value as "<folder>/<filename>" ( folder = the cart
 * session id; older orders may use a numeric cart row id ). The uploads folder carries
 * deny rules and is never served directly ( wp_easycart_customer_uploads ).
 *
 * Product inquiries have no order: their files sit in a random "inquiry-<hex>" folder and
 * the inquiry email links with iq=<folder>&f=<filename> instead of od / o. Access follows
 * the same setting, token ( 'k' ) and secret; the inquiry signature uses its own domain
 * separator ( INQUIRY_SIGNATURE_VERSION ) so an order link and an inquiry link can never
 * be swapped. Shopper copies of the inquiry email never carry the link ( strip_links() ).
 *
 * The endpoint is admin-post.php?action=wp_easycart_order_upload.
 *
 * Sign-in required ( default, ec_option_upload_link_access = 'login' ):
 *   only a logged-in user with manage_options or wpec_orders gets the file. The request
 *   must also carry a short-lived nonce ( admin screens ) or a keyed token ( admin order
 *   email, which must keep working after a nonce would have expired ).
 *
 * Anyone with the link ( ec_option_upload_link_access = 'private' ):
 *   the admin order email carries a signed, expiring link instead. Signature is
 *   HMAC-SHA256 over ( order id, order item id, issued, expires, stored option value )
 *   keyed with a per-site secret ( option wp_easycart_upload_link_secret, not autoloaded,
 *   created on first use ). The link serves the file without a login until it expires.
 *   Rotating the secret ( "Revoke all existing private links", or switching the setting
 *   back to sign-in required ) invalidates every link already emailed, and while the
 *   setting is on sign-in required the endpoint never serves a file to a visitor who
 *   is not signed in as staff. Staff who are signed in can still use a signed link.
 *
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_order_uploads' ) ) :

class wp_easycart_admin_order_uploads {
	const ACTION = 'wp_easycart_order_upload';
	const NONCE_PREFIX = 'wp-easycart-order-upload-';

	/** Setting: 'login' ( default ) or 'private'. */
	const OPTION_ACCESS = 'ec_option_upload_link_access';

	/** Setting: private link lifetime in days, '0' = never expires. */
	const OPTION_EXPIRY = 'ec_option_upload_link_expiry_days';

	/** Per-site HMAC key for private links ( autoload off ). */
	const OPTION_SECRET = 'wp_easycart_upload_link_secret';

	/** Domain separator inside the signed string, bumped if the format ever changes. */
	const SIGNATURE_VERSION = 'wpec-upload-v1';

	/** Domain separator for product inquiry links. */
	const INQUIRY_SIGNATURE_VERSION = 'wpec-upload-inquiry-v1';

	/**
	 * Register the admin-post handlers. Safe to call more than once.
	 */
	public static function init() {
		if ( ! is_admin() ) {
			return;
		}
		if ( has_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) ) ) {
			return;
		}
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
		/* Visitors who are not signed in: a valid private link is served, everything else goes to wp-login and back. */
		add_action( 'admin_post_nopriv_' . self::ACTION, array( __CLASS__, 'handle_nopriv' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Settings                                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * Whether the admin order email carries private ( no sign-in ) links.
	 *
	 * @return bool
	 */
	public static function private_links_enabled() {
		return 'private' === (string) get_option( self::OPTION_ACCESS, 'login' );
	}

	/**
	 * Private link lifetime in days; 0 = never expires.
	 *
	 * @return int
	 */
	public static function expiry_days() {
		$days = (string) get_option( self::OPTION_EXPIRY, '30' );
		return in_array( $days, array( '7', '30', '90', '0' ), true ) ? (int) $days : 30;
	}

	/**
	 * The signing secret. Created on first use when $create is true.
	 *
	 * @param bool $create Generate and store a secret when none exists.
	 * @return string '' when there is no secret and $create is false.
	 */
	private static function secret( $create = false ) {
		$secret = (string) get_option( self::OPTION_SECRET, '' );
		if ( '' === $secret && $create ) {
			$secret = wp_generate_password( 64, true, true );
			if ( ! add_option( self::OPTION_SECRET, $secret, '', false ) ) {
				/* Another request created it first: use the stored one so both links verify. */
				$stored = (string) get_option( self::OPTION_SECRET, '' );
				$secret = ( '' !== $stored ) ? $stored : $secret;
			}
		}
		return $secret;
	}

	/**
	 * Replace the signing secret. Every private link already sent stops working.
	 *
	 * @return bool
	 */
	public static function rotate_secret() {
		return update_option( self::OPTION_SECRET, wp_generate_password( 64, true, true ), false );
	}

	/**
	 * on_save for the access setting: leaving private-link mode revokes the links already sent,
	 * so switching back on later does not revive them.
	 *
	 * @param mixed $value New value.
	 * @param mixed $old   Previous value.
	 */
	public static function on_access_saved( $value, $old ) {
		if ( 'private' === (string) $old && 'private' !== (string) $value && '' !== self::secret( false ) ) {
			self::rotate_secret();
		}
	}

	/* ------------------------------------------------------------------ */
	/* URLs and tokens                                                     */
	/* ------------------------------------------------------------------ */

	/**
	 * Build the download URL for one uploaded file.
	 *
	 * $durable = true is the admin order email. With private links on it returns a signed,
	 * expiring link that works without a login; otherwise a keyed link that still needs a
	 * staff login. $durable = false is the admin order screen ( nonce, staff login ).
	 *
	 * @param int    $orderdetail_id ec_orderdetail.orderdetail_id the option belongs to.
	 * @param string $option_value   ec_order_option.option_value ( "<tempcart_id>/<filename>" ).
	 * @param bool   $durable        true = email link, false = admin screen link.
	 * @param int    $order_id       ec_order.order_id; looked up from the order item when omitted.
	 * @return string
	 */
	public static function url( $orderdetail_id, $option_value, $durable = false, $order_id = 0 ) {
		$orderdetail_id = (int) $orderdetail_id;
		$option_value = (string) $option_value;
		$args = array(
			'action' => self::ACTION,
			'od' => $orderdetail_id,
			'f' => rawurlencode( $option_value ),
		);
		if ( $durable && self::private_links_enabled() ) {
			$order_id = (int) $order_id;
			if ( $order_id <= 0 ) {
				$order_id = self::order_id_for_item( $orderdetail_id );
			}
			$issued = time();
			$days = self::expiry_days();
			$expires = ( $days > 0 ) ? $issued + ( $days * DAY_IN_SECONDS ) : 0;
			$signature = self::signature( $order_id, $orderdetail_id, $option_value, $issued, $expires, self::secret( true ) );
			if ( $order_id > 0 && '' !== $signature ) {
				$args['o'] = $order_id;
				$args['t'] = $issued;
				$args['e'] = $expires;
				$args['s'] = $signature;
				return add_query_arg( $args, admin_url( 'admin-post.php' ) );
			}
		}
		if ( $durable ) {
			$args['k'] = self::token( $orderdetail_id, $option_value );
		} else {
			$args['_wpnonce'] = wp_create_nonce( self::NONCE_PREFIX . $orderdetail_id );
		}
		return add_query_arg( $args, admin_url( 'admin-post.php' ) );
	}

	/**
	 * Keyed token for signed-in staff that does not expire ( unless the auth salt changes ).
	 *
	 * @param int    $orderdetail_id ec_orderdetail.orderdetail_id.
	 * @param string $option_value   ec_order_option.option_value.
	 * @return string
	 */
	public static function token( $orderdetail_id, $option_value ) {
		return hash_hmac( 'sha256', (int) $orderdetail_id . '|' . (string) $option_value, wp_salt( 'auth' ) );
	}

	/**
	 * Download URL for a file attached to a product inquiry ( admin inquiry email ).
	 *
	 * With private links on: a signed, expiring link that works without a login. Otherwise a
	 * keyed link that needs a staff login, like the admin order email.
	 *
	 * @since 6.0.0
	 *
	 * @param string $folder   Inquiry upload folder ( wp_easycart_customer_uploads::new_folder_name( 'inquiry' ) ).
	 * @param string $filename Stored file name ( the option's recorded value ).
	 * @return string '' when the folder name is not valid.
	 */
	public static function inquiry_url( $folder, $filename ) {
		$folder = (string) $folder;
		$filename = (string) $filename;
		if ( ! self::is_folder_name( $folder ) || '' === $filename ) {
			return '';
		}
		$args = array(
			'action' => self::ACTION,
			'iq' => $folder,
			'f' => rawurlencode( $filename ),
		);
		if ( self::private_links_enabled() ) {
			$issued = time();
			$days = self::expiry_days();
			$expires = ( $days > 0 ) ? $issued + ( $days * DAY_IN_SECONDS ) : 0;
			$signature = self::inquiry_signature( $folder, $filename, $issued, $expires, self::secret( true ) );
			if ( '' !== $signature ) {
				$args['t'] = $issued;
				$args['e'] = $expires;
				$args['s'] = $signature;
				return add_query_arg( $args, admin_url( 'admin-post.php' ) );
			}
		}
		$args['k'] = self::inquiry_token( $folder, $filename );
		return add_query_arg( $args, admin_url( 'admin-post.php' ) );
	}

	/**
	 * Keyed staff token for an inquiry file. The "inquiry|" prefix can never equal an order
	 * token's leading integer, so the two token kinds do not overlap.
	 *
	 * @param string $folder   Inquiry upload folder.
	 * @param string $filename Stored file name.
	 * @return string
	 */
	public static function inquiry_token( $folder, $filename ) {
		return hash_hmac( 'sha256', 'inquiry|' . (string) $folder . '/' . (string) $filename, wp_salt( 'auth' ) );
	}

	/**
	 * Remove upload download links from an email body, keeping the file name as plain text.
	 * Used for the shopper's copy of the inquiry email, which shares the admin template.
	 *
	 * @since 6.0.0
	 *
	 * @param string $html Email HTML.
	 * @return string
	 */
	public static function strip_links( $html ) {
		$stripped = preg_replace( '#<a\s[^>]*href=(["\'])[^"\']*action=' . preg_quote( self::ACTION, '#' ) . '[^"\']*\1[^>]*>(.*?)</a>#is', '$2', (string) $html );
		return ( null === $stripped ) ? (string) $html : $stripped;
	}

	/**
	 * Whether a value is a single safe folder segment.
	 *
	 * @param string $folder Folder name.
	 * @return bool
	 */
	private static function is_folder_name( $folder ) {
		return 1 === preg_match( '/^[A-Za-z0-9_-]{1,64}$/', (string) $folder );
	}

	/**
	 * Private link signature. The option value goes last: every other part is an integer,
	 * so a "|" inside a filename cannot shift the fields.
	 *
	 * @param int    $order_id       ec_order.order_id.
	 * @param int    $orderdetail_id ec_orderdetail.orderdetail_id.
	 * @param string $option_value   ec_order_option.option_value.
	 * @param int    $issued         Unix time the link was created.
	 * @param int    $expires        Unix expiry, 0 = never.
	 * @param string $secret         Signing secret.
	 * @return string '' without a secret.
	 */
	private static function signature( $order_id, $orderdetail_id, $option_value, $issued, $expires, $secret ) {
		if ( '' === (string) $secret ) {
			return '';
		}
		$data = implode( '|', array( self::SIGNATURE_VERSION, (int) $order_id, (int) $orderdetail_id, (int) $issued, (int) $expires, (string) $option_value ) );
		return hash_hmac( 'sha256', $data, $secret );
	}

	/**
	 * Whether the request's signature matches the current secret ( expiry is checked separately ).
	 *
	 * @param array $req Parsed request from read_request().
	 * @return bool
	 */
	private static function signature_ok( $req ) {
		if ( '' === $req['s'] || $req['o'] <= 0 || $req['t'] <= 0 || $req['e'] < 0 ) {
			return false;
		}
		$expected = self::signature( $req['o'], $req['od'], $req['f'], $req['t'], $req['e'], self::secret( false ) );
		return '' !== $expected && hash_equals( $expected, $req['s'] );
	}

	/**
	 * Private inquiry link signature. The folder is limited to [A-Za-z0-9_-] and the file name goes
	 * last, so no field can shift into another.
	 *
	 * @param string $folder   Inquiry upload folder.
	 * @param string $filename Stored file name.
	 * @param int    $issued   Unix time the link was created.
	 * @param int    $expires  Unix expiry, 0 = never.
	 * @param string $secret   Signing secret.
	 * @return string '' without a secret.
	 */
	private static function inquiry_signature( $folder, $filename, $issued, $expires, $secret ) {
		if ( '' === (string) $secret ) {
			return '';
		}
		$data = implode( '|', array( self::INQUIRY_SIGNATURE_VERSION, (int) $issued, (int) $expires, (string) $folder, (string) $filename ) );
		return hash_hmac( 'sha256', $data, $secret );
	}

	/**
	 * Whether an inquiry request's signature matches the current secret ( expiry is checked separately ).
	 *
	 * @param array $req Parsed request from read_request().
	 * @return bool
	 */
	private static function inquiry_signature_ok( $req ) {
		if ( '' === $req['s'] || '' === $req['iq'] || '' === $req['f'] || $req['t'] <= 0 || $req['e'] < 0 ) {
			return false;
		}
		$expected = self::inquiry_signature( $req['iq'], $req['f'], $req['t'], $req['e'], self::secret( false ) );
		return '' !== $expected && hash_equals( $expected, $req['s'] );
	}

	/**
	 * Whether a signed link is past its embedded expiry, or older than the current setting allows
	 * ( so shortening the setting also shortens links already sent ).
	 *
	 * @param array $req Parsed request from read_request().
	 * @return bool
	 */
	private static function signature_expired( $req ) {
		$now = time();
		if ( $req['e'] > 0 && $now > $req['e'] ) {
			return true;
		}
		$days = self::expiry_days();
		if ( $days > 0 && $now > $req['t'] + ( $days * DAY_IN_SECONDS ) ) {
			return true;
		}
		return false;
	}

	/* ------------------------------------------------------------------ */
	/* Handlers                                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * Handler for admin_post_nopriv ( visitor not signed in ).
	 */
	public static function handle_nopriv() {
		$req = self::read_request();
		/* Sign-in required, or not a private link: never serve anything, send the visitor to log in. */
		if ( ! self::private_links_enabled() || '' === $req['s'] ) {
			auth_redirect();
			exit;
		}
		self::serve_private( $req );
	}

	/**
	 * Handler for admin_post ( signed in ): staff, or a signed-in non-staff user holding a private link.
	 */
	public static function handle() {
		if ( ! is_user_logged_in() ) {
			auth_redirect();
			exit;
		}

		$req = self::read_request();

		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_orders' ) ) {
			/* A signed-in shopper account ( e.g. the print partner ) is treated like any visitor holding the link. */
			if ( '' !== $req['s'] && self::private_links_enabled() ) {
				self::serve_private( $req );
			}
			self::fail( __( 'You do not have permission to download this file.', 'wp-easycart' ), 403 );
		}

		if ( '' !== $req['iq'] ) {
			self::serve_inquiry_staff( $req );
		}

		if ( $req['od'] <= 0 || '' === $req['f'] ) {
			self::fail( __( 'Invalid download request.', 'wp-easycart' ), 400 );
		}

		$nonce_ok = ( '' !== $req['nonce'] && false !== wp_verify_nonce( $req['nonce'], self::NONCE_PREFIX . $req['od'] ) );
		$token_ok = ( '' !== $req['k'] && hash_equals( self::token( $req['od'], $req['f'] ), $req['k'] ) );
		/* Staff are authorised by capability, so a genuine signed link is accepted even after it expired for the public. */
		$signed_ok = self::signature_ok( $req );
		if ( ! $nonce_ok && ! $token_ok && ! $signed_ok ) {
			self::fail( __( 'This download link is not valid or has expired. Open the order in the WP EasyCart admin to download the file.', 'wp-easycart' ), 403 );
		}

		$path = self::validated_path( $req['od'], $req['f'], ( $signed_ok ? $req['o'] : 0 ), true );
		self::stream( $path );
	}

	/**
	 * Serve a private ( no sign-in ) link or stop with an explanation. Never returns.
	 *
	 * @param array $req Parsed request from read_request().
	 */
	private static function serve_private( $req ) {
		if ( '' !== $req['iq'] ) {
			self::serve_private_inquiry( $req );
		}
		if ( $req['od'] <= 0 || '' === $req['f'] || ! self::signature_ok( $req ) ) {
			self::link_problem( $req, false );
		}
		if ( self::signature_expired( $req ) ) {
			self::link_problem( $req, true );
		}

		/* The signature binds the order, the item and the file; the database must agree that they belong together. */
		$path = self::validated_path( $req['od'], $req['f'], $req['o'] );

		if ( class_exists( 'ec_db' ) ) {
			$db = new ec_db();
			/* ec_db::insert_response only writes when the store's log setting is on. */
			$db->insert_response(
				$req['o'],
				0,
				'File Upload Private Link',
				sprintf( 'Order item %d: customer file "%s" downloaded through a private link from the admin order email.', $req['od'], wp_basename( $path ) )
			);
		}

		self::stream( $path );
	}

	/**
	 * Inquiry file for signed-in staff: keyed token or a genuine signature ( expired or not ). Never returns.
	 *
	 * @param array $req Parsed request from read_request().
	 */
	private static function serve_inquiry_staff( $req ) {
		if ( '' === $req['f'] ) {
			self::fail( __( 'Invalid download request.', 'wp-easycart' ), 400 );
		}
		$token_ok = ( '' !== $req['k'] && hash_equals( self::inquiry_token( $req['iq'], $req['f'] ), $req['k'] ) );
		if ( ! $token_ok && ! self::inquiry_signature_ok( $req ) ) {
			self::fail( __( 'This download link is not valid.', 'wp-easycart' ), 403 );
		}
		self::stream( self::inquiry_path( $req, true ) );
	}

	/**
	 * Private ( no sign-in ) inquiry link, or stop with an explanation. Never returns.
	 *
	 * @param array $req Parsed request from read_request().
	 */
	private static function serve_private_inquiry( $req ) {
		if ( ! self::inquiry_signature_ok( $req ) ) {
			self::link_problem( $req, false );
		}
		if ( self::signature_expired( $req ) ) {
			self::link_problem( $req, true );
		}

		/* No database row to cross-check: the signature binds the folder and file name the store issued. */
		$path = self::inquiry_path( $req );

		if ( class_exists( 'ec_db' ) ) {
			$db = new ec_db();
			$db->insert_response(
				0,
				0,
				'File Upload Private Link',
				sprintf( 'Product inquiry: customer file "%s" downloaded through a private link from the admin inquiry email.', wp_basename( $path ) )
			);
		}

		self::stream( $path );
	}

	/**
	 * Resolve an inquiry file on disk or stop with 404.
	 *
	 * @param array $req   Parsed request from read_request().
	 * @param bool  $staff Signed-in store staff ( gets order tools on the error page ).
	 * @return string Absolute path.
	 */
	private static function inquiry_path( $req, $staff = false ) {
		$path = self::resolve_path( $req['iq'] . '/' . $req['f'] );
		if ( false === $path ) {
			self::missing_file( $req['f'], 0, $staff );
		}
		return $path;
	}

	/**
	 * Read the query string.
	 *
	 * @return array
	 */
	private static function read_request() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- download links arrive from email; each value is authenticated by capability + nonce / keyed token or by the HMAC signature before use.
		$req = array(
			'od'    => isset( $_GET['od'] ) ? absint( $_GET['od'] ) : 0,
			'f'     => isset( $_GET['f'] ) ? sanitize_text_field( wp_unslash( $_GET['f'] ) ) : '',
			'nonce' => isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '',
			'k'     => isset( $_GET['k'] ) ? sanitize_text_field( wp_unslash( $_GET['k'] ) ) : '',
			'o'     => isset( $_GET['o'] ) ? absint( $_GET['o'] ) : 0,
			't'     => isset( $_GET['t'] ) ? absint( $_GET['t'] ) : 0,
			'e'     => isset( $_GET['e'] ) ? absint( $_GET['e'] ) : 0,
			's'     => isset( $_GET['s'] ) ? strtolower( preg_replace( '/[^a-fA-F0-9]/', '', sanitize_text_field( wp_unslash( $_GET['s'] ) ) ) ) : '',
			'iq'    => isset( $_GET['iq'] ) ? sanitize_text_field( wp_unslash( $_GET['iq'] ) ) : '',
		);
		if ( '' !== $req['iq'] && ! self::is_folder_name( $req['iq'] ) ) {
			/* Not a folder name we ever issue: keep it non-empty so no order path runs, and let the signature / token checks fail. */
			$req['iq'] = 'invalid';
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		return $req;
	}

	/**
	 * Confirm the file option belongs to the order item ( and order, when given ) and resolve it on disk.
	 * Stops with an error page when anything does not match. Never returns false.
	 *
	 * @param int    $orderdetail_id ec_orderdetail.orderdetail_id.
	 * @param string $option_value   Requested option value.
	 * @param int    $order_id       ec_order.order_id the item must belong to; 0 = not checked ( staff with nonce / keyed token ).
	 * @param bool   $staff          Signed-in store staff ( gets order tools on the error page ).
	 * @return string Absolute path.
	 */
	private static function validated_path( $orderdetail_id, $option_value, $order_id = 0, $staff = false ) {
		global $wpdb;

		if ( $order_id > 0 ) {
			$orderdetail_exists = $wpdb->get_var( $wpdb->prepare( 'SELECT orderdetail_id FROM ec_orderdetail WHERE orderdetail_id = %d AND order_id = %d', $orderdetail_id, $order_id ) );
		} else {
			$orderdetail_exists = $wpdb->get_var( $wpdb->prepare( 'SELECT orderdetail_id FROM ec_orderdetail WHERE orderdetail_id = %d', $orderdetail_id ) );
		}
		if ( ! $orderdetail_exists ) {
			self::fail( __( 'Order item not found.', 'wp-easycart' ), 404 );
		}

		/* The requested value must be a real file option on this line item; the stored value is what gets resolved on disk. */
		$stored_value = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM ec_order_option WHERE orderdetail_id = %d AND option_type = 'file' AND option_value = %s LIMIT 1", $orderdetail_id, $option_value ) );
		if ( null === $stored_value || '' === $stored_value ) {
			self::fail( __( 'Uploaded file not found for this order item.', 'wp-easycart' ), 404 );
		}

		$path = self::resolve_path( $stored_value );
		if ( false === $path ) {
			self::missing_file( wp_basename( str_replace( '\\', '/', (string) $stored_value ) ), ( $order_id > 0 ? $order_id : self::order_id_for_item( $orderdetail_id ) ), $staff );
		}
		return $path;
	}

	/**
	 * Whether a stored file option value ( "<folder>/<filename>" ) exists on disk, so order screens
	 * and emails can say the file never arrived instead of linking to it.
	 *
	 * @since 6.0.0
	 *
	 * @param string $option_value ec_order_option.option_value.
	 * @return bool
	 */
	public static function file_available( $option_value ) {
		return false !== self::resolve_path( $option_value );
	}

	/**
	 * Page for a link whose file is not on the server. Never returns.
	 *
	 * Staff get the order and a pre-filled email asking the customer to resend; anyone else holding a
	 * private link is told to contact the store ( no order or customer details ).
	 *
	 * @since 6.0.0
	 *
	 * @param string $filename File name the order recorded.
	 * @param int    $order_id Order id, 0 for product inquiries.
	 * @param bool   $staff    Signed-in store staff.
	 */
	private static function missing_file( $filename, $order_id, $staff ) {
		global $wpdb;
		if ( ! headers_sent() ) {
			header( 'Referrer-Policy: no-referrer' );
			header( 'X-Robots-Tag: noindex, nofollow' );
		}
		$filename = sanitize_text_field( (string) $filename );
		$html     = '<h1>' . esc_html__( 'File not available', 'wp-easycart' ) . '</h1>';
		/* translators: %s: file name. */
		$html .= '<p>' . sprintf( esc_html__( 'The customer file %s is not on the server.', 'wp-easycart' ), '<strong>' . esc_html( '' !== $filename ? $filename : __( '(no name)', 'wp-easycart' ) ) . '</strong>' ) . '</p>';

		if ( $staff ) {
			$html .= '<p>' . esc_html__( 'The store did not keep the file when the item was added to the cart, usually because it was a file type or size uploads do not accept, or the file was deleted later. Ask the customer to send it again.', 'wp-easycart' ) . '</p>';
			$actions = array();
			if ( $order_id > 0 ) {
				$actions[] = '<a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=wp-easycart-orders&subpage=orders&ec_admin_form_action=edit&order_id=' . (int) $order_id ) ) . '">' . esc_html( sprintf( /* translators: %d: order number. */ __( 'Open order #%d', 'wp-easycart' ), (int) $order_id ) ) . '</a>';
				$email = ( isset( $wpdb ) && is_object( $wpdb ) ) ? (string) $wpdb->get_var( $wpdb->prepare( 'SELECT user_email FROM ec_order WHERE order_id = %d', (int) $order_id ) ) : '';
				if ( is_email( $email ) ) {
					/* translators: 1: order number, 2: file name. */
					$subject   = sprintf( __( 'Order #%1$d: please send your file %2$s again', 'wp-easycart' ), (int) $order_id, $filename );
					$mailto    = 'mailto:' . $email . '?subject=' . rawurlencode( $subject );
					$actions[] = '<a class="button" href="' . esc_url( $mailto, array( 'mailto' ) ) . '">' . esc_html__( 'Email the customer', 'wp-easycart' ) . '</a>';
				}
			}
			if ( ! empty( $actions ) ) {
				$html .= '<p>' . implode( ' ', $actions ) . '</p>';
			}
		} else {
			$html .= '<p>' . esc_html__( 'Please contact the store; they can ask the customer to send the file again.', 'wp-easycart' ) . '</p>';
		}
		wp_die( wp_kses_post( $html ), esc_html__( 'File not available', 'wp-easycart' ), array( 'response' => 404 ) );
	}

	/**
	 * Order id for an order item, 0 when unknown.
	 *
	 * @param int $orderdetail_id ec_orderdetail.orderdetail_id.
	 * @return int
	 */
	private static function order_id_for_item( $orderdetail_id ) {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return 0;
		}
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT order_id FROM ec_orderdetail WHERE orderdetail_id = %d', (int) $orderdetail_id ) );
	}

	/**
	 * Resolve "<tempcart_id>/<filename>" to an absolute path inside the uploads directory.
	 *
	 * @param string $relative "<tempcart_id>/<filename>" as stored in ec_order_option.option_value.
	 * @return string|false
	 */
	private static function resolve_path( $relative ) {
		$relative = str_replace( '\\', '/', (string) $relative );
		$relative = ltrim( $relative, '/' );
		if ( '' === $relative || false !== strpos( $relative, '..' ) || false !== strpos( $relative, "\0" ) ) {
			return false;
		}
		$parts = explode( '/', $relative );
		if ( 2 !== count( $parts ) || '' === $parts[0] || '' === $parts[1] ) {
			return false;
		}

		foreach ( self::base_dirs() as $base ) {
			$candidate = realpath( $base . DIRECTORY_SEPARATOR . $parts[0] . DIRECTORY_SEPARATOR . $parts[1] );
			if ( false === $candidate ) {
				continue;
			}
			if ( 0 !== strpos( $candidate, $base . DIRECTORY_SEPARATOR ) ) {
				continue;
			}
			if ( is_file( $candidate ) && is_readable( $candidate ) ) {
				return $candidate;
			}
		}
		return false;
	}

	/**
	 * Upload roots, resolved with realpath. Mirrors wp_easycart_customer_uploads::base_dir(),
	 * which writes to the data directory and falls back to the plugin directory.
	 *
	 * @return array
	 */
	private static function base_dirs() {
		$dirs = array();
		foreach ( array( EC_PLUGIN_DATA_DIRECTORY . '/products/uploads', EC_PLUGIN_DIRECTORY . '/products/uploads' ) as $dir ) {
			$real = realpath( $dir );
			if ( false !== $real && is_dir( $real ) ) {
				$dirs[] = rtrim( $real, '/\\' );
			}
		}
		return $dirs;
	}

	/**
	 * Send the file as an attachment and exit.
	 *
	 * @param string $path Absolute, already validated path.
	 */
	private static function stream( $path ) {
		$filename = wp_basename( $path );
		$filetype = wp_check_filetype( $filename );
		$mime = ( ! empty( $filetype['type'] ) ) ? $filetype['type'] : 'application/octet-stream';
		$ascii_name = preg_replace( '/[^\x20-\x7E]/', '_', $filename );
		$ascii_name = str_replace( array( '"', '\\' ), '_', $ascii_name );

		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		nocache_headers();
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Referrer-Policy: no-referrer' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		header( 'Content-Type: ' . $mime );
		header( 'Content-Disposition: attachment; filename="' . $ascii_name . '"; filename*=UTF-8\'\'' . rawurlencode( $filename ) );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'Content-Transfer-Encoding: binary' );

		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- streaming a validated local file to the browser.
		exit;
	}

	/**
	 * Plain page for a private link that is invalid, revoked or expired, with a way to sign in.
	 *
	 * @param array $req     Parsed request from read_request().
	 * @param bool  $expired true = expired, false = not valid ( tampered or revoked ).
	 */
	private static function link_problem( $req, $expired ) {
		if ( ! headers_sent() ) {
			header( 'Referrer-Policy: no-referrer' );
			header( 'X-Robots-Tag: noindex, nofollow' );
		}
		$message = $expired
			? __( 'This download link has expired.', 'wp-easycart' )
			: __( 'This download link is not valid. It may have been revoked by the store.', 'wp-easycart' );
		$html = '<h1>' . esc_html__( 'Download unavailable', 'wp-easycart' ) . '</h1><p>' . esc_html( $message ) . '</p>';
		if ( is_user_logged_in() ) {
			$html .= '<p>' . esc_html__( 'Ask the store to send the order email again for a new link.', 'wp-easycart' ) . '</p>';
		} else {
			/* After signing in, staff land back on this link and get the file through the staff path. */
			$back_args = array(
				'action' => self::ACTION,
				'f' => rawurlencode( (string) $req['f'] ),
				't' => (int) $req['t'],
				'e' => (int) $req['e'],
				's' => (string) $req['s'],
			);
			if ( '' !== $req['iq'] ) {
				$back_args['iq'] = (string) $req['iq'];
			} else {
				$back_args['od'] = (int) $req['od'];
				$back_args['o'] = (int) $req['o'];
			}
			$back = add_query_arg( $back_args, admin_url( 'admin-post.php' ) );
			$html .= '<p>' . esc_html__( 'Store staff can sign in to download the file, or ask the store to send the order email again for a new link.', 'wp-easycart' ) . '</p>';
			$html .= '<p><a href="' . esc_url( wp_login_url( $back ) ) . '">' . esc_html__( 'Sign in to WP Admin', 'wp-easycart' ) . '</a></p>';
		}
		wp_die( wp_kses_post( $html ), esc_html__( 'Download unavailable', 'wp-easycart' ), array( 'response' => $expired ? 410 : 403 ) );
	}

	/**
	 * Stop with an HTTP status and a plain message.
	 *
	 * @param string $message Plain-text message shown on the error page.
	 * @param int    $status  HTTP status code.
	 */
	private static function fail( $message, $status ) {
		wp_die( esc_html( $message ), esc_html__( 'WP EasyCart', 'wp-easycart' ), array( 'response' => (int) $status ) );
	}
}

endif;
