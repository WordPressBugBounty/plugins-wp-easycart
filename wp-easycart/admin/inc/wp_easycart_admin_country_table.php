<?php
/**
 * WP EasyCart Admin — Countries & regions ( V2 ). Replaces the Countries list/details and the States list/details.
 *
 * Tree list: a country row expands to its regions; Ship-to is an inline toggle at both levels. A drawer replaces
 * both details pages ( Details / Regions / Tax / Where used ). Bulk: enable, disable, set VAT, delete. Safe delete
 * reports regions, shipping-zone memberships, tax rules and past orders; cascades regions and zone rows, leaves
 * orders alone, and offers a 15-minute undo. "Restore default countries & regions" reuses the db manager.
 *
 * Legacy URLs: subpage=country&…&id_cnt=N opens the drawer; subpage=states opens this screen; …&id_sta=N opens the
 * region's country on the Regions tab.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

include_once( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_table_v2.php' );

if ( ! class_exists( 'wp_easycart_admin_country_table' ) ) :

	class wp_easycart_admin_country_table extends wp_easycart_admin_table_v2 {

		const NONCE = 'wp-easycart-cntv2';
		private $regions_by_country = null;

		public function __construct() { parent::__construct(); $this->setup(); }

		public static function url() { return admin_url( 'admin.php?page=wp-easycart-settings&subpage=country' ); }

		public function setup() {
			global $wpdb;
			$this->set_table( 'ec_country', 'id_cnt' );
			$this->set_table_id( 'ec_admin_country_list_v2' );
			$this->set_default_sort( 'sort_order', 'ASC' );
			$this->set_header( __( 'Countries & Regions', 'wp-easycart' ) );
			$this->set_docs_link( 'settings', 'countries' );
			$this->set_add_new( true, 'add-new', __( 'Add country', 'wp-easycart' ) );
			$this->set_add_new_js( 'eccountry.open( 0 ); return false;' );
			$this->set_add_new_css( 'ecv2-btn ecv2-btn-primary' );
			$this->set_label( __( 'Country', 'wp-easycart' ), __( 'Countries', 'wp-easycart' ) );
			$this->set_view_modes( array( 'table' ) );
			$this->set_list_columns( array(
				array( 'name' => 'name_cnt', 'label' => __( 'Country', 'wp-easycart' ), 'format' => 'cnt_name', 'linked' => true ),
				array( 'name' => 'iso2_cnt', 'label' => __( 'ISO', 'wp-easycart' ), 'format' => 'cnt_iso', 'width' => 110 ),
				array( 'name' => 'ship_to_active', 'label' => __( 'Ship to', 'wp-easycart' ), 'format' => 'cnt_ship', 'width' => 110 ),
				array( 'name' => 'vat_rate_cnt', 'label' => __( 'VAT', 'wp-easycart' ), 'format' => 'cnt_vat', 'tablet_hide' => true ),
				array( 'select' => '( SELECT COUNT(*) FROM ec_state s WHERE s.idcnt_sta = ec_country.id_cnt ) AS region_count', 'name' => 'region_count', 'label' => __( 'Regions', 'wp-easycart' ), 'format' => 'cnt_regions', 'width' => 140 ),
				array( 'select' => '( SELECT COUNT(*) FROM ec_state s WHERE s.idcnt_sta = ec_country.id_cnt AND s.ship_to_active = 1 ) AS region_active', 'name' => 'region_active', 'format' => 'hidden', 'label' => '' ),
				array( 'name' => 'sort_order', 'label' => __( 'Order', 'wp-easycart' ), 'format' => 'int', 'width' => 70, 'laptop_hide' => true ),
				array( 'name' => 'id_cnt', 'label' => __( 'ID', 'wp-easycart' ), 'format' => 'int', 'is_id' => true, 'laptop_hide' => true ),
				array( 'name' => 'iso3_cnt', 'format' => 'hidden', 'label' => '' ),
				array( 'name' => 'vat_b2b_enabled', 'format' => 'hidden', 'label' => '' ),
				array( 'name' => 'stripe_taxrate_id', 'format' => 'hidden', 'label' => '' ),
			) );
			$this->set_search_columns( array( 'ec_country.name_cnt', 'ec_country.iso2_cnt', 'ec_country.iso3_cnt' ) );
			$this->set_bulk_actions( array(
				array( 'name' => 'ecv2-country-enable', 'label' => __( 'Enable ship-to', 'wp-easycart' ) ),
				array( 'name' => 'ecv2-country-disable', 'label' => __( 'Disable ship-to', 'wp-easycart' ) ),
				array( 'name' => 'ecv2-country-vat', 'label' => __( 'Set VAT rate…', 'wp-easycart' ) ),
				array( 'name' => 'ecv2-country-delete', 'label' => __( 'Delete', 'wp-easycart' ) ),
			) );
			$this->set_row_menu_actions( array(
				array( 'label' => __( 'Edit', 'wp-easycart' ), 'name' => 'edit', 'icon' => 'edit', 'href' => '#', 'onclick' => 'return eccountry.open_row( this );' ),
				array( 'label' => __( 'Add region', 'wp-easycart' ), 'name' => 'region', 'icon' => 'plus-alt2', 'href' => '#', 'onclick' => 'return eccountry.open_row( this, "regions" );' ),
				array( 'label' => __( 'Delete', 'wp-easycart' ), 'name' => 'delete', 'icon' => 'trash', 'href' => '#', 'onclick' => 'return eccountry.delete_row( this );', 'danger' => true ),
			) );
			$this->set_filters( array(
				array( 'data' => array( (object) array( 'value' => 'on', 'label' => __( 'Ship-to on', 'wp-easycart' ), 'icon' => 'yes' ), (object) array( 'value' => 'off', 'label' => __( 'Ship-to off', 'wp-easycart' ), 'icon' => 'no' ), (object) array( 'value' => 'vat', 'label' => __( 'Has VAT', 'wp-easycart' ), 'icon' => 'tag' ), (object) array( 'value' => 'regions', 'label' => __( 'Has regions', 'wp-easycart' ), 'icon' => 'location' ) ), 'label' => __( 'Show', 'wp-easycart' ), 'type' => 'pills', 'where_callback' => true ),
			) );
			$this->set_health_stats( array(
				array( 'label' => __( 'Countries', 'wp-easycart' ), 'value' => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_country' ), 'filter_value' => '', 'color' => 'default', 'group' => 'catalog' ),
				array( 'label' => __( 'Ship-to enabled', 'wp-easycart' ), 'value' => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_country WHERE ship_to_active = 1' ), 'filter_value' => 'on', 'color' => 'green', 'group' => 'catalog' ),
				array( 'label' => __( 'With regions', 'wp-easycart' ), 'value' => (int) $wpdb->get_var( 'SELECT COUNT( DISTINCT s.idcnt_sta ) FROM ec_state s INNER JOIN ec_country c ON c.id_cnt = s.idcnt_sta' ), /* joined so region rows orphaned by a deleted country ( the legacy delete left them ) are not counted; matches the "Has regions" filter */ 'filter_value' => 'regions', 'color' => 'blue', 'group' => 'catalog' ),
				array( 'label' => __( 'VAT rate set', 'wp-easycart' ), 'value' => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_country WHERE vat_rate_cnt > 0' ), 'filter_value' => 'vat', 'color' => 'blue', 'group' => 'catalog' ),
				array( 'label' => __( 'Disabled', 'wp-easycart' ), 'value' => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_country WHERE ship_to_active = 0' ), 'filter_value' => 'off', 'color' => 'gray', 'group' => 'attention' ),
			) );
		}

		protected function get_health_filter_where( $k ) {
			switch ( $k ) {
				case 'on': return 'ec_country.ship_to_active = 1';
				case 'off': return 'ec_country.ship_to_active = 0';
				case 'vat': return 'ec_country.vat_rate_cnt > 0';
				case 'regions': return 'EXISTS ( SELECT 1 FROM ec_state s WHERE s.idcnt_sta = ec_country.id_cnt )';
			}
			return '';
		}
		protected function get_filter_callback_where( $i, $v ) { return 0 === $i ? $this->get_health_filter_where( $v ) : ''; }

		/**
		 * Header: title + count, then Help, "Restore defaults" and "Add country" as header actions ( the restore used
		 * to float on its own bar above the toolbar ). #eccnt_ctx carries the nonce for settings-lists-v2.js.
		 *
		 * @since 6.0.0
		 */
		protected function print_page_header() {
			echo '<div class="ecv2-page-header eccnt-header">';
			echo '<div class="ecv2-page-header-left">';
			echo '<h1 class="ecv2-page-title">' . esc_html( isset( $this->custom_header ) ? $this->custom_header : $this->item_label_plural ) . '</h1>';
			echo '<span class="ecv2-record-count">' . esc_html( $this->record_count ) . ' ' . esc_html( 1 === (int) $this->record_count ? $this->item_label : $this->item_label_plural ) . '</span>';
			echo '</div>';
			echo '<div class="ecv2-page-header-right">';
			echo '<span id="eccnt_ctx" class="eccnt-ctx" data-nonce="' . esc_attr( wp_create_nonce( self::NONCE ) ) . '" hidden></span>';
			if ( isset( $this->docs_guide ) ) {
				echo '<a href="' . esc_url( wp_easycart_admin()->helpsystem->print_docs_url( $this->docs_guide, $this->docs_link, 'master-record' ) ) . '" target="_blank" class="ecv2-btn ecv2-btn-ghost ecv2-btn-sm"><span class="dashicons dashicons-editor-help"></span> ' . esc_html__( 'Help', 'wp-easycart' ) . '</a>';
			}
			echo '<button type="button" class="ecv2-btn ecv2-btn-ghost ecv2-btn-sm eccnt-restore-btn" id="eccnt_restore_btn" onclick="eccountry.restore_defaults( this );" title="' . esc_attr__( 'Add any default country or region that is missing from your list', 'wp-easycart' ) . '"><span class="dashicons dashicons-image-rotate"></span> <span class="eccnt-restore-label">' . esc_html__( 'Restore defaults', 'wp-easycart' ) . '</span></button>';
			echo '<a href="#" class="ecv2-btn ecv2-btn-primary" onclick="' . esc_attr( $this->add_new_js ) . '"><span class="dashicons dashicons-plus-alt2"></span> ' . esc_html( $this->add_new_label ) . '</a>';
			echo '</div>';
			echo '</div>';
		}

		/**
		 * What "Restore defaults" would add, and the size of the default set. Uses the db manager's dry run so the
		 * preview and the restore share one matching rule.
		 *
		 * @since 6.0.0
		 *
		 * @param bool $dry_run False to actually insert.
		 * @return array|WP_Error
		 */
		public static function restore_defaults_run( $dry_run ) {
			if ( ! class_exists( 'ec_db_manager' ) ) {
				require_once EC_PLUGIN_DIRECTORY . '/inc/classes/core/ec_db_manager.php';
			}
			$m = new ec_db_manager();
			if ( ! method_exists( $m, 'restore_default_countries_and_states' ) ) {
				return new WP_Error( 'missing', __( 'Restore is not available in this version.', 'wp-easycart' ) );
			}
			$r = $m->restore_default_countries_and_states( 0, (bool) $dry_run );
			return is_array( $r ) ? $r : new WP_Error( 'failed', __( 'Restore did not return a result.', 'wp-easycart' ) );
		}

		/** Regions for every country on this page in one query. */
		private function regions_for( $country_id ) {
			global $wpdb;
			if ( null === $this->regions_by_country ) {
				$this->regions_by_country = array();
				$ids = array(); foreach ( (array) $this->results as $r ) { $ids[] = (int) $r->id_cnt; }
				if ( $ids ) { foreach ( $wpdb->get_results( 'SELECT * FROM ec_state WHERE idcnt_sta IN ( ' . implode( ',', $ids ) . ' ) ORDER BY sort_order ASC, name_sta ASC' ) as $s ) { $this->regions_by_country[ (int) $s->idcnt_sta ][] = $s; } } // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $ids is a list of (int) cast values built on the line above.
			}
			return isset( $this->regions_by_country[ (int) $country_id ] ) ? $this->regions_by_country[ (int) $country_id ] : array();
		}

		protected function print_table_row( $result ) {
			$open_id = isset( $_GET['id_cnt'] ) ? (int) $_GET['id_cnt'] : ( isset( $_GET['open_country'] ) ? (int) $_GET['open_country'] : 0 );
			$regions = $this->regions_for( $result->id_cnt );
			echo '<tr class="ecv2-row eccnt-row' . ( $result->ship_to_active ? '' : ' is-off' ) . ( $open_id === (int) $result->id_cnt ? ' is-highlight' : '' ) . '" data-id="' . esc_attr( $result->id_cnt ) . '" data-name="' . esc_attr( wp_unslash( $result->name_cnt ) ) . '" data-regions="' . count( $regions ) . '">';
			echo '<td class="ecv2-col-check"><input type="checkbox" name="bulk[]" value="' . esc_attr( $result->id_cnt ) . '" class="ecv2-row-check" /></td>';
			foreach ( $this->list_columns as $col ) {
				if ( 'hidden' === $col['format'] ) { continue; }
				echo '<td class="ecv2-cell ecv2-cell-' . esc_attr( $col['name'] ) . ( ! empty( $col['tablet_hide'] ) ? ' ecv2-hide-tablet' : '' ) . ( ! empty( $col['laptop_hide'] ) ? ' ecv2-hide-laptop' : '' ) . '">'; $this->print_cell_content( $result, $col ); echo '</td>';
			}
			echo '<td class="ecv2-col-actions">'; $this->print_row_actions( $result ); echo '</td></tr>';
			foreach ( $regions as $s ) {
				echo '<tr class="ecv2-row eccnt-region" data-country="' . (int) $result->id_cnt . '" data-id="' . (int) $s->id_sta . '" style="display:none"><td></td>';
				echo '<td class="ecv2-cell"><span class="eccnt-region-name">' . esc_html( wp_unslash( $s->name_sta ) ) . '</span>' . ( $s->group_sta ? ' <span class="ecv2-sub" style="display:inline">· ' . esc_html( wp_unslash( $s->group_sta ) ) . '</span>' : '' ) . '</td>';
				echo '<td class="ecv2-cell"><span class="ecv2-mono">' . esc_html( $s->code_sta ) . '</span></td>';
				echo '<td class="ecv2-cell"><label class="ecv2-toggle ecv2-toggle-sm"><input type="checkbox" class="eccnt-ship" data-kind="region" data-id="' . (int) $s->id_sta . '"' . ( $s->ship_to_active ? ' checked' : '' ) . ' /><span class="ecv2-toggle-slider"></span></label></td>';
				echo '<td class="ecv2-cell ecv2-hide-tablet"></td><td class="ecv2-cell"><a href="#" class="ecv2-sub" onclick="return eccountry.open( ' . (int) $result->id_cnt . ', \'regions\', ' . (int) $s->id_sta . ' );">' . esc_html__( 'edit', 'wp-easycart' ) . '</a></td><td class="ecv2-cell ecv2-hide-laptop">' . (int) $s->sort_order . '</td><td class="ecv2-cell ecv2-hide-laptop">' . (int) $s->id_sta . '</td><td class="ecv2-col-actions"></td></tr>';
			}
			if ( $regions ) { echo '<tr class="eccnt-region eccnt-region-add" data-country="' . (int) $result->id_cnt . '" style="display:none"><td></td><td class="ecv2-cell" colspan="8"><a href="#" class="ecos-link-brand" onclick="return eccountry.open( ' . (int) $result->id_cnt . ', \'regions\', 0 );">+ ' . esc_html( sprintf( __( 'Add region to %s', 'wp-easycart' ), wp_unslash( $result->name_cnt ) ) ) . '</a></td></tr>'; }
		}

		protected function print_cell_content( $result, $col ) {
			switch ( $col['format'] ) {
				case 'cnt_name':
					$n = (int) $result->region_count;
					echo $n ? '<button type="button" class="eccnt-tw" onclick="eccountry.toggle_tree( this );" aria-expanded="false" title="' . esc_attr__( 'Show regions', 'wp-easycart' ) . '">▸</button>' : '<span class="eccnt-tw eccnt-tw-empty"></span>';
					echo '<a href="#" class="ecv2-link-primary ecv2-title-link" onclick="return eccountry.open_row( this );">' . esc_html( wp_unslash( $result->name_cnt ) ) . '</a>';
					break;
				case 'cnt_iso': echo '<span class="ecv2-mono">' . esc_html( $result->iso2_cnt ) . ( $result->iso3_cnt ? ' · ' . esc_html( $result->iso3_cnt ) : '' ) . '</span>'; break;
				case 'cnt_ship': echo '<label class="ecv2-toggle ecv2-toggle-sm"><input type="checkbox" class="eccnt-ship" data-kind="country" data-id="' . esc_attr( $result->id_cnt ) . '"' . ( $result->ship_to_active ? ' checked' : '' ) . ' /><span class="ecv2-toggle-slider"></span></label>'; break;
				case 'cnt_vat':
					if ( (float) $result->vat_rate_cnt > 0 ) { echo '<span class="ecv2-chip ecv2-chip-blue">' . esc_html( rtrim( rtrim( number_format( (float) $result->vat_rate_cnt, 2 ), '0' ), '.' ) ) . '% VAT</span>' . ( $result->vat_b2b_enabled ? ' <span class="ecv2-sub" style="display:inline">' . esc_html__( 'B2B reverse charge', 'wp-easycart' ) . '</span>' : '' ) . ( $result->stripe_taxrate_id ? ' <span class="ecv2-sub ecv2-mono" style="display:inline">' . esc_html( $result->stripe_taxrate_id ) . '</span>' : '' ); }
					else { echo '<span class="ecv2-sub">—</span>'; }
					break;
				case 'cnt_regions':
					$n = (int) $result->region_count;
					echo $n ? '<a href="#" class="ecv2-usage" onclick="return eccountry.toggle_tree( this );">' . esc_html( sprintf( _n( '%d region', '%d regions', $n, 'wp-easycart' ), $n ) ) . '</a>' . ( (int) $result->region_active !== $n ? ' <span class="ecv2-sub" style="display:inline">' . esc_html( sprintf( __( '%d ship-to', 'wp-easycart' ), (int) $result->region_active ) ) . '</span>' : '' ) : '<span class="ecv2-sub">—</span>';
					break;
				default: parent::print_cell_content( $result, $col );
			}
		}

		/* ---- shared ---- */
		public static function flush() { wp_cache_delete( 'wpeasycart-countries' ); wp_cache_delete( 'wpeasycart-states' ); wp_cache_flush(); }

		public static function country_payload( $id ) {
			global $wpdb;
			$c = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_country WHERE id_cnt = %d', (int) $id ) );
			if ( ! $c ) { return null; }
			$regions = array();
			foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ec_state WHERE idcnt_sta = %d ORDER BY sort_order ASC, name_sta ASC', (int) $id ) ) as $s ) { $regions[] = array( 'id' => (int) $s->id_sta, 'name' => wp_unslash( $s->name_sta ), 'code' => $s->code_sta, 'group' => wp_unslash( (string) $s->group_sta ), 'sort' => (int) $s->sort_order, 'ship' => (bool) $s->ship_to_active ); }
			return array( 'id' => (int) $c->id_cnt, 'name' => wp_unslash( $c->name_cnt ), 'iso2' => $c->iso2_cnt, 'iso3' => $c->iso3_cnt, 'sort' => (int) $c->sort_order, 'ship' => (bool) $c->ship_to_active, 'vat' => (float) $c->vat_rate_cnt, 'vat_b2b' => (bool) $c->vat_b2b_enabled, 'stripe' => (string) $c->stripe_taxrate_id, 'regions' => $regions, 'usage' => self::usage( $c ) );
		}

		/** One rendered list row ( with its region sub-rows ) so the list can update in place after an AJAX save. */
		public static function row_html( $id ) {
			global $wpdb;
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT ec_country.*, ( SELECT COUNT(*) FROM ec_state s WHERE s.idcnt_sta = ec_country.id_cnt ) AS region_count, ( SELECT COUNT(*) FROM ec_state s WHERE s.idcnt_sta = ec_country.id_cnt AND s.ship_to_active = 1 ) AS region_active FROM ec_country WHERE ec_country.id_cnt = %d', (int) $id ) );
			if ( ! $row ) { return ''; }
			$table = new self();
			$table->results = array( $row );
			ob_start();
			$table->print_table_row( $row );
			return ob_get_clean();
		}

		/** What references this country: regions, zones, tax rules, orders. */
		public static function usage( $c ) {
			global $wpdb;
			$zones = $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT z.zone_name FROM ec_zone_to_location l JOIN ec_zone z ON z.zone_id = l.zone_id WHERE l.iso2_cnt = %s', $c->iso2_cnt ) );
			return array(
				'regions' => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_state WHERE idcnt_sta = %d', (int) $c->id_cnt ) ),
				'zones' => array_map( 'wp_unslash', $zones ),
				'tax_rules' => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_taxrate WHERE country_code = %s OR vat_country_code = %s OR duty_exempt_country_code = %s', $c->iso2_cnt, $c->iso2_cnt, $c->iso2_cnt ) ),
				'orders' => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_order WHERE billing_country = %s OR shipping_country = %s', $c->iso2_cnt, $c->iso2_cnt ) ),
			);
		}

		public static function save_country( $d, $id = 0 ) {
			global $wpdb;
			$name = isset( $d['name'] ) ? sanitize_text_field( $d['name'] ) : ''; $iso2 = isset( $d['iso2'] ) ? strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $d['iso2'] ) ) : ''; $iso3 = isset( $d['iso3'] ) ? strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $d['iso3'] ) ) : '';
			if ( '' === $name ) { return new WP_Error( 'name', __( 'A country name is required.', 'wp-easycart' ) ); }
			if ( 2 !== strlen( $iso2 ) ) { return new WP_Error( 'iso2', __( 'ISO 2 must be exactly two letters (e.g. US).', 'wp-easycart' ) ); }
			if ( '' !== $iso3 && 3 !== strlen( $iso3 ) ) { return new WP_Error( 'iso3', __( 'ISO 3 must be three letters (e.g. USA).', 'wp-easycart' ) ); }
			$dupe = $wpdb->get_var( $wpdb->prepare( 'SELECT id_cnt FROM ec_country WHERE iso2_cnt = %s AND id_cnt != %d', $iso2, (int) $id ) );
			if ( $dupe ) { return new WP_Error( 'dupe', sprintf( __( 'ISO code %s is already used by another country.', 'wp-easycart' ), $iso2 ) ); }
			$data = array( 'name_cnt' => $name, 'iso2_cnt' => $iso2, 'iso3_cnt' => $iso3, 'sort_order' => isset( $d['sort'] ) ? (int) $d['sort'] : 0, 'ship_to_active' => ! empty( $d['ship'] ) ? 1 : 0, 'vat_rate_cnt' => isset( $d['vat'] ) ? max( 0, min( 100, (float) $d['vat'] ) ) : 0, 'vat_b2b_enabled' => ! empty( $d['vat_b2b'] ) ? 1 : 0 );
			if ( $id ) {
				$old = $wpdb->get_row( $wpdb->prepare( 'SELECT iso2_cnt FROM ec_country WHERE id_cnt = %d', (int) $id ) );
				if ( ! $old ) { return new WP_Error( 'missing', __( 'Country not found.', 'wp-easycart' ) ); }
				$wpdb->update( 'ec_country', $data, array( 'id_cnt' => (int) $id ) );
				if ( $old->iso2_cnt !== $iso2 ) { /* ISO code is the foreign key everywhere else — carry the references along */
					$wpdb->update( 'ec_zone_to_location', array( 'iso2_cnt' => $iso2 ), array( 'iso2_cnt' => $old->iso2_cnt ) );
					$wpdb->update( 'ec_taxrate', array( 'country_code' => $iso2 ), array( 'country_code' => $old->iso2_cnt ) );
				}
				do_action( 'wpeasycart_country_updated', (int) $id );
			} else {
				$wpdb->insert( 'ec_country', $data ); $id = (int) $wpdb->insert_id;
				do_action( 'wpeasycart_country_added', $id );
			}
			self::flush();
			return (int) $id;
		}

		public static function save_region( $country_id, $d, $id = 0 ) {
			global $wpdb;
			if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT id_cnt FROM ec_country WHERE id_cnt = %d', (int) $country_id ) ) ) { return new WP_Error( 'missing', __( 'Country not found.', 'wp-easycart' ) ); }
			$name = isset( $d['name'] ) ? sanitize_text_field( $d['name'] ) : ''; $code = isset( $d['code'] ) ? strtoupper( sanitize_text_field( $d['code'] ) ) : '';
			if ( '' === $name || '' === $code ) { return new WP_Error( 'req', __( 'Region name and code are required.', 'wp-easycart' ) ); }
			if ( $wpdb->get_var( $wpdb->prepare( 'SELECT id_sta FROM ec_state WHERE idcnt_sta = %d AND code_sta = %s AND id_sta != %d', (int) $country_id, $code, (int) $id ) ) ) { return new WP_Error( 'dupe', sprintf( __( 'Code %s is already used in this country.', 'wp-easycart' ), $code ) ); }
			$data = array( 'idcnt_sta' => (int) $country_id, 'name_sta' => $name, 'code_sta' => $code, 'group_sta' => isset( $d['group'] ) ? sanitize_text_field( $d['group'] ) : '', 'sort_order' => isset( $d['sort'] ) ? (int) $d['sort'] : 0, 'ship_to_active' => ! empty( $d['ship'] ) ? 1 : 0 );
			if ( $id ) { $wpdb->update( 'ec_state', $data, array( 'id_sta' => (int) $id ) ); do_action( 'wpeasycart_state_updated', (int) $id ); }
			else { $wpdb->insert( 'ec_state', $data ); $id = (int) $wpdb->insert_id; do_action( 'wpeasycart_state_added', $id ); }
			self::flush();
			return (int) $id;
		}
	}

