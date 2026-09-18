/* WP EasyCart Admin — Settings › Shipping settings ( V2 ): zone editor.
 * Each zone is a card: header ( chevron, name, place count, Delete ) over a body ( place chips + picker ).
 * Zones with more than COLLAPSE_AT places start collapsed to a one-line preview; the chevron button
 * ( aria-expanded / aria-controls ) or a click on the header background toggles a zone. What the merchant
 * opens or closes is remembered per zone in localStorage; a zone that was just added or just received
 * a place stays open for the rest of the visit. */
( function( $ ) {
	'use strict';

	var V = window.ecv2_shipping_vars;
	var E = window.ecst_vars || { ajax: window.ajaxurl, nonce: '' };
	var $root = $( '#ecsh_zones' );
	if ( ! V || ! $root.length ) { return; }

	var T = V.i18n || {};
	var zones = V.zones || [];
	var items = V.items || [];
	var countries = V.countries || [];
	var states = V.states || [];
	var countryByIso = {};
	countries.forEach( function( c ) { countryByIso[ c.iso2 ] = c; } );

	var COLLAPSE_AT = 12;      // more places than this: collapsed by default
	var PREVIEW_COUNT = 3;     // place names listed in a collapsed zone's preview
	var STORE_KEY = 'wpeasycart_ship_zone_open';

	function esc( s ) { return $( '<i>' ).text( s == null ? '' : String( s ) ).html(); }
	function fmt( s, n ) { return String( s ).replace( '%d', n ); }
	function fmtS( s, v ) { return String( s ).replace( '%s', v ); }
	function toast( msg, kind ) {
		if ( typeof window.ecv2_toast === 'function' ) { return window.ecv2_toast( msg, kind || 'success' ); }
		window.alert( msg );
	}
	function confirmBox( title, text ) {
		if ( typeof window.ecv2_show_confirm === 'function' ) { return window.ecv2_show_confirm( title, text ); }
		return Promise.resolve( window.confirm( title + ( text ? '\n\n' + text : '' ) ) );
	}
	function post( action, data, ok, fail ) {
		data = $.extend( { action: action, nonce: E.nonce }, data );
		return $.post( E.ajax, data ).done( function( r ) {
			if ( r && r.success ) { ok( r.data || {} ); } else { fail( ( r && r.data && r.data.message ) ? r.data.message : T.failed ); }
		} ).fail( function() { fail( T.failed ); } );
	}
	function itemsOf( zoneId ) { return items.filter( function( i ) { return i.zone_id === zoneId; } ); }
	function statesOf( iso2 ) {
		var c = countryByIso[ iso2 ];
		if ( ! c ) { return []; }
		return states.filter( function( s ) { return s.cnt === c.id; } );
	}

	/* ---- expanded state ---------------------------------------------- */
	// stored: zone id => 1 ( open ) / 0 ( closed ), only for zones the merchant toggled.
	var stored = {};
	try {
		var raw = window.localStorage ? window.localStorage.getItem( STORE_KEY ) : null;
		var parsed = raw ? JSON.parse( raw ) : null;
		if ( parsed && typeof parsed === 'object' ) { stored = parsed; }
	} catch ( e ) { stored = {}; }
	var session = {}; // zone id => true: kept open this visit ( just added / just received a place )

	function persist() {
		try {
			if ( window.localStorage ) { window.localStorage.setItem( STORE_KEY, JSON.stringify( stored ) ); }
		} catch ( e ) { /* storage blocked or full: the state is simply not remembered */ }
	}
	function isOpen( zoneId ) {
		if ( session[ zoneId ] ) { return true; }
		if ( Object.prototype.hasOwnProperty.call( stored, zoneId ) ) { return !! stored[ zoneId ]; }
		return itemsOf( zoneId ).length <= COLLAPSE_AT;
	}
	function remember( zoneId, open ) {
		delete session[ zoneId ];
		stored[ zoneId ] = open ? 1 : 0;
	}

	/* ---- render ---------------------------------------------------- */
	var $list = $( '#ecsh_zone_list' ), $empty = $( '#ecsh_zone_empty' ), $tools = $( '#ecsh_zone_tools' );

	function countryOptions() {
		var h = '<option value="">' + esc( T.all_countries ) + '</option>';
		countries.forEach( function( c ) { h += '<option value="' + esc( c.iso2 ) + '">' + esc( c.name ) + '</option>'; } );
		return h;
	}
	var COUNTRY_OPTIONS = countryOptions();

	function chipHtml( it ) {
		return '<span class="ecsh-chip" data-id="' + it.id + '"><span class="ecsh-chip-text">' + esc( it.label ) + '</span><button type="button" class="ecsh-chip-rm" title="' + esc( T.remove ) + '" aria-label="' + esc( T.remove ) + '">×</button></span>';
	}
	function countText( n ) { return n === 1 ? T.places_one : fmt( T.places_many, n ); }
	function summaryText( its ) {
		if ( ! its.length ) { return T.no_places_yet || T.no_places || ''; }
		var names = its.slice( 0, PREVIEW_COUNT ).map( function( i ) { return i.label; } ).join( ', ' );
		var rest = its.length - PREVIEW_COUNT;
		return rest > 0 ? names + ' ' + fmt( T.more || '+%d more', rest ) : names;
	}
	function toggleLabel( name, open ) {
		return fmtS( open ? ( T.hide_places || '%s' ) : ( T.show_places || '%s' ), name );
	}

	function zoneHtml( z ) {
		var its = itemsOf( z.id ), open = isOpen( z.id ), bodyId = 'ecsh_zone_body_' + z.id;
		var h = '<div class="ecsh-zone' + ( open ? ' is-open' : ' is-collapsed' ) + '" data-id="' + z.id + '">';
		h += '<div class="ecsh-zone-head">';
		h += '<button type="button" class="ecsh-zone-toggle" aria-expanded="' + ( open ? 'true' : 'false' ) + '" aria-controls="' + bodyId + '" aria-label="' + esc( toggleLabel( z.name, open ) ) + '"><svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true" focusable="false"><path d="M6 3.5 10.5 8 6 12.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></button>';
		h += '<input type="text" class="ecsh-zone-name" value="' + esc( z.name ) + '" title="' + esc( T.rename_hint ) + '" data-orig="' + esc( z.name ) + '" />';
		h += '<span class="ecsh-zone-count">' + esc( countText( its.length ) ) + '</span>';
		h += '<span class="ecsh-zone-state"></span>';
		h += '<span class="ecst-grow"></span>';
		h += '<button type="button" class="ecst-link ecsh-zone-del">' + esc( T.delete ) + '</button>';
		h += '<div class="ecsh-zone-summary"' + ( open ? ' hidden' : '' ) + '>' + esc( summaryText( its ) ) + '</div>';
		h += '</div>';
		h += '<div class="ecsh-zone-body" id="' + bodyId + '"' + ( open ? '' : ' hidden' ) + '>';
		h += '<div class="ecsh-chips">' + ( its.length ? its.map( chipHtml ).join( '' ) : '<span class="ecsh-chips-empty">' + esc( T.no_places ) + '</span>' ) + '</div>';
		h += '<div class="ecsh-picker">';
		h += '<span class="ecsh-picker-label">' + esc( T.add_place ) + '</span>';
		h += '<select class="ecv2-select ecsh-pick-country" aria-label="' + esc( T.add_place ) + '">' + COUNTRY_OPTIONS + '</select>';
		h += '<select class="ecv2-select ecsh-pick-state" hidden><option value="">' + esc( T.whole_country ) + '</option></select>';
		h += '<button type="button" class="ecv2-btn ecv2-btn-sm ecsh-item-add">' + esc( T.add ) + '</button>';
		h += '<span class="ecsh-picker-msg" hidden></span>';
		h += '</div>';
		h += '</div>';
		h += '</div>';
		return h;
	}

	function render() {
		$list.html( zones.map( zoneHtml ).join( '' ) );
		$empty.prop( 'hidden', zones.length > 0 );
		$tools.prop( 'hidden', zones.length === 0 );
	}
	function refreshChips( zoneId ) {
		var $z = $list.find( '.ecsh-zone[data-id="' + zoneId + '"]' ), its = itemsOf( zoneId );
		$z.find( '.ecsh-chips' ).html( its.length ? its.map( chipHtml ).join( '' ) : '<span class="ecsh-chips-empty">' + esc( T.no_places ) + '</span>' );
		$z.find( '.ecsh-zone-count' ).text( countText( its.length ) );
		$z.find( '.ecsh-zone-summary' ).text( summaryText( its ) );
	}
	function setOpen( $z, open ) {
		var name = $.trim( String( $z.find( '.ecsh-zone-name' ).val() || '' ) );
		$z.toggleClass( 'is-open', open ).toggleClass( 'is-collapsed', ! open );
		$z.find( '.ecsh-zone-toggle' ).attr( 'aria-expanded', open ? 'true' : 'false' ).attr( 'aria-label', toggleLabel( name, open ) );
		$z.find( '.ecsh-zone-body' ).prop( 'hidden', ! open );
		$z.find( '.ecsh-zone-summary' ).prop( 'hidden', open );
	}
	function flash( $el, text, isError ) {
		$el.text( text || '' ).toggleClass( 'is-error', !! isError ).toggleClass( 'is-saved', ! isError ).prop( 'hidden', ! text );
		if ( text && ! isError ) { setTimeout( function() { $el.prop( 'hidden', true ).text( '' ); }, 1800 ); }
	}
	render();

	/* ---- expand / collapse ------------------------------------------- */
	function toggleZone( $z ) {
		var id = parseInt( $z.data( 'id' ), 10 ), open = ! $z.hasClass( 'is-open' );
		setOpen( $z, open );
		remember( id, open );
		persist();
	}
	$list.on( 'click', '.ecsh-zone-toggle', function() { toggleZone( $( this ).closest( '.ecsh-zone' ) ); } );
	// the header background ( and the collapsed preview in it ) toggles too; its inputs and buttons keep their own jobs
	$list.on( 'click', '.ecsh-zone-head', function( e ) {
		if ( $( e.target ).closest( 'input, button, select, textarea, a, label' ).length ) { return; }
		if ( window.getSelection && String( window.getSelection() ) !== '' ) { return; } // the merchant is selecting preview text
		toggleZone( $( this ).closest( '.ecsh-zone' ) );
	} );
	$tools.on( 'click', '.ecsh-zone-all', function() {
		var open = String( $( this ).data( 'open' ) ) === '1';
		$list.find( '.ecsh-zone' ).each( function() {
			var $z = $( this );
			setOpen( $z, open );
			remember( parseInt( $z.data( 'id' ), 10 ), open );
		} );
		persist();
	} );

	/* ---- add zone ---------------------------------------------------- */
	var $newName = $( '#ecsh_zone_name' ), $addMsg = $( '#ecsh_zone_add_msg' );
	function addZone() {
		var name = $.trim( $newName.val() );
		if ( ! name ) { flash( $addMsg, T.name_required, true ); $newName.focus(); return; }
		$( '#ecsh_zone_add' ).prop( 'disabled', true );
		post( 'ecv2_shipping_zone_add', { zone_name: name }, function( d ) {
			$( '#ecsh_zone_add' ).prop( 'disabled', false );
			var id = parseInt( d.zone.id, 10 );
			zones.push( { id: id, name: d.zone.name } );
			zones.sort( function( a, b ) { return a.name.localeCompare( b.name ); } );
			session[ id ] = true;
			$newName.val( '' );
			render();
			flash( $addMsg, T.saved, false );
		}, function( m ) { $( '#ecsh_zone_add' ).prop( 'disabled', false ); flash( $addMsg, m, true ); } );
	}
	$( '#ecsh_zone_add' ).on( 'click', addZone );
	$newName.on( 'keydown', function( e ) { if ( e.key === 'Enter' ) { e.preventDefault(); addZone(); } } );

	/* ---- rename ------------------------------------------------------ */
	function renameZone( $in ) {
		var $z = $in.closest( '.ecsh-zone' ), id = parseInt( $z.data( 'id' ), 10 ), name = $.trim( $in.val() ), orig = $in.data( 'orig' );
		if ( name === orig ) { return; }
		if ( ! name ) { $in.val( orig ); return; }
		var $st = $z.find( '.ecsh-zone-state' );
		post( 'ecv2_shipping_zone_rename', { id: id, zone_name: name }, function( d ) {
			$in.data( 'orig', d.zone.name ).val( d.zone.name );
			zones.forEach( function( z ) { if ( z.id === id ) { z.name = d.zone.name; } } );
			$z.find( '.ecsh-zone-toggle' ).attr( 'aria-label', toggleLabel( d.zone.name, $z.hasClass( 'is-open' ) ) );
			flash( $st, T.saved, false );
		}, function( m ) { $in.val( orig ); flash( $st, m, true ); } );
	}
	$list.on( 'blur', '.ecsh-zone-name', function() { renameZone( $( this ) ); } );
	$list.on( 'keydown', '.ecsh-zone-name', function( e ) {
		if ( e.key === 'Enter' ) { e.preventDefault(); $( this ).blur(); }
		if ( e.key === 'Escape' ) { $( this ).val( $( this ).data( 'orig' ) ).blur(); }
	} );

	/* ---- delete zone ------------------------------------------------- */
	$list.on( 'click', '.ecsh-zone-del', function() {
		var $z = $( this ).closest( '.ecsh-zone' ), id = parseInt( $z.data( 'id' ), 10 ), $st = $z.find( '.ecsh-zone-state' );
		confirmBox( T.confirm_delete, T.confirm_text ).then( function( ok ) {
			if ( ! ok ) { return; }
			post( 'ecv2_shipping_zone_delete', { id: id }, function() {
				zones = zones.filter( function( z ) { return z.id !== id; } );
				items = items.filter( function( i ) { return i.zone_id !== id; } );
				delete session[ id ];
				if ( Object.prototype.hasOwnProperty.call( stored, id ) ) { delete stored[ id ]; persist(); }
				render();
			}, function( m ) { flash( $st, m, true ); } );
		} );
	} );

	/* ---- picker ------------------------------------------------------ */
	$list.on( 'change', '.ecsh-pick-country', function() {
		var $z = $( this ).closest( '.ecsh-zone' ), $st = $z.find( '.ecsh-pick-state' ), iso2 = $( this ).val(), list = statesOf( iso2 );
		var h = '<option value="">' + esc( T.whole_country ) + '</option>';
		list.forEach( function( s ) { h += '<option value="' + s.id + '">' + esc( s.name ) + '</option>'; } );
		$st.html( h ).prop( 'hidden', ! iso2 || ! list.length );
	} );
	$list.on( 'click', '.ecsh-item-add', function() {
		var $b = $( this ), $z = $b.closest( '.ecsh-zone' ), id = parseInt( $z.data( 'id' ), 10 );
		var iso2 = $z.find( '.ecsh-pick-country' ).val() || '', sta = parseInt( $z.find( '.ecsh-pick-state' ).val() || '0', 10 ), $msg = $z.find( '.ecsh-picker-msg' );
		session[ id ] = true; // receiving a place: stays open even once it passes the collapse threshold
		$b.prop( 'disabled', true );
		post( 'ecv2_shipping_zone_item_add', { zone_id: id, iso2_cnt: iso2, id_sta: sta }, function( d ) {
			$b.prop( 'disabled', false );
			items.push( { id: parseInt( d.item.id, 10 ), zone_id: parseInt( d.item.zone_id, 10 ), iso2: d.item.iso2, code: d.item.code, label: d.item.label } );
			refreshChips( id );
			setOpen( $z, true );
			flash( $msg, T.saved, false );
		}, function( m ) { $b.prop( 'disabled', false ); flash( $msg, m, true ); } );
	} );

	/* ---- remove chip ------------------------------------------------- */
	$list.on( 'click', '.ecsh-chip-rm', function() {
		var $chip = $( this ).closest( '.ecsh-chip' ), $z = $chip.closest( '.ecsh-zone' ), zoneId = parseInt( $z.data( 'id' ), 10 ), id = parseInt( $chip.data( 'id' ), 10 ), $msg = $z.find( '.ecsh-picker-msg' );
		$chip.addClass( 'is-busy' );
		post( 'ecv2_shipping_zone_item_delete', { id: id }, function() {
			items = items.filter( function( i ) { return i.id !== id; } );
			refreshChips( zoneId );
		}, function( m ) { $chip.removeClass( 'is-busy' ); flash( $msg, m, true ); toast( m, 'error' ); } );
	} );
} )( jQuery );

