/**
 * WP EasyCart Elementor dynamic data ( 6.0.2 ): the live cart tags and the "added to your cart" note.
 *
 * - [data-wpec-cart="count"] / [data-wpec-cart="subtotal"] ( the Cart count and Cart subtotal tags ) follow the cart. They
 *   carry data-wpec-cart-widget, so the store script ( ec-store.js ) asks for the cart once the page is ready ( a page cache
 *   may have served it ) and after every cart change, and fires jQuery 'wpeasycart_cart_changed' ( cart = { count,
 *   subtotal_display, items } ), which this script applies. On a page without the store script, this script asks the
 *   uncached ec_ajax_cart_state request itself.
 * - [data-wpec-dyn-added] ( printed after an add to cart link with "stay on the page" ): a close button, Escape, and the
 *   wpec_added argument removed from the address bar so a reload or a shared link carries nothing.
 */
( function () {
	'use strict';

	var config = window.wpeasycart_elementor_dynamic || {};
	var listening = false;

	function applyCart( cart ) {
		if ( ! cart || 'object' !== typeof cart ) {
			return;
		}
		var nodes = document.querySelectorAll( '[data-wpec-cart]' );
		for ( var i = 0; i < nodes.length; i++ ) {
			var key = nodes[ i ].getAttribute( 'data-wpec-cart' );
			if ( 'count' === key && 'undefined' !== typeof cart.count ) {
				nodes[ i ].textContent = String( parseInt( cart.count, 10 ) || 0 );
			} else if ( 'subtotal' === key && 'string' === typeof cart.subtotal_display ) {
				nodes[ i ].textContent = cart.subtotal_display;
			}
		}
	}

	// Bound as early as possible: the store script's first refresh may answer right after the page is ready.
	function listen() {
		if ( listening || ! window.jQuery ) {
			return;
		}
		listening = true;
		window.jQuery( document ).on( 'wpeasycart_cart_changed', function ( event, cart ) {
			applyCart( cart );
		} );
	}

	function fetchCart() {
		if ( 'function' === typeof window.wpeasycart_refresh_cart_widgets ) {
			return; // The store script refreshes [data-wpec-cart-widget] elements itself.
		}
		if ( ! config.ajax_url || ! window.fetch || ! window.URLSearchParams ) {
			return;
		}
		var body = new window.URLSearchParams();
		body.append( 'action', 'ec_ajax_cart_state' );
		window.fetch( config.ajax_url, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( response ) {
				return response.ok ? response.json() : null;
			} )
			.then( applyCart )
			.catch( function () {} );
	}

	function initNote() {
		var note = document.querySelector( '[data-wpec-dyn-added]' );
		if ( ! note ) {
			return;
		}
		var onKey;
		var hide = function () {
			if ( note.parentNode ) {
				note.parentNode.removeChild( note );
			}
			document.removeEventListener( 'keydown', onKey );
		};
		onKey = function ( event ) {
			if ( 'Escape' === event.key ) {
				hide();
			}
		};
		var close = note.querySelector( '[data-wpec-dyn-close]' );
		if ( close ) {
			close.addEventListener( 'click', hide );
		}
		document.addEventListener( 'keydown', onKey );
		if ( window.history && window.history.replaceState && window.URL ) {
			try {
				var url = new window.URL( window.location.href );
				url.searchParams.delete( 'wpec_added' );
				window.history.replaceState( window.history.state, '', url.toString() );
			} catch ( error ) {
				// The address stays as it is.
			}
		}
	}

	function ready( callback ) {
		if ( 'loading' !== document.readyState ) {
			callback();
		} else {
			document.addEventListener( 'DOMContentLoaded', callback );
		}
	}

	listen();
	ready( function () {
		listen();
		initNote();
		if ( document.querySelector( '[data-wpec-cart]' ) ) {
			fetchCart();
		}
	} );
}() );
