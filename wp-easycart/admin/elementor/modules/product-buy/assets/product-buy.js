/**
 * WP EasyCart product purchase widgets for Elementor ( 6.0.2 ).
 *
 * - Add to Cart: the background add ( ec_ajax_add_to_cart_complete, the same answer handling as ec-store.js ), Buy now,
 *   "after adding" ( stay and confirm | side cart | cart ), swatch keyboard support. Options, quantity buttons and validation
 *   stay ec-store.js's ( never bound here: a second binding would count twice ).
 * - Product Stock / Product Badges: redrawn from the stock ec-store.js writes into their Phase 0 link
 *   ( .ec_details_stock_total_ele[data-wpec-linked-*] ) and from the wpeasycart_product_state event when it exists.
 * - Product Gallery: slider, thumbnails, dots, zoom, lightbox; option image sets switch when ec_option1_image_change()
 *   toggles ec_inactive on the sets, or from wpeasycart_product_state.
 *
 * Events for other scripts ( the side cart ):
 * - 'wpeasycart_item_added' ( e, item, form ), triggered on the add to cart form ( bubbles to document ) after a background
 *   add; the form's data-wpec-after-add says what the merchant chose ( stay | side_cart | cart ).
 * - 'wpeasycart_after_add' ( e, { mode, item, cart, form } ), a jQuery.Event on the form; call e.preventDefault() to show
 *   the cart yourself instead of the widget's "Added to your cart" message.
 */
