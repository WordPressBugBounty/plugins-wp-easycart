/**
 * WP EasyCart for Elementor: Menu Cart and the mini cart ( 6.0.2 ).
 *
 * The cart comes from the uncached cart state ( ec_ajax_cart_state ): ec-store.js asks for it once the page is ready when a
 * [data-wpec-cart-widget] is on the page, and after every add or cart page change, and announces it with
 * jQuery( document ).trigger( 'wpeasycart_cart_changed', [ cart ] ). WP EasyCart adds each line's total, options, limits and the
 * nonces its own update and remove requests check ( filter wp_easycart_cart_state ). An older ec-store.js without that
 * refresh: this script asks itself.
 *
 * window.wpeasycartMiniCart ( also used by WP EasyCart PRO's Side Cart ):
 *   state()                 the last cart state, or null
 *   refresh()               asks for the cart again ( a promise )
 *   update( item, qty )     EasyCart's own quantity update ( ec_ajax_cartitem_update )
 *   remove( item )          EasyCart's own remove ( ec_ajax_cartitem_delete )
 *   row( item, root )       a line's markup ( jQuery ); with a Menu Cart root: its remove icon and quantity buttons ( round 11 )
 *   open( root, opener, options ) / close( root )   a mini cart panel: focus moves in ( options.focus false keeps it where it
 *                           is, for a dropdown ), Esc and the overlay close it, a modal panel keeps focus; options.instant
 *                           opens it at once, without the slide or moving focus ( the editor putting a redrawn panel back )
 *   place( root )           lines a dropdown up with its icon ( round 11: it followed the widget's width )
 *   text( key, values )     wording from the store's language file
 *   announce( root, text )  a polite screen reader message
 *
 * In the Elementor editor ( 6.0.2 ): the panels start closed and open from their icon, as on the page; one left open is opened
 * again when a settings change redraws its widget ( so it stays open while it is styled ); the page never scrolls-locks; and
 * the widgets keep the sample lines they were drawn with ( the cart state of whoever is editing never replaces them ).
 */
