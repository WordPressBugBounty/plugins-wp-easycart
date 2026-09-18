<?php
/**
 * WP EasyCart Admin — Customers CSV import ( V2 ).
 *
 * Replaces the legacy "upload to Media Library → paste URL" flow. The browser parses the CSV, the server previews
 * it, then imports in chunks:
 *   - a row WITH  user_id → updates that customer ( only the columns present in the file are touched )
 *   - a row WITHOUT user_id → creates a customer ( email required, must not already exist )
 *   - "Match existing by email" ( option ) → a row without user_id whose email exists updates that customer instead
 *     of being skipped
 * Every skipped row is reported with a reason. Passwords are never imported: new customers get a random one and
 * use "Forgot password" ( same as the legacy importer ).
 *
 * Accepted columns: user_id, email, first_name, last_name, user_level, is_subscriber, exclude_tax, exclude_shipping,
 * allow_shipping_bypass, vat_registration_number, user_notes, email_other, billing_* and shipping_*
 * ( first_name, last_name, company_name, address_line_1, address_line_2, city, state, zip, country, phone ).
 *
 * AJAX: ecv2_user_import_preview, ecv2_user_import_run, ecv2_user_import_template. Nonce wp-easycart-ecv2-user-import.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_user_import' ) ) :

	final class wp_easycart_admin_user_import {

		const NONCE = 'wp-easycart-ecv2-user-import';
		const CHUNK = 100;

		public static function user_columns() { return array( 'email', 'first_name', 'last_name', 'user_level', 'is_subscriber', 'exclude_tax', 'exclude_shipping', 'allow_shipping_bypass', 'vat_registration_number', 'user_notes', 'email_other' ); }
		public static function address_columns() { return array( 'first_name', 'last_name', 'company_name', 'address_line_1', 'address_line_2', 'city', 'state', 'zip', 'country', 'phone' ); }
		public static function flags() { return array( 'is_subscriber', 'exclude_tax', 'exclude_shipping', 'allow_shipping_bypass' ); }
		public static function all_columns() {
			$c = array_merge( array( 'user_id' ), self::user_columns() );
			foreach ( self::address_columns() as $a ) { $c[] = 'billing_' . $a; }
			foreach ( self::address_columns() as $a ) { $c[] = 'shipping_' . $a; }
			return $c;
		}
		public static function roles() {
			global $wpdb; $r = $wpdb->get_col( 'SELECT role_label FROM ec_role' );
			$r = array_map( 'strtolower', array_map( 'wp_unslash', (array) $r ) );
			foreach ( array( 'shopper', 'admin', 'pending' ) as $b ) { if ( ! in_array( $b, $r, true ) ) { $r[] = $b; } }
			return $r;
		}

		/** Normalise headers: trim, lowercase, spaces/dashes → underscores; a few friendly aliases. */
		public static function normalize_header( $h ) {
			$h = strtolower( trim( (string) $h ) ); $h = preg_replace( '/[\s\-]+/', '_', $h );
			$alias = array( 'id' => 'user_id', 'customer_id' => 'user_id', 'e_mail' => 'email', 'email_address' => 'email', 'e_mail_address' => 'email', 'firstname' => 'first_name', 'lastname' => 'last_name', 'role' => 'user_level', 'level' => 'user_level', 'subscriber' => 'is_subscriber', 'newsletter' => 'is_subscriber', 'vat' => 'vat_registration_number', 'vat_number' => 'vat_registration_number', 'notes' => 'user_notes', 'billing_company' => 'billing_company_name', 'shipping_company' => 'shipping_company_name', 'billing_address' => 'billing_address_line_1', 'shipping_address' => 'shipping_address_line_1', 'billing_postcode' => 'billing_zip', 'shipping_postcode' => 'shipping_zip', 'billing_postal_code' => 'billing_zip', 'shipping_postal_code' => 'shipping_zip' );
			return isset( $alias[ $h ] ) ? $alias[ $h ] : $h;
		}
		public static function flag( $v ) { $v = strtolower( trim( (string) $v ) ); return in_array( $v, array( '1', 'yes', 'y', 'true', 'on' ), true ) ? 1 : 0; }

		/**
		 * Classify rows without writing. Returns per-row action + reason, totals, unknown headers, duplicate emails.
		 * $rows: list of assoc arrays keyed by normalised header.
		 */
		public static function classify( $rows, $headers, $opts ) {
			global $wpdb;
			$known = self::all_columns(); $unknown = array_values( array_diff( $headers, $known ) ); $used = array_values( array_intersect( $headers, $known ) );
			$roles = self::roles(); $seen = array(); $out = array(); $tot = array( 'create' => 0, 'update' => 0, 'skip' => 0 ); $reasons = array();
			$has_email = in_array( 'email', $headers, true ); $has_id = in_array( 'user_id', $headers, true );
			foreach ( $rows as $i => $r ) {
				$id = $has_id && isset( $r['user_id'] ) ? (int) $r['user_id'] : 0; $email = $has_email && isset( $r['email'] ) ? sanitize_email( trim( (string) $r['email'] ) ) : '';
				$action = ''; $reason = ''; $target = 0;
				if ( $id ) {
					$target = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT user_id FROM ec_user WHERE user_id = %d', $id ) );
					if ( ! $target ) { $action = 'skip'; $reason = sprintf( __( 'user_id %d not found', 'wp-easycart' ), $id ); }
					else if ( '' !== $email ) {
						$other = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT user_id FROM ec_user WHERE email = %s AND user_id != %d', $email, $id ) );
						if ( $other ) { $action = 'skip'; $reason = sprintf( __( 'email already belongs to customer #%d', 'wp-easycart' ), $other ); }
					}
					if ( ! $action ) { $action = 'update'; }
				} else {
					if ( '' === $email || ! is_email( $email ) ) { $action = 'skip'; $reason = $has_email ? __( 'invalid or missing email', 'wp-easycart' ) : __( 'no user_id and no email column', 'wp-easycart' ); }
					else if ( isset( $seen[ $email ] ) ) { $action = 'skip'; $reason = sprintf( __( 'duplicate of row %d', 'wp-easycart' ), $seen[ $email ] + 1 ); }
					else {
						$existing = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT user_id FROM ec_user WHERE email = %s', $email ) );
						if ( $existing && ! empty( $opts['match_email'] ) ) { $action = 'update'; $target = $existing; }
						else if ( $existing ) { $action = 'skip'; $reason = sprintf( __( 'email already exists (customer #%d) — turn on “Update existing by email” to update instead', 'wp-easycart' ), $existing ); }
						else { $action = 'create'; }
					}
				}
				if ( '' !== $email ) { $seen[ $email ] = $i; }
				if ( 'skip' !== $action && isset( $r['user_level'] ) && '' !== trim( (string) $r['user_level'] ) && ! in_array( strtolower( trim( (string) $r['user_level'] ) ), $roles, true ) ) { $action = 'skip'; $reason = sprintf( __( 'unknown role “%s”', 'wp-easycart' ), trim( (string) $r['user_level'] ) ); }
				$tot[ $action ]++; if ( $reason ) { $reasons[ $reason ] = isset( $reasons[ $reason ] ) ? $reasons[ $reason ] + 1 : 1; }
				$out[] = array( 'row' => $i + 1, 'action' => $action, 'reason' => $reason, 'target' => $target, 'email' => $email, 'name' => trim( ( isset( $r['first_name'] ) ? $r['first_name'] : '' ) . ' ' . ( isset( $r['last_name'] ) ? $r['last_name'] : '' ) ) );
			}
			arsort( $reasons );
			return array( 'rows' => $out, 'totals' => $tot, 'unknown_headers' => $unknown, 'used_headers' => $used, 'reasons' => $reasons, 'has_id' => $has_id, 'has_email' => $has_email );
		}

		/** Apply one classified row. Returns 'create' | 'update' | 'skip'. */
		public static function apply( $r, $cls ) {
			global $wpdb;
			if ( 'skip' === $cls['action'] ) { return 'skip'; }
			$user = array();
			foreach ( self::user_columns() as $c ) {
				if ( ! array_key_exists( $c, $r ) ) { continue; }
				$v = $r[ $c ];
				if ( in_array( $c, self::flags(), true ) ) { $user[ $c ] = self::flag( $v ); }
				else if ( 'email' === $c || 'email_other' === $c ) { $v = sanitize_email( trim( (string) $v ) ); if ( 'email' === $c && '' === $v ) { continue; } $user[ $c ] = $v; }
				else if ( 'user_level' === $c ) { $v = strtolower( trim( (string) $v ) ); if ( '' !== $v ) { $user[ $c ] = $v; } }
				else if ( 'user_notes' === $c ) { $user[ $c ] = sanitize_textarea_field( (string) $v ); }
				else { $user[ $c ] = sanitize_text_field( (string) $v ); }
			}
			if ( 'create' === $cls['action'] ) {
				$user += array( 'user_level' => 'shopper', 'first_name' => '', 'last_name' => '' );
				$user['password'] = wp_easycart_hash_password( bin2hex( random_bytes( 16 ) ) );
				$user['date_created'] = current_time( 'mysql' );
				$wpdb->insert( 'ec_user', $user ); $uid = (int) $wpdb->insert_id;
				if ( ! $uid ) { return 'skip'; }
				self::addresses( $uid, $r, true );
				do_action( 'wpeasycart_user_added', $uid ); do_action( 'wp_easycart_user_imported', $uid, 'create' );
				return 'create';
			}
			$uid = (int) $cls['target'];
			if ( $user ) { $wpdb->update( 'ec_user', $user, array( 'user_id' => $uid ) ); }
			self::addresses( $uid, $r, false );
			do_action( 'wpeasycart_user_updated', $uid ); do_action( 'wp_easycart_user_imported', $uid, 'update' );
			return 'update';
		}

		/** Billing / shipping: insert when the customer has no default of that type, otherwise update the default row. */
		private static function addresses( $uid, $r, $is_new ) {
			global $wpdb;
			foreach ( array( 'billing', 'shipping' ) as $kind ) {
				$data = array(); $any = false;
				foreach ( self::address_columns() as $a ) { $k = $kind . '_' . $a; if ( array_key_exists( $k, $r ) ) { $data[ $a ] = sanitize_text_field( (string) $r[ $k ] ); if ( '' !== $data[ $a ] ) { $any = true; } } }
				if ( ! $any ) { continue; }
				$col = 'default_' . $kind . '_address_id';
				$existing = $is_new ? 0 : (int) $wpdb->get_var( $wpdb->prepare( "SELECT $col FROM ec_user WHERE user_id = %d", $uid ) );
				if ( $existing && $wpdb->get_var( $wpdb->prepare( 'SELECT address_id FROM ec_address WHERE address_id = %d AND user_id = %d', $existing, $uid ) ) ) { $wpdb->update( 'ec_address', $data, array( 'address_id' => $existing ) ); }
				else { $wpdb->insert( 'ec_address', $data + array( 'user_id' => $uid ) ); $wpdb->update( 'ec_user', array( $col => (int) $wpdb->insert_id ), array( 'user_id' => $uid ) ); }
			}
		}

		public static function template_csv() {
			$h = self::all_columns();
			$ex = array( '', 'jane@example.com', 'Jane', 'Doe', 'shopper', 'yes', 'no', 'no', 'no', '', 'Prefers email', '', 'Jane', 'Doe', 'Acme', '1 Main St', '', 'Portland', 'OR', '97201', 'US', '503-555-0100', 'Jane', 'Doe', '', '1 Main St', '', 'Portland', 'OR', '97201', 'US', '503-555-0100' );
			$q = function( $v ) { return '"' . str_replace( '"', '""', (string) $v ) . '"'; };
			return implode( ',', array_map( $q, $h ) ) . "\n" . implode( ',', array_map( $q, array_pad( $ex, count( $h ), '' ) ) ) . "\n";
		}
	}

