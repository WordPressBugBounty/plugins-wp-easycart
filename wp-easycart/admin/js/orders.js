var ec_admin_order_details_order_info_show = false;
var ec_admin_order_details_shipping_method_show = false;
var ec_admin_order_details_customer_notes_show = false;

jQuery( document ).ready( function( ){
	jQuery( document.getElementById( 'ec_admin_order_details_shipping_method_save' ) ).on( 'click', ec_admin_process_shipping_method );
	jQuery( document.getElementById( 'ec_order_user_id' ) ).select2({
		ajax: {
			url: wpeasycart_admin_ajax_object.ajax_url,
			dataType: 'json',
			delay: 250,
			type: 'post',
			data: function( params ){
				return {
					q: params.term, // search term
					action: 'ec_admin_ajax_get_order_users',
					wp_easycart_nonce: ec_admin_get_value( 'wp_easycart_order_details_nonce', 'text' )
				};
			},
			processResults: function( data, params ){
				return {
					results: data.items
				};
			}
		},
		placeholder: 'Search for a User',
		minimumInputLength: 1
	}).on( 'change', function( ){
		/* 6.0.0: the V2 details drawer shows its own in-drawer busy state; the V1 full-page loader rendered behind it. */
		var ec_order_user_v2 = ( document.getElementById( 'ecodv2_edit_drawer' ) && 'function' === typeof window.ecodv2_order_user_busy );
		if ( ec_order_user_v2 ) {
			window.ecodv2_order_user_busy( true );
		} else {
			jQuery( document.getElementById( "ec_admin_shipping_details" ) ).fadeIn( 'fast' );
		}

		var data = {
			action: 'ec_admin_ajax_update_order_user',
			user_id: jQuery( this ).val( ),
			order_id: jQuery( document.getElementById( 'order_id' ) ).val( ),
			wp_easycart_nonce: ec_admin_get_value( 'wp_easycart_order_details_nonce', 'text' )
		};

		jQuery.ajax({url: wpeasycart_admin_ajax_object.ajax_url, type: 'post', data: data, dataType: 'json', success: function( response ){
			if ( ! ec_order_user_v2 ) {
				ec_admin_hide_loader( 'ec_admin_shipping_details' );
			}
			/* 6.0.0: the response carries the account-dependent fragments; the V2 details screen repaints them. */
			if ( 'function' === typeof window.ecodv2_apply_order_user ) {
				window.ecodv2_apply_order_user( response );
			}
			if ( jQuery( document.getElementById( 'wpeasycart_order_history_refresh' ) ).length ) {
				ec_order_history_refresh();
			}
		}, error: function( xhr ){
			if ( ! ec_order_user_v2 ) {
				ec_admin_hide_loader( 'ec_admin_shipping_details' );
			}
			if ( 'function' === typeof window.ecodv2_apply_order_user ) {
				window.ecodv2_apply_order_user( ( xhr && xhr.responseJSON ) ? xhr.responseJSON : { success: false } );
			}
		} } );

		return false;
	} );
	jQuery( '.ec_admin_order_details_order_status_line > #orderstatus_id' ).select2({
		escapeMarkup: function(markup) {
			return markup;
		},
		templateResult: function( data ) {
			var is_approved = ( jQuery( '#orderstatus_id option[value="' + data.id + '"]' ).attr( 'isapproved' ) ) ? jQuery( '#orderstatus_id option[value="' + data.id + '"]' ).attr( 'isapproved' ) : 0;
			var return_text = data.text;
			if( '0' == data.id || 'add-new' == data.id ) {
				// do not add html
			} else if ( '17' == data.id ) {
				return_text += ' <span class="payment-neutral">' + jQuery( '#orderstatus_id' ).attr( 'data-partial-refund' ) + '</span>';
			} else if ( '16' == data.id ) {
				return_text += ' <span class="payment-bad">' + jQuery( '#orderstatus_id' ).attr( 'data-refunded' ) + '</span>';
			} else if ( '1' == is_approved ) {
				return_text += ' <span class="payment-paid">' + jQuery( '#orderstatus_id' ).attr( 'data-paid' ) + '</span>';
			} else if ( '19' == data.id ) {
				return_text += ' <span class="payment-bad">' + jQuery( '#orderstatus_id' ).attr( 'data-cancelled' ) + '</span>';
			} else if ( '7' == data.id || '9' == data.id ) {
				return_text += ' <span class="payment-bad">' + jQuery( '#orderstatus_id' ).attr( 'data-failed' ) + '</span>';
			}else {
				return_text += ' <span class="payment-processing">' + jQuery( '#orderstatus_id' ).attr( 'data-pending' ) + '</span>';
			}
			return return_text;
		},
		templateSelection: function( data ) {
			var return_text = data.text;
			if( '0' == data.id || 'add-new' == data.id ) {
				// do not add html
			} else if ( '17' == data.id ) {
				return_text += ' <span class="payment-neutral">' + jQuery( '#orderstatus_id' ).attr( 'data-partial-refund' ) + '</span>';
			} else if ( '16' == data.id ) {
				return_text += ' <span class="payment-bad">' + jQuery( '#orderstatus_id' ).attr( 'data-refunded' ) + '</span>';
			} else if ( '1' == data.element.attributes.isapproved.value ) {
				return_text += ' <span class="payment-paid">' + jQuery( '#orderstatus_id' ).attr( 'data-paid' ) + '</span>';
			} else if ( '19' == data.id ) {
				return_text += ' <span class="payment-bad">' + jQuery( '#orderstatus_id' ).attr( 'data-cancelled' ) + '</span>';
			} else if ( '7' == data.id || '9' == data.id ) {
				return_text += ' <span class="payment-bad">' + jQuery( '#orderstatus_id' ).attr( 'data-failed' ) + '</span>';
			}else {
				return_text += ' <span class="payment-processing">' + jQuery( '#orderstatus_id' ).attr( 'data-pending' ) + '</span>';
			}
			return return_text;
		},
	}).parent().find( 'span.select2' ).addClass( 'wpeasycart-admin-orderstatus' );
	jQuery( '.ec_order_download_key' ).select2({
		ajax: {
			url: wpeasycart_admin_ajax_object.ajax_url,
			dataType: 'json',
			delay: 250,
			type: 'post',
			data: function( params ){
				return {
					q: params.term, // search term
					action: 'ec_admin_ajax_get_download_keys'
				};
			},
			processResults: function( data, params ){
				return {
					results: data.items
				};
			}
		},
		placeholder: 'Search for a Download Key',
		minimumInputLength: 1
	}).on( 'change', function( ){
		jQuery( document.getElementById( "ec_admin_order_management" ) ).fadeIn( 'fast' );

		var data = {
			action: 'ec_admin_ajax_update_order_download_key',
			download_key: jQuery( this ).val( ),
			orderdetail_id: jQuery( this ).attr( 'data-orderdetail-id' ),
			wp_easycart_nonce: ec_admin_get_value( 'wp_easycart_order_details_nonce', 'text' )
		};

		jQuery.ajax({url: wpeasycart_admin_ajax_object.ajax_url, type: 'post', data: data, success: function(data){ 
			ec_admin_hide_loader( 'ec_admin_order_management' );
			if ( jQuery( document.getElementById( 'wpeasycart_order_history_refresh' ) ).length ) {
				ec_order_history_refresh();
			}
		} } );

		return false;
	} );
	jQuery( '.wpeasycart-timeline-item-info > a' ).on( 'click', function( ){ return false; } );
} );

