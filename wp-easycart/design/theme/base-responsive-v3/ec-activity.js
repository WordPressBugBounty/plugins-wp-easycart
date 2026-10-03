/**
 * WP EasyCart store activity ( 6.0.2 ): counts product views and store searches for Reports.
 *
 * Pages print markers ( window.wpeasycart_activity.push( [ 'view', product_id ] ) or [ 'search', term, found ] ); this
 * script sends them in one beacon to admin-ajax.php ( action wp_easycart_activity ). Nothing about the visitor is sent
 * or kept: the server only adds to daily totals. A product or search is counted once per browsing session ( sessionStorage ).
 * Markers printed after this script ran ( a redrawn Elementor widget, a quick view ) are sent as they come.
 *
 * While the store asks for cookie consent ( Settings › Integrations › Cookie consent, config.consent ), views and searches
 * count only once the shopper allows statistics, read from the consent bridge ( window.wpeasycart_consent ) as visits are:
 * until then they wait on the page and nothing goes into sessionStorage, and a refusal forgets what this browsing session
 * counted. A search that looks like an email address or a phone number is never sent ( the server skips it too ).
 *
 * The storefront check ( ?wpec_activity_check=1, config.check ) shows a panel and a console line: whether this browser is
 * counted and why not, the consent answer, what this page counts, and what the server counted ( the check posts a request
 * it can read, with check=1, instead of a beacon ). Nothing else changes.
 *
 * @since 6.0.2
 */
