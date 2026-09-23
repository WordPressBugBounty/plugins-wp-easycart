/* 6.0.0: rows are rendered once per view mode; count an id once ( shared definition lives in shell-v2.js ). */window.ecv2_row_check_count = window.ecv2_row_check_count || function() { var seen = {}, n = 0; jQuery( '.ecv2-row-check:checked' ).each( function() { if ( ! seen[ this.value ] ) { seen[ this.value ] = true; n++; } } ); return n; };
/**
 * WP EasyCart Admin — Customers List V2 (FREE).
 *
 * Self-contained script for the wp-easycart-users → accounts page. The V2
 * table base class (wp_easycart_admin_table_v2) emits markup that calls
 * several ecv2_* functions by name; products-v2.js is not loaded on this
 * page, so the framework behaviors that page needs are implemented here
 * with identical names and DOM contracts. Keep both in sync when the base
 * class markup changes.
 *
 * Localized objects:
 *  - ecv2_user_nonces { inline_update, bulk_edit, quick_edit }
 *  - ecv2_user_lang   { ...strings, pro_gate }
 *
 * PRO behaviors (snapshot, tags, guests) live in users-v2-pro.js, which
 * depends on this file and reuses ecv2_toast / ecv2_show_confirm / etc.
 *
 * @since 5.9.3
 */

