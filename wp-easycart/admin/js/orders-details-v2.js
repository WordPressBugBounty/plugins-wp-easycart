/**
 * WP EasyCart Admin - Order Details V2.2 (free enhancements)
 *
 * Presentation-only helpers. Every data operation still runs through the
 * original orders.js handlers (ec_admin_edit_order_status, etc.).
 */

/* ---------- Header actions menu ---------- */
function ecodv2_menu_toggle( btn ) {
	var menu = document.getElementById( 'ecodv2_header_menu' );
	if ( ! menu ) {
		return false;
	}
	menu.classList.toggle( 'is-open' );
	return false;
}

function ecodv2_menu_close() {
	var menu = document.getElementById( 'ecodv2_header_menu' );
	if ( menu ) {
		menu.classList.remove( 'is-open' );
	}
}

/* ---------- Items toolbar: More menu ---------- */
function ecodv2_toolbar_more_toggle() {
	var menu = document.getElementById( 'ecodv2_toolbar_menu' );
	if ( menu ) {
		menu.classList.toggle( 'is-open' );
	}
	return false;
}

/* ---------- Offers panel: summary line <-> detail ---------- */
function ecodv2_offers_toggle( line ) {
	var panel = line.closest( '.ecodv2-offers-panel' );
	if ( panel ) {
		panel.classList.toggle( 'is-open' );
	}
	return false;
}

/* ---------- Totals editor: add-charge menu ---------- */
function ecodv2_add_charge_toggle() {
	var menu = document.getElementById( 'ecodv2_add_charge_menu' );
	if ( ! menu ) {
		return false;
	}
	if ( ! menu.classList.contains( 'is-open' ) ) {
		ecodv2_build_charge_menu( menu );
	}
	menu.classList.toggle( 'is-open' );
	return false;
}

function ecodv2_build_charge_menu( menu ) {
	menu.innerHTML = '';
	var rows = document.querySelectorAll( '.ecodv2-charge-row.ecodv2-charge-hidden[data-charge-label]' );
	if ( ! rows.length ) {
		var none = document.createElement( 'a' );
		none.href = '#';
		none.textContent = '—';
		none.onclick = function() { return false; };
		menu.appendChild( none );
		return;
	}
	rows.forEach( function( row ) {
		var link = document.createElement( 'a' );
		link.href = '#';
		link.textContent = row.getAttribute( 'data-charge-label' );
		link.onclick = function() {
			row.classList.remove( 'ecodv2-charge-hidden' );
			if ( 'vat' === row.getAttribute( 'data-charge' ) ) {
				var reg = document.querySelector( '.ecodv2-vat-reg-row' );
				if ( reg ) {
					reg.classList.remove( 'ecodv2-charge-hidden' );
				}
			}
			menu.classList.remove( 'is-open' );
			var first = row.querySelector( 'input' );
			if ( first ) {
				first.focus();
			}
			return false;
		};
		menu.appendChild( link );
	} );
}

jQuery( function( $ ) {
	if ( ! document.getElementById( 'ecodv2_wrap' ) ) {
		return;
	}

	/* Close any open menu on outside click / escape. */
	function ecodv2_close_menus( except ) {
		$( '.ecdv2-menu.is-open' ).each( function() {
			if ( this !== except ) {
				this.classList.remove( 'is-open' );
			}
		} );
	}
	$( document ).on( 'click', function( e ) {
		if ( ! $( e.target ).closest( '.ecdv2-menu-wrap, .ecodv2-toolbar-more, .ecodv2-add-charge, .ecodv2-status-wrap' ).length ) {
			ecodv2_close_menus( null );
		}
	} );
	$( document ).on( 'keydown', function( e ) {
		if ( 'Escape' === e.key ) {
			ecodv2_close_menus( null );
		}
	} );

	/* Keyboard J / K = older / newer order (skipped while typing). */
	$( document ).on( 'keydown', function( e ) {
		var tag = ( e.target && e.target.tagName ) ? e.target.tagName.toLowerCase() : '';
		if ( 'input' === tag || 'textarea' === tag || 'select' === tag || ( e.target && e.target.isContentEditable ) ) {
			return;
		}
		if ( e.metaKey || e.ctrlKey || e.altKey ) {
			return;
		}
		if ( 'j' === e.key || 'J' === e.key ) {
			var next = document.getElementById( 'ecodv2_nav_next' );
			if ( next ) {
				next.click();
			}
		} else if ( 'k' === e.key || 'K' === e.key ) {
			var prev = document.getElementById( 'ecodv2_nav_prev' );
			if ( prev ) {
				prev.click();
			}
		}
	} );

	/* ---------- Totals display: keep zero-amount rows hidden ---------- */
	var ecodv2_zero_row_keys = [
		'tip_total', 'vat_total', 'gst_total', 'hst_total',
		'pst_total', 'duty_total', 'tax_total', 'discount_total', 'refund_total'
	];

	function ecodv2_prune_totals() {
		var i, key, $row, $val, amount;
		for ( i = 0; i < ecodv2_zero_row_keys.length; i++ ) {
			key  = ecodv2_zero_row_keys[ i ];
			$row = $( '#ec_admin_order_details_totals_' + key + '_row' );
			$val = $( '#ec_admin_order_details_totals_' + key );
			if ( ! $row.length || ! $val.length ) {
				continue;
			}
			amount = parseFloat( String( $val.text() ).replace( /[^0-9.\-]/g, '' ) );
			$row.toggle( ! isNaN( amount ) && Math.abs( amount ) > 0.005 );
		}
		var $reg_row = $( '#ec_admin_order_details_totals_vat_registration_number_row' );
		if ( $reg_row.length ) {
			$reg_row.toggle( '' !== $.trim( $( '#ec_admin_order_details_totals_vat_registration_number' ).text() ) );
		}
	}

	ecodv2_prune_totals();
	$( document ).ajaxComplete( function() {
		setTimeout( ecodv2_prune_totals, 150 );
	} );

	/* ---------- Totals editor: rate auto-calc + computed grand hint ----------
	   Entering a rate fills the paired amount ( still editable ). The grand
	   hint shows the computed sum; clicking it applies. Never auto-writes
	   the grand input itself. */
	function ecodv2_num( id ) {
		var el = document.getElementById( id );
		var v = el ? parseFloat( el.value ) : 0;
		return isNaN( v ) ? 0 : v;
	}

	$( document ).on( 'input', '.ecodv2-rate-input', function() {
		var target = document.getElementById( this.getAttribute( 'data-rate-for' ) );
		if ( ! target ) {
			return;
		}
		var rate = parseFloat( this.value );
		if ( isNaN( rate ) ) {
			return;
		}
		target.value = ( ecodv2_num( 'sub_total' ) * rate / 100 ).toFixed( 2 );
		ecodv2_update_grand_hint();
	} );

	var ecodv2_grand_touched = false;

	function ecodv2_update_grand_hint() {
		var hint = document.getElementById( 'ecodv2_grand_hint' );
		if ( ! hint ) {
			return;
		}
		var computed = ecodv2_num( 'sub_total' ) + ecodv2_num( 'tax_total' )
			+ ecodv2_num( 'vat_total' ) + ecodv2_num( 'gst_total' ) + ecodv2_num( 'hst_total' )
			+ ecodv2_num( 'pst_total' ) + ecodv2_num( 'duty_total' )
			+ ecodv2_num( 'shipping_total' ) - ecodv2_num( 'discount_total' );
		$( '#ec_admin_order_details_totals_form input[name="flex_fee[]"]' ).each( function() {
			var v = parseFloat( this.value );
			if ( ! isNaN( v ) ) {
				computed += v;
			}
		} );
		computed = Math.round( computed * 100 ) / 100;
		var current = ecodv2_num( 'grand_total' );
		if ( Math.abs( computed - current ) <= 0.005 ) {
			hint.textContent = '';
			return;
		}
		if ( ! ecodv2_grand_touched ) {
			/* Grand follows the inputs automatically until manually overridden. */
			document.getElementById( 'grand_total' ).value = computed.toFixed( 2 );
			hint.textContent = '';
			return;
		}
		hint.textContent = 'Calculated: ' + computed.toFixed( 2 ) + ' — apply';
		hint.setAttribute( 'data-computed', computed.toFixed( 2 ) );
	}

	$( document ).on( 'input', '#grand_total', function() {
		ecodv2_grand_touched = true;
	} );
	$( document ).on( 'input', '#ec_admin_order_details_totals_form input', function( e ) {
		if ( 'grand_total' !== e.target.id ) {
			ecodv2_update_grand_hint();
		}
	} );
	$( document ).on( 'click', '#ecodv2_grand_hint', function() {
		var v = this.getAttribute( 'data-computed' );
		if ( v ) {
			document.getElementById( 'grand_total' ).value = v;
			this.textContent = '';
		}
		return false;
	} );
} );