( function() {
	'use strict';

	var config = window.wpeasycart_activity_config || {};
	var waiting = ( window.wpeasycart_activity && window.wpeasycart_activity.length ) ? window.wpeasycart_activity : [];
	var pending = [];
	var held = [];
	var timer = null;
	var prefix = 'wpec_act_';
	var check = ( config.check && 'object' === typeof config.check ) ? config.check : null;
	var log = [];
	var reply = null;
	var box = null;
	var said = '';

	/* The consent bridge, while the store asks for consent ( printed first in the head ). */
	function consent() {
		return config.consent ? ( window.wpeasycart_consent || null ) : null;
	}

	/* May views and searches be counted? Always while the store does not ask; else only with statistics consent. */
	function allowed() {
		if ( ! config.consent ) {
			return true;
		}
		var bridge = consent();
		return !! ( bridge && 'function' === typeof bridge.has && bridge.has( 'statistics' ) );
	}

	/* Has the shopper answered no to statistics ( not "no answer yet" )? */
	function refused() {
		var bridge = consent();
		return !! ( bridge && 'function' === typeof bridge.known && bridge.known( 'statistics' ) && ! bridge.has( 'statistics' ) );
	}

	/* Forget which products and searches this browsing session counted. */
	function forget() {
		try {
			for ( var i = window.sessionStorage.length - 1; i >= 0; i-- ) {
				var name = window.sessionStorage.key( i );
				if ( name && 0 === name.indexOf( prefix ) ) {
					window.sessionStorage.removeItem( name );
				}
			}
		} catch ( e ) {
			/* Storage blocked: nothing was kept. */
		}
	}

	/*
	 * Does a search term look like an email address ( text on both sides of an @ ) or a phone number ( 7 or more digits,
	 * with spaces, dashes, dots, brackets, slashes or a + between them, not joined to letters )? The same rule as
	 * wp_easycart_store_activity::looks_personal().
	 */
	function personal( term ) {
		term = String( null === term || 'undefined' === typeof term ? '' : term );
		if ( /[^\s@]@[^\s@]/.test( term ) ) {
			return true;
		}
		var numbers = /(^|[^a-z0-9])(\+?\(?\d(?:[\s().\/-]{0,2}\d)+)(?![a-z0-9])/gi;
		var match;
		while ( null !== ( match = numbers.exec( term ) ) ) {
			/* A phone number: 7+ digits written with a + or ( or separators, or a plain run of 10 or 11 digits; other plain
			   runs are SKUs and barcodes ( 12+ digits ). */
			var digits = match[2].replace( /\D/g, '' ).length;
			if ( digits >= 7 && ( /^[+(]|\d[\s().\/-]+\d/.test( match[2] ) || ( digits >= 10 && digits <= 11 ) ) ) {
				return true;
			}
		}
		return false;
	}

	function seen( key ) {
		try {
			var name = prefix + key;
			if ( window.sessionStorage.getItem( name ) ) {
				return true;
			}
			window.sessionStorage.setItem( name, '1' );
		} catch ( e ) {
			/* Storage blocked: count it. */
		}
		return false;
	}

	function flush() {
		timer = null;
		if ( ! pending.length || ! config.url ) {
			return;
		}
		var batch = pending.splice( 0, 30 );
		var body = new FormData();
		body.append( 'action', 'wp_easycart_activity' );
		body.append( 'e', JSON.stringify( batch ) );
		if ( check ) {
			/* The check reads what the server counted, so it posts a request it can read instead of a beacon. */
			body.append( 'check', '1' );
			var ask = new XMLHttpRequest();
			ask.open( 'POST', config.url, true );
			ask.onload = function() {
				var answer = null;
				try {
					answer = JSON.parse( ask.responseText );
				} catch ( e ) {
					answer = null;
				}
				checked( batch, ask.status, answer );
			};
			ask.onerror = function() {
				checked( batch, 0, null );
			};
			ask.send( body );
			return;
		}
		try {
			if ( navigator.sendBeacon && navigator.sendBeacon( config.url, body ) ) {
				return;
			}
		} catch ( e ) {
			/* Fall back to a request. */
		}
		var request = new XMLHttpRequest();
		request.open( 'POST', config.url, true );
		request.send( body );
	}

	function add( event ) {
		if ( ! event || ! event.length ) {
			return;
		}
		if ( navigator.webdriver ) {
			note( event, 'robot' );
			return;
		}
		if ( 'search' === event[0] && personal( event[1] ) ) {
			note( event, 'personal' );
			return;
		}
		if ( ! allowed() ) {
			/* Waits on this page for the shopper's answer; kept nowhere else. */
			if ( held.length < 50 ) {
				held.push( event );
			}
			note( event, 'held' );
			return;
		}
		if ( ( 'view' === event[0] || 'search' === event[0] ) && seen( event[0] + ':' + String( event[1] ).slice( 0, 100 ) ) ) {
			note( event, 'seen' );
			return;
		}
		pending.push( event );
		note( event, 'sending' );
		if ( ! timer ) {
			timer = window.setTimeout( flush, 60 );
		}
	}

	/* === The storefront check ( config.check ) === */

	/* What happened to an event, for the panel. */
	function note( event, status ) {
		if ( ! check ) {
			return;
		}
		for ( var i = 0; i < log.length; i++ ) {
			if ( log[ i ].e === event ) {
				log[ i ].s = status;
				show();
				return;
			}
		}
		if ( log.length < 40 ) {
			log.push( { e: event, s: status } );
		}
		show();
	}

	/* The server's answer to the check's request: what it counted ( view, search ) and why_not(). */
	function checked( batch, status, answer ) {
		reply = { status: status, answer: ( answer && 'object' === typeof answer ) ? answer : null };
		for ( var i = 0; i < batch.length; i++ ) {
			note( batch[ i ], reply.answer ? 'answered' : 'failed' );
		}
		show();
	}

	function text( key ) {
		return ( check && check.i18n && check.i18n[ key ] ) ? String( check.i18n[ key ] ) : key;
	}

	function fill( format, first, second ) {
		return String( format ).replace( '%1$s', String( first ) ).replace( '%2$s', String( second ) ).replace( '%s', String( first ) );
	}

	/* Is a cookie banner WP EasyCart reads on this page ( its script or its cookie )? */
	function bannerHere() {
		return !! ( window.Cookiebot || 'function' === typeof window.getCkyConsent || window.complianz || 'function' === typeof window.cmplz_has_consent || 'function' === typeof window.wp_has_consent || /(?:^|;\s*)(?:CookieConsent|cookieyes-consent|viewed_cookie_policy|cmplz_[A-Za-z_]+|wp_consent_[A-Za-z_]+)=/.test( document.cookie || '' ) );
	}

	/* The panel's lines: [ label, value, tone ]. */
	function lines() {
		var out = [];
		var why = String( check.why || '' );
		out.push( [ text( 'counting' ), 'off' === why ? text( 'off' ) : ( 'update' === why ? text( 'update' ) : text( 'on' ) ), ( 'off' === why || 'update' === why ) ? 'bad' : 'good' ] );
		var visitor = text( 'counted' );
		if ( 'staff' === why || 'crawler' === why || 'filter' === why ) {
			visitor = text( why );
		} else if ( navigator.webdriver ) {
			visitor = text( 'robot' );
		}
		out.push( [ text( 'visitor' ), visitor, visitor === text( 'counted' ) ? 'good' : 'bad' ] );
		var consentText = text( 'not_asked' );
		var consentTone = 'good';
		if ( config.consent ) {
			if ( allowed() ) {
				consentText = text( 'allowed' );
			} else if ( refused() ) {
				consentText = text( 'refused' );
				consentTone = 'bad';
			} else {
				consentText = text( 'waiting' );
				consentTone = 'bad';
			}
			consentText += ' ( ' + String( check.setting || '' ) + ' )';
		}
		out.push( [ text( 'consent' ), consentText, consentTone ] );
		if ( config.consent && ! allowed() ) {
			if ( check.block ) {
				out.push( [ '', String( check.block ), 'bad' ] );
			}
			if ( ( check.block || 'any' === check.mode ) && ! bannerHere() ) {
				out.push( [ '', text( 'no_banner' ), 'bad' ] );
			}
		}
		if ( ! log.length ) {
			out.push( [ text( 'page' ), text( 'nothing' ), '' ] );
		}
		for ( var i = 0; i < log.length; i++ ) {
			var ev = log[ i ].e;
			var what = ( 'search' === ev[0] ) ? fill( text( 'search' ), 'personal' === log[ i ].s ? '…' : ev[1], parseInt( ev[2], 10 ) || 0 ) : ( 'view' === ev[0] ? fill( text( 'view' ), ev[1] ) : String( ev[0] ) );
			var state = log[ i ].s;
			var outcome = text( state );
			out.push( [ text( 'page' ), what + ': ' + outcome, ( 'answered' === state || 'sending' === state ) ? 'good' : 'bad' ] );
		}
		if ( reply ) {
			if ( reply.answer && reply.answer.counted ) {
				out.push( [ text( 'sent' ), fill( text( 'stored' ), parseInt( reply.answer.counted.search, 10 ) || 0, parseInt( reply.answer.counted.view, 10 ) || 0 ), ( ( parseInt( reply.answer.counted.search, 10 ) || 0 ) + ( parseInt( reply.answer.counted.view, 10 ) || 0 ) ) > 0 ? 'good' : 'bad' ] );
				if ( reply.answer.why && ( 'staff' === reply.answer.why || 'crawler' === reply.answer.why || 'filter' === reply.answer.why || 'off' === reply.answer.why || 'update' === reply.answer.why ) ) {
					out.push( [ '', text( reply.answer.why ), 'bad' ] );
				}
			} else {
				out.push( [ text( 'sent' ), text( 'failed' ) + ' ( HTTP ' + String( reply.status ) + ' )', 'bad' ] );
			}
		}
		return out;
	}

	function show() {
		if ( ! check ) {
			return;
		}
		var rows = lines();
		var summary = [];
		for ( var i = 0; i < rows.length; i++ ) {
			summary.push( ( rows[ i ][0] ? rows[ i ][0] + ': ' : '' ) + rows[ i ][1] );
		}
		summary = summary.join( ' | ' );
		if ( summary !== said ) {
			said = summary;
			try {
				window.console.info( '[WP EasyCart store activity] ' + summary );
			} catch ( e ) {
				/* No console. */
			}
		}
		if ( ! document.body ) {
			return;
		}
		if ( ! box ) {
			box = document.createElement( 'div' );
			box.id = 'wpeasycart-activity-check';
			box.setAttribute( 'role', 'status' );
			box.setAttribute( 'style', 'position:fixed;bottom:12px;right:12px;z-index:2147483646;max-width:340px;max-height:70vh;overflow:auto;background:#111827;color:#f9fafb;font:13px/1.45 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;padding:12px 14px;border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.3);text-align:left' );
			document.body.appendChild( box );
		}
		while ( box.firstChild ) {
			box.removeChild( box.firstChild );
		}
		var head = document.createElement( 'div' );
		head.style.fontWeight = '600';
		head.style.marginBottom = '6px';
		head.style.paddingRight = '18px';
		head.appendChild( document.createTextNode( text( 'title' ) ) );
		box.appendChild( head );
		for ( var r = 0; r < rows.length; r++ ) {
			var line = document.createElement( 'div' );
			line.style.margin = '2px 0';
			if ( rows[ r ][0] ) {
				var label = document.createElement( 'span' );
				label.style.color = '#9ca3af';
				label.textContent = rows[ r ][0] + ': ';
				line.appendChild( label );
			}
			var value = document.createElement( 'span' );
			value.style.color = ( 'good' === rows[ r ][2] ) ? '#86efac' : ( 'bad' === rows[ r ][2] ? '#fca5a5' : '#f9fafb' );
			value.textContent = rows[ r ][1];
			line.appendChild( value );
			box.appendChild( line );
		}
		var hint = document.createElement( 'div' );
		hint.style.marginTop = '6px';
		hint.style.color = '#d1d5db';
		hint.style.fontSize = '12px';
		hint.textContent = text( 'hint' );
		box.appendChild( hint );
		var close = document.createElement( 'button' );
		close.type = 'button';
		close.textContent = '×';
		close.setAttribute( 'aria-label', text( 'close' ) );
		close.setAttribute( 'style', 'position:absolute;top:6px;right:8px;background:none;border:0;color:#9ca3af;font-size:18px;line-height:1;cursor:pointer;padding:2px' );
		close.onclick = function() {
			document.cookie = String( check.cookie || 'wpec_activity_check' ) + '=;path=' + String( check.path || '/' ) + ';max-age=0;SameSite=Lax';
			check = null;
			if ( box && box.parentNode ) {
				box.parentNode.removeChild( box );
			}
			box = null;
		};
		box.appendChild( close );
	}

	/* A new consent answer: count what waited once statistics are allowed; forget this session's counts when refused. */
	function answered() {
		if ( allowed() ) {
			var list = held;
			held = [];
			for ( var i = 0; i < list.length; i++ ) {
				add( list[ i ] );
			}
		} else if ( refused() ) {
			forget();
		}
		show();
	}

	if ( config.consent ) {
		var bridge = consent();
		if ( bridge && 'function' === typeof bridge.on ) {
			bridge.on( answered );
		} else {
			document.addEventListener( 'wpeasycart_consent_change', answered );
		}
		if ( refused() ) {
			forget();
		}
	}

	window.wpeasycart_activity = {
		push: function() {
			for ( var i = 0; i < arguments.length; i++ ) {
				add( arguments[ i ] );
			}
			return pending.length;
		},
		length: 0
	};
	for ( var i = 0; i < waiting.length; i++ ) {
		add( waiting[ i ] );
	}
	if ( check ) {
		/* Drawn now, and again once the page and a cookie banner loaded later have run. */
		show();
		window.addEventListener( 'load', show );
		window.setTimeout( show, 2500 );
	}
} )();
