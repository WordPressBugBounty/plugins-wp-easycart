/**
 * WP EasyCart Admin Shell V2
 * Drawer, submenu accordions, help dropdown, and live color theming.
 *
 * Color save reuses the existing AJAX action `ec_admin_ajax_save_color_scheme`
 * with the nonce printed on #ec_admin_shell_color (unchanged from V1).
 *
 * Shade math mirrors wp_easycart_shell_theme.php / the @supports block in
 * shell-v2.css — keep all three in sync.
 *
 * @since 5.x.x
 */
( function( $ ) {
	'use strict';

	/* ---------- Shade derivation (JS mirror of the PHP recipe) ---------- */
	function mixHex( hex, target, pct ) {
		function ch( h, i ) { return parseInt( h.substr( 1 + i * 2, 2 ), 16 ); }
		function pad( n ) { return Math.round( n ).toString( 16 ).padStart( 2, '0' ); }
		var out = '#';
		for ( var i = 0; i < 3; i++ ) {
			var a = ch( hex, i ), b = ch( target, i );
			out += pad( a + ( b - a ) * pct );
		}
		return out;
	}
	function luminance( hex ) {
		var r = parseInt( hex.substr( 1, 2 ), 16 ),
			g = parseInt( hex.substr( 3, 2 ), 16 ),
			b = parseInt( hex.substr( 5, 2 ), 16 );
		return ( 0.299 * r + 0.587 * g + 0.114 * b ) / 255;
	}
	function wpEasyCartShellShades( brand ) {
		var light   = luminance( brand ) > 0.72;
		var onBrand = light ? '#1f2937' : '#ffffff';
		return {
			'--ec-brand':        brand,
			'--ec-brand-hover':  mixHex( brand, '#000000', 0.12 ),
			'--ec-brand-active': mixHex( brand, '#000000', 0.22 ),
			'--ec-brand-deep':   mixHex( brand, '#000000', 0.42 ),
			'--ec-brand-soft':   mixHex( brand, '#ffffff', 0.90 ),
			'--ec-brand-softer': mixHex( brand, '#ffffff', 0.95 ),
			'--ec-on-brand':     onBrand,
			'--ec-on-brand-dim': mixHex( onBrand, brand, 0.28 )
		};
	}

	/* Applying all vars inline works for BOTH engines: inline custom
	   properties on :root override the @supports-derived ones with
	   identical values, and give old browsers correct shades too. */
	function applyBrand( brand ) {
		var root = document.documentElement;
		var vars = wpEasyCartShellShades( brand );
		Object.keys( vars ).forEach( function( k ) {
			root.style.setProperty( k, vars[ k ] );
		} );
	}

	$( function() {

		/* ---------- Mobile drawer ---------- */
		$( document ).on( 'click', '.ecsh-tb-hamburger, .ecsh-bn-more', function( e ) {
			e.preventDefault();
			$( 'body' ).addClass( 'ecsh-drawer-open' );
		} );
		$( document ).on( 'click', '.ecsh-scrim', function() {
			$( 'body' ).removeClass( 'ecsh-drawer-open' );
		} );
		$( document ).on( 'keydown', function( e ) {
			if ( 'Escape' === e.key ) {
				$( 'body' ).removeClass( 'ecsh-drawer-open' );
				$( '.ecsh-tb-menu' ).removeClass( 'ecsh-open' );
			}
		} );

		/* ---------- Submenu accordions ---------- */
		$( document ).on( 'click', '.ecsh-sb-chev', function( e ) {
			e.preventDefault();
			var $btn = $( this );
			var $sub = $( $btn.attr( 'data-target' ) );
			var open = $sub.toggleClass( 'ecsh-open' ).hasClass( 'ecsh-open' );
			$btn.attr( 'aria-expanded', open ? 'true' : 'false' );
		} );

		/* ---------- Help dropdown ---------- */
		$( document ).on( 'click', '.ecsh-tb-menu > .ecsh-tb-btn', function( e ) {
			e.preventDefault();
			$( this ).parent().toggleClass( 'ecsh-open' );
		} );
		$( document ).on( 'click', function( e ) {
			var $menu = $( '.ecsh-tb-menu.ecsh-open' );
			if ( $menu.length && ! $menu.is( e.target ) && 0 === $menu.has( e.target ).length ) {
				$menu.removeClass( 'ecsh-open' );
			}
		} );

		/* Expandable groups inside the dropdown (Video Tutorials) */
		$( document ).on( 'click', '.ecsh-dd-group-toggle', function( e ) {
			e.preventDefault();
			e.stopPropagation();
			var open = $( this ).closest( '.ecsh-dd-group' ).toggleClass( 'ecsh-open' ).hasClass( 'ecsh-open' );
			$( this ).attr( 'aria-expanded', open ? 'true' : 'false' );
		} );

		/* ---------- In-admin video lightbox ----------
		   Video links carry data-ecsh-video (YouTube id). Plain click plays
		   in the existing .ec_admin_help_video_container lightbox; modified
		   clicks (new tab) fall through to the YouTube href. The legacy
		   container element is a <div>, so ensure an <iframe> exists before
		   handing off, and clear src on close so playback stops. */
		function ecshEnsureVideoIframe() {
			var $player = $( '#wp_easycart_admin_help_video_player' );
			if ( $player.length && ! $player.is( 'iframe' ) ) {
				$player.replaceWith( '<iframe id="wp_easycart_admin_help_video_player" title="" allow="autoplay; encrypted-media; fullscreen; picture-in-picture" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>' );
			}
		}
		/* 6.0.0: centered 16:9 player sized to the window ( CSS ), title bar with a YouTube link, closes on the X, Esc or a
		   click outside, returns focus to the link that opened it, and clears src so playback stops. */
		var $ecshVideo = $( '.ec_admin_help_video_container' ), ecshVideoReturn = null;
		window.ecsh_video_open = function( videoId, title ) {
			var id = String( videoId || '' ).replace( /[^A-Za-z0-9_-]/g, '' );
			if ( ! id || ! $ecshVideo.length ) {
				return false;
			}
			ecshEnsureVideoIframe();
			ecshVideoReturn = document.activeElement;
			var label = $.trim( String( title || '' ) );
			if ( label ) {
				$ecshVideo.find( '.ecsh-video-title' ).text( label );
			}
			$ecshVideo.find( '.ecsh-video-yt' ).attr( 'href', 'https://www.youtube.com/watch?v=' + id );
			$( '#wp_easycart_admin_help_video_player' )
				.attr( 'title', label || $ecshVideo.find( '.ecsh-video-title' ).text() )
				.attr( 'src', 'https://www.youtube.com/embed/' + id + '?autoplay=1&rel=0&modestbranding=1&playsinline=1' );
			$ecshVideo.removeAttr( 'style' ).addClass( 'is-open' ).attr( 'aria-hidden', 'false' );
			$( 'body' ).addClass( 'ecsh-video-open' );
			setTimeout( function() { $ecshVideo.find( '.ecsh-video-close' ).trigger( 'focus' ); }, 30 );
			return false;
		};
		window.ecsh_video_close = function() {
			if ( ! $ecshVideo.hasClass( 'is-open' ) ) {
				return false;
			}
			$ecshVideo.removeClass( 'is-open' ).attr( 'aria-hidden', 'true' );
			$( '#wp_easycart_admin_help_video_player' ).attr( 'src', '' );
			$( 'body' ).removeClass( 'ecsh-video-open' );
			if ( ecshVideoReturn && ecshVideoReturn.focus ) {
				try { ecshVideoReturn.focus(); } catch ( err ) {}
			}
			ecshVideoReturn = null;
			return false;
		};
		window.wp_easycart_admin_open_video_help = window.ecsh_video_open;
		window.wp_easycart_admin_close_video_help = window.ecsh_video_close;
		$ecshVideo.on( 'click', function( e ) {
			if ( ! $( e.target ).closest( '.ecsh-video-dialog' ).length ) {
				window.ecsh_video_close();
			}
		} );
		$( document ).on( 'keydown', function( e ) {
			if ( 'Escape' === e.key && $ecshVideo.hasClass( 'is-open' ) ) {
				e.preventDefault();
				window.ecsh_video_close();
			}
		} );
		$( document ).on( 'click', 'a[data-ecsh-video]', function( e ) {
			if ( e.metaKey || e.ctrlKey || e.shiftKey || 1 === e.button ) {
				return; /* honor open-in-new-tab */
			}
			e.preventDefault();
			$( '.ecsh-tb-menu' ).removeClass( 'ecsh-open' );
			window.ecsh_video_open( $( this ).attr( 'data-ecsh-video' ), $( this ).text() );
		} );

		/* ---------- Command palette: jump to admin pages/sections or search docs ----------
		   Index comes from wp_easycart_shell_output_search_index() (capability-
		   gated, filterable). Section entries carry &ecshs=<label>; on arrival
		   the block below scrolls to the matching section header. The topbar
		   form remains a working no-JS docs search. */
		var $palette = $( '#ecsh_palette' );
		if ( $palette.length ) {
			var $pInput   = $( '#ecsh_palette_input' );
			var $pResults = $( '#ecsh_palette_results' );
			var pIndex    = ( 'undefined' !== typeof window.wpEasyCartShellSearchIndex ) ? window.wpEasyCartShellSearchIndex : [];
			var pSelected = 0;
			var pItems    = [];

			function paletteOpen() {
				$palette.prop( 'hidden', false );
				$pInput.val( '' );
				paletteRender( '' );
				setTimeout( function() { $pInput.trigger( 'focus' ); }, 10 );
			}
			function paletteClose() {
				$palette.prop( 'hidden', true );
			}
			function paletteScore( item, q ) {
				var label = item.label.toLowerCase(), kw = ( item.kw || '' ).toLowerCase(), group = ( item.group || '' ).toLowerCase();
				if ( 0 === label.indexOf( q ) ) { return 3; }
				if ( -1 !== label.indexOf( q ) ) { return 2; }
				/* every query word must appear somewhere in label+kw+group */
				var hay = label + ' ' + kw + ' ' + group, words = q.split( /\s+/ ), ok = true;
				for ( var i = 0; i < words.length; i++ ) {
					if ( words[ i ] && -1 === hay.indexOf( words[ i ] ) ) { ok = false; break; }
				}
				return ok ? 1 : 0;
			}
			function paletteRender( q ) {
				q = $.trim( q ).toLowerCase();
				var matches = [];
				if ( '' === q ) {
					/* Empty query: top-level destinations as quick links */
					matches = $.grep( pIndex, function( it ) { return '' === ( it.group || '' ); } );
				} else {
					$.each( pIndex, function( _, it ) {
						var s = paletteScore( it, q );
						if ( s > 0 ) { matches.push( { s: s, it: it } ); }
					} );
					matches.sort( function( a, b ) { return b.s - a.s; } );
					matches = $.map( matches.slice( 0, 8 ), function( m ) { return m.it; } );
				}
				pItems = matches.slice( 0 );
				pSelected = 0;

				var html = '';
				if ( q && 0 === matches.length ) {
					html += '<div class="ecsh-palette-empty">' + window.wpEasyCartShellNoResultsLabel + ' &ldquo;' + $( '<i>' ).text( q ).html() + '&rdquo;</div>';
				}
				$.each( matches, function( i, it ) {
					html += '<button type="button" class="ecsh-palette-item" data-i="' + i + '">'
						+ '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>'
						+ '<span>' + $( '<i>' ).text( it.label ).html() + '</span>'
						+ ( it.group ? '<span class="ecsh-palette-group">' + $( '<i>' ).text( it.group ).html() + '</span>' : '' )
						+ '</button>';
				} );
				/* Docs search is always the final row */
				html += '<button type="button" class="ecsh-palette-item ecsh-palette-item-docs" data-docs="1">'
					+ '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>'
					+ '<span>' + window.wpEasyCartShellDocsSearchLabel + ( q ? ' &ldquo;' + $( '<i>' ).text( q ).html() + '&rdquo;' : '\u2026' ) + '</span>'
					+ '</button>';
				$pResults.html( html );
				paletteHighlight();
			}
			function paletteHighlight() {
				var $rows = $pResults.find( '.ecsh-palette-item' );
				$rows.removeClass( 'ecsh-selected' );
				var $row = $rows.eq( pSelected );
				$row.addClass( 'ecsh-selected' );
				if ( $row.length && $row[0].scrollIntoView ) { $row[0].scrollIntoView( { block: 'nearest' } ); }
			}
			function paletteGo( idx ) {
				var $rows = $pResults.find( '.ecsh-palette-item' );
				var $row  = $rows.eq( idx );
				if ( $row.data( 'docs' ) ) {
					var q = $.trim( $pInput.val() );
					window.open( 'https://docs.wpeasycart.com/?s=' + encodeURIComponent( q ), '_blank' );
					paletteClose();
					return;
				}
				var it = pItems[ $row.data( 'i' ) ];
				if ( ! it ) { return; }
				var target = it.url;
				var section = null;
				var m = target.match( /[?&]ecshs=([^&]+)/ );
				if ( m ) { section = decodeURIComponent( m[1] ); }
				/* Same page+subpage: jump in place instead of reloading */
				if ( section ) {
					var base = target.replace( /&ecshs=[^&]+/, '' ).replace( /^admin\.php\?/, '' );
					var here = window.location.search.replace( /^\?/, '' );
					if ( 0 === here.indexOf( base ) && ecshJumpToSection( section ) ) {
						paletteClose();
						return;
					}
				}
				window.location.href = target;
			}

			$( document ).on( 'focus click', '.ecsh-tb-search input', function( e ) {
				e.preventDefault();
				$( this ).trigger( 'blur' );
				paletteOpen();
			} );
			$( document ).on( 'submit', '.ecsh-tb-search', function( e ) { e.preventDefault(); paletteOpen(); } );
			$( document ).on( 'click', '.ecsh-tb-search-btn', paletteOpen );
			$( document ).on( 'click', '.ecsh-palette-backdrop', paletteClose );
			$( document ).on( 'keydown', function( e ) {
				if ( ( e.metaKey || e.ctrlKey ) && 'k' === String( e.key ).toLowerCase() ) {
					e.preventDefault();
					if ( $palette.prop( 'hidden' ) ) { paletteOpen(); } else { paletteClose(); }
				}
				if ( 'Escape' === e.key && ! $palette.prop( 'hidden' ) ) { paletteClose(); }
			} );
			$pInput.on( 'input', function() { paletteRender( this.value ); } );
			$pInput.on( 'keydown', function( e ) {
				var count = $pResults.find( '.ecsh-palette-item' ).length;
				if ( 'ArrowDown' === e.key ) { e.preventDefault(); pSelected = Math.min( pSelected + 1, count - 1 ); paletteHighlight(); }
				else if ( 'ArrowUp' === e.key ) { e.preventDefault(); pSelected = Math.max( pSelected - 1, 0 ); paletteHighlight(); }
				else if ( 'Enter' === e.key ) { e.preventDefault(); paletteGo( pSelected ); }
			} );
			$( document ).on( 'click', '.ecsh-palette-item', function() { paletteGo( $pResults.find( '.ecsh-palette-item' ).index( this ) ); } );
		}

		/* ---------- Section jump: scroll + highlight a section header ----------
		   Matches by text across the shared legacy header classes and common
		   v2/page headings, so no anchors are needed in templates. */
		function ecshJumpToSection( label ) {
			var needle = $.trim( label ).toLowerCase();
			var $target = null;
			$( '.ecsh-content' ).find( '.ec_admin_settings_label, .ec_admin_settings_label_center, .ec_admin_settings_panel h2, .ec_admin_help_video_box > h1, .ecv2-page-title, h1, h2, h3' ).each( function() {
				if ( $target ) { return; }
				var text = $.trim( $( this ).text() ).toLowerCase();
				if ( text && -1 !== text.indexOf( needle ) ) { $target = $( this ); }
			} );
			if ( ! $target ) { return false; }
			$target[0].scrollIntoView( { behavior: 'smooth', block: 'center' } );
			$target.addClass( 'ecsh-jump-highlight' );
			setTimeout( function() { $target.removeClass( 'ecsh-jump-highlight' ); }, 2200 );
			return true;
		}
		( function() {
			var m = window.location.search.match( /[?&]ecshs=([^&]+)/ );
			if ( m ) {
				var label = decodeURIComponent( m[1].replace( /\+/g, ' ' ) );
				/* Legacy content may render late; retry briefly */
				var tries = 0;
				var timer = setInterval( function() {
					tries++;
					if ( ecshJumpToSection( label ) || tries > 10 ) { clearInterval( timer ); }
				}, 250 );
			}
		} )();

		/* ---------- Color selector: live preview + debounced save ---------- */
		var $picker = $( '#ec_admin_shell_color' );
		if ( $picker.length ) {
			var saveTimeout = false;
			$picker.on( 'input change', function() {
				var brand = this.value;
				applyBrand( brand );

				if ( saveTimeout ) {
					clearTimeout( saveTimeout );
				}
				saveTimeout = setTimeout( function() {
					$.ajax( {
						url: wpeasycart_admin_ajax_object.ajax_url,
						type: 'post',
						data: {
							action: 'ec_admin_ajax_save_color_scheme',
							ec_option_admin_color: brand,
							wp_easycart_nonce: $picker.attr( 'data-nonce' )
						}
					} );
				}, 1200 );
			} );
		}
	} );

	/* Admin notices ( .ecv2-flash ): dismiss, and drop the one-time ?success= / ?error= / ?warning= flag from the
	 * address bar once shown so a refresh or a bookmark does not repeat the message. */
	$( document ).on( 'click', '.ecv2-flash-x', function() {
		var $n = $( this ).closest( '.ecv2-flash' );
		$n.addClass( 'is-leaving' );
		setTimeout( function() { $n.remove(); }, 200 );
	} );
	$( function() {
		if ( window.wpEasyCartListRestoring || ! $( '.ecv2-flash' ).length || ! window.history || ! window.history.replaceState || typeof URL !== 'function' ) { return; }
		try {
			var url = new URL( window.location.href ), changed = false;
			[ 'success', 'error', 'warning' ].forEach( function( p ) { if ( url.searchParams.has( p ) ) { url.searchParams.delete( p ); changed = true; } } );
			if ( changed ) { window.history.replaceState( window.history.state, '', url.toString() ); }
		} catch ( e ) {}
	} );

	/* ---------- List state: keep search / filters / sort / page across list -> editor -> back ----------
	 * Every V2 list ( wp_easycart_admin_table_v2, form #ecv2-posts-filter ) is a GET form, so its state lives
	 * in the query string. Per browser tab ( sessionStorage ), remember the last state of each list, keyed by
	 * page + subpage + tab. Then:
	 *  1. On any other EasyCart page of the same page + subpage ( an editor, details page, settings tab ), links
	 *     in the content area or breadcrumb ( and editor back arrows anywhere ) that point at that list with no
	 *     search or filter ( back arrow, Cancel, "Back to ...", empty-state links ) are pointed at the remembered
	 *     state. Echoed paging / sort params are allowed when they agree with it. Links that carry a search,
	 *     filter, id or action are explicit and left alone. Add data-ecv2-list-fresh to a link to opt out.
	 *     Script navigation: window.ecv2_list_url( href ), or <button data-ecv2-list-back="list url">.
	 *     Browser Back needs nothing: the list's history entry is its own stateful GET URL.
	 *  2. When an action from the list or its editor lands back on the bare list ( a ?success= / ?error= redirect
	 *     after a save or delete, or a JS redirect that leaves an ecv2_catalog_flash toast ), the remembered state
	 *     is reapplied once, keeping the flash message.
	 * Nonces, bulk selections, ec_admin_form_action and one-time params are never stored.
	 * @since 6.0.0 */
	window.ecv2_list_url = function( href ) { return href; }; /* replaced below on editor pages when state is available */
	( function() {
		if ( typeof URL !== 'function' || typeof URLSearchParams !== 'function' ) { return; }

		var PREFIX = 'wpec_list_state:';
		var GUARD = 'wpec_list_state_restore';
		var TTL = 6 * 60 * 60 * 1000;
		/* Pages that render a list when subpage is omitted. */
		var DEFAULT_SUBPAGE = { 'wp-easycart-products': 'products', 'wp-easycart-orders': 'orders', 'wp-easycart-users': 'accounts' };
		var IDENTITY = { page: 1, subpage: 1, tab: 1 };
		var FLASH = { success: 1, error: 1, warning: 1 };
		var NEVER = { wp_easycart_nonce: 1, _wpnonce: 1, _wp_http_referer: 1, ec_admin_form_action: 1, bulk: 1, 'bulk[]': 1, success: 1, error: 1, warning: 1 };
		/* State params that may be in the URL without a matching control in the form ( pager, sort links, view toggle ). */
		var STATE_PARAM = /^(s|orderby|order|pagenum|perpage|view_mode|health_filter|filter_\d+)$/;
		var store = null;
		try {
			store = window.sessionStorage;
			store.setItem( PREFIX + 'test', '1' );
			store.removeItem( PREFIX + 'test' );
		} catch ( e ) {
			return;
		}

		function parse( href ) {
			var u, page, subpage, tab;
			if ( ! href ) { return null; }
			try { u = new URL( href, window.location.href ); } catch ( e ) { return null; }
			if ( u.protocol !== window.location.protocol || u.host !== window.location.host || u.pathname !== window.location.pathname || ! /\/admin\.php$/.test( u.pathname ) ) { return null; }
			page = u.searchParams.get( 'page' ) || '';
			if ( 0 !== page.indexOf( 'wp-easycart-' ) ) { return null; }
			subpage = u.searchParams.get( 'subpage' ) || DEFAULT_SUBPAGE[ page ] || '';
			tab = u.searchParams.get( 'tab' ) || '';
			return { url: u, base: page + '|' + subpage, key: PREFIX + page + '|' + subpage + '|' + tab };
		}
		/* True when every non-empty param is in one of the allowed sets. */
		function onlyParams( u, a, b ) {
			var ok = true;
			u.searchParams.forEach( function( v, k ) {
				if ( '' !== v && ! a[ k ] && ! ( b && b[ k ] ) ) { ok = false; }
			} );
			return ok;
		}
		function read( key ) {
			var st = null;
			try { st = JSON.parse( store.getItem( key ) || 'null' ); } catch ( e ) {}
			if ( ! st || 'string' !== typeof st.q || ! st.q || 'number' !== typeof st.t || Date.now() - st.t > TTL ) { return null; }
			return st;
		}
		/* The list's shareable state from the current URL: identity + non-empty params the list itself owns. */
		function capture( here, form ) {
			var names = {}, out = new URLSearchParams(), has = false, i, sp = here.url.searchParams;
			if ( sp.get( 'ec_admin_form_action' ) ) { return null; } /* an action URL, not a list view */
			for ( i = 0; i < form.elements.length; i++ ) {
				if ( form.elements[ i ].name ) { names[ form.elements[ i ].name ] = 1; }
			}
			out.set( 'page', sp.get( 'page' ) );
			if ( sp.get( 'subpage' ) ) { out.set( 'subpage', sp.get( 'subpage' ) ); }
			if ( sp.get( 'tab' ) ) { out.set( 'tab', sp.get( 'tab' ) ); }
			sp.forEach( function( v, k ) {
				if ( '' === v || IDENTITY[ k ] || NEVER[ k ] || ( ! names[ k ] && ! STATE_PARAM.test( k ) ) ) { return; }
				out.append( k, v );
				has = true;
			} );
			return has ? out.toString() : '';
		}
		/* Editors and their redirects often echo the paging / sort they were opened with ( the product editor always
		 * adds &order=asc, plus &pagenum / &orderby when present ) but drop the search and filters. Such a URL still
		 * means "the list": default values ( pagenum=1, order=asc ) are ignored, and any other echoed paging / sort
		 * value must match the remembered state. */
		var ECHOED = { pagenum: 1, orderby: 1, order: 1, perpage: 1, view_mode: 1 };
		function agrees( u, st ) {
			var saved = new URLSearchParams( st.q ), ok = true;
			u.searchParams.forEach( function( v, k ) {
				if ( ! ECHOED[ k ] || '' === v || ( 'pagenum' === k && '1' === v ) || ( 'order' === k && 'asc' === v.toLowerCase() ) ) { return; }
				if ( String( saved.get( k ) || '' ).toLowerCase() !== v.toLowerCase() ) { ok = false; }
			} );
			return ok;
		}
		function sorted( u ) {
			var parts = [];
			u.searchParams.forEach( function( v, k ) { if ( '' !== v ) { parts.push( k + '=' + v ); } } );
			return parts.sort().join( '&' );
		}
		/* List + flash with no state of its own, arrived from this list or one of its editors: reapply the remembered
		 * state once. */
		function restore( here ) {
			var flash = false, carried = null, from, st, guard = null, target;
			if ( ! onlyParams( here.url, IDENTITY, $.extend( {}, FLASH, ECHOED ) ) ) { return false; }
			here.url.searchParams.forEach( function( v, k ) { if ( FLASH[ k ] && '' !== v ) { flash = true; } } );
			try { carried = store.getItem( 'ecv2_catalog_flash' ); } catch ( e ) {}
			if ( ! flash && ! carried ) { return false; }
			from = parse( document.referrer );
			if ( ! from || from.base !== here.base ) { return false; }
			st = read( here.key );
			if ( ! st || ! agrees( here.url, st ) ) { return false; }
			try { guard = JSON.parse( store.getItem( GUARD ) || 'null' ); } catch ( e ) {}
			if ( guard && guard.key === here.key && Date.now() - guard.t < 15000 ) { return false; }

			target = new URL( here.url.pathname + '?' + st.q, window.location.href );
			here.url.searchParams.forEach( function( v, k ) { if ( FLASH[ k ] ) { target.searchParams.set( k, v ); } } );
			if ( sorted( target ) === sorted( here.url ) ) { return false; }
			try { store.setItem( GUARD, JSON.stringify( { key: here.key, t: Date.now() } ) ); } catch ( e ) {}
			if ( carried ) {
				/* List scripts consume the toast on ready, which can run before this page unloads. */
				window.addEventListener( 'pagehide', function() { try { store.setItem( 'ecv2_catalog_flash', carried ); } catch ( e ) {} } );
			}
			window.wpEasyCartListRestoring = true;
			window.location.replace( target.toString() + here.url.hash );
			return true;
		}

		var here = parse( window.location.href );
		if ( ! here ) { return; }
		var form = document.getElementById( 'ecv2-posts-filter' );

		if ( form ) {
			var q = capture( here, form );
			if ( null === q ) { return; }
			if ( restore( here ) ) { return; }
			try {
				store.removeItem( GUARD );
				if ( '' === q ) {
					/* A bare list is a reset, unless it is only an action's landing page ( ?success= etc. ). */
					if ( onlyParams( here.url, IDENTITY ) ) { store.removeItem( here.key ); }
				} else {
					store.setItem( here.key, JSON.stringify( { q: q, t: Date.now() } ) );
				}
			} catch ( e ) {}
			return;
		}

		/* Not a list: an editor or other page of this section. The list URL a link or script should really go to. */
		function listUrl( href ) {
			var info = parse( href ), st;
			if ( ! info || info.base !== here.base || info.url.hash || ! onlyParams( info.url, IDENTITY, ECHOED ) ) { return null; }
			st = read( info.key );
			return ( st && agrees( info.url, st ) ) ? info.url.pathname + '?' + st.q : null;
		}
		function relink( a ) {
			var raw, target;
			if ( ! a || ! a.getAttribute || a.hasAttribute( 'data-ecv2-list-fresh' ) ) { return; }
			raw = a.getAttribute( 'href' );
			if ( ! raw || '#' === raw.charAt( 0 ) || /^\s*javascript:/i.test( raw ) ) { return; }
			target = listUrl( a.href );
			if ( target && target !== raw ) { a.setAttribute( 'href', target ); }
		}
		/* Content area and breadcrumb, plus the editors' back arrows wherever a template puts them. */
		var SCOPE = '.ecsh-content a[href], .ecsh-tb-crumb a[href], a.ecdv2-header-back[href], a.ecv2-od-back[href], a[data-ecv2-list-back][href]';
		$( function() { $( SCOPE ).each( function() { relink( this ); } ); } );
		/* Links rendered later ( drawers, JS headers ): fix them just before they are used. */
		$( document ).on( 'mousedown focusin click', SCOPE, function() { relink( this ); } );
		/* Buttons that navigate back in script: <button data-ecv2-list-back="admin.php?page=…&subpage=…">. */
		$( document ).on( 'click', 'button[data-ecv2-list-back], [role="button"][data-ecv2-list-back]', function( e ) {
			var href = this.getAttribute( 'data-ecv2-list-back' );
			if ( ! href ) { return; }
			e.preventDefault();
			window.location.href = listUrl( href ) || href;
		} );
		/* For scripts that build a list URL themselves ( cancel / after-delete redirects ). */
		window.ecv2_list_url = function( href ) { return listUrl( href ) || href; };
	} )();

} )( jQuery );
/*
 * 6.0.0: list views. The V2 lists render the table, card and spreadsheet views at once ( two hidden ), each with
 * its own bulk[] checkbox per row. Keep every copy of a row in step, count a row once, and submit only the copies in
 * the visible view, so unticking a row in one view cannot leave it selected in a hidden one ( "Export Selected"
 * used to export everything for that reason ).
 */
