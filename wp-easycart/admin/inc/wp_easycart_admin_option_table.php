<?php
/**
 * WP EasyCart Admin — Option Sets list ( V2 ).
 *
 * Extends wp_easycart_admin_table_v2. Inherits table/card/spreadsheet views,
 * health stat cards, pill filters, row menus, bulk actions, toasts.
 *
 * Row menu "Delete" opens the safe-delete modal ( wp_easycart_admin_safe_delete )
 * instead of the legacy two-statement delete. Bulk delete routes every id through
 * the same engine with the 'remove' strategy after a summary confirm.
 *
 * AJAX ( this file ): ecv2_option_toggle_required, ecv2_option_inline_update,
 *                     ecv2_option_bulk_delete.
 *
 * @since 5.x.x
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* The base list class normally arrives later in admin-init.php; load it here so this
   file is safe to include from a constructor regardless of order. The base is wrapped
   in class_exists(), so the later plain include() in admin-init is harmless. */
include_once( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_table_v2.php' );

if ( ! class_exists( 'wp_easycart_admin_option_table' ) ) :

	class wp_easycart_admin_option_table extends wp_easycart_admin_table_v2 {

		private $health_data = array();

		/** Type metadata: label, family ( variation|modifier ), takes a list of choices, dashicon. */
		public static function types() {
			return array(
				'basic-combo'  => array( 'label' => __( 'Dropdown', 'wp-easycart' ), 'family' => 'variation', 'list' => true, 'icon' => 'arrow-down-alt2' ),
				'basic-swatch' => array( 'label' => __( 'Swatch', 'wp-easycart' ), 'family' => 'variation', 'list' => true, 'icon' => 'art' ),
				'combo'        => array( 'label' => __( 'Dropdown (advanced)', 'wp-easycart' ), 'family' => 'modifier', 'list' => true, 'icon' => 'arrow-down-alt2' ),
				'swatch'       => array( 'label' => __( 'Swatch (advanced)', 'wp-easycart' ), 'family' => 'modifier', 'list' => true, 'icon' => 'art' ),
				'radio'        => array( 'label' => __( 'Radio group', 'wp-easycart' ), 'family' => 'modifier', 'list' => true, 'icon' => 'marker' ),
				'checkbox'     => array( 'label' => __( 'Checkbox group', 'wp-easycart' ), 'family' => 'modifier', 'list' => true, 'icon' => 'yes' ),
				'grid'         => array( 'label' => __( 'Quantity grid', 'wp-easycart' ), 'family' => 'modifier', 'list' => true, 'icon' => 'grid-view' ),
				'text'         => array( 'label' => __( 'Text input', 'wp-easycart' ), 'family' => 'modifier', 'list' => false, 'icon' => 'editor-textcolor' ),
				'textarea'     => array( 'label' => __( 'Text area', 'wp-easycart' ), 'family' => 'modifier', 'list' => false, 'icon' => 'editor-paragraph' ),
				'number'       => array( 'label' => __( 'Number', 'wp-easycart' ), 'family' => 'modifier', 'list' => false, 'icon' => 'editor-ol' ),
				'date'         => array( 'label' => __( 'Date', 'wp-easycart' ), 'family' => 'modifier', 'list' => false, 'icon' => 'calendar-alt' ),
				'file'         => array( 'label' => __( 'File upload', 'wp-easycart' ), 'family' => 'modifier', 'list' => false, 'icon' => 'upload' ),
				'dimensions1'  => array( 'label' => __( 'Dimensions (whole inch)', 'wp-easycart' ), 'family' => 'modifier', 'list' => false, 'icon' => 'editor-expand' ),
				'dimensions2'  => array( 'label' => __( 'Dimensions (sub-inch)', 'wp-easycart' ), 'family' => 'modifier', 'list' => false, 'icon' => 'editor-expand' ),
			);
		}

		public static function type_meta( $type ) {
			$t = self::types();
			/* Legacy typo tolerance: the old item list checked 'dimension1' / 'dimension2'. */
			if ( 'dimension1' === $type ) { $type = 'dimensions1'; }
			if ( 'dimension2' === $type ) { $type = 'dimensions2'; }
			return isset( $t[ $type ] ) ? $t[ $type ] : array( 'label' => $type, 'family' => 'modifier', 'list' => true, 'icon' => 'admin-generic' );
		}

		/**
		 * True when $type is a modifier ( advanced ) type and PRO isn't licensed: the set is read-only and
		 * can't be assigned, duplicated or have its requirement changed.
		 *
		 * @since 6.0.0
		 */
		public static function modifier_locked( $type ) {
			if ( null === $type ) {
				return false;
			}
			return class_exists( 'wp_easycart_admin_option_editor_v2' ) ? wp_easycart_admin_option_editor_v2::is_locked_type( $type ) : ! in_array( $type, array( 'basic-combo', 'basic-swatch' ), true );
		}

		/** Accepts an id or the literal '{id}' placeholder used by row-menu templates. */
		public static function editor_url( $option_id ) {
			$id = ( '{id}' === $option_id ) ? '{id}' : (int) $option_id;
			return admin_url( 'admin.php?page=wp-easycart-products&subpage=option&ec_admin_form_action=edit&option_id=' . $id );
		}

		public function __construct() {
			parent::__construct();
			$this->setup();
		}

		public function setup() {
			$this->set_table( 'ec_option', 'option_id' );
			$this->set_table_id( 'ec_admin_option_list_v2' );
			$this->set_default_sort( 'option_name', 'ASC' );
			$this->set_header( __( 'Option Sets', 'wp-easycart' ) );
			$this->set_docs_link( 'products', 'option-sets' );
			$this->set_add_new( true, 'add-new-option', __( 'Add option set', 'wp-easycart' ) );
			/* Existing V2 create slideout ( option-set-slideout-v2.js ) */
			$this->set_add_new_js( 'if ( window.ecosv2_open ) { ecosv2_open( { origin: \'standalone\', type: \'basic-combo\' } ); return false; }' );
			$this->set_add_new_css( 'ecv2-btn ecv2-btn-primary' );
			$this->set_label( __( 'Option set', 'wp-easycart' ), __( 'Option sets', 'wp-easycart' ) );
			$this->set_view_modes( array( 'table', 'card', 'spreadsheet' ) );
			$this->set_inline_editable_columns( array() );

			$products_sub = '( ( SELECT COUNT(*) FROM ec_product p WHERE p.option_id_1 = ec_option.option_id OR p.option_id_2 = ec_option.option_id OR p.option_id_3 = ec_option.option_id OR p.option_id_4 = ec_option.option_id OR p.option_id_5 = ec_option.option_id ) + ( SELECT COUNT(*) FROM ec_option_to_product otp WHERE otp.option_id = ec_option.option_id ) )';
			$this->set_list_columns( array(
				array( 'name' => 'option_name', 'label' => __( 'Option set', 'wp-easycart' ), 'format' => 'os_name', 'linked' => true ),
				array( 'name' => 'option_type', 'label' => __( 'Type', 'wp-easycart' ), 'format' => 'os_type' ),
				array( 'select' => '( SELECT GROUP_CONCAT( CONCAT( oi.optionitem_name, "' . chr( 31 ) . '", COALESCE( oi.optionitem_icon, "" ), "' . chr( 31 ) . '", oi.optionitem_price ) ORDER BY oi.optionitem_order SEPARATOR "' . chr( 30 ) . '" ) FROM ec_optionitem oi WHERE oi.option_id = ec_option.option_id ) AS choices_blob', 'name' => 'choices_blob', 'label' => __( 'Choices', 'wp-easycart' ), 'format' => 'os_choices', 'tablet_hide' => true ),
				array( 'select' => $products_sub . ' AS product_count', 'name' => 'product_count', 'label' => __( 'Used by', 'wp-easycart' ), 'format' => 'os_usage' ),
				array( 'name' => 'option_required', 'label' => __( 'Required', 'wp-easycart' ), 'format' => 'os_required' ),
				array( 'name' => 'option_id', 'label' => __( 'ID', 'wp-easycart' ), 'format' => 'int', 'is_id' => true, 'laptop_hide' => true ),
				array( 'name' => 'option_label', 'format' => 'hidden', 'label' => '' ),
				array( 'name' => 'square_id', 'format' => 'hidden', 'label' => '' ),
				array( 'select' => '( SELECT COUNT(*) FROM ec_optionitem oi2 WHERE oi2.option_id = ec_option.option_id ) AS choice_count', 'name' => 'choice_count', 'format' => 'hidden', 'label' => '' ),
				array( 'select' => '( SELECT COUNT(*) FROM ec_optionitem oi3 WHERE oi3.option_id = ec_option.option_id AND ( oi3.optionitem_icon IS NULL OR oi3.optionitem_icon = "" ) ) AS missing_swatch_count', 'name' => 'missing_swatch_count', 'format' => 'hidden', 'label' => '' ),
			) );

			$this->set_search_columns( array( 'ec_option.option_name', 'ec_option.option_label', 'ec_option.option_id' ) );

			$this->set_bulk_actions( apply_filters( 'wp_easycart_admin_bulk_option_options', array(
				array( 'name' => 'ecv2-option-require', 'label' => __( 'Set required', 'wp-easycart' ) ),
				array( 'name' => 'ecv2-option-optional', 'label' => __( 'Set optional', 'wp-easycart' ) ),
				array( 'name' => 'ecv2-option-delete', 'label' => __( 'Delete', 'wp-easycart' ) ),
			) ) );

			$this->set_row_menu_actions( array(
				array( 'label' => __( 'Edit option set', 'wp-easycart' ), 'name' => 'edit', 'icon' => 'edit', 'href' => self::editor_url( '{id}' ) ),
				array( 'label' => __( 'View products', 'wp-easycart' ), 'name' => 'products', 'icon' => 'products', 'href' => admin_url( 'admin.php?page=wp-easycart-products&subpage=products&option_set={id}' ) ),
				array( 'label' => __( 'Assign to products…', 'wp-easycart' ), 'name' => 'assign', 'icon' => 'plus-alt2', 'href' => '#', 'onclick' => 'ecv2_catalog.assign_option( {id} ); return false;' ),
				array( 'label' => __( 'Duplicate', 'wp-easycart' ), 'name' => 'duplicate', 'icon' => 'admin-page', 'action' => 'duplicate-option' ),
				array( 'label' => __( 'Delete', 'wp-easycart' ), 'name' => 'delete', 'icon' => 'trash', 'href' => '#', 'onclick' => 'ecv2_catalog.safe_delete( \'option\', {id} ); return false;', 'danger' => true ),
			) );

			$this->set_spreadsheet_columns( array(
				array( 'name' => 'option_name', 'label' => __( 'Name', 'wp-easycart' ), 'format' => 'string', 'ss_editable' => true ),
				array( 'name' => 'option_label', 'label' => __( 'Shopper label', 'wp-easycart' ), 'format' => 'string', 'ss_editable' => true ),
				array( 'name' => 'option_type', 'label' => __( 'Type', 'wp-easycart' ), 'format' => 'os_type_text' ),
				array( 'name' => 'choice_count', 'label' => __( 'Choices', 'wp-easycart' ), 'format' => 'int' ),
				array( 'name' => 'product_count', 'label' => __( 'Products', 'wp-easycart' ), 'format' => 'int' ),
				array( 'name' => 'option_required', 'label' => __( 'Required', 'wp-easycart' ), 'format' => 'os_required_sm' ),
				array( 'name' => 'option_id', 'label' => __( 'ID', 'wp-easycart' ), 'format' => 'int', 'is_id' => true ),
			) );

			$type_pills = array();
			foreach ( self::types() as $k => $t ) {
				$type_pills[] = (object) array( 'value' => $k, 'label' => $t['label'], 'icon' => $t['icon'] );
			}
			$this->set_filters( apply_filters( 'wp_easycart_admin_option_list_filters', array(
				array(
					'data'  => array(
						(object) array( 'value' => 'variation', 'label' => __( 'Variations (stock-tracked)', 'wp-easycart' ), 'icon' => 'screenoptions' ),
						(object) array( 'value' => 'modifier', 'label' => __( 'Modifiers', 'wp-easycart' ), 'icon' => 'admin-customizer' ),
					),
					'label' => __( 'Family', 'wp-easycart' ),
					'type'  => 'pills',
					'where_callback' => true,
				),
				array( 'data' => $type_pills, 'label' => __( 'Type', 'wp-easycart' ), 'type' => 'select', 'where' => 'ec_option.option_type = %s' ),
				array(
					'data'  => array(
						(object) array( 'value' => '1', 'label' => __( 'Required', 'wp-easycart' ), 'icon' => 'lock' ),
						(object) array( 'value' => '0', 'label' => __( 'Optional', 'wp-easycart' ), 'icon' => 'unlock' ),
					),
					'label' => __( 'Requirement', 'wp-easycart' ),
					'type'  => 'pills',
					'where' => 'ec_option.option_required = %d',
				),
				array(
					'data'  => array(
						(object) array( 'value' => 'used', 'label' => __( 'In use', 'wp-easycart' ), 'icon' => 'yes-alt' ),
						(object) array( 'value' => 'unused', 'label' => __( 'Unused', 'wp-easycart' ), 'icon' => 'marker' ),
					),
					'label' => __( 'Usage', 'wp-easycart' ),
					'type'  => 'pills',
					'where_callback' => true,
				),
			) ) );

			$this->compute_health_data();
			$h = $this->health_data;
			$stats = array(
				array( 'label' => __( 'All', 'wp-easycart' ), 'value' => $h['total'], 'filter_value' => '', 'color' => 'default', 'group' => 'catalog' ),
				array( 'label' => __( 'Variations', 'wp-easycart' ), 'value' => $h['variation'], 'filter_value' => 'variation', 'color' => 'green', 'group' => 'catalog' ),
				array( 'label' => __( 'Modifiers', 'wp-easycart' ), 'value' => $h['modifier'], 'filter_value' => 'modifier', 'color' => 'blue', 'group' => 'catalog' ),
				array( 'label' => __( 'In use', 'wp-easycart' ), 'value' => $h['used'], 'filter_value' => 'used', 'color' => 'green', 'group' => 'catalog' ),
				array( 'label' => __( 'Unused', 'wp-easycart' ), 'value' => $h['unused'], 'filter_value' => 'unused', 'color' => 'gray', 'group' => 'attention' ),
				array( 'label' => __( 'No choices', 'wp-easycart' ), 'value' => $h['empty'], 'filter_value' => 'empty', 'color' => 'amber', 'group' => 'attention' ),
				array( 'label' => __( 'Missing swatches', 'wp-easycart' ), 'value' => $h['missing_swatch'], 'filter_value' => 'missing_swatch', 'color' => 'amber', 'group' => 'attention' ),
			);
			$this->set_health_stats( $stats );
		}

		/* ------------------------------------------------------------------ */
		/* SQL fragments                                                       */
		/* ------------------------------------------------------------------ */

		private function used_sql() {
			return 'EXISTS ( SELECT 1 FROM ec_product p WHERE p.option_id_1 = ec_option.option_id OR p.option_id_2 = ec_option.option_id OR p.option_id_3 = ec_option.option_id OR p.option_id_4 = ec_option.option_id OR p.option_id_5 = ec_option.option_id ) OR EXISTS ( SELECT 1 FROM ec_option_to_product otp WHERE otp.option_id = ec_option.option_id )';
		}

		private function family_sql( $family ) {
			$in = array();
			foreach ( self::types() as $k => $t ) {
				if ( $t['family'] === $family ) { $in[] = "'" . esc_sql( $k ) . "'"; }
			}
			return 'ec_option.option_type IN ( ' . implode( ',', $in ) . ' )';
		}

		private function list_types_sql() {
			$in = array();
			foreach ( self::types() as $k => $t ) {
				if ( $t['list'] ) { $in[] = "'" . esc_sql( $k ) . "'"; }
			}
			return 'ec_option.option_type IN ( ' . implode( ',', $in ) . ' )';
		}

		private function compute_health_data() {
			global $wpdb;
			$used = $this->used_sql();
			$this->health_data = array(
				'total'          => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_option' ),
				// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- family_sql() / list_types_sql() build IN-lists only from the fixed keys of self::types(); used_sql() is a static fragment.
				'variation'      => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_option WHERE ' . $this->family_sql( 'variation' ) ),
				'modifier'       => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_option WHERE ' . $this->family_sql( 'modifier' ) ),
				'used'           => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_option WHERE ' . $used ),
				'unused'         => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_option WHERE NOT ( ' . $used . ' )' ),
				'empty'          => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_option WHERE ' . $this->list_types_sql() . ' AND NOT EXISTS ( SELECT 1 FROM ec_optionitem oi WHERE oi.option_id = ec_option.option_id )' ),
				// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
				'missing_swatch' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM ec_option WHERE option_type IN ('basic-swatch','swatch') AND EXISTS ( SELECT 1 FROM ec_optionitem oi WHERE oi.option_id = ec_option.option_id AND ( oi.optionitem_icon IS NULL OR oi.optionitem_icon = '' ) )" ),
			);
		}

		protected function get_health_filter_where( $filter_key ) {
			switch ( $filter_key ) {
				case 'variation': return $this->family_sql( 'variation' );
				case 'modifier': return $this->family_sql( 'modifier' );
				case 'used': return '( ' . $this->used_sql() . ' )';
				case 'unused': return 'NOT ( ' . $this->used_sql() . ' )';
				case 'empty': return $this->list_types_sql() . ' AND NOT EXISTS ( SELECT 1 FROM ec_optionitem oi WHERE oi.option_id = ec_option.option_id )';
				case 'missing_swatch': return "ec_option.option_type IN ('basic-swatch','swatch') AND EXISTS ( SELECT 1 FROM ec_optionitem oi WHERE oi.option_id = ec_option.option_id AND ( oi.optionitem_icon IS NULL OR oi.optionitem_icon = '' ) )";
			}
			return '';
		}

		protected function get_filter_callback_where( $filter_index, $value ) {
			if ( 0 === $filter_index ) {
				return in_array( $value, array( 'variation', 'modifier' ), true ) ? $this->family_sql( $value ) : '';
			}
			if ( 3 === $filter_index ) {
				return 'used' === $value ? '( ' . $this->used_sql() . ' )' : ( 'unused' === $value ? 'NOT ( ' . $this->used_sql() . ' )' : '' );
			}
			return '';
		}

		/* ------------------------------------------------------------------ */
		/* Cells                                                               */
		/* ------------------------------------------------------------------ */

		protected function print_table_row( $result ) {
			/* Dim unused sets like inactive products */
			$cls = ( isset( $result->product_count ) && 0 == $result->product_count ) ? ' ecv2-row-inactive' : '';
			echo '<tr class="ecv2-row' . esc_attr( $cls ) . '" data-id="' . esc_attr( $result->option_id ) . '">';
			echo '<td class="ecv2-col-check"><input type="checkbox" name="bulk[]" value="' . esc_attr( $result->option_id ) . '" class="ecv2-row-check" /></td>';
			foreach ( $this->list_columns as $col ) {
				if ( isset( $col['format'] ) && 'hidden' === $col['format'] ) { continue; }
				$extra = '';
				if ( ! empty( $col['tablet_hide'] ) ) { $extra .= ' ecv2-hide-tablet'; }
				if ( ! empty( $col['laptop_hide'] ) ) { $extra .= ' ecv2-hide-laptop'; }
				echo '<td class="ecv2-cell ecv2-cell-' . esc_attr( $col['name'] ) . $extra . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $extra is only static literal class names.
				$this->print_cell_content( $result, $col );
				echo '</td>';
			}
			echo '<td class="ecv2-col-actions">';
			$this->print_row_actions( $result );
			echo '</td></tr>';
		}

		public static function parse_choices( $blob ) {
			$out = array();
			if ( null === $blob || '' === $blob ) { return $out; }
			foreach ( explode( "\x1E", $blob ) as $c ) {
				$p = explode( "\x1F", $c );
				$out[] = array( 'name' => isset( $p[0] ) ? $p[0] : '', 'icon' => isset( $p[1] ) ? $p[1] : '', 'price' => isset( $p[2] ) ? (float) $p[2] : 0 );
			}
			return $out;
		}

		protected function print_cell_content( $result, $col ) {
			switch ( $col['format'] ) {
				case 'os_name':
					$label = isset( $result->option_label ) ? trim( wp_strip_all_tags( wp_unslash( $result->option_label ) ) ) : '';
					echo '<a href="' . esc_url( self::editor_url( $result->option_id ) ) . '" class="ecv2-link-primary ecv2-title-link">' . esc_html( wp_unslash( $result->option_name ) ) . '</a>';
					if ( '' !== $label && $label !== $result->option_name ) {
						/* translators: %s: shopper-facing label */
						echo '<span class="ecv2-sub">' . esc_html( sprintf( __( 'Shown as “%s”', 'wp-easycart' ), $label ) ) . '</span>';
					} else if ( isset( $result->choice_count ) && 0 == $result->choice_count && self::type_meta( $result->option_type )['list'] ) {
						echo '<span class="ecv2-sub ecv2-sub-warn">' . esc_html__( 'No choices yet — shoppers see an empty selector', 'wp-easycart' ) . '</span>';
					}
					break;

				case 'os_type':
					$m = self::type_meta( $result->option_type );
					echo '<span class="ecv2-type-chip"><span class="dashicons dashicons-' . esc_attr( $m['icon'] ) . '"></span>' . esc_html( $m['label'] ) . '</span>';
					if ( 'modifier' === $m['family'] ) {
						echo ' <span class="ecv2-chip ecv2-chip-blue" title="' . esc_attr__( 'Modifier type — does not create stock-tracked variants', 'wp-easycart' ) . '">' . esc_html( wp_easycart_admin_edition::badge( 'pro' ) ) . '</span>';
					}
					if ( in_array( $result->option_type, array( 'basic-swatch', 'swatch' ), true ) && ! empty( $result->missing_swatch_count ) ) {
						echo ' <span class="ecv2-chip ecv2-chip-amber">' . esc_html( sprintf( _n( '%d missing image', '%d missing images', $result->missing_swatch_count, 'wp-easycart' ), $result->missing_swatch_count ) ) . '</span>';
					}
					break;

				case 'os_choices':
					$m = self::type_meta( $result->option_type );
					$choices = self::parse_choices( isset( $result->choices_blob ) ? $result->choices_blob : '' );
					if ( ! $m['list'] ) {
						echo '<span class="ecv2-sub">' . esc_html__( 'Free-form input', 'wp-easycart' ) . '</span>';
						break;
					}
					if ( empty( $choices ) ) {
						echo '<a class="ecv2-btn ecv2-btn-sm" href="' . esc_url( self::editor_url( $result->option_id ) . '#osv2-choices' ) . '">' . esc_html__( 'Add choices', 'wp-easycart' ) . '</a>';
						break;
					}
					$is_swatch = in_array( $result->option_type, array( 'basic-swatch', 'swatch' ), true );
					echo '<div class="ecv2-choices">';
					$shown = 0;
					foreach ( $choices as $c ) {
						if ( $shown >= 5 ) { break; }
						if ( $is_swatch ) {
							$style = $this->swatch_style( $c['icon'] );
							echo '<span class="ecv2-swatch-dot' . ( '' === $c['icon'] ? ' is-missing' : '' ) . '" style="' . esc_attr( $style ) . '" title="' . esc_attr( $c['name'] . ( '' === $c['icon'] ? ' — ' . __( 'no image', 'wp-easycart' ) : '' ) ) . '"></span>';
						} else {
							echo '<span class="ecv2-choice">' . esc_html( $c['name'] );
							if ( 0 != $c['price'] ) {
								echo ' <b>' . esc_html( ( $c['price'] > 0 ? '+' : '' ) . $GLOBALS['currency']->get_currency_display( $c['price'] ) ) . '</b>';
							}
							echo '</span>';
						}
						$shown++;
					}
					if ( count( $choices ) > $shown ) {
						echo '<span class="ecv2-choice ecv2-choice-more">+' . esc_html( count( $choices ) - $shown ) . '</span>';
					}
					echo '</div>';
					break;

				case 'os_usage':
					$n = (int) $result->product_count;
					if ( $n ) {
						echo '<a class="ecv2-usage" href="' . esc_url( admin_url( 'admin.php?page=wp-easycart-products&subpage=products&option_set=' . (int) $result->option_id ) ) . '"><span class="dashicons dashicons-products"></span>' . esc_html( sprintf( _n( '%d product', '%d products', $n, 'wp-easycart' ), $n ) ) . '</a>';
					} else {
						echo '<span class="ecv2-usage ecv2-usage-zero">' . esc_html__( 'Not used', 'wp-easycart' ) . '</span>';
					}
					break;

				case 'os_required':
				case 'os_required_sm':
					$sm = ( 'os_required_sm' === $col['format'] ) ? ' ecv2-toggle-sm' : '';
					$locked = in_array( $result->option_type, array( 'basic-combo', 'basic-swatch' ), true );
					if ( ! $locked && self::modifier_locked( $result->option_type ) ) {
						/* Modifier set without PRO: show the state, clicking opens the upsell. */
						echo '<label class="ecv2-toggle' . $sm . ' ecv2-toggle-locked" title="' . esc_attr( wp_easycart_admin_option_editor_v2::lock_message() ) . '" onclick="' . esc_attr( 'ecdv2_upsell( { context: \'products\', feature: \'modifiers\' } ); return false;' ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $sm is a static literal class name or ''.
						echo '<input type="checkbox" data-id="' . esc_attr( $result->option_id ) . '"' . ( $result->option_required ? ' checked' : '' ) . ' disabled />';
						echo '<span class="ecv2-toggle-slider"></span></label>';
						break;
					}
					echo '<label class="ecv2-toggle' . $sm . ( $locked ? ' ecv2-toggle-locked' : '' ) . '" title="' . ( $locked ? esc_attr__( 'Variation sets are always required', 'wp-easycart' ) : esc_attr__( 'Toggle required', 'wp-easycart' ) ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $sm is a static literal class name or ''.
					echo '<input type="checkbox" class="ecv2-option-required-toggle" data-id="' . esc_attr( $result->option_id ) . '"' . ( $result->option_required ? ' checked' : '' ) . ( $locked ? ' disabled' : '' ) . ' />';
					echo '<span class="ecv2-toggle-slider"></span></label>';
					break;

				case 'os_type_text':
					echo esc_html( self::type_meta( $result->option_type )['label'] );
					break;

				default:
					parent::print_cell_content( $result, $col );
			}
		}

		/** Modifier sets without PRO: "Assign" and "Duplicate" open the upsell instead ( the server refuses them too ). */
		protected function print_row_menu_item( $result, $action ) {
			if ( isset( $action['name'] ) && in_array( $action['name'], array( 'assign', 'duplicate' ), true ) && self::modifier_locked( $result->option_type ) ) {
				echo '<a href="#" class="ecv2-row-menu-item" title="' . esc_attr( wp_easycart_admin_option_editor_v2::lock_message() ) . '" onclick="' . esc_attr( 'ecdv2_upsell( { context: \'products\', feature: \'modifiers\' } ); return false;' ) . '">';
				echo '<span class="dashicons dashicons-lock"></span> ' . esc_html( $action['label'] ) . ' <span class="ecv2-chip ecv2-chip-blue">' . esc_html( wp_easycart_admin_edition::badge( 'pro' ) ) . '</span>';
				echo '</a>';
				return;
			}
			parent::print_row_menu_item( $result, $action );
		}

		protected function print_spreadsheet_cell( $result, $col ) {
			$this->print_cell_content( $result, $col );
		}

		private function swatch_style( $icon ) {
			if ( '' === $icon || null === $icon ) {
				return 'background:repeating-linear-gradient(45deg,#fff 0 3px,#e5e7eb 3px 6px)';
			}
			$colors = class_exists( 'ec_optionitem' ) ? ec_optionitem::swatch_colors( $icon ) : wp_easycart_admin_option_editor_v2::swatch_colors_fallback( $icon );
			if ( $colors ) {
				return 2 === count( $colors ) ? 'background:linear-gradient(135deg,' . $colors[0] . ' 50%,' . $colors[1] . ' 50%)' : 'background:' . $colors[0];
			}
			return 'background-image:url(' . esc_url( $this->swatch_url( $icon ) ) . ');background-size:cover';
		}

		private function swatch_url( $icon ) {
			if ( 0 === strpos( $icon, 'http' ) ) { return $icon; }
			return plugins_url( '/wp-easycart-data/products/swatches/' . ltrim( $icon, '/' ), EC_PLUGIN_DATA_DIRECTORY );
		}

		protected function print_card( $result ) {
			$m = self::type_meta( $result->option_type );
			$choices = self::parse_choices( isset( $result->choices_blob ) ? $result->choices_blob : '' );
			$is_swatch = in_array( $result->option_type, array( 'basic-swatch', 'swatch' ), true );
			echo '<div class="ecv2-card ecv2-os-card' . ( 0 == $result->product_count ? ' ecv2-card-inactive' : '' ) . '" data-id="' . esc_attr( $result->option_id ) . '">';
			echo '<div class="ecv2-card-body">';
			echo '<div class="ecv2-os-card-head"><h3 class="ecv2-card-title"><a href="' . esc_url( self::editor_url( $result->option_id ) ) . '">' . esc_html( wp_unslash( $result->option_name ) ) . '</a></h3><span class="ecv2-type-chip"><span class="dashicons dashicons-' . esc_attr( $m['icon'] ) . '"></span>' . esc_html( $m['label'] ) . '</span></div>';
			echo '<span class="ecv2-sub">' . esc_html( $result->option_required ? __( 'Required', 'wp-easycart' ) : __( 'Optional', 'wp-easycart' ) ) . ' · ' . esc_html( sprintf( _n( '%d choice', '%d choices', (int) $result->choice_count, 'wp-easycart' ), (int) $result->choice_count ) ) . '</span>';
			echo '<div class="ecv2-choices ecv2-choices-card">';
			foreach ( array_slice( $choices, 0, 8 ) as $c ) {
				if ( $is_swatch ) {
					echo '<span class="ecv2-swatch-dot ecv2-swatch-dot-lg' . ( '' === $c['icon'] ? ' is-missing' : '' ) . '" style="' . esc_attr( $this->swatch_style( $c['icon'] ) ) . '" title="' . esc_attr( $c['name'] ) . '"></span>';
				} else {
					echo '<span class="ecv2-choice">' . esc_html( $c['name'] ) . '</span>';
				}
			}
			if ( count( $choices ) > 8 ) { echo '<span class="ecv2-choice ecv2-choice-more">+' . esc_html( count( $choices ) - 8 ) . '</span>'; }
			echo '</div></div>';
			echo '<div class="ecv2-card-footer"><input type="checkbox" name="bulk[]" value="' . esc_attr( $result->option_id ) . '" class="ecv2-row-check" /> <span class="ecv2-sub">' . esc_html( $result->product_count ? sprintf( _n( '%d product', '%d products', (int) $result->product_count, 'wp-easycart' ), (int) $result->product_count ) : __( 'Not used', 'wp-easycart' ) ) . '</span>';
			$this->print_row_actions( $result );
			echo '</div></div>';
		}
	}

endif;

/* ---------------------------------------------------------------------- */
/* AJAX                                                                    */
/* ---------------------------------------------------------------------- */

add_action( 'wp_ajax_ecv2_option_toggle_required', 'ecv2_option_toggle_required' );
function ecv2_option_toggle_required() {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
	}
	check_ajax_referer( 'wp-easycart-ecv2-inline-update', 'wp_easycart_nonce' );
	global $wpdb;
	$id  = isset( $_POST['option_id'] ) ? (int) $_POST['option_id'] : 0;
	$val = ! empty( $_POST['required'] ) && '0' !== $_POST['required'] ? 1 : 0;
	$type = $wpdb->get_var( $wpdb->prepare( 'SELECT option_type FROM ec_option WHERE option_id = %d', $id ) );
	if ( ! $type ) { wp_send_json_error( array( 'message' => __( 'Not found.', 'wp-easycart' ) ) ); }
	if ( wp_easycart_admin_option_table::modifier_locked( $type ) ) {
		wp_send_json_error( array( 'message' => wp_easycart_admin_option_editor_v2::lock_message(), 'code' => 'pro_required' ) );
	}
	if ( in_array( $type, array( 'basic-combo', 'basic-swatch' ), true ) ) { $val = 1; }
	$wpdb->update( 'ec_option', array( 'option_required' => $val ), array( 'option_id' => $id ) );
	do_action( 'wp_easycart_optionset_updated', $id );
	wp_send_json_success( array( 'required' => $val ) );
}

add_action( 'wp_ajax_ecv2_option_inline_update', 'ecv2_option_inline_update' );
function ecv2_option_inline_update() {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
	}
	check_ajax_referer( 'wp-easycart-ecv2-inline-update', 'wp_easycart_nonce' );
	global $wpdb;
	$id    = isset( $_POST['option_id'] ) ? (int) $_POST['option_id'] : 0;
	$field = isset( $_POST['field'] ) ? sanitize_key( $_POST['field'] ) : '';
	$value = isset( $_POST['value'] ) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '';
	if ( ! $id || ! in_array( $field, array( 'option_name', 'option_label' ), true ) ) {
		wp_send_json_error( array( 'message' => __( 'Field not allowed.', 'wp-easycart' ) ) );
	}
	if ( 'option_name' === $field && '' === $value ) {
		wp_send_json_error( array( 'message' => __( 'Name cannot be empty.', 'wp-easycart' ) ) );
	}
	if ( wp_easycart_admin_option_table::modifier_locked( $wpdb->get_var( $wpdb->prepare( 'SELECT option_type FROM ec_option WHERE option_id = %d', $id ) ) ) ) {
		wp_send_json_error( array( 'message' => wp_easycart_admin_option_editor_v2::lock_message(), 'code' => 'pro_required' ) );
	}
	$old = $wpdb->get_var( $wpdb->prepare( "SELECT `$field` FROM ec_option WHERE option_id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $field is strictly whitelisted to option_name / option_label above.
	$wpdb->update( 'ec_option', array( $field => $value ), array( 'option_id' => $id ) );
	do_action( 'wp_easycart_optionset_updated', $id );
	wp_send_json_success( array( 'display_value' => $value, 'old_value' => $old ) );
}

/** Bulk: require / optional / delete ( delete goes through safe-delete with 'remove' ). */
add_action( 'wp_ajax_ecv2_option_bulk', 'ecv2_option_bulk' );
function ecv2_option_bulk() {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
	}
	check_ajax_referer( 'wp-easycart-ecv2-inline-update', 'wp_easycart_nonce' );
	global $wpdb;
	$ids = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ? array_slice( array_map( 'intval', $_POST['ids'] ), 0, 200 ) : array();
	$op  = isset( $_POST['op'] ) ? sanitize_key( $_POST['op'] ) : '';
	if ( empty( $ids ) ) { wp_send_json_error( array( 'message' => __( 'Nothing selected.', 'wp-easycart' ) ) ); }
	$done = 0; $trash = array(); $errors = array();
	foreach ( $ids as $id ) {
		if ( 'require' === $op || 'optional' === $op ) {
			if ( wp_easycart_admin_option_table::modifier_locked( $wpdb->get_var( $wpdb->prepare( 'SELECT option_type FROM ec_option WHERE option_id = %d', $id ) ) ) ) {
				$errors['pro'] = wp_easycart_admin_option_editor_v2::lock_message();
				continue;
			}
			$wpdb->update( 'ec_option', array( 'option_required' => 'require' === $op ? 1 : 0 ), array( 'option_id' => $id ) );
			$done++;
		} else if ( 'delete' === $op ) {
			$r = wp_easycart_admin_safe_delete()->execute( 'option', $id, array( 'strategy' => 'remove' ) );
			if ( is_wp_error( $r ) ) { $errors[] = $r->get_error_message(); } else { $done++; $trash[] = $r['trash_id']; }
		}
	}
	wp_send_json_success( array( 'done' => $done, 'trash_ids' => $trash, 'errors' => array_values( $errors ) ) );
}
