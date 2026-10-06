/**
 * WP EasyCart: account subscription pages ( 6.0.0 ).
 *
 * Loaded after ec-store.js as its own file so stores with a custom copy of ec-store.js still get it.
 * Replaces ec_show_update_subscription_payment() / ec_show_update_subscription_details() with versions that
 * keep the legacy toggles for older template copies and, on the 6.0.0 card layout ( .ec_account_subscription_v2 ),
 * open one panel at a time, scroll it into view below any fixed or sticky site header and focus its heading.
 * The account page can be loaded by AJAX, so every control uses delegated events.
 *
 * 6.0.3: Change plan previews a change ( what it costs, when it starts ) and the customer confirms it
 * ( wp_easycart_subscription_changes, AJAX ec_ajax_subscription_change_* ). A change charged at once that needs
 * 3-D Secure is confirmed with Stripe.js here, then the store asks Stripe whether it applied. Template copies from
 * before 6.0.3 get the same flow ( the review box is made here ) whenever the store runs it
 * ( wpeasycart_ajax_object.subscription_changes ). Monthly / Yearly tabs filter the plans of a plan group.
 */
( function( $, window, document ) {
	'use strict';

	if ( ! $ ) {
		return;
	}

	var PANELS = {
		payment: { panel: '.ec_account_subscription_details_payment_form', trigger: '.ec_account_subscription_details_card_change' },
		plan: { panel: '.ec_account_subscription_upgrade_row', trigger: '.ec_account_subscription_details_plan_change' }
	};

	function prefersReducedMotion() {
		return !! ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches );
	}

	function isPinned( el ) {
		var position;
		try {
			position = window.getComputedStyle( el ).position;
		} catch ( e ) {
			return false;
		}
		return 'fixed' === position || 'sticky' === position || '-webkit-sticky' === position;
	}

	/**
	 * Height covered at the top of the viewport by fixed / sticky elements ( site headers, WP admin bar ).
	 * Samples points across the top edge and walks up from each hit to a pinned ancestor, then repeats
	 * below what it found so stacked bars ( announcement bar + nav ) add up.
	 */
	function topOffset() {
		var offset = 0;
		var viewportWidth = window.innerWidth || document.documentElement.clientWidth;
		var viewportHeight = window.innerHeight || document.documentElement.clientHeight;
		var adminBar = document.getElementById( 'wpadminbar' );
		if ( adminBar && isPinned( adminBar ) ) {
			var barRect = adminBar.getBoundingClientRect();
			if ( barRect.bottom > 0 && barRect.height > 0 ) {
				offset = Math.max( offset, barRect.bottom );
			}
		}
		if ( ! document.elementFromPoint ) {
			return offset;
		}
		var xs = [ 12, Math.round( viewportWidth / 2 ), viewportWidth - 12 ];
		for ( var pass = 0; pass < 4; pass++ ) {
			var found = offset;
			var y = Math.min( offset + 1, viewportHeight - 1 );
			for ( var i = 0; i < xs.length; i++ ) {
				var el = document.elementFromPoint( xs[ i ], y );
				while ( el && el !== document.body && el !== document.documentElement ) {
					if ( isPinned( el ) ) {
						var rect = el.getBoundingClientRect();
						/* Ignore full-screen overlays and anything not actually touching the top area. */
						if ( rect.height > 0 && rect.height < viewportHeight * 0.5 && rect.top <= y + 1 && rect.bottom > found ) {
							found = rect.bottom;
						}
						break;
					}
					el = el.parentElement;
				}
			}
			if ( found <= offset ) {
				break;
			}
			offset = found;
		}
		return Math.max( 0, Math.round( offset ) );
	}

	/** Scroll only when the panel is not already comfortably on screen; then focus its heading. */
	function revealPanel( panel ) {
		if ( ! panel || ! panel.getBoundingClientRect ) {
			return;
		}
		var gap = 16;
		var offset = topOffset();
		var rect = panel.getBoundingClientRect();
		var viewportHeight = window.innerHeight || document.documentElement.clientHeight;
		var available = viewportHeight - offset;
		var fullyVisible = rect.top >= offset && rect.bottom <= viewportHeight;
		var tallButTopVisible = rect.height > available && rect.top >= offset && rect.top <= offset + available / 3;
		if ( ! fullyVisible && ! tallButTopVisible ) {
			var target = Math.max( 0, ( window.pageYOffset || document.documentElement.scrollTop ) + rect.top - offset - gap );
			try {
				window.scrollTo( { top: target, behavior: prefersReducedMotion() ? 'auto' : 'smooth' } );
			} catch ( e ) {
				window.scrollTo( 0, target );
			}
		}
		var heading = panel.querySelector( '.ec_account_subscription_v2_panel_title' );
		if ( heading ) {
			try {
				heading.focus( { preventScroll: true } );
			} catch ( e ) {
				heading.focus();
			}
		}
	}

	function openPanel( key ) {
		var other = 'payment' === key ? 'plan' : 'payment';
		var $panel = $( PANELS[ key ].panel );
		if ( ! $panel.closest( '.ec_account_subscription_v2' ).length ) {
			/* Older template copies: the toggles as they always were. */
			$panel.show();
			$( PANELS[ key ].trigger ).hide();
			$( PANELS[ other ].panel ).hide();
			$( PANELS[ other ].trigger ).show();
			return false;
		}
		$( PANELS[ other ].panel ).hide();
		$( PANELS[ other ].trigger ).attr( 'aria-expanded', 'false' ).removeClass( 'is-open' );
		$panel.show();
		$( PANELS[ key ].trigger ).attr( 'aria-expanded', 'true' ).addClass( 'is-open' );
		if ( 'plan' === key ) {
			$panel.each( function() {
				planState( this );
			} );
		}
		revealPanel( $panel.get( 0 ) );
		return false;
	}

	function closePanel( key ) {
		var $panel = $( PANELS[ key ].panel );
		$panel.hide();
		var $trigger = $( PANELS[ key ].trigger ).attr( 'aria-expanded', 'false' ).removeClass( 'is-open' ).show();
		if ( $trigger.length ) {
			try {
				$trigger.get( 0 ).focus( { preventScroll: true } );
			} catch ( e ) {
				$trigger.get( 0 ).focus();
			}
		}
		return false;
	}

	/** Enable Save only when the plan or quantity changed and the quantity is valid. */
	function planState( panel ) {
		var $panel = $( panel );
		var currentPlan = String( $panel.attr( 'data-current-plan' ) || '' );
		var currentQuantity = parseInt( $panel.attr( 'data-current-quantity' ), 10 ) || 1;
		var plan = String( $panel.find( 'input[name="ec_selected_plan"]' ).val() || currentPlan );
		var $quantity = $panel.find( 'input.ec_account_subscription_v2_qty' );
		var quantity = currentQuantity;
		var valid = true;
		if ( $quantity.length ) {
			var raw = $.trim( String( $quantity.val() ) );
			quantity = parseInt( raw, 10 );
			valid = /^\d+$/.test( raw ) && quantity >= 1 && quantity <= 1000000;
			$panel.find( '.ec_account_subscription_v2_qty_error' ).prop( 'hidden', valid );
			$quantity.attr( 'aria-invalid', valid ? 'false' : 'true' );
			$panel.find( '[data-ec-sub-step="-1"]' ).prop( 'disabled', valid && quantity <= 1 );
		}
		var changed = plan !== currentPlan || ( valid && quantity !== currentQuantity );
		$panel.find( '[data-ec-sub-save="plan"]' ).prop( 'disabled', ! ( changed && valid ) );
		/* 6.0.3: a plan on another billing schedule is charged at once, so its own notice shows instead of the usual one. */
		var $intervalNotice = $panel.find( '.ec_account_subscription_v2_interval_notice' );
		if ( $intervalNotice.length ) {
			var $choice = $panel.find( '.ec_account_subscription_v2_plans input[type="radio"]' ).filter( function() {
				return String( this.value ) === plan;
			} );
			var currentInterval = String( $panel.attr( 'data-current-interval' ) || '' );
			var newInterval = String( $choice.attr( 'data-interval' ) || currentInterval );
			var otherSchedule = '' !== currentInterval && newInterval !== currentInterval;
			$intervalNotice.toggle( otherSchedule );
			$panel.find( '.ec_account_subscription_details_notice' ).not( $intervalNotice ).toggle( ! otherSchedule );
		}
		return changed && valid;
	}

	window.ec_show_update_subscription_payment = function() {
		return openPanel( 'payment' );
	};

	window.ec_show_update_subscription_details = function() {
		return openPanel( 'plan' );
	};

	window.ec_account_subscription_close_panel = function( key ) {
		return closePanel( 'plan' === key ? 'plan' : 'payment' );
	};

	/* ---------------------------------------------------------------- 6.0.3 plan changes */

	function ajaxUrl() {
		return ( window.wpeasycart_ajax_object && window.wpeasycart_ajax_object.ajax_url ) ? window.wpeasycart_ajax_object.ajax_url : '';
	}

	function changesOn( $panel ) {
		if ( '1' === String( $panel.attr( 'data-change-preview' ) || '' ) ) {
			return true;
		}
		return !! ( window.wpeasycart_ajax_object && '1' === String( window.wpeasycart_ajax_object.subscription_changes || '' ) );
	}

	function post( action, $panel, extra ) {
		var data = $.extend( {
			action: action,
			language: ( window.wpeasycart_ajax_object && window.wpeasycart_ajax_object.current_language ) ? window.wpeasycart_ajax_object.current_language : '',
			subscription_id: $panel.data( 'ecSubId' ),
			nonce: $panel.data( 'ecSubNonce' )
		}, extra || {} );
		return $.ajax( { url: ajaxUrl(), type: 'post', data: data, dataType: 'json', cache: false } );
	}

	function chosen( $panel ) {
		var $quantity = $panel.find( 'input.ec_account_subscription_v2_qty' );
		return {
			product_id: String( $panel.find( 'input[name="ec_selected_plan"]' ).val() || $panel.attr( 'data-current-plan' ) || '' ),
			quantity: $quantity.length ? parseInt( $quantity.val(), 10 ) || 1 : 0
		};
	}

	/** The review box and the error line ( made here for template copies from before 6.0.3 ). */
	function reviewBox( $panel ) {
		var $review = $panel.find( '.ec_account_subscription_v2_review' );
		if ( ! $review.length ) {
			$review = $( '<div class="ec_account_subscription_v2_review" role="status" aria-live="polite" hidden><p class="ec_account_subscription_v2_review_text"></p><div class="ec_account_subscription_v2_actions"><button type="button" class="ec_account_subscription_v2_primary" data-ec-sub-change-confirm="1"></button> <button type="button" class="ec_account_subscription_v2_secondary" data-ec-sub-change-back="1"></button></div></div>' );
			$panel.find( '[data-ec-sub-save="plan"]' ).closest( '.ec_account_subscription_v2_actions' ).before( $review );
		}
		return $review;
	}

	function errorLine( $panel ) {
		var $error = $panel.find( '.ec_account_subscription_v2_change_error' );
		if ( ! $error.length ) {
			$error = $( '<div class="ec_account_subscription_v2_field_error ec_account_subscription_v2_change_error" role="alert" hidden></div>' );
			$panel.find( '[data-ec-sub-save="plan"]' ).closest( '.ec_account_subscription_v2_actions' ).before( $error );
		}
		return $error;
	}

	function showError( $panel, message ) {
		var $error = errorLine( $panel );
		$error.text( message || '' ).prop( 'hidden', ! message );
	}

	function busy( $button, on ) {
		$button.prop( 'disabled', on ).attr( 'aria-busy', on ? 'true' : 'false' ).toggleClass( 'is-busy', on );
	}

	function closeReview( $panel ) {
		reviewBox( $panel ).prop( 'hidden', true );
		$panel.find( '.ec_account_subscription_v2_plan_actions, [data-ec-sub-save="plan"]' ).closest( '.ec_account_subscription_v2_actions' ).show();
		planState( $panel.get( 0 ) );
	}

	function failed( $panel, xhr ) {
		var json = ( xhr && xhr.responseJSON ) ? xhr.responseJSON : null;
		var message = ( json && json.data && json.data.message ) ? json.data.message : '';
		showError( $panel, message || $panel.data( 'ecSubErrorText' ) || '' );
	}

	function preview( $panel, $button ) {
		var pick = chosen( $panel );
		showError( $panel, '' );
		busy( $button, true );
		post( 'ec_ajax_subscription_change_preview', $panel, pick ).done( function( json ) {
			busy( $button, false );
			if ( ! json || ! json.success ) {
				failed( $panel, { responseJSON: json } );
				planState( $panel.get( 0 ) );
				return;
			}
			var $review = reviewBox( $panel );
			$review.find( '.ec_account_subscription_v2_review_text' ).text( json.data.message || '' );
			if ( json.data.confirm ) {
				$review.find( '[data-ec-sub-change-confirm]' ).text( json.data.confirm );
			}
			if ( json.data.back ) {
				$review.find( '[data-ec-sub-change-back]' ).text( json.data.back );
			}
			$review.data( 'ecSubPick', pick ).prop( 'hidden', false );
			$button.closest( '.ec_account_subscription_v2_actions' ).hide();
			var confirm = $review.find( '[data-ec-sub-change-confirm]' ).get( 0 );
			if ( confirm ) {
				try {
					confirm.focus( { preventScroll: false } );
				} catch ( e ) {
					confirm.focus();
				}
			}
		} ).fail( function( xhr ) {
			busy( $button, false );
			failed( $panel, xhr );
			planState( $panel.get( 0 ) );
		} );
	}

	function loadStripe( then ) {
		if ( typeof window.Stripe === 'function' ) {
			then();
			return;
		}
		var script = document.createElement( 'script' );
		script.src = 'https://js.stripe.com/v3/';
		script.onload = then;
		script.onerror = then;
		document.head.appendChild( script );
	}

	function sync( $panel, $button ) {
		post( 'ec_ajax_subscription_change_sync', $panel ).done( function( json ) {
			if ( json && json.success && json.data.url ) {
				window.location.href = json.data.url;
				return;
			}
			busy( $button, false );
			failed( $panel, { responseJSON: json } );
		} ).fail( function( xhr ) {
			busy( $button, false );
			failed( $panel, xhr );
		} );
	}

	function confirmChange( $panel, $button ) {
		var pick = reviewBox( $panel ).data( 'ecSubPick' ) || chosen( $panel );
		showError( $panel, '' );
		busy( $button, true );
		post( 'ec_ajax_subscription_change_confirm', $panel, pick ).done( function( json ) {
			if ( ! json || ! json.success ) {
				busy( $button, false );
				failed( $panel, { responseJSON: json } );
				return;
			}
			if ( 'action' === json.data.state && json.data.client_secret ) {
				/* The bank asks the customer to approve the payment ( 3-D Secure ); the change applies once it is paid. */
				loadStripe( function() {
					if ( typeof window.Stripe !== 'function' || ! json.data.key ) {
						sync( $panel, $button );
						return;
					}
					window.Stripe( json.data.key ).confirmCardPayment( json.data.client_secret ).then( function() {
						sync( $panel, $button );
					} );
				} );
				return;
			}
			if ( json.data.url ) {
				window.location.href = json.data.url;
			}
		} ).fail( function( xhr ) {
			busy( $button, false );
			failed( $panel, xhr );
		} );
	}

	/** Plan Save: validate; with plan changes, preview and confirm; else hand over to ec_update_subscription_info() ( ec-store.js ). */
	window.ec_account_subscription_save_plan = function( button, subscription_id, nonce ) {
		var $panel = $( button ).closest( '.ec_account_subscription_upgrade_row' );
		var panel = $panel.get( 0 );
		if ( panel && ! planState( panel ) ) {
			return false;
		}
		if ( $panel.length && changesOn( $panel ) && ajaxUrl() ) {
			$panel.data( 'ecSubId', subscription_id ).data( 'ecSubNonce', nonce );
			preview( $panel, $( button ) );
			return false;
		}
		$( button ).prop( 'disabled', true );
		return window.ec_update_subscription_info( subscription_id, nonce );
	};

	$( document ).on( 'click', '[data-ec-sub-change-confirm]', function( event ) {
		event.preventDefault();
		confirmChange( $( this ).closest( '.ec_account_subscription_upgrade_row' ), $( this ) );
	} );

	$( document ).on( 'click', '[data-ec-sub-change-back]', function( event ) {
		event.preventDefault();
		var $panel = $( this ).closest( '.ec_account_subscription_upgrade_row' );
		closeReview( $panel );
		var $save = $panel.find( '[data-ec-sub-save="plan"]' ).get( 0 );
		if ( $save ) {
			$save.focus();
		}
	} );

	/* The change waiting on a subscription ( printed above it ): Cancel this change. */
	$( document ).on( 'click', '[data-ec-sub-change-cancel]', function( event ) {
		event.preventDefault();
		var $button = $( this );
		var $notice = $button.closest( '.ec_account_subscription_change_open' );
		$notice.data( 'ecSubId', $notice.attr( 'data-subscription' ) ).data( 'ecSubNonce', $notice.attr( 'data-nonce' ) );
		busy( $button, true );
		post( 'ec_ajax_subscription_change_cancel', $notice ).done( function( json ) {
			if ( json && json.success && json.data.url ) {
				window.location.href = json.data.url;
				return;
			}
			busy( $button, false );
			$notice.find( '.ec_account_subscription_change_open_error' ).text( ( json && json.data && json.data.message ) ? json.data.message : '' ).prop( 'hidden', false );
		} ).fail( function( xhr ) {
			busy( $button, false );
			var json = ( xhr && xhr.responseJSON ) ? xhr.responseJSON : null;
			$notice.find( '.ec_account_subscription_change_open_error' ).text( ( json && json.data && json.data.message ) ? json.data.message : '' ).prop( 'hidden', false );
		} );
	} );

	/* Monthly / Yearly ( a plan group's plans sold both ways ). */
	function showTab( $panel, interval ) {
		$panel.find( '[data-ec-sub-tab]' ).each( function() {
			var on = String( $( this ).attr( 'data-ec-sub-tab' ) ) === interval;
			$( this ).toggleClass( 'is-active', on ).attr( 'aria-selected', on ? 'true' : 'false' );
		} );
		$panel.find( 'label.ec_account_subscription_v2_plan[data-interval]' ).each( function() {
			$( this ).prop( 'hidden', String( $( this ).attr( 'data-interval' ) ) !== interval );
		} );
	}

	$( document ).on( 'click', '[data-ec-sub-tab]', function( event ) {
		event.preventDefault();
		showTab( $( this ).closest( '.ec_account_subscription_upgrade_row' ), String( $( this ).attr( 'data-ec-sub-tab' ) ) );
	} );

	/* A subscriber sent here from a pricing table ( ?change_plan=<product> ) finds Change plan open on that plan. */
	$( function() {
		var match = /[?&]change_plan=(\d+)/.exec( window.location.search || '' );
		if ( ! match ) {
			return;
		}
		var $panel = $( PANELS.plan.panel ).first();
		var $radio = $panel.find( '.ec_account_subscription_v2_plans input[type="radio"]' ).filter( function() {
			return String( this.value ) === match[ 1 ];
		} );
		if ( ! $panel.length || ! $( PANELS.plan.trigger ).length ) {
			return;
		}
		openPanel( 'plan' );
		if ( $radio.length ) {
			var interval = String( $radio.attr( 'data-interval' ) || '' );
			if ( interval && $panel.find( '[data-ec-sub-tab="' + interval + '"]' ).length ) {
				showTab( $panel, interval );
			}
			$radio.prop( 'checked', true ).trigger( 'change' );
		}
	} );

	$( document ).on( 'change', '.ec_account_subscription_v2_plans input[type="radio"]', function() {
		var $panel = $( this ).closest( '.ec_account_subscription_upgrade_row' );
		if ( $panel.find( '.ec_account_subscription_v2_review' ).length ) {
			closeReview( $panel );
		}
		$panel.find( 'input[name="ec_selected_plan"]' ).val( this.value );
		$panel.find( '.ec_account_subscription_v2_plan' ).removeClass( 'is-selected' );
		$( this ).closest( '.ec_account_subscription_v2_plan' ).addClass( 'is-selected' );
		planState( $panel.get( 0 ) );
	} );

	$( document ).on( 'click', '[data-ec-sub-step]', function( event ) {
		event.preventDefault();
		var $panel = $( this ).closest( '.ec_account_subscription_upgrade_row' );
		var $quantity = $panel.find( 'input.ec_account_subscription_v2_qty' );
		var value = parseInt( $quantity.val(), 10 );
		value = ( isNaN( value ) ? 1 : value ) + parseInt( $( this ).attr( 'data-ec-sub-step' ), 10 );
		value = Math.min( 1000000, Math.max( 1, value ) );
		$quantity.val( value );
		if ( $panel.find( '.ec_account_subscription_v2_review' ).length ) {
			closeReview( $panel );
		}
		planState( $panel.get( 0 ) );
	} );

	$( document ).on( 'input change', '.ec_account_subscription_v2 input.ec_account_subscription_v2_qty', function() {
		planState( $( this ).closest( '.ec_account_subscription_upgrade_row' ).get( 0 ) );
	} );

	/* Enter in the plan panel would submit the shared form into the card update; run Save instead. */
	$( document ).on( 'keydown', '.ec_account_subscription_v2 .ec_account_subscription_upgrade_row input', function( event ) {
		if ( 13 === event.which ) {
			event.preventDefault();
			var $save = $( this ).closest( '.ec_account_subscription_upgrade_row' ).find( '[data-ec-sub-save="plan"]' );
			if ( $save.length && ! $save.prop( 'disabled' ) ) {
				$save.trigger( 'click' );
			}
		}
	} );

	$( document ).on( 'click', '[data-ec-sub-close]', function( event ) {
		event.preventDefault();
		closePanel( 'plan' === $( this ).attr( 'data-ec-sub-close' ) ? 'plan' : 'payment' );
	} );

	$( document ).on( 'keydown', '.ec_account_subscription_v2 .ec_account_subscription_v2_panel', function( event ) {
		if ( 27 === event.which ) {
			closePanel( $( this ).is( PANELS.plan.panel ) ? 'plan' : 'payment' );
		}
	} );
} )( window.jQuery, window, document );