( function( $ ) {
	/* Unique ids among checked row boxes ( every list script counts through this ). */
	window.ecv2_row_check_count = function() {
		var seen = {}, n = 0;
		$( '.ecv2-row-check:checked' ).each( function() { if ( ! seen[ this.value ] ) { seen[ this.value ] = true; n++; } } );
		return n;
	};
	/* Capture phase: runs before the list scripts' delegated change handlers, so their counts see the synced state. */
	document.addEventListener( 'change', function( e ) {
		var el = e.target;
		if ( ! el || ! el.classList || ! el.classList.contains( 'ecv2-row-check' ) ) { return; }
		var scope = el.form || document;
		var boxes = scope.querySelectorAll( '.ecv2-row-check' );
		for ( var i = 0; i < boxes.length; i++ ) {
			if ( boxes[ i ] !== el && boxes[ i ].value === el.value ) { boxes[ i ].checked = el.checked; }
		}
	}, true );
	/* One copy per id in the request: hidden views' boxes are dropped from the submission. */
	$( document ).on( 'submit', '#ecv2-posts-filter', function() {
		$( this ).find( '.ecv2-view:hidden input[name="bulk[]"]' ).prop( 'disabled', true );
		var $form = $( this );
		setTimeout( function() { $form.find( '.ecv2-view input[name="bulk[]"]' ).prop( 'disabled', false ); }, 1000 );
	} );
} )( jQuery );

