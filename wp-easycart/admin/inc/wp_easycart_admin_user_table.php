<?php
/**
 * WP EasyCart Admin User Table V2
 *
 * Extends wp_easycart_admin_table_v2 with customer-account-specific features.
 * Mirrors the structure of wp_easycart_admin_product_table.
 *
 * FREE scope (parity with the legacy user list + the modern V2 framework):
 *  - Identity column (avatar + name + email), role badge, registered / last
 *    login columns, User ID.
 *  - Health cards: Total, New (30 days), Subscribers, Never Logged In.
 *  - Filters: Role, Subscriber, Registered window.
 *  - Bulk: legacy delete / export / force reset / resend activation +
 *    the V2 Bulk Edit modal (role, subscriber).
 *  - Row menu: Edit, Quick Edit slideout, Login as Customer, Send Password
 *    Reset, Resend Activation, Delete.
 *  - Inline editing (spreadsheet view) of first / last name.
 *
 * PRO layers commerce insights (spend, orders, lifecycle, location, tags,
 * snapshot, guests) on top through the filters/actions declared here. This
 * file must never render PRO data itself — only the upsell teaser.
 *
 * @since 5.9.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

include_once( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_user_import.php' );

if ( ! class_exists( 'wp_easycart_admin_user_table' ) ) :

	class wp_easycart_admin_user_table extends wp_easycart_admin_table_v2 {

		const NEW_CUSTOMER_DAYS = 30;

		private $health_data = array();
		private $role_options = array();
		private $pro_gate = array();
		private $tracking_start = '';
		private $backfill_pending = 0;

		public function __construct() {
			parent::__construct();
		}

		public function setup() {
			global $wpdb;

			$this->pro_gate = wp_easycart_admin_pro_gate::evaluate( array( 'min_version' => '5.9.3' ) );

			/*
			 * First render after the 5.9.3 upgrade stamps when the new
			 * account-timeline fields began tracking, so empty values can be
			 * labeled "not tracked yet" instead of a misleading "Never".
			 */
			$this->tracking_start = get_option( 'ec_option_ecv2_user_tracking_start' );
			if ( ! $this->tracking_start ) {
				$this->tracking_start = current_time( 'mysql' );
				add_option( 'ec_option_ecv2_user_tracking_start', $this->tracking_start );
			}

			// Accounts still waiting on the one-time order-history backfill.
			$this->backfill_pending = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_user WHERE history_aggregates_built = 0' );

			$this->set_table( 'ec_user', 'user_id' );
			$this->set_table_id( 'ec_admin_user_list_v2' );
			$this->set_table_class( 'ecv2-user-table' );
			$this->set_default_sort( 'date_created', 'DESC' );
			$this->set_header( __( 'Customers', 'wp-easycart' ) );
			$this->set_icon( 'admin-users' );
			$this->set_importer( true, __( 'Import Users', 'wp-easycart' ) ); /* the base prints the button; print_page_header() below swaps the legacy form for the V2 importer dialog */
			$this->set_docs_link( 'users', 'user-accounts' );
			$this->set_add_new( true, 'add-new', __( 'Add Customer', 'wp-easycart' ) );
			$this->set_add_new_css( 'ecv2-btn ecv2-btn-primary' );
			$this->set_label( __( 'Customer', 'wp-easycart' ), __( 'Customers', 'wp-easycart' ) );
			$this->set_view_modes( array( 'table', 'card', 'spreadsheet' ) );
			$this->set_inline_editable_columns( array() ); // Table identity cell is a compound cell; inline editing lives in the spreadsheet view.

			$this->role_options = $wpdb->get_results( "SELECT ec_role.role_label AS value, ec_role.role_label AS label FROM ec_role ORDER BY role_id ASC" );

			/*
			 * Columns. PRO appends its own via 'wp_easycart_admin_user_list_columns'
			 * (orders, lifetime spend, last order, lifecycle, location, tags, flags).
			 */
			$columns = array(
				/* Customer is the only elastic column; everything else has a fixed width
				   and a drop tier: laptop_hide (< 1100px container) → tablet_hide (< 880) → mobile_hide (< 640). */
				array( 'name' => 'email', 'label' => __( 'Customer', 'wp-easycart' ), 'format' => 'user_identity', 'linked' => true ),
				array( 'name' => 'user_level', 'label' => __( 'Role', 'wp-easycart' ), 'format' => 'role_badge', 'width' => 100, 'mobile_hide' => true ),
				array( 'name' => 'date_created', 'label' => __( 'Registered', 'wp-easycart' ), 'format' => 'registered_date', 'width' => 110, 'tablet_hide' => true ),
				array( 'name' => 'last_login', 'label' => __( 'Last Login', 'wp-easycart' ), 'format' => 'last_login', 'width' => 110, 'laptop_hide' => true ),
				array( 'name' => 'user_id', 'label' => __( 'ID', 'wp-easycart' ), 'format' => 'int', 'is_id' => true, 'width' => 64, 'laptop_hide' => true ),
				// Hidden data columns used by cell renderers / JS.
				array( 'name' => 'first_name', 'format' => 'hidden', 'label' => '' ),
				array( 'name' => 'last_name', 'format' => 'hidden', 'label' => '' ),
				array( 'name' => 'is_subscriber', 'format' => 'hidden', 'label' => '' ),
				array( 'select' => "( CASE WHEN ec_user.user_notes IS NOT NULL AND ec_user.user_notes != '' THEN 1 ELSE 0 END ) AS has_notes", 'name' => 'has_notes', 'format' => 'hidden', 'label' => '' ),
			);
			$this->set_list_columns( apply_filters( 'wp_easycart_admin_user_list_columns', $columns, $this->pro_gate ) );

			$this->set_search_columns( apply_filters( 'wp_easycart_admin_user_list_search_columns', array(
				'ec_user.email',
				'ec_user.email_other',
				'ec_user.first_name',
				'ec_user.last_name',
				'ec_user.user_id',
				'ec_user.user_level',
				'ec_user.vat_registration_number',
			) ) );

			/*
			 * Bulk actions — legacy GET pipeline (delete / export / reset /
			 * resend) is retained on purpose: it is nonce-protected via the
			 * form's wp-easycart-bulk-accounts field and already battle-tested.
			 */
			$this->set_bulk_actions( apply_filters( 'wp_easycart_admin_bulk_user_options', array(
				array( 'name' => 'delete-account', 'label' => __( 'Delete', 'wp-easycart' ) ),
				array( 'name' => 'export-accounts-csv', 'label' => __( 'Export Selected CSV', 'wp-easycart' ) ),
				array( 'name' => 'export-accounts-csv-all', 'label' => __( 'Export All CSV', 'wp-easycart' ) ),
				array( 'name' => 'accounts-force-password-reset', 'label' => __( 'Force Selected to Reset Password', 'wp-easycart' ) ),
				array( 'name' => 'accounts-resend-activation', 'label' => __( 'Resend Activation Email to Selected', 'wp-easycart' ) ),
			) ) );

			$this->set_bulk_edit_fields( apply_filters( 'wp_easycart_admin_user_list_bulk_edit_fields', array(
				array( 'name' => 'user_level', 'label' => __( 'Role', 'wp-easycart' ), 'type' => 'select', 'options' => $this->get_role_bulk_options() ),
				array( 'name' => 'is_subscriber', 'label' => __( 'Newsletter Subscriber', 'wp-easycart' ), 'type' => 'select', 'options' => array(
					array( 'value' => '1', 'label' => __( 'Subscribed', 'wp-easycart' ) ),
					array( 'value' => '0', 'label' => __( 'Not Subscribed', 'wp-easycart' ) ),
				) ),
			) ) );

			$this->set_row_menu_actions( apply_filters( 'wp_easycart_admin_user_list_row_menu', array(
				array( 'label' => __( 'Edit Customer', 'wp-easycart' ), 'name' => 'edit', 'icon' => 'edit', 'action' => 'edit' ),
				array( 'label' => __( 'Quick Edit', 'wp-easycart' ), 'name' => 'quick-edit', 'icon' => 'welcome-write-blog', 'href' => '#', 'onclick' => 'ecv2_user_open_quick_edit( \'{id}\' ); return false;' ),
				array( 'label' => __( 'Login as Customer', 'wp-easycart' ), 'name' => 'login-as', 'icon' => 'migrate', 'action' => 'user-login-override' ),
				array( 'label' => __( 'Send Password Reset', 'wp-easycart' ), 'name' => 'password-reset', 'icon' => 'lock', 'href' => '#', 'onclick' => 'ecv2_user_row_bulk( \'{id}\', \'accounts-force-password-reset\', true ); return false;' ),
				array( 'label' => __( 'Resend Activation', 'wp-easycart' ), 'name' => 'resend-activation', 'icon' => 'email-alt', 'action' => 'user-resend-activation', 'pending_only' => true ),
				array( 'label' => __( 'Delete', 'wp-easycart' ), 'name' => 'delete', 'icon' => 'trash', 'action' => 'delete-account', 'danger' => true, 'confirm' => true ),
			), $this->pro_gate ) );

			/*
			 * Spreadsheet view — account admin fields, names editable inline.
			 * PRO appends read-only commerce columns via the same filter.
			 */
			$this->set_spreadsheet_columns( apply_filters( 'wp_easycart_admin_user_list_spreadsheet_columns', array(
				array( 'name' => 'email', 'label' => __( 'Email', 'wp-easycart' ), 'format' => 'string' ),
				array( 'name' => 'first_name', 'label' => __( 'First Name', 'wp-easycart' ), 'format' => 'string', 'ss_editable' => true ),
				array( 'name' => 'last_name', 'label' => __( 'Last Name', 'wp-easycart' ), 'format' => 'string', 'ss_editable' => true ),
				array( 'name' => 'user_level', 'label' => __( 'Role', 'wp-easycart' ), 'format' => 'role_badge' ),
				array( 'name' => 'is_subscriber', 'label' => __( 'Subscriber', 'wp-easycart' ), 'format' => 'yes_no', 'ss_default_hidden' => true ),
				array( 'name' => 'date_created', 'label' => __( 'Registered', 'wp-easycart' ), 'format' => 'registered_date', 'ss_default_hidden' => true ),
				array( 'name' => 'last_login', 'label' => __( 'Last Login', 'wp-easycart' ), 'format' => 'last_login', 'ss_default_hidden' => true ),
				array( 'name' => 'user_id', 'label' => __( 'ID', 'wp-easycart' ), 'format' => 'int', 'is_id' => true ),
			) ) );

			/* ?role=<label> ( User Roles list → "View users", older bookmarks ) is the Role filter ( index 0 ). */
			if ( isset( $_GET['role'] ) && '' !== $_GET['role'] && ! isset( $_GET['filter_0'] ) ) { $_GET['filter_0'] = sanitize_text_field( wp_unslash( $_GET['role'] ) ); }

			// Filters. PRO appends spend/orders/recency/country/tag/etc.
			$filters = array(
				array(
					'data' => $this->role_options,
					'label' => __( 'Role', 'wp-easycart' ),
					'type' => 'select',
					'where' => 'ec_user.user_level = %s',
				),
				array(
					'data' => array(
						(object) array( 'value' => '1', 'label' => __( 'Subscribed', 'wp-easycart' ), 'icon' => 'email-alt' ),
						(object) array( 'value' => '0', 'label' => __( 'Not Subscribed', 'wp-easycart' ), 'icon' => 'dismiss' ),
					),
					'label' => __( 'Newsletter', 'wp-easycart' ),
					'type' => 'pills',
					'where' => 'ec_user.is_subscriber = %d',
				),
				array(
					'data' => array(
						(object) array( 'value' => '30', 'label' => __( 'Last 30 Days', 'wp-easycart' ) ),
						(object) array( 'value' => '90', 'label' => __( 'Last 90 Days', 'wp-easycart' ) ),
						(object) array( 'value' => '365', 'label' => __( 'Last Year', 'wp-easycart' ) ),
					),
					'label' => __( 'Registered', 'wp-easycart' ),
					'type' => 'pills',
					'where_callback' => true,
					'callback_key' => 'registered_window',
				),
			);
			$this->set_filters( apply_filters( 'wp_easycart_admin_user_list_filters', $filters, $this->pro_gate ) );

			// Health dashboard.
			$this->compute_health_data();
			/* Grouped strip (same renderer as orders): 'account' = account state,
			   PRO appends a 'commerce' group. */
			$health_stats = array(
				array( 'label' => __( 'All', 'wp-easycart' ), 'value' => $this->health_data['total'], 'filter_value' => '', 'color' => 'default', 'group' => 'account' ),
				array( 'label' => __( 'New (30d)', 'wp-easycart' ), 'value' => $this->health_data['new_30'], 'filter_value' => 'new_30', 'color' => 'green', 'group' => 'account' ),
				array( 'label' => __( 'Subscribers', 'wp-easycart' ), 'value' => $this->health_data['subscribers'], 'filter_value' => 'subscribers', 'color' => 'cyan', 'group' => 'account' ),
				array( 'label' => __( 'Never logged in', 'wp-easycart' ), 'value' => $this->health_data['never_logged_in'], 'filter_value' => 'never_logged_in', 'color' => 'gray', 'group' => 'account' ),
				array( 'label' => __( 'Pending', 'wp-easycart' ), 'value' => $this->health_data['pending'], 'filter_value' => 'pending', 'color' => 'amber', 'group' => 'account' ),
			);
			$this->set_health_stats( apply_filters( 'wp_easycart_admin_user_list_health_stats', $health_stats, $this->health_data, $this->pro_gate ) );
		}

		private function get_role_bulk_options() {
			$options = array();
			foreach ( $this->role_options as $role ) {
				$options[] = array( 'value' => $role->value, 'label' => $role->label );
			}
			return $options;
		}

		private function compute_health_data() {
			global $wpdb;
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT
				COUNT(*) AS total,
				SUM( CASE WHEN date_created IS NOT NULL AND date_created >= DATE_SUB( NOW(), INTERVAL %d DAY ) THEN 1 ELSE 0 END ) AS new_30,
				SUM( CASE WHEN is_subscriber = 1 THEN 1 ELSE 0 END ) AS subscribers,
				SUM( CASE WHEN last_login IS NULL THEN 1 ELSE 0 END ) AS never_logged_in,
				SUM( CASE WHEN user_level = 'pending' THEN 1 ELSE 0 END ) AS pending
			FROM ec_user", self::NEW_CUSTOMER_DAYS ) );

			$this->health_data = array(
				'total'           => $row ? (int) $row->total : 0,
				'new_30'          => $row ? (int) $row->new_30 : 0,
				'subscribers'     => $row ? (int) $row->subscribers : 0,
				'never_logged_in' => $row ? (int) $row->never_logged_in : 0,
				'pending'         => $row ? (int) $row->pending : 0,
			);
		}

		protected function get_health_filter_where( $filter_key ) {
			switch ( $filter_key ) {
				case 'new_30':
					return 'ec_user.date_created IS NOT NULL AND ec_user.date_created >= DATE_SUB( NOW(), INTERVAL ' . (int) self::NEW_CUSTOMER_DAYS . ' DAY )';
				case 'subscribers':
					return 'ec_user.is_subscriber = 1';
				case 'never_logged_in':
					return 'ec_user.last_login IS NULL';
				case 'pending':
					return "ec_user.user_level = 'pending'";
			}
			// PRO health filters (with_orders, vip, at_risk, ...) resolve here.
			return apply_filters( 'wp_easycart_admin_user_health_filter_where', '', $filter_key );
		}

		protected function get_filter_callback_where( $filter_index, $value ) {
			$filter = isset( $this->filters[ $filter_index ] ) ? $this->filters[ $filter_index ] : null;
			if ( ! $filter ) {
				return '';
			}
			$callback_key = isset( $filter['callback_key'] ) ? $filter['callback_key'] : '';

			if ( 'registered_window' === $callback_key ) {
				$days = (int) $value;
				if ( $days > 0 ) {
					return 'ec_user.date_created IS NOT NULL AND ec_user.date_created >= DATE_SUB( NOW(), INTERVAL ' . $days . ' DAY )';
				}
				return '';
			}

			// PRO callback filters (spend range, orders range, last-order recency...).
			return apply_filters( 'wp_easycart_admin_user_filter_callback_where', '', $callback_key, $value, $filter );
		}

		/* ------------------------------------------------------------------ */
		/* Cell rendering                                                       */
		/* ------------------------------------------------------------------ */

		/**
		 * The base header renders the legacy CSV importer ( Media Library upload + paste URL ), which no longer works
		 * on the V2 page. Swap its button for the V2 import dialog and drop the hidden legacy form.
		 */
		protected function print_page_header() {
			ob_start(); parent::print_page_header(); $html = ob_get_clean();
			$btn = '<a href="#" class="ecv2-btn ecv2-btn-ghost ecv2-btn-sm" id="ecv2-user-import-btn" data-nonce="' . esc_attr( wp_create_nonce( wp_easycart_admin_user_import::NONCE ) ) . '" onclick="return ecv2u_import.open( this );"><span class="dashicons dashicons-upload"></span> ' . esc_html__( 'Import Users', 'wp-easycart' ) . '</a>';
			$html = preg_replace( '/<a onclick="ec_admin_importer_open_close\([^"]*"[^>]*>.*?<\/a>/s', $btn, $html, 1 );
			$html = preg_replace( '/<div id="[a-z_]+_importer" class="ec_importer_form">.*?<\/div>\s*<div id="[a-z_]+_importer_status" class="ec_importer_status"><\/div>/s', '', $html, 1 );
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $html is the parent's already-escaped header output with an esc_attr()/esc_html()-built button swapped in.
		}

		protected function print_cell_content( $result, $col ) {
			switch ( isset( $col['format'] ) ? $col['format'] : '' ) {
				case 'user_identity':
					$this->print_user_identity( $result );
					return;
				case 'role_badge':
					$this->print_role_badge( $result );
					return;
				case 'registered_date':
					$this->print_nullable_date( $result->date_created, __( 'Before tracking', 'wp-easycart' ), $this->tracking_note() );
					return;
				case 'last_login':
					$this->print_last_login( $result );
					return;
			}

			/*
			 * PRO cell formats route through this filter. A renderer returns
			 * HTML (escaped internally); empty string falls through to base.
			 */
			$custom = apply_filters( 'wp_easycart_admin_user_cell_content', '', $result, $col, $this );
			if ( '' !== $custom ) {
				echo $custom; // phpcs:ignore WordPress.Security.EscapeOutput -- built and escaped by the renderer.
				return;
			}

			parent::print_cell_content( $result, $col );
		}

		public function print_user_identity( $result ) {
			$first = isset( $result->first_name ) ? trim( wp_unslash( $result->first_name ) ) : '';
			$last  = isset( $result->last_name ) ? trim( wp_unslash( $result->last_name ) ) : '';
			$name  = trim( $first . ' ' . $last );
			$email = isset( $result->email ) ? $result->email : '';
			$edit_url = $this->get_url( $this->key, $result->{ $this->key }, false, 'ec_admin_form_action', 'edit' );

			echo '<div class="ecv2-user-identity">';
			$this->print_user_avatar( $result );
			echo '<div class="ecv2-user-identity-text">';
			echo '<a href="' . esc_url( $edit_url ) . '" class="ecv2-link-primary ecv2-user-name">';
			echo ( '' !== $name ) ? esc_html( $name ) : '<span class="ecv2-user-name-empty">' . esc_html__( '(no name)', 'wp-easycart' ) . '</span>';
			echo '</a>';
			echo '<span class="ecv2-user-email">' . esc_html( $email ) . '</span>';
			$this->print_identity_row_actions( $result, $edit_url );
			echo '</div>';
			$this->print_user_flags( $result );
			echo '</div>';
		}

		/**
		 * Hover actions under the customer name (table view). Reuses the
		 * generic .ecv2-row-action-link styles from admin-v2.css; visibility
		 * is handled by .ecv2-user-row-actions in admin-users-v2.css.
		 */
		private function print_identity_row_actions( $result, $edit_url ) {
			$delete_url = $this->get_url( $this->key, $result->{ $this->key }, false, 'ec_admin_form_action', 'delete-account' );
			echo '<div class="ecv2-user-row-actions">';
			echo '<a href="' . esc_url( $edit_url ) . '" class="ecv2-row-action-link">' . esc_html__( 'Edit', 'wp-easycart' ) . '</a>';
			echo '<span class="ecv2-row-action-sep">|</span>';
			echo '<a href="#" class="ecv2-row-action-link" onclick="ecv2_user_open_quick_edit( \'' . esc_attr( (int) $result->{ $this->key } ) . '\' ); return false;">' . esc_html__( 'Quick Edit', 'wp-easycart' ) . '</a>';
			echo '<span class="ecv2-row-action-sep">|</span>';
			echo '<a href="' . esc_url( $delete_url ) . '" class="ecv2-row-action-link ecv2-row-action-link-danger" onclick="return ecv2_user_confirm_delete( this );">' . esc_html__( 'Delete', 'wp-easycart' ) . '</a>';
			echo '</div>';
		}

		public function print_user_avatar( $result, $size_class = '' ) {
			$first = isset( $result->first_name ) ? trim( wp_unslash( $result->first_name ) ) : '';
			$last  = isset( $result->last_name ) ? trim( wp_unslash( $result->last_name ) ) : '';
			$email = isset( $result->email ) ? $result->email : '';

			$initials = strtoupper( mb_substr( $first, 0, 1 ) . mb_substr( $last, 0, 1 ) );
			if ( '' === trim( $initials ) ) {
				$initials = strtoupper( mb_substr( $email, 0, 1 ) );
			}
			if ( '' === trim( $initials ) ) {
				$initials = '?';
			}

			// Deterministic hue from the email so a customer keeps their color.
			$hue = 0;
			$hash = md5( strtolower( $email ) );
			$hue = hexdec( substr( $hash, 0, 4 ) ) % 360;

			echo '<span class="ecv2-user-avatar' . esc_attr( '' !== $size_class ? ' ' . $size_class : '' ) . '" style="background:hsl(' . esc_attr( $hue ) . ',55%,45%);" aria-hidden="true">' . esc_html( $initials ) . '</span>';
		}

		private function print_user_flags( $result ) {
			$flags = array();
			if ( isset( $result->is_subscriber ) && (int) $result->is_subscriber ) {
				$flags[] = array( 'icon' => 'email-alt', 'title' => __( 'Newsletter subscriber', 'wp-easycart' ), 'cls' => 'ecv2-user-flag-subscriber' );
			}
			if ( isset( $result->has_notes ) && (int) $result->has_notes ) {
				$flags[] = array( 'icon' => 'edit-page', 'title' => __( 'Has account notes', 'wp-easycart' ), 'cls' => 'ecv2-user-flag-notes' );
			}
			// PRO appends tax-exempt / VAT / saved-card / subscription flags.
			$flags = apply_filters( 'wp_easycart_admin_user_identity_flags', $flags, $result );
			if ( empty( $flags ) ) {
				return;
			}
			echo '<span class="ecv2-user-flags">';
			foreach ( $flags as $flag ) {
				echo '<span class="dashicons dashicons-' . esc_attr( $flag['icon'] ) . ' ecv2-user-flag ' . esc_attr( isset( $flag['cls'] ) ? $flag['cls'] : '' ) . '" title="' . esc_attr( $flag['title'] ) . '"></span>';
			}
			echo '</span>';
		}

		public function print_role_badge( $result ) {
			$role = isset( $result->user_level ) ? $result->user_level : '';
			$known = array(
				'admin'   => 'ecv2-role-admin',
				'shopper' => 'ecv2-role-shopper',
				'pending' => 'ecv2-role-pending',
			);
			$cls = isset( $known[ $role ] ) ? $known[ $role ] : 'ecv2-role-custom';
			echo '<span class="ecv2-role-badge ' . esc_attr( $cls ) . '">' . esc_html( $role ) . '</span>';
		}

		private function print_nullable_date( $value, $empty_label, $empty_title = '' ) {
			$ts = ( null !== $value && '' !== $value && '0000-00-00 00:00:00' !== $value ) ? strtotime( $value ) : 0;
			if ( $ts <= 0 ) {
				echo '<span class="ecv2-date-empty"' . ( '' !== $empty_title ? ' title="' . esc_attr( $empty_title ) . '"' : '' ) . '>' . esc_html( $empty_label ) . '</span>';
				return;
			}
			$ts += $this->date_diff;
			echo '<span class="ecv2-date" title="' . esc_attr( date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts ) ) . '">';
			echo esc_html( $this->format_relative_date( $ts, time() + $this->date_diff ) );
			echo '</span>';
		}

		/**
		 * Last login needs context: an empty value on an account that predates
		 * the tracking field means "we don't know", not "they never logged in".
		 */
		public function print_last_login( $result ) {
			$value = isset( $result->last_login ) ? $result->last_login : null;
			$created = isset( $result->date_created ) ? $result->date_created : null;
			$predates_tracking = ( null === $created || '' === $created || '0000-00-00 00:00:00' === $created || strtotime( $created ) < strtotime( $this->tracking_start ) );
			$empty_label = $predates_tracking ? '&mdash;' : __( 'Never', 'wp-easycart' );

			$ts = ( null !== $value && '' !== $value && '0000-00-00 00:00:00' !== $value ) ? strtotime( $value ) : 0;
			if ( $ts <= 0 ) {
				echo '<span class="ecv2-date-empty" title="' . esc_attr( $this->tracking_note() ) . '">' . wp_kses_post( $empty_label ) . '</span>';
				return;
			}
			$this->print_nullable_date( $value, '' );
		}

		private function tracking_note() {
			/* translators: %s: date the new tracking fields were added. */
			return sprintf( __( 'Tracking for this field began %s.', 'wp-easycart' ), date_i18n( get_option( 'date_format' ), strtotime( $this->tracking_start ) ) );
		}

		/* ------------------------------------------------------------------ */
		/* Row / menu tweaks                                                    */
		/* ------------------------------------------------------------------ */

		protected function print_row_menu_item( $result, $action ) {
			// "Resend Activation" only makes sense for pending accounts.
			if ( isset( $action['pending_only'] ) && $action['pending_only'] && ( ! isset( $result->user_level ) || 'pending' !== $result->user_level ) ) {
				return;
			}
			parent::print_row_menu_item( $result, $action );
		}

		protected function print_table_row( $result ) {
			$row_id = $result->{ $this->key };
			echo '<tr class="ecv2-row" data-id="' . esc_attr( $row_id ) . '" data-email="' . esc_attr( isset( $result->email ) ? $result->email : '' ) . '">';
			echo '<td class="ecv2-col-check"><input type="checkbox" name="bulk[]" value="' . esc_attr( $row_id ) . '" class="ecv2-row-check" /></td>';
			foreach ( $this->list_columns as $col ) {
				if ( isset( $col['format'] ) && 'hidden' === $col['format'] ) {
					continue;
				}
				$extra_classes = '';
				if ( isset( $col['tablet_hide'] ) && $col['tablet_hide'] ) {
					$extra_classes .= ' ecv2-hide-tablet';
				}
				if ( isset( $col['laptop_hide'] ) && $col['laptop_hide'] ) {
					$extra_classes .= ' ecv2-hide-laptop';
				}
				echo '<td class="ecv2-cell ecv2-cell-' . esc_attr( $col['name'] ) . $extra_classes . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $extra_classes only ever holds literal class names set above.
				$this->print_cell_content( $result, $col );
				echo '</td>';
			}
			echo '<td class="ecv2-col-actions">';
			$this->print_row_actions( $result );
			echo '</td>';
			echo '</tr>';
		}

		/* ------------------------------------------------------------------ */
		/* Card view                                                            */
		/* ------------------------------------------------------------------ */

		protected function print_card( $result ) {
			$row_id = $result->{ $this->key };
			$first = isset( $result->first_name ) ? trim( wp_unslash( $result->first_name ) ) : '';
			$last  = isset( $result->last_name ) ? trim( wp_unslash( $result->last_name ) ) : '';
			$name  = trim( $first . ' ' . $last );
			$edit_url = $this->get_url( $this->key, $row_id, false, 'ec_admin_form_action', 'edit' );

			echo '<div class="ecv2-card ecv2-user-card" data-id="' . esc_attr( $row_id ) . '">';

			echo '<div class="ecv2-user-card-top">';
			$this->print_user_avatar( $result, 'ecv2-user-avatar-lg' );
			echo '<div class="ecv2-user-card-id">';
			echo '<a href="' . esc_url( $edit_url ) . '" class="ecv2-user-card-name">' . ( '' !== $name ? esc_html( $name ) : esc_html__( '(no name)', 'wp-easycart' ) ) . '</a>';
			echo '<span class="ecv2-user-card-email">' . esc_html( isset( $result->email ) ? $result->email : '' ) . '</span>';
			echo '</div>';
			$this->print_role_badge( $result );
			echo '</div>';

			echo '<div class="ecv2-user-card-meta">';
			echo '<div class="ecv2-user-card-meta-item"><span class="ecv2-user-card-meta-label">' . esc_html__( 'Registered', 'wp-easycart' ) . '</span>';
			$this->print_nullable_date( isset( $result->date_created ) ? $result->date_created : null, __( 'Before tracking', 'wp-easycart' ) );
			echo '</div>';
			echo '<div class="ecv2-user-card-meta-item"><span class="ecv2-user-card-meta-label">' . esc_html__( 'Last Login', 'wp-easycart' ) . '</span>';
			$this->print_nullable_date( isset( $result->last_login ) ? $result->last_login : null, __( 'Never', 'wp-easycart' ) );
			echo '</div>';
			echo '</div>';

			// PRO injects the KPI strip (orders / spend / lifecycle) here.
			do_action( 'wp_easycart_admin_user_card_kpis', $result, $this );

			echo '<div class="ecv2-card-footer">';
			echo '<input type="checkbox" name="bulk[]" value="' . esc_attr( $row_id ) . '" class="ecv2-row-check" />';
			echo '<div class="ecv2-user-card-actions">';
			$this->print_row_actions( $result );
			echo '</div>';
			echo '</div>';

			echo '</div>';
		}

		/* ------------------------------------------------------------------ */
		/* PRO teaser + modals                                                  */
		/* ------------------------------------------------------------------ */

		protected function print_health_dashboard() {
			$this->print_backfill_banner();

			parent::print_health_dashboard();

			// Upsell teaser card — rendered outside the stat toggle system so
			// it can't be hidden away and never masquerades as a real metric.
			if ( 'enabled' !== $this->pro_gate['state'] ) {
				echo '<div class="ecv2-user-pro-teaser" role="button" tabindex="0" data-state="' . esc_attr( $this->pro_gate['state'] ) . '" data-url="' . esc_url( $this->pro_gate['url'] ) . '" data-action="' . esc_attr( $this->pro_gate['action'] ) . '">';
				echo '<span class="dashicons dashicons-lock"></span>';
				echo '<span class="ecv2-user-pro-teaser-text"><strong>' . esc_html__( 'Customer Insights', 'wp-easycart' ) . '</strong> ' . esc_html__( 'Lifetime spend, order history, lifecycle segments, locations & more.', 'wp-easycart' ) . '</span>';
				echo '<span class="ecv2-user-pro-teaser-cta">' . esc_html( $this->pro_gate['desc'] ) . '</span>';
				echo '</div>';
			}
		}

		/**
		 * One-time migration banner: offers to populate the new Registered /
		 * Orders / Lifetime Spend / Last Order fields from existing order
		 * history. Shown while any account still has
		 * history_aggregates_built = 0, unless this admin dismissed it.
		 * Runs batched via the ecv2_user_backfill AJAX endpoint so large
		 * stores never block a request.
		 */
		protected function print_backfill_banner() {
			if ( $this->backfill_pending <= 0 ) {
				return;
			}
			if ( get_user_meta( get_current_user_id(), 'ecv2_user_backfill_dismissed', true ) ) {
				return;
			}
			echo '<div class="ecv2-user-backfill-banner" id="ecv2-user-backfill-banner" data-nonce="' . esc_attr( wp_create_nonce( 'wp-easycart-ecv2-user-backfill' ) ) . '" data-dismiss-nonce="' . esc_attr( wp_create_nonce( 'wp-easycart-ecv2-user-backfill-dismiss' ) ) . '">';
			echo '<span class="dashicons dashicons-database-import ecv2-user-backfill-icon"></span>';
			echo '<div class="ecv2-user-backfill-text">';
			echo '<strong>' . esc_html__( 'New customer fields', 'wp-easycart' ) . '</strong> ';
			/* translators: %s: date the new tracking fields were added. */
			echo esc_html( sprintf( __( 'Registered, Last Login, Orders & Lifetime Spend started tracking on %s and are empty for existing accounts. Populate registration dates and purchase history from your existing orders now.', 'wp-easycart' ), date_i18n( get_option( 'date_format' ), strtotime( $this->tracking_start ) ) ) );
			echo ' <span class="ecv2-user-backfill-note">' . esc_html__( 'Last Login fills in as customers sign in.', 'wp-easycart' ) . '</span>';
			echo '</div>';
			echo '<div class="ecv2-user-backfill-actions">';
			echo '<span class="ecv2-user-backfill-progress" id="ecv2-user-backfill-progress" style="display:none;"></span>';
			/* translators: %d: number of accounts to process. */
			echo '<button type="button" class="ecv2-btn ecv2-btn-primary ecv2-btn-sm" id="ecv2-user-backfill-btn" onclick="ecv2_user_run_backfill( this );">' . esc_html( sprintf( __( 'Populate from Order History (%d)', 'wp-easycart' ), $this->backfill_pending ) ) . '</button>';
			echo '<button type="button" class="ecv2-user-backfill-dismiss" id="ecv2-user-backfill-dismiss" title="' . esc_attr__( 'Dismiss', 'wp-easycart' ) . '"><span class="dashicons dashicons-no-alt"></span></button>';
			echo '</div>';
			echo '</div>';
		}

		protected function print_custom_modals() {
			// Quick Edit slideout markup ships with the template below.
			include EC_PLUGIN_DIRECTORY . '/admin/template/users/users/quick-edit-user-slideout.php';
		}
	}

