<?php
/**
 * WP EasyCart Admin — Subscribers ( V2 ). Newsletter list ( ec_subscriber: email, first, last ).
 *
 * List with a customer-account link when the email matches ec_user, spreadsheet inline edit, Add drawer, CSV
 * import with dedupe + preview, CSV export ( all / filtered / selected ), bulk delete with a 15-minute undo.
 * ( ec_subscriber.email carries a unique key, so duplicates can't exist — the import dedupes before insert. )
 * No details page; legacy edit URLs open the list with the row highlighted.
 *
 * AJAX: ecv2_subscriber_add, ecv2_subscriber_inline_update, ecv2_subscriber_bulk, ecv2_subscriber_restore,
 *       ecv2_subscriber_import_preview, ecv2_subscriber_import, ecv2_subscriber_export ( GET ).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

include_once( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_table_v2.php' );

if ( ! class_exists( 'wp_easycart_admin_subscriber_table' ) ) :

	class wp_easycart_admin_subscriber_table extends wp_easycart_admin_table_v2 {

		const NONCE = 'wp-easycart-subv2';

		public function __construct() { parent::__construct(); $this->setup(); }

		public function setup() {
			global $wpdb;
			$this->set_table( 'ec_subscriber', 'subscriber_id' );
			$this->set_table_id( 'ec_admin_subscriber_list_v2' );
			$this->set_default_sort( 'subscriber_id', 'DESC' );
			$this->set_header( __( 'Subscribers', 'wp-easycart' ) );
			$this->set_docs_link( 'users', 'subscribers' );
			$this->set_add_new( true, 'add-new', __( 'Add subscriber', 'wp-easycart' ) );
			$this->set_add_new_js( 'ecsub.add( null, this ); return false;' );
			$this->set_add_new_css( 'ecv2-btn ecv2-btn-primary' );
			$this->set_label( __( 'Subscriber', 'wp-easycart' ), __( 'Subscribers', 'wp-easycart' ) );
			$this->set_view_modes( array( 'table', 'spreadsheet' ) );
			$this->set_list_columns( array(
				array( 'name' => 'email', 'label' => __( 'Email', 'wp-easycart' ), 'format' => 'sub_email', 'linked' => true ),
				array( 'select' => "TRIM( CONCAT( ec_subscriber.first_name, ' ', ec_subscriber.last_name ) ) AS full_name", 'name' => 'full_name', 'label' => __( 'Name', 'wp-easycart' ), 'format' => 'sub_name', 'orderby' => 'ec_subscriber.last_name' ),
				array( 'select' => '( SELECT u.user_id FROM ec_user u WHERE u.email = ec_subscriber.email LIMIT 1 ) AS customer_id', 'name' => 'customer_id', 'label' => __( 'Account', 'wp-easycart' ), 'format' => 'sub_account', 'tablet_hide' => true ),
				array( 'name' => 'subscriber_id', 'label' => __( 'ID', 'wp-easycart' ), 'format' => 'int', 'is_id' => true, 'laptop_hide' => true ),
				array( 'name' => 'first_name', 'format' => 'hidden', 'label' => '' ),
				array( 'name' => 'last_name', 'format' => 'hidden', 'label' => '' ),
			) );
			$this->set_spreadsheet_columns( array(
				array( 'name' => 'email', 'label' => __( 'Email', 'wp-easycart' ), 'format' => 'string', 'ss_editable' => true ),
				array( 'name' => 'first_name', 'label' => __( 'First name', 'wp-easycart' ), 'format' => 'string', 'ss_editable' => true ),
				array( 'name' => 'last_name', 'label' => __( 'Last name', 'wp-easycart' ), 'format' => 'string', 'ss_editable' => true ),
				array( 'name' => 'subscriber_id', 'label' => __( 'ID', 'wp-easycart' ), 'format' => 'int', 'is_id' => true ),
			) );
			$this->set_search_columns( array( 'ec_subscriber.email', 'ec_subscriber.first_name', 'ec_subscriber.last_name' ) );
			$this->set_bulk_actions( array( array( 'name' => 'ecv2-subscriber-export', 'label' => __( 'Export selected', 'wp-easycart' ) ), array( 'name' => 'ecv2-subscriber-delete', 'label' => __( 'Delete', 'wp-easycart' ) ) ) );
			$this->set_row_menu_actions( array(
				array( 'label' => __( 'Edit', 'wp-easycart' ), 'name' => 'edit', 'icon' => 'edit', 'href' => '#', 'onclick' => 'return ecsub.edit( this );' ),
				array( 'label' => __( 'View account', 'wp-easycart' ), 'name' => 'account', 'icon' => 'admin-users', 'href' => '#', 'onclick' => 'return ecsub.account( this );' ),
				array( 'label' => __( 'Delete', 'wp-easycart' ), 'name' => 'delete', 'icon' => 'trash', 'href' => '#', 'onclick' => 'return ecsub.delete_row( this );', 'danger' => true ),
			) );
			$this->set_filters( array(
				array( 'data' => array( (object) array( 'value' => 'customers', 'label' => __( 'Has account', 'wp-easycart' ), 'icon' => 'admin-users' ), (object) array( 'value' => 'guests', 'label' => __( 'No account', 'wp-easycart' ), 'icon' => 'email' ) ), 'label' => __( 'Show', 'wp-easycart' ), 'type' => 'pills', 'where_callback' => true ),
			) );
			$total = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_subscriber' );
			$customers = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_subscriber s WHERE EXISTS ( SELECT 1 FROM ec_user u WHERE u.email = s.email )' );
			$invalid = self::invalid_count();
			$this->set_health_stats( array(
				array( 'label' => __( 'Subscribers', 'wp-easycart' ), 'value' => $total, 'filter_value' => '', 'color' => 'default', 'group' => 'catalog' ),
				array( 'label' => __( 'Are customers', 'wp-easycart' ), 'value' => $customers, 'filter_value' => 'customers', 'color' => 'green', 'group' => 'catalog' ),
				array( 'label' => __( 'Email only', 'wp-easycart' ), 'value' => $total - $customers, 'filter_value' => 'guests', 'color' => 'gray', 'group' => 'catalog' ),
				array( 'label' => __( 'Invalid addresses', 'wp-easycart' ), 'value' => $invalid, 'filter_value' => '', 'color' => $invalid ? 'red' : 'gray', 'group' => 'attention' ),
			) );
		}

		protected function get_health_filter_where( $k ) {
			switch ( $k ) {
				case 'customers': return 'EXISTS ( SELECT 1 FROM ec_user u WHERE u.email = ec_subscriber.email )';
				case 'guests': return 'NOT EXISTS ( SELECT 1 FROM ec_user u WHERE u.email = ec_subscriber.email )';
			}
			return '';
		}
		protected function get_filter_callback_where( $i, $v ) { return 0 === $i ? $this->get_health_filter_where( $v ) : ''; }

		/** Toolbar extras: import / export / merge. */
		protected function print_toolbar() {
			$export = wp_nonce_url( add_query_arg( array( 'action' => 'ecv2_subscriber_export' ) + $this->filter_args(), admin_url( 'admin-ajax.php' ) ), self::NONCE, 'nonce' );
			echo '<div class="ecsub-bar" data-nonce="' . esc_attr( wp_create_nonce( self::NONCE ) ) . '" data-export="' . esc_url( $export ) . '"><button type="button" class="ecv2-btn ecv2-btn-sm" onclick="ecsub.import_open();">' . esc_html__( 'Import CSV', 'wp-easycart' ) . '</button><a class="ecv2-btn ecv2-btn-sm" href="' . esc_url( $export ) . '">' . esc_html__( 'Export CSV', 'wp-easycart' ) . '</a></div>';
			parent::print_toolbar();
		}
		private function filter_args() { $o = array(); foreach ( array( 's', 'filter_0', 'health_filter' ) as $k ) { if ( isset( $_GET[ $k ] ) && '' !== $_GET[ $k ] ) { $o[ $k ] = sanitize_text_field( wp_unslash( $_GET[ $k ] ) ); } } return $o; }

		protected function print_table_row( $result ) {
			$hl = isset( $_GET['subscriber_id'] ) && (int) $_GET['subscriber_id'] === (int) $result->subscriber_id;
			echo '<tr class="ecv2-row' . ( $hl ? ' is-highlight' : '' ) . '" data-id="' . esc_attr( $result->subscriber_id ) . '" data-email="' . esc_attr( $result->email ) . '" data-first="' . esc_attr( wp_unslash( $result->first_name ) ) . '" data-last="' . esc_attr( wp_unslash( $result->last_name ) ) . '" data-customer-id="' . (int) $result->customer_id . '">';
			echo '<td class="ecv2-col-check"><input type="checkbox" name="bulk[]" value="' . esc_attr( $result->subscriber_id ) . '" class="ecv2-row-check" /></td>';
			foreach ( $this->list_columns as $col ) {
				if ( 'hidden' === $col['format'] ) { continue; }
				echo '<td class="ecv2-cell ecv2-cell-' . esc_attr( $col['name'] ) . ( ! empty( $col['tablet_hide'] ) ? ' ecv2-hide-tablet' : '' ) . ( ! empty( $col['laptop_hide'] ) ? ' ecv2-hide-laptop' : '' ) . '">'; $this->print_cell_content( $result, $col ); echo '</td>';
			}
			echo '<td class="ecv2-col-actions">'; $this->print_row_actions( $result ); echo '</td></tr>';
		}

		/** Row menu per subscriber: "View account" only when an account with that email exists ( email-only subscribers get "Create account" instead ). */
		protected function print_row_actions( $result ) {
			$all = $this->row_menu_actions;
			$this->row_menu_actions = array_values( array_filter( $all, function( $a ) use ( $result ) { return 'account' !== $a['name'] || ! empty( $result->customer_id ); } ) );
			if ( empty( $result->customer_id ) ) {
				$create = array( 'label' => __( 'Create account…', 'wp-easycart' ), 'name' => 'create-account', 'icon' => 'plus-alt2', 'href' => admin_url( 'admin.php?page=wp-easycart-users&subpage=accounts&ec_admin_form_action=add-new&email=' . rawurlencode( (string) $result->email ) . '&first_name=' . rawurlencode( wp_unslash( (string) $result->first_name ) ) . '&last_name=' . rawurlencode( wp_unslash( (string) $result->last_name ) ) ) );
				array_splice( $this->row_menu_actions, 1, 0, array( $create ) );
			}
			parent::print_row_actions( $result );
			$this->row_menu_actions = $all;
		}

		protected function print_cell_content( $result, $col ) {
			switch ( $col['format'] ) {
				case 'sub_email':
					echo '<a href="#" class="ecv2-link-primary ecv2-title-link" onclick="return ecsub.edit( this );">' . esc_html( $result->email ) . '</a>';
					if ( ! is_email( $result->email ) ) { echo ' <span class="ecv2-chip ecv2-chip-red">' . esc_html__( 'invalid', 'wp-easycart' ) . '</span>'; }
					break;
				case 'sub_name': echo '' !== trim( (string) $result->full_name ) ? esc_html( wp_unslash( $result->full_name ) ) : '<span class="ecv2-sub">—</span>'; break;
				case 'sub_account':
					echo $result->customer_id ? '<a class="ecv2-chip ecv2-chip-green" href="' . esc_url( admin_url( 'admin.php?page=wp-easycart-users&subpage=accounts&ec_admin_form_action=edit&user_id=' . (int) $result->customer_id ) ) . '">' . esc_html__( 'customer', 'wp-easycart' ) . '</a>' : '<span class="ecv2-sub">' . esc_html__( 'email only', 'wp-easycart' ) . '</span>';
					break;
				default: parent::print_cell_content( $result, $col );
			}
		}
		protected function print_spreadsheet_cell( $result, $col ) { echo esc_html( wp_unslash( (string) $result->{ $col['name'] } ) ); }

		/** Transient: number of subscriber rows whose email is not shaped like an address ( 5 minutes ). @since 6.0.0 */
		const INVALID_TRANSIENT = 'ecv2_subscriber_invalid_count';

		/**
		 * "Invalid addresses" tile: counted in SQL instead of pulling every email into PHP for is_email().
		 * The pattern is one run of non-space, non-@ characters, an @, another such run, a literal dot, and a
		 * final run — the shape is_email() requires ( local@domain.tld ); is_email()'s extra rules ( length,
		 * character classes ) are not replicated, so the row chip in the list still uses is_email().
		 * Cached 5 minutes; cleared by clear_invalid_cache() on every add / edit / delete / import / restore.
		 *
		 * @since 6.0.0
		 * @return int
		 */
		public static function invalid_count() {
			$cached = get_transient( self::INVALID_TRANSIENT );
			if ( false !== $cached && is_numeric( $cached ) ) {
				return (int) $cached;
			}
			global $wpdb;
			/*
			 * The dot is written as the bracket expression [.] on purpose: a backslash inside a MySQL string
			 * literal is consumed by the string parser ( '\.' reaches the regex engine as '.', any character ),
			 * so a literal dot would need '\\\\.' in PHP source. [.] needs no escaping in either layer.
			 * [:space:] is a POSIX class, supported by both the classic and the ICU ( MySQL 8 ) regex engines.
			 */
			$n = $wpdb->get_var( "SELECT COUNT(*) FROM ec_subscriber WHERE email IS NULL OR TRIM( email ) NOT REGEXP '^[^@[:space:]]+@[^@[:space:]]+[.][^@[:space:]]+$'" );
			$n = ( null === $n ) ? 0 : (int) $n;
			set_transient( self::INVALID_TRANSIENT, $n, 5 * MINUTE_IN_SECONDS );
			return $n;
		}

		/** Drop the cached invalid-address count. Accepts any hook arguments. @since 6.0.0 */
		public static function clear_invalid_cache() { delete_transient( self::INVALID_TRANSIENT ); }

		/* ---- shared helpers ---- */
		public static function normalise( $email ) { return strtolower( trim( (string) $email ) ); }
		public static function exists( $email ) { global $wpdb; return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT subscriber_id FROM ec_subscriber WHERE LOWER( TRIM( email ) ) = %s LIMIT 1', self::normalise( $email ) ) ); }
		public static function insert( $email, $first = '', $last = '' ) {
			global $wpdb;
			$email = trim( (string) $email );
			if ( ! is_email( $email ) ) { return new WP_Error( 'email', __( 'That is not a valid email address.', 'wp-easycart' ) ); }
			if ( self::exists( $email ) ) { return new WP_Error( 'dupe', __( 'That address is already subscribed.', 'wp-easycart' ) ); }
			$first = sanitize_text_field( $first );
			$last  = sanitize_text_field( $last );
			$wpdb->insert( 'ec_subscriber', array( 'email' => $email, 'first_name' => $first, 'last_name' => $last ) );
			$subscriber_id = (int) $wpdb->insert_id;
			/* Same arguments as the storefront newsletter forms ( email, full name ): PRO's mailing-list sync and the activity log
			   read them, and PRO registers for 2 arguments ( passing only an id fatals on PHP 8 after the row is saved ). */
			do_action( 'wpeasycart_subscriber_added', $email, trim( $first . ' ' . $last ) );
			return $subscriber_id;
		}
	}

	/* Every subscriber change ( add, inline edit, delete, import — insert() fires the added action ) invalidates the tile count. */
	foreach ( array( 'wpeasycart_subscriber_added', 'wpeasycart_subscriber_updated', 'wpeasycart_subscriber_deleting' ) as $ecv2_sub_hook ) {
		add_action( $ecv2_sub_hook, array( 'wp_easycart_admin_subscriber_table', 'clear_invalid_cache' ) );
	}
	unset( $ecv2_sub_hook );

