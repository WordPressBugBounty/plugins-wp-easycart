<?php
/**
 * Order screen › Edit order › an address ( free from 6.0.2 ). $wpec_address_type is billing or shipping.
 *
 * Moved into the edit drawer by id. The field ids are the ones WP EasyCart PRO 6.0.1 printed, so an older PRO's script and
 * the order screen's own save the same way ( AJAX ec_admin_ajax_save_order_{type}_address ). Printed only when an older PRO
 * is not printing its own copy ( wp_easycart_admin_order_screen::pro_prints_edit_forms() ).
 *
 * 6.0.5: the country comes first and the rest follows it ( orders-details-v2.js ecodv2_address_country() ): the town, region and
 * postcode in the country's order ( wp_easycart_address::form_layout() ), named as the country names them, a list of the country's
 * regions where it has them ( the text box behind it keeps the value that is saved ), no postcode where the country has none. The
 * countries' data prints once a page ( #ecodv2_address_data ).
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
	'city'           => array( __( 'City', 'wp-easycart' ), 'place', 'address-level2' ),
	'state'          => array( __( 'State / province', 'wp-easycart' ), 'place', 'address-level1' ),
	'zip'            => array( __( 'Postal code', 'wp-easycart' ), 'place', 'postal-code' ),
);
$wpec_address_layout = 'csz';
if ( class_exists( 'wp_easycart_address' ) && method_exists( 'wp_easycart_address', 'form_config' ) ) {
	$wpec_address_layout = wp_easycart_address::form_layout( (string) $wpec_ao->{$wpec_type . '_country'} );
	if ( empty( $GLOBALS['wpec_ecodv2_address_data'] ) ) {
		$GLOBALS['wpec_ecodv2_address_data'] = true;
		$wpec_regions                        = array();
		/* The regions the store ships to ( as the storefront lists them ); a saved region not among them stays on show as it is. */
		foreach ( (array) $wpdb->get_results( 'SELECT c.iso2_cnt, s.code_sta, s.name_sta FROM ec_state s INNER JOIN ec_country c ON c.id_cnt = s.idcnt_sta WHERE s.ship_to_active = 1 ORDER BY c.iso2_cnt, s.sort_order, s.name_sta' ) as $wpec_region ) {
			$wpec_regions[ strtoupper( (string) $wpec_region->iso2_cnt ) ][] = array( (string) $wpec_region->code_sta, (string) $wpec_region->name_sta );
		}
		$wpec_address_data = array_merge(
			wp_easycart_address::form_config(),
			array(
				'regions' => $wpec_regions,
				'region'  => array(
					'_'  => __( 'State / province', 'wp-easycart' ),
					'US' => __( 'State', 'wp-easycart' ),
					'CA' => __( 'Province', 'wp-easycart' ),
					'AU' => __( 'State or territory', 'wp-easycart' ),
					'GB' => __( 'County', 'wp-easycart' ),
					'IE' => __( 'County', 'wp-easycart' ),
					'NZ' => __( 'Region', 'wp-easycart' ),
					'DE' => __( 'State', 'wp-easycart' ),
					'AT' => __( 'State', 'wp-easycart' ),
					'IT' => __( 'Province', 'wp-easycart' ),
					'ES' => __( 'Province', 'wp-easycart' ),
					'AR' => __( 'Province', 'wp-easycart' ),
					'CN' => __( 'Province', 'wp-easycart' ),
					'JP' => __( 'Prefecture', 'wp-easycart' ),
					'MX' => __( 'State', 'wp-easycart' ),
					'BR' => __( 'State', 'wp-easycart' ),
					'IN' => __( 'State', 'wp-easycart' ),
					'HK' => __( 'Area', 'wp-easycart' ),
				),
				'postal'  => array(
					'_'  => __( 'Postal code', 'wp-easycart' ),
					'US' => __( 'ZIP code', 'wp-easycart' ),
					'GB' => __( 'Postcode', 'wp-easycart' ),
					'AU' => __( 'Postcode', 'wp-easycart' ),
					'NZ' => __( 'Postcode', 'wp-easycart' ),
					'ZA' => __( 'Postcode', 'wp-easycart' ),
					'DE' => __( 'Postcode', 'wp-easycart' ),
					'IE' => __( 'Eircode', 'wp-easycart' ),
					'IN' => __( 'PIN code', 'wp-easycart' ),
					'BR' => __( 'CEP', 'wp-easycart' ),
				),
				'choose'  => __( 'Choose…', 'wp-easycart' ),
			)
		);
		/**
		 * The order screen's address editor: layouts, countries without postcodes, regions and the words for each country.
		 *
		 * @since 6.0.5
		 * @param array $wpec_address_data layouts, nozip, regions ( iso2 => [ [ code, name ] ] ), region / postal ( iso2 or _ => words ), choose.
		 */
		$wpec_address_data = apply_filters( 'wp_easycart_admin_order_address_editor', $wpec_address_data );
		echo '<script type="application/json" id="ecodv2_address_data">' . wp_json_encode( $wpec_address_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE ) . '</script>';
	}
}
?>
<div class="ecodv2-form ec_admin_initial_hide" id="ec_admin_order_details_<?php echo esc_attr( $wpec_type ); ?>_form" data-ecodv2-free-form="<?php echo esc_attr( $wpec_type ); ?>">
	<div class="ecv2-btn ecodv2-visually-hidden" id="ec_admin_order_details_<?php echo esc_attr( $wpec_type ); ?>_info_save"><?php esc_html_e( 'Save', 'wp-easycart' ); ?></div>
	<div class="ecodv2-grid ecodv2-address-grid" data-type="<?php echo esc_attr( $wpec_type ); ?>" data-layout="<?php echo esc_attr( $wpec_address_layout ); ?>">
		<div class="ecodv2-field ecodv2-field-wide ecodv2-place-country">
			<label for="<?php echo esc_attr( $wpec_type ); ?>_country"><?php esc_html_e( 'Country', 'wp-easycart' ); ?></label>
			<select name="<?php echo esc_attr( $wpec_type ); ?>_country" id="<?php echo esc_attr( $wpec_type ); ?>_country" class="select2-basic">
				<option value="0"><?php esc_html_e( 'Select a country', 'wp-easycart' ); ?></option>
				<?php foreach ( (array) $wpec_edit_countries as $wpec_country ) { ?>
				<option value="<?php echo esc_attr( $wpec_country->iso2_cnt ); ?>"<?php selected( $wpec_country->iso2_cnt, $wpec_ao->{$wpec_type . '_country'} ); ?>><?php echo esc_html( $wpec_country->name_cnt ); ?></option>
				<?php } ?>
			</select>
		</div>
		<?php foreach ( $wpec_fields as $wpec_part => $wpec_field ) { ?>
			<?php $wpec_id = $wpec_type . '_' . $wpec_part; ?>
		<div class="ecodv2-field<?php echo false !== strpos( $wpec_field[1], 'wide' ) ? ' ecodv2-field-wide' : ''; ?><?php echo false !== strpos( $wpec_field[1], 'place' ) ? ' ecodv2-place-' . esc_attr( $wpec_part ) : ''; ?>">
			<label for="<?php echo esc_attr( $wpec_id ); ?>"><?php echo esc_html( $wpec_field[0] ); ?><?php if ( false !== strpos( $wpec_field[1], 'optional' ) ) { ?> <span class="ecodv2-optional"><?php esc_html_e( 'optional', 'wp-easycart' ); ?></span><?php } ?></label>
			<input type="text" id="<?php echo esc_attr( $wpec_id ); ?>" name="<?php echo esc_attr( $wpec_id ); ?>" value="<?php echo esc_attr( isset( $wpec_ao->{$wpec_id} ) ? $wpec_ao->{$wpec_id} : '' ); ?>" autocomplete="<?php echo esc_attr( ( 'shipping' === $wpec_type ? 'shipping ' : 'billing ' ) . $wpec_field[2] ); ?>" />
			<?php if ( 'state' === $wpec_part ) { ?>
			<select id="<?php echo esc_attr( $wpec_id ); ?>_select" class="ecodv2-region-select" autocomplete="<?php echo esc_attr( ( 'shipping' === $wpec_type ? 'shipping ' : 'billing ' ) . $wpec_field[2] ); ?>" hidden></select>
			<?php } ?>
		</div>
		<?php } ?>
		<div class="ecodv2-field ecodv2-field-wide ecodv2-place-phone">
			<label for="<?php echo esc_attr( $wpec_type ); ?>_phone"><?php esc_html_e( 'Phone', 'wp-easycart' ); ?></label>
			<input type="tel" id="<?php echo esc_attr( $wpec_type ); ?>_phone" name="<?php echo esc_attr( $wpec_type ); ?>_phone" value="<?php echo esc_attr( $wpec_ao->{$wpec_type . '_phone'} ); ?>" autocomplete="<?php echo esc_attr( ( 'shipping' === $wpec_type ? 'shipping' : 'billing' ) . ' tel' ); ?>" />
		</div>
	</div>
</div>
