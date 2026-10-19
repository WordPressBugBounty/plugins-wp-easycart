/**
 * 6.0.5: Settings › Design › Cart & checkout look › "Fit the cart to your theme's content area" ( wp_easycart_admin_cart_fit ).
 * Check my theme opens the cart page out of sight, in a frame as wide as a laptop screen, reads the width the theme gives the cart
 * there and keeps it ( AJAX ecv2_cart_fit ), then draws the rows under the setting again. It runs by itself once for a theme that
 * has not been measured, on Settings › Design and on other EasyCart screens ( wpecCartFit.auto ). A cart page that will not open in a
 * frame ( a security header ) is reported as such.
 */
( function() {
	var cfg = window.wpecCartFit || {};
	var busy = false;

	function rows() {
		return document.querySelectorAll( '.wpec-cart-fit-status' );
	}

	function links() {
		return document.querySelectorAll( '.wpec-cart-fit-status a[href="#wpec-cart-fit-check"]' );
	}

	function setBusy( on ) {
		var list = links();
		for ( var i = 0; i < list.length; i++ ) {
			if ( on ) {
				list[ i ].setAttribute( 'data-label', list[ i ].textContent );
				list[ i ].textContent = ( cfg.text && cfg.text.checking ) || 'Checking…';
				list[ i ].setAttribute( 'aria-disabled', 'true' );
			} else if ( list[ i ].hasAttribute( 'data-label' ) ) {
				list[ i ].textContent = list[ i ].getAttribute( 'data-label' );
				list[ i ].removeAttribute( 'aria-disabled' );
			}
		}
		var all = rows();
		for ( var j = 0; j < all.length; j++ ) {
			all[ j ].setAttribute( 'aria-busy', on ? 'true' : 'false' );
		}
	}

	function kind( cart ) {
		var k = ' ' + cart.className + ' ';
		if ( -1 !== k.indexOf( ' ec_cart_subscription ' ) ) {
			return 'subscription';
		}
		return ( -1 !== k.indexOf( ' ec_cart_onepage_page ' ) ) ? 'onepage' : 'classic';
	}

	function draw( data ) {
		var list = rows();
		for ( var i = 0; i < list.length; i++ ) {
			var html = ( 'on' === list[ i ].getAttribute( 'data-wpec-cart-fit' ) ) ? data.on : data.off;
			if ( html ) {
				list[ i ].outerHTML = html;
			}
		}
	}

	function send( result ) {
		var body = new FormData();
		body.append( 'action', 'ecv2_cart_fit' );
		body.append( 'nonce', cfg.nonce || '' );
		body.append( 'width', result.width || 0 );
		body.append( 'viewport', result.viewport || 0 );
		body.append( 'page', result.page || 'classic' );
		body.append( 'fail', result.fail || '' );
		return fetch( cfg.ajax, { method: 'POST', body: body, credentials: 'same-origin' } ).then( function( response ) {
			return response.json();
		} ).then( function( json ) {
			busy = false;
			if ( json && json.success && json.data ) {
				draw( json.data );
				document.dispatchEvent( new CustomEvent( 'wpec:cart-fit-checked', { detail: { result: result, recommended: !! json.data.recommended } } ) );
				return;
			}
			setBusy( false );
		} ).catch( function() {
			busy = false;
			setBusy( false );
			if ( window.console && window.console.warn ) {
				window.console.warn( ( cfg.text && cfg.text.failed ) || 'The check did not finish.' );
			}
		} );
	}

	/* The cart's width in the frame, once the cart is there: the one-page checkout draws it after the page loads. */
	function measure( frame, started, done ) {
		var doc = null;
		var elapsed = Date.now() - started;
		try {
			doc = frame.contentDocument;
		} catch ( e ) {
			done( { fail: 'blocked' } );
			return;
		}
		if ( ! doc && elapsed > 3000 ) {
			done( { fail: 'blocked' } );
			return;
		}
		var ready = doc && doc.body && doc.location && 'about:blank' !== doc.location.href;
		if ( ready ) {
			var list = doc.getElementsByClassName( 'ec_cart_page' );
			for ( var i = 0; i < list.length; i++ ) {
				if ( list[ i ].closest && list[ i ].closest( '.wpec-el-cart-page' ) ) {
					continue;
				}
				var width = Math.round( list[ i ].getBoundingClientRect().width );
				if ( width > 0 ) {
					done( { width: width, viewport: doc.documentElement.clientWidth, page: kind( list[ i ] ) } );
					return;
				}
			}
			if ( doc.querySelector( '.wpec-el-cart-page' ) && elapsed > 4000 ) {
				done( { page: 'elementor' } );
				return;
			}
			var holder = doc.getElementById( 'wpeasycart_cart_holder' );
			if ( holder && elapsed > 9000 && holder.getBoundingClientRect().width > 0 ) {
				done( { width: Math.round( holder.getBoundingClientRect().width ), viewport: doc.documentElement.clientWidth, page: 'onepage' } );
				return;
			}
		}
		if ( elapsed > 15000 ) {
			done( { fail: ready ? 'none' : 'blocked' } );
			return;
		}
		setTimeout( function() {
			measure( frame, started, done );
		}, 300 );
	}

	function check() {
		if ( busy || ! cfg.url ) {
			return;
		}
		busy = true;
		setBusy( true );
		var frame = document.createElement( 'iframe' );
		frame.setAttribute( 'aria-hidden', 'true' );
		frame.setAttribute( 'tabindex', '-1' );
		frame.setAttribute( 'title', '' );
		frame.className = 'wpec-cart-fit-frame';
		frame.style.cssText = 'position:fixed;top:0;left:-30000px;width:' + ( parseInt( cfg.width, 10 ) || 1440 ) + 'px;height:900px;border:0;visibility:hidden;pointer-events:none;';
		frame.src = cfg.url;
		document.body.appendChild( frame );
		measure( frame, Date.now(), function( result ) {
			if ( frame.parentNode ) {
				frame.parentNode.removeChild( frame );
			}
			send( result );
		} );
	}

	document.addEventListener( 'click', function( event ) {
		var link = event.target && event.target.closest ? event.target.closest( '.wpec-cart-fit-status a[href="#wpec-cart-fit-check"]' ) : null;
		if ( ! link ) {
			return;
		}
		event.preventDefault();
		check();
	} );

	function start() {
		var list = rows();
		for ( var i = 0; i < list.length; i++ ) {
			if ( '1' === list[ i ].getAttribute( 'data-auto' ) ) {
				check();
				return;
			}
		}
		/* Another EasyCart screen, once for a theme not measured: the recommendation shows from the next screen on. */
		if ( 1 === parseInt( cfg.auto, 10 ) ) {
			setTimeout( check, 1500 );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}

	window.wpecCartFitCheck = check;
}() );
