/* WP EasyCart Admin — Settings › Shipping rates ( V2 ).
 * Five rate tables ( flat, by cart total, by weight, by quantity, percentage ) edited in place.
 * Saves through the ecv2_shipping_rate_* handlers in wp_easycart_admin_shipping_rates_v2.php:
 *   ecv2_shipping_rate_add     ( type, label, rate, trigger, free_at, order, zone_id ) → { id, row }
 *   ecv2_shipping_rate_update  ( type, id, …same fields )                             → { id, row }
 *   ecv2_shipping_rate_delete  ( type, id )                                           → { count }
 * All take nonce for the settings V2 action ( ecv2_settings_guard ).
 * Sections for methods that are not the active one start collapsed with a "Not in use" chip.
 * window.ecsr exposes the small helpers the PRO live-rate list reuses. */
( function( $ ) {
	'use strict';

	var V = window.ecsr_vars || { ajax: window.ajaxurl, nonce: '', method: '', i18n: {} };
	var T = V.i18n || {};
	var $wrap = $( '#ecst' );
	if ( ! $wrap.length ) { return; }

	function toast( msg, kind ) {
		if ( ! msg ) { return; }
		if ( typeof window.ecv2_toast === 'function' ) { return window.ecv2_toast( msg, kind || 'success' ); }
	}
	function confirmBox( title, text ) {
		if ( typeof window.ecv2_show_confirm === 'function' ) { return window.ecv2_show_confirm( title, text ); }
		return Promise.resolve( window.confirm( title + ( text ? '\n\n' + text : '' ) ) );
	}
	function post( action, data, ok, fail ) {
		data = $.extend( { action: action, nonce: V.nonce }, data );
		return $.post( V.ajax, data ).done( function( r ) {
			if ( r && r.success ) { ok( r.data || {} ); } else { fail( ( r && r.data && r.data.message ) ? r.data.message : ( T.failed || 'Could not save' ) ); }
		} ).fail( function() { fail( T.failed || 'Could not save' ); } );
	}
	function setState( $row, kind, text ) {
		var $s = $row.find( '.ecsr-state' ).first();
		clearTimeout( $s.data( 'timer' ) );
		$s.removeClass( 'is-saved is-saving is-error' ).text( text || '' ).attr( 'title', '' );
		$row.toggleClass( 'is-saving', kind === 'saving' );
		if ( kind ) { $s.addClass( 'is-' + kind ); }
		if ( kind === 'saved' ) { $s.data( 'timer', setTimeout( function() { $s.text( '' ).removeClass( 'is-saved' ); }, 1600 ) ); }
	}
	function fill( $row, data ) {
		$row.find( '[data-field]' ).each( function() {
			var f = $( this ).data( 'field' );
			if ( ! ( f in data ) ) { return; }
			if ( this === document.activeElement ) { return; } // never clobber what the user is typing
			$( this ).val( String( data[ f ] ) );
		} );
	}
	function values( $row ) {
		var out = { type: $row.data( 'type' ), id: $row.data( 'id' ) };
		$row.find( '[data-field]' ).each( function() { out[ $( this ).data( 'field' ) ] = String( $( this ).val() || '' ); } );
		return out;
	}
	function newValues( $table ) {
		var out = {};
		$table.find( '.ecsr-new [data-field]' ).each( function() { out[ $( this ).data( 'field' ) ] = String( $( this ).val() || '' ); } );
		return out;
	}
	function resetNew( $table ) {
		$table.find( '.ecsr-new [data-field]' ).each( function() {
			if ( this.tagName === 'SELECT' ) { this.selectedIndex = 0; } else { $( this ).val( '' ).removeClass( 'is-invalid' ); }
		} );
		$table.find( '.ecsr-new .ecsr-msg' ).remove();
	}
	function newMsg( $table, text ) {
		$table.find( '.ecsr-new .ecsr-msg' ).remove();
		if ( text ) { $table.find( '.ecsr-cell-add' ).append( $( '<span class="ecsr-msg"></span>' ).text( text ) ); }
	}
	function refreshEmpty( $table ) {
		var n = $table.find( 'tbody .ecsr-row' ).not( '.ecsr-tpl' ).length;
		$table.find( '.ecsr-empty' ).prop( 'hidden', n > 0 );
	}

	/* ---- idle sections ( every method but the active one ) ---- */
	function markIdle( $sec ) {
		if ( $sec.hasClass( 'is-idle' ) ) { return; }
		$sec.addClass( 'is-idle' );
		var $btn = $( '<button type="button" class="ecsr-idle" aria-expanded="false"><span class="ecsr-idle-chip"></span><span class="ecsr-idle-link"></span></button>' );
		$btn.find( '.ecsr-idle-chip' ).text( T.not_in_use || 'Not in use' );
		$btn.find( '.ecsr-idle-link' ).text( T.show || 'Show' );
		$sec.find( '.ecst-sec-titles' ).first().after( $btn );
	}
	function openIdle( $sec, open ) {
		$sec.toggleClass( 'is-open', open );
		$sec.find( '.ecsr-idle' ).attr( 'aria-expanded', open ? 'true' : 'false' ).find( '.ecsr-idle-link' ).text( open ? ( T.hide || 'Hide' ) : ( T.show || 'Show' ) );
	}
	function unmarkIdle( $sec ) {
		$sec.removeClass( 'is-idle is-open' ).find( '.ecsr-idle' ).remove();
	}
	$wrap.find( '.ecsr[data-active="0"]' ).each( function() { markIdle( $( this ).closest( '.ecst-section' ) ); } );
	// the locked live section carries no .ecsr marker; it is idle unless live rates are the method
	if ( V.method !== 'live' ) { markIdle( $( '#ecst-sec-live' ) ); }
	$wrap.on( 'click', '.ecsr-idle', function() {
		var $sec = $( this ).closest( '.ecst-section' );
		openIdle( $sec, ! $sec.hasClass( 'is-open' ) );
	} );
	$( '#ecst_secnav a' ).on( 'click', function() {
		var $sec = $( '#ecst-sec-' + $.escapeSelector( String( $( this ).data( 'sec' ) ) ) );
		if ( $sec.hasClass( 'is-idle' ) ) { openIdle( $sec, true ); }
	} );
	// a deep link straight into a collapsed section opens it
	if ( window.location.hash && window.location.hash.indexOf( '#ecst-sec-' ) === 0 ) {
		var $target = $( window.location.hash );
		if ( $target.hasClass( 'is-idle' ) ) { openIdle( $target, true ); }
	}

	/* ---- method switcher ( saves ec_option_shipping_method through the engine, page 'shipping-settings' ) ---- */
	var TYPE_METHOD = { flat: 'method', price: 'price', weight: 'weight', quantity: 'quantity', percentage: 'percentage', live: 'live' };
	var $active = $( '#ecsr_active' ), $pills = $active.find( '.ecsr-pills' );
	// rarely used methods ( Fraktjakt ) sit behind "More methods" unless one of them is the stored method
	$pills.on( 'click', '.ecsr-more', function() {
		var $btn = $( this ), open = $btn.attr( 'aria-expanded' ) !== 'true';
		$btn.attr( 'aria-expanded', open ? 'true' : 'false' );
		$( '#' + $btn.attr( 'aria-controls' ) ).prop( 'hidden', ! open );
		if ( open ) { $( '#' + $btn.attr( 'aria-controls' ) ).find( '.ecsr-pill' ).first().trigger( 'focus' ); }
	} );
	function applyMethod( method ) {
		V.method = method;
		var proOn = String( $active.data( 'pro' ) ) === '1';
		var $pill = $pills.find( '.ecsr-pill' ).filter( function() { return String( $( this ).data( 'method' ) ) === method; } );
		$pills.find( '.ecsr-pill' ).removeClass( 'is-on' ).attr( 'aria-pressed', 'false' );
		$pill.addClass( 'is-on' ).attr( 'aria-pressed', 'true' );
		$active.attr( 'data-method', method );
		$active.find( '.ecsr-active-title' ).text( String( $pill.data( 'title' ) || method ) );
		var desc = String( $pill.data( 'desc' ) || '' );
		$active.find( '.ecsr-active-desc' ).text( desc ).prop( 'hidden', desc === '' );
		$wrap.find( '.ecsr-notice[data-for-method]' ).each( function() {
			var forMethod = String( $( this ).data( 'for-method' ) ), show = ( forMethod === method );
			if ( forMethod === 'live' ) { show = show && ! proOn; }
			$( this ).prop( 'hidden', ! show );
		} );
		// every rate table ( and the PRO live list ) follows the new method
		$wrap.find( '.ecsr[data-type]' ).not( '.ecsr-mock' ).each( function() {
			var $box = $( this ), on = ( TYPE_METHOD[ String( $box.data( 'type' ) ) ] === method );
			var $sec = $box.closest( '.ecst-section' );
			$box.attr( 'data-active', on ? '1' : '0' );
			if ( on ) {
				if ( $sec.hasClass( 'is-idle' ) ) { unmarkIdle( $sec ); $sec.addClass( 'ecsr-just-on' ); setTimeout( function() { $sec.removeClass( 'ecsr-just-on' ); }, 1900 ); }
			} else if ( ! $sec.hasClass( 'is-idle' ) ) {
				markIdle( $sec );
			}
		} );
		// the locked live section carries no .ecsr marker
		var $live = $( '#ecst-sec-live' );
		if ( $live.length && ! $live.find( '.ecsr[data-type]' ).not( '.ecsr-mock' ).length ) {
			if ( method === 'live' ) { unmarkIdle( $live ); } else { markIdle( $live ); }
		}
	}
	$pills.on( 'click', '.ecsr-pill', function() {
		var $pill = $( this ), method = String( $pill.data( 'method' ) );
		if ( $pill.hasClass( 'is-on' ) || $pills.hasClass( 'is-busy' ) ) { return; }
		if ( String( $pill.data( 'locked' ) ) === '1' ) {
			if ( $pill.find( '.ecst-pro-tag' ).length ) {
				if ( window.ecst && typeof window.ecst.upsell === 'function' ) { window.ecst.upsell( method ); } else { toast( T.method_failed, 'error' ); }
			}
			return;
		}
		$pills.addClass( 'is-busy' );
		$pill.addClass( 'is-saving' );
		var changes = { ec_option_shipping_method: method };
		$.post( V.ajax, { action: 'ecv2_settings_save', nonce: V.nonce, page: 'shipping-settings', changes: JSON.stringify( changes ) } ).done( function( r ) {
			var d = ( r && r.data ) || {};
			if ( r && r.success && d.saved && d.saved.ec_option_shipping_method ) {
				applyMethod( method );
				toast( ( T.method_saved || '%s' ).replace( '%s', String( $pill.data( 'title' ) || method ) ), 'success' );
			} else {
				var msg = ( d.errors && d.errors.ec_option_shipping_method ) ? d.errors.ec_option_shipping_method : ( d.message || T.method_failed );
				toast( msg, 'error' );
			}
		} ).fail( function() {
			toast( T.method_failed, 'error' );
		} ).always( function() {
			$pills.removeClass( 'is-busy' );
			$pill.removeClass( 'is-saving' );
		} );
	} );

	/* ---- edit in place ---- */
	function saveRow( $row ) {
		clearTimeout( $row.data( 'timer' ) );
		$row.data( 'timer', setTimeout( function() {
			var v = values( $row );
			setState( $row, 'saving', T.saving );
			$row.removeClass( 'is-invalid' );
			post( 'ecv2_shipping_rate_update', v, function( d ) {
				setState( $row, 'saved', T.saved );
				if ( d.row ) { fill( $row, d.row ); }
			}, function( m ) {
				setState( $row, 'error', T.failed );
				$row.addClass( 'is-invalid' ).find( '.ecsr-state' ).attr( 'title', m );
				toast( m, 'error' );
			} );
		}, 300 ) );
	}
	$wrap.on( 'change', '.ecsr:not(.ecsr-live) tbody .ecsr-row .ecsr-in', function() { saveRow( $( this ).closest( '.ecsr-row' ) ); } );
	$wrap.on( 'keydown', '.ecsr:not(.ecsr-live) .ecsr-table input.ecsr-in', function( e ) {
		if ( e.key !== 'Enter' ) { return; }
		e.preventDefault();
		var $tr = $( this ).closest( 'tr' );
		if ( $tr.hasClass( 'ecsr-new' ) ) { $tr.find( '.ecsr-add' ).trigger( 'click' ); } else { $( this ).blur(); }
	} );

	/* ---- delete ---- */
	$wrap.on( 'click', '.ecsr:not(.ecsr-live) tbody .ecsr-row .ecsr-del', function() {
		var $row = $( this ).closest( '.ecsr-row' ), $table = $row.closest( '.ecsr-table' );
		confirmBox( T.confirm_title || 'Delete this rate?', T.confirm_text || '' ).then( function( ok ) {
			if ( ! ok ) { return; }
			$row.addClass( 'is-removing' );
			post( 'ecv2_shipping_rate_delete', { type: $row.data( 'type' ), id: $row.data( 'id' ) }, function( d ) {
				$row.remove();
				refreshEmpty( $table );
				toast( d.message || T.deleted, 'success' );
			}, function( m ) {
				$row.removeClass( 'is-removing' );
				setState( $row, 'error', T.failed );
				toast( m, 'error' );
			} );
		} );
	} );

	/* ---- add ---- */
	$wrap.on( 'click', '.ecsr:not(.ecsr-live) .ecsr-add', function() {
		var $btn = $( this ), $table = $btn.closest( '.ecsr-table' ), $box = $table.closest( '.ecsr' ), type = $box.data( 'type' );
		var v = newValues( $table );
		newMsg( $table, '' );
		if ( type === 'flat' && $.trim( v.label || '' ) === '' ) {
			newMsg( $table, T.label_needed );
			$table.find( '.ecsr-new [data-field="label"]' ).addClass( 'is-invalid' ).trigger( 'focus' );
			return;
		}
		$btn.prop( 'disabled', true );
		post( 'ecv2_shipping_rate_add', $.extend( { type: type }, v ), function( d ) {
			$btn.prop( 'disabled', false );
			var $row = $table.find( '.ecsr-tpl' ).clone( true ).removeClass( 'ecsr-tpl' ).prop( 'hidden', false ).addClass( 'is-new' ).attr( 'data-id', d.id ).data( 'id', d.id );
			fill( $row, d.row || {} );
			$table.find( '.ecsr-tpl' ).before( $row );
			refreshEmpty( $table );
			resetNew( $table );
			$table.find( '.ecsr-new [data-field]' ).first().trigger( 'focus' );
			toast( d.message || T.added, 'success' );
		}, function( m ) {
			$btn.prop( 'disabled', false );
			newMsg( $table, m );
		} );
	} );

	window.ecsr = { post: post, toast: toast, confirmBox: confirmBox, setState: setState, fill: fill, values: values, refreshEmpty: refreshEmpty, markIdle: markIdle, openIdle: openIdle, unmarkIdle: unmarkIdle, applyMethod: applyMethod, i18n: T };
} )( jQuery );
