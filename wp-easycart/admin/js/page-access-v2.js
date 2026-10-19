/*
 * WP EasyCart Limit Access ( 6.0.5 ): the editor box's live checks and Users › Limit Access ( drawer, on / off, bulk ).
 * Fields: wp_easycart_admin_page_access::print_fields(); data: window.wpecPageAccess ( ajax, nonce, home, text ).
 */
( function () {
	'use strict';

	var cfg = window.wpecPageAccess || {};
	var text = cfg.text || {};

	function t( key ) {
		return text[ key ] || '';
	}

	function host( url ) {
		var m = /^https?:\/\/([^\/?#:]+)/i.exec( url || '' );
		return m ? m[ 1 ].toLowerCase().replace( /^www\./, '' ) : '';
	}

	function pathOf( url ) {
		var m = /^(?:https?:\/\/[^\/?#]+)?(\/[^?#]*)?(\?[^#]*)?/i.exec( url || '' );
		var path = ( m && m[ 1 ] ) ? m[ 1 ].replace( /\/+$/, '' ) : '';
		return ( path || '/' ) + ( m && m[ 2 ] ? m[ 2 ] : '' );
	}

	/* ------------------------------------------------------------------ The fields ( box and drawer ) */

	function chosenPage( dest ) {
		var input = dest.querySelector( '.wpec-access-page' );
		var chip = dest.querySelector( '.wpec-access-dest-page .wpec-pick-chip' );
		return {
			id: input ? parseInt( input.value, 10 ) || 0 : 0,
			limited: !! ( chip && '1' === chip.getAttribute( 'data-limited' ) )
		};
	}

	function checkDest( root, dest, ctx ) {
		var which = dest.getAttribute( 'data-which' );
		var to = dest.querySelector( '.wpec-access-to' ).value;
		if ( 'page' === to ) {
			var page = chosenPage( dest );
			if ( ! page.id ) {
				return [ 'bad', t( 'noPage' ) ];
			}
			if ( page.id === ctx.post ) {
				return [ 'bad', t( 'self' ) ];
			}
			if ( page.limited ) {
				return 'signed_out' === which ? [ 'bad', t( 'limitedOut' ) ] : [ 'warn', t( 'limited' ) ];
			}
		} else if ( 'url' === to ) {
			var url = ( dest.querySelector( '.wpec-access-url' ).value || '' ).trim();
			if ( ! /^https?:\/\/[^\/\s]+/i.test( url ) && ! /^\/(?!\/)/.test( url ) ) {
				return [ 'bad', t( 'noAddress' ) ];
			}
			var external = /^https?:/i.test( url ) && host( url ) !== host( cfg.home );
			if ( external && ! ctx.externalOk && ! ctx.legacy ) {
				return [ 'bad', t( 'external' ) ];
			}
			if ( ! external && ctx.postUrl && pathOf( url ) === pathOf( ctx.postUrl ) ) {
				return [ 'bad', t( 'self' ) ];
			}
		} else if ( 'product' === to && ! ctx.products ) {
			return [ 'warn', t( 'noProduct' ) ];
		}
		return null;
	}

	function update( root ) {
		var on = root.querySelector( '.wpec-access-on' );
		var body = root.querySelector( '.wpec-access-body' );
		var summary = root.querySelector( '.wpec-access-summary' );
		var isOn = !! ( on && on.checked );
		if ( body ) {
			body.hidden = ! isOn;
		}
		var products = root.querySelectorAll( '.wpec-pick[id$="_products"] .wpec-pick-chip' ).length;
		var users = root.querySelectorAll( '.wpec-pick[id$="_users"] .wpec-pick-chip' ).length;
		var roles = root.querySelectorAll( '.wpec-access-role:checked' ).length;
		var signedIn = root.querySelector( '.wpec-access-signed-in' );
		var restricted = ( signedIn && signedIn.checked ) || products || users || roles;
		var external = root.querySelector( '.wpec-access-external-ok' );
		var ctx = {
			post: parseInt( root.getAttribute( 'data-post' ), 10 ) || 0,
			postUrl: root.getAttribute( 'data-post-url' ) || '',
			legacy: '1' === root.getAttribute( 'data-legacy' ),
			externalOk: !! ( external && external.checked ),
			products: products
		};
		var level = 'ok';
		var usesUrl = false;
		root.querySelectorAll( '.wpec-access-dest' ).forEach( function ( dest ) {
			var to = dest.querySelector( '.wpec-access-to' ).value;
			usesUrl = usesUrl || 'url' === to;
			dest.querySelectorAll( '[data-for]' ).forEach( function ( part ) {
				part.hidden = part.getAttribute( 'data-for' ) !== to;
			} );
			var line = dest.querySelector( '.wpec-access-check-line' );
			var found = isOn ? checkDest( root, dest, ctx ) : null;
			if ( line ) {
				line.hidden = ! found;
				line.className = 'wpec-access-check-line' + ( found ? ' is-' + found[ 0 ] : '' );
				line.textContent = found ? found[ 1 ] : '';
			}
			if ( found ) {
				level = ( 'bad' === found[ 0 ] || 'bad' === level ) ? 'bad' : 'warn';
			}
		} );
		var externalRow = root.querySelector( '.wpec-access-external' );
		if ( externalRow ) {
			externalRow.hidden = ! usesUrl && ! ctx.externalOk;
		}
		/* Any one / all decides something only once two kinds of choice are made. */
		var kinds = ( signedIn && signedIn.checked ? 1 : 0 ) + ( products ? 1 : 0 ) + ( roles ? 1 : 0 ) + ( users ? 1 : 0 );
		var all = root.querySelector( '.wpec-access-match input[value="all"]' );
		var allChecked = !! ( all && all.checked );
		var match = root.querySelector( '.wpec-access-match' );
		if ( match ) {
			match.hidden = kinds < 2;
			var hint = match.querySelector( '.wpec-access-match-hint' );
			if ( hint ) {
				hint.textContent = match.getAttribute( allChecked ? 'data-hint-all' : 'data-hint-any' ) || '';
			}
		}
		if ( ! summary ) {
			return level;
		}
		var title = '';
		var lines = [];
		var tone = level;
		if ( ! isOn ) {
			tone = 'off';
			title = t( 'everyone' );
		} else if ( ! restricted ) {
			tone = 'bad' === level ? 'bad' : 'warn';
			level = tone;
			title = t( 'chooseWho' );
			lines.push( [ '', t( 'nobody' ) ] );
		} else {
			title = t( 'membersOnly' );
			lines.push( [ t( 'rowWho' ), whoText( root, allChecked ) ] );
			root.querySelectorAll( '.wpec-access-dest' ).forEach( function ( dest ) {
				var key = { signed_out: 'rowSignedOut', no_access: 'rowNoAccess', members: 'rowMembers' }[ dest.getAttribute( 'data-which' ) ];
				if ( key ) {
					lines.push( [ t( key ), whereText( dest ) ] );
				}
			} );
			if ( 'ok' !== level ) {
				lines.push( [ '', t( 'fixBelow' ) ] );
			}
		}
		summary.className = 'wpec-access-summary is-' + tone;
		drawStatus( summary, title, lines );
		return level;
	}

	/* The status card's words: wp_easycart_page_access::who_text() / where_text(), from the fields as they are now. */
	function chipText( chip ) {
		var span = chip ? chip.querySelector( 'span' ) : null;
		return span ? span.textContent.trim() : '';
	}

	function whoText( root, all ) {
		var parts = [];
		var signedIn = root.querySelector( '.wpec-access-signed-in' );
		if ( signedIn && signedIn.checked ) {
			parts.push( t( 'anySignedIn' ) );
		}
		var products = Array.prototype.map.call( root.querySelectorAll( '.wpec-pick[id$="_products"] .wpec-pick-chip' ), chipText );
		if ( products.length ) {
			var shown = products.slice( 0, 3 );
			var more = products.length - shown.length;
			var text = t( 'bought' ).replace( '%s', shown.join( ', ' ) );
			if ( more > 0 ) {
				text += ' ' + t( 1 === more ? 'andMoreOne' : 'andMoreMany' ).replace( '%d', String( more ) );
			}
			parts.push( text );
		}
		var roles = Array.prototype.map.call( root.querySelectorAll( '.wpec-access-role:checked' ), function ( box ) {
			return box.value;
		} );
		if ( roles.length ) {
			parts.push( t( 1 === roles.length ? 'roleOne' : 'roleMany' ).replace( '%s', roles.join( ', ' ) ) );
		}
		var users = root.querySelectorAll( '.wpec-pick[id$="_users"] .wpec-pick-chip' ).length;
		if ( users ) {
			parts.push( t( 1 === users ? 'custOne' : 'custMany' ).replace( '%d', String( users ) ) );
		}
		return parts.join( all ? t( 'joinAnd' ) : t( 'joinOr' ) );
	}

	function whereText( dest ) {
		var to = dest.querySelector( '.wpec-access-to' ).value;
		if ( 'page' === to ) {
			return chipText( dest.querySelector( '.wpec-access-dest-page .wpec-pick-chip' ) ) || t( 'wherePageNone' );
		}
		if ( 'url' === to ) {
			var url = ( dest.querySelector( '.wpec-access-url' ).value || '' ).trim();
			if ( ! url ) {
				return t( 'whereUrlNone' );
			}
			var h = host( url );
			if ( h && h !== host( cfg.home ) ) {
				return h + pathOf( url ).replace( /^\/$/, '' );
			}
			return pathOf( url );
		}
		return t( { sign_in: 'whereSignIn', message: 'whereMessage', product: 'whereProduct', store: 'whereStore', stay: 'whereStay' }[ to ] || '' ) || to;
	}

	function drawStatus( summary, title, lines ) {
		var titleEl = summary.querySelector( '.wpec-access-summary-title' );
		var list = summary.querySelector( '.wpec-access-summary-lines' );
		if ( titleEl ) {
			titleEl.textContent = title;
		}
		if ( ! list ) {
			return;
		}
		list.textContent = '';
		lines.forEach( function ( line ) {
			var row = document.createElement( 'div' );
			if ( line[ 0 ] ) {
				var dt = document.createElement( 'dt' );
				dt.textContent = line[ 0 ];
				row.appendChild( dt );
			}
			var dd = document.createElement( 'dd' );
			dd.textContent = line[ 1 ];
			row.appendChild( dd );
			list.appendChild( row );
		} );
	}

	function changed( root ) {
		var listed = root.querySelector( '.wpec-access-problems' );
		if ( listed ) {
			listed.hidden = true; /* the saved rule's problems; the live checks take over */
		}
		update( root );
	}

	function bindFields( root ) {
		if ( ! root || root.wpecAccessBound ) {
			return;
		}
		root.wpecAccessBound = true;
		root.addEventListener( 'change', function () {
			changed( root );
		} );
		root.addEventListener( 'input', function ( e ) {
			if ( e.target && e.target.classList && e.target.classList.contains( 'wpec-access-url' ) ) {
				changed( root );
			}
		} );
		update( root );
	}

	/* Every picker in the fields calls this after a change ( print_picker on_change ). */
	window.wpecPageAccessChanged = function () {
		document.querySelectorAll( '.wpec-access-fields' ).forEach( changed );
	};

	function bindAll() {
		document.querySelectorAll( '.wpec-access-fields' ).forEach( bindFields );
	}

	/* ------------------------------------------------------------------ Users › Limit Access */

	function post( data ) {
		var body = new FormData();
		Object.keys( data ).forEach( function ( key ) {
			var value = data[ key ];
			if ( Array.isArray( value ) ) {
				value.forEach( function ( v ) {
					body.append( key + '[]', v );
				} );
			} else {
				body.append( key, value );
			}
		} );
		body.append( 'nonce', cfg.nonce || '' );
		return fetch( cfg.ajax, { method: 'POST', credentials: 'same-origin', body: body } ).then( function ( r ) {
			return r.json();
		} );
	}

	var drawer = null;
	var lastFocus = null;
	var current = 0;

	function drawerEl( sel ) {
		return drawer ? drawer.querySelector( sel ) : null;
	}

	function message( msg, bad ) {
		var el = drawerEl( '.wpec-access-drawer-msg' );
		if ( el ) {
			el.textContent = msg || '';
			el.className = 'wpec-access-drawer-msg' + ( bad ? ' is-bad' : '' );
		}
	}

	function openDrawer( postId ) {
		drawer = document.querySelector( '.wpec-access-drawer' );
		if ( ! drawer ) {
			return;
		}
		lastFocus = document.activeElement;
		current = 0;
		drawer.hidden = false;
		document.documentElement.classList.add( 'wpec-access-drawer-open' );
		drawerEl( '.wpec-access-drawer-fields' ).innerHTML = '';
		drawerEl( '.wpec-access-drawer-save' ).disabled = true;
		drawerEl( '.wpec-access-drawer-view' ).hidden = true;
		message( '' );
		var choose = drawerEl( '.wpec-access-choose' );
		choose.hidden = !! postId;
		var title = drawerEl( '#wpec-access-drawer-title' );
		if ( ! title.hasAttribute( 'data-new' ) ) {
			title.setAttribute( 'data-new', title.textContent ); /* "Limit a page" */
		}
		title.textContent = title.getAttribute( 'data-new' );
		if ( postId ) {
			load( postId );
		} else {
			var chips = choose.querySelector( '.wpec-pick-chips' );
			if ( chips ) {
				chips.innerHTML = '';
			}
			var target = drawerEl( '#wpec_access_choose_post' );
			if ( target ) {
				target.value = '0';
			}
			var search = choose.querySelector( '.wpec-pick-input' );
			if ( search ) {
				search.value = '';
				search.focus();
			}
		}
	}

	function closeDrawer() {
		if ( ! drawer ) {
			return;
		}
		drawer.hidden = true;
		document.documentElement.classList.remove( 'wpec-access-drawer-open' );
		if ( lastFocus && lastFocus.focus ) {
			lastFocus.focus();
		}
	}

	function load( postId ) {
		var fields = drawerEl( '.wpec-access-drawer-fields' );
		fields.innerHTML = '<p class="wpec-access-loading">' + t( 'loading' ) + '</p>';
		post( { action: 'ecv2_page_access_form', post_id: postId } ).then( function ( res ) {
			if ( ! res || ! res.success ) {
				fields.innerHTML = '';
				message( ( res && res.data && res.data.message ) || t( 'failed' ), true );
				return;
			}
			current = postId;
			fields.innerHTML = res.data.html;
			drawerEl( '#wpec-access-drawer-title' ).textContent = res.data.title;
			var view = drawerEl( '.wpec-access-drawer-view' );
			view.href = res.data.view;
			view.hidden = ! res.data.view;
			if ( typeof window.wpecPickInit === 'function' ) {
				window.wpecPickInit();
			}
			bindFields( fields.querySelector( '.wpec-access-fields' ) );
			drawerEl( '.wpec-access-drawer-save' ).disabled = false;
			var first = fields.querySelector( '.wpec-access-on' );
			if ( first ) {
				first.focus();
			}
		} ).catch( function () {
			fields.innerHTML = '';
			message( t( 'failed' ), true );
		} );
	}

	window.wpecPageAccessChosen = function () {
		var target = document.getElementById( 'wpec_access_choose_post' );
		var id = target ? parseInt( target.value, 10 ) || 0 : 0;
		if ( id ) {
			load( id );
		}
	};

	function save() {
		if ( ! current ) {
			message( t( 'choosePage' ), true );
			return;
		}
		var form = drawerEl( '.wpec-access-drawer-form' );
		var body = new FormData( form );
		body.append( 'action', 'ecv2_page_access_save' );
		body.append( 'post_id', current );
		body.append( 'nonce', cfg.nonce || '' );
		var button = drawerEl( '.wpec-access-drawer-save' );
		button.disabled = true;
		message( t( 'saving' ) );
		fetch( cfg.ajax, { method: 'POST', credentials: 'same-origin', body: body } ).then( function ( r ) {
			return r.json();
		} ).then( function ( res ) {
			if ( ! res || ! res.success ) {
				button.disabled = false;
				message( ( res && res.data && res.data.message ) || t( 'failed' ), true );
				return;
			}
			message( t( 'saved' ) );
			window.location.reload();
		} ).catch( function () {
			button.disabled = false;
			message( t( 'failed' ), true );
		} );
	}

	function toggle( ids, on ) {
		return post( { action: 'ecv2_page_access_toggle', ids: ids, on: on ? '1' : '0' } ).then( function ( res ) {
			if ( res && res.success ) {
				window.location.reload();
			} else {
				window.alert( ( res && res.data && res.data.message ) || t( 'failed' ) ); // eslint-disable-line no-alert
			}
		} );
	}

	function checked() {
		return Array.prototype.map.call( document.querySelectorAll( '.wpec-access-row-check:checked' ), function ( box ) {
			return box.value;
		} );
	}

	function bulkBar() {
		var bar = document.querySelector( '.wpec-access-bulk' );
		if ( ! bar ) {
			return;
		}
		var ids = checked();
		bar.hidden = ! ids.length;
		var count = bar.querySelector( '.wpec-access-bulk-count' );
		if ( count ) {
			count.textContent = ids.length ? String( ids.length ) : '';
		}
	}

	document.addEventListener( 'click', function ( e ) {
		var el = e.target.closest ? e.target.closest( '[data-wpec-access-edit],[data-wpec-access-new],[data-wpec-access-close],[data-wpec-access-toggle],[data-wpec-access-bulk],.wpec-access-drawer-save' ) : null;
		if ( ! el ) {
			return;
		}
		if ( el.hasAttribute( 'data-wpec-access-edit' ) ) {
			e.preventDefault();
			openDrawer( parseInt( el.getAttribute( 'data-wpec-access-edit' ), 10 ) || 0 );
		} else if ( el.hasAttribute( 'data-wpec-access-new' ) ) {
			e.preventDefault();
			openDrawer( 0 );
		} else if ( el.hasAttribute( 'data-wpec-access-close' ) ) {
			e.preventDefault();
			closeDrawer();
		} else if ( el.hasAttribute( 'data-wpec-access-toggle' ) ) {
			e.preventDefault();
			el.disabled = true;
			toggle( [ el.getAttribute( 'data-wpec-access-toggle' ) ], '1' === el.getAttribute( 'data-on' ) );
		} else if ( el.hasAttribute( 'data-wpec-access-bulk' ) ) {
			e.preventDefault();
			var on = '1' === el.getAttribute( 'data-wpec-access-bulk' );
			var ids = checked();
			if ( ! ids.length || ( ! on && ids.length > 1 && ! window.confirm( t( 'confirmOffMany' ) ) ) ) { // eslint-disable-line no-alert
				return;
			}
			toggle( ids, on );
		} else if ( el.classList.contains( 'wpec-access-drawer-save' ) ) {
			e.preventDefault();
			save();
		}
	} );

	document.addEventListener( 'change', function ( e ) {
		if ( e.target.classList.contains( 'wpec-access-check-all' ) ) {
			document.querySelectorAll( '.wpec-access-row-check' ).forEach( function ( box ) {
				box.checked = e.target.checked;
			} );
			bulkBar();
		} else if ( e.target.classList.contains( 'wpec-access-row-check' ) ) {
			bulkBar();
		}
	} );

	document.addEventListener( 'keydown', function ( e ) {
		if ( ! drawer || drawer.hidden ) {
			return;
		}
		if ( 'Escape' === e.key ) {
			var open = drawer.querySelector( '.wpec-pick-results:not([hidden])' );
			if ( ! open ) {
				closeDrawer();
			}
		} else if ( 'Tab' === e.key ) {
			/* focus stays in the drawer */
			var panel = drawer.querySelector( '.wpec-access-drawer-panel' );
			var items = Array.prototype.filter.call( panel.querySelectorAll( 'a[href],button:not([disabled]),input:not([type=hidden]):not([disabled]),select,textarea,[tabindex]:not([tabindex="-1"])' ), function ( el ) {
				return el.offsetParent !== null;
			} );
			if ( ! items.length ) {
				return;
			}
			var first = items[ 0 ];
			var last = items[ items.length - 1 ];
			if ( e.shiftKey && document.activeElement === first ) {
				e.preventDefault();
				last.focus();
			} else if ( ! e.shiftKey && document.activeElement === last ) {
				e.preventDefault();
				first.focus();
			}
		}
	} );

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', bindAll );
	} else {
		bindAll();
	}
	window.wpecPageAccessBind = bindAll;
}() );
