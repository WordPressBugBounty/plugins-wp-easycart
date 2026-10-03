/**
 * WP EasyCart one-page checkout: Place order ( 6.0.2 ).
 *
 * Loaded after ec-store.js whenever the one-page checkout is on, and kept apart from it so a theme's own copy of
 * ec-store.js, or the minified build, still gets it. The payment step prints #wpeasycart_onepage_place with its settings
 * ( nonces, the Place order address ); ec_validate_submit_order() hands a valid form to wpeasycart_onepage_place_order().
 */
/*
 * 6.0.2: the one-page checkout's Place order ( #wpeasycart_onepage_place holds its settings ).
 *  1. Save what the page shows, one save after another ( each writes the whole checkout session ): the contact and address
 *     block with the billing choice and order notes, a different billing address, and anything an extension registers in
 *     window.wpeasycart_checkout_savers ( functions answering with a request or promise ).
 *  2. Ask the server ( ec_ajax_onepage_check ): stock, minimum order, required details. Problems are shown, nothing is charged.
 *  3. Nothing to pay: the free order call. Otherwise the form is submitted: the payment scripts listening on it ( Stripe,
 *     Square, bank transfer, Braintree, Accept.js … ) take over, and anything else posts to the classic Place order, which
 *     handles card fields, 3-D Secure and redirect gateways. On the single page the form is the details form, so it is
 *     turned into that Place order post first.
 * Extensions can stop it with window.wpeasycart_checkout_validators ( functions answering false ).
 * 6.0.2 ( bug round 7 ): the checkout is locked under a status card ( ec-checkout-busy.js ) from the click on, not only once
 * a payment script took over: the saves and the store's check can take a while, and nothing may change meanwhile. The lock
 * ends with Place order's buttons ( wpeasycart_onepage_restore_buttons() ), or when a payment script shows Place order again.
 */
var wpeasycart_onepage_place = { busy: false, submitting: false, session: null, totals: null, total: null, items: null };

/* 6.0.2: the busy state ( window.wpeasycart_checkout_busy, ec-checkout-busy.js; without it Place order works as before ). */
function wpeasycart_onepage_busy( step, options ) {
	if ( window.wpeasycart_checkout_busy ) {
		window.wpeasycart_checkout_busy.lock( step, options );
	}
}

function wpeasycart_onepage_busy_status( step ) {
	if ( window.wpeasycart_checkout_busy ) {
		window.wpeasycart_checkout_busy.status( step );
	}
}

function wpeasycart_onepage_is_busy() {
	return !! ( window.wpeasycart_checkout_busy && window.wpeasycart_checkout_busy.locked() );
}

function wpeasycart_onepage_restore_buttons() {
	wpeasycart_onepage_place.busy = false;
	jQuery( document.getElementById( 'ec_cart_submit_order' ) ).show();
	jQuery( document.getElementById( 'ec_cart_submit_order_working' ) ).hide();
	if ( window.wpeasycart_checkout_busy ) {
		window.wpeasycart_checkout_busy.unlock();
	}
}

/* The page's own checks from extensions ( window.wpeasycart_checkout_validators ), run before the checkout is locked so a
   field they point at can still take the focus. */
function wpeasycart_onepage_validators_ok() {
	var validators = window.wpeasycart_checkout_validators || [];
	for ( var i = 0; i < validators.length; i++ ) {
		if ( 'function' === typeof validators[ i ] && false === validators[ i ]() ) {
			ec_show_error( 'ec_submit_order' );
			return false;
		}
	}
	return true;
}

