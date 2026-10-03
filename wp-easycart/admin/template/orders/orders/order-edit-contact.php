<?php
/**
 * Order screen › Edit order › Customer: the order's email addresses and the card details kept on it ( free from 6.0.2 ).
 *
 * Moved into the edit drawer by id. The field ids are the ones WP EasyCart PRO 6.0.1 printed, so an older PRO's script and
 * the order screen's own save the same way ( AJAX ec_admin_ajax_save_order_management_details ). Printed only when an older
 * PRO is not printing its own copy ( wp_easycart_admin_order_screen::pro_prints_edit_forms() ).
 *
 * @since 6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wpec_edit_order = $this->order;
$wpec_has_other  = ( isset( $wpec_edit_order->email_other ) && '' !== trim( (string) $wpec_edit_order->email_other ) );
?>
<div class="ecodv2-form ec_admin_initial_hide" id="ec_admin_edit_order_information" data-ecodv2-free-form="contact">
	<div class="ecv2-btn ecodv2-visually-hidden" id="ec_admin_order_details_save"><?php esc_html_e( 'Save', 'wp-easycart' ); ?></div>
	<div class="ecodv2-grid">
		<div class="ecodv2-field ecodv2-field-wide">
			<label for="user_email"><?php esc_html_e( 'Email', 'wp-easycart' ); ?></label>
			<input type="email" id="user_email" name="user_email" placeholder="customer@example.com" value="<?php echo esc_attr( $wpec_edit_order->user_email ); ?>" autocomplete="off" />
		</div>
		<div class="ecodv2-field ecodv2-field-wide<?php echo $wpec_has_other ? ' ecodv2-hidden' : ''; ?>" id="ecodv2_add_email_wrap">
			<button type="button" class="ecodv2-add-field" onclick="ecodv2_show_add_email(); return false;">&#65291; <?php esc_html_e( 'Add another email', 'wp-easycart' ); ?></button>
		</div>
		<div class="ecodv2-field ecodv2-field-wide ecodv2-field-removable<?php echo $wpec_has_other ? '' : ' ecodv2-hidden'; ?>" id="ecodv2_add_email_field">
			<label for="email_other"><?php esc_html_e( 'Other email', 'wp-easycart' ); ?> <span class="ecodv2-optional"><?php esc_html_e( 'copied on order emails', 'wp-easycart' ); ?></span></label>
			<input type="email" id="email_other" name="email_other" placeholder="second@example.com" value="<?php echo esc_attr( isset( $wpec_edit_order->email_other ) ? $wpec_edit_order->email_other : '' ); ?>" autocomplete="off" />
			<button type="button" class="ecodv2-field-rm" title="<?php esc_attr_e( 'Remove', 'wp-easycart' ); ?>" aria-label="<?php esc_attr_e( 'Remove the other email', 'wp-easycart' ); ?>" onclick="ecodv2_hide_add_email(); return false;"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
		</div>
		<div class="ecodv2-field ecodv2-field-wide">
			<label for="card_holder_name"><?php esc_html_e( 'Cardholder name', 'wp-easycart' ); ?></label>
			<input type="text" id="card_holder_name" name="card_holder_name" placeholder="<?php esc_attr_e( 'Name on card', 'wp-easycart' ); ?>" value="<?php echo esc_attr( $wpec_edit_order->card_holder_name ); ?>" autocomplete="off" />
		</div>
		<div class="ecodv2-field">
			<label for="creditcard_digits"><?php esc_html_e( 'Card', 'wp-easycart' ); ?> <span class="ecodv2-optional"><?php esc_html_e( 'last 4 digits', 'wp-easycart' ); ?></span></label>
			<div class="ecodv2-cc-wrap">
				<span class="ecodv2-cc-wrap-dots" aria-hidden="true">&bull;&bull;&bull;&bull;</span>
				<input type="text" maxlength="4" inputmode="numeric" pattern="[0-9]*" id="creditcard_digits" name="creditcard_digits" placeholder="0000" value="<?php echo esc_attr( $wpec_edit_order->creditcard_digits ); ?>" autocomplete="off" />
			</div>
		</div>
		<div class="ecodv2-field">
			<label for="cc_exp_month"><?php esc_html_e( 'Expires', 'wp-easycart' ); ?></label>
			<div class="ecodv2-exp-wrap">
				<input type="text" class="ecodv2-exp-mm" maxlength="2" inputmode="numeric" id="cc_exp_month" name="cc_exp_month" placeholder="MM" aria-label="<?php esc_attr_e( 'Expiry month', 'wp-easycart' ); ?>" value="<?php echo esc_attr( $wpec_edit_order->cc_exp_month ); ?>" autocomplete="off" />
				<span class="ecodv2-exp-slash" aria-hidden="true">/</span>
				<input type="text" class="ecodv2-exp-yyyy" maxlength="4" inputmode="numeric" id="cc_exp_year" name="cc_exp_year" placeholder="YYYY" aria-label="<?php esc_attr_e( 'Expiry year', 'wp-easycart' ); ?>" value="<?php echo esc_attr( $wpec_edit_order->cc_exp_year ); ?>" autocomplete="off" />
			</div>
		</div>
	</div>
	<p class="ecodv2-field-note"><?php esc_html_e( 'Corrections to what the order records. The card details are for your reference only: nothing is charged.', 'wp-easycart' ); ?></p>
</div>
