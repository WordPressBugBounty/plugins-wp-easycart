/* WP EasyCart Admin — Settings › Language ( V2 ): installed languages + storefront phrase editor.
   Runs after settings-page-v2.js. Adds the phrase search ( section box and the page filter box
   feed the same index ), area pills, "changed only", save-on-blur, per-phrase reset, per-area
   reset, and language add / remove / switch. */
( function( $ ) {
	'use strict';

	var $wrap = $( '#ecst' );
	if ( ! $wrap.length || $wrap.data( 'page' ) !== 'language-editor' ) { return; }

	var V = window.ecl_vars || { ajax: window.ajaxurl, nonce: '', i18n: {} };
	var T = V.i18n || {};
	var area = '';
	var changedOnly = false;
	var q = '';        // current phrase search, lowercased
	var index = [];    // one entry per row: { el, $el, s ( lowercase haystack ), area, title, key, titleEl, keyEl, marked }
	var groups = [];   // { el, $el, area, note, rows: [ entries ] }

	function toast( msg, kind ) {
		if ( typeof window.ecv2_toast === 'function' ) { return window.ecv2_toast( msg, kind || 'success' ); }
		window.alert( msg );
	}
	function confirmBox( title, text ) {
		if ( typeof window.ecv2_show_confirm === 'function' ) { return window.ecv2_show_confirm( title, text ); }
		return Promise.resolve( window.confirm( title + ( text ? '\n\n' + text : '' ) ) );
	}
	function post( action, data, ok, fail ) {
		data = $.extend( { action: action, nonce: V.nonce }, data );
		return $.post( V.ajax, data ).done( function( r ) {
			if ( r && r.success ) { ok( r.data || {} ); } else { fail( ( r && r.data && r.data.message ) ? r.data.message : ( T.error || 'Request failed.' ) ); }
		} ).fail( function() { fail( T.error || 'Request failed.' ); } );
	}
	function fmt( s, a, b ) { return String( s ).replace( '%1$d', a ).replace( '%2$d', b ).replace( '%d', a ).replace( '%s', a ); }
	function esc( s ) { return String( s == null ? '' : s ).replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' ); }
	function ecl() { return $( '#ecl' ); }
	function lang() { return String( ecl().data( 'language' ) || '' ); }

	/* ================================================================== */
	/* Index: built once per render, one lowercase string per row          */
	/* ================================================================== */
	function buildIndex() {
		index = [];
		groups = [];
		var $e = ecl();
		if ( ! $e.length ) { return; }
		$e.find( '.ecl-group' ).each( function() {
			var g = { el: this, $el: $( this ), area: String( $( this ).data( 'area' ) || '' ), note: $( this ).hasClass( 'ecl-group-note' ), rows: [] };
			$( this ).find( '.ecl-row' ).each( function() {
				var titleEl = this.querySelector( '.ecl-title' ), keyEl = this.querySelector( '.ecl-row-text .ecl-key' ), input = this.querySelector( '.ecl-input' );
				var entry = {
					el: this, $el: $( this ), area: g.area,
					s: String( this.getAttribute( 'data-search' ) || '' ),
					title: titleEl ? titleEl.textContent : '', titleEl: titleEl,
					key: keyEl ? keyEl.textContent : '', keyEl: keyEl,
					marked: false
				};
				if ( input ) { $( input ).data( 'initial', String( input.value ) ); }
				g.rows.push( entry );
				index.push( entry );
			} );
			groups.push( g );
		} );
	}

	/* ================================================================== */
	/* Visibility: search ( all areas ) × area pill × changed-only        */
	/* ================================================================== */
	function matches( s, words ) {
		for ( var i = 0; i < words.length; i++ ) {
			if ( s.indexOf( words[ i ] ) === -1 ) { return false; }
		}
		return true;
	}
	function markText( text, words ) {
		var lower = text.toLowerCase(), best = -1, len = 0;
		for ( var i = 0; i < words.length; i++ ) {
			var at = lower.indexOf( words[ i ] );
			if ( at !== -1 && ( best === -1 || at < best ) ) { best = at; len = words[ i ].length; }
		}
		if ( best === -1 ) { return esc( text ); }
		return esc( text.slice( 0, best ) ) + '<mark>' + esc( text.slice( best, best + len ) ) + '</mark>' + esc( text.slice( best + len ) );
	}
	function refresh() {
		var $e = ecl();
		if ( ! $e.length ) { return; }
		var words = q ? q.split( /\s+/ ).filter( Boolean ) : [];
		var searching = words.length > 0;
		var shown = 0, total = index.length, changed = 0, i, r;
		$e.toggleClass( 'is-searching', searching );
		for ( i = 0; i < total; i++ ) {
			r = index[ i ];
			var isChanged = r.el.getAttribute( 'data-changed' ) === '1';
			if ( isChanged ) { changed++; }
			var hit = ! searching || matches( r.s, words );
			var hide = ! hit || ( ! searching && area && r.area !== area ) || ( changedOnly && ! isChanged );
			r.el.classList.toggle( 'is-ecl-hidden', hide );
			if ( ! hide ) { shown++; }
			if ( searching && hit ) {
				if ( r.titleEl ) { r.titleEl.innerHTML = markText( r.title, words ); }
				if ( r.keyEl ) { r.keyEl.innerHTML = markText( r.key, words ); }
				r.marked = true;
			} else if ( r.marked ) {
				if ( r.titleEl ) { r.titleEl.textContent = r.title; }
				if ( r.keyEl ) { r.keyEl.textContent = r.key; }
				r.marked = false;
			}
		}
		for ( i = 0; i < groups.length; i++ ) {
			var g = groups[ i ], n = 0, c = 0, k;
			for ( k = 0; k < g.rows.length; k++ ) {
				if ( ! g.rows[ k ].el.classList.contains( 'is-ecl-hidden' ) ) { n++; }
				if ( g.rows[ k ].el.getAttribute( 'data-changed' ) === '1' ) { c++; }
			}
			var hide = g.note ? ( searching || changedOnly || ( area && g.area !== area ) ) : ( n === 0 || ( ! searching && area && g.area !== area ) );
			g.el.classList.toggle( 'is-ecl-hidden', hide );
			g.$el.find( '.ecl-group-changed' ).prop( 'hidden', c === 0 ).text( ' · ' + fmt( T.changed_n, c ) );
			g.$el.find( '.ecl-reset-area' ).prop( 'hidden', c === 0 );
			$e.find( '.ecl-pill[data-area="' + g.area + '"]' ).toggleClass( 'has-changed', c > 0 );
		}
		$( '#ecl_changed_count' ).text( fmt( T.changed_n, changed ) );
		$( '#ecl_count' ).text( ( shown === total && ! searching ) ? '' : fmt( T.shown_n, shown, total ) );
		$( '#ecl_empty' ).prop( 'hidden', shown > 0 || total === 0 );
	}
	function search( value ) {
		q = String( value || '' ).trim().toLowerCase();
		refresh();
	}

	function autosize( el ) {
		el.style.height = 'auto';
		el.style.height = ( el.scrollHeight + 2 ) + 'px';
	}

	function setState( $row, kind, text ) {
		var $s = $row.find( '.ecl-state' );
		$row.removeClass( 'is-saving is-error' );
		$s.removeClass( 'is-saving is-saved is-error' ).text( text || '' );
		if ( kind ) { $s.addClass( 'is-' + kind ); }
		if ( kind === 'saving' || kind === 'error' ) { $row.addClass( 'is-' + kind ); }
		if ( kind === 'saved' ) { setTimeout( function() { if ( $s.hasClass( 'is-saved' ) ) { $s.removeClass( 'is-saved' ).text( '' ); } }, 2200 ); }
	}

	function entryOf( $row ) {
		var el = $row[0];
		for ( var i = 0; i < index.length; i++ ) { if ( index[ i ].el === el ) { return index[ i ]; } }
		return null;
	}

	/** Apply a server answer ( value + changed ) to one row and refresh its index entry. */
	function applyRow( $row, value, changed ) {
		var $in = $row.find( '.ecl-input' );
		$in.val( value ).data( 'initial', value );
		$row.attr( 'data-changed', changed ? '1' : '0' );
		$row.find( '.ecl-chip, .ecl-reset' ).prop( 'hidden', ! changed );
		$row.find( '.ecl-default' ).prop( 'hidden', ! changed );
		var search = String( $row.attr( 'data-sbase' ) || '' ) + ' ' + String( value ).toLowerCase();
		$row.attr( 'data-search', search ).data( 'search', search );
		var entry = entryOf( $row );
		if ( entry ) { entry.s = search; }
		autosize( $in[0] );
	}

	/* ================================================================== */
	/* Save on blur / reset                                                */
	/* ================================================================== */
	function savePhrase( $row ) {
		var $in = $row.find( '.ecl-input' ), value = String( $in.val() );
		if ( value === String( $in.data( 'initial' ) ) ) { return; }
		setState( $row, 'saving', T.saving );
		post( 'ecv2_language_save_phrase', { language: lang(), group: $row.data( 'area' ), key: $row.data( 'phrase' ), value: value }, function( d ) {
			if ( String( $in.val() ) !== value ) { return; } // typed again meanwhile: the next blur saves it
			applyRow( $row, d.value, !! d.changed );
			setState( $row, 'saved', T.saved );
			refresh();
		}, function( m ) {
			setState( $row, 'error', T.save_failed );
			toast( m, 'error' );
		} );
	}

	function resetPhrase( $row ) {
		confirmBox( T.reset_title, T.reset_text ).then( function( ok ) {
			if ( ! ok ) { return; }
			setState( $row, 'saving', T.saving );
			post( 'ecv2_language_reset_phrase', { language: lang(), group: $row.data( 'area' ), key: $row.data( 'phrase' ) }, function( d ) {
				applyRow( $row, d.value, false );
				setState( $row, 'saved', T.saved );
				toast( d.message || T.reset_done, 'success' );
				refresh();
			}, function( m ) { setState( $row, 'error', T.save_failed ); toast( m, 'error' ); } );
		} );
	}

	function resetArea( $btn ) {
		var g = $btn.data( 'area' ), $group = $btn.closest( '.ecl-group' ), n = $group.find( '.ecl-row[data-changed="1"]' ).length;
		if ( ! n ) { toast( T.area_none, 'info' ); return; }
		confirmBox( fmt( T.reset_area, $btn.data( 'label' ) ), fmt( T.reset_area_tx, n ) ).then( function( ok ) {
			if ( ! ok ) { return; }
			$btn.prop( 'disabled', true );
			post( 'ecv2_language_reset_area', { language: lang(), group: g }, function( d ) {
				$btn.prop( 'disabled', false );
				var values = d.values || {};
				Object.keys( values ).forEach( function( key ) {
					var $row = $group.find( '.ecl-row[data-phrase="' + key + '"]' );
					if ( $row.length ) { applyRow( $row, values[ key ], false ); }
				} );
				toast( d.message || fmt( T.area_done, Object.keys( values ).length ), 'success' );
				refresh();
			}, function( m ) { $btn.prop( 'disabled', false ); toast( m, 'error' ); } );
		} );
	}

	/* ================================================================== */
	/* Switch the language being edited ( re-render from the server )      */
	/* ================================================================== */
	function loadEditor( file ) {
		var $e = ecl();
		$e.addClass( 'is-loading' );
		post( 'ecv2_language_editor', { language: file }, function( d ) {
			var $new = $( d.html );
			$e.replaceWith( $new );
			area = '';
			changedOnly = false;
			buildIndex();
			$( '#ecl_search' ).val( q );
			refresh();
		}, function( m ) { $e.removeClass( 'is-loading' ); toast( m, 'error' ); } );
	}

	/* ================================================================== */
	/* Wiring ( delegated: the editor is replaced on language switch )     */
	/* ================================================================== */
	$wrap.on( 'focus', '.ecl-input', function() { autosize( this ); } );
	$wrap.on( 'input', '.ecl-input', function() { autosize( this ); } );
	$wrap.on( 'change', '.ecl-input', function() { savePhrase( $( this ).closest( '.ecl-row' ) ); } );
	$wrap.on( 'keydown', '.ecl-input', function( e ) {
		if ( ( e.ctrlKey || e.metaKey ) && e.key === 'Enter' ) { e.preventDefault(); this.blur(); }
		else if ( e.key === 'Escape' ) { $( this ).val( String( $( this ).data( 'initial' ) ) ); autosize( this ); this.blur(); }
	} );
	$wrap.on( 'click', '.ecl-state.is-error', function() { savePhrase( $( this ).closest( '.ecl-row' ) ); } );
	$wrap.on( 'click', '.ecl-reset', function() { resetPhrase( $( this ).closest( '.ecl-row' ) ); } );
	$wrap.on( 'click', '.ecl-reset-area', function() { resetArea( $( this ) ); } );
	$wrap.on( 'click', '.ecl-pill', function() {
		area = String( $( this ).data( 'area' ) || '' );
		$( this ).addClass( 'is-on' ).siblings().removeClass( 'is-on' );
		if ( q ) { q = ''; $( '#ecl_search' ).val( '' ); } // picking an area leaves search mode
		refresh();
	} );
	$wrap.on( 'change', '#ecl_changed_only', function() { changedOnly = this.checked; refresh(); } );
	$wrap.on( 'change', '#ecl_lang', function() { loadEditor( String( $( this ).val() ) ); } );
	$wrap.on( 'input search', '#ecl_search', function() { search( this.value ); } );
	$wrap.on( 'keydown', '#ecl_search', function( e ) { if ( e.key === 'Escape' ) { this.value = ''; search( '' ); } } );
	/* The page filter box performs the same phrase search ( its own handler also toggles is-filtered-out on these rows; the index ignores that class ). */
	$( '#ecst_filter' ).on( 'input', function() { $( '#ecl_search' ).val( this.value ); search( this.value ); } );

	/* languages block */
	$wrap.on( 'click', '#ecl_add_btn', function() {
		var $b = $( this ), file = String( $( '#ecl_add_select' ).val() || '' );
		if ( ! file ) { return; }
		$b.prop( 'disabled', true );
		post( 'ecv2_language_add', { language: file }, function( d ) {
			toast( d.message || '', 'success' );
			setTimeout( function() { window.location.reload(); }, 600 );
		}, function( m ) { $b.prop( 'disabled', false ); toast( m, 'error' ); } );
	} );
	$wrap.on( 'click', '.ecl-remove', function() {
		var $b = $( this );
		if ( $b.prop( 'disabled' ) ) { return; }
		confirmBox( fmt( T.remove_title, $b.data( 'name' ) ), T.remove_text ).then( function( ok ) {
			if ( ! ok ) { return; }
			$b.prop( 'disabled', true );
			post( 'ecv2_language_remove', { language: $b.data( 'file' ) }, function( d ) {
				toast( d.message || '', 'success' );
				setTimeout( function() { window.location.reload(); }, 600 );
			}, function( m ) { $b.prop( 'disabled', false ); toast( m, 'error' ); } );
		} );
	} );

	/* The engine autosaves the storefront language select; once that save lands, the
	   installed list, the editor's storefront chip and the receipt-phrases note are stale — reload. */
	$( document ).ajaxSuccess( function( ev, xhr, settings, data ) {
		if ( ! settings || typeof settings.data !== 'string' ) { return; }
		if ( settings.data.indexOf( 'action=ecv2_settings_save' ) === -1 || settings.data.indexOf( 'ec_option_language' ) === -1 ) { return; }
		if ( ! data || ! data.success || ( data.data && data.data.errors && data.data.errors.ec_option_language ) ) { return; }
		toast( T.reloading, 'info' );
		setTimeout( function() { window.location.reload(); }, 500 );
	} );

	/* initial state */
	buildIndex();
	refresh();
} )( jQuery );
