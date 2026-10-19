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
/* 6.0.5: the unit as people write it ( kg, lb ), not the option's value ( kgs, lbs ). */
$weight  = ( class_exists( 'wp_easycart_packages' ) && method_exists( 'wp_easycart_packages', 'weight_unit' ) ) ? wp_easycart_packages::weight_unit( $weight ) : rtrim( $weight, 's' );
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
	/* 6.0.5: the store's own format ( 93,00 € ). */
	return function_exists( 'wp_easycart_admin_money_text' ) ? wp_easycart_admin_money_text( (float) $n ) : $symbol . number_format( (float) $n, 2 );
};
$ship_to = method_exists( $wizard, 'ship_to_state' ) ? $wizard->ship_to_state() : array( 'country' => '' );
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
				<?php
				/* 6.0.3: a UK store is offered Royal Mail first ( priced from Royal Mail's price lists, not quoted live ). */
				$ecwz_uk = class_exists( 'wp_easycart_carriers' ) && method_exists( 'wp_easycart_carriers', 'store_country' ) && 'GB' === wp_easycart_carriers::store_country();
				/* 6.0.5 ( admin review M10 ): the carriers that quote for this store's country first. */
				$ecwz_country  = ( class_exists( 'wp_easycart_carriers' ) && method_exists( 'wp_easycart_carriers', 'store_country' ) ) ? wp_easycart_carriers::store_country() : '';
				$ecwz_carriers = array(
					'GB' => 'Royal Mail · DHL · UPS · FedEx',
					'CA' => 'Canada Post · UPS · FedEx · DHL',
					'AU' => 'Australia Post · DHL · UPS · FedEx',
					'US' => 'UPS · USPS · FedEx · DHL',
				);
				$ecwz_carriers = isset( $ecwz_carriers[ $ecwz_country ] ) ? $ecwz_carriers[ $ecwz_country ] : ( '' === $ecwz_country ? 'UPS · USPS · FedEx · DHL · Royal Mail · Canada Post · Australia Post' : 'DHL · UPS · FedEx' );
				?>
				<div class="ecwz-ccard-top"><span class="ecwz-ico" style="background:var(--ecsh-g400,#9ca3af)">&#8635;</span><div><h4><?php esc_html_e( 'Live carrier rates', 'wp-easycart' ); ?></h4><span class="ecwz-sub"><?php echo esc_html( $ecwz_carriers ); ?></span></div></div>
				<p><?php echo esc_html( $ecwz_uk ? __( 'Royal Mail prices worked out from each parcel’s size, weight and destination, and live quotes from DHL, UPS and FedEx.', 'wp-easycart' ) : __( 'Real-time quotes from the carrier at checkout, based on box weight and destination.', 'wp-easycart' ) ); ?></p>
				<div class="ecwz-ccard-act">
					<span class="ecwz-badge ecwz-badge-amber"><?php echo esc_html( class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::badge( 'pro' ) : __( 'Pro/Premium', 'wp-easycart' ) ); ?></span>
					<a class="ecwz-btn ecwz-btn-sm" href="<?php echo esc_url( wp_easycart_admin()->pro_install_url( 'trial' ) ); ?>" target="_blank"><?php esc_html_e( 'Try free for 14 days', 'wp-easycart' ); ?></a>
				</div>
			</div>
			<?php } ?>
		</div>

		<?php if ( '' !== $ship_to['country'] ) { ?>
		<?php /* 6.0.5 ( admin review M10 ): every country started switched on, Antarctica included. */ ?>
		<fieldset class="ecwz-shipto" id="ecwz_ship_to">
			<legend><?php esc_html_e( 'Where do you ship?', 'wp-easycart' ); ?></legend>
			<label class="ecwz-vat-choice"><input type="radio" name="ship_to" value="country"<?php checked( 'country', $ship_to['current'] ); ?>> <span><b><?php echo esc_html( sprintf( /* translators: %s: the store's country. */ __( 'Only within %s', 'wp-easycart' ), $ship_to['name'] ) ); ?></b></span></label>
			<?php if ( ! empty( $ship_to['region'] ) ) { ?>
			<label class="ecwz-vat-choice"><input type="radio" name="ship_to" value="region"<?php checked( 'region', $ship_to['current'] ); ?>> <span><b><?php echo esc_html( sprintf( /* translators: %s: a region, e.g. Europe. */ __( 'Across %s', 'wp-easycart' ), $ship_to['region']['name'] ) ); ?></b> <?php echo esc_html( sprintf( /* translators: %d: how many countries. */ _n( '%d country', '%d countries', count( $ship_to['region']['countries'] ), 'wp-easycart' ), count( $ship_to['region']['countries'] ) ) ); ?></span></label>
			<?php } ?>
			<label class="ecwz-vat-choice"><input type="radio" name="ship_to" value="world"<?php checked( 'world', $ship_to['current'] ); ?>> <span><b><?php esc_html_e( 'Worldwide', 'wp-easycart' ); ?></b> <?php esc_html_e( 'Every country people live in.', 'wp-easycart' ); ?></span></label>
			<?php if ( 'keep' === $ship_to['current'] ) { ?>
			<?php /* 6.0.5: a list chosen under Countries & Regions matches none of the above: keep it ( "Worldwide" was ticked, so Continue opened every country ). */ ?>
			<label class="ecwz-vat-choice"><input type="radio" name="ship_to" value="keep" checked> <span><b><?php esc_html_e( 'Keep the countries I ship to now', 'wp-easycart' ); ?></b> <?php echo esc_html( sprintf( /* translators: %d: how many countries. */ _n( '%d country, as set under Countries & Regions.', '%d countries, as set under Countries & Regions.', (int) $ship_to['count'], 'wp-easycart' ), (int) $ship_to['count'] ) ); ?></span></label>
			<?php } ?>
			<div class="ecwz-hint"><?php echo sprintf( esc_html__( 'Checkout offers only these countries for delivery. Fine-tune them later under %s.', 'wp-easycart' ), '<strong>' . esc_html__( 'Settings › Countries & Regions', 'wp-easycart' ) . '</strong>' ); ?></div>
		</fieldset>
		<?php } ?>
	</div>

	<?php $wizard->render_footer( wp_easycart_admin_setup_wizard::STEP_SHIPPING ); ?>
</form>