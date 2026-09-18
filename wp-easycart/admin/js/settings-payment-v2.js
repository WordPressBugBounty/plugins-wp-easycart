/* WP EasyCart Admin — Settings › Payment ( V2 ): gateway cards, test-mode banner, terms gate, the collapsed
   gateway list and the drawer. The drawer hosts a declared gateway form ( V2 fields, saved from the footer through
   ecv2_payment_gateway_save ), a legacy per-gateway partial ( driven by admin/js/payment.js; a partial with one
   Save Options button is saved from the footer too ) or the small Bill later wording form. Saves refresh the
   cards in place ( ecv2_payment_state ) instead of reloading the page. */
( function( $ ) {
	'use strict';

	var V = window.ecpay_vars || { ajax: window.ajaxurl, nonce: '', page: 'payment', user: 0, i18n: {} };
	var T = V.i18n || {};
	var $wrap = $( '#ecst' );
	if ( ! $wrap.length || $wrap.data( 'page' ) !== V.page ) { return; }

	function toast( msg, kind ) {
		if ( typeof window.ecv2_toast === 'function' ) { return window.ecv2_toast( msg, kind || 'success' ); }
		var $t = $( '<div class="ecdv2-toast" style="position:fixed;bottom:24px;right:24px;z-index:99999;background:#111827;color:#fff;padding:10px 14px;border-radius:8px;font-size:13px"></div>' ).text( msg ).appendTo( 'body' );
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
	var drawer = { open: false, key: '', kind: '', mode: '', role: '', initial: '', saving: false, state: null, selects: false, legacyWait: false, legacyTimer: 0, reload: false, opener: null, $legacySave: null };

	function drawerHtml( title ) {
		return '<div class="ecdrawer-backdrop ecpay-backdrop"></div>' +
			'<aside class="ecdrawer ecpay-drawer" role="dialog" aria-modal="true" aria-labelledby="ecpay_drawer_title">' +
				'<div class="ecdrawer-h"><h3 id="ecpay_drawer_title">' + esc( title ) + '</h3><span class="ecpay-drawer-chips" id="ecpay_drawer_chips"></span><a class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" id="ecpay_drawer_docs" href="#" target="_blank" rel="noopener noreferrer" hidden>' + esc( T.docs ) + ' &#8599;</a><button type="button" class="ecdrawer-x" aria-label="' + esc( T.close ) + '">&times;</button></div>' +
				'<div class="ecdrawer-b" id="ecpay_drawer_body"><div class="ecpay-drawer-loading">' + esc( T.loading ) + '</div></div>' +
				'<div class="ecdrawer-f"><span class="ecpay-drawer-note" id="ecpay_drawer_note" aria-live="polite"></span><span class="ecst-grow"></span>' +
					'<button type="button" class="ecv2-btn" id="ecpay_drawer_close">' + esc( T.close ) + '</button>' +
					'<button type="button" class="ecv2-btn ecv2-btn-primary" id="ecpay_drawer_save" hidden>' + esc( T.save ) + '</button>' +
					'<button type="button" class="ecv2-btn ecv2-btn-primary" id="ecpay_drawer_save_on" hidden>' + esc( T.save_on ) + '</button>' +
				'</div>' +
			'</aside>';
	}

	function setChips( state ) {
		var h = '';
		if ( drawer.role ) { h += '<span class="ecv2-chip ecv2-chip-gray">' + esc( drawer.role ) + '</span>'; }
		if ( state && state.state_label ) { h += '<span class="ecv2-chip ' + esc( state.state_class || 'ecv2-chip-gray' ) + '">' + esc( state.state_label ) + '</span>'; }
		if ( state && state.mode_label ) { h += '<span class="ecv2-chip ' + ( state.test ? 'ecv2-chip-amber' : 'ecv2-chip-brand' ) + '">' + esc( state.mode_label ) + '</span>'; }
		$( '#ecpay_drawer_chips' ).html( h );
	}

	/* Header chips and footer buttons follow the gateway's saved state. */
	function applyState( state ) {
		if ( ! state ) { return; }
		drawer.state = state;
		setChips( state );
		if ( drawer.mode === 'v2' ) {
			var canOn = !! state.can_enable;
			$( '#ecpay_drawer_save_on' ).prop( 'hidden', ! canOn );
			$( '#ecpay_drawer_save' ).toggleClass( 'ecv2-btn-primary', ! canOn );
		}
		if ( state.enabled ) { drawer.selects = false; }
		updateDirty();
	}

	/* Re-render the cards and the "More gateways" list in place ( falls back to a reload on close ). */
	function replaceSections( sections ) {
		if ( ! sections ) { return; }
		var $active = $( '#ecst-sec-active .ecst-custom' ), $more = $( '#ecst-sec-more .ecst-custom' );
		if ( ! $active.length || ! $more.length ) { drawer.reload = true; return; }
		if ( typeof sections.active === 'string' ) { $active.html( sections.active ); }
		if ( typeof sections.more === 'string' ) {
			$more.html( sections.more );
			if ( $( '#ecpay_more_list' ).length && read( moreKey ) === '1' ) { setMoreOpen( true ); }
		}
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
	function snapshot() {
		if ( drawer.mode === 'v2' ) { return JSON.stringify( collect() ); }
		if ( drawer.mode === 'manual' ) { return String( $( '#ecpay_manual_title' ).val() ) + '' + String( $( '#ecpay_manual_message' ).val() ); }
		if ( drawer.mode === 'legacy-single' ) {
			return legacyFields().map( function() { return $( this ).is( ':checkbox, :radio' ) ? ( this.checked ? '1' : '0' ) : String( $( this ).val() ); } ).get().join( '' );
		}
		return '';
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

	function openDrawer( kind, key, title ) {
		closeDrawer( true );
		drawer.open = true;
		drawer.key = key;
		drawer.kind = kind;
		drawer.mode = '';
		drawer.role = '';
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
			drawer.role = d.role || '';
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
				} else {
					drawer.mode = 'legacy';
				}
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
		else if ( drawer.mode === 'manual' ) { saveManual( $button ); }
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

	function saveManual( $button ) {
		setBusy( true, $button );
		post( 'ecv2_payment_manual_save', { title: $( '#ecpay_manual_title' ).val(), message: $( '#ecpay_manual_message' ).val() }, function( d ) {
			setBusy( false );
			toast( d.message || T.saved, 'success' );
			drawer.initial = snapshot();
			updateDirty();
			refreshState();
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
		var $b = $( this ), $wrap = $b.closest( '.ecpay-secret' ), $field = $wrap.find( 'input, textarea' ).first(), show = $b.attr( 'aria-pressed' ) !== 'true';
		if ( $field.is( 'input' ) ) { $field.attr( 'type', show ? 'text' : 'password' ); } else { $wrap.toggleClass( 'is-masked', ! show ); }
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

	/* Any legacy save from inside the drawer: report the footer save, refresh the cards in place. */
	$( document ).on( 'ajaxComplete.ecpay', function( e, xhr, settings ) {
		if ( ! drawer.open || ! settings ) { return; }
		var data = typeof settings.data === 'string' ? settings.data : '';
		if ( ! /(^|&)action=ec_admin_ajax_save_/.test( data ) ) { return; }
		var response = xhr ? xhr.responseJSON : null;
		if ( ! response && xhr && xhr.responseText && xhr.responseText.charAt( 0 ) === '{' ) {
			try { response = JSON.parse( xhr.responseText ); } catch ( err ) { response = null; }
		}
		var ok = ! ( response && response.success === false ) && ! ( xhr && xhr.status >= 400 );
		if ( drawer.legacyWait ) {
			drawer.legacyWait = false;
			clearTimeout( drawer.legacyTimer );
			setBusy( false );
			if ( ok ) { toast( T.saved, 'success' ); }
		}
		if ( ok ) {
			/* The legacy save functions post the whole partial ( also when a select saves on change ). */
			drawer.initial = snapshot();
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
	/* Cards + list                                                        */
	/* ================================================================== */
	$wrap.on( 'click', '[data-ecpay-open]', function() {
		var key = String( $( this ).data( 'ecpay-open' ) );
		var $partner = $( this ).closest( '.ecpay-partner' );
		var title = $partner.length ? String( $partner.data( 'label' ) || '' ) : $( this ).closest( '.ecpay-card, .ecpay-more-row' ).find( 'h4, .ecpay-more-text b' ).first().clone().find( '.ecst-pro-tag, .ecv2-cl-pro-badge, [class*="pro-tag"], [class*="pro-chip"]' ).remove().end().text().trim();
		openDrawer( 'gateway', key, title );
	} );
	$wrap.on( 'click', '[data-ecpay-manual]', function() {
		openDrawer( 'manual', 'manual', T.manual_title );
	} );

	function switchGateway( $b, data, confirmText ) {
		var run = function() {
			$b.prop( 'disabled', true ).addClass( 'is-busy' );
			toast( T.working, 'info' );
			post( 'ecv2_payment_toggle', data, function( d ) {
				toast( d.message || '', 'success' );
				if ( d.reload ) { reload(); } else { $b.prop( 'disabled', false ).removeClass( 'is-busy' ); }
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
		switchGateway( $b, { gateway: $b.data( 'ecpay-mode' ), mode: $b.data( 'mode' ) }, '' );
	} );
	/* Disconnect: legacy GET handler; confirm, then navigate. */
	$wrap.on( 'click', '[data-ecpay-confirm]', function( e ) {
		e.preventDefault();
		var href = $( this ).attr( 'href' ), text = $( this ).data( 'ecpay-confirm' );
		confirmBox( text, '' ).then( function( ok ) { if ( ok ) { toast( T.working, 'info' ); window.location.href = href; } } );
	} );
	$wrap.on( 'click', '[data-ecpay-nav]', function() { toast( T.working, 'info' ); } );
	$wrap.on( 'click', '[data-ecpay-choose]', function() {
		var role = String( $( this ).data( 'ecpay-choose' ) );
		var $more = $( '#ecpay_more' );
		if ( ! $more.length ) { return; }
		setMoreOpen( true );
		var $pill = $more.find( '.ecpay-pill[data-ecpay-filter="' + role + '"]' );
		( $pill.length ? $pill : $more.find( '.ecpay-pill[data-ecpay-filter="all"]' ) ).trigger( 'click' );
		$more[0].scrollIntoView( { behavior: 'smooth', block: 'start' } );
	} );
	/* "Change gateway" on a filled card: the same partner-first chooser an empty slot shows. */
	$wrap.on( 'click', '[data-ecpay-change]', function() {
		var role = String( $( this ).data( 'ecpay-change' ) );
		var $chooser = $( '#ecpay_chooser_' + role );
		if ( ! $chooser.length ) { return; }
		var open = $chooser.prop( 'hidden' );
		$chooser.prop( 'hidden', ! open );
		$wrap.find( '[data-ecpay-change="' + role + '"]' ).attr( 'aria-expanded', open ? 'true' : 'false' );
		$( '.ecpay-card[data-role="' + role + '"]' ).toggleClass( 'is-changing', open );
		if ( open ) {
			$chooser[0].scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
			$chooser.find( '.ecpay-chooser-titles h4' ).trigger( 'focus' );
		}
	} );
	$wrap.on( 'click', '[data-ecpay-change-cancel]', function() {
		var role = String( $( this ).data( 'ecpay-change-cancel' ) );
		$( '#ecpay_chooser_' + role ).prop( 'hidden', true );
		$( '.ecpay-card[data-role="' + role + '"]' ).removeClass( 'is-changing' );
		$wrap.find( '[data-ecpay-change="' + role + '"]' ).attr( 'aria-expanded', 'false' ).first().trigger( 'focus' );
	} );
	$wrap.on( 'click', '.ecpay-pill', function() {
		var f = String( $( this ).data( 'ecpay-filter' ) ), any = false;
		$( '.ecpay-pill' ).removeClass( 'is-on' );
		$( this ).addClass( 'is-on' );
		$( '.ecpay-more-group' ).each( function() {
			var show = f === 'all' || $( this ).data( 'role' ) === f;
			$( this ).toggleClass( 'is-hidden', ! show );
			if ( show ) { any = true; }
		} );
		$( '#ecpay_more_empty' ).prop( 'hidden', any );
	} );

	/* Collapsed "More gateways": remembered per admin in this browser. */
	var moreKey = 'ecpay_more_open_' + ( V.user || 0 );
	function setMoreOpen( open ) {
		$( '#ecpay_more_list' ).prop( 'hidden', ! open );
		$( '#ecpay_more_summary' ).toggleClass( 'is-open', open );
		$( '#ecpay_more_toggle' ).attr( 'aria-expanded', open ? 'true' : 'false' ).text( open ? T.collapse : T.browse );
		store( moreKey, open ? '1' : '0' );
	}
	$wrap.on( 'click', '#ecpay_more_toggle', function() { setMoreOpen( $( '#ecpay_more_list' ).prop( 'hidden' ) ); } );
	if ( $( '#ecpay_more_list' ).length && read( moreKey ) === '1' ) { setMoreOpen( true ); }

	/* Test-mode banner: same declared action the section row runs, then refresh so the cards update. */
	$wrap.on( 'click', '[data-ecpay-test-off]', function() {
		var $b = $( this );
		$b.prop( 'disabled', true );
		toast( T.working, 'info' );
		post( 'ecv2_settings_action', { page: V.page, section: 'active', action_id: 'test_mode_off' }, function( d ) {
			toast( d.message || '', 'success' );
			reload( 1400 );
		}, function( m ) { $b.prop( 'disabled', false ); toast( m, 'error' ); } );
	} );

	/* Connect buttons stay inert while the free-edition terms are not accepted. */
	$wrap.on( 'click', '.ecpay-connect[aria-disabled="true"]', function( e ) {
		e.preventDefault();
		toast( T.terms_first, 'error' );
		var $terms = $( '#ecpay_terms' );
		if ( $terms.length ) { $terms[0].scrollIntoView( { behavior: 'smooth', block: 'center' } ); $( '#ecpay_terms_agree' ).trigger( 'focus' ); }
	} );
	$wrap.on( 'click', '.ecpay-connect:not([aria-disabled="true"])', function() { toast( T.opening, 'info' ); } );
	$wrap.on( 'change', '#ecpay_terms_agree', function() { $( '#ecpay_terms_accept' ).prop( 'disabled', ! this.checked ); } );
	$wrap.on( 'click', '#ecpay_terms_accept', function() {
		if ( ! $( '#ecpay_terms_agree' ).is( ':checked' ) ) { return; }
		$( this ).prop( 'disabled', true ).text( T.working );
		$.post( V.ajax, { action: 'ec_admin_ajax_save_terms_accepted', wp_easycart_nonce: V.terms_nonce } ).always( function() {
			$( '.ecpay-connect[aria-disabled="true"]' ).each( function() { $( this ).attr( 'href', $( this ).data( 'href' ) ).removeAttr( 'aria-disabled' ); } );
			$( '#ecpay_terms' ).slideUp( 200, function() { $( this ).remove(); } );
		} );
	} );

	/* Deep link from settings search ( ?gateway=<key> ): bring that gateway into view — its active card first,
	 * then its partner tile, then its row in "More gateways" ( opened if collapsed ) — and flash it. */
	( function() {
		var m = /[?&]gateway=([a-z0-9_\-]+)/i.exec( window.location.search );
		if ( ! m ) { return; }
		var key = m[1].toLowerCase(), $t = $wrap.find( '.ecpay-card[data-gateway="' + key + '"]' );
		if ( ! $t.length ) { $t = $wrap.find( '.ecpay-partner[data-gateway="' + key + '"]' ).filter( ':visible' ); }
		if ( ! $t.length ) {
			$t = $wrap.find( '.ecpay-more-row[data-gateway="' + key + '"]' );
			if ( $t.length && $( '#ecpay_more_list' ).prop( 'hidden' ) ) { setMoreOpen( true ); }
		}
		if ( ! $t.length ) { return; }
		$t.addClass( 'ecst-flash' );
		setTimeout( function() { $t[0].scrollIntoView( { behavior: 'smooth', block: 'center' } ); }, 80 );
		setTimeout( function() { $t.removeClass( 'ecst-flash' ); }, 2600 );
	} )();

	window.ecpay = { open: function( key, title ) { openDrawer( 'gateway', key, title ); }, openManual: function() { openDrawer( 'manual', 'manual', T.manual_title ); }, close: requestClose, refresh: refreshState };
} )( jQuery );