/* 6.0.0: the V2 order details screen confirms first, shows a busy state on the link and reports
   through the V2 toast; the legacy screen keeps its full-page loader. */
function ec_admin_resend_giftcard( script_order_id, script_orderdetail_id, link ){
	var ec_gift_v2 = ( document.getElementById( 'ecodv2_edit_drawer' ) && 'function' === typeof window.ecodv2_save_toast );
	var email = ( link && link.getAttribute( 'data-email' ) ) ? link.getAttribute( 'data-email' ) : '';

	if ( ec_gift_v2 ) {
		if ( ! window.confirm( email ? 'Send the gift card email to ' + email + ' again?' : 'Send the gift card email again?' ) ) {
			return false;
		}
		if ( link ) {
			link.classList.add( 'is-busy' );
		}
	} else {
		jQuery( document.getElementById( "ec_admin_order_management" ) ).fadeIn( 'fast' );
	}

	var data = {
		action: 'ec_admin_ajax_resend_giftcard_email',
		order_id: script_order_id,
		orderdetail_id: script_orderdetail_id,
		wp_easycart_nonce: ec_admin_get_value( 'wp_easycart_order_details_nonce', 'text' )
	};

	jQuery.ajax({url: wpeasycart_admin_ajax_object.ajax_url, type: 'post', data: data, dataType: 'json', success: function( response ){
		if ( link ) {
			link.classList.remove( 'is-busy' );
		}
		if ( ec_gift_v2 ) {
			if ( response && response.success ) {
				window.ecodv2_save_toast( ( response.data && response.data.message ) ? response.data.message : 'Gift card email sent.' );
			} else {
				window.ecodv2_save_toast( ( response && response.data && response.data.message ) ? response.data.message : 'The gift card email could not be sent.', true );
			}
		} else {
			ec_admin_hide_loader( 'ec_admin_order_management' );
		}
		if ( jQuery( document.getElementById( 'wpeasycart_order_history_refresh' ) ).length ) {
			ec_order_history_refresh();
		}
	}, error: function(){
		if ( link ) {
			link.classList.remove( 'is-busy' );
		}
		if ( ec_gift_v2 ) {
			window.ecodv2_save_toast( 'The gift card email could not be sent.', true );
		} else {
			ec_admin_hide_loader( 'ec_admin_order_management' );
		}
	} } );

	return false;
}
function ec_admin_copy_billing_address(button) {
	 document.getElementById( "shipping_first_name" ).value = document.getElementById( "billing_first_name" ).value;
	 document.getElementById( "shipping_last_name" ).value = document.getElementById( "billing_last_name" ).value;
	 document.getElementById( "shipping_company_name" ).value = document.getElementById( "billing_company_name" ).value;
	 document.getElementById( "shipping_address_line_1" ).value = document.getElementById( "billing_address_line_1" ).value;
	 document.getElementById( "shipping_address_line_2" ).value = document.getElementById( "billing_address_line_2" ).value;
	 document.getElementById( "shipping_city" ).value = document.getElementById( "billing_city" ).value;
	 document.getElementById( "shipping_state" ).value = document.getElementById( "billing_state" ).value;
	 document.getElementById( "shipping_country" ).value = document.getElementById( "billing_country" ).value;
	 document.getElementById( "shipping_zip" ).value = document.getElementById( "billing_zip" ).value;
	 document.getElementById( "shipping_phone" ).value = document.getElementById( "billing_phone" ).value;
}