endif;

function ecv2_user_import_guard() {
	if ( ! function_exists( 'ecv2_user_can_manage' ) || ! ecv2_user_can_manage() ) { wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) ); }
	check_ajax_referer( wp_easycart_admin_user_import::NONCE, 'nonce' );
}
function ecv2_user_import_payload() {
	$p = json_decode( wp_unslash( isset( $_POST['payload'] ) ? $_POST['payload'] : '' ), true );
	if ( ! is_array( $p ) || ! isset( $p['headers'], $p['rows'] ) || ! is_array( $p['headers'] ) || ! is_array( $p['rows'] ) ) { wp_send_json_error( array( 'message' => __( 'Nothing to import — the file could not be read.', 'wp-easycart' ) ) ); }
	$headers = array_map( array( 'wp_easycart_admin_user_import', 'normalize_header' ), $p['headers'] );
	$rows = array();
	foreach ( array_slice( $p['rows'], 0, 5000 ) as $row ) { if ( ! is_array( $row ) ) { continue; } $r = array(); foreach ( $headers as $i => $h ) { if ( '' === $h ) { continue; } $r[ $h ] = isset( $row[ $i ] ) ? (string) $row[ $i ] : ''; } $rows[] = $r; }
	return array( $headers, $rows, array( 'match_email' => ! empty( $p['match_email'] ) ) );
}

