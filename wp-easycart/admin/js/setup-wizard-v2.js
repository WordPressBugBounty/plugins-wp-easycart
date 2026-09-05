/**
 * WP EasyCart Admin — Setup Wizard V2
 * Depends on jQuery ( and select2 when registered ). Data via wpEasyCartWizardData.
 *
 * @since 5.x.x
 */
(function( $ ) {
	'use strict';

	var D = window.wpEasyCartWizardData || { ajax_url: ( window.ajaxurl || '' ), nonce: '', terms_nonce: '', i18n: {} };
	var t = function( k, fallback ) { return ( D.i18n && D.i18n[ k ] ) ? D.i18n[ k ] : fallback; };
	var byId = function( id ) { return document.getElementById( id ); };

	function post( action, data ) {
		return $.ajax( {
			url: D.ajax_url,
			type: 'post',
			dataType: 'json',
			data: $.extend( { action: action, nonce: D.nonce }, data || {} )
		} );
	}

	function busy( btn, on, label ) {
		if ( ! btn ) { return; }
		if ( on ) {
			btn.dataset.label = btn.textContent;
			btn.textContent = label || t( 'working', 'Working…' );
			btn.setAttribute( 'disabled', 'disabled' );
		} else {
			btn.textContent = label || btn.dataset.label || btn.textContent;
			btn.removeAttribute( 'disabled' );
		}
	}

	function badge( cls, text ) {
		var s = document.createElement( 'span' );
		s.className = 'ecwz-badge ' + cls;
		s.textContent = text;
		return s;
	}

	/* =====================================================================
	   PUBLIC API ( used by inline onclick handlers in templates )
	   ===================================================================== */
	var W = window.wpEasyCartWizard = {

		/** Add Store / Cart / Account to the primary menu. */
		addMenuItems: function( btn ) {
			busy( btn, true );
			post( 'ec_admin_ajax_wizard_add_menu_items' ).done( function( r ) {
				if ( ! r || ! r.success ) { busy( btn, false ); W.flash( btn, r && r.data ? r.data.message : t( 'error' ) ); return; }
				var foot = byId( 'ecwz_menu_foot' );
				if ( foot ) {
					foot.innerHTML = '';
					foot.appendChild( badge( 'ecwz-badge-green', '\u2713 ' + t( 'added', 'Added' ) ) );
					var span = document.createElement( 'span' ); span.textContent = r.data.message; foot.appendChild( span );
					var grow = document.createElement( 'span' ); grow.className = 'ecwz-grow'; foot.appendChild( grow );
					var a = document.createElement( 'a' ); a.className = 'ecwz-btn ecwz-btn-sm ecwz-btn-ghost'; a.href = r.data.edit; a.target = '_blank'; a.textContent = t( 'edit_menu', 'Edit menu' ); foot.appendChild( a );
				} else {
					W.markItemDone( 'menu' );
				}
			} ).fail( function() { busy( btn, false ); W.flash( btn, t( 'error' ) ); } );
			return false;
		},

		/** Create a draft Terms / Privacy page and select it in the given dropdown. */
		createPage: function( btn, type, selectId ) {
			busy( btn, true );
			post( 'ec_admin_ajax_wizard_create_page', { type: type } ).done( function( r ) {
				if ( ! r || ! r.success ) { busy( btn, false ); W.flash( btn, r && r.data ? r.data.message : t( 'error' ) ); return; }
				var sel = byId( selectId );
				if ( sel ) {
					var o = document.createElement( 'option' ); o.value = r.data.id; o.textContent = r.data.title; sel.appendChild( o ); sel.value = String( r.data.id );
					if ( $.fn.select2 && $( sel ).hasClass( 'select2-hidden-accessible' ) ) { $( sel ).trigger( 'change' ); }
				}
				var wrap = btn.parentNode;
				var b = badge( 'ecwz-badge-green', '\u2713 ' + t( 'draft_made', 'Draft created' ) );
				var a = document.createElement( 'a' ); a.className = 'ecwz-btn ecwz-btn-sm ecwz-btn-ghost'; a.href = r.data.edit; a.target = '_blank'; a.textContent = t( 'edit_page', 'Edit page' );
				wrap.replaceChild( b, btn ); wrap.appendChild( a );
			} ).fail( function() { busy( btn, false ); W.flash( btn, t( 'error' ) ); } );
			return false;
		},

		/** Send a test email to the BCC / admin address. */
		sendTestEmail: function( btn ) {
			var to   = byId( 'ecwz_bcc_email' ) ? byId( 'ecwz_bcc_email' ).value : '';
			var from = byId( 'ecwz_from_email' ) ? byId( 'ecwz_from_email' ).value : '';
			busy( btn, true, t( 'sending', 'Sending…' ) );
			post( 'ec_admin_ajax_wizard_send_test_email', { to: to, from: from } ).done( function( r ) {
				busy( btn, false, t( 'send_again', 'Send again' ) );
				var hint = byId( 'ecwz_test_hint' );
				if ( ! r || ! r.success ) {
					if ( hint ) { hint.innerHTML = ''; hint.appendChild( badge( 'ecwz-badge-red', t( 'error', 'Error' ) ) ); hint.appendChild( document.createTextNode( ' ' + ( r && r.data ? r.data.message : '' ) ) ); }
					else { W.flash( btn, r && r.data ? r.data.message : t( 'error' ) ); }
					return;
				}
				if ( hint ) { hint.innerHTML = ''; hint.appendChild( badge( 'ecwz-badge-green', '\u2713 ' + t( 'sent', 'Sent' ) ) ); var s = document.createElement( 'span' ); s.innerHTML = ' ' + r.data.message; hint.appendChild( s ); }
				W.markItemDone( 'email' );
			} ).fail( function() { busy( btn, false ); W.flash( btn, t( 'error' ) ); } );
			return false;
		},

		/** Hide an optional checklist item. */
		dismissItem: function( btn, key ) {
			var row = btn.closest( '.ecwz-ci' );
			post( 'ec_admin_ajax_wizard_checklist', { item: key, do: 'dismiss' } ).done( function( r ) {
				if ( row ) { row.parentNode.removeChild( row ); }
				W.recount();
				W.updateSidebarBadge( r && r.data ? r.data.remaining : null );
			} );
			return false;
		},

		/** Flip a checklist row to done ( client side ). */
		markItemDone: function( key ) {
			var row = document.querySelector( '.ecwz-ci[data-ci="' + key + '"]' );
			if ( ! row ) { return; }
			row.classList.add( 'is-done' ); row.classList.remove( 'is-feat' );
			$( row ).find( '.ecwz-btn' ).remove();
			W.recount();
			W.updateSidebarBadge( null );
		},

		recount: function() {
			var list = byId( 'ecwz_checklist' );
			if ( ! list ) { return; }
			var rows = list.querySelectorAll( '.ecwz-ci' ), done = list.querySelectorAll( '.ecwz-ci.is-done' );
			var small = list.querySelector( '.ecwz-chk-head small' ), bar = list.querySelector( '.ecwz-chk-head .ecwz-progress i' );
			if ( small ) { small.textContent = done.length + ' / ' + rows.length; }
			if ( bar ) { bar.style.width = ( rows.length ? Math.round( done.length / rows.length * 100 ) : 100 ) + '%'; }
		},

		/** Sidebar count ( shell-v2 renders [data-ecwz-remaining] when the hook is wired ). */
		updateSidebarBadge: function( n ) {
			var el = document.querySelector( '[data-ecwz-remaining]' );
			if ( ! el ) { return; }
			if ( null === n ) { n = Math.max( 0, parseInt( el.textContent, 10 ) - 1 ); }
			el.textContent = n; el.style.display = n > 0 ? '' : 'none';
		},

		flash: function( near, msg ) {
			if ( ! near || ! msg ) { return; }
			var s = document.createElement( 'span' ); s.className = 'ecwz-hint'; s.style.color = '#b91c1c'; s.style.marginLeft = '8px'; s.textContent = msg;
			near.parentNode.appendChild( s );
			setTimeout( function() { if ( s.parentNode ) { s.parentNode.removeChild( s ); } }, 6000 );
		}
	};

	/* =====================================================================
	   STEP 1 — location, currency, tax preview
	   ===================================================================== */
	function initLocation() {
		var country = byId( 'ecwz_country' ), state = byId( 'ecwz_state' ), locale = byId( 'wp_easycart_locale' ), currency = byId( 'wp_easycart_currency' );
		if ( ! country || ! locale ) { return; }

		var meta = {};
		try { meta = JSON.parse( ( byId( 'ecwz_locale_meta' ) || {} ).textContent || '{}' ); } catch ( e ) {}

		function selectedOption( sel ) { return sel.options[ sel.selectedIndex ] || null; }

		function refreshStates() {
			var cc = country.value, hasStates = false, first = null;
			if ( ! state ) { return; }
			for ( var i = 0; i < state.options.length; i++ ) {
				var o = state.options[ i ], match = ( o.getAttribute( 'data-country' ) === cc );
				o.hidden = ! match; o.disabled = ! match;
				if ( match ) { hasStates = true; if ( ! first ) { first = o; } }
			}
			if ( hasStates ) {
				var cur = selectedOption( state );
				if ( ! cur || cur.getAttribute( 'data-country' ) !== cc ) { state.value = first.value; }
				state.style.display = '';
				if ( $.fn.select2 && $( state ).hasClass( 'select2-hidden-accessible' ) ) { $( state ).next( '.select2-container' ).show(); $( state ).trigger( 'change.select2' ); }
			} else {
				state.style.display = 'none';
				if ( $.fn.select2 && $( state ).hasClass( 'select2-hidden-accessible' ) ) { $( state ).next( '.select2-container' ).hide(); }
			}
			return hasStates;
		}

		function compose() {
			var cc = country.value, hasStates = refreshStates();
			locale.value = hasStates && state ? cc + '_' + state.value : cc;
			var m = meta[ cc ];
			var w = byId( 'ecwz_weight_unit' );
			if ( w ) { w.textContent = m ? m.w : 'lbs'; }
			refreshTax();
			refreshPreview();
		}

		function applyCurrencyForCountry() {
			var o = selectedOption( country ), cur = o ? o.getAttribute( 'data-currency' ) : '';
			if ( cur && currency ) {
				currency.value = cur;
				if ( $.fn.select2 && $( currency ).hasClass( 'select2-hidden-accessible' ) ) { $( currency ).trigger( 'change.select2' ); }
			}
		}

		function refreshPreview() {
			var prev = byId( 'ecwz_currency_preview' );
			if ( ! prev || ! currency ) { return; }
			var m = meta[ country.value ] || { pos: 'left', th: ',', dec: '.', n: 2 };
			var o = selectedOption( currency ), sym = o ? ( o.getAttribute( 'data-symbol' ) || '' ) : '';
			var num = ( 1234.56 ).toFixed( m.n ).replace( '.', m.dec );
			num = num.replace( /\B(?=(\d{3})+(?!\d))/g, m.th );
			prev.textContent = ( 'left' === m.pos ) ? sym + num : num + ' ' + sym;
		}

		function refreshTax() {
			var cb = byId( 'wp_easycart_sales_tax' ), box = byId( 'wp_easycart_wizard_tax_info' );
			if ( ! cb || ! box ) { return; }
			var rows = box.querySelectorAll( '.wp_easycart_wizard_tax_row' ), shown = 0;
			for ( var i = 0; i < rows.length; i++ ) {
				var on = rows[ i ].classList.contains( 'wp_easycart_wizard_tax_' + locale.value );
				rows[ i ].style.display = on ? '' : 'none';
				if ( on ) { shown++; }
			}
			var none = byId( 'ecwz_tax_none' ), table = box.querySelector( 'table' );
			if ( none ) { none.style.display = shown ? 'none' : ''; }
			if ( table ) { table.style.display = shown ? '' : 'none'; }
			box.style.display = cb.checked ? '' : 'none';
		}

		$( country ).on( 'change', function() { applyCurrencyForCountry(); compose(); } );
		if ( state ) { $( state ).on( 'change', compose ); }
		if ( currency ) { $( currency ).on( 'change', refreshPreview ); }
		$( byId( 'wp_easycart_sales_tax' ) ).on( 'change', refreshTax );

		if ( $.fn.select2 ) {
			$( '.ecwz-select2' ).each( function() { $( this ).select2( { width: '100%' } ); } );
		}
		compose();
	}

	/* =====================================================================
	   STEP 2 — terms gate, manual toggle
	   ===================================================================== */
	function initPayments() {
		var cards = byId( 'ecwz_pay_cards' );
		if ( ! cards ) { return; }

		var terms = byId( 'ecwz_accept_terms' );
		function gate() {
			var ok = ! terms || terms.checked;
			$( cards ).find( '.ecwz-connect' ).each( function() {
				if ( ok ) {
					this.removeAttribute( 'aria-disabled' );
					if ( this.getAttribute( 'data-href' ) ) { this.setAttribute( 'href', this.getAttribute( 'data-href' ) ); }
				} else {
					this.setAttribute( 'aria-disabled', 'true' );
					this.setAttribute( 'href', '#' );
				}
			} );
		}
		if ( terms ) {
			$( terms ).on( 'change', function() {
				gate();
				if ( terms.checked && D.terms_nonce ) {
					/* Save immediately: Connect leaves the page before the form submits. */
					$.ajax( { url: D.ajax_url, type: 'post', data: { action: 'ec_admin_ajax_save_terms_accepted', wp_easycart_nonce: D.terms_nonce } } );
				}
			} );
		}
		$( cards ).on( 'click', '.ecwz-connect', function( e ) {
			if ( 'true' === this.getAttribute( 'aria-disabled' ) ) {
				e.preventDefault();
				var note = byId( 'ecwz_terms_note' );
				if ( note ) { note.classList.add( 'is-required' ); note.scrollIntoView( { behavior: 'smooth', block: 'center' } ); var cb = byId( 'ecwz_accept_terms' ); if ( cb ) { cb.focus(); } setTimeout( function() { note.classList.remove( 'is-required' ); }, 2000 ); }
				return false;
			}
		} );
		gate();

		var manual = byId( 'wp_easycart_manual_billing' );
		if ( manual ) {
			$( manual ).on( 'change', function() { $( this ).closest( '.ecwz-ccard' ).toggleClass( 'is-on', this.checked ); } );
		}
	}

	/* =====================================================================
	   STEP 3 — radio cards
	   ===================================================================== */
	function initShipping() {
		var cards = byId( 'ecwz_ship_cards' );
		if ( ! cards ) { return; }
		$( cards ).on( 'change', 'input[type="radio"]', function() {
			$( cards ).find( '.ecwz-rcard' ).removeClass( 'is-on' );
			$( this ).closest( '.ecwz-rcard' ).addClass( 'is-on' );
		} );
	}

	/* =====================================================================
	   STEP 4 — advanced disclosure
	   ===================================================================== */
	function initFinish() {
		var change = byId( 'ecwz_rec_change' ), adv = byId( 'ecwz_rec_adv' );
		if ( change && adv ) {
			$( change ).on( 'click', function() { adv.open = true; adv.scrollIntoView( { behavior: 'smooth', block: 'center' } ); } );
		}
		/* Deep links from the checklist ( #ecwz-recommended / #ecwz-policies ) */
		if ( window.location.hash ) {
			var target = document.querySelector( window.location.hash );
			if ( target ) { setTimeout( function() { target.scrollIntoView( { behavior: 'smooth', block: 'start' } ); }, 150 ); }
		}
		if ( $.fn.select2 ) {
			$( '#ecwz_terms_page, #ecwz_privacy_page' ).each( function() { $( this ).addClass( 'ecwz-select' ).select2( { width: '100%' } ); } );
		}
	}

	$( function() {
		if ( ! byId( 'ecwz' ) ) { return; }
		initLocation();
		initPayments();
		initShipping();
		initFinish();
	} );

})( jQuery );