/**
 * WP EasyCart account widgets for Elementor ( 6.0.2 ).
 *
 * - The account menu's phone dropdown opens the page chosen ( delegated, so it works in popups and after the editor redraws
 *   a widget ).
 * - Address forms: ec-store.js binds the country / state lists on page load. When Elementor draws a widget later ( the
 *   editor, a popup ), the same country switch is bound here for that widget.
 * - 6.0.3: a form sent from an Elementor Pro popup comes back to a page where the popup is closed, and so does a link such as
 *   the password reset email. The widget that answers the request ( it printed the message, or shows the view the link asked
 *   for ) carries data-wpec-acc-answer; when every such widget is in a popup, that popup opens on load.
 */
( function ( $ ) {
	'use strict';

	var widgets = [
		'wp_easycart_my_account',
		'wp_easycart_my_account_addresses',
		'wp_easycart_my_account_register'
	];

	$( document ).on( 'change', '.wpec-acc-nav__select', function () {
		var url = $( this ).val();
		if ( url ) {
			window.location.href = url;
		}
	} );

	function countryUpdate() {
		if ( typeof window.wpeasycart_account_billing_country_update === 'function' ) {
			window.wpeasycart_account_billing_country_update();
		}
		if ( typeof window.wpeasycart_account_shipping_country_update === 'function' ) {
			window.wpeasycart_account_shipping_country_update();
		}
	}

	function initWidget( $scope ) {
		if ( ! $scope || ! $scope.find ) {
			return;
		}
		var $countries = $scope.find( '#ec_account_billing_information_country, #ec_account_shipping_information_country' );
		if ( ! $countries.length ) {
			return;
		}
		$countries.off( 'change.wpecAccount' ).on( 'change.wpecAccount', countryUpdate );
		countryUpdate();
	}

	var hooked = false;
	function hook() {
		if ( hooked || ! window.elementorFrontend || ! window.elementorFrontend.hooks ) {
			return;
		}
		hooked = true;
		$.each( widgets, function ( index, name ) {
			window.elementorFrontend.hooks.addAction( 'frontend/element_ready/' + name + '.default', initWidget );
		} );
	}
	$( window ).on( 'elementor/frontend/init', hook );
	hook();

	/* The popup that holds the answer to this request, or 0: none, or one is already on the page outside a popup. */
	function answerPopup() {
		var answers = document.querySelectorAll( '[data-wpec-acc-answer]' );
		var popup   = 0;
		for ( var i = 0; i < answers.length; i++ ) {
			var holder = answers[ i ].closest( '[data-elementor-type="popup"]' );
			if ( ! holder ) {
				return 0;
			}
			if ( ! popup ) {
				popup = parseInt( holder.getAttribute( 'data-elementor-id' ), 10 ) || 0;
			}
		}
		return popup;
	}

	var answerOpened = false;
	function openAnswerPopup( tries ) {
		if ( answerOpened || ( window.elementorFrontend && typeof window.elementorFrontend.isEditMode === 'function' && window.elementorFrontend.isEditMode() ) ) {
			return;
		}
		var id = answerPopup();
		if ( ! id ) {
			return;
		}
		var popups = window.elementorProFrontend && window.elementorProFrontend.modules ? window.elementorProFrontend.modules.popup : null;
		if ( popups && typeof popups.showPopup === 'function' ) {
			answerOpened = true;
			popups.showPopup( { id: id } );
			return;
		}
		if ( tries < 40 ) {
			/* Elementor Pro sets its popups up after its own scripts load. */
			window.setTimeout( function () {
				openAnswerPopup( tries + 1 );
			}, 250 );
		}
	}
	var answerStarted = false;
	$( window ).on( 'load', function () {
		if ( ! answerStarted ) {
			answerStarted = true;
			openAnswerPopup( 0 );
		}
	} );
}( jQuery ) );
