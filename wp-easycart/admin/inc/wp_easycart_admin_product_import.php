<?php
/**
 * WP EasyCart Admin — Products CSV import / export panel ( V2 ).
 *
 * Replaces the legacy "Import Products" bar ( Media Library upload + "Import File" ) on the products list with one
 * panel: choose a CSV → server-side preview ( header mapping against the importer's accepted columns, insert /
 * update counts by product_id + model_number, problems ) → chunked import with progress → results with errors;
 * plus the export links and the help / sample CSV.
 *
 * The rows are written by the existing chunked importer, wp_easycart_admin_products::run_importer(), so the column
 * handling lives in one place. This file only: stores the upload in a private folder under wp-content/uploads,
 * previews it ( the first rows only are kept in memory, the rest is streamed and counted ), and hands the path to
 * run_importer() one chunk at a time. The legacy ec_admin_ajax_import_products route ( attachment id + the
 * 'wp-easycart-start-import' nonce ) is untouched and still works.
 *
 * AJAX ( all POST, nonce 'wp-easycart-ecv2-product-import' in `nonce`, capability manage_options or wpec_products ):
 *   ecv2_product_import_upload   multipart `file` → { token, analysis }
 *   ecv2_product_import_run      token, offset, line → run_importer() reply { done, next, line, inserted, updated, errors }
 *   ecv2_product_import_discard  token → deletes the uploaded file
 *   ecv2_product_import_sample   → { csv, filename } sample file built from the exporter's header row
 *
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_product_import' ) ) :

	final class wp_easycart_admin_product_import {

		const NONCE        = 'wp-easycart-ecv2-product-import';
		const PREVIEW_ROWS = 20;
		/** Rows classified ( insert / update / problem ) before the preview only counts the rest. */
		const SCAN_LIMIT   = 100000;
		/** Rows per product_id / model_number lookup while previewing. */
		const LOOKUP_BATCH = 500;
		/** Seconds an upload stays available for the import to run. */
		const TTL          = 21600;
		const DIR          = 'wp-easycart-imports';
		const DOCS_URL     = 'https://docs.wpeasycart.com/docs/administrative-console-guide/product-importer/';

		/** Same rule as the importer and the export routes. */
		public static function can_manage() {
			return current_user_can( 'manage_options' ) || current_user_can( 'wpec_products' );
		}

		/** True on the products list ( the only screen that prints the panel ). */
		public static function is_products_list() {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only routing check on the admin page and subpage.
			if ( ! isset( $_GET['page'] ) || 'wp-easycart-products' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
				return false;
			}
			$subpage = isset( $_GET['subpage'] ) ? sanitize_key( wp_unslash( $_GET['subpage'] ) ) : 'products';
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			return 'products' === $subpage;
		}

		/* ------------------------------------------------------------------ assets + markup */

		public static function enqueue() {
			if ( ! self::is_products_list() || ! self::can_manage() ) {
				return;
			}
			wp_enqueue_style( 'wp_easycart_product_import_v2_css', plugins_url( 'wp-easycart/admin/css/product-import-v2.css', EC_PLUGIN_DIRECTORY ), array( 'wp_easycart_admin_v2_css' ), EC_CURRENT_VERSION );
			wp_enqueue_script( 'wp_easycart_product_import_v2_js', plugins_url( 'wp-easycart/admin/js/product-import-v2.js', EC_PLUGIN_DIRECTORY ), array( 'jquery' ), EC_CURRENT_VERSION, true );
			$max = (int) wp_max_upload_size();
			wp_localize_script( 'wp_easycart_product_import_v2_js', 'ecv2_product_import', array(
				'ajax_url'         => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( self::NONCE ),
				'export_all_url'   => self::export_all_url(),
				'docs_url'         => self::DOCS_URL,
				'max_upload'       => $max,
				'max_upload_label' => size_format( $max ),
				'preview_rows'     => self::PREVIEW_ROWS,
				'i18n'             => array(
					'uploading'        => __( 'Uploading… %d%%', 'wp-easycart' ),
					'checking'         => __( 'Checking the file…', 'wp-easycart' ),
					'too_large'        => __( 'That file is over the server upload limit of %s. Split it into smaller files.', 'wp-easycart' ),
					'not_csv'          => __( 'Choose a .csv file.', 'wp-easycart' ),
					'generic_error'    => __( 'Something went wrong. Please try again.', 'wp-easycart' ),
					'rows'             => __( '%d rows', 'wp-easycart' ),
					'columns_ok'       => __( '%d columns recognised', 'wp-easycart' ),
					'change_file'      => __( 'Change file', 'wp-easycart' ),
					'new_products'     => __( 'new products', 'wp-easycart' ),
					'updates'          => __( 'updates', 'wp-easycart' ),
					'problems'         => __( 'problem rows', 'wp-easycart' ),
					'ignored'          => __( 'ignored', 'wp-easycart' ),
					'fix_first'        => __( 'Fix these before importing', 'wp-easycart' ),
					'heads_up'         => __( 'Worth a look', 'wp-easycart' ),
					'columns_title'    => __( 'Columns', 'wp-easycart' ),
					'unknown_col'      => __( 'not a product field', 'wp-easycart' ),
					'did_you_mean'     => __( 'did you mean %s?', 'wp-easycart' ),
					'problem_rows'     => __( 'Rows that will be skipped', 'wp-easycart' ),
					'rows_label'       => __( 'rows', 'wp-easycart' ),
					'row'              => __( 'Row', 'wp-easycart' ),
					'sku'              => __( 'SKU', 'wp-easycart' ),
					'title'            => __( 'Title', 'wp-easycart' ),
					'price'            => __( 'Price', 'wp-easycart' ),
					'action'           => __( 'Action', 'wp-easycart' ),
					'insert'           => __( 'Add', 'wp-easycart' ),
					'update'           => __( 'Update #%s', 'wp-easycart' ),
					'skip'             => __( 'Skip', 'wp-easycart' ),
					'showing_first'    => __( 'Showing the first %1$d of %2$d rows.', 'wp-easycart' ),
					'scan_truncated'   => __( 'Counts cover the first %s rows; the rest were only counted.', 'wp-easycart' ),
					'cancel'           => __( 'Cancel', 'wp-easycart' ),
					'close'            => __( 'Close', 'wp-easycart' ),
					'back'             => __( 'Back', 'wp-easycart' ),
					'import_btn'       => __( 'Import %s', 'wp-easycart' ),
					'n_new'            => __( '%d new', 'wp-easycart' ),
					'n_updates'        => __( '%d updates', 'wp-easycart' ),
					'one_update'       => __( '1 update', 'wp-easycart' ),
					'nothing'          => __( 'Nothing to import', 'wp-easycart' ),
					'importing'        => __( 'Importing… keep this window open.', 'wp-easycart' ),
					'progress'         => __( '%1$d of %2$d rows', 'wp-easycart' ),
					'stopped_early'    => __( 'The import stopped early after %d rows: the server did not answer. Rows already imported are kept; upload the file again to continue.', 'wp-easycart' ),
					'added'            => __( 'added', 'wp-easycart' ),
					'updated'          => __( 'updated', 'wp-easycart' ),
					'errors'           => __( 'errors', 'wp-easycart' ),
					'errors_title'     => __( 'Rows that did not import', 'wp-easycart' ),
					'all_good'         => __( 'Every row imported. New products are published or private according to their activate_in_store value.', 'wp-easycart' ),
					'done_refresh'     => __( 'Done — refresh list', 'wp-easycart' ),
					'export_selected'  => __( 'Export %d selected', 'wp-easycart' ),
					'none_selected'    => __( 'Tick products in the list to export only those.', 'wp-easycart' ),
					'export_ready'     => __( 'Your export is downloading.', 'wp-easycart' ),
					'sample_name'      => __( 'easycart-products-sample.csv', 'wp-easycart' ),
				),
			) );
		}

		/** Prints the panel markup below the products list ( hook wp_easycart_admin_ecv2_render_modals ). */
		public static function render_modal( $table_id ) {
			if ( 'ec_admin_product_list_v2' !== $table_id || ! self::can_manage() ) {
				return;
			}
			$template = EC_PLUGIN_DIRECTORY . '/admin/template/products/products/product-import-v2.php';
			if ( file_exists( $template ) ) {
				include $template;
			}
		}

		/** Nonced link to the existing "export all" route ( process_export_products_csv() ). */
		public static function export_all_url() {
			return add_query_arg(
				array(
					'page'                 => 'wp-easycart-products',
					'subpage'              => 'products',
					'ec_admin_form_action' => 'export-all-products-csv',
					'wp_easycart_nonce'    => wp_create_nonce( 'wp-easycart-bulk-products' ),
				),
				admin_url( 'admin.php' )
			);
		}

		/* ------------------------------------------------------------------ columns */

		/**
		 * ec_product columns in table order: name => array( 'type', 'default' ). This is the exporter's header row
		 * ( ec_product.* ) and the importer's list of writable columns.
		 */
		public static function table_columns() {
			static $columns = null;
			if ( null !== $columns ) {
				return $columns;
			}
			global $wpdb;
			$columns = array();
			$rows = $wpdb->get_results( 'SHOW COLUMNS FROM ec_product', ARRAY_A );
			foreach ( (array) $rows as $row ) {
				if ( empty( $row['Field'] ) ) {
					continue;
				}
				$columns[ $row['Field'] ] = array(
					'type'    => isset( $row['Type'] ) ? strtolower( (string) $row['Type'] ) : '',
					'default' => array_key_exists( 'Default', $row ) ? $row['Default'] : null,
				);
			}
			return $columns;
		}

		/** Side-table columns the importer understands on top of the ec_product columns. */
		public static function side_columns() {
			return array( 'categories', 'price_tiers', 'b2b_prices', 'advanced_option_ids' );
		}

		/** The exporter's header row: every ec_product column, then the side-table columns ( same order as the CSV export ). */
		public static function exporter_headers() {
			return array_merge( array_keys( self::table_columns() ), self::side_columns() );
		}

		/** Accepted header name => kind ( key | column | side ). Matching is exact after trim, like the importer. */
		public static function header_map() {
			$map = array();
			foreach ( array_keys( self::table_columns() ) as $name ) {
				$map[ $name ] = in_array( $name, array( 'product_id', 'model_number' ), true ) ? 'key' : 'column';
			}
			foreach ( self::side_columns() as $name ) {
				$map[ $name ] = 'side';
			}
			return $map;
		}

		/** Closest accepted header for an unknown one ( case, spaces, dashes ) or ''. */
		private static function suggest_header( $header, $map ) {
			$loose = strtolower( preg_replace( '/[\s\-]+/', '_', trim( (string) $header ) ) );
			if ( '' === $loose ) {
				return '';
			}
			$alias = array( 'id' => 'product_id', 'sku' => 'model_number', 'model' => 'model_number', 'name' => 'title', 'product_name' => 'title', 'product_title' => 'title', 'sale_price' => 'price', 'regular_price' => 'list_price', 'quantity' => 'stock_quantity', 'stock' => 'stock_quantity', 'qty' => 'stock_quantity', 'active' => 'activate_in_store', 'status' => 'activate_in_store', 'category' => 'categories', 'category_ids' => 'categories', 'image' => 'image1', 'images' => 'product_images' );
			if ( isset( $alias[ $loose ] ) && isset( $map[ $alias[ $loose ] ] ) ) {
				return $alias[ $loose ];
			}
			foreach ( array_keys( $map ) as $name ) {
				if ( strtolower( $name ) === $loose ) {
					return $name;
				}
			}
			return '';
		}

		/* ------------------------------------------------------------------ upload storage */

		/** Private folder under wp-content/uploads; created on first use with an index.html and a deny-all .htaccess. */
		public static function upload_dir() {
			$uploads = wp_upload_dir();
			$dir = trailingslashit( $uploads['basedir'] ) . self::DIR;
			if ( ! is_dir( $dir ) ) {
				wp_mkdir_p( $dir );
			}
			if ( is_dir( $dir ) ) {
				if ( ! file_exists( $dir . '/index.html' ) ) {
					file_put_contents( $dir . '/index.html', '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- one-time marker file in the plugin's own upload folder.
				}
				if ( ! file_exists( $dir . '/.htaccess' ) ) {
					file_put_contents( $dir . '/.htaccess', "Order deny,allow\nDeny from all\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- one-time marker file in the plugin's own upload folder.
				}
			}
			return $dir;
		}

		/** Deletes uploads older than TTL ( a closed browser never sends the discard call ). */
		public static function cleanup_stale() {
			$dir = self::upload_dir();
			$files = glob( $dir . '/products-*.csv' );
			if ( ! is_array( $files ) ) {
				return;
			}
			$cutoff = time() - self::TTL;
			foreach ( $files as $file ) {
				if ( is_file( $file ) && filemtime( $file ) < $cutoff ) {
					wp_delete_file( $file );
				}
			}
		}

		/**
		 * Moves a PHP upload into the private folder and remembers it under a token for TTL seconds.
		 *
		 * @param array $file One $_FILES entry.
		 * @return array|WP_Error array( token, path, name, size )
		 */
		public static function store_upload( $file ) {
			$error = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
			if ( UPLOAD_ERR_INI_SIZE === $error || UPLOAD_ERR_FORM_SIZE === $error ) {
				/* translators: %s is the server upload limit, e.g. 8 MB. */
				return new WP_Error( 'too_large', sprintf( __( 'That file is over the server upload limit of %s. Split it into smaller files.', 'wp-easycart' ), size_format( wp_max_upload_size() ) ) );
			}
			if ( UPLOAD_ERR_OK !== $error ) {
				return new WP_Error( 'upload_failed', __( 'The upload did not complete. Please try again.', 'wp-easycart' ) );
			}
			$tmp = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
			if ( '' === $tmp || ! is_uploaded_file( $tmp ) ) {
				return new WP_Error( 'upload_failed', __( 'The upload did not complete. Please try again.', 'wp-easycart' ) );
			}
			$name = isset( $file['name'] ) ? sanitize_file_name( wp_unslash( (string) $file['name'] ) ) : '';
			$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
			if ( ! in_array( $ext, array( 'csv', 'txt' ), true ) ) {
				return new WP_Error( 'not_csv', __( 'Choose a .csv file.', 'wp-easycart' ) );
			}
			$size = (int) filesize( $tmp );
			if ( $size <= 0 ) {
				return new WP_Error( 'empty', __( 'The file is empty.', 'wp-easycart' ) );
			}
			if ( $size > (int) wp_max_upload_size() ) {
				/* translators: %s is the server upload limit, e.g. 8 MB. */
				return new WP_Error( 'too_large', sprintf( __( 'That file is over the server upload limit of %s. Split it into smaller files.', 'wp-easycart' ), size_format( wp_max_upload_size() ) ) );
			}
			$head = (string) file_get_contents( $tmp, false, null, 0, 4096 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local temp upload, first 4 KB only.
			if ( false !== strpos( $head, "\0" ) ) {
				return new WP_Error( 'binary', __( 'That is not a text CSV file. Save the sheet as CSV ( comma separated ) and try again.', 'wp-easycart' ) );
			}

			self::cleanup_stale();
			$dir = self::upload_dir();
			if ( ! is_dir( $dir ) || ! wp_is_writable( $dir ) ) {
				return new WP_Error( 'dir', __( 'The uploads folder is not writable, so the file could not be saved.', 'wp-easycart' ) );
			}
			$token = strtolower( wp_generate_password( 32, false, false ) );
			$path  = $dir . '/products-' . $token . '.csv';
			if ( ! move_uploaded_file( $tmp, $path ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_move_uploaded_file -- kept out of the Media Library on purpose ( private folder, deleted after the import ); wp_handle_upload() rejects CSVs whose detected mime is not text/csv.
				return new WP_Error( 'move', __( 'The file could not be saved. Please try again.', 'wp-easycart' ) );
			}
			set_transient( 'ecv2_product_import_' . $token, array( 'path' => $path, 'name' => $name, 'size' => $size, 'user' => get_current_user_id(), 'time' => time() ), self::TTL );
			return array( 'token' => $token, 'path' => $path, 'name' => $name, 'size' => $size );
		}

		/** The stored upload for a token, when it belongs to the current user and the file still exists. */
		public static function resolve_token( $token ) {
			$token = strtolower( (string) $token );
			if ( 32 !== strlen( $token ) || ! ctype_alnum( $token ) ) {
				return false;
			}
			$meta = get_transient( 'ecv2_product_import_' . $token );
			if ( ! is_array( $meta ) || empty( $meta['path'] ) || (int) $meta['user'] !== get_current_user_id() ) {
				return false;
			}
			$dir = self::upload_dir();
			if ( 0 !== strpos( (string) $meta['path'], $dir . '/' ) || ! is_file( $meta['path'] ) ) {
				return false;
			}
			$meta['token'] = $token;
			return $meta;
		}

		public static function discard( $token ) {
			$meta = self::resolve_token( $token );
			if ( ! $meta ) {
				return false;
			}
			wp_delete_file( $meta['path'] );
			delete_transient( 'ecv2_product_import_' . $meta['token'] );
			return true;
		}

		/* ------------------------------------------------------------------ preview */

		/** True for a row fgetcsv() returns on an empty line. */
		private static function is_blank_row( $row ) {
			return ! is_array( $row ) || ( 1 === count( $row ) && ( null === $row[0] || '' === trim( (string) $row[0] ) ) );
		}

		/**
		 * Streams the file once and reports what the importer will do with it. Only the first PREVIEW_ROWS rows and
		 * one LOOKUP_BATCH of rows are ever in memory. Mirrors run_importer():
		 *   - headers are matched exactly after trim ( BOM stripped ); an unknown header breaks every row's SQL, so it blocks
		 *   - product_id + model_number are required
		 *   - a row with product_id 0 / empty is inserted unless its model_number already exists ( in the store or earlier
		 *     in the file ); any other product_id must exist or the row is skipped
		 *   - the first blank line, or a row with an empty model_number, ends the import: everything after it is ignored
		 *
		 * @param string $path Absolute path.
		 * @param string $name Original file name ( for the summary ).
		 * @return array analysis, or array( 'error' => message )
		 */
		public static function analyze( $path, $name = '' ) {
			global $wpdb;
			if ( function_exists( 'set_time_limit' ) ) {
				@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- warns where disabled.
			}
			$fh = fopen( $path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- streamed read of the plugin's own upload.
			if ( ! $fh ) {
				return array( 'error' => __( 'The uploaded file could not be opened.', 'wp-easycart' ) );
			}
			$raw = fgetcsv( $fh );
			if ( self::is_blank_row( $raw ) ) {
				fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- streamed read.
				return array( 'error' => __( 'The file is empty — the first row must be the column names.', 'wp-easycart' ) );
			}

			$map      = self::header_map();
			$headers  = array();
			$columns  = array();
			$blocking = array();
			$warnings = array();
			$seen_h   = array();
			$idx      = array( 'product_id' => -1, 'model_number' => -1, 'title' => -1, 'price' => -1 );
			foreach ( $raw as $i => $h ) {
				$h = (string) $h;
				if ( 0 === $i && "\xEF\xBB\xBF" === substr( $h, 0, 3 ) ) {
					$h = substr( $h, 3 );
				}
				$h = trim( $h );
				$headers[] = $h;
				$entry = array( 'index' => $i, 'name' => $h, 'kind' => 'unknown', 'suggest' => '' );
				if ( '' === $h ) {
					$entry['kind'] = 'empty';
					/* translators: %d is the column position. */
					$blocking[] = sprintf( __( 'Column %d has no header. Remove the column or name it.', 'wp-easycart' ), $i + 1 );
				} elseif ( isset( $map[ $h ] ) ) {
					$entry['kind'] = $map[ $h ];
					if ( array_key_exists( $h, $idx ) ) {
						$idx[ $h ] = $i;
					}
					if ( isset( $seen_h[ $h ] ) ) {
						/* translators: %s is the column name. */
						$blocking[] = sprintf( __( 'The column “%s” appears more than once.', 'wp-easycart' ), $h );
					}
				} else {
					$entry['suggest'] = self::suggest_header( $h, $map );
					$blocking[] = '' !== $entry['suggest']
						/* translators: 1: the column name in the file, 2: the accepted column name. */
						? sprintf( __( '“%1$s” is not a product field — rename it to “%2$s”.', 'wp-easycart' ), $h, $entry['suggest'] )
						/* translators: %s is the column name. */
						: sprintf( __( '“%s” is not a product field — remove the column.', 'wp-easycart' ), $h );
				}
				$seen_h[ $h ] = true;
				$columns[] = $entry;
			}
			if ( -1 === $idx['product_id'] ) {
				$blocking[] = __( 'No “product_id” column. Use 0 to add a product, or the exported product_id to update one.', 'wp-easycart' );
			}
			if ( -1 === $idx['model_number'] ) {
				$blocking[] = __( 'No “model_number” column. Every product needs a unique SKU.', 'wp-easycart' );
			}
			$classify = ( -1 !== $idx['product_id'] && -1 !== $idx['model_number'] );
			$col_count = count( $headers );

			$totals   = array( 'insert' => 0, 'update' => 0, 'problem' => 0, 'ignored' => 0 );
			$problems = array();
			$sample   = array();
			$seen_models  = array();
			$strip_models = 0;
			$total     = 0;
			$scanned   = 0;
			$truncated = false;
			$stops_at  = 0;
			$stop_why  = '';
			$batch     = array();
			$started   = microtime( true );
			$strip_pattern = '/[' . preg_quote( "!@#$%^&*()+={}[]|\\'\";:,<.>/?`~*", '/' ) . ']/';

			$flush = function() use ( &$batch, &$totals, &$problems, &$sample, &$seen_models, $idx, $col_count, $wpdb ) {
				if ( empty( $batch ) ) {
					return;
				}
				$ids = array();
				$models = array();
				foreach ( $batch as $item ) {
					if ( $item['pid_int'] > 0 ) {
						$ids[ $item['pid_int'] ] = true;
					}
					$models[] = $item['model'];
				}
				$found_ids = array();
				if ( ! empty( $ids ) ) {
					$ids = array_keys( $ids );
					$rows = $wpdb->get_col( $wpdb->prepare( 'SELECT product_id FROM ec_product WHERE product_id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')', $ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholder list built with array_fill(); every id goes through prepare().
					foreach ( (array) $rows as $r ) {
						$found_ids[ (int) $r ] = true;
					}
				}
				$found_models = array();
				$rows = $wpdb->get_col( $wpdb->prepare( 'SELECT model_number FROM ec_product WHERE model_number IN (' . implode( ',', array_fill( 0, count( $models ), '%s' ) ) . ')', $models ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholder list built with array_fill(); every value goes through prepare().
				foreach ( (array) $rows as $r ) {
					$found_models[ (string) $r ] = true;
				}
				foreach ( $batch as $item ) {
					$action = '';
					$reason = '';
					$key    = '';
					if ( $item['cols'] !== $col_count ) {
						$action = 'problem';
						$key    = 'cols';
						/* translators: 1: cells in the row, 2: columns in the header row. */
						$reason = sprintf( __( 'has %1$d cells but the header has %2$d columns', 'wp-easycart' ), $item['cols'], $col_count );
					} elseif ( '' !== $item['pid_raw'] && '0' !== $item['pid_raw'] && ! is_numeric( $item['pid_raw'] ) ) {
						$action = 'problem';
						$key    = 'pid_nan';
						$reason = __( 'product_id is not a number', 'wp-easycart' );
					} elseif ( $item['pid_int'] > 0 ) {
						if ( isset( $found_ids[ $item['pid_int'] ] ) ) {
							$action = 'update';
						} else {
							$action = 'problem';
							$key    = 'pid_missing';
							$reason = __( 'product_id does not exist in the store ( use 0 to add a product )', 'wp-easycart' );
						}
					} else {
						if ( '' === trim( $item['model'] ) ) {
							$action = 'problem';
							$key    = 'model_blank';
							$reason = __( 'model_number is empty', 'wp-easycart' );
						} elseif ( isset( $seen_models[ $item['model'] ] ) ) {
							$action = 'problem';
							$key    = 'model_dup_file';
							$reason = __( 'model_number is used by an earlier row in this file', 'wp-easycart' );
						} elseif ( isset( $found_models[ $item['model'] ] ) ) {
							$action = 'problem';
							$key    = 'model_dup_store';
							$reason = __( 'model_number already exists in the store ( put its product_id in the row to update it )', 'wp-easycart' );
						} else {
							$action = 'insert';
						}
					}
					$seen_models[ $item['model'] ] = true;
					$totals[ $action ]++;
					if ( 'problem' === $action ) {
						if ( ! isset( $problems[ $key ] ) ) {
							$problems[ $key ] = array( 'reason' => $reason, 'count' => 0, 'rows' => array() );
						}
						$problems[ $key ]['count']++;
						if ( count( $problems[ $key ]['rows'] ) < 8 ) {
							$problems[ $key ]['rows'][] = $item['row'];
						}
					}
					if ( count( $sample ) < self::PREVIEW_ROWS ) {
						$sample[] = array(
							'row'        => $item['row'],
							'product_id' => $item['pid_raw'],
							'sku'        => $item['model'],
							'title'      => $item['title'],
							'price'      => $item['price'],
							'action'     => $action,
							'reason'     => $reason,
						);
					}
				}
				$batch = array();
			};

			$row_no = 0;
			while ( false !== ( $row = fgetcsv( $fh ) ) ) {
				$row_no++;
				$model = ( is_array( $row ) && $classify && isset( $row[ $idx['model_number'] ] ) ) ? (string) $row[ $idx['model_number'] ] : '';
				$blank = self::is_blank_row( $row );
				if ( $stops_at ) {
					if ( ! $blank ) {
						$totals['ignored']++;
					}
					continue;
				}
				if ( $blank || ( $classify && '' === trim( $model ) ) ) {
					$stops_at = $row_no;
					$stop_why = $blank ? 'blank' : 'model';
					continue;
				}
				$total++;
				if ( ! $classify ) {
					if ( count( $sample ) < self::PREVIEW_ROWS ) {
						$sample[] = array( 'row' => $row_no, 'product_id' => -1 !== $idx['product_id'] && isset( $row[ $idx['product_id'] ] ) ? (string) $row[ $idx['product_id'] ] : '', 'sku' => $model, 'title' => -1 !== $idx['title'] && isset( $row[ $idx['title'] ] ) ? (string) $row[ $idx['title'] ] : '', 'price' => -1 !== $idx['price'] && isset( $row[ $idx['price'] ] ) ? (string) $row[ $idx['price'] ] : '', 'action' => '', 'reason' => '' );
					}
					continue;
				}
				if ( $scanned >= self::SCAN_LIMIT || ( microtime( true ) - $started ) > 40 ) {
					$truncated = true;
					continue;
				}
				$scanned++;
				if ( preg_match( $strip_pattern, $model ) ) {
					$strip_models++;
				}
				$pid_raw = isset( $row[ $idx['product_id'] ] ) ? trim( (string) $row[ $idx['product_id'] ] ) : '';
				$batch[] = array(
					'row'     => $row_no,
					'cols'    => count( $row ),
					'pid_raw' => $pid_raw,
					'pid_int' => is_numeric( $pid_raw ) ? (int) $pid_raw : 0,
					'model'   => $model,
					'title'   => -1 !== $idx['title'] && isset( $row[ $idx['title'] ] ) ? (string) $row[ $idx['title'] ] : '',
					'price'   => -1 !== $idx['price'] && isset( $row[ $idx['price'] ] ) ? (string) $row[ $idx['price'] ] : '',
				);
				if ( count( $batch ) >= self::LOOKUP_BATCH ) {
					$flush();
				}
			}
			$flush();
			fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- streamed read.

			if ( $stops_at && 0 === $totals['ignored'] ) {
				$stops_at = 0; // trailing blank lines are harmless
			}
			if ( $stops_at ) {
				$warnings[] = 'blank' === $stop_why
					/* translators: 1: the row number, 2: how many rows come after it. */
					? sprintf( __( 'Row %1$d is blank. The importer stops at the first blank line, so the %2$d rows after it will be ignored — delete the blank line first.', 'wp-easycart' ), $stops_at, $totals['ignored'] )
					/* translators: 1: the row number, 2: how many rows come after it. */
					: sprintf( __( 'Row %1$d has an empty model_number. The importer stops there, so the %2$d rows after it will be ignored — give it a SKU or delete the row.', 'wp-easycart' ), $stops_at, $totals['ignored'] );
			}
			if ( $strip_models ) {
				/* translators: %d is a count of rows. */
				$warnings[] = sprintf( _n( '%d model_number contains punctuation that will be stripped on import ( only letters, numbers, dashes, underscores and slashes are kept ).', '%d model_numbers contain punctuation that will be stripped on import ( only letters, numbers, dashes, underscores and slashes are kept ).', $strip_models, 'wp-easycart' ), $strip_models );
			}
			if ( 0 === $total ) {
				$blocking[] = __( 'The file has no product rows under the header.', 'wp-easycart' );
			}

			$recognised = 0;
			foreach ( $columns as $c ) {
				if ( 'unknown' !== $c['kind'] && 'empty' !== $c['kind'] ) {
					$recognised++;
				}
			}

			return array(
				'file'       => array( 'name' => $name, 'rows' => $total, 'columns' => count( $columns ), 'recognised' => $recognised ),
				'columns'    => $columns,
				'blocking'   => $blocking,
				'warnings'   => $warnings,
				'totals'     => $totals,
				'problems'   => array_values( $problems ),
				'sample'     => $sample,
				'has_title'  => -1 !== $idx['title'],
				'has_price'  => -1 !== $idx['price'],
				'truncated'  => $truncated,
				'scanned'    => $scanned,
				'can_run'    => empty( $blocking ) && ( $totals['insert'] + $totals['update'] ) > 0,
			);
		}

		/* ------------------------------------------------------------------ sample file */

		/** The exporter's header row plus one importable example row ( defaults come from the table definition ). */
		public static function sample_csv() {
			$columns = self::table_columns();
			$headers = self::exporter_headers();
			$example = array(
				'product_id'        => '0',
				'model_number'      => 'SAMPLE-001',
				'title'             => 'Sample product',
				'description'       => 'Full description shown on the product page.',
				'short_description' => 'One line shown in product lists.',
				'price'             => '19.99',
				'list_price'        => '24.99',
				'activate_in_store' => '1',
				'stock_quantity'    => '25',
				'weight'            => '1.00',
				'post_id'           => '',
			);
			$row = array();
			foreach ( $headers as $name ) {
				if ( array_key_exists( $name, $example ) ) {
					$row[] = $example[ $name ];
				} elseif ( isset( $columns[ $name ] ) ) {
					$type    = $columns[ $name ]['type'];
					$default = $columns[ $name ]['default'];
					if ( null !== $default && 'current_timestamp()' !== strtolower( (string) $default ) && 'current_timestamp' !== strtolower( (string) $default ) ) {
						$row[] = (string) $default;
					} elseif ( preg_match( '/^(tiny|small|medium|big)?int|^(decimal|float|double|bit)/', $type ) ) {
						$row[] = '0';
					} elseif ( 0 === strpos( $type, 'datetime' ) || 0 === strpos( $type, 'timestamp' ) ) {
						$row[] = current_time( 'mysql' );
					} elseif ( 0 === strpos( $type, 'date' ) ) {
						$row[] = current_time( 'Y-m-d' );
					} else {
						$row[] = '';
					}
				} else {
					$row[] = '';
				}
			}
			$quote = function( $v ) {
				return '"' . str_replace( '"', '""', (string) $v ) . '"';
			};
			return implode( ',', array_map( $quote, $headers ) ) . "\n" . implode( ',', array_map( $quote, $row ) ) . "\n";
		}
	}

	add_action( 'admin_enqueue_scripts', array( 'wp_easycart_admin_product_import', 'enqueue' ) );
	add_action( 'wp_easycart_admin_ecv2_render_modals', array( 'wp_easycart_admin_product_import', 'render_modal' ) );

endif;

if ( ! function_exists( 'ecv2_product_import_guard' ) ) {
	/** Capability + nonce for every handler below; ends the request on failure. */
	function ecv2_product_import_guard() {
		if ( ! wp_easycart_admin_product_import::can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
		}
		check_ajax_referer( wp_easycart_admin_product_import::NONCE, 'nonce' );
	}
}

if ( ! function_exists( 'ecv2_product_import_token' ) ) {
	/** The posted token, or ends the request when it is missing / expired / someone else's. */
	function ecv2_product_import_token() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- every caller runs ecv2_product_import_guard() first.
		$token = isset( $_POST['token'] ) ? sanitize_key( wp_unslash( $_POST['token'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$meta = wp_easycart_admin_product_import::resolve_token( $token );
		if ( ! $meta ) {
			wp_send_json_error( array( 'message' => __( 'This upload has expired. Choose the file again.', 'wp-easycart' ), 'expired' => true ) );
		}
		return $meta;
	}
}

add_action( 'wp_ajax_ecv2_product_import_upload', function() {
	ecv2_product_import_guard();
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by ecv2_product_import_guard() above.
	$file = isset( $_FILES['file'] ) && is_array( $_FILES['file'] ) ? $_FILES['file'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- every member is validated in store_upload().
	// phpcs:enable WordPress.Security.NonceVerification.Missing
	if ( empty( $file ) ) {
		wp_send_json_error( array( 'message' => __( 'No file was received.', 'wp-easycart' ) ) );
	}
	$stored = wp_easycart_admin_product_import::store_upload( $file );
	if ( is_wp_error( $stored ) ) {
		wp_send_json_error( array( 'message' => $stored->get_error_message() ) );
	}
	$analysis = wp_easycart_admin_product_import::analyze( $stored['path'], $stored['name'] );
	if ( isset( $analysis['error'] ) ) {
		wp_easycart_admin_product_import::discard( $stored['token'] );
		wp_send_json_error( array( 'message' => $analysis['error'] ) );
	}
	wp_send_json_success( array( 'token' => $stored['token'], 'analysis' => $analysis ) );
} );

add_action( 'wp_ajax_ecv2_product_import_run', function() {
	ecv2_product_import_guard();
	$meta = ecv2_product_import_token();
	if ( ! function_exists( 'wp_easycart_admin_products' ) ) {
		wp_send_json_error( array( 'message' => __( 'The product importer is not loaded.', 'wp-easycart' ) ) );
	}
	if ( function_exists( 'ecv2_product_health_cache_clear' ) ) {
		ecv2_product_health_cache_clear();
	}
	/* Reads offset + line from the request, imports one chunk, replies JSON and exits. */
	wp_easycart_admin_products()->run_importer( $meta['path'] );
	wp_send_json_error( array( 'message' => __( 'The importer did not respond.', 'wp-easycart' ) ) );
} );

add_action( 'wp_ajax_ecv2_product_import_discard', function() {
	ecv2_product_import_guard();
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by ecv2_product_import_guard() above.
	$token = isset( $_POST['token'] ) ? sanitize_key( wp_unslash( $_POST['token'] ) ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Missing
	wp_easycart_admin_product_import::discard( $token );
	wp_send_json_success();
} );

add_action( 'wp_ajax_ecv2_product_import_sample', function() {
	ecv2_product_import_guard();
	wp_send_json_success( array( 'csv' => wp_easycart_admin_product_import::sample_csv(), 'filename' => 'easycart-products-sample.csv' ) );
} );
