/* WP EasyCart Admin — Settings pages V2 ( declared pages + settings home ). */
( function( $ ) {
	'use strict';

	var V = window.ecst_vars || { ajax: window.ajaxurl, nonce: '', i18n: {} };
	var T = V.i18n || {};

	function toast( msg, kind ) {
		if ( typeof window.ecv2_toast === 'function' ) {
			// catalog-v2.js appends to #ecv2-toast-container, which settings pages do not print: without it every toast was silently dropped.
			if ( ! document.getElementById( 'ecv2-toast-container' ) ) { $( '<div id="ecv2-toast-container" role="status" aria-live="polite"></div>' ).appendTo( 'body' ); }
			return window.ecv2_toast( msg, kind || 'success' );
		}
		var $t = $( '<div class="ecdv2-toast" style="position:fixed;bottom:24px;right:24px;z-index:99999;background:#111827;color:#fff;padding:10px 14px;border-radius:8px;font-size:13px"></div>' ).text( msg ).appendTo( 'body' );
		setTimeout( function() { $t.fadeOut( 200, function() { $t.remove(); } ); }, 2400 );
	}
	/* The V2 confirm dialog ( catalog-v2.js injects its markup on first use ). okLabel names the primary button for
	 * this one question ( "Create page" ) and the default label is put back once it closes. */
	function confirmBox( title, text, okLabel ) {
		if ( typeof window.ecv2_show_confirm === 'function' ) {
			var p = window.ecv2_show_confirm( title, text ), $ok = $( '#ecv2-confirm-ok' ), was = $ok.text();
			if ( okLabel && $ok.length ) {
				$ok.text( okLabel );
				p = p.then( function( ok ) { $ok.text( was || T.confirm || 'Confirm' ); return ok; } );
			}
			return p;
		}
		return Promise.resolve( window.confirm( title + ( text ? '\n\n' + text : '' ) ) );
	}
	function post( action, data, ok, fail ) {
		data = $.extend( { action: action, nonce: V.nonce }, data );
		return $.post( V.ajax, data ).done( function( r ) {
			if ( r && r.success ) { ok( r.data || {} ); } else { fail( ( r && r.data && r.data.message ) ? r.data.message : 'Request failed.' ); }
		} ).fail( function() { fail( 'Request failed.' ); } );
	}
	function esc( s ) { return $( '<i>' ).text( s == null ? '' : String( s ) ).html().replace( /"/g, '&quot;' ).replace( /'/g, '&#39;' ); } // safe in text and in attribute values
	function fmt( s, a, b ) { return String( s ).replace( '%1$d', a ).replace( '%2$d', b ).replace( '%d', a ); }

	/* ================================================================== */
	/* Declared settings page                                              */
	/* ================================================================== */
	var $wrap = $( '#ecst' );
	if ( $wrap.length ) {
		var page = $wrap.data( 'page' );
		var initial = {};   // key -> value at load ( for discard )
		var initialChips = {}; // key -> [ { value, label, hint } ] at load, for remote pickers ( their labels are not in the DOM otherwise )
		var dirty = {};     // key -> pending value ( batch fields )
		var $status = $( '#ecst_status' );
		var $savebar = $( '#ecst_savebar' );

		function rowOf( key ) { return $( '#ecst-' + $.escapeSelector( key ) ); }
		function valueOf( $row ) {
			var type = $row.data( 'type' );
			if ( type === 'toggle' ) { return $row.find( '.ecst-input' ).is( ':checked' ) ? '1' : '0'; }
			if ( type === 'pills' ) { var $on = $row.find( '.ecst-input:checked' ); return $on.length ? String( $on.val() ) : ''; }
			if ( type === 'color' ) { return String( $row.find( '.ecst-color-hex' ).val() || '' ).trim(); }
			return String( $row.find( '.ecst-input' ).val() || '' );
		}
		function setState( key, kind, text ) {
			var $s = $( '#ecst_state_' + $.escapeSelector( key ) );
			$s.removeClass( 'is-saved is-saving is-error' ).text( text || '' );
			if ( kind ) { $s.addClass( 'is-' + kind ); }
			if ( kind === 'saved' ) { setTimeout( function() { if ( $s.hasClass( 'is-saved' ) ) { $s.removeClass( 'is-saved' ).text( '' ); } }, 2200 ); }
		}
		/* level: falsy = save error ( red, control outlined ), true / 'warn' = non-blocking warning ( amber ),
		 * 'error' = a problem the page reports on every render ( red, row marked, control outlined ). */
		function setMsg( key, text, level ) {
			var $m = $( '#ecst_msg_' + $.escapeSelector( key ) ), warn = ( level === true || level === 'warn' ), err = ( level === 'error' );
			$m.toggleClass( 'is-warn', warn ).toggleClass( 'is-error', err ).text( text || '' ).prop( 'hidden', ! text );
			rowOf( key ).toggleClass( 'has-error', err && !! text ).find( '.ecst-in, .ecst-textarea, .ecst-pairs' ).toggleClass( 'is-invalid', !! text && ! warn );
		}

		/* Problems the page reports ( data-health pages: a store page without its shortcode ). The server re-sends the
		 * list after every save and action; the banner and the affected rows are redrawn from it, and rows that were
		 * flagged earlier but are fine now are cleared. */
		var healthKeys = {};
		$wrap.find( '.ecst-row.has-error[data-key]' ).each( function() { healthKeys[ $( this ).data( 'key' ) ] = true; } );
		function renderBanner( list ) {
			var $b = $( '#ecst_banner' );
			if ( ! $b.length ) { return; }
			if ( ! list.length ) { $b.prop( 'hidden', true ); return; }
			var title = fmt( list.length === 1 ? ( T.banner_title || '%d store page needs attention' ) : ( T.banner_title_many || '%d store pages need attention' ), list.length );
			var html = '<span class="ecst-banner-ic dashicons dashicons-warning" aria-hidden="true"></span><div class="ecst-banner-body">' +
				'<b class="ecst-banner-title">' + esc( title ) + '</b><span class="ecst-banner-sub">' + esc( T.banner_sub || '' ) + '</span><ul class="ecst-banner-list">';
			list.forEach( function( p ) {
				html += '<li><b>' + esc( p.label ) + '</b><span>' + esc( p.text ) + '</span>' +
					'<a class="ecst-banner-fix" href="#ecst-' + esc( p.key ) + '" data-key="' + esc( p.key ) + '">' + esc( T.fix || 'Fix' ) + ' →</a>' +
					( p.edit_url ? '<a class="ecst-banner-edit" href="' + esc( p.edit_url ) + '" target="_blank" rel="noopener noreferrer">' + esc( T.edit_page || 'Edit page' ) + ' ↗</a>' : '' ) + '</li>';
			} );
			$b.html( html + '</ul></div>' ).prop( 'hidden', false );
		}
		function applyProblems( list ) {
			if ( ! Array.isArray( list ) ) { return; }
			var now = {};
			list.forEach( function( p ) { if ( p && p.key ) { now[ p.key ] = p; } } );
			Object.keys( healthKeys ).forEach( function( k ) { if ( ! now[ k ] ) { setMsg( k, '' ); } } );
			Object.keys( now ).forEach( function( k ) { healthKeys[ k ] = true; setMsg( k, now[ k ].text, 'error' ); } );
			renderBanner( list );
		}
		$wrap.on( 'click', '.ecst-banner-fix[data-key]', function( e ) {
			e.preventDefault();
			jumpTo( String( $( this ).attr( 'data-key' ) ) );
			var $in = rowOf( String( $( this ).attr( 'data-key' ) ) ).find( '.ecst-picker-search, .ecst-input' ).first();
			if ( $in.length && $in.is( ':visible' ) ) { setTimeout( function() { $in.trigger( 'focus' ); }, 500 ); }
		} );
		/* Redraw a row after the server changed its value ( an action such as "Create store page" ). */
		function applyField( key, d ) {
			var $row = rowOf( key );
			if ( ! $row.length ) { return; }
			var $rp = $row.find( '.ecst-picker.is-remote' );
			if ( $rp.length && Array.isArray( d.chips ) ) {
				if ( isAttach( $rp ) ) { $row.find( 'input.ecst-input' ).val( d.value == null ? '' : String( d.value ) ); }
				rpRender( $rp, d.chips );
				initialChips[ key ] = d.chips.slice();
			} else if ( $row.data( 'type' ) === 'toggle' ) {
				var on = String( d.value ) === '1';
				$row.find( '.ecst-input' ).prop( 'checked', on );
				$row.find( '.ecst-toggle' ).toggleClass( 'is-on', on );
				$row.toggleClass( 'is-on', on ).toggleClass( 'is-off', ! on );
				$row.find( '.ecst-onoff' ).text( on ? ( T.on || 'On' ) : ( T.off || 'Off' ) );
			} else {
				$row.find( '.ecst-input' ).val( d.value == null ? '' : String( d.value ) );
			}
			initial[ key ] = valueOf( $row );
			delete dirty[ key ];
			$row.find( '.ecst-in, .ecst-textarea, .ecst-color-hex, .ecst-pairs' ).removeClass( 'is-dirty is-invalid' );
			setMsg( key, '' );
			setState( key, 'saved', T.saved );
			applyDeps( key );
			refreshSavebar();
		}
		function setStatus( kind, text ) {
			$status.removeClass( 'is-dirty is-saving is-error ecv2-chip-green' ).text( text );
			if ( kind === 'ok' ) { $status.addClass( 'ecv2-chip-green' ); } else { $status.addClass( 'is-' + kind ); }
		}

		/* dependent rows */
		function applyDeps( parentKey ) {
			var $parent = rowOf( parentKey );
			if ( ! $parent.length ) { return; }
			var val = valueOf( $parent );
			$( '.ecst-row[data-parent="' + parentKey + '"]' ).each( function() {
				var when = String( $( this ).data( 'show-when' ) ).split( '|' );
				var show = when.indexOf( val ) !== -1 && ! $parent.prop( 'hidden' );
				$( this ).prop( 'hidden', ! show );
				applyDeps( $( this ).data( 'key' ) );
			} );
			refreshFold( $parent.closest( '.ecst-section' ) );
		}
		/* The fold names only advanced rows that will appear: not ones hidden by an off parent, and not ones nested
		 * under another advanced row ( they show inside that row's group ). */
		function refreshFold( $sec ) {
			var $fold = $sec.find( '.ecst-fold' ), $btn = $fold.find( '.ecst-fold-btn' );
			if ( ! $btn.length ) { return; }
			var names = [];
			$sec.find( '.ecst-row.is-advanced[data-key]' ).not( '[data-type="html"]' ).each( function() {
				var $r = $( this ), p = $r.data( 'parent' );
				if ( $r.prop( 'hidden' ) || ( p && rowOf( p ).hasClass( 'is-advanced' ) ) ) { return; }
				names.push( $r.data( 'label' ) );
			} );
			$btn.data( 'count', names.length );
			if ( $sec.hasClass( 'is-adv-open' ) ) { return; }
			$fold.prop( 'hidden', ! names.length );
			$btn.text( names.length === 1 ? ( T.show_adv_one || fmt( T.show_adv, 1 ) ) : fmt( T.show_adv, names.length ) );
			$fold.find( '.ecst-fold-names' ).text( names.slice( 0, 4 ).join( ' · ' ) + ( names.length > 4 ? '…' : '' ) );
		}

		/* autosave ( toggle / select / pills ) */
		function saveNow( key, value ) {
			var changes = {};
			changes[ key ] = value;
			setState( key, 'saving', T.saving );
			setMsg( key, '' );
			post( 'ecv2_settings_save', { page: page, changes: JSON.stringify( changes ) }, function( d ) {
				if ( d.errors && d.errors[ key ] ) {
					setState( key, 'error', T.save_failed );
					setMsg( key, d.errors[ key ] );
					return;
				}
				setState( key, 'saved', T.saved );
				if ( d.warnings && d.warnings[ key ] ) { setMsg( key, d.warnings[ key ], true ); }
				initial[ key ] = value;
				syncChips( key, d.saved );
				if ( d.problems ) { applyProblems( d.problems ); }
				applyReset( d.reset );
				$( document ).trigger( 'ecst:saved', [ d, [ key ] ] );
			}, function( m ) {
				setState( key, 'error', T.save_failed );
				setMsg( key, m );
			} );
		}

		/* batch ( text-like ) */
		function refreshSavebar() {
			var keys = Object.keys( dirty );
			if ( ! keys.length ) {
				$savebar.prop( 'hidden', true );
				setStatus( 'ok', T.all_saved );
				return;
			}
			$savebar.prop( 'hidden', false );
			$( '#ecst_savebar_count' ).text( keys.length === 1 ? T.unsaved_one : fmt( T.unsaved_many, keys.length ) );
			$( '#ecst_savebar_names' ).text( keys.map( function( k ) { return rowOf( k ).data( 'label' ); } ).join( ', ' ) );
			setStatus( 'dirty', keys.length === 1 ? T.unsaved_one : fmt( T.unsaved_many, keys.length ) );
		}
		function markDirty( key ) {
			var $row = rowOf( key );
			var v = valueOf( $row );
			if ( v === initial[ key ] ) { delete dirty[ key ]; } else { dirty[ key ] = v; }
			$row.find( '.ecst-in, .ecst-textarea, .ecst-color-hex, .ecst-pairs' ).toggleClass( 'is-dirty', key in dirty );
			refreshSavebar();
		}
		function saveBatch() {
			var keys = Object.keys( dirty );
			if ( ! keys.length ) { return; }
			var changes = $.extend( {}, dirty );
			$( '#ecst_save' ).prop( 'disabled', true );
			setStatus( 'saving', T.saving );
			keys.forEach( function( k ) { setState( k, 'saving', T.saving ); setMsg( k, '' ); } );
			post( 'ecv2_settings_save', { page: page, changes: JSON.stringify( changes ) }, function( d ) {
				$( '#ecst_save' ).prop( 'disabled', false );
				var failed = 0;
				keys.forEach( function( k ) {
					if ( d.errors && d.errors[ k ] ) { failed++; setState( k, 'error', T.save_failed ); setMsg( k, d.errors[ k ] ); return; }
					initial[ k ] = changes[ k ];
					delete dirty[ k ];
					rowOf( k ).find( '.ecst-in, .ecst-textarea, .ecst-color-hex, .ecst-pairs' ).removeClass( 'is-dirty' );
					setState( k, 'saved', T.saved );
					if ( d.warnings && d.warnings[ k ] ) { setMsg( k, d.warnings[ k ], true ); }
					syncChips( k, d.saved );
				} );
				if ( d.problems ) { applyProblems( d.problems ); }
				applyReset( d.reset );
				$( document ).trigger( 'ecst:saved', [ d, keys ] );
				refreshSavebar();
				if ( failed ) { setStatus( 'error', T.save_failed ); toast( d.message || '', 'error' ); } else { toast( d.message || T.saved, 'success' ); }
			}, function( m ) {
				$( '#ecst_save' ).prop( 'disabled', false );
				setStatus( 'error', T.save_failed );
				toast( m, 'error' );
			} );
		}
		/* Server reply 'reset' ( key => value ): settings the save changed as a side effect ( e.g. turning a carrier off
		 * clears its details ). Redraw them and treat the new value as saved, so nothing stale is left to re-save. */
		function applyReset( map ) {
			if ( ! map ) { return; }
			$.each( map, function( k, v ) {
				var $row = rowOf( k ), val = v == null ? '' : String( v );
				if ( ! $row.length ) { return; }
				if ( $row.data( 'type' ) === 'toggle' ) {
					var on = val === '1';
					$row.find( '.ecst-input' ).prop( 'checked', on );
					$row.find( '.ecst-toggle' ).toggleClass( 'is-on', on );
					$row.toggleClass( 'is-on', on ).toggleClass( 'is-off', ! on );
					$row.find( '.ecst-onoff' ).text( on ? ( T.on || 'On' ) : ( T.off || 'Off' ) );
				} else {
					$row.find( '.ecst-input' ).val( val );
				}
				initial[ k ] = valueOf( $row );
				delete dirty[ k ];
				$row.find( '.ecst-in, .ecst-textarea, .ecst-color-hex, .ecst-pairs' ).removeClass( 'is-dirty is-invalid' );
				setMsg( k, '' );
				if ( $row.data( 'type' ) === 'toggle' ) { applyDeps( k ); }
			} );
			refreshSavebar();
		}
		function discard() {
			Object.keys( dirty ).forEach( function( k ) {
				var $row = rowOf( k ), type = $row.data( 'type' ), v = initial[ k ];
				if ( type === 'color' ) { $row.find( '.ecst-color-hex' ).val( v ); $row.find( '.ecst-color-pick' ).val( v || '#ffffff' ); }
				else if ( type === 'pairs' ) { pairsLoad( $row.find( '.ecst-pairs' ), v ); }
				else if ( $row.find( '.ecst-picker.is-attach' ).length ) { $row.find( 'input.ecst-input' ).val( v ); rpRender( $row.find( '.ecst-picker.is-attach' ), initialChips[ k ] || [] ); }
				else if ( $row.find( '.ecst-picker.is-remote' ).length ) { rpRender( $row.find( '.ecst-picker.is-remote' ), initialChips[ k ] || [] ); }
				else if ( type === 'multiselect' && $row.find( '.ecst-picker' ).length ) {
					var $pk = $row.find( '.ecst-picker' ), chosen = String( v || '' ).split( String( $pk.data( 'sep' ) || ',' ) );
					$pk.find( '.ecst-picker-opt' ).each( function() { var on = chosen.indexOf( String( $( this ).attr( 'data-value' ) ) ) !== -1; $( this ).toggleClass( 'is-on', on ).attr( 'aria-selected', on ? 'true' : 'false' ); } );
					pickerSync( $pk );
					$pk.find( '.ecst-input' ).val( v );
				}
				else if ( type === 'multiselect' ) {
					var $box = $row.find( '.ecst-multi' ), parts = String( v || '' ).split( String( $box.data( 'sep' ) || ',' ) );
					$box.find( '.ecst-input' ).val( v );
					$box.find( '.ecst-multi-opt' ).each( function() { var on = parts.indexOf( String( this.value ) ) !== -1; this.checked = on; $( this ).closest( '.ecst-pill' ).toggleClass( 'is-on', on ); } );
				}
				else { $row.find( '.ecst-input' ).val( v ); }
				$row.find( '.ecst-in, .ecst-textarea, .ecst-color-hex, .ecst-pairs' ).removeClass( 'is-dirty is-invalid' );
				setMsg( k, '' );
			} );
			dirty = {};
			refreshSavebar();
		}

		/* wire rows */
		$wrap.find( '.ecst-row' ).each( function() {
			var $row = $( this ), key = $row.data( 'key' );
			if ( ! key || $row.data( 'type' ) === 'html' ) { return; }
			initial[ key ] = valueOf( $row );
			var $rp = $row.find( '.ecst-picker.is-remote' );
			if ( $rp.length ) { initialChips[ key ] = rpChips( $rp ); }
		} );
		$wrap.on( 'change', '.ecst-type-toggle .ecst-input', function() {
			var $row = $( this ).closest( '.ecst-row' ), key = $row.data( 'key' );
			$row.find( '.ecst-toggle' ).toggleClass( 'is-on', this.checked );
			$row.toggleClass( 'is-on', this.checked ).toggleClass( 'is-off', ! this.checked );
			$row.find( '.ecst-onoff' ).text( this.checked ? ( T.on || 'On' ) : ( T.off || 'Off' ) );
			applyDeps( key );
			saveNow( key, this.checked ? '1' : '0' );
		} );
		$wrap.on( 'change', '.ecst-type-select .ecst-input', function() {
			var $row = $( this ).closest( '.ecst-row' ), key = $row.data( 'key' );
			applyDeps( key );
			saveNow( key, String( $( this ).val() ) );
		} );
		$wrap.on( 'change', '.ecst-type-pills .ecst-input', function() {
			var $row = $( this ).closest( '.ecst-row' ), key = $row.data( 'key' );
			$row.find( '.ecst-pill' ).removeClass( 'is-on' );
			$( this ).closest( '.ecst-pill' ).addClass( 'is-on' );
			applyDeps( key );
			saveNow( key, String( $( this ).val() ) );
		} );
		$wrap.on( 'input', '.ecst-row[data-mode="batch"] .ecst-input, .ecst-row[data-mode="batch"] .ecst-color-hex', function() {
			var $row = $( this ).closest( '.ecst-row' );
			if ( $( this ).hasClass( 'ecst-color-pick' ) ) { $row.find( '.ecst-color-hex' ).val( $( this ).val() ); }
			else if ( $( this ).hasClass( 'ecst-color-hex' ) && /^#[0-9a-f]{6}$/i.test( $( this ).val() ) ) { $row.find( '.ecst-color-pick' ).val( $( this ).val() ); }
			markDirty( $row.data( 'key' ) );
		} );
		/* URL field with an attached page picker: a URL typed by hand no longer matches the chip, so the chip goes ( the label comes back from the server on save when the URL points at a local page ). */
		$wrap.on( 'input', '.ecst-row.has-attach input.ecst-input', function() {
			var $pk = $( this ).closest( '.ecst-row' ).find( '.ecst-picker.is-attach' ), chips = rpChips( $pk );
			if ( chips.length && chips[ 0 ].value !== String( this.value ) ) { rpRender( $pk, [] ); }
		} );
		/* 'exclusive' choices ( e.g. "No restrictions" ): picking one clears the rest, picking any other clears it, and clearing everything falls back to it. */
		function exclusiveOf( $box ) { var x = $box.attr( 'data-exclusive' ); return x ? String( x ).split( '|' ) : []; }
		function applyExclusive( excl, value, on, all, set ) {
			if ( ! excl.length ) { return; }
			var isExcl = excl.indexOf( String( value ) ) !== -1;
			if ( on ) { all().forEach( function( v ) { if ( v !== String( value ) && ( isExcl || excl.indexOf( v ) !== -1 ) ) { set( v, false ); } } ); }
			if ( ! all().some( function( v ) { return set( v ); } ) ) { set( excl[0], true ); }
		}
		$wrap.on( 'change', '.ecst-multi-opt', function() {
			var $box = $( this ).closest( '.ecst-multi' ), $row = $box.closest( '.ecst-row' ), sep = String( $box.data( 'sep' ) || ',' ), vals = [];
			var $opts = $box.find( '.ecst-multi-opt' );
			applyExclusive( exclusiveOf( $box ), this.value, this.checked,
				function() { return $opts.map( function() { return String( this.value ); } ).get(); },
				function( v, on ) { var $o = $opts.filter( function() { return String( this.value ) === v; } ); if ( typeof on === 'boolean' ) { $o.prop( 'checked', on ); } return $o.prop( 'checked' ); } );
			$opts.each( function() { $( this ).closest( '.ecst-pill' ).toggleClass( 'is-on', this.checked ); } );
			$box.find( '.ecst-multi-opt:checked' ).each( function() { vals.push( String( this.value ) ); } );
			$box.find( '.ecst-input' ).val( vals.join( sep ) );
			markDirty( $row.data( 'key' ) );
		} );

		/* multiselect picker ( long lists ): chips + searchable listbox. Chips follow option order, as the server stores them. */
		function pickerSync( $pk ) {
			var sep = String( $pk.data( 'sep' ) || ',' ), vals = [], $chips = $pk.find( '.ecst-picker-chips' ).empty();
			$pk.find( '.ecst-picker-opt.is-on' ).each( function() {
				var $o = $( this ), name = $o.find( '.ecst-picker-name' ).text(), hint = $o.find( '.ecst-picker-hint' ).text();
				vals.push( String( $o.attr( 'data-value' ) ) );
				var $chip = $( '<span class="ecst-chip"></span>' ).attr( 'data-value', $o.attr( 'data-value' ) ).append( $( '<span class="ecst-chip-name"></span>' ).text( name ) );
				if ( hint ) { $chip.append( $( '<span class="ecst-chip-hint"></span>' ).text( hint ) ); }
				$chip.append( $( '<button type="button" class="ecst-chip-x">×</button>' ).attr( 'aria-label', String( T.remove || 'Remove %s' ).replace( '%s', name ) ) );
				$chips.append( $chip );
			} );
			$pk.find( '.ecst-input' ).val( vals.join( sep ) );
			var $count = $pk.find( '.ecst-picker-count' );
			$count.text( vals.length ? fmt( T.picker_count, vals.length, $count.data( 'total' ) ) : ( T.picker_none || '' ) );
			$pk.find( '.ecst-picker-clear' ).prop( 'hidden', ! vals.length );
		}
		function pickerChanged( $pk ) {
			pickerSync( $pk );
			markDirty( $pk.closest( '.ecst-row' ).data( 'key' ) );
		}
		function pickerToggle( $pk, $opt, on ) {
			on = ( typeof on === 'boolean' ) ? on : ! $opt.hasClass( 'is-on' );
			$opt.toggleClass( 'is-on', on ).attr( 'aria-selected', on ? 'true' : 'false' );
			var $opts = $pk.find( '.ecst-picker-opt' );
			applyExclusive( exclusiveOf( $pk ), $opt.attr( 'data-value' ), on,
				function() { return $opts.map( function() { return String( $( this ).attr( 'data-value' ) ); } ).get(); },
				function( v, set ) { var $o = $opts.filter( function() { return String( $( this ).attr( 'data-value' ) ) === v; } ); if ( typeof set === 'boolean' ) { $o.toggleClass( 'is-on', set ).attr( 'aria-selected', set ? 'true' : 'false' ); } return $o.hasClass( 'is-on' ); } );
			pickerChanged( $pk );
		}
		function pickerActive( $pk, $opt ) {
			$pk.find( '.ecst-picker-opt.is-active' ).removeClass( 'is-active' );
			var $in = $pk.find( '.ecst-picker-search' );
			if ( ! $opt || ! $opt.length ) { $in.removeAttr( 'aria-activedescendant' ); return; }
			$opt.addClass( 'is-active' );
			$in.attr( 'aria-activedescendant', $opt.attr( 'id' ) );
			var menu = $pk.find( '.ecst-picker-menu' )[ 0 ], el = $opt[ 0 ];
			if ( el.offsetTop < menu.scrollTop ) { menu.scrollTop = el.offsetTop; }
			else if ( el.offsetTop + el.offsetHeight > menu.scrollTop + menu.clientHeight ) { menu.scrollTop = el.offsetTop + el.offsetHeight - menu.clientHeight; }
		}
		function pickerFilter( $pk ) {
			var q = String( $pk.find( '.ecst-picker-search' ).val() || '' ).trim().toLowerCase(), hits = 0;
			$pk.find( '.ecst-picker-opt' ).each( function() {
				var hit = ! q || String( $( this ).attr( 'data-search' ) ).indexOf( q ) !== -1;
				this.hidden = ! hit;
				if ( hit ) { hits++; }
			} );
			$pk.find( '.ecst-picker-empty' ).prop( 'hidden', hits > 0 );
			var $act = $pk.find( '.ecst-picker-opt.is-active' );
			if ( ! $act.length || $act.prop( 'hidden' ) ) { pickerActive( $pk, q ? $pk.find( '.ecst-picker-opt' ).not( '[hidden]' ).first() : null ); }
		}
		function pickerOpen( $pk, open ) {
			var $menu = $pk.find( '.ecst-picker-menu' );
			if ( open === ! $menu.prop( 'hidden' ) ) { return; }
			if ( open ) { $wrap.find( '.ecst-picker.is-open' ).not( $pk ).each( function() { pickerOpen( $( this ), false ); } ); if ( isRemote( $pk ) ) { rpSearch( $pk, true ); } else { pickerFilter( $pk ); } }
			else { pickerActive( $pk, null ); }
			$menu.prop( 'hidden', ! open );
			$pk.toggleClass( 'is-open', open ).find( '.ecst-picker-search' ).attr( 'aria-expanded', open ? 'true' : 'false' );
		}

		/* Remote picker ( lists too long to print: categories, manufacturers, option sets, WordPress pages ).
		 * The chips are the source of truth; the menu only ever holds the last search's matches, fetched from
		 * ecv2_settings_option_search. data-max="1" makes it a single-value control that autosaves like a select. */
		function isRemote( $pk ) { return $pk.hasClass( 'is-remote' ); }
		/* A page picker attached to a URL field ( .is-attach ): it has no hidden input of its own. The URL input beside it
		 * is the row's value; picking a page writes the permalink there, the chip names the page that URL points at, and
		 * typing another URL drops the chip. */
		function isAttach( $pk ) { return $pk.hasClass( 'is-attach' ); }
		function attachInput( $pk ) { return $pk.closest( '.ecst-row' ).find( 'input.ecst-input' ).first(); }
		/* After a save, a search picker redraws its chip from the server's labels ( a URL that now points at a local page gets its title ). */
		function syncChips( key, saved ) {
			if ( ! saved || ! saved[ key ] || ! Array.isArray( saved[ key ].chips ) ) { return; }
			var $rp = rowOf( key ).find( '.ecst-picker.is-remote' );
			if ( ! $rp.length ) { return; }
			rpRender( $rp, saved[ key ].chips );
			initialChips[ key ] = saved[ key ].chips.slice();
		}
		function rpChips( $pk ) {
			return $pk.find( '.ecst-picker-chips .ecst-chip' ).map( function() {
				return { value: String( $( this ).attr( 'data-value' ) ), label: $( this ).find( '.ecst-chip-name' ).text(), hint: $( this ).find( '.ecst-chip-hint' ).text() };
			} ).get();
		}
		function rpChip( item ) {
			var $chip = $( '<span class="ecst-chip"></span>' ).attr( 'data-value', item.value ).append( $( '<span class="ecst-chip-name"></span>' ).text( item.label ) );
			if ( item.hint ) { $chip.append( $( '<span class="ecst-chip-hint"></span>' ).text( item.hint ) ); }
			return $chip.append( $( '<button type="button" class="ecst-chip-x">×</button>' ).attr( 'aria-label', String( T.remove || 'Remove %s' ).replace( '%s', item.label ) ) );
		}
		/* Redraw the chips from a list and sync the hidden input, count and menu ticks. Does not save. */
		function rpRender( $pk, list ) {
			var sep = String( $pk.data( 'sep' ) || ',' ), $chips = $pk.find( '.ecst-picker-chips' ).empty(), vals = [];
			list.forEach( function( item ) { vals.push( item.value ); $chips.append( rpChip( item ) ); } );
			$pk.find( '.ecst-input' ).val( vals.join( sep ) );
			$pk.find( '.ecst-picker-count' ).text( vals.length ? fmt( T.picker_n || '%d selected', vals.length ) : ( T.picker_none || '' ) );
			$pk.find( '.ecst-picker-clear' ).prop( 'hidden', ! vals.length );
			$pk.find( '.ecst-picker-opt' ).each( function() { var on = vals.indexOf( String( $( this ).attr( 'data-value' ) ) ) !== -1; $( this ).toggleClass( 'is-on', on ).attr( 'aria-selected', on ? 'true' : 'false' ); } );
		}
		/* After the user changed the chips: autosave rows ( a select found by search ) save now, others join the save bar. */
		function rpCommit( $pk ) {
			var $row = $pk.closest( '.ecst-row' ), key = $row.data( 'key' );
			applyDeps( key );
			if ( $row.data( 'mode' ) === 'auto' ) { saveNow( key, valueOf( $row ) ); } else { markDirty( key ); }
		}
		function rpSet( $pk, list ) { rpRender( $pk, list ); rpCommit( $pk ); }
		function rpAdd( $pk, item ) {
			var list = rpChips( $pk ), excl = exclusiveOf( $pk ), max = parseInt( $pk.attr( 'data-max' ), 10 ) || 0;
			if ( list.some( function( c ) { return c.value === item.value; } ) ) { return; }
			if ( max === 1 ) { list = []; }
			else if ( excl.length ) { var isExcl = excl.indexOf( item.value ) !== -1; list = list.filter( function( c ) { return isExcl ? false : excl.indexOf( c.value ) === -1; } ); }
			list.push( item );
			if ( isAttach( $pk ) ) { attachInput( $pk ).val( item.value ); }
			rpSet( $pk, list );
		}
		function rpRemove( $pk, value ) {
			var list = rpChips( $pk ).filter( function( c ) { return c.value !== String( value ); } );
			if ( isAttach( $pk ) ) { attachInput( $pk ).val( '' ); }
			rpSet( $pk, list );
		}
		function rpToggle( $pk, $opt ) {
			if ( ! $opt || ! $opt.length ) { return; }
			var value = String( $opt.attr( 'data-value' ) );
			if ( $opt.hasClass( 'is-on' ) ) { rpRemove( $pk, value ); return; }
			rpAdd( $pk, { value: value, label: $opt.find( '.ecst-picker-name' ).text(), hint: $opt.find( '.ecst-picker-hint' ).text() } );
			if ( ( parseInt( $pk.attr( 'data-max' ), 10 ) || 0 ) === 1 ) { $pk.find( '.ecst-picker-search' ).val( '' ); pickerOpen( $pk, false ); }
		}
		function rpShow( $pk, results, term ) {
			var $menu = $pk.find( '.ecst-picker-menu' ), id = $menu.attr( 'id' ), chosen = rpChips( $pk ).map( function( c ) { return c.value; } ), limit = parseInt( $pk.attr( 'data-limit' ), 10 ) || 50;
			$menu.find( '.ecst-picker-opt, .ecst-picker-more' ).remove();
			$menu.find( '.ecst-picker-status' ).prop( 'hidden', true );
			$menu.find( '.ecst-picker-empty' ).prop( 'hidden', results.length > 0 );
			results.forEach( function( r, i ) {
				var value = String( r.id ), on = chosen.indexOf( value ) !== -1;
				var $li = $( '<li class="ecst-picker-opt" role="option"></li>' ).attr( { id: id + '_' + i, 'data-value': value, 'aria-selected': on ? 'true' : 'false' } ).toggleClass( 'is-on', on )
					.append( '<span class="ecst-picker-check" aria-hidden="true"></span>' ).append( $( '<span class="ecst-picker-name"></span>' ).text( r.label ) );
				if ( r.hint ) { $li.append( $( '<span class="ecst-picker-hint"></span>' ).text( r.hint ) ); }
				$menu.append( $li );
			} );
			if ( results.length >= limit ) { $menu.append( $( '<li class="ecst-picker-more" role="presentation"></li>' ).text( fmt( T.picker_more || '', limit ) ) ); }
			pickerActive( $pk, term ? $menu.find( '.ecst-picker-opt' ).first() : null );
		}
		function rpSearch( $pk, now ) {
			var term = String( $pk.find( '.ecst-picker-search' ).val() || '' ).trim(), cache = $pk.data( 'rpCache' ) || {};
			$pk.data( 'rpCache', cache );
			clearTimeout( $pk.data( 'rpTimer' ) );
			if ( cache[ term ] ) { rpShow( $pk, cache[ term ], term ); return; }
			var run = function() {
				var old = $pk.data( 'rpXhr' );
				if ( old && old.abort ) { old.abort(); }
				$pk.find( '.ecst-picker-menu' ).find( '.ecst-picker-status' ).prop( 'hidden', false ).end().find( '.ecst-picker-empty' ).prop( 'hidden', true );
				var xhr = $.get( V.ajax, { action: 'ecv2_settings_option_search', nonce: V.nonce, page: page, key: $pk.closest( '.ecst-row' ).data( 'key' ), term: term } ).done( function( r ) {
					if ( ! r || ! r.success ) { rpShow( $pk, [], term ); return; }
					cache[ term ] = r.data.results || [];
					if ( String( $pk.find( '.ecst-picker-search' ).val() || '' ).trim() === term ) { rpShow( $pk, cache[ term ], term ); }
				} ).fail( function( x, status ) { if ( status !== 'abort' ) { rpShow( $pk, [], term ); } } );
				$pk.data( 'rpXhr', xhr );
			};
			if ( now ) { run(); } else { $pk.data( 'rpTimer', setTimeout( run, 220 ) ); }
		}
		$wrap.on( 'mousedown', '.ecst-picker-box', function( e ) {
			if ( $( e.target ).closest( '.ecst-chip-x' ).length || $( e.target ).is( '.ecst-picker-search' ) ) { return; }
			e.preventDefault();
			$( this ).find( '.ecst-picker-search' ).trigger( 'focus' );
			pickerOpen( $( this ).closest( '.ecst-picker' ), true );
		} );
		$wrap.on( 'click', '.ecst-picker-search', function() { pickerOpen( $( this ).closest( '.ecst-picker' ), true ); } );
		$wrap.on( 'input', '.ecst-picker-search', function() {
			var $pk = $( this ).closest( '.ecst-picker' );
			pickerOpen( $pk, true );
			if ( isRemote( $pk ) ) { rpSearch( $pk ); } else { pickerFilter( $pk ); }
		} );
		$wrap.on( 'keydown', '.ecst-picker-search', function( e ) {
			var $pk = $( this ).closest( '.ecst-picker' ), $vis = $pk.find( '.ecst-picker-opt' ).not( '[hidden]' ), $act = $vis.filter( '.is-active' ), i = $vis.index( $act );
			if ( e.key === 'ArrowDown' || e.key === 'ArrowUp' ) {
				e.preventDefault();
				pickerOpen( $pk, true );
				$vis = $pk.find( '.ecst-picker-opt' ).not( '[hidden]' );
				if ( ! $vis.length ) { return; }
				i = ( i === -1 ) ? ( e.key === 'ArrowDown' ? 0 : $vis.length - 1 ) : Math.max( 0, Math.min( $vis.length - 1, i + ( e.key === 'ArrowDown' ? 1 : -1 ) ) );
				pickerActive( $pk, $vis.eq( i ) );
			} else if ( e.key === 'Enter' ) {
				e.preventDefault();
				if ( $act.length && ! $pk.find( '.ecst-picker-menu' ).prop( 'hidden' ) ) { if ( isRemote( $pk ) ) { rpToggle( $pk, $act ); } else { pickerToggle( $pk, $act ); } }
			} else if ( e.key === 'Escape' ) {
				if ( ! $pk.find( '.ecst-picker-menu' ).prop( 'hidden' ) ) { e.preventDefault(); e.stopPropagation(); pickerOpen( $pk, false ); }
			} else if ( e.key === 'Tab' ) {
				pickerOpen( $pk, false );
			} else if ( e.key === 'Backspace' && this.value === '' ) {
				var $last = $pk.find( '.ecst-chip' ).last();
				if ( ! $last.length ) { return; }
				e.preventDefault();
				if ( isRemote( $pk ) ) { rpRemove( $pk, $last.attr( 'data-value' ) ); return; }
				pickerToggle( $pk, $pk.find( '.ecst-picker-opt' ).filter( function() { return $( this ).attr( 'data-value' ) === $last.attr( 'data-value' ); } ), false );
			}
		} );
		$wrap.on( 'mousedown', '.ecst-picker-opt', function( e ) {
			e.preventDefault(); // keep focus in the search box so several picks can be made in a row
			var $pk = $( this ).closest( '.ecst-picker' );
			pickerActive( $pk, $( this ) );
			if ( isRemote( $pk ) ) { rpToggle( $pk, $( this ) ); } else { pickerToggle( $pk, $( this ) ); }
		} );
		$wrap.on( 'click', '.ecst-chip-x', function( e ) {
			e.preventDefault();
			var $pk = $( this ).closest( '.ecst-picker' ), val = $( this ).closest( '.ecst-chip' ).attr( 'data-value' );
			if ( isRemote( $pk ) ) { rpRemove( $pk, val ); }
			else { pickerToggle( $pk, $pk.find( '.ecst-picker-opt' ).filter( function() { return $( this ).attr( 'data-value' ) === val; } ), false ); }
			$pk.find( '.ecst-picker-search' ).trigger( 'focus' );
		} );
		$wrap.on( 'click', '.ecst-picker-clear', function() {
			var $pk = $( this ).closest( '.ecst-picker' );
			if ( isRemote( $pk ) ) { rpSet( $pk, [] ); return; }
			$pk.find( '.ecst-picker-opt' ).removeClass( 'is-on' ).attr( 'aria-selected', 'false' );
			var excl = exclusiveOf( $pk );
			if ( excl.length ) { $pk.find( '.ecst-picker-opt' ).filter( function() { return String( $( this ).attr( 'data-value' ) ) === excl[0]; } ).addClass( 'is-on' ).attr( 'aria-selected', 'true' ); }
			pickerChanged( $pk );
		} );
		$( document ).on( 'mousedown', function( e ) {
			$wrap.find( '.ecst-picker.is-open' ).each( function() { if ( ! $.contains( this, e.target ) ) { pickerOpen( $( this ), false ); } } );
		} );

		/* pairs ( key=value list editor ): rows are the UI, the hidden input holds the joined string */
		function pairsSync( $pr ) {
			var sep = String( $pr.attr( 'data-sep' ) || ',' ), join = String( $pr.attr( 'data-join' ) || '=' ), out = [];
			$pr.find( '.ecst-pairs-list .ecst-pair' ).each( function() {
				var k = String( $( this ).find( '.ecst-pair-k' ).val() || '' ).trim(), v = String( $( this ).find( '.ecst-pair-v' ).val() || '' ).trim();
				if ( k !== '' || v !== '' ) { out.push( k + join + v ); }
			} );
			$pr.find( '.ecst-input' ).val( out.join( sep ) );
		}
		function pairsAdd( $pr, k, v ) {
			var $li = $( $pr.find( '.ecst-pair-tpl' ).html() );
			$li.find( '.ecst-pair-k' ).val( k || '' );
			$li.find( '.ecst-pair-v' ).val( v || '' );
			$pr.find( '.ecst-pairs-list' ).append( $li );
			return $li;
		}
		function pairsLoad( $pr, value ) {
			var sep = String( $pr.attr( 'data-sep' ) || ',' ), join = String( $pr.attr( 'data-join' ) || '=' );
			$pr.find( '.ecst-pairs-list' ).empty();
			String( value || '' ).split( sep ).forEach( function( entry ) {
				entry = entry.trim();
				if ( entry === '' ) { return; }
				var at = entry.indexOf( join );
				pairsAdd( $pr, at === -1 ? entry : entry.slice( 0, at ), at === -1 ? '' : entry.slice( at + join.length ) );
			} );
			$pr.find( '.ecst-input' ).val( value );
		}
		$wrap.on( 'input', '.ecst-pair-k, .ecst-pair-v', function() {
			if ( this.getAttribute( 'data-upper' ) ) { var p = this.selectionStart; this.value = this.value.toUpperCase(); try { this.setSelectionRange( p, p ); } catch ( err ) {} }
			var $pr = $( this ).closest( '.ecst-pairs' );
			pairsSync( $pr );
			markDirty( $pr.closest( '.ecst-row' ).data( 'key' ) );
		} );
		$wrap.on( 'click', '.ecst-pairs-add', function() {
			var $pr = $( this ).closest( '.ecst-pairs' );
			pairsAdd( $pr ).find( '.ecst-pair-k' ).trigger( 'focus' );
		} );
		$wrap.on( 'click', '.ecst-pair-x', function() {
			var $pr = $( this ).closest( '.ecst-pairs' ), $li = $( this ).closest( '.ecst-pair' ), $next = $li.next().length ? $li.next() : $li.prev();
			$li.remove();
			pairsSync( $pr );
			markDirty( $pr.closest( '.ecst-row' ).data( 'key' ) );
			( $next.length ? $next.find( '.ecst-pair-k' ) : $pr.find( '.ecst-pairs-add' ) ).trigger( 'focus' );
		} );
		$wrap.on( 'keydown', '.ecst-pair-k, .ecst-pair-v', function( e ) {
			if ( e.key !== 'Enter' ) { return; }
			e.preventDefault();
			var $pr = $( this ).closest( '.ecst-pairs' ), $li = $( this ).closest( '.ecst-pair' );
			if ( $( this ).hasClass( 'ecst-pair-k' ) ) { $li.find( '.ecst-pair-v' ).trigger( 'focus' ); return; }
			if ( ! $li.next().length ) { pairsAdd( $pr ).find( '.ecst-pair-k' ).trigger( 'focus' ); } else { $li.next().find( '.ecst-pair-k' ).trigger( 'focus' ); }
		} );
		$wrap.on( 'keydown', '.ecst-row[data-mode="batch"] input.ecst-input', function( e ) {
			if ( e.key === 'Enter' ) { e.preventDefault(); saveBatch(); }
		} );
		$( '#ecst_save' ).on( 'click', saveBatch );
		$( '#ecst_discard' ).on( 'click', discard );
		$( document ).on( 'keydown', function( e ) {
			if ( ( e.ctrlKey || e.metaKey ) && e.key === 's' && Object.keys( dirty ).length ) { e.preventDefault(); saveBatch(); }
		} );
		$( window ).on( 'beforeunload', function() { if ( Object.keys( dirty ).length ) { return T.leave; } } );
		$wrap.on( 'click', '.ecst-state.is-error', function() {
			var key = $( this ).closest( '.ecst-row' ).data( 'key' ), $row = rowOf( key );
			if ( $row.data( 'mode' ) === 'auto' ) { saveNow( key, valueOf( $row ) ); } else { dirty[ key ] = valueOf( $row ); saveBatch(); }
		} );

		/* advanced fold */
		$wrap.on( 'click', '.ecst-fold-btn', function() {
			var $sec = $( this ).closest( '.ecst-section' ), open = ! $sec.hasClass( 'is-adv-open' );
			$sec.toggleClass( 'is-adv-open', open ).find( '.ecst-row.is-advanced' ).toggleClass( 'is-shown', open );
			$( this ).attr( 'aria-expanded', open ? 'true' : 'false' );
			if ( open ) { $( this ).text( T.hide_adv ); }
			$sec.find( '.ecst-fold-names' ).prop( 'hidden', open );
			$sec.find( '.ecst-row.is-advanced' ).each( function() { applyDeps( $( this ).data( 'key' ) ); } );
			refreshFold( $sec );
		} );

		/* actions */
		$wrap.on( 'click', '.ecst-action .ecv2-btn[data-action]', function() {
			var $b = $( this ), run = function() {
				// Inline result under the action text ( stays visible; the toast is a transient extra ).
				var $act = $b.closest( '.ecst-action' ), $res = $act.find( '.ecst-action-result' ), label = $b.text();
				if ( ! $res.length ) { $res = $( '<span class="ecst-action-result" role="status" aria-live="polite"></span>' ).appendTo( $act.find( '.ecst-action-text' ) ); }
				$res.removeClass( 'is-ok is-bad' ).addClass( 'is-busy' ).text( T.working || '' );
				$b.prop( 'disabled', true ).attr( 'aria-busy', 'true' ).addClass( 'is-busy' );
				var done = function( ok, m ) {
					$b.prop( 'disabled', false ).removeAttr( 'aria-busy' ).removeClass( 'is-busy' ).text( label );
					$res.removeClass( 'is-busy' ).addClass( ok ? 'is-ok' : 'is-bad' ).text( m );
					toast( m, ok ? 'success' : 'error' );
				};
				post( 'ecv2_settings_action', { page: page, section: $b.data( 'sec' ), action_id: $b.data( 'action' ) }, function( d ) {
					done( true, d.message || T.saved || 'Done.' );
					/* Rows the action changed ( "Create store page" selects the new page ) are redrawn in place, then the page's problem list. */
					if ( d.fields ) { Object.keys( d.fields ).forEach( function( k ) { applyField( k, d.fields[ k ] ); } ); }
					if ( d.problems ) { applyProblems( d.problems ); }
					if ( d.reload ) { setTimeout( function() { window.location.reload(); }, 700 ); }
				}, function( m ) { done( false, ( m && m !== 'Request failed.' ) ? m : ( T.req_failed || 'Request failed.' ) ); } );
			};
			/* data-confirm-title + data-confirm ( body ) + data-confirm-ok open the V2 dialog with a real title and button. data-confirm
			 * alone used to be the whole dialog title over an empty body and a generic "Confirm"; it now becomes the body, under the
			 * action's own name as the title, with the action's button label on the primary button. */
			var title = $b.data( 'confirm-title' ), body = $b.data( 'confirm' ), okLabel = $b.data( 'confirm-ok' );
			if ( ! title && body ) {
				title = $.trim( $b.closest( '.ecst-action' ).find( '.ecst-action-text > b' ).first().text() ) || String( body );
				if ( title === String( body ) ) { body = ''; }
				okLabel = okLabel || $.trim( $b.text() );
			}
			if ( title ) { confirmBox( String( title ), String( body || '' ), okLabel ? String( okLabel ) : '' ).then( function( ok ) { if ( ok ) { run(); } } ); }
			else { run(); }
		} );

		/* section list: active on scroll + smooth jump */
		var $links = $( '#ecst_secnav a' );
		$links.on( 'click', function( e ) {
			e.preventDefault();
			var $sec = $( '#ecst-sec-' + $.escapeSelector( $( this ).data( 'sec' ) ) );
			if ( $sec.length ) { $sec[0].scrollIntoView( { behavior: 'smooth', block: 'start' } ); }
		} );
		if ( 'IntersectionObserver' in window ) {
			var io = new IntersectionObserver( function( entries ) {
				entries.forEach( function( en ) {
					if ( en.isIntersecting ) { $links.removeClass( 'is-on' ).filter( '[data-sec="' + $( en.target ).data( 'sec' ) + '"]' ).addClass( 'is-on' ); }
				} );
			}, { rootMargin: '-40% 0px -55% 0px' } );
			$wrap.find( '.ecst-section' ).each( function() { io.observe( this ); } );
		}
		$links.first().addClass( 'is-on' );

		/* jump to a setting ( deep link from search, or a search result on this same page )
		 * Scrolls the window itself ( not scrollIntoView ) so the WP admin bar and the sticky shell top bar are
		 * allowed for. On arrival from another page the position is re-applied after load and whenever the page
		 * height changes during the first seconds ( late partials, fonts, images, notices WordPress moves ), until
		 * the user scrolls, clicks or types. Advanced folds around the target are opened first. */
		function stickyOffset() {
			var off = 0, bar = document.getElementById( 'wpadminbar' );
			if ( bar && window.getComputedStyle( bar ).position === 'fixed' ) { off += bar.offsetHeight; }
			$( '.ecsh-topbar' ).each( function() {
				var pos = window.getComputedStyle( this ).position;
				if ( pos === 'sticky' || pos === 'fixed' ) { off += this.offsetHeight; }
			} );
			return off + 12;
		}
		function targetTop( el, block ) {
			var off = stickyOffset(), r = el.getBoundingClientRect(), y = window.pageYOffset || document.documentElement.scrollTop || 0;
			var room = window.innerHeight - off;
			if ( block === 'center' && r.height < room ) { return Math.max( 0, Math.round( y + r.top - off - ( room - r.height ) / 2 ) ); }
			return Math.max( 0, Math.round( y + r.top - off ) );
		}
		function flash( $el ) {
			$el.removeClass( 'is-highlight' );
			void $el[0].offsetWidth; // restart the flash animation
			$el.addClass( 'is-highlight' );
		}
		/* Open whatever hides the row: its advanced fold, an advanced ancestor's fold, a hidden-by-parent state. */
		function reveal( $target ) {
			var $sec = $target.closest( '.ecst-section' ), p = $target.data( 'parent' ), guard = 0;
			$( document ).trigger( 'ecst:reveal', [ $target ] ); // page scripts show a hidden tab or panel first ( shipping carrier tabs )
			if ( $target.hasClass( 'is-advanced' ) && ! $sec.hasClass( 'is-adv-open' ) ) { $sec.find( '.ecst-fold-btn' ).trigger( 'click' ); }
			while ( p && guard++ < 8 ) {
				var $pr = rowOf( p );
				if ( $pr.hasClass( 'is-advanced' ) && ! $pr.closest( '.ecst-section' ).hasClass( 'is-adv-open' ) ) { $pr.closest( '.ecst-section' ).find( '.ecst-fold-btn' ).trigger( 'click' ); }
				p = $pr.data( 'parent' );
			}
			$target.prop( 'hidden', false );
		}
		/* The element to scroll to for a key: its row, or its section when the row cannot be shown. */
		function landingFor( key ) {
			var $target = key ? rowOf( key ) : $();
			if ( ! $target.length ) { return null; }
			reveal( $target );
			if ( ! $target[0].getClientRects().length ) {
				var $sec = $target.closest( '.ecst-section' );
				return $sec.length ? { $el: $sec, block: 'start' } : null;
			}
			return { $el: $target, block: 'center' };
		}
		function jumpTo( key ) {
			var land = landingFor( key );
			if ( ! land ) { return false; }
			flash( land.$el );
			setTimeout( function() { window.scrollTo( { top: targetTop( land.$el[0], land.block ), behavior: 'smooth' } ); }, 60 );
			return true;
		}
		function arrive( land ) {
			var el = land.$el[0], stopped = false, lastTop = -1, until = 0, ro = null;
			var stop = function() {
				stopped = true;
				if ( ro ) { ro.disconnect(); }
				$( window ).off( '.ecstArrive' );
				$( document ).off( '.ecstArrive' );
			};
			var place = function() {
				if ( stopped ) { return; }
				if ( until && Date.now() > until ) { stop(); return; }
				var top = targetTop( el, land.block ), y = window.pageYOffset || document.documentElement.scrollTop || 0;
				if ( top !== lastTop || Math.abs( y - top ) > 2 ) { lastTop = top; window.scrollTo( 0, top ); }
			};
			var settle = function() {
				place();
				window.requestAnimationFrame( function() { window.requestAnimationFrame( function() { place(); flash( land.$el ); } ); } );
				until = Date.now() + 2500;
				[ 150, 400, 900, 1600, 2400 ].forEach( function( ms ) { setTimeout( place, ms ); } );
			};
			if ( 'scrollRestoration' in window.history ) { try { window.history.scrollRestoration = 'manual'; } catch ( e ) {} }
			/* Any deliberate user movement ends the watch. */
			$( window ).on( 'wheel.ecstArrive touchstart.ecstArrive', stop );
			$( document ).on( 'keydown.ecstArrive mousedown.ecstArrive', stop );
			if ( 'ResizeObserver' in window ) {
				ro = new ResizeObserver( function() { place(); } );
				ro.observe( document.body );
				ro.observe( $wrap[0] );
			}
			place();
			if ( document.readyState === 'complete' ) { settle(); } else { $( window ).on( 'load.ecstArrive', settle ); }
		}
		( function() {
			var hash = String( window.location.hash || '' ).replace( /^#/, '' ), land = null, el;
			try { hash = decodeURIComponent( hash ); } catch ( e ) {}
			land = landingFor( String( $wrap.data( 'highlight' ) || '' ) );
			if ( ! land && hash.indexOf( 'ecst-' ) === 0 && ( el = document.getElementById( hash ) ) ) {
				land = { $el: $( el ), block: $( el ).hasClass( 'ecst-section' ) ? 'start' : 'center' };
				if ( land.$el.hasClass( 'ecst-row' ) ) {
					land = landingFor( String( land.$el.data( 'key' ) || '' ) ) || land;
				}
			}
			if ( land ) { arrive( land ); }
		} )();

		window.ecst = {
			upsell: function( feature ) {
				var ctx = $wrap.data( 'upsell' ) || 'default';
				if ( typeof window.ecdv2_upsell === 'function' ) { return window.ecdv2_upsell( { context: ctx, feature: feature || '' } ); }
				if ( typeof window.show_pro_required === 'function' ) { return window.show_pro_required(); }
				toast( T.locked, 'info' );
				return false;
			},
			save: saveBatch,
			discard: discard,
			jump: jumpTo,
			/* Smooth-scroll an element below the admin bar + sticky top bar. */
			scrollTo: function( el, block ) { if ( el ) { window.scrollTo( { top: targetTop( el, block || 'start' ), behavior: 'smooth' } ); } }
		};
	}

	/* ================================================================== */
	/* Settings search ( home, every declared page header, above classic pages ) */
	/* ================================================================== */
	/* Mark the query words in a result label. One pass over the raw text: a word matches only where a word starts
	 * ( "attach" marks "Attach" and "attachment", never the "a" inside "PDF" ), longest word first, one-letter words
	 * are skipped unless they are the whole query, and each piece is escaped as it is emitted, so inserted <mark>
	 * tags are never searched again. */
	function highlight( text, term ) {
		var src = text == null ? '' : String( text ), q = String( term || '' ).trim().toLowerCase();
		var words = [], seen = {}, out = '', plain = '', i = 0, j, w, hit;
		q.split( /\s+/ ).forEach( function( part ) {
			if ( part && ( part.length > 1 || q.length === 1 ) && ! seen[ part ] ) { seen[ part ] = 1; words.push( part ); }
		} );
		if ( ! src || ! words.length ) { return esc( src ); }
		words.sort( function( a, b ) { return b.length - a.length; } );
		var wordChar = /[0-9A-Za-zÀ-ɏͰ-ϿЀ-ӿ]/;
		while ( i < src.length ) {
			hit = '';
			if ( i === 0 || ! wordChar.test( src.charAt( i - 1 ) ) || ! wordChar.test( src.charAt( i ) ) ) {
				for ( j = 0; j < words.length; j++ ) {
					w = words[ j ];
					if ( src.substr( i, w.length ).toLowerCase() === w ) { hit = src.substr( i, w.length ); break; }
				}
			}
			if ( hit ) {
				out += esc( plain ) + '<mark>' + esc( hit ) + '</mark>';
				plain = '';
				i += hit.length;
			} else {
				plain += src.charAt( i );
				i++;
			}
		}
		return out + esc( plain );
	}
	$( '[data-ecst-search]' ).each( function() {
		var $box = $( this ), $in = $box.find( '.ecst-search-input' ), $menu = $box.find( '.ecst-search-menu' ), timer = null, active = -1, lastTerm = '', xhr = null;
		var current = String( $box.attr( 'data-current' ) || '' );
		function open( on ) { $menu.prop( 'hidden', ! on ); $in.attr( 'aria-expanded', on ? 'true' : 'false' ); }
		function render( results, term ) {
			$menu.empty();
			if ( ! results.length ) { $menu.append( $( '<div class="ecst-sr-empty"></div>' ).text( T.no_results || '' ) ); }
			results.forEach( function( r, i ) {
				var path = [ r.page_title, r.section_title ].filter( Boolean ).join( ' › ' );
				var $a = $( '<a class="ecst-sr" role="option"></a>' ).attr( { href: r.url, id: $menu.attr( 'id' ) + '_' + i } )
					.attr( 'data-page', r.page || '' ).attr( 'data-key', r.key || '' ).addClass( 'is-' + ( r.type || 'field' ) ).toggleClass( 'is-locked', !! r.locked )
					.html( '<span class="ecst-sr-text"><span>' + highlight( r.label, term ) + '</span><small>' + highlight( r.desc || '', term ) + '</small></span>' + ( r.value_text ? '<span class="ecv2-chip">' + esc( r.value_text ) + '</span>' : '' ) + '<span class="ecst-sr-path">' + esc( path ) + '</span>' );
				$menu.append( $a );
			} );
			active = -1;
			$in.removeAttr( 'aria-activedescendant' );
			open( true );
		}
		function search() {
			var term = String( $in.val() || '' ).trim();
			if ( term.length < 2 ) { open( false ); $menu.empty(); lastTerm = ''; return; }
			if ( term === lastTerm ) { open( true ); return; }
			lastTerm = term;
			if ( xhr ) { xhr.abort(); }
			xhr = $.get( V.ajax, { action: 'ecv2_settings_search', nonce: V.nonce, term: term } ).done( function( r ) {
				if ( r && r.success && String( $in.val() || '' ).trim() === term ) { render( r.data.results || [], term.toLowerCase() ); }
			} );
		}
		/* A result on the page being viewed scrolls to it instead of reloading. */
		function go( $a ) {
			var key = String( $a.attr( 'data-key' ) || '' ), page = String( $a.attr( 'data-page' ) || '' ), href = String( $a.attr( 'href' ) || '' );
			if ( current && page === current ) {
				if ( key && window.ecst && window.ecst.jump && window.ecst.jump( key ) ) { open( false ); $in.val( '' ); lastTerm = ''; return; }
				var hash = href.indexOf( '#' ) !== -1 ? href.slice( href.indexOf( '#' ) + 1 ) : '';
				if ( hash && document.getElementById( hash ) ) {
					open( false );
					if ( window.ecst && window.ecst.scrollTo ) { window.ecst.scrollTo( document.getElementById( hash ), 'start' ); } else { document.getElementById( hash ).scrollIntoView( { behavior: 'smooth', block: 'start' } ); }
					return;
				}
			}
			window.location.href = href;
		}
		$in.on( 'input', function() { clearTimeout( timer ); timer = setTimeout( search, 180 ); } );
		$in.on( 'keydown', function( e ) {
			var $rows = $menu.find( '.ecst-sr' );
			if ( e.key === 'ArrowDown' ) { e.preventDefault(); if ( $menu.prop( 'hidden' ) && $rows.length ) { open( true ); } active = Math.min( active + 1, $rows.length - 1 ); }
			else if ( e.key === 'ArrowUp' ) { e.preventDefault(); active = Math.max( active - 1, 0 ); }
			else if ( e.key === 'Enter' ) { e.preventDefault(); var $go = active >= 0 ? $rows.eq( active ) : $rows.first(); if ( $go.length ) { go( $go ); } return; }
			else if ( e.key === 'Escape' ) { if ( ! $menu.prop( 'hidden' ) ) { e.stopPropagation(); open( false ); } else { $in.val( '' ).trigger( 'blur' ); } return; }
			else { return; }
			$rows.removeClass( 'is-active' ).eq( active ).addClass( 'is-active' );
			if ( $rows.eq( active ).length ) { $in.attr( 'aria-activedescendant', $rows.eq( active ).attr( 'id' ) ); $rows.eq( active )[0].scrollIntoView( { block: 'nearest' } ); }
		} );
		$menu.on( 'click', '.ecst-sr', function( e ) { if ( e.ctrlKey || e.metaKey || e.shiftKey ) { return; } e.preventDefault(); go( $( this ) ); } );
		$( document ).on( 'mousedown', function( e ) { if ( ! $.contains( $box[0], e.target ) ) { open( false ); } } );
		$in.on( 'focus', function() { if ( $menu.children().length && String( $in.val() || '' ).trim().length >= 2 ) { open( true ); } } );
	} );
	/* "/" focuses the first search box on the screen. */
	$( document ).on( 'keydown', function( e ) {
		if ( e.key !== '/' || $( e.target ).is( 'input, textarea, select, [contenteditable]' ) ) { return; }
		var $first = $( '[data-ecst-search] .ecst-search-input' ).filter( ':visible' ).first();
		if ( $first.length ) { e.preventDefault(); $first.trigger( 'focus' ); }
	} );
} )( jQuery );
