/* WP EasyCart Admin — Settings › Store details ( V2 ): live currency preview.
 * Mirrors ec_currency::get_currency_display() from the values on screen ( saved or not ),
 * and offers the usual format for a known currency code as a one-click apply. */
( function( $ ) {
	'use strict';

	var $pv = $( '#ecsd_preview' );
	if ( ! $pv.length ) { return; }

	var S = window.ecsd_vars || {};
	var presets = {};
	try { presets = JSON.parse( $pv.attr( 'data-presets' ) || '{}' ) || {}; } catch ( e ) { presets = {}; }

	function row( key ) { return $( '#ecst-' + key ); }
	function text( key ) { return String( row( key ).find( '.ecst-input' ).val() || '' ); }
	function pill( key ) { var $on = row( key ).find( '.ecst-input:checked' ); return $on.length ? String( $on.val() ) : ''; }
	function decode( s ) { return $( '<textarea/>' ).html( s ).text(); }

	function current() {
		var places = parseInt( pill( 'ec_option_currency_decimal_places' ), 10 );
		return {
			code: text( 'ec_option_base_currency' ).trim().toUpperCase(),
			symbol: decode( text( 'ec_option_currency' ) ),
			before: pill( 'ec_option_currency_symbol_location' ) !== '0',
			showCode: row( 'ec_option_show_currency_code' ).find( '.ecst-input' ).is( ':checked' ),
			decimal: pill( 'ec_option_currency_decimal_symbol' ),
			thousands: pill( 'ec_option_currency_thousands_seperator' ),
			places: isNaN( places ) ? 2 : places,
			negBefore: pill( 'ec_option_currency_negative_location' ) !== '0'
		};
	}
	function number( amount, places, dec, sep ) {
		var fixed = Math.abs( amount ).toFixed( places ).split( '.' );
		var whole = fixed[0].replace( /\B(?=(\d{3})+(?!\d))/g, sep );
		return places > 0 ? whole + dec + fixed[1] : whole;
	}
	/* Same order as ec_currency::get_currency_display(). */
	function format( amount, f ) {
		var out = f.showCode && f.code ? f.code + ' ' : '';
		if ( amount < 0 && f.negBefore ) { out += '-'; }
		if ( f.before ) { out += f.symbol; }
		if ( amount < 0 && ! f.negBefore ) { out += '-'; }
		out += number( amount, f.places, f.decimal, f.thousands );
		if ( ! f.before ) { out += f.symbol; }
		return out;
	}
	function presetFormat( p, f ) {
		return { code: f.code, symbol: p.symbol, before: p.before === '1', showCode: f.showCode, decimal: p.decimal, thousands: p.thousands, places: parseInt( p.places, 10 ), negBefore: f.negBefore };
	}

	function refresh() {
		var f = current();
		$pv.find( '.ecsd-sample' ).each( function() { $( this ).text( format( parseFloat( $( this ).attr( 'data-amount' ) ), f ) ); } );
		var p = presets[ f.code ], $preset = $pv.find( '.ecsd-preset' );
		if ( ! p ) { $preset.prop( 'hidden', true ); return; }
		var pf = presetFormat( p, f ), sample = format( 1234.56, pf );
		$preset.prop( 'hidden', sample === format( 1234.56, f ) );
		$preset.find( '.ecsd-preset-text' ).text( String( S.preset || 'Usual %1$s format: %2$s' ).replace( '%1$s', f.code ).replace( '%2$s', sample ) );
	}

	function setPill( key, value ) {
		var $in = row( key ).find( '.ecst-input' ).filter( function() { return String( this.value ) === String( value ); } );
		if ( $in.length && ! $in.is( ':checked' ) ) { $in.prop( 'checked', true ).trigger( 'change' ); }
	}
	$pv.on( 'click', '.ecsd-preset-apply', function() {
		var p = presets[ current().code ];
		if ( ! p ) { return; }
		var $sym = row( 'ec_option_currency' ).find( '.ecst-input' );
		if ( $sym.val() !== p.symbol ) { $sym.val( p.symbol ).trigger( 'input' ); }
		setPill( 'ec_option_currency_symbol_location', p.before );
		setPill( 'ec_option_currency_decimal_symbol', p.decimal );
		setPill( 'ec_option_currency_thousands_seperator', p.thousands );
		setPill( 'ec_option_currency_decimal_places', p.places );
		refresh();
	} );

	/* Normalise the code as it is typed so the preset lookup and the saved value agree. */
	row( 'ec_option_base_currency' ).on( 'input', '.ecst-input', function() {
		var up = this.value.toUpperCase();
		if ( up !== this.value ) { var pos = this.selectionStart; this.value = up; try { this.setSelectionRange( pos, pos ); } catch ( e ) {} }
	} );
	$( '#ecst-sec-currency' ).on( 'input change', '.ecst-input', function() { setTimeout( refresh, 0 ); } );
	$( document ).on( 'click', '#ecst_discard', function() { setTimeout( refresh, 0 ); } );
	refresh();
} )( jQuery );
