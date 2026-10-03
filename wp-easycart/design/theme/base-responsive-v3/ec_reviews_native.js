/**
 * Native reviews — storefront behavior. Fold into the store JS bundle.
 *
 *  - ec_reviews_filter( product_id, rating ): click a distribution bar to show only that rating ( toggle ).
 *  - Review-request landing: open the reviews tab, scroll to the form, pre-select the star tapped in the email.
 *
 * Uses the same star elements the existing form binds ( .ec_details_review_input[data-review-score] ), so it
 * triggers the theme's own click handler rather than duplicating its state logic.
 */
function ec_reviews_filter( product_id, rating ) {
	var lists = document.querySelectorAll( '.ec_details_customer_review_list[data-product-id="' + product_id + '"]' );
	if ( ! lists.length ) { return false; }
	var summary = lists[0].closest( '.ec_details_customer_reviews_tab' ) || document;
	var active = summary.querySelector( '.ec_review_dist_row.is-active' );
	var same = active && parseInt( active.getAttribute( 'data-rating' ), 10 ) === rating;
	summary.querySelectorAll( '.ec_review_dist_row' ).forEach( function( r ) { r.classList.remove( 'is-active' ); } );
	lists.forEach( function( list ) {
		list.querySelectorAll( 'li[data-rating]' ).forEach( function( li ) {
			li.style.display = ( same || parseInt( li.getAttribute( 'data-rating' ), 10 ) === rating ) ? '' : 'none';
		} );
	} );
	if ( ! same ) {
		var row = summary.querySelector( '.ec_review_dist_row[data-rating="' + rating + '"]' );
		if ( row ) { row.classList.add( 'is-active' ); }
	}
	return false;
}

( function() {
	function ready( fn ) { if ( document.readyState !== 'loading' ) { fn(); } else { document.addEventListener( 'DOMContentLoaded', fn ); } }

	/*
	 * 6.0.2: open the reviews panel through the theme's own tab handler ( ec-store.js, .ec_details_tab click ). The product page's tab is
	 * <li class="ec_details_tab ec_details_tab_{pid}_{rand} ec_customer_reviews">; the other selectors are kept for custom tab markup.
	 * When no handler switched the panel ( ec-store.js delayed or stopped early ), show it the way that handler does.
	 */
	function open_reviews_tab( pid, rand ) {
		var panel = document.querySelector( '.ec_details_customer_reviews_tab_' + pid + '_' + rand );
		if ( ! panel || window.getComputedStyle( panel ).display !== 'none' ) { return; }
		var link = document.querySelector( '.ec_details_tab_' + pid + '_' + rand + '.ec_customer_reviews' ) || document.querySelector( '[onclick*="ec_show_review_tab"], .ec_details_tab_review_' + pid + '_' + rand + ', .ec_details_tab_reviews' );
		var tabs = ( link && link.parentNode && link.parentNode.classList && link.parentNode.classList.contains( 'ec_details_tabs' ) ) ? link.parentNode : null;
		var was_open = tabs ? tabs.classList.contains( 'ec_is_open' ) : false;
		if ( link ) { link.click(); }
		/* Every tab click toggles ec_is_open, which leaves the phone tab menu expanded; keep it as it was */
		if ( tabs && tabs.classList.contains( 'ec_is_open' ) !== was_open ) { tabs.classList.toggle( 'ec_is_open' ); }
		if ( window.getComputedStyle( panel ).display !== 'none' ) { return; }
		var area = document.querySelector( '.ec_details_extra_area_' + pid + '_' + rand );
		/* 6.0.2: an area an extension draws ( .ec_details_extra_area_custom ) opens its own panels; hiding its children hides it all. */
		if ( area && area.classList.contains( 'ec_details_extra_area_custom' ) ) {
			return;
		}
		if ( area ) {
			Array.prototype.forEach.call( area.children, function( el ) { if ( 'DIV' === el.tagName ) { el.style.display = 'none'; } } );
		}
		if ( tabs ) {
			document.querySelectorAll( '.ec_details_tab_' + pid + '_' + rand ).forEach( function( el ) { el.classList.toggle( 'ec_active', el === link ); } );
		}
		panel.style.display = 'block';
	}

	function land() {
		var banner = document.querySelector( '.ec_review_request_banner' );
		if ( ! banner ) { return; }
		var pid = banner.getAttribute( 'data-product-id' ), rand = banner.getAttribute( 'data-rand-id' ), rating = parseInt( banner.getAttribute( 'data-prefill-rating' ), 10 ) || 0;

		open_reviews_tab( pid, rand );

		/* Pre-select the star from the email */
		if ( rating >= 1 && rating <= 5 ) {
			var star = document.getElementById( 'ec_details_review_star' + rating + '_' + pid + '_' + rand );
			if ( star ) { star.click(); }
		}

		/* Scroll to the form */
		var form = banner.closest( '.ec_details_customer_reviews_form' );
		if ( form ) { setTimeout( function() { form.scrollIntoView( { behavior: 'smooth', block: 'start' } ); }, 150 ); }
	}

	/*
	 * ec-store.js binds the tab and star click handlers in jQuery ready, which jQuery 3 runs after DOMContentLoaded, so a plain
	 * DOMContentLoaded listener here ran first and its clicks did nothing. jQuery runs ready callbacks in the order they were added,
	 * and ec-store.js loads before this file, so this one lands after those handlers are bound.
	 */
	if ( window.jQuery ) {
		window.jQuery( function() { setTimeout( land, 0 ); } );
	} else {
		ready( function() { setTimeout( land, 0 ); } );
	}
} )();

/* 6.0.0: the distribution bars are plain links; wire them to ec_reviews_filter() ( delegated, so AJAX-refreshed lists keep working ). */
( function() {
	document.addEventListener( 'click', function( e ) {
		var row = e.target && e.target.closest ? e.target.closest( '.ec_review_dist_row' ) : null;
		if ( ! row ) { return; }
		e.preventDefault();
		var tab  = row.closest( '.ec_details_customer_reviews_tab' );
		var list = ( tab || document ).querySelector( '.ec_details_customer_review_list' );
		if ( ! list ) { return; }
		ec_reviews_filter( list.getAttribute( 'data-product-id' ), parseInt( row.getAttribute( 'data-rating' ), 10 ) );
	} );
} )();
