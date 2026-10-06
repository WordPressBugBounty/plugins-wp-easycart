<?php
/**
 * WP EasyCart Admin — Products › Import ( 6.0.3 ).
 *
 * The page for wp_easycart_import: pick where the products are now ( WooCommerce on this site, Square, a CSV file ),
 * choose what comes across, try ten products, run the import with a progress bar ( the page drives it; WP-Cron finishes it
 * when the page is closed ), then a report with every note, a CSV of it, the old addresses as redirects and an undo.
 *
 * AJAX ecv2_import_* ( plain guard ecv2_import_guard(), nonce wp-easycart-ecv2-import ); admin-post wpec_import_report and
 * wpec_import_redirects ( CSV downloads ). Script admin/js/import-v2.js, styles admin/css/import-v2.css, markup
 * admin/template/import/import-v2.php.
 *
 * @package WP_EasyCart
 * @since   6.0.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_import' ) ) :

	/**
	 * Products › Import.
	 */
	final class wp_easycart_admin_import {

		/**
		 * Instance.
		 *
		 * @var wp_easycart_admin_import|null
		 */
		protected static $_instance = null; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore -- the admin classes' singleton name.

		/**
		 * The instance.
		 *
		 * @return wp_easycart_admin_import
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		/**
		 * Hooks.
		 */
		public function __construct() {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ), 15 );
			add_action( 'admin_post_wpec_import_report', array( $this, 'download_report' ) );
			add_action( 'admin_post_wpec_import_redirects', array( $this, 'download_redirects' ) );
			add_action( 'admin_init', array( $this, 'redirect_old_page' ) );
		}

		/**
		 * Settings › Cart importer ( the classic page's address, the 6.0.2 launch checklist's link ) opens Products › Import.
		 */
		public function redirect_old_page() {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only: which page was asked for.
			if ( wp_doing_ajax() || ! isset( $_GET['page'], $_GET['subpage'] ) || 'wp-easycart-settings' !== sanitize_key( wp_unslash( $_GET['page'] ) ) || 'cart-importer' !== sanitize_key( wp_unslash( $_GET['subpage'] ) ) ) {
				return;
			}
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			if ( self::can() ) {
				wp_safe_redirect( self::url() );
				exit;
			}
		}

		/**
		 * This is the Import page.
		 *
		 * @return bool
		 */
		public static function is_page() {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only page check.
			return isset( $_GET['page'], $_GET['subpage'] ) && 'wp-easycart-products' === sanitize_key( wp_unslash( $_GET['page'] ) ) && 'import' === sanitize_key( wp_unslash( $_GET['subpage'] ) );
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
		}

		/**
		 * The person may import.
		 *
		 * @return bool
		 */
		public static function can() {
			return current_user_can( 'manage_options' ) || current_user_can( 'wpec_products' ); // phpcs:ignore WordPress.WP.Capabilities.Unknown -- EasyCart's own roles register this capability.
		}

		/**
		 * The Import page's address.
		 *
		 * @param array $args Query arguments ( source … ).
		 * @return string
		 */
		public static function url( $args = array() ) {
			return add_query_arg( array_map( 'rawurlencode', (array) $args ), admin_url( 'admin.php?page=wp-easycart-products&subpage=import' ) );
		}

		/**
		 * Print the page.
		 */
		public function load_page() {
			include EC_PLUGIN_DIRECTORY . '/admin/template/import/import-v2.php';
		}

		/**
		 * Styles and the script, on the Import page only.
		 */
		public function enqueue() {
			if ( ! self::is_page() ) {
				return;
			}
			wp_enqueue_style( 'wp_easycart_admin_import_v2_css', plugins_url( 'wp-easycart/admin/css/import-v2.css', EC_PLUGIN_DIRECTORY ), array(), EC_CURRENT_VERSION );
			wp_enqueue_script( 'wp_easycart_admin_import_v2_js', plugins_url( 'wp-easycart/admin/js/import-v2.js', EC_PLUGIN_DIRECTORY ), array(), EC_CURRENT_VERSION, true );
			wp_localize_script( 'wp_easycart_admin_import_v2_js', 'wpecImport', $this->script_data() );
		}

		/**
		 * What the script starts with.
		 *
		 * @return array
		 */
		private function script_data() {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only: which source card to open.
			$source    = isset( $_GET['source'] ) ? sanitize_key( wp_unslash( $_GET['source'] ) ) : '';
			$connected = isset( $_GET['connected'] ) ? 1 : 0;
			$failed    = ( isset( $_GET['error'] ) && 'square-failed-to-connect' === sanitize_key( wp_unslash( $_GET['error'] ) ) ) ? 1 : 0;
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			$sources = array();
			foreach ( wp_easycart_import::sources() as $id => $item ) {
				$sources[] = array(
					'id'    => $id,
					'label' => $item->label(),
					'desc'  => $item->description(),
				);
			}
			return array(
				'ajax'      => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'wp-easycart-ecv2-import' ),
				'ready'     => wp_easycart_import::ready(),
				'sources'   => $sources,
				'open'      => $source,
				'connected' => $connected,
				'failed'    => $failed,
				'job'       => self::state( wp_easycart_import::job() ),
				'runs'      => self::runs_state(),
				'redirects' => wp_easycart_import::redirects_on(),
				'urls'      => array(
					'products'  => admin_url( 'admin.php?page=wp-easycart-products&subpage=products' ),
					'csv'       => admin_url( 'admin.php?page=wp-easycart-products&subpage=products&ecv2pi=import' ),
					'status'    => admin_url( 'admin.php?page=wp-easycart-settings&subpage=store-status' ),
					'payments'  => admin_url( 'admin.php?page=wp-easycart-settings&subpage=payment' ),
					'report'    => admin_url( 'admin-post.php?action=wpec_import_report' ),
					'redirects' => admin_url( 'admin-post.php?action=wpec_import_redirects' ),
				),
				'text'      => self::text(),
			);
		}

		/**
		 * The job as the script shows it.
		 *
		 * @param array|null $job Job.
		 * @return array|null
		 */
		public static function state( $job ) {
			if ( ! is_array( $job ) ) {
				return null;
			}
			$phases = array();
			foreach ( (array) $job['phases'] as $phase ) {
				$phases[] = array(
					'key'   => (string) $phase['key'],
					'label' => (string) $phase['label'],
					'done'  => (int) $phase['done'],
					'total' => (int) $phase['total'],
					'state' => (string) $phase['state'],
				);
			}
			return array(
				'id'       => (int) $job['id'],
				'source'   => (string) $job['source'],
				'label'    => (string) $job['label'],
				'status'   => (string) $job['status'],
				'trial'    => ! empty( $job['trial'] ),
				'phases'   => $phases,
				'counts'   => isset( $job['counts'] ) ? (array) $job['counts'] : array(),
				'settings' => isset( $job['settings'] ) ? (array) $job['settings'] : array(),
				'error'    => isset( $job['error'] ) ? (string) $job['error'] : '',
				'started'  => (int) $job['started'],
				'finished' => isset( $job['finished'] ) ? (int) $job['finished'] : 0,
				'when'     => self::when( (int) $job['started'] ),
				'busy'     => ! empty( $job['busy'] ),
			);
		}

		/**
		 * The history as the script shows it.
		 *
		 * @return array
		 */
		public static function runs_state() {
			$out = array();
			foreach ( wp_easycart_import::runs() as $run ) {
				$out[] = array(
					'id'     => (int) $run['id'],
					'source' => (string) $run['source'],
					'label'  => (string) $run['label'],
					'trial'  => ! empty( $run['trial'] ),
					'status' => (string) $run['status'],
					'counts' => (array) $run['counts'],
					'when'   => self::when( (int) $run['started'] ),
				);
			}
			return $out;
		}

		/**
		 * A time as the site shows dates.
		 *
		 * @param int $time Unix time.
		 * @return string
		 */
		private static function when( $time ) {
			if ( $time <= 0 ) {
				return '';
			}
			return function_exists( 'wp_date' ) ? wp_date( get_option( 'date_format', 'Y-m-d' ) . ' ' . get_option( 'time_format', 'H:i' ), $time ) : gmdate( 'Y-m-d H:i', $time );
		}

		/**
		 * Where a map row's item lives in the admin.
		 *
		 * @param object $row Map row.
		 * @return string
		 */
		public static function target_link( $row ) {
			$id = (int) $row->target_id;
			if ( $id <= 0 ) {
				return '';
			}
			switch ( $row->entity ) {
				case 'product':
					return admin_url( 'admin.php?page=wp-easycart-products&subpage=products&ec_admin_form_action=edit&product_id=' . $id . '&wp_easycart_nonce=' . wp_create_nonce( 'wp-easycart-action-edit' ) );
				case 'category':
				case 'tag':
					return admin_url( 'admin.php?page=wp-easycart-products&subpage=category&ec_admin_form_action=edit&category_id=' . $id );
				case 'brand':
					return admin_url( 'admin.php?page=wp-easycart-products&subpage=manufacturers&ec_admin_form_action=edit&manufacturer_id=' . $id );
				case 'option':
					return admin_url( 'admin.php?page=wp-easycart-products&subpage=option&ec_admin_form_action=edit&option_id=' . $id );
			}
			return '';
		}

		/**
		 * A map row's kind, in words.
		 *
		 * @param string $entity Entity.
		 * @return string
		 */
		public static function entity_label( $entity ) {
			$labels = array(
				'product'  => __( 'Product', 'wp-easycart' ),
				'category' => __( 'Category', 'wp-easycart' ),
				'tag'      => __( 'Tag', 'wp-easycart' ),
				'brand'    => __( 'Manufacturer', 'wp-easycart' ),
				'review'   => __( 'Review', 'wp-easycart' ),
				'option'   => __( 'Modifier list', 'wp-easycart' ),
			);
			return isset( $labels[ $entity ] ) ? $labels[ $entity ] : ucfirst( (string) $entity );
		}

		/**
		 * A result, in words.
		 *
		 * @param string $status Status.
		 * @return string
		 */
		public static function status_label( $status ) {
			$labels = array(
				'imported' => __( 'Imported', 'wp-easycart' ),
				'updated'  => __( 'Updated', 'wp-easycart' ),
				'skipped'  => __( 'Skipped', 'wp-easycart' ),
				'failed'   => __( 'Failed', 'wp-easycart' ),
			);
			return isset( $labels[ $status ] ) ? $labels[ $status ] : (string) $status;
		}

		/**
		 * The page's wording.
		 *
		 * @return array
		 */
		private static function text() {
			return array(
				'looking'       => __( 'Looking…', 'wp-easycart' ),
				'start'         => __( 'Start', 'wp-easycart' ),
				'nothing'       => __( 'Nothing to import', 'wp-easycart' ),
				'again'         => __( 'Check again', 'wp-easycart' ),
				'back'          => __( 'Back', 'wp-easycart' ),
				'choose'        => __( 'What should come across?', 'wp-easycart' ),
				'options'       => __( 'Options', 'wp-easycart' ),
				'checks'        => __( 'What we found', 'wp-easycart' ),
				'try'           => __( 'Try 10 products first', 'wp-easycart' ),
				'all'           => __( 'Import everything now', 'wp-easycart' ),
				'tryHint'       => __( 'Ten products are imported so you can open them and check. Remove them with one click if anything looks wrong.', 'wp-easycart' ),
				'steps'         => array(
					__( 'Choose', 'wp-easycart' ),
					__( 'Try 10', 'wp-easycart' ),
					__( 'Import', 'wp-easycart' ),
					__( 'Report', 'wp-easycart' ),
				),
				'running'       => __( 'Importing', 'wp-easycart' ),
				'trialRunning'  => __( 'Trying 10 products', 'wp-easycart' ),
				'closeOk'       => __( 'You can close this page: the import keeps going and picks up where it stopped.', 'wp-easycart' ),
				'quiet'         => __( 'Nothing is emailed, charged or taken from stock while products come in.', 'wp-easycart' ),
				'paused'        => __( 'Paused', 'wp-easycart' ),
				'pause'         => __( 'Pause', 'wp-easycart' ),
				'resume'        => __( 'Resume', 'wp-easycart' ),
				'stop'          => __( 'Stop', 'wp-easycart' ),
				'stopAsk'       => __( 'Stop this import? What came across so far stays, and you can remove it from the report.', 'wp-easycart' ),
				'waiting'       => __( 'Waiting', 'wp-easycart' ),
				'trialDone'     => __( 'Ten products, as they will look', 'wp-easycart' ),
				'trialHint'     => __( 'These are in your store now. Open a few to check them.', 'wp-easycart' ),
				'trialNothing'  => __( 'Nothing new to try: these products are in your store already. Import everything to pick up the rest, or choose to update products in the options.', 'wp-easycart' ),
				'looksGood'     => __( 'Looks good: import everything', 'wp-easycart' ),
				'removeTrial'   => __( 'Remove these and change the options', 'wp-easycart' ),
				'open'          => __( 'Open', 'wp-easycart' ),
				'reportTitle'   => __( 'Your import is done', 'wp-easycart' ),
				/* translators: %s: source name. */
				'reportFrom'    => __( 'From %s', 'wp-easycart' ),
				'imported'      => __( 'Imported', 'wp-easycart' ),
				'updated'       => __( 'Updated', 'wp-easycart' ),
				'skipped'       => __( 'Skipped', 'wp-easycart' ),
				'failed'        => __( 'Failed', 'wp-easycart' ),
				'notes'         => __( 'Notes', 'wp-easycart' ),
				'products'      => __( 'Products', 'wp-easycart' ),
				'categories'    => __( 'Categories', 'wp-easycart' ),
				'reviews'       => __( 'Reviews', 'wp-easycart' ),
				'notesTitle'    => __( 'Notes and problems', 'wp-easycart' ),
				'everything'    => __( 'Everything', 'wp-easycart' ),
				'noNotes'       => __( 'No notes: everything came across as it was.', 'wp-easycart' ),
				'more'          => __( 'Show more', 'wp-easycart' ),
				'download'      => __( 'Download the report ( CSV )', 'wp-easycart' ),
				'redirects'     => __( 'Old WooCommerce addresses go to the new pages', 'wp-easycart' ),
				'redirectsCsv'  => __( 'Download the redirects ( CSV )', 'wp-easycart' ),
				'redirectsHint' => __( 'For Redirection, Yoast or Rank Math, if you would rather keep the redirects there.', 'wp-easycart' ),
				'checklist'     => __( 'Before you switch over', 'wp-easycart' ),
				'checkWoo'      => array(
					__( 'Open a few products and check their prices, choices and pictures', 'wp-easycart' ),
					__( 'Connect your payment gateway and place a test order', 'wp-easycart' ),
					__( 'Switch WooCommerce off when you are ready: your products and their addresses keep working', 'wp-easycart' ),
				),
				'checkSquare'   => array(
					__( 'Open a few products and check their prices, choices and pictures', 'wp-easycart' ),
					__( 'Place a test order', 'wp-easycart' ),
					__( 'While Square stays connected, web sales are sent to Square, so the counts at your location stay right', 'wp-easycart' ),
				),
				'undo'          => __( 'Remove everything this import added', 'wp-easycart' ),
				'undoAsk'       => __( 'Remove every product, category and review this import added? Products that were already in your store, or that a later import updated, stay.', 'wp-easycart' ),
				'undoing'       => __( 'Removing…', 'wp-easycart' ),
				'undone'        => __( 'Removed.', 'wp-easycart' ),
				'importAgain'   => __( 'Import again', 'wp-easycart' ),
				'done'          => __( 'Done', 'wp-easycart' ),
				'history'       => __( 'Earlier imports', 'wp-easycart' ),
				'report'        => __( 'Report', 'wp-easycart' ),
				'remove'        => __( 'Remove', 'wp-easycart' ),
				'trial'         => __( 'Trial', 'wp-easycart' ),
				'status'        => array(
					'running'   => __( 'Running', 'wp-easycart' ),
					'paused'    => __( 'Paused', 'wp-easycart' ),
					'done'      => __( 'Done', 'wp-easycart' ),
					'cancelled' => __( 'Stopped', 'wp-easycart' ),
					'undone'    => __( 'Removed', 'wp-easycart' ),
					'failed'    => __( 'Failed', 'wp-easycart' ),
				),
				'where'         => __( 'Where are your products now?', 'wp-easycart' ),
				'csvTitle'      => __( 'A CSV file', 'wp-easycart' ),
				'csvDesc'       => __( 'A spreadsheet of products in the WP EasyCart format.', 'wp-easycart' ),
				'csvButton'     => __( 'Open the CSV importer', 'wp-easycart' ),
				'otherTitle'    => __( 'Shopify, Etsy, Wix or Squarespace', 'wp-easycart' ),
				'otherDesc'     => __( 'Importers for their export files are coming next. Until then, export a CSV and match its columns to the WP EasyCart format.', 'wp-easycart' ),
				'comingNext'    => __( 'Coming next', 'wp-easycart' ),
				'error'         => __( 'Something went wrong. Reload the page and try again.', 'wp-easycart' ),
				'update'        => __( 'Finish the WP EasyCart database update first ( Store Status ), then import.', 'wp-easycart' ),
				'connectedOk'   => __( 'Square is connected. Your payment settings did not change.', 'wp-easycart' ),
				'connectFailed' => __( 'Square did not finish connecting. Try again, and make sure you finish the authorisation on Square’s side.', 'wp-easycart' ),
				'item'          => __( 'Item', 'wp-easycart' ),
				'result'        => __( 'Result', 'wp-easycart' ),
				'note'          => __( 'Note', 'wp-easycart' ),
				'kind'          => __( 'Kind', 'wp-easycart' ),
				'saved'         => __( 'Saved', 'wp-easycart' ),
			);
		}

		// ---- Downloads ----.

		/**
		 * Action admin_post_wpec_import_report: a run's report as CSV.
		 */
		public function download_report() {
			ecv2_import_guard( false );
			$run_id = isset( $_GET['run'] ) ? (int) $_GET['run'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked in ecv2_import_guard().
			$rows   = array( array( __( 'Kind', 'wp-easycart' ), __( 'Source id', 'wp-easycart' ), __( 'Name', 'wp-easycart' ), __( 'Result', 'wp-easycart' ), __( 'Note', 'wp-easycart' ), __( 'Old address', 'wp-easycart' ), __( 'WP EasyCart id', 'wp-easycart' ) ) );
			foreach ( wp_easycart_import::results( $run_id, 'all', 0, 0 ) as $row ) {
				$rows[] = array( self::entity_label( $row->entity ), $row->source_id, $row->label, self::status_label( $row->status ), (string) $row->message, '' !== (string) $row->source_path ? '/' . $row->source_path . '/' : '', (int) $row->target_id ? (int) $row->target_id : '' );
			}
			self::send_csv( 'wp-easycart-import-' . $run_id . '.csv', $rows );
		}

		/**
		 * Action admin_post_wpec_import_redirects: every old address with its new page.
		 */
		public function download_redirects() {
			ecv2_import_guard( false );
			$rows = array( array( 'source', 'target' ) );
			foreach ( wp_easycart_import::redirect_rows() as $row ) {
				$rows[] = $row;
			}
			self::send_csv( 'wp-easycart-redirects.csv', $rows );
		}

		/**
		 * Send rows as a CSV download ( cells that start with = + - @ are quoted, so a spreadsheet never runs them ).
		 *
		 * @param string $name File name.
		 * @param array  $rows Rows.
		 */
		private static function send_csv( $name, $rows ) {
			nocache_headers();
			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $name ) . '"' );
			$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- streaming a download.
			fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- UTF-8 mark for spreadsheets.
			foreach ( $rows as $row ) {
				$cells = array();
				foreach ( (array) $row as $cell ) {
					$cell    = (string) $cell;
					$cells[] = preg_match( '/^[=+\-@]/', $cell ) ? "'" . $cell : $cell;
				}
				fputcsv( $out, $cells, ',', '"', '\\' );
			}
			fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- streaming a download.
			exit;
		}
	}