endif;

/* ---------------------------------------------------------------------- */
function ecv2_cnt_guard() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) ); }
	check_ajax_referer( wp_easycart_admin_country_table::NONCE, 'nonce' );
}

add_action( 'wp_ajax_ecv2_country_get', 'ecv2_country_get' );
function ecv2_country_get() {
	ecv2_cnt_guard();
	$p = wp_easycart_admin_country_table::country_payload( isset( $_POST['id'] ) ? (int) $_POST['id'] : 0 );
	if ( ! $p ) { wp_send_json_error( array( 'message' => __( 'Country not found.', 'wp-easycart' ) ) ); }
	wp_send_json_success( $p );
}

add_action( 'wp_ajax_ecv2_country_save', 'ecv2_country_save' );
function ecv2_country_save() {
	ecv2_cnt_guard();
	$d = json_decode( wp_unslash( isset( $_POST['data'] ) ? $_POST['data'] : '{}' ), true ); if ( ! is_array( $d ) ) { $d = array(); }
	$is_new = empty( $_POST['id'] );
	$r = wp_easycart_admin_country_table::save_country( $d, $is_new ? 0 : (int) $_POST['id'] );
	if ( is_wp_error( $r ) ) { wp_send_json_error( array( 'message' => $r->get_error_message(), 'field' => $r->get_error_code() ) ); }
	wp_send_json_success( array( 'id' => $r, 'is_new' => $is_new, 'message' => $is_new ? __( 'Country created.', 'wp-easycart' ) : __( 'Saved', 'wp-easycart' ), 'country' => wp_easycart_admin_country_table::country_payload( $r ), 'row' => wp_easycart_admin_country_table::row_html( $r ) ) );
}

