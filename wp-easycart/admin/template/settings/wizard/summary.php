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

/* Policies & notifications: the option set seeds placeholder links and youremail@url.com, which are not set. */
$terms   = '' !== (string) $W::real_option( 'ec_option_terms_link' );
$privacy = '' !== (string) $W::real_option( 'ec_option_privacy_link' );
$from    = (string) $W::real_option( 'ec_option_order_from_email' );

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
		/* 6.0.1: the stored method still names a system when shipping is switched off, so say so instead. */
		'v' => ! get_option( 'ec_option_use_shipping' )
			? __( 'Nothing shipped', 'wp-easycart' )
			: ( isset( $ship_labels[ $ship_method ] ) ? $ship_labels[ $ship_method ] : $not_set ) ),
	array( 'step' => $W::STEP_FINISH, 'k' => __( 'Policies', 'wp-easycart' ), 'ok' => ( $terms && $privacy ),
		'v' => ( $terms ? __( 'Terms ✓', 'wp-easycart' ) : __( 'Terms —', 'wp-easycart' ) ) . ' · ' . ( $privacy ? __( 'Privacy ✓', 'wp-easycart' ) : __( 'Privacy —', 'wp-easycart' ) ) ),
	array( 'step' => $W::STEP_FINISH, 'k' => __( 'Notifications', 'wp-easycart' ), 'ok' => '' !== $from,
		/* translators: %s: email address order emails are sent from. */
		'v' => '' !== $from ? sprintf( __( 'From %s', 'wp-easycart' ), $from ) : $not_set ),
);

/* A row is set once its step was submitted and, where a row says so, its value is real ( 'ok' ). A submitted row that is
   still missing something shows what it has, without a check. */
$set_count = 0;
foreach ( $rows as $i => $r ) {
	$rows[ $i ]['done'] = ( $completed >= $r['step'] );
	$rows[ $i ]['set']  = $rows[ $i ]['done'] && ( ! isset( $r['ok'] ) || $r['ok'] );
	if ( $rows[ $i ]['set'] ) {
		$set_count++;
	}
}
$rec = $wizard->get_recommended_status();
$rec_on = array();
$rec_ok = true;
foreach ( $rec as $r ) {
	if ( 'ok' !== $r['state'] ) {
		$rec_ok = false;
	}
	$rec_on[] = $r['label'] . ( 'ok' === $r['state'] ? '' : ' (' . ( 'warn' === $r['state'] ? __( 'waiting', 'wp-easycart' ) : __( 'off', 'wp-easycart' ) ) . ')' );
}

/* The store pages count once all three exist as published pages. */
$pages_ok = true;
foreach ( array( 'ec_option_storepage', 'ec_option_cartpage', 'ec_option_accountpage' ) as $page_option ) {
	if ( 'publish' !== get_post_status( (int) get_option( $page_option ) ) ) {
		$pages_ok = false;
	}
}
?>
<div class="ecwz-rail-box">
	<h3><?php esc_html_e( 'Setup summary', 'wp-easycart' ); ?> <span class="ecwz-badge ecwz-badge-gray"><?php echo esc_html( sprintf( __( '%1$d of %2$d', 'wp-easycart' ), $set_count, count( $rows ) ) ); ?></span></h3>
	<ul class="ecwz-sum">
		<?php foreach ( $rows as $r ) { $is_set = $r['set']; ?>
		<li class="<?php echo $is_set ? 'is-set' : ''; ?>">
			<span class="ecwz-ck" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3.5"><path d="M5 13l4 4L19 7"/></svg></span>
			<span><span class="ecwz-k"><?php echo esc_html( $r['k'] ); ?></span><span class="ecwz-v"><?php echo $r['done'] ? esc_html( $r['v'] ) : esc_html( $not_set ); ?></span></span>
			<?php if ( $r['done'] ) { ?><a class="ecwz-edit" href="<?php echo esc_url( $wizard->step_url( $r['step'] ) ); ?>"><?php esc_html_e( 'Edit', 'wp-easycart' ); ?></a><?php } ?>
		</li>
		<?php } ?>
		<li class="ecwz-grp"><?php esc_html_e( 'Applied automatically', 'wp-easycart' ); ?></li>
		<li class="<?php echo $rec_ok ? 'is-auto' : ''; ?>">
			<span class="ecwz-ck" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3.5"><path d="M5 13l4 4L19 7"/></svg></span>
			<span><span class="ecwz-k"><?php esc_html_e( 'Recommended settings', 'wp-easycart' ); ?></span><span class="ecwz-v"><?php echo esc_html( implode( ' · ', $rec_on ) ); ?></span></span>
			<a class="ecwz-edit" href="<?php echo esc_url( $wizard->step_url( $W::STEP_FINISH ) . '#ecwz-recommended' ); ?>"><?php esc_html_e( 'Edit', 'wp-easycart' ); ?></a>
		</li>
		<li class="<?php echo $pages_ok ? 'is-auto' : ''; ?>">
			<span class="ecwz-ck" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3.5"><path d="M5 13l4 4L19 7"/></svg></span>
			<span><span class="ecwz-k"><?php esc_html_e( 'Store pages', 'wp-easycart' ); ?></span><span class="ecwz-v"><?php echo $pages_ok ? esc_html__( 'Store · Cart · Account created', 'wp-easycart' ) : esc_html__( 'Not created yet', 'wp-easycart' ); ?></span></span>
		</li>
	</ul>
	<div class="ecwz-progress"><i style="width:<?php echo esc_attr( round( $set_count / count( $rows ) * 100 ) ); ?>%"></i></div>
</div>
