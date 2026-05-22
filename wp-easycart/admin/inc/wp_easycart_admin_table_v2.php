<?php
/**
 * WP EasyCart Admin Table V2 - Base Class
 *
 * Modern, reusable admin table with support for:
 * - Table, Card, and Spreadsheet view modes
 * - Health dashboard stat cards
 * - Pill-based filters
 * - Inline editing
 * - Bulk edit modal
 * - Toast notifications
 * - Undo history
 *
 * Extended by page-specific classes (e.g., wp_easycart_admin_product_table)
 * to add custom columns, views, and AJAX endpoints.
 *
 * @since 5.x.x
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_table_v2' ) ) :

	class wp_easycart_admin_table_v2 {

		protected $wpdb;

		/* Core table config */
		protected $table;
		protected $table_id;
		protected $key;
		protected $custom_header;
		protected $icon;

		/* Add new */
		protected $add_new = true;
		protected $add_new_action = 'add-new';
		protected $add_new_label;
		protected $add_new_reset = false;
		protected $add_new_reset_var = '';
		protected $add_new_reset_val = '';
		protected $add_new_js = '';
		protected $add_new_css = 'ec_page_title_button ec_admin_process_click';

		/* Cancel */
		protected $cancel = false;
		protected $cancel_link = '';
		protected $cancel_label;

		/* Docs */
		protected $docs_guide;
		protected $docs_link;

		/* Sorting */
		protected $current_sort_column;
		protected $default_sort_column;
		protected $current_sort_direction;
		protected $default_sort_direction;

		/* Columns */
		protected $list_columns = array();
		protected $search_columns = array();

		/* Pagination */
		protected $current_page;
		protected $perpage;
		protected $perpage_options;

		/* Actions */
		protected $bulk_actions;
		protected $bulk_variables;
		protected $actions;

		/* Filters */
		protected $filters = array();
		protected $search_term;
		protected $search_disabled = false;

		/* Labels */
		protected $item_label;
		protected $item_label_plural;

		/* Data */
		protected $record_count = 0;
		protected $showing = 0;
		protected $total_pages;
		protected $results;

		/* Joins / custom SQL */
		protected $custom_join = '';
		protected $join = '';
		protected $custom_where = '';
		protected $custom_select = '';

		/* Importer */
		protected $importer = false;
		protected $importer_button;

		/* Misc */
		protected $sortable = false;
		protected $get_vars = array();
		protected $page_url;
		protected $query_params;
		protected $date_diff;

		/* V2-specific config */
		protected $view_modes = array( 'table', 'card', 'spreadsheet' );
		protected $current_view_mode = 'table';
		protected $health_stats = array();
		protected $inline_editable_columns = array();
		protected $spreadsheet_columns = array();
		protected $completeness_fields = array();
		protected $row_menu_actions = array();
		protected $bulk_edit_fields = array();

		public function __construct() {
			global $wpdb;
			$this->wpdb = $wpdb;

			$this->add_new_label = __( 'Add New', 'wp-easycart' );
			$this->cancel_label = __( 'Cancel', 'wp-easycart' );

			// Calculate date offset for display.
			$now_server = $this->wpdb->get_var( 'SELECT NOW() AS the_time' );
			$now_timestamp = strtotime( $now_server );
			$now_gmt_timestamp = time();
			$storage_offset = $now_timestamp - $now_gmt_timestamp;
			$local_offset = get_option( 'gmt_offset' ) * 60 * 60;
			$this->date_diff = $local_offset - $storage_offset;

			// Read GET params for sorting / paging.
			if ( isset( $_GET['orderby'] ) && '' != $_GET['orderby'] ) {
				$this->current_sort_column = sanitize_text_field( preg_replace( '/[^a-zA-Z0-9\_\.]/', '', wp_unslash( $_GET['orderby'] ) ) );
			}
			$this->current_sort_direction = ( isset( $_GET['order'] ) && 'desc' == strtolower( $_GET['order'] ) ) ? 'desc' : 'asc';

			if ( isset( $_GET['pagenum'] ) && '' != $_GET['pagenum'] ) {
				$this->current_page = (int) $_GET['pagenum'];
			} else {
				$this->current_page = 1;
			}
			if ( isset( $_GET['perpage'] ) ) {
				$this->perpage = (int) $_GET['perpage'];
			} else if ( isset( $_COOKIE['wpeasycart_admin_perpage'] ) ) {
				$this->perpage = (int) $_COOKIE['wpeasycart_admin_perpage'];
			} else {
				$this->perpage = 25;
			}

			// Default bulk actions.
			$this->bulk_actions = array(
				array( 'name' => 'delete', 'label' => __( 'Delete', 'wp-easycart' ) ),
				array( 'name' => 'export', 'label' => __( 'Export', 'wp-easycart' ) ),
			);

			// Parse URL.
			$uri_parts = explode( '?', sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), 2 );
			$this->page_url = $uri_parts[0];
			$this->query_params = array();
			if ( isset( $uri_parts[1] ) ) {
				$params = explode( '&', $uri_parts[1] );
				foreach ( $params as $param ) {
					$this->query_params[] = explode( '=', $param );
				}
			}
			$this->perpage_options = array( 10, 25, 50, 100, 250, 500 );

			// View mode from GET or cookie.
			if ( isset( $_GET['view_mode'] ) && in_array( $_GET['view_mode'], $this->view_modes, true ) ) {
				$this->current_view_mode = sanitize_key( $_GET['view_mode'] );
			} else if ( isset( $_COOKIE['wpeasycart_admin_view_mode'] ) && in_array( $_COOKIE['wpeasycart_admin_view_mode'], $this->view_modes, true ) ) {
				$this->current_view_mode = sanitize_key( $_COOKIE['wpeasycart_admin_view_mode'] );
			}
		}

		public function set_table( $table, $key ) {
			$this->table = $table;
			$this->key = $key;
		}
		public function set_table_id( $table_id ) {
			$this->table_id = $table_id;
		}
		public function set_default_sort( $default_sort_column, $default_sort_direction ) {
			$this->default_sort_column = $default_sort_column;
			$this->default_sort_direction = $default_sort_direction;
		}
		public function set_header( $header ) {
			$this->custom_header = $header;
		}
		public function set_icon( $icon ) {
			$this->icon = $icon;
		}
		public function set_add_new( $add_new, $add_new_action = '', $add_new_label = '', $add_new_reset = false, $add_new_reset_var = '', $add_new_reset_val = '' ) {
			$this->add_new = $add_new;
			$this->add_new_action = $add_new_action;
			$this->add_new_label = $add_new_label;
			$this->add_new_reset = $add_new_reset;
			$this->add_new_reset_var = $add_new_reset_var;
			$this->add_new_reset_val = $add_new_reset_val;
		}
		public function set_add_new_js( $add_new_js ) {
			$this->add_new_js = $add_new_js;
		}
		public function set_add_new_css( $add_new_css ) {
			$this->add_new_css = $add_new_css;
		}
		public function set_cancel( $cancel, $cancel_link, $cancel_label ) {
			$this->cancel = $cancel;
			$this->cancel_link = $cancel_link;
			$this->cancel_label = $cancel_label;
		}
		public function set_list_columns( $list_columns ) {
			$this->list_columns = $list_columns;
		}
		public function set_search_columns( $search_columns ) {
			$this->search_columns = $search_columns;
		}
		public function set_search_disabled( $search_disabled ) {
			$this->search_disabled = $search_disabled;
		}
		public function set_per_page( $per_page ) {
			$this->perpage = $per_page;
		}
		public function set_bulk_actions( $bulk_actions ) {
			$this->bulk_actions = $bulk_actions;
		}
		public function set_bulk_action_hidden_variables( $bulk_variables ) {
			$this->bulk_variables = $bulk_variables;
		}
		public function set_actions( $actions ) {
			$this->actions = $actions;
		}
		public function set_filters( $filters ) {
			$this->filters = $filters;
		}
		public function set_label( $single, $plural ) {
			$this->item_label = $single;
			$this->item_label_plural = $plural;
		}
		public function set_join( $join ) {
			$this->join = $join;
		}
		public function set_custom_where( $custom_where ) {
			$this->custom_where = $custom_where;
		}
		public function set_custom_select( $custom_select ) {
			$this->custom_select = $custom_select;
		}
		public function set_docs_link( $guide, $docs_link ) {
			$this->docs_guide = $guide;
			$this->docs_link = $docs_link;
		}
		public function set_importer( $importer, $importer_button ) {
			$this->importer = $importer;
			$this->importer_button = $importer_button;
		}
		public function set_get_vars( $get_vars ) {
			$this->get_vars = $get_vars;
		}
		public function set_sortable( $sortable ) {
			$this->sortable = $sortable;
		}

		/* V2-specific setters */
		public function set_view_modes( $modes ) {
			$this->view_modes = $modes;
		}
		public function set_health_stats( $stats ) {
			$this->health_stats = $stats;
		}
		public function set_inline_editable_columns( $columns ) {
			$this->inline_editable_columns = $columns;
		}
		public function set_spreadsheet_columns( $columns ) {
			$this->spreadsheet_columns = $columns;
		}
		public function set_completeness_fields( $fields ) {
			$this->completeness_fields = $fields;
		}
		public function set_row_menu_actions( $actions ) {
			$this->row_menu_actions = $actions;
		}
		public function set_bulk_edit_fields( $fields ) {
			$this->bulk_edit_fields = $fields;
		}

		public function print_table() {
			$this->get_data();

			echo '<div class="ecv2-wrap" data-table-id="' . esc_attr( $this->table_id ) . '" data-key="' . esc_attr( $this->key ) . '" data-view-mode="' . esc_attr( $this->current_view_mode ) . '">';

			$this->print_page_header();

			echo '<form id="ecv2-posts-filter" method="get">';
			$this->print_hidden_fields();

			$this->print_health_dashboard();
			$this->print_toolbar();

			// Table view.
			echo '<div class="ecv2-view ecv2-view-table" ' . ( $this->current_view_mode !== 'table' ? 'style="display:none;"' : '' ) . '>';
			$this->print_table_view();
			echo '</div>';

			// Card view.
			echo '<div class="ecv2-view ecv2-view-card" ' . ( $this->current_view_mode !== 'card' ? 'style="display:none;"' : '' ) . '>';
			$this->print_card_view();
			echo '</div>';

			// Spreadsheet view.
			echo '<div class="ecv2-view ecv2-view-spreadsheet" ' . ( $this->current_view_mode !== 'spreadsheet' ? 'style="display:none;"' : '' ) . '>';
			$this->print_spreadsheet_view();
			echo '</div>';

			$this->print_pagination();
			echo '</form>';

			// Modals.
			$this->print_bulk_edit_modal();
			$this->print_confirm_dialog();
			$this->print_custom_modals();
			do_action( 'wp_easycart_admin_ecv2_render_modals', $this->table_id );

			// Toast container.
			echo '<div id="ecv2-toast-container"></div>';

			// Undo button (hidden by default).
			echo '<div id="ecv2-undo-bar" style="display:none;">';
			echo '<span id="ecv2-undo-message"></span>';
			echo '<button type="button" id="ecv2-undo-button" class="ecv2-btn ecv2-btn-sm">' . esc_html__( 'Undo', 'wp-easycart' ) . '</button>';
			echo '</div>';

			echo '</div>'; // .ecv2-wrap
		}

		protected function print_page_header() {
			echo '<div class="ecv2-page-header">';
			echo '<div class="ecv2-page-header-left">';
			echo '<h1 class="ecv2-page-title">';
			if ( isset( $this->icon ) ) {
				echo '<span class="dashicons dashicons-' . esc_attr( $this->icon ) . '"></span> ';
			}
			echo esc_html( isset( $this->custom_header ) ? $this->custom_header : $this->item_label_plural );
			echo '</h1>';
			echo '<span class="ecv2-record-count">' . esc_html( $this->record_count ) . ' ' . esc_html( $this->record_count == 1 ? $this->item_label : $this->item_label_plural ) . '</span>';
			echo '</div>'; // .ecv2-page-header-left

			echo '<div class="ecv2-page-header-right">';

			// Keyboard hint.
			echo '<span class="ecv2-keyboard-hint"><span class="dashicons dashicons-info-outline"></span> ' . esc_html__( 'Double-click any cell to edit inline', 'wp-easycart' ) . '</span>';

			// Help link.
			if ( isset( $this->docs_guide ) ) {
				echo '<a href="' . esc_url( wp_easycart_admin()->helpsystem->print_docs_url( $this->docs_guide, $this->docs_link, 'master-record' ) ) . '" target="_blank" class="ecv2-btn ecv2-btn-ghost ecv2-btn-sm"><span class="dashicons dashicons-editor-help"></span> ' . esc_html__( 'Help', 'wp-easycart' ) . '</a>';
			}

			// Importer.
			if ( $this->importer ) {
				$subpage = isset( $_GET['subpage'] ) ? sanitize_key( $_GET['subpage'] ) : 'products';
				echo '<a onclick="ec_admin_importer_open_close(\'' . esc_attr( $subpage ) . '_importer\');" class="ecv2-btn ecv2-btn-ghost ecv2-btn-sm"><span class="dashicons dashicons-upload"></span> ' . esc_html( $this->importer_button ) . '</a>';
			}

			// Add new.
			if ( $this->add_new ) {
				echo '<a href="' . esc_url( $this->get_url( 'ec_admin_form_action', $this->add_new_action, $this->add_new_reset, $this->add_new_reset_var, $this->add_new_reset_val ) ) . '" class="ecv2-btn ecv2-btn-primary"' . ( $this->add_new_js ? ' onclick="' . esc_attr( $this->add_new_js ) . '"' : '' ) . '><span class="dashicons dashicons-plus-alt2"></span> ' . esc_html( $this->add_new_label ) . '</a>';
			}

			echo '</div>'; // .ecv2-page-header-right
			echo '</div>'; // .ecv2-page-header

			// Print importer form if active (reuses v1 markup for compatibility).
			if ( $this->importer ) {
				$subpage = isset( $_GET['subpage'] ) ? sanitize_key( $_GET['subpage'] ) : 'products';
				echo '<div id="' . esc_attr( $subpage ) . '_importer" class="ec_importer_form">';
				echo '<a href="https://docs.wpeasycart.com/docs/administrative-console-guide/product-importer/" target="_blank" class="ec_admin_importer_help_link">' . esc_html__( 'Need Help?', 'wp-easycart' ) . '</a>';
				echo ' <input type="hidden" name="' . esc_attr( $subpage ) . '_import_file" id="' . esc_attr( $subpage ) . '_import_file" class="wpec-admin-upload-input" />';
				echo '<input type="button" class="ecv2-btn ecv2-btn-sm" value="' . esc_attr__( 'Browse', 'wp-easycart' ) . '" id="' . esc_attr( $subpage ) . '_browse_button" onclick="ec_admin_import_file_upload( \'' . esc_attr( $subpage ) . '_import_file\', \'' . esc_attr( $subpage ) . '_import_button\', \'' . esc_attr( $subpage ) . '_importer_status\', \'' . esc_attr( $subpage ) . '_browse_button\', \'' . esc_attr__( 'Browse', 'wp-easycart' ) . '\');" />';
				echo '<input type="button" class="ecv2-btn ecv2-btn-primary ecv2-btn-sm" value="' . esc_attr__( 'Import File', 'wp-easycart' ) . '" id="' . esc_attr( $subpage ) . '_import_button" onclick="ec_admin_start_importer( \'' . esc_attr( $subpage ) . '_import_file\', \'' . esc_attr( $subpage ) . '_importer_status\', \'' . esc_attr( wp_create_nonce( 'wp-easycart-start-import' ) ) . '\' );" />';
				echo '</div>';
				echo '<div id="' . esc_attr( $subpage ) . '_importer_status" class="ec_importer_status"></div>';
			}
		}

		protected function print_health_dashboard() {
			if ( empty( $this->health_stats ) ) {
				return;
			}

			// Get hidden stats from user meta.
			$user_id    = get_current_user_id();
			$meta_key   = 'ecv2_hidden_stats_' . sanitize_key( $this->table_id );
			$hidden_raw = get_user_meta( $user_id, $meta_key, true );
			$hidden     = array();
			if ( is_array( $hidden_raw ) ) {
				$hidden = array_map( 'sanitize_key', $hidden_raw );
			}
			$all_hidden = ( isset( $hidden[0] ) && '__all' === $hidden[0] );

			// Stat card visibility toggle bar.
			echo '<div class="ecv2-stat-toggle-bar">';
			echo '<button type="button" class="ecv2-stat-toggle-btn" id="ecv2-stat-toggle-btn" aria-expanded="false" title="' . esc_attr__( 'Show/hide stat tiles', 'wp-easycart' ) . '">';
			echo '<span class="dashicons dashicons-visibility"></span>';
			echo '</button>';

			// Inline toggle panel (hidden by default).
			echo '<div class="ecv2-stat-toggle-panel" id="ecv2-stat-toggle-panel">';

			// "Hide all" checkbox.
			echo '<label class="ecv2-stat-toggle-item ecv2-stat-toggle-hide-all">';
			echo '<input type="checkbox" class="ecv2-stat-toggle-cb" data-stat-key="__all"' . ( $all_hidden ? ' checked' : '' ) . ' /> ';
			echo '<span>' . esc_html__( 'Hide all', 'wp-easycart' ) . '</span>';
			echo '</label>';

			echo '<span class="ecv2-stat-toggle-sep"></span>';

			// Individual stat checkboxes (checked = visible).
			foreach ( $this->health_stats as $stat ) {
				$key     = $stat['filter_value'];
				$label   = $stat['label'];
				if ( '' === $key ) {
					$key = '__total';
				}
				$is_visible = ! $all_hidden && ! in_array( $key, $hidden, true );
				echo '<label class="ecv2-stat-toggle-item">';
				echo '<input type="checkbox" class="ecv2-stat-toggle-cb ecv2-stat-toggle-individual" data-stat-key="' . esc_attr( $key ) . '"' . ( $is_visible ? ' checked' : '' ) . ( $all_hidden ? ' disabled' : '' ) . ' /> ';
				echo '<span>' . esc_html( $label ) . '</span>';
				echo '</label>';
			}

			echo '</div>'; // .ecv2-stat-toggle-panel
			echo '</div>'; // .ecv2-stat-toggle-bar

			// Stat cards container.
			echo '<div class="ecv2-health-dashboard' . ( $all_hidden ? ' ecv2-stats-all-hidden' : '' ) . '" id="ecv2-health-dashboard">';
			foreach ( $this->health_stats as $stat ) {
				$key = $stat['filter_value'];
				if ( '' === $key ) {
					$key = '__total';
				}
				$active_class = '';
				if ( isset( $_GET['health_filter'] ) && $_GET['health_filter'] === $stat['filter_value'] ) {
					$active_class = ' ecv2-stat-active';
				}
				$hidden_class = '';
				if ( $all_hidden || in_array( $key, $hidden, true ) ) {
					$hidden_class = ' ecv2-stat-hidden';
				}
				echo '<div class="ecv2-stat-card' . esc_attr( $active_class ) . esc_attr( $hidden_class ) . ( isset( $stat['color'] ) ? ' ecv2-stat-' . esc_attr( $stat['color'] ) : '' ) . '" data-filter="' . esc_attr( $stat['filter_value'] ) . '" data-stat-key="' . esc_attr( $key ) . '">';
				echo '<div class="ecv2-stat-value">' . esc_html( $stat['value'] ) . '</div>';
				echo '<div class="ecv2-stat-label">' . esc_html( $stat['label'] ) . '</div>';
				echo '</div>';
			}
			echo '</div>';

			// Hidden field for the nonce (used by JS AJAX save).
			echo '<input type="hidden" id="ecv2-stat-toggle-nonce" value="' . esc_attr( wp_create_nonce( 'wp-easycart-ecv2-stat-toggle' ) ) . '" />';
			echo '<input type="hidden" id="ecv2-stat-toggle-table-id" value="' . esc_attr( $this->table_id ) . '" />';
		}

		protected function print_toolbar() {
			echo '<div class="ecv2-toolbar">';

			// Left: Bulk actions + Filters.
			echo '<div class="ecv2-toolbar-left">';
			$this->print_bulk_actions_toolbar();
			$this->print_filter_button();
			echo '</div>';

			// Right: Search + View toggles.
			echo '<div class="ecv2-toolbar-right">';
			if ( ! $this->search_disabled ) {
				$this->print_search_box();
			}
			$this->print_view_toggle();
			echo '</div>';

			echo '</div>'; // .ecv2-toolbar

			// Expandable filter panel.
			$this->print_filter_panel();
		}

		protected function print_bulk_actions_toolbar() {
			if ( ! isset( $this->bulk_actions ) || ! is_array( $this->bulk_actions ) || count( $this->bulk_actions ) == 0 ) {
				return;
			}
			echo '<div class="ecv2-bulk-actions">';
			echo '<span class="ecv2-bulk-count" style="display:none;"><span id="ecv2-selected-count">0</span> ' . esc_html__( 'selected', 'wp-easycart' ) . '</span>';
			echo '<select id="ecv2-bulk-action" name="ec_admin_form_action" class="ecv2-select">';
			echo '<option value="">' . esc_html__( 'Bulk Actions', 'wp-easycart' ) . '</option>';
			foreach ( $this->bulk_actions as $action ) {
				echo '<option value="' . esc_attr( $action['name'] ) . '">' . esc_html( $action['label'] ) . '</option>';
			}
			echo '</select>';
			echo '<button type="button" class="ecv2-btn ecv2-btn-sm" id="ecv2-bulk-apply" onclick="ecv2_bulk_apply_validate( this );">' . esc_html__( 'Apply', 'wp-easycart' ) . '</button>';

			// Bulk edit button (shown when items selected).
			if ( ! empty( $this->bulk_edit_fields ) ) {
				echo '<button type="button" class="ecv2-btn ecv2-btn-primary ecv2-btn-sm" id="ecv2-bulk-edit-btn" style="display:none;" onclick="ecv2_open_bulk_edit();">';
				echo '<span class="dashicons dashicons-edit"></span> ' . esc_html__( 'Bulk Edit', 'wp-easycart' ) . '</button>';
			}
			echo '</div>';
		}

		protected function print_filter_button() {
			if ( empty( $this->filters ) ) {
				return;
			}
			$active_count = 0;
			$active_tags = array();
			for ( $i = 0; $i < count( $this->filters ); $i++ ) {
				if ( isset( $_GET[ 'filter_' . $i ] ) && '' != sanitize_text_field( wp_unslash( $_GET[ 'filter_' . $i ] ) ) ) {
					$active_count++;
					$val = sanitize_text_field( wp_unslash( $_GET[ 'filter_' . $i ] ) );
					$label = $this->filters[ $i ]['label'];
					// Find the human-readable value.
					$val_label = $val;
					if ( isset( $this->filters[ $i ]['data'] ) && is_array( $this->filters[ $i ]['data'] ) ) {
						foreach ( $this->filters[ $i ]['data'] as $option ) {
							if ( isset( $option->value ) && (string) $option->value === $val ) {
								$val_label = isset( $option->label ) ? $option->label : $val;
								break;
							}
						}
					}
					$active_tags[] = array( 'index' => $i, 'group' => $label, 'value' => $val_label );
				}
			}
			if ( isset( $_GET['health_filter'] ) && '' != $_GET['health_filter'] ) {
				$active_count++;
			}
			echo '<button type="button" class="ecv2-btn ecv2-btn-ghost ecv2-btn-sm" id="ecv2-filter-toggle" onclick="ecv2_open_filter_drawer();">';
			echo '<span class="dashicons dashicons-filter"></span> ' . esc_html__( 'Filters', 'wp-easycart' );
			if ( $active_count > 0 ) {
				echo ' <span class="ecv2-filter-badge">' . esc_html( $active_count ) . '</span>';
			}
			echo '</button>';

			// Active filter tags shown inline in the toolbar (including health filter).
			$health_filter_val = isset( $_GET['health_filter'] ) ? sanitize_key( $_GET['health_filter'] ) : '';
			$has_any_tags = ! empty( $active_tags ) || '' !== $health_filter_val;

			// Active filter tags shown inline in the toolbar.
			if ( ! empty( $active_tags ) ) {
				echo '<div class="ecv2-active-filter-tags">';

				// Health filter tag.
				if ( '' !== $health_filter_val ) {
					$health_label = ucwords( str_replace( '_', ' ', $health_filter_val ) );
					// Match the label from the health stats config.
					if ( ! empty( $this->health_stats ) ) {
						foreach ( $this->health_stats as $stat ) {
							if ( $stat['filter_value'] === $health_filter_val ) {
								$health_label = $stat['label'];
								break;
							}
						}
					}
					echo '<span class="ecv2-active-tag">';
					echo '<span class="ecv2-active-tag-label">' . esc_html__( 'Quick Filter', 'wp-easycart' ) . ':</span> ';
					echo esc_html( $health_label );
					echo '<button type="button" class="ecv2-active-tag-remove" data-filter="health_filter" title="' . esc_attr__( 'Remove filter', 'wp-easycart' ) . '">&times;</button>';
					echo '</span>';
				}

				// Regular filter tags.
				foreach ( $active_tags as $tag ) {
					echo '<span class="ecv2-active-tag">';
					echo '<span class="ecv2-active-tag-label">' . esc_html( $tag['group'] ) . ':</span> ';
					echo esc_html( $tag['value'] );
					echo '<button type="button" class="ecv2-active-tag-remove" data-filter="filter_' . esc_attr( $tag['index'] ) . '" title="' . esc_attr__( 'Remove filter', 'wp-easycart' ) . '">&times;</button>';
					echo '</span>';
				}
				if ( $active_count > 1 ) {
					echo '<button type="button" class="ecv2-active-tag ecv2-active-tag-clear-all" onclick="ecv2_clear_filters();">' . esc_html__( 'Clear all', 'wp-easycart' ) . '</button>';
				}
				echo '</div>';
			}
		}

		protected function print_filter_panel() {
			if ( empty( $this->filters ) ) {
				return;
			}
			$active_count = 0;
			for ( $i = 0; $i < count( $this->filters ); $i++ ) {
				if ( isset( $_GET[ 'filter_' . $i ] ) && '' != sanitize_text_field( wp_unslash( $_GET[ 'filter_' . $i ] ) ) ) {
					$active_count++;
				}
			}
			if ( isset( $_GET['health_filter'] ) && '' != $_GET['health_filter'] ) {
				$active_count++;
			}

			// Backdrop overlay.
			echo '<div class="ecv2-drawer-backdrop" id="ecv2-drawer-backdrop"></div>';

			// Drawer.
			echo '<div class="ecv2-filter-drawer" id="ecv2-filter-drawer" role="dialog" aria-label="' . esc_attr__( 'Product Filters', 'wp-easycart' ) . '">';

			// Drawer header.
			echo '<div class="ecv2-drawer-header">';
			echo '<div class="ecv2-drawer-header-left">';
			echo '<span class="dashicons dashicons-filter ecv2-drawer-header-icon"></span>';
			echo '<h3 class="ecv2-drawer-title">' . esc_html__( 'Filters', 'wp-easycart' ) . '</h3>';
			if ( $active_count > 0 ) {
				echo '<span class="ecv2-drawer-active-badge">' . esc_html( $active_count ) . ' ' . esc_html__( 'active', 'wp-easycart' ) . '</span>';
			}
			echo '</div>';
			echo '<button type="button" class="ecv2-drawer-close" id="ecv2-drawer-close" aria-label="' . esc_attr__( 'Close filters', 'wp-easycart' ) . '">';
			echo '<span class="dashicons dashicons-no-alt"></span>';
			echo '</button>';
			echo '</div>';

			// Drawer body (scrollable).
			echo '<div class="ecv2-drawer-body">';

			// Quick Filter group — mirrors the health stat cards.
			if ( ! empty( $this->health_stats ) ) {
				$health_val = isset( $_GET['health_filter'] ) ? sanitize_key( $_GET['health_filter'] ) : '';
				$health_active = ( '' !== $health_val );

				echo '<div class="ecv2-drawer-filter-group' . ( $health_active ? ' ecv2-drawer-filter-group-active' : '' ) . '">';
				echo '<div class="ecv2-drawer-filter-label">';
				echo '<span>' . esc_html__( 'Quick Filter', 'wp-easycart' ) . '</span>';
				if ( $health_active ) {
					echo '<button type="button" class="ecv2-drawer-filter-clear ecv2-drawer-health-clear" title="' . esc_attr__( 'Clear this filter', 'wp-easycart' ) . '">';
					echo '<span class="dashicons dashicons-dismiss"></span>';
					echo '</button>';
				}
				echo '</div>';
				echo '<div class="ecv2-drawer-pills">';
				echo '<button type="button" class="ecv2-drawer-pill ecv2-drawer-health-pill' . ( '' === $health_val ? ' ecv2-drawer-pill-active' : '' ) . '" data-health-value="">' . esc_html__( 'All', 'wp-easycart' ) . '</button>';
				foreach ( $this->health_stats as $stat ) {
					if ( '' === $stat['filter_value'] ) {
						continue; // Skip the "Total" card.
					}
					echo '<button type="button" class="ecv2-drawer-pill ecv2-drawer-health-pill' . ( $health_val === $stat['filter_value'] ? ' ecv2-drawer-pill-active' : '' ) . '" data-health-value="' . esc_attr( $stat['filter_value'] ) . '">';
					echo esc_html( $stat['label'] );
					if ( (int) $stat['value'] > 0 ) {
						echo ' <span style="opacity:.6;">(' . esc_html( $stat['value'] ) . ')</span>';
					}
					echo '</button>';
				}
				echo '</div>';
				echo '</div>';
			}

			for ( $i = 0; $i < count( $this->filters ); $i++ ) {
				$filter = $this->filters[ $i ];
				$current_val = isset( $_GET[ 'filter_' . $i ] ) ? sanitize_text_field( wp_unslash( $_GET[ 'filter_' . $i ] ) ) : '';
				$filter_type = isset( $filter['type'] ) ? $filter['type'] : 'pills';
				$is_active = ( '' !== $current_val );

				echo '<div class="ecv2-drawer-filter-group' . ( $is_active ? ' ecv2-drawer-filter-group-active' : '' ) . '">';
				echo '<div class="ecv2-drawer-filter-label">';
				echo '<span>' . esc_html( $filter['label'] ) . '</span>';
				if ( $is_active ) {
					echo '<button type="button" class="ecv2-drawer-filter-clear" data-filter="filter_' . esc_attr( $i ) . '" title="' . esc_attr__( 'Clear this filter', 'wp-easycart' ) . '">';
					echo '<span class="dashicons dashicons-dismiss"></span>';
					echo '</button>';
				}
				echo '</div>';

				if ( $filter_type === 'select' ) {
					// Searchable select dropdown — isolated from v1 CSS.
					echo '<div class="ecv2-drawer-select-wrap">';
					echo '<select class="ecv2-filter-select ecv2-drawer-select" data-filter="filter_' . esc_attr( $i ) . '" data-placeholder="' . esc_attr( sprintf( __( 'Search %s...', 'wp-easycart' ), strtolower( $filter['label'] ) ) ) . '">';
					echo '<option value="">' . esc_html( sprintf( __( 'All %s', 'wp-easycart' ), $filter['label'] ) ) . '</option>';
					if ( isset( $filter['data'] ) && is_array( $filter['data'] ) ) {
						foreach ( $filter['data'] as $option ) {
							$val = isset( $option->value ) ? $option->value : '';
							$label = isset( $option->label ) ? $option->label : '';
							echo '<option value="' . esc_attr( $val ) . '"' . selected( $current_val, $val, false ) . '>' . esc_html( $label ) . '</option>';
						}
					}
					echo '</select>';
					echo '</div>';
				} elseif ( $filter_type === 'range' ) {
					// Price / numeric range inputs.
					$range_min = '';
					$range_max = '';
					if ( $current_val ) {
						$parts = explode( '-', $current_val, 2 );
						$range_min = isset( $parts[0] ) ? $parts[0] : '';
						$range_max = isset( $parts[1] ) ? $parts[1] : '';
					}
					echo '<div class="ecv2-drawer-range">';
					echo '<div class="ecv2-drawer-range-field">';
					echo '<label class="ecv2-drawer-range-label">' . esc_html__( 'Min', 'wp-easycart' ) . '</label>';
					echo '<input type="number" class="ecv2-drawer-range-input" data-filter="filter_' . esc_attr( $i ) . '" data-range="min" value="' . esc_attr( $range_min ) . '" placeholder="' . esc_attr( isset( $filter['placeholder_min'] ) ? $filter['placeholder_min'] : '0' ) . '" min="0" step="any" />';
					echo '</div>';
					echo '<span class="ecv2-drawer-range-sep">&ndash;</span>';
					echo '<div class="ecv2-drawer-range-field">';
					echo '<label class="ecv2-drawer-range-label">' . esc_html__( 'Max', 'wp-easycart' ) . '</label>';
					echo '<input type="number" class="ecv2-drawer-range-input" data-filter="filter_' . esc_attr( $i ) . '" data-range="max" value="' . esc_attr( $range_max ) . '" placeholder="' . esc_attr( isset( $filter['placeholder_max'] ) ? $filter['placeholder_max'] : 'No limit' ) . '" min="0" step="any" />';
					echo '</div>';
					echo '<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-primary ecv2-drawer-range-apply" data-filter="filter_' . esc_attr( $i ) . '" style="display:none;">' . esc_html__( 'Apply', 'wp-easycart' ) . '</button>';
					echo '</div>';
				} else {
					// Pill-based filter.
					echo '<div class="ecv2-drawer-pills">';

					echo '<button type="button" class="ecv2-drawer-pill' . ( $current_val === '' ? ' ecv2-drawer-pill-active' : '' ) . '" data-filter="filter_' . esc_attr( $i ) . '" data-value="">' . esc_html__( 'All', 'wp-easycart' ) . '</button>';

					if ( isset( $filter['data'] ) && is_array( $filter['data'] ) ) {
						foreach ( $filter['data'] as $option ) {
							$val = isset( $option->value ) ? $option->value : '';
							$label = isset( $option->label ) ? $option->label : '';
							$icon = isset( $option->icon ) ? $option->icon : '';
							echo '<button type="button" class="ecv2-drawer-pill' . ( $current_val == $val ? ' ecv2-drawer-pill-active' : '' ) . '" data-filter="filter_' . esc_attr( $i ) . '" data-value="' . esc_attr( $val ) . '">';
							if ( $icon ) {
								echo '<span class="dashicons dashicons-' . esc_attr( $icon ) . '"></span> ';
							}
							echo esc_html( $label );
							echo '</button>';
						}
					}
					echo '</div>';
				}

				echo '</div>'; // .ecv2-drawer-filter-group
			}

			echo '</div>'; // .ecv2-drawer-body

			// Drawer footer.
			echo '<div class="ecv2-drawer-footer">';
			echo '<button type="button" class="ecv2-btn ecv2-btn-ghost" id="ecv2-drawer-clear-all" onclick="ecv2_clear_filters();">';
			echo '<span class="dashicons dashicons-dismiss"></span> ' . esc_html__( 'Clear all', 'wp-easycart' );
			if ( $active_count > 0 ) {
				echo ' <span class="ecv2-drawer-clear-count">(' . esc_html( $active_count ) . ')</span>';
			}
			echo '</button>';
			echo '<button type="button" class="ecv2-btn ecv2-btn-primary" id="ecv2-drawer-apply" onclick="ecv2_apply_drawer_filters();">';
			echo '<span class="dashicons dashicons-yes"></span> ' . esc_html__( 'Show Results', 'wp-easycart' ) . '</button>';
			echo '</div>';

			echo '</div>'; // .ecv2-filter-drawer
		}

		protected function print_search_box() {
			$search_val = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
			$has_search = '' !== $search_val;
			echo '<div class="ecv2-search">';
			echo '<input type="search" id="ecv2-search-input" name="s" value="' . esc_attr( $search_val ) . '" placeholder="' . esc_attr__( 'Search', 'wp-easycart' ) . ' ' . esc_attr( strtolower( $this->item_label_plural ) ) . '..." />';
			echo '<span class="ecv2-search-loading" id="ecv2-search-loading" style="display:none;"><span class="dashicons dashicons-update ecv2-spin"></span></span>';
			echo '<button type="button" class="ecv2-search-clear" id="ecv2-search-clear" ' . ( $has_search ? '' : 'style="display:none;"' ) . ' title="' . esc_attr__( 'Clear search', 'wp-easycart' ) . '"><span class="dashicons dashicons-no-alt"></span></button>';
			echo '<button type="button" class="ecv2-search-submit" id="ecv2-search-submit" title="' . esc_attr__( 'Search', 'wp-easycart' ) . '"><span class="dashicons dashicons-search"></span></button>';
			echo '</div>';
		}

		protected function print_view_toggle() {
			if ( count( $this->view_modes ) <= 1 ) {
				return;
			}
			echo '<div class="ecv2-view-toggle">';
			if ( in_array( 'table', $this->view_modes ) ) {
				echo '<button type="button" class="ecv2-view-btn' . ( $this->current_view_mode === 'table' ? ' ecv2-view-btn-active' : '' ) . '" data-mode="table" title="' . esc_attr__( 'Table View', 'wp-easycart' ) . '"><span class="dashicons dashicons-list-view"></span></button>';
			}
			if ( in_array( 'card', $this->view_modes ) ) {
				echo '<button type="button" class="ecv2-view-btn' . ( $this->current_view_mode === 'card' ? ' ecv2-view-btn-active' : '' ) . '" data-mode="card" title="' . esc_attr__( 'Card View', 'wp-easycart' ) . '"><span class="dashicons dashicons-grid-view"></span></button>';
			}
			if ( in_array( 'spreadsheet', $this->view_modes ) ) {
				echo '<button type="button" class="ecv2-view-btn' . ( $this->current_view_mode === 'spreadsheet' ? ' ecv2-view-btn-active' : '' ) . '" data-mode="spreadsheet" title="' . esc_attr__( 'Spreadsheet View', 'wp-easycart' ) . '"><span class="dashicons dashicons-editor-table"></span></button>';
			}
			echo '</div>';
		}

		protected function print_hidden_fields() {
			wp_easycart_admin_verification()->print_nonce_field( 'wp_easycart_nonce', 'wp-easycart-bulk-' . ( isset( $_GET['subpage'] ) ? sanitize_key( $_GET['subpage'] ) : '' ) );
			wp_easycart_admin()->preloader->print_preloader( 'ec_admin_table_display_loader' );

			echo '<input type="hidden" name="page" value="' . esc_attr( sanitize_key( $_GET['page'] ) ) . '" />';
			if ( isset( $_GET['subpage'] ) ) {
				echo '<input type="hidden" name="subpage" value="' . esc_attr( sanitize_key( $_GET['subpage'] ) ) . '" />';
			}
			if ( count( $this->get_vars ) ) {
				foreach ( $this->get_vars as $var ) {
					if ( isset( $_GET[ $var ] ) ) {
						echo '<input type="hidden" name="' . esc_attr( $var ) . '" id="' . esc_attr( $var ) . '" value="' . esc_attr( sanitize_text_field( wp_unslash( $_GET[ $var ] ) ) ) . '" />';
					}
				}
			}
			echo '<input type="hidden" name="orderby" value="' . esc_attr( $this->current_sort_column ) . '" />';
			echo '<input type="hidden" name="order" value="' . esc_attr( $this->current_sort_direction ) . '" />';

			// Hidden filter fields (for form submission).
			for ( $i = 0; $i < count( $this->filters ); $i++ ) {
				$val = isset( $_GET[ 'filter_' . $i ] ) ? sanitize_text_field( wp_unslash( $_GET[ 'filter_' . $i ] ) ) : '';
				echo '<input type="hidden" name="filter_' . esc_attr( $i ) . '" id="ecv2-filter-input-' . esc_attr( $i ) . '" value="' . esc_attr( $val ) . '" />';
			}

			// Health filter.
			$health_val = isset( $_GET['health_filter'] ) ? sanitize_key( $_GET['health_filter'] ) : '';
			echo '<input type="hidden" name="health_filter" id="ecv2-health-filter-input" value="' . esc_attr( $health_val ) . '" />';

			// Bulk action variables.
			if ( isset( $this->bulk_variables ) ) {
				foreach ( $this->bulk_variables as $bv ) {
					echo '<input type="hidden" name="' . esc_attr( $bv['name'] ) . '" value="' . esc_attr( $bv['label'] ) . '" />';
				}
			}
		}

		protected function print_table_view() {
			echo '<table class="ecv2-table" id="' . esc_attr( $this->table_id ) . '">';
			$this->print_table_thead();
			echo '<tbody>';
			foreach ( $this->results as $result ) {
				$this->print_table_row( $result );
			}
			if ( empty( $this->results ) ) {
				$visible_cols = 0;
				foreach ( $this->list_columns as $col ) {
					if ( ! isset( $col['format'] ) || $col['format'] !== 'hidden' ) {
						$visible_cols++;
					}
				}
				echo '<tr><td colspan="' . ( $visible_cols + 2 ) . '" class="ecv2-empty-state">';
				echo '<span class="dashicons dashicons-info-outline"></span> ';
				echo esc_html__( 'No items found.', 'wp-easycart' );
				echo '</td></tr>';
			}
			echo '</tbody>';
			echo '</table>';
		}

		protected function print_table_thead() {
			echo '<thead><tr>';
			echo '<th class="ecv2-col-check"><input type="checkbox" id="ecv2-select-all" /></th>';

			foreach ( $this->list_columns as $col ) {
				if ( isset( $col['format'] ) && $col['format'] === 'hidden' ) {
					continue;
				}
				$sort = 'asc';
				if ( $this->current_sort_column == $col['name'] && 'asc' == $this->current_sort_direction ) {
					$sort = 'desc';
				}
				$sorted_class = ( $this->current_sort_column == $col['name'] ) ? ' ecv2-sorted ecv2-sorted-' . $this->current_sort_direction : '';
				$width_attr = isset( $col['width'] ) ? ' style="width:' . esc_attr( $col['width'] ) . 'px;"' : '';

				$extra_classes = '';
				if ( isset( $col['tablet_hide'] ) && $col['tablet_hide'] ) {
					$extra_classes .= ' ecv2-hide-tablet';
				}
				if ( isset( $col['laptop_hide'] ) && $col['laptop_hide'] ) {
					$extra_classes .= ' ecv2-hide-laptop';
				}

				echo '<th class="ecv2-col ecv2-col-' . esc_attr( $col['name'] ) . $sorted_class . $extra_classes . '"' . $width_attr . '>';
				echo '<a href="' . esc_url( $this->get_url( 'orderby', $col['name'], false, 'order', $sort ) ) . '" class="ecv2-sort-link">';
				echo esc_html( $col['label'] );
				if ( $this->current_sort_column == $col['name'] ) {
					echo '<span class="ecv2-sort-arrow">' . ( $this->current_sort_direction === 'asc' ? '&#9650;' : '&#9660;' ) . '</span>';
				}
				echo '</a></th>';
			}

			echo '<th class="ecv2-col-actions">' . esc_html__( 'Actions', 'wp-easycart' ) . '</th>';
			echo '</tr></thead>';
		}

		protected function print_table_row( $result ) {
			$row_id = $result->{ $this->key };
			echo '<tr class="ecv2-row" data-id="' . esc_attr( $row_id ) . '">';

			// Checkbox.
			echo '<td class="ecv2-col-check"><input type="checkbox" name="bulk[]" value="' . esc_attr( $row_id ) . '" class="ecv2-row-check" /></td>';

			// Data columns.
			foreach ( $this->list_columns as $col ) {
				if ( isset( $col['format'] ) && $col['format'] === 'hidden' ) {
					continue;
				}
				$extra_classes = '';
				if ( isset( $col['tablet_hide'] ) && $col['tablet_hide'] ) {
					$extra_classes .= ' ecv2-hide-tablet';
				}
				if ( isset( $col['laptop_hide'] ) && $col['laptop_hide'] ) {
					$extra_classes .= ' ecv2-hide-laptop';
				}
				$editable_attr = '';
				if ( in_array( $col['name'], $this->inline_editable_columns ) ) {
					$editable_attr = ' data-editable="true" data-field="' . esc_attr( $col['name'] ) . '"';
				}
				echo '<td class="ecv2-cell ecv2-cell-' . esc_attr( $col['name'] ) . $extra_classes . '"' . $editable_attr . '>';
				$this->print_cell_content( $result, $col );
				echo '</td>';
			}

			// Actions.
			echo '<td class="ecv2-col-actions">';
			$this->print_row_actions( $result );
			echo '</td>';

			echo '</tr>';
		}

		/**
		 * Print cell content - override in child classes for custom formats.
		 */
		protected function print_cell_content( $result, $col ) {
			if ( ! isset( $col['format'] ) ) {
				echo esc_html( isset( $result->{ $col['name'] } ) ? $result->{ $col['name'] } : '' );
				return;
			}

			switch ( $col['format'] ) {
				case 'int':
					echo esc_html( ( isset( $col['is_id'] ) && $col['is_id'] ? '#' : '' ) . (int) $result->{ $col['name'] } );
					break;
				case 'string':
					if ( isset( $col['linked'] ) && $col['linked'] ) {
						echo '<a href="' . esc_url( $this->get_url( $this->key, $result->{ $this->key }, false, 'ec_admin_form_action', 'edit' ) ) . '" class="ecv2-link-primary">';
						echo esc_html( isset( $result->{ $col['name'] } ) ? strip_tags( wp_unslash( $result->{ $col['name'] } ) ) : '' );
						echo '</a>';
					} else {
						echo esc_html( isset( $result->{ $col['name'] } ) ? strip_tags( wp_unslash( $result->{ $col['name'] } ) ) : '' );
					}
					break;
				case 'currency':
					echo esc_html( $GLOBALS['currency']->get_currency_display( $result->{ $col['name'] } ) );
					break;
				case 'yes_no':
					echo ( (bool) $result->{ $col['name'] } ) ? esc_html__( 'Yes', 'wp-easycart' ) : esc_html__( 'No', 'wp-easycart' );
					break;
				case 'date':
					$ts = strtotime( $result->{ $col['name'] } );
					echo $ts > 0 ? esc_html( date( 'F d, Y', $ts ) ) : '';
					break;
				case 'datetime':
					$ts = strtotime( $result->{ $col['name'] } );
					if ( isset( $col['localize_timestamp'] ) && $col['localize_timestamp'] ) {
						$ts += $this->date_diff;
					}
					echo $ts > 0 ? esc_html( $this->format_relative_date( $ts, time() + ( isset( $col['localize_timestamp'] ) && $col['localize_timestamp'] ? $this->date_diff : 0 ) ) ) : '';
					break;
				case 'bool':
					echo $result->{ $col['name'] } ? esc_html__( 'Yes', 'wp-easycart' ) : esc_html__( 'No', 'wp-easycart' );
					break;
				default:
					echo esc_html( $result->{ $col['name'] } );
					break;
			}
		}

		/**
		 * Print row actions - menu. Override in child for custom actions.
		 */
		protected function print_row_actions( $result ) {
			if ( empty( $this->row_menu_actions ) && ! empty( $this->actions ) ) {
				// Fallback: render v1-style icon actions.
				foreach ( $this->actions as $action ) {
					if ( isset( $action['min_id'] ) && $result->{ $this->key } < $action['min_id'] ) {
						continue;
					}
					$label = esc_attr( $action['label'] );
					$icon = esc_attr( $action['icon'] );
					if ( $action['icon'] == 'hidden' && ! $result->is_visible ) {
						$label = __( 'Activate', 'wp-easycart' );
						$icon = 'visibility';
					}
					$href = isset( $action['custom'] )
						? $this->get_url( $this->key, $result->{ $this->key }, true, $action['custom'], $action['name'] )
						: $this->get_url( $this->key, $result->{ $this->key }, false, 'ec_admin_form_action', $action['name'] );

					echo '<a href="' . esc_url( $href ) . '" class="ecv2-action-icon" title="' . esc_attr( $label ) . '"';
					if ( 'Delete' == $label ) {
						echo ' onclick="return confirm(\'' . esc_attr__( 'Are you sure you want to delete this item?', 'wp-easycart' ) . '\');"';
					}
					echo '><span class="dashicons dashicons-' . esc_attr( $icon ) . '"></span></a>';
				}
				return;
			}

			// Modern menu.
			echo '<div class="ecv2-row-menu-wrap">';
			echo '<button type="button" class="ecv2-row-menu-trigger" onclick="ecv2_toggle_row_menu(this);">&#8943;</button>';
			echo '<div class="ecv2-row-menu">';
			foreach ( $this->row_menu_actions as $action ) {
				if ( isset( $action['min_id'] ) && $result->{ $this->key } < $action['min_id'] ) {
					continue;
				}
				$this->print_row_menu_item( $result, $action );
			}
			echo '</div>'; // .ecv2-row-menu
			echo '</div>'; // .ecv2-row-menu-wrap
		}

		protected function print_row_menu_item( $result, $action ) {
			$label  = $action['label'];
			$icon   = isset( $action['icon'] ) ? $action['icon'] : 'admin-generic';
			$danger = isset( $action['danger'] ) && $action['danger'];

			// Disabled state: render as a non-interactive span instead of a link.
			if ( isset( $action['disabled'] ) && $action['disabled'] ) {
				$title_attr = isset( $action['disabled_title'] ) ? $action['disabled_title'] : '';
				echo '<span class="ecv2-row-menu-item ecv2-row-menu-item-disabled"';
				if ( '' !== $title_attr ) {
					echo ' title="' . esc_attr( $title_attr ) . '"';
				}
				echo '>';
				echo '<span class="dashicons dashicons-' . esc_attr( $icon ) . '"></span> ';
				echo esc_html( $label );
				echo '</span>';
				return;
			}

			echo '<a href="';
			if ( isset( $action['href'] ) ) {
				echo esc_url( str_replace( '{id}', $result->{ $this->key }, $action['href'] ) );
			} else if ( isset( $action['action'] ) ) {
				echo esc_url( $this->get_url( $this->key, $result->{ $this->key }, false, 'ec_admin_form_action', $action['action'] ) );
			} else {
				echo '#';
			}
			echo '" class="ecv2-row-menu-item' . ( $danger ? ' ecv2-row-menu-item-danger' : '' ) . '"';
			if ( isset( $action['onclick'] ) ) {
				echo ' onclick="' . esc_attr( str_replace( '{id}', $result->{ $this->key }, $action['onclick'] ) ) . '"';
			}
			if ( isset( $action['confirm'] ) && $action['confirm'] ) {
				echo ' onclick="return confirm(\'' . esc_attr__( 'Are you sure?', 'wp-easycart' ) . '\');"';
			}
			if ( isset( $action['target'] ) && '' !== $action['target'] ) {
				$allowed_targets = array( '_blank', '_self', '_parent', '_top' );
				$target = in_array( $action['target'], $allowed_targets, true ) ? $action['target'] : '_self';
				echo ' target="' . esc_attr( $target ) . '"';
				if ( '_blank' === $target ) {
					echo ' rel="noopener noreferrer"';
				}
			}
			echo '>';
			echo '<span class="dashicons dashicons-' . esc_attr( $icon ) . '"></span> ';
			echo esc_html( $label );
			echo '</a>';
		}

		protected function print_card_view() {
			echo '<div class="ecv2-card-grid">';
			foreach ( $this->results as $result ) {
				$this->print_card( $result );
			}
			if ( empty( $this->results ) ) {
				echo '<div class="ecv2-empty-state"><span class="dashicons dashicons-info-outline"></span> ' . esc_html__( 'No items found.', 'wp-easycart' ) . '</div>';
			}
			echo '</div>';
		}

		/**
		 * Print a single card. Override in child classes for custom card layout.
		 */
		protected function print_card( $result ) {
			echo '<div class="ecv2-card" data-id="' . esc_attr( $result->{ $this->key } ) . '">';
			echo '<div class="ecv2-card-body">';
			echo '<h3 class="ecv2-card-title">' . esc_html( $result->{ $this->list_columns[0]['name'] } ) . '</h3>';
			echo '</div>';
			echo '<div class="ecv2-card-footer">';
			echo '<input type="checkbox" name="bulk[]" value="' . esc_attr( $result->{ $this->key } ) . '" class="ecv2-row-check" />';
			echo '</div>';
			echo '</div>';
		}

		protected function print_spreadsheet_view() {
			$columns = ! empty( $this->spreadsheet_columns ) ? $this->spreadsheet_columns : $this->list_columns;

			// Column toggle toolbar.
			echo '<div class="ecv2-ss-toolbar">';
			echo '<button type="button" class="ecv2-btn ecv2-btn-ghost ecv2-btn-sm" id="ecv2-ss-col-toggle-btn" onclick="ecv2_toggle_ss_column_picker(this);">';
			echo '<span class="dashicons dashicons-columns"></span> ' . esc_html__( 'Columns', 'wp-easycart' ) . '</button>';
			echo '<div class="ecv2-ss-col-picker" id="ecv2-ss-col-picker" style="display:none;">';
			echo '<div class="ecv2-ss-col-picker-header">' . esc_html__( 'Show/Hide Columns', 'wp-easycart' ) . '</div>';

			// Determine which columns are hidden: cookie overrides defaults.
			$has_cookie = isset( $_COOKIE['wpeasycart_ss_hidden_cols'] );
			$cookie_hidden = array();
			if ( $has_cookie ) {
				$cookie_hidden = array_filter( array_map( 'sanitize_key', explode( ',', sanitize_text_field( wp_unslash( $_COOKIE['wpeasycart_ss_hidden_cols'] ) ) ) ) );
			}
			$default_hidden = array();
			foreach ( $columns as $col ) {
				if ( isset( $col['ss_default_hidden'] ) && $col['ss_default_hidden'] ) {
					$default_hidden[] = $col['name'];
				}
			}
			$hidden_cols = $has_cookie ? $cookie_hidden : $default_hidden;

			foreach ( $columns as $idx => $col ) {
				if ( isset( $col['format'] ) && $col['format'] === 'hidden' ) {
					continue;
				}
				$col_key = esc_attr( $col['name'] );
				$always_on = isset( $col['is_id'] ) && $col['is_id'];
				$is_hidden = in_array( $col['name'], $hidden_cols, true );
				$checked = $is_hidden ? '' : ' checked';
				echo '<label class="ecv2-ss-col-picker-item' . ( $always_on ? ' ecv2-ss-col-picker-locked' : '' ) . '">';
				echo '<input type="checkbox" data-ss-col="' . $col_key . '"' . $checked . ( $always_on ? ' disabled' : '' ) . ' onchange="ecv2_toggle_ss_column(\'' . $col_key . '\', this.checked);" />';
				echo ' ' . esc_html( $col['label'] );
				echo '</label>';
			}
			echo '</div>'; // .ecv2-ss-col-picker
			echo '</div>'; // .ecv2-ss-toolbar

			echo '<div class="ecv2-spreadsheet-wrapper">';

			if ( empty( $this->results ) ) {
				// Empty state: white background centered message matching table/card views.
				echo '<div class="ecv2-empty-state ecv2-empty-state-ss">';
				echo '<span class="dashicons dashicons-info-outline"></span> ';
				echo esc_html__( 'No items found.', 'wp-easycart' );
				echo '</div>';
			} else {
				echo '<table class="ecv2-spreadsheet">';
				echo '<thead><tr>';
				echo '<th class="ecv2-col-check"><input type="checkbox" id="ecv2-ss-select-all" /></th>';
				foreach ( $columns as $col ) {
					if ( isset( $col['format'] ) && $col['format'] === 'hidden' ) {
						continue;
					}
					$hide_style = in_array( $col['name'], $hidden_cols, true ) ? ' style="display:none;"' : '';
					echo '<th class="ecv2-ss-col ecv2-ss-col-' . esc_attr( $col['name'] ) . '"' . $hide_style . '>' . esc_html( $col['label'] ) . '</th>';
				}
				echo '<th class="ecv2-ss-col ecv2-ss-col-actions">' . esc_html__( 'Actions', 'wp-easycart' ) . '</th>';
				echo '</tr></thead>';
				echo '<tbody>';
				foreach ( $this->results as $result ) {
					echo '<tr class="ecv2-ss-row" data-id="' . esc_attr( $result->{ $this->key } ) . '">';
					echo '<td class="ecv2-col-check"><input type="checkbox" name="bulk[]" value="' . esc_attr( $result->{ $this->key } ) . '" class="ecv2-row-check" /></td>';
					foreach ( $columns as $col ) {
						if ( isset( $col['format'] ) && $col['format'] === 'hidden' ) {
							continue;
						}
						$editable = isset( $col['ss_editable'] ) && $col['ss_editable'];
						$hide_style = in_array( $col['name'], $hidden_cols, true ) ? ' style="display:none;"' : '';
						echo '<td class="ecv2-ss-cell ecv2-ss-col ecv2-ss-col-' . esc_attr( $col['name'] ) . ( $editable ? ' ecv2-ss-editable' : '' ) . '" data-field="' . esc_attr( $col['name'] ) . '"' . ( $editable ? ' contenteditable="false"' : '' ) . $hide_style . '>';
						$this->print_spreadsheet_cell( $result, $col );
						echo '</td>';
					}
					// Actions column with 3-dot menu.
					echo '<td class="ecv2-ss-cell ecv2-ss-col ecv2-ss-col-actions">';
					$this->print_row_actions( $result );
					echo '</td>';
					echo '</tr>';
				}
				echo '</tbody></table>';
			}

			echo '</div>'; // .ecv2-spreadsheet-wrapper
		}

		/**
		 * Print a spreadsheet cell. Override in child for custom formats.
		 */
		protected function print_spreadsheet_cell( $result, $col ) {
			$this->print_cell_content( $result, $col );
		}

		protected function print_pagination() {
			echo '<div class="ecv2-pagination">';

			// Left: per page selector.
			echo '<div class="ecv2-pagination-left">';
			echo '<select name="perpage" class="ecv2-select ecv2-select-sm ecv2-perpage-select">';
			foreach ( $this->perpage_options as $pp ) {
				echo '<option value="' . esc_attr( $pp ) . '"' . selected( $this->perpage, $pp, false ) . '>' . esc_html( $pp ) . ' ' . esc_html__( 'per page', 'wp-easycart' ) . '</option>';
			}
			echo '</select>';
			echo '</div>';

			// Center: info.
			echo '<div class="ecv2-pagination-center">';
			$start = ( ( $this->current_page - 1 ) * $this->perpage ) + 1;
			$end = min( $start + $this->showing - 1, $this->record_count );
			if ( $this->record_count > 0 ) {
				echo esc_html( sprintf( __( 'Showing %1$d–%2$d of %3$d', 'wp-easycart' ), $start, $end, $this->record_count ) );
			} else {
				echo esc_html__( 'No items', 'wp-easycart' );
			}
			echo '</div>';

			// Right: page nav.
			echo '<div class="ecv2-pagination-right">';
			if ( $this->total_pages > 1 ) {
				$disabled_prev = $this->current_page <= 1 ? ' disabled' : '';
				$disabled_next = $this->current_page >= $this->total_pages ? ' disabled' : '';

				echo '<a href="' . esc_url( $this->get_url( 'pagenum', '1', false ) ) . '" class="ecv2-page-btn' . $disabled_prev . '" title="' . esc_attr__( 'First', 'wp-easycart' ) . '">&laquo;</a>';
				echo '<a href="' . esc_url( $this->get_url( 'pagenum', max( 1, $this->current_page - 1 ), false ) ) . '" class="ecv2-page-btn' . $disabled_prev . '" title="' . esc_attr__( 'Previous', 'wp-easycart' ) . '">&lsaquo;</a>';
				echo '<span class="ecv2-page-info">';
				echo '<input type="text" name="pagenum" class="ecv2-page-input" value="' . esc_attr( $this->current_page ) . '" size="1" /> / ' . esc_html( $this->total_pages );
				echo '</span>';
				echo '<a href="' . esc_url( $this->get_url( 'pagenum', min( $this->total_pages, $this->current_page + 1 ), false ) ) . '" class="ecv2-page-btn' . $disabled_next . '" title="' . esc_attr__( 'Next', 'wp-easycart' ) . '">&rsaquo;</a>';
				echo '<a href="' . esc_url( $this->get_url( 'pagenum', $this->total_pages, false ) ) . '" class="ecv2-page-btn' . $disabled_next . '" title="' . esc_attr__( 'Last', 'wp-easycart' ) . '">&raquo;</a>';
			}
			echo '</div>';

			echo '</div>'; // .ecv2-pagination
		}

		protected function print_bulk_edit_modal() {
			if ( empty( $this->bulk_edit_fields ) ) {
				return;
			}
			echo '<div class="ecv2-modal-overlay" id="ecv2-bulk-edit-modal" style="display:none;">';
			echo '<div class="ecv2-modal">';
			echo '<div class="ecv2-modal-header">';
			echo '<h2>' . esc_html__( 'Bulk Edit', 'wp-easycart' ) . ' <span id="ecv2-bulk-edit-count"></span></h2>';
			echo '<button type="button" class="ecv2-modal-close" onclick="ecv2_close_bulk_edit();">&times;</button>';
			echo '</div>';
			echo '<div class="ecv2-modal-body">';
			foreach ( $this->bulk_edit_fields as $field ) {
				echo '<div class="ecv2-modal-field">';
				echo '<label class="ecv2-modal-label">' . esc_html( $field['label'] ) . '</label>';
				$this->print_bulk_edit_field( $field );
				echo '</div>';
			}
			echo '</div>';
			echo '<div class="ecv2-modal-footer">';
			echo '<button type="button" class="ecv2-btn ecv2-btn-ghost" onclick="ecv2_close_bulk_edit();">' . esc_html__( 'Cancel', 'wp-easycart' ) . '</button>';
			echo '<button type="button" class="ecv2-btn ecv2-btn-primary" onclick="ecv2_apply_bulk_edit();">' . esc_html__( 'Apply Changes', 'wp-easycart' ) . '</button>';
			echo '</div>';
			echo '</div>'; // .ecv2-modal
			echo '</div>'; // .ecv2-modal-overlay
		}

		protected function print_confirm_dialog() {
			echo '<div class="ecv2-modal-overlay" id="ecv2-confirm-dialog" style="display:none;">';
			echo '<div class="ecv2-modal ecv2-modal-confirm">';
			echo '<div class="ecv2-modal-header">';
			echo '<h2 id="ecv2-confirm-title">' . esc_html__( 'Confirm', 'wp-easycart' ) . '</h2>';
			echo '<button type="button" class="ecv2-modal-close" onclick="ecv2_confirm_cancel();">&times;</button>';
			echo '</div>';
			echo '<div class="ecv2-modal-body">';
			echo '<p id="ecv2-confirm-message"></p>';
			echo '</div>';
			echo '<div class="ecv2-modal-footer">';
			echo '<div class="ecv2-modal-footer-right">';
			echo '<button type="button" class="ecv2-btn ecv2-btn-ghost" onclick="ecv2_confirm_cancel();">' . esc_html__( 'Cancel', 'wp-easycart' ) . '</button>';
			echo '<button type="button" class="ecv2-btn ecv2-btn-primary" id="ecv2-confirm-ok" onclick="ecv2_confirm_ok();">' . esc_html__( 'Confirm', 'wp-easycart' ) . '</button>';
			echo '</div>';
			echo '</div>';
			echo '</div>'; // .ecv2-modal
			echo '</div>'; // .ecv2-modal-overlay
		}

		protected function print_bulk_edit_field( $field ) {
			switch ( $field['type'] ) {
				case 'select':
					echo '<select id="ecv2-be-' . esc_attr( $field['name'] ) . '" class="ecv2-select">';
					echo '<option value="">- ' . esc_html__( 'No Change', 'wp-easycart' ) . ' -</option>';
					foreach ( $field['options'] as $option ) {
						echo '<option value="' . esc_attr( $option['value'] ) . '">' . esc_html( $option['label'] ) . '</option>';
					}
					echo '</select>';
					break;
				case 'price':
					echo '<div class="ecv2-be-price-wrap">';
					echo '<select id="ecv2-be-' . esc_attr( $field['name'] ) . '-mode" class="ecv2-select ecv2-select-sm">';
					echo '<option value="">- ' . esc_html__( 'No Change', 'wp-easycart' ) . ' -</option>';
					echo '<option value="set">' . esc_html__( 'Set to', 'wp-easycart' ) . '</option>';
					echo '<option value="increase">' . esc_html__( 'Increase by', 'wp-easycart' ) . '</option>';
					echo '<option value="decrease">' . esc_html__( 'Decrease by', 'wp-easycart' ) . '</option>';
					echo '<option value="percent_increase">' . esc_html__( 'Increase by %', 'wp-easycart' ) . '</option>';
					echo '<option value="percent_decrease">' . esc_html__( 'Decrease by %', 'wp-easycart' ) . '</option>';
					echo '</select>';
					echo '<input type="number" id="ecv2-be-' . esc_attr( $field['name'] ) . '-value" class="ecv2-input ecv2-input-sm" step="0.01" placeholder="0.00" />';
					echo '</div>';
					break;
				case 'number':
					echo '<div class="ecv2-be-number-wrap">';
					echo '<select id="ecv2-be-' . esc_attr( $field['name'] ) . '-mode" class="ecv2-select ecv2-select-sm">';
					echo '<option value="">- ' . esc_html__( 'No Change', 'wp-easycart' ) . ' -</option>';
					echo '<option value="set">' . esc_html__( 'Set to', 'wp-easycart' ) . '</option>';
					echo '<option value="increase">' . esc_html__( 'Increase by', 'wp-easycart' ) . '</option>';
					echo '<option value="decrease">' . esc_html__( 'Decrease by', 'wp-easycart' ) . '</option>';
					echo '</select>';
					echo '<input type="number" id="ecv2-be-' . esc_attr( $field['name'] ) . '-value" class="ecv2-input ecv2-input-sm" step="1" placeholder="0" />';
					echo '</div>';
					break;
				case 'text':
					echo '<input type="text" id="ecv2-be-' . esc_attr( $field['name'] ) . '" class="ecv2-input" placeholder="' . esc_attr__( 'Leave empty for no change', 'wp-easycart' ) . '" />';
					break;
			}
		}

		protected function print_custom_modals() {
			// Stub — override in subclasses.
		}

		protected function get_data() {
			$sql = $this->get_query();
			$this->results = $this->wpdb->get_results( $sql );
			$this->showing = count( $this->results );
			$record_count_row = $this->wpdb->get_row( 'SELECT COUNT( ' . $this->table . '.' . $this->key . ' ) AS total_rows' . $this->get_filter_select() . ' FROM ' . $this->table . ' ' . $this->join . $this->get_filter() );
			$this->record_count = ( $record_count_row && isset( $record_count_row->total_rows ) ) ? $record_count_row->total_rows : 0;
			$this->total_pages = ceil( $this->record_count / $this->perpage );
			if ( $this->current_page > $this->total_pages && $this->record_count == 0 ) {
				$this->current_page = 1;
			} else if ( $this->current_page > $this->total_pages ) {
				$this->current_page = $this->total_pages;
				$this->get_data();
			}
		}

		protected function get_query() {
			$secondary_sort = '';
			if ( isset( $this->current_sort_column ) ) {
				$sort_column = $this->current_sort_column;
				$sort_direction = $this->current_sort_direction;
			} else if ( is_array( $this->default_sort_column ) ) {
				$sort_column = '';
				for ( $i = 0; $i < count( $this->default_sort_column ); $i++ ) {
					if ( $i > 0 ) {
						$sort_column .= ', ';
					}
					$sort_column .= $this->default_sort_column[ $i ] . ' ' . $this->default_sort_direction[ $i ];
				}
				$sort_direction = '';
			} else {
				$sort_column = $this->current_sort_column = $this->default_sort_column;
				$sort_direction = $this->current_sort_direction = $this->default_sort_direction;
			}
			if ( $sort_column != $this->default_sort_column ) {
				if ( ! is_array( $this->default_sort_column ) && ! is_array( $this->default_sort_direction ) ) {
					$secondary_sort = ', ' . $this->default_sort_column . ' ' . $this->default_sort_direction;
				}
			}

			$sql = 'SELECT ';
			$is_first = true;
			foreach ( $this->list_columns as $col ) {
				if ( ! $is_first ) {
					$sql .= ', ';
				}
				if ( isset( $col['select'] ) ) {
					$sql .= $col['select'];
				} else if ( isset( $col['is_concat'] ) && $col['is_concat'] && isset( $col['concat'] ) ) {
					$sql .= $col['concat'];
				} else {
					$sql .= $this->table . '.' . $col['name'];
				}
				$is_first = false;
			}
			$sql .= ', ' . $this->table . '.' . $this->key;

			if ( $this->custom_select ) {
				$sql .= ', ' . $this->custom_select;
			}

			$sql .= $this->get_filter_select() . ' FROM ' . $this->table . ' ' . $this->join . $this->get_filter() . ' ORDER BY ' . $sort_column . ' ' . $sort_direction . $secondary_sort . ' LIMIT ' . ( $this->current_page - 1 ) * $this->perpage . ', ' . $this->perpage;
			return $sql;
		}

		protected function get_filter() {
			$join = '';
			$where = ' WHERE 1=1' . $this->custom_where;
			$having = '';

			// Health filter.
			if ( isset( $_GET['health_filter'] ) && '' != $_GET['health_filter'] ) {
				$health_where = $this->get_health_filter_where( sanitize_key( $_GET['health_filter'] ) );
				if ( $health_where ) {
					$where .= ' AND ' . $health_where;
				}
			}

			for ( $i = 0; $i < count( $this->filters ); $i++ ) {
				if ( isset( $_GET[ 'filter_' . $i ] ) && '' != sanitize_text_field( wp_unslash( $_GET[ 'filter_' . $i ] ) ) ) {
					// Handle where_callback type filters (e.g., stock filter with custom logic).
					if ( isset( $this->filters[ $i ]['where_callback'] ) && $this->filters[ $i ]['where_callback'] ) {
						$callback_where = $this->get_filter_callback_where( $i, sanitize_text_field( wp_unslash( $_GET[ 'filter_' . $i ] ) ) );
						if ( $callback_where ) {
							$where .= ' AND (' . $callback_where . ')';
						}
						continue;
					}

					if ( isset( $this->filters[ $i ]['join'] ) && '' != $this->filters[ $i ]['join'] ) {
						$join .= ' ' . $this->filters[ $i ]['join'];
					}
					if ( isset( $this->filters[ $i ]['where'] ) && '' != $this->filters[ $i ]['where'] ) {
						$where .= ' AND ( ' . $this->wpdb->prepare( $this->filters[ $i ]['where'], sanitize_text_field( wp_unslash( $_GET[ 'filter_' . $i ] ) ) );
					}
					if ( isset( $this->filters[ $i ]['where2'] ) && '' != $this->filters[ $i ]['where2'] ) {
						$where .= ' OR ' . $this->wpdb->prepare( $this->filters[ $i ]['where2'], sanitize_text_field( wp_unslash( $_GET[ 'filter_' . $i ] ) ) );
					}
					if ( isset( $this->filters[ $i ]['where'] ) && '' != $this->filters[ $i ]['where'] ) {
						$where .= ' )';
					}
					if ( isset( $this->filters[ $i ]['having'] ) && '' == $having && '' != $this->filters[ $i ]['having'] ) {
						$having .= ' HAVING ' . $this->wpdb->prepare( $this->filters[ $i ]['having'], sanitize_text_field( wp_unslash( $_GET[ 'filter_' . $i ] ) ) );
					} else if ( isset( $this->filters[ $i ]['having'] ) && '' != $this->filters[ $i ]['having'] ) {
						$having .= ' AND ' . $this->wpdb->prepare( $this->filters[ $i ]['having'], sanitize_text_field( wp_unslash( $_GET[ 'filter_' . $i ] ) ) );
					} else if ( isset( $this->filters[ $i ]['group'] ) && '' != $this->filters[ $i ]['group'] ) {
						$having .= ' ' . $this->filters[ $i ]['group'];
					}
				}
			}

			if ( isset( $_GET['s'] ) && '' != sanitize_text_field( wp_unslash( $_GET['s'] ) ) ) {
				$search = trim( sanitize_text_field( wp_unslash( $_GET['s'] ) ) );
				$where .= ' AND (';
				for ( $i = 0; $i < count( $this->search_columns ); $i++ ) {
					if ( $i > 0 ) {
						$where .= ' OR ';
					}
					$where .= ' ' . $this->search_columns[ $i ] . ' LIKE ' . $this->wpdb->prepare( '%s', '%' . $search . '%' );
				}
				$where .= ')';
			}

			return $join . $where . $having;
		}

		protected function get_filter_select() {
			$filter_select = '';
			for ( $i = 0; $i < count( $this->filters ); $i++ ) {
				if ( isset( $_GET[ 'filter_' . $i ] ) && '' != sanitize_text_field( wp_unslash( $_GET[ 'filter_' . $i ] ) ) && isset( $this->filters[ $i ]['select'] ) ) {
					$filter_select .= ', ' . $this->filters[ $i ]['select'];
				}
			}
			return $filter_select;
		}

		/**
		 * Override in child classes to support health dashboard filtering.
		 */
		protected function get_health_filter_where( $filter_key ) {
			return '';
		}

		/**
		 * Override in child classes to support callback-type filters.
		 */
		protected function get_filter_callback_where( $filter_index, $value ) {
			return '';
		}

		protected function get_url( $param, $value, $reset_params, $alt_param = null, $alt_value = null ) {
			$url = $this->page_url;
			if ( ! $reset_params ) {
				$url .= '?';
				foreach ( $this->query_params as $query_param ) {
					if ( 'orderby' == $param && 'pagenum' == $query_param[0] ) {
						// Ignore pagenum when resorting.
					} else if ( 'subpage' == $alt_param && 'subpage' == $query_param[0] ) {
						// Skip.
					} else if ( 'success' == $query_param[0] ) {
						// Skip.
					} else if ( isset( $query_param[0] ) && isset( $query_param[1] ) && $query_param[0] != $param && ( ! $alt_param || $query_param[0] != $alt_param ) ) {
						$url .= '&' . $query_param[0] . '=' . $query_param[1];
					}
				}
				$url .= '&' . $param . '=' . str_replace( '%', '%25', $value );
				if ( $alt_param && 'subpage' != $alt_param ) {
					$url .= '&' . $alt_param . '=' . str_replace( '%', '%25', $alt_value );
				}
				if ( $alt_param && 'ec_admin_form_action' == $alt_param ) {
					$url .= '&wp_easycart_nonce=' . wp_create_nonce( 'wp-easycart-action-' . preg_replace( '/[^A-Za-z0-9\-\_]/', '', $alt_value ) );
				}
			} else {
				$url .= '?page=' . sanitize_key( $_GET['page'] );
				if ( $alt_param == 'subpage' ) {
					$url .= '&subpage=' . sanitize_key( $alt_value );
				} else if ( isset( $_GET['subpage'] ) ) {
					$url .= '&subpage=' . sanitize_key( $_GET['subpage'] );
				}
				if ( $param ) {
					$url .= '&' . $param . '=' . str_replace( '%', '%25', $value );
				}
				if ( $alt_param && $alt_param != 'subpage' ) {
					$url .= '&' . $alt_param . '=' . str_replace( '%', '%25', $alt_value );
				}
			}
			return esc_url_raw( $url );
		}

		protected function format_relative_date( $date_timestamp, $now_timestamp ) {
			$date_compare = date( 'Ymd', $date_timestamp );
			$today_compare = date( 'Ymd', $now_timestamp );
			$yesterday_compare = date( 'Ymd', strtotime( 'Yesterday' ) );
			$week_cutoff = strtotime( '-7 days 11:59pm' );
			if ( $date_compare == $today_compare ) {
				return __( 'Today', 'wp-easycart' ) . ' ' . date( get_option( 'time_format' ), $date_timestamp );
			} else if ( $date_compare == $yesterday_compare ) {
				return __( 'Yesterday', 'wp-easycart' ) . ' ' . date( get_option( 'time_format' ), $date_timestamp );
			} else if ( $date_timestamp > $week_cutoff ) {
				return date( 'l ' . get_option( 'time_format' ), $date_timestamp );
			} else if ( date( 'Y', $date_timestamp ) != date( 'Y', $now_timestamp ) ) {
				return date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $date_timestamp );
			} else {
				return date( 'M j ' . get_option( 'time_format' ), $date_timestamp );
			}
		}

		/**
		 * Get product image URL helper - shared utility.
		 */
		public static function get_image_url( $image_value ) {
			if ( empty( $image_value ) ) {
				return '';
			}
			if ( substr( $image_value, 0, 7 ) === 'http://' || substr( $image_value, 0, 8 ) === 'https://' ) {
				return $image_value;
			}
			return plugins_url( '/wp-easycart-data/products/pics1/' . $image_value, EC_PLUGIN_DATA_DIRECTORY );
		}
	}

endif;

add_action( 'wp_ajax_ecv2_save_stat_visibility', 'ecv2_save_stat_visibility' );
function ecv2_save_stat_visibility() {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
	}
	if ( ! isset( $_POST['wp_easycart_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_easycart_nonce'] ) ), 'wp-easycart-ecv2-stat-toggle' ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wp-easycart' ) ) );
	}

	$table_id   = isset( $_POST['table_id'] ) ? sanitize_key( $_POST['table_id'] ) : '';
	$hidden_raw = isset( $_POST['hidden'] ) ? $_POST['hidden'] : array();

	if ( empty( $table_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid table ID.', 'wp-easycart' ) ) );
	}

	// Sanitize the hidden array.
	$hidden = array();
	if ( is_array( $hidden_raw ) ) {
		foreach ( $hidden_raw as $val ) {
			$hidden[] = sanitize_key( $val );
		}
	}

	$user_id  = get_current_user_id();
	$meta_key = 'ecv2_hidden_stats_' . $table_id;
	update_user_meta( $user_id, $meta_key, $hidden );

	wp_send_json_success( array( 'hidden' => $hidden ) );
}
