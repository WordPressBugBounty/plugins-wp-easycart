<?php
/**
 * Customer (User) Details Page — V2.
 *
 * ecdv2 chassis: sticky header, grouped tab rail, card panels, dirty
 * tracking, holistic AJAX save (delegating to legacy insert/update).
 * Rendered by wp_easycart_admin_details_user_v2::output(); behaviors in
 * users-details-v2.js (ecudv2 namespace); shared styling from
 * admin-details-v2.css plus admin-users-details-v2.css.
 *
 * PRO extension points (all fire whether or not PRO is present):
 *  - action wp_easycart_admin_user_details_v2_header_meta ( $user )       — lifecycle badge + tag chips
 *  - action wp_easycart_admin_user_details_v2_menu ( $user )              — extra ⋯ menu items
 *  - action wp_easycart_admin_user_details_v2_general_meta ( $user )      — account meta chips
 *  - action wp_easycart_admin_user_details_v2_addresses_pro ( $details )  — address book card
 *  - action wp_easycart_admin_user_details_v2_prefs_pro ( $details )      — tax context card
 *  - action wp_easycart_admin_user_details_v2_notes_pro ( $details )      — notes timeline card
 *  - action wp_easycart_admin_user_details_v2_activity ( $details )       — live Activity panel (PRO)
 *  - action wp_easycart_admin_user_details_v2_advanced_pro ( $details )   — anonymize + merge cards
 *  - filter wp_easycart_admin_user_details_v2_tabs / _tab_groups
 *  - filter wp_easycart_admin_user_details_v2_delete_warning ( $extra, $user )
 *
 * @since 5.9.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_new = $this->is_new;
$user = $this->user;
$full_name = trim( wp_unslash( $user->first_name . ' ' . $user->last_name ) );
$is_pending = ( ! $is_new && 'pending' === $user->user_level );
$wp_user = $is_new ? false : $this->get_wp_user();
$roles = $this->get_roles();
$is_site_admin = current_user_can( 'manage_options' );
$has_pro_activity = has_action( 'wp_easycart_admin_user_details_v2_activity' );

$tabs = apply_filters( 'wp_easycart_admin_user_details_v2_tabs', array(
	'general'   => array( 'label' => __( 'General', 'wp-easycart' ), 'icon' => 'dashicons-admin-users', 'group' => 'profile', 'desc' => __( 'The account essentials: name, email, role, and newsletter status.', 'wp-easycart' ) ),
	'security'  => array( 'label' => __( 'Password & Security', 'wp-easycart' ), 'icon' => 'dashicons-lock', 'group' => 'profile', 'desc' => __( 'Password management, reset links, and sign-in status.', 'wp-easycart' ) ),
	'notes'     => array( 'label' => __( 'Notes', 'wp-easycart' ), 'icon' => 'dashicons-edit-page', 'group' => 'profile', 'desc' => __( 'Internal notes about this customer. Never shown to the customer.', 'wp-easycart' ) ),
	'addresses' => array( 'label' => __( 'Addresses', 'wp-easycart' ), 'icon' => 'dashicons-location', 'group' => 'commerce', 'desc' => __( 'Default billing and shipping addresses used at checkout.', 'wp-easycart' ) ),
	'prefs'     => array( 'label' => __( 'Tax & Preferences', 'wp-easycart' ), 'icon' => 'dashicons-money-alt', 'group' => 'commerce', 'desc' => __( 'Tax and shipping exemptions, VAT registration, and account flags.', 'wp-easycart' ) ),
	'activity'  => array( 'label' => __( 'Activity', 'wp-easycart' ), 'icon' => 'dashicons-chart-bar', 'group' => 'insights', 'new' => true, 'desc' => __( 'Orders, spend, subscriptions, downloads, and the customer timeline.', 'wp-easycart' ) ),
	'advanced'  => array( 'label' => __( 'Advanced', 'wp-easycart' ), 'icon' => 'dashicons-admin-generic', 'group' => 'advanced', 'desc' => __( 'Data export, account tools, and the danger zone.', 'wp-easycart' ) ),
), $user );

$tab_groups = apply_filters( 'wp_easycart_admin_user_details_v2_tab_groups', array(
	'profile'  => __( 'Profile', 'wp-easycart' ),
	'commerce' => __( 'Commerce', 'wp-easycart' ),
	'insights' => __( 'Insights', 'wp-easycart' ),
	'advanced' => __( 'Advanced', 'wp-easycart' ),
) );

$delete_warning = apply_filters( 'wp_easycart_admin_user_details_v2_delete_warning', '', $user );
?>

<form action="<?php echo esc_attr( $this->action ); ?>" method="POST" id="wpeasycart_admin_form" name="wpeasycart_admin_form" novalidate="novalidate" onsubmit="return false;">
<?php wp_easycart_admin_verification()->print_nonce_field( 'wp_easycart_nonce', 'wp-easycart-user-details' ); ?>
<input type="hidden" name="ec_admin_form_action" value="<?php echo esc_attr( $is_new ? 'add-new-user' : 'update-user' ); ?>" />
<input type="hidden" name="user_id" id="ecudv2_user_id" value="<?php echo esc_attr( $is_new ? '0' : $user->user_id ); ?>" />
<input type="hidden" name="default_billing_address_id" value="<?php echo esc_attr( (int) $user->default_billing_address_id ); ?>" />
<input type="hidden" name="default_shipping_address_id" value="<?php echo esc_attr( (int) $user->default_shipping_address_id ); ?>" />
<input type="hidden" id="ecudv2_utility_nonce" value="<?php echo esc_attr( wp_create_nonce( 'wp-easycart-ecudv2-utility' ) ); ?>" />

<div class="ecdv2-wrap ecudv2-wrap<?php echo $is_new ? ' ecdv2-is-new' : ''; ?>" id="ecdv2_wrap">

	<!-- ============ Sticky header ============ -->
	<div class="ecdv2-header">
		<a href="<?php echo esc_attr( $this->action ); ?>" class="ecdv2-header-back" title="<?php esc_attr_e( 'Back to Customers', 'wp-easycart' ); ?>"><span class="dashicons dashicons-arrow-left-alt2"></span></a>
		<div class="ecdv2-header-thumb ecudv2-header-thumb"><?php $this->print_avatar( 'ecv2-user-avatar-lg' ); ?></div>
		<div class="ecdv2-header-meta">
			<p class="ecdv2-header-title" id="ecudv2_header_title"><?php echo $is_new ? esc_html__( 'New Customer', 'wp-easycart' ) : ( '' !== $full_name ? esc_html( $full_name ) : esc_html__( '(no name)', 'wp-easycart' ) ); ?></p>
			<div class="ecdv2-header-sub">
				<?php if ( ! $is_new ) { ?>
					<span id="ecudv2_header_email"><?php echo esc_html( $user->email ); ?></span>
					&middot; <span class="ecv2-role-badge ecv2-role-custom" id="ecudv2_header_role"><?php echo esc_html( $user->user_level ); ?></span>
					&middot; <span class="ecudv2-header-id">#<?php echo (int) $user->user_id; ?></span>
					<?php do_action( 'wp_easycart_admin_user_details_v2_header_meta', $user ); ?>
				<?php } else { ?>
					<?php esc_attr_e( 'Fill in the essentials, then save to unlock all sections', 'wp-easycart' ); ?>
				<?php } ?>
			</div>
		</div>

		<?php if ( ! $is_new ) { ?>
			<?php if ( $is_pending ) { ?>
				<div class="ecudv2-state ecudv2-state-pending" id="ecudv2_state">
					<span class="ecdv2-status-pill"><?php esc_html_e( 'Pending', 'wp-easycart' ); ?></span>
					<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" onclick="ecudv2.approve( this );"><?php esc_html_e( 'Approve Account', 'wp-easycart' ); ?></button>
				</div>
			<?php } else { ?>
				<div class="ecudv2-state" id="ecudv2_state"><span class="ecdv2-status-pill is-active"><?php esc_html_e( 'Active', 'wp-easycart' ); ?></span></div>
			<?php } ?>
		<?php } ?>

		<div class="ecdv2-header-spacer"></div>
		<span class="ecdv2-dirty-pill" id="ecdv2_dirty_pill"><?php esc_attr_e( 'Unsaved changes', 'wp-easycart' ); ?></span>

		<div class="ecdv2-header-actions">
			<?php if ( ! $is_new ) { ?>
				<a href="admin.php?page=wp-easycart-orders&subpage=orders&filter_5=<?php echo (int) $user->user_id; ?>" class="ecv2-btn"><span class="dashicons dashicons-cart" style="font-size:14px;width:14px;height:14px;margin-top:3px;"></span> <?php esc_attr_e( 'View Orders', 'wp-easycart' ); ?></a>
				<a href="<?php echo esc_url( $this->single_action_url( 'user-login-override', 'wp-easycart-action-login-as-user' ) ); ?>" class="ecv2-btn"><span class="dashicons dashicons-migrate" style="font-size:14px;width:14px;height:14px;margin-top:3px;"></span> <?php esc_attr_e( 'Login as Customer', 'wp-easycart' ); ?></a>

				<div class="ecdv2-header-menu-wrap">
					<button type="button" class="ecv2-btn" onclick="ecudv2.menu_toggle( this );" aria-label="<?php esc_attr_e( 'More actions', 'wp-easycart' ); ?>"><span class="dashicons dashicons-ellipsis" style="font-size:14px;width:14px;height:14px;margin-top:3px;"></span></button>
					<div class="ecudv2-header-menu" id="ecudv2_header_menu">
						<a href="#" onclick="ecudv2.password_reset( '<?php echo esc_url( $this->bulk_action_url( 'accounts-force-password-reset' ) ); ?>' ); return false;"><span class="dashicons dashicons-lock"></span> <?php esc_html_e( 'Send Password Reset', 'wp-easycart' ); ?></a>
						<?php if ( $is_pending ) { ?>
							<a href="<?php echo esc_url( $this->single_action_url( 'user-resend-activation', 'wp-easycart-action-resend-activation' ) ); ?>"><span class="dashicons dashicons-email-alt"></span> <?php esc_html_e( 'Resend Activation Email', 'wp-easycart' ); ?></a>
						<?php } ?>
						<a href="<?php echo esc_url( $this->bulk_action_url( 'export-accounts-csv' ) ); ?>"><span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Export Customer CSV', 'wp-easycart' ); ?></a>
						<?php do_action( 'wp_easycart_admin_user_details_v2_menu', $user ); ?>
						<a href="#" class="ecudv2-menu-danger" onclick="ecudv2.delete_account( '<?php echo esc_url( $this->single_action_url( 'delete-account', 'wp-easycart-action-delete-account' ) ); ?>', this ); return false;" data-extra-warning="<?php echo esc_attr( $delete_warning ); ?>"><span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Delete Account', 'wp-easycart' ); ?></a>
					</div>
				</div>
			<?php } else { ?>
				<a href="<?php echo esc_attr( $this->action ); ?>" class="ecv2-btn"><?php esc_attr_e( 'Cancel', 'wp-easycart' ); ?></a>
			<?php } ?>
			<button type="button" class="ecv2-btn ecv2-btn-primary ecdv2-save-btn" id="ecdv2_save_btn" onclick="ecudv2.save_all();">
				<span class="ecdv2-save-label"><?php echo $is_new ? esc_html__( 'Create Customer', 'wp-easycart' ) : esc_html__( 'Save', 'wp-easycart' ); ?></span>
				<span class="dashicons dashicons-update ecdv2-save-spinner"></span>
			</button>
		</div>
	</div>

	<!-- ============ Body: rail + panels ============ -->
	<div class="ecdv2-body">

		<div class="ecdv2-rail">
			<div class="ecdv2-tabs" id="ecdv2_tabs" role="tablist">
				<?php $ecudv2_current_group = null; ?>
				<?php foreach ( $tabs as $tab_key => $tab ) { ?>
					<?php
					$ecudv2_group = isset( $tab['group'] ) ? $tab['group'] : '';
					if ( $ecudv2_group !== $ecudv2_current_group && isset( $tab_groups[ $ecudv2_group ] ) ) {
						$ecudv2_current_group = $ecudv2_group;
						echo '<div class="ecdv2-rail-group">' . esc_html( $tab_groups[ $ecudv2_group ] ) . '</div>';
					}
					?>
					<button type="button" role="tab" class="ecdv2-tab<?php echo ( 'general' === $tab_key ) ? ' is-active' : ''; ?><?php echo ( 'general' !== $tab_key ) ? ' ecdv2-requires-save' : ''; ?>" data-ecdv2-tab="<?php echo esc_attr( $tab_key ); ?>" onclick="ecudv2.go_tab( '<?php echo esc_attr( $tab_key ); ?>' );">
						<span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>"></span>
						<span><?php echo esc_html( $tab['label'] ); ?></span>
						<?php if ( ! empty( $tab['new'] ) ) { ?><span class="ecdv2-tab-new"><?php esc_attr_e( 'NEW', 'wp-easycart' ); ?></span><?php } ?>
						<span class="ecdv2-tab-dirty-dot"></span>
					</button>
				<?php } ?>
			</div>
		</div>

		<div class="ecdv2-panel-col">
			<?php
			$ecudv2_intro = function( $key ) use ( $tabs ) {
				if ( empty( $tabs[ $key ]['desc'] ) ) { return; }
				echo '<div class="ecdv2-panel-intro"><span class="dashicons ' . esc_attr( $tabs[ $key ]['icon'] ) . '"></span><span>' . esc_html( $tabs[ $key ]['desc'] ) . '</span></div>';
			};
			?>

			<!-- ============ General ============ -->
			<div class="ecdv2-panel is-active" data-ecdv2-panel="general" role="tabpanel">
				<?php $ecudv2_intro( 'general' ); ?>

				<?php $this->section_open( 'basic', __( 'Identity', 'wp-easycart' ), __( 'Name and sign-in email', 'wp-easycart' ) ); ?>
					<div class="ecudv2-field-grid">
						<?php $this->text_field( 'first_name', __( 'First Name', 'wp-easycart' ), wp_unslash( $user->first_name ), 'basic', array( 'required' => true, 'half' => true, 'oninput' => 'ecudv2.sync_header();' ) ); ?>
						<?php $this->text_field( 'last_name', __( 'Last Name', 'wp-easycart' ), wp_unslash( $user->last_name ), 'basic', array( 'required' => true, 'half' => true, 'oninput' => 'ecudv2.sync_header();' ) ); ?>
						<?php $this->text_field( 'email', __( 'Email Address', 'wp-easycart' ), $user->email, 'basic', array( 'required' => true, 'type' => 'email', 'oninput' => 'ecudv2.email_changed( this );', 'autocomplete' => 'off' ) ); ?>
						<?php $this->text_field( 'email_other', __( 'Alternate Email (CC on receipts)', 'wp-easycart' ), $user->email_other, 'basic', array( 'type' => 'email', 'hint' => __( 'Optional second address copied on order receipts.', 'wp-easycart' ) ) ); ?>
					</div>
				<?php $this->section_close(); ?>

				<?php $this->section_open( 'access', __( 'Account Access', 'wp-easycart' ), __( 'Role and account linkage', 'wp-easycart' ) ); ?>
					<div class="ecudv2-field">
						<label for="ecudv2_user_level"><?php esc_html_e( 'User Access Level', 'wp-easycart' ); ?> <span class="ecudv2-req">*</span></label>
						<?php
						/* New customers always start as shoppers; the placeholder only exists for legacy rows whose role is missing. */
						$ecudv2_level = ( $is_new && '' === (string) $user->user_level ) ? 'shopper' : (string) $user->user_level;
						?>
						<select name="user_level" id="ecudv2_user_level" data-ecdv2-sec="access">
							<?php if ( ! $is_new ) { ?>
								<option value=""><?php esc_html_e( 'Select a User Access Level', 'wp-easycart' ); ?></option>
							<?php } ?>
							<?php foreach ( $roles as $role ) { ?>
								<?php if ( (int) $role->admin_access && ! $is_site_admin && $ecudv2_level !== $role->role_label ) { continue; } ?>
								<option value="<?php echo esc_attr( $role->role_label ); ?>"<?php selected( $ecudv2_level, $role->role_label ); ?>><?php echo esc_html( $role->role_label ); ?><?php echo (int) $role->admin_access ? esc_html( ' — ' . __( 'admin access', 'wp-easycart' ) ) : ''; ?></option>
							<?php } ?>
						</select>
						<span class="ecudv2-field-error" data-ecudv2-error="user_level"></span>
					</div>

					<?php $this->toggle_field( 'is_subscriber', __( 'Newsletter subscriber', 'wp-easycart' ), (int) $user->is_subscriber, 'access', __( 'Adds or removes this email from the EasyCart subscriber list on save.', 'wp-easycart' ) ); ?>

					<?php if ( ! $is_new ) { ?>
						<div class="ecudv2-readout-grid">
							<div class="ecudv2-readout">
								<span class="ecudv2-readout-label"><?php esc_html_e( 'WordPress Account', 'wp-easycart' ); ?></span>
								<?php if ( $wp_user ) { ?>
									<a href="<?php echo esc_url( get_edit_user_link( $wp_user->ID ) ); ?>" target="_blank"><?php echo esc_html( $wp_user->user_login ); ?> (#<?php echo (int) $wp_user->ID; ?>)</a>
								<?php } else { ?>
									<span class="ecv2-date-empty"><?php esc_html_e( 'No linked WordPress account', 'wp-easycart' ); ?></span>
								<?php } ?>
							</div>
							<div class="ecudv2-readout">
								<span class="ecudv2-readout-label"><?php esc_html_e( 'Registered', 'wp-easycart' ); ?></span>
								<?php echo wp_kses_post( $this->nullable_date( isset( $user->date_created ) ? $user->date_created : null, __( 'Before tracking', 'wp-easycart' ) ) ); ?>
							</div>
							<div class="ecudv2-readout">
								<span class="ecudv2-readout-label"><?php esc_html_e( 'Last Login', 'wp-easycart' ); ?></span>
								<?php echo wp_kses_post( $this->nullable_date( isset( $user->last_login ) ? $user->last_login : null, '—' ) ); ?>
							</div>
						</div>
						<?php do_action( 'wp_easycart_admin_user_details_v2_general_meta', $user ); ?>
					<?php } ?>
				<?php $this->section_close(); ?>
			</div>

			<!-- ============ Password & Security ============ -->
			<div class="ecdv2-panel ecdv2-requires-save" data-ecdv2-panel="security" role="tabpanel">
				<?php $ecudv2_intro( 'security' ); ?>

				<?php $this->section_open( 'security', __( 'Password', 'wp-easycart' ), $is_new ? __( 'Required to create the account', 'wp-easycart' ) : __( 'Only change when the customer requests it', 'wp-easycart' ) ); ?>
					<?php if ( ! $is_new ) { ?>
						<?php $this->toggle_field( 'update_password', __( 'Set a new password', 'wp-easycart' ), false, 'security', __( 'Leave off to keep the current password.', 'wp-easycart' ) ); ?>
					<?php } ?>
					<div class="ecudv2-password-fields<?php echo $is_new ? ' is-open' : ''; ?>" id="ecudv2_password_fields">
						<div class="ecudv2-field-grid">
							<div class="ecudv2-field ecudv2-field-half">
								<label for="ecudv2_password"><?php esc_html_e( 'Password', 'wp-easycart' ); ?> <?php if ( $is_new ) { ?><span class="ecudv2-req">*</span><?php } ?></label>
								<div class="ecudv2-password-wrap">
									<input type="password" id="ecudv2_password" name="password" value="" data-ecdv2-sec="<?php echo $is_new ? 'basic' : 'security'; ?>" autocomplete="new-password" oninput="ecudv2.password_strength( this );" />
									<button type="button" class="ecudv2-password-eye" onclick="ecudv2.password_reveal( this );" title="<?php esc_attr_e( 'Show password', 'wp-easycart' ); ?>"><span class="dashicons dashicons-visibility"></span></button>
								</div>
								<div class="ecudv2-strength" id="ecudv2_strength"><span></span></div>
								<span class="ecudv2-field-error" data-ecudv2-error="password"></span>
							</div>
							<div class="ecudv2-field ecudv2-field-half">
								<label for="ecudv2_retype_password"><?php esc_html_e( 'Retype Password', 'wp-easycart' ); ?></label>
								<input type="password" id="ecudv2_retype_password" name="retype_password" value="" data-ecdv2-sec="<?php echo $is_new ? 'basic' : 'security'; ?>" autocomplete="new-password" />
								<span class="ecudv2-field-error" data-ecudv2-error="retype_password"></span>
							</div>
						</div>
						<button type="button" class="ecv2-btn ecv2-btn-sm" onclick="ecudv2.password_generate();"><span class="dashicons dashicons-randomize" style="font-size:13px;width:13px;height:13px;margin-top:4px;"></span> <?php esc_html_e( 'Generate Strong Password', 'wp-easycart' ); ?></button>
					</div>
				<?php $this->section_close(); ?>

				<?php if ( ! $is_new ) { ?>
					<?php $this->section_open( 'security_tools', __( 'Sign-in & Recovery', 'wp-easycart' ), __( 'The safer alternative to typing a password for someone', 'wp-easycart' ) ); ?>
						<div class="ecudv2-readout-grid">
							<div class="ecudv2-readout">
								<span class="ecudv2-readout-label"><?php esc_html_e( 'Last Login', 'wp-easycart' ); ?></span>
								<?php echo wp_kses_post( $this->nullable_date( isset( $user->last_login ) ? $user->last_login : null, '—' ) ); ?>
							</div>
							<div class="ecudv2-readout">
								<span class="ecudv2-readout-label"><?php esc_html_e( 'Activation', 'wp-easycart' ); ?></span>
								<span id="ecudv2_activation_readout"><?php echo $is_pending ? esc_html__( 'Pending — activation email sent', 'wp-easycart' ) : esc_html__( 'Activated', 'wp-easycart' ); ?></span>
							</div>
							<?php if ( $this->has_legacy_hash_backup() ) { ?>
								<div class="ecudv2-readout">
									<span class="ecudv2-readout-label"><?php esc_html_e( 'Password Storage', 'wp-easycart' ); ?></span>
									<span class="ecudv2-legacy-hash" title="<?php esc_attr_e( 'A legacy-format password backup exists for this account. Sending a password reset upgrades it to the modern format.', 'wp-easycart' ); ?>"><span class="dashicons dashicons-warning"></span> <?php esc_html_e( 'Legacy backup present', 'wp-easycart' ); ?></span>
								</div>
							<?php } ?>
						</div>
						<button type="button" class="ecv2-btn" onclick="ecudv2.password_reset( '<?php echo esc_url( $this->bulk_action_url( 'accounts-force-password-reset' ) ); ?>' );"><span class="dashicons dashicons-lock" style="font-size:13px;width:13px;height:13px;margin-top:4px;"></span> <?php esc_html_e( 'Send Password Reset Email', 'wp-easycart' ); ?></button>
						<p class="ecudv2-field-hint"><?php esc_html_e( 'Immediately invalidates the current password and emails the customer a reset link.', 'wp-easycart' ); ?></p>
					<?php $this->section_close(); ?>
				<?php } ?>
			</div>

			<!-- ============ Notes ============ -->
			<div class="ecdv2-panel ecdv2-requires-save" data-ecdv2-panel="notes" role="tabpanel">
				<?php $ecudv2_intro( 'notes' ); ?>
				<?php do_action( 'wp_easycart_admin_user_details_v2_notes_pro', $this ); ?>
				<?php $this->section_open( 'notes', __( 'Admin Notes', 'wp-easycart' ), __( 'Internal only — customers never see this', 'wp-easycart' ) ); ?>
					<textarea name="user_notes" id="ecudv2_user_notes" rows="6" data-ecdv2-sec="notes" class="ecudv2-textarea"><?php echo esc_textarea( wp_unslash( (string) $user->user_notes ) ); ?></textarea>
				<?php $this->section_close(); ?>
			</div>

			<!-- ============ Addresses ============ -->
			<div class="ecdv2-panel ecdv2-requires-save" data-ecdv2-panel="addresses" role="tabpanel">
				<?php $ecudv2_intro( 'addresses' ); ?>
				<div class="ecudv2-address-cols">
					<?php
					/* 6.0.0: Copy to Shipping sits in the card header instead of floating beside the name fields. */
					$this->section_open(
						'billing',
						__( 'Billing Address', 'wp-easycart' ),
						'',
						function () {
							?>
							<button type="button" class="ecv2-btn ecv2-btn-sm ecudv2-copy-btn" onclick="ecudv2.copy_to_shipping();"><span class="dashicons dashicons-arrow-right-alt"></span> <?php esc_html_e( 'Copy to Shipping', 'wp-easycart' ); ?></button>
							<?php
						}
					);
					?>
						<?php $this->address_fields( 'billing', $this->billing_info, 'billing' ); ?>
					<?php $this->section_close(); ?>
					<?php $this->section_open( 'shipping', __( 'Shipping Address', 'wp-easycart' ), '' ); ?>
						<?php $this->address_fields( 'shipping', $this->shipping_info, 'shipping' ); ?>
					<?php $this->section_close(); ?>
				</div>
				<?php do_action( 'wp_easycart_admin_user_details_v2_addresses_pro', $this ); ?>
				<?php $this->print_country_datalist(); ?>
			</div>

			<!-- ============ Tax & Preferences ============ -->
			<div class="ecdv2-panel ecdv2-requires-save" data-ecdv2-panel="prefs" role="tabpanel">
				<?php $ecudv2_intro( 'prefs' ); ?>
				<?php $this->section_open( 'prefs', __( 'Tax & Shipping Preferences', 'wp-easycart' ), __( 'Per-account overrides applied at checkout', 'wp-easycart' ) ); ?>
					<?php $this->toggle_field( 'exclude_tax', __( 'Exclude from tax', 'wp-easycart' ), (int) $user->exclude_tax, 'prefs', __( 'No tax is charged on this customer\'s orders.', 'wp-easycart' ) ); ?>
					<?php $this->toggle_field( 'exclude_shipping', __( 'Exclude from shipping charges', 'wp-easycart' ), (int) $user->exclude_shipping, 'prefs', __( 'Shipping is free for this customer.', 'wp-easycart' ) ); ?>
					<?php $this->toggle_field( 'allow_shipping_bypass', __( 'Bypass Disabled Shipping Address', 'wp-easycart' ), isset( $user->allow_shipping_bypass ) ? (int) $user->allow_shipping_bypass : 0, 'prefs', __( 'Lets this customer check out with a shipping address, even if your store is setup to not allow it to differ from the billing address.', 'wp-easycart' ) ); ?>
					<?php $this->toggle_field( 'is_stripe_test_user', __( 'Stripe test-mode customer', 'wp-easycart' ), isset( $user->is_stripe_test_user ) ? (int) $user->is_stripe_test_user : 0, 'prefs', __( 'This customer\'s saved payment data belongs to Stripe test mode.', 'wp-easycart' ) ); ?>
					<div class="ecudv2-field" style="margin-top:12px;">
						<label for="ecudv2_vat_registration_number"><?php esc_html_e( 'VAT Registration Number', 'wp-easycart' ); ?></label>
						<input type="text" id="ecudv2_vat_registration_number" name="vat_registration_number" value="<?php echo esc_attr( $user->vat_registration_number ); ?>" data-ecdv2-sec="prefs" />
					</div>
				<?php $this->section_close(); ?>
				<?php do_action( 'wp_easycart_admin_user_details_v2_prefs_pro', $this ); ?>
			</div>

			<!-- ============ Activity ( Insights ) ============ -->
			<div class="ecdv2-panel ecdv2-requires-save" data-ecdv2-panel="activity" role="tabpanel">
				<?php $ecudv2_intro( 'activity' ); ?>
				<?php
				if ( $has_pro_activity ) {
					// PRO v2 renders the live Activity panel.
					do_action( 'wp_easycart_admin_user_details_v2_activity', $this );
				} else if ( ! $is_new ) {
					/*
					 * FREE: the blurred snapshot mockup + gate (or a legacy
					 * PRO overview via the existing filter) — identical
					 * treatment to the v1 screen, now living in its own tab.
					 */
					$this->print_customer_overview();
				}
				?>
			</div>

			<!-- ============ Advanced ============ -->
			<div class="ecdv2-panel ecdv2-requires-save" data-ecdv2-panel="advanced" role="tabpanel">
				<?php $ecudv2_intro( 'advanced' ); ?>

				<?php if ( ! $is_new ) { ?>
					<?php $this->section_open( 'export', __( 'Data Export', 'wp-easycart' ), __( 'Handy for data requests', 'wp-easycart' ) ); ?>
						<p class="ecudv2-field-hint" style="margin-top:0;"><?php esc_html_e( 'Download this customer\'s profile as a CSV using the standard accounts exporter.', 'wp-easycart' ); ?></p>
						<a href="<?php echo esc_url( $this->bulk_action_url( 'export-accounts-csv' ) ); ?>" class="ecv2-btn"><span class="dashicons dashicons-download" style="font-size:13px;width:13px;height:13px;margin-top:4px;"></span> <?php esc_html_e( 'Export Customer CSV', 'wp-easycart' ); ?></a>
					<?php $this->section_close(); ?>

					<?php do_action( 'wp_easycart_admin_user_details_v2_advanced_pro', $this ); ?>

					<?php $this->section_open( 'danger', __( 'Danger Zone', 'wp-easycart' ), __( 'These actions cannot be undone', 'wp-easycart' ) ); ?>
						<div class="ecudv2-danger-row">
							<div class="ecudv2-danger-text">
								<strong><?php esc_html_e( 'Delete this customer account', 'wp-easycart' ); ?></strong>
								<span class="ecudv2-field-hint"><?php esc_html_e( 'Removes the profile and addresses. Orders are kept for your records.', 'wp-easycart' ); ?></span>
							</div>
							<button type="button" class="ecv2-btn ecudv2-btn-danger" onclick="ecudv2.delete_account( '<?php echo esc_url( $this->single_action_url( 'delete-account', 'wp-easycart-action-delete-account' ) ); ?>', this );" data-extra-warning="<?php echo esc_attr( $delete_warning ); ?>"><?php esc_html_e( 'Delete Account', 'wp-easycart' ); ?></button>
						</div>
					<?php $this->section_close(); ?>

					<?php $this->section_open( 'hooks', __( 'Developer Hooks', 'wp-easycart' ), __( 'For extension authors', 'wp-easycart' ) ); ?>
						<details class="ecudv2-hooks">
							<summary><?php esc_html_e( 'Actions & filters available on this screen', 'wp-easycart' ); ?></summary>
							<code>wp_easycart_admin_user_details_v2_tabs</code>, <code>..._tab_groups</code>, <code>..._header_meta</code>, <code>..._menu</code>, <code>..._general_meta</code>, <code>..._addresses_pro</code>, <code>..._prefs_pro</code>, <code>..._notes_pro</code>, <code>..._activity</code>, <code>..._advanced_pro</code>, <code>..._delete_warning</code>, <code>wp_easycart_admin_user_details_loaded</code>, <code>wpeasycart_account_updated</code>
						</details>
					<?php $this->section_close(); ?>
				<?php } ?>
			</div>

		</div><!-- /panel-col -->
	</div><!-- /body -->

	<div id="ecv2-toast-container" class="ecv2-toast-container"></div>
</div>
</form>