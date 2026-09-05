<?php
/**
 * Setup summary rail. Values come from saved options; a row only counts as set
 * once its step has been submitted, so the rail reflects decisions, not defaults.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
global $wpdb;
$wizard    = wp_easycart_admin_setup_wizard();
$completed = $wizard->completed_through();
$W         = 'wp_easycart_admin_setup_wizard';
$not_set   = __( 'Not set', 'wp-easycart' );

/* Location & currency */
$locale       = get_option( 'ec_option_store_locale' );
$country_name = $locale ? $wpdb->get_var( $wpdb->prepare( 'SELECT name_cnt FROM ec_country WHERE iso2_cnt = %s', $locale ) ) : '';
$currency     = get_option( 'ec_option_base_currency' );
$symbol       = get_option( 'ec_option_currency' );
$weight       = get_option( 'ec_option_paypal_weight_unit' );
$has_tax      = (bool) $wpdb->get_var( 'SELECT taxrate_id FROM ec_taxrate LIMIT 1' ) || get_option( 'ec_option_enable_easy_canada_tax' );

/* Payments & checkout */
$gw      = $wizard->get_gateway_state();
$gw_list = array();
if ( $gw['manual'] ) { $gw_list[] = __( 'Manual', 'wp-easycart' ); }
if ( $gw['paypal'] ) { $gw_list[] = 'PayPal'; }
if ( $gw['stripe'] ) { $gw_list[] = 'Stripe'; }
if ( $gw['square'] ) { $gw_list[] = 'Square'; }

/* Shipping */
$ship_method = $wpdb->get_var( 'SELECT shipping_method FROM ec_setting LIMIT 1' );
$ship_labels = array(
	'method' => __( 'Flat rates', 'wp-easycart' ),
	'price'  => __( 'By cart total', 'wp-easycart' ),
	'weight' => __( 'By weight', 'wp-easycart' ),
);

$rows = array(
	array( 'step' => $W::STEP_LOCATION, 'k' => __( 'Location', 'wp-easycart' ),
		'v' => $country_name ? $country_name : ( $locale ? $locale : $not_set ) ),
	array( 'step' => $W::STEP_LOCATION, 'k' => __( 'Currency & units', 'wp-easycart' ),
		'v' => $currency ? trim( $currency . ' ' . ( $symbol ? '(' . $symbol . ')' : '' ) . ( $weight ? ' · ' . $weight : '' ) ) : $not_set ),
	array( 'step' => $W::STEP_LOCATION, 'k' => __( 'Sales tax', 'wp-easycart' ),
		'v' => $has_tax ? __( 'Charging (starter rates installed)', 'wp-easycart' ) : __( 'Not charging', 'wp-easycart' ) ),
	array( 'step' => $W::STEP_PAYMENTS, 'k' => __( 'Payments', 'wp-easycart' ),
		'v' => $gw_list ? implode( ', ', $gw_list ) : __( 'None yet', 'wp-easycart' ) ),
	array( 'step' => $W::STEP_PAYMENTS, 'k' => __( 'Checkout', 'wp-easycart' ),
		'v' => get_option( 'ec_option_allow_guest' ) ? __( 'Guest checkout allowed', 'wp-easycart' ) : __( 'Account required', 'wp-easycart' ) ),
	array( 'step' => $W::STEP_SHIPPING, 'k' => __( 'Shipping', 'wp-easycart' ),
		'v' => isset( $ship_labels[ $ship_method ] ) ? $ship_labels[ $ship_method ] : $not_set ),
	array( 'step' => $W::STEP_FINISH, 'k' => __( 'Policies', 'wp-easycart' ),
		'v' => ( get_option( 'ec_option_terms_link' ) ? __( 'Terms ✓', 'wp-easycart' ) : __( 'Terms —', 'wp-easycart' ) ) . ' · ' . ( get_option( 'ec_option_privacy_link' ) ? __( 'Privacy ✓', 'wp-easycart' ) : __( 'Privacy —', 'wp-easycart' ) ) ),
	array( 'step' => $W::STEP_FINISH, 'k' => __( 'Notifications', 'wp-easycart' ),
		'v' => get_option( 'ec_option_order_from_email' ) ? sprintf( __( 'From %s', 'wp-easycart' ), get_option( 'ec_option_order_from_email' ) ) : $not_set ),
);

$set_count = 0;
foreach ( $rows as $r ) {
	if ( $completed >= $r['step'] ) {
		$set_count++;
	}
}
$rec = $wizard->get_recommended_status();
$rec_on = array();
foreach ( $rec as $r ) {
	$rec_on[] = $r['label'] . ( 'ok' === $r['state'] ? '' : ' (' . ( 'warn' === $r['state'] ? __( 'waiting', 'wp-easycart' ) : __( 'off', 'wp-easycart' ) ) . ')' );
}
?>
<div class="ecwz-rail-box">
	<h3><?php esc_html_e( 'Setup summary', 'wp-easycart' ); ?> <span class="ecwz-badge ecwz-badge-gray"><?php echo esc_html( sprintf( __( '%1$d of %2$d', 'wp-easycart' ), $set_count, count( $rows ) ) ); ?></span></h3>
	<ul class="ecwz-sum">
		<?php foreach ( $rows as $r ) { $is_set = ( $completed >= $r['step'] ); ?>
		<li class="<?php echo $is_set ? 'is-set' : ''; ?>">
			<span class="ecwz-ck" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3.5"><path d="M5 13l4 4L19 7"/></svg></span>
			<span><span class="ecwz-k"><?php echo esc_html( $r['k'] ); ?></span><span class="ecwz-v"><?php echo $is_set ? esc_html( $r['v'] ) : esc_html( $not_set ); ?></span></span>
			<?php if ( $is_set ) { ?><a class="ecwz-edit" href="<?php echo esc_url( $wizard->step_url( $r['step'] ) ); ?>"><?php esc_html_e( 'Edit', 'wp-easycart' ); ?></a><?php } ?>
		</li>
		<?php } ?>
		<li class="ecwz-grp"><?php esc_html_e( 'Applied automatically', 'wp-easycart' ); ?></li>
		<li class="is-auto">
			<span class="ecwz-ck" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3.5"><path d="M5 13l4 4L19 7"/></svg></span>
			<span><span class="ecwz-k"><?php esc_html_e( 'Recommended settings', 'wp-easycart' ); ?></span><span class="ecwz-v"><?php echo esc_html( implode( ' · ', $rec_on ) ); ?></span></span>
			<a class="ecwz-edit" href="<?php echo esc_url( $wizard->step_url( $W::STEP_FINISH ) . '#ecwz-recommended' ); ?>"><?php esc_html_e( 'Edit', 'wp-easycart' ); ?></a>
		</li>
		<li class="is-auto">
			<span class="ecwz-ck" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3.5"><path d="M5 13l4 4L19 7"/></svg></span>
			<span><span class="ecwz-k"><?php esc_html_e( 'Store pages', 'wp-easycart' ); ?></span><span class="ecwz-v"><?php esc_html_e( 'Store · Cart · Account created', 'wp-easycart' ); ?></span></span>
		</li>
	</ul>
	<div class="ecwz-progress"><i style="width:<?php echo esc_attr( round( $set_count / count( $rows ) * 100 ) ); ?>%"></i></div>
</div>
