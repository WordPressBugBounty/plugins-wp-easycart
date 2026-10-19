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
	   STEP 1 — location, currency, language, tax preview
	   ===================================================================== */
	function json( id ) {
		try { return JSON.parse( ( byId( id ) || {} ).textContent || '{}' ) || {}; } catch ( e ) { return {}; }
	}

	function initLocation() {
		var country = byId( 'ecwz_country' ), state = byId( 'ecwz_state' ), stateWrap = byId( 'ecwz_state_wrap' ), locale = byId( 'wp_easycart_locale' ), currency = byId( 'wp_easycart_currency' );
		if ( ! country || ! locale ) { return; }

		var meta = json( 'ecwz_locale_meta' ), decimals = json( 'ecwz_currency_decimals' ), states = json( 'ecwz_states' );
		var ready = false; /* changes the merchant makes ( not the page setting itself up ) turn tax on */

		function selectedOption( sel ) { return sel.options[ sel.selectedIndex ] || null; }
		function hasSelect2( el ) { return $.fn.select2 && $( el ).hasClass( 'select2-hidden-accessible' ); }

		/* 6.0.5: the state / province list holds only the chosen country's, after "Choose your state" ( it held every US state and
		   Canadian province, the other country's greyed out in the select2 list, and picked the first, Alabama or Alberta ). The state
		   saved for this country comes back when the country does. */
		function refreshStates() {
			var cc = country.value, list = states[ cc ] || null;
			if ( ! state || ! stateWrap ) { return false; }
			if ( ! list ) {
				stateWrap.hidden = true;
				state.value = '';
				return false;
			}
			if ( state.getAttribute( 'data-for' ) !== cc ) {
				var keep = ( country.getAttribute( 'data-saved' ) === cc ) ? ( state.getAttribute( 'data-saved' ) || '' ) : '';
				var none = state.querySelector( 'option[data-none]' );
				state.innerHTML = '';
				if ( none ) {
					none.textContent = none.getAttribute( 'CA' === cc ? 'data-ca' : 'data-us' ) || none.textContent;
					state.appendChild( none );
				}
				for ( var i = 0; i < list.length; i++ ) {
					var o = document.createElement( 'option' );
					o.value = list[ i ][ 0 ];
					o.textContent = list[ i ][ 1 ];
					state.appendChild( o );
				}
				state.value = keep || '';
				state.setAttribute( 'data-for', cc );
				if ( hasSelect2( state ) ) { $( state ).trigger( 'change.select2' ); }
			}
			stateWrap.hidden = false;
			return true;
		}

		function stateError( show ) {
			var err = byId( 'ecwz_state_error' );
			if ( ! err || ! state ) { return; }
			err.hidden = ! show;
			err.textContent = show ? ( err.getAttribute( 'CA' === country.value ? 'data-ca' : 'data-us' ) || '' ) : '';
			state.setAttribute( 'aria-invalid', show ? 'true' : 'false' );
			$( state ).next( '.select2-container' ).toggleClass( 'ecwz-invalid', !! show );
		}

		function units() {
			var m = meta[ country.value ] || {};
			var w = byId( 'ecwz_weight_unit' ), d = byId( 'ecwz_size_unit' );
			if ( w ) { w.textContent = w.getAttribute( 'data-' + ( m.w || 'lb' ) ) || w.textContent; }
			if ( d ) { d.textContent = d.getAttribute( 'data-' + ( m.d || 'in' ) ) || d.textContent; }
		}

		function compose() {
			var cc = country.value, hasStates = refreshStates();
			locale.value = ( hasStates && state && state.value ) ? cc + '_' + state.value : cc;
			if ( state && state.value ) { stateError( false ); }
			units();
			refreshTax();
			refreshPreview();
		}

		function applyCurrencyForCountry() {
			var m = meta[ country.value ] || {}, o = selectedOption( country ), cur = m.c || ( o ? o.getAttribute( 'data-currency' ) : '' );
			if ( cur && currency && currency.querySelector( 'option[value="' + cur + '"]' ) ) {
				currency.value = cur;
				if ( hasSelect2( currency ) ) { $( currency ).trigger( 'change.select2' ); }
			}
		}

		function refreshPreview() {
			var prev = byId( 'ecwz_currency_preview' );
			if ( ! prev || ! currency ) { return; }
			var m = meta[ country.value ] || { pos: 'left', th: ',', dec: '.' };
			var o = selectedOption( currency ), sym = o ? ( o.getAttribute( 'data-symbol' ) || '' ) : '', cur = currency.value;
			/* The currency's decimals ( yen none, dinars three ); the country's own currency keeps the country's ( forints none ). */
			var n = ( undefined !== decimals[ cur ] ) ? decimals[ cur ] : ( ( cur === m.c && undefined !== m.n ) ? m.n : 2 );
			var num = ( 1234.56 ).toFixed( n ).replace( '.', m.dec || '.' );
			num = num.replace( /\B(?=(\d{3})+(?!\d))/g, m.th );
			/* 6.0.5: as the wizard saves it: left | left_space | right | right_space ( a non-breaking space ). */
			var gap = /_space$/.test( String( m.pos ) ) ? '\u00a0' : '';
			prev.textContent = ( 0 === String( m.pos ).indexOf( 'left' ) ) ? sym + gap + num : num + gap + sym;
		}

		/* 6.0.5: the recommended tax settings for the country and choices ( wp_easycart_tax_setup, AJAX ecv2_tax_setup_preview ). */
		var taxTimer = null, taxSeq = 0;
		function previewTax() {
			var sum = byId( 'ecwz_tax_summary' );
			if ( ! sum || ! sum.getAttribute( 'data-ajax' ) ) { return; }
			clearTimeout( taxTimer );
			sum.setAttribute( 'aria-busy', 'true' );
			taxTimer = setTimeout( function() {
				var inc = byId( 'ecwz_tax_included' ), eu = document.querySelector( 'input[name="eu_vat"]:checked' ), pst = byId( 'ecwz_canada_pst' );
				var st = ( state && stateWrap && ! stateWrap.hidden ) ? state.value : '';
				var input = [ 'state:' + st, 'included:' + ( inc && ! inc.checked ? '0' : '1' ), 'eu:' + ( eu ? eu.value : 'home' ), 'pst:' + ( pst && pst.checked ? 'all' : 'home' ) ].join( '|' );
				var seq = ++taxSeq;
				$.post( sum.getAttribute( 'data-ajax' ), { action: 'ecv2_tax_setup_preview', nonce: sum.getAttribute( 'data-nonce' ), country: country.value, input: input, context: 'wizard' } ).done( function( r ) {
					if ( seq === taxSeq && r && r.success && r.data ) { sum.innerHTML = r.data.html; }
				} ).always( function() { if ( seq === taxSeq ) { sum.removeAttribute( 'aria-busy' ); } } );
			}, 150 );
		}

		function refreshTax() {
			var cb = byId( 'wp_easycart_sales_tax' ), box = byId( 'wp_easycart_wizard_tax_info' );
			if ( ! cb || ! box ) { return; }
			box.classList.toggle( 'is-off', ! cb.checked );
			/* VAT / GST countries say whether prices include it; EU countries how other EU customers are charged; Canada where PST is collected. */
			var vat = byId( 'ecwz_vat' ), m = meta[ country.value ] || {};
			if ( vat ) {
				vat.hidden = ! m.v;
				var t = vat.querySelector( '[data-ecwz-vat="included"]' );
				if ( t ) { t.textContent = ( 'GST' === m.v ) ? t.getAttribute( 'data-gst' ) : t.getAttribute( 'data-vat' ); }
				var eu = byId( 'ecwz_eu_vat' );
				if ( eu ) { eu.hidden = ! m.eu; }
			}
			var ca = byId( 'ecwz_canada' );
			if ( ca ) { ca.hidden = ( 'CA' !== country.value ); }
			/* 6.0.5: the row is named for the country's own tax ( wp_easycart_tax_setup::own_tax_name() ). */
			var title = byId( 'ecwz_tax_title' );
			if ( title ) {
				var key = ( 'US' === country.value ) ? 'us' : ( 'CA' === country.value ? 'ca' : ( 'GST' === m.v ? 'gst' : ( m.v ? 'vat' : 'other' ) ) );
				title.textContent = title.getAttribute( 'data-' + key ) || title.textContent;
			}
			previewTax();
		}

		/* 6.0.5: a change to the country, state or a tax choice means the tax should follow it: "Update my tax settings to these" turns
		   on ( coming back and choosing another country kept the old country's tax, the switch being off once tax was set up ). */
		function touched() {
			var cb = byId( 'wp_easycart_sales_tax' );
			if ( ready && cb && ! cb.checked ) {
				cb.checked = true;
			}
			refreshTax();
		}

		$( document ).on( 'change', '#ecwz_tax_included, input[name="eu_vat"], #ecwz_canada_pst', touched );
		$( country ).on( 'change', function() { applyCurrencyForCountry(); compose(); touched(); } );
		if ( state ) { $( state ).on( 'change', function() { compose(); touched(); } ); }
		if ( currency ) { $( currency ).on( 'change', refreshPreview ); }
		$( byId( 'wp_easycart_sales_tax' ) ).on( 'change', refreshTax );

		/* A US or Canadian store's tax needs its state or province: asked for here, before anything is saved. */
		$( byId( 'wpeasycart_admin_setup_wizard_form' ) ).on( 'submit', function( e ) {
			var cb = byId( 'wp_easycart_sales_tax' );
			if ( state && stateWrap && ! stateWrap.hidden && ! state.value && cb && cb.checked ) {
				e.preventDefault();
				e.stopImmediatePropagation();
				stateError( true );
				if ( hasSelect2( state ) ) { $( state ).select2( 'open' ); } else { state.focus(); }
				stateWrap.scrollIntoView( { behavior: 'smooth', block: 'center' } );
				return false;
			}
		} );

		if ( $.fn.select2 ) {
			$( '.ecwz-select2' ).each( function() { $( this ).select2( { width: '100%' } ); } );
		}
		if ( state ) { state.setAttribute( 'data-for', country.value ); }
		compose();
		ready = true;
	}

	/* =====================================================================
	   LEAVING A STEP ( 6.0.5 )
	   Back, a step in the stepper and the summary's Edit links went to another step and threw away what was changed on this one, and
	   Save & exit was a link that saved nothing. Save & exit now saves ( a submit button, wizard_goto=exit ); a link to another step
	   with changes on the page asks: save and go ( wizard_goto=<step> ), go without saving, or stay. Other links get the browser's own
	   "Leave site?" while there are changes.
	   ===================================================================== */
	function initLeaveGuard() {
		var form = byId( 'wpeasycart_admin_setup_wizard_form' ), goto = byId( 'ecwz_goto' );
		if ( ! form || ! goto ) { return; }
		var start = null, leaving = false;
		/* The Connect terms save themselves as they are ticked ( Connect leaves the page before the form is sent ). */
		function snap() { return $( form ).find( ':input' ).not( '#ecwz_goto, [name="wizard_goto"], [type="submit"], [name="accept_terms"]' ).serialize(); }
		function dirty() { return null !== start && ! leaving && snap() !== start; }
		start = snap();

		$( form ).on( 'submit', function( e ) {
			if ( ! e.isDefaultPrevented() ) { leaving = true; }
		} );
		window.addEventListener( 'beforeunload', function( e ) {
			if ( dirty() ) { e.preventDefault(); e.returnValue = ''; }
		} );

		function submitTo( step ) {
			goto.value = String( step );
			if ( form.requestSubmit ) {
				form.requestSubmit();
			} else {
				$( form ).trigger( 'submit' );
			}
			/* A check on the page ( the state ) stopped it: stay, nothing saved. */
			setTimeout( function() { if ( ! leaving ) { goto.value = ''; } }, 0 );
		}

		/* Connecting a gateway and Skip setup leave on purpose ( a gateway comes back to this step ). */
		$( document ).on( 'click', '.ecwz-connect, .ecwz-skip', function( e ) {
			if ( ! e.isDefaultPrevented() && 'true' !== this.getAttribute( 'aria-disabled' ) ) { leaving = true; }
		} );
		$( document ).on( 'click', 'a[data-ecwz-goto]', function( e ) {
			if ( ! dirty() || e.ctrlKey || e.metaKey || e.shiftKey ) { return; }
			e.preventDefault();
			var link = this, to = link.getAttribute( 'data-ecwz-goto' );
			askToSave( link, function( choice ) {
				if ( 'save' === choice ) {
					submitTo( to );
				} else if ( 'leave' === choice ) {
					leaving = true;
					window.location.href = link.href;
				}
			} );
		} );
	}

	/** The "Save your changes?" dialog: answers save | leave | stay. */
	function askToSave( from, done ) {
		var old = byId( 'ecwz_leave' );
		if ( old ) { old.parentNode.removeChild( old ); }
		var wrap = document.createElement( 'div' );
		wrap.className = 'ecwz-modal';
		wrap.id = 'ecwz_leave';
		wrap.innerHTML = '<div class="ecwz-modal-card" role="dialog" aria-modal="true" aria-labelledby="ecwz_leave_title" aria-describedby="ecwz_leave_text">'
			+ '<h3 id="ecwz_leave_title"></h3><p id="ecwz_leave_text"></p>'
			+ '<div class="ecwz-modal-act"><button type="button" class="ecwz-btn ecwz-btn-ghost" data-a="stay"></button><span class="ecwz-grow"></span>'
			+ '<button type="button" class="ecwz-btn" data-a="leave"></button><button type="button" class="ecwz-btn ecwz-btn-primary" data-a="save"></button></div></div>';
		wrap.querySelector( 'h3' ).textContent = t( 'leave_title', 'Save your changes on this step?' );
		wrap.querySelector( 'p' ).textContent = t( 'leave_text', 'You changed something here. Save it before you go, or leave this step as it was.' );
		wrap.querySelector( '[data-a="stay"]' ).textContent = t( 'leave_stay', 'Stay here' );
		wrap.querySelector( '[data-a="leave"]' ).textContent = t( 'leave_discard', 'Don\u2019t save' );
		wrap.querySelector( '[data-a="save"]' ).textContent = t( 'leave_save', 'Save and go' );
		( byId( 'ecwz' ) || document.body ).appendChild( wrap );
		var buttons = wrap.querySelectorAll( 'button' );
		function close( choice ) {
			document.removeEventListener( 'keydown', keys, true );
			if ( wrap.parentNode ) { wrap.parentNode.removeChild( wrap ); }
			if ( 'stay' === choice && from && from.focus ) { from.focus(); }
			done( choice );
		}
		function keys( e ) {
			if ( 'Escape' === e.key ) { e.preventDefault(); close( 'stay' ); return; }
			if ( 'Tab' === e.key ) {
				var list = Array.prototype.slice.call( buttons ), i = list.indexOf( document.activeElement );
				e.preventDefault();
				list[ ( i + ( e.shiftKey ? list.length - 1 : 1 ) ) % list.length ].focus();
			}
		}
		$( wrap ).on( 'click', 'button', function() { close( this.getAttribute( 'data-a' ) ); } );
		$( wrap ).on( 'click', function( e ) { if ( e.target === wrap ) { close( 'stay' ); } } );
		document.addEventListener( 'keydown', keys, true );
		wrap.querySelector( '[data-a="save"]' ).focus();
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
			$( manual ).on( 'change', function() { $( this ).closest( '.ecwz-pline, .ecwz-ccard' ).toggleClass( 'is-on', this.checked ); } );
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

	/* 6.0.1: demo data installs from the button itself ( the old full-card preloader overlay is gone ). */
	function initDemoData() {
		$( document ).on( 'click', '[data-ecwz-demo]', function( e ) {
			e.preventDefault();
			var $btn = $( this ), label = $btn.text();
			if ( $btn.attr( 'aria-disabled' ) === 'true' ) { return; }
			$btn.attr( 'aria-disabled', 'true' ).text( $btn.data( 'busy' ) || label );
			$( '#ecwz_demo_error' ).remove();
			$.post( ( window.wpeasycart_admin_ajax_object && wpeasycart_admin_ajax_object.ajax_url ) || window.ajaxurl, {
				action: 'ec_admin_ajax_install_demo_data',
				wp_easycart_nonce: $( '#wp_easycart_demo_settings_nonce' ).val()
			} ).done( function() {
				$( '#easycart_wizard_demo_data' ).hide();
				$( '#easycart_wizard_demo_data_done' ).show();
			} ).fail( function() {
				$btn.removeAttr( 'aria-disabled' ).text( label );
				$btn.after( $( '<p class="ecwz-note ecwz-note-amber" id="ecwz_demo_error"></p>' ).text( $btn.data( 'fail' ) || '' ) );
			} );
		} );
	}

	$( function() {
		if ( ! byId( 'ecwz' ) ) { return; }
		initDemoData();
		initLocation();
		initPayments();
		initShipping();
		initFinish();
		initLeaveGuard(); /* last: the page has set itself up */
	} );

})( jQuery );