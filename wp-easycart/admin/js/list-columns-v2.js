/**
 * Columns chooser on the V2 admin lists ( 6.0.2 ).
 *
 * Drives the toolbar panel printed by wp_easycart_admin_table_v2::print_columns_control(): tick the columns to show,
 * move them up or down ( buttons, or Alt + arrow keys ), then Save ( my columns ), Reset to default ( drop mine ) or,
 * for administrators, Use as the default for everyone / clear it. Each saves through ecv2_list_columns_save and the
 * page reloads, so the header and every cell come from the server. Closing the panel without saving puts it back.
 */
( function( $ ) {
	'use strict';

	/* Parameters that ran an action or showed a one-time notice; the reload after a save leaves them out. */
	var ONE_SHOT = [ 'ec_admin_form_action', 'wp_easycart_nonce', 'bulk', 'bulk[]', 'success', 'error', 'undo', 'trash', 'restored' ];

	function parse( value, fallback ) {
		try {
			var parsed = JSON.parse( value );
			return parsed || fallback;
		} catch ( e ) {
			return fallback;
		}
	}

	function reload_url() {
		var url;
		try {
			url = new URL( window.location.href );
		} catch ( e ) {
			return window.location.href.split( '#' )[ 0 ];
		}
		ONE_SHOT.forEach( function( name ) { url.searchParams.delete( name ); } );
		url.hash = '';
		return url.toString();
	}

	function Chooser( root ) {
		var $root   = $( root );
		var $btn    = $root.find( '.ecv2-cols-btn' );
		var $panel  = $root.find( '.ecv2-cols-panel' );
		var $list   = $panel.find( '.ecv2-cols-list' );
		var $status = $panel.find( '.ecv2-cols-status' );
		var text    = parse( $panel.attr( 'data-text' ), {} );
		var keys    = parse( $panel.attr( 'data-keys' ), [] );
		var locked  = parse( $panel.attr( 'data-locked' ), [] );
		var snapshot = null;
		var busy     = false;
		var holding  = false;

		function items() {
			return $list.children( '.ecv2-cols-item' );
		}

		function is_open() {
			return ! $panel.prop( 'hidden' );
		}

		function say( message, is_error ) {
			$status.text( message || '' ).toggleClass( 'is-error', !! is_error );
		}

		function take() {
			return items().map( function() {
				return { key: $( this ).attr( 'data-key' ), on: $( this ).find( 'input[type="checkbox"]' ).prop( 'checked' ) };
			} ).get();
		}

		function refresh() {
			var $all = items(), last = $all.length - 1;
			$all.each( function( i ) {
				$( this ).find( '.ecv2-cols-up' ).prop( 'disabled', 0 === i );
				$( this ).find( '.ecv2-cols-down' ).prop( 'disabled', last === i );
			} );
		}

		function restore( state ) {
			if ( ! state ) {
				return;
			}
			state.forEach( function( row ) {
				var $item = items().filter( function() { return $( this ).attr( 'data-key' ) === row.key; } );
				$item.find( 'input[type="checkbox"]:not(:disabled)' ).prop( 'checked', row.on );
				$list.append( $item );
			} );
			refresh();
		}

		function outside( e ) {
			if ( ! $.contains( root, e.target ) ) {
				close( false );
			}
		}

		function open() {
			if ( is_open() ) {
				return;
			}
			snapshot = take();
			say( '' );
			$panel.prop( 'hidden', false );
			$btn.attr( 'aria-expanded', 'true' );
			$root.addClass( 'is-open' );
			var $first = $panel.find( 'input[type="checkbox"]:not(:disabled)' ).first();
			( $first.length ? $first : $panel.find( 'button:not(:disabled)' ).first() ).trigger( 'focus' );
			$( document ).on( 'mousedown.ecv2cols touchstart.ecv2cols', outside );
		}

		function close( focus_button ) {
			if ( ! is_open() || busy ) {
				return;
			}
			restore( snapshot );
			snapshot = null;
			$panel.prop( 'hidden', true );
			$btn.attr( 'aria-expanded', 'false' );
			$root.removeClass( 'is-open' );
			$( document ).off( '.ecv2cols' );
			if ( focus_button ) {
				$btn.trigger( 'focus' );
			}
		}

		function move( $item, step, $focus ) {
			var $to = step < 0 ? $item.prevAll( '.ecv2-cols-item' ).first() : $item.nextAll( '.ecv2-cols-item' ).first();
			if ( ! $to.length ) {
				return;
			}
			holding = true;
			if ( step < 0 ) {
				$to.before( $item );
			} else {
				$to.after( $item );
			}
			refresh();
			/* Focus stays on the control used, unless that just became disabled at the end of the list. */
			if ( ! $focus || ! $focus.length || $focus.prop( 'disabled' ) ) {
				$focus = $item.find( step < 0 ? '.ecv2-cols-down' : '.ecv2-cols-up' );
				if ( $focus.prop( 'disabled' ) ) {
					$focus = $item.find( 'input[type="checkbox"]' );
				}
			}
			$focus.trigger( 'focus' );
			holding = false;
			var position = items().index( $item ) + 1;
			say( String( text.moved || '%1$s moved to position %2$d of %3$d.' )
				.replace( '%1$s', $item.find( '.ecv2-cols-name' ).text() )
				.replace( '%2$d', position )
				.replace( '%3$d', items().length ) );
		}

		function save( op, $trigger ) {
			if ( busy ) {
				return;
			}
			var data = {
				action: 'ecv2_list_columns_save',
				nonce: $panel.attr( 'data-nonce' ),
				table_id: $panel.attr( 'data-table-id' ),
				sig: $panel.attr( 'data-sig' ),
				op: op,
				keys: keys,
				locked: locked
			};
			if ( 'user' === op || 'store' === op ) {
				data.order = [];
				data.hidden = [];
				items().each( function() {
					var key = $( this ).attr( 'data-key' );
					data.order.push( key );
					if ( ! $( this ).find( 'input[type="checkbox"]' ).prop( 'checked' ) && -1 === locked.indexOf( key ) ) {
						data.hidden.push( key );
					}
				} );
			}
			busy = true;
			$root.addClass( 'is-busy' );
			$panel.find( 'button, input' ).prop( 'disabled', true );
			$trigger.attr( 'aria-busy', 'true' );
			say( text.saving || 'Saving…' );
			$.post( $panel.attr( 'data-ajax-url' ) || window.ajaxurl, data ).done( function( response ) {
				if ( response && response.success ) {
					var url = reload_url();
					if ( url === window.location.href.split( '#' )[ 0 ] ) {
						window.location.reload();
					} else {
						window.location.href = url;
					}
					return;
				}
				failed( response && response.data && response.data.message );
			} ).fail( function() {
				failed( '' );
			} );

			function failed( message ) {
				busy = false;
				$root.removeClass( 'is-busy' );
				$trigger.removeAttr( 'aria-busy' );
				$panel.find( 'button, input' ).prop( 'disabled', false );
				$panel.find( '.ecv2-cols-item.is-locked input[type="checkbox"]' ).prop( 'disabled', true );
				$panel.find( '[data-cols-op="reset"]' ).prop( 'disabled', 'true' === $panel.attr( 'data-reset-off' ) );
				refresh();
				say( message || text.error || 'Your columns could not be saved. Try again.', true );
				$trigger.trigger( 'focus' );
			}
		}

		$panel.attr( 'data-reset-off', $panel.find( '[data-cols-op="reset"]' ).prop( 'disabled' ) ? 'true' : 'false' );

		$btn.on( 'click', function( e ) {
			e.preventDefault();
			if ( is_open() ) {
				close( true );
			} else {
				open();
			}
		} );

		$panel.on( 'click', '.ecv2-cols-up, .ecv2-cols-down', function( e ) {
			e.preventDefault();
			move( $( this ).closest( '.ecv2-cols-item' ), $( this ).hasClass( 'ecv2-cols-up' ) ? -1 : 1, $( this ) );
		} );

		$panel.on( 'click', '[data-cols-cancel]', function( e ) {
			e.preventDefault();
			close( true );
		} );

		$panel.on( 'click', '[data-cols-op]', function( e ) {
			e.preventDefault();
			save( $( this ).attr( 'data-cols-op' ), $( this ) );
		} );

		$root.on( 'keydown', function( e ) {
			if ( ! is_open() ) {
				return;
			}
			if ( 'Escape' === e.key ) {
				/* Handled here: the list pages also clear the row selection on Escape. */
				e.preventDefault();
				e.stopPropagation();
				close( true );
				return;
			}
			var $item = $( e.target ).closest( '.ecv2-cols-item' );
			if ( $item.length && e.altKey && ( 'ArrowUp' === e.key || 'ArrowDown' === e.key ) ) {
				e.preventDefault();
				move( $item, 'ArrowUp' === e.key ? -1 : 1, $( e.target ) );
				return;
			}
			/* The toolbar sits in the list's search form: Enter on a box ticks it instead of sending the form. */
			if ( 'Enter' === e.key && $( e.target ).is( 'input[type="checkbox"]' ) ) {
				e.preventDefault();
				if ( ! $( e.target ).prop( 'disabled' ) ) {
					$( e.target ).prop( 'checked', ! $( e.target ).prop( 'checked' ) );
				}
			}
		} );

		/* Tabbing out of the panel closes it, as a click elsewhere does. */
		$root.on( 'focusout', function() {
			setTimeout( function() {
				if ( is_open() && ! holding && ! busy && document.activeElement && document.activeElement !== document.body && ! $.contains( root, document.activeElement ) ) {
					close( false );
				}
			}, 0 );
		} );
	}

	$( function() {
		$( '[data-ecv2-cols]' ).each( function() {
			if ( ! $( this ).data( 'ecv2ColsReady' ) ) {
				$( this ).data( 'ecv2ColsReady', 1 );
				new Chooser( this ); // eslint-disable-line no-new
			}
		} );
	} );
} )( jQuery );