/* ====================================================================
   Edit Order drawer ( V2.3 )
   Hosts the PRO-included edit forms, relocated here by ID at load.
   ==================================================================== */
var ecodv2_drawer_pristine = true;

/* Open the order upsell on a specific feature ( falls back to the generic popup ). */
function ecodv2_locked( feature ) {
	if ( 'function' === typeof window.ecdv2_upsell ) {
		window.ecdv2_upsell( { context: 'orders', feature: feature || '' } );
	} else if ( 'function' === typeof window.show_pro_required ) {
		window.show_pro_required();
	}
	return false;
}
var ECODV2_SECTION_FEATURE = { customer: 'edit_details', billing: 'edit_details', shipping: 'edit_details', details: 'edit_details', fulfillment: '' };

function ecodv2_drawer_available( section ) {
	/* Fulfillment is a free feature: its form is inlined in the drawer, not hosted. */
	if ( 'fulfillment' === section && document.querySelector( '#ecodv2_sec_fulfillment .ecodv2-form' ) ) {
		return true;
	}
	var hosts = document.querySelectorAll( '.ecodv2-sec-host' );
	for ( var i = 0; i < hosts.length; i++ ) {
		if ( hosts[ i ].children.length ) {
			if ( ! section ) { return true; }
			var sec = hosts[ i ].closest( '.ecodv2-drawer-sec' );
			if ( sec && sec.getAttribute( 'data-section' ) === section ) { return true; }
		}
	}
	return false;
}

function ecodv2_open_edit_drawer( section, fallback ) {
	if ( ! ecodv2_drawer_available( section ) ) {
		if ( fallback && 'show_pro_required' !== fallback && 'function' === typeof window[ fallback ] ) {
			window[ fallback ]();
		} else {
			ecodv2_locked( ECODV2_SECTION_FEATURE[ section ] || '' );
		}
		return false;
	}
	document.body.classList.add( 'ecodv2-drawer-open' );
	jQuery( '.ecodv2-drawer .ecodv2-form' ).show(); /* clear post-save inline hides */
	setTimeout( function() { ecodv2_drawer_jump( section ); }, 240 );
	return false;
}

function ecodv2_close_edit_drawer( force ) {
	if ( ! force && ! ecodv2_drawer_pristine ) {
		if ( ! window.confirm( 'Discard unsaved changes?' ) ) {
			return false;
		}
	}
	document.body.classList.remove( 'ecodv2-drawer-open' );
	ecodv2_drawer_reset_dirty();
	return false;
}

function ecodv2_drawer_reset_dirty() {
	ecodv2_drawer_pristine = true;
	jQuery( '.ecodv2-drawer-sec' ).removeClass( 'is-dirty' );
	var save = document.getElementById( 'ecodv2_drawer_save' );
	if ( save ) {
		save.disabled = true;
	}
	var dirty = document.getElementById( 'ecodv2_drawer_dirty' );
	if ( dirty ) {
		dirty.style.display = 'none';
	}
}

function ecodv2_drawer_jump( section ) {
	var sec = document.getElementById( 'ecodv2_sec_' + section );
	if ( ! sec || 'none' === sec.style.display ) {
		sec = document.querySelector( '.ecodv2-drawer-sec:not([style*="display: none"])' );
	}
	if ( ! sec ) {
		return false;
	}
	sec.scrollIntoView( { block: 'start', behavior: 'smooth' } );
	sec.classList.remove( 'ecodv2-sec-flash' );
	void sec.offsetWidth;
	sec.classList.add( 'ecodv2-sec-flash' );
	var first = sec.querySelector( 'input:not([type="hidden"]), select' );
	if ( first ) {
		first.focus( { preventScroll: true } );
	}
	jQuery( '#ecodv2_drawer_jump a' ).removeClass( 'is-active' );
	jQuery( '#ecodv2_drawer_jump a[data-section="' + sec.getAttribute( 'data-section' ) + '"]' ).addClass( 'is-active' );
	return false;
}

function ecodv2_drawer_save() {
	var fired = 0;
	jQuery( '.ecodv2-drawer-sec.is-dirty .ecodv2-visually-hidden' ).each( function() {
		jQuery( this ).trigger( 'click' );
		fired++;
	} );
	if ( ! fired ) {
		/* Nothing marked dirty — save everything present as a safety net. */
		jQuery( '.ecodv2-drawer .ecodv2-visually-hidden' ).each( function() {
			jQuery( this ).trigger( 'click' );
		} );
	}
	ecodv2_drawer_reset_dirty();
	ecodv2_close_edit_drawer( true );
	return false;
}

/* ---- Additional email disclosure ---- */
function ecodv2_show_add_email() {
	jQuery( '#ecodv2_add_email_wrap' ).addClass( 'ecodv2-hidden' );
	jQuery( '#ecodv2_add_email_field' ).removeClass( 'ecodv2-hidden' );
	var el = document.getElementById( 'email_other' );
	if ( el ) {
		el.focus();
	}
	ecodv2_drawer_mark_dirty( el );
	return false;
}

function ecodv2_hide_add_email() {
	var el = document.getElementById( 'email_other' );
	if ( el ) {
		el.value = '';
	}
	jQuery( '#ecodv2_add_email_field' ).addClass( 'ecodv2-hidden' );
	jQuery( '#ecodv2_add_email_wrap' ).removeClass( 'ecodv2-hidden' );
	ecodv2_drawer_mark_dirty( el );
	return false;
}

/* ---- Copy shipping address from billing ---- */
function ecodv2_copy_from_billing() {
	var fields = [ 'first_name', 'last_name', 'company_name', 'address_line_1', 'address_line_2', 'city', 'state', 'zip', 'phone', 'country' ];
	for ( var i = 0; i < fields.length; i++ ) {
		var from = document.getElementById( 'billing_' + fields[ i ] );
		var to = document.getElementById( 'shipping_' + fields[ i ] );
		if ( from && to ) {
			to.value = from.value;
			if ( 'SELECT' === to.tagName && window.jQuery && jQuery( to ).hasClass( 'select2-basic' ) ) {
				jQuery( to ).trigger( 'change' );
			}
		}
	}
	var ok = document.getElementById( 'ecodv2_copied_ok' );
	if ( ok ) {
		ok.style.display = 'inline';
		setTimeout( function() { ok.style.display = 'none'; }, 1600 );
	}
	ecodv2_drawer_mark_dirty( document.getElementById( 'shipping_address_line_1' ) );
	return false;
}

function ecodv2_drawer_mark_dirty( el ) {
	ecodv2_drawer_pristine = false;
	if ( el ) {
		var sec = el.closest ? el.closest( '.ecodv2-drawer-sec' ) : null;
		if ( sec ) {
			sec.classList.add( 'is-dirty' );
		}
	}
	var save = document.getElementById( 'ecodv2_drawer_save' );
	if ( save ) {
		save.disabled = false;
	}
	var dirty = document.getElementById( 'ecodv2_drawer_dirty' );
	if ( dirty ) {
		dirty.style.display = 'inline';
	}
}