endif;

/* ========================================================================== */
/* AJAX endpoints                                                              */
/* ========================================================================== */

if ( ! function_exists( 'ecv2_user_can_manage' ) ) {
	function ecv2_user_can_manage() {
		return current_user_can( 'manage_options' ) || current_user_can( 'wpec_users' );
	}
}

/**
 * One-time backfill of the new account fields from order history, batched.
 * Per batch of 500 accounts (history_aggregates_built = 0):
 *  - completed_order_count / lifetime_spend / last_order_date from ec_order
 *  - date_created backfilled from the account's earliest order when empty
 *  - history_aggregates_built set to 1 (also for accounts with no orders,
 *    so the banner converges to zero)
 * The JS runner loops until `remaining` hits 0. Data lives in the free DB
 * for both editions; PRO simply displays the commerce columns.
 */
add_action( 'wp_ajax_ecv2_user_backfill', 'ecv2_user_backfill' );
function ecv2_user_backfill() {
	if ( ! ecv2_user_can_manage() ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
	}
	if ( ! isset( $_POST['wp_easycart_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_easycart_nonce'] ) ), 'wp-easycart-ecv2-user-backfill' ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wp-easycart' ) ) );
	}

	global $wpdb;
	$batch_ids = $wpdb->get_col( 'SELECT user_id FROM ec_user WHERE history_aggregates_built = 0 ORDER BY user_id ASC LIMIT 500' );

	if ( ! empty( $batch_ids ) ) {
		$id_list = implode( ',', array_map( 'intval', $batch_ids ) );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $id_list is an implode of intval() ids built on the line above.
		$wpdb->query(
			"UPDATE ec_user u
			 LEFT JOIN (
				SELECT user_id, COUNT(*) AS order_count, SUM( grand_total ) AS spend, MAX( order_date ) AS last_order, MIN( order_date ) AS first_order
				FROM ec_order WHERE user_id IN ( {$id_list} ) GROUP BY user_id
			 ) o ON o.user_id = u.user_id
			 SET u.completed_order_count = COALESCE( o.order_count, 0 ),
			     u.lifetime_spend = COALESCE( o.spend, 0 ),
			     u.last_order_date = o.last_order,
			     u.date_created = COALESCE( u.date_created, o.first_order ),
			     u.history_aggregates_built = 1
			 WHERE u.user_id IN ( {$id_list} )"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		wp_cache_flush();
	}

	$remaining = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_user WHERE history_aggregates_built = 0' );
	wp_send_json_success( array(
		'processed' => count( $batch_ids ),
		'remaining' => $remaining,
	) );
}

