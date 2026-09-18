<?php
/**
 * WP EasyCart Admin — Customer (User) Details V2.
 *
 * Extends the v1 details class so all data loading, field-list filters, and
 * the legacy overview filter keep working, and swaps the template for the
 * ecdv2 chassis (sticky header, grouped tab rail, card sections, dirty
 * tracking) used by product details V2.
 *
 * Saving intentionally delegates to the existing wp_easycart_admin_users
 * insert_user() / update_user() handlers via a single AJAX endpoint that
 * posts the full form: those handlers own the WP-account sync, subscriber
 * sync, password backup, QuickBooks push, and wpeasycart_account_updated
 * hook, and we must not fork that logic. Dirty tracking governs UX only.
 *
 * PRO layers (Activity, address book, notes timeline, tags, anonymize,
 * merge) hook the wp_easycart_admin_user_details_v2_* actions/filters
 * declared in the template.
 *
 * @since 5.9.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_details_user_v2' ) ) :

	class wp_easycart_admin_details_user_v2 extends wp_easycart_admin_details_user {

		public $is_new = false;
		public $tracking_start = '';

		public function output( $type = 'edit' ) {
			$this->init();
			$this->is_new = ( 'edit' !== $type );
			if ( 'edit' == $type ) {
				$this->init_data();
			} else {
				/* Pre-fill from the query ( Subscribers → "Create account…", or any link that knows the person ). */
				foreach ( array( 'email' => 'sanitize_email', 'first_name' => 'sanitize_text_field', 'last_name' => 'sanitize_text_field' ) as $k => $fn ) {
					if ( isset( $_GET[ $k ] ) && '' !== $_GET[ $k ] ) { $this->user->{ $k } = call_user_func( $fn, wp_unslash( $_GET[ $k ] ) ); }
				}
				if ( ! empty( $this->user->email ) && property_exists( $this->user, 'is_subscriber' ) ) { $this->user->is_subscriber = 1; }
			}
			if ( 'edit' == $type && ( $this->record_not_found || ! $this->user->user_id ) ) {
				$this->print_record_not_found_notice();
				return false;
			}
			$this->tracking_start = get_option( 'ec_option_ecv2_user_tracking_start', current_time( 'mysql' ) );
			include EC_PLUGIN_DIRECTORY . '/admin/template/users/users/user-details-v2.php';
			return true;
		}

		/* ------------------------------------------------------------------ */
		/* Card helpers ( ecdv2 markup, same classes as product details )       */
		/* ------------------------------------------------------------------ */

		/**
		 * Opens a details card.
		 *
		 * @since 6.0.0 Added $header_actions.
		 *
		 * @param string        $section        Section key ( data-ecdv2-section ).
		 * @param string        $title          Card title.
		 * @param string        $hint           Optional hint beside the title.
		 * @param callable|null $header_actions Optional callback that prints ( escaped ) header buttons before the Help link.
		 */
		public function section_open( $section, $title, $hint = '', $header_actions = null ) {
			echo '<div class="ecdv2-card" data-ecdv2-section="' . esc_attr( $section ) . '">';
			echo '<div class="ecdv2-card-saving"></div>';
			echo '<div class="ecdv2-card-header">';
			echo '<h3 class="ecdv2-card-title">' . esc_html( $title ) . '</h3>';
			if ( '' !== $hint ) {
				echo '<span class="ecdv2-card-hint">' . esc_html( $hint ) . '</span>';
			}
			if ( is_callable( $header_actions ) ) {
				call_user_func( $header_actions );
			}
			echo '<a href="' . esc_url_raw( $this->docs_link ) . '" target="_blank" class="ecdv2-help-link"><span class="dashicons dashicons-editor-help" style="font-size:14px;width:14px;height:14px;"></span>' . esc_html__( 'Help', 'wp-easycart' ) . '</a>';
			echo '</div><div class="ecdv2-card-body">';
		}

		public function section_close() {
			echo '</div></div>';
		}

		public function text_field( $name, $label, $value, $section, $args = array() ) {
			$type = isset( $args['type'] ) ? $args['type'] : 'text';
			$required = ! empty( $args['required'] );
			$half = ! empty( $args['half'] );
			echo '<div class="ecudv2-field' . ( $half ? ' ecudv2-field-half' : '' ) . '">';
			echo '<label for="ecudv2_' . esc_attr( $name ) . '">' . esc_html( $label ) . ( $required ? ' <span class="ecudv2-req">*</span>' : '' ) . '</label>';
			echo '<input type="' . esc_attr( $type ) . '" id="ecudv2_' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" data-ecdv2-sec="' . esc_attr( $section ) . '"';
			if ( isset( $args['list'] ) ) {
				echo ' list="' . esc_attr( $args['list'] ) . '"';
			}
			if ( isset( $args['autocomplete'] ) ) {
				echo ' autocomplete="' . esc_attr( $args['autocomplete'] ) . '"';
			}
			if ( isset( $args['oninput'] ) ) {
				echo ' oninput="' . esc_attr( $args['oninput'] ) . '"';
			}
			echo ' />';
			if ( isset( $args['hint'] ) ) {
				echo '<span class="ecudv2-field-hint">' . esc_html( $args['hint'] ) . '</span>';
			}
			echo '<span class="ecudv2-field-error" data-ecudv2-error="' . esc_attr( $name ) . '"></span>';
			echo '</div>';
		}

		public function toggle_field( $name, $label, $checked, $section, $hint = '' ) {
			echo '<label class="ecudv2-toggle-row">';
			echo '<span class="ecdv2-toggle"><input type="checkbox" name="' . esc_attr( $name ) . '" id="ecudv2_' . esc_attr( $name ) . '" value="1"' . checked( (bool) $checked, true, false ) . ' data-ecdv2-sec="' . esc_attr( $section ) . '" /><span class="ecdv2-toggle-track"></span></span>';
			echo '<span class="ecudv2-toggle-text"><strong>' . esc_html( $label ) . '</strong>';
			if ( '' !== $hint ) {
				echo '<span class="ecudv2-field-hint">' . esc_html( $hint ) . '</span>';
			}
			echo '</span></label>';
		}

		public function address_fields( $prefix, $info, $section ) {
			$val = function( $key ) use ( $info ) {
				return isset( $info->{$key} ) ? $info->{$key} : '';
			};
			echo '<div class="ecudv2-field-grid">';
			$this->text_field( $prefix . '_first_name', __( 'First Name', 'wp-easycart' ), $val( 'first_name' ), $section, array( 'half' => true ) );
			$this->text_field( $prefix . '_last_name', __( 'Last Name', 'wp-easycart' ), $val( 'last_name' ), $section, array( 'half' => true ) );
			$this->text_field( $prefix . '_company_name', __( 'Company Name', 'wp-easycart' ), $val( 'company_name' ), $section );
			$this->text_field( $prefix . '_address_line_1', __( 'Address Line 1', 'wp-easycart' ), $val( 'address_line_1' ), $section );
			$this->text_field( $prefix . '_address_line_2', __( 'Address Line 2', 'wp-easycart' ), $val( 'address_line_2' ), $section );
			$this->text_field( $prefix . '_city', __( 'City', 'wp-easycart' ), $val( 'city' ), $section, array( 'half' => true ) );
			$this->text_field( $prefix . '_state', __( 'State / Province', 'wp-easycart' ), $val( 'state' ), $section, array( 'half' => true ) );
			$this->text_field( $prefix . '_zip', __( 'Zip / Postal Code', 'wp-easycart' ), $val( 'zip' ), $section, array( 'half' => true ) );
			$this->text_field( $prefix . '_country', __( 'Country', 'wp-easycart' ), $val( 'country' ), $section, array( 'half' => true, 'list' => 'ecudv2_countries' ) );
			$this->text_field( $prefix . '_phone', __( 'Phone Number', 'wp-easycart' ), $val( 'phone' ), $section );
			echo '</div>';
		}

		public function print_country_datalist() {
			global $wpdb;
			$countries = $wpdb->get_col( 'SELECT name_cnt FROM ec_country ORDER BY sort_order ASC, name_cnt ASC' );
			echo '<datalist id="ecudv2_countries">';
			foreach ( (array) $countries as $country ) {
				echo '<option value="' . esc_attr( $country ) . '"></option>';
			}
			echo '</datalist>';
		}

		public function get_roles() {
			global $wpdb;
			return $wpdb->get_results( 'SELECT role_label, admin_access FROM ec_role ORDER BY role_label ASC' );
		}

		public function get_wp_user() {
			if ( empty( $this->user->email ) ) {
				return false;
			}
			return get_user_by( 'email', $this->user->email );
		}

		public function has_legacy_hash_backup() {
			return isset( $this->user->password_admin_v1 ) && '' !== trim( (string) $this->user->password_admin_v1 );
		}

		public function print_avatar( $size_class = '' ) {
			$first = trim( wp_unslash( (string) $this->user->first_name ) );
			$last = trim( wp_unslash( (string) $this->user->last_name ) );
			$email = (string) $this->user->email;
			$initials = strtoupper( mb_substr( $first, 0, 1 ) . mb_substr( $last, 0, 1 ) );
			if ( '' === trim( $initials ) ) {
				$initials = '' !== $email ? strtoupper( mb_substr( $email, 0, 1 ) ) : '+';
			}
			$hue = hexdec( substr( md5( strtolower( $email ) ), 0, 4 ) ) % 360;
			echo '<span class="ecv2-user-avatar' . esc_attr( '' !== $size_class ? ' ' . $size_class : '' ) . '" id="ecudv2_header_avatar" style="background:hsl(' . esc_attr( $hue ) . ',55%,45%);">' . esc_html( $initials ) . '</span>';
		}

		public function nullable_date( $value, $empty_label ) {
			$ts = ( null !== $value && '' !== $value && '0000-00-00 00:00:00' !== $value ) ? strtotime( $value ) : 0;
			if ( $ts <= 0 ) {
				/* translators: %s: date tracking began. */
				return '<span class="ecv2-date-empty" title="' . esc_attr( sprintf( __( 'Tracking for this field began %s.', 'wp-easycart' ), date_i18n( get_option( 'date_format' ), strtotime( $this->tracking_start ) ) ) ) . '">' . esc_html( $empty_label ) . '</span>';
			}
			return '<span class="ecv2-date">' . esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts ) ) . '</span>';
		}

		/**
		 * GET action link that rides the legacy bulk pipeline for one user
		 * (password reset, single export) — the same trick the list uses.
		 */
		public function bulk_action_url( $action ) {
			return admin_url( 'admin.php?page=wp-easycart-users&subpage=accounts&ec_admin_form_action=' . rawurlencode( $action ) . '&bulk%5B%5D=' . (int) $this->user->user_id . '&wp_easycart_nonce=' . wp_create_nonce( 'wp-easycart-bulk-accounts' ) );
		}

		public function single_action_url( $action, $nonce_action ) {
			return admin_url( 'admin.php?page=wp-easycart-users&subpage=accounts&ec_admin_form_action=' . rawurlencode( $action ) . '&user_id=' . (int) $this->user->user_id . '&wp_easycart_nonce=' . wp_create_nonce( $nonce_action ) );
		}
	}