function wpeasycart_onepage_show_errors( errors ) {
	/* 6.0.2 ( bug round 7 ): a problem shown means the order stopped: Place order is back and the checkout unlocked first, so
	   a field the problem points at can take the focus ( a payment script, Square's, reports its refusals here too ). */
	wpeasycart_onepage_restore_buttons();
	/* 6.0.2: for the page's own parts ( WP EasyCart PRO's checkout fields mark the rows the check refused ). */
	jQuery( document ).trigger( 'wpeasycart_checkout_errors', [ errors || [] ] );
	var box = jQuery( document.getElementById( 'ec_onepage_order_errors' ) );
	if ( ! box.length ) {
		ec_show_error( 'ec_submit_order' );
		return;
	}
	box.empty();
	jQuery.each( errors || [], function( i, error ) {
		/* 6.0.2: the rule that refused rides along as data-code, so a store can tell which check it was. */
		box.append( jQuery( '<div></div>' ).text( error && error.message ? error.message : '' ).attr( 'data-code', error && error.code ? String( error.code ) : '' ) );
	} );
	box.show();
	jQuery( [ document.documentElement, document.body ] ).animate( { scrollTop: box.offset().top - 80 }, 400 );
}

/* Answers a promise of ( saved, problem ): saved is false when a save found something the customer must fix ( problem is
   the id of the message shown ); otherwise whether each save worked or not, the server check decides. */
function wpeasycart_onepage_save_all() {
	var steps = [], problem = '';
	var nonce = jQuery( document.getElementById( 'wpeasycart_checkout_nonce' ) ).val();
	if ( nonce && document.getElementById( 'ec_cart_onepage_info' ) && 'function' === typeof wp_easycart_checkout_info_request ) {
		steps.push( function() {
			var request = wp_easycart_checkout_info_request();
			if ( ! request || ! request.data ) {
				return null;
			}
			/* 6.0.2: only the error is read here, so the server skips drawing the step templates again ( FREE 6.0.2+ ). */
			if ( 'object' === typeof request.data ) {
				request.data.ec_render = '0';
			}
			return jQuery.ajax( { url: wpeasycart_ajax_object.ajax_url, type: 'post', data: request.data, cache: false } ).done( function( data ) {
				var response_obj = null;
				try {
					response_obj = ( 'string' === typeof data ) ? JSON.parse( data ) : data;
				} catch ( e ) {
					response_obj = null;
				}
				if ( response_obj && 'user_create_error' == response_obj.error ) {
					jQuery( document.getElementById( 'ec_create_account_email_error' ) ).show();
					problem = 'ec_create_account_email_error';
				}
			} );
		} );
	} else if ( nonce && jQuery( document.getElementById( 'ec_order_notes' ) ).length ) {
		steps.push( function() {
			return jQuery.ajax( { url: wpeasycart_ajax_object.ajax_url, type: 'post', data: { action: 'ec_ajax_update_order_notes', ec_order_notes: jQuery( document.getElementById( 'ec_order_notes' ) ).val(), wpeasycart_checkout_nonce: nonce }, cache: false } );
		} );
	}
	if ( jQuery( document.getElementById( 'billing_address_type_different' ) ).is( ':checked' ) ) {
		steps.push( function() {
			var billing_nonce = jQuery( document.getElementById( 'wp_easycart_update_billing_nonce' ) ).val() || jQuery( document.getElementById( 'wpeasycart_onepage_place' ) ).attr( 'data-billing-nonce' );
			return wpeasycart_onepage_billing_request( billing_nonce );
		} );
	}
	jQuery.each( window.wpeasycart_checkout_savers || [], function( i, saver ) {
		if ( 'function' === typeof saver ) {
			steps.push( saver );
		}
	} );
	var done = jQuery.Deferred();
	var run = function( index ) {
		if ( index >= steps.length ) {
			done.resolve( '' === problem, problem );
			return;
		}
		var next = function() {
			run( index + 1 );
		};
		var job = null;
		try {
			job = steps[ index ]();
		} catch ( e ) {
			job = null;
		}
		if ( job && 'function' === typeof job.always ) {
			job.always( next );
		} else if ( job && 'function' === typeof job.then ) {
			job.then( next, next );
		} else {
			next();
		}
	};
	run( 0 );
	return done.promise();
}

