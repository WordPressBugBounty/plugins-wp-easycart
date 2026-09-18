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
	ready( function() {
		var banner = document.querySelector( '.ec_review_request_banner' );
		if ( ! banner ) { return; }
		var pid = banner.getAttribute( 'data-product-id' ), rand = banner.getAttribute( 'data-rand-id' ), rating = parseInt( banner.getAttribute( 'data-prefill-rating' ), 10 ) || 0;

		/* Open the reviews tab if the theme uses tabs ( the tab link carries the product id + rand in its class ) */
		var tab = document.querySelector( '.ec_details_customer_reviews_tab_' + pid + '_' + rand );
		var tabLink = document.querySelector( '[onclick*="ec_show_review_tab"], .ec_details_tab_review_' + pid + '_' + rand + ', .ec_details_tab_reviews' );
		if ( tab && tabLink && window.getComputedStyle( tab ).display === 'none' ) { tabLink.click(); }

		/* Pre-select the star from the email */
		if ( rating >= 1 && rating <= 5 ) {
			var star = document.getElementById( 'ec_details_review_star' + rating + '_' + pid + '_' + rand );
			if ( star ) { setTimeout( function() { star.click(); }, 50 ); }
		}

		/* Scroll to the form */
		var form = banner.closest( '.ec_details_customer_reviews_form' );
		if ( form ) { setTimeout( function() { form.scrollIntoView( { behavior: 'smooth', block: 'start' } ); }, 150 ); }
	} );
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