function ec_admin_edit_order_status(button) {
	jQuery( document.getElementById( "ec_admin_order_management" ) ).fadeIn( 'fast' );
	var orderstatus_id = ec_admin_get_value( 'orderstatus_id', 'select' );
	var is_approved = ( jQuery( '#orderstatus_id option:selected' ).attr( 'isapproved' ) ) ? jQuery( '#orderstatus_id option:selected' ).attr( 'isapproved' ) : 0;

	if( ec_admin_get_value( 'orderstatus_id', 'select' ) == 'add-new' ){
		window.location.href = 'admin.php?page=wp-easycart-settings&subpage=checkout';

	}else{
		jQuery( '#wpeasycart-payment-status' ).removeClass( 'payment-neutral' ).removeClass( 'payment-bad' ).removeClass( 'payment-paid' );
		
		if( '0' == orderstatus_id || 'add-new' == orderstatus_id ) {
			jQuery( '#wpeasycart-payment-status' ).addClass( 'payment-neutral' ).html( jQuery( '#orderstatus_id' ).attr( 'data-pending' ) );
		} else if ( '17' == orderstatus_id ) {
			jQuery( '#wpeasycart-payment-status' ).addClass( 'payment-neutral' ).html( jQuery( '#orderstatus_id' ).attr( 'data-partial-refund' ) );
		} else if ( '16' == orderstatus_id ) {
			jQuery( '#wpeasycart-payment-status' ).addClass( 'payment-bad' ).html( jQuery( '#orderstatus_id' ).attr( 'data-refunded' ) );
		} else if ( '1' == is_approved ) {
			jQuery( '#wpeasycart-payment-status' ).addClass( 'payment-paid' ).html( jQuery( '#orderstatus_id' ).attr( 'data-paid' ) );
		} else if ( '19' == orderstatus_id ) {
			jQuery( '#wpeasycart-payment-status' ).addClass( 'payment-bad' ).html( jQuery( '#orderstatus_id' ).attr( 'data-cancelled' ) );
		} else if ( '7' == orderstatus_id || '9' == orderstatus_id ) {
			jQuery( '#wpeasycart-payment-status' ).addClass( 'payment-bad' ).html( jQuery( '#orderstatus_id' ).attr( 'data-failed' ) );
		}else {
			jQuery( '#wpeasycart-payment-status' ).addClass( 'payment-processing' ).html( jQuery( '#orderstatus_id' ).attr( 'data-pending' ) );
		}

		var data = {
			action: 'ec_admin_ajax_edit_orderstatus',
			order_id: ec_admin_get_value( 'order_id', 'text' ),
			orderstatus_id: ec_admin_get_value( 'orderstatus_id', 'select' ),
			wp_easycart_nonce: ec_admin_get_value( 'wp_easycart_order_details_nonce', 'text' )
		};

		jQuery.ajax({url: wpeasycart_admin_ajax_object.ajax_url, type: 'post', data: data, success: function(data){ 
			ec_admin_hide_loader( 'ec_admin_order_management' );
			if ( jQuery( document.getElementById( 'wpeasycart_order_history_refresh' ) ).length ) {
				ec_order_history_refresh();
			}
		} } );
	}

	return false;
}

