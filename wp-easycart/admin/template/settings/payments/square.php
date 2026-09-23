<?php
/**
 * Square ( free edition ): the gateway's settings, loaded into the Payments drawer ( wp_easycart_admin_payment_v2 ).
 * WP EasyCart PRO replaces this file with its own ( sync, webhooks, digital wallets ).
 *
 * 6.0.1: laid out as the drawer's groups ( Payments, Checkout ) like the PRO panel, with every option in view instead of
 * behind "Advanced Options". Element ids are unchanged: admin/js/payment.js saves from them. The switches save as they
 * change; the checkout options save from the drawer's Save button ( the hidden ec_admin_save_square_options() button ).
 *
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Plan name for the locked option below: the store's own plan, or Pro/Premium when no license is known. */
$wpec_plan_name = class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::plan_name() : __( 'Pro/Premium', 'wp-easycart' );
$ecsq_active    = ( 'square' === get_option( 'ec_option_payment_process_method' ) );
$ecsq_sandbox   = (bool) get_option( 'ec_option_square_is_sandbox' );
$ecsq_has_keys  = ( '' !== (string) get_option( 'ec_option_square_access_token' ) || '' !== (string) get_option( 'ec_option_square_sandbox_access_token' ) );
$ecsq_state     = wp_create_nonce( 'wp-easycart-square' );
$ecsq_return    = rawurlencode( esc_url_raw( admin_url() ) . '?ec_admin_form_action=handle-square' );
?>
<div class="ec_admin_square_row ecsq">
	<div class="ec_admin_slider_row">
		<?php wp_easycart_admin()->preloader->print_preloader( 'ec_admin_square_display_loader' ); ?>
		<div class="ec_admin_slider_row_description">
			<div><?php esc_html_e( 'Square offers the ability to pay with a credit card directly on your website. Adding Square gives your shopping cart a more professional look and increases conversions.', 'wp-easycart' ); ?></div>
			<?php if ( $ecsq_active ) { ?>
				<div class="ecsq-actions">
					<a href="admin.php?page=wp-easycart-settings&amp;subpage=cart-importer" target="_blank"><?php esc_html_e( 'Import products from Square', 'wp-easycart' ); ?></a>
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=wp-easycart-settings&subpage=payment&ec_admin_form_action=square-renew' ), 'wp-easycart-payment-square-renew' ) ); ?>"><?php esc_html_e( 'Renew access', 'wp-easycart' ); ?></a>
					<a class="ecsq-danger" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=wp-easycart-settings&subpage=payment&ec_admin_form_action=square-disconnect' ), 'wp-easycart-payment-square-disconnect' ) ); ?>"><?php esc_html_e( 'Disconnect', 'wp-easycart' ); ?></a>
				</div>
			<?php } ?>
			<input type="hidden" name="use_square" id="use_square" value="<?php echo $ecsq_active ? 1 : 0; ?>" />
		</div>

		<div class="ecsq-group">
			<div class="ecsq-group-t"><?php esc_html_e( 'Payments', 'wp-easycart' ); ?></div>
			<div class="ec_admin_toggles_wrap">
				<div class="ec_admin_toggle">
					<span><?php esc_html_e( 'Take live payments', 'wp-easycart' ); ?><small><?php esc_html_e( 'Connects your Square account and charges real cards.', 'wp-easycart' ); ?></small></span>
					<?php if ( ! $ecsq_active || $ecsq_sandbox ) { ?><a href="https://connect.wpeasycart.com/square-v2/?url=<?php echo esc_attr( $ecsq_return ); ?>&amp;state=<?php echo esc_attr( $ecsq_state ); ?>"><span></span><?php } ?>
					<label class="ec_admin_switch">
						<input type="checkbox" onclick="return square_on_off( );" class="ec_admin_slider_checkbox" value="1" id="ec_option_square_enable"<?php checked( $ecsq_active && ! $ecsq_sandbox ); ?> aria-label="<?php esc_attr_e( 'Take live payments', 'wp-easycart' ); ?>">
						<span class="ec_admin_slider round"></span>
					</label>
					<?php if ( ! $ecsq_active || $ecsq_sandbox ) { ?></a><?php } ?>
				</div>
				<div class="ec_admin_toggle" style="display:none">
					<span><?php esc_html_e( 'Sandbox ( test payments )', 'wp-easycart' ); ?></span>
					<?php if ( ! $ecsq_active || ! $ecsq_sandbox ) { ?><a href="https://connect.wpeasycart.com/square-sandbox/?url=<?php echo esc_attr( $ecsq_return ); ?>&amp;state=<?php echo esc_attr( $ecsq_state ); ?>"><span></span><?php } ?>
					<label class="ec_admin_switch">
						<input type="checkbox" onclick="return square_on_off( );" class="ec_admin_slider_checkbox" value="1" id="ec_option_square_enable_sandbox"<?php checked( $ecsq_active && $ecsq_sandbox ); ?>>
						<span class="ec_admin_slider round"></span>
					</label>
					<?php if ( ! $ecsq_active || ! $ecsq_sandbox ) { ?></a><?php } ?>
				</div>
			</div>
		</div>

		<?php if ( $ecsq_has_keys ) { ?>
			<div class="ecsq-group" id="ec_square_checkout">
				<div class="ecsq-group-t"><?php esc_html_e( 'Checkout', 'wp-easycart' ); ?></div>
				<?php
				$ecsq_location = (string) get_option( $ecsq_sandbox ? 'ec_option_square_sandbox_location_id' : 'ec_option_square_location_id' );
				if ( class_exists( 'ec_square' ) ) {
					$square           = new ec_square();
					$square_locations = $square->get_locations();
					?>
					<div class="ecsq-field">
						<label for="ec_option_square_location_id"><?php esc_html_e( 'Store location', 'wp-easycart' ); ?></label>
						<select name="ec_option_square_location_id" id="ec_option_square_location_id" onchange="ec_admin_save_square_options( );">
							<option value="0"><?php esc_html_e( 'Your default Square location', 'wp-easycart' ); ?></option>
							<?php if ( is_array( $square_locations ) && isset( $square_locations[0] ) && isset( $square_locations[0]->id ) ) { ?>
								<?php foreach ( $square_locations as $location ) { ?>
									<option value="<?php echo esc_attr( $location->id ); ?>"<?php selected( $location->id, $ecsq_location ); ?> data-country="<?php echo esc_attr( $location->country ); ?>"><?php echo esc_html( $location->name ); ?></option>
								<?php } ?>
							<?php } else { ?>
								<option value="0" disabled><?php esc_html_e( 'Could not load your Square locations', 'wp-easycart' ); ?></option>
							<?php } ?>
						</select>
						<small><?php esc_html_e( 'Payments are recorded against this location in Square.', 'wp-easycart' ); ?></small>
					</div>
				<?php } ?>
				<?php /* Wallets need WP EasyCart PRO: the free panel saves them off, as its old select did. */ ?>
				<input type="hidden" id="ec_option_square_digital_wallet" name="ec_option_square_digital_wallet" value="0" />
				<div class="ec_admin_toggles_wrap">
					<div class="ec_admin_toggle ecsq-locked">
						<span><?php esc_html_e( 'Apple Pay, Google Pay and Microsoft Pay', 'wp-easycart' ); ?><small><?php /* translators: %s: plan name, Pro or Premium ( Pro/Premium when no license is known ). */ echo esc_html( sprintf( __( 'Shoppers pay with the wallet on their phone or browser. Available with %s.', 'wp-easycart' ), $wpec_plan_name ) ); ?></small></span>
						<span class="ecsq-pro-chip"><?php echo esc_html( class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::badge( 'pro' ) : 'Pro' ); ?></span>
					</div>
				</div>
				<div class="ecsq-field">
					<label for="ec_option_square_merchant_name"><?php esc_html_e( 'Merchant name', 'wp-easycart' ); ?></label>
					<input type="text" name="ec_option_square_merchant_name" id="ec_option_square_merchant_name" value="<?php echo esc_attr( get_option( 'ec_option_square_merchant_name' ) ); ?>" placeholder="<?php esc_attr_e( 'Your Company Name', 'wp-easycart' ); ?>" onchange="ec_admin_save_square_options( );" />
					<small><?php esc_html_e( 'The business name Square shows the shopper.', 'wp-easycart' ); ?></small>
				</div>
				<?php /* The drawer's Save button runs this ( one ec_admin_save_ button = footer save ); hidden by the drawer. */ ?>
				<input type="button" onclick="ec_admin_save_square_options( );" class="ecsq-save" value="<?php esc_attr_e( 'Save', 'wp-easycart' ); ?>" />
			</div>
		<?php } ?>
	</div>
</div>
