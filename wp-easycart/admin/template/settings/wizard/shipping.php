<?php
/**
 * Step 3 — Shipping. Radio cards preview the preset rates that will be installed.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
global $wpdb;
$wizard  = wp_easycart_admin_setup_wizard();
$presets = $wizard->get_shipping_presets();
$upsell  = $wizard->show_upsell();
$symbol  = html_entity_decode( get_option( 'ec_option_currency' ) ? get_option( 'ec_option_currency' ) : '$' );
$weight  = get_option( 'ec_option_paypal_weight_unit' ) ? get_option( 'ec_option_paypal_weight_unit' ) : 'lbs';
$current = $wpdb->get_var( 'SELECT shipping_method FROM ec_setting LIMIT 1' );
$counts  = $wizard->get_shipping_rate_counts();
$map     = array( 'method' => 'static', 'price' => 'price', 'weight' => 'weight' );
/* Pre-select the saved method only if this step was actually completed; the DB default is 'method'. */
$selected = ( isset( $map[ $current ] ) && $wizard->completed_through() >= wp_easycart_admin_setup_wizard::STEP_SHIPPING ) ? $map[ $current ] : 'static';
/* 6.0.1: a store with nothing to ship says so here, rather than installing rates it will never use. */
$ships_nothing = ( ! get_option( 'ec_option_use_shipping' ) )
	|| ( class_exists( 'wp_easycart_admin_store_status' ) && wp_easycart_admin_store_status::acknowledged( 'shipping' ) );
if ( $ships_nothing ) {
	$selected = 'none';
}

