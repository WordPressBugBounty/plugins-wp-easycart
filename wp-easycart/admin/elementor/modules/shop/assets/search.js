/**
 * WP EasyCart Product Search widget ( 6.0.2 ): live product suggestions as an ARIA 1.2 combobox.
 *
 * Down / Up move through the suggestions ( aria-activedescendant, focus stays in the box ), Enter opens the chosen one
 * ( or searches ), Escape closes the list and then clears the box. Suggestions come from the public endpoint
 * wp_easycart_el_search ( no nonce, so a cached page still searches ), are built as elements and text, and the result
 * count is read out politely. Without this script the box is a plain search form.
 */
( function () {
	'use strict';

	var data = window.wpecElSearch || { ajaxUrl: '', i18n: {} };

	function t( key, fallback ) {
		return ( data.i18n && data.i18n[ key ] ) ? data.i18n[ key ] : ( fallback || '' );
	}

	function fill( text, pairs ) {
		Object.keys( pairs ).forEach( function ( key ) {
			text = String( text ).split( '[' + key + ']' ).join( pairs[ key ] );
		} );
		return text;
	}

	function parseJson( text ) {
		try {
			return JSON.parse( String( text ).replace( /^\uFEFF/, '' ).trim() );
		} catch ( e ) {
			return null;
		}
	}

	/* The title with the typed text marked, as elements ( never markup from the server ). */
	function highlighted( title, term ) {
		var holder = document.createElement( 'span' );
		holder.className = 'wpec-search__option-title';
		var at = title.toLowerCase().indexOf( term.toLowerCase() );
		if ( at < 0 || ! term ) {
			holder.textContent = title;
			return holder;
		}
		holder.appendChild( document.createTextNode( title.slice( 0, at ) ) );
		var mark = document.createElement( 'mark' );
		mark.textContent = title.slice( at, at + term.length );
		holder.appendChild( mark );
		holder.appendChild( document.createTextNode( title.slice( at + term.length ) ) );
		return holder;
	}

	function init( root ) {
		if ( root.wpecSearch ) {
			return;
		}
		root.wpecSearch = true;
		var config = parseJson( root.getAttribute( 'data-wpec-search' ) );
		var form = root.querySelector( 'form' );
		var input = root.querySelector( '.wpec-search__input' );
		var panel = root.querySelector( '.wpec-search__panel' );
		var list = root.querySelector( '.wpec-search__list' );
		var status = root.querySelector( '[data-wpec-search-status]' );
		if ( ! config || ! form || ! input || ! panel || ! list || ! data.ajaxUrl || typeof window.fetch !== 'function' ) {
			return;
		}
		var none = document.createElement( 'p' );
		none.className = 'wpec-search__none';
		none.hidden = true;
		panel.appendChild( none );

		var cache = {};
		var options = [];
		var active = -1;
		var timer = null;
		var controller = null;

		function say( text ) {
			if ( status ) {
				status.textContent = text;
			}
		}

		function open() {
			panel.hidden = false;
			input.setAttribute( 'aria-expanded', 'true' );
		}

		function close() {
			panel.hidden = true;
			input.setAttribute( 'aria-expanded', 'false' );
			setActive( -1 );
		}

		function setActive( index ) {
			options.forEach( function ( option, i ) {
				option.classList.toggle( 'is-active', i === index );
				option.setAttribute( 'aria-selected', i === index ? 'true' : 'false' );
			} );
			active = index;
			if ( index >= 0 && options[ index ] ) {
				input.setAttribute( 'aria-activedescendant', options[ index ].id );
				if ( options[ index ].scrollIntoView ) {
					options[ index ].scrollIntoView( { block: 'nearest' } );
				}
			} else {
				input.removeAttribute( 'aria-activedescendant' );
			}
		}

		/* The results page for a term: the form's address with its own query and ec_search. */
		function allResults( term ) {
			var action = form.getAttribute( 'action' ) || window.location.href;
			var base = action.split( '#' )[ 0 ].split( '?' )[ 0 ];
			var params = [];
			Array.prototype.forEach.call( form.querySelectorAll( 'input[type="hidden"]' ), function ( field ) {
				if ( field.name && 'ec_search' !== field.name ) {
					params.push( encodeURIComponent( field.name ) + '=' + encodeURIComponent( field.value ) );
				}
			} );
			params.push( 'ec_search=' + encodeURIComponent( term ) );
			return base + '?' + params.join( '&' );
		}

		function option( id, url ) {
			var item = document.createElement( 'li' );
			item.id = id;
			item.className = 'wpec-search__option';
			item.setAttribute( 'role', 'option' );
			item.setAttribute( 'aria-selected', 'false' );
			item.setAttribute( 'data-url', url );
			item.addEventListener( 'mousedown', function ( event ) {
				event.preventDefault(); /* Keep the focus in the box. */
			} );
			item.addEventListener( 'click', function () {
				window.location.href = url;
			} );
			return item;
		}

		function render( term, result ) {
			var items = ( result && result.items ) ? result.items.slice( 0, config.max ) : [];
			var total = ( result && result.total ) ? result.total : items.length;
			list.innerHTML = '';
			options = [];
			active = -1;
			items.forEach( function ( found, index ) {
				var item = option( list.id + '-' + index, found.url );
				if ( config.images ) {
					if ( found.image ) {
						var image = document.createElement( 'img' );
						image.className = 'wpec-search__thumb';
						image.src = found.image;
						image.alt = '';
						image.width = 40;
						image.height = 40;
						image.loading = 'lazy';
						item.appendChild( image );
					} else {
						var blank = document.createElement( 'span' );
						blank.className = 'wpec-search__thumb wpec-search__thumb--empty';
						blank.setAttribute( 'aria-hidden', 'true' );
						item.appendChild( blank );
					}
				}
				var text = document.createElement( 'span' );
				text.className = 'wpec-search__option-text';
				text.appendChild( highlighted( String( found.title || '' ), term ) );
				if ( config.prices && found.price ) {
					var price = document.createElement( 'span' );
					price.className = 'wpec-search__option-price';
					price.textContent = found.price;
					text.appendChild( price );
				}
				item.appendChild( text );
				list.appendChild( item );
				options.push( item );
			} );
			if ( config.all && items.length ) {
				var every = option( list.id + '-all', allResults( term ) );
				every.classList.add( 'wpec-search__all' );
				every.textContent = fill( t( 'viewAll', 'View all results for “[term]”' ), { term: term } );
				list.appendChild( every );
				options.push( every );
			}
			list.hidden = ! options.length;
			none.hidden = !! items.length;
			none.textContent = items.length ? '' : fill( t( 'none', 'No products match “[term]”.' ), { term: term } );
			say( items.length ? ( 1 === total ? t( 'one', '1 product found.' ) : fill( t( 'results', '[count] products found.' ), { count: total } ) ) : none.textContent );
			open();
		}

		function search( term ) {
			if ( cache[ term ] ) {
				render( term, cache[ term ] );
				return;
			}
			if ( controller ) {
				controller.abort();
			}
			controller = ( typeof window.AbortController === 'function' ) ? new window.AbortController() : null;
			var url = data.ajaxUrl + ( data.ajaxUrl.indexOf( '?' ) === -1 ? '?' : '&' ) + 'action=wp_easycart_el_search&q=' + encodeURIComponent( term ) + '&limit=' + encodeURIComponent( config.max ) + '&images=' + ( config.images ? 1 : 0 ) + '&prices=' + ( config.prices ? 1 : 0 );
			root.classList.add( 'is-searching' );
			fetch( url, { credentials: 'same-origin', signal: controller ? controller.signal : undefined } ).then( function ( response ) {
				return response.text();
			} ).then( function ( text ) {
				root.classList.remove( 'is-searching' );
				var json = parseJson( text );
				if ( ! json ) {
					return;
				}
				cache[ term ] = json;
				if ( input.value.trim() === term ) {
					render( term, json );
				}
			} ).catch( function () {
				root.classList.remove( 'is-searching' );
			} );
		}

		input.addEventListener( 'input', function () {
			var term = input.value.trim();
			clearTimeout( timer );
			if ( term.length < config.min ) {
				close();
				list.innerHTML = '';
				options = [];
				say( '' );
				return;
			}
			timer = setTimeout( function () {
				search( term );
			}, 200 );
		} );

		input.addEventListener( 'keydown', function ( event ) {
			if ( 'ArrowDown' === event.key ) {
				if ( options.length ) {
					event.preventDefault();
					open();
					setActive( ( active + 1 ) % options.length );
				}
			} else if ( 'ArrowUp' === event.key ) {
				if ( options.length ) {
					event.preventDefault();
					open();
					setActive( active <= 0 ? options.length - 1 : active - 1 );
				}
			} else if ( 'Enter' === event.key ) {
				if ( ! panel.hidden && active >= 0 && options[ active ] ) {
					event.preventDefault();
					window.location.href = options[ active ].getAttribute( 'data-url' );
				}
			} else if ( 'Escape' === event.key ) {
				if ( ! panel.hidden ) {
					event.preventDefault();
					close();
				} else if ( input.value ) {
					event.preventDefault();
					input.value = '';
				}
			} else if ( 'Tab' === event.key ) {
				close();
			}
		} );

		input.addEventListener( 'focus', function () {
			if ( options.length && input.value.trim().length >= config.min ) {
				open();
			}
		} );

		document.addEventListener( 'click', function ( event ) {
			if ( ! root.contains( event.target ) ) {
				close();
			}
		} );
	}

	function initAll( scope ) {
		Array.prototype.forEach.call( ( scope || document ).querySelectorAll( '[data-wpec-search]' ), init );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			initAll( document );
		} );
	} else {
		initAll( document );
	}

	function hookElementor() {
		if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
			window.elementorFrontend.hooks.addAction( 'frontend/element_ready/wp_easycart_product_search.default', function ( $scope ) {
				initAll( $scope && $scope[ 0 ] ? $scope[ 0 ] : document );
			} );
		}
	}

	if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
		hookElementor();
	} else if ( window.jQuery ) {
		window.jQuery( window ).on( 'elementor/frontend/init', hookElementor );
	}
}() );
