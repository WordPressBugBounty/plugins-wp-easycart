/* WP EasyCart Admin — Settings › Shipping rates ( V2 ).
 * Five rate tables ( flat, by cart total, by weight, by quantity, percentage ), each a list of rows
 * ( wp_easycart_admin_shipping_rates_v2::print_list() / print_row(): .ecsr-rows[data-type] > .ecsr-list > .ecsr-item,
 * the row's values in data-row ). 6.0.2: Edit and Add open one side drawer, built here on <body>, that holds every
 * setting of the row; flat methods are dragged ( or moved with the arrow keys on their handle ) into order.
 * Saves through the ecv2_shipping_rate_* handlers in wp_easycart_admin_shipping_rates_v2.php:
 *   ecv2_shipping_rate_add     ( type, label, rate, trigger, free_at, order, zone_id ) → { id, row, html }
 *   ecv2_shipping_rate_update  ( type, id, …same fields )                             → { id, row, html }
 *   ecv2_shipping_rate_delete  ( type, id )                                           → { count }
 * All take nonce for the settings V2 action ( ecv2_settings_guard ).
 * Sections for methods that are not the active one start collapsed with a "Not in use" chip.
 * window.ecsr exposes the helpers the PRO live-rate list reuses: the older ones ( post, toast, confirmBox, setState, … )
 * for WP EasyCart PRO 6.0.1's grid, and drawer / confirm / list for the row list of PRO 6.0.2. */
