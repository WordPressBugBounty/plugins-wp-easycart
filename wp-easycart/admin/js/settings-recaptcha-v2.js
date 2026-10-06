/**
 * WP EasyCart — Settings › Accounts › reCAPTCHA key test ( 6.0.3 ).
 *
 * Test keys draws a real reCAPTCHA v2 box with the saved site key ( Google's script loads only on click ); ticking it
 * sends the answer to AJAX ecv2_recaptcha_test, where the server checks it with Google using the saved secret key and
 * records a pass against those keys. The reCAPTCHA switch below then turns on ( the settings engine refuses it before ).
 * Key edits that are not saved yet hold the test back; a save of either key asks ecv2_recaptcha_status for the new state.
 */
( function( $ ) {
	'use strict';

	var V = window.ecst_recaptcha || {};
	var T = V.text || {};
	var KEYS = [ 'ec_option_recaptcha_site_key', 'ec_option_recaptcha_secret_key' ];
	var script_state = 'none'; // none | loading | ready | failed
	var waiting = [];

	function $root() { return $( '#ecst_recaptcha_test' ); }
	function row( key ) { return $( '#ecst-' + $.escapeSelector( key ) ); }

	function unsaved() {
		var dirty = false;
		$.each( KEYS, function( i, key ) {
			if ( row( key ).find( '.ecst-in' ).hasClass( 'is-dirty' ) ) { dirty = true; }
		} );
		return dirty;
	}

	function result( text, tone ) {
		var $r = $root().find( '.ecst-recaptcha-result' );
		$r.attr( 'class', 'ecst-recaptcha-result' + ( tone ? ' is-' + tone : '' ) ).text( text || '' ).prop( 'hidden', ! text );
	}

	function apply_status( st ) {
		if ( ! st ) { return; }
		var $r = $root();
		$r.attr( { 'data-state': st.state, 'data-site-key': st.site_key || '' } );
		$r.find( '.ecst-recaptcha-status' ).attr( 'class', 'ecst-recaptcha-status is-' + st.tone );
		$r.find( '.ecst-recaptcha-text' ).text( st.text );
		$r.find( '.ecst-recaptcha-run' ).text( st.button ).data( 'can', !! st.can_test );
		refresh_button();
	}

	function refresh_button() {
		var $r = $root(), held = unsaved();
		$r.find( '.ecst-recaptcha-unsaved' ).prop( 'hidden', ! held );
		$r.find( '.ecst-recaptcha-run' ).prop( 'disabled', held || ! $r.find( '.ecst-recaptcha-run' ).data( 'can' ) );
	}

	function post( action, data, ok, fail ) {
		$.post( V.ajax_url, $.extend( { action: action, nonce: $root().attr( 'data-nonce' ) }, data ), function( r ) {
			if ( r && r.success ) { ok( r.data || {} ); } else { fail( ( r && r.data ) ? r.data : {} ); }
		}, 'json' ).fail( function() { fail( { message: T.error } ); } );
	}

	/* Google's script, once, with an explicit render ( never on page load: the settings page stays free of Google until asked ). */
	function with_script( then ) {
		if ( script_state === 'ready' && window.grecaptcha && window.grecaptcha.render ) { then(); return; }
		waiting.push( then );
		if ( script_state === 'loading' ) { return; }
		script_state = 'loading';
		window.ecst_recaptcha_loaded = function() {
			script_state = 'ready';
			var list = waiting; waiting = [];
			$.each( list, function( i, fn ) { fn(); } );
		};
		var s = document.createElement( 'script' );
		s.src = 'https://www.google.com/recaptcha/api.js?onload=ecst_recaptcha_loaded&render=explicit';
		s.async = true;
		s.onerror = function() { script_state = 'failed'; waiting = []; result( T.no_load, 'bad' ); $root().find( '.ecst-recaptcha-stage' ).prop( 'hidden', true ); };
		document.head.appendChild( s );
		setTimeout( function() {
			if ( script_state === 'loading' ) { script_state = 'none'; waiting = []; result( T.no_load, 'bad' ); }
		}, 15000 );
	}

	function run() {
		var $r = $root(), key = String( $r.attr( 'data-site-key' ) || '' );
		if ( ! key || unsaved() ) { refresh_button(); return; }
		var $stage = $r.find( '.ecst-recaptcha-stage' ), $box = $r.find( '.ecst-recaptcha-box' );
		$stage.prop( 'hidden', false );
		$box.empty();
		result( T.loading, 'muted' );
		with_script( function() {
			/* A fresh element each time: grecaptcha draws a box only once per element, and the key may have changed. */
			var el = $( '<div>' ).appendTo( $box.empty() )[ 0 ];
			result( '', '' );
			try {
				window.grecaptcha.render( el, {
					sitekey: key,
					callback: check,
					'expired-callback': function() { result( T.expired, 'warn' ); },
					'error-callback': function() { result( T.no_load, 'bad' ); }
				} );
			} catch ( e ) {
				result( T.error, 'bad' );
			}
		} );
	}

	function check( token ) {
		result( T.checking, 'muted' );
		post( 'ecv2_recaptcha_test', { token: token }, function( d ) {
			apply_status( d.status );
			result( d.message, 'good' );
			$root().find( '.ecst-recaptcha-stage' ).prop( 'hidden', true );
		}, function( d ) {
			if ( d.status ) { apply_status( d.status ); }
			result( d.message || T.error, 'bad' );
			if ( window.grecaptcha && window.grecaptcha.reset ) { try { window.grecaptcha.reset(); } catch ( e ) {} }
		} );
	}

	$( function() {
		var $r = $root();
		if ( ! $r.length ) { return; }
		$r.find( '.ecst-recaptcha-run' ).data( 'can', ! $r.find( '.ecst-recaptcha-run' ).prop( 'disabled' ) );
		$r.on( 'click', '.ecst-recaptcha-run', function( e ) { e.preventDefault(); run(); } );
		/* Typing marks a key row dirty; Discard and the save bar clear it without an input event, so follow the class itself. */
		var watch = window.MutationObserver ? new MutationObserver( refresh_button ) : null;
		$.each( KEYS, function( i, key ) {
			row( key ).on( 'input change', '.ecst-input', function() { setTimeout( refresh_button, 0 ); } );
			if ( watch ) { row( key ).find( '.ecst-in' ).each( function() { watch.observe( this, { attributes: true, attributeFilter: [ 'class' ] } ); } ); }
		} );
		$( document ).on( 'ecst:saved', function( e, d, keys ) {
			var touched = $.grep( keys || [], function( k ) { return KEYS.indexOf( k ) !== -1; } ).length;
			setTimeout( refresh_button, 0 );
			if ( ! touched ) { return; }
			$r.find( '.ecst-recaptcha-stage' ).prop( 'hidden', true );
			result( '', '' );
			post( 'ecv2_recaptcha_status', {}, function( st ) { apply_status( st.status ); }, function() {} );
		} );
		refresh_button();
	} );
}( jQuery ) );