/* Markup as the customer reads it ( a total's currency entities decoded ), without running any of it. */
function wpeasycart_onepage_text( html ) {
	if ( 'string' !== typeof html || '' === html ) {
		return '';
	}
	var doc = ( window.DOMParser ) ? new DOMParser().parseFromString( html, 'text/html' ) : null;
	return String( doc && doc.body ? doc.body.textContent : html ).trim();
}

/* The checks and saves before any payment ( Place order, and PayPal's buttons from their click ). Answers a promise of
   true when the order can be paid; otherwise it has shown what is wrong. */
function wpeasycart_onepage_ready_to_pay( skip_page_checks ) {
	var ready = jQuery.Deferred();
	var settings = document.getElementById( 'wpeasycart_onepage_place' );
	if ( ! settings ) {
		return ready.resolve( true ).promise();
	}
	if ( ! skip_page_checks && ! ec_validate_submit_order( true ) ) {
		return ready.resolve( false ).promise();
	}
	if ( ! wpeasycart_onepage_validators_ok() ) {
		return ready.resolve( false ).promise();
	}
	/* 6.0.2 ( bug round 7 ): locked while the page saves and the store checks it. Place order locked it already; PayPal's
	   buttons and Square's gift card lock it here and hand it back once the checks are done ( their own windows take over ). */
	var own_lock = ! wpeasycart_onepage_is_busy();
	if ( own_lock ) {
		wpeasycart_onepage_busy( 'checking', { focus: false } );
	}
	/* A problem unlocks the checkout first, then shows: a field it points at can take the focus. */
	var finish = function( ok, show ) {
		if ( ! ok ) {
			wpeasycart_onepage_restore_buttons();
		} else if ( own_lock && window.wpeasycart_checkout_busy ) {
			window.wpeasycart_checkout_busy.unlock();
		}
		if ( 'function' === typeof show ) {
			show();
		}
		ready.resolve( ok );
	};
	jQuery( document.getElementById( 'ec_onepage_order_errors' ) ).hide().empty();
	ec_hide_error( 'ec_submit_order' );
	var shown_total = wpeasycart_onepage_text( jQuery( document.getElementById( 'ec_cart_total' ) ).html() );
	wpeasycart_onepage_save_all().then( function( saved, problem ) {
		if ( ! saved ) {
			finish( false, function() {
				ec_show_error( 'ec_submit_order' );
				if ( problem && jQuery( document.getElementById( problem ) ).length ) {
					jQuery( [ document.documentElement, document.body ] ).animate( { scrollTop: jQuery( document.getElementById( problem ) ).offset().top - 80 }, 400 );
				}
			} );
			return;
		}
		jQuery.ajax( {
			url: wpeasycart_ajax_object.ajax_url,
			type: 'post',
			dataType: 'json',
			cache: false,
			data: { action: 'ec_ajax_onepage_check', wpeasycart_checkout_nonce: settings.getAttribute( 'data-check-nonce' ) }
		} ).done( function( response ) {
			var result = ( response && response.data ) ? response.data : {};
			if ( ! response || ! response.success || ( result.errors && result.errors.length ) ) {
				finish( false, function() {
					wpeasycart_onepage_show_errors( result.errors && result.errors.length ? result.errors : [ { message: settings.getAttribute( 'data-error' ) } ] );
				} );
				return;
			}
			wpeasycart_onepage_place.session = result.session || null;
			wpeasycart_onepage_place.totals = result.totals || null;
			wpeasycart_onepage_place.items = result.items || null;
			wpeasycart_onepage_place.total = parseFloat( result.grand_total );
			var charge_total = wpeasycart_onepage_text( result.grand_total_display );
			if ( charge_total && shown_total && charge_total !== shown_total ) {
				/* The saves changed the total ( shipping and tax follow the address ), or the page showed another figure: show
				   the total that would be charged, and let the customer place the order again. */
				finish( false, function() {
					if ( result.cart_data && result.cart_data.cart && result.cart_data.cart.length && 'function' === typeof ec_update_cart ) {
						ec_update_cart( result.cart_data );
					}
					jQuery( document.getElementById( 'ec_cart_total' ) ).text( charge_total );
					jQuery( document.getElementById( 'ec_cart_mobile_total' ) ).text( charge_total );
					jQuery( '.ec_cart_price_total' ).text( charge_total );
					wpeasycart_onepage_show_errors( [ { message: settings.getAttribute( 'data-total-changed' ) || settings.getAttribute( 'data-error' ) } ] );
				} );
				return;
			}
			finish( true );
		} ).fail( function() {
			finish( false, function() {
				wpeasycart_onepage_show_errors( [ { message: settings.getAttribute( 'data-error' ) } ] );
			} );
		} );
	} );
	return ready.promise();
}

