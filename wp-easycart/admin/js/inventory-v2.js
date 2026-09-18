/* 6.0.0: rows are rendered once per view mode; count an id once ( shared definition lives in shell-v2.js ). */window.ecv2_row_check_count = window.ecv2_row_check_count || function() { var seen = {}, n = 0; jQuery( '.ecv2-row-check:checked' ).each( function() { if ( ! seen[ this.value ] ) { seen[ this.value ] = true; n++; } } ); return n; };
/**
 * WP EasyCart Admin — Inventory V2
 *
 * Self-contained behaviors for the modern inventory grid. The markup is
 * produced by wp_easycart_admin_table_v2 / wp_easycart_admin_inventory_table,
 * which reference a handful of global functions ( ecv2_* ). Those shared
 * shell functions are defined here with typeof guards so this file can
 * coexist with products-v2.js if both are ever enqueued together.
 *
 * PRO extends this file via inventory-v2-pro.js ( adjust modal, history,
 * reorder points, bulk update, import ).
 */

( function( $ ) {
	'use strict';

	/* ------------------------------------------------------------------ */
	/* Shared shell: toasts                                                 */
	/* ------------------------------------------------------------------ */

	if ( typeof window.ecv2_toast !== 'function' ) {
		window.ecv2_toast = function( message, type ) {
			var $container = $( '#ecv2-toast-container' );
			if ( ! $container.length ) {
				$container = $( '<div id="ecv2-toast-container"></div>' ).appendTo( 'body' );
			}
			var $toast = $( '<div class="ecv2-toast ecv2-toast-' + ( type || 'info' ) + '"></div>' ).text( message );
			$container.append( $toast );
			setTimeout( function() { $toast.addClass( 'ecv2-toast-show' ); }, 10 );
			setTimeout( function() {
				$toast.removeClass( 'ecv2-toast-show' );
				setTimeout( function() { $toast.remove(); }, 300 );
			}, 3200 );
		};
	}

	/* ------------------------------------------------------------------ */
	/* Shared shell: confirm dialog                                         */
	/* ------------------------------------------------------------------ */

	var ecv2_confirm_resolver = null;

	if ( typeof window.ecv2_show_confirm !== 'function' ) {
		window.ecv2_show_confirm = function( title, message ) {
			return new Promise( function( resolve ) {
				ecv2_confirm_resolver = resolve;
				$( '#ecv2-confirm-title' ).text( title );
				$( '#ecv2-confirm-message' ).text( message );
				$( '#ecv2-confirm-dialog' ).show();
			} );
		};
	}
	if ( typeof window.ecv2_confirm_ok !== 'function' ) {
		window.ecv2_confirm_ok = function() {
			$( '#ecv2-confirm-dialog' ).hide();
			if ( ecv2_confirm_resolver ) { ecv2_confirm_resolver( true ); ecv2_confirm_resolver = null; }
		};
	}
	if ( typeof window.ecv2_confirm_cancel !== 'function' ) {
		window.ecv2_confirm_cancel = function() {
			$( '#ecv2-confirm-dialog' ).hide();
			if ( ecv2_confirm_resolver ) { ecv2_confirm_resolver( false ); ecv2_confirm_resolver = null; }
		};
	}

	/* ------------------------------------------------------------------ */
	/* Shared shell: row menus                                              */
	/* ------------------------------------------------------------------ */

	if ( typeof window.ecv2_toggle_row_menu !== 'function' ) {
		window.ecv2_toggle_row_menu = function( trigger ) {
			var $menu = $( trigger ).siblings( '.ecv2-row-menu' );
			var was_open = $menu.hasClass( 'ecv2-row-menu-open' );
			$( '.ecv2-row-menu' ).removeClass( 'ecv2-row-menu-open' );
			if ( ! was_open ) {
				$menu.addClass( 'ecv2-row-menu-open' );
				/* Flip upward when the menu would clip the viewport bottom. */
				var rect = $menu[0].getBoundingClientRect();
				$menu.toggleClass( 'ecv2-row-menu-up', rect.bottom > $( window ).height() - 10 );
			}
		};
	}
	$( document ).on( 'click', function( e ) {
		if ( ! $( e.target ).closest( '.ecv2-row-menu-wrap, .ecv2i-qty-wrap, .ecv2i-threshold-wrap' ).length ) {
			$( '.ecv2-row-menu' ).removeClass( 'ecv2-row-menu-open' );
			ecv2i_close_qty_pops();
			$( '#ecv2i-threshold-pop' ).hide();
		}
	} );

	/* ------------------------------------------------------------------ */
	/* Shared shell: filter drawer                                          */
	/* ------------------------------------------------------------------ */

	function ecv2i_form() {
		return $( '#ecv2-posts-filter' );
	}
	function ecv2i_reset_pagenum() {
		ecv2i_form().find( 'input[name="pagenum"]' ).val( 1 );
	}

	if ( typeof window.ecv2_open_filter_drawer !== 'function' ) {
		window.ecv2_open_filter_drawer = function() {
			$( '#ecv2-filter-drawer' ).addClass( 'ecv2-drawer-open' );
			$( '#ecv2-drawer-backdrop' ).addClass( 'ecv2-backdrop-visible' );
		};
	}
	function ecv2i_close_drawer() {
		$( '#ecv2-filter-drawer' ).removeClass( 'ecv2-drawer-open' );
		$( '#ecv2-drawer-backdrop' ).removeClass( 'ecv2-backdrop-visible' );
	}
	$( document ).on( 'click', '#ecv2-drawer-close, #ecv2-drawer-backdrop', ecv2i_close_drawer );

	/* Pill selection inside the drawer ( applied on Show Results ). */
	$( document ).on( 'click', '.ecv2-drawer-pill[data-filter]', function() {
		$( this ).closest( '.ecv2-drawer-pills' ).find( '.ecv2-drawer-pill' ).removeClass( 'ecv2-drawer-pill-active' );
		$( this ).addClass( 'ecv2-drawer-pill-active' );
	} );
	$( document ).on( 'click', '.ecv2-drawer-health-pill', function() {
		$( this ).closest( '.ecv2-drawer-pills' ).find( '.ecv2-drawer-pill' ).removeClass( 'ecv2-drawer-pill-active' );
		$( this ).addClass( 'ecv2-drawer-pill-active' );
	} );

	if ( typeof window.ecv2_apply_drawer_filters !== 'function' ) {
		window.ecv2_apply_drawer_filters = function() {
			/* Copy drawer state into the hidden form inputs, then submit. */
			$( '.ecv2-drawer-pill-active[data-filter]' ).each( function() {
				$( '#ecv2-filter-input-' + $( this ).data( 'filter' ).toString().replace( 'filter_', '' ) ).val( $( this ).data( 'value' ) );
			} );
			var $health = $( '.ecv2-drawer-health-pill.ecv2-drawer-pill-active' );
			if ( $health.length ) {
				$( '#ecv2-health-filter-input' ).val( $health.data( 'health-value' ) );
			}
			$( '.ecv2-drawer-select' ).each( function() {
				$( '#ecv2-filter-input-' + $( this ).data( 'filter' ).toString().replace( 'filter_', '' ) ).val( $( this ).val() );
			} );
			ecv2i_reset_pagenum();
			ecv2i_form().trigger( 'submit' );
		};
	}

	if ( typeof window.ecv2_clear_filters !== 'function' ) {
		window.ecv2_clear_filters = function() {
			$( 'input[id^="ecv2-filter-input-"]' ).val( '' );
			$( '#ecv2-health-filter-input' ).val( '' );
			ecv2i_reset_pagenum();
			ecv2i_form().trigger( 'submit' );
		};
	}

	/* Single-filter clear buttons + active tag removal ( toolbar tags and
	 * empty-state chips share the same markup; the empty state adds a
	 * 'search' key for the search-term chip ). */
	$( document ).on( 'click', '.ecv2-drawer-filter-clear[data-filter], .ecv2-active-tag-remove', function() {
		var filter = $( this ).data( 'filter' );
		if ( 'search' === filter ) {
			$( '#ecv2-search-input' ).val( '' );
		} else if ( 'health_filter' === filter ) {
			$( '#ecv2-health-filter-input' ).val( '' );
		} else if ( filter ) {
			$( '#ecv2-filter-input-' + filter.toString().replace( 'filter_', '' ) ).val( '' );
		}
		ecv2i_reset_pagenum();
		ecv2i_form().trigger( 'submit' );
	} );

	/* Clear everything at once: search term, quick filter, drawer filters. */
	window.ecv2i_clear_all = function() {
		$( '#ecv2-search-input' ).val( '' );
		$( 'input[id^="ecv2-filter-input-"]' ).val( '' );
		$( '#ecv2-health-filter-input' ).val( '' );
		ecv2i_reset_pagenum();
		ecv2i_form().trigger( 'submit' );
	};
	$( document ).on( 'click', '.ecv2-drawer-health-clear', function() {
		$( '#ecv2-health-filter-input' ).val( '' );
		ecv2i_reset_pagenum();
		ecv2i_form().trigger( 'submit' );
	} );

	/* ------------------------------------------------------------------ */
	/* Shared shell: stat cards + visibility toggles                        */
	/* ------------------------------------------------------------------ */

	/* Stat tiles are the list's quick filters, as on the other V2 lists: clicking one filters
	 * ( clicking the active one clears it ), drops the search term so the two don't combine,
	 * and starts again at page one. Keyboard: the tile is a button, so Enter / Space activate it. */
	function ecv2i_stat_filter( card ) {
		var $card = $( card );
		if ( $card.hasClass( 'ecv2-stat-static' ) ) { return; }
		var filter = $card.data( 'filter' );
		var current = $( '#ecv2-health-filter-input' ).val();
		$( '#ecv2-health-filter-input' ).val( ( current === filter ) ? '' : filter );
		$( '#ecv2-search-input' ).val( '' );
		ecv2i_reset_pagenum();
		ecv2i_form().trigger( 'submit' );
	}
	$( document ).on( 'click', '.ecv2-stat-card', function() {
		ecv2i_stat_filter( this );
	} );
	$( document ).on( 'keydown', '.ecv2-stat-card[role="button"]', function( e ) {
		if ( 'Enter' === e.key || ' ' === e.key || 'Spacebar' === e.key ) {
			e.preventDefault();
			ecv2i_stat_filter( this );
		}
	} );

	$( document ).on( 'click', '#ecv2-stat-toggle-btn', function( e ) {
		e.stopPropagation();
		$( '#ecv2-stat-toggle-panel' ).toggleClass( 'ecv2-stat-toggle-panel-open' );
	} );
	$( document ).on( 'change', '.ecv2-stat-toggle-cb', function() {
		var $panel = $( '#ecv2-stat-toggle-panel' );
		var hidden = [];
		var $all = $panel.find( '.ecv2-stat-toggle-cb[data-stat-key="__all"]' );
		if ( $all.is( ':checked' ) ) {
			hidden = [ '__all' ];
			$panel.find( '.ecv2-stat-toggle-individual' ).prop( 'disabled', true );
			$( '#ecv2-health-dashboard' ).addClass( 'ecv2-stats-all-hidden' );
			$( '.ecv2-stat-card' ).addClass( 'ecv2-stat-hidden' );
		} else {
			$panel.find( '.ecv2-stat-toggle-individual' ).prop( 'disabled', false );
			$( '#ecv2-health-dashboard' ).removeClass( 'ecv2-stats-all-hidden' );
			$panel.find( '.ecv2-stat-toggle-individual' ).each( function() {
				var key = $( this ).data( 'stat-key' );
				var visible = $( this ).is( ':checked' );
				$( '.ecv2-stat-card[data-stat-key="' + key + '"]' ).toggleClass( 'ecv2-stat-hidden', ! visible );
				if ( ! visible ) {
					hidden.push( key );
				}
			} );
		}
		$.post( wpeasycart_admin_ajax_object.ajax_url, {
			action: 'ecv2_save_stat_visibility',
			table_id: $( '#ecv2-stat-toggle-table-id' ).val(),
			hidden: hidden,
			wp_easycart_nonce: $( '#ecv2-stat-toggle-nonce' ).val()
		} );
	} );

	/* ------------------------------------------------------------------ */
	/* Shared shell: search + pagination                                    */
	/* ------------------------------------------------------------------ */

	$( document ).on( 'keydown', '#ecv2-search-input', function( e ) {
		if ( 'Enter' === e.key ) {
			e.preventDefault();
			ecv2i_reset_pagenum();
			ecv2i_form().trigger( 'submit' );
		}
	} );
	$( document ).on( 'click', '#ecv2-search-submit', function() {
		ecv2i_reset_pagenum();
		ecv2i_form().trigger( 'submit' );
	} );
	$( document ).on( 'click', '#ecv2-search-clear', function() {
		$( '#ecv2-search-input' ).val( '' );
		ecv2i_reset_pagenum();
		ecv2i_form().trigger( 'submit' );
	} );
	$( document ).on( 'change', '.ecv2-perpage-select', function() {
		ecv2i_reset_pagenum();
		ecv2i_form().trigger( 'submit' );
	} );
	$( document ).on( 'keydown', '.ecv2-page-input', function( e ) {
		if ( 'Enter' === e.key ) {
			e.preventDefault();
			ecv2i_form().trigger( 'submit' );
		}
	} );

	/* ------------------------------------------------------------------ */
	/* Shared shell: row selection                                          */
	/* ------------------------------------------------------------------ */

	function ecv2i_refresh_selection() {
		var count = ecv2_row_check_count();
		$( '#ecv2-selected-count' ).text( count );
		$( '.ecv2-bulk-count' ).toggle( count > 0 );
		$( '#ecv2i-bulk-btn' ).toggle( count > 0 );
	}
	$( document ).on( 'change', '#ecv2-select-all', function() {
		$( '.ecv2-row-check' ).prop( 'checked', $( this ).is( ':checked' ) );
		ecv2i_refresh_selection();
	} );
	$( document ).on( 'change', '.ecv2-row-check', ecv2i_refresh_selection );

	window.ecv2i_selected_row_keys = function() {
		return $( '.ecv2-row-check:checked' ).map( function() { return $( this ).val(); } ).get().filter( function( v, i, a ) { return a.indexOf( v ) === i; } );
	};

	/* ------------------------------------------------------------------ */
	/* Inventory: quantity popover                                          */
	/* ------------------------------------------------------------------ */

	function ecv2i_close_qty_pops() {
		$( '.ecv2i-qty-pop' ).removeClass( 'ecv2i-pop-open' );
	}

	window.ecv2i_open_qty = function( trigger ) {
		var $wrap = $( trigger ).closest( '.ecv2i-qty-wrap' );
		var $pop = $wrap.find( '.ecv2i-qty-pop' );
		var was_open = $pop.hasClass( 'ecv2i-pop-open' );
		ecv2i_close_qty_pops();
		if ( ! was_open ) {
			var tracked = parseInt( $wrap.attr( 'data-tracked' ), 10 ) === 1;
			$pop.toggleClass( 'is-tracked', tracked ).toggleClass( 'is-untracked', ! tracked ).addClass( 'ecv2i-pop-open' );
			$pop.find( '.ecv2i-mode-btn' ).removeClass( 'is-on' ).filter( '[data-mode="set"]' ).addClass( 'is-on' );
			$pop.find( '.ecv2i-qty-input' ).val( $wrap.attr( 'data-qty' ) ).attr( 'data-original', $wrap.attr( 'data-qty' ) );
			$pop.find( '.ecv2i-pop-preview' ).text( '' );
			$pop.find( '.ecv2i-pop-extra' ).val( '' );
			ecv2i_clear_field_error( $pop.find( '.ecv2i-pop-extra' ) );
			$pop.find( tracked ? '.ecv2i-qty-input' : '.ecv2i-track-input' ).trigger( 'focus' ).trigger( 'select' );
			/* keep the popover on screen: flip left when it would overflow the table */
			var r = $pop[0].getBoundingClientRect(); $pop.toggleClass( 'is-flip', r.right > window.innerWidth - 12 );
		}
	};
	window.ecv2i_menu_stop_tracking = function( trigger ) {
		var $row = $( trigger ).closest( 'tr' ); $( trigger ).closest( '.ecv2-row-menu' ).removeClass( 'ecv2-row-menu-open' );
		ecv2i_stop_tracking( $row.find( '.ecv2i-qty-wrap' ) );
	};
	function ecv2i_stop_tracking( $wrap ) {
		var go = function() { ecv2i_post_qty( $wrap, { tracked: 0 }, ecv2i_lang.tracking_stopped ); };
		if ( window.ecv2_show_confirm ) { ecv2_show_confirm( ecv2i_lang.stop_confirm_t, ecv2i_lang.stop_confirm_b ).then( function( ok ) { if ( ok ) { go(); } } ); } else if ( window.confirm( ecv2i_lang.stop_confirm_t ) ) { go(); }
	}
	/* Set / Add / Remove → the value actually sent */
	function ecv2i_target_qty( $wrap ) {
		var $pop = $wrap.find( '.ecv2i-qty-pop' ), mode = $pop.find( '.ecv2i-mode-btn.is-on' ).data( 'mode' ) || 'set', v = parseInt( $pop.find( '.ecv2i-qty-input' ).val(), 10 ), cur = parseInt( $wrap.attr( 'data-qty' ), 10 ) || 0;
		if ( isNaN( v ) ) { return NaN; }
		if ( mode === 'add' ) { return cur + v; } if ( mode === 'remove' ) { return Math.max( 0, cur - v ); } return Math.max( 0, v );
	}
	function ecv2i_preview( $wrap ) {
		var $pop = $wrap.find( '.ecv2i-qty-pop' ), mode = $pop.find( '.ecv2i-mode-btn.is-on' ).data( 'mode' ) || 'set', v = parseInt( $pop.find( '.ecv2i-qty-input' ).val(), 10 ), cur = parseInt( $wrap.attr( 'data-qty' ), 10 ) || 0, t = ecv2i_target_qty( $wrap ), $pv = $pop.find( '.ecv2i-pop-preview' );
		if ( isNaN( t ) ) { $pv.text( '' ); return; }
		var f = function( str, a, b, c ) { return str.replace( '%1$s', a ).replace( '%2$s', b ).replace( '%3$s', c ).replace( '%s', a ); };
		$pv.text( mode === 'set' ? ( t === cur ? '' : f( ecv2i_lang.preview_set, t ) ) : f( mode === 'add' ? ecv2i_lang.preview_add : ecv2i_lang.preview_remove, cur, isNaN( v ) ? 0 : v, t ) ).toggleClass( 'is-out', t <= 0 ).toggleClass( 'is-low', t > 0 && t <= ( window.ecv2i_config ? ecv2i_config.threshold : 0 ) );
	}
	$( document ).on( 'click', '.ecv2i-mode-btn', function() { var $pop = $( this ).closest( '.ecv2i-qty-pop' ); $pop.find( '.ecv2i-mode-btn' ).removeClass( 'is-on' ); $( this ).addClass( 'is-on' ); var $in = $pop.find( '.ecv2i-qty-input' ); $in.val( $( this ).data( 'mode' ) === 'set' ? $( this ).closest( '.ecv2i-qty-wrap' ).attr( 'data-qty' ) : 1 ).trigger( 'focus' ).trigger( 'select' ); ecv2i_preview( $( this ).closest( '.ecv2i-qty-wrap' ) ); } );
	$( document ).on( 'input change', '.ecv2i-qty-input', function() { ecv2i_preview( $( this ).closest( '.ecv2i-qty-wrap' ) ); } );
	$( document ).on( 'click', '.ecv2i-stop-tracking', function( e ) { e.preventDefault(); ecv2i_stop_tracking( $( this ).closest( '.ecv2i-qty-wrap' ) ); } );
	$( document ).on( 'click', '.ecv2i-track-start', function() { var $wrap = $( this ).closest( '.ecv2i-qty-wrap' ), v = parseInt( $wrap.find( '.ecv2i-track-input' ).val(), 10 ); if ( isNaN( v ) || v < 0 ) { $wrap.find( '.ecv2i-track-input' ).addClass( 'is-invalid' ).trigger( 'focus' ); return; } ecv2i_post_qty( $wrap, { quantity: v, tracked: 1 }, ecv2i_lang.tracking_started ); } );
	$( document ).on( 'keydown', '.ecv2i-track-input', function( e ) { if ( 'Enter' === e.key ) { e.preventDefault(); $( this ).closest( '.ecv2i-pop-row' ).find( '.ecv2i-track-start' ).trigger( 'click' ); } if ( 'Escape' === e.key ) { ecv2i_close_qty_pops(); } } );
	/* Clicking a derived cell ( Available / Committed, added by PRO ) opens the same popover — the number itself is the entry point. */
	$( document ).on( 'click', 'td.ecv2-cell-available, td.ecv2-cell-committed, .ecv2i-available-link', function( e ) { if ( $( e.target ).is( 'a[href]:not(.ecv2i-available-link)' ) ) { return; } var $btn = $( this ).closest( 'tr' ).find( '.ecv2i-qty-badge-btn' ); if ( $btn.length ) { e.preventDefault(); ecv2i_open_qty( $btn[0] ); } } );
	/* Generic saver: sends quantity and/or tracked, then re-renders the pill and the row's derived cells.
	 * ok_msg may be a string or a function( response.data ) returning the toast text. The core keys
	 * ( action, ids, nonce ) always win over anything passed in extra. */
	function ecv2i_post_qty( $wrap, extra, ok_msg ) {
		$.ajax( { url: wpeasycart_admin_ajax_object.ajax_url, type: 'POST', data: $.extend( {}, extra, { action: 'ecv2_inventory_set_qty', product_id: $wrap.data( 'product-id' ), oiq_id: $wrap.data( 'oiq-id' ), wp_easycart_nonce: ecv2i_nonces.set_qty } ),
			success: function( response ) {
				if ( response.success ) { window.ecv2i_render_badge( $wrap, parseInt( response.data.quantity, 10 ), parseInt( response.data.threshold, 10 ), response.data.tracked ); ecv2i_close_qty_pops(); ecv2_toast( ( 'function' === typeof ok_msg ? ok_msg( response.data ) : ok_msg ) || ecv2i_lang.saved, 'success' ); $( document ).trigger( 'ecv2i:qty-changed', [ $wrap, response.data ] ); return; }
				var message = ( response.data && response.data.message ) ? response.data.message : ecv2i_lang.error;
				/* A refusal naming a field ( PRO: reason required ) is shown on that field, not as a toast. */
				var $field = ( response.data && response.data.field ) ? $wrap.find( '.ecv2i-pop-tracked .ecv2i-pop-extra[name="' + String( response.data.field ).replace( /[^a-z0-9_-]/gi, '' ) + '"]' ) : $();
				if ( $field.length && $wrap.find( '.ecv2i-qty-pop' ).hasClass( 'ecv2i-pop-open' ) ) { ecv2i_field_error( $field, message ); } else { ecv2_toast( message, 'error' ); }
			},
			error: function() { ecv2_toast( ecv2i_lang.error, 'error' ); } } );
	}

	function ecv2i_fmt( str, a, b ) {
		return String( str || '' ).replace( '%1$s', a ).replace( '%2$s', b ).replace( '%s', a );
	}

	/* Toast for a popover save, worded by what actually happened server-side. */
	function ecv2i_saved_message( data ) {
		var qty = parseInt( data.quantity, 10 ), delta = parseInt( data.delta, 10 ) || 0;
		if ( 'add' === data.mode || 'remove' === data.mode ) {
			if ( delta > 0 && ecv2i_lang.saved_add ) { return ecv2i_fmt( ecv2i_lang.saved_add, delta, qty ); }
			if ( delta < 0 && ecv2i_lang.saved_remove ) { return ecv2i_fmt( ecv2i_lang.saved_remove, -delta, qty ); }
			return ecv2i_lang.saved_none ? ecv2i_fmt( ecv2i_lang.saved_none, qty ) : ecv2i_lang.saved;
		}
		return ecv2i_lang.saved_set ? ecv2i_fmt( ecv2i_lang.saved_set, qty ) : ecv2i_lang.saved;
	}

	/* Fields other code ( PRO: reason ) prints into the popover: any .ecv2i-pop-extra[name] is posted. */
	function ecv2i_pop_extra( $pop ) {
		var out = {};
		$pop.find( '.ecv2i-pop-tracked .ecv2i-pop-extra[name]' ).each( function() {
			out[ $( this ).attr( 'name' ) ] = $( this ).val();
		} );
		return out;
	}

	/* Inline field error: marks the field, focuses it and prints the message under it. */
	function ecv2i_field_error( $field, message ) {
		$field = $field.first();
		var id = $field.attr( 'id' ) ? $field.attr( 'id' ) + '-error' : '';
		var $error = $field.siblings( '.ecv2i-pop-error' );
		if ( ! $error.length ) {
			$error = $( '<p class="ecv2i-pop-error" role="alert"></p>' );
			if ( id ) { $error.attr( 'id', id ); }
			$field.after( $error );
		}
		$error.text( message || ecv2i_lang.error ).prop( 'hidden', false );
		$field.addClass( 'is-invalid' ).attr( 'aria-invalid', 'true' );
		if ( id ) { $field.attr( 'aria-describedby', id ); }
		$field.trigger( 'focus' );
	}
	function ecv2i_clear_field_error( $fields ) {
		$fields.removeClass( 'is-invalid' ).removeAttr( 'aria-invalid' ).siblings( '.ecv2i-pop-error' ).prop( 'hidden', true ).text( '' );
	}
	window.ecv2i_field_error = ecv2i_field_error;
	window.ecv2i_clear_field_error = ecv2i_clear_field_error;

	/* A required extra field ( PRO: reason ) without a value blocks the save. Returns true when all are filled. */
	function ecv2i_pop_required_ok( $pop ) {
		var $missing = $pop.find( '.ecv2i-pop-tracked .ecv2i-pop-extra[required]' ).filter( function() {
			return '' === String( $( this ).val() || '' );
		} );
		if ( $missing.length ) {
			ecv2i_field_error( $missing, $missing.first().attr( 'data-required-message' ) );
			return false;
		}
		return true;
	}
	$( document ).on( 'change', '.ecv2i-qty-pop .ecv2i-pop-extra', function() {
		if ( '' !== String( $( this ).val() || '' ) ) { ecv2i_clear_field_error( $( this ) ); }
	} );
	$( document ).on( 'keydown', '.ecv2i-qty-pop .ecv2i-pop-extra', function( e ) {
		if ( 'Enter' === e.key ) { e.preventDefault(); ecv2i_save_qty( $( this ).closest( '.ecv2i-qty-wrap' ) ); }
		if ( 'Escape' === e.key ) { ecv2i_close_qty_pops(); }
	} );

	window.ecv2i_menu_set_qty = function( trigger ) {
		var $row = $( trigger ).closest( 'tr' );
		$( trigger ).closest( '.ecv2-row-menu' ).removeClass( 'ecv2-row-menu-open' );
		var $btn = $row.find( '.ecv2i-qty-badge-btn' );
		if ( $btn.length ) {
			window.ecv2i_open_qty( $btn[0] );
		}
	};

	$( document ).on( 'click', '.ecv2i-step', function() {
		var $input = $( this ).closest( '.ecv2i-pop-row' ).find( 'input[type="number"]' );
		var value = parseInt( $input.val(), 10 );
		if ( isNaN( value ) ) { value = 0; }
		$input.val( value + parseInt( $( this ).data( 'step' ), 10 ) ).trigger( 'change' );
	} );

	function ecv2i_badge_class( qty, threshold ) {
		if ( qty <= 0 ) { return 'ecv2-stock-out'; }
		if ( qty <= threshold ) { return 'ecv2-stock-low'; }
		return 'ecv2-stock-ok';
	}

	window.ecv2i_render_badge = function( $wrap, qty, threshold, tracked ) {
		if ( tracked === undefined ) { tracked = parseInt( $wrap.attr( 'data-tracked' ), 10 ) === 1; } else { tracked = parseInt( tracked, 10 ) === 1; }
		$wrap.attr( 'data-qty', qty ).data( 'qty', qty ).attr( 'data-tracked', tracked ? 1 : 0 );
		var $b = $wrap.find( '.ecv2-stock-badge' );
		if ( ! tracked ) { $b.attr( 'class', 'ecv2-stock-badge ecv2-stock-unlimited' ).html( '&infin; ' + ecv2i_lang.unlimited ); }
		else { var label = qty <= 0 ? ecv2i_lang.out : ecv2i_lang.in_stock.replace( '%s', qty ) + ( qty <= threshold ? ' \u00b7 ' + ecv2i_lang.low : '' ); $b.attr( 'class', 'ecv2-stock-badge ' + ecv2i_badge_class( qty, threshold ) ).text( label ); }
		$wrap.find( '.ecv2i-qty-badge-btn' ).attr( 'title', tracked ? 'Change the quantity on hand' : 'Stock isn\u2019t tracked for this item \u2014 click to start tracking' );
		$wrap.find( '.ecv2i-qty-input' ).val( qty ).attr( 'data-original', qty );
		/* derived cells ( PRO adds them ): available = on hand − committed */
		var $tr = $wrap.closest( 'tr' ), committed = parseInt( $tr.find( 'td.ecv2-cell-committed' ).text().replace( /[^0-9-]/g, '' ), 10 );
		var $av = $tr.find( 'td.ecv2-cell-available' ); if ( $av.length ) { $av.html( tracked ? '<span class="ecv2i-available-link">' + Math.max( 0, qty - ( isNaN( committed ) ? 0 : committed ) ) + '</span>' : '<span class="ecv2-stock-badge ecv2-stock-unlimited">&infin;</span>' ); }
		/* row menu: swap the tracking items */
		var $menu = $tr.find( '.ecv2-row-menu' ); if ( $menu.length ) { $menu.find( '.ecv2-row-menu-item' ).filter( function() { return /Change quantity|Track stock|Stop tracking/.test( $( this ).text() ); } ).remove(); var $edit = $menu.find( '.ecv2-row-menu-item' ).first(); if ( tracked ) { $edit.after( '<a href="#" class="ecv2-row-menu-item" onclick="ecv2i_menu_stop_tracking( this ); return false;"><span class="dashicons dashicons-dismiss"></span> Stop tracking stock</a>' ).after( '<a href="#" class="ecv2-row-menu-item" onclick="ecv2i_menu_set_qty( this ); return false;"><span class="dashicons dashicons-edit"></span> Change quantity\u2026</a>' ); } else { $edit.after( '<a href="#" class="ecv2-row-menu-item" onclick="ecv2i_menu_set_qty( this ); return false;"><span class="dashicons dashicons-plus-alt2"></span> Track stock\u2026</a>' ); } }
	};

	/* Set sends the absolute quantity; Add / Remove send the amount so the server applies ( and logs ) a delta. */
	function ecv2i_save_qty( $wrap ) {
		var $pop = $wrap.find( '.ecv2i-qty-pop' );
		var mode = $pop.find( '.ecv2i-mode-btn.is-on' ).data( 'mode' ) || 'set';
		var amount = parseInt( $pop.find( '.ecv2i-qty-input' ).val(), 10 );
		var qty = ecv2i_target_qty( $wrap );
		if ( isNaN( qty ) || isNaN( amount ) ) { ecv2_toast( ecv2i_lang.error, 'error' ); return; }
		if ( ! ecv2i_pop_required_ok( $pop ) ) { return; }
		if ( 'set' !== mode && amount < 0 ) {
			/* A negative amount ( the − stepper past zero ) means the opposite direction. */
			mode = ( 'add' === mode ) ? 'remove' : 'add';
			amount = -amount;
		}
		ecv2i_post_qty( $wrap, $.extend( ecv2i_pop_extra( $pop ), { mode: mode, quantity: qty, amount: amount } ), ecv2i_saved_message );
	}

	$( document ).on( 'click', '.ecv2i-qty-save', function() {
		ecv2i_save_qty( $( this ).closest( '.ecv2i-qty-wrap' ) );
	} );
	$( document ).on( 'keydown', '.ecv2i-qty-input', function( e ) {
		if ( 'Enter' === e.key ) {
			e.preventDefault();
			ecv2i_save_qty( $( this ).closest( '.ecv2i-qty-wrap' ) );
		}
		if ( 'Escape' === e.key ) {
			ecv2i_close_qty_pops();
		}
	} );

	/* The low stock threshold is one store-wide setting, edited in
	   Settings > Checkout > Stock alerts. The header shows it and links there. */

	/* ------------------------------------------------------------------ */
	/* PRO gates ( overridden by inventory-v2-pro.js when live )            */
	/* ------------------------------------------------------------------ */

	window.ecv2i_show_locked = function( feature ) {
		if ( typeof window.ecdv2_upsell === 'function' ) {
			window.ecdv2_upsell( { context: 'inventory', feature: feature || '' } );
			return false;
		}
		ecv2_toast( ecv2i_lang.pro_required, 'info' );
		return false;
	};

	if ( typeof window.ecv2i_open_bulk !== 'function' ) {
		window.ecv2i_open_bulk = function() {
			window.ecv2i_show_locked();
		};
	}

} )( jQuery );