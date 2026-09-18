<?php
/**
 * WP EasyCart — Private storage for customer file uploads ( the "file" option type ).
 *
 * Files live in <uploads root>/<folder>/<filename>, where the uploads root is
 * EC_PLUGIN_DATA_DIRECTORY . '/products/uploads' ( or the plugin folder's copy on very old
 * installs ) and <folder> is the cart session id ( add to cart, subscriptions ) or a random
 * "inquiry-<hex>" name ( product inquiries ). Nothing in the uploads root is meant to be
 * fetched by URL: every legitimate download goes through
 * admin-post.php?action=wp_easycart_order_upload ( wp_easycart_admin_order_uploads ).
 *
 * Paid download files get the same treatment ( area "downloads" ). They live in
 * EC_PLUGIN_DATA_DIRECTORY . '/products/downloads', in wp-content/uploads/wp-easycart ( the
 * media uploader's is_wpec_download location ) and, on very old installs, in the plugin
 * folder's products/downloads. Customers are only ever served these by
 * ec_orderdetail::start_download(), which reads the file from disk after checking the order,
 * the download limit and the expiry.
 *
 * This class:
 * - writes deny rules into every protected folder: .htaccess ( Apache 2.2 and 2.4, LiteSpeed ),
 *   web.config ( IIS ) and an index.php silence file, on activation, once per
 *   PROTECTION_VERSION after an update, from Diagnostics, and before each upload when any of
 *   them is missing;
 * - stores an upload straight into its private folder. wp_handle_upload() is pointed at the
 *   folder through the upload_dir filter, so no copy is ever written to the public
 *   WordPress uploads directory;
 * - checks with a loopback request whether a protected folder is still publicly reachable
 *   ( nginx ignores .htaccess ) and supplies the nginx rule for Diagnostics.
 *
 * @package wp-easycart
 * @since   6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_customer_uploads' ) ) :

	/**
	 * Customer upload storage, protection and exposure check.
	 */
	class wp_easycart_customer_uploads {

		/** Bump when the protection file contents change so existing stores rewrite them after updating. */
		const PROTECTION_VERSION = '2';

		/** Option holding the PROTECTION_VERSION last written by maybe_protect(). */
		const OPTION_PROTECTED = 'wp_easycart_uploads_protection_version';

		/** Cached result of check_public_access() for customer uploads. */
		const TRANSIENT_ACCESS = 'wp_easycart_uploads_access_check';

		/** Cached result of check_public_access() for paid downloads. */
		const TRANSIENT_DOWNLOADS_ACCESS = 'wp_easycart_downloads_access_check';

		/** Harmless text file in a protected folder that the loopback check requests. */
		const PROBE_FILE = 'wp-easycart-access-check.txt';

		/** Marker inside the probe file: seeing it in a response proves the file itself was served. */
		const PROBE_TOKEN = 'wp-easycart-private-upload-probe';

		// Locations.

		/**
		 * The uploads root new files are written to. Mirrors the pre-6.0.0 rule: the data folder
		 * when it exists, otherwise the plugin folder's copy, otherwise the data folder is created.
		 *
		 * @return string Absolute path without a trailing slash, '' when no folder can be used.
		 */
		public static function base_dir() {
			$data   = EC_PLUGIN_DATA_DIRECTORY . '/products/uploads';
			$plugin = EC_PLUGIN_DIRECTORY . '/products/uploads';
			if ( is_dir( $data ) ) {
				return $data;
			}
			if ( is_dir( $plugin ) ) {
				return $plugin;
			}
			if ( function_exists( 'wp_mkdir_p' ) && is_dir( EC_PLUGIN_DATA_DIRECTORY ) && wp_mkdir_p( $data ) ) {
				return $data;
			}
			return '';
		}

		/**
		 * Every uploads root that exists ( data folder and plugin folder ).
		 *
		 * @return array Absolute paths without trailing slashes.
		 */
		public static function existing_base_dirs() {
			return self::existing_dirs( 'uploads' );
		}

		/**
		 * Protected areas: customer uploads and paid downloads.
		 *
		 * @since 6.0.0
		 *
		 * @return array Area keys.
		 */
		public static function areas() {
			return array( 'uploads', 'downloads' );
		}

		/**
		 * Every folder of an area, whether or not it exists. The downloads list mirrors the
		 * folders ec_orderdetail::start_download() reads from.
		 *
		 * @since 6.0.0
		 *
		 * @param string $area 'uploads' or 'downloads'.
		 * @return array Absolute paths without trailing slashes.
		 */
		public static function area_dirs( $area ) {
			if ( 'downloads' === $area ) {
				return array(
					EC_PLUGIN_DATA_DIRECTORY . '/products/downloads',
					WP_CONTENT_DIR . '/uploads/wp-easycart',
					EC_PLUGIN_DIRECTORY . '/products/downloads',
				);
			}
			return array( EC_PLUGIN_DATA_DIRECTORY . '/products/uploads', EC_PLUGIN_DIRECTORY . '/products/uploads' );
		}

		/**
		 * The folders of an area that exist.
		 *
		 * @since 6.0.0
		 *
		 * @param string $area 'uploads' or 'downloads'.
		 * @return array Absolute paths without trailing slashes.
		 */
		public static function existing_dirs( $area ) {
			$dirs = array();
			foreach ( self::area_dirs( $area ) as $dir ) {
				if ( is_dir( $dir ) ) {
					$dirs[] = $dir;
				}
			}
			return $dirs;
		}

		/**
		 * Public URL of a protected folder ( used only for the exposure check and the nginx rule ).
		 *
		 * @param string $dir Folder inside the data folder, the plugin folder or wp-content.
		 * @return string URL with a trailing slash, '' when the folder is outside all three.
		 */
		public static function base_url( $dir ) {
			$dir   = wp_normalize_path( untrailingslashit( (string) $dir ) );
			$roots = array(
				array( EC_PLUGIN_DATA_DIRECTORY, 'plugins' ),
				array( EC_PLUGIN_DIRECTORY, 'plugins' ),
				array( WP_CONTENT_DIR, 'content' ),
			);
			foreach ( $roots as $root ) {
				$prefix = wp_normalize_path( untrailingslashit( $root[0] ) ) . '/';
				if ( 0 !== strpos( $dir . '/', $prefix ) ) {
					continue;
				}
				$relative = (string) substr( $dir, strlen( $prefix ) );
				if ( 'content' === $root[1] ) {
					return trailingslashit( content_url( $relative ) );
				}
				return trailingslashit( plugins_url( wp_basename( $root[0] ) . ( '' !== $relative ? '/' . $relative : '' ) ) );
			}
			return '';
		}

		/**
		 * Whether a folder name is safe as a single path segment.
		 *
		 * @param string $folder Folder name.
		 * @return bool
		 */
		public static function is_valid_folder_name( $folder ) {
			return 1 === preg_match( '/^[A-Za-z0-9_-]{1,64}$/', (string) $folder );
		}

		/**
		 * A new unguessable folder name, e.g. "inquiry-3f9c…" ( 128 random bits ).
		 *
		 * @param string $prefix Letters, digits, _ or -.
		 * @return string
		 */
		public static function new_folder_name( $prefix ) {
			$prefix = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $prefix );
			try {
				$random = bin2hex( random_bytes( 16 ) );
			} catch ( Exception $e ) {
				$random = strtolower( wp_generate_password( 32, false, false ) );
			}
			return ( '' !== $prefix ? $prefix . '-' : '' ) . $random;
		}

		// Deny rules.

		/**
		 * Protection files keyed by file name.
		 *
		 * @param string $area 'uploads' or 'downloads' ( only the explanatory comments differ ).
		 * @return array
		 */
		public static function protection_files( $area = 'uploads' ) {
			if ( 'downloads' === $area ) {
				$title = 'WP EasyCart download protection v' . self::PROTECTION_VERSION;
				$why   = array(
					'Paid download files are private. Customers download them from their account order',
					'history, which checks the order, the download limit and the expiry, never by direct URL.',
				);
			} else {
				$title = 'WP EasyCart upload protection v' . self::PROTECTION_VERSION;
				$why   = array(
					'Customer files are private. Staff download them through',
					'wp-admin/admin-post.php?action=wp_easycart_order_upload, never by direct URL.',
				);
			}

			$htaccess = implode(
				"\n",
				array(
					'# ' . $title,
					'# ' . $why[0],
					'# ' . $why[1],
					'<IfModule mod_authz_core.c>',
					'	Require all denied',
					'</IfModule>',
					'<IfModule !mod_authz_core.c>',
					'	Order Deny,Allow',
					'	Deny from all',
					'</IfModule>',
					'',
				)
			);

			$web_config = implode(
				"\n",
				array(
					'<?xml version="1.0" encoding="UTF-8"?>',
					'<!-- ' . $title . ': ' . $why[0] . ' ' . $why[1] . ' -->',
					'<configuration>',
					'	<system.webServer>',
					'		<directoryBrowse enabled="false" />',
					'		<security>',
					'			<requestFiltering>',
					'				<fileExtensions allowUnlisted="false" />',
					'			</requestFiltering>',
					'		</security>',
					'	</system.webServer>',
					'</configuration>',
					'',
				)
			);

			return array(
				'.htaccess'  => $htaccess,
				'web.config' => $web_config,
				'index.php'  => "<?php\n// Silence is golden.\n",
			);
		}

		/**
		 * Write ( or refresh ) the deny rules in one protected folder.
		 *
		 * @param string $dir  Folder; '' = base_dir() ( customer uploads only ).
		 * @param string $area 'uploads' or 'downloads'.
		 * @return bool true when every file is in place with the current contents.
		 */
		public static function protect( $dir = '', $area = 'uploads' ) {
			if ( '' === (string) $dir ) {
				if ( 'uploads' !== $area ) {
					return self::protect_area( $area );
				}
				$dir = self::base_dir();
			}
			$dir = rtrim( (string) $dir, '/\\' );
			if ( '' === $dir || ! is_dir( $dir ) ) {
				return false;
			}
			$ok = true;
			foreach ( self::protection_files( $area ) as $name => $contents ) {
				$path = $dir . '/' . $name;
				/* This runs on the storefront ( before every upload ), so nothing here may print a warning: an existing file is only read when it is readable, and only rewritten when it is writable. */
				if ( is_file( $path ) && is_readable( $path ) && self::read_file( $path ) === $contents ) {
					continue;
				}
				if ( ( file_exists( $path ) && ! wp_is_writable( $path ) ) || ( ! file_exists( $path ) && ! wp_is_writable( $dir ) ) ) {
					$ok = false;
					continue;
				}
				/* WP_Filesystem can need FTP credentials; this also runs during storefront uploads, so write directly. */
				if ( ! self::write_file( $path, $contents ) ) {
					$ok = false;
				}
			}
			return $ok;
		}

		// Warning-free filesystem helpers.

		/**
		 * Run a filesystem call with PHP warnings and notices muted ( is_writable() can say yes and the
		 * write still fail on open_basedir, read-only mounts or ownership quirks ). Returns whatever the
		 * callback returns; callers treat false as failure.
		 *
		 * @since 6.0.0
		 *
		 * @param callable $callback The call to make.
		 * @return mixed
		 */
		private static function silently( $callback ) {
			set_error_handler( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- muting one filesystem call on the storefront; restored right after.
				function () {
					return true;
				}
			);
			try {
				$result = call_user_func( $callback );
			} finally {
				restore_error_handler();
			}
			return $result;
		}

		/**
		 * Contents of a small local file, '' when it cannot be read.
		 *
		 * @since 6.0.0
		 *
		 * @param string $path File path.
		 * @return string
		 */
		private static function read_file( $path ) {
			if ( ! is_file( $path ) || ! is_readable( $path ) ) {
				return '';
			}
			$contents = self::silently(
				function () use ( $path ) {
					return file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a small local file.
				}
			);
			return is_string( $contents ) ? $contents : '';
		}

		/**
		 * Write a small file, never printing a warning.
		 *
		 * @since 6.0.0
		 *
		 * @param string $path     File path.
		 * @param string $contents Contents.
		 * @return bool
		 */
		private static function write_file( $path, $contents ) {
			$written = self::silently(
				function () use ( $path, $contents ) {
					return file_put_contents( $path, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- WP_Filesystem can need FTP credentials and this runs during storefront uploads.
				}
			);
			return false !== $written;
		}

		/**
		 * Rename a file or folder, never printing a warning.
		 *
		 * @since 6.0.0
		 *
		 * @param string $from Current path.
		 * @param string $to   New path.
		 * @return bool
		 */
		private static function rename_path( $from, $to ) {
			return true === self::silently(
				function () use ( $from, $to ) {
					return rename( $from, $to ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- move inside the plugin-owned uploads folder.
				}
			);
		}

		// Following the cart when its session id changes.

		/**
		 * Move a cart's upload folder to its new session id. wpeasycart_session::rotate_session_id()
		 * renames the cart rows and fires wpeasycart_session_rotated when a shopper logs in, registers or
		 * restores a cart at checkout; the order then records "<new id>/<file>", so the files have to
		 * follow or staff see "file not received".
		 *
		 * The session is also rotated right after an order is placed. That order already recorded the old
		 * folder and the cart has just been emptied, so nothing is moved when the cart has no rows. And
		 * when an earlier order still points at the old folder ( a gateway return path that did not
		 * rotate ), the files are copied rather than moved so that order keeps working too.
		 *
		 * @since 6.0.0
		 *
		 * @param string $old_id Previous session id.
		 * @param string $new_id New session id.
		 */
		public static function on_session_rotated( $old_id, $new_id ) {
			global $wpdb;

			$old_id = (string) $old_id;
			$new_id = (string) $new_id;
			if ( $old_id === $new_id || ! self::is_valid_folder_name( $old_id ) || ! self::is_valid_folder_name( $new_id ) ) {
				return;
			}
			$sources = array();
			foreach ( self::existing_base_dirs() as $base ) {
				if ( is_dir( $base . '/' . $old_id ) ) {
					$sources[] = $base;
				}
			}
			if ( empty( $sources ) || ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
				return;
			}
			/* The cart rows already carry the new id. No rows = the cart was just turned into an order that recorded the old folder. */
			if ( 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_tempcart WHERE session_id = %s', $new_id ) ) ) {
				return;
			}
			$keep_old = (bool) $wpdb->get_var( $wpdb->prepare( "SELECT orderdetail_id FROM ec_order_option WHERE option_type = 'file' AND option_value LIKE %s LIMIT 1", $wpdb->esc_like( $old_id . '/' ) . '%' ) );

			foreach ( $sources as $base ) {
				if ( ! self::move_folder( $base . '/' . $old_id, $base . '/' . $new_id, $keep_old ) && class_exists( 'ec_db' ) ) {
					$db = new ec_db();
					/* ec_db::insert_response only writes when the store's log setting is on. */
					$db->insert_response( 0, 1, 'Customer File Upload', sprintf( 'Could not move the upload folder %1$s to %2$s when the cart session changed; check the folder permissions. Files this cart uploaded may show as not received on the order.', self::display_path( $base . '/' . $old_id ), self::display_path( $base . '/' . $new_id ) ) );
				}
			}
		}

		/**
		 * Move ( or copy ) every file of one upload folder into another, without ever printing a warning.
		 * A missing target is renamed into place in one step; otherwise files go over one by one, files
		 * already at the target are left alone, and the old folder is removed once it is empty.
		 *
		 * @since 6.0.0
		 *
		 * @param string $from Existing folder.
		 * @param string $to   Target folder ( may exist ).
		 * @param bool   $copy Copy instead of move, leaving $from as it is.
		 * @return bool false when something could not be moved.
		 */
		private static function move_folder( $from, $to, $copy = false ) {
			if ( ! is_dir( $from ) || ! is_readable( $from ) ) {
				return true;
			}
			$parent = dirname( $from );
			if ( ! $copy && ! is_dir( $to ) ) {
				if ( wp_is_writable( $parent ) && self::rename_path( $from, $to ) ) {
					return true;
				}
			}
			if ( ! is_dir( $to ) && ( ! wp_is_writable( $parent ) || ! wp_mkdir_p( $to ) ) ) {
				return false;
			}
			/* Moving an entry out of $from needs $from writable ( the file's own mode does not matter ); landing it needs $to writable. */
			if ( ! wp_is_writable( $to ) || ( ! $copy && ! wp_is_writable( $from ) ) ) {
				return false;
			}
			$entries = self::silently(
				function () use ( $from ) {
					return scandir( $from );
				}
			);
			if ( ! is_array( $entries ) ) {
				return false;
			}
			$ok = true;
			foreach ( $entries as $entry ) {
				if ( '.' === $entry || '..' === $entry ) {
					continue;
				}
				$source = $from . '/' . $entry;
				$target = $to . '/' . $entry;
				if ( ! is_file( $source ) ) {
					$ok = false; /* nothing of ours nests folders here; leave it */
					continue;
				}
				if ( file_exists( $target ) ) {
					continue;
				}
				if ( $copy ) {
					$copied = is_readable( $source ) && true === self::silently(
						function () use ( $source, $target ) {
							return copy( $source, $target );
						}
					);
					if ( ! $copied ) {
						$ok = false;
					}
				} elseif ( ! self::rename_path( $source, $target ) ) {
					$ok = false;
				}
			}
			if ( ! $copy && wp_is_writable( $parent ) ) {
				$left = self::silently(
					function () use ( $from ) {
						return scandir( $from );
					}
				);
				if ( is_array( $left ) && count( $left ) <= 2 ) {
					self::silently(
						function () use ( $from ) {
							return rmdir( $from ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- removing the now-empty old session folder inside the plugin-owned uploads folder.
						}
					);
				}
			}
			return $ok;
		}

		/**
		 * Write the deny rules into every existing folder of one area.
		 *
		 * @since 6.0.0
		 *
		 * @param string $area 'uploads' or 'downloads'.
		 * @return bool
		 */
		public static function protect_area( $area ) {
			$ok = true;
			foreach ( self::existing_dirs( $area ) as $dir ) {
				$ok = self::protect( $dir, $area ) && $ok;
			}
			return $ok;
		}

		/**
		 * Write the deny rules into every protected folder ( customer uploads and paid downloads ).
		 *
		 * @return bool
		 */
		public static function protect_all() {
			$ok = true;
			foreach ( self::areas() as $area ) {
				$ok = self::protect_area( $area ) && $ok;
			}
			return $ok;
		}

		/**
		 * Once per PROTECTION_VERSION ( new installs and plugin updates ): write the deny rules.
		 * Hooked from wpeasycart_update_check() on plugins_loaded.
		 */
		public static function maybe_protect() {
			if ( self::PROTECTION_VERSION === (string) get_option( self::OPTION_PROTECTED, '' ) ) {
				return;
			}
			self::protect_all();
			/* Recorded even when a folder was not writable so this does not retry on every request; uploads and Diagnostics retry. */
			update_option( self::OPTION_PROTECTED, self::PROTECTION_VERSION );
			delete_transient( self::TRANSIENT_ACCESS );
			delete_transient( self::TRANSIENT_DOWNLOADS_ACCESS );
		}

		/**
		 * Cheap check before writing into a folder: rewrite the deny rules when any file is missing.
		 *
		 * @param string $dir  Protected folder.
		 * @param string $area 'uploads' or 'downloads'.
		 */
		public static function ensure_protected( $dir, $area = 'uploads' ) {
			foreach ( array_keys( self::protection_files( $area ) ) as $name ) {
				if ( ! is_file( $dir . '/' . $name ) ) {
					self::protect( $dir, $area );
					return;
				}
			}
		}

		// Storing an upload.

		/**
		 * The name a customer file is stored under. It must equal the value the cart and order
		 * record for the option ( sanitize_text_field() of the browser's file name ), because the
		 * download handler resolves "<folder>/<that value>".
		 *
		 * @since 6.0.0 $extensions: an option's own list of allowed file types.
		 *
		 * @param string $name       Browser-supplied file name.
		 * @param array  $extensions File type keys chosen on the option ( option_extensions() ); empty = the store default.
		 * @return string '' when the name cannot be stored safely.
		 */
		public static function stored_filename( $name, $extensions = array() ) {
			$name = sanitize_text_field( (string) $name );
			if ( '' === $name || false !== strpbrk( $name, "/\\\0" ) || '.' === substr( $name, 0, 1 ) ) {
				return '';
			}
			/* Never a script, page or server file, however the option is set up ( also "photo.php.jpg" ). */
			if ( self::has_denied_extension( $name ) ) {
				return '';
			}
			if ( ! empty( $extensions ) ) {
				return in_array( self::file_extension( $name ), self::expand_extensions( $extensions ), true ) ? $name : '';
			}
			/* Only extensions WordPress allows for uploads ( never .php, .htaccess, web.config ). */
			$check = wp_check_filetype( $name );
			if ( empty( $check['ext'] ) ) {
				return '';
			}
			return $name;
		}

		// Allowed file types per option ( 6.0.0 ).

		/**
		 * File types a merchant can allow on a file upload option. Keyed by the type shown in the
		 * option editor; 'extensions' are the file endings it covers and 'mimes' the contents the
		 * server accepts for it ( as detected by fileinfo, never the browser's claim ).
		 * SVG is deliberately absent: it can carry scripts ( filter: wp_easycart_customer_upload_file_types;
		 * denied_extensions() still applies to anything added ).
		 *
		 * @since 6.0.0
		 *
		 * @return array
		 */
		public static function file_types() {
			$octet = 'application/octet-stream';
			$ole   = array( 'application/msword', 'application/vnd.ms-excel', 'application/vnd.ms-powerpoint', 'application/vnd.ms-office', 'application/cdfv2', 'application/x-ole-storage', $octet );
			$zip   = array( 'application/zip', $octet );
			$types = array(
				'jpg'  => array( array( 'jpg', 'jpeg', 'jpe' ), array( 'image/jpeg', 'image/pjpeg' ) ),
				'png'  => array( array( 'png' ), array( 'image/png' ) ),
				'gif'  => array( array( 'gif' ), array( 'image/gif' ) ),
				'webp' => array( array( 'webp' ), array( 'image/webp' ) ),
				'heic' => array( array( 'heic', 'heif' ), array( 'image/heic', 'image/heif', 'image/heic-sequence', 'image/heif-sequence', $octet ) ),
				'tif'  => array( array( 'tif', 'tiff' ), array( 'image/tiff' ) ),
				'bmp'  => array( array( 'bmp' ), array( 'image/bmp', 'image/x-ms-bmp', 'image/x-bmp' ) ),
				'pdf'  => array( array( 'pdf' ), array( 'application/pdf', 'application/x-pdf' ) ),
				'doc'  => array( array( 'doc' ), $ole ),
				'docx' => array( array( 'docx' ), array_merge( array( 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ), $zip ) ),
				'txt'  => array( array( 'txt' ), array( 'text/plain' ) ),
				'rtf'  => array( array( 'rtf' ), array( 'text/rtf', 'application/rtf' ) ),
				'odt'  => array( array( 'odt' ), array_merge( array( 'application/vnd.oasis.opendocument.text' ), $zip ) ),
				'csv'  => array( array( 'csv' ), array( 'text/csv', 'text/plain', 'application/csv', 'text/x-csv' ) ),
				'xls'  => array( array( 'xls' ), $ole ),
				'xlsx' => array( array( 'xlsx' ), array_merge( array( 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' ), $zip ) ),
				'ppt'  => array( array( 'ppt' ), $ole ),
				'pptx' => array( array( 'pptx' ), array_merge( array( 'application/vnd.openxmlformats-officedocument.presentationml.presentation' ), $zip ) ),
				'psd'  => array( array( 'psd' ), array( 'image/vnd.adobe.photoshop', 'image/x-photoshop', 'application/x-photoshop', 'application/photoshop', 'image/psd', $octet ) ),
				'ai'   => array( array( 'ai' ), array( 'application/pdf', 'application/postscript', 'application/illustrator', $octet ) ),
				'eps'  => array( array( 'eps' ), array( 'application/postscript', 'application/eps', 'image/x-eps', $octet ) ),
				'zip'  => array( array( 'zip' ), array( 'application/zip', 'application/x-zip-compressed', $octet ) ),
				'gz'   => array( array( 'gz', 'gzip' ), array( 'application/gzip', 'application/x-gzip' ) ),
				'bz2'  => array( array( 'bz2' ), array( 'application/x-bzip2', 'application/x-bzip' ) ),
				'7z'   => array( array( '7z' ), array( 'application/x-7z-compressed' ) ),
				'rar'  => array( array( 'rar' ), array( 'application/x-rar', 'application/vnd.rar', 'application/x-rar-compressed' ) ),
			);
			$out   = array();
			foreach ( $types as $key => $type ) {
				$out[ $key ] = array(
					'extensions' => $type[0],
					'mimes'      => $type[1],
				);
			}
			$filtered = apply_filters( 'wp_easycart_customer_upload_file_types', $out );
			if ( ! is_array( $filtered ) ) {
				return $out;
			}
			$denied = self::denied_extensions();
			$clean  = array();
			foreach ( $filtered as $key => $type ) {
				$key = strtolower( preg_replace( '/[^A-Za-z0-9]/', '', (string) $key ) );
				if ( '' === $key || ! is_array( $type ) || empty( $type['extensions'] ) || in_array( $key, $denied, true ) ) {
					continue;
				}
				$extensions = array_values( array_diff( array_map( 'strtolower', array_map( 'strval', (array) $type['extensions'] ) ), $denied ) );
				if ( empty( $extensions ) ) {
					continue;
				}
				$clean[ $key ] = array(
					'extensions' => $extensions,
					'mimes'      => array_map( 'strtolower', array_map( 'strval', isset( $type['mimes'] ) ? (array) $type['mimes'] : array() ) ),
				);
			}
			return $clean;
		}

		/**
		 * The file_types() catalog grouped for the option editor. Types added through the filter land in "Other".
		 *
		 * @since 6.0.0
		 *
		 * @return array group key => { label, types: array of type keys }
		 */
		public static function file_type_groups() {
			$groups = array(
				'images'    => array(
					'label' => __( 'Images', 'wp-easycart' ),
					'types' => array( 'jpg', 'png', 'gif', 'webp', 'heic', 'tif', 'bmp' ),
				),
				'documents' => array(
					'label' => __( 'Documents', 'wp-easycart' ),
					'types' => array( 'pdf', 'doc', 'docx', 'txt', 'rtf', 'odt', 'csv', 'xls', 'xlsx', 'ppt', 'pptx' ),
				),
				'design'    => array(
					'label' => __( 'Design', 'wp-easycart' ),
					'types' => array( 'psd', 'ai', 'eps' ),
				),
				'archives'  => array(
					'label' => __( 'Archives', 'wp-easycart' ),
					'types' => array( 'zip', 'gz', 'bz2', '7z', 'rar' ),
				),
			);
			$known  = self::file_types();
			$listed = array();
			foreach ( $groups as $key => $group ) {
				$groups[ $key ]['types'] = array_values( array_intersect( $group['types'], array_keys( $known ) ) );
				$listed                  = array_merge( $listed, $groups[ $key ]['types'] );
			}
			$other = array_values( array_diff( array_keys( $known ), $listed ) );
			if ( ! empty( $other ) ) {
				$groups['other'] = array(
					'label' => __( 'Other', 'wp-easycart' ),
					'types' => $other,
				);
			}
			return $groups;
		}

		/**
		 * File endings never accepted from shoppers, whatever an option or a filter allows: anything a
		 * web server could run or a browser could render as a page.
		 *
		 * @since 6.0.0
		 *
		 * @return array Lower-case extensions without dots.
		 */
		public static function denied_extensions() {
			$base  = array_merge(
				self::server_script_extensions(),
				array( 'js', 'mjs', 'cjs', 'jsx', 'ts', 'vbs', 'vbe', 'wsf', 'wsh', 'ps1', 'psm1', 'exe', 'com', 'bat', 'cmd', 'msi', 'msp', 'dll', 'scr', 'cpl', 'hta', 'jar', 'app', 'apk', 'dmg', 'sh', 'bash', 'zsh', 'csh', 'ksh', 'run', 'bin', 'html', 'htm', 'xhtml', 'xht', 'svg', 'svgz', 'xml', 'xsl', 'xslt', 'swf', 'htaccess', 'htpasswd', 'ini', 'config', 'conf', 'user', 'inc', 'lnk', 'reg' )
			);
			$extra = apply_filters( 'wp_easycart_customer_upload_denied_extensions', array() );
			/* The filter can only add to the list. */
			return array_values( array_unique( array_merge( $base, array_map( 'strtolower', is_array( $extra ) ? $extra : array() ) ) ) );
		}

		/**
		 * Extensions a web server may hand to a script engine even in the middle of a name
		 * ( "photo.php.jpg" on Apache with AddHandler ).
		 *
		 * @since 6.0.0
		 *
		 * @return array
		 */
		private static function server_script_extensions() {
			return array( 'php', 'php2', 'php3', 'php4', 'php5', 'php6', 'php7', 'php8', 'phtml', 'pht', 'phar', 'phps', 'pl', 'py', 'pyc', 'rb', 'cgi', 'fcgi', 'asp', 'aspx', 'ascx', 'ashx', 'asmx', 'cer', 'asa', 'jsp', 'jspx', 'shtml', 'shtm', 'stm' );
		}

		/**
		 * Whether a file name ends in a denied extension, or carries a server script extension
		 * anywhere after its first dot.
		 *
		 * @since 6.0.0
		 *
		 * @param string $name File name.
		 * @return bool
		 */
		public static function has_denied_extension( $name ) {
			$parts = explode( '.', strtolower( trim( (string) $name ) ) );
			if ( count( $parts ) < 2 ) {
				return false;
			}
			$last = rtrim( (string) array_pop( $parts ), " \t." );
			if ( in_array( $last, self::denied_extensions(), true ) ) {
				return true;
			}
			array_shift( $parts );
			foreach ( $parts as $part ) {
				if ( in_array( trim( $part ), self::server_script_extensions(), true ) ) {
					return true;
				}
			}
			return false;
		}

		/**
		 * Lower-case text after the last dot of a file name.
		 *
		 * @since 6.0.0
		 *
		 * @param string $name File name.
		 * @return string '' when there is none.
		 */
		public static function file_extension( $name ) {
			$name = (string) $name;
			$dot  = strrpos( $name, '.' );
			return ( false === $dot ) ? '' : strtolower( substr( $name, $dot + 1 ) );
		}

		/**
		 * Clean a list of file type keys ( from the option editor or stored meta ): known types only,
		 * aliases mapped to their type ( "jpeg" → "jpg" ), never a denied extension, catalog order.
		 *
		 * @since 6.0.0
		 *
		 * @param array|string $items Type keys or extensions, as an array or a comma separated string.
		 * @return array
		 */
		public static function sanitize_extensions( $items ) {
			if ( is_string( $items ) ) {
				$items = explode( ',', $items );
			}
			if ( ! is_array( $items ) ) {
				return array();
			}
			$types  = self::file_types();
			$denied = self::denied_extensions();
			$wanted = array();
			foreach ( $items as $item ) {
				if ( ! is_scalar( $item ) ) {
					continue;
				}
				$item = strtolower( trim( (string) $item, " \t." ) );
				if ( '' === $item || in_array( $item, $denied, true ) ) {
					continue;
				}
				foreach ( $types as $key => $type ) {
					if ( $key === $item || in_array( $item, $type['extensions'], true ) ) {
						$wanted[ $key ] = true;
						break;
					}
				}
			}
			return array_values( array_intersect( array_keys( $types ), array_keys( $wanted ) ) );
		}

		/**
		 * Every file ending the chosen types cover ( "jpg" → jpg, jpeg, jpe ).
		 *
		 * @since 6.0.0
		 *
		 * @param array $extensions Type keys.
		 * @return array
		 */
		public static function expand_extensions( $extensions ) {
			$types = self::file_types();
			$out   = array();
			foreach ( self::sanitize_extensions( $extensions ) as $key ) {
				$out = array_merge( $out, $types[ $key ]['extensions'] );
			}
			return array_values( array_diff( array_unique( $out ), self::denied_extensions() ) );
		}

		/**
		 * The file types chosen on a file upload option ( option_meta['file_types'] ).
		 *
		 * @since 6.0.0
		 *
		 * @param object|array|int|null $option Option / option set row ( option_meta serialized or already an array ), its meta array, or an option_id.
		 * @return array Type keys; empty = the store default ( allowed_types() ).
		 */
		public static function option_extensions( $option ) {
			$meta = null;
			if ( is_object( $option ) ) {
				if ( property_exists( $option, 'option_meta' ) ) {
					$meta = $option->option_meta;
				} elseif ( isset( $option->option_id ) ) {
					$option = (int) $option->option_id;
				}
			} elseif ( is_array( $option ) ) {
				$meta = $option;
			}
			if ( null === $meta && is_numeric( $option ) && (int) $option > 0 && isset( $GLOBALS['wpdb'] ) ) {
				static $cache = array();

				$option_id = (int) $option;
				if ( ! isset( $cache[ $option_id ] ) ) {
					$cache[ $option_id ] = (string) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( 'SELECT option_meta FROM ec_option WHERE option_id = %d', $option_id ) );
				}
				$meta = $cache[ $option_id ];
			}
			$meta = maybe_unserialize( $meta );
			if ( ! is_array( $meta ) || empty( $meta['file_types'] ) ) {
				return array();
			}
			return self::sanitize_extensions( $meta['file_types'] );
		}

		/**
		 * Whether an uploaded file's contents fit one of the chosen types. The browser's MIME claim is
		 * ignored; fileinfo ( or WordPress's image check ) reads the file itself.
		 *
		 * @since 6.0.0
		 *
		 * @param string $path       Temporary upload path.
		 * @param string $name       Browser-supplied file name.
		 * @param array  $extensions Type keys.
		 * @return bool
		 */
		public static function content_matches( $path, $name, $extensions ) {
			$ext   = self::file_extension( $name );
			$types = self::file_types();
			$mimes = array();
			foreach ( self::sanitize_extensions( $extensions ) as $key ) {
				if ( in_array( $ext, $types[ $key ]['extensions'], true ) ) {
					$mimes = array_merge( $mimes, $types[ $key ]['mimes'] );
				}
			}
			if ( empty( $mimes ) ) {
				return false;
			}
			$real = self::real_mime( $path );
			if ( '' === $real ) {
				/* No way to read the contents on this server: the extension checks above still apply and the folder is never served by URL. */
				return true;
			}
			return in_array( $real, array_map( 'strtolower', $mimes ), true );
		}

		/**
		 * MIME type of a file's contents.
		 *
		 * @since 6.0.0
		 *
		 * @param string $path File path.
		 * @return string Lower case, '' when it cannot be detected.
		 */
		private static function real_mime( $path ) {
			$mime = '';
			if ( ! is_string( $path ) || '' === $path || ! is_file( $path ) ) {
				return '';
			}
			if ( function_exists( 'finfo_open' ) ) {
				$finfo = finfo_open( FILEINFO_MIME_TYPE );
				if ( $finfo ) {
					$mime = (string) finfo_file( $finfo, $path );
					finfo_close( $finfo );
				}
			} elseif ( function_exists( 'mime_content_type' ) ) {
				$mime = (string) mime_content_type( $path );
			}
			if ( ( '' === $mime || 'application/octet-stream' === $mime ) && function_exists( 'wp_get_image_mime' ) ) {
				$image = wp_get_image_mime( $path );
				if ( $image ) {
					$mime = (string) $image;
				}
			}
			return strtolower( trim( $mime ) );
		}

		/**
		 * The chosen types as shoppers read them: "JPG, PNG, PSD". Empty list = the store default.
		 *
		 * @since 6.0.0
		 *
		 * @param array $extensions Type keys.
		 * @return string
		 */
		public static function display_extensions( $extensions = array() ) {
			$list = ! empty( $extensions ) ? self::sanitize_extensions( $extensions ) : self::allowed_extensions( null, true );
			return strtoupper( implode( ', ', $list ) );
		}

		/**
		 * MIME types shoppers may upload ( filter: wpeasycart_allowed_file_upload_types ).
		 *
		 * @since 6.0.0 moved here from ec_cartpage so the product page can tell shoppers before they submit.
		 *
		 * @return array
		 */
		public static function allowed_types() {
			$types    = array( 'text/plain', 'image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'application/x-compressed', 'application/x-zip-compressed', 'application/zip', 'multipart/x-zip', 'application/x-bzip2', 'application/x-bzip', 'application/x-gzip', 'multipart/x-gzip' );
			$filtered = apply_filters( 'wpeasycart_allowed_file_upload_types', $types ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- existing public filter.
			return is_array( $filtered ) ? array_values( $filtered ) : $types;
		}

		/**
		 * Largest upload in bytes: the smaller of post_max_size and upload_max_filesize
		 * ( filter: wp_easycart_max_filesize_upload_limit ).
		 *
		 * @since 6.0.0
		 *
		 * @return int 0 when PHP reports no limit.
		 */
		public static function max_size() {
			$max  = 0;
			$post = self::parse_ini_size( ini_get( 'post_max_size' ) );
			if ( $post > 0 ) {
				$max = $post;
			}
			$upload = self::parse_ini_size( ini_get( 'upload_max_filesize' ) );
			if ( $upload > 0 && ( 0 === $max || $upload < $max ) ) {
				$max = $upload;
			}
			return (int) apply_filters( 'wp_easycart_max_filesize_upload_limit', $max );
		}

		/**
		 * Bytes from a php.ini size such as "8M".
		 *
		 * @param string $size php.ini value.
		 * @return int
		 */
		private static function parse_ini_size( $size ) {
			$unit   = preg_replace( '/[^bkmgtpezy]/i', '', (string) $size );
			$number = (float) preg_replace( '/[^0-9\.]/', '', (string) $size );
			if ( $unit ) {
				return (int) round( $number * pow( 1024, stripos( 'bkmgtpezy', $unit[0] ) ) );
			}
			return (int) round( $number );
		}

		/**
		 * File extensions that match the allowed MIME types, from WordPress's own MIME map.
		 *
		 * @since 6.0.0
		 *
		 * @param array|null $allowed_types Allowed MIME types; null = allowed_types().
		 * @param bool       $primary_only  Only the main extension of each type ( for messages: JPG, not JPG, JPEG, JPE ).
		 * @return array Lower-case extensions without dots; empty when any allowed type has no known
		 *               extension ( the browser check is then skipped so it never blocks a valid file ).
		 */
		public static function allowed_extensions( $allowed_types = null, $primary_only = false ) {
			$allowed_types = ( null === $allowed_types ) ? self::allowed_types() : (array) $allowed_types;
			if ( ! function_exists( 'wp_get_mime_types' ) ) {
				return array();
			}
			$map        = wp_get_mime_types();
			$extensions = array();
			foreach ( $allowed_types as $type ) {
				$found = false;
				foreach ( $map as $exts => $mime ) {
					if ( $mime === $type ) {
						$extensions = array_merge( $extensions, $primary_only ? array( strtok( $exts, '|' ) ) : explode( '|', $exts ) );
						$found      = true;
					}
				}
				/* Common archive aliases browsers send that WordPress maps under another name. */
				if ( ! $found && in_array( $type, array( 'application/x-compressed', 'application/x-zip-compressed', 'multipart/x-zip' ), true ) ) {
					$extensions[] = 'zip';
					$found        = true;
				} elseif ( ! $found && in_array( $type, array( 'application/x-bzip2', 'application/x-bzip' ), true ) ) {
					$extensions[] = 'bz2';
					$found        = true;
				} elseif ( ! $found && 'multipart/x-gzip' === $type ) {
					$extensions[] = 'gz';
					$found        = true;
				}
				if ( ! $found ) {
					return array();
				}
			}
			return array_values( array_unique( array_map( 'strtolower', $extensions ) ) );
		}

		/**
		 * Why an uploaded file cannot be stored, before anything is written.
		 *
		 * @since 6.0.0
		 *
		 * @since 6.0.0 $extensions: an option's own file types. The name's extension and the file's
		 *              contents are checked against them instead of the browser's MIME claim.
		 *
		 * @param array $file          One $_FILES entry.
		 * @param int   $max_filesize  Maximum size in bytes ( 0 = no limit ).
		 * @param array $allowed_types Allowed MIME types ( store default, used when $extensions is empty ).
		 * @param array $extensions    Type keys chosen on the option ( option_extensions() ).
		 * @return string '' ok | 'none' no file chosen | 'type' | 'size' | 'upload' ( PHP could not receive it ).
		 */
		public static function check( $file, $max_filesize, $allowed_types, $extensions = array() ) {
			if ( ! is_array( $file ) || ! isset( $file['name'] ) || '' === trim( (string) $file['name'] ) ) {
				return 'none';
			}
			$error = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_OK;
			if ( UPLOAD_ERR_NO_FILE === $error ) {
				return 'none';
			}
			if ( UPLOAD_ERR_INI_SIZE === $error || UPLOAD_ERR_FORM_SIZE === $error ) {
				return 'size';
			}
			if ( UPLOAD_ERR_OK !== $error || empty( $file['tmp_name'] ) || ! isset( $file['size'] ) ) {
				return 'upload';
			}
			if ( '' === self::stored_filename( $file['name'], $extensions ) ) {
				return 'type';
			}
			if ( ! empty( $extensions ) ) {
				if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
					return 'upload';
				}
				if ( ! self::content_matches( $file['tmp_name'], $file['name'], $extensions ) ) {
					return 'type';
				}
			} else {
				$type = isset( $file['type'] ) ? sanitize_text_field( $file['type'] ) : '';
				if ( ! in_array( $type, (array) $allowed_types, true ) ) {
					return 'type';
				}
			}
			if ( (int) $max_filesize > 0 && (int) $file['size'] > (int) $max_filesize ) {
				return 'size';
			}
			return '';
		}

		/**
		 * Validate one $_FILES entry and store it as <uploads root>/<folder>/<stored_filename()>.
		 *
		 * @param array  $file          One $_FILES entry.
		 * @param string $folder        Cart session id or a new_folder_name() value.
		 * @param int    $max_filesize  Maximum size in bytes.
		 * @param array  $allowed_types Allowed MIME types.
		 * @param array  $extensions    Type keys chosen on the option ( 6.0.0; empty = store default ).
		 * @return bool
		 */
		public static function store( $file, $folder, $max_filesize, $allowed_types, $extensions = array() ) {
			return '' === self::store_file( $file, $folder, $max_filesize, $allowed_types, $extensions );
		}

		/**
		 * store(), reporting why a file was not stored. Every failure other than 'none' fires
		 * wp_easycart_customer_upload_failed ( reason, file name, detail ) so it can be logged.
		 *
		 * @since 6.0.0
		 *
		 * @param array  $file          One $_FILES entry.
		 * @param string $folder        Cart session id or a new_folder_name() value.
		 * @param int    $max_filesize  Maximum size in bytes ( 0 = no limit ).
		 * @param array  $allowed_types Allowed MIME types.
		 * @param array  $extensions    Type keys chosen on the option ( 6.0.0; empty = store default ).
		 * @return string '' stored | 'none' | 'type' | 'size' | 'upload' | 'storage' ( the server could not save it ).
		 */
		public static function store_file( $file, $folder, $max_filesize, $allowed_types, $extensions = array() ) {
			$extensions = self::sanitize_extensions( $extensions );
			$reason     = self::check( $file, $max_filesize, $allowed_types, $extensions );
			if ( '' !== $reason ) {
				if ( 'none' !== $reason ) {
					$detail = isset( $file['type'] ) ? 'type ' . sanitize_text_field( $file['type'] ) : '';
					if ( ! empty( $extensions ) ) {
						$detail .= ( '' !== $detail ? ', ' : '' ) . 'option allows ' . implode( ',', $extensions );
						if ( 'type' === $reason && isset( $file['tmp_name'] ) && is_uploaded_file( $file['tmp_name'] ) ) {
							$detail .= ', contents ' . self::real_mime( $file['tmp_name'] );
						}
					}
					self::failed( $reason, $file, trim( $detail . ( isset( $file['error'] ) ? ', PHP upload error ' . (int) $file['error'] : '' ), ', ' ) );
				}
				return $reason;
			}
			$folder = (string) $folder;
			if ( ! self::is_valid_folder_name( $folder ) ) {
				return self::failed( 'storage', $file, 'invalid folder name' );
			}
			$filename = self::stored_filename( $file['name'], $extensions );

			$base = self::base_dir();
			if ( '' === $base ) {
				return self::failed( 'storage', $file, 'no uploads folder' );
			}
			self::ensure_protected( $base );

			$dir = $base . '/' . $folder;
			if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
				return self::failed( 'storage', $file, 'could not create ' . self::display_path( $dir ) );
			}

			if ( ! function_exists( 'wp_handle_upload' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}

			/* Point wp_handle_upload() at the private folder so nothing is written to wp-content/uploads. */
			$url    = self::base_url( $base ) . $folder;
			$filter = function ( $uploads ) use ( $dir, $url ) {
				$uploads['path']  = $dir;
				$uploads['url']   = $url;
				$uploads['error'] = false;
				return $uploads;
			};
			$overrides = array( 'test_form' => false );
			if ( ! empty( $extensions ) ) {
				/* check() already matched the extension and the contents against the option's types. WordPress's own test would refuse types it has no MIME entry for ( .ai, .eps ) or whose contents it reads differently ( .psd ). */
				$overrides['test_type'] = false;
			}
			add_filter( 'upload_dir', $filter, PHP_INT_MAX );
			$upload = wp_handle_upload( $file, $overrides );
			remove_filter( 'upload_dir', $filter, PHP_INT_MAX );

			if ( ! is_array( $upload ) || isset( $upload['error'] ) || empty( $upload['file'] ) ) {
				/* WordPress refuses files whose contents do not match the extension; tell the shopper it is the file, not the server. */
				$message = ( is_array( $upload ) && isset( $upload['error'] ) ) ? wp_strip_all_tags( (string) $upload['error'] ) : 'wp_handle_upload failed';
				return self::failed( ( false !== stripos( $message, 'type' ) ) ? 'type' : 'storage', $file, $message );
			}

			$stored = (string) $upload['file'];
			if ( wp_normalize_path( dirname( $stored ) ) !== wp_normalize_path( $dir ) ) {
				/* Another plugin moved the file somewhere else; do not leave that copy behind. */
				if ( is_file( $stored ) ) {
					wp_delete_file( $stored );
				}
				return self::failed( 'storage', $file, 'file moved outside the uploads folder by another plugin' );
			}

			$target = $dir . '/' . $filename;
			if ( $stored !== $target ) {
				/* wp_handle_upload() may have sanitized or de-duplicated the name; the order expects the recorded name. The same name uploaded again for this cart replaces the earlier file, as before 6.0.0. */
				if ( is_file( $target ) ) {
					wp_delete_file( $target );
				}
				if ( ! self::rename_path( $stored, $target ) ) {
					wp_delete_file( $stored );
					return self::failed( 'storage', $file, 'rename failed' );
				}
			}
			return '';
		}

		/**
		 * Report a failed upload ( action + store log ) and pass the reason through.
		 *
		 * @param string $reason Reason code.
		 * @param array  $file   One $_FILES entry.
		 * @param string $detail Technical detail for the log.
		 * @return string $reason
		 */
		private static function failed( $reason, $file, $detail ) {
			$name = ( is_array( $file ) && isset( $file['name'] ) ) ? sanitize_text_field( (string) $file['name'] ) : '';
			do_action( 'wp_easycart_customer_upload_failed', $reason, $name, $detail );
			if ( class_exists( 'ec_db' ) ) {
				$db = new ec_db();
				/* ec_db::insert_response only writes when the store's log setting is on. */
				$db->insert_response( 0, 1, 'Customer File Upload', sprintf( 'Upload of "%s" was not accepted ( %s ): %s', $name, $reason, $detail ) );
			}
			return $reason;
		}

		/**
		 * Shopper-facing message for a failed upload. Texts are in the language editor
		 * ( Product Details › File Upload … ).
		 *
		 * @since 6.0.0
		 *
		 * @param string $reason     'type' | 'size' | 'upload' | 'storage'.
		 * @param array  $extensions Type keys chosen on the option ( 6.0.0; empty = store default ).
		 * @return string Plain text.
		 */
		public static function error_message( $reason, $extensions = array() ) {
			$keys = array(
				'type'    => array( 'product_details_file_type_error', 'This file type cannot be uploaded. Accepted file types: %s' ),
				'size'    => array( 'product_details_file_size_error', 'This file is too large. The largest file you can upload is %s.' ),
				'upload'  => array( 'product_details_file_upload_error', 'Your file could not be uploaded. Please try again, or contact us if it keeps happening.' ),
			);
			$key = isset( $keys[ $reason ] ) ? $keys[ $reason ] : $keys['upload'];
			$text = function_exists( 'wp_easycart_language' ) ? trim( (string) wp_easycart_language()->get_text( 'product_details', $key[0] ) ) : '';
			if ( '' === $text ) {
				$text = $key[1];
			}
			if ( 'type' === $reason ) {
				$text = str_replace( '%s', self::display_extensions( $extensions ), $text );
				$text = rtrim( $text, ' :' );
			} elseif ( 'size' === $reason ) {
				$max  = self::max_size();
				$text = str_replace( '%s', ( $max > 0 && function_exists( 'size_format' ) ) ? size_format( $max ) : '', $text );
			}
			return html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		}

		/**
		 * Attributes for a storefront file input: accept list, size limit and the messages
		 * ec-store.js shows before the form is submitted.
		 *
		 * @since 6.0.0
		 * @since 6.0.0 $option: the option's own file types drive the accept list and the messages.
		 *
		 * @param object|array|int|null $option Option set row, its meta or option_id ( see option_extensions() ); null = store default.
		 * @return string Escaped attribute string with a leading space.
		 */
		public static function input_attributes( $option = null ) {
			$out    = '';
			$chosen = ( null === $option ) ? array() : self::option_extensions( $option );
			if ( ! empty( $chosen ) ) {
				$extensions = self::expand_extensions( $chosen );
			} else {
				$extensions = self::allowed_extensions();
			}
			if ( ! empty( $extensions ) ) {
				$out .= ' accept="' . esc_attr( '.' . implode( ',.', $extensions ) ) . '"';
				$out .= ' data-ec-file-ext="' . esc_attr( implode( ',', $extensions ) ) . '"';
			}
			$max = self::max_size();
			if ( $max > 0 ) {
				$out .= ' data-ec-max-size="' . esc_attr( $max ) . '"';
			}
			$out .= ' data-ec-error-file-type="' . esc_attr( self::error_message( 'type', $chosen ) ) . '"';
			$out .= ' data-ec-error-file-size="' . esc_attr( self::error_message( 'size' ) ) . '"';
			$out .= ' data-ec-error-file-upload="' . esc_attr( self::error_message( 'upload' ) ) . '"';
			return $out;
		}

		/**
		 * "Accepted file types: JPG, PNG, PSD" for the shopper, next to the file input
		 * ( language editor: Product Details › File Upload Accepted Types ).
		 *
		 * @since 6.0.0
		 *
		 * @param object|array|int|null $option Option set row, its meta or option_id; null = store default.
		 * @return string Escaped HTML, '' when there is no list to show.
		 */
		public static function accepted_types_html( $option = null ) {
			$chosen = ( null === $option ) ? array() : self::option_extensions( $option );
			$list   = self::display_extensions( $chosen );
			if ( '' === $list ) {
				return '';
			}
			$text = function_exists( 'wp_easycart_language' ) ? trim( (string) wp_easycart_language()->get_text( 'product_details', 'product_details_file_types_hint' ) ) : '';
			if ( '' === $text ) {
				$text = 'Accepted file types: %s';
			}
			$text = html_entity_decode( str_replace( '%s', $list, $text ), ENT_QUOTES, 'UTF-8' );
			return '<div class="ec_details_option_file_types" style="font-size:0.85em;opacity:0.8;margin-top:4px;">' . esc_html( $text ) . '</div>';
		}

		// Exposure check ( Diagnostics ).

		/**
		 * Request a probe file in each folder of an area over HTTP and report whether the web server served it.
		 *
		 * Status:
		 *  'protected' the server refused or did not serve the probe ( in every folder checked );
		 *  'exposed'   the probe's contents came back from a folder, so any file in it is downloadable by URL;
		 *  'unknown'   the site could not be reached over a loopback request ( or asked for a password );
		 *  'missing'   downloads only: no downloads folder exists yet, so there is nothing to expose.
		 *
		 * @param bool   $force Skip the cached result.
		 * @param string $area  'uploads' or 'downloads'.
		 * @return array { status, url, code, message, checked, folders: array of { path, status, url, code, message } }
		 */
		public static function check_public_access( $force = false, $area = 'uploads' ) {
			$area      = ( 'downloads' === $area ) ? 'downloads' : 'uploads';
			$transient = ( 'downloads' === $area ) ? self::TRANSIENT_DOWNLOADS_ACCESS : self::TRANSIENT_ACCESS;
			if ( ! $force ) {
				$cached = get_transient( $transient );
				if ( is_array( $cached ) && isset( $cached['status'] ) ) {
					return $cached;
				}
			}

			$result = array(
				'status'  => 'unknown',
				'url'     => '',
				'code'    => 0,
				'message' => '',
				'checked' => time(),
				'folders' => array(),
			);

			if ( 'downloads' === $area ) {
				$dirs = self::existing_dirs( 'downloads' );
				if ( empty( $dirs ) ) {
					$result['status']  = 'missing';
					$result['message'] = __( 'No downloads folder exists yet.', 'wp-easycart' );
					return self::cache_access( $result, $transient );
				}
			} else {
				$dir = self::base_dir();
				if ( '' === $dir ) {
					$result['message'] = __( 'The customer uploads folder does not exist yet.', 'wp-easycart' );
					return self::cache_access( $result, $transient );
				}
				$dirs = array( $dir );
			}

			/* The worst folder decides: exposed, then unknown, then protected. */
			$rank      = array(
				'protected' => 0,
				'unknown'   => 1,
				'exposed'   => 2,
			);
			$reachable = null;
			$picked    = null;
			foreach ( $dirs as $dir ) {
				$folder              = self::probe_folder( $dir, $area, $reachable );
				$result['folders'][] = $folder;
				if ( null === $picked || $rank[ $folder['status'] ] > $rank[ $picked['status'] ] ) {
					$picked = $folder;
				}
			}

			$result['status']  = $picked['status'];
			$result['url']     = $picked['url'];
			$result['code']    = $picked['code'];
			$result['message'] = $picked['message'];
			return self::cache_access( $result, $transient );
		}

		/**
		 * Loopback-request a probe file in one folder.
		 *
		 * @param string    $dir       Existing protected folder.
		 * @param string    $area      'uploads' or 'downloads'.
		 * @param bool|null $reachable In/out: whether the site can load its own public files ( null = not tested yet ).
		 * @return array { path, status, url, code, message }
		 */
		private static function probe_folder( $dir, $area, &$reachable ) {
			$result = array(
				'path'    => $dir,
				'status'  => 'unknown',
				'url'     => '',
				'code'    => 0,
				'message' => '',
			);

			$probe    = $dir . '/' . self::PROBE_FILE;
			$contents = self::PROBE_TOKEN . "\nWP EasyCart checks that private store files cannot be downloaded by URL. This file is safe to leave in place.\n";
			if ( ! is_file( $probe ) || self::read_file( $probe ) !== $contents ) {
				if ( ! wp_is_writable( $dir ) || ( is_file( $probe ) && ! wp_is_writable( $probe ) ) || ! self::write_file( $probe, $contents ) ) {
					if ( 'downloads' === $area ) {
						/* translators: %s: folder path relative to the WordPress install. */
						$result['message'] = sprintf( __( 'The downloads folder %s is not writable, so the check file could not be created.', 'wp-easycart' ), self::display_path( $dir ) );
					} else {
						$result['message'] = __( 'The customer uploads folder is not writable, so the check file could not be created.', 'wp-easycart' );
					}
					return $result;
				}
			}

			$base = self::base_url( $dir );
			if ( '' === $base ) {
				/* translators: %s: folder path relative to the WordPress install. */
				$result['message'] = sprintf( __( 'The public address of %s could not be worked out.', 'wp-easycart' ), self::display_path( $dir ) );
				return $result;
			}
			$result['url'] = $base . self::PROBE_FILE;
			$args          = array(
				'timeout'             => 10,
				'redirection'         => 3,
				'limit_response_size' => 4096,
				/* Same default core uses for loopback requests ( Site Health ). */
				'sslverify'           => apply_filters( 'https_local_ssl_verify', false ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter.
				'headers'             => array( 'Cache-Control' => 'no-cache' ),
			);
			/* GET rather than HEAD: the body must contain the probe token, so a "200" soft-404 or login page is not mistaken for exposure. */
			$response = wp_remote_get( add_query_arg( 'nocache', (string) time(), $result['url'] ), $args );

			if ( is_wp_error( $response ) ) {
				$result['message'] = $response->get_error_message();
				return $result;
			}

			$code           = (int) wp_remote_retrieve_response_code( $response );
			$result['code'] = $code;
			$body           = (string) wp_remote_retrieve_body( $response );

			if ( $code >= 200 && $code < 300 && false !== strpos( $body, self::PROBE_TOKEN ) ) {
				$result['status'] = 'exposed';
			} elseif ( 401 === $code || 407 === $code ) {
				$result['message'] = __( 'The site asked for a password, so the check could not tell whether files are reachable.', 'wp-easycart' );
			} elseif ( ( $code >= 200 && $code < 300 ) || in_array( $code, array( 403, 404, 410 ), true ) || $code >= 500 ) {
				/* Make sure the loopback reaches the site at all, otherwise a firewall blocking every loopback looks like protection. Tested once per check. */
				if ( null === $reachable ) {
					$control   = wp_remote_head( includes_url( 'js/jquery/jquery.min.js' ), $args );
					$reachable = ! is_wp_error( $control ) && (int) wp_remote_retrieve_response_code( $control ) >= 200 && (int) wp_remote_retrieve_response_code( $control ) < 400;
				}
				if ( $reachable ) {
					$result['status'] = 'protected';
				} else {
					$result['message'] = __( 'The site could not load its own public files over a loopback request.', 'wp-easycart' );
				}
			} else {
				/* translators: %d: HTTP status code. */
				$result['message'] = sprintf( __( 'Unexpected HTTP status %d.', 'wp-easycart' ), $code );
			}

			return $result;
		}

		/**
		 * A folder path relative to the WordPress install, for messages.
		 *
		 * @param string $dir Absolute path.
		 * @return string
		 */
		private static function display_path( $dir ) {
			$dir  = wp_normalize_path( (string) $dir );
			$root = wp_normalize_path( untrailingslashit( ABSPATH ) ) . '/';
			return ( 0 === strpos( $dir, $root ) ) ? substr( $dir, strlen( $root ) ) : wp_basename( $dir );
		}

		/**
		 * Cache a check result: a pass ( or nothing to check ) for 12 hours, anything else for 1 hour.
		 *
		 * @param array  $result    Result from check_public_access().
		 * @param string $transient Transient name.
		 * @return array
		 */
		private static function cache_access( $result, $transient = self::TRANSIENT_ACCESS ) {
			set_transient( $transient, $result, in_array( $result['status'], array( 'protected', 'missing' ), true ) ? 12 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
			return $result;
		}

		/**
		 * The nginx rule that blocks direct access to a protected area. nginx ignores .htaccess,
		 * so it has to go in the site's server block.
		 *
		 * @param string $area 'uploads' or 'downloads'.
		 * @return string
		 */
		public static function nginx_rule( $area = 'uploads' ) {
			if ( 'downloads' === $area ) {
				$dirs = self::existing_dirs( 'downloads' );
				if ( empty( $dirs ) ) {
					$dirs = array( EC_PLUGIN_DATA_DIRECTORY . '/products/downloads' );
				}
				$rules = array( '# WP EasyCart: paid download files are private ( place above any PHP location block ).' );
			} else {
				$dir   = self::base_dir();
				$dirs  = array( '' !== $dir ? $dir : EC_PLUGIN_DATA_DIRECTORY . '/products/uploads' );
				$rules = array( '# WP EasyCart: customer uploads are private ( place above any PHP location block ).' );
			}
			foreach ( $dirs as $dir ) {
				$url = self::base_url( $dir );
				if ( '' === $url ) {
					continue;
				}
				$path    = '/' . trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' ) . '/';
				$rules[] = 'location ^~ ' . $path . " {\n\tdeny all;\n}";
			}
			return implode( "\n", $rules );
		}
	}

	/* 6.0.0: uploads follow the cart when its session id changes ( login, registration or a restored cart at checkout ). */
	add_action( 'wpeasycart_session_rotated', array( 'wp_easycart_customer_uploads', 'on_session_rotated' ), 10, 2 );

endif;
