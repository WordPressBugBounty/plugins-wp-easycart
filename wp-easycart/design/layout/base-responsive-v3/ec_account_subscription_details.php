<?php
/**
 * Account > Subscription details.
 *
 * 6.0.0: card layout. Actions only render when ec_subscription::customer_can() allows them for the
 * subscription's status and gateway ( the same check refuses the change server-side ). The class names the
 * storefront script toggles are unchanged: .ec_account_subscription_details_card_change,
 * .ec_account_subscription_details_plan_change, .ec_account_subscription_upgrade_row and
 * .ec_account_subscription_details_payment_form, plus the #ec_submit_update_form form.
 *
 * Available: $this ( ec_accountpage ), $this->subscription ( ec_subscription ), $GLOBALS['ec_user'].
 */
$ec_sub             = $this->subscription;
$ec_sub_status      = $ec_sub->get_status_key();
$ec_sub_ended       = $ec_sub->is_canceled();
$ec_sub_can_payment = $ec_sub->can_update_payment_method();
$ec_sub_can_plan    = $ec_sub->can_change_plan();
$ec_sub_can_cancel  = $ec_sub->can_cancel();
$ec_sub_details     = $ec_sub->get_purchase_details();
$ec_sub_price       = $ec_sub->get_price_parts();
$ec_sub_image       = $ec_sub->get_image_url();
$ec_sub_next        = $ec_sub->get_next_payment_timestamp();
$ec_sub_last        = $ec_sub->get_last_payment_timestamp();
$ec_sub_start       = $ec_sub->get_start_timestamp();
$ec_sub_payments    = ( is_array( $ec_sub->past_payments ) ) ? $ec_sub->past_payments : array();
$ec_sub_has_options = ( $ec_sub_details && ( count( $ec_sub_details['options'] ) > 0 || $ec_sub_details['signup_fee'] > 0 ) );
$ec_sub_order_ids   = array();
foreach ( $ec_sub_payments as $ec_sub_payment ) {
	$ec_sub_order_ids[] = (int) $ec_sub_payment->order_id;
}
$ec_sub_title = wp_easycart_language()->convert_text( $ec_sub->title );
?>
<section class="ec_account_page ec_account_subscription_v2 ec_account_subscription_status_<?php echo esc_attr( $ec_sub_status ); ?>" id="ec_account_subscription_details">

	<div class="ec_account_subscription_v2_back">
		<a href="<?php echo esc_url( wpeasycart_links()->get_account_page( 'subscriptions' ) ); ?>">&larr; <?php echo wp_easycart_escape_html( ec_subscription::get_text( 'subscription_details_back', 'Back to subscriptions' ) ); ?></a>
	</div>

	<?php do_action( 'wpeasycart_account_subscription_details_before', $ec_sub ); ?>

	<?php if ( $ec_sub_ended ) { ?>
	<div class="ec_account_subscription_v2_notice is-ended"><?php echo wp_easycart_escape_html( ec_subscription::get_text( 'subscription_details_ended_notice', 'This subscription has ended. The payment method and plan can no longer be changed.' ) ); ?></div>
	<?php } else if ( 'canceling' == $ec_sub_status ) { ?>
	<div class="ec_account_subscription_v2_notice is-canceling"><?php echo wp_easycart_escape_html( ec_subscription::get_text( 'subscription_details_canceling_notice', 'This subscription will not renew. It stays active until the end of the current billing period.' ) ); ?></div>
	<?php } else if ( 'past_due' == $ec_sub_status && $ec_sub_can_payment ) { ?>
	<div class="ec_account_subscription_v2_notice is-past-due"><?php echo wp_easycart_escape_html( ec_subscription::get_text( 'subscription_details_past_due_notice', 'Your last payment did not go through. Update your payment method to keep this subscription active.' ) ); ?></div>
	<?php } ?>

	<div class="ec_account_subscription_v2_grid">

		<div class="ec_account_subscription_v2_card ec_account_subscription_v2_summary">
			<div class="ec_account_subscription_v2_product">
				<div class="ec_account_subscription_v2_media">
					<?php if ( '' != $ec_sub_image ) { ?>
					<img src="<?php echo esc_url( $ec_sub_image ); ?>" alt="<?php echo esc_attr( wp_strip_all_tags( $ec_sub_title ) ); ?>" />
					<?php } else { ?>
					<span class="ec_account_subscription_v2_media_placeholder" aria-hidden="true"><?php echo esc_html( function_exists( 'mb_substr' ) ? mb_substr( wp_strip_all_tags( $ec_sub_title ), 0, 1 ) : substr( wp_strip_all_tags( $ec_sub_title ), 0, 1 ) ); ?></span>
					<?php } ?>
				</div>
				<div class="ec_account_subscription_v2_heading">
					<span class="ec_account_subscription_v2_badge is-<?php echo esc_attr( $ec_sub_status ); ?>"><?php echo wp_easycart_escape_html( $ec_sub->get_status_label() ); ?></span>
					<h3 class="ec_account_subscription_title"><?php echo wp_easycart_escape_html( $ec_sub_title ); ?></h3>
					<div class="ec_account_subscription_v2_price">
						<span class="ec_account_subscription_v2_amount"><?php echo esc_html( $ec_sub_price['amount'] ); ?></span><span class="ec_account_subscription_v2_period"><?php echo esc_html( $ec_sub_price['period'] ); ?></span>
					</div>
					<?php if ( (int) $ec_sub->quantity > 1 ) { ?>
					<div class="ec_account_subscription_v2_quantity"><?php echo wp_easycart_escape_html( ec_subscription::get_text( 'subscription_details_quantity', 'Quantity' ) ); ?>: <?php echo (int) $ec_sub->quantity; ?></div>
					<?php } ?>
				</div>
			</div>

			<dl class="ec_account_subscription_v2_facts">
				<?php if ( $ec_sub_next && in_array( $ec_sub_status, array( 'active', 'trialing', 'past_due', 'incomplete' ), true ) ) { ?>
				<div class="ec_account_subscription_v2_fact ec_account_subscription_row_next_bill">
					<dt><?php echo wp_easycart_escape_html( wp_easycart_language()->get_text( 'account_subscriptions', 'subscription_details_next_billing' ) ); ?></dt>
					<dd><?php echo esc_html( ec_subscription::format_date( $ec_sub_next ) ); ?></dd>
				</div>
				<?php } else if ( $ec_sub_next && 'canceling' == $ec_sub_status ) { ?>
				<div class="ec_account_subscription_v2_fact">
					<dt><?php echo wp_easycart_escape_html( ec_subscription::get_text( 'subscription_details_ends', 'Ends' ) ); ?></dt>
					<dd><?php echo esc_html( ec_subscription::format_date( $ec_sub_next ) ); ?></dd>
				</div>
				<?php } else if ( $ec_sub_ended && $ec_sub_next && $ec_sub_next <= time() ) { ?>
				<div class="ec_account_subscription_v2_fact">
					<dt><?php echo wp_easycart_escape_html( ec_subscription::get_text( 'subscription_details_ended', 'Ended' ) ); ?></dt>
					<dd><?php echo esc_html( ec_subscription::format_date( $ec_sub_next ) ); ?></dd>
				</div>
				<?php } ?>
				<?php if ( $ec_sub_last ) { ?>
				<div class="ec_account_subscription_v2_fact">
					<dt><?php echo wp_easycart_escape_html( wp_easycart_language()->get_text( 'account_subscriptions', 'subscription_details_last_payment' ) ); ?></dt>
					<dd><?php echo esc_html( ec_subscription::format_date( $ec_sub_last ) ); ?></dd>
				</div>
				<?php } ?>
				<?php if ( $ec_sub_start ) { ?>
				<div class="ec_account_subscription_v2_fact">
					<dt><?php echo wp_easycart_escape_html( ec_subscription::get_text( 'subscription_details_started', 'Started' ) ); ?></dt>
					<dd><?php echo esc_html( ec_subscription::format_date( $ec_sub_start ) ); ?></dd>
				</div>
				<?php } ?>
			</dl>

			<?php if ( $ec_sub->has_membership_page() && ! $ec_sub_ended ) { ?>
			<div class="ec_account_subscription_v2_membership"><?php $ec_sub->display_membership_page_link( wp_easycart_language()->get_text( 'cart_success', 'cart_payment_complete_line_5' ) ); ?></div>
			<?php } ?>
		</div>

		<div class="ec_account_subscription_v2_side">
			<div class="ec_account_subscription_v2_card ec_account_subscription_v2_payment">
				<h4 class="ec_account_subscription_v2_card_title"><?php echo wp_easycart_escape_html( wp_easycart_language()->get_text( 'account_subscriptions', 'subscription_details_current_billing' ) ); ?></h4>
				<div class="ec_account_subscription_v2_pm ec_account_subscription_details_card">
					<?php if ( '' != $GLOBALS['ec_user']->last4 ) { ?>
					<span class="ec_account_subscription_v2_brand"><?php $GLOBALS['ec_user']->display_card_type(); ?></span>
					<span class="ec_account_subscription_v2_last4">&bull;&bull;&bull;&bull; <?php $GLOBALS['ec_user']->display_last4(); ?></span>
					<?php } else { ?>
					<span class="ec_account_subscription_v2_muted"><?php echo wp_easycart_escape_html( ec_subscription::get_text( 'subscription_details_no_card', 'No card on file' ) ); ?></span>
					<?php } ?>
				</div>
				<?php if ( $ec_sub_can_payment ) { ?>
				<a href="#" role="button" class="ec_account_subscription_details_card_change ec_account_subscription_v2_button" aria-controls="ec_account_subscription_v2_payment_panel" aria-expanded="false" onclick="return ec_show_update_subscription_payment();"><?php echo wp_easycart_escape_html( wp_easycart_language()->get_text( 'cart_payment_information', 'cart_change_payment_method' ) ); ?></a>
				<?php } ?>
			</div>

			<?php if ( $ec_sub_can_plan || $ec_sub_can_cancel ) { ?>
			<div class="ec_account_subscription_v2_card ec_account_subscription_v2_manage">
				<h4 class="ec_account_subscription_v2_card_title"><?php echo wp_easycart_escape_html( ec_subscription::get_text( 'subscription_details_manage', 'Manage subscription' ) ); ?></h4>
				<?php if ( $ec_sub_can_plan ) { ?>
				<a href="#" role="button" class="ec_account_subscription_details_plan_change ec_account_subscription_v2_button" aria-controls="ec_account_subscription_v2_plan_panel" aria-expanded="false" onclick="return ec_show_update_subscription_details();"><?php echo wp_easycart_escape_html( wp_easycart_language()->get_text( 'account_subscriptions', 'subscription_details_change_plan' ) ); ?></a>
				<?php } ?>
				<?php if ( $ec_sub_can_cancel ) { ?>
				<div class="ec_account_subscription_details_cancel">
					<div class="ec_account_subscription_details_cancel_button"><?php $ec_sub->display_cancel_form( wp_easycart_language()->get_text( 'account_subscriptions', 'cancel_subscription_button' ), wp_easycart_language()->get_text( 'account_subscriptions', 'cancel_subscription_confirm_text' ) ); ?></div>
				</div>
				<?php } ?>
			</div>
			<?php } ?>
		</div>

		<?php if ( $ec_sub_can_payment || $ec_sub_can_plan ) { ?>
		<?php $ec_sub_update_nonce = wp_create_nonce( 'wp-easycart-get-stripe-update-customer-card-' . $GLOBALS['ec_cart_data']->ec_cart_id ); ?>
		<div class="ec_account_subscription_details_form_container ec_account_subscription_v2_panels" id="ec_account_subscription_v2_panels">
			<?php $this->display_subscription_update_form_start(); ?>

			<?php if ( $ec_sub_can_plan ) { ?>
			<?php $ec_sub_choices = $ec_sub->get_plan_choices(); ?>
			<div class="ec_account_subscription_upgrade_row ec_account_subscription_v2_card ec_account_subscription_v2_panel" id="ec_account_subscription_v2_plan_panel" role="region" aria-labelledby="ec_account_subscription_v2_plan_heading" data-current-plan="<?php echo esc_attr( (int) $ec_sub->product_id ); ?>" data-current-quantity="<?php echo esc_attr( max( 1, (int) $ec_sub->quantity ) ); ?>">
				<h4 class="ec_account_subscription_v2_card_title ec_account_subscription_v2_panel_title" id="ec_account_subscription_v2_plan_heading" tabindex="-1"><?php echo wp_easycart_escape_html( wp_easycart_language()->get_text( 'account_subscriptions', 'subscription_details_change_plan' ) ); ?></h4>

				<input type="hidden" name="ec_selected_plan" id="ec_selected_plan" value="<?php echo esc_attr( (int) $ec_sub->product_id ); ?>" />

				<?php if ( count( $ec_sub_choices ) > 0 ) { ?>
				<fieldset class="ec_account_subscription_v2_plans">
					<legend class="ec_account_subscription_v2_sr"><?php echo wp_easycart_escape_html( wp_easycart_language()->get_text( 'account_subscriptions', 'subscription_details_change_plan' ) ); ?></legend>
					<?php foreach ( $ec_sub_choices as $ec_sub_choice ) { ?>
					<label class="ec_account_subscription_v2_plan<?php echo ( $ec_sub_choice['current'] ) ? ' is-current is-selected' : ''; ?>">
						<input type="radio" name="ec_account_subscription_plan_choice" value="<?php echo esc_attr( $ec_sub_choice['product_id'] ); ?>"<?php checked( $ec_sub_choice['current'] ); ?> />
						<span class="ec_account_subscription_v2_plan_text">
							<span class="ec_account_subscription_v2_plan_name"><?php echo wp_easycart_escape_html( $ec_sub_choice['title'] ); ?></span>
							<span class="ec_account_subscription_v2_plan_price"><?php echo esc_html( $ec_sub_choice['amount'] ); ?><span class="ec_account_subscription_v2_period"><?php echo esc_html( $ec_sub_choice['period'] ); ?></span></span>
						</span>
						<?php if ( $ec_sub_choice['current'] ) { ?>
						<span class="ec_account_subscription_v2_plan_tag"><?php echo wp_easycart_escape_html( ec_subscription::get_text( 'subscription_details_current_plan', 'Current plan' ) ); ?></span>
						<?php } ?>
					</label>
					<?php } ?>
				</fieldset>
				<?php } ?>

				<?php if ( ! get_option( 'ec_option_subscription_one_only' ) ) { ?>
				<div class="ec_account_subscription_v2_field">
					<label class="ec_account_subscription_v2_label" for="ec_quantity_<?php echo esc_attr( (int) $ec_sub->subscription_id ); ?>"><?php echo wp_easycart_escape_html( ec_subscription::get_text( 'subscription_details_quantity', 'Quantity' ) ); ?></label>
					<div class="ec_account_subscription_v2_stepper">
						<button type="button" class="ec_account_subscription_v2_step" data-ec-sub-step="-1" aria-controls="ec_quantity_<?php echo esc_attr( (int) $ec_sub->subscription_id ); ?>" aria-label="<?php echo esc_attr( ec_subscription::get_text( 'subscription_details_decrease_quantity', 'Decrease quantity' ) ); ?>"<?php disabled( (int) $ec_sub->quantity <= 1 ); ?>>&minus;</button>
						<input type="number" value="<?php echo esc_attr( max( 1, (int) $ec_sub->quantity ) ); ?>" id="ec_quantity_<?php echo esc_attr( (int) $ec_sub->subscription_id ); ?>" name="ec_quantity" autocomplete="off" step="1" min="1" max="1000000" inputmode="numeric" class="ec_quantity ec_account_subscription_v2_qty" aria-describedby="ec_account_subscription_v2_qty_error" />
						<button type="button" class="ec_account_subscription_v2_step" data-ec-sub-step="1" aria-controls="ec_quantity_<?php echo esc_attr( (int) $ec_sub->subscription_id ); ?>" aria-label="<?php echo esc_attr( ec_subscription::get_text( 'subscription_details_increase_quantity', 'Increase quantity' ) ); ?>">+</button>
					</div>
					<div class="ec_account_subscription_v2_field_error ec_account_subscription_v2_qty_error" id="ec_account_subscription_v2_qty_error" role="alert" hidden><?php echo wp_easycart_escape_html( ec_subscription::get_text( 'subscription_details_quantity_invalid', 'Enter a quantity of 1 or more.' ) ); ?></div>
				</div>
				<?php } ?>

				<div class="ec_account_subscription_details_notice ec_account_subscription_v2_info"><?php echo wp_easycart_escape_html( wp_easycart_language()->get_text( 'account_subscriptions', 'subscription_details_notice' ) ); ?></div>

				<div class="ec_account_subscription_v2_actions">
					<input type="submit" class="ec_account_subscription_v2_primary" data-ec-sub-save="plan" disabled="disabled" value="<?php echo esc_attr( wp_easycart_language()->get_text( 'account_subscriptions', 'save_changes_button' ) ); ?>" onclick="return ( typeof ec_account_subscription_save_plan === 'function' ) ? ec_account_subscription_save_plan( this, <?php echo esc_attr( (int) $ec_sub->subscription_id ); ?>, '<?php echo esc_attr( $ec_sub_update_nonce ); ?>' ) : ec_update_subscription_info( <?php echo esc_attr( (int) $ec_sub->subscription_id ); ?>, '<?php echo esc_attr( $ec_sub_update_nonce ); ?>' );" />
					<button type="button" class="ec_account_subscription_v2_secondary" data-ec-sub-close="plan" onclick="if ( typeof ec_account_subscription_close_panel !== 'function' ) { jQuery( '.ec_account_subscription_upgrade_row' ).hide(); jQuery( '.ec_account_subscription_details_plan_change' ).show(); }"><?php echo wp_easycart_escape_html( ec_subscription::get_text( 'subscription_details_close', 'Close' ) ); ?></button>
				</div>
			</div>
			<?php } ?>

			<?php if ( $ec_sub_can_payment ) { ?>
			<div class="ec_account_subscription_details_payment_form ec_account_subscription_v2_card ec_account_subscription_v2_panel" id="ec_account_subscription_v2_payment_panel" role="region" aria-labelledby="ec_account_subscription_v2_payment_heading">
				<h4 class="ec_account_subscription_details_title ec_account_subscription_v2_card_title ec_account_subscription_v2_panel_title" id="ec_account_subscription_v2_payment_heading" tabindex="-1"><?php echo wp_easycart_escape_html( wp_easycart_language()->get_text( 'account_subscriptions', 'subscription_details_payment_method' ) ); ?></h4>
				<div class="ec_account_subscription_details_address"></div>

				<div class="form-row ec_account_subscription_v2_field ec_account_subscription_v2_card_row">
					<span class="ec_account_subscription_v2_label" id="ec_account_subscription_v2_card_label"><?php echo wp_easycart_escape_html( ec_subscription::get_text( 'subscription_details_card_label', 'Card details' ) ); ?></span>
					<div id="ec_stripe_card_row" class="ec_account_subscription_v2_stripe" role="group" aria-labelledby="ec_account_subscription_v2_card_label">
						<!-- a Stripe Element will be inserted here. -->
					</div>

					<!-- Used to display form errors -->
					<div id="ec_card_errors" class="ec_account_subscription_v2_field_error" role="alert"></div>
				</div>

				<div id="stripe-success-cover" style="display:none; cursor:default; position:fixed; top:0; left:0; width:100%; height:100%; z-index:999999; background-color: rgba(0, 0, 0, 0.8); color:#FFF;">
					<style>
					@keyframes rotation{
						0%  { transform:rotate(0deg); }
						100%{ transform:rotate(359deg); }
					}
					</style>
					<div style='font-family: "HelveticaNeue", "HelveticaNeue-Light", "Helvetica Neue Light", helvetica, arial, sans-serif; font-size: 14px; text-align: center; -webkit-box-sizing: border-box; -moz-box-sizing: border-box; -ms-box-sizing: border-box; box-sizing: border-box; width: 350px; max-width: 90%; top: 50%; left: 50%; position: absolute; transform: translate( -50%, -50% ); cursor: pointer; text-align: center;'>
						<div class="paypal-checkout-loader">
							<div style="height: 30px; width: 30px; display: inline-block; box-sizing: content-box; opacity: 1; filter: alpha(opacity=100); -webkit-animation: rotation .7s infinite linear; -moz-animation: rotation .7s infinite linear; -o-animation: rotation .7s infinite linear; animation: rotation .7s infinite linear; border-left: 8px solid rgba(0, 0, 0, .2); border-right: 8px solid rgba(0, 0, 0, .2); border-bottom: 8px solid rgba(0, 0, 0, .2); border-top: 8px solid #fff; border-radius: 100%;"></div>
						</div>
					</div>
				</div>
				<script><?php
					if ( get_option( 'ec_option_payment_process_method' ) == 'stripe' ) {
						$pkey = get_option( 'ec_option_stripe_public_api_key' );
					} else if ( get_option( 'ec_option_payment_process_method' ) == 'stripe_connect' && get_option( 'ec_option_stripe_connect_use_sandbox' ) ) {
						$pkey = get_option( 'ec_option_stripe_connect_sandbox_publishable_key' );
					} else {
						$pkey = get_option( 'ec_option_stripe_connect_production_publishable_key' );
					}
					$pkey = apply_filters( 'wp_easycart_stripe_connect_publishable_key', $pkey );
					$stripe_payment_intent_client_secret = $this->get_stripe_intent_client_secret();
					?>
					jQuery( document.getElementById( 'stripe-success-cover' ) ).appendTo( document.body );
					try {
						var clientSecret = '<?php echo esc_attr( $stripe_payment_intent_client_secret ); ?>';
						var stripe = Stripe( '<?php echo esc_attr( $pkey ); ?>' );
						var elements = stripe.elements( );
						var style = {
							base: {
								color: '#32325d',
								fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
								fontSmoothing: 'antialiased',
								fontSize: '16px',
								'::placeholder': {
								  color: '#aab7c4'
								}
							},
							invalid: {
								color: '#fa755a',
								iconColor: '#fa755a'
							}
						};
						var card = elements.create( 'card', {style: style} );
						card.mount( '#ec_stripe_card_row' );
						card.addEventListener( 'change', function( event ){
							var displayError = document.getElementById( 'ec_card_errors' );
							var saveButton = document.getElementById( 'ec_account_subscription_v2_card_save' );
							if( event.error ){
								displayError.textContent = event.error.message;
							}else{
								displayError.textContent = '';
							}
							jQuery( displayError ).show( );
							jQuery( document.getElementById( 'ec_stripe_card_row' ) ).toggleClass( 'is-invalid', !! event.error ).toggleClass( 'is-complete', !! event.complete );
							if ( saveButton ) {
								saveButton.disabled = ! event.complete;
							}
						} );
						var form = document.getElementById( 'ec_submit_update_form' );
						form.addEventListener( 'submit', function( event ){
							var payment_method = "credit_card";
							event.preventDefault( );
							var termsBox = document.getElementById( 'ec_terms_agree' );
							if ( termsBox && 'checkbox' === termsBox.type && ! termsBox.checked ) {
								jQuery( document.getElementById( 'ec_terms_error' ) ).show( );
								return;
							}
							jQuery( document.getElementById( 'ec_terms_error' ) ).hide( );
							var saveButton = document.getElementById( 'ec_account_subscription_v2_card_save' );
							if ( saveButton ) {
								saveButton.disabled = true;
							}
							jQuery( document.getElementById( 'ec_cart_submit_order' ) ).hide( );
							jQuery( document.getElementById( 'ec_cart_submit_order_working' ) ).show( );
							jQuery( document.getElementById( 'stripe-success-cover' ) ).show( );
							jQuery( document.getElementById( 'ec_stripe_dynamic_error' ) ).hide( );
							jQuery( document.getElementById( 'ec_card_errors' ) ).hide( );
							stripe.handleCardSetup( clientSecret, card ).then( function( result ){
								if( result.error ){
									var errorElement = document.getElementById( 'ec_card_errors' );
									errorElement.textContent = result.error.message;
									jQuery( errorElement ).show( );
									jQuery( document.getElementById( 'ec_stripe_card_row' ) ).addClass( 'is-invalid' );
									if ( saveButton ) {
										saveButton.disabled = false;
									}
									jQuery( document.getElementById( 'ec_submit_order_error' ) ).show( );
									jQuery( document.getElementById( 'ec_cart_submit_order' ) ).show( );
									jQuery( document.getElementById( 'ec_cart_submit_order_working' ) ).hide( );
									jQuery( document.getElementById( 'stripe-success-cover' ) ).hide( );
								}else{
									var data = {
										action: 'ec_ajax_get_stripe_update_customer_card',
										language: wpeasycart_ajax_object.current_language,
										subscription_id: <?php echo (int) $ec_sub->subscription_id; ?>,
										payment_id: result.setupIntent.payment_method,
										setup_intent_id: result.setupIntent.id,
										stripe_subscription_id: jQuery( document.getElementById( 'stripe_subscription_id' ) ).val( ),
										nonce: '<?php echo esc_attr( $ec_sub_update_nonce ); ?>'
									};
									jQuery.ajax({url: wpeasycart_ajax_object.ajax_url, type: 'post', data: data, success: function( result ){
										var json = JSON.parse( result );
										jQuery( location ).attr( 'href', json.url );
									} } );
								}
							} );
						} );
					}catch( err ){
						alert( "Your WP EasyCart with Stripe has a problem: " + err.message + ". Contact WP EasyCart for assistance." );
					}
				</script>

				<?php if ( get_option( 'ec_option_require_terms_agreement' ) ) { ?>
				<div class="ec_cart_input_row ec_agreement_section ec_account_subscription_v2_terms">
					<label for="ec_terms_agree"><input type="checkbox" name="ec_terms_agree" id="ec_terms_agree" value="1" onchange="if ( this.checked ) { jQuery( document.getElementById( 'ec_terms_error' ) ).hide( ); }" /> <?php echo wp_easycart_escape_html( wp_easycart_language()->get_text( 'cart_payment_information', 'cart_payment_review_agree' ) ); ?></label>
				</div>
				<?php } else { ?>
					<input type="hidden" name="ec_terms_agree" id="ec_terms_agree" value="2"  />
				<?php } ?>

				<div class="ec_cart_error_row ec_account_subscription_v2_field_error" id="ec_terms_error" role="alert">
					<?php echo wp_easycart_escape_html( wp_easycart_language()->get_text( 'cart_form_notices', 'cart_notice_payment_accept_terms' ) ); ?>
				</div>

				<div class="ec_account_subscription_details_notice ec_account_subscription_v2_info"><?php echo wp_easycart_escape_html( wp_easycart_language()->get_text( 'account_subscriptions', 'subscription_details_notice' ) ); ?></div>

				<div class="ec_account_subscription_v2_actions">
					<input type="submit" id="ec_account_subscription_v2_card_save" class="ec_account_subscription_v2_primary" disabled="disabled" value="<?php echo esc_attr( wp_easycart_language()->get_text( 'account_subscriptions', 'subscription_details_update_payment' ) ); ?>" onclick="return ec_check_update_subscription_info( );" />
					<button type="button" class="ec_account_subscription_v2_secondary" data-ec-sub-close="payment" onclick="if ( typeof ec_account_subscription_close_panel !== 'function' ) { jQuery( '.ec_account_subscription_details_payment_form' ).hide(); jQuery( '.ec_account_subscription_details_card_change' ).show(); }"><?php echo wp_easycart_escape_html( ec_subscription::get_text( 'subscription_details_close', 'Close' ) ); ?></button>
				</div>
			</div>
			<?php } ?>

			<?php $this->display_subscription_update_form_end(); ?>
		</div>
		<?php } ?>

		<?php if ( $ec_sub_has_options ) { ?>
		<div class="ec_account_subscription_v2_card ec_account_subscription_v2_selections">
			<h4 class="ec_account_subscription_v2_card_title"><?php echo wp_easycart_escape_html( ec_subscription::get_text( 'subscription_details_selections', 'Your selections' ) ); ?></h4>
			<?php if ( $ec_sub_details['plan_changed'] ) { ?>
			<div class="ec_account_subscription_v2_note"><?php echo wp_easycart_escape_html( str_replace( '%s', wp_easycart_language()->convert_text( $ec_sub_details['title'] ), ec_subscription::get_text( 'subscription_details_plan_changed', 'These are the selections from your original purchase of %s. Your plan has changed since then.' ) ) ); ?></div>
			<?php } ?>
			<ul class="ec_account_subscription_v2_options">
				<?php foreach ( $ec_sub_details['options'] as $ec_sub_option ) { ?>
				<li class="ec_account_subscription_v2_option is-<?php echo esc_attr( $ec_sub_option['type'] ); ?>">
					<span class="ec_account_subscription_v2_option_label"><?php echo ( '' != $ec_sub_option['label'] ) ? wp_easycart_escape_html( wp_easycart_language()->convert_text( $ec_sub_option['label'] ) ) : ''; ?></span>
					<span class="ec_account_subscription_v2_option_value"><?php echo esc_html( $ec_sub_option['value'] ); ?><?php if ( '' != $ec_sub_option['price_text'] ) { ?> <span class="ec_account_line_optionitem_pricing">(<?php echo esc_html( $ec_sub_option['price_text'] ); ?>)</span><?php } ?></span>
				</li>
				<?php } ?>
				<?php if ( $ec_sub_details['signup_fee'] > 0 ) { ?>
				<li class="ec_account_subscription_v2_option is-signup-fee">
					<span class="ec_account_subscription_v2_option_label"><?php echo wp_easycart_escape_html( ec_subscription::get_text( 'subscription_details_signup_fee', 'One-time sign-up fee' ) ); ?></span>
					<span class="ec_account_subscription_v2_option_value"><?php echo esc_html( $GLOBALS['currency']->get_currency_display( $ec_sub_details['signup_fee'] ) ); ?></span>
				</li>
				<?php } ?>
			</ul>
			<?php if ( in_array( (int) $ec_sub_details['order_id'], $ec_sub_order_ids, true ) ) { ?>
			<a class="ec_account_subscription_v2_link" href="<?php echo esc_url( wpeasycart_links()->get_account_page( 'order_details', array( 'order_id' => (int) $ec_sub_details['order_id'] ) ) ); ?>"><?php echo wp_easycart_escape_html( ec_subscription::get_text( 'subscription_details_view_original_order', 'View original order' ) ); ?></a>
			<?php } ?>
		</div>
		<?php } ?>

		<?php if ( count( $ec_sub_payments ) > 0 ) { ?>
		<div class="ec_account_subscription_v2_card ec_account_subscription_details_past ec_account_subscription_v2_history">
			<h4 class="ec_account_subscription_v2_card_title"><?php echo wp_easycart_escape_html( wp_easycart_language()->get_text( 'account_subscriptions', 'subscription_details_past_payments' ) ); ?></h4>
			<ul class="ec_account_subscriptions_past_payments ec_account_subscription_v2_payments">
				<?php foreach ( array_reverse( $ec_sub_payments ) as $ec_sub_payment ) { ?>
				<li class="ec_account_subscriptions_past_payment_item">
					<span class="ec_account_subscription_v2_payment_date"><?php echo esc_html( ec_subscription::format_date( ec_subscription::to_timestamp( $ec_sub_payment->order_date ) ) ); ?></span>
					<span class="ec_account_subscription_v2_payment_total"><?php echo esc_html( $GLOBALS['currency']->get_currency_display( $ec_sub_payment->grand_total ) ); ?></span>
					<a class="ec_account_subscription_v2_payment_link" href="<?php echo esc_url( wpeasycart_links()->get_account_page( 'order_details', array( 'order_id' => (int) $ec_sub_payment->order_id ) ) ); ?>"><?php echo wp_easycart_escape_html( wp_easycart_language()->get_text( 'account_orders', 'account_orders_view_order_button' ) ); ?></a>
				</li>
				<?php } ?>
			</ul>
		</div>
		<?php } ?>

	</div>

	<?php do_action( 'wpeasycart_account_subscription_details_after', $ec_sub ); ?>
</section>

<div style="clear:both;"></div>
<div id="ec_current_media_size"></div>

<?php if ( get_option( 'ec_option_cache_prevent' ) ) { ?>
<script type="text/javascript">
	wpeasycart_account_billing_country_update( );
	wpeasycart_account_shipping_country_update( );
	jQuery( document.getElementById( 'ec_account_billing_information_country' ) ).change( function( ){ wpeasycart_account_billing_country_update( ); } );
	jQuery( document.getElementById( 'ec_account_shipping_information_country' ) ).change( function( ){ wpeasycart_account_shipping_country_update( ); } );
</script>
<?php } ?>
