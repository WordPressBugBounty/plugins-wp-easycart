/**
 * WP EasyCart account widgets for Elementor ( 6.0.2 ).
 *
 * - The account menu's phone dropdown opens the page chosen ( delegated, so it works in popups and after the editor redraws
 *   a widget ).
 * - Address forms: ec-store.js binds the country / state lists on page load. When Elementor draws a widget later ( the
 *   editor, a popup ), the same country switch is bound here for that widget.
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
}( jQuery ) );