( function( $ ) {
	'use strict';

	var V = window.ecsr_vars || { ajax: window.ajaxurl, nonce: '', method: '', i18n: {}, types: {}, zones: [] };
	var T = V.i18n || {};
	var $wrap = $( '#ecst' );
	if ( ! $wrap.length ) { return; }

	function t( key, fallback ) { return ( T && T[ key ] ) ? T[ key ] : fallback; }
	/* %s / %d in order, or %1$s / %2$d by position. */
	function sp( str ) {
		var args = Array.prototype.slice.call( arguments, 1 ), next = 0;
		return String( str ).replace( /%(?:(\d+)\$)?[sd]/g, function( m, pos ) {
			var v = pos ? args[ parseInt( pos, 10 ) - 1 ] : args[ next++ ];
			return ( undefined === v || null === v ) ? '' : String( v );
		} );
	}
	function num( v ) { var n = parseFloat( String( v == null ? '' : v ).replace( ',', '.' ) ); return isNaN( n ) ? 0 : n; }

	function toast( msg, kind ) {
		if ( ! msg ) { return; }
		if ( typeof window.ecv2_toast === 'function' ) {
			if ( ! document.getElementById( 'ecv2-toast-container' ) ) { $( '<div id="ecv2-toast-container" role="status" aria-live="polite"></div>' ).appendTo( 'body' ); }
			return window.ecv2_toast( msg, kind || 'success' );
		}
	}

	/* The V2 confirm dialog ( catalog-v2.js ). 6.0.2: its button can be named for the question ( "Delete" ), it takes the
	 * focus, Escape answers No, and focus goes back to what asked. */
	var confirming = false;
	function confirmBox( title, text, okLabel ) {
		if ( typeof window.ecv2_show_confirm !== 'function' ) {
			return Promise.resolve( window.confirm( title + ( text ? '\n\n' + text : '' ) ) );
		}
		var back = document.activeElement;
		var p = window.ecv2_show_confirm( title, text );
		var $ok = $( '#ecv2-confirm-ok' ), was = $ok.text();
		if ( okLabel && $ok.length ) { $ok.text( okLabel ); }
		confirming = true;
		setTimeout( function() { $ok.trigger( 'focus' ); }, 60 );
		return p.then( function( yes ) {
			confirming = false;
			if ( okLabel && $ok.length ) { $ok.text( was ); }
			if ( back && back.focus && document.body.contains( back ) ) { back.focus(); }
			return yes;
		} );
	}
	$( document ).on( 'keydown', function( e ) {
		if ( confirming && ( 'Escape' === e.key || 'Esc' === e.key ) && $( '#ecv2-confirm-dialog' ).is( ':visible' ) && typeof window.ecv2_confirm_cancel === 'function' ) {
			e.preventDefault();
			e.stopImmediatePropagation();
			window.ecv2_confirm_cancel();
		}
	} );

	function post( action, data, ok, fail ) {
		data = $.extend( { action: action, nonce: V.nonce }, data );
		return $.post( V.ajax, data ).done( function( r ) {
			if ( r && r.success ) { ok( r.data || {} ); } else { fail( ( r && r.data && r.data.message ) ? r.data.message : ( T.failed || 'Could not save' ) ); }
		} ).fail( function() { fail( T.failed || 'Could not save' ); } );
	}

	/* ---- kept for WP EasyCart PRO 6.0.1, whose live-rate list is still a grid of inputs ---- */
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
			if ( this === document.activeElement ) { return; }
			$( this ).val( String( data[ f ] ) );
		} );
	}
	function values( $row ) {
		var out = { type: $row.data( 'type' ), id: $row.data( 'id' ) };
		$row.find( '[data-field]' ).each( function() { out[ $( this ).data( 'field' ) ] = String( $( this ).val() || '' ); } );
		return out;
	}
	function refreshEmpty( $table ) {
		var n = $table.find( '.ecsr-row' ).not( '.ecsr-tpl' ).length;
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

	/* ================================================================== */
	/* Drawer ( 6.0.2 ): one side panel on <body> for every list's rows   */
	/* ================================================================== */
	/* ecsr.drawer.open( cfg ):
	 *   cfg.title, cfg.hint, cfg.badge ( HTML from the badge helpers ), cfg.fields ( see fieldRow() ), cfg.values,
	 *   cfg.saveLabel, cfg.deleteLabel ( '' = no Delete ), cfg.onSave( values, api ), cfg.onDelete( api ),
	 *   cfg.onChange( name, value, api ), cfg.validate( values, api ) → false to stop.
	 * api: close( { force, focus } ), busy( on ), error( message, field ), value( name ), set( name, value ),
	 *      options( name, [ [ value, label ], … ], selected ).
	 * Fields: { name, kind: text | money | weight | int | percent | zone | select, label, hint, placeholder, required,
	 *           optional, options ( select ), unit ( weight ), link: { href, text } }. */
	var D = { cfg: null, back: null, initial: '', busy: false };
	var $drawer = $(), $backdrop = $();

	function buildDrawer() {
		if ( $drawer.length ) { return; }
		$backdrop = $( '<div class="ecsr-drawer-backdrop ecv2-layer" hidden></div>' ).appendTo( 'body' );
		$drawer = $( '<div class="ecsr-drawer ecv2-layer" id="ecsr_drawer" role="dialog" aria-modal="true" aria-labelledby="ecsr_drawer_title" aria-describedby="ecsr_drawer_hint" hidden></div>' );
		var $head = $( '<div class="ecsr-drawer-h"></div>' )
			.append( '<span class="ecsr-drawer-badge" aria-hidden="true"></span>' )
			.append( $( '<div class="ecsr-drawer-titles"></div>' ).append( '<h2 id="ecsr_drawer_title" tabindex="-1"></h2>' ).append( '<p id="ecsr_drawer_hint"></p>' ) )
			.append( $( '<button type="button" class="ecsr-drawer-x" data-ecsr-close>&times;</button>' ).attr( 'aria-label', t( 'close', 'Close' ) ) );
		var $form = $( '<form class="ecsr-drawer-b" novalidate></form>' )
			.append( '<div class="ecsr-drawer-alert" role="alert" hidden></div>' )
			.append( '<div class="ecsr-drawer-fields"></div>' );
		var $foot = $( '<div class="ecsr-drawer-f"></div>' )
			.append( $( '<button type="button" class="ecv2-btn ecsr-drawer-del" hidden></button>' ) )
			.append( '<span class="ecsr-grow"></span>' )
			.append( $( '<button type="button" class="ecv2-btn ecsr-drawer-cancel" data-ecsr-close></button>' ).text( t( 'cancel', 'Cancel' ) ) )
			.append( $( '<button type="button" class="ecv2-btn ecv2-btn-primary ecsr-drawer-save"></button>' ) );
		$drawer.append( $head, $form, $foot ).appendTo( 'body' );

		$drawer.on( 'click', '[data-ecsr-close]', function( e ) { e.preventDefault(); requestClose(); } );
		$backdrop.on( 'click', function( e ) { e.preventDefault(); requestClose(); } );
		$drawer.on( 'click', '.ecsr-drawer-save', function( e ) { e.preventDefault(); submit(); } );
		$drawer.on( 'submit', 'form', function( e ) { e.preventDefault(); submit(); } );
		$drawer.on( 'keydown', 'input.ecsr-field-in', function( e ) {
			if ( 'Enter' === e.key ) { e.preventDefault(); submit(); }
		} );
		$drawer.on( 'click', '.ecsr-drawer-del', function( e ) {
			e.preventDefault();
			if ( D.cfg && typeof D.cfg.onDelete === 'function' && ! D.busy ) { D.cfg.onDelete( api ); }
		} );
		$drawer.on( 'input change', '.ecsr-field-in', function( e ) {
			var $f = $( this ).closest( '.ecsr-field' );
			if ( $f.hasClass( 'is-invalid' ) ) { clearFieldError( $f ); }
			if ( 'change' === e.type && D.cfg && typeof D.cfg.onChange === 'function' ) { D.cfg.onChange( String( $( this ).attr( 'name' ) ), String( $( this ).val() || '' ), api ); }
		} );
	}

	function fieldId( name ) { return 'ecsr_f_' + String( name ).replace( /[^a-z0-9_\-]/gi, '' ); }

	function fillOptions( $select, options, selected, placeholder ) {
		$select.empty();
		if ( placeholder ) { $select.append( $( '<option value=""></option>' ).text( placeholder ) ); }
		$.each( options || [], function( i, o ) { $select.append( $( '<option></option>' ).attr( 'value', String( o[0] ) ).text( String( o[1] ) ) ); } );
		var want = ( selected == null ) ? '' : String( selected );
		if ( $select.find( 'option' ).filter( function() { return this.value === want; } ).length ) { $select.val( want ); } else { $select.prop( 'selectedIndex', 0 ); }
	}

	function fieldRow( f, value ) {
		var id = fieldId( f.name ), kind = f.kind || 'text', described = [];
		var $row = $( '<div class="ecsr-field"></div>' ).attr( 'data-name', f.name ).attr( 'data-kind', kind );
		var label = f.label || ( 'zone' === kind ? t( 'zone', 'Zone' ) : f.name );
		var $label = $( '<label class="ecsr-field-l"></label>' ).attr( 'for', id ).text( label );
		if ( f.optional ) { $label.append( ' ' ).append( $( '<span class="ecsr-field-opt"></span>' ).text( t( 'optional', 'Optional' ) ) ); }
		$row.append( $label );
		var $in;
		if ( 'zone' === kind || 'select' === kind ) {
			$in = $( '<select class="ecv2-select ecsr-field-in"></select>' );
			fillOptions( $in, 'zone' === kind ? ( V.zones || [] ) : f.options, value, f.placeholder );
		} else {
			$in = $( '<input type="text" class="ecv2-input ecsr-field-in" autocomplete="off" spellcheck="false">' ).val( value == null ? '' : String( value ) );
			if ( 'text' !== kind ) { $in.attr( 'inputmode', 'int' === kind ? 'numeric' : 'decimal' ); }
			if ( f.placeholder ) { $in.attr( 'placeholder', String( f.placeholder ) ); }
		}
		$in.attr( { id: id, name: f.name } );
		if ( f.required ) { $in.attr( 'aria-required', 'true' ); }
		var prefix = '', suffix = '';
		if ( 'money' === kind && V.symbol ) { if ( parseInt( V.symbol_first, 10 ) === 0 ) { suffix = V.symbol; } else { prefix = V.symbol; } }
		if ( 'percent' === kind ) { suffix = '%'; }
		if ( 'weight' === kind ) { suffix = f.unit || V.weight_unit || ''; }
		var $control = $in;
		if ( prefix || suffix ) {
			$control = $( '<span class="ecsr-affix-wrap"></span>' );
			if ( prefix ) { $control.addClass( 'has-prefix' ).append( $( '<span class="ecsr-affix-t" aria-hidden="true"></span>' ).text( prefix ) ); }
			$control.append( $in );
			if ( suffix ) { $control.addClass( 'has-suffix' ).append( $( '<span class="ecsr-affix-t" aria-hidden="true"></span>' ).text( suffix ) ); }
		}
		$row.append( $control );
		var link = f.link || ( 'zone' === kind && V.zones_url ? { href: V.zones_url, text: t( 'manage_zones', 'Manage zones' ) } : null );
		if ( f.hint || link ) {
			var $hint = $( '<p class="ecsr-field-hint"></p>' ).attr( 'id', id + '_hint' ).text( f.hint || '' );
			if ( link ) { $hint.append( f.hint ? ' ' : '' ).append( $( '<a target="_blank" rel="noopener noreferrer"></a>' ).attr( 'href', link.href ).text( link.text + ' ↗' ) ); }
			$row.append( $hint );
			described.push( id + '_hint' );
		}
		$row.append( $( '<p class="ecsr-field-err"></p>' ).attr( 'id', id + '_err' ).prop( 'hidden', true ) );
		if ( described.length ) { $in.attr( 'aria-describedby', described.join( ' ' ) ); }
		return $row;
	}

	function field( name ) { return $drawer.find( '.ecsr-field-in' ).filter( function() { return this.name === name; } ); }
	function drawerValues() {
		var out = {};
		$drawer.find( '.ecsr-field-in' ).each( function() { out[ this.name ] = String( $( this ).val() == null ? '' : $( this ).val() ); } );
		return out;
	}
	function snapshot() { return JSON.stringify( drawerValues() ); }

	function clearFieldError( $f ) {
		$f.removeClass( 'is-invalid' ).find( '.ecsr-field-err' ).prop( 'hidden', true ).text( '' );
		var $in = $f.find( '.ecsr-field-in' ), ids = String( $in.attr( 'aria-describedby' ) || '' ).replace( $in.attr( 'id' ) + '_err', '' ).replace( /\s+/g, ' ' ).trim();
		$in.removeAttr( 'aria-invalid' );
		if ( ids ) { $in.attr( 'aria-describedby', ids ); } else { $in.removeAttr( 'aria-describedby' ); }
	}
	function clearErrors() {
		$drawer.find( '.ecsr-field.is-invalid' ).each( function() { clearFieldError( $( this ) ); } );
		$drawer.find( '.ecsr-drawer-alert' ).prop( 'hidden', true ).text( '' );
	}
	function showError( msg, name ) {
		var $in = name ? field( name ) : $();
		if ( $in.length ) {
			var $f = $in.closest( '.ecsr-field' ), errId = $in.attr( 'id' ) + '_err';
			$f.addClass( 'is-invalid' ).find( '.ecsr-field-err' ).text( msg ).prop( 'hidden', false );
			$in.attr( 'aria-invalid', 'true' ).attr( 'aria-describedby', $.trim( String( $in.attr( 'aria-describedby' ) || '' ).replace( errId, '' ) + ' ' + errId ) );
			$in.trigger( 'focus' );
			return;
		}
		$drawer.find( '.ecsr-drawer-alert' ).text( msg ).prop( 'hidden', false );
		$drawer.find( '.ecsr-drawer-b' ).scrollTop( 0 );
	}
	function setBusy( on ) {
		D.busy = !! on;
		$drawer.toggleClass( 'is-busy', D.busy ).attr( 'aria-busy', D.busy ? 'true' : 'false' );
		$drawer.find( '.ecsr-drawer-save, .ecsr-drawer-del' ).prop( 'disabled', D.busy );
	}

	/* Every field is checked before cfg.validate and cfg.onSave: required ones filled, numbers 0 or more. */
	function validate() {
		var ok = true, first = null;
		$.each( ( D.cfg && D.cfg.fields ) || [], function( i, f ) {
			var v = $.trim( String( field( f.name ).val() || '' ) ), kind = f.kind || 'text', msg = '';
			if ( f.required && ( '' === v || ( ( 'select' === kind ) && ( '0' === v ) ) ) ) {
				msg = f.requiredText || t( 'required', 'This is required.' );
			} else if ( '' !== v && ( 'money' === kind || 'weight' === kind || 'percent' === kind || 'int' === kind ) ) {
				/* digits with dots or commas ( 4,50 / 1,234.50 ): the server reads the decimal mark the way it always has */
				if ( ! /^[\d.,]+$/.test( v.replace( /\s/g, '' ) ) || ! /\d/.test( v ) ) { msg = t( 'number_needed', 'Enter a number of 0 or more.' ); }
			}
			if ( msg ) {
				ok = false;
				var $in = field( f.name ), $f = $in.closest( '.ecsr-field' ), errId = $in.attr( 'id' ) + '_err';
				$f.addClass( 'is-invalid' ).find( '.ecsr-field-err' ).text( msg ).prop( 'hidden', false );
				$in.attr( 'aria-invalid', 'true' ).attr( 'aria-describedby', $.trim( String( $in.attr( 'aria-describedby' ) || '' ).replace( errId, '' ) + ' ' + errId ) );
				if ( ! first ) { first = $in; }
			}
		} );
		if ( first ) { first.trigger( 'focus' ); }
		return ok;
	}

	function submit() {
		if ( ! D.cfg || D.busy ) { return; }
		clearErrors();
		if ( ! validate() ) { return; }
		var vals = drawerValues();
		if ( typeof D.cfg.validate === 'function' && false === D.cfg.validate( vals, api ) ) { return; }
		if ( typeof D.cfg.onSave === 'function' ) { D.cfg.onSave( vals, api ); }
	}

	function focusables() {
		return $drawer.find( 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])' ).filter( ':visible' );
	}

	function openDrawer( cfg ) {
		buildDrawer();
		if ( isOpen() ) { closeDrawer( { force: true, quiet: true } ); }
		D.cfg = cfg || {};
		D.back = cfg.returnFocus || document.activeElement;
		$drawer.find( '#ecsr_drawer_title' ).text( cfg.title || '' );
		$drawer.find( '#ecsr_drawer_hint' ).text( cfg.hint || '' ).prop( 'hidden', ! cfg.hint );
		$drawer.find( '.ecsr-drawer-badge' ).html( cfg.badge || '' ).prop( 'hidden', ! cfg.badge );
		var $fields = $drawer.find( '.ecsr-drawer-fields' ).empty(), vals = cfg.values || {};
		$.each( cfg.fields || [], function( i, f ) { $fields.append( fieldRow( f, vals[ f.name ] ) ); } );
		$drawer.find( '.ecsr-drawer-save' ).text( cfg.saveLabel || t( 'save', 'Save changes' ) );
		var $del = $drawer.find( '.ecsr-drawer-del' );
		$del.empty().prop( 'hidden', ! cfg.deleteLabel || typeof cfg.onDelete !== 'function' );
		if ( cfg.deleteLabel ) { $del.append( $( '<span></span>' ).text( cfg.deleteLabel ) ); }
		clearErrors();
		setBusy( false );
		if ( typeof cfg.onOpen === 'function' ) { cfg.onOpen( api ); }
		D.initial = snapshot();
		$backdrop.prop( 'hidden', false );
		$drawer.prop( 'hidden', false );
		$( 'body' ).addClass( 'ecsr-drawer-open' );
		setTimeout( function() {
			var $first = $drawer.find( '.ecsr-field-in' ).filter( ':visible' ).first();
			( $first.length ? $first : $drawer.find( '#ecsr_drawer_title' ) ).trigger( 'focus' );
		}, 40 );
	}

	function isOpen() { return $drawer.length && ! $drawer.prop( 'hidden' ); }

	function closeDrawer( opts ) {
		opts = opts || {};
		if ( ! isOpen() ) { return; }
		$drawer.prop( 'hidden', true );
		$backdrop.prop( 'hidden', true );
		$( 'body' ).removeClass( 'ecsr-drawer-open' );
		var back = opts.focus || D.back;
		D.cfg = null;
		D.busy = false;
		if ( ! opts.quiet && back && back.focus && document.body.contains( back ) ) { back.focus(); }
	}

	/* Cancel, ×, Escape and the backdrop ask before throwing away changes. */
	function requestClose() {
		if ( ! isOpen() || D.busy ) { return; }
		if ( snapshot() === D.initial ) { closeDrawer(); return; }
		confirmBox( t( 'discard_title', 'Discard your changes?' ), t( 'discard_text', 'What you changed in this panel has not been saved.' ), t( 'discard', 'Discard' ) ).then( function( yes ) {
			if ( yes ) { closeDrawer(); }
		} );
	}

	$( document ).on( 'keydown', function( e ) {
		if ( ! isOpen() || confirming ) { return; }
		if ( 'Escape' === e.key || 'Esc' === e.key ) {
			e.preventDefault();
			requestClose();
			return;
		}
		if ( 'Tab' !== e.key ) { return; }
		var $f = focusables();
		if ( ! $f.length ) { e.preventDefault(); return; }
		var first = $f[0], last = $f[ $f.length - 1 ];
		if ( ! $drawer[0].contains( document.activeElement ) ) {
			e.preventDefault();
			( e.shiftKey ? last : first ).focus();
		} else if ( e.shiftKey && ( document.activeElement === first || document.activeElement === $drawer.find( '#ecsr_drawer_title' )[0] ) ) {
			e.preventDefault();
			last.focus();
		} else if ( ! e.shiftKey && document.activeElement === last ) {
			e.preventDefault();
			first.focus();
		}
	} );

	var api = {
		close: function( opts ) { closeDrawer( opts ); },
		busy: setBusy,
		error: function( msg, name ) { setBusy( false ); showError( msg || t( 'failed', 'Could not save' ), name ); },
		value: function( name ) { return String( field( name ).val() || '' ); },
		set: function( name, value ) { field( name ).val( value == null ? '' : String( value ) ); },
		options: function( name, list, selected, placeholder ) { fillOptions( field( name ), list, selected, placeholder ); }
	};

	/* ================================================================== */
	/* Lists                                                              */
	/* ================================================================== */
	function rowData( $item ) {
		try { return JSON.parse( String( $item.attr( 'data-row' ) || '{}' ) ) || {}; } catch ( err ) { return {}; }
	}
	function setRowData( $item, data ) { $item.attr( 'data-row', JSON.stringify( data || {} ) ); }

	/* Empty state, headings and the Add button follow the number of rows. */
	function refreshList( $rows ) {
		var n = $rows.find( '.ecsr-list > .ecsr-item' ).length;
		$rows.find( '.ecsr-list, .ecsr-list-head' ).prop( 'hidden', 0 === n );
		$rows.find( '.ecsr-empty-state' ).prop( 'hidden', n > 0 );
		$rows.find( '.ecsr-list-foot' ).prop( 'hidden', 0 === n );
	}

	function flash( $item ) {
		$item.addClass( 'is-new' );
		setTimeout( function() { $item.removeClass( 'is-new' ); }, 1900 );
	}

	var $announcer = $();
	function announce( msg ) {
		if ( ! $announcer.length ) { $announcer = $( '<div class="screen-reader-text" aria-live="polite" aria-atomic="true"></div>' ).appendTo( 'body' ); }
		$announcer.text( '' );
		setTimeout( function() { $announcer.text( msg ); }, 30 );
	}

	/* Drag ( jQuery UI sortable, handle .ecsr-grip ) and the arrow keys on a handle reorder a list; either way the list
	 * fires 'ecsr:order' once the rows settle, and its owner saves the order. */
	function sortableList( $rows ) {
		var $list = $rows.find( '.ecsr-list' ).first();
		if ( ! $list.length || ! $.fn.sortable || $list.data( 'ecsrSortable' ) ) { return; }
		$list.data( 'ecsrSortable', 1 ).sortable( {
			items: '> .ecsr-item:not(.is-readonly)',
			handle: '.ecsr-grip',
			cancel: '.ecsr-item-actions, input, select, textarea, a',
			axis: 'y',
			tolerance: 'pointer',
			placeholder: 'ecsr-placeholder',
			forcePlaceholderSize: true,
			update: function() { $rows.trigger( 'ecsr:order' ); }
		} );
	}
	var moveTimers = {};
	$wrap.on( 'keydown', '.ecsr-grip', function( e ) {
		if ( 'ArrowUp' !== e.key && 'ArrowDown' !== e.key ) { return; }
		e.preventDefault();
		var $item = $( this ).closest( '.ecsr-item' ), $rows = $item.closest( '.ecsr-rows' ), up = 'ArrowUp' === e.key;
		var $to = up ? $item.prevAll( '.ecsr-item:not(.is-readonly)' ).first() : $item.nextAll( '.ecsr-item:not(.is-readonly)' ).first();
		if ( ! $to.length ) { return; }
		if ( up ) { $to.before( $item ); } else { $to.after( $item ); }
		this.focus();
		var $all = $item.parent().children( '.ecsr-item:not(.is-readonly)' );
		announce( sp( t( 'moved', 'Moved to position %1$d of %2$d.' ), $all.index( $item ) + 1, $all.length ) );
		var key = String( $rows.data( 'type' ) );
		clearTimeout( moveTimers[ key ] );
		moveTimers[ key ] = setTimeout( function() { $rows.trigger( 'ecsr:order' ); }, 700 );
	} );

	/* ================================================================== */
	/* The five free tables                                               */
	/* ================================================================== */
	function typeCfg( type ) { return ( V.types && V.types[ type ] ) ? V.types[ type ] : null; }
	function rowsOf( type ) { return $wrap.find( '.ecsr-rows[data-type="' + type + '"]' ).first(); }

	function sortRows( type, $rows ) {
		var $list = $rows.find( '.ecsr-list' ).first(), items = $list.children( '.ecsr-item' ).get();
		items.sort( function( a, b ) {
			var x = rowData( $( a ) ), y = rowData( $( b ) );
			var ka = ( 'flat' === type ) ? num( x.order ) : num( x.trigger ), kb = ( 'flat' === type ) ? num( y.order ) : num( y.trigger );
			return ( ka - kb ) || ( num( x.id ) - num( y.id ) );
		} );
		$list.append( items );
	}

	function placeRow( type, d, $old ) {
		var $rows = rowsOf( type );
		if ( ! d.html || ! $rows.length ) { window.location.reload(); return $(); }
		var $new = $( $.parseHTML( $.trim( String( d.html ) ) ) ).filter( '.ecsr-item' ).first();
		if ( $old && $old.length && document.body.contains( $old[0] ) ) { $old.replaceWith( $new ); } else { $rows.find( '.ecsr-list' ).first().append( $new ); }
		sortRows( type, $rows );
		refreshList( $rows );
		flash( $new );
		return $new;
	}

	function nextOrder( $rows ) {
		var max = 0;
		$rows.find( '.ecsr-list > .ecsr-item' ).each( function() { max = Math.max( max, num( rowData( $( this ) ).order ) ); } );
		return max + 1;
	}

	function openRate( type, $item, opener ) {
		var cfg = typeCfg( type ), $rows = rowsOf( type );
		if ( ! cfg ) { return; }
		var data = ( $item && $item.length ) ? rowData( $item ) : {}, id = parseInt( data.id, 10 ) || 0, vals = {};
		$.each( cfg.fields || [], function( i, f ) { vals[ f.name ] = ( id && ( f.name in data ) ) ? data[ f.name ] : ''; } );
		if ( ! id ) {
			vals.zone_id = '0';
			if ( 'flat' === type ) { vals.order = String( nextOrder( $rows ) ); }
		}
		var fields = $.map( cfg.fields || [], function( f ) {
			return ( 'flat' === type && 'label' === f.name ) ? $.extend( {}, f, { requiredText: t( 'label_needed', 'Give the method a name first.' ) } ) : f;
		} );
		ecsr.drawer.open( {
			returnFocus: opener, /* some browsers do not focus a clicked button */
			title: id ? cfg.edit : cfg.add,
			hint: cfg.hint,
			badge: cfg.badge,
			fields: fields,
			values: vals,
			saveLabel: id ? t( 'save', 'Save changes' ) : cfg.save,
			deleteLabel: id ? t( 'delete', 'Delete' ) : '',
			onDelete: id ? function( dApi ) { removeRate( type, $item, dApi ); } : null,
			onSave: function( values, dApi ) {
				dApi.busy( true );
				var payload = $.extend( { type: type }, values );
				if ( id ) { payload.id = id; }
				post( id ? 'ecv2_shipping_rate_update' : 'ecv2_shipping_rate_add', payload, function( d ) {
					var $new = placeRow( type, d, id ? $item : null );
					dApi.close( { focus: $new.find( '.ecsr-edit' )[0] } );
					toast( d.message || ( id ? t( 'saved', 'Saved' ) : t( 'added', 'Rate added.' ) ), 'success' );
				}, function( m ) { dApi.error( m ); } );
			}
		} );
	}

	function removeRate( type, $item, dApi ) {
		var data = rowData( $item ), $rows = $item.closest( '.ecsr-rows' );
		confirmBox( t( 'confirm_title', 'Delete this rate?' ), t( 'confirm_text', '' ), t( 'delete', 'Delete' ) ).then( function( yes ) {
			if ( ! yes ) { return; }
			if ( dApi ) { dApi.busy( true ); }
			$item.addClass( 'is-removing' );
			post( 'ecv2_shipping_rate_delete', { type: type, id: data.id }, function( d ) {
				var $next = $item.nextAll( '.ecsr-item' ).first();
				if ( ! $next.length ) { $next = $item.prevAll( '.ecsr-item' ).first(); }
				$item.remove();
				refreshList( $rows );
				var target = $next.length ? $next.find( '.ecsr-edit' )[0] : $rows.find( '.ecsr-add' ).filter( ':visible' )[0];
				if ( dApi ) { dApi.close( { focus: target } ); } else if ( target ) { target.focus(); }
				toast( d.message || t( 'deleted', 'Rate deleted.' ), 'success' );
			}, function( m ) {
				$item.removeClass( 'is-removing' );
				if ( dApi ) { dApi.error( m ); } else { toast( m, 'error' ); }
			} );
		} );
	}

	/* Flat methods: after a drag, each moved row is saved with its new position ( 1, 2, 3, … ). */
	function saveFlatOrder( $rows ) {
		var calls = [];
		$rows.find( '.ecsr-list > .ecsr-item' ).each( function( i ) {
			var $it = $( this ), d = rowData( $it ), want = String( i + 1 );
			if ( String( d.order ) === want ) { return; }
			var payload = $.extend( {}, d, { type: 'flat', order: want } );
			calls.push( $.Deferred( function( def ) {
				post( 'ecv2_shipping_rate_update', payload, function( r ) { setRowData( $it, r.row || $.extend( d, { order: want } ) ); def.resolve(); }, function( m ) { def.reject( m ); } );
			} ).promise() );
		} );
		if ( ! calls.length ) { return; }
		$.when.apply( $, calls ).done( function() { toast( t( 'order_saved', 'Order saved.' ), 'success' ); } ).fail( function( m ) { toast( m || t( 'failed', 'Could not save' ), 'error' ); } );
	}

	$wrap.find( '.ecsr-rows[data-type]' ).each( function() {
		var $rows = $( this ), type = String( $rows.data( 'type' ) );
		if ( ! typeCfg( type ) ) { return; }
		if ( 'flat' === type ) {
			sortableList( $rows );
			$rows.on( 'ecsr:order', function() { saveFlatOrder( $rows ); } );
		}
	} );
	$wrap.on( 'click', '.ecsr-rows[data-type] .ecsr-edit', function( e ) {
		var $item = $( this ).closest( '.ecsr-item' ), type = String( $item.closest( '.ecsr-rows' ).data( 'type' ) );
		if ( ! typeCfg( type ) ) { return; }
		e.preventDefault();
		openRate( type, $item, $item.find( '.ecsr-edit' )[0] );
	} );
	$wrap.on( 'click', '.ecsr-rows[data-type] .ecsr-add', function( e ) {
		var type = String( $( this ).closest( '.ecsr-rows' ).data( 'type' ) );
		if ( ! typeCfg( type ) ) { return; }
		e.preventDefault();
		openRate( type, null, this );
	} );
	$wrap.on( 'click', '.ecsr-rows[data-type] .ecsr-remove', function( e ) {
		var $item = $( this ).closest( '.ecsr-item' ), type = String( $item.closest( '.ecsr-rows' ).data( 'type' ) );
		if ( ! typeCfg( type ) ) { return; }
		e.preventDefault();
		removeRate( type, $item, null );
	} );
	/* The title opens the row too ( a larger target than Edit ). */
	$wrap.on( 'click', '.ecsr-rows[data-type] .ecsr-item:not(.is-readonly) .ecsr-item-main', function() {
		$( this ).closest( '.ecsr-item' ).find( '.ecsr-edit' ).trigger( 'click' );
	} );

	var ecsr = window.ecsr = {
		post: post, toast: toast, confirmBox: confirmBox, setState: setState, fill: fill, values: values, refreshEmpty: refreshEmpty,
		markIdle: markIdle, openIdle: openIdle, unmarkIdle: unmarkIdle, applyMethod: applyMethod, i18n: T,
		/* 6.0.2 */
		version: 2,
		confirm: confirmBox,
		drawer: { open: openDrawer, close: closeDrawer, isOpen: isOpen, api: api },
		list: { data: rowData, setData: setRowData, refresh: refreshList, flash: flash, sortable: sortableList, announce: announce },
		sp: sp
	};
} )( jQuery );