( function( $, window, document ) {
	'use strict';

	if ( ! $ ) {
		return;
	}

	var config = window.wpeasycart_elementor_mini_cart || {};
	var ajaxUrl = config.ajax_url || ( window.wpeasycart_ajax_object ? window.wpeasycart_ajax_object.ajax_url : '' );
	var texts = config.text || {};
	var api = window.wpeasycartMiniCart = window.wpeasycartMiniCart || {};
	var current = null;
	var ownRequest = null;
	var FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

	function language() {
		return ( window.wpeasycart_ajax_object && window.wpeasycart_ajax_object.current_language ) ? window.wpeasycart_ajax_object.current_language : '';
	}

	function safeLink( value ) {
		var link = String( value || '' );
		return ( /^(https?:)?\/\//i.test( link ) || '/' === link.charAt( 0 ) ) ? link : '#';
	}

	api.text = function( key, values ) {
		var out = String( texts[ key ] || '' );
		$.each( values || {}, function( name, value ) {
			out = out.split( '[' + name + ']' ).join( String( value ) );
		} );
		return out;
	};

	api.state = function() {
		return current;
	};

	/* A cart state from ec-store.js or from our own request: remember it and draw every menu cart. */
	api.set = function( cart ) {
		if ( ! cart || 'object' !== typeof cart || 'undefined' === typeof cart.count ) {
			return;
		}
		current = cart;
		if ( inEditor() ) {
			return; /* the editor keeps the widgets' sample lines, so there is always something to style */
		}
		$( '.wpec-menu-cart' ).each( function() {
			draw( this, cart );
		} );
		$( document ).trigger( 'wpeasycart_mini_cart_state', [ cart ] );
	};

	/* The richer lines ( nonces for update and remove ) come from WP EasyCart's filter on the cart state. */
	function complete( cart ) {
		if ( ! cart || ! Array.isArray( cart.items ) ) {
			return false;
		}
		for ( var i = 0; i < cart.items.length; i++ ) {
			if ( ! cart.items[ i ] || 'undefined' === typeof cart.items[ i ].delete_nonce ) {
				return false;
			}
		}
		return true;
	}

	api.refresh = function() {
		if ( 'function' === typeof window.wpeasycart_refresh_cart_widgets ) {
			var request = window.wpeasycart_refresh_cart_widgets();
			if ( request && 'function' === typeof request.then ) {
				return request;
			}
		}
		if ( ownRequest ) {
			return ownRequest;
		}
		if ( ! ajaxUrl ) {
			return $.Deferred().reject().promise();
		}
		ownRequest = $.ajax( {
			url: ajaxUrl,
			type: 'post',
			dataType: 'json',
			cache: false,
			data: { action: 'ec_ajax_cart_state', language: language() }
		} ).done( function( cart ) {
			api.set( cart );
		} ).always( function() {
			ownRequest = null;
		} );
		return ownRequest;
	};

	/* After the cart changed here: the cart page on screen follows ( EasyCart's own ec_update_cart() ), then every cart widget. */
	function afterChange( answer, item, removed ) {
		var data = null;
		try {
			data = ( 'string' === typeof answer ) ? JSON.parse( answer ) : answer;
		} catch ( e ) {
			data = null;
		}
		if ( removed && item ) {
			var id = String( item.cartitem_id );
			$( document.getElementById( 'ec_cartitem_row_' + id ) ).remove();
			$( '.ec_cartitem_' + id ).remove();
			$( document.getElementById( 'ec_cartitem_min_error_' + id ) ).remove();
			$( document.getElementById( 'ec_cartitem_max_error_' + id ) ).remove();
			$( document.getElementById( 'ec_cart_widget_row_' + id ) ).remove();
		}
		var pageShowsCart = document.getElementById( 'ec_cart_total' ) || document.getElementById( 'ec_cart_subtotal' ) || document.querySelector( '.ec_cart_table_subtotal_amount' );
		if ( data && data.cart && pageShowsCart && 'function' === typeof window.ec_update_cart ) {
			try {
				window.ec_update_cart( data ); /* it asks for the cart state itself afterwards */
				return;
			} catch ( e ) {}
		}
		api.refresh();
	}

	api.update = function( item, quantity ) {
		if ( ! item || ! item.update_nonce || ! ajaxUrl ) {
			return $.Deferred().reject().promise();
		}
		return $.ajax( {
			url: ajaxUrl,
			type: 'post',
			cache: false,
			data: { action: 'ec_ajax_cartitem_update', ec_v3_24: 'true', cartitem_id: item.cartitem_id, quantity: quantity, nonce: item.update_nonce }
		} ).done( function( answer ) {
			if ( ! answer || '' === String( answer ).trim() ) {
				api.refresh(); /* an expired page: nothing changed, show the cart as it is */
				return;
			}
			afterChange( answer, item, false );
		} ).fail( function() {
			api.refresh();
		} );
	};

	api.remove = function( item ) {
		if ( ! item || ! item.delete_nonce || ! ajaxUrl ) {
			return $.Deferred().reject().promise();
		}
		return $.ajax( {
			url: ajaxUrl,
			type: 'post',
			cache: false,
			data: { action: 'ec_ajax_cartitem_delete', ec_v3_24: 'true', cartitem_id: item.cartitem_id, nonce: item.delete_nonce }
		} ).done( function( answer ) {
			if ( ! answer || '' === String( answer ).trim() ) {
				api.refresh();
				return;
			}
			afterChange( answer, item, true );
		} ).fail( function() {
			api.refresh();
		} );
	};

	/* - / + and the quantity box of a Menu Cart line ( round 11 ). */
	function stepper( item ) {
		var title = String( item.title || '' );
		var quantity = Number( item.quantity ) || 1;
		var min = Math.max( 1, Number( item.min_quantity ) || 1 );
		var max = Number( item.max_quantity ) || 0;
		var $box = $( '<div class="wpec-mini-cart__qty"></div>' );
		var $minus = $( '<button type="button" data-wpec-mc-step="-1">−</button>' ).attr( 'aria-label', api.text( 'decrease', { title: title } ) || '-' );
		var $input = $( '<input type="number" inputmode="numeric" step="1" />' ).attr( { value: quantity, min: min, 'aria-label': api.text( 'quantity', { title: title } ) || title } );
		var $plus = $( '<button type="button" data-wpec-mc-step="1">+</button>' ).attr( 'aria-label', api.text( 'increase', { title: title } ) || '+' );
		if ( max > 0 ) {
			$input.attr( 'max', max );
			$plus.prop( 'disabled', quantity >= max );
		}
		$minus.prop( 'disabled', quantity <= min );
		return $box.append( $minus, $input, $plus );
	}

	/* One cart line, built as elements and text ( a title or price is never read as markup ). */
	api.row = function( item, root ) {
		var link = safeLink( item.link );
		var title = String( item.title || '' );
		var $root = root ? $( root ) : $();
		var $row = $( '<li class="wpec-mini-cart__item"></li>' ).attr( 'data-cartitem', String( item.cartitem_id || 0 ) );
		var $image = $( '<a class="wpec-mini-cart__image" tabindex="-1" aria-hidden="true"></a>' ).attr( 'href', link );
		if ( item.image ) {
			$image.append( $( '<img alt="" loading="lazy" />' ).attr( 'src', String( item.image ) ) );
		}
		var $details = $( '<div class="wpec-mini-cart__details"></div>' );
		$details.append( $( '<a class="wpec-mini-cart__name"></a>' ).attr( 'href', link ).text( title ) );
		if ( item.options ) {
			$details.append( $( '<span class="wpec-mini-cart__options"></span>' ).text( String( item.options ) ) );
		}
		var $meta = $( '<span class="wpec-mini-cart__meta"></span>' );
		var steps = '1' === String( $root.attr( 'data-wpec-stepper' ) || '' ) && ! item.locked && item.update_nonce;
		if ( ! steps ) {
			$meta.append( $( '<span class="wpec-mini-cart__quantity"></span>' ).text( String( item.quantity ) + ' ×' ) );
			$meta.append( document.createTextNode( ' ' ) );
		}
		$meta.append( $( '<span class="wpec-mini-cart__price"></span>' ).text( String( item.price_display || '' ) ) );
		$details.append( $meta );
		if ( steps ) {
			$details.append( stepper( item ) );
		}
		$row.append( $image, $details, $( '<span class="wpec-mini-cart__total"></span>' ).text( String( item.total_display || '' ) ) );
		if ( false !== item.removable ) {
			var $remove = $( '<button type="button" class="wpec-mini-cart__remove" data-wpec-remove><span aria-hidden="true">×</span></button>' );
			var icon = $root.find( 'template.wpec-mini-cart__remove-icon' ).get( 0 );
			if ( icon && icon.content ) {
				$remove.children( 'span' ).empty().append( document.importNode( icon.content, true ) );
			}
			$remove.attr( 'aria-label', api.text( 'remove', { title: title } ) || title );
			$row.append( $remove );
		}
		$row.data( 'wpecItem', item );
		return $row;
	};

	api.announce = function( root, message ) {
		var $status = $( root ).find( '[role="status"]' ).first();
		if ( ! $status.length || ! message ) {
			return;
		}
		$status.text( '' );
		window.setTimeout( function() {
			$status.text( message );
		}, 60 );
	};

	/* A Menu Cart: count, label, subtotal and lines. */
	function draw( root, cart ) {
		var $root = $( root );
		var count = Number( cart.count ) || 0;
		var items = Array.isArray( cart.items ) ? cart.items : [];
		$root.toggleClass( 'wpec-menu-cart--empty', count <= 0 );
		$root.find( '[data-wpec-count]' ).text( String( count ) );
		$root.find( '[data-wpec-subtotal]' ).text( String( null == cart.subtotal_display ? '' : cart.subtotal_display ) );
		var label = String( $root.attr( 'data-wpec-label' ) || texts.count || '' ).split( '[count]' ).join( String( count ) );
		if ( label ) {
			$root.find( '.wpec-menu-cart__toggle' ).attr( 'aria-label', label );
		}
		var $list = $root.find( '[data-wpec-items]' );
		if ( $list.length && complete( cart ) ) {
			$list.empty();
			for ( var i = 0; i < items.length; i++ ) {
				if ( items[ i ] ) {
					$list.append( api.row( items[ i ], root ) );
				}
			}
		}
		$root.find( '.wpec-mini-cart__empty' ).prop( 'hidden', items.length > 0 );
		$root.find( '.wpec-mini-cart__footer' ).prop( 'hidden', items.length <= 0 );
		if ( root.wpecRestore ) {
			var restore = root.wpecRestore;
			root.wpecRestore = null;
			var $target = $root.find( '.wpec-mini-cart__item[data-cartitem="' + restore.id + '"] ' + restore.selector ).first();
			if ( ! $target.length ) {
				$target = $root.find( '.wpec-mini-cart__remove, .wpec-mini-cart__close' ).first();
			}
			$target.trigger( 'focus' );
		}
	}

	/* Panels ------------------------------------------------------------------------------------------------------------- */

	var reduceMotion = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	/*
	 * A dropdown lines up with its icon, not with the widget ( which can be as wide as its column ): the icon's distance
	 * from each side of the widget, in --wpec-mc-start / -end / -center ( checkout.css; 0 before this runs = the widget's
	 * edge, as before round 11 ).
	 */
	api.place = function( root ) {
		var $root = $( root );
		if ( 'dropdown' !== $root.attr( 'data-wpec-menu-cart' ) ) {
			return;
		}
		var toggle = $root.find( '.wpec-menu-cart__toggle' ).get( 0 );
		var panel = $root.find( '.wpec-mini-cart' ).get( 0 );
		if ( ! toggle || ! panel || ! root.getBoundingClientRect || ! panel.style || ! panel.style.setProperty ) {
			return;
		}
		var r = root.getBoundingClientRect();
		var t = toggle.getBoundingClientRect();
		var rtl = window.getComputedStyle && 'rtl' === window.getComputedStyle( root ).direction;
		panel.style.setProperty( '--wpec-mc-start', Math.round( rtl ? r.right - t.right : t.left - r.left ) + 'px' );
		panel.style.setProperty( '--wpec-mc-end', Math.round( rtl ? t.left - r.left : r.right - t.right ) + 'px' );
		panel.style.setProperty( '--wpec-mc-center', Math.round( t.left - r.left + ( t.width / 2 ) ) + 'px' );
	};

	/* The editor remembers which panels are open, by the widget's element id, across the redraws a settings change makes. */
	var editorOpen = {};
	function elementId( root ) {
		return String( $( root ).closest( '.elementor-element[data-id]' ).attr( 'data-id' ) || '' );
	}
	function remember( root, open ) {
		var id = inEditor() ? elementId( root ) : '';
		if ( id ) {
			editorOpen[ id ] = !! open;
		}
	}

	api.open = function( root, opener, options ) {
		var $root = $( root );
		var $panel = $root.find( '.wpec-mini-cart' ).first();
		if ( ! $panel.length || $panel.hasClass( 'is-open' ) ) {
			return;
		}
		var modal = 'true' === $panel.attr( 'aria-modal' );
		var instant = !! ( options && options.instant );
		var focus = ! instant && ( modal || ! options || false !== options.focus );
		root.wpecOpener = opener || document.activeElement;
		api.place( root );
		$panel.prop( 'hidden', false );
		$root.find( '[aria-controls="' + $panel.attr( 'id' ) + '"]' ).attr( 'aria-expanded', 'true' );
		if ( modal && ! inEditor() ) {
			$( document.documentElement ).addClass( 'wpec-mini-cart-locked' );
		}
		remember( root, true );
		if ( instant ) {
			$panel.addClass( 'is-open' );
		} else {
			/*
			 * 6.0.2: the closed position is drawn before is-open, so the panel slides in every time. Shown and opened in one
			 * frame it just appeared; only a first open slid, because its cart request happened to make the browser draw it.
			 */
			var inner = $panel.find( '.wpec-mini-cart__inner' ).get( 0 ) || $panel.get( 0 );
			if ( inner && inner.getBoundingClientRect ) {
				inner.getBoundingClientRect();
			}
			var turn = $panel.data( 'wpecTurn' ) || 0;
			window.requestAnimationFrame( function() {
				if ( ( $panel.data( 'wpecTurn' ) || 0 ) !== turn ) {
					return; /* closed again before this frame */
				}
				$panel.addClass( 'is-open' );
				if ( ! focus ) {
					return;
				}
				var $first = $panel.find( FOCUSABLE ).filter( ':visible' ).first();
				( $first.length ? $first : $panel.find( '.wpec-mini-cart__inner' ) ).trigger( 'focus' );
			} );
		}
		if ( ! complete( current ) && ! inEditor() ) {
			api.refresh();
		}
		$( document ).trigger( 'wpeasycart_mini_cart_open', [ root ] );
	};

	api.close = function( root, keepFocus ) {
		var $root = $( root );
		var $panel = $root.find( '.wpec-mini-cart' ).first();
		if ( ! $panel.length || $panel.prop( 'hidden' ) ) {
			return;
		}
		$panel.data( 'wpecTurn', ( $panel.data( 'wpecTurn' ) || 0 ) + 1 );
		remember( root, false );
		$panel.removeClass( 'is-open' );
		$root.find( '[aria-controls="' + $panel.attr( 'id' ) + '"]' ).attr( 'aria-expanded', 'false' );
		var finish = function() {
			if ( ! $panel.hasClass( 'is-open' ) ) {
				$panel.prop( 'hidden', true );
			}
		};
		if ( reduceMotion || 'true' !== $panel.attr( 'aria-modal' ) ) {
			finish();
		} else {
			window.setTimeout( finish, 320 );
		}
		if ( ! $( '.wpec-mini-cart.is-open[aria-modal="true"]' ).length ) {
			$( document.documentElement ).removeClass( 'wpec-mini-cart-locked' );
		}
		if ( ! keepFocus && root.wpecOpener && 'function' === typeof root.wpecOpener.focus ) {
			root.wpecOpener.focus();
		}
	};

	function rootOf( el ) {
		return $( el ).closest( '[data-wpec-mini-cart-root], .wpec-menu-cart' ).get( 0 );
	}

	/* Toggle: click or Enter opens; hover opens a dropdown on devices with a mouse. */
	$( document ).on( 'click', '.wpec-menu-cart[data-wpec-menu-cart="dropdown"] .wpec-menu-cart__toggle, .wpec-menu-cart[data-wpec-menu-cart="panel"] .wpec-menu-cart__toggle', function( e ) {
		e.preventDefault();
		var root = rootOf( this );
		if ( $( root ).find( '.wpec-mini-cart' ).hasClass( 'is-open' ) ) {
			api.close( root );
		} else {
			api.open( root, this );
		}
	} );
	$( document ).on( 'keydown', '.wpec-menu-cart .wpec-menu-cart__toggle[role="button"]', function( e ) {
		if ( ' ' === e.key || 'Spacebar' === e.key ) {
			e.preventDefault();
			$( this ).trigger( 'click' );
		}
	} );
	var hoverTimer = null;
	$( document ).on( 'mouseenter', '.wpec-menu-cart[data-wpec-menu-cart="dropdown"][data-wpec-hover="1"]', function() {
		if ( window.matchMedia && ! window.matchMedia( '(hover: hover)' ).matches ) {
			return;
		}
		window.clearTimeout( hoverTimer );
		api.open( this, $( this ).find( '.wpec-menu-cart__toggle' ).get( 0 ) );
	} );
	$( document ).on( 'mouseleave', '.wpec-menu-cart[data-wpec-menu-cart="dropdown"][data-wpec-hover="1"]', function() {
		var root = this;
		hoverTimer = window.setTimeout( function() {
			api.close( root, true );
		}, 250 );
	} );
	$( document ).on( 'click', '.wpec-mini-cart [data-wpec-close]', function( e ) {
		e.preventDefault();
		api.close( rootOf( this ) );
	} );
	/* A dropdown closes on a click elsewhere. */
	$( document ).on( 'click', function( e ) {
		$( '.wpec-menu-cart[data-wpec-menu-cart="dropdown"]' ).each( function() {
			if ( ! this.contains( e.target ) ) {
				api.close( this, true );
			}
		} );
	} );
	/* Esc closes; Tab stays inside a modal panel. */
	$( document ).on( 'keydown', function( e ) {
		var $open = $( '.wpec-mini-cart.is-open' );
		if ( ! $open.length ) {
			return;
		}
		if ( 'Escape' === e.key || 'Esc' === e.key ) {
			$open.each( function() {
				api.close( rootOf( this ) );
			} );
			return;
		}
		if ( 'Tab' !== e.key ) {
			return;
		}
		var panel = $open.filter( '[aria-modal="true"]' ).get( 0 );
		if ( ! panel ) {
			return;
		}
		var $focusable = $( panel ).find( FOCUSABLE ).filter( ':visible' );
		if ( ! $focusable.length ) {
			e.preventDefault();
			return;
		}
		var first = $focusable.get( 0 );
		var last = $focusable.get( $focusable.length - 1 );
		if ( ! panel.contains( document.activeElement ) ) {
			e.preventDefault();
			first.focus();
		} else if ( e.shiftKey && document.activeElement === first ) {
			e.preventDefault();
			last.focus();
		} else if ( ! e.shiftKey && document.activeElement === last ) {
			e.preventDefault();
			first.focus();
		}
	} );

	/* Remove a line. */
	$( document ).on( 'click', '.wpec-menu-cart [data-wpec-remove]', function( e ) {
		e.preventDefault();
		var $row = $( this ).closest( '.wpec-mini-cart__item' );
		var item = $row.data( 'wpecItem' );
		var root = rootOf( this );
		if ( ! item ) {
			api.refresh();
			return;
		}
		$row.addClass( 'is-busy' );
		api.remove( item ).always( function() {
			api.announce( root, api.text( 'updated' ) );
			var $next = $( root ).find( '.wpec-mini-cart__remove, .wpec-mini-cart__close' ).not( $row.find( '*' ) ).first();
			if ( $next.length ) {
				$next.trigger( 'focus' );
			}
		} );
	} );

	/* Quantity buttons of a Menu Cart line ( round 11; WP EasyCart PRO's Side Cart has its own ). */
	function changeQuantity( el, quantity, focusSelector ) {
		var $row = $( el ).closest( '.wpec-mini-cart__item' );
		var item = $row.data( 'wpecItem' );
		var root = rootOf( el );
		if ( ! item || ! root ) {
			return;
		}
		var min = Math.max( 1, Number( item.min_quantity ) || 1 );
		var max = Number( item.max_quantity ) || 0;
		quantity = Math.max( min, Math.round( Number( quantity ) || min ) );
		if ( max > 0 ) {
			quantity = Math.min( max, quantity );
		}
		if ( quantity === Number( item.quantity ) ) {
			$row.find( '.wpec-mini-cart__qty input' ).val( quantity );
			return;
		}
		root.wpecRestore = { id: String( item.cartitem_id ), selector: focusSelector };
		$row.addClass( 'is-busy' );
		api.update( item, quantity ).always( function() {
			api.announce( root, api.text( 'updated' ) );
		} );
	}
	$( document ).on( 'click', '.wpec-menu-cart [data-wpec-mc-step]', function( e ) {
		e.preventDefault();
		var item = $( this ).closest( '.wpec-mini-cart__item' ).data( 'wpecItem' );
		if ( item ) {
			var step = this.getAttribute( 'data-wpec-mc-step' );
			changeQuantity( this, ( Number( item.quantity ) || 1 ) + Number( step ), '[data-wpec-mc-step="' + step + '"]' );
		}
	} );
	var typing = null;
	$( document ).on( 'change', '.wpec-menu-cart .wpec-mini-cart__qty input', function() {
		var input = this;
		window.clearTimeout( typing );
		typing = window.setTimeout( function() {
			changeQuantity( input, input.value, '.wpec-mini-cart__qty input' );
		}, 300 );
	} );
	$( document ).on( 'keydown', '.wpec-menu-cart .wpec-mini-cart__qty input', function( e ) {
		if ( 'Enter' === e.key ) {
			e.preventDefault();
			$( this ).trigger( 'change' );
		}
	} );

	/* "Open when a product is added" ( round 11 ): a visible Menu Cart set to open shows the add; a dropdown keeps focus where it is. */
	function inEditor() {
		if ( window.elementorFrontend && window.elementorFrontend.isEditMode ) {
			return !! window.elementorFrontend.isEditMode();
		}
		/* Before Elementor's script starts ( a cart answer can come first ), the preview's body class says it. */
		return !! ( document.body && document.body.classList && document.body.classList.contains( 'elementor-editor-active' ) );
	}
	$( document ).on( 'wpeasycart_item_added', function( e, item ) {
		if ( inEditor() ) {
			return;
		}
		var now = new Date().getTime();
		$( '.wpec-menu-cart[data-wpec-open-on-add="1"]' ).each( function() {
			var root = this;
			if ( ( root.wpecAddedAt && now - root.wpecAddedAt < 800 ) || ! $( root ).is( ':visible' ) ) {
				return;
			}
			root.wpecAddedAt = now;
			api.open( root, document.activeElement, { focus: false } );
			api.refresh();
			var name = ( item && item.content_name ) ? String( item.content_name ) : '';
			api.announce( root, name ? api.text( 'added', { title: name } ) : api.text( 'updated' ) );
		} );
	} );

	/* Dropdowns follow their icon when the window changes size, and in the editor's "show it open" preview. */
	var placing = null;
	$( window ).on( 'resize', function() {
		window.clearTimeout( placing );
		placing = window.setTimeout( function() {
			$( '.wpec-menu-cart[data-wpec-menu-cart="dropdown"]' ).each( function() {
				if ( ! $( this ).find( '.wpec-mini-cart' ).prop( 'hidden' ) ) {
					api.place( this );
				}
			} );
		}, 120 );
	} );
	/*
	 * Each Menu Cart and Side Cart Elementor draws ( the page, the editor after every redraw ): line a dropdown up with its icon,
	 * and in the editor open again a panel that was open before a settings change redrew it ( at once, focus left in the
	 * editor's settings ). WP EasyCart PRO's Side Cart has the same widget name as WP EasyCart's locked one.
	 */
	var hooked = false;
	function hookElementor() {
		if ( hooked || ! window.elementorFrontend || ! window.elementorFrontend.hooks ) {
			return;
		}
		hooked = true;
		var ready = function( $element ) {
			var root = ( $element && $element.find ) ? $element.find( '.wpec-menu-cart, [data-wpec-mini-cart-root]' ).get( 0 ) : null;
			if ( ! root ) {
				return;
			}
			api.place( root );
			var id = inEditor() ? String( $element.attr( 'data-id' ) || '' ) : '';
			if ( id && editorOpen[ id ] ) {
				api.open( root, null, { instant: true } );
			}
		};
		window.elementorFrontend.hooks.addAction( 'frontend/element_ready/wp_easycart_menu_cart.default', ready );
		window.elementorFrontend.hooks.addAction( 'frontend/element_ready/wp_easycart_side_cart.default', ready );
	}
	if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
		hookElementor();
	} else {
		$( window ).on( 'elementor/frontend/init', hookElementor );
	}

	/* The cart changed ( an add, the cart page, another widget ). */
	var lastFullAsk = 0;
	$( document ).on( 'wpeasycart_cart_changed', function( e, cart ) {
		if ( complete( cart ) ) {
			api.set( cart );
		} else if ( cart && 'undefined' !== typeof cart.count ) {
			api.set( cart ); /* the count at once; the lines once the full state answers ( asked at most every few seconds ) */
			var now = new Date().getTime();
			if ( now - lastFullAsk > 3000 && ( $( '.wpec-menu-cart' ).length || $( '[data-wpec-mini-cart-root]' ).length ) ) {
				lastFullAsk = now;
				api.refresh();
			}
		}
	} );

	/* An older ec-store.js ( a theme's copy ) does not ask for the cart state: ask once here. */
	$( function() {
		if ( 'function' !== typeof window.wpeasycart_refresh_cart_widgets && ( $( '.wpec-menu-cart' ).length || $( '[data-wpec-mini-cart-root]' ).length ) ) {
			api.refresh();
		}
	} );
}( window.jQuery, window, document ) );