endif;

if ( ! function_exists( 'ecv2_import_guard' ) ) {
	/**
	 * The Import page's requests: the nonce ( field nonce ) and a person who may import. Answers JSON for AJAX, a plain
	 * message for the downloads.
	 *
	 * @since 6.0.3
	 * @param bool $ajax An AJAX request.
	 */
	function ecv2_import_guard( $ajax = true ) {
		$ok = ( false !== check_ajax_referer( 'wp-easycart-ecv2-import', 'nonce', false ) ) && wp_easycart_admin_import::can();
		if ( $ok ) {
			return;
		}
		if ( $ajax ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Reload the page and try again.', 'wp-easycart' ) ), 403 );
		}
		wp_die( esc_html__( 'Your session has expired. Reload the page and try again.', 'wp-easycart' ), '', array( 'response' => 403 ) );
	}
}

if ( ! function_exists( 'ecv2_import_detect' ) ) {
	/**
	 * AJAX ecv2_import_detect: what a source holds, what can come across, the choices.
	 *
	 * @since 6.0.3
	 */
	function ecv2_import_detect() {
		ecv2_import_guard();
		$source = wp_easycart_import::source( isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : '' );
		if ( ! $source ) {
			wp_send_json_error( array( 'message' => __( 'That import source is not available.', 'wp-easycart' ) ) );
		}
		$detect   = $source->detect( ! empty( $_POST['fresh'] ) );
		$entities = array();
		foreach ( $source->entities( $detect ) as $key => $entity ) {
			$entities[] = array_merge(
				array(
					'key'       => $key,
					'count'     => 0,
					'available' => false,
					'default'   => false,
					'note'      => '',
					'desc'      => '',
					'requires'  => array(),
				),
				$entity
			);
		}
		$fields = array();
		foreach ( $source->fields( $detect ) as $key => $field ) {
			$fields[] = array_merge(
				array(
					'key'     => $key,
					'desc'    => '',
					'options' => array(),
				),
				$field
			);
		}
		$checks = array();
		foreach ( isset( $detect['checks'] ) ? (array) $detect['checks'] : array() as $check ) {
			$checks[] = array(
				'level' => (string) $check[0],
				'title' => (string) $check[1],
				'text'  => isset( $check[2] ) ? (string) $check[2] : '',
			);
		}
		wp_send_json_success(
			array(
				'source'    => $source->id(),
				'label'     => $source->label(),
				'available' => ! empty( $detect['available'] ),
				'connected' => ! empty( $detect['connected'] ),
				'status'    => isset( $detect['status'] ) ? (string) $detect['status'] : '',
				'counts'    => isset( $detect['counts'] ) ? (array) $detect['counts'] : array(),
				'checks'    => $checks,
				'action'    => isset( $detect['action'] ) ? (array) $detect['action'] : null,
				'entities'  => $entities,
				'fields'    => $fields,
			)
		);
	}
}
add_action( 'wp_ajax_ecv2_import_detect', 'ecv2_import_detect' );