jQuery( function( $ ) {
	if ( ! document.getElementById( 'ecodv2_edit_drawer' ) ) {
		return;
	}

	/* Relocate the PRO-included edit forms into the drawer sections. */
	$( '.ecodv2-sec-host' ).each( function() {
		var form = document.getElementById( this.getAttribute( 'data-form-id' ) );
		if ( form ) {
			this.appendChild( form );
		} else {
			/* No form ( free install / feature gated ): hide section + chip. */
			var sec = this.closest( '.ecodv2-drawer-sec' );
			sec.style.display = 'none';
			$( '#ecodv2_drawer_jump a[data-section="' + sec.getAttribute( 'data-section' ) + '"]' ).hide();
		}
	} );

	/* Dirty tracking + expiration auto-advance. */
	$( '#ecodv2_drawer_body' ).on( 'input change', 'input, select, textarea', function() {
		ecodv2_drawer_mark_dirty( this );
	} );
	$( '#ecodv2_drawer_body' ).on( 'input', '#cc_exp_month', function() {
		if ( 2 === this.value.length ) {
			var y = document.getElementById( 'cc_exp_year' );
			if ( y ) {
				y.focus();
			}
		}
	} );

	/* Esc closes ( with the unsaved-changes guard ). */
	$( document ).on( 'keydown', function( e ) {
		if ( 'Escape' === e.key && document.body.classList.contains( 'ecodv2-drawer-open' ) ) {
			ecodv2_close_edit_drawer();
		}
	} );
} );

/* ====================================================================
   V2.4 — copy address, customer-notes popover, fulfillment save flow
   ==================================================================== */
function ecodv2_copy_address( which, btn ) {
	var ids = ( 'shipping' === which )
		? [ 'ec_admin_order_details_shipping_name', 'ec_admin_order_details_shipping_company', 'ec_admin_order_details_shipping_address1', 'ec_admin_order_details_shipping_address2', 'ec_admin_order_details_shipping_address3', 'ec_admin_order_details_shipping_country', 'ec_admin_order_details_shipping_phone' ]
		: [ 'ec_admin_order_details_billing_name', 'ec_admin_order_details_billing_company', 'ec_admin_order_details_billing_address1', 'ec_admin_order_details_billing_address2', 'ec_admin_order_details_billing_address3', 'ec_admin_order_details_billing_country', 'ec_admin_order_details_billing_phone' ];
	var lines = [];
	for ( var i = 0; i < ids.length; i++ ) {
		var el = document.getElementById( ids[ i ] );
		if ( el ) {
			var t = ( el.textContent || '' ).trim();
			if ( '' !== t ) {
				lines.push( t );
			}
		}
	}
	var text = lines.join( '\n' );
	var done = function() {
		if ( btn ) {
			var orig = btn.innerHTML;
			btn.innerHTML = '<span class="dashicons dashicons-yes"></span> Copied';
			btn.classList.add( 'is-copied' );
			setTimeout( function() { btn.innerHTML = orig; btn.classList.remove( 'is-copied' ); }, 1500 );
		}
	};
	if ( navigator.clipboard && navigator.clipboard.writeText ) {
		navigator.clipboard.writeText( text ).then( done, done );
	} else {
		var ta = document.createElement( 'textarea' );
		ta.value = text;
		document.body.appendChild( ta );
		ta.select();
		try { document.execCommand( 'copy' ); } catch ( e ) {}
		document.body.removeChild( ta );
		done();
	}
	return false;
}

/* ---- Customer notes popover ( V4.1 — self-contained editor ) ----
   Saves directly through ec_admin_ajax_edit_customer_notes instead of arming
   the legacy V1 two-phase toggle, which hid the popover's own anchor. */
var ecodv2_cnotes_saved = null;

function ecodv2_open_cnotes_popover() {
	var pop = document.getElementById( 'ecodv2_cnotes_popover' );
	var ta = document.getElementById( 'order_customer_notes' );
	if ( ! pop || ! ta ) {
		return false;
	}
	if ( null === ecodv2_cnotes_saved ) {
		ecodv2_cnotes_saved = ta.value;
	}
	pop.classList.add( 'is-open' );
	ta.focus();
	return false;
}

function ecodv2_close_cnotes_popover() {
	var pop = document.getElementById( 'ecodv2_cnotes_popover' );
	if ( pop ) {
		pop.classList.remove( 'is-open' );
	}
	return false;
}

function ecodv2_cancel_cnotes() {
	var ta = document.getElementById( 'order_customer_notes' );
	if ( ta && null !== ecodv2_cnotes_saved ) {
		ta.value = ecodv2_cnotes_saved;
	}
	return ecodv2_close_cnotes_popover();
}

function ecodv2_save_cnotes() {
	var ta = document.getElementById( 'order_customer_notes' );
	var btn = document.getElementById( 'ec_admin_order_details_customer_notes_save' );
	if ( ! ta ) {
		return false;
	}
	var value = ta.value;
	if ( btn ) {
		btn.disabled = true;
	}
	jQuery.ajax( {
		url: wpeasycart_admin_ajax_object.ajax_url,
		type: 'post',
		data: {
			action: 'ec_admin_ajax_edit_customer_notes',
			order_id: ec_admin_get_value( 'order_id', 'text' ),
			order_customer_notes: value,
			wp_easycart_nonce: ec_admin_get_value( 'wp_easycart_order_details_nonce', 'text' )
		},
		success: function() {
			ecodv2_cnotes_saved = value;
			/* Escape, then convert newlines for display. */
			var safe_html = jQuery( '<div></div>' ).text( value ).html().replace( /\n/g, '<br />' );
			jQuery( document.getElementById( 'ec_admin_order_details_customer_notes' ) ).html( safe_html );
			var has_notes = ( '' !== jQuery.trim( value ) );
			jQuery( document.getElementById( 'ec_admin_order_details_customer_notes_empty_message' ) ).toggle( ! has_notes );
			jQuery( document.getElementById( 'ec_admin_order_details_customer_notes_edit' ) ).toggle( has_notes );
			if ( btn ) {
				btn.disabled = false;
			}
			ecodv2_close_cnotes_popover();
			if ( jQuery( document.getElementById( 'wpeasycart_order_history_refresh' ) ).length && 'function' === typeof window.ec_order_history_refresh ) {
				ec_order_history_refresh();
			}
		},
		error: function() {
			if ( btn ) {
				btn.disabled = false;
			}
		}
	} );
	return false;
}

/* ---- Activity history collapse + full-history drawer ( V4.2 ) ---- */
function ecodv2_history_apply_collapse() {
	var wrap = document.getElementById( 'ecodv2_history_wrap' );
	if ( ! wrap ) {
		return;
	}
	var items = wrap.querySelectorAll( '.wpeasycart-timeline-item' );
	var showall = document.getElementById( 'ecodv2_history_showall' );
	var total_el = document.getElementById( 'ecodv2_history_total' );
	if ( items.length > 21 ) {
		wrap.classList.add( 'ecodv2-history-collapsed' );
		if ( total_el ) {
			total_el.textContent = items.length;
		}
		if ( showall ) {
			showall.style.display = 'block';
		}
	} else {
		wrap.classList.remove( 'ecodv2-history-collapsed' );
		if ( showall ) {
			showall.style.display = 'none';
		}
	}
}

function ecodv2_open_history_drawer() {
	var wrap = document.getElementById( 'ecodv2_history_wrap' );
	var body = document.getElementById( 'ecodv2_history_drawer_body' );
	var drawer = document.getElementById( 'ecodv2_history_drawer' );
	var backdrop = document.getElementById( 'ecodv2_history_backdrop' );
	if ( ! wrap || ! body || ! drawer ) {
		return false;
	}
	var timeline = wrap.querySelector( '.wpeasycart-timeline' );
	body.innerHTML = '';
	if ( timeline ) {
		/* Clone the live timeline; the drawer body has no collapsed class, so
		   every item renders. */
		body.appendChild( timeline.cloneNode( true ) );
		var count_el = document.getElementById( 'ecodv2_history_drawer_count' );
		if ( count_el ) {
			var count = body.querySelectorAll( '.wpeasycart-timeline-item' ).length;
			count_el.textContent = count + ' ' + ( 1 === count ? 'event' : 'events' );
		}
	}
	if ( backdrop ) {
		backdrop.classList.add( 'is-open' );
	}
	drawer.classList.add( 'is-open' );
	document.body.classList.add( 'ecodv2-hdrawer-open' );
	body.scrollTop = 0;
	return false;
}

function ecodv2_close_history_drawer() {
	var drawer = document.getElementById( 'ecodv2_history_drawer' );
	var backdrop = document.getElementById( 'ecodv2_history_backdrop' );
	if ( drawer ) {
		drawer.classList.remove( 'is-open' );
	}
	if ( backdrop ) {
		backdrop.classList.remove( 'is-open' );
	}
	document.body.classList.remove( 'ecodv2-hdrawer-open' );
	return false;
}