$fmt = function( $n ) use ( $symbol ) {
	return $symbol . number_format( (float) $n, 2 );
};
?>
<form action="" method="POST" name="wpeasycart_admin_setup_wizard_form" id="wpeasycart_admin_setup_wizard_form" novalidate="novalidate" class="ecwz-form">
	<?php wp_easycart_admin_verification()->print_nonce_field( 'wp_easycart_nonce', 'wp-easycart-process-wizard-shipping' ); ?>
	<input type="hidden" name="ec_admin_form_action" id="ec_admin_form_action" value="process-wizard-shipping">

	<div class="ecwz-body">
		<h2><?php esc_html_e( 'How do you want to charge for shipping?', 'wp-easycart' ); ?></h2>
		<p class="ecwz-lede"><?php echo sprintf( esc_html__( 'Pick a starting point and we\'ll install these rates for you. Edit amounts, add free-shipping rules or switch methods later under %s.', 'wp-easycart' ), '<a href="admin.php?page=wp-easycart-settings&subpage=shipping-rates">' . esc_html__( 'Settings › Shipping Rates', 'wp-easycart' ) . '</a>' ); ?></p>

		<div class="ecwz-cards" id="ecwz_ship_cards">
			<?php foreach ( $presets as $key => $p ) { ?>
			<label class="ecwz-ccard ecwz-rcard<?php echo ( $key == $selected ) ? ' is-on' : ''; ?>" data-ship="<?php echo esc_attr( $key ); ?>">
				<input type="radio" name="shipping_method" value="<?php echo esc_attr( $key ); ?>"<?php checked( $key, $selected ); ?>>
				<div class="ecwz-ccard-top"><span class="ecwz-dot" aria-hidden="true"></span><div><h4><?php echo esc_html( $p['label'] ); ?></h4><span class="ecwz-sub"><?php echo esc_html( $p['sub'] ); ?></span></div></div>
				<p><?php echo esc_html( $p['desc'] ); ?><?php if ( 'weight' == $key ) { ?> <?php echo sprintf( esc_html__( 'Weights in %s.', 'wp-easycart' ), '<strong>' . esc_html( $weight ) . '</strong>' ); ?><?php } ?></p>
				<?php if ( $counts[ $key ] > 0 ) { ?>
				<div class="ecwz-rates-note"><span class="ecwz-badge ecwz-badge-gray"><?php echo esc_html( sprintf( _n( '%d rate already set up', '%d rates already set up', $counts[ $key ], 'wp-easycart' ), $counts[ $key ] ) ); ?></span> <?php esc_html_e( 'Your existing rates will be kept; nothing new is added.', 'wp-easycart' ); ?></div>
				<?php } else { ?>
				<div class="ecwz-rates-note"><?php esc_html_e( 'We\'ll install these to get you started:', 'wp-easycart' ); ?></div>
				<ul class="ecwz-rates">
					<?php if ( 'static' == $key ) { ?>
						<?php foreach ( $p['rates'] as $r ) { ?><li><span><?php echo esc_html( $r['shipping_label'] ); ?></span><b><?php echo esc_html( $fmt( $r['shipping_rate'] ) ); ?></b></li><?php } ?>
					<?php } else {
						$n = count( $p['rates'] );
						foreach ( $p['rates'] as $i => $r ) {
							$from = $r['trigger_rate'];
							$to   = ( $i + 1 < $n ) ? $p['rates'][ $i + 1 ]['trigger_rate'] : null;
							if ( 'price' == $key ) {
								$range = ( null === $to ) ? $fmt( $from ) . ' +' : $fmt( $from ) . ' – ' . $fmt( $to );
							} else {
								$range = ( null === $to ) ? rtrim( rtrim( $from, '0' ), '.' ) . ' + ' . $weight : rtrim( rtrim( $from, '0' ), '.' ) . ' – ' . rtrim( rtrim( $to, '0' ), '.' ) . ' ' . $weight;
							}
					?><li><span><?php echo esc_html( $range ); ?></span><b><?php echo esc_html( $fmt( $r['shipping_rate'] ) ); ?></b></li><?php
						}
					} ?>
				</ul>
				<?php } ?>
			</label>
			<?php } ?>

			<label class="ecwz-ccard ecwz-rcard<?php echo ( 'none' == $selected ) ? ' is-on' : ''; ?>" data-ship="none">
				<input type="radio" name="shipping_method" value="none"<?php checked( 'none', $selected ); ?>>
				<div class="ecwz-ccard-top"><span class="ecwz-dot" aria-hidden="true"></span><div><h4><?php esc_html_e( 'I don\'t ship anything', 'wp-easycart' ); ?></h4><span class="ecwz-sub"><?php esc_html_e( 'Downloads, services or collection only', 'wp-easycart' ); ?></span></div></div>
				<p><?php esc_html_e( 'No rates are installed and checkout asks for no delivery charge. Your store counts as set up for shipping, so nothing keeps warning you about it.', 'wp-easycart' ); ?></p>
				<div class="ecwz-rates-note"><?php echo sprintf( esc_html__( 'Change your mind later under %s.', 'wp-easycart' ), '<strong>' . esc_html__( 'Settings › Shipping Rates', 'wp-easycart' ) . '</strong>' ); ?></div>
			</label>

			<?php if ( $upsell ) { ?>
			<div class="ecwz-ccard is-locked">
				<div class="ecwz-ccard-top"><span class="ecwz-ico" style="background:var(--ecsh-g400,#9ca3af)">&#8635;</span><div><h4><?php esc_html_e( 'Live carrier rates', 'wp-easycart' ); ?></h4><span class="ecwz-sub">UPS · USPS · FedEx · DHL · Canada Post · Australia Post</span></div></div>
				<p><?php esc_html_e( 'Real-time quotes from the carrier at checkout, based on box weight and destination.', 'wp-easycart' ); ?></p>
				<div class="ecwz-ccard-act">
					<span class="ecwz-badge ecwz-badge-amber"><?php echo esc_html( class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::badge( 'pro' ) : __( 'Pro/Premium', 'wp-easycart' ) ); ?></span>
					<a class="ecwz-btn ecwz-btn-sm" href="admin.php?page=wp-easycart-registration&ec_trial=start" target="_blank"><?php esc_html_e( 'Try free for 14 days', 'wp-easycart' ); ?></a>
				</div>
			</div>
			<?php } ?>
		</div>
	</div>

	<?php $wizard->render_footer( wp_easycart_admin_setup_wizard::STEP_SHIPPING ); ?>
</form>