/**
 * Persist a per-admin dismissal of the backfill banner.
 */
add_action( 'wp_ajax_ecv2_user_backfill_dismiss', 'ecv2_user_backfill_dismiss' );
function ecv2_user_backfill_dismiss() {
	if ( ! ecv2_user_can_manage() ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
	}
	if ( ! isset( $_POST['wp_easycart_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_easycart_nonce'] ) ), 'wp-easycart-ecv2-user-backfill-dismiss' ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wp-easycart' ) ) );
	}
	update_user_meta( get_current_user_id(), 'ecv2_user_backfill_dismissed', 1 );
	wp_send_json_success();
}

/**
 * Inline update (spreadsheet double-click): first_name / last_name only.
 * PRO extends the allowed field list via filter for its own columns.
 */
add_action( 'wp_ajax_ecv2_user_inline_update', 'ecv2_user_inline_update' );
function ecv2_user_inline_update() {
	if ( ! ecv2_user_can_manage() ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
	}
	if ( ! isset( $_POST['wp_easycart_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_easycart_nonce'] ) ), 'wp-easycart-ecv2-user-inline-update' ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wp-easycart' ) ) );
	}

	$user_id = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;
	$field   = isset( $_POST['field'] ) ? sanitize_key( $_POST['field'] ) : '';
	$value   = isset( $_POST['value'] ) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '';

	if ( ! $user_id || ! $field ) {
		wp_send_json_error( array( 'message' => __( 'Invalid request.', 'wp-easycart' ) ) );
	}

	$allowed_fields = apply_filters( 'wp_easycart_admin_user_inline_fields', array( 'first_name', 'last_name' ) );
	if ( ! in_array( $field, $allowed_fields, true ) ) {
		wp_send_json_error( array( 'message' => __( 'Field not allowed.', 'wp-easycart' ) ) );
	}

	global $wpdb;
	$old_value = $wpdb->get_var( $wpdb->prepare( "SELECT `$field` FROM ec_user WHERE user_id = %d", $user_id ) ); // phpcs:ignore -- field whitelisted above.
	$value = wp_easycart_escape_html( $value );

	$updated = $wpdb->update( 'ec_user', array( $field => $value ), array( 'user_id' => $user_id ), array( '%s' ), array( '%d' ) );
	if ( false === $updated ) {
		wp_send_json_error( array( 'message' => __( 'Could not save the change. Please try again.', 'wp-easycart' ) ) );
	}
	do_action( 'wpeasycart_admin_user_updated', $user_id, array( $field => $value ) );
	wp_cache_flush();

	wp_send_json_success( array(
		'user_id'       => $user_id,
		'field'         => $field,
		'value'         => $value,
		'display_value' => wp_specialchars_decode( $value, ENT_QUOTES ),
		'old_value'     => $old_value,
	) );
}

