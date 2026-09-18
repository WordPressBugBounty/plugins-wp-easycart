<?php
/**
 * Settings › Taxes ( V2 declaration ).
 *
 * Replaces the classic Taxes page ( subpage=tax ). Every plain option is a
 * field. Three editors are not options and get their own small V2 tables,
 * printed by section render callables and saved through the ecv2_tax_*
 * handlers in wp_easycart_admin_tax_v2 ( admin/inc/wp_easycart_admin_tax_v2.php ):
 *
 *  - Rates by state and by country ( rows in ec_taxrate ).
 *  - VAT rate per country ( ec_country.vat_rate_cnt / vat_b2b_enabled ).
 *  - The Canada province × role grid ( ec_option_canada_tax_options ).
 *
 * The global, duty and VAT switches and rates never were options: the classic
 * page derived them from the ec_taxrate row ( tax_by_all / tax_by_duty /
 * tax_by_vat|tax_by_single_vat ). They are declared here under their classic
 * input ids, which the engine stores as options, and every save re-syncs the
 * ec_taxrate row from those options ( on_save ). Their defaults are read from
 * the existing row so a store converted mid-flight shows its real state.
 *
 * Assets: the page-level 'enqueue' callable ( admin/css/settings-tax-v2.css,
 * admin/js/settings-tax-v2.js ). Render callables print markup only.
 *
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* The controller is included by admin/admin-init.php; this covers any other route that reads the declarations. */
if ( function_exists( 'add_action' ) && ! class_exists( 'wp_easycart_admin_tax_v2' ) && file_exists( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_tax_v2.php' ) ) {
	require_once EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_tax_v2.php';
}

/* ------------------------------------------------------------------ */
/* Helpers                                                             */
/* ------------------------------------------------------------------ */

if ( ! function_exists( 'ecst_tax_db' ) ) {
	/** True inside WordPress with a database; false for the migration-map generator, which includes this file standalone. */
	function ecst_tax_db() {
		return isset( $GLOBALS['wpdb'] ) && is_object( $GLOBALS['wpdb'] ) && function_exists( 'update_option' ) && class_exists( 'wp_easycart_admin_tax_v2' );
	}
}

if ( ! function_exists( 'ecst_tax_rate_row' ) ) {
	/** The ec_taxrate row behind a classic switch: 'global' ( tax_by_all ), 'duty' or 'vat' ( tax_by_vat|tax_by_single_vat ). Null when off. */
	function ecst_tax_rate_row( $type ) {
		static $rows = null;
		if ( null === $rows ) {
			$rows = array( 'global' => null, 'duty' => null, 'vat' => null );
			if ( ecst_tax_db() ) {
				global $wpdb;
				$results = $wpdb->get_results( 'SELECT * FROM ec_taxrate WHERE tax_by_all = 1 OR tax_by_duty = 1 OR tax_by_vat = 1 OR tax_by_single_vat = 1' );
				foreach ( (array) $results as $row ) {
					if ( $row->tax_by_all && null === $rows['global'] ) {
						$rows['global'] = $row;
					} elseif ( $row->tax_by_duty && null === $rows['duty'] ) {
						$rows['duty'] = $row;
					} elseif ( ( $row->tax_by_vat || $row->tax_by_single_vat ) && null === $rows['vat'] ) {
						$rows['vat'] = $row;
					}
				}
			}
		}
		return isset( $rows[ $type ] ) ? $rows[ $type ] : null;
	}
}

if ( ! function_exists( 'ecst_tax_country_options' ) ) {
	/** iso2 => name for every country ( optionally only the given iso2 codes ), with an empty first choice. */
	function ecst_tax_country_options( $empty_label, $only = array() ) {
		$options = array( '' => $empty_label );
		if ( ! ecst_tax_db() ) {
			return $options;
		}
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT iso2_cnt, name_cnt FROM ec_country ORDER BY sort_order ASC, name_cnt ASC' );
		foreach ( (array) $rows as $row ) {
			if ( ! empty( $only ) && ! in_array( $row->iso2_cnt, $only, true ) ) {
				continue;
			}
			$options[ $row->iso2_cnt ] = $row->name_cnt;
		}
		return $options;
	}
}

if ( ! function_exists( 'ecst_tax_us_state_options' ) ) {
	/** code => name for US states ( ec_country id 223 ), with an empty first choice. */
	function ecst_tax_us_state_options() {
		$options = array( '' => __( 'Select a state', 'wp-easycart' ) );
		if ( ! ecst_tax_db() ) {
			return $options;
		}
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT code_sta, name_sta FROM ec_state WHERE idcnt_sta = 223 ORDER BY name_sta ASC' );
		foreach ( (array) $rows as $row ) {
			$options[ $row->code_sta ] = $row->name_sta;
		}
		return $options;
	}
}

/* ------------------------------------------------------------------ */
/* Assets ( page-level enqueue, called from admin_enqueue_scripts )    */
/* ------------------------------------------------------------------ */

if ( ! function_exists( 'ecst_tax_enqueue' ) ) {
	function ecst_tax_enqueue( $page ) {
		$css = plugins_url( '/admin/css/', EC_PLUGIN_DIRECTORY . '/wpeasycart.php' );
		$js  = plugins_url( '/admin/js/', EC_PLUGIN_DIRECTORY . '/wpeasycart.php' );
		wp_enqueue_style( 'wp_easycart_admin_settings_tax_v2_css', $css . 'settings-tax-v2.css', array( 'wp_easycart_admin_settings_page_v2_css' ), EC_CURRENT_VERSION );
		wp_enqueue_script( 'wp_easycart_admin_settings_tax_v2_js', $js . 'settings-tax-v2.js', array( 'jquery', 'wp_easycart_admin_settings_page_v2_js' ), EC_CURRENT_VERSION, true );
		wp_localize_script( 'wp_easycart_admin_settings_tax_v2_js', 'ectx_vars', array(
			'i18n' => array(
				'saving'         => __( 'Saving…', 'wp-easycart' ),
				'saved'          => __( 'Saved', 'wp-easycart' ),
				'added'          => __( 'Added', 'wp-easycart' ),
				'deleting'       => __( 'Deleting…', 'wp-easycart' ),
				'failed'         => __( 'Could not save. Retry', 'wp-easycart' ),
				'pick_geo'       => __( 'Choose one first', 'wp-easycart' ),
				'enter_rate'     => __( 'Enter a rate', 'wp-easycart' ),
				'confirm_delete' => __( 'Delete this rate?', 'wp-easycart' ),
				'unsaved'        => __( 'Not saved', 'wp-easycart' ),
				'leave'          => __( 'You have tax rates that are not saved.', 'wp-easycart' ),
			),
		) );
	}
}

/* ------------------------------------------------------------------ */
/* Side effects ( classic save handlers, ported )                      */
/* ------------------------------------------------------------------ */

if ( ! function_exists( 'ecst_tax_sync_global' ) ) {
	/**
	 * Classic ec_admin_ajax_update_global_tax_rate: off deletes the tax_by_all
	 * row; on inserts or updates it with the rate. Runs after either the switch
	 * or the rate is saved, reading both from their options.
	 */
	function ecst_tax_sync_global() {
		global $wpdb;
		$enabled = (int) get_option( 'ec_option_use_global_tax' );
		$rate    = (float) get_option( 'ec_global_tax_rate' );
		$row_id  = $wpdb->get_var( 'SELECT taxrate_id FROM ec_taxrate WHERE tax_by_all = 1' );
		if ( ! $enabled ) {
			if ( $row_id ) {
				do_action( 'wpeasycart_taxrate_deleting', $row_id );
				$wpdb->query( 'DELETE FROM ec_taxrate WHERE tax_by_all = 1' );
				do_action( 'wpeasycart_taxrate_deleted', $row_id );
			}
			return;
		}
		if ( $row_id ) {
			$wpdb->query( $wpdb->prepare( 'UPDATE ec_taxrate SET all_rate = %f WHERE taxrate_id = %d', $rate, $row_id ) );
			do_action( 'wpeasycart_taxrate_updated', $row_id );
		} else {
			$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_taxrate( tax_by_all, all_rate ) VALUES( 1, %f )', $rate ) );
			do_action( 'wpeasycart_taxrate_added', $wpdb->insert_id );
		}
	}
}

if ( ! function_exists( 'ecst_tax_sync_duty' ) ) {
	/** Classic ec_admin_ajax_update_duty_tax_rate: off deletes the tax_by_duty row; on inserts or updates rate + exempt country. */
	function ecst_tax_sync_duty() {
		global $wpdb;
		$enabled = (int) get_option( 'ec_option_use_duty_tax' );
		$rate    = (float) get_option( 'ec_duty_tax_rate' );
		$exempt  = wp_easycart_admin_verification()->filter_chars( (string) get_option( 'ec_duty_exempt_country_code' ), 2 );
		$row_id  = $wpdb->get_var( 'SELECT taxrate_id FROM ec_taxrate WHERE tax_by_duty = 1' );
		if ( ! $enabled ) {
			if ( $row_id ) {
				do_action( 'wpeasycart_taxrate_deleting', $row_id );
				$wpdb->query( 'DELETE FROM ec_taxrate WHERE tax_by_duty = 1' );
				do_action( 'wpeasycart_taxrate_deleted', $row_id );
			}
			return;
		}
		if ( $row_id ) {
			$wpdb->query( $wpdb->prepare( 'UPDATE ec_taxrate SET duty_rate = %f, duty_exempt_country_code = %s WHERE taxrate_id = %d', $rate, $exempt, $row_id ) );
			do_action( 'wpeasycart_taxrate_updated', $row_id );
		} else {
			$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_taxrate( tax_by_duty, duty_rate, duty_exempt_country_code ) VALUES( 1, %f, %s )', $rate, $exempt ) );
			do_action( 'wpeasycart_taxrate_added', $wpdb->insert_id );
		}
	}
}

if ( ! function_exists( 'ecst_tax_sync_vat' ) ) {
	/**
	 * Classic ec_admin_ajax_save_vat_tax_settings ( vat_type, ec_vat_pricing_method,
	 * ec_default_vat_rate ): VAT off deletes the VAT row; on keeps one row with
	 * tax_by_vat / tax_by_single_vat from "rate varies by country", vat_rate from
	 * the default rate and vat_included / vat_added from the pricing method. The
	 * classic handler also dropped the ec_config VAT caches.
	 */
	function ecst_tax_sync_vat() {
		global $wpdb;
		$enabled    = (int) get_option( 'ec_option_use_vat_tax' );
		$by_country = (int) get_option( 'ec_vat_by_country' );
		$included   = (int) get_option( 'ec_vat_pricing_method' ) ? 1 : 0;
		$rate       = (float) get_option( 'ec_default_vat_rate' );
		$row_id     = $wpdb->get_var( 'SELECT taxrate_id FROM ec_taxrate WHERE tax_by_vat = 1 OR tax_by_single_vat = 1' );
		if ( ! $enabled ) {
			if ( $row_id ) {
				do_action( 'wpeasycart_taxrate_deleting', $row_id );
				$wpdb->query( 'DELETE FROM ec_taxrate WHERE tax_by_vat = 1 OR tax_by_single_vat = 1' );
				do_action( 'wpeasycart_taxrate_deleted', $row_id );
			}
		} elseif ( $row_id ) {
			$wpdb->query( $wpdb->prepare( 'UPDATE ec_taxrate SET tax_by_vat = %d, tax_by_single_vat = %d, vat_rate = %f, vat_included = %d, vat_added = %d WHERE taxrate_id = %d', $by_country ? 1 : 0, $by_country ? 0 : 1, $rate, $included, $included ? 0 : 1, $row_id ) );
			do_action( 'wpeasycart_taxrate_updated', $row_id );
		} else {
			$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_taxrate( tax_by_vat, tax_by_single_vat, vat_rate, vat_included, vat_added ) VALUES( %d, %d, %f, %d, %d )', $by_country ? 1 : 0, $by_country ? 0 : 1, $rate, $included, $included ? 0 : 1 ) );
			do_action( 'wpeasycart_taxrate_added', $wpdb->insert_id );
		}
		wp_cache_delete( 'wpeasycart-config-vat-included' );
		wp_cache_delete( 'wpeasycart-config-vat-added' );
	}
}

if ( ! function_exists( 'ecst_tax_on_save_canada' ) ) {
	/**
	 * Classic ec_admin_update_canada_tax_display ( tax.js ): switching Canada tax
	 * on ticked every province for every role and saved the grid; switching it
	 * off unticked them all ( rates were kept ). Same effect here, server side.
	 */
	function ecst_tax_on_save_canada( $value, $old, $field ) {
		if ( (int) $value === (int) $old ) {
			return;
		}
		$options = wp_easycart_admin_tax_v2::canada_options();
		foreach ( wp_easycart_admin_tax_v2::provinces() as $province => $info ) {
			foreach ( wp_easycart_admin_tax_v2::roles() as $role ) {
				$collect_key = 'ec_option_collect_' . $province . '_tax_' . $role;
				if ( $value ) {
					$options[ $collect_key ] = 1;
					foreach ( array( 'gst', 'pst', 'hst' ) as $type ) {
						$rate_key = 'ec_option_' . $province . '_tax_' . $role . '_' . $type;
						if ( ! isset( $options[ $rate_key ] ) ) {
							$options[ $rate_key ] = $info[ $type ];
						}
					}
				} else {
					unset( $options[ $collect_key ] );
				}
			}
		}
		update_option( 'ec_option_canada_tax_options', $options );
	}
}

/* ------------------------------------------------------------------ */
/* Editors ( markup only; behavior in settings-tax-v2.js )            */
/* ------------------------------------------------------------------ */

if ( ! function_exists( 'ecst_tax_print_geo_options' ) ) {
	/** <option>s for a state ( grouped by country, value = state id ) or country ( value = iso2 ) select. */
	function ecst_tax_print_geo_options( $kind, $selected, $states, $countries ) {
		echo '<option value="">' . esc_html( 'state' === $kind ? __( 'Select a state', 'wp-easycart' ) : __( 'Select a country', 'wp-easycart' ) ) . '</option>';
		if ( 'state' === $kind ) {
			$open = null;
			foreach ( $states as $state ) {
				if ( $open !== $state->country ) {
					if ( null !== $open ) {
						echo '</optgroup>';
					}
					echo '<optgroup label="' . esc_attr( $state->country_name ) . '">';
					$open = $state->country;
				}
				echo '<option value="' . esc_attr( $state->id ) . '" data-group="' . esc_attr( $state->country ) . '"' . selected( (string) $selected, (string) $state->id, false ) . '>' . esc_html( $state->name ) . '</option>';
			}
			if ( null !== $open ) {
				echo '</optgroup>';
			}
		} else {
			foreach ( $countries as $country ) {
				echo '<option value="' . esc_attr( $country->iso2 ) . '" data-group="' . esc_attr( $country->iso2 ) . '"' . selected( (string) $selected, (string) $country->iso2, false ) . '>' . esc_html( $country->name ) . '</option>';
			}
		}
	}
}

if ( ! function_exists( 'ecst_tax_print_rate_input' ) ) {
	/** Percentage input in the engine's affixed style. */
	function ecst_tax_print_rate_input( $class, $value, $extra_attr = '' ) {
		?>
		<span class="ecst-in ectx-in">
			<input type="number" class="ecv2-input <?php echo esc_attr( $class ); ?>" value="<?php echo esc_attr( $value ); ?>" step="0.001" min="0" max="100" placeholder="0.000" <?php echo $extra_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal data-attribute strings built below, never user input. ?>/>
			<span class="ecst-affix ecst-unit">%</span>
		</span>
		<?php
	}
}

if ( ! function_exists( 'ecst_tax_print_save_button' ) ) {
	/** Inline ✓ for an editable row: enabled ( and shown ) by the JS only while the row has unsaved changes. */
	function ecst_tax_print_save_button() {
		?>
		<button type="button" class="ecv2-btn ecv2-btn-sm ectx-save" title="<?php esc_attr_e( 'Save (Enter). Esc undoes.', 'wp-easycart' ); ?>" aria-label="<?php esc_attr_e( 'Save this row', 'wp-easycart' ); ?>" disabled><span class="dashicons dashicons-yes" aria-hidden="true"></span></button>
		<?php
	}
}

if ( ! function_exists( 'ecst_tax_print_rates_row' ) ) {
	/** One row of the state / country rate table; $row null prints the hidden template row the JS clones. */
	function ecst_tax_print_rates_row( $kind, $row, $states, $countries ) {
		$is_tpl = ( null === $row );
		$geo    = $is_tpl ? '' : ( 'state' === $kind ? $row->state_id : $row->country );
		?>
		<tr class="ectx-row<?php echo $is_tpl ? ' ectx-tpl' : ''; ?>" data-id="<?php echo esc_attr( $is_tpl ? '' : $row->id ); ?>"<?php echo $is_tpl ? ' hidden' : ''; ?>>
			<td><select class="ecv2-select ectx-geo" aria-label="<?php echo esc_attr( 'state' === $kind ? __( 'State', 'wp-easycart' ) : __( 'Country', 'wp-easycart' ) ); ?>"><?php ecst_tax_print_geo_options( $kind, $geo, $states, $countries ); ?></select></td>
			<?php if ( 'state' === $kind ) : ?>
				<td class="ectx-group"><?php echo esc_html( $is_tpl ? '' : $row->country ); ?></td>
			<?php endif; ?>
			<td><?php ecst_tax_print_rate_input( 'ectx-rate', $is_tpl ? '' : (string) (float) $row->rate ); ?></td>
			<td class="ectx-actions"><span class="ecst-state"></span><?php ecst_tax_print_save_button(); ?><button type="button" class="ecv2-btn ecv2-btn-ghost ecv2-btn-sm ectx-delete" title="<?php esc_attr_e( 'Delete', 'wp-easycart' ); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button></td>
		</tr>
		<?php
	}
}

if ( ! function_exists( 'ecst_tax_render_rates_table' ) ) {
	/** Rates by state or by country: a V2 table with an add row, saved through ecv2_tax_rate_*. */
	function ecst_tax_render_rates_table( $kind ) {
		$states    = ( 'state' === $kind ) ? wp_easycart_admin_tax_v2::states() : array();
		$countries = ( 'state' === $kind ) ? array() : wp_easycart_admin_tax_v2::countries();
		$rows      = ( 'state' === $kind ) ? wp_easycart_admin_tax_v2::state_rates() : wp_easycart_admin_tax_v2::country_rates();
		?>
		<div class="ectx-rates" data-kind="<?php echo esc_attr( $kind ); ?>" id="ectx_rates_<?php echo esc_attr( $kind ); ?>">
			<p class="ectx-intro"><?php echo esc_html( 'state' === $kind ? __( 'One row per state or province. The rate applies when the shipping address is in that state; a state with no row is not taxed.', 'wp-easycart' ) : __( 'One row per country. The rate applies when the shipping address is in that country and no state rate matched first.', 'wp-easycart' ) ); ?></p>
			<div class="ectx-frame"<?php echo empty( $rows ) ? ' hidden' : ''; ?>>
			<table class="ecv2-table ectx-table ectx-table-<?php echo esc_attr( $kind ); ?>">
				<thead>
					<tr>
						<th><?php echo esc_html( 'state' === $kind ? __( 'State', 'wp-easycart' ) : __( 'Country', 'wp-easycart' ) ); ?></th>
						<?php if ( 'state' === $kind ) : ?><th><?php esc_html_e( 'Country', 'wp-easycart' ); ?></th><?php endif; ?>
						<th class="ectx-col-rate"><?php esc_html_e( 'Rate', 'wp-easycart' ); ?></th>
						<th class="ectx-actions"></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<?php ecst_tax_print_rates_row( $kind, $row, $states, $countries ); ?>
					<?php endforeach; ?>
					<?php ecst_tax_print_rates_row( $kind, null, $states, $countries ); ?>
				</tbody>
			</table>
			</div>
			<p class="ectx-empty"<?php echo empty( $rows ) ? '' : ' hidden'; ?>><?php echo esc_html( 'state' === $kind ? __( 'No state rates yet. Add the first one below.', 'wp-easycart' ) : __( 'No country rates yet. Add the first one below.', 'wp-easycart' ) ); ?></p>
			<div class="ectx-add">
				<select class="ecv2-select ectx-add-geo" aria-label="<?php echo esc_attr( 'state' === $kind ? __( 'State', 'wp-easycart' ) : __( 'Country', 'wp-easycart' ) ); ?>"><?php ecst_tax_print_geo_options( $kind, '', $states, $countries ); ?></select>
				<?php ecst_tax_print_rate_input( 'ectx-add-rate', '' ); ?>
				<button type="button" class="ecv2-btn ecv2-btn-sm ectx-add-btn"><?php esc_html_e( 'Add rate', 'wp-easycart' ); ?></button>
				<span class="ecst-state"></span>
			</div>
		</div>
		<?php
	}
}

if ( ! function_exists( 'ecst_tax_render_state_rates' ) ) {
	function ecst_tax_render_state_rates() {
		ecst_tax_render_rates_table( 'state' );
	}
}

if ( ! function_exists( 'ecst_tax_render_country_rates' ) ) {
	function ecst_tax_render_country_rates() {
		ecst_tax_render_rates_table( 'country' );
	}
}

if ( ! function_exists( 'ecst_tax_print_toggle' ) ) {
	/** The engine's toggle look, opted out of its row autosave ( no .ecst-input ). */
	function ecst_tax_print_toggle( $class, $on, $label, $extra_attr = '' ) {
		?>
		<label class="ecst-toggle ectx-toggle<?php echo $on ? ' is-on' : ''; ?>">
			<input type="checkbox" class="<?php echo esc_attr( $class ); ?>" value="1" aria-label="<?php echo esc_attr( $label ); ?>"<?php checked( $on ); ?> <?php echo $extra_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal data-attribute strings built below, never user input. ?>/>
			<span class="ecst-toggle-track"><span class="ecst-toggle-knob"></span></span>
		</label>
		<?php
	}
}

if ( ! function_exists( 'ecst_tax_print_vat_country_row' ) ) {
	function ecst_tax_print_vat_country_row( $row ) {
		$is_tpl = ( null === $row );
		?>
		<tr class="ectx-row<?php echo $is_tpl ? ' ectx-tpl' : ''; ?>" data-id="<?php echo esc_attr( $is_tpl ? '' : $row->id ); ?>"<?php echo $is_tpl ? ' hidden' : ''; ?>>
			<td><span class="ectx-geo-name"><?php echo esc_html( $is_tpl ? '' : $row->name ); ?></span></td>
			<td><?php ecst_tax_print_rate_input( 'ectx-rate', $is_tpl ? '' : (string) (float) $row->rate ); ?></td>
			<td class="ectx-toggle-cell" data-label="<?php esc_attr_e( 'Apply to business', 'wp-easycart' ); ?>"><?php ecst_tax_print_toggle( 'ectx-b2b', ( ! $is_tpl && $row->b2b ), __( 'Apply to VAT-registered businesses', 'wp-easycart' ) ); ?></td>
			<td class="ectx-actions"><span class="ecst-state"></span><?php ecst_tax_print_save_button(); ?><button type="button" class="ecv2-btn ecv2-btn-ghost ecv2-btn-sm ectx-delete" title="<?php esc_attr_e( 'Delete', 'wp-easycart' ); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button></td>
		</tr>
		<?php
	}
}

if ( ! function_exists( 'ecst_tax_render_vat_country_rates' ) ) {
	/** VAT rate per country ( ec_country ), shown while VAT is on and varies by country. Saved through ecv2_tax_vat_country_*. */
	function ecst_tax_render_vat_country_rates() {
		$rows      = wp_easycart_admin_tax_v2::vat_country_rates();
		$countries = wp_easycart_admin_tax_v2::countries();
		$show      = (int) get_option( 'ec_option_use_vat_tax' ) && (int) get_option( 'ec_vat_by_country' );
		?>
		<div class="ectx-rates" data-kind="vat" id="ectx_vat_countries"<?php echo $show ? '' : ' hidden'; ?>>
			<p class="ectx-intro"><?php esc_html_e( 'Rate per country, used once the shopper enters their country at checkout. Countries not listed are not charged VAT. "Apply to business" charges the country rate to VAT-registered businesses too, instead of the business rate.', 'wp-easycart' ); ?></p>
			<div class="ectx-frame"<?php echo empty( $rows ) ? ' hidden' : ''; ?>>
			<table class="ecv2-table ectx-table ectx-table-vat">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Country', 'wp-easycart' ); ?></th>
						<th class="ectx-col-rate"><?php esc_html_e( 'Rate', 'wp-easycart' ); ?></th>
						<th class="ectx-col-toggle"><?php esc_html_e( 'Apply to business', 'wp-easycart' ); ?></th>
						<th class="ectx-actions"></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<?php ecst_tax_print_vat_country_row( $row ); ?>
					<?php endforeach; ?>
					<?php ecst_tax_print_vat_country_row( null ); ?>
				</tbody>
			</table>
			</div>
			<p class="ectx-empty"<?php echo empty( $rows ) ? '' : ' hidden'; ?>><?php esc_html_e( 'No country rates yet. Add the first one below.', 'wp-easycart' ); ?></p>
			<div class="ectx-add">
				<select class="ecv2-select ectx-add-geo" aria-label="<?php esc_attr_e( 'Country', 'wp-easycart' ); ?>">
					<option value=""><?php esc_html_e( 'Select a country', 'wp-easycart' ); ?></option>
					<?php foreach ( $countries as $country ) : ?>
						<option value="<?php echo esc_attr( $country->id ); ?>"><?php echo esc_html( $country->name ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php ecst_tax_print_rate_input( 'ectx-add-rate', '' ); ?>
				<button type="button" class="ecv2-btn ecv2-btn-sm ectx-add-btn"><?php esc_html_e( 'Add rate', 'wp-easycart' ); ?></button>
				<span class="ecst-state"></span>
			</div>
		</div>
		<?php
	}
}

if ( ! function_exists( 'ecst_tax_render_canada_grid' ) ) {
	/**
	 * Canada province × role grid ( ec_option_canada_tax_options ): one table per
	 * customer role, switched with a role select; every cell saves on change
	 * through ecv2_tax_canada_update. Follows the declared enable toggle.
	 */
	function ecst_tax_render_canada_grid() {
		$enabled   = (int) get_option( 'ec_option_enable_easy_canada_tax' );
		$options   = wp_easycart_admin_tax_v2::canada_options();
		$provinces = wp_easycart_admin_tax_v2::provinces();
		$roles     = wp_easycart_admin_tax_v2::roles();
		$types     = array( 'gst' => __( 'GST', 'wp-easycart' ), 'pst' => __( 'PST', 'wp-easycart' ), 'hst' => __( 'HST', 'wp-easycart' ) );
		?>
		<div class="ectx-canada" id="ectx_canada"<?php echo $enabled ? '' : ' hidden'; ?>>
			<div class="ectx-canada-bar">
				<label for="ectx_canada_role"><?php esc_html_e( 'Customer role', 'wp-easycart' ); ?></label>
				<select id="ectx_canada_role" class="ecv2-select ectx-role">
					<?php foreach ( $roles as $role ) : ?>
						<option value="<?php echo esc_attr( $role ); ?>"><?php echo esc_html( $role ); ?></option>
					<?php endforeach; ?>
				</select>
				<span class="ectx-hint"><?php esc_html_e( 'Tick the provinces you collect in for this role and set their rates in percent. Ticks save straight away; rate changes save with the ✓ button or Enter.', 'wp-easycart' ); ?> <?php esc_html_e( 'Quebec: enter the QST ( 9.975% ) in the PST column. Shoppers, emails and orders see it named QST ( TVQ in French ), and it is charged on the price before GST, as Revenu Québec requires.', 'wp-easycart' ); ?></span>
			</div>
			<?php foreach ( $roles as $i => $role ) : ?>
				<div class="ectx-frame ectx-canada-frame" data-role="<?php echo esc_attr( $role ); ?>"<?php echo 0 === $i ? '' : ' hidden'; ?>>
				<table class="ecv2-table ectx-table ectx-canada-table" data-role="<?php echo esc_attr( $role ); ?>">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Province', 'wp-easycart' ); ?></th>
							<th class="ectx-col-toggle"><?php esc_html_e( 'Collect', 'wp-easycart' ); ?></th>
							<?php foreach ( $types as $label ) : ?><th class="ectx-col-rate"><?php echo esc_html( $label ); ?></th><?php endforeach; ?>
							<th class="ectx-actions"></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $provinces as $province => $info ) : ?>
							<?php $collect = isset( $options[ 'ec_option_collect_' . $province . '_tax_' . $role ] ); ?>
							<tr class="ectx-row<?php echo $collect ? '' : ' is-off'; ?>" data-province="<?php echo esc_attr( $province ); ?>">
								<td class="ectx-prov"><?php echo esc_html( $info['name'] ); ?></td>
								<td class="ectx-collect-cell" data-label="<?php esc_attr_e( 'Collect', 'wp-easycart' ); ?>"><?php ecst_tax_print_toggle( 'ectx-collect', $collect, $info['name'] ); ?></td>
								<?php foreach ( $types as $type => $label ) : ?>
									<?php
									$rate_key = 'ec_option_' . $province . '_tax_' . $role . '_' . $type;
									$stored   = isset( $options[ $rate_key ] ) ? (float) $options[ $rate_key ] : (float) $info[ $type ];
									$percent  = round( ( $stored < 1 ) ? $stored * 100 : $stored, 3 ); // stored as a fraction; the classic grid showed whole numbers as-is.
									?>
									<td class="ectx-rate-cell" data-label="<?php echo esc_attr( $label ); ?>"><?php ecst_tax_print_rate_input( 'ectx-crate', (string) $percent, 'data-field="' . esc_attr( $type ) . '" aria-label="' . esc_attr( $info['name'] . ' ' . $label ) . '"' ); ?></td>
								<?php endforeach; ?>
								<td class="ectx-actions"><span class="ecst-state"></span><?php ecst_tax_print_save_button(); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}
}

if ( ! function_exists( 'ecst_tax_render_service_heading' ) ) {
	/** Sub-heading row inside the automated services section. */
	function ecst_tax_render_service_heading( $field ) {
		echo '<div class="ectx-intro"><b>' . esc_html( $field['label'] ) . '</b>' . ( '' !== $field['desc'] ? ' <span style="color:var(--ecv2-g500);">' . esc_html( $field['desc'] ) . '</span>' : '' ) . '</div>';
	}
}

/* ------------------------------------------------------------------ */
/* Declaration                                                         */
/* ------------------------------------------------------------------ */

$ecst_tax_global = ecst_tax_rate_row( 'global' );
$ecst_tax_duty   = ecst_tax_rate_row( 'duty' );
$ecst_tax_vat    = ecst_tax_rate_row( 'vat' );

$ecst_tax_taxjar_countries = array( 'US', 'CA', 'AU', 'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE', 'GB' );

return array(
	'slug'        => 'tax',
	'title'       => __( 'Taxes', 'wp-easycart' ),
	'description' => __( 'What tax you collect and where: a flat rate, rates by state or country, VAT, Canadian GST/PST/HST, duty, or an automated service.', 'wp-easycart' ),
	'group'       => 'financial',
	'icon'        => 'media-spreadsheet',
	'docs'        => array( 'settings', 'taxes', 'global-tax-setup' ),
	'legacy'      => array( 'tax' ),
	'upsell'      => 'default',
	'enqueue'     => 'ecst_tax_enqueue',
	'sections'    => array(

		'collect' => array(
			'title'  => __( 'Where you collect', 'wp-easycart' ),
			'hint'   => __( 'The base rules that apply to every order', 'wp-easycart' ),
			'fields' => array(
				'ec_option_use_global_tax' => array(
					'type'     => 'toggle',
					'label'    => __( 'Flat rate on every order', 'wp-easycart' ),
					'desc'     => __( 'One rate on every taxable order, whatever the destination. Use rates by state or country instead when tax depends on where you ship.', 'wp-easycart' ),
					'default'  => $ecst_tax_global ? 1 : 0,
					'on_save'  => 'ecst_tax_sync_global',
					'keywords' => array( 'global', 'sales tax', 'everywhere' ),
					'legacy'   => array( 'page' => 'tax', 'section' => 'Setup Global Tax Rate', 'label' => 'Enable Global Tax' ),
				),
				'ec_global_tax_rate' => array(
					'type'     => 'number',
					'label'    => __( 'Flat tax rate', 'wp-easycart' ),
					'desc'     => __( 'Percentage charged on the taxable subtotal of every order.', 'wp-easycart' ),
					'default'  => $ecst_tax_global ? (string) $ecst_tax_global->all_rate : '0',
					'unit'     => '%',
					'min'      => 0,
					'max'      => 100,
					'step'     => 0.001,
					'parent'   => 'ec_option_use_global_tax',
					'on_save'  => 'ecst_tax_sync_global',
					'keywords' => array( 'global', 'percent', 'sales tax' ),
					'legacy'   => array( 'page' => 'tax', 'section' => 'Setup Global Tax Rate', 'label' => 'Tax Rate' ),
				),
				'ec_option_collect_tax_on_shipping' => array(
					'type'     => 'toggle',
					'label'    => __( 'Charge tax on shipping', 'wp-easycart' ),
					'desc'     => __( 'Adds the shipping charge to the amount your flat, state, country and Canadian rates apply to. Does not apply to TaxCloud.', 'wp-easycart' ),
					'keywords' => array( 'shipping', 'taxable', 'freight' ),
					'legacy'   => array( 'page' => 'shipping-settings', 'section' => 'Basic Shipping Options', 'label' => 'Tax Shipping' ),
				),
			),
		),

		'state' => array(
			'title'  => __( 'Rates by state', 'wp-easycart' ),
			'hint'   => __( 'A rate for each state or province you collect in', 'wp-easycart' ),
			'fields' => array(),
			'render' => 'ecst_tax_render_state_rates',
		),

		'country' => array(
			'title'  => __( 'Rates by country', 'wp-easycart' ),
			'hint'   => __( 'A rate for each country you collect in', 'wp-easycart' ),
			'fields' => array(),
			'render' => 'ecst_tax_render_country_rates',
		),

		'vat' => array(
			'title'  => __( 'VAT', 'wp-easycart' ),
			'hint'   => __( 'Value added tax, one rate or a rate per country', 'wp-easycart' ),
			'fields' => array(
				'ec_option_use_vat_tax' => array(
					'type'     => 'toggle',
					'label'    => __( 'Charge VAT', 'wp-easycart' ),
					'desc'     => __( 'Adds VAT to orders. Choose below whether one rate applies everywhere or each country has its own.', 'wp-easycart' ),
					'default'  => $ecst_tax_vat ? 1 : 0,
					'on_save'  => 'ecst_tax_sync_vat',
					'keywords' => array( 'vat', 'value added', 'eu', 'europe' ),
					'legacy'   => array( 'page' => 'tax', 'section' => 'Setup VAT Options', 'label' => 'Enable VAT' ),
				),
				'ec_vat_by_country' => array(
					'type'     => 'toggle',
					'label'    => __( 'Rate varies by country', 'wp-easycart' ),
					'desc'     => __( 'Uses the country rates in the table below once the shopper enters their address. Off charges the default rate everywhere.', 'wp-easycart' ),
					'default'  => ( $ecst_tax_vat && $ecst_tax_vat->tax_by_vat ) ? 1 : 0,
					'parent'   => 'ec_option_use_vat_tax',
					'on_save'  => 'ecst_tax_sync_vat',
					'keywords' => array( 'vat', 'country', 'per country' ),
					'legacy'   => array( 'page' => 'tax', 'section' => 'Setup VAT Options', 'label' => 'VAT by Country' ),
				),
				'ec_default_vat_rate' => array(
					'type'     => 'number',
					'label'    => __( 'Default VAT rate', 'wp-easycart' ),
					'desc'     => __( 'Charged before the shopper’s country is known, and everywhere when the rate does not vary by country.', 'wp-easycart' ),
					'default'  => $ecst_tax_vat ? (string) $ecst_tax_vat->vat_rate : '0',
					'unit'     => '%',
					'min'      => 0,
					'max'      => 100,
					'step'     => 0.001,
					'parent'   => 'ec_option_use_vat_tax',
					'on_save'  => 'ecst_tax_sync_vat',
					'keywords' => array( 'vat', 'percent', 'global vat' ),
					'legacy'   => array( 'page' => 'tax', 'section' => 'Setup VAT Options', 'label' => 'Default Rate' ),
				),
				'ec_vat_pricing_method' => array(
					'type'     => 'toggle',
					'label'    => __( 'Prices include VAT', 'wp-easycart' ),
					'desc'     => __( 'Product prices are shown VAT-inclusive and the VAT portion is broken out at checkout. Off adds VAT on top at checkout.', 'wp-easycart' ),
					'default'  => ( $ecst_tax_vat && $ecst_tax_vat->vat_included ) ? 1 : 0,
					'parent'   => 'ec_option_use_vat_tax',
					'on_save'  => 'ecst_tax_sync_vat',
					'keywords' => array( 'vat', 'inclusive', 'included', 'gross' ),
					'legacy'   => array( 'page' => 'tax', 'section' => 'Setup VAT Options', 'label' => 'Include in Price' ),
				),
				'ec_option_no_vat_on_shipping' => array(
					'type'     => 'toggle',
					'label'    => __( 'Exempt shipping from VAT', 'wp-easycart' ),
					'desc'     => __( 'Leaves the shipping charge out of the VAT total. Off charges VAT on shipping too.', 'wp-easycart' ),
					'parent'   => 'ec_option_use_vat_tax',
					'keywords' => array( 'vat', 'shipping', 'exempt' ),
					'legacy'   => array( 'page' => 'tax', 'section' => 'Setup VAT Options', 'label' => 'VAT on Shipping', 'note' => 'classic toggle showed the inverse; the stored value is unchanged' ),
				),
				'ec_option_vat_custom_rate' => array(
					'type'     => 'number',
					'label'    => __( 'Rate for VAT-registered businesses', 'wp-easycart' ),
					'desc'     => __( 'Charged instead of the country rate when a shopper enters a VAT registration number at checkout and their country is not marked "Apply to business" below. Needs the VAT number field turned on under Checkout.', 'wp-easycart' ),
					'default'  => '0',
					'unit'     => '%',
					'min'      => 0,
					'max'      => 100,
					'step'     => 0.001,
					'parent'   => 'ec_option_use_vat_tax',
					'advanced' => true,
					'keywords' => array( 'vat', 'b2b', 'business', 'reverse charge' ),
					'legacy'   => array( 'page' => 'tax', 'section' => 'Setup VAT Options', 'label' => 'Business Rate' ),
				),
				'ec_option_validate_vat_registration_number' => array(
					'type'     => 'toggle',
					'label'    => __( 'Verify VAT numbers with Vatlayer', 'wp-easycart' ),
					'desc'     => __( 'Checks the VAT registration number a shopper enters at checkout against the Vatlayer service before the business rate applies.', 'wp-easycart' ),
					'parent'   => 'ec_option_use_vat_tax',
					'advanced' => true,
					'keywords' => array( 'vat', 'vatlayer', 'validate', 'verify' ),
					'legacy'   => array( 'page' => 'tax', 'section' => 'Setup VAT Options', 'label' => 'Validate VAT Registration Number' ),
				),
				'ec_option_vatlayer_api_key' => array(
					'type'     => 'password',
					'label'    => __( 'Vatlayer API key', 'wp-easycart' ),
					'desc'     => __( 'From your Vatlayer account. Required for verification and the business rate.', 'wp-easycart' ),
					'parent'   => 'ec_option_validate_vat_registration_number',
					'advanced' => true,
					'keywords' => array( 'vat', 'vatlayer', 'api' ),
					'legacy'   => array( 'page' => 'tax', 'section' => 'Setup VAT Options', 'label' => 'Vatlayer API Key' ),
				),
				'ec_option_vat_rounding' => array(
					'type'     => 'number',
					'label'    => __( 'Rounding increment', 'wp-easycart' ),
					'desc'     => __( 'VAT totals round to this amount. Most countries use 0.01; some require 0.05.', 'wp-easycart' ),
					'default'  => '.01',
					'min'      => 0.001,
					'step'     => 0.001,
					'parent'   => 'ec_option_use_vat_tax',
					'advanced' => true,
					'keywords' => array( 'vat', 'rounding', 'round', 'cents' ),
					'legacy'   => array( 'page' => 'tax', 'section' => 'Setup VAT Options', 'label' => 'Default Rounding' ),
				),
			),
			'render' => 'ecst_tax_render_vat_country_rates',
		),

		'canada' => array(
			'title'  => __( 'Canada GST/PST/HST', 'wp-easycart' ),
			'hint'   => __( 'Provincial rates for orders shipped to Canada', 'wp-easycart' ),
			'fields' => array(
				'ec_option_enable_easy_canada_tax' => array(
					'type'     => 'toggle',
					'label'    => __( 'Canadian provincial tax', 'wp-easycart' ),
					'desc'     => __( 'Charges GST, PST or HST by province on orders shipped to Canada, with separate rates per customer role. Turning it on ticks every province.', 'wp-easycart' ),
					'on_save'  => 'ecst_tax_on_save_canada',
					'keywords' => array( 'canada', 'gst', 'pst', 'hst' ),
					'legacy'   => array( 'page' => 'tax', 'section' => 'Canada Tax Options', 'label' => 'Enable Canada Tax' ),
				),
			),
			'render' => 'ecst_tax_render_canada_grid',
		),

		'duty' => array(
			'title'  => __( 'Duty', 'wp-easycart' ),
			'hint'   => __( 'A percentage on orders shipped abroad', 'wp-easycart' ),
			'fields' => array(
				'ec_option_use_duty_tax' => array(
					'type'     => 'toggle',
					'label'    => __( 'Charge duty on international orders', 'wp-easycart' ),
					'desc'     => __( 'Adds a duty percentage to orders shipped to any country other than the exempt country below.', 'wp-easycart' ),
					'default'  => $ecst_tax_duty ? 1 : 0,
					'on_save'  => 'ecst_tax_sync_duty',
					'keywords' => array( 'duty', 'customs', 'import', 'international' ),
					'legacy'   => array( 'page' => 'tax', 'section' => 'Setup Duty Options', 'label' => 'Enable Duty' ),
				),
				'ec_duty_exempt_country_code' => array(
					'type'     => 'select',
					'label'    => __( 'Duty-exempt country', 'wp-easycart' ),
					'desc'     => __( 'Usually your own country. Orders shipped here pay no duty.', 'wp-easycart' ),
					'default'  => $ecst_tax_duty ? (string) $ecst_tax_duty->duty_exempt_country_code : '',
					'options'  => function () {
						return ecst_tax_country_options( __( 'None – every country pays duty', 'wp-easycart' ) );
					},
					'parent'   => 'ec_option_use_duty_tax',
					'on_save'  => 'ecst_tax_sync_duty',
					'keywords' => array( 'duty', 'exempt', 'home country' ),
					'legacy'   => array( 'page' => 'tax', 'section' => 'Setup Duty Options', 'label' => 'Duty Exempt Country' ),
				),
				'ec_duty_tax_rate' => array(
					'type'     => 'number',
					'label'    => __( 'Duty rate', 'wp-easycart' ),
					'desc'     => __( 'Percentage charged on the taxable subtotal of orders that owe duty.', 'wp-easycart' ),
					'default'  => $ecst_tax_duty ? (string) $ecst_tax_duty->duty_rate : '0',
					'unit'     => '%',
					'min'      => 0,
					'max'      => 100,
					'step'     => 0.001,
					'parent'   => 'ec_option_use_duty_tax',
					'on_save'  => 'ecst_tax_sync_duty',
					'keywords' => array( 'duty', 'percent', 'customs' ),
					'legacy'   => array( 'page' => 'tax', 'section' => 'Setup Duty Options', 'label' => 'Duty Rate' ),
				),
			),
		),

		'automated' => array(
			'title'  => __( 'Automated tax services', 'wp-easycart' ),
			'hint'   => __( 'Live US rates from TaxCloud or TaxJar instead of your own tables', 'wp-easycart' ),
			'pro'    => true,
			'fields' => array(
				'ecst_tax_heading_taxcloud' => array(
					'type'   => 'html',
					'label'  => __( 'TaxCloud', 'wp-easycart' ),
					'desc'   => __( 'US sales tax by address. Active as soon as both the API ID and API key are filled in.', 'wp-easycart' ),
					'render' => 'ecst_tax_render_service_heading',
				),
				'ec_option_tax_cloud_api_id' => array(
					'type'     => 'text',
					'label'    => __( 'TaxCloud API ID', 'wp-easycart' ),
					'desc'     => __( 'From your TaxCloud account.', 'wp-easycart' ),
					'pro'      => true,
					'keywords' => array( 'taxcloud', 'api', 'usa' ),
					'legacy'   => array( 'page' => 'tax', 'section' => 'Tax Cloud for USA', 'label' => 'API ID' ),
				),
				'ec_option_tax_cloud_api_key' => array(
					'type'     => 'password',
					'label'    => __( 'TaxCloud API key', 'wp-easycart' ),
					'desc'     => __( 'From your TaxCloud account. Kept private; only sent from your server.', 'wp-easycart' ),
					'pro'      => true,
					'keywords' => array( 'taxcloud', 'api', 'secret' ),
					'legacy'   => array( 'page' => 'tax', 'section' => 'Tax Cloud for USA', 'label' => 'API Key' ),
				),
				'ec_option_tax_cloud_address' => array(
					'type'     => 'text',
					'label'    => __( 'TaxCloud origin address', 'wp-easycart' ),
					'desc'     => __( 'Street address you ship from.', 'wp-easycart' ),
					'pro'      => true,
					'keywords' => array( 'taxcloud', 'origin', 'ship from' ),
					'legacy'   => array( 'page' => 'tax', 'section' => 'Tax Cloud for USA', 'label' => 'Origin Address' ),
				),
				'ec_option_tax_cloud_city' => array(
					'type'     => 'text',
					'label'    => __( 'TaxCloud origin city', 'wp-easycart' ),
					'desc'     => __( 'City you ship from.', 'wp-easycart' ),
					'pro'      => true,
					'keywords' => array( 'taxcloud', 'origin', 'city' ),
					'legacy'   => array( 'page' => 'tax', 'section' => 'Tax Cloud for USA', 'label' => 'Origin City' ),
				),
				'ec_option_tax_cloud_state' => array(
					'type'     => 'select',
					'label'    => __( 'TaxCloud origin state', 'wp-easycart' ),
					'desc'     => __( 'State you ship from.', 'wp-easycart' ),
					'options'  => 'ecst_tax_us_state_options',
					'pro'      => true,
					'keywords' => array( 'taxcloud', 'origin', 'state' ),
					'legacy'   => array( 'page' => 'tax', 'section' => 'Tax Cloud for USA', 'label' => 'Origin State' ),
				),
				'ec_option_tax_cloud_zip' => array(
					'type'     => 'text',
					'label'    => __( 'TaxCloud origin ZIP', 'wp-easycart' ),
					'desc'     => __( 'ZIP code you ship from.', 'wp-easycart' ),
					'pro'      => true,
					'keywords' => array( 'taxcloud', 'origin', 'zip', 'postal' ),
					'legacy'   => array( 'page' => 'tax', 'section' => 'Tax Cloud for USA', 'label' => 'Origin Zip' ),
				),
				'ecst_tax_heading_taxjar' => array(
					'type'   => 'html',
					'label'  => __( 'TaxJar', 'wp-easycart' ),
					'desc'   => __( 'US sales tax by address with TaxJar product categories. Needs the token for the mode you pick.', 'wp-easycart' ),
					'render' => 'ecst_tax_render_service_heading',
				),
				'ec_option_tax_jar_enable' => array(
					'type'     => 'toggle',
					'label'    => __( 'TaxJar', 'wp-easycart' ),
					'desc'     => __( 'Calculates US sales tax through TaxJar once a token is entered.', 'wp-easycart' ),
					'pro'      => true,
					'keywords' => array( 'taxjar', 'usa', 'automated' ),
					'legacy'   => array( 'page' => 'tax', 'section' => 'TaxJar', 'label' => 'Enable TaxJar' ),
				),
				'ec_option_tax_jar_sandbox' => array(
					'type'     => 'toggle',
					'label'    => __( 'Sandbox mode', 'wp-easycart' ),
					'desc'     => __( 'Talks to the TaxJar sandbox with the sandbox token. Not live; turn off before you sell.', 'wp-easycart' ),
					'parent'   => 'ec_option_tax_jar_enable',
					'pro'      => true,
					'advanced' => true,
					'keywords' => array( 'taxjar', 'sandbox', 'test' ),
					'legacy'   => array( 'page' => 'tax', 'section' => 'TaxJar', 'label' => 'Enable Sandbox' ),
				),
				'ec_option_tax_jar_live_token' => array(
					'type'     => 'password',
					'label'    => __( 'TaxJar live token', 'wp-easycart' ),
					'desc'     => __( 'From your TaxJar account. Used while sandbox mode is off.', 'wp-easycart' ),
					'parent'   => 'ec_option_tax_jar_enable',
					'pro'      => true,
					'keywords' => array( 'taxjar', 'token', 'api' ),
					'legacy'   => array( 'page' => 'tax', 'section' => 'TaxJar', 'label' => 'Live Token' ),
				),
				'ec_option_tax_jar_sandbox_token' => array(
					'type'     => 'password',
					'label'    => __( 'TaxJar sandbox token', 'wp-easycart' ),
					'desc'     => __( 'From your TaxJar account. Used only while sandbox mode is on.', 'wp-easycart' ),
					'parent'   => 'ec_option_tax_jar_enable',
					'pro'      => true,
					'advanced' => true,
					'keywords' => array( 'taxjar', 'sandbox', 'token' ),
					'legacy'   => array( 'page' => 'tax', 'section' => 'TaxJar', 'label' => 'Sandbox Token' ),
				),
				'ec_option_tax_jar_enable_address_verification' => array(
					'type'     => 'toggle',
					'label'    => __( 'Verify US addresses with TaxJar', 'wp-easycart' ),
					'desc'     => __( 'Needs a TaxJar Professional plan. Applies to US addresses only.', 'wp-easycart' ),
					'parent'   => 'ec_option_tax_jar_enable',
					'pro'      => true,
					'advanced' => true,
					'keywords' => array( 'taxjar', 'address', 'validation' ),
					'legacy'   => array( 'page' => 'tax', 'section' => 'TaxJar', 'label' => 'Enable Address Verification' ),
				),
				'ec_option_tax_jar_address' => array(
					'type'     => 'text',
					'label'    => __( 'TaxJar origin address', 'wp-easycart' ),
					'desc'     => __( 'Street address you ship from.', 'wp-easycart' ),
					'parent'   => 'ec_option_tax_jar_enable',
					'pro'      => true,
					'keywords' => array( 'taxjar', 'origin', 'ship from' ),
					'legacy'   => array( 'page' => 'tax', 'section' => 'TaxJar', 'label' => 'Origin Address' ),
				),
				'ec_option_tax_jar_city' => array(
					'type'     => 'text',
					'label'    => __( 'TaxJar origin city', 'wp-easycart' ),
					'desc'     => __( 'City you ship from.', 'wp-easycart' ),
					'parent'   => 'ec_option_tax_jar_enable',
					'pro'      => true,
					'keywords' => array( 'taxjar', 'origin', 'city' ),
					'legacy'   => array( 'page' => 'tax', 'section' => 'TaxJar', 'label' => 'Origin City' ),
				),
				'ec_option_tax_jar_state' => array(
					'type'        => 'text',
					'label'       => __( 'TaxJar origin state', 'wp-easycart' ),
					'desc'        => __( 'Two-letter state or province code you ship from.', 'wp-easycart' ),
					'placeholder' => 'CA',
					'parent'      => 'ec_option_tax_jar_enable',
					'pro'         => true,
					'keywords'    => array( 'taxjar', 'origin', 'state' ),
					'legacy'      => array( 'page' => 'tax', 'section' => 'TaxJar', 'label' => 'Origin State' ),
				),
				'ec_option_tax_jar_zip' => array(
					'type'     => 'text',
					'label'    => __( 'TaxJar origin ZIP', 'wp-easycart' ),
					'desc'     => __( 'ZIP or postal code you ship from.', 'wp-easycart' ),
					'parent'   => 'ec_option_tax_jar_enable',
					'pro'      => true,
					'keywords' => array( 'taxjar', 'origin', 'zip', 'postal' ),
					'legacy'   => array( 'page' => 'tax', 'section' => 'TaxJar', 'label' => 'Origin Zip' ),
				),
				'ec_option_tax_jar_country' => array(
					'type'     => 'select',
					'label'    => __( 'TaxJar origin country', 'wp-easycart' ),
					'desc'     => __( 'Country you ship from. Only countries TaxJar supports are listed.', 'wp-easycart' ),
					'default'  => 'US',
					'options'  => function () use ( $ecst_tax_taxjar_countries ) {
						return ecst_tax_country_options( __( 'Select a country', 'wp-easycart' ), $ecst_tax_taxjar_countries );
					},
					'parent'   => 'ec_option_tax_jar_enable',
					'pro'      => true,
					'keywords' => array( 'taxjar', 'origin', 'country' ),
					'legacy'   => array( 'page' => 'tax', 'section' => 'TaxJar', 'label' => 'Origin Country Code' ),
				),
			),
		),
	),
);