function ec_admin_process_order_info( ){
	jQuery( document.getElementById( "ec_admin_order_management" ) ).fadeIn( 'fast' );

	var data = {
		action: 'ec_admin_ajax_edit_order_info',
		order_id: ec_admin_get_value( 'order_id', 'text' ),
		order_weight: ec_admin_get_value( 'order_weight', 'text' ),
		giftcard_id: ec_admin_get_value( 'giftcard_id', 'text' ),
		promo_code: ec_admin_get_value( 'promo_code', 'text' ),
		order_notes: ec_admin_get_value( 'order_notes', 'text' ),
		wp_easycart_nonce: ec_admin_get_value( 'wp_easycart_order_details_nonce', 'text' )
	};

	jQuery.ajax({url: wpeasycart_admin_ajax_object.ajax_url, type: 'post', data: data, success: function(data){
		ec_admin_hide_loader( 'ec_admin_order_management' );
		if ( jQuery( document.getElementById( 'wpeasycart_order_history_refresh' ) ).length ) {
			ec_order_history_refresh();
		}
	} } );

	ec_admin_order_details_order_info_show = false;
}

function ec_admin_process_shipping_method( ){

	if( ec_admin_order_details_shipping_method_show ){
		jQuery( document.getElementById( "ec_admin_shipping_details" ) ).fadeIn( 'fast' );
		if( ec_admin_get_value( 'use_expedited_shipping', 'select' ) == '1' ){
			jQuery( document.getElementById( 'ec_admin_order_details_shipping_type' ) ).html( 'Expedite Shipping<br />' );
		}else{
			jQuery( document.getElementById( 'ec_admin_order_details_shipping_type' ) ).html( '' );
		}
		if( ec_admin_get_value( 'shipping_carrier', 'text' ) != '' ){
			jQuery( document.getElementById( 'ec_admin_order_details_shipping_carrier' ) ).html( ec_admin_get_value( 'shipping_carrier', 'text' ) + '<br />' );
		}else{
			jQuery( document.getElementById( 'ec_admin_order_details_shipping_carrier' ) ).html( '' );
		}
		if( ec_admin_get_value( 'shipping_method', 'text' ) != '' ){
			jQuery( document.getElementById( 'ec_admin_order_details_shipping_method' ) ).html( ec_admin_get_value( 'shipping_method', 'text' ) + '<br />' );
		}else{
			jQuery( document.getElementById( 'ec_admin_order_details_shipping_method' ) ).html( '' );
		}
		if( ec_admin_get_value( 'tracking_number', 'text' ) != '' ){
			jQuery( document.getElementById( 'ec_admin_order_details_tracking_number' ) ).html( ec_admin_get_value( 'tracking_number', 'text' ) );
			jQuery( document.getElementById( 'ec_admin_order_details_shipping_empty_message' ) ).hide( );
		}else{
			jQuery( document.getElementById( 'ec_admin_order_details_tracking_number' ) ).html( '' );
			jQuery( document.getElementById( 'ec_admin_order_details_shipping_empty_message' ) ).show( );
		}
		var data = {
			action: 'ec_admin_ajax_edit_shipping_method_info',
			order_id: ec_admin_get_value( 'order_id', 'text' ),
			use_expedited_shipping: ec_admin_get_value( 'use_expedited_shipping', 'select' ),
			shipping_method: ec_admin_get_value( 'shipping_method', 'text' ),
			shipping_carrier: ec_admin_get_value( 'shipping_carrier', 'text' ),
			tracking_number: ec_admin_get_value( 'tracking_number', 'text' ),
			wp_easycart_nonce: ec_admin_get_value( 'wp_easycart_order_details_nonce', 'text' )
		};

		jQuery.ajax({url: wpeasycart_admin_ajax_object.ajax_url, type: 'post', data: data, success: function(data){
			jQuery( document.getElementById( 'ec_admin_order_details_shipping_method_form' ) ).hide( );
			jQuery( document.getElementById( 'ec_admin_view_shipping_method' ) ).show( );
			ec_admin_hide_loader( 'ec_admin_shipping_details' );
			if ( jQuery( document.getElementById( 'wpeasycart_order_history_refresh' ) ).length ) {
				ec_order_history_refresh();
			}
		} } );

		ec_admin_order_details_shipping_method_show = false;

	}else{
		jQuery( document.getElementById( 'ec_admin_order_details_shipping_method_form' ) ).show( );
		jQuery( document.getElementById( 'ec_admin_view_shipping_method' ) ).hide( );
		ec_admin_order_details_shipping_method_show = true;
	}
}

