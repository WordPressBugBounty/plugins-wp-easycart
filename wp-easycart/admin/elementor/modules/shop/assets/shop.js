/**
 * WP EasyCart shop widgets for Elementor ( 6.0.2 ): add to cart from a product card, quick view, "Load more", carousels,
 * and the Shop widget's sorting and filters button.
 *
 * Everything is delegated from the document or started per widget ( DOM ready, and Elementor's element_ready for the
 * editor, popups and anything drawn later ), so widgets redrawn by the editor keep working. No jQuery needed; when jQuery
 * is on the page the store's shared events are triggered ( wpeasycart_item_added, wpeasycart_cart_changed ).
 */
( function () {
	'use strict';

	var data = window.wpecElShop || { ajaxUrl: '', cartUrl: '', viewCart: 1, i18n: {} };

	function t( key, fallback ) {
		return ( data.i18n && data.i18n[ key ] ) ? data.i18n[ key ] : ( fallback || '' );
	}

	function fill( text, pairs ) {
		Object.keys( pairs ).forEach( function ( key ) {
			text = String( text ).split( '[' + key + ']' ).join( pairs[ key ] );
		} );
		return text;
	}

	function each( list, callback ) {
		Array.prototype.forEach.call( list || [], callback );
	}

	function ajaxUrl( params ) {
		var query = Object.keys( params ).map( function ( key ) {
			return encodeURIComponent( key ) + '=' + encodeURIComponent( params[ key ] );
		} ).join( '&' );
		return data.ajaxUrl + ( data.ajaxUrl.indexOf( '?' ) === -1 ? '?' : '&' ) + query;
	}

	function parseJson( text ) {
		try {
			return JSON.parse( String( text ).replace( /^\uFEFF/, '' ).trim() );
		} catch ( e ) {
			return null;
		}
	}

	/* A fetch with a timeout. onController( controller ) lets the caller abort it too ( a quick view closed early ). */
	function request( url, options, timeout, onController ) {
		var controller = ( typeof window.AbortController === 'function' ) ? new window.AbortController() : null;
		var timer = null;
		options = options || {};
		options.credentials = 'same-origin';
		if ( controller ) {
			options.signal = controller.signal;
			timer = setTimeout( function () {
				controller.abort();
			}, timeout || 20000 );
			if ( typeof onController === 'function' ) {
				onController( controller );
			}
		}
		return fetch( url, options ).then( function ( response ) {
			if ( timer ) {
				clearTimeout( timer );
			}
			return response.text().then( function ( text ) {
				return { status: response.status, text: text };
			} );
		}, function ( error ) {
			if ( timer ) {
				clearTimeout( timer );
			}
			throw error;
		} );
	}

	/* A polite announcement in the widget's live region ( or one on the page ). */
	function announce( from, text ) {
		var holder = from && from.closest ? from.closest( '[data-wpec-live-scope], .wpec-products, .wpec-qv-dialog' ) : null;
		var region = holder ? holder.querySelector( '[data-wpec-live]' ) : null;
		if ( ! region ) {
			region = document.getElementById( 'wpec-shop-live' );
			if ( ! region ) {
				region = document.createElement( 'div' );
				region.id = 'wpec-shop-live';
				region.className = 'wpec-sr-only';
				region.setAttribute( 'aria-live', 'polite' );
				document.body.appendChild( region );
			}
		}
		region.textContent = '';
		setTimeout( function () {
			region.textContent = text;
		}, 50 );
	}

	/* ---------------------------------------------------------------- Add to cart */

	function track( element, quantity ) {
		var raw = element.getAttribute( 'data-wpec-track' );
		var info = raw ? parseJson( raw ) : null;
		var eventId = '';
		if ( ! info ) {
			return eventId;
		}
		if ( info.meta && typeof window.wpeasycart_meta_add === 'function' ) {
			try {
				window.wpeasycart_meta_add( element, info.meta, quantity );
				eventId = window.wpeasycart_meta_add_eid || '';
				window.wpeasycart_meta_add_eid = '';
			} catch ( e ) {}
		}
		if ( info.ga4 && typeof window.ec_ga4_add_to_cart === 'function' ) {
			try {
				window.ec_ga4_add_to_cart( info.ga4.model, info.ga4.title, quantity, info.ga4.price, info.ga4.currency, info.ga4.brand, info.ga4.gtm );
			} catch ( e ) {}
		}
		return eventId;
	}

	function setBusy( button, busy ) {
		button.setAttribute( 'aria-busy', busy ? 'true' : 'false' );
		button.classList.toggle( 'is-loading', !! busy );
	}

	function quantityFor( button ) {
		var id = button.getAttribute( 'data-wpec-qty-input' );
		var input = id ? document.getElementById( id ) : null;
		var quantity = input ? parseInt( input.value, 10 ) : 1;
		return ( quantity > 0 ) ? quantity : 1;
	}

	/* The public add-to-cart link: adds without a nonce and opens the cart ( a nonce refused twice ). */
	function addByLink( button ) {
		var href = button.getAttribute( 'data-wpec-href' );
		if ( href ) {
			window.location.href = href;
		} else {
			setBusy( button, false );
		}
	}

	/* Something went wrong after the add may have happened: never add again, open the product page instead. */
	function addFailed( button ) {
		setBusy( button, false );
		announce( button, t( 'addFailed', 'This product could not be added.' ) );
		var link = button.getAttribute( 'data-wpec-link' );
		if ( link ) {
			setTimeout( function () {
				window.location.href = link;
			}, 900 );
		}
	}

	function refreshNonce( button ) {
		return request( ajaxUrl( { action: 'wp_easycart_el_cart_nonce', product_id: button.getAttribute( 'data-wpec-add' ) } ), { method: 'GET' }, 10000 ).then( function ( result ) {
			var json = parseJson( result.text );
			if ( json && json.success && json.data && json.data.nonce ) {
				button.setAttribute( 'data-wpec-nonce', json.data.nonce );
				return true;
			}
			return false;
		}, function () {
			return false;
		} );
	}

	function added( button, json ) {
		var name = button.getAttribute( 'data-wpec-name' ) || '';
		/* Only the visible label changes; the product name read to screen readers stays. */
		var labelEl = button.querySelector( '.wpec-button__label' ) || button;
		var label = button.getAttribute( 'data-wpec-label' ) || labelEl.textContent;
		setBusy( button, false );
		button.setAttribute( 'data-wpec-label', label );
		button.classList.add( 'is-added' );
		labelEl.textContent = t( 'added', 'Added' );
		setTimeout( function () {
			button.classList.remove( 'is-added' );
			labelEl.textContent = button.getAttribute( 'data-wpec-label' ) || label;
		}, 2500 );
		announce( button, fill( t( 'addedNote', '[product] was added to your cart.' ), { product: name } ) );

		if ( data.viewCart && data.cartUrl && button.parentNode && ! button.parentNode.querySelector( '.wpec-view-cart' ) ) {
			var link = document.createElement( 'a' );
			link.className = 'wpec-view-cart';
			link.href = data.cartUrl;
			link.textContent = t( 'viewCart', 'View Cart' );
			button.parentNode.appendChild( link );
		}

		try {
			if ( typeof window.wpeasycart_update_cart_widgets === 'function' ) {
				window.wpeasycart_update_cart_widgets( json );
			}
		} catch ( e ) {}
		try {
			if ( window.jQuery && json[ 0 ] && json[ 0 ].item_added ) {
				window.jQuery( document ).trigger( 'wpeasycart_item_added', [ json[ 0 ].item_added ] );
			}
		} catch ( e ) {}
		try {
			if ( typeof window.wpeasycart_refresh_cart_widgets === 'function' ) {
				window.wpeasycart_refresh_cart_widgets();
			} else if ( window.jQuery && json[ 0 ] ) {
				/* Without the store script: the add's answer in the event's own shape ( plain text values ). */
				window.jQuery( document ).trigger( 'wpeasycart_cart_changed', [ cartFromAdd( json ) ] );
			}
		} catch ( e ) {}
	}

	/* Markup or entities as plain text, parsed where nothing runs or loads ( DOMParser ). */
	function plainText( value ) {
		var text = ( null === value || undefined === value ) ? '' : String( value );
		if ( text.indexOf( '<' ) === -1 && text.indexOf( '&' ) === -1 ) {
			return text;
		}
		try {
			return ( new window.DOMParser() ).parseFromString( text, 'text/html' ).body.textContent || '';
		} catch ( e ) {
			return text.replace( /<[^>]*>/g, '' );
		}
	}

	/*
	 * The cart state wpeasycart_cart_changed carries ( ec-store.js wpeasycart_refresh_cart_widgets() ):
	 * { count, subtotal_display, items: [ { title, quantity, price_display, link, image, cartitem_id, product_id } ] },
	 * built from ec_ajax_add_to_cart's rows ( title, price, quantity, link, cartitem_id; no image or product id ).
	 */
	function cartFromAdd( rows ) {
		var items = [];
		each( rows, function ( row ) {
			if ( ! row || 'object' !== typeof row ) {
				return;
			}
			items.push( {
				title: plainText( row.title ),
				quantity: Number( row.quantity ) || 0,
				price_display: plainText( row.price ),
				link: String( row.link || '' ),
				image: '',
				cartitem_id: Number( row.cartitem_id ) || 0,
				product_id: 0
			} );
		} );
		return {
			count: Number( rows[ 0 ].total_items ) || 0,
			subtotal_display: plainText( rows[ 0 ].total_price ),
			items: items
		};
	}

	function sendAdd( button, quantity, eventId, retried ) {
		var body = new URLSearchParams();
		body.append( 'action', 'ec_ajax_add_to_cart' );
		body.append( 'product_id', button.getAttribute( 'data-wpec-add' ) );
		body.append( 'model_number', button.getAttribute( 'data-wpec-model' ) || '' );
		body.append( 'quantity', String( quantity ) );
		body.append( 'nonce', button.getAttribute( 'data-wpec-nonce' ) || '' );
		if ( eventId ) {
			body.append( 'ec_meta_eid', eventId );
		}
		request( data.ajaxUrl, { method: 'POST', body: body }, 20000 ).then( function ( result ) {
			var text = String( result.text || '' ).replace( /^\uFEFF/, '' ).trim();
			/* Refused before anything was added ( an expired nonce on a cached page, no handler ): safe to try again. */
			if ( ( result.status >= 400 && result.status < 500 ) || '' === text || '0' === text || '-1' === text ) {
				if ( retried ) {
					addByLink( button );
					return;
				}
				refreshNonce( button ).then( function ( ok ) {
					if ( ok ) {
						sendAdd( button, quantity, eventId, true );
					} else {
						addByLink( button );
					}
				} );
				return;
			}
			var json = parseJson( text );
			/* 6.0.2: false === added: the store refused the add ( catalog or inquiry mode, login for pricing, customer role, a
			 * donation ); the product page says why. */
			if ( ! json || ! json[ 0 ] || false === json[ 0 ].added ) {
				addFailed( button );
				return;
			}
			added( button, json );
		}, function () {
			addFailed( button );
		} );
	}

	function addToCart( button ) {
		if ( 'true' === button.getAttribute( 'aria-busy' ) || ! data.ajaxUrl || typeof window.fetch !== 'function' || typeof window.URLSearchParams !== 'function' ) {
			if ( ! data.ajaxUrl || typeof window.fetch !== 'function' ) {
				addByLink( button );
			}
			return;
		}
		var quantity = quantityFor( button );
		var eventId = track( button, quantity );
		setBusy( button, true );
		sendAdd( button, quantity, eventId, false );
	}

	/* ---------------------------------------------------------------- Quick view */

	var dialog = null;
	var opener = null;
	/* Each opening gets a number: an answer for an older one ( closed, or another product opened since ) is dropped. */
	var quickViewSeq = 0;
	var quickViewController = null;

	function stopQuickViewRequest() {
		quickViewSeq++;
		if ( quickViewController ) {
			try {
				quickViewController.abort();
			} catch ( e ) {}
			quickViewController = null;
		}
	}

	function closeDialog() {
		if ( ! dialog ) {
			return;
		}
		if ( typeof dialog.close === 'function' && dialog.open ) {
			dialog.close();
		} else {
			dialog.removeAttribute( 'open' );
			dialog.dispatchEvent( new Event( 'close' ) );
		}
	}

	function getDialog() {
		if ( dialog ) {
			return dialog;
		}
		dialog = document.createElement( 'dialog' );
		dialog.className = 'wpec-qv-dialog';
		dialog.setAttribute( 'aria-labelledby', 'wpec-qv-title' );
		dialog.innerHTML = '<div class="wpec-qv-dialog__panel"><button type="button" class="wpec-qv-dialog__close" data-wpec-qv-close><span aria-hidden="true">&times;</span></button><div class="wpec-qv-dialog__content"></div><div class="wpec-sr-only" aria-live="polite" data-wpec-live></div></div>';
		dialog.querySelector( '[data-wpec-qv-close]' ).setAttribute( 'aria-label', t( 'close', 'Close' ) );
		dialog.addEventListener( 'click', function ( event ) {
			if ( event.target === dialog || ( event.target.closest && event.target.closest( '[data-wpec-qv-close]' ) ) ) {
				closeDialog();
			}
		} );
		dialog.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key && ! ( typeof dialog.showModal === 'function' ) ) {
				closeDialog();
			}
		} );
		dialog.addEventListener( 'close', function () {
			stopQuickViewRequest();
			document.documentElement.classList.remove( 'wpec-qv-open' );
			dialog.querySelector( '.wpec-qv-dialog__content' ).innerHTML = '';
			if ( opener && document.body.contains( opener ) ) {
				opener.focus();
			}
		} );
		document.body.appendChild( dialog );
		return dialog;
	}

	function openQuickView( button ) {
		var box = getDialog();
		var content = box.querySelector( '.wpec-qv-dialog__content' );
		/* Inside the widget that opened it, so that widget's Quick view style settings reach it ( a modal dialog still shows above the page ). */
		var host = ( button.closest && button.closest( '.elementor-widget' ) ) || document.body;
		if ( box.parentNode !== host && ! box.open ) {
			host.appendChild( box );
		}
		stopQuickViewRequest();
		var seq = quickViewSeq;
		opener = button;
		content.innerHTML = '';
		var loading = document.createElement( 'p' );
		loading.className = 'wpec-qv-dialog__loading';
		loading.textContent = t( 'loading', 'Loading…' );
		content.appendChild( loading );
		if ( typeof box.showModal === 'function' ) {
			box.showModal();
		} else {
			box.setAttribute( 'open', '' );
			box.classList.add( 'is-fallback' );
			box.querySelector( '[data-wpec-qv-close]' ).focus();
		}
		document.documentElement.classList.add( 'wpec-qv-open' );
		request( ajaxUrl( { action: 'wp_easycart_el_quick_view', product_id: button.getAttribute( 'data-wpec-quick-view' ) } ), { method: 'GET' }, 20000, function ( controller ) {
			quickViewController = controller;
		} ).then( function ( result ) {
			if ( seq !== quickViewSeq ) {
				return; /* Closed, or another product opened, while this one loaded. */
			}
			quickViewController = null;
			var json = parseJson( result.text );
			if ( ! json || ! json.success || ! json.data ) {
				closeDialog();
				return;
			}
			content.innerHTML = json.data.html;
			if ( typeof window.wpeasycart_init === 'function' ) {
				try {
					window.wpeasycart_init( content );
				} catch ( e ) {}
			}
		}, function () {
			if ( seq === quickViewSeq ) {
				quickViewController = null;
				closeDialog();
			}
		} );
	}

	/* ---------------------------------------------------------------- Load more */

	function loadMore( link, event ) {
		var root = link.closest( '.wpec-products' );
		if ( ! root || ! root.getAttribute( 'data-wpec-query' ) || ! data.ajaxUrl || typeof window.fetch !== 'function' ) {
			return; /* The link opens the next page. */
		}
		event.preventDefault();
		if ( 'true' === link.getAttribute( 'aria-busy' ) ) {
			return;
		}
		var page = parseInt( link.getAttribute( 'data-wpec-page' ), 10 ) || 2;
		var grid = root.querySelector( '.wpec-products__grid' );
		link.setAttribute( 'aria-busy', 'true' );
		link.classList.add( 'is-loading' );
		request( ajaxUrl( {
			action: 'wp_easycart_el_products',
			page: page,
			query: root.getAttribute( 'data-wpec-query' ),
			card: root.getAttribute( 'data-wpec-card' ) || '{}'
		} ), { method: 'GET' }, 30000 ).then( function ( result ) {
			var json = parseJson( result.text );
			link.setAttribute( 'aria-busy', 'false' );
			link.classList.remove( 'is-loading' );
			if ( ! json || ! json.success || ! json.data || ! grid ) {
				window.location.href = link.href;
				return;
			}
			var before = grid.children.length;
			grid.insertAdjacentHTML( 'beforeend', json.data.html );
			var first = grid.children[ before ];
			if ( first ) {
				var target = first.querySelector( '.wpec-card__title a, a[href]' );
				if ( target ) {
					target.focus( { preventScroll: false } );
				}
			}
			announce( root, fill( t( 'loaded', '[count] more products loaded' ), { count: json.data.count } ) );
			if ( typeof window.wpeasycart_init === 'function' ) {
				try {
					window.wpeasycart_init( grid );
				} catch ( e ) {}
			}
			if ( json.data.more ) {
				link.setAttribute( 'data-wpec-page', String( page + 1 ) );
				link.href = link.href.replace( /(wpec-page-[^=&]+=)\d+/, '$1' + ( page + 1 ) );
			} else {
				var holder = link.closest( '.wpec-products__more' );
				( holder || link ).parentNode.removeChild( holder || link );
			}
		}, function () {
			window.location.href = link.href;
		} );
	}

	/* ---------------------------------------------------------------- Carousel */

	function devices() {
		var active = window.elementorFrontend && window.elementorFrontend.config && window.elementorFrontend.config.responsive ? window.elementorFrontend.config.responsive.activeBreakpoints : null;
		var max = [];
		var wide = null;
		if ( active ) {
			Object.keys( active ).forEach( function ( name ) {
				var point = active[ name ];
				if ( ! point || ! point.value ) {
					return;
				}
				if ( 'min' === point.direction ) {
					wide = { name: name, value: parseInt( point.value, 10 ) };
				} else {
					max.push( { name: name, value: parseInt( point.value, 10 ) } );
				}
			} );
		}
		if ( ! max.length ) {
			max = [ { name: 'mobile', value: 767 }, { name: 'tablet', value: 1024 } ];
		}
		max.sort( function ( a, b ) {
			return a.value - b.value;
		} );
		return { max: max, wide: wide };
	}

	/* Each device's value, a device without one taking the next larger device's ( Elementor's inheritance ). */
	function perDevice( raw, fallback, list ) {
		var number = function ( value, otherwise ) {
			var parsed = parseFloat( value );
			return isNaN( parsed ) ? otherwise : parsed;
		};
		var out = { desktop: number( raw && raw.desktop, fallback ) };
		var previous = out.desktop;
		for ( var i = list.max.length - 1; i >= 0; i-- ) {
			var name = list.max[ i ].name;
			out[ name ] = number( raw && raw[ name ], previous );
			previous = out[ name ];
		}
		if ( list.wide ) {
			out[ list.wide.name ] = number( raw && raw[ list.wide.name ], out.desktop );
		}
		return out;
	}

	function initCarousel( root ) {
		if ( root.wpecCarousel ) {
			return;
		}
		root.wpecCarousel = true;
		var config = parseJson( root.getAttribute( 'data-wpec-carousel' ) ) || {};
		var viewport = root.querySelector( '.wpec-carousel__viewport' );
		var track = root.querySelector( '.wpec-carousel__track' );
		var prev = root.querySelector( '.wpec-carousel__arrow--prev' );
		var next = root.querySelector( '.wpec-carousel__arrow--next' );
		var dots = root.querySelector( '.wpec-carousel__dots' );
		var pauseButton = root.querySelector( '.wpec-carousel__pause' );
		var reduced = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		var list = devices();
		var views = perDevice( config.perView, 4, list );
		var gaps = perDevice( config.gap, 24, list );
		var slides = track ? track.children.length : 0;
		var autoplay = !! config.autoplay && ! reduced;
		var paused = false;
		var hovered = false;
		if ( ! viewport || ! track || ! slides ) {
			return;
		}
		if ( pauseButton && ! autoplay ) {
			pauseButton.hidden = true;
		}
		var most = 1;
		Object.keys( views ).forEach( function ( name ) {
			most = Math.max( most, views[ name ] );
		} );

		function setPauseLabel() {
			if ( pauseButton ) {
				pauseButton.setAttribute( 'aria-pressed', paused ? 'true' : 'false' );
				pauseButton.textContent = paused ? t( 'play', 'Play' ) : t( 'pause', 'Pause' );
			}
		}

		function scrollMode() {
			root.classList.add( 'is-scroll' );
			var timer = null;
			var rtl = 'rtl' === getComputedStyle( track ).direction;
			function step( direction ) {
				var end = track.scrollWidth - track.clientWidth - 2;
				var at = Math.abs( track.scrollLeft );
				var behavior = reduced ? 'auto' : 'smooth';
				if ( direction > 0 && at >= end ) {
					track.scrollTo( { left: 0, behavior: behavior } );
					return;
				}
				track.scrollBy( { left: direction * track.clientWidth * ( rtl ? -1 : 1 ), behavior: behavior } );
			}
			if ( prev ) {
				prev.addEventListener( 'click', function () {
					step( -1 );
				} );
			}
			if ( next ) {
				next.addEventListener( 'click', function () {
					step( 1 );
				} );
			}
			if ( autoplay ) {
				timer = setInterval( function () {
					if ( ! paused && ! hovered && document.visibilityState !== 'hidden' ) {
						step( 1 );
					}
				}, config.delay || 5000 );
			}
			return {
				stop: function () {},
				start: function () {},
				timer: timer
			};
		}

		function swiperMode( SwiperClass ) {
			var base = list.max.length ? list.max[ 0 ].name : 'desktop';
			var breakpoints = {};
			list.max.forEach( function ( point, index ) {
				var name = ( index + 1 < list.max.length ) ? list.max[ index + 1 ].name : 'desktop';
				breakpoints[ point.value + 1 ] = { slidesPerView: views[ name ], spaceBetween: gaps[ name ] };
			} );
			if ( list.wide ) {
				breakpoints[ list.wide.value ] = { slidesPerView: views[ list.wide.name ], spaceBetween: gaps[ list.wide.name ] };
			}
			var options = {
				wrapperClass: 'wpec-carousel__track',
				slideClass: 'wpec-carousel__slide',
				slidesPerView: views[ base ],
				spaceBetween: gaps[ base ],
				breakpoints: breakpoints,
				speed: reduced ? 0 : 450,
				watchOverflow: true,
				loop: !! config.loop && slides > most,
				navigation: ( prev && next ) ? { nextEl: next, prevEl: prev, disabledClass: 'is-disabled' } : false,
				pagination: dots ? { el: dots, clickable: true } : false,
				a11y: {
					enabled: true,
					prevSlideMessage: t( 'prev', 'Previous products' ),
					nextSlideMessage: t( 'next', 'Next products' ),
					paginationBulletMessage: t( 'goTo', 'Go to slide [index]' ).split( '[index]' ).join( '{{index}}' )
				},
				autoplay: autoplay ? { delay: config.delay || 5000, disableOnInteraction: false } : false
			};
			var swiper;
			root.classList.add( 'is-swiper' );
			try {
				swiper = new SwiperClass( viewport, options );
			} catch ( e ) {
				root.classList.remove( 'is-swiper' );
				return scrollMode();
			}
			return {
				stop: function () {
					if ( swiper && swiper.autoplay && swiper.autoplay.running ) {
						swiper.autoplay.stop();
					}
				},
				start: function () {
					if ( swiper && swiper.autoplay && ! swiper.autoplay.running ) {
						swiper.autoplay.start();
					}
				}
			};
		}

		function begin( controller ) {
			if ( ! autoplay ) {
				return;
			}
			var sync = function () {
				if ( paused || hovered ) {
					controller.stop();
				} else {
					controller.start();
				}
			};
			root.addEventListener( 'mouseenter', function () {
				hovered = true;
				sync();
			} );
			root.addEventListener( 'mouseleave', function () {
				hovered = false;
				sync();
			} );
			root.addEventListener( 'focusin', function () {
				hovered = true;
				sync();
			} );
			root.addEventListener( 'focusout', function ( event ) {
				if ( ! event.relatedTarget || ! root.contains( event.relatedTarget ) ) {
					hovered = false;
					sync();
				}
			} );
			if ( pauseButton ) {
				pauseButton.addEventListener( 'click', function () {
					paused = ! paused;
					setPauseLabel();
					sync();
				} );
			}
			setPauseLabel();
		}

		var SwiperClass = window.Swiper;
		if ( typeof SwiperClass === 'function' ) {
			begin( swiperMode( SwiperClass ) );
		} else if ( window.elementorFrontend && window.elementorFrontend.utils && window.elementorFrontend.utils.assetsLoader && typeof window.elementorFrontend.utils.assetsLoader.load === 'function' ) {
			try {
				window.elementorFrontend.utils.assetsLoader.load( 'script', 'swiper' ).then( function () {
					begin( typeof window.Swiper === 'function' ? swiperMode( window.Swiper ) : scrollMode() );
				}, function () {
					begin( scrollMode() );
				} );
			} catch ( e ) {
				begin( scrollMode() );
			}
		} else {
			begin( scrollMode() );
		}
	}

	/* ---------------------------------------------------------------- Shop */

	/*
	 * The sort menu keeps its Sort button. A choice made with the mouse or a finger sorts at once; from the keyboard every
	 * arrow key changes a closed menu, so it waits for the button ( or Enter ) instead of leaving the page on the first key.
	 */
	var sortByPointer = null;

	function sortPointer( event ) {
		var target = event.target;
		sortByPointer = ( target && target.closest ) ? target.closest( 'select[data-wpec-sort]' ) : null;
	}

	document.addEventListener( 'pointerdown', sortPointer, true );
	document.addEventListener( 'mousedown', sortPointer, true );
	document.addEventListener( 'keydown', function ( event ) {
		if ( event.target && event.target.matches && event.target.matches( 'select[data-wpec-sort]' ) ) {
			sortByPointer = null;
		}
	}, true );

	function toggleFilters( button ) {
		var root = button.closest( '.wpec-shop' );
		if ( ! root ) {
			return;
		}
		var open = ! root.classList.contains( 'is-filters-open' );
		root.classList.toggle( 'is-filters-open', open );
		button.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		if ( open ) {
			var panel = document.getElementById( button.getAttribute( 'aria-controls' ) );
			var first = panel ? panel.querySelector( 'input, a[href], button' ) : null;
			if ( first ) {
				first.focus();
			}
		}
	}

	/* ---------------------------------------------------------------- Start */

	document.addEventListener( 'click', function ( event ) {
		var target = event.target;
		if ( ! target || ! target.closest ) {
			return;
		}
		var add = target.closest( 'button[data-wpec-add]' );
		if ( add ) {
			event.preventDefault();
			addToCart( add );
			return;
		}
		var quick = target.closest( '[data-wpec-quick-view]' );
		if ( quick ) {
			event.preventDefault();
			openQuickView( quick );
			return;
		}
		var more = target.closest( '.wpec-load-more' );
		if ( more ) {
			loadMore( more, event );
			return;
		}
		var toggle = target.closest( '.wpec-shop__filters-toggle' );
		if ( toggle ) {
			toggleFilters( toggle );
			return;
		}
		var link = target.closest( 'a.wpec-card__button[data-wpec-track]' );
		if ( link ) {
			track( link, 1 ); /* The store sends shoppers to the cart: the link adds, this only reports it. */
		}
	} );

	document.addEventListener( 'change', function ( event ) {
		var select = event.target;
		if ( select && select.matches && select.matches( 'select[data-wpec-sort]' ) && select.form && sortByPointer === select ) {
			sortByPointer = null;
			select.form.submit();
		}
	} );

	function init( scope ) {
		var root = scope || document;
		each( root.querySelectorAll( '[data-wpec-carousel]' ), initCarousel );
	}

	window.wpeasycart_shop_widgets_init = init;

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			init( document );
		} );
	} else {
		init( document );
	}

	function hookElementor() {
		if ( ! window.elementorFrontend || ! window.elementorFrontend.hooks ) {
			return;
		}
		[ 'wp_easycart_products', 'wp_easycart_product_carousel', 'wp_easycart_shop' ].forEach( function ( name ) {
			window.elementorFrontend.hooks.addAction( 'frontend/element_ready/' + name + '.default', function ( $scope ) {
				init( $scope && $scope[ 0 ] ? $scope[ 0 ] : document );
			} );
		} );
	}

	if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
		hookElementor();
	} else if ( window.jQuery ) {
		window.jQuery( window ).on( 'elementor/frontend/init', hookElementor );
	}
}() );
