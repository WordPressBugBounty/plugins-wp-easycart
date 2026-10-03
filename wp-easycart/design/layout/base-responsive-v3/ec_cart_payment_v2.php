<?php if ( ! isset( $cartpage ) ) {
	$cartpage = $this;
} ?>

<?php if ( ! $cartpage->page_allowed( 'payment' ) && get_option( 'ec_option_onepage_checkout_tabbed' ) ) { ?>
	<div class="ec_cart_header ec_cart_header_no_border">
		<?php echo wp_easycart_language( )->get_text( 'cart_payment_information', 'cart_payment_information_payment_method' ); ?>
	</div>

	<div class="ec_cart_locked_panel">
		<?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_onepage', 'payment_locked' ) ); ?>
	</div>

<?php } else { ?>

	<?php
	if ( function_exists( 'wp_easycart_meta_add_payment_info' ) ) {
		// 6.0.2: Meta AddPaymentInfo once per checkout. The tabbed checkout's payment step counts when it shows; on the
		// single page, payment is on the page from the start, so it counts once the shopper reaches that section.
		wp_easycart_meta_add_payment_info( $cartpage, get_option( 'ec_option_onepage_checkout_tabbed' ) ? 'now' : 'deferred' );
	}
	?>

	<?php if ( '' != get_option( 'ec_option_google_ga4_property_id' ) ) { ?>
	<script>
		jQuery( document ).ready( function() {
			/* 6.0.2: the single page's form is the details form; one handler, even when this section is drawn again */
			jQuery( document.getElementById( 'ec_submit_order_form' ) || document.getElementById( 'wpeasycart_checkout_details_form' ) ).off( 'submit.wpecga4' ).on( 'submit.wpecga4', function() {
				<?php if ( get_option( 'ec_option_google_ga4_tag_manager' ) ) { ?>
				dataLayer.push( { ecommerce: null } );
				dataLayer.push( {
					event: "add_payment_info",
					ecommerce: {
				<?php } else { ?>
				gtag( "event", "add_payment_info", {
				<?php }?>
					currency: "<?php echo esc_attr( wp_easycart_base_currency_code() ); ?>",
					value: <?php echo esc_attr( number_format( $cartpage->order_totals->grand_total, 2, '.', '' ) ); ?>,
					coupon_code: "<?php echo esc_attr( $cartpage->coupon_code ); ?>",
					shipping_tier: "<?php echo esc_attr( trim( strip_tags( $cartpage->shipping->get_selected_shipping_method() ) ) ); ?>",
					items: [
					<?php for( $i=0; $i < count( $cartpage->cart->cart ); $i++ ) { ?>
						{
							item_id: "<?php echo esc_attr( $cartpage->cart->cart[$i]->model_number ); ?>",
							item_name: "<?php echo esc_attr( $cartpage->cart->cart[$i]->title ); ?>",
							index: <?php echo esc_attr( $i ); ?>,
							price: <?php echo esc_attr( number_format( $cartpage->cart->cart[$i]->unit_price, 2, '.', '' ) ); ?>,
							item_brand: "<?php echo esc_attr( $cartpage->cart->cart[$i]->manufacturer_name ) ; ?>",
							quantity: <?php echo esc_attr( number_format( $cartpage->cart->cart[$i]->quantity, 2, '.', '' ) ); ?>
						},
					<?php } ?>
					]
				<?php if ( ! get_option( 'ec_option_google_ga4_tag_manager' ) ) { ?>} );<?php } else { ?>} } );<?php }?>
			} );
		} );
	</script>
	<?php }?>

	<?php if( get_option( 'ec_option_onepage_checkout_tabbed' ) ) { ?>
	<div class="ec_cart_review_box">
		<div class="ec_cart_review_row">
			<div class="ec_cart_review_label"><?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_onepage', 'contact' ) ); ?></div>
			<div class="ec_cart_review_info"><?php echo esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->email, ENT_QUOTES ) ); ?></div>
			<div class="ec_cart_review_button"><a href="#" onclick="return wp_easycart_goto_page_v2( 'information', '<?php echo esc_attr( wp_create_nonce( 'wp-easycart-goto-cart-page-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ); ?>' );"><?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_onepage', 'change' ) ); ?></a></div>
		</div>
		<?php if ( get_option( 'ec_option_use_shipping' ) && $cartpage->shipping_address_allowed && ( $cartpage->cart->shippable_total_items > 0 || $cartpage->order_totals->handling_total > 0 || $cartpage->cart->excluded_shippable_total_items > 0 ) ) { ?>
		<div class="ec_cart_review_row">
			<div class="ec_cart_review_label"><?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_onepage', 'review_ship_to' ) ); ?></div>
			<div class="ec_cart_review_info"><?php
				echo esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->shipping_address_line_1, ENT_QUOTES ) );
				if ( '' != $GLOBALS['ec_cart_data']->cart_data->shipping_address_line_2 ) {
					echo ', ' . esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->shipping_address_line_2, ENT_QUOTES ) );
				}
				echo ', ' . esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->shipping_city, ENT_QUOTES ) );
				if ( '' != $GLOBALS['ec_cart_data']->cart_data->shipping_state ) {
					echo ' ' . esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->shipping_state, ENT_QUOTES ) );
				}
				if ( '' != $GLOBALS['ec_cart_data']->cart_data->shipping_zip ) {
					echo ', ' . esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->shipping_zip, ENT_QUOTES ) );
				}
				echo ' ' . esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->shipping_country, ENT_QUOTES ) );
			?></div>
			<div class="ec_cart_review_button"><a href="#" onclick="return wp_easycart_goto_page_v2( 'information', '<?php echo esc_attr( wp_create_nonce( 'wp-easycart-goto-cart-page-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ); ?>' );"><?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_onepage', 'change' ) ); ?></a></div>
		</div>
		<div class="ec_cart_review_row">
			<div class="ec_cart_review_label"><?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_onepage', 'review_method' ) ); ?></div>
			<div class="ec_cart_review_info"><?php echo esc_attr( $cartpage->shipping->get_selected_shipping_method_label() ); ?></div>
			<div class="ec_cart_review_button"><a href="#" onclick="return wp_easycart_goto_page_v2( 'shipping', '<?php echo esc_attr( wp_create_nonce( 'wp-easycart-goto-cart-page-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ); ?>' );"><?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_onepage', 'change' ) ); ?></a></div>
		</div>
		<?php } else { ?>
		<div class="ec_cart_review_row">
			<div class="ec_cart_review_label"><?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_onepage', 'review_billing' ) ); ?></div>
			<div class="ec_cart_review_info"><?php
				echo esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->billing_address_line_1, ENT_QUOTES ) );
				if ( '' != $GLOBALS['ec_cart_data']->cart_data->billing_address_line_2 ) {
					echo ', ' . esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->billing_address_line_2, ENT_QUOTES ) );
				}
				echo ', ' . esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->billing_city, ENT_QUOTES ) );
				if ( '' != $GLOBALS['ec_cart_data']->cart_data->billing_state ) {
					echo ' ' . esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->billing_state, ENT_QUOTES ) );
				}
				if ( '' != $GLOBALS['ec_cart_data']->cart_data->billing_zip ) {
					echo ', ' . esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->billing_zip, ENT_QUOTES ) );
				}
				echo ' ' . esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->billing_country, ENT_QUOTES ) );
			?></div>
			<div class="ec_cart_review_button"><a href="#" onclick="return wp_easycart_goto_page_v2( 'information', '<?php echo esc_attr( wp_create_nonce( 'wp-easycart-goto-cart-page-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ); ?>' );"><?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_onepage', 'change' ) ); ?></a></div>
		</div>
		<?php }?>
	</div>
	<?php if ( get_option( 'ec_option_use_shipping' ) && $cartpage->shipping_address_allowed && ( $cartpage->cart->shippable_total_items > 0 || $cartpage->order_totals->handling_total > 0 || $cartpage->cart->excluded_shippable_total_items > 0 ) ) { ?>
	<input type="hidden" id="ec_cart_shipping_first_name" value="<?php echo esc_attr( $GLOBALS['ec_cart_data']->cart_data->shipping_first_name ); ?>" />
	<input type="hidden" id="ec_cart_shipping_last_name" value="<?php echo esc_attr( $GLOBALS['ec_cart_data']->cart_data->shipping_last_name ); ?>" />
	<input type="hidden" id="ec_cart_shipping_company_name" value="<?php echo esc_attr( $GLOBALS['ec_cart_data']->cart_data->shipping_company_name ); ?>" />
	<input type="hidden" id="ec_cart_shipping_address" value="<?php echo esc_attr( $GLOBALS['ec_cart_data']->cart_data->shipping_address_line_1 ); ?>" />
	<input type="hidden" id="ec_cart_shipping_address2" value="<?php echo esc_attr( $GLOBALS['ec_cart_data']->cart_data->shipping_address_line_2 ); ?>" />
	<input type="hidden" id="ec_cart_shipping_city" value="<?php echo esc_attr( $GLOBALS['ec_cart_data']->cart_data->shipping_city ); ?>" />
	<input type="hidden" id="ec_cart_shipping_state" value="<?php echo esc_attr( $GLOBALS['ec_cart_data']->cart_data->shipping_state ); ?>" />
	<input type="hidden" id="ec_cart_shipping_zip" value="<?php echo esc_attr( $GLOBALS['ec_cart_data']->cart_data->shipping_zip ); ?>" />
	<input type="hidden" id="ec_cart_shipping_country" value="<?php echo esc_attr( $GLOBALS['ec_cart_data']->cart_data->shipping_country ); ?>" />
	<input type="hidden" id="ec_cart_shipping_phone" value="<?php echo esc_attr( $GLOBALS['ec_cart_data']->cart_data->shipping_phone ); ?>" />
	<?php } else { ?>
	<input type="hidden" id="ec_cart_billing_first_name" value="<?php echo esc_attr( $GLOBALS['ec_cart_data']->cart_data->billing_first_name ); ?>" />
	<input type="hidden" id="ec_cart_billing_last_name" value="<?php echo esc_attr( $GLOBALS['ec_cart_data']->cart_data->billing_last_name ); ?>" />
	<input type="hidden" id="ec_cart_billing_company_name" value="<?php echo esc_attr( $GLOBALS['ec_cart_data']->cart_data->billing_company_name ); ?>" />
	<input type="hidden" id="ec_cart_billing_address" value="<?php echo esc_attr( $GLOBALS['ec_cart_data']->cart_data->billing_address_line_1 ); ?>" />
	<input type="hidden" id="ec_cart_billing_address2" value="<?php echo esc_attr( $GLOBALS['ec_cart_data']->cart_data->billing_address_line_2 ); ?>" />
	<input type="hidden" id="ec_cart_billing_city" value="<?php echo esc_attr( $GLOBALS['ec_cart_data']->cart_data->billing_city ); ?>" />
	<input type="hidden" id="ec_cart_billing_state" value="<?php echo esc_attr( $GLOBALS['ec_cart_data']->cart_data->billing_state ); ?>" />
	<input type="hidden" id="ec_cart_billing_zip" value="<?php echo esc_attr( $GLOBALS['ec_cart_data']->cart_data->billing_zip ); ?>" />
	<input type="hidden" id="ec_cart_billing_country" value="<?php echo esc_attr( $GLOBALS['ec_cart_data']->cart_data->billing_country ); ?>" />
	<input type="hidden" id="ec_cart_billing_phone" value="<?php echo esc_attr( $GLOBALS['ec_cart_data']->cart_data->billing_phone ); ?>" />
	<?php }?>
	<?php }?>

	<?php if ( get_option( 'ec_option_onepage_checkout_tabbed' ) ) { $cartpage->display_page_three_form_start( ); } ?>

	<?php
	/* 6.0.2: preorder and restaurant pickup ( date, time, store location ), as the classic payment step. */
	$wpec_pickup_closed = $cartpage->cart->has_restaurant_items() && ! $cartpage->cart->is_restaurant_open();
	if ( $cartpage->cart->has_preorder_items() || $cartpage->cart->has_restaurant_items() ) {
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_pickup_v2.php' ) ) {
			include EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_pickup_v2.php';
		} else {
			include EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_pickup_v2.php';
		}
		do_action( 'wpeasycart_checkout_fields', 'pickup', $cartpage ); /* 6.0.2: checkout fields ( WP EasyCart PRO ) */
	}
	?>

	<?php if( $cartpage->order_totals->grand_total > 0 ){ ?>
	<div class="ec_cart_header ec_cart_header_no_border">
		<?php echo wp_easycart_language( )->get_text( 'cart_payment_information', 'cart_payment_information_payment_method' ); ?>
	</div>

	<div class="ec_cart_error_row" id="ec_payment_method_error">
		<?php echo wp_easycart_language( )->get_text( 'ec_errors', 'missing_payment_method' )?> 
	</div>

	<div class="ec_cart_payment_table">
		<?php if( $cartpage->use_manual_payment( ) ){?>
			<div id="manual-success-cover" style="display:none; cursor:default; position:fixed; top:0; left:0; width:100%; height:100%; z-index:999999; background-color: rgba(0, 0, 0, 0.8); color:#FFF;">
				<style>
				@keyframes rotation{
					0%  { transform:rotate(0deg); }
					100%{ transform:rotate(359deg); }
				}
				</style>
				<div style='font-family: "HelveticaNeue", "HelveticaNeue-Light", "Helvetica Neue Light", helvetica, arial, sans-serif; font-size: 14px; text-align: center; -webkit-box-sizing: border-box; -moz-box-sizing: border-box; -ms-box-sizing: border-box; box-sizing: border-box; width: 350px; top: 50%; left: 50%; position: absolute; margin-left: -165px; margin-top: -80px; cursor: pointer; text-align: center;'>
					<div class="paypal-checkout-loader">
						<div style="height: 30px; width: 30px; display: inline-block; box-sizing: content-box; opacity: 1; filter: alpha(opacity=100); -webkit-animation: rotation .7s infinite linear; -moz-animation: rotation .7s infinite linear; -o-animation: rotation .7s infinite linear; animation: rotation .7s infinite linear; border-left: 8px solid rgba(0, 0, 0, .2); border-right: 8px solid rgba(0, 0, 0, .2); border-bottom: 8px solid rgba(0, 0, 0, .2); border-top: 8px solid #fff; border-radius: 100%;"></div>
					</div>
				</div>
			</div>
			<label for="ec_payment_manual" class="ec_cart_full_radio">
				<div class="ec_cart_payment_table_row<?php echo ( 'manual_bill' == $cartpage->get_selected_payment_method() ) ? ' ec_payment_row_selected' : ''; ?>">
					<div class="ec_cart_payment_table_column">
						<input type="radio" class="no_wrap" name="ec_cart_payment_selection" id="ec_payment_manual" value="manual_bill"<?php if( $cartpage->get_selected_payment_method( ) == "manual_bill" ){ ?> checked="checked"<?php }?> onChange="ec_update_payment_display( '<?php echo esc_attr( wp_create_nonce( 'wp-easycart-update-payment-method-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ); ?>' );" /> <?php echo wp_easycart_language( )->get_text( 'cart_payment_information', 'cart_payment_information_manual_payment' )?>
					</div>
				</div>
			</label>

			<div id="ec_manual_payment_form"<?php if( $cartpage->get_selected_payment_method( ) == "manual_bill" ){ ?> style="display:block;"<?php }?>>
				<div class="ec_cart_box_section">
					<?php $cartpage->display_manual_payment_text( ); ?>
				</div>
			</div>
		<?php } ?>

		<?php if( get_option( 'ec_option_use_affirm' ) ){ ?>
			<label for="ec_payment_affirm" class="ec_cart_full_radio">
				<div class="ec_cart_payment_table_row<?php echo ( 'affirm' == $cartpage->get_selected_payment_method() ) ? ' ec_payment_row_selected' : ''; ?>">
					<div class="ec_cart_payment_table_column">
						<input type="radio" class="no_wrap" name="ec_cart_payment_selection" id="ec_payment_affirm" value="affirm"<?php if( $cartpage->get_selected_payment_method( ) == "affirm" ){ ?> checked="checked"<?php }?> onChange="ec_update_payment_display( '<?php echo esc_attr( wp_create_nonce( 'wp-easycart-update-payment-method-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ); ?>' );" /> <?php echo wp_easycart_language( )->get_text( 'cart_payment_information', 'cart_payment_information_affirm' ); ?>
					</div>
				</div>
			</label>

			<div id="ec_affirm_form"<?php if( $cartpage->get_selected_payment_method( ) == "affirm" ){ ?> style="display:block;"<?php }?>>
				<div class="ec_cart_box_section ec_affirm_box">
					<script>
						function ec_checkout_with_affirm( ){
						var wpec_affirm_value = function( key, fallback ) { /* 6.0.2: what Place order just saved */
							return ( 'function' === typeof wpeasycart_onepage_session_value ) ? wpeasycart_onepage_session_value( key, fallback ) : fallback;
						};
						var wpec_affirm_totals = ( 'undefined' !== typeof wpeasycart_onepage_place && wpeasycart_onepage_place.totals ) ? wpeasycart_onepage_place.totals : null;
						affirm.checkout({
							config: {
								financial_product_key:		"<?php echo esc_attr( get_option( 'ec_option_affirm_financial_product' ) ); ?>"
							},
							merchant: {
								user_confirmation_url:		"<?php echo esc_attr( wpeasycart_links()->get_cart_page( 'process_affirm' ) ); ?>",
								user_cancel_url:			"<?php echo esc_attr( wpeasycart_links()->get_cart_page( 'checkout_payment' ) ); ?>"
							},
							billing: {
								name: {
									first:					wpec_affirm_value( 'billing_first_name', "<?php echo esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->billing_first_name, ENT_QUOTES ) ); ?>" ),
									last:					wpec_affirm_value( 'billing_last_name', "<?php echo esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->billing_last_name, ENT_QUOTES ) ); ?>" )
								},
								address: {
									line1:					wpec_affirm_value( 'billing_address_line_1', "<?php echo esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->billing_address_line_1, ENT_QUOTES ) ); ?>" ),
									line2:					wpec_affirm_value( 'billing_address_line_2', "<?php echo esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->billing_address_line_2, ENT_QUOTES ) ); ?>" ),
									city:					wpec_affirm_value( 'billing_city', "<?php echo esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->billing_city, ENT_QUOTES ) ); ?>" ),
									state:					wpec_affirm_value( 'billing_state', "<?php echo esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->billing_state, ENT_QUOTES ) ); ?>" ),
									zipcode:				wpec_affirm_value( 'billing_zip', "<?php echo esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->billing_zip, ENT_QUOTES ) ); ?>" ),
									country:				wpec_affirm_value( 'billing_country', "<?php echo esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->billing_country, ENT_QUOTES ) ); ?>" )
								},
								phone_number:				wpec_affirm_value( 'billing_phone', "<?php echo esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->billing_phone, ENT_QUOTES ) ); ?>" )<?php if( !class_exists( 'Email_Encoder' ) && !function_exists( 'eae_encode_emails' ) ){ ?>,
								email:						wpec_affirm_value( 'email', "<?php echo esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->email, ENT_QUOTES ) ); ?>" )<?php }?>
							},
							shipping: {
								name: {
									first:					wpec_affirm_value( 'shipping_first_name', "<?php echo esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->shipping_first_name, ENT_QUOTES ) ); ?>" ),
									last:					wpec_affirm_value( 'shipping_last_name', "<?php echo esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->shipping_last_name, ENT_QUOTES ) ); ?>" )
								},
								address: {
									line1:					wpec_affirm_value( 'shipping_address_line_1', "<?php echo esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->shipping_address_line_1, ENT_QUOTES ) ); ?>" ),
									line2:					wpec_affirm_value( 'shipping_address_line_2', "<?php echo esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->shipping_address_line_2, ENT_QUOTES ) ); ?>" ),
									city:					wpec_affirm_value( 'shipping_city', "<?php echo esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->shipping_city, ENT_QUOTES ) ); ?>" ),
									state:					wpec_affirm_value( 'shipping_state', "<?php echo esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->shipping_state, ENT_QUOTES ) ); ?>" ),
									zipcode:				wpec_affirm_value( 'shipping_zip', "<?php echo esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->shipping_zip, ENT_QUOTES ) ); ?>" ),
									country:				wpec_affirm_value( 'shipping_country', "<?php echo esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->shipping_country, ENT_QUOTES ) ); ?>" )
								},
								phone_number:				wpec_affirm_value( 'shipping_phone', "<?php echo esc_attr( htmlspecialchars( $GLOBALS['ec_cart_data']->cart_data->shipping_phone, ENT_QUOTES ) ); ?>" )
							},
							items: ( 'undefined' !== typeof wpeasycart_onepage_place && wpeasycart_onepage_place.items && wpeasycart_onepage_place.items.length ) ? wpeasycart_onepage_place.items : [<?php for( $i=0; $i<count( $cartpage->cart->cart ); $i++ ){ ?>{
								display_name:         		"<?php echo esc_attr( $cartpage->cart->cart[$i]->title ); ?>",
								sku:                  		"<?php echo esc_attr( $cartpage->cart->cart[$i]->model_number ); ?>",
								unit_price:           		<?php echo esc_attr( number_format( ( 100 * $cartpage->cart->cart[$i]->unit_price ), 0, '', '' ) ); ?>,
								qty:                  		<?php echo esc_attr( $cartpage->cart->cart[$i]->quantity ); ?>,
								item_image_url:       		"<?php echo esc_attr( $cartpage->cart->cart[$i]->get_image_url( ) ); ?>",
								item_url:             		"<?php echo esc_attr( $cartpage->cart->cart[$i]->get_product_url( ) ); ?>"
							},<?php }?>],
							tax_amount:						wpec_affirm_totals ? wpec_affirm_totals.tax : <?php echo esc_attr( number_format( ( 100 * $cartpage->order_totals->tax_total ), 0, '', '' ) ); ?>,
							shipping_amount:				wpec_affirm_totals ? wpec_affirm_totals.shipping : <?php echo esc_attr( number_format( ( 100 * $cartpage->order_totals->shipping_total ), 0, '', '' ) ); ?>
						});
						affirm.checkout.open( );
					}
						( function() { /* 6.0.2: opened from the submit Place order sends after its checks */
							var affirm_form = document.getElementById( 'ec_submit_order_form' ) || document.getElementById( 'wpeasycart_checkout_details_form' );
							if ( ! affirm_form ) {
								return;
							}
							if ( affirm_form.wpecAffirmSubmit ) {
								affirm_form.removeEventListener( 'submit', affirm_form.wpecAffirmSubmit );
							}
							affirm_form.wpecAffirmSubmit = function( event ) {
								if ( 'affirm' != jQuery( 'input:radio[name=ec_cart_payment_selection]:checked' ).val() ) {
									return;
								}
								event.preventDefault();
								wpeasycart_onepage_restore_buttons();
								ec_checkout_with_affirm();
							};
							affirm_form.addEventListener( 'submit', affirm_form.wpecAffirmSubmit );
						} )();
					</script>

					<a href="https://www.affirm.com" target="_blank"><img src="<?php echo esc_attr( $cartpage->get_payment_image_source( "affirm-banner-540x200.png" ) ); ?>" alt="Affirm Split Pay" /></a>
				</div>
			</div>
		<?php }?>

		<?php if( $cartpage->use_third_party( ) ){ /* 6.0.2: PayPal too ( its buttons replace Place order when chosen ) */ ?>
			<label for="ec_payment_third_party" class="ec_cart_full_radio">
				<div class="ec_cart_payment_table_row<?php echo ( 'third_party' == $cartpage->get_selected_payment_method() ) ? ' ec_payment_row_selected' : ''; ?>">
					<div class="ec_cart_payment_table_column">
						<input type="radio" class="no_wrap" name="ec_cart_payment_selection" id="ec_payment_third_party" value="third_party"<?php if( $cartpage->get_selected_payment_method( ) == "third_party" ){ ?> checked="checked"<?php }?> onChange="ec_update_payment_display( '<?php echo esc_attr( wp_create_nonce( 'wp-easycart-update-payment-method-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ); ?>' );" /> <?php echo wp_easycart_language( )->get_text( 'cart_payment_information', 'cart_payment_information_third_party' )?> <?php $cartpage->ec_cart_display_current_third_party_name( ); ?>
					</div>
				</div>
			</label>

			<div id="ec_third_party_form"<?php if( $cartpage->get_selected_payment_method( ) == "third_party" ){ ?> style="display:block;"<?php }?>>
				<div class="ec_cart_box_section">
					<?php if( get_option( 'ec_option_payment_third_party' ) != "paypal" || get_option( 'ec_option_paypal_enable_pay_now' ) != '1' ){
						echo wp_easycart_language( )->get_text( 'cart_payment_information', 'cart_payment_information_third_party_first' )?> <?php $cartpage->ec_cart_display_current_third_party_name( ); ?> <?php echo wp_easycart_language( )->get_text( 'cart_payment_information', 'cart_payment_information_third_party_second' ) . '<br />';
					}?>

					<?php if( get_option( 'ec_option_payment_third_party' ) == "paypal" ){ ?>
						<img src="<?php echo esc_attr( $cartpage->get_payment_image_source( "paypal.jpg" ) ); ?>" alt="PayPal" />

					<?php }else if( get_option( 'ec_option_payment_third_party' ) == "skrill" ){ ?>
						<img src="<?php echo esc_attr( $cartpage->get_payment_image_source( "skrill-logo.gif" ) ); ?>" alt="Skrill" />

					<?php }else if( get_option( 'ec_option_realex_thirdparty_type' ) == 'hpp' && get_option( 'ec_option_payment_third_party' ) == "realex_thirdparty" ){  ?>
						<script>
						jQuery( document ).ready( function( ){
							var data = {
								action: "ec_ajax_realex_hpp_init",
								total: "<?php echo esc_js( $cartpage->order_totals->grand_total ); ?>"
							};
							jQuery.ajax( { url: wpeasycart_ajax_object.ajax_url, type: "post", data: data, success: function( data ){
								<?php if( get_option( 'ec_option_realex_thirdparty_test_mode' ) ){ ?>RealexHpp.setHppUrl('https://pay.sandbox.realexpayments.com/pay');
								<?php }?>RealexHpp.init( "ec_cart_submit_order", "<?php echo esc_url( wpeasycart_links()->get_cart_page( 'checkout_success', array( 'order_id' => '' ) ) ); ?>" + data.order_id, data.response );
							} } );
						} );
						</script>

					<?php }?>

					<?php do_action( 'wpeasycart_third_party_checkout_box' ); ?>

				</div>
			</div>
		<?php }?>

		<?php if( $cartpage->use_payment_gateway( ) ){?>
		<label for="ec_payment_credit_card" class="ec_cart_full_radio">
			<div class="ec_cart_payment_table_row<?php echo ( 'credit_card' == $cartpage->get_selected_payment_method() ) ? ' ec_payment_row_selected' : ''; ?>">
				<div class="ec_cart_payment_table_column">
					<input type="radio" class="no_wrap" name="ec_cart_payment_selection" id="ec_payment_credit_card" value="credit_card"<?php if( $cartpage->get_selected_payment_method( ) == "credit_card" ){ ?> checked="checked"<?php }?> onChange="ec_update_payment_display( '<?php echo esc_attr( wp_create_nonce( 'wp-easycart-update-payment-method-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ); ?>' );" /> <?php echo wp_easycart_language( )->get_text( 'cart_payment_information', 'cart_payment_information_credit_card' )?>
				</div>
			</div>
		</label>

		<div id="ec_credit_card_form"<?php if( $cartpage->get_selected_payment_method( ) == "credit_card" ){ ?> style="display:block;"<?php }?>>
			<div class="ec_cart_box_section">
				<?php if( get_option( 'ec_option_payment_process_method' ) == "square"  && $cartpage->order_totals->grand_total < 1 ){ ?>
				<p style="font-size:18px; color:red"><?php echo esc_html( sprintf( wp_easycart_language()->get_text( 'cart_onepage', 'card_minimum' ), $GLOBALS['currency']->get_currency_display( 1 ) ) ); ?></p>
				<?php }else if( ( get_option( 'ec_option_payment_process_method' ) == "stripe" || get_option( 'ec_option_payment_process_method' ) == "stripe_connect" ) && $cartpage->order_totals->grand_total < .5 ){ ?>
				<p style="font-size:18px; color:red"><?php echo esc_html( sprintf( wp_easycart_language()->get_text( 'cart_onepage', 'card_minimum' ), $GLOBALS['currency']->get_currency_display( 0.5 ) ) ); ?></p>
				<?php }?>

				<?php if( get_option( 'ec_option_payment_process_method' ) == "square" ){
					$cartpage->print_square_payment_card();

				} else if( ( get_option( 'ec_option_payment_process_method' ) == 'stripe' && get_option( 'ec_option_stripe_public_api_key' ) != "" ) || ( get_option( 'ec_option_payment_process_method' ) == 'stripe_connect' ) ){ ?>
				<?php if( $cartpage->order_totals->grand_total >= .5 ){ ?>
				<div class="form-row" style="margin-top:12px;">
					<div id="ec_stripe_card_row">
					  <!-- a Stripe Element will be inserted here. -->
					</div>

					<!-- Used to display form errors -->
					<div id="ec_card_errors" role="alert" style="color:rgb(181, 41, 41); float:left; width:100%; margin-top:5px; text-align:center; background:rgb(241, 241, 241);"></div>
				</div>

				<div id="stripe-success-cover" style="display:none; cursor:default; position:fixed; top:0; left:0; width:100%; height:100%; z-index:999999; background-color: rgba(0, 0, 0, 0.8); color:#FFF;">
					<style>
					@keyframes rotation{
						0%  { transform:rotate(0deg); }
						100%{ transform:rotate(359deg); }
					}
					</style>
					<div style='font-family: "HelveticaNeue", "HelveticaNeue-Light", "Helvetica Neue Light", helvetica, arial, sans-serif; font-size: 14px; text-align: center; -webkit-box-sizing: border-box; -moz-box-sizing: border-box; -ms-box-sizing: border-box; box-sizing: border-box; width: 350px; top: 50%; left: 50%; position: absolute; margin-left: -165px; margin-top: -80px; cursor: pointer; text-align: center;'>
						<div class="paypal-checkout-loader">
							<div style="height: 30px; width: 30px; display: inline-block; box-sizing: content-box; opacity: 1; filter: alpha(opacity=100); -webkit-animation: rotation .7s infinite linear; -moz-animation: rotation .7s infinite linear; -o-animation: rotation .7s infinite linear; animation: rotation .7s infinite linear; border-left: 8px solid rgba(0, 0, 0, .2); border-right: 8px solid rgba(0, 0, 0, .2); border-bottom: 8px solid rgba(0, 0, 0, .2); border-top: 8px solid #fff; border-radius: 100%;"></div>
						</div>
					</div>
				</div>
				<?php } /* Close Minimum Required check*/ ?>

				<?php }else if( get_option( 'ec_option_payment_process_method' ) == "braintree" ){ // Close if Stripe Only Form ?>
				<?php $braintree_gateway = new ec_braintree( ); ?>
				<div id="wpec_braintree_dropin"></div>
				<input type="hidden" id="braintree_nonce" name="braintree_nonce" value="" />
				<style>
				.braintree-large-button.braintree-toggle{ display:none !important; }
				</style>
				<script>
					var form = ( jQuery( document.getElementById( 'ec_submit_order_form' ) ).length ) ? document.getElementById( 'ec_submit_order_form' ) : document.getElementById( 'wpeasycart_checkout_details_form' );
					var client_token = "<?php echo esc_attr( $braintree_gateway->get_client_token( ) ); ?>";
					braintree.dropin.create(
						{
							authorization: client_token,
							selector: '#wpec_braintree_dropin'
						}, 
						function( createErr, instance ){
							if( createErr ){
								console.log( 'Create Error', createErr );
								return;
							}
							if ( form.wpecBraintreeSubmit ) { /* 6.0.2: one listener, even when the section is drawn again */
								form.removeEventListener( 'submit', form.wpecBraintreeSubmit );
							}
							form.addEventListener(
								'submit',
								form.wpecBraintreeSubmit = function( event ){
									var payment_method = "credit_card";
									if( jQuery( 'input:radio[name=ec_cart_payment_selection]:checked' ).length )
										payment_method = jQuery( 'input:radio[name=ec_cart_payment_selection]:checked' ).val( );
									if( payment_method != 'credit_card' ){
										jQuery( document.getElementById( 'ec_submit_order_error' ) ).hide( );
									}else{
										event.preventDefault( );
										jQuery( document.getElementById( 'ec_cart_submit_order' ) ).hide( );
										jQuery( document.getElementById( 'ec_cart_submit_order_working' ) ).show( );
										jQuery( document.getElementById( 'ec_submit_order_error' ) ).hide( );
										instance.requestPaymentMethod(
											function( err, payload ){
												if (err) {
													jQuery( document.getElementById( 'ec_submit_order_error' ) ).show( );
													jQuery( document.getElementById( 'ec_cart_submit_order' ) ).show( );
													jQuery( document.getElementById( 'ec_cart_submit_order_working' ) ).hide( );
													console.log( 'Request Payment Method Error', err );
													return;
												}
												document.querySelector( '#braintree_nonce' ).value = payload.nonce;
												form.submit( );
											}
										);
									}
								}
							);
						}
					);
				</script>
				<?php }else if( get_option( 'ec_option_payment_process_method' ) == "cardpointe" ){ // Close if Braintree Only Form ?>
				<input type="hidden" name="cardpointe_token" id="cardpointe_token" />
				<iframe id="cardpointeTokenFrame" name="cardpointeTokenFrame" src="https://<?php echo esc_attr( get_option( 'ec_option_cardpointe_site' ) ); ?>.cardconnect.com/itoke/ajax-tokenizer.html?useexpiry=true&usecvv=true&orientation=horizontal&tokenizewheninactive=true&css=<?php echo urlencode( 'br{display:inline-block;width:100%;clear:both;}label{display:inline-block;font-family:Arial, sans-serif;font-size:12px;font-weight:bold;color:#222222;margin-top:10px; }input,select{border-color:#e1e1e1; background-color:#f8f8f8; color:#919191; -webkit-appearance:none; border:1px solid #e1e1e1; outline:none; font:13px "HelveticaNeue", "Helvetica Neue", Helvetica, Arial, sans-serif; color:#777; margin:0; display:inline-block; max-width:100%; background:#FFF; padding:8px 6px; line-height:1.1em; box-sizing:border-box; -moz-box-sizing:border-box; -webkit-box-sizing:border-box; height:39px;}.error{color:red;}input[id="ccnumfield"]{width:100%;}select{width:46px;}' ); ?>" frameborder="0" scrolling="no"></iframe>
				<script language="JavaScript">
					window.addEventListener( 'message', function(event) {
						var token = JSON.parse( event.data );
						var mytoken = document.getElementById( 'cardpointe_token' );
						if( token.message ){
							mytoken.value = token.message;
						}
					}, false );
				</script>

				<?php }else{ // Close if Card Pointe Only Form ?>

				<div class="ec_cart_input_row" style="margin-top:-10px;">
					<img src="<?php echo esc_attr( $cartpage->get_payment_image_source( "visa.png" ) ); ?>" alt="Visa" class="ec_card_active" id="ec_card_visa" />
					<img src="<?php echo esc_attr( $cartpage->get_payment_image_source( "visa_inactive.png" ) ); ?>" alt="Visa" class="ec_card_inactive" id="ec_card_visa_inactive" />

					<img src="<?php echo esc_attr( $cartpage->get_payment_image_source( "discover.png" ) ); ?>" alt="Discover" class="ec_card_active" id="ec_card_discover" />
					<img src="<?php echo esc_attr( $cartpage->get_payment_image_source( "discover_inactive.png" ) ); ?>" alt="Discover" class="ec_card_inactive" id="ec_card_discover_inactive" />

					<img src="<?php echo esc_attr( $cartpage->get_payment_image_source( "mastercard.png") ); ?>" alt="Mastercard" class="ec_card_active" id="ec_card_mastercard" />
					<img src="<?php echo esc_attr( $cartpage->get_payment_image_source( "mastercard_inactive.png") ); ?>" alt="Mastercard" class="ec_card_inactive" id="ec_card_mastercard_inactive" />

					<img src="<?php echo esc_attr( $cartpage->get_payment_image_source( "american_express.png") ); ?>" alt="AMEX" class="ec_card_active" id="ec_card_amex" />
					<img src="<?php echo esc_attr( $cartpage->get_payment_image_source( "american_express_inactive.png") ); ?>" alt="AMEX" class="ec_card_inactive" id="ec_card_amex_inactive" />
				</div>

				<?php if( get_option( 'ec_option_show_card_holder_name' ) ){ ?>
				<div class="ec_cart_input_row">
					<input name="ec_card_holder_name" id="ec_card_holder_name" type="text" class="input-lg form-control" placeholder="<?php echo wp_easycart_language( )->get_text( 'cart_payment_information', 'cart_payment_information_card_holder_name' )?>">
					<div class="ec_cart_error_row" id="ec_card_holder_name_error">
						<?php echo wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_valid' ); ?> <?php echo wp_easycart_language( )->get_text( 'cart_payment_information', 'cart_payment_information_card_holder_name' )?>
					</div>
				</div>
				<?php }else{ ?>
				<?php $cartpage->ec_cart_display_card_holder_name_hidden_input(); ?>
				<?php } ?>
				<div class="ec_cart_input_row">
					<div>
						<input name="ec_card_number" id="ec_card_number"<?php if( get_option( 'ec_option_payment_process_method' ) == "eway" && get_option( 'ec_option_eway_use_rapid_pay' ) ){?> data-eway-encrypt-name="ec_card_number"<?php }?> type="tel" class="input-lg form-control cc-number" autocomplete="cc-number" placeholder="<?php echo wp_easycart_language( )->get_text( 'cart_payment_information', 'cart_payment_information_card_number' )?>">
					</div>
					<div class="ec_cart_error_row" id="ec_card_number_error">
						<?php echo wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_valid' ); ?> <?php echo wp_easycart_language( )->get_text( 'cart_payment_information', 'cart_payment_information_card_number' )?>
					</div>
				</div>
				<div class="ec_cart_input_row">
					<div class="ec_cart_input_left_half">
						<div>
							<input name="ec_cc_expiration" id="ec_cc_expiration" type="tel" class="input-lg form-control cc-exp" autocomplete="cc-exp" placeholder="MM / YYYY">
						</div>
						<div class="ec_cart_error_row" id="ec_expiration_date_error" style="padding:8px;">
							<?php echo wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_valid' ); ?> <?php echo wp_easycart_language( )->get_text( 'cart_payment_information', 'cart_payment_information_expiration_date' )?>
						</div>
					</div>
					<div class="ec_cart_input_right_half">
						<div>
							<input name="ec_security_code" id="ec_security_code"<?php if( get_option( 'ec_option_payment_process_method' ) == "eway" && get_option( 'ec_option_eway_use_rapid_pay' ) ){?> data-eway-encrypt-name="ec_security_code"<?php }?> type="tel" class="input-lg form-control cc-cvc" autocomplete="off" placeholder="CVV">
						</div>
						<div class="ec_cart_error_row" id="ec_security_code_error" style="padding:8px;">
							<?php echo wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_valid' ); ?> <?php echo wp_easycart_language( )->get_text( 'cart_payment_information', 'cart_payment_information_security_code' )?>
						</div>
					</div>
				</div>
				<?php } //else from Stripe only check ?>
			</div>
			<?php do_action( 'wp_easycart_end_live_payment_box_inner', $cartpage ); ?>
		</div>
		<?php } //close if/else check for live gateway ?>
		<?php do_action( 'wp_easycart_cart_payment_payment_methods_end', $cartpage ); ?>
	</div>
	<?php } //close if/else check for free order ?>

	<?php if ( get_option( 'ec_option_payment_process_method' ) == 'stripe_connect' && ( get_option( 'ec_option_stripe_affirm' ) || get_option( 'ec_option_stripe_klarna' ) ) && ( get_option( 'ec_option_stripe_pay_later_minimum' ) && (int) get_option( 'ec_option_stripe_pay_later_minimum' ) > 50 ) ) { ?>
	<div class="paylater_message_v2" data-min-price="<?php echo (int) get_option( 'ec_option_stripe_pay_later_minimum' ); ?>" <?php if ( $cartpage->order_totals->sub_total >= (int) get_option( 'ec_option_stripe_pay_later_minimum' ) ) { echo ' style="display:none;"'; } ?>><?php echo wp_easycart_language( )->get_text( 'cart_totals', 'cart_totals_min_buy_now' ); ?> <?php echo $GLOBALS['currency']->get_currency_display( get_option( 'ec_option_stripe_pay_later_minimum' ) ); ?></div>
	<?php } ?>

	<?php do_action( 'wp_easycart_cart_payment_after_payment_table', $cartpage ); ?>

	<?php if( get_option( 'ec_option_use_shipping' ) && $cartpage->shipping_address_allowed && ( $cartpage->cart->shippable_total_items > 0 || $cartpage->order_totals->handling_total > 0 || $cartpage->cart->excluded_shippable_total_items > 0 ) ) { ?>
	<div class="ec_cart_header ec_cart_header_no_border">
		<?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_onepage', 'billing_address' ) ); ?>
	</div>

	<div class="ec_cart_billing_table">
		<?php $shipping_selector = ( 'true' == $GLOBALS['ec_cart_data']->cart_data->shipping_selector ) ? 1 : 0; ?>
		<label for="billing_address_type_same" class="ec_cart_full_radio">
			<div class="ec_cart_billing_table_row<?php echo ( ! $shipping_selector ) ? ' ec_billing_row_selected' : ''; ?>">
				<div class="ec_cart_billing_table_column"><input type="radio" name="billing_address_type[]" id="billing_address_type_same" value="0"<?php echo ( ! $shipping_selector ) ? ' checked="checked"' : ''; ?> onChange="ec_update_billing_address_display( '0', '<?php echo esc_attr( wp_create_nonce( 'wp-easycart-update-billing-address-type-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ); ?>' );" /> <?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_onepage', 'billing_same' ) ); ?></div>
			</div>
		</label>
		<label for="billing_address_type_different" class="ec_cart_full_radio">
			<div class="ec_cart_billing_table_row<?php echo ( $shipping_selector ) ? ' ec_billing_row_selected' : ''; ?>">
				<div class="ec_cart_billing_table_column"><input type="radio" name="billing_address_type[]" id="billing_address_type_different" value="1"<?php echo ( $shipping_selector ) ? ' checked="checked"' : ''; ?> onChange="ec_update_billing_address_display( '1', '<?php echo esc_attr( wp_create_nonce( 'wp-easycart-update-billing-address-type-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ); ?>' );" /> <?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_onepage', 'billing_different' ) ); ?></div>
			</div>
		</label>
		<div class="ec_cart_billing_table_address"<?php echo ( ! $shipping_selector ) ? ' style="display:none;"' : ''; ?>>
			<?php if ( get_option( 'ec_option_stripe_address_autocomplete' ) ) {
				$theme = get_option( 'ec_option_stripe_payment_theme' );
				$layout_type = get_option( 'ec_option_stripe_payment_layout' );'accordion'; // accordion, tabs
				$layout_default_collapsed = 'false';
				$layout_radios = 'false';
				$layout_spaced_accordion_items = 'false';
				$labels = 'floating'; // floating, above
				$payment_method_order = array( 'card', 'apple_pay', 'google_pay', 'klarna' );
			?>

				<div id="billing-address-element" class="ec_cart_stripe_address_is_init">
					<!-- Elements will create form elements here -->
				</div>
				<input type="hidden" id="ec_billing_name" value="<?php echo esc_attr( $GLOBALS['ec_cart_data']->cart_data->billing_first_name ); ?> <?php echo esc_attr( $GLOBALS['ec_cart_data']->cart_data->billing_last_name ); ?>" />
				<input type="hidden" id="ec_billing_last_name" value="<?php echo esc_attr( $GLOBALS['ec_cart_data']->cart_data->billing_last_name ); ?>" />
				<input type="hidden" id="ec_billing_company_name" value="<?php echo esc_attr( $GLOBALS['ec_cart_data']->cart_data->billing_company_name ); ?>" />
				<input type="hidden" id="ec_billing_address_line_1" value="<?php echo esc_attr( $GLOBALS['ec_cart_data']->cart_data->billing_address_line_1 ); ?>" />
				<input type="hidden" id="ec_billing_address_line_2" value="<?php echo esc_attr( $GLOBALS['ec_cart_data']->cart_data->billing_address_line_2 ); ?>" />
				<input type="hidden" id="ec_billing_city" value="<?php echo esc_attr( $GLOBALS['ec_cart_data']->cart_data->billing_city ); ?>" />
				<input type="hidden" id="ec_billing_state" value="<?php echo esc_attr( $GLOBALS['ec_cart_data']->cart_data->billing_state ); ?>" />
				<input type="hidden" id="ec_billing_zip" value="<?php echo esc_attr( $GLOBALS['ec_cart_data']->cart_data->billing_zip ); ?>" />
				<input type="hidden" id="ec_billing_country" value="<?php echo esc_attr( $GLOBALS['ec_cart_data']->cart_data->billing_country ); ?>" />
				<input type="hidden" id="ec_billing_phone" value="<?php echo esc_attr( $GLOBALS['ec_cart_data']->cart_data->billing_phone ); ?>" />
			<?php } else { // Non-Stripe Implementation Here ?>
				<?php if( get_option( 'ec_option_display_country_top' ) ){ ?>
				<div class="ec_cart_input_row">
					<label for="ec_cart_billing_country"><?php echo wp_easycart_language( )->get_text( 'cart_billing_information', 'cart_billing_information_country' ); ?>*</label>
					<?php $cartpage->display_billing_input( "country" ); ?>
					<div class="ec_cart_error_row" id="ec_cart_billing_country_error">
						<?php echo wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_select_your' ); ?> <?php echo wp_easycart_language( )->get_text( 'cart_billing_information', 'cart_billing_information_country' ); ?>
					</div>
				</div>
				<?php }?>
				<div class="ec_cart_input_row">
					<div class="ec_cart_input_left_half">
						<label for="ec_cart_billing_first_name"><?php echo wp_easycart_language( )->get_text( 'cart_billing_information', 'cart_billing_information_first_name' ); ?>*</label>
						<?php $cartpage->display_billing_input( "first_name" ); ?>
						<div class="ec_cart_error_row" id="ec_cart_billing_first_name_error">
							<?php echo wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ); ?> <?php echo wp_easycart_language( )->get_text( 'cart_billing_information', 'cart_billing_information_first_name' ); ?>
						</div>
					</div>
					<div class="ec_cart_input_right_half">
						<label for="ec_cart_billing_last_name"><?php echo wp_easycart_language( )->get_text( 'cart_billing_information', 'cart_billing_information_last_name' ); ?>*</label>
						<?php $cartpage->display_billing_input( "last_name" ); ?>
						<div class="ec_cart_error_row" id="ec_cart_billing_last_name_error">
							<?php echo wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ); ?> <?php echo wp_easycart_language( )->get_text( 'cart_billing_information', 'cart_billing_information_last_name' ); ?>
						</div>
					</div>
				</div>
				<?php if( get_option( 'ec_option_enable_company_name' ) ){ ?>
				<div class="ec_cart_input_row">
					<label for="ec_cart_billing_company_name"><?php echo wp_easycart_language( )->get_text( 'cart_billing_information', 'cart_billing_information_company_name' ); ?></label>
					<?php $cartpage->display_billing_input( "company_name" ); ?>
				</div>
				<?php }?>
				<?php if( get_option( 'ec_option_collect_vat_registration_number' ) ){ ?>
				<div class="ec_cart_input_row">
					<label for="ec_cart_billing_vat_registration_number"><?php echo wp_easycart_language( )->get_text( 'cart_billing_information', 'cart_billing_information_vat_registration_number' ); ?></label>
					<?php $cartpage->display_vat_registration_number_input( ); ?>
				</div>
				<?php }?>
				<div class="ec_cart_input_row">
					<label for="ec_cart_billing_address"><?php echo wp_easycart_language( )->get_text( 'cart_billing_information', 'cart_billing_information_address' ); ?>*</label>
					<?php $cartpage->display_billing_input( "address" ); ?>
				</div>
				<div class="ec_cart_error_row" id="ec_cart_billing_address_error">
					<?php echo wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ); ?> <?php echo wp_easycart_language( )->get_text( 'cart_billing_information', 'cart_billing_information_address' ); ?>
				</div>
				<?php if( get_option( 'ec_option_use_address2' ) ){ ?>
				<div class="ec_cart_input_row">
					<label for="ec_cart_billing_address2"><?php echo wp_easycart_language( )->get_text( 'cart_billing_information', 'cart_billing_information_address2' ); ?></label>
					<?php $cartpage->display_billing_input( "address2" ); ?>
				</div>
				<?php }?>
				<div class="ec_cart_input_row">
					<label for="ec_cart_billing_city"><?php echo wp_easycart_language( )->get_text( 'cart_billing_information', 'cart_billing_information_city' ); ?>*</label>
					<?php $cartpage->display_billing_input( "city" ); ?>
					<div class="ec_cart_error_row" id="ec_cart_billing_city_error">
						<?php echo wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ); ?> <?php echo wp_easycart_language( )->get_text( 'cart_billing_information', 'cart_billing_information_city' ); ?>
					</div>
				</div>
				<div class="ec_cart_input_row">
					<div class="ec_cart_input_left_half">
						<label for="ec_cart_billing_state"><?php echo wp_easycart_language( )->get_text( 'cart_billing_information', 'cart_billing_information_state' ); ?><span id="ec_billing_state_required">*</span></label>
						<?php $cartpage->display_billing_input( "state" ); ?>
						<div class="ec_cart_error_row" id="ec_cart_billing_state_error">
							<?php echo wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ); ?> <?php echo wp_easycart_language( )->get_text( 'cart_billing_information', 'cart_billing_information_state' ); ?>
						</div>
					</div>
					<div class="ec_cart_input_right_half">
						<label for="ec_cart_billing_zip"><?php echo wp_easycart_language( )->get_text( 'cart_billing_information', 'cart_billing_information_zip' ); ?>*</label>
						<?php $cartpage->display_billing_input( "zip" ); ?>
						<div class="ec_cart_error_row" id="ec_cart_billing_zip_error">
							<?php echo wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ); ?> <?php echo wp_easycart_language( )->get_text( 'cart_billing_information', 'cart_billing_information_zip' ); ?>
						</div>
					</div>
				</div>
				<?php if( !get_option( 'ec_option_display_country_top' ) ){ ?>
				<div class="ec_cart_input_row">
					<label for="ec_cart_billing_country"><?php echo wp_easycart_language( )->get_text( 'cart_billing_information', 'cart_billing_information_country' ); ?>*</label>
					<?php $cartpage->display_billing_input( "country" ); ?>
					<div class="ec_cart_error_row" id="ec_cart_billing_country_error">
						<?php echo wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_select_your' ); ?> <?php echo wp_easycart_language( )->get_text( 'cart_billing_information', 'cart_billing_information_country' ); ?>
					</div>
				</div>
				<?php }?>
				<?php if( get_option( 'ec_option_collect_user_phone' ) ){ ?>
				<div class="ec_cart_input_row">
					<label for="ec_cart_billing_phone"><?php echo wp_easycart_language( )->get_text( 'cart_billing_information', 'cart_billing_information_phone' ); ?><?php if( get_option( 'ec_option_user_phone_required' ) ){ ?>*<?php }?></label>
					<?php $cartpage->display_billing_input( "phone" ); ?>
					<?php if( get_option( 'ec_option_user_phone_required' ) ){ ?>
					<div class="ec_cart_error_row" id="ec_cart_billing_phone_error">
						<?php echo wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ); ?> <?php echo wp_easycart_language( )->get_text( 'cart_billing_information', 'cart_billing_information_phone' ); ?>
					</div>
					<?php }?>
				</div>
				<?php }?>
				<input type="hidden" id="wp_easycart_update_billing_nonce" value="<?php echo esc_attr( wp_create_nonce( 'wp-easycart-update-billing-address-type-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ); ?>" />

				<?php if( wp_easycart_cart_is_dynamic() ){ ?>
				<script type="text/javascript">
					wpeasycart_cart_billing_country_update( );
					jQuery( document.getElementById( 'ec_cart_billing_country' ) ).change( wpeasycart_cart_billing_country_update );
				</script>
				<?php }?>

				<?php do_action( 'wpeasycart_billing_after' ); ?>
			<?php } ?>
		</div>
	</div>
	<?php do_action( 'wpeasycart_checkout_fields', 'billing', $cartpage ); /* 6.0.2: checkout fields ( WP EasyCart PRO ) */ ?>
	<?php }?>

	<div class="ec_cart_header ec_cart_header_no_border">
		<?php echo wp_easycart_language( )->get_text( 'cart_payment_information', 'cart_payment_information_submit_order_button' )?>
	</div>

	<div class="ec_cart_error" id="ec_stripe_dynamic_error" style="display:none;">
		<div>
			<?php echo wp_easycart_language( )->get_text( "ec_errors", "payment_failed" ); ?>
		</div>
	</div>

	<?php do_action( 'wpeasycart_checkout_fields', 'review', $cartpage ); /* 6.0.2: checkout fields ( WP EasyCart PRO ) */ ?>

	<div class="ec_cart_error_row" id="ec_terms_error">
		<?php echo wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_payment_accept_terms' )?> 
	</div>
	<div class="ec_cart_input_row" id="ec_terms_row"<?php if( get_option( 'ec_option_payment_third_party' ) == "paypal" && $cartpage->get_selected_payment_method( ) == "third_party" && get_option( 'ec_option_paypal_enable_pay_now' ) == '1' && $cartpage->order_totals->grand_total > 0 ){ ?> style="display:none;"<?php }?>>
		<?php echo wp_easycart_language( )->get_text( 'cart_payment_information', 'cart_payment_information_checkout_text' )?>
	</div>

	<?php if( get_option( 'ec_option_require_terms_agreement' ) ){ ?>
	<div class="ec_cart_input_row ec_agreement_section" id="ec_terms_agreement_row">
		<input type="checkbox" name="ec_terms_agree" id="ec_terms_agree" value="1"  /> <?php echo wp_easycart_language( )->get_text( 'cart_payment_information', 'cart_payment_review_agree' )?>
	</div>
	<?php }else{ ?>
		<input type="hidden" name="ec_terms_agree" id="ec_terms_agree" value="2"  />
	<?php }?>

	<div class="ec_cart_error_row" id="ec_email_order2_error">
		<?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_onepage', 'invalid_email' ) ); ?>
	</div>

	<div class="ec_cart_error_row" id="ec_create_account_order_error">
		<?php echo wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_checkout_details_errors' )?>
	</div>

	<div class="ec_cart_error_row" id="ec_shipping_order_error">
		<?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_onepage', 'shipping_address_error' ) ); ?>
	</div>

	<div class="ec_cart_error_row" id="ec_shipping_method_order_error">
		<?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_onepage', 'no_shipping_method' ) ); ?>
	</div>

	<div class="ec_cart_error_row" id="ec_billing_order_error">
		<?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_onepage', 'billing_address_error' ) ); ?>
	</div>

	<?php if ( ( get_option( 'ec_option_payment_process_method' ) == "stripe" || get_option( 'ec_option_payment_process_method' ) == "stripe_connect" ) ) { 
		$cartpage->print_stripe_script_v2( true );
	} ?>

	<?php /* 6.0.2: Place order ( wpeasycart_onepage_place_order() in ec-store.js ): the server's check and the classic Place order post. */ ?>
	<div class="ec_cart_error" id="ec_onepage_order_errors" role="alert" style="display:none;"></div>
	<div id="wpeasycart_onepage_place" hidden
		data-check-nonce="<?php echo esc_attr( wp_create_nonce( 'wp-easycart-save-checkout-info-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ); ?>"
		data-submit-action="<?php echo esc_url( wpeasycart_links()->get_cart_page( 'checkout_submit_order' ) ); ?>"
		data-submit-nonce="<?php echo esc_attr( wp_create_nonce( 'wp-easycart-cart-submit-order-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ); ?>"
		data-free-nonce="<?php echo esc_attr( wp_create_nonce( 'wp-easycart-v2-complete-payment-main-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ); ?>"
		data-billing-nonce="<?php echo esc_attr( wp_create_nonce( 'wp-easycart-update-billing-address-type-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ); ?>"
		data-eway-key="<?php echo ( 'eway' == get_option( 'ec_option_payment_process_method' ) && get_option( 'ec_option_eway_use_rapid_pay' ) ) ? esc_attr( get_option( 'ec_option_eway_client_key' ) ) : ''; ?>"
		data-error="<?php echo esc_attr( wp_strip_all_tags( wp_easycart_language()->get_text( 'cart_onepage', 'place_order_error' ) ) ); ?>"
		data-total-changed="<?php echo esc_attr( wp_strip_all_tags( wp_easycart_language()->get_text( 'cart_onepage', 'total_changed' ) ) ); ?>"></div>
	<div class="ec_cart_error_row" id="ec_submit_order_error">
		<?php echo wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_payment_correct_errors' )?>
	</div>

	<?php /* 6.0.2: checkout protection's notice ( ec-checkout-protection.js ) goes here, full width above PayPal's buttons and Place order. */ ?>
	<div id="ec_checkout_protection_notice" class="wpec-protect-mount"></div>

	<?php if ( 'paypal' == get_option( 'ec_option_payment_third_party' ) && '1' == get_option( 'ec_option_paypal_enable_pay_now' ) && $cartpage->order_totals->grand_total > 0 ) { /* 6.0.2: PayPal's buttons when PayPal is chosen */ ?>
	<div class="ec_cart_paypal_row_v2" id="wpeasycart_submit_paypal_order_row"<?php if ( 'third_party' != $cartpage->get_selected_payment_method() ) { ?> style="display:none;"<?php } ?>>
		<div id="paypal-button-container-payment" style="width:100%; max-width:100%; margin:0;"></div>
		<div class="ec_cart_error" id="paypal-error" style="display:none;"><div><?php echo wp_kses_post( wp_easycart_language()->get_text( 'ec_errors', 'payment_failed' ) ); ?></div></div>
	</div>
	<?php /* 6.0.2: its own id ( the express PayPal button in the information step prints paypal-success-cover ). */ ?>
	<div id="paypal-success-cover-payment" style="display:none; cursor:default; position:fixed; top:0; left:0; width:100%; height:100%; z-index:999999; background-color: rgba(0, 0, 0, 0.8); color:#FFF;">
		<div style="position:absolute; top:50%; left:50%; margin:-19px 0 0 -19px;"><div style="height:30px; width:30px; box-sizing:content-box; animation:rotation .7s infinite linear; border:8px solid rgba(0, 0, 0, .2); border-top-color:#fff; border-radius:100%;"></div></div>
	</div>
	<?php $cartpage->print_paypal_express_button_code( true, false, 'paypal-button-container-payment' ); ?>
	<?php } ?>

	<div class="ec_cart_bottom_nav_v2<?php echo ( get_option( 'ec_option_onepage_checkout_tabbed' ) ) ? ' ec_cart_bottom_nav_tabbed' : ''; ?>" id="wpeasycart_submit_order_row"<?php if( $wpec_pickup_closed || ( get_option( 'ec_option_payment_third_party' ) == "paypal" && $cartpage->get_selected_payment_method( ) == "third_party" && get_option( 'ec_option_paypal_enable_pay_now' ) == '1' && $cartpage->order_totals->grand_total > 0 ) ){ /* 6.0.2: nothing to order while the restaurant is closed */ ?> style="display:none;"<?php }?>>
		<?php if( get_option( 'ec_option_onepage_checkout_tabbed' ) ) { ?>
		<div class="ec_cart_bottom_nav_left">
			<a href="#" class="ec_cart_bottom_nav_back" onclick="return wp_easycart_goto_page_v2( 'shipping', '<?php echo esc_attr( wp_create_nonce( 'wp-easycart-goto-cart-page-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ); ?>' );"><?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_onepage', 'return_to_shipping' ) ); ?></a>
		</div>
		<?php }?>
		<div class="ec_cart_bottom_nav_right ec_cart_button_column">
			<input type="submit" value="<?php echo wp_easycart_language( )->get_text( 'cart_payment_information', 'cart_payment_information_submit_order_button' )?>" class="ec_cart_button" id="ec_cart_submit_order" onclick="return ec_validate_submit_order( );" />
			<input type="submit" value="<?php echo esc_attr( strtoupper( wp_easycart_language( )->get_text( 'cart', 'cart_please_wait' ) ) ); ?>" class="ec_cart_button_working" id="ec_cart_submit_order_working" onclick="return false;" />
		</div>
	</div>

	<?php if ( get_option( 'ec_option_onepage_checkout_tabbed' ) ) { $cartpage->display_page_three_form_end( ); } ?>
	
	<?php if( $cartpage->use_manual_payment( ) ){?>
	<script type="text/javascript">
		var form = ( jQuery( document.getElementById( 'ec_submit_order_form' ) ).length ) ? document.getElementById( 'ec_submit_order_form' ) : document.getElementById( 'wpeasycart_checkout_details_form' );
		if ( form.wpecManualSubmit ) { /* 6.0.2: the section was drawn again: replace the listener, never add a second one */
			form.removeEventListener( 'submit', form.wpecManualSubmit );
		}
		form.wpecManualSubmit = function( event ) {
			var payment_method = 'credit_card';
			if ( jQuery( 'input:radio[name=ec_cart_payment_selection]:checked' ).length ) {
				payment_method = jQuery( 'input:radio[name=ec_cart_payment_selection]:checked' ).val();
			}
			if ( 'manual_bill' == payment_method ) {
				event.preventDefault();
				var ec_terms_agree = 0;
				if ( jQuery( document.getElementById( 'ec_terms_agree' ) ).length && jQuery( document.getElementById( 'ec_terms_agree' ) ).is( ':checked' ) ) {
					ec_terms_agree = 1;
				}
				var ec_cart_is_subscriber = 0;
				if ( jQuery( document.getElementById( 'ec_cart_is_subscriber' ) ).length && jQuery( document.getElementById( 'ec_cart_is_subscriber' ) ).is( ':checked' ) ) {
					ec_cart_is_subscriber = 1;
				}
				jQuery( document.getElementById( 'ec_cart_submit_order' ) ).hide( );
				jQuery( document.getElementById( 'ec_cart_submit_order_working' ) ).show( );
				jQuery( document.getElementById( 'manual-success-cover' ) ).show( );
				
				var data = {
					action: 'ec_ajax_complete_payment_manual',
					language: wpeasycart_ajax_object.current_language,
					ec_terms_agree: ec_terms_agree,
					ec_cart_is_subscriber: ec_cart_is_subscriber,
					nonce: '<?php echo esc_attr( wp_create_nonce( 'wp-easycart-complete-payment-manual-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ); ?>'
				};
				jQuery.ajax({url: wpeasycart_ajax_object.ajax_url, type: 'post', data: data, success: function( result ){
					wpeasycart_checkout_goto( result );
				}, error: function() {
					/* 6.0.2: the order call failed ( a network error, an expired page ): Place order comes back with the reason. */
					jQuery( document.getElementById( 'manual-success-cover' ) ).hide( );
					if ( 'function' === typeof wpeasycart_onepage_show_errors && document.getElementById( 'wpeasycart_onepage_place' ) ) {
						wpeasycart_onepage_show_errors( [ { message: document.getElementById( 'wpeasycart_onepage_place' ).getAttribute( 'data-error' ) } ] );
					} else {
						jQuery( document.getElementById( 'ec_cart_submit_order' ) ).show( );
						jQuery( document.getElementById( 'ec_cart_submit_order_working' ) ).hide( );
						ec_show_error( 'ec_submit_order' );
					}
				} } );

				return false;
			}
		};
		form.addEventListener( 'submit', form.wpecManualSubmit );
	</script>
	<?php }?>
<?php } // Close check for payment allowed ?>
