/* WP EasyCart Admin — checks that run after the page shows ( 6.0.5, admin review M15; wp_easycart_admin_deferred_checks ).
 * Each [data-ecv2-deferred] placeholder is run through AJAX ecv2_deferred_check, one after another ( a single-worker server
 * answers one request at a time ), and replaced by the answer. */
( function() {
	'use strict';
	var C = window.wpecDeferred || {};
	function next() {
		var row = document.querySelector( '[data-ecv2-deferred]:not([data-ecv2-deferred-busy])' );
		if ( ! row || ! C.ajax ) { return; }
		row.setAttribute( 'data-ecv2-deferred-busy', '1' );
		var body = new window.FormData();
		body.append( 'action', 'ecv2_deferred_check' );
		body.append( 'check', row.getAttribute( 'data-ecv2-deferred' ) );
		body.append( 'nonce', C.nonce || '' );
		window.fetch( C.ajax, { method: 'POST', credentials: 'same-origin', body: body } ).then( function( r ) {
			return r.json();
		} ).then( function( json ) {
			if ( ! json || ! json.success ) { throw new Error( 'failed' ); }
			var holder = document.createElement( 'div' );
			holder.innerHTML = json.data && json.data.html ? json.data.html : '';
			var parent = row.parentNode;
			while ( holder.firstChild ) { parent.insertBefore( holder.firstChild, row ); }
			parent.removeChild( row );
			document.dispatchEvent( new window.CustomEvent( 'wpec:deferred-check', { detail: { check: row.getAttribute( 'data-ecv2-deferred' ) } } ) );
		} ).catch( function() {
			row.removeAttribute( 'data-ecv2-deferred' );
			row.classList.add( 'is-failed' );
			var text = row.querySelector( '.ecv2-deferred-text' );
			if ( text ) { text.textContent = C.failed || 'This check did not finish. Reload the page to try again.'; }
		} ).then( next );
	}
	if ( 'loading' === document.readyState ) { document.addEventListener( 'DOMContentLoaded', next ); } else { next(); }
} )();