function wpeasycart_onepage_place_order() {
	/* Busy until the buttons come back ( a problem was shown, by this script or a payment script ) or the page moves on:
	   another click or Enter while the order is on its way does nothing. */
	if ( wpeasycart_onepage_is_busy() || ( wpeasycart_onepage_place.busy && ! jQuery( document.getElementById( 'ec_cart_submit_order' ) ).is( ':visible' ) ) ) {
		return;
	}
	if ( ! wpeasycart_onepage_validators_ok() ) {
		wpeasycart_onepage_restore_buttons(); /* an older ec-store.js may have hidden Place order already */
		return;
	}
	wpeasycart_onepage_place.busy = true;
	jQuery( document.getElementById( 'ec_cart_submit_order' ) ).hide();
	jQuery( document.getElementById( 'ec_cart_submit_order_working' ) ).show();
	/* 6.0.2 ( bug round 7 ): locked at once, before the saves and the store's check ( they can take a while ). */
	wpeasycart_onepage_busy( 'checking' );
	wpeasycart_onepage_ready_to_pay( true ).then( function( ok ) {
		if ( ! ok ) {
			wpeasycart_onepage_restore_buttons();
			return;
		}
		var settings = document.getElementById( 'wpeasycart_onepage_place' );
		if ( ! ( wpeasycart_onepage_place.total > 0 ) ) {
			wpeasycart_onepage_busy_status( 'finishing' );
			wpeasycart_onepage_free_order( settings );
		} else {
			/* A bank transfer order is only recorded; every other way pays now. */
			wpeasycart_onepage_busy_status( 'manual_bill' === jQuery( 'input:radio[name=ec_cart_payment_selection]:checked' ).val() ? 'finishing' : 'payment' );
			wpeasycart_onepage_submit( settings );
		}
	} );
}

function wpeasycart_onepage_free_order( settings ) {
	jQuery( document.getElementById( 'ec_cart_submit_order' ) ).hide();
	jQuery( document.getElementById( 'ec_cart_submit_order_working' ) ).show();
	jQuery.ajax( {
		url: wpeasycart_ajax_object.ajax_url,
		type: 'post',
		cache: false,
		data: {
			action: 'ec_ajax_v2_complete_free_payment',
			language: wpeasycart_ajax_object.current_language,
			ec_terms_agree: ( jQuery( document.getElementById( 'ec_terms_agree' ) ).is( ':checked' ) || '2' == jQuery( document.getElementById( 'ec_terms_agree' ) ).val() ) ? 1 : 0,
			ec_cart_is_subscriber: jQuery( document.getElementById( 'ec_cart_is_subscriber' ) ).is( ':checked' ) ? 1 : 0,
			nonce: settings.getAttribute( 'data-free-nonce' )
		},
		success: function( result ) {
			if ( ! wpeasycart_checkout_goto( result ) ) { /* 6.0.2: an empty answer no longer reloads the page */
				wpeasycart_onepage_show_errors( [ { message: settings.getAttribute( 'data-error' ) } ] );
			}
		},
		error: function() {
			wpeasycart_onepage_restore_buttons();
			wpeasycart_onepage_show_errors( [ { message: settings.getAttribute( 'data-error' ) } ] );
		}
	} );
}

