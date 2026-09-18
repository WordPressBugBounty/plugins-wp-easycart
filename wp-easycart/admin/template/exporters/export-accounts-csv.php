<?php
/**
 * Customer accounts CSV exporter.
 *
 * Included by wp_easycart_admin_users::process_export_users() after the
 * capability check. Streams the CSV in bounded chunks of users so a store
 * with hundreds of thousands of accounts never holds the table in memory.
 *
 * "Export All CSV" exports what the customer list currently shows: the
 * search box, filter panel and health-stat selection are submitted with the
 * list form and are applied here through the list table's own filter builder
 * (which also covers PRO-registered filters). "Export Selected CSV" exports
 * the checked accounts only.
 *
 * @since 6.0.0 Rewritten to stream in chunks and to honour the list scope.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- capability-checked route ( wp_easycart_admin_users::process_export_users() ); the list form submits the wp-easycart-bulk-accounts nonce with these values.
$user_id_array = array();
if ( isset( $_GET['ec_admin_form_action'] ) && 'export-accounts-csv' === sanitize_key( wp_unslash( $_GET['ec_admin_form_action'] ) ) && isset( $_GET['bulk'] ) ) {
	$user_id_array = array_values( array_unique( array_filter( array_map( 'absint', (array) wp_unslash( $_GET['bulk'] ) ) ) ) );
}
// phpcs:enable WordPress.Security.NonceVerification.Recommended

if ( ! class_exists( 'wp_easycart_admin_user_export_scope' ) && class_exists( 'wp_easycart_admin_user_table' ) ) {
	/**
	 * Exposes the customer list's current search / filter / health-stat scope
	 * so "Export All CSV" exports exactly the rows the list is showing.
	 *
	 * @since 6.0.0
	 */
	class wp_easycart_admin_user_export_scope extends wp_easycart_admin_user_table {

		/**
		 * Split the list table's filter clause into the pieces the export
		 * query needs (it adds its own keyset WHERE and GROUP BY).
		 *
		 * @return array select, join, where ( " AND ..." ), having.
		 */
		public function get_export_scope() {
			$scope = array(
				'select' => '',
				'join' => '',
				'where' => '',
				'having' => '',
			);

			$this->setup();
			$filter_sql = $this->get_filter();

			$where_marker = ' WHERE 1=1';
			$where_pos = strpos( $filter_sql, $where_marker );
			if ( false === $where_pos ) {
				return $scope;
			}

			$scope['select'] = $this->get_filter_select();
			$scope['join'] = substr( $filter_sql, 0, $where_pos );
			$conditions = substr( $filter_sql, $where_pos + strlen( $where_marker ) );

			if ( $this->has_active_having_filter() ) {
				$having_pos = strrpos( $conditions, ' HAVING ' );
				if ( false !== $having_pos ) {
					$scope['having'] = substr( $conditions, $having_pos );
					$conditions = substr( $conditions, 0, $having_pos );
				}
			}

			$scope['where'] = $conditions;
			return $scope;
		}

		/**
		 * Whether an active filter declares a HAVING clause.
		 *
		 * @return bool
		 */
		private function has_active_having_filter() {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filter state, mirrors wp_easycart_admin_table_v2::get_filter().
			foreach ( $this->filters as $i => $filter ) {
				if ( isset( $filter['having'] ) && '' !== $filter['having'] && isset( $_GET[ 'filter_' . $i ] ) && '' !== sanitize_text_field( wp_unslash( $_GET[ 'filter_' . $i ] ) ) ) {
					return true;
				}
			}
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			return false;
		}
	}
}

/* Header row: identical to the previous SELECT list and aliasing. */
$keys = array(
	'user_id',
	'email',
	'list_id',
	'edit_sequence',
	'quickbooks_status',
	'first_name',
	'last_name',
	'default_billing_address_id',
	'default_shipping_address_id',
	'user_level',
	'is_subscriber',
	'realauth_registered',
	'stripe_customer_id',
	'default_card_type',
	'default_card_last4',
	'exclude_tax',
	'exclude_shipping',
	'user_notes',
	'vat_registration_number',
	'billing_address_id',
	'billing_user_id',
	'billing_first_name',
	'billing_last_name',
	'billing_address_line_1',
	'billing_address_line_2',
	'billing_city',
	'billing_state',
	'billing_zip',
	'billing_country',
	'billing_phone',
	'billing_company_name',
	'shipping_address_id',
	'shipping_user_id',
	'shipping_first_name',
	'shipping_last_name',
	'shipping_address_line_1',
	'shipping_address_line_2',
	'shipping_city',
	'shipping_state',
	'shipping_zip',
	'shipping_country',
	'shipping_phone',
	'shipping_company_name',
	'customer_value',
);