add_action( 'wp_ajax_ecv2_region_save', 'ecv2_region_save' );
function ecv2_region_save() {
	ecv2_cnt_guard();
	$d = json_decode( wp_unslash( isset( $_POST['data'] ) ? $_POST['data'] : '{}' ), true ); if ( ! is_array( $d ) ) { $d = array(); }
	$r = wp_easycart_admin_country_table::save_region( isset( $_POST['country_id'] ) ? (int) $_POST['country_id'] : 0, $d, isset( $_POST['id'] ) ? (int) $_POST['id'] : 0 );
	if ( is_wp_error( $r ) ) { wp_send_json_error( array( 'message' => $r->get_error_message() ) ); }
	wp_send_json_success( array( 'id' => $r, 'country' => wp_easycart_admin_country_table::country_payload( (int) $_POST['country_id'] ), 'row' => wp_easycart_admin_country_table::row_html( (int) $_POST['country_id'] ) ) );
}

add_action( 'wp_ajax_ecv2_region_delete', 'ecv2_region_delete' );
function ecv2_region_delete() {
	ecv2_cnt_guard(); global $wpdb;
	$s = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_state WHERE id_sta = %d', isset( $_POST['id'] ) ? (int) $_POST['id'] : 0 ) );
	if ( ! $s ) { wp_send_json_error( array( 'message' => __( 'Region not found.', 'wp-easycart' ) ) ); }
	do_action( 'wpeasycart_state_deleting', (int) $s->id_sta );
	$wpdb->delete( 'ec_state', array( 'id_sta' => (int) $s->id_sta ) );
	$iso = $wpdb->get_var( $wpdb->prepare( 'SELECT iso2_cnt FROM ec_country WHERE id_cnt = %d', (int) $s->idcnt_sta ) );
	if ( $iso ) { $wpdb->delete( 'ec_zone_to_location', array( 'iso2_cnt' => $iso, 'code_sta' => $s->code_sta ) ); }
	wp_easycart_admin_country_table::flush();
	wp_send_json_success( array( 'country' => wp_easycart_admin_country_table::country_payload( (int) $s->idcnt_sta ), 'row' => wp_easycart_admin_country_table::row_html( (int) $s->idcnt_sta ) ) );
}

