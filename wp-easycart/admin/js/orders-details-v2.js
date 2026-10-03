/**
 * WP EasyCart Admin - the order screen.
 *
 * 6.0.2 redesign ( https://claude.ai/artifact/9FU888SGrpJoFceHXNspwX ): the section at the end of this file holds the
 * order screen's own saves ( status, pinned note, Fulfill, address and contact corrections ), the money format, dialogs
 * and keyboard handling. The older sections still drive WP EasyCart PRO's forms through its handlers.
 */

/* ---------- Header menus ( Print, ⋯, Status ) and the items' More menu: one open at a time ---------- */
function ecodv2_menu_toggle( btn, id ) {
	var menu = document.getElementById( id || 'ecodv2_header_menu' );
	if ( ! menu ) {
		return false;
	}
	var open = ! menu.classList.contains( 'is-open' );
	ecodv2_menu_close();
	if ( open ) {
		menu.classList.add( 'is-open' );
		if ( btn && btn.setAttribute ) {
			btn.setAttribute( 'aria-expanded', 'true' );
		}
	}
	return false;
}

function ecodv2_menu_close() {
	var menus = document.querySelectorAll( '.ecodv2-header .ecdv2-menu.is-open, #ecodv2_toolbar_menu.is-open' );
	var i;
	for ( i = 0; i < menus.length; i++ ) {
		menus[ i ].classList.remove( 'is-open' );
	}
	var buttons = document.querySelectorAll( '.ecodv2-header [aria-expanded="true"], .ecodv2-toolbar-more [aria-expanded="true"]' );
	for ( i = 0; i < buttons.length; i++ ) {
		buttons[ i ].setAttribute( 'aria-expanded', 'false' );
	}
}

/* 6.0.0 — send dialog for the order emails.
   To starts as the order's current email ( read from the Edit Order form when it is on the page, so an address changed
   there is used without a reload ), Cc as its second email; Bcc starts empty. The server sends to exactly these.
   6.0.1 — one dialog for every order email: the Email choice switches between the order receipt and the order shipped
   email ( WP EasyCart PRO adds the packing slip email ), Preview renders what will go out, and PRO adds its own sections
   ( content profile, adjustments, items in this box, attachments ) through two registries:
     window.ecodv2_send_kinds[ key ]  = { label, button, send( payload, done ) }
     window.ecodv2_send_extensions[]  = { render( ctx ), collect( ctx ) → object }   ctx = { kind, order_id, $root, T }
   Whatever the extensions collect is posted as JSON in 'documents' ( read by the wp_easycart_order_send_args filter ). */
function ecodv2_email_i18n() {
	if ( ! window.ecodv2_email_strings ) {
		var el = document.getElementById( 'ecodv2_email_i18n' ), parsed = {};
		try { parsed = el ? JSON.parse( el.textContent ) : {}; } catch ( e ) { parsed = {}; }
		window.ecodv2_email_strings = parsed;
	}
	return window.ecodv2_email_strings;
}

function ecodv2_order_current_email( link, attr, input_id ) {
	var input = document.getElementById( input_id );
	if ( input ) {
		return String( input.value || '' ).trim();
	}
	return ( link && link.getAttribute( attr ) ) ? String( link.getAttribute( attr ) ).trim() : '';
}

window.ecodv2_send_kinds = window.ecodv2_send_kinds || {};
window.ecodv2_send_extensions = window.ecodv2_send_extensions || [];

/* Post one of the dialog's sends; reports through the V2 toast and refreshes the order history on success. */
function ecodv2_send_post( action, payload, done, fallback_message ) {
	var T = ecodv2_email_i18n();
	var data = {
		action: action,
		order_id: T.order_id || jQuery( document.getElementById( 'order_id' ) ).val(),
		wp_easycart_nonce: T.nonce || '',
		to: payload.to,
		cc: payload.cc,
		bcc: payload.bcc
	};
	if ( payload.documents ) {
		data.documents = payload.documents;
	}
	jQuery.post( wpeasycart_admin_ajax_object.ajax_url, data, function( response ) {
		if ( response && response.success ) {
			done( true );
			ecodv2_save_toast( ( response.data && response.data.message ) ? response.data.message : fallback_message );
			if ( jQuery( document.getElementById( 'wpeasycart_order_history_refresh' ) ).length && 'function' === typeof window.ec_order_history_refresh ) {
				window.ec_order_history_refresh();
			}
			/* 6.0.2 bug round 6: what the email changed shows at once ( an invoice sent: "Invoice sent", Resend invoice, and a
			   draft is now awaiting payment ). */
			if ( document.getElementById( 'ecodv2_next_panel' ) && 'function' === typeof window.ecodv2_screen_refresh ) {
				window.ecodv2_screen_refresh( null, true );
			}
		} else {
			done( false, ( response && response.data && response.data.message ) ? response.data.message : '', ( response && response.data && response.data.field ) ? response.data.field : '' );
		}
	}, 'json' ).fail( function() { done( false ); } );
}

/* The emails the dialog can send, in order. Without PRO the packing slip email shows locked ( upgrade popup ). */
function ecodv2_send_kind_list() {
	var T = ecodv2_email_i18n(), extra = window.ecodv2_send_kinds || {}, list = [];
	list.push( {
		key: 'receipt',
		label: T.kind_receipt || 'Order receipt',
		button: T.receipt_button || 'Send receipt',
		send: function( payload, done ) { ecodv2_send_post( 'ecv2_order_resend_receipt', payload, done, 'Order receipt sent.' ); }
	} );
	list.push( {
		key: 'shipped',
		label: T.kind_shipped || 'Order shipped',
		button: T.shipped_button || 'Send shipped email',
		send: function( payload, done ) { ec_admin_send_order_shipped_email( true, payload, done ); }
	} );
	for ( var key in extra ) {
		if ( Object.prototype.hasOwnProperty.call( extra, key ) && extra[ key ] && 'function' === typeof extra[ key ].send ) {
			list.push( jQuery.extend( { key: key }, extra[ key ] ) );
		}
	}
	if ( ! extra.packing_slip && ! T.pro ) {
		list.push( { key: 'packing_slip', label: T.kind_packing_slip || 'Packing slip', locked: true } );
	}
	return list;
}

/* opts: kind ( receipt | shipped | packing_slip ), to, cc. */
function ecodv2_email_dialog( opts ) {
	var T = ecodv2_email_i18n(), $ = jQuery;
	var text = function( k, fallback ) { return T[ k ] || fallback; };
	var kinds = ecodv2_send_kind_list(), by_key = {}, i;
	for ( i = 0; i < kinds.length; i++ ) { by_key[ kinds[ i ].key ] = kinds[ i ]; }
	var kind = ( by_key[ opts.kind ] && ! by_key[ opts.kind ].locked ) ? opts.kind : 'receipt';
	var exts = ( window.ecodv2_send_extensions || [] ).filter( function( ext ) { return ext && 'function' === typeof ext.render; } );

	$( '#ecodv2_email_dialog' ).remove();
	/* 6.0.2: the email to send is one clear choice ( a picker with an icon and a line on each email ) rather than a row of
	   pills that did not read as choices ( owner bug round 4, item 3 ). An email type may bring its own icon and desc. */
	var icons = { receipt: 'media-text', shipped: 'car', packing_slip: 'clipboard', invoice: 'media-spreadsheet', gift_receipt: 'heart', message: 'edit' };
	var kind_icon = function( k ) { return 'dashicons-' + ( k.icon || icons[ k.key ] || 'email-alt' ); };
	var kind_desc = function( k ) { return k.desc || T[ 'desc_' + k.key ] || ''; };
	var options = '';
	for ( i = 0; i < kinds.length; i++ ) {
		options += '<li class="ecodv2-send-choice' + ( kinds[ i ].locked ? ' is-locked' : '' ) + '" role="option" id="ecodv2_send_choice_' + ecodv2_esc( kinds[ i ].key ) + '" aria-selected="false" ' +
			( kinds[ i ].locked ? 'aria-disabled="true" data-locked="attachments"' : 'data-kind="' + ecodv2_esc( kinds[ i ].key ) + '"' ) + '>' +
			'<span class="ecodv2-send-choice-icon dashicons ' + ecodv2_esc( kind_icon( kinds[ i ] ) ) + '" aria-hidden="true"></span>' +
			'<span class="ecodv2-send-choice-text"><b>' + ecodv2_esc( kinds[ i ].label ) + '</b>' + ( kind_desc( kinds[ i ] ) ? '<small>' + ecodv2_esc( kind_desc( kinds[ i ] ) ) + '</small>' : '' ) + '</span>' +
			( kinds[ i ].locked ? '<span class="ecodv2-send-pro">' + ecodv2_esc( text( 'pro_badge', 'Pro' ) ) + '</span>' : '<span class="ecodv2-send-choice-check dashicons dashicons-yes" aria-hidden="true"></span>' ) +
		'</li>';
	}
	/* 6.0.2: the recipients read as "To  dana@example.com" and open for editing on a click; Cc and Bcc stay out of the way
	   until asked for ( owner bug round 4, item 4 ). The inputs keep their ids: sending and errors read them. */
	var rcpt = function( key, label, value, shown ) {
		return '<div class="ecodv2-send-rcpt' + ( shown ? '' : ' is-hidden' ) + ( '' === ( value || '' ) ? ' is-editing' : '' ) + '" data-rcpt="' + key + '">' +
			'<label class="ecodv2-send-rcpt-label" for="ecodv2_send_' + key + '">' + ecodv2_esc( label ) + '</label>' +
			'<button type="button" class="ecodv2-send-rcpt-view" data-rcpt-edit="' + key + '" title="' + ecodv2_esc( text( 'edit', 'Edit' ) ) + '"><span class="ecodv2-send-rcpt-value">' + ecodv2_esc( value || '' ) + '</span><span class="dashicons dashicons-edit" aria-hidden="true"></span><span class="screen-reader-text">' + ecodv2_esc( text( 'edit', 'Edit' ) + ' ' + label ) + '</span></button>' +
			'<input type="text" class="ecv2-input" id="ecodv2_send_' + key + '" value="' + ecodv2_esc( value || '' ) + '" autocomplete="off" spellcheck="false" inputmode="email" placeholder="' + ecodv2_esc( text( 'no_address', 'Add an email address' ) ) + '">' +
		'</div>';
	};
	var has_cc = '' !== jQuery.trim( opts.cc || '' );
	/* T.pro: the document sections are unlocked ( a PRO without a licence still registers its Message section ). */
	var locked_strip = ( ! T.pro ) ?
		'<button type="button" class="ecodv2-send-more is-locked" data-locked="send"><span class="ecodv2-send-more-icon dashicons dashicons-paperclip" aria-hidden="true"></span><span class="ecodv2-send-more-text"><b>' + ecodv2_esc( text( 'more_title', 'Attachments, content and items' ) ) + '</b><span>' + ecodv2_esc( text( 'more_desc', '' ) ) + '</span></span><span class="ecodv2-send-pro">' + ecodv2_esc( text( 'pro_badge', 'Pro' ) ) + '</span></button>' : '';
	var $m = $(
		'<div class="ecv2-modal-overlay ecodv2-send-overlay" id="ecodv2_email_dialog" role="dialog" aria-modal="true" aria-labelledby="ecodv2_send_title">' +
			'<div class="ecv2-modal ecodv2-send-modal' + ( exts.length ? ' has-ext' : '' ) + '">' +
				'<div class="ecv2-modal-header"><h2 id="ecodv2_send_title">' + ecodv2_esc( text( 'send_title', 'Send email' ) ) + ( T.order_id ? ' <span class="ecodv2-send-order">#' + ecodv2_esc( T.order_id ) + '</span>' : '' ) + '</h2><button type="button" class="ecv2-modal-close" data-close aria-label="' + ecodv2_esc( text( 'close', 'Close' ) ) + '">&times;</button></div>' +
				'<div class="ecv2-modal-body">' +
					'<div class="ecodv2-send-field"><span class="ecodv2-send-label" id="ecodv2_send_kind_label">' + ecodv2_esc( text( 'kind_label', 'Email' ) ) + '</span>' +
						'<div class="ecodv2-send-picker">' +
							'<button type="button" class="ecodv2-send-pick" id="ecodv2_send_pick" aria-haspopup="listbox" aria-expanded="false" aria-controls="ecodv2_send_menu" aria-labelledby="ecodv2_send_kind_label ecodv2_send_pick" title="' + ecodv2_esc( text( 'choose_email', 'Choose the email to send' ) ) + '">' +
								'<span class="ecodv2-send-choice-icon dashicons" aria-hidden="true"></span><span class="ecodv2-send-choice-text"><b></b><small></small></span><span class="ecodv2-send-pick-caret dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>' +
							'</button>' +
							'<ul class="ecodv2-send-menu" id="ecodv2_send_menu" role="listbox" tabindex="-1" aria-labelledby="ecodv2_send_kind_label" hidden>' + options + '</ul>' +
						'</div>' +
					'</div>' +
					'<div class="ecodv2-send-rcpts">' +
						rcpt( 'to', text( 'to', 'To' ), opts.to, true ) +
						rcpt( 'cc', text( 'cc', 'Cc' ), opts.cc, has_cc ) +
						rcpt( 'bcc', text( 'bcc', 'Bcc' ), '', false ) +
						'<div class="ecodv2-send-rcpt-add">' +
							'<button type="button" class="ecodv2-send-rcpt-more" data-rcpt-add="cc"' + ( has_cc ? ' hidden' : '' ) + '>+ ' + ecodv2_esc( text( 'add_cc', 'Cc' ) ) + '</button>' +
							'<button type="button" class="ecodv2-send-rcpt-more" data-rcpt-add="bcc">+ ' + ecodv2_esc( text( 'add_bcc', 'Bcc' ) ) + '</button>' +
						'</div>' +
						'<p class="ecodv2-send-hint" hidden>' + ecodv2_esc( text( 'hint', 'Separate several addresses with commas.' ) ) + '</p>' +
					'</div>' +
					'<div class="ecodv2-send-ext"></div>' +
					locked_strip +
					'<p class="ecodv2-send-error" role="alert" hidden></p>' +
				'</div>' +
				'<div class="ecv2-modal-footer">' +
					'<button type="button" class="ecv2-btn ecv2-btn-ghost" id="ecodv2_send_preview">' + ecodv2_esc( text( 'preview', 'Preview' ) ) + '</button>' +
					'<div class="ecv2-modal-footer-right">' +
						'<button type="button" class="ecv2-btn ecv2-btn-ghost" data-close>' + ecodv2_esc( text( 'cancel', 'Cancel' ) ) + '</button>' +
						'<button type="button" class="ecv2-btn ecv2-btn-primary" id="ecodv2_send_go"></button>' +
					'</div>' +
				'</div>' +
			'</div>' +
		'</div>'
	);
	var $go = $m.find( '#ecodv2_send_go' ), $err = $m.find( '.ecodv2-send-error' ), $ext = $m.find( '.ecodv2-send-ext' );
	var ctx = function() { return { kind: kind, order_id: T.order_id || '', $root: $ext, T: T }; };
	var close = function() { $( document ).off( 'keydown.ecodv2send' ); $( window ).off( 'resize.ecodv2send' ); $m.remove(); };
	var show_error = function( message, key ) {
		$err.text( message ).prop( 'hidden', false );
		/* Only the address fields: a section's own field ( PRO Message subject / text ) keeps the mark its send put on it. */
		$m.find( '#ecodv2_send_to, #ecodv2_send_cc, #ecodv2_send_bcc' ).removeClass( 'is-invalid' );
		if ( key ) { open_rcpt( key ); $m.find( '#ecodv2_send_' + key ).addClass( 'is-invalid' ).trigger( 'focus' ); }
	};
	/* 6.0.2: a recipient row opens its input ( and the Cc / Bcc links reveal theirs ). */
	var open_rcpt = function( key ) {
		var $row = $m.find( '.ecodv2-send-rcpt[data-rcpt="' + key + '"]' );
		$row.removeClass( 'is-hidden' ).addClass( 'is-editing' );
		$m.find( '[data-rcpt-add="' + key + '"]' ).prop( 'hidden', true );
		$m.find( '.ecodv2-send-hint' ).prop( 'hidden', false );
		return $row.find( 'input' );
	};
	/* 6.0.2: the email picker ( a listbox under a button ). Bug round 6: the list floats over the dialog rather than pushing
	   it down: it is placed against the window under the button ( above it when there is more room there ), so the dialog's
	   own scrolling never cuts it off, and it follows the button while the dialog scrolls or the window changes size. */
	var $pick = $m.find( '#ecodv2_send_pick' ), $menu = $m.find( '#ecodv2_send_menu' );
	var place_menu = function() {
		if ( $menu.prop( 'hidden' ) || ! $pick.length ) {
			return;
		}
		var box = $pick[0].getBoundingClientRect(), view = window.innerHeight || document.documentElement.clientHeight || 0;
		var below = view - box.bottom - 16, above = box.top - 16, up = below < 200 && above > below;
		var room = Math.max( 120, Math.min( 320, up ? above : below ) );
		$menu.toggleClass( 'is-up', up ).css( {
			left: Math.round( box.left ) + 'px',
			width: Math.round( box.width ) + 'px',
			top: up ? 'auto' : Math.round( box.bottom + 6 ) + 'px',
			bottom: up ? Math.round( view - box.top + 6 ) + 'px' : 'auto',
			maxHeight: Math.round( room ) + 'px'
		} );
	};
	var menu_open = function( on ) {
		$menu.prop( 'hidden', ! on );
		$pick.attr( 'aria-expanded', on ? 'true' : 'false' );
		if ( on ) {
			place_menu();
			var $cur = $menu.find( '.ecodv2-send-choice[data-kind="' + kind + '"]' );
			$menu.find( '.ecodv2-send-choice' ).removeClass( 'is-focus' );
			$cur.addClass( 'is-focus' );
			$menu.attr( 'aria-activedescendant', $cur.attr( 'id' ) || null ).trigger( 'focus' );
		} else {
			$menu.removeAttr( 'aria-activedescendant' );
		}
	};
	var menu_move = function( step ) {
		var $opts = $menu.find( '.ecodv2-send-choice' ), at = $opts.index( $opts.filter( '.is-focus' ) ), next = Math.max( 0, Math.min( $opts.length - 1, at + step ) );
		$opts.removeClass( 'is-focus' ).eq( next ).addClass( 'is-focus' );
		$menu.attr( 'aria-activedescendant', $opts.eq( next ).attr( 'id' ) );
		var el = $opts.get( next );
		if ( el && el.scrollIntoView ) { el.scrollIntoView( { block: 'nearest' } ); }
	};
	/* Everything the extensions collected for this send, as the JSON the server reads ( '' when there is nothing ). */
	var documents = function() {
		var merged = {}, any = false;
		exts.forEach( function( ext ) {
			if ( 'function' !== typeof ext.collect ) { return; }
			var part = ext.collect( ctx() );
			if ( part && 'object' === typeof part ) { $.extend( merged, part ); any = true; }
		} );
		return any ? JSON.stringify( merged ) : '';
	};
	var set_kind = function( next ) {
		kind = next;
		$m.find( '.ecodv2-send-choice[data-kind]' ).each( function() {
			var on = $( this ).attr( 'data-kind' ) === kind;
			$( this ).toggleClass( 'is-active', on ).attr( 'aria-selected', on ? 'true' : 'false' );
		} );
		$pick.find( '.ecodv2-send-choice-icon' ).attr( 'class', 'ecodv2-send-choice-icon dashicons ' + kind_icon( by_key[ kind ] ) );
		$pick.find( '.ecodv2-send-choice-text b' ).text( by_key[ kind ].label );
		$pick.find( '.ecodv2-send-choice-text small' ).text( kind_desc( by_key[ kind ] ) ).prop( 'hidden', '' === kind_desc( by_key[ kind ] ) );
		$go.text( by_key[ kind ].button );
		/* A type can have no document to preview ( PRO: a written message ). */
		$m.find( '#ecodv2_send_preview' ).prop( 'hidden', false === by_key[ kind ].preview );
		$err.prop( 'hidden', true );
		$ext.empty();
		exts.forEach( function( ext ) { ext.render( ctx() ); } );
		/* A type can change a recipient ( WP EasyCart PRO: the gift receipt goes to the gift recipient ): each row shows
		   the address it will send to. */
		$m.find( '.ecodv2-send-rcpt' ).each( function() {
			var $r = $( this ), v = $.trim( $r.find( 'input' ).val() || '' );
			$r.find( '.ecodv2-send-rcpt-value' ).text( v );
			if ( '' === v ) {
				$r.addClass( 'is-editing' );
			}
		} );
	};
	var go = function() {
		var payload = { to: $.trim( $m.find( '#ecodv2_send_to' ).val() ), cc: $.trim( $m.find( '#ecodv2_send_cc' ).val() ), bcc: $.trim( $m.find( '#ecodv2_send_bcc' ).val() ), documents: documents() };
		if ( '' === payload.to ) { show_error( text( 'need_to', 'Enter at least one email address to send to.' ), 'to' ); return; }
		$err.prop( 'hidden', true );
		$go.prop( 'disabled', true ).text( text( 'sending', 'Sending…' ) );
		by_key[ kind ].send( payload, function( ok, message, key ) {
			if ( ok ) { close(); return; }
			$go.prop( 'disabled', false ).text( by_key[ kind ].button );
			show_error( message || text( 'failed', 'The email could not be sent.' ), key );
		} );
	};
	var preview = function() {
		var $btn = $m.find( '#ecodv2_send_preview' ).prop( 'disabled', true );
		$err.prop( 'hidden', true );
		jQuery.post( wpeasycart_admin_ajax_object.ajax_url, {
			action: 'ecv2_order_email_preview',
			order_id: T.order_id || jQuery( document.getElementById( 'order_id' ) ).val(),
			wp_easycart_nonce: T.nonce || '',
			email: kind,
			documents: documents()
		}, function( response ) {
			if ( response && response.success && response.data && response.data.html ) {
				ecodv2_email_preview( response.data.html, by_key[ kind ].label );
			} else {
				show_error( ( response && response.data && response.data.message ) ? response.data.message : text( 'preview_failed', 'The preview could not be loaded.' ) );
			}
		}, 'json' ).fail( function() {
			show_error( text( 'preview_failed', 'The preview could not be loaded.' ) );
		} ).always( function() { $btn.prop( 'disabled', false ); } );
	};
	$m.on( 'click', function( e ) { if ( $( e.target ).is( $m ) || $( e.target ).is( '[data-close]' ) ) { close(); } } );
	$pick.on( 'click', function() { menu_open( $menu.prop( 'hidden' ) ); } );
	$pick.on( 'keydown', function( e ) {
		if ( 'ArrowDown' === e.key || 'ArrowUp' === e.key ) { e.preventDefault(); menu_open( true ); }
	} );
	$m.on( 'click', '.ecodv2-send-choice[data-kind]', function() { set_kind( $( this ).attr( 'data-kind' ) ); menu_open( false ); $pick.trigger( 'focus' ); } );
	$m.on( 'mousemove', '.ecodv2-send-choice', function() {
		$menu.find( '.ecodv2-send-choice' ).removeClass( 'is-focus' );
		$( this ).addClass( 'is-focus' );
	} );
	$menu.on( 'keydown', function( e ) {
		if ( 'ArrowDown' === e.key ) { e.preventDefault(); menu_move( 1 ); }
		else if ( 'ArrowUp' === e.key ) { e.preventDefault(); menu_move( -1 ); }
		else if ( 'Home' === e.key ) { e.preventDefault(); menu_move( -99 ); }
		else if ( 'End' === e.key ) { e.preventDefault(); menu_move( 99 ); }
		else if ( 'Enter' === e.key || ' ' === e.key ) { e.preventDefault(); $menu.find( '.ecodv2-send-choice.is-focus' ).trigger( 'click' ); }
		else if ( 'Escape' === e.key || 'Tab' === e.key ) {
			/* Escape closes the list, not the dialog. */
			if ( 'Escape' === e.key ) { e.preventDefault(); e.stopPropagation(); }
			menu_open( false );
			$pick.trigger( 'focus' );
		}
	} );
	$m.on( 'mousedown', function( e ) {
		if ( ! $menu.prop( 'hidden' ) && ! $( e.target ).closest( '.ecodv2-send-picker' ).length ) { menu_open( false ); }
	} );
	/* The floating list follows its button ( bug round 6 ): the dialog scrolls ( caught on the way down ) or the window resizes. */
	$m[0].addEventListener( 'scroll', place_menu, true );
	$( window ).on( 'resize.ecodv2send', place_menu );
	$m.on( 'click', '[data-rcpt-edit]', function() { open_rcpt( $( this ).attr( 'data-rcpt-edit' ) ).trigger( 'focus' ).select(); } );
	$m.on( 'click', '[data-rcpt-add]', function() { open_rcpt( $( this ).attr( 'data-rcpt-add' ) ).trigger( 'focus' ); } );
	$m.on( 'click', '[data-locked]', function() {
		/* The upgrade popup shares the modal layer; close this dialog first so it does not cover the popup. */
		var feature = $( this ).attr( 'data-locked' );
		close();
		if ( 'function' === typeof window.ecdv2_upsell ) { window.ecdv2_upsell( { context: 'documents', feature: feature } ); }
		return false;
	} );
	/* 6.0.2: Enter never sends ( an address typed and Enter pressed went out at once ). In an address it shows the address
	   and moves to Send, so a second Enter sends; in a text area ( PRO: a written message ) it starts a new line. */
	$m.on( 'keydown', 'input.ecv2-input', function( e ) {
		if ( 'Enter' !== e.key ) { return; }
		e.preventDefault();
		var $row = $( this ).closest( '.ecodv2-send-rcpt' ), typed = $.trim( this.value );
		if ( $row.length && '' !== typed ) {
			$row.find( '.ecodv2-send-rcpt-value' ).text( typed );
			$row.removeClass( 'is-editing' );
		}
		$go.trigger( 'focus' );
	} );
	$go.on( 'click', go );
	$m.find( '#ecodv2_send_preview' ).on( 'click', preview );
	$( document ).on( 'keydown.ecodv2send', function( e ) { if ( 'Escape' === e.key && ! $( '#ecodv2_email_preview' ).length && $menu.prop( 'hidden' ) ) { close(); } } );
	$( 'body' ).append( $m );
	set_kind( kind );
	/* With an address already filled in, the email picker takes the focus; otherwise the To box does. */
	setTimeout( function() {
		if ( '' === $.trim( $m.find( '#ecodv2_send_to' ).val() ) ) {
			$m.find( '#ecodv2_send_to' ).trigger( 'focus' );
		} else {
			$pick.trigger( 'focus' );
		}
	}, 30 );
}

