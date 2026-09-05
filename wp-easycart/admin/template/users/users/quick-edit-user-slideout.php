<?php
/**
 * Quick Edit Customer Slideout - V2 user list.
 *
 * Rendered by wp_easycart_admin_user_table::print_custom_modals(). Populated
 * and saved via the ecv2_user_quick_get / ecv2_user_quick_save AJAX endpoints
 * in users-v2.js.
 *
 * @since 5.9.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$ecv2_qe_roles = $wpdb->get_results( 'SELECT role_label, admin_access FROM ec_role ORDER BY role_id ASC' );
$ecv2_qe_is_site_admin = current_user_can( 'manage_options' );
?>
<div class="ecv2-slideout-overlay" id="ecv2-user-quick-edit-overlay" style="display:none;">
	<div class="ecv2-slideout" role="dialog" aria-modal="true" aria-labelledby="ecv2-user-qe-title">
		<div class="ecv2-slideout-header">
			<h2 id="ecv2-user-qe-title"><?php esc_html_e( 'Quick Edit Customer', 'wp-easycart' ); ?> <span class="ecv2-user-qe-id-chip" id="ecv2-user-qe-id-chip"></span></h2>
			<button type="button" class="ecv2-modal-close" onclick="ecv2_user_close_quick_edit();">&times;</button>
		</div>
		<div class="ecv2-slideout-body">
			<input type="hidden" id="ecv2-user-qe-id" value="" />

			<div class="ecv2-slideout-field">
				<label for="ecv2-user-qe-email"><?php esc_html_e( 'Email Address', 'wp-easycart' ); ?></label>
				<input type="email" id="ecv2-user-qe-email" class="ecv2-input" autocomplete="off" />
				<div class="ecv2-slideout-field-error" data-field="email"></div>
			</div>

			<div class="ecv2-slideout-field-row">
				<div class="ecv2-slideout-field">
					<label for="ecv2-user-qe-first"><?php esc_html_e( 'First Name', 'wp-easycart' ); ?></label>
					<input type="text" id="ecv2-user-qe-first" class="ecv2-input" autocomplete="off" />
				</div>
				<div class="ecv2-slideout-field">
					<label for="ecv2-user-qe-last"><?php esc_html_e( 'Last Name', 'wp-easycart' ); ?></label>
					<input type="text" id="ecv2-user-qe-last" class="ecv2-input" autocomplete="off" />
				</div>
			</div>

			<div class="ecv2-slideout-field">
				<label for="ecv2-user-qe-role"><?php esc_html_e( 'Role', 'wp-easycart' ); ?></label>
				<select id="ecv2-user-qe-role" class="ecv2-select">
					<?php foreach ( $ecv2_qe_roles as $ecv2_qe_role ) { ?>
						<?php if ( (int) $ecv2_qe_role->admin_access && ! $ecv2_qe_is_site_admin ) { continue; } ?>
						<option value="<?php echo esc_attr( $ecv2_qe_role->role_label ); ?>"><?php echo esc_html( $ecv2_qe_role->role_label ); ?></option>
					<?php } ?>
				</select>
				<div class="ecv2-slideout-field-error" data-field="user_level"></div>
			</div>

			<div class="ecv2-slideout-field">
				<label for="ecv2-user-qe-email-other"><?php esc_html_e( 'Alternate Email (CC on receipts)', 'wp-easycart' ); ?></label>
				<input type="email" id="ecv2-user-qe-email-other" class="ecv2-input" autocomplete="off" />
			</div>

			<div class="ecv2-slideout-field">
				<label for="ecv2-user-qe-vat"><?php esc_html_e( 'VAT Registration Number', 'wp-easycart' ); ?></label>
				<input type="text" id="ecv2-user-qe-vat" class="ecv2-input" autocomplete="off" />
			</div>

			<label class="ecv2-slideout-check">
				<input type="checkbox" id="ecv2-user-qe-subscriber" value="1" />
				<span><?php esc_html_e( 'Newsletter subscriber', 'wp-easycart' ); ?></span>
			</label>

			<div class="ecv2-slideout-divider"></div>

			<button type="button" class="ecv2-btn ecv2-btn-ghost ecv2-btn-sm" id="ecv2-user-qe-reset-btn" onclick="ecv2_user_qe_password_reset( this );">
				<span class="dashicons dashicons-lock"></span> <?php esc_html_e( 'Send Password Reset Email', 'wp-easycart' ); ?>
			</button>
			<p class="ecv2-slideout-hint"><?php esc_html_e( 'This immediately invalidates the current password and emails the customer a reset link.', 'wp-easycart' ); ?></p>

			<?php do_action( 'wp_easycart_admin_user_quick_edit_fields' ); ?>
		</div>
		<div class="ecv2-slideout-footer">
			<button type="button" class="ecv2-btn ecv2-btn-ghost" onclick="ecv2_user_close_quick_edit();"><?php esc_html_e( 'Cancel', 'wp-easycart' ); ?></button>
			<button type="button" class="ecv2-btn ecv2-btn-primary" id="ecv2-user-qe-save" onclick="ecv2_user_qe_save( this );"><?php esc_html_e( 'Save Customer', 'wp-easycart' ); ?></button>
		</div>
	</div>
</div>