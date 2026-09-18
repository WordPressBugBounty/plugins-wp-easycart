<?php
/**
 * Settings › Shipping settings ( V2 declaration ).
 *
 * Replaces the classic "shipping-settings" subpage ( shipping-basic-options.php,
 * packing-slip.php, shipping-zones.php, country-list.php, state-list.php plus the
 * PRO carrier partials hooked to 'wpeasycart_admin_shipping_setup' ). The shipping
 * method chooser and the Fraktjakt account / ship-from fields come from the rates
 * page; the rate tables themselves stay on subpage=shipping-rates.
 *
 * Storage notes:
 * - Most rows are plain wp_options and save through the engine.
 * - The shipping method, handling / expedited amounts, every fraktjakt_* value and
 *   the carrier credential columns live in the single-row ec_setting table. Their
 *   rows read from that table via 'default' ( ecv2_shipping_setting() ) and write
 *   back in on_save ( ecv2_shipping_write_setting() ), which also removes the shadow
 *   option the engine stores so ec_setting stays the one source. Each such row carries
 *   'store' => 'setting'; the UPS origin address rows ( one serialized option ) carry
 *   'store' => 'ups_settings'.
 * - Shipping zones are edited by the V2 zone editor ( admin/inc/wp_easycart_admin_shipping_settings_v2.php,
 *   admin/js/settings-shipping-v2.js ) through its own guarded AJAX handlers.
 * - Ship-to countries and regions are managed on Settings › Countries & Regions; this
 *   page only links there.
 * - Carrier accounts ( Australia Post, Canada Post, DHL, FedEx, UPS, USPS ) are PRO rows:
 *   declared here so the free plugin shows them locked, given their save side effects,
 *   connection checks and the UPS connect buttons by wp-easycart-pro/admin/template/settings/shipping-settings.php
 *   through the page filter. Every carrier row carries 'carrier' and 'store' so PRO can
 *   attach behavior without repeating the field list.
 *
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* The zone editor controller is included by admin/admin-init.php; this covers any other route that reads the declarations. */
if ( function_exists( 'add_action' ) && ! class_exists( 'wp_easycart_admin_shipping_settings_v2' ) && file_exists( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_shipping_settings_v2.php' ) ) {
	require_once EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_shipping_settings_v2.php';
}

/* ---------------------------------------------------------------------- */
/* ec_setting access                                                       */
/* ---------------------------------------------------------------------- */

if ( ! function_exists( 'ecv2_shipping_setting_row' ) ) {
	/**
	 * The ec_setting row, read once per request. False when $wpdb is not available
	 * ( the migration-map generator includes this file outside WP ).
	 */
	function ecv2_shipping_setting_row( $refresh = false ) {
		static $row = null;
		if ( $refresh ) {
			$row = null;
		}
		if ( null !== $row ) {
			return $row;
		}
		$row = false;
		if ( isset( $GLOBALS['wpdb'] ) && is_object( $GLOBALS['wpdb'] ) && method_exists( $GLOBALS['wpdb'], 'get_row' ) ) {
			$found = $GLOBALS['wpdb']->get_row( 'SELECT * FROM ec_setting' );
			if ( $found ) {
				$row = $found;
			}
		}
		return $row;
	}
}

if ( ! function_exists( 'ecv2_shipping_setting' ) ) {
	/** One ec_setting column, or $fallback when the row cannot be read. */
	function ecv2_shipping_setting( $column, $fallback = '' ) {
		$row = ecv2_shipping_setting_row();
		if ( $row && isset( $row->{$column} ) && null !== $row->{$column} ) {
			return $row->{$column};
		}
		return $fallback;
	}
}

if ( ! function_exists( 'ecv2_shipping_setting_columns' ) ) {
	/** ec_setting columns a row on this page may write, with their wpdb format. */
	function ecv2_shipping_setting_columns() {
		return array(
			'shipping_method'            => '%s',
			'shipping_handling_rate'     => '%f',
			'shipping_expedite_rate'     => '%f',
			'fraktjakt_customer_id'      => '%s',
			'fraktjakt_login_key'        => '%s',
			'fraktjakt_conversion_rate'  => '%f',
			'fraktjakt_test_mode'        => '%d',
			'fraktjakt_address'          => '%s',
			'fraktjakt_city'             => '%s',
			'fraktjakt_state'            => '%s',
			'fraktjakt_zip'              => '%s',
			'fraktjakt_country'          => '%s',
			'auspost_api_key'            => '%s',
			'auspost_ship_from_zip'      => '%s',
			'canadapost_username'        => '%s',
			'canadapost_password'        => '%s',
			'canadapost_customer_number' => '%s',
			'canadapost_contract_id'     => '%s',
			'canadapost_ship_from_zip'   => '%s',
			'canadapost_test_mode'       => '%d',
			'dhl_site_id'                => '%s',
			'dhl_password'               => '%s',
			'dhl_ship_from_zip'          => '%s',
			'dhl_ship_from_country'      => '%s',
			'dhl_weight_unit'            => '%s',
			'dhl_test_mode'              => '%d',
			'fedex_account_number'       => '%s',
			'fedex_ship_from_zip'        => '%s',
			'fedex_country_code'         => '%s',
			'fedex_weight_units'         => '%s',
			'fedex_conversion_rate'      => '%f',
			'fedex_test_account'         => '%d',
			'ups_access_license_number'  => '%s',
			'ups_user_id'                => '%s',
			'ups_password'               => '%s',
			'ups_shipper_number'         => '%s',
			'ups_weight_type'            => '%s',
			'ups_ship_from_zip'          => '%s',
			'ups_country_code'           => '%s',
			'ups_conversion_rate'        => '%f',
			'ups_negotiated_rates'       => '%d',
			'usps_user_name'             => '%s',
			'usps_ship_from_zip'         => '%s',
		);
	}
}

if ( ! function_exists( 'ecv2_shipping_write_setting' ) ) {
	/**
	 * Write one whitelisted column of the ec_setting row ( keyed by setting_id, so the
	 * query is built by $wpdb->update — no interpolated SQL ). Returns false for an
	 * unknown column or a missing row.
	 */
	function ecv2_shipping_write_setting( $column, $value ) {
		global $wpdb;
		$columns = ecv2_shipping_setting_columns();
		$row     = ecv2_shipping_setting_row();
		if ( ! isset( $columns[ $column ] ) || ! $row || ! isset( $row->setting_id ) ) {
			return false;
		}
		$format = $columns[ $column ];
		if ( '%d' === $format ) {
			$value = (int) $value;
		} elseif ( '%f' === $format ) {
			$value = (float) $value;
		} else {
			$value = (string) $value;
		}
		$wpdb->update( 'ec_setting', array( $column => $value ), array( 'setting_id' => (int) $row->setting_id ), array( $format ), array( '%d' ) );
		return true;
	}
}

if ( ! function_exists( 'ecv2_shipping_flush_cache' ) ) {
	/** Legacy save_shipping_settings() cleared the settings cache after every save. */
	function ecv2_shipping_flush_cache( $value = null, $old = null, $field = null ) {
		wp_cache_delete( 'wpeasycart-settings', 'wpeasycart-settings' );
		ecv2_shipping_setting_row( true );
	}
}

if ( ! function_exists( 'ecv2_shipping_on_save_setting' ) ) {
	/**
	 * on_save for every ec_setting-backed row: write the column, drop the shadow
	 * option the engine just stored, clear the settings cache.
	 */
	function ecv2_shipping_on_save_setting( $value, $old, $field ) {
		ecv2_shipping_write_setting( $field['key'], $value );
		delete_option( $field['key'] );
		ecv2_shipping_flush_cache();
	}
}

if ( ! function_exists( 'ecv2_shipping_on_save_method' ) ) {
	/** Ports wp_easycart_admin_shipping::update_shipping_method() ( rates page chooser ). */
	function ecv2_shipping_on_save_method( $value, $old, $field ) {
		ecv2_shipping_write_setting( 'shipping_method', $value );
		delete_option( $field['key'] );
		wp_cache_delete( 'wpeasycart-config-get-shipping-method', 'wpeasycart-settings' );
		wp_cache_delete( 'wpeasycart-shipping-data', 'wpeasycart-shipping' );
		ecv2_shipping_flush_cache();
	}
}

if ( ! function_exists( 'ecv2_shipping_ups_settings' ) ) {
	/** The UPS origin address option ( one serialized array ), always with every key. */
	function ecv2_shipping_ups_settings() {
		$defaults = array( 'address1' => '', 'address2' => '', 'address3' => '', 'city' => '', 'state' => '' );
		$stored   = get_option( 'ec_option_ups_settings' );
		return is_array( $stored ) ? array_merge( $defaults, $stored ) : $defaults;
	}
}

if ( ! function_exists( 'ecv2_shipping_ups_setting' ) ) {
	/** One key of the UPS origin address option. */
	function ecv2_shipping_ups_setting( $key ) {
		$settings = ecv2_shipping_ups_settings();
		return isset( $settings[ $key ] ) ? (string) $settings[ $key ] : '';
	}
}

if ( ! function_exists( 'ecv2_shipping_on_save_ups_setting' ) ) {
	/** on_save for the UPS origin address rows ( ups_ship_from_address1 … ups_ship_from_state ). */
	function ecv2_shipping_on_save_ups_setting( $value, $old, $field ) {
		$key = str_replace( 'ups_ship_from_', '', $field['key'] );
		$settings = ecv2_shipping_ups_settings();
		if ( isset( $settings[ $key ] ) ) {
			$settings[ $key ] = trim( (string) $value );
			update_option( 'ec_option_ups_settings', $settings );
		}
		delete_option( $field['key'] );
		ecv2_shipping_flush_cache();
	}
}

if ( ! function_exists( 'ecv2_shipping_carrier_details' ) ) {
	/**
	 * The saved details of a carrier that has no on/off setting of its own ( Australia Post, Canada Post, DHL ):
	 * key => array( store, value when cleared ). Its "Enable" row is on while any of these are filled, and turning
	 * it off clears them, which is what stops the carrier quoting ( ec_live_shipping checks these values ).
	 *
	 * @since 6.0.0
	 * @param string $carrier Carrier key.
	 * @return array
	 */
	function ecv2_shipping_carrier_details( $carrier ) {
		$map = array(
			'auspost'    => array(
				'auspost_api_key'       => array( 'setting', '' ),
				'auspost_ship_from_zip' => array( 'setting', '' ),
			),
			'canadapost' => array(
				'canadapost_username'        => array( 'setting', '' ),
				'canadapost_password'        => array( 'setting', '' ),
				'canadapost_customer_number' => array( 'setting', '' ),
				'canadapost_contract_id'     => array( 'setting', '' ),
				'canadapost_ship_from_zip'   => array( 'setting', '' ),
				'canadapost_test_mode'       => array( 'setting', '0' ),
			),
			'dhl'        => array(
				'dhl_site_id'                  => array( 'setting', '' ),
				'dhl_password'                 => array( 'setting', '' ),
				'ec_option_dhl_account_number' => array( 'option', '' ),
				'dhl_ship_from_zip'            => array( 'setting', '' ),
				'dhl_ship_from_country'        => array( 'setting', '' ),
				'dhl_weight_unit'              => array( 'setting', '' ),
				'dhl_test_mode'                => array( 'setting', '0' ),
			),
		);
		return isset( $map[ $carrier ] ) ? $map[ $carrier ] : array();
	}
}

if ( ! function_exists( 'ecv2_shipping_carrier_in_use' ) ) {
	/** Default of a carrier's "Enable" row on stores that never saved it: on when any credential or origin detail is filled. @since 6.0.0 */
	function ecv2_shipping_carrier_in_use( $carrier ) {
		foreach ( ecv2_shipping_carrier_details( $carrier ) as $key => $detail ) {
			if ( '0' === $detail[1] ) {
				continue; // Test mode alone does not make a carrier set up.
			}
			$value = ( 'option' === $detail[0] ) ? ( function_exists( 'get_option' ) ? get_option( $key, '' ) : '' ) : ecv2_shipping_setting( $key, '' );
			if ( '' !== trim( (string) $value ) ) {
				return 1;
			}
		}
		return 0;
	}
}

if ( ! function_exists( 'ecv2_shipping_reset_queue' ) ) {
	/**
	 * Settings a save changed as a side effect, returned to the page as reply['reset'] so it redraws them.
	 *
	 * @since 6.0.0
	 * @param array|null $add key => value to add.
	 * @return array
	 */
	function ecv2_shipping_reset_queue( $add = null ) {
		static $queue = array();
		if ( is_array( $add ) ) {
			$queue = array_merge( $queue, $add );
		}
		return $queue;
	}
}

if ( ! function_exists( 'ecv2_shipping_on_save_carrier_enable' ) ) {
	/** on_save for the Australia Post, Canada Post and DHL "Enable" rows: turning one off clears that carrier's details. @since 6.0.0 */
	function ecv2_shipping_on_save_carrier_enable( $value, $old, $field ) {
		if ( (int) $value ) {
			return;
		}
		$carrier = isset( $field['carrier'] ) ? sanitize_key( $field['carrier'] ) : '';
		$reset   = array();
		foreach ( ecv2_shipping_carrier_details( $carrier ) as $key => $detail ) {
			if ( 'option' === $detail[0] ) {
				update_option( $key, $detail[1] );
			} else {
				ecv2_shipping_write_setting( $key, $detail[1] );
				delete_option( $key );
			}
			$reset[ $key ] = $detail[1];
		}
		ecv2_shipping_reset_queue( $reset );
		ecv2_shipping_flush_cache();
	}
}

if ( ! function_exists( 'ecv2_shipping_sanitize_state_code' ) ) {
	/** Carriers want a bare 2-character state / province code, or nothing. */
	function ecv2_shipping_sanitize_state_code( $raw, $field ) {
		$code = strtoupper( sanitize_text_field( (string) $raw ) );
		$code = preg_replace( '/[^A-Z0-9]/', '', $code );
		return substr( $code, 0, 2 );
	}
}

/* ---------------------------------------------------------------------- */
/* Option lists                                                            */
/* ---------------------------------------------------------------------- */

if ( ! function_exists( 'ecv2_shipping_country_options' ) ) {
	/**
	 * iso2 => name for the origin-country selects, the way the legacy carrier partials build
	 * theirs. Rows declare it behind an 'options' closure, so the table is read once per
	 * request and only when the page renders or one of those rows saves.
	 */
	function ecv2_shipping_country_options( $empty_label = '' ) {
		static $rows = null;
		if ( null === $rows ) {
			$rows = array();
			if ( isset( $GLOBALS['wpdb'] ) && is_object( $GLOBALS['wpdb'] ) && method_exists( $GLOBALS['wpdb'], 'get_results' ) ) {
				$found = $GLOBALS['wpdb']->get_results( 'SELECT iso2_cnt, name_cnt FROM ec_country ORDER BY sort_order ASC' );
				if ( is_array( $found ) ) {
					$rows = $found;
				}
			}
		}
		$options = array( '' => ( '' !== $empty_label ) ? $empty_label : __( 'Select a country', 'wp-easycart' ) );
		foreach ( $rows as $row ) {
			$options[ (string) $row->iso2_cnt ] = (string) $row->name_cnt;
		}
		return $options;
	}
}

if ( ! function_exists( 'ecv2_shipping_method_options' ) ) {
	/**
	 * The shipping systems the free plugin can calculate. PRO adds 'fraktjakt' and
	 * 'live' through the page filter; when one of those is stored but PRO is not
	 * active the current value is kept selectable so the control never lies.
	 */
	function ecv2_shipping_method_options() {
		$options = array(
			'method'     => __( 'Static methods ( flat rates you name )', 'wp-easycart' ),
			'price'      => __( 'By order total', 'wp-easycart' ),
			'weight'     => __( 'By order weight', 'wp-easycart' ),
			'quantity'   => __( 'By item quantity', 'wp-easycart' ),
			'percentage' => __( 'Percentage of the order total', 'wp-easycart' ),
		);
		$current = (string) ecv2_shipping_setting( 'shipping_method', 'method' );
		$plan    = class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::badge( 'pro' ) : 'Pro/Premium';
		if ( 'fraktjakt' === $current ) {
			/* translators: %s: plan name, Pro or Premium ( Pro/Premium when no license is known ). */
			$options['fraktjakt'] = sprintf( __( 'Fraktjakt ( %s )', 'wp-easycart' ), $plan );
		} elseif ( 'live' === $current ) {
			/* translators: %s: plan name, Pro or Premium ( Pro/Premium when no license is known ). */
			$options['live'] = sprintf( __( 'Live carrier rates ( %s )', 'wp-easycart' ), $plan );
		}
		return $options;
	}
}

/* ---------------------------------------------------------------------- */
/* Assets and custom rows                                                  */
/* ---------------------------------------------------------------------- */

if ( ! function_exists( 'ecv2_shipping_enqueue' ) ) {
	/** Page 'enqueue': the zone editor and carrier styling ( runs on admin_enqueue_scripts, never in render ). */
	function ecv2_shipping_enqueue( $page ) {
		$css = plugins_url( '/admin/css/', EC_PLUGIN_DIRECTORY . '/wpeasycart.php' );
		$js  = plugins_url( '/admin/js/', EC_PLUGIN_DIRECTORY . '/wpeasycart.php' );
		wp_enqueue_style( 'wp_easycart_admin_settings_shipping_v2_css', $css . 'settings-shipping-v2.css', array( 'wp_easycart_admin_settings_page_v2_css' ), EC_CURRENT_VERSION );
		wp_enqueue_script( 'wp_easycart_admin_settings_shipping_v2_js', $js . 'settings-shipping-v2.js', array( 'jquery', 'wp_easycart_admin_settings_page_v2_js' ), EC_CURRENT_VERSION, true );
		if ( class_exists( 'wp_easycart_admin_shipping_settings_v2' ) ) {
			wp_localize_script( 'wp_easycart_admin_settings_shipping_v2_js', 'ecv2_shipping_vars', wp_easycart_admin_shipping_settings_v2::localize() );
		}
	}
}

if ( ! function_exists( 'ecv2_shipping_render_zones' ) ) {
	/** Section render: the V2 zone editor ( data comes from the localized script; see the controller ). */
	function ecv2_shipping_render_zones( $page, $section ) {
		if ( ! class_exists( 'wp_easycart_admin_shipping_settings_v2' ) ) {
			echo '<p>' . esc_html__( 'The zone editor is not available on this install.', 'wp-easycart' ) . '</p>';
			return;
		}
		wp_easycart_admin_shipping_settings_v2::render_zones( $page, $section );
	}
}

if ( ! function_exists( 'ecv2_shipping_render_ship_to_note' ) ) {
	/** html row: ship-to lists live on Countries & Regions now; link there. */
	function ecv2_shipping_render_ship_to_note( $field, $page ) {
		$url = admin_url( 'admin.php?page=wp-easycart-settings&subpage=country' );
		echo '<div class="ecsh-note">';
		echo '<div class="ecsh-note-text"><b>' . esc_html__( 'Managed under Countries & Regions', 'wp-easycart' ) . '</b>';
		echo '<span>' . esc_html__( 'Turn shipping on or off for each country and region there. Anywhere that is off is refused as a shipping address at checkout; zones and tax rules still see the full list.', 'wp-easycart' ) . '</span></div>';
		echo '<a class="ecv2-btn" href="' . esc_url( $url ) . '">' . esc_html__( 'Manage ship-to countries', 'wp-easycart' ) . ' →</a>';
		echo '</div>';
	}
}

if ( ! function_exists( 'ecv2_shipping_carrier_status' ) ) {
	/**
	 * 'connected' | 'error' | 'disabled' | '' for a carrier. PRO answers through the
	 * ecv2_shipping_carrier_status filter ( its connection probes ); free has no answer.
	 */
	function ecv2_shipping_carrier_status( $carrier ) {
		$status = apply_filters( 'ecv2_shipping_carrier_status', '', $carrier );
		return in_array( $status, array( 'connected', 'error', 'incomplete', 'disabled' ), true ) ? $status : '';
	}
}

if ( ! function_exists( 'ecv2_shipping_carrier_status_detail' ) ) {
	/**
	 * array( 'status', 'label', 'note' ) for a carrier sub-header: the status plus one sentence on what to do
	 * ( "Add: Origin country." ). PRO answers through ecv2_shipping_carrier_status_detail; $refresh re-probes after a save.
	 *
	 * @since 6.0.0
	 */
	function ecv2_shipping_carrier_status_detail( $carrier, $refresh = false ) {
		$detail = apply_filters( 'ecv2_shipping_carrier_status_detail', array( 'status' => ecv2_shipping_carrier_status( $carrier ), 'note' => '' ), $carrier, $refresh );
		$status = ( is_array( $detail ) && isset( $detail['status'] ) && in_array( $detail['status'], array( 'connected', 'error', 'incomplete', 'disabled' ), true ) ) ? $detail['status'] : '';
		$labels = ecv2_shipping_carrier_status_labels();
		return array(
			'status' => $status,
			'label'  => isset( $labels[ $status ] ) ? $labels[ $status ] : '',
			'note'   => ( is_array( $detail ) && isset( $detail['note'] ) ) ? (string) $detail['note'] : '',
		);
	}
}

if ( ! function_exists( 'ecv2_shipping_carrier_status_labels' ) ) {
	/** Chip text per status. @since 6.0.0 */
	function ecv2_shipping_carrier_status_labels() {
		return array(
			'connected'  => __( 'Connected', 'wp-easycart' ),
			'error'      => __( 'Not connecting', 'wp-easycart' ),
			'incomplete' => __( 'Needs details', 'wp-easycart' ),
			'disabled'   => __( 'Not set up', 'wp-easycart' ),
		);
	}
}

if ( ! function_exists( 'ecv2_shipping_render_carrier_head' ) ) {
	/** html row: a carrier sub-header — lettermark, name, docs link and ( PRO ) connection status. */
	function ecv2_shipping_render_carrier_head( $field, $page ) {
		$carrier = isset( $field['carrier'] ) ? sanitize_key( $field['carrier'] ) : '';
		$name    = isset( $field['carrier_name'] ) ? $field['carrier_name'] : $carrier;
		$mark    = isset( $field['mark'] ) ? $field['mark'] : strtoupper( substr( $carrier, 0, 2 ) );
		$detail  = ecv2_shipping_carrier_status_detail( $carrier );
		$status  = $detail['status'];
		$docs = '';
		if ( ! empty( $field['docs'] ) && function_exists( 'wp_easycart_admin' ) && isset( wp_easycart_admin()->helpsystem ) ) {
			$docs = wp_easycart_admin()->helpsystem->print_docs_url( 'settings', 'shipping-settings', $field['docs'] );
		}
		echo '<div class="ecsh-carrier-head" data-carrier="' . esc_attr( $carrier ) . '">';
		echo '<span class="ecsh-mark ecsh-mark-' . esc_attr( $carrier ) . '" aria-hidden="true">' . esc_html( $mark ) . '</span>';
		echo '<span class="ecsh-carrier-name">' . esc_html( $name ) . '</span>';
		/* Always printed ( hidden when empty ) so settings-shipping-v2.js can update it from the save reply without a reload. */
		echo '<span class="ecsh-status is-' . esc_attr( '' !== $status ? $status : 'none' ) . '" id="ecsh_status_' . esc_attr( $carrier ) . '"' . ( '' === $status ? ' hidden' : '' ) . '>' . esc_html( $detail['label'] ) . '</span>';
		echo '<span class="ecst-grow"></span>';
		if ( '' !== $docs ) {
			echo '<a class="ecst-docs" href="' . esc_url( $docs ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Docs', 'wp-easycart' ) . ' ↗</a>';
		}
		echo '</div>';
		echo '<div class="ecsh-carrier-note is-' . esc_attr( '' !== $status ? $status : 'none' ) . '" id="ecsh_note_' . esc_attr( $carrier ) . '"' . ( '' === $detail['note'] ? ' hidden' : '' ) . '>' . esc_html( $detail['note'] ) . '</div>';
	}
}

if ( ! function_exists( 'ecv2_shipping_carrier_row' ) ) {
	/**
	 * One carrier field with the shared defaults filled in. $store says where the value
	 * lives so PRO can attach the matching on_save:
	 *   setting      ec_setting column ( key = column )
	 *   option       plain wp_option
	 *   ups_settings key of the ec_option_ups_settings array
	 *   ups_oauth | fedex_oauth | usps_flag | usps_client   options with extra legacy side effects
	 */
	function ecv2_shipping_carrier_row( $carrier, $carrier_name, $store, $args ) {
		$row = array_merge( array(
			'type'     => 'text',
			'pro'      => true,
			'carrier'  => $carrier,
			'store'    => $store,
			'keywords' => array( $carrier_name, 'live rates', 'carrier' ),
		), $args );
		if ( ! isset( $row['legacy'] ) ) {
			$row['legacy'] = array();
		}
		$row['legacy'] = array_merge( array( 'page' => 'shipping-settings', 'section' => $carrier_name . ' Setup', 'label' => $row['label'] ), $row['legacy'] );
		if ( 'setting' === $store && ! isset( $row['default'] ) ) {
			$row['default'] = ( 'toggle' === $row['type'] ) ? (int) ecv2_shipping_setting( $args['key'], 0 ) : (string) ecv2_shipping_setting( $args['key'], '' );
			$row['legacy']['note'] = 'stored in ec_setting.' . $args['key'];
		}
		if ( 'ups_settings' === $store && ! isset( $row['default'] ) ) {
			$row['default'] = ecv2_shipping_ups_setting( str_replace( 'ups_ship_from_', '', $args['key'] ) );
			$row['legacy']['note'] = 'stored in the ec_option_ups_settings array';
		}
		if ( 'setting' === $store || 'ups_settings' === $store ) {
			$row['on_save'] = ( 'ups_settings' === $store ) ? 'ecv2_shipping_on_save_ups_setting' : 'ecv2_shipping_on_save_setting';
		}
		unset( $row['key'] );
		return $row;
	}
}

if ( ! function_exists( 'ecv2_shipping_carrier_fields' ) ) {
	/** The Carrier accounts section rows: a sub-header row then the fields, per carrier. */
	function ecv2_shipping_carrier_fields() {
		/* 'options' callable: ec_country is read only when this page renders or an origin-country row saves. */
		$countries = function () {
			return ecv2_shipping_country_options( __( 'Select one', 'wp-easycart' ) );
		};
		$lb_kg     = array( 'LB' => __( 'Pounds ( lb )', 'wp-easycart' ), 'KG' => __( 'Kilograms ( kg )', 'wp-easycart' ) );
		$fields    = array();

		/* Australia Post */
		$c = 'auspost';
		$n = __( 'Australia Post', 'wp-easycart' );
		$fields[ 'ecv2_shipping_head_' . $c ] = array( 'type' => 'html', 'render' => 'ecv2_shipping_render_carrier_head', 'carrier' => $c, 'carrier_name' => $n, 'mark' => 'AP', 'docs' => 'australia-post' );
		$fields['ec_option_auspost_enable'] = ecv2_shipping_carrier_row( $c, $n, 'carrier_enable', array( 'key' => 'ec_option_auspost_enable', 'type' => 'toggle', 'default' => ecv2_shipping_carrier_in_use( $c ), 'label' => __( 'Enable Australia Post', 'wp-easycart' ), 'desc' => __( 'Quotes Australia Post services with your Australia Post API key. Turning it off clears the saved Australia Post details.', 'wp-easycart' ), 'on_save' => 'ecv2_shipping_on_save_carrier_enable', 'legacy' => array( 'label' => '( new in 6.0.0 )', 'note' => 'on while any detail is filled; turning it off clears the details' ) ) );
		$fields['auspost_api_key']       = ecv2_shipping_carrier_row( $c, $n, 'setting', array( 'key' => 'auspost_api_key', 'type' => 'password', 'label' => __( 'API key', 'wp-easycart' ), 'desc' => __( 'From your Australia Post developer account. Rates start quoting once the key and postal code are set.', 'wp-easycart' ), 'parent' => 'ec_option_auspost_enable', 'legacy' => array( 'label' => 'API Key' ) ) );
		$fields['auspost_ship_from_zip'] = ecv2_shipping_carrier_row( $c, $n, 'setting', array( 'key' => 'auspost_ship_from_zip', 'label' => __( 'Origin postal code', 'wp-easycart' ), 'desc' => __( 'Where parcels are posted from; Australia Post quotes from here.', 'wp-easycart' ), 'parent' => 'ec_option_auspost_enable', 'legacy' => array( 'label' => 'Ship From Postal Code' ) ) );

		/* Canada Post */
		$c = 'canadapost';
		$n = __( 'Canada Post', 'wp-easycart' );
		$fields[ 'ecv2_shipping_head_' . $c ] = array( 'type' => 'html', 'render' => 'ecv2_shipping_render_carrier_head', 'carrier' => $c, 'carrier_name' => $n, 'mark' => 'CP', 'docs' => 'canada-post' );
		$fields['ec_option_canadapost_enable'] = ecv2_shipping_carrier_row( $c, $n, 'carrier_enable', array( 'key' => 'ec_option_canadapost_enable', 'type' => 'toggle', 'default' => ecv2_shipping_carrier_in_use( $c ), 'label' => __( 'Enable Canada Post', 'wp-easycart' ), 'desc' => __( 'Quotes Canada Post services with your Canada Post developer account. Turning it off clears the saved Canada Post details.', 'wp-easycart' ), 'on_save' => 'ecv2_shipping_on_save_carrier_enable', 'legacy' => array( 'label' => '( new in 6.0.0 )', 'note' => 'on while any detail is filled; turning it off clears the details' ) ) );
		$fields['canadapost_username']        = ecv2_shipping_carrier_row( $c, $n, 'setting', array( 'key' => 'canadapost_username', 'label' => __( 'API username', 'wp-easycart' ), 'desc' => __( 'From your Canada Post developer program account.', 'wp-easycart' ), 'parent' => 'ec_option_canadapost_enable', 'legacy' => array( 'label' => 'API User Name' ) ) );
		$fields['canadapost_password']        = ecv2_shipping_carrier_row( $c, $n, 'setting', array( 'key' => 'canadapost_password', 'type' => 'password', 'label' => __( 'API password', 'wp-easycart' ), 'parent' => 'ec_option_canadapost_enable', 'legacy' => array( 'label' => 'API Password' ) ) );
		$fields['canadapost_customer_number'] = ecv2_shipping_carrier_row( $c, $n, 'setting', array( 'key' => 'canadapost_customer_number', 'label' => __( 'Customer number', 'wp-easycart' ), 'parent' => 'ec_option_canadapost_enable', 'legacy' => array( 'label' => 'Customer Number' ) ) );
		$fields['canadapost_contract_id']     = ecv2_shipping_carrier_row( $c, $n, 'setting', array( 'key' => 'canadapost_contract_id', 'label' => __( 'Contract ID', 'wp-easycart' ), 'desc' => __( 'Only if you have negotiated rates; leave empty for counter rates.', 'wp-easycart' ), 'advanced' => true, 'parent' => 'ec_option_canadapost_enable', 'legacy' => array( 'label' => 'Negotiated Rates Contract ID' ) ) );
		$fields['canadapost_ship_from_zip']   = ecv2_shipping_carrier_row( $c, $n, 'setting', array( 'key' => 'canadapost_ship_from_zip', 'label' => __( 'Origin postal code', 'wp-easycart' ), 'parent' => 'ec_option_canadapost_enable', 'legacy' => array( 'label' => 'Ship From Postal Code' ) ) );
		$fields['canadapost_test_mode']       = ecv2_shipping_carrier_row( $c, $n, 'setting', array( 'key' => 'canadapost_test_mode', 'type' => 'toggle', 'label' => __( 'Test mode', 'wp-easycart' ), 'desc' => __( 'Sends requests to Canada Post’s development server.', 'wp-easycart' ), 'parent' => 'ec_option_canadapost_enable', 'legacy' => array( 'label' => 'Test Mode' ) ) );

		/* DHL */
		$c = 'dhl';
		$n = __( 'DHL', 'wp-easycart' );
		$fields[ 'ecv2_shipping_head_' . $c ] = array( 'type' => 'html', 'render' => 'ecv2_shipping_render_carrier_head', 'carrier' => $c, 'carrier_name' => $n, 'mark' => 'DHL', 'docs' => 'dhl' );
		$fields['ec_option_dhl_enable'] = ecv2_shipping_carrier_row( $c, $n, 'carrier_enable', array( 'key' => 'ec_option_dhl_enable', 'type' => 'toggle', 'default' => ecv2_shipping_carrier_in_use( $c ), 'label' => __( 'Enable DHL', 'wp-easycart' ), 'desc' => __( 'Quotes DHL services with your DHL XML Services account. Turning it off clears the saved DHL details.', 'wp-easycart' ), 'on_save' => 'ecv2_shipping_on_save_carrier_enable', 'legacy' => array( 'label' => '( new in 6.0.0 )', 'note' => 'on while any detail is filled; turning it off clears the details' ) ) );
		$fields['dhl_site_id']                 = ecv2_shipping_carrier_row( $c, $n, 'setting', array( 'key' => 'dhl_site_id', 'label' => __( 'Site ID', 'wp-easycart' ), 'desc' => __( 'From your DHL XML Services account.', 'wp-easycart' ), 'parent' => 'ec_option_dhl_enable', 'legacy' => array( 'label' => 'DHL Site ID' ) ) );
		$fields['dhl_password']                = ecv2_shipping_carrier_row( $c, $n, 'setting', array( 'key' => 'dhl_password', 'type' => 'password', 'label' => __( 'Site password', 'wp-easycart' ), 'parent' => 'ec_option_dhl_enable', 'legacy' => array( 'label' => 'DHL Site Password' ) ) );
		$fields['ec_option_dhl_account_number'] = ecv2_shipping_carrier_row( $c, $n, 'option', array( 'key' => 'ec_option_dhl_account_number', 'label' => __( 'Account number', 'wp-easycart' ), 'default' => '', 'parent' => 'ec_option_dhl_enable', 'legacy' => array( 'label' => 'DHL Account Number' ) ) );
		$fields['dhl_ship_from_zip']           = ecv2_shipping_carrier_row( $c, $n, 'setting', array( 'key' => 'dhl_ship_from_zip', 'label' => __( 'Origin postal code', 'wp-easycart' ), 'parent' => 'ec_option_dhl_enable', 'legacy' => array( 'label' => 'Origin Postal Code' ) ) );
		$fields['dhl_ship_from_country']       = ecv2_shipping_carrier_row( $c, $n, 'setting', array( 'key' => 'dhl_ship_from_country', 'type' => 'select', 'options' => $countries, 'label' => __( 'Origin country', 'wp-easycart' ), 'parent' => 'ec_option_dhl_enable', 'legacy' => array( 'label' => 'Origin Country' ) ) );
		$fields['dhl_weight_unit']             = ecv2_shipping_carrier_row( $c, $n, 'setting', array( 'key' => 'dhl_weight_unit', 'type' => 'select', 'options' => array_merge( array( '' => __( 'Select one', 'wp-easycart' ) ), $lb_kg ), 'label' => __( 'Weight unit', 'wp-easycart' ), 'desc' => __( 'The unit your product weights are entered in.', 'wp-easycart' ), 'parent' => 'ec_option_dhl_enable', 'legacy' => array( 'label' => 'Weight Unit' ) ) );
		$fields['dhl_test_mode']               = ecv2_shipping_carrier_row( $c, $n, 'setting', array( 'key' => 'dhl_test_mode', 'type' => 'toggle', 'label' => __( 'Test mode', 'wp-easycart' ), 'desc' => __( 'Sends requests to DHL’s test server.', 'wp-easycart' ), 'parent' => 'ec_option_dhl_enable', 'legacy' => array( 'label' => 'Test Mode' ) ) );

		/* FedEx */
		$c = 'fedex';
		$n = __( 'FedEx', 'wp-easycart' );
		$fields[ 'ecv2_shipping_head_' . $c ] = array( 'type' => 'html', 'render' => 'ecv2_shipping_render_carrier_head', 'carrier' => $c, 'carrier_name' => $n, 'mark' => 'FX', 'docs' => 'fedex' );
		$fields['ec_option_fedex_use_oauth']              = ecv2_shipping_carrier_row( $c, $n, 'fedex_oauth', array( 'key' => 'ec_option_fedex_use_oauth', 'type' => 'toggle', 'default' => 0, 'label' => __( 'Enable FedEx', 'wp-easycart' ), 'desc' => __( 'Quotes FedEx services with the REST API key and secret from your FedEx developer project.', 'wp-easycart' ), 'legacy' => array( 'label' => 'Enable FedEx' ) ) );
		$fields['ec_option_fedex_api_key']                = ecv2_shipping_carrier_row( $c, $n, 'option', array( 'key' => 'ec_option_fedex_api_key', 'label' => __( 'API key', 'wp-easycart' ), 'default' => '', 'parent' => 'ec_option_fedex_use_oauth', 'legacy' => array( 'label' => 'API Key' ) ) );
		$fields['ec_option_fedex_api_secret_key']         = ecv2_shipping_carrier_row( $c, $n, 'option', array( 'key' => 'ec_option_fedex_api_secret_key', 'type' => 'password', 'label' => __( 'API secret key', 'wp-easycart' ), 'default' => '', 'parent' => 'ec_option_fedex_use_oauth', 'legacy' => array( 'label' => 'API Secret Key' ) ) );
		$fields['fedex_account_number']                   = ecv2_shipping_carrier_row( $c, $n, 'setting', array( 'key' => 'fedex_account_number', 'label' => __( 'Account number', 'wp-easycart' ), 'parent' => 'ec_option_fedex_use_oauth', 'legacy' => array( 'label' => 'Account Number' ) ) );
		$fields['fedex_ship_from_zip']                    = ecv2_shipping_carrier_row( $c, $n, 'setting', array( 'key' => 'fedex_ship_from_zip', 'label' => __( 'Origin postal code', 'wp-easycart' ), 'parent' => 'ec_option_fedex_use_oauth', 'legacy' => array( 'label' => 'Origin Postal Code' ) ) );
		$fields['fedex_country_code']                     = ecv2_shipping_carrier_row( $c, $n, 'setting', array( 'key' => 'fedex_country_code', 'type' => 'select', 'options' => $countries, 'label' => __( 'Origin country', 'wp-easycart' ), 'parent' => 'ec_option_fedex_use_oauth', 'legacy' => array( 'label' => 'Origin Country' ) ) );
		$fields['fedex_weight_units']                     = ecv2_shipping_carrier_row( $c, $n, 'setting', array( 'key' => 'fedex_weight_units', 'type' => 'select', 'options' => $lb_kg, 'label' => __( 'Weight unit', 'wp-easycart' ), 'desc' => __( 'The unit your product weights are entered in.', 'wp-easycart' ), 'parent' => 'ec_option_fedex_use_oauth', 'legacy' => array( 'label' => 'Weight Unit' ) ) );
		$fields['fedex_conversion_rate']                  = ecv2_shipping_carrier_row( $c, $n, 'setting', array( 'key' => 'fedex_conversion_rate', 'type' => 'number', 'min' => 0, 'step' => 0.001, 'placeholder' => '1.000', 'label' => __( 'Rate multiplier', 'wp-easycart' ), 'desc' => __( 'Converts FedEx’s quote currency to yours, or pads every rate. 1.000 leaves quotes as they are.', 'wp-easycart' ), 'advanced' => true, 'parent' => 'ec_option_fedex_use_oauth', 'legacy' => array( 'label' => 'Conversion Rate' ) ) );
		$fields['ec_option_fedex_use_check_address_type'] = ecv2_shipping_carrier_row( $c, $n, 'option', array( 'key' => 'ec_option_fedex_use_check_address_type', 'type' => 'toggle', 'default' => 0, 'label' => __( 'Check for business addresses', 'wp-easycart' ), 'desc' => __( 'Asks FedEx whether the address is residential or business before quoting, so business rates apply where they should. Needs Address Validation Service permission on your FedEx project.', 'wp-easycart' ), 'advanced' => true, 'parent' => 'ec_option_fedex_use_oauth', 'legacy' => array( 'label' => 'Check Address Type' ) ) );
		$fields['fedex_test_account']                     = ecv2_shipping_carrier_row( $c, $n, 'setting', array( 'key' => 'fedex_test_account', 'type' => 'toggle', 'label' => __( 'Test mode', 'wp-easycart' ), 'desc' => __( 'Sends requests to FedEx’s sandbox.', 'wp-easycart' ), 'parent' => 'ec_option_fedex_use_oauth', 'legacy' => array( 'label' => 'Test Mode' ) ) );

		/* UPS */
		$c = 'ups';
		$n = __( 'UPS', 'wp-easycart' );
		$fields[ 'ecv2_shipping_head_' . $c ] = array( 'type' => 'html', 'render' => 'ecv2_shipping_render_carrier_head', 'carrier' => $c, 'carrier_name' => $n, 'mark' => 'UPS', 'docs' => 'ups' );
		$fields['ec_option_ups_use_oauth']  = ecv2_shipping_carrier_row( $c, $n, 'ups_oauth', array( 'key' => 'ec_option_ups_use_oauth', 'type' => 'toggle', 'default' => 0, 'label' => __( 'Enable UPS', 'wp-easycart' ), 'desc' => __( 'Quotes UPS services through your linked UPS account. Link the account below, then fill in the shipper details.', 'wp-easycart' ), 'legacy' => array( 'label' => 'Enable UPS' ) ) );
		$fields['ecv2_shipping_ups_connect'] = array( 'type' => 'html', 'render' => null, 'carrier' => $c, 'parent' => 'ec_option_ups_use_oauth' ); // PRO attaches the Link / Reconnect / Refresh / Disconnect buttons.
		$fields['ups_shipper_number']       = ecv2_shipping_carrier_row( $c, $n, 'setting', array( 'key' => 'ups_shipper_number', 'label' => __( 'Shipper number', 'wp-easycart' ), 'desc' => __( 'The six-character account number from your UPS profile.', 'wp-easycart' ), 'parent' => 'ec_option_ups_use_oauth', 'legacy' => array( 'label' => 'Shipper Number' ) ) );
		$fields['ups_weight_type']          = ecv2_shipping_carrier_row( $c, $n, 'setting', array( 'key' => 'ups_weight_type', 'type' => 'select', 'options' => array( '' => __( 'Select one', 'wp-easycart' ), 'LBS' => __( 'Pounds ( lb )', 'wp-easycart' ), 'KGS' => __( 'Kilograms ( kg )', 'wp-easycart' ) ), 'label' => __( 'Weight unit', 'wp-easycart' ), 'desc' => __( 'The unit your product weights are entered in.', 'wp-easycart' ), 'parent' => 'ec_option_ups_use_oauth', 'legacy' => array( 'label' => 'Weight Unit' ) ) );
		$fields['ups_ship_from_address1']   = ecv2_shipping_carrier_row( $c, $n, 'ups_settings', array( 'key' => 'ups_ship_from_address1', 'label' => __( 'Origin address line 1', 'wp-easycart' ), 'parent' => 'ec_option_ups_use_oauth', 'legacy' => array( 'label' => 'Origin Address (Line 1)' ) ) );
		$fields['ups_ship_from_address2']   = ecv2_shipping_carrier_row( $c, $n, 'ups_settings', array( 'key' => 'ups_ship_from_address2', 'label' => __( 'Origin address line 2', 'wp-easycart' ), 'advanced' => true, 'parent' => 'ec_option_ups_use_oauth', 'legacy' => array( 'label' => 'Origin Address (Line 2)' ) ) );
		$fields['ups_ship_from_address3']   = ecv2_shipping_carrier_row( $c, $n, 'ups_settings', array( 'key' => 'ups_ship_from_address3', 'label' => __( 'Origin address line 3', 'wp-easycart' ), 'advanced' => true, 'parent' => 'ec_option_ups_use_oauth', 'legacy' => array( 'label' => 'Origin Address (Line 3)' ) ) );
		$fields['ups_ship_from_city']       = ecv2_shipping_carrier_row( $c, $n, 'ups_settings', array( 'key' => 'ups_ship_from_city', 'label' => __( 'Origin city', 'wp-easycart' ), 'parent' => 'ec_option_ups_use_oauth', 'legacy' => array( 'label' => 'Origin City' ) ) );
		$fields['ups_ship_from_state']      = ecv2_shipping_carrier_row( $c, $n, 'ups_settings', array( 'key' => 'ups_ship_from_state', 'label' => __( 'Origin state or province code', 'wp-easycart' ), 'desc' => __( 'Two-character code, for example NY or ON.', 'wp-easycart' ), 'placeholder' => 'NY', 'sanitize' => 'ecv2_shipping_sanitize_state_code', 'parent' => 'ec_option_ups_use_oauth', 'legacy' => array( 'label' => 'Origin State/Province (2 Character Code)' ) ) );
		$fields['ups_ship_from_zip']        = ecv2_shipping_carrier_row( $c, $n, 'setting', array( 'key' => 'ups_ship_from_zip', 'label' => __( 'Origin postal code', 'wp-easycart' ), 'parent' => 'ec_option_ups_use_oauth', 'legacy' => array( 'label' => 'Origin Postal Code' ) ) );
		$fields['ups_country_code']         = ecv2_shipping_carrier_row( $c, $n, 'setting', array( 'key' => 'ups_country_code', 'type' => 'select', 'options' => $countries, 'label' => __( 'Origin country', 'wp-easycart' ), 'parent' => 'ec_option_ups_use_oauth', 'legacy' => array( 'label' => 'Origin Country' ) ) );
		$fields['ups_conversion_rate']      = ecv2_shipping_carrier_row( $c, $n, 'setting', array( 'key' => 'ups_conversion_rate', 'type' => 'number', 'min' => 0, 'step' => 0.001, 'placeholder' => '1.000', 'label' => __( 'Rate multiplier', 'wp-easycart' ), 'desc' => __( 'Converts UPS’s quote currency to yours, or pads every rate. 1.000 leaves quotes as they are.', 'wp-easycart' ), 'advanced' => true, 'parent' => 'ec_option_ups_use_oauth', 'legacy' => array( 'label' => 'Conversion Rate' ) ) );
		$fields['ups_negotiated_rates']     = ecv2_shipping_carrier_row( $c, $n, 'setting', array( 'key' => 'ups_negotiated_rates', 'type' => 'toggle', 'label' => __( 'Use negotiated rates', 'wp-easycart' ), 'desc' => __( 'Quotes your account’s negotiated rates when UPS returns them.', 'wp-easycart' ), 'advanced' => true, 'parent' => 'ec_option_ups_use_oauth', 'legacy' => array( 'label' => 'Negotiated Rates' ) ) );

		/* USPS */
		$c = 'usps';
		$n = __( 'USPS', 'wp-easycart' );
		$fields[ 'ecv2_shipping_head_' . $c ] = array( 'type' => 'html', 'render' => 'ecv2_shipping_render_carrier_head', 'carrier' => $c, 'carrier_name' => $n, 'mark' => 'US', 'docs' => 'usps' );
		$fields['ec_option_usps_v3_enable']         = ecv2_shipping_carrier_row( $c, $n, 'usps_flag', array( 'key' => 'ec_option_usps_v3_enable', 'type' => 'toggle', 'default' => 0, 'label' => __( 'Enable USPS', 'wp-easycart' ), 'desc' => __( 'Quotes USPS services through the USPS API. Uses the WP EasyCart USPS app unless you enter your own credentials.', 'wp-easycart' ), 'legacy' => array( 'label' => 'Enable USPS', 'note' => 'labelled "Upgrade to USPS V3" for stores still on Web Tools' ) ) );
		$fields['ec_option_usps_v3_custom']         = ecv2_shipping_carrier_row( $c, $n, 'usps_flag', array( 'key' => 'ec_option_usps_v3_custom', 'type' => 'toggle', 'default' => 0, 'label' => __( 'Use my own USPS app', 'wp-easycart' ), 'desc' => __( 'Connect with a client ID and secret from your own USPS developer app instead of the WP EasyCart app.', 'wp-easycart' ), 'advanced' => true, 'parent' => 'ec_option_usps_v3_enable', 'legacy' => array( 'label' => 'Enter Your Own Credentials' ) ) );
		$fields['ec_option_usps_v3_client_id']      = ecv2_shipping_carrier_row( $c, $n, 'usps_client', array( 'key' => 'ec_option_usps_v3_client_id', 'label' => __( 'Client ID', 'wp-easycart' ), 'default' => '', 'desc' => __( 'From your USPS developer app.', 'wp-easycart' ), 'advanced' => true, 'parent' => 'ec_option_usps_v3_custom', 'legacy' => array( 'label' => 'USPS V3 Client ID' ) ) );
		$fields['ec_option_usps_v3_client_secret']  = ecv2_shipping_carrier_row( $c, $n, 'usps_client', array( 'key' => 'ec_option_usps_v3_client_secret', 'type' => 'password', 'label' => __( 'Client secret', 'wp-easycart' ), 'default' => '', 'advanced' => true, 'parent' => 'ec_option_usps_v3_custom', 'legacy' => array( 'label' => 'USPS V3 Client Secret' ) ) );
		$fields['ec_option_usps_v3_custom_old']     = ecv2_shipping_carrier_row( $c, $n, 'usps_flag', array( 'key' => 'ec_option_usps_v3_custom_old', 'type' => 'toggle', 'default' => 0, 'label' => __( 'Revert to USPS Web Tools', 'wp-easycart' ), 'desc' => __( 'Only if you must: Web Tools stopped working in January 2026. Uses the Web Tools username below.', 'wp-easycart' ), 'advanced' => true, 'parent' => 'ec_option_usps_v3_custom', 'legacy' => array( 'label' => 'Revert to Web Tools' ) ) );
		$fields['usps_user_name']                   = ecv2_shipping_carrier_row( $c, $n, 'setting', array( 'key' => 'usps_user_name', 'label' => __( 'Web Tools username', 'wp-easycart' ), 'desc' => __( 'The legacy USPS Web Tools user ID, usually in the form 123ABCD1234.', 'wp-easycart' ), 'advanced' => true, 'parent' => 'ec_option_usps_v3_custom_old', 'legacy' => array( 'label' => 'API User Name', 'note' => 'legacy showed this when Web Tools was still in use; now a child of "Revert to USPS Web Tools"' ) ) );
		$fields['usps_ship_from_zip']               = ecv2_shipping_carrier_row( $c, $n, 'setting', array( 'key' => 'usps_ship_from_zip', 'label' => __( 'Origin ZIP code', 'wp-easycart' ), 'parent' => 'ec_option_usps_v3_enable', 'legacy' => array( 'label' => 'Origin Zip' ) ) );
		$fields['usps_conversion_rate']             = ecv2_shipping_carrier_row( $c, $n, 'option', array( 'key' => 'usps_conversion_rate', 'type' => 'number', 'min' => 0, 'step' => 0.001, 'default' => '1.000', 'placeholder' => '1.000', 'label' => __( 'Rate multiplier', 'wp-easycart' ), 'desc' => __( 'Converts USPS’s quote currency to yours, or pads every rate. 1.000 leaves quotes as they are.', 'wp-easycart' ), 'advanced' => true, 'parent' => 'ec_option_usps_v3_enable', 'legacy' => array( 'label' => 'Conversion Rate' ) ) );
		$fields['ec_option_usps_v3_custom_rates']   = ecv2_shipping_carrier_row( $c, $n, 'usps_flag', array( 'key' => 'ec_option_usps_v3_custom_rates', 'type' => 'toggle', 'default' => 0, 'label' => __( 'Quote my account’s rates', 'wp-easycart' ), 'desc' => __( 'Uses your USPS payment account for contract or commercial pricing instead of retail.', 'wp-easycart' ), 'parent' => 'ec_option_usps_v3_enable', 'legacy' => array( 'label' => 'Enable Custom Account Rates' ) ) );
		$fields['ec_option_usps_v3_price_type']     = ecv2_shipping_carrier_row( $c, $n, 'option', array( 'key' => 'ec_option_usps_v3_price_type', 'type' => 'select', 'default' => 'CONTRACT', 'options' => array( 'RETAIL' => __( 'Retail', 'wp-easycart' ), 'CONTRACT' => __( 'Contract', 'wp-easycart' ), 'COMMERCIAL' => __( 'Commercial', 'wp-easycart' ) ), 'label' => __( 'Price type', 'wp-easycart' ), 'parent' => 'ec_option_usps_v3_custom_rates', 'legacy' => array( 'label' => 'Price Type' ) ) );
		$fields['ec_option_usps_v3_account_type']   = ecv2_shipping_carrier_row( $c, $n, 'option', array( 'key' => 'ec_option_usps_v3_account_type', 'type' => 'select', 'default' => 'EPS', 'options' => array( 'EPS' => __( 'Enterprise Payment System ( EPS )', 'wp-easycart' ), 'PERMIT' => __( 'Permit', 'wp-easycart' ), 'METER' => __( 'PC Postage meter', 'wp-easycart' ), 'MID' => __( 'Mailer ID ( MID )', 'wp-easycart' ) ), 'label' => __( 'Payment account type', 'wp-easycart' ), 'parent' => 'ec_option_usps_v3_custom_rates', 'legacy' => array( 'label' => 'Payment Account Type' ) ) );
		$fields['ec_option_usps_v3_account_number'] = ecv2_shipping_carrier_row( $c, $n, 'option', array( 'key' => 'ec_option_usps_v3_account_number', 'label' => __( 'Account number', 'wp-easycart' ), 'default' => '', 'desc' => __( 'The EPS account, permit number, meter number or MID that matches the type above.', 'wp-easycart' ), 'parent' => 'ec_option_usps_v3_custom_rates', 'legacy' => array( 'label' => 'Your Account Number' ) ) );
		$fields['ec_option_usps_v3_crid']           = ecv2_shipping_carrier_row( $c, $n, 'option', array( 'key' => 'ec_option_usps_v3_crid', 'label' => __( 'CRID', 'wp-easycart' ), 'default' => '', 'desc' => __( 'Customer Registration ID of the business the account belongs to.', 'wp-easycart' ), 'parent' => 'ec_option_usps_v3_custom_rates', 'legacy' => array( 'label' => 'Your CRID' ) ) );

		/* 6.0.0: most-used carriers first ( the blocks above are written alphabetically ). The page shows one carrier at a
		   time in tabs ( settings-shipping-v2.js ), in this order. */
		$order   = array( 'usps', 'ups', 'fedex', 'dhl', 'canadapost', 'auspost' );
		$grouped = array_fill_keys( $order, array() );
		foreach ( $fields as $key => $field ) {
			$carrier = isset( $field['carrier'] ) && isset( $grouped[ $field['carrier'] ] ) ? $field['carrier'] : 'usps';
			$grouped[ $carrier ][ $key ] = $field;
		}
		$fields = array();
		foreach ( $grouped as $rows ) {
			$fields += $rows;
		}
		return $fields;
	}
}

/* Plan name for copy: the store's own plan, or Pro/Premium when no license is known ( and outside WordPress ). */
$ecv2_shipping_plan = class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::plan_name() : 'Pro/Premium';

return array(
	'slug'        => 'shipping-settings',
	'title'       => __( 'Shipping settings', 'wp-easycart' ),
	'description' => __( 'How shipping is charged, what shoppers can choose, where you ship, and what prints on the packing slip.', 'wp-easycart' ),
	'group'       => 'financial',
	'icon'        => 'car',
	'docs'        => array( 'settings', 'shipping-settings', 'shipping-basic-options' ),
	'legacy'      => array( 'shipping-settings' ),
	'upsell'      => 'default',
	'enqueue'     => 'ecv2_shipping_enqueue',
	'sections'    => array(

		'shipping-method' => array(
			'title'  => __( 'Shipping method', 'wp-easycart' ),
			'hint'   => __( 'Whether you charge shipping and how rates are worked out', 'wp-easycart' ),
			'fields' => array(
				'ec_option_use_shipping' => array(
					'type'     => 'toggle',
					'label'    => __( 'Charge shipping at checkout', 'wp-easycart' ),
					'desc'     => __( 'Off means no shipping address or rate is collected and every order is treated as not shipped. The rest of this page only applies when it is on.', 'wp-easycart' ),
					'default'  => 1,
					'keywords' => array( 'enable', 'disable', 'turn off', 'shipping' ),
					'legacy'   => array( 'page' => 'shipping-settings', 'section' => 'Additional Shipping Options', 'label' => 'Enable Shipping' ),
				),
				'ec_option_shipping_method' => array(
					'type'     => 'select',
					'label'    => __( 'How rates are calculated', 'wp-easycart' ),
					/* translators: %s: plan name, Pro or Premium ( Pro/Premium when no license is known ). */
					'desc'     => sprintf( __( 'Pick the system, then build its rate table on the Shipping rates page. Live carrier rates and Fraktjakt are included with %s.', 'wp-easycart' ), $ecv2_shipping_plan ),
					'default'  => (string) ecv2_shipping_setting( 'shipping_method', 'method' ),
					'options'  => ecv2_shipping_method_options(),
					'parent'   => 'ec_option_use_shipping',
					'on_save'  => 'ecv2_shipping_on_save_method',
					'keywords' => array( 'static', 'weight', 'quantity', 'live rates' ),
					'legacy'   => array( 'page' => 'shipping-rates', 'section' => 'Shipping Method', 'label' => 'Select and Manage Your Shipping Method', 'note' => 'stored in ec_setting.shipping_method; the chooser on the rates page still works' ),
				),
				'shipping_handling_rate' => array(
					'type'        => 'number',
					'label'       => __( 'Handling fee', 'wp-easycart' ),
					'desc'        => __( 'Added on top of every shipping rate as a flat handling charge. Leave at 0 for none.', 'wp-easycart' ),
					'default'     => (string) ecv2_shipping_setting( 'shipping_handling_rate', '0.00' ),
					'placeholder' => '0.00',
					'min'         => 0,
					'step'        => 0.01,
					'parent'      => 'ec_option_use_shipping',
					'on_save'     => 'ecv2_shipping_on_save_setting',
					'keywords'    => array( 'handling', 'surcharge', 'fee', 'packaging' ),
					'legacy'      => array( 'page' => 'shipping-settings', 'section' => 'Additional Shipping Options', 'label' => 'Global Handling Rate', 'note' => 'stored in ec_setting.shipping_handling_rate' ),
				),
				'shipping_expedite_rate' => array(
					'type'        => 'number',
					'label'       => __( 'Expedited shipping surcharge', 'wp-easycart' ),
					'desc'        => __( 'Offers an expedited choice at the standard rate plus this amount. Price, weight, quantity and percentage systems only; 0 hides the choice.', 'wp-easycart' ),
					'default'     => (string) ecv2_shipping_setting( 'shipping_expedite_rate', '0.00' ),
					'placeholder' => '0.00',
					'min'         => 0,
					'step'        => 0.01,
					'advanced'    => true,
					'parent'      => 'ec_option_use_shipping',
					'on_save'     => 'ecv2_shipping_on_save_setting',
					'keywords'    => array( 'expedited', 'express', 'rush', 'surcharge' ),
					'legacy'      => array( 'page' => 'shipping-settings', 'section' => 'Additional Shipping Options', 'label' => 'Expedited Shipping Cost', 'note' => 'stored in ec_setting.shipping_expedite_rate' ),
				),
				'ec_option_enable_metric_unit_display' => array(
					'type'     => 'select',
					'label'    => __( 'Dimension units', 'wp-easycart' ),
					'desc'     => __( 'Used when product dimensions drive option pricing or are sent to a live carrier.', 'wp-easycart' ),
					'default'  => '0',
					'options'  => array(
						'0' => __( 'Standard ( inches )', 'wp-easycart' ),
						'1' => __( 'Metric ( centimetres )', 'wp-easycart' ),
					),
					'advanced' => true,
					'parent'   => 'ec_option_use_shipping',
					'on_save'  => 'ecv2_shipping_flush_cache',
					'keywords' => array( 'metric', 'inches', 'centimetres', 'dimensions' ),
					'legacy'   => array( 'page' => 'shipping-settings', 'section' => 'Additional Shipping Options', 'label' => 'Dimension Unit' ),
				),
			),
		),

		'checkout' => array(
			'title'  => __( 'Options at checkout', 'wp-easycart' ),
			'hint'   => __( 'What shoppers see and can choose when shipping is charged', 'wp-easycart' ),
			'fields' => array(
				'ec_option_hide_shipping_rate_page1' => array(
					'type'     => 'toggle',
					'label'    => __( 'Hide rates until an address is entered', 'wp-easycart' ),
					'desc'     => __( 'The cart page shows no shipping estimate; rates appear once the shopper gives their address at checkout.', 'wp-easycart' ),
					'parent'   => 'ec_option_use_shipping',
					'on_save'  => 'ecv2_shipping_flush_cache',
					'keywords' => array( 'estimate', 'cart page', 'hide', 'address' ),
					'legacy'   => array( 'page' => 'shipping-settings', 'section' => 'Additional Shipping Options', 'label' => 'Hide Cart Shipping Rate' ),
				),
				'ec_option_add_local_pickup' => array(
					'type'     => 'toggle',
					'label'    => __( 'Offer free local pickup', 'wp-easycart' ),
					'desc'     => __( 'Adds a no-cost local pickup choice next to your shipping rates.', 'wp-easycart' ),
					'parent'   => 'ec_option_use_shipping',
					'on_save'  => 'ecv2_shipping_flush_cache',
					'keywords' => array( 'pickup', 'collect', 'in store', 'free' ),
					'legacy'   => array( 'page' => 'shipping-settings', 'section' => 'Additional Shipping Options', 'label' => 'Add Free Local Pickup' ),
				),
				'ec_option_ship_to_billing_global' => array(
					'type'     => 'toggle',
					'label'    => __( 'Ship to the billing address only', 'wp-easycart' ),
					'desc'     => __( 'Removes the separate shipping address form; shoppers are told orders go to their billing address.', 'wp-easycart' ),
					'parent'   => 'ec_option_use_shipping',
					'on_save'  => 'ecv2_shipping_flush_cache',
					'keywords' => array( 'billing address', 'shipping address', 'same address', 'disable' ),
					'legacy'   => array( 'page' => 'shipping-settings', 'section' => 'Additional Shipping Options', 'label' => 'Disable shipping address (ship to billing address only)' ),
				),
				'ec_option_collect_shipping_for_subscriptions' => array(
					'type'     => 'toggle',
					'label'    => __( 'Charge shipping on subscriptions', 'wp-easycart' ),
					'desc'     => __( 'Collects a shipping address and adds a rate when a subscription product is marked shippable.', 'wp-easycart' ),
					'parent'   => 'ec_option_use_shipping',
					'on_save'  => 'ecv2_shipping_flush_cache',
					'keywords' => array( 'subscription', 'recurring', 'shipping address' ),
					'legacy'   => array( 'page' => 'shipping-settings', 'section' => 'Additional Shipping Options', 'label' => 'Shipping Allowed on Subscriptions' ),
				),
				'ec_option_static_ship_items_seperately' => array(
					'type'     => 'toggle',
					'label'    => __( 'Static methods charge per item', 'wp-easycart' ),
					'desc'     => __( 'A static method’s rate is multiplied by the number of items instead of charged once per order.', 'wp-easycart' ),
					'advanced' => true,
					'parent'   => 'ec_option_use_shipping',
					'on_save'  => 'ecv2_shipping_flush_cache',
					'keywords' => array( 'per item', 'separately', 'multiply', 'static' ),
					'legacy'   => array( 'page' => 'shipping-settings', 'section' => 'Additional Shipping Options', 'label' => 'Item Ships Separately (Static Method)' ),
				),
			),
		),

		'live-rates' => array(
			'title'  => __( 'Live carrier rates', 'wp-easycart' ),
			'hint'   => __( 'How rates fetched from a carrier are quoted and shown', 'wp-easycart' ),
			'fields' => array(
				'ec_option_show_delivery_days_live_shipping' => array(
					'type'     => 'toggle',
					'label'    => __( 'Show estimated delivery days', 'wp-easycart' ),
					'desc'     => __( 'Adds the carrier’s transit estimate after each live rate, where the carrier provides one.', 'wp-easycart' ),
					'default'  => 1,
					'parent'   => 'ec_option_use_shipping',
					'on_save'  => 'ecv2_shipping_flush_cache',
					'keywords' => array( 'delivery days', 'transit', 'estimate', 'live' ),
					'legacy'   => array( 'page' => 'shipping-settings', 'section' => 'Additional Shipping Options', 'label' => 'Show Delivery Days' ),
				),
				'ec_option_ship_items_seperately' => array(
					'type'     => 'toggle',
					'label'    => __( 'Quote each item as its own parcel', 'wp-easycart' ),
					'desc'     => __( 'Live rates are requested per item and added up, instead of one combined package.', 'wp-easycart' ),
					'advanced' => true,
					'parent'   => 'ec_option_use_shipping',
					'on_save'  => 'ecv2_shipping_flush_cache',
					'keywords' => array( 'separately', 'parcel', 'package', 'live' ),
					'legacy'   => array( 'page' => 'shipping-settings', 'section' => 'Additional Shipping Options', 'label' => 'Items Ship Separately (Live)' ),
				),
				'ec_option_fedex_use_net_charge' => array(
					'type'     => 'toggle',
					'label'    => __( 'Use FedEx account discounts', 'wp-easycart' ),
					'desc'     => __( 'Quotes your negotiated FedEx rates instead of list rates.', 'wp-easycart' ),
					'default'  => 1,
					'advanced' => true,
					'parent'   => 'ec_option_use_shipping',
					'on_save'  => 'ecv2_shipping_flush_cache',
					'keywords' => array( 'fedex', 'discount', 'negotiated', 'net charge' ),
					'legacy'   => array( 'page' => 'shipping-settings', 'section' => 'Additional Shipping Options', 'label' => 'FedEx Account Discounts Apply' ),
				),
				'ec_option_live_override_always' => array(
					'type'     => 'toggle',
					'label'    => __( 'Always show overridden rates', 'wp-easycart' ),
					'desc'     => __( 'A live rate you have replaced with a fixed amount is shown even when the carrier returns no rate for the address. Off hides it in that case.', 'wp-easycart' ),
					'advanced' => true,
					'parent'   => 'ec_option_use_shipping',
					'on_save'  => 'ecv2_shipping_flush_cache',
					'keywords' => array( 'override', 'fixed rate', 'fallback', 'live' ),
					'legacy'   => array( 'page' => 'shipping-settings', 'section' => 'Additional Shipping Options', 'label' => 'Live Override Rates Always Show' ),
				),
			),
		),

		'carriers' => array(
			'title'  => __( 'Carrier accounts', 'wp-easycart' ),
			'hint'   => __( 'USPS, UPS, FedEx, DHL, Canada Post and Australia Post credentials and origin details. A carrier quotes once its details are complete.', 'wp-easycart' ),
			'pro'    => true,
			'fields' => ecv2_shipping_carrier_fields(),
		),

		'fraktjakt' => array(
			'title'  => __( 'Fraktjakt account', 'wp-easycart' ),
			'hint'   => __( 'Swedish carrier aggregator, used when the shipping method is Fraktjakt', 'wp-easycart' ),
			'pro'    => true,
			'fields' => array(
				'fraktjakt_customer_id' => array(
					'type'     => 'text',
					'label'    => __( 'Customer ID', 'wp-easycart' ),
					'desc'     => __( 'From your Fraktjakt account settings.', 'wp-easycart' ),
					'default'  => (string) ecv2_shipping_setting( 'fraktjakt_customer_id', '' ),
					'pro'      => true,
					'on_save'  => 'ecv2_shipping_on_save_setting',
					'keywords' => array( 'fraktjakt', 'customer', 'account' ),
					'legacy'   => array( 'page' => 'shipping-rates', 'section' => 'Fraktjakt Setup', 'label' => 'Customer ID', 'note' => 'stored in ec_setting.fraktjakt_customer_id' ),
				),
				'fraktjakt_login_key' => array(
					'type'     => 'password',
					'label'    => __( 'Login key', 'wp-easycart' ),
					'desc'     => __( 'The API login key from your Fraktjakt account. Keep it private.', 'wp-easycart' ),
					'default'  => (string) ecv2_shipping_setting( 'fraktjakt_login_key', '' ),
					'pro'      => true,
					'on_save'  => 'ecv2_shipping_on_save_setting',
					'keywords' => array( 'fraktjakt', 'login', 'api key' ),
					'legacy'   => array( 'page' => 'shipping-rates', 'section' => 'Fraktjakt Setup', 'label' => 'Login Key', 'note' => 'stored in ec_setting.fraktjakt_login_key' ),
				),
				'fraktjakt_conversion_rate' => array(
					'type'        => 'number',
					'label'       => __( 'Currency conversion rate', 'wp-easycart' ),
					'desc'        => __( 'Multiplier applied to Fraktjakt’s SEK quotes to reach your store currency. 1.000 means no conversion.', 'wp-easycart' ),
					'default'     => (string) ecv2_shipping_setting( 'fraktjakt_conversion_rate', '1.000' ),
					'placeholder' => '1.000',
					'min'         => 0,
					'step'        => 0.001,
					'advanced'    => true,
					'pro'         => true,
					'on_save'     => 'ecv2_shipping_on_save_setting',
					'keywords'    => array( 'fraktjakt', 'conversion', 'currency', 'sek' ),
					'legacy'      => array( 'page' => 'shipping-rates', 'section' => 'Fraktjakt Setup', 'label' => 'Conversion Rate', 'note' => 'stored in ec_setting.fraktjakt_conversion_rate' ),
				),
				'fraktjakt_test_mode' => array(
					'type'     => 'toggle',
					'label'    => __( 'Test mode', 'wp-easycart' ),
					'desc'     => __( 'Sends quote requests to Fraktjakt’s test server. Turn off before taking real orders.', 'wp-easycart' ),
					'default'  => (int) ecv2_shipping_setting( 'fraktjakt_test_mode', 0 ),
					'pro'      => true,
					'on_save'  => 'ecv2_shipping_on_save_setting',
					'keywords' => array( 'fraktjakt', 'test', 'sandbox' ),
					'legacy'   => array( 'page' => 'shipping-rates', 'section' => 'Fraktjakt Setup', 'label' => 'Test Mode', 'note' => 'stored in ec_setting.fraktjakt_test_mode' ),
				),
			),
		),

		'ship-from' => array(
			'title'  => __( 'Ship-from address', 'wp-easycart' ),
			'hint'   => __( 'The origin Fraktjakt quotes from. Other carriers take their origin details in their own setup above.', 'wp-easycart' ),
			'pro'    => true,
			'fields' => array(
				'fraktjakt_address' => array(
					'type'     => 'text',
					'label'    => __( 'Street address', 'wp-easycart' ),
					'desc'     => __( 'Where parcels are collected from.', 'wp-easycart' ),
					'default'  => (string) ecv2_shipping_setting( 'fraktjakt_address', '' ),
					'pro'      => true,
					'on_save'  => 'ecv2_shipping_on_save_setting',
					'keywords' => array( 'origin', 'ship from', 'address', 'fraktjakt' ),
					'legacy'   => array( 'page' => 'shipping-rates', 'section' => 'Fraktjakt Setup › Initial Shipping Address', 'label' => 'Ship From: Address', 'note' => 'stored in ec_setting.fraktjakt_address' ),
				),
				'fraktjakt_city' => array(
					'type'     => 'text',
					'label'    => __( 'City', 'wp-easycart' ),
					'default'  => (string) ecv2_shipping_setting( 'fraktjakt_city', '' ),
					'pro'      => true,
					'on_save'  => 'ecv2_shipping_on_save_setting',
					'keywords' => array( 'origin', 'ship from', 'city', 'fraktjakt' ),
					'legacy'   => array( 'page' => 'shipping-rates', 'section' => 'Fraktjakt Setup › Initial Shipping Address', 'label' => 'Ship From: City', 'note' => 'stored in ec_setting.fraktjakt_city' ),
				),
				'fraktjakt_state' => array(
					'type'        => 'text',
					'label'       => __( 'State or province code', 'wp-easycart' ),
					'desc'        => __( 'Two-character code, only if your country uses them. Leave empty otherwise.', 'wp-easycart' ),
					'default'     => (string) ecv2_shipping_setting( 'fraktjakt_state', '' ),
					'placeholder' => 'AB',
					'pro'         => true,
					'sanitize'    => 'ecv2_shipping_sanitize_state_code',
					'on_save'     => 'ecv2_shipping_on_save_setting',
					'keywords'    => array( 'origin', 'ship from', 'state', 'province' ),
					'legacy'      => array( 'page' => 'shipping-rates', 'section' => 'Fraktjakt Setup › Initial Shipping Address', 'label' => 'Ship From: State', 'note' => 'stored in ec_setting.fraktjakt_state' ),
				),
				'fraktjakt_zip' => array(
					'type'     => 'text',
					'label'    => __( 'Postal code', 'wp-easycart' ),
					'default'  => (string) ecv2_shipping_setting( 'fraktjakt_zip', '' ),
					'pro'      => true,
					'on_save'  => 'ecv2_shipping_on_save_setting',
					'keywords' => array( 'origin', 'ship from', 'postal code', 'zip' ),
					'legacy'   => array( 'page' => 'shipping-rates', 'section' => 'Fraktjakt Setup › Initial Shipping Address', 'label' => 'Ship From: Postal Code', 'note' => 'stored in ec_setting.fraktjakt_zip' ),
				),
				'fraktjakt_country' => array(
					'type'     => 'select',
					'label'    => __( 'Country', 'wp-easycart' ),
					'desc'     => __( 'Saved as the two-letter country code Fraktjakt expects.', 'wp-easycart' ),
					'default'  => (string) ecv2_shipping_setting( 'fraktjakt_country', '' ),
					'options'  => function () {
						return ecv2_shipping_country_options();
					},
					'pro'      => true,
					'on_save'  => 'ecv2_shipping_on_save_setting',
					'keywords' => array( 'origin', 'ship from', 'country', 'fraktjakt' ),
					'legacy'   => array( 'page' => 'shipping-rates', 'section' => 'Fraktjakt Setup › Initial Shipping Address', 'label' => 'Ship From: Country Code', 'note' => 'was a free-text code; now a country list ( ec_country ), stored in ec_setting.fraktjakt_country' ),
				),
			),
		),

		'packing-slip' => array(
			'title'  => __( 'Packing slip', 'wp-easycart' ),
			'hint'   => __( 'What prints on the packing slip for each order', 'wp-easycart' ),
			'fields' => array(
				'ec_option_packing_slip_show_logo' => array(
					'type'     => 'toggle',
					'label'    => __( 'Store logo', 'wp-easycart' ),
					'default'  => 1,
					'on_save'  => 'ecv2_shipping_flush_cache',
					'keywords' => array( 'packing slip', 'logo', 'print' ),
					'legacy'   => array( 'page' => 'shipping-settings', 'section' => 'Packing Slip Options', 'label' => 'Logo on Packing Slip' ),
				),
				'ec_option_packing_slip_show_order_id' => array(
					'type'     => 'toggle',
					'label'    => __( 'Order number', 'wp-easycart' ),
					'default'  => 1,
					'on_save'  => 'ecv2_shipping_flush_cache',
					'keywords' => array( 'packing slip', 'order number', 'order id' ),
					'legacy'   => array( 'page' => 'shipping-settings', 'section' => 'Packing Slip Options', 'label' => 'Order Number on Packing Slip' ),
				),
				'ec_option_packing_slip_show_order_date' => array(
					'type'     => 'toggle',
					'label'    => __( 'Order date', 'wp-easycart' ),
					'default'  => 1,
					'on_save'  => 'ecv2_shipping_flush_cache',
					'keywords' => array( 'packing slip', 'order date', 'date' ),
					'legacy'   => array( 'page' => 'shipping-settings', 'section' => 'Packing Slip Options', 'label' => 'Order Date on Packing Slip' ),
				),
				'ec_option_packing_slip_show_billing' => array(
					'type'     => 'toggle',
					'label'    => __( 'Billing address', 'wp-easycart' ),
					'default'  => 1,
					'on_save'  => 'ecv2_shipping_flush_cache',
					'keywords' => array( 'packing slip', 'billing', 'address' ),
					'legacy'   => array( 'page' => 'shipping-settings', 'section' => 'Packing Slip Options', 'label' => 'Billing Info on Packing Slip' ),
				),
				'ec_option_packing_slip_show_shipping' => array(
					'type'     => 'toggle',
					'label'    => __( 'Shipping address', 'wp-easycart' ),
					'default'  => 1,
					'on_save'  => 'ecv2_shipping_flush_cache',
					'keywords' => array( 'packing slip', 'shipping', 'address' ),
					'legacy'   => array( 'page' => 'shipping-settings', 'section' => 'Packing Slip Options', 'label' => 'Shipping Info on Packing Slip' ),
				),
				'ec_option_packing_slip_show_phone' => array(
					'type'     => 'toggle',
					'label'    => __( 'Phone number', 'wp-easycart' ),
					'desc'     => __( 'Printed under the billing and shipping addresses, so it needs at least one of those on.', 'wp-easycart' ),
					'default'  => 1,
					'on_save'  => 'ecv2_shipping_flush_cache',
					'keywords' => array( 'packing slip', 'phone', 'telephone' ),
					'legacy'   => array( 'page' => 'shipping-settings', 'section' => 'Packing Slip Options', 'label' => 'Phone on Packing Slip', 'note' => 'legacy showed this row only when billing or shipping was on; the engine has no OR parent so it is always shown' ),
				),
				'ec_option_packing_slip_show_email' => array(
					'type'     => 'toggle',
					'label'    => __( 'Email address', 'wp-easycart' ),
					'default'  => 1,
					'on_save'  => 'ecv2_shipping_flush_cache',
					'keywords' => array( 'packing slip', 'email' ),
					'legacy'   => array( 'page' => 'shipping-settings', 'section' => 'Packing Slip Options', 'label' => 'Email on Packing Slip' ),
				),
				'ec_option_packing_slip_show_product_image' => array(
					'type'     => 'toggle',
					'label'    => __( 'Product images', 'wp-easycart' ),
					'default'  => 1,
					'on_save'  => 'ecv2_shipping_flush_cache',
					'keywords' => array( 'packing slip', 'image', 'thumbnail' ),
					'legacy'   => array( 'page' => 'shipping-settings', 'section' => 'Packing Slip Options', 'label' => 'Product Images on Packing Slip' ),
				),
				'ec_option_packing_slip_show_product_title' => array(
					'type'     => 'toggle',
					'label'    => __( 'Product titles', 'wp-easycart' ),
					'default'  => 1,
					'on_save'  => 'ecv2_shipping_flush_cache',
					'keywords' => array( 'packing slip', 'title', 'product name' ),
					'legacy'   => array( 'page' => 'shipping-settings', 'section' => 'Packing Slip Options', 'label' => 'Product Title on Packing Slip' ),
				),
				'ec_option_packing_slip_show_model_number' => array(
					'type'     => 'toggle',
					'label'    => __( 'Model numbers', 'wp-easycart' ),
					'default'  => 1,
					'on_save'  => 'ecv2_shipping_flush_cache',
					'keywords' => array( 'packing slip', 'model number', 'sku' ),
					'legacy'   => array( 'page' => 'shipping-settings', 'section' => 'Packing Slip Options', 'label' => 'Model Number on Packing Slip' ),
				),
				'ec_option_packing_slip_show_options' => array(
					'type'     => 'toggle',
					'label'    => __( 'Product options', 'wp-easycart' ),
					'desc'     => __( 'The size, color or other choices made for each item.', 'wp-easycart' ),
					'default'  => 1,
					'on_save'  => 'ecv2_shipping_flush_cache',
					'keywords' => array( 'packing slip', 'options', 'variations' ),
					'legacy'   => array( 'page' => 'shipping-settings', 'section' => 'Packing Slip Options', 'label' => 'Product Options on Packing Slip' ),
				),
				'ec_option_packing_slip_show_pricing' => array(
					'type'     => 'toggle',
					'label'    => __( 'Prices', 'wp-easycart' ),
					'desc'     => __( 'Unit and line prices for each item. The totals below need this on.', 'wp-easycart' ),
					'default'  => 1,
					'on_save'  => 'ecv2_shipping_flush_cache',
					'keywords' => array( 'packing slip', 'price', 'amounts' ),
					'legacy'   => array( 'page' => 'shipping-settings', 'section' => 'Packing Slip Options', 'label' => 'Pricing on Packing Slip' ),
				),
				'ec_option_packing_slip_show_subtotal' => array(
					'type'     => 'toggle',
					'label'    => __( 'Subtotal', 'wp-easycart' ),
					'default'  => 1,
					'parent'   => 'ec_option_packing_slip_show_pricing',
					'on_save'  => 'ecv2_shipping_flush_cache',
					'keywords' => array( 'packing slip', 'subtotal' ),
					'legacy'   => array( 'page' => 'shipping-settings', 'section' => 'Packing Slip Options', 'label' => 'Sub Total on Packing Slip' ),
				),
				'ec_option_packing_slip_show_tiptotal' => array(
					'type'     => 'toggle',
					'label'    => __( 'Tip', 'wp-easycart' ),
					'default'  => 1,
					'parent'   => 'ec_option_packing_slip_show_pricing',
					'on_save'  => 'ecv2_shipping_flush_cache',
					'keywords' => array( 'packing slip', 'tip', 'gratuity' ),
					'legacy'   => array( 'page' => 'shipping-settings', 'section' => 'Packing Slip Options', 'label' => 'Tip Total on Packing Slip' ),
				),
				'ec_option_packing_slip_show_shippingtotal' => array(
					'type'     => 'toggle',
					'label'    => __( 'Shipping total', 'wp-easycart' ),
					'default'  => 1,
					'parent'   => 'ec_option_packing_slip_show_pricing',
					'on_save'  => 'ecv2_shipping_flush_cache',
					'keywords' => array( 'packing slip', 'shipping total' ),
					'legacy'   => array( 'page' => 'shipping-settings', 'section' => 'Packing Slip Options', 'label' => 'Shipping Total on Packing Slip' ),
				),
				'ec_option_packing_slip_show_discounttotal' => array(
					'type'     => 'toggle',
					'label'    => __( 'Discounts', 'wp-easycart' ),
					'default'  => 1,
					'parent'   => 'ec_option_packing_slip_show_pricing',
					'on_save'  => 'ecv2_shipping_flush_cache',
					'keywords' => array( 'packing slip', 'discount', 'coupon' ),
					'legacy'   => array( 'page' => 'shipping-settings', 'section' => 'Packing Slip Options', 'label' => 'Discount Total on Packing Slip' ),
				),
				'ec_option_packing_slip_show_taxtotal' => array(
					'type'     => 'toggle',
					'label'    => __( 'Tax or VAT', 'wp-easycart' ),
					'default'  => 1,
					'parent'   => 'ec_option_packing_slip_show_pricing',
					'on_save'  => 'ecv2_shipping_flush_cache',
					'keywords' => array( 'packing slip', 'tax', 'vat' ),
					'legacy'   => array( 'page' => 'shipping-settings', 'section' => 'Packing Slip Options', 'label' => 'Tax/VAT on Packing Slip' ),
				),
				'ec_option_packing_slip_show_grandtotal' => array(
					'type'     => 'toggle',
					'label'    => __( 'Grand total', 'wp-easycart' ),
					'default'  => 1,
					'parent'   => 'ec_option_packing_slip_show_pricing',
					'on_save'  => 'ecv2_shipping_flush_cache',
					'keywords' => array( 'packing slip', 'grand total', 'total' ),
					'legacy'   => array( 'page' => 'shipping-settings', 'section' => 'Packing Slip Options', 'label' => 'Grand Total on Packing Slip' ),
				),
				'ec_option_packing_slip_show_order_notes' => array(
					'type'     => 'toggle',
					'label'    => __( 'Order notes', 'wp-easycart' ),
					'desc'     => __( 'Notes the shopper left at checkout.', 'wp-easycart' ),
					'default'  => 1,
					'on_save'  => 'ecv2_shipping_flush_cache',
					'keywords' => array( 'packing slip', 'notes', 'comments' ),
					'legacy'   => array( 'page' => 'shipping-settings', 'section' => 'Packing Slip Options', 'label' => 'Order Notes on Packing Slip' ),
				),
			),
		),

		'zones' => array(
			'title'  => __( 'Shipping zones', 'wp-easycart' ),
			'hint'   => __( 'Group countries and regions so a rate table can charge by destination', 'wp-easycart' ),
			'fields' => array(),
			'render' => 'ecv2_shipping_render_zones',
		),

		'ship-to' => array(
			'title'  => __( 'Ship-to countries & regions', 'wp-easycart' ),
			'hint'   => __( 'Where you are willing to ship', 'wp-easycart' ),
			'fields' => array(
				'ecv2_shipping_ship_to_note' => array(
					'type'   => 'html',
					'render' => 'ecv2_shipping_render_ship_to_note',
				),
			),
		),
	),
);
