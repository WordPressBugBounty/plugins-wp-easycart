/* 6.0.0: rows are rendered once per view mode; count an id once ( shared definition lives in shell-v2.js ). */window.ecv2_row_check_count = window.ecv2_row_check_count || function() { var seen = {}, n = 0; jQuery( '.ecv2-row-check:checked' ).each( function() { if ( ! seen[ this.value ] ) { seen[ this.value ] = true; n++; } } ); return n; };
/**
 * WP EasyCart Admin — Catalog V2 shared script ( Option Sets + Categories ).
 *
 * Section 1 is the ecv2_* table framework the V2 base table markup calls by
 * name ( row menus, filter drawer, view toggle, stat cards, bulk selection,
 * spreadsheet columns ). It is the same contract as users-v2.js / orders-v2.js;
 * keep them in sync when the base class markup changes.
 *
 * Section 2 is catalog-specific: inline toggles, category tree drag/nest,
 * and the ecv2_catalog object ( safe-delete modal with Undo, assign-to-products,
 * add-products picker, new-category and move-under modals ). The editors load
 * this file too and call ecv2_catalog.* from their templates.
 *
 * Localized: ecv2_catalog_vars { nonces: { inline_update, safe_delete, osv2, catv2 }, categories: [ {id,label} ], lang: {…} }
 *
 * @since 5.x.x
 */
