/**
 * WP EasyCart checkout protection ( 6.0.2 ): the storefront side of the card-testing defence.
 *
 * On any page with a payment step ( classic and one-page checkout, subscription checkout, the pay-link page, a
 * subscription's card form in My Account ) this script asks the store what the step should show:
 *   ok      nothing ( most shoppers never see anything )
 *   check   a short human check ( Cloudflare Turnstile or Google reCAPTCHA v2 ) above Place order; the provider's
 *           script is loaded only now, so shoppers who never need a check never download it
 *   paused  a friendly "payments are paused" notice with the time and the store's email; Place order is disabled
 * It asks again after any AJAX answer that carries the X-WPEC-Protection header, and when Stripe's payment form shows
 * a card error ( the server then looks the payment up with Stripe itself: nothing the browser says is trusted ).
 *
 * Localized as window.wpeasycart_protection by wp_easycart_checkout_guard::enqueue().
 *
 * @since 6.0.2
 */
( function () {
	'use strict';

	var cfg = window.wpeasycart_protection;
	if ( ! cfg || ! cfg.ajax_url || ! window.jQuery ) {
		return;
	}
	var $ = window.jQuery;

	var BUTTONS = '#ec_cart_submit_order, #ec-pay-card-button, #ec_account_subscription_v2_card_save';
	var ERRORS = '#ec_card_errors, #ec_stripe_dynamic_error, #wp-easycart-square-payment-status-container';
	/* 6.0.2: the payment templates' own place for the notice, above the PayPal buttons and Place order. */
	var MOUNTS = '#ec_checkout_protection_notice, .wpec-protect-mount';
	/* The row Place order sits in ( the notice goes above it, at the row's full width, never inside its 25% column ). */
	var ROWS = '#wpeasycart_submit_order_row, .ec_cart_bottom_nav_v2, .ec_cart_button_row';
	/* PayPal's buttons on a payment step: they are frames, so they are covered while a check is owed. */
	var PAYPAL = '#wpeasycart_submit_paypal_order_row, #ec-pay-paypal';
	/* Where the checkout is drawn after the page loads ( the dynamic cart and account ). */
	var HOLDERS = '#wpeasycart_cart_holder, #wpeasycart_account_holder';

	var current = 'ok';
	var box = null;
	var widgetId = null;
	var busy = false;
	var queued = false;
	var providerLoading = false;
	var providerCallbacks = [];

	/* ---------------------------------------------------------------- */
	/* Small helpers                                                    */
	/* ---------------------------------------------------------------- */

	function post( action, data ) {
		data = data || {};
		data.action = 'ec_ajax_checkout_protection_' + action;
		data.nonce = cfg.nonce;
		return $.ajax( { url: cfg.ajax_url, type: 'post', data: data, dataType: 'json', wpecProtection: true } );
	}

	/* 6.0.2: the one-page checkout's single page is one form ( #wpeasycart_checkout_details_form ) that also holds the
	   address step; it is a payment form only when Place order is in it ( the classic address page and the "cart first"
	   screen use the same form id with no payment ). */
	function isPaymentForm( form ) {
		if ( ! form || ! form.id ) {
			return false;
		}
		if ( 'ec_submit_order_form' === form.id ) {
			return true;
		}
		return 'wpeasycart_checkout_details_form' === form.id && !! form.querySelector( '#ec_cart_submit_order' );
	}

	function paymentForm() {
		var form = document.getElementById( 'ec_submit_order_form' );
		if ( form ) {
			return form;
		}
		form = document.getElementById( 'wpeasycart_checkout_details_form' );
		return isPaymentForm( form ) ? form : null;
	}

	function hasPaymentStep() {
		return !! ( document.querySelector( BUTTONS + ', ' + MOUNTS + ', #ec-pay' ) || paymentForm() );
	}

	function visible( node ) {
		return !! node && null !== node.offsetParent;
	}

	/* The notice's look lives in ec-store.css ( 6.0.2, in the store's colours ). A store's own copy of ec-store.css from
	   before 6.0.2 lacks it: then, and only then, a plain fallback is added. */
	function ensureStyles( node ) {
		if ( document.getElementById( 'wpec-protect-css' ) || ! window.getComputedStyle ) {
			return;
		}
		if ( '0px' !== window.getComputedStyle( node ).paddingTop ) {
			return;
		}
		var css = '.wpec-protect{float:left;clear:both;width:100%;box-sizing:border-box;margin:12px 0;padding:14px 16px;border:1px solid rgba(0,0,0,.12);border-radius:8px;font-size:.975rem;line-height:1.5;text-align:left}' +
			'.wpec-protect.is-paused{background:rgb(255,245,204);color:rgb(122,65,0)}' +
			'.wpec-protect-title{display:block;font-weight:600;margin:0 0 2px}' +
			'.wpec-protect-widget{margin-top:10px;min-height:10px;max-width:100%}' +
			'.wpec-protect-error{color:rgb(122,9,22);margin-top:6px}' +
			'.wpec-protect-blocked{opacity:.55;pointer-events:none}';
		var style = document.createElement( 'style' );
		style.id = 'wpec-protect-css';
		style.appendChild( document.createTextNode( css ) );
		document.head.appendChild( style );
	}

	/**
	 * Where the notice goes ( 6.0.2 ): the payment template's own place above the PayPal buttons and Place order; with a
	 * template from before 6.0.2, above the PayPal buttons when PayPal is chosen, else above Place order's whole row.
	 */
	function anchor() {
		var i;
		var mounts = document.querySelectorAll( MOUNTS );
		for ( i = 0; i < mounts.length; i++ ) {
			if ( visible( mounts[ i ] ) ) {
				return { node: mounts[ i ], inside: true };
			}
		}
		var paypal = document.querySelectorAll( PAYPAL );
		for ( i = 0; i < paypal.length; i++ ) {
			if ( visible( paypal[ i ] ) ) {
				return { node: paypal[ i ], before: true };
			}
		}
		var buttons = document.querySelectorAll( BUTTONS );
		for ( i = 0; i < buttons.length; i++ ) {
			if ( visible( buttons[ i ] ) ) {
				var row = buttons[ i ].closest ? buttons[ i ].closest( ROWS ) : null;
				return { node: row || buttons[ i ], before: true };
			}
		}
		/* Nothing on screen yet ( an earlier step of the steps layout ): where it will be when the step opens. */
		if ( mounts.length ) {
			return { node: mounts[ 0 ], inside: true };
		}
		if ( buttons.length ) {
			var hidden_row = buttons[ 0 ].closest ? buttons[ 0 ].closest( ROWS ) : null;
			return { node: hidden_row || buttons[ 0 ], before: true };
		}
		var form = paymentForm();
		return form ? { node: form, before: false } : null;
	}

	/* A blocked Place order is disabled; PayPal's buttons ( frames ) are covered, so they can't be clicked either. */
	function blockNode( node, blocked, is_button ) {
		if ( blocked ) {
			if ( ! node.hasAttribute( 'data-wpec-blocked' ) ) {
				node.setAttribute( 'data-wpec-blocked', ( is_button && node.disabled ) ? '1' : '0' );
			}
			if ( is_button ) {
				node.disabled = true;
			}
			node.classList.add( 'wpec-protect-blocked' );
			node.setAttribute( 'aria-disabled', 'true' );
		} else if ( node.hasAttribute( 'data-wpec-blocked' ) ) {
			if ( is_button ) {
				node.disabled = ( '1' === node.getAttribute( 'data-wpec-blocked' ) );
			}
			node.removeAttribute( 'data-wpec-blocked' );
			node.classList.remove( 'wpec-protect-blocked' );
			node.removeAttribute( 'aria-disabled' );
		}
	}

	function setBlocked( blocked ) {
		var i;
		var buttons = document.querySelectorAll( BUTTONS );
		for ( i = 0; i < buttons.length; i++ ) {
			blockNode( buttons[ i ], blocked, true );
		}
		var paypal = document.querySelectorAll( PAYPAL );
		for ( i = 0; i < paypal.length; i++ ) {
			blockNode( paypal[ i ], blocked, false );
		}
	}

	function removeBox() {
		if ( box && box.parentNode ) {
			box.parentNode.removeChild( box );
		}
		box = null;
		widgetId = null;
	}

	function makeBox( kind, title, message ) {
		removeBox();
		var where = anchor();
		if ( ! where ) {
			return null;
		}
		box = document.createElement( 'div' );
		box.className = 'wpec-protect' + ( 'paused' === kind ? ' is-paused' : '' );
		box.setAttribute( 'role', 'paused' === kind ? 'alert' : 'status' );
		box.setAttribute( 'aria-live', 'polite' );
		var strong = document.createElement( 'span' );
		strong.className = 'wpec-protect-title';
		strong.appendChild( document.createTextNode( title ) );
		box.appendChild( strong );
		var text = document.createElement( 'span' );
		text.className = 'wpec-protect-text';
		text.appendChild( document.createTextNode( message ) );
		box.appendChild( text );
		if ( where.inside ) {
			where.node.appendChild( box );
		} else if ( where.before ) {
			where.node.parentNode.insertBefore( box, where.node );
		} else {
			where.node.insertBefore( box, where.node.firstChild );
		}
		ensureStyles( box );
		return box;
	}

	function showError( message ) {
		if ( ! box ) {
			return;
		}
		var old = box.querySelector( '.wpec-protect-error' );
		if ( old ) {
			old.parentNode.removeChild( old );
		}
		var err = document.createElement( 'div' );
		err.className = 'wpec-protect-error';
		err.appendChild( document.createTextNode( message ) );
		box.appendChild( err );
	}

	/* ---------------------------------------------------------------- */
	/* Check providers ( loaded on demand )                             */
	/* ---------------------------------------------------------------- */

	function providerReady() {
		if ( 'recaptcha' === cfg.provider ) {
			return !! ( window.grecaptcha && window.grecaptcha.render );
		}
		return !! ( window.turnstile && window.turnstile.render );
	}

	function loadProvider( done ) {
		if ( providerReady() ) {
			done();
			return;
		}
		providerCallbacks.push( done );
		if ( providerLoading ) {
			return;
		}
		providerLoading = true;
		var flush = function () {
			var list = providerCallbacks;
			providerCallbacks = [];
			for ( var i = 0; i < list.length; i++ ) {
				list[ i ]();
			}
		};
		/* The store may already load reCAPTCHA for its login forms: wait for that copy instead of adding another. */
		var existing = ( 'recaptcha' === cfg.provider ) ? document.querySelector( 'script[src*="recaptcha/api.js"]' ) : document.querySelector( 'script[src*="challenges.cloudflare.com/turnstile"]' );
		if ( ! existing ) {
			var s = document.createElement( 'script' );
			s.async = true;
			s.defer = true;
			s.src = ( 'recaptcha' === cfg.provider ) ? 'https://www.google.com/recaptcha/api.js?render=explicit' : 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
			document.head.appendChild( s );
		}
		var tries = 0;
		var wait = window.setInterval( function () {
			tries++;
			if ( providerReady() ) {
				window.clearInterval( wait );
				flush();
			} else if ( tries > 100 ) {
				/* 10 seconds: the provider can't be reached. The server lets payments through under stricter limits. */
				window.clearInterval( wait );
				providerCallbacks = [];
				providerLoading = false;
				setBlocked( false );
				removeBox();
			}
		}, 100 );
	}

	function renderWidget() {
		if ( ! box || ! cfg.site_key ) {
			return;
		}
		var holder = document.createElement( 'div' );
		holder.className = 'wpec-protect-widget';
		box.appendChild( holder );
		loadProvider( function () {
			if ( ! box || ! holder.parentNode ) {
				return;
			}
			var options = {
				sitekey: cfg.site_key,
				callback: verify,
				'expired-callback': function () {
					showError( cfg.text.check_failed );
				}
			};
			if ( 'recaptcha' === cfg.provider ) {
				widgetId = window.grecaptcha.render( holder, options );
			} else {
				options[ 'error-callback' ] = function () {
					showError( cfg.text.check_failed );
				};
				widgetId = window.turnstile.render( holder, options );
			}
		} );
	}

	function resetWidget() {
		try {
			if ( 'recaptcha' === cfg.provider && window.grecaptcha && null !== widgetId ) {
				window.grecaptcha.reset( widgetId );
			} else if ( window.turnstile && null !== widgetId ) {
				window.turnstile.reset( widgetId );
			}
		} catch ( e ) {
			/* nothing to reset */
		}
	}

	function verify( token ) {
		showError( cfg.text.checking );
		post( 'verify', { token: token } ).done( function ( res ) {
			if ( res && res.success ) {
				if ( res.data && res.data.reload ) {
					/* Stripe's payment form was held back until now: draw the step again. */
					window.location.reload();
					return;
				}
				current = 'ok';
				removeBox();
				setBlocked( false );
			} else {
				showError( ( res && res.data && res.data.message ) ? res.data.message : cfg.text.check_failed );
				resetWidget();
			}
		} ).fail( function () {
			showError( cfg.text.check_failed );
			resetWidget();
		} );
	}

	/* ---------------------------------------------------------------- */
	/* State                                                            */
	/* ---------------------------------------------------------------- */

	function apply( state ) {
		if ( ! state || ! state.state ) {
			return;
		}
		if ( 'paused' === state.state ) {
			current = 'paused';
			makeBox( 'paused', cfg.text.paused_title, state.message || '' );
			setBlocked( true );
		} else if ( 'check' === state.state && cfg.site_key ) {
			if ( 'check' === current && box && box.parentNode ) {
				return; /* already showing */
			}
			current = 'check';
			if ( makeBox( 'check', cfg.text.check_title, cfg.text.check_needed ) ) {
				renderWidget();
				setBlocked( true );
			}
		} else {
			current = 'ok';
			removeBox();
			setBlocked( false );
		}
	}

	function refresh() {
		if ( busy ) {
			queued = true;
			return;
		}
		if ( ! hasPaymentStep() ) {
			return;
		}
		busy = true;
		post( 'state' ).done( function ( res ) {
			if ( res && res.success ) {
				apply( res.data );
			}
		} ).always( function () {
			busy = false;
			if ( queued ) {
				queued = false;
				refresh();
			}
		} );
	}

	/* ---------------------------------------------------------------- */
	/* Watching the page                                                */
	/* ---------------------------------------------------------------- */

	/* The server tags an AJAX answer when this shopper's state just changed ( a decline, a pause, a check needed ). */
	$( document ).ajaxComplete( function ( event, xhr, settings ) {
		if ( settings && settings.wpecProtection ) {
			return;
		}
		var header = xhr && xhr.getResponseHeader ? xhr.getResponseHeader( 'X-WPEC-Protection' ) : null;
		if ( header ) {
			refresh();
		}
	} );

	/* The pay-link page talks to the store with fetch(): watch its answers for the same header. */
	if ( window.fetch ) {
		var realFetch = window.fetch;
		window.fetch = function () {
			/* 6.0.2: called on window, as the real fetch needs ( a caller's own `this` threw "Illegal invocation" ). */
			return realFetch.apply( window, arguments ).then( function ( response ) {
				try {
					if ( response && response.headers && response.headers.get( 'X-WPEC-Protection' ) ) {
						refresh();
					}
				} catch ( e ) {
					/* a response without readable headers: nothing to do */
				}
				return response;
			} );
		};
	}

	/* Stripe shows card errors in the page; ask the server to look the payment up with Stripe and count it. */
	var lastError = '';
	function watchErrors() {
		var nodes = document.querySelectorAll( ERRORS );
		for ( var i = 0; i < nodes.length; i++ ) {
			if ( nodes[ i ].getAttribute( 'data-wpec-watched' ) ) {
				continue;
			}
			nodes[ i ].setAttribute( 'data-wpec-watched', '1' );
			new MutationObserver( onErrorChange ).observe( nodes[ i ], { childList: true, characterData: true, subtree: true } );
		}
	}
	var reportTimer = null;
	function onErrorChange( mutations ) {
		var node = mutations && mutations[ 0 ] ? mutations[ 0 ].target : null;
		while ( node && 1 !== node.nodeType ) {
			node = node.parentNode;
		}
		var text = node ? ( node.textContent || '' ).trim() : '';
		if ( '' === text || text === lastError || text === cfg.text.declined ) {
			return;
		}
		lastError = text;
		window.clearTimeout( reportTimer );
		reportTimer = window.setTimeout( function () {
			post( 'report' ).done( function ( res ) {
				if ( res && res.success ) {
					if ( cfg.simple && res.data && res.data.declined ) {
						/* Show the simple decline wording instead of the bank's reason. */
						var nodes = document.querySelectorAll( ERRORS );
						for ( var i = 0; i < nodes.length; i++ ) {
							if ( ( nodes[ i ].textContent || '' ).trim() === lastError ) {
								var target = nodes[ i ].querySelector( 'div' ) || nodes[ i ];
								lastError = cfg.text.declined;
								target.textContent = cfg.text.declined;
							}
						}
					}
					apply( res.data );
				}
			} );
		}, 400 );
	}

	/* A blocked Place order must not run through another route: a page script re-enabling the button, the Enter key,
	   or a theme's own button. */
	document.addEventListener( 'click', function ( e ) {
		if ( 'ok' === current || ! e.target || ! e.target.closest || ! e.target.closest( BUTTONS ) ) {
			return;
		}
		e.preventDefault();
		e.stopImmediatePropagation();
		if ( box && box.scrollIntoView ) {
			box.scrollIntoView( { behavior: 'smooth', block: 'center' } );
		}
	}, true );
	document.addEventListener( 'submit', function ( e ) {
		if ( 'ok' === current || ! isPaymentForm( e.target ) ) {
			return;
		}
		e.preventDefault();
		e.stopImmediatePropagation();
		if ( box && box.scrollIntoView ) {
			box.scrollIntoView( { behavior: 'smooth', block: 'center' } );
		}
	}, true );

	/* The checkout is often drawn after the page loads ( the dynamic cart, one-page section redraws ). */
	var seenStep = false;
	var scanTimer = null;
	function scan() {
		watchErrors();
		var present = hasPaymentStep();
		if ( present && ( ! seenStep || ( current !== 'ok' && ( ! box || ! box.parentNode ) ) ) ) {
			/* First sight of the payment step, or it was redrawn and took the notice with it: ask again. */
			seenStep = true;
			refresh();
		} else if ( present && current !== 'ok' ) {
			setBlocked( true );
		}
		if ( ! present ) {
			seenStep = false;
		}
	}
	function start() {
		scan();
		/* 6.0.2: watched only where a payment step is, or can be drawn later ( the dynamic cart and account ); a cart,
		   account or product page with neither is left alone. */
		if ( window.MutationObserver && document.body && ( hasPaymentStep() || document.querySelector( HOLDERS ) ) ) {
			new MutationObserver( function () {
				window.clearTimeout( scanTimer );
				scanTimer = window.setTimeout( scan, 150 );
			} ).observe( document.body, { childList: true, subtree: true } );
		}
	}
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
} )();