/* ---------- Editor rail as a mobile slide-out ( 6.0.0 ) ----------
   Every V2 editor ( product, option set, category, user, role, subscription plan, settings lists ) puts its section
   links and health card in .ecdv2-rail beside .ecdv2-main. On a phone that stack ate the top of the screen, so the
   rail becomes a panel that slides in from the left behind a burger button added to the editor header. Desktop keeps
   the sticky column: everything here is driven by the body class and the CSS media query. */
( function( $ ) {
	'use strict';
	$( function() {
		var $rail = $( '.ecdv2-rail' ).first();
		var $header = $( '.ecdv2-header' ).first();
		if ( ! $rail.length || $header.find( '.ecdv2-rail-toggle' ).length ) {
			return;
		}
		/* The CSS hides the rail off screen from the first paint. Without a header there is nowhere to put the burger, so
		   hand that page its column back instead. */
		if ( ! $header.length ) {
			$( 'body' ).addClass( 'ecdv2-rail-static' );
			return;
		}
		var L = window.ecv2_shell_lang || {};
		var T = L.sections || 'Sections';
		var close_label = L.close || 'Close';
		var $toggle = $( '<button type="button" class="ecdv2-rail-toggle" aria-expanded="false"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg><span class="screen-reader-text"></span></button>' );
		$toggle.attr( 'title', T ).find( '.screen-reader-text' ).text( T );
		var $back = $header.find( '.ecdv2-header-back' ).first();
		if ( $back.length ) { $back.after( $toggle ); } else { $header.prepend( $toggle ); }

		$( 'body' ).addClass( 'ecdv2-rail-ready' ); /* from here the panel may animate; on load it is simply off screen */
		var $scrim = $( '<div class="ecdv2-rail-scrim" hidden></div>' ).appendTo( 'body' );

		/* .ecv2-wrap is a CSS container ( container-type:inline-size ), which makes it the containing block for fixed
		   children: the panel was then as tall as the editor, not the window. On a phone the rail moves to <body> so it
		   can fill the screen, and it goes back into the editor for the sticky column on a wider screen. */
		var $railHome = $( '<span class="ecdv2-rail-home" hidden></span>' ).insertBefore( $rail );
		function place_rail() {
			var narrow = window.innerWidth <= 782;
			if ( narrow && $rail.parent()[0] !== document.body ) {
				$rail.appendTo( 'body' );
			} else if ( ! narrow && $rail.parent()[0] === document.body ) {
				$railHome.after( $rail );
			}
		}
		place_rail();
		$rail.prepend( '<div class="ecdv2-rail-head"><b></b><button type="button" class="ecdv2-rail-close" aria-label=""><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div>' );
		$rail.find( '.ecdv2-rail-head b' ).text( T );
		$rail.find( '.ecdv2-rail-close' ).attr( 'aria-label', close_label );

		function open_rail( on ) {
			$( 'body' ).toggleClass( 'ecdv2-rail-open', on );
			$toggle.attr( 'aria-expanded', on ? 'true' : 'false' );
			$scrim.prop( 'hidden', ! on );
			if ( on ) { $rail.find( '.ecdv2-rail-close' ).trigger( 'focus' ); } else { $toggle.trigger( 'focus' ); }
		}
		$toggle.on( 'click', function() { open_rail( ! $( 'body' ).hasClass( 'ecdv2-rail-open' ) ); return false; } );
		$scrim.on( 'click', function() { open_rail( false ); } );
		$rail.on( 'click', '.ecdv2-rail-close', function() { open_rail( false ); return false; } );
		/* Picking a section is the end of the trip: let the jump happen, then get out of the way. */
		$rail.on( 'click', '.ecdv2-rail-link, .ecdv2-tab, a[href^="#"]', function() {
			if ( $( 'body' ).hasClass( 'ecdv2-rail-open' ) ) { setTimeout( function() { open_rail( false ); }, 60 ); }
		} );
		$( document ).on( 'keydown', function( e ) {
			if ( 'Escape' === e.key && $( 'body' ).hasClass( 'ecdv2-rail-open' ) ) { open_rail( false ); }
		} );
		/* Back on a wide screen the rail is a column again; drop the open state so nothing is left fixed. */
		$( window ).on( 'resize.ecdv2rail', function() {
			place_rail();
			if ( window.innerWidth > 782 && $( 'body' ).hasClass( 'ecdv2-rail-open' ) ) {
				$( 'body' ).removeClass( 'ecdv2-rail-open' );
				$scrim.prop( 'hidden', true );
				$toggle.attr( 'aria-expanded', 'false' );
			}
		} );
	} );
} )( jQuery );