function ec_admin_process_customer_notes( ){
	if( ec_admin_order_details_customer_notes_show ){
		jQuery( document.getElementById( "ec_admin_shipping_details" ) ).fadeIn( 'fast' );

		jQuery( document.getElementById( 'ec_admin_order_details_customer_notes' ) ).html( ec_admin_get_value( 'order_customer_notes', 'text' ).replace( /\n/g, '<br />' ) );
		if( ec_admin_get_value( 'order_customer_notes', 'text' ) != '' ){
			jQuery( document.getElementById( 'ec_admin_order_details_customer_notes_empty_message' ) ).hide( );
		}else{
			jQuery( document.getElementById( 'ec_admin_order_details_customer_notes_empty_message' ) ).show( );
		}

		var data = {
			action: 'ec_admin_ajax_edit_customer_notes',
			order_id: ec_admin_get_value( 'order_id', 'text' ),
			order_customer_notes: ec_admin_get_value( 'order_customer_notes', 'text' ),
			wp_easycart_nonce: ec_admin_get_value( 'wp_easycart_order_details_nonce', 'text' )
		};

		jQuery.ajax({url: wpeasycart_admin_ajax_object.ajax_url, type: 'post', data: data, success: function(data){
			jQuery( document.getElementById( 'ec_admin_order_details_customer_notes_form' ) ).hide( );
			jQuery( document.getElementById( 'ec_admin_order_details_customer_notes_content' ) ).show( );
			ec_admin_hide_loader( 'ec_admin_shipping_details' );
			if ( jQuery( document.getElementById( 'wpeasycart_order_history_refresh' ) ).length ) {
				ec_order_history_refresh();
			}
		} } );

		ec_admin_order_details_customer_notes_show = false;
	}else{
		jQuery( document.getElementById( 'ec_admin_order_details_customer_notes_form' ) ).show( );
		jQuery( document.getElementById( 'ec_admin_order_details_customer_notes_content' ) ).hide( );
		ec_admin_order_details_customer_notes_show = true;
	}
}

/* 6.0.0: the V2 order details screen confirms ( this emails the customer ), marks the menu item busy
   while it sends and reports the address through the V2 toast; the legacy screen is unchanged. */
/* recipients / on_done: from the V2 send dialog ( ecodv2_send_shipped_dialog ); on_done( ok, message, field ) lets it
   close or show the error in place. Without them the email goes to the order's addresses ( shipping-label flow ). */
