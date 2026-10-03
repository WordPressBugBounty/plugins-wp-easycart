/**
 * WP EasyCart checkout: Place order's busy state ( 6.0.2 ).
 *
 * From the moment Place order is accepted until the page moves on, or a problem brings the button back, the checkout is
 * locked: a status card over the page says what is happening ( checking the order, processing the payment … ) and the
 * checkout's columns are inert, so nothing in them can be clicked, typed or changed, and a second click or Enter does nothing.
 *
 *  - The one-page checkout ( ec-checkout-onepage.js ) locks as soon as Place order is clicked, before its saves and the
 *    store's check, and moves the status on as it goes ( lock( 'checking' ), status( 'payment' ) … ).
 *  - Payment scripts say when an order stops by showing #ec_cart_submit_order again ( a decline, a cancelled 3-D Secure
 *    check, a network failure … ): a lock taken while that button was hidden ends when it shows again, when the checkout is
 *    drawn again, or when checkout protection blocks it ( a human check is owed ).
 *  - The classic payment step gets the same card, without any change to its flow: after Place order, when a payment
 *    script's cover shows ( Stripe, Square, bank transfer ), the step's form posts on its own, or Braintree takes over.
 *  - The payment services' own windows ( Stripe's 3-D Secure, Square's verification, PayPal, Apple Pay / Google Pay ) open
 *    on the page body above the card ( the card sits under the covers' old z-index ), so they stay usable. The older dark
 *    covers are hidden while the card shows.
 *
 * API: window.wpeasycart_checkout_busy.lock( step, options ), .status( step ), .unlock(), .locked(), .restore()
 * Steps: checking | payment | finishing. Wording: window.wpeasycart_checkout_busy_text ( localized ), English fallback.
 *
 * @since 6.0.2
 */