endif;

/* ========================================================================== */
/* AJAX endpoints ( FREE )                                                     */
/* ========================================================================== */

if ( ! function_exists( 'ecudv2_can_manage' ) ) {
	function ecudv2_can_manage() {
		return current_user_can( 'manage_options' ) || current_user_can( 'wpec_manager' ) || current_user_can( 'wpec_users' );
	}
}

/**
 * Guard shared by save/approve: non-site-admin managers may not assign an
 * admin-access role, nor edit an account that currently holds one.
 * Returns '' when allowed, or an error message.
 */
if ( ! function_exists( 'ecudv2_role_guard' ) ) {
	function ecudv2_role_guard( $user_id, $posted_role ) {
		if ( current_user_can( 'manage_options' ) ) {
			return '';
		}
		global $wpdb;
		if ( '' !== $posted_role ) {
			$grants_admin = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT admin_access FROM ec_role WHERE role_label = %s', $posted_role ) );
			if ( $grants_admin ) {
				return __( 'Only site administrators can assign admin-level roles.', 'wp-easycart' );
			}
		}
		if ( $user_id > 0 ) {
			$current_admin = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT r.admin_access FROM ec_user u INNER JOIN ec_role r ON r.role_label = u.user_level WHERE u.user_id = %d', $user_id ) );
			if ( $current_admin ) {
				return __( 'Only site administrators can modify admin-level accounts.', 'wp-easycart' );
			}
		}
		return '';
	}
}

