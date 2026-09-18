<?php
/**
 * WP EasyCart Admin — Taxes settings ( V2 ) data + AJAX.
 *
 * Backs admin/template/settings/tax.php. The declaration's plain options save
 * through the engine; the three editors that are not options save here:
 *
 *  - Rates by state / by country: rows in ec_taxrate ( tax_by_state = 1 /
 *    tax_by_country = 1 ), the same columns the classic
 *    wp_easycart_admin_taxes handlers wrote, firing the same
 *    wpeasycart_taxrate_added / _updated / _deleting / _deleted hooks.
 *  - VAT rate per country: ec_country.vat_rate_cnt + vat_b2b_enabled.
 *  - Canada province × role grid: the ec_option_canada_tax_options array,
 *    same keys as the classic grid ( ec_option_collect_<province>_tax_<role>,
 *    ec_option_<province>_tax_<role>_<gst|pst|hst> as fractions ).
 *
 * Every handler opens with ecv2_settings_guard() ( capability + V2 nonce ).
 * Included by admin/admin-init.php so the handlers exist on admin-ajax requests.
 *
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_tax_v2' ) ) :

class wp_easycart_admin_tax_v2 {

	/* ------------------------------------------------------------------ */
	/* Data                                                                */
	/* ------------------------------------------------------------------ */

	/** Province key => label + default GST / PST / HST ( fractions, as the option stores them ). Same keys as the classic grid. */
	public static function provinces() {
		return array(
			'alberta'               => array( 'name' => __( 'Alberta', 'wp-easycart' ), 'gst' => .05, 'pst' => .00, 'hst' => .00 ),
			'british_columbia'      => array( 'name' => __( 'British Columbia', 'wp-easycart' ), 'gst' => .05, 'pst' => .07, 'hst' => .00 ),
			'manitoba'              => array( 'name' => __( 'Manitoba', 'wp-easycart' ), 'gst' => .05, 'pst' => .08, 'hst' => .00 ),
			'new_brunswick'         => array( 'name' => __( 'New Brunswick', 'wp-easycart' ), 'gst' => .00, 'pst' => .00, 'hst' => .13 ),
			'newfoundland'          => array( 'name' => __( 'Newfoundland and Labrador', 'wp-easycart' ), 'gst' => .00, 'pst' => .00, 'hst' => .13 ),
			'northwest_territories' => array( 'name' => __( 'Northwest Territories', 'wp-easycart' ), 'gst' => .05, 'pst' => .00, 'hst' => .00 ),
			'nova_scotia'           => array( 'name' => __( 'Nova Scotia', 'wp-easycart' ), 'gst' => .00, 'pst' => .00, 'hst' => .15 ),
			'nunavut'               => array( 'name' => __( 'Nunavut', 'wp-easycart' ), 'gst' => .05, 'pst' => .00, 'hst' => .00 ),
			'ontario'               => array( 'name' => __( 'Ontario', 'wp-easycart' ), 'gst' => .00, 'pst' => .00, 'hst' => .13 ),
			'prince_edward_island'  => array( 'name' => __( 'Prince Edward Island', 'wp-easycart' ), 'gst' => .00, 'pst' => .00, 'hst' => .14 ),
			'quebec'                => array( 'name' => __( 'Quebec', 'wp-easycart' ), 'gst' => .05, 'pst' => .09975, 'hst' => .00 ),
			'saskatchewan'          => array( 'name' => __( 'Saskatchewan', 'wp-easycart' ), 'gst' => .05, 'pst' => .05, 'hst' => .00 ),
			'yukon'                 => array( 'name' => __( 'Yukon', 'wp-easycart' ), 'gst' => .05, 'pst' => .00, 'hst' => .00 ),
		);
	}

	/** Customer role labels the Canada grid is keyed by ( every ec_role except admin ). */
	public static function roles() {
		global $wpdb;
		$labels = array();
		foreach ( (array) $wpdb->get_results( "SELECT role_label FROM ec_role WHERE role_label != 'admin' ORDER BY role_id ASC" ) as $role ) {
			$labels[] = $role->role_label;
		}
		return $labels;
	}

	/** The Canada grid option as an array. */
	public static function canada_options() {
		$options = get_option( 'ec_option_canada_tax_options' );
		return is_array( $options ) ? $options : array();
	}

	/** Ship-to states with their country: id, name, code, country iso2, country name. */
	public static function states() {
		global $wpdb;
		return (array) $wpdb->get_results( 'SELECT ec_state.id_sta AS id, ec_state.name_sta AS name, ec_state.code_sta AS code, ec_country.iso2_cnt AS country, ec_country.name_cnt AS country_name FROM ec_state LEFT JOIN ec_country ON ec_country.id_cnt = ec_state.idcnt_sta WHERE ec_state.ship_to_active = 1 ORDER BY ec_country.sort_order ASC, ec_state.name_sta ASC' );
	}

	/** Ship-to countries: id ( id_cnt ), iso2, name. */
	public static function countries() {
		global $wpdb;
		return (array) $wpdb->get_results( 'SELECT id_cnt AS id, iso2_cnt AS iso2, name_cnt AS name FROM ec_country WHERE ship_to_active = 1 ORDER BY sort_order ASC, name_cnt ASC' );
	}

	/** State rate rows: id, state_id ( resolved like the classic table ), country ( iso2 ), rate. */
	public static function state_rates() {
		global $wpdb;
		return (array) $wpdb->get_results( 'SELECT ec_taxrate.taxrate_id AS id, ec_state.id_sta AS state_id, ec_taxrate.country_code AS country, ec_taxrate.state_rate AS rate FROM ec_taxrate LEFT JOIN ec_country ON ( ec_country.iso2_cnt = ec_taxrate.country_code ) LEFT JOIN ec_state ON ( ec_state.code_sta = ec_taxrate.state_code AND ec_state.idcnt_sta = ec_country.id_cnt ) WHERE ec_taxrate.tax_by_state = 1 ORDER BY ec_taxrate.country_code ASC, ec_taxrate.state_code ASC' );
	}

	/** Country rate rows: id, country ( iso2 ), rate. */
	public static function country_rates() {
		global $wpdb;
		return (array) $wpdb->get_results( 'SELECT taxrate_id AS id, country_code AS country, country_rate AS rate FROM ec_taxrate WHERE tax_by_country = 1 ORDER BY country_code ASC' );
	}

	/** Countries with a VAT rate: id ( id_cnt ), iso2, name, rate, b2b. */
	public static function vat_country_rates() {
		global $wpdb;
		return (array) $wpdb->get_results( 'SELECT id_cnt AS id, iso2_cnt AS iso2, name_cnt AS name, vat_rate_cnt AS rate, vat_b2b_enabled AS b2b FROM ec_country WHERE vat_rate_cnt > 0 ORDER BY iso2_cnt ASC' );
	}

	/* ------------------------------------------------------------------ */
	/* Request helpers ( called after ecv2_settings_guard() )              */
	/* ------------------------------------------------------------------ */

	private static function post_text( $key ) {
		return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by ecv2_settings_guard() in the calling handler.
	}

	/** A percentage 0–100 with up to three decimals, or WP_Error. */
	private static function post_rate( $key = 'rate' ) {
		$raw = self::post_text( $key );
		if ( '' === $raw || ! is_numeric( $raw ) ) {
			return new WP_Error( 'rate', __( 'Enter a rate.', 'wp-easycart' ) );
		}
		$rate = round( (float) $raw, 3 );
		if ( $rate < 0 || $rate > 100 ) {
			return new WP_Error( 'rate', __( 'The rate must be between 0 and 100.', 'wp-easycart' ) );
		}
		return $rate;
	}

	private static function post_kind() {
		$kind = self::post_text( 'kind' );
		if ( ! in_array( $kind, array( 'state', 'country' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown rate table.', 'wp-easycart' ) ) );
		}
		return $kind;
	}

	/** ec_state row + its country for a posted state id, or dies. */
	private static function state_for( $state_id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT ec_state.code_sta, ec_country.iso2_cnt FROM ec_state LEFT JOIN ec_country ON ec_country.id_cnt = ec_state.idcnt_sta WHERE ec_state.id_sta = %d', $state_id ) );
		if ( ! $row ) {
			wp_send_json_error( array( 'message' => __( 'Choose a state.', 'wp-easycart' ) ) );
		}
		return $row;
	}

	/** Validates a posted iso2 country code against ec_country, or dies. */
	private static function country_for( $iso2 ) {
		global $wpdb;
		$iso2 = strtoupper( wp_easycart_admin_verification()->filter_chars( $iso2, 2 ) );
		$found = $wpdb->get_var( $wpdb->prepare( 'SELECT iso2_cnt FROM ec_country WHERE iso2_cnt = %s', $iso2 ) );
		if ( ! $found ) {
			wp_send_json_error( array( 'message' => __( 'Choose a country.', 'wp-easycart' ) ) );
		}
		return $found;
	}

	/* ------------------------------------------------------------------ */
	/* AJAX: rates by state / by country ( ec_taxrate )                    */
	/* ------------------------------------------------------------------ */

	/** POST kind, geo ( state id | iso2 ), rate → { id, group } */
	public static function ajax_rate_add() {
		ecv2_settings_guard();
		global $wpdb;
		$kind = self::post_kind();
		$rate = self::post_rate();
		if ( is_wp_error( $rate ) ) {
			wp_send_json_error( array( 'message' => $rate->get_error_message() ) );
		}
		if ( 'state' === $kind ) {
			$state = self::state_for( (int) self::post_text( 'geo' ) );
			$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_taxrate( tax_by_state, state_code, country_code, state_rate ) VALUES( 1, %s, %s, %f )', $state->code_sta, $state->iso2_cnt, $rate ) );
			$group = $state->iso2_cnt;
		} else {
			$iso2 = self::country_for( self::post_text( 'geo' ) );
			$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_taxrate( tax_by_country, country_code, country_rate ) VALUES( 1, %s, %f )', $iso2, $rate ) );
			$group = $iso2;
		}
		$id = (int) $wpdb->insert_id;
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Could not add the rate.', 'wp-easycart' ) ) );
		}
		do_action( 'wpeasycart_taxrate_added', $id );
		wp_send_json_success( array( 'id' => $id, 'group' => $group, 'rate' => $rate, 'message' => __( 'Saved.', 'wp-easycart' ) ) );
	}

	/** POST kind, id, geo, rate → { id, group } */
	public static function ajax_rate_update() {
		ecv2_settings_guard();
		global $wpdb;
		$kind = self::post_kind();
		$id   = (int) self::post_text( 'id' );
		$rate = self::post_rate();
		if ( is_wp_error( $rate ) ) {
			wp_send_json_error( array( 'message' => $rate->get_error_message() ) );
		}
		$flag  = ( 'state' === $kind ) ? 'tax_by_state' : 'tax_by_country';
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT taxrate_id FROM ec_taxrate WHERE taxrate_id = %d AND {$flag} = 1", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $flag is one of two literal column names chosen above.
		if ( ! $exists ) {
			wp_send_json_error( array( 'message' => __( 'That rate no longer exists. Reload the page.', 'wp-easycart' ) ) );
		}
		if ( 'state' === $kind ) {
			$state = self::state_for( (int) self::post_text( 'geo' ) );
			$wpdb->query( $wpdb->prepare( 'UPDATE ec_taxrate SET state_code = %s, country_code = %s, state_rate = %f WHERE taxrate_id = %d', $state->code_sta, $state->iso2_cnt, $rate, $id ) );
			$group = $state->iso2_cnt;
		} else {
			$iso2 = self::country_for( self::post_text( 'geo' ) );
			$wpdb->query( $wpdb->prepare( 'UPDATE ec_taxrate SET country_code = %s, country_rate = %f WHERE taxrate_id = %d', $iso2, $rate, $id ) );
			$group = $iso2;
		}
		do_action( 'wpeasycart_taxrate_updated', $id );
		wp_send_json_success( array( 'id' => $id, 'group' => $group, 'rate' => $rate, 'message' => __( 'Saved.', 'wp-easycart' ) ) );
	}

	/** POST kind, id → { remaining } */
	public static function ajax_rate_delete() {
		ecv2_settings_guard();
		global $wpdb;
		$kind = self::post_kind();
		$id   = (int) self::post_text( 'id' );
		$flag = ( 'state' === $kind ) ? 'tax_by_state' : 'tax_by_country';
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT taxrate_id FROM ec_taxrate WHERE taxrate_id = %d AND {$flag} = 1", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $flag is one of two literal column names chosen above.
		if ( $exists ) {
			do_action( 'wpeasycart_taxrate_deleting', $id );
			$wpdb->query( $wpdb->prepare( 'DELETE FROM ec_taxrate WHERE taxrate_id = %d', $id ) );
			do_action( 'wpeasycart_taxrate_deleted', $id );
		}
		$remaining = (int) $wpdb->get_var( "SELECT COUNT(*) FROM ec_taxrate WHERE {$flag} = 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $flag is one of two literal column names chosen above.
		wp_send_json_success( array( 'remaining' => $remaining, 'message' => __( 'Deleted.', 'wp-easycart' ) ) );
	}

	/* ------------------------------------------------------------------ */
	/* AJAX: VAT rate per country ( ec_country )                           */
	/* ------------------------------------------------------------------ */

	/** POST country_id, rate, b2b → { id, name, rate, b2b }. Same write as the classic save_vat_country_tax_rate. */
	public static function ajax_vat_country_update() {
		ecv2_settings_guard();
		global $wpdb;
		$country_id = (int) self::post_text( 'country_id' );
		$rate       = self::post_rate();
		$b2b        = ( '1' === self::post_text( 'b2b' ) ) ? 1 : 0;
		if ( is_wp_error( $rate ) ) {
			wp_send_json_error( array( 'message' => $rate->get_error_message() ) );
		}
		$country = $wpdb->get_row( $wpdb->prepare( 'SELECT id_cnt, name_cnt FROM ec_country WHERE id_cnt = %d', $country_id ) );
		if ( ! $country ) {
			wp_send_json_error( array( 'message' => __( 'Choose a country.', 'wp-easycart' ) ) );
		}
		$wpdb->query( $wpdb->prepare( 'UPDATE ec_country SET vat_rate_cnt = %f, vat_b2b_enabled = %d WHERE id_cnt = %d', $rate, $b2b, $country_id ) );
		wp_send_json_success( array( 'id' => $country_id, 'name' => $country->name_cnt, 'rate' => $rate, 'b2b' => $b2b, 'message' => __( 'Saved.', 'wp-easycart' ) ) );
	}

	/** POST country_id → clears the rate ( the classic delete set rate and b2b to 0 ). */
	public static function ajax_vat_country_delete() {
		ecv2_settings_guard();
		global $wpdb;
		$country_id = (int) self::post_text( 'country_id' );
		$wpdb->query( $wpdb->prepare( 'UPDATE ec_country SET vat_rate_cnt = 0, vat_b2b_enabled = 0 WHERE id_cnt = %d', $country_id ) );
		$remaining = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_country WHERE vat_rate_cnt > 0' );
		wp_send_json_success( array( 'remaining' => $remaining, 'message' => __( 'Deleted.', 'wp-easycart' ) ) );
	}

	/* ------------------------------------------------------------------ */
	/* AJAX: Canada grid ( ec_option_canada_tax_options )                  */
	/* ------------------------------------------------------------------ */

	/**
	 * POST province, role, field ( collect | gst | pst | hst ), value.
	 * collect: 1 sets ec_option_collect_<province>_tax_<role> = 1, 0 removes it
	 * ( the classic grid only stored ticked keys ). Rates arrive as percentages
	 * and are stored as fractions ( 5 → .05 ), which is what ec_tax multiplies
	 * back by 100.
	 */
	public static function ajax_canada_update() {
		ecv2_settings_guard();
		$province = sanitize_key( self::post_text( 'province' ) );
		$role     = self::post_text( 'role' );
		$field    = sanitize_key( self::post_text( 'field' ) );
		$provinces = self::provinces();
		if ( ! isset( $provinces[ $province ] ) || ! in_array( $role, self::roles(), true ) || ! in_array( $field, array( 'collect', 'gst', 'pst', 'hst' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown province, role or column.', 'wp-easycart' ) ) );
		}
		$options = self::canada_options();
		if ( 'collect' === $field ) {
			$on  = ( '1' === self::post_text( 'value' ) );
			$key = 'ec_option_collect_' . $province . '_tax_' . $role;
			if ( $on ) {
				$options[ $key ] = 1;
				foreach ( array( 'gst', 'pst', 'hst' ) as $type ) {
					$rate_key = 'ec_option_' . $province . '_tax_' . $role . '_' . $type;
					if ( ! isset( $options[ $rate_key ] ) ) {
						$options[ $rate_key ] = $provinces[ $province ][ $type ];
					}
				}
			} else {
				unset( $options[ $key ] );
			}
			$stored = $on ? 1 : 0;
		} else {
			$rate = self::post_rate( 'value' );
			if ( is_wp_error( $rate ) ) {
				wp_send_json_error( array( 'message' => $rate->get_error_message() ) );
			}
			$stored = round( $rate / 100, 5 );
			$options[ 'ec_option_' . $province . '_tax_' . $role . '_' . $field ] = $stored;
		}
		update_option( 'ec_option_canada_tax_options', $options );
		wp_send_json_success( array( 'value' => $stored, 'message' => __( 'Saved.', 'wp-easycart' ) ) );
	}

	public static function hooks() {
		add_action( 'wp_ajax_ecv2_tax_rate_add', array( __CLASS__, 'ajax_rate_add' ) );
		add_action( 'wp_ajax_ecv2_tax_rate_update', array( __CLASS__, 'ajax_rate_update' ) );
		add_action( 'wp_ajax_ecv2_tax_rate_delete', array( __CLASS__, 'ajax_rate_delete' ) );
		add_action( 'wp_ajax_ecv2_tax_vat_country_update', array( __CLASS__, 'ajax_vat_country_update' ) );
		add_action( 'wp_ajax_ecv2_tax_vat_country_delete', array( __CLASS__, 'ajax_vat_country_delete' ) );
		add_action( 'wp_ajax_ecv2_tax_canada_update', array( __CLASS__, 'ajax_canada_update' ) );
	}
}

wp_easycart_admin_tax_v2::hooks();

endif;