/* The rendered email, over the send dialog. */
function ecodv2_email_preview( html, title ) {
	var T = ecodv2_email_i18n(), $ = jQuery;
	$( '#ecodv2_email_preview' ).remove();
	var $p = $(
		'<div class="ecv2-modal-overlay ecodv2-preview-overlay" id="ecodv2_email_preview" role="dialog" aria-modal="true" aria-labelledby="ecodv2_preview_title">' +
			'<div class="ecv2-modal ecodv2-preview-modal">' +
				'<div class="ecv2-modal-header"><h2 id="ecodv2_preview_title">' + ecodv2_esc( ( T.preview || 'Preview' ) + ' · ' + title ) + '</h2><button type="button" class="ecv2-modal-close" data-close aria-label="' + ecodv2_esc( T.close || 'Close' ) + '">&times;</button></div>' +
				'<div class="ecv2-modal-body"><iframe class="ecodv2-preview-frame" sandbox="allow-same-origin" title="' + ecodv2_esc( title ) + '"></iframe></div>' +
			'</div>' +
		'</div>'
	);
	var close = function() { $( document ).off( 'keydown.ecodv2preview' ); $p.remove(); };
	$p.on( 'click', function( e ) { if ( $( e.target ).is( $p ) || $( e.target ).closest( '[data-close]' ).length ) { close(); } } );
	$( document ).on( 'keydown.ecodv2preview', function( e ) { if ( 'Escape' === e.key ) { close(); } } );
	$( 'body' ).append( $p );
	$p.find( 'iframe' )[0].srcdoc = html;
	$p.find( '[data-close]' ).trigger( 'focus' );
}

function ecodv2_esc( s ) {
	return String( s == null ? '' : s ).replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' ).replace( /"/g, '&quot;' ).replace( /'/g, '&#39;' );
}

/* Open the send dialog on one email ( the header menu's Send Email, the fulfillment card ). */
function ecodv2_send_dialog( link, kind ) {
	ecodv2_menu_close();
	ecodv2_email_dialog( {
		kind: kind || ( link && link.getAttribute( 'data-kind' ) ) || 'receipt',
		to: ecodv2_order_current_email( link, 'data-email', 'user_email' ),
		cc: ecodv2_order_current_email( link, 'data-email-other', 'email_other' )
	} );
	return false;
}

/* 6.0.0 entry points, kept for anything that still calls them. */
function ecodv2_resend_receipt( link ) {
	return ecodv2_send_dialog( link, 'receipt' );
}

function ecodv2_send_shipped_dialog( link ) {
	return ecodv2_send_dialog( link, 'shipped' );
}

/* 6.0.0 — after the order shipped email goes out, leave a line on the fulfillment card so the
   screen shows what happened without a reload ( the toast is transient ). */
function ecodv2_shipped_email_sent( data ) {
	/* 6.0.2: in the next step panel ( the banner of the older layout is hidden ). */
	var host = document.querySelector( '#ecodv2_next_panel .ecodv2-next-main' ) || document.getElementById( 'ecodv2_fulfill_banner' );
	if ( ! host ) {
		return;
	}
	var note = document.getElementById( 'ecodv2_shipped_email_note' );
	if ( ! note ) {
		note = document.createElement( 'div' );
		note.id = 'ecodv2_shipped_email_note';
		note.className = 'ecodv2-shipped-note';
		host.appendChild( note );
	}
	var email = ( data && data.email ) ? String( data.email ) : '';
	var other = ( data && data.email_other ) ? String( data.email_other ) : '';
	var to = email + ( other ? ', ' + other : '' );
	var text = to ? ecodv2_t( 'shipped_sent', 'Shipped email sent to %s' ).replace( '%s', to ) : ecodv2_t( 'shipped_sent_bare', 'Shipped email sent' );
	note.innerHTML = '';
	var icon = document.createElement( 'span' );
	icon.className = 'dashicons dashicons-yes-alt';
	var msg = document.createElement( 'span' );
	msg.textContent = text;
	note.appendChild( icon );
	note.appendChild( msg );
	note.style.display = '';
}

/* ---------- Items toolbar: More menu ---------- */
function ecodv2_toolbar_more_toggle( btn ) {
	return ecodv2_menu_toggle( btn, 'ecodv2_toolbar_menu' );
}