/* List scope only applies to "Export All"; a selection is already explicit. */
$scope = array(
	'select' => '',
	'join' => '',
	'where' => '',
	'having' => '',
);
if ( 0 === count( $user_id_array ) && class_exists( 'wp_easycart_admin_user_export_scope' ) ) {
	$scope_table = new wp_easycart_admin_user_export_scope();
	$scope = $scope_table->get_export_scope();
	unset( $scope_table );
}

$chunk_size = 1000;

$select_sql = 'SELECT
		ec_user.user_id,
		ec_user.email,
		ec_user.list_id,
		ec_user.edit_sequence,
		ec_user.quickbooks_status,
		ec_user.first_name,
		ec_user.last_name,
		ec_user.default_billing_address_id,
		ec_user.default_shipping_address_id,
		ec_user.user_level,
		ec_user.is_subscriber,
		ec_user.realauth_registered,
		ec_user.stripe_customer_id,
		ec_user.default_card_type,
		ec_user.default_card_last4,
		ec_user.exclude_tax,
		ec_user.exclude_shipping,
		ec_user.user_notes,
		ec_user.vat_registration_number,
		ec_address.address_id AS billing_address_id,
		ec_address.user_id AS billing_user_id,
		ec_address.first_name AS billing_first_name,
		ec_address.last_name AS billing_last_name,
		ec_address.address_line_1 AS billing_address_line_1,
		ec_address.address_line_2 AS billing_address_line_2,
		ec_address.city AS billing_city,
		ec_address.state AS billing_state,
		ec_address.zip AS billing_zip,
		ec_address.country AS billing_country,
		ec_address.phone AS billing_phone,
		ec_address.company_name AS billing_company_name,
		ec_address1.address_id AS shipping_address_id,
		ec_address1.user_id AS shipping_user_id,
		ec_address1.first_name AS shipping_first_name,
		ec_address1.last_name AS shipping_last_name,
		ec_address1.address_line_1 AS shipping_address_line_1,
		ec_address1.address_line_2 AS shipping_address_line_2,
		ec_address1.city AS shipping_city,
		ec_address1.state AS shipping_state,
		ec_address1.zip AS shipping_zip,
		ec_address1.country AS shipping_country,
		ec_address1.phone AS shipping_phone,
		ec_address1.company_name AS shipping_company_name,
		( SUM( ec_order.sub_total ) - SUM( ec_order.refund_total ) ) AS customer_value' . $scope['select'] . '
	FROM
		ec_user
		LEFT JOIN ec_address ON ( ec_user.default_billing_address_id = ec_address.address_id )
		LEFT JOIN ec_address ec_address1 ON ( ec_user.default_shipping_address_id = ec_address1.address_id )
		LEFT JOIN ec_order ON ( ec_order.user_id = ec_user.user_id )' . $scope['join'];

$user_id_filter_sql = '';
if ( count( $user_id_array ) > 0 ) {
	$user_id_filter_sql = $wpdb->prepare( ' AND ec_user.user_id IN ( ' . implode( ', ', array_fill( 0, count( $user_id_array ), '%d' ) ) . ' )', $user_id_array ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- the only interpolation is a list of %d placeholders; the ids are prepare() arguments.
}

if ( function_exists( 'set_time_limit' ) ) {
	@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a large export legitimately outlives max_execution_time; set_time_limit() warns where it is disabled.
}
while ( ob_get_level() > 0 ) {
	ob_end_clean();
}

header( 'Content-Type: text/csv; charset=utf-8' );
header( 'Content-Disposition: attachment; filename=users-export-' . date( 'Y-m-d' ) . '.csv' );

$output = fopen( 'php://output', 'w' );
fputcsv( $output, $keys );

$last_user_id = 0;
while ( true ) {
	/*
	 * The list table's filter clause is already prepare()d, so it must not go
	 * through prepare() again: assemble the query from prepared fragments.
	 */
	$sql = $select_sql
		. $wpdb->prepare( ' WHERE ec_user.user_id > %d', $last_user_id )
		. $user_id_filter_sql
		. $scope['where']
		. ' GROUP BY ec_user.user_id'
		. $scope['having']
		. $wpdb->prepare( ' ORDER BY ec_user.user_id ASC LIMIT %d', $chunk_size );
	$results = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- assembled from static SQL, prepare()d fragments and the list table's own prepare()d filter clause.
	if ( empty( $results ) ) {
		break;
	}

	foreach ( $results as $result ) {
		$last_user_id = max( $last_user_id, (int) $result['user_id'] );
		$line = array();
		foreach ( $keys as $key ) {
			$line[] = isset( $result[ $key ] ) ? $result[ $key ] : '';
		}
		fputcsv( $output, $line );
	}

	flush();

	if ( count( $results ) < $chunk_size ) {
		break;
	}
}

fclose( $output );
die();
