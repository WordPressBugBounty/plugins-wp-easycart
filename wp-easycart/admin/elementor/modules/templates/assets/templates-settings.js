/**
 * Settings › Elementor › Product pages / Category pages ( 6.0.2 ): the Edit link, status and Theme Builder warning follow
 * the template chosen in the select ( which autosaves ).
 *
 * @package Wp_Easycart_Elementor
 */
( function( $ ) {
	'use strict';

	function sync( $tools ) {
		var select = document.getElementById( $tools.attr( 'data-wpec-elt-select' ) );
		if ( ! select ) {
			return;
		}
		var value = String( select.value );
		$tools.find( '[data-wpec-elt-template]' ).each( function() {
			this.hidden = String( this.getAttribute( 'data-wpec-elt-template' ) ) !== value;
		} );
		$tools.find( '.wpec-elt-conflict' ).each( function() {
			this.hidden = ( '0' === value || '' === value );
		} );
	}

	$( function() {
		$( '.wpec-elt-tools' ).each( function() {
			var $tools = $( this );
			sync( $tools );
			$( document ).on( 'change', '#' + $tools.attr( 'data-wpec-elt-select' ), function() {
				sync( $tools );
			} );
		} );
	} );
}( jQuery ) );