( function( $, window, document ) {
	'use strict';

	if ( ! $ ) {
		return;
	}

	var WIDGETS = [ 'wp_easycart_add_to_cart', 'wp_easycart_product_price', 'wp_easycart_product_stock', 'wp_easycart_product_sku', 'wp_easycart_product_gallery', 'wp_easycart_product_badges' ];
	var canObserve = ( 'function' === typeof window.MutationObserver );

	function readTexts( el ) {
		try {
			return JSON.parse( el.getAttribute( 'data-wpec-texts' ) || '{}' ) || {};
		} catch ( err ) {
			return {};
		}
	}

	function prefersReducedMotion() {
		return !! ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches );
	}

	/* ------------------------------------------------------------ Add to Cart */

	var Cart = {
		/* A message under the button; it hides after a while unless sticky ( kept until the next message ). */
		status: function( $wrap, message, link, linkText, isError, sticky ) {
			var $status = $wrap.find( '.wpec-atc__status' ).first();
			if ( ! $status.length || ! message ) {
				return;
			}
			window.clearTimeout( $status.data( 'wpecTimer' ) );
			$status.empty().toggleClass( 'is-error', !! isError ).removeAttr( 'hidden' );
			$status.append( $( '<span class="wpec-atc__status-text"></span>' ).text( message ) );
			if ( link && linkText ) {
				$status.append( $( '<a class="wpec-atc__status-link"></a>' ).attr( 'href', link ).text( linkText ) );
			}
			if ( sticky ) {
				return;
			}
			$status.data( 'wpecTimer', window.setTimeout( function() {
				if ( ! $status.find( ':focus' ).length ) {
					$status.attr( 'hidden', 'hidden' ).empty();
				}
			}, 9000 ) );
		},

		/*
		 * No answer to a background add ( timeout, dropped connection ): the add may still have gone in, so nothing is sent
		 * again and the shopper is not asked to try again. The cart widgets ask the store what the cart holds, the message
		 * points to the cart, and the button comes back ( done ) only once that answer is in, so a quick second click cannot
		 * add the product twice. A refresh that never answers releases the button after 20 seconds.
		 */
		unconfirmed: function( $wrap, texts, done ) {
			var finished = false;
			var timer = null;
			var finish = function() {
				if ( finished ) {
					return;
				}
				finished = true;
				window.clearTimeout( timer );
				done();
			};
			Cart.status( $wrap, texts.unconfirmed || '', $wrap.attr( 'data-wpec-cart-url' ), texts.view_cart || '', true, true );
			timer = window.setTimeout( finish, 20000 );
			var request = null;
			try {
				if ( 'function' === typeof window.wpeasycart_refresh_cart_widgets ) {
					request = window.wpeasycart_refresh_cart_widgets();
				}
			} catch ( err ) {
				request = null;
			}
			if ( request && 'function' === typeof request.always ) {
				request.always( finish );
			} else {
				finish();
			}
		},

		/* Swatches as a radio group for keyboards and screen readers ( the click handlers are ec-store.js's ). */
		swatches: function( scope ) {
			$( scope ).find( '.wpec-atc .ec_details_swatches_ele' ).each( function() {
				var list = this;
				if ( list.wpecSwatches ) {
					Cart.syncSwatches( list );
					return;
				}
				list.wpecSwatches = true;
				var label = $( list ).siblings( '.ec_details_option_label_ele' ).first().clone().children().remove().end().text();
				$( list ).attr( 'role', 'radiogroup' );
				if ( $.trim( label ) ) {
					$( list ).attr( 'aria-label', $.trim( label ) );
				}
				Cart.syncSwatches( list );
				if ( canObserve ) {
					new window.MutationObserver( function() {
						Cart.syncSwatches( list );
					} ).observe( list, { attributes: true, attributeFilter: [ 'class' ], subtree: true } );
				}
			} );
		},

		syncSwatches: function( list ) {
			$( list ).children( 'li' ).each( function() {
				var selected = $( this ).hasClass( 'ec_selected' );
				var available = $( this ).hasClass( 'ec_active' ) || selected;
				if ( this.getAttribute( 'role' ) !== 'radio' ) {
					this.setAttribute( 'role', 'radio' );
					this.setAttribute( 'tabindex', '0' );
				}
				if ( this.getAttribute( 'aria-checked' ) !== String( selected ) ) {
					this.setAttribute( 'aria-checked', String( selected ) );
				}
				if ( this.getAttribute( 'aria-disabled' ) !== String( ! available ) ) {
					this.setAttribute( 'aria-disabled', String( ! available ) );
				}
				if ( ! this.getAttribute( 'aria-label' ) ) {
					var name = this.getAttribute( 'title' ) || $( this ).find( 'img' ).attr( 'alt' ) || $.trim( $( this ).text() );
					if ( name ) {
						this.setAttribute( 'aria-label', name );
					}
				}
			} );
		},

		buttonFor: function( $form, submitter ) {
			if ( submitter && $( submitter ).hasClass( 'wpec-atc__button' ) ) {
				return $( submitter );
			}
			return $form.find( '.wpec-atc__button--add' ).first();
		}
	};

	$( document ).on( 'click', '.wpec-atc [type="submit"]', function() {
		if ( this.form ) {
			$( this.form ).data( 'wpecSubmitter', this );
		}
	} );

	$( document ).on( 'keydown', '.wpec-atc .ec_details_swatches_ele > li', function( e ) {
		var key = e.key || e.keyCode;
		if ( 'Enter' === key || ' ' === key || 13 === key || 32 === key ) {
			e.preventDefault();
			$( this ).trigger( 'click' );
		} else if ( 'ArrowRight' === key || 'ArrowDown' === key || 'ArrowLeft' === key || 'ArrowUp' === key ) {
			var items = $( this ).parent().children( 'li:visible' );
			var index = items.index( this );
			var step = ( 'ArrowRight' === key || 'ArrowDown' === key ) ? 1 : -1;
			if ( 'rtl' === $( this ).closest( '[dir]' ).attr( 'dir' ) && ( 'ArrowRight' === key || 'ArrowLeft' === key ) ) {
				step = -step;
			}
			var next = items.eq( ( index + step + items.length ) % items.length );
			if ( next.length ) {
				e.preventDefault();
				next.trigger( 'focus' );
			}
		}
	} );

	/* Enter in the back-in-stock email box asks for the email ( it is not an add to cart ). */
	$( document ).on( 'keydown', '.wpec-atc .ec_out_of_stock_notify_input input', function( e ) {
		if ( 'Enter' === e.key || 13 === e.keyCode ) {
			e.preventDefault();
			$( this ).closest( '.ec_out_of_stock_notify' ).find( '.ec_out_of_stock_notify_button button' ).trigger( 'click' );
		}
	} );

	$( document ).on( 'submit', '.wpec-atc form.ec_add_to_cart_form', function( e ) {
		var form = this;
		var $form = $( form );
		var $wrap = $form.closest( '.wpec-atc' );
		var texts = readTexts( $wrap.get( 0 ) || form );
		var submitter = ( e.originalEvent && e.originalEvent.submitter ) ? e.originalEvent.submitter : $form.data( 'wpecSubmitter' );
		$form.removeData( 'wpecSubmitter' );

		if ( '1' === $wrap.attr( 'data-wpec-editor' ) ) {
			e.preventDefault();
			Cart.status( $wrap, texts.editor || '', '', '', false );
			return false;
		}
		var action = $form.find( 'input[name="ec_cart_form_action"]' ).last().val();
		var buyNow = !! ( submitter && submitter.getAttribute && '1' === submitter.getAttribute( 'data-wpec-buy-now' ) );
		var mode = $form.attr( 'data-wpec-after-add' ) || $wrap.attr( 'data-wpec-after-add' ) || 'stay';
		var ajaxUrl = ( window.wpeasycart_ajax_object && window.wpeasycart_ajax_object.ajax_url ) ? window.wpeasycart_ajax_object.ajax_url : $wrap.attr( 'data-wpec-ajax-url' );
		/* Subscriptions, inquiries and "go to the cart" post the form as always. */
		if ( 'add_to_cart_v3' !== action || 'undefined' === typeof window.FormData || ! ajaxUrl || ( 'cart' === mode && ! buyNow ) ) {
			return true;
		}
		e.preventDefault();
		if ( $form.data( 'wpeasycartAdding' ) ) {
			return false;
		}
		$form.data( 'wpeasycartAdding', true );
		var $button = Cart.buttonFor( $form, submitter );
		$button.addClass( 'is-loading' ).attr( 'aria-busy', 'true' ).prop( 'disabled', true );
		var data = new window.FormData( form );
		data.append( 'action', 'ec_ajax_add_to_cart_complete' );
		data.append( 'noredirect', '1' );

		var stopLoading = function() {
			$button.removeClass( 'is-loading' ).removeAttr( 'aria-busy' ).prop( 'disabled', false );
			$form.data( 'wpeasycartAdding', false );
		};
		/* Posted again only when the add cannot have run ( refused before adding ): never a second add. */
		var postNormally = function() {
			stopLoading();
			window.HTMLFormElement.prototype.submit.call( form );
		};
		/* The add may have gone in but its answer was unreadable: show the cart ( a plain visit, nothing is added again ). */
		var showCart = function() {
			stopLoading();
			var cartUrl = $wrap.attr( 'data-wpec-cart-url' ) || $form.attr( 'action' );
			if ( cartUrl ) {
				window.location.href = cartUrl;
			}
		};

		$.ajax( {
			url: ajaxUrl,
			type: 'post',
			data: data,
			dataType: 'text',
			processData: false,
			contentType: false,
			cache: false,
			timeout: 90000,
			success: function( result ) {
				var body = String( ( null == result ) ? '' : result ).replace( /^[﻿\s]+|\s+$/g, '' );
				if ( '' === body || '0' === body || '-1' === body ) {
					postNormally();
					return;
				}
				var json = null;
				try {
					json = JSON.parse( body );
				} catch ( err ) {
					try {
						json = ( body.indexOf( '[' ) > 0 ) ? JSON.parse( body.substring( body.indexOf( '[' ) ) ) : null;
					} catch ( err2 ) {
						json = null;
					}
				}
				if ( json && json[0] && false === json[0].added ) {
					postNormally();
					return;
				}
				if ( ! json || ! json[0] ) {
					showCart();
					return;
				}
				stopLoading();
				try {
					if ( 'function' === typeof window.wpeasycart_update_cart_widgets ) {
						window.wpeasycart_update_cart_widgets( json );
					}
				} catch ( err3 ) {}
				var item = json[0].item_added ? json[0].item_added : null;
				try {
					$form.trigger( 'wpeasycart_item_added', [ item, form ] );
				} catch ( err4 ) {}
				try {
					if ( 'function' === typeof window.wpeasycart_refresh_cart_widgets ) {
						window.wpeasycart_refresh_cart_widgets();
					}
				} catch ( err5 ) {}
				if ( buyNow ) {
					var checkout = $wrap.attr( 'data-wpec-checkout-url' );
					if ( checkout ) {
						window.location.href = checkout;
						return;
					}
				}
				var after = $.Event( 'wpeasycart_after_add' );
				try {
					$form.trigger( after, [ { mode: mode, item: item, cart: json, form: form } ] );
				} catch ( err6 ) {}
				$button.addClass( 'is-added' );
				window.setTimeout( function() {
					$button.removeClass( 'is-added' );
				}, 2000 );
				if ( ! after.isDefaultPrevented() ) {
					Cart.status( $wrap, texts.added || '', $wrap.attr( 'data-wpec-cart-url' ), texts.view_cart || '', false );
				}
			},
			error: function( xhr, textStatus ) {
				if ( 'timeout' === textStatus || ! xhr || ! xhr.status ) {
					Cart.unconfirmed( $wrap, texts, stopLoading ); /* no answer: the add may still go in, so nothing is sent again */
				} else if ( xhr.status >= 400 && xhr.status < 500 ) {
					postNormally();
				} else {
					showCart();
				}
			}
		} );
		return false;
	} );

	/* ------------------------------------------------------------ Stock ( Product Stock, stock note, Product Badges ) */

	var Stock = {
		/* What ec-store.js wrote into a Phase 0 stock link: { unlimited } or { quantity }, or null when unknown. */
		read: function( link ) {
			var span = link.querySelector( '[id^="ec_details_stock_quantity_"]' );
			if ( link.style && 'none' === link.style.display ) {
				return { unlimited: true }; /* wpeasycart_details_linked_stock() hides it for an option without stock tracking */
			}
			var text = span ? $.trim( span.textContent ) : '';
			if ( 'inf' === text ) {
				return { unlimited: true };
			}
			var quantity = parseInt( text, 10 );
			if ( isNaN( quantity ) ) {
				return null;
			}
			return ( quantity >= 10000000 ) ? { unlimited: true } : { quantity: quantity };
		},

		fromState: function( stock ) {
			if ( null === stock || 'undefined' === typeof stock || '' === stock ) {
				return null;
			}
			if ( 'inf' === stock ) {
				return { unlimited: true };
			}
			var quantity = parseInt( stock, 10 );
			if ( isNaN( quantity ) ) {
				return null;
			}
			return ( quantity >= 10000000 ) ? { unlimited: true } : { quantity: quantity };
		},

		renderLine: function( el, info ) {
			if ( ! info ) {
				return;
			}
			var texts = readTexts( el );
			var low = parseInt( el.getAttribute( 'data-wpec-low' ), 10 ) || 0;
			var counts = '1' === el.getAttribute( 'data-wpec-counts' );
			var backorders = '1' === el.getAttribute( 'data-wpec-backorders' );
			var barMax = parseInt( el.getAttribute( 'data-wpec-bar-max' ), 10 ) || 20;
			var state = 'in';
			var text = texts['in'] || '';
			var fill = 100;
			if ( ! info.unlimited ) {
				var quantity = info.quantity;
				fill = Math.max( 0, Math.min( 100, Math.round( ( quantity / barMax ) * 100 ) ) );
				if ( quantity <= 0 ) {
					state = backorders ? 'backorder' : 'out';
					text = texts[ state ] || '';
					fill = 0;
				} else if ( low > 0 && quantity <= low ) {
					state = 'low';
					text = counts ? String( texts.low || '' ).replace( '[count]', quantity ) : ( texts['in'] || '' );
				} else if ( counts ) {
					text = String( texts.count || '' ).replace( '[count]', quantity );
				}
			}
			$( el ).removeClass( 'is-in is-low is-out is-backorder' ).addClass( 'is-' + state );
			var $text = $( el ).find( '.wpec-stock__text' );
			if ( $text.text() !== text ) {
				$text.text( text );
			}
			$( el ).find( '.wpec-stock__bar-fill' ).css( 'width', fill + '%' );
		},

		renderBadges: function( el, info ) {
			if ( ! info ) {
				return;
			}
			var low = parseInt( el.getAttribute( 'data-wpec-low' ), 10 ) || 0;
			var backorders = '1' === el.getAttribute( 'data-wpec-backorders' );
			var isOut = ! info.unlimited && info.quantity <= 0 && ! backorders;
			var isLow = ! info.unlimited && info.quantity > 0 && low > 0 && info.quantity <= low;
			$( el ).find( '.wpec-badge--low' ).prop( 'hidden', ! isLow );
			$( el ).find( '.wpec-badge--out' ).prop( 'hidden', ! isOut );
			$( el ).prop( 'hidden', ! $( el ).children( '.wpec-badge:not([hidden]), .wpec-badge-offer' ).length );
		},

		render: function( el, info ) {
			if ( $( el ).hasClass( 'wpec-badges' ) ) {
				Stock.renderBadges( el, info );
			} else {
				Stock.renderLine( el, info );
			}
		},

		init: function( scope ) {
			$( scope ).find( '.wpec-stock, .wpec-badges' ).addBack( '.wpec-stock, .wpec-badges' ).each( function() {
				var el = this;
				var link = $( el ).children( '.wpec-stock__link' ).get( 0 );
				if ( ! link || el.wpecStock ) {
					return;
				}
				el.wpecStock = true;
				if ( canObserve ) {
					new window.MutationObserver( function() {
						Stock.render( el, Stock.read( link ) );
					} ).observe( link, { attributes: true, attributeFilter: [ 'style' ], childList: true, characterData: true, subtree: true } );
				}
			} );
		}
	};

	/* ------------------------------------------------------------ Offers ( Product Price, Your Price ) */

	/*
	 * The Offers price preview follows the shopper's options. The price template and the Add to Cart area print the offer's
	 * rules ( data-wpec-offer-rules, from WP EasyCart PRO 6.0.2's ec_offer_display::get_product_price_rules() ) next to the
	 * prices; after ec-store.js writes the price for the options chosen, wpeasycart_product_state gives it here ( state.price,
	 * before offers ) and the offer price is worked out again the way PRO works it out. Without rules ( an older PRO ) the
	 * plain price shows once the options change it, never a stale offer price.
	 */
	var Offers = {
		rules: function( el ) {
			var raw = el ? el.getAttribute( 'data-wpec-offer-rules' ) : null;
			if ( null === raw || '' === raw ) {
				return null;
			}
			try {
				var rules = JSON.parse( raw );
				return $.isArray( rules ) ? rules : null;
			} catch ( err ) {
				return null;
			}
		},

		/* The lowest price the rules give price ( price itself when none lowers it ): ec_offer_display::apply_price_rules(). */
		price: function( rules, price ) {
			price = Number( price );
			var best = price;
			if ( ! $.isArray( rules ) || ! ( price > 0 ) ) {
				return price;
			}
			$.each( rules, function( i, rule ) {
				var value = Number( rule && rule.value ) || 0;
				var floor = Number( rule && rule.floor ) || 0;
				var kind = rule ? rule.kind : 'percent';
				var preview;
				if ( 'percent' === kind ) {
					preview = Math.max( 0, price - ( price * value / 100 ) );
				} else if ( 'amount' === kind ) {
					preview = Math.max( 0, price - value );
				} else {
					preview = Math.min( price, value );
				}
				if ( floor > 0 && preview < floor ) {
					preview = Math.min( price, floor );
				}
				if ( preview < best ) {
					best = preview;
				}
			} );
			return best;
		},

		/* An amount as the form shows money ( ec-store.js's ec_details_format_money_v2() and the form's currency fields ). */
		format: function( state, amount ) {
			if ( 'function' !== typeof window.ec_details_format_money_v2 || ! document.getElementById( 'currency_symbol_' + state.product_id + '_' + state.rand_id ) ) {
				return null;
			}
			return window.ec_details_format_money_v2( state.product_id, state.rand_id, amount );
		},

		/* A formatted amount back to a number in the store's currency ( the reverse of format() ), or null. */
		parse: function( state, text ) {
			var key = state.product_id + '_' + state.rand_id;
			var field = function( id ) {
				var el = document.getElementById( id + '_' + key );
				return el ? String( el.value ) : '';
			};
			var s = String( text );
			$.each( [ field( 'currency_code' ), field( 'currency_symbol' ), field( 'grouping_symbol' ) ], function( i, part ) {
				if ( part ) {
					s = s.split( part ).join( '' );
				}
			} );
			s = s.split( field( 'decimal_symbol' ) || '.' ).join( '.' ).replace( /[^0-9.\-]/g, '' );
			var n = parseFloat( s );
			if ( isNaN( n ) ) {
				return null;
			}
			return n / ( Number( field( 'conversion_rate' ) ) || 1 );
		},

		/* The amount a price row shows: with or without VAT, as wpeasycart_details_linked_price() works it out. */
		vat: function( block, amount, state ) {
			var $block = $( block );
			if ( ! $block.hasClass( 'ec_details_no_vat_price' ) && ! $block.hasClass( 'ec_details_vat_price' ) ) {
				return amount;
			}
			var key = state.product_id + '_' + state.rand_id;
			var added = $block.attr( 'data-wpec-vat-added' );
			var multiplier = Number( $block.attr( 'data-wpec-vat-multiplier' ) );
			if ( 'undefined' === typeof added ) {
				added = $( document.getElementById( 'vat_added_' + key ) ).val();
			}
			if ( ! ( multiplier > 0 ) ) {
				multiplier = Number( $( document.getElementById( 'vat_rate_multiplier_' + key ) ).val() ) || 1;
			}
			if ( $block.hasClass( 'ec_details_no_vat_price' ) ) {
				return ( '1' === String( added ) ) ? amount : amount / multiplier;
			}
			return ( '1' === String( added ) ) ? amount * multiplier : amount;
		},

		/* One price row of the price template that carried an offer ( data-wpec-offer ). Returns whether the offer applies. */
		block: function( block, state ) {
			var price = Number( state.price );
			var rules = Offers.rules( block );
			var base = Number( block.getAttribute( 'data-wpec-offer-base' ) );
			if ( null === rules && Math.abs( price - base ) < 0.000001 ) {
				return $( block ).hasClass( 'ec_details_price_has_offer' ); /* an older PRO: the page's own offer price stays */
			}
			var offered = ( null === rules ) ? price : Offers.price( rules, price );
			var on = offered < price - 0.000001;
			var money = function( amount ) {
				return Offers.format( state, Offers.vat( block, amount, state ) );
			};
			if ( null === money( price ) ) {
				return on;
			}
			var $block = $( block );
			var strike = $block.children( '.ec_offer_price_strike' );
			var preview = $block.children( '.ec_offer_price_preview' );
			if ( preview.length ) {
				strike.text( money( price ) ).prop( 'hidden', ! on );
				preview.text( money( offered ) );
			} else {
				/* VAT rows: ec-store.js wrote the price before offers into the price; the struck price is the regular price. */
				var list = Number( block.getAttribute( 'data-wpec-list-price' ) ) || 0;
				var regular = on ? Math.max( list, price ) : list;
				$block.children( '.ec_product_sale_price_ele, .ec_product_price_ele' ).first().text( money( offered ) );
				$block.children( '.ec_product_old_price_ele' ).text( money( regular ) ).prop( 'hidden', ! ( regular > offered ) );
			}
			$block.toggleClass( 'ec_details_price_has_offer', on );
			return on;
		},

		/* The "You save" line of a Product Price widget. */
		savings: function( line, state, widget ) {
			var price = Number( state.price );
			var rules = Offers.rules( line );
			var offered = ( null === rules ) ? price : Offers.price( rules, price );
			var regular = Math.max( Number( line.getAttribute( 'data-wpec-list-price' ) ) || 0, price );
			var amount = regular - offered;
			var vatRow = $( widget ).children( '.ec_details_vat_price' ).get( 0 );
			var text = '';
			if ( amount > 0.004 ) {
				var shown = Offers.format( state, vatRow ? Offers.vat( vatRow, amount, state ) : amount );
				if ( null === shown ) {
					return;
				}
				text = String( line.getAttribute( 'data-wpec-save-text' ) || '' ).replace( '[amount]', shown ).replace( '[percent]', String( Math.max( 1, Math.round( 100 - ( ( offered / regular ) * 100 ) ) ) ) );
			}
			$( line ).text( text ).prop( 'hidden', '' === text );
		},

		/* "Your price" of an add to cart form ( #ec_final_price_* ), when its wrapper carries an offer. */
		yourPrice: function( state ) {
			var final = document.getElementById( 'ec_final_price_' + state.product_id + '_' + state.rand_id );
			var wrap = final ? $( final ).closest( '[data-wpec-offer]' ) : $();
			if ( ! wrap.length ) {
				return;
			}
			var price = Number( state.price );
			var rules = Offers.rules( wrap.get( 0 ) );
			var offered = ( null === rules ) ? price : Offers.price( rules, price );
			var written = $.trim( $( final ).text() );
			var plain = Offers.format( state, price );
			var amount = offered;
			if ( ! ( offered < price - 0.000001 ) || null === plain ) {
				return; /* nothing to take off: the price ec-store.js wrote stands */
			}
			if ( written !== $.trim( plain ) ) {
				if ( written === $( final ).data( 'wpecOfferText' ) || written === $.trim( Offers.format( state, offered ) ) ) {
					$( final ).data( 'wpecOfferText', written );
					return; /* already this offer's price ( an event for the stock or the SKU only, or ec-store.js applied it ) */
				}
				/* Area pricing or a quantity grid: ec-store.js wrote more than the unit price; the offer scales it. */
				var shown = Offers.parse( state, written );
				if ( null === shown ) {
					return;
				}
				amount = shown * ( offered / price );
			}
			var text = Offers.format( state, amount );
			$( final ).text( text ).data( 'wpecOfferText', $.trim( text ) );
		},

		update: function( state ) {
			if ( ! state || ! state.product_id || null === state.price || 'undefined' === typeof state.price || isNaN( Number( state.price ) ) ) {
				return;
			}
			var selector = '[data-product-id="' + parseInt( state.product_id, 10 ) + '"]';
			$( '.wpec-price' + selector ).each( function() {
				var widget = this;
				var any = false;
				$( widget ).children( '.ec_details_price[data-wpec-offer]' ).each( function() {
					any = Offers.block( this, state ) || any;
				} );
				if ( $( widget ).children( '.ec_details_price[data-wpec-offer]' ).length ) {
					$( widget ).toggleClass( 'wpec-price--has-offer', any );
				}
				$( widget ).children( '.wpec-price__save' ).each( function() {
					Offers.savings( this, state, widget );
				} );
			} );
			Offers.yourPrice( state );
		},

		init: function( scope ) {
			$( scope ).find( '[data-wpec-offer] [id^="ec_final_price_"]' ).each( function() {
				if ( 'undefined' === typeof $( this ).data( 'wpecOfferText' ) ) {
					$( this ).data( 'wpecOfferText', $.trim( $( this ).text() ) );
				}
			} );
		}
	};

	window.wpeasycart_offer_price = Offers.price;

	/* ------------------------------------------------------------ Gallery */

	var Lightbox = {
		el: null,
		items: [],
		index: 0,
		opener: null,
		gallery: null,

		/* The colours of the gallery that opened it ( Style › Larger image ): the lightbox is one element on body. */
		COLORS: [ '--wpec-lb-bg', '--wpec-lb-color', '--wpec-lb-button-bg' ],

		build: function( texts ) {
			if ( Lightbox.el ) {
				return Lightbox.el;
			}
			var $box = $( '<div class="wpec-lightbox" role="dialog" aria-modal="true" hidden></div>' );
			$box.append( $( '<button type="button" class="wpec-lightbox__button wpec-lightbox__close"><span aria-hidden="true">&times;</span></button>' ).attr( 'aria-label', texts.close || 'Close' ) );
			$box.append( $( '<button type="button" class="wpec-lightbox__button wpec-lightbox__prev"><span aria-hidden="true">&#8249;</span></button>' ).attr( 'aria-label', texts.previous || 'Previous image' ) );
			$box.append( $( '<button type="button" class="wpec-lightbox__button wpec-lightbox__next"><span aria-hidden="true">&#8250;</span></button>' ).attr( 'aria-label', texts.next || 'Next image' ) );
			$box.append( '<figure class="wpec-lightbox__figure"><img class="wpec-lightbox__img" alt="" /></figure><div class="wpec-lightbox__thumbs" hidden></div><div class="wpec-lightbox__count" aria-live="polite"></div>' );
			$( 'body' ).append( $box );
			$box.on( 'click', function( e ) {
				if ( e.target === this ) {
					Lightbox.close();
				}
			} );
			$box.on( 'click', '.wpec-lightbox__close', Lightbox.close );
			$box.on( 'click', '.wpec-lightbox__prev', function() {
				Lightbox.show( Lightbox.index - 1 );
			} );
			$box.on( 'click', '.wpec-lightbox__next', function() {
				Lightbox.show( Lightbox.index + 1 );
			} );
			$box.on( 'click', '.wpec-lightbox__thumb', function() {
				Lightbox.show( parseInt( $( this ).attr( 'data-index' ), 10 ) || 0 );
			} );
			$box.on( 'keydown', function( e ) {
				var rtl = 'rtl' === document.documentElement.getAttribute( 'dir' );
				if ( 'Escape' === e.key || 27 === e.keyCode ) {
					Lightbox.close();
				} else if ( 'ArrowLeft' === e.key && ! $( e.target ).is( 'video' ) ) {
					Lightbox.show( Lightbox.index + ( rtl ? 1 : -1 ) );
				} else if ( 'ArrowRight' === e.key && ! $( e.target ).is( 'video' ) ) {
					Lightbox.show( Lightbox.index + ( rtl ? -1 : 1 ) );
				} else if ( 'Tab' === e.key ) {
					var focusable = $box.find( 'button:visible, video:visible, iframe:visible' );
					var first = focusable.get( 0 );
					var last = focusable.get( focusable.length - 1 );
					if ( e.shiftKey && document.activeElement === first ) {
						e.preventDefault();
						last.focus();
					} else if ( ! e.shiftKey && document.activeElement === last ) {
						e.preventDefault();
						first.focus();
					}
				}
			} );
			Lightbox.el = $box.get( 0 );
			return Lightbox.el;
		},

		/*
		 * items: [ { src, alt, type ( image | video | embed ), video, thumb } ]; options: counter, thumbs ( booleans ).
		 */
		open: function( items, index, opener, texts, gallery, options ) {
			if ( ! items.length ) {
				return;
			}
			options = options || {};
			var box = Lightbox.build( texts );
			Lightbox.items = items;
			Lightbox.opener = opener;
			Lightbox.gallery = gallery || null;
			Lightbox.counter = ( false !== options.counter );
			var style = ( gallery && window.getComputedStyle ) ? window.getComputedStyle( gallery ) : null;
			$.each( Lightbox.COLORS, function( i, name ) {
				var value = style ? $.trim( style.getPropertyValue( name ) ) : '';
				if ( value ) {
					box.style.setProperty( name, value );
				} else {
					box.style.removeProperty( name );
				}
			} );
			var $thumbs = $( box ).find( '.wpec-lightbox__thumbs' ).empty();
			if ( options.thumbs && items.length > 1 ) {
				$.each( items, function( i, item ) {
					var $b = $( '<button type="button" class="wpec-lightbox__thumb"></button>' ).attr( { 'data-index': i, 'aria-label': String( texts.thumbnail || 'Show image [number]' ).replace( '[number]', i + 1 ) } );
					$b.append( $( '<img alt="" loading="lazy" />' ).attr( 'src', item.thumb || item.src ) );
					$thumbs.append( $b );
				} );
				$thumbs.removeAttr( 'hidden' );
				$( box ).addClass( 'wpec-lightbox--thumbs' );
			} else {
				$thumbs.attr( 'hidden', 'hidden' );
				$( box ).removeClass( 'wpec-lightbox--thumbs' );
			}
			$( box ).attr( 'aria-label', texts.open || '' ).removeAttr( 'hidden' );
			$( box ).find( '.wpec-lightbox__prev, .wpec-lightbox__next' ).prop( 'hidden', items.length < 2 );
			$( 'body' ).addClass( 'wpec-lightbox-open' );
			Lightbox.show( index );
			$( box ).find( '.wpec-lightbox__close' ).trigger( 'focus' );
			if ( gallery ) {
				Autoplay.stop( gallery );
			}
		},

		isOpen: function() {
			return !! ( Lightbox.el && ! Lightbox.el.hasAttribute( 'hidden' ) );
		},

		show: function( index ) {
			var count = Lightbox.items.length;
			if ( ! count ) {
				return;
			}
			Lightbox.index = ( index + count ) % count;
			var item = Lightbox.items[ Lightbox.index ];
			var $figure = $( Lightbox.el ).find( '.wpec-lightbox__figure' );
			var $img = $figure.find( '.wpec-lightbox__img' );
			$figure.find( '.wpec-lightbox__video, .wpec-lightbox__embed' ).remove();
			if ( 'video' === item.type && item.video ) {
				$img.attr( 'hidden', 'hidden' );
				$figure.append( $( '<video class="wpec-lightbox__video" controls playsinline autoplay></video>' ).attr( 'poster', item.src || null ).append( $( '<source />' ).attr( 'src', item.video ) ) );
			} else if ( 'embed' === item.type && item.video && /^https?:\/\//i.test( item.video ) ) {
				$img.attr( 'hidden', 'hidden' );
				$figure.append( $( '<iframe class="wpec-lightbox__embed" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>' ).attr( { src: item.video, title: item.alt || '' } ) );
			} else {
				$img.removeAttr( 'hidden' ).attr( { src: item.src, alt: item.alt } );
			}
			$( Lightbox.el ).find( '.wpec-lightbox__count' ).text( ( count > 1 && Lightbox.counter ) ? ( Lightbox.index + 1 ) + ' / ' + count : '' ).prop( 'hidden', ! Lightbox.counter );
			$( Lightbox.el ).find( '.wpec-lightbox__thumb' ).removeClass( 'is-active' ).removeAttr( 'aria-current' ).eq( Lightbox.index ).addClass( 'is-active' ).attr( 'aria-current', 'true' );
		},

		close: function() {
			if ( ! Lightbox.el ) {
				return;
			}
			$( Lightbox.el ).find( '.wpec-lightbox__video, .wpec-lightbox__embed' ).remove();
			$( Lightbox.el ).attr( 'hidden', 'hidden' );
			$( 'body' ).removeClass( 'wpec-lightbox-open' );
			if ( Lightbox.opener && Lightbox.opener.focus ) {
				Lightbox.opener.focus();
			}
			if ( Lightbox.gallery ) {
				Autoplay.start( Lightbox.gallery );
			}
			Lightbox.gallery = null;
		}
	};

	/* The gallery's choices ( data-wpec-* printed by the widget ). */
	function galleryOption( gallery, name, fallback ) {
		var value = gallery ? gallery.getAttribute( 'data-wpec-' + name ) : null;
		return ( null === value || '' === value ) ? fallback : value;
	}

	/* Autoplay: the active set of a gallery moves on by itself; never while the lightbox is open, while the pointer or the
	 * keyboard is on the gallery ( with "pause on hover" ), while the page is hidden, after the shopper paused it, or for
	 * visitors who ask for less motion. */
	var Autoplay = {
		interval: function( gallery ) {
			var ms = parseInt( galleryOption( gallery, 'autoplay', '0' ), 10 ) || 0;
			return ( ms > 0 && ! prefersReducedMotion() ) ? Math.max( 1500, ms ) : 0;
		},

		stop: function( gallery ) {
			if ( gallery && gallery.wpecAutoplay ) {
				window.clearTimeout( gallery.wpecAutoplay );
				gallery.wpecAutoplay = null;
			}
		},

		start: function( gallery ) {
			Autoplay.stop( gallery );
			var ms = Autoplay.interval( gallery );
			if ( ! ms || gallery.wpecPaused || gallery.wpecHover || Lightbox.isOpen() || document.hidden ) {
				return;
			}
			gallery.wpecAutoplay = window.setTimeout( function() {
				gallery.wpecAutoplay = null;
				var set = $( gallery ).children( '.wpec-gallery__set' ).not( '.ec_inactive' ).get( 0 );
				var count = set ? Gallery.slides( set ).length : 0;
				var playing = set && Gallery.slides( set ).eq( Gallery.current( set ) ).find( 'video' ).filter( function() {
					return ! this.paused && ! this.muted;
				} ).length;
				if ( count > 1 && ! playing ) {
					Gallery.go( set, ( Gallery.current( set ) + 1 ) % count, false, true );
				}
				Autoplay.start( gallery );
			}, ms );
		},

		init: function( gallery ) {
			if ( ! Autoplay.interval( gallery ) ) {
				$( gallery ).find( '.wpec-gallery__autoplay' ).attr( 'hidden', 'hidden' );
				return;
			}
			var pause = '1' === galleryOption( gallery, 'autoplay-pause', '1' );
			if ( pause ) {
				$( gallery ).on( 'mouseenter focusin', function() {
					gallery.wpecHover = true;
					Autoplay.stop( gallery );
				} ).on( 'mouseleave focusout', function( e ) {
					if ( 'focusout' === e.type && e.relatedTarget && $.contains( gallery, e.relatedTarget ) ) {
						return;
					}
					gallery.wpecHover = false;
					Autoplay.start( gallery );
				} );
			}
			Autoplay.start( gallery );
		}
	};

	$( document ).on( 'visibilitychange', function() {
		$( '.wpec-gallery' ).each( function() {
			if ( document.hidden ) {
				Autoplay.stop( this );
			} else if ( this.wpecGallery ) {
				Autoplay.start( this );
			}
		} );
	} );

	$( document ).on( 'click', '.wpec-gallery__autoplay', function() {
		var gallery = $( this ).closest( '.wpec-gallery' ).get( 0 );
		var texts = readTexts( gallery );
		gallery.wpecPaused = ! gallery.wpecPaused;
		$( this ).attr( 'aria-pressed', gallery.wpecPaused ? 'true' : 'false' ).attr( 'aria-label', gallery.wpecPaused ? ( texts.resume || 'Play' ) : ( texts.pause || 'Pause' ) );
		if ( gallery.wpecPaused ) {
			Autoplay.stop( gallery );
		} else {
			gallery.wpecHover = false;
			Autoplay.start( gallery );
		}
	} );

	var Gallery = {
		slides: function( set ) {
			return $( set ).find( '.wpec-gallery__slide' );
		},

		current: function( set ) {
			var index = parseInt( $( set ).attr( 'data-wpec-index' ), 10 );
			return isNaN( index ) ? 0 : index;
		},

		gallery: function( set ) {
			return $( set ).closest( '.wpec-gallery' ).get( 0 );
		},

		fade: function( set ) {
			return 'fade' === galleryOption( Gallery.gallery( set ), 'transition', 'slide' );
		},

		loop: function( set ) {
			return '1' === galleryOption( Gallery.gallery( set ), 'loop', '0' );
		},

		/* Scrolls the track to left over the widget's speed ( easeInOut ); snapping is off while it moves. */
		animate: function( track, left, speed ) {
			var start = track.scrollLeft;
			var distance = left - start;
			var begin = null;
			if ( track.wpecFrame ) {
				window.cancelAnimationFrame( track.wpecFrame );
			}
			track.style.scrollSnapType = 'none';
			track.style.scrollBehavior = 'auto';
			var step = function( time ) {
				if ( null === begin ) {
					begin = time;
				}
				var t = Math.min( 1, ( time - begin ) / speed );
				var eased = ( t < 0.5 ) ? 2 * t * t : 1 - Math.pow( -2 * t + 2, 2 ) / 2;
				track.scrollLeft = start + distance * eased;
				if ( t < 1 ) {
					track.wpecFrame = window.requestAnimationFrame( step );
				} else {
					track.wpecFrame = null;
					track.style.scrollSnapType = '';
					track.style.scrollBehavior = '';
				}
			};
			track.wpecFrame = window.requestAnimationFrame( step );
		},

		go: function( set, index, instant, auto ) {
			var slides = Gallery.slides( set );
			if ( ! slides.length ) {
				return;
			}
			if ( Gallery.loop( set ) || auto ) {
				index = ( index + slides.length ) % slides.length;
			} else {
				index = Math.max( 0, Math.min( slides.length - 1, index ) );
			}
			var track = $( set ).find( '.wpec-gallery__track' ).get( 0 );
			if ( track && ! Gallery.fade( set ) ) {
				var left = slides.get( index ).offsetLeft - slides.get( 0 ).offsetLeft;
				var speed = parseInt( galleryOption( Gallery.gallery( set ), 'speed', '-1' ), 10 );
				if ( instant || prefersReducedMotion() || 0 === speed ) {
					track.scrollLeft = left;
				} else if ( speed > 0 && window.requestAnimationFrame ) {
					Gallery.animate( track, left, speed );
				} else if ( track.scrollTo ) {
					track.scrollTo( { left: left, behavior: 'smooth' } );
				} else {
					track.scrollLeft = left;
				}
			}
			Gallery.mark( set, index );
		},

		mark: function( set, index ) {
			var $set = $( set );
			if ( Gallery.current( set ) === index && $set.attr( 'data-wpec-index' ) ) {
				return;
			}
			$set.attr( 'data-wpec-index', index );
			var slides = Gallery.slides( set );
			slides.removeClass( 'is-active' ).eq( index ).addClass( 'is-active' );
			/* A video that scrolled away stops; with "play videos by themselves" the shown one plays ( muted ). */
			slides.not( slides.eq( index ) ).find( 'video' ).each( function() {
				if ( ! this.paused ) {
					this.pause();
				}
			} );
			Gallery.autoVideo( set );
			Zoom.hide( set );
			var thumbs = $set.find( '.wpec-gallery__thumb' );
			thumbs.removeClass( 'is-active' ).removeAttr( 'aria-current' );
			var thumb = thumbs.eq( index ).addClass( 'is-active' ).attr( 'aria-current', 'true' ).get( 0 );
			var list = $set.find( '.wpec-gallery__thumbs' ).get( 0 );
			if ( thumb && list ) {
				/* Keep the chosen thumbnail in view without moving the page. */
				if ( thumb.offsetLeft < list.scrollLeft || thumb.offsetLeft + thumb.offsetWidth > list.scrollLeft + list.clientWidth ) {
					list.scrollLeft = thumb.offsetLeft - ( list.clientWidth - thumb.offsetWidth ) / 2;
				}
				if ( thumb.offsetTop < list.scrollTop || thumb.offsetTop + thumb.offsetHeight > list.scrollTop + list.clientHeight ) {
					list.scrollTop = thumb.offsetTop - ( list.clientHeight - thumb.offsetHeight ) / 2;
				}
				Thumbs.update( list );
			}
			$set.find( '.wpec-gallery__dot' ).removeClass( 'is-active' ).eq( index ).addClass( 'is-active' );
			var loop = Gallery.loop( set );
			$set.find( '.wpec-gallery__nav--prev' ).prop( 'disabled', ! loop && 0 === index );
			$set.find( '.wpec-gallery__nav--next' ).prop( 'disabled', ! loop && index >= slides.length - 1 );
			Gallery.hook( set );
		},

		autoVideo: function( set ) {
			var gallery = Gallery.gallery( set );
			if ( '1' !== galleryOption( gallery, 'video-autoplay', '0' ) || prefersReducedMotion() || $( set ).hasClass( 'ec_inactive' ) ) {
				return;
			}
			Gallery.slides( set ).eq( Gallery.current( set ) ).find( 'video' ).each( function() {
				this.muted = true;
				if ( this.paused && this.play ) {
					var played = this.play();
					if ( played && played.catch ) {
						played.catch( function() {} );
					}
				}
			} );
		},

		/* The hidden hook's image is the one shown: wpeasycart_product_state reports it as state.image. */
		hook: function( set ) {
			if ( $( set ).hasClass( 'ec_inactive' ) ) {
				return;
			}
			var img = Gallery.slides( set ).eq( Gallery.current( set ) ).find( 'img' ).first();
			var src = img.length ? ( img.get( 0 ).currentSrc || img.attr( 'src' ) ) : '';
			if ( src ) {
				$( set ).closest( '.wpec-gallery' ).children( '.wpec-gallery__hook' ).find( 'img' ).attr( 'src', src );
			}
		},

		/* The shown set changed ( ec-store.js or a state event toggled ec_inactive ): start it at its first image. */
		sync: function( gallery ) {
			var active = $( gallery ).children( '.wpec-gallery__set' ).not( '.ec_inactive' ).get( 0 );
			if ( ! active || gallery.wpecActiveSet === active ) {
				return;
			}
			gallery.wpecActiveSet = active;
			$( gallery ).children( '.wpec-gallery__set' ).removeClass( 'is-active' );
			$( active ).addClass( 'is-active' ).removeAttr( 'data-wpec-index' );
			Gallery.go( active, 0, true );
			Thumbs.update( $( active ).find( '.wpec-gallery__thumbs' ).get( 0 ) );
		},

		showSet: function( gallery, optionitemId ) {
			var target = $( gallery ).children( '.wpec-gallery__set[data-wpec-set="' + optionitemId + '"]' );
			if ( ! target.length ) {
				return false;
			}
			$( gallery ).children( '.wpec-gallery__set' ).addClass( 'ec_inactive' );
			target.removeClass( 'ec_inactive' );
			Gallery.sync( gallery );
			return true;
		},

		/* The items the lightbox shows for a set ( images; videos too when the widget asks ). */
		lightboxItems: function( set, slide, videos ) {
			var items = [];
			var start = 0;
			$( set ).find( '.wpec-gallery__slide' ).each( function( i ) {
				var type = $( this ).attr( 'data-type' ) || 'image';
				if ( 'image' !== type && ! videos ) {
					return;
				}
				if ( this === slide ) {
					start = items.length;
				}
				var img = $( this ).find( 'img' ).first();
				var thumb = $( set ).find( '.wpec-gallery__thumb' ).eq( i ).find( 'img' ).attr( 'src' );
				items.push( {
					type: type,
					src: ( 'image' === type ) ? ( $( this ).attr( 'data-full' ) || img.attr( 'src' ) ) : ( $( this ).find( 'video' ).attr( 'poster' ) || img.attr( 'src' ) || '' ),
					video: $( this ).attr( 'data-video' ) || $( this ).find( 'video source' ).attr( 'src' ) || $( this ).find( '.wpec-gallery__play' ).attr( 'data-embed' ) || '',
					alt: img.attr( 'alt' ) || '',
					thumb: thumb || img.attr( 'src' ) || ''
				} );
			} );
			return { items: items, start: start };
		},

		openLightbox: function( gallery, set, slide, opener ) {
			if ( '1' !== galleryOption( gallery, 'lightbox', '0' ) ) {
				return;
			}
			var list = Gallery.lightboxItems( set, slide, '1' === galleryOption( gallery, 'lightbox-videos', '0' ) );
			Lightbox.open( list.items, list.start, opener, readTexts( gallery ), gallery, {
				counter: '0' !== galleryOption( gallery, 'lightbox-counter', '1' ),
				thumbs: '1' === galleryOption( gallery, 'lightbox-thumbs', '0' )
			} );
		},

		init: function( scope ) {
			$( scope ).find( '.wpec-gallery' ).addBack( '.wpec-gallery' ).each( function() {
				var gallery = this;
				if ( gallery.wpecGallery ) {
					return;
				}
				gallery.wpecGallery = true;
				var fade = 'fade' === galleryOption( gallery, 'transition', 'slide' );
				$( gallery ).children( '.wpec-gallery__set' ).each( function() {
					var set = this;
					var track = $( set ).find( '.wpec-gallery__track' ).get( 0 );
					Gallery.mark( set, 0 );
					Thumbs.init( set );
					if ( ! track ) {
						return;
					}
					if ( fade ) {
						Swipe.init( set, track );
						return;
					}
					var pending = false;
					$( track ).on( 'scroll', function() {
						if ( pending || track.wpecFrame ) {
							return;
						}
						pending = true;
						window.requestAnimationFrame( function() {
							pending = false;
							var width = track.clientWidth || 1;
							Gallery.mark( set, Math.round( Math.abs( track.scrollLeft ) / width ) );
						} );
					} );
				} );
				gallery.wpecActiveSet = $( gallery ).children( '.wpec-gallery__set' ).not( '.ec_inactive' ).get( 0 );
				if ( canObserve ) {
					/* ec_option1_image_change_gallery() switches sets by toggling ec_inactive on them. */
					$( gallery ).children( '.wpec-gallery__set' ).each( function() {
						new window.MutationObserver( function() {
							Gallery.sync( gallery );
						} ).observe( this, { attributes: true, attributeFilter: [ 'class' ] } );
					} );
				}
				Autoplay.init( gallery );
			} );
		}
	};

	/* Swipes for the fade transition ( its track does not scroll ). */
	var Swipe = {
		init: function( set, track ) {
			var startX = null;
			var startY = null;
			track.addEventListener( 'touchstart', function( e ) {
				if ( e.touches && 1 === e.touches.length ) {
					startX = e.touches[0].clientX;
					startY = e.touches[0].clientY;
				}
			}, { passive: true } );
			track.addEventListener( 'touchend', function( e ) {
				if ( null === startX || ! e.changedTouches || ! e.changedTouches.length ) {
					return;
				}
				var dx = e.changedTouches[0].clientX - startX;
				var dy = e.changedTouches[0].clientY - startY;
				startX = null;
				if ( Math.abs( dx ) > 40 && Math.abs( dx ) > Math.abs( dy ) ) {
					var rtl = 'rtl' === $( track ).css( 'direction' );
					Gallery.go( set, Gallery.current( set ) + ( ( dx < 0 ) !== rtl ? 1 : -1 ) );
				}
			}, { passive: true } );
		}
	};

	/* The thumbnail strip's own arrows ( "Arrows on the strip" ): shown only while the strip has more than fits. */
	var Thumbs = {
		vertical: function( list ) {
			return 'column' === $( list ).css( 'flex-direction' );
		},

		update: function( list ) {
			var wrap = list ? $( list ).parent( '.wpec-gallery__thumbs-wrap' ) : $();
			if ( ! wrap.length ) {
				return;
			}
			var vertical = Thumbs.vertical( list );
			var position = vertical ? list.scrollTop : Math.abs( list.scrollLeft );
			var size = vertical ? list.clientHeight : list.clientWidth;
			var full = vertical ? list.scrollHeight : list.scrollWidth;
			var more = full > size + 2;
			wrap.children( '.wpec-gallery__thumbs-nav' ).prop( 'hidden', ! more );
			wrap.children( '.wpec-gallery__thumbs-nav--prev' ).prop( 'disabled', position <= 1 );
			wrap.children( '.wpec-gallery__thumbs-nav--next' ).prop( 'disabled', position + size >= full - 1 );
		},

		init: function( set ) {
			var list = $( set ).find( '.wpec-gallery__thumbs' ).get( 0 );
			if ( ! list || ! $( list ).parent( '.wpec-gallery__thumbs-wrap' ).length ) {
				return;
			}
			var pending = false;
			$( list ).on( 'scroll', function() {
				if ( pending ) {
					return;
				}
				pending = true;
				window.requestAnimationFrame( function() {
					pending = false;
					Thumbs.update( list );
				} );
			} );
			$( window ).on( 'resize', function() {
				Thumbs.update( list );
			} );
			Thumbs.update( list );
		}
	};

	$( document ).on( 'click', '.wpec-gallery__thumbs-nav', function() {
		var list = $( this ).siblings( '.wpec-gallery__thumbs' ).get( 0 );
		if ( ! list ) {
			return;
		}
		var step = $( this ).hasClass( 'wpec-gallery__thumbs-nav--prev' ) ? -1 : 1;
		var behavior = prefersReducedMotion() ? 'auto' : 'smooth';
		if ( Thumbs.vertical( list ) ) {
			if ( list.scrollBy ) {
				list.scrollBy( { top: step * list.clientHeight * 0.8, behavior: behavior } );
			} else {
				list.scrollTop += step * list.clientHeight * 0.8;
			}
		} else {
			var rtl = 'rtl' === $( list ).css( 'direction' );
			var amount = step * list.clientWidth * 0.8 * ( rtl ? -1 : 1 );
			if ( list.scrollBy ) {
				list.scrollBy( { left: amount, behavior: behavior } );
			} else {
				list.scrollLeft += amount;
			}
		}
	} );

	$( document ).on( 'click', '.wpec-gallery__thumb', function() {
		Gallery.go( $( this ).closest( '.wpec-gallery__set' ).get( 0 ), parseInt( $( this ).attr( 'data-index' ), 10 ) || 0 );
	} );

	$( document ).on( 'click', '.wpec-gallery__dot', function() {
		Gallery.go( $( this ).closest( '.wpec-gallery__set' ).get( 0 ), parseInt( $( this ).attr( 'data-index' ), 10 ) || 0 );
	} );

	$( document ).on( 'click', '.wpec-gallery__nav', function() {
		var set = $( this ).closest( '.wpec-gallery__set' ).get( 0 );
		var step = $( this ).hasClass( 'wpec-gallery__nav--prev' ) ? -1 : 1;
		Gallery.go( set, Gallery.current( set ) + step );
	} );

	$( document ).on( 'keydown', '.wpec-gallery__track', function( e ) {
		var set = $( this ).closest( '.wpec-gallery__set' ).get( 0 );
		var rtl = 'rtl' === $( this ).css( 'direction' );
		var key = e.key;
		var index = Gallery.current( set );
		if ( 'ArrowLeft' === key ) {
			index += rtl ? 1 : -1;
		} else if ( 'ArrowRight' === key ) {
			index += rtl ? -1 : 1;
		} else if ( 'Home' === key ) {
			index = 0;
		} else if ( 'End' === key ) {
			index = Gallery.slides( set ).length - 1;
		} else {
			return;
		}
		e.preventDefault();
		Gallery.go( set, index );
	} );

	$( document ).on( 'click', '.wpec-gallery__open', function( e ) {
		var gallery = $( this ).closest( '.wpec-gallery' ).get( 0 );
		var slide = $( this ).closest( '.wpec-gallery__slide' ).get( 0 );
		/* Click to zoom: a mouse click zooms ( and a second one stops ); the keyboard still opens the larger image. */
		if ( Zoom.byClick( gallery ) && e.detail > 0 ) {
			Zoom.toggle( slide, e );
			return;
		}
		Gallery.openLightbox( gallery, $( this ).closest( '.wpec-gallery__set' ).get( 0 ), slide, this );
	} );

	$( document ).on( 'click', '.wpec-gallery__expand', function() {
		var gallery = $( this ).closest( '.wpec-gallery' ).get( 0 );
		var set = $( this ).closest( '.wpec-gallery__set' ).get( 0 );
		Gallery.openLightbox( gallery, set, Gallery.slides( set ).get( Gallery.current( set ) ), this );
	} );

	$( document ).on( 'click', '.wpec-gallery__play', function() {
		var url = $( this ).attr( 'data-embed' );
		if ( ! url || ! /^https?:\/\//i.test( url ) ) {
			return;
		}
		var frame = $( '<iframe class="wpec-gallery__embed" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>' ).attr( 'src', url ).attr( 'title', $( this ).attr( 'aria-label' ) || '' );
		$( this ).replaceWith( frame );
	} );

	/* Zoom ( a mouse or pen only ): inside the image, or a lens that follows the pointer; on hover, or on click. The largest
	 * image the store has ( data-full ) is magnified by the widget's level. */
	var canHover = !! ( window.matchMedia && window.matchMedia( '(hover: hover) and (pointer: fine)' ).matches );
	var Zoom = {
		on: function( gallery ) {
			return canHover && '1' === galleryOption( gallery, 'zoom', '0' );
		},

		byClick: function( gallery ) {
			return Zoom.on( gallery ) && 'click' === galleryOption( gallery, 'zoom-trigger', 'hover' );
		},

		layer: function( slide, lens ) {
			var $stage = $( slide ).closest( '.wpec-gallery__stage' );
			var cls = lens ? 'wpec-gallery__lens' : 'wpec-gallery__zoom';
			var layer = $stage.children( '.' + cls );
			if ( ! layer.length ) {
				layer = $( '<div aria-hidden="true"></div>' ).addClass( cls ).appendTo( $stage );
			}
			return layer;
		},

		move: function( slide, e ) {
			var gallery = $( slide ).closest( '.wpec-gallery' ).get( 0 );
			var full = $( slide ).attr( 'data-full' );
			if ( ! full ) {
				return;
			}
			var lens = 'lens' === galleryOption( gallery, 'zoom-type', 'inner' );
			var level = parseFloat( galleryOption( gallery, 'zoom-level', '2' ) ) || 2;
			var layer = Zoom.layer( slide, lens );
			if ( layer.attr( 'data-src' ) !== full ) {
				layer.attr( 'data-src', full ).css( 'background-image', 'url("' + String( full ).replace( /["\\\n]/g, '' ) + '")' );
			}
			var rect = slide.getBoundingClientRect();
			var x = Math.max( 0, Math.min( 1, ( e.clientX - rect.left ) / ( rect.width || 1 ) ) );
			var y = Math.max( 0, Math.min( 1, ( e.clientY - rect.top ) / ( rect.height || 1 ) ) );
			if ( lens ) {
				var stage = $( slide ).closest( '.wpec-gallery__stage' ).get( 0 ).getBoundingClientRect();
				var size = layer.outerWidth() || 160;
				layer.css( {
					left: ( e.clientX - stage.left ) + 'px',
					top: ( e.clientY - stage.top ) + 'px',
					'background-size': ( rect.width * level ) + 'px ' + ( rect.height * level ) + 'px',
					'background-position': ( size / 2 - x * rect.width * level ) + 'px ' + ( size / 2 - y * rect.height * level ) + 'px'
				} );
			} else {
				layer.css( { 'background-size': ( level * 100 ) + '%', 'background-position': ( x * 100 ) + '% ' + ( y * 100 ) + '%' } );
			}
			layer.addClass( 'is-zooming' );
		},

		toggle: function( slide, e ) {
			var zoomed = ! $( slide ).hasClass( 'is-zoomed' );
			$( slide ).toggleClass( 'is-zoomed', zoomed );
			if ( zoomed ) {
				Zoom.move( slide, e );
			} else {
				Zoom.hide( $( slide ).closest( '.wpec-gallery__set' ).get( 0 ) );
			}
		},

		hide: function( set ) {
			$( set ).find( '.wpec-gallery__zoom, .wpec-gallery__lens' ).removeClass( 'is-zooming' );
			$( set ).find( '.wpec-gallery__slide.is-zoomed' ).removeClass( 'is-zoomed' );
		}
	};

	$( document ).on( 'mousemove', '.wpec-gallery[data-wpec-zoom="1"] .wpec-gallery__slide[data-type="image"]', function( e ) {
		var gallery = $( this ).closest( '.wpec-gallery' ).get( 0 );
		if ( ! Zoom.on( gallery ) || ( Zoom.byClick( gallery ) && ! $( this ).hasClass( 'is-zoomed' ) ) ) {
			return;
		}
		Zoom.move( this, e );
	} );

	/* Click to zoom without the larger image ( no button around the image ). */
	$( document ).on( 'click', '.wpec-gallery[data-wpec-zoom-trigger="click"] .wpec-gallery__slide[data-type="image"]', function( e ) {
		var gallery = $( this ).closest( '.wpec-gallery' ).get( 0 );
		if ( ! $( e.target ).closest( '.wpec-gallery__open' ).length && Zoom.byClick( gallery ) ) {
			Zoom.toggle( this, e );
		}
	} );

	$( document ).on( 'mouseleave', '.wpec-gallery__stage', function() {
		Zoom.hide( $( this ).closest( '.wpec-gallery__set' ).get( 0 ) );
	} );

	/* ------------------------------------------------------------ The foundation's product state ( when it exists ) */

	$( document ).on( 'wpeasycart_product_state', function( e, state ) {
		if ( ! state || ! state.product_id ) {
			return;
		}
		var selector = '[data-product-id="' + parseInt( state.product_id, 10 ) + '"]';
		var info = Stock.fromState( state.stock );
		if ( info ) {
			$( '.wpec-stock' + selector + ', .wpec-badges' + selector ).each( function() {
				Stock.render( this, info );
			} );
		}
		try {
			Offers.update( state );
		} catch ( err ) {}
		$( '.wpec-sku' + selector ).each( function() {
			var value = $( this ).find( '.wpec-sku__value' );
			var sku = ( 'string' === typeof state.sku && '' !== state.sku ) ? state.sku : String( value.attr( 'data-wpec-default-sku' ) || '' );
			if ( '' !== sku ) {
				value.text( sku );
			}
			/* A product without its own SKU shows the widget only while the chosen variant has one. */
			if ( this.hasAttribute( 'data-wpec-sku-variants' ) ) {
				$( this ).prop( 'hidden', '' === sku );
			}
		} );
		$( '.wpec-gallery' + selector ).each( function() {
			var gallery = this;
			/* state.options = [ { name, value } ] of the form's ec_option fields; the gallery names the one whose option item
			 * picks its image set ( data-wpec-image-field: ec_option1, or an advanced list, swatch or radio ). */
			var field = gallery.getAttribute( 'data-wpec-image-field' );
			if ( field && $.isArray( state.options ) ) {
				$.each( state.options, function( i, option ) {
					if ( option && field === option.name && parseInt( option.value, 10 ) > 0 ) {
						Gallery.showSet( gallery, parseInt( option.value, 10 ) );
						return false;
					}
				} );
			}
			if ( 'string' === typeof state.image && '' !== state.image ) {
				var set = $( gallery ).children( '.wpec-gallery__set' ).not( '.ec_inactive' ).get( 0 );
				Gallery.slides( set ).each( function( index ) {
					if ( $( this ).attr( 'data-full' ) === state.image || $( this ).find( 'img' ).attr( 'src' ) === state.image ) {
						Gallery.go( set, index );
						return false;
					}
				} );
			}
		} );
	} );

	/* ------------------------------------------------------------ Start */

	function initAll( scope ) {
		var root = scope ? ( scope.jquery ? scope.get( 0 ) : scope ) : document;
		if ( ! root ) {
			return;
		}
		Cart.swatches( root );
		/* Required-option and quantity messages are read out when ec-store.js shows them. */
		$( root ).find( '.wpec-atc .ec_details_option_row_error' ).attr( 'role', 'alert' );
		Stock.init( root );
		Offers.init( root );
		Gallery.init( root );
	}

	window.wpeasycart_product_buy_init = initAll;

	var hooked = false;
	function hookElementor() {
		if ( hooked || ! window.elementorFrontend || ! window.elementorFrontend.hooks ) {
			return;
		}
		hooked = true;
		$.each( WIDGETS, function( i, name ) {
			window.elementorFrontend.hooks.addAction( 'frontend/element_ready/' + name + '.default', function( $scope ) {
				initAll( $scope );
			} );
		} );
	}

	$( function() {
		initAll( document );
		hookElementor();
	} );
	$( window ).on( 'elementor/frontend/init', hookElementor );
	hookElementor();
}( window.jQuery, window, document ) );