jQuery( function( $ ) {
	if ( ! document.getElementById( 'ecodv2_wrap' ) ) {
		return;
	}
	$( document ).on( 'click', function( e ) {
		if ( ! $( e.target ).closest( '.ecodv2-cnotes-anchor, .ecodv2-cnotes-trigger' ).length ) {
			ecodv2_close_cnotes_popover();
		}
	} );
	$( document ).on( 'keydown', function( e ) {
		if ( 'Escape' === e.key ) {
			ecodv2_close_cnotes_popover();
			ecodv2_close_history_drawer();
		}
	} );

	/* ---- Activity history: collapse at 21+ items ( V4.2 ) ----
	   Applies on load and re-applies whenever the PRO AJAX refresh replaces
	   the timeline markup ( MutationObserver on the wrap ). */
	ecodv2_history_apply_collapse();
	var ecodv2_history_wrap_el = document.getElementById( 'ecodv2_history_wrap' );
	if ( ecodv2_history_wrap_el && 'undefined' !== typeof MutationObserver ) {
		new MutationObserver( function() {
			ecodv2_history_apply_collapse();
		} ).observe( ecodv2_history_wrap_el, { childList: true, subtree: true } );
	}

	/* Arm the shipping-method toggle whenever the drawer opens with a
	   fulfillment section, so the drawer Save's call performs the save. */
	var ecodv2_orig_open = window.ecodv2_open_edit_drawer;
	window.ecodv2_open_edit_drawer = function( section, fallback ) {
		var out = ecodv2_orig_open( section, fallback );
		if ( document.body.classList.contains( 'ecodv2-drawer-open' )
			&& document.getElementById( 'ecodv2_sec_fulfillment' )
			&& 'undefined' !== typeof window.ec_admin_order_details_shipping_method_show
			&& ! window.ec_admin_order_details_shipping_method_show
			&& 'function' === typeof window.ec_admin_process_shipping_method ) {
			window.ec_admin_process_shipping_method();
			$( '#ec_admin_order_details_shipping_method_form' ).show();
		}
		return out;
	};

	/* Extend drawer save: fulfillment section persists via the legacy fns. */
	var ecodv2_orig_save = window.ecodv2_drawer_save;
	window.ecodv2_drawer_save = function() {
		var fulfillment_dirty = $( '#ecodv2_sec_fulfillment' ).hasClass( 'is-dirty' );
		var out = ecodv2_orig_save();
		if ( fulfillment_dirty ) {
			if ( window.ec_admin_order_details_shipping_method_show && 'function' === typeof window.ec_admin_process_shipping_method ) {
				window.ec_admin_process_shipping_method();
			}
			if ( 'function' === typeof window.ec_admin_process_order_info ) {
				window.ec_admin_process_order_info(); /* persists #order_weight */
			}
		}
		return out;
	};
} );

/* ====================================================================
   V2.6 — status swatch pill + inline date cancel
   ==================================================================== */
function ecodv2_status_toggle() {
	var menu = document.getElementById( 'ecodv2_status_menu' );
	if ( menu ) {
		menu.classList.toggle( 'is-open' );
	}
	return false;
}

function ecodv2_set_status( value, item ) {
	var sel = document.getElementById( 'orderstatus_id' );
	var menu = document.getElementById( 'ecodv2_status_menu' );
	if ( ! sel ) {
		return false;
	}
	jQuery( sel ).val( value ).trigger( 'change' ); /* fires ec_admin_edit_order_status */
	if ( 'add-new' === value ) {
		return false; /* handler redirects to settings */
	}
	if ( item ) {
		var dot = document.getElementById( 'ecodv2_status_pill_dot' );
		var label = document.getElementById( 'ecodv2_status_pill_label' );
		if ( dot ) {
			dot.style.background = item.getAttribute( 'data-color' ) || '#e5e7eb';
		}
		if ( label ) {
			label.textContent = item.querySelector( '.ecodv2-status-item-label' ).textContent;
		}
		jQuery( '#ecodv2_status_menu .ecodv2-status-item' ).removeClass( 'is-current' );
		item.classList.add( 'is-current' );
	}
	if ( menu ) {
		menu.classList.remove( 'is-open' );
	}
	return false;
}

function ecodv2_cancel_date_edit() {
	var input = document.getElementById( 'order_date' );
	if ( input ) {
		input.value = input.getAttribute( 'data-original' ) || input.value;
	}
	var time = document.getElementById( 'order_time' );
	if ( time ) {
		time.value = time.getAttribute( 'data-original' ) || time.value;
	}
	jQuery( '#ec_admin_order_details_order_date_edit' ).hide();
	jQuery( '#ec_admin_order_details_order_date_row' ).show();
	return false;
}

jQuery( function( $ ) {
	if ( ! document.getElementById( 'ecodv2_wrap' ) ) {
		return;
	}
	/* Close the status menu on outside click ( escape already handled globally ). */
	$( document ).on( 'click', function( e ) {
		if ( ! $( e.target ).closest( '.ecodv2-status-wrap' ).length ) {
			var menu = document.getElementById( 'ecodv2_status_menu' );
			if ( menu ) {
				menu.classList.remove( 'is-open' );
			}
		}
	} );
} );

/* ====================================================================
   V2.9 — date display reformat, status->fulfillment sync, panel fixes
   ==================================================================== */
function ecodv2_copy_tracking( btn ) {
	var text = jQuery.trim( jQuery( '#ec_admin_order_details_tracking_number' ).text() );
	if ( ! text ) {
		return false;
	}
	var done = function() {
		btn.classList.add( 'is-copied' );
		setTimeout( function() { btn.classList.remove( 'is-copied' ); }, 1500 );
	};
	if ( navigator.clipboard && navigator.clipboard.writeText ) {
		navigator.clipboard.writeText( text ).then( done, done );
	} else {
		done();
	}
	return false;
}

/* ====================================================================
   V4.0 — generic copy-to-clipboard for the customer card ( email,
   phone, and any future data-copy-target button ). Reads the target
   element's live text so PRO edits stay in sync without re-render.
   ==================================================================== */
function ecodv2_copy_text( btn ) {
	var text = '';
	var target = btn.getAttribute( 'data-copy-target' );
	if ( target ) {
		text = jQuery.trim( jQuery( '#' + target ).text() );
	}
	if ( ! text ) {
		text = btn.getAttribute( 'data-copy' ) || '';
	}
	if ( ! text ) {
		return false;
	}
	var done = function() {
		btn.classList.add( 'is-copied' );
		setTimeout( function() { btn.classList.remove( 'is-copied' ); }, 1500 );
	};
	if ( navigator.clipboard && navigator.clipboard.writeText ) {
		navigator.clipboard.writeText( text ).then( done, done );
	} else {
		done();
	}
	return false;
}

/* Item 1: after the PRO date save, rebuild the view text in display format
   ( "Aug 5 2026 1:00 pm" ) instead of the raw input value. */
