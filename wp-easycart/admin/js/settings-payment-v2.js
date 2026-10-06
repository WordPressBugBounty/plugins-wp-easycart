/* WP EasyCart Admin — Settings › Payment ( V2 ): one line per way to pay ( with a ⋯ menu ), the searchable gateway list,
   the test-mode line, the EasyCart Connect terms ( asked when a Connect link is first used ) and the drawer. The drawer hosts
   a declared gateway form ( V2 fields, saved from the footer through ecv2_payment_gateway_save ), a legacy per-gateway partial
   ( driven by admin/js/payment.js; a partial with one Save Options button is saved from the footer too ) or the small Bill
   later wording form. Every change redraws the page sections in place ( 6.0.3: turning a gateway on or off and switching
   test mode too ); only Connect, Reconnect and Disconnect leave the page.

   6.0.3: a legacy panel saved from the footer saves once: its switches and selects no longer save as they change ( D5 ),
   except the Live / Test switches, which connect or select the gateway. A panel with three or more groups shows them as tabs. */
( function( $ ) {
	'use strict';

	var V = window.ecpay_vars || { ajax: window.ajaxurl, nonce: '', page: 'payment', user: 0, i18n: {} };
	var T = V.i18n || {};
	var $wrap = $( '#ecst' );
	if ( ! $wrap.length || $wrap.data( 'page' ) !== V.page ) { return; }

	function toast( msg, kind ) {
		if ( ! msg ) { return; }
		if ( typeof window.ecv2_toast === 'function' ) { return window.ecv2_toast( msg, kind || 'success' ); }
		var $t = $( '<div class="ecdv2-toast" role="status" style="position:fixed;bottom:24px;right:24px;z-index:99999;background:#111827;color:#fff;padding:10px 14px;border-radius:8px;font-size:13px"></div>' ).text( msg ).appendTo( 'body' );
		setTimeout( function() { $t.fadeOut( 200, function() { $t.remove(); } ); }, 2400 );
	}
	/* ecv2_show_confirm() needs the #ecv2-confirm-dialog markup, which only the table pages print; without it
	   the promise never resolves. Use it only when the dialog exists, otherwise the native confirm. */
	function confirmBox( title, text ) {
		if ( typeof window.ecv2_show_confirm === 'function' && $( '#ecv2-confirm-dialog' ).length ) { return window.ecv2_show_confirm( title, text || '' ); }
		return Promise.resolve( window.confirm( title + ( text ? '\n\n' + text : '' ) ) );
	}
	function post( action, data, ok, fail ) {
		data = $.extend( { action: action, nonce: V.nonce }, data );
		return $.post( V.ajax, data ).done( function( r ) {
			if ( r && r.success ) { ok( r.data || {} ); } else { fail( ( r && r.data && r.data.message ) ? r.data.message : T.request_failed ); }
		} ).fail( function() { fail( T.request_failed ); } );
	}
	function esc( s ) { return $( '<i>' ).text( s == null ? '' : String( s ) ).html(); }
	function reload( delay ) { setTimeout( function() { window.location.reload(); }, delay || 700 ); }
	function store( key, val ) { try { window.localStorage.setItem( key, val ); } catch ( e ) { /* private mode */ } }
	function read( key ) { try { return window.localStorage.getItem( key ); } catch ( e ) { return null; } }

	/* ================================================================== */
	/* Drawer                                                              */
	/* ================================================================== */
	/* mode: 'v2' ( declared gateway form, footer Save ), 'legacy-single' ( legacy partial with one Save Options
	   button, driven from the footer ), 'legacy' ( partial that saves from its own controls ), 'manual' ( Bill later ). */
	var drawer = { open: false, key: '', kind: '', mode: '', initial: '', saving: false, state: null, selects: false, legacyWait: false, legacyTimer: 0, reload: false, opener: null, $legacySave: null };

	function drawerHtml( title ) {
		return '<div class="ecdrawer-backdrop ecpay-backdrop"></div>' +
			'<aside class="ecdrawer ecpay-drawer" role="dialog" aria-modal="true" aria-labelledby="ecpay_drawer_title">' +
				'<div class="ecdrawer-h"><div class="ecpay-drawer-titles"><h3 id="ecpay_drawer_title">' + esc( title ) + '</h3><span class="ecpay-drawer-sub" id="ecpay_drawer_sub"></span></div><span class="ecpay-drawer-chips" id="ecpay_drawer_chips"></span><a class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" id="ecpay_drawer_docs" href="#" target="_blank" rel="noopener noreferrer" hidden>' + esc( T.docs ) + ' &#8599;</a><button type="button" class="ecdrawer-x" aria-label="' + esc( T.close ) + '">&times;</button>' +
					'<div class="ecpay-tabs" id="ecpay_drawer_tabs" role="tablist" aria-label="' + esc( T.tabs_label ) + '" hidden></div></div>' +
				'<div class="ecdrawer-b" id="ecpay_drawer_body"><div class="ecpay-drawer-loading">' + esc( T.loading ) + '</div></div>' +
				'<div class="ecdrawer-f"><span class="ecpay-drawer-note" id="ecpay_drawer_note" aria-live="polite"></span><span class="ecst-grow"></span>' +
					'<button type="button" class="ecv2-btn" id="ecpay_drawer_close">' + esc( T.close ) + '</button>' +
					'<button type="button" class="ecv2-btn ecv2-btn-primary" id="ecpay_drawer_save" hidden>' + esc( T.save ) + '</button>' +
					'<button type="button" class="ecv2-btn ecv2-btn-primary" id="ecpay_drawer_save_on" hidden>' + esc( T.save_on ) + '</button>' +
				'</div>' +
			'</aside>';
	}

	/* 6.0.3: the state lives in the header once ( state and mode chips, the account line under the name ); no slot chip. */
	function setChips( state ) {
		var h = '';
		if ( state && state.state_label ) { h += '<span class="ecv2-chip ' + esc( state.state_class || 'ecv2-chip-gray' ) + '">' + esc( state.state_label ) + '</span>'; }
		if ( state && state.mode_label ) { h += '<span class="ecv2-chip ' + ( state.test ? 'ecv2-chip-amber' : 'ecv2-chip-brand' ) + '">' + esc( state.mode_label ) + '</span>'; }
		$( '#ecpay_drawer_chips' ).html( h );
		$( '#ecpay_drawer_sub' ).text( state && state.detail ? state.detail : '' );
	}

	/* Header chips and footer buttons follow the gateway's saved state. */
	function applyState( state ) {
		if ( ! state ) { return; }
		drawer.state = state;
		setChips( state );
		if ( drawer.mode === 'v2' || drawer.mode === 'manual' ) {
			var canOn = drawer.mode === 'manual' ? ! state.enabled : !! state.can_enable;
			$( '#ecpay_drawer_save_on' ).prop( 'hidden', ! canOn );
			$( '#ecpay_drawer_save' ).toggleClass( 'ecv2-btn-primary', ! canOn );
		}
		if ( state.enabled ) { drawer.selects = false; }
		updateDirty();
	}

	/* Re-render the lines, the gateway list and the subscriptions row in place ( falls back to a reload on close ). */
	function replaceSections( sections ) {
		if ( ! sections ) { return; }
		var $active = $( '#ecst-sec-active .ecst-custom' ), $more = $( '#ecst-sec-more .ecst-custom' );
		if ( ! $active.length || ! $more.length ) { drawer.reload = true; return; }
		var keep = catalogState();
		closeMenus();
		if ( typeof sections.active === 'string' ) { $active.html( sections.active ); placeSummary(); }
		if ( typeof sections.more === 'string' ) { $more.html( sections.more ); restoreCatalog( keep ); }
		if ( typeof sections.subs === 'string' ) { $( '#ecst-ecst_payment_subscriptions .ecst-row-html' ).html( sections.subs ); }
	}

	var refreshTimer = 0;
	function refreshState() {
		clearTimeout( refreshTimer );
		refreshTimer = setTimeout( function() {
			$.post( V.ajax, { action: 'ecv2_payment_state', nonce: V.nonce, gateway: drawer.open ? drawer.key : '' } ).done( function( r ) {
				if ( ! r || ! r.success || ! r.data ) { drawer.reload = true; return; }
				replaceSections( r.data.sections );
				if ( drawer.open && r.data.state ) { applyState( r.data.state ); }
			} ).fail( function() { drawer.reload = true; } );
		}, 350 );
	}

	/* Values of a declared form: option => string ( toggles send their on / off value ). */
	function collect() {
		var values = {};
		$( '#ecpay_gwform .ecpay-in' ).each( function() {
			var $i = $( this ), key = $i.attr( 'data-key' );
			if ( ! key ) { return; }
			if ( $i.is( ':checkbox' ) ) {
				values[ key ] = String( $i.is( ':checked' ) ? $i.attr( 'data-on' ) : $i.attr( 'data-off' ) );
			} else if ( $i.is( ':radio' ) ) {
				if ( $i.is( ':checked' ) ) { values[ key ] = String( $i.val() ); }
			} else {
				values[ key ] = String( $i.val() == null ? '' : $i.val() );
			}
		} );
		return values;
	}
	function legacyFields() {
		return $( '#ecpay_drawer_body' ).find( 'input:not([type="hidden"]):not([type="submit"]):not([type="button"]), select, textarea' );
	}
	var LEGACY_SEP = '\u0001';
	function legacyParts() {
		return legacyFields().map( function() { return $( this ).is( ':checkbox, :radio' ) ? ( this.checked ? '1' : '0' ) : String( $( this ).val() ); } ).get();
	}
	function snapshot() {
		if ( drawer.mode === 'v2' ) { return JSON.stringify( collect() ); }
		if ( drawer.mode === 'manual' ) { return String( $( '#ecpay_manual_title' ).val() ) + '' + String( $( '#ecpay_manual_message' ).val() ) + '' + manualRoles().join( ',' ); }
		if ( drawer.mode === 'legacy-single' ) { return legacyParts().join( LEGACY_SEP ); }
		return '';
	}
	/* 6.0.2: a one-option save ( ec_admin_update_stripe_connect_option() posts update_var ) marks only that field as saved, so a
	   Stripe payment method switch no longer hides an unsaved currency or signing secret beside it. The other saves post the
	   whole partial and mark it all saved, as before. */
	function savedSnapshot( posted ) {
		var m = /(?:^|&)update_var=([^&]*)/.exec( String( posted || '' ) );
		if ( drawer.mode !== 'legacy-single' || ! m ) { return snapshot(); }
		var id = '', before = String( drawer.initial ).split( LEGACY_SEP ), now = legacyParts();
		try { id = decodeURIComponent( m[1].replace( /\+/g, ' ' ) ); } catch ( err ) { return snapshot(); }
		if ( before.length !== now.length ) { return now.join( LEGACY_SEP ); }
		legacyFields().each( function( i ) { if ( this.id === id ) { before[ i ] = now[ i ]; } } );
		return before.join( LEGACY_SEP );
	}
	function isDirty() {
		return drawer.open && ( drawer.mode === 'v2' || drawer.mode === 'manual' || drawer.mode === 'legacy-single' ) && snapshot() !== drawer.initial;
	}
	function updateDirty() {
		if ( ! drawer.open ) { return; }
		var dirty = isDirty(), note = '';
		if ( dirty ) {
			note = T.unsaved;
		} else if ( drawer.mode === 'legacy' ) {
			note = T.drawer_note;
		}
		if ( drawer.selects && ( drawer.mode === 'legacy-single' || drawer.mode === 'legacy' ) ) {
			note = ( note ? note + ' · ' : '' ) + String( T.legacy_selects || '' ).replace( '%s', $( '#ecpay_drawer_title' ).text() );
		}
		$( '#ecpay_drawer_note' ).text( note ).toggleClass( 'is-dirty', dirty );
		$( '.ecpay-drawer' ).toggleClass( 'is-dirty', dirty );
	}

	/* show_if: reveal rows whose rules match the current values; hide sections left empty. */
	function applyShowIf() {
		var values = collect();
		$( '#ecpay_gwform [data-show-if]' ).each( function() {
			var rules = {}, show = true;
			try { rules = JSON.parse( $( this ).attr( 'data-show-if' ) ) || {}; } catch ( e ) { rules = {}; }
			$.each( rules, function( key, allowed ) {
				var current = values[ key ] == null ? '' : String( values[ key ] );
				if ( $.inArray( current, $.isArray( allowed ) ? allowed : [ String( allowed ) ] ) === -1 ) { show = false; }
			} );
			$( this ).prop( 'hidden', ! show );
		} );
		$( '#ecpay_gwform .ecpay-gwsec' ).each( function() {
			$( this ).prop( 'hidden', ! $( this ).find( '.ecpay-gwrow:not([hidden])' ).length );
		} );
	}

	function clearErrors( $row ) {
		( $row || $( '#ecpay_gwform .ecpay-gwrow.is-error' ) ).removeClass( 'is-error' ).find( '.ecpay-gwmsg' ).prop( 'hidden', true ).text( '' );
		( $row || $( '#ecpay_gwform' ) ).find( '[aria-invalid]' ).removeAttr( 'aria-invalid' );
	}
	function showErrors( errors ) {
		var $first = null;
		$.each( errors || {}, function( key, message ) {
			var $row = $( '#ecpay_gwform .ecpay-gwrow' ).filter( function() { return $( this ).attr( 'data-row' ) === key; } );
			if ( ! $row.length ) { return; }
			$row.prop( 'hidden', false ).addClass( 'is-error' ).closest( '.ecpay-gwsec' ).prop( 'hidden', false );
			$row.find( '.ecpay-gwmsg' ).text( message ).prop( 'hidden', false );
			$row.find( '.ecpay-in' ).attr( 'aria-invalid', 'true' );
			if ( ! $first ) { $first = $row; }
		} );
		if ( $first ) {
			$first[0].scrollIntoView( { behavior: 'smooth', block: 'center' } );
			$first.find( '.ecpay-in' ).first().trigger( 'focus' );
		}
	}

	function setBusy( on, $button ) {
		drawer.saving = on;
		var $buttons = $( '#ecpay_drawer_save, #ecpay_drawer_save_on' );
		$buttons.prop( 'disabled', on );
		$( '.ecpay-drawer' ).toggleClass( 'is-saving', on );
		if ( on && $button && $button.length ) {
			$button.data( 'label', $button.text() ).text( T.saving );
		} else if ( ! on ) {
			$buttons.each( function() { if ( $( this ).data( 'label' ) ) { $( this ).text( $( this ).data( 'label' ) ).removeData( 'label' ); } } );
		}
	}

	/* 6.0.3 ( D5 ): the panel's own controls stop saving as they change; the footer's Save ( the hidden save button, which posts
	   every field ) saves them together. Only a call the footer Save covers is held: the footer's own save function, and Stripe's
	   one-option save when the footer posts every Stripe option. Anything else keeps saving at once ( an older WP EasyCart PRO
	   panel whose control saves through another function, the Live / Test switches, which connect or select the gateway, and
	   the sync switch ). */
	function holdAutosaves( $body, footerFn ) {
		var names = [ footerFn ];
		if ( 'ec_admin_save_stripe_connect_options' === footerFn ) { names.push( 'ec_admin_update_stripe_connect_option' ); }
		var held = new RegExp( '(?:return\\s+)?(?:' + names.join( '|' ) + ')\\s*\\(\\s*(?:jQuery\\s*\\(\\s*this\\s*\\)|this)?\\s*\\)\\s*;?', 'g' );
		$body.find( 'input, select, textarea' ).not( '.ecsq-save, .ecpay-legacy-save, [type="button"], [type="submit"]' ).each( function() {
			var el = this;
			[ 'onchange', 'onclick', 'oninput' ].forEach( function( attr ) {
				var code = el.getAttribute( attr );
				if ( ! code ) { return; }
				var rest = code.replace( held, '' );
				if ( rest === code ) { return; }
				rest = rest.trim();
				if ( rest ) { el.setAttribute( attr, rest ); } else { el.removeAttribute( attr ); }
				el.setAttribute( 'data-ecpay-held', '1' );
			} );
		} );
	}

	/* 6.0.3: a panel with three or more groups ( Stripe, PayPal, Square with Pro ) shows one group at a time, under tabs. A group
	   keeps its own id ( PayPal's #ec_paypal_webhooks is redrawn by id ); one without an id gets one. */
	function markPanel( $g, i ) {
		$g.attr( { role: 'tabpanel', 'aria-labelledby': 'ecpay_tab_' + i, tabindex: '-1' } ).addClass( 'ecpay-tabpanel' );
	}
	function buildTabs( $body ) {
		var $groups = $body.find( '.ecsq-group' ).filter( function() { return this.style.display !== 'none' && $( this ).children( '.ecsq-group-t' ).length; } );
		var $tabs = $( '#ecpay_drawer_tabs' );
		if ( $groups.length < 3 ) { $tabs.prop( 'hidden', true ).empty(); return; }
		$tabs.empty().prop( 'hidden', false );
		$groups.each( function( i ) {
			var $g = $( this );
			if ( ! this.id ) { this.id = 'ecpay_tabpanel_' + i; }
			markPanel( $g, i );
			$( '<button type="button" role="tab" class="ecpay-tab"></button>' ).attr( { id: 'ecpay_tab_' + i, 'aria-controls': this.id, 'aria-selected': 'false', tabindex: '-1' } ).text( $.trim( $g.children( '.ecsq-group-t' ).first().text() ) ).appendTo( $tabs );
		} );
		$( '.ecpay-drawer' ).addClass( 'has-tabs' );
		selectTab( 0, false, false );
	}
	function selectTab( index, focus, keepScroll ) {
		var $tabs = $( '#ecpay_drawer_tabs .ecpay-tab' );
		if ( ! $tabs.length ) { return; }
		index = ( index + $tabs.length ) % $tabs.length;
		$tabs.each( function( i ) {
			var on = i === index, panel = document.getElementById( $( this ).attr( 'aria-controls' ) );
			$( this ).attr( { 'aria-selected': on ? 'true' : 'false', tabindex: on ? '0' : '-1' } ).toggleClass( 'is-on', on );
			if ( panel ) { markPanel( $( panel ), i ); $( panel ).toggleClass( 'is-tab-hidden', ! on ); }
		} );
		if ( focus ) { $tabs.eq( index ).trigger( 'focus' ); }
		if ( ! keepScroll ) { $( '#ecpay_drawer_body' ).scrollTop( 0 ); }
	}
	/* A panel that redraws one of its groups ( PayPal's Secure notifications ) gets the tab state back on the new markup. */
	function syncTabs() {
		var $on = $( '#ecpay_drawer_tabs .ecpay-tab.is-on' );
		if ( $on.length ) { selectTab( $on.index(), false, true ); }
	}
	$( document ).on( 'click.ecpay', '#ecpay_drawer_tabs .ecpay-tab', function() { selectTab( $( this ).index(), false, false ); } );
	$( document ).on( 'keydown.ecpay', '#ecpay_drawer_tabs .ecpay-tab', function( e ) {
		var i = $( this ).index(), n = $( '#ecpay_drawer_tabs .ecpay-tab' ).length;
		if ( e.key === 'ArrowRight' ) { e.preventDefault(); selectTab( i + 1, true, false ); }
		else if ( e.key === 'ArrowLeft' ) { e.preventDefault(); selectTab( i - 1, true, false ); }
		else if ( e.key === 'Home' ) { e.preventDefault(); selectTab( 0, true, false ); }
		else if ( e.key === 'End' ) { e.preventDefault(); selectTab( n - 1, true, false ); }
	} );

	function openDrawer( kind, key, title ) {
		closeDrawer( true );
		closeMenus();
		drawer.open = true;
		drawer.key = key;
		drawer.kind = kind;
		drawer.mode = '';
		drawer.initial = '';
		drawer.saving = false;
		drawer.state = null;
		drawer.selects = false;
		drawer.legacyWait = false;
		drawer.$legacySave = null;
		drawer.opener = document.activeElement;
		$( 'body' ).append( drawerHtml( title || key ) ).addClass( 'ecdrawer-open' );
		$( '.ecpay-drawer .ecdrawer-x, #ecpay_drawer_close, .ecpay-backdrop' ).on( 'click', requestClose );
		$( '#ecpay_drawer_save' ).on( 'click', function() { save( false, $( this ) ); } );
		$( '#ecpay_drawer_save_on' ).on( 'click', function() { save( true, $( this ) ); } );
		$( '.ecpay-drawer .ecdrawer-x' ).trigger( 'focus' );
		var action = kind === 'manual' ? 'ecv2_payment_manual_form' : 'ecv2_payment_gateway_form';
		post( action, { gateway: key }, function( d ) {
			if ( ! drawer.open || drawer.key !== key ) { return; }
			$( '#ecpay_drawer_title' ).text( d.title || title || key );
			if ( d.docs ) { $( '#ecpay_drawer_docs' ).attr( 'href', d.docs ).prop( 'hidden', false ); }
			var $body = $( '#ecpay_drawer_body' );
			$body.html( d.html || '' );
			if ( kind === 'manual' ) {
				drawer.mode = 'manual';
				$( '#ecpay_drawer_save' ).prop( 'hidden', false );
				$( '#ecpay_manual_title' ).trigger( 'focus' );
			} else if ( d.mode === 'v2' ) {
				drawer.mode = 'v2';
				applyShowIf();
				$( '#ecpay_drawer_save' ).prop( 'hidden', false );
				$body.find( '.ecpay-gwrow:not([hidden]) .ecpay-in' ).filter( ':visible' ).first().trigger( 'focus' );
			} else {
				bindLegacy( $body );
				drawer.selects = !! d.selects;
				/* A partial with exactly one "Save Options" button is saved from the footer instead. */
				var $saves = $body.find( 'input[type="submit"], input[type="button"], button' ).filter( function() { return /ec_admin_save_[a-z0-9_]+\s*\(/i.test( String( $( this ).attr( 'onclick' ) || '' ) ); } );
				if ( $saves.length === 1 ) {
					drawer.mode = 'legacy-single';
					drawer.$legacySave = $saves.first();
					$saves.addClass( 'ecpay-legacy-save' ).attr( { 'aria-hidden': 'true', tabindex: '-1' } );
					$( '#ecpay_drawer_save' ).prop( 'hidden', false );
					var footerFn = /ec_admin_save_[a-z0-9_]+/i.exec( String( $saves.first().attr( 'onclick' ) || '' ) );
					if ( footerFn ) { holdAutosaves( $body, footerFn[0] ); }
				} else {
					drawer.mode = 'legacy';
				}
				buildTabs( $body );
			}
			drawer.initial = snapshot();
			setChips( d.state || null );
			applyState( d.state || null );
			updateDirty();
		}, function( m ) {
			$( '#ecpay_drawer_body' ).html( '<div class="ecpay-drawer-error">' + esc( m || T.load_failed ) + '</div>' );
			toast( m || T.load_failed, 'error' );
		} );
	}

	function requestClose() {
		if ( ! drawer.open || drawer.saving || drawer.confirming ) { return; }
		if ( isDirty() ) {
			var key = drawer.key;
			drawer.confirming = true;
			confirmBox( T.confirm_leave, '' ).then( function( ok ) { drawer.confirming = false; if ( ok && drawer.open && drawer.key === key ) { closeDrawer(); } } );
			return;
		}
		closeDrawer();
	}

	function closeDrawer( silent ) {
		if ( ! drawer.open ) { return; }
		drawer.open = false;
		clearTimeout( drawer.legacyTimer );
		$( '.ecpay-drawer, .ecpay-backdrop' ).remove();
		$( 'body' ).removeClass( 'ecdrawer-open' );
		if ( ! silent && drawer.reload ) { reload( 100 ); return; }
		if ( ! silent && drawer.opener && document.body.contains( drawer.opener ) ) { try { drawer.opener.focus(); } catch ( e ) { /* gone */ } }
	}

	function save( enable, $button ) {
		if ( ! drawer.open || drawer.saving ) { return; }
		if ( drawer.mode === 'v2' && enable && drawer.state && drawer.state.swap ) {
			confirmBox( T.confirm_swap, '' ).then( function( ok ) { if ( ok && drawer.open ) { saveGateway( true, $button ); } } );
			return;
		}
		if ( drawer.mode === 'v2' ) { saveGateway( enable, $button ); }
		else if ( drawer.mode === 'manual' ) { saveManual( enable, $button ); }
		else if ( drawer.mode === 'legacy-single' ) { saveLegacy( $button ); }
	}

	function saveGateway( enable, $button ) {
		var key = drawer.key;
		clearErrors();
		setBusy( true, $button );
		$.post( V.ajax, {
			action: 'ecv2_payment_gateway_save',
			nonce: V.nonce,
			gateway: key,
			values: JSON.stringify( collect() ),
			enable: enable ? '1' : '0',
			wp_easycart_nonce: $( '#ecpay_gw_nonce' ).val()
		} ).done( function( r ) {
			var d = ( r && r.data ) || {};
			if ( r && r.success ) {
				replaceSections( d.sections );
				if ( drawer.open && drawer.key === key ) {
					drawer.initial = snapshot();
					applyState( d.state );
				}
				toast( d.message || T.saved, 'success' );
				if ( d.warning ) { toast( d.warning, 'error' ); }
			} else {
				if ( drawer.open && drawer.key === key ) { showErrors( d.errors ); }
				toast( d.message || T.request_failed, 'error' );
			}
		} ).fail( function( xhr ) {
			var j = xhr && xhr.responseJSON;
			toast( ( j && j.data && j.data.message ) ? j.data.message : T.request_failed, 'error' );
		} ).always( function() { setBusy( false ); } );
	}

	/* 6.0.2: the roles Bill later is offered to ( none ticked = everyone ). */
	function manualRoles() {
		return $( '#ecpay_manual_roles .ecpay-manual-role:checked' ).map( function() { return String( this.value ); } ).get();
	}

	function saveManual( enable, $button ) {
		setBusy( true, $button );
		post( 'ecv2_payment_manual_save', { title: $( '#ecpay_manual_title' ).val(), message: $( '#ecpay_manual_message' ).val(), roles: manualRoles(), roles_sent: 1, enable: enable ? '1' : '0' }, function( d ) {
			setBusy( false );
			toast( d.message || T.saved, 'success' );
			drawer.initial = snapshot();
			replaceSections( d.sections );
			if ( d.state ) { applyState( d.state ); } else { updateDirty(); }
		}, function( m ) {
			setBusy( false );
			toast( m, 'error' );
		} );
	}

	/* Runs the partial's own save function ( its onclick ); the ajaxComplete listener below reports the result. */
	function saveLegacy( $button ) {
		if ( ! drawer.$legacySave || ! drawer.$legacySave.length ) { return; }
		setBusy( true, $button );
		drawer.legacyWait = true;
		clearTimeout( drawer.legacyTimer );
		drawer.legacyTimer = setTimeout( function() { if ( drawer.legacyWait ) { drawer.legacyWait = false; setBusy( false ); } }, 15000 );
		drawer.$legacySave[0].click();
	}

	/* payment.js binds its Stripe currency / country notes on document ready only; the partial arrives later. */
	function bindLegacy( $body ) {
		$body.on( 'change', '#ec_option_stripe_currency, #ec_option_stripe_company_country', function() {
			var cur = $( '#ec_option_stripe_currency' ).val(), curNote = $( '#stripe_account_currency_note' );
			var cty = $( '#ec_option_stripe_company_country' ).val(), ctyNote = $( '#stripe_account_country_note' );
			if ( curNote.length ) { curNote.toggle( String( cur ) !== String( curNote.attr( 'data-currency' ) ) ); }
			if ( ctyNote.length ) { ctyNote.toggle( String( cty ) !== String( ctyNote.attr( 'data-country' ) ) ); }
		} );
		$body.on( 'input change', 'input, select, textarea', updateDirty );
	}

	/* Declared form: toggles, pills, show_if, dirty state, errors clear as the field changes. */
	$( document ).on( 'input.ecpay change.ecpay', '#ecpay_gwform .ecpay-in', function( e ) {
		var $i = $( this ), $row = $i.closest( '.ecpay-gwrow' );
		if ( $i.is( ':checkbox' ) ) { $i.closest( '.ecst-toggle' ).toggleClass( 'is-on', $i.is( ':checked' ) ); }
		if ( $i.is( ':radio' ) ) { $row.find( '.ecst-pill' ).each( function() { $( this ).toggleClass( 'is-on', $( this ).find( 'input' ).is( ':checked' ) ); } ); }
		if ( $row.hasClass( 'is-error' ) ) { clearErrors( $row ); }
		if ( e.type === 'change' || $i.is( 'select, :checkbox, :radio' ) ) { applyShowIf(); }
		updateDirty();
	} );
	$( document ).on( 'click.ecpay', '#ecpay_gwform .ecpay-reveal', function() {
		var $b = $( this ), $field = $b.closest( '.ecpay-secret' ).find( 'input, textarea' ).first(), show = $b.attr( 'aria-pressed' ) !== 'true';
		if ( $field.is( 'input' ) ) { $field.attr( 'type', show ? 'text' : 'password' ); } else { $b.closest( '.ecpay-secret' ).toggleClass( 'is-masked', ! show ); }
		$b.attr( 'aria-pressed', show ? 'true' : 'false' ).text( show ? T.hide : T.show );
	} );
	$( document ).on( 'click.ecpay', '#ecpay_gwform .ecpay-copy-btn', function() {
		var $b = $( this ), $field = $b.closest( '.ecpay-copy' ).find( 'input' ), text = String( $field.val() || '' );
		var done = function() { $b.text( T.copied ); setTimeout( function() { $b.text( T.copy ); }, 1600 ); };
		if ( window.navigator && navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard.writeText( text ).then( done, function() { $field.trigger( 'select' ); } );
		} else {
			$field.trigger( 'select' );
			try { if ( document.execCommand( 'copy' ) ) { done(); } } catch ( err ) { /* select stays for a manual copy */ }
		}
	} );
	$( document ).on( 'keydown.ecpay', '#ecpay_gwform input.ecpay-in', function( e ) {
		if ( e.key === 'Enter' ) { e.preventDefault(); save( false, $( '#ecpay_drawer_save' ) ); }
	} );
	$( document ).on( 'submit.ecpay', '#ecpay_gwform', function( e ) { e.preventDefault(); } );
	$( document ).on( 'input.ecpay', '#ecpay_manual_title, #ecpay_manual_message', updateDirty );
	$( document ).on( 'change.ecpay', '#ecpay_manual_roles .ecpay-manual-role', function() {
		$( this ).closest( '.ecst-pill' ).toggleClass( 'is-on', this.checked );
		updateDirty();
	} );

	/* Any legacy save from inside the drawer: report the footer save, refresh the lines in place. */
	$( document ).on( 'ajaxComplete.ecpay', function( e, xhr, settings ) {
		if ( ! drawer.open || ! settings ) { return; }
		syncTabs();
		var data = typeof settings.data === 'string' ? settings.data : '';
		if ( ! /(^|&)action=ec_admin_ajax_save_/.test( data ) ) { return; }
		var response = xhr ? xhr.responseJSON : null;
		if ( ! response && xhr && xhr.responseText && xhr.responseText.charAt( 0 ) === '{' ) {
			try { response = JSON.parse( xhr.responseText ); } catch ( err ) { response = null; }
		}
		var ok = ! ( response && response.success === false ) && ! ( xhr && xhr.status >= 400 );
		var wasWaiting = drawer.legacyWait;
		if ( drawer.legacyWait ) {
			drawer.legacyWait = false;
			clearTimeout( drawer.legacyTimer );
			setBusy( false );
			toast( ok ? T.saved : T.request_failed, ok ? 'success' : 'error' );
		}
		if ( ok ) {
			/* The panel saves ( footer Save, the Live / Test switches ) post every field; a one-option save marks only its field
			   saved; another call ( PayPal's notification buttons ) saves no field, so what was typed stays unsaved. */
			if ( /(?:^|&)update_var=/.test( data ) ) {
				drawer.initial = savedSnapshot( data );
			} else if ( wasWaiting || /(?:^|&)action=ec_admin_ajax_save_(?:square_free|square_pro|paypal|stripe_connect|stripe)(?:&|$)/.test( data ) ) {
				drawer.initial = snapshot();
			}
			refreshState();
		}
		updateDirty();
	} );
	$( document ).on( 'keydown.ecpay', function( e ) {
		if ( ! drawer.open ) { return; }
		if ( e.key === 'Escape' ) { e.preventDefault(); requestClose(); return; }
		if ( ( e.ctrlKey || e.metaKey ) && ( e.key === 's' || e.key === 'S' ) && ! $( '#ecpay_drawer_save' ).prop( 'hidden' ) ) { e.preventDefault(); save( false, $( '#ecpay_drawer_save' ) ); }
	} );

	/* ================================================================== */
	/* Lines: the ⋯ menus                                                  */
	/* ================================================================== */
	function closeMenus( $except ) {
		$wrap.find( '.ecpay-menu' ).not( $except || [] ).each( function() {
			$( this ).prop( 'hidden', true );
			$wrap.find( '[aria-controls="' + this.id + '"]' ).attr( 'aria-expanded', 'false' );
		} );
	}
	function menuItems( $menu ) { return $menu.find( '[role="menuitem"]' ).filter( function() { return ! this.hidden; } ); }
	$wrap.on( 'click', '.ecpay-menu-btn', function( e ) {
		e.stopPropagation();
		var $b = $( this ), $menu = $( '#' + $b.attr( 'aria-controls' ) ), open = $menu.prop( 'hidden' );
		closeMenus( $menu );
		$menu.prop( 'hidden', ! open );
		$b.attr( 'aria-expanded', open ? 'true' : 'false' );
		if ( open ) { menuItems( $menu ).first().trigger( 'focus' ); }
	} );
	$wrap.on( 'keydown', '.ecpay-menu-btn', function( e ) {
		if ( e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ' ) {
			var $menu = $( '#' + $( this ).attr( 'aria-controls' ) );
			if ( $menu.prop( 'hidden' ) ) { e.preventDefault(); $( this ).trigger( 'click' ); }
		}
	} );
	$wrap.on( 'keydown', '.ecpay-menu', function( e ) {
		var $menu = $( this ), $items = menuItems( $menu ), i = $items.index( document.activeElement );
		if ( e.key === 'Escape' ) { e.preventDefault(); e.stopPropagation(); closeMenus(); $wrap.find( '[aria-controls="' + this.id + '"]' ).trigger( 'focus' ); }
		else if ( e.key === 'ArrowDown' ) { e.preventDefault(); $items.eq( ( i + 1 ) % $items.length ).trigger( 'focus' ); }
		else if ( e.key === 'ArrowUp' ) { e.preventDefault(); $items.eq( ( i - 1 + $items.length ) % $items.length ).trigger( 'focus' ); }
		else if ( e.key === 'Home' ) { e.preventDefault(); $items.first().trigger( 'focus' ); }
		else if ( e.key === 'End' ) { e.preventDefault(); $items.last().trigger( 'focus' ); }
		else if ( e.key === 'Tab' ) { closeMenus(); }
	} );
	$wrap.on( 'click', '.ecpay-menu-item', function() { closeMenus(); } );
	$( document ).on( 'click.ecpay', function( e ) { if ( ! $( e.target ).closest( '.ecpay-menu-wrap' ).length ) { closeMenus(); } } );

	/* ================================================================== */
	/* Actions on lines, suggestions and gateway tiles                      */
	/* ================================================================== */
	$wrap.on( 'click', '[data-ecpay-open]', function() {
		var key = String( $( this ).data( 'ecpay-open' ) );
		var $host = $( this ).closest( '.ecpay-line, .ecpay-tile' );
		var title = $host.length ? $.trim( $host.find( '.ecpay-line-text > strong, .ecpay-tile-text > b' ).first().clone().find( '.ecpay-rec' ).remove().end().text() ) : '';
		openDrawer( 'gateway', key, title );
	} );
	$wrap.on( 'click', '[data-ecpay-manual]', function() {
		openDrawer( 'manual', 'manual', T.manual_title );
	} );

	/* 6.0.3 ( D6 ): turning a gateway on or off and switching test mode redraw the page in place. */
	function switchGateway( $b, data, confirmText ) {
		var run = function() {
			$b.prop( 'disabled', true ).addClass( 'is-busy' );
			post( 'ecv2_payment_toggle', data, function( d ) {
				toast( d.message || '', 'success' );
				if ( d.sections ) { replaceSections( d.sections ); } else { reload(); }
			}, function( m ) { $b.prop( 'disabled', false ).removeClass( 'is-busy' ); toast( m, 'error' ); } );
		};
		if ( confirmText ) { confirmBox( confirmText, '' ).then( function( ok ) { if ( ok ) { run(); } } ); } else { run(); }
	}
	$wrap.on( 'click', '[data-ecpay-toggle]', function() {
		var $b = $( this ), on = String( $b.data( 'on' ) ) === '1';
		var confirmText = ! on ? T.confirm_off : ( String( $b.data( 'swap' ) ) === '1' ? T.confirm_swap : '' );
		switchGateway( $b, { gateway: $b.data( 'ecpay-toggle' ), on: on ? '1' : '0' }, confirmText );
	} );
	$wrap.on( 'click', '[data-ecpay-mode]', function() {
		var $b = $( this );
		switchGateway( $b, { gateway: $b.data( 'ecpay-mode' ), mode: $b.data( 'mode' ) }, String( $b.data( 'mode' ) ) === 'live' ? T.confirm_live : '' );
	} );
	/* Disconnect: legacy GET handler; confirm, then navigate. */
	$wrap.on( 'click', '[data-ecpay-confirm]', function( e ) {
		e.preventDefault();
		var href = $( this ).attr( 'href' ), text = $( this ).data( 'ecpay-confirm' );
		confirmBox( text, '' ).then( function( ok ) { if ( ok ) { toast( T.working, 'info' ); window.location.href = href; } } );
	} );
	/* 6.0.2: the same inside the drawer, which sits on body outside $wrap ( the PayPal panel's Disconnect ). */
	$( document ).on( 'click.ecpay', '.ecpay-drawer [data-ecpay-confirm]', function( e ) {
		e.preventDefault();
		var href = $( this ).attr( 'href' ), text = $( this ).data( 'ecpay-confirm' );
		drawer.confirming = true;
		confirmBox( text, '' ).then( function( ok ) { drawer.confirming = false; if ( ok ) { toast( T.working, 'info' ); window.location.href = href; } } );
	} );
	$wrap.on( 'click', '[data-ecpay-nav]', function() { toast( T.working, 'info' ); } );
	/* "Replace with another gateway" and an empty line's "All card gateways": the list, filtered to that way to pay. */
	$wrap.on( 'click', '[data-ecpay-choose]', function() {
		var role = String( $( this ).data( 'ecpay-choose' ) );
		if ( ! $( '#ecpay_catalog' ).length ) { return; }
		setFilter( $( '.ecpay-filter[data-ecpay-filter="' + role + '"]' ).length ? role : 'all' );
		$( '#ecpay_search' ).val( '' );
		applyCatalog();
		$( '#ecst-sec-more' )[0].scrollIntoView( { behavior: 'smooth', block: 'start' } );
		setTimeout( function() { $( '#ecpay_search' ).trigger( 'focus' ); }, 350 );
	} );
	/* A locked tile: the whole tile opens the short preview ( its lock button does for keyboards ). */
	$wrap.on( 'click', '.ecpay-tile.is-locked', function( e ) {
		if ( $( e.target ).closest( 'button, a' ).length ) { return; }
		$( this ).find( '.ecpay-lock-btn' ).trigger( 'click' );
	} );

	/* Test-mode line: every selected gateway in test mode goes live where it can. */
	$wrap.on( 'click', '[data-ecpay-test-off]', function() {
		var $b = $( this );
		confirmBox( T.confirm_all_live, '' ).then( function( ok ) {
			if ( ! ok ) { return; }
			$b.prop( 'disabled', true );
			post( 'ecv2_payment_test_off', {}, function( d ) {
				toast( d.message || '', 'success' );
				replaceSections( d.sections );
			}, function( m ) { $b.prop( 'disabled', false ); toast( m, 'error' ); } );
		} );
	} );

	/* ================================================================== */
	/* The summary beside the page title                                    */
	/* ================================================================== */
	function placeSummary() {
		var $sum = $( '#ecst-sec-active #ecpay_summary' ), $right = $wrap.find( '.ecst-header-right' ).first();
		if ( ! $sum.length || ! $right.length ) { return; }
		$right.find( '#ecpay_summary' ).not( $sum ).remove();
		$sum.addClass( 'is-header' ).prependTo( $right );
	}
	placeSummary();

	/* ================================================================== */
	/* EasyCart Connect terms ( Free ): asked when a Connect link is used   */
	/* ================================================================== */
	var pendingHref = '';
	function termsDialog( href ) {
		pendingHref = href || '';
		$( '#ecpay_terms' ).remove();
		var agree = esc( T.terms_agree ).replace( '%1$s', '<a href="' + esc( V.terms_url ) + '" target="_blank" rel="noopener noreferrer">' ).replace( '%2$s', '</a>' );
		var $d = $( '<div class="ecpay-terms-layer" id="ecpay_terms"><div class="ecpay-terms-box" role="dialog" aria-modal="true" aria-labelledby="ecpay_terms_title">' +
			'<h3 id="ecpay_terms_title">' + esc( T.terms_title ) + '</h3>' +
			'<p>' + esc( T.terms_text ) + '</p>' +
			'<label class="ecpay-terms-row"><input type="checkbox" id="ecpay_terms_agree" value="1" /><span>' + agree + '</span></label>' +
			'<div class="ecpay-terms-act"><button type="button" class="ecv2-btn" id="ecpay_terms_cancel">' + esc( T.cancel ) + '</button><button type="button" class="ecv2-btn ecv2-btn-primary" id="ecpay_terms_accept" disabled>' + esc( T.terms_accept ) + '</button></div>' +
			'</div></div>' ).appendTo( 'body' );
		$d.find( '#ecpay_terms_agree' ).trigger( 'focus' );
	}
	function closeTerms() { $( '#ecpay_terms' ).remove(); pendingHref = ''; }
	$wrap.on( 'click', '.ecpay-connect[aria-disabled="true"]', function( e ) {
		e.preventDefault();
		closeMenus();
		termsDialog( String( $( this ).data( 'href' ) || '' ) );
	} );
	$wrap.on( 'click', '.ecpay-connect:not([aria-disabled="true"])', function() { toast( T.opening, 'info' ); } );
	$( document ).on( 'change.ecpay', '#ecpay_terms_agree', function() { $( '#ecpay_terms_accept' ).prop( 'disabled', ! this.checked ); } );
	$( document ).on( 'click.ecpay', '#ecpay_terms_cancel', closeTerms );
	$( document ).on( 'click.ecpay', '#ecpay_terms', function( e ) { if ( e.target === this ) { closeTerms(); } } );
	$( document ).on( 'keydown.ecpay', '#ecpay_terms', function( e ) { if ( e.key === 'Escape' ) { e.preventDefault(); closeTerms(); } } );
	$( document ).on( 'click.ecpay', '#ecpay_terms_accept', function() {
		if ( ! $( '#ecpay_terms_agree' ).is( ':checked' ) ) { return; }
		var href = pendingHref;
		$( this ).prop( 'disabled', true ).text( T.working );
		$.post( V.ajax, { action: 'ec_admin_ajax_save_terms_accepted', wp_easycart_nonce: V.terms_nonce } ).done( function() {
			$wrap.find( '.ecpay-connect[aria-disabled="true"]' ).each( function() { $( this ).attr( 'href', $( this ).data( 'href' ) ).removeAttr( 'aria-disabled' ); } );
			if ( href ) { toast( T.opening, 'info' ); window.location.href = href; } else { closeTerms(); }
		} ).fail( function() {
			toast( T.request_failed, 'error' );
			$( '#ecpay_terms_accept' ).prop( 'disabled', false ).text( T.terms_accept );
		} );
	} );

	/* ================================================================== */
	/* The gateway list: search, filters, older gateways                    */
	/* ================================================================== */
	var olderKey = 'ecpay_older_open_' + ( V.user || 0 );
	function setFilter( f ) {
		$( '.ecpay-filter' ).each( function() {
			var on = String( $( this ).data( 'ecpay-filter' ) ) === f;
			$( this ).toggleClass( 'is-on', on ).attr( 'aria-pressed', on ? 'true' : 'false' );
		} );
		$( '#ecpay_catalog' ).attr( 'data-filter', f );
	}
	function catalogState() {
		return { filter: String( $( '#ecpay_catalog' ).attr( 'data-filter' ) || 'recommended' ), q: String( $( '#ecpay_search' ).val() || '' ), focus: document.activeElement && document.activeElement.id === 'ecpay_search' };
	}
	function restoreCatalog( s ) {
		if ( ! $( '#ecpay_catalog' ).length ) { return; }
		if ( s ) {
			if ( $( '.ecpay-filter[data-ecpay-filter="' + s.filter + '"]' ).length ) { setFilter( s.filter ); }
			$( '#ecpay_search' ).val( s.q );
		}
		applyCatalog();
		if ( s && s.focus ) { $( '#ecpay_search' ).trigger( 'focus' ); }
	}
	function applyCatalog() {
		var $cat = $( '#ecpay_catalog' );
		if ( ! $cat.length ) { return; }
		var f = String( $cat.attr( 'data-filter' ) || 'recommended' );
		var q = $.trim( String( $( '#ecpay_search' ).val() || '' ) ).toLowerCase();
		var olderOpen = read( olderKey ) === '1';
		var shown = 0, olderInFilter = 0;
		$( '#ecpay_tiles .ecpay-tile' ).each( function() {
			var $t = $( this ), show;
			var inFilter = f === 'all' || ( f === 'recommended' ? $t.attr( 'data-rec' ) === '1' : $t.attr( 'data-role' ) === f );
			if ( q ) {
				show = String( $t.attr( 'data-search' ) || '' ).indexOf( q ) !== -1;
			} else if ( $t.attr( 'data-older' ) === '1' && f !== 'recommended' ) {
				if ( inFilter ) { olderInFilter++; }
				show = inFilter && olderOpen;
			} else {
				show = inFilter;
			}
			$t.prop( 'hidden', ! show );
			if ( show ) { shown++; }
		} );
		var $toggle = $( '#ecpay_older_toggle' );
		if ( ! q && olderInFilter > 0 ) {
			$toggle.prop( 'hidden', false ).attr( 'aria-expanded', olderOpen ? 'true' : 'false' ).html( esc( olderOpen ? T.older_hide : T.older_show ) + ( olderOpen ? '' : ' <em>' + olderInFilter + '</em>' ) );
		} else {
			$toggle.prop( 'hidden', true );
		}
		var $empty = $( '#ecpay_cat_empty' );
		if ( shown ) {
			$empty.prop( 'hidden', true ).text( '' );
		} else if ( q ) {
			$empty.prop( 'hidden', false ).text( String( T.no_match || '' ).replace( '%s', q ) );
		} else if ( olderInFilter ) {
			$empty.prop( 'hidden', true ).text( '' );
		} else {
			$empty.prop( 'hidden', false ).text( f === 'recommended' ? T.rec_done : T.none_left );
		}
	}
	$wrap.on( 'click', '.ecpay-filter', function() { setFilter( String( $( this ).data( 'ecpay-filter' ) ) ); applyCatalog(); } );
	$wrap.on( 'input search', '#ecpay_search', applyCatalog );
	$wrap.on( 'keydown', '#ecpay_search', function( e ) { if ( e.key === 'Escape' && this.value ) { e.preventDefault(); this.value = ''; applyCatalog(); } } );
	$wrap.on( 'click', '#ecpay_older_toggle', function() {
		store( olderKey, read( olderKey ) === '1' ? '0' : '1' );
		applyCatalog();
	} );
	applyCatalog();

	/* Deep link from settings search ( ?gateway=<key> ): bring that gateway into view — its line first, then its tile in
	 * the list ( found by search when a filter or the older group hides it ) — and flash it. */
	( function() {
		var m = /[?&]gateway=([a-z0-9_\-]+)/i.exec( window.location.search );
		if ( ! m ) { return; }
		var key = m[1].toLowerCase(), $t = $wrap.find( '.ecpay-line[data-gateway="' + key + '"]' );
		if ( ! $t.length ) {
			$t = $wrap.find( '.ecpay-tile[data-gateway="' + key + '"]' );
			if ( $t.length && $t.prop( 'hidden' ) ) {
				setFilter( 'all' );
				$( '#ecpay_search' ).val( $.trim( $t.find( '.ecpay-tile-text > b' ).first().clone().find( '.ecpay-rec' ).remove().end().text() ) );
				applyCatalog();
			}
		}
		if ( ! $t.length ) { return; }
		$t.addClass( 'ecst-flash' );
		setTimeout( function() { $t[0].scrollIntoView( { behavior: 'smooth', block: 'center' } ); }, 80 );
		setTimeout( function() { $t.removeClass( 'ecst-flash' ); }, 2600 );
	} )();

	window.ecpay = { open: function( key, title ) { openDrawer( 'gateway', key, title ); }, openManual: function() { openDrawer( 'manual', 'manual', T.manual_title ); }, close: requestClose, refresh: refreshState };
} )( jQuery );
