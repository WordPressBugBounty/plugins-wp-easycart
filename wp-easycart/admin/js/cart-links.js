/**
 * WP EasyCart — Marketing > Cart Links (FREE).
 * Builder drawer + list interactions. PRO augments through the
 * ecv2_cart_link_* jQuery events triggered below.
 */
( function( $ ) {
	'use strict';

	var V = window.ecv2_cart_link_vars || { ajax_url: window.ajaxurl || '', nonce: '', lang: {} };
	var L = function( key, dflt ) { return ( V.lang && V.lang[ key ] ) || dflt || ''; };
	var state = { id: 0, product: null, needs_reload: false, search_timer: null };

	function esc( s ) {
		return $( '<span>' ).text( s == null ? '' : s ).html();
	}

	/* Self-contained toast: this page loads none of the list scripts that
	 * usually define ecv2_toast, so define it here ( guarded ). */
	if ( typeof window.ecv2_toast !== 'function' ) {
		window.ecv2_toast = function( msg, type ) {
			var $holder = $( '#ecv2-cl-toasts' );
			if ( ! $holder.length ) {
				$holder = $( '<div id="ecv2-cl-toasts" class="ecv2-cl-toasts"></div>' ).appendTo( document.body );
			}
			var $toast = $( '<div class="ecv2-cl-toast"></div>' )
				.addClass( 'error' === type ? 'ecv2-cl-toast-error' : 'ecv2-cl-toast-success' )
				.text( msg == null ? '' : msg )
				.appendTo( $holder );
			window.requestAnimationFrame( function() { $toast.addClass( 'is-in' ); } );
			setTimeout( function() {
				$toast.removeClass( 'is-in' );
				setTimeout( function() { $toast.remove(); }, 250 );
			}, 2600 );
		};
	}
	function toast( msg, type ) { window.ecv2_toast( msg, type ); }
	function post( action, data ) {
		data = $.extend( { action: action, wp_easycart_nonce: V.nonce }, data || {} );
		return $.post( V.ajax_url, data );
	}

	/* ------------------------------------------------------------------ */
	/* PRO upsell — thin alias; the shared popup lives in upsell.js         */
	/* ------------------------------------------------------------------ */

	window.ecv2_cart_link_upsell = function( feature ) {
		if ( typeof window.ecdv2_upsell === 'function' ) {
			return window.ecdv2_upsell( { context: 'cart_links', feature: feature || '' } );
		}
		return false;
	};

	/* Locked drawer sections are keyboard-reachable ( role=button ). */
	$( document ).on( 'keydown', '.ecv2-cl-locked', function( e ) {
		if ( e.key === 'Enter' || e.key === ' ' ) {
			e.preventDefault();
			window.ecv2_cart_link_upsell( $( this ).data( 'feature' ) );
		}
	} );

	/* ------------------------------------------------------------------ */
	/* Drawer open / close                                                 */
	/* ------------------------------------------------------------------ */

	window.ecv2_cart_link_new = function() {
		state.id = 0;
		state.product = null;
		$( '#ecv2_cl_id' ).val( 0 );
		$( '#ecv2_cl_label' ).val( '' );
		$( 'input[name="ecv2_cl_destination"][value="cart"]' ).prop( 'checked', true );
		$( '#ecv2_cl_clear_cart' ).prop( 'checked', false );
		$( '#ecv2_cl_footer_note' ).text( '' );
		render_item_search();
		$( document ).trigger( 'ecv2_cart_link_drawer_reset' );
		open_drawer();
		setTimeout( function() { $( '#ecv2_cl_product_search' ).trigger( 'focus' ); }, 200 );
		return false;
	};

	window.ecv2_cart_link_edit = function( id ) {
		post( 'ecv2_cart_link_get', { cart_link_id: id } ).done( function( response ) {
			if ( ! response || ! response.success ) {
				toast( ( response && response.data && response.data.message ) || L( 'error' ), 'error' );
				return;
			}
			var link = response.data;
			/* Fresh slate on every edit open: without this, re-opening kept the
			 * previous hydration's lines and duplicated the product list. */
			state.product = null;
			$( '#ecv2_cl_items' ).empty();
			$( document ).trigger( 'ecv2_cart_link_drawer_reset' );

			state.id = parseInt( link.cart_link_id, 10 );
			$( '#ecv2_cl_id' ).val( state.id );
			$( '#ecv2_cl_label' ).val( link.link_label || '' );
			$( 'input[name="ecv2_cl_destination"][value="' + ( link.destination === 'checkout' ? 'checkout' : 'cart' ) + '"]' ).prop( 'checked', true );
			$( '#ecv2_cl_clear_cart' ).prop( 'checked', String( link.clear_cart ) === '1' );
			$( document ).trigger( 'ecv2_cart_link_drawer_loaded', [ link ] );

			var first = ( link.items && link.items.length ) ? link.items[ 0 ] : null;
			if ( first ) {
				load_product( parseInt( first.product_id, 10 ), first );
			} else {
				render_item_search();
			}
			open_drawer();
		} ).fail( function() { toast( L( 'error' ), 'error' ); } );
		return false;
	};

	function open_drawer() {
		$( '#ecv2_cl_title' ).text( state.id ? 'Edit Cart Link' : 'New Cart Link' );
		var $nodes = $( '#ecv2_cl_backdrop, #ecv2_cl_drawer' ).show();
		/* Class on the next frame so the slide-in transition actually runs. */
		window.requestAnimationFrame( function() { $nodes.addClass( 'ecv2-drawer-open' ); } );
		$( 'body' ).addClass( 'ecv2-cl-drawer-lock' );
	}

	window.ecv2_cart_link_close = function() {
		var $nodes = $( '#ecv2_cl_backdrop, #ecv2_cl_drawer' ).removeClass( 'ecv2-drawer-open' );
		$( 'body' ).removeClass( 'ecv2-cl-drawer-lock' );
		setTimeout( function() { $nodes.hide(); }, 220 ); /* after the slide-out */
		return false;
	};

	/* ------------------------------------------------------------------ */
	/* Product line ( FREE: exactly one )                                  */
	/* ------------------------------------------------------------------ */

	function render_item_search() {
		var html = '';
		html += '<div class="ecv2-cl-item" id="ecv2_cl_item">';
		html += '<input type="text" id="ecv2_cl_product_search" class="ecv2-input" placeholder="Search products by name or SKU…" autocomplete="off" />';
		html += '<div class="ecv2-cl-search-results" id="ecv2_cl_search_results"></div>';
		html += '</div>';
		$( '#ecv2_cl_items' ).html( html );
	}

	$( document ).on( 'input', '#ecv2_cl_product_search', function() {
		var term = $( this ).val();
		clearTimeout( state.search_timer );
		if ( term.length < 2 ) {
			$( '#ecv2_cl_search_results' ).empty();
			return;
		}
		state.search_timer = setTimeout( function() {
			$( '#ecv2_cl_search_results' ).html( '<div class="ecv2-cl-search-note">' + esc( L( 'searching', 'Searching…' ) ) + '</div>' );
			post( 'ecv2_cart_link_product_search', { term: term } ).done( function( response ) {
				var products = ( response && response.success && response.data.products ) || [];
				if ( ! products.length ) {
					$( '#ecv2_cl_search_results' ).html( '<div class="ecv2-cl-search-note">' + esc( L( 'no_products', 'No matching products found.' ) ) + '</div>' );
					return;
				}
				var html = '';
				products.forEach( function( p ) {
					html += '<button type="button" class="ecv2-cl-search-hit" data-product-id="' + p.product_id + '">';
					html += '<span class="ecv2-cl-hit-title">' + esc( p.title ) + '</span>';
					html += '<span class="ecv2-cl-hit-meta">' + esc( p.sku ) + ' · ' + esc( p.price ) + '</span>';
					html += '</button>';
				} );
				$( '#ecv2_cl_search_results' ).html( html );
			} );
		}, 250 );
	} );

	$( document ).on( 'click', '.ecv2-cl-search-hit', function() {
		load_product( parseInt( $( this ).data( 'product-id' ), 10 ), null );
	} );

	function load_product( product_id, presets ) {
		post( 'ecv2_cart_link_product_data', { product_id: product_id } ).done( function( response ) {
			if ( ! response || ! response.success ) {
				toast( ( response && response.data && response.data.message ) || L( 'error' ), 'error' );
				return;
			}
			state.product = response.data;
			render_product_line( response.data, presets || {} );
			$( document ).trigger( 'ecv2_cart_link_product_loaded', [ response.data, presets || {} ] );
		} ).fail( function() { toast( L( 'error' ), 'error' ); } );
	}

	function render_product_line( p, presets ) {
		var qty = Math.max( 1, parseInt( presets.quantity, 10 ) || 1 );
		var html = '';
		html += '<div class="ecv2-cl-item ecv2-cl-item-selected" id="ecv2_cl_item" data-product-id="' + p.product_id + '">';
		html += '<div class="ecv2-cl-item-head">';
		html += '<div class="ecv2-cl-item-title">' + esc( p.title ) + ' <span class="ecv2-sku-chip">' + esc( p.sku ) + '</span> <span class="ecv2-qe-muted">' + esc( p.price ) + '</span></div>';
		html += '<button type="button" class="ecv2-cl-item-swap" onclick="ecv2_cart_link_swap_product(); return false;">Change</button>';
		html += '</div>';
		html += '<div class="ecv2-cl-item-fields">';
		html += '<label class="ecv2-cl-field"><span>Quantity</span><input type="number" min="1" step="1" id="ecv2_cl_qty" class="ecv2-input" value="' + qty + '" /></label>';
		( p.basic || [] ).forEach( function( slot ) {
			var selected = presets[ 'optionitem_id_' + slot.slot ] || 0;
			html += '<label class="ecv2-cl-field"><span>' + esc( slot.label ) + '</span>';
			html += '<select class="ecv2-select ecv2-cl-basic" data-slot="' + slot.slot + '">';
			slot.items.forEach( function( item, idx ) {
				var sel = ( parseInt( selected, 10 ) === item.id || ( ! selected && idx === 0 ) ) ? ' selected' : '';
				html += '<option value="' + item.id + '"' + sel + '>' + esc( item.name ) + '</option>';
			} );
			html += '</select></label>';
		} );
		html += '</div>';
		html += '<div class="ecv2-cl-item-pro" id="ecv2_cl_item_pro"></div>'; /* PRO mounts modifier inputs here */
		html += '</div>';
		/* Replace ONLY the open search line — .html() on the container wiped
		 * already-archived lines on PRO multi-product links. */
		var $current = $( '#ecv2_cl_item' );
		if ( $current.length ) {
			$current.replaceWith( html );
		} else {
			$( '#ecv2_cl_items' ).append( html );
		}
	}

	window.ecv2_cart_link_swap_product = function() {
		state.product = null;
		render_item_search();
		setTimeout( function() { $( '#ecv2_cl_product_search' ).trigger( 'focus' ); }, 50 );
	};

	/* ------------------------------------------------------------------ */
	/* Save                                                                */
	/* ------------------------------------------------------------------ */

	window.ecv2_cart_link_save = function() {
		if ( ! state.product ) {
			toast( L( 'select_product', 'Search for a product first.' ), 'error' );
			return false;
		}
		var item = {
			product_id: state.product.product_id,
			quantity: Math.max( 1, parseInt( $( '#ecv2_cl_qty' ).val(), 10 ) || 1 )
		};
		$( '.ecv2-cl-basic' ).each( function() {
			item[ 'optionitem_id_' + $( this ).data( 'slot' ) ] = parseInt( $( this ).val(), 10 ) || 0;
		} );

		var payload = {
			cart_link_id: state.id,
			link_label: $( '#ecv2_cl_label' ).val(),
			destination: $( 'input[name="ecv2_cl_destination"]:checked' ).val(),
			clear_cart: $( '#ecv2_cl_clear_cart' ).is( ':checked' ) ? 1 : 0,
			is_active: 1,
			items: [ item ]
		};
		$( document ).trigger( 'ecv2_cart_link_before_save', [ payload, state ] );

		var was_edit = state.id > 0;
		var $btn = $( '#ecv2_cl_save' );
		var btn_label = $btn.text();
		$btn.prop( 'disabled', true ).addClass( 'ecv2-cl-btn-busy' ).text( L( 'saving', 'Saving…' ) );
		post( 'ecv2_cart_link_save', payload ).done( function( response ) {
			if ( ! response || ! response.success ) {
				$btn.prop( 'disabled', false ).removeClass( 'ecv2-cl-btn-busy' ).text( btn_label );
				toast( ( response && response.data && response.data.message ) || L( 'error' ), 'error' );
				return;
			}
			$( document ).trigger( 'ecv2_cart_link_saved', [ response.data ] );
			/* Reload so the list re-renders server-side; the banner param
			 * surfaces the confirmation with copy / edit shortcuts. */
			var url = window.location.href.replace( /[?&]cl_saved=\d+/g, '' ).replace( /[?&]cl_updated=\d+/g, '' );
			url += ( url.indexOf( '?' ) > -1 ? '&' : '?' ) + ( was_edit ? 'cl_updated=' : 'cl_saved=' ) + response.data.cart_link_id;
			window.location.href = url;
		} ).fail( function() {
			$btn.prop( 'disabled', false ).removeClass( 'ecv2-cl-btn-busy' ).text( btn_label );
			toast( L( 'error' ), 'error' );
		} );
		return false;
	};

	/* ------------------------------------------------------------------ */
	/* List actions                                                        */
	/* ------------------------------------------------------------------ */

	window.ecv2_cart_link_copy_row = function( el ) {
		copy_text( $( el ).data( 'url' ) );
		close_menus();
		return false;
	};
	function copy_text( text ) {
		if ( ! text ) { return; }
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( text ).then( function() { toast( L( 'copied', 'Link copied.' ), 'success' ); } );
		} else {
			var input = document.createElement( 'input' );
			input.value = text;
			document.body.appendChild( input );
			input.select();
			try { document.execCommand( 'copy' ); toast( L( 'copied', 'Link copied.' ), 'success' ); } catch ( e ) {}
			document.body.removeChild( input );
		}
	}

	window.ecv2_cart_link_toggle = function( input, id ) {
		post( 'ecv2_cart_link_set_active', { cart_link_id: id, is_active: input.checked ? 1 : 0 } ).done( function( response ) {
			if ( ! response || ! response.success ) {
				input.checked = ! input.checked;
				toast( L( 'error' ), 'error' );
				return;
			}
			toast( input.checked ? L( 'active', 'Active' ) : L( 'inactive', 'Inactive' ), 'success' );
		} ).fail( function() {
			input.checked = ! input.checked;
			toast( L( 'error' ), 'error' );
		} );
	};

	window.ecv2_cart_link_delete = function( id ) {
		close_menus();
		var run = function( ok ) {
			if ( ! ok ) { return; }
			post( 'ecv2_cart_link_delete', { cart_link_id: id } ).done( function( response ) {
				if ( response && response.success ) {
					$( 'tr[data-cart-link-id="' + id + '"]' ).remove();
					toast( L( 'deleted', 'Cart link deleted.' ), 'success' );
					if ( ! $( '#ecv2_cart_links_body tr' ).length ) {
						window.location.reload();
					}
				} else {
					toast( L( 'error' ), 'error' );
				}
			} );
		};
		if ( typeof window.ecdv2_confirm === 'function' ) {
			window.ecdv2_confirm( { title: L( 'delete', 'Delete' ), message: L( 'confirm_delete' ), confirm_label: L( 'delete', 'Delete' ), danger: true } ).then( run );
		} else {
			run( window.confirm( L( 'confirm_delete' ) ) );
		}
		return false;
	};

	window.ecv2_cart_link_menu = function( trigger ) {
		var $menu = $( trigger ).siblings( '.ecv2-row-menu' );
		var open = $menu.hasClass( 'ecv2-row-menu-open' );
		close_menus();
		if ( ! open ) { $menu.addClass( 'ecv2-row-menu-open' ); }
		return false;
	};
	function close_menus() {
		$( '.ecv2-row-menu' ).removeClass( 'ecv2-row-menu-open' );
	}
	$( document ).on( 'click', function( e ) {
		if ( ! $( e.target ).closest( '.ecv2-row-menu-wrap' ).length ) { close_menus(); }
	} );
	$( document ).on( 'keydown', function( e ) {
		if ( e.key !== 'Escape' ) { return; }
		if ( $( '#ec_admin_upsell_popup' ).is( ':visible' ) ) { return; } /* upsell.js handles it */
		if ( $( '#ecv2_cl_drawer' ).hasClass( 'ecv2-drawer-open' ) ) { window.ecv2_cart_link_close(); }
	} );

} )( jQuery );