jQuery( function( $ ) {
	'use strict';

	var VARS   = window.ecv2_catalog_vars || {};
	var NONCES = VARS.nonces || {};
	var AJAX_URL = ( window.wpeasycart_admin_ajax_object && wpeasycart_admin_ajax_object.ajax_url ) || window.ajaxurl;
	var ecv2_catalog_lang = VARS.lang || {};

	var ECV2_BULK_MAX = 500;
	var ECV2_BULK_LEGACY_MAX = 100; // Legacy GET pipeline — keep URLs sane.
	var ecv2_undo_stack = [];
	var ECV2_UNDO_MAX = 10;
	var ecv2_confirm_resolve = null;

	function _t( key, fallback ) {
		return ( typeof ecv2_catalog_lang !== 'undefined' && ecv2_catalog_lang[ key ] ) ? ecv2_catalog_lang[ key ] : fallback;
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

	/* The table pages print the dialog markup; every other V2 page ( settings, editors ) gets it injected on first use. */
	function ecv2_ensure_confirm_dialog() {
		if ( $( '#ecv2-confirm-dialog' ).length ) { return; }
		var cancel = ( window.ecv2_lang && ecv2_lang.cancel ) ? ecv2_lang.cancel : 'Cancel', ok = ( window.ecv2_lang && ecv2_lang.confirm ) ? ecv2_lang.confirm : 'Confirm';
		$( 'body' ).append(
			'<div class="ecv2-modal-overlay" id="ecv2-confirm-dialog" style="display:none;"><div class="ecv2-modal ecv2-modal-confirm">' +
			'<div class="ecv2-modal-header"><h2 id="ecv2-confirm-title"></h2><button type="button" class="ecv2-modal-close" onclick="ecv2_confirm_cancel();">&times;</button></div>' +
			'<div class="ecv2-modal-body"><p id="ecv2-confirm-message"></p></div>' +
			'<div class="ecv2-modal-footer"><div class="ecv2-modal-footer-right">' +
			'<button type="button" class="ecv2-btn ecv2-btn-ghost" onclick="ecv2_confirm_cancel();">' + ecv2_esc_html( cancel ) + '</button>' +
			'<button type="button" class="ecv2-btn ecv2-btn-primary" id="ecv2-confirm-ok" onclick="ecv2_confirm_ok();">' + ecv2_esc_html( ok ) + '</button>' +
			'</div></div></div></div>'
		);
	}

	function ecv2_show_confirm( title, message ) {
		ecv2_ensure_confirm_dialog();
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

	/* select2 AJAX options for a typeahead filter ( a <select data-ajax-action="…"> printed by
	   wp_easycart_admin_table_v2 ), or null for a plain select. Accepts the three response shapes the
	   admin-ajax search handlers use: { results:[…] }, { data:{ results:[…] } } and { items:[…] }. @since 6.0.0 */
	function ecv2_filter_ajax_opts( $select ) {
		var action = $select.data( 'ajax-action' );
		if ( ! action ) { return null; }
		var nonce = $select.data( 'ajax-nonce' ) || '';
		var nonce_key = $select.data( 'ajax-nonce-key' ) || 'wp_easycart_nonce';
		var term_key = $select.data( 'ajax-term-key' ) || 'q';
		return {
			url: ( typeof ajaxurl !== 'undefined' ) ? ajaxurl : wpeasycart_admin_ajax_object.ajax_url,
			type: 'POST',
			dataType: 'json',
			delay: 250,
			data: function( params ) {
				var d = { action: action, page: params.page || 1 };
				d[ term_key ] = params.term || '';
				if ( nonce ) { d[ nonce_key ] = nonce; }
				return d;
			},
			processResults: function( r ) {
				var list = [];
				if ( r && Array.isArray( r.results ) ) { list = r.results; }
				else if ( r && r.data && Array.isArray( r.data.results ) ) { list = r.data.results; }
				else if ( r && Array.isArray( r.items ) ) { list = r.items; }
				var out = [];
				for ( var i = 0; i < list.length; i++ ) {
					var it = list[ i ];
					if ( ! it || typeof it.id === 'undefined' || it.id === null ) { continue; }
					out.push( { id: String( it.id ), text: String( it.text || it.name || it.label || it.title || it.id ) } );
				}
				return { results: out, pagination: { more: !! ( r && r.more ) } };
			},
			cache: true
		};
	}

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
				var ajax = ecv2_filter_ajax_opts( $select );
				if ( ajax ) {
					opts.ajax = ajax;
					opts.minimumResultsForSearch = 0;
					opts.minimumInputLength = parseInt( $select.data( 'ajax-min' ), 10 ) || 0;
				}
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

	function close_row_menus() {
		$( '.ecv2-row-menu' ).removeClass( 'ecv2-row-menu-open ecv2-menu-fixed' ).css( { top: '', right: '', left: '' } );
		$( '.ecv2-card' ).removeClass( 'ecv2-card-menu-active' );
	}
	window.ecv2_close_row_menus = close_row_menus;
	$( document ).on( 'click', function( e ) {
		if ( ! $( e.target ).closest( '.ecv2-row-menu-wrap' ).length ) { close_row_menus(); }
	} );
	/* Choosing an item closes the menu. This delegated handler runs after the item's own inline onclick ( Approve,
	   Deny, Set quantity… ), so the action fires first and the menu goes away — regardless of which screen built it. */
	$( document ).on( 'click', '.ecv2-row-menu .ecv2-row-menu-item', function() { close_row_menus(); } );
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
		document.cookie = 'wpeasycart_ss_hidden_cols_catalog=' + hidden.join( ',' ) + ';path=/;max-age=31536000';
	};

	$( document ).ready( function() {
		var match = document.cookie.match( /wpeasycart_ss_hidden_cols_catalog=([^;]*)/ );
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


	/* ================================================================== */
	/* Bulk apply — catalog actions                                        */
	/* ================================================================== */

	function catalog_ajax( data, ok, fail ) {
		$.ajax( { url: AJAX_URL, type: 'POST', dataType: 'json', data: data } )
			.done( function( r ) { if ( r && r.success ) { ok( r.data || {} ); } else { ( fail || function( m ) { ecv2_toast( m, 'error' ); } )( ( r && r.data && r.data.message ) || _t( 'error', 'Something went wrong.' ) ); } } )
			.fail( function() { ( fail || function( m ) { ecv2_toast( m, 'error' ); } )( _t( 'error', 'Something went wrong.' ) ); } );
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
		var ids = ecv2_bulk_collect_ids();
		if ( ids.length === 0 ) { ecv2_toast( _t( 'bulk_none_selected', 'Select some rows first.' ), 'error' ); return; }
		if ( ids.length > ECV2_BULK_MAX ) { ecv2_toast( _t( 'bulk_max_exceeded', 'Too many selected. Narrow your selection.' ), 'error' ); return; }

		var m = action.match( /^ecv2-(option|category|menu|manufacturer|review|role|subscriber|country|plan|sub|dl|ac|fee|gc|sch|loc)-(.+)$/ );
		if ( ! m ) { $( btn ).closest( 'form' ).submit(); return; } /* legacy GET actions untouched */
		var type = m[1], op = m[2];

		if ( type === 'role' && op === 'delete' ) { window.ecrole && ecrole.bulk_delete( ids ); return; }
		if ( type === 'country' ) { window.eccountry && eccountry.bulk( op, ids ); return; }
		if ( type === 'plan' && op === 'delete' ) { window.ecplan && ecplan.bulk_delete( ids ); return; }
		/* PRO lists ( orders-pro-v2.js, fees-v2.js, abandoned-carts-v2.js ) */
		if ( type === 'sub' && op === 'delete' ) { window.ecsubs && ecsubs.bulk_delete( ids ); return; }
		if ( type === 'dl' ) { window.ecdl && ecdl.bulk( op, ids ); return; }
		if ( type === 'ac' ) { window.ecac && ecac.bulk( op, ids ); return; }
		if ( type === 'fee' && op === 'delete' ) { window.ecfee && ecfee.bulk_delete( ids ); return; }
		if ( type === 'gc' && op === 'delete' ) { window.ecgc && ecgc.bulk_delete( ids ); return; }
		if ( type === 'sch' && op === 'delete' ) { window.ecsch && ecsch.bulk_delete( ids ); return; }
		if ( type === 'loc' && op === 'delete' ) { window.ecloc && ecloc.delete_ids( ids ); return; }
		if ( type === 'subscriber' && op === 'export' ) { window.ecsub && ecsub.export_selected( ids ); return; }
		if ( type === 'subscriber' && op === 'delete' ) { window.ecsub && ecsub.bulk_delete( ids ); return; }
		if ( op === 'delete' ) { ecv2_catalog.bulk_delete( type, ids ); return; }
		if ( op === 'move' ) { ecv2_catalog.move_category( ids ); return; }
		var opmap = { require: 'require', optional: 'optional', activate: 'activate', deactivate: 'deactivate', feature: 'feature', unfeature: 'unfeature', approve: 'approve', deny: 'deny' };
		if ( ! opmap[ op ] ) { return; }
		catalog_ajax( { action: 'ecv2_' + type + '_bulk', wp_easycart_nonce: NONCES.inline_update, ids: ids, op: opmap[ op ] }, function( d ) {
			ecv2_toast( _t( 'bulk_done', 'Updated %d items.' ).replace( '%d', d.done ), 'success' );
			setTimeout( function() { window.location.reload(); }, 600 );
		} );
	};

	/* ================================================================== */
	/* Inline toggles                                                      */
	/* ================================================================== */

	$( document ).on( 'change', '.ecv2-option-required-toggle', function() {
		var $cb = $( this ), id = $cb.data( 'id' ), val = $cb.is( ':checked' ) ? 1 : 0;
		catalog_ajax( { action: 'ecv2_option_toggle_required', wp_easycart_nonce: NONCES.inline_update, option_id: id, required: val }, function( d ) {
			$( '.ecv2-option-required-toggle[data-id="' + id + '"]' ).prop( 'checked', !! parseInt( d.required, 10 ) );
			ecv2_toast( _t( 'saved', 'Saved' ), 'success' );
		}, function( msg ) { $cb.prop( 'checked', ! val ); ecv2_toast( msg, 'error' ); } );
	} );

	$( document ).on( 'change', '.ecv2-category-toggle', function() {
		var $cb = $( this ), id = $cb.data( 'id' ), field = $cb.data( 'field' ), val = $cb.is( ':checked' ) ? 1 : 0;
		catalog_ajax( { action: 'ecv2_category_toggle', wp_easycart_nonce: NONCES.inline_update, category_id: id, field: field, value: val }, function() {
			$( '.ecv2-category-toggle[data-id="' + id + '"][data-field="' + field + '"]' ).prop( 'checked', !! val );
			if ( field === 'is_active' ) {
				$( '.ecv2-row[data-id="' + id + '"], .ecv2-card[data-id="' + id + '"]' ).toggleClass( 'ecv2-row-inactive ecv2-card-inactive', ! val );
			}
			ecv2_toast( _t( 'saved', 'Saved' ), 'success' );
		}, function( msg ) { $cb.prop( 'checked', ! val ); ecv2_toast( msg, 'error' ); } );
	} );

	$( document ).on( 'change', '.ecv2-review-toggle', function() {
		var $cb = $( this ), id = $cb.data( 'id' ), val = $cb.is( ':checked' ) ? 1 : 0;
		catalog_ajax( { action: 'ecv2_review_toggle', wp_easycart_nonce: NONCES.inline_update, review_id: id, approved: val }, function() {
			$( '.ecv2-review-toggle[data-id="' + id + '"]' ).prop( 'checked', !! val );
			$( '.ecv2-row[data-id="' + id + '"]' ).toggleClass( 'ecv2-row-pending', ! val ).find( '.ecv2-cell-approved .ecv2-chip' ).remove();
			if ( ! val ) { $( '.ecv2-row[data-id="' + id + '"] .ecv2-cell-approved' ).append( ' <span class="ecv2-chip ecv2-chip-amber">' + _t( 'pending', 'Pending' ) + '</span>' ); }
			ecv2_toast( val ? _t( 'approved', 'Approved — now visible in the store' ) : _t( 'denied', 'Denied — hidden from shoppers' ), 'success' );
		}, function( msg ) { $cb.prop( 'checked', ! val ); ecv2_toast( msg, 'error' ); } );
	} );

	/* ================================================================== */
	/* Tree drag: reorder / nest ( categories + menus )                    */
	/* ================================================================== */

	/*
	 * Interaction model. One decision per pointer position, from the pointer alone:
	 *
	 *  1. The row under the pointer is resolved with closest( 'tr.ecv2-cat-row' ), so events fired on cells,
	 *     links and icons inside a row all mean "that row". Listeners live on document only for the drag.
	 *  2. Vertical band on that row:  top 25% = the gap ABOVE it, middle 50% = NEST INSIDE it ( last child ),
	 *     bottom 25% = the gap BELOW it. A row that can never take children ( a level-3 menu ) has no middle
	 *     band ( top half / bottom half ). The band the pointer is already in is widened by a few px, so a
	 *     boundary can't flicker.
	 *  3. In a gap, the depth comes from how far the pointer has moved sideways since the drag started: each
	 *     indent step ( 24px ) right is one level deeper, left one shallower ( the WordPress menu editor model ).
	 *     It is clamped to what the gap can hold: at most one deeper than the row above, never shallower than the
	 *     row below ( that row keeps its parent ). Over the dragged rows themselves the gap is their current
	 *     spot, so a sideways-only drag indents / outdents in place.
	 *  4. Limits ( menus: three levels, counting the sub-menus being moved ) are checked on the final position.
	 *     A position that breaks them paints a red "not allowed" indicator and the drop is refused.
	 *  5. A single absolutely-positioned indicator ( a line at the target depth, or an outline for nest )
	 *     floats over the table. Rows get no classes, text or borders while dragging, so nothing reflows under
	 *     the pointer; the indicator repaints only when the computed target changes.
	 *  6. Near the top / bottom of the viewport the window auto-scrolls and the target is re-evaluated.
	 *  Keyboard: focus a row's drag handle; Up / Down move among siblings, Right nests under the previous
	 *  sibling, Left moves out one level.
	 *
	 * Save payload is unchanged: menus post key / parent_key / order[]; categories category_id / parent_id / order[].
	 */

	var TREE_INDENT = 24; /* .ecv2-tree { padding-left: calc( var(--lvl) * 24px ) } */
	var TREE_HYSTERESIS = 5; /* px the current band is widened by */

	/* Loaded descendants of a row: the rows that follow it at a deeper level. "Show more" rows
	 * ( tr.ecv2-cat-more-row, one per parent with unloaded children ) travel with the block too. */
	function tree_descendants( $row ) {
		var lvl = parseInt( $row.data( 'level' ), 10 ), out = [];
		var $n = $row.next( 'tr' );
		while ( $n.length && ( $n.hasClass( 'ecv2-cat-row' ) || $n.hasClass( 'ecv2-cat-more-row' ) ) && parseInt( $n.data( 'level' ), 10 ) > lvl ) { out.push( $n[0] ); $n = $n.next( 'tr' ); }
		return $( out );
	}

	/* ---- Lazy children ( categories ): rows arrive from ecv2_category_children, 200 per call ---- */
	function tree_children_action() { return $( '.ecv2-tree-scroll' ).data( 'children-action' ) || ''; }
	function tree_load_children( $row, offset, $after ) {
		var action = tree_children_action();
		if ( ! action ) { return; }
		var id = parseInt( $row.data( 'id' ), 10 ) || 0, level = ( parseInt( $row.data( 'level' ), 10 ) || 0 ) + 1;
		if ( ! id || $row.data( 'loading' ) ) { return; }
		$row.data( 'loading', 1 ).attr( 'data-loaded', '1' );
		var cols = $row.children( 'td' ).length;
		var $loading = $( '<tr class="ecv2-cat-more-row ecv2-cat-loading-row" data-level="' + level + '"><td colspan="' + cols + '"><span class="ecv2-sub"><span class="dashicons dashicons-update ecv2-spin"></span> ' + _t( 'loading', 'Loading…' ) + '</span></td></tr>' );
		( $after && $after.length ? $after : $row ).after( $loading );
		catalog_ajax( { action: action, wp_easycart_nonce: NONCES.inline_update, parent_id: id, offset: offset || 0, level: level }, function( d ) {
			$row.removeData( 'loading' );
			var $rows = $( $.trim( d.html || '' ) );
			$loading.replaceWith( $rows );
			$rows.filter( 'tr' ).find( '.ecv2-tree' ).css( '--lvl', level );
			if ( d.more ) {
				var left = Math.max( 0, ( parseInt( $row.attr( 'data-children' ), 10 ) || 0 ) - d.next_offset );
				var more_label = left ? _t( 'show_more_children', 'Show %d more…' ).replace( '%d', left ) : _t( 'loading_more', 'Show more…' );
				var $more = $( '<tr class="ecv2-cat-more-row" data-level="' + level + '" data-parent-id="' + id + '" data-offset="' + d.next_offset + '"><td colspan="' + cols + '"><a href="#" class="ecv2-cat-more-link">' + more_label + '</a></td></tr>' );
				$rows.filter( 'tr' ).last().after( $more );
			}
			$( '.ecv2-tree-scroll[data-tree="1"] .ecv2-drag-handle' ).not( '[tabindex]' ).attr( { tabindex: 0, role: 'button', 'aria-label': _t( 'drag_keys', 'Move: arrow keys ( left / right change level )' ) } );
		}, function( msg ) {
			$row.removeData( 'loading' ).attr( 'data-loaded', '0' );
			$loading.remove();
			$row.find( '.ecv2-tree-toggle' ).first().removeClass( 'is-open' ).attr( 'aria-expanded', 'false' );
			ecv2_toast( msg, 'error' );
		} );
	}
	$( document ).on( 'click', '.ecv2-cat-more-link', function( e ) {
		e.preventDefault();
		var $more = $( this ).closest( 'tr.ecv2-cat-more-row' );
		var $parent = $( 'tr.ecv2-cat-row[data-id="' + $more.data( 'parent-id' ) + '"]' ).first();
		var offset = parseInt( $more.attr( 'data-offset' ), 10 ) || 0;
		var $prev = $more.prev( 'tr' );
		$more.remove();
		tree_load_children( $parent, offset, $prev );
	} );

	var drag = { $row: null, $block: null, rows: [], height: 0, start_x: 0, start_depth: 0, want: null, x: 0, y: 0, t: 0, band: null, target: null, sig: '', $ind: null, raf: 0 };

	function tree_kind() { return $( '.ecv2-tree-scroll' ).data( 'tree-kind' ) || 'category'; }
	/* Deepest 0-based depth a row may sit at: menus 0..2 ( three tables ), categories unlimited. */
	function tree_max_depth() { return tree_kind() === 'menu' ? 2 : 999; }
	function tree_depth( el ) { return parseInt( $( el ).data( 'level' ), 10 ) || 0; }
	function tree_key( el ) { return tree_kind() === 'menu' ? String( $( el ).data( 'id' ) ) : parseInt( $( el ).data( 'id' ), 10 ); }
	function tree_parent_key( el ) { return tree_kind() === 'menu' ? String( $( el ).data( 'parent' ) || '' ) : ( parseInt( $( el ).data( 'parent' ), 10 ) || 0 ); }
	function tree_name( el ) { return $.trim( $( el ).find( '.ecv2-title-link' ).first().text() ); }
	function tree_row_by_key( key ) { var k = String( key ), hit = null; $.each( drag.rows, function( i, el ) { if ( String( $( el ).data( 'id' ) ) === k ) { hit = el; return false; } } ); return hit; }
	function tree_in_block( el ) { return drag.$block && drag.$block.index( el ) !== -1; }
	function tree_visible( el ) { return el.getClientRects().length > 0; }

	/* Prepare the shared context for a move of $row ( used by the mouse drag and the keyboard ). */
	function tree_context( $row ) {
		drag.$row = $row;
		drag.$block = $row.add( tree_descendants( $row ) );
		drag.rows = $( 'tr.ecv2-cat-row' ).get();
		var base = tree_depth( $row[0] ), max = base;
		drag.$block.each( function() { max = Math.max( max, tree_depth( this ) ); } );
		drag.height = max - base; /* 0 = leaf, 1 = has children, 2 = has grandchildren */
	}

	/* Nearest visible, non-dragged row before index i ( exclusive ), or null. */
	function tree_prev_visible( i ) {
		for ( var j = i - 1; j >= 0; j-- ) { if ( ! tree_in_block( drag.rows[ j ] ) && tree_visible( drag.rows[ j ] ) ) { return drag.rows[ j ]; } }
		return null;
	}
	/* First non-dragged row after index i ( exclusive ) whose depth is <= depth, or null ( = end of the list ). */
	function tree_next_at_or_above( i, depth ) {
		for ( var j = i + 1; j < drag.rows.length; j++ ) { if ( ! tree_in_block( drag.rows[ j ] ) && tree_depth( drag.rows[ j ] ) <= depth ) { return drag.rows[ j ]; } }
		return null;
	}
	/* The row a new row at `depth` inserted before `before` ( null = end ) would hang under. */
	function tree_parent_for( before, depth ) {
		if ( depth <= 0 ) { return null; }
		var i = before ? drag.rows.indexOf( before ) : drag.rows.length;
		for ( var j = i - 1; j >= 0; j-- ) {
			var el = drag.rows[ j ];
			if ( tree_in_block( el ) ) { continue; }
			var d = tree_depth( el );
			if ( d === depth - 1 ) { return el; }
			if ( d < depth - 1 ) { return null; }
		}
		return null;
	}

	/* Depth the pointer asks for, from sideways travel since dragstart ( with hysteresis ). */
	function tree_wanted_depth( x ) {
		var raw = drag.start_depth + ( x - drag.start_x ) / TREE_INDENT;
		raw = Math.max( 0, Math.min( tree_max_depth(), raw ) );
		var d = Math.round( raw );
		if ( drag.want !== null && Math.abs( raw - drag.want ) < 0.7 ) { d = drag.want; }
		drag.want = d;
		return d;
	}

	/* Target: the gap immediately before `before` ( a visible row, or null = after the last row ). */
	function tree_gap_target( before, x ) {
		var i = before ? drag.rows.indexOf( before ) : drag.rows.length;
		var above = tree_prev_visible( i );
		if ( ! above && ! before ) { return null; }
		var lo = before ? tree_depth( before ) : 0, hi = above ? tree_depth( above ) + 1 : 0;
		var t = { mode: 'gap', before: before, above: above, depth: Math.max( lo, Math.min( hi, tree_wanted_depth( x ) ) ), denied: false };
		var cap = tree_max_depth() - drag.height;
		if ( t.depth > cap ) { if ( lo <= cap ) { t.depth = cap; } else { t.denied = true; } }
		t.parent = tree_parent_for( before, t.depth );
		return t;
	}

	/* Target: nest inside `row` as its last child. */
	function tree_into_target( row ) {
		var d = tree_depth( row ) + 1;
		return { mode: 'into', row: row, parent: row, depth: d, before: tree_next_at_or_above( drag.rows.indexOf( row ), d - 1 ), denied: d + drag.height > tree_max_depth() };
	}

	function tree_evaluate( x, y, el ) {
		var row = el && el.closest ? el.closest( 'tr.ecv2-cat-row' ) : null;
		if ( row && drag.rows.indexOf( row ) === -1 ) { row = null; }
		if ( ! row ) {
			/* Not on a row ( e.g. the pointer slid left of the table while outdenting ): use the row at that height.
			   Inside the tree area, above the first row / below the last row still counts. */
			var vis = $.grep( drag.rows, tree_visible );
			if ( ! vis.length ) { return null; }
			for ( var v = 0; v < vis.length; v++ ) { var vr = vis[ v ].getBoundingClientRect(); if ( y >= vr.top && y < vr.bottom ) { row = vis[ v ]; break; } }
			if ( ! row ) {
				if ( ! el || ! el.closest || ! el.closest( '.ecv2-tree-scroll' ) ) { return null; }
				var rest = $.grep( vis, function( r ) { return ! tree_in_block( r ); } );
				if ( ! rest.length ) { return null; }
				drag.band = null;
				if ( y < rest[0].getBoundingClientRect().top ) { return tree_gap_target( rest[0], x ); }
				if ( y >= rest[ rest.length - 1 ].getBoundingClientRect().bottom ) { return tree_gap_target( null, x ); }
				return drag.target;
			}
		}
		if ( tree_in_block( row ) ) {
			/* Over the dragged rows: the gap they already occupy ( sideways movement indents / outdents in place ). */
			drag.band = null;
			var after = drag.rows.indexOf( drag.$block.last()[0] ) + 1;
			return tree_gap_target( after < drag.rows.length ? drag.rows[ after ] : null, x );
		}
		var rect = row.getBoundingClientRect(), h = rect.height, yy = y - rect.top;
		var can_nest = tree_depth( row ) + 1 <= tree_max_depth();
		var top_edge = can_nest ? h * 0.25 : h * 0.5, bot_edge = can_nest ? h * 0.75 : h * 0.5;
		var prev = drag.band && drag.band.row === row ? drag.band.name : '';
		if ( prev === 'before' ) { top_edge += TREE_HYSTERESIS; }
		else if ( prev === 'after' ) { bot_edge -= TREE_HYSTERESIS; }
		else if ( prev === 'into' ) { top_edge -= TREE_HYSTERESIS; bot_edge += TREE_HYSTERESIS; }
		if ( top_edge > bot_edge ) { if ( prev === 'after' ) { top_edge = bot_edge; } else { bot_edge = top_edge; } }
		var band = yy < top_edge ? 'before' : ( yy >= bot_edge ? 'after' : 'into' );
		drag.band = { row: row, name: band };
		if ( band === 'into' ) { return tree_into_target( row ); }
		if ( band === 'before' ) { return tree_gap_target( row, x ); }
		/* Gap below the row = gap above the next visible row that isn't being dragged. */
		var i = drag.rows.indexOf( row ), next = null;
		for ( var j = i + 1; j < drag.rows.length; j++ ) { if ( ! tree_in_block( drag.rows[ j ] ) && tree_visible( drag.rows[ j ] ) ) { next = drag.rows[ j ]; break; } }
		return tree_gap_target( next, x );
	}

	function tree_label( t ) {
		if ( t.denied ) { return tree_kind() === 'menu' ? _t( 'drop_too_deep', 'Too deep — menus go three levels, counting sub-menus' ) : _t( 'drop_not_allowed', 'Can’t drop here' ); }
		if ( t.mode === 'into' ) { return _t( 'drop_nest', 'Nest under “%s”' ).replace( '%s', tree_name( t.row ) ); }
		return t.parent ? _t( 'drop_inside', 'Inside “%s”' ).replace( '%s', tree_name( t.parent ) ) : _t( 'drop_top', 'Top level' );
	}

	function tree_paint( t ) {
		if ( ! drag.$ind ) { drag.$ind = $( '<div class="ecv2-tree-drop" aria-hidden="true"><span class="ecv2-tree-drop-label"></span></div>' ).appendTo( 'body' ); }
		var $i = drag.$ind;
		if ( ! t ) { $i.hide(); return; }
		var sx = window.pageXOffset, sy = window.pageYOffset;
		var table = drag.$row.closest( 'table' )[0].getBoundingClientRect();
		if ( t.mode === 'into' ) {
			var r = t.row.getBoundingClientRect();
			$i.attr( 'class', 'ecv2-tree-drop is-into' + ( t.denied ? ' is-denied' : '' ) ).css( { left: r.left + sx, top: r.top + sy, width: r.width, height: r.height } );
		} else {
			var y = t.before ? t.before.getBoundingClientRect().top : t.above.getBoundingClientRect().bottom;
			var tree = $( drag.$row[0] ).find( '.ecv2-tree' )[0];
			var left = ( tree ? tree.getBoundingClientRect().left : table.left ) + t.depth * TREE_INDENT;
			$i.attr( 'class', 'ecv2-tree-drop is-line' + ( t.denied ? ' is-denied' : '' ) ).css( { left: left + sx, top: y + sy, width: Math.max( 40, table.right - left ), height: '' } );
		}
		$i.find( '.ecv2-tree-drop-label' ).text( tree_label( t ) );
		$i.show();
	}

	function tree_set_target( t ) {
		drag.target = t;
		var sig = t ? [ t.mode, t.before ? $( t.before ).data( 'id' ) : 'end', t.depth, t.row ? $( t.row ).data( 'id' ) : '', t.denied ? 1 : 0 ].join( '|' ) : '';
		if ( sig === drag.sig ) { return; }
		drag.sig = sig;
		tree_paint( t );
	}

	function tree_autoscroll() {
		drag.raf = 0;
		if ( ! drag.$row ) { return; }
		if ( Date.now() - drag.t < 400 ) {
			var zone = 70, top = ( $( '#wpadminbar' ).outerHeight() || 0 ) + zone, bottom = window.innerHeight - zone, dy = 0;
			if ( drag.y < top ) { dy = -Math.ceil( 18 * Math.min( 1, ( top - drag.y ) / zone ) ); }
			else if ( drag.y > bottom ) { dy = Math.ceil( 18 * Math.min( 1, ( drag.y - bottom ) / zone ) ); }
			if ( dy ) {
				var was = window.pageYOffset;
				window.scrollBy( 0, dy );
				if ( window.pageYOffset !== was ) {
					/* rows moved under a still pointer: re-evaluate and reposition */
					drag.sig = '';
					tree_set_target( tree_evaluate( drag.x, drag.y, document.elementFromPoint( drag.x, drag.y ) ) );
				}
			}
		}
		drag.raf = window.requestAnimationFrame( tree_autoscroll );
	}

	function tree_on_dragover( e ) {
		if ( ! drag.$row ) { return; }
		var oe = e.originalEvent;
		drag.x = oe.clientX; drag.y = oe.clientY; drag.t = Date.now();
		var t = tree_evaluate( drag.x, drag.y, e.target );
		tree_set_target( t );
		if ( t && ! t.denied ) { e.preventDefault(); oe.dataTransfer.dropEffect = 'move'; }
		else if ( oe.dataTransfer ) { oe.dataTransfer.dropEffect = 'none'; } /* no preventDefault: native not-allowed cursor, no drop */
	}

	function tree_cleanup() {
		$( document ).off( '.ecv2tree' );
		if ( drag.raf ) { window.cancelAnimationFrame( drag.raf ); }
		if ( drag.$block ) { drag.$block.removeClass( 'is-dragging' ); }
		if ( drag.$ind ) { drag.$ind.remove(); }
		drag = { $row: null, $block: null, rows: [], height: 0, start_x: 0, start_depth: 0, want: null, x: 0, y: 0, t: 0, band: null, target: null, sig: '', $ind: null, raf: 0 };
	}

	function tree_toggle_html() { return '<button type="button" class="ecv2-tree-toggle is-open" aria-expanded="true" onclick="ecv2_catalog.toggle_tree( this );"><span class="dashicons dashicons-arrow-right-alt2"></span></button>'; }

	/* Apply target t to the context row: move the DOM block, re-level it, save. Returns false for a no-op. */
	function tree_commit( t ) {
		var kind = tree_kind(), move_action = $( '.ecv2-tree-scroll' ).data( 'move-action' ) || 'ecv2_category_move';
		var $row = drag.$row, $block = drag.$block;
		var old_depth = tree_depth( $row[0] ), old_parent = tree_parent_key( $row[0] );
		var new_parent = t.parent ? tree_key( t.parent ) : ( kind === 'menu' ? '' : 0 );
		var next_after = $block.last().next( 'tr.ecv2-cat-row' )[0] || null;
		if ( String( new_parent ) === String( old_parent ) && t.depth === old_depth && ( t.before || null ) === next_after ) { return false; }
		var old_parent_row = old_parent ? tree_row_by_key( old_parent ) : null;

		if ( t.before ) { $( t.before ).before( $block ); }
		else { $( drag.rows ).not( $block ).last().after( $block ); }

		var delta = t.depth - old_depth;
		$block.each( function() {
			var $r = $( this ), l = tree_depth( this ) + delta;
			$r.attr( 'data-level', l ).data( 'level', l ).find( '.ecv2-tree' ).first().css( '--lvl', l );
		} );
		$row.attr( 'data-parent', new_parent ).data( 'parent', new_parent );
		var lazy_parent = null;
		if ( t.parent ) {
			var $p = $( t.parent ), $pt = $p.find( '.ecv2-tree-toggle' ).first();
			if ( $p.attr( 'data-loaded' ) === '0' ) { lazy_parent = $p; } /* children never rendered: fetch them after the save so the moved row lands in server order */
			else if ( ! $p.hasClass( 'has-children' ) || $pt.hasClass( 'is-leaf' ) ) { $p.addClass( 'has-children' ); $pt.replaceWith( tree_toggle_html() ); }
			else if ( ! $pt.hasClass( 'is-open' ) ) { window.ecv2_catalog.toggle_tree( $pt[0] ); } /* collapsed parent: open it so the moved row stays visible */
		}
		if ( old_parent_row && old_parent_row !== t.parent && ! tree_descendants( $( old_parent_row ) ).length ) {
			$( old_parent_row ).removeClass( 'has-children' ).find( '.ecv2-tree-toggle' ).first().replaceWith( '<span class="ecv2-tree-toggle is-leaf"></span>' );
		}

		var order = [];
		$( 'tr.ecv2-cat-row' ).each( function() { if ( String( tree_parent_key( this ) ) === String( new_parent ) ) { order.push( tree_key( this ) ); } } );
		var payload = kind === 'menu'
			? { action: move_action, wp_easycart_nonce: NONCES.inline_update, key: tree_key( $row[0] ), parent_key: new_parent, order: order }
			: { action: move_action, wp_easycart_nonce: NONCES.inline_update, category_id: tree_key( $row[0] ), parent_id: new_parent, order: order };
		var parent_changed = String( new_parent ) !== String( old_parent ), parent_name = t.parent ? tree_name( t.parent ) : '';
		catalog_ajax( payload, function( d ) {
			var msg = parent_changed && parent_name ? _t( 'nested', 'Moved into “%s”' ).replace( '%s', parent_name )
				: ( delta && 0 === t.depth ? _t( 'now_top', 'Moved to the top level' )
				: ( delta ? _t( 'relevelled', 'Moved to level %d' ).replace( '%d', t.depth + 1 ) : _t( 'reordered', 'Order saved' ) ) );
			ecv2_toast( msg, 'success' );
			if ( lazy_parent ) {
				$block.remove();
				lazy_parent.find( '.ecv2-tree-toggle' ).first().addClass( 'is-open' ).attr( 'aria-expanded', 'true' );
				tree_load_children( lazy_parent, 0 );
			}
			/* a menu that changed level has new ids ( it moved tables ) — refresh so keys, links and counts are right */
			if ( kind === 'menu' && d && d.level_changed ) { setTimeout( function() { window.location.reload(); }, 700 ); }
		}, function( msg ) { ecv2_toast( msg, 'error' ); setTimeout( function() { window.location.reload(); }, 900 ); } );
		return true;
	}

	$( document ).on( 'dragstart', 'tr.ecv2-cat-row', function( e ) {
		if ( $( '.ecv2-tree-scroll' ).data( 'tree' ) !== 1 ) { e.preventDefault(); return; }
		tree_cleanup();
		var oe = e.originalEvent;
		tree_context( $( this ) );
		drag.start_x = oe.clientX; drag.start_depth = tree_depth( this );
		drag.$block.addClass( 'is-dragging' );
		oe.dataTransfer.effectAllowed = 'move';
		try { oe.dataTransfer.setData( 'text/plain', String( $( this ).data( 'id' ) ) ); } catch ( err ) {}
		$( document )
			.on( 'dragover.ecv2tree', tree_on_dragover )
			.on( 'drop.ecv2tree', function( ev ) {
				ev.preventDefault();
				var t = drag.target;
				if ( t && ! t.denied ) { tree_commit( t ); }
				tree_cleanup();
			} )
			.on( 'dragend.ecv2tree', tree_cleanup );
		drag.raf = window.requestAnimationFrame( tree_autoscroll );
	} );

	/* Keyboard: drag handles are focusable; arrows move the focused row. */
	$( '.ecv2-tree-scroll[data-tree="1"] .ecv2-drag-handle' ).attr( { tabindex: 0, role: 'button', 'aria-label': _t( 'drag_keys', 'Move: arrow keys ( left / right change level )' ) } );
	$( document ).on( 'keydown', '.ecv2-tree-scroll[data-tree="1"] .ecv2-drag-handle', function( e ) {
		var k = e.key;
		if ( [ 'ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight' ].indexOf( k ) === -1 || drag.target ) { return; }
		e.preventDefault();
		var $row = $( this ).closest( 'tr.ecv2-cat-row' );
		if ( ! $row.length ) { return; }
		tree_context( $row );
		var i = drag.rows.indexOf( $row[0] ), last = drag.rows.indexOf( drag.$block.last()[0] ), d = tree_depth( $row[0] );
		var parent = tree_parent_for( $row[0], d ), sib = null, j, t = null;
		if ( k === 'ArrowUp' || k === 'ArrowRight' ) {
			for ( j = i - 1; j >= 0; j-- ) { if ( tree_depth( drag.rows[ j ] ) <= d ) { sib = tree_depth( drag.rows[ j ] ) === d ? drag.rows[ j ] : null; break; } }
		}
		if ( k === 'ArrowUp' && sib ) { t = { mode: 'gap', before: sib, depth: d, parent: parent }; }
		if ( k === 'ArrowRight' && sib ) { t = { mode: 'gap', before: drag.rows[ last + 1 ] || null, depth: d + 1, parent: sib }; }
		if ( k === 'ArrowDown' ) {
			var nx = drag.rows[ last + 1 ];
			if ( nx && tree_depth( nx ) === d ) { t = { mode: 'gap', before: tree_next_at_or_above( drag.rows.indexOf( nx ), d ), depth: d, parent: parent }; }
		}
		if ( k === 'ArrowLeft' && parent ) { t = { mode: 'gap', before: tree_next_at_or_above( drag.rows.indexOf( parent ), d - 1 ), depth: d - 1, parent: tree_parent_for( parent, d - 1 ) }; }
		if ( t && t.depth + drag.height > tree_max_depth() ) { ecv2_toast( _t( 'drop_too_deep', 'Too deep — menus go three levels, counting sub-menus' ), 'info' ); t = null; }
		var $handle = $( this );
		if ( t ) { tree_commit( t ); }
		drag = { $row: null, $block: null, rows: [], height: 0, start_x: 0, start_depth: 0, want: null, x: 0, y: 0, t: 0, band: null, target: null, sig: '', $ind: null, raf: 0 };
		$handle.trigger( 'focus' );
	} );


	/* ================================================================== */
	/* Spreadsheet inline edit ( option set name / label )                 */
	/* ================================================================== */

	$( document ).on( 'dblclick', '.ecv2-ss-cell.ecv2-ss-editable', function() {
		var $cell = $( this );
		var $wrap = $cell.closest( '.ecv2-wrap' ), tid = $wrap.data( 'table-id' );
		var generic = { ec_admin_option_list_v2: [ 'ecv2_option_inline_update', 'option_id' ], ec_admin_manufacturer_list_v2: [ 'ecv2_manufacturer_inline_update', 'manufacturer_id' ], ec_admin_subscriber_list_v2: [ 'ecv2_subscriber_inline_update', 'subscriber_id' ] };
		if ( $cell.hasClass( 'ecv2-ss-editing' ) || ! generic[ tid ] ) { return; }
		var inline_action = generic[ tid ][0], id_key = generic[ tid ][1];
		var field = $cell.data( 'field' ), row_id = $cell.closest( '.ecv2-ss-row' ).data( 'id' ), current = $.trim( $cell.text() );
		$cell.addClass( 'ecv2-ss-editing' );
		var $input = $( '<input type="text" class="ecv2-ss-edit-input" />' ).val( current );
		$cell.empty().append( $input ); $input.trigger( 'focus' ).select();
		function finish( text ) { $cell.removeClass( 'ecv2-ss-editing' ).text( text ); }
		function save() {
			var v = $.trim( $input.val() );
			if ( v === current || ( v === '' && field === 'option_name' ) ) { finish( current ); return; }
			finish( v );
			var req = { action: inline_action, wp_easycart_nonce: NONCES.inline_update, field: field, value: v }; req[ id_key ] = row_id;
			catalog_ajax( req, function( d ) {
				finish( d.display_value );
				if ( field === 'option_name' ) { $( '.ecv2-row[data-id="' + row_id + '"] .ecv2-title-link' ).text( d.display_value ); }
				ecv2_toast( _t( 'saved', 'Saved' ), 'success' );
				ecv2_push_undo( { message: _t( 'field_updated', 'Field updated — click Undo to revert.' ), undo_fn: function() {
					var undo_req = { action: inline_action, wp_easycart_nonce: NONCES.inline_update, field: field, value: d.old_value }; undo_req[ id_key ] = row_id;
					catalog_ajax( undo_req, function() { $cell.text( d.old_value ); ecv2_toast( _t( 'reverted', 'Reverted' ), 'success' ); } );
				} } );
			}, function( msg ) { finish( current ); ecv2_toast( msg, 'error' ); } );
		}
		$input.on( 'blur', save ).on( 'keydown', function( e ) { if ( e.key === 'Enter' ) { $input.off( 'blur' ); save(); } if ( e.key === 'Escape' ) { $input.off( 'blur' ); finish( current ); } } );
	} );

	/* ================================================================== */
	/* ecv2_catalog — modals shared by lists and editors                   */
	/* ================================================================== */

	/**
	 * Dialog. opts ( or a legacy extra_class string ):
	 *   anchor  — element that opened it; on desktop the dialog becomes a popover under that element,
	 *             right-aligned to it ( so "Add …" buttons in the page header open right where they are ).
	 *   sheet   — on narrow screens ( ≤ 720px ) every dialog is a bottom sheet; pass false to keep it centered.
	 *   size    — 'sm' | 'md' ( default ) | 'lg'
	 */
	function modal( id, title, body_html, footer_html, opts ) {
		close_modal();
		if ( typeof opts === 'string' ) { opts = { extra_class: opts }; }
		opts = $.extend( { anchor: null, sheet: true, size: 'md', extra_class: '' }, opts || {} );
		if ( /ecv2-modal-sm/.test( opts.extra_class ) ) { opts.size = 'sm'; }
		var narrow = window.innerWidth <= 720;
		var mode = narrow ? ( opts.sheet ? 'sheet' : 'center' ) : ( opts.anchor ? 'popover' : 'center' );
		var $m = $(
			'<div class="ecv2-modal-overlay ecv2-catalog-modal is-' + mode + ' ecv2-size-' + opts.size + ' ' + opts.extra_class + '" id="' + id + '" role="dialog" aria-modal="true" aria-labelledby="' + id + '_title">' +
				'<div class="ecv2-modal">' +
					'<div class="ecv2-modal-header"><h2 id="' + id + '_title">' + ecv2_esc_html( title ) + '</h2><button type="button" class="ecv2-modal-close" data-close aria-label="Close">&times;</button></div>' +
					'<div class="ecv2-modal-body">' + body_html + '</div>' +
					'<div class="ecv2-modal-footer"><div class="ecv2-modal-footer-right">' + footer_html + '</div></div>' +
				'</div>' +
			'</div>'
		);
		$( 'body' ).append( $m );
		if ( mode === 'popover' ) { place_popover( $m, opts.anchor ); }
		requestAnimationFrame( function() { $m.addClass( 'is-open' ); } );
		$m.on( 'click', function( e ) { if ( $( e.target ).is( $m ) || $( e.target ).is( '[data-close]' ) ) { close_modal(); } } );
		$( document ).on( 'keydown.ecv2modal', function( e ) { if ( e.key === 'Escape' ) { close_modal(); } } );
		if ( mode === 'popover' ) { $( window ).on( 'resize.ecv2modal scroll.ecv2modal', function() { place_popover( $m, opts.anchor ); } ); }
		setTimeout( function() { $m.find( 'input, select, textarea' ).filter( ':visible' ).first().trigger( 'focus' ); }, 60 );
		return $m;
	}
	/* Right edge aligned to the anchor's right edge, just below it; flips above if there's no room; clamps to the viewport. */
	function place_popover( $m, anchor ) {
		var $box = $m.find( '.ecv2-modal' ), r = anchor.getBoundingClientRect(), w = $box.outerWidth(), h = $box.outerHeight(), gap = 8, pad = 12;
		var left = Math.max( pad, Math.min( r.right - w, window.innerWidth - w - pad ) );
		var top = r.bottom + gap, flip = false;
		if ( top + h > window.innerHeight - pad && r.top - gap - h > pad ) { top = r.top - gap - h; flip = true; }
		top = Math.max( pad, top );
		$box.css( { left: left, top: top } ).toggleClass( 'is-flipped', flip );
		var arrow = Math.max( 18, Math.min( w - 18, ( r.left + r.width / 2 ) - left ) );
		$box.css( '--ecv2-arrow-x', arrow + 'px' );
	}
	function close_modal() { $( document ).off( 'keydown.ecv2modal' ); $( window ).off( 'resize.ecv2modal scroll.ecv2modal' ); $( '.ecv2-catalog-modal' ).remove(); }
	$( document ).on( 'keydown', function( e ) { if ( e.key === 'Escape' ) { close_modal(); } } );

	function esc_attr( s ) { return ecv2_esc_html( s ); }
	function thumb_html( url ) { return url ? '<span class="ecv2-thumb"><img src="' + ecv2_esc_html( url ) + '" alt=""></span>' : '<span class="ecv2-thumb"><span class="dashicons dashicons-format-image"></span></span>'; }
	function n( v ) { return parseInt( v, 10 ) || 0; }

	/* ------------------------------------------------------------------ */
	/* Category typeahead ( ecv2_category_search, 20 matches per request )  */
	/* ------------------------------------------------------------------ */

	/*
	 * Mounts a search-as-you-type parent picker into $mount. The picker keeps a hidden
	 * input with the chosen id ( 0 = top level ). Options: id ( element id for the hidden
	 * input ), value / label ( preselected category ), exclude ( ids never offered ),
	 * onpick( id, label ). Reuses the product editor's .ecdv2-cat-combo styles.
	 */
	function cat_picker( $mount, opts ) {
		opts = opts || {};
		var uid = opts.id || ( 'ecv2_catpick_' + Math.floor( Math.random() * 1e6 ) );
		var $wrap = $( '<div class="ecdv2-cat-combo ecv2-cat-picker"></div>' );
		var $input = $( '<input type="text" class="ecv2-input" autocomplete="off">' ).attr( 'placeholder', _t( 'top_level', '— Top level —' ) );
		var $hidden = $( '<input type="hidden">' ).attr( 'id', uid ).val( n( opts.value ) );
		var $menu = $( '<div class="ecdv2-cat-menu" style="display:none"></div>' );
		$wrap.append( $input, $hidden, $menu ).appendTo( $mount );
		if ( n( opts.value ) && opts.label ) { $input.val( opts.label ); }
		var timer = null, seq = 0, exclude = $.map( opts.exclude || [], n );
		function pick( id, label ) {
			$hidden.val( id ); $input.val( id ? label : '' ); $menu.hide();
			if ( typeof opts.onpick === 'function' ) { opts.onpick( id, label ); }
		}
		function paint( term, results ) {
			var html = '', first = true;
			if ( ! term ) { html += '<button type="button" class="ecdv2-cat-menu-item is-active" data-id="0">' + _t( 'top_level', '— Top level —' ) + '</button>'; first = false; }
			$.each( results || [], function( i, r ) {
				if ( $.inArray( n( r.id ), exclude ) !== -1 ) { return; }
				html += '<button type="button" class="ecdv2-cat-menu-item' + ( first ? ' is-active' : '' ) + '" data-id="' + n( r.id ) + '">' + ecv2_esc_html( r.name ) + '</button>';
				first = false;
			} );
			if ( term && first ) { html += '<div class="ecdv2-cat-menu-none">' + _t( 'no_categories_match', 'No matching categories.' ) + '</div>'; }
			if ( ! term ) { html += '<div class="ecdv2-cat-menu-none">' + _t( 'type_to_search', 'Type to search categories…' ) + '</div>'; }
			$menu.html( html ).show();
		}
		function search() {
			var term = $.trim( $input.val() ), my = ++seq;
			clearTimeout( timer );
			if ( ! term ) { paint( '', [] ); return; }
			$menu.html( '<div class="ecdv2-cat-menu-none">' + _t( 'searching', 'Searching…' ) + '</div>' ).show();
			timer = setTimeout( function() {
				$.post( AJAX_URL, { action: 'ecv2_category_search', search: term, wp_easycart_nonce: NONCES.category_search }, function( r ) {
					if ( my !== seq ) { return; }
					paint( term, ( r && r.success && r.data ) ? r.data.results : [] );
				}, 'json' ).fail( function() { if ( my === seq ) { paint( term, [] ); } } );
			}, 250 );
		}
		$input.on( 'input', function() { $hidden.val( 0 ); search(); } ); /* typing discards the previous pick */
		$input.on( 'focus', function() { search(); } );
		$input.on( 'keydown', function( e ) {
			var $items = $menu.find( '.ecdv2-cat-menu-item' ), $act = $items.filter( '.is-active' );
			if ( e.key === 'ArrowDown' || e.key === 'ArrowUp' ) {
				e.preventDefault();
				var i = $items.index( $act );
				i = e.key === 'ArrowDown' ? Math.min( i + 1, $items.length - 1 ) : Math.max( i - 1, 0 );
				$items.removeClass( 'is-active' ).eq( i ).addClass( 'is-active' );
			} else if ( e.key === 'Enter' ) {
				e.preventDefault();
				var $hit = $act.length ? $act : $items.first();
				if ( $hit.length ) { pick( n( $hit.data( 'id' ) ), $hit.text() ); }
			} else if ( e.key === 'Escape' ) { $menu.hide(); }
		} );
		$menu.on( 'mousedown', '.ecdv2-cat-menu-item', function( e ) { e.preventDefault(); pick( n( $( this ).data( 'id' ) ), $( this ).text() ); } );
		$input.on( 'blur', function() { setTimeout( function() { $menu.hide(); if ( ! n( $hidden.val() ) ) { $input.val( '' ); } }, 150 ); } );
		return { val: function() { return n( $hidden.val() ); }, $input: $input };
	}

	/* Category editor: the parent <select> holds only the current parent; swap in the typeahead. */
	if ( VARS.mode === 'category-editor' ) {
		$( function() {
			var $sel = $( '#eccat_parent' );
			if ( ! $sel.length || $sel.data( 'ecv2-picker' ) ) { return; }
			var self_id = n( $( '[data-category-id]' ).first().data( 'category-id' ) ) || n( ( window.location.search.match( /[?&]category_id=(\d+)/ ) || [] )[1] );
			var $cur = $sel.find( 'option:selected' );
			var $mount = $( '<div></div>' ).insertAfter( $sel );
			$sel.hide().data( 'ecv2-picker', 1 );
			cat_picker( $mount, {
				value: n( $cur.val() ), label: n( $cur.val() ) ? $cur.text() : '', exclude: self_id ? [ self_id ] : [],
				onpick: function( id, label ) {
					if ( id && ! $sel.find( 'option[value="' + id + '"]' ).length ) { $sel.append( $( '<option>' ).val( id ).text( label ) ); }
					$sel.val( String( id ) ).trigger( 'change' );
				}
			} );
		} );
	}

	window.ecv2_catalog_modal = modal; window.ecv2_catalog_close_modal = close_modal;
	window.ecv2_catalog = {

		toggle_tree: function( btn ) {
			var $b = $( btn ), $row = $b.closest( 'tr' ), open = ! $b.hasClass( 'is-open' );
			$b.toggleClass( 'is-open', open ).attr( 'aria-expanded', open ? 'true' : 'false' );
			/* first expand of a parent whose children were never rendered: fetch them */
			if ( open && $row.attr( 'data-loaded' ) === '0' ) { tree_load_children( $row, 0 ); return; }
			var $desc = tree_descendants( $row );
			if ( open ) {
				/* show direct children; deeper levels follow their own toggle state */
				var lvl = n( $row.data( 'level' ) );
				var hidden_under = null;
				$desc.each( function() {
					var $r = $( this ), l = n( $r.data( 'level' ) );
					if ( hidden_under !== null && l > hidden_under ) { return; }
					hidden_under = null;
					$r.show();
					if ( $r.hasClass( 'has-children' ) && ! $r.find( '.ecv2-tree-toggle' ).hasClass( 'is-open' ) ) { hidden_under = l; }
				} );
			} else { $desc.hide(); }
		},

		view_category: function( link, id ) {
			var $row = $( '.ecv2-row[data-id="' + id + '"]' );
			var slug = $row.find( '.ecv2-mono' ).first().text();
			if ( ! slug ) { ecv2_toast( _t( 'no_page', 'This category has no page yet — save it once in the editor.' ), 'info' ); return false; }
			return true;
		},

		/* ---------------- Safe delete ---------------- */
		safe_delete: function( type, id, opts ) {
			opts = opts || {};
			var $m = modal( 'ecv2-safe-delete', _t( 'analyzing', 'Checking what this affects…' ), '<div class="ecv2-sd-loading"><span class="dashicons dashicons-update ecv2-spin"></span></div>', '<button type="button" class="ecv2-btn" data-close>' + _t( 'cancel', 'Cancel' ) + '</button>', 'ecv2-sd-modal' );
			catalog_ajax( { action: 'ecv2_delete_impact', nonce: NONCES.safe_delete, type: type, id: id }, function( r ) {
				$m.find( '.ecv2-modal-header h2' ).text( _t( 'delete_q', 'Delete “%s”?' ).replace( '%s', r.name ) );
				var html = '<div class="ecv2-sd-impacts">';
				$.each( r.impacts, function( i, im ) {
					html += '<div class="ecv2-sd-impact is-' + im.severity + '"><span class="ecv2-sd-count">' + im.count + '</span><div class="ecv2-sd-impact-main"><b>' + ecv2_esc_html( im.label ) + '</b><span>' + ecv2_esc_html( im.detail ) + ( im.sample && im.sample.length ? ' <em>' + ecv2_esc_html( im.sample.join( ', ' ) ) + ( im.count > im.sample.length ? ' …' : '' ) + '</em>' : '' ) + ( im.link ? ' <a href="' + ecv2_esc_html( im.link ) + '" target="_blank" rel="noopener">' + _t( 'view', 'View' ) + '</a>' : '' ) + '</span></div></div>';
				} );
				html += '</div><div class="ecv2-sd-strategies">';
				var first = null;
				$.each( r.strategies, function( i, s ) {
					if ( first === null && ! s.disabled ) { first = s.key; }
					html += '<label class="ecv2-sd-strategy' + ( s.disabled ? ' is-disabled' : '' ) + ( s.danger ? ' is-danger' : '' ) + '"><input type="radio" name="ecv2_sd_strategy" value="' + s.key + '"' + ( s.disabled ? ' disabled' : '' ) + ( s.recommended && ! s.disabled ? ' checked' : '' ) + '><div><b>' + ecv2_esc_html( s.label ) + ( s.recommended ? ' <span class="ecv2-chip ecv2-chip-green">' + _t( 'recommended', 'Recommended' ) + '</span>' : '' ) + '</b><span>' + ecv2_esc_html( s.description ) + '</span>';
					if ( s.requires_target ) {
						html += '<select class="ecv2-select ecv2-sd-target" data-for="' + s.key + '"' + ( s.disabled ? ' disabled' : '' ) + '>';
						$.each( s.targets || [], function( j, t ) { html += '<option value="' + n( t.value ) + '">' + ecv2_esc_html( t.label ) + '</option>'; } );
						html += '</select>' + ( s.disabled ? '<small>' + _t( 'no_targets', 'Nothing to move to yet.' ) + '</small>' : '' );
					}
					html += '</div></label>';
				} );
				html += '</div>';
				if ( r.redirect && r.redirect.available ) {
					html += '<label class="ecv2-sd-redirect"><input type="checkbox" id="ecv2_sd_redirect" checked> <span><b>' + _t( 'redirect', 'Redirect the old URL' ) + '</b><small>' + ecv2_esc_html( r.redirect.from ) + ' → ' + _t( 'redirect_to', 'the target category, or the store page' ) + '</small></span></label>';
				}
				html += '<p class="ecv2-sd-undo"><span class="dashicons dashicons-backup"></span> ' + _t( 'undo_note', 'You can undo this for %d days from Store Status › Recently deleted.' ).replace( '%d', r.undo_days ) + '</p>';
				$m.find( '.ecv2-modal-body' ).html( html );
				if ( ! $m.find( 'input[name="ecv2_sd_strategy"]:checked' ).length && first ) { $m.find( 'input[name="ecv2_sd_strategy"][value="' + first + '"]' ).prop( 'checked', true ); }
				$m.find( '.ecv2-modal-footer-right' ).html( '<button type="button" class="ecv2-btn" data-close>' + _t( 'cancel', 'Cancel' ) + '</button><button type="button" class="ecv2-btn ecv2-btn-danger" id="ecv2_sd_go">' + _t( 'delete', 'Delete' ) + '</button>' );
				$( '#ecv2_sd_go' ).on( 'click', function() {
					var strategy = $m.find( 'input[name="ecv2_sd_strategy"]:checked' ).val();
					if ( ! strategy ) { return; }
					var target = $m.find( '.ecv2-sd-target[data-for="' + strategy + '"]' ).val() || 0;
					var redirect = $( '#ecv2_sd_redirect' ).length ? ( $( '#ecv2_sd_redirect' ).is( ':checked' ) ? 1 : 0 ) : 0;
					$( this ).prop( 'disabled', true ).text( _t( 'deleting', 'Deleting…' ) );
					catalog_ajax( { action: 'ecv2_delete_execute', nonce: NONCES.safe_delete, type: type, id: id, strategy: strategy, target_id: target, redirect: redirect }, function( d ) {
						close_modal();
						if ( opts.redirect_to ) {
							try { sessionStorage.setItem( 'ecv2_catalog_flash', JSON.stringify( { message: d.message, trash_id: d.trash_id } ) ); } catch ( err ) {}
							window.location.href = opts.redirect_to; return;
						}
						/* opts.row: the clicked row, for lists whose data-id is not the numeric id ( menus use "level:id" ). */
						var $row = ( opts.row && $( opts.row ).length ) ? $( opts.row ) : $( '.ecv2-row[data-id="' + id + '"], .ecv2-card[data-id="' + id + '"]' );
						var $desc = $row.is( 'tr' ) ? tree_descendants( $row ) : $();
						$row.add( strategy === 'cascade' ? $desc : $() ).fadeOut( 200, function() { $( this ).remove(); } );
						ecv2_catalog.undo_toast( d.message, d.trash_id );
						/* A strategy with a target ( reassign products, replace a set, move into another category or menu ) changes the
						   target row's counts and may re-parent rows not rendered yet: reload so the list shows the result. */
						var chosen = $.grep( r.strategies || [], function( s ) { return s.key === strategy; } )[0];
						if ( ( chosen && chosen.requires_target ) || ( strategy !== 'cascade' && $desc.length ) ) { setTimeout( function() { window.location.reload(); }, 1800 ); }
					}, function( msg ) { $( '#ecv2_sd_go' ).prop( 'disabled', false ).text( _t( 'delete', 'Delete' ) ); ecv2_toast( msg, 'error' ); } );
				} );
			}, function( msg ) { close_modal(); ecv2_toast( msg, 'error' ); } );
		},

		undo_toast: function( message, trash_id ) {
			var $t = $( '<div class="ecv2-toast ecv2-toast-success ecv2-toast-undo"><span class="dashicons dashicons-yes"></span> <span class="ecv2-toast-msg"></span> <a href="#" class="ecv2-toast-undo-link">' + _t( 'undo', 'Undo' ) + '</a></div>' );
			$t.find( '.ecv2-toast-msg' ).text( message );
			$t.find( '.ecv2-toast-undo-link' ).on( 'click', function( e ) {
				e.preventDefault(); $t.find( 'a' ).text( _t( 'restoring', 'Restoring…' ) );
				catalog_ajax( { action: 'ecv2_delete_restore', nonce: NONCES.safe_delete, trash_id: trash_id }, function( d ) { ecv2_toast( d.message, 'success' ); setTimeout( function() { window.location.reload(); }, 600 ); } );
			} );
			$( '#ecv2-toast-container' ).append( $t );
			setTimeout( function() { $t.fadeOut( 300, function() { $( this ).remove(); } ); }, 12000 );
		},

		bulk_delete: function( type, ids ) {
			var labels = { option: _t( 'bulk_delete_options', 'Delete %d option sets?' ), category: _t( 'bulk_delete_categories', 'Delete %d categories?' ), menu: _t( 'bulk_delete_menus', 'Delete %d menus?' ), manufacturer: _t( 'bulk_delete_manufacturers', 'Delete %d manufacturers?' ), review: _t( 'bulk_delete_reviews', 'Delete %d reviews?' ) };
			var msgs = {
				option: _t( 'bulk_delete_options_msg', 'Each set is removed from its products and their variant stock rows for it are deleted. Each deletion can be undone for 30 days.' ),
				category: _t( 'bulk_delete_categories_msg', 'Products are never deleted. Subcategories move up a level and old URLs redirect to the store page. Each deletion can be undone for 30 days.' ),
				menu: _t( 'bulk_delete_menus_msg', 'Products are never deleted; their menu paths are trimmed. Sub-menus of a deleted menu are deleted too. Old URLs redirect to the store page. Undo for 30 days.' ),
				manufacturer: _t( 'bulk_delete_manufacturers_msg', 'Products keep everything except their manufacturer. Old URLs redirect to the store page. Undo for 30 days.' ),
				review: _t( 'bulk_delete_reviews_msg', 'Reviews are removed from the store immediately. Undo is available for 15 minutes.' )
			};
			var label = labels[ type ], msg = msgs[ type ];
			ecv2_show_confirm( label.replace( '%d', ids.length ), msg ).then( function( ok ) {
				if ( ! ok ) { return; }
				catalog_ajax( { action: 'ecv2_' + type + '_bulk', wp_easycart_nonce: NONCES.inline_update, ids: ids, op: 'delete' }, function( d ) {
					if ( type === 'review' && d.undo ) { ecv2_catalog.review_undo_toast( _t( 'bulk_deleted', 'Deleted %d items.' ).replace( '%d', d.done ), d.undo ); }
					else { ecv2_toast( _t( 'bulk_deleted', 'Deleted %d items.' ).replace( '%d', d.done ) + ( d.errors && d.errors.length ? ' ' + d.errors.join( ' ' ) : '' ), d.errors && d.errors.length ? 'info' : 'success' ); }
					setTimeout( function() { window.location.reload(); }, type === 'review' ? 1600 : 900 );
				} );
			} );
		},

		/* ---------------- Option set → products ---------------- */
		assign_option: function( option_id ) {
			var selected = {};
			var $m = modal( 'ecv2-assign', _t( 'assign_title', 'Assign to products' ),
				'<div class="ecv2-picker-bar"><input type="search" class="ecv2-input" id="ecv2_as_q" placeholder="' + _t( 'search_products', 'Search products by name or SKU…' ) + '"><span class="ecv2-chip ecv2-chip-gray" id="ecv2_as_count">0</span></div><div class="ecv2-picker-note" id="ecv2_as_note"></div><div class="ecv2-picker-list" id="ecv2_as_list"></div><div class="ecv2-picker-foot" id="ecv2_as_foot"></div>',
				'<button type="button" class="ecv2-btn" data-close>' + _t( 'cancel', 'Cancel' ) + '</button><button type="button" class="ecv2-btn ecv2-btn-primary" id="ecv2_as_go" disabled>' + _t( 'assign', 'Assign' ) + '</button>' );
			function load() {
				$( '#ecv2_as_list' ).html( '<div class="ecv2-sd-loading"><span class="dashicons dashicons-update ecv2-spin"></span></div>' );
				catalog_ajax( { action: 'ecv2_option_assign_search', nonce: NONCES.osv2, option_id: option_id, q: $( '#ecv2_as_q' ).val() }, function( d ) {
					/* Modifier sets ( PRO ) are appended to each product's modifier list, so slots don't apply. */
					$( '#ecv2_as_note' ).text( d.advanced ? _t( 'modifier_note', 'This modifier is added after any modifiers the product already has.' ) : _t( 'slots_note', 'Products have %d option-set slots. Products with every slot full are shown but cannot be selected.' ).replace( '%d', d.slots ) );
					var html = '';
					$.each( d.items, function( i, p ) {
						html += '<label class="ecv2-picker-row' + ( p.full ? ' is-disabled' : '' ) + ( selected[ p.id ] ? ' is-on' : '' ) + '"><input type="checkbox" value="' + p.id + '"' + ( p.full ? ' disabled' : '' ) + ( selected[ p.id ] ? ' checked' : '' ) + '>' + thumb_html( p.image ) + '<div class="ecv2-picker-main"><span>' + ecv2_esc_html( p.title ) + '</span><small>' + ecv2_esc_html( p.sku || '' ) + ( p.sets.length ? ' · ' + _t( 'uses', 'uses' ) + ' ' + ecv2_esc_html( p.sets.join( ', ' ) ) : '' ) + '</small></div><span class="ecv2-chip ' + ( p.full ? 'ecv2-chip-amber' : 'ecv2-chip-gray' ) + '">' + ( p.full ? _t( 'slots_full', 'Slots full' ) : ( d.advanced ? _t( 'modifier', 'Modifier' ) : ( p.slots - p.slots_used ) + ' ' + _t( 'free', 'free' ) ) ) + '</span></label>';
					} );
					if ( ! d.items.length ) { html = '<div class="ecv2-picker-empty">' + _t( 'no_matches', 'No matching products.' ) + '</div>'; }
					$( '#ecv2_as_list' ).html( html );
					$( '#ecv2_as_foot' ).text( _t( 'showing', 'Showing %1 of %2' ).replace( '%1', d.items.length ).replace( '%2', d.total ) );
				}, function( msg ) {
					/* Modifier set without PRO: the server refuses; show the upsell instead of an empty picker. */
					close_modal();
					if ( typeof window.ecdv2_upsell === 'function' ) { window.ecdv2_upsell( { context: 'products', feature: 'modifiers' } ); }
					ecv2_toast( msg, 'error' );
				} );
			}
			var t; $( '#ecv2_as_q' ).on( 'input', function() { clearTimeout( t ); t = setTimeout( load, 250 ); } );
			$m.on( 'change', '.ecv2-picker-row input', function() { var id = n( this.value ); if ( this.checked ) { selected[ id ] = 1; } else { delete selected[ id ]; } $( this ).closest( '.ecv2-picker-row' ).toggleClass( 'is-on', this.checked ); var c = Object.keys( selected ).length; $( '#ecv2_as_count' ).text( c ); $( '#ecv2_as_go' ).prop( 'disabled', ! c ); } );
			$( '#ecv2_as_go' ).on( 'click', function() {
				$( this ).prop( 'disabled', true );
				catalog_ajax( { action: 'ecv2_option_assign', nonce: NONCES.osv2, option_id: option_id, product_ids: Object.keys( selected ) }, function( d ) {
					close_modal();
					ecv2_toast( _t( 'assigned', 'Assigned to %d products.' ).replace( '%d', d.assigned ) + ( d.stock_created ? ' ' + _t( 'stock_created', '%d variant stock rows created.' ).replace( '%d', d.stock_created ) : '' ), 'success' );
					setTimeout( function() { window.location.reload(); }, 900 );
				}, function( msg ) { $( '#ecv2_as_go' ).prop( 'disabled', false ); ecv2_toast( msg, 'error' ); } );
			} );
			load();
		},

		/* ---------------- Category → products ---------------- */
		pick_products: function( category_id, on_added ) {
			var selected = {};
			var $m = modal( 'ecv2-pick', _t( 'add_products_title', 'Add products' ),
				'<div class="ecv2-picker-bar"><input type="search" class="ecv2-input" id="ecv2_pk_q" placeholder="' + _t( 'search_products', 'Search products by name or SKU…' ) + '"><select class="ecv2-select" id="ecv2_pk_scope"><option value="notin">' + _t( 'not_in_cat', 'Not in this category' ) + '</option><option value="uncategorized">' + _t( 'uncategorized', 'Uncategorized only' ) + '</option><option value="all">' + _t( 'all_products', 'All products' ) + '</option></select><span class="ecv2-chip ecv2-chip-gray" id="ecv2_pk_count">0</span></div><div class="ecv2-picker-list" id="ecv2_pk_list"></div><div class="ecv2-picker-foot" id="ecv2_pk_foot"></div>',
				'<button type="button" class="ecv2-btn" data-close>' + _t( 'cancel', 'Cancel' ) + '</button><button type="button" class="ecv2-btn ecv2-btn-primary" id="ecv2_pk_go" disabled>' + _t( 'add_to_category', 'Add to category' ) + '</button>' );
			var last = [];
			function load() {
				$( '#ecv2_pk_list' ).html( '<div class="ecv2-sd-loading"><span class="dashicons dashicons-update ecv2-spin"></span></div>' );
				catalog_ajax( { action: 'ecv2_category_product_search', wp_easycart_nonce: NONCES.inline_update, category_id: category_id, q: $( '#ecv2_pk_q' ).val(), scope: $( '#ecv2_pk_scope' ).val() }, function( d ) {
					last = d.items; var html = '';
					$.each( d.items, function( i, p ) {
						html += '<label class="ecv2-picker-row' + ( selected[ p.id ] ? ' is-on' : '' ) + '"><input type="checkbox" value="' + p.id + '"' + ( selected[ p.id ] ? ' checked' : '' ) + '>' + thumb_html( p.image ) + '<div class="ecv2-picker-main"><span>' + ecv2_esc_html( p.title ) + '</span><small>' + ecv2_esc_html( p.sku || '' ) + ' · ' + ecv2_esc_html( p.price ) + ' · ' + ( p.cats ? ecv2_esc_html( p.cats ) : _t( 'uncategorized', 'uncategorized' ) ) + '</small></div><span class="ecv2-chip ' + ( p.active ? 'ecv2-chip-green' : 'ecv2-chip-gray' ) + '">' + ( p.active ? _t( 'active', 'Active' ) : _t( 'inactive', 'Inactive' ) ) + '</span></label>';
					} );
					if ( ! d.items.length ) { html = '<div class="ecv2-picker-empty">' + _t( 'no_matches', 'No matching products.' ) + '</div>'; }
					$( '#ecv2_pk_list' ).html( html );
					$( '#ecv2_pk_foot' ).html( _t( 'showing', 'Showing %1 of %2' ).replace( '%1', d.items.length ).replace( '%2', d.total ) + ( d.items.length > 1 ? ' · <a href="#" id="ecv2_pk_all">' + _t( 'select_shown', 'Select all shown' ) + '</a>' : '' ) );
				} );
			}
			var t; $( '#ecv2_pk_q' ).on( 'input', function() { clearTimeout( t ); t = setTimeout( load, 250 ); } ); $( '#ecv2_pk_scope' ).on( 'change', load );
			$m.on( 'change', '.ecv2-picker-row input', function() { var id = n( this.value ); if ( this.checked ) { selected[ id ] = 1; } else { delete selected[ id ]; } $( this ).closest( '.ecv2-picker-row' ).toggleClass( 'is-on', this.checked ); var c = Object.keys( selected ).length; $( '#ecv2_pk_count' ).text( c ); $( '#ecv2_pk_go' ).prop( 'disabled', ! c ); } );
			$m.on( 'click', '#ecv2_pk_all', function( e ) { e.preventDefault(); $.each( last, function( i, p ) { selected[ p.id ] = 1; } ); $m.find( '.ecv2-picker-row input' ).prop( 'checked', true ).closest( '.ecv2-picker-row' ).addClass( 'is-on' ); $( '#ecv2_pk_count' ).text( Object.keys( selected ).length ); $( '#ecv2_pk_go' ).prop( 'disabled', false ); } );
			$( '#ecv2_pk_go' ).on( 'click', function() {
				$( this ).prop( 'disabled', true );
				var ids = Object.keys( selected );
				catalog_ajax( { action: 'ecv2_category_add_products', wp_easycart_nonce: NONCES.inline_update, category_id: category_id, product_ids: ids }, function( d ) {
					close_modal();
					ecv2_toast( _t( 'products_added', 'Added %d products.' ).replace( '%d', d.added ), 'success' );
					if ( typeof on_added === 'function' ) { on_added( ids, last ); } else { setTimeout( function() { window.location.reload(); }, 700 ); }
				}, function( msg ) { $( '#ecv2_pk_go' ).prop( 'disabled', false ); ecv2_toast( msg, 'error' ); } );
			} );
			load();
		},

		/* ---------------- New category ---------------- */
		new_category: function( parent_id, anchor ) {
			/* parent: the row's name when opened from "Add subcategory", otherwise a typeahead ( no whole-table list ) */
			var parent_label = n( parent_id ) ? $.trim( $( 'tr.ecv2-cat-row[data-id="' + n( parent_id ) + '"], .ecv2-card[data-id="' + n( parent_id ) + '"]' ).first().find( '.ecv2-title-link, .ecv2-card-title a' ).first().text() ) : '';
			var $m = modal( 'ecv2-newcat', _t( 'new_category', 'New category' ),
				'<div class="ecdv2-grid"><div class="ecdv2-field ecdv2-field-full"><label class="ecdv2-label">' + _t( 'name', 'Name' ) + '</label><input type="text" class="ecv2-input" id="ecv2_nc_name" placeholder="' + _t( 'name_ph', 'e.g. Accessories' ) + '"></div><div class="ecdv2-field"><label class="ecdv2-label">' + _t( 'parent', 'Parent' ) + '</label><div id="ecv2_nc_parent_mount"></div></div><div class="ecdv2-field"><label class="ecdv2-label">' + _t( 'visibility', 'Visibility' ) + '</label><label class="ecos-toggle-row"><span class="ecv2-toggle"><input type="checkbox" id="ecv2_nc_active" checked><span class="ecv2-toggle-slider"></span></span><span class="ecos-toggle-t">' + _t( 'active', 'Active' ) + '</span></label></div></div><p class="ecos-hint" style="margin:12px 0 0">' + _t( 'nc_hint', 'You can add products, images and a description after creating.' ) + '</p>',
				'<button type="button" class="ecv2-btn" data-close>' + _t( 'cancel', 'Cancel' ) + '</button><button type="button" class="ecv2-btn" id="ecv2_nc_create">' + _t( 'create', 'Create' ) + '</button><button type="button" class="ecv2-btn ecv2-btn-primary" id="ecv2_nc_edit">' + _t( 'create_edit', 'Create & edit' ) + '</button>', { size: 'sm', anchor: anchor || null });
			var parent_pick = cat_picker( $( '#ecv2_nc_parent_mount' ), { id: 'ecv2_nc_parent', value: n( parent_id ), label: parent_label || ( n( parent_id ) ? '#' + n( parent_id ) : '' ) } );
			setTimeout( function() { $( '#ecv2_nc_name' ).trigger( 'focus' ); }, 100 );
			function go( then_edit ) {
				var name = $.trim( $( '#ecv2_nc_name' ).val() );
				if ( ! name ) { $( '#ecv2_nc_name' ).addClass( 'is-invalid' ).trigger( 'focus' ); return; }
				$( '#ecv2_nc_create, #ecv2_nc_edit' ).prop( 'disabled', true );
				catalog_ajax( { action: 'ecv2_category_create', wp_easycart_nonce: NONCES.inline_update, name: name, parent_id: parent_pick.val(), is_active: $( '#ecv2_nc_active' ).is( ':checked' ) ? 1 : 0 }, function( d ) {
					close_modal();
					if ( then_edit ) { window.location.href = d.edit_url; return; }
					ecv2_toast( _t( 'created', 'Created “%s”' ).replace( '%s', name ), 'success' );
					setTimeout( function() { window.location.reload(); }, 600 );
				}, function( msg ) { $( '#ecv2_nc_create, #ecv2_nc_edit' ).prop( 'disabled', false ); ecv2_toast( msg, 'error' ); } );
			}
			$( '#ecv2_nc_create' ).on( 'click', function() { go( false ); } );
			$( '#ecv2_nc_edit' ).on( 'click', function() { go( true ); } );
			$( '#ecv2_nc_name' ).on( 'keydown', function( e ) { if ( e.key === 'Enter' ) { go( true ); } } );
		},

		/* ---------------- Menus ---------------- */
		menu_row: function( link ) { return $( link ).closest( 'tr.ecv2-menu-row, .ecv2-row' ); },
		menu_edit: function( link ) { var $r = ecv2_catalog.menu_row( link ); if ( $r.data( 'edit-url' ) ) { window.location.href = $r.data( 'edit-url' ); } return false; },
		menu_add_sub: function( link ) { var $r = ecv2_catalog.menu_row( link ); if ( n( $r.data( 'menu-level' ) ) >= 3 ) { ecv2_toast( _t( 'menu_depth', 'Menus go three levels deep at most.' ), 'info' ); return false; } ecv2_catalog.new_menu( String( $r.data( 'id' ) ), n( $r.data( 'menu-level' ) ) ); return false; },
		menu_products: function( link ) { var $r = ecv2_catalog.menu_row( link ); window.location.href = 'admin.php?page=wp-easycart-products&subpage=products&menu=' + $r.data( 'menu-level' ) + ':' + $r.data( 'menu-id' ); return false; },
		menu_view: function( link ) { var $r = ecv2_catalog.menu_row( link ); if ( ! $r.data( 'slug' ) ) { ecv2_toast( _t( 'no_page', 'This item has no page yet — save it once in the editor.' ), 'info' ); return false; } $( link ).attr( 'href', ( VARS.store_base || '/store/' ) + $r.data( 'slug' ) + '/' ); return true; },
		menu_delete: function( link ) { var $r = ecv2_catalog.menu_row( link ); ecv2_catalog.safe_delete( 'menu' + $r.data( 'menu-level' ), n( $r.data( 'menu-id' ) ), { row: $r } ); return false; },
		view_row_slug: function( link ) { var $r = $( link ).closest( '.ecv2-row, .ecv2-card' ); if ( ! $r.data( 'slug' ) ) { ecv2_toast( _t( 'no_page', 'This item has no page yet — save it once in the editor.' ), 'info' ); return false; } $( link ).attr( 'href', ( VARS.store_base || '/store/' ) + $r.data( 'slug' ) + '/' ); return true; },

		/* parent_key: "" for a top-level menu, "1:id" or "2:id" for a sub-menu. parent_level: 0|1|2 */
		new_menu: function( parent_key, parent_level, anchor ) {
			var menus = VARS.menus || [], parent_listed = ( parent_key === '' );
			var opts = '<option value="">' + _t( 'top_level_menu', '— Top-level menu —' ) + '</option>';
			$.each( menus, function( i, m ) { if ( m.level <= 2 ) { if ( m.value === parent_key ) { parent_listed = true; } opts += '<option value="' + esc_attr( m.value ) + '"' + ( m.value === parent_key ? ' selected' : '' ) + '>' + ecv2_esc_html( m.label ) + '</option>'; } } );
			/* the localized list is capped at 500; a parent beyond the cap ( "Add sub-menu" on a late row ) is added from the row itself */
			if ( ! parent_listed && parent_key ) { var $pr = $( 'tr.ecv2-menu-row[data-id="' + parent_key + '"], .ecv2-row[data-id="' + parent_key + '"]' ).first(); opts += '<option value="' + esc_attr( parent_key ) + '" selected>' + ecv2_esc_html( $pr.length ? tree_name( $pr[0] ) : parent_key ) + '</option>'; }
			var capped_note = VARS.menus_capped ? '<span class="ecdv2-field-desc">' + _t( 'menus_capped', 'Showing the first 500 menus. Drag rows on the Menus list to place a menu elsewhere.' ) + '</span>' : '';
			var $m = modal( 'ecv2-newmenu', _t( 'new_menu', 'New menu' ),
				'<div class="ecdv2-grid"><div class="ecdv2-field ecdv2-field-full"><label class="ecdv2-label">' + _t( 'name', 'Name' ) + '</label><input type="text" class="ecv2-input" id="ecv2_nm_name" placeholder="' + _t( 'menu_name_ph', 'e.g. Shop by Brand' ) + '"></div><div class="ecdv2-field ecdv2-field-full"><label class="ecdv2-label">' + _t( 'parent', 'Parent' ) + '</label><select class="ecv2-select" id="ecv2_nm_parent">' + opts + '</select><span class="ecdv2-field-desc">' + _t( 'menu_parent_hint', 'Top-level menus, sub-menus and sub-sub-menus — three levels at most.' ) + '</span>' + capped_note + '</div></div>',
				'<button type="button" class="ecv2-btn" data-close>' + _t( 'cancel', 'Cancel' ) + '</button><button type="button" class="ecv2-btn" id="ecv2_nm_create">' + _t( 'create', 'Create' ) + '</button><button type="button" class="ecv2-btn ecv2-btn-primary" id="ecv2_nm_edit">' + _t( 'create_edit', 'Create & edit' ) + '</button>', { size: 'sm', anchor: anchor || null });
			setTimeout( function() { $( '#ecv2_nm_name' ).trigger( 'focus' ); }, 100 );
			function go( then_edit ) {
				var name = $.trim( $( '#ecv2_nm_name' ).val() ); if ( ! name ) { $( '#ecv2_nm_name' ).addClass( 'is-invalid' ).trigger( 'focus' ); return; }
				$( '#ecv2_nm_create, #ecv2_nm_edit' ).prop( 'disabled', true );
				catalog_ajax( { action: 'ecv2_menu_create', wp_easycart_nonce: NONCES.inline_update, name: name, parent_key: $( '#ecv2_nm_parent' ).val() }, function( d ) {
					close_modal(); if ( then_edit ) { window.location.href = d.edit_url; return; }
					ecv2_toast( _t( 'created', 'Created “%s”' ).replace( '%s', name ), 'success' ); setTimeout( function() { window.location.reload(); }, 600 );
				}, function( msg ) { $( '#ecv2_nm_create, #ecv2_nm_edit' ).prop( 'disabled', false ); ecv2_toast( msg, 'error' ); } );
			}
			$( '#ecv2_nm_create' ).on( 'click', function() { go( false ); } ); $( '#ecv2_nm_edit' ).on( 'click', function() { go( true ); } );
			$( '#ecv2_nm_name' ).on( 'keydown', function( e ) { if ( e.key === 'Enter' ) { go( true ); } } );
		},

		/* ---------------- Manufacturers ---------------- */
		new_manufacturer: function( anchor ) {
			var $m = modal( 'ecv2-newmf', _t( 'new_manufacturer', 'New manufacturer' ),
				'<div class="ecdv2-field"><label class="ecdv2-label">' + _t( 'name', 'Name' ) + '</label><input type="text" class="ecv2-input" id="ecv2_nmf_name" placeholder="' + _t( 'mf_name_ph', 'e.g. Acme Co.' ) + '"><span class="ecdv2-field-desc">' + _t( 'mf_hint', 'A store page is created automatically. Assign products after creating.' ) + '</span></div>',
				'<button type="button" class="ecv2-btn" data-close>' + _t( 'cancel', 'Cancel' ) + '</button><button type="button" class="ecv2-btn" id="ecv2_nmf_create">' + _t( 'create', 'Create' ) + '</button><button type="button" class="ecv2-btn ecv2-btn-primary" id="ecv2_nmf_edit">' + _t( 'create_edit', 'Create & edit' ) + '</button>', { size: 'sm', anchor: anchor || null });
			setTimeout( function() { $( '#ecv2_nmf_name' ).trigger( 'focus' ); }, 100 );
			function go( then_edit ) {
				var name = $.trim( $( '#ecv2_nmf_name' ).val() ); if ( ! name ) { $( '#ecv2_nmf_name' ).addClass( 'is-invalid' ).trigger( 'focus' ); return; }
				$( '#ecv2_nmf_create, #ecv2_nmf_edit' ).prop( 'disabled', true );
				catalog_ajax( { action: 'ecv2_manufacturer_create', wp_easycart_nonce: NONCES.inline_update, name: name }, function( d ) {
					close_modal(); if ( then_edit ) { window.location.href = d.edit_url; return; }
					ecv2_toast( _t( 'created', 'Created “%s”' ).replace( '%s', name ), 'success' ); setTimeout( function() { window.location.reload(); }, 600 );
				}, function( msg ) { $( '#ecv2_nmf_create, #ecv2_nmf_edit' ).prop( 'disabled', false ); ecv2_toast( msg, 'error' ); } );
			}
			$( '#ecv2_nmf_create' ).on( 'click', function() { go( false ); } ); $( '#ecv2_nmf_edit' ).on( 'click', function() { go( true ); } );
			$( '#ecv2_nmf_name' ).on( 'keydown', function( e ) { if ( e.key === 'Enter' ) { go( true ); } } );
		},

		assign_manufacturer: function( manufacturer_id ) {
			var selected = {}, last = [];
			var $m = modal( 'ecv2-mfassign', _t( 'assign_products_title', 'Assign products' ),
				'<div class="ecv2-picker-bar"><input type="search" class="ecv2-input" id="ecv2_ma_q" placeholder="' + _t( 'search_products', 'Search products by name or SKU…' ) + '"><select class="ecv2-select" id="ecv2_ma_scope"><option value="unassigned">' + _t( 'mf_unassigned', 'Without a manufacturer' ) + '</option><option value="all">' + _t( 'mf_any', 'Any product (reassign)' ) + '</option></select><span class="ecv2-chip ecv2-chip-gray" id="ecv2_ma_count">0</span></div><div class="ecv2-picker-list" id="ecv2_ma_list"></div><div class="ecv2-picker-foot" id="ecv2_ma_foot"></div>',
				'<button type="button" class="ecv2-btn" data-close>' + _t( 'cancel', 'Cancel' ) + '</button><button type="button" class="ecv2-btn ecv2-btn-primary" id="ecv2_ma_go" disabled>' + _t( 'assign', 'Assign' ) + '</button>' );
			function load() {
				$( '#ecv2_ma_list' ).html( '<div class="ecv2-sd-loading"><span class="dashicons dashicons-update ecv2-spin"></span></div>' );
				catalog_ajax( { action: 'ecv2_manufacturer_assign_search', wp_easycart_nonce: NONCES.inline_update, manufacturer_id: manufacturer_id, q: $( '#ecv2_ma_q' ).val(), scope: $( '#ecv2_ma_scope' ).val() }, function( d ) {
					last = d.items; var html = '';
					$.each( d.items, function( i, p ) { html += '<label class="ecv2-picker-row' + ( selected[ p.id ] ? ' is-on' : '' ) + '"><input type="checkbox" value="' + p.id + '"' + ( selected[ p.id ] ? ' checked' : '' ) + '>' + thumb_html( p.image ) + '<div class="ecv2-picker-main"><span>' + ecv2_esc_html( p.title ) + '</span><small>' + ecv2_esc_html( p.sku || '' ) + ' · ' + ecv2_esc_html( p.price ) + ' · ' + ( p.cats ? _t( 'currently', 'currently' ) + ' ' + ecv2_esc_html( p.cats ) : _t( 'no_manufacturer', 'no manufacturer' ) ) + '</small></div><span class="ecv2-chip ' + ( p.active ? 'ecv2-chip-green' : 'ecv2-chip-gray' ) + '">' + ( p.active ? _t( 'active', 'Active' ) : _t( 'inactive', 'Inactive' ) ) + '</span></label>'; } );
					if ( ! d.items.length ) { html = '<div class="ecv2-picker-empty">' + _t( 'no_matches', 'No matching products.' ) + '</div>'; }
					$( '#ecv2_ma_list' ).html( html );
					$( '#ecv2_ma_foot' ).html( _t( 'showing', 'Showing %1 of %2' ).replace( '%1', d.items.length ).replace( '%2', d.total ) + ( d.items.length > 1 ? ' · <a href="#" id="ecv2_ma_all">' + _t( 'select_shown', 'Select all shown' ) + '</a>' : '' ) );
				} );
			}
			var t; $( '#ecv2_ma_q' ).on( 'input', function() { clearTimeout( t ); t = setTimeout( load, 250 ); } ); $( '#ecv2_ma_scope' ).on( 'change', load );
			$m.on( 'change', '.ecv2-picker-row input', function() { var id = n( this.value ); if ( this.checked ) { selected[ id ] = 1; } else { delete selected[ id ]; } $( this ).closest( '.ecv2-picker-row' ).toggleClass( 'is-on', this.checked ); var c = Object.keys( selected ).length; $( '#ecv2_ma_count' ).text( c ); $( '#ecv2_ma_go' ).prop( 'disabled', ! c ); } );
			$m.on( 'click', '#ecv2_ma_all', function( e ) { e.preventDefault(); $.each( last, function( i, p ) { selected[ p.id ] = 1; } ); $m.find( '.ecv2-picker-row input' ).prop( 'checked', true ).closest( '.ecv2-picker-row' ).addClass( 'is-on' ); $( '#ecv2_ma_count' ).text( Object.keys( selected ).length ); $( '#ecv2_ma_go' ).prop( 'disabled', false ); } );
			$( '#ecv2_ma_go' ).on( 'click', function() {
				$( this ).prop( 'disabled', true );
				catalog_ajax( { action: 'ecv2_manufacturer_assign', wp_easycart_nonce: NONCES.inline_update, manufacturer_id: manufacturer_id, product_ids: Object.keys( selected ) }, function( d ) {
					close_modal(); ecv2_toast( _t( 'products_assigned', 'Assigned %d products.' ).replace( '%d', d.added ), 'success' ); setTimeout( function() { window.location.reload(); }, 700 );
				}, function( msg ) { $( '#ecv2_ma_go' ).prop( 'disabled', false ); ecv2_toast( msg, 'error' ); } );
			} );
			load();
		},

		/* ---------------- Reviews ---------------- */
		review_set: function( id, val ) { $( '.ecv2-review-toggle[data-id="' + id + '"]' ).first().prop( 'checked', !! val ).trigger( 'change' ); },
		review_product: function( link ) { var pid = $( link ).closest( '.ecv2-row, .ecv2-card' ).data( 'product-id' ); if ( ! pid ) { ecv2_toast( _t( 'product_gone', 'That product no longer exists.' ), 'info' ); return false; } window.location.href = 'admin.php?page=wp-easycart-products&subpage=products&ec_admin_form_action=edit&product_id=' + pid; return false; },
		review_delete: function( id, redirect_to ) {
			ecv2_show_confirm( _t( 'delete_review_q', 'Delete this review?' ), _t( 'delete_review_msg', 'It is removed from the store immediately. You can undo for 15 minutes. If you only want to hide it, deny it instead.' ) ).then( function( ok ) {
				if ( ! ok ) { return; }
				catalog_ajax( { action: 'ecv2_review_delete', wp_easycart_nonce: NONCES.inline_update, review_id: id }, function( d ) {
					if ( redirect_to ) { try { sessionStorage.setItem( 'ecv2_catalog_flash', JSON.stringify( { message: d.message, review_undo: d.undo } ) ); } catch ( err ) {} window.location.href = redirect_to; return; }
					$( '.ecv2-row[data-id="' + id + '"], .ecv2-card[data-id="' + id + '"]' ).fadeOut( 200, function() { $( this ).remove(); } );
					ecv2_catalog.review_undo_toast( d.message, d.undo );
				} );
			} );
		},
		review_undo_toast: function( message, undo ) {
			var $t = $( '<div class="ecv2-toast ecv2-toast-success ecv2-toast-undo"><span class="dashicons dashicons-yes"></span> <span class="ecv2-toast-msg"></span> <a href="#">' + _t( 'undo', 'Undo' ) + '</a></div>' );
			$t.find( '.ecv2-toast-msg' ).text( message );
			$t.find( 'a' ).on( 'click', function( e ) { e.preventDefault(); catalog_ajax( { action: 'ecv2_review_restore', wp_easycart_nonce: NONCES.inline_update, undo: undo }, function( d ) { ecv2_toast( d.message, 'success' ); setTimeout( function() { window.location.reload(); }, 600 ); } ); } );
			$( '#ecv2-toast-container' ).append( $t ); setTimeout( function() { $t.fadeOut( 300, function() { $( this ).remove(); } ); }, 12000 );
		},

		/* ---------------- Move categories under… ---------------- */
		move_category: function( ids ) {
			/* typeahead target; the moved categories themselves are never offered ( the server also refuses cycles ) */
			var $m = modal( 'ecv2-move', _t( 'move_title', 'Move %d categories under…' ).replace( '%d', ids.length ).replace( /^Move 1 categories/, 'Move 1 category' ),
				'<div class="ecdv2-field"><label class="ecdv2-label">' + _t( 'new_parent', 'New parent' ) + '</label><div id="ecv2_mv_parent_mount"></div><span class="ecdv2-field-desc">' + _t( 'move_hint', 'Categories keep their products and subcategories. URLs do not change.' ) + '</span></div>',
				'<button type="button" class="ecv2-btn" data-close>' + _t( 'cancel', 'Cancel' ) + '</button><button type="button" class="ecv2-btn ecv2-btn-primary" id="ecv2_mv_go">' + _t( 'move', 'Move' ) + '</button>', 'ecv2-modal-sm' );
			var target_pick = cat_picker( $( '#ecv2_mv_parent_mount' ), { id: 'ecv2_mv_parent', exclude: $.map( ids, n ) } );
			$( '#ecv2_mv_go' ).on( 'click', function() {
				$( this ).prop( 'disabled', true );
				catalog_ajax( { action: 'ecv2_category_bulk', wp_easycart_nonce: NONCES.inline_update, ids: ids, op: 'move', target_id: target_pick.val() }, function( d ) {
					close_modal();
					ecv2_toast( ( d.errors && d.errors.length ) ? d.errors.join( ' ' ) : _t( 'moved', 'Moved %d categories.' ).replace( '%d', d.done ), d.errors && d.errors.length ? 'error' : 'success' );
					setTimeout( function() { window.location.reload(); }, 700 );
				}, function( msg ) { $( '#ecv2_mv_go' ).prop( 'disabled', false ); ecv2_toast( msg, 'error' ); } );
			} );
		}
	};

	/* Flash message after a redirecting delete ( editor → list ) */
	try {
		var flash = sessionStorage.getItem( 'ecv2_catalog_flash' );
		if ( flash ) {
			sessionStorage.removeItem( 'ecv2_catalog_flash' );
			flash = JSON.parse( flash );
			setTimeout( function() { if ( flash.review_undo ) { ecv2_catalog.review_undo_toast( flash.message, flash.review_undo ); } else { ecv2_catalog.undo_toast( flash.message, flash.trash_id ); } }, 300 );
		}
	} catch ( err ) {}

} );