if ( ! function_exists( 'ecv2_import_start' ) ) {
	/**
	 * AJAX ecv2_import_start: start an import ( or a trial ) and work on it for a moment.
	 *
	 * @since 6.0.3
	 */
	function ecv2_import_start() {
		ecv2_import_guard();
		$choices = array(
			'entities' => isset( $_POST['entities'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['entities'] ) ) : array(),
			'fields'   => array(),
		);
		if ( isset( $_POST['fields'] ) && is_array( $_POST['fields'] ) ) {
			foreach ( wp_unslash( $_POST['fields'] ) as $key => $value ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each value is sanitized below.
				$choices['fields'][ sanitize_key( $key ) ] = sanitize_key( (string) $value );
			}
		}
		$job = wp_easycart_import::start( isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : '', $choices, ! empty( $_POST['trial'] ) );
		if ( is_wp_error( $job ) ) {
			wp_send_json_error( array( 'message' => $job->get_error_message() ) );
		}
		$job = wp_easycart_import::step( 4 );
		wp_send_json_success( array( 'job' => wp_easycart_admin_import::state( $job ) ) );
	}
}
add_action( 'wp_ajax_ecv2_import_start', 'ecv2_import_start' );

if ( ! function_exists( 'ecv2_import_step' ) ) {
	/**
	 * AJAX ecv2_import_step: work on the running import for a few seconds.
	 *
	 * @since 6.0.3
	 */
	function ecv2_import_step() {
		ecv2_import_guard();
		$job = wp_easycart_import::step();
		wp_send_json_success(
			array(
				'job'  => wp_easycart_admin_import::state( $job ),
				'runs' => ( $job && ! wp_easycart_import::is_open( $job ) ) ? wp_easycart_admin_import::runs_state() : null,
			)
		);
	}
}
add_action( 'wp_ajax_ecv2_import_step', 'ecv2_import_step' );