add_action( 'wp_ajax_ecv2_user_import_preview', function() {
	ecv2_user_import_guard(); list( $headers, $rows, $opts ) = ecv2_user_import_payload();
	$c = wp_easycart_admin_user_import::classify( $rows, $headers, $opts );
	$c['sample'] = array_slice( $c['rows'], 0, 12 ); unset( $c['rows'] ); $c['total'] = count( $rows );
	wp_send_json_success( $c );
} );

add_action( 'wp_ajax_ecv2_user_import_run', function() {
	ecv2_user_import_guard(); list( $headers, $rows, $opts ) = ecv2_user_import_payload();
	$offset = isset( $_POST['offset'] ) ? max( 0, (int) $_POST['offset'] ) : 0; $limit = wp_easycart_admin_user_import::CHUNK;
	/* classify the whole file again so duplicate detection across chunks stays correct, then apply this slice */
	$c = wp_easycart_admin_user_import::classify( $rows, $headers, $opts ); $done = array( 'create' => 0, 'update' => 0, 'skip' => 0 );
	for ( $i = $offset; $i < min( $offset + $limit, count( $rows ) ); $i++ ) { $done[ wp_easycart_admin_user_import::apply( $rows[ $i ], $c['rows'][ $i ] ) ]++; }
	wp_cache_flush();
	wp_send_json_success( array( 'done' => $done, 'next' => min( $offset + $limit, count( $rows ) ), 'total' => count( $rows ), 'finished' => $offset + $limit >= count( $rows ) ) );
} );

add_action( 'wp_ajax_ecv2_user_import_template', function() {
	ecv2_user_import_guard();
	wp_send_json_success( array( 'csv' => wp_easycart_admin_user_import::template_csv(), 'filename' => 'easycart-customers-template.csv' ) );
} );