function wpeasycart_onepage_submit( settings ) {
	var form = document.getElementById( 'ec_submit_order_form' ) || document.getElementById( 'wpeasycart_checkout_details_form' );
	if ( ! form ) {
		wpeasycart_onepage_restore_buttons();
		return;
	}
	if ( 'wpeasycart_checkout_details_form' == form.id ) {
		/* The single page's details form becomes the classic Place order post. */
		form.setAttribute( 'action', settings.getAttribute( 'data-submit-action' ) );
		jQuery( form ).find( 'input[name="ec_cart_form_action"]' ).val( 'submit_order' );
		jQuery( form ).find( 'input[name="ec_cart_form_nonce"]' ).val( settings.getAttribute( 'data-submit-nonce' ) );
	}
	if ( jQuery( document.getElementById( 'ec_card_number' ) ).length ) {
		jQuery( document.getElementById( 'ec_card_number' ) ).val( jQuery( document.getElementById( 'ec_card_number' ) ).val().replace( /\s+/g, '' ) );
	}
	/* eWay Rapid: its script encrypts forms marked when the page loaded; this form may not have been. */
	if ( settings.getAttribute( 'data-eway-key' ) && window.eCrypt && 'function' === typeof window.eCrypt.encryptValue ) {
		jQuery( form ).find( '[data-eway-encrypt-name]' ).each( function() {
			if ( this.value && 0 !== this.value.indexOf( 'eCrypted:' ) ) {
				this.value = window.eCrypt.encryptValue( this.value, settings.getAttribute( 'data-eway-key' ) );
			}
		} );
		form.removeAttribute( 'data-eway-encrypt-key' );
	}
	/* A submit event without the browser's own field checks ( hidden sections carry required fields ): the payment scripts
	   listening on the form take it over, or it posts. */
	wpeasycart_onepage_place.submitting = true;
	var submit_event;
	try {
		submit_event = new Event( 'submit', { bubbles: true, cancelable: true } );
	} catch ( e ) {
		submit_event = document.createEvent( 'Event' );
		submit_event.initEvent( 'submit', true, true );
	}
	var go_on = form.dispatchEvent( submit_event );
	wpeasycart_onepage_place.submitting = false;
	if ( go_on ) {
		jQuery( document.getElementById( 'ec_cart_submit_order' ) ).hide();
		jQuery( document.getElementById( 'ec_cart_submit_order_working' ) ).show();
		HTMLFormElement.prototype.submit.call( form );
	}
}

/* The freshest address for a payment script: what the last Place order check read from the session, else the page's own. */
function wpeasycart_onepage_session_value( key, fallback ) {
	var session = wpeasycart_onepage_place.session;
	return ( session && 'undefined' !== typeof session[ key ] ) ? session[ key ] : fallback;
}

