/**
 * WP EasyCart Elementor: product information widgets ( module product-info, 6.0.2 ).
 *
 * - Product Tabs: the ARIA tabs pattern ( click, arrow keys, Home, End, Enter, Space ), an accordion where the Layout control
 *   asks for one ( the widget's CSS sets --wpec-pi-tabs-mode per device; this reads it and follows resizes ), tabs added by
 *   extensions, #tab-<slug> links. Fires the product page's jQuery event wpeasycart_tab_selected on the tab it opens.
 * - Product Reviews: the star picker ( radios; the classes ec_submit_product_review() in ec-store.js counts ), the form sent
 *   through ec_submit_product_review(), the filter by stars, the review-request landing ( pre-filled star, scroll ).
 * - Product Rating: its link opens the reviews on the page ( this module's, the product page's tabs, the older widget's ).
 * - Share Buttons: Copy link. Product Meta: the SKU follows wpeasycart_product_state.
 * - Round 11: tab icons and the merchant's own accordion icons copied into the accordion rows, "start all closed" and "one
 *   open at a time", reviews paged behind "Show more reviews", the Read more button of the description widgets, the Print
 *   and device share menu buttons, the Meta stock line following the chosen option, and the tab area WP EasyCart Tabs draws
 *   for the Product Tabs widget ( window.wpecTabsInit / window.wpecTabs.open ).
 *
 * Everything is delegated or ( re )bound per widget through Elementor's frontend/element_ready hook, so it works in the editor,
 * popups and loops.
 */
