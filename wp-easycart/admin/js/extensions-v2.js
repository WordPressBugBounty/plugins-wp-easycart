/**
 * WP EasyCart — Extensions page: category pills and search ( both filter in the browser ).
 * A #category in the URL ( e.g. #shipping ) opens that category.
 *
 * @since 6.0.2
 */
( function() {
	'use strict';

	var root = document.getElementById( 'ecext' );
	if ( ! root ) {
		return;
	}
	var cards  = Array.prototype.slice.call( root.querySelectorAll( '.ecext-card' ) );
	var pills  = Array.prototype.slice.call( root.querySelectorAll( '.ecext-pill' ) );
	var search = document.getElementById( 'ecext_search' );
	var empty  = document.getElementById( 'ecext_empty' );
	var filter = 'all';

	function apply() {
		var term  = search ? search.value.trim().toLowerCase() : '';
		var shown = 0;
		cards.forEach( function( card ) {
			var ok;
			if ( 'all' === filter ) {
				ok = true;
			} else if ( 'installed' === filter ) {
				ok = '1' === card.getAttribute( 'data-installed' );
			} else {
				ok = filter === card.getAttribute( 'data-category' );
			}
			if ( ok && term ) {
				ok = -1 !== ( card.getAttribute( 'data-search' ) || '' ).indexOf( term );
			}
			card.hidden = ! ok;
			if ( ok ) {
				shown++;
			}
		} );
		if ( empty ) {
			empty.hidden = shown > 0;
		}
	}

	function choose( value ) {
		var found = false;
		pills.forEach( function( pill ) {
			var on = pill.getAttribute( 'data-filter' ) === value;
			pill.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
			found = found || on;
		} );
		filter = found ? value : 'all';
		if ( ! found && pills.length ) {
			pills[ 0 ].setAttribute( 'aria-pressed', 'true' );
		}
		apply();
	}

	pills.forEach( function( pill ) {
		pill.addEventListener( 'click', function() {
			choose( pill.getAttribute( 'data-filter' ) );
		} );
	} );
	if ( search ) {
		search.addEventListener( 'input', apply );
	}

	var hash = ( window.location.hash || '' ).replace( '#', '' );
	if ( hash && /^[a-z0-9_-]+$/.test( hash ) ) {
		choose( hash );
	}
} )();