/** Inline toggles for both levels. */
add_action( 'wp_ajax_ecv2_country_toggle_ship', 'ecv2_country_toggle_ship' );
function ecv2_country_toggle_ship() {
	ecv2_cnt_guard(); global $wpdb;
	$kind = isset( $_POST['kind'] ) && 'region' === $_POST['kind'] ? 'region' : 'country'; $id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0; $on = ! empty( $_POST['on'] ) && '0' !== $_POST['on'] ? 1 : 0;
	if ( 'region' === $kind ) { $wpdb->update( 'ec_state', array( 'ship_to_active' => $on ), array( 'id_sta' => $id ) ); do_action( 'wpeasycart_state_updated', $id ); }
	else { $wpdb->update( 'ec_country', array( 'ship_to_active' => $on ), array( 'id_cnt' => $id ) ); do_action( 'wpeasycart_country_updated', $id ); }
	wp_easycart_admin_country_table::flush();
	wp_send_json_success( array( 'on' => $on ) );
}

add_action( 'wp_ajax_ecv2_country_bulk', 'ecv2_country_bulk' );
function ecv2_country_bulk() {
	ecv2_cnt_guard(); global $wpdb;
	$ids = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ? array_slice( array_map( 'intval', $_POST['ids'] ), 0, 500 ) : array(); $op = isset( $_POST['op'] ) ? sanitize_key( $_POST['op'] ) : '';
	if ( empty( $ids ) ) { wp_send_json_error( array( 'message' => __( 'Nothing selected.', 'wp-easycart' ) ) ); }
	$in = implode( ',', $ids );
	if ( 'enable' === $op || 'disable' === $op ) { $n = (int) $wpdb->query( 'UPDATE ec_country SET ship_to_active = ' . ( 'enable' === $op ? 1 : 0 ) . " WHERE id_cnt IN ( $in )" ); if ( ! empty( $_POST['regions_too'] ) ) { $wpdb->query( 'UPDATE ec_state SET ship_to_active = ' . ( 'enable' === $op ? 1 : 0 ) . " WHERE idcnt_sta IN ( $in )" ); } $msg = 'enable' === $op ? sprintf( _n( 'Ship-to enabled for %d country.', 'Ship-to enabled for %d countries.', $n, 'wp-easycart' ), $n ) : sprintf( _n( 'Ship-to disabled for %d country.', 'Ship-to disabled for %d countries.', $n, 'wp-easycart' ), $n ); } // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $in is an implode of intval() ids; the ship_to_active value is a literal 1/0 ternary.
	else if ( 'vat' === $op ) { $rate = isset( $_POST['rate'] ) ? max( 0, min( 100, (float) $_POST['rate'] ) ) : 0; $n = (int) $wpdb->query( $wpdb->prepare( "UPDATE ec_country SET vat_rate_cnt = %f WHERE id_cnt IN ( $in )", $rate ) ); $msg = sprintf( _n( 'VAT rate set on %d country.', 'VAT rate set on %d countries.', $n, 'wp-easycart' ), $n ); } // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $in is an implode of intval() ids; $rate is prepared with %f.
	else { wp_send_json_error( array( 'message' => __( 'Unknown action.', 'wp-easycart' ) ) ); }
	wp_easycart_admin_country_table::flush();
	wp_send_json_success( array( 'done' => $n, 'message' => $msg ) );
}

