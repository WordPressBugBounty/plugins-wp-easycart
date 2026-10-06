/**
 * Carrier fields on the order screens ( WP EasyCart 6.0.3 ).
 *
 * Every carrier <select data-wpec-carrier> ( the order screen's Ship form and Fulfill window, the orders list's Ship popover )
 * lists wp_easycart_carriers' carriers and ends with Other…. Other… puts a field for the carrier's name in place of the list.
 * What is typed becomes the select's own value ( an option of its own ), so every script that reads the select sends it as the
 * carrier. window.wpecCarrierSet( select, name ) selects a carrier, adding it when the list does not have it.
 * Wording: window.wpec_carriers_text ( name, list ), printed with the script.
 */
( function ( $ ) {
	'use strict';
	if ( window.wpecCarrierSet ) {
		return;
	}
	var OTHER = '__other__';
	var TEXT  = window.wpec_carriers_text || { name: 'Carrier name', list: 'Choose from the list' };

	/* Shown to the person: not hidden, not inside something hidden ( no layout needed ). */
	function shown( el ) {
		for ( var node = el; node && 1 === node.nodeType; node = node.parentNode ) {
			if ( node.hidden || 'none' === node.style.display || 'hidden' === node.type ) {
				return false;
			}
		}
		return ! el.disabled;
	}

	/* The carrier chosen before Other… ( kept on each change ), else the one the page started on, else the first. */
	function previous( $sel ) {
		var last = String( $sel.data( 'wpecLast' ) || '' );
		var $opt = realOptions( $sel ).filter( function () {
			return '' !== last ? this.value === last : this.defaultSelected;
		} ).first();
		return $opt.length ? $opt : realOptions( $sel ).first();
	}

	function realOptions( $sel ) {
		return $sel.find( 'option' ).filter( function () {
			return OTHER !== this.value && ! this.hasAttribute( 'data-wpec-custom' );
		} );
	}

	function customOption( $sel ) {
		var $opt = $sel.find( 'option[data-wpec-custom]' );
		if ( ! $opt.length ) {
			$opt = $( '<option data-wpec-custom="1"></option>' );
			var $other = $sel.find( 'option[value="' + OTHER + '"]' );
			if ( $other.length ) {
				$other.before( $opt );
			} else {
				$sel.append( $opt );
			}
		}
		return $opt;
	}

	function wrapFor( $sel ) {
		var $wrap = $sel.next( '.wpec-carrier-other' );
		if ( $wrap.length ) {
			return $wrap;
		}
		$wrap = $( '<span class="wpec-carrier-other" hidden><input type="text" class="wpec-carrier-other-input" autocomplete="off" spellcheck="false" maxlength="60" /><button type="button" class="wpec-carrier-other-back"><span aria-hidden="true">&times;</span></button></span>' );
		$wrap.find( 'input' ).attr( { placeholder: TEXT.name, 'aria-label': TEXT.name } );
		$wrap.find( 'button' ).attr( { 'aria-label': TEXT.list, title: TEXT.list } );
		/* Keys stay in the field: Enter goes on to the next field ( it never ships the order ), Escape goes back to the list. */
		$wrap.find( 'input' ).on( 'keydown', function ( e ) {
			if ( 'Escape' === e.key ) {
				e.preventDefault();
				e.stopPropagation();
				showList( $sel, true );
			} else if ( 'Enter' === e.key ) {
				e.preventDefault();
				e.stopPropagation();
				var $fields = $( 'input[type="text"], textarea, select' ).filter( function () {
					return shown( this );
				} );
				var at      = $fields.index( this );
				if ( at > -1 && $fields.eq( at + 1 ).length ) {
					$fields.eq( at + 1 ).trigger( 'focus' );
				}
			}
		} );
		$wrap.insertAfter( $sel );
		return $wrap;
	}

	function showList( $sel, focus ) {
		var $wrap = $sel.next( '.wpec-carrier-other' );
		if ( $wrap.length ) {
			$wrap.prop( 'hidden', true );
		}
		$sel.removeClass( 'wpec-carrier-is-other' ).show();
		$sel.find( 'option[data-wpec-custom]' ).remove();
		if ( '' === String( $sel.val() || '' ) || OTHER === $sel.val() ) {
			previous( $sel ).prop( 'selected', true );
		}
		if ( focus ) {
			$sel.trigger( 'focus' );
		}
	}

	function showOther( $sel, value ) {
		var $wrap = wrapFor( $sel );
		customOption( $sel ).val( value ).text( value ).prop( 'selected', true );
		$sel.addClass( 'wpec-carrier-is-other' ).hide();
		$wrap.prop( 'hidden', false ).find( 'input' ).val( value ).trigger( 'focus' );
	}

	/**
	 * Select a carrier ( any case ), adding it to the list when it is not there; '' leaves the list's first carrier.
	 *
	 * @param {Element|jQuery} sel   A carrier select.
	 * @param {string}         value Carrier name.
	 */
	window.wpecCarrierSet = function ( sel, value ) {
		var $sel = $( sel );
		value    = $.trim( String( value || '' ) );
		if ( ! $sel.length ) {
			return;
		}
		if ( '' === value || OTHER === value ) {
			showList( $sel, false );
			return;
		}
		var lower  = value.toLowerCase();
		var $match = realOptions( $sel ).filter( function () {
			return String( this.value ).toLowerCase() === lower;
		} ).first();
		if ( ! $match.length ) {
			$match = $( '<option></option>' ).val( value ).text( value );
			var $first = $sel.children( 'optgroup, option' ).first();
			if ( $first.length ) {
				$first.before( $match );
			} else {
				$sel.append( $match );
			}
		}
		$match.prop( 'selected', true );
		$sel.data( 'wpecLast', $match.val() );
		showList( $sel, false );
	};

	$( document ).on( 'change', 'select[data-wpec-carrier]', function () {
		var $sel = $( this );
		if ( OTHER === $sel.val() ) {
			showOther( $sel, '' );
		} else if ( ! $sel.find( 'option:selected' ).is( '[data-wpec-custom]' ) ) {
			$sel.data( 'wpecLast', $sel.val() );
			showList( $sel, false );
		}
	} );

	$( document ).on( 'input', '.wpec-carrier-other-input', function () {
		var $sel  = $( this ).closest( '.wpec-carrier-other' ).prev( 'select[data-wpec-carrier]' );
		var value = $.trim( this.value );
		customOption( $sel ).val( value ).text( value ).prop( 'selected', true );
		$sel.trigger( 'wpec-carrier-typed', [ value ] );
	} );

	$( document ).on( 'click', '.wpec-carrier-other-back', function ( e ) {
		e.preventDefault();
		var $sel  = $( this ).closest( '.wpec-carrier-other' ).prev( 'select[data-wpec-carrier]' );
		var value = $.trim( $( this ).siblings( 'input' ).val() || '' );
		if ( '' !== value ) {
			/* The name typed stays chosen, now as a line in the list. */
			window.wpecCarrierSet( $sel, value );
			$sel.trigger( 'focus' );
		} else {
			showList( $sel, true );
		}
	} );
}( jQuery ) );
