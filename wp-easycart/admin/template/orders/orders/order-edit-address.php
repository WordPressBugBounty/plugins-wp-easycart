<?php
/**
 * Order screen › Edit order › an address ( free from 6.0.2 ). $wpec_address_type is billing or shipping.
 *
 * Moved into the edit drawer by id. The field ids are the ones WP EasyCart PRO 6.0.1 printed, so an older PRO's script and
 * the order screen's own save the same way ( AJAX ec_admin_ajax_save_order_{type}_address ). Printed only when an older PRO
 * is not printing its own copy ( wp_easycart_admin_order_screen::pro_prints_edit_forms() ).
 *
 * @since 6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$wpec_type = ( isset( $wpec_address_type ) && 'shipping' === $wpec_address_type ) ? 'shipping' : 'billing';
$wpec_ao   = $this->order;
if ( ! isset( $wpec_edit_countries ) ) {
	$wpec_edit_countries = $wpdb->get_results( 'SELECT iso2_cnt, name_cnt FROM ec_country ORDER BY name_cnt ASC' );
}
$wpec_fields = array(
	'first_name'     => array( __( 'First name', 'wp-easycart' ), '', 'given-name' ),
	'last_name'      => array( __( 'Last name', 'wp-easycart' ), '', 'family-name' ),
	'company_name'   => array( __( 'Company', 'wp-easycart' ), 'wide optional', 'organization' ),
	'address_line_1' => array( __( 'Address', 'wp-easycart' ), 'wide', 'address-line1' ),
	'address_line_2' => array( __( 'Apartment, suite, floor', 'wp-easycart' ), 'wide optional', 'address-line2' ),
	'city'           => array( __( 'City', 'wp-easycart' ), '', 'address-level2' ),
	'state'          => array( __( 'State / province', 'wp-easycart' ), '', 'address-level1' ),
	'zip'            => array( __( 'Postal code', 'wp-easycart' ), '', 'postal-code' ),
);
?>
<div class="ecodv2-form ec_admin_initial_hide" id="ec_admin_order_details_<?php echo esc_attr( $wpec_type ); ?>_form" data-ecodv2-free-form="<?php echo esc_attr( $wpec_type ); ?>">
	<div class="ecv2-btn ecodv2-visually-hidden" id="ec_admin_order_details_<?php echo esc_attr( $wpec_type ); ?>_info_save"><?php esc_html_e( 'Save', 'wp-easycart' ); ?></div>
	<div class="ecodv2-grid">
		<?php foreach ( $wpec_fields as $wpec_part => $wpec_field ) { ?>
			<?php $wpec_id = $wpec_type . '_' . $wpec_part; ?>
		<div class="ecodv2-field<?php echo false !== strpos( $wpec_field[1], 'wide' ) ? ' ecodv2-field-wide' : ''; ?>">
			<label for="<?php echo esc_attr( $wpec_id ); ?>"><?php echo esc_html( $wpec_field[0] ); ?><?php if ( false !== strpos( $wpec_field[1], 'optional' ) ) { ?> <span class="ecodv2-optional"><?php esc_html_e( 'optional', 'wp-easycart' ); ?></span><?php } ?></label>
			<input type="text" id="<?php echo esc_attr( $wpec_id ); ?>" name="<?php echo esc_attr( $wpec_id ); ?>" value="<?php echo esc_attr( isset( $wpec_ao->{$wpec_id} ) ? $wpec_ao->{$wpec_id} : '' ); ?>" autocomplete="<?php echo esc_attr( ( 'shipping' === $wpec_type ? 'shipping ' : 'billing ' ) . $wpec_field[2] ); ?>" />
		</div>
		<?php } ?>
		<div class="ecodv2-field">
			<label for="<?php echo esc_attr( $wpec_type ); ?>_country"><?php esc_html_e( 'Country', 'wp-easycart' ); ?></label>
			<select name="<?php echo esc_attr( $wpec_type ); ?>_country" id="<?php echo esc_attr( $wpec_type ); ?>_country" class="select2-basic">
				<option value="0"><?php esc_html_e( 'Select a country', 'wp-easycart' ); ?></option>
				<?php foreach ( (array) $wpec_edit_countries as $wpec_country ) { ?>
				<option value="<?php echo esc_attr( $wpec_country->iso2_cnt ); ?>"<?php selected( $wpec_country->iso2_cnt, $wpec_ao->{$wpec_type . '_country'} ); ?>><?php echo esc_html( $wpec_country->name_cnt ); ?></option>
				<?php } ?>
			</select>
		</div>
		<div class="ecodv2-field ecodv2-field-wide">
			<label for="<?php echo esc_attr( $wpec_type ); ?>_phone"><?php esc_html_e( 'Phone', 'wp-easycart' ); ?></label>
			<input type="tel" id="<?php echo esc_attr( $wpec_type ); ?>_phone" name="<?php echo esc_attr( $wpec_type ); ?>_phone" value="<?php echo esc_attr( $wpec_ao->{$wpec_type . '_phone'} ); ?>" autocomplete="<?php echo esc_attr( ( 'shipping' === $wpec_type ? 'shipping' : 'billing' ) . ' tel' ); ?>" />
		</div>
	</div>
</div>
