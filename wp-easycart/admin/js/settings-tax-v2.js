/* Settings › Taxes ( V2 ): rate tables ( state / country ), VAT rate per country and the Canada grid.
   Saved through the ecv2_tax_* handlers ( wp_easycart_admin_tax_v2 ).
   Nonce + ajax URL come from the engine's ecst_vars; wording from ectx_vars.

   Save model for editable rows: typing marks the row dirty ( amber input, ✓ button enabled ).
   The ✓ button or Enter saves the row, Escape reverts it. Leaving the row does NOT save; the
   row stays marked "Not saved" with the ✓ highlighted, and leaving the page warns. Toggles
   ( VAT "Apply to business", Canada "Collect" ) still save on change, without touching a
   pending rate edit. */
( function( $ ) {
	'use strict';
	var V = window.ecst_vars || { ajax: window.ajaxurl, nonce: '' };
	var T = ( window.ectx_vars && window.ectx_vars.i18n ) ? window.ectx_vars.i18n : {};
	var t = function( k, d ) { return T[ k ] || d; };

	function post( action, data, ok, fail ) {
		data = $.extend( { action: action, nonce: V.nonce }, data );
		return $.post( V.ajax, data ).done( function( r ) {
			if ( r && r.success ) { ok( r.data || {} ); } else { fail( ( r && r.data && r.data.message ) ? r.data.message : t( 'failed', 'Could not save.' ) ); }
		} ).fail( function() { fail( t( 'failed', 'Could not save.' ) ); } );
	}

	/* tiny per-row status: saving / saved ( fades ) / pending / error ( stays ) */
	function state( $el, kind, text ) {
		$el.removeClass( 'is-saved is-saving is-error is-pending' ).text( text || '' );
		if ( kind ) { $el.addClass( 'is-' + kind ); }
		if ( kind === 'saved' ) { setTimeout( function() { if ( $el.hasClass( 'is-saved' ) ) { $el.removeClass( 'is-saved' ).text( '' ); } }, 2200 ); }
	}
	function rateOf( $input ) {
		var v = String( $input.val() || '' ).trim();
		return ( v === '' || isNaN( Number( v ) ) ) ? null : Number( v );
	}
	function setToggle( $label, on ) {
		$label.toggleClass( 'is-on', on ).find( 'input' ).prop( 'checked', on );
	}
	/* one-at-a-time request queue: job( next ) must call next() when its request settles */
	function serial() {
		var queue = [], busy = false;
		function pump() {
			if ( busy || ! queue.length ) { return; }
			busy = true;
			queue.shift()( function() { busy = false; pump(); } );
		}
		return function( job ) { queue.push( job ); pump(); };
	}
	function refreshEmpty( $box ) {
		var n = $box.find( 'tbody .ectx-row' ).not( '.ectx-tpl' ).length;
		$box.find( '.ectx-empty' ).prop( 'hidden', n > 0 );
		$box.find( '.ectx-frame' ).prop( 'hidden', n === 0 );
	}

	/* ================================================================== */
	/* Row dirty tracking ( shared by every editable table )               */
	/* ================================================================== */
	var EDIT = '.ectx-geo, .ectx-rate, .ectx-crate';

	/* the last saved value of each editable control lives in data( 'ectxOrig' ) */
	function snapshot( $row ) {
		$row.find( EDIT ).each( function() { $( this ).data( 'ectxOrig', String( $( this ).val() || '' ) ); } );
	}
	function fieldDirty( el ) {
		var $el = $( el ), orig = $el.data( 'ectxOrig' ), cur = String( $el.val() || '' ).trim();
		orig = ( orig === undefined ) ? '' : String( orig ).trim();
		if ( cur === orig ) { return false; }
		if ( ! $el.is( 'select' ) && cur !== '' && orig !== '' && ! isNaN( Number( cur ) ) && Number( cur ) === Number( orig ) ) { return false; } /* 5 vs 5.000 */
		return true;
	}
	function dirtyFields( $row ) {
		return $row.find( EDIT ).filter( function() { return fieldDirty( this ); } );
	}
	/* repaints the row: amber on changed controls, ✓ enabled while anything is unsaved */
	function markRow( $row ) {
		var dirty = false;
		$row.find( EDIT ).each( function() {
			var d = fieldDirty( this );
			dirty = dirty || d;
			( $( this ).is( 'select' ) ? $( this ) : $( this ).closest( '.ectx-in' ) ).toggleClass( 'is-dirty', d );
		} );
		$row.toggleClass( 'is-dirty', dirty );
		if ( ! dirty ) { $row.removeClass( 'is-pending' ); }
		$row.find( '.ectx-save' ).prop( 'disabled', ! dirty || !! $row.data( 'ectxSaving' ) );
		var $st = $row.find( '.ecst-state' );
		if ( ! dirty && $st.hasClass( 'is-pending' ) ) { state( $st, null ); }
		return dirty;
	}
	function revertRow( $row ) {
		$row.find( EDIT ).each( function() {
			var orig = $( this ).data( 'ectxOrig' );
			if ( orig !== undefined ) { $( this ).val( orig ); }
		} );
		state( $row.find( '.ecst-state' ), null );
		markRow( $row );
	}
	/* focus left the row with unsaved changes: say so and highlight the ✓, never save silently */
	function flagPending( $row ) {
		if ( ! $row.hasClass( 'is-dirty' ) || $row.data( 'ectxSaving' ) ) { return; }
		var $st = $row.find( '.ecst-state' );
		$row.addClass( 'is-pending' );
		if ( ! $st.hasClass( 'is-error' ) ) { state( $st, 'pending', t( 'unsaved', 'Not saved' ) ); }
	}

	/**
	 * Wires the dirty model on one table box. saveRow( $row, done ) performs the save and must
	 * call done( true|false ) once; the row is locked ( ✓ disabled ) until then.
	 */
	function editable( $box, saveRow ) {
		$box.find( '.ectx-row' ).not( '.ectx-tpl' ).each( function() { snapshot( $( this ) ); markRow( $( this ) ); } );

		function run( $row ) {
			if ( $row.data( 'ectxSaving' ) || ! markRow( $row ) ) { return; }
			$row.data( 'ectxSaving', true ).addClass( 'is-saving' ).removeClass( 'is-pending' );
			$row.find( '.ectx-save' ).prop( 'disabled', true );
			saveRow( $row, function() {
				$row.removeData( 'ectxSaving' ).removeClass( 'is-saving' );
				markRow( $row );
			} );
		}

		$box.on( 'input change', '.ectx-row:not(.ectx-tpl) ' + EDIT.split( ', ' ).join( ', .ectx-row:not(.ectx-tpl) ' ), function() {
			var $row = $( this ).closest( '.ectx-row' );
			if ( markRow( $row ) ) {
				$row.removeClass( 'is-pending' );
				var $st = $row.find( '.ecst-state' );
				if ( $st.is( '.is-pending, .is-saved' ) ) { state( $st, null ); }
			}
		} );
		$box.on( 'click', '.ectx-save', function() { run( $( this ).closest( '.ectx-row' ) ); } );
		$box.on( 'keydown', '.ectx-row ' + EDIT.split( ', ' ).join( ', .ectx-row ' ), function( e ) {
			var $row = $( this ).closest( '.ectx-row' );
			if ( e.key === 'Enter' ) { e.preventDefault(); run( $row ); }
			else if ( e.key === 'Escape' && $row.hasClass( 'is-dirty' ) && ! $row.data( 'ectxSaving' ) ) { e.preventDefault(); revertRow( $row ); }
		} );
		$box.on( 'focusout', '.ectx-row', function( e ) {
			var $row = $( this );
			if ( e.relatedTarget && $.contains( this, e.relatedTarget ) ) { return; }
			setTimeout( function() { if ( ! $.contains( $row[ 0 ], document.activeElement ) ) { flagPending( $row ); } }, 0 );
		} );
		/* an error message retries the row. Stops the engine's own .ecst-state.is-error handler, which only knows declared rows. */
		$box.on( 'click', '.ectx-row .ecst-state', function( e ) {
			e.stopPropagation();
			if ( $( this ).hasClass( 'is-error' ) ) { run( $( this ).closest( '.ectx-row' ) ); }
		} );
		$box.on( 'click', '.ectx-add .ecst-state', function( e ) { e.stopPropagation(); } );
	}

	$( window ).on( 'beforeunload', function() {
		if ( $( '.ectx-rates, .ectx-canada' ).find( '.ectx-row.is-dirty' ).not( '.ectx-tpl' ).length ) { return t( 'leave', 'You have tax rates that are not saved.' ); }
	} );

	/* ================================================================== */
	/* Rates by state / by country ( ec_taxrate )                          */
	/* ================================================================== */
	function initRates( $box ) {
		var kind = $box.data( 'kind' );

		editable( $box, function( $row, done ) {
			var $st = $row.find( '.ecst-state' ), $geo = $row.find( '.ectx-geo' ), $rate = $row.find( '.ectx-rate' );
			var geo = $geo.val(), rateRaw = String( $rate.val() || '' ), rate = rateOf( $rate );
			if ( ! geo ) { state( $st, 'error', t( 'pick_geo', 'Choose one' ) ); done( false ); return; }
			if ( rate === null ) { state( $st, 'error', t( 'enter_rate', 'Enter a rate' ) ); done( false ); return; }
			state( $st, 'saving', t( 'saving', 'Saving…' ) );
			post( 'ecv2_tax_rate_update', { kind: kind, id: $row.data( 'id' ), geo: geo, rate: rate }, function( d ) {
				$row.find( '.ectx-group' ).text( d.group || '' );
				$geo.data( 'ectxOrig', String( geo ) );
				$rate.data( 'ectxOrig', rateRaw );
				state( $st, 'saved', t( 'saved', 'Saved' ) );
				done( true );
			}, function( msg ) { state( $st, 'error', msg ); done( false ); } );
		} );

		$box.on( 'keydown', '.ectx-add-rate', function( e ) {
			if ( e.key === 'Enter' ) { e.preventDefault(); $box.find( '.ectx-add-btn' ).trigger( 'click' ); }
		} );

		$box.on( 'click', '.ectx-delete', function() {
			var $row = $( this ).closest( '.ectx-row' ), $st = $row.find( '.ecst-state' );
			if ( ! window.confirm( t( 'confirm_delete', 'Delete this rate?' ) ) ) { return; }
			state( $st, 'saving', t( 'deleting', 'Deleting…' ) );
			post( 'ecv2_tax_rate_delete', { kind: kind, id: $row.data( 'id' ) }, function() {
				$row.remove();
				refreshEmpty( $box );
			}, function( msg ) { state( $st, 'error', msg ); } );
		} );

		$box.on( 'click', '.ectx-add-btn', function() {
			var $add = $box.find( '.ectx-add' ), $st = $add.find( '.ecst-state' ), $btn = $( this );
			var geo = $add.find( '.ectx-add-geo' ).val(), rate = rateOf( $add.find( '.ectx-add-rate' ) );
			if ( $btn.prop( 'disabled' ) ) { return; }
			if ( ! geo ) { state( $st, 'error', t( 'pick_geo', 'Choose one' ) ); return; }
			if ( rate === null ) { state( $st, 'error', t( 'enter_rate', 'Enter a rate' ) ); return; }
			state( $st, 'saving', t( 'saving', 'Saving…' ) );
			$btn.prop( 'disabled', true );
			post( 'ecv2_tax_rate_add', { kind: kind, geo: geo, rate: rate }, function( d ) {
				var $row = $box.find( '.ectx-tpl' ).clone( true ).removeClass( 'ectx-tpl' ).removeAttr( 'hidden' ).attr( 'data-id', d.id ).data( 'id', d.id );
				$row.find( '.ectx-geo' ).val( geo );
				$row.find( '.ectx-group' ).text( d.group || '' );
				$row.find( '.ectx-rate' ).val( d.rate );
				$box.find( '.ectx-tpl' ).before( $row );
				snapshot( $row );
				markRow( $row );
				$add.find( '.ectx-add-geo' ).val( '' );
				$add.find( '.ectx-add-rate' ).val( '' );
				refreshEmpty( $box );
				state( $st, 'saved', t( 'added', 'Added' ) );
			}, function( msg ) { state( $st, 'error', msg ); } ).always( function() { $btn.prop( 'disabled', false ); } );
		} );

		refreshEmpty( $box );
	}

	/* ================================================================== */
	/* VAT rate per country ( ec_country )                                 */
	/* ================================================================== */
	function initVatCountries( $box ) {
		/* rate and "Apply to business" share one UPDATE, so writes go out one at a time */
		var enqueue = serial();

		editable( $box, function( $row, done ) {
			var $st = $row.find( '.ecst-state' ), $rate = $row.find( '.ectx-rate' ), rateRaw = String( $rate.val() || '' ), rate = rateOf( $rate );
			if ( rate === null ) { state( $st, 'error', t( 'enter_rate', 'Enter a rate' ) ); done( false ); return; }
			state( $st, 'saving', t( 'saving', 'Saving…' ) );
			enqueue( function( next ) {
				post( 'ecv2_tax_vat_country_update', { country_id: $row.data( 'id' ), rate: rate, b2b: $row.find( '.ectx-b2b' ).is( ':checked' ) ? 1 : 0 }, function() {
					$rate.data( 'ectxOrig', rateRaw );
					state( $st, 'saved', t( 'saved', 'Saved' ) );
					done( true );
				}, function( msg ) { state( $st, 'error', msg ); done( false ); } ).always( next );
			} );
		} );

		/* the toggle saves at once with the last SAVED rate ( read when the request leaves ), so a pending rate edit stays pending */
		$box.on( 'change', '.ectx-row:not(.ectx-tpl) .ectx-b2b', function() {
			var $cb = $( this ), $row = $cb.closest( '.ectx-row' ), $st = $row.find( '.ecst-state' ), on = $cb.is( ':checked' );
			setToggle( $cb.closest( '.ectx-toggle' ), on );
			state( $st, 'saving', t( 'saving', 'Saving…' ) );
			enqueue( function( next ) {
				var orig = String( $row.find( '.ectx-rate' ).data( 'ectxOrig' ) || '' ).trim();
				var saved = ( orig === '' || isNaN( Number( orig ) ) ) ? 0 : Number( orig );
				post( 'ecv2_tax_vat_country_update', { country_id: $row.data( 'id' ), rate: saved, b2b: on ? 1 : 0 }, function() {
					state( $st, 'saved', t( 'saved', 'Saved' ) );
					if ( $row.hasClass( 'is-dirty' ) ) { flagPending( $row ); }
				}, function( msg ) { setToggle( $cb.closest( '.ectx-toggle' ), ! on ); state( $st, 'error', msg ); } ).always( next );
			} );
		} );
		$box.on( 'keydown', '.ectx-add-rate', function( e ) {
			if ( e.key === 'Enter' ) { e.preventDefault(); $box.find( '.ectx-add-btn' ).trigger( 'click' ); }
		} );

		$box.on( 'click', '.ectx-delete', function() {
			var $row = $( this ).closest( '.ectx-row' ), $st = $row.find( '.ecst-state' );
			if ( ! window.confirm( t( 'confirm_delete', 'Delete this rate?' ) ) ) { return; }
			state( $st, 'saving', t( 'deleting', 'Deleting…' ) );
			post( 'ecv2_tax_vat_country_delete', { country_id: $row.data( 'id' ) }, function() {
				$row.remove();
				refreshEmpty( $box );
			}, function( msg ) { state( $st, 'error', msg ); } );
		} );

		$box.on( 'click', '.ectx-add-btn', function() {
			var $add = $box.find( '.ectx-add' ), $st = $add.find( '.ecst-state' ), $btn = $( this );
			var $geo = $add.find( '.ectx-add-geo' ), id = $geo.val(), rate = rateOf( $add.find( '.ectx-add-rate' ) );
			if ( $btn.prop( 'disabled' ) ) { return; }
			if ( ! id ) { state( $st, 'error', t( 'pick_geo', 'Choose one' ) ); return; }
			if ( rate === null ) { state( $st, 'error', t( 'enter_rate', 'Enter a rate' ) ); return; }
			state( $st, 'saving', t( 'saving', 'Saving…' ) );
			$btn.prop( 'disabled', true );
			post( 'ecv2_tax_vat_country_update', { country_id: id, rate: rate, b2b: 0 }, function( d ) {
				var $row = $box.find( '.ectx-row[data-id="' + d.id + '"]' ).not( '.ectx-tpl' );
				if ( $row.length ) {
					$row.find( '.ectx-rate' ).val( d.rate );
					setToggle( $row.find( '.ectx-toggle' ), false );
					state( $row.find( '.ecst-state' ), null );
				} else {
					$row = $box.find( '.ectx-tpl' ).clone( true ).removeClass( 'ectx-tpl' ).removeAttr( 'hidden' ).attr( 'data-id', d.id ).data( 'id', d.id );
					$row.find( '.ectx-geo-name' ).text( d.name || $geo.find( 'option:selected' ).text() );
					$row.find( '.ectx-rate' ).val( d.rate );
					$box.find( '.ectx-tpl' ).before( $row );
				}
				snapshot( $row );
				markRow( $row );
				$geo.val( '' );
				$add.find( '.ectx-add-rate' ).val( '' );
				refreshEmpty( $box );
				state( $st, 'saved', t( 'added', 'Added' ) );
			}, function( msg ) { state( $st, 'error', msg ); } ).always( function() { $btn.prop( 'disabled', false ); } );
		} );

		/* only meaningful while VAT is on and varies by country */
		var $use = $( '#ecst_f_ec_option_use_vat_tax' ), $by = $( '#ecst_f_ec_vat_by_country' );
		function visible() { $box.prop( 'hidden', ! ( $use.is( ':checked' ) && $by.is( ':checked' ) ) ); }
		$use.add( $by ).on( 'change', visible );
		visible();
		refreshEmpty( $box );
	}

	/* ================================================================== */
	/* Canada grid ( ec_option_canada_tax_options )                        */
	/* ================================================================== */
	function initCanada( $box ) {
		var $roleSel = $box.find( '.ectx-role' );

		function showRole() {
			var role = $roleSel.val();
			$box.find( '.ectx-canada-frame' ).each( function() { $( this ).prop( 'hidden', String( $( this ).data( 'role' ) ) !== role ); } );
		}
		$roleSel.on( 'change', showRole );
		showRole();

		/* every write rewrites the whole option server side, so writes go out one at a time */
		var enqueue = serial();
		function send( $row, field, value, ok, fail ) {
			enqueue( function( next ) {
				post( 'ecv2_tax_canada_update', { province: $row.data( 'province' ), role: $row.closest( '.ectx-canada-table' ).data( 'role' ), field: field, value: value }, ok, fail ).always( next );
			} );
		}

		/* ✓ / Enter: the changed GST / PST / HST cells of the row, in order */
		editable( $box, function( $row, done ) {
			var $st = $row.find( '.ecst-state' ), jobs = [], bad = false;
			dirtyFields( $row ).each( function() {
				var $in = $( this ), raw = String( $in.val() || '' ), rate = rateOf( $in );
				if ( rate === null ) { bad = true; return false; }
				jobs.push( { $in: $in, field: $in.data( 'field' ), raw: raw, rate: rate } );
			} );
			if ( bad ) { state( $st, 'error', t( 'enter_rate', 'Enter a rate' ) ); done( false ); return; }
			state( $st, 'saving', t( 'saving', 'Saving…' ) );
			var i = 0;
			( function step() {
				if ( i >= jobs.length ) { state( $st, 'saved', t( 'saved', 'Saved' ) ); done( true ); return; }
				var job = jobs[ i++ ];
				send( $row, job.field, job.rate, function() { job.$in.data( 'ectxOrig', job.raw ); step(); }, function( msg ) { state( $st, 'error', msg ); done( false ); } );
			} )();
		} );

		$box.on( 'change', '.ectx-collect', function() {
			var $cb = $( this ), $row = $cb.closest( '.ectx-row' ), on = $cb.is( ':checked' ), $st = $row.find( '.ecst-state' );
			setToggle( $cb.closest( '.ectx-toggle' ), on );
			$row.toggleClass( 'is-off', ! on );
			state( $st, 'saving', t( 'saving', 'Saving…' ) );
			send( $row, 'collect', on ? 1 : 0, function() {
				state( $st, 'saved', t( 'saved', 'Saved' ) );
				if ( $row.hasClass( 'is-dirty' ) ) { flagPending( $row ); }
			}, function( msg ) {
				setToggle( $cb.closest( '.ectx-toggle' ), ! on );
				$row.toggleClass( 'is-off', on );
				state( $st, 'error', msg );
			} );
		} );

		/* follows the declared enable toggle: the server ticks / unticks every province on save; mirror that here */
		var $enable = $( '#ecst_f_ec_option_enable_easy_canada_tax' );
		$enable.on( 'change', function() {
			var on = $( this ).is( ':checked' );
			$box.prop( 'hidden', ! on );
			$box.find( '.ectx-row' ).each( function() {
				setToggle( $( this ).find( '.ectx-toggle' ), on );
				$( this ).toggleClass( 'is-off', ! on );
			} );
		} );
	}

	$( function() {
		$( '.ectx-rates[data-kind="state"], .ectx-rates[data-kind="country"]' ).each( function() { initRates( $( this ) ); } );
		$( '.ectx-rates[data-kind="vat"]' ).each( function() { initVatCountries( $( this ) ); } );
		$( '.ectx-canada' ).each( function() { initCanada( $( this ) ); } );
	} );
} )( jQuery );
