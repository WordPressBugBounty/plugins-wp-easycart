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
				$player.replaceWith( '<iframe id="wp_easycart_admin_help_video_player" allow="autoplay; encrypted-media; fullscreen" allowfullscreen frameborder="0"></iframe>' );
			}
		}
		$( document ).on( 'click', 'a[data-ecsh-video]', function( e ) {
			if ( e.metaKey || e.ctrlKey || e.shiftKey || 1 === e.button ) {
				return; /* honor open-in-new-tab */
			}
			e.preventDefault();
			ecshEnsureVideoIframe();
			if ( 'function' === typeof window.wp_easycart_admin_open_video_help ) {
				window.wp_easycart_admin_open_video_help( $( this ).attr( 'data-ecsh-video' ) );
			} else {
				$( '#wp_easycart_admin_help_video_player' ).attr( 'src', 'https://www.youtube.com/embed/' + $( this ).attr( 'data-ecsh-video' ) + '?enablejsapi=1&widgetid=1' );
				$( '.ec_admin_help_video_container' ).show();
			}
			$( '.ecsh-tb-menu' ).removeClass( 'ecsh-open' );
		} );
		$( document ).on( 'click', '.ec_admin_help_video_container .ec_admin_upsell_popup_close a', function() {
			$( '#wp_easycart_admin_help_video_player' ).attr( 'src', '' );
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

} )( jQuery );