/* Carrier accounts: one carrier at a time in tabs ( USPS first; the section rows arrive grouped per carrier, each group
 * opened by its .ecsh-carrier-head row ), and each carrier's status chip + note refreshed from the save reply, so
 * entering the last missing detail shows Connected without a reload. The tab opens on a deep-linked setting, else the
 * merchant's last tab, else the first carrier that is set up, else USPS. */
( function( $ ) {
	'use strict';

	var $sec = $( '#ecst-sec-carriers' );
	var $heads = $sec.find( '.ecsh-carrier-head' );
	if ( ! $heads.length ) { return; }

	var STORE = 'ecsh_carrier_tab';
	var LIVE = [ 'connected', 'error', 'incomplete' ];
	var $rows = $sec.find( '.ecst-row' );
	var carrierOf = {}; // row key -> carrier
	var order = [];
	var current = '';

	$rows.each( function() {
		var $head = $( this ).find( '.ecsh-carrier-head' );
		if ( $head.length ) {
			current = String( $head.data( 'carrier' ) || '' );
			if ( current && order.indexOf( current ) === -1 ) { order.push( current ); }
		}
		if ( current ) {
			$( this ).attr( 'data-carrier', current );
			carrierOf[ String( $( this ).data( 'key' ) || '' ) ] = current;
		}
	} );
	if ( order.length < 2 ) { return; }

	function statusOf( carrier ) {
		var $chip = $( '#ecsh_status_' + carrier );
		var m = $chip.length && ! $chip.prop( 'hidden' ) ? String( $chip.attr( 'class' ) || '' ).match( /\bis-([a-z]+)\b/ ) : null;
		return m && 'none' !== m[1] ? m[1] : '';
	}

	var $tabs = $( '<div class="ecsh-tabs" role="tablist"></div>' ).attr( 'aria-label', $.trim( $sec.find( '.ecdv2-card-title' ).first().clone().children().remove().end().text() ) );
	order.forEach( function( carrier ) {
		var $head = $heads.filter( '[data-carrier="' + carrier + '"]' ).first();
		var $tab = $( '<button type="button" class="ecsh-tab" role="tab" aria-selected="false"></button>' ).attr( { 'data-carrier': carrier, id: 'ecsh_tab_' + carrier } );
		$tab.append( $head.find( '.ecsh-mark' ).clone().addClass( 'ecsh-tab-mark' ) );
		$tab.append( $( '<span class="ecsh-tab-name"></span>' ).text( $.trim( $head.find( '.ecsh-carrier-name' ).text() ) ) );
		$tab.append( '<span class="ecsh-tab-dot" aria-hidden="true"></span>' );
		$tabs.append( $tab );
	} );
	$rows.first().before( $tabs );

	function paintDot( carrier ) {
		var s = statusOf( carrier );
		var $tab = $tabs.find( '[data-carrier="' + carrier + '"]' );
		$tab.find( '.ecsh-tab-dot' ).attr( 'class', 'ecsh-tab-dot' + ( s ? ' is-' + s : '' ) );
		$tab.attr( 'title', s ? $.trim( $( '#ecsh_status_' + carrier ).text() ) : '' );
	}

	function show( carrier, remember ) {
		if ( order.indexOf( carrier ) === -1 ) { return; }
		$rows.each( function() {
			var c = $( this ).attr( 'data-carrier' );
			$( this ).toggleClass( 'ecsh-tab-off', !! c && c !== carrier );
		} );
		$tabs.find( '.ecsh-tab' ).each( function() {
			var on = $( this ).data( 'carrier' ) === carrier;
			$( this ).toggleClass( 'is-on', on ).attr( { 'aria-selected': on ? 'true' : 'false', tabindex: on ? '0' : '-1' } );
		} );
		if ( remember ) { try { window.localStorage.setItem( STORE, carrier ); } catch ( e ) {} }
	}

	/* first tab */
	var start = '', hash = String( window.location.hash || '' ).replace( /^#ecst-/, '' ), saved = '';
	try { hash = decodeURIComponent( hash ); } catch ( e ) {}
	start = carrierOf[ String( $( '#ecst' ).data( 'highlight' ) || '' ) ] || carrierOf[ hash ] || '';
	if ( ! start ) { try { saved = String( window.localStorage.getItem( STORE ) || '' ); } catch ( e ) {} }
	if ( ! start && order.indexOf( saved ) !== -1 ) { start = saved; }
	if ( ! start ) {
		order.some( function( c ) { if ( LIVE.indexOf( statusOf( c ) ) !== -1 ) { start = c; return true; } return false; } );
	}
	order.forEach( paintDot );
	show( start || order[0], false );

	$tabs.on( 'click', '.ecsh-tab', function() { show( String( $( this ).data( 'carrier' ) ), true ); } );
	$tabs.on( 'keydown', '.ecsh-tab', function( e ) {
		var i = order.indexOf( String( $( this ).data( 'carrier' ) ) ), n = -1;
		if ( e.key === 'ArrowRight' ) { n = ( i + 1 ) % order.length; } else if ( e.key === 'ArrowLeft' ) { n = ( i - 1 + order.length ) % order.length; } else if ( e.key === 'Home' ) { n = 0; } else if ( e.key === 'End' ) { n = order.length - 1; }
		if ( n < 0 ) { return; }
		e.preventDefault();
		show( order[ n ], true );
		$tabs.find( '[data-carrier="' + order[ n ] + '"]' ).trigger( 'focus' );
	} );

	/* a search jump or deep link to a carrier setting opens its tab first */
	$( document ).on( 'ecst:reveal', function( e, $target ) {
		var c = $target && $target.length ? $target.attr( 'data-carrier' ) : '';
		if ( c ) { show( c, false ); }
	} );

	/* live status from the save reply ( PRO fills reply.carriers ) */
	$( document ).on( 'ecst:saved', function( e, d ) {
		if ( ! d || ! d.carriers ) { return; }
		$.each( d.carriers, function( carrier, info ) {
			var s = info && info.status ? String( info.status ) : '';
			var $chip = $( '#ecsh_status_' + carrier ), $note = $( '#ecsh_note_' + carrier );
			$chip.attr( 'class', 'ecsh-status is-' + ( s || 'none' ) ).text( info && info.label ? info.label : '' ).prop( 'hidden', ! s );
			$note.attr( 'class', 'ecsh-carrier-note is-' + ( s || 'none' ) ).text( info && info.note ? info.note : '' ).prop( 'hidden', ! ( info && info.note ) );
			paintDot( carrier );
		} );
	} );
} )( jQuery );