/**
 * Bulk edit modal apply: role and/or subscriber flag for up to 500 accounts.
 */
add_action( 'wp_ajax_ecv2_user_bulk_edit', 'ecv2_user_bulk_edit' );
function ecv2_user_bulk_edit() {
	if ( ! ecv2_user_can_manage() ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
	}
	if ( ! isset( $_POST['wp_easycart_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_easycart_nonce'] ) ), 'wp-easycart-ecv2-user-bulk-edit' ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wp-easycart' ) ) );
	}

	$ids_raw = isset( $_POST['user_ids'] ) ? (array) $_POST['user_ids'] : array();
	$ids = array_filter( array_map( 'intval', $ids_raw ) );
	if ( empty( $ids ) ) {
		wp_send_json_error( array( 'message' => __( 'No customers selected.', 'wp-easycart' ) ) );
	}
	if ( count( $ids ) > 500 ) {
		wp_send_json_error( array( 'message' => __( 'You can only process up to 500 customers at a time.', 'wp-easycart' ) ) );
	}

	global $wpdb;
	$data = array();
	$formats = array();

	if ( isset( $_POST['user_level'] ) && '' !== $_POST['user_level'] ) {
		$role = sanitize_text_field( wp_unslash( $_POST['user_level'] ) );
		$role_exists = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_role WHERE role_label = %s', $role ) );
		if ( ! $role_exists ) {
			wp_send_json_error( array( 'message' => __( 'Unknown role.', 'wp-easycart' ) ) );
		}
		/*
		 * Guard: never let a wpec_users manager (non-admin) grant a role that
		 * carries EasyCart admin access — that would be privilege escalation.
		 */
		$grants_admin = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT admin_access FROM ec_role WHERE role_label = %s', $role ) );
		if ( $grants_admin && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Only site administrators can assign admin-level roles.', 'wp-easycart' ) ) );
		}
		$data['user_level'] = $role;
		$formats[] = '%s';
	}

	if ( isset( $_POST['is_subscriber'] ) && '' !== $_POST['is_subscriber'] ) {
		$data['is_subscriber'] = (int) $_POST['is_subscriber'] ? 1 : 0;
		$formats[] = '%d';
	}

	$data = apply_filters( 'wp_easycart_admin_user_bulk_edit_data', $data, $_POST ); // PRO adds its fields (validated in PRO).

	if ( empty( $data ) ) {
		wp_send_json_error( array( 'message' => __( 'No changes selected.', 'wp-easycart' ) ) );
	}

	$updated = 0;
	foreach ( $ids as $id ) {
		$result = $wpdb->update( 'ec_user', $data, array( 'user_id' => $id ) );
		if ( false !== $result ) {
			$updated++;
			do_action( 'wpeasycart_admin_user_updated', $id, $data );
		}
	}
	wp_cache_flush();

	wp_send_json_success( array(
		'updated' => $updated,
		/* translators: %d: number of updated customer accounts. */
		'message' => sprintf( __( '%d customers updated.', 'wp-easycart' ), $updated ),
	) );
}

