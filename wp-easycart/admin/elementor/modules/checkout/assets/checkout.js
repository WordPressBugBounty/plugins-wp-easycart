/**
 * WP EasyCart for Elementor: Cart, Checkout, Order Summary and Order Confirmation widgets ( 6.0.2 ).
 *
 * 1. Later requests of the cart page ( ec_ajax_* ) carry the ids of the widget that drew it ( wpec_el_doc, wpec_el_ids ), so
 *    the server applies that widget's settings where it draws parts again ( text between sections, the order confirmation
 *    additions ). Only ids travel.
 * 2. The empty cart: when EasyCart's cart page says the cart is empty ( .ec_cart_empty, also after it loads by AJAX ), the
 *    widget shows its own empty state instead.
 * 3. A Cart widget away from the cart page on a store with cache prevention asks for the cart after the page loads.
 * 4. The Order Summary follows the cart page: after anything the cart page changes, and once its requests are done, it asks
 *    for the summary again.
 * 5. Round 11: the * after a required field's label gets its own element ( .wpec-required, for its colour ), product names
 *    that should not link leave the tab order, and a Cart widget away from the cart page is drawn again when a product is
 *    added elsewhere on the page.
 *
 * Nothing here takes part in placing an order: EasyCart's own scripts do that, unchanged.
 */