/* ---------- Offers panel: summary line <-> detail ---------- */
function ecodv2_offers_toggle( line ) {
	/* 6.0.2: the Discount row's button opens the panel after it ( offers, coupon, gift card ). */
	var panel = line.getAttribute( 'aria-controls' ) ? document.getElementById( line.getAttribute( 'aria-controls' ) ) : line.closest( '.ecodv2-offers-panel' );
	if ( panel ) {
		var open = ! panel.classList.contains( 'is-open' );
		panel.classList.toggle( 'is-open', open );
		line.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
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
		/* 6.0.2: never while a dialog, drawer, menu or editor is open ( it left the page with the change unsaved ). */
		if ( ecodv2_layer_open() ) {
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
		/* 6.0.2: the order's tip is part of the grand total ( it has no input here ), and VAT a store includes in its prices
		   is not added on top ( data-tip / data-vat-included, printed by WP EasyCart PRO's editor; absent means 0 ). */
		var form = document.getElementById( 'ec_admin_order_details_totals_form' );
		var tip = form ? ( parseFloat( form.getAttribute( 'data-tip' ) ) || 0 ) : 0;
		var vat_on_top = ! ( form && '1' === form.getAttribute( 'data-vat-included' ) );
		var computed = ecodv2_num( 'sub_total' ) + ecodv2_num( 'tax_total' )
			+ ( vat_on_top ? ecodv2_num( 'vat_total' ) : 0 ) + ecodv2_num( 'gst_total' ) + ecodv2_num( 'hst_total' )
			+ ecodv2_num( 'pst_total' ) + ecodv2_num( 'duty_total' )
			+ ecodv2_num( 'shipping_total' ) - ecodv2_num( 'discount_total' ) + tip;
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

/* --------------------------------------------------------------------
   V2.10 fix — the drawer saves through the legacy PRO handlers bound to
   the hidden .ecodv2-visually-hidden controls ( orders-pro.js /
   orders.js ). Those handlers are TWO-STATE TOGGLES: with their *_show
   flag false, a click enters "edit mode" ( hides the on-page view block,
   saves NOTHING ) and only a second click saves. The drawer never called
   the toggles on open, so every section's flag was still false when
   ecodv2_drawer_save() fired its single click — nothing persisted and
   the view blocks vanished. The fulfillment section already had a
   one-off arm wrapper; this arms every section, by flag assignment, so
   no view block is ever hidden as a side effect.
   -------------------------------------------------------------------- */
var ECODV2_LEGACY_TOGGLE_FLAGS = [
	'ec_admin_order_details_save_show',            /* customer / payment info  ( #ec_admin_order_details_save ) */
	'ec_admin_order_details_billing_show',         /* billing address          ( #ec_admin_order_details_billing_info_save ) */
	'ec_admin_order_details_shipping_show',        /* shipping address         ( #ec_admin_order_details_shipping_info_save ) */
	'ec_admin_order_details_save_bottom_show',     /* additional details       ( #ec_admin_order_details_save_bottom ) */
	'ec_admin_order_details_shipping_method_show'  /* fulfillment ( free )     ( #ec_admin_order_details_shipping_method_save ) */
];

function ecodv2_arm_legacy_toggles() {
	for ( var i = 0; i < ECODV2_LEGACY_TOGGLE_FLAGS.length; i++ ) {
		if ( 'undefined' !== typeof window[ ECODV2_LEGACY_TOGGLE_FLAGS[ i ] ] ) {
			window[ ECODV2_LEGACY_TOGGLE_FLAGS[ i ] ] = true;
		}
	}
	/* orders-pro.js reads this localized object in the details-bottom save;
	   if a build ships without it the handler throws mid-loop and aborts
	   the remaining sections' saves. Provide safe defaults. */
	if ( 'undefined' === typeof window.wp_easycart_pro_admin_orders_language ) {
		window.wp_easycart_pro_admin_orders_language = {};
	}
	if ( ! window.wp_easycart_pro_admin_orders_language[ 'agree-terms-yes' ] ) {
		window.wp_easycart_pro_admin_orders_language[ 'agree-terms-yes' ] = 'Agreed to Terms: Yes';
	}
	if ( ! window.wp_easycart_pro_admin_orders_language[ 'agree-terms-no' ] ) {
		window.wp_easycart_pro_admin_orders_language[ 'agree-terms-no' ] = 'Agreed to Terms: No';
	}
}

/* Re-show every V2 view block a stray legacy "edit mode" pass may have
   hidden ( repairs pages already broken by the pre-fix behavior too ). */
function ecodv2_drawer_restore_views() {
	jQuery( '#ec_admin_view_order_information, #ec_admin_view_order_information_bottom, #ec_admin_order_details_billing_content, #ec_admin_order_details_shipping_content, #ec_admin_view_shipping_method' ).show();
}

/* --------------------------------------------------------------------
   V2.11 — drawer-native saving indicator.
   The legacy section handlers fade in the V1 full-page preloaders
   ( #ec_admin_shipping_details / #ec_admin_order_management ), which
   look wrong behind the V2 drawer. While a drawer save is in flight we
   suppress those overlays ( body.ecodv2-drawer-saving, see the CSS ),
   show the progress on the drawer's own Save button, keep the drawer
   open until every section's request lands, then flash "Saved" and
   close with a toast. Failures keep the drawer open, keep the dirty
   state, and surface an error toast instead of silently closing.
   -------------------------------------------------------------------- */
var ecodv2_drawer_saving = false;
var ecodv2_drawer_pending = 0;
var ecodv2_drawer_seen = 0;
var ecodv2_drawer_failed = false;
var ecodv2_drawer_watchdog = null;
var ecodv2_drawer_save_label = null;

/* Every ajax action a drawer section can post through the legacy handlers.
   The "_bottom" action sits first so the shorter management-details name
   never shadows it during matching. */
var ECODV2_DRAWER_SAVE_ACTIONS = [
	'ec_admin_ajax_save_order_management_details_bottom',
	'ec_admin_ajax_save_order_management_details',
	'ec_admin_ajax_save_order_billing_address',
	'ec_admin_ajax_save_order_shipping_address',
	'ec_admin_ajax_edit_shipping_method_info',
	'ec_admin_ajax_edit_order_info' /* 6.0.2: the Codes section */
];

/* 6.0.2: the section each save belongs to, for the message when one fails. */
var ECODV2_DRAWER_SAVE_SECTIONS = {
	ec_admin_ajax_save_order_management_details_bottom: 'section_details',
	ec_admin_ajax_save_order_management_details: 'section_customer',
	ec_admin_ajax_save_order_billing_address: 'section_billing',
	ec_admin_ajax_save_order_shipping_address: 'section_shipping',
	ec_admin_ajax_edit_shipping_method_info: 'section_fulfill',
	ec_admin_ajax_edit_order_info: 'section_codes'
};
var ecodv2_drawer_failed_sections = [];

function ecodv2_drawer_note_failure( settings ) {
	var data = ( settings && 'string' === typeof settings.data ) ? settings.data : '';
	for ( var i = 0; i < ECODV2_DRAWER_SAVE_ACTIONS.length; i++ ) {
		if ( -1 !== data.indexOf( 'action=' + ECODV2_DRAWER_SAVE_ACTIONS[ i ] ) ) {
			var name = ecodv2_t( ECODV2_DRAWER_SAVE_SECTIONS[ ECODV2_DRAWER_SAVE_ACTIONS[ i ] ], '' );
			if ( '' !== name && -1 === ecodv2_drawer_failed_sections.indexOf( name ) ) {
				ecodv2_drawer_failed_sections.push( name );
			}
			return;
		}
	}
}

function ecodv2_is_drawer_save_request( settings ) {
	var data = ( settings && 'string' === typeof settings.data ) ? settings.data : '';
	if ( '' === data ) {
		return false;
	}
	for ( var i = 0; i < ECODV2_DRAWER_SAVE_ACTIONS.length; i++ ) {
		if ( -1 !== data.indexOf( 'action=' + ECODV2_DRAWER_SAVE_ACTIONS[ i ] ) ) {
			return true;
		}
	}
	return false;
}

function ecodv2_suppress_legacy_loaders() {
	/* The handlers call fadeIn synchronously inside the triggered clicks;
	   stop the animation and hide. The body class keeps them hidden via
	   CSS for the whole in-flight window. */
	jQuery( '#ec_admin_shipping_details, #ec_admin_order_management' ).stop( true, true ).hide();
}

/* The order screen's one toast ( 6.0.2: the list's ecv2_toast is routed here too ). action: { label, fn } adds a button
   ( Undo ). Errors are announced at once ( role alert ) and stay longer. */
function ecodv2_save_toast( message, is_error, action ) {
	var toast = document.getElementById( 'ecodv2_save_toast' );
	if ( ! toast ) {
		toast = document.createElement( 'div' );
		toast.id = 'ecodv2_save_toast';
		toast.className = 'ecodv2-save-toast';
		toast.innerHTML = '<span class="dashicons" aria-hidden="true"></span><span class="ecodv2-save-toast-msg"></span><button type="button" class="ecodv2-save-toast-act" hidden></button>';
		document.body.appendChild( toast );
	}
	toast.setAttribute( 'role', is_error ? 'alert' : 'status' );
	toast.setAttribute( 'aria-live', is_error ? 'assertive' : 'polite' );
	toast.classList.toggle( 'is-error', !! is_error );
	toast.querySelector( '.dashicons' ).className = 'dashicons ' + ( is_error ? 'dashicons-warning' : 'dashicons-yes-alt' );
	toast.querySelector( '.ecodv2-save-toast-msg' ).textContent = message;
	var act = toast.querySelector( '.ecodv2-save-toast-act' );
	if ( act ) {
		act.hidden = ! action;
		act.onclick = null;
		if ( action ) {
			act.textContent = action.label;
			act.onclick = function() {
				toast.classList.remove( 'is-visible' );
				action.fn();
			};
		}
	}
	toast.classList.add( 'is-visible' );
	clearTimeout( toast._ecodv2_hide );
	toast._ecodv2_hide = setTimeout( function() {
		toast.classList.remove( 'is-visible' );
	}, action ? 7000 : ( is_error ? 5200 : 2600 ) );
}

function ecodv2_drawer_set_button( state ) {
	var save = document.getElementById( 'ecodv2_drawer_save' );
	if ( ! save ) {
		return;
	}
	if ( null === ecodv2_drawer_save_label ) {
		ecodv2_drawer_save_label = save.innerHTML;
	}
	save.classList.remove( 'is-saving', 'is-saved' );
	if ( 'saving' === state ) {
		save.disabled = true;
		save.classList.add( 'is-saving' );
		save.innerHTML = '<span class="ecodv2-save-spin"></span>Saving\u2026';
	} else if ( 'saved' === state ) {
		save.disabled = true;
		save.classList.add( 'is-saved' );
		save.innerHTML = '\u2713 Saved';
	} else {
		save.innerHTML = ecodv2_drawer_save_label;
		save.disabled = ( 'idle-disabled' === state );
	}
}

function ecodv2_drawer_saving_begin() {
	ecodv2_drawer_saving = true;
	ecodv2_drawer_pending = 0;
	ecodv2_drawer_seen = 0;
	ecodv2_drawer_failed = false;
	ecodv2_drawer_failed_sections = [];
	document.body.classList.add( 'ecodv2-drawer-saving' );
	ecodv2_drawer_set_button( 'saving' );
	ecodv2_suppress_legacy_loaders();
}

function ecodv2_drawer_saving_finish() {
	if ( ! ecodv2_drawer_saving ) {
		return;
	}
	ecodv2_drawer_saving = false;
	clearTimeout( ecodv2_drawer_watchdog );
	ecodv2_drawer_watchdog = null;
	ecodv2_suppress_legacy_loaders();
	ecodv2_drawer_restore_views();
	ecodv2_drawer_after_save_repaint();

	if ( ecodv2_drawer_failed ) {
		/* Keep the drawer open with the dirty state intact so nothing the
		   merchant typed is lost; they can retry immediately. */
		document.body.classList.remove( 'ecodv2-drawer-saving' );
		ecodv2_drawer_set_button( 'idle' );
		ecodv2_save_toast( ecodv2_drawer_failed_sections.length ? ecodv2_t( 'drawer_failed', 'Some changes could not be saved: %s. Check them and save again.' ).replace( '%s', ecodv2_drawer_failed_sections.join( ', ' ) ) : ecodv2_t( 'failed', 'Some changes could not be saved. Please try again.' ), true );
		return;
	}

	ecodv2_drawer_set_button( 'saved' );
	setTimeout( function() {
		ecodv2_drawer_reset_dirty();
		ecodv2_close_edit_drawer( true );
		ecodv2_drawer_set_button( 'idle-disabled' );
		ecodv2_save_toast( ecodv2_t( 'drawer_saved', 'Order changes saved.' ) );
		/* Trailing legacy callbacks ( history refresh etc. ) may still call
		   the old loaders; lift the suppression once they have settled. */
		setTimeout( function() {
			document.body.classList.remove( 'ecodv2-drawer-saving' );
			ecodv2_suppress_legacy_loaders();
		}, 800 );
	}, 550 );
}

/* After the legacy handlers repaint the V1 spans ( they run synchronously
   inside the triggered clicks ), reconcile the V2-only presentation the
   legacy code doesn't know about: customer-card name / initials / phone,
   the V2-styled card line, and the payment-name fallback. */
function ecodv2_drawer_after_save_repaint() {
	var $ = jQuery;
	function val( id ) {
		var el = document.getElementById( id );
		return el ? String( el.value || '' ).trim() : '';
	}

	/* Customer card: name + avatar initials follow the billing name. */
	var first = val( 'billing_first_name' );
	var last = val( 'billing_last_name' );
	var name = $.trim( first + ' ' + last );
	if ( '' !== name && $( '.ecodv2-user-card-name' ).length ) {
		$( '.ecodv2-user-card-name' ).first().text( name );
	}
	var initials = ( first ? first.charAt( 0 ).toUpperCase() : '' ) + ( last ? last.charAt( 0 ).toUpperCase() : '' );
	var avatar = document.querySelector( '.ecodv2-user-card .ecodv2-avatar' );
	if ( avatar && '' !== initials ) {
		avatar.textContent = initials;
	}

	/* Customer card: phone follows the billing phone ( shown as entered;
	   the server-side pretty formatting applies on the next page load ). */
	var phone = val( 'billing_phone' );
	var phone_span = document.getElementById( 'ec_admin_order_details_user_phone' );
	if ( phone_span ) {
		if ( '' !== phone ) {
			var tel = document.createElement( 'a' );
			tel.href = 'tel:' + phone.replace( /[^0-9+]/g, '' );
			tel.textContent = phone;
			phone_span.innerHTML = '';
			phone_span.appendChild( tel );
		} else {
			phone_span.textContent = '';
		}
	}

	/* Payment info: legacy writes a plain "**** **** **** 1234" string;
	   restore the V2 dots + bold digits + "· MM / YYYY" treatment. */
	var digits = val( 'creditcard_digits' );
	var cc_span = document.getElementById( 'ec_admin_order_details_creditcard_digits' );
	if ( cc_span ) {
		cc_span.innerHTML = '';
		if ( '' !== digits ) {
			var dots = document.createElement( 'span' );
			dots.className = 'ecodv2-cc-dots';
			dots.innerHTML = '&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;';
			var b = document.createElement( 'b' );
			b.textContent = digits;
			cc_span.appendChild( dots );
			cc_span.appendChild( document.createTextNode( ' ' ) );
			cc_span.appendChild( b );
		}
	}
	var exp_m = val( 'cc_exp_month' );
	var exp_y = val( 'cc_exp_year' );
	var exp_span = document.getElementById( 'ec_admin_order_details_cc_exp' );
	if ( exp_span ) {
		exp_span.innerHTML = '';
		if ( '' !== exp_m ) {
			var exp = document.createElement( 'span' );
			exp.className = 'ecodv2-cc-exp';
			exp.textContent = '\u00b7 ' + exp_m + ' / ' + exp_y;
			exp_span.appendChild( exp );
		}
	}

	/* Payment info: keep the V2 fallback ( shipping name ) when the
	   cardholder field is empty — legacy blanks it instead. */
	if ( '' === val( 'card_holder_name' ) ) {
		var fallback = $.trim( val( 'shipping_first_name' ) + ' ' + val( 'shipping_last_name' ) );
		if ( '' !== fallback ) {
			$( '#ec_admin_order_details_card_holder_name' ).text( fallback );
		}
	}

	/* 6.0.2: the email addresses as links ( WP EasyCart PRO 6.0.1 wrote "mailto: " with a space ), and the addresses the
	   Email dialog starts with. */
	if ( document.getElementById( 'user_email' ) ) {
		ecodv2_paint_email( 'ec_admin_order_details_user_email', val( 'user_email' ) );
		$( '#ecodv2_send_email_link, #ecodv2_send_shipped_link' ).attr( 'data-email', val( 'user_email' ) );
	}
	if ( document.getElementById( 'email_other' ) ) {
		ecodv2_paint_email( 'ec_admin_order_details_email_other', val( 'email_other' ) );
		$( '#ecodv2_send_email_link, #ecodv2_send_shipped_link' ).attr( 'data-email-other', val( 'email_other' ) );
	}
	ecodv2_sync_same_address();
}

function ecodv2_drawer_available( section ) {
	/* Fulfillment is a free feature: its form is inlined in the drawer, not hosted. */
	if ( 'fulfillment' === section && document.querySelector( '#ecodv2_sec_fulfillment .ecodv2-form' ) ) {
		return true;
	}
	/* 6.0.2: so are the coupon and gift card codes. */
	if ( 'codes' === section && document.querySelector( '#ecodv2_sec_codes input' ) ) {
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
	/* V2.10: arm the legacy two-state toggles by flag so the drawer's Save
	   click always takes their SAVE branch, and repair any view block a
	   previous unarmed click may have hidden. Runs in the base function so
	   the PRO wrappers ( which call this first ) see the armed state and
	   skip their own arm pass. */
	ecodv2_arm_legacy_toggles();
	ecodv2_drawer_restore_views();
	setTimeout( function() { ecodv2_drawer_jump( section ); }, 240 );
	return false;
}

function ecodv2_close_edit_drawer( force ) {
	if ( ecodv2_drawer_saving && ! force ) {
		return false; /* Esc / backdrop must not interrupt an in-flight save */
	}
	if ( ! force && ! ecodv2_drawer_pristine ) {
		if ( ! window.confirm( ecodv2_t( 'discard', 'Discard the changes you have not saved?' ) ) ) {
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
	if ( ecodv2_drawer_saving ) {
		return false; /* double-click guard */
	}
	if ( 'undefined' !== typeof ecodv2_order_user_busy_on && ecodv2_order_user_busy_on ) {
		return false; /* account change in flight; the Save button is locked until it lands */
	}
	/* V2.10: the hidden controls are bound to legacy two-state toggles.
	   Re-arm every flag immediately before triggering so each click is
	   guaranteed to take the toggle's SAVE branch ( belt-and-suspenders on
	   top of the arming done at drawer open — covers a drawer left open
	   across a prior save, when the legacy handlers reset their flags ). */
	ecodv2_arm_legacy_toggles();
	ecodv2_drawer_saving_begin();
	var fired = 0;
	jQuery( '.ecodv2-drawer-sec.is-dirty .ecodv2-visually-hidden' ).each( function() {
		/* One failing section must not abort the sections after it. */
		try {
			jQuery( this ).trigger( 'click' );
		} catch ( e ) {
			if ( window.console && console.error ) {
				console.error( 'EasyCart drawer save: section handler failed', this.id, e );
			}
		}
		fired++;
	} );
	if ( ! fired ) {
		/* Nothing marked dirty — save everything present as a safety net. */
		jQuery( '.ecodv2-drawer .ecodv2-visually-hidden' ).each( function() {
			try {
				jQuery( this ).trigger( 'click' );
			} catch ( e ) {
				if ( window.console && console.error ) {
					console.error( 'EasyCart drawer save: section handler failed', this.id, e );
				}
			}
		} );
	}
	/* The legacy handlers hide their old loaders' targets and repaint the
	   V1 spans synchronously; keep the V2 view blocks visible with no gap. */
	ecodv2_suppress_legacy_loaders();
	ecodv2_drawer_restore_views();
	setTimeout( function() {
		ecodv2_drawer_restore_views();
		ecodv2_drawer_after_save_repaint();
	}, 0 );

	/* ajaxSend runs synchronously inside each triggered handler, so by this
	   point ecodv2_drawer_seen reflects every request the sections opened.
	   Completion is driven by ajaxComplete; the watchdog covers a request
	   that never returns. */
	if ( 0 === ecodv2_drawer_seen ) {
		setTimeout( ecodv2_drawer_saving_finish, 350 );
	} else {
		ecodv2_drawer_watchdog = setTimeout( function() {
			ecodv2_drawer_failed = true;
			ecodv2_drawer_saving_finish();
		}, 10000 );
	}
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

/* --------------------------------------------------------------------
   6.0.0 — order account ( guest <-> customer account ).
   #ec_order_user_id saves on change through ec_admin_ajax_update_order_user
   ( orders.js ), outside the drawer's Save. The handler returns the
   account-dependent fragments rendered server-side ( customer card chip +
   link, header chip hook, customer card hook ); repaint them so the page
   matches a fresh load. On failure the picker goes back to the saved account.
   -------------------------------------------------------------------- */
var ecodv2_order_user_saved = null;

function ecodv2_order_user_remember() {
	var select = document.getElementById( 'ec_order_user_id' );
	if ( ! select || select.selectedIndex < 0 ) {
		return;
	}
	var opt = select.options[ select.selectedIndex ];
	ecodv2_order_user_saved = { id: String( opt.value ), text: opt.text };
}

function ecodv2_order_user_set_select( id, text ) {
	var select = document.getElementById( 'ec_order_user_id' );
	if ( ! select ) {
		return;
	}
	var match = null;
	for ( var i = 0; i < select.options.length; i++ ) {
		if ( String( select.options[ i ].value ) === String( id ) ) {
			match = select.options[ i ];
		}
	}
	if ( ! match ) {
		match = document.createElement( 'option' );
		match.value = String( id );
		select.appendChild( match );
	}
	if ( 'string' === typeof text && '' !== text ) {
		match.text = text;
	}
	match.selected = true;
	/* Namespaced: refresh select2's display without re-firing the save handler. */
	jQuery( select ).trigger( 'change.select2' );
}

/* In-drawer status line under the account picker ( #ecodv2_account_status ).
   state: 'busy' | 'ok' | 'error' | '' ( clear ). Replaces the V1 full-page
   preloader, which rendered behind the drawer for this flow. */
var ecodv2_order_user_busy_on = false;

function ecodv2_order_user_status( state, message ) {
	var box = document.getElementById( 'ecodv2_account_status' );
	if ( ! box ) {
		return;
	}
	clearTimeout( box._ecodv2_hide );
	box.className = 'ecodv2-account-status' + ( state ? ' is-' + state : '' );
	box.innerHTML = '';
	if ( ! state ) {
		box.hidden = true;
		return;
	}
	var icon = document.createElement( 'span' );
	if ( 'busy' === state ) {
		icon.className = 'ecodv2-save-spin';
	} else {
		icon.className = 'dashicons ' + ( 'error' === state ? 'dashicons-warning' : 'dashicons-yes-alt' );
	}
	var text = document.createElement( 'span' );
	text.className = 'ecodv2-account-status-msg';
	text.textContent = message;
	box.appendChild( icon );
	box.appendChild( text );
	box.hidden = false;
	if ( 'ok' === state ) {
		box._ecodv2_hide = setTimeout( function() {
			ecodv2_order_user_status( '' );
		}, 6000 );
	}
}

/* Busy state for the account change: lock the picker and the drawer Save
   ( so a drawer save cannot race the account request ) and show the inline
   spinner. Unsaved billing / shipping edits are untouched either way. */
function ecodv2_order_user_busy( on ) {
	ecodv2_order_user_busy_on = !! on;
	var select = document.getElementById( 'ec_order_user_id' );
	if ( select ) {
		/* select2 4.0.x mirrors the disabled attribute onto its container. */
		select.disabled = !! on;
	}
	var drawer = document.getElementById( 'ecodv2_edit_drawer' );
	if ( drawer ) {
		drawer.classList.toggle( 'is-account-busy', !! on );
	}
	if ( on ) {
		var box = document.getElementById( 'ecodv2_account_status' );
		var busy_text = ( box && box.getAttribute( 'data-busy-text' ) ) ? box.getAttribute( 'data-busy-text' ) : 'Updating the customer account…';
		ecodv2_order_user_status( 'busy', busy_text );
	}
}

function ecodv2_apply_order_user( response ) {
	var data = ( response && response.data ) ? response.data : {};
	ecodv2_order_user_busy( false );
	if ( ! response || ! response.success ) {
		if ( ecodv2_order_user_saved ) {
			ecodv2_order_user_set_select( ecodv2_order_user_saved.id, ecodv2_order_user_saved.text );
		}
		var status_box = document.getElementById( 'ecodv2_account_status' );
		var error_text = data.message ? String( data.message ) : ( ( status_box && status_box.getAttribute( 'data-error-text' ) ) ? status_box.getAttribute( 'data-error-text' ) : 'The customer account could not be updated. Please try again.' );
		ecodv2_order_user_status( 'error', error_text );
		ecodv2_save_toast( error_text, true );
		return;
	}

	var user_id = parseInt( data.user_id, 10 ) || 0;

	/* Hidden field the legacy / PRO forms read. */
	jQuery( 'input[type="hidden"][name="user_id"]' ).val( user_id );

	/* Customer card: account chip + "View account" link, avatar tint. */
	var account = document.getElementById( 'ecodv2_user_account' );
	if ( account && 'string' === typeof data.account_html ) {
		account.innerHTML = data.account_html;
	}
	var avatar = document.querySelector( '.ecodv2-user-card .ecodv2-avatar' );
	if ( avatar ) {
		avatar.classList.toggle( 'ecodv2-avatar-guest', ! user_id );
	}

	/* Header chips ( PRO tags + insight chips such as "Guest checkout" / "First order" ). */
	var chips = document.getElementById( 'ecodv2_header_chips' );
	if ( chips && 'string' === typeof data.header_chips_html ) {
		chips.innerHTML = data.header_chips_html;
	}

	/* Customer card hook output ( PRO customer stats + "all orders" links ). */
	var extra = document.getElementById( 'ecodv2_customer_card_extra' );
	if ( extra && 'string' === typeof data.customer_card_html ) {
		extra.innerHTML = data.customer_card_html;
	}

	ecodv2_order_user_set_select( user_id, 'string' === typeof data.select_label ? data.select_label : '' );
	ecodv2_order_user_remember();
	var ok_text = data.message ? String( data.message ) : 'Customer account updated.';
	ecodv2_order_user_status( 'ok', ok_text );
	ecodv2_save_toast( ok_text );
}

jQuery( function( $ ) {
	if ( ! document.getElementById( 'ecodv2_edit_drawer' ) ) {
		return;
	}

	/* 6.0.2: the Codes section shows only when something prints the gift card / coupon fields. */
	if ( ! document.querySelector( '#ecodv2_sec_codes input' ) ) {
		$( '#ecodv2_sec_codes' ).hide();
		$( '#ecodv2_drawer_jump a[data-section="codes"]' ).hide();
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
		/* The account picker saves on its own ( ecodv2_apply_order_user ); it never makes the drawer dirty. */
		if ( 'ec_order_user_id' === this.id || $( this ).closest( '.ecodv2-drawer-account' ).length ) {
			return;
		}
		ecodv2_drawer_mark_dirty( this );
	} );
	ecodv2_order_user_remember();
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

	/* V2.11: drive the drawer saving indicator off the real requests.
	   ajaxSend fires synchronously inside each legacy handler's $.ajax
	   call, so by the time ecodv2_drawer_save() finishes triggering,
	   ecodv2_drawer_seen holds the exact number of requests to wait for.
	   Local success/error callbacks run before the global ajaxComplete,
	   so the legacy DOM repaints are done when the counter hits zero. */
	$( document ).ajaxSend( function( e, xhr, settings ) {
		if ( ! ecodv2_drawer_saving ) {
			return;
		}
		if ( ecodv2_is_drawer_save_request( settings ) ) {
			ecodv2_drawer_pending++;
			ecodv2_drawer_seen++;
		}
		/* Anything fired during the window ( including the history refresh
		   the sections trigger ) may fade a legacy overlay back in. */
		ecodv2_suppress_legacy_loaders();
		setTimeout( ecodv2_suppress_legacy_loaders, 30 );
	} );
	$( document ).ajaxError( function( e, xhr, settings ) {
		if ( ecodv2_drawer_saving && ecodv2_is_drawer_save_request( settings ) ) {
			ecodv2_drawer_failed = true;
			ecodv2_drawer_note_failure( settings );
		}
	} );
	$( document ).ajaxComplete( function( e, xhr, settings ) {
		if ( ecodv2_drawer_saving && ecodv2_is_drawer_save_request( settings ) ) {
			/* 6.0.2: a save that answers { success: false } failed too ( the older answers were empty, so they still count
			   as saved ). */
			var answer = ecodv2_json( xhr );
			if ( answer && false === answer.success ) {
				ecodv2_drawer_failed = true;
				ecodv2_drawer_note_failure( settings );
			}
			ecodv2_drawer_pending--;
			if ( ecodv2_drawer_pending <= 0 ) {
				setTimeout( ecodv2_drawer_saving_finish, 120 );
			}
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
	/* 6.0.2: the note's callout is hidden while there is no note; it shows while its popover is open. */
	jQuery( '#ec_admin_order_details_customer_notes_content' ).addClass( 'is-editing' );
	pop.classList.add( 'is-open' );
	ta.focus();
	return false;
}

function ecodv2_close_cnotes_popover() {
	var pop = document.getElementById( 'ecodv2_cnotes_popover' );
	if ( pop ) {
		pop.classList.remove( 'is-open' );
	}
	jQuery( '#ec_admin_order_details_customer_notes_content' ).removeClass( 'is-editing' );
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
		dataType: 'json',
		data: {
			action: 'ec_admin_ajax_edit_customer_notes',
			order_id: ec_admin_get_value( 'order_id', 'text' ),
			order_customer_notes: value,
			wp_easycart_nonce: ec_admin_get_value( 'wp_easycart_order_details_nonce', 'text' )
		},
		success: function( response ) {
			if ( btn ) {
				btn.disabled = false;
			}
			/* 6.0.2: the answer says whether it was saved ( an expired page used to report "saved" ). */
			if ( ! response || false === response.success ) {
				ecodv2_save_toast( ( response && response.data && response.data.message ) ? response.data.message : ecodv2_t( 'note_failed', 'The customer note could not be saved.' ), true );
				return;
			}
			ecodv2_cnotes_saved = value;
			/* Escape, then convert newlines for display. */
			var safe_html = jQuery( '<div></div>' ).text( value ).html().replace( /\n/g, '<br />' );
			jQuery( document.getElementById( 'ec_admin_order_details_customer_notes' ) ).html( safe_html );
			var has_notes = ( '' !== jQuery.trim( value ) );
			jQuery( document.getElementById( 'ec_admin_order_details_customer_notes_content' ) ).toggleClass( 'is-empty', ! has_notes );
			jQuery( document.getElementById( 'ecodv2_cnotes_add_btn' ) ).prop( 'hidden', has_notes );
			ecodv2_close_cnotes_popover();
			ecodv2_save_toast( ( response.data && response.data.message ) ? response.data.message : ecodv2_t( 'note_saved', 'Customer note saved.' ) );
			ecodv2_history_refresh();
		},
		error: function() {
			if ( btn ) {
				btn.disabled = false;
			}
			ecodv2_save_toast( ecodv2_t( 'note_failed', 'The customer note could not be saved.' ), true );
		}
	} );
	return false;
}

/* 6.0.2: activity log filters and day headings ( owner bug round 4, item 14 ). The chosen filter lives on the wrap as
   data-tl-filter ( CSS hides the rest ); each day's heading shows on the first entry of that day still in view. */
function ecodv2_tl_apply( wrap ) {
	if ( ! wrap ) {
		return;
	}
	var filter = wrap.getAttribute( 'data-tl-filter' ) || 'all';
	if ( 'all' !== filter && ! wrap.querySelector( '.ecodv2-tl-chip[data-tl-filter="' + filter + '"]' ) ) {
		filter = 'all';
		wrap.removeAttribute( 'data-tl-filter' );
	}
	var chips = wrap.querySelectorAll( '.ecodv2-tl-chip' ), items = wrap.querySelectorAll( '.ecodv2-tl-item' ), last = '', i;
	for ( i = 0; i < chips.length; i++ ) {
		var on = chips[ i ].getAttribute( 'data-tl-filter' ) === filter;
		chips[ i ].classList.toggle( 'is-active', on );
		chips[ i ].setAttribute( 'aria-pressed', on ? 'true' : 'false' );
	}
	for ( i = 0; i < items.length; i++ ) {
		var cat = items[ i ].getAttribute( 'data-cat' ), day = items[ i ].getAttribute( 'data-day' ) || '';
		var shown = 'all' === filter || 'all' === cat || cat === filter;
		items[ i ].classList.toggle( 'is-day-first', shown && '' !== day && day !== last );
		if ( shown && '' !== day ) {
			last = day;
		}
	}
}

/* ---- Activity history collapse + full-history drawer ( V4.2 ) ---- */
function ecodv2_history_apply_collapse() {
	var wrap = document.getElementById( 'ecodv2_history_wrap' );
	if ( ! wrap ) {
		return;
	}
	ecodv2_tl_apply( wrap );
	var items = wrap.querySelectorAll( '.wpeasycart-timeline-item' );
	var showall = document.getElementById( 'ecodv2_history_showall' );
	var total_el = document.getElementById( 'ecodv2_history_total' );
	var filtered = 'all' !== ( wrap.getAttribute( 'data-tl-filter' ) || 'all' );
	if ( items.length > 21 && ! filtered ) {
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
		ecodv2_tl_apply( body ); /* every entry shows in the drawer, so the day headings are worked out again */
		var count_el = document.getElementById( 'ecodv2_history_drawer_count' );
		if ( count_el ) {
			var count = body.querySelectorAll( '.wpeasycart-timeline-item' ).length;
			count_el.textContent = count + ' ' + ( 1 === count ? ecodv2_t( 'history_event', 'event' ) : ecodv2_t( 'history_events', 'events' ) );
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

	/* 6.0.2: activity log filter chips. */
	$( document ).on( 'click', '.ecodv2-tl-chip', function() {
		var wrap = this.closest( '.ecodv2-history-wrap' ), filter = this.getAttribute( 'data-tl-filter' ) || 'all';
		if ( ! wrap ) {
			return;
		}
		if ( 'all' === filter ) {
			wrap.removeAttribute( 'data-tl-filter' );
		} else {
			wrap.setAttribute( 'data-tl-filter', filter );
		}
		ecodv2_history_apply_collapse();
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
			/* 6.0.2: no order info save here ( the page has no weight field, and posting it blanked the weight ). */
		}
		return out;
	};
} );

/* ====================================================================
   V2.6 — status swatch pill + inline date cancel
   ==================================================================== */
function ecodv2_status_toggle() {
	var menu = document.getElementById( 'ecodv2_status_menu' );
	ecodv2_menu_toggle( document.getElementById( 'ecodv2_status_pill' ), 'ecodv2_status_menu' );
	if ( menu && menu.classList.contains( 'is-open' ) ) {
		var current = menu.querySelector( '.ecodv2-status-item.is-current' ) || menu.querySelector( '.ecodv2-status-item' );
		if ( current ) {
			current.focus();
		}
	}
	return false;
}

/* 6.0.2: a status change saves through its own request, shows it is saving, and goes back when it fails ( the page said
   "changed" whatever the answer ). Refunded, Partial refund and Cancelled ask first: they change the status only, and
   with WP EasyCart PRO the dialog offers the real refund instead. */
function ecodv2_set_status( value, item ) {
	var sel = document.getElementById( 'orderstatus_id' );
	ecodv2_menu_close();
	if ( ! sel ) {
		return false;
	}
	if ( 'add-new' === value ) {
		window.location.href = 'admin.php?page=wp-easycart-settings&subpage=checkout';
		return false;
	}
	value = String( value );
	if ( value === String( sel.value ) || document.body.classList.contains( 'ecodv2-status-busy' ) ) {
		return false;
	}
	if ( '16' === value || '17' === value || '19' === value ) {
		var pro = window.ecodv2_pro || {};
		var can_refund = 'function' === typeof pro.open_refund && !! document.getElementById( 'ec_admin_refund_button' );
		ecodv2_confirm( {
			title: ecodv2_t( 'status_title_' + value, '' ),
			body: ecodv2_t( 'status_body_' + value, '' ),
			yes: ecodv2_t( 'status_confirm', 'Change status' ),
			no: ecodv2_t( 'cancel', 'Cancel' ),
			alt: can_refund ? ecodv2_t( 'status_refund', 'Refund the payment instead' ) : '',
			on_alt: function() { pro.open_refund( value ); },
			on_yes: function() { ecodv2_status_apply( value ); }
		} );
		return false;
	}
	ecodv2_status_apply( value );
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
   V4.0 — copy-to-clipboard for the customer card ( email, phone, any
   data-copy-target button ). Reads the target element's live text so PRO
   edits stay in sync. 6.0.2: its own name ( a second ecodv2_copy_text( text,
   btn ) below replaced it, so Copy email and Copy phone copied nothing ).
   ==================================================================== */
function ecodv2_copy_from( btn ) {
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
	return ecodv2_copy_text( text, btn );
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

/* Shipment details: show the empty state only while method, carrier, tracking and expedite are all blank
   ( orders.js rewrites those spans when the fulfillment drawer saves ). */
function ecodv2_sync_shipment() {
	var box = document.getElementById( 'ec_admin_view_shipping_method' );
	if ( ! box || ! box.classList ) {
		return;
	}
	var ids = [ 'ec_admin_order_details_shipping_method', 'ec_admin_order_details_shipping_carrier', 'ec_admin_order_details_tracking_number', 'ec_admin_order_details_shipping_type' ];
	var has = false;
	for ( var i = 0; i < ids.length; i++ ) {
		var el = document.getElementById( ids[ i ] );
		if ( el && '' !== jQuery.trim( el.textContent ) ) {
			has = true;
		}
	}
	box.classList.toggle( 'is-empty', ! has );
}

/* Reflect a status in the shipping badge and banner. 6.0.2: the status change's answer carries the order's shipping state
   ( ecodv2_apply_fulfillment() ); this reading from the status alone stays for anything that still calls it. */
function ecodv2_sync_fulfillment( status_id ) {
	var banner = document.getElementById( 'ecodv2_fulfill_banner' );
	if ( ! banner ) {
		return;
	}
	var current = ( banner.className.match( /ecodv2-fulfill-banner-(\w+)/ ) || [] )[ 1 ] || 'none';
	if ( 'pickup' === current || 'digital' === current || 'partial' === current ) {
		return;
	}
	var fulfill_ids = ( banner.getAttribute( 'data-fulfill-status-ids' ) || '' ).split( ',' );
	var tracking = jQuery.trim( jQuery( '#ec_admin_order_details_tracking_number' ).text() );
	ecodv2_apply_fulfillment( ( -1 !== jQuery.inArray( String( status_id ), fulfill_ids ) || '' !== tracking ) ? 'fulfilled' : 'unfulfilled' );
}

jQuery( function( $ ) {
	if ( ! document.getElementById( 'ecodv2_wrap' ) ) {
		return;
	}

	/* Item 5: opening the drawer must not hide the panel's shipping info —
	   the legacy toggle's "show edit" pass hides the view; restore it. */
	var ecodv2_orig_open2 = window.ecodv2_open_edit_drawer;
	window.ecodv2_open_edit_drawer = function( section, fallback ) {
		var out = ecodv2_orig_open2( section, fallback );
		setTimeout( function() {
			$( '#ec_admin_view_shipping_method' ).show();
			ecodv2_sync_shipment();
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

	/* 6.0.2: the balance follows the grand total when the totals are saved ( Edit totals, or a line changed ). */
	$( document ).ajaxComplete( function( e, xhr, settings ) {
		var d = ( settings && 'string' === typeof settings.data ) ? settings.data : '';
		if ( -1 !== d.indexOf( 'action=ec_admin_ajax_edit_order_totals' ) ) {
			ecodv2_sync_balance();
			setTimeout( ecodv2_line_toast_flush, 60 );
		}
		/* 6.0.2: a status change's answer carries what was paid ( ecodv2_status_reply() ). */
	} );

	/* Keep the tracking copy button and the shipment empty state in sync after a fulfillment save. */
	$( document ).ajaxComplete( function() {
		var has = '' !== $.trim( $( '#ec_admin_order_details_tracking_number' ).text() );
		$( '.ecodv2-tracking-copy' ).toggle( has );
		ecodv2_sync_shipment();
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
			ecodv2_save_toast( ( response && response.data && response.data.message ) ? response.data.message : 'The order date could not be saved.', true );
			return;
		}
		ecodv2_save_toast( 'Order date updated.' );
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
	if ( text && 1 === text.nodeType ) {
		return ecodv2_copy_from( text ); /* older markup passed the button */
	}
	text = String( text || '' ).trim();
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

/* 6.0.2: Fulfill is free and is the one way to ship an order; these older names open it. */
function ecodv2_open_label_popup() {
	return ecodv2_fulfill_open();
}

function ecodv2_label_popup_open() {
	return ecodv2_fulfill_open();
}

/* 6.0.2: the Create shipping label window's steps ( only when the order has packages ): 1 = make the label, 2 = a
   tracking number for each package. */
function ecodv2_label_step( n ) {
	var $pop = jQuery( '#ecodv2_label_popup' );
	if ( ! $pop.find( '[data-label-step]' ).length ) {
		return false;
	}
	$pop.find( '[data-label-step]' ).each( function() {
		this.hidden = String( n ) !== this.getAttribute( 'data-label-step' );
	} );
	$pop.find( '[data-label-stepnav]' ).each( function() {
		var on = String( n ) === this.getAttribute( 'data-label-stepnav' );
		jQuery( this ).toggleClass( 'is-on', on ).attr( 'aria-current', on ? 'step' : null );
	} );
	if ( 2 === Number( n ) ) {
		var $empty = $pop.find( '.ecodv2-label-pkg:not(.is-done) .ecodv2-label-pkg-tracking, #ecodv2_label_tracking' ).filter( function() {
			return '' === jQuery.trim( this.value );
		} ).first();
		( $empty.length ? $empty : $pop.find( '#ecodv2_label_save_all' ) ).trigger( 'focus' );
	} else {
		$pop.find( '.ecodv2-svc, .ecodv2-label-carrier' ).first().trigger( 'focus' );
	}
	return false;
}

/* 6.0.2: the Fulfill window's package rows and the Ship panel's package were printed with the page. Once the Packages card
   is drawn with other packages or items ( Edit packages, Pack again, a line changed ), they no longer match: no tracking is
   sent from them until they are drawn again ( ecv2_order_packages_track_all refuses stale rows too ). Bug round 6: they are
   drawn again at once ( ecodv2_screen_refresh() ), with the steps, the next step panel and the lines' package chips. */
var ecodv2_packages_seen = null;

function ecodv2_packages_signature() {
	return jQuery( '#ecpk_order .ecpk-pkg' ).map( function() {
		return ( this.getAttribute( 'data-shipment-id' ) || '0' ) + ':' + jQuery.trim( jQuery( this ).find( '.ecpk-pkg-items' ).text() ).replace( /\s+/g, ' ' );
	} ).get().join( '|' );
}

function ecodv2_packages_stale() {
	return null !== ecodv2_packages_seen && ecodv2_packages_signature() !== ecodv2_packages_seen;
}

jQuery( function( $ ) {
	ecodv2_packages_seen = ecodv2_packages_signature();
	$( document ).on( 'ecpk:updated', function() {
		$( '#ecodv2_label_popup' ).toggleClass( 'is-stale', ecodv2_packages_stale() );
		/* The Packages card was drawn again ( by WP EasyCart or a label extension ): so is everything that follows it. Not
		   while the page is about to reload after a save. */
		if ( ! ecodv2_leaving ) {
			ecodv2_screen_refresh( null, true );
		}
	} );
} );

/* ---------- 6.0.2 bug round 6: the page drawn again without a reload ( ecv2_order_screen_refresh ) ----------
   The steps, the next step panel, the status, the Fulfill window's tracking rows and each line's package chip, as the
   order reads now. Asked for after the Packages card changes and after an email is sent ( force: the order changed, so
   a request already on its way is replaced ); anything that needs the rows current waits for the one on its way. Every
   caller's then( ok ) runs once the last one answers. */
var ecodv2_refresh = { xhr: null, wait: [] };

function ecodv2_refresh_pending() {
	return !! ecodv2_refresh.xhr;
}

function ecodv2_screen_refresh( then, force ) {
	var $ = jQuery, order_id = $( '#order_id' ).val(), nonce = $( '#wp_easycart_order_details_nonce' ).val();
	if ( 'function' === typeof then ) {
		ecodv2_refresh.wait.push( then );
	}
	if ( ecodv2_refresh.xhr && true !== force ) {
		return false; /* the answer on its way is current: wait for it */
	}
	var finish = function( ok ) {
		var wait = ecodv2_refresh.wait;
		ecodv2_refresh.wait = [];
		wait.forEach( function( fn ) {
			try { fn( ok ); } catch ( e ) {}
		} );
	};
	if ( ! order_id || ! nonce || 'undefined' === typeof wpeasycart_admin_ajax_object ) {
		finish( false );
		return false;
	}
	if ( ecodv2_refresh.xhr && ecodv2_refresh.xhr.abort ) {
		var old = ecodv2_refresh.xhr;
		ecodv2_refresh.xhr = null;
		old.abort();
	}
	var xhr = $.ajax( {
		url: wpeasycart_admin_ajax_object.ajax_url,
		type: 'post',
		dataType: 'json',
		data: { action: 'ecv2_order_screen_refresh', order_id: order_id, wp_easycart_nonce: nonce }
	} );
	ecodv2_refresh.xhr = xhr;
	xhr.done( function( r ) {
		if ( xhr !== ecodv2_refresh.xhr ) {
			return;
		}
		ecodv2_refresh.xhr = null;
		var ok = !! ( r && r.success && r.data );
		if ( ok && ! ecodv2_leaving ) {
			ecodv2_refresh_apply( r.data );
		}
		finish( ok );
	} ).fail( function() {
		if ( xhr !== ecodv2_refresh.xhr ) {
			return; /* replaced by a newer ask */
		}
		ecodv2_refresh.xhr = null;
		finish( false );
	} );
	return false;
}

/* Put a refresh answer ( wp_easycart_admin_order_screen::refresh_reply() ) in place. What was typed in the Ship panel and in
   the Fulfill window's rows stays where the same field is drawn again. */
function ecodv2_refresh_apply( d ) {
	var $ = jQuery;
	var kept = {
		ship: $( '#ecodv2_ship_tracking' ).val(),
		carrier: $( '#ecodv2_ship_carrier' ).val(),
		email: $( '#ecodv2_ship_email' ).length ? $( '#ecodv2_ship_email' ).is( ':checked' ) : null,
		mark: $( '#ecodv2_label_mark_shipped' ).length ? $( '#ecodv2_label_mark_shipped' ).is( ':checked' ) : null,
		notify: $( '#ecodv2_label_send_email' ).length ? $( '#ecodv2_label_send_email' ).is( ':checked' ) : null,
		single: $( '#ecodv2_label_tracking' ).val(),
		rows: {}
	};
	$( '#ecodv2_label_rows .ecodv2-label-pkg:not(.is-done)' ).each( function() {
		var sid = parseInt( this.getAttribute( 'data-shipment-id' ), 10 ) || 0, typed = $.trim( $( this ).find( '.ecodv2-label-pkg-tracking' ).val() || '' );
		if ( sid && '' !== typed ) {
			kept.rows[ sid ] = { tracking: typed, carrier: $( this ).find( '.ecodv2-label-pkg-carrier' ).val() };
		}
	} );
	ecodv2_status_reply( d );
	ecodv2_status_paint( d.status_id, d.status_label, d.status_color );
	if ( 'string' === typeof d.label_html ) {
		var host = document.getElementById( 'ecodv2_label_rows' );
		if ( host ) {
			host.innerHTML = d.label_html;
			$.each( kept.rows, function( sid, row ) {
				var $row = $( '#ecodv2_label_rows .ecodv2-label-pkg[data-shipment-id="' + ( parseInt( sid, 10 ) || 0 ) + '"]:not(.is-done)' );
				$row.find( '.ecodv2-label-pkg-tracking' ).val( row.tracking );
				if ( row.carrier && $row.find( '.ecodv2-label-pkg-carrier option' ).filter( function() { return this.value === row.carrier; } ).length ) {
					$row.find( '.ecodv2-label-pkg-carrier' ).val( row.carrier );
				}
			} );
			if ( kept.single && document.getElementById( 'ecodv2_label_tracking' ) ) {
				$( '#ecodv2_label_tracking' ).val( kept.single );
			}
			if ( null !== kept.mark ) {
				$( '#ecodv2_label_mark_shipped' ).prop( 'checked', kept.mark );
			}
			if ( null !== kept.notify ) {
				$( '#ecodv2_label_send_email' ).prop( 'checked', kept.notify );
			}
		}
		$( '#ecodv2_label_save_all' ).prop( 'hidden', !! d.label_nothing );
		if ( d.label_nav ) {
			$( '#ecodv2_label_popup [data-label-stepnav="2"] .ecodv2-label-stepname' ).text( d.label_nav );
		}
	}
	if ( kept.ship && document.getElementById( 'ecodv2_ship_tracking' ) ) {
		$( '#ecodv2_ship_tracking' ).val( kept.ship );
		if ( kept.carrier && $( '#ecodv2_ship_carrier option' ).filter( function() { return this.value === kept.carrier; } ).length ) {
			$( '#ecodv2_ship_carrier' ).val( kept.carrier );
		}
	}
	if ( null !== kept.email && document.getElementById( 'ecodv2_ship_email' ) ) {
		$( '#ecodv2_ship_email' ).prop( 'checked', kept.email );
	}
	if ( d.line_packages && 'object' === typeof d.line_packages ) {
		$.each( d.line_packages, function( id, words ) {
			var holder = document.querySelector( '[data-ecodv2-line-pkgs="' + ( parseInt( id, 10 ) || 0 ) + '"]' );
			if ( ! holder ) {
				return;
			}
			holder.innerHTML = '';
			if ( words ) {
				var chip = document.createElement( 'span' );
				chip.className = 'ecodv2-chip ecodv2-chip-muted';
				chip.textContent = String( words );
				holder.appendChild( document.createTextNode( ' ' ) );
				holder.appendChild( chip );
			}
		} );
	}
	ecodv2_packages_seen = ecodv2_packages_signature();
	$( '#ecodv2_label_popup' ).removeClass( 'is-stale' );
	$( document ).trigger( 'ecodv2_screen_refreshed', [ d ] );
}

/* The status pill and the hidden select for a status the server reports ( a status the menu does not list yet, such as Order
   Delivered made on first use, takes the answer's name and colour ). */
function ecodv2_status_paint( id, label, color ) {
	var $ = jQuery, sel = document.getElementById( 'orderstatus_id' );
	id = parseInt( id, 10 ) || 0;
	if ( ! sel || ! id || String( id ) === String( sel.value ) ) {
		return;
	}
	var $item = $( '#ecodv2_status_menu .ecodv2-status-item[data-status-id="' + id + '"]' );
	if ( ! $( sel ).find( 'option[value="' + id + '"]' ).length ) {
		var opt = document.createElement( 'option' );
		opt.value = String( id );
		opt.textContent = String( label || id );
		sel.insertBefore( opt, sel.querySelector( 'option[value="add-new"]' ) );
	}
	sel.value = String( id );
	var dot = document.getElementById( 'ecodv2_status_pill_dot' ), text = document.getElementById( 'ecodv2_status_pill_label' );
	var hex = /^#[0-9a-f]{3,8}$/i.test( String( color || '' ) ) ? String( color ) : '#e5e7eb';
	if ( dot ) {
		dot.style.background = $item.length ? ( $item.attr( 'data-color' ) || '#e5e7eb' ) : hex;
	}
	if ( text ) {
		text.textContent = $item.length ? $item.find( '.ecodv2-status-item-label' ).text() : String( label || '' );
	}
	$( '#ecodv2_status_menu .ecodv2-status-item' ).removeClass( 'is-current' ).attr( 'aria-selected', 'false' );
	$item.addClass( 'is-current' ).attr( 'aria-selected', 'true' );
}

/* A package's Add tracking ( packages-v2.js ): the Fulfill window at its tracking step, on that package's row ( by id, or by its
   place in the plan while the packages are not saved ). When the packages just changed, the rows are drawn again first.
   Answers false when the page has no Fulfill window ( the Packages card then opens its own dialog ). */
function ecodv2_fulfill_package( shipment_id, index ) {
	var $ = jQuery;
	if ( ! document.getElementById( 'ecodv2_label_popup' ) ) {
		return false;
	}
	shipment_id = parseInt( shipment_id, 10 ) || 0;
	index = parseInt( index, 10 ) || 0;
	var open = function() {
		var $pop = $( '#ecodv2_label_popup' );
		var $row = shipment_id ? $pop.find( '.ecodv2-label-pkg[data-shipment-id="' + shipment_id + '"]' ) : $pop.find( '.ecodv2-label-pkg[data-shipment-id="0"][data-index="' + index + '"]' );
		ecodv2_fulfill_open();
		ecodv2_label_step( 2 );
		if ( $row.length && ! $row.hasClass( 'is-done' ) ) {
			$row.find( '.ecodv2-label-pkg-tracking' ).trigger( 'focus' );
		}
	};
	if ( ecodv2_packages_stale() || ecodv2_refresh_pending() ) {
		ecodv2_screen_refresh( open );
	} else {
		open();
	}
	return true;
}

/* A button that saves says so until the answer comes back or the page reloads: disabled, a spinner and its data-busy-text
   ( else "Saving…" ). on false puts its own label back. */
function ecodv2_btn_busy( btn, on, text ) {
	if ( ! btn || ! btn.nodeType ) {
		return;
	}
	if ( on ) {
		if ( undefined === btn.ecodv2_label ) {
			btn.ecodv2_label = btn.innerHTML;
		}
		btn.disabled = true;
		btn.setAttribute( 'aria-busy', 'true' );
		btn.classList.add( 'ecv2-btn-busy', 'is-saving' );
		btn.innerHTML = '';
		var spin = document.createElement( 'span' );
		spin.className = 'ecodv2-save-spin';
		spin.setAttribute( 'aria-hidden', 'true' );
		btn.appendChild( spin );
		btn.appendChild( document.createTextNode( text || btn.getAttribute( 'data-busy-text' ) || ecodv2_t( 'saving', 'Saving…' ) ) );
		return;
	}
	if ( undefined !== btn.ecodv2_label ) {
		btn.innerHTML = btn.ecodv2_label;
		btn.ecodv2_label = undefined;
	}
	btn.disabled = false;
	btn.removeAttribute( 'aria-busy' );
	btn.classList.remove( 'ecv2-btn-busy', 'is-saving' );
}

/* A control whose ring shows the saving ( data-busy="ring": the Delivered step's circle, the next step panel's Mark delivered
   card ): it keeps its look, the ring turns, and a line marked data-busy-line says "Saving…" meanwhile ( bug round 7 ). Other
   buttons go through ecodv2_btn_busy(). */
function ecodv2_ring_busy( btn, on ) {
	if ( ! btn || ! btn.nodeType ) {
		return;
	}
	if ( 'ring' !== btn.getAttribute( 'data-busy' ) ) {
		ecodv2_btn_busy( btn, on );
		return;
	}
	var line = btn.querySelector( '[data-busy-line]' );
	btn.disabled = !! on;
	btn.classList.toggle( 'is-busy', !! on );
	if ( on ) {
		btn.setAttribute( 'aria-busy', 'true' );
		if ( line && undefined === line.ecodv2_text ) {
			line.ecodv2_text = line.textContent;
			line.textContent = ecodv2_t( 'saving', 'Saving…' );
		}
	} else {
		btn.removeAttribute( 'aria-busy' );
		if ( line && undefined !== line.ecodv2_text ) {
			line.textContent = line.ecodv2_text;
			line.ecodv2_text = undefined;
		}
	}
}

/* Delivered ( optional ): the store marks a shipped order delivered from the Delivered step's circle or the next step panel
   ( ecv2_order_screen_delivered ). The page reloads afterwards, as it does after shipping. */
function ecodv2_mark_delivered( btn ) {
	var $ = jQuery, buttons = $( '[data-step-act="delivered"]' ).get();
	if ( ( btn && btn.disabled ) || buttons.some( function( b ) { return b.disabled; } ) ) {
		return false;
	}
	buttons.forEach( function( b ) {
		ecodv2_ring_busy( b, true );
	} );
	var release = function( answer ) {
		$( '[data-step-act="delivered"]' ).each( function() {
			ecodv2_ring_busy( this, false );
		} );
		ecodv2_save_toast( ( answer && answer.data && answer.data.message ) ? answer.data.message : ecodv2_t( 'delivered_failed', 'The order could not be marked delivered. Reload the page and try again.' ), true );
	};
	$.ajax( {
		url: wpeasycart_admin_ajax_object.ajax_url,
		type: 'post',
		dataType: 'json',
		data: { action: 'ecv2_order_screen_delivered', order_id: $( '#order_id' ).val(), wp_easycart_nonce: $( '#wp_easycart_order_details_nonce' ).val() }
	} ).done( function( r ) {
		if ( ! r || ! r.success || ! r.data ) {
			release( r );
			return;
		}
		ecodv2_refresh_apply( r.data );
		ecodv2_save_toast( r.data.message || ecodv2_t( 'saved', 'Saved' ) );
		/* The packages, the lines and the activity follow on the reloaded page. */
		ecodv2_leaving = true;
		setTimeout( function() { window.location.reload(); }, 900 );
	} ).fail( function( xhr ) {
		release( ecodv2_json( xhr ) );
	} );
	return false;
}

/* 6.0.2: every package's tracking number in one request ( ecv2_order_packages_track_all ): each package keeps its own
   number, the customer gets one shipped email listing them all, and the order is marked shipped once no package is
   still waiting. The page reloads afterwards, as the Packages card does, so the status and banners follow. */
function ecodv2_label_save_all() {
	var $box = jQuery( '#ecodv2_label_pkgs' ), $status = jQuery( '#ecodv2_label_status' ), $btn = jQuery( '#ecodv2_label_save_all' ), rows = [];
	var mark = ! document.getElementById( 'ecodv2_label_mark_shipped' ) || jQuery( '#ecodv2_label_mark_shipped' ).is( ':checked' );
	if ( ! $box.length ) {
		return ecodv2_fulfill_save();
	}
	if ( $btn.prop( 'disabled' ) ) {
		return false;
	}
	$box.find( '.ecodv2-label-pkg:not(.is-done)' ).each( function() {
		var $p = jQuery( this ), tracking = jQuery.trim( $p.find( '.ecodv2-label-pkg-tracking' ).val() || '' );
		if ( '' !== tracking ) {
			rows.push( {
				index: parseInt( $p.attr( 'data-index' ), 10 ) || 0,
				shipment_id: parseInt( $p.attr( 'data-shipment-id' ), 10 ) || 0,
				carrier: $p.find( '.ecodv2-label-pkg-carrier' ).val() || '',
				tracking_number: tracking
			} );
		}
	} );
	if ( ! rows.length ) {
		/* Shipped without tracking: Mark as shipped alone sets the status. */
		if ( mark ) {
			return ecodv2_fulfill_post( '', '' );
		}
		$status.removeClass( 'is-saved' ).addClass( 'is-error' ).text( $box.attr( 'data-need' ) );
		$box.find( '.ecodv2-label-pkg-tracking' ).first().trigger( 'focus' );
		return false;
	}
	/* 6.0.2 bug round 6: the packages changed since these rows were drawn: draw them again ( what was typed stays on the
	   same packages ) and ask for a second look, rather than "reload the order". */
	var redraw = function() {
		ecodv2_btn_busy( $btn[0], true, ecodv2_t( 'updating', 'Updating…' ) );
		ecodv2_screen_refresh( function( ok ) {
			ecodv2_btn_busy( $btn[0], false );
			$status.removeClass( 'is-saved' ).addClass( 'is-error' ).text( ok ? ecodv2_t( 'packages_changed', 'The packages changed, so the list was brought up to date. Check the tracking numbers and save again.' ) : $box.attr( 'data-fail' ) );
		} );
	};
	if ( ecodv2_packages_stale() || ecodv2_refresh_pending() ) {
		redraw();
		return false;
	}
	ecodv2_btn_busy( $btn[0], true );
	$status.removeClass( 'is-error is-saved' ).text( '' );
	jQuery.post( wpeasycart_admin_ajax_object.ajax_url, {
		action: 'ecv2_order_packages_track_all',
		nonce: $box.attr( 'data-nonce' ),
		order_id: $box.attr( 'data-order-id' ),
		rows: JSON.stringify( rows ),
		notify: jQuery( '#ecodv2_label_send_email' ).is( ':checked' ) ? 1 : 0,
		mark_shipped: mark ? 1 : 0
	} ).done( function( r ) {
		if ( r && r.success ) {
			/* Saving… stays on the button until the page reloads. */
			ecodv2_leaving = true;
			$status.removeClass( 'is-error' ).addClass( 'is-saved' ).text( r.data && r.data.message ? r.data.message : '' );
			if ( r.data && r.data.html && 'function' === typeof window.ecpk_replace ) {
				window.ecpk_replace( r.data.html );
			}
			setTimeout( function() {
				window.location.reload();
			}, ( r.data && r.data.unpaid ) ? 3200 : 1600 );
			return;
		}
		ecodv2_btn_busy( $btn[0], false );
		if ( r && r.data && 'stale' === r.data.code ) {
			redraw();
			return;
		}
		$status.removeClass( 'is-saved' ).addClass( 'is-error' ).text( ( r && r.data && r.data.message ) ? r.data.message : $box.attr( 'data-fail' ) );
	} ).fail( function( xhr ) {
		var msg = ( xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ) ? xhr.responseJSON.data.message : $box.attr( 'data-fail' );
		ecodv2_btn_busy( $btn[0], false );
		$status.removeClass( 'is-saved' ).addClass( 'is-error' ).text( msg );
	} );
	return false;
}

function ecodv2_label_popup_close() {
	document.body.classList.remove( 'ecodv2-label-open' );
	return false;
}

/* 6.0.2: the single tracking field saves through the Fulfill flow ( ecodv2_fulfill_save() ). */
function ecodv2_label_save_tracking() {
	return ecodv2_fulfill_save();
}

/* 6.0.2: Net received ( paid less refunded ) follows the Refunded amount, which WP EasyCart PRO's refund drawer and totals
   editor rewrite in place ( owner bug round 4, item 15 ). */
function ecodv2_sync_net_received() {
	var row = document.getElementById( 'ecodv2_totals_net_row' ), span = document.getElementById( 'ecodv2_totals_net' ), ref = document.getElementById( 'ec_admin_order_details_totals_refund_total' );
	if ( ! row || ! span || ! ref ) {
		return;
	}
	var refunded = parseFloat( String( ref.textContent ).replace( /[^0-9.\-]/g, '' ) ) || 0, base = parseFloat( row.getAttribute( 'data-base' ) ) || 0, none = refunded < 0.005;
	var chip = document.getElementById( 'ecodv2_refund_chip' ), chip_amount = document.getElementById( 'ecodv2_refund_chip_amount' ), ref_row = document.getElementById( 'ec_admin_order_details_totals_refund_total_row' );
	span.textContent = Math.max( 0, base - refunded ).toFixed( 2 );
	/* The rows' own class decides ( PRO's .show() would make them block rows ). */
	[ row, ref_row, chip ].forEach( function( el ) {
		if ( el ) {
			el.style.display = '';
			el.classList.toggle( 'ec_admin_initial_hide', none );
		}
	} );
	if ( chip_amount ) {
		chip_amount.textContent = refunded.toFixed( 2 );
	}
}
jQuery( function() {
	var ref = document.getElementById( 'ec_admin_order_details_totals_refund_total' );
	if ( ref && 'function' === typeof window.MutationObserver ) {
		new MutationObserver( ecodv2_sync_net_received ).observe( ref, { childList: true, characterData: true, subtree: true } );
	}
} );

jQuery( function( $ ) {
	if ( ! document.getElementById( 'ecodv2_label_popup' ) ) {
		return;
	}
	$( document ).on( 'keydown', function( e ) {
		if ( 'Escape' === e.key && document.body.classList.contains( 'ecodv2-label-open' ) ) {
			ecodv2_label_popup_close();
		}
	} );
	/* 6.0.2: the steps. */
	$( document ).on( 'click', '#ecodv2_label_popup [data-label-go]', function( e ) {
		e.preventDefault();
		ecodv2_label_step( parseInt( $( this ).attr( 'data-label-go' ), 10 ) );
	} );
	/* Opening a carrier's site ( a new tab ) means the labels are made there: go on to the tracking step with that carrier. */
	$( document ).on( 'click', '#ecodv2_label_popup .ecodv2-label-carrier', function() {
		var carrier = $( this ).attr( 'data-carrier' ) || '', $pop = $( '#ecodv2_label_popup' );
		if ( ! $pop.find( '[data-label-step]' ).length ) {
			return;
		}
		$pop.find( '#ecodv2_label_carrier_all, .ecodv2-label-pkg-carrier, #ecodv2_label_carrier_sel' ).each( function() {
			if ( $( this ).find( 'option' ).filter( function() { return this.value === carrier; } ).length ) {
				$( this ).val( carrier );
			}
		} );
		setTimeout( function() {
			ecodv2_label_step( 2 );
		}, 0 );
	} );
	$( document ).on( 'change', '#ecodv2_label_carrier_all', function() {
		$( '#ecodv2_label_popup .ecodv2-label-pkg:not(.is-done) .ecodv2-label-pkg-carrier' ).val( $( this ).val() );
	} );
	/* Enter in a tracking box: on to the next empty one, then Save. */
	$( document ).on( 'keydown', '#ecodv2_label_popup .ecodv2-label-pkg-tracking', function( e ) {
		if ( 'Enter' !== e.key ) {
			return;
		}
		e.preventDefault();
		var $all = $( '#ecodv2_label_popup .ecodv2-label-pkg:not(.is-done) .ecodv2-label-pkg-tracking' ), at = $all.index( this ), $next = $all.slice( at + 1 ).filter( function() {
			return '' === $.trim( this.value );
		} ).first();
		if ( $next.length ) {
			$next.trigger( 'focus' );
		} else {
			ecodv2_fulfill_save();
		}
	} );
	$( document ).on( 'keydown', '#ecodv2_label_tracking', function( e ) {
		if ( 'Enter' === e.key ) {
			e.preventDefault();
			ecodv2_fulfill_save();
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
		ecodv2_line_toast_expect( ecodv2_t( 'line_saved', 'Line saved.' ) );
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
   6.0.2 — Paid and the balance under the grand total. What was paid does
   not change when the order does, so the balance is the new grand total
   less what was paid ( plus any overpayment already refunded ).
   ==================================================================== */
function ecodv2_sync_balance() {
	var row = document.getElementById( 'ec_admin_order_details_totals_balance_row' );
	var grand_el = document.getElementById( 'ec_admin_order_details_totals_grand_total' );
	if ( ! row || ! grand_el ) {
		return;
	}
	var grand = parseFloat( String( grand_el.textContent ).replace( /[^0-9.\-]/g, '' ) ) || 0;
	var paid = parseFloat( row.getAttribute( 'data-paid' ) ) || 0;
	var back = parseFloat( row.getAttribute( 'data-overpaid-refunded' ) ) || 0;
	var balance = Math.round( ( grand - paid + back ) * 100 ) / 100;
	var state = 'paid';
	if ( balance >= 0.005 ) {
		state = ( paid >= 0.005 ) ? 'partial' : 'unpaid';
	} else if ( balance <= -0.005 ) {
		state = 'overpaid';
	}
	row.className = row.className.replace( /\bis-(unpaid|partial|overpaid|paid)\b/g, '' ).replace( /\s+$/, '' ) + ' is-' + state;
	var label = document.getElementById( 'ec_admin_order_details_totals_balance_label' );
	if ( label ) {
		label.textContent = row.getAttribute( 'data-label-' + state ) || label.textContent;
	}
	var amount = document.getElementById( 'ec_admin_order_details_totals_balance' );
	if ( amount ) {
		amount.textContent = Math.abs( balance ).toFixed( 2 );
	}
	row.setAttribute( 'title', 'overpaid' === state ? ( row.getAttribute( 'data-title-overpaid' ) || '' ) : '' );
	jQuery( document ).trigger( 'ecodv2_balance_changed', [ state, balance ] );
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
		pop.setAttribute( 'role', 'alertdialog' );
		pop.innerHTML = '<div class="ecodv2-del-pop-msg"></div>'
			+ '<div class="ecodv2-del-pop-actions">'
			+ '<button type="button" class="ecv2-btn ecv2-btn-sm ecodv2-del-cancel">' + ecodv2_esc( ecodv2_t( 'cancel', 'Cancel' ) ) + '</button>'
			+ '<button type="button" class="ecv2-btn ecv2-btn-sm ecodv2-del-go">' + ecodv2_esc( ecodv2_t( 'remove', 'Remove' ) ) + '</button>'
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
			ecodv2_line_toast_expect( ecodv2_t( 'line_removed', 'Line removed.' ) );
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
	/* 6.0.2: a line with refunded units was already given back: removing it would count it twice ( refund due ). */
	var del_msg = ecodv2_t( 'remove_line', 'Remove this line from the order?' );
	var del_line = null;
	if ( window.ecodv2_lines && window.ecodv2_lines.length ) {
		for ( var li = 0; li < window.ecodv2_lines.length; li++ ) {
			if ( String( window.ecodv2_lines[ li ].id ) === String( id ) ) {
				del_line = window.ecodv2_lines[ li ];
			}
		}
	}
	if ( del_line && parseInt( del_line.refunded, 10 ) > 0 ) {
		del_msg += ' ' + ecodv2_t( 'remove_refunded', 'This line was refunded. Removing it lowers the order total again, so the order will show a refund due for what was already refunded.' );
	}
	pop.querySelector( '.ecodv2-del-pop-msg' ).textContent = del_msg;
	setTimeout( function() { pop.querySelector( '.ecodv2-del-cancel' ).focus(); }, 20 );
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
	/* 6.0.2: start in the product search ( keyboard users landed nowhere ). */
	setTimeout( function() {
		var $p = jQuery( '#order_line_add_product_id' );
		if ( $p.data( 'select2' ) ) {
			$p.select2( 'open' );
		} else if ( $p.length ) {
			$p.trigger( 'focus' );
		}
	}, 200 );
	return false;
}

function ecodv2_close_add_line_modal() {
	document.body.classList.remove( 'ecodv2-add-open' );
	/* Reset the picker so the next open starts clean ( works for plain select and select2 ). */
	var $p = jQuery( '#order_line_add_product_id' );
	if ( $p.length ) {
		if ( $p.data( 'select2' ) ) { $p.select2( 'close' ); }
		$p.val( '0' ).trigger( 'change' );
	}
	var qty = document.getElementById( 'order_line_add_quantity' );
	if ( qty ) { qty.value = '1'; }
	ecodv2_add_base = 0;
	ecodv2_add_onetime = 0;
	jQuery( '#ecodv2_add_unit, #ecodv2_add_total' ).val( '0.00' );
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
		var $save = $( '#ecodv2_add_line_save' );
		host.innerHTML = '';
		host.classList.remove( 'has-options' );
		ecodv2_add_unit_touched = false;
		ecodv2_add_onetime = 0;
		$save.prop( 'disabled', true );
		if ( '0' === pid || ! pid ) {
			return;
		}
		/* 6.0.2: Add stays off until the product's price and options are in ( a quick click saved a 0.00 line ). */
		modal.classList.add( 'is-loading' );
		$.post( wpeasycart_admin_ajax_object.ajax_url, {
			action: 'ecv2_line_product_data', nonce: nonce, product_id: pid
		}, function( response ) {
			if ( pid !== document.getElementById( 'order_line_add_product_id' ).value ) {
				return; /* another product was picked meanwhile */
			}
			if ( ! response || ! response.success ) {
				ecodv2_save_toast( ( response && response.data && response.data.message ) ? response.data.message : 'The product could not be loaded.', true );
				return;
			}
			ecodv2_add_base = parseFloat( response.data.price ) || 0;
			modal.setAttribute( 'data-title', response.data.title || '' );
			( response.data.options || [] ).forEach( function( option ) {
				var id = 'ecodv2_add_option_' + option.slot;
				var wrap = document.createElement( 'div' );
				wrap.className = 'ecodv2-field';
				var label = document.createElement( 'label' );
				label.textContent = option.label;
				label.setAttribute( 'for', id );
				var sel = document.createElement( 'select' );
				sel.id = id;
				sel.setAttribute( 'data-slot', option.slot );
				var none = document.createElement( 'option' );
				none.value = '';
				none.textContent = '—';
				sel.appendChild( none );
				( option.items || [] ).forEach( function( item ) {
					var o = document.createElement( 'option' );
					o.value = item.optionitem_id;
					/* 6.0.2: WP EasyCart PRO 6.0.2 says what the choice costs in the store currency ( price_label ); an older
					   PRO only sends the number. */
					var price = parseFloat( item.optionitem_price ) || 0;
					var extra = ( 'string' === typeof item.price_label ) ? item.price_label : ( price > 0 ? '+' + price.toFixed( 2 ) : '' );
					o.textContent = item.optionitem_name + ( '' !== extra ? ' (' + extra + ')' : '' );
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
			host.classList.toggle( 'has-options', !! host.children.length );
			ecodv2_add_recalc( true );
			$save.prop( 'disabled', false );
		} ).fail( function() {
			ecodv2_save_toast( 'The product could not be loaded.', true );
		} ).always( function() {
			modal.classList.remove( 'is-loading' );
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
				/* 6.0.2: WP EasyCart PRO 6.0.2 answers recalc: the reloaded page opens Edit totals with the tax worked out
				   again for the new line, for the merchant to check and save ( the grand total moved by the line only ). */
				var next = window.location.href;
				if ( response.data && response.data.recalc && ! /[?&]ecodv2_totals=recalc(&|$)/.test( next ) ) {
					next = next.replace( /#.*$/, '' ) + ( -1 === next.indexOf( '?' ) ? '?' : '&' ) + 'ecodv2_totals=recalc';
				}
				setTimeout( function() {
					if ( next === window.location.href ) {
						window.location.reload();
					} else {
						window.location.href = next;
					}
				}, 700 );
			} else {
				btn.disabled = false;
				ecodv2_save_toast( ( response && response.data && response.data.message ) ? response.data.message : 'The line could not be added.', true );
			}
		} ).fail( function() {
			btn.disabled = false;
			ecodv2_save_toast( 'The line could not be added.', true );
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
   Pinned note: the note for the team at the top of the order.
   6.0.2: saves through its own request ( ecv2_order_pinned_note ), which
   saves the note only and answers whether it did; Unpin offers Undo.
   ==================================================================== */
function ecodv2_pin_edit() {
	var legacy = document.getElementById( 'order_notes' );
	var input = document.getElementById( 'ecodv2_pin_input' );
	if ( ! input ) {
		return false;
	}
	input.value = legacy ? legacy.value : '';
	jQuery( '#ecodv2_pin_strip' ).hide();
	jQuery( '#ecodv2_pin_add_btn' ).prop( 'hidden', true );
	jQuery( '#ecodv2_pin_editor' ).show();
	input.focus();
	return false;
}

function ecodv2_pin_cancel() {
	var has = '' !== jQuery.trim( jQuery( '#order_notes' ).val() );
	jQuery( '#ecodv2_pin_editor' ).hide();
	jQuery( '#ecodv2_pin_strip' ).toggle( has );
	jQuery( '#ecodv2_pin_add_btn' ).prop( 'hidden', has );
	jQuery( '#ecodv2_pin_unpin' ).toggle( has );
	return false;
}

/* restore: the note to put back ( Undo after Unpin ). */
function ecodv2_pin_save( unpin, restore ) {
	var $ = jQuery;
	var legacy = document.getElementById( 'order_notes' );
	var btn = document.getElementById( 'ecodv2_pin_save_btn' );
	var before = legacy ? legacy.value : '';
	var value = ( 'string' === typeof restore ) ? restore : ( unpin ? '' : $.trim( $( '#ecodv2_pin_input' ).val() ) );
	var fail = function( answer ) {
		ecodv2_save_toast( ( answer && answer.data && answer.data.message ) ? answer.data.message : ecodv2_t( 'failed', 'This change could not be saved. Reload the page and try again.' ), true );
	};
	if ( btn ) {
		btn.disabled = true;
		btn.setAttribute( 'aria-busy', 'true' );
	}
	$.ajax( {
		url: wpeasycart_admin_ajax_object.ajax_url,
		type: 'post',
		dataType: 'json',
		data: {
			action: 'ecv2_order_pinned_note',
			order_id: $( '#order_id' ).val(),
			note: value,
			wp_easycart_nonce: $( '#wp_easycart_order_details_nonce' ).val()
		}
	} ).done( function( r ) {
		if ( ! r || ! r.success ) {
			fail( r );
			return;
		}
		var note = ( r.data && 'string' === typeof r.data.note ) ? r.data.note : value;
		if ( legacy ) {
			legacy.value = note;
		}
		$( '#ecodv2_pin_text' ).text( note );
		ecodv2_pin_cancel();
		var undo = ( '' === note && '' !== before ) ? { label: ecodv2_t( 'undo', 'Undo' ), fn: function() { ecodv2_pin_save( false, before ); } } : null;
		ecodv2_save_toast( ( r.data && r.data.message ) ? r.data.message : ecodv2_t( '' === note ? 'pin_removed' : 'pin_saved', 'Saved' ), false, undo );
		ecodv2_history_refresh();
	} ).fail( function( xhr ) {
		fail( ecodv2_json( xhr ) );
	} ).always( function() {
		if ( btn ) {
			btn.disabled = false;
			btn.removeAttribute( 'aria-busy' );
		}
	} );
	return false;
}
/* ====================================================================
   6.0.2 — the order screen redesign
   ( review: https://claude.ai/artifact/9FU888SGrpJoFceHXNspwX )
   Wording and the store's money format come from #ecodv2_screen_data
   ( wp_easycart_admin_order_screen ).
   ==================================================================== */
function ecodv2_screen() {
	if ( ! window.ecodv2_screen_cache ) {
		var el = document.getElementById( 'ecodv2_screen_data' ), parsed = {};
		try { parsed = el ? JSON.parse( el.textContent ) : {}; } catch ( e ) { parsed = {}; }
		parsed.text = parsed.text || {};
		parsed.money = parsed.money || {};
		window.ecodv2_screen_cache = parsed;
	}
	return window.ecodv2_screen_cache;
}

function ecodv2_t( key, fallback ) {
	var text = ecodv2_screen().text;
	return ( text && 'string' === typeof text[ key ] && '' !== text[ key ] ) ? text[ key ] : fallback;
}

/* An amount as the store prints it ( ec_currency::get_currency_display(), without conversion ). */
function ecodv2_money( amount ) {
	var c = ecodv2_screen().money, n = parseFloat( amount );
	if ( isNaN( n ) ) {
		n = 0;
	}
	var decimals = ( null == c.decimals ) ? 2 : parseInt( c.decimals, 10 );
	var negative = n < -0.0000001, parts = Math.abs( n ).toFixed( decimals ).split( '.' );
	var number = parts[0].replace( /\B(?=(\d{3})+(?!\d))/g, null == c.group ? ',' : c.group ) + ( parts[1] ? ( null == c.dec ? '.' : c.dec ) + parts[1] : '' );
	var symbol = ( null == c.symbol ) ? '$' : c.symbol, before = ( null == c.before ) ? true : !! c.before, out = c.code ? c.code + ' ' : '';
	if ( negative && c.negBefore ) {
		out += '-';
	}
	if ( before ) {
		out += symbol;
	}
	if ( negative && ! c.negBefore ) {
		out += '-';
	}
	out += number;
	if ( ! before ) {
		out += symbol;
	}
	return out;
}

/* The formatted amount beside a hidden raw number ( the raw one is what WP EasyCart PRO and the older scripts rewrite ). */
function ecodv2_amt_paint( raw ) {
	var shown = ( raw && raw.id ) ? document.querySelector( '.ecodv2-amt[data-amt-for="' + raw.id + '"]' ) : null;
	if ( ! shown ) {
		return;
	}
	var v = parseFloat( String( raw.textContent ).replace( /[^0-9.\-]/g, '' ) );
	if ( isNaN( v ) ) {
		v = 0;
	}
	shown.textContent = shown.getAttribute( 'data-sign' ) ? '−' + ecodv2_money( Math.abs( v ) ) : ecodv2_money( v );
	/* 6.0.2 bug round 14: a fee row that comes to nothing stays hidden, and shows once Edit totals gives it an amount. */
	var row = raw.closest ? raw.closest( '[data-hide-zero]' ) : null;
	if ( row ) {
		row.classList.toggle( 'ec_admin_initial_hide', Math.abs( v ) < 0.005 );
	}
}

function ecodv2_raw_amount( id ) {
	var el = document.getElementById( id );
	return el ? ( parseFloat( String( el.textContent ).replace( /[^0-9.\-]/g, '' ) ) || 0 ) : 0;
}

/* The JSON a request answered ( null when it answered something else ). */
function ecodv2_json( xhr ) {
	if ( ! xhr ) {
		return null;
	}
	if ( xhr.responseJSON && 'object' === typeof xhr.responseJSON ) {
		return xhr.responseJSON;
	}
	var text = ( 'string' === typeof xhr.responseText ) ? jQuery.trim( xhr.responseText ) : '';
	if ( '{' !== text.charAt( 0 ) ) {
		return null;
	}
	try {
		return JSON.parse( text );
	} catch ( e ) {
		return null;
	}
}

function ecodv2_history_refresh() {
	if ( jQuery( document.getElementById( 'wpeasycart_order_history_refresh' ) ).length && 'function' === typeof window.ec_order_history_refresh ) {
		window.ec_order_history_refresh();
	}
}

function ecodv2_paint_email( id, email ) {
	var span = document.getElementById( id );
	if ( ! span ) {
		return;
	}
	span.innerHTML = '';
	email = jQuery.trim( email || '' );
	if ( '' !== email ) {
		var link = document.createElement( 'a' );
		link.href = 'mailto:' + email;
		link.textContent = email;
		span.appendChild( link );
	}
}

/* Bill to reads "Same as shipping address" while the two match. */
function ecodv2_sync_same_address() {
	var box = document.getElementById( 'ecodv2_billto' );
	if ( ! box ) {
		return;
	}
	var parts = [ 'name', 'company', 'address1', 'address2', 'address3', 'country' ], same = true;
	var text = function( id ) {
		var el = document.getElementById( id );
		return el ? jQuery.trim( el.textContent ).toLowerCase() : '';
	};
	for ( var i = 0; i < parts.length; i++ ) {
		if ( text( 'ec_admin_order_details_billing_' + parts[ i ] ) !== text( 'ec_admin_order_details_shipping_' + parts[ i ] ) ) {
			same = false;
		}
	}
	box.classList.toggle( 'is-same', same && '' !== text( 'ec_admin_order_details_shipping_address1' ) );
}

/* Is anything open that J / K must not leave behind? */
function ecodv2_layer_open() {
	var b = document.body.classList;
	if ( b.contains( 'ecodv2-drawer-open' ) || b.contains( 'ecodv2-label-open' ) || b.contains( 'ecodv2-line-open' ) || b.contains( 'ecodv2-add-open' ) || b.contains( 'ecodv2-hdrawer-open' ) || b.contains( 'ecpk-modal-open' ) ) {
		return true;
	}
	if ( document.querySelector( '#ecodv2_email_dialog, #ecodv2_email_preview, .ecodv2-modal.is-open, .ecodv2-cnotes-popover.is-open, .ecdv2-menu.is-open, #ecodv2_line_del_pop.is-open, #ecodv2_cmdk, #ecodv2_scan' ) ) {
		return true;
	}
	var confirm_box = document.getElementById( 'ecodv2_confirm' );
	if ( confirm_box && ! confirm_box.hidden ) {
		return true;
	}
	return jQuery( '#ecodv2_pin_editor:visible, #ec_admin_order_details_totals_form:visible, #ec_admin_order_details_order_date_edit:visible, .ecv2-modal-overlay:visible' ).length > 0;
}

function ecodv2_scroll_to( id ) {
	var el = document.getElementById( id );
	if ( ! el ) {
		return false;
	}
	el.scrollIntoView( { behavior: 'smooth', block: 'start' } );
	el.classList.remove( 'ecodv2-flash' );
	void el.offsetWidth;
	el.classList.add( 'ecodv2-flash' );
	if ( ! el.hasAttribute( 'tabindex' ) ) {
		el.setAttribute( 'tabindex', '-1' );
	}
	try {
		el.focus( { preventScroll: true } );
	} catch ( e ) {
		el.focus();
	}
	return false;
}

/* Everything in a dialog the keyboard can reach. */
function ecodv2_focusables( el ) {
	if ( ! el ) {
		return [];
	}
	return Array.prototype.filter.call( el.querySelectorAll( 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])' ), function( node ) {
		return !! ( node.offsetWidth || node.offsetHeight || node.getClientRects().length ) && ! node.closest( '[hidden]' );
	} );
}

function ecodv2_trap_tab( el, e ) {
	var list = ecodv2_focusables( el );
	if ( ! list.length ) {
		return;
	}
	var first = list[0], last = list[ list.length - 1 ];
	if ( ! el.contains( document.activeElement ) ) {
		e.preventDefault();
		first.focus();
	} else if ( e.shiftKey && document.activeElement === first ) {
		e.preventDefault();
		last.focus();
	} else if ( ! e.shiftKey && document.activeElement === last ) {
		e.preventDefault();
		first.focus();
	}
}

/* A question in the page's own style. o: title, body, yes, no, alt ( a third choice, e.g. Refund the payment instead ),
   on_yes, on_alt. Cancel has the focus, so Enter never confirms by accident. */
function ecodv2_confirm( o ) {
	var $ = jQuery, box = document.getElementById( 'ecodv2_confirm' ), back = document.getElementById( 'ecodv2_confirm_backdrop' );
	if ( ! box ) {
		if ( window.confirm( o.body || o.title ) && o.on_yes ) {
			o.on_yes();
		}
		return;
	}
	var $yes = $( '#ecodv2_confirm_yes' ), $no = $( '#ecodv2_confirm_no' ), $alt = $( '#ecodv2_confirm_alt' ), opener = document.activeElement;
	$( '#ecodv2_confirm_title' ).text( o.title || '' );
	$( '#ecodv2_confirm_body' ).text( o.body || '' ).prop( 'hidden', ! o.body );
	$yes.text( o.yes || 'OK' );
	$no.text( o.no || ecodv2_t( 'cancel', 'Cancel' ) );
	$alt.text( o.alt || '' ).prop( 'hidden', ! o.alt );
	var close = function() {
		box.hidden = true;
		if ( back ) {
			back.hidden = true;
		}
		$( document ).off( 'keydown.ecodv2confirm' );
		$yes.off( 'click.ecodv2confirm' );
		$no.off( 'click.ecodv2confirm' );
		$alt.off( 'click.ecodv2confirm' );
		$( back ).off( 'click.ecodv2confirm' );
		if ( opener && opener.focus && document.body.contains( opener ) ) {
			opener.focus();
		}
	};
	$yes.on( 'click.ecodv2confirm', function() { close(); if ( o.on_yes ) { o.on_yes(); } } );
	$no.on( 'click.ecodv2confirm', close );
	$alt.on( 'click.ecodv2confirm', function() { close(); if ( o.on_alt ) { o.on_alt(); } } );
	$( back ).on( 'click.ecodv2confirm', close );
	$( document ).on( 'keydown.ecodv2confirm', function( e ) {
		if ( 'Escape' === e.key ) {
			e.preventDefault();
			e.stopImmediatePropagation();
			close();
		} else if ( 'Tab' === e.key ) {
			ecodv2_trap_tab( box, e );
		}
	} );
	box.hidden = false;
	if ( back ) {
		back.hidden = false;
	}
	setTimeout( function() { $no.trigger( 'focus' ); }, 20 );
}

/* ---------- Status ---------- */
function ecodv2_status_apply( value ) {
	var $ = jQuery, sel = document.getElementById( 'orderstatus_id' ), prev = String( sel.value );
	var $item = $( '#ecodv2_status_menu .ecodv2-status-item[data-status-id="' + value + '"]' );
	var $prev_item = $( '#ecodv2_status_menu .ecodv2-status-item.is-current' );
	var pill = document.getElementById( 'ecodv2_status_pill' );
	var paint = function( $it ) {
		if ( ! $it.length ) {
			return;
		}
		var dot = document.getElementById( 'ecodv2_status_pill_dot' ), label = document.getElementById( 'ecodv2_status_pill_label' );
		if ( dot ) {
			dot.style.background = $it.attr( 'data-color' ) || '#e5e7eb';
		}
		if ( label ) {
			label.textContent = $it.find( '.ecodv2-status-item-label' ).text();
		}
		$( '#ecodv2_status_menu .ecodv2-status-item' ).removeClass( 'is-current' ).attr( 'aria-selected', 'false' );
		$it.addClass( 'is-current' ).attr( 'aria-selected', 'true' );
	};
	var settle = function() {
		document.body.classList.remove( 'ecodv2-status-busy' );
		if ( pill ) {
			pill.removeAttribute( 'aria-busy' );
			pill.classList.remove( 'is-busy' );
		}
	};
	var revert = function( message ) {
		sel.value = prev;
		paint( $prev_item );
		ecodv2_save_toast( message || ecodv2_t( 'status_failed', 'The status could not be changed. Reload the page and try again.' ), true );
	};
	sel.value = value;
	paint( $item );
	document.body.classList.add( 'ecodv2-status-busy' );
	if ( pill ) {
		pill.setAttribute( 'aria-busy', 'true' );
		pill.classList.add( 'is-busy' );
	}
	$.ajax( {
		url: wpeasycart_admin_ajax_object.ajax_url,
		type: 'post',
		dataType: 'json',
		data: {
			action: 'ec_admin_ajax_edit_orderstatus',
			order_id: $( '#order_id' ).val(),
			orderstatus_id: value,
			wp_easycart_nonce: $( '#wp_easycart_order_details_nonce' ).val()
		}
	} ).done( function( r ) {
		settle();
		if ( ! r || ! r.success ) {
			revert( ( r && r.data && r.data.message ) ? r.data.message : '' );
			return;
		}
		ecodv2_status_reply( r.data || {} );
		if ( r.data && r.data.message ) {
			ecodv2_save_toast( r.data.message );
		}
		ecodv2_history_refresh();
	} ).fail( function( xhr ) {
		settle();
		var answer = ecodv2_json( xhr );
		revert( ( answer && answer.data && answer.data.message ) ? answer.data.message : '' );
	} );
}

/* The page after a status change: the payment badge, the shipping state and what was paid ( the server's answer ). */
function ecodv2_status_reply( d ) {
	var $ = jQuery;
	if ( d.badge && d.badge.label ) {
		$( '#wpeasycart-payment-status' ).removeClass( 'payment-paid payment-processing payment-bad payment-neutral' ).addClass( d.badge['class'] || '' ).text( d.badge.label );
	}
	if ( d.fulfillment ) {
		ecodv2_apply_fulfillment( d.fulfillment );
	}
	/* 6.0.2: the steps and the next step panel as they read now. */
	if ( 'string' === typeof d.steps_html && d.steps_html ) {
		var steps = document.getElementById( 'ecodv2_steps' );
		if ( steps ) {
			steps.innerHTML = d.steps_html;
		}
	}
	if ( 'string' === typeof d.next_html && d.next_html ) {
		var panel = document.getElementById( 'ecodv2_next_panel' ), holder = document.createElement( 'div' );
		holder.innerHTML = d.next_html;
		if ( panel && holder.firstElementChild ) {
			panel.parentNode.replaceChild( holder.firstElementChild, panel );
			ecodv2_next_init();
		}
	}
	/* 6.0.2 bug round 14: the notices above the next step ( a hold ends with its status; Stock not taken ends when it is done ). */
	if ( 'string' === typeof d.flags_html ) {
		var attention = document.getElementById( 'ecodv2_attention' );
		if ( attention ) {
			attention.innerHTML = d.flags_html;
			attention.hidden = '' === d.flags_html;
		}
	}
	if ( d.fulfillment ) {
		$( '#ecodv2_wrap' ).toggleClass( 'is-packing', ( 'unfulfilled' === d.fulfillment || 'partial' === d.fulfillment ) && $( 'input[data-ecodv2-pack]' ).length > 0 );
	}
	if ( null !== d.paid && undefined !== d.paid ) {
		var row = document.getElementById( 'ec_admin_order_details_totals_balance_row' ), paid = Number( d.paid ) || 0;
		if ( row ) {
			row.setAttribute( 'data-paid', paid.toFixed( 2 ) );
			$( '#ec_admin_order_details_totals_paid' ).text( paid.toFixed( 2 ) );
			$( '#ec_admin_order_details_totals_paid_row' ).toggleClass( 'ec_admin_initial_hide', paid < 0.005 );
			ecodv2_sync_balance();
		}
	}
	$( document ).trigger( 'ecodv2_status_changed', [ d ] );
}

/* The shipping badge, banner, Fulfill button and next step for a state: fulfilled | partial | unfulfilled | digital |
   pickup | none. */
function ecodv2_apply_fulfillment( state ) {
	var $ = jQuery, banner = document.getElementById( 'ecodv2_fulfill_banner' ), badge = document.getElementById( 'ecodv2_fulfill_badge' );
	if ( ! banner || ! state ) {
		return;
	}
	var current = ( banner.className.match( /ecodv2-fulfill-banner-(\w+)/ ) || [] )[ 1 ] || 'none';
	if ( current === state ) {
		return;
	}
	var local = '1' === banner.getAttribute( 'data-local-pickup' );
	var icons = { fulfilled: 'yes-alt', unfulfilled: 'warning', partial: 'clock', digital: 'download', pickup: 'store', none: 'clock' };
	var icon_for = function( s ) {
		return ( local && ( 'fulfilled' === s || 'unfulfilled' === s ) ) ? 'store' : ( icons[ s ] || 'clock' );
	};
	var open = ( 'unfulfilled' === state || 'partial' === state ) && ! local;
	banner.className = banner.className.replace( /ecodv2-fulfill-banner-\w+/, 'ecodv2-fulfill-banner-' + state );
	$( '#ecodv2_wrap' ).attr( 'data-fulfillment', state );
	var msg = document.getElementById( 'ecodv2_fulfill_message' );
	if ( msg ) {
		msg.textContent = banner.getAttribute( 'data-msg-' + state ) || ecodv2_t( 'fulfill_msg_' + state, msg.textContent );
	}
	var icon = banner.querySelector( '.ecodv2-fulfill-row > .dashicons' );
	if ( icon ) {
		icon.className = 'dashicons dashicons-' + icon_for( state );
	}
	$( '#ecodv2_create_label_btn' ).toggle( open );
	if ( badge ) {
		var label = badge.getAttribute( 'data-label-' + state );
		if ( 'none' === state || ! label ) {
			badge.style.display = 'none';
		} else {
			var classes = { fulfilled: 'ecodv2-fulfill-badge-ok', digital: 'ecodv2-fulfill-badge-ok', pickup: 'ecodv2-fulfill-badge-pickup', unfulfilled: 'ecodv2-fulfill-badge-warn', partial: 'ecodv2-fulfill-badge-warn' };
			badge.style.display = '';
			badge.className = 'ecodv2-fulfill-badge ' + ( classes[ state ] || '' );
			badge.setAttribute( 'data-state', state );
			var badge_icon = badge.querySelector( '.dashicons' );
			if ( badge_icon ) {
				badge_icon.className = 'dashicons dashicons-' + icon_for( state );
			}
			var badge_label = document.getElementById( 'ecodv2_fulfill_badge_label' );
			if ( badge_label ) {
				badge_label.textContent = label;
			}
		}
	}
	/* 6.0.2: the next step panel is redrawn from the status change's answer ( ecodv2_status_reply() ); no header button. */
	if ( document.getElementById( 'ecodv2_next_panel' ) ) {
		return;
	}
	var next = document.getElementById( 'ecodv2_next_btn' );
	if ( next && 'fulfill' === next.getAttribute( 'data-step' ) ) {
		next.hidden = ! open;
	} else if ( ! next && open ) {
		var actions = document.querySelector( '.ecodv2-header-actions' );
		if ( actions ) {
			next = document.createElement( 'button' );
			next.type = 'button';
			next.id = 'ecodv2_next_btn';
			next.className = 'ecv2-btn ecv2-btn-primary ecodv2-next-btn';
			next.setAttribute( 'data-step', 'fulfill' );
			next.innerHTML = '<span class="dashicons dashicons-airplane" aria-hidden="true"></span> ';
			next.appendChild( document.createTextNode( ecodv2_t( 'partial' === state ? 'fulfill_remaining' : 'fulfill_items', 'Fulfill items' ) ) );
			next.onclick = function() {
				ecodv2_fulfill_open();
				return false;
			};
			actions.appendChild( next );
		}
	}
}

/* ---------- Fulfill ( free from 6.0.2 ): step 1 makes the label, step 2 records the tracking ---------- */
function ecodv2_fulfill_open( shipment_id ) {
	var pop = document.getElementById( 'ecodv2_label_popup' );
	ecodv2_menu_close();
	if ( ! pop ) {
		return false;
	}
	document.body.classList.add( 'ecodv2-label-open' );
	jQuery( '#ecodv2_label_status' ).text( '' ).removeClass( 'is-error is-saved' );
	/* 6.0.2 bug round 6: the packages changed and the rows were not drawn again yet ( a failed refresh ): try again now. */
	if ( ecodv2_packages_stale() && ! ecodv2_refresh_pending() ) {
		ecodv2_screen_refresh();
	}
	var $row = shipment_id ? jQuery( pop ).find( '.ecodv2-label-pkg[data-shipment-id="' + ( parseInt( shipment_id, 10 ) || 0 ) + '"]' ) : jQuery();
	if ( $row.length ) {
		ecodv2_label_step( 2 );
		$row.find( '.ecodv2-label-pkg-tracking' ).trigger( 'focus' );
	} else {
		ecodv2_label_step( 1 );
	}
	return false;
}

function ecodv2_fulfill_save() {
	if ( document.getElementById( 'ecodv2_label_pkgs' ) ) {
		return ecodv2_label_save_all();
	}
	var $ = jQuery, tracking = $.trim( $( '#ecodv2_label_tracking' ).val() || '' );
	if ( '' === tracking && ! $( '#ecodv2_label_mark_shipped' ).is( ':checked' ) ) {
		$( '#ecodv2_label_status' ).removeClass( 'is-saved' ).addClass( 'is-error' ).text( ecodv2_t( 'fulfill_need', 'Enter a tracking number, or tick Mark as shipped.' ) );
		$( '#ecodv2_label_tracking' ).trigger( 'focus' );
		return false;
	}
	return ecodv2_fulfill_post( $( '#ecodv2_label_carrier_sel' ).val() || '', tracking );
}

/* An order without packages ( or shipped without tracking ): ecv2_order_screen_fulfill saves the tracking, marks the
   order shipped and emails the customer, as ticked. */
function ecodv2_fulfill_post( carrier, tracking ) {
	var $ = jQuery, $btn = $( '#ecodv2_label_save_all' ), $status = $( '#ecodv2_label_status' );
	if ( $btn.prop( 'disabled' ) ) {
		return false;
	}
	var release = function() {
		ecodv2_btn_busy( $btn[0], false );
	};
	var failed = function( answer ) {
		release();
		$status.removeClass( 'is-saved' ).addClass( 'is-error' ).text( ( answer && answer.data && answer.data.message ) ? answer.data.message : ecodv2_t( 'fulfill_failed', 'The shipment could not be saved. Reload the order and try again.' ) );
	};
	/* 6.0.2 bug round 6: Saving… on the button until the page reloads. */
	ecodv2_btn_busy( $btn[0], true );
	$status.removeClass( 'is-error is-saved' ).text( '' );
	$.ajax( {
		url: wpeasycart_admin_ajax_object.ajax_url,
		type: 'post',
		dataType: 'json',
		data: {
			action: 'ecv2_order_screen_fulfill',
			order_id: $( '#order_id' ).val(),
			carrier: carrier,
			tracking_number: tracking,
			mark_shipped: $( '#ecodv2_label_mark_shipped' ).is( ':checked' ) ? 1 : 0,
			notify: $( '#ecodv2_label_send_email' ).is( ':checked' ) ? 1 : 0,
			wp_easycart_nonce: $( '#wp_easycart_order_details_nonce' ).val()
		}
	} ).done( function( r ) {
		if ( r && r.success ) {
			$status.removeClass( 'is-error' ).addClass( 'is-saved' ).text( ( r.data && r.data.message ) ? r.data.message : ecodv2_t( 'saved', 'Saved' ) );
			ecodv2_leaving = true;
			/* 6.0.2 bug round 14: an order not paid yet says so ( and stays unpaid ): time to read it before the reload. */
			setTimeout( function() { window.location.reload(); }, ( r.data && r.data.unpaid ) ? 3200 : 1200 );
			return;
		}
		failed( r );
	} ).fail( function( xhr ) {
		failed( ecodv2_json( xhr ) );
	} );
	return false;
}

/* ---------- Line edits say what they did to the total ---------- */
var ecodv2_line_toast = null;
var ecodv2_line_toast_timer = null;

function ecodv2_line_toast_expect( message ) {
	ecodv2_line_toast = { message: message, before: ecodv2_raw_amount( 'ec_admin_order_details_totals_grand_total' ) };
	clearTimeout( ecodv2_line_toast_timer );
	ecodv2_line_toast_timer = setTimeout( ecodv2_line_toast_flush, 5000 );
}

function ecodv2_line_toast_flush() {
	if ( ! ecodv2_line_toast ) {
		return;
	}
	var pending = ecodv2_line_toast, after = ecodv2_raw_amount( 'ec_admin_order_details_totals_grand_total' ), message = pending.message;
	ecodv2_line_toast = null;
	clearTimeout( ecodv2_line_toast_timer );
	if ( Math.abs( after - pending.before ) >= 0.005 ) {
		message += ' ' + ecodv2_t( 'totals_moved', 'Total %1$s → %2$s.' ).replace( '%1$s', ecodv2_money( pending.before ) ).replace( '%2$s', ecodv2_money( after ) );
	}
	ecodv2_save_toast( message );
}

/* ---------- Address and contact corrections ( free from 6.0.2, D4 ) ----------
   WP EasyCart PRO 6.0.1's script saves them itself; without it these run. Both post to the same actions. */

/* WP EasyCart PRO 6.0.1's script posts the address and contact saves without the order screen's nonce, and WP EasyCart
   answers those actions now ( ecv2_order_screen_guard() ): add the nonce when a save left it out. Registered at load, so it
   is in place before any click. */
if ( window.jQuery && 'function' === typeof jQuery.ajaxPrefilter ) {
	jQuery.ajaxPrefilter( function( options ) {
		var d = ( 'string' === typeof options.data ) ? options.data : '', nonce;
		if ( ! /(^|&)action=ec_admin_ajax_save_order_(billing_address|shipping_address|management_details)(&|$)/.test( d ) || /(^|&)wp_easycart_nonce=/.test( d ) ) {
			return;
		}
		nonce = jQuery( '#wp_easycart_order_details_nonce' ).val();
		if ( nonce ) {
			options.data = d + '&wp_easycart_nonce=' + encodeURIComponent( nonce );
		}
	} );
}

function ecodv2_free_save_address( type ) {
	var $ = jQuery, parts = [ 'first_name', 'last_name', 'company_name', 'address_line_1', 'address_line_2', 'city', 'state', 'zip', 'country', 'phone' ];
	var data = { action: 'ec_admin_ajax_save_order_' + type + '_address', order_id: $( '#order_id' ).val(), wp_easycart_nonce: $( '#wp_easycart_order_details_nonce' ).val() };
	$.each( parts, function( i, part ) {
		data[ type + '_' + part ] = String( $( '#' + type + '_' + part ).val() || '' );
	} );
	return $.ajax( { url: wpeasycart_admin_ajax_object.ajax_url, type: 'post', dataType: 'json', data: data } ).done( function( r ) {
		if ( ! r || ! r.success ) {
			return; /* the drawer reports it and stays open */
		}
		var v = function( part ) { return $.trim( data[ type + '_' + part ] ); };
		var $country = $( '#' + type + '_country option:selected' );
		$( '#ec_admin_order_details_' + type + '_name' ).text( $.trim( v( 'first_name' ) + ' ' + v( 'last_name' ) ) );
		$( '#ec_admin_order_details_' + type + '_company' ).text( v( 'company_name' ) );
		$( '#ec_admin_order_details_' + type + '_address1' ).text( v( 'address_line_1' ) );
		$( '#ec_admin_order_details_' + type + '_address2' ).text( v( 'address_line_2' ) );
		$( '#ec_admin_order_details_' + type + '_address3' ).text( $.trim( v( 'city' ) + ' ' + v( 'state' ) + ' ' + v( 'zip' ) ) );
		$( '#ec_admin_order_details_' + type + '_country' ).text( '0' === String( $country.val() ) ? '' : $.trim( $country.text() ) );
		$( '#ec_admin_order_details_' + type + '_phone' ).text( v( 'phone' ) );
		ecodv2_sync_same_address();
		ecodv2_history_refresh();
	} );
}

function ecodv2_free_save_contact() {
	var $ = jQuery, keys = [ 'user_email', 'email_other', 'card_holder_name', 'creditcard_digits', 'cc_exp_month', 'cc_exp_year' ];
	var data = { action: 'ec_admin_ajax_save_order_management_details', order_id: $( '#order_id' ).val(), wp_easycart_nonce: $( '#wp_easycart_order_details_nonce' ).val() };
	$.each( keys, function( i, key ) {
		data[ key ] = $.trim( String( $( '#' + key ).val() || '' ) );
	} );
	$( '#user_email' ).removeClass( 'is-invalid' );
	return $.ajax( { url: wpeasycart_admin_ajax_object.ajax_url, type: 'post', dataType: 'json', data: data } ).done( function( r ) {
		if ( ! r || ! r.success ) {
			if ( r && r.data && r.data.field ) {
				$( '#' + r.data.field ).addClass( 'is-invalid' );
			}
			return;
		}
		$( '#ec_admin_order_details_card_holder_name' ).text( data.card_holder_name );
		ecodv2_drawer_after_save_repaint();
		ecodv2_history_refresh();
	} );
}

/* The Codes section: the gift card and coupon codes recorded on the order ( nothing else of the order info ). */
function ecodv2_codes_save() {
	var $ = jQuery, data = { action: 'ec_admin_ajax_edit_order_info', order_id: $( '#order_id' ).val(), wp_easycart_nonce: $( '#wp_easycart_order_details_nonce' ).val() };
	$.each( [ 'giftcard_id', 'promo_code' ], function( i, key ) {
		if ( document.getElementById( key ) ) {
			data[ key ] = String( $( '#' + key ).val() || '' );
		}
	} );
	return $.ajax( { url: wpeasycart_admin_ajax_object.ajax_url, type: 'post', dataType: 'json', data: data } ).done( function( r ) {
		if ( r && r.success ) {
			ecodv2_history_refresh();
		}
	} );
}

/* ---------- Leaving with unsaved changes ---------- */
var ecodv2_leaving = false;

function ecodv2_unsaved() {
	if ( ecodv2_leaving ) {
		return false;
	}
	if ( document.body.classList.contains( 'ecodv2-drawer-open' ) && ! ecodv2_drawer_pristine ) {
		return true;
	}
	var input = document.getElementById( 'ecodv2_pin_input' ), legacy = document.getElementById( 'order_notes' );
	if ( input && jQuery( '#ecodv2_pin_editor' ).is( ':visible' ) && jQuery.trim( input.value ) !== jQuery.trim( legacy ? legacy.value : '' ) ) {
		return true;
	}
	var pop = document.getElementById( 'ecodv2_cnotes_popover' ), notes = document.getElementById( 'order_customer_notes' );
	return !! ( pop && pop.classList.contains( 'is-open' ) && notes && null !== ecodv2_cnotes_saved && notes.value !== ecodv2_cnotes_saved );
}

/* ---------- Previous / next follow the list the merchant came from ---------- */
function ecodv2_nav_init() {
	var data = null, id = parseInt( ecodv2_screen().order_id, 10 ) || 0;
	try {
		data = JSON.parse( window.sessionStorage.getItem( 'wpec_order_nav' ) || 'null' );
	} catch ( e ) {
		data = null;
	}
	if ( ! id || ! data || ! data.ids || ! data.ids.length || ( Date.now() - ( parseInt( data.t, 10 ) || 0 ) ) > 43200000 ) {
		return;
	}
	var ids = data.ids.map( function( x ) { return parseInt( x, 10 ); } ), at = ids.indexOf( id );
	if ( -1 === at ) {
		return;
	}
	var set = function( which, target ) {
		var link = document.getElementById( 'ecodv2_nav_' + which ), off = document.getElementById( 'ecodv2_nav_' + which + '_off' ), el;
		if ( target ) {
			if ( ! link && off ) {
				link = document.createElement( 'a' );
				link.className = off.className.replace( /\s*ecodv2-nav-disabled/, '' );
				link.id = 'ecodv2_nav_' + which;
				link.innerHTML = off.innerHTML;
				link.setAttribute( 'aria-label', ecodv2_t( 'nav_' + which, '' ) );
				link.title = ecodv2_t( 'nav_' + which, '' );
				off.parentNode.replaceChild( link, off );
			}
			if ( link ) {
				link.href = 'admin.php?page=wp-easycart-orders&subpage=orders&order_id=' + target + '&ec_admin_form_action=edit';
			}
		} else if ( link ) {
			el = document.createElement( 'span' );
			el.className = link.className + ' ecodv2-nav-disabled';
			el.id = 'ecodv2_nav_' + which + '_off';
			el.innerHTML = link.innerHTML;
			link.parentNode.replaceChild( el, link );
		}
	};
	set( 'prev', ids[ at - 1 ] );
	set( 'next', ids[ at + 1 ] );
	var pos = document.getElementById( 'ecodv2_nav_pos' );
	if ( pos && ids.length > 1 ) {
		pos.textContent = ecodv2_t( 'nav_position', '%1$d of %2$d' ).replace( '%1$d', String( at + 1 ) ).replace( '%2$d', String( ids.length ) );
		pos.hidden = false;
	}
	/* Back returns to the list as it was ( filters, search, page ). */
	var back = document.querySelector( '.ecodv2-header .ecdv2-header-back' );
	if ( back && 'string' === typeof data.back && 0 === data.back.indexOf( window.location.origin ) && -1 !== data.back.indexOf( 'page=wp-easycart-orders' ) ) {
		back.href = data.back;
	}
}

jQuery( function( $ ) {
	if ( ! document.getElementById( 'ecodv2_wrap' ) ) {
		return;
	}

	/* One toast on this screen: the list's toast ( WP EasyCart PRO and the Packages card use it ) shows here. */
	window.ecv2_toast = function( message, type ) {
		ecodv2_save_toast( message, 'error' === type );
	};

	/* Amounts: format what the older scripts write in place. */
	$( '.ecodv2-amt-raw[id]' ).each( function() {
		var raw = this;
		if ( 'function' === typeof window.MutationObserver ) {
			new MutationObserver( function() { ecodv2_amt_paint( raw ); ecodv2_mirrors_paint(); } ).observe( raw, { childList: true, characterData: true, subtree: true } );
		}
	} );

	/* The payment chip follows the balance. */
	$( document ).on( 'ecodv2_balance_changed', function( e, state ) {
		var chip = document.getElementById( 'ecodv2_pay_chip' );
		var map = { paid: [ 'is-good', 'pay_paid' ], partial: [ 'is-warn', 'pay_partial' ], unpaid: [ 'is-warn', 'pay_unpaid' ], overpaid: [ 'is-info', 'pay_overpaid' ] };
		if ( chip && map[ state ] ) {
			chip.className = 'ecodv2-pay-chip ' + map[ state ][0];
			chip.textContent = ecodv2_t( map[ state ][1], chip.textContent );
		}
		ecodv2_mirrors_paint();
	} );

	/* Address and contact corrections without WP EasyCart PRO's script ( D4 ). */
	if ( 'function' !== typeof window.ec_order_show_hide_edit_billing ) {
		$( '#ec_admin_order_details_billing_info_save' ).on( 'click', function() { ecodv2_free_save_address( 'billing' ); } );
		$( '#ec_admin_order_details_shipping_info_save' ).on( 'click', function() { ecodv2_free_save_address( 'shipping' ); } );
	}
	if ( 'function' !== typeof window.ec_order_show_hide_edit_order_information ) {
		$( '#ec_admin_order_details_save' ).on( 'click', ecodv2_free_save_contact );
	}
	$( '#ecodv2_codes_save' ).on( 'click', ecodv2_codes_save );

	/* A line edit that failed says nothing about the total. */
	$( document ).ajaxError( function( e, xhr, settings ) {
		var d = ( settings && 'string' === typeof settings.data ) ? settings.data : '';
		if ( -1 !== d.indexOf( 'order_detail_line_item' ) || -1 !== d.indexOf( 'action=ec_admin_ajax_edit_order_totals' ) ) {
			ecodv2_line_toast = null;
			clearTimeout( ecodv2_line_toast_timer );
		}
	} );

	/* Status menu: arrow keys move, Escape returns to the pill. Print and ⋯ close on Escape back to their button. */
	$( document ).on( 'keydown', '#ecodv2_status_menu, #ecodv2_print_menu, #ecodv2_header_menu, #ecodv2_toolbar_menu', function( e ) {
		var $items = $( this ).find( 'button:visible, a:visible' ), at = $items.index( document.activeElement );
		if ( 'ArrowDown' === e.key ) {
			e.preventDefault();
			$items.eq( Math.min( $items.length - 1, at + 1 ) ).trigger( 'focus' );
		} else if ( 'ArrowUp' === e.key ) {
			e.preventDefault();
			$items.eq( Math.max( 0, at - 1 ) ).trigger( 'focus' );
		} else if ( 'Escape' === e.key ) {
			var $toggle = $( '[aria-controls="' + this.id + '"]' );
			ecodv2_menu_close();
			$toggle.trigger( 'focus' );
		}
	} );

	/* Dialogs: focus moves in when one opens, stays in while it is open ( Tab wraps ), and goes back when it closes. */
	var layers = { 'ecodv2-drawer-open': 'ecodv2_edit_drawer', 'ecodv2-label-open': 'ecodv2_label_popup', 'ecodv2-line-open': 'ecodv2_line_modal', 'ecodv2-hdrawer-open': 'ecodv2_history_drawer', 'ecodv2-add-open': 'ec_admin_add_new_order_item' };
	var open = {}, stack = [];
	var sync_layers = function() {
		Object.keys( layers ).forEach( function( cls ) {
			var on = document.body.classList.contains( cls ), el = document.getElementById( layers[ cls ] );
			if ( on && ! open[ cls ] ) {
				open[ cls ] = { back: document.activeElement };
				stack.push( cls );
				setTimeout( function() {
					if ( el && ! el.contains( document.activeElement ) ) {
						var list = ecodv2_focusables( el );
						if ( list.length ) {
							list[0].focus();
						}
					}
				}, 280 );
			} else if ( ! on && open[ cls ] ) {
				var back = open[ cls ].back;
				delete open[ cls ];
				stack.splice( stack.indexOf( cls ), 1 );
				if ( back && back.focus && document.body.contains( back ) && ( ! document.activeElement || document.activeElement === document.body || ( el && el.contains( document.activeElement ) ) ) ) {
					try { back.focus(); } catch ( err ) {}
				}
			}
		} );
	};
	if ( 'function' === typeof window.MutationObserver ) {
		new MutationObserver( sync_layers ).observe( document.body, { attributes: true, attributeFilter: [ 'class' ] } );
	}
	$( document ).on( 'keydown', function( e ) {
		var confirm_box = document.getElementById( 'ecodv2_confirm' );
		if ( 'Tab' !== e.key || ! stack.length || ( confirm_box && ! confirm_box.hidden ) ) {
			return;
		}
		var el = document.getElementById( layers[ stack[ stack.length - 1 ] ] );
		if ( el ) {
			ecodv2_trap_tab( el, e );
		}
	} );

	window.addEventListener( 'beforeunload', function( e ) {
		if ( ecodv2_unsaved() ) {
			e.preventDefault();
			e.returnValue = '';
			return '';
		}
	} );
	ecodv2_nav_init();
	ecodv2_sync_same_address();
} );

/* ====================================================================
   6.0.2 — the fast path ( layout B: https://claude.ai/artifact/84saKxSSu3YqMhKLDSMnj5 )
   Ship order in the next step panel, Packed ticks, the figures at the top of
   the Payment card, Find ( Ctrl K ), the keys ( P, E, R, N, ? ), scanning a
   tracking barcode with the camera, and the Ship bar on a phone.
   ==================================================================== */

/* A tracking number as the carriers take it: no spaces or scanner control characters, and a USPS label's routing barcode
   ( 420 + ZIP code before the tracking number ) cut to the tracking number. */
function ecodv2_tracking_clean( value ) {
	var v = String( value || '' ).replace( /[\s\u0000-\u001f]+/g, '' );
	var usps = v.match( /^420\d{5}(?:\d{4})?(9\d{19,21})$/ );
	return usps ? usps[1] : v;
}

/* Ship order: the tracking on the order ( or its one package ), the order marked shipped and, when ticked, the shipped
   email, in one request. The page reloads to show the order shipped and the next order to ship. */
function ecodv2_ship( force ) {
	var $ = jQuery, box = document.getElementById( 'ecodv2_ship' );
	if ( ! box ) {
		return false;
	}
	var $btn = $( '#ecodv2_ship_go' ), $status = $( '#ecodv2_ship_status' ), $input = $( '#ecodv2_ship_tracking' );
	if ( $btn.prop( 'disabled' ) ) {
		return false;
	}
	var carrier = $( '#ecodv2_ship_carrier' ).val() || '', tracking = ecodv2_tracking_clean( $input.val() );
	$input.val( tracking );
	if ( '' === tracking && true !== force ) {
		ecodv2_confirm( {
			title: ecodv2_t( 'ship_no_tracking', 'Ship without a tracking number?' ),
			body: ecodv2_t( 'ship_no_tracking_body', 'The order is marked shipped, and the shipped email goes out without tracking.' ),
			yes: ecodv2_t( 'ship_anyway', 'Ship without tracking' ),
			on_yes: function() { ecodv2_ship( true ); }
		} );
		return false;
	}
	var notify = $( '#ecodv2_ship_email' ).is( ':checked' ) ? 1 : 0, nonce = box.getAttribute( 'data-packages-nonce' ), data;
	if ( ecodv2_packages_stale() || ecodv2_refresh_pending() ) {
		/* The package this panel names was printed before the packages changed: the panel is drawn again ( bug round 6 ), and
		   the tracking number stays in it when it still asks for one. */
		ecodv2_screen_refresh( function( ok ) {
			jQuery( '#ecodv2_ship_status' ).removeClass( 'is-saved' ).addClass( 'is-error' ).text( ok ? ecodv2_t( 'packages_changed_ship', 'The packages changed, so this step was brought up to date. Check it and ship again.' ) : ecodv2_t( 'fulfill_failed', 'The shipment could not be saved. Reload the order and try again.' ) );
		} );
		return false;
	}
	if ( nonce && '' !== tracking ) {
		/* The order's one package keeps its own tracking ( ecv2_order_packages_track_all ). */
		data = {
			action: 'ecv2_order_packages_track_all',
			nonce: nonce,
			order_id: box.getAttribute( 'data-order-id' ),
			rows: JSON.stringify( [ {
				index: parseInt( box.getAttribute( 'data-package-index' ), 10 ) || 0,
				shipment_id: parseInt( box.getAttribute( 'data-shipment-id' ), 10 ) || 0,
				carrier: carrier,
				tracking_number: tracking
			} ] ),
			notify: notify,
			mark_shipped: 1
		};
	} else {
		data = {
			action: 'ecv2_order_screen_fulfill',
			order_id: $( '#order_id' ).val(),
			carrier: carrier,
			tracking_number: tracking,
			mark_shipped: 1,
			notify: notify,
			wp_easycart_nonce: $( '#wp_easycart_order_details_nonce' ).val()
		};
	}
	var failed = function( answer ) {
		ecodv2_btn_busy( $btn[0], false );
		$( '.ecodv2-shipbar-go' ).each( function() {
			ecodv2_btn_busy( this, false );
		} );
		if ( answer && answer.data && 'stale' === answer.data.code ) {
			/* The packages changed on the server since this panel was drawn: draw it again and say so there. */
			ecodv2_screen_refresh( function( ok ) {
				jQuery( '#ecodv2_ship_status' ).removeClass( 'is-saved' ).addClass( 'is-error' ).text( ok ? ecodv2_t( 'packages_changed_ship', 'The packages changed, so this step was brought up to date. Check it and ship again.' ) : answer.data.message );
			} );
			return;
		}
		$status.removeClass( 'is-saved' ).addClass( 'is-error' ).text( ( answer && answer.data && answer.data.message ) ? answer.data.message : ecodv2_t( 'fulfill_failed', 'The shipment could not be saved. Reload the order and try again.' ) );
	};
	/* 6.0.2 bug round 6: Shipping… with a spinner on the button ( and the phone's Ship bar ) until the page reloads. */
	ecodv2_btn_busy( $btn[0], true, ecodv2_t( 'ship_shipping', 'Shipping…' ) );
	$( '.ecodv2-shipbar-go' ).each( function() {
		ecodv2_btn_busy( this, true, ecodv2_t( 'ship_shipping', 'Shipping…' ) );
	} );
	$status.removeClass( 'is-error is-saved' ).text( '' );
	$.ajax( { url: wpeasycart_admin_ajax_object.ajax_url, type: 'post', dataType: 'json', data: data } ).done( function( r ) {
		if ( r && r.success ) {
			$status.removeClass( 'is-error' ).addClass( 'is-saved' ).text( ( r.data && r.data.message ) ? r.data.message : ecodv2_t( 'saved', 'Saved' ) );
			ecodv2_pack_forget();
			ecodv2_leaving = true;
			setTimeout( function() { window.location.reload(); }, 900 );
			return;
		}
		failed( r );
	} ).fail( function( xhr ) {
		failed( ecodv2_json( xhr ) );
	} );
	return false;
}

/* More than one package to ship: the Fulfill window at its tracking step ( its rows drawn again first when the packages just
   changed, bug round 6 ). */
function ecodv2_fulfill_tracking() {
	var open = function() {
		ecodv2_fulfill_open();
		ecodv2_label_step( 2 );
	};
	if ( ecodv2_packages_stale() || ecodv2_refresh_pending() ) {
		ecodv2_screen_refresh( open );
	} else {
		open();
	}
	return false;
}

/* 6.0.2 bug round 14: Stock not taken ( a manual payment order paid before 6.0.2 kept its stock ). what: take ( take it now,
   after a confirm ) or mark ( the store already corrected the stock by hand ). The notice goes once the answer is in place. */
function ecodv2_stock_fix( btn, what ) {
	var $ = jQuery;
	if ( btn && btn.disabled ) {
		return false;
	}
	var send = function() {
		var buttons = $( btn ).closest( '.ecodv2-callout' ).find( 'button' ).get();
		buttons.forEach( function( b ) {
			b.disabled = true;
		} );
		ecodv2_btn_busy( btn, true );
		var failed = function( answer ) {
			ecodv2_btn_busy( btn, false );
			buttons.forEach( function( b ) {
				b.disabled = false;
			} );
			ecodv2_save_toast( ( answer && answer.data && answer.data.message ) ? answer.data.message : ecodv2_t( 'stock_failed', 'The stock could not be changed. Reload the page and try again.' ), true );
		};
		$.ajax( {
			url: wpeasycart_admin_ajax_object.ajax_url,
			type: 'post',
			dataType: 'json',
			data: { action: 'ecv2_order_screen_stock', 'do': 'take' === what ? 'take' : 'mark', order_id: $( '#order_id' ).val(), wp_easycart_nonce: $( '#wp_easycart_order_details_nonce' ).val() }
		} ).done( function( r ) {
			if ( ! r || ! r.success || ! r.data ) {
				failed( r );
				return;
			}
			ecodv2_refresh_apply( r.data );
			ecodv2_save_toast( r.data.message || ecodv2_t( 'saved', 'Saved' ) );
			ecodv2_history_refresh();
		} ).fail( function( xhr ) {
			failed( ecodv2_json( xhr ) );
		} );
	};
	if ( 'take' === what ) {
		ecodv2_confirm( {
			title: ecodv2_t( 'stock_take_title', 'Take this order’s stock now?' ),
			body: ecodv2_t( 'stock_take_body', '' ),
			yes: ecodv2_t( 'stock_take_yes', 'Take stock now' ),
			on_yes: send
		} );
	} else {
		send();
	}
	return false;
}

/* Local pickup collected ( the next step, free from 6.0.2 ): Order Picked Up through the status change. */
function ecodv2_mark_picked_up() {
	ecodv2_confirm( {
		title: ecodv2_t( 'pickup_title', 'Mark this order picked up?' ),
		body: ecodv2_t( 'pickup_body', '' ),
		yes: ecodv2_t( 'pickup_yes', 'Mark picked up' ),
		on_yes: function() { ecodv2_status_apply( '18' ); }
	} );
	return false;
}

/* 6.0.2: the orders list sends an order here to finish something it cannot do in a row: ?ecodv2_open=tracking ( several
   packages to ship, one tracking number each ) or refund ( WP EasyCart PRO's refund, from a Refunded / Cancelled status
   change ). Once, after every script on the page is ready. */
jQuery( function( $ ) {
	var m = /[?&]ecodv2_open=(tracking|refund)(&|$)/.exec( window.location.search );
	if ( ! m || ! document.getElementById( 'ecodv2_wrap' ) ) {
		return;
	}
	setTimeout( function() {
		var pro = window.ecodv2_pro || {};
		if ( 'tracking' === m[1] && 'function' === typeof window.ecodv2_fulfill_open ) {
			ecodv2_fulfill_tracking();
		} else if ( 'refund' === m[1] && 'function' === typeof pro.open_refund && document.getElementById( 'ec_admin_refund_button' ) ) {
			pro.open_refund();
		}
		if ( window.history && window.history.replaceState ) {
			window.history.replaceState( window.history.state, '', window.location.href.replace( /([?&])ecodv2_open=(tracking|refund)(&|$)/, '$1' ).replace( /[?&]$/, '' ) );
		}
	}, 0 );
} );

function ecodv2_edit_packages() {
	var btn = document.querySelector( '#ecpk_order [data-ecpk-edit]' );
	if ( btn ) {
		btn.click();
	} else {
		ecodv2_scroll_to( 'ecpk_order' );
	}
	return false;
}

/* ---------- Packed ticks: saved on the order line ( packed_quantity ), so everyone packing the order sees them. Until the
   6.0.2 database update has run ( data-pack-store="browser" ) they are kept in this browser, per order. ---------- */
function ecodv2_pack_on_order() {
	var wrap = document.getElementById( 'ecodv2_wrap' );
	return !! wrap && 'order' === wrap.getAttribute( 'data-pack-store' );
}

/* One tick saved on the order ( ecv2_order_screen_pack ); the tick goes back when the save fails. */
function ecodv2_pack_save( box ) {
	var $ = jQuery, packed = !! box.checked;
	box.disabled = true;
	$.ajax( {
		url: wpeasycart_admin_ajax_object.ajax_url,
		type: 'post',
		dataType: 'json',
		data: {
			action: 'ecv2_order_screen_pack',
			order_id: $( '#order_id' ).val(),
			orderdetail_id: box.getAttribute( 'data-ecodv2-pack' ),
			packed: packed ? 1 : 0,
			wp_easycart_nonce: $( '#wp_easycart_order_details_nonce' ).val()
		}
	} ).done( function( r ) {
		box.disabled = false;
		if ( ! r || ! r.success ) {
			box.checked = ! packed;
			ecodv2_pack_progress();
			ecodv2_save_toast( ( r && r.data && r.data.message ) ? r.data.message : ecodv2_t( 'pack_failed', 'The packed item could not be saved. Reload the order and try again.' ), true );
		}
	} ).fail( function( xhr ) {
		var answer = ecodv2_json( xhr );
		box.disabled = false;
		box.checked = ! packed;
		ecodv2_pack_progress();
		ecodv2_save_toast( ( answer && answer.data && answer.data.message ) ? answer.data.message : ecodv2_t( 'pack_failed', 'The packed item could not be saved. Reload the order and try again.' ), true );
	} );
}

function ecodv2_pack_key() {
	return 'wpec_packed_' + ( parseInt( ecodv2_screen().order_id, 10 ) || 0 );
}

function ecodv2_pack_read() {
	try {
		var map = JSON.parse( window.localStorage.getItem( ecodv2_pack_key() ) || '{}' );
		return ( map && 'object' === typeof map ) ? map : {};
	} catch ( e ) {
		return {};
	}
}

function ecodv2_pack_forget() {
	try {
		window.localStorage.removeItem( ecodv2_pack_key() );
	} catch ( e ) {}
}

function ecodv2_pack_progress() {
	var boxes = document.querySelectorAll( 'input[data-ecodv2-pack]' ), done = 0, total = boxes.length;
	Array.prototype.forEach.call( boxes, function( box ) {
		if ( box.checked ) {
			++done;
		}
		var line = box.closest( '.ecodv2-line' );
		if ( line ) {
			line.classList.toggle( 'is-packed', box.checked );
		}
	} );
	var count = document.getElementById( 'ecodv2_pack_count' ), bar = document.getElementById( 'ecodv2_pack_bar' ), wrap = document.getElementById( 'ecodv2_pack_progress' );
	if ( count ) {
		count.textContent = ( total > 0 && done >= total ) ? ecodv2_t( 'pack_done', 'All packed' ) : ecodv2_t( 'pack_progress', '%1$d of %2$d packed' ).replace( '%1$d', String( done ) ).replace( '%2$d', String( total ) );
	}
	if ( bar ) {
		bar.style.width = ( total > 0 ? Math.round( done / total * 100 ) : 0 ) + '%';
	}
	if ( wrap ) {
		wrap.classList.toggle( 'is-done', total > 0 && done >= total );
	}
	return { done: done, total: total };
}

function ecodv2_pack_init() {
	var boxes = document.querySelectorAll( 'input[data-ecodv2-pack]' );
	if ( ! boxes.length || ecodv2_pack_on_order() ) {
		/* The ticks the page printed come from the order ( or nothing is left to pack ): no browser copy. */
		ecodv2_pack_forget();
		ecodv2_pack_progress();
		return;
	}
	var map = ecodv2_pack_read();
	Array.prototype.forEach.call( boxes, function( box ) {
		box.checked = !! map[ box.getAttribute( 'data-ecodv2-pack' ) ];
	} );
	ecodv2_pack_progress();
}

/* ---------- Figures at the top of the Payment card follow the rows below ---------- */
function ecodv2_mirrors_paint() {
	Array.prototype.forEach.call( document.querySelectorAll( '[data-ecodv2-mirror]' ), function( el ) {
		var raw = document.getElementById( el.getAttribute( 'data-ecodv2-mirror' ) );
		if ( raw ) {
			el.textContent = ecodv2_money( Math.abs( parseFloat( String( raw.textContent ).replace( /[^0-9.\-]/g, '' ) ) || 0 ) );
		}
	} );
	Array.prototype.forEach.call( document.querySelectorAll( '[data-ecodv2-mirror-text]' ), function( el ) {
		var src = document.getElementById( el.getAttribute( 'data-ecodv2-mirror-text' ) );
		if ( src && '' !== jQuery.trim( src.textContent ) ) {
			el.textContent = jQuery.trim( src.textContent );
		}
	} );
}

/* ---------- Keys shown as the platform writes them ( ⌘ on a Mac ) ---------- */
function ecodv2_is_mac() {
	var p = ( window.navigator.userAgentData && window.navigator.userAgentData.platform ) || window.navigator.platform || '';
	return /mac|iphone|ipad/i.test( p );
}

/* ---------- The next step panel ( after the page loads and after a status change redraws it ) ---------- */
function ecodv2_next_init() {
	var $ = jQuery;
	if ( ecodv2_is_mac() ) {
		$( '[data-ecodv2-mod]' ).text( '⌘ K' );
	}
	/* The camera button only where the browser can read barcodes ( a USB or Bluetooth scanner types into the field anywhere ). */
	var scan = document.getElementById( 'ecodv2_ship_scan' );
	if ( scan ) {
		scan.hidden = ! ecodv2_scan_supported();
	}
	/* A phone: Ship order and Next stay at the bottom of the screen. */
	$( '.ecodv2-shipbar' ).remove();
	var go = document.getElementById( 'ecodv2_ship_go' );
	if ( go ) {
		var bar = document.createElement( 'div' ), ship = document.createElement( 'button' ), next = document.getElementById( 'ecodv2_queue_next' );
		bar.className = 'ecodv2-shipbar';
		ship.type = 'button';
		ship.className = 'ecv2-btn ecv2-btn-primary ecodv2-shipbar-go';
		ship.textContent = go.getAttribute( 'data-ship-many' ) ? jQuery.trim( go.textContent ) : ecodv2_t( 'act_ship', 'Ship order' );
		ship.onclick = function() {
			go.click();
			return false;
		};
		bar.appendChild( ship );
		if ( next ) {
			var link = document.createElement( 'a' );
			link.className = 'ecv2-btn ecodv2-shipbar-next';
			link.href = next.href;
			link.textContent = ecodv2_t( 'act_next', 'Next order' );
			link.setAttribute( 'aria-label', ecodv2_t( 'act_next_ship', 'Next order to ship' ) );
			bar.appendChild( link );
		}
		document.body.appendChild( bar );
		document.body.classList.add( 'ecodv2-has-shipbar' );
	} else {
		document.body.classList.remove( 'ecodv2-has-shipbar' );
	}
}

/* ---------- Scan a tracking barcode with the camera ( BarcodeDetector: Chrome, Edge, Android ) ---------- */
function ecodv2_scan_supported() {
	return !! ( window.BarcodeDetector && window.isSecureContext && window.navigator.mediaDevices && window.navigator.mediaDevices.getUserMedia );
}

function ecodv2_scan_open() {
	var $ = jQuery;
	if ( ! ecodv2_scan_supported() || document.getElementById( 'ecodv2_scan' ) ) {
		return false;
	}
	var opener = document.activeElement, stream = null, timer = null, closed = false;
	var $box = $( '<div class="ecodv2-scan" id="ecodv2_scan" role="dialog" aria-modal="true"></div>' ).attr( 'aria-label', ecodv2_t( 'scan_title', 'Scan a tracking barcode' ) );
	var video = document.createElement( 'video' );
	video.setAttribute( 'playsinline', '' );
	video.muted = true;
	var $close = $( '<button type="button" class="ecodv2-scan-close"></button>' ).text( ecodv2_t( 'close', 'Close' ) );
	var $hint = $( '<p class="ecodv2-scan-hint" role="status" aria-live="polite"></p>' ).text( ecodv2_t( 'scan_hint', 'Hold the label’s barcode inside the frame.' ) );
	$box.append( $( '<div class="ecodv2-scan-view"></div>' ).append( video ).append( '<span class="ecodv2-scan-frame" aria-hidden="true"></span>' ) ).append( $hint ).append( $close );
	$( 'body' ).append( $box );
	var close = function() {
		closed = true;
		clearTimeout( timer );
		if ( stream ) {
			stream.getTracks().forEach( function( track ) { track.stop(); } );
		}
		$( document ).off( 'keydown.ecodv2scan' );
		$box.remove();
		if ( opener && opener.focus && document.body.contains( opener ) ) {
			opener.focus();
		}
	};
	$close.on( 'click', close );
	$( document ).on( 'keydown.ecodv2scan', function( e ) {
		if ( 'Escape' === e.key ) {
			e.preventDefault();
			e.stopImmediatePropagation();
			close();
		} else if ( 'Tab' === e.key ) {
			e.preventDefault();
			$close.trigger( 'focus' );
		}
	} );
	$close.trigger( 'focus' );
	var wanted = [ 'code_128', 'code_39', 'code_93', 'codabar', 'ean_13', 'itf', 'pdf417', 'qr_code', 'data_matrix', 'upc_a', 'aztec' ];
	var formats = ( window.BarcodeDetector.getSupportedFormats ? window.BarcodeDetector.getSupportedFormats() : Promise.resolve( wanted ) );
	formats.then( function( supported ) {
		var use = wanted.filter( function( f ) { return -1 !== supported.indexOf( f ); } );
		var detector = new window.BarcodeDetector( use.length ? { formats: use } : undefined );
		return window.navigator.mediaDevices.getUserMedia( { video: { facingMode: { ideal: 'environment' } }, audio: false } ).then( function( media ) {
			if ( closed ) {
				media.getTracks().forEach( function( track ) { track.stop(); } );
				return;
			}
			stream = media;
			video.srcObject = media;
			var played = video.play();
			var look = function() {
				if ( closed ) {
					return;
				}
				detector.detect( video ).then( function( codes ) {
					var code = ( codes && codes.length ) ? ecodv2_tracking_clean( codes[0].rawValue ) : '';
					if ( code ) {
						$( '#ecodv2_ship_tracking' ).val( code );
						close();
						$( '#ecodv2_ship_go' ).trigger( 'focus' );
						return;
					}
					timer = setTimeout( look, 250 );
				} ).catch( function() {
					timer = setTimeout( look, 400 );
				} );
			};
			( played && played.then ? played : Promise.resolve() ).then( look );
		} );
	} ).catch( function() {
		$hint.addClass( 'is-error' ).text( ecodv2_t( 'scan_denied', 'The camera could not be opened. Allow the camera for this site, or type the tracking number.' ) );
	} );
	return false;
}

/* ---------- Find ( Ctrl K ): orders by number, name or email, and everything this page does ---------- */
var ecodv2_cmdk_state = null;

function ecodv2_cmdk_actions() {
	var list = [], $ = jQuery;
	var add = function( label, words, run, key ) {
		if ( label ) {
			list.push( { label: label, words: ( label + ' ' + ( words || '' ) ).toLowerCase(), run: run, key: key || '' } );
		}
	};
	var click = function( id ) {
		return function() {
			var el = document.getElementById( id );
			if ( el ) {
				el.click();
			}
		};
	};
	if ( document.getElementById( 'ecodv2_ship' ) ) {
		add( ecodv2_t( 'act_ship', 'Ship order' ), 'ship send tracking fulfill', function() {
			var input = document.getElementById( 'ecodv2_ship_tracking' );
			if ( input ) {
				input.scrollIntoView( { block: 'center', behavior: 'smooth' } );
				input.focus();
			}
		} );
	}
	var next = document.getElementById( 'ecodv2_next_btn' );
	if ( next ) {
		add( $.trim( next.textContent ), 'next step', click( 'ecodv2_next_btn' ) );
	} else if ( document.getElementById( 'ecodv2_ship_go' ) && document.getElementById( 'ecodv2_ship_go' ).getAttribute( 'data-ship-many' ) ) {
		add( $.trim( document.getElementById( 'ecodv2_ship_go' ).textContent ), 'ship tracking packages', click( 'ecodv2_ship_go' ) );
	}
	if ( document.getElementById( 'ecodv2_label_popup' ) ) {
		add( ecodv2_t( 'act_fulfill', 'Fulfill: make a label and add tracking' ), 'label shipping fulfill carrier', function() { ecodv2_fulfill_open(); } );
	}
	var prints = document.querySelectorAll( '#ecodv2_print_menu a[href]' );
	Array.prototype.forEach.call( prints, function( a, i ) {
		var label = 0 === i ? ecodv2_t( 'act_slip', 'Print the packing slip' ) : ( 1 === i ? ecodv2_t( 'act_receipt', 'Print the receipt' ) : $.trim( a.textContent ) );
		add( label, 'print pdf document', function() { window.open( a.href, '_blank', 'noopener' ); }, 0 === i ? 'P' : '' );
	} );
	if ( document.getElementById( 'ecodv2_send_email_link' ) ) {
		add( ecodv2_t( 'act_email', 'Email the customer' ), 'email send receipt message invoice', click( 'ecodv2_send_email_link' ), 'E' );
	}
	if ( document.getElementById( 'ec_admin_refund_button' ) ) {
		add( ecodv2_t( 'act_refund', 'Refund' ), 'refund money back return', click( 'ec_admin_refund_button' ), 'R' );
	}
	if ( document.getElementById( 'ec_admin_order_total_edit' ) ) {
		add( ecodv2_t( 'act_totals', 'Edit totals' ), 'totals tax shipping discount', click( 'ec_admin_order_total_edit' ) );
	}
	if ( document.getElementById( 'ec_admin_order_details_edit' ) ) {
		add( ecodv2_t( 'act_edit', 'Edit the customer and addresses' ), 'address customer email phone edit', click( 'ec_admin_order_details_edit' ) );
	}
	if ( document.getElementById( 'ecodv2_pin_input' ) ) {
		add( ecodv2_t( 'act_pin', 'Pin a note for the team' ), 'note pin team', function() { ecodv2_scroll_to( 'ecodv2_notes_card' ); ecodv2_pin_edit(); } );
	}
	if ( document.getElementById( 'order_customer_notes' ) ) {
		add( ecodv2_t( 'act_cnote', 'Write a note for the customer' ), 'note customer receipt', function() { ecodv2_scroll_to( 'ecodv2_notes_card' ); ecodv2_open_cnotes_popover(); } );
	}
	var copy = document.querySelector( '.ecodv2-o-shipto .ecdv2-card-header .ecodv2-copy-link[onclick*="shipping"]' );
	if ( copy ) {
		add( ecodv2_t( 'act_copy_address', 'Copy the shipping address' ), 'copy address ship to', function() { copy.click(); } );
	}
	if ( document.getElementById( 'ecodv2_history_drawer' ) ) {
		add( ecodv2_t( 'act_activity', 'Show all activity' ), 'history log timeline activity', function() { ecodv2_open_history_drawer(); } );
	}
	Array.prototype.forEach.call( document.querySelectorAll( '#ecodv2_status_menu .ecodv2-status-item:not(.is-current)' ), function( item ) {
		var name = $.trim( $( item ).find( '.ecodv2-status-item-label' ).text() );
		add( ecodv2_t( 'act_status', 'Set status: %s' ).replace( '%s', name ), 'status', function() { item.click(); } );
	} );
	if ( document.getElementById( 'ecodv2_queue_next' ) ) {
		add( ecodv2_t( 'act_next_ship', 'Next order to ship' ), 'queue next ship', click( 'ecodv2_queue_next' ), 'N' );
	}
	if ( document.getElementById( 'ecodv2_nav_prev' ) ) {
		add( ecodv2_t( 'act_prev', 'Previous order' ), 'previous back', click( 'ecodv2_nav_prev' ), 'K' );
	}
	if ( document.getElementById( 'ecodv2_nav_next' ) ) {
		add( ecodv2_t( 'act_next', 'Next order' ), 'next forward', click( 'ecodv2_nav_next' ), 'J' );
	}
	add( ecodv2_t( 'act_keys', 'Keyboard shortcuts' ), 'keys help shortcuts keyboard', function() { ecodv2_keys_open(); }, '?' );
	return list;
}

function ecodv2_cmdk_open() {
	var $ = jQuery;
	if ( document.getElementById( 'ecodv2_cmdk' ) ) {
		$( '#ecodv2_cmdk_input' ).trigger( 'focus' );
		return false;
	}
	ecodv2_menu_close();
	var st = ecodv2_cmdk_state = { opener: document.activeElement, actions: ecodv2_cmdk_actions(), items: [], active: 0, orders: [], searching: false, query: '', token: 0, timer: null };
	var $back = $( '<div class="ecodv2-cmdk-backdrop" id="ecodv2_cmdk_backdrop"></div>' );
	var $box = $( '<div class="ecodv2-cmdk" id="ecodv2_cmdk" role="dialog" aria-modal="true"></div>' ).attr( 'aria-label', ecodv2_t( 'find_label', 'Find an order or run an action' ) );
	var $input = $( '<input type="text" id="ecodv2_cmdk_input" class="ecodv2-cmdk-input" role="combobox" aria-expanded="true" aria-controls="ecodv2_cmdk_list" aria-autocomplete="list" autocomplete="off" spellcheck="false" />' ).attr( 'placeholder', ecodv2_t( 'find_placeholder', 'Find an order, or type what to do…' ) ).attr( 'aria-label', ecodv2_t( 'find_label', 'Find an order or run an action' ) );
	$box.append( $( '<div class="ecodv2-cmdk-search"><span class="dashicons dashicons-search" aria-hidden="true"></span></div>' ).append( $input ) );
	$box.append( '<div class="ecodv2-cmdk-list" id="ecodv2_cmdk_list" role="listbox"></div>' );
	$box.append( $( '<div class="ecodv2-cmdk-foot"></div>' ).text( ecodv2_t( 'find_hint', '↑ ↓ to move · Enter to open · Esc to close' ) ) );
	$( 'body' ).append( $back ).append( $box );
	$back.on( 'click', ecodv2_cmdk_close );
	$input.on( 'input', function() {
		st.query = $.trim( this.value );
		st.active = 0;
		ecodv2_cmdk_search();
		ecodv2_cmdk_render();
	} );
	$input.on( 'keydown', function( e ) {
		if ( 'ArrowDown' === e.key || 'ArrowUp' === e.key ) {
			e.preventDefault();
			if ( st.items.length ) {
				st.active = ( st.active + ( 'ArrowDown' === e.key ? 1 : -1 ) + st.items.length ) % st.items.length;
				ecodv2_cmdk_render( true );
			}
		} else if ( 'Enter' === e.key ) {
			e.preventDefault();
			ecodv2_cmdk_run( st.active );
		} else if ( 'Escape' === e.key ) {
			e.preventDefault();
			e.stopImmediatePropagation();
			ecodv2_cmdk_close();
		} else if ( 'Tab' === e.key ) {
			e.preventDefault();
		}
	} );
	$box.on( 'mousedown', '.ecodv2-cmdk-item', function( e ) {
		e.preventDefault();
		ecodv2_cmdk_run( parseInt( $( this ).attr( 'data-index' ), 10 ) || 0 );
	} );
	$box.on( 'mousemove', '.ecodv2-cmdk-item', function() {
		var at = parseInt( $( this ).attr( 'data-index' ), 10 ) || 0;
		if ( at !== st.active ) {
			st.active = at;
			ecodv2_cmdk_render( true );
		}
	} );
	ecodv2_cmdk_render();
	$input.trigger( 'focus' );
	return false;
}

function ecodv2_cmdk_close() {
	var st = ecodv2_cmdk_state;
	jQuery( document ).off( 'keydown.ecodv2keyshelp' );
	jQuery( '#ecodv2_cmdk, #ecodv2_cmdk_backdrop' ).remove();
	if ( st ) {
		clearTimeout( st.timer );
		ecodv2_cmdk_state = null;
		if ( st.opener && st.opener.focus && document.body.contains( st.opener ) && st.opener !== document.body ) {
			try { st.opener.focus(); } catch ( e ) {}
		}
	}
	return false;
}

function ecodv2_cmdk_run( at ) {
	var st = ecodv2_cmdk_state;
	if ( ! st || ! st.items[ at ] ) {
		return;
	}
	var item = st.items[ at ];
	ecodv2_cmdk_close();
	if ( item.url ) {
		window.location.href = item.url;
	} else if ( item.run ) {
		item.run();
	}
}

/* Orders for what was typed ( ecv2_order_screen_find ), a moment after typing stops. */
function ecodv2_cmdk_search() {
	var st = ecodv2_cmdk_state, $ = jQuery;
	if ( ! st ) {
		return;
	}
	clearTimeout( st.timer );
	st.orders = [];
	var q = st.query.replace( /^#/, '' );
	if ( q.length < 2 && ! /^\d+$/.test( q ) ) {
		st.searching = false;
		return;
	}
	st.searching = true;
	var token = ++st.token;
	st.timer = setTimeout( function() {
		$.ajax( {
			url: wpeasycart_admin_ajax_object.ajax_url,
			type: 'post',
			dataType: 'json',
			data: { action: 'ecv2_order_screen_find', q: q, wp_easycart_nonce: $( '#wp_easycart_order_details_nonce' ).val() }
		} ).always( function( r ) {
			if ( ! ecodv2_cmdk_state || token !== ecodv2_cmdk_state.token ) {
				return;
			}
			ecodv2_cmdk_state.searching = false;
			ecodv2_cmdk_state.orders = ( r && r.success && r.data && r.data.orders ) ? r.data.orders : [];
			ecodv2_cmdk_render();
		} );
	}, 200 );
}

function ecodv2_cmdk_render( keep ) {
	var st = ecodv2_cmdk_state, $ = jQuery, $list = $( '#ecodv2_cmdk_list' );
	if ( ! st || ! $list.length ) {
		return;
	}
	var q = st.query.toLowerCase(), words = q.replace( /^#/, '' ).split( /\s+/ ).filter( Boolean ), current = parseInt( ecodv2_screen().order_id, 10 ) || 0;
	var actions = st.actions.filter( function( a ) {
		return words.every( function( w ) { return -1 !== a.words.indexOf( w ); } );
	} );
	if ( ! q ) {
		actions = actions.slice( 0, 9 );
	}
	var items = [];
	var html = '';
	var group = function( title, rows ) {
		if ( ! rows.length ) {
			return;
		}
		html += '<div class="ecodv2-cmdk-group" role="presentation">' + ecodv2_esc( title ) + '</div>';
		rows.forEach( function( row ) {
			var at = items.length;
			items.push( row );
			html += '<div class="ecodv2-cmdk-item' + ( at === st.active ? ' is-active' : '' ) + '" id="ecodv2_cmdk_opt_' + at + '" role="option" data-index="' + at + '" aria-selected="' + ( at === st.active ? 'true' : 'false' ) + '">'
				+ '<span class="ecodv2-cmdk-main">' + row.html + '</span>'
				+ ( row.key ? '<kbd class="ecodv2-kbd">' + ecodv2_esc( row.key ) + '</kbd>' : '' )
				+ '</div>';
		} );
	};
	var orders = [];
	var digits = st.query.replace( /^#/, '' );
	if ( /^\d+$/.test( digits ) && parseInt( digits, 10 ) !== current ) {
		orders.push( { html: '<span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>' + ecodv2_esc( ecodv2_t( 'find_open', 'Open order #%s' ).replace( '%s', digits ) ), url: 'admin.php?page=wp-easycart-orders&subpage=orders&order_id=' + parseInt( digits, 10 ) + '&ec_admin_form_action=edit' } );
	}
	st.orders.forEach( function( o ) {
		if ( parseInt( o.id, 10 ) === current || ( orders.length && orders[0].url && -1 !== orders[0].url.indexOf( 'order_id=' + o.id + '&' ) ) ) {
			return;
		}
		orders.push( {
			html: '<span class="ecodv2-cmdk-order"><b>#' + ecodv2_esc( o.id ) + '</b> ' + ecodv2_esc( o.name || o.email ) + '</span>'
				+ '<span class="ecodv2-cmdk-meta">' + ( o.color ? '<span class="ecodv2-status-dot" style="background:' + ecodv2_esc( o.color ) + ';"></span>' : '' ) + ecodv2_esc( [ o.status, o.total, o.date ].filter( Boolean ).join( ' · ' ) ) + '</span>',
			url: o.url
		} );
	} );
	group( ecodv2_t( 'find_orders', 'Orders' ), orders.slice( 0, 8 ) );
	group( ecodv2_t( 'find_actions', 'This order' ), actions.map( function( a ) {
		return { html: ecodv2_esc( a.label ), run: a.run, key: a.key };
	} ) );
	if ( st.searching ) {
		html += '<div class="ecodv2-cmdk-note" role="presentation">' + ecodv2_esc( ecodv2_t( 'find_searching', 'Searching…' ) ) + '</div>';
	} else if ( ! items.length ) {
		html += '<div class="ecodv2-cmdk-note" role="presentation">' + ecodv2_esc( ecodv2_t( 'find_none', 'Nothing matches.' ) ) + '</div>';
	}
	st.items = items;
	if ( st.active >= items.length ) {
		st.active = Math.max( 0, items.length - 1 );
	}
	$list.html( html );
	$( '#ecodv2_cmdk_input' ).attr( 'aria-activedescendant', items.length ? 'ecodv2_cmdk_opt_' + st.active : null );
	if ( keep ) {
		var el = document.getElementById( 'ecodv2_cmdk_opt_' + st.active );
		if ( el && el.scrollIntoView ) {
			el.scrollIntoView( { block: 'nearest' } );
		}
	}
}

/* ---------- The keys ( ? ) ---------- */
function ecodv2_keys_open() {
	var $ = jQuery;
	if ( document.getElementById( 'ecodv2_cmdk' ) ) {
		ecodv2_cmdk_close();
	}
	var opener = document.activeElement, mod = ecodv2_is_mac() ? '⌘ K' : 'Ctrl K';
	var rows = [
		[ mod, ecodv2_t( 'key_find', 'Find an order or run an action' ) ],
		[ ecodv2_t( 'key_enter', 'Enter' ), ecodv2_t( 'key_ship', 'Ship order ( in the tracking number )' ) ],
		[ 'P', ecodv2_t( 'key_print', 'Print' ) ],
		[ 'E', ecodv2_t( 'key_email', 'Email the customer' ) ],
		[ 'R', ecodv2_t( 'key_refund', 'Refund' ) ],
		[ 'N', ecodv2_t( 'key_next_ship', 'Next order to ship' ) ],
		[ 'K / J', ecodv2_t( 'key_prev_next', 'Previous / next order' ) ],
		[ '?', ecodv2_t( 'key_help', 'These shortcuts' ) ]
	];
	var $back = $( '<div class="ecodv2-cmdk-backdrop" id="ecodv2_cmdk_backdrop"></div>' );
	var $box = $( '<div class="ecodv2-cmdk is-keys" id="ecodv2_cmdk" role="dialog" aria-modal="true" aria-labelledby="ecodv2_keys_title"></div>' );
	var $close = $( '<button type="button" class="ecodv2-drawer-x"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>' ).attr( 'aria-label', ecodv2_t( 'close', 'Close' ) );
	var $list = $( '<dl class="ecodv2-keys"></dl>' );
	rows.forEach( function( row ) {
		$list.append( $( '<dt></dt>' ).append( $( '<kbd class="ecodv2-kbd"></kbd>' ).text( row[0] ) ) ).append( $( '<dd></dd>' ).text( row[1] ) );
	} );
	$box.append( $( '<div class="ecodv2-keys-head"></div>' ).append( $( '<h3 id="ecodv2_keys_title"></h3>' ).text( ecodv2_t( 'keys_title', 'Keyboard shortcuts' ) ) ).append( $close ) ).append( $list );
	$( 'body' ).append( $back ).append( $box );
	var close = function() {
		$( document ).off( 'keydown.ecodv2keyshelp' );
		$box.remove();
		$back.remove();
		if ( opener && opener.focus && document.body.contains( opener ) && opener !== document.body ) {
			try { opener.focus(); } catch ( e ) {}
		}
	};
	$close.on( 'click', close );
	$back.on( 'click', close );
	$( document ).on( 'keydown.ecodv2keyshelp', function( e ) {
		if ( 'Escape' === e.key || '?' === e.key ) {
			e.preventDefault();
			e.stopImmediatePropagation();
			close();
		} else if ( 'Tab' === e.key ) {
			e.preventDefault();
			$close.trigger( 'focus' );
		}
	} );
	$close.trigger( 'focus' );
	return false;
}

jQuery( function( $ ) {
	if ( ! document.getElementById( 'ecodv2_wrap' ) ) {
		return;
	}
	ecodv2_next_init();
	ecodv2_pack_init();
	ecodv2_mirrors_paint();

	$( document ).on( 'change', 'input[data-ecodv2-pack]', function() {
		if ( ecodv2_pack_on_order() ) {
			ecodv2_pack_save( this );
		} else {
			var map = ecodv2_pack_read();
			if ( this.checked ) {
				map[ this.getAttribute( 'data-ecodv2-pack' ) ] = 1;
			} else {
				delete map[ this.getAttribute( 'data-ecodv2-pack' ) ];
			}
			try {
				window.localStorage.setItem( ecodv2_pack_key(), JSON.stringify( map ) );
			} catch ( e ) {}
		}
		var p = ecodv2_pack_progress();
		/* Everything packed: on to the tracking number. */
		if ( this.checked && p.total > 0 && p.done >= p.total ) {
			var input = document.getElementById( 'ecodv2_ship_tracking' );
			if ( input ) {
				input.scrollIntoView( { block: 'center', behavior: 'smooth' } );
				try { input.focus( { preventScroll: true } ); } catch ( err ) { input.focus(); }
			}
		}
	} );

	$( document ).on( 'keydown', '#ecodv2_ship_tracking', function( e ) {
		if ( 'Enter' === e.key ) {
			e.preventDefault();
			ecodv2_ship();
		}
	} );
	$( document ).on( 'click', '#ecodv2_ship_scan', function( e ) {
		e.preventDefault();
		ecodv2_scan_open();
	} );

	/* The keys: Ctrl / ⌘ K anywhere; P, E, R, N and ? while nothing is being typed and nothing is open. */
	$( document ).on( 'keydown', function( e ) {
		if ( ( e.ctrlKey || e.metaKey ) && ! e.altKey && ! e.shiftKey && ( 'k' === e.key || 'K' === e.key ) ) {
			if ( document.getElementById( 'ecodv2_cmdk' ) ) {
				e.preventDefault();
				ecodv2_cmdk_close();
				return;
			}
			if ( ecodv2_layer_open() ) {
				return;
			}
			e.preventDefault();
			ecodv2_cmdk_open();
			return;
		}
		var tag = ( e.target && e.target.tagName ) ? e.target.tagName.toLowerCase() : '';
		if ( 'input' === tag || 'textarea' === tag || 'select' === tag || ( e.target && e.target.isContentEditable ) || e.ctrlKey || e.metaKey || e.altKey || ecodv2_layer_open() ) {
			return;
		}
		var key = e.key, el = null;
		if ( 'p' === key || 'P' === key ) {
			el = document.getElementById( 'ecodv2_print_btn' );
			if ( el ) {
				e.preventDefault();
				el.click();
				setTimeout( function() { $( '#ecodv2_print_menu a:visible' ).first().trigger( 'focus' ); }, 30 );
			}
		} else if ( 'e' === key || 'E' === key ) {
			el = document.getElementById( 'ecodv2_send_email_link' );
		} else if ( 'r' === key || 'R' === key ) {
			el = document.getElementById( 'ec_admin_refund_button' );
		} else if ( 'n' === key || 'N' === key ) {
			el = document.getElementById( 'ecodv2_queue_next' );
		} else if ( '?' === key ) {
			e.preventDefault();
			ecodv2_keys_open();
			return;
		}
		if ( el && 'p' !== key.toLowerCase() ) {
			e.preventDefault();
			el.click();
		}
	} );
} );
