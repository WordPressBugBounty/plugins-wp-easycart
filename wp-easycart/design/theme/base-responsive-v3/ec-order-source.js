/**
 * WP EasyCart order sources ( 6.0.2 ).
 *
 * Runs on every storefront page and keeps, in the first-party cookie window.wpeasycart_order_source.cookie ( wpec_source ),
 * the shopper's first visit and the latest visit that brought them back: the referring site, the campaign tags ( UTM ),
 * which ad or email click IDs ( config.clicks: gclid, Mailchimp's mc_cid... ) were in the address, by name only, never their
 * values, and the landing page. PHP reads it when an order is placed
 * ( wp_easycart_order_source::stamp() ). Nothing identifies the shopper: no IP address, no ID, no browser details.
 *
 * Rules: the first visit is kept for 90 days. The latest visit is replaced by any visit with campaign tags, an ad click,
 * an AI assistant or a search engine; by another site only when it starts a new visit ( 30 minutes quiet ); never by a
 * direct visit. The store's own site, payment pages and the sites the merchant lists are never visits of their own, and on
 * the cart and account pages ( where shoppers come back from bank pages ) a referring site alone never counts.
 *
 * With the WP Consent API present it waits for the configured category, and deletes the cookie when consent is refused;
 * 6.0.2: it also waits for a consent type that a consent plugin defines later, and for the API's own script.
 * A prerendered page waits until the shopper opens it. Kept separate from ec-store.js so theme copies of that file and
 * its minified build still get it. Plain ES5, no dependencies.
 *
 * @since 6.0.2
 */