jQuery( function( $ ) {
	'use strict';

	if ( ! $( '.ecv2-wrap[data-table-id="ec_admin_user_list_v2"]' ).length ) {
		return;
	}

	var ECV2_BULK_MAX = 500;
	var ECV2_BULK_LEGACY_MAX = 100; // Legacy GET pipeline — keep URLs sane.
	var ecv2_undo_stack = [];
	var ECV2_UNDO_MAX = 10;
	var ecv2_confirm_resolve = null;

	function _t( key, fallback ) {
		return ( typeof ecv2_user_lang !== 'undefined' && ecv2_user_lang[ key ] ) ? ecv2_user_lang[ key ] : fallback;
	}

	/* ================================================================== */
	/* Shared framework: toast / escape / confirm / undo                   */
	/* ================================================================== */

	function ecv2_toast( message, type ) {
		type = type || 'success';
		var icon = type === 'success' ? 'yes' : ( type === 'error' ? 'no' : 'info-outline' );
		var $toast = $( '<div class="ecv2-toast ecv2-toast-' + type + '"><span class="dashicons dashicons-' + icon + '"></span> <span></span></div>' );
		$toast.find( 'span' ).last().text( message );
		$( '#ecv2-toast-container' ).append( $toast );
		setTimeout( function() {
			$toast.fadeOut( 300, function() { $( this ).remove(); } );
		}, 3500 );
	}
	window.ecv2_toast = window.ecv2_toast || ecv2_toast;

	function ecv2_esc_html( str ) {
		if ( ! str ) { return ''; }
		var div = document.createElement( 'div' );
		div.appendChild( document.createTextNode( str ) );
		return div.innerHTML;
	}
	window.ecv2_esc_html = window.ecv2_esc_html || ecv2_esc_html;

	function ecv2_show_confirm( title, message ) {
		return new Promise( function( resolve ) {
			ecv2_confirm_resolve = resolve;
			$( '#ecv2-confirm-title' ).text( title );
			$( '#ecv2-confirm-message' ).text( message );
			$( '#ecv2-confirm-dialog' ).fadeIn( 200 );
		} );
	}
	window.ecv2_show_confirm = window.ecv2_show_confirm || ecv2_show_confirm;

	window.ecv2_confirm_ok = function() {
		$( '#ecv2-confirm-dialog' ).fadeOut( 200 );
		if ( ecv2_confirm_resolve ) { ecv2_confirm_resolve( true ); ecv2_confirm_resolve = null; }
	};
	window.ecv2_confirm_cancel = function() {
		$( '#ecv2-confirm-dialog' ).fadeOut( 200 );
		if ( ecv2_confirm_resolve ) { ecv2_confirm_resolve( false ); ecv2_confirm_resolve = null; }
	};
	$( document ).on( 'click', '#ecv2-confirm-dialog', function( e ) {
		if ( $( e.target ).is( '#ecv2-confirm-dialog' ) ) { window.ecv2_confirm_cancel(); return false; }
	} );

	function ecv2_push_undo( action_data ) {
		ecv2_undo_stack.push( action_data );
		if ( ecv2_undo_stack.length > ECV2_UNDO_MAX ) { ecv2_undo_stack.shift(); }
		$( '#ecv2-undo-message' ).text( action_data.message || _t( 'undo_available', 'Action completed. Undo available.' ) );
		$( '#ecv2-undo-bar' ).fadeIn( 200 );
		clearTimeout( window.ecv2_undo_timer );
		window.ecv2_undo_timer = setTimeout( function() { $( '#ecv2-undo-bar' ).fadeOut( 200 ); }, 15000 );
	}
	$( document ).on( 'click', '#ecv2-undo-button', function() {
		if ( ecv2_undo_stack.length === 0 ) { return; }
		var action = ecv2_undo_stack.pop();
		if ( action && action.undo_fn ) { action.undo_fn(); }
		if ( ecv2_undo_stack.length === 0 ) { $( '#ecv2-undo-bar' ).fadeOut( 200 ); }
	} );

	/* ================================================================== */
	/* Shared framework: view toggle, stat cards, stat visibility          */
	/* ================================================================== */

	$( document ).on( 'click', '.ecv2-view-btn', function() {
		var mode = $( this ).data( 'mode' );
		$( '.ecv2-view-btn' ).removeClass( 'ecv2-view-btn-active' );
		$( this ).addClass( 'ecv2-view-btn-active' );
		$( '.ecv2-view' ).hide();
		$( '.ecv2-view-' + mode ).show();
		$( '.ecv2-wrap' ).attr( 'data-view-mode', mode );
		document.cookie = 'wpeasycart_admin_view_mode=' + mode + ';path=/;max-age=31536000';
	} );

	$( document ).on( 'click', '.ecv2-stat-card', function() {
		var filter_val = $( this ).data( 'filter' );
		$( '#ecv2-health-filter-input' ).val( filter_val );
		$( '.ecv2-stat-card' ).removeClass( 'ecv2-stat-active' );
		if ( filter_val !== '' ) { $( this ).addClass( 'ecv2-stat-active' ); }
		$( '.ecv2-stat-card' ).addClass( 'ecv2-stat-loading-dim' );
		$( this ).removeClass( 'ecv2-stat-loading-dim' ).addClass( 'ecv2-stat-loading' );
		$( this ).find( '.ecv2-stat-value' ).html( '<span class="dashicons dashicons-update ecv2-spin ecv2-stat-spinner"></span>' );
		$( '#ecv2-search-input' ).val( '' );
		$( '#ecv2-posts-filter' ).submit();
	} );

	$( document ).on( 'click', '#ecv2-stat-toggle-btn', function() {
		var $panel = $( '#ecv2-stat-toggle-panel' );
		var expanded = $panel.hasClass( 'ecv2-stat-toggle-open' );
		$panel.toggleClass( 'ecv2-stat-toggle-open' );
		$( this ).attr( 'aria-expanded', ! expanded );
	} );

	$( document ).on( 'change', '.ecv2-stat-toggle-cb[data-stat-key="__all"]', function() {
		var hide_all = $( this ).is( ':checked' );
		if ( hide_all ) {
			$( '#ecv2-health-dashboard' ).addClass( 'ecv2-stats-all-hidden' );
			$( '.ecv2-stat-toggle-individual' ).prop( 'checked', false ).prop( 'disabled', true );
		} else {
			$( '#ecv2-health-dashboard' ).removeClass( 'ecv2-stats-all-hidden' );
			$( '.ecv2-stat-toggle-individual' ).prop( 'disabled', false ).prop( 'checked', true );
			$( '.ecv2-stat-card' ).removeClass( 'ecv2-stat-hidden' );
		}
		ecv2_save_stat_visibility();
	} );

	$( document ).on( 'change', '.ecv2-stat-toggle-individual', function() {
		var key = $( this ).data( 'stat-key' );
		var visible = $( this ).is( ':checked' );
		$( '.ecv2-stat-card[data-stat-key="' + key + '"]' ).toggleClass( 'ecv2-stat-hidden', ! visible );
		ecv2_save_stat_visibility();
	} );

	function ecv2_save_stat_visibility() {
		var hidden = [];
		if ( $( '.ecv2-stat-toggle-cb[data-stat-key="__all"]' ).is( ':checked' ) ) {
			hidden.push( '__all' );
		} else {
			$( '.ecv2-stat-toggle-individual' ).each( function() {
				if ( ! $( this ).is( ':checked' ) ) { hidden.push( $( this ).data( 'stat-key' ) ); }
			} );
		}
		var nonce = $( '#ecv2-stat-toggle-nonce' ).val();
		var table_id = $( '#ecv2-stat-toggle-table-id' ).val();
		if ( ! nonce || ! table_id ) { return; }
		$.post( ajaxurl, { action: 'ecv2_save_stat_visibility', wp_easycart_nonce: nonce, table_id: table_id, hidden: hidden } );
	}

	/* ================================================================== */
	/* Shared framework: filter drawer                                     */
	/* ================================================================== */

	var ecv2_drawer_filter_snapshot = {};

	function ecv2_capture_filter_state() {
		var state = {};
		$( '[id^="ecv2-filter-input-"]' ).each( function() { state[ this.id ] = $( this ).val() || ''; } );
		state['ecv2-health-filter-input'] = $( '#ecv2-health-filter-input' ).val() || '';
		return state;
	}

	function ecv2_filters_changed( before, after ) {
		var all_keys = {}, key;
		for ( key in before ) { if ( before.hasOwnProperty( key ) ) { all_keys[ key ] = true; } }
		for ( key in after ) { if ( after.hasOwnProperty( key ) ) { all_keys[ key ] = true; } }
		for ( key in all_keys ) {
			if ( all_keys.hasOwnProperty( key ) && ( before[ key ] || '' ) !== ( after[ key ] || '' ) ) { return true; }
		}
		return false;
	}

	function ecv2_show_filter_loader() {
		if ( ! $( '#ecv2-filter-overlay' ).length ) {
			$( 'body' ).append(
				'<div id="ecv2-filter-overlay" class="ecv2-filter-overlay"><div class="ecv2-filter-overlay-inner">' +
				'<span class="dashicons dashicons-update ecv2-spin ecv2-filter-overlay-spinner"></span>' +
				'<span class="ecv2-filter-overlay-text">' + ecv2_esc_html( _t( 'applying_filters', 'Applying filters…' ) ) + '</span>' +
				'</div></div>'
			);
		}
		$( '#ecv2-filter-overlay' ).addClass( 'ecv2-filter-overlay-visible' );
	}

	$( document ).on( 'change', '.ecv2-perpage-select', function() {
		ecv2_show_filter_loader();
		$( this ).closest( 'form' ).submit();
	} );

	function ecv2_sync_range_inputs() {
		$( '.ecv2-drawer-range' ).each( function() {
			var $apply_btn = $( this ).find( '.ecv2-drawer-range-apply' );
			if ( $apply_btn.length ) {
				var filter_name = $apply_btn.data( 'filter' );
				var min_val = $( this ).find( '.ecv2-drawer-range-input[data-range="min"]' ).val() || '';
				var max_val = $( this ).find( '.ecv2-drawer-range-input[data-range="max"]' ).val() || '';
				var combined = min_val + '-' + max_val;
				if ( combined === '-' ) { combined = ''; }
				$( '#ecv2-filter-input-' + String( filter_name ).replace( 'filter_', '' ) ).val( combined );
			}
		} );
	}

	window.ecv2_open_filter_drawer = function() {
		ecv2_drawer_filter_snapshot = ecv2_capture_filter_state();
		$( '#ecv2-filter-drawer, #ecv2-drawer-backdrop' ).addClass( 'ecv2-drawer-open' );
		ecv2_init_filter_selects();
	};

	window.ecv2_close_filter_drawer = function( force_apply ) {
		ecv2_sync_range_inputs();
		var changed = ecv2_filters_changed( ecv2_drawer_filter_snapshot, ecv2_capture_filter_state() );
		$( '#ecv2-filter-drawer, #ecv2-drawer-backdrop' ).removeClass( 'ecv2-drawer-open' );
		if ( changed || force_apply ) {
			ecv2_show_filter_loader();
			$( '#ecv2-posts-filter' ).submit();
		}
	};

	window.ecv2_apply_drawer_filters = function() {
		ecv2_sync_range_inputs();
		$( '#ecv2-filter-drawer, #ecv2-drawer-backdrop' ).removeClass( 'ecv2-drawer-open' );
		ecv2_show_filter_loader();
		$( '#ecv2-posts-filter' ).submit();
	};

	window.ecv2_clear_filters = function() {
		$( '[id^="ecv2-filter-input-"]' ).val( '' );
		$( '#ecv2-health-filter-input' ).val( '' );
		$( '.ecv2-filter-select' ).val( '' );
		if ( typeof $.fn.select2 === 'function' ) { $( '.ecv2-filter-select' ).trigger( 'change.select2' ); }
		$( '.ecv2-drawer-range-input' ).val( '' );
		$( '.ecv2-stat-card' ).removeClass( 'ecv2-stat-active' );
		$( '#ecv2-search-input' ).val( '' );
		ecv2_show_filter_loader();
		$( '#ecv2-posts-filter' ).submit();
	};

	$( document ).on( 'click', '#ecv2-drawer-close, #ecv2-drawer-backdrop', function() {
		window.ecv2_close_filter_drawer();
	} );
	$( document ).on( 'keydown', function( e ) {
		if ( e.key === 'Escape' && $( '#ecv2-filter-drawer' ).hasClass( 'ecv2-drawer-open' ) ) {
			window.ecv2_close_filter_drawer();
		}
	} );

	$( document ).on( 'click', '.ecv2-drawer-pill:not(.ecv2-drawer-health-pill)', function() {
		var filter_name = $( this ).data( 'filter' );
		var filter_val = $( this ).data( 'value' );
		$( this ).siblings( '.ecv2-drawer-pill' ).removeClass( 'ecv2-drawer-pill-active' );
		$( this ).addClass( 'ecv2-drawer-pill-active' );
		$( '#ecv2-filter-input-' + String( filter_name ).replace( 'filter_', '' ) ).val( filter_val );
	} );

	$( document ).on( 'click', '.ecv2-drawer-health-pill', function() {
		var val = $( this ).data( 'health-value' );
		$( '.ecv2-drawer-health-pill' ).removeClass( 'ecv2-drawer-pill-active' );
		$( this ).addClass( 'ecv2-drawer-pill-active' );
		$( '#ecv2-health-filter-input' ).val( val );
		$( '.ecv2-stat-card' ).removeClass( 'ecv2-stat-active' );
		if ( val !== '' ) { $( '.ecv2-stat-card[data-filter="' + val + '"]' ).addClass( 'ecv2-stat-active' ); }
		var $group = $( this ).closest( '.ecv2-drawer-filter-group' );
		var $label = $group.find( '.ecv2-drawer-filter-label' );
		if ( val !== '' ) {
			$group.addClass( 'ecv2-drawer-filter-group-active' );
			if ( ! $label.find( '.ecv2-drawer-health-clear' ).length ) {
				$label.append( '<button type="button" class="ecv2-drawer-filter-clear ecv2-drawer-health-clear" title="' + ecv2_esc_html( _t( 'clear_filter', 'Clear this filter' ) ) + '"><span class="dashicons dashicons-dismiss"></span></button>' );
			}
		} else {
			$group.removeClass( 'ecv2-drawer-filter-group-active' );
			$label.find( '.ecv2-drawer-health-clear' ).remove();
		}
	} );

	$( document ).on( 'click', '.ecv2-drawer-health-clear', function() {
		$( '#ecv2-health-filter-input' ).val( '' );
		$( '.ecv2-drawer-health-pill' ).removeClass( 'ecv2-drawer-pill-active' );
		$( '.ecv2-drawer-health-pill[data-health-value=""]' ).addClass( 'ecv2-drawer-pill-active' );
		$( '.ecv2-stat-card' ).removeClass( 'ecv2-stat-active' );
		$( this ).closest( '.ecv2-drawer-filter-group' ).removeClass( 'ecv2-drawer-filter-group-active' );
		$( this ).remove();
	} );

	$( document ).on( 'click', '.ecv2-drawer-filter-clear:not(.ecv2-drawer-health-clear)', function() {
		var filter_name = $( this ).data( 'filter' );
		$( '#ecv2-filter-input-' + String( filter_name ).replace( 'filter_', '' ) ).val( '' );
		var $group = $( this ).closest( '.ecv2-drawer-filter-group' );
		$group.find( '.ecv2-drawer-pill' ).removeClass( 'ecv2-drawer-pill-active' );
		$group.find( '.ecv2-drawer-pill[data-value=""]' ).addClass( 'ecv2-drawer-pill-active' );
		$group.find( '.ecv2-filter-select' ).val( '' );
		if ( typeof $.fn.select2 === 'function' ) { $group.find( '.ecv2-filter-select' ).trigger( 'change.select2' ); }
		$group.find( '.ecv2-drawer-range-input' ).val( '' );
		$group.removeClass( 'ecv2-drawer-filter-group-active' );
		$( this ).remove();
	} );

	$( document ).on( 'click', '.ecv2-drawer-range-apply', function() {
		var filter_name = $( this ).data( 'filter' );
		var $group = $( this ).closest( '.ecv2-drawer-filter-group' );
		var min_val = $group.find( '.ecv2-drawer-range-input[data-range="min"]' ).val() || '';
		var max_val = $group.find( '.ecv2-drawer-range-input[data-range="max"]' ).val() || '';
		var combined = min_val + '-' + max_val;
		if ( combined === '-' ) { combined = ''; }
		$( '#ecv2-filter-input-' + String( filter_name ).replace( 'filter_', '' ) ).val( combined );
	} );

	$( document ).on( 'click', '.ecv2-active-tag-remove', function() {
		var filter_name = $( this ).data( 'filter' );
		if ( filter_name === 'health_filter' ) {
			$( '#ecv2-health-filter-input' ).val( '' );
			$( '.ecv2-stat-card' ).removeClass( 'ecv2-stat-active' );
		} else {
			$( '#ecv2-filter-input-' + String( filter_name ).replace( 'filter_', '' ) ).val( '' );
		}
		ecv2_show_filter_loader();
		$( '#ecv2-posts-filter' ).submit();
	} );

	$( document ).on( 'change', '.ecv2-filter-select', function() {
		var filter_name = $( this ).data( 'filter' );
		var filter_val = $( this ).val() || '';
		$( '#' + String( filter_name ).replace( 'filter_', 'ecv2-filter-input-' ) ).val( filter_val );
		if ( ! $( '#ecv2-filter-drawer' ).hasClass( 'ecv2-drawer-open' ) ) {
			$( '#ecv2-posts-filter' ).submit();
		}
	} );

	function ecv2_init_filter_selects() {
		if ( typeof $.fn.select2 !== 'function' ) { return; }
		$( '.ecv2-filter-select' ).each( function() {
			if ( ! $( this ).data( 'select2' ) ) {
				var $select = $( this );
				var opts = {
					width: '240px',
					allowClear: true,
					placeholder: $select.data( 'placeholder' ) || '',
					minimumResultsForSearch: 10
				};
				var $drawer = $select.closest( '#ecv2-filter-drawer' );
				if ( $drawer.length ) { opts.dropdownParent = $drawer; }
				$select.select2( opts );
			}
		} );
	}

	/* ================================================================== */
	/* Shared framework: search box                                        */
	/* ================================================================== */

	function ecv2_search_submit() {
		$( '#ecv2-search-clear' ).hide();
		$( '#ecv2-search-loading' ).show();
		$( '#ecv2-search-submit' ).prop( 'disabled', true );
		$( '#ecv2-search-input' ).prop( 'readonly', true ).addClass( 'ecv2-search-loading-state' );
		$( '#ecv2-posts-filter' ).submit();
	}
	$( document ).on( 'keydown', '#ecv2-search-input', function( e ) {
		if ( e.key === 'Enter' ) { e.preventDefault(); ecv2_search_submit(); }
	} );
	$( document ).on( 'input', '#ecv2-search-input', function() {
		$( '#ecv2-search-clear' ).toggle( $( this ).val().length > 0 );
	} );
	$( document ).on( 'click', '#ecv2-search-submit', ecv2_search_submit );
	$( document ).on( 'click', '#ecv2-search-clear', function() {
		$( '#ecv2-search-input' ).val( '' );
		$( '#ecv2-search-clear' ).hide();
		ecv2_search_submit();
	} );

	/* ================================================================== */
	/* Shared framework: selection + row menus + ss column picker          */
	/* ================================================================== */

	$( document ).on( 'change', '#ecv2-select-all, #ecv2-ss-select-all', function() {
		var checked = $( this ).is( ':checked' );
		$( '.ecv2-row-check' ).prop( 'checked', checked );
		ecv2_update_bulk_count();
	} );
	$( document ).on( 'change', '.ecv2-row-check', ecv2_update_bulk_count );

	function ecv2_update_bulk_count() {
		var count = ecv2_row_check_count();
		if ( count > 0 ) {
			$( '#ecv2-selected-count' ).text( count );
			$( '.ecv2-bulk-count' ).show();
			$( '#ecv2-bulk-edit-btn' ).show();
		} else {
			$( '.ecv2-bulk-count' ).hide();
			$( '#ecv2-bulk-edit-btn' ).hide();
		}
	}

	function ecv2_bulk_reset_selection() {
		$( '.ecv2-row-check, #ecv2-select-all, #ecv2-ss-select-all' ).prop( 'checked', false );
		ecv2_update_bulk_count();
	}

	window.ecv2_toggle_row_menu = function( trigger ) {
		var $menu = $( trigger ).siblings( '.ecv2-row-menu' );
		$( '.ecv2-row-menu' ).not( $menu ).removeClass( 'ecv2-row-menu-open ecv2-menu-fixed' ).css( { top: '', right: '', left: '' } );
		$( '.ecv2-card' ).removeClass( 'ecv2-card-menu-active' );

		if ( $( trigger ).closest( '.ecv2-card, .ecv2-table, .ecv2-spreadsheet' ).length ) {
			var rect = trigger.getBoundingClientRect();
			$menu.addClass( 'ecv2-menu-fixed' );
			$menu.css( { top: ( rect.bottom + 4 ) + 'px', right: ( window.innerWidth - rect.right ) + 'px', left: 'auto' } );
			$menu.toggleClass( 'ecv2-row-menu-open' );
			if ( $menu.hasClass( 'ecv2-row-menu-open' ) ) {
				$( trigger ).closest( '.ecv2-card' ).addClass( 'ecv2-card-menu-active' );
				var menu_rect = $menu[0].getBoundingClientRect();
				if ( menu_rect.bottom > window.innerHeight - 8 ) {
					var above_top = rect.top - menu_rect.height - 4;
					if ( above_top > 8 ) { $menu.css( { top: above_top + 'px' } ); }
				}
			}
		} else {
			$menu.removeClass( 'ecv2-menu-fixed' ).css( { top: '', right: '', left: '' } );
			$menu.toggleClass( 'ecv2-row-menu-open' );
		}
	};

	$( document ).on( 'click', function( e ) {
		if ( ! $( e.target ).closest( '.ecv2-row-menu-wrap' ).length ) {
			$( '.ecv2-row-menu' ).removeClass( 'ecv2-row-menu-open ecv2-menu-fixed' ).css( { top: '', right: '', left: '' } );
			$( '.ecv2-card' ).removeClass( 'ecv2-card-menu-active' );
		}
	} );
	$( window ).on( 'scroll', function() {
		$( '.ecv2-menu-fixed' ).removeClass( 'ecv2-row-menu-open ecv2-menu-fixed' ).css( { top: '', right: '', left: '' } );
		$( '.ecv2-card' ).removeClass( 'ecv2-card-menu-active' );
	} );

	window.ecv2_toggle_ss_column_picker = function() {
		var $picker = $( '#ecv2-ss-col-picker' );
		if ( $picker.is( ':visible' ) ) {
			$picker.hide();
			$( document ).off( 'click.ecv2-ss-col-picker' );
		} else {
			$picker.show();
			setTimeout( function() {
				$( document ).on( 'click.ecv2-ss-col-picker', function( e ) {
					if ( ! $( e.target ).closest( '#ecv2-ss-col-picker, #ecv2-ss-col-toggle-btn' ).length ) {
						$picker.hide();
						$( document ).off( 'click.ecv2-ss-col-picker' );
					}
				} );
			}, 10 );
		}
	};

	window.ecv2_toggle_ss_column = function( col_name, visible ) {
		$( '.ecv2-ss-col-' + col_name ).toggle( visible );
		var hidden = [];
		$( '#ecv2-ss-col-picker input[type="checkbox"]' ).each( function() {
			if ( ! this.checked && ! this.disabled ) { hidden.push( $( this ).data( 'ss-col' ) ); }
		} );
		// Separate cookie from the product list so preferences don't clash.
		document.cookie = 'wpeasycart_ss_hidden_cols_users=' + hidden.join( ',' ) + ';path=/;max-age=31536000';
	};

	$( document ).ready( function() {
		var match = document.cookie.match( /wpeasycart_ss_hidden_cols_users=([^;]*)/ );
		if ( match ) {
			var hidden = match[1] ? match[1].split( ',' ) : [];
			$( '#ecv2-ss-col-picker input[type="checkbox"]' ).each( function() {
				var col = $( this ).data( 'ss-col' );
				if ( ! col || this.disabled ) { return; }
				var should_hide = hidden.indexOf( col ) !== -1;
				$( this ).prop( 'checked', ! should_hide );
				$( '.ecv2-ss-col-' + col ).toggle( ! should_hide );
			} );
		}
	} );

	/* Escape clears selection when nothing else is open. */
	$( document ).on( 'keydown', function( e ) {
		if ( e.key !== 'Escape' ) { return; }
		if ( $( '.ecv2-modal-overlay:visible, .ecv2-slideout-overlay:visible' ).length ) { return; }
		if ( $( e.target ).is( 'input, textarea, select, [contenteditable="true"]' ) ) { return; }
		if ( ecv2_row_check_count() > 0 ) { ecv2_bulk_reset_selection(); }
	} );

	/* ================================================================== */
	/* Bulk actions — validation + legacy GET pipeline                     */
	/* ================================================================== */

	function ecv2_bulk_collect_ids() {
		var ids = [];
		$( '.ecv2-row-check:checked' ).each( function() { var v = $( this ).val(); if ( ids.indexOf( v ) === -1 ) { ids.push( v ); } } );
		return ids;
	}

	window.ecv2_bulk_apply_validate = function( btn ) {
		var $select = $( '#ecv2-bulk-action' );
		var action = $select.val();

		if ( ! action ) {
			$select.addClass( 'ecv2-bulk-highlight' );
			setTimeout( function() { $select.removeClass( 'ecv2-bulk-highlight' ); }, 1500 );
			ecv2_toast( _t( 'bulk_no_action', 'Please select a bulk action.' ), 'error' );
			return;
		}

		// Export All needs no selection; the exporter honors current filters.
		if ( action === 'export-accounts-csv-all' ) {
			$( 'input[name="bulk[]"]' ).prop( 'checked', false );
			$( btn ).closest( 'form' ).submit();
			return;
		}

		var ids = ecv2_bulk_collect_ids();
		if ( ids.length === 0 ) {
			ecv2_toast( _t( 'bulk_none_selected', 'Please select customers first.' ), 'error' );
			return;
		}
		if ( ids.length > ECV2_BULK_LEGACY_MAX ) {
			ecv2_toast( _t( 'bulk_max_exceeded', 'You can only process up to 100 customers at a time with this action. Please narrow your selection.' ), 'error' );
			return;
		}

		if ( action === 'delete-account' ) {
			var msg = ids.length === 1
				? _t( 'bulk_confirm_delete_one', 'Permanently delete this customer account? Their addresses are removed too. Orders are kept. This cannot be undone.' )
				: _t( 'bulk_confirm_delete_many', 'Permanently delete %d customer accounts? Their addresses are removed too. Orders are kept. This cannot be undone.' ).replace( '%d', ids.length );
			ecv2_show_confirm( _t( 'bulk_confirm_delete_title', 'Delete customers?' ), msg ).then( function( ok ) {
				if ( ok ) { $( btn ).closest( 'form' ).submit(); }
			} );
			return;
		}

		if ( action === 'accounts-force-password-reset' ) {
			var reset_msg = _t( 'bulk_confirm_reset', 'Invalidate the current password for %d customer(s) and email each a reset link?' ).replace( '%d', ids.length );
			ecv2_show_confirm( _t( 'bulk_confirm_reset_title', 'Force password reset?' ), reset_msg ).then( function( ok ) {
				if ( ok ) { $( btn ).closest( 'form' ).submit(); }
			} );
			return;
		}

		// Everything else (export selected, resend activation, PRO-registered
		// actions) rides the nonce-protected legacy GET pipeline unchanged.
		$( btn ).closest( 'form' ).submit();
	};

	/**
	 * Row-level shortcut into a bulk-only legacy action (e.g. single
	 * password reset): check just this row and submit the form with the
	 * action set, so the existing bulk nonce + handler do the work.
	 */
	window.ecv2_user_row_bulk = function( user_id, action, needs_confirm ) {
		var run = function() {
			ecv2_bulk_reset_selection();
			$( '.ecv2-row-check[value="' + user_id + '"]' ).first().prop( 'checked', true );
			$( '#ecv2-bulk-action' ).val( action );
			$( '#ecv2-posts-filter' ).submit();
		};
		if ( needs_confirm && action === 'accounts-force-password-reset' ) {
			ecv2_show_confirm(
				_t( 'row_confirm_reset_title', 'Send password reset?' ),
				_t( 'row_confirm_reset', 'This invalidates the customer\u2019s current password immediately and emails them a reset link. Continue?' )
			).then( function( ok ) { if ( ok ) { run(); } } );
			return;
		}
		run();
	};

	/* ================================================================== */
	/* Bulk Edit modal (role / subscriber) — AJAX                          */
	/* ================================================================== */

	window.ecv2_open_bulk_edit = function() {
		var count = ecv2_row_check_count();
		$( '#ecv2-bulk-edit-count' ).text( '(' + count + ' ' + _t( 'customers', 'customers' ) + ')' );
		$( '#ecv2-bulk-edit-modal' ).fadeIn( 200 );
	};
	window.ecv2_close_bulk_edit = function() {
		$( '#ecv2-bulk-edit-modal' ).fadeOut( 200 );
	};

	window.ecv2_apply_bulk_edit = function() {
		var user_ids = ecv2_bulk_collect_ids();
		if ( user_ids.length === 0 ) {
			ecv2_toast( _t( 'bulk_none_selected', 'Please select customers first.' ), 'error' );
			return;
		}
		if ( user_ids.length > ECV2_BULK_MAX ) {
			ecv2_toast( _t( 'bulk_max_exceeded_500', 'You can only process up to 500 customers at a time.' ), 'error' );
			return;
		}

		var data = {
			action: 'ecv2_user_bulk_edit',
			user_ids: user_ids,
			wp_easycart_nonce: ecv2_user_nonces.bulk_edit
		};

		/*
		 * Generic field collection: every base-rendered bulk edit control has
		 * an id of ecv2-be-{field}. Reading them generically means PRO fields
		 * registered through the wp_easycart_admin_user_list_bulk_edit_fields
		 * filter flow through with zero extra JS. -mode/-value composite ids
		 * (price/number types) are skipped — the user table doesn't use them.
		 */
		var has_change = false;
		$( '#ecv2-bulk-edit-modal select[id^="ecv2-be-"], #ecv2-bulk-edit-modal input[id^="ecv2-be-"]' ).each( function() {
			var field = this.id.replace( 'ecv2-be-', '' );
			if ( /-(mode|value)$/.test( field ) ) { return; }
			var val = $( this ).val();
			if ( val !== '' && val !== null && val !== undefined ) {
				data[ field ] = val;
				has_change = true;
			}
		} );

		if ( ! has_change ) {
			ecv2_toast( _t( 'no_changes', 'No changes selected.' ), 'info' );
			return;
		}

		$.ajax( {
			url: wpeasycart_admin_ajax_object.ajax_url,
			type: 'POST',
			dataType: 'json',
			data: data,
			success: function( response ) {
				if ( response.success ) {
					ecv2_toast( response.data.message || _t( 'saved', 'Saved successfully.' ), 'success' );
					window.ecv2_close_bulk_edit();
					setTimeout( function() { location.reload(); }, 900 );
				} else {
					ecv2_toast( ( response.data && response.data.message ) || _t( 'error', 'An error occurred. Please try again.' ), 'error' );
				}
			},
			error: function() { ecv2_toast( _t( 'error', 'An error occurred. Please try again.' ), 'error' ); }
		} );
	};

	/* ================================================================== */
	/* Spreadsheet inline editing: first_name / last_name                  */
	/* ================================================================== */

	$( document ).on( 'dblclick', '.ecv2-ss-cell.ecv2-ss-editable', function() {
		var $cell = $( this );
		if ( $cell.hasClass( 'ecv2-ss-editing' ) ) { return; }
		var field = $cell.data( 'field' );
		var row_id = $cell.closest( '.ecv2-ss-row' ).data( 'id' );
		var current_text = $.trim( $cell.text() );

		$cell.addClass( 'ecv2-ss-editing' );
		var $input = $( '<input type="text" class="ecv2-ss-edit-input" />' ).val( current_text );
		$cell.empty().append( $input );
		$input.focus().select();

		function finish( text ) {
			$cell.removeClass( 'ecv2-ss-editing' ).text( text );
		}

		function save_edit() {
			var new_val = $.trim( $input.val() );
			finish( new_val !== '' || field !== 'email' ? new_val : current_text );
			if ( new_val === current_text ) { return; }

			$.ajax( {
				url: wpeasycart_admin_ajax_object.ajax_url,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'ecv2_user_inline_update',
					user_id: row_id,
					field: field,
					value: new_val,
					wp_easycart_nonce: ecv2_user_nonces.inline_update
				},
				success: function( response ) {
					if ( response.success ) {
						finish( response.data.display_value );
						ecv2_user_sync_name( row_id );
						ecv2_toast( _t( 'saved', 'Saved successfully.' ), 'success' );
						ecv2_push_undo( {
							message: _t( 'field_updated', 'Field updated' ) + ' \u2014 ' + _t( 'click_undo', 'Click Undo to revert.' ),
							undo_fn: function() {
								$.ajax( {
									url: wpeasycart_admin_ajax_object.ajax_url,
									type: 'POST',
									dataType: 'json',
									data: {
										action: 'ecv2_user_inline_update',
										user_id: row_id,
										field: field,
										value: response.data.old_value,
										wp_easycart_nonce: ecv2_user_nonces.inline_update
									},
									success: function( r2 ) {
										if ( r2.success ) {
											$( '.ecv2-ss-row[data-id="' + row_id + '"] .ecv2-ss-cell[data-field="' + field + '"]' ).text( r2.data.display_value );
											ecv2_user_sync_name( row_id );
											ecv2_toast( _t( 'undone', 'Change reverted.' ), 'info' );
										}
									}
								} );
							}
						} );
					} else {
						finish( current_text );
						ecv2_toast( ( response.data && response.data.message ) || _t( 'error', 'An error occurred.' ), 'error' );
					}
				},
				error: function() {
					finish( current_text );
					ecv2_toast( _t( 'error', 'An error occurred.' ), 'error' );
				}
			} );
		}

		$input.on( 'keydown', function( e ) {
			if ( e.key === 'Enter' ) { e.preventDefault(); save_edit(); }
			if ( e.key === 'Escape' ) { finish( current_text ); }
		} );
		$input.on( 'blur', save_edit );
	} );

	/* Re-render the identity cell name in the table + card views after a
	 * name change in the spreadsheet view, using the ss row's cells as the
	 * source of truth. */
	function ecv2_user_sync_name( user_id ) {
		var $ss_row = $( '.ecv2-ss-row[data-id="' + user_id + '"]' );
		if ( ! $ss_row.length ) { return; }
		var first = $.trim( $ss_row.find( '.ecv2-ss-cell[data-field="first_name"]' ).text() );
		var last = $.trim( $ss_row.find( '.ecv2-ss-cell[data-field="last_name"]' ).text() );
		var name = $.trim( first + ' ' + last );
		var display = name !== '' ? ecv2_esc_html( name ) : '<span class="ecv2-user-name-empty">' + ecv2_esc_html( _t( 'no_name', '(no name)' ) ) + '</span>';
		$( '.ecv2-row[data-id="' + user_id + '"] .ecv2-user-name' ).html( display );
		$( '.ecv2-card[data-id="' + user_id + '"] .ecv2-user-card-name' ).html( display );
	}

	/* ================================================================== */
	/* Quick Edit slideout                                                 */
	/* ================================================================== */

	window.ecv2_user_open_quick_edit = function( user_id ) {
		var $overlay = $( '#ecv2-user-quick-edit-overlay' );
		$overlay.find( '.ecv2-slideout-field-error' ).text( '' );
		$( '#ecv2-user-qe-id' ).val( user_id );
		$( '#ecv2-user-qe-id-chip' ).text( '#' + user_id );
		$( '#ecv2-user-qe-save' ).prop( 'disabled', true );

		$.ajax( {
			url: wpeasycart_admin_ajax_object.ajax_url,
			type: 'POST',
			dataType: 'json',
			data: { action: 'ecv2_user_quick_get', user_id: user_id, wp_easycart_nonce: ecv2_user_nonces.quick_edit },
			success: function( response ) {
				if ( ! response.success ) {
					ecv2_toast( ( response.data && response.data.message ) || _t( 'error', 'An error occurred.' ), 'error' );
					return;
				}
				var u = response.data.user;
				$( '#ecv2-user-qe-email' ).val( u.email );
				$( '#ecv2-user-qe-email-other' ).val( u.email_other );
				$( '#ecv2-user-qe-first' ).val( u.first_name );
				$( '#ecv2-user-qe-last' ).val( u.last_name );
				$( '#ecv2-user-qe-vat' ).val( u.vat_registration_number );
				$( '#ecv2-user-qe-subscriber' ).prop( 'checked', parseInt( u.is_subscriber, 10 ) === 1 );
				var $role = $( '#ecv2-user-qe-role' );
				if ( ! $role.find( 'option[value="' + String( u.user_level ).replace( /"/g, '\\"' ) + '"]' ).length ) {
					// Role hidden from this manager (admin-level) — show read-only.
					$role.append( $( '<option/>' ).attr( 'value', u.user_level ).text( u.user_level ) );
				}
				$role.val( u.user_level );
				$( '#ecv2-user-qe-save' ).prop( 'disabled', false );
			},
			error: function() { ecv2_toast( _t( 'error', 'An error occurred.' ), 'error' ); }
		} );

		$overlay.fadeIn( 150 );
		setTimeout( function() { $( '#ecv2-user-qe-email' ).focus(); }, 200 );
	};

	window.ecv2_user_close_quick_edit = function() {
		$( '#ecv2-user-quick-edit-overlay' ).fadeOut( 150 );
	};

	$( document ).on( 'keydown', function( e ) {
		if ( e.key === 'Escape' && $( '#ecv2-user-quick-edit-overlay' ).is( ':visible' ) ) {
			window.ecv2_user_close_quick_edit();
		}
	} );
	$( document ).on( 'click', '#ecv2-user-quick-edit-overlay', function( e ) {
		if ( $( e.target ).is( '#ecv2-user-quick-edit-overlay' ) ) { window.ecv2_user_close_quick_edit(); }
	} );

	window.ecv2_user_qe_save = function( btn ) {
		var user_id = $( '#ecv2-user-qe-id' ).val();
		$( '#ecv2-user-quick-edit-overlay .ecv2-slideout-field-error' ).text( '' );
		$( btn ).prop( 'disabled', true );

		$.ajax( {
			url: wpeasycart_admin_ajax_object.ajax_url,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'ecv2_user_quick_save',
				user_id: user_id,
				email: $( '#ecv2-user-qe-email' ).val(),
				email_other: $( '#ecv2-user-qe-email-other' ).val(),
				first_name: $( '#ecv2-user-qe-first' ).val(),
				last_name: $( '#ecv2-user-qe-last' ).val(),
				user_level: $( '#ecv2-user-qe-role' ).val(),
				vat_registration_number: $( '#ecv2-user-qe-vat' ).val(),
				is_subscriber: $( '#ecv2-user-qe-subscriber' ).is( ':checked' ) ? 1 : 0,
				wp_easycart_nonce: ecv2_user_nonces.quick_edit
			},
			success: function( response ) {
				$( btn ).prop( 'disabled', false );
				if ( response.success ) {
					var d = response.data;
					var name = $.trim( ( d.first_name || '' ) + ' ' + ( d.last_name || '' ) );
					var display = name !== '' ? ecv2_esc_html( name ) : '<span class="ecv2-user-name-empty">' + ecv2_esc_html( _t( 'no_name', '(no name)' ) ) + '</span>';
					$( '.ecv2-row[data-id="' + d.user_id + '"] .ecv2-user-name' ).html( display );
					$( '.ecv2-row[data-id="' + d.user_id + '"] .ecv2-user-email' ).text( d.email );
					$( '.ecv2-card[data-id="' + d.user_id + '"] .ecv2-user-card-name' ).html( display );
					$( '.ecv2-card[data-id="' + d.user_id + '"] .ecv2-user-card-email' ).text( d.email );
					$( '.ecv2-ss-row[data-id="' + d.user_id + '"] .ecv2-ss-cell[data-field="email"]' ).text( d.email );
					$( '.ecv2-ss-row[data-id="' + d.user_id + '"] .ecv2-ss-cell[data-field="first_name"]' ).text( d.first_name );
					$( '.ecv2-ss-row[data-id="' + d.user_id + '"] .ecv2-ss-cell[data-field="last_name"]' ).text( d.last_name );
					ecv2_toast( d.message || _t( 'saved', 'Saved successfully.' ), 'success' );
					window.ecv2_user_close_quick_edit();
					// Role badge may need a re-style; a soft reload keeps the DOM honest.
					setTimeout( function() { location.reload(); }, 700 );
				} else {
					var d2 = response.data || {};
					if ( d2.field ) {
						$( '#ecv2-user-quick-edit-overlay .ecv2-slideout-field-error[data-field="' + d2.field + '"]' ).text( d2.message || '' );
					}
					ecv2_toast( d2.message || _t( 'error', 'An error occurred.' ), 'error' );
				}
			},
			error: function() {
				$( btn ).prop( 'disabled', false );
				ecv2_toast( _t( 'error', 'An error occurred.' ), 'error' );
			}
		} );
	};

	window.ecv2_user_qe_password_reset = function( btn ) {
		var user_id = $( '#ecv2-user-qe-id' ).val();
		if ( ! user_id ) { return; }
		window.ecv2_user_close_quick_edit();
		window.ecv2_user_row_bulk( user_id, 'accounts-force-password-reset', true );
	};

	/* ================================================================== */
	/* One-time backfill banner                                            */
	/* ================================================================== */

	window.ecv2_user_run_backfill = function( btn ) {
		var $banner = $( '#ecv2-user-backfill-banner' );
		var nonce = $banner.data( 'nonce' );
		var $progress = $( '#ecv2-user-backfill-progress' );
		var total_done = 0;

		$( btn ).prop( 'disabled', true ).text( _t( 'backfill_working', 'Populating…' ) );
		$( '#ecv2-user-backfill-dismiss' ).hide();
		$progress.show().text( '' );

		function run_batch() {
			$.ajax( {
				url: wpeasycart_admin_ajax_object.ajax_url,
				type: 'POST',
				dataType: 'json',
				data: { action: 'ecv2_user_backfill', wp_easycart_nonce: nonce },
				success: function( response ) {
					if ( ! response.success ) {
						$( btn ).prop( 'disabled', false ).text( _t( 'backfill_retry', 'Retry' ) );
						ecv2_toast( ( response.data && response.data.message ) || _t( 'error', 'An error occurred.' ), 'error' );
						return;
					}
					total_done += response.data.processed;
					if ( response.data.remaining > 0 ) {
						$progress.text( total_done + ' ' + _t( 'backfill_done_sep', 'done —' ) + ' ' + response.data.remaining + ' ' + _t( 'backfill_remaining', 'remaining…' ) );
						run_batch();
						return;
					}
					$progress.text( '' );
					$banner.addClass( 'ecv2-user-backfill-complete' );
					$( btn ).text( _t( 'backfill_complete', 'Done! Refreshing…' ) );
					ecv2_toast( _t( 'backfill_success', 'Customer history populated.' ), 'success' );
					setTimeout( function() { location.reload(); }, 1200 );
				},
				error: function() {
					$( btn ).prop( 'disabled', false ).text( _t( 'backfill_retry', 'Retry' ) );
					ecv2_toast( _t( 'error', 'An error occurred.' ), 'error' );
				}
			} );
		}
		run_batch();
	};

	$( document ).on( 'click', '#ecv2-user-backfill-dismiss', function() {
		var $banner = $( '#ecv2-user-backfill-banner' );
		$.post( wpeasycart_admin_ajax_object.ajax_url, {
			action: 'ecv2_user_backfill_dismiss',
			wp_easycart_nonce: $banner.data( 'dismiss-nonce' )
		} );
		$banner.slideUp( 200, function() { $( this ).remove(); } );
	} );

	/* ================================================================== */
	/* Hover row-actions: Delete confirm                                   */
	/* ================================================================== */

	window.ecv2_user_confirm_delete = function( el ) {
		var href = $( el ).attr( 'href' );
		ecv2_show_confirm(
			_t( 'bulk_confirm_delete_title', 'Delete customers?' ),
			_t( 'bulk_confirm_delete_one', 'Permanently delete this customer account? Their addresses are removed too. Orders are kept. This cannot be undone.' )
		).then( function( ok ) {
			if ( ok ) { window.location.href = href; }
		} );
		return false;
	};

	/* ================================================================== */
	/* PRO teaser                                                          */
	/* ================================================================== */

	$( document ).on( 'click keydown', '.ecv2-user-pro-teaser', function( e ) {
		if ( e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ' ) { return; }
		e.preventDefault();
		var action = $( this ).data( 'action' );
		var url = $( this ).data( 'url' );
		if ( action === 'show_pro_required' && typeof window.show_pro_required === 'function' ) {
			window.show_pro_required();
		} else if ( url ) {
			window.location.href = url;
		}
	} );

} );

/* ====================================================================== */
/* Customers CSV import ( V2 ) — parse in the browser, preview, run in chunks */
/* ====================================================================== */
( function( $ ) {
	'use strict';
	var AJAX_URL = ( window.wpeasycart_admin_ajax_object && wpeasycart_admin_ajax_object.ajax_url ) || window.ajaxurl;
	var state = { headers: [], rows: [], nonce: '', match_email: false, preview: null };
	function esc( s ) { return $( '<span>' ).text( s == null ? '' : s ).html(); }
	function post( action, data, ok, fail ) { $.post( AJAX_URL, $.extend( { action: action, nonce: state.nonce }, data || {} ), function( r ) { if ( ! r || ! r.success ) { ( fail || function( m ) { ecv2_toast( m, 'error' ); } )( ( r && r.data && r.data.message ) || 'Something went wrong.' ); return; } ok && ok( r.data ); }, 'json' ).fail( function() { ( fail || function( m ) { ecv2_toast( m, 'error' ); } )( 'Something went wrong.' ); } ); }
	/* RFC-4180-ish CSV parser: quotes, escaped quotes, newlines inside quotes, CRLF, BOM. */
	function parse_csv( text ) {
		text = text.replace( /^\uFEFF/, '' ); var rows = [], row = [], cell = '', q = false, i, ch;
		for ( i = 0; i < text.length; i++ ) { ch = text[ i ];
			if ( q ) { if ( ch === '"' ) { if ( text[ i + 1 ] === '"' ) { cell += '"'; i++; } else { q = false; } } else { cell += ch; } }
			else if ( ch === '"' ) { q = true; }
			else if ( ch === ',' ) { row.push( cell ); cell = ''; }
			else if ( ch === '\n' || ch === '\r' ) { if ( ch === '\r' && text[ i + 1 ] === '\n' ) { i++; } row.push( cell ); cell = ''; if ( row.length > 1 || row[0] !== '' ) { rows.push( row ); } row = []; }
			else { cell += ch; }
		}
		if ( cell !== '' || row.length ) { row.push( cell ); rows.push( row ); }
		return rows;
	}
	function payload() { return JSON.stringify( { headers: state.headers, rows: state.rows, match_email: state.match_email ? 1 : 0 } ); }
	function modal( body, foot ) {
		close();
		var $m = $( '<div class="ecv2-modal-overlay ecv2u-import" id="ecv2u_import" role="dialog" aria-modal="true" aria-labelledby="ecv2u_import_title"><div class="ecv2-modal ecv2u-import-modal"><div class="ecv2-modal-header"><h2 id="ecv2u_import_title">Import customers</h2><button type="button" class="ecv2-modal-close" data-close aria-label="Close">&times;</button></div><div class="ecv2-modal-body">' + body + '</div><div class="ecv2-modal-footer"><div class="ecv2-modal-footer-right">' + foot + '</div></div></div></div>' );
		$( 'body' ).append( $m );
		$m.on( 'click', function( e ) { if ( $( e.target ).is( $m ) || $( e.target ).is( '[data-close]' ) ) { close(); } } );
		$( document ).on( 'keydown.ecv2uimport', function( e ) { if ( e.key === 'Escape' ) { close(); } } );
		return $m;
	}
	function close() { $( document ).off( 'keydown.ecv2uimport' ); $( '#ecv2u_import' ).remove(); }
	function step_pick() {
		modal(
			'<div class="ecv2u-drop" id="ecv2u_drop"><span class="dashicons dashicons-upload"></span><b>Drop a CSV here or choose a file</b><span class="ecv2-sub">First row must be column names. Rows with a <code>user_id</code> update that customer; rows without one create a customer from their email.</span><input type="file" accept=".csv,text/csv" id="ecv2u_file"></div>' +
			'<div class="ecv2u-cols"><b>Columns you can include</b><div>user_id · email · first_name · last_name · user_level · is_subscriber · exclude_tax · exclude_shipping · allow_shipping_bypass · vat_registration_number · user_notes · email_other</div><div>billing_&lt;field&gt; / shipping_&lt;field&gt; — first_name, last_name, company_name, address_line_1, address_line_2, city, state, zip, country, phone</div><div class="ecv2-sub">Passwords are never imported; new customers use “Forgot password”. Only columns present in the file are changed on updates.</div></div>',
			'<a href="#" class="ecv2-btn ecv2-btn-ghost" id="ecv2u_tpl">Download template</a><span class="ecos-grow"></span><button type="button" class="ecv2-btn" data-close>Cancel</button>'
		);
		var $drop = $( '#ecv2u_drop' );
		$drop.on( 'click', function( e ) { if ( ! $( e.target ).is( 'input' ) ) { $( '#ecv2u_file' ).trigger( 'click' ); } } );
		$drop.on( 'dragover', function( e ) { e.preventDefault(); $drop.addClass( 'is-over' ); } ).on( 'dragleave drop', function() { $drop.removeClass( 'is-over' ); } ).on( 'drop', function( e ) { e.preventDefault(); var f = e.originalEvent.dataTransfer.files[0]; if ( f ) { read( f ); } } );
		$( '#ecv2u_file' ).on( 'change', function() { if ( this.files[0] ) { read( this.files[0] ); } } );
		$( '#ecv2u_tpl' ).on( 'click', function( e ) { e.preventDefault(); post( 'ecv2_user_import_template', {}, function( d ) { var a = document.createElement( 'a' ); a.href = URL.createObjectURL( new Blob( [ d.csv ], { type: 'text/csv' } ) ); a.download = d.filename; document.body.appendChild( a ); a.click(); document.body.removeChild( a ); } ); } );
	}
	function read( file ) {
		if ( file.size > 8 * 1024 * 1024 ) { ecv2_toast( 'That file is over 8 MB — split it into smaller files.', 'error' ); return; }
		var fr = new FileReader();
		fr.onload = function() { var rows = parse_csv( String( fr.result ) ); if ( rows.length < 2 ) { ecv2_toast( 'The file needs a header row and at least one customer row.', 'error' ); return; } state.headers = rows[0]; state.rows = rows.slice( 1 ); state.file = file.name; preview(); };
		fr.readAsText( file );
	}
	function preview() {
		$( '#ecv2u_import .ecv2-modal-body' ).html( '<div class="ecos-hint">Checking ' + state.rows.length + ' rows…</div>' );
		post( 'ecv2_user_import_preview', { payload: payload() }, function( d ) {
			state.preview = d; var t = d.totals, can = t.create + t.update > 0;
			var body = '<div class="ecv2u-file"><span class="dashicons dashicons-media-spreadsheet" aria-hidden="true"></span><span class="ecv2u-file-meta"><b title="' + esc( state.file ).replace( /"/g, '&quot;' ) + '">' + esc( state.file ) + '</b><span class="ecv2-sub">' + d.total + ' rows · ' + d.used_headers.length + ' recognised columns</span></span><a href="#" id="ecv2u_change">Change file</a></div>';
			body += '<div class="ecv2u-totals"><div class="is-create"><b>' + t.create + '</b><span>new customers</span></div><div class="is-update"><b>' + t.update + '</b><span>updates</span></div><div class="' + ( t.skip ? 'is-skip' : '' ) + '"><b>' + t.skip + '</b><span>skipped</span></div></div>';
			if ( d.unknown_headers.length ) { body += '<div class="ecos-note"><span class="dashicons dashicons-warning"></span><div><b>Ignored columns:</b> ' + esc( d.unknown_headers.join( ', ' ) ) + '. They don’t match any customer field, so they’re left out.</div></div>'; }
			if ( ! d.has_id && ! d.has_email ) { body += '<div class="ecos-note"><span class="dashicons dashicons-warning"></span><div>No <code>user_id</code> or <code>email</code> column — nothing can be matched or created.</div></div>'; }
			var reasons = Object.keys( d.reasons || {} );
			if ( reasons.length ) { body += '<div class="ecv2u-reasons"><b>Why rows are skipped</b>'; $.each( reasons, function( i, r ) { body += '<div><span class="ecv2-chip ecv2-chip-gray">' + d.reasons[ r ] + '</span> ' + esc( r ) + '</div>'; } ); body += '</div>'; }
			body += '<label class="ecos-toggle-row ecv2u-opt"><span class="ecv2-toggle ecv2-toggle-sm"><input type="checkbox" id="ecv2u_match"' + ( state.match_email ? ' checked' : '' ) + '><span class="ecv2-toggle-slider"></span></span><span class="ecv2u-opt-text"><span class="ecos-toggle-t">Update existing customers by email</span><small>Rows without a <code>user_id</code> whose email already exists update that customer instead of being skipped.</small></span></label>';
			body += '<div class="ecv2u-sample-wrap"><table class="ecv2u-sample"><thead><tr><th class="ecv2u-col-row">Row</th><th>Customer</th><th class="ecv2u-col-action">Action</th></tr></thead><tbody>';
			$.each( d.sample, function( i, r ) {
				var tone = r.action === 'create' ? 'green' : ( r.action === 'update' ? 'blue' : 'gray' );
				var name = String( r.name || '' ).trim(), email = String( r.email || '' ).trim(), who;
				if ( name && email ) { who = '<span class="ecv2u-cust-name">' + esc( name ) + '</span><span class="ecv2u-cust-email">' + esc( email ) + '</span>'; }
				else if ( name || email ) { who = '<span class="ecv2u-cust-name">' + esc( name || email ) + '</span>'; }
				else { who = '<span class="ecv2u-cust-none">No name or email</span>'; }
				body += '<tr><td class="ecv2u-col-row">' + esc( r.row ) + '</td><td class="ecv2u-col-cust">' + who + '</td><td class="ecv2u-col-action"><span class="ecv2-chip ecv2-chip-' + tone + '">' + ( r.action === 'update' ? 'Update #' + esc( r.target ) : r.action === 'create' ? 'Create' : 'Skip' ) + '</span>' + ( r.reason ? '<span class="ecv2-sub">' + esc( r.reason ) + '</span>' : '' ) + '</td></tr>';
			} );
			body += '</tbody></table></div>' + ( d.total > d.sample.length ? '<div class="ecv2-sub ecv2u-sample-more">Showing the first ' + d.sample.length + ' of ' + d.total + ' rows.</div>' : '' );
			$( '#ecv2u_import .ecv2-modal-body' ).html( body );
			$( '#ecv2u_import .ecv2-modal-footer-right' ).html( '<button type="button" class="ecv2-btn" data-close>Cancel</button><button type="button" class="ecv2-btn ecv2-btn-primary" id="ecv2u_go"' + ( can ? '' : ' disabled' ) + '>' + ( can ? 'Import ' + ( t.create ? t.create + ' new' : '' ) + ( t.create && t.update ? ' + ' : '' ) + ( t.update ? t.update + ' update' + ( t.update === 1 ? '' : 's' ) : '' ) : 'Nothing to import' ) + '</button>' );
			$( '#ecv2u_change' ).on( 'click', function( e ) { e.preventDefault(); step_pick(); } );
			$( '#ecv2u_match' ).on( 'change', function() { state.match_email = this.checked; preview(); } );
			$( '#ecv2u_go' ).on( 'click', run );
		} );
	}
	function run() {
		var done = { create: 0, update: 0, skip: 0 }, total = state.rows.length;
		$( '#ecv2u_import .ecv2-modal-footer-right' ).html( '<span class="ecv2-sub">Importing… don’t close this window.</span>' );
		$( '#ecv2u_import .ecv2-modal-body' ).html( '<div class="ecv2u-progress"><div class="ecv2u-bar"><span id="ecv2u_bar"></span></div><div class="ecv2-sub" id="ecv2u_prog">0 of ' + total + '</div></div>' );
		( function chunk( offset ) {
			post( 'ecv2_user_import_run', { payload: payload(), offset: offset }, function( d ) {
				done.create += d.done.create; done.update += d.done.update; done.skip += d.done.skip;
				$( '#ecv2u_bar' ).css( 'width', Math.round( d.next / d.total * 100 ) + '%' ); $( '#ecv2u_prog' ).text( d.next + ' of ' + d.total );
				if ( ! d.finished ) { chunk( d.next ); return; }
				$( '#ecv2u_import .ecv2-modal-body' ).html( '<div class="ecv2u-totals"><div class="is-create"><b>' + done.create + '</b><span>created</span></div><div class="is-update"><b>' + done.update + '</b><span>updated</span></div><div class="' + ( done.skip ? 'is-skip' : '' ) + '"><b>' + done.skip + '</b><span>skipped</span></div></div><div class="ecos-note info"><span class="dashicons dashicons-info-outline"></span><div>New customers have no password yet — they sign in with “Forgot password”. Lifecycle and spend stats fill in as orders arrive.</div></div>' );
				$( '#ecv2u_import .ecv2-modal-footer-right' ).html( '<button type="button" class="ecv2-btn ecv2-btn-primary" id="ecv2u_done">Done — refresh list</button>' );
				$( '#ecv2u_done' ).on( 'click', function() { window.location.reload(); } );
			}, function( m ) { ecv2_toast( m, 'error' ); $( '#ecv2u_import .ecv2-modal-footer-right' ).html( '<button type="button" class="ecv2-btn" data-close>Close</button>' ); } );
		} )( 0 );
	}
	window.ecv2u_import = { open: function( btn ) { state.nonce = $( btn ).data( 'nonce' ) || state.nonce; state.match_email = false; step_pick(); return false; } };
} )( jQuery );

/* =====================================================================
   Customer delete — the safe-delete flow the catalog lists use.

   6.0.1: a customer delete used to run straight off a link, leaving every order they had placed pointing
   at an account that no longer existed ( the order screen shows that as an empty customer box ). The same
   engine that reassigns categories, menus and option sets now handles customers, so the merchant decides
   where the orders go first. catalog-v2.js owns the original modal, but it cannot be loaded here: it binds
   the same delegated handlers this file does, so filters, stat cards and view toggles would all fire twice.

   The customer the orders move to is searched for ( ecv2_delete_targets ), not picked from a list: a store
   can have hundreds of thousands of accounts. After the delete the page reloads onto the list's Undo bar
   ( ?trash=<id>, wp_easycart_admin_undo::maybe_print_bar() ); a toast faded before its Undo could be used.
   Used by the list's row menu and hover link, and by the customer details page ( opts.redirect_to ).
   ===================================================================== */
( function( $ ) {
	'use strict';

	/* Safe in text and in quoted attributes ( customer names and emails are shopper-entered ). */
	function esc( s ) {
		return String( s == null ? '' : s ).replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' ).replace( /"/g, '&quot;' ).replace( /'/g, '&#039;' );
	}

	function t( key, fallback ) {
		return ( typeof ecv2_user_lang !== 'undefined' && ecv2_user_lang[ key ] ) ? ecv2_user_lang[ key ] : fallback;
	}

	function nonce() {
		return ( typeof ecv2_user_nonces !== 'undefined' && ecv2_user_nonces.safe_delete ) ? ecv2_user_nonces.safe_delete : '';
	}

	function url() {
		return ( typeof ajaxurl !== 'undefined' ) ? ajaxurl : wpeasycart_admin_ajax_object.ajax_url;
	}

	function toast( m, type ) {
		if ( window.ecv2_toast ) { ecv2_toast( m, type ); }
	}

	function reset_go() {
		$( '#ecv2_ud_go' ).prop( 'disabled', false ).text( t( 'delete', 'Delete' ) );
	}

	function ajax( data, done ) {
		return $.post( url(), data, function( r ) {
			if ( ! r || ! r.success ) {
				toast( ( r && r.data && r.data.message ) || t( 'error', 'Something went wrong.' ), 'error' );
				reset_go();
				return;
			}
			done( r.data );
		}, 'json' ).fail( function( xhr, status ) {
			if ( 'abort' === status ) { return; }
			toast( t( 'error', 'Something went wrong.' ), 'error' );
			reset_go();
		} );
	}

	/* Bumped whenever a window closes, so a late answer for an earlier window never fills a newer one. */
	var modal_gen = 0;

	function close_modal() {
		modal_gen++;
		$( '#ecv2_user_delete_modal' ).remove();
		$( document ).off( 'keydown.ecv2ud' );
	}

	function open_modal( title, body, footer ) {
		close_modal();
		$( 'body' ).append(
			'<div class="ecv2-modal-overlay ecv2-sd-modal" id="ecv2_user_delete_modal" style="display:flex;">' +
				'<div class="ecv2-modal" role="dialog" aria-modal="true" aria-labelledby="ecv2_ud_title">' +
					'<div class="ecv2-modal-header"><h2 id="ecv2_ud_title">' + esc( title ) + '</h2>' +
						'<button type="button" class="ecv2-modal-close" id="ecv2_ud_x" aria-label="' + esc( t( 'close', 'Close' ) ) + '">&times;</button></div>' +
					'<div class="ecv2-modal-body" id="ecv2_ud_body">' + body + '</div>' +
					'<div class="ecv2-modal-footer"><div class="ecv2-modal-footer-right" id="ecv2_ud_foot">' + footer + '</div></div>' +
				'</div>' +
			'</div>'
		);
		$( '#ecv2_ud_x' ).on( 'click', close_modal );
		$( document ).on( 'keydown.ecv2ud', function( e ) {
			if ( 'Escape' === e.key && ! $( '#ecv2_ud_go' ).prop( 'disabled' ) ) { close_modal(); }
		} );
	}

	/* ---------- The "move their orders to" search box ---------- */

	function search_html( s ) {
		return '<div class="ecv2-sd-search" data-for="' + esc( s.key ) + '">' +
			'<input type="hidden" class="ecv2-sd-target" data-for="' + esc( s.key ) + '" value="">' +
			'<div class="ecv2-sd-picked" hidden><span class="dashicons dashicons-admin-users" aria-hidden="true"></span><span class="ecv2-sd-picked-text"><b></b><small></small></span>' +
				'<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-sd-picked-change">' + esc( t( 'change', 'Change' ) ) + '</button></div>' +
			'<input type="search" class="ecv2-input ecv2-sd-search-input" autocomplete="off" spellcheck="false" role="combobox" aria-expanded="false" aria-autocomplete="list"' +
				' placeholder="' + esc( t( 'target_search_placeholder', 'Search customers by name, email or #' ) ) + '"' + ( s.disabled ? ' disabled' : '' ) + '>' +
			'<div class="ecv2-sd-results" role="listbox" hidden></div>' +
		'</div>';
	}

	function bind_search( id ) {
		var $box = $( '#ecv2_ud_body .ecv2-sd-search' );
		if ( ! $box.length ) { return; }
		var key = $box.data( 'for' ), $in = $box.find( '.ecv2-sd-search-input' ), $res = $box.find( '.ecv2-sd-results' );
		var timer = null, xhr = null, seq = 0;

		function choose_strategy() {
			$( 'input[name="ecv2_ud_strategy"][value="' + key + '"]' ).prop( 'checked', true );
		}
		function show( html ) {
			$res.html( html ).prop( 'hidden', false );
			$in.attr( 'aria-expanded', 'true' );
		}
		function hide() {
			$res.prop( 'hidden', true ).empty();
			$in.attr( 'aria-expanded', 'false' );
		}
		function pick( $item ) {
			$box.find( '.ecv2-sd-target' ).val( $item.attr( 'data-value' ) );
			$box.find( '.ecv2-sd-picked-text b' ).text( $item.attr( 'data-label' ) || '' );
			$box.find( '.ecv2-sd-picked-text small' ).text( $item.attr( 'data-detail' ) || '' );
			$box.find( '.ecv2-sd-picked' ).prop( 'hidden', false );
			$in.prop( 'hidden', true ).val( '' );
			hide();
			choose_strategy();
		}
		function search( q ) {
			if ( xhr ) { xhr.abort(); }
			var mine = ++seq;
			show( '<div class="ecv2-sd-results-note">' + esc( t( 'target_searching', 'Searching…' ) ) + '</div>' );
			xhr = ajax( { action: 'ecv2_delete_targets', nonce: nonce(), type: 'customer', id: id, q: q }, function( d ) {
				if ( mine !== seq ) { return; }
				if ( ! d.results || ! d.results.length ) {
					show( '<div class="ecv2-sd-results-note">' + esc( t( 'target_none', 'No other customer matches “%s”.' ).replace( '%s', q ) ) + '</div>' );
					return;
				}
				var html = '';
				$.each( d.results, function( i, r ) {
					html += '<button type="button" class="ecv2-sd-result" role="option" data-value="' + esc( r.value ) + '" data-label="' + esc( r.label ) + '" data-detail="' + esc( r.detail ) + '">' +
						'<b>' + esc( r.label ) + '</b><small>' + esc( r.detail ) + '</small></button>';
				} );
				if ( 20 <= d.results.length ) {
					html += '<div class="ecv2-sd-results-note">' + esc( t( 'target_more', 'Showing the first 20. Type more to narrow it down.' ) ) + '</div>';
				}
				show( html );
			} );
		}

		$in.on( 'focus', choose_strategy );
		$in.on( 'input', function() {
			var q = $.trim( this.value );
			clearTimeout( timer );
			if ( q.length < 2 && ! /^\d+$/.test( q ) ) {
				if ( xhr ) { xhr.abort(); }
				seq++;
				if ( q.length ) { show( '<div class="ecv2-sd-results-note">' + esc( t( 'target_min', 'Keep typing…' ) ) + '</div>' ); } else { hide(); }
				return;
			}
			timer = setTimeout( function() { search( q ); }, 250 );
		} );
		$in.on( 'keydown', function( e ) {
			var $items = $res.find( '.ecv2-sd-result' ), i = $items.index( $items.filter( '.is-active' ) );
			if ( 'ArrowDown' === e.key || 'ArrowUp' === e.key ) {
				if ( ! $items.length ) { return; }
				e.preventDefault();
				i = 'ArrowDown' === e.key ? Math.min( i + 1, $items.length - 1 ) : Math.max( i - 1, 0 );
				$items.removeClass( 'is-active' ).eq( i ).addClass( 'is-active' )[0].scrollIntoView( { block: 'nearest' } );
			} else if ( 'Enter' === e.key ) {
				e.preventDefault();
				if ( $items.length ) { pick( i >= 0 ? $items.eq( i ) : $items.first() ); }
			} else if ( 'Escape' === e.key && ! $res.prop( 'hidden' ) ) {
				e.stopPropagation();
				hide();
			}
		} );
		$res.on( 'mousedown', '.ecv2-sd-result', function( e ) { e.preventDefault(); } ); /* keep focus in the box */
		$res.on( 'click', '.ecv2-sd-result', function( e ) { e.preventDefault(); pick( $( this ) ); } );
		$box.on( 'click', '.ecv2-sd-picked-change', function( e ) {
			e.preventDefault();
			$box.find( '.ecv2-sd-target' ).val( '' );
			$box.find( '.ecv2-sd-picked' ).prop( 'hidden', true );
			$in.prop( 'hidden', false ).trigger( 'focus' );
		} );
	}

	/**
	 * Ask what should happen to this customer's orders, then delete.
	 *
	 * @param {number} id   Customer id.
	 * @param {Object} opts { redirect_to: list URL to land on ( details page ), warning: extra line to show,
	 *                      before_leave: called just before the page navigates away }.
	 */
	window.ecv2_user_safe_delete = function( id, opts ) {
		id = parseInt( id, 10 ) || 0;
		opts = opts || {};
		if ( ! id ) { return false; }
		if ( window.ecv2_close_row_menus ) { ecv2_close_row_menus(); }

		open_modal(
			t( 'delete_checking', 'Checking what this affects…' ),
			'<div class="ecv2-sd-loading"><span class="dashicons dashicons-update ecv2-spin"></span></div>',
			'<button type="button" class="ecv2-btn" id="ecv2_ud_cancel">' + esc( t( 'cancel', 'Cancel' ) ) + '</button>'
		);
		$( '#ecv2_ud_cancel' ).on( 'click', close_modal );

		var gen = modal_gen;
		ajax( { action: 'ecv2_delete_impact', nonce: nonce(), type: 'customer', id: id }, function( r ) {
			if ( gen !== modal_gen || ! $( '#ecv2_user_delete_modal' ).length ) { return; }
			var html = '<div class="ecv2-sd-impacts">';
			$.each( r.impacts, function( i, im ) {
				html += '<div class="ecv2-sd-impact is-' + esc( im.severity ) + '"><span class="ecv2-sd-count">' + esc( im.count ) + '</span>' +
					'<div class="ecv2-sd-impact-main"><b>' + esc( im.label ) + '</b><span>' + esc( im.detail ) +
					( im.sample && im.sample.length ? ' <em>' + esc( im.sample.join( ', ' ) ) + ( im.count > im.sample.length ? ' …' : '' ) + '</em>' : '' ) +
					( im.link ? ' <a href="' + esc( im.link ) + '" target="_blank" rel="noopener">' + esc( t( 'view', 'View' ) ) + '</a>' : '' ) +
					'</span></div></div>';
			} );
			if ( opts.warning ) {
				html += '<div class="ecv2-sd-impact is-block"><span class="ecv2-sd-count">!</span><div class="ecv2-sd-impact-main"><span>' + esc( opts.warning ) + '</span></div></div>';
			}
			html += '</div><div class="ecv2-sd-strategies">';
			var first = null;
			$.each( r.strategies, function( i, s ) {
				if ( first === null && ! s.disabled ) { first = s.key; }
				html += '<label class="ecv2-sd-strategy' + ( s.disabled ? ' is-disabled' : '' ) + '">' +
					'<input type="radio" name="ecv2_ud_strategy" value="' + esc( s.key ) + '"' + ( s.disabled ? ' disabled' : '' ) + ( s.recommended && ! s.disabled ? ' checked' : '' ) + '>' +
					'<div><b>' + esc( s.label ) + ( s.recommended ? ' <span class="ecv2-chip ecv2-chip-green">' + esc( t( 'recommended', 'Recommended' ) ) + '</span>' : '' ) + '</b><span>' + esc( s.description ) + '</span>';
				if ( s.requires_target && s.target_search ) {
					html += search_html( s ) + ( s.disabled ? '<small>' + esc( t( 'target_no_other', 'There is no other customer to move them to.' ) ) + '</small>' : '' );
				} else if ( s.requires_target ) {
					html += '<select class="ecv2-select ecv2-sd-target" data-for="' + esc( s.key ) + '"' + ( s.disabled ? ' disabled' : '' ) + '>';
					$.each( s.targets || [], function( j, tg ) { html += '<option value="' + esc( tg.value ) + '">' + esc( tg.label ) + '</option>'; } );
					html += '</select>' + ( s.disabled ? '<small>' + esc( t( 'target_no_other', 'There is no other customer to move them to.' ) ) + '</small>' : '' );
				}
				html += '</div></label>';
			} );
			html += '</div><p class="ecv2-sd-undo"><span class="dashicons dashicons-backup"></span> ' + esc( t( 'undo_note', 'You can undo this for %d days from Store Status › Recently deleted.' ).replace( '%d', r.undo_days ) ) + '</p>';

			$( '#ecv2_ud_title' ).text( t( 'delete_named', 'Delete “%s”?' ).replace( '%s', r.name ) );
			$( '#ecv2_ud_body' ).html( html );
			if ( ! $( 'input[name="ecv2_ud_strategy"]:checked' ).length && first ) {
				$( 'input[name="ecv2_ud_strategy"][value="' + first + '"]' ).prop( 'checked', true );
			}
			bind_search( id );
			$( '#ecv2_ud_foot' ).html( '<button type="button" class="ecv2-btn" id="ecv2_ud_cancel">' + esc( t( 'cancel', 'Cancel' ) ) + '</button><button type="button" class="ecv2-btn ecv2-btn-danger" id="ecv2_ud_go">' + esc( t( 'delete', 'Delete' ) ) + '</button>' );
			$( '#ecv2_ud_cancel' ).on( 'click', close_modal );

			$( '#ecv2_ud_go' ).on( 'click', function() {
				var strategy = $( 'input[name="ecv2_ud_strategy"]:checked' ).val();
				if ( ! strategy ) { return; }
				var chosen = $.grep( r.strategies || [], function( s ) { return s.key === strategy; } )[0] || {};
				var target = $( '.ecv2-sd-target[data-for="' + strategy + '"]' ).val() || 0;
				if ( chosen.requires_target && ! parseInt( target, 10 ) ) {
					toast( t( 'target_required', 'Choose the customer their orders should move to.' ), 'error' );
					$( '.ecv2-sd-search[data-for="' + strategy + '"] .ecv2-sd-search-input' ).trigger( 'focus' );
					return;
				}
				$( this ).prop( 'disabled', true ).text( t( 'deleting', 'Deleting…' ) );
				ajax( { action: 'ecv2_delete_execute', nonce: nonce(), type: 'customer', id: id, strategy: strategy, target_id: target }, function( d ) {
					/* Land on the list with its Undo bar. The reload also redraws the Orders / Spend figures of a customer
					   who just received orders. */
					if ( opts.before_leave ) { opts.before_leave(); }
					if ( d.trash_id && window.ecv2_undo_landing ) {
						window.location.href = ecv2_undo_landing( 'trash', d.trash_id, opts.redirect_to );
						return;
					}
					close_modal();
					toast( d.message, 'success' );
					setTimeout( function() { if ( opts.redirect_to ) { window.location.href = opts.redirect_to; } else { window.location.reload(); } }, 900 );
				} );
			} );
		} );
		return false;
	};
} )( jQuery );