function ec_admin_send_order_shipped_email( skip_confirm, recipients, on_done ){
	var ec_ship_v2 = ( document.getElementById( 'ecodv2_edit_drawer' ) && 'function' === typeof window.ecodv2_save_toast );
	var ec_ship_link = document.getElementById( 'ecodv2_send_shipped_link' );
	var ec_ship_email = ( ec_ship_link && ec_ship_link.getAttribute( 'data-email' ) ) ? ec_ship_link.getAttribute( 'data-email' ) : '';

	if ( ec_ship_v2 ) {
		if ( ! recipients && '' === ec_ship_email ) {
			window.ecodv2_save_toast( 'This order has no email address to send to.', true );
			return false;
		}
		/* The shipping-label flow already asked ( "Email the customer" checkbox ), so it skips the prompt. */
		if ( ! skip_confirm && ! window.confirm( 'Email the customer at ' + ec_ship_email + ' that this order has shipped?' ) ) {
			return false;
		}
		if ( ec_ship_link ) {
			ec_ship_link.classList.add( 'is-busy' );
		}
	} else {
		jQuery( document.getElementById( "ec_admin_order_management" ) ).fadeIn( 'fast' );
	}

	var data = {
		action: 'ec_admin_ajax_order_details_send_order_shipped_email',
		order_id: ec_admin_get_value( 'order_id', 'text' ),
		wp_easycart_nonce: ec_admin_get_value( 'wp_easycart_order_details_nonce', 'text' )
	};
	if ( recipients ) {
		data.to = recipients.to;
		data.cc = recipients.cc;
		data.bcc = recipients.bcc;
	}

	jQuery.ajax({url: wpeasycart_admin_ajax_object.ajax_url, type: 'post', data: data, dataType: 'json', success: function( response ){
		if ( ec_ship_link ) {
			ec_ship_link.classList.remove( 'is-busy' );
		}
		if ( on_done && ! ( response && response.success ) ) {
			on_done( false, ( response && response.data && response.data.message ) ? response.data.message : '', ( response && response.data && response.data.field ) ? response.data.field : '' );
			return;
		}
		if ( on_done ) {
			on_done( true );
		}
		if ( ec_ship_v2 ) {
			if ( response && response.success ) {
				window.ecodv2_save_toast( ( response.data && response.data.message ) ? response.data.message : 'Order shipped email sent.' );
				if ( 'function' === typeof window.ecodv2_shipped_email_sent ) {
					window.ecodv2_shipped_email_sent( response.data );
				}
			} else {
				window.ecodv2_save_toast( ( response && response.data && response.data.message ) ? response.data.message : 'The order shipped email could not be sent.', true );
			}
		} else {
			ec_admin_hide_loader( 'ec_admin_order_management' );
		}
		if ( jQuery( document.getElementById( 'wpeasycart_order_history_refresh' ) ).length ) {
			ec_order_history_refresh();
		}
	}, error: function(){
		if ( ec_ship_link ) {
			ec_ship_link.classList.remove( 'is-busy' );
		}
		if ( on_done ) {
			on_done( false );
			return;
		}
		if ( ec_ship_v2 ) {
			window.ecodv2_save_toast( 'The order shipped email could not be sent.', true );
		} else {
			ec_admin_hide_loader( 'ec_admin_order_management' );
		}
	} } );

	ec_admin_order_details_order_info_show = false;
	return false;
}

function ec_admin_enable_download_item( orderdetail_id ) {
	jQuery( document.getElementById( "ec_admin_order_management" ) ).fadeIn( 'fast' );
	var data = {
		action: 'ec_admin_ajax_enable_download_item',
		order_id: jQuery( document.getElementById( 'ec_qe_order_id' ) ).html( ),
		orderdetail_id: orderdetail_id,
		wp_easycart_nonce: ec_admin_get_value( 'wp_easycart_order_details_nonce', 'text' )
	};
	jQuery.ajax({url: wpeasycart_admin_ajax_object.ajax_url, type: 'post', data: data, success: function(data){
		ec_admin_hide_loader( 'ec_admin_order_management' );
		jQuery( '#ec_details_option_no_downloads_' + orderdetail_id ).hide();
		jQuery( '#ec_details_option_yes_downloads_' + orderdetail_id ).show();
	} } );
}

