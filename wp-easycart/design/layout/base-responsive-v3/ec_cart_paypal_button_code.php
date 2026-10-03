<?php
/* 6.0.2: the one-page checkout's payment step has its own set of buttons ( its own container and render function ), and
   checks and saves the checkout before PayPal opens. */
$wpec_paypal_container = ( isset( $container_id ) && '' != $container_id ) ? $container_id : 'paypal-button-container';
$wpec_paypal_fn        = 'wpeasycart_paypal_render_button' . ( ( 'paypal-button-container' == $wpec_paypal_container ) ? '' : '_' . preg_replace( '/[^A-Za-z0-9_]/', '_', $wpec_paypal_container ) );
$wpec_paypal_onepage   = ! empty( $is_payment_page ) && function_exists( 'wp_easycart_onepage_active' ) && wp_easycart_onepage_active();
/* 6.0.2: each set of buttons has its own loading cover ( paypal-button-container-payment → paypal-success-cover-payment ), so
   the express buttons and the payment step's buttons on one page never share an id. */
$wpec_paypal_cover     = 'paypal-success-cover' . ( ( 'paypal-button-container' == $wpec_paypal_container ) ? '' : '-' . preg_replace( '/[^A-Za-z0-9_\-]/', '', str_replace( 'paypal-button-container-', '', $wpec_paypal_container ) ) );
?>
<script>
	/* The cover this set of buttons printed ( a template from before 6.0.2 prints paypal-success-cover for every set ). A
	   redrawn section prints it again: keep the newest one, moved to the page body. */
	function <?php echo esc_attr( $wpec_paypal_fn ); ?>_cover() {
		var covers = document.querySelectorAll( '[id="<?php echo esc_attr( $wpec_paypal_cover ); ?>"]' );
		if ( ! covers.length && '<?php echo esc_attr( $wpec_paypal_cover ); ?>' !== 'paypal-success-cover' ) {
			covers = document.querySelectorAll( '[id="paypal-success-cover"]' );
		}
		if ( ! covers.length ) {
			return null;
		}
		var cover = covers[ covers.length - 1 ];
		for ( var i = 0; i < covers.length - 1; i++ ) {
			if ( covers[ i ].parentNode === document.body ) {
				covers[ i ].parentNode.removeChild( covers[ i ] );
			}
		}
		if ( cover.parentNode !== document.body ) {
			document.body.appendChild( cover );
		}
		return cover;
	}
	<?php echo esc_attr( $wpec_paypal_fn ); ?>_cover();
	/* 6.0.2: the error box next to these buttons ( the payment step's #paypal-error, the express buttons' own ). */
	function <?php echo esc_attr( $wpec_paypal_fn ); ?>_error( show ) {
		var holder = jQuery( document.getElementById( '<?php echo esc_attr( $wpec_paypal_container ); ?>' ) ).closest( '#wpeasycart_submit_paypal_order_row, .ec_cart_express_checkout' );
		var box = holder.length ? holder.find( '#paypal-error, .wpec-paypal-error' ).first() : jQuery( document.getElementById( 'paypal-error' ) );
		box.toggle( !! show );
	}
	/* 6.0.2: opening PayPal saves PayPal as the checkout's payment method. A shopper who closes it and pays another way gets the
	   page's own payment choice back ( the checked option, else none ), so their payment's fees and totals are the ones used. */
	function <?php echo esc_attr( $wpec_paypal_fn ); ?>_restore_method() {
		var pick = jQuery( 'input[name="ec_cart_payment_selection"]:checked' );
		jQuery.ajax( {
			url: wpeasycart_ajax_object.ajax_url,
			type: 'post',
			data: {
				action: 'ec_ajax_update_payment_method',
				payment_method: pick.length ? String( pick.val() ) : '',
				nonce: '<?php echo esc_attr( wp_create_nonce( 'wp-easycart-update-payment-method-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ); ?>'
			},
			success: function( response ) {
				try {
					var result = JSON.parse( response );
					if ( result && result.cart_data && 'function' === typeof ec_update_cart ) {
						ec_update_cart( result.cart_data );
					}
				} catch ( e ) {}
			}
		} );
	}
	function <?php echo esc_attr( $wpec_paypal_fn ); ?>( ){
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
						orderid = String( response ).trim();
					}
				} );
				/* 6.0.2: the store refused to start the payment ( checkout protection, or PayPal said no ): say so here and stop,
				   rather than hand PayPal the word "error" as an order id. */
				if ( ! orderid || 'error' === orderid ) {
					<?php echo esc_attr( $wpec_paypal_fn ); ?>_error( true );
					throw new Error( 'wpeasycart_paypal_create_order' );
				}
				<?php echo esc_attr( $wpec_paypal_fn ); ?>_error( false );
				return orderid;
			},<?php
			if ( ( ! $is_payment_page || wp_easycart_onepage_active() ) && get_option( 'ec_option_use_shipping' ) && $this->cart->shippable_total_items > 0 ) { ?>
			onShippingChange( data, actions ) {
				var allowed_countries = [<?php
				$first_country = true;
				foreach ( $GLOBALS['ec_countries']->countries as $country ) {
					if ( ! $first_country ) {
						echo ',';
					}
					echo '"' . esc_attr( $country->iso2_cnt ) . '"';
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
			<?php }?><?php if ( $is_payment_page && ! $wpec_paypal_onepage ) { ?>
			onClick: function( data, actions ) {<?php if ( get_option( 'ec_option_require_terms_agreement' ) ) { ?>
				if ( ! document.querySelector( '#ec_terms_agree' ).checked ) {
					jQuery( '#ec_terms_error' ).show();
				} else {
					jQuery( '#ec_terms_error' ).hide();
				}<?php }?>
				/* 6.0.2: the page's own checks and saves ( checkout fields ) first: PayPal finishes without posting the form. */
				var checks = window.wpeasycart_checkout_validators || [];
				for ( var i = 0; i < checks.length; i++ ) {
					if ( 'function' === typeof checks[ i ] && false === checks[ i ]() ) {
						return actions.reject();
					}
				}
				var saves = [];
				jQuery.each( window.wpeasycart_checkout_savers || [], function( j, saver ) {
					if ( 'function' === typeof saver ) {
						try {
							saves.push( saver() );
						} catch ( e ) {}
					}
				} );
				if ( ! saves.length ) {
					return actions.resolve();
				}
				return new Promise( function( resolve ) {
					jQuery.when.apply( jQuery, saves ).always( function() {
						resolve( actions.resolve() );
					} );
				} );
			},<?php }?><?php if ( $wpec_paypal_onepage ) { ?>
			onClick: function( data, actions ) {
				return new Promise( function( resolve ) {
					wpeasycart_onepage_ready_to_pay().then( function( ok ) {
						resolve( ok ? actions.resolve() : actions.reject() );
					} );
				} );
			},<?php }?>
			onApprove: function( data, actions ) {
				var cover = <?php echo esc_attr( $wpec_paypal_fn ); ?>_cover();
				jQuery( cover ).delay( 600 ).fadeIn( 'slow' );
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
					jQuery( cover ).stop( true ).hide();
					<?php echo esc_attr( $wpec_paypal_fn ); ?>_error( true );
				}
			},
			onCancel: function() {
				jQuery( <?php echo esc_attr( $wpec_paypal_fn ); ?>_cover() ).stop( true ).hide();
				<?php echo esc_attr( $wpec_paypal_fn ); ?>_restore_method();
			},
			onError: function(data, actions) {
				jQuery( <?php echo esc_attr( $wpec_paypal_fn ); ?>_cover() ).stop( true ).hide();
				<?php echo esc_attr( $wpec_paypal_fn ); ?>_error( true );
				<?php echo esc_attr( $wpec_paypal_fn ); ?>_restore_method();
				if ( window.console && console.debug ) {
					console.debug( data );
				}
			},
		} ).render( '#<?php echo esc_attr( $wpec_paypal_container ); ?>' );
	}
	jQuery(document).ready(function( $ ){
		setTimeout( <?php echo esc_attr( $wpec_paypal_fn ); ?>, 1 ); // Delay load for mmenu sites
	});
</script>