( function( $, window, document ) {
	'use strict';

	if ( ! $ ) {
		return;
	}

	var config = window.wpeasycart_elementor_checkout || {};
	var ajaxUrl = config.ajax_url || ( window.wpeasycart_ajax_object ? window.wpeasycart_ajax_object.ajax_url : '' );
	var OWN_ACTION = 'wp_easycart_elementor_cart';

	function language() {
		return ( window.wpeasycart_ajax_object && window.wpeasycart_ajax_object.current_language ) ? window.wpeasycart_ajax_object.current_language : '';
	}

	/* 1. Request context --------------------------------------------------------------------------------------------- */

	var context = null;
	function readContext() {
		var doc = '';
		var ids = [];
		$( '.wpec-el-cart-page[data-wpec-el-id]' ).each( function() {
			var id = String( this.getAttribute( 'data-wpec-el-id' ) || '' ).replace( /[^a-z0-9]/gi, '' );
			var d = String( this.getAttribute( 'data-wpec-el-doc' ) || '' ).replace( /[^0-9]/g, '' );
			if ( id && d && ( ! doc || doc === d ) ) {
				doc = d;
				ids.push( id );
			}
		} );
		return ( doc && ids.length ) ? { doc: doc, ids: ids.slice( 0, 8 ).join( ',' ) } : null;
	}

	if ( 'function' === typeof $.ajaxPrefilter ) {
		$.ajaxPrefilter( function( options ) {
			/* String data only ( jQuery serialises objects before prefilters run ); FormData and fetch() are left alone. */
			if ( 'string' !== typeof options.data || ! /(^|&)action=ec_ajax_/.test( options.data ) || /(^|&)wpec_el_doc=/.test( options.data ) ) {
				return;
			}
			if ( null === context ) {
				context = readContext() || false;
			}
			if ( context ) {
				options.data += '&wpec_el_doc=' + encodeURIComponent( context.doc ) + '&wpec_el_ids=' + encodeURIComponent( context.ids );
			}
		} );
	}

	/* 2. Empty cart ----------------------------------------------------------------------------------------------------- */

	function syncEmpty( root ) {
		var $root = $( root );
		var $own = $root.children( '.wpec-cart-empty' );
		if ( ! $own.length ) {
			return;
		}
		var empty = $root.find( '.ec_cart_empty' ).length > 0;
		$root.toggleClass( 'wpec-cart--empty', empty );
		if ( empty ) {
			$own.removeAttr( 'hidden' );
		} else {
			$own.attr( 'hidden', 'hidden' );
		}
	}

	/* 5. The required mark and product names ( round 11 ). */

	function decorate( root ) {
		$( root ).find( '.ec_cart_input_row label, .ec_cart_input_row_flex label' ).each( function() {
			var last = this.lastChild;
			if ( ! last || 3 !== last.nodeType || $( this ).children( '.wpec-required' ).length ) {
				return;
			}
			var text = String( last.nodeValue );
			var trimmed = text.replace( /\s+$/, '' );
			if ( '*' !== trimmed.slice( -1 ) ) {
				return;
			}
			last.nodeValue = trimmed.slice( 0, -1 );
			var mark = document.createElement( 'span' );
			mark.className = 'wpec-required';
			mark.textContent = '*';
			this.appendChild( mark );
		} );
		$( root ).closest( '.wpec-cart--no-title-link' ).addBack( '.wpec-cart--no-title-link' ).find( '.ec_cartitem_title' ).attr( { tabindex: '-1', 'aria-disabled': 'true' } );
	}

	function watchFlow( root ) {
		if ( root.wpecFlowWatched ) {
			return;
		}
		root.wpecFlowWatched = true;
		syncEmpty( root );
		decorate( root );
		if ( 'function' === typeof window.MutationObserver ) {
			var timer = null;
			new window.MutationObserver( function() {
				window.clearTimeout( timer );
				timer = window.setTimeout( function() {
					syncEmpty( root );
					decorate( root );
				}, 50 );
			} ).observe( root, { childList: true, subtree: true } );
		}
	}

	/* 3. A Cart widget away from the cart page, drawn after the page loads ---------------------------------------------------- */

	function loadCart( root ) {
		if ( root.wpecCartLoading || ! ajaxUrl ) {
			return;
		}
		root.wpecCartLoading = true;
		var $root = $( root );
		var data = { action: OWN_ACTION, view: 'cart', language: language() };
		/* The widget's own texts ( round 11 ): only its saved ids travel, as with the cart page's requests. */
		var id = String( root.getAttribute( 'data-wpec-el-id' ) || '' ).replace( /[^a-z0-9]/gi, '' );
		var doc = String( root.getAttribute( 'data-wpec-el-doc' ) || '' ).replace( /[^0-9]/g, '' );
		if ( id && doc ) {
			data.wpec_el_doc = doc;
			data.wpec_el_ids = id;
		}
		$.ajax( {
			url: ajaxUrl,
			type: 'post',
			dataType: 'json',
			cache: false,
			data: data
		} ).done( function( answer ) {
			$root.find( '.wpec-cart-loading' ).remove();
			root.wpecCartCount = ( answer && 'undefined' !== typeof answer.count ) ? Number( answer.count ) : null;
			if ( answer && answer.count > 0 && answer.html ) {
				$root.removeClass( 'wpec-cart--empty' );
				$root.children( '.wpec-cart-contents' ).remove();
				var $contents = $( '<div class="wpec-cart-contents"></div>' );
				$root.prepend( $contents );
				$contents.html( answer.html ); /* EasyCart's own cart markup ( its scripts run, as when the cart page loads it ) */
				$root.children( '.wpec-cart-empty' ).attr( 'hidden', 'hidden' );
				decorate( root );
				if ( 'function' === typeof window.wpeasycart_init ) {
					try {
						window.wpeasycart_init( $contents.get( 0 ) );
					} catch ( e ) {}
				}
			} else {
				$root.addClass( 'wpec-cart--empty' );
				$root.children( '.wpec-cart-contents' ).remove();
				$root.children( '.wpec-cart-empty' ).removeAttr( 'hidden' );
			}
		} ).fail( function() {
			$root.find( '.wpec-cart-loading' ).remove();
			if ( ! $root.children( '.wpec-cart-contents' ).length ) {
				$root.children( '.wpec-cart-empty' ).removeAttr( 'hidden' );
			}
		} ).always( function() {
			root.wpecCartLoading = false;
		} );
	}

	/*
	 * A product added elsewhere on the page ( an Add to Cart widget, a quick view ): the Cart widget away from the cart page
	 * shows it ( round 11: it stayed as the page drew it ). Its own changes ( quantities, remove ) update it in place.
	 */
	function offpageCarts() {
		return $( '[data-wpec-cart-offpage]' ).filter( function() {
			return ! $( this ).closest( '.elementor-editor-active' ).length;
		} );
	}
	$( document ).on( 'wpeasycart_item_added', function() {
		offpageCarts().each( function() {
			loadCart( this );
		} );
	} );
	$( document ).on( 'wpeasycart_cart_changed', function( e, cart ) {
		if ( ! cart || 'undefined' === typeof cart.count ) {
			return;
		}
		offpageCarts().each( function() {
			var shown = ( 'number' === typeof this.wpecCartCount ) ? this.wpecCartCount : ( $( this ).hasClass( 'wpec-cart--empty' ) ? 0 : null );
			/* An empty cart that now has products, or products that went elsewhere ( another tab ): draw it again. */
			if ( null !== shown && ( ( 0 === shown ) !== ( Number( cart.count ) <= 0 ) ) ) {
				loadCart( this );
			}
		} );
	} );

	/* 4. Order Summary ---------------------------------------------------------------------------------------------------- */

	var summaryTimer = null;
	var summaryRequest = null;
	var summaryPending = false;

	function refreshSummaries() {
		var $summaries = $( '[data-wpec-summary]' );
		if ( ! $summaries.length || ! ajaxUrl ) {
			return;
		}
		if ( summaryRequest ) {
			summaryRequest.wpecAgain = true;
			return;
		}
		$summaries.addClass( 'is-updating' ).attr( 'aria-busy', 'true' );
		summaryRequest = $.ajax( {
			url: ajaxUrl,
			type: 'post',
			dataType: 'json',
			cache: false,
			data: { action: OWN_ACTION, view: 'summary', language: language() }
		} );
		var request = summaryRequest;
		request.done( function( answer ) {
			if ( ! answer || 'object' !== typeof answer ) {
				return;
			}
			$summaries.each( function() {
				var $summary = $( this );
				var $message = $summary.children( '.wpec-summary__empty' );
				if ( answer.count > 0 && answer.html ) {
					$summary.find( '.wpec-summary__body' ).html( answer.html );
					$message.attr( 'hidden', 'hidden' );
					$summary.children( '.wpec-summary__edit-row' ).removeAttr( 'hidden' );
					$summary.removeAttr( 'hidden' );
				} else if ( $message.length ) {
					/* Round 11: a summary set to say the cart is empty says so instead of going away. */
					$summary.find( '.wpec-summary__body' ).empty();
					$message.removeAttr( 'hidden' );
					$summary.children( '.wpec-summary__edit-row' ).attr( 'hidden', 'hidden' );
					$summary.removeAttr( 'hidden' );
				} else {
					$summary.attr( 'hidden', 'hidden' );
				}
			} );
		} ).always( function() {
			$summaries.removeClass( 'is-updating' ).removeAttr( 'aria-busy' );
			summaryRequest = null;
			if ( request.wpecAgain ) {
				summaryWanted();
			}
		} );
	}

	/*
	 * The summary is asked for only once the page's own requests are done ( jQuery's ajaxStop, then a short pause ), never
	 * beside them: the one-page checkout saves one request after another, each rewriting the whole checkout session, and a
	 * summary drawn in between would work out shipping and tax on the same session at the same time.
	 */
	function refreshSummariesSoon() {
		window.clearTimeout( summaryTimer );
		summaryTimer = window.setTimeout( function() {
			if ( $.active > 0 ) {
				return; /* ajaxStop brings it back */
			}
			if ( summaryPending ) {
				summaryPending = false;
				refreshSummaries();
			}
		}, 300 );
	}

	function summaryWanted() {
		if ( ! $( '[data-wpec-summary]' ).length ) {
			return;
		}
		summaryPending = true;
		if ( ! ( $.active > 0 ) ) {
			refreshSummariesSoon();
		}
	}

	/* The cart page changed the cart, shipping, tax or a code: every ec_ajax_* answer ( but the cart state read ). */
	$( document ).on( 'ajaxComplete', function( event, xhr, settings ) {
		var data = ( settings && 'string' === typeof settings.data ) ? settings.data : '';
		if ( ! /(^|&)action=ec_ajax_/.test( data ) || /(^|&)action=ec_ajax_(cart_state|get_dynamic_cart_menu|get_dynamic_cart_total)(&|$)/.test( data ) ) {
			return;
		}
		if ( $( '[data-wpec-summary]' ).length ) {
			summaryPending = true;
		}
	} );
	$( document ).on( 'ajaxStop', function() {
		if ( summaryPending ) {
			refreshSummariesSoon();
		}
	} );
	$( document ).on( 'wpeasycart_cart_updated wpeasycart_cart_changed', summaryWanted );

	/* Start ----------------------------------------------------------------------------------------------------------------- */

	function init( container ) {
		var $scope = $( container || document );
		$scope.find( '.wpec-el-cart-page' ).addBack( '.wpec-el-cart-page' ).each( function() {
			decorate( this );
		} );
		$scope.find( '[data-wpec-cart-flow]' ).addBack( '[data-wpec-cart-flow]' ).each( function() {
			watchFlow( this );
		} );
		$scope.find( '[data-wpec-cart-load]' ).addBack( '[data-wpec-cart-load]' ).each( function() {
			loadCart( this );
		} );
		if ( $scope.find( '[data-wpec-summary-load]' ).addBack( '[data-wpec-summary-load]' ).length ) {
			summaryWanted();
		}
		context = null; /* read again: a widget may have just been drawn */
	}

	$( function() {
		init( document );
	} );

	$( window ).on( 'elementor/frontend/init', function() {
		if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
			[ 'wp_easycart_cart', 'wp_easycart_checkout', 'wp_easycart_order_summary', 'wp_easycart_thank_you' ].forEach( function( name ) {
				window.elementorFrontend.hooks.addAction( 'frontend/element_ready/' + name + '.default', function( $element ) {
					init( $element && $element.get ? $element.get( 0 ) : null );
				} );
			} );
		}
	} );
}( window.jQuery, window, document ) );
