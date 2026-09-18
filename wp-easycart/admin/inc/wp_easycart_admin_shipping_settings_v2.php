<?php
/**
 * WP EasyCart Admin — Settings › Shipping settings ( V2 ) zone editor.
 *
 * Backs the "Shipping zones" section of admin/template/settings/shipping-settings.php:
 * the section render prints the editor shell, the page 'enqueue' localizes the
 * data ( zones, zone locations, countries, regions ) for admin/js/settings-shipping-v2.js,
 * and the AJAX handlers below change the same tables the legacy editable tables did
 * ( ec_zone, ec_zone_to_location ) and fire the same hooks
 * ( wpeasycart_zone_added / _updated / _deleting / _deleted,
 *   wpeasycart_zone_location_added / _deleting / _deleted ).
 *
 * A zone location is ( iso2_cnt, code_sta ): '' / '' means everywhere, an iso2 with
 * '' means the whole country, an iso2 with a code means one region of that country.
 *
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_shipping_settings_v2' ) ) :

class wp_easycart_admin_shipping_settings_v2 {

	/* ------------------------------------------------------------------ */
	/* Data                                                                */
	/* ------------------------------------------------------------------ */

	/** Countries as id => array( id, iso2, name ), in display order. */
	public static function countries() {
		global $wpdb;
		$out = array();
		$rows = $wpdb->get_results( 'SELECT id_cnt, iso2_cnt, name_cnt FROM ec_country ORDER BY sort_order ASC, name_cnt ASC' );
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row->id_cnt ] = array( 'id' => (int) $row->id_cnt, 'iso2' => (string) $row->iso2_cnt, 'name' => wp_unslash( (string) $row->name_cnt ) );
		}
		return $out;
	}

	/** Regions as a flat list of array( id, cnt ( country id ), code, name ). */
	public static function states() {
		global $wpdb;
		$out = array();
		$rows = $wpdb->get_results( 'SELECT id_sta, idcnt_sta, code_sta, name_sta FROM ec_state ORDER BY sort_order ASC, name_sta ASC' );
		foreach ( (array) $rows as $row ) {
			$out[] = array( 'id' => (int) $row->id_sta, 'cnt' => (int) $row->idcnt_sta, 'code' => (string) $row->code_sta, 'name' => wp_unslash( (string) $row->name_sta ) );
		}
		return $out;
	}

	/** Zones as a list of array( id, name ). */
	public static function zones() {
		global $wpdb;
		$out = array();
		$rows = $wpdb->get_results( 'SELECT zone_id, zone_name FROM ec_zone ORDER BY zone_name ASC' );
		foreach ( (array) $rows as $row ) {
			$out[] = array( 'id' => (int) $row->zone_id, 'name' => wp_unslash( (string) $row->zone_name ) );
		}
		return $out;
	}

	/** Zone locations as a list of array( id, zone_id, iso2, code, label ). */
	public static function items() {
		global $wpdb;
		$out = array();
		$rows = $wpdb->get_results( 'SELECT zone_to_location_id, zone_id, iso2_cnt, code_sta FROM ec_zone_to_location ORDER BY zone_id ASC, iso2_cnt ASC, code_sta ASC' );
		foreach ( (array) $rows as $row ) {
			$out[] = self::item_row( $row );
		}
		return $out;
	}

	/** Shape one ec_zone_to_location row for the editor, with its human label. */
	private static function item_row( $row ) {
		return array(
			'id'      => (int) $row->zone_to_location_id,
			'zone_id' => (int) $row->zone_id,
			'iso2'    => (string) $row->iso2_cnt,
			'code'    => (string) $row->code_sta,
			'label'   => self::item_label( (string) $row->iso2_cnt, (string) $row->code_sta ),
		);
	}

	/** "All countries", "France" or "United States › Ohio". */
	public static function item_label( $iso2, $code ) {
		global $wpdb;
		if ( '' === $iso2 ) {
			return __( 'All countries', 'wp-easycart' );
		}
		$country = $wpdb->get_row( $wpdb->prepare( 'SELECT id_cnt, name_cnt FROM ec_country WHERE iso2_cnt = %s LIMIT 1', $iso2 ) );
		if ( ! $country ) {
			return $iso2 . ( '' !== $code ? ' › ' . $code : '' );
		}
		$name = wp_unslash( (string) $country->name_cnt );
		if ( '' === $code ) {
			return $name;
		}
		$state = $wpdb->get_var( $wpdb->prepare( 'SELECT name_sta FROM ec_state WHERE idcnt_sta = %d AND code_sta = %s LIMIT 1', (int) $country->id_cnt, $code ) );
		return $name . ' › ' . ( $state ? wp_unslash( (string) $state ) : $code );
	}

	/** Everything the editor script needs. */
	public static function localize() {
		return array(
			'zones'     => self::zones(),
			'items'     => self::items(),
			'countries' => array_values( self::countries() ),
			'states'    => self::states(),
			'i18n'      => array(
				'all_countries'  => __( 'All countries', 'wp-easycart' ),
				'whole_country'  => __( 'Whole country', 'wp-easycart' ),
				'add'            => __( 'Add', 'wp-easycart' ),
				'add_place'      => __( 'Add country or region', 'wp-easycart' ),
				'delete'         => __( 'Delete', 'wp-easycart' ),
				'remove'         => __( 'Remove', 'wp-easycart' ),
				'rename_hint'    => __( 'Click the name to rename', 'wp-easycart' ),
				'no_places'      => __( 'No countries or regions yet — this zone matches nothing until you add one.', 'wp-easycart' ),
				'places_one'     => __( '1 place', 'wp-easycart' ),
				/* translators: %d: number of countries and regions in a shipping zone. */
				'places_many'    => __( '%d places', 'wp-easycart' ),
				'name_required'  => __( 'Give the zone a name first.', 'wp-easycart' ),
				'confirm_delete' => __( 'Delete this zone?', 'wp-easycart' ),
				'confirm_text'   => __( 'Rates assigned to it on the Shipping rates page will no longer match any destination.', 'wp-easycart' ),
				'saved'          => __( 'Saved', 'wp-easycart' ),
				'failed'         => __( 'Could not save. Retry', 'wp-easycart' ),
				'already'        => __( 'That place is already in this zone.', 'wp-easycart' ),
				/* translators: %d: number of further countries or regions not listed in a collapsed zone's preview. */
				'more'           => __( '+%d more', 'wp-easycart' ),
				'no_places_yet'  => __( 'No countries or regions yet', 'wp-easycart' ),
				/* translators: %s: shipping zone name. */
				'show_places'    => __( 'Show places in %s', 'wp-easycart' ),
				/* translators: %s: shipping zone name. */
				'hide_places'    => __( 'Hide places in %s', 'wp-easycart' ),
			),
		);
	}

	/* ------------------------------------------------------------------ */
	/* Render                                                              */
	/* ------------------------------------------------------------------ */

	/** Section render: the editor shell; the script fills it from the localized data. */
	public static function render_zones( $page, $section ) {
		?>
		<div class="ecsh-zones" id="ecsh_zones">
			<div class="ecsh-zone-add">
				<input type="text" class="ecv2-input" id="ecsh_zone_name" placeholder="<?php esc_attr_e( 'New zone name, e.g. Europe', 'wp-easycart' ); ?>" autocomplete="off" />
				<button type="button" class="ecv2-btn ecv2-btn-primary" id="ecsh_zone_add"><?php esc_html_e( 'Add zone', 'wp-easycart' ); ?></button>
				<span class="ecsh-zone-add-msg" id="ecsh_zone_add_msg" hidden></span>
			</div>
			<div class="ecsh-zone-tools" id="ecsh_zone_tools" hidden>
				<button type="button" class="ecst-link ecsh-zone-all" data-open="1" aria-controls="ecsh_zone_list"><?php esc_html_e( 'Expand all', 'wp-easycart' ); ?></button>
				<span class="ecsh-zone-tools-sep" aria-hidden="true">·</span>
				<button type="button" class="ecst-link ecsh-zone-all" data-open="0" aria-controls="ecsh_zone_list"><?php esc_html_e( 'Collapse all', 'wp-easycart' ); ?></button>
			</div>
			<div class="ecsh-zone-list" id="ecsh_zone_list"></div>
			<div class="ecsh-empty" id="ecsh_zone_empty" hidden><?php esc_html_e( 'No zones yet. Zones let a rate table charge different amounts by destination; without any, every rate applies everywhere you ship.', 'wp-easycart' ); ?></div>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* AJAX                                                                */
	/* ------------------------------------------------------------------ */

	/** POST zone_name → { zone: { id, name } }. */
	public static function ajax_zone_add() {
		global $wpdb;
		ecv2_settings_guard();
		$name = isset( $_POST['zone_name'] ) ? sanitize_text_field( wp_unslash( $_POST['zone_name'] ) ) : '';
		if ( '' === $name ) {
			wp_send_json_error( array( 'message' => __( 'Give the zone a name first.', 'wp-easycart' ) ) );
		}
		$wpdb->insert( 'ec_zone', array( 'zone_name' => $name ), array( '%s' ) );
		$zone_id = (int) $wpdb->insert_id;
		if ( $zone_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Could not create the zone.', 'wp-easycart' ) ) );
		}
		do_action( 'wpeasycart_zone_added', $zone_id );
		wp_cache_flush();
		wp_send_json_success( array( 'zone' => array( 'id' => $zone_id, 'name' => $name ) ) );
	}

	/** POST id, zone_name → { zone: { id, name } }. */
	public static function ajax_zone_rename() {
		global $wpdb;
		ecv2_settings_guard();
		$zone_id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$name    = isset( $_POST['zone_name'] ) ? sanitize_text_field( wp_unslash( $_POST['zone_name'] ) ) : '';
		if ( $zone_id <= 0 || '' === $name ) {
			wp_send_json_error( array( 'message' => __( 'Give the zone a name first.', 'wp-easycart' ) ) );
		}
		$wpdb->update( 'ec_zone', array( 'zone_name' => $name ), array( 'zone_id' => $zone_id ), array( '%s' ), array( '%d' ) );
		do_action( 'wpeasycart_zone_updated', $zone_id );
		wp_cache_flush();
		wp_send_json_success( array( 'zone' => array( 'id' => $zone_id, 'name' => $name ) ) );
	}

	/** POST id → deletes the zone and its locations ( same hook order as the legacy delete_zone() ). */
	public static function ajax_zone_delete() {
		global $wpdb;
		ecv2_settings_guard();
		$zone_id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		if ( $zone_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Unknown zone.', 'wp-easycart' ) ) );
		}
		do_action( 'wpeasycart_zone_deleting', $zone_id );
		$wpdb->delete( 'ec_zone', array( 'zone_id' => $zone_id ), array( '%d' ) );
		$locations = $wpdb->get_results( $wpdb->prepare( 'SELECT zone_to_location_id FROM ec_zone_to_location WHERE zone_id = %d', $zone_id ) );
		foreach ( (array) $locations as $location ) {
			do_action( 'wpeasycart_zone_location_deleting', (int) $location->zone_to_location_id );
		}
		$wpdb->delete( 'ec_zone_to_location', array( 'zone_id' => $zone_id ), array( '%d' ) );
		foreach ( (array) $locations as $location ) {
			do_action( 'wpeasycart_zone_location_deleted', (int) $location->zone_to_location_id );
		}
		do_action( 'wpeasycart_zone_deleted', $zone_id );
		wp_cache_flush();
		wp_send_json_success( array( 'id' => $zone_id ) );
	}

	/** POST zone_id, iso2_cnt ( '' = all countries ), id_sta ( 0 = whole country ) → { item }. */
	public static function ajax_zone_item_add() {
		global $wpdb;
		ecv2_settings_guard();
		$zone_id = isset( $_POST['zone_id'] ) ? (int) $_POST['zone_id'] : 0;
		$iso2    = isset( $_POST['iso2_cnt'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['iso2_cnt'] ) ) ) : '';
		$id_sta  = isset( $_POST['id_sta'] ) ? (int) $_POST['id_sta'] : 0;
		if ( $zone_id <= 0 || ! $wpdb->get_var( $wpdb->prepare( 'SELECT zone_id FROM ec_zone WHERE zone_id = %d', $zone_id ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown zone.', 'wp-easycart' ) ) );
		}
		$code = '';
		if ( '' !== $iso2 ) {
			$country_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id_cnt FROM ec_country WHERE iso2_cnt = %s LIMIT 1', $iso2 ) );
			if ( $country_id <= 0 ) {
				wp_send_json_error( array( 'message' => __( 'Unknown country.', 'wp-easycart' ) ) );
			}
			if ( $id_sta > 0 ) {
				$code = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT code_sta FROM ec_state WHERE id_sta = %d AND idcnt_sta = %d LIMIT 1', $id_sta, $country_id ) );
				if ( '' === $code ) {
					wp_send_json_error( array( 'message' => __( 'That region does not belong to the chosen country.', 'wp-easycart' ) ) );
				}
			}
		}
		$exists = $wpdb->get_var( $wpdb->prepare( 'SELECT zone_to_location_id FROM ec_zone_to_location WHERE zone_id = %d AND iso2_cnt = %s AND code_sta = %s LIMIT 1', $zone_id, $iso2, $code ) );
		if ( $exists ) {
			wp_send_json_error( array( 'message' => __( 'That place is already in this zone.', 'wp-easycart' ) ) );
		}
		$wpdb->insert( 'ec_zone_to_location', array( 'zone_id' => $zone_id, 'iso2_cnt' => $iso2, 'code_sta' => $code ), array( '%d', '%s', '%s' ) );
		$item_id = (int) $wpdb->insert_id;
		if ( $item_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Could not add that place.', 'wp-easycart' ) ) );
		}
		do_action( 'wpeasycart_zone_location_added', $item_id );
		wp_cache_flush();
		wp_send_json_success( array( 'item' => array( 'id' => $item_id, 'zone_id' => $zone_id, 'iso2' => $iso2, 'code' => $code, 'label' => self::item_label( $iso2, $code ) ) ) );
	}

	/** POST id → removes one zone location. */
	public static function ajax_zone_item_delete() {
		global $wpdb;
		ecv2_settings_guard();
		$item_id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		if ( $item_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Unknown zone location.', 'wp-easycart' ) ) );
		}
		do_action( 'wpeasycart_zone_location_deleting', $item_id );
		$wpdb->delete( 'ec_zone_to_location', array( 'zone_to_location_id' => $item_id ), array( '%d' ) );
		do_action( 'wpeasycart_zone_location_deleted', $item_id );
		wp_cache_flush();
		wp_send_json_success( array( 'id' => $item_id ) );
	}

	/**
	 * Filter wp_easycart_settings_save_reply: after a carrier setting saves, send that carrier's fresh connection status
	 * so the sub-header chip and note update without a reload. The probe already ran once in the field's validate
	 * callback and is cached for the request, so this adds no extra HTTP call.
	 *
	 * @since 6.0.0
	 * @param array  $reply      Save reply.
	 * @param string $slug       Settings page slug.
	 * @param array  $saved_keys Saved keys.
	 * @return array
	 */
	public static function save_reply_carriers( $reply, $slug, $saved_keys ) {
		if ( 'shipping-settings' !== $slug || empty( $saved_keys ) || ! function_exists( 'ecv2_shipping_carrier_status_detail' ) || ! class_exists( 'wp_easycart_admin_settings_registry' ) ) {
			return $reply;
		}
		$carriers = array();
		foreach ( $saved_keys as $key ) {
			$field = wp_easycart_admin_settings_registry::field( $slug, $key );
			if ( $field && ! empty( $field['carrier'] ) ) {
				$carriers[ sanitize_key( $field['carrier'] ) ] = true;
			}
		}
		foreach ( array_keys( $carriers ) as $carrier ) {
			$reply['carriers'][ $carrier ] = ecv2_shipping_carrier_status_detail( $carrier, true );
		}
		/* Details a carrier's "Enable" row cleared when it was turned off: the page empties those fields. */
		if ( function_exists( 'ecv2_shipping_reset_queue' ) && ecv2_shipping_reset_queue() ) {
			$reply['reset'] = ecv2_shipping_reset_queue();
		}
		return $reply;
	}
}

add_filter( 'wp_easycart_settings_save_reply', array( 'wp_easycart_admin_shipping_settings_v2', 'save_reply_carriers' ), 10, 3 );

add_action( 'wp_ajax_ecv2_shipping_zone_add', array( 'wp_easycart_admin_shipping_settings_v2', 'ajax_zone_add' ) );
add_action( 'wp_ajax_ecv2_shipping_zone_rename', array( 'wp_easycart_admin_shipping_settings_v2', 'ajax_zone_rename' ) );
add_action( 'wp_ajax_ecv2_shipping_zone_delete', array( 'wp_easycart_admin_shipping_settings_v2', 'ajax_zone_delete' ) );
add_action( 'wp_ajax_ecv2_shipping_zone_item_add', array( 'wp_easycart_admin_shipping_settings_v2', 'ajax_zone_item_add' ) );
add_action( 'wp_ajax_ecv2_shipping_zone_item_delete', array( 'wp_easycart_admin_shipping_settings_v2', 'ajax_zone_item_delete' ) );

endif;