( function( $ ) {
	'use strict';

	if ( ! $ ) {
		return;
	}

	var reduced = !! ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches );
	var landed = false;

	function scrollToElement( el ) {
		if ( el && el.scrollIntoView ) {
			el.scrollIntoView( { behavior: reduced ? 'auto' : 'smooth', block: 'start' } );
		}
	}

	function focusElement( el ) {
		if ( ! el ) {
			return;
		}
		if ( ! el.hasAttribute( 'tabindex' ) && ! /^(A|BUTTON|INPUT|SELECT|TEXTAREA)$/.test( el.tagName ) ) {
			el.setAttribute( 'tabindex', '-1' );
		}
		try {
			el.focus( { preventScroll: true } );
		} catch ( e ) {
			el.focus();
		}
	}

	/* ------------------------------------------------------------------ */
	/* Product Tabs                                                        */
	/* ------------------------------------------------------------------ */

	function tabsMode( root ) {
		var mode = '';
		try {
			mode = window.getComputedStyle( root ).getPropertyValue( '--wpec-pi-tabs-mode' );
		} catch ( e ) {
			mode = '';
		}
		return 'accordion' === String( mode ).trim() ? 'accordion' : 'tabs';
	}

	function tabsList( root ) {
		return $( root ).children( '.wpec-pi-tabs__list' );
	}

	function tabsOf( root ) {
		return tabsList( root ).children( 'li' );
	}

	function visibleTabs( root ) {
		return tabsOf( root ).filter( function() {
			return ! this.hasAttribute( 'hidden' );
		} );
	}

	/* A tab's panel: its aria-controls inside this widget, else an extension panel ( .ec_details_<id>_tab ). */
	function findPanel( root, tab ) {
		var id = tab.getAttribute( 'aria-controls' ), panel = id ? document.getElementById( id ) : null, key;
		if ( panel && $( panel ).closest( '.wpec-pi-tabs' )[0] === root ) {
			return panel;
		}
		key = tab.getAttribute( 'data-tab-id' );
		if ( ! key ) {
			return null;
		}
		return $( root ).children( '.wpec-pi-tabs__panels' ).children( 'div' ).filter( function() {
			return this.classList && this.classList.contains( 'ec_details_' + key + '_tab' );
		} ).get( 0 ) || null;
	}

	function setOpen( panel, button, open ) {
		$( panel ).toggleClass( 'is-open', !! open );
		if ( button ) {
			button.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		}
	}

	function markActive( root, tab ) {
		tabsOf( root ).each( function() {
			var on = ( this === tab );
			$( this ).toggleClass( 'is-active', on ).attr( {
				'aria-selected': on ? 'true' : 'false',
				tabindex: on ? '0' : '-1'
			} );
		} );
		$( tab ).trigger( 'wpeasycart_tab_selected' );
	}

	/* Tabs mode: open one tab, close the rest. */
	function selectTab( root, tab, focus ) {
		if ( ! tab ) {
			return;
		}
		tabsOf( root ).each( function() {
			var panel = $( this ).data( 'wpecPiPanel' );
			if ( panel ) {
				setOpen( panel, $( this ).data( 'wpecPiAcc' ), this === tab );
			}
		} );
		markActive( root, tab );
		if ( focus ) {
			tab.focus();
		}
	}

	function applyMode( root, force ) {
		var mode = tabsMode( root ), tabs = visibleTabs( root ), active, list = tabsList( root ).get( 0 );
		if ( ! force && mode === root.wpecPiMode ) {
			return;
		}
		root.wpecPiMode = mode;
		active = tabs.filter( '.is-active' ).get( 0 ) || tabs.get( 0 );
		if ( list ) {
			var direction = '';
			try {
				direction = window.getComputedStyle( list ).flexDirection;
			} catch ( e ) {
				direction = '';
			}
			list.setAttribute( 'aria-orientation', 'column' === direction ? 'vertical' : 'horizontal' );
		}
		tabs.each( function() {
			var panel = $( this ).data( 'wpecPiPanel' ), button = $( this ).data( 'wpecPiAcc' );
			if ( ! panel ) {
				return;
			}
			if ( 'accordion' === mode ) {
				panel.setAttribute( 'role', 'region' );
				panel.setAttribute( 'aria-labelledby', button.id );
			} else {
				panel.setAttribute( 'role', 'tabpanel' );
				panel.setAttribute( 'aria-labelledby', this.id );
			}
		} );
		if ( active ) {
			/* Switching layouts leaves the tab that was open as the one open. */
			selectTab( root, active, false );
		}
	}

	/* An accordion row's button: the tab's icon, its name, and the merchant's own open / closed icons when there are any. */
	function accordionButton( root, tab, panel ) {
		var button = document.createElement( 'button' ), label = document.createElement( 'span' ), icon = $( tab ).children( '.wpec-pi-tabs__icon' ).get( 0 ), labelEl = $( tab ).children( '.wpec-pi-tabs__label' ).get( 0 ), icons = $( root ).children( '.wpec-pi-tabs__acc-icons' ).get( 0 ), marker;
		button.type = 'button';
		button.className = 'wpec-pi-tabs__acc';
		button.id = tab.id + '-acc';
		button.setAttribute( 'aria-controls', panel.id );
		button.setAttribute( 'aria-expanded', 'false' );
		if ( icon ) {
			button.appendChild( icon.cloneNode( true ) );
		}
		label.className = 'wpec-pi-tabs__acc-label';
		label.textContent = String( $( labelEl || tab ).text() ).trim();
		button.appendChild( label );
		if ( icons ) {
			marker = document.createElement( 'span' );
			marker.className = 'wpec-pi-tabs__acc-marker';
			marker.setAttribute( 'aria-hidden', 'true' );
			$( icons ).children().each( function() {
				marker.appendChild( this.cloneNode( true ) );
			} );
			button.appendChild( marker );
		}
		return button;
	}

	function initTabs( root ) {
		var tabs, active = null, base;
		if ( ! root || root.getAttribute( 'data-wpec-pi-ready' ) ) {
			return;
		}
		root.setAttribute( 'data-wpec-pi-ready', '1' );
		base = root.id || ( 'wpec-pi-tabs-' + Math.floor( Math.random() * 1000000 ) );
		tabs = tabsOf( root );
		tabs.each( function( index ) {
			var tab = this, panel = findPanel( root, tab ), button;
			if ( ! panel ) {
				tab.setAttribute( 'hidden', 'hidden' );
				return;
			}
			if ( ! tab.id ) {
				tab.id = base + '-tab-x' + index;
			}
			if ( ! panel.id ) {
				panel.id = base + '-panel-x' + index;
			}
			$( tab ).addClass( 'wpec-pi-tabs__tab' ).attr( { role: 'tab', 'aria-controls': panel.id } );
			/* Extension panels arrive with an inline display:none. */
			panel.style.display = '';
			$( panel ).addClass( 'wpec-pi-tabs__panel' ).attr( 'tabindex', '0' );
			button = accordionButton( root, tab, panel );
			panel.parentNode.insertBefore( button, panel );
			$( button ).data( 'wpecPiTab', tab );
			$( tab ).data( 'wpecPiAcc', button ).data( 'wpecPiPanel', panel );
			if ( ! active && $( tab ).hasClass( 'is-active' ) ) {
				active = tab;
			}
		} );
		/* An extension tab set to open first ( ec_active, WP EasyCart Tabs ): the widget left all of its own tabs closed. */
		if ( ! active || $( root ).hasClass( 'wpec-pi-tabs--ext-first' ) ) {
			active = visibleTabs( root ).filter( '.ec_active' ).get( 0 ) || active || visibleTabs( root ).get( 0 );
		}
		tabs.removeClass( 'ec_active is-active' );
		if ( active ) {
			$( active ).addClass( 'is-active' );
		}
		applyMode( root, true );
		/* "When the page opens: All are closed" ( accordion only; tabs always show one ). */
		if ( 'accordion' === root.wpecPiMode && 'closed' === root.getAttribute( 'data-acc-start' ) ) {
			tabsOf( root ).each( function() {
				var panel = $( this ).data( 'wpecPiPanel' );
				if ( panel ) {
					setOpen( panel, $( this ).data( 'wpecPiAcc' ), false );
				}
			} );
		}
	}

	/* Open a tab whatever the layout; returns the element to scroll to. */
	function openTab( tab ) {
		var root = $( tab ).closest( '.wpec-pi-tabs' )[0], button, panel;
		if ( ! root ) {
			return tab;
		}
		initTabs( root );
		if ( 'accordion' === root.wpecPiMode ) {
			button = $( tab ).data( 'wpecPiAcc' );
			panel = $( tab ).data( 'wpecPiPanel' );
			if ( '1' === root.getAttribute( 'data-acc-single' ) ) {
				selectTab( root, tab, false );
			} else if ( panel ) {
				setOpen( panel, button, true );
			}
			markActive( root, tab );
			return button || panel || root;
		}
		selectTab( root, tab, false );
		return tab;
	}

	function openPanel( panel ) {
		var root = $( panel ).closest( '.wpec-pi-tabs' )[0], tab;
		if ( ! root ) {
			return null;
		}
		initTabs( root );
		tab = tabsOf( root ).filter( function() {
			return $( this ).data( 'wpecPiPanel' ) === panel;
		} ).get( 0 );
		return tab ? openTab( tab ) : null;
	}

	$( document ).on( 'click', '.wpec-pi-tabs__list > li', function() {
		var root = $( this ).closest( '.wpec-pi-tabs' )[0];
		if ( ! root ) {
			return;
		}
		initTabs( root );
		selectTab( root, this, false );
	} );

	$( document ).on( 'keydown', '.wpec-pi-tabs__list > li', function( e ) {
		var root = $( this ).closest( '.wpec-pi-tabs' )[0], tabs, index, next = null, key = e.key, rtl = false;
		if ( ! root ) {
			return;
		}
		initTabs( root );
		tabs = visibleTabs( root );
		index = tabs.index( this );
		try {
			rtl = 'rtl' === window.getComputedStyle( this ).direction;
		} catch ( err ) {
			rtl = false;
		}
		if ( 'ArrowDown' === key || 'Down' === key || ( rtl ? ( 'ArrowLeft' === key || 'Left' === key ) : ( 'ArrowRight' === key || 'Right' === key ) ) ) {
			next = tabs.eq( ( index + 1 ) % tabs.length );
		} else if ( 'ArrowUp' === key || 'Up' === key || ( rtl ? ( 'ArrowRight' === key || 'Right' === key ) : ( 'ArrowLeft' === key || 'Left' === key ) ) ) {
			next = tabs.eq( ( index - 1 + tabs.length ) % tabs.length );
		} else if ( 'Home' === key ) {
			next = tabs.first();
		} else if ( 'End' === key ) {
			next = tabs.last();
		} else if ( 'Enter' === key || ' ' === key || 'Spacebar' === key ) {
			e.preventDefault();
			selectTab( root, this, false );
			return;
		}
		if ( next && next.length ) {
			e.preventDefault();
			selectTab( root, next.get( 0 ), true );
		}
	} );

	$( document ).on( 'click', '.wpec-pi-tabs__acc', function() {
		var button = this, root = $( button ).closest( '.wpec-pi-tabs' )[0], tab = $( button ).data( 'wpecPiTab' ), panel = document.getElementById( button.getAttribute( 'aria-controls' ) ), open = 'true' !== button.getAttribute( 'aria-expanded' );
		if ( ! panel ) {
			return;
		}
		/* "Allow several open" off: opening a row closes the others. */
		if ( open && root && tab && '1' === root.getAttribute( 'data-acc-single' ) ) {
			selectTab( root, tab, false );
			return;
		}
		setOpen( panel, button, open );
		if ( open && tab && root ) {
			markActive( root, tab );
		}
	} );

	var resizeQueued = false;
	$( window ).on( 'resize orientationchange', function() {
		if ( resizeQueued ) {
			return;
		}
		resizeQueued = true;
		( window.requestAnimationFrame || window.setTimeout )( function() {
			resizeQueued = false;
			$( '.wpec-pi-tabs[data-wpec-pi-ready]' ).each( function() {
				applyMode( this, false );
			} );
		} );
	} );

	/* ------------------------------------------------------------------ */
	/* Reviews                                                             */
	/* ------------------------------------------------------------------ */

	/* The chosen star and those below it carry the class ec_submit_product_review() counts. */
	function syncRate( stars ) {
		var inputs = $( stars ).find( '.wpec-pi-rate__input' ), value = parseInt( inputs.filter( ':checked' ).val(), 10 ) || 0;
		inputs.each( function() {
			var on = value > 0 && parseInt( this.value, 10 ) <= value;
			$( this ).toggleClass( 'ec_product_details_star_on_ele', on );
			$( this ).next( '.wpec-pi-rate__star' ).toggleClass( 'is-on', on );
		} );
	}

	$( document ).on( 'change', '.wpec-pi-rate__input', function() {
		syncRate( $( this ).closest( '.wpec-pi-rate__stars' ) );
	} );

	$( document ).on( 'mouseenter', '.wpec-pi-rate__star', function() {
		var stars = $( this ).closest( '.wpec-pi-rate__stars' ), value = parseInt( $( this ).prev( '.wpec-pi-rate__input' ).val(), 10 ) || 0;
		stars.addClass( 'is-hovering' ).find( '.wpec-pi-rate__star' ).each( function() {
			$( this ).toggleClass( 'is-hover', ( parseInt( $( this ).prev( '.wpec-pi-rate__input' ).val(), 10 ) || 0 ) <= value );
		} );
	} );

	$( document ).on( 'mouseleave', '.wpec-pi-rate__stars', function() {
		$( this ).removeClass( 'is-hovering' ).find( '.is-hover' ).removeClass( 'is-hover' );
	} );

	$( document ).on( 'submit', '.wpec-pi-review-form__form', function( e ) {
		var form = $( this );
		e.preventDefault();
		syncRate( form.find( '.wpec-pi-rate__stars' ) );
		if ( 'function' === typeof window.ec_submit_product_review ) {
			window.ec_submit_product_review( form.attr( 'data-product-id' ), form.attr( 'data-rand-id' ), form.attr( 'data-nonce' ) );
		}
	} );

	/* Which reviews show: the star filter, then "Reviews shown at first" ( data-wpec-pi-per-page ) and the pages opened since. */
	function applyReviews( root ) {
		var el = $( root ).get( 0 ), $root = $( el ), rating = el.wpecPiRating || 0, per = parseInt( $root.attr( 'data-wpec-pi-per-page' ), 10 ) || 0, limit = el.wpecPiLimit || per, matching = 0, shown = 0, more = $root.find( '.wpec-pi-reviews__more-wrap' );
		$root.find( '.wpec-pi-review' ).each( function() {
			var match = ! rating || parseInt( this.getAttribute( 'data-rating' ), 10 ) === rating;
			if ( match ) {
				matching++;
			}
			if ( match && ( ! per || matching <= limit ) ) {
				this.removeAttribute( 'hidden' );
				shown++;
			} else {
				this.setAttribute( 'hidden', 'hidden' );
			}
			$( this ).removeClass( 'wpec-pi-review--more' );
		} );
		/* The script pages the reviews from here on ( the class folded them until it ran ). */
		$root.removeClass( 'wpec-pi-reviews--paged' );
		if ( more.length ) {
			if ( per && shown < matching ) {
				more.removeAttr( 'hidden' );
			} else {
				more.attr( 'hidden', 'hidden' );
			}
		}
		return { shown: shown, total: matching };
	}

	function filterReviews( root, rating ) {
		var text = rating ? String( root.attr( 'data-wpec-pi-showing' ) || '' ).split( '[rating]' ).join( String( rating ) ) : '', bar = root.find( '.wpec-pi-reviews__filter' ), el = root.get( 0 );
		root.find( 'button.wpec-pi-reviews__bar[data-rating]' ).each( function() {
			this.setAttribute( 'aria-pressed', ( rating && parseInt( this.getAttribute( 'data-rating' ), 10 ) === rating ) ? 'true' : 'false' );
		} );
		if ( el ) {
			el.wpecPiRating = rating;
			el.wpecPiLimit = parseInt( root.attr( 'data-wpec-pi-per-page' ), 10 ) || 0;
			applyReviews( el );
		}
		bar.find( '.wpec-pi-reviews__filter-text' ).text( text );
		if ( rating ) {
			bar.removeAttr( 'hidden' );
		} else {
			bar.attr( 'hidden', 'hidden' );
		}
		root.find( '.wpec-pi-reviews__live' ).text( text );
	}

	$( document ).on( 'click', 'button.wpec-pi-reviews__bar[data-rating]', function() {
		var root = $( this ).closest( '.wpec-pi-reviews' ), rating = parseInt( this.getAttribute( 'data-rating' ), 10 ) || 0;
		filterReviews( root, 'true' === this.getAttribute( 'aria-pressed' ) ? 0 : rating );
	} );

	$( document ).on( 'click', '.wpec-pi-reviews__filter-clear', function() {
		var root = $( this ).closest( '.wpec-pi-reviews' );
		filterReviews( root, 0 );
		focusElement( root.find( 'button.wpec-pi-reviews__bar:not([disabled])' ).get( 0 ) );
	} );

	/* "Show more reviews": the next page, in place ( without the script the link reloads the page with every review ). */
	function showMoreReviews( link ) {
		var root = $( link ).closest( '.wpec-pi-reviews' ), el = root.get( 0 ), per, before, result, first;
		if ( ! el ) {
			return;
		}
		per = parseInt( root.attr( 'data-wpec-pi-per-page' ), 10 ) || 0;
		before = root.find( '.wpec-pi-review:not([hidden])' ).length;
		el.wpecPiLimit = ( el.wpecPiLimit || per ) + per;
		result = applyReviews( el );
		first = root.find( '.wpec-pi-review:not([hidden])' ).get( before );
		root.find( '.wpec-pi-reviews__live' ).text( String( root.attr( 'data-wpec-pi-shown' ) || '' ).split( '[shown]' ).join( String( result.shown ) ).split( '[total]' ).join( String( result.total ) ) );
		/* Focus goes to the first review added, so keyboard and screen reader users carry on reading there. */
		focusElement( first );
	}

	$( document ).on( 'click', '[data-wpec-pi-more]', function( e ) {
		e.preventDefault();
		showMoreReviews( this );
	} );

	$( document ).on( 'keydown', '[data-wpec-pi-more]', function( e ) {
		if ( ' ' === e.key || 'Spacebar' === e.key ) {
			e.preventDefault();
			showMoreReviews( this );
		}
	} );

	/* Arrived from a review-request email: open the reviews, pre-select the star tapped in the email, go to the form. */
	function land( root ) {
		var banner = $( root ).find( '.ec_review_request_banner' ).get( 0 ), rating, input, panel, form;
		if ( ! banner || landed ) {
			return;
		}
		landed = true;
		rating = parseInt( banner.getAttribute( 'data-prefill-rating' ), 10 ) || 0;
		panel = $( root ).closest( '.wpec-pi-tabs__panel' ).get( 0 );
		if ( panel ) {
			openPanel( panel );
		}
		if ( rating >= 1 && rating <= 5 ) {
			input = $( root ).find( '.wpec-pi-rate__input' ).filter( function() {
				return parseInt( this.value, 10 ) === rating;
			} ).get( 0 );
			if ( input ) {
				input.checked = true;
				syncRate( $( input ).closest( '.wpec-pi-rate__stars' ) );
			}
		}
		form = $( banner ).closest( '.wpec-pi-review-form' ).get( 0 );
		window.setTimeout( function() {
			scrollToElement( form );
		}, 200 );
	}

	function initReviews( root ) {
		if ( ! root || root.getAttribute( 'data-wpec-pi-ready' ) ) {
			return;
		}
		root.setAttribute( 'data-wpec-pi-ready', '1' );
		$( root ).find( '.wpec-pi-rate__stars' ).each( function() {
			syncRate( this );
		} );
		if ( root.hasAttribute( 'data-wpec-pi-per-page' ) ) {
			$( root ).find( '[data-wpec-pi-more]' ).attr( 'role', 'button' );
			applyReviews( root );
		}
		land( root );
	}

	/* ------------------------------------------------------------------ */
	/* Rating link: go to the reviews                                      */
	/* ------------------------------------------------------------------ */

	function goToReviews( productId ) {
		var id = parseInt( productId, 10 ), target, panel, scrollTarget, li, list, area;
		if ( ! id ) {
			return false;
		}
		target = $( '[data-wpec-pi-reviews="' + id + '"]' ).get( 0 );
		if ( target ) {
			panel = $( target ).closest( '.wpec-pi-tabs__panel' ).get( 0 );
			scrollTarget = target;
			/* The Product Tabs widget drawn by WP EasyCart Tabs: its script opens the Reviews tab ( key "reviews" ). */
			area = $( target ).closest( '.wpt[data-style]' ).get( 0 );
			if ( area && window.wpecTabs && 'function' === typeof window.wpecTabs.open && window.wpecTabs.open( area, 'reviews', { scroll: true, focus: true, user: true } ) ) {
				return true;
			}
			if ( panel ) {
				scrollTarget = openPanel( panel ) || target;
				if ( 'tabs' === ( $( panel ).closest( '.wpec-pi-tabs' )[0] || {} ).wpecPiMode ) {
					scrollTarget = $( panel ).closest( '.wpec-pi-tabs' )[0];
				}
			}
			scrollToElement( scrollTarget );
			focusElement( target );
			return true;
		}
		/* The product page's tabs, or the older reviews widget. */
		li = $( '.ec_details_tabs[data-product-id="' + id + '"] > .ec_customer_reviews' ).get( 0 );
		if ( li ) {
			if ( 'function' === typeof window.wpeasycart_tabs_select ) {
				window.wpeasycart_tabs_select( li, false );
			} else {
				$( li ).trigger( 'click' );
			}
			scrollToElement( $( li ).closest( '.ec_details_extra_area' ).get( 0 ) || li );
			return true;
		}
		list = $( '.ec_details_customer_review_list[data-product-id="' + id + '"]' ).get( 0 );
		if ( list ) {
			scrollToElement( list.parentNode || list );
			return true;
		}
		return false;
	}

	/* The reviews on this page open in place; otherwise the link goes to the product page's #reviews. */
	$( document ).on( 'click', '[data-wpec-pi-reviews-link]', function( e ) {
		if ( goToReviews( this.getAttribute( 'data-wpec-pi-reviews-link' ) ) ) {
			e.preventDefault();
		}
	} );

	/* #tab-<slug> opens that tab ( #tab-reviews, #tab-description, … ); #reviews goes to the reviews. */
	function openFromHash() {
		var hash = '', slug, tab;
		try {
			hash = decodeURIComponent( ( window.location.hash || '' ).replace( /^#/, '' ) );
		} catch ( e ) {
			hash = '';
		}
		if ( ! hash ) {
			return;
		}
		if ( 'reviews' === hash ) {
			tab = $( '[data-wpec-pi-reviews]' ).get( 0 );
			if ( tab ) {
				goToReviews( tab.getAttribute( 'data-wpec-pi-reviews' ) );
			}
			return;
		}
		if ( 0 !== hash.indexOf( 'tab-' ) ) {
			return;
		}
		slug = hash.slice( 4 );
		if ( 'customer-reviews' === slug ) {
			slug = 'reviews';
		}
		tab = $( '.wpec-pi-tabs__list > li' ).filter( function() {
			return this.getAttribute( 'data-tab-slug' ) === slug;
		} ).get( 0 );
		if ( tab ) {
			scrollToElement( openTab( tab ) );
		}
	}

	$( window ).on( 'hashchange', openFromHash );

	/* ------------------------------------------------------------------ */
	/* Share Buttons: Copy link                                            */
	/* ------------------------------------------------------------------ */

	function copyFallback( text ) {
		var area = document.createElement( 'textarea' ), ok = false;
		area.value = text;
		area.setAttribute( 'readonly', '' );
		area.style.position = 'fixed';
		area.style.top = '-1000px';
		area.style.opacity = '0';
		document.body.appendChild( area );
		area.select();
		try {
			ok = document.execCommand( 'copy' );
		} catch ( e ) {
			ok = false;
		}
		document.body.removeChild( area );
		return ok;
	}

	$( document ).on( 'click', '[data-wpec-pi-copy]', function() {
		var button = this, url = button.getAttribute( 'data-wpec-pi-copy' ) || '', wrap = $( button ).closest( '.wpec-pi-share' ), done;
		done = function() {
			var message = wrap.attr( 'data-wpec-pi-copied' ) || '', status = wrap.find( '.wpec-pi-share__status' );
			status.text( '' );
			window.setTimeout( function() {
				status.text( message );
			}, 50 );
			$( button ).addClass( 'is-copied' );
			window.setTimeout( function() {
				$( button ).removeClass( 'is-copied' );
				status.text( '' );
			}, 2500 );
		};
		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard.writeText( url ).then( done, function() {
				if ( copyFallback( url ) ) {
					done();
				}
			} );
		} else if ( copyFallback( url ) ) {
			done();
		}
	} );

	/* ------------------------------------------------------------------ */
	/* Product Meta: the SKU follows the chosen option                     */
	/* ------------------------------------------------------------------ */

	$( document ).on( 'wpeasycart_product_state', function( e, state ) {
		var id = state ? parseInt( state.product_id, 10 ) : 0;
		if ( ! id ) {
			return;
		}
		$( '[data-wpec-pi-sku="' + id + '"]' ).each( function() {
			var sku = ( state.sku && String( state.sku ).trim() ) ? String( state.sku ).trim() : ( this.getAttribute( 'data-default' ) || '' );
			if ( this.textContent !== sku ) {
				this.textContent = sku;
			}
		} );
		/* The stock line follows the chosen option when the store says what it has of it. */
		$( '[data-wpec-pi-stock="' + id + '"]' ).each( function() {
			var text = ( state.stock_display && String( state.stock_display ).trim() ) ? String( state.stock_display ).trim() : ( this.getAttribute( 'data-default' ) || '' );
			if ( this.textContent !== text ) {
				this.textContent = text;
			}
		} );
	} );

	/* ------------------------------------------------------------------ */
	/* Description widgets: Read more                                      */
	/* ------------------------------------------------------------------ */

	/* Text that fits needs no button ( a pixel or two of rounding is not "more" ). False while the text is not on screen. */
	function measureCollapse( root, content ) {
		if ( ! content.clientHeight ) {
			return false;
		}
		if ( content.scrollHeight <= content.clientHeight + 2 ) {
			$( root ).removeClass( 'is-collapsed' ).addClass( 'is-short' );
		}
		return true;
	}

	function initCollapse( root ) {
		var content, toggle, observer;
		if ( ! root || root.getAttribute( 'data-wpec-pi-ready' ) ) {
			return;
		}
		root.setAttribute( 'data-wpec-pi-ready', '1' );
		content = $( root ).children( '.wpec-pi-collapse__content' ).get( 0 );
		toggle = $( root ).find( '.wpec-pi-collapse__toggle' ).get( 0 );
		if ( ! content || ! toggle || measureCollapse( root, content ) || ! window.ResizeObserver ) {
			return;
		}
		/* Hidden for now ( a closed tab or popup ): decide once it shows. */
		observer = new window.ResizeObserver( function() {
			if ( measureCollapse( root, content ) ) {
				observer.disconnect();
			}
		} );
		observer.observe( content );
	}

	$( document ).on( 'click', '.wpec-pi-collapse__toggle', function() {
		var root = $( this ).closest( '.wpec-pi-collapse' ), open = 'true' !== this.getAttribute( 'aria-expanded' ), top;
		root.toggleClass( 'is-collapsed', ! open );
		this.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		this.textContent = open ? ( root.attr( 'data-less' ) || this.textContent ) : ( root.attr( 'data-more' ) || this.textContent );
		if ( ! open && root[0] && root[0].getBoundingClientRect ) {
			top = root[0].getBoundingClientRect().top;
			/* Folding a long text back leaves the reader below it: bring its start back into view. */
			if ( top < 0 ) {
				scrollToElement( root[0] );
			}
		}
	} );

	/* ------------------------------------------------------------------ */
	/* Share Buttons: Print, the device's share menu                       */
	/* ------------------------------------------------------------------ */

	$( document ).on( 'click', '[data-wpec-pi-print]', function() {
		if ( 'function' === typeof window.print ) {
			window.print();
		}
	} );

	$( document ).on( 'click', '[data-wpec-pi-native]', function() {
		var button = this;
		if ( ! navigator.share ) {
			return;
		}
		navigator.share( { title: button.getAttribute( 'data-title' ) || document.title, url: button.getAttribute( 'data-wpec-pi-native' ) || window.location.href } ).then( null, function() {} );
	} );

	function initShare( root ) {
		if ( navigator.share ) {
			$( root ).find( '[data-wpec-pi-native][hidden]' ).removeAttr( 'hidden' );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Boot                                                                */
	/* ------------------------------------------------------------------ */

	function init( scope ) {
		var $scope = scope ? $( scope ) : $( document );
		$scope.find( '.wpec-pi-tabs' ).addBack( '.wpec-pi-tabs' ).each( function() {
			initTabs( this );
		} );
		$scope.find( '.wpec-pi-reviews' ).addBack( '.wpec-pi-reviews' ).each( function() {
			initReviews( this );
		} );
		$scope.find( '.wpec-pi-collapse' ).addBack( '.wpec-pi-collapse' ).each( function() {
			initCollapse( this );
		} );
		$scope.find( '.wpec-pi-share' ).addBack( '.wpec-pi-share' ).each( function() {
			initShare( this );
		} );
		/* The tab area WP EasyCart Tabs draws for the Product Tabs widget ( its init is safe to call again ). */
		if ( 'function' === typeof window.wpecTabsInit ) {
			$scope.find( '.wpec-pi-tabs-area' ).addBack( '.wpec-pi-tabs-area' ).each( function() {
				window.wpecTabsInit( this );
			} );
		}
	}

	window.wpeasycart_product_info_init = init;

	$( function() {
		init();
		openFromHash();
	} );

	function addHooks() {
		var names = [ 'wp_easycart_product_tabs', 'wp_easycart_product_reviews', 'wp_easycart_product_rating', 'wp_easycart_product_share', 'wp_easycart_product_meta', 'wp_easycart_product_description', 'wp_easycart_product_short_description' ];
		if ( ! window.elementorFrontend || ! window.elementorFrontend.hooks || 'function' !== typeof window.elementorFrontend.hooks.addAction ) {
			return false;
		}
		$.each( names, function( i, name ) {
			window.elementorFrontend.hooks.addAction( 'frontend/element_ready/' + name + '.default', function( $element ) {
				init( $element && $element[0] ? $element[0] : null );
			} );
		} );
		return true;
	}

	if ( ! addHooks() ) {
		$( window ).on( 'elementor/frontend/init', addHooks );
	}
}( window.jQuery ) );
