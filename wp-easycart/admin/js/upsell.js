/**
 * WP EasyCart — upsell popup controller.
 *
 * Entry points:
 *   ecdv2_upsell( { context: 'offers', feature: 'schedule' } )
 *   show_pro_required( 'usps' )     legacy name, still works
 *   hide_pro_required()
 *
 * Contexts/features come from wp_easycart_upsell_vars.entries ( PHP catalog ).
 * The popup markup is rendered once by load_upsell_popup(); this swaps content.
 */
( function( $ ) {
	'use strict';

	var V = window.wp_easycart_upsell_vars || { entries: {}, lang: {} };

	var ALIASES = {
		usps: 'shipping_usps', ups: 'shipping_ups', fedex: 'shipping_fedex', dhl: 'shipping_dhl',
		canada_post: 'shipping_canada_post', australia_post: 'shipping_australia_post',
		gift_cards: 'giftcards', giftcard: 'giftcards', abandoned: 'abandoned_cart',
		promotion: 'offers', promotions: 'offers', coupon: 'coupons', cart_links: 'cart_links'
	};

	function esc( s ) { return $( '<span>' ).text( s == null ? '' : s ).html(); }

	function resolve( key ) {
		key = String( key || '' ).replace( /-/g, '_' );
		if ( ALIASES[ key ] ) { key = ALIASES[ key ]; }
		return V.entries[ key ] ? key : '';
	}

	function apply( $panel, key, feature ) {
		var e = V.entries[ key ];
		if ( ! e ) { return; }
		$panel.attr( 'data-upsell-context', key );
		/* 6.0.1: only a newer WP EasyCart PRO is missing: the update, never the plans ( CSS hides them under .is-update ). */
		var fe = ( feature && e.features && e.features[ feature ] && e.features[ feature ].update_version ) ? e.features[ feature ] : null;
		var update = !! ( fe || e.update );
		$panel.toggleClass( 'is-update', update );
		$panel.find( '[data-upsell-update-text]' ).text( ( fe ? fe.update_text : e.update_text ) || '' );
		$panel.find( '[data-upsell-update-link]' ).attr( 'href', e.update_url || 'plugins.php' );
		$panel.find( '[data-upsell-plan]' ).text( update ? ( e.update_badge || 'Update' ) : ( e.badge || ( e.plan === 'premium' ? 'Premium' : 'Pro/Premium' ) ) );
		$panel.find( '[data-upsell-title]' ).text( e.headline || e.title );
		$panel.find( '[data-upsell-lede]' ).text( e.lede );
		$panel.find( '[data-upsell-stat]' ).toggle( !! e.stat_line ).find( '.ecv2-upsell-stat-text' ).text( e.stat_line || '' );

		var html = '';
		Object.keys( e.features || {} ).forEach( function( k ) {
			var f = e.features[ k ];
			html += '<div class="ecv2-upsell-feature' + ( k === feature ? ' is-highlight' : '' ) + '" data-feature="' + esc( k ) + '">';
			html += '<span class="dashicons ' + esc( f.icon ) + '"></span>';
			html += '<span class="ecv2-upsell-feature-text"><strong>' + esc( f.title ) + '</strong><span>' + esc( f.desc ) + '</span></span>';
			html += '</div>';
		} );
		$panel.find( '[data-upsell-features]' ).html( html );

		$panel.find( '[data-upsell-plan-card="pro"]' ).attr( 'href', e.pro_url ).toggleClass( 'is-recommended', e.plan !== 'premium' );
		$panel.find( '[data-upsell-plan-card="premium"]' ).attr( 'href', e.prem_url ).toggleClass( 'is-recommended', e.plan === 'premium' );
		$panel.find( '[data-upsell-docs]' )
			.attr( 'href', e.docs || 'https://www.wpeasycart.com/wordpress-shopping-cart-features/' )
			.text( ( e.docs ? ( V.lang.learn || 'How it works' ) : ( V.lang.full || 'Full feature list' ) ) + ' ↗' );
	}

	function open( key, feature ) {
		var $popup = $( '#ec_admin_upsell_popup' );
		if ( ! $popup.length ) {
			var e = V.entries[ key ] || V.entries[ 'default' ];
			if ( e && ( e.update || ( feature && e.features && e.features[ feature ] && e.features[ feature ].update_version ) ) ) { window.location.href = e.update_url; return; }
			if ( e ) { window.open( e.pro_url, '_blank' ); }
			return;
		}
		apply( $popup.find( '.ecv2-upsell' ), key, feature );
		$popup.stop( true, true ).css( 'display', 'flex' ).hide().fadeIn( 160 );
		$( 'body' ).addClass( 'ecv2-upsell-lock' );
		window.requestAnimationFrame( function() {
			var hl = $popup.find( '.is-highlight' )[ 0 ];
			if ( hl ) { hl.scrollIntoView( { block: 'nearest' } ); }
			$popup.find( '.ecv2-upsell-x' ).trigger( 'focus' );
		} );
	}

	function close() {
		$( '#ec_admin_upsell_popup' ).stop( true, true ).fadeOut( 140 );
		$( 'body' ).removeClass( 'ecv2-upsell-lock' );
	}

	/* Page context: the panel's server-rendered context, or the locked page's. */
	function page_context() {
		return $( '.ecv2-locked-page, .ecv2-cl-upsell' ).first().attr( 'data-upsell-context' ) ||
		       $( '#ec_admin_upsell_popup .ecv2-upsell' ).attr( 'data-upsell-context' ) || 'default';
	}

	window.ecdv2_upsell = function( opts ) {
		opts = opts || {};
		var key = resolve( opts.context || opts.source ) || page_context();
		var feature = opts.feature || '';
		/* A bare feature key that is itself a context ( legacy callers ). */
		if ( ! opts.context && feature && resolve( feature ) ) { key = resolve( feature ); feature = ''; }
		open( key, feature );
		return false;
	};

	window.show_pro_required = function( key, $toggle ) {
		if ( $toggle && $toggle.length ) { $toggle.prop( 'checked', false ); }
		open( resolve( key ) || page_context(), '' );
		return false;
	};
	window.hide_pro_required = close;

	$( document ).on( 'click', '#ec_admin_upsell_popup', function( e ) {
		if ( e.target === this ) { close(); }
	} );
	$( document ).on( 'keydown', function( e ) {
		if ( e.key === 'Escape' && $( '#ec_admin_upsell_popup' ).is( ':visible' ) ) { close(); }
	} );
	/* Locked preview blocks are focusable; Enter/Space opens. */
	$( document ).on( 'keydown', '.ecv2-locked-preview', function( e ) {
		if ( e.key === 'Enter' || e.key === ' ' ) { e.preventDefault(); $( this ).trigger( 'click' ); }
	} );

} )( jQuery );