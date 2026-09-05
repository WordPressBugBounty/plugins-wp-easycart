<?php
/**
 * Step 2 — Payments & checkout.
 *
 * Gateways are cards with explicit Connect buttons ( the old design used toggles
 * that navigated off-site ). Connect links are the same onboarding URLs as before;
 * their return handlers redirect back here ( see load_setup_wizard() remap ).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$wizard   = wp_easycart_admin_setup_wizard();
$gw       = $wizard->get_gateway_state();
$env      = wp_easycart_admin_setup_wizard::get_environment();
$gate     = $wizard->show_terms_gate();
$upsell   = $wizard->show_upsell();
$admin    = esc_url_raw( admin_url() );
$state_id = rand( 1000000, 9999999 );

$paypal_url = esc_url_raw( wp_easycart_admin()->get_available_url() ) . '/paypal-v2/production_onboard.php?redirect=' . urlencode( $admin . '?wpeasycart_paypal_onboard=production&is_wizard=true' );
$stripe_url = esc_url_raw( wp_easycart_admin()->get_available_url() ) . '/connect/?step=start&redirect=' . urlencode( $admin . '?ec_admin_form_action=stripe_onboard&env=production&goto=wizard' ) . '&env=production';
$square_url = 'https://connect.wpeasycart.com/square-v2/?url=' . urlencode( $admin . '?ec_admin_form_action=handle-square&goto=wizard' ) . '&state=' . $state_id;
$payment_settings = admin_url( 'admin.php?page=wp-easycart-settings&subpage=payment' );

$flash = '';
if ( isset( $_GET['success'] ) ) {
	$flash = __( 'Connected. You can manage this gateway any time under Settings › Payment.', 'wp-easycart' );
} else if ( isset( $_GET['error'] ) && 'terms-required' == $_GET['error'] ) {
	$flash = __( 'Accept the EasyCart Connect terms below to use PayPal, Stripe or Square. Manual payments do not require them.', 'wp-easycart' );
} else if ( isset( $_GET['error'] ) ) {
	$flash = __( 'The gateway connection did not complete. You can try again below or finish it later under Settings › Payment.', 'wp-easycart' );
}

$ssl_badge = $env['https']
	? '<span class="ecwz-badge ecwz-badge-green">&#10003; ' . esc_html__( 'SSL detected', 'wp-easycart' ) . '</span>'
	: '<span class="ecwz-badge ecwz-badge-amber">' . esc_html__( 'Needs SSL', 'wp-easycart' ) . '</span>';
?>
<form action="" method="POST" name="wpeasycart_admin_setup_wizard_form" id="wpeasycart_admin_setup_wizard_form" novalidate="novalidate" class="ecwz-form">
	<?php wp_easycart_admin_verification()->print_nonce_field( 'wp_easycart_nonce', 'wp-easycart-process-wizard-payments' ); ?>
	<input type="hidden" name="ec_admin_form_action" id="ec_admin_form_action" value="process-wizard-payments">
	<?php if ( $gw['paypal'] ) { ?><input type="hidden" name="paypal_standard" value="1"><?php } ?>
	<?php if ( $gw['stripe'] ) { ?><input type="hidden" name="use_stripe" value="1"><?php } ?>
	<?php if ( $gw['square'] ) { ?><input type="hidden" name="use_square" value="1"><?php } ?>

	<div class="ecwz-body">
		<h2><?php esc_html_e( 'How will customers pay?', 'wp-easycart' ); ?></h2>
		<p class="ecwz-lede"><?php echo sprintf( esc_html__( 'Turn on manual payments now, or connect a gateway in a couple of clicks. More gateways can be added any time under %s.', 'wp-easycart' ), '<a href="' . esc_url( $payment_settings ) . '">' . esc_html__( 'Settings › Payment', 'wp-easycart' ) . '</a>' ); ?></p>

		<?php if ( $flash ) { ?>
		<div class="ecwz-note <?php echo isset( $_GET['error'] ) ? 'ecwz-note-amber' : 'ecwz-note-brand'; ?>">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg>
			<div><?php echo esc_html( $flash ); ?></div>
		</div>
		<?php } ?>

		<?php if ( $gate ) { ?>
		<div class="ecwz-terms" id="ecwz_terms_note">
			<div class="ecwz-terms-head">
				<b><?php esc_html_e( 'EasyCart Connect terms', 'wp-easycart' ); ?></b>
				<span class="ecwz-badge ecwz-badge-amber"><?php esc_html_e( 'Required for PayPal, Stripe and Square', 'wp-easycart' ); ?></span>
			</div>
			<label class="ecwz-check-row">
				<input type="checkbox" name="accept_terms" id="ecwz_accept_terms" value="1">
				<span><?php echo sprintf( esc_html__( 'I agree to the WP EasyCart %1$sterms and privacy policy%2$s. The Free edition includes unlimited products, orders and accounts plus manual payments. PayPal, Stripe and Square are available through EasyCart Connect with a 2%% fee; Pro removes fees and unlocks 30+ gateways.', 'wp-easycart' ), '<a href="https://www.wpeasycart.com/terms-and-conditions/" target="_blank" rel="noopener noreferrer">', '</a>' ); ?></span>
			</label>
		</div>
		<?php } ?>

		<div class="ecwz-cards" id="ecwz_pay_cards">

			<div class="ecwz-ccard<?php echo $gw['manual'] ? ' is-on' : ''; ?>" data-pay="manual">
				<div class="ecwz-ccard-top"><span class="ecwz-ico" style="background:var(--ecsh-g700,#374151)">$</span><div><h4><?php esc_html_e( 'Manual payments', 'wp-easycart' ); ?></h4><span class="ecwz-sub"><?php esc_html_e( 'Check, direct deposit, pay on pickup', 'wp-easycart' ); ?></span></div></div>
				<p><?php esc_html_e( 'Customers complete the order and pay you offline. You write the instructions shown at checkout. No fees.', 'wp-easycart' ); ?></p>
				<div class="ecwz-ccard-act">
					<span class="ecwz-badge ecwz-badge-green">&#10003; <?php esc_html_e( 'No fees', 'wp-easycart' ); ?></span>
					<label class="ecwz-tg"><input type="checkbox" name="manual_billing" id="wp_easycart_manual_billing" value="1"<?php checked( $gw['manual'] ); ?>><span></span></label>
				</div>
			</div>

			<div class="ecwz-ccard<?php echo $gw['paypal'] ? ' is-on' : ''; ?>" data-pay="paypal">
				<div class="ecwz-ccard-top"><span class="ecwz-ico" style="background:#003087">PP</span><div><h4>PayPal</h4><span class="ecwz-sub"><?php esc_html_e( 'No SSL certificate required', 'wp-easycart' ); ?></span></div></div>
				<p><?php esc_html_e( 'Redirects the customer to PayPal to pay. Connects in a few seconds with your PayPal login.', 'wp-easycart' ); ?></p>
				<div class="ecwz-ccard-act">
					<?php if ( $gw['paypal'] ) { ?>
					<span class="ecwz-badge ecwz-badge-green">&#10003; <?php esc_html_e( 'Connected', 'wp-easycart' ); ?></span>
					<a class="ecwz-btn ecwz-btn-sm ecwz-btn-ghost" href="<?php echo esc_url( $payment_settings ); ?>"><?php esc_html_e( 'Manage', 'wp-easycart' ); ?></a>
					<?php } else { ?>
					<span class="ecwz-badge ecwz-badge-gray"><?php echo $upsell ? esc_html__( '2% fee on Free', 'wp-easycart' ) : esc_html__( 'No EasyCart fees', 'wp-easycart' ); ?></span>
					<a class="ecwz-btn ecwz-btn-sm ecwz-connect" href="<?php echo $gate ? '#' : esc_url( $paypal_url ); ?>" data-href="<?php echo esc_url( $paypal_url ); ?>"<?php if ( $gate ) { echo ' aria-disabled="true"'; } ?>><?php esc_html_e( 'Connect', 'wp-easycart' ); ?></a>
					<?php } ?>
				</div>
			</div>

			<div class="ecwz-ccard<?php echo $gw['stripe'] ? ' is-on' : ''; ?>" data-pay="stripe">
				<div class="ecwz-ccard-top"><span class="ecwz-ico" style="background:#635bff">S</span><div><h4>Stripe</h4><span class="ecwz-sub"><?php esc_html_e( 'Cards, Apple Pay, Google Pay', 'wp-easycart' ); ?></span></div></div>
				<p><?php esc_html_e( 'Cards are entered on your checkout page.', 'wp-easycart' ); ?> <?php echo $ssl_badge; ?></p>
				<div class="ecwz-ccard-act">
					<?php if ( $gw['stripe'] ) { ?>
					<span class="ecwz-badge ecwz-badge-green">&#10003; <?php esc_html_e( 'Connected', 'wp-easycart' ); ?></span>
					<a class="ecwz-btn ecwz-btn-sm ecwz-btn-ghost" href="<?php echo esc_url( $payment_settings ); ?>"><?php esc_html_e( 'Manage', 'wp-easycart' ); ?></a>
					<?php } else { ?>
					<span class="ecwz-badge ecwz-badge-gray"><?php echo $upsell ? esc_html__( '2% fee on Free', 'wp-easycart' ) : esc_html__( 'No EasyCart fees', 'wp-easycart' ); ?></span>
					<a class="ecwz-btn ecwz-btn-sm ecwz-connect" href="<?php echo $gate ? '#' : esc_url( $stripe_url ); ?>" data-href="<?php echo esc_url( $stripe_url ); ?>"<?php if ( $gate ) { echo ' aria-disabled="true"'; } ?><?php if ( ! $env['https'] ) { echo ' title="' . esc_attr__( 'Requires https', 'wp-easycart' ) . '"'; } ?>><?php esc_html_e( 'Connect', 'wp-easycart' ); ?></a>
					<?php } ?>
				</div>
			</div>

			<div class="ecwz-ccard<?php echo $gw['square'] ? ' is-on' : ''; ?>" data-pay="square">
				<div class="ecwz-ccard-top"><span class="ecwz-ico" style="background:#000">&#9634;</span><div><h4>Square</h4><span class="ecwz-sub"><?php esc_html_e( 'Sync with your Square POS', 'wp-easycart' ); ?></span></div></div>
				<p><?php esc_html_e( 'Accept cards on your site and keep inventory in step with Square.', 'wp-easycart' ); ?> <?php echo $ssl_badge; ?></p>
				<div class="ecwz-ccard-act">
					<?php if ( $gw['square'] ) { ?>
					<span class="ecwz-badge ecwz-badge-green">&#10003; <?php esc_html_e( 'Connected', 'wp-easycart' ); ?></span>
					<a class="ecwz-btn ecwz-btn-sm ecwz-btn-ghost" href="<?php echo esc_url( $payment_settings ); ?>"><?php esc_html_e( 'Manage', 'wp-easycart' ); ?></a>
					<?php } else { ?>
					<span class="ecwz-badge ecwz-badge-gray"><?php echo $upsell ? esc_html__( '2% fee on Free', 'wp-easycart' ) : esc_html__( 'No EasyCart fees', 'wp-easycart' ); ?></span>
					<a class="ecwz-btn ecwz-btn-sm ecwz-connect" href="<?php echo $gate ? '#' : esc_url( $square_url ); ?>" data-href="<?php echo esc_url( $square_url ); ?>"<?php if ( $gate ) { echo ' aria-disabled="true"'; } ?><?php if ( ! $env['https'] ) { echo ' title="' . esc_attr__( 'Requires https', 'wp-easycart' ) . '"'; } ?>><?php esc_html_e( 'Connect', 'wp-easycart' ); ?></a>
					<?php } ?>
				</div>
			</div>
		</div>

		<?php if ( $upsell ) { ?>
		<div class="ecwz-hint ecwz-upsell-row">
			<span class="ecwz-badge ecwz-badge-amber">PRO</span>
			<?php esc_html_e( 'Authorize.net, Braintree, Mollie, Klarna and 30+ more.', 'wp-easycart' ); ?>
			<a href="https://docs.wpeasycart.com/wp-easycart-administrative-console-guide/?section=payment" target="_blank" rel="noopener noreferrer" class="ecwz-lnk"><?php esc_html_e( 'See the list', 'wp-easycart' ); ?></a> ·
			<a href="admin.php?page=wp-easycart-registration&ec_trial=start" target="_blank" class="ecwz-lnk"><?php esc_html_e( 'Start 14-day trial', 'wp-easycart' ); ?></a>
		</div>
		<?php } ?>

		<h3 class="ecwz-sec"><?php esc_html_e( 'Checkout', 'wp-easycart' ); ?></h3>
		<div class="ecwz-frow ecwz-frow-first ecwz-frow-tight">
			<div class="ecwz-lab"><?php esc_html_e( 'Guest checkout', 'wp-easycart' ); ?><small><?php esc_html_e( 'Customers can buy without creating an account. Recommended: requiring an account is the single biggest cause of abandoned checkouts.', 'wp-easycart' ); ?></small></div>
			<div class="ecwz-val ecwz-val-toggle">
				<label class="ecwz-tg-row">
					<span class="ecwz-tg"><input type="checkbox" name="allow_guest" id="ecwz_allow_guest" value="1"<?php checked( false !== get_option( 'ec_option_allow_guest' ) ? (bool) get_option( 'ec_option_allow_guest' ) : true ); ?>><span></span></span>
					<span class="ecwz-t"><?php esc_html_e( 'Allow guest checkout', 'wp-easycart' ); ?></span>
				</label>
				<div class="ecwz-hint"><?php echo sprintf( esc_html__( 'Customers can still create an account after ordering. Phone, company name and address requirements are under %s.', 'wp-easycart' ), '<a href="admin.php?page=wp-easycart-settings&subpage=checkout" class="ecwz-lnk">' . esc_html__( 'Settings › Checkout', 'wp-easycart' ) . '</a>' ); ?></div>
			</div>
		</div>
	</div>

	<?php $wizard->render_footer( wp_easycart_admin_setup_wizard::STEP_PAYMENTS ); ?>
</form>