add_action( 'wp_ajax_ecv2_country_delete_impact', 'ecv2_country_delete_impact' );
function ecv2_country_delete_impact() {
	ecv2_cnt_guard(); global $wpdb;
	$ids = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ? array_slice( array_map( 'intval', $_POST['ids'] ), 0, 500 ) : array();
	$items = array(); $tot = array( 'regions' => 0, 'zones' => array(), 'tax_rules' => 0, 'orders' => 0, 'ship_on' => 0 );
	foreach ( $ids as $id ) {
		$c = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_country WHERE id_cnt = %d', $id ) ); if ( ! $c ) { continue; }
		$u = wp_easycart_admin_country_table::usage( $c ); $items[] = array( 'id' => (int) $c->id_cnt, 'name' => wp_unslash( $c->name_cnt ), 'iso2' => $c->iso2_cnt, 'ship' => (bool) $c->ship_to_active ) + $u;
		$tot['regions'] += $u['regions']; $tot['zones'] = array_unique( array_merge( $tot['zones'], $u['zones'] ) ); $tot['tax_rules'] += $u['tax_rules']; $tot['orders'] += $u['orders']; $tot['ship_on'] += $c->ship_to_active ? 1 : 0;
	}
	$tot['zones'] = array_values( $tot['zones'] );
	wp_send_json_success( array( 'items' => $items, 'totals' => $tot ) );
}