if ( ! function_exists( 'ecv2_import_control' ) ) {
	/**
	 * AJAX ecv2_import_control: pause, resume, cancel or dismiss the import.
	 *
	 * @since 6.0.3
	 */
	function ecv2_import_control() {
		ecv2_import_guard();
		$action = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		$job    = wp_easycart_import::control( $action );
		wp_send_json_success(
			array(
				'job'  => wp_easycart_admin_import::state( $job ),
				'runs' => wp_easycart_admin_import::runs_state(),
			)
		);
	}
}
add_action( 'wp_ajax_ecv2_import_control', 'ecv2_import_control' );

if ( ! function_exists( 'ecv2_import_results' ) ) {
	/**
	 * AJAX ecv2_import_results: a page of a run's report.
	 *
	 * @since 6.0.3
	 */
	function ecv2_import_results() {
		ecv2_import_guard();
		$run_id = isset( $_POST['run'] ) ? (int) $_POST['run'] : 0;
		$filter = ( isset( $_POST['filter'] ) && 'notes' === sanitize_key( wp_unslash( $_POST['filter'] ) ) ) ? 'notes' : 'all';
		$offset = isset( $_POST['offset'] ) ? max( 0, (int) $_POST['offset'] ) : 0;
		$rows   = array();
		foreach ( wp_easycart_import::results( $run_id, $filter, $offset, 51 ) as $row ) {
			$rows[] = array(
				'entity' => (string) $row->entity,
				'kind'   => wp_easycart_admin_import::entity_label( $row->entity ),
				'label'  => (string) $row->label,
				'status' => (string) $row->status,
				'result' => wp_easycart_admin_import::status_label( $row->status ),
				'note'   => (string) $row->message,
				'link'   => wp_easycart_admin_import::target_link( $row ),
			);
		}
		$more = count( $rows ) > 50;
		wp_send_json_success(
			array(
				'rows'   => array_slice( $rows, 0, 50 ),
				'more'   => $more,
				'counts' => wp_easycart_import::result_counts( $run_id ),
				'run'    => wp_easycart_import::run( $run_id ),
			)
		);
	}
}
add_action( 'wp_ajax_ecv2_import_results', 'ecv2_import_results' );