function ecodv2_format_date_view() {
	var input = document.getElementById( 'order_date' );
	var span = document.getElementById( 'ec_admin_order_details_order_date' );
	var form = document.getElementById( 'ec_admin_order_details_order_date_edit' );
	if ( ! input || ! span ) {
		return;
	}
	var parsed = new Date( input.value );
	if ( isNaN( parsed.getTime() ) ) {
		return;
	}
	var months = [ 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec' ];
	var text = months[ parsed.getMonth() ] + ' ' + parsed.getDate() + ' ' + parsed.getFullYear();
	var time = form ? ( form.getAttribute( 'data-time' ) || '' ) : '';
	span.textContent = time ? ( text + ' ' + time ) : text;
	input.setAttribute( 'data-original', input.value );
}

/* Item 4: reflect status changes in the fulfillment badge + panel immediately. */
function ecodv2_sync_fulfillment( status_id ) {
	var banner = document.getElementById( 'ecodv2_fulfill_banner' );
	var badge = document.getElementById( 'ecodv2_fulfill_badge' );
	if ( ! banner ) {
		return;
	}
	var current = banner.className.match( /ecodv2-fulfill-banner-(\w+)/ );
	current = current ? current[ 1 ] : 'none';
	if ( 'pickup' === current ) {
		return; /* pickup orders stay pickup regardless of status */
	}
	var fulfill_ids = ( banner.getAttribute( 'data-fulfill-status-ids' ) || '' ).split( ',' );
	var tracking = jQuery.trim( jQuery( '#ec_admin_order_details_tracking_number' ).text() );
	var fulfilled = ( -1 !== jQuery.inArray( String( status_id ), fulfill_ids ) ) || '' !== tracking;
	var next = fulfilled ? 'fulfilled' : 'unfulfilled';

	if ( next === current ) {
		return;
	}
	banner.className = banner.className.replace( /ecodv2-fulfill-banner-\w+/, 'ecodv2-fulfill-banner-' + next );
	var msg = document.getElementById( 'ecodv2_fulfill_message' );
	if ( msg ) {
		msg.textContent = banner.getAttribute( 'data-msg-' + next ) || msg.textContent;
	}
	var icon = banner.querySelector( '.ecodv2-fulfill-row > .dashicons' );
	if ( icon ) {
		icon.className = 'dashicons ' + ( fulfilled ? 'dashicons-yes-alt' : 'dashicons-warning' );
	}
	var label_btn = document.getElementById( 'ecodv2_create_label_btn' );
	if ( label_btn ) {
		label_btn.style.display = fulfilled ? 'none' : '';
	}
	jQuery( '.ecodv2-fulfill-btn' ).toggle( ! fulfilled );
	if ( badge ) {
		badge.style.display = '';
		badge.className = 'ecodv2-fulfill-badge ' + ( fulfilled ? 'ecodv2-fulfill-badge-ok' : 'ecodv2-fulfill-badge-warn' );
		badge.querySelector( '.dashicons' ).className = 'dashicons ' + ( fulfilled ? 'dashicons-yes-alt' : 'dashicons-warning' );
		var label = document.getElementById( 'ecodv2_fulfill_badge_label' );
		if ( label ) {
			label.textContent = fulfilled ? 'Fulfilled' : 'Unfulfilled';
		}
	}
}

jQuery( function( $ ) {
	if ( ! document.getElementById( 'ecodv2_wrap' ) ) {
		return;
	}

	/* Item 4: hook into the pill flow. */
	var ecodv2_orig_set_status = window.ecodv2_set_status;
	window.ecodv2_set_status = function( value, item ) {
		var out = ecodv2_orig_set_status( value, item );
		if ( 'add-new' !== value ) {
			ecodv2_sync_fulfillment( value );
		}
		return out;
	};

	/* Item 5: opening the drawer must not hide the panel's shipping info —
	   the legacy toggle's "show edit" pass hides the view; restore it. */
	var ecodv2_orig_open2 = window.ecodv2_open_edit_drawer;
	window.ecodv2_open_edit_drawer = function( section, fallback ) {
		var out = ecodv2_orig_open2( section, fallback );
		setTimeout( function() {
			$( '#ec_admin_view_shipping_method' ).show();
			if ( '' === $.trim( $( '#ec_admin_order_details_tracking_number' ).text() ) ) {
				$( '#ec_admin_order_details_shipping_empty_message' ).show();
			}
		}, 50 );
		return out;
	};

	/* Reconcile totals after any line edit/delete ajax lands ( covers slow
	   responses; global handlers fire after the request's own success ). */
	$( document ).ajaxComplete( function( e, xhr, settings ) {
		var d = ( settings && 'string' === typeof settings.data ) ? settings.data : '';
		if ( -1 !== d.indexOf( 'ec_admin_ajax_delete_order_detail_line_item' ) ) {
			setTimeout( function() { ecodv2_recalc_order_totals(); }, 50 );
		}
	} );

	/* Keep the tracking copy button in sync after a fulfillment save. */
	$( document ).ajaxComplete( function() {
		var has = '' !== $.trim( $( '#ec_admin_order_details_tracking_number' ).text() );
		$( '.ecodv2-tracking-copy' ).toggle( has );
	} );
} );


/* ====================================================================
   V3.0 — date + time save via the free endpoint
   ==================================================================== */
function ecodv2_save_order_date() {
	var form = document.getElementById( 'ec_admin_order_details_order_date_edit' );
	var date = document.getElementById( 'order_date' );
	var time = document.getElementById( 'order_time' );
	if ( ! form || ! date || ! time ) {
		return false;
	}
	jQuery.post( ajaxurl, {
		action: 'wp_easycart_ecv2_save_order_date',
		nonce: form.getAttribute( 'data-nonce' ),
		order_id: form.getAttribute( 'data-order-id' ),
		order_date: date.value,
		order_time: time.value
	}, function( response ) {
		if ( ! response || ! response.success ) {
			window.alert( 'Could not save the order date.' );
			return;
		}
		var span = document.getElementById( 'ec_admin_order_details_order_date' );
		if ( span ) {
			span.textContent = response.data.display;
		}
		date.setAttribute( 'data-original', date.value );
		time.setAttribute( 'data-original', time.value );
		jQuery( '#ec_admin_order_details_order_date_edit' ).hide();
		jQuery( '#ec_admin_order_details_order_date_row' ).show();
	} );
	return false;
}

/* ====================================================================
   V3.1 — create shipping label popup
   ==================================================================== */
function ecodv2_copy_text( text, btn ) {
	text = ( text || '' ).trim();
	var done = function() {
		if ( btn ) {
			btn.classList.add( 'is-copied' );
			setTimeout( function() { btn.classList.remove( 'is-copied' ); }, 1400 );
		}
	};
	if ( navigator.clipboard && navigator.clipboard.writeText ) {
		navigator.clipboard.writeText( text ).then( done, done );
	} else {
		var ta = document.createElement( 'textarea' );
		ta.value = text;
		document.body.appendChild( ta );
		ta.select();
		try { document.execCommand( 'copy' ); } catch ( e ) {}
		document.body.removeChild( ta );
		done();
	}
	return false;
}

function ecodv2_open_label_popup( action ) {
	if ( 'ecodv2_label_popup_open' !== action ) {
		if ( action && 'show_pro_required' !== action && 'function' === typeof window[ action ] ) {
			window[ action ]();
		} else {
			ecodv2_locked( 'labels' );
		}
		return false;
	}
	return ecodv2_label_popup_open();
}

function ecodv2_label_popup_open() {
	document.body.classList.add( 'ecodv2-label-open' );
	var saved = document.getElementById( 'ecodv2_label_saved' );
	if ( saved ) {
		saved.style.display = 'none';
	}
	return false;
}

function ecodv2_label_popup_close() {
	document.body.classList.remove( 'ecodv2-label-open' );
	return false;
}

function ecodv2_label_save_tracking() {
	var tracking = document.getElementById( 'ecodv2_label_tracking' );
	var carrier = document.getElementById( 'ecodv2_label_carrier_sel' );
	if ( ! tracking || '' === tracking.value.trim() ) {
		tracking.focus();
		return false;
	}
	/* Write into the fulfillment form inputs ( in the drawer ) and drive the
	   legacy two-phase save: arm if needed, then save. */
	var t_input = document.getElementById( 'tracking_number' );
	var c_input = document.getElementById( 'shipping_carrier' );
	if ( ! t_input ) {
		return false;
	}
	t_input.value = tracking.value.trim();
	if ( c_input && '' !== carrier.value ) {
		c_input.value = carrier.value;
	}
	if ( 'undefined' !== typeof window.ec_admin_order_details_shipping_method_show && ! window.ec_admin_order_details_shipping_method_show && 'function' === typeof window.ec_admin_process_shipping_method ) {
		window.ec_admin_process_shipping_method(); /* arm */
	}
	if ( 'function' === typeof window.ec_admin_process_shipping_method ) {
		window.ec_admin_process_shipping_method(); /* save */
	}
	/* Restore the panel view ( the arm pass hides it ) + sync fulfillment. */
	jQuery( '#ec_admin_view_shipping_method' ).show();
	jQuery( '#ecodv2_create_label_btn, .ecodv2-fulfill-btn' ).hide();
	var sel = document.getElementById( 'orderstatus_id' );
	if ( sel ) {
		ecodv2_sync_fulfillment( sel.value );
	}
	var saved = document.getElementById( 'ecodv2_label_saved' );
	if ( saved ) {
		saved.style.display = 'block';
	}
	if ( document.getElementById( 'ecodv2_label_send_email' ) && document.getElementById( 'ecodv2_label_send_email' ).checked ) {
		setTimeout( function() {
			jQuery( '#ecodv2_send_shipped_btn' ).trigger( 'click' );
		}, 900 );
	}
	setTimeout( ecodv2_label_popup_close, 1600 );
	return false;
}

jQuery( function( $ ) {
	if ( ! document.getElementById( 'ecodv2_label_popup' ) ) {
		return;
	}
	$( document ).on( 'keydown', function( e ) {
		if ( 'Escape' === e.key && document.body.classList.contains( 'ecodv2-label-open' ) ) {
			ecodv2_label_popup_close();
		}
	} );
} );


/* ====================================================================
   V3.2 — date editor owns its own open/close ( the legacy PRO toggle is
   gate-check only; calling it desynced its internal state and made its
   close branch overwrite the view with the raw input value ).
   ==================================================================== */
function ecodv2_open_date_edit( gate_action ) {
	var form = document.getElementById( 'ec_admin_order_details_order_date_edit' );
	if ( ! form || 'show_pro_required' === gate_action ) {
		ecodv2_locked( 'edit_details' );
		return false;
	}
	jQuery( '#ec_admin_order_details_order_date_row' ).hide();
	jQuery( form ).show();
	var input = document.getElementById( 'order_date' );
	if ( input ) {
		input.focus();
	}
	return false;
}

/* ====================================================================
   V3.3 — Edit Line Item modal ( wraps the orders-pro.js toggle flow )
   ==================================================================== */
var ecodv2_line_modal_id = null;
var ecodv2_line_parked_id = null;

/* Move any containers parked in the modal back to their line row.
   Never destroy them — they are the PRO edit form for that line. */
function ecodv2_line_modal_restore() {
	var body = document.getElementById( 'ecodv2_line_modal_body' );
	if ( ! body || null === ecodv2_line_parked_id ) {
		if ( body ) {
			body.innerHTML = '';
		}
		return;
	}
	var row = document.getElementById( 'ec_admin_order_details_line_item_' + ecodv2_line_parked_id );
	var money = body.querySelector( '.ecodv2-line-money' );
	if ( money ) {
		while ( money.firstChild ) {
			body.appendChild( money.firstChild );
		}
		money.remove();
	}
	while ( body.firstChild ) {
		var el = body.firstChild;
		if ( row && 1 === el.nodeType ) {
			el.classList.remove( 'ecodv2-visually-hidden' );
			jQuery( el ).hide();
			row.appendChild( el );
		} else {
			body.removeChild( el );
		}
	}
	ecodv2_line_parked_id = null;
}

function ecodv2_line_edit_containers( id ) {
	var ids = [
		'ec_admin_order_details_title_edit_' + id,
		'ec_admin_order_details_model_number_edit_' + id,
		'ec_admin_order_details_optionitem_name_1_edit_' + id,
		'ec_admin_order_details_optionitem_name_2_edit_' + id,
		'ec_admin_order_details_optionitem_name_3_edit_' + id,
		'ec_admin_order_details_optionitem_name_4_edit_' + id,
		'ec_admin_order_details_optionitem_name_5_edit_' + id,
		'ec_admin_order_details_giftcard_id_edit_' + id,
		'ec_admin_order_details_gift_card_email_edit_' + id,
		'ec_admin_order_details_gift_card_to_name_edit_' + id,
		'ec_admin_order_details_gift_card_from_name_edit_' + id,
		'ec_admin_order_details_gift_card_message_edit_' + id,
		'ec_admin_order_details_item_price_edit_' + id,
		'ec_admin_order_details_item_total_edit_' + id,
		'ec_admin_order_details_item_save_display_' + id
	];
	var found = [];
	for ( var i = 0; i < ids.length; i++ ) {
		var el = document.getElementById( ids[ i ] );
		if ( el ) {
			found.push( el );
		}
	}
	jQuery( '.ec_admin_order_details_item_adv_opt_edit_' + id ).each( function() {
		found.push( this );
	} );
	return found;
}

function ecodv2_open_line_modal( id, gate_action ) {
	var pencil = document.getElementById( 'ec_admin_order_line_edit_' + id );
	var title_edit = document.getElementById( 'ec_admin_order_details_title_edit_' + id );
	if ( 'show_pro_required' === gate_action || ! title_edit ) {
		if ( gate_action && 'show_pro_required' !== gate_action && 'function' === typeof window[ gate_action ] && 'ec_order_edit_line_item' !== gate_action ) {
			window[ gate_action ]( id );
		} else if ( ! title_edit || 'show_pro_required' === gate_action ) {
			ecodv2_locked( 'lines' );
		}
		if ( ! title_edit ) {
			return false;
		}
	}
	ecodv2_line_modal_id = id;

	/* Arm the legacy toggle ( shows edit containers, hides row displays ). */
	if ( '1' !== jQuery( pencil ).attr( 'data-editing' ) && 'function' === typeof window.ec_order_edit_line_item ) {
		window.ec_order_edit_line_item( id );
	}
	/* Row displays stay visible behind the modal. */
	jQuery( '#ec_admin_order_details_item_price_display_' + id + ', #ec_admin_order_details_item_total_display_' + id ).show();

	/* Return any previously parked containers, then load this line's. */
	ecodv2_line_modal_restore();
	var body = document.getElementById( 'ecodv2_line_modal_body' );
	var parts = ecodv2_line_edit_containers( id );
	ecodv2_line_parked_id = id;
	var money = document.createElement( 'div' );
	money.className = 'ecodv2-line-money';
	for ( var i = 0; i < parts.length; i++ ) {
		var pid = parts[ i ].id || '';
		if ( pid === 'ec_admin_order_details_item_price_edit_' + id || pid === 'ec_admin_order_details_item_total_edit_' + id ) {
			money.appendChild( parts[ i ] );
		} else if ( pid === 'ec_admin_order_details_item_save_display_' + id ) {
			parts[ i ].classList.add( 'ecodv2-visually-hidden' );
			body.appendChild( parts[ i ] );
		} else {
			body.appendChild( parts[ i ] );
		}
		jQuery( parts[ i ] ).show();
	}
	body.appendChild( money );

	/* Offer chips for context. */
	var chips = document.getElementById( 'ecodv2_line_modal_chips' );
	var row_chips = document.querySelector( '#ec_admin_order_details_line_item_' + id + ' .ecodv2-line-chips' );
	chips.innerHTML = row_chips ? row_chips.innerHTML : '';

	document.body.classList.add( 'ecodv2-line-open' );
	var first = body.querySelector( 'input' );
	if ( first ) {
		first.focus();
	}
	return false;
}

function ecodv2_line_modal_cancel() {
	var id = ecodv2_line_modal_id;
	if ( null !== id ) {
		/* Reset the legacy toggle state without saving. */
		var pencil = document.getElementById( 'ec_admin_order_line_edit_' + id );
		jQuery( pencil ).removeClass( 'dashicons-yes' ).addClass( 'dashicons-edit' ).attr( 'data-editing', 0 );
		jQuery( '#ec_admin_order_details_item_price_display_' + id + ', #ec_admin_order_details_item_total_display_' + id ).show();
		jQuery( ecodv2_line_edit_containers( id ) ).hide();
	}
	ecodv2_line_modal_restore();
	document.body.classList.remove( 'ecodv2-line-open' );
	ecodv2_line_modal_id = null;
	return false;
}

jQuery( function( $ ) {
	if ( ! document.getElementById( 'ecodv2_line_modal' ) ) {
		return;
	}

	$( '#ecodv2_line_modal_save' ).on( 'click', function() {
		var id = ecodv2_line_modal_id;
		if ( null === id ) {
			return false;
		}
		if ( 'function' === typeof window.ec_order_edit_line_item ) {
			window.ec_order_edit_line_item( id ); /* editing state = save branch ( reads values synchronously ) */
		}
		ecodv2_line_modal_restore();
		document.body.classList.remove( 'ecodv2-line-open' );
		ecodv2_line_modal_id = null;
		ecodv2_recalc_order_totals();
		return false;
	} );

	$( '#ecodv2_line_modal_remove' ).on( 'click', function() {
		var id = ecodv2_line_modal_id;
		if ( null === id ) {
			return false;
		}
		ecodv2_confirm_line_delete( id, this, 'ec_order_delete_line_item' );
		return false;
	} );

	/* Keep the legacy qty × unit auto-calc live inside the modal. */
	$( '#ecodv2_line_modal_body' ).on( 'input', 'input[id^="line_item_quantity_"], input[id^="line_item_unit_price_"]', function() {
		if ( null !== ecodv2_line_modal_id && 'function' === typeof window.ec_admin_update_line_item_total ) {
			window.ec_admin_update_line_item_total( ecodv2_line_modal_id );
		}
	} );

	$( document ).on( 'keydown', function( e ) {
		if ( 'Escape' === e.key && document.body.classList.contains( 'ecodv2-line-open' ) ) {
			ecodv2_line_modal_cancel();
		}
	} );
} );


/* ====================================================================
   V3.4 — order totals follow line edits
   Sub total = sum of the per-line total inputs ( pre-rendered for every
   line ); grand recomputes via the legacy helper; persistence runs the
   legacy totals toggle silently ( arm if hidden, then save ).
   ==================================================================== */
function ecodv2_recalc_order_totals( exclude_id ) {
	var sub_input = document.getElementById( 'sub_total' );
	if ( ! sub_input || 'function' !== typeof window.ec_order_show_hide_edit_totals ) {
		return;
	}
	var sub = 0;
	jQuery( 'input[id^="line_item_total_price_"]' ).each( function() {
		if ( exclude_id && this.id === 'line_item_total_price_' + exclude_id ) {
			return; /* line is being removed */
		}
		var v = parseFloat( this.value );
		if ( ! isNaN( v ) ) {
			sub += v;
		}
	} );
	sub_input.value = ( Math.round( sub * 100 ) / 100 ).toFixed( 2 );
	if ( 'function' === typeof window.ec_order_update_totals ) {
		window.ec_order_update_totals();
	}
	document.body.classList.add( 'ecodv2-totals-silent' );
	try {
		if ( 'undefined' !== typeof window.ec_admin_order_details_totals_show && ! window.ec_admin_order_details_totals_show ) {
			window.ec_order_show_hide_edit_totals(); /* arm */
		}
		window.ec_order_show_hide_edit_totals(); /* save + span refresh */
	} finally {
		setTimeout( function() {
			document.body.classList.remove( 'ecodv2-totals-silent' );
		}, 100 );
	}
}


/* ====================================================================
   V3.5 — styled delete confirmation ( replaces the native confirm )
   ==================================================================== */
function ecodv2_confirm_line_delete( id, anchor, gate_action ) {
	/* Free installs keep the gate; unknown overrides keep their own flow. */
	if ( 'show_pro_required' === gate_action || 'function' !== typeof window.ec_order_delete_line_item_confirmed ) {
		if ( gate_action && 'show_pro_required' !== gate_action && 'function' === typeof window[ gate_action ] ) {
			window[ gate_action ]( id );
		} else {
			ecodv2_locked( 'lines' );
		}
		return false;
	}
	var pop = document.getElementById( 'ecodv2_line_del_pop' );
	if ( ! pop ) {
		pop = document.createElement( 'div' );
		pop.id = 'ecodv2_line_del_pop';
		pop.className = 'ecodv2-del-pop';
		pop.innerHTML = '<div class="ecodv2-del-pop-msg"></div>'
			+ '<div class="ecodv2-del-pop-actions">'
			+ '<button type="button" class="ecv2-btn ecv2-btn-sm ecodv2-del-cancel">Cancel</button>'
			+ '<button type="button" class="ecv2-btn ecv2-btn-sm ecodv2-del-go">Remove</button>'
			+ '</div>';
		document.body.appendChild( pop );
		pop.querySelector( '.ecodv2-del-cancel' ).addEventListener( 'click', function() {
			pop.classList.remove( 'is-open' );
		} );
		pop.querySelector( '.ecodv2-del-go' ).addEventListener( 'click', function() {
			pop.classList.remove( 'is-open' );
			var del_id = pop.getAttribute( 'data-line-id' );
			if ( document.body.classList.contains( 'ecodv2-line-open' ) ) {
				ecodv2_line_modal_cancel();
			}
			window.ec_order_delete_line_item_confirmed( del_id );
			/* Instant: recompute with the removed line excluded ( no ajax race ). */
			ecodv2_recalc_order_totals( del_id );
		} );
		document.addEventListener( 'click', function( e ) {
			if ( ! e.target.closest( '#ecodv2_line_del_pop' ) && ! e.target.closest( '.dashicons-trash' ) && ! e.target.closest( '#ecodv2_line_modal_remove' ) ) {
				pop.classList.remove( 'is-open' );
			}
		} );
	}
	pop.setAttribute( 'data-line-id', id );
	pop.querySelector( '.ecodv2-del-pop-msg' ).textContent = 'Remove this line from the order?';
	var rect = anchor.getBoundingClientRect();
	pop.style.top = ( rect.bottom + window.scrollY + 8 ) + 'px';
	pop.style.left = Math.max( 12, Math.min( rect.left + window.scrollX - 180, window.scrollX + document.documentElement.clientWidth - 252 ) ) + 'px';
	pop.classList.add( 'is-open' );
	return false;
}

/* ====================================================================
   V3.6 — Add Line modal: variant selection + live pricing
   Pricing semantics ( mirrors storefront display logic ):
   - optionitem_price_override > -1 replaces the base price
   - optionitem_price adjusts the unit price
   - optionitem_price_onetime adds once to the line total ( not × qty )
   Manual unit-price edits stick until the product changes.
   ==================================================================== */
var ecodv2_add_base = 0;
var ecodv2_add_onetime = 0;
var ecodv2_add_unit_touched = false;

function ecodv2_open_add_line_modal( gate_action ) {
	var modal = document.getElementById( 'ec_admin_add_new_order_item' );
	if ( 'show_pro_required' === gate_action || ! modal || ! modal.classList.contains( 'ecodv2-add-modal' ) ) {
		if ( gate_action && 'show_pro_required' !== gate_action && 'function' === typeof window[ gate_action ] && 'ec_order_add_new_line' !== gate_action ) {
			window[ gate_action ]();
		} else {
			ecodv2_locked( 'lines' );
		}
		if ( ! modal || ! modal.classList.contains( 'ecodv2-add-modal' ) ) {
			return false;
		}
	}
	document.body.classList.add( 'ecodv2-add-open' );
	return false;
}

function ecodv2_close_add_line_modal() {
	document.body.classList.remove( 'ecodv2-add-open' );
	/* Reset the picker so the next open starts clean ( works for plain select and select2 ). */
	var $p = jQuery( '#order_line_add_product_id' );
	if ( $p.length ) { $p.val( '0' ).trigger( 'change' ); }
	return false;
}

function ecodv2_add_recalc( from_selection ) {
	var unit_el = document.getElementById( 'ecodv2_add_unit' );
	var qty = parseInt( document.getElementById( 'order_line_add_quantity' ).value, 10 ) || 1;
	if ( from_selection && ! ecodv2_add_unit_touched ) {
		var base = ecodv2_add_base;
		var adjust = 0;
		ecodv2_add_onetime = 0;
		jQuery( '#ecodv2_add_options select' ).each( function() {
			var opt = this.options[ this.selectedIndex ];
			if ( ! opt || ! opt.value ) {
				return;
			}
			var override = parseFloat( opt.getAttribute( 'data-override' ) );
			if ( ! isNaN( override ) && override > -1 ) {
				base = override;
			}
			adjust += parseFloat( opt.getAttribute( 'data-price' ) ) || 0;
			ecodv2_add_onetime += parseFloat( opt.getAttribute( 'data-onetime' ) ) || 0;
		} );
		unit_el.value = ( Math.round( ( base + adjust ) * 100 ) / 100 ).toFixed( 2 );
	}
	var unit = parseFloat( unit_el.value ) || 0;
	document.getElementById( 'ecodv2_add_total' ).value = ( Math.round( ( qty * unit + ecodv2_add_onetime ) * 100 ) / 100 ).toFixed( 2 );
}

jQuery( function( $ ) {
	var modal = document.getElementById( 'ec_admin_add_new_order_item' );
	if ( ! modal || ! modal.classList.contains( 'ecodv2-add-modal' ) ) {
		return;
	}
	var nonce = modal.getAttribute( 'data-nonce' );

	/* Product picker: search-as-you-type, 25 results a page, so a 100k-product catalog never
	 * renders into the modal. Falls back to a plain select if select2 is not on the page. */
	var $picker = $( '#order_line_add_product_id' );
	if ( $picker.hasClass( 'ecodv2-product-search' ) && $.fn.select2 ) {
		$picker.select2( {
			width: '100%',
			dropdownParent: $( modal ),
			placeholder: $picker.attr( 'data-placeholder' ) || '',
			allowClear: false,
			minimumInputLength: 0,
			ajax: {
				url: wpeasycart_admin_ajax_object.ajax_url,
				type: 'POST',
				dataType: 'json',
				delay: 250,
				cache: true,
				data: function( params ) {
					return { action: 'ec_admin_ajax_ecv2_product_search', q: params.term || '', page: params.page || 1 };
				},
				processResults: function( data, params ) {
					params.page = params.page || 1;
					return { results: ( data && data.results ) ? data.results : [], pagination: { more: !! ( data && data.more ) } };
				}
			},
			templateResult: function( item ) {
				if ( ! item.id || item.loading ) { return item.text; }
				var $r = $( '<span class="ecodv2-product-result"></span>' ).text( item.title || item.text );
				if ( item.sku ) { $r.append( $( '<code></code>' ).text( item.sku ) ); }
				if ( item.price ) { $r.append( $( '<em></em>' ).text( item.price ) ); }
				return $r;
			},
			templateSelection: function( item ) { return item.title || item.text; },
			language: {
				searching: function() { return $picker.attr( 'data-searching' ) || 'Searching…'; },
				noResults: function() { return $picker.attr( 'data-none' ) || 'No products match'; },
				loadingMore: function() { return $picker.attr( 'data-more' ) || 'Loading more…'; }
			}
		} );
	}

	$( '#order_line_add_product_id' ).on( 'change', function() {
		var pid = this.value;
		var host = document.getElementById( 'ecodv2_add_options' );
		host.innerHTML = '';
		ecodv2_add_unit_touched = false;
		$( '#ecodv2_add_line_save' ).prop( 'disabled', '0' === pid || ! pid );
		if ( '0' === pid || ! pid ) {
			return;
		}
		$.post( wpeasycart_admin_ajax_object.ajax_url, {
			action: 'ecv2_line_product_data', nonce: nonce, product_id: pid
		}, function( response ) {
			if ( ! response || ! response.success ) {
				return;
			}
			ecodv2_add_base = response.data.price || 0;
			modal.setAttribute( 'data-title', response.data.title || '' );
			( response.data.options || [] ).forEach( function( option ) {
				var wrap = document.createElement( 'div' );
				wrap.className = 'ecodv2-field ecodv2-field-wide';
				var label = document.createElement( 'label' );
				label.textContent = option.label;
				var sel = document.createElement( 'select' );
				sel.setAttribute( 'data-slot', option.slot );
				var none = document.createElement( 'option' );
				none.value = '';
				none.textContent = '—';
				sel.appendChild( none );
				( option.items || [] ).forEach( function( item ) {
					var o = document.createElement( 'option' );
					o.value = item.optionitem_id;
					o.textContent = item.optionitem_name + ( parseFloat( item.optionitem_price ) > 0 ? ' (+' + parseFloat( item.optionitem_price ).toFixed( 2 ) + ')' : '' );
					o.setAttribute( 'data-price', item.optionitem_price );
					o.setAttribute( 'data-onetime', item.optionitem_price_onetime );
					o.setAttribute( 'data-override', item.optionitem_price_override );
					o.setAttribute( 'data-name', item.optionitem_name );
					sel.appendChild( o );
				} );
				wrap.appendChild( label );
				wrap.appendChild( sel );
				host.appendChild( wrap );
			} );
			ecodv2_add_recalc( true );
		} );
	} );

	$( '#ecodv2_add_options' ).on( 'change', 'select', function() { ecodv2_add_recalc( true ); } );
	$( '#order_line_add_quantity' ).on( 'input', function() { ecodv2_add_recalc( false ); } );
	$( '#ecodv2_add_unit' ).on( 'input', function() { ecodv2_add_unit_touched = true; ecodv2_add_recalc( false ); } );

	$( '#ecodv2_add_line_save' ).on( 'click', function() {
		var data = {
			action: 'ecv2_order_add_line_full',
			nonce: nonce,
			order_id: jQuery( '#order_id' ).val() || document.getElementById( 'order_id' ).value,
			product_id: $( '#order_line_add_product_id' ).val(),
			quantity: $( '#order_line_add_quantity' ).val(),
			unit_price: $( '#ecodv2_add_unit' ).val(),
			total_price: $( '#ecodv2_add_total' ).val(),
			title: modal.getAttribute( 'data-title' ) || ''
		};
		$( '#ecodv2_add_options select' ).each( function() {
			var opt = this.options[ this.selectedIndex ];
			if ( opt && opt.value ) {
				data[ 'optionitem_id_' + this.getAttribute( 'data-slot' ) ] = opt.value;
				data[ 'optionitem_name_' + this.getAttribute( 'data-slot' ) ] = opt.getAttribute( 'data-name' );
			}
		} );
		var btn = this;
		btn.disabled = true;
		$.post( wpeasycart_admin_ajax_object.ajax_url, data, function( response ) {
			if ( response && response.success ) {
				document.getElementById( 'ecodv2_add_saved' ).style.display = 'block';
				setTimeout( function() { window.location.reload(); }, 700 );
			} else {
				btn.disabled = false;
			}
		} );
		return false;
	} );

	$( document ).on( 'keydown', function( e ) {
		if ( 'Escape' === e.key && document.body.classList.contains( 'ecodv2-add-open' ) ) {
			ecodv2_close_add_line_modal();
		}
	} );
} );

/* ====================================================================
   V3.8 — pinned note: strip + editor with save feedback
   Persists through the legacy free info save ( ec_admin_process_order_info
   reads #order_notes by id along with weight / giftcard / promo ).
   ==================================================================== */
function ecodv2_pin_edit() {
	var legacy = document.getElementById( 'order_notes' );
	document.getElementById( 'ecodv2_pin_input' ).value = legacy ? legacy.value : '';
	jQuery( '#ecodv2_pin_strip, #ecodv2_pin_add' ).hide();
	jQuery( '#ecodv2_pin_editor' ).show();
	document.getElementById( 'ecodv2_pin_input' ).focus();
	return false;
}

function ecodv2_pin_cancel() {
	var has = '' !== jQuery.trim( jQuery( '#order_notes' ).val() );
	jQuery( '#ecodv2_pin_editor' ).hide();
	jQuery( '#ecodv2_pin_strip' ).toggle( has );
	jQuery( '#ecodv2_pin_add' ).toggle( ! has );
	return false;
}

function ecodv2_pin_save( unpin ) {
	var value = unpin ? '' : jQuery.trim( jQuery( '#ecodv2_pin_input' ).val() );
	var legacy = document.getElementById( 'order_notes' );
	var btn = document.getElementById( 'ecodv2_pin_save_btn' );
	if ( ! legacy || 'function' !== typeof window.ec_admin_process_order_info ) {
		return false;
	}
	legacy.value = value;
	btn.textContent = 'Saving…';
	btn.disabled = true;
	window.ec_admin_process_order_info();
	/* The legacy save has no callback hook; watch its ajax land. */
	var done = function() {
		btn.textContent = '\u2713 Saved';
		setTimeout( function() {
			btn.textContent = 'Save Note';
			btn.disabled = false;
			jQuery( '#ecodv2_pin_text' ).text( value );
			jQuery( '#ecodv2_pin_unpin' ).toggle( '' !== value );
			ecodv2_pin_cancel();
		}, 650 );
	};
	var handler = function( e, xhr, settings ) {
		var d = ( settings && 'string' === typeof settings.data ) ? settings.data : '';
		if ( -1 !== d.indexOf( 'ec_admin_ajax_update_order_info' ) || -1 !== d.indexOf( 'order_notes' ) ) {
			jQuery( document ).off( 'ajaxComplete', handler );
			done();
		}
	};
	jQuery( document ).on( 'ajaxComplete', handler );
	/* Safety: never leave the button stuck if the action name differs. */
	setTimeout( function() {
		jQuery( document ).off( 'ajaxComplete', handler );
		if ( btn.disabled ) {
			done();
		}
	}, 4000 );
	return false;
}