/**
 * Holistic save: posts the full details form and delegates to the legacy
 * insert_user() / update_user() handlers (which verify the form's
 * wp-easycart-user-details nonce and own every side effect). Returns JSON.
 */
add_action( 'wp_ajax_ecudv2_user_save', 'ecudv2_user_save' );
function ecudv2_user_save() {
	if ( ! ecudv2_can_manage() ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
	}
	/* Same nonce insert_user() / update_user() verify later; check it before reading any form data. */
	if ( ! isset( $_POST['wp_easycart_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_easycart_nonce'] ) ), 'wp-easycart-user-details' ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed. Please reload and try again.', 'wp-easycart' ) ) );
	}

	/* The V2 form posts the mode as ecudv2_form_action ( see users-details-v2.js ); accept the legacy key too. */
	$mode = isset( $_POST['ecudv2_form_action'] ) ? sanitize_key( wp_unslash( $_POST['ecudv2_form_action'] ) ) : ( isset( $_POST['ec_admin_form_action'] ) ? sanitize_key( wp_unslash( $_POST['ec_admin_form_action'] ) ) : '' );
	$is_new = ( 'add-new-user' === $mode );

	$user_id = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;
	$posted_role = isset( $_POST['user_level'] ) ? sanitize_text_field( wp_unslash( $_POST['user_level'] ) ) : '';
	$password_generated = false;

	if ( $is_new ) {
		global $wpdb;
		$first_name = isset( $_POST['first_name'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) ) : '';
		$last_name = isset( $_POST['last_name'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) ) : '';
		$raw_email = isset( $_POST['email'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['email'] ) ) ) : '';
		if ( '' === $first_name ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a first name.', 'wp-easycart' ), 'field' => 'first_name' ) );
		}
		if ( '' === $last_name ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a last name.', 'wp-easycart' ), 'field' => 'last_name' ) );
		}
		if ( '' === $raw_email || ! is_email( $raw_email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'wp-easycart' ), 'field' => 'email' ) );
		}
		if ( (int) $wpdb->get_var( $wpdb->prepare( 'SELECT user_id FROM ec_user WHERE email = %s LIMIT 1', $raw_email ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Another account already uses this email address.', 'wp-easycart' ), 'field' => 'email' ) );
		}

		/* New customers default to the shopper role. */
		if ( '' === $posted_role ) {
			$posted_role = 'shopper';
			$_POST['user_level'] = 'shopper';
		}
		if ( 'shopper' !== $posted_role && ! $wpdb->get_var( $wpdb->prepare( 'SELECT role_label FROM ec_role WHERE role_label = %s', $posted_role ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Please select a user access level.', 'wp-easycart' ), 'field' => 'user_level' ) );
		}

		/* The password is optional on create ( its panel unlocks after saving ): never store a hash of an empty string. */
		$raw_password = isset( $_POST['password'] ) ? wp_unslash( $_POST['password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passwords are hashed, never sanitized.
		if ( '' === $raw_password ) {
			/* No slashes or quotes in the generated set, so the legacy handler's wp_unslash() leaves it intact. */
			$generated = wp_generate_password( 24, true, false );
			$_POST['password'] = $generated;
			$_POST['retype_password'] = $generated;
			$password_generated = true;
		} elseif ( strlen( $raw_password ) < 8 ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a password 8 characters or greater.', 'wp-easycart' ), 'field' => 'password' ) );
		}
	}

	$guard = ecudv2_role_guard( $user_id, $posted_role );
	if ( '' !== $guard ) {
		wp_send_json_error( array( 'message' => $guard, 'field' => 'user_level' ) );
	}

	$users = wp_easycart_admin_users();

	if ( $is_new ) {
		$result = $users->insert_user();
	} else {
		$result = $users->update_user();
	}

	if ( false === $result ) {
		/* insert_user() / update_user() return false for a missing capability as well as a bad nonce. */
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_manager' ) ) {
			wp_send_json_error( array( 'message' => __( 'Your account does not have permission to create or edit customers.', 'wp-easycart' ) ) );
		}
		wp_send_json_error( array( 'message' => __( 'Security check failed. Please reload and try again.', 'wp-easycart' ) ) );
	}
	if ( isset( $result['error'] ) ) {
		if ( 'user-duplicate' === $result['error'] ) {
			wp_send_json_error( array( 'message' => __( 'Another account already uses this email address.', 'wp-easycart' ), 'field' => 'email' ) );
		}
		wp_send_json_error( array( 'message' => __( 'Save failed. Please review the form and try again.', 'wp-easycart' ), 'code' => $result['error'] ) );
	}

	if ( $is_new ) {
		global $wpdb;
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$new_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT user_id FROM ec_user WHERE email = %s ORDER BY user_id DESC LIMIT 1', $email ) );
		/* 'account_created' is logged by ec_user_activity on the wpeasycart_account_added action insert_user() fires. */
		wp_send_json_success( array( 'user_id' => $new_id, 'created' => true, 'password_generated' => $password_generated ) );
	}

	wp_send_json_success( array(
		'user_id' => $user_id,
		'message' => __( 'Customer saved.', 'wp-easycart' ),
	) );
}

/**
 * Debounced email-uniqueness check for the identity card.
 */
add_action( 'wp_ajax_ecudv2_email_exists', 'ecudv2_email_exists' );
function ecudv2_email_exists() {
	if ( ! ecudv2_can_manage() ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
	}
	if ( ! isset( $_POST['wp_easycart_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_easycart_nonce'] ) ), 'wp-easycart-ecudv2-utility' ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wp-easycart' ) ) );
	}
	global $wpdb;
	$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$user_id = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;
	if ( '' === $email || ! is_email( $email ) ) {
		wp_send_json_success( array( 'exists' => false, 'valid' => false ) );
	}
	$duplicate = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT user_id FROM ec_user WHERE email = %s AND user_id != %d LIMIT 1', $email, $user_id ) );
	wp_send_json_success( array( 'exists' => $duplicate > 0, 'valid' => true ) );
}

/**
 * One-click approve: pending → shopper (per product decision, no prompt).
 */
add_action( 'wp_ajax_ecudv2_user_approve', 'ecudv2_user_approve' );
function ecudv2_user_approve() {
	if ( ! ecudv2_can_manage() ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
	}
	if ( ! isset( $_POST['wp_easycart_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_easycart_nonce'] ) ), 'wp-easycart-ecudv2-utility' ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wp-easycart' ) ) );
	}
	global $wpdb;
	$user_id = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;
	$current = $wpdb->get_var( $wpdb->prepare( 'SELECT user_level FROM ec_user WHERE user_id = %d', $user_id ) );
	if ( 'pending' !== $current ) {
		wp_send_json_error( array( 'message' => __( 'This account is not pending activation.', 'wp-easycart' ) ) );
	}
	$wpdb->update( 'ec_user', array( 'user_level' => 'shopper' ), array( 'user_id' => $user_id ), array( '%s' ), array( '%d' ) );
	if ( function_exists( 'wp_easycart_log_user_activity' ) ) {
		wp_easycart_log_user_activity( $user_id, 'account_approved', array( 'actor_type' => 'admin' ) );
	}
	do_action( 'wpeasycart_account_updated', $user_id );
	wp_cache_delete( 'wpeasycart-user-' . $user_id, 'wpeasycart-user' );
	wp_cache_flush();
	wp_send_json_success( array( 'message' => __( 'Account approved. The customer can now sign in as a shopper.', 'wp-easycart' ) ) );
}