( function( window, document ) {
	'use strict';

	if ( window.wpeasycart_checkout_busy || ! document.documentElement ) {
		return;
	}

	var FALLBACK = {
		title: 'Placing your order',
		checking: 'Checking your order…',
		payment: 'Processing your payment…',
		finishing: 'Completing your order…',
		slow: 'This is taking a little longer than usual.',
		hint: 'Please keep this page open.'
	};
	var STEPS = [ 'checking', 'payment', 'finishing' ];
	var SLOW_AFTER = 25000;
	var WATCH_EVERY = 200;
	var BUTTON = 'ec_cart_submit_order';
	var WORKING = 'ec_cart_submit_order_working';
	/* The checkout's parts beside the column that holds Place order ( both layouts, both checkouts ). */
	var PARTS = '.ec_cart_left, .ec_cart_right, .ec_cart_mobile_summary, .ec_cart_breadcrumbs_v2';
	/* The payment scripts' older covers: shown means a payment script took the order over. */
	var COVERS = [ 'stripe-success-cover', 'square-success-cover', 'manual-success-cover' ];
	var LOCKED_CLASS = 'wpec-checkout-locked';
	var BUSY_CLASS = 'wpec-checkout-busy';
	/* The card's look lives in ec-store.css; a store's own copy of ec-store.css from before 6.0.2 gets this instead. */
	var FALLBACK_CSS = '.wpec-busy{position:fixed;top:0;right:0;bottom:0;left:0;z-index:999990;display:flex;align-items:center;justify-content:center;padding:16px;box-sizing:border-box;background:rgba(17,24,39,.42);-webkit-backdrop-filter:blur(3px);backdrop-filter:blur(3px);cursor:progress;animation:wpec-busy-in .16s ease-out}' +
		'.wpec-busy[hidden]{display:none!important}' +
		'.wpec-busy-card{display:flex;flex-direction:column;align-items:center;width:340px;max-width:100%;box-sizing:border-box;padding:28px 28px 24px;border-radius:16px;background:#fff;color:#111827;box-shadow:0 24px 60px rgba(15,23,42,.28),0 2px 6px rgba(15,23,42,.12);font-family:var(--wpec-font-main,inherit);text-align:center;outline:none}' +
		'.wpec-busy-spinner{display:block;width:44px;height:44px;margin:0 0 16px;box-sizing:border-box;border-radius:50%;border:3px solid rgba(17,24,39,.1);border-top-color:var(--wpec-main-color,#222);animation:wpec-busy-spin .75s linear infinite}' +
		'.wpec-busy-title{display:block;margin:0 0 4px;font-size:17px;font-weight:600;line-height:1.35;text-transform:none;letter-spacing:0}' +
		'.wpec-busy-live{display:block;width:100%}' +
		'.wpec-busy-status{display:block;min-height:1.45em;font-size:15px;line-height:1.45;color:#374151}' +
		'.wpec-busy-hint{display:block;margin-top:6px;font-size:13px;line-height:1.45;color:#6b7280}' +
		'.wpec-checkout-locked{pointer-events:none;-webkit-user-select:none;user-select:none}' +
		'html.wpec-checkout-busy #stripe-success-cover,html.wpec-checkout-busy #square-success-cover,html.wpec-checkout-busy #manual-success-cover,html.wpec-checkout-busy [id^="paypal-success-cover"]{display:none!important}' +
		'@keyframes wpec-busy-spin{to{transform:rotate(360deg)}}' +
		'@keyframes wpec-busy-in{from{opacity:0}to{opacity:1}}' +
		'@keyframes wpec-busy-pulse{0%,100%{opacity:1}50%{opacity:.35}}' +
		'@media (prefers-reduced-motion:reduce){.wpec-busy{animation:none}.wpec-busy-spinner{animation:wpec-busy-pulse 2s ease-in-out infinite}}';

	var has_inert = !! ( window.HTMLElement && 'inert' in window.HTMLElement.prototype );
	var state = { locked: false, step: '', regions: [], button: null, watch: false, focus: null, move_focus: true, timer: 0, slow: 0 };
	var overlay = null;
	var card = null;
	var status_line = null;
	var hint_line = null;

	function phrase( key ) {
		var text = window.wpeasycart_checkout_busy_text;
		var value = ( text && 'string' === typeof text[ key ] ) ? text[ key ] : '';
		return ( '' !== value ) ? value : ( FALLBACK[ key ] || '' );
	}

	function place_button() {
		return document.getElementById( BUTTON );
	}

	function is_shown( node ) {
		return !! node && 'none' !== node.style.display;
	}

	function visible( node ) {
		return !! node && null !== node.offsetParent;
	}

	function span( class_name, id ) {
		var node = document.createElement( 'span' );
		node.className = class_name;
		if ( id ) {
			node.id = id;
		}
		return node;
	}

	function ensure_styles() {
		if ( document.getElementById( 'wpec-busy-css' ) || ! window.getComputedStyle || ! overlay ) {
			return;
		}
		if ( 'fixed' === window.getComputedStyle( overlay ).position ) {
			return;
		}
		var style = document.createElement( 'style' );
		style.id = 'wpec-busy-css';
		style.appendChild( document.createTextNode( FALLBACK_CSS ) );
		( document.head || document.documentElement ).appendChild( style );
	}

	/* The card, made once and kept on the page body ( outside every locked part, so it is never inert itself ). */
	function build() {
		if ( overlay && overlay.parentNode ) {
			return;
		}
		overlay = document.createElement( 'div' );
		overlay.className = 'wpec-busy';
		overlay.id = 'wpeasycart_checkout_busy';
		overlay.hidden = true;
		card = document.createElement( 'div' );
		card.className = 'wpec-busy-card';
		card.setAttribute( 'role', 'dialog' );
		card.setAttribute( 'aria-modal', 'true' );
		card.setAttribute( 'aria-labelledby', 'wpeasycart_checkout_busy_title' );
		card.setAttribute( 'aria-describedby', 'wpeasycart_checkout_busy_status' );
		card.tabIndex = -1;
		var spinner = span( 'wpec-busy-spinner' );
		spinner.setAttribute( 'aria-hidden', 'true' );
		var title = span( 'wpec-busy-title', 'wpeasycart_checkout_busy_title' );
		title.appendChild( document.createTextNode( phrase( 'title' ) ) );
		var live = document.createElement( 'div' );
		live.className = 'wpec-busy-live';
		live.setAttribute( 'role', 'status' );
		live.setAttribute( 'aria-live', 'polite' );
		status_line = span( 'wpec-busy-status', 'wpeasycart_checkout_busy_status' );
		hint_line = span( 'wpec-busy-hint' );
		live.appendChild( status_line );
		live.appendChild( hint_line );
		card.appendChild( spinner );
		card.appendChild( title );
		card.appendChild( live );
		overlay.appendChild( card );
		( document.body || document.documentElement ).appendChild( overlay );
		ensure_styles();
	}

	/* The column that holds Place order ( the one-page checkout's .ec_cart_left, the classic step's form ) and the checkout's
	   other parts beside it: the order summary, the mobile summary, the steps. Without a Place order, the checkout's form. */
	function regions_for( anchor ) {
		var top = null;
		var node = anchor;
		while ( node && 1 === node.nodeType && node !== document.body && node !== document.documentElement ) {
			if ( node.matches && node.matches( '.ec_cart_left, form' ) ) {
				top = node;
			}
			if ( node.classList && node.classList.contains( 'ec_cart_page' ) ) {
				break;
			}
			node = node.parentNode;
		}
		if ( ! top ) {
			return anchor && anchor.matches && anchor.matches( 'form' ) ? [ anchor ] : [];
		}
		var list = [ top ];
		var parent = top.parentNode;
		if ( parent && parent !== document.body && parent !== document.documentElement && parent.children ) {
			for ( var i = 0; i < parent.children.length; i++ ) {
				var part = parent.children[ i ];
				if ( part !== top && part !== overlay && part.matches && part.matches( PARTS ) ) {
					list.push( part );
				}
			}
		}
		return list;
	}

	function checkout_anchor() {
		return place_button() || document.getElementById( 'wpeasycart_checkout_details_form' ) || document.getElementById( 'ec_submit_order_form' );
	}

	function set_hint( slow ) {
		if ( ! hint_line ) {
			return;
		}
		hint_line.textContent = slow ? phrase( 'slow' ) + ' ' + phrase( 'hint' ) : phrase( 'hint' );
	}

	function arm_slow() {
		window.clearTimeout( state.slow );
		state.slow = window.setTimeout( function() {
			if ( state.locked ) {
				set_hint( true );
			}
		}, SLOW_AFTER );
	}

	/* What the card says is happening: checking | payment | finishing. */
	function status( step ) {
		if ( ! state.locked || ! status_line || -1 === STEPS.indexOf( step ) ) {
			return;
		}
		if ( step !== state.step ) {
			state.step = step;
			status_line.textContent = phrase( step );
			set_hint( false );
			arm_slow();
		}
	}

	function focus_card() {
		if ( ! card ) {
			return;
		}
		try {
			card.focus( { preventScroll: true } );
		} catch ( e ) {
			card.focus();
		}
	}

	/**
	 * Lock the checkout.
	 *
	 * @param {string} step    checking | payment | finishing.
	 * @param {Object} options focus: false leaves the focus where it is ( a payment service's own window may have it ).
	 */
	function lock( step, options ) {
		options = options || {};
		if ( state.locked ) {
			status( step );
			return;
		}
		build();
		var button = place_button();
		state.locked = true;
		state.step = '';
		state.button = button;
		/* Place order hidden: the lock ends when a script shows it again ( the payment stopped ). */
		state.watch = !! button && ! is_shown( button );
		state.focus = document.activeElement;
		state.move_focus = ( false !== options.focus );
		state.regions = regions_for( checkout_anchor() );
		for ( var i = 0; i < state.regions.length; i++ ) {
			var region = state.regions[ i ];
			region.classList.add( LOCKED_CLASS );
			region.setAttribute( 'aria-busy', 'true' );
			if ( has_inert && ! region.inert ) {
				region.inert = true;
				region.setAttribute( 'data-wpec-busy-inert', '1' );
			}
		}
		document.documentElement.classList.add( BUSY_CLASS );
		overlay.hidden = false;
		status( step || 'checking' );
		if ( state.move_focus ) {
			focus_card();
		}
		window.clearInterval( state.timer );
		state.timer = window.setInterval( watch, WATCH_EVERY );
	}

	/* The focus goes back to Place order ( or where it was ), unless something already took it ( a field with a problem ). */
	function give_focus_back() {
		var active = document.activeElement;
		if ( ! state.move_focus ) {
			return; /* the lock never took the focus ( a payment service's own window has it ) */
		}
		if ( active && active !== document.body && active !== document.documentElement && ! ( overlay && overlay.contains( active ) ) ) {
			return;
		}
		var target = place_button();
		if ( ! visible( target ) ) {
			target = ( state.focus && state.focus.focus && document.documentElement.contains( state.focus ) && visible( state.focus ) ) ? state.focus : null;
		}
		if ( ! target ) {
			if ( active && overlay && overlay.contains( active ) && active.blur ) {
				active.blur();
			}
			return;
		}
		try {
			target.focus( { preventScroll: true } );
		} catch ( e ) {
			target.focus();
		}
	}

	function unlock() {
		if ( ! state.locked ) {
			return;
		}
		state.locked = false;
		window.clearInterval( state.timer );
		window.clearTimeout( state.slow );
		for ( var i = 0; i < state.regions.length; i++ ) {
			var region = state.regions[ i ];
			region.classList.remove( LOCKED_CLASS );
			region.removeAttribute( 'aria-busy' );
			if ( region.hasAttribute( 'data-wpec-busy-inert' ) ) {
				region.inert = false;
				region.removeAttribute( 'data-wpec-busy-inert' );
			}
		}
		state.regions = [];
		document.documentElement.classList.remove( BUSY_CLASS );
		if ( overlay ) {
			overlay.hidden = true;
		}
		give_focus_back();
		state.button = null;
		state.focus = null;
		state.step = '';
	}

	/* Place order back ( and "Please wait" gone ), then unlocked. */
	function restore() {
		if ( 'function' === typeof window.wpeasycart_onepage_restore_buttons && document.getElementById( 'wpeasycart_onepage_place' ) ) {
			window.wpeasycart_onepage_restore_buttons(); /* one-page: also ends its own busy flag, and unlocks */
		} else {
			var button = place_button();
			var working = document.getElementById( WORKING );
			if ( button ) {
				button.style.display = '';
			}
			if ( working ) {
				working.style.display = 'none';
			}
		}
		unlock();
	}

	/* While locked: the order stopped when Place order shows again, was drawn again or went away, or checkout protection
	   blocked it ( its notice asks for a check, which must be usable ). */
	function watch() {
		if ( ! state.locked || ! state.watch ) {
			return;
		}
		var button = place_button();
		if ( ! button || button !== state.button || ! document.documentElement.contains( button ) || is_shown( button ) ) {
			unlock();
		} else if ( button.hasAttribute( 'data-wpec-blocked' ) ) {
			restore();
		}
	}

	/* ---------------------------------------------------------------- */
	/* The classic payment step ( and a Place order a payment script    */
	/* hid without asking for the lock )                                */
	/* ---------------------------------------------------------------- */

	function payment_method() {
		var picked = document.querySelector( 'input[name="ec_cart_payment_selection"]:checked' );
		return picked ? String( picked.value ) : 'credit_card';
	}

	function classic_form( button ) {
		var form = button.form || ( button.closest ? button.closest( 'form' ) : null );
		if ( ! form || 'ec_submit_order_form' !== form.id ) {
			return null;
		}
		var action = form.querySelector( 'input[name="ec_cart_form_action"]' );
		return ( action && 'submit_order' === action.value ) ? form : null;
	}

	/* The covers are printed with display:none; a payment script's show() clears it. */
	function cover_shown() {
		for ( var i = 0; i < COVERS.length; i++ ) {
			var cover = document.getElementById( COVERS[ i ] );
			if ( cover && is_shown( cover ) ) {
				return true;
			}
		}
		return false;
	}

	/* Checked once the click or the submit has run through every script on the page: Place order hidden, and the order is
	   on its way ( a payment script's cover shows, the form posts on its own, or Braintree's drop-in took it over ). A flow
	   that opens its own window on the page without a cover ( Realex's, Affirm's ) is left as it was. */
	function classic_check( event ) {
		if ( state.locked ) {
			return;
		}
		var button = place_button();
		if ( ! button || is_shown( button ) || document.getElementById( 'wpeasycart_onepage_place' ) || ! classic_form( button ) ) {
			return;
		}
		var method = payment_method();
		if ( 'affirm' === method || ( 'third_party' === method && window.RealexHpp ) ) {
			return;
		}
		var posting = !! event && 'submit' === event.type && ! event.defaultPrevented;
		var braintree = !! event && 'submit' === event.type && 'credit_card' === method && !! document.getElementById( 'wpec_braintree_dropin' );
		if ( cover_shown() || posting || braintree ) {
			lock( 'manual_bill' === method ? 'finishing' : 'payment' );
		}
	}

	function later( event ) {
		window.setTimeout( function() {
			classic_check( event );
		}, 0 );
	}

	document.addEventListener( 'click', function( event ) {
		var target = event.target;
		if ( target && target.closest && target.closest( '#' + BUTTON ) ) {
			later( event );
		}
	}, false );

	document.addEventListener( 'submit', function( event ) {
		var form = event.target;
		if ( form && form.querySelector && form.querySelector( '#' + BUTTON ) ) {
			later( event );
		}
	}, false );

	/* ---------------------------------------------------------------- */
	/* Keyboard: nothing behind the card, even where inert is missing   */
	/* ---------------------------------------------------------------- */

	function in_regions( node ) {
		for ( var i = 0; i < state.regions.length; i++ ) {
			if ( state.regions[ i ] === node || state.regions[ i ].contains( node ) ) {
				return true;
			}
		}
		return false;
	}

	document.addEventListener( 'keydown', function( event ) {
		if ( ! state.locked ) {
			return;
		}
		var target = event.target;
		if ( 'Tab' === event.key && state.move_focus && ( target === card || target === document.body || target === document.documentElement ) ) {
			event.preventDefault(); /* the card keeps the focus while it shows ( nothing behind it can be reached ) */
			focus_card();
		} else if ( target && target !== document.body && in_regions( target ) ) {
			event.preventDefault();
			event.stopPropagation();
		}
	}, true );

	document.addEventListener( 'focusin', function( event ) {
		if ( state.locked && ! has_inert && event.target && in_regions( event.target ) ) {
			if ( event.target.blur ) {
				event.target.blur();
			}
			focus_card();
		}
	}, true );

	/* Back to this page from the next one ( the back / forward cache kept it locked ): Place order is usable again. */
	window.addEventListener( 'pageshow', function( event ) {
		if ( event.persisted && state.locked ) {
			restore();
		}
	} );

	window.wpeasycart_checkout_busy = {
		lock: lock,
		status: status,
		unlock: unlock,
		restore: restore,
		locked: function() {
			return state.locked;
		}
	};
} )( window, document );

