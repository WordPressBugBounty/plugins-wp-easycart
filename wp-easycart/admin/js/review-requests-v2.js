/** Reviews → Requests & rules. Depends on catalog-v2.js ( ecv2_toast ). */
jQuery( function( $ ) {
	'use strict';
	var $root = $( '#ecrq' ); if ( ! $root.length ) { return; }
	var D = JSON.parse( $( '#ecrq_data' ).text() || '{}' );
	var AJAX_URL = ( window.wpeasycart_admin_ajax_object && wpeasycart_admin_ajax_object.ajax_url ) || window.ajaxurl;
	var dirty = false;
	$root.on( 'input change', '[data-field], [data-rule], .ecrq-status-cb', function() { dirty = true; $( '#ecrq_save' ).addClass( 'is-dirty' ); } );
	$( window ).on( 'beforeunload', function() { if ( dirty ) { return 'You have unsaved changes.'; } } );
	function collect() {
		var data = { rules: {}, request_statuses: [] };
		$root.find( '[data-field]' ).each( function() { data[ $( this ).data( 'field' ) ] = this.type === 'checkbox' ? ( this.checked ? 1 : 0 ) : this.value; } );
		$root.find( '[data-rule]' ).each( function() { data.rules[ $( this ).data( 'rule' ) ] = this.type === 'checkbox' ? ( this.checked ? 1 : 0 ) : this.value; } );
		$root.find( '.ecrq-status-cb:checked' ).each( function() { data.request_statuses.push( parseInt( this.value, 10 ) ); } );
		return data;
	}
	function post( action, extra, cb ) {
		$.post( AJAX_URL, $.extend( { action: action, nonce: D.nonce }, extra || {} ), function( r ) {
			if ( ! r || ! r.success ) { ecv2_toast( ( r && r.data && r.data.message ) || 'Something went wrong.', 'error' ); return; }
			cb( r.data );
		}, 'json' ).fail( function() { ecv2_toast( 'Something went wrong.', 'error' ); } );
	}
	window.ecrq = {
		save: function() {
			var data = collect();
			if ( data.request_enabled && ! data.request_statuses.length ) { ecv2_toast( 'Pick at least one trigger status.', 'error' ); return; }
			$( '#ecrq_save' ).prop( 'disabled', true );
			post( 'ecv2_review_settings_save', { data: JSON.stringify( data ) }, function( d ) { dirty = false; $( '#ecrq_save' ).prop( 'disabled', false ).removeClass( 'is-dirty' ); ecv2_toast( d.message, 'success' ); } );
			$( '#ecrq_save' ).prop( 'disabled', false );
		},
		test: function( reminder ) { post( 'ecv2_review_request_test', { reminder: reminder ? 1 : 0 }, function( d ) { ecv2_toast( d.message, 'success' ); } ); },
		send_now: function() { post( 'ecv2_review_requests_send_now', {}, function( d ) { ecv2_toast( d.message, 'success' ); if ( d.funnel ) { $.each( [ 'queued', 'sent', 'opened', 'reviewed' ], function( i, k ) { $( '#ecrq_f_' + k ).text( d.funnel[ k ] ); } ); } } ); },
		recompute: function() { post( 'ecv2_review_verified_recompute', {}, function( d ) { ecv2_toast( d.message, 'success' ); } ); }
	};
} );
