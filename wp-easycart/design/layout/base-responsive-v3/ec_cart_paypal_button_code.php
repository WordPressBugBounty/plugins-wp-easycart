<script>
	jQuery( document.getElementById( 'paypal-success-cover' ) ).appendTo( document.body );
	function wpeasycart_paypal_render_button( ){
		paypal.Buttons( {
			env: '<?php if( get_option( 'ec_option_paypal_use_sandbox' ) == '1' ){ echo "sandbox"; }else{ echo "production"; } ?>',
			commit: false,
			style: {
				size:  'responsive', // small | medium | large | responsive
				color: '<?php echo esc_attr( get_option( 'ec_option_paypal_button_color' ) ); ?>', // gold | blue | silver | black
				shape: '<?php echo esc_attr( get_option( 'ec_option_paypal_button_shape' ) ); ?>',  // pill | rect
				tagline: false,
				layout: <?php if( $is_payment_page || $is_horizontal ){ echo "'horizontal'"; }else{ ?>'vertical'<?php }?>
			},
			client: {
				<?php if( get_option( 'ec_option_paypal_use_sandbox' ) == '1' ){ ?>sandbox: '<?php if( get_option( 'ec_option_paypal_sandbox_merchant_id' ) != '' ){ 
					// APP ID NOT PUBLIC OR SECRET KEY! THIS TELLS PAYPAL THE PARTER THE MERCHANT IS PROCESSING WITH. MERCHANT DESCRIBED BELOW, WHICH IS SPECIFIC TO THE MERCHANT. THEY HAVE CONNECTED WITH THE WP EasyCart PAYPAL APP. CANNOT USE ONE WITHOUT THE OTHER. THIS WAS CREATED WITH PAYPAL IN ORDER TO ALLOW FOR QUICK ONBOARDING, WITHOUT PROGRAMMING EXPERIENCE AND PAYPAL
					// For more information: https://developer.paypal.com/docs/platforms/seller-onboarding/
					echo 'Acet2ZT0h9IALSY-n76aGnnjCYp3E3myqcmrJ7tfqJiLUvLzXKQMabHN9uLr2W_N03txVHuvkpsQDwhw';
				}else{
					// THIS IS FOR THOSE THAT TAKE THE TIME TO CREATE THEIR OWN PAYPAL APP, NOT THE PUBLIC WP EASYCART APP
					echo esc_attr( get_option( 'ec_option_paypal_sandbox_app_id' ) );
				} ?>'<?php }?>
				<?php if( get_option( 'ec_option_paypal_use_sandbox' ) == '0' ){ ?>production: '<?php if( get_option( 'ec_option_paypal_production_merchant_id' ) != '' ){ 
					// APP ID NOT PUBLIC OR SECRET KEY! THIS TELLS PAYPAL THE PARTER THE MERCHANT IS PROCESSING WITH. MERCHANT DESCRIBED BELOW, WHICH IS SPECIFIC TO THE MERCHANT. THEY HAVE CONNECTED WITH THE WP EasyCart PAYPAL APP. CANNOT USE ONE WITHOUT THE OTHER. THIS WAS CREATED WITH PAYPAL IN ORDER TO ALLOW FOR QUICK ONBOARDING, WITHOUT PROGRAMMING EXPERIENCE AND PAYPAL
					// For more information: https://developer.paypal.com/docs/platforms/seller-onboarding/
					echo 'AXLwqGbEI4j2xLhSOPgUhJYNQkkooPmPUWH9NDIVUZ7PxY6yKPYGrBCELYlSdTSepUaVb_r_M0IdPSJa';
				}else{
					// THIS IS FOR THOSE THAT TAKE THE TIME TO CREATE THEIR OWN PAYPAL APP, NOT THE PUBLIC WP EASYCART APP
					echo esc_attr( get_option( 'ec_option_paypal_production_app_id' ) ); 
				} ?>'<?php }?>
			},
			createOrder() {
				var data = {
					action: 'wp_easycart_ajax_init_paypal_express',
					ec_cart_form_nonce: '<?php echo esc_attr( wp_create_nonce( 'wp-easycart-paypal-init-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ); ?>',
					is_payment_page: <?php echo esc_attr( ( isset( $is_payment_page ) ) ? (int) $is_payment_page : 0 ); ?>
				};
				var orderid = null;
				jQuery.ajax( { 
					url: wpeasycart_ajax_object.ajax_url, 
					type: 'post', 
					data: data,
					async: false,
					success: function( response ){
						orderid = response.trim();
					}
				} );
				return orderid;
			},<?php
			if ( ( ! $is_payment_page || get_option( 'ec_option_onepage_checkout' ) ) && get_option( 'ec_option_use_shipping' ) && $this->cart->shippable_total_items > 0 ) { ?>
			onShippingChange( data, actions ) {
				var allowed_countries = [<?php
				$first_country = true;
				foreach ( $GLOBALS['ec_countries']->countries as $country ) {
					if ( ! $first_country ) {
						echo ',';
					}
					echo '"' . $country->iso2_cnt . '"';
					$first_country = false;
				} ?>];
				if ( ! allowed_countries.includes( data.shipping_address.country_code ) ) {
					return actions.reject();
				}
				var requestData = {
					action: 'wp_easycart_ajax_shipping_paypal_express',
					ec_cart_form_nonce: '<?php echo esc_attr( wp_create_nonce( 'wp-easycart-paypal-shipping-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ); ?>',
					orderID: data.orderID,
					shippingAddress: data.shipping_address,
					selectedRate: data.selected_shipping_option,
				};
				var is_valid_address = true;
				jQuery.ajax( { 
					url: wpeasycart_ajax_object.ajax_url,
					type: 'post', 
					data: requestData,
					async: false,
					success: function( response ){
						var json_result = JSON.parse( response );
						ec_update_cart( json_result.cart_data );
						for ( var j = 0; j < json_result.cart_data.cart.length; j++ ) {
							if ( 1 == Number( json_result.cart_data.cart[ j ].shipping_restricted ) ) {
								is_valid_address = false;
							}
						}
					}
				} );
				if ( ! is_valid_address ) {
					return actions.reject();
				}
			},<?php } else { ?>
			onShippingChange( data, actions ) {
				var requestData = {
					action: 'wp_easycart_ajax_shipping_paypal_express',
					ec_cart_form_nonce: '<?php echo esc_attr( wp_create_nonce( 'wp-easycart-paypal-shipping-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ); ?>',
					orderID: data.orderID,
					shippingAddress: data.shipping_address,
					selectedRate: data.selected_shipping_option,
				};
				jQuery.ajax( { 
					url: wpeasycart_ajax_object.ajax_url,
					type: 'post', 
					data: requestData,
					async: false,
					success: function( response ){
						var json_result = JSON.parse( response );
						ec_update_cart( json_result.cart_data );
					}
				} );
			},<?php }?><?php if ( $is_payment_page && get_option( 'ec_option_require_terms_agreement' ) ) { ?>
			onInit(data,actions) {
				actions.disable();
				document.querySelector( '#ec_terms_agree' ).addEventListener( 'change', function( event ) {
					if ( event.target.checked ) {
						actions.enable();
					} else {
						actions.disable();
					}
				} );
			},
			onClick() {
				if ( ! document.querySelector( '#ec_terms_agree' ).checked ) {
					jQuery( '#ec_terms_error' ).show();
				} else {
					jQuery( '#ec_terms_error' ).hide();
				}
			},<?php }?>
			onApprove: function( data, actions ) {
				jQuery( document.getElementById( 'paypal-success-cover' ) ).delay( 600 ).fadeIn( 'slow' );
				var data = {
					action: 'wp_easycart_ajax_complete_paypal_express',
					ec_cart_form_nonce: '<?php echo esc_attr( wp_create_nonce( 'wp-easycart-paypal-complete-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ); ?>',
					token: data.orderID,
					payerID: data.payerID
				};
				var is_error = false;
				jQuery.ajax( { 
					url: wpeasycart_ajax_object.ajax_url,
					type: 'post', 
					data: data,
					async: false,
					success: function( response ){
						if ( 'error' == response ) {
							is_error = true;
						} else {
							window.location = response;
						}
					}
				} );
				if ( is_error ){
					jQuery( document.getElementById( 'paypal-success-cover' ) ).hide();
					jQuery( document.getElementById( 'paypal-error' ) ).show();
				}
			},
			onError: function(data, actions) {
				console.debug(data);
			},
		} ).render( '#paypal-button-container' );
	}
	jQuery(document).ready(function( $ ){
		setTimeout( wpeasycart_paypal_render_button, 1 ); // Delay load for mmenu sites
	});
</script>