/**
 * wpeasycart_checkout_goto( result, error_field ) ( 6.0.2 ): a checkout completion call ( free order, bank transfer, Stripe,
 * the pay-for-order page, a subscription ) answers with the page to go to. An empty or malformed answer ( the server no longer
 * knew the cart session, a plugin printed before the address ) sent the browser to '' = the same page again, with no message
 * and no order. Now only an http(s) or root-relative address is followed; anything else brings Place order back, ends the
 * busy state and shows the submit notice ( #<error_field>_error, default ec_submit_order ). Returns whether it navigated.
 * Here rather than in ec-store.js so the minified build and theme copies of ec-store.js ( wp-easycart-data ) have it too.
 */
( function( window, document ) {
	'use strict';

	if ( 'function' === typeof window.wpeasycart_checkout_goto ) {
		return;
	}

	window.wpeasycart_checkout_goto = function( result, error_field ) {
		var $ = window.jQuery;
		result = ( 'string' === typeof result ) ? result.trim() : '';
		if ( /^(https?:\/\/|\/)[^\s<>"']*$/.test( result ) ) {
			window.location.href = result;
			return true;
		}
		if ( window.console && window.console.warn ) {
			window.console.warn( 'WP EasyCart: the checkout call did not answer with a page to go to.', result );
		}
		if ( $ ) {
			$( document.getElementById( 'ec_cart_submit_order' ) ).show();
			$( document.getElementById( 'ec_cart_submit_order_working' ) ).hide();
			$( '#stripe-success-cover, #manual-success-cover' ).hide();
		}
		if ( 'function' === typeof window.wpeasycart_onepage_restore_buttons ) {
			window.wpeasycart_onepage_restore_buttons();
		}
		if ( window.wpeasycart_checkout_busy && window.wpeasycart_checkout_busy.locked() ) {
			window.wpeasycart_checkout_busy.unlock();
		}
		if ( 'function' === typeof window.ec_show_error ) {
			window.ec_show_error( error_field || 'ec_submit_order' );
		} else if ( $ ) {
			$( document.getElementById( ( error_field || 'ec_submit_order' ) + '_error' ) ).show();
		}
		return false;
	};
} )( window, document );
