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

	$( document ).on( 'click', '.ecv2-stat-card', function() {
		var filter = $( this ).data( 'filter' );
		var current = $( '#ecv2-health-filter-input' ).val();
		$( '#ecv2-health-filter-input' ).val( ( current === filter ) ? '' : filter );
		ecv2i_reset_pagenum();
		ecv2i_form().trigger( 'submit' );
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
		var count = $( '.ecv2-row-check:checked' ).length;
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
		return $( '.ecv2-row-check:checked' ).map( function() { return $( this ).val(); } ).get();
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
			$pop.addClass( 'ecv2i-pop-open' );
			$pop.find( '.ecv2i-qty-input' ).trigger( 'focus' ).trigger( 'select' );
		}
	};

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

	window.ecv2i_render_badge = function( $wrap, qty, threshold ) {
		var suffix = '';
		if ( qty <= 0 ) {
			suffix = ' \u00b7 ' + ecv2i_lang.out;
		} else if ( qty <= threshold ) {
			suffix = ' \u00b7 ' + ecv2i_lang.low;
		}
		$wrap.attr( 'data-qty', qty ).data( 'qty', qty );
		$wrap.find( '.ecv2-stock-badge' )
			.attr( 'class', 'ecv2-stock-badge ' + ecv2i_badge_class( qty, threshold ) )
			.text( qty + suffix );
		$wrap.find( '.ecv2i-qty-input' ).val( qty ).attr( 'data-original', qty );
	};

	function ecv2i_save_qty( $wrap ) {
		var $input = $wrap.find( '.ecv2i-qty-input' );
		var qty = parseInt( $input.val(), 10 );
		if ( isNaN( qty ) ) {
			ecv2_toast( ecv2i_lang.error, 'error' );
			return;
		}
		$.ajax( {
			url: wpeasycart_admin_ajax_object.ajax_url,
			type: 'POST',
			data: {
				action: 'ecv2_inventory_set_qty',
				product_id: $wrap.data( 'product-id' ),
				oiq_id: $wrap.data( 'oiq-id' ),
				quantity: qty,
				wp_easycart_nonce: ecv2i_nonces.set_qty
			},
			success: function( response ) {
				if ( response.success ) {
					window.ecv2i_render_badge( $wrap, parseInt( response.data.quantity, 10 ), parseInt( response.data.threshold, 10 ) );
					ecv2i_close_qty_pops();
					ecv2_toast( ecv2i_lang.saved, 'success' );
				} else {
					ecv2_toast( ( response.data && response.data.message ) ? response.data.message : ecv2i_lang.error, 'error' );
				}
			},
			error: function() {
				ecv2_toast( ecv2i_lang.error, 'error' );
			}
		} );
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

	/* ------------------------------------------------------------------ */
	/* Inventory: low stock threshold                                       */
	/* ------------------------------------------------------------------ */

	$( document ).on( 'click', '#ecv2i-threshold-btn', function( e ) {
		e.stopPropagation();
		$( '#ecv2i-threshold-pop' ).toggle();
	} );
	$( document ).on( 'click', '#ecv2i-threshold-save', function() {
		var threshold = parseInt( $( '#ecv2i-threshold-input' ).val(), 10 );
		if ( isNaN( threshold ) || threshold < 1 ) {
			ecv2_toast( ecv2i_lang.error, 'error' );
			return;
		}
		$.post( wpeasycart_admin_ajax_object.ajax_url, {
			action: 'ecv2_inventory_save_threshold',
			threshold: threshold,
			wp_easycart_nonce: ecv2i_nonces.save_threshold
		}, function( response ) {
			if ( response.success ) {
				ecv2_toast( ecv2i_lang.threshold_saved, 'success' );
				/* Badges + stats are threshold-derived: reload for accuracy. */
				window.location.reload();
			} else {
				ecv2_toast( ( response.data && response.data.message ) ? response.data.message : ecv2i_lang.error, 'error' );
			}
		} );
	} );

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