add_action( 'wp_ajax_ecv2_country_delete', 'ecv2_country_delete' );
function ecv2_country_delete() {
	ecv2_cnt_guard(); global $wpdb;
	$ids = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ? array_slice( array_map( 'intval', $_POST['ids'] ), 0, 500 ) : array();
	if ( empty( $ids ) ) { wp_send_json_error( array( 'message' => __( 'Nothing selected.', 'wp-easycart' ) ) ); }
	$snap = array( 'countries' => array(), 'states' => array(), 'zones' => array() ); $names = array();
	foreach ( $ids as $id ) {
		$c = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_country WHERE id_cnt = %d', $id ), ARRAY_A ); if ( ! $c ) { continue; }
		$snap['countries'][] = $c; $names[] = wp_unslash( $c['name_cnt'] );
		$snap['states'] = array_merge( $snap['states'], $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ec_state WHERE idcnt_sta = %d', $id ), ARRAY_A ) );
		$snap['zones'] = array_merge( $snap['zones'], $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ec_zone_to_location WHERE iso2_cnt = %s', $c['iso2_cnt'] ), ARRAY_A ) );
		do_action( 'wpeasycart_country_deleting', (int) $id );
		$wpdb->delete( 'ec_state', array( 'idcnt_sta' => (int) $id ) );
		$wpdb->delete( 'ec_zone_to_location', array( 'iso2_cnt' => $c['iso2_cnt'] ) );
		$wpdb->delete( 'ec_country', array( 'id_cnt' => (int) $id ) );
		do_action( 'wpeasycart_country_deleted', (int) $id );
	}
	wp_easycart_admin_country_table::flush();
	if ( empty( $snap['countries'] ) ) { wp_send_json_error( array( 'message' => __( 'Country not found.', 'wp-easycart' ) ) ); }
	$undo = 'cnt_' . time() . '_' . wp_rand( 100, 999 ); set_transient( 'ec_country_undo_' . $undo, $snap, 15 * MINUTE_IN_SECONDS );
	wp_send_json_success( array( 'undo' => $undo, 'message' => count( $names ) === 1 ? sprintf( __( 'Deleted %1$s with %2$d regions.', 'wp-easycart' ), $names[0], count( $snap['states'] ) ) : sprintf( __( 'Deleted %1$d countries and %2$d regions.', 'wp-easycart' ), count( $names ), count( $snap['states'] ) ) ) );
}

add_action( 'wp_ajax_ecv2_country_restore', 'ecv2_country_restore' );
function ecv2_country_restore() {
	ecv2_cnt_guard(); global $wpdb;
	$key = isset( $_POST['undo'] ) ? preg_replace( '/[^a-z0-9_]/', '', (string) $_POST['undo'] ) : ''; $snap = get_transient( 'ec_country_undo_' . $key );
	if ( ! $snap || ! is_array( $snap ) ) { wp_send_json_error( array( 'message' => __( 'This deletion can no longer be undone.', 'wp-easycart' ) ) ); }
	foreach ( $snap['countries'] as $c ) { $wpdb->replace( 'ec_country', $c ); }
	foreach ( $snap['states'] as $s ) { $wpdb->replace( 'ec_state', $s ); }
	foreach ( $snap['zones'] as $z ) { $wpdb->replace( 'ec_zone_to_location', $z ); }
	delete_transient( 'ec_country_undo_' . $key ); wp_easycart_admin_country_table::flush();
	wp_send_json_success( array( 'message' => sprintf( _n( 'Restored %d country.', 'Restored %d countries.', count( $snap['countries'] ), 'wp-easycart' ), count( $snap['countries'] ) ) ) );
}

add_action( 'wp_ajax_ecv2_country_restore_preview', 'ecv2_country_restore_preview' );
/**
 * Preview for "Restore defaults": how many default countries / regions are missing. Nothing is written.
 *
 * @since 6.0.0
 */
function ecv2_country_restore_preview() {
	ecv2_cnt_guard();
	$r = wp_easycart_admin_country_table::restore_defaults_run( true );
	if ( is_wp_error( $r ) ) {
		wp_send_json_error( array( 'message' => $r->get_error_message() ) );
	}
	wp_send_json_success(
		array(
			'countries'                => (int) $r['countries_added'],
			'regions'                  => (int) $r['states_added'],
			'default_countries'        => (int) $r['default_countries'],
			'default_regions'          => (int) $r['default_states'],
			'default_region_countries' => (int) $r['default_region_countries'],
		)
	);
}

add_action( 'wp_ajax_ecv2_country_restore_defaults', 'ecv2_country_restore_defaults' );
/**
 * Insert every missing default country and region ( ship-to off ) and report what was added or refused.
 *
 * @since 6.0.0 Returns counts and database failures instead of a fixed message.
 */
function ecv2_country_restore_defaults() {
	ecv2_cnt_guard();
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a few hundred inserts on a slow host; set_time_limit() warns where it is disabled.
	}
	$r = wp_easycart_admin_country_table::restore_defaults_run( false );
	if ( is_wp_error( $r ) ) {
		wp_send_json_error( array( 'message' => $r->get_error_message() ) );
	}
	wp_easycart_admin_country_table::flush();
	$countries = (int) $r['countries_added'];
	$regions   = (int) $r['states_added'];
	$failed    = (int) $r['countries_failed'] + (int) $r['states_failed'];
	if ( 0 === $countries && 0 === $regions && 0 === $failed ) {
		$message = __( 'Nothing to restore: every default country and region is already in your list.', 'wp-easycart' );
	} else {
		/* translators: 1: number of countries added, 2: number of regions added. */
		$message = sprintf( __( 'Added %1$s and %2$s, with ship-to off. Existing entries were not changed.', 'wp-easycart' ), sprintf( _n( '%d country', '%d countries', $countries, 'wp-easycart' ), $countries ), sprintf( _n( '%d region', '%d regions', $regions, 'wp-easycart' ), $regions ) );
	}
	if ( $failed ) {
		/* translators: 1: number of rows the database refused, 2: database error text. */
		$message .= ' ' . sprintf( _n( '%1$d entry could not be saved (%2$s).', '%1$d entries could not be saved (%2$s).', $failed, 'wp-easycart' ), $failed, $r['last_error'] ? $r['last_error'] : __( 'unknown database error', 'wp-easycart' ) );
	}
	wp_send_json_success(
		array(
			'message'   => $message,
			'countries' => $countries,
			'regions'   => $regions,
			'failed'    => $failed,
		)
	);
}