/* A different billing address, saved for Place order ( as ec_update_billing_address_display() saves it ). */
function wpeasycart_onepage_billing_request( nonce ) {
	var el = function( id ) { return jQuery( document.getElementById( id ) ); };
	var pick = function( primary, secondary ) { return el( primary ).length ? el( primary ).val() : el( secondary ).val(); };
	var country = pick( 'ec_billing_country', 'ec_cart_billing_country' );
	var state = el( 'ec_billing_state' ).length ? el( 'ec_billing_state' ).val() : ( el( 'ec_cart_billing_state_' + country ).length ? el( 'ec_cart_billing_state_' + country ).val() : el( 'ec_cart_billing_state' ).val() );
	return jQuery.ajax( {
		url: wpeasycart_ajax_object.ajax_url,
		type: 'post',
		cache: false,
		data: {
			action: 'ec_ajax_update_billing_address_type',
			billing_address_type: '1',
			ec_billing_address_line_1: pick( 'ec_billing_address_line_1', 'ec_cart_billing_address' ),
			ec_billing_address_line_2: pick( 'ec_billing_address_line_2', 'ec_cart_billing_address2' ),
			ec_billing_city: pick( 'ec_billing_city', 'ec_cart_billing_city' ),
			ec_billing_state: state,
			ec_billing_zip: pick( 'ec_billing_zip', 'ec_cart_billing_zip' ),
			ec_billing_country: country,
			ec_billing_phone: pick( 'ec_billing_phone', 'ec_cart_billing_phone' ),
			ec_billing_name: pick( 'ec_billing_name', 'ec_cart_billing_first_name' ),
			ec_billing_last_name: pick( 'ec_billing_last_name', 'ec_cart_billing_last_name' ),
			ec_billing_company_name: pick( 'ec_billing_company_name', 'ec_cart_billing_company_name' ),
			nonce: nonce
		}
	} );
}

/*
 * 6.0.2: the order summary beside the checkout ( .ec_cart_right_v2, both layouts ).
 * An inline script in ec_checkout_v2.php used to push it down with padding on every scroll, with no limit: a summary taller
 * than the window made the page longer at each scroll, so it could be scrolled far past the checkout. The summary now stays
 * in view with position: sticky, which never changes the page's height and stops at the end of the checkout, and only while
 * it sits beside the checkout and fits in the window below any fixed header; otherwise it scrolls with the page. A theme's
 * copy of the template that still carries the old script has its padding cancelled on wide screens.
 */
var wpeasycart_onepage_summary = { frame: 0, styled: false, list: null, observer: null, observed: [] };

function wpeasycart_onepage_summary_style() {
	if ( wpeasycart_onepage_summary.styled || ! document.head ) {
		return;
	}
	wpeasycart_onepage_summary.styled = true;
	var style = document.createElement( 'style' );
	style.id = 'wpeasycart-onepage-summary-css';
	style.appendChild( document.createTextNode(
		'@media only screen and ( min-width:990px ){ .ec_cart_right.ec_cart_right_v2{ padding-top:0 !important; } }' +
		'.ec_cart_right.ec_cart_right_v2.ec_cart_right_sticky{ position:-webkit-sticky; position:sticky; }'
	) );
	document.head.appendChild( style );
}

/* How far down a fixed or sticky header ( and the admin bar ) reaches at the top of the window, plus a gap. */
function wpeasycart_onepage_summary_top( summary ) {
	var top = 0;
	var width = document.documentElement.clientWidth || window.innerWidth || 0;
	var limit = ( window.innerHeight || 0 ) * 0.4;
	if ( width && document.elementFromPoint && window.getComputedStyle ) {
		[ 0.15, 0.5, 0.85 ].forEach( function( share ) {
			var node = document.elementFromPoint( Math.round( width * share ), 1 );
			while ( node && 1 === node.nodeType && node !== document.body && node !== document.documentElement ) {
				if ( node === summary || summary.contains( node ) ) {
					break;
				}
				var position = window.getComputedStyle( node ).position;
				if ( 'fixed' === position || 'sticky' === position ) {
					var rect = node.getBoundingClientRect();
					if ( rect.top <= 1 && rect.bottom > top && rect.bottom <= limit ) {
						top = rect.bottom;
					}
					break;
				}
				node = node.parentNode;
			}
		} );
	}
	return Math.round( top ) + 20;
}

