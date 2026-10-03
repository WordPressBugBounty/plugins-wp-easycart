/**
 * WP EasyCart — Settings › Checkout protection ( 6.0.2 ).
 *
 * The level explanation follows the level pills; the status card's extra-checks buttons, the paused list and the
 * activity list talk to ecv2_protection_* ( admin/inc/wp_easycart_admin_checkout_protection.php ); "Show a test check"
 * renders the shoppers' human check here and confirms the saved secret key.
 */
( function ( $ ) {
	'use strict';

	var cfg = window.ecv2_protection;
	if ( ! cfg ) {
		return;
	}

	function post( action, data ) {
		data = data || {};
		data.action = 'ecv2_protection_' + action;
		data.nonce = cfg.nonce;
		return $.ajax( { url: cfg.ajax_url, type: 'post', data: data, dataType: 'json' } );
	}

	/* 6.0.2: the V2 toast ( catalog-v2.js, loaded on every settings page ), the browser box only as a fallback. */
	function fail( res ) {
		var msg = ( res && res.data && res.data.message ) ? res.data.message : cfg.text.error;
		if ( 'function' === typeof window.ecv2_toast ) {
			window.ecv2_toast( msg, 'error' );
		} else {
			window.alert( msg ); // eslint-disable-line no-alert
		}
	}

	/* 6.0.2: ask with the V2 confirm dialog when it is there. */
	function ask( text, go ) {
		if ( 'function' === typeof window.ecv2_show_confirm ) {
			var asked = window.ecv2_show_confirm( cfg.text.block_title || '', text );
			if ( asked && 'function' === typeof asked.then ) {
				asked.then( function ( yes ) { if ( yes ) { go(); } } );
				return;
			}
		}
		if ( window.confirm( text ) ) { // eslint-disable-line no-alert
			go();
		}
	}

	$( function () {
		/* Level: the explanation under the pills follows the choice before it saves. */
		$( document ).on( 'change', '.ecst-row[data-key="ec_option_checkout_protection_level"] .ecst-input', function () {
			var level = String( this.value );
			if ( cfg.levels && cfg.levels[ level ] ) {
				$( '#ecpt-level-explain' ).html( cfg.levels[ level ] );
			}
		} );

		/* Extra checks: on, off, or held for 24 hours. */
		$( document ).on( 'click', '[data-ecpt-extra]', function () {
			var $b = $( this ).prop( 'disabled', true );
			post( 'extra', { mode: $b.attr( 'data-ecpt-extra' ) } ).done( function ( res ) {
				if ( res && res.success ) {
					window.location.reload();
				} else {
					$b.prop( 'disabled', false );
					fail( res );
				}
			} ).fail( function () {
				$b.prop( 'disabled', false );
				fail();
			} );
		} );

		/* Unpause one shopper, address, email or card. */
		$( document ).on( 'click', '[data-ecpt-unpause]', function () {
			var $b = $( this ).prop( 'disabled', true );
			post( 'unpause', { key: $b.attr( 'data-ecpt-unpause' ) } ).done( function ( res ) {
				if ( res && res.success ) {
					$b.replaceWith( $( '<span class="ecpt-done"></span>' ).text( cfg.text.unpaused ) );
				} else {
					$b.prop( 'disabled', false );
					fail( res );
				}
			} ).fail( function () {
				$b.prop( 'disabled', false );
				fail();
			} );
		} );

		/* Block from the activity list ( the hash goes on the block list; the page reloads to show it ). */
		$( document ).on( 'click', '[data-ecpt-block]', function () {
			var $b = $( this );
			ask( cfg.text.block_ask, function () {
				$b.prop( 'disabled', true );
				post( 'block', { key: $b.attr( 'data-ecpt-block' ), display: $b.attr( 'data-ecpt-display' ) || '' } ).done( function ( res ) {
					if ( res && res.success ) {
						$b.replaceWith( $( '<span class="ecpt-done"></span>' ).text( cfg.text.blocked ) );
					} else {
						$b.prop( 'disabled', false );
						fail( res );
					}
				} ).fail( function () {
					$b.prop( 'disabled', false );
					fail();
				} );
			} );
		} );

		/* Your address → the never-limit list ( marks the row changed; the save bar keeps it ). */
		$( document ).on( 'click', '[data-ecpt-add-ip]', function () {
			var ip = String( $( this ).attr( 'data-ecpt-add-ip' ) || '' );
			var $ta = $( '.ecst-row[data-key="ec_option_checkout_protection_allow_list"] .ecst-input' ).first();
			if ( ! ip || ! $ta.length ) {
				return;
			}
			var lines = String( $ta.val() || '' ).split( /\r?\n/ ).filter( function ( l ) { return '' !== l.trim(); } );
			var has = lines.some( function ( l ) { return 0 === l.trim().indexOf( ip ); } );
			if ( ! has ) {
				lines.push( ip );
				$ta.val( lines.join( '\n' ) ).trigger( 'input' );
			}
			$( this ).replaceWith( $( '<span class="ecpt-done"></span>' ).text( cfg.text.added_ip ) );
			$ta.trigger( 'focus' );
		} );

		/* Activity: filter and "Show more". */
		var filter = 'all';
		var shown = $( '#ecpt-activity tbody tr' ).length;
		function loadActivity( reset ) {
			var offset = reset ? 0 : shown;
			var $more = $( '#ecpt-more' ).prop( 'disabled', true );
			post( 'activity', { filter: filter, offset: offset } ).done( function ( res ) {
				if ( ! res || ! res.success ) {
					fail( res );
					return;
				}
				var $body = $( '#ecpt-activity tbody' );
				if ( reset ) {
					$body.html( res.data.html );
				} else {
					$body.append( res.data.html );
				}
				shown = res.data.shown;
				$( '#ecpt-count' ).text( res.data.count );
				$more.prop( 'hidden', shown >= res.data.total );
			} ).always( function () {
				$more.prop( 'disabled', false );
			} );
		}
		$( document ).on( 'click', '[data-ecpt-filter]', function () {
			filter = String( $( this ).attr( 'data-ecpt-filter' ) );
			$( '[data-ecpt-filter]' ).attr( 'aria-pressed', 'false' );
			$( this ).attr( 'aria-pressed', 'true' );
			loadActivity( true );
		} );
		$( document ).on( 'click', '#ecpt-more', function () {
			loadActivity( false );
		} );

		/* 6.0.2: the key status note, the test block and the key the test uses follow a save on this page ( they were the
		   page-load values until a reload ). */
		function refreshKeys() {
			post( 'keys', {} ).done( function ( res ) {
				if ( ! res || ! res.success ) {
					return;
				}
				var changed = res.data.site_key !== cfg.site_key || res.data.provider !== cfg.provider;
				cfg.site_key = res.data.site_key;
				cfg.provider = res.data.provider;
				$( '#ecpt-keys-status' ).html( res.data.status );
				$( '.ecpt-test' ).prop( 'hidden', ! cfg.site_key );
				if ( changed ) {
					$( '#ecpt-test-widget' ).empty();
					$( '#ecpt-test-result' ).removeClass( 'is-ok is-bad' ).text( '' );
				}
			} );
		}
		$( document ).on( 'ecst:saved', function ( e, d, keys ) {
			var watched = [ 'ec_option_checkout_protection_human', 'ec_option_checkout_protection_provider', 'ec_option_checkout_protection_turnstile_site_key', 'ec_option_checkout_protection_turnstile_secret_key' ];
			for ( var i = 0; i < ( keys || [] ).length; i++ ) {
				if ( -1 !== watched.indexOf( keys[ i ] ) ) {
					refreshKeys();
					return;
				}
			}
		} );

		/* Test the keys: the same check shoppers get, then the saved secret key confirms the pass. */
		$( document ).on( 'click', '#ecpt-test-keys', function () {
			var $result = $( '#ecpt-test-result' ).removeClass( 'is-ok is-bad' ).text( '' );
			if ( ! cfg.site_key ) {
				$result.addClass( 'is-bad' ).text( cfg.text.no_key );
				return;
			}
			/* 6.0.2: the button comes back once the test has an answer ( it stayed disabled until a reload ). */
			var $btn = $( this ).prop( 'disabled', true );
			var finish = function () {
				$btn.prop( 'disabled', false );
			};
			var holder = document.getElementById( 'ecpt-test-widget' );
			holder.innerHTML = '';
			var render = function () {
				var options = {
					sitekey: cfg.site_key,
					callback: function ( token ) {
						$result.text( cfg.text.testing );
						post( 'test_token', { token: token } ).done( function ( res ) {
							if ( res && res.success ) {
								$result.addClass( 'is-ok' ).text( cfg.text.test_ok );
								refreshKeys();
							} else {
								$result.addClass( 'is-bad' ).text( ( res && res.data && res.data.message ) ? res.data.message : cfg.text.error );
							}
						} ).fail( function () {
							$result.addClass( 'is-bad' ).text( cfg.text.error );
						} ).always( finish );
					},
					'error-callback': function () {
						$result.addClass( 'is-bad' ).text( cfg.text.error );
						finish();
					}
				};
				if ( 'recaptcha' === cfg.provider ) {
					window.grecaptcha.render( holder, options );
				} else {
					window.turnstile.render( holder, options );
				}
			};
			var ready = function () {
				return 'recaptcha' === cfg.provider ? !! ( window.grecaptcha && window.grecaptcha.render ) : !! ( window.turnstile && window.turnstile.render );
			};
			if ( ready() ) {
				render();
				return;
			}
			var s = document.createElement( 'script' );
			s.async = true;
			s.src = 'recaptcha' === cfg.provider ? 'https://www.google.com/recaptcha/api.js?render=explicit' : 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
			document.head.appendChild( s );
			var tries = 0;
			var wait = window.setInterval( function () {
				tries++;
				if ( ready() ) {
					window.clearInterval( wait );
					render();
				} else if ( tries > 100 ) {
					window.clearInterval( wait );
					$result.addClass( 'is-bad' ).text( cfg.text.error );
					finish();
				}
			}, 100 );
		} );
	} );
}( jQuery ) );
