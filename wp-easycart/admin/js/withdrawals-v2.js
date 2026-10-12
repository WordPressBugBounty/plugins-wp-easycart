/**
 * Settings › Checkout › Withdrawal button ( 6.0.4 ): copy the sentence for the store's terms, and attach or dismiss the
 * statements whose order number and email matched no order ( AJAX ecv2_withdrawal_attach / ecv2_withdrawal_dismiss ).
 *
 * Data: window.ecwd_vars ( ajax, nonce, text ). No jQuery.
 *
 * @since 6.0.4
 */
( function() {
	'use strict';

	var vars = window.ecwd_vars || {};
	var text = vars.text || {};

	function post( data, done ) {
		var body = new window.FormData();
		Object.keys( data ).forEach( function( key ) {
			body.append( key, data[ key ] );
		} );
		body.append( 'nonce', vars.nonce || '' );
		window.fetch( vars.ajax, { method: 'POST', body: body, credentials: 'same-origin' } ).then( function( response ) {
			return response.json();
		} ).then( function( answer ) {
			done( answer && answer.success ? null : ( ( answer && answer.data && answer.data.message ) || text.failed ), answer && answer.data ? answer.data : {} );
		} ).catch( function() {
			done( text.failed, {} );
		} );
	}

	function copy( button ) {
		var source = document.getElementById( button.getAttribute( 'data-ecwd-copy' ) );
		var note = button.parentNode ? button.parentNode.querySelector( '.ecwd-copied' ) : null;
		if ( ! source ) {
			return;
		}
		var shown = function() {
			if ( note ) {
				note.hidden = false;
				window.setTimeout( function() {
					note.hidden = true;
				}, 2000 );
			}
		};
		var select = function() {
			var range = document.createRange();
			range.selectNodeContents( source );
			var selection = window.getSelection();
			selection.removeAllRanges();
			selection.addRange( range );
		};
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( source.textContent ).then( shown, select );
		} else {
			select();
		}
	}

	function row_action( button, kind ) {
		var row = button.closest( '[data-ecwd-row]' );
		if ( ! row || button.disabled ) {
			return;
		}
		var message = row.querySelector( '.ecwd-msg' );
		var data = { action: 'attach' === kind ? 'ecv2_withdrawal_attach' : 'ecv2_withdrawal_dismiss', withdrawal_id: row.getAttribute( 'data-ecwd-row' ) };
		if ( 'attach' === kind ) {
			var order = row.querySelector( '.ecwd-order' );
			data.order_id = order ? order.value.replace( /[^0-9]/g, '' ) : '';
			if ( '' === data.order_id ) {
				message.textContent = text.no_order || '';
				if ( order ) {
					order.focus();
				}
				return;
			}
		}
		Array.prototype.forEach.call( row.querySelectorAll( 'button' ), function( b ) {
			b.disabled = true;
		} );
		post( data, function( error, answer ) {
			if ( error ) {
				message.textContent = error;
				Array.prototype.forEach.call( row.querySelectorAll( 'button' ), function( b ) {
					b.disabled = false;
				} );
				return;
			}
			message.textContent = answer.message || ( 'attach' === kind ? text.attached : text.dismissed );
			if ( answer.url ) {
				var link = document.createElement( 'a' );
				link.href = answer.url;
				link.textContent = answer.message;
				message.textContent = '';
				message.appendChild( link );
			}
			row.classList.add( 'is-done' );
		} );
	}

	document.addEventListener( 'click', function( event ) {
		var button = event.target.closest ? event.target.closest( '[data-ecwd-copy], [data-ecwd-attach], [data-ecwd-dismiss]' ) : null;
		if ( ! button ) {
			return;
		}
		event.preventDefault();
		if ( button.hasAttribute( 'data-ecwd-copy' ) ) {
			copy( button );
		} else if ( button.hasAttribute( 'data-ecwd-attach' ) ) {
			row_action( button, 'attach' );
		} else {
			row_action( button, 'dismiss' );
		}
	} );
}() );