function wpeasycart_onepage_summary_layout() {
	var list = wpeasycart_onepage_summary.list;
	if ( ! list ) {
		list = wpeasycart_onepage_summary.list = document.getElementsByClassName( 'ec_cart_right_v2' ); /* live: the checkout is drawn after the page loads */
	}
	if ( ! list.length ) {
		return;
	}
	wpeasycart_onepage_summary_style();
	var watch = [];
	for ( var i = 0; i < list.length; i++ ) {
		var summary = list[ i ];
		var left = summary.parentNode ? summary.parentNode.querySelector( '.ec_cart_left' ) : null;
		var sticky = false;
		var top = 0;
		if ( left && null !== summary.offsetParent ) {
			var a = left.getBoundingClientRect();
			var b = summary.getBoundingClientRect();
			/* Beside the checkout ( two columns, either side ), not below it ( one column on phones and narrow screens ). */
			if ( a.width > 0 && b.width > 0 && ( b.left >= a.right - 2 || b.right <= a.left + 2 ) ) {
				top = wpeasycart_onepage_summary_top( summary );
				sticky = ( summary.offsetHeight + top + 16 <= ( window.innerHeight || document.documentElement.clientHeight || 0 ) );
			}
		}
		if ( sticky ) {
			summary.classList.add( 'ec_cart_right_sticky' );
			summary.style.top = top + 'px';
		} else if ( summary.classList.contains( 'ec_cart_right_sticky' ) ) {
			summary.classList.remove( 'ec_cart_right_sticky' );
			summary.style.top = '';
		}
		watch.push( summary );
		if ( left ) {
			watch.push( left );
		}
	}
	/* Heights change without a scroll ( a coupon, the totals, a section drawn again ): look again when they do. */
	var changed = ( watch.length !== wpeasycart_onepage_summary.observed.length ) || watch.some( function( node, n ) {
		return node !== wpeasycart_onepage_summary.observed[ n ];
	} );
	if ( window.ResizeObserver && changed ) {
		if ( ! wpeasycart_onepage_summary.observer ) {
			wpeasycart_onepage_summary.observer = new window.ResizeObserver( wpeasycart_onepage_summary_queue );
		}
		wpeasycart_onepage_summary.observer.disconnect();
		watch.forEach( function( node ) {
			wpeasycart_onepage_summary.observer.observe( node );
		} );
		wpeasycart_onepage_summary.observed = watch;
	}
}

function wpeasycart_onepage_summary_queue() {
	if ( wpeasycart_onepage_summary.frame ) {
		return;
	}
	var later = window.requestAnimationFrame || function( callback ) {
		return window.setTimeout( callback, 16 );
	};
	wpeasycart_onepage_summary.frame = later( function() {
		wpeasycart_onepage_summary.frame = 0;
		wpeasycart_onepage_summary_layout();
	} );
}

( function() {
	window.addEventListener( 'scroll', wpeasycart_onepage_summary_queue, { passive: true } );
	window.addEventListener( 'resize', wpeasycart_onepage_summary_queue );
	window.addEventListener( 'load', wpeasycart_onepage_summary_queue );
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', wpeasycart_onepage_summary_queue );
	} else {
		wpeasycart_onepage_summary_queue();
	}
	if ( window.jQuery ) {
		window.jQuery( document ).on( 'wpeasycart_cart_updated', wpeasycart_onepage_summary_queue );
	}
} )();

/* An older ec-store.js ( a theme's copy, or the minified build ) validates without handing over to Place order: wrap it. */
( function() {
	if ( 'function' !== typeof window.ec_validate_submit_order || window.ec_validate_submit_order.length > 0 ) {
		return; /* this release's version: it calls wpeasycart_onepage_place_order() itself */
	}
	var legacy = window.ec_validate_submit_order;
	window.ec_validate_submit_order = function( validate_only ) {
		var ok = legacy();
		if ( ! ok || ! document.getElementById( 'wpeasycart_onepage_place' ) ) {
			return ok;
		}
		if ( validate_only ) {
			wpeasycart_onepage_restore_buttons();
			return true;
		}
		if ( wpeasycart_onepage_place.submitting ) {
			return true;
		}
		wpeasycart_onepage_place_order();
		return false;
	};
} )();