( function() {
	'use strict';

	var config = window.wpeasycart_order_source;
	if ( ! config || ! config.cookie || 'undefined' === typeof document.cookie ) {
		return;
	}
	var done = false;

	/* ------------------------------------------------------------------ */
	/* Cookie                                                              */
	/* ------------------------------------------------------------------ */

	function cookieTail( maxAge ) {
		return '; max-age=' + maxAge + '; path=' + ( config.path || '/' ) + ( config.domain ? '; domain=' + config.domain : '' ) + '; SameSite=Lax' + ( config.secure ? '; Secure' : '' );
	}

	function read() {
		var match = document.cookie.match( new RegExp( '(?:^|; )' + config.cookie + '=([A-Za-z0-9_-]+)' ) );
		if ( ! match ) {
			return null;
		}
		try {
			var base = match[1].replace( /-/g, '+' ).replace( /_/g, '/' );
			while ( base.length % 4 ) {
				base += '=';
			}
			var data = JSON.parse( decodeURIComponent( escape( window.atob( base ) ) ) );
			return ( data && 1 === data.v && ( data.f || data.l ) ) ? data : null;
		} catch ( e ) {
			return null;
		}
	}

	function encode( data ) {
		return window.btoa( unescape( encodeURIComponent( JSON.stringify( data ) ) ) ).replace( /\+/g, '-' ).replace( /\//g, '_' ).replace( /=+$/, '' );
	}

	/*
	 * 6.0.2: browsers drop a cookie over about 4 KB without a word, and PHP reads no more than 4,000 characters. Long tags in
	 * other alphabets can pass that, so the least useful tags go first ( term, content, id; the latest visit's, then the
	 * first visit's ), then every tag is shortened.
	 */
	function fit( data ) {
		var limit = 3500;
		var value = encode( data );
		var visits = [ data.l, data.f ];
		var drop = [ 't', 'o', 'i' ];
		for ( var v = 0; v < visits.length && value.length > limit; v++ ) {
			if ( visits[ v ] && visits[ v ].u ) {
				for ( var d = 0; d < drop.length; d++ ) {
					delete visits[ v ].u[ drop[ d ] ];
				}
				value = encode( data );
			}
		}
		for ( var w = 0; w < visits.length && value.length > limit; w++ ) {
			if ( visits[ w ] && visits[ w ].u ) {
				for ( var key in visits[ w ].u ) {
					if ( Object.prototype.hasOwnProperty.call( visits[ w ].u, key ) ) {
						visits[ w ].u[ key ] = String( visits[ w ].u[ key ] ).slice( 0, 30 );
					}
				}
				value = encode( data );
			}
		}
		return value;
	}

	function write( data ) {
		try {
			var value = fit( data );
			if ( value.length <= 4000 ) {
				document.cookie = config.cookie + '=' + value + cookieTail( config.days * 86400 );
			}
		} catch ( e ) {}
	}

	function remove() {
		document.cookie = config.cookie + '=' + cookieTail( 0 );
	}

	/* ------------------------------------------------------------------ */
	/* The visit                                                           */
	/* ------------------------------------------------------------------ */

	function params() {
		var out = {};
		var query = window.location.search.replace( /^\?/, '' );
		if ( ! query ) {
			return out;
		}
		var parts = query.split( '&' );
		for ( var i = 0; i < parts.length; i++ ) {
			var eq = parts[ i ].indexOf( '=' );
			var key = eq < 0 ? parts[ i ] : parts[ i ].slice( 0, eq );
			var value = eq < 0 ? '' : parts[ i ].slice( eq + 1 );
			try {
				key = decodeURIComponent( key.replace( /\+/g, ' ' ) ).toLowerCase();
				value = decodeURIComponent( value.replace( /\+/g, ' ' ) );
			} catch ( e ) {
				continue;
			}
			if ( ! Object.prototype.hasOwnProperty.call( out, key ) ) {
				out[ key ] = value;
			}
		}
		return out;
	}

	/* Same rule as wp_easycart_order_source::host_matches(): the host or a subdomain of it; "name.*" under any country ending. */
	function matches( host, key ) {
		if ( ! host || ! key ) {
			return false;
		}
		if ( '.*' === key.slice( -2 ) ) {
			var name = key.slice( 0, -2 ).replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
			return new RegExp( '(^|\\.)' + name + '\\.[a-z]{2,3}(\\.[a-z]{2})?$' ).test( host );
		}
		return host === key || host.slice( -( key.length + 1 ) ) === '.' + key;
	}

	function onList( host, list ) {
		for ( var i = 0; i < ( list || [] ).length; i++ ) {
			if ( matches( host, list[ i ] ) ) {
				return true;
			}
		}
		return false;
	}

	function onSkipPage() {
		var path = window.location.pathname.replace( /\/+$/, '' );
		for ( var i = 0; i < ( config.skip || [] ).length; i++ ) {
			if ( path === config.skip[ i ] || 0 === path.indexOf( config.skip[ i ] + '/' ) ) {
				return true;
			}
		}
		/* 6.0.2: with plain permalinks the cart and account pages are ?page_id=N. */
		var pageId = parseInt( params().page_id, 10 );
		for ( var j = 0; pageId && j < ( config.skip_ids || [] ).length; j++ ) {
			if ( pageId === parseInt( config.skip_ids[ j ], 10 ) ) {
				return true;
			}
		}
		return false;
	}

	/*
	 * The referring site: '' for none ( a direct visit ), null when it doesn't count ( the store itself, a payment page,
	 * a listed site, or any site on the cart and account pages ), or its host ( android-app://package for Android apps ).
	 * 6.0.2: the cart and account pages only ignore a site for a shopper already recorded ( known ): a new shopper who lands
	 * on the cart from a Cart Link or buy link shared on another site keeps that site.
	 */
	function referrer( known ) {
		var match = ( document.referrer || '' ).match( /^([a-z][a-z0-9+.-]*):\/\/([^\/?#:]+)/i );
		if ( ! match ) {
			return '';
		}
		var scheme = match[1].toLowerCase();
		var host = match[2].toLowerCase();
		var skip = known && onSkipPage();
		if ( 'android-app' === scheme ) {
			return skip ? null : 'android-app://' + host;
		}
		if ( 'http' !== scheme && 'https' !== scheme ) {
			return '';
		}
		host = host.replace( /^www\./, '' );
		if ( ( config.site && matches( host, config.site ) ) || onList( host, config.ignore ) || skip ) {
			return null;
		}
		return host;
	}

	function record() {
		if ( done ) {
			return;
		}
		done = true;
		var now = Math.floor( Date.now() / 1000 );
		var query = params();
		var tags = {};
		var tagged = false;
		var extra = false;
		var names = [ [ 'utm_source', 's' ], [ 'utm_medium', 'm' ], [ 'utm_campaign', 'c' ], [ 'utm_term', 't' ], [ 'utm_content', 'o' ], [ 'utm_id', 'i' ] ];
		for ( var i = 0; i < names.length; i++ ) {
			if ( query[ names[ i ][0] ] ) {
				tags[ names[ i ][1] ] = String( query[ names[ i ][0] ] ).slice( 0, 100 );
				/* 6.0.2: only source, medium and campaign say where a visit came from. A link with just utm_term,
				   utm_content or utm_id is kept with the visit but counts like an untagged one ( it used to replace the
				   latest visit, which then read as Direct ). */
				if ( i < 3 ) {
					tagged = true;
				} else {
					extra = true;
				}
			}
		}
		var clicks = [];
		for ( var c = 0; c < ( config.clicks || [] ).length; c++ ) {
			if ( query[ config.clicks[ c ] ] ) {
				clicks.push( config.clicks[ c ] );
			}
		}
		var data = read();
		var host = referrer( !! data );
		var fresh = ! data || ! data.s || ( now - data.s ) > ( config.session || 30 ) * 60;
		/* 6.0.2: a new visit ( not a return from a payment page or the store itself ) is counted for Reports. */
		var counted = fresh && null !== host;

		function visit( from ) {
			var out = { t: now, r: from || '', p: String( window.location.pathname || '/' ).slice( 0, 200 ) };
			if ( tagged || extra ) {
				out.u = tags;
			}
			if ( clicks.length ) {
				out.k = clicks;
			}
			return out;
		}

		var touch = null;
		if ( tagged || clicks.length ) {
			touch = visit( host || '' );
		} else if ( host ) {
			touch = visit( host );
		} else if ( '' === host && fresh ) {
			touch = visit( '' );
		}

		if ( ! data || ! data.f || ( now - data.f.t ) > config.days * 86400 ) {
			touch = touch || visit( host || '' );
			data = { v: 1, f: touch, l: touch, n: 1, s: now };
		} else {
			if ( fresh && null !== host ) {
				data.n = ( data.n || 1 ) + 1;
			}
			if ( touch ) {
				var strong = tagged || clicks.length || ( touch.r && ( 0 === touch.r.indexOf( 'android-app://' ) || onList( touch.r, config.strong ) ) );
				if ( strong || ( touch.r && fresh ) ) {
					data.l = touch;
				}
			}
			data.s = now;
		}
		write( data );
		if ( counted ) {
			report( touch || visit( host || '' ) );
		}
	}

	/* 6.0.2: Reports counts visits by source type ( Settings › Store activity ); only the day's total is kept. */
	function report( touch ) {
		if ( ! config.visits || navigator.webdriver ) {
			return;
		}
		var body = new FormData();
		body.append( 'action', 'wp_easycart_activity' );
		body.append( 'e', JSON.stringify( [ [ 'visit', touch ] ] ) );
		try {
			if ( navigator.sendBeacon && navigator.sendBeacon( config.visits, body ) ) {
				return;
			}
		} catch ( e ) {
			/* Fall back to a request. */
		}
		var request = new XMLHttpRequest();
		request.open( 'POST', config.visits, true );
		request.send( body );
	}

	/* ------------------------------------------------------------------ */
	/* Consent                                                             */
	/* ------------------------------------------------------------------ */

	/* 6.0.2: Settings › Integrations › Cookie consent set to Google Consent Mode, Cookiebot, CookieYes or Complianz: the
	   store's consent bridge ( window.wpeasycart_consent, printed first in the head ) answers instead of the WP Consent API. */
	function bridge() {
		return ( config.bridge && window.wpeasycart_consent ) ? window.wpeasycart_consent : null;
	}

	function allowed() {
		if ( bridge() ) {
			return bridge().has( config.consent || 'marketing' );
		}
		if ( 'function' !== typeof window.wp_has_consent ) {
			/* 6.0.2: with the WP Consent API active on the site, a missing function means it has not loaded yet. */
			return ! config.consent_api;
		}
		try {
			return !! window.wp_has_consent( config.consent || 'marketing' );
		} catch ( e ) {
			return ! config.consent_api;
		}
	}

	/*
	 * 6.0.2: can consent be asked yet? The WP Consent API answers "yes" while no consent type is set, and consent plugins
	 * that work out the visitor's region in the browser set waitfor_consent_hook and define the type later
	 * ( event wp_consent_type_defined ). A script optimiser can also hold the API's script back.
	 */
	function consentReady() {
		if ( config.bridge ) {
			return !! ( bridge() && bridge().known( config.consent || 'marketing' ) );
		}
		if ( window.waitfor_consent_hook && 'undefined' === typeof window.wp_consent_type ) {
			return false;
		}
		return ! ( config.consent_api && 'function' !== typeof window.wp_has_consent );
	}

	function start() {
		if ( allowed() ) {
			record();
		} else if ( bridge() || 'function' === typeof window.wp_has_consent ) {
			remove();
		}
	}

	/* Start once consent can be asked: now, when the consent type is defined, or when the page has loaded. Until then,
	   and if it never can be, nothing is recorded ( a consent change to allow still records ). */
	function begin() {
		var begun = false;
		function attempt() {
			if ( ! begun && consentReady() ) {
				begun = true;
				start();
			}
		}
		attempt();
		if ( ! begun ) {
			document.addEventListener( 'wp_consent_type_defined', attempt );
			document.addEventListener( 'wpeasycart_consent_change', attempt );
			window.addEventListener( 'load', attempt );
		}
	}

	/* 6.0.2: a later answer from the consent bridge records the visit, or removes it when consent is refused. */
	document.addEventListener( 'wpeasycart_consent_change', function() {
		if ( ! bridge() || ! bridge().known( config.consent || 'marketing' ) ) {
			return;
		}
		if ( allowed() ) {
			record();
		} else {
			remove();
		}
	} );

	document.addEventListener( 'wp_listen_for_consent_change', function( event ) {
		var changed = ( event && event.detail ) ? event.detail : {};
		var state = changed[ config.consent || 'marketing' ];
		if ( 'allow' === state ) {
			record();
		} else if ( 'deny' === state ) {
			remove();
		}
	} );

	if ( document.prerendering ) {
		document.addEventListener( 'prerenderingchange', begin, { once: true } );
	} else {
		begin();
	}
}() );