endif;

function ecv2_sub_guard( $get = false ) {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_users' ) ) { wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) ); }
	if ( $get ) { if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( $_GET['nonce'], wp_easycart_admin_subscriber_table::NONCE ) ) { wp_die( 'Invalid nonce' ); } return; }
	check_ajax_referer( wp_easycart_admin_subscriber_table::NONCE, 'nonce' );
}

add_action( 'wp_ajax_ecv2_subscriber_add', 'ecv2_subscriber_add' );
function ecv2_subscriber_add() {
	ecv2_sub_guard();
	$r = wp_easycart_admin_subscriber_table::insert( isset( $_POST['email'] ) ? wp_unslash( $_POST['email'] ) : '', isset( $_POST['first'] ) ? wp_unslash( $_POST['first'] ) : '', isset( $_POST['last'] ) ? wp_unslash( $_POST['last'] ) : '' );
	if ( is_wp_error( $r ) ) { wp_send_json_error( array( 'message' => $r->get_error_message() ) ); }
	wp_send_json_success( array( 'subscriber_id' => $r ) );
}

add_action( 'wp_ajax_ecv2_subscriber_inline_update', 'ecv2_subscriber_inline_update' );
function ecv2_subscriber_inline_update() {
	ecv2_sub_guard(); global $wpdb;
	$id = isset( $_POST['subscriber_id'] ) ? (int) $_POST['subscriber_id'] : 0; $field = isset( $_POST['field'] ) ? sanitize_key( $_POST['field'] ) : ''; $value = isset( $_POST['value'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['value'] ) ) ) : '';
	if ( ! $id || ! in_array( $field, array( 'email', 'first_name', 'last_name' ), true ) ) { wp_send_json_error( array( 'message' => __( 'Invalid update.', 'wp-easycart' ) ) ); }
	if ( 'email' === $field ) {
		if ( ! is_email( $value ) ) { wp_send_json_error( array( 'message' => __( 'That is not a valid email address.', 'wp-easycart' ) ) ); }
		$other = wp_easycart_admin_subscriber_table::exists( $value ); if ( $other && $other !== $id ) { wp_send_json_error( array( 'message' => __( 'Another subscriber already has that address.', 'wp-easycart' ) ) ); }
	}
	$old = $wpdb->get_var( $wpdb->prepare( "SELECT $field FROM ec_subscriber WHERE subscriber_id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $field is whitelisted via in_array( ..., array( 'email', 'first_name', 'last_name' ), true ) above.
	$wpdb->update( 'ec_subscriber', array( $field => $value ), array( 'subscriber_id' => $id ) );
	do_action( 'wpeasycart_subscriber_updated', $id );
	wp_send_json_success( array( 'display_value' => $value, 'old_value' => $old ) );
}

add_action( 'wp_ajax_ecv2_subscriber_bulk', 'ecv2_subscriber_bulk' );
function ecv2_subscriber_bulk() {
	ecv2_sub_guard(); global $wpdb;
	$ids = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ? array_slice( array_map( 'intval', $_POST['ids'] ), 0, 2000 ) : array();
	if ( empty( $ids ) || 'delete' !== ( isset( $_POST['op'] ) ? sanitize_key( $_POST['op'] ) : '' ) ) { wp_send_json_error( array( 'message' => __( 'Nothing to do.', 'wp-easycart' ) ) ); }
	$rows = $wpdb->get_results( 'SELECT * FROM ec_subscriber WHERE subscriber_id IN ( ' . implode( ',', $ids ) . ' )', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $ids is an intval()-mapped list built above.
	foreach ( $rows as $r ) { do_action( 'wpeasycart_subscriber_deleting', (int) $r['subscriber_id'] ); }
	$wpdb->query( 'DELETE FROM ec_subscriber WHERE subscriber_id IN ( ' . implode( ',', $ids ) . ' )' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $ids is an intval()-mapped list built above.
	/* translators: %d: number of subscribers deleted. */
	$message = sprintf( _n( 'Deleted %d subscriber.', 'Deleted %d subscribers.', count( $rows ), 'wp-easycart' ), count( $rows ) );
	/* 6.0.1: shared store, on an option rather than a transient — see wp_easycart_admin_undo::store(). The list reloads
	   onto the shared Undo bar ( wp_easycart_admin_undo::maybe_print_bar() ), which restores through the filter below. */
	$undo = class_exists( 'wp_easycart_admin_undo' ) ? wp_easycart_admin_undo::store( 'subscriber', $rows, $message ) : '';
	wp_send_json_success( array( 'done' => count( $rows ), 'undo' => $undo, 'message' => $message ) );
}

/**
 * Write deleted subscribers back with their original ids.
 *
 * @since 6.0.1
 * @param array $rows ec_subscriber rows as they were deleted.
 * @return string|WP_Error
 */
function ecv2_subscriber_restore_rows( $rows ) {
	global $wpdb;
	if ( ! $rows || ! is_array( $rows ) ) {
		return new WP_Error( 'gone', __( 'This deletion can no longer be undone.', 'wp-easycart' ) );
	}
	foreach ( $rows as $r ) {
		$wpdb->replace( 'ec_subscriber', $r );
	}
	wp_easycart_admin_subscriber_table::clear_invalid_cache();
	/* translators: %d: number of subscribers put back. */
	return sprintf( _n( 'Restored %d subscriber.', 'Restored %d subscribers.', count( $rows ), 'wp-easycart' ), count( $rows ) );
}

/**
 * Filter: wp_easycart_admin_undo_restore_subscriber. The Undo bar above the list restores through
 * ecv2_undo_restore, which hands the snapshot here.
 *
 * @since 6.0.1
 * @param mixed $result   Null until something handles it.
 * @param array $snapshot Rows from ecv2_subscriber_bulk().
 * @return string|WP_Error
 */
function ecv2_subscriber_undo_restore( $result, $snapshot ) {
	return ecv2_subscriber_restore_rows( $snapshot );
}
add_filter( 'wp_easycart_admin_undo_restore_subscriber', 'ecv2_subscriber_undo_restore', 10, 2 );

add_action( 'wp_ajax_ecv2_subscriber_restore', 'ecv2_subscriber_restore' );
function ecv2_subscriber_restore() {
	ecv2_sub_guard(); global $wpdb;
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- ecv2_sub_guard() verifies the nonce above.
	$key   = isset( $_POST['undo'] ) ? sanitize_text_field( wp_unslash( $_POST['undo'] ) ) : '';
	$entry = class_exists( 'wp_easycart_admin_undo' ) ? wp_easycart_admin_undo::take( $key ) : false;
	$result = ecv2_subscriber_restore_rows( ( $entry && isset( $entry['snapshot'] ) ) ? $entry['snapshot'] : false );
	if ( is_wp_error( $result ) ) { wp_send_json_error( array( 'message' => $result->get_error_message() ) ); }
	wp_send_json_success( array( 'message' => $result ) );
}

/** Rows come parsed from the browser as [ { email, first, last }, … ]; we validate, dedupe and report. */
function ecv2_subscriber_import_analyze( $rows ) {
	$seen = array(); $new = array(); $existing = 0; $invalid = array();
	foreach ( (array) $rows as $r ) {
		if ( ! is_array( $r ) ) { continue; }
		$email = trim( (string) ( isset( $r['email'] ) ? $r['email'] : '' ) ); $k = wp_easycart_admin_subscriber_table::normalise( $email );
		if ( ! is_email( $email ) ) { if ( '' !== $email ) { $invalid[] = $email; } continue; }
		if ( isset( $seen[ $k ] ) ) { continue; }
		$seen[ $k ] = 1;
		if ( wp_easycart_admin_subscriber_table::exists( $email ) ) { $existing++; continue; }
		$new[] = array( 'email' => $email, 'first' => sanitize_text_field( isset( $r['first'] ) ? $r['first'] : '' ), 'last' => sanitize_text_field( isset( $r['last'] ) ? $r['last'] : '' ) );
	}
	return array( 'new' => $new, 'existing' => $existing, 'invalid' => $invalid );
}
add_action( 'wp_ajax_ecv2_subscriber_import_preview', 'ecv2_subscriber_import_preview' );
function ecv2_subscriber_import_preview() {
	ecv2_sub_guard();
	$a = ecv2_subscriber_import_analyze( json_decode( wp_unslash( isset( $_POST['rows'] ) ? $_POST['rows'] : '[]' ), true ) );
	wp_send_json_success( array( 'new' => count( $a['new'] ), 'existing' => $a['existing'], 'invalid' => count( $a['invalid'] ), 'invalid_sample' => array_slice( $a['invalid'], 0, 5 ) ) );
}
add_action( 'wp_ajax_ecv2_subscriber_import', 'ecv2_subscriber_import' );
function ecv2_subscriber_import() {
	ecv2_sub_guard();
	$a = ecv2_subscriber_import_analyze( json_decode( wp_unslash( isset( $_POST['rows'] ) ? $_POST['rows'] : '[]' ), true ) );
	$n = 0; foreach ( $a['new'] as $r ) { if ( ! is_wp_error( wp_easycart_admin_subscriber_table::insert( $r['email'], $r['first'], $r['last'] ) ) ) { $n++; } }
	wp_send_json_success( array( 'imported' => $n, 'existing' => $a['existing'], 'invalid' => $a['invalid'], 'message' => sprintf( __( 'Imported %1$d subscribers · %2$d already subscribed · %3$d invalid.', 'wp-easycart' ), $n, $a['existing'], count( $a['invalid'] ) ) ) );
}

add_action( 'wp_ajax_ecv2_subscriber_export', 'ecv2_subscriber_export' );
function ecv2_subscriber_export() {
	ecv2_sub_guard( true ); global $wpdb;
	$where = ' WHERE 1=1';
	if ( ! empty( $_GET['ids'] ) ) { $where .= ' AND subscriber_id IN ( ' . implode( ',', array_map( 'intval', explode( ',', (string) $_GET['ids'] ) ) ) . ' )'; }
	$f = isset( $_GET['health_filter'] ) ? sanitize_key( $_GET['health_filter'] ) : ( isset( $_GET['filter_0'] ) ? sanitize_key( $_GET['filter_0'] ) : '' );
	if ( 'customers' === $f ) { $where .= ' AND EXISTS ( SELECT 1 FROM ec_user u WHERE u.email = ec_subscriber.email )'; }
	if ( 'guests' === $f ) { $where .= ' AND NOT EXISTS ( SELECT 1 FROM ec_user u WHERE u.email = ec_subscriber.email )'; }
	if ( ! empty( $_GET['s'] ) ) { $like = '%' . $wpdb->esc_like( sanitize_text_field( wp_unslash( $_GET['s'] ) ) ) . '%'; $where .= $wpdb->prepare( ' AND ( email LIKE %s OR first_name LIKE %s OR last_name LIKE %s )', $like, $like, $like ); }
	if ( ! headers_sent() ) { header( 'Content-Type: text/csv; charset=utf-8' ); header( 'Content-Disposition: attachment; filename="subscribers-' . date( 'Ymd' ) . '.csv"' ); }
	$out = fopen( 'php://output', 'w' ); fputcsv( $out, array( 'email', 'first_name', 'last_name' ) );
	/* 6.0.0: streamed 1000 rows at a time ( LIMIT / OFFSET over the email order ) instead of one result set for the whole list. */
	$chunk = 1000; $offset = 0;
	while ( true ) {
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT email, first_name, last_name FROM ec_subscriber $where ORDER BY email, subscriber_id LIMIT %d OFFSET %d", $chunk, $offset ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is literal SQL, an intval() IN list and $wpdb->prepare() fragments; limit and offset go through prepare().
		if ( empty( $rows ) ) { break; }
		foreach ( $rows as $r ) { fputcsv( $out, array( $r['email'], wp_unslash( $r['first_name'] ), wp_unslash( $r['last_name'] ) ) ); }
		fflush( $out );
		if ( ob_get_level() ) { ob_flush(); }
		flush();
		if ( count( $rows ) < $chunk ) { break; }
		$offset += $chunk;
	}
	fclose( $out ); exit;
}