if ( ! function_exists( 'ecv2_import_undo' ) ) {
	/**
	 * AJAX ecv2_import_undo: remove what a run added, a slice at a time ( the page asks again until done ).
	 *
	 * @since 6.0.3
	 */
	function ecv2_import_undo() {
		ecv2_import_guard();
		$result         = wp_easycart_import::undo( isset( $_POST['run'] ) ? (int) $_POST['run'] : 0, 8 );
		$result['runs'] = wp_easycart_admin_import::runs_state();
		$result['job']  = wp_easycart_admin_import::state( wp_easycart_import::job() );
		wp_send_json_success( $result );
	}
}
add_action( 'wp_ajax_ecv2_import_undo', 'ecv2_import_undo' );

if ( ! function_exists( 'ecv2_import_redirects' ) ) {
	/**
	 * AJAX ecv2_import_redirects: old addresses redirect, or not.
	 *
	 * @since 6.0.3
	 */
	function ecv2_import_redirects() {
		ecv2_import_guard();
		update_option( wp_easycart_import::REDIRECTS_OPTION, empty( $_POST['on'] ) ? '0' : '1', false );
		wp_send_json_success( array( 'on' => wp_easycart_import::redirects_on() ) );
	}
}
add_action( 'wp_ajax_ecv2_import_redirects', 'ecv2_import_redirects' );

wp_easycart_admin_import::instance();