/**
 * Quick Edit slideout: fetch one account's editable basics.
 */
add_action( 'wp_ajax_ecv2_user_quick_get', 'ecv2_user_quick_get' );
function ecv2_user_quick_get() {
	if ( ! ecv2_user_can_manage() ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
	}
	if ( ! isset( $_POST['wp_easycart_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_easycart_nonce'] ) ), 'wp-easycart-ecv2-user-quick-edit' ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wp-easycart' ) ) );
	}

	$user_id = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;
	global $wpdb;
	$user = $wpdb->get_row( $wpdb->prepare( 'SELECT user_id, email, email_other, first_name, last_name, user_level, is_subscriber, vat_registration_number FROM ec_user WHERE user_id = %d', $user_id ) );
	if ( ! $user ) {
		wp_send_json_error( array( 'message' => __( 'Customer not found.', 'wp-easycart' ) ) );
	}

	$user->first_name = wp_specialchars_decode( wp_unslash( $user->first_name ), ENT_QUOTES );
	$user->last_name  = wp_specialchars_decode( wp_unslash( $user->last_name ), ENT_QUOTES );

	wp_send_json_success( array( 'user' => $user ) );
}

/**
 * Quick Edit slideout: save the account basics.
 */
add_action( 'wp_ajax_ecv2_user_quick_save', 'ecv2_user_quick_save' );
function ecv2_user_quick_save() {
	if ( ! ecv2_user_can_manage() ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
	}
	if ( ! isset( $_POST['wp_easycart_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_easycart_nonce'] ) ), 'wp-easycart-ecv2-user-quick-edit' ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wp-easycart' ) ) );
	}

	$user_id = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;
	if ( ! $user_id ) {
		wp_send_json_error( array( 'message' => __( 'Invalid request.', 'wp-easycart' ) ) );
	}

	global $wpdb;
	$existing = $wpdb->get_row( $wpdb->prepare( 'SELECT user_id FROM ec_user WHERE user_id = %d', $user_id ) );
	if ( ! $existing ) {
		wp_send_json_error( array( 'message' => __( 'Customer not found.', 'wp-easycart' ) ) );
	}

	$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	if ( '' === $email || ! is_email( $email ) ) {
		wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'wp-easycart' ), 'field' => 'email' ) );
	}
	$duplicate = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT user_id FROM ec_user WHERE email = %s AND user_id != %d LIMIT 1', $email, $user_id ) );
	if ( $duplicate > 0 ) {
		wp_send_json_error( array( 'message' => __( 'Another account already uses this email address.', 'wp-easycart' ), 'field' => 'email' ) );
	}

	$role = isset( $_POST['user_level'] ) ? sanitize_text_field( wp_unslash( $_POST['user_level'] ) ) : 'shopper';
	$role_row = $wpdb->get_row( $wpdb->prepare( 'SELECT role_label, admin_access FROM ec_role WHERE role_label = %s', $role ) );
	if ( ! $role_row ) {
		wp_send_json_error( array( 'message' => __( 'Unknown role.', 'wp-easycart' ), 'field' => 'user_level' ) );
	}
	if ( (int) $role_row->admin_access && ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Only site administrators can assign admin-level roles.', 'wp-easycart' ), 'field' => 'user_level' ) );
	}

	$data = array(
		'email'         => $email,
		'email_other'   => isset( $_POST['email_other'] ) ? sanitize_email( wp_unslash( $_POST['email_other'] ) ) : '',
		'first_name'    => wp_easycart_escape_html( sanitize_text_field( wp_unslash( isset( $_POST['first_name'] ) ? $_POST['first_name'] : '' ) ) ),
		'last_name'     => wp_easycart_escape_html( sanitize_text_field( wp_unslash( isset( $_POST['last_name'] ) ? $_POST['last_name'] : '' ) ) ),
		'user_level'    => $role_row->role_label,
		'is_subscriber' => ( isset( $_POST['is_subscriber'] ) && (int) $_POST['is_subscriber'] ) ? 1 : 0,
		'vat_registration_number' => sanitize_text_field( wp_unslash( isset( $_POST['vat_registration_number'] ) ? $_POST['vat_registration_number'] : '' ) ),
	);
	$updated = $wpdb->update(
		'ec_user',
		$data,
		array( 'user_id' => $user_id ),
		array( '%s', '%s', '%s', '%s', '%s', '%d', '%s' ),
		array( '%d' )
	);
	if ( false === $updated ) {
		wp_send_json_error( array( 'message' => __( 'Could not save. Please try again.', 'wp-easycart' ) ) );
	}
	do_action( 'wpeasycart_admin_user_updated', $user_id, $data );
	wp_cache_flush();

	wp_send_json_success( array(
		'user_id'    => $user_id,
		'email'      => $email,
		'first_name' => wp_specialchars_decode( $data['first_name'], ENT_QUOTES ),
		'last_name'  => wp_specialchars_decode( $data['last_name'], ENT_QUOTES ),
		'user_level' => $data['user_level'],
		'message'    => __( 'Customer saved.', 'wp-easycart' ),
	) );
}
/**
 * Stale links: several places ( the order editor's "View account", older bookmarks ) point at
 * subpage=users, which is not a route and renders the V1 shell without styles. Redirect to accounts,
 * preserving the rest of the query ( user_id, ec_admin_form_action ).
 */
add_action( 'admin_init', 'ecv2_users_subpage_alias' );
function ecv2_users_subpage_alias() {
	if ( ! isset( $_GET['page'], $_GET['subpage'] ) || 'wp-easycart-users' !== $_GET['page'] || 'users' !== $_GET['subpage'] || wp_doing_ajax() ) { return; }
	$q = $_GET; $q['subpage'] = 'accounts';
	wp_safe_redirect( add_query_arg( array_map( 'sanitize_text_field', array_map( 'wp_unslash', $q ) ), admin_url( 'admin.php' ) ) );
	exit;
}
